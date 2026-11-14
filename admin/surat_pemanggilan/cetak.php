<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['nis'])) {
    die("Akses tidak valid. Harap gunakan form pencetakan.");
}

$nis = mysqli_real_escape_string($conn, $_POST['nis']);
$no_surat = $_POST['no_surat'] ?? '020/SMKTI/B/I/2026';
$lampiran = $_POST['lampiran'] ?? '-';

$days = [
    'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
];

$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];

if (isset($_POST['tanggal'])) {
    $ts = strtotime($_POST['tanggal']);
    $hari_tanggal = $days[date('l', $ts)] . ', ' . date('d', $ts) . ' ' . $months[(int)date('m', $ts)] . ' ' . date('Y', $ts);
} else {
    $hari_tanggal = $_POST['hari_tanggal'] ?? 'Kamis, 08 Januari 2026';
}

if (isset($_POST['waktu'])) {
    $pukul = date('H.i', strtotime($_POST['waktu'])) . ' Wita';
} else {
    $pukul = $_POST['pukul'] ?? '08.00 Wita';
}

$tempat = $_POST['tempat'] ?? 'SMK TI Bali Global Denpasar';
$keperluan = $_POST['keperluan'] ?? 'Masalah Disiplin Siswa';

// Ambil data siswa
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

if (!$result || mysqli_num_rows($result) === 0) {
    die("Data siswa tidak ditemukan.");
}

$data = mysqli_fetch_assoc($result);

$kelas_rombel = '-';
if (!empty($data['tingkat']) && !empty($data['rombel'])) {
    $kelas_rombel = $data['tingkat'] . ' ' . $data['rombel'];
}
$kelas_nis = $kelas_rombel . ' / ' . $nis;
$nama_ortu = !empty($data['nama_ortu']) ? $data['nama_ortu'] : '................................................';

setlocale(LC_TIME, 'id_ID', 'id_ID.utf8');
$tanggal_sekarang = date('d') . ' ' . $months[(int)date('m')] . ' ' . date('Y');

