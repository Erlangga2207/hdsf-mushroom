<?php
session_start();

// Hanya terima POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

require_once '../admin/koneksi.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Validasi tidak kosong
if (empty($username) || empty($password)) {
    $_SESSION['login_error'] = 'Username dan password tidak boleh kosong.';
    header('Location: login.php');
    exit;
}

// Cari user di database
$stmt = mysqli_prepare($koneksi, "SELECT id_user, username, password FROM user WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, 's', $username);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Verifikasi password dengan password_verify (bcrypt)
if ($user && password_verify($password, $user['password'])) {
    // Login berhasil
    session_regenerate_id(true); // Cegah session fixation
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id']        = $user['id_user'];
    $_SESSION['admin_username']  = $user['username'];

    header('Location: ../admin/dashboard.php');
    exit;
} else {
    $_SESSION['login_error'] = 'Username atau password salah. Silakan coba lagi.';
    header('Location: login.php');
    exit;
}
