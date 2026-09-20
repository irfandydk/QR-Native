import { Korban, DetailUnsur, DetailAlut, Renops, RenopsSRU, LaporanHarian, Operasi } from '../types';

export const HARI_INDO = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
export const BULAN_INDO = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

/**
 * Format date to WITA (UTC+8) Indonesian formatted string
 * e.g. "Sabtu, 19 September 2026"
 */
export function formatTanggalIndo(dateInput: string | Date | undefined): string {
  if (!dateInput) return '-';
  const d = new Date(dateInput);
  if (isNaN(d.getTime())) return String(dateInput);

  // Convert to WITA (UTC+8) representation
  const utc = d.getTime() + (d.getTimezoneOffset() * 60000);
  const witaDate = new Date(utc + (3600000 * 8));

  const hari = HARI_INDO[witaDate.getDay()];
  const tanggal = witaDate.getDate();
  const bulan = BULAN_INDO[witaDate.getMonth()];
  const tahun = witaDate.getFullYear();

  return `${hari}, ${tanggal} ${bulan} ${tahun}`;
}

/**
 * Format date to simple "19 September 2026"
 */
export function formatTanggalSimple(dateInput: string | Date | undefined): string {
  if (!dateInput) return '-';
  const d = new Date(dateInput);
  if (isNaN(d.getTime())) return String(dateInput);

  const utc = d.getTime() + (d.getTimezoneOffset() * 60000);
  const witaDate = new Date(utc + (3600000 * 8));

  const tanggal = witaDate.getDate();
  const bulan = BULAN_INDO[witaDate.getMonth()];
  const tahun = witaDate.getFullYear();

  return `${tanggal} ${bulan} ${tahun}`;
}

/**
 * Format time to "HH:mm" WITA
 */
export function formatJamWITA(dateInput: string | Date | undefined): string {
  if (!dateInput) return '00:00';
  const d = new Date(dateInput);
  if (isNaN(d.getTime())) return '00:00';

  const utc = d.getTime() + (d.getTimezoneOffset() * 60000);
  const witaDate = new Date(utc + (3600000 * 8));

  const jam = String(witaDate.getHours()).padStart(2, '0');
  const menit = String(witaDate.getMinutes()).padStart(2, '0');

  return `${jam}:${menit}`;
}

/**
 * Format datetime to Military TW (Tanggal Waktu) standard
 * TW [MMDD] [HHMM] H (H = Hotel / WITA UTC+8)
 * e.g. "TW 0919 0700 H"
 */
export function formatTW(dateInput: string | Date | undefined): string {
  if (!dateInput) return 'TW 0000 0000 H';
  const d = new Date(dateInput);
  if (isNaN(d.getTime())) return 'TW 0000 0000 H';

  const utc = d.getTime() + (d.getTimezoneOffset() * 60000);
  const witaDate = new Date(utc + (3600000 * 8));

  const mm = String(witaDate.getMonth() + 1).padStart(2, '0');
  const dd = String(witaDate.getDate()).padStart(2, '0');
  const hh = String(witaDate.getHours()).padStart(2, '0');
  const min = String(witaDate.getMinutes()).padStart(2, '0');

  return `TW ${mm}${dd} ${hh}${min} H`;
}

/**
 * Calculate H-day from waktu_berangkat (or waktu_lapor) to targetDate
 * Day on departure date is H.1, next day is H.2, etc.
 */
export function hitungHariKe(waktuBerangkat: string | undefined, targetDate: string | Date = new Date()): string {
  if (!waktuBerangkat) return 'H.1';

  const d1 = new Date(waktuBerangkat);
  const d2 = typeof targetDate === 'string' ? new Date(targetDate) : targetDate;
  if (isNaN(d1.getTime()) || isNaN(d2.getTime())) return 'H.1';

  // Normalize to WITA calendar dates (zero out hours)
  const utc1 = d1.getTime() + (d1.getTimezoneOffset() * 60000) + (3600000 * 8);
  const utc2 = d2.getTime() + (d2.getTimezoneOffset() * 60000) + (3600000 * 8);

  const cal1 = new Date(utc1);
  cal1.setHours(0, 0, 0, 0);

  const cal2 = new Date(utc2);
  cal2.setHours(0, 0, 0, 0);

  const diffMs = cal2.getTime() - cal1.getTime();
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));

  const dayNumber = Math.max(1, diffDays + 1);
  return `H.${dayNumber}`;
}

