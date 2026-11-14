<?php

require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = $_POST['id'] ?? '';

        $id_clean = mysqli_real_escape_string($conn, $id);

        $query_cek = "SELECT COUNT(*) FROM kelas WHERE kode_guru = '$id_clean'";
        $res_cek = mysqli_query($conn, $query_cek);

        if (!$res_cek) {
            $error = "Terjadi kesalahan sistem saat mengecek data kelas terkait guru ini.";
        }
        else {
            $cek = mysqli_fetch_row($res_cek)[0];

            if ($cek > 0) {
                $error = 'Data Guru tidak bisa dihapus karena masih terdaftar sebagai wali kelas.';
            }
            else {
                mysqli_begin_transaction($conn);
                try {
                    $stmt_del = mysqli_prepare($conn, "DELETE FROM guru WHERE kode_guru = ?");
                    mysqli_stmt_bind_param($stmt_del, 's', $id);

                    if (!mysqli_stmt_execute($stmt_del)) {
                        throw new Exception("Gagal menghapus data dari database.");
                    }

                    mysqli_stmt_close($stmt_del);
                    mysqli_commit($conn);
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
$filter_role = $_GET['role'] ?? '';
$filter_status = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(nama_pengguna LIKE '%$s%' OR kode_guru LIKE '%$s%' OR username LIKE '%$s%')";
}

if ($filter_role) {
    $r = mysqli_real_escape_string($conn, $filter_role);
    $conditions[] = "role = '$r'";
}

if ($filter_status) {
    $st = mysqli_real_escape_string($conn, $filter_status);
    $conditions[] = "aktif = '$st'";
}

$where = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query_total = "SELECT COUNT(*) FROM guru $where";
$res_total = mysqli_query($conn, $query_total);

if (!$res_total) {
    die("Query Error: " . mysqli_error($conn));
}

$total = mysqli_fetch_row($res_total)[0] ?? 0;
$total_pages = max(1, ceil($total / $limit));

$sort = $_GET['sort'] ?? 'name_asc';
$order_sql = "ORDER BY nama_pengguna ASC";

if ($sort === 'terbaru') {
    // Assuming kode_guru is somewhat sequential or username is created sequentially. Better order by kode_guru DESC if it's auto-generated, but it's CHAR. Let's just use kode_guru DESC
    $order_sql = "ORDER BY kode_guru DESC";
}
elseif ($sort === 'name_asc') {
    $order_sql = "ORDER BY nama_pengguna ASC";
}
elseif ($sort === 'role_asc') {
    $order_sql = "ORDER BY role ASC, nama_pengguna ASC";
}

$query_guru = "
    SELECT 
        kode_guru, 
        nama_pengguna, 
        role, 
        username,
        aktif,
        jabatan,
        telp
    FROM guru
    $where 
    $order_sql 
    LIMIT $limit OFFSET $offset
";

$result = mysqli_query($conn, $query_guru);

if (!$result) {
    die("Query Error: " . mysqli_error($conn));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru - SISP</title>
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
            max-width: 600px;
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
        .avatar-container i {
            font-size: 4rem;
        }
        .profile-card .guru-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--navy-900, #111827);
            margin: 0;
            line-height: 1.2;
        }
        .profile-card .guru-kode {
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
            grid-template-columns: repeat(2, 1fr);
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
                <h1><i class="fa-solid fa-chalkboard-user" style="color:var(--navy-500)"></i> Data Guru & Admin</h1>
                <span class="breadcrumb">Admin / Data Guru</span>
            </div>
            <div class="topbar-right">
                <a href="tambah.php" class="btn btn-primary"><i class="fas fa-plus"></i> Tambah Guru</a>
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
                    <h3><i class="fa-solid fa-table-list"></i> Daftar Guru (<?php echo $total; ?>)</h3>
                    
                    <form method="GET" class="header-controls" style="flex-wrap: wrap; gap: 10px;">
                        <div class="search-box" style="flex-grow: 1; min-width: 200px;">
                            <input type="text" name="q" placeholder="Cari Nama, Kode, Username..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                        </div>
                        
                        <button type="button" onclick="openFilterModal()" style="display: flex; align-items: center; justify-content: center; gap: 8px; background-color: white; border: 1px solid #cbd5e1; border-radius: 20px; padding: 8px 16px; color: var(--color-accent); font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s;">
                            <i class="fas fa-filter" style="color: var(--color-accent);"></i> Filter 
                            <?php if ($filter_role || $filter_status): ?>
                                <span style="background: var(--navy-500); color: white; border-radius: 50%; width: 20px; height: 20px; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; margin-left: 4px;">!</span>
                            <?php
endif; ?>
                        </button>

                        <div class="modal-overlay" id="filterModal" style="display:none;">
                            <div class="modal">
                                <h3><i class="fas fa-filter" style="color:var(--navy-500)"></i> Filter Data Guru</h3>
                                
                                <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem; margin-bottom: 1.5rem; text-align: left;">
                                    <div>
                                        <label style="display:block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; color: var(--navy-700);">Role Akses</label>
                                        <select name="role" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                                            <option value="">Semua Role</option>
                                            <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="kepsek" <?php echo $filter_role === 'kepsek' ? 'selected' : ''; ?>>Kepsek</option>
                                            <option value="guru_pelajaran" <?php echo $filter_role === 'guru_pelajaran' ? 'selected' : ''; ?>>Guru Pelajaran</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label style="display:block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; color: var(--navy-700);">Status Aktif</label>
                                        <select name="status" style="width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none;">
                                            <option value="">Semua Status</option>
                                            <option value="Y" <?php echo $filter_status === 'Y' ? 'selected' : ''; ?>>Aktif</option>
                                            <option value="N" <?php echo $filter_status === 'N' ? 'selected' : ''; ?>>Non-Aktif</option>
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
                                    <option value="name_asc" <?php echo($sort === 'name_asc') ? 'selected' : ''; ?>>Urutan: Nama (A-Z)</option>
                                    <option value="role_asc" <?php echo($sort === 'role_asc') ? 'selected' : ''; ?>>Urutan: Role</option>
                                    <option value="terbaru" <?php echo($sort === 'terbaru') ? 'selected' : ''; ?>>Urutan: Kode Terbaru</option>
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
                                <th>Kode</th>
                                <th>Nama Pengguna</th>
                                <th>Role</th>
                                <th>Jabatan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 40px 0; color: #94a3b8;">
                                            <i class="fas fa-chalkboard-user" style="font-size: 3rem; margin-bottom: 16px;"></i>
                                            <p style="margin: 0; font-weight: 500;">Tidak ada data guru</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php
else: ?>
                                <?php
    while ($row = mysqli_fetch_assoc($result)):
        $kode = htmlspecialchars($row['kode_guru']);
        $nama = htmlspecialchars($row['nama_pengguna']);
        $role = htmlspecialchars($row['role']);
        $jabatan = htmlspecialchars($row['jabatan'] ?? '-');
        $username = htmlspecialchars($row['username']);
        $telp = htmlspecialchars($row['telp'] ?? '-');
        $aktif = $row['aktif'];

        // Badge Status
        if ($aktif === 'Y') {
            $status_html = '<span class="badge badge-green">Aktif</span>';
        }
        else {
            $status_html = '<span class="badge badge-red">Non-Aktif</span>';
        }

        // Role Formatting
        $role_display = $role;
        if ($role === 'admin')
            $role_display = 'Administrator';
        if ($role === 'kepsek')
            $role_display = 'Kepsek';
        if ($role === 'guru_pelajaran')
            $role_display = 'Guru Pelajaran';

        $detail_payload = htmlspecialchars(json_encode([
            'nama' => $nama,
            'kode' => $kode,
            'role' => $role_display,
            'jabatan' => $jabatan,
            'username' => $username,
            'telp' => $telp,
            'status_html' => $status_html
        ]), ENT_QUOTES, 'UTF-8');
?>
                                    <tr>
                                        <td>
                                            <div class="profile-icon">
                                                <i class="fas fa-user-circle"></i>
                                            </div>
                                        </td>
                                        <td><code><?php echo $kode; ?></code></td>
                                        <td style="font-weight:600; color:var(--navy-700)"><?php echo $nama; ?></td>
                                        <td>
                                            <span style="font-size:0.85rem; padding:4px 8px; border-radius:4px; background:#e2e8f0; color:#475569; font-weight:500;">
                                                <?php echo htmlspecialchars($role_display); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $jabatan; ?></td>
                                        <td>
                                            <?php echo $status_html; ?>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button type="button" class="action-btn info-btn" data-payload="<?php echo $detail_payload; ?>" onclick="showDetailModal(this)" title="Info Guru">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <a href="edit.php?kode=<?php echo urlencode($kode); ?>" class="action-btn edit-btn" title="Edit Guru">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($kode !== $_SESSION['user_id']): // Prevent deleting oneself ?>
                                                <button type="button" onclick="confirmDelete('<?php echo addslashes($kode); ?>', '<?php echo addslashes($nama); ?>')" class="action-btn del-btn" title="Hapus Guru">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php
        endif; ?>
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
$params = "&q=" . urlencode($search) . "&sort=" . urlencode($sort) . "&role=" . urlencode($filter_role) . "&status=" . urlencode($filter_status);
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
                <h3><i class="fas fa-address-card"></i> Detail Informasi Guru</h3>
                <button class="close-btn" onclick="closeDetailModal()"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="detail-modal-body">
                <div class="modal-col-left">
                    <div class="profile-card">
                        <div class="avatar-container" id="modal-avatar-container">
                            <i class="fas fa-user-circle avatar-icon"></i>
                        </div>
                        <h4 class="guru-name" id="modal-nama">-</h4>
                        <div class="guru-kode" id="modal-kode">-</div>
                        <div id="modal-status-badge" style="margin-top:0.5rem;"></div>
                    </div>
                </div>

                <div class="modal-col-right">
                    <div class="info-card">
                        <div class="info-card-header">
                            <i class="fas fa-briefcase"></i> Data Pekerjaan
                        </div>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Hak Akses (Role)</span>
                                <span class="info-value" id="modal-role">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Pekerjaan/Jabatan</span>
                                <span class="info-value" id="modal-jabatan">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Username Akun</span>
                                <span class="info-value" id="modal-username">-</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">No. Telepon Aktif</span>
                                <span class="info-value" id="modal-telp">-</span>
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
            <p>Hapus pengguna <strong id="deleteNama"></strong>?</p>
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
                
                document.getElementById('modal-nama').textContent = data.nama;
                document.getElementById('modal-kode').textContent = data.kode;
                document.getElementById('modal-status-badge').innerHTML = data.status_html;
                
                document.getElementById('modal-role').textContent = data.role;
                document.getElementById('modal-jabatan').textContent = data.jabatan;
                document.getElementById('modal-username').textContent = data.username;
                document.getElementById('modal-telp').textContent = data.telp;
                
                document.getElementById('detailModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            } catch (e) {
                console.error("Error parsing detail data payload", e);
                alert("Gagal memuat detail pengguna.");
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
