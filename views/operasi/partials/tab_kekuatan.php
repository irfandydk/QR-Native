<?php
/**
 * QR-SAR - Tab Kekuatan Unsur & Alut Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

$totalPersKekuatan = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $operasi['detail_unsur'] ?? []));
$totalAlutKekuatan = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $operasi['detail_alut'] ?? []));
?>
<div id="tab-content-kekuatan" class="tab-pane" style="display: <?= $activeTab === 'kekuatan' ? 'block' : 'none' ?>;">
  <!-- Unified 2-Column Architectural Strength Matrix -->
  <div class="opsd-panel rounded-2xl overflow-hidden grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-200 dark:divide-slate-800">
    <!-- Left: Unsur SAR Terlibat -->
    <section class="flex flex-col">
      <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex items-center justify-between gap-3">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Unsur SAR Terlibat</h3>
        <span class="opsd-num font-mono text-xs font-bold opsd-accent-text">
          <?= $totalPersKekuatan ?> Personel
        </span>
      </div>

      <div class="px-4 sm:px-5 py-2 flex-1">
        <?php if (!empty($operasi['detail_unsur'])): ?>
          <ul class="divide-y divide-slate-100 dark:divide-slate-800/80 list-none p-0 m-0 text-xs">
            <?php foreach ($operasi['detail_unsur'] as $u): ?>
              <li class="py-2.5 flex items-center justify-between gap-3">
                <span class="font-medium text-slate-800 dark:text-slate-200"><?= e($u['nama_unsur']) ?></span>
                <span class="opsd-num font-mono font-semibold text-slate-600 dark:text-slate-300 shrink-0">
                  <?= (int)($u['jumlah_personil'] ?? 1) ?> Org
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed py-3"><?= e(trim((string)($operasi['unsur_terlibat'] ?? '')) ?: 'Tim Rescue KPP Kendari') ?></div>
        <?php endif; ?>
      </div>
    </section>

    <!-- Right: Alut & Peralatan SAR -->
    <section class="flex flex-col">
      <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex items-center justify-between gap-3">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Alut &amp; Peralatan SAR</h3>
        <span class="opsd-num font-mono text-xs font-bold opsd-accent-text">
          <?= $totalAlutKekuatan ?> Unit
        </span>
      </div>

      <div class="px-4 sm:px-5 py-2 flex-1">
        <?php if (!empty($operasi['detail_alut'])): ?>
          <ul class="divide-y divide-slate-100 dark:divide-slate-800/80 list-none p-0 m-0 text-xs">
            <?php foreach (AlutKategori::sort($operasi['detail_alut']) as $a): $katA = AlutKategori::resolve($a); ?>
              <li class="py-2.5 flex items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2 min-w-0">
                  <span class="font-medium text-slate-800 dark:text-slate-200"><?= e($a['nama_alut']) ?></span>
                  <span class="px-1.5 py-0.5 rounded-md text-[10px] font-semibold <?= AlutKategori::isAlut($katA) ? 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400' ?>"><?= e(AlutKategori::label($katA)) ?></span>
                </div>
                <span class="opsd-num font-mono font-semibold text-slate-600 dark:text-slate-300 shrink-0">
                  <?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed py-3"><?= e(trim((string)($operasi['alut_palsar'] ?? '')) ?: '-') ?></div>
        <?php endif; ?>
      </div>
    </section>
  </div>
</div>
