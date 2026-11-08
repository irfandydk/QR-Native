<?php
/**
 * QR-SAR - Tab Cetak Dokumen Resmi Operasi SAR: hanya Rencana Operasi SAR (per hari, draf/terbit, capture
 * Peta Lokasi & Peta Rencana Operasi) dan Berita Radio SAR.
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
require_once dirname(__DIR__, 3) . '/models/DokumenPetaSnapshotModel.php';
$renopsDocStatus = RenopsDokumenModel::statusByOperasi((int)$id);
// Peta Lokasi (LKP) 1x per operasi; Peta Rencana Operasi per hari RENOPS: [id_renops] = true
$adaPetaLkp = false;
$adaPetaRencana = [];
foreach (DokumenPetaSnapshotModel::getAllByOperasi((int)$id) as $snap) {
    $jDoc = strtoupper((string)($snap['jenis_dokumen'] ?? ''));
    if ($jDoc === 'RENOPS' && empty($snap['id_referensi'])) {
        $adaPetaLkp = true;
    } elseif ($jDoc === 'RENOPS_RENCANA' && !empty($snap['id_referensi'])) {
        $adaPetaRencana[(int)$snap['id_referensi']] = true;
    }
}
$captureLkpUrl = BASE_URL . '/gis.php?id_operasi=' . (int)$id . '&include_base=1&capture_doc=RENOPS';
?>
<div id="tab-content-cetak" class="tab-pane space-y-4" style="display: <?= $activeTab === 'cetak' ? 'block' : 'none' ?>;">
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                <span>Cetak &amp; Download PDF Dokumen Resmi Operasi SAR</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Rencana Operasi SAR per hari (draf bisa dicetak sebelum QR terbit) dan Berita Radio SAR.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
            <!-- Card 1: RENOPS -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-xs mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">1. Rencana Operasi SAR</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Format SMEAC: Peta Lokasi LKP (1x per operasi), Peta Rencana Operasi (per hari), foto dokumentasi, terbit QR. Draf dicetak dengan watermark DRAF tanpa QR.</p>
                    <a href="<?= e($captureLkpUrl) ?>" class="mt-2 block py-1.5 rounded-lg border border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold text-[11px] text-center transition no-underline"><?= $adaPetaLkp ? '&#10003; Peta Lokasi LKP (perbarui)' : 'Capture Peta Lokasi LKP (1x per operasi)' ?></a>
                </div>
                <div class="space-y-1.5 max-h-96 overflow-y-auto">
                    <?php if (!empty($operasi['renops'])): ?>
                        <?php foreach ($operasi['renops'] as $r):
                            $rid = (int)$r['id_renops'];
                            $terbitVersi = $renopsDocStatus[$rid]['terbit_versi'] ?? null;
                            $printRenopsUrl = BASE_URL . '/operasi.php?action=print_renops&id=' . (int)$id . '&id_renops=' . $rid;
                            $captureRencanaUrl = BASE_URL . '/gis.php?id_operasi=' . (int)$id . '&include_base=1&capture_doc=RENOPS_RENCANA&capture_ref=' . $rid;
                            $adaRencana = !empty($adaPetaRencana[$rid]);
                        ?>
                        <div class="p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/40 space-y-1.5">
                            <div class="flex items-center justify-between gap-1">
                                <span class="text-[11px] font-bold text-slate-800 dark:text-slate-200"><?= e($r['hari_ke']) ?> &bull; <?= e(TemplateHelper::formatTanggalSimple($r['tanggal_laporan'])) ?></span>
                                <?php if ($terbitVersi): ?>
                                    <span class="px-1.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">TERBIT v<?= (int)$terbitVersi ?></span>
                                <?php else: ?>
                                    <span class="px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">DRAF &bull; belum terbit</span>
                                <?php endif; ?>
                            </div>
                            <div class="grid grid-cols-3 gap-1">
                                <a href="<?= BASE_URL ?>/operasi.php?action=renops_dokumen&id_renops=<?= $rid ?>" class="py-1.5 px-1 rounded-lg border border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-[11px] text-center transition no-underline">Edit Draf</a>
                                <a href="<?= e($printRenopsUrl) ?>" target="_blank" class="py-1.5 px-1 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold text-[11px] text-center transition no-underline"><?= $terbitVersi ? 'Cetak' : 'Cetak Draf' ?></a>
                                <a href="<?= e($printRenopsUrl . '&download_pdf=1') ?>" target="_blank" class="py-1.5 px-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] text-center transition no-underline" title="Download PDF RENOPS"><?= $terbitVersi ? 'Unduh PDF' : 'PDF Draf' ?></a>
                            </div>
                            <a href="<?= e($captureRencanaUrl) ?>" class="block py-1 rounded-lg border border-emerald-500/40 bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 font-bold text-[11px] text-center transition no-underline"><?= $adaRencana ? '&#10003; Peta Rencana Operasi ' . e($r['hari_ke']) . ' (perbarui)' : 'Capture Peta Rencana Operasi ' . e($r['hari_ke']) ?></a>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="block text-[11px] text-slate-400 text-center py-1">Belum ada RENOPS</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card 2: BRA SAR -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-xs mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">2. Berita Radio SAR</h4>
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

        </div>
    </div>
</div>
