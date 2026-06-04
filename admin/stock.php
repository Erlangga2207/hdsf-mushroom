<?php
session_start();
require_once 'session_guard.php';
require_once 'koneksi.php';

$page_title  = 'Manajemen Stok';
$active_menu = 'stock';
$base_path   = '';

// ===== HITUNG STOK REAL-TIME (FIFO-aware) =====
$stok_data   = getStokTotal($koneksi);
$stok_putih  = $stok_data['putih'];
$stok_coklat = $stok_data['coklat'];
$stok_total  = $stok_putih + $stok_coklat;

// Detail masuk/keluar untuk kartu stok
$detail = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(jamur_putih),0)  AS masuk_putih,
            COALESCE(SUM(jamur_coklat),0) AS masuk_coklat FROM barang_masuk"));
$detail_k = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(jamur_putih),0)  AS keluar_putih,
            COALESCE(SUM(jamur_coklat),0) AS keluar_coklat FROM barang_keluar"));

$kapasitas = 5000;
$persen_kapasitas = $kapasitas > 0 ? min(100, round($stok_total / $kapasitas * 100, 1)) : 0;

// ===== PERGERAKAN STOK 7 HARI TERAKHIR =====
$tren_masuk = [];
$tren_keluar = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl = date('Y-m-d', strtotime("-$i days"));
    $label = date('D', strtotime($tgl));

    $m = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COALESCE(SUM(jumlah_barang),0) AS total FROM barang_masuk WHERE tgl_setor='$tgl'"));
    $k = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT COALESCE(SUM(jamur_putih+jamur_coklat),0) AS total FROM barang_keluar WHERE tgl_kirim='$tgl'"));

    $tren_masuk[]  = ['label' => $label, 'tgl' => $tgl, 'val' => (float)$m['total']];
    $tren_keluar[] = ['label' => $label, 'tgl' => $tgl, 'val' => (float)$k['total']];
}

$max_bar = 1;
foreach ($tren_masuk as $t)  $max_bar = max($max_bar, $t['val']);
foreach ($tren_keluar as $t) $max_bar = max($max_bar, $t['val']);

