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

        // Cek dulu apakah ada siswa di kelas ini
        $query_cek = "SELECT COUNT(*) FROM siswa WHERE id_kelas = $id";
        $res_cek = mysqli_query($conn, $query_cek);

        if (!$res_cek) {
            $error = "Terjadi kesalahan sistem saat mengecek relasi kelas.";
        }
        else {
            $cek = mysqli_fetch_row($res_cek)[0];

            if ($cek > 0) {
                $error = 'Data Kelas tidak bisa dihapus karena masih ada ' . $cek . ' siswa yang terdaftar di kelas tersebut.';
            }
            else {
                mysqli_begin_transaction($conn);
                try {
                    $stmt_del = mysqli_prepare($conn, "DELETE FROM kelas WHERE id_kelas = ?");
                    mysqli_stmt_bind_param($stmt_del, 'i', $id);

                    if (!mysqli_stmt_execute($stmt_del)) {
                        throw new Exception("Gagal menghapus data dari database.");
                    }

                    mysqli_stmt_close($stmt_del);
                    mysqli_commit($conn);
                // $msg = "Data Kelas berhasil dihapus.";
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
    $conditions[] = "(t.tingkat LIKE '%$s%' OR pk.program_keahlian LIKE '%$s%' OR g.nama_pengguna LIKE '%$s%' OR k.rombel LIKE '%$s%')";
}

$where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query_total = "
    SELECT COUNT(*) 
    FROM kelas k
    LEFT JOIN tingkat t ON k.id_tingkat = t.id_tingkat
    LEFT JOIN program_keahlian pk ON k.id_program_keahlian = pk.id_program_keahlian
    LEFT JOIN guru g ON k.kode_guru = g.kode_guru
    $where
";
$res_total = mysqli_query($conn, $query_total);

if (!$res_total) {
    die("Query Error: " . mysqli_error($conn));
}

$total = mysqli_fetch_row($res_total)[0] ?? 0;
$total_pages = max(1, ceil($total / $limit));

$sort = $_GET['sort'] ?? 'tingkat_asc';
$order_sql = "ORDER BY t.tingkat ASC, pk.program_keahlian ASC, k.rombel ASC";

if ($sort === 'tingkat_desc') {
    $order_sql = "ORDER BY t.tingkat DESC, pk.program_keahlian ASC, k.rombel ASC";
}
elseif ($sort === 'jurusan_asc') {
    $order_sql = "ORDER BY pk.program_keahlian ASC, t.tingkat ASC, k.rombel ASC";
}


$query_kelas = "
    SELECT 
        k.id_kelas, 
        t.tingkat, 
        pk.program_keahlian, 
        k.rombel,
        g.nama_pengguna AS wali_kelas,
        (SELECT COUNT(*) FROM siswa s WHERE s.id_kelas = k.id_kelas AND s.status = 'aktif') AS jumlah_siswa
    FROM kelas k
    LEFT JOIN tingkat t ON k.id_tingkat = t.id_tingkat
    LEFT JOIN program_keahlian pk ON k.id_program_keahlian = pk.id_program_keahlian
    LEFT JOIN guru g ON k.kode_guru = g.kode_guru
    $where 
    $order_sql 
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query_kelas);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Kelas - SISP</title>
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
            background: white;
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
            background-color: #f8fafc;
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
        
        .badge-kelas {
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-block;
        }
        .badge-rpl { background: #e0f2fe; color: #0284c7; border: 1px solid #bae6fd; } /* Biru */
        .badge-dkv { background: #f3e8ff; color: #7e22ce; border: 1px solid #d8b4fe; } /* Ungu */
        .badge-tkj { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; } /* Hijau */
        .badge-an { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; } /* Merah */
        .badge-bd { background: #fef9c3; color: #a16207; border: 1px solid #fef08a; } /* Kuning */
        .badge-default { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; } /* Default */
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1><i class="fa-solid fa-school" style="color:var(--navy-500)"></i> Data Kelas</h1>
                <span class="breadcrumb">Admin / Data Kelas</span>
            </div>
            <div class="topbar-right">
                <a href="tambah.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Kelas</a>
            </div>
        </div>
        
        <div class="page-content">
            <?php if (isset($_GET['success']) && $_GET['success'] === 'tambah'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Data Kelas berhasil ditambahkan.</div>
            <?php
endif; ?>
            <?php if (isset($_GET['success']) && $_GET['success'] === 'edit'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Data Kelas berhasil diperbarui.</div>
            <?php
endif; ?>
            
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
                    <h3><i class="fa-solid fa-list-ul"></i> Daftar Kelas (<?php echo $total; ?>)</h3>
                    
                    <form method="GET" class="header-controls">
                        <div class="search-box">
                            <input type="text" name="q" placeholder="Cari Tingkat, Jurusan, Wali Kelas..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                        </div>
                        
                        <div class="filter-box">
                            <div class="select-wrapper">
                                <select name="sort" onchange="this.form.submit()" style="padding-right: 30px;">
                                    <option value="tingkat_asc" <?php echo($sort === 'tingkat_asc') ? 'selected' : ''; ?>>Urutan: Tingkat Terendah</option>
                                    <option value="tingkat_desc" <?php echo($sort === 'tingkat_desc') ? 'selected' : ''; ?>>Urutan: Tingkat Tertinggi</option>
                                    <option value="jurusan_asc" <?php echo($sort === 'jurusan_asc') ? 'selected' : ''; ?>>Urutan: Jurusan (A-Z)</option>
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
                                <th style="width:50px">No</th>
                                <th>Nama Kelas</th>
                                <th>Wali Kelas</th>
                                <th>Jumlah Siswa</th>
                                <th style="width:100px">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; color: #94a3b8;">
                                            <i class="fas fa-school" style="font-size: 3rem; margin-bottom: 16px;"></i>
                                            <p style="margin: 0; font-weight: 500;">Tidak ada data kelas</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php
else: ?>
                                <?php
    $no = $offset + 1;
    while ($row = mysqli_fetch_assoc($result)):
        $id = $row['id_kelas'];
        $nama_kelas = htmlspecialchars($row['tingkat'] . ' ' . $row['program_keahlian'] . ' ' . $row['rombel']);
        
        $pk = strtoupper(trim($row['program_keahlian']));
        $badge_class = 'badge-default';
        if (strpos($pk, 'RPL') !== false) $badge_class = 'badge-rpl';
        elseif (strpos($pk, 'DKV') !== false) $badge_class = 'badge-dkv';
        elseif (strpos($pk, 'TKJ') !== false) $badge_class = 'badge-tkj';
        elseif (strpos($pk, 'AN') !== false) $badge_class = 'badge-an';
        elseif (strpos($pk, 'BD') !== false) $badge_class = 'badge-bd';
        
        $wali_kelas = htmlspecialchars($row['wali_kelas'] ?? 'Belum ada Wali Kelas');
        $jumlah_siswa = $row['jumlah_siswa'];
?>
                                    <tr>
                                        <td><?php echo $no++; ?></td>
                                        <td>
                                            <span class="badge-kelas <?php echo $badge_class; ?>"><?php echo $nama_kelas; ?></span>
                                        </td>
                                        <td>
                                            <?php if (empty($row['wali_kelas'])): ?>
                                                <em style="color:#94a3b8">Belum di assign</em>
                                            <?php
        else: ?>
                                                <i class="fas fa-user-tie" style="color:#64748b; margin-right:4px;"></i> <?php echo $wali_kelas; ?>
                                            <?php
        endif; ?>
                                        </td>
                                        <td>
                                            <div style="display:flex; align-items:center;">
                                                
                                                <strong><?php echo $jumlah_siswa; ?></strong> 
                                                <i class="fas fa-users" style="color:#94a3b8; font-size:12px; margin-left:8px;"></i> 
                                                <!-- <span style="font-size:12px; color:#64748b; margin-left:4px;">siswa</span> -->
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <a href="edit.php?id=<?php echo $id; ?>" class="action-btn edit-btn" title="Edit Kelas">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete(<?php echo $id; ?>, '<?php echo addslashes($nama_kelas); ?>')" class="action-btn del-btn" title="Hapus Kelas">
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
            <p>Hapus kelas <strong id="deleteNama"></strong>?</p>
            <p style="font-size:12px; color:#64748b; margin-top:4px;">Penghapusan hanya bisa dilakukan jika tidak ada siswa yang terdaftar di kelas ini.</p>
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
