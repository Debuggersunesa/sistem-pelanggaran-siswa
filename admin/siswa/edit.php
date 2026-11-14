<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

// Ambil NIS dari URL
$nis = isset($_GET['nis']) ? (int)$_GET['nis'] : 0;
if ($nis <= 0) {
    header('Location: index.php');
    exit;
}

// Fetch data for Tahun Ajaran
$ta_options = [];
$q_ta = "SELECT id_tahun_ajaran, tahun, aktif FROM tahun_ajaran ORDER BY tahun DESC";
$res_ta = mysqli_query($conn, $q_ta);
if ($res_ta) {
    while ($row = mysqli_fetch_assoc($res_ta)) {
        $ta_options[] = $row;
    }
}

// Fetch data for Kelas (to be used in JS)
$kelas_raw = [];
$q_kelas = "SELECT k.id_kelas, t.tingkat, pk.program_keahlian, k.rombel 
            FROM kelas k 
            JOIN tingkat t ON k.id_tingkat = t.id_tingkat 
            JOIN program_keahlian pk ON k.id_program_keahlian = pk.id_program_keahlian
            ORDER BY t.tingkat ASC, pk.program_keahlian ASC, k.rombel ASC";
$res_kelas = mysqli_query($conn, $q_kelas);
if ($res_kelas) {
    while ($row = mysqli_fetch_assoc($res_kelas)) {
        $kelas_raw[] = $row;
    }
}

// Ambil data siswa yang akan diedit (Termasuk relasi Ortru)
$query_fetch = "
    SELECT s.*, 
           ow.nama AS nama_ortu, ow.status_hubungan, ow.pekerjaan AS pekerjaan_ortu, 
           ow.no_telp AS telp_ortu, ow.alamat AS alamat_ortu
    FROM siswa s
    LEFT JOIN ortu_wali ow ON s.id_ortu_wali = ow.id_ortu_wali
    WHERE s.nis = ?
";
$stmt_fetch = mysqli_prepare($conn, $query_fetch);
mysqli_stmt_bind_param($stmt_fetch, 'i', $nis);
mysqli_stmt_execute($stmt_fetch);
$result_fetch = mysqli_stmt_get_result($stmt_fetch);
$siswa = mysqli_fetch_assoc($result_fetch);
mysqli_stmt_close($stmt_fetch);

