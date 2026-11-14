<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$msg = '';
$error = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'tambah_sukses') {
    $msg = 'Entry Pelanggaran Siswa berhasil ditambahkan.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = $_POST['id'] ?? '';
        $id_clean = mysqli_real_escape_string($conn, $id);

        mysqli_begin_transaction($conn);
        try {
            // Check dependencies on treaties
            $query_cek = "SELECT COUNT(*) FROM perjanjian_siswa WHERE id_pelanggaran_siswa = '$id_clean'";
            $res_cek = mysqli_query($conn, $query_cek);
            if (!$res_cek)
                throw new Exception("Error database saat mengecek relasi.");
            if (mysqli_fetch_row($res_cek)[0] > 0)
                throw new Exception("Data tidak bisa dihapus karena terhubung dengan Surat Perjanjian Siswa.");

            $query_cek2 = "SELECT COUNT(*) FROM perjanjian_ortu WHERE id_pelanggaran_siswa = '$id_clean'";
            $res_cek2 = mysqli_query($conn, $query_cek2);
            if (!$res_cek2)
                throw new Exception("Error database saat mengecek relasi.");
            if (mysqli_fetch_row($res_cek2)[0] > 0)
                throw new Exception("Data tidak bisa dihapus karena terhubung dengan Surat Perjanjian Orang Tua.");


            $stmt_del = mysqli_prepare($conn, "DELETE FROM pelanggaran_siswa WHERE id_pelanggaran_siswa = ?");
            mysqli_stmt_bind_param($stmt_del, 'i', $id);

            if (!mysqli_stmt_execute($stmt_del)) {
                throw new Exception("Gagal menghapus data dari database.");
            }

            mysqli_stmt_close($stmt_del);
            mysqli_commit($conn);
            $msg = 'Data Pelanggaran berhasil dihapus.';
        }
        catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

// Filters & Pagination
$search = trim($_GET['q'] ?? '');
$filter_ta = $_GET['ta'] ?? '';
$filter_jenis = $_GET['jenis'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];

// Base Join Query to ensure Data Integrity
$from_clause = "FROM pelanggaran_siswa ps 
                JOIN siswa s ON ps.nis = s.nis 
                JOIN jenis_pelanggaran jp ON ps.id_jenis_pelanggaran = jp.id_jenis_pelanggaran
                JOIN tahun_ajaran ta ON ps.id_tahun_ajaran = ta.id_tahun_ajaran";

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(s.nama_siswa LIKE '%$s%' OR s.nis LIKE '%$s%' OR jp.jenis LIKE '%$s%')";
}

if ($filter_ta) {
    $ta_clean = mysqli_real_escape_string($conn, $filter_ta);
    $conditions[] = "ps.id_tahun_ajaran = '$ta_clean'";
}

if ($filter_jenis) {
    $j_clean = mysqli_real_escape_string($conn, $filter_jenis);
    $conditions[] = "ps.id_jenis_pelanggaran = '$j_clean'";
}

$where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query_total = "SELECT COUNT(*) $from_clause $where";
$res_total = mysqli_query($conn, $query_total);

if (!$res_total) {
    die("Query Error: " . mysqli_error($conn));
}

$total = mysqli_fetch_row($res_total)[0] ?? 0;
$total_pages = max(1, ceil($total / $limit));

$sort = $_GET['sort'] ?? 'tanggal_desc';
$order_sql = "ORDER BY ps.tanggal DESC";

if ($sort === 'tanggal_asc')
    $order_sql = "ORDER BY ps.tanggal ASC";
elseif ($sort === 'tanggal_desc')
    $order_sql = "ORDER BY ps.tanggal DESC";
elseif ($sort === 'nama_asc')
    $order_sql = "ORDER BY s.nama_siswa ASC";
elseif ($sort === 'poin_desc')
    $order_sql = "ORDER BY jp.poin DESC";

$query_data = "
    SELECT 
        ps.id_pelanggaran_siswa, 
        ps.tanggal, 
        ps.nis, 
        s.nama_siswa,
        s.foto,
        jp.jenis, 
        jp.poin,
        ta.tahun,
        ps.keterangan
    $from_clause
    $where 
    $order_sql 
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query_data);
if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}

