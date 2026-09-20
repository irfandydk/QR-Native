import { KategoriBra, BraSar, BraSection, Operasi } from '../types';

/**
 * Converts a Javascript Date into Basarnas Military TW (Tanggal Waktu)
 * Format: MMDD HHmm H (e.g., "0920 0700 H")
 * Where:
 * 09 = Bulan (2 digit)
 * 20 = Tanggal (2 digit)
 * 07 = Jam (2 digit, WITA)
 * 00 = Menit (2 digit)
 * H = Zona Waktu Indonesia Tengah (Hotel / UTC+8)
 */
export function formatMilitaryTW(date: Date = new Date(), timezoneCode: 'H' | 'WITA' | 'G' | 'I' = 'H'): string {
  // Ensure we compute in WITA (UTC+8)
  const utc = date.getTime() + date.getTimezoneOffset() * 60000;
  const witaDate = new Date(utc + 8 * 3600000);

  const mm = String(witaDate.getMonth() + 1).padStart(2, '0');
  const dd = String(witaDate.getDate()).padStart(2, '0');
  const hh = String(witaDate.getHours()).padStart(2, '0');
  const min = String(witaDate.getMinutes()).padStart(2, '0');

  const tz = timezoneCode === 'WITA' ? 'H' : timezoneCode;
  return `${mm}${dd} ${hh}${min} ${tz}`;
}

/**
 * Formats a Date to Indo formal date string (e.g. "20 September 2026")
 */
export function formatIndoDate(date: Date = new Date()): string {
  const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
  ];
  return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
}

/**
 * Calculates current Month and Year suffix: MMYY (e.g. "0926" for Sept 2026)
 */
export function getBulanTahunSuffix(date: Date = new Date()): string {
  const mm = String(date.getMonth() + 1).padStart(2, '0');
  const yy = String(date.getFullYear()).slice(-2);
  return `${mm}${yy}`;
}

/**
 * Counts total words in BRA telegram message body
 */
export function countBraWords(sections: BraSection[], additionalText: string = ''): number {
  const combined = sections.map(s => `${s.kode} ${s.isi}`).join(' ') + ' ' + additionalText;
  const words = combined.trim().split(/\s+/).filter(Boolean);
  return words.length;
}

/**
 * Next section code generator:
 * 0 -> 'AAA', 1 -> 'BBB', 2 -> 'CCC', 3 -> 'DDD', 4 -> 'EEE', ...
 */
export function getSectionCode(index: number): string {
  const charCode = 65 + (index % 26);
  const letter = String.fromCharCode(charCode);
  return letter.repeat(3);
}

/**
 * Generate sections from Renops (Rencana Operasi)
 */
