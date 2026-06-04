<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Tambah Barang Keluar';
$active_menu = 'barang_keluar';
$base_path   = '../';

$pedagang_list = mysqli_query($koneksi, "SELECT id_pedagang, nama FROM pedagang ORDER BY nama ASC");
$fifo_batches  = getFifoBatches($koneksi);
$stok          = getStokTotal($koneksi);
$stok_putih    = $stok['putih'];
$stok_coklat   = $stok['coklat'];

$errors = [];
$old = ['id_pedagang'=>'','tgl_kirim'=>date('Y-m-d'),'jamur_putih'=>'','jamur_coklat'=>'','harga_putih'=>'','harga_coklat'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['id_pedagang']  = (int)($_POST['id_pedagang']  ?? 0);
    $old['tgl_kirim']    = trim($_POST['tgl_kirim']     ?? '');
    $old['jamur_putih']  = trim($_POST['jamur_putih']   ?? '');
    $old['jamur_coklat'] = trim($_POST['jamur_coklat']  ?? '');
    $old['harga_putih']  = trim($_POST['harga_putih']   ?? '');
    $old['harga_coklat'] = trim($_POST['harga_coklat']  ?? '');

    $jp = $old['jamur_putih']  !== '' ? (float)$old['jamur_putih']  : 0;
    $jc = $old['jamur_coklat'] !== '' ? (float)$old['jamur_coklat'] : 0;
    $hp = $old['harga_putih']  !== '' ? (float)$old['harga_putih']  : null;
    $hc = $old['harga_coklat'] !== '' ? (float)$old['harga_coklat'] : null;

    if ($old['id_pedagang'] <= 0) $errors[] = 'Pilih pedagang terlebih dahulu.';
    if ($old['tgl_kirim']  === '') $errors[] = 'Tanggal kirim wajib diisi.';
    if ($jp + $jc <= 0)            $errors[] = 'Minimal satu jenis jamur harus diisi lebih dari 0.';
    if ($jp > $stok_putih)         $errors[] = 'Stok jamur putih tidak cukup. Tersedia: '.number_format($stok_putih,2).' kg.';
    if ($jc > $stok_coklat)        $errors[] = 'Stok jamur coklat tidak cukup. Tersedia: '.number_format($stok_coklat,2).' kg.';

    if (empty($errors)) {
        $potongan = hitungFifoPotongan($koneksi, $jp, $jc);
        if ($potongan === false) {
            $errors[] = 'Stok tidak mencukupi untuk memenuhi permintaan ini.';
        } else {
            $id_stock_utama = terapkanFifo($koneksi, $potongan);
            $hp_sql = $hp !== null ? $hp : 'NULL';
            $hc_sql = $hc !== null ? $hc : 'NULL';
            $tgl_e  = mysqli_real_escape_string($koneksi, $old['tgl_kirim']);
            $ok = mysqli_query($koneksi,
                "INSERT INTO barang_keluar (id_stock,id_pedagang,tgl_kirim,jamur_putih,jamur_coklat,harga_putih,harga_coklat)
                 VALUES ($id_stock_utama,{$old['id_pedagang']},'$tgl_e',$jp,$jc,$hp_sql,$hc_sql)");
            if ($ok) {
                $id_bk = mysqli_insert_id($koneksi);
                simpanDetailFifo($koneksi, $id_bk, $potongan);
                $jml_batch = count($potongan);
                $_SESSION['flash_message'] = "Barang keluar berhasil dicatat. Stok $jml_batch batch diperbarui (FIFO).";
                $_SESSION['flash_type']    = 'success';
                header('Location: index.php');
                exit;
            } else {
                rollbackFifo($koneksi, $potongan);
                $errors[] = 'Gagal menyimpan transaksi: '.mysqli_error($koneksi);
            }
        }
    }
    $fifo_batches = getFifoBatches($koneksi);
    $stok = getStokTotal($koneksi);
    $stok_putih = $stok['putih']; $stok_coklat = $stok['coklat'];
}

require_once '../header.php';
?>

<div class="max-w-5xl mx-auto">
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <a href="index.php" class="hover:text-primary">Barang Keluar</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Tambah</span>
    </nav>

    <?php require_once '../fifo_widget.php'; ?>

    <?php if (!empty($errors)): ?>
    <div class="mb-md p-md rounded-lg bg-error-container border border-error/20 text-on-error-container">
        <div class="flex items-center gap-sm mb-xs"><span class="material-symbols-outlined text-[18px]">error</span><strong class="text-[14px]">Terdapat kesalahan:</strong></div>
        <ul class="list-disc list-inside text-[13px] space-y-xs"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg">
        <div class="flex items-center gap-sm mb-lg border-b border-outline-variant/10 pb-md">
            <span class="material-symbols-outlined text-primary">outbox</span>
            <div>
                <h3 class="text-[18px] font-semibold text-on-surface">Form Barang Keluar</h3>
                <p class="text-[12px] text-on-surface-variant">Stok dipotong otomatis sesuai urutan FIFO dari batch tertua</p>
            </div>
        </div>

        <form method="POST" class="space-y-lg">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                <div>
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Pedagang <span class="text-error">*</span></label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">storefront</span>
                        <select name="id_pedagang" class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all appearance-none">
                            <option value="">-- Pilih Pedagang --</option>
                            <?php while ($pd = mysqli_fetch_assoc($pedagang_list)): ?>
                            <option value="<?= $pd['id_pedagang'] ?>" <?= $old['id_pedagang'] == $pd['id_pedagang'] ? 'selected' : '' ?>><?= htmlspecialchars($pd['nama']) ?></option>
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

            <div>
                <p class="text-[13px] font-bold text-on-surface mb-sm flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px] text-primary">scale</span>
                    Jumlah Jamur (kg)
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih <span class="text-[10px] font-normal normal-case text-on-surface-variant/60">Stok: <?= number_format($stok_putih,2) ?> kg</span></label>
                        <div class="relative">
                            <input type="number" name="jamur_putih" value="<?= htmlspecialchars($old['jamur_putih']) ?>"
                                   min="0" step="0.01" max="<?= $stok_putih ?>" placeholder="0.00"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jp_input"/>
                            <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                        </div>
                    </div>
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat <span class="text-[10px] font-normal normal-case text-on-surface-variant/60">Stok: <?= number_format($stok_coklat,2) ?> kg</span></label>
                        <div class="relative">
                            <input type="number" name="jamur_coklat" value="<?= htmlspecialchars($old['jamur_coklat']) ?>"
                                   min="0" step="0.01" max="<?= $stok_coklat ?>" placeholder="0.00"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jc_input"/>
                            <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <p class="text-[13px] font-bold text-on-surface mb-sm flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px] text-primary">payments</span>
                    Harga Jual per kg <span class="text-[11px] font-normal text-on-surface-variant/60">(opsional)</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Jamur Putih</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_putih" value="<?= htmlspecialchars($old['harga_putih']) ?>"
                                   min="0" step="100" placeholder="Contoh: 15000"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hp_input"/>
                        </div>
                    </div>
                    <div class="bg-surface-container-low/50 rounded-lg p-md border border-outline-variant/10">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Jamur Coklat</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_coklat" value="<?= htmlspecialchars($old['harga_coklat']) ?>"
                                   min="0" step="100" placeholder="Contoh: 20000"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hc_input"/>
                        </div>
                    </div>
                </div>
            </div>

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
                    <span class="material-symbols-outlined text-[18px]">save</span> Simpan Transaksi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    const jp=document.getElementById('jp_input'),jc=document.getElementById('jc_input');
    const hp=document.getElementById('hp_input'),hc=document.getElementById('hc_input');
    const box=document.getElementById('preview-box');
    const fmt=n=>'Rp '+Math.round(n).toLocaleString('id-ID');
    function update(){
        const jpv=parseFloat(jp.value)||0,jcv=parseFloat(jc.value)||0;
        const hpv=parseFloat(hp.value)||0,hcv=parseFloat(hc.value)||0;
        const sp=jpv*hpv,sc=jcv*hcv;
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
