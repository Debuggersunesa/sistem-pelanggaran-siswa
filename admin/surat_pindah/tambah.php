<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$errors = [];

// Fetch Siswa Aktif
$siswa_options = [];
$res_siswa = mysqli_query($conn, "SELECT nis, nama_siswa FROM siswa WHERE status IN('aktif') ORDER BY nama_siswa ASC");
if ($res_siswa) {
    while ($row = mysqli_fetch_assoc($res_siswa)) {
        $siswa_options[] = $row;
    }
}

// Fetch Tahun Ajaran Aktif
$q_ta_aktif = "SELECT id_tahun_ajaran FROM tahun_ajaran WHERE aktif = 'Y' LIMIT 1";
$res_ta = mysqli_query($conn, $q_ta_aktif);
$id_tahun_ajaran_aktif = ($res_ta && mysqli_num_rows($res_ta) > 0) ? mysqli_fetch_assoc($res_ta)['id_tahun_ajaran'] : null;

// Fetch Profil Sekolah
$q_profil = "SELECT id_profil_sekolah FROM profil_sekolah LIMIT 1";
$res_profil = mysqli_query($conn, $q_profil);
$id_profil_sekolah = ($res_profil && mysqli_num_rows($res_profil) > 0) ? mysqli_fetch_assoc($res_profil)['id_profil_sekolah'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $no_surat = trim($_POST['no_surat'] ?? '');
    $tanggal_pembuatan_surat = $_POST['tanggal_pembuatan_surat'] ?? '';
    $nis = $_POST['nis'] ?? '';
    $sekolah_tujuan = trim($_POST['sekolah_tujuan'] ?? '');
    $alasan_pindah = trim($_POST['alasan_pindah'] ?? '');

    if (empty($no_surat)) $errors[] = 'Nomor Surat wajib diisi.';
    if (empty($tanggal_pembuatan_surat)) $errors[] = 'Tanggal Pembuatan Surat wajib diisi.';
    if (empty($nis)) $errors[] = 'Siswa wajib dipilih.';
    if (empty($sekolah_tujuan)) $errors[] = 'Sekolah Tujuan wajib diisi.';
    if (empty($id_tahun_ajaran_aktif)) $errors[] = 'Tahun Ajaran aktif belum di-setting.';

    if (empty($errors)) {
        mysqli_begin_transaction($conn);
        try {
            // 1. Insert ke tabel surat_pindah
            $stmt1 = mysqli_prepare($conn, "INSERT INTO surat_pindah (sekolah_tujuan, alasan_pindah) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt1, 'ss', $sekolah_tujuan, $alasan_pindah);
            mysqli_stmt_execute($stmt1);
            $id_surat_pindah = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt1);

            // Ambil tingkat siswa
            $q_tingkat = "SELECT t.tingkat FROM siswa s LEFT JOIN kelas k ON s.id_kelas = k.id_kelas LEFT JOIN tingkat t ON k.id_tingkat = t.id_tingkat WHERE s.nis = ?";
            $stmt_t = mysqli_prepare($conn, $q_tingkat);
            mysqli_stmt_bind_param($stmt_t, 'i', $nis);
            mysqli_stmt_execute($stmt_t);
            $res_t = mysqli_stmt_get_result($stmt_t);
            $row_t = mysqli_fetch_assoc($res_t);
            $tingkat = $row_t['tingkat'] ?? '-';
            mysqli_stmt_close($stmt_t);

            $jenis_surat = 'Pindah';

            // 2. Insert ke tabel surat_keluar
            $stmt2 = mysqli_prepare($conn, "INSERT INTO surat_keluar (no_surat, jenis_surat, id_surat_pindah, nis, tanggal_pembuatan_surat, id_profil_sekolah, id_tahun_ajaran, tingkat) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, 'ssiisiis', $no_surat, $jenis_surat, $id_surat_pindah, $nis, $tanggal_pembuatan_surat, $id_profil_sekolah, $id_tahun_ajaran_aktif, $tingkat);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            mysqli_commit($conn);
            echo "<script>window.location.href = 'index.php?msg=tambah_sukses';</script>";
            exit;
        }
        catch (Exception $e) {
            mysqli_rollback($conn);
            $errors[] = 'Gagal menyimpan data ke database: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entry Surat Pindah - SISP</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; color: var(--color-text); }
        .form-control { width: 100%; padding: 11px 16px; border: 1.5px solid var(--color-border); border-radius: var(--radius-sm); font-size: 14px; font-family: inherit; }
        .form-control:focus { outline: none; border-color: var(--color-accent); box-shadow: 0 0 0 3px rgba(63,114,175,0.12); }
        .select2-container .select2-selection--single { height: 44px; border: 1.5px solid var(--color-border); border-radius: var(--radius-sm); display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { padding-left: 14px; color: var(--color-text); }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 42px; right: 10px; }
        .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .two-cols { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Entry Surat Pindah</div>
                <div class="topbar-user"><span><?php echo htmlspecialchars(getUserName()); ?></span></div>
            </div>
            
            <div class="page-content">
                <div style="margin-bottom:20px; font-size:13px;">
                    <a href="index.php" style="color:var(--color-accent); font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Surat Pindah</a>
                </div>
                
                <div class="card" style="max-width: 850px; margin: 0 auto;">
                    <div class="card-header">
                        <h3><i class="fas fa-file-export" style="margin-right:8px;"></i>Form Entry Surat Pindah Siswa</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($id_tahun_ajaran_aktif)): ?>
                            <div class="alert alert-danger" style="margin-bottom: 20px;">
                                <i class="fas fa-exclamation-triangle"></i> Belum ada Tahun Ajaran aktif yang diset.
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger" style="margin-bottom: 20px;">
                                <ul style="margin-left: 15px;">
                                    <?php foreach ($errors as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label" for="nis">Nama Siswa / NIS <span style="color:red">*</span></label>
                                <select id="nis" name="nis" class="form-control select2" required>
                                    <option value=""></option>
                                    <?php foreach ($siswa_options as $siswa): ?>
                                        <option value="<?php echo htmlspecialchars($siswa['nis']); ?>" <?php echo (($_POST['nis'] ?? '') === $siswa['nis']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($siswa['nama_siswa']) . ' - NIS: ' . htmlspecialchars($siswa['nis']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="two-cols">
                                <div class="form-group">
                                    <label class="form-label" for="no_surat">Nomor Surat <span style="color:red">*</span></label>
                                    <input type="text" id="no_surat" name="no_surat" class="form-control" value="<?php echo htmlspecialchars($_POST['no_surat'] ?? '421.2 / ___ / SISP / ' . date('Y')); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="tanggal_pembuatan_surat">Tanggal Surat <span style="color:red">*</span></label>
                                    <input type="date" id="tanggal_pembuatan_surat" name="tanggal_pembuatan_surat" class="form-control" value="<?php echo htmlspecialchars($_POST['tanggal_pembuatan_surat'] ?? date('Y-m-d')); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="sekolah_tujuan">Sekolah Tujuan <span style="color:red">*</span></label>
                                <input type="text" id="sekolah_tujuan" name="sekolah_tujuan" class="form-control" placeholder="Nama sekolah tujuan baru..." value="<?php echo htmlspecialchars($_POST['sekolah_tujuan'] ?? ''); ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="alasan_pindah">Alasan Pindah</label>
                                <textarea id="alasan_pindah" name="alasan_pindah" class="form-control" rows="3" placeholder="Alasan kepindahan siswa..."><?php echo htmlspecialchars($_POST['alasan_pindah'] ?? ''); ?></textarea>
                            </div>
                            
                            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 30px;">
                                <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Surat Pindah</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({ placeholder: "-- Cari Nama / NIS Siswa --", allowClear: true, width: '100%' });
        });
    </script>
</body>
</html>