export function buildBraSectionsFromRenops(
  operasi: Operasi,
  renops: any,
  twbStr: string = formatMilitaryTW(new Date(Date.now() - 15 * 60000), 'H'),
  twuStr: string = formatMilitaryTW(new Date(), 'H')
): { sections: BraSection[]; perihal: string; aksi: string; info: string } {
  const hariKe = renops.hari_ke || 1;
  const namaOps = (operasi.nama_operasi || 'OPERASI SAR').toUpperCase();
  const perihal = `RENCANA OPERASI SAR HARI KE ${hariKe} ${namaOps} TTK`;

  // SRU Formatted
  let sruText = '';
  if (renops.sru && Array.isArray(renops.sru) && renops.sru.length > 0) {
    sruText = renops.sru.map((s: any, idx: number) => {
      const parts = [];
      if (s.nama_sru) parts.push(`* ${s.nama_sru.toUpperCase()}`);
      if (s.pola_pencarian) parts.push(`POLA: ${s.pola_pencarian.toUpperCase()}`);
      if (s.luas_area) parts.push(`LUAS: ${s.luas_area.toUpperCase()}`);
      if (s.koordinat_area) parts.push(`AREA/KOORDINAT: ${s.koordinat_area.toUpperCase()}`);
      return parts.join(' KMA ');
    }).join('\n');
  } else {
    sruText = `* SRU 1: POLA PARALLEL TRACK LUAS 10 NM² DISEPUTARAN LKP\n* SRU 2: PENYISIRAN PESISIR PANTAI`;
  }

  // Unsur
  const unsurList = (renops.detail_unsur && renops.detail_unsur.length > 0)
    ? renops.detail_unsur
    : (operasi.detail_unsur || []);
  const unsurFormatted = unsurList.length > 0
    ? unsurList.map((u: any) => `* ${u.nama_unsur.toUpperCase()} : ${u.jumlah_personil} ORANG`).join('\n')
    : `* RESCUER KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG\n* MASYARAKAT : 5 ORANG`;

  // Alut
  const alutList = (renops.detail_alut && renops.detail_alut.length > 0)
    ? renops.detail_alut
    : (operasi.detail_alut || []);
  const alutFormatted = alutList.length > 0
    ? alutList.map((a: any) => `• ${a.jumlah_alut} ${a.satuan.toUpperCase()} ${a.nama_alut.toUpperCase()}`).join('\n')
    : `• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT\n• 1 SET PALSAR KOMUNIKASI`;

  const cuaca = (renops.cuaca || operasi.cuaca || 'CERAH BERAWAN').toUpperCase();
  const gelombang = (renops.tinggi_gelombang || operasi.gelombang || '0.5 - 1.25 M').toUpperCase();
  const angin = (renops.arah_angin || operasi.angin || 'TIMUR LAUT - TENGGARA, 5-15 KNOTS').toUpperCase();

  const sections: BraSection[] = [
    {
      kode: 'AAA',
      isi: `PD TW ${twbStr} TIM SAR GABUNGAN MELAKSANAKAN BRIEFING PEMBAGIAN SEKTOR PENCARIAN RENCANA OPERASI SAR HARI KE ${hariKe} TERHADAP ${namaOps} DENGAN WAKTU MULAI PERGERAKAN TW ${twuStr} TTK`,
    },
    {
      kode: 'BBB',
      isi: `RENCANA PEMBAGIAN SRU TTK DUA\n${sruText}\n\nKONDISI METEOROLOGI / CUACA TTK DUA\n• CUACA : ${cuaca}\n• ANGIN : ${angin}\n• GELOMBANG : ${gelombang}`,
    },
    {
      kode: 'CCC',
      isi: `UNSUR SAR TERLIBAT TTK DUA\n${unsurFormatted}\n\nALUT DAN PALSAR TTK DUA\n${alutFormatted}`,
    },
    {
      kode: 'DDD',
      isi: `DMKN SBG RENCANA OPERASI HARI KE ${hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`,
    },
  ];

  return {
    sections,
    perihal,
    aksi: 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    info: 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
  };
}

/**
 * Generate sections from Sitrep (Laporan Perkembangan)
 */
