<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// Ambil detail potongan FIFO untuk rollback
$detail_fifo = getDetailFifo($koneksi, $id);

if (empty($detail_fifo)) {
    // Fallback: hapus manual tanpa rollback detail
    $bk = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT bk.id_stock, bk.jamur_putih, bk.jamur_coklat, s.sisa_putih, s.sisa_coklat
         FROM barang_keluar bk JOIN stock s ON bk.id_stock=s.id_stock
         WHERE bk.id_barang_keluar=$id"));
    if ($bk) {
        $rp = round($bk['sisa_putih']  + $bk['jamur_putih'],  2);
        $rc = round($bk['sisa_coklat'] + $bk['jamur_coklat'], 2);
        mysqli_query($koneksi, "UPDATE stock SET sisa_putih=$rp, sisa_coklat=$rc WHERE id_stock={$bk['id_stock']}");
    }
} else {
    // Rollback semua batch yang terlibat
    rollbackFifo($koneksi, $detail_fifo);
}

// Hapus transaksi (detail terhapus otomatis via ON DELETE CASCADE)
$del = mysqli_query($koneksi, "DELETE FROM barang_keluar WHERE id_barang_keluar=$id");
if ($del && mysqli_affected_rows($koneksi) > 0) {
    $_SESSION['flash_message'] = 'Data barang keluar dihapus dan stok dikembalikan ke batch asal.';
    $_SESSION['flash_type']    = 'success';
} else {
    $_SESSION['flash_message'] = 'Data tidak ditemukan atau gagal dihapus.';
    $_SESSION['flash_type']    = 'error';
}
header('Location: index.php');
exit;
