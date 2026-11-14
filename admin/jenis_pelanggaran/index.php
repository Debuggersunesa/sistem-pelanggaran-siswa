<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$msg = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'tambah_sukses') {
    $msg = 'Jenis Pelanggaran berhasil ditambahkan.';
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = $_POST['id'] ?? '';
        $id_clean = mysqli_real_escape_string($conn, $id);

        $query_cek = "SELECT COUNT(*) FROM pelanggaran_siswa WHERE id_jenis_pelanggaran = '$id_clean'";
        $res_cek = mysqli_query($conn, $query_cek);

        if (!$res_cek) {
            $error = "Terjadi kesalahan sistem saat mengecek data pelanggaran siswa terkait.";
        }
        else {
            $cek = mysqli_fetch_row($res_cek)[0];

            if ($cek > 0) {
                $error = 'Data Jenis Pelanggaran tidak bisa dihapus karena sudah digunakan pada data pelanggaran siswa.';
            }
            else {
                mysqli_begin_transaction($conn);
                try {
                    $stmt_del = mysqli_prepare($conn, "DELETE FROM jenis_pelanggaran WHERE id_jenis_pelanggaran = ?");
                    mysqli_stmt_bind_param($stmt_del, 'i', $id);

                    if (!mysqli_stmt_execute($stmt_del)) {
                        throw new Exception("Gagal menghapus data dari database.");
                    }

                    mysqli_stmt_close($stmt_del);
                    mysqli_commit($conn);
                    $msg = 'Jenis Pelanggaran berhasil dihapus.';
                }
                catch (Exception $e) {
                    mysqli_rollback($conn);
                    $error = $e->getMessage();
                }
            }
        }
    }
}

$search = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(jenis LIKE '%$s%')";
}

$where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query_total = "SELECT COUNT(*) FROM jenis_pelanggaran $where";
$res_total = mysqli_query($conn, $query_total);

if (!$res_total) {
    die("Query Error: " . mysqli_error($conn));
}

$total = mysqli_fetch_row($res_total)[0] ?? 0;
$total_pages = max(1, ceil($total / $limit));

$sort = $_GET['sort'] ?? 'jenis_asc';
$order_sql = "ORDER BY jenis ASC";

if ($sort === 'jenis_asc') {
    $order_sql = "ORDER BY jenis ASC";
}
elseif ($sort === 'jenis_desc') {
    $order_sql = "ORDER BY jenis DESC";
}
elseif ($sort === 'poin_asc') {
    $order_sql = "ORDER BY poin ASC";
}
elseif ($sort === 'poin_desc') {
    $order_sql = "ORDER BY poin DESC";
}

$query_data = "
    SELECT 
        id_jenis_pelanggaran, 
        jenis, 
        poin 
    FROM jenis_pelanggaran
    $where 
    $order_sql 
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query_data);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jenis Pelanggaran - SISP</title>
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
            background: #FEE2E2;
            color: #DC2626;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1><i class="fa-solid fa-list-ul" style="color:var(--navy-500)"></i> Data Jenis Pelanggaran</h1>
                <span class="breadcrumb">Admin / Jenis Pelanggaran</span>
            </div>
            <div class="topbar-right">
                <a href="tambah.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Jenis Pelanggaran</a>
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
                    <h3><i class="fa-solid fa-table-list"></i> Daftar Jenis Pelanggaran (<?php echo $total; ?>)</h3>
                    
                    <form method="GET" class="header-controls" style="flex-wrap: wrap; gap: 10px;">
                        <div class="search-box" style="flex-grow: 1; min-width: 200px;">
                            <input type="text" name="q" placeholder="Cari Jenis Pelanggaran..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                        </div>

                        <div class="filter-box">
                            <div class="select-wrapper">
                                <select name="sort" onchange="this.form.submit()" style="padding-right: 30px;">
                                    <option value="jenis_asc" <?php echo($sort === 'jenis_asc') ? 'selected' : ''; ?>>Urutan: Jenis (A-Z)</option>
                                    <option value="jenis_desc" <?php echo($sort === 'jenis_desc') ? 'selected' : ''; ?>>Urutan: Jenis (Z-A)</option>
                                    <option value="poin_asc" <?php echo($sort === 'poin_asc') ? 'selected' : ''; ?>>Urutan: Poin (Terendah)</option>
                                    <option value="poin_desc" <?php echo($sort === 'poin_desc') ? 'selected' : ''; ?>>Urutan: Poin (Tertinggi)</option>
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
                                <th width="5%">No</th>
                                <th>Jenis Pelanggaran</th>
                                <th width="15%">Poin</th>
                                <th width="15%">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; color: #94a3b8;">
                                            <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 16px;"></i>
                                            <p style="margin: 0; font-weight: 500;">Tidak ada data jenis pelanggaran</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php
else: ?>
                                <?php
    $no = $offset + 1;
    while ($row = mysqli_fetch_assoc($result)):
        $id = $row['id_jenis_pelanggaran'];
        $jenis = htmlspecialchars($row['jenis']);
        $poin = (int)$row['poin'];
?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td style="font-weight:500; color:var(--navy-800)"><?php echo $jenis; ?></td>
                                        <td>
                                            <span class="badge-poin">
                                                <i class="fas fa-star" style="font-size:0.75rem; margin-right:4px;"></i> <?php echo $poin; ?> Poin
                                            </span>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="edit.php?id=<?php echo urlencode($id); ?>" class="action-btn edit-btn" title="Edit Jenis Pelanggaran">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete('<?php echo $id; ?>', '<?php echo addslashes($jenis); ?>')" class="action-btn del-btn" title="Hapus Jenis Pelanggaran">
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
$params = "&q=" . urlencode($search) . "&sort=" . urlencode($sort);
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
            <p>Yakin ingin menghapus jenis pelanggaran <strong id="deleteNama"></strong>?</p>
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
    
    <script>
        function confirmDelete(id, nama) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteNama').textContent = nama;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var deleteModal = document.getElementById('deleteModal');
            if (event.target == deleteModal) {
                closeModal();
            }
        }
    </script>
</body>
</html>
