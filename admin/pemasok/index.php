<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Data Pemasok';
$active_menu = 'pemasok';
$base_path   = '../';

// Search
$search = trim($_GET['search'] ?? '');
$where  = '';
if ($search !== '') {
    $s     = mysqli_real_escape_string($koneksi, $search);
    $where = "WHERE nama LIKE '%$s%' OR no_hp LIKE '%$s%' OR alamat LIKE '%$s%'";
}

// Pagination
$per_page    = 10;
$page        = max(1, (int)($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;
$total_rows  = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT COUNT(*) AS total FROM pemasok $where"))['total'];
$total_pages = (int)ceil($total_rows / $per_page);

$data = mysqli_query($koneksi, "SELECT * FROM pemasok $where ORDER BY id_pemasok DESC LIMIT $per_page OFFSET $offset");

require_once '../header.php';
?>

<div class="max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Pemasok</span>
    </nav>

    <!-- Top Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-sm mb-md">
        <div>
            <h2 class="text-[22px] font-bold text-on-surface">Daftar Pemasok</h2>
            <p class="text-[13px] text-on-surface-variant"><?= $total_rows ?> pemasok terdaftar</p>
        </div>
        <a href="tambah.php" class="inline-flex items-center gap-xs bg-primary text-on-primary px-md py-sm rounded-lg font-semibold text-[14px] hover:opacity-90 active:scale-95 transition-all shadow-sm">
            <span class="material-symbols-outlined text-[18px]">person_add</span>
            Tambah Pemasok
        </a>
    </div>

    <!-- Search Bar -->
    <form method="GET" class="mb-md">
        <div class="relative max-w-sm">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Cari nama, HP, alamat..."
                   class="w-full pl-[44px] pr-md py-sm bg-surface-container-lowest border border-outline-variant/30 rounded-lg text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary"/>
        </div>
    </form>

    <!-- Table Card -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant/20">
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant w-16">ID</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Nama Pemasok</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">No HP</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Alamat</th>
                        <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant/10">
                    <?php if (mysqli_num_rows($data) === 0): ?>
                    <tr>
                        <td colspan="5" class="px-md py-lg text-center text-on-surface-variant text-[14px]">
                            <?= $search ? 'Tidak ada hasil untuk "<strong>' . htmlspecialchars($search) . '</strong>"' : 'Belum ada data pemasok.' ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php $no = $offset + 1; while ($row = mysqli_fetch_assoc($data)): ?>
                    <tr class="hover:bg-surface-container-low/60 transition-colors group">
                        <td class="px-md py-sm text-[13px] text-on-surface-variant font-medium">#PM<?= str_pad($row['id_pemasok'], 3, '0', STR_PAD_LEFT) ?></td>
                        <td class="px-md py-sm">
                            <a href="detail.php?id=<?= $row['id_pemasok'] ?>" class="text-[14px] font-semibold text-primary hover:underline">
                                <?= htmlspecialchars($row['nama']) ?>
                            </a>
                        </td>
                        <td class="px-md py-sm text-[13px] text-on-surface-variant"><?= htmlspecialchars($row['no_hp']) ?></td>
                        <td class="px-md py-sm text-[13px] text-on-surface-variant max-w-[200px] truncate" title="<?= htmlspecialchars($row['alamat']) ?>">
                            <?= htmlspecialchars($row['alamat']) ?>
                        </td>
                        <td class="px-md py-sm">
                            <div class="flex items-center justify-center gap-sm">
                                <a href="detail.php?id=<?= $row['id_pemasok'] ?>" title="Detail & Barang Masuk"
                                   class="p-xs text-on-surface-variant hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">open_in_new</span>
                                </a>
                                <a href="edit.php?id=<?= $row['id_pemasok'] ?>" title="Edit"
                                   class="p-xs text-on-surface-variant hover:text-primary transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">edit</span>
                                </a>
                                <a href="hapus.php?id=<?= $row['id_pemasok'] ?>" title="Hapus"
                                   onclick="return confirm('Yakin hapus pemasok \"<?= htmlspecialchars(addslashes($row['nama'])) ?>\"?\nSemua riwayat barang masuk terkait juga akan ikut terhapus.')"
                                   class="p-xs text-on-surface-variant hover:text-error transition-colors">
                                    <span class="material-symbols-outlined text-[20px]">delete</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php $no++; endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-md py-sm bg-surface-container-low/40 border-t border-outline-variant/10 flex flex-col sm:flex-row justify-between items-center gap-sm">
            <p class="text-[12px] text-on-surface-variant">
                Menampilkan <?= min($offset + 1, $total_rows) ?>–<?= min($offset + $per_page, $total_rows) ?> dari <?= $total_rows ?> pemasok
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
