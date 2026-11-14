<?php
require_once 'includes/session.php';
require_once 'config/koneksi.php';

if (isLoggedIn()) {
    redirectToDashboard(getUserRole());
}

$error = '';
$success = '';

if (isset($_GET['logout'])) {
    $success = 'Anda berhasil keluar dari sistem.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? ''); // Bisa username (guru) atau NIS (siswa)
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username/NIS dan password wajib diisi.';
    }
    else {
        // Cek di tabel guru (Admin, Kepsek, Guru Pelajaran)
        $stmt_guru = mysqli_prepare($conn, "SELECT kode_guru, username, password, role, nama_pengguna FROM guru WHERE username = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_guru, 's', $username);
        mysqli_stmt_execute($stmt_guru);
        $res_guru = mysqli_stmt_get_result($stmt_guru);
        $guru = mysqli_fetch_assoc($res_guru);
        mysqli_stmt_close($stmt_guru);

        if ($guru) {
            if (password_verify($password, $guru['password'])) {
                $_SESSION['user_id'] = $guru['kode_guru'];
                $_SESSION['role'] = $guru['role']; // admin, kepsek, guru_pelajaran
                $_SESSION['nama'] = $guru['nama_pengguna'];
                redirectToDashboard($guru['role']);
            }
            else {
                $error = 'Password salah.';
            }
        }
        else {
            // Cek di tabel siswa
            $stmt_siswa = mysqli_prepare($conn, "SELECT nis, nama_siswa, password, status FROM siswa WHERE nis = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_siswa, 's', $username);
            mysqli_stmt_execute($stmt_siswa);
            $res_siswa = mysqli_stmt_get_result($stmt_siswa);
            $siswa = mysqli_fetch_assoc($res_siswa);
            mysqli_stmt_close($stmt_siswa);

            if ($siswa) {
                if (password_verify($password, $siswa['password'])) {
                    if ($siswa['status'] !== 'aktif') {
                        $error = 'Akun siswa tidak aktif.';
                    }
                    else {
                        $_SESSION['user_id'] = $siswa['nis'];
                        $_SESSION['role'] = 'siswa';
                        $_SESSION['nama'] = $siswa['nama_siswa'];
                        redirectToDashboard('siswa');
                    }
                }
                else {
                    $error = 'Password salah.';
                }
            }
            else {
                $error = 'Akun tidak ditemukan.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SISP</title>
    <link rel="stylesheet" href="css/auth.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <div class="auth-logo"><i class="fas fa-shield-alt"></i></div>
            <h1 class="auth-title">Selamat Datang</h1>
            <p class="auth-subtitle">Masuk untuk mengakses Sistem Poin Pelanggaran Siswa (SPPS)</p>
        </div>

        <?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div><?php
endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div><?php
endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Username / NIS</label>
                <div class="input-wrapper">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan ID Pengguna" required autofocus>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-wrapper">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Masukkan Password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                </div>
            </div>
            <button type="submit" class="btn-login">Masuk ke Sistem</button>
        </form>

    </div>
    <script>
    function togglePassword() {
        const p = document.getElementById('password');
        const i = document.querySelector('.toggle-password');
        if (p.type === 'password') { p.type = 'text'; i.classList.replace('fa-eye', 'fa-eye-slash'); }
        else { p.type = 'password'; i.classList.replace('fa-eye-slash', 'fa-eye'); }
    }
    </script>
</body>
</html>