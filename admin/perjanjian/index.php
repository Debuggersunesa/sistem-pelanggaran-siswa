<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

// Fetch all students for the dropdown
$query_siswa = "SELECT nis, nama_siswa FROM siswa WHERE status = 'aktif' ORDER BY nama_siswa ASC";
$result_siswa = mysqli_query($conn, $query_siswa);
$siswa_options = [];
if ($result_siswa) {
    while ($row = mysqli_fetch_assoc($result_siswa)) {
        $siswa_options[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Surat Perjanjian - SISP</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Select2 for better dropdown -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--navy-700);
        }
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s;
            font-family: inherit;
        }
        .form-control:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        .form-control[readonly] {
            background-color: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }
        /* Select2 Customization */
        .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            display: flex;
            align-items: center;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: normal;
            padding-left: 14px;
            color: #334155;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px;
            right: 10px;
        }
        .card-body {
            padding: 24px;
        }
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--navy-800);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .two-cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .two-cols {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-file-contract" style="color:var(--navy-500)"></i> Cetak Surat Perjanjian</h1>
                <span class="breadcrumb">Admin / Surat / Perjanjian Siswa</span>
            </div>
        </div>
        
        <div class="page-content">
            <div class="card" style="max-width: 800px; margin: 0 auto;">
                <div class="card-header">
                    <h3><i class="fas fa-print"></i> Form Cetak Surat Perjanjian</h3>
                </div>
                <div class="card-body">
                    <form action="cetak.php" method="POST" target="_blank" id="cetakForm">
                        
                        <div class="form-group">
                            <label class="form-label" for="nis">Pilih Siswa <span style="color:red">*</span></label>
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

                        <!-- Data Preview Container -->
                        <div id="dataPreview" style="display:none; margin-top: 30px;">
                            <div class="section-title"><i class="fas fa-user-graduate"></i> Data Siswa</div>
                            <div class="two-cols">
                                <div class="form-group">
                                    <label class="form-label">Nama Lengkap</label>
                                    <input type="text" id="preview_nama" class="form-control" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Kelas / Rombel</label>
                                    <input type="text" id="preview_kelas" class="form-control" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Program Keahlian</label>
                                    <input type="text" id="preview_jurusan" class="form-control" readonly>
                                </div>
                            </div>

                            <div class="section-title" style="margin-top: 20px;"><i class="fas fa-users"></i> Data Orang Tua / Wali</div>
                            <div class="two-cols">
                                <div class="form-group">
                                    <label class="form-label">Nama Orang Tua</label>
                                    <input type="text" id="preview_ortu" class="form-control" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Pekerjaan</label>
                                    <input type="text" id="preview_pekerjaan" class="form-control" readonly>
                                </div>
                                <div class="form-group" style="grid-column: 1 / -1;">
                                    <label class="form-label">Alamat Rumah</label>
                                    <input type="text" id="preview_alamat" class="form-control" readonly>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">No. Hp/Telp</label>
                                    <input type="text" id="preview_telp" class="form-control" readonly>
                                </div>
                            </div>
                            
                            <div class="section-title" style="margin-top: 20px;"><i class="fas fa-exclamation-circle"></i> Detail Masalah</div>
                            <div class="form-group">
                                <label class="form-label" for="masalah">Masalah yang Dibuat <span style="color:red">*</span></label>
                                <textarea name="masalah" id="masalah" class="form-control" rows="4" placeholder="Jelaskan masalah pelanggaran yang dilakukan oleh siswa..." required></textarea>
                            </div>

                            <div class="form-actions" style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 10px;">
                                <button type="reset" class="btn btn-secondary" onclick="resetForm()"><i class="fas fa-undo"></i> Reset</button>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-print"></i> Cetak Surat Sekarang</button>
                            </div>
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
            $('.select2').select2({
                placeholder: "-- Pilih Siswa --",
                allowClear: true
            });

            $('#nis').on('change', function() {
                var nis = $(this).val();
                
                if (nis) {
                    // Fetch data
                    $.ajax({
                        url: 'get_siswa_data.php',
                        type: 'GET',
                        data: { nis: nis },
                        dataType: 'json',
                        success: function(response) {
                            if(response.status === 'success') {
                                var data = response.data;
                                $('#preview_nama').val(data.nama_siswa);
                                $('#preview_kelas').val(data.kelas_rombel);
                                $('#preview_jurusan').val(data.program_keahlian);
                                $('#preview_ortu').val(data.nama_ortu || '-');
                                $('#preview_pekerjaan').val(data.pekerjaan || '-');
                                $('#preview_alamat').val(data.alamat_ortu || data.alamat_siswa || '-');
                                $('#preview_telp').val(data.no_telp || '-');
                                
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
