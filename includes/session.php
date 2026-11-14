<?php
// Mencegah error jika session sudah dimulai sebelumnya
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Fungsi untuk mendapatkan Base URL (folder project)
if (!function_exists('getBaseUrl')) {
    function getBaseUrl()
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . "://" . $host . "/sistem_pelanggaran/";
    }
}

// 2. Fungsi mengecek apakah user sudah login (boolean)
if (!function_exists('isLoggedIn')) {
    function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }
}

// 3. FUNGSI BARU YANG DIMINTA OLEH DASHBOARD.PHP
// Fungsi ini akan menendang user kembali ke halaman login jika belum login
if (!function_exists('requireLogin')) {
    function requireLogin()
    {
        if (!isLoggedIn()) {
            header("Location: " . getBaseUrl() . "index.php?error=akses");
            exit;
        }
    }
}

// 4. Fungsi untuk mendapatkan role user
if (!function_exists('getUserRole')) {
    function getUserRole()
    {
        return $_SESSION['role'] ?? '';
    }
}

// 4b. Fungsi untuk mendapatkan user ID
if (!function_exists('getUserId')) {
    function getUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }
}

// 4c. Fungsi untuk mendapatkan nama user
if (!function_exists('getUserName')) {
    function getUserName()
    {
        return $_SESSION['nama'] ?? 'User';
    }
}

// 5. Fungsi untuk mengarahkan ke dashboard yang benar
if (!function_exists('redirectToDashboard')) {
    function redirectToDashboard($role)
    {
        $url = getBaseUrl() . $role . '/dashboard.php';
        header("Location: " . $url);
        exit;
    }
}

// 6. Fungsi keamanan spesifik untuk membatasi hak akses per role
if (!function_exists('requireRole')) {
    function requireRole($allowed_roles)
    {
        requireLogin(); // Panggil requireLogin dulu untuk memastikan sudah login

        $user_role = getUserRole();
        if (!in_array($user_role, (array)$allowed_roles)) {
            // Jika role tidak sesuai, tendang ke dashboard aslinya
            header("Location: " . getBaseUrl() . $user_role . "/dashboard.php");
            exit;
        }
    }
}
?>