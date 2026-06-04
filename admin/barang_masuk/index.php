<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Barang Masuk';
$active_menu = 'barang_masuk';
$base_path   = '../';

$search = trim($_GET['search'] ?? '');
$where  = '';
if ($search !== '') {
    $s     = mysqli_real_escape_string($koneksi, $search);
    $where = "WHERE p.nama LIKE '%$s%' OR bm.tgl_setor LIKE '%$s%'";
}

$per_page    = 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;

$total_rows  = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS total FROM barang_masuk bm JOIN pemasok p ON bm.id_pemasok=p.id_pemasok $where"))['total'];
$total_pages = (int)ceil($total_rows / $per_page);

$data = mysqli_query($koneksi,
    "SELECT bm.*, p.nama AS nama_pemasok
     FROM barang_masuk bm
     JOIN pemasok p ON bm.id_pemasok = p.id_pemasok
     $where
     ORDER BY bm.tgl_setor DESC, bm.id_barang_masuk DESC
     LIMIT $per_page OFFSET $offset");

// Summary
$summary = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COALESCE(SUM(jamur_putih),0) AS total_putih,
            COALESCE(SUM(jamur_coklat),0) AS total_coklat,
            COALESCE(SUM(jumlah_barang),0) AS grand_total,
            COUNT(*) AS total_transaksi
     FROM barang_masuk"));

require_once '../header.php';
?>

<div class="max-w-6xl mx-auto">
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Barang Masuk</span>
    </nav>

    <!-- Summary Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-gutter mb-lg">
        <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Total Transaksi</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= $summary['total_transaksi'] ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">setoran masuk</p>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= number_format($summary['total_putih'], 1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-surface-container-lowest p-md rounded-xl card-shadow border border-outline-variant/10">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat</p>
            <p class="text-[28px] font-bold text-tertiary leading-none"><?= number_format($summary['total_coklat'], 1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-primary p-md rounded-xl card-shadow">
            <p class="text-[11px] font-bold uppercase tracking-widest text-primary-fixed-dim mb-xs opacity-80">Grand Total</p>
            <p class="text-[28px] font-bold text-white leading-none"><?= number_format($summary['grand_total'], 1) ?></p>
            <p class="text-[12px] text-primary-fixed-dim opacity-70 mt-xs">kg semua waktu</p>
        </div>
    </div>

    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-sm mb-md">
        <h2 class="text-[22px] font-bold text-on-surface">Semua Transaksi Masuk</h2>
        <a href="tambah.php" class="inline-flex items-center gap-xs bg-primary text-on-primary px-md py-sm rounded-lg font-semibold text-[14px] hover:opacity-90 active:scale-95 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[18px]">add_box</span>
            Tambah Barang Masuk
        </a>
    </div>

    <!-- Search -->
    <form method="GET" class="mb-md">
        <div class="relative max-w-sm">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Cari nama pemasok atau tanggal..."
                   class="w-full pl-[44px] pr-md py-sm bg-surface-container-lowest border border-outline-variant/30 rounded-lg text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant/20">
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant w-16">ID</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Pemasok</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Tanggal Masuk</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Jamur Putih</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Jamur Coklat</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Total</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php if (mysqli_num_rows($data) === 0): ?>
                    <tr><td colspan="7" class="px-md py-lg text-center text-on-surface-variant text-[14px]">Belum ada data barang masuk.</td></tr>
                    <?php endif; ?>
                    <?php while ($row = mysqli_fetch_assoc($data)): ?>
                    <tr class="hover:bg-surface-container-low/60 transition-colors">
                        <td class="px-md py-sm text-[12px] text-on-surface-variant font-medium">#<?= str_pad($row['id_barang_masuk'], 4, '0', STR_PAD_LEFT) ?></td>
                        <td class="px-md py-sm">
                            <a href="../pemasok/detail.php?id=<?= $row['id_pemasok'] ?>" class="text-[14px] font-semibold text-primary hover:underline">
                                <?= htmlspecialchars($row['nama_pemasok']) ?>
                            </a>
                        </td>
                        <td class="px-md py-sm text-[13px] text-on-surface-variant"><?= date('d M Y', strtotime($row['tgl_setor'])) ?></td>
                        <td class="px-md py-sm text-[13px] text-right"><?= number_format($row['jamur_putih'], 2) ?> <span class="text-on-surface-variant text-[12px]">kg</span></td>
                        <td class="px-md py-sm text-[13px] text-right"><?= number_format($row['jamur_coklat'], 2) ?> <span class="text-on-surface-variant text-[12px]">kg</span></td>
                        <td class="px-md py-sm text-[13px] font-bold text-primary text-right"><?= number_format($row['jumlah_barang'], 2) ?> <span class="text-on-surface-variant text-[12px] font-normal">kg</span></td>
                        <td class="px-md py-sm">
                            <div class="flex items-center justify-center gap-sm">
                                <a href="edit.php?id=<?= $row['id_barang_masuk'] ?>" class="p-xs text-on-surface-variant hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                </a>
                                <a href="hapus.php?id=<?= $row['id_barang_masuk'] ?>"
                                   onclick="return confirm('Yakin hapus data barang masuk ini?')"
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

        <div class="px-md py-sm bg-surface-container-low/40 border-t border-outline-variant/10 flex flex-col sm:flex-row justify-between items-center gap-sm">
            <p class="text-[12px] text-on-surface-variant">
                Menampilkan <?= min($offset + 1, $total_rows) ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> transaksi
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
