<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['nis'])) {
    die("Akses tidak valid. Harap gunakan form pencetakan.");
}

$nis = mysqli_real_escape_string($conn, $_POST['nis']);
$masalah = $_POST['masalah'] ?? '';

// Ambil data siswa lengkap beserta relasinya
$query = "
    SELECT 
        s.nama_siswa,
        t.tingkat, k.rombel,
        pk.program_keahlian,
        ow.nama AS nama_ortu,
        ow.pekerjaan,
        ow.alamat AS alamat_ortu,
        ow.no_telp,
        s.alamat AS alamat_siswa,
        g.nama_pengguna AS wali_kelas
    FROM siswa s
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas
    LEFT JOIN tingkat t ON k.id_tingkat = t.id_tingkat
    LEFT JOIN program_keahlian pk ON k.id_program_keahlian = pk.id_program_keahlian
    LEFT JOIN ortu_wali ow ON s.id_ortu_wali = ow.id_ortu_wali
    LEFT JOIN guru g ON k.kode_guru = g.kode_guru
    WHERE s.nis = '$nis'
";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    die("Data siswa tidak ditemukan.");
}

$data = mysqli_fetch_assoc($result);

$kelas_rombel = '-';
if (!empty($data['tingkat']) && !empty($data['rombel'])) {
    $kelas_rombel = $data['tingkat'] . ' - ' . $data['rombel'];
}

$nama_ortu = !empty($data['nama_ortu']) ? $data['nama_ortu'] : '................................................';
$pekerjaan = !empty($data['pekerjaan']) ? $data['pekerjaan'] : '................................................';
$alamat = !empty($data['alamat_ortu']) ? $data['alamat_ortu'] : (!empty($data['alamat_siswa']) ? $data['alamat_siswa'] : '................................................');
$no_telp = !empty($data['no_telp']) ? $data['no_telp'] : '................................................';
$program_keahlian = !empty($data['program_keahlian']) ? $data['program_keahlian'] : '-';

$wali_kelas = !empty($data['wali_kelas']) ? $data['wali_kelas'] : '................................................';

