<?php
/**
 * QR-SAR - Tactical GIS & Maritime Plotting Hub
 * KPP Kendari - Basarnas
 * 100% Faithful Implementation matching MapPlottingPage.tsx & Basarnas Operational Guidelines
 * Powered by Leaflet.js with Multi-layer Satellite, Hybrid, Roadmap & Terrain
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$selectedOperasiId = null;
$modeParam = $_GET['mode'] ?? null;

// 4. Operasi yang telah ditutup tidak masuk dalam pilihan! (Hanya status OPEN)
$rawActiveOps = OperasiModel::getListSimple('OPEN');
$activeOperasiList = [];
$activeOperasiMap = [];
$activeOperasiIds = [];
foreach ($rawActiveOps as $ops) {
    if (strtoupper($ops['status'] ?? '') === 'OPEN' && empty($ops['waktu_selesai'])) {
        $opId = (int)$ops['id_operasi'];
        $activeOperasiList[] = $ops;
        $activeOperasiMap[$opId] = $ops;
        $activeOperasiIds[] = $opId;
    }
}

$captureDocParam = isset($_GET['capture_doc']) ? strtoupper(trim((string)$_GET['capture_doc'])) : '';
$rawCapRef = $_GET['capture_ref'] ?? $_GET['id_ref'] ?? '';
$captureRefParam = $rawCapRef !== '' ? (int)$rawCapRef : null;
if ($captureDocParam !== '' && !empty($_GET['id_operasi'])) {
    $reqCaptureOpId = (int)$_GET['id_operasi'];
    if ($reqCaptureOpId > 0 && !isset($activeOperasiMap[$reqCaptureOpId])) {
        $closedOp = OperasiModel::getById($reqCaptureOpId);
        if ($closedOp) {
            $activeOperasiList[] = $closedOp;
            $activeOperasiMap[$reqCaptureOpId] = $closedOp;
            $activeOperasiIds[] = $reqCaptureOpId;
        }
    }
}

if (isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '') {
    $reqId = (int)$_GET['id_operasi'];
    if (isset($activeOperasiMap[$reqId])) {
        $selectedOperasiId = $reqId;
        $filterMode = 'operasi';
    } else {
        $selectedOperasiId = null;
        $filterMode = 'basemap';
    }
} elseif (isset($_GET['id']) && $_GET['id'] !== '') {
    $reqId = (int)$_GET['id'];
    if (isset($activeOperasiMap[$reqId])) {
        $selectedOperasiId = $reqId;
        $filterMode = 'operasi';
    } else {
        $selectedOperasiId = null;
        $filterMode = 'basemap';
    }
} elseif ($modeParam === 'basemap' || $modeParam === 'dasar') {
    $filterMode = 'basemap';
} elseif ($modeParam === 'all') {
    $filterMode = 'all';
} else {
    // Default tampilan saat membuka GIS SAR adalah Base Map
    $filterMode = 'basemap';
}

// 5. Pilihan Misi Operasi SAR: Jika memilih operasi tertentu, default includeBase = true (menampilkan komponen base map)
$includeBase = isset($_GET['include_base'])
    ? ($_GET['include_base'] === '1' || $_GET['include_base'] === 'true')
    : ($filterMode === 'operasi');

$currentOperasi = $selectedOperasiId ? ($activeOperasiMap[$selectedOperasiId] ?? null) : null;

$savedPoints = GisModel::getPoints();
$savedPolygons = GisModel::getPolygons();
$savedPatterns = GisModel::getSearchPatterns();
$savedRoutes = GisModel::getRoutes();
$sarUnits = MasterModel::getSarUnits();
$alutList = MasterModel::getAlut();

// Calculate initial counts matching active filter mode
// 1. Base Map: Only items without id_operasi
// 2. Semua Komponen: Base map items + items belonging to active (OPEN) operations
// 3. Operasi Aktif: Only items belonging to that selected active operation (+ base map if includeBase is true)
if ($filterMode === 'basemap' || $filterMode === 'dasar') {
    $initialPointsCount = count(array_filter($savedPoints, fn($p) => empty($p['id_operasi'])));
    $initialPolygonsCount = count(array_filter($savedPolygons, fn($p) => empty($p['id_operasi'])));
    $initialPatternsCount = count(array_filter($savedPatterns, fn($p) => empty($p['id_operasi'])));
    $initialRoutesCount = count(array_filter($savedRoutes, fn($p) => empty($p['id_operasi'])));
} elseif ($filterMode === 'operasi' && $selectedOperasiId) {
    $initialPointsCount = count(array_filter($savedPoints, fn($p) => ((int)($p['id_operasi'] ?? 0)) === $selectedOperasiId || ($includeBase && empty($p['id_operasi']))));
    $initialPolygonsCount = count(array_filter($savedPolygons, fn($p) => ((int)($p['id_operasi'] ?? 0)) === $selectedOperasiId || ($includeBase && empty($p['id_operasi']))));
    $initialPatternsCount = count(array_filter($savedPatterns, fn($p) => ((int)($p['id_operasi'] ?? 0)) === $selectedOperasiId || ($includeBase && empty($p['id_operasi']))));
    $initialRoutesCount = count(array_filter($savedRoutes, fn($p) => ((int)($p['id_operasi'] ?? 0)) === $selectedOperasiId || ($includeBase && empty($p['id_operasi']))));
} else {
    $initialPointsCount = count(array_filter($savedPoints, fn($p) => empty($p['id_operasi']) || in_array((int)$p['id_operasi'], $activeOperasiIds, true)));
    $initialPolygonsCount = count(array_filter($savedPolygons, fn($p) => empty($p['id_operasi']) || in_array((int)$p['id_operasi'], $activeOperasiIds, true)));
    $initialPatternsCount = count(array_filter($savedPatterns, fn($p) => empty($p['id_operasi']) || in_array((int)$p['id_operasi'], $activeOperasiIds, true)));
    $initialRoutesCount = count(array_filter($savedRoutes, fn($p) => empty($p['id_operasi']) || in_array((int)$p['id_operasi'], $activeOperasiIds, true)));
}
?>

<!-- JSZip for KMZ export (Local Offline Vendor) -->
<script src="<?= BASE_URL ?>/assets/vendor/jszip/jszip.min.js?v=<?= APP_VERSION ?>"></script>

<style>
  /* Tactical GIS Custom Styling & Animations */
  .sar-radar-pulse {
    animation: sar-radar 2s cubic-bezier(0, 0, 0.2, 1) infinite;
  }
  @keyframes sar-radar {
    0% { transform: scale(0.9); opacity: 0.9; }
    70%, 100% { transform: scale(2.4); opacity: 0; }
  }

  .leaflet-popup-content-wrapper {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
    padding: 0;
  }
  .dark .leaflet-popup-content-wrapper,
  [data-theme="dark"] .leaflet-popup-content-wrapper {
    background: #0f172a;
    color: #f8fafc;
    border: 1px solid #334155;
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.5);
  }
  .leaflet-popup-content {
    margin: 12px 14px;
    line-height: 1.4;
  }
  .leaflet-popup-tip {
    background: #ffffff;
  }
  .dark .leaflet-popup-tip,
  [data-theme="dark"] .leaflet-popup-tip {
    background: #0f172a;
  }

  .distance-label-tooltip {
    background: rgba(255, 255, 255, 0.95);
    border: 1px solid #ea580c;
    color: #0f172a;
    font-weight: 800;
    font-size: 10px;
    padding: 2px 7px;
    border-radius: 6px;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  }
  .dark .distance-label-tooltip,
  [data-theme="dark"] .distance-label-tooltip {
    background: rgba(15, 23, 42, 0.92);
    color: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.4);
  }

  /* Custom Scrollbar for Tools Sidebar */
  .gis-scrollbar::-webkit-scrollbar {
    width: 5px;
  }
  .gis-scrollbar::-webkit-scrollbar-track {
    background: transparent;
  }
  .gis-scrollbar::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
  }
  .dark .gis-scrollbar::-webkit-scrollbar-thumb {
    background: #334155;
  }

  /* Tactical Maritime Offline Grid Background */
  #gisLeafletMap {
    background-color: #0b1329 !important;
    background-image: 
      linear-gradient(rgba(255, 255, 255, 0.04) 1px, transparent 1px),
      linear-gradient(90deg, rgba(255, 255, 255, 0.04) 1px, transparent 1px) !important;
    background-size: 40px 40px !important;
  }

  /* Draggable Waypoints for SAR Patterns */
  .sar-pat-draggable-marker,
  .sar-pat-corner {
    cursor: grab !important;
    transition: transform 0.12s ease;
  }
  .sar-pat-draggable-marker:hover,
  .sar-pat-corner:hover {
    transform: scale(1.18);
  }
  .sar-pat-draggable-marker:active,
  .sar-pat-corner:active,
  .leaflet-marker-dragging {
    cursor: grabbing !important;
    transform: scale(1.25);
  }
</style>

