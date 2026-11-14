<?php
ob_start();
error_reporting(0);

require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

if (!isset($_GET['nis']) || empty($_GET['nis'])) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'NIS tidak diberikan']);
    exit;
}

$nis = mysqli_real_escape_string($conn, $_GET['nis']);

$query = "
    SELECT 
        s.nama_siswa,
        t.tingkat, k.rombel,
        ow.nama AS nama_ortu
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN tingkat t ON k.id_tingkat = t.id_tingkat
    LEFT JOIN ortu_wali ow ON s.id_ortu_wali = ow.id_ortu_wali
    WHERE s.nis = '$nis'
";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);

    $kelas_rombel = '-';
    if (!empty($data['tingkat']) && !empty($data['rombel'])) {
        $kelas_rombel = $data['tingkat'] . ' - ' . $data['rombel'];
    }

    $response = [
        'status' => 'success',
        'data' => [
            'nama_siswa' => $data['nama_siswa'],
            'kelas_rombel' => $kelas_rombel,
            'nama_ortu' => $data['nama_ortu']
        ]
    ];

    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($response);
}
else {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Data tidak ditemukan']);
}
