<?php
$role = getUserRole(); // 'admin', 'kepsek', 'guru_pelajaran', 'siswa'
$active_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page_name)
{
    global $active_page;
    // Simple check, can be expanded if needed
    return $active_page === $page_name ? 'active' : '';
}

function isDirActive($dir_name)
{
    global $current_dir;
    return $current_dir === $dir_name ? 'active' : '';
}

function inArrayDirActive($dirs)
{
    global $current_dir;
    return in_array($current_dir, $dirs) ? 'active' : '';
}
?>
<style>
/* Dropdown Sidebar Styles */
.sidebar-dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border-radius: var(--radius-md);
    color: rgba(255, 255, 255, 0.7);
    font-weight: 500;
    margin-bottom: 4px;
    cursor: pointer;
    transition: var(--transition);
    border: none;
    background: transparent;
    width: 100%;
    text-align: left;
    font-family: inherit;
    font-size: 14px;
}
.sidebar-dropdown-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--color-white);
}
.sidebar-dropdown-toggle.active {
    background: rgba(255, 255, 255, 0.1);
    color: var(--color-white);
    font-weight: 600;
}
.sidebar-dropdown-toggle .icon {
    width: 20px;
    display: flex;
    justify-content: center;
}
.sidebar-dropdown-toggle .chevron {
    margin-left: auto;
    font-size: 12px;
    transition: transform 0.3s ease;
}
.sidebar-submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-in-out;
    background: rgba(0, 0, 0, 0.15);
    border-radius: var(--radius-md);
    margin-bottom: 4px;
}
.sidebar-submenu.open {
    max-height: 500px;
}
.sidebar-sublink {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px 10px 48px;
    color: rgba(255, 255, 255, 0.6);
    font-size: 13.5px;
    text-decoration: none;
    transition: var(--transition);
}
.sidebar-sublink:hover {
    color: var(--color-white);
    background: rgba(255, 255, 255, 0.05);
}
.sidebar-sublink.active {
    color: var(--color-white);
    font-weight: 600;
    background: rgba(255, 255, 255, 0.1);
    border-left: 3px solid var(--color-accent);
}
</style>
<div class="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon"><i class="fa-solid fa-school-lock"></i></div>
        <div class="sidebar-brand-text">
            <h3>SPPS</h3>
            <span>Sistem Pelanggaran</span>
        </div>
    </div>
    
    <!-- <div class="sidebar-user">
        <div class="sidebar-user-avatar">
            <i class="fas fa-user"></i>
        </div>
        <div class="sidebar-user-info">
            <h4><?php echo htmlspecialchars(substr(getUserName(), 0, 15)); ?></h4>
            <span><?php echo ucfirst(str_replace('_', ' ', $role)); ?></span>
        </div>
    </div> -->

    <div class="sidebar-nav">
        
        <!-- ADMIN & GURU BK MENU -->
        <?php if ($role === 'admin' || $role === 'kepsek'): ?>
            <div class="sidebar-section-label">Main</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?><?php echo $role; ?>/dashboard.php" class="sidebar-link <?php echo isActive('dashboard.php'); ?>">
                    <span class="icon"><i class="fa-solid fa-house"></i></span>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="sidebar-section-label">Master Data</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>admin/siswa/index.php" class="sidebar-link <?php echo isDirActive('siswa'); ?>">
                    <span class="icon"><i class="fa-solid fa-graduation-cap"></i></span>
                    <span>Data Siswa</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>admin/guru/index.php" class="sidebar-link <?php echo isDirActive('guru'); ?>">
                    <span class="icon"><i class="fas fa-chalkboard-teacher"></i></span>
                    <span>Data Guru</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>admin/kelas/index.php" class="sidebar-link <?php echo isDirActive('kelas'); ?>">
                    <span class="icon"><i class="fa-solid fa-school"></i></span>
                    <span>Data Kelas</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>admin/jenis_pelanggaran/index.php" class="sidebar-link <?php echo isDirActive('jenis_pelanggaran'); ?>">
                    <span class="icon"><i class="fas fa-exclamation-circle"></i></span>
                    <span>Jenis Pelanggaran</span>
                </a>
            </div>

            <div class="sidebar-section-label">Transaksi</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>admin/pelanggaran/index.php" class="sidebar-link <?php echo isDirActive('pelanggaran'); ?>">
                    <span class="icon"><i class="fas fa-file-signature"></i></span>
                    <span>Entry Pelanggaran</span>
                </a>
            </div>
            <div class="sidebar-item">
                <?php
    $surat_dirs_admin = ['perjanjian', 'surat_pindah', 'surat_pernyataan_ortu', 'surat_pemanggilan'];
    $surat_active_admin = inArrayDirActive($surat_dirs_admin);