export const TEMPLATE_FOOTER = `Demikian kami laporkan, mohon petunjuk dan arahan

Ttd

KKS Kendari
===========================
🏢 Kantor Pencarian Dan Pertolongan Kendari
📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116
☎️ Telp. : (0401) 3196557
📠 Fax. : (0401) 3196558
📱 WhatsApp : +62811-4034-115
🚨 Emergency Call : 115
🌐 Website : Www.Kendari.Basarnas.go.id
📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id
📲 Medsos : @Basarnaskendari`;

export function generateHeaderWA(judulLaporan: string, namaOperasi: string, tanggal: Date = new Date()): string {
  const tglFormatted = formatTanggalIndo(tanggal);
  return `FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
${judulLaporan.toUpperCase()}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
${tglFormatted}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan ${namaOperasi}.
__________________________________________________`;
}

/**
 * Counts victims by status: S, MD, DP, H
 */
export function hitungStatistikKorban(korbanList: Korban[] = []) {
  let s = 0;
  let md = 0;
  let dp = 0;
  let h = 0;

  korbanList.forEach(k => {
    if (k.status_korban === 'S') s++;
    else if (k.status_korban === 'MD') md++;
    else if (k.status_korban === 'DP') dp++;
    else if (k.status_korban === 'H') h++;
  });

  return { total: korbanList.length, s, md, dp, h };
}

/**
 * Format status korban label
 */
export function getLabelStatusKorban(status: string): string {
  switch (status) {
    case 'S': return 'Selamat (S)';
    case 'MD': return 'Meninggal Dunia (MD)';
    case 'DP': return 'Dalam Pencarian (DP)';
    case 'H': return 'Hilang / Nihil (H)';
    default: return status;
  }
}

/**
 * Generate Laporan Awal WhatsApp Text
 */
export function generateLaporanAwalWA(
  operasi: Partial<Operasi>,
  korbanList: Korban[] = [],
  unsurList: DetailUnsur[] = [],
  alutList: DetailAlut[] = []
): string {
  const header = generateHeaderWA(
    `LAPORAN AWAL OPS SAR`,
    operasi.nama_operasi || 'Operasi SAR',
    operasi.waktu_lapor ? new Date(operasi.waktu_lapor) : new Date()
  );

  const tglLapor = formatTanggalSimple(operasi.waktu_lapor);
  const jamLapor = formatJamWITA(operasi.waktu_lapor);

  const tglBerangkat = formatTanggalSimple(operasi.waktu_berangkat);
  const jamBerangkat = formatJamWITA(operasi.waktu_berangkat);

  const tglEta = formatTanggalSimple(operasi.eta_tiba || operasi.waktu_berangkat);
  const jamEta = formatJamWITA(operasi.eta_tiba || operasi.waktu_berangkat);

  const statsKorban = hitungStatistikKorban(korbanList);

  // List Identitas Korban
  let identitasKorbanText = '';
  if (korbanList.length === 0) {
    identitasKorbanText = '1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -';
  } else {
    identitasKorbanText = korbanList.map((k, idx) => {
      const statusLabel = k.status_korban === 'DP' ? '' : ` (${getLabelStatusKorban(k.status_korban)})`;
      return `${idx + 1}. Nama : ${k.nama_korban}${statusLabel}\n   Umur : ${k.umur || '-'}\n   Jenis Kelamin : ${k.jk || '-'}\n   Alamat : ${k.alamat || '-'}`;
    }).join('\n\n');
  }

  // List Unsur SAR
  let unsurText = '';
  if (unsurList.length === 0) {
    unsurText = '• Tim Rescue KPP Kendari (7 Org)';
  } else {
    unsurText = unsurList.map(u => `• ${u.nama_unsur} (${u.jumlah_personil} Org)`).join('\n');
  }

  // List Alut
  let alutText = '';
  if (alutList.length === 0) {
    alutText = '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat';
  } else {
    alutText = alutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
  }

  const body = `1. Sumber Info kejadian :
${operasi.nama_pelapor || 'Bpk. Pelapor'} (${operasi.hubungan_pelapor || 'Masyarakat'} / No. HP ${operasi.no_telp_pelapor || '-'})
Pada Tanggal ${tglLapor} pukul ${jamLapor} Wita

2. Lokasi Kejadian :
Lokasi : ${operasi.lokasi_kejadian || '-'}
Koordinat : ${operasi.koordinat || '-'}
jarak tempuh : ${operasi.jarak || '-'}
Estimasi waktu tempuh : ${operasi.eta_lokasi || '-'}

3. Pelaksanaan SAR
Pada Tanggal ${tglBerangkat} pukul ${jamBerangkat} Wita, ${operasi.tim_berangkat || 'Tim Rescue Kansar Kendari'} berangkat ke LKP untuk melaksanakan Operasi SAR, dengan estimasi waktu tempuh ETA ${tglEta} pukul ${jamEta} WITA.

4. Kronologi Kejadian
${operasi.kronologi || '-'}

5. Data Korban: ${statsKorban.total} orang
• S : ${statsKorban.s} orang
• MD : ${statsKorban.md} orang
• DP : ${statsKorban.dp} orang
• H : ${statsKorban.h} orang

Identitas Korban :
${identitasKorbanText}

6. Unsur SAR Terlibat
${unsurText}

7. Alut dan Palsar
${alutText}

8. Faktor Penghambat
${operasi.faktor_penghambat || 'Nihil'}

9. Kondisi Cuaca :
• cuaca : ${operasi.cuaca || 'Cerah Berawan'}
• Gelombang : ${operasi.gelombang || '0.5 - 1.25 M'}
• Angin: ${operasi.angin || 'Barat Daya - Barat Laut, 5 - 15 Knots'}`;

  return `${header}\n\n${body}\n\n${TEMPLATE_FOOTER}`;
}

