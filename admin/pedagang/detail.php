<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Detail Pedagang';
$active_menu = 'pedagang';
$base_path   = '../';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$pedagang = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM pedagang WHERE id_pedagang = $id"));
if (!$pedagang) {
    $_SESSION['flash_message'] = 'Pedagang tidak ditemukan.';
    $_SESSION['flash_type']    = 'error';
    header('Location: index.php');
    exit;
}

// ===== HANDLE TAMBAH BARANG KELUAR =====
$errors_bk = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tambah_bk') {
    $tgl_kirim    = trim($_POST['tgl_kirim']    ?? '');
    $jamur_putih  = $_POST['jamur_putih']  !== '' ? (float)$_POST['jamur_putih']  : 0;
    $jamur_coklat = $_POST['jamur_coklat'] !== '' ? (float)$_POST['jamur_coklat'] : 0;
    $harga_putih  = $_POST['harga_putih']  !== '' ? (float)$_POST['harga_putih']  : null;
    $harga_coklat = $_POST['harga_coklat'] !== '' ? (float)$_POST['harga_coklat'] : null;

    $stok_cek    = getStokTotal($koneksi);
    $avail_putih  = $stok_cek['putih'];
    $avail_coklat = $stok_cek['coklat'];

    if ($tgl_kirim === '')                    $errors_bk[] = 'Tanggal kirim wajib diisi.';
    if ($jamur_putih + $jamur_coklat <= 0)    $errors_bk[] = 'Minimal satu jenis jamur harus diisi dan lebih dari 0.';
    if ($jamur_putih < 0)                     $errors_bk[] = 'Jumlah jamur putih tidak boleh negatif.';
    if ($jamur_coklat < 0)                    $errors_bk[] = 'Jumlah jamur coklat tidak boleh negatif.';
    if ($jamur_putih  > $avail_putih)         $errors_bk[] = 'Stok jamur putih tidak cukup. Tersedia: ' . number_format($avail_putih, 2) . ' kg.';
    if ($jamur_coklat > $avail_coklat)        $errors_bk[] = 'Stok jamur coklat tidak cukup. Tersedia: ' . number_format($avail_coklat, 2) . ' kg.';
    if ($harga_putih  !== null && $harga_putih  < 0) $errors_bk[] = 'Harga putih tidak boleh negatif.';
    if ($harga_coklat !== null && $harga_coklat < 0) $errors_bk[] = 'Harga coklat tidak boleh negatif.';

    if (empty($errors_bk)) {
        // FIFO multi-batch: potong stok dari batch tertua secara berantai
        $potongan = hitungFifoPotongan($koneksi, $jamur_putih, $jamur_coklat);
        if ($potongan === false) {
            $errors_bk[] = 'Stok tidak mencukupi untuk memenuhi permintaan ini.';
        } else {
            $id_stock_utama = terapkanFifo($koneksi, $potongan);
            $hp_sql = $harga_putih  !== null ? $harga_putih  : 'NULL';
            $hc_sql = $harga_coklat !== null ? $harga_coklat : 'NULL';
            $tgl_e  = mysqli_real_escape_string($koneksi, $tgl_kirim);
            $ok = mysqli_query($koneksi,
                "INSERT INTO barang_keluar (id_stock, id_pedagang, tgl_kirim, jamur_putih, jamur_coklat, harga_putih, harga_coklat)
                 VALUES ($id_stock_utama, $id, '$tgl_e', $jamur_putih, $jamur_coklat, $hp_sql, $hc_sql)");
            if ($ok) {
                $id_bk = mysqli_insert_id($koneksi);
                simpanDetailFifo($koneksi, $id_bk, $potongan);
                $jml_batch = count($potongan);
                $_SESSION['flash_message'] = "Barang keluar disimpan. Stok $jml_batch batch diperbarui (FIFO).";
                $_SESSION['flash_type']    = 'success';
                header("Location: detail.php?id=$id");
                exit;
            } else {
                rollbackFifo($koneksi, $potongan);
                $errors_bk[] = 'Gagal menyimpan: ' . mysqli_error($koneksi);
            }
        }
    }
}

