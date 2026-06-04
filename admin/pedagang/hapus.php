<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$pedagang = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama FROM pedagang WHERE id_pedagang = $id"));
if (!$pedagang) {
    $_SESSION['flash_message'] = 'Pedagang tidak ditemukan.';
    $_SESSION['flash_type']    = 'error';
    header('Location: index.php');
    exit;
}

// Cek apakah ada barang keluar terkait
$cek = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM barang_keluar WHERE id_pedagang = $id"));
if ($cek['total'] > 0) {
    $_SESSION['flash_message'] = 'Gagal menghapus: Pedagang "' . $pedagang['nama'] . '" masih memiliki ' . $cek['total'] . ' transaksi barang keluar. Hapus transaksi terkait terlebih dahulu.';
    $_SESSION['flash_type']    = 'error';
    header('Location: index.php');
    exit;
}

$stmt = mysqli_prepare($koneksi, "DELETE FROM pedagang WHERE id_pedagang = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash_message'] = 'Pedagang "' . $pedagang['nama'] . '" berhasil dihapus.';
    $_SESSION['flash_type']    = 'success';
} else {
    $_SESSION['flash_message'] = 'Gagal menghapus: ' . mysqli_error($koneksi);
    $_SESSION['flash_type']    = 'error';
}
mysqli_stmt_close($stmt);
header('Location: index.php');
exit;
