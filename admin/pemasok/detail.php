<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Detail Pemasok';
$active_menu = 'pemasok';
$base_path   = '../';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

// ===== FILTER TANGGAL =====
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');

$date_where = '';
if ($date_from !== '' && $date_to !== '') {
    $df = mysqli_real_escape_string($koneksi, $date_from);
    $dt = mysqli_real_escape_string($koneksi, $date_to);
    $date_where = " AND bm.tgl_setor BETWEEN '$df' AND '$dt'";
} elseif ($date_from !== '') {
    $df = mysqli_real_escape_string($koneksi, $date_from);
    $date_where = " AND bm.tgl_setor >= '$df'";
} elseif ($date_to !== '') {
    $dt = mysqli_real_escape_string($koneksi, $date_to);
    $date_where = " AND bm.tgl_setor <= '$dt'";
}
$is_filtered = $date_where !== '';

$pemasok = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM pemasok WHERE id_pemasok=$id"));
if (!$pemasok) {
    $_SESSION['flash_message'] = 'Pemasok tidak ditemukan.';
    $_SESSION['flash_type']    = 'error';
    header('Location: index.php'); exit;
}

// ===== HANDLE TAMBAH BARANG MASUK =====
$errors_bm = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'tambah_bm') {
    $tgl_setor        = trim($_POST['tgl_setor']         ?? '');
    $jamur_putih      = (float)($_POST['jamur_putih']    ?? 0);
    $jamur_coklat     = (float)($_POST['jamur_coklat']   ?? 0);
    $harga_beli_putih  = trim($_POST['harga_beli_putih']  ?? '');
    $harga_beli_coklat = trim($_POST['harga_beli_coklat'] ?? '');
    $hbp = $harga_beli_putih  !== '' ? (float)$harga_beli_putih  : null;
    $hbc = $harga_beli_coklat !== '' ? (float)$harga_beli_coklat : null;

    if ($tgl_setor === '')               $errors_bm[] = 'Tanggal masuk wajib diisi.';
    if ($jamur_putih + $jamur_coklat <= 0) $errors_bm[] = 'Total jamur harus lebih dari 0 kg.';
    if ($jamur_putih  < 0)               $errors_bm[] = 'Jumlah jamur putih tidak boleh negatif.';
    if ($jamur_coklat < 0)               $errors_bm[] = 'Jumlah jamur coklat tidak boleh negatif.';

    if (empty($errors_bm)) {
        $hbp_sql = $hbp !== null ? $hbp : 'NULL';
        $hbc_sql = $hbc !== null ? $hbc : 'NULL';
        $tgl_e   = mysqli_real_escape_string($koneksi, $tgl_setor);
        $ok = mysqli_query($koneksi,
            "INSERT INTO barang_masuk (id_pemasok,tgl_setor,jamur_putih,jamur_coklat,harga_beli_putih,harga_beli_coklat)
             VALUES ($id,'$tgl_e',$jamur_putih,$jamur_coklat,$hbp_sql,$hbc_sql)");
        if ($ok) {
            $new_bm_id = mysqli_insert_id($koneksi);
            mysqli_query($koneksi,
                "INSERT INTO stock (id_barang_masuk,jumlah_jamur_putih,jumlah_jamur_coklat,sisa_putih,sisa_coklat)
                 VALUES ($new_bm_id,$jamur_putih,$jamur_coklat,$jamur_putih,$jamur_coklat)");
            $_SESSION['flash_message'] = 'Data barang masuk berhasil disimpan.';
            $_SESSION['flash_type']    = 'success';
            header("Location: detail.php?id=$id"); exit;
        } else {
            $errors_bm[] = 'Gagal menyimpan: '.mysqli_error($koneksi);
        }
    }
}

// ===== HANDLE HAPUS BARANG MASUK =====
if (isset($_GET['hapus_bm'])) {
    $bm_id = (int)$_GET['hapus_bm'];
    mysqli_query($koneksi, "DELETE FROM barang_masuk WHERE id_barang_masuk=$bm_id AND id_pemasok=$id");
    $_SESSION['flash_message'] = 'Riwayat setoran berhasil dihapus.';
    $_SESSION['flash_type']    = 'success';
    header("Location: detail.php?id=$id"); exit;
}

// ===== DATA STATISTIK PEMASOK =====
$stats = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS total_setor,
            COALESCE(SUM(bm.jamur_putih),0)   AS total_putih,
            COALESCE(SUM(bm.jamur_coklat),0)  AS total_coklat,
            COALESCE(SUM(bm.jumlah_barang),0) AS grand_total,
            -- Pendapatan realtime: dari kg yang sudah terjual saja
            COALESCE(SUM(
                COALESCE((
                    SELECT SUM(bkd.potong_putih)
                    FROM barang_keluar_detail bkd
                    WHERE bkd.id_stock = s.id_stock
                ), 0) * COALESCE(bm.harga_beli_putih, 0) +
                COALESCE((
                    SELECT SUM(bkd.potong_coklat)
                    FROM barang_keluar_detail bkd
                    WHERE bkd.id_stock = s.id_stock
                ), 0) * COALESCE(bm.harga_beli_coklat, 0)
            ), 0) AS total_pendapatan
     FROM barang_masuk bm
     LEFT JOIN stock s ON s.id_barang_masuk = bm.id_barang_masuk
     WHERE bm.id_pemasok = $id"));

$riwayat = mysqli_query($koneksi,
    "SELECT bm.*, s.sisa_putih, s.sisa_coklat,
            COALESCE((
                SELECT SUM(bkd.potong_putih)
                FROM barang_keluar_detail bkd
                WHERE bkd.id_stock = s.id_stock
            ), 0) AS terjual_putih,
            COALESCE((
                SELECT SUM(bkd.potong_coklat)
                FROM barang_keluar_detail bkd
                WHERE bkd.id_stock = s.id_stock
            ), 0) AS terjual_coklat
     FROM barang_masuk bm
     LEFT JOIN stock s ON s.id_barang_masuk = bm.id_barang_masuk
     WHERE bm.id_pemasok = $id $date_where
     ORDER BY bm.tgl_setor DESC, bm.id_barang_masuk DESC");

// Stats untuk rentang yang difilter (dipakai di footer tabel)
$filtered_stats = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT COUNT(*) AS total_setor,
            COALESCE(SUM(bm.jumlah_barang),0) AS grand_total,
            COALESCE(SUM(
                COALESCE((
                    SELECT SUM(bkd.potong_putih)
                    FROM barang_keluar_detail bkd
                    WHERE bkd.id_stock = s.id_stock
                ), 0) * COALESCE(bm.harga_beli_putih, 0) +
                COALESCE((
                    SELECT SUM(bkd.potong_coklat)
                    FROM barang_keluar_detail bkd
                    WHERE bkd.id_stock = s.id_stock
                ), 0) * COALESCE(bm.harga_beli_coklat, 0)
            ), 0) AS total_pendapatan
     FROM barang_masuk bm
     LEFT JOIN stock s ON s.id_barang_masuk = bm.id_barang_masuk
     WHERE bm.id_pemasok = $id $date_where"));

require_once '../header.php';
?>

<div class="max-w-6xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <a href="index.php" class="hover:text-primary">Pemasok</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium"><?= htmlspecialchars($pemasok['nama']) ?></span>
    </nav>

    <!-- Profil -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg mb-lg flex flex-col sm:flex-row items-start sm:items-center gap-md">
        <div class="w-16 h-16 bg-primary rounded-xl flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-white" style="font-size:32px">person</span>
        </div>
        <div class="flex-1">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Profil Pemasok · #PM<?= str_pad($id,3,'0',STR_PAD_LEFT) ?></p>
            <h1 class="text-[26px] font-bold text-on-surface"><?= htmlspecialchars($pemasok['nama']) ?></h1>
            <div class="flex flex-wrap gap-md mt-xs text-[13px] text-on-surface-variant">
                <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">call</span><?= htmlspecialchars($pemasok['no_hp']) ?></span>
                <span class="flex items-center gap-xs"><span class="material-symbols-outlined text-[16px]">location_on</span><?= htmlspecialchars($pemasok['alamat']) ?></span>
            </div>
        </div>
        <a href="edit.php?id=<?= $id ?>" class="flex items-center gap-xs px-md py-sm border border-outline-variant text-on-surface rounded-lg text-[13px] font-medium hover:bg-surface-container transition-colors shrink-0">
            <span class="material-symbols-outlined text-[18px]">edit</span> Edit
        </a>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-gutter mb-lg">
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Total Setoran</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= $stats['total_setor'] ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kali setor</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih</p>
            <p class="text-[28px] font-bold text-primary leading-none"><?= number_format($stats['total_putih'],1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat</p>
            <p class="text-[28px] font-bold text-tertiary leading-none"><?= number_format($stats['total_coklat'],1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg total</p>
        </div>
        <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Grand Total</p>
            <p class="text-[28px] font-bold text-on-surface leading-none"><?= number_format($stats['grand_total'],1) ?></p>
            <p class="text-[12px] text-on-surface-variant mt-xs">kg semua waktu</p>
        </div>
        <div class="bg-primary rounded-xl card-shadow p-md text-center">
            <p class="text-[11px] font-bold uppercase tracking-widest text-primary-fixed-dim mb-xs opacity-80">Total Pendapatan</p>
            <p class="text-[18px] font-bold text-white leading-none">Rp <?= number_format($stats['total_pendapatan'],0,',','.') ?></p>
            <p class="text-[12px] text-primary-fixed-dim opacity-70 mt-xs">dari semua setoran</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-gutter">
        <!-- Form Input Barang Masuk -->
        <div class="lg:col-span-2">
            <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg sticky top-20">
                <div class="flex items-center gap-sm mb-md border-b border-outline-variant/10 pb-sm">
                    <span class="material-symbols-outlined text-primary">add_box</span>
                    <h3 class="text-[16px] font-semibold text-on-surface">Input Barang Masuk</h3>
                </div>

                <?php if (!empty($errors_bm)): ?>
                <div class="mb-md p-sm rounded-lg bg-error-container border border-error/20 text-on-error-container text-[13px]">
                    <?php foreach ($errors_bm as $e): ?><p class="flex gap-xs items-start"><span class="material-symbols-outlined text-[14px] mt-[2px]">error</span><?= htmlspecialchars($e) ?></p><?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-md">
                    <input type="hidden" name="action" value="tambah_bm"/>

                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Tanggal Masuk <span class="text-error">*</span></label>
                        <div class="relative">
                            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[18px]">calendar_today</span>
                            <input type="date" name="tgl_setor" value="<?= isset($_POST['tgl_setor']) ? htmlspecialchars($_POST['tgl_setor']) : date('Y-m-d') ?>"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-sm">Jumlah Jamur (kg)</p>
                        <div class="grid grid-cols-2 gap-sm">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">Putih</label>
                                <div class="relative">
                                    <input type="number" name="jamur_putih" value="<?= isset($_POST['jamur_putih']) ? htmlspecialchars($_POST['jamur_putih']) : '0' ?>"
                                           min="0" step="0.01" placeholder="0.00"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[28px] text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jp_input"/>
                                    <span class="absolute right-xs top-1/2 -translate-y-1/2 text-[10px] text-on-surface-variant">kg</span>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">Coklat</label>
                                <div class="relative">
                                    <input type="number" name="jamur_coklat" value="<?= isset($_POST['jamur_coklat']) ? htmlspecialchars($_POST['jamur_coklat']) : '0' ?>"
                                           min="0" step="0.01" placeholder="0.00"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[28px] text-[14px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jc_input"/>
                                    <span class="absolute right-xs top-1/2 -translate-y-1/2 text-[10px] text-on-surface-variant">kg</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Harga Beli -->
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs flex items-center gap-xs">
                            Harga Beli <span class="text-[10px] font-normal normal-case text-on-surface-variant/60">(opsional)</span>
                        </p>
                        <div class="grid grid-cols-2 gap-sm">
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">Putih/kg</label>
                                <div class="relative">
                                    <span class="absolute left-xs top-1/2 -translate-y-1/2 text-on-surface-variant text-[11px] font-medium">Rp</span>
                                    <input type="number" name="harga_beli_putih" value="<?= isset($_POST['harga_beli_putih']) ? htmlspecialchars($_POST['harga_beli_putih']) : '' ?>"
                                           min="0" step="100" placeholder="10000"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[32px] pr-xs py-sm text-[13px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hbp_input"/>
                                </div>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold uppercase tracking-widest text-on-surface-variant/70 mb-xs">Coklat/kg</label>
                                <div class="relative">
                                    <span class="absolute left-xs top-1/2 -translate-y-1/2 text-on-surface-variant text-[11px] font-medium">Rp</span>
                                    <input type="number" name="harga_beli_coklat" value="<?= isset($_POST['harga_beli_coklat']) ? htmlspecialchars($_POST['harga_beli_coklat']) : '' ?>"
                                           min="0" step="100" placeholder="15000"
                                           class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[32px] pr-xs py-sm text-[13px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hbc_input"/>
                                </div>
                            </div>
                        </div>
                        <p class="text-[10px] text-on-surface-variant mt-xs flex items-center gap-xs">
                            <span class="material-symbols-outlined text-[12px]">info</span>
                            Pendapatan dihitung otomatis: (kg × harga beli)
                        </p>
                    </div>

                    <!-- Preview pendapatan -->
                    <div class="p-sm bg-primary-fixed/30 rounded-lg border border-primary/10" id="preview-box" style="display:none">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-on-primary-fixed-variant mb-sm">Preview Pendapatan</p>
                        <div class="space-y-xs text-[12px]">
                            <div class="flex justify-between"><span class="text-on-surface-variant">Putih</span><strong class="text-primary" id="prev-putih">Rp 0</strong></div>
                            <div class="flex justify-between"><span class="text-on-surface-variant">Coklat</span><strong class="text-tertiary" id="prev-coklat">Rp 0</strong></div>
                            <div class="flex justify-between border-t border-primary/20 pt-xs"><span class="font-bold">Total</span><strong class="text-primary text-[14px]" id="prev-total">Rp 0</strong></div>
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-primary text-on-primary font-semibold py-sm rounded-lg flex items-center justify-center gap-xs hover:opacity-90 active:scale-95 transition-all text-[14px]">
                        <span class="material-symbols-outlined text-[18px]">save</span> Simpan Laporan
                    </button>
                </form>
            </div>
        </div>

        <!-- Riwayat Setoran -->
        <div class="lg:col-span-3">
            <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden">
                <div class="px-md py-sm flex items-center justify-between border-b border-outline-variant/10">
                    <div class="flex items-center gap-sm">
                        <span class="material-symbols-outlined text-primary text-[20px]">history</span>
                        <h3 class="text-[16px] font-semibold text-on-surface">Riwayat Setoran</h3>
                    </div>
                    <div class="flex items-center gap-xs">
                        <?php if ($is_filtered): ?>
                        <span class="text-[11px] text-primary bg-primary/10 px-xs py-[2px] rounded-full flex items-center gap-xs">
                            <span class="material-symbols-outlined text-[12px]">filter_alt</span>Filter aktif
                        </span>
                        <?php endif; ?>
                        <span class="text-[12px] text-on-surface-variant bg-surface-container px-sm py-xs rounded-full"><?= $filtered_stats['total_setor'] ?> setoran</span>
                    </div>
                </div>

                <!-- Filter Tanggal -->
                <form method="GET" id="filter-form" class="px-md py-sm border-b border-outline-variant/10 bg-surface-container-low/40">
                    <input type="hidden" name="id" value="<?= $id ?>"/>
                    <div class="flex flex-wrap items-center gap-sm">
                        <!-- Preset buttons -->
                        <div class="flex gap-xs flex-wrap">
                            <button type="button" onclick="setPreset('all')"
                                class="preset-btn px-sm py-[4px] rounded-full text-[11px] font-medium border transition-colors <?= !$is_filtered ? 'bg-primary text-on-primary border-primary' : 'border-outline-variant text-on-surface-variant hover:border-primary hover:text-primary' ?>">
                                Semua
                            </button>
                            <button type="button" onclick="setPreset('week')"
                                class="preset-btn px-sm py-[4px] rounded-full text-[11px] font-medium border border-outline-variant text-on-surface-variant hover:border-primary hover:text-primary transition-colors">
                                Minggu Ini
                            </button>
                            <button type="button" onclick="setPreset('month')"
                                class="preset-btn px-sm py-[4px] rounded-full text-[11px] font-medium border border-outline-variant text-on-surface-variant hover:border-primary hover:text-primary transition-colors">
                                Bulan Ini
                            </button>
                            <button type="button" onclick="setPreset('lastmonth')"
                                class="preset-btn px-sm py-[4px] rounded-full text-[11px] font-medium border border-outline-variant text-on-surface-variant hover:border-primary hover:text-primary transition-colors">
                                Bulan Lalu
                            </button>
                        </div>
                        <!-- Date range input -->
                        <div class="flex items-center gap-xs flex-1 min-w-0">
                            <input type="date" name="date_from" id="filter_from" value="<?= htmlspecialchars($date_from) ?>"
                                   class="flex-1 min-w-0 bg-white border border-outline-variant/40 rounded-lg px-sm py-[5px] text-[12px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                            <span class="text-on-surface-variant text-[12px] shrink-0">–</span>
                            <input type="date" name="date_to" id="filter_to" value="<?= htmlspecialchars($date_to) ?>"
                                   class="flex-1 min-w-0 bg-white border border-outline-variant/40 rounded-lg px-sm py-[5px] text-[12px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                            <button type="submit"
                                    class="shrink-0 bg-primary text-on-primary px-sm py-[5px] rounded-lg text-[12px] font-medium hover:opacity-90 transition-opacity flex items-center gap-xs">
                                <span class="material-symbols-outlined text-[14px]">search</span>Filter
                            </button>
                            <?php if ($is_filtered): ?>
                            <a href="?id=<?= $id ?>"
                               class="shrink-0 border border-outline-variant text-on-surface-variant px-sm py-[5px] rounded-lg text-[12px] font-medium hover:border-error hover:text-error transition-colors flex items-center gap-xs">
                                <span class="material-symbols-outlined text-[14px]">close</span>Reset
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-outline-variant/10 bg-surface-container-low/50">
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Tanggal</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Jamur Putih</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Jamur Coklat</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Sisa Stok</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Pendapatan</th>
                                <th class="px-md py-sm text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/10">
                            <?php if (mysqli_num_rows($riwayat) === 0): ?>
                            <tr><td colspan="6" class="px-md py-lg text-center text-on-surface-variant text-[13px]">
                                <span class="material-symbols-outlined text-[32px] opacity-30 block mb-sm">inbox</span>
                                Belum ada riwayat setoran.
                            </td></tr>
                            <?php endif; ?>
                            <?php while ($row = mysqli_fetch_assoc($riwayat)):
                                $ada_harga      = $row['harga_beli_putih'] !== null || $row['harga_beli_coklat'] !== null;
                                $sisa_total     = ($row['sisa_putih'] ?? 0) + ($row['sisa_coklat'] ?? 0);
                                $batch_habis    = $sisa_total <= 0;
                                $terjual_putih  = (float)($row['terjual_putih']  ?? 0);
                                $terjual_coklat = (float)($row['terjual_coklat'] ?? 0);
                                // Pendapatan realtime: hanya dari kg yang sudah terjual
                                $pendapatan_realtime =
                                    ($terjual_putih  * (float)($row['harga_beli_putih']  ?? 0)) +
                                    ($terjual_coklat * (float)($row['harga_beli_coklat'] ?? 0));
                            ?>
                            <tr class="hover:bg-surface-container-low/50 transition-colors <?= $batch_habis ? 'opacity-60' : '' ?>">
                                <td class="px-md py-sm">
                                    <p class="text-[13px] font-semibold text-on-surface"><?= date('d M Y', strtotime($row['tgl_setor'])) ?></p>
                                    <p class="text-[11px] text-on-surface-variant"><?= number_format($row['jumlah_barang'],2) ?> kg total</p>
                                </td>
                                <td class="px-md py-sm text-right">
                                    <p class="text-[13px]"><?= number_format($row['jamur_putih'],2) ?> <span class="text-[11px] text-on-surface-variant">kg</span></p>
                                    <?php if ($row['harga_beli_putih']): ?>
                                    <p class="text-[10px] text-on-surface-variant">@ Rp <?= number_format($row['harga_beli_putih'],0,',','.') ?></p>
                                    <?php endif; ?>
                                </td>
                                <td class="px-md py-sm text-right">
                                    <p class="text-[13px]"><?= number_format($row['jamur_coklat'],2) ?> <span class="text-[11px] text-on-surface-variant">kg</span></p>
                                    <?php if ($row['harga_beli_coklat']): ?>
                                    <p class="text-[10px] text-on-surface-variant">@ Rp <?= number_format($row['harga_beli_coklat'],0,',','.') ?></p>
                                    <?php endif; ?>
                                </td>
                                <!-- Sisa stok per batch -->
                                <td class="px-md py-sm text-right">
                                    <?php if ($batch_habis): ?>
                                    <span class="inline-flex items-center gap-xs text-[11px] bg-surface-container text-on-surface-variant/60 px-xs py-[2px] rounded">
                                        <span class="material-symbols-outlined text-[12px]">check_circle</span> Habis Terjual
                                    </span>
                                    <?php else: ?>
                                    <p class="text-[12px] font-medium text-primary"><?= number_format($row['sisa_putih']??0,2) ?> kg <span class="text-[10px] text-on-surface-variant font-normal">putih</span></p>
                                    <p class="text-[12px] font-medium text-tertiary"><?= number_format($row['sisa_coklat']??0,2) ?> kg <span class="text-[10px] text-on-surface-variant font-normal">coklat</span></p>
                                    <?php endif; ?>
                                    <?php if ($terjual_putih > 0 || $terjual_coklat > 0): ?>
                                    <div class="mt-xs border-t border-outline-variant/10 pt-xs">
                                        <p class="text-[10px] text-on-surface-variant/60 uppercase tracking-wide mb-[2px]">Terjual</p>
                                        <?php if ($terjual_putih > 0): ?>
                                        <p class="text-[11px] text-on-surface-variant"><?= number_format($terjual_putih,2) ?> kg putih</p>
                                        <?php endif; ?>
                                        <?php if ($terjual_coklat > 0): ?>
                                        <p class="text-[11px] text-on-surface-variant"><?= number_format($terjual_coklat,2) ?> kg coklat</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <!-- Pendapatan realtime (berdasarkan kg terjual) -->
                                <td class="px-md py-sm text-right">
                                    <?php if (!$ada_harga): ?>
                                    <span class="inline-flex items-center gap-xs text-[10px] text-on-surface-variant/60 bg-surface-container px-xs py-[2px] rounded">
                                        <span class="material-symbols-outlined text-[11px]">schedule</span>
                                        Belum ada harga
                                    </span>
                                    <?php elseif ($terjual_putih <= 0 && $terjual_coklat <= 0): ?>
                                    <span class="inline-flex items-center gap-xs text-[10px] text-on-surface-variant/60 bg-surface-container px-xs py-[2px] rounded">
                                        <span class="material-symbols-outlined text-[11px]">hourglass_empty</span>
                                        Belum terjual
                                    </span>
                                    <?php else: ?>
                                    <div>
                                        <?php if ($terjual_putih > 0 && $row['harga_beli_putih']): 
                                            $pp = $terjual_putih * (float)$row['harga_beli_putih']; ?>
                                        <p class="text-[11px]">Putih: <strong class="text-primary">Rp <?= number_format($pp,0,',','.') ?></strong></p>
                                        <?php endif; ?>
                                        <?php if ($terjual_coklat > 0 && $row['harga_beli_coklat']): 
                                            $pc = $terjual_coklat * (float)$row['harga_beli_coklat']; ?>
                                        <p class="text-[11px]">Coklat: <strong class="text-tertiary">Rp <?= number_format($pc,0,',','.') ?></strong></p>
                                        <?php endif; ?>
                                        <p class="text-[12px] font-bold text-primary border-t border-outline-variant/20 pt-xs mt-xs">
                                            Rp <?= number_format($pendapatan_realtime,0,',','.') ?>
                                        </p>
                                        <?php if (!$batch_habis): ?>
                                        <p class="text-[10px] text-on-surface-variant/60 mt-[2px]">sebagian terjual</p>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-md py-sm">
                                    <div class="flex items-center justify-center gap-xs">
                                        <a href="../barang_masuk/edit.php?id=<?= $row['id_barang_masuk'] ?>" class="p-xs text-on-surface-variant hover:text-primary transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">edit</span>
                                        </a>
                                        <a href="?id=<?= $id ?>&hapus_bm=<?= $row['id_barang_masuk'] ?>"
                                           onclick="return confirm('Hapus riwayat setoran ini? Stok batch juga akan terhapus.')"
                                           class="p-xs text-on-surface-variant hover:text-error transition-colors">
                                            <span class="material-symbols-outlined text-[18px]">delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>

                        <!-- Footer total pendapatan (mengikuti filter aktif) -->
                        <?php if ($filtered_stats['total_setor'] > 0 && $filtered_stats['total_pendapatan'] > 0): ?>
                        <tfoot>
                            <tr class="bg-surface-container-low border-t border-outline-variant/20">
                                <td colspan="3" class="px-md py-sm text-[12px] font-bold text-on-surface-variant uppercase tracking-wider">
                                    Total Pendapatan
                                    <?php if ($is_filtered): ?>
                                    <span class="font-normal normal-case text-[10px] text-primary ml-xs">(periode filter)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-md py-sm text-right text-[12px] font-bold"><?= number_format($filtered_stats['grand_total'],2) ?> kg</td>
                                <td class="px-md py-sm text-right">
                                    <p class="text-[13px] font-bold text-primary">Rp <?= number_format($filtered_stats['total_pendapatan'],0,',','.') ?></p>
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
    const jp=document.getElementById('jp_input'),jc=document.getElementById('jc_input');
    const hbp=document.getElementById('hbp_input'),hbc=document.getElementById('hbc_input');
    const box=document.getElementById('preview-box');
    const fmt=n=>'Rp '+Math.round(n).toLocaleString('id-ID');
    function update(){
        const jpv=parseFloat(jp?.value)||0,jcv=parseFloat(jc?.value)||0;
        const hbpv=parseFloat(hbp?.value)||0,hbcv=parseFloat(hbc?.value)||0;
        const pp=jpv*hbpv,pc=jcv*hbcv;
        if((hbpv>0&&jpv>0)||(hbcv>0&&jcv>0)){
            document.getElementById('prev-putih').textContent=fmt(pp);
            document.getElementById('prev-coklat').textContent=fmt(pc);
            document.getElementById('prev-total').textContent=fmt(pp+pc);
            box.style.display='block';
        } else { box.style.display='none'; }
    }
    [jp,jc,hbp,hbc].forEach(el=>el&&el.addEventListener('input',update));
})();

// ===== Filter preset buttons =====
function setPreset(type) {
    const from = document.getElementById('filter_from');
    const to   = document.getElementById('filter_to');
    const form = document.getElementById('filter-form');
    const today = new Date();
    const pad = n => String(n).padStart(2,'0');
    const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;

    if (type === 'all') {
        from.value = '';
        to.value = '';
        form.submit();
        return;
    }
    if (type === 'week') {
        const day = today.getDay(); // 0=Sun
        const diffMon = (day === 0) ? -6 : 1 - day;
        const mon = new Date(today); mon.setDate(today.getDate() + diffMon);
        const sun = new Date(mon);   sun.setDate(mon.getDate() + 6);
        from.value = fmt(mon);
        to.value   = fmt(sun);
    }
    if (type === 'month') {
        from.value = `${today.getFullYear()}-${pad(today.getMonth()+1)}-01`;
        to.value   = fmt(today);
    }
    if (type === 'lastmonth') {
        const y = today.getMonth() === 0 ? today.getFullYear()-1 : today.getFullYear();
        const m = today.getMonth() === 0 ? 12 : today.getMonth();
        const lastDay = new Date(y, m, 0).getDate();
        from.value = `${y}-${pad(m)}-01`;
        to.value   = `${y}-${pad(m)}-${lastDay}`;
    }
    form.submit();
}
</script>
<?php require_once '../footer.php'; ?>
