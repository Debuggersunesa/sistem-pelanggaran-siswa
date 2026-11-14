<?php
require_once '../includes/session.php';
requireRole('admin');
require_once '../config/koneksi.php';
?>
<?php
// Menghitung statistik dashboard
$query_count_siswa = "SELECT COUNT(*) FROM siswa WHERE status = 'aktif'";
$res_count_siswa = mysqli_query($conn, $query_count_siswa);
$count_siswa = $res_count_siswa ? mysqli_fetch_row($res_count_siswa)[0] : 0;

$query_count_guru = "SELECT COUNT(*) FROM guru WHERE aktif = 'Y'";
$res_count_guru = mysqli_query($conn, $query_count_guru);
$count_guru = $res_count_guru ? mysqli_fetch_row($res_count_guru)[0] : 0;

$query_count_pel = "SELECT COUNT(*) FROM pelanggaran_siswa";
$res_count_pel = mysqli_query($conn, $query_count_pel);
$count_pelanggaran = $res_count_pel ? mysqli_fetch_row($res_count_pel)[0] : 0;

$query_count_kelas = "SELECT COUNT(*) FROM kelas";
$res_count_kelas = mysqli_query($conn, $query_count_kelas);
$count_kelas = $res_count_kelas ? mysqli_fetch_row($res_count_kelas)[0] : 0;

/// Fetch 5 most recent violations
$query_recent = "SELECT ps.tanggal, s.nama_siswa AS nama_siswa, jp.jenis AS jenis, jp.poin 
                 FROM pelanggaran_siswa ps
                 JOIN siswa s ON ps.nis = s.nis
                 JOIN jenis_pelanggaran jp ON ps.id_jenis_pelanggaran = jp.id_jenis_pelanggaran
                 ORDER BY ps.tanggal DESC LIMIT 5";


$result_recent = mysqli_query($conn, $query_recent);

if (!$result_recent) {
    // Log error or handle it (for development, we might want to see the error)
    $error_message = "Query failed: " . mysqli_error($conn);
}
// We will handle the display logic to show the error or an empty state.

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - SPPS</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Dashboard Admin</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>
            
            <div class="page-content">
                <div class="greeting-banner">
                    <h1 class="greeting-title">Selamat Datang, <?php echo htmlspecialchars(getUserName()); ?>!</h1>
                    <p class="greeting-subtitle">Anda masuk sebagai Administrator. Kelola data master siswa, guru, dan pantau kedisiplinan sekolah dari panel ini.</p>
                </div>

                <div class="stats-grid">
                    <a href="siswa/index.php" class="stat-card">
                        <div class="stat-icon blue"><i class="fa-solid fa-graduation-cap"></i></div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $count_siswa ?? 0; ?></div>
                            <div class="stat-label">Siswa Aktif</div>
                        </div>
                    </a>
                    <a href="guru/index.php" class="stat-card green">
                        <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $count_guru ?? 0; ?></div>
                            <div class="stat-label">Guru Aktif</div>
                        </div>
                    </a>
                    <a href="pelanggaran/index.php" class="stat-card red">
                        <div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $count_pelanggaran ?? 0; ?></div>
                            <div class="stat-label">Total Pelanggaran</div>
                        </div>
                    </a>
                    <div class="stat-card orange">
                        <div class="stat-icon orange"><i class="fas fa-school"></i></div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $count_kelas ?? 0; ?></div>
                            <div class="stat-label">Jumlah Kelas</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history" style="color:var(--color-primary)"></i> Pelanggaran Terbaru</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger" style="color:red; text-align:center;">
                                <i class="fas fa-exclamation-triangle"></i> Gagal memuat data: <?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php
elseif ($result_recent && mysqli_num_rows($result_recent) > 0): ?>
                            <div class="recent-violations-list" style="display:flex; flex-direction:column; gap:15px;">
                                <?php while ($row = mysqli_fetch_assoc($result_recent)): ?>
                                    <div class="violation-item" style="background:#fff; border-radius:10px; padding:15px; box-shadow:0 4px 6px rgba(0,0,0,0.05); border:1px solid #eee; display:flex; justify-content:space-between; align-items:center; transition:transform 0.2s;">
                                        <div class="v-info" style="display:flex; flex-direction:column; gap:5px;">
                                            <div style="font-weight:600; color:var(--color-dark); font-size:1.05rem;"><?php echo htmlspecialchars($row['nama_siswa']); ?></div>
                                            <div style="font-size:0.9rem; color:#666;">
                                                <span style="color:var(--color-primary); font-weight:500;"><?php echo htmlspecialchars($row['jenis']); ?></span>
                                                <span style="margin:0 5px;">•</span>
                                                <small><?php echo date('d M Y', strtotime($row['tanggal'])); ?></small>
                                            </div>
                                        </div>
                                        <div class="v-points">
                                            <span class="badge red" style="background:var(--color-danger); color:#fff; padding:5px 12px; border-radius:20px; font-weight:bold; font-size:0.9rem;">
                                                +<?php echo $row['poin']; ?> Poin
                                            </span>
                                        </div>
                                    </div>
                                <?php
    endwhile; ?>
                            </div>
                        <?php
else: ?>
                            <div class="empty-state" style="text-align:center; padding:40px 0; color:#888;">
                                <i class="fas fa-check-circle" style="font-size:48px; color:#ddd; margin-bottom:15px;"></i>
                                <p>Tidak ada pelanggaran terbaru.</p>
                            </div>
                        <?php
endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>