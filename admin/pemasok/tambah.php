<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Tambah Pemasok';
$active_menu = 'pemasok';
$base_path   = '../';

$errors = [];
$old    = ['nama' => '', 'no_hp' => '', 'alamat' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['nama']  = trim($_POST['nama'] ?? '');
    $old['no_hp'] = trim($_POST['no_hp'] ?? '');
    $old['alamat']= trim($_POST['alamat'] ?? '');

    if ($old['nama'] === '')   $errors[] = 'Nama pemasok wajib diisi.';
    if ($old['no_hp'] === '')  $errors[] = 'Nomor HP wajib diisi.';
    if ($old['alamat'] === '') $errors[] = 'Alamat wajib diisi.';

    if (empty($errors)) {
        $stmt = mysqli_prepare($koneksi, "INSERT INTO pemasok (nama, no_hp, alamat) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $old['nama'], $old['no_hp'], $old['alamat']);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = 'Pemasok "' . $old['nama'] . '" berhasil ditambahkan.';
            $_SESSION['flash_type']    = 'success';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Gagal menyimpan data: ' . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
}

require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <a href="index.php" class="hover:text-primary">Pemasok</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Tambah</span>
    </nav>

    <!-- Error -->
    <?php if (!empty($errors)): ?>
    <div class="mb-md p-md rounded-lg bg-error-container border border-error/20 text-on-error-container">
        <div class="flex items-center gap-sm mb-xs"><span class="material-symbols-outlined text-[18px]">error</span><strong class="text-[14px]">Terdapat kesalahan:</strong></div>
        <ul class="list-disc list-inside text-[13px] space-y-xs">
            <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 p-lg">
        <div class="flex items-center gap-sm mb-lg border-b border-outline-variant/10 pb-md">
            <span class="material-symbols-outlined text-primary">person_add</span>
            <h3 class="text-[18px] font-semibold text-on-surface">Form Tambah Pemasok</h3>
        </div>

        <form method="POST" class="space-y-md">
            <!-- Nama -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Nama Pemasok <span class="text-error">*</span></label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">person</span>
                    <input type="text" name="nama" value="<?= htmlspecialchars($old['nama']) ?>"
                           placeholder="Contoh: PT Jamur Makmur Sentosa"
                           class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                </div>
                <p class="text-[12px] text-on-surface-variant mt-xs">Gunakan nama resmi badan usaha atau perorangan.</p>
            </div>

            <!-- No HP -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Nomor HP / WhatsApp <span class="text-error">*</span></label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">call</span>
                    <input type="tel" name="no_hp" value="<?= htmlspecialchars($old['no_hp']) ?>"
                           placeholder="0812 XXXX XXXX"
                           class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                </div>
            </div>

            <!-- Alamat -->
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Alamat Lengkap <span class="text-error">*</span></label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute left-sm top-4 text-on-surface-variant text-[20px]">location_on</span>
                    <textarea name="alamat" rows="4" placeholder="Masukkan alamat lengkap gudang atau kantor pemasok..."
                              class="w-full pl-[44px] pr-sm py-sm bg-white border border-outline-variant/40 rounded-lg text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none"><?= htmlspecialchars($old['alamat']) ?></textarea>
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex justify-end gap-sm pt-md border-t border-outline-variant/10">
                <a href="index.php" class="px-lg py-sm border border-secondary text-secondary rounded-lg font-medium text-[14px] hover:bg-secondary-container/20 transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-lg py-sm bg-primary text-on-primary rounded-lg font-semibold text-[14px] shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Simpan Pemasok
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../footer.php'; ?>
