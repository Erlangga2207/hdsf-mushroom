<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Barang Keluar';
$active_menu = 'barang_keluar';
$base_path   = '../';

$search = trim($_GET['search'] ?? '');
$where  = '';
if ($search !== '') {
    $s     = mysqli_real_escape_string($koneksi, $search);
    $where = "WHERE pd.nama LIKE '%$s%' OR bk.tgl_kirim LIKE '%$s%' OR p.nama LIKE '%$s%'";
}

$per_page    = 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;

$total_rows  = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS total
     FROM barang_keluar bk
     JOIN pedagang pd ON bk.id_pedagang = pd.id_pedagang
     JOIN stock s     ON bk.id_stock    = s.id_stock
     JOIN barang_masuk bm ON s.id_barang_masuk = bm.id_barang_masuk
     JOIN pemasok p   ON bm.id_pemasok  = p.id_pemasok
     $where"))['total'];
$total_pages = (int)ceil($total_rows / $per_page);

$data = mysqli_query($koneksi,
    "SELECT bk.*, pd.nama AS nama_pedagang,
            p.nama AS nama_pemasok, bm.tgl_setor
     FROM barang_keluar bk
     JOIN pedagang pd    ON bk.id_pedagang   = pd.id_pedagang
     JOIN stock s        ON bk.id_stock      = s.id_stock
     JOIN barang_masuk bm ON s.id_barang_masuk = bm.id_barang_masuk
     JOIN pemasok p      ON bm.id_pemasok    = p.id_pemasok
     $where
     ORDER BY bk.tgl_kirim DESC, bk.id_barang_keluar DESC
     LIMIT $per_page OFFSET $offset");

$summary = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(jamur_putih),0)  AS total_putih,
            COALESCE(SUM(jamur_coklat),0) AS total_coklat,
            COALESCE(SUM(subtotal),0)     AS total_omzet,
            COUNT(*)                      AS total_transaksi
     FROM barang_keluar"));

require_once '../header.php';
?>

