<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

// Fetch all students
$query_siswa = "SELECT nis, nama_siswa FROM siswa WHERE status = 'aktif' ORDER BY nama_siswa ASC";
$result_siswa = mysqli_query($conn, $query_siswa);
$siswa_options = [];
if ($result_siswa) {
    while ($row = mysqli_fetch_assoc($result_siswa)) {
        $siswa_options[] = $row;
    }
}

// Generate default No Surat (optional, visual only)
$tahun = date('Y');
$bulan_romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
$bulan = $bulan_romawi[(int)date('m') - 1];
$default_no_surat = "001/SMKTI/B/$bulan/$tahun";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Surat Pemanggilan Orang Tua - SISP</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 500; color: var(--navy-700); }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; font-family: inherit; }
        .form-control:focus { outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-control[readonly] { background-color: #f1f5f9; color: #64748b; cursor: not-allowed; }
        .select2-container .select2-selection--single { height: 42px; border: 1px solid #cbd5e1; border-radius: 6px; display: flex; align-items: center; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height: normal; padding-left: 14px; color: #334155; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height: 40px; right: 10px; }
        .section-title { font-size: 1.1rem; font-weight: 600; color: var(--navy-800); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid #e2e8f0; }
        .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 768px) { .two-cols { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-envelope-open-text" style="color:var(--navy-500)"></i> Pemanggilan Orang Tua</h1>
                <span class="breadcrumb">Admin / Surat / Pemanggilan Orang Tua</span>
            </div>
        </div>
        
        <div class="page-content">
            <div class="card" style="max-width: 800px; margin: 0 auto;">
                <div class="card-header">
                    <h3><i class="fas fa-print"></i> Form Cetak Surat Pemanggilan</h3>
                </div>
                <div class="card-body" style="padding: 24px;">
                    <form action="cetak.php" method="POST" target="_blank" id="cetakForm">
                        
                        <div class="section-title"><i class="fas fa-file-alt"></i> Info Surat</div>
                        <div class="two-cols">
                            <div class="form-group">
                                <label class="form-label">No. Surat</label>
                                <input type="text" name="no_surat" class="form-control" value="<?php echo htmlspecialchars($default_no_surat); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Lampiran</label>
                                <input type="text" name="lampiran" class="form-control" value="-" required>
                            </div>
                        </div>

                        <div class="section-title" style="margin-top:10px;"><i class="fas fa-user-graduate"></i> Siswa Tertuju</div>
                        <div class="form-group">
                            <label class="form-label">Pilih Siswa <span style="color:red">*</span></label>
                            <select name="nis" id="nis" class="form-control select2" required>
                                <option value="">-- Pilih Siswa --</option>
                                <?php foreach ($siswa_options as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['nis']); ?>">
                                        <?php echo htmlspecialchars($s['nis'] . ' - ' . $s['nama_siswa']); ?>
                                    </option>
                                <?php
endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="dataPreview" style="display:none; background:#f8fafc; padding:15px; border-radius:6px; border:1px solid #e2e8f0; margin-bottom:20px;">
                            <div class="two-cols">
                                <div>
                                    <span style="font-size:0.85rem; color:#64748b; font-weight:600;">Kelas / NIS</span><br>
                                    <strong id="preview_kelas" style="color:var(--navy-700);"></strong>
                                </div>
                                <div>
                                    <span style="font-size:0.85rem; color:#64748b; font-weight:600;">Nama Orang Tua / Wali</span><br>
                                    <strong id="preview_ortu" style="color:var(--navy-700);"></strong>
                                </div>
                            </div>
                        </div>

                        <div class="section-title"><i class="far fa-calendar-check"></i> Jadwal Pemanggilan</div>
                        <div class="two-cols">
                            <div class="form-group">
                                <label class="form-label">Tanggal Pemanggilan</label>
                                <input type="date" name="tanggal" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Waktu Pemanggilan</label>
                                <input type="time" name="waktu" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tempat</label>
                                <input type="text" name="tempat" class="form-control" value="SMK TI Bali Global Denpasar" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Keperluan</label>
                                <input type="text" name="keperluan" class="form-control" value="Masalah Disiplin Siswa" required>
                            </div>
                        </div>

                        <div class="form-actions" style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 10px;">
                            <button type="reset" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-undo"></i> Reset</button>
                            <button type="submit" class="btn btn-primary" style="background-color: var(--color-success); border-color: var(--color-success);"><i class="fas fa-print"></i> Cetak Surat Pemanggilan</button>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({ placeholder: "-- Pilih Siswa --", allowClear: true });
            
            $('#nis').on('change', function() {
                var nis = $(this).val();
                if (nis) {
                    $.ajax({
                        url: 'get_siswa_data.php',
                        type: 'GET',
                        data: { nis: nis },
                        dataType: 'json',
                        success: function(response) {
                            if(response.status === 'success') {
                                var data = response.data;
                                var kls = data.kelas_rombel + ' / ' + nis;
                                $('#preview_kelas').text(kls);
                                $('#preview_ortu').text(data.nama_ortu || '-');
                                $('#dataPreview').slideDown();
                            } else {
                                alert('Data siswa tidak ditemukan.');
                                $('#dataPreview').slideUp();
                            }
                        },
                        error: function() {
                            alert('Terjadi kesalahan saat mengambil data.');
                        }
                    });
                } else {
                    $('#dataPreview').slideUp();
                }
            });
        });

        function resetForm() {
            $('#nis').val(null).trigger('change');
            $('#dataPreview').slideUp();
        }
    </script>
</body>
</html>
