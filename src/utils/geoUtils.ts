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
