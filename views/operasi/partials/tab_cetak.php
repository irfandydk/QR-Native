<?php
/**
 * QR-SAR - Tab Cetak Dokumen Resmi Operasi SAR
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
require_once dirname(__DIR__, 3) . '/models/DokumenPetaSnapshotModel.php';
$opsMapSnapshots = DokumenPetaSnapshotModel::getAllByOperasi((int)$id);
$hasSnapLaporanAwal = false;
$hasSnapRenops = false;
$hasSnapPenutupan = false;
foreach ($opsMapSnapshots as $snap) {
    $jDoc = strtoupper((string)($snap['jenis_dokumen'] ?? ''));
    if ($jDoc === 'LAPORAN_AWAL') $hasSnapLaporanAwal = true;
    elseif ($jDoc === 'RENOPS') $hasSnapRenops = true;
    elseif ($jDoc === 'PENUTUPAN') $hasSnapPenutupan = true;
}
?>
<div id="tab-content-cetak" class="tab-pane space-y-4" style="display: <?= $activeTab === 'cetak' ? 'block' : 'none' ?>;">
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak &amp; Download PDF Dokumen Resmi Operasi SAR</span>
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Pilih berkas resmi yang ingin dicetak / diunduh PDF, atau ambil cuplikan peta taktis 16:9 untuk Laporan Awal (1x), RENOPS (per hari), dan Penutupan (1x):</p>
            </div>
            <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=LAPORAN_AWAL" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-xs transition no-underline shrink-0">
                <span>Studio Capture Peta 16:9</span>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-4 gap-3.5">
            <!-- Card 1: Laporan Awal -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 rounded-lg bg-orange-500/10 text-orange-600 flex items-center justify-center font-bold text-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        </div>
                        <?php if ($hasSnapLaporanAwal): ?>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Peta 16:9 Siap (1x)</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-200/70 dark:bg-slate-800 text-slate-500">Tanpa Peta</span>
                        <?php endif; ?>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">1. Berkas Laporan Awal</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Naskah dinas resmi Basarnas lengkap dengan Kop, Peta LKP 16:9 (1x capture), tanda tangan &amp; QR verifikasi.</p>
                </div>
                <div class="space-y-1.5">
                    <div class="grid grid-cols-2 gap-1.5">
                        <a href="<?= BASE_URL ?>/operasi.php?action=print_laporan_awal&id=<?= $id ?>" target="_blank" class="py-2 px-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-[11px] transition shadow-xs cursor-pointer flex items-center justify-center gap-1 no-underline">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                            <span>Cetak</span>
                        </a>
                        <a href="<?= BASE_URL ?>/operasi.php?action=print_laporan_awal&id=<?= $id ?>&download_pdf=1" target="_blank" class="py-2 px-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] transition shadow-xs cursor-pointer flex items-center justify-center gap-1 no-underline" title="Download PDF Laporan Awal">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                            <span>Unduh PDF</span>
                        </a>
                    </div>
                    <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=LAPORAN_AWAL" class="w-full py-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold text-[11px] transition flex items-center justify-center gap-1 no-underline">
                        <span><?= $hasSnapLaporanAwal ? 'Perbarui Peta 16:9 (1x)' : 'Capture Peta 16:9 (1x)' ?></span>
                    </a>
                </div>
            </div>

            <!-- Card 2: RENOPS -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                        </div>
                        <?php if ($hasSnapRenops): ?>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Peta Harian Siap</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-200/70 dark:bg-slate-800 text-slate-500">Tanpa Peta</span>
                        <?php endif; ?>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">2. Rencana Operasi SAR</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Lembar RENOPS harian: BAB II memuat Peta 16:9 per Hari RENOPS, Pembagian SRU &amp; Legenda Peta.</p>
                </div>
                <div class="space-y-1.5">
                    <?php if (!empty($operasi['renops'])): ?>
                        <?php if (count($operasi['renops']) === 1): 
                            $singleRenopsId = (int)$operasi['renops'][0]['id_renops'];
                        ?>
                        <div class="grid grid-cols-2 gap-1.5">
                            <a href="<?= BASE_URL ?>/operasi.php?action=print_renops&id=<?= $id ?>&id_renops=<?= $singleRenopsId ?>" target="_blank" class="py-2 px-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-[11px] text-center transition shadow-xs flex items-center justify-center gap-1 cursor-pointer no-underline">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                                <span>Cetak</span>
                            </a>
                            <a href="<?= BASE_URL ?>/operasi.php?action=print_renops&id=<?= $id ?>&id_renops=<?= $singleRenopsId ?>&download_pdf=1" target="_blank" class="py-2 px-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] text-center transition shadow-xs flex items-center justify-center gap-1 cursor-pointer no-underline" title="Download PDF RENOPS">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                <span>Unduh PDF</span>
                            </a>
                        </div>
                        <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=RENOPS&capture_ref=<?= $singleRenopsId ?>" class="w-full py-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold text-[11px] transition flex items-center justify-center gap-1 no-underline">
                            <span><?= $hasSnapRenops ? 'Perbarui Peta RENOPS' : 'Capture Peta RENOPS' ?></span>
                        </a>
                        <?php else: ?>
                        <div class="space-y-1.5">
                            <select id="select-renops-cetak" class="control control-sm w-full">
                                <?php foreach ($operasi['renops'] as $r): ?>
                                <option value="<?= (int)$r['id_renops'] ?>"><?= e($r['hari_ke']) ?> &bull; <?= TemplateHelper::formatTanggalSimple($r['tanggal_laporan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="grid grid-cols-2 gap-1.5">
                                <button type="button" onclick="const rid = document.getElementById('select-renops-cetak').value; window.open('<?= BASE_URL ?>/operasi.php?action=print_renops&id=<?= $id ?>&id_renops=' + rid, '_blank');" class="py-1.5 px-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-[11px] transition shadow-xs flex items-center justify-center gap-1 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                                    <span>Cetak</span>
                                </button>
                                <button type="button" onclick="const rid = document.getElementById('select-renops-cetak').value; window.open('<?= BASE_URL ?>/operasi.php?action=print_renops&id=<?= $id ?>&id_renops=' + rid + '&download_pdf=1', '_blank');" class="py-1.5 px-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] transition shadow-xs flex items-center justify-center gap-1 cursor-pointer" title="Download PDF RENOPS Terpilih">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                    <span>Unduh PDF</span>
                                </button>
                            </div>
                            <button type="button" onclick="const rid = document.getElementById('select-renops-cetak').value; window.location.href='<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=RENOPS&capture_ref=' + rid;" class="w-full py-1.5 rounded-xl border border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold text-[11px] transition flex items-center justify-center gap-1 cursor-pointer">
                                <span>Capture Peta Hari Terpilih</span>
                            </button>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="block text-[11px] text-slate-400 text-center py-1">Belum ada RENOPS terbit</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 3: BRA SAR -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-xs mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">3. Berita Radio SAR</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Naskah telegram resmi Basarnas dengan verifikasi QR Digital.</p>
                </div>
                <?php if (!empty($operasi['bra_sar'])): ?>
                    <?php if (count($operasi['bra_sar']) === 1): ?>
                    <a href="<?= BASE_URL ?>/bra.php?view=<?= (int)$operasi['bra_sar'][0]['id_bra'] ?>" class="w-full py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs text-center transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                        <span>Cetak Dokumen BRA</span>
                    </a>
                    <?php else: ?>
                    <div class="space-y-1.5">
                        <select id="select-bra-cetak" class="control control-sm w-full">
                            <?php foreach ($operasi['bra_sar'] as $b): ?>
                            <option value="<?= (int)$b['id_bra'] ?>">BRA #<?= (int)$b['id_bra'] ?> &bull; <?= e(!empty($b['no_bra']) ? $b['no_bra'] : $b['perihal']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" onclick="const bid = document.getElementById('select-bra-cetak').value; window.location.href='<?= BASE_URL ?>/bra.php?view=' + bid;" class="w-full py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                            <span>Cetak BRA Terpilih</span>
                        </button>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                <span class="text-[11px] text-slate-400 text-center py-2">Belum ada BRA terbit</span>
                <?php endif; ?>
            </div>

            <!-- Card 4: Perkembangan & Penutupan -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-600 flex items-center justify-center font-bold text-xs">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                        </div>
                        <?php if ($hasSnapPenutupan): ?>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">Peta Penutupan Siap (1x)</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-200/70 dark:bg-slate-800 text-slate-500">Tanpa Peta</span>
                        <?php endif; ?>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">4. Perkembangan &amp; Penutupan</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Laporan Perkembangan (SITREP) serta Perkembangan &amp; Penutupan Operasi SAR (Peta 16:9 1x).</p>
                </div>
                <div class="space-y-1.5">
                    <div class="grid grid-cols-2 gap-1.5">
                        <a href="<?= BASE_URL ?>/operasi.php?action=print_penutupan&id=<?= $id ?>" target="_blank" class="py-1.5 px-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-[11px] transition shadow-xs cursor-pointer flex items-center justify-center gap-1 no-underline">
                            <span>Cetak Penutupan</span>
                        </a>
                        <a href="<?= BASE_URL ?>/operasi.php?action=print_penutupan&id=<?= $id ?>&download_pdf=1" target="_blank" class="py-1.5 px-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] transition shadow-xs cursor-pointer flex items-center justify-center gap-1 no-underline" title="Download PDF Berita Acara Penutupan">
                            <span>PDF Penutupan</span>
                        </a>
                    </div>
                    <?php if (!empty($operasi['laporan_harian'])): ?>
                        <?php if (count($operasi['laporan_harian']) === 1): 
                            $singleLapId = (int)$operasi['laporan_harian'][0]['id_laporan'];
                        ?>
                        <div class="grid grid-cols-2 gap-1.5 pt-1 border-t border-slate-200 dark:border-slate-700/60">
                            <a href="<?= BASE_URL ?>/operasi.php?action=print_sitrep&id=<?= $id ?>&id_laporan=<?= $singleLapId ?>" target="_blank" class="py-1.5 px-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-[11px] transition flex items-center justify-center gap-1 no-underline">
                                <span>Cetak SITREP</span>
                            </a>
                            <a href="<?= BASE_URL ?>/operasi.php?action=print_sitrep&id=<?= $id ?>&id_laporan=<?= $singleLapId ?>&download_pdf=1" target="_blank" class="py-1.5 px-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] transition flex items-center justify-center gap-1 no-underline" title="Download PDF SITREP">
                                <span>PDF SITREP</span>
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="space-y-1 pt-1 border-t border-slate-200 dark:border-slate-700/60">
                            <select id="select-sitrep-cetak" class="control control-sm w-full">
                                <?php foreach ($operasi['laporan_harian'] as $lh): ?>
                                <option value="<?= (int)$lh['id_laporan'] ?>"><?= e($lh['hari_ke']) ?> &bull; <?= strtoupper($lh['jenis_laporan']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="grid grid-cols-2 gap-1.5">
                                <button type="button" onclick="const lid = document.getElementById('select-sitrep-cetak').value; window.open('<?= BASE_URL ?>/operasi.php?action=print_sitrep&id=<?= $id ?>&id_laporan=' + lid, '_blank');" class="py-1.5 px-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-[11px] transition flex items-center justify-center gap-1 cursor-pointer">
                                    <span>Cetak SITREP</span>
                                </button>
                                <button type="button" onclick="const lid = document.getElementById('select-sitrep-cetak').value; window.open('<?= BASE_URL ?>/operasi.php?action=print_sitrep&id=<?= $id ?>&id_laporan=' + lid + '&download_pdf=1', '_blank');" class="py-1.5 px-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] transition flex items-center justify-center gap-1 cursor-pointer" title="Download PDF SITREP Terpilih">
                                    <span>PDF SITREP</span>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=PENUTUPAN" class="w-full py-1.5 px-2 rounded-xl border border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold text-[11px] transition flex items-center justify-center gap-1 no-underline text-center">
                        <span><?= $hasSnapPenutupan ? 'Perbarui Peta Penutupan (1x)' : 'Capture Peta Penutupan (1x)' ?></span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
