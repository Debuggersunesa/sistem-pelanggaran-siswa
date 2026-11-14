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
        s.alamat AS alamat_siswa,
        t.tingkat, k.rombel,
        pk.program_keahlian,
        ow.nama AS nama_ortu,
        ow.pekerjaan,
        ow.alamat AS alamat_ortu,
        ow.no_telp
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN tingkat t ON k.id_tingkat = t.id_tingkat
    LEFT JOIN program_keahlian pk ON k.id_program_keahlian = pk.id_program_keahlian
    LEFT JOIN ortu_wali ow ON s.id_ortu_wali = ow.id_ortu_wali
    WHERE s.nis = '$nis'
";

$result = mysqli_query($conn, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $data = mysqli_fetch_assoc($result);

    // Format kelas
    $kelas_rombel = '-';
    if (!empty($data['tingkat']) && !empty($data['rombel'])) {
        $kelas_rombel = $data['tingkat'] . ' - ' . $data['rombel'];
    }

    $response = [
        'status' => 'success',
        'data' => [
            'nama_siswa' => $data['nama_siswa'],
            'kelas_rombel' => $kelas_rombel,
            'program_keahlian' => $data['program_keahlian'] ?? '-',
            'nama_ortu' => $data['nama_ortu'],
            'pekerjaan' => $data['pekerjaan'],
            'alamat_ortu' => $data['alamat_ortu'],
            'alamat_siswa' => $data['alamat_siswa'],
            'no_telp' => $data['no_telp']
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
