import { SarUnit } from '../types';

export const DEFAULT_SAR_UNITS: SarUnit[] = [
  {
    id_unit: 1,
    nama_unit: 'Kansar Kendari (Kantor Utama)',
    jenis_unit: 'KANTOR_SAR',
    wilayah: 'Kota Kendari / Konawe Selatan',
    latitude: -4.084194,
    longitude: 122.428389,
    koordinat_dms: '04° 05\' 03" S - 122° 25\' 42" E',
    alamat: 'Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan, Sultra',
    no_telp: '(0401) 3122115 / Emergency 115',
    is_default: true,
  },
  {
    id_unit: 2,
    nama_unit: 'Pos SAR Kolaka',
    jenis_unit: 'POS_SAR',
    wilayah: 'Kabupaten Kolaka',
    latitude: -4.053889,
    longitude: 121.604167,
    koordinat_dms: '04° 03\' 14" S - 121° 36\' 15" E',
    alamat: 'Kompleks Pelabuhan Ferry Kolaka, Jl. Dermaga Ferry Kolaka, Kel. Kolakaasi, Kec. Latambaga, Kab. Kolaka',
    no_telp: '0811-4091-115',
    is_default: false,
  },
  {
    id_unit: 3,
    nama_unit: 'Pos SAR Baubau',
    jenis_unit: 'POS_SAR',
    wilayah: 'Kota Baubau & Buton',
    latitude: -5.463889,
    longitude: 122.604167,
    koordinat_dms: '05° 27\' 50" S - 122° 36\' 15" E',
    alamat: 'Kawasan Pelabuhan Murhum / Jembatan Batu, Kel. Batulo, Kec. Wolio, Kota Baubau, Sulawesi Tenggara',
    no_telp: '0811-4092-115',
    is_default: false,
  },
  {
    id_unit: 4,
    nama_unit: 'Pos SAR Wakatobi',
    jenis_unit: 'POS_SAR',
    wilayah: 'Kabupaten Wakatobi',
    latitude: -5.326389,
    longitude: 123.588889,
    koordinat_dms: '05° 19\' 35" S - 123° 35\' 20" E',
    alamat: 'Kawasan Pelabuhan Mandati / Pangulubelo, Wangi-Wangi Selatan, Kab. Wakatobi, Sulawesi Tenggara',
    no_telp: '0811-4093-115',
    is_default: false,
  },
  {
    id_unit: 5,
    nama_unit: 'Pos SAR Konawe Utara',
    jenis_unit: 'POS_SAR',
    wilayah: 'Kabupaten Konawe Utara',
    latitude: -3.513889,
    longitude: 122.180556,
    koordinat_dms: '03° 30\' 50" S - 122° 10\' 50" E',
    alamat: 'Jl. Poros Trans Sulawesi Kendari - Morowali, Wanggudu / Kec. Asera, Kab. Konawe Utara, Sulawesi Tenggara',
    no_telp: '0811-4094-115',
    is_default: false,
  },
  {
    id_unit: 6,
    nama_unit: 'Unit Siaga SAR Muna',
    jenis_unit: 'UNIT_SIAGA',
    wilayah: 'Kabupaten Muna',
    latitude: -4.836111,
    longitude: 122.719444,
    koordinat_dms: '04° 50\' 10" S - 122° 43\' 10" E',
    alamat: 'Kawasan Pelabuhan Nusantara Raha / Jl. By Pass, Kota Raha, Kab. Muna, Sulawesi Tenggara',
    no_telp: '0811-4095-115',
    is_default: false,
  },
  {
    id_unit: 7,
    nama_unit: 'Unit Siaga SAR Konawe',
    jenis_unit: 'UNIT_SIAGA',
    wilayah: 'Kabupaten Konawe',
    latitude: -3.905556,
    longitude: 122.047222,
    koordinat_dms: '03° 54\' 20" S - 122° 02\' 50" E',
    alamat: 'Jl. Poros Kendari - Kolaka, Kota Unaaha, Kab. Konawe, Sulawesi Tenggara',
    no_telp: '0811-4096-115',
    is_default: false,
  },
  {
    id_unit: 8,
    nama_unit: 'Unit Siaga SAR Kolaka Utara',
    jenis_unit: 'UNIT_SIAGA',
    wilayah: 'Kabupaten Kolaka Utara',
    latitude: -3.425000,
    longitude: 120.897000,
    koordinat_dms: '03° 25\' 30" S - 120° 53\' 49" E',
    alamat: 'Kawasan Pelabuhan Penyeberangan Tobaku / Jl. Poros Lasusua, Kec. Lasusua, Kab. Kolaka Utara',
    no_telp: '0811-4097-115',
    is_default: false,
  },
  {
    id_unit: 9,
    nama_unit: 'Dermaga SAR Kendari (KN SAR / RB)',
    jenis_unit: 'KN_SAR',
    wilayah: 'Kota Kendari (Bungkutoko)',
    latitude: -3.974167,
    longitude: 122.585000,
    koordinat_dms: '03° 58\' 27" S - 122° 35\' 06" E',
    alamat: 'Kawasan Pelabuhan Bungkutoko (Pangkalan KN SAR Pacitan / RB 210 / RB 307), Kota Kendari',
    no_telp: '(0401) 3122115',
    is_default: false,
  },
];

