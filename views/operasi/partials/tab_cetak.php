<?php
/**
 * QR-SAR - Tab Dokumen RENOPS Resmi Operasi SAR (Format SMEAC per Hari, Peta LKP, Peta Rencana, & Verifikasi QR)
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
$daftarRenops = is_array($operasi['renops'] ?? null) ? $operasi['renops'] : [];
$renopsTotal = count($daftarRenops);
$gridClass = $renopsTotal <= 1
    ? 'grid grid-cols-1 gap-4'
    : ($renopsTotal === 2
        ? 'grid grid-cols-1 md:grid-cols-2 gap-4 grid-flow-dense'
        : 'grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 grid-flow-dense');
?>
<div id="tab-content-cetak" class="tab-pane" style="display: <?= $activeTab === 'cetak' ? 'block' : 'none' ?>;">
  <section class="opsd-panel rounded-2xl overflow-hidden">
    <!-- Unified Header & Peta LKP Action Bar -->
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex flex-wrap items-center justify-between gap-3">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Dokumen Rencana Operasi SAR (RENOPS)</h3>
          <span class="opsd-num px-2 py-0.5 rounded-md font-mono text-[11px] font-semibold bg-slate-200/70 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
            A4 / PDF &bull; <?= $renopsTotal ?> dokumen
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Format SMEAC resmi per hari operasi, lampiran peta lokasi LKP, peta rencana operasi, dan verifikasi QR digital.
        </p>
      </div>

      <a href="<?= e($captureLkpUrl) ?>" class="inline-flex items-center justify-center gap-1.5 h-8 px-3.5 rounded-xl border border-emerald-500/35 bg-emerald-500/10 hover:bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-semibold text-xs transition shrink-0 no-underline">
        <?php if ($adaPetaLkp): ?>
          <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <span>Peta Lokasi LKP Tersimpan (Perbarui)</span>
        <?php else: ?>
          <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
          <span>Capture Peta Lokasi LKP (1x per operasi)</span>
        <?php endif; ?>
      </a>
    </div>

    <!-- Adaptive Gapless Document Grid -->
    <div class="p-4 sm:p-5">
      <?php if ($renopsTotal > 0): ?>
        <div class="<?= $gridClass ?>">
          <?php foreach ($daftarRenops as $r):
            $rid = (int)$r['id_renops'];
            $terbitVersi = $renopsDocStatus[$rid]['terbit_versi'] ?? null;
            $printRenopsUrl = BASE_URL . '/operasi.php?action=print_renops&id=' . (int)$id . '&id_renops=' . $rid;
            $captureRencanaUrl = BASE_URL . '/gis.php?id_operasi=' . (int)$id . '&include_base=1&capture_doc=RENOPS_RENCANA&capture_ref=' . $rid;
            $adaRencana = !empty($adaPetaRencana[$rid]);
          ?>
          <article class="opsd-row-card rounded-xl border border-slate-200/90 dark:border-slate-800 bg-slate-50/55 dark:bg-slate-950/35 p-4 flex flex-col justify-between gap-3.5 <?= $renopsTotal === 1 ? 'md:flex-row md:items-center md:gap-6' : '' ?>">
            <div class="space-y-2.5 min-w-0 <?= $renopsTotal === 1 ? 'md:flex-1' : '' ?>">
              <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2 min-w-0">
                  <span class="opsd-num px-2 py-0.5 rounded-md font-mono text-xs font-bold opsd-accent-soft shrink-0">
                    <?= e($r['hari_ke']) ?>
                  </span>
                  <span class="opsd-num text-xs font-semibold text-slate-700 dark:text-slate-200 truncate">
                    <?= e(TemplateHelper::formatTanggalSimple($r['tanggal_laporan'])) ?>
                  </span>
                </div>
                <?php if ($terbitVersi): ?>
                  <span class="opsd-num px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 shrink-0">TERBIT v<?= (int)$terbitVersi ?></span>
                <?php else: ?>
                  <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-300 shrink-0">DRAF</span>
                <?php endif; ?>
              </div>

              <a href="<?= e($captureRencanaUrl) ?>" class="flex items-center justify-between gap-2 py-2 px-3 rounded-lg border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-orange-500/40 text-slate-700 dark:text-slate-300 font-medium text-[11px] transition no-underline">
                <span class="inline-flex items-center gap-1.5 min-w-0 truncate">
                  <?php if ($adaRencana): ?>
                    <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                    <span class="truncate">Peta Rencana Operasi <?= e($r['hari_ke']) ?> (perbarui)</span>
                  <?php else: ?>
                    <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                    <span class="truncate">Capture Peta Rencana Operasi <?= e($r['hari_ke']) ?></span>
                  <?php endif; ?>
                </span>
                <span class="text-[10px] font-mono font-semibold text-slate-400 shrink-0">GIS &rarr;</span>
              </a>
            </div>

            <div class="grid grid-cols-3 gap-1.5 <?= $renopsTotal === 1 ? 'md:w-80 md:shrink-0' : 'pt-2.5 border-t border-slate-200/70 dark:border-slate-800/80' ?>">
              <a href="<?= BASE_URL ?>/operasi.php?action=renops_dokumen&id_renops=<?= $rid ?>" class="opsd-ghost-btn h-8 px-2.5 rounded-lg font-semibold text-xs inline-flex items-center justify-center transition no-underline">Edit Draf</a>
              <a href="<?= e($printRenopsUrl) ?>" target="_blank" class="opsd-accent-btn h-8 px-2.5 rounded-lg font-semibold text-xs inline-flex items-center justify-center transition no-underline"><?= $terbitVersi ? 'Cetak' : 'Cetak Draf' ?></a>
              <a href="<?= e($printRenopsUrl . '&download_pdf=1') ?>" target="_blank" class="opsd-accent-soft h-8 px-2.5 rounded-lg font-semibold text-xs inline-flex items-center justify-center transition no-underline" title="Download PDF RENOPS"><?= $terbitVersi ? 'Unduh PDF' : 'PDF Draf' ?></a>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="py-10 text-center space-y-3">
          <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto border border-slate-200 dark:border-slate-700">
            <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </div>
          <div class="max-w-sm mx-auto space-y-1">
            <h4 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Dokumen RENOPS</h4>
            <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
              Terbitkan Rencana Operasi SAR (RENOPS) harian terlebih dahulu agar draf dokumen SMEAC, cetak, dan PDF tersedia di sini.
            </p>
          </div>
          <?php if (!empty($isOpen)): ?>
            <div class="pt-1">
              <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= (int)$id ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-9 px-4 rounded-xl font-semibold text-xs transition no-underline">
                <span>Buat RENOPS Pertama</span>
              </a>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </section>
</div>
