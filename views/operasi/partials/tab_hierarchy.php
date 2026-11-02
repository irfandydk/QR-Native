<?php
/**
 * QR-SAR - Tab Hirarki Dokumen Operasi SAR
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
?>
<div id="tab-content-hierarchy" class="tab-pane space-y-4" style="display: <?= $activeTab === 'hierarchy' ? 'block' : 'none' ?>;">
    <div class="flex items-center justify-between gap-3 mb-2">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
            <span>Alur Kronologis Dokumen Operasi SAR &bull; <?= count($timeline ?? []) ?> Dokumen Terbit</span>
        </h3>
    </div>

    <div class="relative pl-6 sm:pl-8 space-y-3.5 before:absolute before:left-3 sm:before:left-4 before:top-3 before:bottom-3 before:w-0.5 before:bg-gradient-to-b before:from-orange-500/40 before:via-slate-200 dark:before:via-slate-800 before:to-slate-200 dark:before:to-slate-800">
        <?php foreach (($timeline ?? []) as $idx => $doc): 
            $docType = $doc['type'];
            $isAwal = $docType === 'LAPORAN_AWAL';
            $isRenops = $docType === 'RENOPS';
            $isPenutupan = $docType === 'PENUTUPAN';
            $isLapbang = $docType === 'LAPBANG';

            $nodeColor = $isRenops ? 'bg-orange-500 text-white' : ($isPenutupan ? 'bg-red-500 text-white' : ($isLapbang ? 'bg-emerald-600 text-white' : 'bg-blue-600 text-white'));
            $badgeClass = $isRenops ? 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20' : ($isPenutupan ? 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20' : ($isLapbang ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20'));
        ?>
        <div class="relative">
            <!-- Node Pin -->
            <div class="absolute -left-6 sm:-left-8 top-1/2 -translate-y-1/2 w-6 h-6 sm:w-7 sm:h-7 rounded-full <?= $nodeColor ?> flex items-center justify-center font-mono font-bold text-[11px] shadow-sm ring-4 ring-white dark:ring-slate-900 z-10">
                <?= $idx + 1 ?>
            </div>

            <!-- Item Card (Concentric Double-Bezel Architecture) -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-800/90 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-slate-300 dark:hover:border-slate-700 transition">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-lg font-mono text-[11px] font-bold border <?= $badgeClass ?>">
                            <?= e($docType) ?> • <?= e($doc['hari_ke']) ?>
                        </span>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                            <?= e($doc['label']) ?>
                        </h4>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-1 font-mono">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                            <span><?= TemplateHelper::formatTanggalIndo($doc['waktu']) ?> (<?= TemplateHelper::formatJamWITA($doc['waktu']) ?> WITA)</span>
                        </span>
                        <?php if (!empty($doc['cuaca'])): ?>
                        <span class="text-slate-300 dark:text-slate-700 hidden sm:inline">&bull;</span>
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                            <span><?= e($doc['cuaca']) ?></span>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    <?php if ($isAwal): ?>
                    <button type="button" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-awal')">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <span>Naskah WA</span>
                    </button>
                    <?php elseif ($isRenops): ?>
                    <button type="button" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-renops-<?= (int)($doc['doc_id'] ?? 0) ?>')" title="Lihat pratinjau format WhatsApp RENOPS">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <span>Naskah WA</span>
                    </button>
                    <button type="button" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/25 font-semibold text-xs transition cursor-pointer" onclick="navigateToDoc('renops', <?= (int)($doc['doc_id'] ?? 0) ?>)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                        <span>Buka RENOPS</span>
                    </button>
                    <?php elseif ($isLapbang || $isPenutupan): ?>
                    <button type="button" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-sitrep-<?= (int)($doc['doc_id'] ?? 0) ?>')" title="Lihat pratinjau format WhatsApp LAPBANG">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <span>Naskah WA</span>
                    </button>
                    <button type="button" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-white border border-emerald-500/25 font-semibold text-xs transition cursor-pointer" onclick="navigateToDoc('sitrep', <?= (int)($doc['doc_id'] ?? 0) ?>)">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <span>Buka LAPBANG</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
