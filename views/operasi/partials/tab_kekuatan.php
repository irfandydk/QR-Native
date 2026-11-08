<?php
/**
 * QR-SAR - Tab Kekuatan Unsur & Alut Operasi SAR
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */

$totalPersKekuatan = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $operasi['detail_unsur'] ?? []));
$totalAlutKekuatan = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $operasi['detail_alut'] ?? []));
?>
<div id="tab-content-kekuatan" class="tab-pane space-y-5" style="display: <?= $activeTab === 'kekuatan' ? 'block' : 'none' ?>;">
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Left: Unsur SAR Terlibat -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Unsur SAR Terlibat (Potensi Gabungan)</h3>
                        <p class="text-[11px] text-slate-400">Instansi dan potensi yang dikerahkan</p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                    Total: <?= $totalPersKekuatan ?> Personel
                </span>
            </div>

            <?php if (!empty($operasi['detail_unsur'])): ?>
            <div class="space-y-1.5">
                <?php foreach ($operasi['detail_unsur'] as $u): ?>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs">
                    <span class="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        <span><?= e($u['nama_unsur']) ?></span>
                    </span>
                    <span class="px-2 py-0.5 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 font-mono font-bold text-xs">
                        <?= (int)($u['jumlah_personil'] ?? 1) ?> Org
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                <?= e(($operasi['unsur_terlibat'] ?? '') ?: '• Tim Rescue KPP Kendari') ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Alut & Peralatan SAR (Palsar) -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Alut & Peralatan SAR</h3>
                        <p class="text-[11px] text-slate-400">Armada dan sarana operasi</p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-orange-500/10 text-orange-600 dark:text-orange-400">
                    Total: <?= $totalAlutKekuatan ?> Unit
                </span>
            </div>

            <?php if (!empty($operasi['detail_alut'])): ?>
            <div class="space-y-1.5">
                <?php foreach (AlutKategori::sort($operasi['detail_alut']) as $a): $katA = AlutKategori::resolve($a); ?>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs">
                    <span class="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                        <span><?= e($a['nama_alut']) ?></span>
                        <span class="px-1.5 py-0.5 rounded-md text-[10px] font-bold border <?= AlutKategori::isAlut($katA) ? 'bg-sky-500/10 text-sky-700 dark:text-sky-300 border-sky-500/30' : 'bg-amber-500/10 text-amber-700 dark:text-amber-300 border-amber-500/30' ?>"><?= e(AlutKategori::label($katA)) ?></span>
                    </span>
                    <span class="px-2 py-0.5 rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-400 font-mono font-bold text-xs">
                        <?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                <?= e(($operasi['alut_palsar'] ?? '') ?: '-') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