// For specific exact match with image if they wanted it exactly hardcoded, but we use dynamic
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Pemanggilan - <?php echo htmlspecialchars($data['nama_siswa']); ?></title>
    <style>
        @page {
            size: A4;
            margin-top: 1cm;
            margin-bottom: 2.54cm;
            margin-left: 2.54cm;
            margin-right: 2.54cm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11.5pt;
            line-height: 1.35;
            color: #000;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .container { 
            width: 100%; 
            max-width: 800px; 
            margin: 0 auto; 
            border: 1px solid #000; 
            padding: 30px; 
            box-sizing: border-box; 
        }
        .kop-surat { text-align: center; margin-bottom: 15px; }
        .kop-surat img { width: 100%; max-width: 800px; height: auto; display: block; }
        .header-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 1px 0; }
        .col-label { width: 80px; }
        .col-colon { width: 20px; text-align: center; }
        .col-val { font-weight: bold; }
        .body-surat { margin-top: 10px; text-align: justify; }
        .ortu-info { margin-left: 30px; margin-bottom: 10px; padding: 0;}
        .ortu-table { width: 100%; border-collapse: collapse; }
        .ortu-table td { vertical-align: top; padding: 1px 0; }
        .o-label { width: 180px; }
        .o-colon { width: 20px; text-align: center; }
        .o-val { }
        .jadwal-info { margin-left: 30px; margin-top: 5px; margin-bottom: 15px; padding: 0;}
        .jadwal-table { width: 100%; border-collapse: collapse; }
        .jadwal-table td { vertical-align: top; padding: 1px 0; }
        .j-label { width: 150px; }
        .j-colon { width: 20px; text-align: center; }
        
        .signature-area { width: 100%; margin-top: 30px; }
        .signature-table { width: 100%; text-align: left; }
        .signature-table td { width: 50%; vertical-align: top; padding-bottom: 50px; }
        .name-line { display: inline-block; border-bottom: 1px solid #000; min-width: 180px; font-weight: bold; padding-bottom: 1px;}
        
        .indent-p { text-indent: 40px; margin: 4px 0;}
        .normal-p { margin: 4px 0;}
        
        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .container { border: none !important; padding: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="position: fixed; bottom: 30px; right: 30px; display: flex; gap: 10px; z-index: 1000;">
        <button onclick="window.print()" style="padding:10px 20px; background:#2563eb; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            Cetak Surat Pemanggilan
        </button>
        <button onclick="window.close()" style="padding:10px 20px; background:#6b7280; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            Tutup
        </button>
    </div>

    <div class="container">
        <!-- Kop Surat -->
        <div class="kop-surat">
            <!-- Ensure kop surat style matches previous one, using provided image -->
            <img src="../../assets/img/kop_surat.jpg" alt="Kop Surat SMK TI Bali Global Denpasar" onerror="this.src='https://via.placeholder.com/800x150.png?text=KOP+SURAT+SMK+TI+BALI+GLOBAL+DENPASAR';">
        </div>

        <table class="header-table">
            <tr>
                <td class="col-label">No.</td>
                <td class="col-colon">:</td>
                <td class="col-val" style="font-weight:normal;"><?php echo htmlspecialchars($no_surat); ?></td>
            </tr>
            <tr>
                <td class="col-label">Lamp.</td>
                <td class="col-colon">:</td>
                <td class="col-val" style="font-weight:normal;"><?php echo htmlspecialchars($lampiran); ?></td>
            </tr>
            <tr>
                <td class="col-label">Perihal</td>
                <td class="col-colon">:</td>
                <td class="col-val">Pemanggilan Orang Tua / Wali Siswa</td>
            </tr>
        </table>

        <div class="body-surat">
            <p class="normal-p">Kepada</p>
            <p class="normal-p" style="margin-bottom: 10px;">Yth. Bapak/ Ibu</p>
            
            <div class="ortu-info">
                <table class="ortu-table">
                    <tr>
                        <td class="o-label">Orang Tua / Wali dari</td>
                        <td class="o-colon">:</td>
                        <td class="o-val"><?php echo htmlspecialchars($data['nama_siswa']); ?></td>
                    </tr>
                    <tr>
                        <td class="o-label">Kelas / Nis</td>
                        <td class="o-colon">:</td>
                        <td class="o-val"><?php echo htmlspecialchars($kelas_nis); ?></td>
                    </tr>
                </table>
            </div>

            <p class="normal-p">Dengan hormat,</p>
            <p class="normal-p">Bersama surat ini, kami mengharapkan kehadiran Bapak / Ibu pada :</p>

            <div class="jadwal-info">
                <table class="jadwal-table">
                    <tr>
                        <td class="j-label">Hari / Tanggal</td>
                        <td class="j-colon">:</td>
                        <td><?php echo htmlspecialchars($hari_tanggal); ?></td>
                    </tr>
                    <tr>
                        <td class="j-label">Pukul</td>
                        <td class="j-colon">:</td>
                        <td><?php echo htmlspecialchars($pukul); ?></td>
                    </tr>
                    <tr>
                        <td class="j-label">Tempat</td>
                        <td class="j-colon">:</td>
                        <td><?php echo htmlspecialchars($tempat); ?></td>
                    </tr>
                    <tr>
                        <td class="j-label">Keperluan</td>
                        <td class="j-colon">:</td>
                        <td><?php echo htmlspecialchars($keperluan); ?></td>
                    </tr>
                </table>
            </div>

            <p class="indent-p">
                Demikian surat ini kami sampaikan, besar harapan kami pertemuan ini agar tidak diwakilkan.
            </p>
            <p class="normal-p">
                Atas perhatian dan kerjasamanya, kami ucapkan terimakasih.
            </p>

            <div class="signature-area">
                <table class="signature-table">
                    <tr>
                        <td>
                            Mengetahui,<br>
                            Waka Kesiswaan
                        </td>
                        <td style="padding-left: 20px;">
                            Denpasar, <?php echo $tanggal_sekarang; ?><br>
                            Kepsek
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height:30px;"></td></tr>
                    <tr>
                        <td style="vertical-align:bottom;">
                            <span class="name-line">Bagus Putu Eka Wijaya, S.Kom</span>
                        </td>
                        <td style="vertical-align:bottom; padding-left: 20px;">
                            <!-- Hardcoded name matching the user image reference (I Gusti Ayu Rinjani, M.Pd) -->
                            <span class="name-line">I Gusti Ayu Rinjani, M.Pd</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
