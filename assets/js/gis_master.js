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

  // 1. STATE MANAGEMENT
  let savedPoints = Array.isArray(initialData.points) ? [...initialData.points] : [];
  let savedPolygons = Array.isArray(initialData.polygons) ? [...initialData.polygons] : [];
  let savedPatterns = Array.isArray(initialData.patterns) ? [...initialData.patterns] : [];
  let savedRoutes = Array.isArray(initialData.routes) ? [...initialData.routes] : [];

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

  // Search Pattern state
  let activePatternType = 'parallel_track';
  let activeGeneratedWaypoints = [];
  let activeGeneratedDistanceNm = 0;

  // Measure Distance state
  let isMeasureModeActive = false;
  let measureModeType = 'straight'; // 'straight' or 'path'
  let measurePoints = [];
  let vesselSpeedKnots = 25;
  let speedUnit = 'knot';

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

  // Verified Sultra Landmarks & Maritime Presets
  const VERIFIED_MARITIME_PRESETS = [
    {
      name: 'Kantor Pencarian & Pertolongan Kendari (Kansar)',
      address: 'Gedung Kantor Basarnas Kendari, Jl. Pierre Tendean, Baruga, Kota Kendari',
      lat: -4.037664,
      lng: 122.486662,
      badge: 'UNIT BASARNAS',
      type: 'Kantor SAR Utama (Pusat Kendari)',
      dms: '04° 02\' 16" S - 122° 29\' 12" E'
    },
    {
      name: 'Kansar Kendari / Basarnas Kendari',
      address: 'Jl. Pierre Tendean, Baruga, Kota Kendari',
      lat: -4.037664,
      lng: 122.486662,
      badge: 'UNIT BASARNAS',
      type: 'Kantor SAR Utama',
      dms: '04° 02\' 16" S - 122° 29\' 12" E'
    },
    {
      name: 'Dermaga SAR Kendari (Pangkalan KN SAR Pacitan / RB 210)',
      address: 'Kawasan Pelabuhan Bungkutoko, Kec. Abeli, Kota Kendari',
      lat: -3.9725,
      lng: 122.586944,
      badge: 'PANGKALAN ALUT',
      type: 'Pangkalan Kapal SAR',
      dms: '03° 58\' 21" S - 122° 35\' 13" E'
    },
    {
      name: 'Pos SAR Kolaka',
      address: 'Kompleks Pelabuhan Feri Kolaka, Kel. Sea / Kolakaasi, Kab. Kolaka',
      lat: -4.057582,
      lng: 121.589213,
      badge: 'UNIT BASARNAS',
      type: 'Pos SAR Resmi',
      dms: '04° 03\' 27" S - 121° 35\' 21" E'
    },
    {
      name: 'Pelabuhan Ferry Kolaka / POS SAR KOLAKA',
      address: 'Kawasan Dermaga Penyeberangan Ferry Kolaka - Bajoe',
      lat: -4.057582,
      lng: 121.589213,
      badge: 'UNIT BASARNAS',
      type: 'Pos SAR Resmi & Dermaga',
      dms: '04° 03\' 27" S - 121° 35\' 21" E'
    },
    {
      name: 'Pos SAR Baubau',
      address: 'Kawasan Pelabuhan Murhum / Jembatan Batu, Kota Baubau',
      lat: -5.453677,
      lng: 122.610329,
      badge: 'UNIT BASARNAS',
      type: 'Pos SAR Resmi',
      dms: '05° 27\' 13" S - 122° 36\' 37" E'
    },
    {
      name: 'Pos SAR Wakatobi',
      address: 'Kawasan Pelabuhan Mandati / Pangulubelo, Wangi-Wangi Selatan, Wakatobi',
      lat: -5.326276,
      lng: 123.538899,
      badge: 'UNIT BASARNAS',
      type: 'Pos SAR Resmi',
      dms: '05° 19\' 35" S - 123° 32\' 20" E'
    },
    {
      name: 'Pos SAR Konawe Utara',
      address: 'Jl. Poros Trans Sulawesi, Wanggudu, Kab. Konawe Utara',
      lat: -3.513889,
      lng: 122.180556,
      badge: 'UNIT BASARNAS',
      type: 'Pos SAR Resmi',
      dms: '03° 30\' 50" S - 122° 10\' 50" E'
    },
    {
      name: 'Unit Siaga SAR Muna',
      address: 'Kawasan Pelabuhan Nusantara Raha, Kota Raha, Kab. Muna',
      lat: -4.836111,
      lng: 122.719444,
      badge: 'UNIT BASARNAS',
      type: 'Unit Siaga SAR',
      dms: '04° 50\' 10" S - 122° 43\' 10" E'
    },
    {
      name: 'Unit Siaga SAR Konawe',
      address: 'Jl. Poros Kendari - Kolaka, Kota Unaaha, Kab. Konawe',
      lat: -3.905556,
      lng: 122.047222,
      badge: 'UNIT BASARNAS',
      type: 'Unit Siaga SAR',
      dms: '03° 54\' 20" S - 122° 02\' 50" E'
    },
    {
      name: 'Unit Siaga SAR Kolaka Utara',
      address: 'Kawasan Pelabuhan Penyeberangan Tobaku, Lasusua, Kab. Kolaka Utara',
      lat: -3.425,
      lng: 120.897,
      badge: 'UNIT BASARNAS',
      type: 'Unit Siaga SAR',
      dms: '03° 25\' 30" S - 120° 53\' 49" E'
    },
    {
      name: 'Pelabuhan Nusantara Kendari',
      address: 'Kawasan Pelabuhan Kandai, Kendari Barat',
      lat: -3.978194,
      lng: 122.593389,
      badge: 'PELABUHAN',
      type: 'Pelabuhan Samudera',
      dms: '03° 58\' 41" S - 122° 35\' 36" E'
    },
    {
      name: 'Pelabuhan Ferry Kolaka (Dermaga ASDP)',
      address: 'Kolakaasi, Kec. Latambaga, Kab. Kolaka',
      lat: -4.054167,
      lng: 121.603333,
      badge: 'PELABUHAN',
      type: 'Pelabuhan Penyeberangan',
      dms: '04° 03\' 15" S - 121° 36\' 12" E'
    },
    {
      name: 'Pelabuhan Murhum Baubau (PELNI)',
      address: 'Jl. Yos Sudarso, Wolio, Kota Baubau',
      lat: -5.464722,
      lng: 122.603889,
      badge: 'PELABUHAN',
      type: 'Pelabuhan Samudera',
      dms: '05° 27\' 53" S - 122° 36\' 14" E'
    },
    {
      name: 'Perairan Teluk Kendari',
      address: 'Teluk Kendari, Sulawesi Tenggara',
      lat: -3.97,
      lng: 122.62,
      badge: 'PERAIRAN',
      type: 'Kawasan Perairan & Alur Masuk',
      dms: '03° 58\' 12" S - 122° 37\' 12" E'
    },
    {
      name: 'Pulau Labengki (Konawe Utara)',
      address: 'Perairan Labengki, Kec. Lasolo Kepulauan, Konut',
      lat: -3.44,
      lng: 122.45,
      badge: 'PERAIRAN',
      type: 'Kawasan Wisata Maritim & Kepulauan',
      dms: '03° 26\' 24" S - 122° 27\' 00" E'
    },
    {
      name: 'Selat Tiworo (Muna Barat - Konsel)',
      address: 'Perairan Selat Tiworo, Sulawesi Tenggara',
      lat: -4.75,
      lng: 122.4,
      badge: 'PERAIRAN',
      type: 'Alur Pelayaran Antar Pulau',
      dms: '04° 45\' 00" S - 122° 24\' 00" E'
    }
  ];

  // 12 Preset Point Icons configuration matching PointCreatorCard.tsx
  const PRESET_ICONS = [
    { type: 'lkp', label: 'LKP Kejadian', color: '#dc2626', emoji: '🚨' },
    { type: 'kantor', label: 'Kantor SAR', color: '#1e3a8a', emoji: '🏢' },
    { type: 'pos_sar', label: 'Pos / Unit SAR', color: '#ea580c', emoji: '📻' },
    { type: 'posko_sar', label: 'Posko Taktis', color: '#059669', emoji: '🏕️' },
    { type: 'rumah', label: 'Rumah/Shelter', color: '#16a34a', emoji: '🏠' },
    { type: 'kapal', label: 'Kapal SAR KN', color: '#0284c7', emoji: '🚢' },
    { type: 'perahu', label: 'Perahu Nelayan', color: '#06b6d4', emoji: '⛵' },
    { type: 'rib', label: 'RIB Basarnas', color: '#f97316', emoji: '🚤' },
    { type: 'orang_hilang', label: 'Orang Hilang', color: '#ef4444', emoji: '👤' },
    { type: 'kecelakaan_kapal', label: 'Laka Kapal', color: '#b91c1c', emoji: '🔥' },
    { type: 'kecelakaan_pesawat', label: 'Laka Udara', color: '#7c3aed', emoji: '✈️' },
    { type: 'bencana', label: 'Bencana Alam', color: '#d97706', emoji: '⚠️' }
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
      const d = Math.floor(absVal);
      const mFloat = (absVal - d) * 60;
      const m = Math.floor(mFloat);
      const s = Math.round((mFloat - m) * 60);
      const padD = isLat ? String(d).padStart(2, '0') : String(d).padStart(3, '0');
      return `${padD}° ${String(m).padStart(2, '0')}' ${String(s).padStart(2, '0')}" ${dir}`;
    }
    return `${formatDmsPart(lat, true)} - ${formatDmsPart(lng, false)}`;
  }

  function parseCoordinatesString(text) {
    if (!text) return null;
    const clean = text.trim();

    // DMS Format Regex: 04° 02' 16" S - 122° 29' 12" E or 04 02 16 S 122 29 12 E
    const dmsRegex = /(\d{1,2})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NSns])\s*[-–,\s]+\s*(\d{1,3})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([EWew])/i;
    const match = clean.match(dmsRegex);
    if (match) {
      const latD = parseFloat(match[1]);
      const latM = parseFloat(match[2]);
      const latS = parseFloat(match[3]);
      const latDir = match[4].toUpperCase();

      const lngD = parseFloat(match[5]);
      const lngM = parseFloat(match[6]);
      const lngS = parseFloat(match[7]);
      const lngDir = match[8].toUpperCase();

      let lat = latD + latM / 60 + latS / 3600;
      if (latDir === 'S') lat = -lat;

      let lng = lngD + lngM / 60 + lngS / 3600;
      if (lngDir === 'W') lng = -lng;

      return { lat, lng };
    }

    // Decimal comma separated e.g. -4.037694, 122.486694
    const decRegex = /([-+]?\d{1,2}\.\d+)\s*,\s*([-+]?\d{1,3}\.\d+)/;
    const decMatch = clean.match(decRegex);
    if (decMatch) {
      return {
        lat: parseFloat(decMatch[1]),
        lng: parseFloat(decMatch[2])
      };
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
      Math.sin((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.cos(dLat);
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

    const R = 6378137; // Earth radius in meters
    let totalArea = 0;

    for (let i = 0; i < coords.length; i++) {
      const p1 = coords[i];
      const p2 = coords[(i + 1) % coords.length];
      const lat1Rad = (p1.lat * Math.PI) / 180;
      const lat2Rad = (p2.lat * Math.PI) / 180;
      const dLngRad = ((p2.lng - p1.lng) * Math.PI) / 180;
      totalArea += dLngRad * (2 + Math.sin(lat1Rad) + Math.sin(lat2Rad));
    }
    totalArea = Math.abs((totalArea * (R * R)) / 4.0);

    const sqKm = totalArea / 1000000;
    const sqNm = sqKm * 0.291553;
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
        <image href="${customUrl}" x="${cx - 10}" y="6" width="20" height="20" preserveAspectRatio="xMidYMid slice" />
      `;
    } else {
      switch (type) {
        case 'alphabet':
          const char = (labelNumber || 'A').toUpperCase();
          innerContent = `
            <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
            <text x="${cx}" y="20.5" font-family="Arial, system-ui, sans-serif" font-size="12" font-weight="900" fill="${color}" text-anchor="middle">${char}</text>
          `;
          break;
        case 'lkp':
          innerContent = `
            <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
            <circle cx="${cx}" cy="16" r="9" fill="${color}" />
            <circle cx="${cx}" cy="16" r="4" fill="#ffffff" />
            <line x1="${cx - 10}" y1="16" x2="${cx + 10}" y2="16" stroke="#ffffff" stroke-width="1.8" />
            <line x1="${cx}" y1="6" x2="${cx}" y2="26" stroke="#ffffff" stroke-width="1.8" />
          `;
          break;
        case 'kantor':
          innerContent = `
            <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
            <path d="M${cx - 6} 22 V14 L${cx} 9 L${cx + 6} 14 V22 H${cx - 6} Z" fill="${color}" />
            <rect x="${cx - 2}" y="17" width="4" height="5" fill="#ffffff" />
          `;
          break;
        case 'pos_sar':
          innerContent = `
            <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
            <path d="M${cx - 4} 22 L${cx} 11 L${cx + 4} 22 Z" fill="${color}" />
            <circle cx="${cx}" cy="11" r="2" fill="${color}" />
            <path d="M${cx - 6} 11 A6 6 0 0 1 ${cx + 6} 11" fill="none" stroke="${color}" stroke-width="1.5" stroke-linecap="round" />
          `;
          break;
        case 'posko_sar':
          innerContent = `
            <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
            <path d="M${cx - 7} 22 L${cx} 12 L${cx + 7} 22 Z" fill="${color}" />
            <line x1="${cx}" y1="7" x2="${cx}" y2="12" stroke="${color}" stroke-width="1.5" stroke-linecap="round" />
            <polygon points="${cx},7 ${cx + 4.5},8.5 ${cx},10" fill="#dc2626" />
            <polygon points="${cx - 2.5},22 ${cx},17.5 ${cx + 2.5},22" fill="#ffffff" />
          `;
          break;
        case 'kapal':
          innerContent = `
            <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
            <path d="M${cx - 8} 17 L${cx - 5} 21 H${cx + 5} L${cx + 8} 17 Z" fill="${color}" />
            <rect x="${cx - 4}" y="12" width="8" height="5" fill="${color}" />
            <line x1="${cx}" y1="8" x2="${cx}" y2="12" stroke="${color}" stroke-width="1.5" />
          `;
          break;
        case 'rib':
        case 'perahu':
          innerContent = `
            <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
            <path d="M${cx - 7} 17 Q${cx} 22 ${cx + 7} 17 L${cx + 5} 20 H${cx - 5} Z" fill="${color}" />
            <polygon points="${cx - 1},10 ${cx + 4},13 ${cx - 1},16" fill="${color}" />
          `;
          break;
        default:
          innerContent = `
            <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
            <text x="${cx}" y="20" font-family="Arial, sans-serif" font-size="11" font-weight="900" fill="${color}" text-anchor="middle">📍</text>
          `;
      }
    }

    const haloRing = isEditing
      ? `<circle cx="${cx}" cy="16" r="17" fill="none" stroke="#facc15" stroke-width="3" stroke-dasharray="3,3" />`
      : type === 'lkp'
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
    const pType = point.pointType || point.type || 'lkp';
    const preset = PRESET_ICONS.find((p) => p.type === pType);
    const color = point.customColor || (preset ? preset.color : '#ea580c');
    const label = point.label || 'Titik SAR';
    const svg = createPointSvgHtml(pType, color, isEditing, point.customIconUrl, point.alphabetChar);

    return `
      <div style="display:flex; flex-direction:column; align-items:center; user-select:none; pointer-events:auto; transform: translate(-50%, -100%);">
        <div style="
          background: rgba(15, 23, 42, 0.95);
          color: #f8fafc;
          font-size: 11px;
          font-weight: 800;
          padding: 2px 7px;
          border-radius: 6px;
          border: 1px solid rgba(255, 255, 255, 0.4);
          box-shadow: 0 2px 6px rgba(0,0,0,0.6);
          white-space: nowrap;
          margin-bottom: 2px;
          pointer-events: none;
          max-width: 160px;
          overflow: hidden;
          text-overflow: ellipsis;
        ">${label}</div>
        ${svg}
      </div>
    `;
  }

  function createVertexBadgeHtml(letter, color = '#ea580c', isDragging = false) {
    const size = isDragging ? 36 : 30;
    return `
      <div style="transform: translate(-50%, -50%); display: inline-block;">
        <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
          <circle cx="${size/2}" cy="${size/2}" r="${size/2 - 2}" fill="${color}" stroke="#ffffff" stroke-width="2.5" />
          <text x="${size/2}" y="${size/2 + 4.5}" font-family="Arial, sans-serif" font-size="12" font-weight="900" fill="#ffffff" text-anchor="middle">${letter}</text>
        </svg>
      </div>
    `;
  }

  function createPatternCornerHtml(type, color = '#0284c7') {
    const isStart = type === 'start';
    const isEnd = type === 'end';
    const fillColor = isStart ? '#16a34a' : isEnd ? '#dc2626' : color;
    const letter = isStart ? 'S' : isEnd ? 'E' : '';
    const size = 24;

    return `
      <div style="transform: translate(-50%, -50%); display: inline-block;">
        <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
          <circle cx="12" cy="12" r="10" fill="${fillColor}" stroke="#ffffff" stroke-width="2" />
          ${letter ? `<text x="12" y="16" font-family="Arial, sans-serif" font-size="11" font-weight="900" fill="#ffffff" text-anchor="middle">${letter}</text>` : `<circle cx="12" cy="12" r="4" fill="#ffffff" />`}
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

    // Kansar Kendari center
    map = L.map('gisLeafletMap', {
      center: [-4.037694, 122.486694],
      zoom: 11,
      zoomControl: false,
      attributionControl: false
    });

    // Zoom control on bottom right
    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // 4 Basemap Tile Layers
    tileLayers.satellite = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      { maxZoom: 19 }
    );

    tileLayers.labels = L.tileLayer(
      'https://{s}.basemaps.cartocdn.com/rastertiles/voyager_only_labels/{z}/{x}/{y}{r}.png',
      { subdomains: 'abcd', maxZoom: 19 }
    );

    tileLayers.roadmap = L.tileLayer(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      { maxZoom: 19 }
    );

    tileLayers.terrain = L.tileLayer(
      'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      { maxZoom: 17 }
    );

    // Set Default: Hybrid (Satellite + CartoDB Labels)
    setBasemapLayer('hybrid');

    // Map Event Listeners
    map.on('mousemove', onMapMouseMove);
    map.on('click', onMapClick);
    map.on('zoomend', () => {
      const zoomEl = document.getElementById('hudZoomLevel');
      if (zoomEl) zoomEl.textContent = String(map.getZoom());
    });

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

    if (type === 'hybrid') {
      map.addLayer(tileLayers.satellite);
      map.addLayer(tileLayers.labels);
    } else if (type === 'satellite') {
      map.addLayer(tileLayers.satellite);
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

    // 3. If Measure Distance is active, add measure point
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

      // Check verified presets with multi-word matching
      const terms = rawQ.toLowerCase().split(/\s+/).filter(Boolean);
      VERIFIED_MARITIME_PRESETS.forEach((item) => {
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
        } catch (fallbackErr) {}
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
          category: 'None'
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
          iconType: 'alert'
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
        const emoji = (function() {
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
                <span class="text-slate-900 dark:text-white font-bold text-xs truncate">${r.name}</span>
                ${r.badge ? `<span class="text-[9px] px-1.5 py-0.2 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold border border-orange-500/30 shrink-0">${r.badge}</span>` : ''}
              </div>
              <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">${r.address || ''}</div>
              <div class="text-[10px] font-mono text-slate-400 dark:text-slate-500 mt-0.5">${r.dms || decimalToDms(r.lat, r.lng)}</div>
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
      <div class="gm-pin-container" style="transform: translate(-50%, -100%);">
        <div class="gm-pulse-ring"></div>
        <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42" fill="none">
          <path d="M17 0C7.611 0 0 7.611 0 17c0 12.75 17 25 17 25s17-12.25 17-25C34 7.611 26.389 0 17 0z" fill="#ea580c"/>
          <circle cx="17" cy="17" r="7" fill="#ffffff"/>
          <circle cx="17" cy="17" r="3.5" fill="#ea580c"/>
        </svg>
      </div>
    `;

    temporarySearchMarker = L.marker([result.lat, result.lng], {
      icon: L.divIcon({ html: pinHtml, className: 'gm-temp-search-marker', iconSize: [34, 42] })
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
      const emoji = (function() {
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
    // Populate 12 Preset SAR Icons
    const gridSar = document.getElementById('iconGridSar');
    if (gridSar) {
      gridSar.innerHTML = PRESET_ICONS.map(
        (p) => `
        <button type="button" data-icon="${p.type}" class="preset-icon-btn p-1.5 rounded-xl border border-slate-800 bg-slate-900 hover:border-orange-500 text-center flex flex-col items-center justify-center gap-1 transition ${pointIconType === p.type ? 'border-orange-500 ring-2 ring-orange-500/30' : ''}">
          <span class="text-base">${p.emoji}</span>
          <span class="text-[9px] font-bold text-slate-300 truncate w-full">${p.label}</span>
        </button>
      `
      ).join('');

      gridSar.querySelectorAll('.preset-icon-btn').forEach((btn) => {
        btn.addEventListener('click', () => {
          pointIconType = btn.getAttribute('data-icon');
          gridSar.querySelectorAll('.preset-icon-btn').forEach((b) => b.classList.remove('border-orange-500', 'ring-2', 'ring-orange-500/30'));
          btn.classList.add('border-orange-500', 'ring-2', 'ring-orange-500/30');
          updateDraftMarkerVisual();
        });
      });
    }

    // Populate Alphabet Grid A-Z
    const gridAlphabet = document.getElementById('iconGridAlphabet');
    if (gridAlphabet) {
      const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');
      gridAlphabet.innerHTML = letters
        .map(
          (char) => `
        <button type="button" data-char="${char}" class="alpha-icon-btn py-1 rounded-lg border border-slate-800 bg-slate-900 text-center font-bold text-xs text-white hover:border-orange-500 transition ${pointAlphabetChar === char ? 'border-orange-500 bg-orange-500/20' : ''}">
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

    if (tabSar && tabAlpha && tabCustom) {
      tabSar.addEventListener('click', () => {
        tabSar.className = 'py-1 rounded-lg bg-orange-500 text-white';
        tabAlpha.className = 'py-1 rounded-lg text-slate-400 hover:text-white';
        tabCustom.className = 'py-1 rounded-lg text-slate-400 hover:text-white';
        document.getElementById('iconGridSar').classList.remove('hidden');
        document.getElementById('iconGridAlphabet').classList.add('hidden');
        document.getElementById('iconGridCustom').classList.add('hidden');
      });

      tabAlpha.addEventListener('click', () => {
        tabAlpha.className = 'py-1 rounded-lg bg-orange-500 text-white';
        tabSar.className = 'py-1 rounded-lg text-slate-400 hover:text-white';
        tabCustom.className = 'py-1 rounded-lg text-slate-400 hover:text-white';
        document.getElementById('iconGridAlphabet').classList.remove('hidden');
        document.getElementById('iconGridSar').classList.add('hidden');
        document.getElementById('iconGridCustom').classList.add('hidden');
      });

      tabCustom.addEventListener('click', () => {
        tabCustom.className = 'py-1 rounded-lg bg-orange-500 text-white';
        tabSar.className = 'py-1 rounded-lg text-slate-400 hover:text-white';
        tabAlpha.className = 'py-1 rounded-lg text-slate-400 hover:text-white';
        document.getElementById('iconGridCustom').classList.remove('hidden');
        document.getElementById('iconGridSar').classList.add('hidden');
        document.getElementById('iconGridAlphabet').classList.add('hidden');
      });
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
          category: 'None',
          pointType: 'lkp'
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
    document.getElementById('pcPointCategory').value = data.category || 'None';
    document.getElementById('pcPointDesc').value = data.description || '';

    pointIconType = data.pointType || 'lkp';
    pointAlphabetChar = data.alphabetChar || 'A';
    pointCustomIconUrl = data.customIconUrl || '';

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

    const name = document.getElementById('pcPointName').value || 'Titik Baru';
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
        iconSize: [46, 54]
      })
    );
  }

  async function handleSavePoint() {
    const name = document.getElementById('pcPointName').value.trim();
    if (!name) {
      alert('Nama / Label Titik wajib diisi!');
      return;
    }

    const category = document.getElementById('pcPointCategory').value;
    const desc = document.getElementById('pcPointDesc').value.trim();

    const pointPayload = {
      id: editingPointId || `pt-${Date.now()}`,
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
      const res = await fetch(`${BASE_URL}/api/gis/points`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pointPayload)
      });

      if (editingPointId) {
        const idx = savedPoints.findIndex((p) => p.id === editingPointId);
        if (idx !== -1) savedPoints[idx] = pointPayload;
      } else {
        savedPoints.unshift(pointPayload);
      }

      closePointCreator();
      renderSavedPoints();
    } catch (err) {
      console.error('Error saving point:', err);
      // Fallback local update
      if (editingPointId) {
        const idx = savedPoints.findIndex((p) => p.id === editingPointId);
        if (idx !== -1) savedPoints[idx] = pointPayload;
      } else {
        savedPoints.unshift(pointPayload);
      }
      closePointCreator();
      renderSavedPoints();
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
          iconSize: [38, 46]
        })
      );

      // Popup
      const dms = decimalToDms(pt.lat, pt.lng);
      marker.bindPopup(`
        <div class="p-3 text-xs space-y-1">
          <div class="font-bold text-orange-400 text-sm">${pt.label}</div>
          <div class="text-[10px] text-slate-300">Kategori: <strong>${pt.category || pt.pointType || 'LKP'}</strong></div>
          <div class="text-[10px] font-mono text-slate-400">${dms}</div>
          ${pt.description ? `<div class="text-[10px] text-slate-300 pt-1 border-t border-slate-700">${pt.description}</div>` : ''}
        </div>
      `);

      // Dragend handler
      marker.on('dragend', async (e) => {
        const pos = e.target.getLatLng();
        pt.lat = pos.lat;
        pt.lng = pos.lng;

        // Persist update
        try {
          await fetch(`${BASE_URL}/api/gis/points/${pt.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(pt)
          });
        } catch (err) {}

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

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-start gap-2.5 flex-1 min-w-0 cursor-pointer" onclick="window.SAR_GIS.focusPoint('${pt.id}')">
              <span class="w-8 h-8 rounded-xl flex items-center justify-center text-sm shrink-0 border border-slate-200 dark:border-slate-700" style="background:${preset.color}25; color:${preset.color};">
                ${preset.emoji}
              </span>
              <div class="min-w-0 flex-1">
                <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${pt.label}</div>
                <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono truncate">${dms}</div>
                <div class="text-[9px] text-orange-600 dark:text-orange-400 font-mono mt-0.5">${distFromKansar.nm} NM dari Kansar (${distFromKansar.bearing}° ${distFromKansar.headingName})</div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePointVisibility('${pt.id}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded" title="${isHidden ? 'Tampilkan' : 'Sembunyikan'}">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPoint('${pt.id}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded" title="Edit">
                ✏️
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePoint('${pt.id}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded" title="Hapus">
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

    updateLivePolygonVisual();
  }

  function cancelPolygonDrawing() {
    isDrawingPolygon = false;
    editingPolygonId = null;
    activePolygonPoints = [];

    document.getElementById('polygonDrawingEditorCard').classList.add('hidden');

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
          iconSize: [30, 30]
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

    const payload = {
      id: editingPolygonId || `poly-${Date.now()}`,
      name: name,
      points: activePolygonPoints,
      strokeColor: polyStrokeColor,
      strokeWeight: polyStrokeWeight,
      fillColor: polyFillColor,
      fillOpacity: polyFillOpacity
    };

    try {
      await fetch(`${BASE_URL}/api/gis/polygons`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });

      if (editingPolygonId) {
        const idx = savedPolygons.findIndex((p) => p.id === editingPolygonId);
        if (idx !== -1) savedPolygons[idx] = payload;
      } else {
        savedPolygons.unshift(payload);
      }

      cancelPolygonDrawing();
      renderSavedPolygons();
    } catch (err) {
      if (editingPolygonId) {
        const idx = savedPolygons.findIndex((p) => p.id === editingPolygonId);
        if (idx !== -1) savedPolygons[idx] = payload;
      } else {
        savedPolygons.unshift(payload);
      }
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
      overlay.bindPopup(`
        <div class="p-3 text-xs space-y-1">
          <div class="font-bold text-orange-400 text-sm">${poly.name}</div>
          <div class="text-[11px] text-slate-300">Luas: <strong>${stats.sqNm} NM²</strong> (${stats.sqKm} km²)</div>
          <div class="text-[11px] text-slate-300">Keliling: <strong>${stats.perimeterNm} NM</strong> (${stats.perimeterKm} km)</div>
          <div class="text-[10px] text-slate-400 font-mono">${poly.points.length} Sudut Vertices</div>
        </div>
      `);

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

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="cursor-pointer flex-1 min-w-0" onclick="window.SAR_GIS.focusPolygon('${poly.id}')">
              <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full shrink-0" style="background-color: ${poly.strokeColor};"></span>
                <span class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${poly.name}</span>
              </div>
              <div class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                ${stats.sqNm} NM² (${stats.sqKm} km²) • Keliling: ${stats.perimeterNm} NM
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePolygonVisibility('${poly.id}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded" title="Sembunyikan / Tampilkan">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.editPolygon('${poly.id}')" class="p-1 text-slate-400 hover:text-orange-500 dark:hover:text-orange-400 rounded" title="Edit">
                ✏️
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePolygon('${poly.id}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded" title="Hapus">
                🗑️
              </button>
            </div>
          </div>

          <button type="button" onclick="window.SAR_GIS.applyPatternFromPolygon('${poly.id}')" class="w-full py-1.5 px-2 rounded-xl bg-slate-100 hover:bg-orange-500/10 text-orange-600 dark:bg-slate-900 dark:hover:bg-orange-500/20 dark:text-orange-400 border border-slate-200 dark:border-slate-800 text-[10px] font-bold transition flex items-center justify-center gap-1.5">
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

    let options = `<option value="datum_kansar">📍 Datum LKP Kansar Kendari</option>`;
    savedPolygons.forEach((p) => {
      options += `<option value="${p.id}">📐 Area: ${p.name} (${p.points.length} Sudut)</option>`;
    });
    sel.innerHTML = options;
  }

  // ==========================================
  // 8. SEARCH PATTERNS GENERATOR (IAMSAR)
  // ==========================================

  function initSearchPatternUI() {
    // Pattern type buttons
    document.querySelectorAll('.pat-type-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        activePatternType = btn.getAttribute('data-ptype');
        document.querySelectorAll('.pat-type-btn').forEach((b) => {
          b.className = 'pat-type-btn p-2 rounded-xl border border-slate-800 bg-slate-950 text-slate-400 text-left hover:text-white transition';
        });
        btn.className = 'pat-type-btn p-2 rounded-xl border border-orange-500 bg-orange-500/20 text-orange-400 text-left transition';
      });
    });

    // Generate Button
    const btnGen = document.getElementById('btnGenerateSearchPattern');
    if (btnGen) {
      btnGen.addEventListener('click', generateSearchPatternRoute);
    }

    // Clear Button
    const btnClear = document.getElementById('btnClearCurrentPattern');
    if (btnClear) {
      btnClear.addEventListener('click', clearCurrentPatternVisual);
    }

    // Save Button
    const btnSave = document.getElementById('btnSavePatternConfirm');
    if (btnSave) {
      btnSave.addEventListener('click', handleSaveSearchPattern);
    }
  }

  function generateSearchPatternRoute() {
    const targetPolyId = document.getElementById('patternTargetPolygon').value;
    const spacingNm = parseFloat(document.getElementById('patternTrackSpacing').value) || 1.5;
    const legs = parseInt(document.getElementById('patternLegsCount').value, 10) || 6;
    const speed = parseFloat(document.getElementById('patternVesselSpeed').value) || 20;

    let targetPolygon = savedPolygons.find((p) => p.id === targetPolyId);
    let coords = [];

    if (targetPolygon && targetPolygon.points.length >= 3) {
      coords = generateFittedPatternInsidePolygon(activePatternType, targetPolygon.points, spacingNm, legs);
    } else {
      coords = generateDatumPattern(activePatternType, { lat: -4.037694, lng: 122.486694 }, spacingNm, legs);
    }

    activeGeneratedWaypoints = coords;

    // Calculate total distance along waypoints
    let totalNm = 0;
    for (let i = 0; i < coords.length - 1; i++) {
      totalNm += calculateDistanceHaversine(coords[i].lat, coords[i].lng, coords[i + 1].lat, coords[i + 1].lng).nm;
    }
    activeGeneratedDistanceNm = totalNm;

    const etaMins = (totalNm / Math.max(1, speed)) * 60;

    // Show Results Card
    document.getElementById('resPatternDistNm').textContent = `${totalNm.toFixed(2)} NM (${(totalNm * 1.852).toFixed(2)} km)`;
    document.getElementById('resPatternEta').textContent = formatDurationIndonesian(etaMins);
    document.getElementById('resPatternWpCount').textContent = `${coords.length} WP`;
    document.getElementById('patternResultsCard').classList.remove('hidden');

    renderActivePatternOnMap();
  }

  function generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount) {
    let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
    polygonPoints.forEach((p) => {
      if (p.lat < minLat) minLat = p.lat;
      if (p.lat > maxLat) maxLat = p.lat;
      if (p.lng < minLng) minLng = p.lng;
      if (p.lng > maxLng) maxLng = p.lng;
    });

    const midLat = (minLat + maxLat) / 2;
    const lngKmPerDeg = 111.32 * Math.cos((midLat * Math.PI) / 180);
    const spacingKm = spacingNm * 1.852;
    const marginLat = (spacingKm / 2) / 110.574;
    const marginLng = (spacingKm / 2) / lngKmPerDeg;

    const effMinLat = minLat + marginLat;
    const effMaxLat = maxLat - marginLat;
    const effMinLng = minLng + marginLng;
    const effMaxLng = maxLng - marginLng;

    const points = [];

    if (type === 'parallel_track') {
      const stepY = legsCount > 1 ? (effMaxLat - effMinLat) / (legsCount - 1) : 0;
      for (let i = 0; i < legsCount; i++) {
        const curLat = effMaxLat - i * stepY;
        const sweepWest = i % 2 === 0;
        points.push({
          lat: curLat,
          lng: sweepWest ? effMinLng : effMaxLng,
          label: i === 0 ? 'Entry (S)' : `Lintasan ${i + 1}A`
        });
        points.push({
          lat: curLat,
          lng: sweepWest ? effMaxLng : effMinLng,
          label: i === legsCount - 1 ? 'Exit (E)' : `Lintasan ${i + 1}B`
        });
      }
    } else if (type === 'creeping_line') {
      const stepX = legsCount > 1 ? (effMaxLng - effMinLng) / (legsCount - 1) : 0;
      for (let i = 0; i < legsCount; i++) {
        const curLng = effMinLng + i * stepX;
        const sweepNorth = i % 2 === 0;
        points.push({
          lat: sweepNorth ? effMaxLat : effMinLat,
          lng: curLng,
          label: i === 0 ? 'Entry (S)' : `Sapu ${i + 1}A`
        });
        points.push({
          lat: sweepNorth ? effMinLat : effMaxLat,
          lng: curLng,
          label: i === legsCount - 1 ? 'Exit (E)' : `Sapu ${i + 1}B`
        });
      }
    } else if (type === 'sector_search') {
      const radiusKm = spacingKm * 2;
      points.push({ lat: midLat, lng: midLng, label: 'Datum Centroid' });
      for (let i = 0; i < 3; i++) {
        const a1 = (i * 120 * Math.PI) / 180;
        const a2 = ((i * 120 + 120) * Math.PI) / 180;
        const p1Lat = midLat + (radiusKm * Math.cos(a1)) / 110.574;
        const p1Lng = midLng + (radiusKm * Math.sin(a1)) / lngKmPerDeg;
        const p2Lat = midLat + (radiusKm * Math.cos(a2)) / 110.574;
        const p2Lng = midLng + (radiusKm * Math.sin(a2)) / lngKmPerDeg;
        points.push({ lat: p1Lat, lng: p1Lng, label: `Sektor ${i + 1} Luar` });
        points.push({ lat: p2Lat, lng: p2Lng, label: `Sektor ${i + 1} Sudut` });
        points.push({ lat: midLat, lng: midLng, label: 'Kembali ke Datum' });
      }
    } else {
      // Expanding Square (SS)
      points.push({ lat: midLat, lng: midLng, label: 'Datum LKP (S)' });
      let curX = 0, curY = 0, step = 1, dir = 0;
      for (let leg = 1; leg <= legsCount * 2; leg++) {
        const legKm = step * spacingKm;
        if (dir === 0) curX += legKm;
        else if (dir === 1) curY -= legKm;
        else if (dir === 2) curX -= legKm;
        else if (dir === 3) curY += legKm;

        points.push({
          lat: midLat + curY / 110.574,
          lng: midLng + curX / lngKmPerDeg,
          label: `WP-${leg}`
        });

        if (leg % 2 === 0) step += 1;
        dir = (dir + 1) % 4;
      }
    }

    return points;
  }

  function generateDatumPattern(type, center, spacingNm, legsCount) {
    const fakePolygon = [
      { lat: center.lat + 0.1, lng: center.lng - 0.1 },
      { lat: center.lat + 0.1, lng: center.lng + 0.1 },
      { lat: center.lat - 0.1, lng: center.lng + 0.1 },
      { lat: center.lat - 0.1, lng: center.lng - 0.1 }
    ];
    return generateFittedPatternInsidePolygon(type, fakePolygon, spacingNm, legsCount);
  }

  function renderActivePatternOnMap() {
    clearCurrentPatternVisual();

    if (activeGeneratedWaypoints.length < 2) return;

    const latlngs = activeGeneratedWaypoints.map((p) => [p.lat, p.lng]);
    activePatternPolyline = L.polyline(latlngs, {
      color: '#0284c7',
      weight: 3,
      dashArray: '6, 6'
    }).addTo(map);

    // Corner markers: 'S' and 'E'
    activeGeneratedWaypoints.forEach((wp, idx) => {
      const isStart = idx === 0;
      const isEnd = idx === activeGeneratedWaypoints.length - 1;
      if (isStart || isEnd) {
        const marker = L.marker([wp.lat, wp.lng], { zIndexOffset: 1500 }).addTo(map);
        marker.setIcon(
          L.divIcon({
            html: createPatternCornerHtml(isStart ? 'start' : 'end'),
            className: 'sar-pat-corner',
            iconSize: [24, 24]
          })
        );
        marker.bindTooltip(wp.label, { direction: 'top', offset: [0, -12] });
        activePatternMarkers.push(marker);
      }
    });

    map.fitBounds(activePatternPolyline.getBounds(), { padding: [40, 40] });
  }

  function clearCurrentPatternVisual() {
    if (activePatternPolyline) {
      map.removeLayer(activePatternPolyline);
      activePatternPolyline = null;
    }
    activePatternMarkers.forEach((m) => map.removeLayer(m));
    activePatternMarkers = [];
    document.getElementById('patternResultsCard').classList.add('hidden');
  }

  async function handleSaveSearchPattern() {
    const name = document.getElementById('patternFormName').value.trim();
    if (!name || activeGeneratedWaypoints.length < 2) {
      alert('Lintasan belum digenerate!');
      return;
    }

    const payload = {
      id: `pattern-${Date.now()}`,
      name: name,
      patternType: activePatternType,
      waypoints: activeGeneratedWaypoints,
      totalDistanceNm: activeGeneratedDistanceNm,
      createdAt: `${window.formatTanggalIndo(new Date())} ${window.formatJamWita(new Date())} WITA`
    };

    try {
      await fetch(`${BASE_URL}/api/gis/search-patterns`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      savedPatterns.unshift(payload);
      clearCurrentPatternVisual();
      renderSavedPatterns();
    } catch (err) {
      savedPatterns.unshift(payload);
      clearCurrentPatternVisual();
      renderSavedPatterns();
    }
  }

  function renderSavedPatterns() {
    patternPolylinesMap.forEach((pl) => map.removeLayer(pl));
    patternPolylinesMap.clear();

    savedPatterns.forEach((pat) => {
      if (hiddenPatternIds.has(pat.id)) return;
      if (!pat.waypoints || pat.waypoints.length < 2) return;

      const latlngs = pat.waypoints.map((p) => [p.lat, p.lng]);
      const pl = L.polyline(latlngs, {
        color: pat.color || '#0284c7',
        weight: 3,
        dashArray: '5, 5'
      }).addTo(map);

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
        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="cursor-pointer flex-1 min-w-0" onclick="window.SAR_GIS.focusPattern('${pat.id}')">
              <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${pat.name}</div>
              <div class="text-[10px] font-mono text-sky-600 dark:text-sky-400 mt-0.5">
                ${pat.totalDistanceNm ? `${pat.totalDistanceNm.toFixed(1)} NM` : ''} • ${pat.waypoints ? pat.waypoints.length : 0} Waypoints
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.togglePatternVisibility('${pat.id}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.deletePattern('${pat.id}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded">
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
  // 9. MEASURE DISTANCE & SPEED SIMULATOR
  // ==========================================

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

    const btnStraight = document.getElementById('btnModeStraight');
    const btnPath = document.getElementById('btnModePath');

    if (btnStraight && btnPath) {
      btnStraight.addEventListener('click', () => {
        measureModeType = 'straight';
        btnStraight.className = 'p-2 rounded-xl border border-orange-500 bg-orange-500/20 text-orange-400 text-center transition font-bold';
        btnPath.className = 'p-2 rounded-xl border border-slate-800 bg-slate-950 text-slate-400 text-center hover:text-white transition font-bold';
        if (measurePoints.length > 2) measurePoints = measurePoints.slice(0, 2);
        updateMeasureVisual();
      });

      btnPath.addEventListener('click', () => {
        measureModeType = 'path';
        btnPath.className = 'p-2 rounded-xl border border-orange-500 bg-orange-500/20 text-orange-400 text-center transition font-bold';
        btnStraight.className = 'p-2 rounded-xl border border-slate-800 bg-slate-950 text-slate-400 text-center hover:text-white transition font-bold';
        updateMeasureVisual();
      });
    }

    // Quick Speed Preset Cards
    document.querySelectorAll('.speed-preset-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        const speed = parseFloat(btn.getAttribute('data-speed'));
        document.getElementById('measureVesselSpeed').value = speed;
        vesselSpeedKnots = speed;
        updateMeasureVisual();
      });
    });

    const speedInput = document.getElementById('measureVesselSpeed');
    if (speedInput) {
      speedInput.addEventListener('input', (e) => {
        vesselSpeedKnots = parseFloat(e.target.value) || 20;
        updateMeasureVisual();
      });
    }

    const clearBtn = document.getElementById('btnClearMeasureRoute');
    if (clearBtn) {
      clearBtn.addEventListener('click', clearMeasureVisual);
    }

    const saveBtn = document.getElementById('btnSaveMeasureRoute');
    if (saveBtn) {
      saveBtn.addEventListener('click', handleSaveMeasureRoute);
    }
  }

  function addMeasurePoint(lat, lng) {
    if (measureModeType === 'straight' && measurePoints.length >= 2) {
      measurePoints = [{ lat, lng }];
    } else {
      measurePoints.push({ lat, lng });
    }
    updateMeasureVisual();
  }

  function updateMeasureVisual() {
    let totalKm = 0;
    let totalNm = 0;
    let lastBearing = 0;
    let lastHeading = 'Utara';

    for (let i = 0; i < measurePoints.length - 1; i++) {
      const p1 = measurePoints[i];
      const p2 = measurePoints[i + 1];
      const res = calculateDistanceHaversine(p1.lat, p1.lng, p2.lat, p2.lng);
      totalKm += res.km;
      totalNm += res.nm;
      lastBearing = res.bearing;
      lastHeading = res.headingName;
    }

    const speedKnotsEffective = Math.max(1, vesselSpeedKnots);
    const etaMins = (totalNm / speedKnotsEffective) * 60;

    document.getElementById('liveRouteDistNm').textContent = `${totalNm.toFixed(2)} NM`;
    document.getElementById('liveRouteDistKm').textContent = `${totalKm.toFixed(2)} km`;
    document.getElementById('liveRouteBearing').textContent = `${lastBearing}° (${lastHeading})`;
    document.getElementById('liveRouteEta').textContent = formatDurationIndonesian(etaMins);

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
      const marker = L.circleMarker([p.lat, p.lng], {
        radius: 6,
        fillColor: '#10b981',
        color: '#ffffff',
        weight: 2,
        fillOpacity: 1
      }).addTo(map);

      marker.bindTooltip(`WP-${idx + 1}`, { permanent: true, direction: 'top', offset: [0, -8] });
      measureMarkers.push(marker);
    });
  }

  function clearMeasureVisual() {
    measurePoints = [];
    if (measurePolyline) {
      map.removeLayer(measurePolyline);
      measurePolyline = null;
    }
    measureMarkers.forEach((m) => map.removeLayer(m));
    measureMarkers = [];

    document.getElementById('liveRouteDistNm').textContent = '0.00 NM';
    document.getElementById('liveRouteDistKm').textContent = '0.00 km';
    document.getElementById('liveRouteBearing').textContent = '0° (Utara)';
    document.getElementById('liveRouteEta').textContent = '0 Menit';
  }

  async function handleSaveMeasureRoute() {
    if (measurePoints.length < 2) {
      alert('Minimal tentukan 2 titik rute!');
      return;
    }

    const payload = {
      id: `route-${Date.now()}`,
      name: `Rute Operasi #${savedRoutes.length + 1}`,
      points: measurePoints,
      vesselSpeedKnots: vesselSpeedKnots,
      strokeColor: '#059669',
      strokeWeight: 3.5,
      strokeOpacity: 0.9,
      createdAt: `${window.formatTanggalIndo(new Date())} WITA`
    };

    try {
      await fetch(`${BASE_URL}/api/gis/routes`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      savedRoutes.unshift(payload);
      clearMeasureVisual();
      renderSavedRoutes();
    } catch (err) {
      savedRoutes.unshift(payload);
      clearMeasureVisual();
      renderSavedRoutes();
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
        let distNm = 0;
        for (let i = 0; i < (rt.points ? rt.points.length - 1 : 0); i++) {
          distNm += calculateDistanceHaversine(rt.points[i].lat, rt.points[i].lng, rt.points[i + 1].lat, rt.points[i + 1].lng).nm;
        }

        return `
        <div class="p-3 rounded-2xl border ${isHidden ? 'bg-slate-100/50 dark:bg-slate-950/40 border-slate-200 dark:border-slate-800/60 opacity-60' : 'bg-white dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs'} hover:border-orange-500/50 transition group space-y-2">
          <div class="flex items-start justify-between gap-2">
            <div class="cursor-pointer flex-1 min-w-0" onclick="window.SAR_GIS.focusRoute('${rt.id}')">
              <div class="text-xs font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition truncate">${rt.name}</div>
              <div class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 mt-0.5">
                ${distNm.toFixed(2)} NM (${(distNm * 1.852).toFixed(2)} km) • @ ${rt.vesselSpeedKnots} Knot
              </div>
            </div>

            <div class="flex items-center gap-1 shrink-0">
              <button type="button" onclick="window.SAR_GIS.toggleRouteVisibility('${rt.id}')" class="p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white rounded">
                ${isHidden ? '👁️‍🗨️' : '👁️'}
              </button>
              <button type="button" onclick="window.SAR_GIS.deleteRoute('${rt.id}')" class="p-1 text-slate-400 hover:text-red-500 dark:hover:text-red-400 rounded">
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
    let kml = `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n  <Document>\n    <name>Plotting Operasi SAR Kendari</name>\n`;

    // Folder 1: Points
    kml += `    <Folder>\n      <name>Titik Operasi SAR</name>\n`;
    savedPoints.forEach((p) => {
      kml += `      <Placemark>\n        <name>${p.label || 'Titik'}</name>\n        <description>${p.description || ''} [${p.category || 'SAR'}]</description>\n        <Point><coordinates>${p.lng},${p.lat},0</coordinates></Point>\n      </Placemark>\n`;
    });
    kml += `    </Folder>\n`;

    // Folder 2: Polygons
    kml += `    <Folder>\n      <name>Area Sektor Poligon</name>\n`;
    savedPolygons.forEach((poly) => {
      const coordsStr = poly.points.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
      kml += `      <Placemark>\n        <name>${poly.name || 'Sektor'}</name>\n        <Polygon><outerBoundaryIs><LinearRing><coordinates>${coordsStr} ${poly.points[0].lng},${poly.points[0].lat},0</coordinates></LinearRing></outerBoundaryIs></Polygon>\n      </Placemark>\n`;
    });
    kml += `    </Folder>\n`;

    // Folder 3: Patterns & Routes
    kml += `    <Folder>\n      <name>Rute dan Lintasan SAR</name>\n`;
    savedPatterns.forEach((pat) => {
      const coordsStr = pat.waypoints.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
      kml += `      <Placemark>\n        <name>${pat.name || 'Pola Lintasan'}</name>\n        <LineString><coordinates>${coordsStr}</coordinates></LineString>\n      </Placemark>\n`;
    });
    savedRoutes.forEach((rt) => {
      const coordsStr = rt.points.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
      kml += `      <Placemark>\n        <name>${rt.name || 'Rute Jarak'}</name>\n        <LineString><coordinates>${coordsStr}</coordinates></LineString>\n      </Placemark>\n`;
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
        properties: { name: p.label, category: p.category, type: p.pointType },
        geometry: { type: 'Point', coordinates: [p.lng, p.lat] }
      });
    });

    savedPolygons.forEach((poly) => {
      const ring = poly.points.map((pt) => [pt.lng, pt.lat]);
      ring.push([poly.points[0].lng, poly.points[0].lat]);
      features.push({
        type: 'Feature',
        properties: { name: poly.name },
        geometry: { type: 'Polygon', coordinates: [ring] }
      });
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

    // Populate Print Tables
    const tbodyPts = document.getElementById('printTbodyPoints');
    if (tbodyPts) {
      tbodyPts.innerHTML = savedPoints
        .map(
          (p) => `
        <tr>
          <td class="py-1 font-bold text-slate-800">${p.label}</td>
          <td class="py-1 text-slate-600">${p.category || p.pointType || 'LKP'}</td>
          <td class="py-1 text-slate-700">${decimalToDms(p.lat, p.lng)}</td>
        </tr>
      `
        )
        .join('');
    }

    const tbodyPolys = document.getElementById('printTbodyPolygons');
    if (tbodyPolys) {
      tbodyPolys.innerHTML = savedPolygons
        .map((poly) => {
          const stats = calculatePolygonSphericalArea(poly.points);
          return `
          <tr>
            <td class="py-1 font-bold text-slate-800">${poly.name}</td>
            <td class="py-1 text-slate-700">${stats.sqNm} NM²</td>
            <td class="py-1 text-slate-700">${stats.perimeterNm} NM</td>
          </tr>
        `;
        })
        .join('');
    }

    // Render Mini-Map Snapshot Target inside modal
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

      L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
        { maxZoom: 19 }
      ).addTo(printPreviewMap);

      // Clone polygons and points onto print map
      savedPolygons.forEach((poly) => {
        if (poly.points && poly.points.length >= 3) {
          L.polygon(poly.points.map((p) => [p.lat, p.lng]), {
            color: poly.strokeColor || '#ea580c',
            weight: 2,
            fillColor: poly.fillColor || '#ea580c',
            fillOpacity: 0.25
          }).addTo(printPreviewMap);
        }
      });

      savedPoints.forEach((pt) => {
        L.marker([pt.lat, pt.lng], {
          icon: L.divIcon({
            html: createMarkerHtml(pt, false),
            className: 'sar-print-pin',
            iconSize: [30, 36]
          })
        }).addTo(printPreviewMap);
      });
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
          <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
          <style>
            @page { size: A4 portrait; margin: 10mm; }
            body { font-family: system-ui, -apple-system, sans-serif; color: #000; background: #fff; margin: 0; padding: 0; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 10px; }
            th { background: #f1f5f9; text-align: left; }
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
      setTimeout(() => document.body.removeChild(frame), 1000);
    }, 500);
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

    document.querySelectorAll('.gis-tab-nav-btn').forEach((btn) => {
      const t = btn.getAttribute('data-tab');
      if (t === tabId) {
        btn.className = 'gis-tab-nav-btn py-3 px-1 border-b-2 border-orange-500 text-orange-400 flex flex-col items-center justify-center gap-1 transition';
      } else {
        btn.className = 'gis-tab-nav-btn py-3 px-1 border-b-2 border-transparent text-slate-400 hover:text-slate-200 flex flex-col items-center justify-center gap-1 transition';
      }
    });

    document.querySelectorAll('.gis-tab-pane').forEach((pane) => {
      if (pane.id === tabId) {
        pane.classList.remove('hidden');
      } else {
        pane.classList.add('hidden');
      }
    });
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
        await fetch(`${BASE_URL}/api/gis/points/${id}`, { method: 'DELETE' });
        savedPoints = savedPoints.filter((p) => p.id !== id);
        renderSavedPoints();
      } catch (err) {
        savedPoints = savedPoints.filter((p) => p.id !== id);
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
        await fetch(`${BASE_URL}/api/gis/polygons/${id}`, { method: 'DELETE' });
        savedPolygons = savedPolygons.filter((p) => p.id !== id);
        renderSavedPolygons();
      } catch (err) {
        savedPolygons = savedPolygons.filter((p) => p.id !== id);
        renderSavedPolygons();
      }
    },

    removeVertex: (index) => {
      removePolygonVertex(index);
    },

    applyPatternFromPolygon: (id) => {
      switchActiveTab('tab-pattern');
      const sel = document.getElementById('patternTargetPolygon');
      if (sel) sel.value = id;
      generateSearchPatternRoute();
    },

    focusPattern: (id) => {
      const pat = savedPatterns.find((p) => p.id === id);
      if (pat && pat.waypoints.length >= 2 && map) {
        const bounds = L.latLngBounds(pat.waypoints.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [50, 50] });
      }
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
        await fetch(`${BASE_URL}/api/gis/search-patterns/${id}`, { method: 'DELETE' });
        savedPatterns = savedPatterns.filter((p) => p.id !== id);
        renderSavedPatterns();
      } catch (err) {
        savedPatterns = savedPatterns.filter((p) => p.id !== id);
        renderSavedPatterns();
      }
    },

    focusRoute: (id) => {
      const rt = savedRoutes.find((r) => r.id === id);
      if (rt && rt.points.length >= 2 && map) {
        const bounds = L.latLngBounds(rt.points.map((p) => [p.lat, p.lng]));
        map.fitBounds(bounds, { padding: [50, 50] });
      }
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
        await fetch(`${BASE_URL}/api/gis/routes/${id}`, { method: 'DELETE' });
        savedRoutes = savedRoutes.filter((r) => r.id !== id);
        renderSavedRoutes();
      } catch (err) {
        savedRoutes = savedRoutes.filter((r) => r.id !== id);
        renderSavedRoutes();
      }
    }
  };

  // ==========================================
  // 14. BOOTSTRAP APPLICATION
  // ==========================================

  document.addEventListener('DOMContentLoaded', () => {
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
  });
})();
