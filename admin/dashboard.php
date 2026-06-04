<?php
session_start();
require_once 'session_guard.php';
require_once 'koneksi.php';

$page_title  = 'Dashboard Operasional';
$active_menu = 'dashboard';
$base_path   = '';

// ===== AMBIL DATA STATISTIK =====
$total_pemasok  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pemasok"))['total'];
$total_pedagang = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pedagang"))['total'];

// Total stok saat ini (FIFO-aware)
$stok_raw   = getStokTotal($koneksi);
$stok_putih  = $stok_raw['putih'];
$stok_coklat = $stok_raw['coklat'];
$stok_total  = $stok_raw['total'];

// Total barang keluar (penjualan)
$keluar = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(jamur_putih + jamur_coklat),0) AS total, COALESCE(SUM(subtotal),0) AS omzet FROM barang_keluar"));
$total_keluar = $keluar['total'];
$total_omzet  = $keluar['omzet'];

// 7 transaksi barang masuk terbaru
$recent_masuk = mysqli_query($koneksi,
    "SELECT bm.id_barang_masuk, p.nama AS pemasok, bm.tgl_setor, bm.jamur_putih, bm.jamur_coklat, bm.jumlah_barang
     FROM barang_masuk bm
     JOIN pemasok p ON bm.id_pemasok = p.id_pemasok
     ORDER BY bm.tgl_setor DESC, bm.id_barang_masuk DESC
     LIMIT 7");

// 7 transaksi barang keluar terbaru
$recent_keluar = mysqli_query($koneksi,
    "SELECT bk.id_barang_keluar, pd.nama AS pedagang, bk.tgl_kirim, bk.jamur_putih, bk.jamur_coklat, bk.subtotal
     FROM barang_keluar bk
     JOIN pedagang pd ON bk.id_pedagang = pd.id_pedagang
     ORDER BY bk.tgl_kirim DESC, bk.id_barang_keluar DESC
     LIMIT 7");

require_once 'header.php';
?>

<!-- ===== STATS GRID ===== -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter mb-lg">

    <!-- Total Pemasok -->
    <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10 flex flex-col gap-sm">
        <div class="flex justify-between items-start">
            <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Total Pemasok</span>
            <div class="w-9 h-9 bg-primary-fixed/40 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-[20px]">local_shipping</span>
            </div>
        </div>
        <span class="text-[36px] font-bold text-on-surface leading-none"><?= $total_pemasok ?></span>
        <a href="pemasok/index.php" class="text-[12px] text-primary font-medium flex items-center gap-xs hover:underline">
            <span class="material-symbols-outlined text-[14px]">arrow_forward</span> Lihat semua
        </a>
    </div>

    <!-- Total Pedagang -->
    <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10 flex flex-col gap-sm">
        <div class="flex justify-between items-start">
            <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Total Pedagang</span>
            <div class="w-9 h-9 bg-primary-fixed/40 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-[20px]">storefront</span>
            </div>
        </div>
        <span class="text-[36px] font-bold text-on-surface leading-none"><?= $total_pedagang ?></span>
        <a href="pedagang/index.php" class="text-[12px] text-primary font-medium flex items-center gap-xs hover:underline">
            <span class="material-symbols-outlined text-[14px]">arrow_forward</span> Lihat semua
        </a>
    </div>

    <!-- Total Stok -->
    <div class="bg-primary p-md rounded-xl card-shadow flex flex-col gap-sm">
        <div class="flex justify-between items-start">
            <span class="text-[11px] font-bold uppercase tracking-widest text-primary-fixed-dim opacity-80">Total Stok Gudang</span>
            <span class="material-symbols-outlined text-primary-fixed-dim text-[20px]">inventory_2</span>
        </div>
        <div>
            <span class="text-[36px] font-bold text-white leading-none"><?= number_format($stok_total, 1) ?></span>
            <span class="text-[13px] text-primary-fixed-dim opacity-80 ml-xs">kg</span>
        </div>
        <div class="text-[12px] text-primary-fixed-dim opacity-70">
            Putih: <?= number_format($stok_putih, 1) ?>kg · Coklat: <?= number_format($stok_coklat, 1) ?>kg
        </div>
    </div>

    <!-- Total Omzet -->
    <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10 flex flex-col gap-sm">
        <div class="flex justify-between items-start">
            <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Total Omzet</span>
            <div class="w-9 h-9 bg-primary-fixed/40 rounded-lg flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-[20px]">payments</span>
            </div>
        </div>
        <span class="text-[24px] font-bold text-on-surface leading-none">
            Rp <?= number_format($total_omzet, 0, ',', '.') ?>
        </span>
        <a href="barang_keluar/index.php" class="text-[12px] text-primary font-medium flex items-center gap-xs hover:underline">
            <span class="material-symbols-outlined text-[14px]">arrow_forward</span> Lihat penjualan
        </a>
    </div>
</div>

<!-- ===== TABEL TERBARU ===== -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">

    <!-- Barang Masuk Terbaru -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
        <div class="px-md py-sm flex items-center justify-between border-b border-outline-variant/10">
            <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-primary text-[20px]">move_to_inbox</span>
                <h3 class="text-[16px] font-semibold text-on-surface">Barang Masuk Terbaru</h3>
            </div>
            <a href="barang_masuk/index.php" class="text-[12px] text-primary hover:underline font-medium">Lihat semua →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-outline-variant/10">
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Pemasok</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Tanggal</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php if (mysqli_num_rows($recent_masuk) === 0): ?>
                    <tr><td colspan="3" class="px-md py-lg text-center text-on-surface-variant text-[13px]">Belum ada data</td></tr>
                    <?php endif; ?>
                    <?php while ($row = mysqli_fetch_assoc($recent_masuk)): ?>
                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                        <td class="px-md py-sm text-[13px] font-medium text-on-surface"><?= htmlspecialchars($row['pemasok']) ?></td>
                        <td class="px-md py-sm text-[13px] text-on-surface-variant"><?= date('d M Y', strtotime($row['tgl_setor'])) ?></td>
                        <td class="px-md py-sm text-[13px] font-semibold text-primary text-right"><?= number_format($row['jumlah_barang'], 1) ?> kg</td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Barang Keluar Terbaru -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
        <div class="px-md py-sm flex items-center justify-between border-b border-outline-variant/10">
            <div class="flex items-center gap-sm">
                <span class="material-symbols-outlined text-primary text-[20px]">outbox</span>
                <h3 class="text-[16px] font-semibold text-on-surface">Barang Keluar Terbaru</h3>
            </div>
            <a href="barang_keluar/index.php" class="text-[12px] text-primary hover:underline font-medium">Lihat semua →</a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-outline-variant/10">
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Pedagang</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Tanggal</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php if (mysqli_num_rows($recent_keluar) === 0): ?>
                    <tr><td colspan="3" class="px-md py-lg text-center text-on-surface-variant text-[13px]">Belum ada data</td></tr>
                    <?php endif; ?>
                    <?php while ($row = mysqli_fetch_assoc($recent_keluar)): ?>
                    <tr class="hover:bg-surface-container-low/50 transition-colors">
                        <td class="px-md py-sm text-[13px] font-medium text-on-surface"><?= htmlspecialchars($row['pedagang']) ?></td>
                        <td class="px-md py-sm text-[13px] text-on-surface-variant"><?= date('d M Y', strtotime($row['tgl_kirim'])) ?></td>
                        <td class="px-md py-sm text-[13px] font-semibold text-primary text-right">Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- end grid -->

<?php require_once 'footer.php'; ?>
