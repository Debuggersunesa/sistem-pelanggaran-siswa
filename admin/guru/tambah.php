<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kode_guru = trim($_POST['kode_guru'] ?? '');
    $nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
    $role = $_POST['role'] ?? '';
    $jabatan = trim($_POST['jabatan'] ?? '');
    $telp = trim($_POST['telp'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $aktif = $_POST['aktif'] ?? '';

    // Validasi Foto
    $foto_name = null;
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
        elseif ($file_size > 2 * 1024 * 1024) { // 2MB max
            $errors[] = 'Ukuran foto maksimal 2MB.';
        }
        else {
            // Generate unique name
            $sanitized_kode = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $kode_guru);
            $foto_name = $sanitized_kode . '_' . time() . '.' . $file_ext;
            $upload_dir = '../../uploads/guru/';

            // Create dir if not exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            if (!move_uploaded_file($file_tmp, $upload_dir . $foto_name)) {
                $errors[] = 'Gagal menyimpan file foto ke server.';
                $foto_name = null; // Reset if failed
            }
        }
    }

    // Validasi input mandatory
    if (empty($kode_guru))
        $errors[] = 'Kode Guru / NIP wajib diisi.';
    if (empty($nama_pengguna))
        $errors[] = 'Nama Lengkap wajib diisi.';
    if (empty($role))
        $errors[] = 'Role / Hak Akses wajib dipilih.';
    if (empty($username))
        $errors[] = 'Username wajib diisi.';
    if (empty($password))
        $errors[] = 'Password wajib diisi.';
    if (empty($aktif))
        $errors[] = 'Status Akun wajib dipilih.';

    // Minimal panjang password
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Password minimal terdiri dari 6 karakter.';
    }

    if (empty($errors)) {
        // Cek duplikasi Kode Guru
        $stmt_cek_kode = mysqli_prepare($conn, "SELECT kode_guru FROM guru WHERE kode_guru = ?");
        mysqli_stmt_bind_param($stmt_cek_kode, 's', $kode_guru);
        mysqli_stmt_execute($stmt_cek_kode);
        mysqli_stmt_store_result($stmt_cek_kode);
        if (mysqli_stmt_num_rows($stmt_cek_kode) > 0) {
            $errors[] = "Kode Guru / NIP '$kode_guru' sudah terdaftar. Silakan gunakan yang lain.";
        }
        mysqli_stmt_close($stmt_cek_kode);
    }

    if (empty($errors)) {
        // Cek duplikasi Username
        $stmt_cek_user = mysqli_prepare($conn, "SELECT username FROM guru WHERE username = ?");
        mysqli_stmt_bind_param($stmt_cek_user, 's', $username);
        mysqli_stmt_execute($stmt_cek_user);
        mysqli_stmt_store_result($stmt_cek_user);
        if (mysqli_stmt_num_rows($stmt_cek_user) > 0) {
            $errors[] = "Username '$username' sudah digunakan. Silakan pilih username lain.";
        }
        mysqli_stmt_close($stmt_cek_user);
    }

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $stmt_insert = mysqli_prepare($conn,
                "INSERT INTO guru (kode_guru, nama_pengguna, role, username, password, aktif, jabatan, telp, foto) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt_insert, 'sssssssss',
                $kode_guru, $nama_pengguna, $role, $username, $hashed_password, $aktif, $jabatan, $telp, $foto_name
            );

            if (!mysqli_stmt_execute($stmt_insert)) {
                throw new Exception("Gagal menyimpan data Guru: " . mysqli_stmt_error($stmt_insert));
            }
            mysqli_stmt_close($stmt_insert);

            mysqli_commit($conn);
            header('Location: index.php?success=tambah');
            exit;

        }
        catch (Exception $e) {
            mysqli_rollback($conn);
            // Hapus foto jika database gagal
            if ($foto_name && file_exists('../../uploads/guru/' . $foto_name)) {
                unlink('../../uploads/guru/' . $foto_name);
            }
            $errors[] = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Guru - SISP</title>
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
            background: linear-gradient(135deg, var(--color-main) 0%, var(--color-accent) 100%);
        }
        .form-card-header h3 {
            color: #fff;
            margin: 0;
            font-size: 18px;
        }
        .form-card-header p {
            color: rgba(255,255,255,0.75);
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
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Tambah Guru / Admin</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>

            <div class="page-content">
                <div style="margin-bottom:20px;font-size:13px;color:var(--color-text-muted);">
                    <a href="index.php" style="color:var(--color-accent);font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Guru</a>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3><i class="fas fa-user-plus" style="margin-right:8px;"></i>Form Tambah Data Guru</h3>
                        <p>Lengkapi informasi profil, hak akses dan kredensial akun untuk guru atau admin baru.</p>
                    </div>
                    <div class="form-card-body">

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

                        <!-- Added enctype for File Upload -->
                        <form method="POST" action="" enctype="multipart/form-data">
                            
                            <!-- SECTION 1: PROFIL PENGGUNA -->
                            <div class="form-section-title">
                                <i class="fas fa-id-card"></i> 1. Profil Pengguna
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="kode_guru">Kode Guru / NIP <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-hashtag"></i></span>
                                        <input type="text" id="kode_guru" name="kode_guru" class="form-control"
                                               placeholder="Contoh: G-001 atau Nomor Induk Pegawai"
                                               value="<?php echo htmlspecialchars($_POST['kode_guru'] ?? ''); ?>"
                                               required>
                                    </div>
                                    <small style="color:#64748b; margin-top:4px; display:block;">Penting: Akan digunakan sebagai identitas utama.</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="nama_pengguna">Nama Lengkap <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-user-tie"></i></span>
                                        <input type="text" id="nama_pengguna" name="nama_pengguna" class="form-control"
                                               placeholder="Beserta gelar (opsional)"
                                               value="<?php echo htmlspecialchars($_POST['nama_pengguna'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row-3">
                                <div class="form-group">
                                    <label class="form-label" for="jabatan">Jabatan</label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-briefcase"></i></span>
                                        <input type="text" id="jabatan" name="jabatan" class="form-control"
                                               placeholder="Contoh: Guru Matematika, Wakasek, dll"
                                               value="<?php echo htmlspecialchars($_POST['jabatan'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="telp">No. Telepon / WhatsApp</label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-phone"></i></span>
                                        <input type="text" id="telp" name="telp" class="form-control"
                                               placeholder="08xxxxxxxxxx"
                                               value="<?php echo htmlspecialchars($_POST['telp'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="foto">Foto Profil (Opsional)</label>
                                    <input type="file" id="foto" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg">
                                    <small style="color:#64748b; margin-top:4px; display:block;">Maksimal 2MB (JPG/PNG)</small>
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
                                        <option value="admin" <?php echo($_POST['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                        <option value="kepsek" <?php echo($_POST['role'] ?? '') === 'kepsek' ? 'selected' : ''; ?>>Kepsek</option>
                                        <option value="guru_pelajaran" <?php echo($_POST['role'] ?? '') === 'guru_pelajaran' ? 'selected' : ''; ?>>Guru Pelajaran</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="aktif">Status Akun <span class="required">*</span></label>
                                    <select id="aktif" name="aktif" class="form-control" required>
                                        <option value="">-- Pilih Status --</option>
                                        <option value="Y" <?php echo($_POST['aktif'] ?? 'Y') === 'Y' ? 'selected' : ''; ?>>Aktif (Bisa Login)</option>
                                        <option value="N" <?php echo($_POST['aktif'] ?? '') === 'N' ? 'selected' : ''; ?>>Non-Aktif (Akses Ditutup)</option>
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
                                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="password">Password Akun <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                                        <input type="password" id="password" name="password" class="form-control"
                                               placeholder="Minimal 6 karakter"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Data Guru
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