setlocale(LC_TIME, 'id_ID', 'id_ID.utf8');
$tanggal_sekarang = strftime('%d %B %Y', time());
// Fallback if strftime is deprecated/not working correctly
$months = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$tanggal_sekarang_alt = date('d') . ' ' . $months[(int)date('m')] . ' ' . date('Y');

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Surat Perjanjian Siswa - <?php echo htmlspecialchars($data['nama_siswa']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 1.5cm;
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
        .kop-surat {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 3px solid #000;
            padding-bottom: 5px;
        }
        .kop-surat img {
            width: 100%;
            max-width: 800px;
            height: auto;
            display: block;
        }
        .surat-title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        .content {
            text-align: justify;
        }
        table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
        }
        td {
            vertical-align: top;
            padding: 1px 0;
        }
        .label-col {
            width: 25%;
        }
        .colon-col {
            width: 5%;
            text-align: center;
        }
        .val-col {
            width: 70%;
        }
        .masalah-text {
            border-bottom: 1px dotted #000;
            line-height: 1.5;
            min-height: 25px;
            display: block;
        }
        .signature-area {
            width: 100%;
            margin-top: 20px;
        }
        .signature-table {
            width: 100%;
            text-align: center;
            margin-bottom: 15px;
        }
        .signature-table td {
            width: 50%;
            padding-bottom: 50px; /* Space for signature */
        }
        .name-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 200px;
            padding-bottom: 1px;
            font-weight: bold;
        }
        .name-dots {
            display: inline-block;
            border-bottom: 1px dotted #000;
            min-width: 200px;
            padding-bottom: 1px;
        }
        @media print {
            body { background: white; }
            .no-print { display: none; }
            .container { border: none; padding: 0; }
            .container { border: 1px solid #000; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="position: fixed; bottom: 30px; right: 30px; display: flex; gap: 10px; z-index: 1000;">
        <button onclick="window.print()" style="padding:10px 20px; background:#2563eb; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            Cetak Ulang
        </button>
        <button onclick="window.close()" style="padding:10px 20px; background:#6b7280; color:#fff; border:none; border-radius:5px; cursor:pointer; font-size:16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
            Tutup Tab
        </button>
    </div>

    <div class="no-print" style="margin-bottom:20px; text-align:center; color:#4b5563; font-family:sans-serif; font-size:14px; position:absolute; top:20px; left:0; width:100%;">
        Catatan: Simpan file gambar kop surat sebagai <strong>kop_surat.jpg</strong> di folder <code>assets/img/</code>.
    </div>

    <div class="container">
        <!-- Kop Surat -->
        <div class="kop-surat">
            <img src="../../assets/img/kop_surat.jpg" alt="Kop Surat SMK TI Bali Global Denpasar" onerror="this.src='https://via.placeholder.com/800x150.png?text=KOP+SURAT+SMK+TI+BALI+GLOBAL+DENPASAR';">
            <!-- Note: if image is not placed yet, it will fallback or show default alt text -->
        </div>

        <div class="surat-title">
            SURAT PERNYATAAN SISWA
        </div>

        <div class="content">
            <p style="margin-top:0;">Yang bertandatangan di bawah ini :</p>
            
            <table>
                <tr>
                    <td class="label-col">Nama</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($data['nama_siswa']); ?></td>
                </tr>
                <tr>
                    <td class="label-col">NIS</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($nis); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Kelas</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($kelas_rombel); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Program Keahlian</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($program_keahlian); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Masalah</td>
                    <td class="colon-col">:</td>
                    <td class="val-col">
                        <span class="masalah-text"><?php echo nl2br(htmlspecialchars($masalah)); ?></span>
                    </td>
                </tr>
                <!-- Spacer -->
                <tr><td colspan="3" style="height:10px;"></td></tr>
            </table>

            <table>
                <tr>
                    <td class="label-col">Nama Orang Tua</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($nama_ortu); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Pekerjaan</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($pekerjaan); ?></td>
                </tr>
                <tr>
                    <td class="label-col">Alamat Rumah</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($alamat); ?></td>
                </tr>
                <tr>
                    <td class="label-col">No. Hp./Telp.</td>
                    <td class="colon-col">:</td>
                    <td class="val-col"><?php echo htmlspecialchars($no_telp); ?></td>
                </tr>
            </table>

            <p style="text-indent: 0; margin-top: 15px;">
                Menyatakan dan berjanji akan bersungguh-sungguh berubah dan bersedia mentaati aturan dan tata tertib sekolah. Apabila selama masa pembinaan tidak mengalami perubahan, maka siswa yang bersangkutan dikembalikan kepada orang tua/wali.
            </p>
            <p style="text-indent: 0; margin-bottom: 40px;">
                Demikian surat pernyataan ini saya buat dengan sesungguhnya tanpa ada tekanan dari siapapun.
            </p>

            <div class="signature-area">
                <table class="signature-table">
                    <tr>
                        <td>
                            Mengetahui,<br>
                            Orang Tua/Wali siswa
                        </td>
                        <td>
                            Denpasar, <?php echo $tanggal_sekarang_alt; ?><br>
                            Siswa yang bersangkutan
                        </td>
                    </tr>
                    <tr><td colspan="2" style="height:20px;"></td></tr>
                    <tr>
                        <td style="vertical-align:bottom;">
                            <?php if ($nama_ortu !== '................................................'): ?>
                                <span class="name-dots"><?php echo htmlspecialchars($nama_ortu); ?></span>
                            <?php
else: ?>
                                <span class="name-dots"></span>
                            <?php
endif; ?>
                        </td>
                        <td style="vertical-align:bottom;">
                            <span class="name-dots"><?php echo htmlspecialchars($data['nama_siswa']); ?></span>
                        </td>
                    </tr>
                </table>

                <table class="signature-table" style="margin-top:30px;">
                    <tr>
                        <td>Guru Bimbingan Konseling</td>
                        <td>Guru Wali Kelas</td>
                    </tr>
                    <tr><td colspan="2" style="height:20px;"></td></tr>
                    <tr>
                        <td style="vertical-align:bottom;">
                            <span class="name-line">Ni Putu Chintya Pradnya Suari, S.Pd</span>
                        </td>
                        <td style="vertical-align:bottom;">
                            <?php if ($wali_kelas !== '................................................'): ?>
                                <span class="name-dots"><?php echo htmlspecialchars($wali_kelas); ?></span>
                            <?php
else: ?>
                                <span class="name-dots"></span>
                            <?php
endif; ?>
                        </td>
                    </tr>
                </table>

                <table class="signature-table" style="margin-top:30px; width: 100%;">
                    <tr>
                        <td style="width:100%;">
                            Mengetahui<br>
                            Wakasek Kesiswaan
                        </td>
                    </tr>
                    <tr><td style="height:20px;"></td></tr>
                    <tr>
                        <td style="vertical-align:bottom;">
                            <span class="name-line">Bagus Putu Eka Wijaya, S.Kom</span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
