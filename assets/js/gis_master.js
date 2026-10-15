/**
 * QR-SAR - Tactical Maritime GIS Master Controller
 * KPP Kendari - Basarnas
 * 100% Faithful Implementation matching MapPlottingPage.tsx and SAR Operational Guidelines
 */

(function () {
  'use strict';

  // Base URL & State Data from server injection
  const BASE_URL = window.location.origin;
  const initialData = window.SAR_INITIAL_GIS_DATA || {};
  const csrfToken = initialData.csrfToken || window.CSRF_TOKEN || (document.querySelector('meta[name="csrf-token"]') ? document.querySelector('meta[name="csrf-token"]').getAttribute('content') : '');
  let filterMode = initialData.filterMode || (initialData.currentOperasiId ? 'operasi' : 'all');
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
    return `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9px] font-black bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30" title="Misi: ${escapeHtml(title)}">🚨 Ops #${idOps}</span>`;
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
                <span class="text-slate-600 dark:text-slate-400 text-[10px] ml-1">(Komponen tanpa ID Operasi SAR)</span>
              </div>
            </div>
            <button type="button" class="btn-switch-mode-all text-[10px] font-bold text-slate-500 hover:text-orange-500 underline shrink-0 ml-2 cursor-pointer">Semua</button>
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
            <button type="button" class="btn-switch-mode-all text-[10px] font-bold text-slate-500 hover:text-red-500 underline shrink-0 ml-2 cursor-pointer" title="Kembali ke semua plotting">Lepas</button>
          </div>
        `;
      } else {
        banner.innerHTML = `
          <div class="px-3 py-1.5 bg-slate-100 dark:bg-slate-800/60 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
            <div class="flex items-center gap-1.5 min-w-0">
              <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
              <div class="truncate">
                <span class="font-extrabold text-slate-700 dark:text-slate-200">2. Semua Komponen</span>
                <span class="text-slate-500 dark:text-slate-400 text-[10px] ml-1">(Base Map & Operasi Aktif)</span>
              </div>
            </div>
          </div>
        `;
      }
    }

    const pBadge = document.getElementById('pointsBadgeCount');
    if (pBadge) pBadge.textContent = String(savedPoints.length);
    const polyBadge = document.getElementById('polygonsBadgeCount');
    if (polyBadge) polyBadge.textContent = String(savedPolygons.length);
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

  // Polygon Drawing state
  let isDrawingPolygon = false;
  let editingPolygonId = null;
  let activePolygonPoints = [];
  let polyStrokeColor = '#ea580c';
  let polyStrokeWeight = 2.5;
  let polyFillColor = '#ea580c';
  let polyFillOpacity = 0.2;

  // Search Pattern state (Default: Parallel Track)
  let activePatternType = 'parallel_track';
  let activeGeneratedWaypoints = [];
  let activeGeneratedDistanceNm = 0;
  let editingPatternId = null;

  // Measure Distance & Route Planning state (Jarak Lurus 2 Titik & Jalur Manual)
  let isMeasureModeActive = false;
  let measureModeType = 'straight'; // 'straight' or 'path'
  let measurePoints = [];
  let currentActiveRouteId = null;
  let autoSaveRouteDebounceTimer = null;
  let vesselSpeedKnots = 25;
  let speedUnit = 'knot';
  let patternSpacingUnit = 'NM'; // 'NM' or 'KM'

  // Hidden Layers IDs for eye toggles
  let hiddenPointIds = new Set();
  let hiddenPolygonIds = new Set();
  let hiddenPatternIds = new Set();
  let hiddenRouteIds = new Set();

  // Leaflet Map & Layer References
  let map = null;
  let tileLayers = {};
  let pointMarkersMap = new Map();
  let draftPointMarker = null;
  let polygonOverlaysMap = new Map();
  let activeDrawingPolygonOverlay = null;
  let activeDrawingVertexMarkers = [];
  let patternPolylinesMap = new Map();
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
    function formatDmsPart(val, isLat) {
      const dir = isLat ? (val < 0 ? 'S' : 'N') : (val < 0 ? 'W' : 'E');
      const absVal = Math.abs(val);
      let d = Math.floor(absVal);
      const mFloat = (absVal - d) * 60;
      let m = Math.floor(mFloat);
      let s = Math.round((mFloat - m) * 60);
      if (s >= 60) {
        s = 0;
        m += 1;
        if (m >= 60) {
          m = 0;
          d += 1;
        }
      }
      const padD = isLat ? String(d).padStart(2, '0') : String(d).padStart(3, '0');
      return `${padD}° ${String(m).padStart(2, '0')}' ${String(s).padStart(2, '0')}" ${dir}`;
    }
    return `${formatDmsPart(lat, true)}, ${formatDmsPart(lng, false)}`;
  }

  function parseCoordinatesString(text) {
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

  function calculatePolygonSphericalArea(coords) {
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
      perimeterKm += calculateDistanceHaversine(p1.lat, p1.lng, p2.lat, p2.lng).km;
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
    const width = isEditing ? 46 : 38;
    const height = isEditing ? 54 : 46;
    const cx = width / 2;

    let innerContent = '';

    if (customUrl) {
      innerContent = `
        <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
        <image href="${escapeHtml(customUrl)}" x="${cx - 10}" y="6" width="20" height="20" preserveAspectRatio="xMidYMid slice" />
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
      <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="display:block;">
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
      zoomControl: false,
      attributionControl: false
    });

    // Zoom control on bottom right
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Modern High-Resolution Basemaps with maxNativeZoom Scaling (No Blank on Zoom)
    // 1. Google Maps Hybrid (High-Res Satellite + Roads + Labels)
    tileLayers.google_hybrid = L.tileLayer(
      'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
      { maxNativeZoom: 20, maxZoom: 22, subdomains: ['mt0', 'mt1', 'mt2', 'mt3'] }
    );

    // 2. ESRI World Imagery (High-Res Satellite) with maxNativeZoom 18
    tileLayers.satellite = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      { maxNativeZoom: 18, maxZoom: 22 }
    );

    // 3. CartoDB Labels overlay for ESRI Satellite
    tileLayers.labels = L.tileLayer(
      'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png',
      { subdomains: 'abcd', maxNativeZoom: 19, maxZoom: 22 }
    );

    // 4. Google Roadmap for crisp vector street networks
    tileLayers.roadmap = L.tileLayer(
      'https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}',
      { maxNativeZoom: 20, maxZoom: 22, subdomains: ['mt0', 'mt1', 'mt2', 'mt3'] }
    );

    // 5. OpenTopoMap for Relief & Contours with maxNativeZoom 17
    tileLayers.terrain = L.tileLayer(
      'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      { maxNativeZoom: 17, maxZoom: 22 }
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
      map.addLayer(tileLayers.labels);
    } else if (type === 'roadmap') {
      map.addLayer(tileLayers.roadmap);
    } else if (type === 'terrain') {
      map.addLayer(tileLayers.terrain);
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

    // 2. If Polygon Drawing is active, add vertex
    if (isDrawingPolygon) {
      addPolygonVertex(lat, lng);
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

      // Check verified presets with multi-word matching from database
      const terms = rawQ.toLowerCase().split(/\s+/).filter(Boolean);
      const verifiedPresets = getVerifiedMaritimePresets();
      verifiedPresets.forEach((item) => {
        const fullHaystack = `${item.name} ${item.address} ${item.type || ''} ${item.dms || ''}`.toLowerCase();
        const matchesAll = terms.every(t => fullHaystack.includes(t));
        if (matchesAll) {
          instantResults.push({
            ...item,
            badge: item.badge || 'SAR SULTRA',
            icon: item.type === 'Pangkalan / Kantor SAR' ? 'shield' : 'map-pin'
          });
        }
      });

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
                ${r.badge ? `<span class="text-[9px] px-1.5 py-0.2 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold border border-orange-500/30 shrink-0">${escapeHtml(r.badge)}</span>` : ''}
              </div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">${escapeHtml(r.address || '')}</div>
              <div class="text-[10px] font-mono text-slate-400 dark:text-slate-500 mt-0.5">${escapeHtml(r.dms || decimalToDms(r.lat, r.lng))}</div>
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
        gridSar.innerHTML = `<div class="col-span-4 p-3 text-center text-[10px] text-slate-400 italic">Tidak ada logo yang cocok dengan pencarian.</div>`;
        return;
      }

      gridSar.innerHTML = filtered
        .map(
          (p) => `
          <button type="button" data-icon="${p.type}" title="${p.label}" class="preset-icon-btn p-1.5 rounded-xl border border-slate-800 bg-slate-900 hover:border-orange-500 text-center flex flex-col items-center justify-center gap-1 transition cursor-pointer ${pointIconType === p.type ? 'border-orange-500 ring-2 ring-orange-500/30 bg-orange-500/10' : ''}">
            <span class="text-base select-none">${p.emoji}</span>
            <span class="text-[9px] font-bold text-slate-300 truncate w-full px-0.5">${p.label}</span>
          </button>
        `
        )
        .join('');

      gridSar.querySelectorAll('.preset-icon-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          pointIconType = btn.getAttribute('data-icon');
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
        const center = map.getCenter();
        openPointCreator({
          label: '',
          lat: center.lat,
          lng: center.lng,
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

    // Custom Icon Upload & File Management Handlers
    const fileInput = document.getElementById('pcCustomIconFile');
    const triggerUploadBtn = document.getElementById('btnTriggerUploadIcon');
    const removeIconBtn = document.getElementById('btnRemoveCustomIcon');
    const urlInput = document.getElementById('pcCustomIconUrl');
    const previewCard = document.getElementById('pcCustomIconPreviewCard');
    const thumbImg = document.getElementById('pcCustomIconThumb');
    const fileNameEl = document.getElementById('pcCustomIconFileName');

    if (triggerUploadBtn && fileInput) {
      triggerUploadBtn.addEventListener('click', () => {
        fileInput.click();
      });
    }

    if (fileInput) {
      fileInput.addEventListener('change', async (e) => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;

        // Compress raster image to max 128x128 on offscreen canvas to prevent database overflow
        if (file.type === 'image/svg+xml') {
          const reader = new FileReader();
          reader.onload = (re) => {
            pointCustomIconUrl = re.target.result;
            if (thumbImg) thumbImg.src = pointCustomIconUrl;
            if (fileNameEl) fileNameEl.textContent = file.name;
            if (previewCard) previewCard.classList.remove('hidden');
            updateDraftMarkerVisual();
          };
          reader.readAsDataURL(file);
        } else {
          const reader = new FileReader();
          reader.onload = (re) => {
            const img = new Image();
            img.onload = () => {
              const maxDim = 128;
              let w = img.width;
              let h = img.height;
              if (w > maxDim || h > maxDim) {
                const ratio = Math.min(maxDim / w, maxDim / h);
                w = Math.round(w * ratio);
                h = Math.round(h * ratio);
              }
              const canvas = document.createElement('canvas');
              canvas.width = w;
              canvas.height = h;
              const ctx = canvas.getContext('2d');
              ctx.drawImage(img, 0, 0, w, h);
              pointCustomIconUrl = canvas.toDataURL('image/png', 0.85);
              if (thumbImg) thumbImg.src = pointCustomIconUrl;
              if (fileNameEl) fileNameEl.textContent = file.name;
              if (previewCard) previewCard.classList.remove('hidden');
              updateDraftMarkerVisual();
            };
            img.onerror = () => {
              pointCustomIconUrl = re.target.result;
              if (thumbImg) thumbImg.src = pointCustomIconUrl;
              if (fileNameEl) fileNameEl.textContent = file.name;
              if (previewCard) previewCard.classList.remove('hidden');
              updateDraftMarkerVisual();
            };
            img.src = re.target.result;
          };
          reader.readAsDataURL(file);
        }

        // Upload to server endpoint /api/gis/upload-icon
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
            pointCustomIconUrl = json.url;
            if (urlInput) urlInput.value = json.url;
            updateDraftMarkerVisual();
          }
        } catch (err) {
          console.warn('Upload to server failed, using compressed local DataURL:', err);
        }
      });
    }

    if (removeIconBtn) {
      removeIconBtn.addEventListener('click', () => {
        pointCustomIconUrl = '';
        if (fileInput) fileInput.value = '';
        if (urlInput) urlInput.value = '';
        if (previewCard) previewCard.classList.add('hidden');
        updateDraftMarkerVisual();
      });
    }

    if (urlInput) {
      urlInput.addEventListener('input', (e) => {
        const val = e.target.value.trim();
        pointCustomIconUrl = val;
        if (val) {
          if (thumbImg) thumbImg.src = val;
          if (fileNameEl) fileNameEl.textContent = val.split('/').pop() || 'Custom Icon';
          if (previewCard) previewCard.classList.remove('hidden');
        } else {
          if (previewCard) previewCard.classList.add('hidden');
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
      label.textContent = '🔓 Mode Geser Aktif';
      btn.className = 'flex-1 py-1.5 px-3 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition bg-amber-500/20 border-amber-500/40 text-amber-300 shadow';
    } else {
      iconClosed.classList.remove('hidden');
      iconOpen.classList.add('hidden');
      label.textContent = '🔒 Posisi Terkunci';
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

  function openPointCreator(data = {}) {
    isCreatingPoint = true;
    editingPointId = data.id || null;

    document.getElementById('pcPointName').value = data.label || '';

    // Normalize category strictly to the 8 valid options
    const VALID_CATEGORIES = [
      'Kantor SAR',
      'POS SAR',
      'Unit Siaga SAR',
      'LKP',
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
      else if (cat.includes('Kantor')) cat = 'Kantor SAR';
      else if (cat.includes('Posko')) cat = 'Posko';
      else if (cat.includes('POS')) cat = 'POS SAR';
      else if (cat.includes('Siaga')) cat = 'Unit Siaga SAR';
      else cat = 'Tanpa Kategori';
    }
    const catSelect = document.getElementById('pcPointCategory');
    if (catSelect) catSelect.value = cat;

    document.getElementById('pcPointDesc').value = data.description || '';

    pointIconType = data.pointType || 'pin_umum';
    pointAlphabetChar = data.alphabetChar || 'A';
    pointCustomIconUrl = data.customIconUrl || '';

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
    const urlInput = document.getElementById('pcCustomIconUrl');
    const previewCard = document.getElementById('pcCustomIconPreviewCard');
    const thumbImg = document.getElementById('pcCustomIconThumb');
    const fileNameEl = document.getElementById('pcCustomIconFileName');
    if (fileInput) fileInput.value = '';
    if (urlInput) urlInput.value = pointCustomIconUrl;
    if (pointCustomIconUrl && previewCard) {
      previewCard.classList.remove('hidden');
      if (thumbImg) thumbImg.src = pointCustomIconUrl;
      if (fileNameEl) fileNameEl.textContent = pointCustomIconUrl.split('/').pop() || 'Custom Icon';
    } else if (previewCard) {
      previewCard.classList.add('hidden');
    }

    pointDraftCoords = {
      lat: data.lat !== undefined ? data.lat : -4.015,
      lng: data.lng !== undefined ? data.lng : 122.585
    };

    updatePointDraftCoords(pointDraftCoords.lat, pointDraftCoords.lng);

    const titleEl = document.getElementById('pointCreatorTitle');
    if (titleEl) {
      titleEl.innerHTML = editingPointId ? '<span>✏️ Edit Titik SAR</span>' : '<span>📍 Plot Titik Taktis Baru</span>';
    }

    document.getElementById('pointCreatorCard').classList.remove('hidden');

    // Pan map to draft position
    map.panTo([pointDraftCoords.lat, pointDraftCoords.lng]);
  }

  function closePointCreator() {
    isCreatingPoint = false;
    editingPointId = null;
    document.getElementById('pointCreatorCard').classList.add('hidden');

    const previewCard = document.getElementById('pcCustomIconPreviewCard');
    const fileInput = document.getElementById('pcCustomIconFile');
    if (previewCard) previewCard.classList.add('hidden');
    if (fileInput) fileInput.value = '';

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
    const html = createMarkerHtml(
      {
        label: name,
        pointType: pointIconType,
        alphabetChar: pointAlphabetChar,
        customIconUrl: pointCustomIconUrl
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

    const pointPayload = {
      id: editingPointId || `pt-${Date.now()}`,
      id_operasi: targetOpId,
      label: name,
      category: category,
      pointType: pointIconType,
      alphabetChar: pointAlphabetChar,
      customIconUrl: pointCustomIconUrl,
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
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-blue-500/20 text-blue-400 border border-blue-500/30">🏢 Kantor SAR</span>';
      case 'POS SAR':
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-orange-500/20 text-orange-400 border border-orange-500/30">📻 POS SAR</span>';
      case 'Unit Siaga SAR':
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">📡 Unit Siaga SAR</span>';
      case 'LKP':
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-red-500/20 text-red-400 border border-red-500/30">🚨 LKP</span>';
      case 'Posko':
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">🏕️ Posko</span>';
      case 'Entry Poin':
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-green-500/20 text-green-400 border border-green-500/30">🟢 Entry Poin</span>';
      case 'Exit Poin':
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-rose-500/20 text-rose-400 border border-rose-500/30">🔴 Exit Poin</span>';
      default:
        return '<span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold bg-slate-500/20 text-slate-400 border border-slate-500/30">⚪ Tanpa Kategori</span>';
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
      if (hiddenPointIds.has(pt.id)) return;

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
          <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-200 border border-slate-700">
            <span>Kategori:</span>
            <span class="text-orange-400 font-extrabold">${escapeHtml(cat)}</span>
          </div>
          <div class="text-[10px] font-mono text-slate-400 pt-0.5">${escapeHtml(dms)}</div>
          ${pt.description ? `<div class="text-[10px] text-slate-300 pt-1 border-t border-slate-700">${escapeHtml(pt.description)}</div>` : ''}
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
            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-800 text-slate-200 border border-slate-700">
              <span>Kategori:</span>
              <span class="text-orange-400 font-extrabold">${escapeHtml(cat)}</span>
            </div>
            <div class="text-[10px] font-mono text-slate-400 pt-0.5">${escapeHtml(newDms)}</div>
            ${pt.description ? `<div class="text-[10px] text-slate-300 pt-1 border-t border-slate-700">${escapeHtml(pt.description)}</div>` : ''}
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
        const isHidden = hiddenPointIds.has(pt.id);
        const dms = decimalToDms(pt.lat, pt.lng);
        const distFromKansar = calculateDistanceHaversine(-4.037694, 122.486694, pt.lat, pt.lng);
        const preset = PRESET_ICONS.find((p) => p.type === pt.pointType) || PRESET_ICONS[0];
        const category = pt.category || 'Tanpa Kategori';
        const hasName = pt.label && pt.label.trim();
        const displayTitle = hasName
          ? pt.label
          : (category !== 'Tanpa Kategori' ? `[${category}] (Tanpa Nama)` : '(Titik Tanpa Nama)');

        const iconDisplay = pt.customIconUrl
          ? `<img src="${pt.customIconUrl}" class="w-full h-full object-contain rounded-lg p-0.5">`
          : pt.pointType === 'alphabet'
            ? `<span class="font-black text-sm">${pt.alphabetChar || 'A'}</span>`
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
                  <span class="text-[9px] text-orange-600 dark:text-orange-400 font-mono">${distFromKansar.nm} NM (${distFromKansar.bearing}° ${distFromKansar.headingName})</span>
                </div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono truncate mt-0.5">${escapeHtml(dms)}</div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePointVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded cursor-pointer" title="${isHidden ? 'Tampilkan' : 'Sembunyikan'}">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPoint('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded cursor-pointer" title="Edit">
                ✏️
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePoint('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded cursor-pointer" title="Hapus">
                🗑️
              </button>
            </div>
          </div>
        </div>
      `;
      })
      .join('');
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

  function startPolygonDrawing(existingData = null) {
    isDrawingPolygon = true;
    editingPolygonId = existingData ? existingData.id : null;
    activePolygonPoints = existingData ? [...existingData.points] : [];

    polyStrokeColor = existingData ? existingData.strokeColor : '#ea580c';
    polyStrokeWeight = existingData ? existingData.strokeWeight : 2.5;
    polyFillColor = existingData ? existingData.fillColor : '#ea580c';
    polyFillOpacity = existingData ? existingData.fillOpacity : 0.2;

    document.getElementById('polyEditorName').value = existingData ? existingData.name : 'Sektor Pencarian Alpha';
    document.getElementById('polygonDrawingEditorCard').classList.remove('hidden');
    const btnNewPoly = document.getElementById('btnStartNewPolygon');
    if (btnNewPoly) btnNewPoly.classList.add('hidden');

    updateLivePolygonVisual();
  }

  function cancelPolygonDrawing() {
    isDrawingPolygon = false;
    editingPolygonId = null;
    activePolygonPoints = [];

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
    const areaStats = calculatePolygonSphericalArea(activePolygonPoints);
    document.getElementById('polyLiveSqNm').textContent = `${areaStats.sqNm.toFixed(2)} NM²`;
    document.getElementById('polyLiveSqKm').textContent = `${areaStats.sqKm.toFixed(2)} km² (${areaStats.hectares.toFixed(1)} Ha)`;
    document.getElementById('polyLivePerimeterNm').textContent = `${areaStats.perimeterNm.toFixed(2)} NM`;
    document.getElementById('polyLivePerimeterKm').textContent = `${areaStats.perimeterKm.toFixed(2)} km`;

    // Nearest Posko detection
    if (activePolygonPoints.length > 0) {
      const midLat = activePolygonPoints.reduce((acc, p) => acc + p.lat, 0) / activePolygonPoints.length;
      const midLng = activePolygonPoints.reduce((acc, p) => acc + p.lng, 0) / activePolygonPoints.length;
      const dist = calculateDistanceHaversine(-4.037694, 122.486694, midLat, midLng);
      document.getElementById('polyLiveNearestPosko').textContent = `Kansar Kendari (${dist.nm} NM, ${dist.bearing}° ${dist.headingName})`;
    }

    // 2. Render Vertices Table
    const tableEl = document.getElementById('polyVerticesListTable');
    if (tableEl) {
      tableEl.innerHTML = activePolygonPoints
        .map(
          (pt, idx) => `
        <div class="flex items-center justify-between p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-[11px]">
          <div class="flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-orange-500 text-white font-bold text-[10px] flex items-center justify-center">${String.fromCharCode(65 + (idx % 26))}</span>
            <span class="font-bold text-white">${pt.label}</span>
          </div>
          <div class="text-[10px] font-mono text-slate-400">${decimalToDms(pt.lat, pt.lng)}</div>
          <button type="button" onclick="window.SAR_GIS.removeVertex(${idx})" class="text-slate-500 hover:text-red-400 p-0.5">✕</button>
        </div>
      `
        )
        .join('');
    }

    // 3. Render Map Polygon Overlay
    if (activeDrawingPolygonOverlay) {
      map.removeLayer(activeDrawingPolygonOverlay);
      activeDrawingPolygonOverlay = null;
    }

    if (activePolygonPoints.length >= 2) {
      const latlngs = activePolygonPoints.map((p) => [p.lat, p.lng]);
      activeDrawingPolygonOverlay = L.polygon(latlngs, {
        color: polyStrokeColor,
        weight: polyStrokeWeight,
        fillColor: polyFillColor,
        fillOpacity: polyFillOpacity
      }).addTo(map);
    }

    // 4. Render Vertex Badges (Draggable handles)
    activeDrawingVertexMarkers.forEach((m) => map.removeLayer(m));
    activeDrawingVertexMarkers = [];

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

  async function handleSavePolygon() {
    const name = document.getElementById('polyEditorName').value.trim();
    if (!name) {
      alert('Nama Poligon Area wajib diisi!');
      return;
    }

    if (activePolygonPoints.length < 3) {
      alert('Poligon area minimal membutuhkan 3 titik sudut (A, B, C)!');
      return;
    }

    const targetOpId = (filterMode === 'operasi' && currentOperasiId)
      ? currentOperasiId
      : (editingPolygonId ? (rawAllPolygons.find(p => p.id === editingPolygonId)?.id_operasi || null) : null);

    const areaStats = calculatePolygonSphericalArea(activePolygonPoints);
    const payload = {
      id: editingPolygonId || `poly-${Date.now()}`,
      id_operasi: targetOpId,
      name: name,
      points: activePolygonPoints,
      areaNm2: areaStats.sqNm,
      strokeColor: polyStrokeColor,
      strokeWeight: polyStrokeWeight,
      fillColor: polyFillColor,
      fillOpacity: polyFillOpacity
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

    // Render on map
    savedPolygons.forEach((poly) => {
      if (hiddenPolygonIds.has(poly.id)) return;
      if (!poly.points || poly.points.length < 3) return;

      const latlngs = poly.points.map((p) => [p.lat, p.lng]);
      const overlay = L.polygon(latlngs, {
        color: poly.strokeColor || '#ea580c',
        weight: poly.strokeWeight || 2.5,
        fillColor: poly.fillColor || '#ea580c',
        fillOpacity: poly.fillOpacity || 0.2
      }).addTo(map);

      const stats = calculatePolygonSphericalArea(poly.points);
      const safePolyId = escapeHtml(poly.id);
      const safePolyName = escapeHtml(poly.name);

      overlay.bindPopup(`
        <div class="p-3 text-xs space-y-1">
          <div class="font-bold text-orange-400 text-sm">${safePolyName}</div>
          <div class="text-[11px] text-slate-300">Luas: <strong>${stats.sqNm} NM²</strong> (${stats.sqKm} km²)</div>
          <div class="text-[11px] text-slate-300">Keliling: <strong>${stats.perimeterNm} NM</strong> (${stats.perimeterKm} km)</div>
          <div class="text-[10px] text-slate-400 font-mono">${poly.points.length} Sudut Vertices</div>
          <div class="pt-2">
            <button type="button" onclick="window.SAR_GIS.applyPatternFromPolygon('${safePolyId}')" class="w-full py-1.5 px-2 rounded-lg bg-orange-600 hover:bg-orange-500 text-white font-bold text-[10px] flex items-center justify-center gap-1 shadow-xs cursor-pointer">
              <span>🚀 Buat Pola Lintasan SAR</span>
            </button>
          </div>
        </div>
      `);

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
    });

    renderSavedPolygonsList();
    populatePolygonSelectOptions();
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
        const isHidden = hiddenPolygonIds.has(poly.id);
        const stats = calculatePolygonSphericalArea(poly.points);
        const safeId = escapeHtml(poly.id);
        const safeName = escapeHtml(poly.name);
        const safeColor = escapeHtml(poly.strokeColor || '#ea580c');

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="cursor-pointer flex-1 min-w-0" onclick="window.SAR_GIS.focusPolygon('${safeId}')">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="w-3 h-3 rounded-full shrink-0" style="background-color: ${safeColor};"></span>
                <span class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${safeName}</span>
                ${getOperasiBadgeHtml(poly.id_operasi)}
              </div>
              <div class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                ${stats.sqNm} NM² (${stats.sqKm} km²) • Keliling: ${stats.perimeterNm} NM
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePolygonVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded" title="Sembunyikan / Tampilkan">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPolygon('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded" title="Edit">
                ✏️
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePolygon('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded" title="Hapus">
                🗑️
              </button>
            </div>
          </div>

          <button type="button" onclick="window.SAR_GIS.applyPatternFromPolygon('${poly.id}')" class="w-full py-1.5 px-2 rounded-xl bg-slate-100 hover:bg-orange-500/10 text-orange-600 dark:bg-slate-900 dark:hover:bg-orange-500/20 dark:text-orange-400 border border-slate-200 dark:border-slate-800 text-[10px] font-bold transition flex items-center justify-center gap-1.5 cursor-pointer">
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
      options += `<option value="active_draft">✏️ Poligon Aktif Sedang Digambar (${activePolygonPoints.length} Sudut)</option>`;
    }

    savedPolygons.forEach((p) => {
      const ptCount = (p.points || []).length;
      options += `<option value="${p.id}">📐 Area: ${p.name} (${ptCount} Sudut)</option>`;
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
        if (nameInput) nameInput.value = `Pola Lintasan SAR ${getPatternDisplayName(activePatternType)} #${savedPatterns.length + 1}`;
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

    // Activate default parallel_track button style
    const defaultPatBtn = document.querySelector('.pat-type-btn[data-ptype="parallel_track"]');
    if (defaultPatBtn) {
      defaultPatBtn.className = 'pat-type-btn p-2 rounded-xl border border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 text-left transition font-bold shadow-xs';
    }

    // Target polygon select listener
    const targetPolySel = document.getElementById('patternTargetPolygon');
    if (targetPolySel) {
      targetPolySel.addEventListener('change', () => {
        updatePatternAutoCalculation();
        const selectedId = targetPolySel.value;
        let poly = null;
        if (selectedId === 'active_draft' && activePolygonPoints && activePolygonPoints.length >= 3) {
          poly = { points: activePolygonPoints };
        } else {
          poly = savedPolygons.find((p) => String(p.id) === String(selectedId));
        }
        if (poly && poly.points && poly.points.length >= 3 && map) {
          const validPts = poly.points.map((p) => [parseFloat(p.lat), parseFloat(p.lng)]).filter((p) => !isNaN(p[0]) && !isNaN(p[1]));
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

    // Spacing unit select listener (NM vs KM)
    const spacingUnitSel = document.getElementById('patternSpacingUnit');
    const spacingInput = document.getElementById('patternTrackSpacing');
    if (spacingUnitSel && spacingInput) {
      spacingUnitSel.addEventListener('change', (e) => {
        const newUnit = e.target.value;
        const currentVal = parseFloat(spacingInput.value) || 1.0;
        if (newUnit === 'KM' && patternSpacingUnit === 'NM') {
          // Convert NM to KM
          spacingInput.value = (currentVal * 1.852).toFixed(2);
        } else if (newUnit === 'NM' && patternSpacingUnit === 'KM') {
          // Convert KM to NM
          spacingInput.value = (currentVal / 1.852).toFixed(2);
        }
        patternSpacingUnit = newUnit;
        updatePatternAutoCalculation();
        if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
          generateSearchPatternRoute();
        }
      });
    }

    if (spacingInput) {
      spacingInput.addEventListener('input', () => {
        updatePatternAutoCalculation();
        if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
          generateSearchPatternRoute();
        }
      });
    }

    const legsInput = document.getElementById('patternLegsCount');
    if (legsInput) {
      legsInput.addEventListener('input', () => {
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

  function updatePatternAutoCalculation() {
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
    const spacingNm = unit === 'KM' ? (rawSpacing / 1.852) : rawSpacing;
    const spacingKm = spacingNm * 1.852;

    let targetPolygon = null;
    if (targetPolyId === 'active_draft' && activePolygonPoints && activePolygonPoints.length >= 3) {
      targetPolygon = { id: 'active_draft', name: 'Poligon Aktif', points: activePolygonPoints };
    } else {
      targetPolygon = savedPolygons.find((p) => String(p.id) === String(targetPolyId));
    }

    let rawCoords = (targetPolygon && targetPolygon.points && targetPolygon.points.length >= 3)
      ? targetPolygon.points
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
    const activeSpan = unit === 'KM' ? spanDimKm : spanDimNm;

    // Recommended sweeps
    const availSpan = Math.max(0, activeSpan - rawSpacing);
    let recommendedSweeps = Math.max(2, Math.round(availSpan / rawSpacing) + 1);

    if (activePatternType === 'sector_search') {
      recommendedSweeps = 3;
    } else if (activePatternType === 'expanding_square') {
      const minDim = Math.min(heightNm, widthNm);
      recommendedSweeps = Math.max(2, Math.min(10, Math.round(minDim / (2 * spacingNm))));
    }

    const legsInput = document.getElementById('patternLegsCount');
    if (legsInput) {
      legsInput.value = recommendedSweeps;
    }

    const badgeEl = document.getElementById('patternAutoBadge');
    if (badgeEl) {
      badgeEl.textContent = `${recommendedSweeps} SWEEPS`;
    }

    const dimInfoEl = document.getElementById('patternDimInfo');
    if (dimInfoEl) {
      if (unit === 'KM') {
        dimInfoEl.textContent = `P: ${widthKm.toFixed(1)} km • L: ${heightKm.toFixed(1)} km`;
      } else {
        dimInfoEl.textContent = `P: ${widthNm.toFixed(1)} NM • L: ${heightNm.toFixed(1)} NM`;
      }
    }

    const marginInfoEl = document.getElementById('patternMarginInfo');
    if (marginInfoEl) {
      const marginVal = rawSpacing / 2;
      marginInfoEl.textContent = `${marginVal.toFixed(2)} ${unit} (S/2 Jarak Pandang)`;
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
    const spacingNm = unit === 'KM' ? (rawSpacing / 1.852) : rawSpacing;
    const legsInput = document.getElementById('patternLegsCount');
    const legs = parseInt(legsInput ? legsInput.value : '6', 10) || 6;

    let targetPolygon = null;
    if (targetPolyId === 'active_draft' && activePolygonPoints && activePolygonPoints.length >= 3) {
      targetPolygon = { id: 'active_draft', name: 'Poligon Aktif', points: activePolygonPoints };
    } else {
      targetPolygon = savedPolygons.find((p) => String(p.id) === String(targetPolyId));
    }

    let coords = [];

    if (targetPolygon && targetPolygon.points && targetPolygon.points.length >= 3) {
      coords = generateFittedPatternInsidePolygon(activePatternType, targetPolygon.points, spacingNm, legs);
    } else {
      coords = generateDatumPattern(activePatternType, null, spacingNm, legs);
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

  function generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount) {
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
    } else if (type === 'expanding_square') {
      // Expanding Square (SS) - Starts from polygon centroid and expands strictly bounded
      const startPt = { lat: centroid.lat, lng: centroid.lng };
      points.push({ lat: startPt.lat, lng: startPt.lng, label: 'Datum Centroid (S)' });

      let curPt = { lat: startPt.lat, lng: startPt.lng };
      let curX = 0, curY = 0, step = 1, dir = 0;
      const totalLegs = effectiveLegs * 2;
      const safeBufferDeg = Math.min(spanLat, spanLng) * 0.03;

      for (let leg = 1; leg <= totalLegs; leg++) {
        const legKm = step * spacingKm;
        if (dir === 0) curX += legKm;       // East
        else if (dir === 1) curY -= legKm;  // South
        else if (dir === 2) curX -= legKm;  // West
        else if (dir === 3) curY += legKm;  // North

        const rawTarget = {
          lat: startPt.lat + curY / 110.574,
          lng: startPt.lng + curX / lngKmPerDeg
        };

        const clipped = clipSegmentToPolygon(curPt, rawTarget, cleanPoints, safeBufferDeg);
        points.push({
          lat: clipped.lat,
          lng: clipped.lng,
          label: leg === totalLegs ? 'Exit (E)' : `WP-${leg}`
        });
        curPt = { lat: clipped.lat, lng: clipped.lng };

        if (leg % 2 === 0) step += 1;
        dir = (dir + 1) % 4;
      }
    } else {
      // 4. Pola Pencarian E-SAR (Explorer SAR - Darat / Gunung Hutan)
      // Pola sapuan sejajar serentak beberapa personel regu (skirmish / sweep line)
      const personnelCount = Math.max(2, effectiveLegs || 5);
      const spanLatKm = (maxLat - minLat) * 110.574;
      const spanLngKm = (maxLng - minLng) * lngKmPerDeg;

      if (spanLatKm >= spanLngKm) {
        // Area memanjang vertikal (Utara-Selatan): Seluruh personel berbaris sejajar Barat-Timur di baseline awal dan menyapu maju serentak ke arah Selatan
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

            // Seluruh personel bergerak serentak dari baseline awal (top) ke batas maju (bottom)
            points.push({ lat: top, lng: curLng, label: `P${pNum} Start (Baseline)` });
            points.push({ lat: bottom, lng: curLng, label: `P${pNum} Batas Maju` });
          }
        }
      } else {
        // Area memanjang horizontal (Barat-Timur): Seluruh personel berbaris sejajar Utara-Selatan di baseline awal dan menyapu maju serentak ke arah Timur
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

            // Seluruh personel bergerak serentak dari baseline awal (west) ke batas maju (east)
            points.push({ lat: curLat, lng: west, label: `P${pNum} Start (Baseline)` });
            points.push({ lat: curLat, lng: east, label: `P${pNum} Batas Maju` });
          }
        }
      }
    }

    // Final safety check: guarantee all waypoints are strictly inside polygon boundaries
    return points.map((p) => {
      if (!isPointInPolygon(p.lat, p.lng, cleanPoints)) {
        return {
          lat: p.lat * 0.88 + centroid.lat * 0.12,
          lng: p.lng * 0.88 + centroid.lng * 0.12,
          label: p.label
        };
      }
      return p;
    });
  }

  function generateDatumPattern(type, center, spacingNm, legsCount) {
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
    const spanDeg = Math.max(0.08, (spacingNm * Math.max(3, legsCount || 4) * 1.852) / 110.574);
    const fakePolygon = [
      { lat: c.lat + spanDeg / 2, lng: c.lng - spanDeg / 2 },
      { lat: c.lat + spanDeg / 2, lng: c.lng + spanDeg / 2 },
      { lat: c.lat - spanDeg / 2, lng: c.lng + spanDeg / 2 },
      { lat: c.lat - spanDeg / 2, lng: c.lng - spanDeg / 2 }
    ];
    return generateFittedPatternInsidePolygon(type, fakePolygon, spacingNm, legsCount);
  }

  function getPatternPolylineCoords(type, waypoints) {
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
      elDist.textContent = `${totalNm.toFixed(2)} NM (${(totalNm * 1.852).toFixed(2)} km)`;
    }
    const elWp = document.getElementById('resPatternWpCount');
    if (elWp) {
      elWp.textContent = `${activeGeneratedWaypoints.length} WP`;
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
        `<div class="text-left font-sans text-xs"><b>${escapeHtml(labelText)}</b><br><span class="text-[10px] text-amber-500 dark:text-amber-300">✋ Geser / drag untuk sesuaikan titik</span></div>`,
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
          `<div class="text-left font-sans text-xs"><b>${escapeHtml(labelText)}</b><br><span class="text-[10px] font-mono text-slate-400 dark:text-slate-300">${curLatLng.lat.toFixed(5)}, ${curLatLng.lng.toFixed(5)}</span><br><span class="text-[10px] text-amber-500 dark:text-amber-300">✋ Geser / drag untuk sesuaikan titik</span></div>`
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
    if (type === 'expanding_square') return 'Expanding Square (SS)';
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
        alert('Lintasan belum digenerate!');
      }
      return;
    }

    const patTypeName = getPatternDisplayName(activePatternType);
    const patName = name || `Pola Lintasan SAR ${patTypeName} #${savedPatterns.length + 1}`;

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
      trackSpacingNm: patternSpacingUnit === 'KM' ? (spacingVal / 1.852) : spacingVal,
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
    patternPolylinesMap.forEach((pl) => map.removeLayer(pl));
    patternPolylinesMap.clear();

    savedPatterns.forEach((pat) => {
      if (hiddenPatternIds.has(pat.id)) return;
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
        const isHidden = hiddenPatternIds.has(pat.id);
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
              <div class="text-[10px] font-mono text-sky-600 dark:text-sky-400 mt-0.5">
                ${pat.totalDistanceNm ? `${pat.totalDistanceNm.toFixed(1)} NM` : ''} • ${pat.waypoints ? pat.waypoints.length : 0} Waypoints • <span class="capitalize text-slate-500 dark:text-slate-400 font-sans">${getPatternDisplayName(pat.patternType)}</span>
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePatternVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded" title="${isHidden ? 'Tampilkan' : 'Sembunyikan'}">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPattern('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded cursor-pointer transition" title="Edit Pola Ini">
                ✏️
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePattern('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded cursor-pointer transition" title="Hapus">
                🗑️
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

    // Immediately remove saved static polyline from map to prevent 2 overlapping patterns!
    if (patternPolylinesMap.has(pat.id)) {
      map.removeLayer(patternPolylinesMap.get(pat.id));
      patternPolylinesMap.delete(pat.id);
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
    if (formTitle) formTitle.textContent = '✏️ Edit Parameter Pola Lintasan SAR';
    const btnSave = document.getElementById('btnSavePatternConfirm');
    if (btnSave) btnSave.innerHTML = '<span>💾 Perbarui Pola SAR</span>';

    if (Array.isArray(pat.waypoints) && pat.waypoints.length >= 2) {
      activeGeneratedWaypoints = pat.waypoints.map(w => ({ lat: parseFloat(w.lat), lng: parseFloat(w.lng), label: w.label || '' }));
      recalculateActivePatternDistance();
      renderActivePatternOnMap();

      const elDist = document.getElementById('resPatternDistNm');
      if (elDist) elDist.textContent = `${activeGeneratedDistanceNm.toFixed(2)} NM (${(activeGeneratedDistanceNm * 1.852).toFixed(2)} km)`;
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
    if (routeNameInput && (!routeNameInput.value.trim() || routeNameInput.value.startsWith('Jarak Lurus') || routeNameInput.value.startsWith('Jalur Manual') || routeNameInput.value.startsWith('Rute Darat') || routeNameInput.value.startsWith('Rute Laut'))) {
      const modeLabel = measureModeType === 'straight' ? 'Jarak Lurus' : 'Jalur Manual';
      routeNameInput.value = `${modeLabel} #${savedRoutes.length + 1}`;
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

    if (!points || !Array.isArray(points) || points.length < 2) {
      if (elNm) elNm.textContent = '0.00 NM';
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
      if (elKm) {
        elKm.textContent = `${totalKm.toFixed(2)} km`;
        elKm.className = 'text-base font-black text-emerald-600 dark:text-emerald-400 font-mono';
      }
      if (elNm) {
        elNm.textContent = `${totalNm.toFixed(2)} NM`;
        elNm.className = 'text-[10px] text-slate-500 dark:text-slate-400 block font-mono';
      }
    } else {
      // Perhitungan berbasis Knot (Kts)
      const speedKts = Math.max(0.1, enteredSpeed);
      vesselSpeedKnots = speedKts;
      etaMins = (totalNm / speedKts) * 60;
      if (elNm) {
        elNm.textContent = `${totalNm.toFixed(2)} NM`;
        elNm.className = 'text-base font-black text-emerald-600 dark:text-emerald-400 font-mono';
      }
      if (elKm) {
        elKm.textContent = `${totalKm.toFixed(2)} km`;
        elKm.className = 'text-[10px] text-slate-500 dark:text-slate-400 block font-mono';
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

    // Satuan Kecepatan & Navigasi: Otomatis konversi nilai dan hitung ulang seketika!
    const speedUnitSel = document.getElementById('measureSpeedUnit');
    if (speedUnitSel) {
      let prevUnit = speedUnitSel.value || 'knot';
      speedUnitSel.addEventListener('change', (e) => {
        const newUnit = e.target.value;
        const speedInput = document.getElementById('measureVesselSpeed');
        if (speedInput) {
          const curVal = parseFloat(speedInput.value) || 25;
          if (newUnit === 'kmh' && prevUnit === 'knot') {
            // Knot ➔ Km/Jam: curVal * 1.852
            speedInput.value = (curVal * 1.852).toFixed(1);
          } else if (newUnit === 'knot' && prevUnit === 'kmh') {
            // Km/Jam ➔ Knot: curVal / 1.852
            speedInput.value = (curVal / 1.852).toFixed(1);
          }
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

    // Initialize default mode
    setMeasureMode(measureModeType);
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
    if (nameInput && !nameInput.value.trim()) {
      nameInput.value = `Jalur Manual Ops #${savedRoutes.length + 1}`;
    }

    updateMeasureVisual();

    const badge = document.getElementById('routeRealtimeStatusBadge');
    if (measurePoints.length >= 2) {
      if (badge) {
        if (currentActiveRouteId) {
          badge.textContent = '✏️ Mode Edit (Siap Disimpan)';
          badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
        } else {
          badge.textContent = '✏️ Rute Belum Disimpan';
          badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
        }
      }
    } else {
      if (badge) {
        badge.textContent = 'Menunggu titik ke-2...';
        badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
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
          <div class="font-mono text-[10px] text-slate-500 bg-slate-100 p-1 rounded border border-slate-200">DMS: ${dmsP}</div>
          <div class="text-[9px] text-slate-400 italic">Geser titik ini pada peta untuk menyesuaikan posisi rute.</div>
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
            badge.textContent = currentActiveRouteId ? '✏️ Mode Edit (Posisi Diubah)' : '✏️ Rute Belum Disimpan';
            badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
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
    if (nameInput) nameInput.value = '';

    const saveBtn = document.getElementById('btnSaveMeasureRoute');
    if (saveBtn) {
      saveBtn.innerHTML = '<span>💾 Simpan Rute Ini</span>';
    }

    const realtimeBadge = document.getElementById('routeRealtimeStatusBadge');
    if (realtimeBadge) {
      realtimeBadge.textContent = '⚡ Siap Rancang';
      realtimeBadge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
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
    const isManualClick = !!options.isManualClick;

    if (!measurePoints || measurePoints.length < 2) {
      const badge = document.getElementById('routeRealtimeStatusBadge');
      if (badge) {
        badge.textContent = measurePoints.length === 1 ? 'Menunggu titik ke-2' : '⚡ Siap Rancang';
        badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 font-bold transition';
      }
      showRouteSaveFeedback('Minimal tentukan 2 titik rute!', 'warning');
      return;
    }

    // 1. Resolve Route Name directly from Form Input (No localhost prompt!)
    const nameInput = document.getElementById('measureRouteNameInput');
    let routeName = nameInput ? nameInput.value.trim() : '';
    if (!routeName) {
      const modeLabel = measureModeType === 'straight' ? 'Jarak Lurus' : 'Jalur Manual';
      routeName = `${modeLabel} #${savedRoutes.length + 1}`;
      if (nameInput) nameInput.value = routeName;
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
      points: measurePoints,
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
      badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition animate-pulse';
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

      const timeStr = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      if (badge) {
        badge.textContent = `⚡ Tersimpan (${timeStr})`;
        badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-bold transition';
      }

      const counter = document.getElementById('manualPointsCounter');
      if (counter) {
        counter.textContent = `${measurePoints.length} Titik (Tersimpan)`;
      }

      const actionText = isEditing ? 'diperbarui' : 'disimpan';
      showRouteSaveFeedback(`✅ Rute "${escapeHtml(routeName)}" berhasil ${actionText} (${metrics.totalNm.toFixed(2)} NM, ETA ${formatDurationIndonesian(metrics.etaMins)}).`, 'success');
      currentActiveRouteId = null;
    } catch (err) {
      console.warn('Save route offline or failed:', err);
      const existingIdx = savedRoutes.findIndex(r => String(r.id) === String(payload.id));
      if (existingIdx >= 0) {
        savedRoutes[existingIdx] = payload;
      } else {
        savedRoutes.unshift(payload);
      }
      renderSavedRoutes();

      if (badge) {
        badge.textContent = '⚡ Tersimpan Lokal';
        badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/30 font-bold transition';
      }

      showRouteSaveFeedback(`✅ Rute "${escapeHtml(routeName)}" disimpan secara lokal (${metrics.totalNm.toFixed(2)} NM).`, 'info');
      currentActiveRouteId = null;
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
      badge.textContent = `✏️ Mengedit: ${rt.name || 'Rute'}`;
      badge.className = 'text-[9px] font-mono px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold transition';
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
    savedRoutePolylinesMap.forEach((pl) => map.removeLayer(pl));
    savedRoutePolylinesMap.clear();

    savedRoutes.forEach((route) => {
      if (hiddenRouteIds.has(route.id)) return;
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
        const isHidden = hiddenRouteIds.has(rt.id);
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
              <div class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                ${distNm.toFixed(2)} NM (${(distNm * 1.852).toFixed(2)} km) • @ ${escapeHtml(speedDisplay)}
              </div>
              <div class="text-[9px] font-mono text-slate-400 mt-1 flex flex-col gap-0.5">
                <span class="truncate"><b class="text-emerald-400">A:</b> ${escapeHtml(startDms)}</span>
                <span class="truncate"><b class="text-red-400">B:</b> ${escapeHtml(endDms)}</span>
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.editRoute('${safeId}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded transition" title="Edit Rute Ini">
                ✏️
              </button>
              <button type="button" onclick="window.SAR_GIS.toggleRouteVisibility('${safeId}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded" title="Tampilkan/Sembunyikan">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.deleteRoute('${safeId}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded" title="Hapus Rute">
                🗑️
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
  // 11. PRINT MAP MODAL IMPLEMENTATION
  // ==========================================

  function initPrintModalUI() {
    const btnOpen = document.getElementById('btnOpenPrintModal');
    const btnClose = document.getElementById('btnClosePrintModal');
    const btnCancel = document.getElementById('btnCancelPrintModal');
    const btnPrint = document.getElementById('btnExecutePrintIframe');
    const modal = document.getElementById('printMapModal');

    // Realtime metadata input synchronization to printable sheet preview
    const inputDocNum = document.getElementById('printDocNumber');
    const inputTitle = document.getElementById('printOpsTitle');
    const inputSmc = document.getElementById('printSmcName');
    const inputScale = document.getElementById('printScale');

    const prevDocNum = document.getElementById('printDocNumberPreview');
    const prevTitle = document.getElementById('printPreviewTitle');
    const prevSmc = document.getElementById('printPreviewSmc');
    const prevScale = document.getElementById('printPreviewScale');
    const sigSmc = document.getElementById('printSignatureSmc');

    if (inputDocNum && prevDocNum) {
      inputDocNum.addEventListener('input', (e) => {
        prevDocNum.textContent = e.target.value.trim() || '-';
      });
    }
    if (inputTitle && prevTitle) {
      inputTitle.addEventListener('input', (e) => {
        prevTitle.textContent = e.target.value.trim() || '-';
      });
    }
    if (inputSmc) {
      inputSmc.addEventListener('input', (e) => {
        const val = e.target.value.trim() || '-';
        if (prevSmc) prevSmc.textContent = val;
        if (sigSmc) sigSmc.textContent = val;
      });
    }
    if (inputScale && prevScale) {
      inputScale.addEventListener('input', (e) => {
        prevScale.textContent = e.target.value.trim() || '-';
      });
    }

    if (btnOpen) {
      btnOpen.addEventListener('click', openPrintMapModal);
    }

    [btnClose, btnCancel].forEach((b) => {
      if (b) {
        b.addEventListener('click', () => {
          modal.classList.add('hidden');
          modal.classList.remove('flex');
        });
      }
    });

    if (btnPrint) {
      btnPrint.addEventListener('click', executePrintIframe);
    }
  }

  let printPreviewMap = null;

  function openPrintMapModal() {
    const modal = document.getElementById('printMapModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    // 1. Sync header metadata inputs to preview
    const inputDocNum = document.getElementById('printDocNumber');
    const inputTitle = document.getElementById('printOpsTitle');
    const inputSmc = document.getElementById('printSmcName');
    const inputScale = document.getElementById('printScale');

    const prevDocNum = document.getElementById('printDocNumberPreview');
    const prevTitle = document.getElementById('printPreviewTitle');
    const prevSmc = document.getElementById('printPreviewSmc');
    const prevScale = document.getElementById('printPreviewScale');
    const sigSmc = document.getElementById('printSignatureSmc');

    if (inputDocNum && prevDocNum) prevDocNum.textContent = inputDocNum.value || '-';
    if (inputTitle && prevTitle) prevTitle.textContent = inputTitle.value || '-';
    if (inputSmc) {
      if (prevSmc) prevSmc.textContent = inputSmc.value || '-';
      if (sigSmc) sigSmc.textContent = inputSmc.value || '-';
    }
    if (inputScale && prevScale) prevScale.textContent = inputScale.value || '-';

    // 2. Populate Points Table
    const tbodyPts = document.getElementById('printTbodyPoints');
    if (tbodyPts) {
      tbodyPts.innerHTML = savedPoints
        .map(
          (p) => `
        <tr>
          <td class="py-1 font-bold text-slate-800">${escapeHtml(p.label || '(Tanpa Nama)')}</td>
          <td class="py-1 text-slate-600">${escapeHtml(p.category || 'Tanpa Kategori')}</td>
          <td class="py-1 text-slate-700">${decimalToDms(p.lat, p.lng)}</td>
        </tr>
      `
        )
        .join('');
    }

    // 3. Populate Polygons Table
    const tbodyPolys = document.getElementById('printTbodyPolygons');
    if (tbodyPolys) {
      tbodyPolys.innerHTML = savedPolygons
        .map((poly) => {
          const stats = calculatePolygonSphericalArea(poly.points);
          return `
          <tr>
            <td class="py-1 font-bold text-slate-800">${escapeHtml(poly.name)}</td>
            <td class="py-1 text-slate-700">${stats.sqNm} NM²</td>
            <td class="py-1 text-slate-700">${stats.perimeterNm} NM</td>
          </tr>
        `;
        })
        .join('');
    }

    // 4. Populate Routes & Measurements Table
    const tbodyRoutes = document.getElementById('printTbodyRoutes');
    if (tbodyRoutes) {
      const allPrintRoutes = [...savedRoutes];
      if (measurePoints && measurePoints.length >= 2) {
        const mMetrics = recalcRouteMetrics(measurePoints);
        allPrintRoutes.unshift({
          name: document.getElementById('measureRouteNameInput')?.value || 'Jarak Lurus Aktif (Pengukuran)',
          distanceNm: mMetrics.totalNm,
          bearing: mMetrics.lastBearing,
          heading: mMetrics.lastHeading,
          vesselSpeedKnots: vesselSpeedKnots,
          etaText: formatDurationIndonesian(mMetrics.etaMins)
        });
      }

      if (allPrintRoutes.length === 0) {
        tbodyRoutes.innerHTML = `<tr><td colspan="4" class="py-1 text-center text-slate-400 italic">Tidak ada rute / jarak aktif.</td></tr>`;
      } else {
        tbodyRoutes.innerHTML = allPrintRoutes
          .map((rt) => {
            const dist = typeof rt.distanceNm === 'number' ? rt.distanceNm.toFixed(2) : (parseFloat(rt.distanceNm) || 0).toFixed(2);
            const brg = rt.bearing !== undefined ? `${rt.bearing}° (${rt.heading || 'Nav'})` : '-';
            const spd = rt.vesselSpeedKnots ? `${rt.vesselSpeedKnots} Kts` : '-';
            return `
              <tr>
                <td class="py-1 font-bold text-slate-800">${escapeHtml(rt.name || 'Rute Navigasi')}</td>
                <td class="py-1 text-slate-700">${dist} NM</td>
                <td class="py-1 text-slate-700">${brg} / ${spd}</td>
                <td class="py-1 text-slate-700 font-bold text-emerald-700">${rt.etaText || '-'}</td>
              </tr>
            `;
          })
          .join('');
      }
    }

    // 5. Populate Search Patterns Table
    const tbodyPatterns = document.getElementById('printTbodyPatterns');
    if (tbodyPatterns) {
      const allPrintPatterns = [...savedPatterns];
      if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2 && !savedPatterns.some(p => p.id === editingPatternId)) {
        allPrintPatterns.unshift({
          name: document.getElementById('patternFormName')?.value || 'Pola SAR Aktif (Rancangan)',
          patternType: activePatternType,
          trackSpacingNm: patternSpacingUnit === 'KM' ? ((parseFloat(document.getElementById('patternTrackSpacing')?.value) || 1) / 1.852) : (parseFloat(document.getElementById('patternTrackSpacing')?.value) || 1),
          totalDistanceNm: activeGeneratedDistanceNm
        });
      }

      if (allPrintPatterns.length === 0) {
        tbodyPatterns.innerHTML = `<tr><td colspan="4" class="py-1 text-center text-slate-400 italic">Tidak ada pola lintasan SAR tersimpan.</td></tr>`;
      } else {
        tbodyPatterns.innerHTML = allPrintPatterns
          .map((pat) => {
            const dist = typeof pat.totalDistanceNm === 'number' ? pat.totalDistanceNm.toFixed(2) : (parseFloat(pat.totalDistanceNm) || 0).toFixed(2);
            const spacing = typeof pat.trackSpacingNm === 'number' ? pat.trackSpacingNm.toFixed(2) : (parseFloat(pat.trackSpacingNm) || 0).toFixed(2);
            return `
              <tr>
                <td class="py-1 font-bold text-slate-800">${escapeHtml(pat.name || 'Pola SAR')}</td>
                <td class="py-1 text-slate-700">${getPatternDisplayName(pat.patternType)}</td>
                <td class="py-1 text-slate-700">${spacing} NM</td>
                <td class="py-1 text-slate-700 font-bold text-sky-700">${dist} NM</td>
              </tr>
            `;
          })
          .join('');
      }
    }

    // 6. Render Mini-Map Snapshot Target inside modal
    setTimeout(() => {
      const container = document.getElementById('printMapSnapshotTarget');
      if (!container) return;

      if (printPreviewMap) {
        printPreviewMap.remove();
        printPreviewMap = null;
      }

      printPreviewMap = L.map('printMapSnapshotTarget', {
        center: map.getCenter(),
        zoom: map.getZoom(),
        zoomControl: false,
        attributionControl: false
      });

      // Synchronize tile layer with active basemap
      if (activeBasemap === 'hybrid' || activeBasemap === 'google_hybrid') {
        L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
          maxNativeZoom: 20,
          maxZoom: 22,
          subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
        }).addTo(printPreviewMap);
      } else if (activeBasemap === 'satellite') {
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
          maxNativeZoom: 18,
          maxZoom: 22
        }).addTo(printPreviewMap);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png', {
          subdomains: 'abcd',
          maxNativeZoom: 19,
          maxZoom: 22
        }).addTo(printPreviewMap);
      } else if (activeBasemap === 'roadmap') {
        L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
          maxNativeZoom: 20,
          maxZoom: 22,
          subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
        }).addTo(printPreviewMap);
      } else if (activeBasemap === 'terrain') {
        L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
          maxNativeZoom: 17,
          maxZoom: 22
        }).addTo(printPreviewMap);
      } else {
        L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
          maxNativeZoom: 20,
          maxZoom: 22,
          subdomains: ['mt0', 'mt1', 'mt2', 'mt3']
        }).addTo(printPreviewMap);
      }

      // Clone Polygons
      savedPolygons.forEach((poly) => {
        if (hiddenPolygonIds.has(poly.id)) return;
        if (poly.points && poly.points.length >= 3) {
          L.polygon(poly.points.map((p) => [p.lat, p.lng]), {
            color: poly.strokeColor || '#ea580c',
            weight: 2,
            fillColor: poly.fillColor || '#ea580c',
            fillOpacity: poly.fillOpacity || 0.25
          }).addTo(printPreviewMap);
        }
      });

      // Clone Points
      savedPoints.forEach((pt) => {
        if (hiddenPointIds.has(pt.id)) return;
        L.marker([pt.lat, pt.lng], {
          icon: L.divIcon({
            html: createMarkerHtml(pt, false),
            className: 'sar-print-pin',
            iconSize: [38, 46],
            iconAnchor: [19, 42],
            popupAnchor: [0, -42]
          })
        }).addTo(printPreviewMap);
      });

      // Clone Saved Search Patterns
      savedPatterns.forEach((pat) => {
        if (hiddenPatternIds.has(pat.id)) return;
        if (!pat.waypoints || pat.waypoints.length < 2) return;
        L.polyline(pat.waypoints.map((p) => [p.lat, p.lng]), {
          color: pat.color || '#0284c7',
          weight: 3,
          dashArray: '6, 6'
        }).addTo(printPreviewMap);
      });

      // Clone Active Generated Pattern (if currently on map)
      if (activeGeneratedWaypoints && activeGeneratedWaypoints.length >= 2) {
        L.polyline(activeGeneratedWaypoints.map((p) => [p.lat, p.lng]), {
          color: '#0284c7',
          weight: 3,
          dashArray: '6, 6'
        }).addTo(printPreviewMap);
      }

      // Clone Saved Routes
      savedRoutes.forEach((route) => {
        if (hiddenRouteIds.has(route.id)) return;
        if (!route.points || route.points.length < 2) return;
        L.polyline(route.points.map((p) => [p.lat, p.lng]), {
          color: route.strokeColor || '#10b981',
          weight: 3.5,
          opacity: 0.9
        }).addTo(printPreviewMap);

        route.points.forEach((p, idx) => {
          const isStart = idx === 0;
          const isEnd = idx === route.points.length - 1;
          if (isStart || isEnd) {
            const bgColor = isStart ? '#16a34a' : '#dc2626';
            const text = isStart ? 'A' : (route.points.length === 2 ? 'B' : `${idx + 1}`);
            L.marker([p.lat, p.lng], {
              icon: L.divIcon({
                className: 'custom-measure-waypoint',
                html: `<div style="width:20px;height:20px;border-radius:50%;background:${bgColor};border:2px solid #fff;color:#fff;font-weight:900;font-size:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,0.5);">${text}</div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10]
              })
            }).addTo(printPreviewMap);
          }
        });
      });

      // Clone Active Straight-line / Measurement Ruler
      if (measurePoints && measurePoints.length >= 2) {
        L.polyline(measurePoints.map((p) => [p.lat, p.lng]), {
          color: '#10b981',
          weight: 3.5
        }).addTo(printPreviewMap);

        measurePoints.forEach((p, idx) => {
          const isStart = idx === 0;
          const isEnd = idx === measurePoints.length - 1;
          const bgColor = isStart ? '#16a34a' : (isEnd ? '#dc2626' : '#0ea5e9');
          const text = isStart ? 'A' : (measurePoints.length === 2 ? 'B' : `${idx + 1}`);
          L.marker([p.lat, p.lng], {
            icon: L.divIcon({
              className: 'custom-measure-waypoint',
              html: `<div style="width:20px;height:20px;border-radius:50%;background:${bgColor};border:2px solid #fff;color:#fff;font-weight:900;font-size:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 4px rgba(0,0,0,0.5);">${text}</div>`,
              iconSize: [20, 20],
              iconAnchor: [10, 10]
            })
          }).addTo(printPreviewMap);
        });

        // Add distance tooltip at midpoint
        const metrics = recalcRouteMetrics(measurePoints);
        if (metrics && metrics.totalNm > 0) {
          const midPoint = [
            (measurePoints[0].lat + measurePoints[measurePoints.length - 1].lat) / 2,
            (measurePoints[0].lng + measurePoints[measurePoints.length - 1].lng) / 2
          ];
          L.tooltip({ permanent: true, direction: 'center', className: 'distance-label-tooltip' })
            .setContent(`📏 ${metrics.totalNm.toFixed(2)} NM (${metrics.lastBearing}° ${metrics.lastHeading})`)
            .setLatLng(midPoint)
            .addTo(printPreviewMap);
        }
      }

      // Ensure leaflet container dimensions adapt correctly
      setTimeout(() => {
        if (printPreviewMap) {
          printPreviewMap.invalidateSize();
        }
      }, 250);
    }, 200);
  }

  function executePrintIframe() {
    const printArea = document.getElementById('printableDocumentSheet');
    if (!printArea) return;

    const frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    document.body.appendChild(frame);

    const doc = frame.contentWindow?.document;
    if (!doc) {
      window.print();
      return;
    }

    doc.open();
    doc.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>LEMBAR OPERASIONAL SAR KENDARI</title>
          <link rel="stylesheet" href="/assets/css/leaflet.min.css"/>
          <style>
            @page { size: A4 portrait; margin: 8mm; }
            body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #0f172a; background: #fff; margin: 0; padding: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 4px; }
            th, td { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 9.5px; }
            th { background: #f1f5f9; text-align: left; font-weight: bold; color: #334155; }
            .grid { display: grid; }
            .grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
            .grid-cols-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .gap-2 { gap: 8px; }
            .gap-3 { gap: 12px; }
            .border { border: 1px solid #cbd5e1; }
            .border-b { border-bottom: 1px solid #cbd5e1; }
            .border-b-2 { border-bottom: 2px solid #0f172a; }
            .border-2 { border: 2px solid #0f172a; }
            .border-slate-800 { border-color: #1e293b; }
            .rounded { border-radius: 4px; }
            .rounded-lg { border-radius: 8px; }
            .p-2 { padding: 8px; }
            .p-6 { padding: 18px; }
            .pb-0\\.5 { padding-bottom: 2px; }
            .pb-3 { padding-bottom: 12px; }
            .pt-6 { padding-top: 18px; }
            .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
            .font-bold { font-weight: bold; }
            .font-black { font-weight: 900; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .flex { display: flex; }
            .items-center { align-items: center; }
            .justify-between { justify-content: space-between; }
            .h-14 { height: 56px; }
            .h-16 { height: 60px; }
            .w-auto { width: auto; }
            .w-full { width: 100%; }
            .uppercase { text-transform: uppercase; }
            .underline { text-decoration: underline; }
            .text-orange-600 { color: #ea580c; }
            .text-emerald-700 { color: #047857; }
            .text-sky-700 { color: #0369a1; }
            .bg-slate-50 { background-color: #f8fafc; }
            .bg-slate-900 { background-color: #0f172a; }
            .text-white { color: #ffffff; }
            .text-slate-400 { color: #94a3b8; }
            .text-slate-500 { color: #64748b; }
            .text-slate-600 { color: #475569; }
            .text-slate-700 { color: #334155; }
            .text-slate-800 { color: #1e293b; }
            .text-slate-900 { color: #0f172a; }
            .text-slate-950 { color: #020617; }
            .text-xs { font-size: 11px; }
            .text-sm { font-size: 13px; }
            .text-\\[9px\\] { font-size: 9px; }
            .text-\\[10px\\] { font-size: 10px; }
            .text-\\[11px\\] { font-size: 11px; }
            .space-y-4 > * + * { margin-top: 14px; }
            .distance-label-tooltip { background: rgba(255, 255, 255, 0.95); border: 1.5px solid #10b981; color: #0f172a; font-weight: 800; font-size: 9px; padding: 2px 6px; border-radius: 4px; }
            .sar-print-pin svg, .custom-measure-waypoint div { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            * { print-color-adjust: exact; -webkit-print-color-adjust: exact; box-sizing: border-box; }
          </style>
        </head>
        <body>
          ${printArea.innerHTML}
        </body>
      </html>
    `);
    doc.close();

    setTimeout(() => {
      frame.contentWindow?.focus();
      frame.contentWindow?.print();
      setTimeout(() => document.body.removeChild(frame), 1500);
    }, 600);
  }

  // ==========================================
  // 12. TAB SWITCHER & GLOBAL CONTROLS
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
      const pt = savedPoints.find((p) => p.id === id);
      if (pt && map) {
        map.flyTo([pt.lat, pt.lng], 14, { duration: 1 });
        const marker = pointMarkersMap.get(id);
        if (marker) marker.openPopup();
      }
    },

    togglePointVisibility: (id) => {
      if (hiddenPointIds.has(id)) {
        hiddenPointIds.delete(id);
      } else {
        hiddenPointIds.add(id);
      }
      renderSavedPoints();
    },

    editPoint: (id) => {
      const pt = savedPoints.find((p) => p.id === id);
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
      const poly = savedPolygons.find((p) => p.id === id);
      if (poly && poly.points.length >= 3 && map) {
        const bounds = L.latLngBounds(poly.points.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [50, 50] });
      }
    },

    togglePolygonVisibility: (id) => {
      if (hiddenPolygonIds.has(id)) {
        hiddenPolygonIds.delete(id);
      } else {
        hiddenPolygonIds.add(id);
      }
      renderSavedPolygons();
    },

    editPolygon: (id) => {
      const poly = savedPolygons.find((p) => p.id === id);
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
      switchActiveTab('tab-pattern');
      const card = document.getElementById('patternCreationCard');
      if (card) card.classList.remove('hidden');
      const btnNew = document.getElementById('btnStartNewPattern');
      if (btnNew) btnNew.classList.add('hidden');
      const sel = document.getElementById('patternTargetPolygon');
      if (sel) {
        sel.value = id;
        updatePatternAutoCalculation();
      }
      const poly = savedPolygons.find((p) => String(p.id) === String(id));
      if (poly && poly.points && poly.points.length >= 3 && map) {
        const validPts = poly.points.map((p) => [parseFloat(p.lat), parseFloat(p.lng)]).filter((p) => !isNaN(p[0]) && !isNaN(p[1]));
        if (validPts.length >= 3) {
          const bounds = L.latLngBounds(validPts);
          map.fitBounds(bounds, { padding: [60, 60], maxZoom: 15 });
        }
      }
      generateSearchPatternRoute();
    },

    focusPattern: (id) => {
      const pat = savedPatterns.find((p) => p.id === id);
      if (pat && pat.waypoints.length >= 2 && map) {
        const bounds = L.latLngBounds(pat.waypoints.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [50, 50] });
      }
    },

    editPattern: (id) => {
      editPattern(id);
    },

    togglePatternVisibility: (id) => {
      if (hiddenPatternIds.has(id)) {
        hiddenPatternIds.delete(id);
      } else {
        hiddenPatternIds.add(id);
      }
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
      if (hiddenRouteIds.has(id)) {
        hiddenRouteIds.delete(id);
      } else {
        hiddenRouteIds.add(id);
      }
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
    clearRoute: () => clearMeasureVisual()
  };

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
          } else {
            filterMode = 'basemap';
            currentOperasiId = null;
            currentOperasi = null;
            includeBaseInActive = false;
          }
        }

        // Sinkronisasi judul lembar operasional pada modal cetak jika tersedia
        const inputTitle = document.getElementById('printOpsTitle');
        if (inputTitle) {
          if (currentOperasi) {
            inputTitle.value = currentOperasi.nama_operasi.toUpperCase();
          } else if (filterMode === 'basemap' || filterMode === 'dasar') {
            inputTitle.value = 'BASE MAP TAKTIS WILAYAH KERJA KPP KENDARI';
          } else {
            inputTitle.value = 'PLOTING TAKTIS OPERASI SAR WILAYAH KERJA KPP KENDARI';
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
  // 14. BOOTSTRAP APPLICATION
  // ==========================================

  function bootstrapApp() {
    initLeafletMap();
    initLocationSearchBar();
    initPointCreatorUI();
    initPolygonManagerUI();
    initSearchPatternUI();
    initMeasureDistanceUI();
    initPrintModalUI();
    initTabSwitcher();
    initSidebarToggle();
    initBasemapSwitcher();
    initExportDropdown();
    initFullscreenToggle();
    initOperasiSelector();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapApp);
  } else {
    bootstrapApp();
  }
})();
