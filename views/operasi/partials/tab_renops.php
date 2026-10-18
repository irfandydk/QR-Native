<?php
/**
 * QR-SAR - Tab Rencana Operasi SAR (RENOPS)
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
?>
<div id="tab-content-renops" class="tab-pane space-y-4" style="display: <?= $activeTab === 'renops' ? 'block' : 'none' ?>;">

    <div class="flex items-center justify-between gap-3 mb-2">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
            <span>Daftar Rencana Operasi SAR (RENOPS) &bull; <?= $countRenops ?> Dokumen</span>
        </h3>
        <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat RENOPS <?= e($nextRenops['hari_ke'] ?? $hariKe) ?></span>
        </a>
        <?php endif; ?>
    </div>

    <?php if (!$isOpen): ?>
    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            <div>
                <h4 class="font-bold text-xs text-amber-900 dark:text-amber-100">Operasi Telah Ditutup (CLOSED)</h4>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">Penambahan RENOPS dinonaktifkan. Buka kembali operasi jika ada pembaharuan.</p>
            </div>
        </div>
        <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-xs transition whitespace-nowrap cursor-pointer">
            Buka Kembali Operasi
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($operasi['renops'])): ?>
    <div class="p-8 sm:p-12 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs text-center">
        <div class="w-12 h-12 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Belum Ada Rencana Operasi (RENOPS)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Lembar RENOPS memuat pembagian Search and Rescue Unit (SRU), sektor koordinat, dan pola pencarian per hari operasi.
        </p>
        <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat RENOPS <?= e($nextRenops['hari_ke'] ?? $hariKe) ?></span>
        </a>
        <?php endif; ?>
    </div>
    <?php else: foreach ($operasi['renops'] as $ren): 
        $snapUnsur = !empty($ren['detail_unsur']) && is_array($ren['detail_unsur']) ? $ren['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
        $snapAlut = !empty($ren['detail_alut']) && is_array($ren['detail_alut']) ? $ren['detail_alut'] : ($operasi['detail_alut'] ?? []);
        $totalSnapPers = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $snapUnsur));
        $totalSnapAlut = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $snapAlut));

        // Perhitungan hari dinamis sebenarnya dari tanggal pelaksanaan vs waktu mulai operasi SAR (menghormati siklus sebelum/sesudah reopen)
        $renTgl = !empty($ren['tanggal_laporan']) ? $ren['tanggal_laporan'] : (!empty($ren['waktu_briefing']) ? date('Y-m-d', strtotime($ren['waktu_briefing'])) : date('Y-m-d'));
        $dynamicHariKe = TemplateHelper::hitungHariKe($operasi, $renTgl, $ren['hari_ke'] ?? null);
        $renForWa = $ren;
        $renForWa['hari_ke'] = $dynamicHariKe;

        $renWa = $ren['full_text_wa'] ?? '';
        if (empty($renWa) || strpos($renWa, 'FILE AKTIF') === false || strpos($renWa, 'Data Korban:') !== false) {
            $renWa = TemplateHelper::generateRenopsWA($operasi, $renForWa, $ren['srus'] ?? [], $operasi['korban'] ?? [], $snapUnsur, $snapAlut);
        } elseif (!empty($ren['hari_ke']) && $ren['hari_ke'] !== $dynamicHariKe) {
            // PHP-05: Pertahankan snapshot historis full_text_wa; hanya sesuaikan label H.x pada header jika berbeda
            $renWa = preg_replace('/\bRENCANA (?:OPERASI|OPS) SAR\s+H(?:\.?\d+(?:\s*\+\s*\d+)?)/i', 'RENCANA OPS SAR ' . $dynamicHariKe, $renWa, 1) ?? $renWa;
        }
    ?>
    <div id="card-renops-<?= (int)$ren['id_renops'] ?>" class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden transition-all">
        <!-- Renops Header -->
        <div class="p-3.5 sm:p-4 border-b bg-slate-50/70 dark:bg-slate-800/40 border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-lg font-mono font-black text-xs bg-orange-600 text-white shadow-xs">
                    <?= e($dynamicHariKe) ?>
                </span>
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                    <span>Rencana: <?= TemplateHelper::formatTanggalIndo($ren['tanggal_laporan']) ?></span>
                </span>
            </div>

            <!-- Action Toolbar for Renops -->
            <div class="flex items-center gap-1.5 flex-wrap no-print">
                <button type="button" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($renWa), ENT_QUOTES) ?>, "Naskah RENOPS WhatsApp Berhasil Disalin!")'>
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    <span>Salin WA</span>
                </button>

                <a href="<?= BASE_URL ?>/operasi.php?action=print_renops&id=<?= $id ?>&id_renops=<?= (int)$ren['id_renops'] ?>" target="_blank" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 text-blue-700 dark:text-blue-300 font-semibold text-xs transition cursor-pointer" title="Cetak Berkas Lembar Resmi RENOPS (A4 Basarnas)">
                    <svg class="w-3.5 h-3.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak Berkas</span>
                </a>

                <?php if ($isOpen): ?>
                <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>&id_renops=<?= $ren['id_renops'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                    <span>Edit</span>
                </a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=renops&id_renops=<?= $ren['id_renops'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/20 font-semibold text-xs transition cursor-pointer" title="Buat Berita Radio dari RENOPS ini">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                    <span>BRA Renops</span>
                </a>

                <?php if ($isOpen && AuthHelper::canDelete()): ?>
                <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer" onclick="deleteRenops(<?= (int)$ren['id_renops'] ?>)" title="Hapus Lembar Renops">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Renops Body -->
        <div class="p-4 sm:p-5 space-y-4">
            <!-- Timetable & Cuaca Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Waktu Briefing</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono"><?= TemplateHelper::formatTW($ren['waktu_briefing']) ?></span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Mulai Operasi</span>
                    <span class="font-bold text-orange-600 dark:text-orange-400 font-mono"><?= TemplateHelper::formatTW($ren['waktu_mulai_ops']) ?></span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Prakiraan Cuaca BMKG</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 truncate block"><?= e($ren['cuaca']) ?></span>
                </div>
            </div>

            <!-- SRU Table -->
            <div>
                <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                    <span>Pembagian Sektor & Search and Rescue Unit (SRU):</span>
                </h4>
                <div class="table-responsive rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <table class="table text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th style="width: 25%;">Nama SRU</th>
                                <th style="width: 45%;">Koordinat Sektor & Tugas</th>
                                <th style="width: 15%;">Luas Area</th>
                                <th style="width: 15%;">Pola & Total Sapuan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ren['srus'])): ?>
                            <tr><td colspan="4" class="text-slate-400 text-center py-3">Tidak ada pembagian SRU tersimpan</td></tr>
                            <?php else: foreach ($ren['srus'] as $s): ?>
                            <tr>
                                <td class="font-bold text-orange-600 dark:text-orange-400"><?= e($s['nama_sru']) ?></td>
                                <td class="font-mono text-xs whitespace-pre-wrap"><?= e($s['koordinat_area']) ?></td>
                                <td class="font-semibold text-xs"><?= e($s['luas_area']) ?></td>
                                <td>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 block text-center"><?= e($s['pola_pencarian']) ?></span>
                                    <?php if (!empty($s['total_sapuan'])): ?>
                                     <div class="text-[10px] font-mono font-semibold text-emerald-600 dark:text-emerald-400 mt-1 text-center" title="Total Sapuan / Lintasan"><?= e($s['total_sapuan']) ?></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Snapshot Potensi & Alut Renops -->
            <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        <span>Potensi SAR & Armada Terlibat (Snapshot <?= e($dynamicHariKe) ?>):</span>
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-mono font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                        <?= $totalSnapPers ?> Personel &bull; <?= $totalSnapAlut ?> Unit Alut
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Unsur SAR Terlibat:</span>
                        <div class="space-y-1">
                            <?php if (empty($snapUnsur)): ?>
                            <span class="text-slate-400">-</span>
                            <?php else: foreach ($snapUnsur as $u): ?>
                            <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                <span>&bull; <?= e($u['nama_unsur']) ?></span>
                                <strong class="text-purple-600 dark:text-purple-400 font-mono"><?= (int)($u['jumlah_personil'] ?? 1) ?> Org</strong>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Armada Alut & Palsar:</span>
                        <div class="space-y-1">
                            <?php if (empty($snapAlut)): ?>
                            <span class="text-slate-400">-</span>
                            <?php else: foreach ($snapAlut as $a): ?>
                            <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                <span>&bull; <?= e($a['nama_alut']) ?></span>
                                <strong class="text-emerald-600 dark:text-emerald-400 font-mono"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></strong>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Text Format Accordion -->
            <details class="group rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 p-3 transition">
                <summary class="flex items-center justify-between cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300 select-none">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <span>Format Naskah WhatsApp Siap Kirim (<?= e($dynamicHariKe) ?>)</span>
                    </span>
                    <span class="text-slate-400 group-open:rotate-180 transition-transform text-[11px] font-mono">&dtrif;</span>
                </summary>
                <div class="mt-2.5 pt-2.5 border-t border-slate-200 dark:border-slate-800">
                    <pre class="font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed select-all max-h-72 overflow-y-auto"><?= e($renWa) ?></pre>
                </div>
            </details>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>
