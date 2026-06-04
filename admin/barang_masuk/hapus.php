<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$stmt = mysqli_prepare($koneksi, "DELETE FROM barang_masuk WHERE id_barang_masuk = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
    $_SESSION['flash_message'] = 'Data barang masuk berhasil dihapus.';
    $_SESSION['flash_type']    = 'success';
} else {
    $_SESSION['flash_message'] = 'Data tidak ditemukan atau gagal dihapus.';
    $_SESSION['flash_type']    = 'error';
}
mysqli_stmt_close($stmt);
header('Location: index.php');
exit;
