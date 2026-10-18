<?php
/**
 * QR-SAR - Tab Berita Radio SAR (BRA)
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
?>
<div id="tab-content-bra" class="tab-pane space-y-4" style="display: <?= $activeTab === 'bra' ? 'block' : 'none' ?>;">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                <span>Berita Radio SAR (BRA) &bull; <?= $countBra ?> Dokumen</span>
            </h3>
        </div>
        <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=laporan_awal" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat BRA SAR Baru</span>
        </a>
    </div>

    <?php if (empty($operasi['bra_sar'])): ?>
    <div class="p-8 sm:p-12 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs text-center">
        <div class="w-12 h-12 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Belum Ada Berita Radio SAR (BRA)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Terbitkan naskah telegram resmi Basarnas untuk dikirimkan ke Kabasarnas dan jajaran pimpinan Senkom.
        </p>
        <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=laporan_awal" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat BRA SAR Baru</span>
        </a>
    </div>
    <?php else: foreach ($operasi['bra_sar'] as $bra): ?>
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3.5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-lg bg-orange-500 text-white font-mono font-bold text-xs">
                    <?= in_array(strtoupper($bra['kategori']), ['SAR_UMUM', 'OPERASI_SAR']) ? 'BRA OPERASI SAR' : ($bra['kategori'] === 'PERGERAKAN_KAPAL' ? 'BRA KAPAL' : 'BRA MAPEL') ?>
                </span>
                <span class="font-mono font-black text-sm text-slate-900 dark:text-white">
                    NO: <?= e($bra['no_bra']) ?>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Lihat Dokumen</span>
                </a>
                <a href="<?= BASE_URL ?>/bra.php?action=edit&id=<?= $bra['id_bra'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                    <span>Edit</span>
                </a>
            </div>
        </div>

        <div class="text-xs">
            <strong class="text-slate-900 dark:text-white">PERIHAL:</strong> <span class="text-slate-600 dark:text-slate-400"><?= e($bra['perihal'] ?: '-') ?></span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono">
            <div>TWU: <strong class="text-slate-900 dark:text-white"><?= e($bra['twu']) ?></strong></div>
            <div>TWB: <strong class="text-slate-900 dark:text-white"><?= e($bra['twb']) ?></strong></div>
            <div>Jml Kata: <strong class="text-slate-900 dark:text-white"><?= e($bra['jumlah_kata']) ?></strong></div>
            <div>SMC: <strong class="text-slate-900 dark:text-white"><?= e($bra['nama_smc']) ?></strong></div>
        </div>

        <?php if (!empty($bra['sections'])): ?>
        <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 font-mono text-[11px] space-y-1">
            <div class="font-bold text-orange-600 dark:text-orange-400 uppercase text-[10px] mb-1">Naskah Telegram (Preview):</div>
            <?php foreach (array_slice($bra['sections'], 0, 4) as $sec): ?>
                <div class="text-slate-700 dark:text-slate-300">
                    <span class="font-bold"><?= e($sec['kode']) ?> :</span>
                    <span class="text-slate-500"><?= e(mb_strimwidth($sec['isi'], 0, 140, '...')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
</div>
