<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Tambah Barang Masuk';
$active_menu = 'barang_masuk';
$base_path   = '../';

$pemasok_list = mysqli_query($koneksi, "SELECT id_pemasok, nama FROM pemasok ORDER BY nama ASC");

$errors = [];
$old = ['id_pemasok'=>'','tgl_setor'=>date('Y-m-d'),'jamur_putih'=>'','jamur_coklat'=>'','harga_beli_putih'=>'','harga_beli_coklat'=>''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['id_pemasok']        = (int)($_POST['id_pemasok']        ?? 0);
    $old['tgl_setor']         = trim($_POST['tgl_setor']          ?? '');
    $old['jamur_putih']       = trim($_POST['jamur_putih']        ?? '');
    $old['jamur_coklat']      = trim($_POST['jamur_coklat']       ?? '');
    $old['harga_beli_putih']  = trim($_POST['harga_beli_putih']   ?? '');
    $old['harga_beli_coklat'] = trim($_POST['harga_beli_coklat']  ?? '');

    $jp  = $old['jamur_putih']       !== '' ? (float)$old['jamur_putih']       : 0;
    $jc  = $old['jamur_coklat']      !== '' ? (float)$old['jamur_coklat']      : 0;
    $hbp = $old['harga_beli_putih']  !== '' ? (float)$old['harga_beli_putih']  : null;
    $hbc = $old['harga_beli_coklat'] !== '' ? (float)$old['harga_beli_coklat'] : null;

    if ($old['id_pemasok'] <= 0) $errors[] = 'Pilih pemasok terlebih dahulu.';
    if ($old['tgl_setor']  === '') $errors[] = 'Tanggal masuk wajib diisi.';
    if ($jp + $jc <= 0)            $errors[] = 'Total jamur harus lebih dari 0 kg.';
    if ($jp < 0)                   $errors[] = 'Jumlah jamur putih tidak boleh negatif.';
    if ($jc < 0)                   $errors[] = 'Jumlah jamur coklat tidak boleh negatif.';

    if (empty($errors)) {
        $hbp_sql = $hbp !== null ? $hbp : 'NULL';
        $hbc_sql = $hbc !== null ? $hbc : 'NULL';
        $tgl_e   = mysqli_real_escape_string($koneksi, $old['tgl_setor']);
        $ok = mysqli_query($koneksi,
            "INSERT INTO barang_masuk (id_pemasok, tgl_setor, jamur_putih, jamur_coklat, harga_beli_putih, harga_beli_coklat)
             VALUES ({$old['id_pemasok']}, '$tgl_e', $jp, $jc, $hbp_sql, $hbc_sql)");
        if ($ok) {
            $new_bm_id = mysqli_insert_id($koneksi);
            mysqli_query($koneksi,
                "INSERT INTO stock (id_barang_masuk, jumlah_jamur_putih, jumlah_jamur_coklat, sisa_putih, sisa_coklat)
                 VALUES ($new_bm_id, $jp, $jc, $jp, $jc)");
            $_SESSION['flash_message'] = 'Barang masuk berhasil ditambahkan.';
            $_SESSION['flash_type']    = 'success';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Gagal menyimpan: '.mysqli_error($koneksi);
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
        <span class="text-primary font-medium">Tambah</span>
    </nav>

    <?php if (!empty($errors)): ?>
    <div class="mb-md p-md rounded-lg bg-error-container border border-error/20 text-on-error-container">
        <div class="flex items-center gap-sm mb-xs"><span class="material-symbols-outlined text-[18px]">error</span><strong class="text-[14px]">Terdapat kesalahan:</strong></div>
        <ul class="list-disc list-inside text-[13px] space-y-xs"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg">
        <div class="flex items-center gap-sm mb-lg border-b border-outline-variant/10 pb-md">
            <span class="material-symbols-outlined text-primary">add_box</span>
            <h3 class="text-[18px] font-semibold text-on-surface">Form Tambah Barang Masuk</h3>
        </div>

        <form method="POST" class="space-y-md">
            <!-- Pemasok -->
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

            <!-- Tanggal -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Tanggal Masuk <span class="text-error">*</span></label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">calendar_today</span>
                    <input type="date" name="tgl_setor" value="<?= htmlspecialchars($old['tgl_setor']) ?>"
                           class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                </div>
            </div>

            <!-- Jumlah Jamur -->
            <div>
                <p class="text-[13px] font-bold text-on-surface mb-sm">Jumlah Jamur (kg)</p>
                <div class="grid grid-cols-2 gap-md">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Putih</label>
                        <div class="relative">
                            <input type="number" name="jamur_putih" value="<?= htmlspecialchars($old['jamur_putih']) ?>"
                                   min="0" step="0.01" placeholder="0.00"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jp_input"/>
                            <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Jamur Coklat</label>
                        <div class="relative">
                            <input type="number" name="jamur_coklat" value="<?= htmlspecialchars($old['jamur_coklat']) ?>"
                                   min="0" step="0.01" placeholder="0.00"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm pr-[36px] text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="jc_input"/>
                            <span class="absolute right-sm top-1/2 -translate-y-1/2 text-[12px] text-on-surface-variant">kg</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Harga Beli (opsional) -->
            <div>
                <p class="text-[13px] font-bold text-on-surface mb-xs flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px] text-primary">price_check</span>
                    Harga Beli dari Pemasok
                    <span class="text-[11px] font-normal text-on-surface-variant/60">(opsional — untuk perhitungan pendapatan pemasok)</span>
                </p>
                <div class="grid grid-cols-2 gap-md">
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Beli Putih/kg</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_beli_putih" value="<?= htmlspecialchars($old['harga_beli_putih']) ?>"
                                   min="0" step="100" placeholder="Contoh: 10000"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hbp_input"/>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Harga Beli Coklat/kg</label>
                        <div class="relative">
                            <span class="absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant font-medium text-[13px]">Rp</span>
                            <input type="number" name="harga_beli_coklat" value="<?= htmlspecialchars($old['harga_beli_coklat']) ?>"
                                   min="0" step="100" placeholder="Contoh: 15000"
                                   class="w-full bg-white border border-outline-variant/40 rounded-lg pl-[40px] pr-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all" id="hbc_input"/>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview pendapatan -->
            <div class="bg-primary-fixed/30 rounded-lg border border-primary/10 p-md" id="preview-box" style="display:none">
                <p class="text-[11px] font-bold uppercase tracking-widest text-on-primary-fixed-variant mb-sm">Preview Pendapatan Pemasok</p>
                <div class="grid grid-cols-3 gap-md text-center">
                    <div><p class="text-[11px] text-on-surface-variant mb-xs">Pendapatan Putih</p><p class="text-[15px] font-bold text-primary" id="prev-putih">Rp 0</p></div>
                    <div><p class="text-[11px] text-on-surface-variant mb-xs">Pendapatan Coklat</p><p class="text-[15px] font-bold text-tertiary" id="prev-coklat">Rp 0</p></div>
                    <div class="border-l border-primary/20 pl-md"><p class="text-[11px] text-on-surface-variant mb-xs">Total Pendapatan</p><p class="text-[17px] font-bold text-primary" id="prev-total">Rp 0</p></div>
                </div>
            </div>

            <div class="p-sm bg-primary-fixed/20 rounded-lg flex gap-sm items-start">
                <span class="material-symbols-outlined text-primary text-[18px]">info</span>
                <p class="text-[12px] text-on-primary-fixed-variant leading-relaxed">
                    Data barang masuk ini akan otomatis membuat batch stok baru untuk sistem FIFO. Harga beli opsional — bisa diisi nanti via menu Edit.
                </p>
            </div>

            <div class="flex justify-end gap-sm pt-md border-t border-outline-variant/10">
                <a href="index.php" class="px-lg py-sm border border-secondary text-secondary rounded-lg font-medium text-[14px] hover:bg-secondary-container/20 transition-colors">Batal</a>
                <button type="submit" class="px-lg py-sm bg-primary text-on-primary rounded-lg font-semibold text-[14px] shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px]">save</span> Simpan
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    const jp=document.getElementById('jp_input'),jc=document.getElementById('jc_input');
    const hbp=document.getElementById('hbp_input'),hbc=document.getElementById('hbc_input');
    const box=document.getElementById('preview-box');
    const fmt=n=>'Rp '+Math.round(n).toLocaleString('id-ID');
    function update(){
        const jpv=parseFloat(jp.value)||0,jcv=parseFloat(jc.value)||0;
        const hbpv=parseFloat(hbp.value)||0,hbcv=parseFloat(hbc.value)||0;
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
</script>
<?php require_once '../footer.php'; ?>