<div class="max-w-7xl mx-auto">
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Barang Keluar</span>
    </nav>

    <!-- Summary -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-gutter mb-lg">
        <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Total Transaksi</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= $summary['total_transaksi'] ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">pengiriman</p>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih Keluar</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= number_format($summary['total_putih'], 1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat Keluar</p>
            <p class="text-[28px] font-bold text-tertiary leading-none"><?= number_format($summary['total_coklat'], 1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-primary p-md rounded-xl card-shadow">
            <p class="text-[11px] font-bold uppercase tracking-widest text-primary-fixed-dim mb-xs opacity-80">Total Omzet</p>
            <p class="text-[20px] font-bold text-white leading-none">Rp <?= number_format($summary['total_omzet'], 0, ',', '.') ?></p>
            <p class="text-[12px] text-primary-fixed-dim opacity-70 mt-xs">semua waktu</p>
        </div>
    </div>

    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-sm mb-md">
        <h2 class="text-[22px] font-bold text-on-surface">Semua Transaksi Keluar</h2>
        <a href="tambah.php" class="inline-flex items-center gap-xs bg-primary text-on-primary px-md py-sm rounded-lg font-semibold text-[14px] hover:opacity-90 active:scale-95 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[18px]">outbox</span>
            Tambah Barang Keluar
        </a>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-md">
        <div class="relative max-w-sm">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Cari pedagang, pemasok, tanggal..."
                   class="w-full pl-[44px] pr-md py-sm bg-surface-container-lowest border border-outline-variant/30 rounded-lg text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant/20">
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">ID</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Pedagang</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Sumber Pemasok<br><span class="text-[10px] font-normal normal-case text-on-surface-variant/60">FIFO Batch</span></th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Tgl Kirim</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Jamur Putih</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Jamur Coklat</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Subtotal</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php if (mysqli_num_rows($data) === 0): ?>
                    <tr><td colspan="8" class="px-md py-lg text-center text-on-surface-variant text-[14px]">Belum ada data barang keluar.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = mysqli_fetch_assoc($data)):
                        $ada_harga_p = $row['harga_putih']  !== null && $row['harga_putih']  > 0;
                        $ada_harga_c = $row['harga_coklat'] !== null && $row['harga_coklat'] > 0;
                        $sub_p = $row['jamur_putih']  * ($row['harga_putih']  ?? 0);
                        $sub_c = $row['jamur_coklat'] * ($row['harga_coklat'] ?? 0);
                    ?>
                    <tr class="hover:bg-surface-container-low/60 transition-colors">
                        <td class="px-md py-sm text-[12px] text-on-surface-variant font-medium">#<?= str_pad($row['id_barang_keluar'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td class="px-md py-sm">
                            <span class="text-[14px] font-semibold text-primary"><?= htmlspecialchars($row['nama_pedagang']) ?></span>
                        </td>
                        <td class="px-md py-sm">
                            <p class="text-[13px] font-medium text-on-surface"><?= htmlspecialchars($row['nama_pemasok']) ?></p>
                            <p class="text-[11px] text-on-surface-variant">Masuk: <?= date('d M Y', strtotime($row['tgl_setor'])) ?></p>
                        </td>
                        <td class="px-md py-sm text-[13px] text-on-surface-variant"><?= date('d M Y', strtotime($row['tgl_kirim'])) ?></td>
                        <td class="px-md py-sm text-right">
                            <p class="text-[13px]"><?= number_format($row['jamur_putih'], 2) ?> <span class="text-on-surface-variant text-[12px]">kg</span></p>
                            <?php if ($ada_harga_p): ?>
                            <p class="text-[11px] text-on-surface-variant">@ Rp <?= number_format($row['harga_putih'], 0, ',', '.') ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-md py-sm text-right">
                            <p class="text-[13px]"><?= number_format($row['jamur_coklat'], 2) ?> <span class="text-on-surface-variant text-[12px]">kg</span></p>
                            <?php if ($ada_harga_c): ?>
                            <p class="text-[11px] text-on-surface-variant">@ Rp <?= number_format($row['harga_coklat'], 0, ',', '.') ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-md py-sm text-right">
                            <?php if ($ada_harga_p || $ada_harga_c): ?>
                            <div class="space-y-xs">
                                <?php if ($ada_harga_p && $row['jamur_putih'] > 0): ?>
                                <p class="text-[12px] text-on-surface">Putih: <strong class="text-primary">Rp <?= number_format($sub_p, 0, ',', '.') ?></strong></p>
                                <?php endif; ?>
                                <?php if ($ada_harga_c && $row['jamur_coklat'] > 0): ?>
                                <p class="text-[12px] text-on-surface">Coklat: <strong class="text-tertiary">Rp <?= number_format($sub_c, 0, ',', '.') ?></strong></p>
                                <?php endif; ?>
                                <p class="text-[13px] font-bold text-primary border-t border-outline-variant/20 pt-xs">Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></p>
                            </div>
                            <?php else: ?>
                            <span class="inline-flex items-center gap-xs text-[11px] text-on-surface-variant/60 bg-surface-container px-xs py-[2px] rounded">
                                <span class="material-symbols-outlined text-[12px]">schedule</span>
                                Belum ada harga
                            </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-md py-sm">
                            <div class="flex items-center justify-center gap-sm">
                                <a href="edit.php?id=<?= $row['id_barang_keluar'] ?>" class="p-xs text-on-surface-variant hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                </a>
                                <a href="hapus.php?id=<?= $row['id_barang_keluar'] ?>"
                                   onclick="return confirm('Yakin hapus data barang keluar ini? Stok akan dikembalikan.')"
                                   class="p-xs text-on-surface-variant hover:text-error transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-md py-sm bg-surface-container-low/40 border-t border-outline-variant/10 flex flex-col sm:flex-row justify-between items-center gap-sm">
            <p class="text-[12px] text-on-surface-variant">
                Menampilkan <?= min($offset + 1, max(1,$total_rows)) ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> transaksi
            </p>
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center gap-xs">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"
                   class="w-8 h-8 rounded flex items-center justify-center text-[13px] font-medium transition-colors
                          <?= $i === $page ? 'bg-primary text-on-primary' : 'border border-outline-variant text-on-surface-variant hover:bg-surface-container' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../footer.php'; ?>