/**
 * Convert Decimal Degrees to DMS formatted Basarnas string
 * e.g. -4.017778, 122.569444 -> 04° 01' 04" S - 122° 34' 10" E
 */
export function decimalToDms(lat: number, lng: number): string {
  const latDir = lat < 0 ? 'S' : 'N';
  const absLat = Math.abs(lat);
  const latDeg = Math.floor(absLat);
  const latMinFloat = (absLat - latDeg) * 60;
  const latMin = Math.floor(latMinFloat);
  const latSec = Math.round((latMinFloat - latMin) * 60);

  const lngDir = lng < 0 ? 'W' : 'E';
  const absLng = Math.abs(lng);
  const lngDeg = Math.floor(absLng);
  const lngMinFloat = (absLng - lngDeg) * 60;
  const lngMin = Math.floor(lngMinFloat);
  const lngSec = Math.round((lngMinFloat - lngMin) * 60);

  const latStr = `${String(latDeg).padStart(2, '0')}° ${String(latMin).padStart(2, '0')}' ${String(latSec).padStart(2, '0')}" ${latDir}`;
  const lngStr = `${String(lngDeg).padStart(3, '0')}° ${String(lngMin).padStart(2, '0')}' ${String(lngSec).padStart(2, '0')}" ${lngDir}`;

  return `${latStr} - ${lngStr}`;
}

/**
 * Parse DMS string or text to decimal latitude and longitude
 */
export function parseDmsToDecimal(dmsText: string): { lat: number; lng: number } | null {
  if (!dmsText) return null;

  // Regex for formats like: 04° 01' 04" S - 122° 34' 10" E or variants
  const regex = /(\d{1,2})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NSns])\s*[-–,\s]+\s*(\d{1,3})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([EWew])/i;
  const match = dmsText.match(regex);

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

  // Also check if text is raw comma-separated decimals e.g. -4.017778, 122.569444
  const decMatch = dmsText.match(/([-+]?\d{1,2}\.\d+)\s*,\s*([-+]?\d{1,3}\.\d+)/);
  if (decMatch) {
    return {
      lat: parseFloat(decMatch[1]),
      lng: parseFloat(decMatch[2]),
    };
  }

  return null;
}

/**
 * Calculate straight line distance (Haversine formula) in kilometers and nautical miles
 */
