<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

// Ambil parameter kode dari URL
$kode = $_GET['kode'] ?? '';

if (empty($kode)) {
    header('Location: index.php');
    exit;
}

// Fetch data guru yang akan di-edit
$kode_clean = mysqli_real_escape_string($conn, $kode);
$query_fetch = "SELECT * FROM guru WHERE kode_guru = ?";
$stmt_fetch = mysqli_prepare($conn, $query_fetch);
mysqli_stmt_bind_param($stmt_fetch, 's', $kode_clean);
mysqli_stmt_execute($stmt_fetch);
$result_fetch = mysqli_stmt_get_result($stmt_fetch);
$guru = mysqli_fetch_assoc($result_fetch);
mysqli_stmt_close($stmt_fetch);

if (!$guru) {
    // Jika tidak ditemukan, kembalikan ke daftar
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $role = $_POST['role'] ?? '';
    $jabatan = trim($_POST['jabatan'] ?? '');
    $telp = trim($_POST['telp'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $aktif = $_POST['aktif'] ?? '';

    // Handle File Upload if exist
    $foto_name = $guru['foto'] ?? null; // Default to exact old photo, using null coalesce
    $foto_uploaded = false;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file_tmp = $_FILES['foto']['tmp_name'];
        $file_name = $_FILES['foto']['name'];
        $file_size = $_FILES['foto']['size'];
        $file_error = $_FILES['foto']['error'];

        $allowed_ext = ['jpg', 'jpeg', 'png'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if ($file_error !== UPLOAD_ERR_OK) {
            $errors[] = 'Terjadi kesalahan saat upload foto.';
        }
        elseif (!in_array($file_ext, $allowed_ext)) {
            $errors[] = 'Format foto harus JPG, JPEG, atau PNG.';
        }
        elseif ($file_size > 2 * 1024 * 1024) {
            $errors[] = 'Ukuran foto maksimal 2MB.';
        }
        else {
            $sanitized_kode = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $kode_clean);
            $new_foto_name = $sanitized_kode . '_' . time() . '.' . $file_ext;
            $upload_dir = '../../uploads/guru/';

            if (!is_dir($upload_dir))
                mkdir($upload_dir, 0777, true);

            if (move_uploaded_file($file_tmp, $upload_dir . $new_foto_name)) {
                $foto_name = $new_foto_name; // Set new name for DB
                $foto_uploaded = true;
            }
            else {
                $errors[] = 'Gagal menyimpan file foto ke server.';
            }
        }
    }

    // Validasi input mandatory
    if (empty($nama_pengguna))
        $errors[] = 'Nama Lengkap wajib diisi.';
    if (empty($role))
        $errors[] = 'Role / Hak Akses wajib dipilih.';
    if (empty($username))
        $errors[] = 'Username wajib diisi.';
    if (empty($aktif))
        $errors[] = 'Status Akun wajib dipilih.';

    // Minimal panjang password jika diisi
    if (!empty($new_password) && strlen($new_password) < 6) {
        $errors[] = 'Password minimal terdiri dari 6 karakter.';
    }

    if (empty($errors)) {
        // Cek duplikasi Username untuk pengguna LAIN
        $stmt_cek_user = mysqli_prepare($conn, "SELECT username FROM guru WHERE username = ? AND kode_guru != ?");
        mysqli_stmt_bind_param($stmt_cek_user, 'ss', $username, $kode_clean);
        mysqli_stmt_execute($stmt_cek_user);
        mysqli_stmt_store_result($stmt_cek_user);
        if (mysqli_stmt_num_rows($stmt_cek_user) > 0) {
            $errors[] = "Username '$username' sudah digunakan oleh pengguna lain. Silakan pilih username lain.";
        }
        mysqli_stmt_close($stmt_cek_user);
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $update_query = "UPDATE guru SET nama_pengguna=?, role=?, jabatan=?, telp=?, username=?, aktif=?, foto=? ";
            $types = 'sssssss';
            $params = [&$nama_pengguna, &$role, &$jabatan, &$telp, &$username, &$aktif, &$foto_name];

            // Jika password baru diisi, tambahkan ke update
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query .= ", password=? ";
                $types .= 's';
                $params[] = & $hashed_password;
            }

            $update_query .= " WHERE kode_guru=?";
            $types .= 's';
            $params[] = & $kode_clean;

            $stmt_update = mysqli_prepare($conn, $update_query);

            // Dynamic binding
            $bind_names = array_merge([$stmt_update, $types], $params);
            call_user_func_array('mysqli_stmt_bind_param', $bind_names);

            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("Gagal memperbarui data Guru: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);

            mysqli_commit($conn);

            // Delete old photo file if a new one was uploaded successfully AND old one exists
            $old_foto = $guru['foto'] ?? '';
            if ($foto_uploaded && !empty($old_foto) && file_exists('../../uploads/guru/' . $old_foto)) {
                unlink('../../uploads/guru/' . $old_foto);
            }

            header('Location: index.php?success=edit');
            exit;

        }
        catch (Exception $e) {
            mysqli_rollback($conn);
            // Revert uploaded photo if transaction failed
            if ($foto_uploaded && file_exists('../../uploads/guru/' . $foto_name)) {
                unlink('../../uploads/guru/' . $foto_name);
            }
            $errors[] = $e->getMessage();
        }
    }

    // Perbaharui template data dari post jika ada error agar nilai yang diketik tidak hilang
    $guru['nama_pengguna'] = $_POST['nama_pengguna'] ?? $guru['nama_pengguna'];
    $guru['role'] = $_POST['role'] ?? $guru['role'];
    $guru['jabatan'] = $_POST['jabatan'] ?? $guru['jabatan'];
    $guru['telp'] = $_POST['telp'] ?? $guru['telp'];
    $guru['username'] = $_POST['username'] ?? $guru['username'];
    $guru['aktif'] = $_POST['aktif'] ?? $guru['aktif'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Guru - SISP</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            max-width: 850px;
            margin: 0 auto;
            overflow: hidden;
        }
        .form-card-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--color-border);
            /* Warna warning/orange untuk membedakan Edit dengan Tambah */
            background: linear-gradient(135deg, var(--color-warning, #f59e0b) 0%, #d97706 100%);
        }
        .form-card-header h3 {
            color: #fff;
            margin: 0;
            font-size: 18px;
        }
        .form-card-header p {
            color: rgba(255,255,255,0.85);
            margin: 4px 0 0;
            font-size: 13px;
        }
        .form-card-body {
            padding: 32px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-main);
            margin-top: 32px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--color-bg);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section-title:first-child {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 22px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 8px;
        }
        .form-label span.required {
            color: var(--color-danger);
            margin-left: 2px;
        }
        .form-label .hint {
            font-weight: 400;
            color: var(--color-text-muted);
            font-size: 11px;
            margin-left: 6px;
        }
        .form-control {
            width: 100%;
            padding: 11px 16px;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--color-text);
            background: var(--color-bg);
            transition: var(--transition);
            outline: none;
        }
        .form-control:focus {
            border-color: var(--color-accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(63,114,175,0.12);
        }
        .form-control[type="file"] {
            padding: 8px 12px;
            background: #fff;
        }
        .form-control:disabled,
        .form-control.read-only {
            opacity: 0.7;
            cursor: not-allowed;
            background: #e2e8f0;
            border-color: #cbd5e1;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 16px;
            margin-top: 32px;
            border-top: 1px solid var(--color-border);
            justify-content: flex-end;
        }
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            border-radius: var(--radius-md);
            padding: 14px 20px;
            margin-bottom: 24px;
        }
        .alert-danger ul {
            margin: 6px 0 0 16px;
            list-style: disc;
        }
        .input-icon-wrap {
            position: relative;
        }
        .input-icon-wrap .form-control {
            padding-left: 40px;
        }
        .input-icon-wrap .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-muted);
            font-size: 14px;
        }
        
        .kode-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            color: var(--navy-700);
            font-weight: 700;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-size: 15px;
            margin-bottom: 24px;
            border: 1px solid #cbd5e1;
            font-family: monospace;
        }
        
        /* Current Photo Preview */
        .preview-foto {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
        }
        .foto-upload-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Edit Guru / Admin</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>

            <div class="page-content">
                <div style="margin-bottom:20px;font-size:13px;color:var(--color-text-muted);">
                    <a href="index.php" style="color:var(--color-warning, #f59e0b);font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Guru</a>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3><i class="fas fa-user-edit" style="margin-right:8px;"></i>Form Edit Data Guru</h3>
                        <p>Perbarui informasi profil, hak akses dan kredensial akun. Kosongkan field kata sandi jika tidak mengubahnya.</p>
                    </div>
                    <div class="form-card-body">

                        <div class="kode-badge">
                            <i class="fas fa-id-badge" style="color:var(--navy-500)"></i>
                            NIP / KODE GURU: <?php echo htmlspecialchars($guru['kode_guru']); ?>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert-danger">
                                <strong><i class="fas fa-exclamation-triangle"></i> Terdapat kesalahan:</strong>
                                <ul>
                                    <?php foreach ($errors as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php
    endforeach; ?>
                                </ul>
                            </div>
                        <?php
endif; ?>

                        <form method="POST" action="edit.php?kode=<?php echo urlencode($kode); ?>" enctype="multipart/form-data">
                            
                            <!-- SECTION 1: PROFIL PENGGUNA -->
                            <div class="form-section-title">
                                <i class="fas fa-id-card"></i> 1. Profil Pengguna
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="nama_pengguna">Nama Lengkap <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <span class="input-icon"><i class="fas fa-user-tie"></i></span>
                                    <input type="text" id="nama_pengguna" name="nama_pengguna" class="form-control"
                                            placeholder="Beserta gelar (opsional)"
                                            value="<?php echo htmlspecialchars($guru['nama_pengguna']); ?>"
                                            required>
                                </div>
                            </div>

                            <div class="form-row-3">
                                <div class="form-group">
                                    <label class="form-label" for="jabatan">Jabatan</label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-briefcase"></i></span>
                                        <input type="text" id="jabatan" name="jabatan" class="form-control"
                                               placeholder="Contoh: Guru Matematika, Wakasek, dll"
                                               value="<?php echo htmlspecialchars($guru['jabatan'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="telp">No. Telepon / WhatsApp</label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                                        <input type="text" id="telp" name="telp" class="form-control"
                                               placeholder="08xxxxxxxxxx"
                                               value="<?php echo htmlspecialchars($guru['telp'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="foto">Foto Profil (Ganti Foto)</label>
                                    <div class="foto-upload-group">
                                        <?php if (!empty($guru['foto']) && file_exists('../../uploads/guru/' . $guru['foto'])): ?>
                                            <img src="../../uploads/guru/<?php echo htmlspecialchars($guru['foto']); ?>" class="preview-foto" alt="Current">
                                        <?php
else: ?>
                                            <div class="preview-foto" style="display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#94a3b8;"><i class="fas fa-user-tie"></i></div>
                                        <?php
endif; ?>
                                        <div style="flex:1">
                                            <input type="file" id="foto" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding:4px 8px; font-size:12px;">
                                        </div>
                                    </div>
                                    <small style="color:#64748b; margin-top:4px; display:block;">Kosongkan jika tidak mengganti foto.</small>
                                </div>
                            </div>

                            <!-- SECTION 2: HAK AKSES & KREDENSIAL -->
                            <div class="form-section-title">
                                <i class="fas fa-shield-alt"></i> 2. Hak Akses & Kredensial Akun
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="role">Role / Hak Akses <span class="required">*</span></label>
                                    <select id="role" name="role" class="form-control" required>
                                        <option value="">-- Pilih Role --</option>
                                        <option value="admin" <?php echo($guru['role'] === 'admin') ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="kepsek" <?php echo($guru['role'] === 'kepsek') ? 'selected' : ''; ?>>Kepsek</option>
                                        <option value="guru_pelajaran" <?php echo($guru['role'] === 'guru_pelajaran') ? 'selected' : ''; ?>>Guru Pelajaran</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="aktif">Status Akun <span class="required">*</span></label>
                                    <select id="aktif" name="aktif" class="form-control" required>
                                        <option value="">-- Pilih Status --</option>
                                        <option value="Y" <?php echo($guru['aktif'] === 'Y') ? 'selected' : ''; ?>>Aktif (Bisa Login)</option>
                                        <option value="N" <?php echo($guru['aktif'] === 'N') ? 'selected' : ''; ?>>Non-Aktif (Akses Ditutup)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="username">Username Akun <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-at"></i></span>
                                        <input type="text" id="username" name="username" class="form-control"
                                               placeholder="Username untuk login"
                                               value="<?php echo htmlspecialchars($guru['username']); ?>"
                                               required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="password">
                                        Password Baru Akun
                                        <span class="hint">(Kosongkan jika tidak diubah)</span>
                                    </label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                                        <input type="password" id="password" name="password" class="form-control"
                                               placeholder="Ketik password baru jika ingin mengubah">
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary" style="background-color: var(--color-warning, #f59e0b); border-color: var(--color-warning, #f59e0b);">
                                    <i class="fas fa-save"></i> Perbarui Data Guru
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