/**
 * Generate Rencana Operasi SAR (Renops H.X) WhatsApp Text
 */
export function generateRenopsWA(
  operasi: Partial<Operasi>,
  renops: Partial<Renops>,
  srus: RenopsSRU[] = [],
  korbanList: Korban[] = [],
  unsurList: DetailUnsur[] = [],
  alutList: DetailAlut[] = []
): string {
  const hariKe = renops.hari_ke || 'H.1';
  const header = `FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
RENCANA OPS SAR ${hariKe.toUpperCase()}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
${formatTanggalIndo(renops.tanggal_laporan ? new Date(renops.tanggal_laporan) : new Date())}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan Rencana Ops ${operasi.nama_operasi || 'Operasi SAR'}.
====================`;

  const twBriefing = formatTW(renops.waktu_briefing);
  const twMulai = formatTW(renops.waktu_mulai_ops);

  // SRUs text
  let sruText = '';
  if (srus.length === 0) {
    sruText = `*SRU 1 Tim Rescue Kansar Kendari melakukan penyisiran dgn koordinat
A: ${operasi.koordinat || '03° 58\' 30" S - 122° 35\' 12" E'}
Luas area pencarian 10 Nm²
Pola pencarian : Parallel Track Search`;
  } else {
    sruText = srus.map((s, idx) => {
      const sruName = s.nama_sru.startsWith('*') ? s.nama_sru : `*${s.nama_sru}`;
      return `${sruName} melakukan penyisiran dgn koordinat
${s.koordinat_area || operasi.koordinat || '-'}
Luas area pencarian ${s.luas_area || '-'}
Pola pencarian : ${s.pola_pencarian || 'Parallel Track Search'}`;
    }).join('\n\n');
  }

  const statsKorban = hitungStatistikKorban(korbanList);

  let identitasKorbanText = '';
  if (korbanList.length === 0) {
    identitasKorbanText = '1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -';
  } else {
    identitasKorbanText = korbanList.map((k, idx) => {
      const statusLabel = k.status_korban === 'DP' ? '' : ` (${getLabelStatusKorban(k.status_korban)})`;
      return `${idx + 1}. Nama : ${k.nama_korban}${statusLabel}\n   Umur : ${k.umur || '-'}\n   Jenis Kelamin : ${k.jk || '-'}\n   Alamat : ${k.alamat || '-'}`;
    }).join('\n\n');
  }

  // Unsur
  let unsurText = '';
  if (unsurList.length === 0) {
    unsurText = '• Tim Rescue Kansar Kendari : 7 Orang';
  } else {
    unsurText = unsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil} Orang`).join('\n');
  }

  // Alut
  let alutText = '';
  if (alutList.length === 0) {
    alutText = '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat';
  } else {
    alutText = alutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
  }

  const body = `Rencana Ops ${hariKe} :

• PD ${twBriefing} Tim SAR Gabungan Melaksanakan Brifing Operasi.
• Pada ${twMulai} Tim SAR Gabungan Melanjutkan Operasi Pencarian ${hariKe} Sesuai Rencana Operasi ${hariKe} dengan Tim SAR gabungan menuju ke entri point masing-masing.
Dengan Tim dibagi ${srus.length || 1} SRU dengan area pencarian sbb :

${sruText}

1. Data Korban: ${statsKorban.total} Orang
• S : ${statsKorban.s} ORANG
• MD : ${statsKorban.md} ORANG
• DP : ${statsKorban.dp} ORANG
• H : ${statsKorban.h} ORANG

2. Identitas Korban
${identitasKorbanText}

3. Unsur SAR Terlibat
${unsurText}

4. Alut dan Palsar
${alutText}

5. Kondisi Cuaca :
• Cuaca: ${renops.cuaca || operasi.cuaca || 'Cerah Berawan'}
(Sumber BMKG)`;

  return `${header}\n\n${body}\n\n${TEMPLATE_FOOTER}`;
}

/**
 * Generate Laporan Perkembangan (Lapbang) atau Penutupan WhatsApp Text
 */
export function generateLapbangWA(
  operasi: Partial<Operasi>,
  laporan: Partial<LaporanHarian>,
  korbanList: Korban[] = [],
  unsurList: DetailUnsur[] = [],
  alutList: DetailAlut[] = [],
  isPenutupan: boolean = false
): string {
  const hariKe = laporan.hari_ke || 'H.1';
  const judul = isPenutupan
    ? `LAPBANG OPS SAR ${hariKe.toUpperCase()} DAN PENUTUPAN`
    : `LAPBANG OPS SAR ${hariKe.toUpperCase()}`;

  const header = `FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
${judul}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
${formatTanggalIndo(laporan.waktu_laporan ? new Date(laporan.waktu_laporan) : new Date())}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan Perkembangan Ops ${operasi.nama_operasi || 'Operasi SAR'}.
__________________________________________________`;

  let klausulPenutupan = '';
  if (isPenutupan) {
    const tglIndo = formatTanggalSimple(laporan.waktu_debrief || laporan.waktu_laporan || new Date());
    const jamDebrief = formatJamWITA(laporan.waktu_debrief || laporan.waktu_laporan || new Date());
    klausulPenutupan = `\n\n• Pada tanggal ${tglIndo} Pukul ${jamDebrief} WITA, Tim SAR Gabungan melakukan debriefing penutupan Ops SAR terhadap ${operasi.nama_operasi || 'korban'}, selanjutnya operasi SAR diusulkan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih.`;
  }

  const statsKorban = hitungStatistikKorban(korbanList);

  let identitasKorbanText = '';
  if (korbanList.length === 0) {
    identitasKorbanText = '1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -';
  } else {
    identitasKorbanText = korbanList.map((k, idx) => {
      return `${idx + 1}. Nama : ${k.nama_korban} [Status: ${getLabelStatusKorban(k.status_korban)}]\n   Umur : ${k.umur || '-'}\n   Jenis Kelamin : ${k.jk || '-'}\n   Alamat : ${k.alamat || '-'}`;
    }).join('\n\n');
  }

  let unsurText = '';
  if (unsurList.length === 0) {
    unsurText = '• Tim Rescue Kansar Kendari : 7 Orang';
  } else {
    unsurText = unsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil} Orang`).join('\n');
  }

  let alutText = '';
  if (alutList.length === 0) {
    alutText = '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat';
  } else {
    alutText = alutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
  }

  const body = `Hasil Ops SAR ${hariKe}:

${laporan.isi_laporan || 'Pencarian telah dilaksanakan oleh Tim SAR Gabungan sesuai Renops.'}${klausulPenutupan}

1. Data Korban: ${statsKorban.total} Orang
• S : ${statsKorban.s} ORANG
• MD : ${statsKorban.md} ORANG
• DP : ${statsKorban.dp} ORANG
• H : ${statsKorban.h} ORANG

2. Identitas Korban
${identitasKorbanText}

3. Unsur SAR Terlibat
${unsurText}

4. Alut dan Palsar
${alutText}`;

  return `${header}\n\n${body}\n\n${TEMPLATE_FOOTER}`;
}
