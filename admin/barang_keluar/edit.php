<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Edit Barang Keluar';
$active_menu = 'barang_keluar';
$base_path   = '../';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: index.php'); exit; }

$bk = mysqli_fetch_assoc(mysqli_query($koneksi,
    "SELECT bk.*, s.sisa_putih, s.sisa_coklat, s.id_stock AS sid
     FROM barang_keluar bk
     JOIN stock s ON bk.id_stock = s.id_stock
     WHERE bk.id_barang_keluar = $id"));
if (!$bk) {
    $_SESSION['flash_message'] = 'Data tidak ditemukan.';
    $_SESSION['flash_type']    = 'error';
    header('Location: index.php');
    exit;
}

$pedagang_list = mysqli_query($koneksi, "SELECT id_pedagang, nama FROM pedagang ORDER BY nama ASC");

// Stok tersedia = sisa_stock + jumlah yang sedang diedit (kembalikan dulu)
$stok_raw   = getStokTotal($koneksi);
$stok_putih  = $stok_raw['putih']  + $bk['jamur_putih'];
$stok_coklat = $stok_raw['coklat'] + $bk['jamur_coklat'];

$errors = [];
$old    = $bk;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['id_pedagang']  = (int)($_POST['id_pedagang']  ?? 0);
    $old['tgl_kirim']    = trim($_POST['tgl_kirim']     ?? '');
    $old['jamur_putih']  = trim($_POST['jamur_putih']   ?? '');
    $old['jamur_coklat'] = trim($_POST['jamur_coklat']  ?? '');
    $old['harga_putih']  = $_POST['harga_putih']  !== '' ? trim($_POST['harga_putih'])  : '';
    $old['harga_coklat'] = $_POST['harga_coklat'] !== '' ? trim($_POST['harga_coklat']) : '';

    $jp = (float)$old['jamur_putih'];
    $jc = (float)$old['jamur_coklat'];
    $hp = $old['harga_putih']  !== '' ? (float)$old['harga_putih']  : null;
    $hc = $old['harga_coklat'] !== '' ? (float)$old['harga_coklat'] : null;

    if ($old['id_pedagang'] <= 0) $errors[] = 'Pilih pedagang.';
    if ($old['tgl_kirim']  === '') $errors[] = 'Tanggal kirim wajib diisi.';
    if ($jp + $jc <= 0)            $errors[] = 'Total jamur harus lebih dari 0.';
    if ($jp > $stok_putih)         $errors[] = 'Stok jamur putih tidak cukup. Tersedia: ' . number_format($stok_putih, 2) . ' kg.';
    if ($jc > $stok_coklat)        $errors[] = 'Stok jamur coklat tidak cukup. Tersedia: ' . number_format($stok_coklat, 2) . ' kg.';

    if (empty($errors)) {
        mysqli_begin_transaction($koneksi);
        
        // 1. Ambil detail FIFO asli
        $detail_fifo = getDetailFifo($koneksi, $id);
        if (empty($detail_fifo)) {
            // Fallback jika tidak ada detail FIFO (data lama/seed data)
            $bk_stock = mysqli_fetch_assoc(mysqli_query($koneksi,
                "SELECT sisa_putih, sisa_coklat FROM stock WHERE id_stock = {$bk['id_stock']}"));
            if ($bk_stock) {
                $detail_fifo = [
                    [
                        'id_stock'      => $bk['id_stock'],
                        'potong_putih'  => $bk['jamur_putih'],
                        'potong_coklat' => $bk['jamur_coklat'],
                        'sisa_putih'    => $bk_stock['sisa_putih'],
                        'sisa_coklat'   => $bk_stock['sisa_coklat'],
                    ]
                ];
            }
        }
        
        // 2. Kembalikan stok lama (rollback)
        if (!empty($detail_fifo)) {
            foreach ($detail_fifo as $p) {
                mysqli_query($koneksi,
                    "UPDATE stock 
                     SET sisa_putih = ROUND(sisa_putih + {$p['potong_putih']}, 2),
                         sisa_coklat = ROUND(sisa_coklat + {$p['potong_coklat']}, 2)
                     WHERE id_stock = {$p['id_stock']}");
            }
        }
        
        // 3. Cek apakah stok cukup setelah dikembalikan
        $stok_fresh = getStokTotal($koneksi);
        if ($jp > $stok_fresh['putih']) {
            $errors[] = 'Stok jamur putih tidak cukup. Tersedia: ' . number_format($stok_fresh['putih'], 2) . ' kg.';
        }
        if ($jc > $stok_fresh['coklat']) {
            $errors[] = 'Stok jamur coklat tidak cukup. Tersedia: ' . number_format($stok_fresh['coklat'], 2) . ' kg.';
        }
        
        if (empty($errors)) {
            // 4. Hitung potongan FIFO baru
            $potongan = hitungFifoPotongan($koneksi, $jp, $jc);
            if ($potongan === false) {
                $errors[] = 'Stok tidak mencukupi untuk memenuhi permintaan ini.';
            } else {
                // 5. Terapkan FIFO baru
                $id_stock_utama = terapkanFifo($koneksi, $potongan);
                
                // 6. Update barang_keluar
                $hp_sql = $hp !== null ? $hp : 'NULL';
                $hc_sql = $hc !== null ? $hc : 'NULL';
                $id_pd  = (int)$old['id_pedagang'];
                $tgl    = mysqli_real_escape_string($koneksi, $old['tgl_kirim']);
                
                $res = mysqli_query($koneksi,
                    "UPDATE barang_keluar
                     SET id_stock = $id_stock_utama,
                         id_pedagang = $id_pd,
                         tgl_kirim = '$tgl',
                         jamur_putih = $jp,
                         jamur_coklat = $jc,
                         harga_putih = $hp_sql,
                         harga_coklat = $hc_sql
                     WHERE id_barang_keluar = $id");
                     
                if ($res) {
                    // 7. Bersihkan detail FIFO lama & simpan yang baru
                    mysqli_query($koneksi, "DELETE FROM barang_keluar_detail WHERE id_barang_keluar = $id");
                    simpanDetailFifo($koneksi, $id, $potongan);
                    
                    mysqli_commit($koneksi);
                    
                    $_SESSION['flash_message'] = 'Data barang keluar berhasil diperbarui.';
                    $_SESSION['flash_type']    = 'success';
                    header('Location: index.php');
                    exit;
                } else {
                    $errors[] = 'Gagal memperbarui data transaksi: ' . mysqli_error($koneksi);
                }
            }
        }
        
        // Jika ada error di atas, batalkan perubahan (rollback transaction)
        if (!empty($errors)) {
            mysqli_rollback($koneksi);
        }
    }
}

require_once '../header.php';
?>

<div class="max-w-3xl mx-auto">
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <a href="index.php" class="hover:text-primary">Barang Keluar</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Edit</span>
    </nav>

    <!-- Info stok -->
    <div class="grid grid-cols-2 gap-gutter mb-md">
        <div class="bg-primary-fixed/30 border border-primary/20 rounded-xl p-md">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-primary-fixed-variant mb-xs">Stok Jamur Putih (tersedia)</p>
            <p class="text-[22px] font-bold text-primary"><?= number_format($stok_putih, 2) ?> <span class="text-[14px] font-normal text-on-surface-variant">kg</span></p>
        </div>
        <div class="bg-primary-fixed/30 border border-primary/20 rounded-xl p-md">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-primary-fixed-variant mb-xs">Stok Jamur Coklat (tersedia)</p>
            <p class="text-[22px] font-bold text-tertiary"><?= number_format($stok_coklat, 2) ?> <span class="text-[14px] font-normal text-on-surface-variant">kg</span></p>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="mb-md p-md rounded-lg bg-error-container border border-error/20 text-on-error-container">
        <div class="flex items-center gap-sm mb-xs"><span class="material-symbols-outlined text-[18px]">error</span><strong class="text-[14px]">Terdapat kesalahan:</strong></div>
        <ul class="list-disc list-inside text-[13px] space-y-xs">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg">
        <div class="flex items-center gap-sm mb-lg border-b border-outline-variant/10 pb-md">
            <span class="material-symbols-outlined text-primary">edit</span>
            <div>
                <h3 class="text-[18px] font-semibold text-on-surface">Edit Barang Keluar</h3>
                <p class="text-[12px] text-on-surface-variant">#<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <form method="POST" class="space-y-lg">
            <!-- Pedagang + Tanggal -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Pedagang <span class="text-error">*</span></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">storefront</span>
                        <select name="id_pedagang" class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all appearance-none">
                            <option value="">-- Pilih Pedagang --</option>
                            <?php while ($pd = mysqli_fetch_assoc($pedagang_list)): ?>
                            <option value="<?= $pd['id_pedagang'] ?>" <?= $old['id_pedagang'] == $pd['id_pedagang'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($pd['nama']) ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Tanggal Kirim <span class="text-error">*</span></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">local_shipping</span>
                        <input type="date" name="tgl_kirim" value="<?= htmlspecialchars($old['tgl_kirim']) ?>"
                               class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                    </div>
                </div>
            </div>

            <!-- Jumlah Jamur -->
            <div>
                <p class="text-[13px] font-bold text-on-surface mb-sm flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px] text-primary">scale</span>
                    Jumlah Jamur (kg)
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih</label>
                        <div class="relative">
                            <input type="number" name="jamur_putih" value="<?= $old['jamur_putih'] ?>"
                                   min="0" step="0.01" max="<?= $stok_putih ?>"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jp_input"/>
                            <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                        </div>
                    </div>
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat</label>
                        <div class="relative">
                            <input type="number" name="jamur_coklat" value="<?= $old['jamur_coklat'] ?>"
                                   min="0" step="0.01" max="<?= $stok_coklat ?>"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jc_input"/>
                            <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Harga per kg -->
            <div>
                <p class="text-[13px] font-bold text-on-surface mb-sm flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px] text-primary">payments</span>
                    Harga per kg
                    <span class="text-[11px] font-normal text-on-surface-variant/60">(opsional)</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Jamur Putih</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_putih" value="<?= $old['harga_putih'] ?>"
                                   min="0" step="100" placeholder="Kosongkan jika belum ada"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hp_input"/>
                        </div>
                    </div>
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Jamur Coklat</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_coklat" value="<?= $old['harga_coklat'] ?>"
                                   min="0" step="100" placeholder="Kosongkan jika belum ada"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hc_input"/>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview -->
            <div class="bg-primary-fixed/30 rounded-lg border border-primary/10 p-md" id="preview-box" style="display:none">
                <p class="text-[11px] font-bold uppercase tracking-widest text-on-primary-fixed-variant mb-sm">Preview Kalkulasi</p>
                <div class="grid grid-cols-3 gap-md text-center">
                    <div><p class="text-[11px] text-on-surface-variant mb-xs">Subtotal Putih</p><p class="text-[16px] font-bold text-primary" id="prev-putih">Rp 0</p></div>
                    <div><p class="text-[11px] text-on-surface-variant mb-xs">Subtotal Coklat</p><p class="text-[16px] font-bold text-tertiary" id="prev-coklat">Rp 0</p></div>
                    <div class="border-l border-primary/20 pl-md"><p class="text-[11px] text-on-surface-variant mb-xs">Grand Total</p><p class="text-[18px] font-bold text-primary" id="prev-total">Rp 0</p></div>
                </div>
            </div>

            <div class="flex justify-end gap-sm pt-md border-t border-outline-variant/10">
                <a href="index.php" class="px-lg py-sm border border-secondary text-secondary rounded-lg font-medium text-[14px] hover:bg-secondary-container/20 transition-colors">Batal</a>
                <button type="submit" class="px-lg py-sm bg-primary text-on-primary rounded-lg font-semibold text-[14px] shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const jp=document.getElementById('jp_input'), jc=document.getElementById('jc_input');
    const hp=document.getElementById('hp_input'), hc=document.getElementById('hc_input');
    const box=document.getElementById('preview-box');
    const fmt=n=>'Rp '+Math.round(n).toLocaleString('id-ID');
    function update(){
        const jpv=parseFloat(jp.value)||0, jcv=parseFloat(jc.value)||0;
        const hpv=parseFloat(hp.value)||0, hcv=parseFloat(hc.value)||0;
        const sp=jpv*hpv, sc=jcv*hcv;
        if((hpv>0&&jpv>0)||(hcv>0&&jcv>0)){
            document.getElementById('prev-putih').textContent=fmt(sp);
            document.getElementById('prev-coklat').textContent=fmt(sc);
            document.getElementById('prev-total').textContent=fmt(sp+sc);
            box.style.display='block';
        } else { box.style.display='none'; }
    }
    [jp,jc,hp,hc].forEach(el=>el&&el.addEventListener('input',update));
    update();
})();
</script>

<?php require_once '../footer.php'; ?>
