/**
 * QR-SAR - Tactical Maritime GIS Master Controller
 * KPP Kendari - Basarnas
 * 100% Faithful Implementation matching MapPlottingPage.tsx and SAR Operational Guidelines
 */

(function () {
  'use strict';

  // Ikon aksi daftar (SVG, seragam dengan ikon halaman lain)
  const svgIcon = (d) => `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">${d}</svg>`;
  const GIS_ICON = {
    copy: svgIcon('<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'),
    eye: svgIcon('<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>'),
    eyeOff: svgIcon('<path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c7 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3 7 10 7a9.7 9.7 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/>'),
    edit: svgIcon('<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>'),
    trash: svgIcon('<path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>')
  };

  // Base URL & State Data from server injection
  const BASE_URL = window.location.origin;
  const initialData = window.SAR_INITIAL_GIS_DATA || {};
  const csrfToken = initialData.csrfToken || window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
  let filterMode = initialData.filterMode || (initialData.currentOperasiId ? 'operasi' : 'basemap');
  let includeBaseInActive = !!initialData.includeBase;
  let currentOperasiId = initialData.currentOperasiId ? parseInt(initialData.currentOperasiId, 10) : null;
  let currentOperasi = initialData.currentOperasi || null;
  const operasiList = Array.isArray(initialData.operasiList) ? initialData.operasiList : [];
  const activeOperasiIds = new Set(operasiList.map(op => parseInt(op.id_operasi, 10)));

  // 4. Operasi yang telah ditutup tidak masuk dalam pilihan! (Sanitasi sesi/URL)
  if (currentOperasiId && !activeOperasiIds.has(currentOperasiId)) {
    currentOperasiId = null;
    currentOperasi = null;
    filterMode = 'basemap';
  }

  // Safe HTML Escaping Helper
  const escapeHtml = typeof window.escapeHtml === 'function'
    ? window.escapeHtml
    : function (str) {
        if (str === null || str === undefined) return '';
        return String(str)
          .replace(/&/g, '&amp;')
          .replace(/</g, '&lt;')
          .replace(/>/g, '&gt;')
          .replace(/"/g, '&quot;')
          .replace(/'/g, '&#039;');
      };

  // Safe XML / KML Escaping Helper
  function escapeXml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&apos;');
  }

  function getOperasiBadgeHtml(idOps) {
    if (!idOps || idOps <= 0) return '';
    const ops = operasiList.find((o) => parseInt(o.id_operasi, 10) === parseInt(idOps, 10));
    const title = ops ? ops.nama_operasi : `Ops #${idOps}`;
    return `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-black bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30" title="Misi: ${escapeHtml(title)}">🚨 Ops #${idOps}</span>`;
  }

  // Unified GIS Fetch with Auto CSRF Injection
  async function gisFetch(url, options = {}) {
    const opts = { ...options };
    opts.headers = opts.headers || {};

    const token = csrfToken || window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
    if (token) {
      if (typeof Headers !== 'undefined' && opts.headers instanceof Headers) {
        if (!opts.headers.has('X-CSRF-Token')) opts.headers.set('X-CSRF-Token', token);
      } else if (Array.isArray(opts.headers)) {
        opts.headers.push(['X-CSRF-Token', token]);
      } else {
        opts.headers['X-CSRF-Token'] = token;
      }
    }

    if (!(opts.body instanceof FormData)) {
      if (typeof Headers !== 'undefined' && opts.headers instanceof Headers) {
        if (!opts.headers.has('Content-Type')) opts.headers.set('Content-Type', 'application/json');
      } else if (!opts.headers['Content-Type']) {
        opts.headers['Content-Type'] = 'application/json';
      }
    }

    return fetch(url, opts);
  }

  // 1. STATE MANAGEMENT
  // Master Raw Collections (containing all items loaded from database)
  let rawAllPoints = Array.isArray(initialData.points) ? [...initialData.points] : [];
  let rawAllPolygons = Array.isArray(initialData.polygons) ? [...initialData.polygons] : [];
  let rawAllPatterns = Array.isArray(initialData.patterns) ? [...initialData.patterns] : [];
  let rawAllRoutes = Array.isArray(initialData.routes) ? [...initialData.routes] : [];

  // Filtered collections actively displayed on map and sidebars
  let savedPoints = [];
  let savedPolygons = [];
  let savedPatterns = [];
  let savedRoutes = [];

  function applyActiveFilter() {
    if (filterMode === 'basemap' || filterMode === 'dasar') {
      // 1. Base Map : menampilkan komponen tanpa id operasi SAR
      savedPoints = rawAllPoints.filter(p => !p.id_operasi || parseInt(p.id_operasi, 10) === 0);
      savedPolygons = rawAllPolygons.filter(p => !p.id_operasi || parseInt(p.id_operasi, 10) === 0);
      savedPatterns = rawAllPatterns.filter(p => !p.id_operasi || parseInt(p.id_operasi, 10) === 0);
      savedRoutes = rawAllRoutes.filter(r => !r.id_operasi || parseInt(r.id_operasi, 10) === 0);
    } else if (filterMode === 'operasi' && currentOperasiId && activeOperasiIds.has(currentOperasiId)) {
      // 3. Operasi aktif : menampilkan komponen khusus id operasi nya (+ Base Map jika dicentang)
      savedPoints = rawAllPoints.filter(p => {
        const opId = p.id_operasi ? parseInt(p.id_operasi, 10) : 0;
        if (opId === currentOperasiId) return true;
        if (includeBaseInActive && opId === 0) return true;
        return false;
      });
      savedPolygons = rawAllPolygons.filter(p => {
        const opId = p.id_operasi ? parseInt(p.id_operasi, 10) : 0;
        if (opId === currentOperasiId) return true;
        if (includeBaseInActive && opId === 0) return true;
        return false;
      });
      savedPatterns = rawAllPatterns.filter(p => {
        const opId = p.id_operasi ? parseInt(p.id_operasi, 10) : 0;
        if (opId === currentOperasiId) return true;
        if (includeBaseInActive && opId === 0) return true;
        return false;
      });
      savedRoutes = rawAllRoutes.filter(r => {
        const opId = r.id_operasi ? parseInt(r.id_operasi, 10) : 0;
        if (opId === currentOperasiId) return true;
        if (includeBaseInActive && opId === 0) return true;
        return false;
      });
    } else {
      // 2. Semua Komponen : Menampilkan seluruh komponen base map dan seluruh id operasi aktif (Operasi tertutup tidak tampil)
      const isBaseOrActive = (item) => {
        const opId = item.id_operasi ? parseInt(item.id_operasi, 10) : 0;
        return opId === 0 || activeOperasiIds.has(opId);
      };
      savedPoints = rawAllPoints.filter(isBaseOrActive);
      savedPolygons = rawAllPolygons.filter(isBaseOrActive);
      savedPatterns = rawAllPatterns.filter(isBaseOrActive);
      savedRoutes = rawAllRoutes.filter(isBaseOrActive);
    }

    updateSidebarBanner();
  }

  function updateSidebarBanner() {
    const banner = document.getElementById('gisSidebarOperasiBanner');
    if (banner) {
      if (filterMode === 'basemap' || filterMode === 'dasar') {
        banner.innerHTML = `
          <div class="px-3 py-2 bg-blue-500/10 border-b border-blue-500/20 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-extrabold text-blue-600 dark:text-blue-400">1. Base Map</span>
                <span class="text-slate-600 dark:text-slate-400 text-[11px] ml-1">(Komponen tanpa ID Operasi SAR)</span>
              </div>
            </div>
            <button type="button" class="btn-switch-mode-all text-[11px] font-bold text-slate-500 hover:text-orange-500 underline shrink-0 ml-2 cursor-pointer">Semua</button>
          </div>
        `;
      } else if (filterMode === 'operasi' && currentOperasi) {
        banner.innerHTML = `
          <div class="px-3 py-2 bg-orange-500/10 border-b border-orange-500/20 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-orange-500 animate-pulse shrink-0"></span>
              <div class="truncate">
                <span class="font-extrabold text-orange-600 dark:text-orange-400">3. Ops #${currentOperasi.id_operasi}</span>
                <span class="text-slate-600 dark:text-slate-300 ml-1 text-[11px]">${escapeHtml(currentOperasi.nama_operasi)}</span>
              </div>
            </div>
            <button type="button" class="btn-switch-mode-all text-[11px] font-bold text-slate-500 hover:text-red-500 underline shrink-0 ml-2 cursor-pointer" title="Kembali ke semua plotting">Lepas</button>
          </div>
        `;
      } else {
        banner.innerHTML = `
          <div class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-extrabold text-slate-700 dark:text-slate-200">2. Semua Komponen</span>
                <span class="text-slate-500 dark:text-slate-400 text-[11px] ml-1">(Base Map & Operasi Aktif)</span>
              </div>
            </div>
          </div>
        `;
      }
    }

    updateGisBadges();
  }

  function updateGisBadges() {
    const pBadge = document.getElementById('pointsBadgeCount');
    if (pBadge) pBadge.textContent = String(savedPoints ? savedPoints.length : 0);
    const polyBadge = document.getElementById('polygonsBadgeCount');
    if (polyBadge) polyBadge.textContent = String(savedPolygons ? savedPolygons.length : 0);
    const patBadge = document.getElementById('patternsBadgeCount');
    if (patBadge) patBadge.textContent = String(savedPatterns ? savedPatterns.length : 0);
    const routeBadge = document.getElementById('routesBadgeCount');
    if (routeBadge) routeBadge.textContent = String(savedRoutes ? savedRoutes.length : 0);
  }

  function notifyGisStorageUpdate() {
    try {
      localStorage.setItem('qr_sar_gis_last_update', JSON.stringify({
        ts: Date.now(),
        operasiId: currentOperasiId
      }));
    } catch (e) {
      // Ignore storage errors
    }
  }

  function updateRawPoint(payload, isDelete = false) {
    if (isDelete) {
      rawAllPoints = rawAllPoints.filter(p => String(p.id) !== String(payload.id));
    } else {
      const idx = rawAllPoints.findIndex(p => String(p.id) === String(payload.id));
      if (idx !== -1) {
        rawAllPoints[idx] = payload;
      } else {
        rawAllPoints.unshift(payload);
      }
    }
    applyActiveFilter();
    notifyGisStorageUpdate();
  }

  function updateRawPolygon(payload, isDelete = false) {
    if (isDelete) {
      rawAllPolygons = rawAllPolygons.filter(p => String(p.id) !== String(payload.id));
    } else {
      const idx = rawAllPolygons.findIndex(p => String(p.id) === String(payload.id));
      if (idx !== -1) {
        rawAllPolygons[idx] = payload;
      } else {
        rawAllPolygons.unshift(payload);
      }
    }
    applyActiveFilter();
    notifyGisStorageUpdate();
  }

  function updateRawPattern(payload, isDelete = false) {
    if (isDelete) {
      rawAllPatterns = rawAllPatterns.filter(p => String(p.id) !== String(payload.id));
    } else {
      const idx = rawAllPatterns.findIndex(p => String(p.id) === String(payload.id));
      if (idx !== -1) {
        rawAllPatterns[idx] = payload;
      } else {
        rawAllPatterns.unshift(payload);
      }
    }
    applyActiveFilter();
    notifyGisStorageUpdate();
  }

  function updateRawRoute(payload, isDelete = false) {
    if (isDelete) {
      rawAllRoutes = rawAllRoutes.filter(r => String(r.id) !== String(payload.id));
    } else {
      const idx = rawAllRoutes.findIndex(r => String(r.id) === String(payload.id));
      if (idx !== -1) {
        rawAllRoutes[idx] = payload;
      } else {
        rawAllRoutes.unshift(payload);
      }
    }
    applyActiveFilter();
    notifyGisStorageUpdate();
  }

  function reRenderAllLayers() {
    renderSavedPoints();
    renderSavedPolygons();
    renderSavedPatterns();
    renderSavedRoutes();
  }

  function updateUrlParams() {
    try {
      const url = new URL(window.location.href);
      if (filterMode === 'basemap' || filterMode === 'dasar') {
        url.searchParams.set('mode', 'basemap');
        url.searchParams.delete('id_operasi');
        url.searchParams.delete('id');
        url.searchParams.delete('include_base');
      } else if (filterMode === 'operasi' && currentOperasiId) {
        url.searchParams.set('id_operasi', String(currentOperasiId));
        url.searchParams.delete('mode');
        url.searchParams.delete('id');
        if (includeBaseInActive) {
          url.searchParams.set('include_base', '1');
        } else {
          url.searchParams.delete('include_base');
        }
      } else {
        url.searchParams.set('mode', 'all');
        url.searchParams.delete('id_operasi');
        url.searchParams.delete('id');
        url.searchParams.delete('include_base');
      }
      window.history.replaceState({}, '', url.toString());
    } catch (e) {
      // Ignore
    }
  }

  // Populate active filtered state on boot
  applyActiveFilter();

  let isPointsDraggableUnlocked = false;
  let isSidebarVisible = true;
  let activeTab = 'tab-points';
  let activeBasemap = 'hybrid';

  // Point Creator state
  let isCreatingPoint = false;
  let editingPointId = null;
  let pointIconType = 'lkp';
  let pointAlphabetChar = 'A';
  let pointCustomIconUrl = '';
  let pointDraftCoords = { lat: -4.015, lng: 122.585 };

  function normalizeIconUrl(url) {
    if (!url) return '';
    const u = String(url).trim();
    if (!u) return '';
    if (u.startsWith('/uploads/')) {
      return BASE_URL + u;
    }
    const idx = u.indexOf('/uploads/icons/');
    if (idx !== -1) {
      return BASE_URL + u.substring(idx);
    }
    return u;
  }

  // Global Dynamic Measurement Unit state (SSOT: 'NM' | 'KM' | 'M')
  let activeGlobalUnit = 'NM';
  try {
    const savedGlobalUnit = localStorage.getItem('qr_sar_gis_unit');
    if (savedGlobalUnit && ['NM', 'KM', 'M'].includes(savedGlobalUnit)) {
      activeGlobalUnit = savedGlobalUnit;
    }
  } catch (e) {}

  // Search Area (Polygon, Rectangle, Circle) state
  let isDrawingPolygon = false;
  let editingPolygonId = null;
  let activeSearchAreaShape = 'polygon'; // 'polygon' | 'rectangle' | 'circle'
  let polyCircleRadiusNm = 1.0;
  let polyCircleRadiusUnit = activeGlobalUnit; // 'NM' | 'KM' | 'M'
  let polyRectWidthNm = 2.0;
  let polyRectHeightNm = 2.0;
  let polyRectDimUnit = activeGlobalUnit; // 'NM' | 'KM' | 'M'
  let polyShapeCenter = null;
  let activePolygonPoints = [];
  let polyStrokeColor = '#ea580c';
  let polyStrokeWeight = 2.5;
  let polyFillColor = '#ea580c';
  let polyFillOpacity = 0.2;

  // Search Pattern state (Default: Parallel Track)
  let activePatternType = 'parallel_track';
  let activeEsarOrientation = 'horizontal'; // 'horizontal' | 'vertical'
  let activeEsarDirection = 'west_to_east'; // 'west_to_east' | 'east_to_west' | 'north_to_south' | 'south_to_north'
  let activeEsarArrowMarkers = [];
  let activeGeneratedWaypoints = [];
  let activeGeneratedDistanceNm = 0;
  let editingPatternId = null;

  // Measure Distance & Route Planning state (Jarak Lurus 2 Titik & Jalur Manual)
  let isMeasureModeActive = false;
  let measureModeType = 'straight'; // 'straight' or 'path'
  let measurePoints = [];
  let currentActiveRouteId = null;
  let activeDistanceUnit = activeGlobalUnit; // 'NM' | 'KM' | 'M'
  let isUndoingRoutePoint = false;
  let autoSaveRouteDebounceTimer = null;
  let vesselSpeedKnots = 25;
  let speedUnit = activeGlobalUnit === 'KM' ? 'kmh' : (activeGlobalUnit === 'M' ? 'ms' : 'knot');
  let patternSpacingUnit = activeGlobalUnit; // 'NM' | 'KM' | 'M'

  /**
   * Helper pemformat jarak dinamis mengacu pada activeGlobalUnit
   * Format:
   *  - KM: "X.XX km (Y.YY NM)"
   *  - NM: "X.XX NM (Y.YY km)"
   *  - M:  "X m (Y.YY NM)"
   */
  function formatDynamicDistance(distNm, distKm = null) {
    const numNm = parseFloat(distNm) || 0;
    if (distKm === null || distKm === undefined) distKm = numNm * 1.852;
    const numKm = parseFloat(distKm) || 0;
    const distM = numKm * 1000;
    if (activeGlobalUnit === 'KM') {
      return `${numKm.toFixed(2)} km (${numNm.toFixed(2)} NM)`;
    } else if (activeGlobalUnit === 'M') {
      return `${Math.round(distM).toLocaleString('id-ID')} m (${numNm.toFixed(2)} NM)`;
    }
    return `${numNm.toFixed(2)} NM (${numKm.toFixed(2)} km)`;
  }

  /**
   * Helper pemformat luas area dinamis mengacu pada activeGlobalUnit
   * Konversi presisi:
   *  1 NM = 1.852 km
   *  1 NM² = (1.852)² km² = 3.429904 km²
   *  1 km² = (1 / 3.429904) NM² ≈ 0.291553 NM²
   *  1 km² = 100 Ha = 1,000,000 m²
   */
  function formatDynamicArea(sqNm, sqKm = null) {
    const numNm2 = parseFloat(sqNm) || 0;
    if (sqKm === null || sqKm === undefined) sqKm = numNm2 * 3.429904;
    const numKm2 = parseFloat(sqKm) || 0;
    const hectares = numKm2 * 100;
    const sqM = numKm2 * 1000000;
    if (activeGlobalUnit === 'KM') {
      return {
        primary: `${numKm2.toFixed(2)} km²`,
        secondary: `${numNm2.toFixed(2)} NM² (${hectares.toFixed(1)} Ha)`,
        combined: `${numKm2.toFixed(2)} km² (${numNm2.toFixed(2)} NM²)`
      };
    } else if (activeGlobalUnit === 'M') {
      return {
        primary: `${Math.round(sqM).toLocaleString('id-ID')} m²`,
        secondary: `${numNm2.toFixed(2)} NM² (${hectares.toFixed(1)} Ha)`,
        combined: `${Math.round(sqM).toLocaleString('id-ID')} m² (${numNm2.toFixed(2)} NM²)`
      };
    }
    return {
      primary: `${numNm2.toFixed(2)} NM²`,
      secondary: `${numKm2.toFixed(2)} km² (${hectares.toFixed(1)} Ha)`,
      combined: `${numNm2.toFixed(2)} NM² (${numKm2.toFixed(2)} km²)`
    };
  }

  /**
   * Helper pemformat keliling perimeter dinamis mengacu pada activeGlobalUnit
   */
  function formatDynamicPerimeter(perimNm, perimKm = null) {
    const numNm = parseFloat(perimNm) || 0;
    if (perimKm === null || perimKm === undefined) perimKm = numNm * 1.852;
    const numKm = parseFloat(perimKm) || 0;
    const perimM = numKm * 1000;
    if (activeGlobalUnit === 'KM') {
      return {
        primary: `${numKm.toFixed(2)} km`,
        secondary: `${numNm.toFixed(2)} NM`,
        combined: `${numKm.toFixed(2)} km (${numNm.toFixed(2)} NM)`
      };
    } else if (activeGlobalUnit === 'M') {
      return {
        primary: `${Math.round(perimM).toLocaleString('id-ID')} m`,
        secondary: `${numNm.toFixed(2)} NM`,
        combined: `${Math.round(perimM).toLocaleString('id-ID')} m (${numNm.toFixed(2)} NM)`
      };
    }
    return {
      primary: `${numNm.toFixed(2)} NM`,
      secondary: `${numKm.toFixed(2)} km`,
      combined: `${numNm.toFixed(2)} NM (${numKm.toFixed(2)} km)`
    };
  }

  /**
   * Helper pemformat kecepatan dinamis
   */
  function formatDynamicSpeed(speedKnots, speedKmh = null) {
    const numKts = parseFloat(speedKnots) || 0;
    if (speedKmh === null || speedKmh === undefined) speedKmh = numKts * 1.852;
    const numKmh = parseFloat(speedKmh) || 0;
    const speedMs = (numKmh * 1000) / 3600;
    if (activeGlobalUnit === 'KM' || speedUnit === 'kmh') {
      return `${numKmh.toFixed(1)} km/jam (${numKts.toFixed(1)} Knot)`;
    } else if (activeGlobalUnit === 'M' || speedUnit === 'ms') {
      return `${speedMs.toFixed(1)} m/s (${numKts.toFixed(1)} Knot)`;
    }
    return `${numKts.toFixed(1)} Knot (${numKmh.toFixed(1)} km/jam)`;
  }

  /**
   * Sinkronisasi satuan dinamis menyeluruh di seluruh aplikasi GIS
   */
  function setGlobalMeasurementUnit(unit, skipRedraw = false) {
    if (!['NM', 'KM', 'M'].includes(unit)) unit = 'NM';
    activeGlobalUnit = unit;
    try {
      localStorage.setItem('qr_sar_gis_unit', unit);
    } catch (e) {}

    // 1. Update Master Pill Switcher UI
    document.querySelectorAll('.global-unit-pill-btn').forEach((btn) => {
      const bUnit = btn.getAttribute('data-global-unit');
      if (bUnit === unit) {
        btn.className = 'global-unit-pill-btn px-2 py-1 rounded-lg bg-orange-500 text-white shadow-xs transition font-black cursor-pointer';
      } else {
        btn.className = 'global-unit-pill-btn px-2 py-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition font-medium cursor-pointer';
      }
    });

    // 2. Synchronize Search Area Circle Radius Unit
    polyCircleRadiusUnit = unit;
    const circleRadUnitSel = document.getElementById('polyCircleRadiusUnit');
    const circleRadInput = document.getElementById('polyCircleRadiusNm');
    if (circleRadUnitSel) circleRadUnitSel.value = unit;
    if (circleRadInput) {
      if (unit === 'KM') circleRadInput.value = (polyCircleRadiusNm * 1.852).toFixed(2);
      else if (unit === 'M') circleRadInput.value = Math.round(polyCircleRadiusNm * 1852);
      else circleRadInput.value = polyCircleRadiusNm.toFixed(2);
    }

    // 3. Synchronize Search Area Rectangle Dimensions Unit
    polyRectDimUnit = unit;
    const rectDimUnitSel = document.getElementById('polyRectDimUnit');
    const rectWidthInput = document.getElementById('polyRectWidthNm');
    const rectHeightInput = document.getElementById('polyRectHeightNm');
    const wLabel = document.getElementById('polyRectWidthUnitLabel');
    const hLabel = document.getElementById('polyRectHeightUnitLabel');
    if (rectDimUnitSel) rectDimUnitSel.value = unit;
    if (wLabel) wLabel.textContent = unit;
    if (hLabel) hLabel.textContent = unit;
    if (rectWidthInput && rectHeightInput) {
      if (unit === 'KM') {
        rectWidthInput.value = (polyRectWidthNm * 1.852).toFixed(2);
        rectHeightInput.value = (polyRectHeightNm * 1.852).toFixed(2);
      } else if (unit === 'M') {
        rectWidthInput.value = Math.round(polyRectWidthNm * 1852);
        rectHeightInput.value = Math.round(polyRectHeightNm * 1852);
      } else {
        rectWidthInput.value = polyRectWidthNm.toFixed(2);
        rectHeightInput.value = polyRectHeightNm.toFixed(2);
      }
    }

    // 4. Synchronize Search Pattern Track Spacing Unit
    const prevSpacingUnit = patternSpacingUnit;
    patternSpacingUnit = unit;
    const spacingUnitSel = document.getElementById('patternSpacingUnit');
    const spacingInput = document.getElementById('patternTrackSpacing');
    if (spacingUnitSel) spacingUnitSel.value = unit;
    if (spacingInput) {
      const curVal = parseFloat(spacingInput.value) || 1.0;
      let valInNm = curVal;
      if (prevSpacingUnit === 'KM') valInNm = curVal / 1.852;
      else if (prevSpacingUnit === 'M') valInNm = curVal / 1852;
      let targetVal = valInNm;
      if (unit === 'KM') targetVal = valInNm * 1.852;
      else if (unit === 'M') targetVal = valInNm * 1852;
      spacingInput.value = (unit === 'M') ? Math.round(targetVal) : targetVal.toFixed(2);
    }

    // 5. Synchronize Measure Distance Unit
    activeDistanceUnit = unit;
    const distUnitSel = document.getElementById('measureDistanceUnit');
    if (distUnitSel) distUnitSel.value = unit;

    // 6. Synchronize Speed Unit: KM -> kmh, NM -> knot, M -> ms
    const targetSpeedUnit = unit === 'KM' ? 'kmh' : (unit === 'M' ? 'ms' : 'knot');
    const speedUnitSel = document.getElementById('measureSpeedUnit');
    const speedInput = document.getElementById('measureVesselSpeed');
    if (speedUnitSel) {
      const prevSUnit = speedUnitSel.value || speedUnit;
      speedUnitSel.value = targetSpeedUnit;
      speedUnit = targetSpeedUnit;
      if (speedInput) {
        const curSpeed = parseFloat(speedInput.value) || 25;
        let knots = curSpeed;
        if (prevSUnit === 'kmh') knots = curSpeed / 1.852;
        else if (prevSUnit === 'ms') knots = (curSpeed * 3.6) / 1.852;
        let newSpeed = knots;
        if (targetSpeedUnit === 'kmh') newSpeed = knots * 1.852;
        else if (targetSpeedUnit === 'ms') newSpeed = (knots * 1.852) / 3.6;
        speedInput.value = newSpeed.toFixed(1);
      }
    }

    if (!skipRedraw) {
      try { updateLivePolygonVisual(); } catch (e) {}
      try { renderSavedPolygons(); } catch (e) {}
      try { updatePatternAutoCalculation(); } catch (e) {}
      try {
        if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
          recalculateActivePatternDistance();
        }
      } catch (e) {}
      try { renderSavedPatterns(); } catch (e) {}
      try { recalcRouteMetrics(measurePoints); } catch (e) {}
      try { renderSavedRoutes(); } catch (e) {}
    }
  }

  function initGlobalUnitSwitcher() {
    document.querySelectorAll('.global-unit-pill-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const u = btn.getAttribute('data-global-unit');
        if (u) {
          setGlobalMeasurementUnit(u);
        }
      });
    });
    // Set initial active pill and sync dropdowns
    setGlobalMeasurementUnit(activeGlobalUnit, true);
  }

  // Hidden Layers IDs for eye toggles (Normalized string comparison)
  let hiddenPointIds = new Set();
  let hiddenPolygonIds = new Set();
  let hiddenPatternIds = new Set();
  let hiddenRouteIds = new Set();

  function isIdHidden(idSet, id) {
    if (!idSet || id === null || id === undefined) return false;
    return idSet.has(id) || idSet.has(String(id)) || (!isNaN(Number(id)) && idSet.has(Number(id)));
  }

  function toggleIdInSet(idSet, id) {
    if (!idSet || id === null || id === undefined) return;
    const strId = String(id);
    const numId = Number(id);
    if (isIdHidden(idSet, id)) {
      idSet.delete(id);
      idSet.delete(strId);
      if (!isNaN(numId)) idSet.delete(numId);
    } else {
      idSet.add(strId);
    }
  }

  // Leaflet Map & Layer References
  let map = null;
  let tileLayers = {};
  let pointMarkersMap = new Map();
  let draftPointMarker = null;
  let polygonOverlaysMap = new Map();
  let activeDrawingPolygonOverlay = null;
  let activeDrawingVertexMarkers = [];
  let patternPolylinesMap = new Map();
  let savedPatternArrowMarkersMap = new Map();
  let activePatternPolyline = null;
  let activePatternMarkers = [];
  let measurePolyline = null;
  let measureMarkers = [];
  let savedRoutePolylinesMap = new Map();
  let temporarySearchMarker = null;

  // Dynamic Sultra Landmarks & Maritime Presets loaded from Database (SarUnit table)
  function getVerifiedMaritimePresets() {
    const rawUnits = (window.SAR_INITIAL_GIS_DATA && Array.isArray(window.SAR_INITIAL_GIS_DATA.sarUnits))
      ? window.SAR_INITIAL_GIS_DATA.sarUnits
      : [];

    return rawUnits.map((u) => {
      const j = (u.jenis_unit || '').toUpperCase();
      let badge = 'SAR SULTRA';
      let type = 'Fasilitas Operasional SAR';
      if (j === 'KANSAR') { badge = 'UNIT BASARNAS'; type = 'Kantor SAR Utama'; }
      else if (j === 'POS_SAR') { badge = 'UNIT BASARNAS'; type = 'Pos SAR Resmi'; }
      else if (j === 'UNIT_SIAGA') { badge = 'UNIT BASARNAS'; type = 'Unit Siaga SAR'; }
      else if (j === 'DERMAGA') { badge = 'PANGKALAN ALUT'; type = 'Pangkalan Kapal SAR'; }
      else if (j === 'PELABUHAN') { badge = 'PELABUHAN'; type = 'Pelabuhan Maritim'; }
      else if (j === 'PERAIRAN') { badge = 'PERAIRAN'; type = 'Kawasan Perairan & Alur Laut'; }

      return {
        name: u.nama_unit || '',
        address: u.alamat || `${u.nama_unit}, ${u.wilayah || 'Sulawesi Tenggara'}`,
        lat: parseFloat(u.latitude),
        lng: parseFloat(u.longitude),
        badge: badge,
        type: type,
        dms: u.koordinat_dms || ''
      };
    });
  }


  // Comprehensive Preset Point Icons for SAR operations, incidents, places, assets, and tactical markers
  const PRESET_ICONS = [
    // --- KEJADIAN & BENCANA ---
    { type: 'lkp', label: 'LKP / Titik Kejadian', color: '#dc2626', emoji: '🚨', group: 'kejadian' },
    { type: 'orang_hilang', label: 'Orang Hilang / Survivor', color: '#ef4444', emoji: '👤', group: 'kejadian' },
    { type: 'tenggelam', label: 'Korban Tenggelam / Laka Air', color: '#0284c7', emoji: '🏊', group: 'kejadian' },
    { type: 'kecelakaan_kapal', label: 'Laka Kapal / Karam', color: '#b91c1c', emoji: '🚢', group: 'kejadian' },
    { type: 'perahu', label: 'Perahu Terbalik / Nelayan', color: '#06b6d4', emoji: '⛵', group: 'kejadian' },
    { type: 'kecelakaan_pesawat', label: 'Laka Pesawat / Jatuh', color: '#7c3aed', emoji: '✈️', group: 'kejadian' },
    { type: 'laka_darat', label: 'Laka Transportasi Darat', color: '#d97706', emoji: '🚗', group: 'kejadian' },
    { type: 'kmm', label: 'KMM / Bahaya Jiwa', color: '#ea580c', emoji: '🧗', group: 'kejadian' },
    { type: 'banjir', label: 'Banjir / Luapan Air', color: '#2563eb', emoji: '🌊', group: 'kejadian' },
    { type: 'longsor', label: 'Tanah Longsor', color: '#78350f', emoji: '⛰️', group: 'kejadian' },
    { type: 'gempa', label: 'Gempa / Runtuhan', color: '#854d0e', emoji: '🏚️', group: 'kejadian' },
    { type: 'kebakaran', label: 'Kebakaran / Karhutla', color: '#ea580c', emoji: '🔥', group: 'kejadian' },
    { type: 'gunung_api', label: 'Erupsi Gunung Berapi', color: '#991b1b', emoji: '🌋', group: 'kejadian' },
    { type: 'badai', label: 'Cuaca Buruk / Badai', color: '#475569', emoji: '🌪️', group: 'kejadian' },
    { type: 'hewan_buas', label: 'Serangan Satwa / Buaya', color: '#15803d', emoji: '🐊', group: 'kejadian' },
    { type: 'medis_gawat', label: 'Korban Medis Kritis', color: '#be123c', emoji: '🩸', group: 'kejadian' },
    { type: 'bencana', label: 'Bencana Alam / Hazard', color: '#d97706', emoji: '⚠️', group: 'kejadian' },

    // --- TEMPAT & FASILITAS ---
    { type: 'kantor', label: 'Kantor / Pangkalan SAR', color: '#1e3a8a', emoji: '🏢', group: 'tempat' },
    { type: 'pos_sar', label: 'Pos / Stasiun SAR', color: '#ea580c', emoji: '📻', group: 'tempat' },
    { type: 'posko_sar', label: 'Posko Taktis Lapangan', color: '#059669', emoji: '🏕️', group: 'tempat' },
    { type: 'tenda', label: 'Tenda Siaga / Pengungsi', color: '#0d9488', emoji: '⛺', group: 'tempat' },
    { type: 'rumah_sakit', label: 'Rumah Sakit / Faskes', color: '#e11d48', emoji: '🏥', group: 'tempat' },
    { type: 'pelabuhan', label: 'Pelabuhan / Dermaga', color: '#0284c7', emoji: '⚓', group: 'tempat' },
    { type: 'bandara', label: 'Bandara / Airstrip', color: '#4338ca', emoji: '🛫', group: 'tempat' },
    { type: 'helipad', label: 'Helipad / LZ Evakuasi', color: '#ca8a04', emoji: '🚁', group: 'tempat' },
    { type: 'mercusuar', label: 'Mercusuar / Tower Pantau', color: '#334155', emoji: '🗼', group: 'tempat' },
    { type: 'spbu_bbm', label: 'Depot BBM / Pengisian', color: '#d97706', emoji: '⛽', group: 'tempat' },
    { type: 'rumah', label: 'Rumah / Titik Hunian', color: '#16a34a', emoji: '🏠', group: 'tempat' },
    { type: 'pantai', label: 'Pantai / Area Pesisir', color: '#0891b2', emoji: '🏖️', group: 'tempat' },
    { type: 'pulau', label: 'Pulau / Terumbu Karang', color: '#059669', emoji: '🏝️', group: 'tempat' },
    { type: 'hutan', label: 'Hutan / Pegunungan', color: '#166534', emoji: '🌲', group: 'tempat' },
    { type: 'jembatan', label: 'Jembatan / Akses Utama', color: '#64748b', emoji: '🌉', group: 'tempat' },

    // --- ARMADA, ALUT & TIM SAR ---
    { type: 'kapal', label: 'Kapal SAR KN / KRI', color: '#0284c7', emoji: '🚢', group: 'alut' },
    { type: 'rib', label: 'RIB Basarnas', color: '#f97316', emoji: '🚤', group: 'alut' },
    { type: 'perahu_karet', label: 'Perahu Karet / Raft', color: '#dc2626', emoji: '🛶', group: 'alut' },
    { type: 'speedboat', label: 'Speedboat / Patroli', color: '#0ea5e9', emoji: '🛥️', group: 'alut' },
    { type: 'helikopter', label: 'Helikopter SAR', color: '#ea580c', emoji: '🚁', group: 'alut' },
    { type: 'pesawat', label: 'Pesawat Patroli Udara', color: '#7c3aed', emoji: '🛩️', group: 'alut' },
    { type: 'rescue_car', label: 'Rescue Car / D-Max', color: '#ea580c', emoji: '🚙', group: 'alut' },
    { type: 'rescue_truck', label: 'Truk Angkut / Truk SAR', color: '#475569', emoji: '🚛', group: 'alut' },
    { type: 'ambulans', label: 'Ambulans Evakuasi Medis', color: '#dc2626', emoji: '🚑', group: 'alut' },
    { type: 'motor_trail', label: 'Motor Trail Rescuer', color: '#c2410c', emoji: '🏍️', group: 'alut' },
    { type: 'penyelam', label: 'Tim Selam / Under-Water', color: '#0369a1', emoji: '🤿', group: 'alut' },
    { type: 'k9', label: 'Unit Anjing Pelacak K-9', color: '#854d0e', emoji: '🐕', group: 'alut' },
    { type: 'drone', label: 'Drone / UAV Thermal', color: '#6d28d9', emoji: '🛰️', group: 'alut' },
    { type: 'tim_rescuer', label: 'Tim Personel Rescuer', color: '#ea580c', emoji: '👷', group: 'alut' },

    // --- TAKTIS & NAVIGASI ---
    { type: 'titik_kumpul', label: 'Titik Kumpul / Assembly', color: '#16a34a', emoji: '🏁', group: 'taktis' },
    { type: 'entry_point', label: 'Entry Poin / Pintu Masuk', color: '#15803d', emoji: '🟢', group: 'taktis' },
    { type: 'exit_point', label: 'Exit Poin / Titik Keluar', color: '#b91c1c', emoji: '🔴', group: 'taktis' },
    { type: 'target', label: 'Objek Sasaran / Datum', color: '#dc2626', emoji: '🎯', group: 'taktis' },
    { type: 'bendera', label: 'Bendera Marker Taktis', color: '#ef4444', emoji: '🚩', group: 'taktis' },
    { type: 'rintangan', label: 'Bahaya / Rintangan Alur', color: '#eab308', emoji: '⚠️', group: 'taktis' },
    { type: 'logistik', label: 'Drop Zone Logistik / Air', color: '#b45309', emoji: '📦', group: 'taktis' },
    { type: 'komunikasi', label: 'Stasiun Radio / Repeater', color: '#0891b2', emoji: '📡', group: 'taktis' },
    { type: 'bintang', label: 'Titik Prioritas Utama', color: '#eab308', emoji: '⭐', group: 'taktis' },
    { type: 'pin_umum', label: 'Pin Marker Default', color: '#ea580c', emoji: '📍', group: 'taktis' }
  ];

  // Color Palette for Polygons and Routes
  const COLOR_PALETTE = [
    { label: 'Orange Basarnas', color: '#ea580c' },
    { label: 'Merah LKP', color: '#dc2626' },
    { label: 'Biru Laut Dalam', color: '#0284c7' },
    { label: 'Kuning Siaga', color: '#d97706' },
    { label: 'Hijau Search Area', color: '#16a34a' },
    { label: 'Ungu Taktis', color: '#9333ea' },
    { label: 'Teal Pesisir', color: '#0d9488' },
    { label: 'Abu-Abu Baja', color: '#475569' }
  ];

  // ==========================================
  // 2. GEODETIC & MATHEMATICAL UTILITIES
  // ==========================================

  function decimalToDms(lat, lng) {
    return window.QrSarGis.Geodesy.decimalToDms(lat, lng);
  }

  function parseCoordinatesString(text) {
    if (window.QrSarGis && window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.parseCoordinatesString === 'function') {
      return window.QrSarGis.Geodesy.parseCoordinatesString(text);
    }
    if (!text || typeof text !== 'string') return null;
    const clean = text.trim();
    if (!clean) return null;

    // Normalise Indonesian cardinal letters (LS -> S, LU -> N, BT -> E, BB -> W)
    const norm = clean
      .replace(/\bLS\b/gi, 'S')
      .replace(/\bLU\b/gi, 'N')
      .replace(/\bBT\b/gi, 'E')
      .replace(/\bBB\b/gi, 'W');

    // 1. Full DMS Regex: e.g. 04° 02' 16" S, 122° 29' 12" E or 04 02 16 S 122 29 12 E
    const dmsRegex = /(\d{1,2})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NSns])\s*[-–,\s/]+\s*(\d{1,3})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([EWew])/i;
    const mDms = norm.match(dmsRegex);
    if (mDms) {
      let lat = parseFloat(mDms[1]) + parseFloat(mDms[2]) / 60 + parseFloat(mDms[3]) / 3600;
      if (mDms[4].toUpperCase() === 'S') lat = -lat;
      let lng = parseFloat(mDms[5]) + parseFloat(mDms[6]) / 60 + parseFloat(mDms[7]) / 3600;
      if (mDms[8].toUpperCase() === 'W') lng = -lng;
      if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        return { lat, lng };
      }
    }

    // 2. DDM (Degrees Decimal Minutes) Regex: e.g. 04° 02.266' S, 122° 29.200' E
    const ddmRegex = /(\d{1,2})[°\s]+(\d{1,2}(?:\.\d+)?)[']?\s*([NSns])\s*[-–,\s/]+\s*(\d{1,3})[°\s]+(\d{1,2}(?:\.\d+)?)[']?\s*([EWew])/i;
    const mDdm = norm.match(ddmRegex);
    if (mDdm) {
      let lat = parseFloat(mDdm[1]) + parseFloat(mDdm[2]) / 60;
      if (mDdm[3].toUpperCase() === 'S') lat = -lat;
      let lng = parseFloat(mDdm[4]) + parseFloat(mDdm[5]) / 60;
      if (mDdm[6].toUpperCase() === 'W') lng = -lng;
      if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        return { lat, lng };
      }
    }

    // 3. Decimal with Cardinal direction: e.g. 4.0376 S, 122.4866 E
    const decCardRegex = /(\d{1,2}(?:\.\d+)?)\s*([NSns])\s*[-–,\s/]+\s*(\d{1,3}(?:\.\d+)?)\s*([EWew])/i;
    const mDecCard = norm.match(decCardRegex);
    if (mDecCard) {
      let lat = parseFloat(mDecCard[1]);
      if (mDecCard[2].toUpperCase() === 'S') lat = -lat;
      let lng = parseFloat(mDecCard[3]);
      if (mDecCard[4].toUpperCase() === 'W') lng = -lng;
      if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        return { lat, lng };
      }
    }

    // 4. Standard Signed Decimal: e.g. -4.037664, 122.486662 or -4.037664 122.486662
    const decRegex = /([-+]?\d{1,2}(?:\.\d+)?)\s*[,;\s]+\s*([-+]?\d{1,3}(?:\.\d+)?)/;
    const mDec = clean.match(decRegex);
    if (mDec) {
      const lat = parseFloat(mDec[1]);
      const lng = parseFloat(mDec[2]);
      if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        return { lat, lng };
      }
    }

    return null;
  }

  function calculateDistanceHaversine(lat1, lon1, lat2, lon2) {
    if (window.QrSarGis && window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.calculateDistanceHaversine === 'function') {
      return window.QrSarGis.Geodesy.calculateDistanceHaversine(lat1, lon1, lat2, lon2);
    }
    const R = 6371; // Earth radius in km
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLon = ((lon2 - lon1) * Math.PI) / 180;
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const km = R * c;
    const nm = km * 0.539957;

    const y = Math.sin(dLon) * Math.cos((lat2 * Math.PI) / 180);
    const x =
      Math.cos((lat1 * Math.PI) / 180) * Math.sin((lat2 * Math.PI) / 180) -
      Math.sin((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.cos(dLon);
    let brng = (Math.atan2(y, x) * 180) / Math.PI;
    brng = (brng + 360) % 360;

    const headings = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
    const headingIdx = Math.round(brng / 45) % 8;

    return {
      km: Math.round(km * 100) / 100,
      nm: Math.round(nm * 100) / 100,
      bearing: Math.round(brng),
      headingName: headings[headingIdx]
    };
  }

  function calculatePolygonSphericalArea(coordsOrPoly, shapeType, radiusNm, boxWidthNm, boxHeightNm) {
    if (window.QrSarGis && window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.calculatePolygonSphericalArea === 'function') {
      return window.QrSarGis.Geodesy.calculatePolygonSphericalArea(coordsOrPoly, shapeType, radiusNm, boxWidthNm, boxHeightNm);
    }
    let coords = coordsOrPoly;
    if (coordsOrPoly && typeof coordsOrPoly === 'object' && !Array.isArray(coordsOrPoly)) {
      if (coordsOrPoly.points) {
        coords = coordsOrPoly.points;
      }
      shapeType = shapeType || coordsOrPoly.shapeType;
      radiusNm = radiusNm || coordsOrPoly.radiusNm;
      boxWidthNm = boxWidthNm || coordsOrPoly.boxWidthNm;
      boxHeightNm = boxHeightNm || coordsOrPoly.boxHeightNm;
    }

    // 1. Circle Area: A = pi * r^2
    if (shapeType === 'circle' && radiusNm && Number(radiusNm) > 0) {
      const r = Number(radiusNm);
      const sqNm = Math.PI * Math.pow(r, 2);
      const sqKm = sqNm * 3.429904;
      const perimeterNm = 2 * Math.PI * r;
      const perimeterKm = perimeterNm * 1.852;
      return {
        sqKm: Math.round(sqKm * 100) / 100,
        sqNm: Math.round(sqNm * 100) / 100,
        hectares: Math.round(sqKm * 1000) / 10,
        perimeterKm: Math.round(perimeterKm * 100) / 100,
        perimeterNm: Math.round(perimeterNm * 100) / 100
      };
    }

    // 2. Box / Rectangle Area: A = width * height
    if ((shapeType === 'box' || shapeType === 'rectangle') && boxWidthNm && boxHeightNm && Number(boxWidthNm) > 0 && Number(boxHeightNm) > 0) {
      const w = Number(boxWidthNm);
      const h = Number(boxHeightNm);
      const sqNm = w * h;
      const sqKm = sqNm * 3.429904;
      const perimeterNm = 2 * (w + h);
      const perimeterKm = perimeterNm * 1.852;
      return {
        sqKm: Math.round(sqKm * 100) / 100,
        sqNm: Math.round(sqNm * 100) / 100,
        hectares: Math.round(sqKm * 1000) / 10,
        perimeterKm: Math.round(perimeterKm * 100) / 100,
        perimeterNm: Math.round(perimeterNm * 100) / 100
      };
    }

    if (!coords || coords.length < 3) {
      return { sqKm: 0, sqNm: 0, hectares: 0, perimeterKm: 0, perimeterNm: 0 };
    }

    // Geodesic / nautical area in NM² matching SAR/Basarnas standard and GisModel.php
    let sumLat = 0;
    coords.forEach(p => {
      const lat = p.lat !== undefined ? p.lat : (Array.isArray(p) ? p[0] : 0);
      sumLat += Number(lat || 0);
    });
    const meanLatRad = (sumLat / coords.length) * (Math.PI / 180);
    const cosMeanLat = Math.cos(meanLatRad);

    let areaSum = 0;
    const n = coords.length;
    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const pt1 = coords[i];
      const pt2 = coords[j];
      const lng1 = pt1.lng !== undefined ? pt1.lng : (Array.isArray(pt1) ? pt1[1] : 0);
      const lat1 = pt1.lat !== undefined ? pt1.lat : (Array.isArray(pt1) ? pt1[0] : 0);
      const lng2 = pt2.lng !== undefined ? pt2.lng : (Array.isArray(pt2) ? pt2[1] : 0);
      const lat2 = pt2.lat !== undefined ? pt2.lat : (Array.isArray(pt2) ? pt2[0] : 0);

      const x1 = Number(lng1 || 0) * 60.0 * cosMeanLat;
      const y1 = Number(lat1 || 0) * 60.0;
      const x2 = Number(lng2 || 0) * 60.0 * cosMeanLat;
      const y2 = Number(lat2 || 0) * 60.0;
      areaSum += (x1 * y2) - (x2 * y1);
    }
    const sqNm = Math.abs(areaSum) / 2.0;
    const sqKm = sqNm * 3.429904; // 1 NM = 1.852 km => 1 NM² = 3.429904 km²
    const hectares = sqKm * 100;

    let perimeterKm = 0;
    for (let i = 0; i < coords.length; i++) {
      const p1 = coords[i];
      const p2 = coords[(i + 1) % coords.length];
      const lat1 = p1.lat !== undefined ? p1.lat : (Array.isArray(p1) ? p1[0] : 0);
      const lng1 = p1.lng !== undefined ? p1.lng : (Array.isArray(p1) ? p1[1] : 0);
      const lat2 = p2.lat !== undefined ? p2.lat : (Array.isArray(p2) ? p2[0] : 0);
      const lng2 = p2.lng !== undefined ? p2.lng : (Array.isArray(p2) ? p2[1] : 0);
      perimeterKm += calculateDistanceHaversine(lat1, lng1, lat2, lng2).km;
    }
    const perimeterNm = perimeterKm * 0.539957;

    return {
      sqKm: Math.round(sqKm * 100) / 100,
      sqNm: Math.round(sqNm * 100) / 100,
      hectares: Math.round(hectares * 10) / 10,
      perimeterKm: Math.round(perimeterKm * 100) / 100,
      perimeterNm: Math.round(perimeterNm * 100) / 100
    };
  }

  function formatDurationIndonesian(minutes) {
    const rounded = Math.max(1, Math.round(minutes));
    const hrs = Math.floor(rounded / 60);
    const mins = rounded % 60;
    if (hrs > 0 && mins > 0) return `${hrs} Jam ${mins} Menit`;
    if (hrs > 0) return `${hrs} Jam`;
    return `${mins} Menit`;
  }

  // ==========================================
  // 3. SVG MARKER HTML GENERATORS
  // ==========================================

  function createPointSvgHtml(type, color, isEditing, customUrl, labelNumber) {
    if (window.QrSarGis && window.QrSarGis.Markers && typeof window.QrSarGis.Markers.createPointSvgHtml === 'function') {
      return window.QrSarGis.Markers.createPointSvgHtml(type, color, isEditing, customUrl, labelNumber);
    }
    const width = isEditing ? 46 : 38;
    const height = isEditing ? 54 : 46;
    const cx = width / 2;

    let innerContent = '';

    const normalizedUrl = normalizeIconUrl(customUrl);
    if (normalizedUrl) {
      const safeUrl = escapeHtml(normalizedUrl);
      innerContent = `
        <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
        <image href="${safeUrl}" xlink:href="${safeUrl}" x="${cx - 10}" y="6" width="20" height="20" preserveAspectRatio="xMidYMid slice" />
      `;
    } else if (type === 'alphabet') {
      const char = (labelNumber || 'A').toUpperCase();
      innerContent = `
        <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
        <text x="${cx}" y="20.5" font-family="Arial, system-ui, sans-serif" font-size="12" font-weight="900" fill="${color}" text-anchor="middle">${escapeHtml(char)}</text>
      `;
    } else if (type === 'lkp' || type === 'target') {
      innerContent = `
        <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
        <circle cx="${cx}" cy="16" r="9" fill="${color}" />
        <circle cx="${cx}" cy="16" r="4" fill="#ffffff" />
        <line x1="${cx - 10}" y1="16" x2="${cx + 10}" y2="16" stroke="#ffffff" stroke-width="1.8" />
        <line x1="${cx}" y1="6" x2="${cx}" y2="26" stroke="#ffffff" stroke-width="1.8" />
      `;
    } else {
      const preset = PRESET_ICONS.find((p) => p.type === type);
      const emoji = preset ? preset.emoji : '📍';
      innerContent = `
        <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
        <text x="${cx}" y="16.5" font-family="'Segoe UI Emoji', 'Apple Color Emoji', 'Noto Color Emoji', Arial, sans-serif" font-size="12" text-anchor="middle" dominant-baseline="central">${emoji}</text>
      `;
    }

    const haloRing = isEditing
      ? `<circle cx="${cx}" cy="16" r="17" fill="none" stroke="#facc15" stroke-width="3" stroke-dasharray="3,3" />`
      : (type === 'lkp' || type === 'target' || type === 'medis_gawat' || type === 'bencana')
        ? `<circle cx="${cx}" cy="16" r="18" fill="none" stroke="${color}" stroke-width="1.5" stroke-opacity="0.6">
          <animate attributeName="r" values="14;22;14" dur="2s" repeatCount="indefinite" />
          <animate attributeName="stroke-opacity" values="0.8;0.1;0.8" dur="2s" repeatCount="indefinite" />
         </circle>`
        : '';

    return `
      <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="display:block;">
        <defs>
          <filter id="shadow_${Math.random().toString(36).substring(2, 6)}" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000000" flood-opacity="0.45" />
          </filter>
        </defs>
        <g>
          ${haloRing}
          <path d="M${cx} ${height - 4} C${cx - 2} ${height - 10}, ${cx - 14} 24, ${cx - 14} 16 A14 14 0 1 1 ${cx + 14} 16 C${cx + 14} 24, ${cx + 2} ${height - 10}, ${cx} ${height - 4} Z" fill="${color}" stroke="#ffffff" stroke-width="2" />
          ${innerContent}
        </g>
      </svg>
    `;
  }

  function createMarkerHtml(point, isEditing = false) {
    if (window.QrSarGis && window.QrSarGis.Markers && typeof window.QrSarGis.Markers.createMarkerHtml === 'function') {
      return window.QrSarGis.Markers.createMarkerHtml(point, isEditing);
    }
    const pType = point.pointType || point.type || 'pin_umum';
    const preset = PRESET_ICONS.find((p) => p.type === pType);
    const color = point.customColor || (preset ? preset.color : '#ea580c');
    const label = point.label !== undefined && point.label !== null ? String(point.label).trim() : '';
    const width = isEditing ? 46 : 38;
    const height = isEditing ? 54 : 46;
    const svg = createPointSvgHtml(pType, color, isEditing, point.customIconUrl, point.alphabetChar);

    // Requirement 1: If label is empty, only show the point without any name above it
    const labelBadge = label
      ? `
        <div style="
          position: absolute;
          bottom: 100%;
          left: 50%;
          transform: translateX(-50%);
          margin-bottom: 3px;
          background: rgba(15, 23, 42, 0.95);
          color: #f8fafc;
          font-size: 11px;
          font-weight: 800;
          padding: 2px 7px;
          border-radius: 6px;
          border: 1px solid rgba(255, 255, 255, 0.4);
          box-shadow: 0 2px 6px rgba(0,0,0,0.6);
          white-space: nowrap;
          pointer-events: none;
          max-width: 180px;
          overflow: hidden;
          text-overflow: ellipsis;
          z-index: 10;
        ">${escapeHtml(label)}</div>
      `
      : '';

    return `
      <div class="sar-marker-root" style="position: relative; width: ${width}px; height: ${height}px; user-select: none; pointer-events: auto;">
        ${labelBadge}
        ${svg}
      </div>
    `;
  }

  function createVertexBadgeHtml(letter, color = '#ea580c', isDragging = false) {
    if (window.QrSarGis && window.QrSarGis.Markers && typeof window.QrSarGis.Markers.createVertexBadgeHtml === 'function') {
      return window.QrSarGis.Markers.createVertexBadgeHtml(letter, color, isDragging);
    }
    const size = isDragging ? 36 : 30;
    return `
      <div style="display: block; width: ${size}px; height: ${size}px; user-select: none;">
        <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
          <circle cx="${size / 2}" cy="${size / 2}" r="${size / 2 - 2}" fill="${color}" stroke="#ffffff" stroke-width="2.5" />
          <text x="${size / 2}" y="${size / 2 + 4.5}" font-family="Arial, sans-serif" font-size="12" font-weight="900" fill="#ffffff" text-anchor="middle">${letter}</text>
        </svg>
      </div>
    `;
  }

  function createPatternCornerHtml(type, color = '#0284c7', customLetter = '') {
    if (window.QrSarGis && window.QrSarGis.Markers && typeof window.QrSarGis.Markers.createPatternCornerHtml === 'function') {
      return window.QrSarGis.Markers.createPatternCornerHtml(type, color, customLetter);
    }
    const isStart = type === 'start';
    const isEnd = type === 'end';
    const fillColor = isStart ? '#16a34a' : isEnd ? '#dc2626' : color;
    const letter = customLetter ? customLetter : (isStart ? 'S' : isEnd ? 'E' : '');
    const isLong = letter.length >= 2;
    const size = isLong ? 26 : 24;
    const center = size / 2;
    const radius = isLong ? 11 : 10;
    const fontSize = isLong ? 9 : 11;
    const textY = isLong ? (center + 3.5) : 16;

    return `
      <div style="display: block; width: ${size}px; height: ${size}px; user-select: none; cursor: grab;" class="sar-pat-corner">
        <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
          <circle cx="${center}" cy="${center}" r="${radius}" fill="${fillColor}" stroke="#ffffff" stroke-width="2" />
          ${letter ? `<text x="${center}" y="${textY}" font-family="Arial, sans-serif" font-size="${fontSize}" font-weight="900" fill="#ffffff" text-anchor="middle">${letter}</text>` : `<circle cx="${center}" cy="${center}" r="4" fill="#ffffff" />`}
        </svg>
      </div>
    `;
  }

  // ==========================================
  // 4. MAP INITIALIZATION & TILE LAYERS
  // ==========================================

  function initLeafletMap() {
    const mapElement = document.getElementById('gisLeafletMap');
    if (!mapElement) return;

    // Kansar Kendari center with maxZoom 22
    map = L.map('gisLeafletMap', {
      center: [-4.037694, 122.486694],
      zoom: 11,
      minZoom: 4,
      maxZoom: 22,
      zoomSnap: 0.5,
      zoomDelta: 0.5,
      zoomControl: false,
      attributionControl: false
    });

    // Zoom control on bottom right
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Modern High-Resolution Basemaps with maxNativeZoom Scaling (No Blank on Zoom) - 100% Bebas API Key
    // 1. Google Maps Hybrid (High-Res Satellite + Roads + Labels)
    tileLayers.google_hybrid = L.tileLayer(
      'https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
      { maxNativeZoom: 20, maxZoom: 22, subdomains: ['0', '1', '2', '3'], attribution: 'Google Hybrid Satelit' }
    );

    // 2. Google Satellite High-Res (Identik dengan Peta Taktis Satelit pada Detail Operasi)
    tileLayers.satellite = L.tileLayer(
      'https://mt{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}',
      { maxNativeZoom: 20, maxZoom: 22, subdomains: ['0', '1', '2', '3'], attribution: 'Google Satellite' }
    );

    // 3. CartoDB Labels overlay (opsional)
    tileLayers.labels = L.tileLayer(
      'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png',
      { subdomains: 'abcd', maxNativeZoom: 19, maxZoom: 22, attribution: 'CartoDB Labels' }
    );

    // 4. Google Roadmap for crisp vector street networks
    tileLayers.roadmap = L.tileLayer(
      'https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
      { maxNativeZoom: 20, maxZoom: 22, subdomains: ['0', '1', '2', '3'], attribution: 'Google Maps Roadmap' }
    );

    // 5. Google Terrain for Relief & Contours with maxNativeZoom 20
    tileLayers.terrain = L.tileLayer(
      'https://mt{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}',
      { maxNativeZoom: 20, maxZoom: 22, subdomains: ['0', '1', '2', '3'], attribution: 'Google Terrain' }
    );

    // 6. OpenStreetMap Standard for open maritime & land navigation
    tileLayers.osm = L.tileLayer(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      { maxNativeZoom: 19, maxZoom: 22, subdomains: ['a', 'b', 'c'], attribution: '© OpenStreetMap contributors' }
    );

    // Set Default: Hybrid (Google High-Res Satellite + Navigation Labels)
    setBasemapLayer('hybrid');

    // Map Event Listeners
    map.on('mousemove', onMapMouseMove);
    map.on('click', onMapClick);
    map.on('zoomend', () => {
      const zoomEl = document.getElementById('hudZoomLevel');
      if (zoomEl) zoomEl.textContent = String(map.getZoom());
    });

    // Offline / Blank Spot Connectivity Monitoring
    const offlineIndicator = document.getElementById('gisOfflineIndicator');
    function checkConnectivity(forcedOffline = false) {
      if (!offlineIndicator) return;
      if (forcedOffline || !navigator.onLine) {
        offlineIndicator.classList.remove('hidden');
      } else {
        offlineIndicator.classList.add('hidden');
      }
    }

    window.addEventListener('online', () => checkConnectivity(false));
    window.addEventListener('offline', () => checkConnectivity(true));

    // Detect tile load failures in maritime blank spots
    let consecutiveTileErrors = 0;
    Object.values(tileLayers).forEach((layer) => {
      layer.on('tileerror', () => {
        consecutiveTileErrors++;
        if (consecutiveTileErrors >= 4) {
          checkConnectivity(true);
        }
      });
      layer.on('tileload', () => {
        if (consecutiveTileErrors > 0) {
          consecutiveTileErrors = Math.max(0, consecutiveTileErrors - 1);
          if (consecutiveTileErrors === 0 && navigator.onLine) {
            checkConnectivity(false);
          }
        }
      });
    });

    checkConnectivity();

    setTimeout(() => {
      map.invalidateSize();
    }, 200);

    // Initial render of all overlays
    renderSavedPoints();
    renderSavedPolygons();
    renderSavedPatterns();
    renderSavedRoutes();
  }

  function setBasemapLayer(type) {
    activeBasemap = type;
    Object.values(tileLayers).forEach((layer) => {
      if (map.hasLayer(layer)) map.removeLayer(layer);
    });

    if (type === 'hybrid' || type === 'google_hybrid') {
      map.addLayer(tileLayers.google_hybrid);
    } else if (type === 'satellite') {
      map.addLayer(tileLayers.satellite);
    } else if (type === 'roadmap') {
      map.addLayer(tileLayers.roadmap);
    } else if (type === 'terrain') {
      map.addLayer(tileLayers.terrain);
    } else if (type === 'osm') {
      if (tileLayers.osm) map.addLayer(tileLayers.osm);
    }

    // Update Pills in UI
    document.querySelectorAll('.basemap-pill-btn').forEach((btn) => {
      const layer = btn.getAttribute('data-layer');
      if (layer === type) {
        btn.className = 'basemap-pill-btn px-2.5 py-1 rounded-lg bg-orange-500 text-white shadow-sm transition font-bold';
      } else {
        btn.className = 'basemap-pill-btn px-2.5 py-1 rounded-lg text-slate-400 hover:text-white transition font-medium';
      }
    });
  }

  function onMapMouseMove(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;
    const hudDec = document.getElementById('hudCoordsDecimal');
    const hudDms = document.getElementById('hudCoordsDms');

    if (hudDec) hudDec.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
    if (hudDms) hudDms.textContent = decimalToDms(lat, lng);
  }

  function onMapClick(e) {
    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    // 1. If Point Creator is open, update coordinates
    if (isCreatingPoint) {
      updatePointDraftCoords(lat, lng);
      return;
    }

    // 2. If Search Area (Polygon/Rectangle/Circle) Drawing is active
    if (isDrawingPolygon) {
      handleSearchAreaMapClick(lat, lng);
      return;
    }

    // 3. If Measure Distance / Routing is active
    if (isMeasureModeActive) {
      addMeasurePoint(lat, lng);
      return;
    }
  }

  // ==========================================
  // 5. LOCATION SEARCH BAR IMPLEMENTATION
  // ==========================================

  // ==========================================
  // 5. LOCATION SEARCH BAR (GOOGLE MAPS GEOLOCATION & GPS ENGINE)
  // ==========================================

  let currentSelectedSearchResult = null;
  let currentSearchResults = [];

  function initLocationSearchBar() {
    const searchInput = document.getElementById('locationSearchInput');
    const clearBtn = document.getElementById('btnClearLocationSearch');
    const popover = document.getElementById('locationSearchResultsPopover');
    const resultsList = document.getElementById('locationSearchResultsList');

    if (!searchInput) return;

    let searchDebounceTimer = null;

    searchInput.addEventListener('input', (e) => {
      const rawQ = e.target.value.trim();
      if (!rawQ) {
        clearBtn.classList.add('hidden');
        popover.classList.add('hidden');
        currentSearchResults = [];
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        return;
      }

      clearBtn.classList.remove('hidden');

      // 1. Instant Local Matching: Coordinates (DMS or DD) & Local Verified Landmarks
      const parsedCoords = parseCoordinatesString(rawQ);
      const instantResults = [];

      if (parsedCoords) {
        instantResults.push({
          name: `Titik Koordinat: ${parsedCoords.lat.toFixed(5)}, ${parsedCoords.lng.toFixed(5)}`,
          address: decimalToDms(parsedCoords.lat, parsedCoords.lng),
          lat: parsedCoords.lat,
          lng: parsedCoords.lng,
          type: 'Koordinat GPS',
          badge: 'GPS DD',
          icon: 'crosshair',
          dms: decimalToDms(parsedCoords.lat, parsedCoords.lng),
          isCoordinate: true
        });
      }

      // Check saved database points from current session
      const terms = rawQ.toLowerCase().split(/\s+/).filter(Boolean);
      if (Array.isArray(rawAllPoints) && terms.length > 0) {
        rawAllPoints.forEach((pt) => {
          const fullHaystack = `${pt.label || ''} ${pt.description || ''} ${pt.category || ''}`.toLowerCase();
          const matchesAll = terms.every(t => fullHaystack.includes(t));
          if (matchesAll && pt.lat && pt.lng) {
            instantResults.push({
              name: pt.label || 'Titik Taktis',
              address: pt.description || `${pt.category || 'Titik Taktis'} (${pt.id_operasi ? 'Operasi SAR' : 'Base Map'})`,
              lat: parseFloat(pt.lat),
              lng: parseFloat(pt.lng),
              badge: pt.id_operasi ? 'OPERASI SAR' : 'BASE MAP',
              type: pt.category || 'Titik Taktis',
              dms: decimalToDms(parseFloat(pt.lat), parseFloat(pt.lng)),
              icon: 'map-pin'
            });
          }
        });
      }

      currentSearchResults = instantResults;
      if (instantResults.length > 0) {
        renderSearchResults(instantResults);
      }

      // 2. Comprehensive Remote Geocoding (Debounced like Google Maps)
      if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
      searchDebounceTimer = setTimeout(async () => {
        if (rawQ.length < 2) return;

        try {
          // Primary query to our unified backend geocoding endpoint
          const res = await fetch(`${BASE_URL}/api/geocode?q=${encodeURIComponent(rawQ)}`);
          if (res.ok) {
            const data = await res.json();
            if (data.success && Array.isArray(data.data) && data.data.length > 0) {
              const combined = [...instantResults];
              data.data.forEach((remoteItem) => {
                const isDup = combined.some(c => Math.abs(c.lat - remoteItem.lat) < 0.0008 && Math.abs(c.lng - remoteItem.lng) < 0.0008);
                if (!isDup) {
                  combined.push(remoteItem);
                }
              });
              currentSearchResults = combined;
              renderSearchResults(combined);
              return;
            }
          }
        } catch (err) {
          // Fallback to direct ArcGIS World Geocoding if server unreachable
        }

        // Direct Client Fallback to ArcGIS World Geocoding Service (100% CORS & Global Coverage)
        try {
          const arcUrl = `https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?f=json&singleLine=${encodeURIComponent(rawQ)}&countryCode=IDN&maxLocations=8`;
          const arcRes = await fetch(arcUrl);
          if (arcRes.ok) {
            const arcData = await arcRes.json();
            if (arcData && Array.isArray(arcData.candidates) && arcData.candidates.length > 0) {
              const combined = [...instantResults];
              arcData.candidates.forEach((cand) => {
                const lat = parseFloat(cand.location.y);
                const lng = parseFloat(cand.location.x);
                if (!lat || !lng) return;

                const isDup = combined.some(c => Math.abs(c.lat - lat) < 0.0008 && Math.abs(c.lng - lng) < 0.0008);
                if (!isDup) {
                  const parts = (cand.address || '').split(',');
                  const name = parts[0] ? parts[0].trim() : cand.address;
                  const sub = parts.slice(1).join(', ').trim() || cand.address;
                  combined.push({
                    name: name,
                    address: sub,
                    lat: lat,
                    lng: lng,
                    type: 'Lokasi Geografis',
                    badge: 'MAPS',
                    dms: decimalToDms(lat, lng)
                  });
                }
              });
              currentSearchResults = combined;
              renderSearchResults(combined);
            }
          }
        } catch (fallbackErr) { }
      }, 250);
    });

    // Support Keyboard Enter & Escape
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (currentSearchResults && currentSearchResults.length > 0) {
          selectSearchResult(currentSearchResults[0]);
        }
      } else if (e.key === 'Escape') {
        popover.classList.add('hidden');
      }
    });

    clearBtn.addEventListener('click', () => {
      searchInput.value = '';
      clearBtn.classList.add('hidden');
      popover.classList.add('hidden');
      currentSearchResults = [];
      if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
    });

    document.addEventListener('click', (e) => {
      if (!e.target.closest('#locationSearchBarWrapper')) {
        popover.classList.add('hidden');
      }
    });

    // Close floating Google Maps card
    const closeBannerBtn = document.getElementById('btnCloseSearchBanner');
    if (closeBannerBtn) {
      closeBannerBtn.addEventListener('click', () => {
        document.getElementById('floatingSearchBanner').classList.add('hidden');
        if (temporarySearchMarker) {
          map.removeLayer(temporarySearchMarker);
          temporarySearchMarker = null;
        }
        currentSelectedSearchResult = null;
      });
    }

    // Quick Action 1: Plot Titik SAR
    const convertBtn = document.getElementById('btnConvertSearchToPoint');
    if (convertBtn) {
      convertBtn.addEventListener('click', () => {
        if (!currentSelectedSearchResult) return;
        openPointCreator({
          label: currentSelectedSearchResult.name,
          lat: currentSelectedSearchResult.lat,
          lng: currentSelectedSearchResult.lng,
          category: 'Tanpa Kategori',
          pointType: 'pin_umum'
        });
        document.getElementById('floatingSearchBanner').classList.add('hidden');
      });
    }

    // Quick Action 2: Set as LKP (Titik Kejadian)
    const setLkpBtn = document.getElementById('btnSetSearchAsLkp');
    if (setLkpBtn) {
      setLkpBtn.addEventListener('click', () => {
        if (!currentSelectedSearchResult) return;
        openPointCreator({
          label: 'LKP: ' + currentSelectedSearchResult.name,
          lat: currentSelectedSearchResult.lat,
          lng: currentSelectedSearchResult.lng,
          category: 'LKP',
          pointType: 'lkp'
        });
        document.getElementById('floatingSearchBanner').classList.add('hidden');
      });
    }

    // Quick Action 3: Salin Koordinat
    const copyCoordsBtn = document.getElementById('btnCopySearchCoords');
    if (copyCoordsBtn) {
      copyCoordsBtn.addEventListener('click', () => {
        if (!currentSelectedSearchResult) return;
        const dms = currentSelectedSearchResult.dms || decimalToDms(currentSelectedSearchResult.lat, currentSelectedSearchResult.lng);
        const text = `${currentSelectedSearchResult.name}\nKoordinat: ${dms}\nDecimal: ${currentSelectedSearchResult.lat.toFixed(6)}, ${currentSelectedSearchResult.lng.toFixed(6)}`;
        if (navigator.clipboard) {
          navigator.clipboard.writeText(text);
        }
        if (typeof showToast === 'function') {
          showToast(`Koordinat ${currentSelectedSearchResult.name} disalin ke clipboard!`, 'success');
        }
      });
    }

    // Quick Action 4: Ukur Jarak
    const measureBtn = document.getElementById('btnMeasureFromSearch');
    if (measureBtn) {
      measureBtn.addEventListener('click', () => {
        if (!currentSelectedSearchResult) return;
        const toggleSwitch = document.getElementById('toggleMeasureMode');
        if (toggleSwitch && !toggleSwitch.checked) {
          toggleSwitch.checked = true;
          toggleSwitch.dispatchEvent(new Event('change'));
        }
        isMeasureModeActive = true;
        addMeasurePoint(currentSelectedSearchResult.lat, currentSelectedSearchResult.lng);
        if (typeof showToast === 'function') {
          showToast(`Pengukuran jarak dimulai dari: ${currentSelectedSearchResult.name}`, 'info');
        }
        document.getElementById('floatingSearchBanner').classList.add('hidden');
      });
    }
  }

  function renderSearchResults(results) {
    const popover = document.getElementById('locationSearchResultsPopover');
    const resultsList = document.getElementById('locationSearchResultsList');

    if (results.length === 0) {
      resultsList.innerHTML = `<div class="p-3 text-slate-400 text-center italic text-xs">Tidak ditemukan lokasi / koordinat yang sesuai. Coba nama kota, desa, atau jalan lain.</div>`;
      popover.classList.remove('hidden');
      return;
    }

    resultsList.innerHTML = results
      .slice(0, 10)
      .map((r, idx) => {
        const emoji = (function () {
          const t = (r.type || '').toLowerCase();
          const b = (r.badge || '').toLowerCase();
          if (b.includes('gps') || t.includes('koordinat')) return '📍';
          if (b.includes('sar') || t.includes('basarnas')) return '🛡️';
          if (t.includes('pelabuhan') || t.includes('port') || t.includes('harbor')) return '⚓';
          if (t.includes('bandara') || t.includes('airport')) return '✈️';
          if (t.includes('pantai') || t.includes('beach') || t.includes('pulau') || t.includes('island') || t.includes('gunung')) return '⛰️';
          if (t.includes('jalan') || t.includes('street')) return '🛣️';
          if (t.includes('village') || t.includes('desa') || t.includes('kelurahan')) return '🏘️';
          if (t.includes('city') || t.includes('kota') || t.includes('kabupaten')) return '🏢';
          return '📍';
        })();

        return `
          <div class="p-2.5 hover:bg-slate-100 dark:hover:bg-slate-800/80 cursor-pointer transition flex items-start justify-between gap-2.5 search-result-item" data-idx="${idx}">
            <div class="w-7 h-7 rounded-lg bg-orange-500/10 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0 text-sm mt-0.5">
              ${emoji}
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-slate-900 dark:text-white font-bold text-xs truncate">${escapeHtml(r.name)}</span>
                ${r.badge ? `<span class="text-[11px] px-1.5 py-0.2 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold border border-orange-500/30 shrink-0">${escapeHtml(r.badge)}</span>` : ''}
              </div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">${escapeHtml(r.address || '')}</div>
              <div class="text-[11px] font-mono text-slate-400 dark:text-slate-500 mt-0.5">${escapeHtml(r.dms || decimalToDms(r.lat, r.lng))}</div>
            </div>
            <span class="text-xs text-orange-500 dark:text-orange-400 shrink-0 font-bold self-center">➔</span>
          </div>
        `;
      })
      .join('');

    popover.classList.remove('hidden');

    resultsList.querySelectorAll('.search-result-item').forEach((item) => {
      item.addEventListener('click', () => {
        const idx = parseInt(item.getAttribute('data-idx'), 10);
        selectSearchResult(results[idx]);
      });
    });
  }

  function selectSearchResult(result) {
    if (!result || typeof result.lat !== 'number' || typeof result.lng !== 'number') return;
    currentSelectedSearchResult = result;

    document.getElementById('locationSearchResultsPopover').classList.add('hidden');

    // Smart zoom depending on place type
    let zoomLevel = 14;
    const typeLower = (result.type || '').toLowerCase();
    if (typeLower.includes('country') || typeLower.includes('negara')) zoomLevel = 6;
    else if (typeLower.includes('region') || typeLower.includes('provinsi')) zoomLevel = 9;
    else if (typeLower.includes('city') || typeLower.includes('kabupaten') || typeLower.includes('kota')) zoomLevel = 12;
    else if (typeLower.includes('subregion') || typeLower.includes('kecamatan')) zoomLevel = 13;
    else if (typeLower.includes('village') || typeLower.includes('desa') || typeLower.includes('kelurahan')) zoomLevel = 15;
    else if (typeLower.includes('street') || typeLower.includes('jalan') || typeLower.includes('poi') || typeLower.includes('pelabuhan') || result.isCoordinate) zoomLevel = 16;

    // Smooth Google Maps Flight
    map.flyTo([result.lat, result.lng], zoomLevel, { duration: 1.4, easeLinearity: 0.25 });

    // Show Google Maps animated pulse pin on map
    if (temporarySearchMarker) map.removeLayer(temporarySearchMarker);

    const pinHtml = `
      <div class="gm-pin-container" style="position: relative; width: 34px; height: 42px;">
        <div class="gm-pulse-ring"></div>
        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42" fill="none">
          <path d="M17 0C7.611 0 0 7.611 0 17c0 12.75 17 25 17 25s17-12.25 17-25C34 7.611 26.389 0 17 0z" fill="#ea580c"/>
          <circle cx="17" cy="17" r="7" fill="#ffffff"/>
          <circle cx="17" cy="17" r="3.5" fill="#ea580c"/>
        </svg>
      </div>
    `;

    temporarySearchMarker = L.marker([result.lat, result.lng], {
      icon: L.divIcon({ html: pinHtml, className: 'gm-temp-search-marker', iconSize: [34, 42], iconAnchor: [17, 42], popupAnchor: [0, -42] })
    }).addTo(map);

    // Populate Google Maps Place Card
    const banner = document.getElementById('floatingSearchBanner');
    const titleEl = document.getElementById('searchBannerTitle');
    const badgeEl = document.getElementById('searchBannerBadge');
    const addressEl = document.getElementById('searchBannerAddress');
    const coordsEl = document.getElementById('searchBannerCoords');
    const decEl = document.getElementById('searchBannerDecimal');
    const iconEl = document.getElementById('searchBannerIcon');

    if (titleEl) titleEl.textContent = result.name;
    if (badgeEl) badgeEl.textContent = result.badge || 'MAPS';
    if (addressEl) addressEl.textContent = result.address || 'Lokasi Geografis Terverifikasi';
    if (coordsEl) coordsEl.textContent = result.dms || decimalToDms(result.lat, result.lng);
    if (decEl) decEl.textContent = `${result.lat.toFixed(5)}, ${result.lng.toFixed(5)}`;
    if (iconEl) {
      const emoji = (function () {
        const t = (result.type || '').toLowerCase();
        const b = (result.badge || '').toLowerCase();
        if (b.includes('gps') || t.includes('koordinat')) return '📍';
        if (b.includes('sar') || t.includes('basarnas')) return '🛡️';
        if (t.includes('pelabuhan') || t.includes('port')) return '⚓';
        if (t.includes('bandara') || t.includes('airport')) return '✈️';
        if (t.includes('pantai') || t.includes('pulau') || t.includes('gunung')) return '⛰️';
        if (t.includes('village') || t.includes('desa')) return '🏘️';
        if (t.includes('city') || t.includes('kota')) return '🏢';
        return '📍';
      })();
      iconEl.textContent = emoji;
    }

    banner.classList.remove('hidden');
  }

  // ==========================================
  // 6. SAVED POINTS & POINT CREATOR CARD
  // ==========================================

  function initPointCreatorUI() {
    let currentPresetGroup = 'all';
    let currentPresetQuery = '';

    function renderPresetIcons(group = 'all', query = '') {
      currentPresetGroup = group;
      currentPresetQuery = query;
      const gridSar = document.getElementById('iconGridSar');
      if (!gridSar) return;

      const q = (query || '').toLowerCase().trim();
      const filtered = PRESET_ICONS.filter((p) => {
        const matchGroup = group === 'all' || p.group === group;
        const matchQuery = !q || p.label.toLowerCase().includes(q) || p.type.toLowerCase().includes(q);
        return matchGroup && matchQuery;
      });

      if (filtered.length === 0) {
        gridSar.innerHTML = `<div class="col-span-4 p-3 text-center text-[11px] text-slate-400 italic">Tidak ada logo yang cocok dengan pencarian.</div>`;
        return;
      }

      gridSar.innerHTML = filtered
        .map(
          (p) => `
          <button type="button" data-icon="${p.type}" title="${p.label}" class="preset-icon-btn p-1.5 rounded-xl border border-slate-800 bg-slate-900 hover:border-orange-500 text-center flex flex-col items-center justify-center gap-1 transition cursor-pointer ${pointIconType === p.type ? 'border-orange-500 ring-2 ring-orange-500/30 bg-orange-500/10' : ''}">
            <span class="text-base select-none">${p.emoji}</span>
            <span class="text-[11px] font-bold text-slate-300 truncate w-full px-0.5">${p.label}</span>
          </button>
        `
        )
        .join('');

      gridSar.querySelectorAll('.preset-icon-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          pointIconType = btn.getAttribute('data-icon');
          pointCustomIconUrl = '';
          const previewCard = document.getElementById('pcCustomIconPreviewCard');
          if (previewCard) previewCard.classList.add('hidden');
          const galleryContainer = document.getElementById('customIconGalleryContainer');
          if (galleryContainer) {
            galleryContainer.querySelectorAll('.custom-icon-item').forEach((it) => {
              it.className = 'custom-icon-item group relative p-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-orange-500 transition cursor-pointer flex flex-col items-center justify-center text-center';
              const check = it.querySelector('.custom-check-badge');
              if (check) check.remove();
            });
          }
          gridSar.querySelectorAll('.preset-icon-btn').forEach((b) => b.classList.remove('border-orange-500', 'ring-2', 'ring-orange-500/30', 'bg-orange-500/10'));
          btn.classList.add('border-orange-500', 'ring-2', 'ring-orange-500/30', 'bg-orange-500/10');
          updateDraftMarkerVisual();
        });
      });
    }

    // Initial render of preset icons
    renderPresetIcons('all', '');

    // Preset group filter buttons
    document.querySelectorAll('.preset-filter-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.preset-filter-btn').forEach((b) => {
          b.className = 'preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition';
        });
        btn.className = 'preset-filter-btn px-2 py-0.5 rounded-lg bg-orange-500 text-white shrink-0 cursor-pointer transition';
        const group = btn.getAttribute('data-group') || 'all';
        renderPresetIcons(group, currentPresetQuery);
      });
    });

    // Preset search input
    const searchInput = document.getElementById('pcPresetSearch');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        renderPresetIcons(currentPresetGroup, e.target.value);
      });
    }

    // Populate Alphabet Grid A-Z
    const gridAlphabet = document.getElementById('iconGridAlphabet');
    if (gridAlphabet) {
      const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
      gridAlphabet.innerHTML = letters
        .map(
          (char) => `
        <button type="button" data-char="${char}" class="alpha-icon-btn py-1 rounded-lg border border-slate-800 bg-slate-900 text-center font-bold text-xs text-white hover:border-orange-500 transition cursor-pointer ${pointAlphabetChar === char ? 'border-orange-500 bg-orange-500/20' : ''}">
          ${char}
        </button>
      `
        )
        .join('');

      gridAlphabet.querySelectorAll('.alpha-icon-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          pointIconType = 'alphabet';
          pointAlphabetChar = btn.getAttribute('data-char');
          pointCustomIconUrl = '';
          const previewCard = document.getElementById('pcCustomIconPreviewCard');
          if (previewCard) previewCard.classList.add('hidden');
          const galleryContainer = document.getElementById('customIconGalleryContainer');
          if (galleryContainer) {
            galleryContainer.querySelectorAll('.custom-icon-item').forEach((it) => {
              it.className = 'custom-icon-item group relative p-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-orange-500 transition cursor-pointer flex flex-col items-center justify-center text-center';
              const check = it.querySelector('.custom-check-badge');
              if (check) check.remove();
            });
          }
          gridAlphabet.querySelectorAll('.alpha-icon-btn').forEach((b) => b.classList.remove('border-orange-500', 'bg-orange-500/20'));
          btn.classList.add('border-orange-500', 'bg-orange-500/20');
          updateDraftMarkerVisual();
        });
      });
    }

    // Icon Sub-tabs switching
    const tabSar = document.getElementById('tabIconPresetSar');
    const tabAlpha = document.getElementById('tabIconAlphabet');
    const tabCustom = document.getElementById('tabIconCustom');
    const panelSar = document.getElementById('iconGridSarPanel');
    const panelAlpha = document.getElementById('iconGridAlphabet');
    const panelCustom = document.getElementById('iconGridCustom');

    if (tabSar && tabAlpha && tabCustom) {
      tabSar.addEventListener('click', () => {
        tabSar.className = 'py-1 rounded-lg bg-orange-500 text-white cursor-pointer transition';
        tabAlpha.className = 'py-1 rounded-lg text-slate-400 hover:text-white cursor-pointer transition';
        tabCustom.className = 'py-1 rounded-lg text-slate-400 hover:text-white cursor-pointer transition';
        if (panelSar) panelSar.classList.remove('hidden');
        if (panelAlpha) panelAlpha.classList.add('hidden');
        if (panelCustom) panelCustom.classList.add('hidden');
      });

      tabAlpha.addEventListener('click', () => {
        tabAlpha.className = 'py-1 rounded-lg bg-orange-500 text-white cursor-pointer transition';
        tabSar.className = 'py-1 rounded-lg text-slate-400 hover:text-white cursor-pointer transition';
        tabCustom.className = 'py-1 rounded-lg text-slate-400 hover:text-white cursor-pointer transition';
        if (panelAlpha) panelAlpha.classList.remove('hidden');
        if (panelSar) panelSar.classList.add('hidden');
        if (panelCustom) panelCustom.classList.add('hidden');
      });

      tabCustom.addEventListener('click', () => {
        tabCustom.className = 'py-1 rounded-lg bg-orange-500 text-white cursor-pointer transition';
        tabSar.className = 'py-1 rounded-lg text-slate-400 hover:text-white cursor-pointer transition';
        tabAlpha.className = 'py-1 rounded-lg text-slate-400 hover:text-white cursor-pointer transition';
        if (panelCustom) panelCustom.classList.remove('hidden');
        if (panelSar) panelSar.classList.add('hidden');
        if (panelAlpha) panelAlpha.classList.add('hidden');
      });
    }

    // Realtime update of draft marker when user types or clears point name
    const nameInput = document.getElementById('pcPointName');
    if (nameInput) {
      nameInput.addEventListener('input', updateDraftMarkerVisual);
    }

    // DMS Numeric Inputs Event Listeners
    ['dmsLatDeg', 'dmsLatMin', 'dmsLatSec', 'dmsLatDir', 'dmsLngDeg', 'dmsLngMin', 'dmsLngSec', 'dmsLngDir'].forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.addEventListener('input', syncDmsInputsToDecimal);
        el.addEventListener('change', syncDmsInputsToDecimal);
      }
    });

    // Add Point Button
    const btnAdd = document.getElementById('btnShowPointCreator');
    if (btnAdd) {
      btnAdd.addEventListener('click', () => {
        let lat = -4.015;
        let lng = 122.585;
        try {
          if (map && typeof map.getCenter === 'function') {
            const center = map.getCenter();
            if (center && !isNaN(center.lat) && !isNaN(center.lng)) {
              lat = center.lat;
              lng = center.lng;
            }
          }
        } catch (e) {
          // Use default coordinates
        }
        openPointCreator({
          label: '',
          lat: lat,
          lng: lng,
          category: 'Tanpa Kategori',
          pointType: 'pin_umum'
        });
      });
    }

    // Cancel Point Creator
    const btnCancel = document.getElementById('btnCancelPointCreator');
    const btnCancelSave = document.getElementById('btnCancelSavePoint');
    [btnCancel, btnCancelSave].forEach((btn) => {
      if (btn) btn.addEventListener('click', closePointCreator);
    });

    // Save Point Confirm
    const btnSaveConfirm = document.getElementById('btnSavePointConfirm');
    if (btnSaveConfirm) {
      btnSaveConfirm.addEventListener('click', handleSavePoint);
    }

    // Custom Icon Upload & Gallery Management Handlers (Permanent Upload & Custom Gallery)
    const fileInput = document.getElementById('pcCustomIconFile');
    const triggerUploadBtn = document.getElementById('btnTriggerUploadIcon');
    const removeIconBtn = document.getElementById('btnRemoveCustomIcon');
    const previewCard = document.getElementById('pcCustomIconPreviewCard');
    const thumbImg = document.getElementById('pcCustomIconThumb');
    const fileNameEl = document.getElementById('pcCustomIconFileName');
    const galleryContainer = document.getElementById('customIconGalleryContainer');
    const countBadge = document.getElementById('customIconCountBadge');

    // Click listener on Tab "Logo Kustom"
    const tabCustomBtn = document.getElementById('tabIconCustom');
    if (tabCustomBtn) {
      tabCustomBtn.addEventListener('click', () => {
        loadCustomIconsGallery(pointCustomIconUrl);
      });
    }

    if (triggerUploadBtn && fileInput) {
      triggerUploadBtn.addEventListener('click', () => {
        fileInput.click();
      });
    }

    // Direct Permanent Upload via /api/gis/upload-icon
    if (fileInput) {
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;

        // Button state
        if (triggerUploadBtn) {
          triggerUploadBtn.disabled = true;
          triggerUploadBtn.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span><span>Mengupload logo permanen...</span>';
        }

        const formData = new FormData();
        formData.append('icon', file);
        if (csrfToken) formData.append('csrf_token', csrfToken);

        try {
          const res = await gisFetch(`${BASE_URL}/api/gis/upload-icon`, {
            method: 'POST',
            body: formData
          });
          const json = await res.json();
          if (json.success && json.url) {
            pointCustomIconUrl = normalizeIconUrl(json.url);
            pointIconType = 'custom_upload';

            if (thumbImg) thumbImg.src = pointCustomIconUrl;
            if (fileNameEl) fileNameEl.textContent = json.filename || file.name;
            if (previewCard) previewCard.classList.remove('hidden');

            // Refresh gallery and auto-select newly uploaded custom icon
            await loadCustomIconsGallery(pointCustomIconUrl);
            updateDraftMarkerVisual();
          } else {
            showToast(json.error || 'Gagal mengupload logo.', 'error');
          }
        } catch (err) {
          console.error('Upload icon failed:', err);
          showToast('Terjadi kesalahan saat mengupload logo.', 'error');
        } finally {
          if (triggerUploadBtn) {
            triggerUploadBtn.disabled = false;
            triggerUploadBtn.innerHTML = `
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              <span>+ Upload File Logo / Ikon Baru</span>
            `;
          }
          fileInput.value = '';
        }
      });
    }

    if (removeIconBtn) {
      removeIconBtn.addEventListener('click', () => {
        pointCustomIconUrl = '';
        pointIconType = 'pin_umum';
        if (fileInput) fileInput.value = '';
        if (previewCard) previewCard.classList.add('hidden');
        if (galleryContainer) {
          galleryContainer.querySelectorAll('.custom-icon-item').forEach((it) => {
            it.className = 'custom-icon-item group relative p-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-orange-500 transition cursor-pointer flex flex-col items-center justify-center text-center';
            const check = it.querySelector('.custom-check-badge, .absolute.top-1.right-1');
            if (check) check.remove();
          });
        }
        updateDraftMarkerVisual();
      });
    }

    // Toggle Draggable Lock Button
    const btnLock = document.getElementById('btnToggleDragLock');
    if (btnLock) {
      btnLock.addEventListener('click', () => {
        isPointsDraggableUnlocked = !isPointsDraggableUnlocked;
        updateDraggableLockUI();
      });
    }

    // Focus Kansar Quick Button
    const btnFocusKansar = document.getElementById('btnFocusKansarQuick');
    if (btnFocusKansar) {
      btnFocusKansar.addEventListener('click', () => {
        map.flyTo([-4.037694, 122.486694], 13, { duration: 1.2 });
      });
    }
  }

  function updateDraggableLockUI() {
    const iconClosed = document.getElementById('iconLockClosed');
    const iconOpen = document.getElementById('iconLockOpen');
    const label = document.getElementById('labelLockStatus');
    const btn = document.getElementById('btnToggleDragLock');

    if (isPointsDraggableUnlocked) {
      iconClosed.classList.add('hidden');
      iconOpen.classList.remove('hidden');
      label.textContent = 'Mode Geser Aktif';
      btn.className = 'flex-1 py-1.5 px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition bg-amber-500/20 border-amber-500/40 text-amber-300 shadow';
    } else {
      iconClosed.classList.remove('hidden');
      iconOpen.classList.add('hidden');
      label.textContent = 'Posisi Terkunci';
      btn.className = 'flex-1 py-1.5 px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition bg-slate-800 border-slate-700 text-slate-300 hover:bg-slate-750';
    }

    // Update draggable on existing point markers
    pointMarkersMap.forEach((marker) => {
      if (marker.dragging) {
        if (isPointsDraggableUnlocked) {
          marker.dragging.enable();
        } else {
          marker.dragging.disable();
        }
      }
    });
  }

  // Load permanently saved custom icons from /api/gis/custom-icons (Module Scope)
  async function loadCustomIconsGallery(selectedUrl = null) {
    const galleryContainer = document.getElementById('customIconGalleryContainer');
    const countBadge = document.getElementById('customIconCountBadge');
    const thumbImg = document.getElementById('pcCustomIconThumb');
    const fileNameEl = document.getElementById('pcCustomIconFileName');
    const previewCard = document.getElementById('pcCustomIconPreviewCard');
    if (!galleryContainer) return;
    try {
      const res = await gisFetch(`${BASE_URL}/api/gis/custom-icons`);
      const json = await res.json();
      const icons = (json && (json.icons || json.data)) || [];
      if (Array.isArray(icons)) {
        if (countBadge) {
          countBadge.textContent = `${icons.length} Logo`;
        }
        if (icons.length === 0) {
          galleryContainer.innerHTML = '<div class="col-span-4 p-3 text-center text-[11px] text-slate-400">Belum ada logo kustom tersimpan. Klik "+ Upload File Logo" di atas untuk menambahkan logo baru.</div>';
          return;
        }
        const activeUrl = normalizeIconUrl(selectedUrl || pointCustomIconUrl || '');
        galleryContainer.innerHTML = icons.map((ic) => {
          const itemUrl = normalizeIconUrl(ic.url);
          const isMatch = activeUrl && (
            itemUrl === activeUrl ||
            itemUrl.endsWith(activeUrl) ||
            activeUrl.endsWith(itemUrl) ||
            (activeUrl.split('/').pop() && activeUrl.split('/').pop() === itemUrl.split('/').pop())
          );
          const safeUrl = escapeHtml(itemUrl);
          const rawName = ic.name || ic.filename || itemUrl.split('/').pop() || 'Logo Kustom';
          const safeName = escapeHtml(rawName);
          const safeFilename = escapeHtml(ic.filename || itemUrl.split('/').pop() || '');
          return `
            <div class="custom-icon-item group relative p-1.5 rounded-xl border ${isMatch ? 'border-orange-500 bg-orange-500/10 ring-2 ring-orange-500/30' : 'border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900'} hover:border-orange-500 transition cursor-pointer flex flex-col items-center justify-center text-center"
                 data-icon-url="${safeUrl}" data-icon-name="${safeName}" data-icon-filename="${safeFilename}" title="${safeName}">
              <img src="${safeUrl}" alt="${safeName}" class="w-8 h-8 object-contain rounded p-0.5 bg-white/80 dark:bg-slate-950/80">
              <span class="text-[11px] font-medium text-slate-600 dark:text-slate-400 truncate w-full mt-1">${safeName}</span>
              ${isMatch ? '<span class="custom-check-badge absolute top-1 right-1 w-3.5 h-3.5 bg-orange-500 text-white rounded-full flex items-center justify-center text-[11px] font-bold shadow-xs">✓</span>' : ''}
              <button type="button" class="delete-custom-icon-btn absolute -top-1 -right-1 w-4 h-4 bg-red-600 hover:bg-red-700 text-white rounded-full flex items-center justify-center text-[11px] font-bold opacity-0 group-hover:opacity-100 transition shadow-xs z-10" data-filename="${safeFilename}" title="Hapus logo dari daftar">✕</button>
            </div>
          `;
        }).join('');

        // Bind click to each gallery item
        galleryContainer.querySelectorAll('.custom-icon-item').forEach((item) => {
          item.addEventListener('click', (e) => {
            if (e.target.closest('.delete-custom-icon-btn')) return;
            const url = item.getAttribute('data-icon-url');
            const name = item.getAttribute('data-icon-name');
            pointCustomIconUrl = normalizeIconUrl(url);
            pointIconType = 'custom_upload';

            if (thumbImg) thumbImg.src = pointCustomIconUrl;
            if (fileNameEl) fileNameEl.textContent = name;
            if (previewCard) previewCard.classList.remove('hidden');

            // Highlight active selected item in gallery
            galleryContainer.querySelectorAll('.custom-icon-item').forEach((it) => {
              it.className = 'custom-icon-item group relative p-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:border-orange-500 transition cursor-pointer flex flex-col items-center justify-center text-center';
              const check = it.querySelector('.custom-check-badge');
              if (check) check.remove();
            });
            item.className = 'custom-icon-item group relative p-1.5 rounded-xl border border-orange-500 bg-orange-500/10 ring-2 ring-orange-500/30 hover:border-orange-500 transition cursor-pointer flex flex-col items-center justify-center text-center';
            if (!item.querySelector('.custom-check-badge')) {
              const checkEl = document.createElement('span');
              checkEl.className = 'custom-check-badge absolute top-1 right-1 w-3.5 h-3.5 bg-orange-500 text-white rounded-full flex items-center justify-center text-[11px] font-bold shadow-xs';
              checkEl.textContent = '✓';
              item.appendChild(checkEl);
            }

            updateDraftMarkerVisual();
          });
        });

        // Bind delete button for each icon
        galleryContainer.querySelectorAll('.delete-custom-icon-btn').forEach((delBtn) => {
          delBtn.addEventListener('click', async (e) => {
            e.stopPropagation();
            const fn = delBtn.getAttribute('data-filename');
            if (!fn) return;
            if (!(await confirmAction('Hapus Logo Kustom', `Hapus berkas logo "${fn}" dari daftar logo kustom?`, 'Ya, Hapus', true))) return;
            try {
              const resDel = await gisFetch(`${BASE_URL}/api/gis/custom-icons`, {
                method: 'DELETE',
                body: JSON.stringify({ filename: fn })
              });
              const jsonDel = await resDel.json();
              if (jsonDel.success) {
                if (pointCustomIconUrl && pointCustomIconUrl.includes(fn)) {
                  pointCustomIconUrl = '';
                  pointIconType = 'pin_umum';
                  if (previewCard) previewCard.classList.add('hidden');
                  updateDraftMarkerVisual();
                }
                if (Array.isArray(rawAllPoints)) {
                  rawAllPoints.forEach((p) => {
                    if (p.customIconUrl && p.customIconUrl.includes(fn)) {
                      p.customIconUrl = '';
                      p.pointType = 'pin_umum';
                    }
                  });
                  applyActiveFilter();
                  renderSavedPoints();
                  notifyGisStorageUpdate();
                }
                loadCustomIconsGallery(pointCustomIconUrl);
              } else {
                showToast(jsonDel.message || 'Gagal menghapus logo kustom.', 'error');
              }
            } catch (err) {
              console.error('Delete custom icon failed:', err);
            }
          });
        });
      }
    } catch (err) {
      console.warn('Failed to load custom icons gallery:', err);
    }
  }

  function openPointCreator(data = {}) {
    isCreatingPoint = true;
    editingPointId = data.id || null;

    // Ensure tab is switched to tab-points and sidebar is opened
    switchActiveTab('tab-points');
    const sidebar = document.getElementById('gisSidebarPanel');
    if (sidebar && sidebar.classList.contains('-ml-[390px]')) {
      isSidebarVisible = true;
      sidebar.classList.remove('-ml-[390px]');
      const floatingBtn = document.getElementById('btnFloatingOpenSidebar');
      if (floatingBtn) floatingBtn.classList.add('hidden');
      const iconClose = document.getElementById('iconSidebarClose');
      if (iconClose) iconClose.classList.remove('hidden');
      const iconOpen = document.getElementById('iconSidebarOpen');
      if (iconOpen) iconOpen.classList.add('hidden');
      const label = document.getElementById('labelSidebarToggle');
      if (label) label.textContent = 'Sembunyikan Tools';
      setTimeout(() => { if (map) map.invalidateSize(); }, 200);
    }

    const nameInput = document.getElementById('pcPointName');
    if (nameInput) nameInput.value = data.label || '';

    // Normalize category strictly to the valid options (including Dermaga SAR & Penemuan Korban)
    const VALID_CATEGORIES = [
      'Kantor SAR',
      'POS SAR',
      'Unit Siaga SAR',
      'Dermaga SAR',
      'LKP',
      'Penemuan Korban',
      'Lokasi Penemuan Korban',
      'Posko',
      'Entry Poin',
      'Exit Poin',
      'Tanpa Kategori'
    ];
    let cat = data.category || 'Tanpa Kategori';
    if (cat === 'None' || cat === 'none' || !cat) cat = 'Tanpa Kategori';
    if (cat === 'Entry Point') cat = 'Entry Poin';
    if (cat === 'Exit Point') cat = 'Exit Poin';
    if (!VALID_CATEGORIES.includes(cat)) {
      if (cat.toUpperCase() === 'LKP') cat = 'LKP';
      else if (cat.toLowerCase().includes('dermaga')) cat = 'Dermaga SAR';
      else if (cat.toLowerCase().includes('penemuan') || cat.toLowerCase().includes('korban')) cat = 'Penemuan Korban';
      else if (cat.includes('Kantor')) cat = 'Kantor SAR';
      else if (cat.includes('Posko')) cat = 'Posko';
      else if (cat.includes('POS')) cat = 'POS SAR';
      else if (cat.includes('Siaga')) cat = 'Unit Siaga SAR';
      else cat = 'Tanpa Kategori';
    }
    const catSelect = document.getElementById('pcPointCategory');
    if (catSelect) catSelect.value = cat;

    const descInput = document.getElementById('pcPointDesc');
    if (descInput) descInput.value = data.description || '';

    pointIconType = data.pointType || 'pin_umum';
    pointAlphabetChar = data.alphabetChar || 'A';
    pointCustomIconUrl = normalizeIconUrl(data.customIconUrl || '');
    if (pointCustomIconUrl) {
      pointIconType = 'custom_upload';
    }

    // Reset search and filter pills in preset selector
    const searchInput = document.getElementById('pcPresetSearch');
    if (searchInput) searchInput.value = '';
    document.querySelectorAll('.preset-filter-btn').forEach((b) => {
      if (b.getAttribute('data-group') === 'all') {
        b.className = 'preset-filter-btn px-2 py-0.5 rounded-lg bg-orange-500 text-white shrink-0 cursor-pointer transition';
      } else {
        b.className = 'preset-filter-btn px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 shrink-0 cursor-pointer transition';
      }
    });

    const tabSar = document.getElementById('tabIconPresetSar');
    const tabAlpha = document.getElementById('tabIconAlphabet');
    const tabCustom = document.getElementById('tabIconCustom');
    const panelSar = document.getElementById('iconGridSarPanel');
    const panelAlpha = document.getElementById('iconGridAlphabet');
    const panelCustom = document.getElementById('iconGridCustom');

    if (pointCustomIconUrl) {
      if (tabCustom) tabCustom.click();
    } else if (pointIconType === 'alphabet') {
      if (tabAlpha) tabAlpha.click();
    } else {
      if (tabSar) tabSar.click();
      const gridSar = document.getElementById('iconGridSar');
      if (gridSar) {
        gridSar.querySelectorAll('.preset-icon-btn').forEach((b) => {
          if (b.getAttribute('data-icon') === pointIconType) {
            b.classList.add('border-orange-500', 'ring-2', 'ring-orange-500/30', 'bg-orange-500/10');
            b.scrollIntoView({ block: 'nearest', inline: 'nearest' });
          } else {
            b.classList.remove('border-orange-500', 'ring-2', 'ring-orange-500/30', 'bg-orange-500/10');
          }
        });
      }
    }

    const fileInput = document.getElementById('pcCustomIconFile');
    const previewCard = document.getElementById('pcCustomIconPreviewCard');
    const thumbImg = document.getElementById('pcCustomIconThumb');
    const fileNameEl = document.getElementById('pcCustomIconFileName');
    if (fileInput) fileInput.value = '';
    if (pointCustomIconUrl && previewCard) {
      previewCard.classList.remove('hidden');
      if (thumbImg) thumbImg.src = pointCustomIconUrl;
      if (fileNameEl) fileNameEl.textContent = pointCustomIconUrl.split('/').pop() || 'Custom Icon';
    } else if (previewCard) {
      previewCard.classList.add('hidden');
    }

    if (typeof loadCustomIconsGallery === 'function') {
      loadCustomIconsGallery(pointCustomIconUrl);
    }

    pointDraftCoords = {
      lat: data.lat !== undefined ? data.lat : -4.015,
      lng: data.lng !== undefined ? data.lng : 122.585
    };

    updatePointDraftCoords(pointDraftCoords.lat, pointDraftCoords.lng);

    const titleEl = document.getElementById('pointCreatorTitle');
    if (titleEl) {
      titleEl.innerHTML = editingPointId ? '<span>Edit Titik SAR</span>' : '<span>Plot Titik Taktis Baru</span>';
    }

    const card = document.getElementById('pointCreatorCard');
    if (card) {
      card.classList.remove('hidden');
    }

    // Pan map to draft position
    if (map) {
      map.panTo([pointDraftCoords.lat, pointDraftCoords.lng]);
    }
  }

  function closePointCreator() {
    isCreatingPoint = false;
    editingPointId = null;
    document.getElementById('pointCreatorCard').classList.add('hidden');

    const previewCard = document.getElementById('pcCustomIconPreviewCard');
    const fileInput = document.getElementById('pcCustomIconFile');
    if (previewCard) previewCard.classList.add('hidden');
    if (fileInput) fileInput.value = '';

    pointCustomIconUrl = '';
    pointIconType = 'pin_umum';

    if (draftPointMarker) {
      map.removeLayer(draftPointMarker);
      draftPointMarker = null;
    }
  }

  function updatePointDraftCoords(lat, lng) {
    pointDraftCoords = { lat, lng };

    // Sync DMS input fields
    const absLat = Math.abs(lat);
    const latD = Math.floor(absLat);
    const latMFloat = (absLat - latD) * 60;
    const latM = Math.floor(latMFloat);
    const latS = Math.round((latMFloat - latM) * 600) / 10;
    const latDir = lat < 0 ? 'S' : 'N';

    const absLng = Math.abs(lng);
    const lngD = Math.floor(absLng);
    const lngMFloat = (absLng - lngD) * 60;
    const lngM = Math.floor(lngMFloat);
    const lngS = Math.round((lngMFloat - lngM) * 600) / 10;
    const lngDir = lng < 0 ? 'W' : 'E';

    document.getElementById('dmsLatDeg').value = latD;
    document.getElementById('dmsLatMin').value = latM;
    document.getElementById('dmsLatSec').value = latS;
    document.getElementById('dmsLatDir').value = latDir;

    document.getElementById('dmsLngDeg').value = lngD;
    document.getElementById('dmsLngMin').value = lngM;
    document.getElementById('dmsLngSec').value = lngS;
    document.getElementById('dmsLngDir').value = lngDir;

    const readout = document.getElementById('pcReadoutDecimal');
    if (readout) readout.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

    updateDraftMarkerVisual();
  }

  function syncDmsInputsToDecimal() {
    const latD = parseFloat(document.getElementById('dmsLatDeg').value) || 0;
    const latM = parseFloat(document.getElementById('dmsLatMin').value) || 0;
    const latS = parseFloat(document.getElementById('dmsLatSec').value) || 0;
    const latDir = document.getElementById('dmsLatDir').value;

    const lngD = parseFloat(document.getElementById('dmsLngDeg').value) || 0;
    const lngM = parseFloat(document.getElementById('dmsLngMin').value) || 0;
    const lngS = parseFloat(document.getElementById('dmsLngSec').value) || 0;
    const lngDir = document.getElementById('dmsLngDir').value;

    let lat = latD + latM / 60 + latS / 3600;
    if (latDir === 'S') lat = -lat;

    let lng = lngD + lngM / 60 + lngS / 3600;
    if (lngDir === 'W') lng = -lng;

    pointDraftCoords = { lat, lng };

    const readout = document.getElementById('pcReadoutDecimal');
    if (readout) readout.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;

    updateDraftMarkerVisual();
  }

  function updateDraftMarkerVisual() {
    if (!draftPointMarker) {
      draftPointMarker = L.marker([pointDraftCoords.lat, pointDraftCoords.lng], {
        draggable: true,
        zIndexOffset: 1000
      }).addTo(map);

      draftPointMarker.on('dragend', (e) => {
        const pos = e.target.getLatLng();
        updatePointDraftCoords(pos.lat, pos.lng);
      });
    } else {
      draftPointMarker.setLatLng([pointDraftCoords.lat, pointDraftCoords.lng]);
    }

    const nameInput = document.getElementById('pcPointName');
    const name = nameInput ? nameInput.value.trim() : '';
    const normCustom = normalizeIconUrl(pointCustomIconUrl);
    const html = createMarkerHtml(
      {
        label: name,
        pointType: normCustom ? 'custom_upload' : pointIconType,
        alphabetChar: pointAlphabetChar,
        customIconUrl: normCustom
      },
      true
    );

    draftPointMarker.setIcon(
      L.divIcon({
        html: html,
        className: 'sar-draft-pin',
        iconSize: [46, 54],
        iconAnchor: [23, 50],
        popupAnchor: [0, -50]
      })
    );
  }

  async function handleSavePoint() {
    // 1. Point name is completely optional! If left blank, point appears without label
    const nameInput = document.getElementById('pcPointName');
    const name = nameInput ? nameInput.value.trim() : '';

    // 2. Category strictly from input select
    const categorySelect = document.getElementById('pcPointCategory');
    const category = categorySelect ? categorySelect.value : 'Tanpa Kategori';

    const descInput = document.getElementById('pcPointDesc');
    const desc = descInput ? descInput.value.trim() : '';

    const targetOpId = (filterMode === 'operasi' && currentOperasiId)
      ? currentOperasiId
      : (editingPointId ? (rawAllPoints.find(p => p.id === editingPointId)?.id_operasi || null) : null);

    const normCustom = normalizeIconUrl(pointCustomIconUrl);
    const finalPointType = normCustom ? 'custom_upload' : pointIconType;

    const pointPayload = {
      id: editingPointId || `pt-${Date.now()}`,
      id_operasi: targetOpId,
      label: name,
      category: category,
      pointType: finalPointType,
      alphabetChar: pointAlphabetChar,
      customIconUrl: normCustom,
      description: desc,
      lat: pointDraftCoords.lat,
      lng: pointDraftCoords.lng
    };

    try {
      await gisFetch(`${BASE_URL}/api/gis/points`, {
        method: 'POST',
        body: JSON.stringify(pointPayload)
      });

      updateRawPoint(pointPayload, false);
      closePointCreator();
      renderSavedPoints();
    } catch (err) {
      console.error('Error saving point:', err);
      updateRawPoint(pointPayload, false);
      closePointCreator();
      renderSavedPoints();
    }
  }

  function getCategoryBadgeHtml(cat) {
    switch (cat) {
      case 'Kantor SAR':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-blue-500/20 text-blue-400 border border-blue-500/30">🏢 Kantor SAR</span>';
      case 'POS SAR':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-orange-500/20 text-orange-400 border border-orange-500/30">📻 POS SAR</span>';
      case 'Unit Siaga SAR':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">📡 Unit Siaga SAR</span>';
      case 'Dermaga SAR':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-sky-500/20 text-sky-400 border border-sky-500/30">⚓ Dermaga SAR</span>';
      case 'LKP':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-red-500/20 text-red-400 border border-red-500/30">🚨 LKP</span>';
      case 'Penemuan Korban':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-amber-500/20 text-amber-400 border border-amber-500/30">📍 Penemuan Korban</span>';
      case 'Lokasi Penemuan Korban':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-amber-500/20 text-amber-400 border border-amber-500/30">📍 Lokasi Penemuan Korban</span>';
      case 'Posko':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">🏕️ Posko</span>';
      case 'Entry Poin':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-green-500/20 text-green-400 border border-green-500/30">🟢 Entry Poin</span>';
      case 'Exit Poin':
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-rose-500/20 text-rose-400 border border-rose-500/30">🔴 Exit Poin</span>';
      default:
        return '<span class="px-1.5 py-0.5 rounded text-[11px] font-extrabold bg-slate-500/20 text-slate-400 border border-slate-500/30">⚪ Tanpa Kategori</span>';
    }
  }

  function renderSavedPoints() {
    // 1. Update Badge
    const badge = document.getElementById('pointsBadgeCount');
    if (badge) badge.textContent = String(savedPoints.length);

    // 2. Remove obsolete markers
    pointMarkersMap.forEach((marker, id) => {
      map.removeLayer(marker);
    });
    pointMarkersMap.clear();

    // 3. Render Markers on Map
    savedPoints.forEach((pt) => {
      if (isIdHidden(hiddenPointIds, pt.id)) return;

      const html = createMarkerHtml(pt, false);
      const marker = L.marker([pt.lat, pt.lng], {
        draggable: isPointsDraggableUnlocked
      }).addTo(map);

      marker.setIcon(
        L.divIcon({
          html: html,
          className: 'sar-map-pin',
          iconSize: [38, 46],
          iconAnchor: [19, 42],
          popupAnchor: [0, -42]
        })
      );

      // Popup
      const dms = decimalToDms(pt.lat, pt.lng);
      const cat = pt.category || 'Tanpa Kategori';
      const labelText = pt.label && pt.label.trim() ? pt.label : (cat !== 'Tanpa Kategori' ? cat : 'Titik SAR');

      marker.bindPopup(`
        <div class="p-3 text-xs space-y-1.5 min-w-[190px]">
          <div class="font-bold text-orange-400 text-sm">${escapeHtml(labelText)}</div>
          <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-800 text-slate-200 border border-slate-700">
            <span>Kategori:</span>
            <span class="text-orange-400 font-extrabold">${escapeHtml(cat)}</span>
          </div>
          <div class="text-[11px] font-mono text-slate-400 pt-0.5">${escapeHtml(dms)}</div>
          ${pt.description ? `<div class="text-[11px] text-slate-300 pt-1 border-t border-slate-700">${escapeHtml(pt.description)}</div>` : ''}
        </div>
      `);

      // Dragend handler
      marker.on('dragend', async (e) => {
        const pos = e.target.getLatLng();
        pt.lat = pos.lat;
        pt.lng = pos.lng;
        if (filterMode === 'operasi' && currentOperasiId && !pt.id_operasi) {
          pt.id_operasi = currentOperasiId;
        }
        updateRawPoint(pt, false);

        // Fix: Update marker popup with freshly calculated coordinates
        const newDms = decimalToDms(pt.lat, pt.lng);
        marker.setPopupContent(`
          <div class="p-3 text-xs space-y-1.5 min-w-[190px]">
            <div class="font-bold text-orange-400 text-sm">${escapeHtml(labelText)}</div>
            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-800 text-slate-200 border border-slate-700">
              <span>Kategori:</span>
              <span class="text-orange-400 font-extrabold">${escapeHtml(cat)}</span>
            </div>
            <div class="text-[11px] font-mono text-slate-400 pt-0.5">${escapeHtml(newDms)}</div>
            ${pt.description ? `<div class="text-[11px] text-slate-300 pt-1 border-t border-slate-700">${escapeHtml(pt.description)}</div>` : ''}
          </div>
        `);

        // Persist update
        try {
          await gisFetch(`${BASE_URL}/api/gis/points/${pt.id}`, {
            method: 'PUT',
            body: JSON.stringify(pt)
          });
        } catch (err) { }

        renderSavedPointsList();
      });

      pointMarkersMap.set(pt.id, marker);
    });

    renderSavedPointsList();
  }

  function renderSavedPointsList() {
    const listContainer = document.getElementById('savedPointsListContainer');
    if (!listContainer) return;

    if (savedPoints.length === 0) {
      listContainer.innerHTML = `<div class="p-4 text-center text-slate-500 text-xs italic">Belum ada titik SAR tersimpan.</div>`;
      return;
    }

    listContainer.innerHTML = savedPoints
      .map((pt) => {
        const isHidden = isIdHidden(hiddenPointIds, pt.id);
        const dms = decimalToDms(pt.lat, pt.lng);
        const distFromKansar = calculateDistanceHaversine(-4.037694, 122.486694, pt.lat, pt.lng);
        const preset = PRESET_ICONS.find((p) => p.type === pt.pointType) || PRESET_ICONS[0];
        const category = pt.category || 'Tanpa Kategori';
        const hasName = pt.label && pt.label.trim();
        const displayTitle = hasName
          ? pt.label
          : (category !== 'Tanpa Kategori' ? `[${category}] (Tanpa Nama)` : '(Titik Tanpa Nama)');

        const normIcon = normalizeIconUrl(pt.customIconUrl);
        const iconDisplay = normIcon
          ? `<img src="${escapeHtml(normIcon)}" class="w-full h-full object-contain rounded-lg p-0.5">`
          : pt.pointType === 'alphabet'
            ? `<span class="font-black text-sm">${escapeHtml(pt.alphabetChar || 'A')}</span>`
            : `<span class="text-base">${preset.emoji}</span>`;

        const safeId = escapeHtml(pt.id);
        const safeTitle = escapeHtml(displayTitle);

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-2.5 flex-1 min-w-0 cursor-pointer" onclick="window.SAR_GIS.focusPoint('${safeId}')">
              <span class="w-8 h-8 rounded-xl flex items-center justify-center text-sm shrink-0 border border-slate-200 dark:border-slate-700 select-none" style="background:${preset.color}25; color:${preset.color};">
                ${iconDisplay}
              </span>
              <div class="min-w-0 flex-1">
                <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${safeTitle}</div>
                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                  ${getCategoryBadgeHtml(category)}
                  ${getOperasiBadgeHtml(pt.id_operasi)}
                  <span class="text-[11px] text-orange-600 dark:text-orange-400 font-mono">${distFromKansar.nm} NM (${distFromKansar.bearing}° ${distFromKansar.headingName})</span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono truncate mt-0.5">${escapeHtml(dms)}</div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.copyPointCoordinates('${safeId}')" class="p-1 text-slate-400 hover:text-emerald-500 dark:hover:text-emerald-400 rounded cursor-pointer transition" title="Salin Koordinat (DMS & Desimal)">
                ${GIS_ICON.copy}
              </button>
              <button type="button" onclick="window.SAR_GIS.togglePointVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded cursor-pointer" title="${isHidden ? 'Tampilkan' : 'Sembunyikan'}">
                ${isHidden ? GIS_ICON.eyeOff : GIS_ICON.eye}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPoint('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded cursor-pointer" title="Edit">
                ${GIS_ICON.edit}
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePoint('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded cursor-pointer" title="Hapus">
                ${GIS_ICON.trash}
              </button>
            </div>
          </div>
        </div>
      `;
      })
      .join('');
  }

  function copyPointCoordinates(id) {
    const pt = savedPoints.find((p) => String(p.id) === String(id));
    if (!pt) {
      if (typeof showToast === 'function') showToast('Titik SAR tidak ditemukan.', 'error');
      return;
    }
    const dms = decimalToDms(pt.lat, pt.lng);
    const latNum = parseFloat(pt.lat);
    const lngNum = parseFloat(pt.lng);
    const ddLat = isNaN(latNum) ? pt.lat : latNum.toFixed(6);
    const ddLng = isNaN(lngNum) ? pt.lng : lngNum.toFixed(6);
    const label = pt.label && pt.label.trim() ? pt.label.trim() : (pt.category || 'Titik SAR');
    const textToCopy = `${label}: ${dms} (${ddLat}, ${ddLng})`;

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(textToCopy).then(() => {
        if (typeof showToast === 'function') {
          showToast(`Koordinat ${label} berhasil disalin ke clipboard!`, 'success');
        }
      }).catch(() => {
        fallbackCopyText(textToCopy, label);
      });
    } else {
      fallbackCopyText(textToCopy, label);
    }
  }

  function fallbackCopyText(text, label) {
    const el = document.createElement('textarea');
    el.value = text;
    el.setAttribute('readonly', '');
    el.style.position = 'absolute';
    el.style.left = '-9999px';
    document.body.appendChild(el);
    el.select();
    try {
      document.execCommand('copy');
      if (typeof showToast === 'function') {
        showToast(`Koordinat ${label} berhasil disalin ke clipboard!`, 'success');
      }
    } catch {
      prompt('Salin manual koordinat:', text);
    }
    document.body.removeChild(el);
  }

  // ==========================================
  // 7. POLYGON SEARCH AREA MANAGER
  // ==========================================

  function initPolygonManagerUI() {
    // Color Swatches
    const swatchesContainer = document.getElementById('polyStrokeColorSwatches');
    if (swatchesContainer) {
      swatchesContainer.innerHTML = COLOR_PALETTE.map(
        (c) => `
        <button type="button" data-color="${c.color}" class="poly-color-dot w-6 h-6 rounded-full border-2 transition ${polyStrokeColor === c.color ? 'border-white scale-110' : 'border-transparent'}" style="background-color: ${c.color};" title="${c.label}"></button>
      `
      ).join('');

      swatchesContainer.querySelectorAll('.poly-color-dot').forEach((btn) => {
        btn.addEventListener('click', () => {
          polyStrokeColor = btn.getAttribute('data-color');
          polyFillColor = polyStrokeColor;
          document.getElementById('labelStrokeColor').textContent = polyStrokeColor.toUpperCase();
          document.getElementById('labelStrokeColor').style.color = polyStrokeColor;

          swatchesContainer.querySelectorAll('.poly-color-dot').forEach((b) => b.classList.replace('border-white', 'border-transparent'));
          btn.classList.replace('border-transparent', 'border-white');

          updateLivePolygonVisual();
        });
      });
    }

    // Weight & Opacity Sliders
    const weightSlider = document.getElementById('polyStrokeWeightSlider');
    const opacitySlider = document.getElementById('polyFillOpacitySlider');

    if (weightSlider) {
      weightSlider.addEventListener('input', (e) => {
        polyStrokeWeight = parseFloat(e.target.value);
        document.getElementById('labelStrokeWeight').textContent = `${polyStrokeWeight} px`;
        updateLivePolygonVisual();
      });
    }

    if (opacitySlider) {
      opacitySlider.addEventListener('input', (e) => {
        polyFillOpacity = parseFloat(e.target.value);
        document.getElementById('labelFillOpacity').textContent = `${Math.round(polyFillOpacity * 100)}%`;
        updateLivePolygonVisual();
      });
    }

    // Search Area Shape Selector Buttons (Polygon Bebas, Persegi / Kotak, Circle / Lingkaran)
    document.querySelectorAll('.poly-shape-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const shape = btn.getAttribute('data-shape') || 'polygon';
        setSearchAreaShape(shape);
      });
    });

    // Circle Radius Input & Unit Listeners (NM, KM, Meter)
    const circleRadInput = document.getElementById('polyCircleRadiusNm');
    const circleRadUnitSel = document.getElementById('polyCircleRadiusUnit');

    function syncCircleRadiusFromInput() {
      if (!circleRadInput) return;
      const rawVal = parseFloat(circleRadInput.value) || 1.0;
      const unit = circleRadUnitSel ? circleRadUnitSel.value : 'NM';
      polyCircleRadiusUnit = unit;
      if (unit === 'KM') {
        polyCircleRadiusNm = Math.max(0.01, rawVal / 1.852);
      } else if (unit === 'M') {
        polyCircleRadiusNm = Math.max(0.001, rawVal / 1852);
      } else {
        polyCircleRadiusNm = Math.max(0.05, rawVal);
      }
      if (activeSearchAreaShape === 'circle' && (polyShapeCenter || activePolygonPoints.length > 0)) {
        const c = polyShapeCenter || getPolygonCentroid(activePolygonPoints);
        polyShapeCenter = c;
        activePolygonPoints = generateCircleVertices(c.lat, c.lng, polyCircleRadiusNm);
        updateLivePolygonVisual();
      }
    }

    if (circleRadInput) {
      circleRadInput.addEventListener('input', syncCircleRadiusFromInput);
    }

    if (circleRadUnitSel) {
      circleRadUnitSel.addEventListener('change', (e) => {
        setGlobalMeasurementUnit(e.target.value);
        syncCircleRadiusFromInput();
      });
    }

    // Rectangle Dimensions Inputs & Unit Listeners (Lebar & Tinggi: NM, KM, Meter)
    const rectWidthInput = document.getElementById('polyRectWidthNm');
    const rectHeightInput = document.getElementById('polyRectHeightNm');
    const rectDimUnitSel = document.getElementById('polyRectDimUnit');

    function syncRectDimsFromInput() {
      const rawW = parseFloat(rectWidthInput ? rectWidthInput.value : '2.0') || 2.0;
      const rawH = parseFloat(rectHeightInput ? rectHeightInput.value : '2.0') || 2.0;
      const unit = rectDimUnitSel ? rectDimUnitSel.value : 'NM';
      polyRectDimUnit = unit;
      if (unit === 'KM') {
        polyRectWidthNm = Math.max(0.01, rawW / 1.852);
        polyRectHeightNm = Math.max(0.01, rawH / 1.852);
      } else if (unit === 'M') {
        polyRectWidthNm = Math.max(0.001, rawW / 1852);
        polyRectHeightNm = Math.max(0.001, rawH / 1852);
      } else {
        polyRectWidthNm = Math.max(0.05, rawW);
        polyRectHeightNm = Math.max(0.05, rawH);
      }
      if (activeSearchAreaShape === 'rectangle' && (polyShapeCenter || activePolygonPoints.length > 0)) {
        const c = polyShapeCenter || getPolygonCentroid(activePolygonPoints);
        polyShapeCenter = c;
        activePolygonPoints = generateRectangleVertices(c.lat, c.lng, polyRectWidthNm, polyRectHeightNm);
        updateLivePolygonVisual();
      }
    }

    [rectWidthInput, rectHeightInput].forEach((inp) => {
      if (inp) {
        inp.addEventListener('input', syncRectDimsFromInput);
      }
    });

    if (rectDimUnitSel) {
      rectDimUnitSel.addEventListener('change', (e) => {
        setGlobalMeasurementUnit(e.target.value);
        syncRectDimsFromInput();
      });
    }

    // New Polygon Button
    const btnNew = document.getElementById('btnStartNewPolygon');
    if (btnNew) {
      btnNew.addEventListener('click', () => {
        startPolygonDrawing();
      });
    }

    // Cancel Drawing
    const btnCancel = document.getElementById('btnCancelDrawingPoly');
    const btnCancelForm = document.getElementById('btnCancelPolyForm');
    [btnCancel, btnCancelForm].forEach((btn) => {
      if (btn) btn.addEventListener('click', cancelPolygonDrawing);
    });

    // Save Polygon
    const btnSaveConfirm = document.getElementById('btnSavePolygonConfirm');
    if (btnSaveConfirm) {
      btnSaveConfirm.addEventListener('click', handleSavePolygon);
    }
  }

  function setSearchAreaShape(shape) {
    activeSearchAreaShape = shape;

    document.querySelectorAll('.poly-shape-btn').forEach((b) => {
      const isCur = b.getAttribute('data-shape') === shape;
      if (isCur) {
        b.className = 'poly-shape-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-left transition font-bold shadow-xs cursor-pointer';
      } else {
        b.className = 'poly-shape-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-left hover:border-orange-500 transition cursor-pointer';
      }
    });

    const rectBox = document.getElementById('polyRectangleParamsBox');
    const circleBox = document.getElementById('polyCircleParamsBox');
    const guideText = document.getElementById('polyDrawingGuideText');

    if (shape === 'rectangle') {
      if (rectBox) rectBox.classList.remove('hidden');
      if (circleBox) circleBox.classList.add('hidden');
      if (guideText) {
        guideText.innerHTML = '👉 <strong>Klik di atas peta</strong> untuk menentukan titik pusat persegi, atau ubah Lebar & Tinggi NM di atas.';
      }
      if (activePolygonPoints.length > 0) {
        const c = polyShapeCenter || getPolygonCentroid(activePolygonPoints);
        polyShapeCenter = c;
        activePolygonPoints = generateRectangleVertices(c.lat, c.lng, polyRectWidthNm, polyRectHeightNm);
        updateLivePolygonVisual();
      }
    } else if (shape === 'circle') {
      if (rectBox) rectBox.classList.add('hidden');
      if (circleBox) circleBox.classList.remove('hidden');
      if (guideText) {
        guideText.innerHTML = '👉 <strong>Klik di atas peta</strong> untuk menentukan titik pusat lingkaran, lalu tentukan Radius NM.';
      }
      if (activePolygonPoints.length > 0) {
        const c = polyShapeCenter || getPolygonCentroid(activePolygonPoints);
        polyShapeCenter = c;
        activePolygonPoints = generateCircleVertices(c.lat, c.lng, polyCircleRadiusNm);
        updateLivePolygonVisual();
      }
    } else {
      if (rectBox) rectBox.classList.add('hidden');
      if (circleBox) circleBox.classList.add('hidden');
      if (guideText) {
        guideText.innerHTML = '👉 <strong>Klik di atas peta</strong> untuk menambah Titik A, B, C, dst. Semua titik sudut <strong>dapat digeser langsung</strong> pada peta.';
      }
    }
  }

  function generateCircleVertices(centerLat, centerLng, radiusNm, numPoints = 36) {
    const points = [];
    const radiusKm = radiusNm * 1.852;
    const latKmPerDeg = 110.574;
    const lngKmPerDeg = 111.32 * Math.cos((centerLat * Math.PI) / 180);

    for (let i = 0; i < numPoints; i++) {
      const theta = (i / numPoints) * 2 * Math.PI;
      const dLat = (radiusKm * Math.cos(theta)) / latKmPerDeg;
      const dLng = (radiusKm * Math.sin(theta)) / lngKmPerDeg;
      points.push({
        lat: centerLat + dLat,
        lng: centerLng + dLng,
        label: `R${i + 1}`
      });
    }
    return points;
  }

  function generateRectangleVertices(centerLat, centerLng, widthNm, heightNm) {
    const halfWidthKm = (widthNm * 1.852) / 2;
    const halfHeightKm = (heightNm * 1.852) / 2;
    const latKmPerDeg = 110.574;
    const lngKmPerDeg = 111.32 * Math.cos((centerLat * Math.PI) / 180);

    const dLat = halfHeightKm / latKmPerDeg;
    const dLng = halfWidthKm / lngKmPerDeg;

    return [
      { lat: centerLat + dLat, lng: centerLng - dLng, label: 'Titik A (NW)' },
      { lat: centerLat + dLat, lng: centerLng + dLng, label: 'Titik B (NE)' },
      { lat: centerLat - dLat, lng: centerLng + dLng, label: 'Titik C (SE)' },
      { lat: centerLat - dLat, lng: centerLng - dLng, label: 'Titik D (SW)' }
    ];
  }

  function handleSearchAreaMapClick(lat, lng) {
    if (activeSearchAreaShape === 'circle') {
      polyShapeCenter = { lat, lng };
      activePolygonPoints = generateCircleVertices(lat, lng, polyCircleRadiusNm);
      updateLivePolygonVisual();
    } else if (activeSearchAreaShape === 'rectangle') {
      polyShapeCenter = { lat, lng };
      activePolygonPoints = generateRectangleVertices(lat, lng, polyRectWidthNm, polyRectHeightNm);
      updateLivePolygonVisual();
    } else {
      addPolygonVertex(lat, lng);
    }
  }

  function startPolygonDrawing(existingData = null) {
    isDrawingPolygon = true;
    editingPolygonId = existingData ? existingData.id : null;
    activePolygonPoints = existingData ? [...existingData.points] : [];

    polyStrokeColor = existingData ? (existingData.strokeColor || '#ea580c') : '#ea580c';
    polyStrokeWeight = existingData ? (existingData.strokeWeight || 2.5) : 2.5;
    polyFillColor = existingData ? (existingData.fillColor || '#ea580c') : '#ea580c';
    polyFillOpacity = existingData ? (existingData.fillOpacity || 0.2) : 0.2;

    const shape = existingData && existingData.shapeType ? existingData.shapeType : 'polygon';
    activeSearchAreaShape = shape;

    if (existingData && existingData.shapeType === 'circle') {
      polyCircleRadiusNm = parseFloat(existingData.radiusNm) || 1.0;
      const rInput = document.getElementById('polyCircleRadiusNm');
      if (rInput) rInput.value = polyCircleRadiusNm;
      polyShapeCenter = (existingData.centerLat && existingData.centerLng)
        ? { lat: parseFloat(existingData.centerLat), lng: parseFloat(existingData.centerLng) }
        : (activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : null);
    } else if (existingData && existingData.shapeType === 'rectangle') {
      polyRectWidthNm = parseFloat(existingData.boxWidthNm) || 2.0;
      polyRectHeightNm = parseFloat(existingData.boxHeightNm) || 2.0;
      const wInput = document.getElementById('polyRectWidthNm');
      const hInput = document.getElementById('polyRectHeightNm');
      if (wInput) wInput.value = polyRectWidthNm;
      if (hInput) hInput.value = polyRectHeightNm;
      polyShapeCenter = (existingData.centerLat && existingData.centerLng)
        ? { lat: parseFloat(existingData.centerLat), lng: parseFloat(existingData.centerLng) }
        : (activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : null);
    } else {
      polyShapeCenter = activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : null;
    }

    setSearchAreaShape(shape);

    const polyNameInput = document.getElementById('polyEditorName');
    if (polyNameInput) {
      polyNameInput.value = existingData ? (existingData.name || '') : '';
      polyNameInput.placeholder = 'SEARCH AREA SRU 1';
    }
    document.getElementById('polygonDrawingEditorCard').classList.remove('hidden');
    const btnNewPoly = document.getElementById('btnStartNewPolygon');
    if (btnNewPoly) btnNewPoly.classList.add('hidden');

    updateLivePolygonVisual();
  }

  function cancelPolygonDrawing() {
    isDrawingPolygon = false;
    editingPolygonId = null;
    activePolygonPoints = [];
    polyShapeCenter = null;
    activeSearchAreaShape = 'polygon';
    setSearchAreaShape('polygon');

    document.getElementById('polygonDrawingEditorCard').classList.add('hidden');
    const btnNewPoly = document.getElementById('btnStartNewPolygon');
    if (btnNewPoly) btnNewPoly.classList.remove('hidden');

    if (activeDrawingPolygonOverlay) {
      map.removeLayer(activeDrawingPolygonOverlay);
      activeDrawingPolygonOverlay = null;
    }

    activeDrawingVertexMarkers.forEach((m) => map.removeLayer(m));
    activeDrawingVertexMarkers = [];
  }

  function addPolygonVertex(lat, lng) {
    const nextIdx = activePolygonPoints.length;
    const label = `Titik ${String.fromCharCode(65 + (nextIdx % 26))}`;
    activePolygonPoints.push({ lat, lng, label });
    updateLivePolygonVisual();
  }

  function removePolygonVertex(index) {
    activePolygonPoints.splice(index, 1);
    // Re-label
    activePolygonPoints.forEach((pt, idx) => {
      pt.label = `Titik ${String.fromCharCode(65 + (idx % 26))}`;
    });
    updateLivePolygonVisual();
  }

  function updateLivePolygonVisual() {
    // 1. Calculate Area & Perimeter
    let areaStats;
    if (activeSearchAreaShape === 'circle') {
      const radiusKm = polyCircleRadiusNm * 1.852;
      const sqNm = Math.PI * Math.pow(polyCircleRadiusNm, 2);
      const sqKm = Math.PI * Math.pow(radiusKm, 2);
      const perimeterNm = 2 * Math.PI * polyCircleRadiusNm;
      const perimeterKm = 2 * Math.PI * radiusKm;
      areaStats = {
        sqNm: sqNm,
        sqKm: sqKm,
        hectares: sqKm * 100,
        perimeterNm: perimeterNm,
        perimeterKm: perimeterKm
      };
    } else if (activeSearchAreaShape === 'rectangle') {
      const sqNm = polyRectWidthNm * polyRectHeightNm;
      const sqKm = (polyRectWidthNm * 1.852) * (polyRectHeightNm * 1.852);
      const perimeterNm = 2 * (polyRectWidthNm + polyRectHeightNm);
      const perimeterKm = 2 * ((polyRectWidthNm * 1.852) + (polyRectHeightNm * 1.852));
      areaStats = {
        sqNm: sqNm,
        sqKm: sqKm,
        hectares: sqKm * 100,
        perimeterNm: perimeterNm,
        perimeterKm: perimeterKm
      };
    } else {
      areaStats = calculatePolygonSphericalArea(activePolygonPoints);
    }

    const areaFmt = formatDynamicArea(areaStats.sqNm, areaStats.sqKm);
    const perimFmt = formatDynamicPerimeter(areaStats.perimeterNm, areaStats.perimeterKm);

    const elSqNm = document.getElementById('polyLiveSqNm');
    const elSqKm = document.getElementById('polyLiveSqKm');
    const elPerimNm = document.getElementById('polyLivePerimeterNm');
    const elPerimKm = document.getElementById('polyLivePerimeterKm');

    if (elSqNm) elSqNm.textContent = areaFmt.primary;
    if (elSqKm) elSqKm.textContent = areaFmt.secondary;
    if (elPerimNm) elPerimNm.textContent = perimFmt.primary;
    if (elPerimKm) elPerimKm.textContent = perimFmt.secondary;

    // Nearest Posko detection
    const cPt = polyShapeCenter || (activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : null);
    if (cPt) {
      const dist = calculateDistanceHaversine(-4.037694, 122.486694, cPt.lat, cPt.lng);
      const distFormatted = formatDynamicDistance(dist.nm, dist.km);
      const elPosko = document.getElementById('polyLiveNearestPosko');
      if (elPosko) elPosko.textContent = `Kansar Kendari (${distFormatted}, ${dist.bearing}° ${dist.headingName})`;
    }

    // 2. Render Vertices Table / Circle Info
    const tableEl = document.getElementById('polyVerticesListTable');
    if (tableEl) {
      if (activeSearchAreaShape === 'circle') {
        const c = polyShapeCenter || (activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : null);
        const centerDms = c ? decimalToDms(c.lat, c.lng) : 'Klik pada peta untuk menetapkan titik pusat';
        let radDisplay = formatDynamicDistance(polyCircleRadiusNm, polyCircleRadiusNm * 1.852);
        tableEl.innerHTML = `
          <div class="p-3 rounded-xl bg-orange-500/10 border border-orange-500/20 space-y-2 text-xs">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 rounded-full bg-orange-500 text-white font-black text-xs flex items-center justify-center shrink-0">📍</span>
              <div class="min-w-0">
                <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Titik Pusat Datum (LKP)</div>
                <div class="font-mono font-bold text-orange-600 dark:text-orange-400 text-[11px] truncate">${centerDms}</div>
              </div>
            </div>
            <div class="flex items-center justify-between pt-1 border-t border-orange-500/15 text-[11px]">
              <span class="text-slate-500 dark:text-slate-400">Radius Sektor:</span>
              <span class="font-mono font-bold text-slate-800 dark:text-slate-200">${radDisplay}</span>
            </div>
            <div class="text-[11px] text-slate-400 italic">💡 Geser pin pusat di atas peta untuk memindahkan posisi radius lingkaran secara presisi.</div>
          </div>
        `;
      } else {
        tableEl.innerHTML = activePolygonPoints
          .map(
            (pt, idx) => `
          <div class="flex items-center justify-between p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-[11px]">
            <div class="flex items-center gap-2">
              <span class="w-5 h-5 rounded-full bg-orange-500 text-white font-bold text-[11px] flex items-center justify-center">${String.fromCharCode(65 + (idx % 26))}</span>
              <span class="font-bold text-white">${pt.label}</span>
            </div>
            <div class="text-[11px] font-mono text-slate-400">${decimalToDms(pt.lat, pt.lng)}</div>
            <button type="button" onclick="window.SAR_GIS.removeVertex(${idx})" class="text-slate-500 hover:text-red-400 p-0.5">✕</button>
          </div>
        `
          )
          .join('');
      }
    }

    // 3. Render Map Polygon / Circle Overlay
    if (activeDrawingPolygonOverlay) {
      map.removeLayer(activeDrawingPolygonOverlay);
      activeDrawingPolygonOverlay = null;
    }

    if (activeSearchAreaShape === 'circle') {
      const c = polyShapeCenter || (activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : null);
      if (c) {
        activeDrawingPolygonOverlay = L.circle([c.lat, c.lng], {
          radius: polyCircleRadiusNm * 1852,
          color: polyStrokeColor,
          weight: polyStrokeWeight,
          fillColor: polyFillColor,
          fillOpacity: polyFillOpacity
        }).addTo(map);
      }
    } else if (activePolygonPoints.length >= 2) {
      const latlngs = activePolygonPoints.map((p) => [p.lat, p.lng]);
      activeDrawingPolygonOverlay = L.polygon(latlngs, {
        color: polyStrokeColor,
        weight: polyStrokeWeight,
        fillColor: polyFillColor,
        fillOpacity: polyFillOpacity
      }).addTo(map);
    }

    // 4. Render Handles: Circle = HANYA 1 center marker; Polygon/Rect = vertex markers
    activeDrawingVertexMarkers.forEach((m) => map.removeLayer(m));
    activeDrawingVertexMarkers = [];

    if (activeSearchAreaShape === 'circle') {
      const c = polyShapeCenter || (activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : null);
      if (c) {
        const marker = L.marker([c.lat, c.lng], {
          draggable: true,
          zIndexOffset: 1250
        }).addTo(map);

        marker.setIcon(
          L.divIcon({
            html: `<div class="w-7 h-7 rounded-full bg-orange-600 text-white font-black flex items-center justify-center border-2 border-white shadow-lg text-xs">📍</div>`,
            className: 'sar-circle-center-pin',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
          })
        );

        marker.on('dragend', (e) => {
          const pos = e.target.getLatLng();
          polyShapeCenter = { lat: pos.lat, lng: pos.lng };
          activePolygonPoints = generateCircleVertices(pos.lat, pos.lng, polyCircleRadiusNm);
          updateLivePolygonVisual();
        });

        activeDrawingVertexMarkers.push(marker);
      }
    } else {
      activePolygonPoints.forEach((pt, idx) => {
        const letter = String.fromCharCode(65 + (idx % 26));
        const marker = L.marker([pt.lat, pt.lng], {
          draggable: true,
          zIndexOffset: 1200
        }).addTo(map);

        marker.setIcon(
          L.divIcon({
            html: createVertexBadgeHtml(letter, polyStrokeColor, false),
            className: 'sar-vertex-pin',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
          })
        );

        marker.on('dragend', (e) => {
          const pos = e.target.getLatLng();
          pt.lat = pos.lat;
          pt.lng = pos.lng;
          updateLivePolygonVisual();
        });

        activeDrawingVertexMarkers.push(marker);
      });
    }
  }

  async function handleSavePolygon() {
    let name = document.getElementById('polyEditorName').value.trim();
    if (!name) {
      name = `SEARCH AREA SRU ${savedPolygons.length + 1}`;
    }

    if (activePolygonPoints.length < 3) {
      showToast('Poligon area minimal membutuhkan 3 titik sudut (A, B, C)!', 'error');
      return;
    }

    const targetOpId = (filterMode === 'operasi' && currentOperasiId)
      ? currentOperasiId
      : (editingPolygonId ? (rawAllPolygons.find(p => p.id === editingPolygonId)?.id_operasi || null) : null);

    const areaStats = calculatePolygonSphericalArea(activePolygonPoints);
    const centroid = activePolygonPoints.length ? getPolygonCentroid(activePolygonPoints) : { lat: 0, lng: 0 };
    const centerLat = polyShapeCenter ? polyShapeCenter.lat : centroid.lat;
    const centerLng = polyShapeCenter ? polyShapeCenter.lng : centroid.lng;

    let exactAreaNm2 = areaStats.sqNm;
    if (activeSearchAreaShape === 'circle') {
      exactAreaNm2 = Math.PI * Math.pow(polyCircleRadiusNm, 2);
    } else if (activeSearchAreaShape === 'rectangle') {
      exactAreaNm2 = polyRectWidthNm * polyRectHeightNm;
    }

    const payload = {
      id: editingPolygonId || `poly-${Date.now()}`,
      id_operasi: targetOpId,
      name: name,
      points: activePolygonPoints,
      areaNm2: parseFloat(exactAreaNm2.toFixed(2)),
      strokeColor: polyStrokeColor,
      strokeWeight: polyStrokeWeight,
      fillColor: polyFillColor,
      fillOpacity: polyFillOpacity,
      shapeType: activeSearchAreaShape,
      centerLat: centerLat,
      centerLng: centerLng,
      radiusNm: activeSearchAreaShape === 'circle' ? polyCircleRadiusNm : null,
      boxWidthNm: activeSearchAreaShape === 'rectangle' ? polyRectWidthNm : null,
      boxHeightNm: activeSearchAreaShape === 'rectangle' ? polyRectHeightNm : null
    };

    try {
      await gisFetch(`${BASE_URL}/api/gis/polygons`, {
        method: 'POST',
        body: JSON.stringify(payload)
      });

      updateRawPolygon(payload, false);
      cancelPolygonDrawing();
      renderSavedPolygons();
    } catch (err) {
      updateRawPolygon(payload, false);
      cancelPolygonDrawing();
      renderSavedPolygons();
    }
  }

  function renderSavedPolygons() {
    const badge = document.getElementById('polygonsBadgeCount');
    if (badge) badge.textContent = String(savedPolygons.length);

    // Remove existing
    polygonOverlaysMap.forEach((poly) => map.removeLayer(poly));
    polygonOverlaysMap.clear();

    // Render on map with robust defensive checks (respecting hiddenPolygonIds)
    savedPolygons.forEach((poly) => {
      try {
        if (isIdHidden(hiddenPolygonIds, poly.id)) return;

        const isCircle = poly.shapeType === 'circle';
        let overlay = null;
        let stats = null;
        const safePolyId = escapeHtml(poly.id);
        const safePolyName = escapeHtml(poly.name);

        if (isCircle) {
          const cLat = poly.centerLat ? parseFloat(poly.centerLat) : (poly.points && poly.points[0] ? poly.points[0].lat : 0);
          const cLng = poly.centerLng ? parseFloat(poly.centerLng) : (poly.points && poly.points[0] ? poly.points[0].lng : 0);
          const rNm = parseFloat(poly.radiusNm) || 1.0;
          const radiusMeters = rNm * 1852;
          overlay = L.circle([cLat, cLng], {
            radius: radiusMeters,
            color: poly.strokeColor || '#ea580c',
            weight: poly.strokeWeight || 2.5,
            fillColor: poly.fillColor || '#ea580c',
            fillOpacity: poly.fillOpacity || 0.2
          }).addTo(map);

          const sqNm = Math.PI * Math.pow(rNm, 2);
          const sqKm = sqNm * 3.4299;
          const perimNm = 2 * Math.PI * rNm;
          const perimKm = perimNm * 1.852;
          stats = {
            sqNm: sqNm.toFixed(2),
            sqKm: sqKm.toFixed(2),
            perimeterNm: perimNm.toFixed(2),
            perimeterKm: perimKm.toFixed(2)
          };

          const areaFmt = formatDynamicArea(sqNm, sqKm);
          const perimFmt = formatDynamicPerimeter(perimNm, perimKm);
          const radStr = formatDynamicDistance(rNm, rNm * 1.852);

          overlay.bindPopup(`
            <div class="p-3 text-xs space-y-1">
              <div class="font-bold text-orange-400 text-sm">⭕ ${safePolyName}</div>
              <div class="text-[11px] text-slate-300">Radius: <strong>${radStr}</strong></div>
              <div class="text-[11px] text-slate-300">Luas Sektor: <strong>${areaFmt.combined}</strong></div>
              <div class="text-[11px] text-slate-300">Keliling: <strong>${perimFmt.combined}</strong></div>
              <div class="text-[11px] text-slate-400 font-mono">Pusat LKP: ${decimalToDms(cLat, cLng)}</div>
              <div class="pt-2">
                <button type="button" onclick="window.SAR_GIS.applyPatternFromPolygon('${safePolyId}')" class="w-full py-1.5 px-2 rounded-lg bg-orange-600 hover:bg-orange-500 text-white font-bold text-[11px] flex items-center justify-center gap-1 shadow-xs cursor-pointer">
                  <span>🚀 Buat Pola Lintasan SAR</span>
                </button>
              </div>
            </div>
          `);
        } else {
          const points = Array.isArray(poly.points) ? poly.points : [];
          if (points.length < 3) return;

          const latlngs = points.map((p) => [p.lat, p.lng]);
          overlay = L.polygon(latlngs, {
            color: poly.strokeColor || '#ea580c',
            weight: poly.strokeWeight || 2.5,
            fillColor: poly.fillColor || '#ea580c',
            fillOpacity: poly.fillOpacity || 0.2
          }).addTo(map);

          if (poly.shapeType === 'rectangle' || poly.shapeType === 'box') {
            const wNm = parseFloat(poly.boxWidthNm) || 2.0;
            const hNm = parseFloat(poly.boxHeightNm) || 2.0;
            const sqNm = poly.areaNm2 ? parseFloat(poly.areaNm2) : (wNm * hNm);
            const sqKm = (wNm * 1.852) * (hNm * 1.852);
            stats = {
              sqNm: sqNm.toFixed(2),
              sqKm: sqKm.toFixed(2),
              perimeterNm: (2 * (wNm + hNm)).toFixed(2),
              perimeterKm: (2 * ((wNm * 1.852) + (hNm * 1.852))).toFixed(2)
            };
          } else {
            stats = calculatePolygonSphericalArea(points);
          }
          const areaFmt = formatDynamicArea(stats.sqNm, stats.sqKm);
          const perimFmt = formatDynamicPerimeter(stats.perimeterNm, stats.perimeterKm);

          overlay.bindPopup(`
            <div class="p-3 text-xs space-y-1">
              <div class="font-bold text-orange-400 text-sm">${safePolyName}</div>
              <div class="text-[11px] text-slate-300">Luas: <strong>${areaFmt.combined}</strong></div>
              <div class="text-[11px] text-slate-300">Keliling: <strong>${perimFmt.combined}</strong></div>
              <div class="text-[11px] text-slate-400 font-mono">${points.length} Sudut Vertices</div>
              <div class="pt-2">
                <button type="button" onclick="window.SAR_GIS.applyPatternFromPolygon('${safePolyId}')" class="w-full py-1.5 px-2 rounded-lg bg-orange-600 hover:bg-orange-500 text-white font-bold text-[11px] flex items-center justify-center gap-1 shadow-xs cursor-pointer">
                  <span>🚀 Buat Pola Lintasan SAR</span>
                </button>
              </div>
            </div>
          `);
        }

        // Forward click to map drawing/measure handler when in drawing or measuring mode
        overlay.on('click', (e) => {
          if (isMeasureModeActive || isDrawingPolygon || isCreatingPoint) {
            overlay.closePopup();
            setTimeout(() => overlay.closePopup(), 10);
            onMapClick(e);
            if (e.originalEvent) {
              L.DomEvent.stopPropagation(e.originalEvent);
            }
            return;
          }
        });

        overlay.on('popupopen', () => {
          if (isMeasureModeActive || isDrawingPolygon || isCreatingPoint) {
            overlay.closePopup();
          }
        });

        polygonOverlaysMap.set(poly.id, overlay);
      } catch (err) {
        console.warn('[GIS Warning] Gagal merender poligon:', poly, err);
      }
    });

    renderSavedPolygonsList();
    populatePolygonSelectOptions();
    updateGisBadges();
  }

  function renderSavedPolygonsList() {
    const container = document.getElementById('savedPolygonsListContainer');
    if (!container) return;

    if (savedPolygons.length === 0) {
      container.innerHTML = `<div class="p-4 text-center text-slate-500 text-xs italic">Belum ada poligon area tersimpan.</div>`;
      return;
    }

    container.innerHTML = savedPolygons
      .map((poly) => {
        const isHidden = isIdHidden(hiddenPolygonIds, poly.id);
        const shapeType = poly.shapeType || 'polygon';

        let stats;
        if (shapeType === 'circle') {
          const rNm = parseFloat(poly.radiusNm) || 1.0;
          const sqNm = Math.PI * Math.pow(rNm, 2);
          const sqKm = sqNm * 3.4299;
          const perimNm = 2 * Math.PI * rNm;
          const perimKm = perimNm * 1.852;
          stats = {
            sqNm: (poly.areaNm2 ? parseFloat(poly.areaNm2) : sqNm).toFixed(2),
            sqKm: sqKm.toFixed(2),
            perimeterNm: perimNm.toFixed(2),
            perimeterKm: perimKm.toFixed(2)
          };
        } else if (shapeType === 'rectangle' || shapeType === 'box') {
          const wNm = parseFloat(poly.boxWidthNm) || 2.0;
          const hNm = parseFloat(poly.boxHeightNm) || 2.0;
          const sqNm = wNm * hNm;
          const sqKm = (wNm * 1.852) * (hNm * 1.852);
          const perimNm = 2 * (wNm + hNm);
          const perimKm = 2 * ((wNm * 1.852) + (hNm * 1.852));
          stats = {
            sqNm: (poly.areaNm2 ? parseFloat(poly.areaNm2) : sqNm).toFixed(2),
            sqKm: sqKm.toFixed(2),
            perimeterNm: perimNm.toFixed(2),
            perimeterKm: perimKm.toFixed(2)
          };
        } else {
          stats = calculatePolygonSphericalArea(poly.points);
          if (poly.areaNm2) {
            stats.sqNm = parseFloat(poly.areaNm2).toFixed(2);
            stats.sqKm = (parseFloat(poly.areaNm2) * 3.4299).toFixed(2);
          }
        }

        const safeId = escapeHtml(poly.id);
        const safeName = escapeHtml(poly.name);
        const safeColor = escapeHtml(poly.strokeColor || '#ea580c');
        const shapeBadge = shapeType === 'circle'
          ? '<span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20 font-bold">⭕ Lingkaran</span>'
          : (shapeType === 'rectangle'
            ? '<span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 font-bold">⏹️ Persegi</span>'
            : '<span class="text-[11px] font-mono px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20 font-bold">📐 Bebas</span>');

        const areaFmt = formatDynamicArea(stats.sqNm, stats.sqKm);
        const perimFmt = formatDynamicPerimeter(stats.perimeterNm, stats.perimeterKm);

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="cursor-pointer flex-1 min-w-0" onclick="window.SAR_GIS.focusPolygon('${safeId}')">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="w-3 h-3 rounded-full shrink-0" style="background-color: ${safeColor};"></span>
                <span class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${safeName}</span>
                ${shapeBadge}
                ${getOperasiBadgeHtml(poly.id_operasi)}
              </div>
              <div class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                ${areaFmt.combined} • Keliling: ${perimFmt.combined}
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePolygonVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded cursor-pointer" title="${isHidden ? 'Tampilkan Search Area' : 'Sembunyikan Search Area'}">
                ${isHidden ? GIS_ICON.eyeOff : GIS_ICON.eye}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPolygon('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded cursor-pointer" title="Edit">
                ${GIS_ICON.edit}
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePolygon('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded cursor-pointer" title="Hapus">
                ${GIS_ICON.trash}
              </button>
            </div>
          </div>

          <button type="button" onclick="window.SAR_GIS.applyPatternFromPolygon('${poly.id}')" class="w-full py-1.5 px-2 rounded-xl bg-slate-100 hover:bg-orange-500/10 text-orange-600 dark:bg-slate-900 dark:hover:bg-orange-500/20 dark:text-orange-400 border border-slate-200 dark:border-slate-800 text-[11px] font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
            <span>🚀 Buat Pola SAR dari Area Ini</span>
          </button>
        </div>
      `;
      })
      .join('');
  }

  function populatePolygonSelectOptions() {
    const sel = document.getElementById('patternTargetPolygon');
    if (!sel) return;

    const currentVal = sel.value;
    let options = `<option value="datum_kansar">📍 Datum LKP Kansar Kendari</option>`;

    if (activePolygonPoints && activePolygonPoints.length >= 3) {
      options += `<option value="active_draft">Poligon Aktif Sedang Digambar (${activePolygonPoints.length} Sudut)</option>`;
    }

    savedPolygons.forEach((p) => {
      const shapeType = p.shapeType || 'polygon';
      let info = '';
      if (shapeType === 'circle') {
        const r = parseFloat(p.radiusNm || p.radius_nm) || 1.0;
        info = `⭕ Circle R: ${formatDynamicDistance(r, r * 1.852)}`;
      } else if (shapeType === 'rectangle' || shapeType === 'box') {
        const w = parseFloat(p.boxWidthNm || p.box_width_nm) || 2.0;
        const h = parseFloat(p.boxHeightNm || p.box_height_nm) || 2.0;
        if (activeGlobalUnit === 'KM') {
          info = `⏹️ Persegi ${(w * 1.852).toFixed(1)}x${(h * 1.852).toFixed(1)} km`;
        } else if (activeGlobalUnit === 'M') {
          info = `⏹️ Persegi ${Math.round(w * 1852)}x${Math.round(h * 1852)} m`;
        } else {
          info = `⏹️ Persegi ${w.toFixed(1)}x${h.toFixed(1)} NM`;
        }
      } else {
        const ptCount = (p.points || []).length;
        info = `📐 Poligon (${ptCount} Sudut)`;
      }
      options += `<option value="${p.id}">${info} - ${escapeHtml(p.name)}</option>`;
    });

    sel.innerHTML = options;
    if (currentVal && Array.from(sel.options).some((o) => o.value === currentVal)) {
      sel.value = currentVal;
    }
    updatePatternAutoCalculation();
  }

  // ==========================================
  // 8. SEARCH PATTERNS GENERATOR (IAMSAR)
  // ==========================================

  function initSearchPatternUI() {
    // New Pattern Button (Show Form, Hide Button)
    const btnNewPattern = document.getElementById('btnStartNewPattern');
    if (btnNewPattern) {
      btnNewPattern.addEventListener('click', () => {
        editingPatternId = null;
        const formTitle = document.getElementById('patternFormHeaderTitle');
        if (formTitle) formTitle.textContent = '📐 Parameter Pola Lintasan IAMSAR';
        const btnSave = document.getElementById('btnSavePatternConfirm');
        if (btnSave) btnSave.innerHTML = '<span>💾 Simpan Pola SAR</span>';
        const nameInput = document.getElementById('patternFormName');
        if (nameInput) {
          nameInput.value = '';
          nameInput.placeholder = 'CREAPING LANE 1/ESAR 1/';
        }
        const card = document.getElementById('patternCreationCard');
        if (card) card.classList.remove('hidden');
        btnNewPattern.classList.add('hidden');
        updatePatternAutoCalculation();
      });
    }

    // Cancel / Close Form Buttons
    const btnCancelTop = document.getElementById('btnCancelPatternFormTop');
    const btnCancelBottom = document.getElementById('btnCancelPatternForm');
    [btnCancelTop, btnCancelBottom].forEach((btn) => {
      if (btn) {
        btn.addEventListener('click', () => {
          clearCurrentPatternVisual();
          editingPatternId = null;
          const formTitle = document.getElementById('patternFormHeaderTitle');
          if (formTitle) formTitle.textContent = '📐 Parameter Pola Lintasan IAMSAR';
          const btnSave = document.getElementById('btnSavePatternConfirm');
          if (btnSave) btnSave.innerHTML = '<span>💾 Simpan Pola SAR</span>';
          const card = document.getElementById('patternCreationCard');
          if (card) card.classList.add('hidden');
          const btnNew = document.getElementById('btnStartNewPattern');
          if (btnNew) btnNew.classList.remove('hidden');
        });
      }
    });

    // Pattern type buttons (Theme Adaptive & Real-Time Replacement)
    document.querySelectorAll('.pat-type-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        activePatternType = btn.getAttribute('data-ptype');
        document.querySelectorAll('.pat-type-btn').forEach((b) => {
          b.className = 'pat-type-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-left hover:border-orange-500 transition';
        });
        btn.className = 'pat-type-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-left transition font-bold shadow-xs';
        const statusMsg = document.getElementById('patternStatusMsg');
        if (statusMsg) statusMsg.classList.add('hidden');

        // Dynamic label: Personel E-SAR vs Sweep
        const legsLabel = document.getElementById('patternLegsLabel');
        if (legsLabel) {
          legsLabel.textContent = (activePatternType === 'sector_search' || activePatternType === 'e_sar')
            ? 'Jumlah Personel E-SAR:'
            : 'Jumlah Sweep:';
        }

        updatePatternAutoCalculation();

        // REAL-TIME REPLACEMENT: Instantly regenerate new pattern so previous pattern is cleanly replaced!
        generateSearchPatternRoute();
      });
    });

    // E-SAR Orientation Buttons (Horizontal vs Vertical)
    const btnEsarH = document.getElementById('btnEsarOrientHorizontal');
    const btnEsarV = document.getElementById('btnEsarOrientVertical');

    function updateEsarDirectionButtonsUI() {
      document.querySelectorAll('.esar-dir-btn').forEach((btn) => {
        const dir = btn.getAttribute('data-direction') || btn.getAttribute('data-dir');
        const isActive = dir === activeEsarDirection;
        if (isActive) {
          btn.className = 'esar-dir-btn p-2 rounded-xl border border-sky-500 bg-sky-500/20 text-sky-700 dark:text-sky-300 font-bold transition shadow-xs cursor-pointer flex items-center justify-center gap-1 text-xs';
        } else {
          btn.className = 'esar-dir-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-sky-500 font-semibold transition cursor-pointer flex items-center justify-center gap-1 text-xs';
        }
      });

      // Toggle Horizontal vs Vertical direction button groups
      const hGroup = document.getElementById('esarDirHorizontalGroup');
      const vGroup = document.getElementById('esarDirVerticalGroup');
      if (hGroup && vGroup) {
        if (activeEsarOrientation === 'horizontal') {
          hGroup.classList.remove('hidden');
          vGroup.classList.add('hidden');
        } else {
          hGroup.classList.add('hidden');
          vGroup.classList.remove('hidden');
        }
      }

      if (btnEsarH) {
        btnEsarH.className = activeEsarOrientation === 'horizontal'
          ? 'esar-orient-btn p-2 rounded-xl border border-sky-500 bg-sky-500/20 text-sky-700 dark:text-sky-300 flex items-center justify-center gap-1.5 transition shadow-xs cursor-pointer font-bold'
          : 'esar-orient-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-sky-500 flex items-center justify-center gap-1.5 transition cursor-pointer';
      }
      if (btnEsarV) {
        btnEsarV.className = activeEsarOrientation === 'vertical'
          ? 'esar-orient-btn p-2 rounded-xl border border-sky-500 bg-sky-500/20 text-sky-700 dark:text-sky-300 flex items-center justify-center gap-1.5 transition shadow-xs cursor-pointer font-bold'
          : 'esar-orient-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:border-sky-500 flex items-center justify-center gap-1.5 transition cursor-pointer';
      }
    }

    [btnEsarH, btnEsarV].forEach((btn) => {
      if (btn) {
        btn.addEventListener('click', () => {
          activeEsarOrientation = btn.getAttribute('data-orientation') || 'horizontal';
          if (activeEsarOrientation === 'horizontal') {
            if (activeEsarDirection !== 'west_to_east' && activeEsarDirection !== 'east_to_west') {
              activeEsarDirection = 'west_to_east';
            }
          } else {
            if (activeEsarDirection !== 'north_to_south' && activeEsarDirection !== 'south_to_north') {
              activeEsarDirection = 'north_to_south';
            }
          }
          updateEsarDirectionButtonsUI();
          updatePatternAutoCalculation({ source: 'auto' });
          generateSearchPatternRoute();
        });
      }
    });

    // 4-Vector Direction Buttons for E-SAR (West->East, East->West, North->South, South->North)
    document.querySelectorAll('.esar-dir-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const dir = btn.getAttribute('data-direction') || btn.getAttribute('data-dir') || 'west_to_east';
        activeEsarDirection = dir;
        if (dir === 'north_to_south' || dir === 'south_to_north') {
          activeEsarOrientation = 'vertical';
        } else {
          activeEsarOrientation = 'horizontal';
        }
        updateEsarDirectionButtonsUI();
        updatePatternAutoCalculation({ source: 'auto' });
        generateSearchPatternRoute();
      });
    });

    // Activate default parallel_track button style
    const defaultPatBtn = document.querySelector('.pat-type-btn[data-ptype="parallel_track"]');
    if (defaultPatBtn) {
      defaultPatBtn.className = 'pat-type-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-left transition font-bold shadow-xs';
    }

    // Target polygon select listener
    const targetPolySel = document.getElementById('patternTargetPolygon');
    if (targetPolySel) {
      targetPolySel.addEventListener('change', () => {
        updatePatternAutoCalculation({ source: 'auto' });
        const selectedId = targetPolySel.value;
        const resolvedPts = resolveTargetPolygonPoints(selectedId);
        if (resolvedPts && resolvedPts.length >= 3 && map) {
          const validPts = resolvedPts.map((p) => [parseFloat(p.lat), parseFloat(p.lng)]).filter((p) => !isNaN(p[0]) && !isNaN(p[1]));
          if (validPts.length >= 3) {
            const bounds = L.latLngBounds(validPts);
            map.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
          }
        }
        // Real-time update if already generated
        if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
          generateSearchPatternRoute();
        }
      });
    }

    // Spacing unit select listener (NM, KM, M)
    const spacingUnitSel = document.getElementById('patternSpacingUnit');
    const spacingInput = document.getElementById('patternTrackSpacing');
    if (spacingUnitSel && spacingInput) {
      spacingUnitSel.addEventListener('change', (e) => {
        setGlobalMeasurementUnit(e.target.value);
        if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
          generateSearchPatternRoute();
        }
      });
    }

    // Two-Way Binding: Track Spacing Input -> Auto Calc Personnel
    if (spacingInput) {
      spacingInput.addEventListener('input', () => {
        updatePatternAutoCalculation({ source: 'spacing' });
        if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
          generateSearchPatternRoute();
        }
      });
    }

    // Two-Way Binding: Personnel Count Input -> Auto Calc Track Spacing
    const legsInput = document.getElementById('patternLegsCount');
    if (legsInput) {
      legsInput.addEventListener('input', () => {
        updatePatternAutoCalculation({ source: 'personnel' });
        if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
          generateSearchPatternRoute();
        }
      });
    }

    // Generate Button
    const btnGen = document.getElementById('btnGenerateSearchPattern');
    if (btnGen) {
      btnGen.addEventListener('click', generateSearchPatternRoute);
    }

    // Clear Button (Batal / Bersihkan)
    const btnClear = document.getElementById('btnClearCurrentPattern');
    if (btnClear) {
      btnClear.addEventListener('click', () => {
        clearCurrentPatternVisual();
        const card = document.getElementById('patternCreationCard');
        if (card) card.classList.add('hidden');
        const btnNew = document.getElementById('btnStartNewPattern');
        if (btnNew) btnNew.classList.remove('hidden');
      });
    }

    // Save Button
    const btnSave = document.getElementById('btnSavePatternConfirm');
    if (btnSave) {
      btnSave.addEventListener('click', handleSaveSearchPattern);
    }

    // Customization: Color picker, Preset Buttons, Opacity Slider, Line Weight & Style
    const patColorInput = document.getElementById('patternLineColor');
    if (patColorInput) {
      patColorInput.addEventListener('input', () => {
        updateActivePatternStyle();
      });
    }

    document.querySelectorAll('.pat-color-preset').forEach((btn) => {
      btn.addEventListener('click', () => {
        const col = btn.getAttribute('data-color');
        if (col && patColorInput) {
          patColorInput.value = col;
          updateActivePatternStyle();
        }
      });
    });

    const patOpacityInput = document.getElementById('patternLineOpacity');
    const patOpacityValDisplay = document.getElementById('patternOpacityValDisplay');
    if (patOpacityInput) {
      patOpacityInput.addEventListener('input', (e) => {
        const pct = Math.round(parseFloat(e.target.value) * 100);
        if (patOpacityValDisplay) patOpacityValDisplay.textContent = `${pct}%`;
        updateActivePatternStyle();
      });
    }

    const patWeightInput = document.getElementById('patternLineWeight');
    if (patWeightInput) {
      patWeightInput.addEventListener('change', () => {
        updateActivePatternStyle();
      });
    }

    const patStyleInput = document.getElementById('patternLineStyle');
    if (patStyleInput) {
      patStyleInput.addEventListener('change', () => {
        updateActivePatternStyle();
      });
    }

    // Only update preview metadata without generating pattern visual
    updatePatternAutoCalculation();
  }

  function updateActivePatternStyle() {
    if (!activePatternPolyline || !map) return;
    const colorInput = document.getElementById('patternLineColor');
    const opacityInput = document.getElementById('patternLineOpacity');
    const weightInput = document.getElementById('patternLineWeight');
    const styleInput = document.getElementById('patternLineStyle');

    const color = colorInput ? colorInput.value : '#0284c7';
    const opacity = opacityInput ? (parseFloat(opacityInput.value) || 0.9) : 0.85;
    const weight = weightInput ? (parseInt(weightInput.value, 10) || 3) : 3;
    const style = styleInput ? styleInput.value : 'dashed';
    const dashArray = style === 'dashed' ? '6, 6' : '';

    activePatternPolyline.setStyle({
      color: color,
      opacity: opacity,
      weight: weight,
      dashArray: dashArray
    });

    activePatternMarkers.forEach((m) => {
      if (m.setStyle) {
        m.setStyle({
          fillColor: color,
          fillOpacity: Math.min(1.0, opacity + 0.1)
        });
      }
    });
  }

  function resolveTargetPolygonPoints(targetPolyId) {
    if (targetPolyId === 'active_draft') {
      if (activePolygonPoints && activePolygonPoints.length >= 3) {
        return activePolygonPoints;
      }
      return null;
    }
    const poly = savedPolygons.find((p) => String(p.id) === String(targetPolyId));
    if (!poly) return null;

    if (poly.points && Array.isArray(poly.points) && poly.points.length >= 3) {
      return poly.points;
    }

    // Dynamic resolution for circle
    if (poly.shapeType === 'circle' && (poly.radiusNm || poly.radius_nm)) {
      const cLat = poly.centerLat != null ? parseFloat(poly.centerLat) : (poly.center_lat != null ? parseFloat(poly.center_lat) : null);
      const cLng = poly.centerLng != null ? parseFloat(poly.centerLng) : (poly.center_lng != null ? parseFloat(poly.center_lng) : null);
      const rNm = parseFloat(poly.radiusNm || poly.radius_nm) || 1.0;
      if (cLat != null && !isNaN(cLat) && cLng != null && !isNaN(cLng) && rNm > 0) {
        return generateCircleVertices(cLat, cLng, rNm, 36);
      }
    }

    // Dynamic resolution for rectangle/box
    if ((poly.shapeType === 'rectangle' || poly.shapeType === 'box') && (poly.boxWidthNm || poly.box_width_nm || poly.boxHeightNm || poly.box_height_nm)) {
      const cLat = poly.centerLat != null ? parseFloat(poly.centerLat) : (poly.center_lat != null ? parseFloat(poly.center_lat) : null);
      const cLng = poly.centerLng != null ? parseFloat(poly.centerLng) : (poly.center_lng != null ? parseFloat(poly.center_lng) : null);
      const wNm = parseFloat(poly.boxWidthNm || poly.box_width_nm) || 2.0;
      const hNm = parseFloat(poly.boxHeightNm || poly.box_height_nm) || 2.0;
      if (cLat != null && !isNaN(cLat) && cLng != null && !isNaN(cLng)) {
        return generateRectangleVertices(cLat, cLng, wNm, hNm);
      }
    }

    return null;
  }

  function updatePatternAutoCalculation(trigger = { source: 'auto' }) {
    if (!activePatternType) {
      activePatternType = 'parallel_track';
    }

    const polySel = document.getElementById('patternTargetPolygon');
    const targetPolyId = polySel ? polySel.value : 'datum_kansar';
    const spacingInput = document.getElementById('patternTrackSpacing');
    const unitSel = document.getElementById('patternSpacingUnit');
    const unit = unitSel ? unitSel.value : 'NM';
    patternSpacingUnit = unit;
    const rawSpacing = parseFloat(spacingInput ? spacingInput.value : '1.0') || 1.0;
    let spacingNm = rawSpacing;
    if (unit === 'KM') spacingNm = rawSpacing / 1.852;
    else if (unit === 'M') spacingNm = rawSpacing / 1852;
    const spacingKm = spacingNm * 1.852;

    const resolvedPoints = resolveTargetPolygonPoints(targetPolyId);
    let rawCoords = (resolvedPoints && resolvedPoints.length >= 3)
      ? resolvedPoints
      : [
        { lat: -3.98, lng: 122.50 },
        { lat: -3.98, lng: 122.62 },
        { lat: -4.08, lng: 122.62 },
        { lat: -4.08, lng: 122.50 }
      ];

    const coords = rawCoords.map((p) => ({
      lat: parseFloat(p.lat),
      lng: parseFloat(p.lng)
    })).filter((p) => !isNaN(p.lat) && !isNaN(p.lng));

    let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
    coords.forEach((p) => {
      if (p.lat < minLat) minLat = p.lat;
      if (p.lat > maxLat) maxLat = p.lat;
      if (p.lng < minLng) minLng = p.lng;
      if (p.lng > maxLng) maxLng = p.lng;
    });

    const midLat = (minLat + maxLat) / 2;
    const heightKm = Math.abs(maxLat - minLat) * 110.574;
    const widthKm = Math.abs(maxLng - minLng) * (111.32 * Math.cos((midLat * Math.PI) / 180));
    const heightNm = heightKm * 0.539957;
    const widthNm = widthKm * 0.539957;

    const spanDimNm = (activePatternType === 'creeping_line') ? widthNm : heightNm;
    const spanDimKm = (activePatternType === 'creeping_line') ? widthKm : heightKm;
    const activeSpan = unit === 'KM' ? spanDimKm : (unit === 'M' ? spanDimKm * 1000 : spanDimNm);

    const legsInput = document.getElementById('patternLegsCount');
    const badgeEl = document.getElementById('patternAutoBadge');
    const dimInfoEl = document.getElementById('patternDimInfo');
    const marginInfoEl = document.getElementById('patternMarginInfo');

    const isEsar = (activePatternType === 'sector_search' || activePatternType === 'e_sar');
    const esarWrapper = document.getElementById('esarOrientationWrapper');
    const esarPersonnelInfo = document.getElementById('patternEsarPersonnelInfo');
    const personnelReadout = document.getElementById('patternPersonnelCalcReadout');

    if (isEsar) {
      if (esarWrapper) esarWrapper.classList.remove('hidden');
      if (esarPersonnelInfo) esarPersonnelInfo.classList.remove('hidden');

      const areaStats = calculatePolygonSphericalArea(coords);
      const areaNm = areaStats.sqNm || (widthNm * heightNm);
      const perpSpanNm = activeEsarOrientation === 'horizontal' ? heightNm : widthNm;

      let effectiveSpacingNm = spacingNm;
      let effectivePersonnel = 5;

      if (trigger && trigger.source === 'personnel') {
        // TWO-WAY REACTIVE BINDING: User changed Personnel Count -> Re-calculate Track Spacing
        effectivePersonnel = Math.max(2, Math.min(50, parseInt(legsInput ? legsInput.value : '5', 10) || 5));
        effectiveSpacingNm = Math.max(0.005, perpSpanNm / Math.max(1, effectivePersonnel - 1));
        if (spacingInput) {
          if (unit === 'KM') spacingInput.value = (effectiveSpacingNm * 1.852).toFixed(2);
          else if (unit === 'M') spacingInput.value = Math.round(effectiveSpacingNm * 1852);
          else spacingInput.value = effectiveSpacingNm.toFixed(2);
        }
      } else if (trigger && trigger.source === 'spacing') {
        // TWO-WAY REACTIVE BINDING: User changed Track Spacing -> Re-calculate Personnel Count
        effectiveSpacingNm = Math.max(0.001, spacingNm);
        effectivePersonnel = Math.max(2, Math.min(50, Math.ceil(perpSpanNm / effectiveSpacingNm) + 1));
        if (legsInput) {
          legsInput.value = effectivePersonnel;
        }
      } else {
        // AUTO CALCULATION (Initial or Area change): Standard IAMSAR / Basarnas auto recommendation
        let autoSpacingNm = 0.10;
        if (areaNm <= 0.5) {
          autoSpacingNm = 0.05;
        } else if (areaNm <= 2.0) {
          autoSpacingNm = 0.10;
        } else {
          autoSpacingNm = 0.20;
        }

        if (spacingInput) {
          if (unit === 'KM') spacingInput.value = (autoSpacingNm * 1.852).toFixed(2);
          else if (unit === 'M') spacingInput.value = Math.round(autoSpacingNm * 1852);
          else spacingInput.value = autoSpacingNm.toFixed(2);
        }

        const numPersonnel = Math.max(2, Math.min(30, Math.ceil(perpSpanNm / autoSpacingNm) + 1));
        effectivePersonnel = numPersonnel;
        effectiveSpacingNm = autoSpacingNm;

        if (legsInput) {
          legsInput.value = numPersonnel;
        }
      }

      if (badgeEl) {
        badgeEl.textContent = `${effectivePersonnel} PERSONIL`;
      }
      if (personnelReadout) {
        let dirLabel = 'Barat ➔ Timur';
        if (activeEsarDirection === 'east_to_west') dirLabel = 'Timur ➔ Barat';
        else if (activeEsarDirection === 'north_to_south') dirLabel = 'Utara ➔ Selatan';
        else if (activeEsarDirection === 'south_to_north') dirLabel = 'Selatan ➔ Utara';
        personnelReadout.textContent = `Track: ${formatDynamicDistance(effectiveSpacingNm, effectiveSpacingNm * 1.852)} • ${effectivePersonnel} Personil (Arah: ${dirLabel})`;
      }
      if (marginInfoEl) {
        const marginVal = effectiveSpacingNm / 2;
        marginInfoEl.textContent = `${formatDynamicDistance(marginVal, marginVal * 1.852)} (S/2 Jarak Pandang Personil)`;
      }
    } else {
      if (esarWrapper) esarWrapper.classList.add('hidden');
      if (esarPersonnelInfo) esarPersonnelInfo.classList.add('hidden');

      // Recommended sweeps for marine search (Parallel Track & Creeping Line)
      let recommendedSweeps = 6;
      if (trigger && (trigger.source === 'personnel' || trigger.source === 'legs')) {
        recommendedSweeps = Math.max(2, Math.min(50, parseInt(legsInput ? legsInput.value : '6', 10) || 6));
        const newSpacing = Math.max(0.01, activeSpan / Math.max(1, recommendedSweeps - 1));
        if (spacingInput) {
          if (unit === 'KM') spacingInput.value = newSpacing.toFixed(2);
          else if (unit === 'M') spacingInput.value = Math.round(newSpacing);
          else spacingInput.value = newSpacing.toFixed(2);
        }
      } else {
        const availSpan = Math.max(0, activeSpan - rawSpacing);
        recommendedSweeps = Math.max(2, Math.min(50, Math.round(availSpan / Math.max(0.001, rawSpacing)) + 1));
        if (legsInput) {
          legsInput.value = recommendedSweeps;
        }
      }

      if (badgeEl) {
        badgeEl.textContent = `${recommendedSweeps} SWEEPS`;
      }
      if (marginInfoEl) {
        const marginVal = rawSpacing / 2;
        const marginNmVal = (unit === 'KM') ? (marginVal / 1.852) : ((unit === 'M') ? (marginVal / 1852) : marginVal);
        marginInfoEl.textContent = `${formatDynamicDistance(marginNmVal, marginNmVal * 1.852)} (S/2 Jarak Pandang)`;
      }
    }

    if (dimInfoEl) {
      if (activeGlobalUnit === 'KM') {
        dimInfoEl.textContent = `P: ${widthKm.toFixed(1)} km (${widthNm.toFixed(1)} NM) • L: ${heightKm.toFixed(1)} km (${heightNm.toFixed(1)} NM)`;
      } else if (activeGlobalUnit === 'M') {
        dimInfoEl.textContent = `P: ${Math.round(widthKm * 1000)} m • L: ${Math.round(heightKm * 1000)} m`;
      } else {
        dimInfoEl.textContent = `P: ${widthNm.toFixed(1)} NM (${widthKm.toFixed(1)} km) • L: ${heightNm.toFixed(1)} NM (${heightKm.toFixed(1)} km)`;
      }
    }
  }

  function generateSearchPatternRoute() {
    const statusMsg = document.getElementById('patternStatusMsg');
    if (!activePatternType) {
      activePatternType = 'parallel_track';
    }

    const polySel = document.getElementById('patternTargetPolygon');
    const targetPolyId = polySel ? polySel.value : 'datum_kansar';
    const spacingInput = document.getElementById('patternTrackSpacing');
    const unitSel = document.getElementById('patternSpacingUnit');
    const unit = unitSel ? unitSel.value : 'NM';
    const rawSpacing = parseFloat(spacingInput ? spacingInput.value : '1.0') || 1.0;
    let spacingNm = rawSpacing;
    if (unit === 'KM') spacingNm = rawSpacing / 1.852;
    else if (unit === 'M') spacingNm = rawSpacing / 1852;
    const legsInput = document.getElementById('patternLegsCount');
    const legs = parseInt(legsInput ? legsInput.value : '6', 10) || 6;

    const resolvedPoints = resolveTargetPolygonPoints(targetPolyId);

    let coords = [];
    const patOptions = {
      orientation: activeEsarOrientation,
      direction: activeEsarDirection
    };

    if (resolvedPoints && resolvedPoints.length >= 3) {
      coords = generateFittedPatternInsidePolygon(activePatternType, resolvedPoints, spacingNm, legs, patOptions);
    } else {
      coords = generateDatumPattern(activePatternType, null, spacingNm, legs, patOptions);
    }

    if (!coords || coords.length < 2) {
      if (statusMsg) {
        statusMsg.className = 'text-[11px] p-2.5 rounded-xl bg-amber-500/10 text-amber-500 border border-amber-500/30 flex items-center gap-1.5';
        statusMsg.innerHTML = '<span>⚠️ Gagal membuat koordinat lintasan SAR. Pastikan poligon memiliki minimal 3 titik sudut yang valid.</span>';
        statusMsg.classList.remove('hidden');
      }
      return;
    }

    activeGeneratedWaypoints = coords;

    // Calculate total distance along waypoints
    const totalNm = recalculateActivePatternDistance();

    // Show Results Card
    const elDist = document.getElementById('resPatternDistNm');
    if (elDist) {
      elDist.textContent = `${totalNm.toFixed(2)} NM (${(totalNm * 1.852).toFixed(2)} km)`;
    }
    const elTypeInfo = document.getElementById('resPatternTypeInfo');
    if (elTypeInfo) {
      elTypeInfo.textContent = (activePatternType === 'sector_search' || activePatternType === 'e_sar')
        ? `Pola Pencarian E-SAR (${legs} Personel Darat)`
        : `${getPatternDisplayName(activePatternType)} (${legs} Sapuan)`;
    }
    const elWp = document.getElementById('resPatternWpCount');
    if (elWp) {
      elWp.textContent = `${coords.length} WP`;
    }
    const resCard = document.getElementById('patternResultsCard');
    if (resCard) {
      resCard.classList.remove('hidden');
    }

    if (statusMsg) {
      const patTypeName = getPatternDisplayName(activePatternType);
      statusMsg.className = 'text-[11px] p-2.5 rounded-xl bg-sky-500/10 text-sky-400 border border-sky-500/30 flex items-center gap-1.5';
      statusMsg.innerHTML = `<span>✨ Pola Lintasan <b>${patTypeName}</b> berhasil digenerate (${coords.length} WP, ${totalNm.toFixed(2)} NM). Klik <b>Simpan Pola SAR</b> untuk menyimpan ke database.</span>`;
      statusMsg.classList.remove('hidden');
    }

    renderActivePatternOnMap();
  }

  // ==========================================
  // SAR PATTERN GEOMETRY & BOUNDARY ENGINE
  // ==========================================

  function isPointInPolygon(lat, lng, poly) {
    let inside = false;
    const n = poly.length;
    for (let i = 0, j = n - 1; i < n; j = i++) {
      const xi = poly[i].lng, yi = poly[i].lat;
      const xj = poly[j].lng, yj = poly[j].lat;
      const intersect = ((yi > lat) !== (yj > lat)) &&
        (lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  function getPolygonCentroid(poly) {
    let area = 0;
    let cx = 0;
    let cy = 0;
    const n = poly.length;

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const xi = poly[i].lng;
      const yi = poly[i].lat;
      const xj = poly[j].lng;
      const yj = poly[j].lat;
      const factor = (xi * yj - xj * yi);
      area += factor;
      cx += (xi + xj) * factor;
      cy += (yi + yj) * factor;
    }
    area = area / 2;

    if (Math.abs(area) > 1e-9) {
      cx = cx / (6 * area);
      cy = cy / (6 * area);
      if (isPointInPolygon(cy, cx, poly)) {
        return { lat: cy, lng: cx };
      }
    }

    let sumLat = 0, sumLng = 0;
    poly.forEach(p => { sumLat += p.lat; sumLng += p.lng; });
    const avgPt = { lat: sumLat / n, lng: sumLng / n };
    if (isPointInPolygon(avgPt.lat, avgPt.lng, poly)) {
      return avgPt;
    }
    return { lat: poly[0].lat, lng: poly[0].lng };
  }

  function getHorizontalIntersections(lat, poly) {
    const n = poly.length;
    const intersections = [];

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const p1 = poly[i];
      const p2 = poly[j];

      const minY = Math.min(p1.lat, p2.lat);
      const maxY = Math.max(p1.lat, p2.lat);

      if (lat >= minY && lat <= maxY && maxY !== minY) {
        const t = (lat - p1.lat) / (p2.lat - p1.lat);
        if (t >= 0 && t <= 1) {
          const lngInter = p1.lng + t * (p2.lng - p1.lng);
          intersections.push(lngInter);
        }
      }
    }

    intersections.sort((a, b) => a - b);
    const unique = [];
    for (let i = 0; i < intersections.length; i++) {
      if (i === 0 || Math.abs(intersections[i] - intersections[i - 1]) > 1e-6) {
        unique.push(intersections[i]);
      }
    }
    return unique;
  }

  function getVerticalIntersections(lng, poly) {
    const n = poly.length;
    const intersections = [];

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const p1 = poly[i];
      const p2 = poly[j];

      const minX = Math.min(p1.lng, p2.lng);
      const maxX = Math.max(p1.lng, p2.lng);

      if (lng >= minX && lng <= maxX && maxX !== minX) {
        const t = (lng - p1.lng) / (p2.lng - p1.lng);
        if (t >= 0 && t <= 1) {
          const latInter = p1.lat + t * (p2.lat - p1.lat);
          intersections.push(latInter);
        }
      }
    }

    intersections.sort((a, b) => a - b);
    const unique = [];
    for (let i = 0; i < intersections.length; i++) {
      if (i === 0 || Math.abs(intersections[i] - intersections[i - 1]) > 1e-6) {
        unique.push(intersections[i]);
      }
    }
    return unique;
  }

  function lineSegmentIntersection(p1, p2, p3, p4) {
    const dX1 = p2.lng - p1.lng;
    const dY1 = p2.lat - p1.lat;
    const dX2 = p4.lng - p3.lng;
    const dY2 = p4.lat - p3.lat;

    const denom = dX1 * dY2 - dY1 * dX2;
    if (Math.abs(denom) < 1e-12) return null;

    const t = ((p3.lng - p1.lng) * dY2 - (p3.lat - p1.lat) * dX2) / denom;
    const u = ((p3.lng - p1.lng) * dY1 - (p3.lat - p1.lat) * dX1) / denom;

    if (t >= 0 && t <= 1 && u >= 0 && u <= 1) {
      return {
        lat: p1.lat + t * dY1,
        lng: p1.lng + t * dX1,
        t: t
      };
    }
    return null;
  }

  function clipSegmentToPolygon(fromPt, toPt, poly, safeMarginDeg = 0.0005) {
    const n = poly.length;
    let closestInter = null;
    let minT = 1.0;

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const inter = lineSegmentIntersection(fromPt, toPt, poly[i], poly[j]);
      if (inter && inter.t < minT && inter.t > 1e-5) {
        minT = inter.t;
        closestInter = inter;
      }
    }

    if (closestInter) {
      const dist = Math.hypot(toPt.lat - fromPt.lat, toPt.lng - fromPt.lng) || 1e-5;
      const pullT = Math.max(0.01, minT - (safeMarginDeg / dist));
      const safeT = Math.max(0.05, Math.min(minT * 0.96, pullT));
      return {
        lat: fromPt.lat + safeT * (toPt.lat - fromPt.lat),
        lng: fromPt.lng + safeT * (toPt.lng - fromPt.lng),
        clipped: true
      };
    }

    if (isPointInPolygon(toPt.lat, toPt.lng, poly)) {
      return { lat: toPt.lat, lng: toPt.lng, clipped: false };
    }

    return {
      lat: fromPt.lat + 0.9 * (toPt.lat - fromPt.lat),
      lng: fromPt.lng + 0.9 * (toPt.lng - fromPt.lng),
      clipped: true
    };
  }

  function generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount, options = {}) {
    if (window.QrSarGis && window.QrSarGis.Patterns && typeof window.QrSarGis.Patterns.generateFittedPatternInsidePolygon === 'function') {
      return window.QrSarGis.Patterns.generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount, options);
    }
    const cleanPoints = (polygonPoints || []).map((p) => ({
      lat: parseFloat(p.lat),
      lng: parseFloat(p.lng),
      label: p.label || ''
    })).filter((p) => !isNaN(p.lat) && !isNaN(p.lng));

    if (cleanPoints.length < 3) return [];

    let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
    cleanPoints.forEach((p) => {
      if (p.lat < minLat) minLat = p.lat;
      if (p.lat > maxLat) maxLat = p.lat;
      if (p.lng < minLng) minLng = p.lng;
      if (p.lng > maxLng) maxLng = p.lng;
    });

    const centroid = getPolygonCentroid(cleanPoints);
    const midLat = centroid.lat;
    const midLng = centroid.lng;
    const lngKmPerDeg = Math.max(0.1, 111.32 * Math.cos((midLat * Math.PI) / 180));
    const spacingKm = Math.max(0.1, spacingNm * 1.852);

    const marginLat = (spacingKm / 2) / 110.574;
    const marginLng = (spacingKm / 2) / lngKmPerDeg;

    const spanLat = Math.max(0.001, maxLat - minLat);
    const spanLng = Math.max(0.001, maxLng - minLng);
    const safeMarginLat = Math.min(marginLat, spanLat * 0.12);
    const safeMarginLng = Math.min(marginLng, spanLng * 0.12);

    const points = [];
    const effectiveLegs = Math.max(2, legsCount || 2);

    if (type === 'parallel_track') {
      const effMinLat = minLat + safeMarginLat;
      const effMaxLat = maxLat - safeMarginLat;
      const stepY = (effMaxLat - effMinLat) / Math.max(1, effectiveLegs - 1);

      for (let i = 0; i < effectiveLegs; i++) {
        const curLat = effMaxLat - i * stepY;
        const inters = getHorizontalIntersections(curLat, cleanPoints);
        if (inters.length >= 2) {
          const left = inters[0];
          const right = inters[inters.length - 1];
          const legSpan = right - left;
          const legMargin = Math.min(safeMarginLng, legSpan * 0.08);
          const west = left + legMargin;
          const east = right - legMargin;
          const sweepEast = i % 2 === 0;

          points.push({
            lat: curLat,
            lng: sweepEast ? west : east,
            label: i === 0 ? 'Entry (S)' : `Lintasan ${i + 1}A`
          });
          points.push({
            lat: curLat,
            lng: sweepEast ? east : west,
            label: i === effectiveLegs - 1 ? 'Exit (E)' : `Lintasan ${i + 1}B`
          });
        }
      }

      if (points.length < 2) {
        const stepY = spanLat / Math.max(1, effectiveLegs - 1);
        for (let i = 0; i < effectiveLegs; i++) {
          const curLat = effMaxLat - i * stepY;
          const sweepEast = i % 2 === 0;
          points.push({ lat: curLat, lng: sweepEast ? minLng + safeMarginLng : maxLng - safeMarginLng, label: i === 0 ? 'Entry (S)' : `Lintasan ${i + 1}A` });
          points.push({ lat: curLat, lng: sweepEast ? maxLng - safeMarginLng : minLng + safeMarginLng, label: i === effectiveLegs - 1 ? 'Exit (E)' : `Lintasan ${i + 1}B` });
        }
      }
    } else if (type === 'creeping_line') {
      const effMinLng = minLng + safeMarginLng;
      const effMaxLng = maxLng - safeMarginLng;
      const stepX = (effMaxLng - effMinLng) / Math.max(1, effectiveLegs - 1);

      for (let i = 0; i < effectiveLegs; i++) {
        const curLng = effMinLng + i * stepX;
        const inters = getVerticalIntersections(curLng, cleanPoints);
        if (inters.length >= 2) {
          const bottom = inters[0];
          const top = inters[inters.length - 1];
          const legSpan = top - bottom;
          const legMargin = Math.min(safeMarginLat, legSpan * 0.08);
          const south = bottom + legMargin;
          const north = top - legMargin;
          const sweepSouth = i % 2 === 0;

          points.push({
            lat: sweepSouth ? north : south,
            lng: curLng,
            label: i === 0 ? 'Entry (S)' : `Sapu ${i + 1}A`
          });
          points.push({
            lat: sweepSouth ? south : north,
            lng: curLng,
            label: i === effectiveLegs - 1 ? 'Exit (E)' : `Sapu ${i + 1}B`
          });
        }
      }

      if (points.length < 2) {
        const stepX = spanLng / Math.max(1, effectiveLegs - 1);
        for (let i = 0; i < effectiveLegs; i++) {
          const curLng = effMinLng + i * stepX;
          const sweepSouth = i % 2 === 0;
          points.push({ lat: sweepSouth ? maxLat - safeMarginLat : minLat + safeMarginLat, lng: curLng, label: i === 0 ? 'Entry (S)' : `Sapu ${i + 1}A` });
          points.push({ lat: sweepSouth ? minLat + safeMarginLat : maxLat - safeMarginLat, lng: curLng, label: i === effectiveLegs - 1 ? 'Exit (E)' : `Sapu ${i + 1}B` });
        }
      }
    } else {
      // 3. Pola Pencarian E-SAR (Explorer SAR - Darat / Gunung Hutan)
      // Pola sapuan sejajar serentak beberapa personel regu (skirmish / sweep line)
      const personnelCount = Math.max(2, effectiveLegs || 5);
      const spanLatKm = (maxLat - minLat) * 110.574;
      const spanLngKm = (maxLng - minLng) * lngKmPerDeg;
      const orient = (options && options.orientation) ? options.orientation : (spanLatKm >= spanLngKm ? 'vertical' : 'horizontal');
      const dir = (options && options.direction) ? options.direction : (orient === 'vertical' ? 'north_to_south' : 'west_to_east');

      if (orient === 'vertical') {
        // Area disapu vertikal (Utara-Selatan atau Selatan-Utara): Seluruh personel berbaris sejajar Barat-Timur di baseline awal
        const effMinLng = minLng + safeMarginLng;
        const effMaxLng = maxLng - safeMarginLng;
        const stepX = (effMaxLng - effMinLng) / Math.max(1, personnelCount - 1);

        for (let i = 0; i < personnelCount; i++) {
          const curLng = effMinLng + i * stepX;
          const inters = getVerticalIntersections(curLng, cleanPoints);
          if (inters.length >= 2) {
            const bottom = inters[0] + safeMarginLat;
            const top = inters[inters.length - 1] - safeMarginLat;
            const pNum = i + 1;

            if (dir === 'south_to_north') {
              // Seluruh personel bergerak serentak dari baseline awal (Selatan/bottom) ke batas maju (Utara/top)
              points.push({ lat: bottom, lng: curLng, label: `P${pNum} Start (Baseline)`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'north', bearing: 0 });
              points.push({ lat: top, lng: curLng, label: `P${pNum} Batas Maju`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'north', bearing: 0 });
            } else {
              // Default: Seluruh personel bergerak serentak dari baseline awal (Utara/top) ke batas maju (Selatan/bottom)
              points.push({ lat: top, lng: curLng, label: `P${pNum} Start (Baseline)`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'south', bearing: 180 });
              points.push({ lat: bottom, lng: curLng, label: `P${pNum} Batas Maju`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'south', bearing: 180 });
            }
          }
        }
      } else {
        // Area disapu horizontal (Barat-Timur atau Timur-Barat): Seluruh personel berbaris sejajar Utara-Selatan di baseline awal
        const effMinLat = minLat + safeMarginLat;
        const effMaxLat = maxLat - safeMarginLat;
        const stepY = (effMaxLat - effMinLat) / Math.max(1, personnelCount - 1);

        for (let i = 0; i < personnelCount; i++) {
          const curLat = effMaxLat - i * stepY;
          const inters = getHorizontalIntersections(curLat, cleanPoints);
          if (inters.length >= 2) {
            const west = inters[0] + safeMarginLng;
            const east = inters[inters.length - 1] - safeMarginLng;
            const pNum = i + 1;

            if (dir === 'east_to_west') {
              // Seluruh personel bergerak serentak dari baseline awal (Timur/east) ke batas maju (Barat/west)
              points.push({ lat: curLat, lng: east, label: `P${pNum} Start (Baseline)`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'west', bearing: 270 });
              points.push({ lat: curLat, lng: west, label: `P${pNum} Batas Maju`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'west', bearing: 270 });
            } else {
              // Default: Seluruh personel bergerak serentak dari baseline awal (Barat/west) ke batas maju (Timur/east)
              points.push({ lat: curLat, lng: west, label: `P${pNum} Start (Baseline)`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'east', bearing: 90 });
              points.push({ lat: curLat, lng: east, label: `P${pNum} Batas Maju`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'east', bearing: 90 });
            }
          }
        }
      }
    }

    // Final safety check: guarantee all waypoints are strictly inside polygon boundaries and preserve metadata
    return points.map((p) => {
      if (!isPointInPolygon(p.lat, p.lng, cleanPoints)) {
        return {
          ...p,
          lat: p.lat * 0.88 + centroid.lat * 0.12,
          lng: p.lng * 0.88 + centroid.lng * 0.12
        };
      }
      return p;
    });
  }

  function generateDatumPattern(type, center, spacingNm, legsCount, options = {}) {
    let c = center;
    if (!c || typeof c.lat !== 'number' || isNaN(c.lat)) {
      const lkpPoint = savedPoints.find((p) => p.category === 'LKP' || p.pointType === 'lkp');
      if (lkpPoint && !isNaN(parseFloat(lkpPoint.lat))) {
        c = { lat: parseFloat(lkpPoint.lat), lng: parseFloat(lkpPoint.lng) };
      } else if (map) {
        const mc = map.getCenter();
        c = { lat: mc.lat, lng: mc.lng };
      } else {
        c = { lat: -4.037694, lng: 122.486694 };
      }
    } else {
      c = { lat: parseFloat(c.lat), lng: parseFloat(c.lng) };
    }

    if (window.QrSarGis && window.QrSarGis.Patterns && typeof window.QrSarGis.Patterns.generateDatumPattern === 'function') {
      return window.QrSarGis.Patterns.generateDatumPattern(type, c, spacingNm, legsCount, options);
    }
    const spanDeg = Math.max(0.08, (spacingNm * Math.max(3, legsCount || 4) * 1.852) / 110.574);
    const fakePolygon = [
      { lat: c.lat + spanDeg / 2, lng: c.lng - spanDeg / 2 },
      { lat: c.lat + spanDeg / 2, lng: c.lng + spanDeg / 2 },
      { lat: c.lat - spanDeg / 2, lng: c.lng + spanDeg / 2 },
      { lat: c.lat - spanDeg / 2, lng: c.lng - spanDeg / 2 }
    ];
    return generateFittedPatternInsidePolygon(type, fakePolygon, spacingNm, legsCount, options);
  }

  function getPatternPolylineCoords(type, waypoints) {
    if (window.QrSarGis && window.QrSarGis.Patterns && typeof window.QrSarGis.Patterns.getPatternPolylineCoords === 'function') {
      return window.QrSarGis.Patterns.getPatternPolylineCoords(type, waypoints);
    }
    if (!waypoints || waypoints.length < 2) return [];
    if (type === 'sector_search' || type === 'e_sar') {
      const segments = [];
      const baseline = [];
      for (let i = 0; i < waypoints.length - 1; i += 2) {
        const pStart = waypoints[i];
        const pFin = waypoints[i + 1];
        if (pStart && pFin) {
          segments.push([
            [parseFloat(pStart.lat), parseFloat(pStart.lng)],
            [parseFloat(pFin.lat), parseFloat(pFin.lng)]
          ]);
          baseline.push([parseFloat(pStart.lat), parseFloat(pStart.lng)]);
        }
      }
      if (baseline.length >= 2) {
        segments.push(baseline);
      }
      return segments;
    }
    return waypoints.map((p) => [parseFloat(p.lat), parseFloat(p.lng)]);
  }

  function recalculateActivePatternDistance() {
    if (!activeGeneratedWaypoints || activeGeneratedWaypoints.length < 2) return 0;
    let totalNm = 0;
    if (activePatternType === 'sector_search' || activePatternType === 'e_sar') {
      for (let i = 0; i < activeGeneratedWaypoints.length - 1; i += 2) {
        const p1 = activeGeneratedWaypoints[i];
        const p2 = activeGeneratedWaypoints[i + 1];
        if (p1 && p2) {
          totalNm += calculateDistanceHaversine(parseFloat(p1.lat), parseFloat(p1.lng), parseFloat(p2.lat), parseFloat(p2.lng)).nm;
        }
      }
    } else {
      for (let i = 0; i < activeGeneratedWaypoints.length - 1; i++) {
        const p1 = activeGeneratedWaypoints[i];
        const p2 = activeGeneratedWaypoints[i + 1];
        totalNm += calculateDistanceHaversine(parseFloat(p1.lat), parseFloat(p1.lng), parseFloat(p2.lat), parseFloat(p2.lng)).nm;
      }
    }
    activeGeneratedDistanceNm = totalNm;

    const elDist = document.getElementById('resPatternDistNm');
    if (elDist) {
      elDist.textContent = formatDynamicDistance(totalNm, totalNm * 1.852);
    }
    const elWp = document.getElementById('resPatternWpCount');
    if (elWp) {
      elWp.textContent = `${activeGeneratedWaypoints.length} WP`;
    }

    // Luas Total Sapuan (IAMSAR Vol II Chapter 5: A = S * L)
    const spacingInput = document.getElementById('patternTrackSpacing');
    const unitSel = document.getElementById('patternSpacingUnit');
    const rawSpacing = parseFloat(spacingInput ? spacingInput.value : '1.0') || 1.0;
    const curSpacingUnit = unitSel ? unitSel.value : patternSpacingUnit;
    const spacingNm = (curSpacingUnit === 'KM') ? (rawSpacing / 1.852) : (curSpacingUnit === 'M' ? (rawSpacing / 1852) : rawSpacing);
    const areaSweptNm2 = totalNm * spacingNm;
    const areaSweptKm2 = areaSweptNm2 * 3.429904;
    const elAreaSwept = document.getElementById('resPatternAreaSwept');
    if (elAreaSwept) {
      elAreaSwept.textContent = formatDynamicArea(areaSweptNm2, areaSweptKm2).combined;
    }

    return totalNm;
  }

  function removeActivePatternLayers() {
    if (activePatternPolyline && map) {
      map.removeLayer(activePatternPolyline);
      activePatternPolyline = null;
    }
    activePatternMarkers.forEach((m) => {
      if (map) map.removeLayer(m);
    });
    activePatternMarkers = [];
    activeEsarArrowMarkers.forEach((m) => {
      if (map) map.removeLayer(m);
    });
    activeEsarArrowMarkers = [];
  }

  function renderActivePatternOnMap() {
    removeActivePatternLayers();

    if (!activeGeneratedWaypoints || activeGeneratedWaypoints.length < 2) return;

    const colorInput = document.getElementById('patternLineColor');
    const opacityInput = document.getElementById('patternLineOpacity');
    const weightInput = document.getElementById('patternLineWeight');
    const styleInput = document.getElementById('patternLineStyle');

    const lineColor = colorInput ? colorInput.value : '#0284c7';
    const lineOpacity = opacityInput ? (parseFloat(opacityInput.value) || 0.9) : 0.85;
    const lineWeight = weightInput ? (parseInt(weightInput.value, 10) || 3) : 3.5;
    const lineStyle = styleInput ? styleInput.value : 'dashed';
    const dashArr = lineStyle === 'dashed' ? '6, 6' : '';

    const polyCoords = getPatternPolylineCoords(activePatternType, activeGeneratedWaypoints);
    activePatternPolyline = L.polyline(polyCoords, {
      color: lineColor,
      weight: lineWeight,
      opacity: lineOpacity,
      dashArray: dashArr
    }).addTo(map);

    const isEsar = (activePatternType === 'sector_search' || activePatternType === 'e_sar');

    // Create directional arrows for E-SAR sweep lines
    if (isEsar) {
      for (let i = 0; i < activeGeneratedWaypoints.length - 1; i += 2) {
        const p1 = activeGeneratedWaypoints[i];
        const p2 = activeGeneratedWaypoints[i + 1];
        if (!p1 || !p2) continue;

        const lat1 = parseFloat(p1.lat);
        const lng1 = parseFloat(p1.lng);
        const lat2 = parseFloat(p2.lat);
        const lng2 = parseFloat(p2.lng);
        if (isNaN(lat1) || isNaN(lng1) || isNaN(lat2) || isNaN(lng2)) continue;

        // Position arrow marker at 65% of the sweep segment
        const arrowLat = lat1 + (lat2 - lat1) * 0.65;
        const arrowLng = lng1 + (lng2 - lng1) * 0.65;
        const distData = calculateDistanceHaversine(lat1, lng1, lat2, lng2);
        const brng = distData.bearing;

        const arrowSvg = `
          <div style="transform: rotate(${Math.round(brng)}deg); display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; pointer-events: none;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="${lineColor}" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.45));">
              <polygon points="12,2 22,22 12,17 2,22 12,2" />
            </svg>
          </div>
        `;

        const arrowMarker = L.marker([arrowLat, arrowLng], {
          icon: L.divIcon({
            html: arrowSvg,
            className: 'sar-esar-sweep-arrow',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
          }),
          interactive: false,
          zIndexOffset: 1450
        }).addTo(map);

        activeEsarArrowMarkers.push(arrowMarker);
      }
    }

    // Create DRAGGABLE markers for ALL waypoints
    activeGeneratedWaypoints.forEach((wp, idx) => {
      const lat = parseFloat(wp.lat);
      const lng = parseFloat(wp.lng);
      if (isNaN(lat) || isNaN(lng)) return;

      let iconHtml = '';
      let iconClass = 'sar-pat-draggable-marker';
      let iconSize = [24, 24];
      let iconAnchor = [12, 12];
      let zIndex = 1500;
      let defaultLabel = `WP-${idx + 1}`;

      if (isEsar) {
        const pIndex = Math.floor(idx / 2) + 1;
        const isStartOfPerson = (idx % 2 === 0);
        const pLabel = `P${pIndex}`;
        defaultLabel = isStartOfPerson ? `Personel ${pIndex} (Start Baseline)` : `Personel ${pIndex} (Batas Maju)`;
        iconHtml = createPatternCornerHtml(isStartOfPerson ? 'start' : 'end', lineColor, pLabel);
        zIndex = isStartOfPerson ? 1600 : 1550;
        iconSize = [26, 26];
        iconAnchor = [13, 13];
      } else {
        const isStart = idx === 0;
        const isEnd = idx === activeGeneratedWaypoints.length - 1;
        if (isStart || isEnd) {
          iconHtml = createPatternCornerHtml(isStart ? 'start' : 'end', lineColor);
          zIndex = 1800;
          iconSize = [24, 24];
          iconAnchor = [12, 12];
          defaultLabel = isStart ? 'Start (S)' : 'Exit (E)';
        } else {
          // Intermediate draggable waypoint handle
          iconHtml = `
            <div style="width: 14px; height: 14px; background: ${lineColor}; border: 2.5px solid #ffffff; border-radius: 50%; box-shadow: 0 1px 4px rgba(0,0,0,0.45); cursor: grab;"></div>
          `;
          zIndex = 1500;
          iconSize = [14, 14];
          iconAnchor = [7, 7];
        }
      }

      const marker = L.marker([lat, lng], {
        draggable: true,
        zIndexOffset: zIndex
      }).addTo(map);

      marker.setIcon(
        L.divIcon({
          html: iconHtml,
          className: iconClass,
          iconSize: iconSize,
          iconAnchor: iconAnchor
        })
      );

      const labelText = wp.label || defaultLabel;
      marker.bindTooltip(
        `<div class="text-left font-sans text-xs"><b>${escapeHtml(labelText)}</b><br><span class="text-[11px] text-amber-500 dark:text-amber-300">✋ Geser / drag untuk sesuaikan titik</span></div>`,
        { direction: 'top', offset: [0, -iconAnchor[1]] }
      );

      // Wire up drag events for real-time dynamic polyline update
      marker.on('drag', (e) => {
        const curLatLng = e.latlng;
        activeGeneratedWaypoints[idx].lat = curLatLng.lat;
        activeGeneratedWaypoints[idx].lng = curLatLng.lng;

        if (activePatternPolyline) {
          const updatedCoords = getPatternPolylineCoords(activePatternType, activeGeneratedWaypoints);
          activePatternPolyline.setLatLngs(updatedCoords);
        }
      });

      marker.on('dragend', (e) => {
        const curLatLng = e.latlng;
        recalculateActivePatternDistance();
        marker.setTooltipContent(
          `<div class="text-left font-sans text-xs"><b>${escapeHtml(labelText)}</b><br><span class="text-[11px] font-mono text-slate-400 dark:text-slate-300">${curLatLng.lat.toFixed(5)}, ${curLatLng.lng.toFixed(5)}</span><br><span class="text-[11px] text-amber-500 dark:text-amber-300">✋ Geser / drag untuk sesuaikan titik</span></div>`
        );
      });

      activePatternMarkers.push(marker);
    });

    if (activePatternPolyline && map) {
      map.fitBounds(activePatternPolyline.getBounds(), { padding: [50, 50], maxZoom: 16 });
    }
  }

  function clearCurrentPatternVisual() {
    const wasEditing = !!editingPatternId;
    editingPatternId = null;
    removeActivePatternLayers();
    activeGeneratedWaypoints = [];
    activeGeneratedDistanceNm = 0;
    const resCard = document.getElementById('patternResultsCard');
    if (resCard) resCard.classList.add('hidden');
    const statusMsg = document.getElementById('patternStatusMsg');
    if (statusMsg) {
      statusMsg.innerHTML = '';
      statusMsg.classList.add('hidden');
    }
    if (wasEditing) {
      renderSavedPatterns();
    }
  }

  function getPatternDisplayName(type) {
    if (type === 'parallel_track') return 'Parallel Track (PS)';
    if (type === 'creeping_line') return 'Creeping Line (CS)';
    if (type === 'sector_search' || type === 'e_sar') return 'Pola Pencarian E-SAR';
    return 'Lintasan IAMSAR';
  }

  async function handleSaveSearchPattern() {
    const statusMsg = document.getElementById('patternStatusMsg');
    const nameInput = document.getElementById('patternFormName');
    const name = nameInput ? nameInput.value.trim() : '';

    if (!activeGeneratedWaypoints || activeGeneratedWaypoints.length < 2) {
      if (statusMsg) {
        statusMsg.className = 'text-[11px] p-2.5 rounded-xl bg-amber-500/10 text-amber-400 border border-amber-500/30 flex items-center gap-1.5';
        statusMsg.innerHTML = '<span>⚠️ Harap lakukan generate pola SAR terlebih dahulu sebelum menyimpan!</span>';
        statusMsg.classList.remove('hidden');
      } else {
        showToast('Lintasan belum digenerate!', 'error');
      }
      return;
    }

    const defaultPatPrefix = (activePatternType === 'e_sar' || activePatternType === 'sector_search')
      ? 'ESAR'
      : (activePatternType === 'creeping_line' ? 'CREAPING LANE' : 'PARALLEL TRACK');
    const patName = name || `${defaultPatPrefix} ${savedPatterns.length + 1}`;

    const polySel = document.getElementById('patternTargetPolygon');
    const targetPolyId = polySel ? polySel.value : '';
    const targetPolygon = savedPolygons.find((p) => String(p.id) === String(targetPolyId));
    const polyName = targetPolygon ? targetPolygon.name : 'Datum LKP';

    const spacingInput = document.getElementById('patternTrackSpacing');
    const spacingVal = parseFloat(spacingInput ? spacingInput.value : '1.0') || 1.0;
    const legsVal = parseInt(document.getElementById('patternLegsCount').value, 10) || 6;

    const btnSave = document.getElementById('btnSavePatternConfirm');
    if (btnSave) {
      btnSave.disabled = true;
      btnSave.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span><span>Menyimpan ke Database...</span>';
    }

    const isEditing = !!editingPatternId;
    const finalId = editingPatternId || `pattern-${Date.now()}`;

    const targetOpId = (filterMode === 'operasi' && currentOperasiId)
      ? currentOperasiId
      : (editingPatternId ? (rawAllPatterns.find(p => String(p.id) === String(editingPatternId))?.id_operasi || null) : null);

    const colorInput = document.getElementById('patternLineColor');
    const opacityInput = document.getElementById('patternLineOpacity');
    const weightInput = document.getElementById('patternLineWeight');
    const styleInput = document.getElementById('patternLineStyle');

    const lineColor = colorInput ? colorInput.value : '#0284c7';
    const lineOpacity = opacityInput ? (parseFloat(opacityInput.value) || 0.9) : 0.85;
    const lineWeight = weightInput ? (parseInt(weightInput.value, 10) || 3) : 3;
    const lineStyle = styleInput ? styleInput.value : 'dashed';
    const dashArr = lineStyle === 'dashed' ? '6, 6' : '';

    const payload = {
      id: finalId,
      id_operasi: targetOpId,
      name: patName,
      polygonId: targetPolyId,
      polygonName: polyName,
      patternType: activePatternType,
      trackSpacingNm: patternSpacingUnit === 'KM' ? (spacingVal / 1.852) : (patternSpacingUnit === 'M' ? (spacingVal / 1852) : spacingVal),
      legsCount: legsVal,
      color: lineColor,
      opacity: lineOpacity,
      strokeWeight: lineWeight,
      dashArray: dashArr,
      waypoints: activeGeneratedWaypoints,
      totalDistanceNm: activeGeneratedDistanceNm,
      createdAt: `${window.formatTanggalIndo(new Date())} ${window.formatJamWita(new Date())} WITA`
    };

    try {
      const resp = await gisFetch(`${BASE_URL}/api/gis/search-patterns`, {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      const resJson = await resp.json().catch(() => ({}));
      if (resJson && resJson.id) {
        payload.id = resJson.id;
      }
      updateRawPattern(payload, false);
      renderSavedPatterns();

      if (btnSave) {
        btnSave.innerHTML = isEditing ? '<span>✓ Berhasil Diperbarui</span>' : '<span>✓ Berhasil Disimpan</span>';
      }

      setTimeout(() => {
        clearCurrentPatternVisual();
        editingPatternId = null;
        const card = document.getElementById('patternCreationCard');
        if (card) card.classList.add('hidden');
        const btnNew = document.getElementById('btnStartNewPattern');
        if (btnNew) btnNew.classList.remove('hidden');
        const formTitle = document.getElementById('patternFormHeaderTitle');
        if (formTitle) formTitle.textContent = '📐 Parameter Pola Lintasan IAMSAR';
        if (btnSave) {
          btnSave.disabled = false;
          btnSave.innerHTML = '<span>💾 Simpan Pola SAR</span>';
        }
      }, 600);
    } catch (err) {
      console.error('Error saving search pattern:', err);
      const existingIdx = savedPatterns.findIndex(p => String(p.id) === String(payload.id));
      if (existingIdx >= 0) {
        savedPatterns[existingIdx] = payload;
      } else {
        savedPatterns.unshift(payload);
      }
      renderSavedPatterns();

      setTimeout(() => {
        clearCurrentPatternVisual();
        editingPatternId = null;
        const card = document.getElementById('patternCreationCard');
        if (card) card.classList.add('hidden');
        const btnNew = document.getElementById('btnStartNewPattern');
        if (btnNew) btnNew.classList.remove('hidden');
        const formTitle = document.getElementById('patternFormHeaderTitle');
        if (formTitle) formTitle.textContent = '📐 Parameter Pola Lintasan IAMSAR';
        if (btnSave) {
          btnSave.disabled = false;
          btnSave.innerHTML = '<span>💾 Simpan Pola SAR</span>';
        }
      }, 600);
    }
  }

  function renderSavedPatterns() {
    updateGisBadges();
    patternPolylinesMap.forEach((pl) => map.removeLayer(pl));
    patternPolylinesMap.clear();

    savedPatternArrowMarkersMap.forEach((markers) => {
      markers.forEach((m) => { if (map) map.removeLayer(m); });
    });
    savedPatternArrowMarkersMap.clear();

    savedPatterns.forEach((pat) => {
      if (isIdHidden(hiddenPatternIds, pat.id)) return;
      if (editingPatternId && String(pat.id) === String(editingPatternId)) return;
      if (!pat.waypoints || pat.waypoints.length < 2) return;

      const latlngs = getPatternPolylineCoords(pat.patternType, pat.waypoints);
      const pl = L.polyline(latlngs, {
        color: pat.color || '#0284c7',
        weight: pat.strokeWeight ? Number(pat.strokeWeight) : 3,
        opacity: pat.opacity !== undefined && pat.opacity !== null ? Number(pat.opacity) : 0.85,
        dashArray: (pat.dashArray && pat.dashArray !== 'none') ? pat.dashArray : (pat.dashArray === '' ? '' : '5, 5')
      }).addTo(map);

      // Pass click to onMapClick when in drawing or measure mode
      pl.on('click', (e) => {
        if (isMeasureModeActive || isDrawingPolygon || isCreatingPoint) {
          onMapClick(e);
          if (e.originalEvent) L.DomEvent.stopPropagation(e.originalEvent);
        }
      });

      patternPolylinesMap.set(pat.id, pl);

      // Render directional arrows for E-SAR saved patterns
      if (pat.patternType === 'e_sar' || pat.patternType === 'sector_search') {
        const arrowMarkers = [];
        const wps = pat.waypoints;
        for (let i = 0; i < wps.length - 1; i += 2) {
          const p1 = wps[i];
          const p2 = wps[i + 1];
          if (!p1 || !p2) continue;

          const lat1 = parseFloat(p1.lat);
          const lng1 = parseFloat(p1.lng);
          const lat2 = parseFloat(p2.lat);
          const lng2 = parseFloat(p2.lng);
          if (isNaN(lat1) || isNaN(lng1) || isNaN(lat2) || isNaN(lng2)) continue;

          const arrowLat = lat1 + (lat2 - lat1) * 0.65;
          const arrowLng = lng1 + (lng2 - lng1) * 0.65;
          const distData = calculateDistanceHaversine(lat1, lng1, lat2, lng2);
          const brng = distData.bearing;

          const arrowSvg = `
            <div style="transform: rotate(${Math.round(brng)}deg); display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; pointer-events: none;">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="${pat.color || '#0284c7'}" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.45));">
                <polygon points="12,2 22,22 12,17 2,22 12,2" />
              </svg>
            </div>
          `;

          const arrowMarker = L.marker([arrowLat, arrowLng], {
            icon: L.divIcon({
              html: arrowSvg,
              className: 'sar-esar-sweep-arrow',
              iconSize: [28, 28],
              iconAnchor: [14, 14]
            }),
            interactive: false,
            zIndexOffset: 1450
          }).addTo(map);

          arrowMarkers.push(arrowMarker);
        }
        if (arrowMarkers.length > 0) {
          savedPatternArrowMarkersMap.set(pat.id, arrowMarkers);
        }
      }
    });

    renderSavedPatternsList();
  }

  function renderSavedPatternsList() {
    const container = document.getElementById('savedPatternsListContainer');
    if (!container) return;

    if (savedPatterns.length === 0) {
      container.innerHTML = `<div class="p-4 text-center text-slate-500 text-xs italic">Belum ada pola pencarian tersimpan.</div>`;
      return;
    }

    container.innerHTML = savedPatterns
      .map((pat) => {
        const isHidden = isIdHidden(hiddenPatternIds, pat.id);
        const safeId = escapeHtml(pat.id);
        const safeName = escapeHtml(pat.name);
        const patColor = pat.color || '#0284c7';
        const patOpacity = pat.opacity !== undefined && pat.opacity !== null ? pat.opacity : 0.85;

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="cursor-pointer flex-1 min-w-0" onclick="window.SAR_GIS.focusPattern('${safeId}')">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="w-2.5 h-2.5 rounded-full inline-block border border-white/30 shrink-0 shadow-xs" style="background-color: ${patColor}; opacity: ${patOpacity}"></span>
                <span class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${safeName}</span>
                ${getOperasiBadgeHtml(pat.id_operasi)}
              </div>
              <div class="text-[11px] font-mono text-sky-600 dark:text-sky-400 mt-0.5">
                ${pat.totalDistanceNm ? formatDynamicDistance(pat.totalDistanceNm, pat.totalDistanceNm * 1.852) : ''} • ${pat.waypoints ? pat.waypoints.length : 0} Waypoints • <span class="capitalize text-slate-500 dark:text-slate-400 font-sans">${getPatternDisplayName(pat.patternType)}</span>
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePatternVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded" title="${isHidden ? 'Tampilkan' : 'Sembunyikan'}">
                ${isHidden ? GIS_ICON.eyeOff : GIS_ICON.eye}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPattern('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded cursor-pointer transition" title="Edit Pola Ini">
                ${GIS_ICON.edit}
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePattern('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded cursor-pointer transition" title="Hapus">
                ${GIS_ICON.trash}
              </button>
            </div>
          </div>
        </div>
      `;
      })
      .join('');
  }

  function editPattern(id) {
    const pat = savedPatterns.find((p) => String(p.id) === String(id));
    if (!pat) return;

    editingPatternId = pat.id;

    // Immediately remove saved static polyline and arrows from map to prevent 2 overlapping patterns!
    if (patternPolylinesMap.has(pat.id)) {
      map.removeLayer(patternPolylinesMap.get(pat.id));
      patternPolylinesMap.delete(pat.id);
    }
    if (savedPatternArrowMarkersMap.has(pat.id)) {
      savedPatternArrowMarkersMap.get(pat.id).forEach((m) => { if (map) map.removeLayer(m); });
      savedPatternArrowMarkersMap.delete(pat.id);
    }

    switchActiveTab('tab-pattern');

    // Ensure sidebar is visible
    const sidebar = document.getElementById('gisSidebarPanel');
    const floatingBtn = document.getElementById('btnFloatingOpenSidebar');
    const iconClose = document.getElementById('iconSidebarClose');
    const iconOpen = document.getElementById('iconSidebarOpen');
    const label = document.getElementById('labelSidebarToggle');
    if (sidebar && sidebar.classList.contains('-ml-[390px]')) {
      isSidebarVisible = true;
      sidebar.classList.remove('-ml-[390px]');
      if (floatingBtn) floatingBtn.classList.add('hidden');
      if (iconClose) iconClose.classList.remove('hidden');
      if (iconOpen) iconOpen.classList.add('hidden');
      if (label) label.textContent = 'Sembunyikan Tools';
      setTimeout(() => { if (map) map.invalidateSize(); }, 200);
    }

    const card = document.getElementById('patternCreationCard');
    if (card) card.classList.remove('hidden');
    const btnNew = document.getElementById('btnStartNewPattern');
    if (btnNew) btnNew.classList.add('hidden');

    const nameInput = document.getElementById('patternFormName');
    if (nameInput) nameInput.value = pat.name || '';

    const polySel = document.getElementById('patternTargetPolygon');
    if (polySel && pat.polygonId) {
      polySel.value = pat.polygonId;
    }

    activePatternType = pat.patternType || 'parallel_track';
    document.querySelectorAll('.pat-type-btn').forEach((b) => {
      const ptype = b.getAttribute('data-ptype');
      if (ptype === activePatternType) {
        b.className = 'pat-type-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-left transition font-bold shadow-xs';
      } else {
        b.className = 'pat-type-btn p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 text-left hover:border-orange-500 transition';
      }
    });

    const legsLabel = document.getElementById('patternLegsLabel');
    if (legsLabel) {
      legsLabel.textContent = (activePatternType === 'sector_search' || activePatternType === 'e_sar')
        ? 'Jumlah Personel E-SAR:'
        : 'Jumlah Sweep:';
    }

    const spacingInput = document.getElementById('patternTrackSpacing');
    const unitSel = document.getElementById('patternSpacingUnit');
    if (unitSel) unitSel.value = 'NM';
    patternSpacingUnit = 'NM';
    if (spacingInput) spacingInput.value = pat.trackSpacingNm ? parseFloat(pat.trackSpacingNm).toFixed(2) : '1.0';

    const legsInput = document.getElementById('patternLegsCount');
    if (legsInput) legsInput.value = pat.legsCount || 6;

    // Load styling into form inputs
    const colorInput = document.getElementById('patternLineColor');
    if (colorInput && pat.color) {
      colorInput.value = pat.color;
    }
    const opacityInput = document.getElementById('patternLineOpacity');
    const opacityValDisplay = document.getElementById('patternOpacityValDisplay');
    if (opacityInput && pat.opacity !== undefined && pat.opacity !== null) {
      const opNum = parseFloat(pat.opacity);
      opacityInput.value = opNum > 1 ? (opNum / 100).toFixed(2) : opNum.toFixed(2);
      if (opacityValDisplay) {
        opacityValDisplay.textContent = `${Math.round(parseFloat(opacityInput.value) * 100)}%`;
      }
    }
    const weightInput = document.getElementById('patternLineWeight');
    if (weightInput && pat.strokeWeight) {
      weightInput.value = String(pat.strokeWeight);
    }
    const styleInput = document.getElementById('patternLineStyle');
    if (styleInput) {
      styleInput.value = (pat.dashArray && pat.dashArray !== 'none' && pat.dashArray !== '') ? 'dashed' : (pat.dashArray === '' ? 'solid' : 'dashed');
    }

    const formTitle = document.getElementById('patternFormHeaderTitle');
    if (formTitle) formTitle.textContent = 'Edit Parameter Pola Lintasan SAR';
    const btnSave = document.getElementById('btnSavePatternConfirm');
    if (btnSave) btnSave.innerHTML = '<span>💾 Perbarui Pola SAR</span>';

    if (Array.isArray(pat.waypoints) && pat.waypoints.length >= 2) {
      activeGeneratedWaypoints = pat.waypoints.map(w => ({ lat: parseFloat(w.lat), lng: parseFloat(w.lng), label: w.label || '' }));
      recalculateActivePatternDistance();
      renderActivePatternOnMap();

      const elDist = document.getElementById('resPatternDistNm');
      if (elDist) elDist.textContent = formatDynamicDistance(activeGeneratedDistanceNm, activeGeneratedDistanceNm * 1.852);
      const elTypeInfo = document.getElementById('resPatternTypeInfo');
      if (elTypeInfo) {
        elTypeInfo.textContent = (activePatternType === 'sector_search' || activePatternType === 'e_sar')
          ? `Pola Pencarian E-SAR (${pat.legsCount || 5} Personel Darat)`
          : `${getPatternDisplayName(activePatternType)} (${pat.legsCount || 6} Sapuan)`;
      }
      const elWp = document.getElementById('resPatternWpCount');
      if (elWp) elWp.textContent = `${activeGeneratedWaypoints.length} WP`;
      const resCard = document.getElementById('patternResultsCard');
      if (resCard) resCard.classList.remove('hidden');

      if (map) {
        const bounds = L.latLngBounds(activeGeneratedWaypoints.map(p => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
      }
    } else {
      generateSearchPatternRoute();
    }
  }

  // ==========================================
  // 9. MEASURE DISTANCE & SPEED SIMULATOR
  // (Jarak Lurus 2 Titik & Jalur Manual Multi-Titik)
  // ==========================================

  function setMeasureMode(mode) {
    measureModeType = mode === 'path' ? 'path' : 'straight';
    const btnStraight = document.getElementById('btnModeStraight');
    const btnPath = document.getElementById('btnModePath');
    const activeClass = 'p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-center transition font-bold shadow-xs';
    const inactiveClass = 'p-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-400 text-center hover:text-slate-900 dark:hover:text-white transition font-bold';

    if (btnStraight && btnPath) {
      if (measureModeType === 'straight') {
        btnStraight.className = activeClass;
        btnPath.className = inactiveClass;
      } else {
        btnPath.className = activeClass;
        btnStraight.className = inactiveClass;
      }
    }

    const routeCard = document.getElementById('routePlanningCard');
    if (routeCard && !routeCard.classList.contains('hidden')) {
      isMeasureModeActive = true;
    }

    if (measureModeType === 'straight' && measurePoints.length > 2) {
      measurePoints = measurePoints.slice(0, 2);
    }

    const counter = document.getElementById('manualPointsCounter');
    if (counter) {
      counter.textContent = `${measurePoints.length} Titik`;
    }

    const routeNameInput = document.getElementById('measureRouteNameInput');
    if (routeNameInput) {
      routeNameInput.placeholder = 'RUTE 1';
      if (!currentActiveRouteId) {
        // Keep empty during new route creation so placeholder is shown
        if (routeNameInput.value.startsWith('Jarak Lurus') || routeNameInput.value.startsWith('Jalur Manual')) {
          routeNameInput.value = '';
        }
      }
    }

    updateMeasureVisual();
  }

  function recalcRouteMetrics(points = measurePoints) {
    const elNm = document.getElementById('liveRouteDistNm');
    const elKm = document.getElementById('liveRouteDistKm');
    const elBrg = document.getElementById('liveRouteBearing');
    const elEta = document.getElementById('liveRouteEta');
    const elStartDms = document.getElementById('liveRouteStartDms');
    const elEndDms = document.getElementById('liveRouteEndDms');

    const speedUnitSel = document.getElementById('measureSpeedUnit');
    const currentUnit = speedUnitSel ? speedUnitSel.value : (speedUnit || 'knot');
    speedUnit = currentUnit;
    const speedInput = document.getElementById('measureVesselSpeed');
    const enteredSpeed = parseFloat(speedInput ? speedInput.value : '25') || 25;

    const distUnitSel = document.getElementById('measureDistanceUnit');
    if (distUnitSel) {
      activeDistanceUnit = distUnitSel.value || 'NM';
    }

    if (!points || !Array.isArray(points) || points.length < 2) {
      if (elNm) elNm.textContent = activeDistanceUnit === 'M' ? '0 Meter' : (activeDistanceUnit === 'KM' ? '0.00 km' : '0.00 NM');
      if (elKm) elKm.textContent = '0.00 km';
      if (elBrg) elBrg.textContent = '0° (Utara)';
      if (elEta) elEta.textContent = '0 Menit';
      if (elStartDms && points && points.length > 0) {
        elStartDms.textContent = decimalToDms(points[0].lat, points[0].lng);
      }
      return { totalKm: 0, totalNm: 0, lastBearing: 0, lastHeading: 'Utara', etaMins: 0 };
    }

    let totalKm = 0;
    let totalNm = 0;
    let lastBearing = 0;
    let lastHeading = 'Utara';

    for (let i = 0; i < points.length - 1; i++) {
      const p1 = points[i];
      const p2 = points[i + 1];
      const res = calculateDistanceHaversine(p1.lat, p1.lng, p2.lat, p2.lng);
      totalKm += res.km;
      totalNm += res.nm;
      lastBearing = res.bearing;
      lastHeading = res.headingName;
    }

    let etaMins = 0;
    if (currentUnit === 'kmh') {
      // Perhitungan berbasis Km/Jam
      const speedKmh = Math.max(0.1, enteredSpeed);
      vesselSpeedKnots = speedKmh / 1.852;
      etaMins = (totalKm / speedKmh) * 60;
    } else if (currentUnit === 'ms') {
      // Perhitungan berbasis Meter/Detik (m/s)
      const speedMs = Math.max(0.1, enteredSpeed);
      const speedKmh = speedMs * 3.6;
      vesselSpeedKnots = speedKmh / 1.852;
      etaMins = (totalKm / speedKmh) * 60;
    } else {
      // Perhitungan berbasis Knot (Kts)
      const speedKts = Math.max(0.1, enteredSpeed);
      vesselSpeedKnots = speedKts;
      etaMins = (totalNm / speedKts) * 60;
    }

    // Dynamic Distance Unit rendering
    if (activeDistanceUnit === 'KM') {
      if (elNm) {
        elNm.textContent = `${totalKm.toFixed(2)} km`;
        elNm.className = 'text-base font-black text-emerald-600 dark:text-emerald-400 font-mono';
      }
      if (elKm) {
        elKm.textContent = `${totalNm.toFixed(2)} NM (${Math.round(totalKm * 1000)} m)`;
        elKm.className = 'text-[11px] text-slate-500 dark:text-slate-400 block font-mono';
      }
    } else if (activeDistanceUnit === 'M') {
      if (elNm) {
        elNm.textContent = `${Math.round(totalKm * 1000).toLocaleString('id-ID')} Meter`;
        elNm.className = 'text-base font-black text-emerald-600 dark:text-emerald-400 font-mono';
      }
      if (elKm) {
        elKm.textContent = `${totalNm.toFixed(2)} NM (${totalKm.toFixed(2)} km)`;
        elKm.className = 'text-[11px] text-slate-500 dark:text-slate-400 block font-mono';
      }
    } else {
      // Default: NM
      if (elNm) {
        elNm.textContent = `${totalNm.toFixed(2)} NM`;
        elNm.className = 'text-base font-black text-emerald-600 dark:text-emerald-400 font-mono';
      }
      if (elKm) {
        elKm.textContent = `${totalKm.toFixed(2)} km (${Math.round(totalKm * 1000)} m)`;
        elKm.className = 'text-[11px] text-slate-500 dark:text-slate-400 block font-mono';
      }
    }

    if (elBrg) elBrg.textContent = `${lastBearing}° (${lastHeading})`;
    if (elEta) elEta.textContent = formatDurationIndonesian(etaMins);
    if (elStartDms) elStartDms.textContent = decimalToDms(points[0].lat, points[0].lng);
    if (elEndDms) elEndDms.textContent = decimalToDms(points[points.length - 1].lat, points[points.length - 1].lng);

    return { totalKm, totalNm, lastBearing, lastHeading, etaMins };
  }

  function initMeasureDistanceUI() {
    const toggleSwitch = document.getElementById('toggleMeasureMode');
    if (toggleSwitch) {
      toggleSwitch.addEventListener('change', (e) => {
        isMeasureModeActive = e.target.checked;
        if (!isMeasureModeActive) {
          clearMeasureVisual();
        }
      });
    }

    // Sub-mode (Jarak Lurus vs Jalur Manual)
    const btnStraight = document.getElementById('btnModeStraight');
    const btnPath = document.getElementById('btnModePath');
    if (btnStraight) {
      btnStraight.addEventListener('click', () => setMeasureMode('straight'));
    }
    if (btnPath) {
      btnPath.addEventListener('click', () => setMeasureMode('path'));
    }

    // Satuan Jarak Rute (NM, KM, M)
    const distUnitSel = document.getElementById('measureDistanceUnit');
    if (distUnitSel) {
      distUnitSel.addEventListener('change', (e) => {
        setGlobalMeasurementUnit(e.target.value);
      });
    }

    // Satuan Kecepatan & Navigasi: Otomatis konversi nilai dan hitung ulang seketika!
    const speedUnitSel = document.getElementById('measureSpeedUnit');
    if (speedUnitSel) {
      let prevUnit = speedUnitSel.value || 'knot';
      speedUnitSel.addEventListener('change', (e) => {
        const newUnit = e.target.value;
        const speedInput = document.getElementById('measureVesselSpeed');
        if (speedInput) {
          const curVal = parseFloat(speedInput.value) || 25;
          // Convert current value to Knots first, then to target unit
          let valInKnots = curVal;
          if (prevUnit === 'kmh') valInKnots = curVal / 1.852;
          else if (prevUnit === 'ms') valInKnots = (curVal * 3.6) / 1.852;

          let targetVal = valInKnots;
          if (newUnit === 'kmh') targetVal = valInKnots * 1.852;
          else if (newUnit === 'ms') targetVal = (valInKnots * 1.852) / 3.6;

          speedInput.value = targetVal.toFixed(1);
        }
        prevUnit = newUnit;
        speedUnit = newUnit;
        recalcRouteMetrics(measurePoints);
      });
    }

    // Quick Speed Preset Buttons (dengan dukungan unit otomatis)
    document.querySelectorAll('.speed-preset-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const speed = parseFloat(btn.getAttribute('data-speed'));
        const unit = btn.getAttribute('data-unit') || 'knot';
        const unitSel = document.getElementById('measureSpeedUnit');
        if (unitSel) {
          unitSel.value = unit;
          speedUnit = unit;
        }
        const speedInput = document.getElementById('measureVesselSpeed');
        if (speedInput) speedInput.value = speed;
        recalcRouteMetrics(measurePoints);
        updateMeasureVisual();
      });
    });

    const speedInput = document.getElementById('measureVesselSpeed');
    if (speedInput) {
      speedInput.addEventListener('input', () => {
        recalcRouteMetrics(measurePoints);
      });
    }

    // Start New Route Button
    const btnNewRoute = document.getElementById('btnStartNewRoute');
    if (btnNewRoute) {
      btnNewRoute.addEventListener('click', () => {
        const card = document.getElementById('routePlanningCard');
        if (card) card.classList.remove('hidden');
        btnNewRoute.classList.add('hidden');
        isMeasureModeActive = true;
        currentActiveRouteId = null;
        clearMeasureVisual();
        const rNameInput = document.getElementById('measureRouteNameInput');
        if (rNameInput) {
          rNameInput.value = '';
          rNameInput.placeholder = 'RUTE 1';
        }
        setMeasureMode(measureModeType);
      });
    }

    // Cancel / Clear Route Buttons
    const clearBtn = document.getElementById('btnClearMeasureRoute');
    const cancelTopBtn = document.getElementById('btnCancelRouteFormTop');
    [clearBtn, cancelTopBtn].forEach((b) => {
      if (b) {
        b.addEventListener('click', () => {
          clearMeasureVisual();
          isMeasureModeActive = false;
          const card = document.getElementById('routePlanningCard');
          if (card) card.classList.add('hidden');
          const btnNew = document.getElementById('btnStartNewRoute');
          if (btnNew) btnNew.classList.remove('hidden');
        });
      }
    });

    const saveBtn = document.getElementById('btnSaveMeasureRoute');
    if (saveBtn) {
      saveBtn.addEventListener('click', () => performSaveRoute({ isManualClick: true }));
    }

    // Undo Waypoint Buttons (Both Counter Pill & Bottom Action Bar)
    const btnUndoWaypoint = document.getElementById('btnUndoMeasureRouteWaypoint');
    const btnBottomUndo = document.getElementById('btnBottomUndoRoute');
    [btnUndoWaypoint, btnBottomUndo].forEach((btn) => {
      if (btn && !btn.getAttribute('onclick')) {
        btn.addEventListener('click', () => undoLastMeasurePoint());
      }
    });

    // Initialize default mode
    setMeasureMode(measureModeType);
  }

  function undoLastMeasurePoint() {
    // Single-click debounce throttle lock to prevent accidental double point deletion
    if (isUndoingRoutePoint) return;
    isUndoingRoutePoint = true;
    setTimeout(() => {
      isUndoingRoutePoint = false;
    }, 200);

    if (!Array.isArray(measurePoints) || measurePoints.length === 0) {
      if (typeof showToast === 'function') {
        showToast('Belum ada titik rute untuk di-undo.', 'info');
      }
      return;
    }
    measurePoints.pop();
    const counter = document.getElementById('manualPointsCounter');
    if (counter) {
      counter.textContent = `${measurePoints.length} Titik`;
    }
    updateMeasureVisual();
    const badge = document.getElementById('routeRealtimeStatusBadge');
    if (badge) {
      if (measurePoints.length >= 2) {
        badge.textContent = currentActiveRouteId ? 'Mode Edit (Siap Disimpan)' : 'Rute Belum Disimpan';
        badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
      } else if (measurePoints.length === 1) {
        badge.textContent = 'Menunggu titik ke-2...';
        badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
      } else {
        badge.textContent = '⚡ Siap Rancang';
        badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
      }
    }
    if (typeof showToast === 'function') {
      showToast('Titik rute terakhir berhasil dihapus (Undo).', 'info');
    }
  }

  function addMeasurePoint(lat, lng) {
    if (measureModeType === 'straight' && measurePoints.length >= 2) {
      measurePoints = [{ lat, lng }];
    } else {
      measurePoints.push({ lat, lng });
    }

    const counter = document.getElementById('manualPointsCounter');
    if (counter) {
      counter.textContent = `${measurePoints.length} Titik`;
    }

    const nameInput = document.getElementById('measureRouteNameInput');
    if (nameInput) {
      nameInput.placeholder = 'RUTE 1';
    }

    updateMeasureVisual();

    const badge = document.getElementById('routeRealtimeStatusBadge');
    if (measurePoints.length >= 2) {
      if (badge) {
        if (currentActiveRouteId) {
          badge.textContent = 'Mode Edit (Siap Disimpan)';
          badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
        } else {
          badge.textContent = 'Rute Belum Disimpan';
          badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
        }
      }
    } else {
      if (badge) {
        badge.textContent = 'Menunggu titik ke-2...';
        badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
      }
    }
  }

  function updateMeasureVisual() {
    recalcRouteMetrics(measurePoints);

    // Map Polyline & Waypoints
    if (measurePolyline) {
      map.removeLayer(measurePolyline);
      measurePolyline = null;
    }
    measureMarkers.forEach((m) => map.removeLayer(m));
    measureMarkers = [];

    if (measurePoints.length >= 2) {
      const latlngs = measurePoints.map((p) => [p.lat, p.lng]);
      measurePolyline = L.polyline(latlngs, {
        color: '#10b981',
        weight: 3.5
      }).addTo(map);
    }

    measurePoints.forEach((p, idx) => {
      const isStart = idx === 0;
      const isEnd = idx === measurePoints.length - 1 && measurePoints.length > 1;
      const bgColor = isStart ? '#16a34a' : (isEnd ? '#dc2626' : '#0ea5e9');
      const labelText = isStart ? 'A' : (isEnd ? 'B' : `${idx + 1}`);
      const dmsP = decimalToDms(p.lat, p.lng);

      const markerIcon = L.divIcon({
        className: 'custom-measure-waypoint',
        html: `<div style="
          width: 22px; height: 22px;
          border-radius: 50%;
          background: ${bgColor};
          border: 2px solid #ffffff;
          box-shadow: 0 2px 6px rgba(0,0,0,0.4);
          display: flex; align-items: center; justify-content: center;
          color: white; font-size: 10px; font-weight: bold; font-family: ui-sans-serif, system-ui;
          cursor: grab;
        ">${labelText}</div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11]
      });

      const marker = L.marker([p.lat, p.lng], {
        draggable: true,
        icon: markerIcon
      }).addTo(map);

      marker.bindTooltip(`WP-${idx + 1} (${dmsP})`, { permanent: true, direction: 'top', offset: [0, -11] });
      marker.bindPopup(`
        <div class="p-2 space-y-1 font-sans text-xs">
          <div class="font-bold text-emerald-600 flex items-center gap-1"><span>📍</span> Waypoint ${idx + 1} (${labelText}) - Dapat Digeser</div>
          <div class="font-mono text-[11px] text-slate-500 bg-slate-100 p-1 rounded border border-slate-200">DMS: ${dmsP}</div>
          <div class="text-[11px] text-slate-400 italic">Geser titik ini pada peta untuk menyesuaikan posisi rute.</div>
        </div>
      `);

      marker.on('drag', (e) => {
        const newLatLng = e.target.getLatLng();
        p.lat = newLatLng.lat;
        p.lng = newLatLng.lng;
        if (measurePolyline) {
          measurePolyline.setLatLngs(measurePoints.map(pt => [pt.lat, pt.lng]));
        }
        recalcRouteMetrics(measurePoints);
      });

      marker.on('dragend', (e) => {
        const newLatLng = e.target.getLatLng();
        p.lat = newLatLng.lat;
        p.lng = newLatLng.lng;
        const newDms = decimalToDms(p.lat, p.lng);
        marker.setTooltipContent(`WP-${idx + 1} (${newDms})`);
        recalcRouteMetrics(measurePoints);
        if (measurePoints.length >= 2) {
          const badge = document.getElementById('routeRealtimeStatusBadge');
          if (badge) {
            badge.textContent = currentActiveRouteId ? 'Mode Edit (Posisi Diubah)' : 'Rute Belum Disimpan';
            badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
          }
        }
      });

      measureMarkers.push(marker);
    });
  }

  function clearMeasureVisual() {
    measurePoints = [];
    currentActiveRouteId = null;
    if (autoSaveRouteDebounceTimer) {
      clearTimeout(autoSaveRouteDebounceTimer);
      autoSaveRouteDebounceTimer = null;
    }

    const nameInput = document.getElementById('measureRouteNameInput');
    if (nameInput) {
      nameInput.value = '';
      nameInput.placeholder = 'RUTE 1';
    }

    const saveBtn = document.getElementById('btnSaveMeasureRoute');
    if (saveBtn) {
      saveBtn.innerHTML = '<span>💾 Simpan Rute Ini</span>';
    }

    const realtimeBadge = document.getElementById('routeRealtimeStatusBadge');
    if (realtimeBadge) {
      realtimeBadge.textContent = '⚡ Siap Rancang';
      realtimeBadge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
    }

    const manualCounter = document.getElementById('manualPointsCounter');
    if (manualCounter) manualCounter.textContent = '0 Titik';

    const feedbackBanner = document.getElementById('routeSaveFeedbackBanner');
    if (feedbackBanner) feedbackBanner.classList.add('hidden');

    const liveDmsStart = document.getElementById('liveRouteStartDms');
    if (liveDmsStart) liveDmsStart.textContent = 'Belum ditentukan';
    const liveDmsEnd = document.getElementById('liveRouteEndDms');
    if (liveDmsEnd) liveDmsEnd.textContent = 'Belum ditentukan';

    if (measurePolyline) {
      map.removeLayer(measurePolyline);
      measurePolyline = null;
    }
    measureMarkers.forEach((m) => map.removeLayer(m));
    measureMarkers = [];

    const distNmEl = document.getElementById('liveRouteDistNm');
    if (distNmEl) distNmEl.textContent = '0.00 NM';
    const distKmEl = document.getElementById('liveRouteDistKm');
    if (distKmEl) distKmEl.textContent = '0.00 km';
    const brgEl = document.getElementById('liveRouteBearing');
    if (brgEl) brgEl.textContent = '0° (Utara)';
    const etaEl = document.getElementById('liveRouteEta');
    if (etaEl) etaEl.textContent = '0 Menit';
  }

  async function performSaveRoute(options = {}) {
    if (!measurePoints || measurePoints.length < 2) {
      const badge = document.getElementById('routeRealtimeStatusBadge');
      if (badge) {
        badge.textContent = measurePoints.length === 1 ? 'Menunggu titik ke-2' : '⚡ Siap Rancang';
        badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
      }
      showRouteSaveFeedback('Minimal tentukan 2 titik rute!', 'warning');
      return;
    }

    // 1. Resolve Route Name from Form Input or Placeholder fallback "RUTE #"
    const nameInput = document.getElementById('measureRouteNameInput');
    let routeName = nameInput ? nameInput.value.trim() : '';
    if (!routeName) {
      routeName = `RUTE ${savedRoutes.length + 1}`;
    }

    const isEditing = !!currentActiveRouteId && savedRoutes.some(r => String(r.id) === String(currentActiveRouteId));
    if (!currentActiveRouteId) {
      currentActiveRouteId = `route-${Date.now()}`;
    }

    const metrics = recalcRouteMetrics(measurePoints);
    const strokeColor = '#10b981';

    const targetOpId = (filterMode === 'operasi' && currentOperasiId)
      ? currentOperasiId
      : (currentActiveRouteId ? (rawAllRoutes.find(r => String(r.id) === String(currentActiveRouteId))?.id_operasi || null) : null);

    const payload = {
      id: currentActiveRouteId,
      id_operasi: targetOpId,
      name: routeName,
      points: measurePoints.map(p => ({ lat: p.lat, lng: p.lng, label: p.label || '' })),
      vesselSpeedKnots: vesselSpeedKnots,
      speedUnit: speedUnit || 'knot',
      distanceNm: metrics.totalNm,
      distanceKm: metrics.totalKm,
      bearing: metrics.lastBearing,
      heading: metrics.lastHeading,
      etaText: formatDurationIndonesian(metrics.etaMins),
      routingMode: measureModeType,
      strokeColor: strokeColor,
      strokeWeight: 3.5,
      strokeOpacity: 0.9,
      createdAt: `${window.formatTanggalIndo(new Date())} WITA`
    };

    const saveBtn = document.getElementById('btnSaveMeasureRoute');
    if (saveBtn) {
      saveBtn.disabled = true;
      saveBtn.innerHTML = '<span class="inline-block animate-spin mr-1">⏳</span><span>Menyimpan Rute...</span>';
    }

    const badge = document.getElementById('routeRealtimeStatusBadge');
    if (badge) {
      badge.textContent = '⏳ Menyimpan...';
      badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition animate-pulse';
    }

    try {
      const resp = await gisFetch(`${BASE_URL}/api/gis/routes`, {
        method: 'POST',
        body: JSON.stringify(payload)
      });
      const resJson = await resp.json().catch(() => ({}));
      if (resJson && resJson.id) {
        currentActiveRouteId = resJson.id;
        payload.id = resJson.id;
      }

      updateRawRoute(payload, false);
      renderSavedRoutes();

      const actionText = isEditing ? 'diperbarui' : 'disimpan';
      if (typeof showToast === 'function') {
        showToast(`Rute "${routeName}" berhasil ${actionText} (${metrics.totalNm.toFixed(2)} NM, ETA ${formatDurationIndonesian(metrics.etaMins)}).`, 'success');
      }

      // Requirement 1: Auto-hide form after route is successfully added or updated
      clearMeasureVisual();
      isMeasureModeActive = false;
      currentActiveRouteId = null;
      const routeCard = document.getElementById('routePlanningCard');
      if (routeCard) routeCard.classList.add('hidden');
      const btnNewRoute = document.getElementById('btnStartNewRoute');
      if (btnNewRoute) btnNewRoute.classList.remove('hidden');
    } catch (err) {
      console.warn('Save route offline or failed:', err);
      const existingIdx = savedRoutes.findIndex(r => String(r.id) === String(payload.id));
      if (existingIdx >= 0) {
        savedRoutes[existingIdx] = payload;
      } else {
        savedRoutes.unshift(payload);
      }
      renderSavedRoutes();

      if (typeof showToast === 'function') {
        showToast(`Rute "${routeName}" disimpan secara lokal (${metrics.totalNm.toFixed(2)} NM).`, 'info');
      }

      // Requirement 1: Auto-hide form after route is added/updated
      clearMeasureVisual();
      isMeasureModeActive = false;
      currentActiveRouteId = null;
      const routeCard = document.getElementById('routePlanningCard');
      if (routeCard) routeCard.classList.add('hidden');
      const btnNewRoute = document.getElementById('btnStartNewRoute');
      if (btnNewRoute) btnNewRoute.classList.remove('hidden');
    } finally {
      if (saveBtn) {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span>💾 Simpan Rute Ini</span>';
      }
    }
  }

  function showRouteSaveFeedback(msg, type = 'success') {
    const banner = document.getElementById('routeSaveFeedbackBanner');
    if (!banner) return;
    banner.classList.remove('hidden');
    if (type === 'success') {
      banner.className = 'text-xs p-2.5 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 flex items-center justify-between animate-in fade-in';
      banner.innerHTML = `<span>${msg}</span><button type="button" onclick="this.parentElement.classList.add('hidden')" class="ml-2 font-bold opacity-60 hover:opacity-100">&times;</button>`;
    } else if (type === 'warning') {
      banner.className = 'text-xs p-2.5 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 flex items-center justify-between animate-in fade-in';
      banner.innerHTML = `<span>⚠️ ${msg}</span><button type="button" onclick="this.parentElement.classList.add('hidden')" class="ml-2 font-bold opacity-60 hover:opacity-100">&times;</button>`;
    } else {
      banner.className = 'text-xs p-2.5 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/30 flex items-center justify-between animate-in fade-in';
      banner.innerHTML = `<span>${msg}</span><button type="button" onclick="this.parentElement.classList.add('hidden')" class="ml-2 font-bold opacity-60 hover:opacity-100">&times;</button>`;
    }
    setTimeout(() => {
      if (banner) banner.classList.add('hidden');
    }, 6000);
  }

  function editRoute(id) {
    const rt = savedRoutes.find((r) => String(r.id) === String(id));
    if (!rt) return;

    // Switch to measure tab
    switchActiveTab('tab-measure');

    // Ensure sidebar is visible
    const sidebar = document.getElementById('gisSidebarPanel');
    const floatingBtn = document.getElementById('btnFloatingOpenSidebar');
    const iconClose = document.getElementById('iconSidebarClose');
    const iconOpen = document.getElementById('iconSidebarOpen');
    const label = document.getElementById('labelSidebarToggle');
    if (sidebar && sidebar.classList.contains('-ml-[390px]')) {
      isSidebarVisible = true;
      sidebar.classList.remove('-ml-[390px]');
      if (floatingBtn) floatingBtn.classList.add('hidden');
      if (iconClose) iconClose.classList.remove('hidden');
      if (iconOpen) iconOpen.classList.add('hidden');
      if (label) label.textContent = 'Sembunyikan Tools';
    }

    // Open route card & activate measure mode
    const card = document.getElementById('routePlanningCard');
    if (card) card.classList.remove('hidden');
    const btnNew = document.getElementById('btnStartNewRoute');
    if (btnNew) btnNew.classList.add('hidden');

    isMeasureModeActive = true;
    currentActiveRouteId = rt.id;
    measurePoints = Array.isArray(rt.points)
      ? rt.points.map((p) => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng), label: p.label || '' }))
      : [];
    measureModeType = rt.routingMode || (measurePoints.length > 2 ? 'path' : 'straight');

    const nameInput = document.getElementById('measureRouteNameInput');
    if (nameInput) nameInput.value = rt.name || '';

    const speedInput = document.getElementById('measureVesselSpeed');
    const speedUnitSel = document.getElementById('measureSpeedUnit');
    const rUnit = rt.speedUnit || 'knot';
    if (speedUnitSel) {
      speedUnitSel.value = rUnit;
      speedUnit = rUnit;
    }
    if (speedInput) {
      if (rUnit === 'kmh') {
        speedInput.value = ((parseFloat(rt.vesselSpeedKnots) || 25) * 1.852).toFixed(1);
      } else {
        speedInput.value = parseFloat(rt.vesselSpeedKnots) || 25;
      }
    }

    const saveBtn = document.getElementById('btnSaveMeasureRoute');
    if (saveBtn) {
      saveBtn.innerHTML = '<span>💾 Perbarui Rute</span>';
    }

    const badge = document.getElementById('routeRealtimeStatusBadge');
    if (badge) {
      badge.textContent = `Mengedit: ${rt.name || 'Rute'}`;
      badge.className = 'text-[11px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
    }

    const counter = document.getElementById('manualPointsCounter');
    if (counter) {
      counter.textContent = `${measurePoints.length} Titik (Mode Edit)`;
    }

    setMeasureMode(measureModeType);
    updateMeasureVisual();

    if (measurePoints.length >= 2 && map) {
      const bounds = L.latLngBounds(measurePoints.map((p) => [p.lat, p.lng]));
      map.fitBounds(bounds, { padding: [60, 60] });
    }
  }

  function renderSavedRoutes() {
    updateGisBadges();
    savedRoutePolylinesMap.forEach((pl) => map.removeLayer(pl));
    savedRoutePolylinesMap.clear();

    savedRoutes.forEach((route) => {
      if (isIdHidden(hiddenRouteIds, route.id)) return;
      if (!route.points || route.points.length < 2) return;

      const latlngs = route.points.map((p) => [p.lat, p.lng]);
      const pl = L.polyline(latlngs, {
        color: route.strokeColor || '#059669',
        weight: route.strokeWeight || 3.5,
        opacity: route.strokeOpacity || 0.9
      }).addTo(map);

      savedRoutePolylinesMap.set(route.id, pl);
    });

    renderSavedRoutesList();
  }

  function renderSavedRoutesList() {
    const container = document.getElementById('savedRoutesListContainer');
    if (!container) return;

    if (savedRoutes.length === 0) {
      container.innerHTML = `<div class="p-4 text-center text-slate-500 text-xs italic">Belum ada rute terukur tersimpan.</div>`;
      return;
    }

    container.innerHTML = savedRoutes
      .map((rt) => {
        const isHidden = isIdHidden(hiddenRouteIds, rt.id);
        const safeId = escapeHtml(rt.id);
        const safeName = escapeHtml(rt.name);

        let distNm = 0;
        for (let i = 0; i < (rt.points ? rt.points.length - 1 : 0); i++) {
          distNm += calculateDistanceHaversine(rt.points[i].lat, rt.points[i].lng, rt.points[i + 1].lat, rt.points[i + 1].lng).nm;
        }

        const startDms = rt.points && rt.points[0] ? decimalToDms(rt.points[0].lat, rt.points[0].lng) : '-';
        const endDms = rt.points && rt.points.length > 1 ? decimalToDms(rt.points[rt.points.length - 1].lat, rt.points[rt.points.length - 1].lng) : '-';
        const speedDisplay = rt.speedUnit === 'kmh'
          ? `${((parseFloat(rt.vesselSpeedKnots) || 25) * 1.852).toFixed(1)} km/jam`
          : `${rt.vesselSpeedKnots || 25} Knot`;

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="cursor-pointer flex-1 min-w-0" onclick="window.SAR_GIS.focusRoute('${safeId}')">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${safeName}</span>
                ${getOperasiBadgeHtml(rt.id_operasi)}
              </div>
              <div class="text-[11px] font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                ${formatDynamicDistance(distNm, distNm * 1.852)} • @ ${escapeHtml(speedDisplay)}
              </div>
              <div class="text-[11px] font-mono text-slate-400 mt-1 flex flex-col gap-0.5">
                <span class="truncate"><b class="text-emerald-400">A:</b> ${escapeHtml(startDms)}</span>
                <span class="truncate"><b class="text-red-400">B:</b> ${escapeHtml(endDms)}</span>
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.editRoute('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded transition" title="Edit Rute Ini">
                ${GIS_ICON.edit}
              </button>
              <button type="button" onclick="window.SAR_GIS.toggleRouteVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded" title="Tampilkan/Sembunyikan">
                ${isHidden ? GIS_ICON.eyeOff : GIS_ICON.eye}
              </button>
              <button type="button" onclick="window.SAR_GIS.deleteRoute('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded" title="Hapus Rute">
                ${GIS_ICON.trash}
              </button>
            </div>
          </div>
        </div>
      `;
      })
      .join('');
  }

  // ==========================================
  // 10. SPATIAL EXPORT UTILITIES (KML, KMZ, GEOJSON)
  // ==========================================

  function generateKmlDocument() {
    if (window.QrSarGis && window.QrSarGis.Export && typeof window.QrSarGis.Export.generateKmlDocument === 'function') {
      return window.QrSarGis.Export.generateKmlDocument({
        operasiName: currentOperasi ? currentOperasi.nama_operasi : 'Plotting Operasi SAR Kendari',
        points: savedPoints,
        polygons: savedPolygons,
        patterns: savedPatterns,
        routes: savedRoutes
      });
    }
    let kml = `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n  <Document>\n    <name>${escapeXml(currentOperasi ? currentOperasi.nama_operasi : 'Plotting Operasi SAR Kendari')}</name>\n`;

    // Folder 1: Points
    kml += `    <Folder>\n      <name>Titik Operasi SAR</name>\n`;
    savedPoints.forEach((p) => {
      kml += `      <Placemark>\n        <name>${escapeXml(p.label || 'Titik')}</name>\n        <description>${escapeXml(p.description || '')} [${escapeXml(p.category || 'SAR')}]</description>\n        <Point><coordinates>${p.lng},${p.lat},0</coordinates></Point>\n      </Placemark>\n`;
    });
    kml += `    </Folder>\n`;

    // Folder 2: Polygons
    kml += `    <Folder>\n      <name>Area Sektor Poligon</name>\n`;
    savedPolygons.forEach((poly) => {
      if (poly.points && poly.points.length >= 3) {
        const coordsStr = poly.points.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
        kml += `      <Placemark>\n        <name>${escapeXml(poly.name || 'Sektor')}</name>\n        <Polygon><outerBoundaryIs><LinearRing><coordinates>${coordsStr} ${poly.points[0].lng},${poly.points[0].lat},0</coordinates></LinearRing></outerBoundaryIs></Polygon>\n      </Placemark>\n`;
      }
    });
    kml += `    </Folder>\n`;

    // Folder 3: Patterns & Routes
    kml += `    <Folder>\n      <name>Rute dan Lintasan SAR</name>\n`;
    savedPatterns.forEach((pat) => {
      if (pat.waypoints && pat.waypoints.length >= 2) {
        const coordsStr = pat.waypoints.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
        kml += `      <Placemark>\n        <name>${escapeXml(pat.name || 'Pola Lintasan')}</name>\n        <LineString><coordinates>${coordsStr}</coordinates></LineString>\n      </Placemark>\n`;
      }
    });
    savedRoutes.forEach((rt) => {
      if (rt.points && rt.points.length >= 2) {
        const coordsStr = rt.points.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
        kml += `      <Placemark>\n        <name>${escapeXml(rt.name || 'Rute Jarak')}</name>\n        <LineString><coordinates>${coordsStr}</coordinates></LineString>\n      </Placemark>\n`;
      }
    });
    kml += `    </Folder>\n  </Document>\n</kml>`;

    return kml;
  }

  function downloadKmlFile() {
    if (window.QrSarGis && window.QrSarGis.Export && typeof window.QrSarGis.Export.downloadKml === 'function') {
      window.QrSarGis.Export.downloadKml({
        operasiName: currentOperasi ? currentOperasi.nama_operasi : 'Plotting Operasi SAR Kendari',
        points: savedPoints,
        polygons: savedPolygons,
        patterns: savedPatterns,
        routes: savedRoutes
      });
      return;
    }
    const kml = generateKmlDocument();
    const blob = new Blob([kml], { type: 'application/vnd.google-earth.kml+xml' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `SAR_KENDARI_PLOTTING_${new Date().toISOString().slice(0, 10)}.kml`;
    a.click();
    URL.revokeObjectURL(url);
  }

  async function downloadKmzFile() {
    if (window.QrSarGis && window.QrSarGis.Export && typeof window.QrSarGis.Export.downloadKmz === 'function') {
      await window.QrSarGis.Export.downloadKmz({
        operasiName: currentOperasi ? currentOperasi.nama_operasi : 'Plotting Operasi SAR Kendari',
        points: savedPoints,
        polygons: savedPolygons,
        patterns: savedPatterns,
        routes: savedRoutes
      });
      return;
    }
    if (typeof JSZip === 'undefined') {
      downloadKmlFile();
      return;
    }

    const zip = new JSZip();
    zip.file('doc.kml', generateKmlDocument());
    const content = await zip.generateAsync({ type: 'blob' });
    const url = URL.createObjectURL(content);
    const a = document.createElement('a');
    a.href = url;
    a.download = `SAR_KENDARI_PLOTTING_${new Date().toISOString().slice(0, 10)}.kmz`;
    a.click();
    URL.revokeObjectURL(url);
  }

  function downloadGeoJsonFile() {
    if (window.QrSarGis && window.QrSarGis.Export && typeof window.QrSarGis.Export.downloadGeoJson === 'function') {
      window.QrSarGis.Export.downloadGeoJson({
        points: savedPoints,
        polygons: savedPolygons,
        patterns: savedPatterns,
        routes: savedRoutes
      });
      return;
    }
    const features = [];

    savedPoints.forEach((p) => {
      features.push({
        type: 'Feature',
        properties: { name: p.label || '', category: p.category || 'Tanpa Kategori', type: p.pointType, id_operasi: p.id_operasi || null },
        geometry: { type: 'Point', coordinates: [p.lng, p.lat] }
      });
    });

    savedPolygons.forEach((poly) => {
      if (poly.points && poly.points.length >= 3) {
        const ring = poly.points.map((pt) => [pt.lng, pt.lat]);
        ring.push([poly.points[0].lng, poly.points[0].lat]);
        features.push({
          type: 'Feature',
          properties: { name: poly.name, id_operasi: poly.id_operasi || null },
          geometry: { type: 'Polygon', coordinates: [ring] }
        });
      }
    });

    savedPatterns.forEach((pat) => {
      if (pat.waypoints && pat.waypoints.length >= 2) {
        features.push({
          type: 'Feature',
          properties: {
            name: pat.name || 'Pola SAR',
            type: pat.patternType,
            trackSpacingNm: pat.trackSpacingNm,
            totalDistanceNm: pat.totalDistanceNm,
            legsCount: pat.legsCount,
            id_operasi: pat.id_operasi || null
          },
          geometry: {
            type: 'LineString',
            coordinates: pat.waypoints.map((wp) => [wp.lng, wp.lat])
          }
        });
      }
    });

    savedRoutes.forEach((rt) => {
      if (rt.points && rt.points.length >= 2) {
        features.push({
          type: 'Feature',
          properties: {
            name: rt.name || 'Rute Jarak & Navigasi',
            vesselSpeedKnots: rt.vesselSpeedKnots,
            distanceNm: rt.distanceNm,
            etaText: rt.etaText,
            id_operasi: rt.id_operasi || null
          },
          geometry: {
            type: 'LineString',
            coordinates: rt.points.map((pt) => [pt.lng, pt.lat])
          }
        });
      }
    });

    const geoJson = { type: 'FeatureCollection', features };
    const blob = new Blob([JSON.stringify(geoJson, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `SAR_KENDARI_GEOJSON_${new Date().toISOString().slice(0, 10)}.geojson`;
    a.click();
    URL.revokeObjectURL(url);
  }

  // ==========================================
  // 11. TAB SWITCHER & GLOBAL CONTROLS
  // ==========================================

  function initTabSwitcher() {
    document.querySelectorAll('.gis-tab-nav-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetTab = btn.getAttribute('data-tab');
        switchActiveTab(targetTab);
      });
    });
  }

  function switchActiveTab(tabId) {
    activeTab = tabId;

    // 1. Update tab navigation button styling (Theme Adaptive)
    document.querySelectorAll('.gis-tab-nav-btn').forEach((btn) => {
      const t = btn.getAttribute('data-tab');
      if (t === tabId) {
        btn.className = 'gis-tab-nav-btn py-3 px-1 border-b-2 border-orange-500 text-orange-600 dark:text-orange-400 flex flex-col items-center justify-center gap-1 transition';
      } else {
        btn.className = 'gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition';
      }
    });

    // 2. Toggle panes
    document.querySelectorAll('.gis-tab-pane').forEach((pane) => {
      if (pane.id === tabId) {
        pane.classList.remove('hidden');
      } else {
        pane.classList.add('hidden');
      }
    });

    // 3. User Requirement 4: Invalidate & cancel route measurement when switching away from tab-measure
    if (tabId !== 'tab-measure') {
      isMeasureModeActive = false;
      clearMeasureVisual();
      const routeCard = document.getElementById('routePlanningCard');
      if (routeCard) routeCard.classList.add('hidden');
      const btnNewRoute = document.getElementById('btnStartNewRoute');
      if (btnNewRoute) btnNewRoute.classList.remove('hidden');
    }

    // 4. Automatically cancel unsaved pattern preview when switching away from tab-pattern
    if (tabId !== 'tab-pattern') {
      clearCurrentPatternVisual();
      const patternCard = document.getElementById('patternCreationCard');
      if (patternCard) patternCard.classList.add('hidden');
      const btnNewPat = document.getElementById('btnStartNewPattern');
      if (btnNewPat) btnNewPat.classList.remove('hidden');
    }

    // 5. Automatically cancel unsaved polygon drawing when switching away from tab-polygon
    if (tabId !== 'tab-polygon') {
      if (isDrawingPolygon) {
        cancelPolygonDrawing();
      }
      const polyCard = document.getElementById('polygonDrawingEditorCard');
      if (polyCard) polyCard.classList.add('hidden');
      const btnNewPoly = document.getElementById('btnStartNewPolygon');
      if (btnNewPoly) btnNewPoly.classList.remove('hidden');
    }
  }

  function initSidebarToggle() {
    const btnToggle = document.getElementById('btnToggleGisSidebar');
    const floatingBtn = document.getElementById('btnFloatingOpenSidebar');
    const sidebar = document.getElementById('gisSidebarPanel');
    const iconClose = document.getElementById('iconSidebarClose');
    const iconOpen = document.getElementById('iconSidebarOpen');
    const label = document.getElementById('labelSidebarToggle');

    function toggle() {
      isSidebarVisible = !isSidebarVisible;

      if (isSidebarVisible) {
        sidebar.classList.remove('-ml-[390px]');
        floatingBtn.classList.add('hidden');
        iconClose.classList.remove('hidden');
        iconOpen.classList.add('hidden');
        if (label) label.textContent = 'Sembunyikan Tools';
      } else {
        sidebar.classList.add('-ml-[390px]');
        floatingBtn.classList.remove('hidden');
        iconClose.classList.add('hidden');
        iconOpen.classList.remove('hidden');
        if (label) label.textContent = 'Buka Tools Plotting';
      }

      setTimeout(() => {
        if (map) map.invalidateSize();
      }, 310);
    }

    if (btnToggle) btnToggle.addEventListener('click', toggle);
    if (floatingBtn) floatingBtn.addEventListener('click', toggle);
  }

  function initBasemapSwitcher() {
    document.querySelectorAll('.basemap-pill-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const layer = btn.getAttribute('data-layer');
        setBasemapLayer(layer);
      });
    });
  }

  function initExportDropdown() {
    const toggleBtn = document.getElementById('btnToggleExportMenu');
    const dropdown = document.getElementById('exportDropdownMenu');

    if (toggleBtn && dropdown) {
      toggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
      });

      document.addEventListener('click', (e) => {
        if (!e.target.closest('#exportMenuWrapper')) {
          dropdown.classList.add('hidden');
        }
      });

      document.getElementById('btnExportKml')?.addEventListener('click', () => {
        dropdown.classList.add('hidden');
        downloadKmlFile();
      });

      document.getElementById('btnExportKmz')?.addEventListener('click', () => {
        dropdown.classList.add('hidden');
        downloadKmzFile();
      });

      document.getElementById('btnExportGeoJson')?.addEventListener('click', () => {
        dropdown.classList.add('hidden');
        downloadGeoJsonFile();
      });
    }
  }

  function initFullscreenToggle() {
    const btn = document.getElementById('btnToggleFullscreen');
    const container = document.getElementById('gisRootContainer');

    if (btn && container) {
      btn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
          container.requestFullscreen?.() || container.webkitRequestFullscreen?.();
        } else {
          document.exitFullscreen?.() || document.webkitExitFullscreen?.();
        }
      });

      document.addEventListener('fullscreenchange', () => {
        setTimeout(() => {
          if (map) map.invalidateSize();
        }, 150);
      });
    }
  }

  // ==========================================
  // 13. GLOBAL WINDOW APIS FOR DOM HANDLERS
  // ==========================================

  window.SAR_GIS = {
    focusPoint: (id) => {
      const pt = savedPoints.find((p) => String(p.id) === String(id));
      if (pt && map) {
        map.flyTo([pt.lat, pt.lng], 14, { duration: 1 });
        const marker = pointMarkersMap.get(pt.id) || pointMarkersMap.get(id);
        if (marker) marker.openPopup();
      }
    },

    togglePointVisibility: (id) => {
      toggleIdInSet(hiddenPointIds, id);
      renderSavedPoints();
    },

    editPoint: (id) => {
      const pt = savedPoints.find((p) => String(p.id) === String(id));
      if (pt) {
        openPointCreator(pt);
      }
    },

    deletePoint: async (id) => {
      const ok = await confirmAction('Hapus Titik SAR', 'Apakah Anda yakin ingin menghapus titik SAR ini?', 'Ya, Hapus Titik', true);
      if (!ok) return;
      try {
        await gisFetch(`${BASE_URL}/api/gis/points/${id}`, { method: 'DELETE' });
        updateRawPoint({ id }, true);
        renderSavedPoints();
      } catch (err) {
        updateRawPoint({ id }, true);
        renderSavedPoints();
      }
    },

    focusPolygon: (id) => {
      const poly = savedPolygons.find((p) => String(p.id) === String(id));
      if (poly && poly.points.length >= 3 && map) {
        const bounds = L.latLngBounds(poly.points.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [50, 50] });
      }
    },

    togglePolygonVisibility: (id) => {
      toggleIdInSet(hiddenPolygonIds, id);
      renderSavedPolygons();
    },

    editPolygon: (id) => {
      const poly = savedPolygons.find((p) => String(p.id) === String(id));
      if (poly) {
        startPolygonDrawing(poly);
      }
    },

    deletePolygon: async (id) => {
      const ok = await confirmAction('Hapus Poligon Sektor', 'Apakah Anda yakin ingin menghapus polygon area pencarian ini?', 'Ya, Hapus Poligon', true);
      if (!ok) return;
      try {
        await gisFetch(`${BASE_URL}/api/gis/polygons/${id}`, { method: 'DELETE' });
        updateRawPolygon({ id }, true);
        renderSavedPolygons();
      } catch (err) {
        updateRawPolygon({ id }, true);
        renderSavedPolygons();
      }
    },

    removeVertex: (index) => {
      removePolygonVertex(index);
    },

    applyPatternFromPolygon: (id) => {
      editingPatternId = null;
      switchActiveTab('tab-pattern');
      const card = document.getElementById('patternCreationCard');
      if (card) card.classList.remove('hidden');
      const btnNew = document.getElementById('btnStartNewPattern');
      if (btnNew) btnNew.classList.add('hidden');
      const nameInput = document.getElementById('patternFormName');
      if (nameInput) {
        nameInput.value = '';
        nameInput.placeholder = 'CREAPING LANE 1/ESAR 1/';
      }
      const sel = document.getElementById('patternTargetPolygon');
      if (sel) {
        sel.value = id;
        updatePatternAutoCalculation();
      }
      const resolvedPts = resolveTargetPolygonPoints(id);
      if (resolvedPts && resolvedPts.length >= 3 && map) {
        const validPts = resolvedPts.map((p) => [parseFloat(p.lat), parseFloat(p.lng)]).filter((p) => !isNaN(p[0]) && !isNaN(p[1]));
        if (validPts.length >= 3) {
          const bounds = L.latLngBounds(validPts);
          map.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
        }
      }
      generateSearchPatternRoute();
    },

    focusPattern: (id) => {
      const pat = savedPatterns.find((p) => String(p.id) === String(id));
      if (pat && pat.waypoints.length >= 2 && map) {
        const bounds = L.latLngBounds(pat.waypoints.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [50, 50] });
      }
    },

    editPattern: (id) => {
      editPattern(id);
    },

    togglePatternVisibility: (id) => {
      toggleIdInSet(hiddenPatternIds, id);
      renderSavedPatterns();
    },

    deletePattern: async (id) => {
      const ok = await confirmAction('Hapus Lintasan SAR', 'Apakah Anda yakin ingin menghapus pola lintasan pencarian IAMSAR ini?', 'Ya, Hapus Pola', true);
      if (!ok) return;
      try {
        await gisFetch(`${BASE_URL}/api/gis/search-patterns/${id}`, { method: 'DELETE' });
        updateRawPattern({ id }, true);
        renderSavedPatterns();
      } catch (err) {
        updateRawPattern({ id }, true);
        renderSavedPatterns();
      }
    },

    focusRoute: (id) => {
      const rt = savedRoutes.find((r) => String(r.id) === String(id));
      if (rt && rt.points.length >= 2 && map) {
        const bounds = L.latLngBounds(rt.points.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [50, 50] });
      }
    },

    editRoute: (id) => {
      editRoute(id);
    },

    toggleRouteVisibility: (id) => {
      toggleIdInSet(hiddenRouteIds, id);
      renderSavedRoutes();
    },

    deleteRoute: async (id) => {
      const ok = await confirmAction('Hapus Rute Jarak', 'Apakah Anda yakin ingin menghapus rute jarak dan kalkulasi waktu tempuh ini?', 'Ya, Hapus Rute', true);
      if (!ok) return;
      try {
        await gisFetch(`${BASE_URL}/api/gis/routes/${id}`, { method: 'DELETE' });
        updateRawRoute({ id }, true);
        renderSavedRoutes();
      } catch (err) {
        updateRawRoute({ id }, true);
        renderSavedRoutes();
      }
    },

    setMeasureMode: (mode) => setMeasureMode(mode),
    saveRoute: () => performSaveRoute({ isManualClick: true }),
    clearRoute: () => clearMeasureVisual(),
    undoRoutePoint: () => undoLastMeasurePoint(),
    copyPointCoordinates: (id) => copyPointCoordinates(id),
    setSearchAreaShape: (shape) => setSearchAreaShape(shape),
    setUnit: (u) => setGlobalMeasurementUnit(u),
    getUnit: () => activeGlobalUnit,
    updateBadges: () => updateGisBadges()
  };

  window.QRSAR = window.QRSAR || {};
  window.QRSAR.GIS = window.QRSAR.GIS || {};
  window.QRSAR.GIS.Facade = window.SAR_GIS;

  function initOperasiSelector() {
    const sel = document.getElementById('selectGisOperasi');
    const checkIncludeBase = document.getElementById('checkIncludeBase');
    const wrapperIncludeBase = document.getElementById('wrapperIncludeBase');
    const btnReset = document.getElementById('btnResetGisOperasi');

    function updateSelectorUI() {
      if (wrapperIncludeBase) {
        if (filterMode === 'operasi') {
          wrapperIncludeBase.classList.remove('hidden');
          wrapperIncludeBase.classList.add('flex');
        } else {
          wrapperIncludeBase.classList.remove('flex');
          wrapperIncludeBase.classList.add('hidden');
        }
      }
      if (checkIncludeBase) {
        checkIncludeBase.checked = includeBaseInActive;
      }
      if (btnReset) {
        if (filterMode === 'operasi' || filterMode === 'basemap' || filterMode === 'dasar') {
          btnReset.classList.remove('hidden');
        } else {
          btnReset.classList.add('hidden');
        }
      }
      if (sel) {
        if (filterMode === 'basemap' || filterMode === 'dasar') {
          sel.value = 'basemap';
        } else if (filterMode === 'all') {
          sel.value = 'all';
        } else if (filterMode === 'operasi' && currentOperasiId) {
          sel.value = String(currentOperasiId);
        }
      }
    }

    if (sel) {
      sel.addEventListener('change', (e) => {
        const val = e.target.value;
        if (val === 'basemap' || val === 'dasar') {
          filterMode = 'basemap';
          currentOperasiId = null;
          currentOperasi = null;
          includeBaseInActive = false;
        } else if (val === 'all' || !val) {
          filterMode = 'all';
          currentOperasiId = null;
          currentOperasi = null;
          includeBaseInActive = false;
        } else {
          const selectedId = parseInt(val, 10);
          const foundOps = operasiList.find(op => parseInt(op.id_operasi, 10) === selectedId);
          // 4. Operasi yang telah ditutup tidak masuk dalam pilihan! (Hanya operasi aktif)
          if (foundOps && activeOperasiIds.has(selectedId)) {
            filterMode = 'operasi';
            currentOperasiId = selectedId;
            currentOperasi = foundOps;
            // 5. Pilihan misi operasi SAR: secara default sudah include dapat menampilkan komponen pada Base Map!
            includeBaseInActive = true;
          } else {
            filterMode = 'basemap';
            currentOperasiId = null;
            currentOperasi = null;
            includeBaseInActive = false;
          }
        }


        updateUrlParams();
        updateSelectorUI();
        applyActiveFilter();
        reRenderAllLayers();

        // Focus map if new operation has LKP coordinates
        if (currentOperasi && currentOperasi.koordinat && map) {
          const opCoords = parseCoordinatesString(currentOperasi.koordinat);
          if (opCoords && opCoords.lat && opCoords.lng) {
            map.setView([opCoords.lat, opCoords.lng], 11);
          }
        }
      });
    }

    if (checkIncludeBase) {
      checkIncludeBase.addEventListener('change', (e) => {
        includeBaseInActive = !!e.target.checked;
        updateUrlParams();
        applyActiveFilter();
        reRenderAllLayers();
      });
    }

    if (btnReset) {
      btnReset.addEventListener('click', () => {
        filterMode = 'all';
        currentOperasiId = null;
        currentOperasi = null;
        includeBaseInActive = false;
        updateUrlParams();
        updateSelectorUI();
        applyActiveFilter();
        reRenderAllLayers();
      });
    }

    // Attach click listener for any banner switch button
    document.addEventListener('click', (e) => {
      const btnAll = e.target.closest('.btn-switch-mode-all');
      if (btnAll) {
        e.preventDefault();
        filterMode = 'all';
        currentOperasiId = null;
        currentOperasi = null;
        includeBaseInActive = false;
        updateUrlParams();
        updateSelectorUI();
        applyActiveFilter();
        reRenderAllLayers();
      }
    });

    updateSelectorUI();

    // If an operation is selected and has LKP coordinates, auto focus if layer has no items
    if (currentOperasi && currentOperasi.koordinat && map) {
      if ((!savedPoints || savedPoints.length === 0) && (!savedPolygons || savedPolygons.length === 0)) {
        const opCoords = parseCoordinatesString(currentOperasi.koordinat);
        if (opCoords && opCoords.lat && opCoords.lng) {
          map.setView([opCoords.lat, opCoords.lng], 11);
        }
      }
    }
  }

  // ==========================================
  // 13B. DOCUMENT MAP CAPTURE 16:9 ENGINE
  // ==========================================

  let isCaptureModeActive = false;
  let captureBoxRect = { x: 40, y: 40, width: 640, height: 360 };
  let captureLatLngBounds = null;
  let captureDetectedData = {
    points: [],
    polygons: [],
    patterns: [],
    routes: [],
    unit: 'NM'
  };
  let cachedOperasiSnapshots = [];
  let cachedOperasiRenopsList = [];

  function initDocumentCaptureUI() {
    const btnToggle = document.getElementById('btnToggleCaptureMode');
    const btnClose = document.getElementById('btnCloseCaptureMode');
    const overlay = document.getElementById('captureFrameOverlay');
    const frameBox = document.getElementById('captureFrameBox');
    const panel = document.getElementById('captureControlPanel');
    const selOperasi = document.getElementById('captureSelectOperasi');
    const selJenis = document.getElementById('captureSelectJenisDokumen');
    const refWrapper = document.getElementById('captureReferensiWrapper');
    const selRef = document.getElementById('captureSelectReferensi');
    const btnFit = document.getElementById('btnFitCaptureComponents');
    const btnSave = document.getElementById('btnExecuteCaptureSave');
    const inputKet = document.getElementById('captureInputKeterangan');

    if (!btnToggle || !overlay || !frameBox || !panel) return;
    // Dokumen RENOPS: Peta Lokasi LKP (RENOPS) 1x per operasi; Peta Rencana Operasi (RENOPS_RENCANA) per hari (id_referensi = id_renops).
    const isPerHari = (j) => j === 'RENOPS_RENCANA';

    function computeCaptureFrameGeometry() {
      const wrapper = document.getElementById('gisMapWrapper');
      if (!wrapper) return;
      const wRect = wrapper.getBoundingClientRect();
      const totalW = Math.max(320, wRect.width);
      const totalH = Math.max(240, wRect.height);

      // Reserve space on the right for the control panel on desktop screens
      const panelWidthReserve = totalW >= 900 ? 370 : 24;
      const availW = Math.max(260, totalW - panelWidthReserve - 40);
      const availH = Math.max(180, totalH - 88);

      // Enforce exact 16:9 aspect ratio
      let boxW = availW;
      let boxH = Math.round((boxW * 9) / 16);
      if (boxH > availH) {
        boxH = availH;
        boxW = Math.round((boxH * 16) / 9);
      }

      const boxX = Math.max(16, Math.round((totalW - (totalW >= 900 ? 356 : 0) - boxW) / 2));
      const boxY = Math.max(34, Math.round((totalH - boxH) / 2));

      captureBoxRect = { x: boxX, y: boxY, width: boxW, height: boxH };

      frameBox.style.left = `${boxX}px`;
      frameBox.style.top = `${boxY}px`;
      frameBox.style.width = `${boxW}px`;
      frameBox.style.height = `${boxH}px`;

      if (map) {
        const nw = map.containerPointToLatLng([boxX, boxY]);
        const se = map.containerPointToLatLng([boxX + boxW, boxY + boxH]);
        captureLatLngBounds = L.latLngBounds(
          [Math.min(nw.lat, se.lat), Math.min(nw.lng, se.lng)],
          [Math.max(nw.lat, se.lat), Math.max(nw.lng, se.lng)]
        );

        const badge = document.getElementById('captureFrameBoundsBadge');
        if (badge) {
          badge.textContent = `NW: ${decimalToDms(nw.lat, nw.lng)} | SE: ${decimalToDms(se.lat, se.lng)}`;
        }
      }
    }

    function getPatternDisplayLabel(type) {
      const t = String(type || '').toLowerCase();
      if (t === 'parallel' || t === 'parallel_track') return 'Parallel Track Search';
      if (t === 'creeping' || t === 'creeping_line') return 'Creeping Line Search';
      if (t === 'expanding_square' || t === 'square') return 'Expanding Square Search';
      if (t === 'sector' || t === 'sector_search') return 'Sector Search';
      if (t === 'track_line' || t === 'trackline') return 'Track Line Search';
      if (t === 'esar' || t === 'e_sar' || t === 'e-sar') return 'E-SAR (Explore / Darat)';
      return type || 'Pola Pencarian SAR';
    }

    function extractCornerLetter(rawLabel, fallbackIdx) {
      const fallback = String.fromCharCode(65 + (fallbackIdx % 26));
      if (!rawLabel) return fallback;
      const str = String(rawLabel).trim();
      if (/^[A-Z0-9]{1,2}$/i.test(str)) return str.toUpperCase();
      const m = str.match(/\bTitik\s+([A-Z0-9]{1,2})\b/i);
      if (m && m[1]) return m[1].toUpperCase();
      return fallback;
    }

    function detectComponentsInCaptureBox() {
      if (!isCaptureModeActive || !map) return;
      computeCaptureFrameGeometry();
      if (!captureLatLngBounds) return;

      const detectedPoints = [];
      const detectedPolygons = [];
      const detectedPatterns = [];
      const detectedRoutes = [];

      // 1. Detect Points inside 16:9 BBox (including full icon metadata for canvas rendering)
      (savedPoints || []).forEach((pt) => {
        if (isIdHidden(hiddenPointIds, pt.id)) return;
        const lat = parseFloat(pt.lat);
        const lng = parseFloat(pt.lng);
        if (isNaN(lat) || isNaN(lng)) return;
        if (captureLatLngBounds.contains([lat, lng])) {
          const cat = pt.category || 'Tanpa Kategori';
          const label = (pt.label && pt.label.trim()) ? pt.label.trim() : (cat !== 'Tanpa Kategori' ? cat : 'Titik SAR');
          const pType = pt.pointType || (cat === 'LKP' ? 'lkp' : 'pin_umum');
          const preset = PRESET_ICONS.find((p) => p.type === pType);
          const normCustomUrl = normalizeIconUrl(pt.customIconUrl || '');
          const pinColor = pt.customColor || (preset ? preset.color : (cat === 'LKP' ? '#dc2626' : (cat === 'Penemuan Korban' || cat === 'Lokasi Penemuan Korban' ? '#d97706' : '#ea580c')));
          const emoji = preset ? preset.emoji : (cat === 'LKP' ? '🚨' : (cat === 'Dermaga SAR' ? '⚓' : (cat === 'Kantor SAR' ? '🏢' : '📍')));

          detectedPoints.push({
            id: pt.id,
            label: label,
            hasCustomLabel: !!(pt.label && pt.label.trim()),
            category: cat,
            pointType: normCustomUrl ? 'custom_upload' : pType,
            alphabetChar: pt.alphabetChar || 'A',
            customIconUrl: normCustomUrl,
            color: pinColor,
            emoji: emoji,
            lat: lat,
            lng: lng,
            dms: decimalToDms(lat, lng),
            description: pt.description || '',
            scope: pt.id_operasi ? 'Operasi SAR' : 'Base Map'
          });
        }
      });

      // 2. Detect Search Areas (Polygons / Rectangles / Circles) intersecting 16:9 BBox
      (savedPolygons || []).forEach((poly) => {
        if (isIdHidden(hiddenPolygonIds, poly.id)) return;
        const pts = Array.isArray(poly.points) ? poly.points : [];
        const shapeType = poly.shapeType || 'polygon';
        let intersects = false;

        if (shapeType === 'circle' && poly.centerLat && poly.centerLng) {
          const cLat = parseFloat(poly.centerLat);
          const cLng = parseFloat(poly.centerLng);
          const rNm = parseFloat(poly.radiusNm) || 1.0;
          const degDelta = rNm / 60.0;
          const cBounds = L.latLngBounds([cLat - degDelta, cLng - degDelta], [cLat + degDelta, cLng + degDelta]);
          intersects = captureLatLngBounds.intersects(cBounds);
        } else if (pts.length >= 3) {
          const polyBounds = L.latLngBounds(pts.map(p => [parseFloat(p.lat), parseFloat(p.lng)]));
          intersects = captureLatLngBounds.intersects(polyBounds);
        }

        if (intersects) {
          const areaNm2 = parseFloat(poly.areaNm2) || calculatePolygonAreaNm2(pts, shapeType, poly.radiusNm, poly.boxWidthNm, poly.boxHeightNm);
          const areaInfo = formatDynamicArea(areaNm2);
          let coordsSummary = '';
          let circleCenterDms = '';
          let circleRadiusText = '';

          if (shapeType === 'circle') {
            const cLat = parseFloat(poly.centerLat ?? (pts[0] ? pts[0].lat : 0));
            const cLng = parseFloat(poly.centerLng ?? (pts[0] ? pts[0].lng : 0));
            const rNm = parseFloat(poly.radiusNm) || 1.0;
            circleCenterDms = decimalToDms(cLat, cLng);
            circleRadiusText = formatDynamicDistance(rNm, rNm * 1.852);
            coordsSummary = `Pusat: ${circleCenterDms} (Radius: ${circleRadiusText})`;
          } else {
            const maxPts = pts.slice(0, 6).map((p, idx) => {
              const lbl = extractCornerLetter(p.label, idx);
              return `Titik ${lbl}: ${decimalToDms(parseFloat(p.lat), parseFloat(p.lng))}`;
            });
            coordsSummary = maxPts.join(' | ') + (pts.length > 6 ? ` (+${pts.length - 6} ttk)` : '');
          }

          detectedPolygons.push({
            id: poly.id,
            name: poly.name || 'Search Area',
            shapeType: shapeType,
            area_nm2: Number(areaNm2.toFixed(2)),
            area_text: areaInfo.combined,
            coords_summary: coordsSummary,
            center_dms: circleCenterDms,
            radius_text: circleRadiusText,
            strokeColor: poly.strokeColor || '#ea580c',
            fillColor: poly.fillColor || '#ea580c',
            fillOpacity: poly.fillOpacity ?? 0.2,
            centerLat: poly.centerLat ? parseFloat(poly.centerLat) : (shapeType === 'circle' && pts[0] ? parseFloat(pts[0].lat) : null),
            centerLng: poly.centerLng ? parseFloat(poly.centerLng) : (shapeType === 'circle' && pts[0] ? parseFloat(pts[0].lng) : null),
            radiusNm: poly.radiusNm ? parseFloat(poly.radiusNm) : (shapeType === 'circle' ? 1.0 : null),
            // Khusus lingkaran (circle), titik batas keliling aproksimasi dikosongkan agar legenda peta tidak dipenuhi 36 titik
            points: shapeType === 'circle' ? [] : pts.map((p, idx) => ({
              lat: parseFloat(p.lat),
              lng: parseFloat(p.lng),
              label: extractCornerLetter(p.label, idx),
              dms: decimalToDms(parseFloat(p.lat), parseFloat(p.lng))
            }))
          });
        }
      });

      // 3. Detect Search Patterns intersecting 16:9 BBox
      const allCandidatePatterns = [...(savedPatterns || [])];
      if (Array.isArray(activeGeneratedWaypoints) && activeGeneratedWaypoints.length >= 2 && !editingPatternId) {
        allCandidatePatterns.push({
          id: 'active-live-pattern',
          name: `Pola Aktif (${getPatternDisplayLabel(activePatternType)})`,
          type: activePatternType,
          patternType: activePatternType,
          trackSpacingNm: parseFloat(document.getElementById('patternTrackSpacing')?.value) || 0.5,
          totalDistanceNm: activeGeneratedDistanceNm || 0,
          color: '#06b6d4',
          strokeColor: '#06b6d4',
          waypoints: activeGeneratedWaypoints
        });
      }

      allCandidatePatterns.forEach((pat) => {
        if (pat.id !== 'active-live-pattern' && isIdHidden(hiddenPatternIds, pat.id)) return;
        const wps = Array.isArray(pat.waypoints) ? pat.waypoints : [];
        if (wps.length < 2) return;
        const patBounds = L.latLngBounds(wps.map(w => [parseFloat(w.lat), parseFloat(w.lng)]));
        if (!captureLatLngBounds.intersects(patBounds)) return;

        const patType = pat.patternType || pat.type || 'parallel_track';
        const spacingNm = parseFloat(pat.trackSpacingNm) || 0.5;
        let distNm = parseFloat(pat.totalDistanceNm) || 0;
        if (distNm <= 0) {
          for (let i = 0; i < wps.length - 1; i++) {
            const d = calculateDistanceHaversine(parseFloat(wps[i].lat), parseFloat(wps[i].lng), parseFloat(wps[i + 1].lat), parseFloat(wps[i + 1].lng));
            distNm += d.nm;
          }
        }
        const sweptNm2 = spacingNm * distNm;
        const csp = wps[0];

        detectedPatterns.push({
          id: pat.id,
          name: pat.name || getPatternDisplayLabel(patType),
          type: patType,
          type_label: getPatternDisplayLabel(patType),
          spacing_text: formatDynamicDistance(spacingNm),
          length_text: formatDynamicDistance(distNm),
          swept_area_text: formatDynamicArea(sweptNm2).combined,
          csp_dms: `CSP: ${decimalToDms(parseFloat(csp.lat), parseFloat(csp.lng))}`,
          csp_lat: parseFloat(csp.lat),
          csp_lng: parseFloat(csp.lng),
          waypoints_count: wps.length,
          strokeColor: pat.color || pat.strokeColor || '#06b6d4',
          waypoints: wps.map(w => ({
            lat: parseFloat(w.lat),
            lng: parseFloat(w.lng),
            label: w.label || '',
            bearing: w.bearing ?? null,
            pNum: w.pNum ?? null
          }))
        });
      });

      // 4. Detect Routes / Distance Lines intersecting 16:9 BBox
      const allCandidateRoutes = [...(savedRoutes || [])];
      if (Array.isArray(measurePoints) && measurePoints.length >= 2 && !currentActiveRouteId) {
        allCandidateRoutes.push({
          id: 'active-live-route',
          name: 'Jalur Pengukuran Aktif',
          vesselSpeedKnots: vesselSpeedKnots || 25,
          strokeColor: '#10b981',
          points: measurePoints
        });
      }

      allCandidateRoutes.forEach((rt) => {
        if (rt.id !== 'active-live-route' && isIdHidden(hiddenRouteIds, rt.id)) return;
        const rPts = Array.isArray(rt.points) ? rt.points : [];
        if (rPts.length < 2) return;
        const rBounds = L.latLngBounds(rPts.map(p => [parseFloat(p.lat), parseFloat(p.lng)]));
        if (!captureLatLngBounds.intersects(rBounds)) return;

        let totalNm = 0;
        let totalKm = 0;
        for (let i = 0; i < rPts.length - 1; i++) {
          const seg = calculateDistanceHaversine(
            parseFloat(rPts[i].lat),
            parseFloat(rPts[i].lng),
            parseFloat(rPts[i + 1].lat),
            parseFloat(rPts[i + 1].lng)
          );
          totalNm += seg.nm;
          totalKm += seg.km;
        }
        const firstPt = rPts[0];
        const lastPt = rPts[rPts.length - 1];
        const directSeg = calculateDistanceHaversine(
          parseFloat(firstPt.lat),
          parseFloat(firstPt.lng),
          parseFloat(lastPt.lat),
          parseFloat(lastPt.lng)
        );

        detectedRoutes.push({
          id: rt.id,
          name: rt.name || 'RUTE 1',
          distance_text: formatDynamicDistance(totalNm, totalKm),
          bearing_text: `${directSeg.bearing}° (${directSeg.headingName})`,
          speed_text: formatDynamicSpeed(parseFloat(rt.vesselSpeedKnots) || 25),
          waypoints_summary: `Titik A (${decimalToDms(parseFloat(firstPt.lat), parseFloat(firstPt.lng))}) → Titik B (${decimalToDms(parseFloat(lastPt.lat), parseFloat(lastPt.lng))})`,
          start_point: {
            label: 'A',
            lat: parseFloat(firstPt.lat),
            lng: parseFloat(firstPt.lng),
            dms: decimalToDms(parseFloat(firstPt.lat), parseFloat(firstPt.lng))
          },
          end_point: {
            label: 'B',
            lat: parseFloat(lastPt.lat),
            lng: parseFloat(lastPt.lng),
            dms: decimalToDms(parseFloat(lastPt.lat), parseFloat(lastPt.lng))
          },
          strokeColor: rt.strokeColor || '#10b981',
          points: rPts.map((p, idx) => ({
            lat: parseFloat(p.lat),
            lng: parseFloat(p.lng),
            label: idx === 0 ? 'A' : (idx === rPts.length - 1 ? 'B' : `WP-${idx + 1}`),
            dms: decimalToDms(parseFloat(p.lat), parseFloat(p.lng))
          }))
        });
      });

      captureDetectedData = {
        points: detectedPoints,
        polygons: detectedPolygons,
        patterns: detectedPatterns,
        routes: detectedRoutes,
        unit: activeGlobalUnit
      };

      // Update UI Counters
      const elPts = document.getElementById('captureCountPoints');
      const elPolys = document.getElementById('captureCountPolygons');
      const elPats = document.getElementById('captureCountPatterns');
      const elRts = document.getElementById('captureCountRoutes');
      if (elPts) elPts.textContent = String(detectedPoints.length);
      if (elPolys) elPolys.textContent = String(detectedPolygons.length);
      if (elPats) elPats.textContent = String(detectedPatterns.length);
      if (elRts) elRts.textContent = String(detectedRoutes.length);

      const listEl = document.getElementById('captureDetectedList');
      if (listEl) {
        const itemsHtml = [];
        detectedPoints.forEach((p) => {
          itemsHtml.push(`<div class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800"><span class="font-bold text-slate-800 dark:text-slate-200 truncate">${escapeHtml(p.emoji || '📍')} ${escapeHtml(p.label)} <span class="text-[11px] text-purple-500">(${escapeHtml(p.category)})</span></span><span class="font-mono text-[11px] text-slate-500 shrink-0">${escapeHtml(p.dms)}</span></div>`);
        });
        detectedPolygons.forEach((poly) => {
          itemsHtml.push(`<div class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800"><span class="font-bold text-blue-600 dark:text-blue-400 truncate">📐 ${escapeHtml(poly.name)}</span><span class="font-mono text-[11px] text-slate-500 shrink-0">${escapeHtml(poly.area_text)}</span></div>`);
        });
        detectedPatterns.forEach((pat) => {
          itemsHtml.push(`<div class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800"><span class="font-bold text-orange-600 dark:text-orange-400 truncate">🧭 ${escapeHtml(pat.name)}</span><span class="font-mono text-[11px] text-slate-500 shrink-0">S: ${escapeHtml(pat.spacing_text)}</span></div>`);
        });
        detectedRoutes.forEach((rt) => {
          itemsHtml.push(`<div class="flex items-center justify-between gap-2 px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800"><span class="font-bold text-emerald-600 dark:text-emerald-400 truncate">📏 ${escapeHtml(rt.name)}</span><span class="font-mono text-[11px] text-slate-500 shrink-0">${escapeHtml(rt.distance_text)}</span></div>`);
        });

        if (itemsHtml.length === 0) {
          listEl.innerHTML = `<div class="text-slate-400 italic text-center py-2">Belum ada komponen di dalam bingkai 16:9. Geser peta atau klik "Paskan Peta".</div>`;
        } else {
          listEl.innerHTML = itemsHtml.join('');
        }
      }
    }

    async function fetchOperasiSnapshotMeta(idOp) {
      if (!idOp || idOp <= 0) {
        cachedOperasiSnapshots = [];
        cachedOperasiRenopsList = [];
        updateCaptureRefDropdown();
        updateSavedStatusBanner();
        return;
      }
      try {
        const res = await gisFetch(`${BASE_URL}/api/gis/snapshots?id_operasi=${encodeURIComponent(idOp)}`);
        const json = await res.json();
        if (json && json.success) {
          const rawSnaps = Array.isArray(json.data?.snapshots) ? json.data.snapshots : (Array.isArray(json.snapshots) ? json.snapshots : (Array.isArray(json.data) ? json.data : []));
          const rawRenops = Array.isArray(json.renops_list) ? json.renops_list : (Array.isArray(json.data?.renops_list) ? json.data.renops_list : []);
          cachedOperasiSnapshots = rawSnaps;
          cachedOperasiRenopsList = rawRenops;
        }
      } catch (e) {
        console.warn('[Capture] Gagal memuat metadata snapshot:', e);
      }
      updateCaptureRefDropdown();
      updateSavedStatusBanner();
    }

    function updateCaptureRefDropdown() {
      if (!selJenis || !refWrapper || !selRef) return;
      const jenis = selJenis.value;
      const prevVal = selRef.value;

      // Peta LKP (RENOPS): 1x per operasi (id_referensi = NULL). Peta Rencana Operasi: per hari RENOPS (id_renops).
      if (isPerHari(jenis)) {
        refWrapper.classList.remove('hidden');
        if (cachedOperasiRenopsList.length === 0) {
          selRef.innerHTML = `<option value="">⚠️ Belum ada dokumen RENOPS pada operasi ini</option>`;
        } else {
          const opts = cachedOperasiRenopsList.map((r) => {
            return `<option value="${r.id_renops}">🗺️ RENOPS ${escapeHtml(r.hari_ke || '')} (${escapeHtml(r.tanggal_laporan || '')})</option>`;
          });
          selRef.innerHTML = opts.join('');
          if (prevVal && Array.from(selRef.options).some(o => o.value === String(prevVal))) {
            selRef.value = String(prevVal);
          } else {
            selRef.value = String(cachedOperasiRenopsList[0].id_renops);
          }
        }
      } else {
        refWrapper.classList.add('hidden');
        selRef.innerHTML = `<option value="">1x per Operasi SAR</option>`;
        selRef.value = '';
      }
    }

    function getPrintUrlForSelection(idOp, jenis, idRef) {
      if (!idOp) return '#';
      // Kembali ke editor draf dokumen RENOPS (hari terpilih, atau hari pertama untuk Peta LKP); cetak draf/terbit dari sana.
      const ref = idRef || (cachedOperasiRenopsList[0] ? cachedOperasiRenopsList[0].id_renops : '');
      return ref ? `${BASE_URL}/operasi.php?action=renops_dokumen&id_renops=${ref}` : `${BASE_URL}/operasi_detail.php?id=${idOp}&tab=cetak`;
    }

    function updateSavedStatusBanner() {
      const banner = document.getElementById('captureSavedStatusBanner');
      if (!banner || !selOperasi || !selJenis) return;
      const idOp = parseInt(selOperasi.value, 10) || 0;
      const jenis = selJenis.value;
      const idRef = (isPerHari(jenis) && selRef && selRef.value) ? parseInt(selRef.value, 10) : null;

      if (!idOp) {
        banner.classList.add('hidden');
        return;
      }

      let found = null;
      if (isPerHari(jenis)) {
        found = cachedOperasiSnapshots.find((s) => {
          return String(s.jenis_dokumen) === jenis && parseInt(s.id_referensi, 10) === idRef;
        });
      } else {
        found = cachedOperasiSnapshots.find((s) => String(s.jenis_dokumen) === String(jenis));
      }

      if (found) {
        const printUrl = getPrintUrlForSelection(idOp, jenis, idRef);
        banner.classList.remove('hidden');
        banner.innerHTML = `
          <div class="flex items-center justify-between gap-2">
            <div>
              <div class="font-extrabold text-emerald-700 dark:text-emerald-300">✓ Snapshot 16:9 Tersedia pada Dokumen Ini</div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400">Terakhir diperbarui: ${escapeHtml(found.updated_at || '')}</div>
            </div>
            <a href="${escapeHtml(printUrl)}" target="_blank" class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shrink-0 shadow-xs transition">
              🖨️ Lihat Cetakan
            </a>
          </div>
        `;
      } else {
        banner.classList.add('hidden');
      }
    }

    function fitComponentsIntoCaptureBox() {
      if (!map) return;
      const coords = [];
      (savedPoints || []).forEach(p => {
        if (!isIdHidden(hiddenPointIds, p.id) && p.lat && p.lng) {
          if (!currentOperasiId || parseInt(p.id_operasi, 10) === currentOperasiId) {
            coords.push([parseFloat(p.lat), parseFloat(p.lng)]);
          }
        }
      });
      (savedPolygons || []).forEach(poly => {
        if (!isIdHidden(hiddenPolygonIds, poly.id) && Array.isArray(poly.points)) {
          poly.points.forEach(pt => coords.push([parseFloat(pt.lat), parseFloat(pt.lng)]));
        }
      });
      (savedPatterns || []).forEach(pat => {
        if (!isIdHidden(hiddenPatternIds, pat.id) && Array.isArray(pat.waypoints)) {
          pat.waypoints.forEach(wp => coords.push([parseFloat(wp.lat), parseFloat(wp.lng)]));
        }
      });
      (savedRoutes || []).forEach(rt => {
        if (!isIdHidden(hiddenRouteIds, rt.id) && Array.isArray(rt.points)) {
          rt.points.forEach(pt => coords.push([parseFloat(pt.lat), parseFloat(pt.lng)]));
        }
      });

      if (coords.length === 0 && currentOperasi && currentOperasi.koordinat) {
        const parsed = parseCoordinatesString(currentOperasi.koordinat);
        if (parsed) coords.push([parsed.lat, parsed.lng]);
      }

      if (coords.length === 1) {
        map.setView(coords[0], 13);
      } else if (coords.length > 1) {
        const bounds = L.latLngBounds(coords);
        map.fitBounds(bounds, {
          paddingTopLeft: [60, 60],
          paddingBottomRight: [window.innerWidth >= 900 ? 410 : 60, 60],
          maxZoom: 15
        });
      }
      setTimeout(detectComponentsInCaptureBox, 250);
    }

    function openCaptureMode(docType = null, refId = null) {
      isCaptureModeActive = true;
      overlay.classList.remove('hidden');
      panel.classList.remove('hidden');
      btnToggle.classList.remove('bg-emerald-600');
      btnToggle.classList.add('bg-amber-600', 'ring-2', 'ring-amber-300');

      if (selOperasi && currentOperasiId) {
        selOperasi.value = String(currentOperasiId);
      }
      if (docType && selJenis) {
        if (['RENOPS', 'RENOPS_RENCANA'].includes(docType)) {
          selJenis.value = docType;
        }
      }

      computeCaptureFrameGeometry();
      detectComponentsInCaptureBox();

      const opIdToFetch = selOperasi && selOperasi.value ? parseInt(selOperasi.value, 10) : currentOperasiId;
      fetchOperasiSnapshotMeta(opIdToFetch).then(() => {
        if (refId && selRef && selJenis && isPerHari(selJenis.value)) {
          selRef.value = String(refId);
          updateSavedStatusBanner();
        }
      });
    }

    function closeCaptureMode() {
      isCaptureModeActive = false;
      overlay.classList.add('hidden');
      panel.classList.add('hidden');
      btnToggle.classList.remove('bg-amber-600', 'ring-2', 'ring-amber-300');
      btnToggle.classList.add('bg-emerald-600');
    }

    /**
     * Offscreen 1280x720 (16:9) High-Resolution Canvas Synthesizer
     * Renders map tiles (with proxy fallback) + all active vector layers + icons + tactical HUD
     */
    async function renderCaptureCanvas16x9() {
      const CANVAS_W = 1280;
      const CANVAS_H = 720;
      const canvas = document.createElement('canvas');
      canvas.width = CANVAS_W;
      canvas.height = CANVAS_H;
      const ctx = canvas.getContext('2d');

      computeCaptureFrameGeometry();
      const scaleX = CANVAS_W / Math.max(1, captureBoxRect.width);
      const scaleY = CANVAS_H / Math.max(1, captureBoxRect.height);

      function latLngToCanvas(lat, lng) {
        const pt = map.latLngToContainerPoint([lat, lng]);
        return {
          x: (pt.x - captureBoxRect.x) * scaleX,
          y: (pt.y - captureBoxRect.y) * scaleY
        };
      }

      // 1. Tactical Maritime Background + Subtle Grid (Fallback if offline)
      ctx.fillStyle = '#0b1329';
      ctx.fillRect(0, 0, CANVAS_W, CANVAS_H);
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.06)';
      ctx.lineWidth = 1;
      for (let gx = 0; gx < CANVAS_W; gx += 64) {
        ctx.beginPath(); ctx.moveTo(gx, 0); ctx.lineTo(gx, CANVAS_H); ctx.stroke();
      }
      for (let gy = 0; gy < CANVAS_H; gy += 64) {
        ctx.beginPath(); ctx.moveTo(0, gy); ctx.lineTo(CANVAS_W, gy); ctx.stroke();
      }

      // 2. Render Web Mercator Map Tiles onto the 1280x720 Canvas
      function lon2tileX(lon, z) {
        return ((lon + 180) / 360) * Math.pow(2, z);
      }
      function lat2tileY(lat, z) {
        const rad = (lat * Math.PI) / 180;
        return ((1 - Math.log(Math.tan(rad) + 1 / Math.cos(rad)) / Math.PI) / 2) * Math.pow(2, z);
      }
      function tileX2lon(x, z) {
        return (x / Math.pow(2, z)) * 360 - 180;
      }
      function tileY2lat(y, z) {
        const n = Math.PI - (2 * Math.PI * y) / Math.pow(2, z);
        return (180 / Math.PI) * Math.atan(0.5 * (Math.exp(n) - Math.exp(-n)));
      }

      function buildTileUrl(layerType, z, x, y) {
        const sNum = Math.abs(x + y) % 4;
        if (layerType === 'satellite') {
          // Identik dengan Peta Taktis Satelit pada Detail Operasi (Google Satellite HD)
          return `https://mt${sNum}.google.com/vt/lyrs=s&x=${x}&y=${y}&z=${z}`;
        }
        if (layerType === 'roadmap') {
          return `https://mt${sNum}.google.com/vt/lyrs=m&x=${x}&y=${y}&z=${z}`;
        }
        if (layerType === 'terrain') {
          return `https://mt${sNum}.google.com/vt/lyrs=p&x=${x}&y=${y}&z=${z}`;
        }
        if (layerType === 'osm') {
          const subs = ['a', 'b', 'c'];
          return `https://${subs[Math.abs(x + y) % 3]}.tile.openstreetmap.org/${z}/${x}/${y}.png`;
        }
        // Default: Google Hybrid (Satellite + Labels)
        return `https://mt${sNum}.google.com/vt/lyrs=y&x=${x}&y=${y}&z=${z}`;
      }

      function loadImageSafe(rawUrl) {
        return new Promise((resolve) => {
          if (!rawUrl) {
            resolve(null);
            return;
          }
          const img = new Image();
          img.crossOrigin = 'anonymous';
          let settled = false;
          const timer = setTimeout(() => {
            if (!settled) { settled = true; resolve(null); }
          }, 4500);

          img.onload = () => {
            if (!settled) { settled = true; clearTimeout(timer); resolve(img); }
          };
          img.onerror = () => {
            if (rawUrl.startsWith('http://') || rawUrl.startsWith('https://')) {
              const proxyImg = new Image();
              proxyImg.crossOrigin = 'anonymous';
              proxyImg.onload = () => {
                if (!settled) { settled = true; clearTimeout(timer); resolve(proxyImg); }
              };
              proxyImg.onerror = () => {
                if (!settled) { settled = true; clearTimeout(timer); resolve(null); }
              };
              proxyImg.src = `${BASE_URL}/api/gis/tile-proxy?url=${encodeURIComponent(rawUrl)}`;
            } else {
              if (!settled) { settled = true; clearTimeout(timer); resolve(null); }
            }
          };
          img.src = rawUrl;
        });
      }

      if (captureLatLngBounds) {
        const maxNative = 20;
        const z = Math.max(4, Math.min(maxNative, Math.round(map.getZoom())));
        const nw = captureLatLngBounds.getNorthWest();
        const se = captureLatLngBounds.getSouthEast();

        const minX = Math.floor(lon2tileX(nw.lng, z));
        const maxX = Math.floor(lon2tileX(se.lng, z));
        const minY = Math.floor(lat2tileY(nw.lat, z));
        const maxY = Math.floor(lat2tileY(se.lat, z));

        const tileJobs = [];
        for (let tx = minX; tx <= maxX; tx++) {
          for (let ty = minY; ty <= maxY; ty++) {
            if (tx < 0 || ty < 0) continue;
            const url = buildTileUrl(activeBasemap, z, tx, ty);
            const tNW = latLngToCanvas(tileY2lat(ty, z), tileX2lon(tx, z));
            const tSE = latLngToCanvas(tileY2lat(ty + 1, z), tileX2lon(tx + 1, z));
            tileJobs.push(
              loadImageSafe(url).then((img) => ({
                img,
                x: tNW.x,
                y: tNW.y,
                w: tSE.x - tNW.x,
                h: tSE.y - tNW.y
              }))
            );
          }
        }

        const loadedTiles = await Promise.all(tileJobs);
        loadedTiles.forEach((t) => {
          if (t.img && t.w > 0 && t.h > 0) {
            ctx.drawImage(t.img, t.x - 0.5, t.y - 0.5, t.w + 1, t.h + 1);
          }
        });
      }

      // 3. Draw Search Areas (Polygons / Rectangles / Circles) with Corner Badges A, B, C, D
      captureDetectedData.polygons.forEach((poly) => {
        const strokeColor = poly.strokeColor || '#ea580c';
        const fillColor = poly.fillColor || strokeColor;
        ctx.save();

        if (poly.shapeType === 'circle' && poly.centerLat && poly.centerLng) {
          const cPt = latLngToCanvas(poly.centerLat, poly.centerLng);
          const edgeLat = poly.centerLat + (poly.radiusNm || 1.0) / 60.0;
          const ePt = latLngToCanvas(edgeLat, poly.centerLng);
          const rPx = Math.max(8, Math.hypot(ePt.x - cPt.x, ePt.y - cPt.y));

          ctx.beginPath();
          ctx.arc(cPt.x, cPt.y, rPx, 0, Math.PI * 2);
          ctx.fillStyle = hexToRgba(fillColor, 0.22);
          ctx.fill();
          ctx.lineWidth = 3;
          ctx.strokeStyle = strokeColor;
          ctx.stroke();
          // Requirement 5: Nama search area pada hasil capture peta tidak perlu tampil
        } else if (Array.isArray(poly.points) && poly.points.length >= 3) {
          const cPts = poly.points.map(p => latLngToCanvas(p.lat, p.lng));
          ctx.beginPath();
          cPts.forEach((pt, idx) => {
            if (idx === 0) ctx.moveTo(pt.x, pt.y);
            else ctx.lineTo(pt.x, pt.y);
          });
          ctx.closePath();
          ctx.fillStyle = hexToRgba(fillColor, 0.22);
          ctx.fill();
          ctx.lineWidth = 3;
          ctx.strokeStyle = strokeColor;
          ctx.stroke();

          // Requirement 7: Titik A, B, C, D pada Polygon dan Persegi
          let sumX = 0, sumY = 0;
          cPts.forEach((pt, idx) => {
            sumX += pt.x;
            sumY += pt.y;
            const cornerLetter = extractCornerLetter(poly.points[idx].label, idx);
            drawCanvasCircleBadge(ctx, pt.x, pt.y, cornerLetter, strokeColor);
          });
          // Requirement 5: Nama search area pada hasil capture peta tidak perlu tampil
        }
        ctx.restore();
      });

      // 4. Draw Search Patterns (Tracks & Directional Arrows ONLY — NO Start/Exit 'S'/'E' badges per Requirement 7)
      captureDetectedData.patterns.forEach((pat) => {
        const wps = pat.waypoints || [];
        if (wps.length < 2) return;
        const cWps = wps.map(w => ({ ...latLngToCanvas(w.lat, w.lng), raw: w }));
        const patColor = pat.strokeColor || '#06b6d4';

        ctx.save();
        // Dark halo casing for visibility over satellite
        ctx.beginPath();
        cWps.forEach((pt, idx) => {
          if (idx === 0) ctx.moveTo(pt.x, pt.y);
          else ctx.lineTo(pt.x, pt.y);
        });
        ctx.lineWidth = 5.5;
        ctx.strokeStyle = 'rgba(15, 23, 42, 0.75)';
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';
        ctx.stroke();

        // Main pattern track
        ctx.beginPath();
        cWps.forEach((pt, idx) => {
          if (idx === 0) ctx.moveTo(pt.x, pt.y);
          else ctx.lineTo(pt.x, pt.y);
        });
        ctx.lineWidth = 3;
        ctx.strokeStyle = patColor;
        ctx.stroke();

        // Directional arrows along segments
        for (let i = 0; i < cWps.length - 1; i++) {
          const p1 = cWps[i];
          const p2 = cWps[i + 1];
          const segLen = Math.hypot(p2.x - p1.x, p2.y - p1.y);
          if (segLen > 32) {
            const mx = (p1.x + p2.x) / 2;
            const my = (p1.y + p2.y) / 2;
            const angle = Math.atan2(p2.y - p1.y, p2.x - p1.x);
            drawCanvasArrowHead(ctx, mx, my, angle, patColor);
          }
        }
        ctx.restore();
      });

      // 5. Draw Routes (Navigation Lines, Distance Pill, and ONLY Start Point 'A' & End Point 'B' per Requirement 7)
      captureDetectedData.routes.forEach((rt) => {
        const rPts = rt.points || [];
        if (rPts.length < 2) return;
        const cPts = rPts.map(p => latLngToCanvas(p.lat, p.lng));
        const rColor = rt.strokeColor || '#10b981';

        ctx.save();
        ctx.beginPath();
        cPts.forEach((pt, idx) => {
          if (idx === 0) ctx.moveTo(pt.x, pt.y);
          else ctx.lineTo(pt.x, pt.y);
        });
        ctx.lineWidth = 6;
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.85)';
        ctx.stroke();

        ctx.beginPath();
        ctx.setLineDash([10, 6]);
        cPts.forEach((pt, idx) => {
          if (idx === 0) ctx.moveTo(pt.x, pt.y);
          else ctx.lineTo(pt.x, pt.y);
        });
        ctx.lineWidth = 3.5;
        ctx.strokeStyle = rColor;
        ctx.stroke();
        ctx.setLineDash([]);

        // Requirement 7: Titik A ke B pada Jalur Manual (hanya Titik Awal A dan Titik Akhir B, bukan titik keseluruhan jalur)
        const startPt = cPts[0];
        const endPt = cPts[cPts.length - 1];
        drawCanvasCircleBadge(ctx, startPt.x, startPt.y, 'A', '#16a34a');
        drawCanvasCircleBadge(ctx, endPt.x, endPt.y, 'B', '#dc2626');

        const midIdx = Math.floor((cPts.length - 1) / 2);
        const mx = (cPts[midIdx].x + cPts[midIdx + 1].x) / 2;
        const my = (cPts[midIdx].y + cPts[midIdx + 1].y) / 2;
        drawCanvasPillLabel(ctx, mx, my - 14, `${rt.name}: ${rt.distance_text}`, '#ffffff', '#0f172a', rColor);
        ctx.restore();
      });

      // 6. Draw Tactical Points WITH their Component Icons (Custom Image, Alphabet, LKP Target, or Preset Emoji)
      const pointCustomImages = await Promise.all(
        captureDetectedData.points.map((pt) =>
          pt.customIconUrl ? loadImageSafe(pt.customIconUrl) : Promise.resolve(null)
        )
      );

      captureDetectedData.points.forEach((pt, idx) => {
        const cPt = latLngToCanvas(pt.lat, pt.lng);
        const isLkp = pt.category === 'LKP' || pt.pointType === 'lkp';
        const isKorban = pt.category === 'Penemuan Korban' || pt.category === 'Lokasi Penemuan Korban';
        const pinColor = pt.color || (isLkp ? '#dc2626' : (isKorban ? '#d97706' : '#ea580c'));
        const customImg = pointCustomImages[idx];

        ctx.save();
        // Outer tactical pulse ring for LKP / Korban
        if (isLkp || isKorban) {
          ctx.beginPath();
          ctx.arc(cPt.x, cPt.y - 22, 20, 0, Math.PI * 2);
          ctx.fillStyle = hexToRgba(pinColor, 0.28);
          ctx.fill();
        }

        // Draw Teardrop Pin Body anchored at (cPt.x, cPt.y) with head center at (cPt.x, cPt.y - 24)
        const headY = cPt.y - 24;
        const headR = 14;
        ctx.beginPath();
        ctx.arc(cPt.x, headY, headR, Math.PI * 0.82, Math.PI * 0.18, false);
        ctx.lineTo(cPt.x, cPt.y);
        ctx.closePath();
        ctx.fillStyle = pinColor;
        ctx.fill();
        ctx.lineWidth = 2.2;
        ctx.strokeStyle = '#ffffff';
        ctx.stroke();

        // Inner white circle for icon
        ctx.beginPath();
        ctx.arc(cPt.x, headY, 10.5, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();

        // Render specific Point Icon inside the white circle
        if (customImg) {
          ctx.save();
          ctx.beginPath();
          ctx.arc(cPt.x, headY, 9.5, 0, Math.PI * 2);
          ctx.clip();
          ctx.drawImage(customImg, cPt.x - 9.5, headY - 9.5, 19, 19);
          ctx.restore();
        } else if (pt.pointType === 'alphabet') {
          ctx.fillStyle = pinColor;
          ctx.font = '900 12px Arial, sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText(String(pt.alphabetChar || 'A').toUpperCase(), cPt.x, headY + 0.5);
        } else if (pt.pointType === 'lkp' || pt.pointType === 'target') {
          ctx.beginPath();
          ctx.arc(cPt.x, headY, 8.5, 0, Math.PI * 2);
          ctx.fillStyle = pinColor;
          ctx.fill();
          ctx.beginPath();
          ctx.arc(cPt.x, headY, 3.5, 0, Math.PI * 2);
          ctx.fillStyle = '#ffffff';
          ctx.fill();
          ctx.strokeStyle = '#ffffff';
          ctx.lineWidth = 1.6;
          ctx.beginPath();
          ctx.moveTo(cPt.x - 8.5, headY);
          ctx.lineTo(cPt.x + 8.5, headY);
          ctx.moveTo(cPt.x, headY - 8.5);
          ctx.lineTo(cPt.x, headY + 8.5);
          ctx.stroke();
        } else {
          const iconEmoji = pt.emoji || '📍';
          ctx.font = '13px "Apple Color Emoji", "Segoe UI Emoji", "Noto Color Emoji", Arial, sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText(iconEmoji, cPt.x, headY + 1);
        }

        // Point Label pill above pin
        const titleText = `${pt.label}`;
        drawCanvasPillLabel(ctx, cPt.x, headY - 25, titleText, '#0f172a', '#ffffff', pinColor);
        ctx.restore();
      });

      // 7. Draw Official Basarnas 16:9 Tactical Frame Header, Compass Rose & Coordinate Footer
      drawCaptureHudOverlay(ctx, CANVAS_W, CANVAS_H);

      return canvas.toDataURL('image/png', 0.92);
    }

    function hexToRgba(hex, alpha) {
      const clean = String(hex || '#ea580c').replace('#', '');
      const num = parseInt(clean.length === 3 ? clean.split('').map(c => c + c).join('') : clean, 16);
      const r = (num >> 16) & 255;
      const g = (num >> 8) & 255;
      const b = num & 255;
      return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    }

    function drawCanvasPillLabel(ctx, x, y, text, bgHex, textHex, borderHex) {
      ctx.save();
      ctx.font = 'bold 11px Arial, sans-serif';
      const padX = 8;
      const metrics = ctx.measureText(text);
      const w = Math.min(360, metrics.width + padX * 2);
      const h = 20;
      const rx = Math.max(8, Math.min(1280 - w - 8, x - w / 2));
      const ry = Math.max(42, Math.min(720 - h - 32, y - h / 2));

      ctx.fillStyle = bgHex === '#0f172a' ? 'rgba(15, 23, 42, 0.9)' : 'rgba(255, 255, 255, 0.94)';
      ctx.strokeStyle = borderHex || '#ea580c';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.roundRect(rx, ry, w, h, 5);
      ctx.fill();
      ctx.stroke();

      ctx.fillStyle = textHex || '#ffffff';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(text, rx + w / 2, ry + h / 2, w - 8);
      ctx.restore();
    }

    function drawCanvasArrowHead(ctx, x, y, angleRad, color) {
      ctx.save();
      ctx.translate(x, y);
      ctx.rotate(angleRad);
      ctx.beginPath();
      ctx.moveTo(7, 0);
      ctx.lineTo(-6, -5);
      ctx.lineTo(-3, 0);
      ctx.lineTo(-6, 5);
      ctx.closePath();
      ctx.fillStyle = color;
      ctx.fill();
      ctx.lineWidth = 1.2;
      ctx.strokeStyle = '#ffffff';
      ctx.stroke();
      ctx.restore();
    }

    function drawCanvasCircleBadge(ctx, x, y, char, bgHex) {
      ctx.save();
      ctx.beginPath();
      ctx.arc(x, y, 10, 0, Math.PI * 2);
      ctx.fillStyle = bgHex;
      ctx.fill();
      ctx.lineWidth = 2;
      ctx.strokeStyle = '#ffffff';
      ctx.stroke();
      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 10px Arial, sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(char, x, y);
      ctx.restore();
    }

    function drawCaptureHudOverlay(ctx, w, h) {
      ctx.save();
      // Top-left official badge
      const opId = selOperasi && selOperasi.value ? parseInt(selOperasi.value, 10) : currentOperasiId;
      const opObj = operasiList.find(o => parseInt(o.id_operasi, 10) === opId) || currentOperasi;
      const opTitle = opObj ? `OPS #${opObj.id_operasi} - ${opObj.nama_operasi}` : 'PETA TAKTIS OPERASI SAR';
      const docLabelMap = {
        RENOPS: 'PETA LOKASI LKP OPERASI SAR',
        RENOPS_RENCANA: 'PETA RENCANA OPERASI SAR (RENOPS)'
      };
      const selectedJenis = selJenis?.value || 'RENOPS';
      let docTitle = docLabelMap[selectedJenis] || 'DOKUMEN OPERASI SAR';
      if (isPerHari(selectedJenis) && selRef && selRef.value) {
        const foundRenops = cachedOperasiRenopsList.find(r => String(r.id_renops) === String(selRef.value));
        if (foundRenops && foundRenops.hari_ke) {
          docTitle = `PETA RENCANA OPERASI SAR (RENOPS ${foundRenops.hari_ke})`;
        }
      }

      ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
      ctx.strokeStyle = '#ea580c';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.roundRect(14, 12, 540, 44, 8);
      ctx.fill();
      ctx.stroke();

      ctx.fillStyle = '#f97316';
      ctx.font = 'bold 11px Arial, sans-serif';
      ctx.textAlign = 'left';
      ctx.textBaseline = 'top';
      ctx.fillText(`BASARNAS KENDARI • ${docTitle}`, 26, 19);

      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 13px Arial, sans-serif';
      ctx.fillText(String(opTitle).substring(0, 62), 26, 35);

      // Top-right Compass Rose (Utara / North)
      const cx = w - 42;
      const cy = 42;
      ctx.beginPath();
      ctx.arc(cx, cy, 24, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
      ctx.fill();
      ctx.lineWidth = 1.5;
      ctx.strokeStyle = '#94a3b8';
      ctx.stroke();

      ctx.beginPath();
      ctx.moveTo(cx, cy - 16);
      ctx.lineTo(cx + 6, cy + 4);
      ctx.lineTo(cx, cy);
      ctx.lineTo(cx - 6, cy + 4);
      ctx.closePath();
      ctx.fillStyle = '#ef4444';
      ctx.fill();

      ctx.fillStyle = '#ffffff';
      ctx.font = 'bold 10px Arial, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('U', cx, cy + 16);

      // Bottom Coordinate & Unit Bar
      ctx.fillStyle = 'rgba(15, 23, 42, 0.9)';
      ctx.fillRect(0, h - 26, w, 26);
      ctx.fillStyle = '#e2e8f0';
      ctx.font = 'bold 11px monospace';
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';
      const nw = captureLatLngBounds ? captureLatLngBounds.getNorthWest() : { lat: -4.03, lng: 122.48 };
      const se = captureLatLngBounds ? captureLatLngBounds.getSouthEast() : { lat: -4.05, lng: 122.52 };
      ctx.fillText(
        `WGS 84 | NW: ${decimalToDms(nw.lat, nw.lng)} • SE: ${decimalToDms(se.lat, se.lng)} | Satuan: ${activeGlobalUnit}`,
        14,
        h - 13
      );

      ctx.textAlign = 'right';
      ctx.fillStyle = '#fb923c';
      ctx.fillText(
        `Komponen: ${captureDetectedData.points.length} Titik • ${captureDetectedData.polygons.length} Area • ${captureDetectedData.patterns.length} Pola • ${captureDetectedData.routes.length} Jalur`,
        w - 14,
        h - 13
      );
      ctx.restore();
    }

    // Event Listeners
    btnToggle.addEventListener('click', () => {
      if (isCaptureModeActive) {
        closeCaptureMode();
      } else {
        openCaptureMode();
      }
    });

    if (btnClose) {
      btnClose.addEventListener('click', closeCaptureMode);
    }

    if (btnFit) {
      btnFit.addEventListener('click', fitComponentsIntoCaptureBox);
    }

    if (selOperasi) {
      selOperasi.addEventListener('change', () => {
        const val = parseInt(selOperasi.value, 10);
        if (val > 0) {
          const mainSel = document.getElementById('selectGisOperasi');
          if (mainSel && mainSel.value !== String(val)) {
            mainSel.value = String(val);
            mainSel.dispatchEvent(new Event('change'));
          }
          fetchOperasiSnapshotMeta(val);
          setTimeout(fitComponentsIntoCaptureBox, 300);
        } else {
          fetchOperasiSnapshotMeta(0);
        }
      });
    }

    if (selJenis) {
      selJenis.addEventListener('change', () => {
        updateCaptureRefDropdown();
        updateSavedStatusBanner();
      });
    }

    if (selRef) {
      selRef.addEventListener('change', updateSavedStatusBanner);
    }

    if (btnSave) {
      btnSave.addEventListener('click', async () => {
        const idOp = selOperasi && selOperasi.value ? parseInt(selOperasi.value, 10) : currentOperasiId;
        if (!idOp || idOp <= 0) {
          showToast('Silakan pilih Operasi SAR terlebih dahulu pada dropdown nomor 1 sebelum melakukan Capture Peta.', 'error');
          if (selOperasi) selOperasi.focus();
          return;
        }

        const jenis = selJenis ? selJenis.value : 'RENOPS';
        const idRef = (isPerHari(jenis) && selRef && selRef.value) ? parseInt(selRef.value, 10) : null;
        if (isPerHari(jenis) && (!idRef || idRef <= 0)) {
          showToast('Silakan pilih Hari RENOPS tujuan terlebih dahulu (atau buat dokumen RENOPS pada operasi ini).', 'error');
          return;
        }
        const keterangan = inputKet ? inputKet.value.trim() : '';

        const origHtml = btnSave.innerHTML;
        btnSave.disabled = true;
        btnSave.innerHTML = `<span>⏳ Merender Peta 16:9...</span>`;

        try {
          detectComponentsInCaptureBox();
          const dataUrl = await renderCaptureCanvas16x9();
          const nw = captureLatLngBounds ? captureLatLngBounds.getNorthWest() : { lat: -4.037, lng: 122.486 };
          const se = captureLatLngBounds ? captureLatLngBounds.getSouthEast() : { lat: -4.05, lng: 122.52 };

          const payload = {
            id_operasi: idOp,
            jenis_dokumen: jenis,
            id_referensi: idRef,
            image_base64: dataUrl,
            bounds: {
              north: nw.lat,
              west: nw.lng,
              south: se.lat,
              east: se.lng,
              zoom: map ? map.getZoom() : 11,
              basemap: activeBasemap
            },
            komponen: captureDetectedData,
            keterangan_tambahan: keterangan
          };

          const res = await gisFetch(`${BASE_URL}/api/gis/snapshots`, {
            method: 'POST',
            body: JSON.stringify(payload)
          });
          const json = await res.json();
          if (json && json.success) {
            await fetchOperasiSnapshotMeta(idOp);
            btnSave.innerHTML = `<span>✅ Tersimpan ke Dokumen!</span>`;
            setTimeout(() => {
              btnSave.disabled = false;
              btnSave.innerHTML = origHtml;
            }, 1800);
          } else {
            throw new Error((json && json.message) ? json.message : 'Gagal menyimpan snapshot peta.');
          }
        } catch (err) {
          console.error('[Capture Error]', err);
          showToast('Gagal menyimpan capture peta: ' + (err.message || err), 'error');
          btnSave.disabled = false;
          btnSave.innerHTML = origHtml;
        }
      });
    }

    if (map) {
      map.on('moveend zoomend resize', () => {
        if (isCaptureModeActive) {
          detectComponentsInCaptureBox();
        }
      });
    }
    window.addEventListener('resize', () => {
      if (isCaptureModeActive) {
        detectComponentsInCaptureBox();
      }
    });

    // Auto-open if URL has ?capture_doc=...
    if (initialData.captureDocParam) {
      setTimeout(() => {
        openCaptureMode(initialData.captureDocParam, initialData.captureRefParam || null);
        fitComponentsIntoCaptureBox();
      }, 450);
    }
  }

  // ==========================================
  // 14. BOOTSTRAP APPLICATION
  // ==========================================

  function bootstrapApp() {
    if (typeof L === 'undefined') {
      setTimeout(bootstrapApp, 50);
      return;
    }

    // 1. Inisialisasi Kontrol Navigasi & UI Shell Terlebih Dahulu (Tab, Sidebar, Basemap, dll)
    // Memastikan tab dan tombol SELALU responsif dan dapat diklik dalam kondisi apa pun
    try { initTabSwitcher(); } catch (e) { console.error('[GIS Bootstrap] TabSwitcher error:', e); }
    try { initSidebarToggle(); } catch (e) { console.error('[GIS Bootstrap] SidebarToggle error:', e); }
    try { initBasemapSwitcher(); } catch (e) { console.error('[GIS Bootstrap] BasemapSwitcher error:', e); }
    try { initGlobalUnitSwitcher(); } catch (e) { console.error('[GIS Bootstrap] GlobalUnitSwitcher error:', e); }
    try { initExportDropdown(); } catch (e) { console.error('[GIS Bootstrap] ExportDropdown error:', e); }
    try { initFullscreenToggle(); } catch (e) { console.error('[GIS Bootstrap] FullscreenToggle error:', e); }
    try { initOperasiSelector(); } catch (e) { console.error('[GIS Bootstrap] OperasiSelector error:', e); }

    // 2. Inisialisasi Inti Peta Geospasial Leaflet
    try { initLeafletMap(); } catch (e) { console.error('[GIS Bootstrap] LeafletMap error:', e); }

    // 3. Inisialisasi Modul Fitur Taktis (Pencarian Lokasi, Titik, Search Area, Pola, Rute, Capture Cetak)
    try { initLocationSearchBar(); } catch (e) { console.error('[GIS Bootstrap] LocationSearchBar error:', e); }
    try { initPointCreatorUI(); } catch (e) { console.error('[GIS Bootstrap] PointCreatorUI error:', e); }
    try { initPolygonManagerUI(); } catch (e) { console.error('[GIS Bootstrap] PolygonManagerUI error:', e); }
    try { initSearchPatternUI(); } catch (e) { console.error('[GIS Bootstrap] SearchPatternUI error:', e); }
    try { initMeasureDistanceUI(); } catch (e) { console.error('[GIS Bootstrap] MeasureDistanceUI error:', e); }
    try { initDocumentCaptureUI(); } catch (e) { console.error('[GIS Bootstrap] DocumentCaptureUI error:', e); }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapApp);
  } else {
    bootstrapApp();
  }
})();
