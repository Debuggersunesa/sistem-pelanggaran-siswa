<?php
require_once '../../includes/session.php';
requireRole(['admin', 'kepsek']);
require_once '../../config/koneksi.php';

$msg = '';
$error = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'tambah_sukses') {
    $msg = 'Data Surat Pindah Siswa berhasil ditambahkan.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';

    if ($aksi === 'hapus') {
        $id = $_POST['id'] ?? '';
        $id_clean = mysqli_real_escape_string($conn, $id);

        mysqli_begin_transaction($conn);
        try {
            // Ambil id_surat_pindah sebelum menghapus surat_keluar
            $q_get = "SELECT id_surat_pindah FROM surat_keluar WHERE id_surat_keluar = '$id_clean'";
            $res_get = mysqli_query($conn, $q_get);
            $row_get = mysqli_fetch_assoc($res_get);
            $id_sp = $row_get['id_surat_pindah'] ?? null;

            // Hapus dari surat_keluar
            $stmt_del1 = mysqli_prepare($conn, "DELETE FROM surat_keluar WHERE id_surat_keluar = ?");
            mysqli_stmt_bind_param($stmt_del1, 'i', $id_clean);
            if (!mysqli_stmt_execute($stmt_del1)) {
                throw new Exception("Gagal menghapus data surat keluar.");
            }
            mysqli_stmt_close($stmt_del1);

            // Hapus dari surat_pindah jika ada relasinya
            if ($id_sp) {
                $stmt_del2 = mysqli_prepare($conn, "DELETE FROM surat_pindah WHERE id_surat_pindah = ?");
                mysqli_stmt_bind_param($stmt_del2, 'i', $id_sp);
                mysqli_stmt_execute($stmt_del2);
                mysqli_stmt_close($stmt_del2);
            }

            mysqli_commit($conn);
            $msg = 'Data Surat Pindah berhasil dihapus.';
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
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 15;
$offset = ($page - 1) * $limit;

$conditions = [];

// Base Join Query Sesuai Skema Database
$from_clause = "FROM surat_keluar sk 
                JOIN surat_pindah sp ON sk.id_surat_pindah = sp.id_surat_pindah
                JOIN siswa s ON sk.nis = s.nis 
                JOIN tahun_ajaran ta ON sk.id_tahun_ajaran = ta.id_tahun_ajaran";

if ($search) {
    $s = mysqli_real_escape_string($conn, $search);
    $conditions[] = "(s.nama_siswa LIKE '%$s%' OR s.nis LIKE '%$s%' OR sk.no_surat LIKE '%$s%' OR sp.sekolah_tujuan LIKE '%$s%')";
}

if ($filter_ta) {
    $ta_clean = mysqli_real_escape_string($conn, $filter_ta);
    $conditions[] = "sk.id_tahun_ajaran = '$ta_clean'";
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
$order_sql = "ORDER BY sk.tanggal_pembuatan_surat DESC";

if ($sort === 'tanggal_asc')
    $order_sql = "ORDER BY sk.tanggal_pembuatan_surat ASC";
elseif ($sort === 'tanggal_desc')
    $order_sql = "ORDER BY sk.tanggal_pembuatan_surat DESC";
elseif ($sort === 'nama_asc')
    $order_sql = "ORDER BY s.nama_siswa ASC";
elseif ($sort === 'tujuan_asc')
    $order_sql = "ORDER BY sp.sekolah_tujuan ASC";

$query_data = "
    SELECT 
        sk.id_surat_keluar,
        sp.id_surat_pindah,
        sk.no_surat,
        sk.tanggal_pembuatan_surat, 
        sk.nis, 
        s.nama_siswa,
        s.foto,
        sp.sekolah_tujuan,
        sp.alasan_pindah,
        ta.tahun,
        sk.tingkat
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Surat Pindah - SISP</title>
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
            background-color: var(--color-light);
            color: var(--color-main);
            font-weight: 600;
            padding: 12px 16px;
            text-align: left;
            border-bottom: 2px solid var(--color-border);
        }
        .table-wrapper td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--color-border);
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
        }
        .action-btn.del-btn:hover {
            background-color: #ef4444;
            color: white;
        }
        .action-btn.info-btn:hover {
            background-color: #3f72af;
            color: white;
        }
        .action-btn.print-btn:hover {
            background-color: #10b981;
            color: white;
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
        .badge-tujuan {
            background: #e0f2fe;
            color: #0369a1;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 6px;
            display: inline-block;
            font-size: 0.85rem;
        }
        .student-info strong {
            color: var(--color-main);
            font-weight: 600;
        }
        .student-info span {
            font-size: 0.85rem;
            color: var(--color-muted);
        }
    </style>
</head>
<body>
    <?php require_once '../../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="topbar">
            <div class="topbar-left">
                <h1><i class="fa-solid fa-file-export" style="color:var(--color-accent)"></i> Data Surat Pindah Siswa</h1>
            </div>
            <div class="topbar-right">
                <a href="cetak_form.php" class="btn btn-secondary" style="margin-right: 8px;"><i class="fas fa-print"></i> Cetak Surat</a>
                <a href="tambah.php" class="btn btn-primary"><i class="fas fa-plus"></i> Entry Surat Pindah</a>
            </div>
        </div>
        
        <div class="page-content">
            <?php if ($msg): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">
                    <h3><i class="fa-solid fa-table-list"></i> Riwayat Surat Pindah (<?php echo $total; ?>)</h3>
                    
                    <form method="GET" class="header-controls" style="display: flex; gap: 10px; align-items: center;">
                        <div class="search-box">
                            <input type="text" name="q" placeholder="Cari Siswa, No. Surat, atau Sekolah..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="search-btn"><i class="fas fa-search"></i></button>
                        </div>

                        <div class="filter-box">
                            <div class="select-wrapper">
                                <select name="ta" onchange="this.form.submit()">
                                    <option value="">Semua Tahun Ajaran</option>
                                    <?php foreach ($ta_options as $t): ?>
                                        <option value="<?php echo $t['id_tahun_ajaran']; ?>" <?php echo $filter_ta == $t['id_tahun_ajaran'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($t['tahun']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>

                        <div class="filter-box">
                            <div class="select-wrapper">
                                <select name="sort" onchange="this.form.submit()">
                                    <option value="tanggal_desc" <?php echo ($sort === 'tanggal_desc') ? 'selected' : ''; ?>>Urutan: Tanggal (Terbaru)</option>
                                    <option value="tanggal_asc" <?php echo ($sort === 'tanggal_asc') ? 'selected' : ''; ?>>Urutan: Tanggal (Terlama)</option>
                                    <option value="nama_asc" <?php echo ($sort === 'nama_asc') ? 'selected' : ''; ?>>Urutan: Nama Siswa (A-Z)</option>
                                    <option value="tujuan_asc" <?php echo ($sort === 'tujuan_asc') ? 'selected' : ''; ?>>Urutan: Sekolah Tujuan (A-Z)</option>
                                </select>
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="table-wrapper" style="padding: 20px;">
                    <table>
                        <thead>
                            <tr>
                                <th>No. Surat</th>
                                <th>Data Siswa</th>
                                <th>Sekolah Tujuan</th>
                                <th>Tanggal Surat</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (mysqli_num_rows($result) === 0): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 40px; color: var(--color-muted);">
                                        <i class="fas fa-file-export" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
                                        Tidak ada riwayat surat pindah ditemukan.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                while ($row = mysqli_fetch_assoc($result)):
                                    $id = $row['id_surat_keluar'];
                                    $date = new DateTime($row['tanggal_pembuatan_surat']);
                                    $tgl_format = $date->format('d M Y');

                                    $no_surat = htmlspecialchars($row['no_surat']);
                                    $nis = htmlspecialchars($row['nis']);
                                    $nama_siswa = htmlspecialchars($row['nama_siswa']);
                                    $sekolah_tujuan = htmlspecialchars($row['sekolah_tujuan']);
                                    $alasan_pindah = htmlspecialchars($row['alasan_pindah'] ?? '-');

                                    $foto_path = (!empty($row['foto']) && file_exists("../../uploads/siswa/" . $row['foto']))
                                        ? "../../uploads/siswa/" . htmlspecialchars($row['foto'])
                                        : "https://ui-avatars.com/api/?name=" . urlencode($nama_siswa) . "&background=e2e8f0&color=475569";
                                ?>
                                    <tr>
                                        <td><code><?php echo $no_surat; ?></code></td>
                                        <td>
                                            <div class="student-info">
                                                <strong><?php echo $nama_siswa; ?></strong><br>
                                                <span>NIS: <?php echo $nis; ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge-tujuan">
                                                <i class="fas fa-school" style="margin-right:4px;"></i> <?php echo $sekolah_tujuan; ?>
                                            </span>
                                        </td>
                                        <td><?php echo $tgl_format; ?></td>
                                        <td>
                                            <div class="action-btns">
                                                <form action="cetak.php" method="POST" target="_blank" style="margin:0;">
                                                    <input type="hidden" name="nis" value="<?php echo $nis; ?>">
                                                    <input type="hidden" name="sekolah_tujuan" value="<?php echo $sekolah_tujuan; ?>">
                                                    <input type="hidden" name="alasan_pindah" value="<?php echo $alasan_pindah; ?>">
                                                    <input type="hidden" name="no_surat" value="<?php echo $no_surat; ?>">
                                                    <button type="submit" class="action-btn print-btn" title="Cetak Surat Pindah">
                                                        <i class="fas fa-print"></i>
                                                    </button>
                                                </form>

                                                <button type="button" onclick="openInfoModal({
                                                    nomor: '<?php echo addslashes($no_surat); ?>',
                                                    nama: '<?php echo addslashes($nama_siswa); ?>',
                                                    nis: '<?php echo addslashes($nis); ?>',
                                                    tujuan: '<?php echo addslashes($sekolah_tujuan); ?>',
                                                    alasan: '<?php echo addslashes(str_replace(array("\r", "\n"), array('\r', '\n'), $alasan_pindah)); ?>',
                                                    tgl: '<?php echo addslashes($tgl_format); ?>',
                                                    foto: '<?php echo addslashes($foto_path); ?>'
                                                })" class="action-btn info-btn" title="Detail">
                                                    <i class="fas fa-info-circle"></i>
                                                </button>
                                                <a href="edit.php?id=<?php echo urlencode($id); ?>" class="action-btn edit-btn" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" onclick="confirmDelete('<?php echo $id; ?>', '<?php echo addslashes($nama_siswa . ' (' . $no_surat . ')'); ?>')" class="action-btn del-btn" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Hapus -->
    <div class="modal-overlay" id="deleteModal" style="display:none">
        <div class="modal">
            <h3><i class="fas fa-exclamation-triangle" style="color:#dc2626"></i> Konfirmasi Hapus</h3>
            <p style="margin: 10px 0;">Yakin ingin menghapus data surat pindah untuk: <br><strong id="deleteNama"></strong>?</p>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button onclick="closeModal()" class="btn btn-secondary">Batal</button>
                <form method="POST" style="margin:0">
                    <input type="hidden" name="aksi" value="hapus">
                    <input type="hidden" name="id" id="deleteId">
                    <button type="submit" class="btn" style="background-color: #ef4444; color: white;"><i class="fas fa-trash"></i> Hapus</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Info -->
    <div class="modal-overlay" id="infoModal" style="display:none">
        <div class="modal" style="max-width: 450px;">
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--color-border); padding-bottom:12px; margin-bottom:16px;">
                <h3 style="margin:0; font-size: 1.1rem;"><i class="fas fa-info-circle" style="color:var(--color-accent)"></i> Detail Surat Pindah</h3>
                <button type="button" onclick="closeInfoModal()" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:var(--color-muted);"><i class="fas fa-times"></i></button>
            </div>
            <div style="display:flex; gap:16px; margin-bottom:20px; align-items: center;">
                <img id="infoFoto" src="" alt="Foto" style="width:60px; height:60px; border-radius:50%; object-fit:cover; border:2px solid var(--color-border);">
                <div>
                    <strong id="infoNama" style="font-size:1.05rem; color:var(--color-main);"></strong><br>
                    <span style="color:var(--color-muted); font-size:0.9rem;">NIS: <span id="infoNis"></span></span>
                </div>
            </div>
            <div style="background:#f8fafc; padding:12px 16px; border-radius:8px; margin-bottom:16px; border:1px solid var(--color-border);">
                <span style="display:block; font-size:0.75rem; color:var(--color-muted); font-weight:600; text-transform:uppercase;">No. Surat & Tujuan</span>
                <strong id="infoNomor" style="color:var(--color-text);"></strong><br>
                <span class="badge-tujuan" style="margin-top: 4px;"><i class="fas fa-school"></i> <span id="infoTujuan"></span></span>
            </div>
            <div style="font-size:0.9rem; display:flex; flex-direction:column; gap:8px;">
                <div><strong>Tanggal:</strong> <span id="infoTgl"></span></div>
                <div><strong>Alasan Pindah:</strong> <span id="infoAlasan"></span></div>
            </div>
        </div>
    </div>
    
    <script>
        function openInfoModal(data) {
            document.getElementById('infoNama').textContent = data.nama;
            document.getElementById('infoNis').textContent = data.nis;
            document.getElementById('infoNomor').textContent = data.nomor;
            document.getElementById('infoTujuan').textContent = data.tujuan;
            document.getElementById('infoAlasan').textContent = data.alasan || '-';
            document.getElementById('infoTgl').textContent = data.tgl;
            document.getElementById('infoFoto').src = data.foto;
            document.getElementById('infoModal').style.display = 'flex';
        }
        function closeInfoModal() { document.getElementById('infoModal').style.display = 'none'; }
        function confirmDelete(id, text) {
            document.getElementById('deleteId').value = id;
            document.getElementById('deleteNama').textContent = text;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        function closeModal() { document.getElementById('deleteModal').style.display = 'none'; }
        window.onclick = function(event) {
            if (event.target == document.getElementById('deleteModal')) closeModal();
            if (event.target == document.getElementById('infoModal')) closeInfoModal();
        }
    </script>
</body>
</html>