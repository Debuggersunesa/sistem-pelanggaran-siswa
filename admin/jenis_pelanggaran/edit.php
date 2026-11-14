<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$error = '';
$msg = '';

$id = $_GET['id'] ?? '';

if (empty($id)) {
    header("Location: index.php");
    exit;
}

// Fetch existing data
$id_clean = mysqli_real_escape_string($conn, $id);
$query_get = "SELECT jenis, poin FROM jenis_pelanggaran WHERE id_jenis_pelanggaran = '$id_clean'";
$result_get = mysqli_query($conn, $query_get);

if (!$result_get || mysqli_num_rows($result_get) === 0) {
    header("Location: index.php");
    exit;
}

$data = mysqli_fetch_assoc($result_get);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jenis = trim($_POST['jenis'] ?? '');
    $poin = trim($_POST['poin'] ?? '');

    if (empty($jenis) || $poin === '') {
        $error = 'Semua field wajib diisi.';
    }
    elseif (!is_numeric($poin) || (int)$poin <= 0) {
        $error = 'Poin harus berupa angka yang lebih besar dari 0.';
    }
    else {
        $poin = (int)$poin;

        // Check for duplicate jenis (excluding current ID)
        $cek_query = "SELECT id_jenis_pelanggaran FROM jenis_pelanggaran WHERE jenis = ? AND id_jenis_pelanggaran != ?";
        $stmt_cek = mysqli_prepare($conn, $cek_query);
        mysqli_stmt_bind_param($stmt_cek, 'si', $jenis, $id);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);

        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $error = 'Jenis pelanggaran ini sudah ada.';
        }
        else {
            $query = "UPDATE jenis_pelanggaran SET jenis = ?, poin = ? WHERE id_jenis_pelanggaran = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sii', $jenis, $poin, $id);

            if (mysqli_stmt_execute($stmt)) {
                $msg = 'Data jenis pelanggaran berhasil diperbarui.';
                // Update $data array so the form shows new values
                $data['jenis'] = $jenis;
                $data['poin'] = $poin;
            }
            else {
                $error = 'Gagal menyimpan data ke database: ' . mysqli_error($conn);
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
    <title>Edit Jenis Pelanggaran - SISP</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1><i class="fas fa-edit" style="color:var(--navy-500)"></i> Edit Jenis Pelanggaran</h1>
                <span class="breadcrumb"><a href="index.php">Jenis Pelanggaran</a> / Edit</span>
            </div>
        </div>
        
        <div class="page-content">
            <?php if ($msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php
endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php
endif; ?>
            
            <div class="card" style="max-width: 600px; margin: 0 auto;">
                <div class="card-header">
                    <h3>Form Edit Jenis Pelanggaran</h3>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="jenis">Nama / Jenis Pelanggaran <span class="text-danger">*</span></label>
                            <input type="text" id="jenis" name="jenis" class="form-control" value="<?php echo htmlspecialchars($_POST['jenis'] ?? $data['jenis']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="poin">Poin Pelanggaran <span class="text-danger">*</span></label>
                            <input type="number" id="poin" name="poin" class="form-control" value="<?php echo htmlspecialchars($_POST['poin'] ?? $data['poin']); ?>" required min="1">
                        </div>
                        
                        <div class="form-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: flex-end;">
                            <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Kembali</a>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan Perubahan</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
