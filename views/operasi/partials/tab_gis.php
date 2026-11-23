<?php
/**
 * QR-SAR - Tab Peta Taktis Operasi (GIS)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */
?>
<div id="tab-content-gis" class="tab-pane space-y-4" style="display: <?= $activeTab === 'gis' ? 'block' : 'none' ?>;">
  <div class="opsd-panel rounded-2xl overflow-hidden">
    <!-- Integrated Map Control Toolbar -->
    <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex flex-col xl:flex-row xl:items-center justify-between gap-2.5">
      <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs min-w-0">
        <span class="opsd-num font-mono font-bold text-slate-900 dark:text-white">
          LKP <?= e($operasi['koordinat']) ?>
        </span>
        <?php if (!empty($operasi['jarak'])): ?>
          <span class="text-slate-500 dark:text-slate-400">
            Jarak Kansar: <strong class="opsd-num font-mono opsd-accent-text"><?= e($operasi['jarak']) ?></strong>
          </span>
        <?php endif; ?>
      </div>

      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <div class="ops-gis-seg flex items-center p-0.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
          <button type="button" id="btn-layer-hybrid" onclick="switchDetailMapLayer('hybrid')" class="px-2.5 py-1 rounded-md transition cursor-pointer bg-orange-600 text-white shadow-xs font-bold text-[11px]" title="Peta Hybrid (Satelit + Label)">
            Hybrid
          </button>
          <button type="button" id="btn-layer-satellite" onclick="switchDetailMapLayer('satellite')" class="px-2.5 py-1 rounded-md transition cursor-pointer bg-transparent text-slate-700 dark:text-slate-300 font-semibold text-[11px]" title="Citra Satelit Murni">
            Satelit
          </button>
          <button type="button" id="btn-layer-roadmap" onclick="switchDetailMapLayer('roadmap')" class="px-2.5 py-1 rounded-md transition cursor-pointer bg-transparent text-slate-700 dark:text-slate-300 font-semibold text-[11px]" title="Peta Jalan Vektor">
            Roadmap
          </button>
          <button type="button" id="btn-layer-terrain" onclick="switchDetailMapLayer('terrain')" class="px-2.5 py-1 rounded-md transition cursor-pointer bg-transparent text-slate-700 dark:text-slate-300 font-semibold text-[11px]" title="Topografi dan Relief">
            Terrain
          </button>
        </div>

        <button type="button" onclick="refreshDetailGis(true)" id="btn-refresh-gis" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition cursor-pointer" title="Perbarui data plotting GIS dari server">
          <svg id="icon-refresh-gis" class="w-3.5 h-3.5 text-slate-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
          <span>Segarkan</span>
        </button>

        <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$operasi['id_operasi'] ?>&include_base=1&capture_doc=RENOPS" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline" title="Buka Studio Capture Peta 16:9 untuk Dokumen Cetak Resmi">
          <span>Capture Peta (16:9)</span>
        </a>

        <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$operasi['id_operasi'] ?>&include_base=1" class="opsd-accent-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition no-underline" title="Buka Halaman Plotting GIS Penuh">
          <span>Buka Plotting GIS</span>
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 17 17 7"/><path d="M7 7h10v10"/></svg>
        </a>
      </div>
    </div>

    <!-- Compact Legend & Auto-Fit Bar -->
    <div class="px-4 sm:px-5 py-2 border-b border-slate-100 dark:border-slate-800/80 flex flex-wrap items-center justify-between gap-2 text-[11px]">
      <div class="opsd-num flex flex-wrap items-center gap-x-4 gap-y-1 font-mono text-slate-600 dark:text-slate-300">
        <span class="inline-flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-sm bg-blue-500" aria-hidden="true"></span>
          <span>Search Area (<strong id="stat-cnt-polygons"><?= $cntPolys ?></strong>)</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-sm bg-orange-500" aria-hidden="true"></span>
          <span>Pola SAR (<strong id="stat-cnt-patterns"><?= $cntPatterns ?></strong>)</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-sm bg-emerald-500" aria-hidden="true"></span>
          <span>Jalur (<strong id="stat-cnt-routes"><?= $cntRoutes ?></strong>)</span>
        </span>
        <span class="inline-flex items-center gap-1.5">
          <span class="w-2 h-2 rounded-sm bg-purple-500" aria-hidden="true"></span>
          <span>Titik Taktis (<strong id="stat-cnt-points"><?= $cntPoints ?></strong>)</span>
        </span>
      </div>
      <button type="button" onclick="detailOpsMap && detailOpsMap.fitAllComponents && detailOpsMap.fitAllComponents()" class="text-[11px] font-semibold opsd-accent-text hover:underline cursor-pointer">
        Fit Semua Komponen
      </button>
    </div>

    <!-- Leaflet Map Canvas -->
    <div id="map-detail-ops" class="w-full" style="height: 520px;"></div>
  </div>

  <!-- Saved Tactical Component Inventory -->
  <section class="opsd-panel rounded-2xl overflow-hidden">
    <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
      <div class="flex items-center gap-2">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Inventaris Komponen Taktis</h3>
        <span id="detail-total-badge" class="opsd-num px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-slate-200/70 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
          Total <?= $totalComponents ?>
        </span>
      </div>

      <div class="ops-gis-seg flex flex-wrap items-center gap-1" id="history-filter-pills">
        <button type="button" onclick="filterDetailGisHistory('all')" id="btn-hist-all" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-orange-600 text-white transition cursor-pointer">
          Semua
        </button>
        <button type="button" onclick="filterDetailGisHistory('polygons')" id="btn-hist-polygons" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
          Search Area
        </button>
        <button type="button" onclick="filterDetailGisHistory('patterns')" id="btn-hist-patterns" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
          Pola SAR
        </button>
        <button type="button" onclick="filterDetailGisHistory('routes')" id="btn-hist-routes" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
          Jalur
        </button>
        <button type="button" onclick="filterDetailGisHistory('points')" id="btn-hist-points" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
          Titik Taktis
        </button>
        <button type="button" onclick="filterDetailGisHistory('basemap')" id="btn-hist-basemap" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
          Base Map
        </button>
      </div>
    </div>

    <div id="detail-gis-history-container" class="p-4 sm:p-5 space-y-2.5">
      <!-- Populated via JavaScript -->
    </div>
  </section>
</div>
