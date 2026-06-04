<?php
/**
 * PARTIAL: FIFO STOCK WIDGET
 * Include di halaman tambah/detail barang keluar untuk menampilkan
 * antrian stok FIFO dari batch pemasok.
 *
 * Variabel yang dibutuhkan:
 *   $koneksi  — koneksi DB
 *   $fifo_batches — array dari getFifoBatches($koneksi)
 */
if (empty($fifo_batches)) {
    $fifo_batches = getFifoBatches($koneksi);
}
$total_stok  = getStokTotal($koneksi);
$total_putih  = $total_stok['putih'];
$total_coklat = $total_stok['coklat'];
?>

<div class="bg-surface-container-lowest rounded-xl card-shadow border border-outline-variant/10 overflow-hidden mb-lg">
    <!-- Header -->
    <div class="px-md py-sm flex items-center justify-between border-b border-outline-variant/10 bg-primary-fixed/20">
        <div class="flex items-center gap-sm">
            <span class="material-symbols-outlined text-primary text-[20px]">swap_vert</span>
            <div>
                <h3 class="text-[15px] font-bold text-primary">Antrian Stok FIFO</h3>
                <p class="text-[11px] text-on-surface-variant">Ambil dari urutan <strong class="text-primary">#1 terlebih dahulu</strong> (masuk paling awal)</p>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <p class="text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Total Stok Tersedia</p>
            <p class="text-[13px] font-bold text-primary">
                Putih: <?= number_format($total_putih, 2) ?> kg &nbsp;·&nbsp;
                Coklat: <?= number_format($total_coklat, 2) ?> kg
            </p>
        </div>
    </div>

    <!-- Table -->
    <?php if (empty($fifo_batches)): ?>
    <div class="px-md py-lg text-center text-on-surface-variant text-[13px]">
        <span class="material-symbols-outlined text-[32px] opacity-30 block mb-sm">inventory_2</span>
        Tidak ada stok tersedia. Silakan input barang masuk terlebih dahulu.
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-outline-variant/10">
                    <th class="px-md py-xs text-[11px] font-bold uppercase tracking-widest text-on-surface-variant w-12">Urutan</th>
                    <th class="px-md py-xs text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Pemasok</th>
                    <th class="px-md py-xs text-[11px] font-bold uppercase tracking-widest text-on-surface-variant">Tgl Masuk</th>
                    <th class="px-md py-xs text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Sisa Putih</th>
                    <th class="px-md py-xs text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-right">Sisa Coklat</th>
                    <th class="px-md py-xs text-[11px] font-bold uppercase tracking-widest text-on-surface-variant text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/10">
                <?php foreach ($fifo_batches as $urutan => $batch): ?>
                <?php
                    $is_first  = ($urutan === 0);
                    $hari_lalu = (int)((time() - strtotime($batch['tgl_setor'])) / 86400);
                ?>
                <tr class="<?= $is_first ? 'bg-primary-fixed/20' : 'hover:bg-surface-container-low/40' ?> transition-colors">
                    <!-- Urutan -->
                    <td class="px-md py-sm text-center">
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-[12px] font-bold
                            <?= $is_first ? 'bg-primary text-white' : 'bg-surface-container text-on-surface-variant' ?>">
                            <?= $urutan + 1 ?>
                        </span>
                    </td>

                    <!-- Pemasok -->
                    <td class="px-md py-sm">
                        <p class="text-[13px] font-semibold text-on-surface"><?= htmlspecialchars($batch['nama_pemasok']) ?></p>
                        <p class="text-[11px] text-on-surface-variant">Batch #<?= str_pad($batch['id_barang_masuk'], 4, '0', STR_PAD_LEFT) ?></p>
                    </td>

                    <!-- Tanggal -->
                    <td class="px-md py-sm">
                        <p class="text-[13px] text-on-surface"><?= date('d M Y', strtotime($batch['tgl_setor'])) ?></p>
                        <p class="text-[11px] text-on-surface-variant"><?= $hari_lalu === 0 ? 'Hari ini' : $hari_lalu . ' hari lalu' ?></p>
                    </td>

                    <!-- Sisa Putih -->
                    <td class="px-md py-sm text-right">
                        <?php if ($batch['sisa_putih'] > 0): ?>
                        <p class="text-[13px] font-bold text-primary"><?= number_format($batch['sisa_putih'], 2) ?> <span class="text-[11px] font-normal text-on-surface-variant">kg</span></p>
                        <?php else: ?>
                        <span class="text-[12px] text-on-surface-variant/40">Habis</span>
                        <?php endif; ?>
                    </td>

                    <!-- Sisa Coklat -->
                    <td class="px-md py-sm text-right">
                        <?php if ($batch['sisa_coklat'] > 0): ?>
                        <p class="text-[13px] font-bold text-tertiary"><?= number_format($batch['sisa_coklat'], 2) ?> <span class="text-[11px] font-normal text-on-surface-variant">kg</span></p>
                        <?php else: ?>
                        <span class="text-[12px] text-on-surface-variant/40">Habis</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td class="px-md py-sm text-center">
                        <?php if ($is_first): ?>
                        <span class="inline-flex items-center gap-xs text-[11px] font-bold text-primary bg-primary-fixed px-sm py-xs rounded-full">
                            <span class="material-symbols-outlined text-[13px]">arrow_upward</span>
                            Ambil Duluan
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-xs text-[11px] text-on-surface-variant bg-surface-container px-sm py-xs rounded-full">
                            <span class="material-symbols-outlined text-[13px]">schedule</span>
                            Antri
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