// ===== HANDLE HAPUS BARANG KELUAR =====
if (isset($_GET['hapus_bk'])) {
    $bk_id = (int)$_GET['hapus_bk'];
    // Verifikasi milik pedagang ini
    $cek = mysqli_fetch_assoc(mysqli_query($koneksi,
        "SELECT id_barang_keluar FROM barang_keluar WHERE id_barang_keluar=$bk_id AND id_pedagang=$id"));
    if ($cek) {
        // Rollback FIFO multi-batch
        $detail_fifo = getDetailFifo($koneksi, $bk_id);
        if (!empty($detail_fifo)) {
            rollbackFifo($koneksi, $detail_fifo);
        } else {
            // Fallback single-batch rollback
            $bk_row = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT bk.id_stock, bk.jamur_putih, bk.jamur_coklat, s.sisa_putih, s.sisa_coklat
                 FROM barang_keluar bk JOIN stock s ON bk.id_stock=s.id_stock
                 WHERE bk.id_barang_keluar=$bk_id"));
            if ($bk_row) {
                $rp = round($bk_row['sisa_putih'] + $bk_row['jamur_putih'], 2);
                $rc = round($bk_row['sisa_coklat'] + $bk_row['jamur_coklat'], 2);
                mysqli_query($koneksi,
                    "UPDATE stock SET sisa_putih=$rp, sisa_coklat=$rc WHERE id_stock={$bk_row['id_stock']}");
            }
        }
        mysqli_query($koneksi, "DELETE FROM barang_keluar WHERE id_barang_keluar=$bk_id");
        $_SESSION['flash_message'] = 'Riwayat pengiriman dihapus dan stok dikembalikan ke batch asal.';
        $_SESSION['flash_type']    = 'success';
    }
    header("Location: detail.php?id=$id");
    exit;
}

// ===== DATA =====
$fifo_batches = getFifoBatches($koneksi);
$stok         = getStokTotal($koneksi);
$stok_putih   = $stok['putih'];
$stok_coklat  = $stok['coklat'];

$stats = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*)                                    AS total_transaksi,
            COALESCE(SUM(jamur_putih),0)               AS total_putih,
            COALESCE(SUM(jamur_coklat),0)              AS total_coklat,
            COALESCE(SUM(jamur_putih+jamur_coklat),0)  AS total_kg,
            COALESCE(SUM(subtotal),0)                  AS total_omzet
     FROM barang_keluar WHERE id_pedagang = $id"));

