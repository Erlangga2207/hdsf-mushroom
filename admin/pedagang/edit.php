<?php
session_start();
require_once '../session_guard.php';
require_once '../koneksi.php';

$page_title  = 'Edit Pedagang';
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

$errors = [];
$old    = $pedagang;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['nama']  = trim($_POST['nama'] ?? '');
    $old['no_hp'] = trim($_POST['no_hp'] ?? '');
    $old['alamat']= trim($_POST['alamat'] ?? '');

    if ($old['nama'] === '')   $errors[] = 'Nama pedagang wajib diisi.';
    if ($old['no_hp'] === '')  $errors[] = 'Nomor HP wajib diisi.';
    if ($old['alamat'] === '') $errors[] = 'Alamat wajib diisi.';

    if (empty($errors)) {
        $stmt = mysqli_prepare($koneksi, "UPDATE pedagang SET nama=?, no_hp=?, alamat=? WHERE id_pedagang=?");
        mysqli_stmt_bind_param($stmt, 'sssi', $old['nama'], $old['no_hp'], $old['alamat'], $id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['flash_message'] = 'Data pedagang berhasil diperbarui.';
            $_SESSION['flash_type']    = 'success';
            header('Location: index.php');
            exit;
        } else {
            $errors[] = 'Gagal memperbarui: ' . mysqli_error($koneksi);
        }
        mysqli_stmt_close($stmt);
    }
}

require_once '../header.php';
?>

<div class="max-w-2xl mx-auto">
    <nav class="flex items-center gap-xs text-[13px] text-on-surface-variant mb-md">
        <a href="../dashboard.php" class="hover:text-primary">Dashboard</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <a href="index.php" class="hover:text-primary">Pedagang</a>
        <span class="material-symbols-outlined text-[16px]">chevron_right</span>
        <span class="text-primary font-medium">Edit</span>
    </nav>

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
                <h3 class="text-[18px] font-semibold text-on-surface">Edit Pedagang</h3>
                <p class="text-[12px] text-on-surface-variant">PDG-<?= str_pad($id, 3, '0', STR_PAD_LEFT) ?></p>
            </div>
        </div>

        <form method="POST" class="space-y-md">
            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Nama Pedagang <span class="text-error">*</span></label>
                <input type="text" name="nama" value="<?= htmlspecialchars($old['nama']) ?>"
                       class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Nomor HP / WhatsApp <span class="text-error">*</span></label>
                <div class="flex">
                    <span class="inline-flex items-center px-sm bg-surface-container border border-r-0 border-outline-variant/40 rounded-l-lg text-[14px] text-on-surface-variant font-medium">+62</span>
                    <input type="tel" name="no_hp" value="<?= htmlspecialchars($old['no_hp']) ?>"
                           class="flex-1 bg-white border border-outline-variant/40 rounded-r-lg px-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all"/>
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold uppercase tracking-widest text-on-surface-variant mb-xs">Alamat Lengkap <span class="text-error">*</span></label>
                <textarea name="alamat" rows="4"
                          class="w-full bg-white border border-outline-variant/40 rounded-lg px-sm py-sm text-[15px] focus:outline-none focus:border-primary focus:ring-1 focus:ring-primary transition-all resize-none"><?= htmlspecialchars($old['alamat']) ?></textarea>
            </div>

            <div class="flex justify-end gap-sm pt-md border-t border-outline-variant/10">
                <a href="index.php" class="px-lg py-sm border border-secondary text-secondary rounded-lg font-medium text-[14px] hover:bg-secondary-container/20 transition-colors">
                    Batal
                </a>
                <button type="submit" class="px-lg py-sm bg-primary text-on-primary rounded-lg font-semibold text-[14px] shadow-sm hover:opacity-90 active:scale-95 transition-all flex items-center gap-xs">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    Simpan Perubahan
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../footer.php'; ?>