export function calculateStraightDistance(
  lat1: number,
  lon1: number,
  lat2: number,
  lon2: number
): { km: number; nm: number; bearing: number; headingName: string } {
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

  // Bearing in degrees
  const y = Math.sin(dLon) * Math.cos((lat2 * Math.PI) / 180);
  const x =
    Math.cos((lat1 * Math.PI) / 180) * Math.sin((lat2 * Math.PI) / 180) -
    Math.sin((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.cos(dLat);
  let brng = (Math.atan2(y, x) * 180) / Math.PI;
  brng = (brng + 360) % 360;

  const headings = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
  const headingIdx = Math.round(brng / 45) % 8;

  return {
    km: Math.round(km * 10) / 10,
    nm: Math.round(nm * 10) / 10,
    bearing: Math.round(brng),
    headingName: headings[headingIdx],
  };
}

/**
 * Format duration in minutes into friendly Indonesian string e.g. "1 Jam 30 Menit"
 */
export function formatDurationIndonesian(minutes: number): string {
  const rounded = Math.max(1, Math.round(minutes));
  const hrs = Math.floor(rounded / 60);
  const mins = rounded % 60;
  if (hrs > 0 && mins > 0) {
    return `${hrs} Jam ${mins} Menit`;
  } else if (hrs > 0) {
    return `${hrs} Jam`;
  }
  return `${mins} Menit`;
}

export function dmsPartsToDecimal(deg: number, min: number, sec: number, dir: 'S' | 'N' | 'E' | 'W'): number {
  const d = Math.abs(deg || 0);
  const m = Math.min(59, Math.max(0, min || 0));
  const s = Math.min(59.999, Math.max(0, sec || 0));
  let dec = d + m / 60 + s / 3600;
  if (dir === 'S' || dir === 'W') {
    dec = -dec;
  }
  return dec;
}

export function decimalToDmsParts(val: number, isLat: boolean): { deg: number; min: number; sec: number; dir: 'S' | 'N' | 'E' | 'W' } {
  const dir = isLat ? (val < 0 ? 'S' : 'N') : (val < 0 ? 'W' : 'E');
  const abs = Math.abs(val);
  const deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  const min = Math.floor(minFloat);
  const sec = Math.round((minFloat - min) * 600) / 10;
  return { deg, min, sec, dir };
}

export function getAlphabetLabel(index: number): string {
  const letter = String.fromCharCode(65 + (index % 26));
  const round = Math.floor(index / 26);
  return round > 0 ? `Titik ${letter}${round + 1}` : `Titik ${letter}`;
}

export type PointIconType =
  | 'lkp'
  | 'kantor'
  | 'pos_sar'
  | 'rumah'
  | 'kapal'
  | 'perahu'
  | 'rib'
  | 'orang_hilang'
  | 'kecelakaan_kapal'
  | 'kecelakaan_pesawat'
  | 'bencana'
  | 'waypoint'
  | 'alphabet'
  | 'custom_upload';

export interface LatLngPoint {
  id?: string;
  lat: number;
  lng: number;
  label?: string;
  iconType?: PointIconType;
  pointType?: PointIconType;
  alphabetChar?: string; // A - Z
  customIconUrl?: string; // Data URL or external image for custom uploaded icon
  customColor?: string;
  notes?: string;
  description?: string;
}

export interface SavedDistanceRoute {
  id: string;
  name: string;
  points: LatLngPoint[];
  vesselSpeedKnots: number;
  strokeColor: string;
  strokeWeight: number; // ketebalan garis (1 - 10)
  strokeOpacity: number; // transparansi garis (0.1 - 1.0)
  createdAt?: string;
}

/**
 * Calculate total distance along multiple path points (polyline)
 */
export function calculatePolylineDistance(points: LatLngPoint[]): {
  totalKm: number;
  totalNm: number;
  legs: Array<{ from: LatLngPoint; to: LatLngPoint; km: number; nm: number; bearing: number; headingName: string }>;
} {
  if (points.length < 2) {
    return { totalKm: 0, totalNm: 0, legs: [] };
  }

  let totalKm = 0;
  const legs = [];

  for (let i = 0; i < points.length - 1; i++) {
    const p1 = points[i];
    const p2 = points[i + 1];
    const res = calculateStraightDistance(p1.lat, p1.lng, p2.lat, p2.lng);
    totalKm += res.km;
    legs.push({
      from: p1,
      to: p2,
      km: res.km,
      nm: res.nm,
      bearing: res.bearing,
      headingName: res.headingName,
    });
  }

  const totalNm = Math.round(totalKm * 0.539957 * 100) / 100;
  return {
    totalKm: Math.round(totalKm * 100) / 100,
    totalNm,
    legs,
  };
}

/**
 * Calculate spherical polygon area (in km², NM², and Hectares)
 * Uses spherical excess / Girard's theorem approximation on WGS84
 */
export function calculatePolygonArea(coords: LatLngPoint[]): {
  sqMeters: number;
  sqKm: number;
  sqNm: number;
  hectares: number;
  perimeterKm: number;
  perimeterNm: number;
} {
  if (coords.length < 3) {
    return { sqMeters: 0, sqKm: 0, sqNm: 0, hectares: 0, perimeterKm: 0, perimeterNm: 0 };
  }

  const R = 6378137; // WGS84 Earth radius in meters
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

  const sqKm = totalArea / 1_000_000;
  const sqNm = sqKm * 0.291553; // 1 km² = ~0.291553 NM²
  const hectares = sqKm * 100;

  // Calculate perimeter
  let perimeterKm = 0;
  for (let i = 0; i < coords.length; i++) {
    const p1 = coords[i];
    const p2 = coords[(i + 1) % coords.length];
    perimeterKm += calculateStraightDistance(p1.lat, p1.lng, p2.lat, p2.lng).km;
  }
  const perimeterNm = perimeterKm * 0.539957;

  return {
    sqMeters: Math.round(totalArea),
    sqKm: Math.round(sqKm * 100) / 100,
    sqNm: Math.round(sqNm * 100) / 100,
    hectares: Math.round(hectares * 10) / 10,
    perimeterKm: Math.round(perimeterKm * 100) / 100,
    perimeterNm: Math.round(perimeterNm * 100) / 100,
  };
}

export type SearchPatternType = 'expanding_square' | 'parallel_track' | 'creeping_line' | 'esar_jungle' | 'e_sar';

/**
 * Generate standard IAMSAR & E-SAR Search Pattern Waypoints
 * Can generate either around a datum center OR fitted directly inside an area polygon.
 * 
 * Rules applied:
 * 1. Entry point (Start 'S') is prioritized from the corner closest to the Posko/Base of operations.
 * 2. Track spacing margin (S/2) is applied to all polygon boundaries (left, right, top, bottom).
 * 3. Sweep direction and orientation adjust dynamically to start at that closest entry point.
 */
export function generateSearchPatternWaypoints(
  patternType: SearchPatternType,
  center: { lat: number; lng: number },
  trackSpacingNm: number = 1.0,
  searchRadiusNm: number = 3.0,
  polygonCoords?: LatLngPoint[],
  poskoLocation?: { lat: number; lng: number },
  customLegsCount?: number
): LatLngPoint[] {
  const points: LatLngPoint[] = [];
  const kmPerNm = 1.852;
  const spacingKm = Math.max(0.05, trackSpacingNm * kmPerNm);
  const latKmPerDeg = 110.574;
  const lngKmPerDeg = 111.32 * Math.cos((center.lat * Math.PI) / 180);

  const offsetToCoord = (dEastKm: number, dNorthKm: number, baseLat: number = center.lat, baseLng: number = center.lng): LatLngPoint => ({
    lat: baseLat + dNorthKm / latKmPerDeg,
    lng: baseLng + dEastKm / (111.32 * Math.cos((baseLat * Math.PI) / 180)),
  });

  // CHECK IF GENERATING INSIDE A DEFINED POLYGON
  if (polygonCoords && polygonCoords.length >= 3) {
    let minLat = Infinity;
    let maxLat = -Infinity;
    let minLng = Infinity;
    let maxLng = -Infinity;

    polygonCoords.forEach((p) => {
      if (p.lat < minLat) minLat = p.lat;
      if (p.lat > maxLat) maxLat = p.lat;
      if (p.lng < minLng) minLng = p.lng;
      if (p.lng > maxLng) maxLng = p.lng;
    });

    const midLat = (minLat + maxLat) / 2;
    const midLng = (minLng + maxLng) / 2;
    const polyLngKmPerDeg = 111.32 * Math.cos((midLat * Math.PI) / 180);

    // Track spacing margin (S/2 per standard IAMSAR) applied to left, right, top, and bottom borders
    const marginKm = spacingKm / 2;
    const marginLat = marginKm / latKmPerDeg;
    const marginLng = marginKm / polyLngKmPerDeg;

    let effMinLat = minLat + marginLat;
    let effMaxLat = maxLat - marginLat;
    let effMinLng = minLng + marginLng;
    let effMaxLng = maxLng - marginLng;

    // Safety fallback for very narrow polygons
    if (effMaxLat <= effMinLat) {
      const safeMargin = (maxLat - minLat) * 0.15;
      effMinLat = minLat + safeMargin;
      effMaxLat = maxLat - safeMargin;
    }
    if (effMaxLng <= effMinLng) {
      const safeMargin = (maxLng - minLng) * 0.15;
      effMinLng = minLng + safeMargin;
      effMaxLng = maxLng - safeMargin;
    }

    const effHeightKm = Math.max(0.1, (effMaxLat - effMinLat) * latKmPerDeg);
    const effWidthKm = Math.max(0.1, (effMaxLng - effMinLng) * polyLngKmPerDeg);

    // 4 candidate corners for entry point
    const corners = [
      { id: 'NW', lat: effMaxLat, lng: effMinLng, isNorth: true, isWest: true },
      { id: 'NE', lat: effMaxLat, lng: effMaxLng, isNorth: true, isWest: false },
      { id: 'SW', lat: effMinLat, lng: effMinLng, isNorth: false, isWest: true },
      { id: 'SE', lat: effMinLat, lng: effMaxLng, isNorth: false, isWest: false },
    ];

    // Select entry point closest to Posko (if provided)
    let startCorner = corners[0];
    if (poskoLocation) {
      let minDistance = Infinity;
      for (const corner of corners) {
        const d = calculateStraightDistance(poskoLocation.lat, poskoLocation.lng, corner.lat, corner.lng).km;
        if (d < minDistance) {
          minDistance = d;
          startCorner = corner;
        }
      }
    }

    if (patternType === 'parallel_track') {
      // Parallel Track Search (PS) along long horizontal sweeps
      const calculatedLegs = Math.max(1, Math.round(effHeightKm / spacingKm));
      const numLegs = customLegsCount && customLegsCount >= 1 ? customLegsCount : calculatedLegs;
      const stepY = numLegs > 1 ? (effMaxLat - effMinLat) / (numLegs - 1) : 0;

      for (let i = 0; i < numLegs; i++) {
        const curLat =
          numLegs === 1
            ? (effMaxLat + effMinLat) / 2
            : startCorner.isNorth
            ? effMaxLat - i * stepY
            : effMinLat + i * stepY;
        const sweepStartsWest = startCorner.isWest ? i % 2 === 0 : i % 2 !== 0;

        const pStart: LatLngPoint = {
          lat: curLat,
          lng: sweepStartsWest ? effMinLng : effMaxLng,
          label: i === 0 ? 'Entry Point (S) - Dekat Posko' : `Lintasan ${i + 1}A`,
        };
        const pEnd: LatLngPoint = {
          lat: curLat,
          lng: sweepStartsWest ? effMaxLng : effMinLng,
          label: i === numLegs - 1 ? 'Exit Point (E) - Selesai' : `Lintasan ${i + 1}B`,
        };

        points.push(pStart);
        points.push(pEnd);
      }
      return points;
    } else if (patternType === 'creeping_line') {
      // Creeping Line Search (CS) stepping along horizontal axis with vertical sweeps
      const calculatedLegs = Math.max(1, Math.round(effWidthKm / spacingKm));
      const numLegs = customLegsCount && customLegsCount >= 1 ? customLegsCount : calculatedLegs;
      const stepX = numLegs > 1 ? (effMaxLng - effMinLng) / (numLegs - 1) : 0;

      for (let i = 0; i < numLegs; i++) {
        const curLng =
          numLegs === 1
            ? (effMaxLng + effMinLng) / 2
            : startCorner.isWest
            ? effMinLng + i * stepX
            : effMaxLng - i * stepX;
        const sweepStartsNorth = startCorner.isNorth ? i % 2 === 0 : i % 2 !== 0;

        const pStart: LatLngPoint = {
          lat: sweepStartsNorth ? effMaxLat : effMinLat,
          lng: curLng,
          label: i === 0 ? 'Entry Point (S) - Dekat Posko' : `Lintasan ${i + 1}A`,
        };
        const pEnd: LatLngPoint = {
          lat: sweepStartsNorth ? effMinLat : effMaxLat,
          lng: curLng,
          label: i === numLegs - 1 ? 'Exit Point (E) - Selesai' : `Lintasan ${i + 1}B`,
        };

        points.push(pStart);
        points.push(pEnd);
      }
      return points;
    } else if (patternType === 'esar_jungle' || patternType === 'e_sar') {
      // E-SAR (Hutan / Darat): High-density sweeps with entry closest to basecamp/posko
      const eSarSpacingKm = Math.min(spacingKm, 0.3); // max 300m for jungle search
      const calculatedSweeps = Math.max(1, Math.min(30, Math.round(effHeightKm / eSarSpacingKm)));
      const numSweeps = customLegsCount && customLegsCount >= 1 ? customLegsCount : calculatedSweeps;
      const stepY = numSweeps > 1 ? (effMaxLat - effMinLat) / (numSweeps - 1) : 0;

      for (let i = 0; i < numSweeps; i++) {
        const curLat =
          numSweeps === 1
            ? (effMaxLat + effMinLat) / 2
            : startCorner.isNorth
            ? effMaxLat - i * stepY
            : effMinLat + i * stepY;
        const sweepStartsWest = startCorner.isWest ? i % 2 === 0 : i % 2 !== 0;

        const pStart: LatLngPoint = {
          lat: curLat,
          lng: sweepStartsWest ? effMinLng : effMaxLng,
          label: i === 0 ? 'E-SAR Entry (Basecamp/Posko)' : `Sapu Darat #${i + 1}A`,
        };
        const pEnd: LatLngPoint = {
          lat: curLat,
          lng: sweepStartsWest ? effMaxLng : effMinLng,
          label: i === numSweeps - 1 ? 'E-SAR Selesai (Rendezvous)' : `Sapu Darat #${i + 1}B`,
        };

        points.push(pStart);
        points.push(pEnd);
      }
      return points;
    } else {
      // Expanding Square (SS) centered at polygon centroid
      points.push({ lat: midLat, lng: midLng, label: 'Centroid Datum (LKP)' });
      let curX = 0;
      let curY = 0;
      let step = 1;
      let dir = 0; // 0: East, 1: South, 2: West, 3: North
      let leg = 1;
      const maxLegs = customLegsCount && customLegsCount > 1 ? customLegsCount : 12;

      while (leg <= maxLegs) {
        const legLen = step * spacingKm;
        if (dir === 0) curX += legLen;
        else if (dir === 1) curY -= legLen;
        else if (dir === 2) curX -= legLen;
        else if (dir === 3) curY += legLen;

        points.push({
          ...offsetToCoord(curX, curY, midLat, midLng),
          label: `Leg ${leg}`,
        });

        if (leg % 2 === 0) step += 1;
        dir = (dir + 1) % 4;
        leg++;
      }
      return points;
    }
  }

  // STANDARD GENERATION AROUND DATUM / CENTER
  if (patternType === 'expanding_square') {
    // Expanding Square Search (SS)
    points.push({ ...center, label: 'Datum (LKP)' });
    let currentX = 0;
    let currentY = 0;
    let step = 1;
    let legCount = 1;
    let dir = 0; // Directions: 0: East, 1: South, 2: West, 3: North
    const maxLegs = 12;

    while (legCount <= maxLegs) {
      const legLength = step * spacingKm;
      if (dir === 0) currentX += legLength;
      else if (dir === 1) currentY -= legLength;
      else if (dir === 2) currentX -= legLength;
      else if (dir === 3) currentY += legLength;

      points.push({
        ...offsetToCoord(currentX, currentY),
        label: `Leg ${legCount}`,
      });

      if (legCount % 2 === 0) {
        step += 1;
      }
      dir = (dir + 1) % 4;
      legCount++;
    }
  } else if (patternType === 'parallel_track') {
    // Parallel Track Search (PS)
    const radiusKm = searchRadiusNm * kmPerNm;
    const widthKm = radiusKm * 1.5;
    const heightKm = radiusKm * 2;
    const startX = -widthKm / 2;
    const startY = heightKm / 2;
    const numLegs =
      customLegsCount && customLegsCount >= 1
        ? customLegsCount
        : Math.max(1, Math.round(heightKm / spacingKm));

    for (let i = 0; i < numLegs; i++) {
      const y = startY - i * spacingKm;
      if (i % 2 === 0) {
        points.push({ ...offsetToCoord(startX, y), label: `Lintasan ${i + 1}A` });
        points.push({ ...offsetToCoord(startX + widthKm, y), label: `Lintasan ${i + 1}B` });
      } else {
        points.push({ ...offsetToCoord(startX + widthKm, y), label: `Lintasan ${i + 1}A` });
        points.push({ ...offsetToCoord(startX, y), label: `Lintasan ${i + 1}B` });
      }
    }
  } else if (patternType === 'creeping_line') {
    // Creeping Line Search (CS)
    const radiusKm = searchRadiusNm * kmPerNm;
    const widthKm = radiusKm * 2;
    const heightKm = radiusKm * 1.2;
    const startX = -widthKm / 2;
    const startY = -heightKm / 2;
    const numLegs =
      customLegsCount && customLegsCount >= 1
        ? customLegsCount
        : Math.max(1, Math.round(widthKm / spacingKm));

    for (let i = 0; i < numLegs; i++) {
      const x = startX + i * spacingKm;
      if (i % 2 === 0) {
        points.push({ ...offsetToCoord(x, startY), label: `Lintasan ${i + 1}A` });
        points.push({ ...offsetToCoord(x, startY + heightKm), label: `Lintasan ${i + 1}B` });
      } else {
        points.push({ ...offsetToCoord(x, startY + heightKm), label: `Lintasan ${i + 1}A` });
        points.push({ ...offsetToCoord(x, startY), label: `Lintasan ${i + 1}B` });
      }
    }
  } else if (patternType === 'esar_jungle' || patternType === 'e_sar') {
    // E-SAR (Hutan & Gunung / Wilderness Sweep Search)
    const radiusKm = Math.min(searchRadiusNm * kmPerNm, 3.0); // dense perimeter
    const eSarSpacingKm = Math.min(spacingKm, 0.25); // ~250m interval
    const startX = -radiusKm;
    const startY = radiusKm;
    const numLegs =
      customLegsCount && customLegsCount >= 1
        ? customLegsCount
        : Math.max(1, Math.min(20, Math.round((radiusKm * 2) / eSarSpacingKm)));

    for (let i = 0; i < numLegs; i++) {
      const y = startY - i * eSarSpacingKm;
      if (i % 2 === 0) {
        points.push({ ...offsetToCoord(startX, y), label: `Sapu Darat #${i + 1}A` });
        points.push({ ...offsetToCoord(startX + radiusKm * 2, y), label: `Sapu Darat #${i + 1}B` });
      } else {
        points.push({ ...offsetToCoord(startX + radiusKm * 2, y), label: `Sapu Darat #${i + 1}A` });
        points.push({ ...offsetToCoord(startX, y), label: `Sapu Darat #${i + 1}B` });
      }
    }
  }

  return points;
}