?>
                <!-- <button type="button" class="sidebar-dropdown-toggle <?php echo $surat_active_admin; ?>" onclick="toggleSubmenu('adminSuratMenu', this)">
                    <span class="icon"><i class="fas fa-envelope-open-text"></i></span>
                    <span>Surat</span>
                    <span class="chevron"><i class="fas fa-chevron-<?php echo $surat_active_admin ? 'up' : 'down'; ?>"></i></span>
                </button> -->
                <div class="sidebar-submenu <?php echo $surat_active_admin ? 'open' : ''; ?>" id="adminSuratMenu">
                    <a href="<?php echo getBaseUrl(); ?>admin/surat_pindah/index.php" class="sidebar-sublink <?php echo isDirActive('surat_pindah'); ?>">
                        Pindah Sekolah
                    </a>
                    <a href="<?php echo getBaseUrl(); ?>admin/perjanjian/index.php" class="sidebar-sublink <?php echo isDirActive('perjanjian'); ?>">
                        Perjanjian Siswa
                    </a>
                    <!-- Assuming these directories will be created later based on user request -->
                    <a href="<?php echo getBaseUrl(); ?>admin/surat_pernyataan_ortu/index.php" class="sidebar-sublink <?php echo isDirActive('surat_pernyataan_ortu'); ?>">
                        Pernyataan Orang Tua
                    </a>
                    <a href="<?php echo getBaseUrl(); ?>admin/surat_pemanggilan/index.php" class="sidebar-sublink <?php echo isDirActive('surat_pemanggilan'); ?>">
                        Pemanggilan Orang Tua
                    </a>
                </div>
            </div>

            <!-- <div class="sidebar-section-label">Laporan</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>admin/laporan/index.php" class="sidebar-link <?php echo isDirActive('laporan'); ?>">
                    <span class="icon"><i class="fas fa-chart-line"></i></span>
                    <span>Laporan</span>
                </a>
            </div> -->


        <!-- GURU PELAJARAN MENU -->
        <?php
elseif ($role === 'guru_pelajaran'): ?>
            <div class="sidebar-section-label">Main</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>guru_pelajaran/dashboard.php" class="sidebar-link <?php echo isActive('dashboard.php'); ?>">
                    <span class="icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="sidebar-section-label">Transaksi</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>guru_pelajaran/pelanggaran/index.php" class="sidebar-link <?php echo isDirActive('pelanggaran'); ?>">
                    <span class="icon"><i class="fas fa-file-signature"></i></span>
                    <span>Entry Pelanggaran</span>
                </a>
            </div>

        <!-- SISWA MENU -->
        <?php
elseif ($role === 'siswa'): ?>
            <div class="sidebar-section-label">Main</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>siswa/dashboard.php" class="sidebar-link <?php echo isActive('dashboard.php'); ?>">
                    <span class="icon"><i class="fas fa-tachometer-alt"></i></span>
                    <span>Dashboard</span>
                </a>
            </div>

            <div class="sidebar-section-label">Menu Siswa</div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>siswa/profil.php" class="sidebar-link <?php echo isActive('profil.php'); ?>">
                    <span class="icon"><i class="fas fa-user"></i></span>
                    <span>Profil Saya</span>
                </a>
            </div>
            <div class="sidebar-item">
                <a href="<?php echo getBaseUrl(); ?>siswa/riwayat.php" class="sidebar-link <?php echo isActive('riwayat.php'); ?>">
                    <span class="icon"><i class="fas fa-history"></i></span>
                    <span>Riwayat Pelanggaran</span>
                </a>
            </div>

        <?php
endif; ?>
    </div>

    <div class="sidebar-footer">
        <a href="<?php echo getBaseUrl(); ?>logout.php" class="sidebar-link logout-item">
            <span class="icon"><i class="fas fa-sign-out-alt"></i></span>
            <span>Keluar</span>
        </a>
    </div>
</div>

<script>
function toggleSubmenu(menuId, btn) {
    const menu = document.getElementById(menuId);
    const chevron = btn.querySelector('.chevron i');
    
    if (menu.classList.contains('open')) {
        menu.classList.remove('open');
        chevron.classList.remove('fa-chevron-up');
        chevron.classList.add('fa-chevron-down');
        btn.classList.remove('active');
    } else {
        menu.classList.add('open');
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-up');
        btn.classList.add('active');
    }
}
</script>