export function buildBraSectionsFromSitrep(
  operasi: Operasi,
  sitrep: any,
  twbStr: string = formatMilitaryTW(new Date(Date.now() - 15 * 60000), 'H'),
  twuStr: string = formatMilitaryTW(new Date(), 'H')
): { sections: BraSection[]; perihal: string; aksi: string; info: string } {
  const hariKe = sitrep.hari_ke || 1;
  const namaOps = (operasi.nama_operasi || 'OPERASI SAR').toUpperCase();
  const perihal = `LAPORAN PERKEMBANGAN HARI KE ${hariKe} ${namaOps} TTK`;

  // Status korban
  const selamat = sitrep.korban_selamat_update !== undefined ? sitrep.korban_selamat_update : (operasi.korban_selamat || 0);
  const md = sitrep.korban_meninggal_update !== undefined ? sitrep.korban_meninggal_update : (operasi.korban_meninggal || 0);
  const dp = sitrep.korban_hilang_update !== undefined ? sitrep.korban_hilang_update : (operasi.korban_hilang || 0);

  // Unsur & Alut
  const unsurList = (sitrep.detail_unsur && sitrep.detail_unsur.length > 0)
    ? sitrep.detail_unsur
    : (operasi.detail_unsur || []);
  const unsurFormatted = unsurList.length > 0
    ? unsurList.map((u: any) => `* ${u.nama_unsur.toUpperCase()} : ${u.jumlah_personil} ORANG`).join('\n')
    : `* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG\n* MASYARAKAT : 5 ORANG`;

  const alutList = (sitrep.detail_alut && sitrep.detail_alut.length > 0)
    ? sitrep.detail_alut
    : (operasi.detail_alut || []);
  const alutFormatted = alutList.length > 0
    ? alutList.map((a: any) => `• ${a.jumlah_alut} ${a.satuan.toUpperCase()} ${a.nama_alut.toUpperCase()}`).join('\n')
    : `• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT`;

  const isiLaporan = sitrep.isi_laporan
    ? sitrep.isi_laporan.toUpperCase()
    : `PENCARIAN HARI KE ${hariKe} TELAH DILAKSANAKAN SESUAI RENCANA OPERASI DENGAN HASIL SEMENTARA KORBAN BELUM DITEMUKAN (NIHIL)`;

  const sections: BraSection[] = [
    {
      kode: 'AAA',
      isi: `PD TW ${twbStr} TERIMA INFO DARI OSC TIM SAR GABUNGAN BAHWA ${isiLaporan} TTK`,
    },
    {
      kode: 'BBB',
      isi: `DATA PERKEMBANGAN KORBAN TTK DUA\n* KORBAN SELAMAT : ${selamat} ORANG\n* KORBAN MENINGGAL DUNIA : ${md} ORANG\n* KORBAN DALAM PENCARIAN (DP) : ${dp} ORANG\n* TOTAL POB : ${operasi.pob || (selamat + md + dp)} ORANG`,
    },
    {
      kode: 'CCC',
      isi: `UNSUR SAR TERLIBAT TTK DUA\n${unsurFormatted}\n\nALUT DAN PALSAR TTK DUA\n${alutFormatted}`,
    },
    {
      kode: 'DDD',
      isi: sitrep.status_operasi === 'SELESAI'
        ? `OPERASI SAR DINYATAKAN DITUTUP DAN SELURUH UNSUR DIKEMBALIKAN KE KESATUAN MASING-MASING KMA UCAPAN TERIMA KASIH ATAS KERJASAMANYA TTK HBS`
        : `PD TW ${twuStr} PENCARIAN DIHENTIKAN SEMENTARA DENGAN HASIL NIHIL KMA OPERASI AKAN DILANJUTKAN KEMBALI BESOK PAGI PADA TW 0700 H TTK`,
    },
    {
      kode: 'EEE',
      isi: `DMKN SBG LAPORAN PERKEMBANGAN HARI KE ${hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`,
    },
  ];

  return {
    sections,
    perihal,
    aksi: 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    info: 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
  };
}

/**
 * Default templates based on the official Basarnas documents
 */
