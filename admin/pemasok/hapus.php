<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$pemasok = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT nama FROM pemasok WHERE id_pemasok = $id"));
if (!$pemasok) {
    $_SESSION['flash_message'] = 'Pemasok tidak ditemukan.';
    $_SESSION['flash_type']    = 'error';
    header('Location: index.php');
    exit;
}

// Hapus barang_masuk terkait dulu (cascade manual)
mysqli_query($koneksi, "DELETE FROM barang_masuk WHERE id_pemasok = $id");

$stmt = mysqli_prepare($koneksi, "DELETE FROM pemasok WHERE id_pemasok = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
if (mysqli_stmt_execute($stmt)) {
    $_SESSION['flash_message'] = 'Pemasok "' . $pemasok['nama'] . '" berhasil dihapus.';
    $_SESSION['flash_type']    = 'success';
} else {
    $_SESSION['flash_message'] = 'Gagal menghapus pemasok: ' . mysqli_error($koneksi);
    $_SESSION['flash_type']    = 'error';
}
mysqli_stmt_close($stmt);
header('Location: index.php');
exit;
