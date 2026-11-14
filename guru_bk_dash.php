<?php
$admin_dashboard = file_get_contents('admin/dashboard.php');

// Replace roles and texts
$kepsek_dashboard = str_replace(
[
    "requireRole('admin')",
    "Dashboard Admin",
    "Administrator",
    '<a href="siswa/index.php" class="stat-card">',
    '<a href="guru/index.php" class="stat-card green">',
    '<a href="pelanggaran/index.php" class="stat-card red">',
],
[
    "requireRole('kepsek')",
    "Dashboard Kepsek",
    "Kepsek",
    '<a href="../admin/siswa/index.php" class="stat-card">',
    '<a href="../admin/guru/index.php" class="stat-card green">',
    '<a href="../admin/pelanggaran/index.php" class="stat-card red">',
],
    $admin_dashboard
);

file_put_contents('kepsek/dashboard.php', $kepsek_dashboard);
echo "Updated kepsek/dashboard.php\n";
?>
