<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$errors = [];
$msg = '';

$id = $_GET['id'] ?? '';

if (empty($id)) {
    header("Location: index.php");
    exit;
}

$id_clean = mysqli_real_escape_string($conn, $id);

// Fetch existing data
$query_get = "SELECT tanggal, nis, id_jenis_pelanggaran, id_tahun_ajaran, keterangan FROM pelanggaran_siswa WHERE id_pelanggaran_siswa = '$id_clean'";
$result_get = mysqli_query($conn, $query_get);

if (!$result_get || mysqli_num_rows($result_get) === 0) {
    header("Location: index.php");
    exit;
}

$data = mysqli_fetch_assoc($result_get);

// Fetch Siswa Aktif for Dropdown
$siswa_options = [];
$res_siswa = mysqli_query($conn, "SELECT nis, nama_siswa FROM siswa WHERE status IN('aktif') ORDER BY nama_siswa ASC");
if ($res_siswa) {
    while ($row = mysqli_fetch_assoc($res_siswa)) {
        $siswa_options[] = $row;
    }
}

// Fetch Jenis Pelanggaran
$jenis_options = [];
$res_jp = mysqli_query($conn, "SELECT id_jenis_pelanggaran, jenis, poin FROM jenis_pelanggaran ORDER BY jenis ASC");
if ($res_jp) {
    while ($row = mysqli_fetch_assoc($res_jp)) {
        $jenis_options[] = $row;
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal = $_POST['tanggal'] ?? '';
    $nis = $_POST['nis'] ?? '';
    $id_jenis_pelanggaran = $_POST['id_jenis_pelanggaran'] ?? '';
    $keterangan = trim($_POST['keterangan'] ?? '');

    if (empty($tanggal)) {
        $errors[] = 'Tanggal Pelanggaran wajib diisi.';
    }
    if (empty($nis)) {
        $errors[] = 'Siswa wajib dipilih.';
    }
    if (empty($id_jenis_pelanggaran)) {
        $errors[] = 'Jenis Pelanggaran wajib dipilih.';
    }

    if (empty($errors)) {
        $query = "UPDATE pelanggaran_siswa SET tanggal = ?, nis = ?, id_jenis_pelanggaran = ?, keterangan = ? WHERE id_pelanggaran_siswa = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'ssisi', $tanggal, $nis, $id_jenis_pelanggaran, $keterangan, $id_clean);

        if (mysqli_stmt_execute($stmt)) {
            $msg = 'Data riwayat pelanggaran berhasil diperbarui.';
            // Update the local array so form doesn't keep old data
            $data['tanggal'] = $tanggal;
            $data['nis'] = $nis;
            $data['id_jenis_pelanggaran'] = $id_jenis_pelanggaran;
            $data['keterangan'] = $keterangan;
        }
        else {
            $errors[] = 'Gagal menyimpan data ke database: ' . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Pelanggaran - SPPS</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Select2 Component -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
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
        textarea.form-control {
            resize: vertical;
            min-height: 90px;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
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
        
        .alert-success {
            background: #f0fdf4;
            border: 1px solid #86efac;
            color: #166534;
            border-radius: var(--radius-md);
            padding: 14px 20px;
            margin-bottom: 24px;
        }

        /* Select2 Theme Override */
        .select2-container--default .select2-selection--single {
            height: 44px;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            background: var(--color-bg);
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--color-text);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            padding-left: 16px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 42px;
            right: 12px;
        }
        .select2-container--open .select2-selection--single {
            border-color: var(--color-accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(63,114,175,0.12);
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Edit Riwayat Pelanggaran</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>
            
            <div class="page-content">
                <div style="margin-bottom:20px;font-size:13px;color:var(--color-text-muted);">
                    <a href="index.php" style="color:var(--color-accent);font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Pelanggaran</a>
                </div>
                
                <div class="form-card">
                    <div class="form-card-header">
                        <h3><i class="fas fa-edit" style="margin-right:8px;"></i>Form Edit Pelanggaran Siswa</h3>
                        <p>Ubah data kejadian pelanggaran tata tertib sekolah.</p>
                    </div>
                    <div class="form-card-body">
                        <?php if ($msg): ?>
                            <div class="alert-success">
                                <strong><i class="fas fa-check-circle"></i> Berhasil!</strong>
                                <p style="margin: 4px 0 0;"><?php echo htmlspecialchars($msg); ?></p>
                            </div>
                        <?php
endif; ?>

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
                        
                        <form method="POST" action="">
                            <div class="form-section-title">
                                <i class="fas fa-user-graduate"></i> Identitas & Kasus Pelanggaran
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label" for="nis">Nama Siswa / NIS <span class="required">*</span></label>
                                    <select id="nis" name="nis" class="form-control select2-siswa" required>
                                        <option value=""></option>
                                        <?php foreach ($siswa_options as $siswa): ?>
                                            <option value="<?php echo htmlspecialchars($siswa['nis']); ?>" <?php echo(($_POST['nis'] ?? $data['nis']) === $siswa['nis']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($siswa['nama_siswa']) . ' - NIS: ' . htmlspecialchars($siswa['nis']); ?>
                                            </option>
                                        <?php
endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="id_jenis_pelanggaran">Jenis Pelanggaran <span class="required">*</span></label>
                                    <select id="id_jenis_pelanggaran" name="id_jenis_pelanggaran" class="form-control select2-jenis" required>
                                        <option value=""></option>
                                        <?php foreach ($jenis_options as $j): ?>
                                            <option value="<?php echo htmlspecialchars($j['id_jenis_pelanggaran']); ?>" <?php echo(($_POST['id_jenis_pelanggaran'] ?? $data['id_jenis_pelanggaran']) == $j['id_jenis_pelanggaran']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($j['jenis']) . ' (Poin: ' . $j['poin'] . ')'; ?>
                                            </option>
                                        <?php
endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="tanggal">Tanggal & Waktu Kejadian <span class="required">*</span></label>
                                    <?php
$tgl_value = $_POST['tanggal'] ?? $data['tanggal'];
$dt = new DateTime($tgl_value);
$fmt_tgl = $dt->format('Y-m-d\TH:i');
?>
                                    <input type="datetime-local" id="tanggal" name="tanggal" class="form-control" 
                                           value="<?php echo htmlspecialchars($fmt_tgl); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="keterangan">Keterangan / Kronologi Tambahan (Opsional)</label>
                                <textarea id="keterangan" name="keterangan" class="form-control" 
                                          placeholder="Jelaskan detail kejadian..."><?php echo htmlspecialchars($_POST['keterangan'] ?? $data['keterangan']); ?></textarea>
                            </div>
                            
                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Perubahan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery required for Select2 -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2-siswa').select2({
                placeholder: "-- Cari Nama / NIS Siswa --",
                width: '100%'
            });
            $('.select2-jenis').select2({
                placeholder: "-- Cari Jenis Pelanggaran --",
                width: '100%'
            });
        });
    </script>
</body>
</html>
