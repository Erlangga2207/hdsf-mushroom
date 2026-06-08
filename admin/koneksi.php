<?php
// ============================================================
//  KONEKSI DATABASE - HDSF Mushroom v2.3.0
// ============================================================
require_once __DIR__ . '/config.local.php';

$koneksi = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$koneksi) {
    die('<div style="font-family:Inter,sans-serif;padding:40px;text-align:center;color:#ba1a1a;">
        <h2>Koneksi Database Gagal</h2>
        <p>' . mysqli_connect_error() . '</p>
        <p>Pastikan MySQL sudah berjalan dan database <strong>' . DB_NAME . '</strong> sudah dibuat.</p>
    </div>');
}
mysqli_set_charset($koneksi, 'utf8mb4');

// ============================================================
//  HELPER: Ambil semua batch stok terurut FIFO
//  (hanya batch yang masih punya sisa > 0)
// ============================================================
function getFifoBatches($koneksi) {
    $result = mysqli_query($koneksi,
        "SELECT s.id_stock, s.id_barang_masuk, s.sisa_putih, s.sisa_coklat,
                bm.tgl_setor, p.nama AS nama_pemasok, p.id_pemasok
         FROM stock s
         JOIN barang_masuk bm ON s.id_barang_masuk = bm.id_barang_masuk
         JOIN pemasok p       ON bm.id_pemasok = p.id_pemasok
         WHERE s.sisa_putih > 0 OR s.sisa_coklat > 0
         ORDER BY bm.tgl_setor ASC, bm.id_barang_masuk ASC");
    $batches = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $batches[] = $row;
    }
    return $batches;
}

// ============================================================
//  HELPER: Total stok dari sisa per-batch
// ============================================================
function getStokTotal($koneksi) {
    $row = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COALESCE(SUM(sisa_putih),0)  AS stok_putih,
                COALESCE(SUM(sisa_coklat),0) AS stok_coklat
         FROM stock"));
    return [
        'putih'  => (float)$row['stok_putih'],
        'coklat' => (float)$row['stok_coklat'],
        'total'  => (float)$row['stok_putih'] + (float)$row['stok_coklat'],
    ];
}

// ============================================================
//  HELPER: Proses FIFO multi-batch
//  Memotong stok dari batch tertua ke terbaru secara berantai.
//  Mengembalikan array hasil potongan per batch:
//  [['id_stock'=>X, 'potong_putih'=>Y, 'potong_coklat'=>Z], ...]
//  atau false jika stok tidak cukup.
// ============================================================
function hitungFifoPotongan($koneksi, $req_putih, $req_coklat) {
    $batches = getFifoBatches($koneksi);
    $sisa_p  = $req_putih;
    $sisa_c  = $req_coklat;
    $potongan = [];

    foreach ($batches as $batch) {
        if ($sisa_p <= 0 && $sisa_c <= 0) break;

        $potong_p = min($batch['sisa_putih'],  $sisa_p);
        $potong_c = min($batch['sisa_coklat'], $sisa_c);

        if ($potong_p > 0 || $potong_c > 0) {
            $potongan[] = [
                'id_stock'      => $batch['id_stock'],
                'potong_putih'  => $potong_p,
                'potong_coklat' => $potong_c,
                'sisa_putih'    => $batch['sisa_putih'],
                'sisa_coklat'   => $batch['sisa_coklat'],
            ];
            $sisa_p -= $potong_p;
            $sisa_c -= $potong_c;
        }
    }

    // Cek apakah semua terpenuhi
    if ($sisa_p > 0.001 || $sisa_c > 0.001) {
        return false; // stok tidak cukup
    }

    return $potongan;
}

// ============================================================
//  HELPER: Terapkan potongan FIFO ke database
//  Mengembalikan id_stock batch pertama yang dipakai
// ============================================================
function terapkanFifo($koneksi, $potongan) {
    $id_stock_utama = null;
    foreach ($potongan as $idx => $p) {
        $baru_putih  = round($p['sisa_putih']  - $p['potong_putih'],  2);
        $baru_coklat = round($p['sisa_coklat'] - $p['potong_coklat'], 2);
        mysqli_query($koneksi,
            "UPDATE stock SET sisa_putih=$baru_putih, sisa_coklat=$baru_coklat
             WHERE id_stock={$p['id_stock']}");
        if ($idx === 0) $id_stock_utama = $p['id_stock'];
    }
    return $id_stock_utama;
}

// ============================================================
//  HELPER: Rollback potongan FIFO (saat hapus barang keluar)
// ============================================================
function rollbackFifo($koneksi, $potongan) {
    foreach ($potongan as $p) {
        $kembali_putih  = round($p['sisa_putih']  + $p['potong_putih'],  2);
        $kembali_coklat = round($p['sisa_coklat'] + $p['potong_coklat'], 2);
        mysqli_query($koneksi,
            "UPDATE stock SET sisa_putih=$kembali_putih, sisa_coklat=$kembali_coklat
             WHERE id_stock={$p['id_stock']}");
    }
}

// ============================================================
//  HELPER: Simpan detail potongan FIFO ke tabel barang_keluar_detail
// ============================================================
function simpanDetailFifo($koneksi, $id_barang_keluar, $potongan) {
    foreach ($potongan as $p) {
        mysqli_query($koneksi,
            "INSERT INTO barang_keluar_detail (id_barang_keluar, id_stock, potong_putih, potong_coklat)
             VALUES ($id_barang_keluar, {$p['id_stock']}, {$p['potong_putih']}, {$p['potong_coklat']})");
    }
}

// ============================================================
//  HELPER: Ambil detail potongan FIFO dari tabel detail
//  untuk keperluan rollback saat hapus
// ============================================================
function getDetailFifo($koneksi, $id_barang_keluar) {
    $result = mysqli_query($koneksi,
        "SELECT bkd.id_stock, bkd.potong_putih, bkd.potong_coklat,
                s.sisa_putih, s.sisa_coklat
         FROM barang_keluar_detail bkd
         JOIN stock s ON bkd.id_stock = s.id_stock
         WHERE bkd.id_barang_keluar = $id_barang_keluar");
    $rows = [];
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
    }
    return $rows;
}
