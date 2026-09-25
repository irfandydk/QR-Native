import { Operasi, Renops, LaporanHarian, DetailUnsur, DetailAlut, Korban, StatusKorban, RenopsSRU } from '../types';
import { hitungHariKe } from './templates';

export interface HierarchyDocument {
  id: string;
  type: 'LAPORAN_AWAL' | 'RENOPS' | 'LAPBANG' | 'PENUTUPAN';
  label: string; // e.g. "Laporan Awal", "Renops H.1", "Lapbang H.1"
  hariKe: string; // "H.1", "H.2", etc.
  dayNumber: number;
  waktu: string; // ISO datetime
  unsur?: DetailUnsur[];
  alut?: DetailAlut[];
  cuaca?: string;
  korban?: Korban[];
}

/**
 * Extracts the integer day number from strings like "H.1", "H.2", "H.10"
 */
export function extractDayNumber(hariKeStr?: string): number {
  if (!hariKeStr) return 1;
  const match = hariKeStr.match(/\d+/);
  return match ? parseInt(match[0], 10) : 1;
}

/**
 * Returns a sorted chronological timeline of all documents in the operation
 */
export function getOperationalTimeline(operasi: Operasi): HierarchyDocument[] {
  const docs: HierarchyDocument[] = [];

  // 1. Base Document: Laporan Awal
  docs.push({
    id: `awal-${operasi.id_operasi}`,
    type: 'LAPORAN_AWAL',
    label: 'Laporan Awal (File Aktif)',
    hariKe: 'H.1',
    dayNumber: 1,
    waktu: operasi.waktu_berangkat || operasi.waktu_lapor || operasi.created_at,
    unsur: operasi.detail_unsur || [],
    alut: operasi.detail_alut || [],
    cuaca: operasi.cuaca || '',
    korban: operasi.korban || [],
  });

  // Collect and sort all Renops and Lapbangs
  const renopsList = Array.isArray(operasi.renops) ? [...operasi.renops] : [];
  const lapbangList = Array.isArray(operasi.laporan_harian)
    ? [...operasi.laporan_harian]
    : Array.isArray(operasi.laporan)
    ? [...operasi.laporan]
    : [];

  // Sort Renops by day
  renopsList.sort((a, b) => {
    const dayA = extractDayNumber(a.hari_ke);
    const dayB = extractDayNumber(b.hari_ke);
    if (dayA !== dayB) return dayA - dayB;
    return new Date(a.created_at || a.waktu_briefing).getTime() - new Date(b.created_at || b.waktu_briefing).getTime();
  });

  // Sort Lapbang by day
  lapbangList.sort((a, b) => {
    const dayA = extractDayNumber(a.hari_ke);
    const dayB = extractDayNumber(b.hari_ke);
    if (dayA !== dayB) return dayA - dayB;
    return new Date(a.waktu_laporan).getTime() - new Date(b.waktu_laporan).getTime();
  });

  // Interleave according to Basarnas SAR operational workflow:
  // For each Day N: Renops H.N (morning plan) -> Lapbang H.N (afternoon/evening report)
  const maxDay = Math.max(
    1,
    ...renopsList.map((r) => extractDayNumber(r.hari_ke)),
    ...lapbangList.map((l) => extractDayNumber(l.hari_ke))
  );

  for (let day = 1; day <= maxDay; day++) {
    const dayRenops = renopsList.filter((r) => extractDayNumber(r.hari_ke) === day);
    const dayLapbangs = lapbangList.filter((l) => extractDayNumber(l.hari_ke) === day);

    dayRenops.forEach((r) => {
      docs.push({
        id: `renops-${r.id_renops}`,
        type: 'RENOPS',
        label: `Renops ${r.hari_ke || `H.${day}`}`,
        hariKe: r.hari_ke || `H.${day}`,
        dayNumber: day,
        waktu: r.waktu_briefing || r.created_at || '',
        unsur: operasi.detail_unsur || [],
        alut: operasi.detail_alut || [],
        cuaca: r.cuaca || '',
      });
    });

    dayLapbangs.forEach((l) => {
      const isPenutupan = l.jenis_laporan === 'PENUTUPAN';
      docs.push({
        id: `lapbang-${l.id_laporan}`,
        type: isPenutupan ? 'PENUTUPAN' : 'LAPBANG',
        label: isPenutupan ? `Lapbang Penutupan (${l.hari_ke || `H.${day}`})` : `Lapbang ${l.hari_ke || `H.${day}`}`,
        hariKe: l.hari_ke || `H.${day}`,
        dayNumber: day,
        waktu: l.waktu_laporan || '',
        unsur: operasi.detail_unsur || [],
        alut: operasi.detail_alut || [],
        cuaca: operasi.cuaca || '',
      });
    });
  }

  return docs;
}

