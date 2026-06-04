<?php
// ============================================================
//  SESSION GUARD - Cek login sebelum akses halaman admin
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ' . str_repeat('../', substr_count($_SERVER['PHP_SELF'], '/', strlen($_SERVER['DOCUMENT_ROOT'])) - substr_count(dirname($_SERVER['PHP_SELF']), '/', strlen($_SERVER['DOCUMENT_ROOT']))) . '../auth/login.php');
    exit;
}