// Fetch Filter Options
$ta_options = [];
$res_ta = mysqli_query($conn, "SELECT id_tahun_ajaran, tahun FROM tahun_ajaran ORDER BY tahun DESC");
if ($res_ta) {
    while ($row = mysqli_fetch_assoc($res_ta))
        $ta_options[] = $row;
}

$jenis_options = [];
$res_jp = mysqli_query($conn, "SELECT id_jenis_pelanggaran, jenis FROM jenis_pelanggaran ORDER BY jenis ASC");
if ($res_jp) {
    while ($row = mysqli_fetch_assoc($res_jp))
        $jenis_options[] = $row;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pelanggaran - SISP</title>
    <link rel="stylesheet" href="../../css/style.css?v=<?php echo filemtime('../../css/style.css'); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .table-wrapper {
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            background: white;
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
            text-decoration: none;
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
        .action-btn.info-btn:hover {
            background-color: #3f72af;
            color: white;
            box-shadow: 0 4px 6px -1px rgba(63, 114, 175, 0.3);
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
        .badge-poin {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
        }
        .badge-poin.high {
            background: #fee2e2;
            color: #b91c1c;
        }
        .badge-poin.medium {
            background: #fef3c7;
            color: #b45309;
        }
        .badge-poin.low {
            background: #FEE2E2;
            color: #DC2626;
        }
        .student-info {
            display:flex;
            align-items: center;
            gap:6px;
        }
        .student-info strong {
            color: var(--navy-800);
            font-weight: 600;
        }
        .student-info span {
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1><i class="fa-solid fa-file-signature" style="color:var(--navy-500)"></i> Data Pelanggaran Siswa</h1>
                <span class="breadcrumb">Admin / Pelanggaran Siswa</span>
            </div>
            <div class="topbar-right">
                <a href="tambah.php" class="btn btn-primary"><i class="fas fa-plus"></i> Entry Pelanggaran</a>
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
                    <h3><i class="fa-solid fa-table-list"></i> Riwayat Pelanggaran (<?php echo $total; ?>)</h3>
                    
                    <form method="GET" class="header-controls" style="flex-wrap: wrap; gap: 10px;">
                        <div class="search-box" style="flex-grow: 1; min-width: 200px;">
                            <input type="text" name="q" placeholder="Cari Siswa atau Pelanggaran..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                        </div>

                        <button type="button" onclick="openFilterModal()" style="display: flex; align-items: center; justify-content: center; gap: 8px; background-color: white; border: 1px solid #cbd5e1; border-radius: 20px; padding: 8px 16px; color: var(--color-accent); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-filter" style="color: var(--color-accent);"></i> Filter 
                            <?php if ($filter_ta || $filter_jenis): ?>
                                <span style="background: var(--navy-500); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; margin-left: 4px;">!</span>
                            <?php
endif; ?>
                        </button>

                        <div class="modal-overlay" id="filterModal" style="display:none;">
                            <div class="modal" style="max-width: 500px;">
                                <h3><i class="fas fa-filter" style="color:var(--navy-500)"></i> Filter Data</h3>
                                
                                <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem; margin-bottom: 1.5rem; text-align: left;">
                                    <div>
                                        <label style="display:block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; color: var(--navy-700);">Tahun Ajaran</label>
                                        <select name="ta" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                                            <option value="">Semua Tahun Ajaran</option>
                                            <?php foreach ($ta_options as $t): ?>
                                                <option value="<?php echo $t['id_tahun_ajaran']; ?>" <?php echo $filter_ta == $t['id_tahun_ajaran'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($t['tahun']); ?>
                                                </option>
                                            <?php
endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; color: var(--navy-700);">Jenis Pelanggaran</label>
                                        <select name="jenis" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                                            <option value="">Semua Jenis</option>
                                            <?php foreach ($jenis_options as $j): ?>
                                                <option value="<?php echo $j['id_jenis_pelanggaran']; ?>" <?php echo $filter_jenis == $j['id_jenis_pelanggaran'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($j['jenis']); ?>
                                                </option>
                                            <?php
endforeach; ?>
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
                                    <option value="tanggal_desc" <?php echo($sort === 'tanggal_desc') ? 'selected' : ''; ?>>Urutan: Tanggal (Terbaru)</option>
                                    <option value="tanggal_asc" <?php echo($sort === 'tanggal_asc') ? 'selected' : ''; ?>>Urutan: Tanggal (Terlama)</option>
                                    <option value="nama_asc" <?php echo($sort === 'nama_asc') ? 'selected' : ''; ?>>Urutan: Nama Siswa (A-Z)</option>
                                    <option value="poin_desc" <?php echo($sort === 'poin_desc') ? 'selected' : ''; ?>>Urutan: Poin Terbesar</option>
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
                                <th>NIS</th>
                                <th>Data Siswa</th>
                                <th>Pelanggaran</th>
                                <th>Poin</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; color: #94a3b8;">
                                            <i class="fas fa-file-signature" style="font-size: 3rem; margin-bottom: 16px;"></i>
                                            <p style="margin: 0; font-weight: 500;">Tidak ada riwayat pelanggaran</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php
else: ?>
                                <?php
    while ($row = mysqli_fetch_assoc($result)):
        $id = $row['id_pelanggaran_siswa'];

        // Format Tanggal
        $date = new DateTime($row['tanggal']);
        $tgl_format = $date->format('d M Y, H:i');

        $nis = htmlspecialchars($row['nis']);
        $nama_siswa = htmlspecialchars($row['nama_siswa']);
        $jenis = htmlspecialchars($row['jenis']);
        $poin = (int)$row['poin'];
        $keterangan = htmlspecialchars($row['keterangan'] ?? '-');

        $foto_path = (!empty($row['foto']) && file_exists("../../uploads/siswa/" . $row['foto']))
            ? "../../uploads/siswa/" . htmlspecialchars($row['foto'])
            : "https://ui-avatars.com/api/?name=" . urlencode($nama_siswa) . "&background=e2e8f0&color=475569";

        $admin_input = "Administrator";

        $badge_class = 'low';
        if ($poin >= 50)
            $badge_class = 'high';
        elseif ($poin >= 20)
            $badge_class = 'medium';
?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($nis); ?></code></td>
                                        <td>
                                            <div class="student-info">
                                                <strong style="color:var(--navy-700)"><?php echo $nama_siswa; ?></strong>
                                            </div>
                                        </td>
                                        <td style="font-weight:500; color:var(--navy-800); max-width:250px; line-height:1.4;">
                                            <?php echo $jenis; ?>
                                        </td>
                                        <td>
                                            <span class="badge-poin <?php echo $badge_class; ?>">
                                                <i class="fas fa-star" style="font-size:0.75rem; margin-right:4px;"></i> <?php echo $poin; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button type="button" onclick="openInfoModal({
                                                    nama: '<?php echo addslashes($nama_siswa); ?>',
                                                    nis: '<?php echo addslashes($nis); ?>',
                                                    jenis: '<?php echo addslashes($jenis); ?>',
                                                    poin: '<?php echo $poin; ?>',
                                                    tgl: '<?php echo addslashes($tgl_format); ?>',
                                                    ket: '<?php echo addslashes(str_replace(array("\r", "\n"), array('\r', '\n'), $keterangan)); ?>',
                                                    foto: '<?php echo addslashes($foto_path); ?>',
                                                    admin: '<?php echo addslashes($admin_input); ?>'
                                                })" class="action-btn info-btn" title="Info Lebih Lanjut">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <a href="edit.php?id=<?php echo urlencode($id); ?>" class="action-btn edit-btn" title="Edit Data">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete('<?php echo $id; ?>', '<?php echo addslashes($nama_siswa . ' - ' . $jenis); ?>')" class="action-btn del-btn" title="Hapus Data">
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
$params = "&q=" . urlencode($search) . "&sort=" . urlencode($sort) . "&ta=" . urlencode($filter_ta) . "&jenis=" . urlencode($filter_jenis);
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

    <div class="modal-overlay" id="deleteModal" style="display:none">
        <div class="modal">
            <h3><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i> Konfirmasi Hapus</h3>
            <p>Yakin ingin menghapus data pelanggaran: <br><strong id="deleteNama"></strong>?</p>
            <p style="font-size: 0.85rem; color: #64748b;">Tindakan ini tidak dapat dibatalkan.</p>
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
    
    <!-- Info Modal -->
    <div class="modal-overlay" id="infoModal" style="display:none">
        <div class="modal" style="max-width: 450px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:12px; margin-bottom:16px;">
                <h3 style="margin:0; font-size: 1.1rem;"><i class="fas fa-info-circle" style="color:var(--navy-500)"></i> Detail Pelanggaran</h3>
                <button type="button" onclick="closeInfoModal()" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:#94a3b8;"><i class="fas fa-times"></i></button>
            </div>
            <div style="display:flex; gap:16px; margin-bottom:20px;">
                <img id="infoFoto" src="" alt="Foto Profil" style="width:70px; height:70px; border-radius:50%; object-fit:cover; border:2px solid #e2e8f0;">
                <div style="display:flex; flex-direction:column; justify-content:center;">
                    <strong id="infoNama" style="font-size:1.1rem; color:var(--navy-800);"></strong>
                    <span style="color:#64748b; font-size:0.9rem;">NIS: <span id="infoNis"></span></span>
                </div>
            </div>
            
            <div style="background:#f8fafc; padding:12px 16px; border-radius:8px; margin-bottom:16px; border:1px solid #e2e8f0;">
                <div style="margin-bottom:8px;">
                    <span style="display:block; font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase;">Pelanggaran</span>
                    <strong id="infoJenis" style="color:var(--navy-700); font-size:1rem;"></strong>
                    <span id="infoPoinBadge" class="badge-poin" style="margin-left:8px; font-size:0.75rem;"><i class="fas fa-star" style="font-size:0.65rem;"></i> <span id="infoPoin"></span> Poin</span>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr; gap:12px; font-size:0.9rem;">
                <div style="display:flex; gap:8px;">
                    <i class="far fa-calendar-alt" style="color:#64748b; margin-top:3px; width:16px;"></i>
                    <div>
                        <span style="display:block; font-size:0.75rem; color:#64748b; font-weight:600;">Waktu Kejadian</span>
                        <span id="infoTgl" style="color:var(--color-text);"></span>
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <i class="fas fa-user-tie" style="color:#64748b; margin-top:3px; width:16px;"></i>
                    <div>
                        <span style="display:block; font-size:0.75rem; color:#64748b; font-weight:600;">Diinput Oleh</span>
                        <span id="infoAdmin" style="color:var(--color-text);"></span>
                    </div>
                </div>
                <div style="display:flex; gap:8px;">
                    <i class="fas fa-align-left" style="color:#64748b; margin-top:3px; width:16px;"></i>
                    <div>
                        <span style="display:block; font-size:0.75rem; color:#64748b; font-weight:600;">Keterangan</span>
                        <span id="infoKet" style="color:var(--color-text); line-height:1.4; word-break: break-all;"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function openInfoModal(data) {
            document.getElementById('infoNama').textContent = data.nama;
            document.getElementById('infoNis').textContent = data.nis;
            document.getElementById('infoJenis').textContent = data.jenis;
            document.getElementById('infoPoin').textContent = data.poin;
            document.getElementById('infoTgl').textContent = data.tgl;
            document.getElementById('infoKet').textContent = data.ket || '-';
            document.getElementById('infoAdmin').textContent = data.admin;
            
            // Set Badge Class for points
            const poinBadge = document.getElementById('infoPoinBadge');
            poinBadge.className = 'badge-poin';
            if (data.poin >= 50) poinBadge.classList.add('high');
            else if (data.poin >= 20) poinBadge.classList.add('medium');
            else poinBadge.classList.add('low');

            const fotoEl = document.getElementById('infoFoto');
            fotoEl.src = data.foto;

            document.getElementById('infoModal').style.display = 'flex';
        }

        function closeInfoModal() {
            document.getElementById('infoModal').style.display = 'none';
        }

        function confirmDelete(id, text) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteNama').textContent = text;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function openFilterModal() {
            document.getElementById('filterModal').style.display = 'flex';
        }

        function closeFilterModal() {
            document.getElementById('filterModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var deleteModal = document.getElementById('deleteModal');
            if (event.target == deleteModal) closeModal();
            
            var filterModal = document.getElementById('filterModal');
            if (event.target == filterModal) closeFilterModal();

            var infoModal = document.getElementById('infoModal');
            if (event.target == infoModal) closeInfoModal();
        }
    </script>
</body>
</html>