<div id="gisRootContainer" class="w-full h-full flex flex-col overflow-hidden bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-100 select-none" style="height: 100%; flex: 1;">
  
  <!-- ========================================== -->
  <!-- 1. TOP SUB-NAVBAR (HEADER KONTROL GIS)     -->
  <!-- ========================================== -->
  <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-3.5 py-2 flex flex-wrap items-center justify-between gap-2.5 shrink-0 z-20 shadow-xs">
    
    <!-- Left Section: Sidebar Toggle & Operational Header -->
    <div class="flex items-center gap-3">
      <button type="button" id="btnToggleGisSidebar" class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 text-xs font-bold transition border border-slate-200 dark:border-slate-700 shadow-xs cursor-pointer" title="Buka / Tutup Tools Plotting">
        <svg id="iconSidebarClose" class="w-4 h-4 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
          <path d="M9 3v18M14 9l-3 3 3 3"/>
        </svg>
        <svg id="iconSidebarOpen" class="w-4 h-4 text-orange-500 dark:text-orange-400 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
          <path d="M9 3v18M11 9l3 3-3 3"/>
        </svg>
        <span id="labelSidebarToggle" class="hidden sm:inline">Sembunyikan Tools</span>
      </button>

      <div class="flex items-center gap-2.5 border-l border-slate-200 dark:border-slate-700 pl-3">
        <img src="<?= e(getAppLogoUrl()) ?>" alt="Logo Basarnas" class="w-8 h-8 object-contain drop-shadow-sm shrink-0" onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xs sm:text-sm font-black tracking-wide text-slate-900 dark:text-white uppercase flex items-center gap-1.5">
              <span>GIS RADAR OPERASI SAR KENDARI</span>
            </h1>
            <span class="text-[9px] font-mono px-1.5 py-0.5 rounded bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold border border-orange-500/30">
              SULTRA MARITIME OPS
            </span>
          </div>
        </div>
      </div>

      <!-- SAR Mission Operasi Selector Dropdown (3 Pilihan Plotting Teroptimasi) -->
      <div class="hidden md:flex items-center gap-1.5 pl-3 border-l border-slate-200 dark:border-slate-700">
        <label for="selectGisOperasi" class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1 shrink-0">
          <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          <span class="hidden lg:inline">Misi Ops:</span>
        </label>
        <select 
          id="selectGisOperasi" 
          class="text-xs py-1 px-2 rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 font-semibold focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition max-w-[230px] truncate"
          title="Pilih mode tampilan komponen plotting taktis"
        >
          <option value="basemap" <?= ($filterMode === 'basemap' || $filterMode === 'dasar') ? 'selected' : '' ?>>
            🗺️ 1. Base Map
          </option>
          <option value="all" <?= $filterMode === 'all' ? 'selected' : '' ?>>
            🌐 2. Semua Komponen
          </option>
          <optgroup label="🚨 3. Operasi Aktif (Komponen Khusus ID Operasi)">
            <?php if (empty($activeOperasiList)): ?>
              <option value="" disabled>(Tidak ada operasi SAR aktif)</option>
            <?php else: ?>
              <?php foreach ($activeOperasiList as $ops): ?>
                <option value="<?= (int)$ops['id_operasi'] ?>" <?= ($filterMode === 'operasi' && $selectedOperasiId === (int)$ops['id_operasi']) ? 'selected' : '' ?>>
                  Ops #<?= $ops['id_operasi'] ?> - <?= e(mb_strimwidth($ops['nama_operasi'], 0, 26, '...')) ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </optgroup>
        </select>
        
        <!-- Toggle: Tampilkan Base Map juga pada Operasi Aktif -->
        <label id="wrapperIncludeBase" class="items-center gap-1 text-[11px] font-medium text-slate-600 dark:text-slate-400 select-none cursor-pointer <?= $filterMode === 'operasi' ? 'flex' : 'hidden' ?>" title="Muat juga komponen Base Map pada peta operasi SAR aktif ini">
          <input type="checkbox" id="checkIncludeBase" class="rounded border-slate-300 dark:border-slate-600 text-orange-500 focus:ring-orange-500 cursor-pointer" <?= $includeBase ? 'checked' : '' ?>>
          <span class="text-[10px] font-bold text-orange-600 dark:text-orange-400">+ Base Map</span>
        </label>

        <button type="button" id="btnResetGisOperasi" class="p-1 text-slate-400 hover:text-red-500 transition <?= ($filterMode === 'operasi' || $filterMode === 'basemap' || $filterMode === 'dasar') ? '' : 'hidden' ?>" title="Reset ke Semua Komponen">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Center Section: LocationSearchBar (Verified Sultra Presets & Coordinates Parser) -->
    <div class="flex-1 max-w-md mx-2 relative" id="locationSearchBarWrapper">
      <div class="relative flex items-center">
        <span class="absolute left-3 text-slate-400 dark:text-slate-500 pointer-events-none">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </span>
        <input 
          type="text" 
          id="locationSearchInput" 
          placeholder="Cari kota, desa, jalan, pelabuhan, pulau, atau koordinat DMS/DD..."
          autocomplete="off"
          class="w-full pl-9 pr-8 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-700 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition font-medium"
        >
        <button type="button" id="btnClearLocationSearch" class="absolute right-2.5 text-slate-400 hover:text-slate-700 dark:hover:text-white hidden p-0.5" title="Bersihkan">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Autocomplete Dropdown Popover -->
      <div id="locationSearchResultsPopover" class="absolute left-0 right-0 top-full mt-1.5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-2xl overflow-hidden z-50 hidden max-h-80 overflow-y-auto gis-scrollbar text-xs">
        <div class="p-2 border-b border-slate-100 dark:border-slate-800 text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex justify-between items-center bg-slate-50 dark:bg-slate-950/50">
          <span>Pencarian Lokasi (Google Maps Geocoding & GPS)</span>
          <span class="text-orange-500 dark:text-orange-400 font-mono">115 SAR GPS</span>
        </div>
        <div id="locationSearchResultsList" class="divide-y divide-slate-100 dark:divide-slate-800">
          <!-- Populated by JS -->
        </div>
      </div>
    </div>

    <!-- Right Section: Master Unit Switcher, Basemap Pills, Ekspor & Fullscreen -->
    <div class="flex items-center gap-2">
      <!-- Master Satuan Switcher (Dinamis: NM | KM | M) -->
      <div id="gisGlobalUnitSwitcher" class="bg-slate-100 dark:bg-slate-950/90 p-0.5 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center text-[11px] font-bold" title="Satuan Pengukuran Dinamis Peta Taktis">
        <span class="text-[9px] text-slate-400 font-mono px-1.5 uppercase hidden lg:inline">Unit:</span>
        <button type="button" data-global-unit="NM" class="global-unit-pill-btn px-2 py-1 rounded-lg bg-orange-500 text-white shadow-xs transition font-black cursor-pointer">
          NM
        </button>
        <button type="button" data-global-unit="KM" class="global-unit-pill-btn px-2 py-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition font-medium cursor-pointer">
          KM
        </button>
        <button type="button" data-global-unit="M" class="global-unit-pill-btn px-2 py-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition font-medium cursor-pointer">
          M
        </button>
      </div>

      <!-- 4-Pill Basemap Switcher -->
      <div class="bg-slate-100 dark:bg-slate-950/90 p-0.5 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center text-[11px] font-bold">
        <button type="button" data-layer="hybrid" class="basemap-pill-btn px-2.5 py-1 rounded-lg bg-orange-500 text-white shadow-xs transition">
          Hybrid
        </button>
        <button type="button" data-layer="satellite" class="basemap-pill-btn px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
          Satelit
        </button>
        <button type="button" data-layer="roadmap" class="basemap-pill-btn px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
          Roadmap
        </button>
        <button type="button" data-layer="terrain" class="basemap-pill-btn px-2.5 py-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition">
          Terrain
        </button>
      </div>

      <!-- Capture Peta Cetak 16:9 Button -->
      <button type="button" id="btnToggleCaptureMode" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition shadow cursor-pointer" title="Capture / Screenshot Area Peta 16:9 untuk Dokumen Cetak Resmi (Laporan Awal, RENOPS, SITREP, Penutupan)">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span class="hidden sm:inline">Capture Peta Cetak</span>
      </button>

      <!-- Ekspor KMZ/KML Dropdown Menu -->
      <div class="relative" id="exportMenuWrapper">
        <button type="button" id="btnToggleExportMenu" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition shadow cursor-pointer" title="Ekspor Data Spasial">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
          <span class="hidden sm:inline">Ekspor KMZ/KML</span>
          <svg class="w-3 h-3 ml-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <div id="exportDropdownMenu" class="absolute right-0 top-full mt-1.5 w-52 bg-slate-900 border border-slate-700 rounded-xl shadow-2xl py-1 z-50 hidden text-xs font-medium">
          <button type="button" id="btnExportKml" class="w-full text-left px-3 py-2 text-slate-200 hover:bg-orange-500 hover:text-white flex items-center gap-2 transition">
            <span class="text-base">🌍</span>
            <div>
              <div class="font-bold">Google Earth KML</div>
              <div class="text-[10px] text-slate-400">Format OGC .kml standar</div>
            </div>
          </button>
          <button type="button" id="btnExportKmz" class="w-full text-left px-3 py-2 text-slate-200 hover:bg-orange-500 hover:text-white flex items-center gap-2 transition">
            <span class="text-base">📦</span>
            <div>
              <div class="font-bold">Google Earth KMZ</div>
              <div class="text-[10px] text-slate-400">Kompresi .kmz lengkap</div>
            </div>
          </button>
          <button type="button" id="btnExportGeoJson" class="w-full text-left px-3 py-2 text-slate-200 hover:bg-orange-500 hover:text-white flex items-center gap-2 transition">
            <span class="text-base">🌐</span>
            <div>
              <div class="font-bold">Universal GeoJSON</div>
              <div class="text-[10px] text-slate-400">QGIS / ArcGIS / ATAK .json</div>
            </div>
          </button>
        </div>
      </div>

      <!-- Fullscreen Toggle -->
      <button type="button" id="btnToggleFullscreen" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:hover:text-white dark:border-slate-700 transition" title="Layar Penuh">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
      </button>
    </div>
  </header>

  <!-- ========================================== -->
  <!-- 2. MAIN WORKSPACE CONTAINER                -->
  <!-- ========================================== -->
  <div class="flex-1 flex overflow-hidden relative" style="height: calc(100% - 48px);">

    <!-- LEFT TOOLS PLOTTING PANEL (COLLAPSIBLE) -->
    <div id="gisSidebarPanel" class="w-[390px] max-w-full bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col z-10 transition-all duration-300 shadow-xl relative select-none shrink-0">
      
      <!-- Dynamic Operasi / Mode Banner -->
      <div id="gisSidebarOperasiBanner">
        <?php if ($filterMode === 'basemap' || $filterMode === 'dasar'): ?>
          <div class="px-3 py-2 bg-blue-500/10 border-b border-blue-500/20 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-extrabold text-blue-600 dark:text-blue-400">1. Base Map</span>
                <span class="text-slate-600 dark:text-slate-400 text-[10px] ml-1">(Komponen tanpa ID Operasi SAR)</span>
              </div>
            </div>
            <button type="button" class="btn-switch-mode-all text-[10px] font-bold text-slate-500 hover:text-orange-500 underline shrink-0 ml-2 cursor-pointer">Semua</button>
          </div>
        <?php elseif ($filterMode === 'operasi' && $currentOperasi): ?>
          <div class="px-3 py-2 bg-orange-500/10 border-b border-orange-500/20 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse shrink-0"></span>
              <div class="truncate">
                <span class="font-extrabold text-orange-600 dark:text-orange-400">3. Ops #<?= $currentOperasi['id_operasi'] ?></span>
                <span class="text-slate-600 dark:text-slate-300 ml-1"><?= e($currentOperasi['nama_operasi']) ?></span>
              </div>
            </div>
            <button type="button" class="btn-switch-mode-all text-[10px] font-bold text-slate-500 hover:text-red-500 underline shrink-0 ml-2 cursor-pointer" title="Kembali ke semua plotting">Lepas</button>
          </div>
        <?php else: ?>
          <div class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-extrabold text-slate-700 dark:text-slate-200">2. Semua Komponen</span>
                <span class="text-slate-500 dark:text-slate-400 text-[10px] ml-1">(Base Map & Operasi Aktif)</span>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- 4 Tabs Header -->
      <div class="grid grid-cols-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/70 text-[11px] font-bold text-center">
        <button type="button" data-tab="tab-points" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-orange-500 text-orange-600 dark:text-orange-400 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
          <span>Titik (<span id="pointsBadgeCount"><?= $initialPointsCount ?></span>)</span>
        </button>

        <button type="button" data-tab="tab-polygon" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="2" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="22"/></svg>
          <span>Search Area (<span id="polygonsBadgeCount"><?= $initialPolygonsCount ?></span>)</span>
        </button>

        <button type="button" data-tab="tab-pattern" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
          <span>Pola SAR (<span id="patternsBadgeCount"><?= $initialPatternsCount ?></span>)</span>
        </button>

        <button type="button" data-tab="tab-measure" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
          <span>Jarak & ETA (<span id="routesBadgeCount"><?= $initialRoutesCount ?></span>)</span>
        </button>
      </div>

      <!-- TAB 1: TITIK SAR (SAVED POINTS & POINT CREATOR CARD) -->
      <div id="tab-points" class="gis-tab-pane flex-1 overflow-y-auto p-3.5 space-y-3.5 gis-scrollbar bg-slate-50/50 dark:bg-slate-900/50">
        <!-- Top Controls: Draggable Lock & Add Point Button -->
        <div class="space-y-2">
          <div class="flex items-center justify-between gap-2">
            <button type="button" id="btnToggleDragLock" class="flex-1 py-1.5 px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-750">
              <svg id="iconLockClosed" class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <svg id="iconLockOpen" class="w-3.5 h-3.5 text-amber-500 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
              <span id="labelLockStatus">🔒 Posisi Terkunci</span>
            </button>

            <button type="button" id="btnFocusKansarQuick" class="p-2 rounded-xl bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition cursor-pointer" title="Pusatkan Peta ke Kansar Kendari">
              📍
            </button>
          </div>

          <button type="button" id="btnShowPointCreator" class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg transition cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>+ Tambah Titik Baru di Peta</span>
          </button>
        </div>

        <!-- Point Creator / Editor Card (PointCreatorCard.tsx) -->
        <div id="pointCreatorCard" class="hidden p-3.5 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl space-y-3 animate-in fade-in">
          <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <span id="pointCreatorTitle" class="text-xs font-black uppercase text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span>📍 Plot Titik Taktis Baru</span>
            </span>
            <button type="button" id="btnCancelPointCreator" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg transition" title="Tutup Form">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Name & Category -->
          <div class="space-y-2">
            <div>
              <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                Nama / Label Titik <span class="text-slate-400 font-normal text-[9px]">(Opsional - Kosongkan jika tanpa nama diatas titik)</span>
              </label>
              <input type="text" id="pcPointName" placeholder="Contoh: KM Sumber Bahagia (atau kosongkan tanpa nama)" class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-orange-500">
            </div>

            <div>
              <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Kategori Titik Operasi</label>
              <select id="pcPointCategory" class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-orange-500 font-semibold">
                <option value="Tanpa Kategori">⚪ Tanpa Kategori</option>
                <option value="Kantor SAR">🏢 Kantor SAR</option>
                <option value="POS SAR">📻 POS SAR</option>
                <option value="Unit Siaga SAR">📡 Unit Siaga SAR</option>
                <option value="Dermaga SAR">⚓ Dermaga SAR</option>
                <option value="LKP">🚨 LKP</option>
                <option value="Penemuan Korban">📍 Penemuan Korban</option>
                <option value="Lokasi Penemuan Korban">📍 Lokasi Penemuan Korban</option>
                <option value="Posko">🏕️ Posko</option>
                <option value="Entry Poin">🟢 Entry Poin</option>
                <option value="Exit Poin">🔴 Exit Poin</option>
              </select>
            </div>
          </div>

          <!-- Icon Preset Selector (Diverse presets + Alphabet A-Z + Custom Upload) -->
          <div class="space-y-2">
            <div class="flex items-center justify-between">
              <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400">Pilih Desain Logo / Pin Peta:</label>
              <span class="text-[9px] text-slate-400 italic">Visual saja, terpisah dari kategori</span>
            </div>
            
            <div class="grid grid-cols-3 gap-1 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-[10px] font-bold text-center">
              <button type="button" id="tabIconPresetSar" class="py-1 rounded-lg bg-orange-500 text-white cursor-pointer transition">Preset Logo</button>
              <button type="button" id="tabIconAlphabet" class="py-1 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer transition">Huruf (A-Z)</button>
              <button type="button" id="tabIconCustom" class="py-1 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer transition">Logo Kustom</button>
            </div>

            <!-- Panel 1: Preset Logo with Filter Pills & Search -->
            <div id="iconGridSarPanel" class="space-y-1.5">
              <!-- Quick Filter Pills -->
              <div class="flex items-center gap-1 overflow-x-auto pb-1 gis-scrollbar text-[9px] font-bold">
                <button type="button" data-group="all" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-orange-500 text-white shrink-0 cursor-pointer transition">Semua</button>
                <button type="button" data-group="kejadian" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">🚨 Kejadian</button>
                <button type="button" data-group="tempat" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">🏢 Tempat</button>
                <button type="button" data-group="alut" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">🚢 Armada & Alut</button>
                <button type="button" data-group="taktis" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">🎯 Taktis</button>
              </div>

              <!-- Search input for preset logo -->
              <div class="relative">
                <input type="text" id="pcPresetSearch" placeholder="Cari logo (mis: kapal, helikopter, banjir, posko)..." class="w-full pl-6 pr-2 py-1 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[10px] text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-orange-500">
                <span class="absolute left-2 top-1 text-slate-400 text-[10px]">🔍</span>
              </div>

              <div id="iconGridSar" class="grid grid-cols-4 gap-1.5 max-h-48 overflow-y-auto gis-scrollbar p-1">
                <!-- Rendered via JS -->
              </div>
            </div>

            <!-- Panel 2: Alphabet Selector (A-Z) -->
            <div id="iconGridAlphabet" class="hidden grid grid-cols-6 gap-1 max-h-32 overflow-y-auto gis-scrollbar p-1">
              <!-- Rendered via JS -->
            </div>

            <!-- Panel 3: Custom / Upload -->
            <div id="iconGridCustom" class="hidden p-2 text-left text-xs text-slate-600 dark:text-slate-400 space-y-2.5">
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300">Upload Logo Baru (Tersimpan Permanen):</span>
                <span class="text-[9px] text-slate-400 font-mono">PNG/JPG/SVG/WebP</span>
              </div>
              
              <!-- File Upload Input (Hidden) & Dropzone Button -->
              <input type="file" id="pcCustomIconFile" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif" class="hidden">
              <button type="button" id="btnTriggerUploadIcon" class="w-full py-2 px-3 rounded-xl border border-dashed border-orange-500/60 hover:border-orange-500 bg-orange-500/10 hover:bg-orange-500/20 text-orange-600 dark:text-orange-400 font-bold text-xs flex items-center justify-center gap-2 transition cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>+ Upload File Logo / Ikon Baru</span>
              </button>

              <!-- Uploaded Preview Card -->
              <div id="pcCustomIconPreviewCard" class="hidden p-2 rounded-xl bg-orange-50 dark:bg-orange-950/30 border border-orange-500/40 flex items-center justify-between gap-2 text-left">
                <div class="flex items-center gap-2.5 min-w-0">
                  <img id="pcCustomIconThumb" src="" alt="Custom Icon" class="w-8 h-8 rounded-lg object-contain bg-white dark:bg-slate-900 p-0.5 border border-orange-400/40 shrink-0">
                  <div class="min-w-0 flex-1">
                    <div id="pcCustomIconFileName" class="text-xs font-bold text-slate-900 dark:text-white truncate">icon.png</div>
                    <div class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1">
                      <span>✓ Logo Terpilih Aktif</span>
                    </div>
                  </div>
                </div>
                <button type="button" id="btnRemoveCustomIcon" class="px-2.5 py-1 text-[11px] font-bold text-red-600 hover:text-red-700 dark:text-red-400 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition cursor-pointer flex items-center gap-1 shrink-0" title="Lepas logo kustom dari titik ini (titik kembali ke pin standar)">
                  <span>✕ Lepas dari Titik</span>
                </button>
              </div>

              <!-- Daftar Logo Kustom Tersimpan -->
              <div class="pt-1 border-t border-slate-100 dark:border-slate-800 space-y-1.5">
                <div class="flex items-center justify-between text-[10px] font-bold text-slate-600 dark:text-slate-400">
                  <span>Pustaka Logo Kustom (Klik untuk pilih):</span>
                  <span id="customIconCountBadge" class="text-[9px] text-orange-600 dark:text-orange-400 font-mono">0 Logo</span>
                </div>
                <div id="customIconGalleryContainer" class="grid grid-cols-4 gap-1.5 max-h-40 overflow-y-auto gis-scrollbar p-1 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                  <div class="col-span-4 p-3 text-center text-[10px] text-slate-400">Memuat daftar logo...</div>
                </div>
              </div>
            </div>
          </div>

          <!-- DMS Numeric Editor (Deg/Min/Sec with S/N & E/W) -->
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex items-center justify-between text-[10px] font-bold text-slate-600 dark:text-slate-400">
              <span>INPUT KOORDINAT NUMERIK (DMS)</span>
              <span class="text-orange-600 dark:text-orange-400 font-mono">Realtime WGS 84</span>
            </div>

            <!-- Lintang (Latitude) -->
            <div>
              <span class="text-[10px] text-slate-600 dark:text-slate-400 block mb-0.5 font-bold">Lintang (Latitude / S-N):</span>
              <div class="grid grid-cols-4 gap-1 items-center font-mono">
                <input type="number" id="dmsLatDeg" min="0" max="90" placeholder="04" class="w-full px-1.5 py-1 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-center font-bold text-xs text-slate-900 dark:text-white">
                <input type="number" id="dmsLatMin" min="0" max="59" placeholder="02" class="w-full px-1.5 py-1 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-center font-bold text-xs text-slate-900 dark:text-white">
                <input type="number" id="dmsLatSec" min="0" max="59.9" step="0.1" placeholder="16.0" class="w-full px-1.5 py-1 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-center font-bold text-xs text-slate-900 dark:text-white">
                <select id="dmsLatDir" class="w-full px-1 py-1 rounded bg-orange-500/10 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 border border-orange-500/30 font-bold text-center text-xs">
                  <option value="S">S (LS)</option>
                  <option value="N">N (LU)</option>
                </select>
              </div>
            </div>

            <!-- Bujur (Longitude) -->
            <div>
              <span class="text-[10px] text-slate-600 dark:text-slate-400 block mb-0.5 font-bold">Bujur (Longitude / E-W):</span>
              <div class="grid grid-cols-4 gap-1 items-center font-mono">
                <input type="number" id="dmsLngDeg" min="0" max="180" placeholder="122" class="w-full px-1.5 py-1 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-center font-bold text-xs text-slate-900 dark:text-white">
                <input type="number" id="dmsLngMin" min="0" max="59" placeholder="29" class="w-full px-1.5 py-1 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-center font-bold text-xs text-slate-900 dark:text-white">
                <input type="number" id="dmsLngSec" min="0" max="59.9" step="0.1" placeholder="12.0" class="w-full px-1.5 py-1 rounded bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-center font-bold text-xs text-slate-900 dark:text-white">
                <select id="dmsLngDir" class="w-full px-1 py-1 rounded bg-orange-500/10 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 border border-orange-500/30 font-bold text-center text-xs">
                  <option value="E">E (BT)</option>
                  <option value="W">W (BB)</option>
                </select>
              </div>
            </div>

            <!-- Readout Decimal -->
            <div class="text-[10px] font-mono text-slate-500 dark:text-slate-400 text-right pt-1">
              Desimal: <span id="pcReadoutDecimal" class="text-orange-600 dark:text-orange-400 font-bold">-</span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
            <button type="button" id="btnCancelSavePoint" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">Batal</button>
            <button type="button" id="btnSavePointConfirm" class="px-4 py-2 text-xs font-black bg-orange-600 hover:bg-orange-500 text-white rounded-xl shadow-md shadow-orange-600/20 transition flex items-center gap-1.5 cursor-pointer">
              <span>Simpan Titik SAR</span>
            </button>
          </div>
        </div>

        <!-- Saved Points List Cards Container -->
        <div class="space-y-2">
          <div class="flex items-center justify-between text-xs font-bold text-slate-400 uppercase tracking-wider">
            <span>Daftar Titik Tersimpan</span>
            <span class="text-[10px] text-slate-500">Klik untuk Fokus</span>
          </div>

          <div id="savedPointsListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

      <!-- TAB 2: POLYGON SEARCH AREA (POLYGON MANAGER) -->
      <div id="tab-polygon" class="gis-tab-pane hidden flex-1 overflow-y-auto p-3.5 space-y-3.5 gis-scrollbar bg-slate-50/50 dark:bg-slate-900/50">
        <!-- New Search Area Button -->
        <button type="button" id="btnStartNewPolygon" class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg shadow-orange-600/20 transition cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/></svg>
          <span>+ Buat Search Area Baru</span>
        </button>

        <!-- Drawing & Editing Mode Panel -->
        <div id="polygonDrawingEditorCard" class="hidden p-3.5 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl space-y-3 animate-in fade-in">
          <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <span id="polyEditorTitle" class="text-xs font-black uppercase text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span>📐 Rancang Search Area Baru</span>
            </span>
            <button type="button" id="btnCancelDrawingPoly" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg transition" title="Tutup Form">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Pilihan Bentuk Search Area (Polygon, Rectangle, Circle) -->
          <div>
            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5">Bentuk Geometri Search Area:</label>
            <div class="grid grid-cols-3 gap-1.5 text-xs font-bold text-center">
              <button type="button" id="btnShapePolygon" data-shape="polygon" class="poly-shape-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 font-bold transition shadow-xs cursor-pointer flex flex-col items-center gap-1">
                <span class="text-base">📐</span>
                <span class="text-[10px] font-bold">Polygon Bebas</span>
              </button>
              <button type="button" id="btnShapeRectangle" data-shape="rectangle" class="poly-shape-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:border-orange-500 font-bold transition cursor-pointer flex flex-col items-center gap-1">
                <span class="text-base">⏹️</span>
                <span class="text-[10px] font-bold">Persegi (Kotak)</span>
              </button>
              <button type="button" id="btnShapeCircle" data-shape="circle" class="poly-shape-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:border-orange-500 font-bold transition cursor-pointer flex flex-col items-center gap-1">
                <span class="text-base">⭕</span>
                <span class="text-[10px] font-bold">Circle (Radius)</span>
              </button>
            </div>
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Nama Sektor Search Area:</label>
            <input type="text" id="polyEditorName" value="" placeholder="SEARCH AREA SRU 1" class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-orange-500">
          </div>

          <!-- Dynamic Parameter Input: Circle Radius -->
          <div id="polyCircleParamsBox" class="hidden p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Parameter Lingkaran Radius:</span>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Radius Pencarian:</label>
                <div class="flex items-center gap-1">
                  <input type="number" id="polyCircleRadiusNm" value="1.5" min="0.01" step="0.1" class="w-full px-2 py-1.5 rounded-xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white focus:border-orange-500">
                  <select id="polyCircleRadiusUnit" class="px-1.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-orange-600 dark:text-orange-400 font-bold focus:border-orange-500 cursor-pointer">
                    <option value="NM" selected>NM</option>
                    <option value="KM">Km</option>
                    <option value="M">Meter</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Pusat Datum / LKP:</label>
                <div id="polyCircleCenterReadout" class="text-[10px] font-mono font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-950 px-2 py-2 rounded-xl border border-slate-200 dark:border-slate-700 truncate">Klik peta untuk pusat</div>
              </div>
            </div>
          </div>

          <!-- Dynamic Parameter Input: Rectangle Dimensions -->
          <div id="polyRectangleParamsBox" class="hidden p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Parameter Kotak Persegi:</span>
              <div class="flex items-center gap-1 text-[10px]">
                <span class="text-slate-500">Satuan:</span>
                <select id="polyRectDimUnit" class="px-1.5 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] text-orange-600 dark:text-orange-400 font-bold focus:border-orange-500 cursor-pointer">
                  <option value="NM" selected>NM</option>
                  <option value="KM">Km</option>
                  <option value="M">Meter</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Panjang (P):</label>
                <div class="flex items-center gap-1">
                  <input type="number" id="polyRectWidthNm" value="2.0" min="0.01" step="0.1" class="w-full px-2 py-1.5 rounded-xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white focus:border-orange-500">
                  <span id="polyRectWidthUnitLabel" class="text-[10px] font-bold text-slate-400">NM</span>
                </div>
              </div>
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Lebar (L):</label>
                <div class="flex items-center gap-1">
                  <input type="number" id="polyRectHeightNm" value="2.0" min="0.01" step="0.1" class="w-full px-2 py-1.5 rounded-xl bg-white dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs font-mono font-bold text-slate-900 dark:text-white focus:border-orange-500">
                  <span id="polyRectHeightUnitLabel" class="text-[10px] font-bold text-slate-400">NM</span>
                </div>
              </div>
            </div>
          </div>

          <div id="polyDrawingGuideText" class="p-2.5 rounded-xl bg-orange-500/10 border border-orange-500/20 text-[11px] text-orange-700 dark:text-orange-300">
            👉 <strong>Klik di atas peta</strong> untuk menambah Titik A, B, C, dst. Semua titik sudut <strong>dapat digeser langsung</strong> pada peta.
          </div>

          <!-- Real-Time Metrics -->
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
              <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-semibold">Luas Search Area</span>
              <span id="polyLiveSqNm" class="text-sm font-black text-emerald-600 dark:text-emerald-400 font-mono">0.00 NM²</span>
              <span id="polyLiveSqKm" class="text-[10px] text-slate-500 dark:text-slate-400 block font-mono">0.00 km² (0 Ha)</span>
            </div>
            <div class="p-2.5 rounded-xl bg-orange-500/10 border border-orange-500/20">
              <span class="text-[10px] text-slate-500 dark:text-slate-400 block font-semibold">Keliling Perimeter</span>
              <span id="polyLivePerimeterNm" class="text-sm font-black text-orange-600 dark:text-orange-400 font-mono">0.00 NM</span>
              <span id="polyLivePerimeterKm" class="text-[10px] text-slate-500 dark:text-slate-400 block font-mono">0.00 km</span>
            </div>
          </div>

          <!-- Nearest Posko Detection -->
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-[11px] text-slate-700 dark:text-slate-300 flex items-center justify-between">
            <span>Posko Terdekat:</span>
            <span id="polyLiveNearestPosko" class="font-bold text-orange-600 dark:text-orange-400 font-mono">Kansar Kendari</span>
          </div>

          <!-- Styling Controls (Stroke color, weight, fill color, fill opacity) -->
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Penyesuaian Garis & Warna:</span>

            <!-- Stroke Color Swatches -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-1">
                <span class="text-slate-600 dark:text-slate-400">1. Warna Garis:</span>
                <span id="labelStrokeColor" class="font-mono text-orange-600 dark:text-orange-400 font-bold">#EA580C</span>
              </div>
              <div class="flex items-center gap-1.5 flex-wrap" id="polyStrokeColorSwatches">
                <!-- Color dots -->
              </div>
            </div>

            <!-- Stroke Weight Slider -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-0.5">
                <span class="text-slate-600 dark:text-slate-400">2. Ketebalan Garis:</span>
                <span id="labelStrokeWeight" class="font-mono text-slate-900 dark:text-white font-bold">2.5 px</span>
              </div>
              <input type="range" id="polyStrokeWeightSlider" min="1" max="8" step="0.5" value="2.5" class="w-full accent-orange-500">
            </div>

            <!-- Fill Opacity Slider -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-0.5">
                <span class="text-slate-600 dark:text-slate-400">3. Transparansi Arsiran:</span>
                <span id="labelFillOpacity" class="font-mono text-slate-900 dark:text-white font-bold">20%</span>
              </div>
              <input type="range" id="polyFillOpacitySlider" min="0.05" max="0.8" step="0.05" value="0.2" class="w-full accent-orange-500">
            </div>
          </div>

          <!-- Vertices Table (Titik A, B, C...) -->
          <div class="space-y-1.5">
            <span class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Daftar Titik Sudut:</span>
            <div id="polyVerticesListTable" class="space-y-1 max-h-36 overflow-y-auto gis-scrollbar text-xs">
              <!-- Rendered via JS -->
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800">
            <button type="button" id="btnCancelPolyForm" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">Batal</button>
            <button type="button" id="btnSavePolygonConfirm" class="px-4 py-2 text-xs font-black bg-orange-600 hover:bg-orange-500 text-white rounded-xl shadow-md shadow-orange-600/20 transition cursor-pointer">
              Simpan Polygon Area
            </button>
          </div>
        </div>

        <!-- Saved Polygons List -->
        <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800">
          <div class="flex items-center justify-between text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
            <span>Sektor Area Tersimpan</span>
          </div>

          <div id="savedPolygonsListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

      <!-- TAB 3: POLA SAR (SEARCH PATTERNS IAMSAR) -->
      <div id="tab-pattern" class="gis-tab-pane hidden flex-1 overflow-y-auto p-3.5 space-y-3.5 gis-scrollbar bg-slate-50/50 dark:bg-slate-900/50">
        <!-- New Search Pattern Button -->
        <button type="button" id="btnStartNewPattern" class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg shadow-orange-600/20 transition cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/></svg>
          <span>+ Buat Pola Lintasan SAR Baru</span>
        </button>

        <!-- Pattern Creation / Editor Card -->
        <div id="patternCreationCard" class="hidden p-3.5 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl space-y-3 animate-in fade-in">
          <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <span class="text-xs font-black uppercase text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span id="patternFormHeaderTitle">📐 Parameter Pola Lintasan IAMSAR</span>
            </span>
            <button type="button" id="btnCancelPatternFormTop" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg transition" title="Tutup Form">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <div>
            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Nama Pola Lintasan:</label>
            <input type="text" id="patternFormName" value="" placeholder="CREAPING LANE 1/ESAR 1/" class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-orange-500">
          </div>

          <!-- Base Polygon / Datum Selection -->
          <div>
            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Target Area Poligon / Datum Basis:</label>
            <select id="patternTargetPolygon" class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-orange-500">
              <!-- Populated with savedPolygons or LKP -->
            </select>
          </div>

          <!-- Pattern Types Selector (3 Baku: Parallel, Creeping, E-SAR) -->
          <div>
            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5">Metode Pola IAMSAR (Pilih Salah Satu):</label>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-1.5 text-xs font-bold">
              <button type="button" data-ptype="parallel_track" class="pat-type-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-left transition font-bold shadow-xs cursor-pointer">
                <div class="text-xs font-black">Parallel Track (PS)</div>
                <div class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Lintasan sejajar sapuan luas</div>
              </button>
              <button type="button" data-ptype="creeping_line" class="pat-type-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-left hover:border-orange-500 transition cursor-pointer">
                <div class="text-xs font-black">Creeping Line (CS)</div>
                <div class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Sapuan rapat tegak lurus</div>
              </button>
              <button type="button" data-ptype="e_sar" class="pat-type-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-left hover:border-orange-500 transition cursor-pointer">
                <div class="text-xs font-black">Pola Pencarian E-SAR</div>
                <div class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Sapuan sejajar personil (Darat/Hutan)</div>
              </button>
            </div>
          </div>

          <!-- E-SAR Sweep Direction Selector -->
          <div id="esarOrientationWrapper" class="hidden space-y-2 p-2.5 rounded-xl bg-sky-500/10 border border-sky-500/30">
            <div class="flex items-center justify-between text-xs">
              <label class="block text-[10px] font-bold text-sky-800 dark:text-sky-300 uppercase tracking-wider">Arah Sapuan E-SAR (Lintasan Panah):</label>
              <span class="text-[9px] font-mono text-sky-600 dark:text-sky-400 font-bold">ORIENTASI TAKTIS</span>
            </div>
            <div class="grid grid-cols-2 gap-1.5 text-xs font-bold">
              <button type="button" id="btnEsarOrientHorizontal" data-orientation="horizontal" class="esar-orient-btn p-2 rounded-xl border border-sky-500 bg-sky-500/20 text-sky-700 dark:text-sky-300 flex items-center justify-center gap-1.5 transition shadow-xs cursor-pointer font-bold">
                <span>↔️</span>
                <span>Horizontal (Barat - Timur)</span>
              </button>
              <button type="button" id="btnEsarOrientVertical" data-orientation="vertical" class="esar-orient-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-sky-500 flex items-center justify-center gap-1.5 transition cursor-pointer">
                <span>↕️</span>
                <span>Vertikal (Utara - Selatan)</span>
              </button>
            </div>

            <!-- E-SAR 4-Vector Direction Controls -->
            <div id="esarDirectionControls" class="pt-1.5 border-t border-sky-500/20 space-y-1">
              <label class="block text-[10px] font-bold text-sky-800 dark:text-sky-300">Arah Gerak Sapuan Maju:</label>
              <div id="esarDirHorizontalGroup" class="grid grid-cols-2 gap-1.5 text-xs font-bold">
                <button type="button" id="btnEsarDirWtoE" data-direction="west_to_east" data-dir="west_to_east" class="esar-dir-btn p-1.5 rounded-lg border border-sky-500 bg-sky-500/20 text-sky-700 dark:text-sky-300 flex items-center justify-center gap-1 cursor-pointer">
                  <span>➡️</span>
                  <span>Barat ➔ Timur</span>
                </button>
                <button type="button" id="btnEsarDirEtoW" data-direction="east_to_west" data-dir="east_to_west" class="esar-dir-btn p-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-sky-500 flex items-center justify-center gap-1 cursor-pointer">
                  <span>⬅️</span>
                  <span>Timur ➔ Barat</span>
                </button>
              </div>
              <div id="esarDirVerticalGroup" class="hidden grid grid-cols-2 gap-1.5 text-xs font-bold">
                <button type="button" id="btnEsarDirNtoS" data-direction="north_to_south" data-dir="north_to_south" class="esar-dir-btn p-1.5 rounded-lg border border-sky-500 bg-sky-500/20 text-sky-700 dark:text-sky-300 flex items-center justify-center gap-1 cursor-pointer">
                  <span>⬇️</span>
                  <span>Utara ➔ Selatan</span>
                </button>
                <button type="button" id="btnEsarDirStoN" data-direction="south_to_north" data-dir="south_to_north" class="esar-dir-btn p-1.5 rounded-lg border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-sky-500 flex items-center justify-center gap-1 cursor-pointer">
                  <span>⬆️</span>
                  <span>Selatan ➔ Utara</span>
                </button>
              </div>
            </div>

            <p class="text-[9px] text-sky-700 dark:text-sky-300 leading-tight">
              Tiap lintasan sapuan dilengkapi <b>tanda panah arah maju</b> dan personil dihitung otomatis sesuai luas area.
            </p>
          </div>

          <!-- Parameters: Track Spacing & Auto Sweep Calculation -->
          <div class="space-y-2">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Track Spacing (S):</label>
                <div class="flex items-center gap-1">
                  <input type="number" id="patternTrackSpacing" value="1.0" step="0.01" min="0.01" class="w-full px-2 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white font-mono font-bold focus:border-orange-500 transition">
                  <select id="patternSpacingUnit" class="px-1.5 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-orange-600 dark:text-orange-400 font-bold focus:border-orange-500">
                    <option value="NM" selected>NM</option>
                    <option value="KM">Km</option>
                    <option value="M">Meter</option>
                  </select>
                </div>
              </div>
              <div>
                <div class="flex items-center justify-between mb-1">
                  <label id="patternLegsLabel" class="block text-[10px] font-bold text-slate-600 dark:text-slate-400">Jumlah Sweep:</label>
                  <span id="patternAutoBadge" class="text-[9px] font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-1.5 py-0.2 rounded border border-emerald-500/20">AUTO</span>
                </div>
                <input type="number" id="patternLegsCount" value="6" min="2" max="50" class="w-full px-2 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white font-mono font-bold focus:border-orange-500 transition">
              </div>
            </div>

            <!-- Auto Calculation Live Indicator & Margin Note -->
            <div id="patternAutoCalcBox" class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 text-[11px] space-y-1.5 font-mono">
              <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                <span class="text-[10px] font-sans text-slate-500 dark:text-slate-400">Dimensi Area & Sweep:</span>
                <span id="patternDimInfo" class="text-orange-600 dark:text-orange-400 font-bold">P: - • L: -</span>
              </div>
              <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
                <span class="text-[10px] font-sans text-slate-500 dark:text-slate-400">Margin Batas Poligon:</span>
                <span id="patternMarginInfo" class="text-emerald-600 dark:text-emerald-400 font-bold">0.50 NM (S/2)</span>
              </div>
              <div id="patternEsarPersonnelInfo" class="hidden flex items-center justify-between text-slate-700 dark:text-slate-300 border-t border-slate-200 dark:border-slate-800 pt-1">
                <span class="text-[10px] font-sans text-slate-500 dark:text-slate-400">Rekomendasi Tim E-SAR:</span>
                <span id="patternPersonnelCalcReadout" class="text-sky-600 dark:text-sky-400 font-bold">Track: 0.10 NM • 8 Personil</span>
              </div>
              <div class="text-[9px] font-sans text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800 pt-1 leading-relaxed">
                💡 <span class="text-slate-600 dark:text-slate-300">Garis pola otomatis disesuaikan (fitted) dengan batas poligon non-simetris agar tetap berada di dalam area pencarian.</span>
              </div>
            </div>

            <!-- Kustomisasi Warna, Transparansi & Gaya Garis Pola SAR -->
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-800 space-y-2.5">
              <div class="text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider flex items-center justify-between">
                <span>🎨 Kustomisasi Garis Pola SAR</span>
                <span id="patternOpacityValDisplay" class="font-mono text-sky-600 dark:text-sky-400 font-bold">90%</span>
              </div>

              <!-- Color Palette & Custom Picker -->
              <div class="space-y-1.5">
                <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400">Warna Garis Lintasan:</label>
                <div class="flex items-center gap-2">
                  <input type="color" id="patternLineColor" value="#0284c7" class="w-8 h-8 rounded-lg cursor-pointer border border-slate-300 dark:border-slate-700 bg-transparent p-0.5" title="Pilih warna bebas">
                  <div class="flex items-center gap-1.5 flex-1 flex-wrap">
                    <button type="button" class="w-6 h-6 rounded-md bg-[#0284c7] border border-white/20 shadow-xs cursor-pointer hover:scale-110 transition pat-color-preset" data-color="#0284c7" title="Biru Taktis"></button>
                    <button type="button" class="w-6 h-6 rounded-md bg-[#f97316] border border-white/20 shadow-xs cursor-pointer hover:scale-110 transition pat-color-preset" data-color="#f97316" title="Oranye Basarnas"></button>
                    <button type="button" class="w-6 h-6 rounded-md bg-[#ef4444] border border-white/20 shadow-xs cursor-pointer hover:scale-110 transition pat-color-preset" data-color="#ef4444" title="Merah"></button>
                    <button type="button" class="w-6 h-6 rounded-md bg-[#eab308] border border-white/20 shadow-xs cursor-pointer hover:scale-110 transition pat-color-preset" data-color="#eab308" title="Kuning"></button>
                    <button type="button" class="w-6 h-6 rounded-md bg-[#10b981] border border-white/20 shadow-xs cursor-pointer hover:scale-110 transition pat-color-preset" data-color="#10b981" title="Hijau"></button>
                    <button type="button" class="w-6 h-6 rounded-md bg-[#a855f7] border border-white/20 shadow-xs cursor-pointer hover:scale-110 transition pat-color-preset" data-color="#a855f7" title="Ungu"></button>
                    <button type="button" class="w-6 h-6 rounded-md bg-[#ffffff] border border-slate-400 shadow-xs cursor-pointer hover:scale-110 transition pat-color-preset" data-color="#ffffff" title="Putih"></button>
                  </div>
                </div>
              </div>

              <!-- Opacity Slider -->
              <div class="space-y-1">
                <div class="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400">
                  <span class="font-bold">Transparansi / Opasitas Garis:</span>
                  <span class="text-[9px] text-slate-400">10% - 100%</span>
                </div>
                <input type="range" id="patternLineOpacity" min="0.1" max="1" step="0.05" value="0.9" class="w-full accent-sky-600 dark:accent-sky-500 cursor-pointer">
              </div>

              <!-- Line Style & Weight -->
              <div class="grid grid-cols-2 gap-2 pt-1 border-t border-slate-200 dark:border-slate-800 text-[10px]">
                <div>
                  <label class="block font-bold text-slate-500 dark:text-slate-400 mb-1">Ketebalan:</label>
                  <select id="patternLineWeight" class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-200 font-bold focus:border-orange-500">
                    <option value="2">2 px (Tipis)</option>
                    <option value="3" selected>3 px (Standar)</option>
                    <option value="4">4 px (Tebal)</option>
                    <option value="5">5 px (Ekstra Tebal)</option>
                  </select>
                </div>
                <div>
                  <label class="block font-bold text-slate-500 dark:text-slate-400 mb-1">Gaya Garis:</label>
                  <select id="patternLineStyle" class="w-full px-2 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-200 font-bold focus:border-orange-500">
                    <option value="dashed" selected>Putus-putus (Dash)</option>
                    <option value="solid">Garis Utuh (Solid)</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Pattern Status / Alert Box -->
          <div id="patternStatusMsg" class="hidden text-[11px] p-2.5 rounded-xl transition"></div>

          <!-- Pattern Results Box -->
          <div id="patternResultsCard" class="hidden p-3 bg-slate-50 dark:bg-slate-900/90 rounded-2xl border border-sky-500/30 space-y-2 text-xs">
            <div class="flex items-center justify-between font-bold">
              <span class="text-slate-600 dark:text-slate-400">Total Panjang Lintasan (L):</span>
              <span id="resPatternDistNm" class="text-sm font-black text-sky-600 dark:text-sky-400 font-mono">0.00 NM</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400 font-semibold">
              <span title="IAMSAR Vol II Formula: Luas Sapuan = Track Spacing (S) × Total Lintasan (L)">Luas Total Sapuan (A = S × L):</span>
              <span id="resPatternAreaSwept" class="font-bold text-emerald-600 dark:text-emerald-400 font-mono">0.00 NM²</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
              <span>Metode & Sapuan:</span>
              <span id="resPatternTypeInfo" class="font-bold text-orange-600 dark:text-orange-400 font-mono">-</span>
            </div>
            <div class="flex items-center justify-between text-slate-600 dark:text-slate-400">
              <span>Jumlah Waypoint (WP):</span>
              <span id="resPatternWpCount" class="font-mono text-slate-900 dark:text-white font-bold">0 WP</span>
            </div>
          </div>

          <!-- Save Pattern & Cancel Action Buttons (Always Visible in Form Footer) -->
          <div class="pt-2 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
            <button type="button" id="btnCancelPatternForm" class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-bold transition">
              Batal / Bersihkan
            </button>
            <button type="button" id="btnSavePatternConfirm" class="flex-1 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold transition shadow-md shadow-sky-600/20 flex items-center justify-center gap-1.5 cursor-pointer">
              <span>💾 Simpan Pola SAR</span>
            </button>
          </div>
        </div>

        <!-- Saved Search Patterns List -->
        <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800">
          <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pola Lintasan Tersimpan</div>
          <div id="savedPatternsListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

      <!-- TAB 4: JARAK & ETA (MEASURE DISTANCE & SPEED SIMULATOR) -->
      <div id="tab-measure" class="gis-tab-pane hidden flex-1 overflow-y-auto p-3.5 space-y-3.5 gis-scrollbar bg-slate-50/50 dark:bg-slate-900/50">
        <!-- New Route Button -->
        <button type="button" id="btnStartNewRoute" class="w-full py-2.5 px-3 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white text-xs font-black uppercase tracking-wider flex items-center justify-center gap-2 shadow-lg shadow-orange-600/20 transition cursor-pointer">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m3 11 18-5-5 18-3-8-10-5z"/></svg>
          <span>+ Buat / Rencanakan Rute Baru</span>
        </button>

        <!-- Route Planning & Measuring Form Card -->
        <div id="routePlanningCard" class="hidden p-3.5 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl space-y-3 animate-in fade-in">
          <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-slate-800">
            <span class="text-xs font-black uppercase text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span>🧭 Perencanaan Rute Navigasi SAR</span>
            </span>
            <button type="button" id="btnCancelRouteFormTop" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg transition" title="Tutup Form">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Input Nama Jalur Operasi Langsung Pada Form (Bukan Konfirmasi Browser/Localhost) -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400">
                Nama / Label Jalur Operasi <span class="text-orange-500">*</span>
              </label>
              <span id="routeRealtimeStatusBadge" class="text-[9px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition">
                ⚡ Siap Rancang
              </span>
            </div>
            <input 
              type="text" 
              id="measureRouteNameInput" 
              value=""
              placeholder="RUTE 1" 
              class="w-full px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:border-orange-500 transition font-medium"
            >
          </div>

          <!-- Pilihan Mode Pengukuran / Rute: Jarak Lurus & Jalur Manual -->
          <div>
            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1.5">Metode Pengukuran / Rute:</label>
            <div class="grid grid-cols-2 gap-2 text-xs font-bold">
              <button type="button" id="btnModeStraight" class="p-2.5 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-center transition shadow-xs flex flex-col items-center gap-1 cursor-pointer">
                <span class="text-sm">📏</span>
                <span class="font-extrabold text-xs">Jarak Lurus (2 Titik)</span>
                <span class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Garis lurus asal ➔ tujuan</span>
              </button>
              <button type="button" id="btnModePath" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-center hover:text-slate-900 dark:hover:text-white transition flex flex-col items-center gap-1 cursor-pointer">
                <span class="text-sm">📍</span>
                <span class="font-extrabold text-xs">Jalur Manual (Multi-Titik)</span>
                <span class="text-[10px] font-normal text-slate-500 dark:text-slate-400">Plot waypoint bebas di peta</span>
              </button>
            </div>
          </div>

          <!-- Petunjuk & Counter Plotting Manual -->
          <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-[11px] text-emerald-700 dark:text-emerald-300 space-y-1.5">
            <div class="font-bold flex items-center justify-between">
              <span class="flex items-center gap-1.5 font-bold"><span>🎯</span> Plotting Titik Pada Peta</span>
              <div class="flex items-center gap-1.5">
                <button type="button" id="btnUndoMeasureRouteWaypoint" onclick="window.SAR_GIS && window.SAR_GIS.undoRoutePoint()" class="px-2 py-0.5 text-[10px] font-bold rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-700 dark:text-amber-300 border border-amber-500/30 transition cursor-pointer flex items-center gap-1" title="Batalkan / hapus titik waypoint terakhir">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7v6h6M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
                  <span>Undo Titik</span>
                </button>
                <span id="manualPointsCounter" class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">0 Titik</span>
              </div>
            </div>
            <p class="text-[10px] text-slate-600 dark:text-slate-400 leading-tight">
              Klik pada peta untuk menambah titik rute. Pin waypoint dapat digeser pada peta untuk koreksi posisi, atau gunakan <b>Undo Titik</b> untuk membatalkan titik terakhir.
            </p>
          </div>

          <!-- Live Distance & ETA Card with DMS Coordinates Readout -->
          <div class="p-3.5 bg-slate-50 dark:bg-slate-900/90 rounded-2xl border border-emerald-500/30 space-y-2.5 text-xs">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-1.5">
                <span class="text-slate-600 dark:text-slate-400 font-bold">Total Jarak Tempuh:</span>
                <select id="measureDistanceUnit" class="px-1.5 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[10px] text-emerald-600 dark:text-emerald-400 font-bold focus:border-emerald-500 cursor-pointer">
                  <option value="NM" selected>NM</option>
                  <option value="KM">Km</option>
                  <option value="M">Meter</option>
                </select>
              </div>
              <div class="text-right">
                <span id="liveRouteDistNm" class="text-base font-black text-emerald-600 dark:text-emerald-400 font-mono">0.00 NM</span>
                <span id="liveRouteDistKm" class="text-[10px] text-slate-500 dark:text-slate-400 block font-mono">0.00 km</span>
              </div>
            </div>

            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
              <span>Arah Haluan (Bearing):</span>
              <span id="liveRouteBearing" class="font-bold text-orange-600 dark:text-orange-400 font-mono">0° (Utara)</span>
            </div>

            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
              <span>Estimasi Waktu Tiba (ETA):</span>
              <span id="liveRouteEta" class="font-bold text-slate-900 dark:text-white font-mono text-sm">0 Menit</span>
            </div>

            <!-- DMS Coordinates Result Box -->
            <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-1.5 text-[11px] font-mono">
              <div class="flex items-start justify-between gap-1">
                <span class="text-emerald-600 dark:text-emerald-400 font-bold shrink-0">📍 Asal (A):</span>
                <span id="liveRouteStartDms" class="text-slate-700 dark:text-slate-200 text-right font-bold text-[10px]">Belum ditentukan</span>
              </div>
              <div class="flex items-start justify-between gap-1">
                <span class="text-red-600 dark:text-red-400 font-bold shrink-0">🎯 Tujuan (B):</span>
                <span id="liveRouteEndDms" class="text-slate-700 dark:text-slate-200 text-right font-bold text-[10px]">Belum ditentukan</span>
              </div>
            </div>
          </div>

          <!-- Quick Speed Presets -->
          <div class="space-y-1.5">
            <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider">Preset Armada SAR Kendari:</label>
            <div class="grid grid-cols-2 gap-1.5 text-[11px]">
              <button type="button" data-speed="29" data-unit="knot" class="speed-preset-btn p-2 rounded-xl bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-850 border border-slate-200 dark:border-slate-800 text-left transition">
                <div class="font-bold text-slate-900 dark:text-white">KN SAR Pacitan</div>
                <div class="text-[10px] text-orange-600 dark:text-orange-400 font-mono">29 Knot</div>
              </button>
              <button type="button" data-speed="24" data-unit="knot" class="speed-preset-btn p-2 rounded-xl bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-850 border border-slate-200 dark:border-slate-800 text-left transition">
                <div class="font-bold text-slate-900 dark:text-white">Rescue Boat 210</div>
                <div class="text-[10px] text-orange-600 dark:text-orange-400 font-mono">24 Knot</div>
              </button>
              <button type="button" data-speed="32" data-unit="knot" class="speed-preset-btn p-2 rounded-xl bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-850 border border-slate-200 dark:border-slate-800 text-left transition">
                <div class="font-bold text-slate-900 dark:text-white">RIB 01 Kendari</div>
                <div class="text-[10px] text-orange-600 dark:text-orange-400 font-mono">32 Knot</div>
              </button>
              <button type="button" data-speed="8" data-unit="knot" class="speed-preset-btn p-2 rounded-xl bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-850 border border-slate-200 dark:border-slate-800 text-left transition">
                <div class="font-bold text-slate-900 dark:text-white">Rubber Boat</div>
                <div class="text-[10px] text-orange-600 dark:text-orange-400 font-mono">8 Knot</div>
              </button>
              <button type="button" data-speed="130" data-unit="knot" class="speed-preset-btn p-2 rounded-xl bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-850 border border-slate-200 dark:border-slate-800 text-left transition">
                <div class="font-bold text-slate-900 dark:text-white">Heli Dauphin</div>
                <div class="text-[10px] text-orange-600 dark:text-orange-400 font-mono">130 Knot</div>
              </button>
              <button type="button" data-speed="60" data-unit="kmh" class="speed-preset-btn p-2 rounded-xl bg-slate-50 dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-850 border border-slate-200 dark:border-slate-800 text-left transition">
                <div class="font-bold text-slate-900 dark:text-white">Rescue Car Darat</div>
                <div class="text-[10px] text-orange-600 dark:text-orange-400 font-mono">60 km/jam</div>
              </button>
            </div>
          </div>

          <!-- Custom Speed Input -->
          <div class="flex items-center gap-2">
            <div class="flex-1">
              <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Kecepatan Manual:</label>
              <input type="number" id="measureVesselSpeed" value="25" min="0.1" step="0.1" class="w-full px-2 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white font-mono">
            </div>
            <div>
              <label class="block text-[10px] font-bold text-slate-600 dark:text-slate-400 mb-1">Satuan:</label>
              <select id="measureSpeedUnit" class="px-2 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs text-slate-900 dark:text-white font-bold cursor-pointer">
                <option value="knot">Knot (Kts)</option>
                <option value="kmh">Km / Jam</option>
                <option value="ms">m / detik (m/s)</option>
              </select>
            </div>
          </div>

          <!-- Feedback Alert Banner -->
          <div id="routeSaveFeedbackBanner" class="hidden text-xs p-2.5 rounded-xl transition"></div>

          <!-- Action Buttons (Batal / Bersihkan, Undo & Simpan Rute) -->
          <div class="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
            <button type="button" id="btnClearMeasureRoute" onclick="window.SAR_GIS && window.SAR_GIS.clearRoute()" class="px-3 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer" title="Bersihkan seluruh jalur">
              Bersihkan
            </button>
            <button type="button" id="btnBottomUndoRoute" onclick="window.SAR_GIS && window.SAR_GIS.undoRoutePoint()" class="px-3 py-2 text-xs font-bold rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer flex items-center gap-1" title="Undo titik rute terakhir">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7v6h6M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
              <span>Undo</span>
            </button>
            <button type="button" id="btnSaveMeasureRoute" onclick="window.SAR_GIS && window.SAR_GIS.saveRoute()" class="flex-1 py-2 text-xs font-black rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-md shadow-emerald-600/20 flex items-center justify-center gap-1.5 cursor-pointer">
              <span>💾 Simpan Rute Ini</span>
            </button>
          </div>
        </div>

        <!-- Saved Routes List -->
        <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800">
          <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rute Terukur Tersimpan</div>
          <div id="savedRoutesListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

    </div>

    <!-- MAP CANVAS (LEAFLET INTERACTIVE FULL VIEWPORT) -->
    <div class="flex-1 h-full relative" id="gisMapWrapper" style="height: 100%; flex: 1;">
      <div id="gisLeafletMap" class="w-full h-full z-0" style="width: 100%; height: 100%;"></div>

      <!-- Floating Offline / Blank Spot Status Badge -->
      <div id="gisOfflineIndicator" class="absolute bottom-4 left-4 z-[400] hidden px-3.5 py-2 rounded-xl bg-amber-500/95 text-slate-950 font-bold text-xs shadow-2xl backdrop-blur-md flex items-center gap-2 border border-amber-600/30 transition-all duration-300">
        <span class="relative flex h-2.5 w-2.5">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span>
        </span>
        <span>Mode Luar Jaringan (Blank Spot) — Plotting Taktis Tetap Berfungsi di Memori Lokal</span>
      </div>

      <!-- Floating Button: Buka Tools Plotting (when sidebar is closed) -->
      <button type="button" id="btnFloatingOpenSidebar" class="absolute top-4 left-4 z-[400] hidden px-3.5 py-2 rounded-xl bg-white/95 dark:bg-slate-900/90 hover:bg-white dark:hover:bg-slate-900 text-slate-800 dark:text-white text-xs font-bold border border-slate-200 dark:border-slate-700 shadow-2xl flex items-center gap-2 backdrop-blur transition cursor-pointer">
        <svg class="w-4 h-4 text-orange-500 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><path d="M9 3v18M11 9l3 3-3 3"/></svg>
        <span>Buka Tools Plotting</span>
      </button>

      <!-- Floating Search Pin Banner (when a search result is clicked) -->
      <!-- Floating Google Maps Place Card (when a search result is clicked) -->
      <div id="floatingSearchBanner" class="absolute top-4 left-1/2 -translate-x-1/2 z-[400] hidden p-4 rounded-2xl bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-white shadow-2xl backdrop-blur-md w-[92vw] max-w-lg transition-all animate-in fade-in slide-in-from-top-4 duration-200">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 min-w-0 flex-1">
            <div class="w-10 h-10 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0 font-bold border border-orange-500/30 text-lg shadow-xs">
              <span id="searchBannerIcon">📍</span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <h3 id="searchBannerTitle" class="text-sm font-black truncate text-slate-900 dark:text-white">-</h3>
                <span id="searchBannerBadge" class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30 shrink-0">MAPS</span>
              </div>
              <p id="searchBannerAddress" class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5 font-medium">-</p>
              <div class="flex items-center gap-3 mt-1.5 text-[11px] font-mono text-slate-600 dark:text-slate-300">
                <span id="searchBannerCoords" class="text-orange-600 dark:text-orange-400 font-bold">-</span>
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <span id="searchBannerDecimal" class="text-slate-500 dark:text-slate-400">-</span>
              </div>
            </div>
          </div>
          <button type="button" id="btnCloseSearchBanner" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer shrink-0" title="Tutup">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Google Maps Style Quick Action Buttons -->
        <div class="flex items-center gap-2 mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar">
          <button type="button" id="btnConvertSearchToPoint" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>
            <span>Plot Titik SAR</span>
          </button>
          
          <button type="button" id="btnSetSearchAsLkp" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            <span>Titik Kejadian LKP</span>
          </button>

          <button type="button" id="btnCopySearchCoords" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
            <span>Salin Koordinat</span>
          </button>

          <button type="button" id="btnMeasureFromSearch" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/></svg>
            <span>Ukur Jarak</span>
          </button>
        </div>
      </div>

      <!-- ================================================================ -->
      <!-- OVERLAY CAPTURE PETA CETAK 16:9 & FLOATING CONTROL PANEL         -->
      <!-- ================================================================ -->
      <div id="captureFrameOverlay" class="absolute inset-0 z-[410] hidden pointer-events-none select-none overflow-hidden">
        <!-- 16:9 Framing Box (Dynamically sized & centered via JS to fit map container) -->
        <div id="captureFrameBox" class="absolute border-2 border-emerald-400 rounded-sm" style="box-shadow: 0 0 0 9999px rgba(2, 6, 23, 0.58);">
          <!-- Corner Brackets -->
          <div class="absolute -top-0.5 -left-0.5 w-5 h-5 border-t-4 border-l-4 border-emerald-400"></div>
          <div class="absolute -top-0.5 -right-0.5 w-5 h-5 border-t-4 border-r-4 border-emerald-400"></div>
          <div class="absolute -bottom-0.5 -left-0.5 w-5 h-5 border-b-4 border-l-4 border-emerald-400"></div>
          <div class="absolute -bottom-0.5 -right-0.5 w-5 h-5 border-b-4 border-r-4 border-emerald-400"></div>

          <!-- Top Frame Header Badge -->
          <div class="absolute -top-7 left-0 flex items-center gap-2 bg-slate-950/90 text-emerald-400 border border-emerald-500/40 px-2.5 py-0.5 rounded-t-lg text-[10px] font-mono font-bold tracking-wider shadow-lg whitespace-nowrap">
            <span>📸 BINGKAI CAPTURE DOKUMEN CETAK (RASIO 16:9 &bull; 1280&times;720 PX)</span>
            <span class="text-slate-400 font-normal hidden sm:inline">| Geser &amp; Zoom Peta untuk Menyesuaikan Area</span>
          </div>

          <!-- Rule of Thirds Subtle Grid & Center Crosshair -->
          <div class="absolute inset-0 grid grid-cols-3 grid-rows-3 opacity-25">
            <div class="border-r border-b border-dashed border-emerald-300"></div>
            <div class="border-r border-b border-dashed border-emerald-300"></div>
            <div class="border-b border-dashed border-emerald-300"></div>
            <div class="border-r border-b border-dashed border-emerald-300"></div>
            <div class="border-r border-b border-dashed border-emerald-300 flex items-center justify-center">
              <div class="w-4 h-4 border border-emerald-400 rounded-full flex items-center justify-center">
                <div class="w-1 h-1 bg-emerald-400 rounded-full"></div>
              </div>
            </div>
            <div class="border-b border-dashed border-emerald-300"></div>
            <div class="border-r border-dashed border-emerald-300"></div>
            <div class="border-r border-dashed border-emerald-300"></div>
            <div></div>
          </div>

          <!-- Bottom Frame Bounds Readout -->
          <div id="captureFrameBoundsBadge" class="absolute -bottom-6 right-0 bg-slate-950/90 text-slate-300 border border-slate-700 px-2 py-0.5 rounded-b-lg text-[9px] font-mono shadow-lg whitespace-nowrap">
            BBox: -
          </div>
        </div>
      </div>

      <!-- Floating Capture Control Panel -->
      <div id="captureControlPanel" class="absolute top-3 right-3 z-[450] hidden w-[350px] max-w-[92vw] bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-emerald-500/40 rounded-2xl shadow-2xl text-slate-800 dark:text-slate-100 overflow-hidden">
        <!-- Header -->
        <div class="px-3.5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white flex items-center justify-between">
          <div class="flex items-center gap-2">
            <span class="text-sm">📸</span>
            <div>
              <div class="text-xs font-black uppercase tracking-wider">Capture Peta Dokumen (16:9)</div>
              <div class="text-[9px] text-emerald-100 font-medium">Otomatis mendeteksi komponen di dalam kotak 16:9</div>
            </div>
          </div>
          <button type="button" id="btnCloseCaptureMode" class="p-1 rounded-lg bg-white/10 hover:bg-white/20 text-white transition cursor-pointer" title="Tutup Mode Capture">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Body -->
        <div class="p-3.5 space-y-2.5 text-xs max-h-[78vh] overflow-y-auto gis-scrollbar">
          <!-- 1. Pilih Operasi SAR -->
          <div>
            <label for="captureSelectOperasi" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
              1. Operasi SAR Target
            </label>
            <select id="captureSelectOperasi" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-800 dark:text-slate-100 focus:outline-none focus:border-emerald-500">
              <option value="">-- Pilih Operasi SAR --</option>
              <?php foreach ($activeOperasiList as $ops): ?>
                <option value="<?= (int)$ops['id_operasi'] ?>" <?= ($selectedOperasiId === (int)$ops['id_operasi']) ? 'selected' : '' ?>>
                  Ops #<?= (int)$ops['id_operasi'] ?> - <?= e(mb_strimwidth($ops['nama_operasi'], 0, 38, '...')) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- 2. Pilih Jenis Dokumen Cetak -->
          <div class="grid grid-cols-1 gap-2">
            <div>
              <label for="captureSelectJenisDokumen" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                2. Sasaran Dokumen Cetak Resmi
              </label>
              <select id="captureSelectJenisDokumen" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs font-bold text-emerald-700 dark:text-emerald-400 focus:outline-none focus:border-emerald-500">
                <option value="LAPORAN_AWAL">📄 Laporan Awal Operasi SAR (1x Capture)</option>
                <option value="RENOPS">🗺️ Rencana Operasi / RENOPS (Per Hari Operasi)</option>
                <option value="PENUTUPAN">📑 Laporan Perkembangan &amp; Penutupan (1x Capture)</option>
              </select>
            </div>

            <!-- Sub-selector khusus untuk Hari RENOPS -->
            <div id="captureReferensiWrapper" class="hidden">
              <label for="captureSelectReferensi" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                Pilih Hari Rencana Operasi (RENOPS) <span class="text-red-500">*</span>
              </label>
              <select id="captureSelectReferensi" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-emerald-500">
                <option value="">-- Memuat Daftar Hari RENOPS --</option>
              </select>
            </div>
          </div>

          <!-- 3. Live Deteksi Komponen di Dalam Bingkai 16:9 -->
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                <span>🎯 Komponen Terdeteksi di Area 16:9</span>
              </span>
              <button type="button" id="btnFitCaptureComponents" class="text-[10px] font-bold text-orange-600 dark:text-orange-400 hover:underline cursor-pointer" title="Paskan semua komponen operasi ke dalam bingkai 16:9">
                Paskan Peta
              </button>
            </div>

            <!-- 4 Counters Grid -->
            <div class="grid grid-cols-4 gap-1.5 text-center font-mono">
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountPoints" class="text-xs font-black text-purple-600 dark:text-purple-400">0</div>
                <div class="text-[9px] text-slate-500 font-sans font-bold">Titik</div>
              </div>
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountPolygons" class="text-xs font-black text-blue-600 dark:text-blue-400">0</div>
                <div class="text-[9px] text-slate-500 font-sans font-bold">Area</div>
              </div>
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountPatterns" class="text-xs font-black text-orange-600 dark:text-orange-400">0</div>
                <div class="text-[9px] text-slate-500 font-sans font-bold">Pola SAR</div>
              </div>
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountRoutes" class="text-xs font-black text-emerald-600 dark:text-emerald-400">0</div>
                <div class="text-[9px] text-slate-500 font-sans font-bold">Jalur</div>
              </div>
            </div>

            <!-- Daftar Ringkas Komponen -->
            <div id="captureDetectedList" class="max-h-28 overflow-y-auto gis-scrollbar space-y-1 text-[10px] pr-1">
              <div class="text-slate-400 italic text-center py-2">Memindai komponen di dalam bingkai 16:9...</div>
            </div>
          </div>

          <!-- 4. Deskripsi / Keterangan Peta (Opsional) -->
          <div>
            <label for="captureInputKeterangan" class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
              Judul / Keterangan Peta pada Cetakan (Opsional)
            </label>
            <input type="text" id="captureInputKeterangan" placeholder="Misal: Peta LKP, Search Area & Lokasi Penemuan Korban" class="w-full px-2.5 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-emerald-500">
          </div>

          <!-- Status Snapshot Tersimpan -->
          <div id="captureSavedStatusBanner" class="hidden p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-[10px] text-emerald-700 dark:text-emerald-300 space-y-1">
            <!-- Diisi via JS -->
          </div>

          <!-- Action Buttons -->
          <div class="pt-1 flex items-center gap-2">
            <button type="button" id="btnExecuteCaptureSave" class="flex-1 py-2.5 px-3 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-black uppercase tracking-wider shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2 cursor-pointer">
              <span>📸 Capture &amp; Simpan ke Dokumen</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom Crosshair Coordinate HUD Bar -->
      <div class="absolute bottom-3 left-3 z-[400] bg-white/90 dark:bg-slate-950/90 text-slate-800 dark:text-white backdrop-blur px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 text-[11px] font-mono shadow-2xl flex items-center gap-3">
        <div class="flex items-center gap-1.5">
          <span class="text-orange-600 dark:text-orange-400 font-bold">Lat/Lng:</span>
          <span id="hudCoordsDecimal">-4.037664, 122.486662</span>
        </div>
        <span class="text-slate-300 dark:text-slate-700">|</span>
        <div class="flex items-center gap-1.5">
          <span class="text-sky-600 dark:text-sky-400 font-bold">DMS:</span>
          <span id="hudCoordsDms">04° 02' 16" S - 122° 29' 12" E</span>
        </div>
        <span class="text-slate-300 dark:text-slate-700">|</span>
        <div class="flex items-center gap-1.5">
          <span class="text-slate-500 dark:text-slate-400 font-bold">Zoom:</span>
          <span id="hudZoomLevel" class="text-amber-500 dark:text-amber-400">11</span>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ========================================== -->
