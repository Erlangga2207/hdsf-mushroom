<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Edit Barang Masuk';
$active_menu = 'barang_masuk';
$base_path   = '../';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$bm = mysqli_fetch_assoc(mysqli_query($koneksi, "SELECT * FROM barang_masuk WHERE id_barang_masuk=$id"));
if (!$bm) {
    $_SESSION['flash_message'] = 'Data tidak ditemukan.';
    $_SESSION['flash_type']    = 'error';
    header('Location: index.php'); exit;
}

$pemasok_list = mysqli_query($koneksi, "SELECT id_pemasok, nama FROM pemasok ORDER BY nama ASC");
$errors = [];
$old    = $bm;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['id_pemasok']        = (int)($_POST['id_pemasok']        ?? 0);
    $old['tgl_setor']         = trim($_POST['tgl_setor']          ?? '');
    $old['jamur_putih']       = trim($_POST['jamur_putih']        ?? '');
    $old['jamur_coklat']      = trim($_POST['jamur_coklat']       ?? '');
    $old['harga_beli_putih']  = trim($_POST['harga_beli_putih']   ?? '');
    $old['harga_beli_coklat'] = trim($_POST['harga_beli_coklat']  ?? '');

    $jp  = (float)$old['jamur_putih'];
    $jc  = (float)$old['jamur_coklat'];
    $hbp = $old['harga_beli_putih']  !== '' ? (float)$old['harga_beli_putih']  : null;
    $hbc = $old['harga_beli_coklat'] !== '' ? (float)$old['harga_beli_coklat'] : null;

    if ($old['id_pemasok'] <= 0) $errors[] = 'Pilih pemasok.';
    if ($old['tgl_setor']  === '') $errors[] = 'Tanggal masuk wajib diisi.';
    if ($jp + $jc <= 0)            $errors[] = 'Total jamur harus lebih dari 0.';

    if (empty($errors)) {
        $hbp_sql = $hbp !== null ? $hbp : 'NULL';
        $hbc_sql = $hbc !== null ? $hbc : 'NULL';
        $tgl_e   = mysqli_real_escape_string($koneksi, $old['tgl_setor']);
        $ok = mysqli_query($koneksi,
            "UPDATE barang_masuk SET id_pemasok={$old['id_pemasok']}, tgl_setor='$tgl_e',
             jamur_putih=$jp, jamur_coklat=$jc, harga_beli_putih=$hbp_sql, harga_beli_coklat=$hbc_sql
             WHERE id_barang_masuk=$id");
        if ($ok) {
            // Sinkronkan sisa stok batch (selisih jumlah)
            $used_p = (float)mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT COALESCE(SUM(bkd.potong_putih),0) AS v FROM barang_keluar_detail bkd
                 JOIN stock s ON bkd.id_stock=s.id_stock WHERE s.id_barang_masuk=$id"))['v'];
            $used_c = (float)mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT COALESCE(SUM(bkd.potong_coklat),0) AS v FROM barang_keluar_detail bkd
                 JOIN stock s ON bkd.id_stock=s.id_stock WHERE s.id_barang_masuk=$id"))['v'];
            $sisa_p = max(0, round($jp - $used_p, 2));
            $sisa_c = max(0, round($jc - $used_c, 2));
            mysqli_query($koneksi,
                "INSERT INTO stock (id_barang_masuk,jumlah_jamur_putih,jumlah_jamur_coklat,sisa_putih,sisa_coklat)
                 VALUES ($id,$jp,$jc,$sisa_p,$sisa_c)
                 ON DUPLICATE KEY UPDATE jumlah_jamur_putih=$jp,jumlah_jamur_coklat=$jc,sisa_putih=$sisa_p,sisa_coklat=$sisa_c");
            $_SESSION['flash_message'] = 'Data barang masuk berhasil diperbarui.';
            $_SESSION['flash_type']    = 'success';
            header('Location: index.php'); exit;
        } else {
            $errors[] = 'Gagal memperbarui: '.mysqli_error($koneksi);
        }
    }
}

require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <a href="index.php" class="hover:text-primary">Barang Masuk</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Edit</span>
    </nav>

    <?php if (!empty($errors)): ?>
    <div class="mb-md p-md rounded-lg bg-error-container border border-error/20 text-on-error-container">
        <div class="flex items-center gap-sm mb-xs"><span class="material-symbols-outlined text-[18px]">error</span><strong class="text-[14px]">Terdapat kesalahan:</strong></div>
        <ul class="list-disc list-inside text-[13px] space-y-xs"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg">
        <div class="flex items-center gap-sm mb-lg border-b border-outline-variant/10 pb-md">
            <span class="material-symbols-outlined text-primary">edit</span>
            <div>
                <h3 class="text-[18px] font-semibold text-on-surface">Edit Barang Masuk</h3>
                <p class="text-[12px] text-on-surface-variant">#<?= str_pad($id,4,'0',STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <form method="POST" class="space-y-md">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Pemasok <span class="text-error">*</span></label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">local_shipping</span>
                    <select name="id_pemasok" class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all appearance-none">
                        <option value="">-- Pilih Pemasok --</option>
                        <?php while ($p = mysqli_fetch_assoc($pemasok_list)): ?>
                        <option value="<?= $p['id_pemasok'] ?>" <?= $old['id_pemasok'] == $p['id_pemasok'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Tanggal Masuk <span class="text-error">*</span></label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">calendar_today</span>
                    <input type="date" name="tgl_setor" value="<?= htmlspecialchars($old['tgl_setor']) ?>"
                           class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-md">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih (kg)</label>
                    <div class="relative">
                        <input type="number" name="jamur_putih" value="<?= $old['jamur_putih'] ?>"
                               min="0" step="0.01"
                               class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                        <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat (kg)</label>
                    <div class="relative">
                        <input type="number" name="jamur_coklat" value="<?= $old['jamur_coklat'] ?>"
                               min="0" step="0.01"
                               class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                        <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                    </div>
                </div>
            </div>

            <!-- Harga Beli -->
            <div>
                <p class="text-[13px] font-bold text-on-surface mb-sm flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px] text-primary">price_check</span>
                    Harga Beli dari Pemasok
                    <span class="text-[11px] font-normal text-on-surface-variant/60">(opsional)</span>
                </p>
                <div class="grid grid-cols-2 gap-md">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Beli Putih/kg</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_beli_putih" value="<?= $old['harga_beli_putih'] ?>"
                                   min="0" step="100" placeholder="Kosongkan jika belum ada"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Beli Coklat/kg</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_beli_coklat" value="<?= $old['harga_beli_coklat'] ?>"
                                   min="0" step="100" placeholder="Kosongkan jika belum ada"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-sm pt-md border-t border-outline-variant/10">
                <a href="index.php" class="px-lg py-sm border border-secondary text-secondary rounded-lg font-medium text-[14px] hover:bg-secondary-container/20 transition-colors">Batal</a>
                <button type="submit" class="px-lg py-sm bg-primary text-on-primary rounded-lg font-semibold text-[14px] shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px]">save</span> Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>
<?php require_once '../footer.php'; ?>
