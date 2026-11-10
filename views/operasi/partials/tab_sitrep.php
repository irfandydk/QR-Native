<?php
/**
 * QR-SAR - Tab Laporan Perkembangan (LAPBANG)
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
?>
<div id="tab-content-sitrep" class="tab-pane space-y-4" style="display: <?= ($activeTab === 'sitrep' || $activeTab === 'laporan') ? 'block' : 'none' ?>;">

    <div class="flex items-center justify-between gap-3 mb-2">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            <span>Daftar Laporan Perkembangan (LAPBANG) &bull; <?= $countSitrep ?> Dokumen</span>
        </h3>
        <div class="flex items-center gap-2">
            <?php if ($isOpen): ?>
            <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span>+ Terbitkan LAPBANG <?= e($nextLapbang['hari_ke'] ?? $hariKe) ?></span>
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&kategori=OPERASI_SAR" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-white border border-slate-200 dark:border-slate-700 font-semibold text-xs transition" title="Buat Berita Radio dari Laporan Perkembangan">
                <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                <span>BRA Operasi SAR</span>
            </a>
        </div>
    </div>

    <?php if (!$isOpen): ?>
    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 00-2.25 2.25z"/></svg>
            <div>
                <h4 class="font-bold text-xs text-amber-900 dark:text-amber-100">Operasi Telah Ditutup (CLOSED)</h4>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">Penambahan LAPBANG dinonaktifkan demi integritas arsip.</p>
            </div>
        </div>
        <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-xs transition whitespace-nowrap cursor-pointer">
            Buka Kembali Operasi
        </button>
    </div>
    <?php endif; ?>

    <!-- LIST FEED LAPBANG (BERSIH TANPA KARTU KPI REDUNDAN) -->
    <?php if (empty($operasi['laporan_harian'])): ?>
    <div class="p-8 sm:p-12 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm text-center">
        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Belum Ada Laporan Perkembangan (LAPBANG)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Laporan perkembangan memuat evaluasi hasil pencarian harian tim gabungan dan debriefing lapangan.
        </p>
        <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-sm transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Terbitkan LAPBANG <?= e($nextLapbang['hari_ke'] ?? $hariKe) ?></span>
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($operasi['laporan_harian'] as $idx => $lap): 
            $isPenutupan = ($lap['jenis_laporan'] ?? '') === 'PENUTUPAN';
            $snapUnsur = !empty($lap['detail_unsur']) && is_array($lap['detail_unsur']) ? $lap['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
            $snapAlut = !empty($lap['detail_alut']) && is_array($lap['detail_alut']) ? $lap['detail_alut'] : ($operasi['detail_alut'] ?? []);
            $totalSnapPers = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $snapUnsur));
            $totalSnapAlut = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $snapAlut));

            // Perhitungan hari dinamis sebenarnya dari tanggal laporan vs waktu mulai operasi SAR (menghormati siklus sebelum/sesudah reopen)
            $lapTgl = !empty($lap['waktu_laporan']) ? date('Y-m-d', strtotime($lap['waktu_laporan'])) : (!empty($lap['tanggal_laporan']) ? $lap['tanggal_laporan'] : date('Y-m-d'));
            $dynamicHariKeLap = TemplateHelper::hitungHariKe($operasi, $lapTgl, $lap['hari_ke'] ?? null);
            $lapForWa = $lap;
            $lapForWa['hari_ke'] = $dynamicHariKeLap;

            $lapWa = $lap['full_text_wa'] ?? '';
            if (empty($lapWa) || strpos($lapWa, 'FILE AKTIF') === false) {
                $lapWa = TemplateHelper::generateLapbangWA($operasi, $lapForWa, $operasi['korban'] ?? [], $snapUnsur, $snapAlut, $isPenutupan);
            } elseif (!empty($lap['hari_ke']) && $lap['hari_ke'] !== $dynamicHariKeLap) {
                // PHP-05: Pertahankan snapshot historis full_text_wa; hanya sesuaikan label H.x pada header jika berbeda
                $lapWa = preg_replace('/\b(?:LAPORAN PERKEMBANGAN|LAPBANG OPS SAR)(?:\s+DAN\s+PENUTUPAN)?\s+H(?:\.?\d+(?:\s*\+\s*\d+)?)/i', ($isPenutupan ? "LAPBANG OPS SAR {$dynamicHariKeLap} DAN PENUTUPAN" : "LAPBANG OPS SAR {$dynamicHariKeLap}"), $lapWa, 1) ?? $lapWa;
            }
        ?>
        <div id="card-sitrep-<?= (int)($lap['id_laporan'] ?? ($lap['id_lapbang'] ?? 0)) ?>" class="rounded-2xl bg-white dark:bg-slate-900 border <?= $isPenutupan ? 'border-red-500/30 dark:border-red-900/50' : 'border-slate-200 dark:border-slate-800' ?> shadow-xs overflow-hidden transition-all">
            
            <!-- Lapbang Header -->
            <div class="p-3.5 sm:p-4 border-b <?= $isPenutupan ? 'bg-red-50/40 dark:bg-red-950/20 border-red-200/50 dark:border-red-900/40' : 'bg-slate-50/70 dark:bg-slate-800/40 border-slate-100 dark:border-slate-800' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-lg font-mono font-bold text-xs <?= $isPenutupan ? 'bg-red-500 text-white shadow-xs' : 'bg-blue-600 text-white shadow-xs' ?>">
                        <?= $isPenutupan ? 'PENUTUPAN OPERASI' : "LAPBANG " . e($dynamicHariKeLap) ?>
                    </span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                        <span><?= TemplateHelper::formatTanggalIndo($lap['waktu_laporan']) ?></span>
                        <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                        <span class="font-mono text-blue-600 dark:text-blue-400"><?= TemplateHelper::formatJamWITA($lap['waktu_laporan']) ?> WITA</span>
                    </span>
                </div>

                <!-- Action Toolbar -->
                <div class="flex items-center gap-1.5 flex-wrap no-print">
                    <button type="button" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-sitrep-<?= (int)($lap['id_laporan'] ?? ($lap['id_lapbang'] ?? 0)) ?>')" title="Lihat pratinjau format WhatsApp LAPBANG">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <span>Naskah WA</span>
                    </button>


                    <?php if ($isOpen): ?>
                    <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>&id_laporan=<?= $lap['id_laporan'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                        <span>Edit</span>
                    </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=sitrep&id_laporan=<?= $lap['id_laporan'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/20 font-semibold text-xs transition cursor-pointer" title="Buat Berita Radio dari laporan ini">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                        <span>BRA LAPBANG</span>
                    </a>

                    <?php if ($isOpen && AuthHelper::canDelete()): ?>
                    <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer" onclick="deleteLaporan(<?= (int)$lap['id_laporan'] ?>)" title="Hapus Laporan Perkembangan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Lapbang Body -->
            <div class="p-4 sm:p-5 space-y-4">
                <!-- Hasil Pencarian -->
                <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200/70 dark:border-slate-800 text-xs sm:text-sm leading-relaxed text-slate-800 dark:text-slate-200">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <span class="font-bold text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">Hasil Pencarian & Tindakan Lapangan:</span>
                    </div>
                    <div class="whitespace-pre-wrap font-sans pl-1">
                        <?= nl2br(e($lap['isi_laporan'])) ?>
                    </div>
                    
                    <?php if (!empty($lap['waktu_debrief'])): ?>
                        <?php if ($isPenutupan): ?>
                        <div class="mt-3 p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/60 flex items-center gap-2 text-red-600 dark:text-red-400 font-bold text-xs">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                            <span>Debriefing Resmi Penutupan: <?= TemplateHelper::formatTanggalIndo($lap['waktu_debrief']) ?> Pukul <?= TemplateHelper::formatJamWITA($lap['waktu_debrief']) ?> WITA</span>
                        </div>
                        <?php else: ?>
                        <div class="mt-3 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/80 flex items-center gap-2 text-slate-700 dark:text-slate-300 font-semibold text-xs">
                            <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Penutupan Sementara: <?= TemplateHelper::formatTanggalIndo($lap['waktu_debrief']) ?> Pukul <?= TemplateHelper::formatJamWITA($lap['waktu_debrief']) ?> WITA</span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <!-- Snapshot Unsur & Alut -->
                <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                            <span>Potensi & Alut Terlibat (Snapshot <?= e($dynamicHariKeLap) ?>):</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full font-mono text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                            <?= $totalSnapPers ?> Personel &bull; <?= $totalSnapAlut ?> Unit Alut
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Potensi SAR Gabungan:</span>
                            <div class="space-y-1">
                                <?php if (empty($snapUnsur)): ?>
                                <span class="text-slate-400 text-xs italic">Tidak ada catatan unsur</span>
                                <?php else: foreach ($snapUnsur as $u): ?>
                                <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">&bull; <?= e($u['nama_unsur']) ?></span>
                                    <span class="font-mono font-bold text-purple-600 dark:text-purple-400"><?= (int)($u['jumlah_personil'] ?? 1) ?> Org</span>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Armada Alut & Palsar:</span>
                            <div class="space-y-1">
                                <?php if (empty($snapAlut)): ?>
                                <span class="text-slate-400 text-xs italic">Tidak ada catatan alut</span>
                                <?php else: foreach ($snapAlut as $a): ?>
                                <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">&bull; <?= e($a['nama_alut']) ?></span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></span>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL: VIEW WA LAPBANG -->
        <div id="modal-view-wa-sitrep-<?= (int)($lap['id_laporan'] ?? ($lap['id_lapbang'] ?? 0)) ?>" class="modal-overlay">
            <div class="modal-container" style="max-width: 760px; width: 95%;">
                <div class="modal-header flex items-center justify-between border-b border-slate-100 dark:border-slate-800 p-4">
                    <div class="min-w-0 pr-3">
                        <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                            <span>Naskah <?= $isPenutupan ? 'Penutupan Operasi' : "LAPBANG (" . e($dynamicHariKeLap) . ")" ?> WhatsApp Resmi</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5 truncate" title="<?= e($operasi['nama_operasi']) ?>"><?= e($operasi['nama_operasi']) ?></p>
                    </div>
                    <button type="button" class="btn-tactile p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer shrink-0" onclick="closeModal('modal-view-wa-sitrep-<?= (int)($lap['id_laporan'] ?? ($lap['id_lapbang'] ?? 0)) ?>')" data-close-modal title="Tutup">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="modal-body p-4 overflow-y-auto flex-1">
                    <pre id="wa-sitrep-text-<?= (int)($lap['id_laporan'] ?? ($lap['id_lapbang'] ?? 0)) ?>" class="font-mono p-4 rounded-xl text-xs bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-800 leading-relaxed select-all whitespace-pre-wrap m-0"><?= e($lapWa) ?></pre>
                </div>
                <div class="modal-footer flex items-center justify-between p-4 border-t border-slate-100 dark:border-slate-800">
                    <span class="text-[11px] text-slate-400 font-mono">Format Baku FILE AKTIF</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-view-wa-sitrep-<?= (int)($lap['id_laporan'] ?? ($lap['id_lapbang'] ?? 0)) ?>')" data-close-modal>Tutup</button>
                        <button type="button" class="btn btn-primary btn-sm inline-flex items-center gap-1.5" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($lapWa), ENT_QUOTES) ?>, "Naskah LAPBANG WhatsApp Berhasil Disalin!")'>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                            <span>Salin Naskah</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
