<?php

require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);

        $query_cek = "SELECT COUNT(*) FROM pelanggaran_siswa WHERE nis = $id";
        $res_cek = mysqli_query($conn, $query_cek);

        if (!$res_cek) {
            $error = "Terjadi kesalahan sistem saat mengecek data pelanggaran.";
        }
        else {
            $cek = mysqli_fetch_row($res_cek)[0];

            if ($cek > 0) {
                $error = 'Siswa tidak bisa dihapus karena memiliki riwayat pelanggaran tercatat.';
            }
            else {
                $stmt_foto = mysqli_prepare($conn, "SELECT foto FROM siswa WHERE nis = ?");
                mysqli_stmt_bind_param($stmt_foto, 'i', $id);
                mysqli_stmt_execute($stmt_foto);
                $res_foto = mysqli_stmt_get_result($stmt_foto);
                $foto_lama = '';

                if ($row_foto = mysqli_fetch_assoc($res_foto)) {
                    $foto_lama = $row_foto['foto'];
                }

                mysqli_stmt_close($stmt_foto);

                mysqli_begin_transaction($conn);
                try {
                    $stmt_del = mysqli_prepare($conn, "DELETE FROM siswa WHERE nis = ?");
                    mysqli_stmt_bind_param($stmt_del, 'i', $id);

                    if (!mysqli_stmt_execute($stmt_del)) {
                        throw new Exception("Gagal menghapus data dari database.");
                    }

                    mysqli_stmt_close($stmt_del);
                    mysqli_commit($conn);

                    if (!empty($foto_lama) && file_exists('../../uploads/siswa/' . $foto_lama)) {
                        unlink('../../uploads/siswa/' . $foto_lama);
                    }
                }
                catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$tahun_ajaran_aktif = '-';
$query_ta = "SELECT tahun FROM tahun_ajaran WHERE aktif = 'Y' LIMIT 1";
$res_ta = mysqli_query($conn, $query_ta);

if ($res_ta && mysqli_num_rows($res_ta) > 0) {
    $row_ta = mysqli_fetch_assoc($res_ta);
    $tahun_ajaran_aktif = $row_ta['tahun'];
}

$search = trim($_GET['q'] ?? '');
$filter_tingkat = $_GET['tingkat'] ?? '';
$filter_jurusan = $_GET['jurusan'] ?? '';
$filter_ta = $_GET['ta'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(s.nama_siswa LIKE '%$s%' OR s.nis LIKE '%$s%')";
}

if ($filter_tingkat) {
    $conditions[] = "t.id_tingkat = " . (int)$filter_tingkat;
}

if ($filter_jurusan) {
    $conditions[] = "pk.id_program_keahlian = " . (int)$filter_jurusan;
}

if ($filter_ta) {
    $conditions[] = "s.id_tahun_ajaran = " . (int)$filter_ta;
}

$where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query_total = "SELECT COUNT(*) FROM siswa s $where";
$res_total = mysqli_query($conn, $query_total);

if (!$res_total) {
    die("Query Error: " . mysqli_error($conn));
}

$total = mysqli_fetch_row($res_total)[0] ?? 0;
$total_pages = max(1, ceil($total / $limit));

$sort = $_GET['sort'] ?? 'terbaru';
$order_sql = "ORDER BY s.nama_siswa ASC";

if ($sort === 'terbaru') {
    $order_sql = "ORDER BY s.nis DESC";
}
elseif ($sort === 'name_asc') {
    $order_sql = "ORDER BY s.nama_siswa ASC";
}
elseif ($sort === 'poin_desc') {
    $order_sql = "ORDER BY total_poin DESC, s.nama_siswa ASC";
}

$tingkat_options_res = mysqli_query($conn, "SELECT id_tingkat, tingkat FROM tingkat ORDER BY tingkat ASC");
$jurusan_options_res = mysqli_query($conn, "SELECT id_program_keahlian, program_keahlian FROM program_keahlian ORDER BY program_keahlian ASC");
$ta_options_res = mysqli_query($conn, "SELECT id_tahun_ajaran, tahun, aktif FROM tahun_ajaran ORDER BY tahun DESC");

$query_siswa = "
    SELECT 
        s.nis, 
        s.nama_siswa, 
        s.jenis_kelamin, 
        s.alamat, 
        s.status,
        s.foto,
        ta.tahun AS tahun_ajaran_siswa,
        t.tingkat, 
        pk.program_keahlian, 
        pk.deskripsi AS jurusan_deskripsi,
        k.rombel, 
        ow.nama AS nama_ortu,
        ow.status_hubungan,
        ow.no_telp AS telp_ortu,
        ow.pekerjaan AS pekerjaan_ortu,
        COALESCE((
            SELECT SUM(jp.poin) 
            FROM pelanggaran_siswa ps 
            JOIN jenis_pelanggaran jp ON ps.id_jenis_pelanggaran = jp.id_jenis_pelanggaran 
            WHERE ps.nis = s.nis
        ), 0) AS total_poin
    FROM siswa s 
    LEFT JOIN kelas k ON s.id_kelas = k.id_kelas 
    LEFT JOIN tingkat t ON k.id_tingkat = t.id_tingkat 
    LEFT JOIN program_keahlian pk ON k.id_program_keahlian = pk.id_program_keahlian 
    LEFT JOIN ortu_wali ow ON s.id_ortu_wali = ow.id_ortu_wali
    LEFT JOIN tahun_ajaran ta ON s.id_tahun_ajaran = ta.id_tahun_ajaran
    $where 
    $order_sql 
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query_siswa);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - SISP</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-wrapper {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .table-wrapper table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-wrapper th {
            background-color: var(--navy-50);
            color: var(--navy-700);
            font-weight: 600;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid var(--navy-100);
        }
        .table-wrapper td {
            padding: 12px 16px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: middle;
        }
        .table-wrapper tbody tr:hover {
            background-color: #f9fafb;
        }
        .profile-icon {
            font-size: 2.5rem;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            background: #f3f4f6;
        }
        .profile-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .action-btns {
            display: flex;
            gap: 0.5rem;
        }
        .action-btn {
            background-color: #f1f5f9;
            color: #64748b;
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .action-btn.info-btn:hover {
            background-color: #3F72AF; 
            color: white;
            box-shadow: 0 4px 6px -1px rgba(63, 114, 175, 0.3);
        }
        .action-btn.edit-btn:hover {
            background-color: #f59e0b;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.3);
        }
        .action-btn.del-btn:hover {
            background-color: #ef4444;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(239, 68, 68, 0.3);
        }
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
        }
        .modal h3 {
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }
        .modal-actions button[type="submit"] {
            background-color: #ef4444 !important;
            color: white !important;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .modal-actions button[type="submit"]:hover {
            background-color: #dc2626 !important;
        }
        .detail-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 1rem;
        }
        .detail-modal {
            background-color: white;
            border-radius: 12px;
            width: 100%;
            max-width: 800px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            animation: modalFadeIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(20px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        .detail-modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            background-color: var(--navy-600, #112d4e);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .detail-modal-header h3 {
            margin: 0;
            font-size: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
        }
        .close-btn {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .close-btn:hover { 
            background: rgba(255, 255, 255, 0.2);
            transform: rotate(90deg);
        }
        .detail-modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            display: grid;
            grid-template-columns: 1fr 1.5fr;
            gap: 1.5rem;
        }
        .modal-col-left {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .profile-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
        }
        .avatar-container {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e2e8f0;
            color: var(--navy-500, #3f72af);
            margin-bottom: 0.5rem;
            border: 3px solid white;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .avatar-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .avatar-container i {
            font-size: 4rem;
        }
        .profile-card .student-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy-900, #111827);
            margin: 0;
            line-height: 1.2;
        }
        .profile-card .student-nis {
            font-family: monospace;
            background: #e2e8f0;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            color: #475569;
            font-size: 0.875rem;
        }
        .modal-col-right {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .info-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        .info-card-header {
            background: #f8fafc;
            padding: 0.75rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            color: var(--navy-800, #1e293b);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-card-header i {
            color: var(--navy-500, #3f72af);
        }
        .info-grid {
            padding: 1rem 1.25rem;
            display: grid;
            gap: 1rem;
        }
        .academic-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .parent-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .personal-grid {
            grid-template-columns: 1fr;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #64748b;
            font-weight: 600;
        }
        .info-value {
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 500;
        }
        .detail-modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e5e7eb;
            background-color: #f8fafc;
            display: flex;
            justify-content: flex-end;
        }
        @media (max-width: 768px) {
            .detail-modal-body {
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
                <h1><i class="fa-solid fa-graduation-cap" style="color:var(--navy-500)"></i> Data Siswa</h1>
                <span class="breadcrumb">Admin / Data Siswa</span>
            </div>
            <div class="topbar-right">
                <a href="tambah.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Siswa</a>
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
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-table-list"></i> Daftar Siswa (<?php echo $total; ?>)</h3>
                    
                    <form method="GET" class="header-controls" style="flex-wrap: wrap; gap: 10px;">
                        <div class="search-box" style="flex-grow: 1; min-width: 200px;">
                            <input type="text" name="q" placeholder="Cari Nama, NIS..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                        </div>
                        
                        <button type="button" onclick="openFilterModal()" style="display: flex; align-items: center; justify-content: center; gap: 8px; background-color: white; border: 1px solid #cbd5e1; border-radius: 20px; padding: 8px 16px; color: var(--color-accent); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-filter" style="color: var(--color-accent);"></i> Filter 
                            <?php if ($filter_tingkat || $filter_jurusan || $filter_ta): ?>
                                <span style="background: var(--navy-500); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; margin-left: 4px;">!</span>
                            <?php
endif; ?>
                        </button>

                        <div class="modal-overlay" id="filterModal" style="display:none;">
                            <div class="modal">
                                <h3><i class="fas fa-filter" style="color:var(--navy-500)"></i> Filter Data Siswa</h3>
                                
                                <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem; margin-bottom: 1.5rem; text-align: left;">
                                    <div>
                                        <label style="display:block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; color: var(--navy-700);">Kelas (Tingkat)</label>
                                        <select name="tingkat" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                                            <option value="">Semua Kelas</option>
                                            <?php while ($opt = mysqli_fetch_assoc($tingkat_options_res)): ?>
                                                <option value="<?php echo $opt['id_tingkat']; ?>" <?php echo $filter_tingkat == $opt['id_tingkat'] ? 'selected' : ''; ?>>Kelas <?= htmlspecialchars($opt['tingkat']); ?></option>
                                            <?php
endwhile; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label style="display:block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; color: var(--navy-700);">Jurusan (Program Keahlian)</label>
                                        <select name="jurusan" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                                            <option value="">Semua Jurusan</option>
                                            <?php while ($opt = mysqli_fetch_assoc($jurusan_options_res)): ?>
                                                <option value="<?php echo $opt['id_program_keahlian']; ?>" <?php echo $filter_jurusan == $opt['id_program_keahlian'] ? 'selected' : ''; ?>><?= htmlspecialchars($opt['program_keahlian']); ?></option>
                                            <?php
endwhile; ?>
                                        </select>
                                    </div>

                                    <div>
                                        <label style="display:block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; color: var(--navy-700);">Tahun Ajaran</label>
                                        <select name="ta" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                                            <option value="">Semua Thn. Ajaran</option>
                                            <?php while ($opt = mysqli_fetch_assoc($ta_options_res)): ?>
                                                <option value="<?php echo $opt['id_tahun_ajaran']; ?>" <?php echo $filter_ta == $opt['id_tahun_ajaran'] ? 'selected' : ''; ?>><?= htmlspecialchars($opt['tahun']); ?> <?= $opt['aktif'] == 'Y' ? '(Aktif)' : ''; ?></option>
                                            <?php
endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="modal-actions">
                                    <button type="button" onclick="closeFilterModal()" class="btn btn-secondary">Batal</button>
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Terapkan Filter</button>
                                </div>
                            </div>
                        </div>

                        <div class="filter-box">
                            <div class="select-wrapper">
                                <select name="sort" onchange="this.form.submit()" style="padding-right: 30px;">
                                    <option value="terbaru" <?php echo($sort === 'terbaru') ? 'selected' : ''; ?>>Urutan: Most Recent</option>
                                    <option value="name_asc" <?php echo($sort === 'name_asc') ? 'selected' : ''; ?>>Urutan: Name (A-Z)</option>
                                    <option value="poin_desc" <?php echo($sort === 'poin_desc') ? 'selected' : ''; ?>>Urutan: Poin Tertinggi</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th></th>
                                <th>NIS</th>
                                <th>Nama Siswa</th>
                                <th>Kelas</th>
                                <th>Total Poin</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; color: #94a3b8;">
                                            <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 16px;"></i>
                                            <p style="margin: 0; font-weight: 500;">Tidak ada data siswa</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php
else: ?>
                                <?php
    while ($row = mysqli_fetch_assoc($result)):
        $poin = (int)$row['total_poin'];
        $poin_bg = "";
        $poin_color = "";
        if ($poin == 0) {
            $poin_bg = "#d1fae5";
            $poin_color = "#059669"; // Green
        }
        elseif ($poin < 26) {
            $poin_bg = "#fef08a";
            $poin_color = "#a16207"; // Yellow
        }
        elseif ($poin >= 26 && $poin <= 50) {
            $poin_bg = "#ffedd5";
            $poin_color = "#c2410c"; // Orange
        }
        elseif ($poin >= 51 && $poin < 100) {
            $poin_bg = "#fee2e2";
            $poin_color = "#b91c1c"; // Red
        }
        else {
            $poin_bg = "#000000";
            $poin_color = "#ffffff"; // Black
        }
        $poin_html = '<span class="badge" style="background: ' . $poin_bg . '; color: ' . $poin_color . ';">' . $poin . '</span>';

        $nama_kelas = isset($row['tingkat']) ? ($row['tingkat'] . ' ' . $row['program_keahlian'] . ' ' . $row['rombel']) : '-';
        $sts = strtolower(trim($row['status']));

        switch ($sts) {
            case 'aktif':
                $status_html = '<span class="badge badge-green">Aktif</span>';
                break;
            case 'lulus':
                $status_html = '<span class="badge" style="background: #FEF3C7; color: #D97706;">Lulus</span>';
                break;
            case 'pindah':
                $status_html = '<span class="badge" style="background: #F3E8FF; color: #7E22CE;">Pindah</span>';
                break;
            case 'non-aktif':
            default:
                $status_html = '<span class="badge badge-red">Non-Aktif</span>';
                break;
        }

        $modal_tingkat = htmlspecialchars($row['tingkat'] ?? '-');
        $modal_jurusan = htmlspecialchars($row['program_keahlian'] ?? '-');
        $modal_rombel = htmlspecialchars(isset($row['rombel']) ? "Rombel " . $row['rombel'] : '-');
        $modal_jk = htmlspecialchars($row['jenis_kelamin'] ?? '-');
        $modal_alamat = htmlspecialchars($row['alamat'] ?? '-');
        $modal_ortu_nama = htmlspecialchars($row['nama_ortu'] ?? 'Tidak tersedia');
        $modal_ortu_hub = htmlspecialchars($row['status_hubungan'] ?? '-');
        $modal_ortu_pkj = htmlspecialchars($row['pekerjaan_ortu'] ?? '-');
        $modal_ortu_telp = htmlspecialchars($row['telp_ortu'] ?? '-');
        $foto_path = !empty($row['foto']) ? "../../uploads/siswa/" . htmlspecialchars($row['foto']) : "";
        $ta_display = $row['tahun_ajaran_siswa'] ?? $tahun_ajaran_aktif;

        $detail_payload = htmlspecialchars(json_encode([
            'nama' => $row['nama_siswa'],
            'nis' => $row['nis'],
            'foto' => $foto_path,
            'status_html' => $status_html,
            'jk' => $modal_jk,
            'alamat' => $modal_alamat,
            'tingkat' => $modal_tingkat,
            'jurusan' => $modal_jurusan,
            'rombel' => $modal_rombel,
            'ta' => $ta_display,
            'ortu_nama' => $modal_ortu_nama,
            'ortu_hub' => $modal_ortu_hub,
            'ortu_pkj' => $modal_ortu_pkj,
            'ortu_telp' => $modal_ortu_telp
        ]), ENT_QUOTES, 'UTF-8');
?>
                                    <tr>
                                        <td>
                                            <div class="profile-icon">
                                                <?php if ($foto_path): ?>
                                                    <img src="<?php echo $foto_path; ?>" alt="Foto profil <?php echo htmlspecialchars($row['nama_siswa']); ?>">
                                                <?php
        else: ?>
                                                    <i class="fas fa-user-circle"></i>
                                                <?php
        endif; ?>
                                            </div>
                                        </td>
                                        <td><code><?php echo htmlspecialchars($row['nis']); ?></code></td>
                                        <td style="font-weight:600; color:var(--navy-700)"><?php echo htmlspecialchars($row['nama_siswa']); ?></td>
                                        <td><?php echo htmlspecialchars($nama_kelas); ?></td>
                                        <td style="padding-left: 50px;"> 
                                            <?php echo $poin_html; ?>
                                        </td>
                                        <td>
                                            <?php echo $status_html; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button type="button" class="action-btn info-btn" data-payload="<?php echo $detail_payload; ?>" onclick="showDetailModal(this)" title="Info Siswa">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <a href="edit.php?nis=<?php echo htmlspecialchars($row['nis']); ?>" class="action-btn edit-btn" title="Edit Siswa">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete('<?php echo htmlspecialchars(addslashes($row['nis'])); ?>', '<?php echo htmlspecialchars(addslashes($row['nama_siswa'])); ?>')" class="action-btn del-btn" title="Hapus Siswa">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php
    endwhile; ?>
                            <?php
endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php
$params = "&q=" . urlencode($search) . "&sort=" . urlencode($sort) . "&tingkat=" . urlencode($filter_tingkat) . "&jurusan=" . urlencode($filter_jurusan) . "&ta=" . urlencode($filter_ta);
if ($total_pages > 1): ?>
                    <div style="padding:1rem">
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?><?php echo $params; ?>" class="page-link">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            <?php
    endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?><?php echo $params; ?>" class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php
    endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?><?php echo $params; ?>" class="page-link">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php
    endif; ?>
                        </div>
                    </div>
                <?php
endif; ?>
            </div>
        </div>
    </div>
    
    <div class="detail-modal-overlay" id="detailModal">
        <div class="detail-modal">
            <div class="detail-modal-header">
                <h3><i class="fas fa-address-card"></i> Detail Informasi Siswa</h3>
                <button class="close-btn" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="detail-modal-body">
                <div class="modal-col-left">
                    <div class="profile-card">
                        <div class="avatar-container" id="modal-avatar-container">
                            <i class="fas fa-user-circle avatar-icon"></i>
                        </div>
                        <h4 class="student-name" id="modal-nama">-</h4>
                        <div class="student-nis" id="modal-nis">-</div>
                        <div id="modal-status-badge"></div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="fas fa-user"></i> Informasi Pribadi
                        </div>
                        <div class="info-grid personal-grid">
                            <div class="info-item">
                                <span class="info-label">Jenis Kelamin</span>
                                <span class="info-value" id="modal-jk">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Alamat Lengkap</span>
                                <span class="info-value" id="modal-alamat">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-col-right">
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="fas fa-graduation-cap"></i> Data Akademik
                        </div>
                        <div class="info-grid academic-grid">
                            <div class="info-item">
                                <span class="info-label">Tingkat</span>
                                <span class="info-value" id="modal-tingkat">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Tahun Ajaran</span>
                                <span class="info-value" id="modal-ta">-</span>
                            </div>
                            <div class="info-item" style="grid-column: span 2;">
                                <span class="info-label">Program Keahlian (Jurusan)</span>
                                <span class="info-value" id="modal-jurusan">-</span>
                            </div>
                            <div class="info-item" style="grid-column: span 2;">
                                <span class="info-label">Kelas (Rombel)</span>
                                <span class="info-value" id="modal-rombel">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="fas fa-users"></i> Data Orang Tua / Wali
                        </div>
                        <div class="info-grid parent-grid">
                            <div class="info-item" style="grid-column: span 2;">
                                <span class="info-label">Nama Lengkap</span>
                                <span class="info-value" id="modal-ortu-nama">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status Hubungan</span>
                                <span class="info-value" id="modal-ortu-hub">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. Telepon</span>
                                <span class="info-value" id="modal-ortu-telp">-</span>
                            </div>
                            <div class="info-item" style="grid-column: span 2;">
                                <span class="info-label">Pekerjaan</span>
                                <span class="info-value" id="modal-ortu-pkj">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detail-modal-footer">
                <button class="btn btn-secondary" onclick="closeDetailModal()">Tutup</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal" style="display:none">
        <div class="modal">
            <h3><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i> Konfirmasi Hapus</h3>
            <p>Hapus siswa <strong id="deleteNama"></strong>?</p>
            <div class="modal-actions">
                <button onclick="closeModal()" class="btn btn-secondary">Batal</button>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="submit" class="btn" style="background-color: #ef4444; color: white;"><i class="fas fa-trash"></i> Hapus</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function confirmDelete(id, nama) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteNama').textContent = nama;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function showDetailModal(buttonElement) {
            try {
                const data = JSON.parse(buttonElement.getAttribute('data-payload'));
                
                const avatarContainer = document.getElementById('modal-avatar-container');
                if (data.foto) {
                    avatarContainer.innerHTML = `<img src="${data.foto}" alt="Foto Profil">`;
                } else {
                    avatarContainer.innerHTML = `<i class="fas fa-user-circle avatar-icon"></i>`;
                }

                document.getElementById('modal-nama').textContent = data.nama;
                document.getElementById('modal-nis').textContent = data.nis;
                document.getElementById('modal-status-badge').innerHTML = data.status_html;
                
                document.getElementById('modal-jk').textContent = data.jk;
                document.getElementById('modal-alamat').textContent = data.alamat;

                document.getElementById('modal-tingkat').textContent = data.tingkat;
                document.getElementById('modal-jurusan').textContent = data.jurusan;
                document.getElementById('modal-rombel').textContent = data.rombel;
                document.getElementById('modal-ta').textContent = data.ta;

                document.getElementById('modal-ortu-nama').textContent = data.ortu_nama;
                document.getElementById('modal-ortu-hub').textContent = data.ortu_hub;
                document.getElementById('modal-ortu-pkj').textContent = data.ortu_pkj;
                document.getElementById('modal-ortu-telp').textContent = data.ortu_telp;
                
                document.getElementById('detailModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            } catch (e) {
                console.error("Error parsing detail data payload", e);
                alert("Gagal memuat detail siswa.");
            }
        }

        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function openFilterModal() {
            document.getElementById('filterModal').style.display = 'flex';
        }

        function closeFilterModal() {
            document.getElementById('filterModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var detailModal = document.getElementById('detailModal');
            if (event.target == detailModal) {
                closeDetailModal();
            }
            
            var deleteModal = document.getElementById('deleteModal');
            if (event.target == deleteModal) {
                closeModal();
            }

            var filterModal = document.getElementById('filterModal');
            if (event.target == filterModal) {
                closeFilterModal();
            }
        }
    </script>
</body>
</html>