$riwayat = mysqli_query($koneksi,
    "SELECT bk.*, p.nama AS nama_pemasok, bm.tgl_setor
     FROM barang_keluar bk
     JOIN stock s        ON bk.id_stock       = s.id_stock
     JOIN barang_masuk bm ON s.id_barang_masuk = bm.id_barang_masuk
     JOIN pemasok p      ON bm.id_pemasok     = p.id_pemasok
     WHERE bk.id_pedagang = $id
     ORDER BY bk.tgl_kirim DESC, bk.id_barang_keluar DESC");

require_once '../header.php';
?>

<div class="max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <a href="index.php" class="hover:text-primary">Pedagang</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium"><?= htmlspecialchars($pedagang['nama']) ?></span>
    </nav>

    <!-- Profil -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg mb-lg flex flex-col sm:flex-row items-start sm:items-center gap-md">
        <div class="w-16 h-16 bg-primary rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-white" style="font-size:32px">storefront</span>
        </div>
        <div class="flex-1">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Profil Pedagang · PDG-<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></p>
            <h1 class="text-[26px] font-bold text-on-surface"><?= htmlspecialchars($pedagang['nama']) ?></h1>
            <div class="flex flex-wrap gap-md mt-xs text-[13px] text-on-surface-variant">
                <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">call</span><?= htmlspecialchars($pedagang['no_hp']) ?></span>
                <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">location_on</span><?= htmlspecialchars($pedagang['alamat']) ?></span>
            </div>
        </div>
        <a href="edit.php?id=<?= $id ?>" class="flex items-center gap-xs px-md py-sm border border-outline-variant text-on-surface rounded-lg text-[13px] font-medium hover:bg-surface-container transition-colors shrink-0">
            <span class="material-symbols-outlined text-[18px]">edit</span> Edit
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-gutter mb-lg">
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Total Transaksi</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= $stats['total_transaksi'] ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">pengiriman</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= number_format($stats['total_putih'], 1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat</p>
            <p class="text-[28px] font-bold text-tertiary leading-none"><?= number_format($stats['total_coklat'], 1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-primary rounded-xl card-shadow p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-primary-fixed-dim mb-xs opacity-80">Total Omzet</p>
            <p class="text-[18px] font-bold text-white leading-none">Rp <?= number_format($stats['total_omzet'], 0, ',', '.') ?></p>
            <p class="text-[12px] text-primary-fixed-dim opacity-70 mt-xs"><?= number_format($stats['total_kg'], 1) ?> kg dikirim</p>
        </div>
    </div>

    <!-- FIFO Widget -->
    <?php require_once '../fifo_widget.php'; ?>

    <!-- Form + Riwayat -->
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-gutter">

        <!-- Form -->
        <div class="lg:col-span-2">
            <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg sticky top-20">
                <div class="flex items-center gap-sm mb-md border-b border-outline-variant/10 pb-sm">
                    <span class="material-symbols-outlined text-primary">outbox</span>
                    <h3 class="text-[16px] font-semibold text-on-surface">Input Barang Keluar</h3>
                </div>

                <?php if (!empty($errors_bk)): ?>
                <div class="mb-md p-sm rounded-lg bg-error-container border border-error/20 text-on-error-container text-[13px]">
                    <?php foreach ($errors_bk as $e): ?>
                    <p class="flex gap-xs items-start"><span class="material-symbols-outlined text-[14px] mt-[2px]">error</span><?= htmlspecialchars($e) ?></p>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-md">
                    <input type="hidden" name="action" value="tambah_bk"/>

                    <!-- Tanggal -->
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Tanggal Kirim <span class="text-error">*</span></label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">calendar_today</span>
                            <input type="date" name="tgl_kirim"
                                   value="<?= isset($_POST['tgl_kirim']) ? htmlspecialchars($_POST['tgl_kirim']) : date('Y-m-d') ?>"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                        </div>
                    </div>

                    <!-- Jumlah Jamur -->
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-sm">Jumlah Jamur (kg)</p>
                        <div class="grid grid-cols-2 gap-sm">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">
                                    Putih <span class="text-[9px] font-normal">max: <?= number_format($stok_putih,1) ?></span>
                                </label>
                                <div class="relative">
                                    <input type="number" name="jamur_putih"
                                           value="<?= isset($_POST['jamur_putih']) ? htmlspecialchars($_POST['jamur_putih']) : '' ?>"
                                           min="0" step="0.01" max="<?= $stok_putih ?>" placeholder="0.00"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[28px] text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                                           id="jp_input"/>
                                    <span class="absolute right-xs top-1/2 -translate-y-1/2 text-[10px] text-on-surface-variant">kg</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">
                                    Coklat <span class="text-[9px] font-normal">max: <?= number_format($stok_coklat,1) ?></span>
                                </label>
                                <div class="relative">
                                    <input type="number" name="jamur_coklat"
                                           value="<?= isset($_POST['jamur_coklat']) ? htmlspecialchars($_POST['jamur_coklat']) : '' ?>"
                                           min="0" step="0.01" max="<?= $stok_coklat ?>" placeholder="0.00"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[28px] text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                                           id="jc_input"/>
                                    <span class="absolute right-xs top-1/2 -translate-y-1/2 text-[10px] text-on-surface-variant">kg</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Harga per kg (opsional, terpisah) -->
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs flex items-center gap-xs">
                            Harga per kg
                            <span class="text-[10px] font-normal normal-case text-on-surface-variant/60">(opsional)</span>
                        </p>
                        <div class="grid grid-cols-2 gap-sm">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">Putih</label>
                                <div class="relative">
                                    <span class="absolute left-xs top-1/2 -translate-y-1/2 text-on-surface-variant text-[11px] font-medium">Rp</span>
                                    <input type="number" name="harga_putih"
                                           value="<?= isset($_POST['harga_putih']) ? htmlspecialchars($_POST['harga_putih']) : '' ?>"
                                           min="0" step="100" placeholder="15000"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[32px] pr-xs py-sm text-[13px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                                           id="hp_input"/>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">Coklat</label>
                                <div class="relative">
                                    <span class="absolute left-xs top-1/2 -translate-y-1/2 text-on-surface-variant text-[11px] font-medium">Rp</span>
                                    <input type="number" name="harga_coklat"
                                           value="<?= isset($_POST['harga_coklat']) ? htmlspecialchars($_POST['harga_coklat']) : '' ?>"
                                           min="0" step="100" placeholder="12000"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[32px] pr-xs py-sm text-[13px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"
                                           id="hc_input"/>
                                </div>
                            </div>
                        </div>
                        <p class="text-[10px] text-on-surface-variant mt-xs flex items-center gap-xs">
                            <span class="material-symbols-outlined text-[12px]">info</span>
                            Subtotal muncul di riwayat jika harga diisi.
                        </p>
                    </div>

                    <!-- Preview -->
                    <div class="p-sm bg-primary-fixed/30 rounded-lg border border-primary/10" id="preview-box" style="display:none">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-primary-fixed-variant mb-sm">Preview Kalkulasi</p>
                        <div class="space-y-xs text-[12px]">
                            <div class="flex justify-between"><span class="text-on-surface-variant">Subtotal Putih</span><strong class="text-primary" id="prev-putih">Rp 0</strong></div>
                            <div class="flex justify-between"><span class="text-on-surface-variant">Subtotal Coklat</span><strong class="text-tertiary" id="prev-coklat">Rp 0</strong></div>
                            <div class="flex justify-between border-t border-primary/20 pt-xs"><span class="font-bold text-on-surface">Grand Total</span><strong class="text-primary text-[14px]" id="prev-total">Rp 0</strong></div>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full bg-primary text-on-primary font-semibold py-sm rounded-lg flex items-center justify-center gap-xs hover:opacity-90 active:scale-95 transition-all text-[14px]">
                        <span class="material-symbols-outlined text-[18px]">save</span>
                        Simpan Pengiriman
                    </button>
                </form>
            </div>
        </div>

        <!-- Riwayat -->
        <div class="lg:col-span-3">
            <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
                <div class="px-md py-sm flex items-center justify-between border-b border-outline-variant/10">
                    <div class="flex items-center gap-sm">
                        <span class="material-symbols-outlined text-primary text-[20px]">history</span>
                        <h3 class="text-[16px] font-semibold text-on-surface">Riwayat Pengiriman</h3>
                    </div>
                    <span class="text-[12px] text-on-surface-variant bg-surface-container px-sm py-xs rounded-full">
                        <?= $stats['total_transaksi'] ?> transaksi
                    </span>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-outline-variant/10 bg-surface-container-low/50">
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Tanggal</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Sumber<br><span class="text-[10px] font-normal">Pemasok FIFO</span></th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Putih</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Coklat</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Subtotal</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            <?php if (mysqli_num_rows($riwayat) === 0): ?>
                            <tr>
                                <td colspan="6" class="px-md py-lg text-center">
                                    <div class="flex flex-col items-center gap-sm text-on-surface-variant">
                                        <span class="material-symbols-outlined text-[40px] opacity-30">outbox</span>
                                        <p class="text-[13px]">Belum ada riwayat pengiriman.</p>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <?php while ($row = mysqli_fetch_assoc($riwayat)):
                                $ada_p   = $row['harga_putih']  !== null && $row['harga_putih']  > 0;
                                $ada_c   = $row['harga_coklat'] !== null && $row['harga_coklat'] > 0;
                                $sub_p   = $row['jamur_putih']  * ($row['harga_putih']  ?? 0);
                                $sub_c   = $row['jamur_coklat'] * ($row['harga_coklat'] ?? 0);
                                $ada_subtotal = $ada_p || $ada_c;
                            ?>
                            <tr class="hover:bg-surface-container-low/50 transition-colors">
                                <!-- Tanggal -->
                                <td class="px-md py-sm">
                                    <p class="text-[13px] font-semibold text-on-surface"><?= date('d M Y', strtotime($row['tgl_kirim'])) ?></p>
                                    <p class="text-[11px] text-on-surface-variant"><?= number_format($row['jamur_putih'] + $row['jamur_coklat'], 2) ?> kg</p>
                                </td>

                                <!-- Sumber Pemasok FIFO -->
                                <td class="px-md py-sm">
                                    <p class="text-[12px] font-medium text-on-surface"><?= htmlspecialchars($row['nama_pemasok']) ?></p>
                                    <p class="text-[10px] text-on-surface-variant">Masuk: <?= date('d M Y', strtotime($row['tgl_setor'])) ?></p>
                                </td>

                                <!-- Jamur Putih -->
                                <td class="px-md py-sm text-right">
                                    <?php if ($row['jamur_putih'] > 0): ?>
                                    <p class="text-[13px] font-medium"><?= number_format($row['jamur_putih'], 2) ?> <span class="text-[11px] text-on-surface-variant">kg</span></p>
                                    <?php if ($ada_p): ?>
                                    <p class="text-[10px] text-on-surface-variant">@ Rp <?= number_format($row['harga_putih'], 0, ',', '.') ?></p>
                                    <?php endif; ?>
                                    <?php else: ?><span class="text-[12px] text-on-surface-variant/40">—</span><?php endif; ?>
                                </td>

                                <!-- Jamur Coklat -->
                                <td class="px-md py-sm text-right">
                                    <?php if ($row['jamur_coklat'] > 0): ?>
                                    <p class="text-[13px] font-medium"><?= number_format($row['jamur_coklat'], 2) ?> <span class="text-[11px] text-on-surface-variant">kg</span></p>
                                    <?php if ($ada_c): ?>
                                    <p class="text-[10px] text-on-surface-variant">@ Rp <?= number_format($row['harga_coklat'], 0, ',', '.') ?></p>
                                    <?php endif; ?>
                                    <?php else: ?><span class="text-[12px] text-on-surface-variant/40">—</span><?php endif; ?>
                                </td>

                                <!-- Subtotal per jenis -->
                                <td class="px-md py-sm text-right">
                                    <?php if ($ada_subtotal): ?>
                                    <div class="space-y-xs">
                                        <?php if ($ada_p && $row['jamur_putih'] > 0): ?>
                                        <p class="text-[11px]">Putih: <strong class="text-primary">Rp <?= number_format($sub_p, 0, ',', '.') ?></strong></p>
                                        <?php endif; ?>
                                        <?php if ($ada_c && $row['jamur_coklat'] > 0): ?>
                                        <p class="text-[11px]">Coklat: <strong class="text-tertiary">Rp <?= number_format($sub_c, 0, ',', '.') ?></strong></p>
                                        <?php endif; ?>
                                        <p class="text-[12px] font-bold text-primary border-t border-outline-variant/20 pt-xs">
                                            Rp <?= number_format($row['subtotal'], 0, ',', '.') ?>
                                        </p>
                                    </div>
                                    <?php else: ?>
                                    <span class="inline-flex items-center gap-xs text-[10px] text-on-surface-variant/60 bg-surface-container px-xs py-[2px] rounded">
                                        <span class="material-symbols-outlined text-[11px]">schedule</span>
                                        Belum ada harga
                                    </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Aksi -->
                                <td class="px-md py-sm">
                                    <div class="flex items-center justify-center gap-xs">
                                        <a href="../barang_keluar/edit.php?id=<?= $row['id_barang_keluar'] ?>"
                                           class="p-xs text-on-surface-variant hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </a>
                                        <a href="?id=<?= $id ?>&hapus_bk=<?= $row['id_barang_keluar'] ?>"
                                           onclick="return confirm('Hapus riwayat ini? Stok akan dikembalikan otomatis.')"
                                           class="p-xs text-on-surface-variant hover:text-error transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>

                        <?php if ($stats['total_transaksi'] > 0): ?>
                        <tfoot>
                            <tr class="bg-surface-container-low border-t border-outline-variant/20">
                                <td colspan="3" class="px-md py-sm text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">Total Keseluruhan</td>
                                <td class="px-md py-sm text-right">
                                    <p class="text-[12px] font-bold"><?= number_format($stats['total_kg'], 2) ?> kg</p>
                                </td>
                                <td class="px-md py-sm text-right">
                                    <?php if ($stats['total_omzet'] > 0): ?>
                                    <p class="text-[13px] font-bold text-primary">Rp <?= number_format($stats['total_omzet'], 0, ',', '.') ?></p>
                                    <?php else: ?><span class="text-[12px] text-on-surface-variant/40">—</span><?php endif; ?>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const jp=document.getElementById('jp_input'), jc=document.getElementById('jc_input');
    const hp=document.getElementById('hp_input'), hc=document.getElementById('hc_input');
    const box=document.getElementById('preview-box');
    const fmt=n=>'Rp '+Math.round(n).toLocaleString('id-ID');
    function update(){
        const jpv=parseFloat(jp?.value)||0, jcv=parseFloat(jc?.value)||0;
        const hpv=parseFloat(hp?.value)||0, hcv=parseFloat(hc?.value)||0;
        const sp=jpv*hpv, sc=jcv*hcv;
        if((hpv>0&&jpv>0)||(hcv>0&&jcv>0)){
            document.getElementById('prev-putih').textContent=fmt(sp);
            document.getElementById('prev-coklat').textContent=fmt(sc);
            document.getElementById('prev-total').textContent=fmt(sp+sc);
            box.style.display='block';
        } else { box.style.display='none'; }
    }
    [jp,jc,hp,hc].forEach(el=>el&&el.addEventListener('input',update));
})();
</script>

<?php require_once '../footer.php'; ?>
