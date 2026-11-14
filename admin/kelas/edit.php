<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: index.php');
    exit;
}

// Ambil data kelas saat ini
$query_fetch = "SELECT * FROM kelas WHERE id_kelas = ?";
$stmt_fetch = mysqli_prepare($conn, $query_fetch);
mysqli_stmt_bind_param($stmt_fetch, 'i', $id);
mysqli_stmt_execute($stmt_fetch);
$result_fetch = mysqli_stmt_get_result($stmt_fetch);
$kelas = mysqli_fetch_assoc($result_fetch);
mysqli_stmt_close($stmt_fetch);

if (!$kelas) {
    header('Location: index.php');
    exit;
}

$errors = [];

// Fetch dropdown data
$tingkat_options = [];
$res_tingkat = mysqli_query($conn, "SELECT id_tingkat, tingkat FROM tingkat ORDER BY tingkat ASC");
if ($res_tingkat)
    while ($row = mysqli_fetch_assoc($res_tingkat))
        $tingkat_options[] = $row;

$program_options = [];
$res_prog = mysqli_query($conn, "SELECT id_program_keahlian, program_keahlian FROM program_keahlian ORDER BY program_keahlian ASC");
if ($res_prog)
    while ($row = mysqli_fetch_assoc($res_prog))
        $program_options[] = $row;

$guru_options = [];
$res_guru = mysqli_query($conn, "SELECT kode_guru, nama_pengguna FROM guru WHERE aktif='Y' ORDER BY nama_pengguna ASC");
if ($res_guru)
    while ($row = mysqli_fetch_assoc($res_guru))
        $guru_options[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tingkat = $_POST['id_tingkat'] ?? '';
    $id_program_keahlian = $_POST['id_program_keahlian'] ?? '';
    $rombel = $_POST['rombel'] ?? '';
    $kode_guru = $_POST['kode_guru'] ?? '';

    // Validation
    if (empty($id_tingkat))
        $errors[] = 'Tingkat wajib dipilih.';
    if (empty($id_program_keahlian))
        $errors[] = 'Program Keahlian wajib dipilih.';
    if (empty($rombel) || !is_numeric($rombel))
        $errors[] = 'Rombongan Belajar (Rombel) wajib diisi dengan angka valid.';

    // Check duplicate class combination (mengecualikan ID diri sendiri)
    if (empty($errors)) {
        $stmt_cek = mysqli_prepare($conn, "SELECT id_kelas FROM kelas WHERE id_tingkat = ? AND id_program_keahlian = ? AND rombel = ? AND id_kelas != ?");
        mysqli_stmt_bind_param($stmt_cek, 'iiii', $id_tingkat, $id_program_keahlian, $rombel, $id);
        mysqli_stmt_execute($stmt_cek);
        mysqli_stmt_store_result($stmt_cek);
        if (mysqli_stmt_num_rows($stmt_cek) > 0) {
            $errors[] = "Kombinasi kelas (Tingkat + Program Keahlian + Rombel) ini sudah ada di database untuk kelas lain.";
        }
        mysqli_stmt_close($stmt_cek);
    }

    if (empty($errors)) {
        $final_kode_guru = (!empty($kode_guru)) ? $kode_guru : null;

        $stmt_update = mysqli_prepare($conn, "UPDATE kelas SET id_tingkat=?, id_program_keahlian=?, rombel=?, kode_guru=? WHERE id_kelas=?");
        mysqli_stmt_bind_param($stmt_update, 'iiisi', $id_tingkat, $id_program_keahlian, $rombel, $final_kode_guru, $id);

        if (mysqli_stmt_execute($stmt_update)) {
            header('Location: index.php?success=edit');
            exit;
        }
        else {
            $errors[] = "Gagal memperbarui data kelas: " . mysqli_stmt_error($stmt_update);
        }
        mysqli_stmt_close($stmt_update);
    }

    // Kembalikan input jika ada error
    $kelas['id_tingkat'] = $id_tingkat;
    $kelas['id_program_keahlian'] = $id_program_keahlian;
    $kelas['rombel'] = $rombel;
    $kelas['kode_guru'] = $kode_guru;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Kelas - SISP</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-card {
            background: var(--color-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-card);
            max-width: 650px;
            margin: 0 auto;
            overflow: hidden;
        }
        .form-card-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--color-border);
            background: linear-gradient(135deg, var(--color-warning, #f59e0b) 0%, #d97706 100%);
        }
        .form-card-header h3 { color: #fff; margin: 0; font-size: 18px; }
        .form-card-header p { color: rgba(255,255,255,0.85); margin: 4px 0 0; font-size: 13px; }
        .form-card-body { padding: 32px; }
        .form-group { margin-bottom: 22px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--color-text); margin-bottom: 8px; }
        .form-label span.required { color: var(--color-danger); margin-left: 2px; }
        .form-control {
            width: 100%; padding: 11px 16px; border: 1.5px solid var(--color-border);
            border-radius: var(--radius-sm); font-size: 14px; font-family: 'Inter', sans-serif;
            color: var(--color-text); background: var(--color-bg); transition: var(--transition); outline: none;
        }
        .form-control:focus { border-color: var(--color-accent); background: #fff; box-shadow: 0 0 0 3px rgba(63,114,175,0.12); }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-actions { display: flex; gap: 12px; padding-top: 16px; margin-top: 32px; border-top: 1px solid var(--color-border); justify-content: flex-end; }
        .alert-danger { background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; border-radius: var(--radius-md); padding: 14px 20px; margin-bottom: 24px; }
        .alert-danger ul { margin: 6px 0 0 16px; list-style: disc; }
        .input-icon-wrap { position: relative; }
        .input-icon-wrap .form-control { padding-left: 40px; }
        .input-icon-wrap .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--color-text-muted); font-size: 14px; }
        .kode-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            color: var(--navy-700);
            font-weight: 700;
            padding: 10px 18px;
            border-radius: var(--radius-sm);
            font-size: 15px;
            margin-bottom: 24px;
            border: 1px dashed #cbd5e1;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <?php include '../../includes/sidebar.php'; ?>
        <div class="main-content">
            <div class="topbar">
                <div class="topbar-title">Edit Data Kelas</div>
                <div class="topbar-user">
                    <span><?php echo htmlspecialchars(getUserName()); ?></span>
                    <div class="sidebar-user-avatar" style="width:32px;height:32px;font-size:12px;">A</div>
                </div>
            </div>

            <div class="page-content">
                <div style="margin-bottom:20px;font-size:13px;color:var(--color-text-muted);">
                    <a href="index.php" style="color:var(--color-warning, #f59e0b);font-weight:500;"><i class="fas fa-chevron-left" style="font-size:11px;"></i> Kembali ke Data Kelas</a>
                </div>

                <div class="form-card">
                    <div class="form-card-header">
                        <h3><i class="fas fa-edit" style="margin-right:8px;"></i>Form Edit Data Kelas</h3>
                        <p>Perbarui tingkat, program keahlian, rombel, atau wali kelas.</p>
                    </div>
                    <div class="form-card-body">
                        
                        <div class="kode-badge">
                            <i class="fas fa-fingerprint" style="color:var(--navy-500)"></i>
                            SYSTEM ID: <?php echo htmlspecialchars($kelas['id_kelas']); ?>
                        </div>

                        <?php if (!empty($errors)): ?>
                            <div class="alert-danger">
                                <strong><i class="fas fa-exclamation-triangle"></i> Terdapat kesalahan:</strong>
                                <ul><?php foreach ($errors as $err): ?><li><?php echo htmlspecialchars($err); ?></li><?php
    endforeach; ?></ul>
                            </div>
                        <?php