// ===== PEMASOK TOP 5 =====
$top_pemasok = mysqli_query($koneksi,
    "SELECT p.nama, SUM(bm.jumlah_barang) AS total_kg
     FROM barang_masuk bm
     JOIN pemasok p ON bm.id_pemasok = p.id_pemasok
     GROUP BY bm.id_pemasok
     ORDER BY total_kg DESC
     LIMIT 5");

// ===== PEDAGANG TOP 5 =====
$top_pedagang = mysqli_query($koneksi,
    "SELECT pd.nama, SUM(bk.jamur_putih + bk.jamur_coklat) AS total_kg, SUM(bk.subtotal) AS total_omzet
     FROM barang_keluar bk
     JOIN pedagang pd ON bk.id_pedagang = pd.id_pedagang
     GROUP BY bk.id_pedagang
     ORDER BY total_kg DESC
     LIMIT 5");

require_once 'header.php';
?>

<div class="max-w-6xl mx-auto">
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Stok</span>
    </nav>

    <!-- ===== KARTU STOK UTAMA ===== -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-lg">

        <!-- Jamur Putih -->
        <div class="bg-surface-container-lowest p-lg rounded-xl card-shadow border-l-4 border-primary">
            <div class="flex justify-between items-start mb-sm">
                <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Stok Jamur Putih</span>
                <span class="material-symbols-outlined text-primary" style="font-variation-settings:'FILL' 1">eco</span>
            </div>
            <div class="flex items-baseline gap-xs mb-sm">
                <span class="text-[40px] font-bold text-on-surface leading-none"><?= number_format($stok_putih, 1) ?></span>
                <span class="text-[16px] text-on-surface-variant">kg</span>
            </div>
            <div class="text-[12px] text-on-surface-variant space-y-xs">
                <div class="flex justify-between">
                    <span>Total masuk:</span>
                    <span class="font-semibold text-primary"><?= number_format(0, 1) ?> kg</span>
                </div>
                <div class="flex justify-between">
                    <span>Total keluar:</span>
                    <span class="font-semibold text-error"><?= number_format(0, 1) ?> kg</span>
                </div>
            </div>
        </div>

        <!-- Jamur Coklat -->
        <div class="bg-surface-container-lowest p-lg rounded-xl card-shadow border-l-4 border-tertiary">
            <div class="flex justify-between items-start mb-sm">
                <span class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Stok Jamur Coklat</span>
                <span class="material-symbols-outlined text-tertiary" style="font-variation-settings:'FILL' 1">nutrition</span>
            </div>
            <div class="flex items-baseline gap-xs mb-sm">
                <span class="text-[40px] font-bold text-on-surface leading-none"><?= number_format($stok_coklat, 1) ?></span>
                <span class="text-[16px] text-on-surface-variant">kg</span>
            </div>
            <div class="text-[12px] text-on-surface-variant space-y-xs">
                <div class="flex justify-between">
                    <span>Total masuk:</span>
                    <span class="font-semibold text-primary"><?= number_format(0, 1) ?> kg</span>
                </div>
                <div class="flex justify-between">
                    <span>Total keluar:</span>
                    <span class="font-semibold text-error"><?= number_format(0, 1) ?> kg</span>
                </div>
            </div>
        </div>

        <!-- Grand Total -->
        <div class="bg-primary p-lg rounded-xl card-shadow text-white">
            <div class="flex justify-between items-start mb-sm">
                <span class="text-[11px] font-bold uppercase tracking-widest text-primary-fixed-dim opacity-80">Grand Total Stok</span>
                <span class="material-symbols-outlined text-primary-fixed-dim" style="font-variation-settings:'FILL' 1">inventory</span>
            </div>
            <div class="flex items-baseline gap-xs mb-md">
                <span class="text-[40px] font-bold leading-none"><?= number_format($stok_total, 1) ?></span>
                <span class="text-[16px] opacity-80">kg</span>
            </div>
            <!-- Progress Bar Kapasitas -->
            <div class="mb-xs">
                <div class="flex justify-between text-[11px] text-primary-fixed-dim opacity-80 mb-xs">
                    <span>Kapasitas Gudang</span>
                    <span><?= $persen_kapasitas ?>%</span>
                </div>
                <div class="w-full bg-white/20 rounded-full h-2">
                    <div class="h-2 rounded-full transition-all duration-500
                        <?= $persen_kapasitas >= 90 ? 'bg-error' : ($persen_kapasitas >= 70 ? 'bg-yellow-400' : 'bg-primary-fixed-dim') ?>"
                         style="width: <?= $persen_kapasitas ?>%"></div>
                </div>
            </div>
            <p class="text-[11px] text-primary-fixed-dim opacity-70">Kapasitas: <?= number_format($kapasitas) ?> kg</p>
        </div>
    </div>

    <!-- ===== AKSI CEPAT ===== -->
    <div class="grid grid-cols-2 gap-gutter mb-lg">
        <a href="barang_masuk/tambah.php"
           class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md flex items-center gap-md hover:border-primary/40 hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all">
                <span class="material-symbols-outlined text-primary group-hover:text-white text-[24px]">move_to_inbox</span>
            </div>
            <div>
                <p class="text-[15px] font-bold text-on-surface">Input Barang Masuk</p>
                <p class="text-[12px] text-on-surface-variant">Catat setoran dari pemasok</p>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant ml-auto text-[20px]">arrow_forward</span>
        </a>
        <a href="barang_keluar/tambah.php"
           class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md flex items-center gap-md hover:border-primary/40 hover:shadow-md transition-all group">
            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center group-hover:bg-primary group-hover:text-white transition-all">
                <span class="material-symbols-outlined text-primary group-hover:text-white text-[24px]">outbox</span>
            </div>
            <div>
                <p class="text-[15px] font-bold text-on-surface">Input Barang Keluar</p>
                <p class="text-[12px] text-on-surface-variant">Catat pengiriman ke pedagang</p>
            </div>
            <span class="material-symbols-outlined text-on-surface-variant ml-auto text-[20px]">arrow_forward</span>
        </a>
    </div>

    <!-- ===== TREN 7 HARI ===== -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg mb-lg">
        <div class="flex items-center justify-between mb-lg">
            <div>
                <h3 class="text-[18px] font-semibold text-on-surface">Tren Keluar-Masuk Stok</h3>
                <p class="text-[12px] text-on-surface-variant">7 hari terakhir (kg)</p>
            </div>
            <div class="flex items-center gap-md text-[12px]">
                <span class="flex items-center gap-xs"><span class="w-3 h-3 rounded-full bg-primary inline-block"></span> Masuk</span>
                <span class="flex items-center gap-xs"><span class="w-3 h-3 rounded-full bg-error inline-block"></span> Keluar</span>
            </div>
        </div>

        <div class="flex items-end gap-sm h-48">
            <?php foreach ($tren_masuk as $i => $tm): ?>
            <?php $tk = $tren_keluar[$i]; ?>
            <div class="flex-1 flex flex-col items-center gap-xs">
                <div class="w-full flex gap-xs items-end justify-center" style="height:160px">
                    <!-- Bar Masuk -->
                    <div class="flex-1 bg-primary rounded-t transition-all relative group"
                         style="height: <?= $max_bar > 0 ? max(4, round($tm['val'] / $max_bar * 160)) : 4 ?>px">
                        <?php if ($tm['val'] > 0): ?>
                        <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-inverse-surface text-inverse-on-surface text-[10px] px-xs py-[2px] rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                            <?= number_format($tm['val'], 1) ?> kg
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Bar Keluar -->
                    <div class="flex-1 bg-error/60 rounded-t transition-all relative group"
                         style="height: <?= $max_bar > 0 ? max($tk['val'] > 0 ? 4 : 0, round($tk['val'] / $max_bar * 160)) : 0 ?>px">
                        <?php if ($tk['val'] > 0): ?>
                        <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-inverse-surface text-inverse-on-surface text-[10px] px-xs py-[2px] rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
                            <?= number_format($tk['val'], 1) ?> kg
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="text-[10px] font-bold text-on-surface-variant uppercase"><?= $tm['label'] ?></span>
                <span class="text-[9px] text-on-surface-variant/60"><?= date('d/m', strtotime($tm['tgl'])) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ===== TOP PEMASOK & PEDAGANG ===== -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-gutter">

        <!-- Top Pemasok -->
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
            <div class="px-md py-sm flex items-center justify-between border-b border-outline-variant/10">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-primary text-[20px]">local_shipping</span>
                    <h3 class="text-[15px] font-semibold text-on-surface">Top 5 Pemasok</h3>
                </div>
                <a href="pemasok/index.php" class="text-[12px] text-primary hover:underline">Lihat semua →</a>
            </div>
            <div class="p-md space-y-sm">
                <?php
                $rank = 1;
                $max_pemasok = 1;
                $rows_pemasok = [];
                while ($r = mysqli_fetch_assoc($top_pemasok)) {
                    $rows_pemasok[] = $r;
                    if ($r['total_kg'] > $max_pemasok) $max_pemasok = $r['total_kg'];
                }
                foreach ($rows_pemasok as $r):
                    $pct = $max_pemasok > 0 ? round($r['total_kg'] / $max_pemasok * 100) : 0;
                ?>
                <div>
                    <div class="flex justify-between items-center mb-xs">
                        <span class="text-[13px] font-medium text-on-surface flex items-center gap-xs">
                            <span class="text-[11px] font-bold text-primary-container bg-primary-fixed/40 w-5 h-5 rounded flex items-center justify-center"><?= $rank ?></span>
                            <?= htmlspecialchars($r['nama']) ?>
                        </span>
                        <span class="text-[12px] font-bold text-primary"><?= number_format($r['total_kg'], 1) ?> kg</span>
                    </div>
                    <div class="w-full bg-surface-container h-1.5 rounded-full">
                        <div class="h-1.5 bg-primary rounded-full" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php $rank++; endforeach; ?>
                <?php if (empty($rows_pemasok)): ?>
                <p class="text-[13px] text-on-surface-variant text-center py-md">Belum ada data.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top Pedagang -->
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
            <div class="px-md py-sm flex items-center justify-between border-b border-outline-variant/10">
                <div class="flex items-center gap-sm">
                    <span class="material-symbols-outlined text-primary text-[20px]">storefront</span>
                    <h3 class="text-[15px] font-semibold text-on-surface">Top 5 Pedagang</h3>
                </div>
                <a href="pedagang/index.php" class="text-[12px] text-primary hover:underline">Lihat semua →</a>
            </div>
            <div class="p-md space-y-sm">
                <?php
                $rank = 1;
                $max_pedagang = 1;
                $rows_pedagang = [];
                while ($r = mysqli_fetch_assoc($top_pedagang)) {
                    $rows_pedagang[] = $r;
                    if ($r['total_kg'] > $max_pedagang) $max_pedagang = $r['total_kg'];
                }
                foreach ($rows_pedagang as $r):
                    $pct = $max_pedagang > 0 ? round($r['total_kg'] / $max_pedagang * 100) : 0;
                ?>
                <div>
                    <div class="flex justify-between items-center mb-xs">
                        <span class="text-[13px] font-medium text-on-surface flex items-center gap-xs">
                            <span class="text-[11px] font-bold text-primary-container bg-primary-fixed/40 w-5 h-5 rounded flex items-center justify-center"><?= $rank ?></span>
                            <?= htmlspecialchars($r['nama']) ?>
                        </span>
                        <div class="text-right">
                            <p class="text-[12px] font-bold text-primary"><?= number_format($r['total_kg'], 1) ?> kg</p>
                            <p class="text-[10px] text-on-surface-variant">Rp <?= number_format($r['total_omzet'], 0, ',', '.') ?></p>
                        </div>
                    </div>
                    <div class="w-full bg-surface-container h-1.5 rounded-full">
                        <div class="h-1.5 bg-primary rounded-full" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php $rank++; endforeach; ?>
                <?php if (empty($rows_pedagang)): ?>
                <p class="text-[13px] text-on-surface-variant text-center py-md">Belum ada data.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php require_once 'footer.php'; ?>