/**
 * Computes default form parameters for a NEW Renops based on latest document in hierarchy
 */
export function getNextRenopsDefaults(operasi: Operasi) {
  const renopsList = Array.isArray(operasi.renops) ? operasi.renops : [];
  const lapbangList = Array.isArray(operasi.laporan_harian)
    ? operasi.laporan_harian
    : Array.isArray(operasi.laporan)
    ? operasi.laporan
    : [];

  const maxRenopsDay = renopsList.reduce((max, r) => Math.max(max, extractDayNumber(r.hari_ke)), 0);
  const maxLapbangDay = lapbangList.reduce((max, l) => Math.max(max, extractDayNumber(l.hari_ke)), 0);

  let targetDayNumber = 1;
  let predecessorLabel = 'Laporan Awal';

  if (maxLapbangDay >= 1) {
    // If Lapbang H.1 exists, next Renops is H.2
    targetDayNumber = maxLapbangDay + 1;
    predecessorLabel = `Lapbang H.${maxLapbangDay}`;
  } else if (maxRenopsDay >= 1) {
    // If Renops H.1 exists without Lapbang H.1, next Renops could be H.2
    targetDayNumber = maxRenopsDay + 1;
    predecessorLabel = `Renops H.${maxRenopsDay}`;
  } else {
    // First Renops
    targetDayNumber = 1;
    predecessorLabel = 'Laporan Awal (File Aktif)';
  }

  const hariKe = `H.${targetDayNumber}`;

  // Target Date calculation based on start time + (targetDayNumber - 1) days
  const baseDate = new Date(operasi.waktu_berangkat || operasi.waktu_lapor || new Date());
  const targetDateObj = new Date(baseDate);
  if (!isNaN(baseDate.getTime())) {
    targetDateObj.setDate(baseDate.getDate() + (targetDayNumber - 1));
  }
  const targetDateStr = !isNaN(targetDateObj.getTime())
    ? targetDateObj.toISOString().split('T')[0]
    : new Date().toISOString().split('T')[0];

  // Inherited Unsur & Alut from operation's latest active state
  const inheritedUnsur: DetailUnsur[] = operasi.detail_unsur ? JSON.parse(JSON.stringify(operasi.detail_unsur)) : [];
  const inheritedAlut: DetailAlut[] = operasi.detail_alut ? JSON.parse(JSON.stringify(operasi.detail_alut)) : [];

  // Inherited Weather: look at last renops or initial
  const lastRenops = renopsList.length > 0 ? renopsList[0] : null;
  const inheritedCuaca = lastRenops?.cuaca || operasi.cuaca || 'Cerah Berawan, Angin Barat Daya - Barat Laut 5 - 15 Knots, Gelombang 0.5 - 1.25 M';

  // Smart default SRUs
  const defaultSrus: Omit<RenopsSRU, 'id_sru' | 'id_renops'>[] = [
    {
      nama_sru: `SRU 1 (Tim Rescue Kansar Kendari)`,
      koordinat_area: `Sektor A: ${operasi.koordinat || '03° 58\' 30" S - 122° 35\' 12" E'}\nPenyisiran sekitar LKP radius ${operasi.jarak || '10 Nm'}`,
      luas_area: `${targetDayNumber * 8 || 10} Nm²`,
      pola_pencarian: 'Parallel Track Search',
    },
    {
      nama_sru: `SRU 2 (Unsur Gabungan & Nelayan)`,
      koordinat_area: `Penyisiran visual pesisir pantai & perairan dangkal ${operasi.lokasi_kejadian || 'sekitar LKP'}`,
      luas_area: `${Math.max(4, targetDayNumber * 4)} Nm²`,
      pola_pencarian: 'Creeping Line Search',
    },
  ];

  return {
    hariKe,
    targetDayNumber,
    targetDateStr,
    inheritedUnsur,
    inheritedAlut,
    inheritedCuaca,
    defaultSrus,
    predecessorLabel,
  };
}

