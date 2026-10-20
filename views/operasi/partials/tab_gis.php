<?php
/**
 * QR-SAR - Tab Peta Taktis Operasi (GIS)
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
?>
<div id="tab-content-gis" class="tab-pane space-y-4" style="display: <?= $activeTab === 'gis' ? 'block' : 'none' ?>;">
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <!-- Card Header with Map Layer Switchers -->
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex flex-col lg:flex-row lg:items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-950/50">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                    <span>Peta Taktis Operasi SAR (Tactical GIS)</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20 font-mono">
                        OPS #<?= (int)$operasi['id_operasi'] ?> + BASE MAP
                    </span>
                </h3>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                    LKP: <span class="font-bold text-slate-700 dark:text-slate-300"><?= e($operasi['koordinat']) ?></span> &bull; Jarak Kansar: <strong class="text-orange-600"><?= e($operasi['jarak']) ?></strong>
                </div>
            </div>

            <!-- Map Type Switcher Controls & Action Link -->
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center p-1 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs font-semibold">
                    <button type="button" id="btn-layer-hybrid" onclick="switchDetailMapLayer('hybrid')" class="px-2.5 py-1 rounded-lg transition cursor-pointer bg-orange-600 text-white shadow-xs font-bold text-[11px] flex items-center gap-1" title="Tampilan Peta Hybrid (Satelit Resolusi Tinggi + Label Jalan & Wilayah)">
                        <span>🛰️</span>
                        <span>Hybrid</span>
                    </button>
                    <button type="button" id="btn-layer-satellite" onclick="switchDetailMapLayer('satellite')" class="px-2.5 py-1 rounded-lg transition cursor-pointer bg-transparent text-slate-700 dark:text-slate-300 hover:text-orange-500 font-bold text-[11px] flex items-center gap-1" title="Tampilan Citra Satelit Murni Resolusi Tinggi">
                        <span>🌍</span>
                        <span>Satelit</span>
                    </button>
                    <button type="button" id="btn-layer-roadmap" onclick="switchDetailMapLayer('roadmap')" class="px-2.5 py-1 rounded-lg transition cursor-pointer bg-transparent text-slate-700 dark:text-slate-300 hover:text-orange-500 font-bold text-[11px] flex items-center gap-1" title="Tampilan Peta Jalan Vektor (Roadmap)">
                        <span>🗺️</span>
                        <span>Roadmap</span>
                    </button>
                    <button type="button" id="btn-layer-terrain" onclick="switchDetailMapLayer('terrain')" class="px-2.5 py-1 rounded-lg transition cursor-pointer bg-transparent text-slate-700 dark:text-slate-300 hover:text-orange-500 font-bold text-[11px] flex items-center gap-1" title="Tampilan Topografi, Elevasi & Kontur Relief (Terrain)">
                        <span>🏔️</span>
                        <span>Terrain</span>
                    </button>
                </div>

                <button type="button" onclick="refreshDetailGis(true)" id="btn-refresh-gis" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition shadow-xs cursor-pointer" title="Perbarui data plotting GIS dari server">
                    <svg id="icon-refresh-gis" class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    <span>Segarkan</span>
                </button>


                <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$operasi['id_operasi'] ?>&include_base=1&capture_doc=LAPORAN_AWAL" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-emerald-500/30 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:hover:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-bold text-xs transition shadow-xs no-underline" title="Buka Studio Capture Peta 16:9 untuk Dokumen Cetak Resmi">
                    <span>📸 Capture Peta Dokumen (16:9)</span>
                </a>

                <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$operasi['id_operasi'] ?>&include_base=1" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-blue-500/30 bg-blue-50 hover:bg-blue-100 dark:bg-blue-950/40 dark:hover:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold text-xs transition shadow-xs" title="Buka Halaman Plotting GIS Penuh dengan fitur Menggambar Search Area & Pola SAR">
                    <span>✏️ Buka Plotting GIS</span>
                    <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                </a>
            </div>
        </div>

        <!-- Tactical Stats & Legend Bar -->
        <div class="px-4 py-2.5 bg-slate-100/70 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-slate-400 font-semibold text-[11px]">Komponen Ditampilkan:</span>
                <span class="inline-flex items-center gap-1 font-mono font-bold text-blue-600 dark:text-blue-400">
                    <span class="w-2.5 h-2.5 rounded-sm bg-blue-500"></span>
                    <span>Search Area (<span id="stat-cnt-polygons"><?= $cntPolys ?></span>)</span>
                </span>
                <span class="inline-flex items-center gap-1 font-mono font-bold text-orange-600 dark:text-orange-400">
                    <span class="w-2.5 h-2.5 rounded-sm bg-orange-500"></span>
                    <span>Pola SAR (<span id="stat-cnt-patterns"><?= $cntPatterns ?></span>)</span>
                </span>
                <span class="inline-flex items-center gap-1 font-mono font-bold text-emerald-600 dark:text-emerald-400">
                    <span class="w-2.5 h-2.5 rounded-sm bg-emerald-500"></span>
                    <span>Jalur (<span id="stat-cnt-routes"><?= $cntRoutes ?></span>)</span>
                </span>
                <span class="inline-flex items-center gap-1 font-mono font-bold text-purple-600 dark:text-purple-400">
                    <span class="w-2.5 h-2.5 rounded-sm bg-purple-500"></span>
                    <span>Titik Taktis (<span id="stat-cnt-points"><?= $cntPoints ?></span>)</span>
                </span>
            </div>
            <button type="button" onclick="detailOpsMap && detailOpsMap.fitAllComponents && detailOpsMap.fitAllComponents()" class="text-[11px] font-semibold text-slate-500 hover:text-orange-500 dark:text-slate-400 cursor-pointer flex items-center gap-1">
                <span>🎯 Reset Tampilan Seluruh Komponen</span>
            </button>
        </div>

        <!-- Leaflet Map Canvas -->
        <div id="map-detail-ops" class="w-full" style="height: 520px;"></div>
    </div>

    <!-- History Komponen Taktis Tersimpan (Operasi & Base Map) -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs p-5 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h4 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📜 Riwayat & Inventaris Komponen Taktis Tersimpan</span>
                    <span id="detail-total-badge" class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        Total <?= $totalComponents ?>
                    </span>
                </h4>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Komponen Search Area, Pola Pencarian SAR, Jalur, dan Titik yang tersimpan pada ID Operasi ini serta data acuan Base Map.
                </p>
            </div>

            <!-- Filter Category Pills -->
            <div class="flex flex-wrap items-center gap-1.5" id="history-filter-pills">
                <button type="button" onclick="filterDetailGisHistory('all')" id="btn-hist-all" class="px-2.5 py-1 rounded-lg text-xs font-bold bg-orange-600 text-white transition cursor-pointer">
                    Semua
                </button>
                <button type="button" onclick="filterDetailGisHistory('polygons')" id="btn-hist-polygons" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
                    Search Area
                </button>
                <button type="button" onclick="filterDetailGisHistory('patterns')" id="btn-hist-patterns" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
                    Pola SAR
                </button>
                <button type="button" onclick="filterDetailGisHistory('routes')" id="btn-hist-routes" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
                    Jalur
                </button>
                <button type="button" onclick="filterDetailGisHistory('points')" id="btn-hist-points" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
                    Titik Taktis
                </button>
                <button type="button" onclick="filterDetailGisHistory('basemap')" id="btn-hist-basemap" class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
                    Base Map
                </button>
            </div>
        </div>

        <!-- Component Cards List -->
        <div id="detail-gis-history-container" class="space-y-2.5">
            <!-- Populated via JavaScript -->
        </div>
    </div>
</div>
