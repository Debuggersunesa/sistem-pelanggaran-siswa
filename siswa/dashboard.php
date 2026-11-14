<?php
require_once '../includes/session.php';
requireRole('siswa');
require_once '../config/koneksi.php';

$nis = $_SESSION['user_id'];
// Get student data & points
$query = "SELECT s.*, k.nama_kelas, pk.program_keahlian 
          FROM siswa s 
          LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
          LEFT JOIN program_keahlian pk ON k.id_program_keahlian = pk.id_program_keahlian
          WHERE s.nis = '$nis'";
$siswa = mysqli_fetch_assoc(mysqli_query($conn, $query));

// Total Points
$q_poin = "SELECT SUM(jp.poin) as total FROM pelanggaran_siswa ps 
           JOIN jenis_pelanggaran jp ON ps.id_jenis_pelanggaran = jp.id_jenis_pelanggaran 
           WHERE ps.nis = '$nis'";
$total_poin = mysqli_fetch_assoc(mysqli_query($conn, $q_poin))['total'] ?? 0;

// History
$history = mysqli_query($conn, "SELECT ps.tanggal, jp.jenis, jp.poin, ps.keterangan 
                                FROM pelanggaran_siswa ps 
                                JOIN jenis_pelanggaran jp ON ps.id_jenis_pelanggaran = jp.id_jenis_pelanggaran 
                                WHERE ps.nis = '$nis' ORDER BY ps.tanggal DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Siswa - SISP</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Dashboard Siswa</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars($siswa['nama_siswa']); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">S</div>
                </div>
            </div>
            
            <div class="page-content">
                <div class="greeting-banner">
                    <h1 class="greeting-title">Selamat Datang, <?php echo htmlspecialchars($siswa['nama_siswa']); ?>!</h1>
                    <p class="greeting-subtitle">NIS: <?php echo htmlspecialchars($siswa['nis']); ?> | Kelas: <?php echo htmlspecialchars($siswa['nama_kelas'] ?? '-'); ?></p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-icon blue"><i class="fas fa-id-card"></i></div>
                        <div class="stat-info">
                            <div class="stat-value">Profil</div>
                            <div class="stat-label">Data Diri Saya</div>
                        </div>
                    </div>
                    <div class="stat-card <?php echo $total_poin > 50 ? 'red' : ($total_poin > 20 ? 'orange' : 'green'); ?>">
                        <div class="stat-icon <?php echo $total_poin > 50 ? 'red' : ($total_poin > 20 ? 'orange' : 'green'); ?>"><i class="fas fa-clipboard-list"></i></div>
                        <div class="stat-info">
                            <div class="stat-value"><?php echo $total_poin; ?></div>
                            <div class="stat-label">Total Poin Pelanggaran</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Riwayat Pelanggaran Terakhir</h3>
                        <a href="riwayat.php" class="btn btn-sm btn-secondary">Lihat Semua</a>
                    </div>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Pelanggaran</th>
                                    <th>Poin</th>
                                    <th>Keterangan</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (mysqli_num_rows($history) == 0): ?>
                                    <tr><td colspan="4" class="table-empty">Belum ada data pelanggaran.</td></tr>
                                <?php
else:
    while ($row = mysqli_fetch_assoc($history)): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($row['tanggal'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['jenis']); ?></td>
                                        <td><span class="badge badge-danger">+<?php echo $row['poin']; ?></span></td>
                                        <td><?php echo htmlspecialchars($row['keterangan']); ?></td>
                                    </tr>
                                <?php
    endwhile;
endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>