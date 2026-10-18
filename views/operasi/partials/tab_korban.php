<?php
/**
 * QR-SAR - Tab Data Korban & Kronologi Operasi SAR
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
?>
<div id="tab-content-korban" class="tab-pane space-y-5" style="display: <?= $activeTab === 'korban' ? 'block' : 'none' ?>;">
    
    <!-- STATS SUMMARY 5 MINI CARDS -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Korban (POB)</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono"><?= (int)($statsKorban['total'] ?? $countKorban) ?></span>
                <span class="text-[11px] text-slate-400 block"><?= (int)($statsKorban['identified'] ?? $countKorban) ?> Terdata<?= !empty($statsKorban['unrecorded_dp']) ? " • {$statsKorban['unrecorded_dp']} Belum" : '' ?></span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            </div>
        </div>

        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Selamat (S)</span>
                <span class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono"><?= (int)($statsKorban['s'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400 block">Dievakuasi</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wider block">Meninggal (MD)</span>
                <span class="text-xl sm:text-2xl font-black text-red-600 dark:text-red-400 font-mono"><?= (int)($statsKorban['md'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400 block">Ditemukan</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-red-500/10 text-red-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            </div>
        </div>

        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider block">Pencarian (DP)</span>
                <span class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 font-mono"><?= (int)($statsKorban['dp'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400 block">Target Aktif</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Hilang / Nihil (H)</span>
                <span class="text-xl sm:text-2xl font-black text-slate-600 dark:text-slate-300 font-mono"><?= (int)($statsKorban['h'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400 block">Operasi Ditutup</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-500/10 text-slate-600 dark:text-slate-400 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/></svg>
            </div>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN CONTENT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Left: Victims Table (7 cols) -->
        <div class="lg:col-span-7 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden flex flex-col">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Identitas Korban</h3>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                    Total <?= (int)($statsKorban['total'] ?? $countKorban) ?> Orang (POB)
                </span>
            </div>

            <?php if (empty($operasi['korban']) && empty($statsKorban['unrecorded_dp'])): ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                <p>Tidak ada data korban tercatat.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 uppercase font-bold text-[10px] tracking-wider">
                            <th class="py-2.5 px-3.5 w-10 text-center">No</th>
                            <th class="py-2.5 px-3.5">Nama Korban</th>
                            <th class="py-2.5 px-3.5">Umur / JK</th>
                            <th class="py-2.5 px-3.5">Domisili</th>
                            <th class="py-2.5 px-3.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php foreach ($operasi['korban'] as $idx => $k): 
                            $st = $k['status_korban'];
                            $stUpper = strtoupper((string)$st);
                            if (in_array($stUpper, ['S', 'SELAMAT'], true)) {
                                $stClass = 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20';
                            } elseif (in_array($stUpper, ['MD', 'MENINGGAL', 'MENINGGAL DUNIA'], true)) {
                                $stClass = 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20';
                            } elseif (in_array($stUpper, ['H', 'HILANG', 'NIHIL'], true)) {
                                $stClass = 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border border-slate-500/30';
                            } else {
                                $stClass = 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20';
                            }
                        ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-2.5 px-3.5 text-center font-bold font-mono text-slate-400"><?= $idx + 1 ?></td>
                            <td class="py-2.5 px-3.5 font-bold text-slate-900 dark:text-white">
                                <?= e($k['nama_korban']) ?>
                            </td>
                            <td class="py-2.5 px-3.5 font-medium"><?= e($k['umur'] ?: '-') ?> (<?= e($k['jk'] ?: '-') ?>)</td>
                            <td class="py-2.5 px-3.5 text-slate-500 text-[11px]"><?= e($k['alamat'] ?: '-') ?></td>
                            <td class="py-2.5 px-3.5 text-center">
                                <span class="px-2 py-0.5 rounded-lg font-bold font-mono text-[10px] <?= $stClass ?>">
                                    <?= TemplateHelper::getLabelStatusKorban($st) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>

                        <?php if (!empty($statsKorban['unrecorded_dp'])): ?>
                        <tr class="bg-amber-500/5 dark:bg-amber-500/10 font-medium">
                            <td class="py-2.5 px-3.5 text-center font-bold font-mono text-amber-500">
                                <?= count($operasi['korban']) > 0 ? (count($operasi['korban']) + 1) : 1 ?>
                            </td>
                            <td class="py-2.5 px-3.5 font-bold text-amber-800 dark:text-amber-300">
                                <?= (int)$statsKorban['unrecorded_dp'] ?> Orang Korban Belum Terdata (POB)
                            </td>
                            <td class="py-2.5 px-3.5 text-amber-700 dark:text-amber-400 text-[11px]">-</td>
                            <td class="py-2.5 px-3.5 text-amber-700 dark:text-amber-400 text-[11px]">Identitas belum terverifikasi</td>
                            <td class="py-2.5 px-3.5 text-center">
                                <span class="px-2 py-0.5 rounded-lg font-bold font-mono text-[10px] bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                    Dalam Pencarian (DP)
                                </span>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Kronologi & Pelapor (5 cols) -->
        <div class="lg:col-span-5 space-y-5">
            <!-- Kronologi Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2.5">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Kronologi Kejadian</h3>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono"><?= $tglKejadian ?></span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800 text-xs leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-wrap font-sans">
                    <?= e(($operasi['kronologi'] ?? '') ?: 'Kronologi belum diinput.') ?>
                </div>
            </div>

            <!-- Pelapor & Kondisi Lapangan Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Informasi Pelapor & Cuaca</h3>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Pelapor</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e(($operasi['nama_pelapor'] ?? '') ?: '-') ?></strong>
                        <span class="text-[10px] text-slate-500">(<?= e(($operasi['hubungan_pelapor'] ?? '') ?: '-') ?>)</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Kontak Pelapor</span>
                        <strong class="text-slate-900 dark:text-white font-mono block truncate"><?= e(($operasi['no_telp_pelapor'] ?? '') ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Kondisi Cuaca</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e(($operasi['cuaca'] ?? '') ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Tinggi Gelombang</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e(($operasi['gelombang'] ?? '') ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Angin</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e(($operasi['angin'] ?? '') ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Faktor Penghambat</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e(($operasi['faktor_penghambat'] ?? '') ?: 'Nihil') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
