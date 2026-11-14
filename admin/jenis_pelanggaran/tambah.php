<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = trim($_POST['jenis'] ?? '');
    $poin = trim($_POST['poin'] ?? '');

    if (empty($jenis)) {
        $errors[] = 'Nama jenis pelanggaran wajib diisi.';
    }
    if ($poin === '') {
        $errors[] = 'Poin pelanggaran wajib diisi.';
    }
    elseif (!is_numeric($poin) || (int)$poin <= 0) {
        $errors[] = 'Poin harus berupa angka yang lebih besar dari 0.';
    }

    if (empty($errors)) {
        $poin = (int)$poin;
        // Check for duplicate jenis
        $cek_query = "SELECT id_jenis_pelanggaran FROM jenis_pelanggaran WHERE jenis = ?";
        $stmt_cek = mysqli_prepare($conn, $cek_query);
        mysqli_stmt_bind_param($stmt_cek, 's', $jenis);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);

        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $errors[] = 'Jenis pelanggaran ini sudah ada.';
        }
        else {
            $query = "INSERT INTO jenis_pelanggaran (jenis, poin) VALUES (?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'si', $jenis, $poin);

            if (mysqli_stmt_execute($stmt)) {
                // To show success message on index
                echo "<script>
                    window.location.href = 'index.php?msg=tambah_sukses';
                </script>";
                exit;
            }
            else {
                $errors[] = 'Gagal menyimpan data ke database: ' . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($stmt_cek);
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Jenis Pelanggaran - SPPS</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            max-width: 850px; /* Matching siswa/tambah width mostly, though it can be smaller for fewer fields */
            margin: 0 auto;
            overflow: hidden;
        }
        .form-card-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--color-border);
            /* Following admin/siswa/tambah.php header gradient */
            background: linear-gradient(135deg, var(--color-main) 0%, var(--color-accent) 100%);
        }
        .form-card-header h3 {
            color: #fff;
            margin: 0;
            font-size: 18px;
        }
        .form-card-header p {
            color: rgba(255,255,255,0.75);
            margin: 4px 0 0;
            font-size: 13px;
        }
        .form-card-body {
            padding: 32px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-main);
            margin-top: 32px;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--color-bg);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-section-title:first-child {
            margin-top: 0;
        }

        .form-group {
            margin-bottom: 22px;
        }
        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--color-text);
            margin-bottom: 8px;
        }
        .form-label span.required {
            color: var(--color-danger);
            margin-left: 2px;
        }
        .form-control {
            width: 100%;
            padding: 11px 16px;
            border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--color-text);
            background: var(--color-bg);
            transition: var(--transition);
            outline: none;
        }
        .form-control:focus {
            border-color: var(--color-accent);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(63,114,175,0.12);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            padding-top: 16px;
            margin-top: 32px;
            border-top: 1px solid var(--color-border);
            justify-content: flex-end;
        }
        .alert-danger {
            background: #fef2f2;
            border: 1px solid #fca5a5;
            color: #991b1b;
            border-radius: var(--radius-md);
            padding: 14px 20px;
            margin-bottom: 24px;
        }
        .alert-danger ul {
            margin: 6px 0 0 16px;
            list-style: disc;
        }
        .input-icon-wrap {
            position: relative;
        }
        .input-icon-wrap .form-control {
            padding-left: 40px;
        }
        .input-icon-wrap .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-muted);
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Tambah Jenis Pelanggaran</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>
            
            <div class="page-content">
                <div style="margin-bottom:20px;font-size:13px;color:var(--color-text-muted);">
                    <a href="index.php" style="color:var(--color-accent);font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Jenis Pelanggaran</a>
                </div>
                
                <div class="form-card">
                    <div class="form-card-header">
                        <h3><i class="fas fa-exclamation-circle" style="margin-right:8px;"></i>Form Tambah Jenis Pelanggaran</h3>
                        <p>Lengkapi nama pelanggaran dan bobot poin pelanggarannya.</p>
                    </div>
                    <div class="form-card-body">
                        <?php if (!empty($errors)): ?>
                            <div class="alert-danger">
                                <strong><i class="fas fa-exclamation-triangle"></i> Terdapat kesalahan:</strong>
                                <ul>
                                    <?php foreach ($errors as $err): ?>
                                        <li><?php echo htmlspecialchars($err); ?></li>
                                    <?php
    endforeach; ?>
                                </ul>
                            </div>
                        <?php
endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-section-title">
                                <i class="fas fa-info-circle"></i> Detail Pelanggaran
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group" style="grid-column: span 2;">
                                    <label class="form-label" for="jenis">Nama / Jenis Pelanggaran <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-clipboard-list"></i></span>
                                        <input type="text" id="jenis" name="jenis" class="form-control" 
                                               placeholder="Contoh: Terlambat masuk sekolah" 
                                               value="<?php echo htmlspecialchars($_POST['jenis'] ?? ''); ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label" for="poin">Poin Pelanggaran <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-star"></i></span>
                                        <input type="number" id="poin" name="poin" class="form-control" 
                                               placeholder="Contoh: 10" 
                                               value="<?php echo htmlspecialchars($_POST['poin'] ?? ''); ?>" 
                                               required min="1">
                                    </div>
                                    <small style="color:#64748b; margin-top:4px; display:block;">Masukkan angka lebih dari 0.</small>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Batal
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Simpan Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