/**
 * Computes default form parameters for a NEW Lapbang based on latest document in hierarchy
 */
export function getNextLapbangDefaults(operasi: Operasi) {
  const renopsList = Array.isArray(operasi.renops) ? operasi.renops : [];
  const lapbangList = Array.isArray(operasi.laporan_harian)
    ? operasi.laporan_harian
    : Array.isArray(operasi.laporan)
    ? operasi.laporan
    : [];

  const maxRenopsDay = renopsList.reduce((max, r) => Math.max(max, extractDayNumber(r.hari_ke)), 0);
  const maxLapbangDay = lapbangList.reduce((max, l) => Math.max(max, extractDayNumber(l.hari_ke)), 0);

  let targetDayNumber = 1;
  let predecessorLabel = 'Laporan Awal';

  if (maxRenopsDay > maxLapbangDay) {
    // e.g. Renops H.2 exists, Lapbang H.1 was last lapbang -> Target Lapbang is H.2
    targetDayNumber = maxRenopsDay;
    predecessorLabel = `Renops H.${maxRenopsDay}`;
  } else if (maxLapbangDay >= 1) {
    // Lapbang H.1 exists without Renops H.2 -> Target Lapbang is H.2
    targetDayNumber = maxLapbangDay + 1;
    predecessorLabel = `Lapbang H.${maxLapbangDay}`;
  } else if (maxRenopsDay === 1) {
    targetDayNumber = 1;
    predecessorLabel = 'Renops H.1';
  } else {
    targetDayNumber = 1;
    predecessorLabel = 'Laporan Awal (File Aktif)';
  }

  const hariKe = `H.${targetDayNumber}`;

  // Inherited Unsur & Alut from operation's latest active state
  const inheritedUnsur: DetailUnsur[] = operasi.detail_unsur ? JSON.parse(JSON.stringify(operasi.detail_unsur)) : [];
  const inheritedAlut: DetailAlut[] = operasi.detail_alut ? JSON.parse(JSON.stringify(operasi.detail_alut)) : [];

  // Inherited Victim Statuses
  const inheritedVictimStatuses: { id_korban: number; nama_korban: string; status_korban: StatusKorban }[] = (operasi.korban || []).map((k) => ({
    id_korban: k.id_korban,
    nama_korban: k.nama_korban,
    status_korban: k.status_korban || 'DP',
  }));

  const suggestedIsiLaporan = `Tim SAR Gabungan telah melaksanakan pencarian Operasi SAR Hari Ke-${targetDayNumber} (${hariKe}) sesuai Rencana Operasi. Hingga sore hari pencarian dihentikan sementara dengan hasil NIHIL dan akan dilanjutkan esok hari (${hariKe === 'H.1' ? 'H.2' : `H.${targetDayNumber + 1}`}) sesuai Renops Harian.`;

  return {
    hariKe,
    targetDayNumber,
    inheritedUnsur,
    inheritedAlut,
    inheritedVictimStatuses,
    suggestedIsiLaporan,
    predecessorLabel,
  };
}