export function getDefaultBraTemplate(
  kategori: KategoriBra,
  nextNoBra: string,
  nomorUrut: number,
  satkerKode: string = 'SAR-304',
  bulanTahun: string = getBulanTahunSuffix(),
  operasi?: Operasi | null
): Omit<BraSar, 'id_bra'> {
  const now = new Date();
  const twu = formatMilitaryTW(now, 'H');
  const twb = formatMilitaryTW(new Date(now.getTime() - 15 * 60000), 'H'); // 15 mins before TWU

  if (kategori === 'MAPEL') {
    // BRA Mapel (Pemantauan dan Pemapelan Kapal)
    const namaKorban = operasi?.korban?.[0]?.nama_korban || 'LA SARDIN';
    const umurKorban = operasi?.korban?.[0]?.umur || '46 TAHUN';
    const jkKorban = operasi?.korban?.[0]?.jk === 'Perempuan' ? 'WANITA' : 'LAKIS';
    const lokasi = operasi?.lokasi_kejadian || 'PERAIRAN ANTARA PANTAI BAHARI DAN PULAU BATU ATAS KAB BUTON SELATAN';
    const koordinat = operasi?.koordinat || "06° 01' 06.87\" S - 122° 42' 38.76\" E";
    const kronologi = operasi?.kronologi || 'MEMANCING TUNA MENGGUNAKAN LONGBOAT WARNA PUTIH KMA UNGU';

    const defaultSections: BraSection[] = [
      {
        kode: 'AAA',
        isi: `PD TW ${twb} TERIMA INFO DARI ${operasi?.nama_pelapor?.toUpperCase() || 'IBU NURSIN'} (KELUARGA KORBAN/ HP ${operasi?.no_telp_pelapor || '0822-5204-4012'}) BAHWA PD TW ${twb} KELUARGANYA ATAS NAMA ${namaKorban} (${jkKorban}/${umurKorban}) BELUM KEMBALI DARI ${kronologi.toUpperCase()} PADA LKP DUGA DI ${lokasi.toUpperCase()} PD PERKIRAAN KOORDINAT ${koordinat} KMA HINGGA SAAT INI KORBAN MASIH DALAM PENCARIAN OLEH TIM RESCUE POS SAR KMA TTK`,
      },
      {
        kode: 'BBB',
        isi: `TERKAIT INFO TSB MHN BANTUAN PEMANTAUAN DAN PEMAPELAN KEPADA KAPALS YANG MELINTASI DI AREA ${lokasi.toUpperCase()} DAN PERAIRAN SEKITARNYA KMA APABILA MENEMUKAN KORBAN TERSEBUT AGAR DAPAT MEMBERIKAN PERTOLONGAN SERTA MELAPORKAN KPD BASARNAS KENDARI PADA KESEMPATAN PERTAMA TTK`,
      },
      {
        kode: 'CCC',
        isi: `DEMIKIAN SEBAGAI LAPORAN KMA ATAS KERJASAMANYA KAMI UCAPKAN TERIMA KASIH KMA KA KPP KENDARI KRM TTK HBS`,
      },
    ];

    return {
      kategori: 'MAPEL',
      no_bra: nextNoBra,
      nomor_urut: nomorUrut,
      satker_kode: satkerKode,
      bulan_tahun: bulanTahun,
      id_operasi: operasi?.id_operasi || null,
      nama_operasi: operasi?.nama_operasi || 'PEMAPELAN KMM 1 ORANG NELAYAN BELUM KEMBALI DARI MELAUT',
      panggilan: '',
      jenis: '',
      nr_stn: '',
      derajat: 'SGR',
      instruksi_penerusan: '',
      dari: 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
      aksi: 'KA KSOP KENDARI KMA KA KSOP BAU BAU DAN KA SROP BAU BAU',
      info: 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
      sifat: 'SEGERA',
      menbra: '',
      perihal: operasi?.nama_operasi ? `PEMAPELAN ${operasi.nama_operasi.toUpperCase()} TTK` : 'PEMAPELAN KECELAKAAN KAPAL / KMM TTK',
      tanggal_waktu: formatIndoDate(now),
      twu,
      twb,
      jumlah_kata: `${countBraWords(defaultSections)} KATA`,
      sections: defaultSections,
      pengirim: 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
      nama_smc: 'AMIRUDDIN AS, S.Sos.',
      pangkat_smc: 'PEMBINA (IV/a)',
      jabatan_smc: 'KA KPP KENDARI',
      twt: '',
      twk: '',
      sistem: '',
      paraf: '',
      created_at: now.toISOString(),
    };
  } else if (kategori === 'SAR_UMUM') {
    // If an operation is selected, check if it has a latest Renops or Sitrep!
    let smartSections: BraSection[] = [];
    let smartPerihal = operasi?.nama_operasi || 'KECELAKAAN KAPAL MOB 1 ORANG HILANG DI PERAIRAN TELUK PASARWAJO';
    let smartAksi = 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';
    let smartInfo = 'POLRES DAN BPBD SETEMPAT';

    const latestRenops = (operasi?.renops && operasi.renops.length > 0)
      ? operasi.renops[operasi.renops.length - 1]
      : null;
    const latestSitrep = (operasi?.laporan && operasi.laporan.length > 0)
      ? (operasi.laporan as any)[(operasi.laporan as any).length - 1]
      : null;

    if (latestSitrep) {
      const res = buildBraSectionsFromSitrep(operasi!, latestSitrep, twb, twu);
      smartSections = res.sections;
      smartPerihal = res.perihal;
      smartAksi = res.aksi;
      smartInfo = res.info;
    } else if (latestRenops) {
      const res = buildBraSectionsFromRenops(operasi!, latestRenops, twb, twu);
      smartSections = res.sections;
      smartPerihal = res.perihal;
      smartAksi = res.aksi;
      smartInfo = res.info;
    } else {
      // Unsur terlibat list
      const unsurListFormatted = operasi?.detail_unsur && operasi.detail_unsur.length > 0
        ? operasi.detail_unsur.map(u => `* ${u.nama_unsur.toUpperCase()} : ${u.jumlah_personil} ORANG`).join('\n')
        : `* SMC DAN STAF : 7 ORANG\n* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 4 ORANG\n* TNI AL : 2 ORANG\n* BPBD : 5 ORANG\n* MASYARAKAT : 5 ORANG`;

      // Alut list
      const alutListFormatted = operasi?.detail_alut && operasi.detail_alut.length > 0
        ? operasi.detail_alut.map(a => `• ${a.jumlah_alut} ${a.satuan.toUpperCase()} ${a.nama_alut.toUpperCase()}`).join('\n')
        : `• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT\n• 1 UNIT AQUA EYE\n• 1 SET PALSAR MEDIS\n• 1 SET PALSAR KOMUNIKASI\n• 4 UNIT LONGBOAT MASYARAKAT`;

      const cuaca = operasi?.cuaca ? operasi.cuaca.toUpperCase() : 'CERAH BERAWAN';

      smartSections = [
        {
          kode: 'AAA',
          isi: `PD TW ${twb} TERIMA INFORMASI DARI OSC TIM SAR GABUNGAN MELAKSANAKAN BRIEFING SELANJUTNYA MELAKSANAKAN PENCARIAN TERHADAP ${operasi?.nama_operasi ? operasi.nama_operasi.toUpperCase() : 'KORBAN HILANG'}\nPADA TW ${twu} TIM MELAKSANAKAN PENCARIAN SEKALIGUS BERKOORDINASI DENGAN NELAYAN SETEMPAT PADA TW 1200 H TIM ISTIRAHAT SHOLAT DAN MAKAN KMA SELANJUTNYA PENCARIAN DILANJUTKAN PADA TW 1300 H TTK`,
        },
        {
          kode: 'BBB',
          isi: `UNSUR SAR TERLIBAT :\n${unsurListFormatted}\n\nALUT DAN PALSAR :\n${alutListFormatted}\n\nKONDISI CUACA :\n• CUACA: ${cuaca}`,
        },
        {
          kode: 'CCC',
          isi: `PD TW ${twu} PENCARIAN TERHADAP KORBAN MASIH NIHIL DAN TIM SAR GABUNGAN MELAKSANAKAN BRIEFING EVALUASI SEKALIGUS PENUTUPAN SEMENTARA OPERASI SAR. DAN PENCARIAN AKAN DILANJUTKAN KEMBALI BESOK PADA TW 0700 H TTK`,
        },
        {
          kode: 'DDD',
          isi: `DMKN SBG LAPORAN KMA KA KPP KENDARI SELAKU SMC KIRIM TTK HBS`,
        },
      ];
    }

    return {
      kategori: 'SAR_UMUM',
      no_bra: nextNoBra,
      nomor_urut: nomorUrut,
      satker_kode: satkerKode,
      bulan_tahun: bulanTahun,
      id_operasi: operasi?.id_operasi || null,
      nama_operasi: smartPerihal,
      panggilan: '',
      jenis: '',
      nr_stn: '',
      derajat: 'SGR',
      instruksi_penerusan: '',
      dari: 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
      aksi: smartAksi,
      info: smartInfo,
      sifat: '',
      menbra: '',
      perihal: smartPerihal,
      tanggal_waktu: formatIndoDate(now),
      twu,
      twb,
      jumlah_kata: `${countBraWords(smartSections)} KATA`,
      sections: smartSections,
      pengirim: 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
      nama_smc: 'AMIRUDDIN AS., S.Sos.',
      pangkat_smc: 'PEMBINA (IV/a)',
      jabatan_smc: 'SMC',
      twt: '',
      twk: '',
      sistem: '',
      paraf: '',
      created_at: now.toISOString(),
    };
  } else {
    // BRA PERGERAKAN KAPAL (Lampiran Resmi: Pelayaran Kapal SAR / KN Pacitan / Rescue Boat)
    const perihal = operasi?.nama_operasi 
      ? `PELAYARAN KN SAR PACITAN 102 KENDARI DALAM RANGKA BKO OPERASI SAR ${operasi.nama_operasi.toUpperCase()} TTK`
      : 'PELAYARAN KN SAR PACITAN 102 KENDARI DALAM RANGKA BKO OPRASI SAR KAPAL TENGGELAM KM VIRGO TRANSPORT 8 DISEPUTARAN LAUT JAWA - BANJARMASIN TTK';

    const defaultSections: BraSection[] = [
      {
        kode: 'AAA',
        isi: `PD TW ${twb} KMA PELAYARAN KN SAR PACITAN 102 KENDARI DALAM RANGKA BKO OPERASI SAR KAPAL TENGGELAM KM VIRGO TRANSPORT 8 DISEPUTARAN LAUT JAWA - BANJARMASIN TTK`,
      },
      {
        kode: 'BBB',
        isi: `DATA KAPAL TTK DUA
NAMA : KN.SAR PACITAN 102
NAKHODA : RUSLAN, S.T
LOA : 59,80 M
TYPE : ALUMINIUM
TAHUN : 2013
ENDURANCE : 47 JAM /69,84%
SPEED MAX : 20 KNOT
SPEED ECO : 15 KNOT

REN LAYAR TTK DUA
RUTE : DERMAGA UTAMA BASARNAS KAWASAN TIMUR KMA KENDARI KMA MENUJU PERAIRAN LAUT JAWA - BANJARMASIN DENGAN KETERANGAN TTK DUA
HEADING : 266°
JARAK TEMPUH : 620 NM
ESTIMASI PERGERAKAN 45 JAM
ETD : ${formatIndoDate(now).toUpperCase()} - PKL. 20.45 WITA
ETA : ${formatIndoDate(new Date(now.getTime() + 48 * 3600000)).toUpperCase()} - PKL. 15.45 WIB

MUATAN POB TTK DUA
CREW KN SAR PACITAN : 20 ORANG
RESCUER : 13 ORANG KMA SEBAGAI BERIKUT TTK DUA
1. (OSC) 1 ORG
2. PENYELAM 7 ORG
3. PILOT DROWN 1 ORG
4. RESCUE 4 ORG
KADET : 4 ORANG
MEKANIK : 2 ORANG
ELTRECION : 1 ORANG
TOTAL POB : 40 ORANG

DATA PALSAR ALKOM , ALNAV & ALAT KESELAMATAN TTK DUA
SEA RIDER 1 UNIT ( 40 PK ) READY
SEKOCI 2 UNIT ( 40 PK ) READY
RUBBER BOAT 1 UNIT ( 25 PK ) READY
GPS TRACKING : 1 UNIT READY
RUGGEAR : 1 UNIT READY
STARLINK : 1 UNIT READY
DRONE : 1 UNIT READY
ALAT SELAM : 4 SET READY
KANTONG JENAZAH : 130 BH READY
ALAT MEDIS & EVAKUASI : 1 SET READY
TELP SATELITE : +8821650619537
VHF MARINE : 1 UNIT READY
GPS : 2 UNIT READY
AIS : 1 UNIT READY
RADAR : 1 UNIT READY
ECDIS : 1 UNIT READY
HANDI TALKY : 8 UNIT (HT) READY

DATA CUACA TTK DUA
PRAKICU : BERAWAN
ANGIN : TIMUR - BARAT, KECEP. 5 S/D 20 KM/JAM
GELOMBANG : 0,5 - 1,5 METER
(SUMBER BMKG.COM)

LAINS TTK DUA
MOHON BANTUAN BCC UNTUK PEMANTAUAN MELALUI IMSAR DAN GPS TRACKING TTK`,
      },
      {
        kode: 'CCC',
        isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KDI KRM TTK HBS`,
      },
    ];

    return {
      kategori: 'PERGERAKAN_KAPAL',
      no_bra: nextNoBra,
      nomor_urut: nomorUrut,
      satker_kode: satkerKode,
      bulan_tahun: bulanTahun,
      id_operasi: operasi?.id_operasi || null,
      nama_operasi: perihal,
      panggilan: '',
      jenis: '',
      nr_stn: '',
      derajat: 'SGR',
      instruksi_penerusan: '',
      dari: 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
      aksi: 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
      info: 'DEPUTI OPS DAN KESIAPSIAGAAN KMA DEPUTI SARPRAS DAN SISTEM KOMUNIKASI KMA DIREKTUR SARPRAS DAN DIREKTUR KESIAPSIAGAAN',
      sifat: '',
      menbra: '',
      perihal,
      tanggal_waktu: formatIndoDate(now),
      twu,
      twb,
      jumlah_kata: `${countBraWords(defaultSections)} KATA`,
      sections: defaultSections,
      pengirim: 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
      nama_smc: 'AMIRUDDIN AS, S.Sos',
      pangkat_smc: 'PEMBINA (IV/a)',
      jabatan_smc: 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
      twt: '',
      twk: '',
      sistem: '',
      paraf: '',
      created_at: now.toISOString(),
    };
  }
}

