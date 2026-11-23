<?php
/**
 * QR-SAR - Tab Cetak Dokumen Resmi Operasi SAR: hanya Rencana Operasi SAR (per hari, draf/terbit, capture
 * Peta Lokasi & Peta Rencana Operasi) dan Berita Radio SAR.
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */
require_once dirname(__DIR__, 3) . '/models/DokumenPetaSnapshotModel.php';
$renopsDocStatus = RenopsDokumenModel::statusByOperasi((int)$id);
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
<div id="tab-content-cetak" class="tab-pane" style="display: <?= $activeTab === 'cetak' ? 'block' : 'none' ?>;">
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
    <!-- Card 1: Rencana Operasi SAR -->
    <section class="opsd-panel rounded-2xl overflow-hidden flex flex-col">
      <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">1. Rencana Operasi SAR</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Format SMEAC per hari, peta LKP, peta rencana, dan verifikasi QR.
          </p>
        </div>
        <span class="font-mono text-[11px] font-semibold text-slate-400 shrink-0 pt-0.5">A4 / PDF</span>
      </div>

      <div class="p-4 sm:p-5 space-y-3.5">
        <a href="<?= e($captureLkpUrl) ?>" class="flex items-center justify-center gap-1.5 w-full py-2 px-3 rounded-xl border border-emerald-500/35 bg-emerald-500/10 hover:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-semibold text-xs transition no-underline">
          <?php if ($adaPetaLkp): ?>
            <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <span>Peta Lokasi LKP (perbarui)</span>
          <?php else: ?>
            <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            <span>Capture Peta Lokasi LKP (1x per operasi)</span>
          <?php endif; ?>
        </a>

        <div class="space-y-2.5 max-h-96 overflow-y-auto">
          <?php if (!empty($operasi['renops'])): ?>
            <?php foreach ($operasi['renops'] as $r):
              $rid = (int)$r['id_renops'];
              $terbitVersi = $renopsDocStatus[$rid]['terbit_versi'] ?? null;
              $printRenopsUrl = BASE_URL . '/operasi.php?action=print_renops&id=' . (int)$id . '&id_renops=' . $rid;
              $captureRencanaUrl = BASE_URL . '/gis.php?id_operasi=' . (int)$id . '&include_base=1&capture_doc=RENOPS_RENCANA&capture_ref=' . $rid;
              $adaRencana = !empty($adaPetaRencana[$rid]);
            ?>
            <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/30 space-y-2.5">
              <div class="flex items-center justify-between gap-2">
                <span class="opsd-num text-xs font-bold text-slate-800 dark:text-slate-200">
                  <?= e($r['hari_ke']) ?> <span class="font-normal text-slate-500 dark:text-slate-400 ml-1"><?= e(TemplateHelper::formatTanggalSimple($r['tanggal_laporan'])) ?></span>
                </span>
                <?php if ($terbitVersi): ?>
                  <span class="opsd-num px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">TERBIT v<?= (int)$terbitVersi ?></span>
                <?php else: ?>
                  <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-amber-500/10 text-amber-700 dark:text-amber-300">DRAF</span>
                <?php endif; ?>
              </div>
              <div class="grid grid-cols-3 gap-1.5">
                <a href="<?= BASE_URL ?>/operasi.php?action=renops_dokumen&id_renops=<?= $rid ?>" class="opsd-ghost-btn h-8 px-2 rounded-lg font-semibold text-xs inline-flex items-center justify-center transition no-underline">Edit Draf</a>
                <a href="<?= e($printRenopsUrl) ?>" target="_blank" class="opsd-accent-btn h-8 px-2 rounded-lg font-semibold text-xs inline-flex items-center justify-center transition no-underline"><?= $terbitVersi ? 'Cetak' : 'Cetak Draf' ?></a>
                <a href="<?= e($printRenopsUrl . '&download_pdf=1') ?>" target="_blank" class="opsd-accent-soft h-8 px-2 rounded-lg font-semibold text-xs inline-flex items-center justify-center transition no-underline" title="Download PDF RENOPS"><?= $terbitVersi ? 'Unduh PDF' : 'PDF Draf' ?></a>
              </div>
              <a href="<?= e($captureRencanaUrl) ?>" class="flex items-center justify-center gap-1.5 py-1.5 px-2.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 font-medium text-[11px] transition no-underline">
                <?php if ($adaRencana): ?>
                  <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                  <span>Peta Rencana Operasi <?= e($r['hari_ke']) ?> (perbarui)</span>
                <?php else: ?>
                  <span>Capture Peta Rencana Operasi <?= e($r['hari_ke']) ?></span>
                <?php endif; ?>
              </a>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="text-xs text-slate-400 text-center py-4">Belum ada dokumen RENOPS.</p>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- Card 2: Berita Radio SAR -->
    <section class="opsd-panel rounded-2xl overflow-hidden flex flex-col">
      <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex items-start justify-between gap-3">
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">2. Berita Radio SAR</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Naskah telegram dinas Basarnas lengkap dengan verifikasi QR Digital.
          </p>
        </div>
        <span class="font-mono text-[11px] font-semibold text-slate-400 shrink-0 pt-0.5">A4 / QR</span>
      </div>

      <div class="p-4 sm:p-5 space-y-3.5">
        <?php if (!empty($operasi['bra_sar'])): ?>
          <?php if (count($operasi['bra_sar']) === 1): $onlyBra = $operasi['bra_sar'][0]; ?>
            <div class="p-3.5 rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/30 space-y-1 text-xs">
              <div class="opsd-num font-mono font-bold text-slate-900 dark:text-white truncate">NO: <?= e($onlyBra['no_bra'] ?: ('BRA #' . (int)$onlyBra['id_bra'])) ?></div>
              <?php if (!empty($onlyBra['perihal'])): ?>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><?= e($onlyBra['perihal']) ?></div>
              <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/bra.php?view=<?= (int)$onlyBra['id_bra'] ?>" class="opsd-accent-btn w-full h-9 rounded-xl font-semibold text-xs flex items-center justify-center gap-1.5 transition cursor-pointer no-underline">
              <span>Cetak Dokumen BRA</span>
            </a>
          <?php else: ?>
            <div class="space-y-2.5">
              <select id="select-bra-cetak" aria-label="Pilih dokumen BRA" class="control control-sm w-full">
                <?php foreach ($operasi['bra_sar'] as $b): ?>
                  <option value="<?= (int)$b['id_bra'] ?>">BRA #<?= (int)$b['id_bra'] ?> - <?= e(!empty($b['no_bra']) ? $b['no_bra'] : $b['perihal']) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" onclick="const bid = document.getElementById('select-bra-cetak').value; window.location.href='<?= BASE_URL ?>/bra.php?view=' + bid;" class="opsd-accent-btn w-full h-9 rounded-xl font-semibold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                <span>Cetak BRA Terpilih</span>
              </button>
            </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-xs text-slate-400 text-center py-4">Belum ada BRA terbit.</p>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
