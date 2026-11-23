<?php
/**
 * QR-SAR - Tab Hirarki Dokumen Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */
?>
<div id="tab-content-hierarchy" class="tab-pane space-y-3" style="display: <?= $activeTab === 'hierarchy' ? 'block' : 'none' ?>;">
  <div class="relative pl-7 space-y-3 before:absolute before:left-[11px] before:top-4 before:bottom-4 before:w-px before:bg-slate-200 dark:before:bg-slate-800">
    <?php foreach (($timeline ?? []) as $idx => $doc):
      $docType = (string)($doc['type'] ?? '');
      $isAwal = $docType === 'LAPORAN_AWAL';
      $isRenops = $docType === 'RENOPS';
      $isPenutupan = $docType === 'PENUTUPAN';
      $isLapbang = $docType === 'LAPBANG';

      $typeLabel = $isAwal ? 'LAPORAN AWAL' : ($isRenops ? 'RENOPS' : ($isPenutupan ? 'PENUTUPAN' : 'LAPBANG'));
      $nodeClass = $isRenops
          ? 'opsd-accent-badge'
          : ($isPenutupan
              ? 'bg-rose-600 text-white'
              : ($isLapbang ? 'bg-emerald-600 text-white' : 'bg-slate-800 dark:bg-slate-200 text-white dark:text-slate-900'));
      $badgeClass = $isRenops
          ? 'opsd-accent-text bg-slate-100 dark:bg-slate-800'
          : ($isPenutupan
              ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400'
              : ($isLapbang
                  ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                  : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'));
    ?>
    <div class="relative">
      <div class="opsd-num absolute -left-7 top-4 w-6 h-6 rounded-full <?= $nodeClass ?> flex items-center justify-center font-mono font-bold text-[11px] ring-4 ring-[var(--bg-body)] z-10">
        <?= $idx + 1 ?>
      </div>

      <article class="opsd-panel opsd-row-card rounded-xl px-4 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="space-y-1 min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <span class="px-2 py-0.5 rounded-md font-mono text-[11px] font-bold <?= $badgeClass ?>">
              <?= e($typeLabel) ?>
            </span>
            <span class="opsd-num font-mono text-[11px] font-semibold text-slate-500 dark:text-slate-400">
              <?= e($doc['hari_ke']) ?>
            </span>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
              <?= e($doc['label']) ?>
            </h3>
          </div>
          <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-1">
            <span class="opsd-num font-mono text-[11px]">
              <?= TemplateHelper::formatTanggalIndo($doc['waktu']) ?>, <?= TemplateHelper::formatJamWITA($doc['waktu']) ?> WITA
            </span>
            <?php if (!empty($doc['cuaca'])): ?>
              <span class="text-slate-600 dark:text-slate-400">Cuaca: <?= e($doc['cuaca']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <div class="shrink-0 flex items-center gap-1.5 flex-wrap no-print">
          <?php if ($isAwal): ?>
            <button type="button" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-awal')">
              <span>Naskah WA</span>
            </button>
          <?php elseif ($isRenops): ?>
            <button type="button" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-renops-<?= (int)($doc['doc_id'] ?? 0) ?>')" title="Lihat pratinjau format WhatsApp RENOPS">
              <span>Naskah WA</span>
            </button>
            <button type="button" class="opsd-accent-soft inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition cursor-pointer" onclick="navigateToDoc('renops', <?= (int)($doc['doc_id'] ?? 0) ?>)">
              <span>Buka RENOPS</span>
            </button>
          <?php elseif ($isLapbang || $isPenutupan): ?>
            <button type="button" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-sitrep-<?= (int)($doc['doc_id'] ?? 0) ?>')" title="Lihat pratinjau format WhatsApp LAPBANG">
              <span>Naskah WA</span>
            </button>
            <button type="button" class="opsd-accent-soft inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition cursor-pointer" onclick="navigateToDoc('sitrep', <?= (int)($doc['doc_id'] ?? 0) ?>)">
              <span>Buka LAPBANG</span>
            </button>
          <?php endif; ?>
        </div>
      </article>
    </div>
    <?php endforeach; ?>
  </div>
</div>