<!-- 3. MASTER CLIENT-SIDE GIS APPLICATION JS   -->
<!-- ========================================== -->
<script>
window.SAR_INITIAL_GIS_DATA = {
  filterMode: <?= jsonSafe($filterMode) ?>,
  includeBase: <?= jsonSafe($includeBase) ?>,
  currentOperasiId: <?= jsonSafe($selectedOperasiId) ?>,
  currentOperasi: <?= jsonSafe($currentOperasi) ?>,
  captureDocParam: <?= jsonSafe($captureDocParam) ?>,
  captureRefParam: <?= jsonSafe($captureRefParam) ?>,
  csrfToken: <?= jsonSafe(csrfToken()) ?>,
  points: <?= jsonSafe($savedPoints) ?>,
  polygons: <?= jsonSafe($savedPolygons) ?>,
  patterns: <?= jsonSafe($savedPatterns) ?>,
  routes: <?= jsonSafe($savedRoutes) ?>,
  sarUnits: <?= jsonSafe($sarUnits) ?>,
  operasiList: <?= jsonSafe($activeOperasiList) ?>,
  kansarKendari: { lat: -4.037664, lng: 122.486662, label: 'Kantor SAR Kendari' }
};
</script>

<script src="<?= BASE_URL ?>/assets/js/gis/geodesy.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/markers.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/patterns.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/export.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/layers.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/ui_search.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/ui_points.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/ui_polygons.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/ui_patterns.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/ui_routes.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis_master.js?v=<?= APP_VERSION ?>.<?= file_exists(ROOT_DIR . '/assets/js/gis_master.js') ? filemtime(ROOT_DIR . '/assets/js/gis_master.js') : time() ?>"></script>
