<?php
/**
 * QR-SAR - Tab Berita Radio SAR (BRA) - Daftar Ringkas
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */
?>
<div id="tab-content-bra" class="tab-pane space-y-3.5" style="display: <?= $activeTab === 'bra' ? 'block' : 'none' ?>;">
  <?php if (empty($operasi['bra_sar'])): ?>
    <div class="opsd-panel p-10 text-center rounded-2xl space-y-3">
      <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto border border-slate-200 dark:border-slate-700">
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
      </div>
      <div class="max-w-sm mx-auto space-y-1">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Berita Radio SAR (BRA)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          Terbitkan naskah telegram resmi Basarnas untuk dikirimkan ke pimpinan dan Senkom.
        </p>
      </div>
      <div class="pt-1">
        <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=laporan_awal&return_to=operasi_bra&return_op=<?= (int)$id ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-9 px-4 rounded-xl font-semibold text-xs transition no-underline">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
          <span>Buat BRA SAR Baru</span>
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="opsd-panel rounded-2xl overflow-hidden">
      <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex flex-wrap items-center justify-between gap-2.5">
        <div class="flex items-center gap-2">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Berita Radio SAR</h3>
          <span class="opsd-num px-2 py-0.5 rounded-md font-mono text-[11px] font-semibold bg-slate-200/70 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
            <?= count($operasi['bra_sar']) ?> dokumen
          </span>
        </div>
        <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=laporan_awal&return_to=operasi_bra&return_op=<?= (int)$id ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg font-semibold text-xs transition shrink-0 no-underline no-print">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
          <span>Buat BRA SAR Baru</span>
        </a>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[600px] text-left border-collapse text-xs">
          <thead>
            <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/40 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
              <th class="px-4 sm:px-5 py-2.5" style="width: 24%;">No BRA SAR</th>
              <th class="px-4 py-2.5" style="width: 17%;">Kategori</th>
              <th class="px-4 py-2.5">Perihal</th>
              <th class="px-4 sm:px-5 py-2.5 text-right no-print" style="width: 22%;">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
            <?php foreach ($operasi['bra_sar'] as $bra):
              $katBra = strtoupper((string)($bra['kategori'] ?? ''));
              $katLabel = in_array($katBra, ['SAR_UMUM', 'OPERASI_SAR'], true)
                  ? 'BRA OPS SAR'
                  : ($katBra === 'PERGERAKAN_KAPAL' ? 'BRA KAPAL' : 'BRA MAPEL');
              $katBadgeClass = match ($katLabel) {
                  'BRA KAPAL' => 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
                  'BRA MAPEL' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
                  default => 'opsd-accent-text bg-slate-100 dark:bg-slate-800',
              };
            ?>
            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors">
              <td class="opsd-num px-4 sm:px-5 py-3 align-middle whitespace-nowrap font-mono font-bold opsd-accent-text">
                <?= e($bra['no_bra'] ?: '-') ?>
              </td>
              <td class="px-4 py-3 align-middle whitespace-nowrap">
                <span class="inline-flex items-center px-2 py-0.5 rounded-md font-mono font-bold text-[11px] <?= $katBadgeClass ?>">
                  <?= $katLabel ?>
                </span>
              </td>
              <td class="px-4 py-3 align-middle">
                <a href="<?= BASE_URL ?>/bra.php?view=<?= (int)$bra['id_bra'] ?>&return_to=operasi_bra&return_op=<?= (int)$id ?>" class="font-semibold text-slate-900 dark:text-white hover:underline block leading-snug">
                  <?= e($bra['perihal'] ?: 'Naskah Berita Radio SAR') ?>
                </a>
              </td>
              <td class="px-4 sm:px-5 py-3 align-middle text-right whitespace-nowrap no-print">
                <div class="inline-flex items-center gap-1.5">
                  <a href="<?= BASE_URL ?>/bra.php?view=<?= (int)$bra['id_bra'] ?>&return_to=operasi_bra&return_op=<?= (int)$id ?>" class="opsd-accent-soft inline-flex items-center h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline">
                    <span>Lihat Dokumen</span>
                  </a>
                  <a href="<?= BASE_URL ?>/bra.php?action=edit&id=<?= (int)$bra['id_bra'] ?>&return_to=operasi_bra&return_op=<?= (int)$id ?>" class="opsd-ghost-btn inline-flex items-center h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline">
                    <span>Edit</span>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
