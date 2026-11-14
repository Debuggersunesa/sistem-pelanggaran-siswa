<?php
require_once '../includes/session.php';
requireRole('guru_pelajaran');
require_once '../config/koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - SISP</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-wrapper">
        <?php include '../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Dashboard Guru Pelajaran</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">G</div>
                </div>
            </div>
            
            <div class="page-content">
                <div class="greeting-banner">
                    <h1 class="greeting-title">Selamat Datang, <?php echo htmlspecialchars(getUserName()); ?>!</h1>
                    <p class="greeting-subtitle">Anda login sebagai Guru Pelajaran. Laporkan pelanggaran siswa yang terjadi di kelas Anda agar segera ditindaklanjuti.</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card green">
                        <div class="stat-icon green"><i class="fas fa-plus-circle"></i></div>
                        <div class="stat-info">
                            <div class="stat-value">Lapor</div>
                            <div class="stat-label">Entry Pelanggaran</div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bolt" style="color:var(--color-warning)"></i> Akses Cepat</h3>
                    </div>
                    <div class="card-body">
                         <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
                            <a href="pelanggaran/index.php" class="btn btn-secondary" style="justify-content:center;height:60px;">
                                <i class="fas fa-exclamation-triangle"></i> Laporkan Siswa
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>