endif; ?>

                        <form method="POST" action="edit.php?id=<?php echo urlencode($id); ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="id_tingkat">Tingkat Kelas <span class="required">*</span></label>
                                    <select id="id_tingkat" name="id_tingkat" class="form-control" required>
                                        <option value="">-- Pilih Tingkat --</option>
                                        <?php foreach ($tingkat_options as $opt): ?>
                                            <option value="<?php echo $opt['id_tingkat']; ?>" <?php echo($kelas['id_tingkat'] == $opt['id_tingkat']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt['tingkat']); ?></option>
                                        <?php
endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="rombel">Rombel (Rombongan Belajar) <span class="required">*</span></label>
                                    <div class="input-icon-wrap">
                                        <span class="input-icon"><i class="fas fa-hashtag"></i></span>
                                        <input type="number" id="rombel" name="rombel" class="form-control" min="1" max="20" placeholder="Contoh: 1, 2, 3" value="<?php echo htmlspecialchars($kelas['rombel']); ?>" required>
                                    </div>
                                    <small style="color:#64748b; margin-top:4px; display:block;">Hanya angka urutan.</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="id_program_keahlian">Program Keahlian (Jurusan) <span class="required">*</span></label>
                                <select id="id_program_keahlian" name="id_program_keahlian" class="form-control" required>
                                    <option value="">-- Pilih Program Keahlian --</option>
                                    <?php foreach ($program_options as $opt): ?>
                                        <option value="<?php echo $opt['id_program_keahlian']; ?>" <?php echo($kelas['id_program_keahlian'] == $opt['id_program_keahlian']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt['program_keahlian']); ?></option>
                                    <?php
endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group" style="padding-top: 16px; margin-top: 24px; border-top: 1px dashed #cbd5e1;">
                                <label class="form-label" for="kode_guru">Wali Kelas Lengkap (Opsional)</label>
                                <div class="input-icon-wrap">
                                    <select id="kode_guru" name="kode_guru" class="form-control" style="padding-left: 40px; appearance:none;">
                                        <option value="">-- Belum ada Wali Kelas --</option>
                                        <?php foreach ($guru_options as $opt): ?>
                                            <option value="<?php echo htmlspecialchars($opt['kode_guru']); ?>" <?php echo(($kelas['kode_guru'] ?? '') === $opt['kode_guru']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($opt['nama_pengguna']); ?> (<?php echo htmlspecialchars($opt['kode_guru']); ?>)</option>
                                        <?php
endforeach; ?>
                                    </select>
                                    <span class="input-icon"><i class="fas fa-user-tie"></i></span>
                                </div>
                                <small style="color:#64748b; margin-top:6px; display:block;">Pilih guru yang ditugaskan membina kelas ini. Bisa ditambahkan nanti.</small>
                            </div>

                            <div class="form-actions">
                                <a href="index.php" class="btn btn-secondary"><i class="fas fa-times"></i> Batal</a>
                                <button type="submit" class="btn btn-primary" style="background-color: var(--color-warning, #f59e0b); border-color: var(--color-warning, #f59e0b);"><i class="fas fa-save"></i> Perbarui Kelas</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