if (!$siswa) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Data Siswa
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $alamat = trim($_POST['alamat'] ?? '');
    $new_password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? '';
    $id_kelas = $_POST['id_kelas'] ?? '';
    $tahun_ajaran_input = trim($_POST['tahun_ajaran_input'] ?? '');

    // Data Orang Tua
    $nama_ortu = trim($_POST['nama_ortu'] ?? '');
    $status_hubungan = $_POST['status_hubungan'] ?? '';
    $pekerjaan_ortu = trim($_POST['pekerjaan_ortu'] ?? '');
    $telp_ortu = trim($_POST['telp_ortu'] ?? '');
    $alamat_ortu = trim($_POST['alamat_ortu'] ?? '');

    // Validation
    if (empty($nama_siswa))
        $errors[] = 'Nama Siswa wajib diisi.';
    if (empty($jenis_kelamin))
        $errors[] = 'Jenis Kelamin wajib dipilih.';
    if (empty($alamat))
        $errors[] = 'Alamat wajib diisi.';
    if (empty($status))
        $errors[] = 'Status wajib dipilih.';

    // Validasi Orang Tua
    $has_ortu_data = !empty($nama_ortu) || !empty($status_hubungan) || !empty($telp_ortu);
    if ($has_ortu_data) {
        if (empty($nama_ortu))
            $errors[] = 'Nama Orang Tua/Wali wajib diisi.';
        if (empty($status_hubungan))
            $errors[] = 'Status Hubungan wajib dipilih.';
        if (empty($telp_ortu))
            $errors[] = 'No. Telepon Orang Tua wajib diisi.';
    }

    // Handle File Upload if exist
    $foto_name = $siswa['foto']; // Default to exact old photo
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
            $new_foto_name = $nis . '_' . time() . '.' . $file_ext;
            $upload_dir = '../../uploads/siswa/';

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

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // 1. Update/Insert Data Orang Tua
            $id_ortu_final = $siswa['id_ortu_wali'];

            if ($has_ortu_data) {
                if ($id_ortu_final) {
                    // Update existing
                    $stmt_ortu = mysqli_prepare($conn, "UPDATE ortu_wali SET nama=?, status_hubungan=?, pekerjaan=?, no_telp=?, alamat=? WHERE id_ortu_wali=?");
                    mysqli_stmt_bind_param($stmt_ortu, 'sssssi', $nama_ortu, $status_hubungan, $pekerjaan_ortu, $telp_ortu, $alamat_ortu, $id_ortu_final);
                    if (!mysqli_stmt_execute($stmt_ortu))
                        throw new Exception("Gagal update data Orang Tua.");
                    mysqli_stmt_close($stmt_ortu);
                }
                else {
                    // Insert new
                    $stmt_ortu = mysqli_prepare($conn, "INSERT INTO ortu_wali (nama, status_hubungan, pekerjaan, no_telp, alamat) VALUES (?, ?, ?, ?, ?)");
                    mysqli_stmt_bind_param($stmt_ortu, 'sssss', $nama_ortu, $status_hubungan, $pekerjaan_ortu, $telp_ortu, $alamat_ortu);
                    if (mysqli_stmt_execute($stmt_ortu)) {
                        $id_ortu_final = mysqli_insert_id($conn);
                    }
                    else {
                        throw new Exception("Gagal menyimpan data Orang Tua baru.");
                    }
                    mysqli_stmt_close($stmt_ortu);
                }
            }
            else if ($id_ortu_final) {
                // Option to delete or unlink orphaned ortru data here if desired.
                // For safety we will keep it but unlink it from student.
                $id_ortu_final = null;
            }

            // 2. Update Data Siswa
            $final_id_kelas = (!empty($id_kelas)) ? (int)$id_kelas : null;
            $final_id_ta = null;
            $id_tahun_ajaran_post = $_POST['id_tahun_ajaran'] ?? '';

            if ($id_tahun_ajaran_post === 'new_data' && !empty($tahun_ajaran_input)) {
                $stmt_cek_ta = mysqli_prepare($conn, "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE tahun = ?");
                mysqli_stmt_bind_param($stmt_cek_ta, 's', $tahun_ajaran_input);
                mysqli_stmt_execute($stmt_cek_ta);
                $res_cek_ta = mysqli_stmt_get_result($stmt_cek_ta);

                if ($row_ta = mysqli_fetch_assoc($res_cek_ta)) {
                    $final_id_ta = $row_ta['id_tahun_ajaran'];
                }
                else {
                    $stmt_ins_ta = mysqli_prepare($conn, "INSERT INTO tahun_ajaran (tahun, aktif) VALUES (?, 'N')");
                    mysqli_stmt_bind_param($stmt_ins_ta, 's', $tahun_ajaran_input);
                    if (mysqli_stmt_execute($stmt_ins_ta)) {
                        $final_id_ta = mysqli_insert_id($conn);
                    }
                    mysqli_stmt_close($stmt_ins_ta);
                }
                mysqli_stmt_close($stmt_cek_ta);
            }
            elseif (!empty($id_tahun_ajaran_post) && $id_tahun_ajaran_post !== 'new_data') {
                $final_id_ta = (int)$id_tahun_ajaran_post;
            }

            $update_query = "UPDATE siswa SET nama_siswa = ?, jenis_kelamin = ?, alamat = ?, foto = ?, status = ?, id_ortu_wali = ?, id_kelas = ?, id_tahun_ajaran = ?";
            $types = 'sssssiii';
            $params = [&$nama_siswa, &$jenis_kelamin, &$alamat, &$foto_name, &$status, &$id_ortu_final, &$final_id_kelas, &$final_id_ta];

            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_query .= ", password = ?";
                $types .= 's';
                $params[] = & $hashed_password;
            }

            $update_query .= " WHERE nis = ?";
            $types .= 'i';
            $params[] = & $nis;

            $stmt_update = mysqli_prepare($conn, $update_query);

            // Dynamic binding
            $bind_names = array_merge([$stmt_update, $types], $params);
            call_user_func_array('mysqli_stmt_bind_param', $bind_names);

            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception("Gagal memperbarui data Siswa: " . mysqli_stmt_error($stmt_update));
            }
            mysqli_stmt_close($stmt_update);

            mysqli_commit($conn);

            // Delete old photo file if a new one was uploaded successfully AND old one exists
            if ($foto_uploaded && !empty($siswa['foto']) && file_exists('../../uploads/siswa/' . $siswa['foto'])) {
                unlink('../../uploads/siswa/' . $siswa['foto']);
            }

            header('Location: index.php?success=edit');
            exit;

        }
        catch (Exception $e) {
            mysqli_rollback($conn);
            // Revert uploaded photo if transaction failed
            if ($foto_uploaded && file_exists('../../uploads/siswa/' . $foto_name)) {
                unlink('../../uploads/siswa/' . $foto_name);
            }
            $errors[] = $e->getMessage();
        }
    }

    // Perbaharui data di form jika ada error (menggunakan data dari POST)
    $siswa['nama_siswa'] = $_POST['nama_siswa'] ?? $siswa['nama_siswa'];
    $siswa['jenis_kelamin'] = $_POST['jenis_kelamin'] ?? $siswa['jenis_kelamin'];
    $siswa['alamat'] = $_POST['alamat'] ?? $siswa['alamat'];
    $siswa['status'] = $_POST['status'] ?? $siswa['status'];
    $siswa['id_kelas'] = $_POST['id_kelas'] ?? $siswa['id_kelas'];
    $siswa['nama_ortu'] = $_POST['nama_ortu'] ?? $siswa['nama_ortu'];
    $siswa['status_hubungan'] = $_POST['status_hubungan'] ?? $siswa['status_hubungan'];
    $siswa['pekerjaan_ortu'] = $_POST['pekerjaan_ortu'] ?? $siswa['pekerjaan_ortu'];
    $siswa['telp_ortu'] = $_POST['telp_ortu'] ?? $siswa['telp_ortu'];
    $siswa['alamat_ortu'] = $_POST['alamat_ortu'] ?? $siswa['alamat_ortu'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Siswa - SPPS</title>
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
        .form-control:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #e2e8f0;
        }
        .form-control[type="file"] {
            padding: 8px 12px;
            background: #fff;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 90px;
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
        .nis-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fffbeb;
            color: #b45309;
            font-weight: 700;
            padding: 8px 16px;
            border-radius: var(--radius-sm);
            font-size: 15px;
            margin-bottom: 24px;
            border: 1px dashed #f59e0b;
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
                <div class="topbar-title">Edit Siswa</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>

            <div class="page-content">

                <!-- Breadcrumb -->
                <div style="margin-bottom:20px;font-size:13px;color:var(--color-text-muted);">
                    <a href="index.php" style="color:var(--color-warning, #f59e0b);font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Siswa</a>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3><i class="fas fa-user-edit" style="margin-right:8px;"></i>Form Edit Profil Siswa</h3>
                        <p>Perbarui detail data pribadi, kelas, dan orang tua. Kosongkan password jika tidak ingin mengubah akun login.</p>
                    </div>
                    <div class="form-card-body">

                        <div class="nis-badge">
                            <i class="fas fa-id-card"></i>
                            ID / NIS Siswa: <?php echo htmlspecialchars($siswa['nis']); ?>
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

                        <form method="POST" action="edit.php?nis=<?php echo urlencode($nis); ?>" enctype="multipart/form-data">
                            
                            <!-- SECTION 1: DATA PRIBADI SISWA -->
                            <div class="form-section-title">
                                <i class="fas fa-user-circle"></i> 1. Informasi Pribadi Siswa
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="nama_siswa">Nama Lengkap Siswa <span class="required">*</span></label>
                                <div class="input-icon-wrap">
                                    <span class="input-icon"><i class="fas fa-user"></i></span>
                                    <input type="text" id="nama_siswa" name="nama_siswa" class="form-control"
                                           placeholder="Nama lengkap siswa"
                                           value="<?php echo htmlspecialchars($siswa['nama_siswa']); ?>"
                                           required>
                                </div>
                            </div>

                            <div class="form-row-3">
                                <div class="form-group">
                                    <label class="form-label" for="jenis_kelamin">Jenis Kelamin <span class="required">*</span></label>
                                    <select id="jenis_kelamin" name="jenis_kelamin" class="form-control" required>
                                        <option value="">-- Pilih --</option>
                                        <option value="Laki - Laki" <?php echo($siswa['jenis_kelamin'] === 'Laki - Laki') ? 'selected' : ''; ?>>Laki - Laki</option>
                                        <option value="Perempuan" <?php echo($siswa['jenis_kelamin'] === 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="status">Status Anak <span class="required">*</span></label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="">-- Pilih Status --</option>
                                        <option value="aktif" <?php echo($siswa['status'] === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="tidak_aktif" <?php echo($siswa['status'] === 'tidak_aktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                                        <option value="lulus" <?php echo($siswa['status'] === 'lulus') ? 'selected' : ''; ?>>Lulus</option>
                                        <option value="pindah" <?php echo($siswa['status'] === 'pindah') ? 'selected' : ''; ?>>Pindah</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="foto">Foto Profil (Ganti Foto)</label>
                                    <div class="foto-upload-group">
                                        <?php if (!empty($siswa['foto']) && file_exists('../../uploads/siswa/' . $siswa['foto'])): ?>
                                            <img src="../../uploads/siswa/<?php echo htmlspecialchars($siswa['foto']); ?>" class="preview-foto" alt="Current">
                                        <?php
else: ?>
                                            <div class="preview-foto" style="display:flex;align-items:center;justify-content:center;background:#f1f5f9;color:#94a3b8;"><i class="fas fa-user"></i></div>
                                        <?php
endif; ?>
                                        <div style="flex:1">
                                            <input type="file" id="foto" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg" style="padding:4px 8px; font-size:12px;">
                                        </div>
                                    </div>
                                    <small style="color:#64748b; margin-top:4px; display:block;">Kosongkan jika tidak mengganti foto.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="alamat">Alamat Lengkap Siswa <span class="required">*</span></label>
                                <textarea id="alamat" name="alamat" class="form-control"
                                          placeholder="Masukkan alamat domisili siswa..."
                                          required><?php echo htmlspecialchars($siswa['alamat']); ?></textarea>
                            </div>

                            <!-- SECTION 2: DATA AKADEMIK -->
                            <div class="form-section-title">
                                <i class="fas fa-graduation-cap"></i> 2. Data Akademik & Akun
                            </div>

                            <div class="form-row">
                                <?php
$val_ta_id = $_POST['id_tahun_ajaran'] ?? $siswa['id_tahun_ajaran'];
?>
                                <div class="form-group" id="container_ta">
                                    <label class="form-label" for="id_tahun_ajaran">Tahun Ajaran <span class="required">*</span></label>
                                    <div style="display:flex; gap:10px;">
                                        <select id="id_tahun_ajaran" name="id_tahun_ajaran" class="form-control" required onchange="handleTaChange(this)">
                                            <option value="">-- Pilih Tahun Ajaran --</option>
                                            <?php foreach ($ta_options as $ta): ?>
                                                <option value="<?php echo $ta['id_tahun_ajaran']; ?>" <?php echo($val_ta_id == $ta['id_tahun_ajaran']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($ta['tahun']); ?> <?php echo $ta['aktif'] === 'Y' ? '(Aktif)' : ''; ?>
                                                </option>
                                            <?php
endforeach; ?>
                                            <option value="new_data" style="font-weight:bold; color:var(--color-main);">+ Tambah Tahun Ajaran Baru...</option>
                                        </select>
                                        
                                        <input type="text" id="tahun_ajaran_input_new" name="tahun_ajaran_input" class="form-control" style="display:none;" placeholder="Ketik Tahun Ajaran baru (Cth: 2025/2026)">
                                        <button type="button" id="btn_cancel_new_ta" class="btn btn-secondary" style="display:none; padding:8px 14px;" onclick="cancelNewTa()"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="password">
                                        Password Baru Akun
                                        <span class="hint">(Biarkan kosong untuk mempertahankan password lama)</span>
                                    </label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                                        <input type="password" id="password" name="password" class="form-control"
                                               placeholder="Ketik password baru jika ingin diubah">
                                    </div>
                                </div>
                            </div>

                            <!-- Cascading Dropdowns for Kelas -->
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label class="form-label" for="sel_tingkat">Tingkat Kelas</label>
                                    <select id="sel_tingkat" class="form-control">
                                        <option value="">-- Pilih Tingkat --</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="sel_jurusan">Program Keahlian</label>
                                    <select id="sel_jurusan" class="form-control" disabled>
                                        <option value="">-- Pilih Jurusan --</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="sel_rombel">Rombel</label>
                                    <select id="sel_rombel" class="form-control" disabled>
                                        <option value="">-- Pilih Rombel --</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Hidden input to store actual id_kelas -->
                            <input type="hidden" name="id_kelas" id="id_kelas" value="<?php echo htmlspecialchars($siswa['id_kelas'] ?? ''); ?>">


                            <!-- SECTION 3: DATA ORANG TUA / WALI -->
                            <div class="form-section-title">
                                <i class="fas fa-users"></i> 3. Data Orang Tua / Wali (Opsional)
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="nama_ortu">Nama Lengkap Orang Tua/Wali</label>
                                    <input type="text" id="nama_ortu" name="nama_ortu" class="form-control"
                                           placeholder="Nama ayah/ibu/wali"
                                           value="<?php echo htmlspecialchars($siswa['nama_ortu'] ?? ''); ?>">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="status_hubungan">Status Hubungan</label>
                                        <select id="status_hubungan" name="status_hubungan" class="form-control">
                                            <option value="">-- Pilih --</option>
                                            <option value="Ayah" <?php echo(($siswa['status_hubungan'] ?? '') === 'Ayah') ? 'selected' : ''; ?>>Ayah</option>
                                            <option value="Ibu" <?php echo(($siswa['status_hubungan'] ?? '') === 'Ibu') ? 'selected' : ''; ?>>Ibu</option>
                                            <option value="Wali" <?php echo(($siswa['status_hubungan'] ?? '') === 'Wali') ? 'selected' : ''; ?>>Wali</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="telp_ortu">No. Telp / WhatsApp</label>
                                        <input type="text" id="telp_ortu" name="telp_ortu" class="form-control"
                                               placeholder="08xxxxxxxxxx"
                                               value="<?php echo htmlspecialchars($siswa['telp_ortu'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="pekerjaan_ortu">Pekerjaan</label>
                                <input type="text" id="pekerjaan_ortu" name="pekerjaan_ortu" class="form-control"
                                       placeholder="Pekerjaan orang tua..."
                                       value="<?php echo htmlspecialchars($siswa['pekerjaan_ortu'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="alamat_ortu">Alamat Domisili Orang Tua</label>
                                <textarea id="alamat_ortu" name="alamat_ortu" class="form-control"
                                          placeholder="Isi jika berbeda dengan alamat siswa..."><?php echo htmlspecialchars($siswa['alamat_ortu'] ?? ''); ?></textarea>
                            </div>


                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                                <button type="submit" class="btn" style="background-color: var(--color-warning, #f59e0b); color: #fff; border:none; padding:10px 18px; border-radius:4px; font-weight:600; cursor:pointer;">
                                    <i class="fas fa-save"></i> Perbarui Data Siswa
                                </button>
                            </div>
                        </form>

                    </div>
                </div>

            </div>
        </div>
    </div>
    
    <!-- Script for Cascading Dropdowns -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Raw data from PHP
            const kelasData = <?php echo json_encode($kelas_raw); ?>;
            
            const selTingkat = document.getElementById('sel_tingkat');
            const selJurusan = document.getElementById('sel_jurusan');
            const selRombel = document.getElementById('sel_rombel');
            const inputIdKelas = document.getElementById('id_kelas');
            
            // Extract unique Tingkat
            const uniqueTingkat = [...new Set(kelasData.map(item => item.tingkat))].sort();
            uniqueTingkat.forEach(t => {
                selTingkat.add(new Option(t, t));
            });

            // Handle Tingkat Change
            selTingkat.addEventListener('change', function() {
                selJurusan.innerHTML = '<option value="">-- Pilih Jurusan --</option>';
                selRombel.innerHTML = '<option value="">-- Pilih Rombel --</option>';
                
                // Only clear the hidden input if this was a user action, not our init code
                if(event && event.isTrusted) {
                    inputIdKelas.value = '';
                }
                
                selJurusan.disabled = !this.value;
                selRombel.disabled = true;

                if (this.value) {
                    const filteredJurusan = [...new Set(kelasData.filter(k => k.tingkat === this.value).map(k => k.program_keahlian))].sort();
                    filteredJurusan.forEach(j => {
                        selJurusan.add(new Option(j, j));
                    });
                }
            });

            // Handle Jurusan Change
            selJurusan.addEventListener('change', function() {
                selRombel.innerHTML = '<option value="">-- Pilih Rombel --</option>';
                
                if(event && event.isTrusted) {
                    inputIdKelas.value = '';
                }
                
                selRombel.disabled = !this.value;

                if (this.value) {
                    const filteredRombel = kelasData.filter(k => k.tingkat === selTingkat.value && k.program_keahlian === this.value);
                    filteredRombel.forEach(k => {
                        selRombel.add(new Option(k.rombel, k.id_kelas)); // Value is id_kelas here
                    });
                }
            });

            // Handle Rombel Change (Final selection)
            selRombel.addEventListener('change', function() {
                if(this.value) {
                    inputIdKelas.value = this.value; // Store the actual ID
                }
            });
            
            // Logic to PRE-SELECT data on Edit Form Load
            const prestoredId = inputIdKelas.value;
            if(prestoredId) {
                const targetKls = kelasData.find(k => k.id_kelas == prestoredId);
                if(targetKls) {
                    selTingkat.value = targetKls.tingkat;
                    selTingkat.dispatchEvent(new Event('change'));
                    
                    selJurusan.value = targetKls.program_keahlian;
                    selJurusan.dispatchEvent(new Event('change'));
                    
                    selRombel.value = targetKls.id_kelas;
                }
            }
        });

        function handleTaChange(selectEl) {
            if(selectEl.value === 'new_data') {
                selectEl.style.display = 'none';
                selectEl.removeAttribute('required');
                
                let inputTa = document.getElementById('tahun_ajaran_input_new');
                inputTa.style.display = 'block';
                inputTa.setAttribute('required', 'required');
                inputTa.focus();
                
                document.getElementById('btn_cancel_new_ta').style.display = 'inline-flex';
            }
        }

        function cancelNewTa() {
            let selectEl = document.getElementById('id_tahun_ajaran');
            selectEl.style.display = 'block';
            selectEl.setAttribute('required', 'required');
            selectEl.value = ''; // Reset to unselected
            
            let inputTa = document.getElementById('tahun_ajaran_input_new');
            inputTa.style.display = 'none';
            inputTa.removeAttribute('required');
            inputTa.value = '';
            
            document.getElementById('btn_cancel_new_ta').style.display = 'none';
        }
    </script>
</body>
</html>
