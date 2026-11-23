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
$isAdmin = AuthHelper::isAdmin();

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
$rawFromRenops = $_GET['from_renops'] ?? '';
$fromRenopsParam = ($rawFromRenops !== '' && (int)$rawFromRenops > 0) ? (int)$rawFromRenops : null;
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
<script src="<?= assetUrl('assets/vendor/jszip/jszip.min.js') ?>"></script>

<style>
  /* Tactical GIS Custom Styling & Animations */
  .sar-radar-pulse {
    animation: sar-radar 2s cubic-bezier(0, 0, 0.2, 1) infinite;
  }
  @keyframes sar-radar {
    0% { transform: scale(0.9); opacity: 0.9; }
    70%, 100% { transform: scale(2.4); opacity: 0; }
  }

  /* Match exact top navbar height (68px) across all QR-SAR pages */
  #gisUnifiedHeader {
    min-block-size: 68px;
    height: 68px;
    padding-inline: 0.875rem;
  }
  @media (min-width: 640px) {
    #gisUnifiedHeader {
      padding-inline: 1.5rem;
    }
  }
  #gisUnifiedHeader .gis-hdr-icon-btn {
    min-inline-size: 2.25rem;
    min-block-size: 2.25rem;
  }
  @media (pointer: coarse) {
    #gisUnifiedHeader .gis-hdr-icon-btn {
      min-inline-size: 2.75rem;
      min-block-size: 2.75rem;
    }
  }

  .leaflet-popup-content-wrapper {
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-radius: 0.875rem;
    box-shadow: 0 12px 28px -6px rgba(15, 23, 42, 0.16);
    padding: 0;
  }
  .dark .leaflet-popup-content-wrapper,
  [data-theme="dark"] .leaflet-popup-content-wrapper {
    background: #0f172a;
    color: #f8fafc;
    border: 1px solid #334155;
    box-shadow: 0 14px 32px -6px rgba(2, 6, 23, 0.55);
  }
  .leaflet-popup-content {
    margin: 12px 14px;
    line-height: 1.45;
  }
  .leaflet-popup-tip {
    background: #ffffff;
  }
  .dark .leaflet-popup-tip,
  [data-theme="dark"] .leaflet-popup-tip {
    background: #0f172a;
  }

  .distance-label-tooltip {
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid #ea580c;
    color: #0f172a;
    font-weight: 700;
    font-size: 10px;
    font-variant-numeric: tabular-nums;
    padding: 2px 7px;
    border-radius: 6px;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.14);
  }
  .dark .distance-label-tooltip,
  [data-theme="dark"] .distance-label-tooltip {
    background: rgba(15, 23, 42, 0.94);
    color: #f8fafc;
    box-shadow: 0 2px 8px rgba(2, 6, 23, 0.45);
  }

  /* Accessible Standardized Scrollbar (WCAG >= 3:1 thumb contrast + WebKit fallback) */
  .gis-scrollbar {
    --gis-sb-thumb: #64748b;
    --gis-sb-track: transparent;
    scrollbar-width: thin;
    scrollbar-color: var(--gis-sb-thumb) var(--gis-sb-track);
    scroll-padding-bottom: 1.5rem;
  }
  .dark .gis-scrollbar,
  [data-theme="dark"] .gis-scrollbar {
    --gis-sb-thumb: #94a3b8;
  }
  @supports not (scrollbar-color: auto) {
    .gis-scrollbar {
      scrollbar-width: auto;
    }
    .gis-scrollbar::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    .gis-scrollbar::-webkit-scrollbar-track {
      background: var(--gis-sb-track);
    }
    .gis-scrollbar::-webkit-scrollbar-thumb {
      background: var(--gis-sb-thumb);
      border-radius: 9999px;
    }
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

  /* Compact Spacious GIS Sidebar Panel */
  .gis-sidebar-panel {
    --gis-sidebar-w: 360px;
    width: var(--gis-sidebar-w);
    max-width: 100%;
    transition: margin-left 0.25s cubic-bezier(0.22, 1, 0.36, 1), transform 0.25s cubic-bezier(0.22, 1, 0.36, 1);
  }
  .gis-sidebar-panel.gis-sidebar-collapsed {
    margin-left: calc(var(--gis-sidebar-w) * -1);
  }
  @media (max-width: 767px) {
    .gis-sidebar-panel {
      position: absolute;
      top: 0;
      bottom: 0;
      left: 0;
      z-index: 420;
      width: min(360px, 88vw);
      margin-left: 0;
      transform: translateX(0);
    }
    .gis-sidebar-panel.gis-sidebar-collapsed {
      margin-left: 0;
      transform: translateX(-100%);
      pointer-events: none;
    }
  }

  /* Dark Mode Adaptive Leaflet Zoom & Attribution Controls */
  .dark .leaflet-bar,
  [data-theme="dark"] .leaflet-bar {
    border-color: #334155;
    box-shadow: 0 6px 20px rgba(2, 6, 23, 0.45);
  }
  .dark .leaflet-bar a,
  [data-theme="dark"] .leaflet-bar a {
    background-color: rgba(15, 23, 42, 0.94);
    color: #e2e8f0;
    border-bottom-color: #334155;
  }
  .dark .leaflet-bar a:hover,
  [data-theme="dark"] .leaflet-bar a:hover {
    background-color: #1e293b;
    color: #ffffff;
  }
  .dark .leaflet-control-attribution,
  [data-theme="dark"] .leaflet-control-attribution {
    background: rgba(15, 23, 42, 0.85) !important;
    color: #94a3b8 !important;
  }
  .dark .leaflet-control-attribution a,
  [data-theme="dark"] .leaflet-control-attribution a {
    color: #fb923c !important;
  }

  /* Stable Sidebar Tab Polish (persists across JS className toggles) */
  #gisSidebarPanel .gis-tab-nav-btn {
    cursor: pointer;
    font-variant-numeric: tabular-nums;
  }
  #gisSidebarPanel .gis-tab-nav-btn.border-orange-500 {
    background-color: rgba(249, 115, 22, 0.06);
  }
  .dark #gisSidebarPanel .gis-tab-nav-btn.border-orange-500,
  [data-theme="dark"] #gisSidebarPanel .gis-tab-nav-btn.border-orange-500 {
    background-color: rgba(249, 115, 22, 0.1);
  }

  /* Floating Circular Map Controls (Top-Right Vertical Toolbar) */
  .gis-fab-btn {
    min-inline-size: 2.625rem;
    min-block-size: 2.625rem;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(203, 213, 225, 0.9);
    background: rgba(255, 255, 255, 0.95);
    color: #334155;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.14);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    cursor: pointer;
    position: relative;
  }
  .dark .gis-fab-btn,
  [data-theme="dark"] .gis-fab-btn {
    border-color: rgba(71, 85, 105, 0.85);
    background: rgba(15, 23, 42, 0.92);
    color: #e2e8f0;
    box-shadow: 0 8px 24px rgba(2, 6, 23, 0.45);
  }
  .gis-fab-btn[aria-expanded="true"] {
    background: #ea580c;
    border-color: #f97316;
    color: #ffffff;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.25), 0 8px 22px rgba(234, 88, 12, 0.35);
  }
  .gis-fab-btn:focus-visible {
    outline: 2px solid #f97316;
    outline-offset: 2px;
  }
  @media (hover: hover) {
    .gis-fab-btn:not([aria-expanded="true"]):hover {
      background: #ffffff;
      border-color: #f97316;
      color: #ea580c;
    }
    .dark .gis-fab-btn:not([aria-expanded="true"]):hover,
    [data-theme="dark"] .gis-fab-btn:not([aria-expanded="true"]):hover {
      background: rgba(30, 41, 59, 0.96);
      border-color: #fb923c;
      color: #fb923c;
    }
  }
  @media (pointer: coarse) {
    .gis-fab-btn {
      min-inline-size: 2.75rem;
      min-block-size: 2.75rem;
    }
  }
  @media (prefers-reduced-motion: no-preference) {
    .gis-fab-btn {
      transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease, transform 150ms ease, box-shadow 150ms ease;
    }
    .gis-fab-btn:active {
      transform: scale(0.94);
    }
  }
  @media (forced-colors: active) {
    .gis-fab-btn {
      border: 1px solid ButtonText;
      color: ButtonText;
    }
    .gis-fab-btn[aria-expanded="true"] {
      border-color: Highlight;
      outline: 2px solid Highlight;
    }
  }
  .gis-fab-popover {
    position: absolute;
    right: calc(100% + 0.5rem);
    top: 0;
  }
</style>

