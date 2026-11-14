<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$errors = [];

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Data Siswa
    $nis = trim($_POST['nis'] ?? '');
    $nama_siswa = trim($_POST['nama_siswa'] ?? '');
    $jenis_kelamin = $_POST['jenis_kelamin'] ?? '';
    $alamat = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';
    $status = $_POST['status'] ?? '';
    $id_kelas = $_POST['id_kelas'] ?? '';
    $tahun_ajaran_input = trim($_POST['tahun_ajaran_input'] ?? '');

    // Data Orang Tua
    $nama_ortu = trim($_POST['nama_ortu'] ?? '');
    $status_hubungan = $_POST['status_hubungan'] ?? '';
    $pekerjaan_ortu = trim($_POST['pekerjaan_ortu'] ?? '');
    $telp_ortu = trim($_POST['telp_ortu'] ?? '');
    $alamat_ortu = trim($_POST['alamat_ortu'] ?? '');

    // Validasi Siswa
    if (empty($nis))
        $errors[] = 'NIS wajib diisi.';
    if (empty($nama_siswa))
        $errors[] = 'Nama Siswa wajib diisi.';
    if (empty($jenis_kelamin))
        $errors[] = 'Jenis Kelamin wajib dipilih.';
    if (empty($alamat))
        $errors[] = 'Alamat Siswa wajib diisi.';
    if (empty($password))
        $errors[] = 'Password wajib diisi.';
    if (empty($status))
        $errors[] = 'Status wajib dipilih.';

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
            $foto_name = $nis . '_' . time() . '.' . $file_ext;
            $upload_dir = '../../uploads/siswa/';

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

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            $id_ortu_inserted = null;

            // 1. Insert Data Orang Tua (if provided)
            if ($has_ortu_data) {
                $stmt_ortu = mysqli_prepare($conn,
                    "INSERT INTO ortu_wali (nama, status_hubungan, pekerjaan, no_telp, alamat) VALUES (?, ?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($stmt_ortu, 'sssss', $nama_ortu, $status_hubungan, $pekerjaan_ortu, $telp_ortu, $alamat_ortu);

                if (mysqli_stmt_execute($stmt_ortu)) {
                    $id_ortu_inserted = mysqli_insert_id($conn);
                }
                else {
                    throw new Exception("Gagal menyimpan data Orang Tua: " . mysqli_stmt_error($stmt_ortu));
                }
                mysqli_stmt_close($stmt_ortu);
            }

            // 2. Insert Data Siswa
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
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

            $stmt_siswa = mysqli_prepare($conn,
                "INSERT INTO siswa (nis, nama_siswa, jenis_kelamin, alamat, foto, password, status, id_ortu_wali, id_kelas, id_tahun_ajaran)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt_siswa, 'issssssiii',
                $nis, $nama_siswa, $jenis_kelamin, $alamat, $foto_name, $hashed_password, $status, $id_ortu_inserted, $final_id_kelas, $final_id_ta
            );

            if (!mysqli_stmt_execute($stmt_siswa)) {
                throw new Exception("Gagal menyimpan data Siswa: " . mysqli_stmt_error($stmt_siswa));
            }
            mysqli_stmt_close($stmt_siswa);

            mysqli_commit($conn);
            header('Location: index.php?success=tambah');
            exit;

        }
        catch (Exception $e) {
            mysqli_rollback($conn);
            // Hapus foto jika database gagal
            if ($foto_name && file_exists('../../uploads/siswa/' . $foto_name)) {
                unlink('../../uploads/siswa/' . $foto_name);
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
    <title>Tambah Siswa - SPPS</title>
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
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Tambah Siswa</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>

            <div class="page-content">
                <div style="margin-bottom:20px;font-size:13px;color:var(--color-text-muted);">
                    <a href="index.php" style="color:var(--color-accent);font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Siswa</a>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3><i class="fas fa-user-plus" style="margin-right:8px;"></i>Form Tambah Siswa Lengkap</h3>
                        <p>Lengkapi profil siswa beserta data akademik dan informasi orang tua/wali.</p>
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
                            
                            <!-- SECTION 1: DATA PRIBADI SISWA -->
                            <div class="form-section-title">
                                <i class="fas fa-user-circle"></i> 1. Informasi Pribadi Siswa
                            </div>
                            
                            <div class="form-row-3">
                                <div class="form-group">
                                    <label class="form-label" for="nis">NIS <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-id-card"></i></span>
                                        <input type="number" id="nis" name="nis" class="form-control"
                                               placeholder="Nomor Induk Siswa"
                                               value="<?php echo htmlspecialchars($_POST['nis'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label" for="nama_siswa">Nama Lengkap Siswa <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-user"></i></span>
                                        <input type="text" id="nama_siswa" name="nama_siswa" class="form-control"
                                               placeholder="Nama lengkap siswa"
                                               value="<?php echo htmlspecialchars($_POST['nama_siswa'] ?? ''); ?>"
                                               required>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row-3">
                                <div class="form-group">
                                    <label class="form-label" for="jenis_kelamin">Jenis Kelamin <span class="required">*</span></label>
                                    <select id="jenis_kelamin" name="jenis_kelamin" class="form-control" required>
                                        <option value="">-- Pilih --</option>
                                        <option value="Laki - Laki" <?php echo(($_POST['jenis_kelamin'] ?? '') === 'Laki - Laki') ? 'selected' : ''; ?>>Laki - Laki</option>
                                        <option value="Perempuan" <?php echo(($_POST['jenis_kelamin'] ?? '') === 'Perempuan') ? 'selected' : ''; ?>>Perempuan</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="status">Status Anak <span class="required">*</span></label>
                                    <select id="status" name="status" class="form-control" required>
                                        <option value="">-- Pilih Status --</option>
                                        <option value="aktif" <?php echo(($_POST['status'] ?? 'aktif') === 'aktif') ? 'selected' : ''; ?>>Aktif</option>
                                        <option value="tidak_aktif" <?php echo(($_POST['status'] ?? '') === 'tidak_aktif') ? 'selected' : ''; ?>>Tidak Aktif</option>
                                        <option value="lulus" <?php echo(($_POST['status'] ?? '') === 'lulus') ? 'selected' : ''; ?>>Lulus</option>
                                        <option value="pindah" <?php echo(($_POST['status'] ?? '') === 'pindah') ? 'selected' : ''; ?>>Pindah</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="foto">Foto Profil (Opsional)</label>
                                    <input type="file" id="foto" name="foto" class="form-control" accept="image/png, image/jpeg, image/jpg">
                                    <small style="color:#64748b; margin-top:4px; display:block;">Maksimal 2MB (JPG/PNG)</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="alamat">Alamat Lengkap Siswa <span class="required">*</span></label>
                                <textarea id="alamat" name="alamat" class="form-control"
                                          placeholder="Masukkan alamat domisili siswa..."
                                          required><?php echo htmlspecialchars($_POST['alamat'] ?? ''); ?></textarea>
                            </div>

                            <!-- SECTION 2: DATA AKADEMIK -->
                            <div class="form-section-title">
                                <i class="fas fa-graduation-cap"></i> 2. Data Akademik & Akun
                            </div>

                            <div class="form-row">
                                <?php
$default_ta_id = '';
foreach ($ta_options as $ta) {
    if ($ta['aktif'] === 'Y') {
        $default_ta_id = $ta['id_tahun_ajaran'];
        break;
    }
}
$selected_ta = $_POST['id_tahun_ajaran'] ?? $default_ta_id;
?>
                                <div class="form-group" id="container_ta">
                                    <label class="form-label" for="id_tahun_ajaran">Tahun Ajaran <span class="required">*</span></label>
                                    <div style="display:flex; gap:10px;">
                                        <select id="id_tahun_ajaran" name="id_tahun_ajaran" class="form-control" required onchange="handleTaChange(this)">
                                            <option value="">-- Pilih Tahun Ajaran --</option>
                                            <?php foreach ($ta_options as $ta): ?>
                                                <option value="<?php echo $ta['id_tahun_ajaran']; ?>" <?php echo($selected_ta == $ta['id_tahun_ajaran']) ? 'selected' : ''; ?>>
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
                                    <label class="form-label" for="password">Password Akun Siswa <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-lock"></i></span>
                                        <input type="password" id="password" name="password" class="form-control"
                                               placeholder="Minimal 6 karakter"
                                               required>
                                    </div>
                                    <small style="color:#64748b; margin-top:4px; display:block;">Digunakan siswa untuk login ke sistem.</small>
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
                            <input type="hidden" name="id_kelas" id="id_kelas" value="<?php echo htmlspecialchars($_POST['id_kelas'] ?? ''); ?>">


                            <!-- SECTION 3: DATA ORANG TUA / WALI -->
                            <div class="form-section-title">
                                <i class="fas fa-users"></i> 3. Data Orang Tua / Wali (Opsional)
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="nama_ortu">Nama Lengkap Orang Tua/Wali</label>
                                    <input type="text" id="nama_ortu" name="nama_ortu" class="form-control"
                                           placeholder="Nama ayah/ibu/wali"
                                           value="<?php echo htmlspecialchars($_POST['nama_ortu'] ?? ''); ?>">
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label class="form-label" for="status_hubungan">Status Hubungan</label>
                                        <select id="status_hubungan" name="status_hubungan" class="form-control">
                                            <option value="">-- Pilih --</option>
                                            <option value="Ayah" <?php echo(($_POST['status_hubungan'] ?? '') === 'Ayah') ? 'selected' : ''; ?>>Ayah</option>
                                            <option value="Ibu" <?php echo(($_POST['status_hubungan'] ?? '') === 'Ibu') ? 'selected' : ''; ?>>Ibu</option>
                                            <option value="Wali" <?php echo(($_POST['status_hubungan'] ?? '') === 'Wali') ? 'selected' : ''; ?>>Wali</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="form-label" for="telp_ortu">No. Telp / WhatsApp</label>
                                        <input type="text" id="telp_ortu" name="telp_ortu" class="form-control"
                                               placeholder="08xxxxxxxxxx"
                                               value="<?php echo htmlspecialchars($_POST['telp_ortu'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="pekerjaan_ortu">Pekerjaan</label>
                                <input type="text" id="pekerjaan_ortu" name="pekerjaan_ortu" class="form-control"
                                       placeholder="Pekerjaan orang tua..."
                                       value="<?php echo htmlspecialchars($_POST['pekerjaan_ortu'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="alamat_ortu">Alamat Domisili Orang Tua</label>
                                <textarea id="alamat_ortu" name="alamat_ortu" class="form-control"
                                          placeholder="Isi jika berbeda dengan alamat siswa..."><?php echo htmlspecialchars($_POST['alamat_ortu'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Siswa Baru
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
                inputIdKelas.value = '';
                
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
                inputIdKelas.value = '';
                
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
                inputIdKelas.value = this.value; // Store the actual ID
            });
            
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
            selectEl.value = '';
            
            let inputTa = document.getElementById('tahun_ajaran_input_new');
            inputTa.style.display = 'none';
            inputTa.removeAttribute('required');
            inputTa.value = '';
            
            document.getElementById('btn_cancel_new_ta').style.display = 'none';
        }
    </script>
</body>
</html>
