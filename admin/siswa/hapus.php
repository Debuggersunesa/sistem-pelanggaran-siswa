<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$nis = isset($_GET['nis']) ? (int)$_GET['nis'] : 0;

if ($nis <= 0) {
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM siswa WHERE nis = ?");
mysqli_stmt_bind_param($stmt, 'i', $nis);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header('Location: index.php?success=hapus');
exit;