<div id="gisRootContainer" class="w-full h-full flex flex-col overflow-hidden bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 select-none" style="height: 100%; flex: 1;">
  
  <!-- ========================================== -->
  <!-- 1. UNIFIED HEADER (68px STANDARD NAVBAR)   -->
  <!-- ========================================== -->
  <header id="gisUnifiedHeader" class="print:hidden navbar top-navbar no-print sticky top-0 z-30 shrink-0 backdrop-blur-md border-b transition-colors duration-200 bg-white/95 dark:bg-slate-900/90 border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 shadow-xs flex items-center justify-between gap-2 sm:gap-3">
    
    <!-- Left Section: Mobile Nav, Sidebar Tools Toggle, Unified Brand & Misi Ops Selector -->
    <div class="flex items-center gap-2 sm:gap-2.5 min-w-0">
      <button
        type="button"
        id="mobile-menu-btn"
        class="gis-hdr-icon-btn lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-500 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer shrink-0"
        title="Buka Navigasi Sistem"
        aria-label="Buka Navigasi Sistem"
      >
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
      </button>

      <button type="button" id="btnToggleGisSidebar" class="gis-hdr-icon-btn btn-tactile inline-flex items-center justify-center w-9 h-9 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 dark:bg-slate-800/90 dark:hover:bg-slate-700/80 dark:text-slate-200 transition-colors border border-slate-200 dark:border-slate-700/80 shadow-xs cursor-pointer shrink-0" title="Sembunyikan / Buka Panel Tools Plotting" aria-label="Sembunyikan / Buka Panel Tools Plotting">
        <svg id="iconSidebarClose" class="w-4 h-4 text-orange-600 dark:text-orange-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
          <path d="M9 3v18M14 9l-3 3 3 3"/>
        </svg>
        <svg id="iconSidebarOpen" class="w-4 h-4 text-orange-600 dark:text-orange-400 hidden" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
          <path d="M9 3v18M11 9l3 3-3 3"/>
        </svg>
        <span id="labelSidebarToggle" class="sr-only">Sembunyikan Tools</span>
      </button>

      <div class="hidden sm:block w-px h-7 bg-slate-200 dark:bg-slate-800 shrink-0" aria-hidden="true"></div>

      <div class="flex items-center gap-2.5 min-w-0">
        <img src="<?= e(getAppLogoUrl()) ?>" alt="Logo Basarnas" class="w-7 h-7 sm:w-8 sm:h-8 object-contain shrink-0" onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'">
        <div class="min-w-0">
          <h1 class="text-sm sm:text-base font-bold tracking-tight text-slate-900 dark:text-slate-100 leading-tight truncate">
            GIS RADAR OPERASI SAR KENDARI
          </h1>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium tracking-tight truncate hidden xl:block leading-tight mt-0.5">
            KPP KELAS A KENDARI &bull; PLOTTING TAKTIS MARITIM
          </p>
        </div>
      </div>

      <!-- SAR Mission Operasi Selector Dropdown (3 Pilihan Plotting Teroptimasi) -->
      <div class="hidden sm:flex items-center gap-2 pl-3 ml-0.5 border-l border-slate-200 dark:border-slate-800 shrink-0">
        <select 
          id="selectGisOperasi" 
          class="h-9 px-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-50/90 dark:bg-slate-800/80 text-xs font-semibold text-slate-800 dark:text-slate-100 max-w-[195px] xl:max-w-[235px] truncate focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/20 transition-colors cursor-pointer"
          title="Pilih mode tampilan komponen plotting taktis"
          aria-label="Pilih Misi Operasi SAR"
        >
          <option value="basemap" <?= ($filterMode === 'basemap' || $filterMode === 'dasar') ? 'selected' : '' ?>>
            1. Base Map
          </option>
          <option value="all" <?= $filterMode === 'all' ? 'selected' : '' ?>>
            2. Semua Komponen
          </option>
          <optgroup label="3. Operasi Aktif (Komponen Khusus ID Operasi)">
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
        <label id="wrapperIncludeBase" class="items-center gap-1.5 h-9 px-2.5 rounded-xl border border-orange-500/25 bg-orange-500/10 text-xs font-semibold text-orange-700 dark:text-orange-400 select-none cursor-pointer <?= $filterMode === 'operasi' ? 'flex' : 'hidden' ?>" title="Muat juga komponen Base Map pada peta operasi SAR aktif ini">
          <input type="checkbox" id="checkIncludeBase" class="rounded border-slate-300 dark:border-slate-600 text-orange-500 focus:ring-orange-500 cursor-pointer" <?= $includeBase ? 'checked' : '' ?>>
          <span class="whitespace-nowrap">+ Base</span>
        </label>

        <button type="button" id="btnResetGisOperasi" class="btn-tactile inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer <?= ($filterMode === 'operasi' || $filterMode === 'basemap' || $filterMode === 'dasar') ? '' : 'hidden' ?>" title="Reset ke Semua Komponen" aria-label="Reset ke Semua Komponen">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>

        <?php if ($filterMode === 'operasi' && $selectedOperasiId > 0): ?>
          <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $selectedOperasiId ?>" class="btn-tactile inline-flex items-center gap-1.5 h-9 px-3 rounded-xl bg-orange-500/10 hover:bg-orange-500/20 text-orange-600 dark:text-orange-400 text-xs font-semibold border border-orange-500/30 transition-colors shadow-xs whitespace-nowrap" title="Kembali ke Detail Operasi SAR">
            <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            <span class="hidden lg:inline">Ke Operasi</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Section: Spatial Actions, Fullscreen & Global Theme Toggle -->
    <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
      <!-- Capture Peta Cetak 16:9 Button -->
      <button type="button" id="btnToggleCaptureMode" class="gis-hdr-icon-btn btn-tactile shrink-0 whitespace-nowrap inline-flex items-center justify-center gap-1.5 h-9 px-2.5 sm:px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition-colors shadow-xs cursor-pointer" title="Capture / Screenshot Area Peta 16:9 untuk Dokumen Cetak Resmi (RENOPS)" aria-label="Capture Peta Cetak 16:9">
        <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span class="hidden md:inline">Capture</span>
      </button>

      <!-- Ekspor Data Spasial Button (Opens Scope-Aware Export Modal) -->
      <div class="relative shrink-0" id="exportMenuWrapper">
        <button type="button" id="btnToggleExportMenu" class="gis-hdr-icon-btn btn-tactile shrink-0 whitespace-nowrap inline-flex items-center justify-center gap-1.5 h-9 px-2.5 sm:px-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800/90 hover:border-orange-500/50 hover:bg-orange-50/70 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors shadow-xs cursor-pointer" title="Ekspor Data Spasial (KMZ, KML, GeoJSON)" aria-label="Ekspor Data Spasial">
          <svg class="w-4 h-4 shrink-0 text-orange-600 dark:text-orange-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
          <span class="hidden md:inline">Ekspor</span>
        </button>
      </div>

      <!-- Impor KML/KMZ Button (Opens Interactive Import Modal) -->
      <button type="button" id="btnOpenImportKmlModal" class="gis-hdr-icon-btn btn-tactile shrink-0 whitespace-nowrap inline-flex items-center justify-center gap-1.5 h-9 px-2.5 sm:px-3 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800/90 hover:border-sky-500/50 hover:bg-sky-50/70 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 text-xs font-semibold transition-colors shadow-xs cursor-pointer" title="Impor Berkas KML / KMZ" aria-label="Impor Berkas KML atau KMZ">
        <svg class="w-4 h-4 shrink-0 text-sky-600 dark:text-sky-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
        <span class="hidden md:inline">Impor</span>
      </button>

      <div class="w-px h-6 bg-slate-200 dark:bg-slate-800 mx-0.5 hidden sm:block" aria-hidden="true"></div>

      <!-- Fullscreen Toggle -->
      <button type="button" id="btnToggleFullscreen" class="gis-hdr-icon-btn btn-tactile hidden sm:inline-flex shrink-0 items-center justify-center w-9 h-9 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 hover:text-slate-900 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 dark:text-slate-300 dark:hover:text-white dark:border-slate-700/80 transition-colors shadow-xs cursor-pointer" title="Layar Penuh" aria-label="Layar Penuh">
        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/></svg>
      </button>

      <!-- Global Theme Toggle Button (Unified from navbar.php) -->
      <button
        type="button"
        id="theme-toggle-btn"
        class="gis-hdr-icon-btn shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer shadow-xs"
        title="Ganti Mode Gelap / Terang"
        aria-label="Ganti Mode Gelap / Terang"
      >
        <span id="theme-icon" class="flex items-center justify-center">
          <svg class="w-4 h-4 hidden dark:block text-amber-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="w-4 h-4 block dark:hidden text-slate-700" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </span>
      </button>
    </div>
  </header>

  <!-- ========================================== -->
  <!-- 2. MAIN WORKSPACE CONTAINER                -->
  <!-- ========================================== -->
  <div class="flex-1 min-h-0 flex overflow-hidden relative">

    <!-- LEFT TOOLS PLOTTING PANEL (COLLAPSIBLE & COMPACT) -->
    <div id="gisSidebarPanel" class="gis-sidebar-panel bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-r border-slate-200 dark:border-slate-800 flex flex-col z-10 shadow-lg relative select-none shrink-0">
      
      <!-- Dynamic Operasi / Mode Banner -->
      <div id="gisSidebarOperasiBanner">
        <?php if ($filterMode === 'basemap' || $filterMode === 'dasar'): ?>
          <div class="px-3.5 py-2 bg-sky-500/10 border-b border-sky-500/20 flex items-center justify-between text-[11px]">
            <div class="flex items-center gap-2 min-w-0">
              <span class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-semibold text-sky-700 dark:text-sky-400">1. Base Map</span>
                <span class="text-slate-500 dark:text-slate-400 ml-1">(Tanpa ID Ops)</span>
              </div>
            </div>
            <button type="button" class="btn-switch-mode-all text-[11px] font-semibold text-slate-500 hover:text-orange-600 dark:hover:text-orange-400 underline shrink-0 ml-2 cursor-pointer">Semua</button>
          </div>
        <?php elseif ($filterMode === 'operasi' && $currentOperasi): ?>
          <div class="px-3.5 py-2 bg-orange-500/10 border-b border-orange-500/20 flex items-center justify-between text-[11px]">
            <div class="flex items-center gap-2 min-w-0">
              <span class="w-2 h-2 rounded-full bg-orange-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-semibold text-orange-600 dark:text-orange-400">3. Ops #<?= $currentOperasi['id_operasi'] ?></span>
                <span class="text-slate-600 dark:text-slate-300 ml-1"><?= e($currentOperasi['nama_operasi']) ?></span>
              </div>
            </div>
            <button type="button" class="btn-switch-mode-all text-[11px] font-semibold text-slate-500 hover:text-red-500 underline shrink-0 ml-2 cursor-pointer" title="Kembali ke semua plotting">Lepas</button>
          </div>
        <?php else: ?>
          <div class="px-3.5 py-2 bg-slate-50 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-[11px]">
            <div class="flex items-center gap-2 min-w-0">
              <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-semibold text-slate-700 dark:text-slate-200">2. Semua Komponen</span>
                <span class="text-slate-500 dark:text-slate-400 ml-1">(Base Map &amp; Ops Aktif)</span>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>

      <!-- 4 Tabs Header (Stable Vertical Rhythm Matching JS Switcher) -->
      <div class="grid grid-cols-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/90 dark:bg-slate-950/60 text-[10.5px] font-semibold text-center shrink-0">
        <button type="button" data-tab="tab-points" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-orange-500 text-orange-600 dark:text-orange-400 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
          <span class="leading-tight">Titik (<span id="pointsBadgeCount"><?= $initialPointsCount ?></span>)</span>
        </button>

        <button type="button" data-tab="tab-polygon" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="2" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="22"/></svg>
          <span class="leading-tight">Search Area (<span id="polygonsBadgeCount"><?= $initialPolygonsCount ?></span>)</span>
        </button>

        <button type="button" data-tab="tab-pattern" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
          <span class="leading-tight">Pola SAR (<span id="patternsBadgeCount"><?= $initialPatternsCount ?></span>)</span>
        </button>

        <button type="button" data-tab="tab-measure" class="gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/></svg>
          <span class="leading-tight">Jarak & ETA (<span id="routesBadgeCount"><?= $initialRoutesCount ?></span>)</span>
        </button>
      </div>

      <!-- TAB 1: TITIK SAR (SAVED POINTS & POINT CREATOR CARD) -->
      <div id="tab-points" class="gis-tab-pane flex-1 overflow-y-auto p-3 space-y-3 gis-scrollbar bg-slate-50/60 dark:bg-slate-900/40">
        <!-- Compact Single-Row Action Bar -->
        <div class="flex items-center gap-1.5">
          <button type="button" id="btnShowPointCreator" class="btn-tactile inline-flex items-center gap-1.5 h-8 px-3 rounded-xl bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold transition-colors shadow-xs cursor-pointer shrink-0" title="Tambah Titik Baru di Peta">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Titik</span>
          </button>

          <button type="button" id="btnToggleDragLock" class="btn-tactile flex-1 h-8 px-2.5 rounded-xl border text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors bg-white dark:bg-slate-800/90 border-slate-200 dark:border-slate-700/80 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 cursor-pointer min-w-0 shadow-xs">
            <svg id="iconLockClosed" class="w-3.5 h-3.5 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            <svg id="iconLockOpen" class="w-3.5 h-3.5 text-amber-500 hidden shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/></svg>
            <span id="labelLockStatus" class="truncate">Posisi Terkunci</span>
          </button>

          <button type="button" id="btnFocusKansarQuick" class="btn-tactile inline-flex items-center justify-center w-8 h-8 rounded-xl bg-white dark:bg-slate-800/90 hover:bg-slate-100 dark:hover:bg-slate-700 border border-slate-200 dark:border-slate-700/80 text-slate-600 dark:text-slate-300 hover:text-orange-600 dark:hover:text-orange-400 transition-colors cursor-pointer shrink-0 shadow-xs" title="Pusatkan Peta ke Kansar Kendari" aria-label="Pusatkan Peta ke Kansar Kendari">
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
          </button>
        </div>

        <!-- Point Creator / Editor Card (PointCreatorCard.tsx) -->
        <div id="pointCreatorCard" class="hidden p-4 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3.5">
          <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-800">
            <span id="pointCreatorTitle" class="text-xs font-bold uppercase tracking-wide text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span>Plot Titik Taktis Baru</span>
            </span>
            <button type="button" id="btnCancelPointCreator" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer" title="Tutup Form" aria-label="Tutup Form Titik">
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Name & Category -->
          <div class="space-y-2.5">
            <div>
              <div class="field">
                <label class="field-label">Nama Titik</label>
                <input type="text" id="pcPointName" placeholder="Contoh: KM Sumber Bahagia" class="field-input w-full">
              </div>
            </div>

            <div>
              <div class="field">
                <label class="field-label">Kategori</label>
                <select id="pcPointCategory" class="field-input w-full font-semibold">
                <option value="Tanpa Kategori">Tanpa Kategori</option>
                <option value="Kantor SAR">Kantor SAR</option>
                <option value="POS SAR">POS SAR</option>
                <option value="Unit Siaga SAR">Unit Siaga SAR</option>
                <option value="Dermaga SAR">Dermaga SAR</option>
                <option value="LKP">LKP</option>
                <option value="Penemuan Korban">Penemuan Korban</option>
                <option value="Lokasi Penemuan Korban">Lokasi Penemuan Korban</option>
                <option value="Posko">Posko</option>
                <option value="Entry Poin">Entry Poin</option>
                <option value="Exit Poin">Exit Poin</option>
              </select>
              </div>
            </div>
          </div>

          <!-- Icon Preset Selector (Diverse presets + Alphabet A-Z + Custom Upload) -->
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400">Ikon Penanda</label>
              <span id="pcSelectedIconBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-orange-500/10 border border-orange-500/30 text-[11px] font-semibold text-orange-600 dark:text-orange-400 shrink-0">
                <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                <span>Pin Lokasi Standar</span>
              </span>
            </div>
            
            <div class="grid grid-cols-3 gap-1 p-1 bg-slate-100 dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 text-[11px] font-semibold text-center">
              <button type="button" id="tabIconPresetSar" class="py-1 rounded-lg bg-orange-500 text-white cursor-pointer transition">Preset Logo</button>
              <button type="button" id="tabIconAlphabet" class="py-1 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer transition">Huruf (A-Z)</button>
              <button type="button" id="tabIconCustom" class="py-1 rounded-lg text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer transition">Logo Kustom</button>
            </div>

            <!-- Panel 1: Preset Logo with Filter Pills & Search -->
            <div id="iconGridSarPanel" class="space-y-1.5">
              <!-- Quick Filter Pills -->
              <div class="flex items-center gap-1 overflow-x-auto pb-1 gis-scrollbar text-[11px] font-semibold">
                <button type="button" data-group="all" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-orange-500 text-white shrink-0 cursor-pointer transition">Semua</button>
                <button type="button" data-group="kejadian" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">Kejadian</button>
                <button type="button" data-group="korban" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">Korban</button>
                <button type="button" data-group="tempat" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">Tempat</button>
                <button type="button" data-group="alut" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">Armada & Alut</button>
                <button type="button" data-group="taktis" class="preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition">Taktis</button>
              </div>

              <!-- Search input for preset logo -->
              <div class="relative flex items-center">
                <span class="absolute left-2.5 text-slate-400 pointer-events-none">
                  <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </span>
                <input type="text" id="pcPresetSearch" placeholder="Cari logo (mis: kapal, helikopter, posko)..." class="control control-sm w-full pl-8 pr-2.5">
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
                <span class="text-[11px] font-semibold text-slate-700 dark:text-slate-300">Upload Logo Baru (Tersimpan Permanen):</span>
                <span class="text-[11px] text-slate-400 font-mono">PNG/JPG/SVG/WebP</span>
              </div>
              
              <!-- File Upload Input (Hidden) & Dropzone Button -->
              <input type="file" id="pcCustomIconFile" accept="image/png,image/jpeg,image/svg+xml,image/webp,image/gif" class="hidden">
              <button type="button" id="btnTriggerUploadIcon" class="w-full py-2 px-3 rounded-xl border border-dashed border-orange-500/60 hover:border-orange-500 bg-orange-500/10 hover:bg-orange-500/20 text-orange-600 dark:text-orange-400 font-semibold text-xs flex items-center justify-center gap-2 transition cursor-pointer">
                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <span>+ Upload File Logo / Ikon Baru</span>
              </button>

              <!-- Uploaded Preview Card -->
              <div id="pcCustomIconPreviewCard" class="hidden p-2 rounded-xl bg-orange-50 dark:bg-orange-950/30 border border-orange-500/40 flex items-center justify-between gap-2 text-left">
                <div class="flex items-center gap-2.5 min-w-0">
                  <img id="pcCustomIconThumb" src="" alt="Custom Icon" class="w-8 h-8 rounded-lg object-contain bg-white dark:bg-slate-900 p-0.5 border border-orange-400/40 shrink-0">
                  <div class="min-w-0 flex-1">
                    <div id="pcCustomIconFileName" class="text-xs font-semibold text-slate-900 dark:text-white truncate">icon.png</div>
                    <div class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                      <span>Logo Terpilih Aktif</span>
                    </div>
                  </div>
                </div>
                <button type="button" id="btnRemoveCustomIcon" class="px-2.5 py-1 text-[11px] font-semibold text-red-600 hover:text-red-700 dark:text-red-400 bg-red-500/10 hover:bg-red-500/20 rounded-lg transition cursor-pointer flex items-center gap-1 shrink-0" title="Lepas logo kustom dari titik ini (titik kembali ke pin standar)">
                  <span>Lepas dari Titik</span>
                </button>
              </div>

              <!-- Daftar Logo Kustom Tersimpan -->
              <div class="pt-1 border-t border-slate-100 dark:border-slate-800 space-y-1.5">
                <div class="flex items-center justify-between text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                  <span>Pustaka Logo Kustom (Klik untuk pilih):</span>
                  <span id="customIconCountBadge" class="text-[11px] text-orange-600 dark:text-orange-400 font-mono">0 Logo</span>
                </div>
                <div id="customIconGalleryContainer" class="grid grid-cols-4 gap-1.5 max-h-40 overflow-y-auto gis-scrollbar p-1 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                  <div class="col-span-4 p-3 text-center text-[11px] text-slate-400">Memuat daftar logo...</div>
                </div>
              </div>
            </div>
          </div>

          <!-- DMS Numeric Editor (Deg/Min/Sec with S/N & E/W) -->
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-600 dark:text-slate-400">
              <span class="uppercase tracking-wider">Koordinat (DMS)</span>
              <span id="pcReadoutDecimal" class="text-orange-600 dark:text-orange-400 font-mono font-bold">-</span>
            </div>

            <!-- Lintang (Latitude) -->
            <div>
              <span class="text-[11px] text-slate-600 dark:text-slate-400 block mb-1 font-semibold">Lintang (S-N)</span>
              <div class="grid grid-cols-4 gap-1 items-center font-mono">
                <input type="number" id="dmsLatDeg" min="0" max="90" placeholder="04" class="control control-sm w-full text-center font-bold">
                <input type="number" id="dmsLatMin" min="0" max="59" placeholder="02" class="control control-sm w-full text-center font-bold">
                <input type="number" id="dmsLatSec" min="0" max="59.9" step="0.1" placeholder="16.0" class="control control-sm w-full text-center font-bold">
                <select id="dmsLatDir" class="control control-sm w-full text-orange-600 dark:text-orange-400 font-bold text-center">
                  <option value="S">S (LS)</option>
                  <option value="N">N (LU)</option>
                </select>
              </div>
            </div>

            <!-- Bujur (Longitude) -->
            <div>
              <span class="text-[11px] text-slate-600 dark:text-slate-400 block mb-1 font-semibold">Bujur (E-W)</span>
              <div class="grid grid-cols-4 gap-1 items-center font-mono">
                <input type="number" id="dmsLngDeg" min="0" max="180" placeholder="122" class="control control-sm w-full text-center font-bold">
                <input type="number" id="dmsLngMin" min="0" max="59" placeholder="29" class="control control-sm w-full text-center font-bold">
                <input type="number" id="dmsLngSec" min="0" max="59.9" step="0.1" placeholder="12.0" class="control control-sm w-full text-center font-bold">
                <select id="dmsLngDir" class="control control-sm w-full text-orange-600 dark:text-orange-400 font-bold text-center">
                  <option value="E">E (BT)</option>
                  <option value="W">W (BB)</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-slate-800">
            <button type="button" id="btnCancelSavePoint" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">Batal</button>
            <button type="button" id="btnSavePointConfirm" class="px-4 py-2 text-xs font-semibold bg-orange-600 hover:bg-orange-500 text-white rounded-xl transition flex items-center gap-1.5 cursor-pointer shadow-xs">
              <span>Simpan Titik SAR</span>
            </button>
          </div>
        </div>

        <!-- Saved Points List Cards Container -->
        <div class="space-y-2.5">
          <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
            <span>Daftar Titik Tersimpan</span>
            <span class="normal-case font-medium text-slate-400 dark:text-slate-500">Klik untuk Fokus</span>
          </div>

          <select id="filterGisPointCategory" aria-label="Filter Kategori Titik SAR" class="w-full h-9 text-xs font-medium rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3 text-slate-700 dark:text-slate-200 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-colors cursor-pointer">
            <option value="">Semua Kategori</option>
          </select>

          <div id="savedPointsListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

      <!-- TAB 2: POLYGON SEARCH AREA (POLYGON MANAGER) -->
      <div id="tab-polygon" class="gis-tab-pane hidden flex-1 overflow-y-auto p-3 space-y-3 gis-scrollbar bg-slate-50/60 dark:bg-slate-900/40">
        <!-- Compact Header & New Search Area Pill -->
        <div class="flex items-center justify-between gap-2">
          <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Sektor Area Tersimpan</span>
          <button type="button" id="btnStartNewPolygon" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold transition-colors shadow-xs cursor-pointer shrink-0" title="Buat Search Area Baru">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Area</span>
          </button>
        </div>

        <!-- Drawing & Editing Mode Panel -->
        <div id="polygonDrawingEditorCard" class="hidden p-4 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3.5">
          <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-800">
            <span id="polyEditorTitle" class="text-xs font-bold uppercase tracking-wide text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span>Rancang Search Area Baru</span>
            </span>
            <button type="button" id="btnCancelDrawingPoly" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer" title="Tutup Form" aria-label="Tutup Form Area">
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- 1. Nama Search Area -->
          <div>
            <div class="field">
              <label class="field-label">Nama Search Area</label>
              <input type="text" id="polyEditorName" value="" placeholder="SRU 1" class="field-input w-full">
            </div>
          </div>

          <!-- 2. Bentuk Search Area (Polygon, Rectangle, Circle) -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Bentuk Search Area</label>
            <div class="grid grid-cols-3 gap-1.5 text-xs font-semibold text-center">
              <button type="button" id="btnShapePolygon" data-shape="polygon" class="poly-shape-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 font-bold transition shadow-xs cursor-pointer">
                <span class="text-[11px] font-bold">Polygon Bebas</span>
              </button>
              <button type="button" id="btnShapeRectangle" data-shape="rectangle" class="poly-shape-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:border-orange-500 font-bold transition cursor-pointer">
                <span class="text-[11px] font-bold">Persegi (Kotak)</span>
              </button>
              <button type="button" id="btnShapeCircle" data-shape="circle" class="poly-shape-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 hover:border-orange-500 font-bold transition cursor-pointer">
                <span class="text-[11px] font-bold">Circle (Radius)</span>
              </button>
            </div>
          </div>

          <!-- Dynamic Parameter Input: Circle Radius -->
          <div id="polyCircleParamsBox" class="hidden p-3 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Radius</label>
                <div class="flex items-center gap-1">
                  <input type="number" id="polyCircleRadiusNm" value="1.5" min="0.01" step="0.1" class="control control-sm w-full font-mono font-bold">
                  <select id="polyCircleRadiusUnit" class="control control-sm text-orange-600 dark:text-orange-400 font-bold cursor-pointer">
                    <option value="NM" selected>NM</option>
                    <option value="KM">Km</option>
                    <option value="M">Meter</option>
                  </select>
                </div>
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Pusat Lingkaran</label>
                <div id="polyCircleCenterReadout" class="text-[11px] font-mono font-bold text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-950 px-2 py-2 rounded-xl border border-slate-200 dark:border-slate-700 truncate">Klik peta</div>
              </div>
            </div>
          </div>

          <!-- Dynamic Parameter Input: Rectangle Dimensions & Orientation Slidebar -->
          <div id="polyRectangleParamsBox" class="hidden p-3 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Ukuran & Orientasi Persegi</span>
              <select id="polyRectDimUnit" class="control control-sm text-orange-600 dark:text-orange-400 font-bold cursor-pointer">
                <option value="NM" selected>NM</option>
                <option value="KM">Km</option>
                <option value="M">Meter</option>
              </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Panjang (P)</label>
                <div class="flex items-center gap-1">
                  <input type="number" id="polyRectWidthNm" value="2.0" min="0.01" step="0.1" class="control control-sm w-full font-mono font-bold">
                  <span id="polyRectWidthUnitLabel" class="text-[11px] font-bold text-slate-400">NM</span>
                </div>
              </div>
              <div>
                <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Lebar (L)</label>
                <div class="flex items-center gap-1">
                  <input type="number" id="polyRectHeightNm" value="2.0" min="0.01" step="0.1" class="control control-sm w-full font-mono font-bold">
                  <span id="polyRectHeightUnitLabel" class="text-[11px] font-bold text-slate-400">NM</span>
                </div>
              </div>
            </div>
            <div class="pt-1.5 border-t border-slate-200/70 dark:border-slate-800 space-y-1">
              <div class="flex items-center justify-between text-[11px]">
                <label for="polyRectRotationSlider" class="font-semibold text-slate-600 dark:text-slate-400">Arah Orientasi Persegi</label>
                <span id="polyRectRotationLabel" class="font-mono font-bold text-orange-600 dark:text-orange-400 tabular-nums">0°</span>
              </div>
              <input type="range" id="polyRectRotationSlider" min="0" max="359" step="1" value="0" class="w-full accent-orange-500 cursor-pointer">
            </div>
          </div>

          <!-- 3. Perhitungan (Luas & Keliling) -->
          <div class="grid grid-cols-2 gap-2 text-xs">
            <div class="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">Luas Area</span>
              <span id="polyLiveSqNm" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 font-mono tabular-nums">0.00 NM²</span>
              <span id="polyLiveSqKm" class="text-[11px] text-slate-500 dark:text-slate-400 block font-mono tabular-nums">0.00 km² (0 Ha)</span>
            </div>
            <div class="p-2.5 rounded-xl bg-orange-500/10 border border-orange-500/20">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 block font-medium">Keliling</span>
              <span id="polyLivePerimeterNm" class="text-sm font-bold text-orange-600 dark:text-orange-400 font-mono tabular-nums">0.00 NM</span>
              <span id="polyLivePerimeterKm" class="text-[11px] text-slate-500 dark:text-slate-400 block font-mono tabular-nums">0.00 km</span>
            </div>
          </div>

          <!-- 4. Warna Area & Garis -->
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2.5 text-xs">
            <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Warna Area & Garis</span>

            <!-- Stroke Color Swatches -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-1">
                <span class="text-slate-600 dark:text-slate-400">1. Warna Garis</span>
                <span id="labelStrokeColor" class="font-mono text-orange-600 dark:text-orange-400 font-bold">#EA580C</span>
              </div>
              <div class="flex items-center gap-1.5 flex-wrap" id="polyStrokeColorSwatches">
                <!-- Color dots -->
              </div>
              <div class="flex items-center justify-between text-[11px] pt-1.5 mt-1.5 border-t border-slate-200/60 dark:border-slate-800">
                <span class="text-slate-600 dark:text-slate-400 font-medium">Warna Garis Kustom</span>
                <div class="flex items-center gap-2">
                  <input type="color" id="polyCustomStrokeColorInput" value="#ea580c" class="w-7 h-7 p-0 border border-slate-300 dark:border-slate-700 rounded-lg cursor-pointer bg-transparent">
                  <span id="polyCustomStrokeColorHex" class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">#EA580C</span>
                </div>
              </div>
            </div>

            <!-- Stroke Weight Slider -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-0.5">
                <span class="text-slate-600 dark:text-slate-400">2. Ketebalan Garis</span>
                <span id="labelStrokeWeight" class="font-mono text-slate-900 dark:text-white font-bold tabular-nums">2.5 px</span>
              </div>
              <input type="range" id="polyStrokeWeightSlider" min="1" max="8" step="0.5" value="2.5" class="w-full accent-orange-500">
            </div>

            <!-- Stroke Opacity Slider -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-0.5">
                <span class="text-slate-600 dark:text-slate-400">3. Transparansi Garis</span>
                <span id="labelStrokeOpacity" class="font-mono text-slate-900 dark:text-white font-bold tabular-nums">100%</span>
              </div>
              <input type="range" id="polyStrokeOpacitySlider" min="0.1" max="1" step="0.05" value="1" class="w-full accent-orange-500">
            </div>

            <!-- Fill Color Swatches -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-1">
                <span class="text-slate-600 dark:text-slate-400">4. Warna Isi (Arsiran)</span>
                <span id="labelFillColor" class="font-mono text-orange-600 dark:text-orange-400 font-bold">#EA580C</span>
              </div>
              <div class="flex items-center gap-1.5 flex-wrap" id="polyFillColorSwatches">
                <!-- Color dots -->
              </div>
              <div class="flex items-center justify-between text-[11px] pt-1.5 mt-1.5 border-t border-slate-200/60 dark:border-slate-800">
                <span class="text-slate-600 dark:text-slate-400 font-medium">Warna Isi Kustom</span>
                <div class="flex items-center gap-2">
                  <input type="color" id="polyCustomFillColorInput" value="#ea580c" class="w-7 h-7 p-0 border border-slate-300 dark:border-slate-700 rounded-lg cursor-pointer bg-transparent">
                  <span id="polyCustomFillColorHex" class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">#EA580C</span>
                </div>
              </div>
            </div>

            <!-- Fill Opacity Slider -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-0.5">
                <span class="text-slate-600 dark:text-slate-400">5. Transparansi Isi</span>
                <span id="labelFillOpacity" class="font-mono text-slate-900 dark:text-white font-bold tabular-nums">20%</span>
              </div>
              <input type="range" id="polyFillOpacitySlider" min="0.05" max="0.8" step="0.05" value="0.2" class="w-full accent-orange-500">
            </div>
          </div>

          <!-- Vertices Table (Titik A, B, C...) -->
          <div class="space-y-1.5">
            <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Daftar Titik Sudut</span>
            <div id="polyVerticesListTable" class="space-y-1 max-h-36 overflow-y-auto gis-scrollbar text-xs">
              <!-- Rendered via JS -->
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center justify-between pt-2.5 border-t border-slate-100 dark:border-slate-800">
            <button type="button" id="btnCancelPolyForm" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer">Batal</button>
            <button type="button" id="btnSavePolygonConfirm" class="px-4 py-2 text-xs font-semibold bg-orange-600 hover:bg-orange-500 text-white rounded-xl transition cursor-pointer shadow-xs">
              Simpan Polygon Area
            </button>
          </div>
        </div>

        <!-- Saved Polygons List -->
        <div class="space-y-2">
          <div id="savedPolygonsListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

      <!-- TAB 3: POLA SAR (SEARCH PATTERNS IAMSAR) -->
      <div id="tab-pattern" class="gis-tab-pane hidden flex-1 overflow-y-auto p-3 space-y-3 gis-scrollbar bg-slate-50/60 dark:bg-slate-900/40">
        <!-- Compact Header & New Search Pattern Pill -->
        <div class="flex items-center justify-between gap-2">
          <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pola Lintasan Tersimpan</span>
          <button type="button" id="btnStartNewPattern" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold transition-colors shadow-xs cursor-pointer shrink-0" title="Buat Pola Lintasan SAR Baru">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Pola SAR</span>
          </button>
        </div>

        <!-- Pattern Creation / Editor Card -->
        <div id="patternCreationCard" class="hidden p-4 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3.5">
          <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-800">
            <span class="text-xs font-bold uppercase tracking-wide text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span id="patternFormHeaderTitle">Parameter Pola Lintasan IAMSAR</span>
            </span>
            <button type="button" id="btnCancelPatternFormTop" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer" title="Tutup Form" aria-label="Tutup Form Pola">
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- 1. Nama Pola SAR -->
          <div>
            <div class="field">
              <label class="field-label">Nama Pola SAR</label>
              <input type="text" id="patternFormName" value="" placeholder="Pola Pencarian Area SRU 1 (PS)" class="field-input w-full">
            </div>
          </div>

          <!-- 2. Target Search Area -->
          <div>
            <div class="field">
              <label class="field-label">Target Search Area</label>
              <select id="patternTargetPolygon" class="field-input w-full">
                <!-- Populated with savedPolygons or LKP -->
              </select>
            </div>
          </div>

          <!-- 3. Jenis Pola (Tanpa Keterangan) -->
          <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Jenis Pola</label>
            <div class="grid grid-cols-3 gap-1.5 text-xs font-semibold">
              <button type="button" data-ptype="parallel_track" class="pat-type-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-center transition font-bold shadow-xs cursor-pointer">
                Parallel Track (PS)
              </button>
              <button type="button" data-ptype="creeping_line" class="pat-type-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-center hover:border-orange-500 transition cursor-pointer">
                Creeping Line (CS)
              </button>
              <button type="button" data-ptype="e_sar" class="pat-type-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-center hover:border-orange-500 transition cursor-pointer">
                E-SAR
              </button>
            </div>
          </div>

          <!-- 4. Arah Orientasi (Cukup Slide Bar 0°-359°) -->
          <div id="patternFlipWrapper" class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-1.5">
            <div class="flex items-center justify-between text-[11px]">
              <label for="patternRotationSlider" class="font-semibold text-slate-600 dark:text-slate-400">Arah Orientasi</label>
              <span id="patternOrientationBadge" class="font-mono font-bold text-orange-600 dark:text-orange-400 tabular-nums">0°</span>
            </div>
            <input type="range" id="patternRotationSlider" min="0" max="359" step="1" value="0" class="w-full accent-orange-500 cursor-pointer">
            <button type="button" id="btnPatternFlipH" aria-pressed="false" class="hidden"></button>
            <button type="button" id="btnPatternFlipV" aria-pressed="false" class="hidden"></button>
          </div>

          <!-- E-SAR Sweep Direction Selector (Hidden for legacy test compatibility) -->
          <div id="esarOrientationWrapper" class="hidden">
            <button type="button" id="btnEsarOrientHorizontal" data-orientation="horizontal" class="esar-orient-btn"></button>
            <button type="button" id="btnEsarOrientVertical" data-orientation="vertical" class="esar-orient-btn"></button>
            <div id="esarDirectionControls">
              <div id="esarDirHorizontalGroup">
                <button type="button" id="btnEsarDirWtoE" data-direction="west_to_east" data-dir="west_to_east" class="esar-dir-btn"></button>
                <button type="button" id="btnEsarDirEtoW" data-direction="east_to_west" data-dir="east_to_west" class="esar-dir-btn"></button>
              </div>
              <div id="esarDirVerticalGroup" class="hidden">
                <button type="button" id="btnEsarDirNtoS" data-direction="north_to_south" data-dir="north_to_south" class="esar-dir-btn"></button>
                <button type="button" id="btnEsarDirStoN" data-direction="south_to_north" data-dir="south_to_north" class="esar-dir-btn"></button>
              </div>
            </div>
          </div>

          <!-- 5. Track Spacing & Jumlah Sweep -->
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Track Spacing (S)</label>
              <div class="flex items-center gap-1">
                <input type="number" id="patternTrackSpacing" value="1.0" step="0.01" min="0.01" class="control control-sm w-full font-mono font-bold">
                <select id="patternSpacingUnit" class="control control-sm text-orange-600 dark:text-orange-400 font-bold">
                  <option value="NM" selected>NM</option>
                  <option value="KM">Km</option>
                  <option value="M">Meter</option>
                </select>
              </div>
            </div>
            <div>
              <div class="flex items-center justify-between mb-1">
                <label id="patternLegsLabel" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400">Jumlah Sweep</label>
                <span id="patternAutoBadge" class="text-[10px] font-mono font-bold text-emerald-600 dark:text-emerald-400 bg-emerald-500/10 px-1.5 py-0.5 rounded border border-emerald-500/20">AUTO</span>
              </div>
              <input type="number" id="patternLegsCount" value="6" min="2" max="50" class="control control-sm w-full font-mono font-bold">
            </div>
          </div>

          <!-- 6. Warna & Garis Pola SAR -->
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-800 space-y-2.5">
            <div class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider flex items-center justify-between">
              <span>Warna & Garis Pola</span>
              <span id="labelPatternLineColor" class="font-mono text-sky-600 dark:text-sky-400 font-bold text-[11px]">#0284C7</span>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap" id="patternColorSwatches">
              <!-- Rendered via JS -->
            </div>

            <div class="flex items-center justify-between text-[11px] pt-1.5 border-t border-slate-200/60 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">Warna Kustom</span>
              <div class="flex items-center gap-2">
                <input type="color" id="patternLineColor" value="#0284c7" class="w-7 h-7 p-0 border border-slate-300 dark:border-slate-700 rounded-lg cursor-pointer bg-transparent" title="Pilih warna bebas">
                <span id="patternCustomColorHex" class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">#0284C7</span>
              </div>
            </div>

            <div class="space-y-1">
              <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                <span class="font-semibold">Transparansi Garis</span>
                <span id="patternOpacityValDisplay" class="font-mono text-slate-900 dark:text-white font-bold tabular-nums">90%</span>
              </div>
              <input type="range" id="patternLineOpacity" min="0.1" max="1" step="0.05" value="0.9" class="w-full accent-sky-600 dark:accent-sky-500 cursor-pointer">
            </div>

            <div class="grid grid-cols-2 gap-2 pt-1.5 border-t border-slate-200 dark:border-slate-800 text-[11px]">
              <div>
                <div class="field">
                  <label class="field-label">Ketebalan</label>
                  <select id="patternLineWeight" class="field-input w-full font-semibold">
                    <option value="2">2 px (Tipis)</option>
                    <option value="3" selected>3 px (Standar)</option>
                    <option value="4">4 px (Tebal)</option>
                    <option value="5">5 px (Ekstra Tebal)</option>
                  </select>
                </div>
              </div>
              <div>
                <div class="field">
                  <label class="field-label">Gaya Garis</label>
                  <select id="patternLineStyle" class="field-input w-full font-semibold">
                    <option value="dashed" selected>Putus-putus (Dash)</option>
                    <option value="solid">Garis Utuh (Solid)</option>
                  </select>
                </div>
              </div>
            </div>
          </div>

          <!-- Pattern Status / Alert Box -->
          <div id="patternStatusMsg" class="hidden text-[11px] p-2.5 rounded-xl transition"></div>

          <!-- 7. Card Perhitungan Terpadu (Dimensi, Margin, Panjang Lintasan, Total Sapuan) -->
          <div id="patternResultsCard" class="p-3 bg-slate-50 dark:bg-slate-900/90 rounded-2xl border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
            <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perhitungan Pola SAR</div>
            <div class="grid grid-cols-2 gap-2">
              <div class="p-2 rounded-xl bg-white dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Dimensi Area</span>
                <span id="patternDimInfo" class="text-[11px] font-bold text-orange-600 dark:text-orange-400 font-mono tabular-nums">P: - &bull; L: -</span>
              </div>
              <div class="p-2 rounded-xl bg-white dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Margin Batas (S/2)</span>
                <span id="patternMarginInfo" class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 font-mono tabular-nums">0.50 NM</span>
              </div>
              <div class="p-2 rounded-xl bg-white dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Panjang Lintasan (L)</span>
                <span id="resPatternDistNm" class="text-[11px] font-bold text-sky-600 dark:text-sky-400 font-mono tabular-nums">0.00 NM</span>
              </div>
              <div class="p-2 rounded-xl bg-white dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800">
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Total Sapuan (A = S×L)</span>
                <span id="resPatternAreaSwept" class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 font-mono tabular-nums">0.00 NM²</span>
              </div>
            </div>
            <div id="patternEsarPersonnelInfo" class="hidden flex items-center justify-between text-[11px] pt-1.5 border-t border-slate-200 dark:border-slate-800 font-mono">
              <span class="font-sans text-slate-500 dark:text-slate-400">Tim E-SAR:</span>
              <span id="patternPersonnelCalcReadout" class="text-sky-600 dark:text-sky-400 font-bold">-</span>
            </div>
            <span id="resPatternTypeInfo" class="hidden"></span>
            <span id="resPatternWpCount" class="hidden"></span>
          </div>

          <!-- Save Pattern & Cancel Action Buttons (Always Visible in Form Footer) -->
          <div class="pt-2.5 border-t border-slate-100 dark:border-slate-800 flex items-center gap-2">
            <button type="button" id="btnCancelPatternForm" class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-semibold transition cursor-pointer">
              Batal / Bersihkan
            </button>
            <button type="button" id="btnSavePatternConfirm" class="flex-1 py-2 rounded-xl bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
              <span>Simpan Pola SAR</span>
            </button>
          </div>
        </div>

        <!-- Saved Search Patterns List -->
        <div class="space-y-2">
          <div id="savedPatternsListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

      <!-- TAB 4: JARAK & ETA (MEASURE DISTANCE & SPEED SIMULATOR) -->
      <div id="tab-measure" class="gis-tab-pane hidden flex-1 overflow-y-auto p-3 space-y-3 gis-scrollbar bg-slate-50/60 dark:bg-slate-900/40">
        <!-- Compact Header & New Route Pill -->
        <div class="flex items-center justify-between gap-2">
          <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rute Terukur Tersimpan</span>
          <button type="button" id="btnStartNewRoute" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white text-xs font-semibold transition-colors shadow-xs cursor-pointer shrink-0" title="Buat / Rencanakan Rute Baru">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Rute</span>
          </button>
        </div>

        <!-- Route Planning & Measuring Form Card -->
        <div id="routePlanningCard" class="hidden p-4 bg-white dark:bg-slate-950 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-md space-y-3.5">
          <div class="flex items-center justify-between pb-2.5 border-b border-slate-100 dark:border-slate-800">
            <span class="text-xs font-bold uppercase tracking-wide text-orange-600 dark:text-orange-400 flex items-center gap-1.5">
              <span>Perencanaan Rute Navigasi SAR</span>
            </span>
            <button type="button" id="btnCancelRouteFormTop" class="p-1 text-slate-400 hover:text-slate-600 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer" title="Tutup Form" aria-label="Tutup Form Rute">
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Input Nama Jalur Operasi Langsung Pada Form -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400">
                Nama Rute <span class="text-orange-500">*</span>
              </label>
              <span id="routeRealtimeStatusBadge" class="text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-semibold transition">
                Siap Rancang
              </span>
            </div>
            <input 
              type="text" 
              id="measureRouteNameInput" 
              value=""
              placeholder="Rute 1" 
              class="control control-sm w-full font-medium"
            >
          </div>

          <!-- Pilihan Mode Pengukuran / Rute: Jarak Lurus & Jalur Manual -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400">Metode Rute</label>
              <span id="manualPointsCounter" class="text-[11px] font-mono font-bold px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 tabular-nums">0 Titik</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs font-bold">
              <button type="button" id="btnModeStraight" class="p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-center transition shadow-xs cursor-pointer">
                <span class="font-semibold text-xs">Jarak Lurus (2 Titik)</span>
              </button>
              <button type="button" id="btnModePath" class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-center hover:text-slate-900 dark:hover:text-white transition cursor-pointer">
                <span class="font-semibold text-xs">Jalur Manual (Multi-Titik)</span>
              </button>
            </div>
          </div>

          <!-- Penyesuaian Garis & Warna Rute -->
          <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 space-y-2.5 text-xs">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 uppercase tracking-wider block">Garis & Warna Rute</span>
              <span id="labelRouteStrokeColor" class="font-mono text-emerald-600 dark:text-emerald-400 font-bold text-[11px]">#10B981</span>
            </div>

            <!-- Preset Color Swatches -->
            <div class="flex items-center gap-1.5 flex-wrap" id="routeColorSwatches">
              <!-- Rendered via JS -->
            </div>

            <!-- Custom Color Picker Row -->
            <div class="flex items-center justify-between text-[11px] pt-1.5 border-t border-slate-200/60 dark:border-slate-800">
              <span class="text-slate-600 dark:text-slate-400 font-medium">Warna Kustom</span>
              <div class="flex items-center gap-2">
                <input type="color" id="routeCustomColorInput" value="#10b981" class="w-7 h-7 p-0 border border-slate-300 dark:border-slate-700 rounded-lg cursor-pointer bg-transparent">
                <span id="routeCustomColorHex" class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">#10b981</span>
              </div>
            </div>

            <!-- Stroke Weight Slider -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-0.5">
                <span class="text-slate-600 dark:text-slate-400 font-medium">Ketebalan Garis</span>
                <span id="labelRouteStrokeWeight" class="font-mono text-slate-900 dark:text-white font-bold tabular-nums">3.5 px</span>
              </div>
              <input type="range" id="routeStrokeWeightSlider" min="1.5" max="8" step="0.5" value="3.5" class="w-full accent-emerald-500">
            </div>

            <!-- Stroke Opacity Slider -->
            <div>
              <div class="flex items-center justify-between text-[11px] mb-0.5">
                <span class="text-slate-600 dark:text-slate-400 font-medium">Transparansi Garis</span>
                <span id="labelRouteStrokeOpacity" class="font-mono text-slate-900 dark:text-white font-bold tabular-nums">90%</span>
              </div>
              <input type="range" id="routeStrokeOpacitySlider" min="0.2" max="1" step="0.05" value="0.9" class="w-full accent-emerald-500">
            </div>
          </div>

          <!-- Live Distance & ETA Card with DMS Coordinates Readout -->
          <div class="p-3.5 bg-slate-50 dark:bg-slate-900/90 rounded-2xl border border-emerald-500/30 space-y-2.5 text-xs">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-1.5">
                <span class="text-slate-600 dark:text-slate-400 font-semibold">Total Jarak:</span>
                <select id="measureDistanceUnit" class="control control-sm text-emerald-600 dark:text-emerald-400 font-bold cursor-pointer">
                  <option value="NM" selected>NM</option>
                  <option value="KM">Km</option>
                  <option value="M">Meter</option>
                </select>
              </div>
              <div class="text-right">
                <span id="liveRouteDistNm" class="text-base font-bold text-emerald-600 dark:text-emerald-400 font-mono tabular-nums">0.00 NM</span>
                <span id="liveRouteDistKm" class="text-[11px] text-slate-500 dark:text-slate-400 block font-mono tabular-nums">0.00 km</span>
              </div>
            </div>

            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
              <span>Arah Haluan (Bearing)</span>
              <span id="liveRouteBearing" class="font-bold text-orange-600 dark:text-orange-400 font-mono tabular-nums">0° (Utara)</span>
            </div>

            <div class="flex items-center justify-between text-slate-700 dark:text-slate-300">
              <span>Estimasi Waktu Tiba (ETA)</span>
              <span id="liveRouteEta" class="font-bold text-slate-900 dark:text-white font-mono text-sm tabular-nums">0 Menit</span>
            </div>

            <!-- DMS Coordinates Result Box -->
            <div class="pt-2 border-t border-slate-200 dark:border-slate-800 space-y-1.5 text-[11px] font-mono">
              <div class="flex items-start justify-between gap-1">
                <span class="text-emerald-600 dark:text-emerald-400 font-bold shrink-0">Asal (A):</span>
                <span id="liveRouteStartDms" class="text-slate-700 dark:text-slate-200 text-right font-semibold text-[11px]">Belum ditentukan</span>
              </div>
              <div class="flex items-start justify-between gap-1">
                <span class="text-red-600 dark:text-red-400 font-bold shrink-0">Tujuan (B):</span>
                <span id="liveRouteEndDms" class="text-slate-700 dark:text-slate-200 text-right font-semibold text-[11px]">Belum ditentukan</span>
              </div>
            </div>
          </div>


          <!-- Custom Speed Input -->
          <div class="flex items-center gap-2">
            <div class="flex-1">
              <div class="field">
                <label class="field-label">Kecepatan Manual</label>
                <input placeholder=" " type="number" id="measureVesselSpeed" value="25" min="0.1" step="0.1" class="field-input w-full font-mono">
              </div>
            </div>
            <div>
              <div class="field">
                <label class="field-label">Satuan</label>
                <select id="measureSpeedUnit" class="field-input font-semibold cursor-pointer">
                <option value="knot">Knot (Kts)</option>
                <option value="kmh">Km / Jam</option>
                <option value="ms">m / detik (m/s)</option>
              </select>
              </div>
            </div>
          </div>

          <!-- Feedback Alert Banner -->
          <div id="routeSaveFeedbackBanner" class="hidden text-xs p-2.5 rounded-xl transition"></div>

          <!-- Action Buttons (Bersihkan Garis, Undo Titik & Simpan Rute) -->
          <div class="flex items-center gap-2 pt-2.5 border-t border-slate-100 dark:border-slate-800">
            <button type="button" id="btnClearMeasureRoute" onclick="window.SAR_GIS && window.SAR_GIS.clearRoute()" class="px-3 py-2 text-xs font-semibold rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer" title="Kosongkan seluruh titik garis rute (reset garis)">
              Bersihkan
            </button>
            <button type="button" id="btnResetMeasureLine" class="hidden" onclick="window.SAR_GIS && window.SAR_GIS.resetMeasureLine()"></button>
            <button type="button" id="btnUndoMeasureRouteWaypoint" class="hidden" onclick="window.SAR_GIS && window.SAR_GIS.undoRoutePoint()"></button>
            <button type="button" id="btnBottomUndoRoute" onclick="window.SAR_GIS && window.SAR_GIS.undoRoutePoint()" class="px-3 py-2 text-xs font-semibold rounded-xl bg-amber-500/15 hover:bg-amber-500/25 text-amber-700 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer flex items-center gap-1.5" title="Batalkan titik rute terakhir (1 per 1)">
              <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 7v6h6M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"/></svg>
              <span>Undo</span>
            </button>
            <button type="button" id="btnSaveMeasureRoute" onclick="window.SAR_GIS && window.SAR_GIS.saveRoute()" class="flex-1 py-2 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white transition shadow-xs flex items-center justify-center gap-1.5 cursor-pointer">
              <span>Simpan Rute Ini</span>
            </button>
          </div>
        </div>

        <!-- Saved Routes List -->
        <div class="space-y-2">
          <div id="savedRoutesListContainer" class="space-y-2">
            <!-- Populated by JS -->
          </div>
        </div>
      </div>

    </div>

    <!-- MAP CANVAS (LEAFLET INTERACTIVE FULL VIEWPORT) -->
    <div class="flex-1 h-full relative" id="gisMapWrapper" style="height: 100%; flex: 1;">
      <div id="gisLeafletMap" class="w-full h-full z-0" style="width: 100%; height: 100%;"></div>

      <!-- Floating Circular Map Controls Dock (Top-Right Vertical Toolbar: Search, Basemap, Unit) -->
      <div id="gisFloatingMapDock" role="toolbar" aria-orientation="vertical" aria-label="Kontrol Peta Taktis" aria-controls="gisLeafletMap" focusgroup="toolbar wrap block" class="absolute top-3 right-3 z-[405] flex flex-col items-end gap-2">
        <!-- 1. Floating Search Control -->
        <div class="relative flex items-center" data-fab-group="search">
          <button
            type="button"
            id="btnFloatingSearchToggle"
            class="gis-fab-btn"
            aria-label="Pencarian Lokasi"
            aria-expanded="false"
            aria-controls="floatingSearchPopover"
            title="Cari Lokasi / Koordinat"
          >
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          </button>

          <div id="floatingSearchPopover" class="gis-fab-popover hidden z-50" style="width: min(340px, calc(100vw - 5rem));">
            <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-2.5 rounded-2xl border border-slate-200/90 dark:border-slate-700/80 shadow-xl" id="locationSearchBarWrapper">
              <div class="relative flex items-center">
                <span class="absolute left-3 text-slate-400 dark:text-slate-500 pointer-events-none">
                  <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                </span>
                <input 
                  type="text" 
                  id="locationSearchInput" 
                  placeholder="Cari kota, pulau, pelabuhan, atau DMS/DD..."
                  autocomplete="off"
                  class="control control-sm w-full font-medium pl-9 pr-8 rounded-xl shadow-xs"
                >
                <button type="button" id="btnClearLocationSearch" class="btn-tactile absolute right-2.5 text-slate-400 hover:text-slate-700 dark:hover:text-white hidden p-0.5 cursor-pointer" title="Bersihkan" aria-label="Bersihkan Pencarian">
                  <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
              </div>

              <!-- Autocomplete Dropdown Popover -->
              <div id="locationSearchResultsPopover" class="mt-2 bg-white dark:bg-slate-900 border border-slate-200/90 dark:border-slate-700/90 rounded-xl shadow-xl overflow-hidden hidden max-h-72 overflow-y-auto gis-scrollbar text-xs">
                <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-800 text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex justify-between items-center bg-slate-50 dark:bg-slate-950/50">
                  <span>Pencarian Lokasi &amp; GPS</span>
                  <span class="text-orange-600 dark:text-orange-400 font-mono font-bold">115 SAR</span>
                </div>
                <div id="locationSearchResultsList" class="divide-y divide-slate-100 dark:divide-slate-800">
                  <!-- Populated by JS -->
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 2. Floating Basemap Switcher Control -->
        <div class="relative flex items-center" data-fab-group="basemap">
          <button
            type="button"
            id="btnFloatingBasemapToggle"
            class="gis-fab-btn"
            aria-label="Jenis Peta"
            aria-expanded="false"
            aria-controls="floatingBasemapPopover"
            title="Jenis Peta: Hybrid"
          >
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
          </button>

          <div id="floatingBasemapPopover" class="gis-fab-popover hidden z-50 w-52">
            <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-2.5 rounded-2xl border border-slate-200/90 dark:border-slate-700/80 shadow-xl space-y-2">
              <div class="flex items-center justify-between px-1">
                <span class="text-[10.5px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Jenis Peta</span>
                <span id="floatingBasemapActiveLabel" class="text-[10.5px] font-mono font-bold text-orange-600 dark:text-orange-400">Hybrid</span>
              </div>
              <div class="grid grid-cols-2 gap-1.5 text-[11px] font-semibold">
                <button type="button" data-layer="hybrid" aria-pressed="true" class="btn-tactile basemap-pill-btn px-2.5 py-2 rounded-xl bg-orange-500 text-white shadow-xs transition font-bold cursor-pointer text-center">
                  Hybrid
                </button>
                <button type="button" data-layer="satellite" aria-pressed="false" class="btn-tactile basemap-pill-btn px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition font-medium cursor-pointer text-center">
                  Satelit
                </button>
                <button type="button" data-layer="roadmap" aria-pressed="false" class="btn-tactile basemap-pill-btn px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition font-medium cursor-pointer text-center">
                  Roadmap
                </button>
                <button type="button" data-layer="terrain" aria-pressed="false" class="btn-tactile basemap-pill-btn px-2.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition font-medium cursor-pointer text-center">
                  Terrain
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- 3. Floating Measurement Unit Control -->
        <div class="relative flex items-center" data-fab-group="unit">
          <button
            type="button"
            id="btnFloatingUnitToggle"
            class="gis-fab-btn"
            aria-label="Satuan Pengukuran"
            aria-expanded="false"
            aria-controls="floatingUnitPopover"
            title="Satuan Pengukuran (NM / KM / M)"
          >
            <span id="floatingUnitBadge" class="text-[11px] font-mono font-extrabold tracking-tight">NM</span>
          </button>

          <div id="floatingUnitPopover" class="gis-fab-popover hidden z-50 w-44">
            <div class="bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-2.5 rounded-2xl border border-slate-200/90 dark:border-slate-700/80 shadow-xl space-y-2">
              <div class="px-1 text-[10.5px] font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Satuan Peta</div>
              <div id="gisGlobalUnitSwitcher" class="grid grid-cols-3 gap-1.5 text-[11px] font-semibold" title="Satuan Pengukuran Dinamis Peta Taktis">
                <button type="button" data-global-unit="NM" aria-pressed="true" class="btn-tactile global-unit-pill-btn px-2 py-1.5 rounded-xl bg-orange-500 text-white shadow-xs transition font-bold cursor-pointer text-center" title="Nautical Mile">
                  NM
                </button>
                <button type="button" data-global-unit="KM" aria-pressed="false" class="btn-tactile global-unit-pill-btn px-2 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition font-medium cursor-pointer text-center" title="Kilometer">
                  KM
                </button>
                <button type="button" data-global-unit="M" aria-pressed="false" class="btn-tactile global-unit-pill-btn px-2 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 transition font-medium cursor-pointer text-center" title="Meter">
                  M
                </button>
              </div>
              <p class="px-1 text-[10px] text-slate-500 dark:text-slate-400 leading-tight">NM: Nautical Mile &bull; KM: Kilometer &bull; M: Meter</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Floating Offline / Blank Spot Status Badge -->
      <div id="gisOfflineIndicator" class="absolute bottom-14 left-3 z-[400] hidden px-3.5 py-2 rounded-xl bg-amber-500/95 text-slate-950 font-semibold text-xs shadow-lg backdrop-blur-md flex items-center gap-2 border border-amber-600/30 transition-all duration-300 max-w-[calc(100%-1.5rem)]">
        <span class="relative flex h-2.5 w-2.5 shrink-0">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-600"></span>
        </span>
        <span class="truncate">Mode Luar Jaringan (Blank Spot): Plotting Taktis Tetap Berfungsi di Memori Lokal</span>
      </div>

      <!-- Floating Button: Buka Tools Plotting (when sidebar is closed) -->
      <button type="button" id="btnFloatingOpenSidebar" class="btn-tactile absolute top-3 left-3 z-[400] hidden px-3.5 py-2 rounded-xl bg-white/95 dark:bg-slate-900/95 hover:bg-white dark:hover:bg-slate-900 text-slate-800 dark:text-white text-xs font-semibold border border-slate-200 dark:border-slate-700/80 shadow-lg flex items-center gap-2 backdrop-blur-md transition cursor-pointer">
        <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><path d="M9 3v18M11 9l3 3-3 3"/></svg>
        <span>Buka Tools Plotting</span>
      </button>

      <!-- Floating Place Card (when a search result is clicked) -->
      <div id="floatingSearchBanner" class="absolute top-3 left-1/2 -translate-x-1/2 z-[400] hidden p-4 rounded-2xl bg-white/95 dark:bg-slate-900/95 border border-slate-200 dark:border-slate-700/80 text-slate-900 dark:text-white shadow-xl backdrop-blur-md w-[92vw] max-w-lg transition-all">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3 min-w-0 flex-1">
            <div class="w-10 h-10 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0 font-bold border border-orange-500/25 text-lg shadow-xs">
              <span id="searchBannerIcon"></span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2 flex-wrap">
                <h3 id="searchBannerTitle" class="text-sm font-bold truncate text-slate-900 dark:text-white">-</h3>
                <span id="searchBannerBadge" class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30 shrink-0">MAPS</span>
              </div>
              <p id="searchBannerAddress" class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5 font-medium">-</p>
              <div class="flex items-center gap-3 mt-1.5 text-[11px] font-mono text-slate-600 dark:text-slate-300 tabular-nums">
                <span id="searchBannerCoords" class="text-orange-600 dark:text-orange-400 font-bold">-</span>
                <span class="text-slate-300 dark:text-slate-700">|</span>
                <span id="searchBannerDecimal" class="text-slate-500 dark:text-slate-400">-</span>
              </div>
            </div>
          </div>
          <button type="button" id="btnCloseSearchBanner" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer shrink-0" title="Tutup" aria-label="Tutup Kartu Pencarian">
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex items-center gap-2 mt-3.5 pt-3 border-t border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar">
          <button type="button" id="btnConvertSearchToPoint" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-semibold text-xs shadow-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" x2="4" y1="22" y2="15"/></svg>
            <span>Plot Titik SAR</span>
          </button>
          
          <button type="button" id="btnSetSearchAsLkp" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold text-xs shadow-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>
            <span>Titik Kejadian LKP</span>
          </button>

          <button type="button" id="btnCopySearchCoords" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5 text-orange-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
            <span>Salin Koordinat</span>
          </button>

          <button type="button" id="btnMeasureFromSearch" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition-colors cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5 text-sky-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.41 2.41 0 0 1 0-3.4l2.6-2.6a2.41 2.41 0 0 1 3.4 0Z"/><path d="m14.5 12.5 2-2"/><path d="m11.5 9.5 2-2"/><path d="m8.5 6.5 2-2"/><path d="m17.5 15.5 2-2"/></svg>
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
          <div class="absolute -top-7 left-0 flex items-center gap-2 bg-slate-950/95 text-emerald-400 border border-emerald-500/40 px-2.5 py-0.5 rounded-t-lg text-[11px] font-mono font-bold tracking-wider shadow-lg whitespace-nowrap">
            <span>BINGKAI CAPTURE DOKUMEN CETAK (RASIO 16:9 &bull; 1280&times;720 PX)</span>
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
          <div id="captureFrameBoundsBadge" class="absolute -bottom-6 right-0 bg-slate-950/95 text-slate-300 border border-slate-700 px-2 py-0.5 rounded-b-lg text-[11px] font-mono shadow-lg whitespace-nowrap">
            BBox: -
          </div>
        </div>
      </div>

      <!-- Floating Capture Control Panel -->
      <div id="captureControlPanel" class="absolute top-3 right-3 z-[450] hidden w-[350px] max-w-[92vw] bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border border-emerald-500/40 rounded-2xl shadow-2xl text-slate-800 dark:text-slate-100 overflow-hidden">
        <!-- Header -->
        <div class="px-4 py-3 bg-emerald-600 text-white flex items-center justify-between">
          <div class="flex items-center gap-2.5 min-w-0">
            <span class="w-7 h-7 rounded-lg bg-white/15 flex items-center justify-center shrink-0">
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
            </span>
            <div class="min-w-0">
              <div class="text-xs font-bold uppercase tracking-wider truncate">Capture Peta Dokumen (16:9)</div>
              <div class="text-[11px] text-emerald-100 font-medium truncate">Otomatis mendeteksi komponen di kotak 16:9</div>
            </div>
          </div>
          <button type="button" id="btnCloseCaptureMode" class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white transition cursor-pointer shrink-0" title="Tutup Mode Capture" aria-label="Tutup Mode Capture">
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Body -->
        <div class="p-3.5 space-y-3 text-xs max-h-[78vh] overflow-y-auto gis-scrollbar">
          <!-- 1. Pilih Operasi SAR -->
          <div>
            <div class="field">
              <label for="captureSelectOperasi" class="field-label">1. Operasi SAR Target</label>
              <select id="captureSelectOperasi" class="field-input w-full font-semibold">
              <option value="">-- Pilih Operasi SAR --</option>
              <?php foreach ($activeOperasiList as $ops): ?>
                <option value="<?= (int)$ops['id_operasi'] ?>" <?= ($selectedOperasiId === (int)$ops['id_operasi']) ? 'selected' : '' ?>>
                  Ops #<?= (int)$ops['id_operasi'] ?> - <?= e(mb_strimwidth($ops['nama_operasi'], 0, 38, '...')) ?>
                </option>
              <?php endforeach; ?>
            </select>
            </div>
          </div>

          <!-- 2. Pilih Jenis Dokumen Cetak -->
          <div class="grid grid-cols-1 gap-2">
            <div>
              <div class="field">
                <label for="captureSelectJenisDokumen" class="field-label">2. Sasaran Dokumen Cetak Resmi</label>
                <select id="captureSelectJenisDokumen" class="field-input w-full font-semibold text-emerald-700 dark:text-emerald-400">
                <option value="RENOPS">RENOPS - Peta Lokasi LKP (1x per Operasi)</option>
                <option value="RENOPS_RENCANA">RENOPS - Peta Rencana Operasi (Per Hari Operasi)</option>
              </select>
              </div>
            </div>

            <!-- Sub-selector khusus untuk Hari RENOPS -->
            <div id="captureReferensiWrapper" class="hidden">
              <div class="field">
                <label for="captureSelectReferensi" class="field-label">Pilih Hari Rencana Operasi (RENOPS) <span class="text-red-500">*</span></label>
                <select id="captureSelectReferensi" class="field-input w-full font-semibold">
                <option value="">-- Memuat Daftar Hari RENOPS --</option>
              </select>
              </div>
            </div>
          </div>

          <!-- 3. Live Deteksi Komponen di Dalam Bingkai 16:9 -->
          <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/90 border border-slate-200 dark:border-slate-800 space-y-2">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1">
                <span>Komponen di Area 16:9</span>
              </span>
              <button type="button" id="btnFitCaptureComponents" class="text-[11px] font-semibold text-orange-600 dark:text-orange-400 hover:underline cursor-pointer" title="Paskan semua komponen operasi ke dalam bingkai 16:9">
                Paskan Peta
              </button>
            </div>

            <!-- 4 Counters Grid -->
            <div class="grid grid-cols-4 gap-1.5 text-center font-mono tabular-nums">
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountPoints" class="text-xs font-bold text-purple-600 dark:text-purple-400">0</div>
                <div class="text-[10.5px] text-slate-500 font-sans font-semibold">Titik</div>
              </div>
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountPolygons" class="text-xs font-bold text-blue-600 dark:text-blue-400">0</div>
                <div class="text-[10.5px] text-slate-500 font-sans font-semibold">Area</div>
              </div>
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountPatterns" class="text-xs font-bold text-orange-600 dark:text-orange-400">0</div>
                <div class="text-[10.5px] text-slate-500 font-sans font-semibold">Pola SAR</div>
              </div>
              <div class="p-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div id="captureCountRoutes" class="text-xs font-bold text-emerald-600 dark:text-emerald-400">0</div>
                <div class="text-[10.5px] text-slate-500 font-sans font-semibold">Jalur</div>
              </div>
            </div>

            <!-- Daftar Ringkas Komponen -->
            <div id="captureDetectedList" class="max-h-28 overflow-y-auto gis-scrollbar space-y-1 text-[11px] pr-1">
              <div class="text-slate-400 italic text-center py-2">Memindai komponen di dalam bingkai 16:9...</div>
            </div>
          </div>

          <!-- Status Snapshot Tersimpan -->
          <div id="captureSavedStatusBanner" class="hidden p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-[11px] text-emerald-700 dark:text-emerald-300 space-y-1">
            <!-- Diisi via JS -->
          </div>

          <!-- Action Buttons -->
          <div class="pt-1 flex items-center gap-2">
            <button type="button" id="btnExecuteCaptureSave" class="flex-1 py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold uppercase tracking-wider shadow-md transition flex items-center justify-center gap-2 cursor-pointer">
              <span>Capture &amp; Simpan ke Dokumen</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Bottom Crosshair Coordinate HUD Bar -->
      <div class="bg-white/95 dark:bg-slate-900/95 text-slate-800 dark:text-slate-100 backdrop-blur-md absolute bottom-3 left-3 z-[400] px-3.5 py-2 rounded-xl border border-slate-200/90 dark:border-slate-700/80 text-[11px] font-mono tabular-nums shadow-lg flex items-center gap-2.5 sm:gap-3 select-none max-w-[calc(100%-1.5rem)]">
        <div class="flex items-center gap-1.5 min-w-0">
          <span class="text-orange-600 dark:text-orange-400 font-bold shrink-0">Lat/Lng:</span>
          <span id="hudCoordsDecimal" class="truncate">-4.037664, 122.486662</span>
        </div>
        <span class="hidden md:inline text-slate-300 dark:text-slate-700" aria-hidden="true">|</span>
        <div class="hidden md:flex items-center gap-1.5 shrink-0">
          <span class="text-sky-600 dark:text-sky-400 font-bold">DMS:</span>
          <span id="hudCoordsDms">04° 02' 16" S - 122° 29' 12" E</span>
        </div>
        <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">|</span>
        <div class="flex items-center gap-1.5 shrink-0">
          <span class="text-slate-500 dark:text-slate-400 font-bold">Zoom:</span>
          <span id="hudZoomLevel" class="text-amber-600 dark:text-amber-400 font-bold">11</span>
        </div>
      </div>
    </div>
  </div>

  <!-- ======================================================== -->
  <!-- MODAL EKSPOR DATA SPASIAL (KML / KMZ / GEOJSON)          -->
  <!-- Direct child of #gisRootContainer + Top Layer <dialog>   -->
  <!-- ======================================================== -->
  <dialog id="exportSpatialModal" closedby="any" aria-labelledby="exportModalTitle" class="gis-modal-dialog m-auto p-0 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl max-w-lg w-[calc(100%-2rem)] max-h-[90dvh] overflow-hidden text-slate-800 dark:text-slate-100">
    <!-- Modal Header (Pinned) -->
    <div class="shrink-0 flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-xl bg-orange-500/10 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        </div>
        <div class="min-w-0">
          <h3 id="exportModalTitle" class="text-sm font-bold text-slate-900 dark:text-white truncate">Ekspor Data Spasial SAR</h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">Unduh data plotting untuk Google Earth, QGIS, atau ATAK</p>
        </div>
      </div>
      <button type="button" id="btnCloseExportModal" class="btn-tactile shrink-0 p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/70 dark:hover:bg-slate-800 transition cursor-pointer" title="Tutup Dialog" aria-label="Tutup Dialog">
        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- Modal Body (Scrollable) -->
    <div class="flex-1 min-h-0 overflow-y-auto gis-scrollbar px-5 sm:px-6 py-4 space-y-4 text-xs">
      <!-- 1. Pilihan Lingkup / Cakupan Data (Scope) -->
      <div>
        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-2">1. Cakupan Data Ekspor (Scope)</label>
        <div class="space-y-2">
          <!-- Option A: Tampilan Saat Ini -->
          <label class="gis-choice-card flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer transition">
            <input type="radio" name="exportScope" value="current" checked autofocus class="mt-0.5 text-orange-600 focus:ring-orange-500 shrink-0">
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-slate-900 dark:text-white">Tampilan Peta Saat Ini</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Mengekspor layer yang sedang aktif sesuai filter saat ini</div>
            </div>
          </label>

          <!-- Option B: Operasi SAR Tertentu -->
          <label class="gis-choice-card flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer transition">
            <input type="radio" name="exportScope" value="operasi" class="mt-0.5 text-orange-600 focus:ring-orange-500 shrink-0">
            <div class="flex-1 min-w-0 space-y-2">
              <div class="font-semibold text-slate-900 dark:text-white">Operasi SAR Aktif</div>
              <select id="exportOperasiSelect" class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-orange-500 disabled:opacity-50">
                <?php if (empty($activeOperasiList)): ?>
                  <option value="">(Tidak ada operasi SAR aktif)</option>
                <?php else: ?>
                  <?php foreach ($activeOperasiList as $op): ?>
                    <option value="<?= (int)$op['id_operasi'] ?>" <?= ($selectedOperasiId === (int)$op['id_operasi']) ? 'selected' : '' ?>>
                      #<?= (int)$op['id_operasi'] ?> - <?= htmlspecialchars($op['nama_operasi']) ?>
                    </option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
              <label class="flex items-center gap-2 pt-0.5 cursor-pointer">
                <input type="checkbox" id="exportIncludeBaseCheck" checked class="rounded border-slate-300 dark:border-slate-700 text-orange-600 focus:ring-orange-500">
                <span class="text-[11px] text-slate-600 dark:text-slate-400">Sertakan Base Map Global (Potensi, Kansar, Pos SAR)</span>
              </label>
            </div>
          </label>

          <!-- Option C: Base Map Global -->
          <label class="gis-choice-card flex items-start gap-3 p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer transition">
            <input type="radio" name="exportScope" value="basemap" class="mt-0.5 text-orange-600 focus:ring-orange-500 shrink-0">
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-slate-900 dark:text-white">Base Map Global</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Komponen permanen pangkalan dan sarana pendukung (tanpa ID operasi)</div>
            </div>
          </label>
        </div>
      </div>

      <!-- Opsi Layer Tersembunyi -->
      <div class="pt-1">
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" id="exportIncludeHiddenCheck" class="rounded border-slate-300 dark:border-slate-700 text-orange-600 focus:ring-orange-500 shrink-0">
          <span class="text-xs font-medium text-slate-700 dark:text-slate-300">Sertakan Layer yang Sedang Disembunyikan (Ikon Mata Tertutup)</span>
        </label>
      </div>

      <!-- 2. Pilihan Format Berkas -->
      <div class="pt-1">
        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-2">2. Format Berkas</label>
        <div class="grid grid-cols-3 gap-2">
          <label class="gis-choice-card flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer transition text-center">
            <input type="radio" name="exportFormat" value="kmz" checked class="mb-1 text-orange-600 focus:ring-orange-500">
            <span class="font-bold text-slate-900 dark:text-white text-xs">KMZ</span>
            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold">Direkomendasikan</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400">Google Earth + Ikon</span>
          </label>
          <label class="gis-choice-card flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer transition text-center">
            <input type="radio" name="exportFormat" value="kml" class="mb-1 text-orange-600 focus:ring-orange-500">
            <span class="font-bold text-slate-900 dark:text-white text-xs">KML</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">OGC Standar</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400">XML Dokumen</span>
          </label>
          <label class="gis-choice-card flex flex-col items-center justify-center p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer transition text-center">
            <input type="radio" name="exportFormat" value="geojson" class="mb-1 text-orange-600 focus:ring-orange-500">
            <span class="font-bold text-slate-900 dark:text-white text-xs">GeoJSON</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400 font-medium">Universal GIS</span>
            <span class="text-[10px] text-slate-500 dark:text-slate-400">QGIS / ATAK</span>
          </label>
        </div>
      </div>
    </div>

    <!-- Modal Footer (Pinned) -->
    <div class="shrink-0 px-5 sm:px-6 py-3.5 border-t border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900 flex items-center justify-end gap-2">
      <button type="button" id="btnCancelExportModal" class="px-4 py-2 rounded-xl bg-slate-200/80 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer">
        Batal
      </button>
      <button type="button" id="btnExecuteSpatialExport" class="px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-semibold transition shadow-xs flex items-center gap-1.5 cursor-pointer">
        <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        <span>Unduh Berkas</span>
      </button>
    </div>
  </dialog>

  <!-- ======================================================== -->
  <!-- MODAL IMPOR DATA GEOSPASIAL (KML / KMZ)                  -->
  <!-- Direct child of #gisRootContainer + Top Layer <dialog>   -->
  <!-- ======================================================== -->
  <dialog id="importKmlModal" closedby="any" aria-labelledby="importModalTitle" class="gis-modal-dialog m-auto p-0 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl max-w-3xl w-[calc(100%-2rem)] max-h-[90dvh] overflow-hidden text-slate-800 dark:text-slate-100">
    <!-- Modal Header (Pinned) -->
    <div class="shrink-0 flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-xl bg-sky-500/10 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
        </div>
        <div class="min-w-0">
          <h3 id="importModalTitle" class="text-sm font-bold text-slate-900 dark:text-white truncate">Impor Data Geospasial (KML / KMZ)</h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">Unggah berkas Google Earth KML / KMZ untuk dikurasi dan disimpan ke sistem QR-SAR</p>
        </div>
      </div>
      <button type="button" id="btnCloseImportModal" class="btn-tactile shrink-0 p-1.5 rounded-lg text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hover:bg-slate-200/70 dark:hover:bg-slate-800 transition cursor-pointer" title="Tutup Dialog" aria-label="Tutup Dialog">
        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12"/></svg>
      </button>
    </div>

    <!-- Modal Body (Scrollable) -->
    <div class="flex-1 min-h-0 overflow-y-auto gis-scrollbar px-5 sm:px-6 py-4 space-y-5 text-xs">
      <!-- Inline Top-Layer Error Banner -->
      <div id="importInlineError" role="alert" class="hidden p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 flex items-start justify-between gap-2 text-xs">
        <div class="flex items-start gap-2 min-w-0">
          <svg class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
          <span id="importInlineErrorText" class="font-semibold break-words"></span>
        </div>
      </div>

      <!-- Step 1: Berkas KML / KMZ -->
      <div>
        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Langkah 1: Pilih Berkas KML / KMZ</label>
        <div id="importDropZone" role="button" tabindex="0" autofocus class="border-2 border-dashed border-slate-300 dark:border-slate-700 hover:border-orange-500 dark:hover:border-orange-500 focus:outline-none focus:border-orange-500 rounded-2xl p-5 flex flex-col items-center justify-center text-center bg-slate-50/70 dark:bg-slate-800/40 transition cursor-pointer">
          <input type="file" id="importKmlFileInput" accept=".kml,.kmz" class="hidden">
          <div class="w-11 h-11 rounded-2xl bg-orange-500/10 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 flex items-center justify-center mb-2">
            <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path d="M7 16a4 4 0 0 1-.88-7.903A5 5 0 1 1 15.9 6L16 6a5 5 0 0 1 1 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
          </div>
          <div class="font-semibold text-slate-800 dark:text-slate-200 text-sm">Tarik &amp; lepas berkas di sini, atau <span class="text-orange-600 dark:text-orange-400 underline">pilih dari perangkat</span></div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Mendukung berkas OGC KML 2.2 (.kml) dan Google Earth Terkompresi (.kmz)</div>
          <div id="importFileInfo" class="mt-3 hidden inline-flex flex-wrap items-center justify-center gap-2 px-3 py-1.5 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20 font-mono text-[11px] max-w-full">
            <span id="importFileName" class="font-bold truncate max-w-[260px]"></span>
            <span id="importFileSize" class="text-slate-500 dark:text-slate-400 shrink-0"></span>
          </div>
        </div>
      </div>

      <!-- Step 2: Target Scope Selector -->
      <div>
        <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Langkah 2: Target Penyimpanan Data</label>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <!-- Target A: Operasi SAR Aktif -->
          <label class="gis-choice-card flex flex-col p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 cursor-pointer transition">
            <div class="flex items-center gap-2">
              <input type="radio" id="importTargetTypeOperasi" name="importTargetType" value="operasi" checked class="text-orange-600 focus:ring-orange-500 shrink-0">
              <span class="font-semibold text-slate-900 dark:text-white">Operasi SAR Aktif</span>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 mb-2">Tautkan seluruh entitas ke misi operasi pencarian aktif</div>
            <select id="importTargetOperasiSelect" class="w-full px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-orange-500 disabled:opacity-50">
              <?php if (empty($activeOperasiList)): ?>
                <option value="">(Tidak ada operasi SAR aktif)</option>
              <?php else: ?>
                <?php foreach ($activeOperasiList as $op): ?>
                  <option value="<?= (int)$op['id_operasi'] ?>" <?= ($selectedOperasiId === (int)$op['id_operasi']) ? 'selected' : '' ?>>
                    #<?= (int)$op['id_operasi'] ?> - <?= htmlspecialchars($op['nama_operasi']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </label>

          <!-- Target B: Base Map Global -->
          <label class="gis-choice-card flex flex-col p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-orange-500/50 bg-slate-50/60 dark:bg-slate-800/40 transition <?= !$isAdmin ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer' ?>">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-2">
                <input type="radio" id="importTargetTypeBasemap" name="importTargetType" value="basemap" <?= !$isAdmin ? 'disabled' : '' ?> class="text-orange-600 focus:ring-orange-500 shrink-0">
                <span class="font-semibold text-slate-900 dark:text-white">Base Map Global</span>
              </div>
              <?php if (!$isAdmin): ?>
                <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 font-semibold border border-amber-500/30 shrink-0">Khusus Administrator</span>
              <?php endif; ?>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Data permanen pangkalan Basarnas, pos SAR, dan fasilitas potensi tanpa ID Operasi</div>
          </label>
        </div>
        <?php if (!$isAdmin && empty($activeOperasiList)): ?>
          <div class="mt-2 p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 text-[11px] flex items-start gap-2">
            <svg class="w-4 h-4 shrink-0 mt-0.5 text-amber-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Tidak ada operasi SAR yang berstatus OPEN. Pengguna non-administrator hanya dapat mengimpor data ke operasi SAR aktif.</span>
          </div>
        <?php endif; ?>
      </div>

      <!-- Step 3: Pratinjau & Kurasi Entitas -->
      <div>
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
          <label class="font-semibold text-slate-700 dark:text-slate-300">Langkah 3: Pratinjau &amp; Kurasi Entitas</label>
          <!-- Counters Badges -->
          <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-mono tabular-nums">
            <span class="px-2 py-0.5 rounded-md bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 font-semibold" title="Titik Plotting">Titik: <span id="importCountPoints">0</span></span>
            <span class="px-2 py-0.5 rounded-md bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 font-semibold" title="Sektor Poligon">Poligon: <span id="importCountPolygons">0</span></span>
            <span class="px-2 py-0.5 rounded-md bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20 font-semibold" title="Pola Pencarian SAR">Pola SAR: <span id="importCountPatterns">0</span></span>
            <span class="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-semibold" title="Jalur Navigasi">Jalur: <span id="importCountRoutes">0</span></span>
            <span class="px-2 py-0.5 rounded-md bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 font-semibold" title="Peringatan Validasi">Peringatan: <span id="importCountWarnings">0</span></span>
          </div>
        </div>

        <!-- Warnings Alert Box -->
        <div id="importWarningsBox" class="mb-2 p-3 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-700 dark:text-amber-300 hidden">
          <div class="font-semibold text-xs flex items-center gap-1.5 mb-1">
            <svg class="w-4 h-4 text-amber-500 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <span>Peringatan Normalisasi Geospasial</span>
          </div>
          <ul id="importWarningsList" class="list-disc list-inside text-[11px] space-y-0.5 max-h-24 overflow-y-auto gis-scrollbar"></ul>
        </div>

        <!-- Bulk Toolbar -->
        <div class="flex flex-wrap items-center justify-between gap-2 p-2 rounded-xl bg-slate-100 dark:bg-slate-800/70 border border-slate-200 dark:border-slate-800 text-[11px] mb-2">
          <div class="flex items-center gap-1.5">
            <button type="button" id="importSelectAllBtn" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-orange-500 hover:text-orange-600 dark:hover:text-orange-400 font-semibold transition cursor-pointer">Pilih Semua</button>
            <button type="button" id="importDeselectAllBtn" class="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-rose-500 hover:text-rose-600 dark:hover:text-rose-400 font-semibold transition cursor-pointer">Kosongkan</button>
          </div>
          <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-slate-500 dark:text-slate-400 font-medium">Ubah Garis:</span>
            <button type="button" id="importSetAllLinesRoute" class="px-2.5 py-1 rounded-lg bg-emerald-600/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600 hover:text-white font-semibold transition cursor-pointer">Semua Garis → Jalur</button>
            <button type="button" id="importSetAllLinesPattern" class="px-2.5 py-1 rounded-lg bg-orange-600/10 text-orange-700 dark:text-orange-400 border border-orange-500/30 hover:bg-orange-600 hover:text-white font-semibold transition cursor-pointer">Semua Garis → Pola SAR</button>
          </div>
        </div>

        <!-- Entity Preview List -->
        <div id="importPreviewList" class="max-h-64 overflow-y-auto gis-scrollbar space-y-1.5 border border-slate-200 dark:border-slate-800 rounded-xl p-2 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="p-6 text-center text-slate-400 dark:text-slate-500">
            Pilih atau seret berkas KML / KMZ untuk memuat daftar entitas geospasial
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Footer (Pinned) -->
    <div class="shrink-0 px-5 sm:px-6 py-3.5 border-t border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-900 flex flex-wrap items-center justify-between gap-3">
      <label class="flex items-center gap-2 cursor-pointer text-xs">
        <input type="checkbox" id="importAutoZoomCheck" checked class="rounded border-slate-300 dark:border-slate-700 text-orange-600 focus:ring-orange-500">
        <span class="font-medium text-slate-700 dark:text-slate-300">Fokuskan Peta ke Area Impor Otomatis</span>
      </label>
      <div class="flex items-center gap-2 ml-auto">
        <button type="button" id="btnCancelImportKml" class="px-4 py-2 rounded-xl bg-slate-200/80 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer">
          Batal
        </button>
        <button type="button" id="btnConfirmImportKml" disabled class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold transition shadow-xs flex items-center gap-1.5 cursor-pointer">
          <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          <span>Impor ke Database</span>
        </button>
      </div>
    </div>
  </dialog>
</div>

<!-- ========================================== -->
<!-- 3. MASTER CLIENT-SIDE GIS APPLICATION JS   -->
<!-- ========================================== -->
<script>
window.SAR_INITIAL_GIS_DATA = {
  isAdmin: <?= jsonSafe($isAdmin) ?>,
  filterMode: <?= jsonSafe($filterMode) ?>,
  includeBase: <?= jsonSafe($includeBase) ?>,
  currentOperasiId: <?= jsonSafe($selectedOperasiId) ?>,
  currentOperasi: <?= jsonSafe($currentOperasi) ?>,
  captureDocParam: <?= jsonSafe($captureDocParam) ?>,
  captureRefParam: <?= jsonSafe($captureRefParam) ?>,
  fromRenopsParam: <?= jsonSafe($fromRenopsParam) ?>,
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

<script src="<?= assetUrl('assets/js/gis/geodesy.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/markers.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/patterns.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/naming.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/export.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/kml_importer.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/tile_fallback.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis_master.js') ?>"></script>
