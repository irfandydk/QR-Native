// Node test for assets/js/bra/bra_templates.js — run: node tests/js/bra_templates.test.js
'use strict';
const assert = require('node:assert/strict');
const T = require('../../assets/js/bra/bra_templates.js');

function test(name, fn) {
  try {
    fn();
    console.log('PASS ' + name);
  } catch (e) {
    process.exitCode = 1;
    console.log('FAIL ' + name + '\n  ' + e.message.split('\n').join('\n  '));
  }
}

// --- Task 1: formatting ---
test('punctuation converted', () => assert.equal(
  T.toSentence('Di Ds. Lalowaru, Kec. Moramo/Konsel.'),
  'DI DS TTK LALOWARU KMA KEC TTK MORAMO KONSEL TTK'));
test('parentheses kept', () => assert.equal(
  T.toSentence('dari Andi (Keluarga Korban/ HP 0812) bahwa'),
  'DARI ANDI (KELUARGA KORBAN/ HP 0812) BAHWA'));
test('dms verbatim', () => assert.equal(
  T.toSentence('pada koordinat {k}.', { k: `03° 58' 30.5" S - 122° 35' 12" E` }),
  `PADA KOORDINAT 03° 58' 30.5" S - 122° 35' 12" E TTK`));
test('decimal kept', () => assert.equal(T.toSentence('jarak 2.5 NM'), 'JARAK 2.5 NM'));
test('militaryTime', () => {
  assert.equal(T.militaryTime('2026-10-28 17:05:00'), '1028 1705 H');
  assert.equal(T.militaryTime(''), '');
});
test('hariNumber', () => ['H.2', 'H-2', '2', 'HARI KE 2'].forEach(v => assert.equal(T.hariNumber(v), 2)));
test('numberedList', () => {
  assert.equal(T.numberedList('UNSUR SAR TERLIBAT', ['A : 1 ORANG', 'B : 2 ORANG']),
    'UNSUR SAR TERLIBAT TTK DUA\n1. A : 1 ORANG\n2. B : 2 ORANG');
  assert.equal(T.numberedList('X', []), '');
});
test('letteredList', () => assert.equal(T.letteredList('AREA', ['P1', 'P2']), 'AREA TTK DUA\nA. P1\nB. P2'));
test('unsur and alut items', () => {
  assert.deepEqual(T.unsurItems([{ nama_unsur: 'Rescuer Kendari', jumlah_personil: 6 }]), ['RESCUER KENDARI : 6 ORANG']);
  assert.deepEqual(T.alutItems([{ nama_alut: 'Rescue Car', jumlah_alut: 1, satuan: 'Unit' }]), ['RESCUE CAR : 1 UNIT']);
});
test('cuacaItems skips empty', () => assert.deepEqual(
  T.cuacaItems({ cuaca: 'Cerah', angin: '', gelombang: '0.5 M' }),
  ['CUACA : CERAH', 'GELOMBANG : 0.5 M']));
test('transport', () => {
  assert.equal(T.isTransportAlut('Rescue Car'), true);
  assert.equal(T.isTransportAlut('RIB 40 PK'), true);
  assert.equal(T.isTransportAlut('Palsar Medis'), false);
  assert.equal(T.isTransportAlut('Kantong Mayat'), false);
});
test('parseSruArea', () => {
  const r = T.parseSruArea("Sektor: S1 (4 NM²)\nBatas Koordinat:\nP1 03° 58' S - 122° 35' E\nP2 03° 59' S - 122° 36' E\n\n[Titik Entry Poin: EP1]\nKoordinat: 03° 58' S - 122° 35' E\n\n[Titik Exit Poin: XP1]\nKoordinat: 03° 59' S - 122° 36' E");
  assert.equal(r.coords.length, 2);
  assert.equal(r.entry, "03° 58' S - 122° 35' E");
  assert.equal(r.exit, "03° 59' S - 122° 36' E");
});
test('formatSectionText keeps list lines', () => assert.equal(
  T.formatSectionText('Unsur SAR Terlibat TTK DUA\n1. Rescuer : 6 orang\nTim bergerak, cepat.'),
  'UNSUR SAR TERLIBAT TTK DUA\n1. RESCUER : 6 ORANG\nTIM BERGERAK KMA CEPAT TTK'));

// --- Task 2: draft builders ---
const AREA = "Sektor: S1 (4 NM²)\nBatas Koordinat:\nP1 03° 58' S - 122° 35' E\nP2 03° 59' S - 122° 36' E\n\n[Titik Entry Poin: EP1]\nKoordinat: 03° 58' S - 122° 35' E\n\n[Titik Exit Poin: XP1]\nKoordinat: 03° 59' S - 122° 36' E";
const op = {
  id_operasi: 7, nama_operasi: 'KMM Bahari Hilang Kontak', jenis_kejadian: 'Kecelakaan Kapal',
  nama_pelapor: 'Andi', hubungan_pelapor: 'Keluarga Korban', no_telp_pelapor: '081234',
  waktu_lapor: '2026-10-28 17:05:00', tim_berangkat: 'Tim Rescue Kansar Kendari',
  lokasi_kejadian: 'Perairan Ds. Lalowaru, Kec. Moramo/Konsel', koordinat: `03° 58' 30.5" S - 122° 35' 12" E`,
  jarak: '25 NM', radial: '135', eta_lokasi: '2 Jam', cuaca: 'Cerah', angin: 'Timur 5 Knot', gelombang: '0.5 M', jumlah_pob: 3,
  korban: [{ status_korban: 'S' }, { status_korban: 'MD' }, { status_korban: 'DP' }],
  detail_unsur: [{ nama_unsur: 'Rescuer Kendari', jumlah_personil: 6 }],
  detail_alut: [
    { nama_alut: 'Rescue Car', jumlah_alut: 1, satuan: 'Unit' },
    { nama_alut: 'RIB', jumlah_alut: 1, satuan: 'Unit' },
    { nama_alut: 'Palsar Medis', jumlah_alut: 1, satuan: 'Set' }
  ],
  detail_tim_berangkat: [{ nama_tim: 'Tim Rescue Kansar Kendari' }],
  renops: [{
    id_renops: 11, hari_ke: 'H.2', waktu_briefing: '2026-10-29 06:30:00', waktu_mulai_ops: '2026-10-29 07:00:00', cuaca: 'Berawan',
    detail_unsur: [], detail_alut: [],
    srus: [{ nama_sru: 'SRU 1', koordinat_area: AREA, luas_area: '4 NM²', pola_pencarian: 'Parallel Track', track_spacing: '0.5 NM' }]
  }],
  laporan_harian: [
    { id_laporan: 21, hari_ke: 'H.2', jenis_laporan: 'PERKEMBANGAN', isi_laporan: 'Pencarian nihil, korban belum ditemukan.', waktu_laporan: '2026-10-29 18:00:00', cuaca: 'Hujan', detail_unsur: [], detail_alut: [] },
    { id_laporan: 22, hari_ke: 'H.3', jenis_laporan: 'PENUTUPAN', isi_laporan: 'Seluruh korban ditemukan.', waktu_laporan: '2026-10-30 17:00:00', waktu_debrief: '2026-10-30 16:30:00', cuaca: 'Cerah', detail_unsur: [], detail_alut: [] }
  ]
};
test('LA BBB uses transport only', () => {
  const bbb = T.buildLaporanAwal(op).sections[1].isi;
  assert.match(bbb, /MENGGUNAKAN 1 UNIT RESCUE CAR DAN 1 UNIT RIB/);
  assert.doesNotMatch(bbb, /MEDIS/);
});
test('LA BBB without transport/jarak stays clean', () => {
  const bbb = T.buildLaporanAwal({ ...op, detail_alut: [], jarak: '', radial: '', eta_lokasi: '' }).sections[1].isi;
  assert.doesNotMatch(bbb, /MENGGUNAKAN|JARAK|RADIAL|ETA/);
  assert.match(bbb, /MENUJU LKP TTK$/);
});
test('LA AAA keeps pelapor parentheses and DMS', () => {
  const aaa = T.buildLaporanAwal(op).sections[0].isi;
  assert.match(aaa, /^PD TW 1028 1705 H TERIMA INFO DARI ANDI \(KELUARGA KORBAN\/ HP 081234\) BAHWA/);
  assert.ok(aaa.includes(`03° 58' 30.5" S - 122° 35' 12" E TTK`));
});
test('LA CCC has 3 lists', () => {
  const ccc = T.buildLaporanAwal(op).sections[2].isi;
  ['UNSUR SAR TERLIBAT TTK DUA', 'ALUT SAR TTK DUA', 'KONDISI CUACA TTK DUA'].forEach(h => assert.ok(ccc.includes(h)));
});
test('no slash or comma in narrative', () => {
  [T.buildLaporanAwal(op), T.buildRenops(op, op.renops[0]), T.buildLapbang(op, op.laporan_harian[0])].forEach(d =>
    d.sections.forEach(s => s.isi.split('\n')
      .filter(l => !/^\s*(\d+|[A-Z])\.\s|TTK DUA$/.test(l))
      .forEach(l => assert.doesNotMatch(l.replace(/\([^)]*\)/g, ''), /[,/]/))));
});
test('renops BBB SRU block', () => {
  const d = T.buildRenops(op, op.renops[0]);
  assert.match(d.sections[1].isi, /^PEMBAGIAN SRU TTK DUA\n1\. SEARCH AREA SRU 1 TTK DUA\nA\. /);
  assert.match(d.sections[1].isi, /POLA PARALLEL TRACK TS 0\.5 NM LUAS 4 NM²/);
  assert.match(d.sections[1].isi, /ENTRY DAN EXIT POINT /);
  assert.match(d.perihal, /HARI KE 2/);
});
test('lapbang has no victim data, penutupan has', () => {
  assert.doesNotMatch(T.buildLapbang(op, op.laporan_harian[0]).sections.map(s => s.isi).join('\n'), /DATA KORBAN/);
  assert.match(T.buildPenutupan(op, op.laporan_harian[1]).sections[2].isi, /SELAMAT : 1 ORANG\n2\. MENINGGAL DUNIA : 1 ORANG\n3\. DALAM PENCARIAN : 1 ORANG/);
});
test('mapel addressing and sections', () => {
  const d = T.buildMapel(op);
  assert.equal(d.aksi, 'KA KSOP KENDARI KMA KA SROP KENDARI');
  assert.equal(d.info, 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN');
  assert.equal(d.sections.length, 3);
  assert.equal(d.kind, 'LANJUTAN');
});
test('referenceOptions', () => {
  const r = T.referenceOptions(op);
  assert.equal(r.renops.length, 1);
  assert.equal(r.renops[0].label, 'HARI KE 2');
  assert.equal(r.lapbang.length, 1);
  assert.ok(r.penutupan);
  assert.deepEqual(T.referenceOptions({ ...op, renops: [], laporan_harian: [] }), { renops: [], lapbang: [], penutupan: null });
});
test('senderTitle', () => {
  assert.equal(T.senderTitle('LAPORAN_AWAL', true, 'KEPALA KANTOR X'), 'KEPALA KANTOR X');
  assert.equal(T.senderTitle('LANJUTAN', true, 'KEPALA KANTOR X'), 'SMC');
  assert.equal(T.senderTitle('LANJUTAN', false, 'KEPALA KANTOR X'), 'KEPALA KANTOR X');
});
test('operasi SAR info empty', () => {
  const d = T.buildLaporanAwal(op);
  assert.equal(d.info, '');
  assert.equal(d.aksi, 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN');
  assert.equal(d.kind, 'LAPORAN_AWAL');
});
test('nama_operasi prefix OPERASI SAR not duplicated', () => {
  const o = { ...op, nama_operasi: 'Operasi SAR Kecelakaan Kapal Longboat' };
  const d = T.buildLaporanAwal(o);
  assert.equal(d.perihal, 'LAPORAN AWAL OPERASI SAR TERHADAP KECELAKAAN KAPAL LONGBOAT TTK');
  assert.match(d.sections[0].isi, /BAHWA TELAH TERJADI KECELAKAAN KAPAL LONGBOAT DI /);
  assert.equal(T.buildMapel({ ...o, nama_operasi: 'Operasi SAR terhadap KM Sinar' }).perihal, 'PERMINTAAN PEMAPELAN DAN PEMANTAUAN TERHADAP KM SINAR TTK');
});
test('sru coords drop stored point labels', () => {
  const r = T.parseSruArea("Sektor: S1 (4 KM²)\nBatas Koordinat:\nA: 03° 57' 54.6\" S - 122° 30' 50.2\" E\nB: 03° 57' 54.6\" S - 122° 31' 55.0\" E");
  assert.deepEqual(r.coords, [`03° 57' 54.6" S - 122° 30' 50.2" E`, `03° 57' 54.6" S - 122° 31' 55.0" E`]);
});
test('pola value without duplicated POLA prefix', () => {
  const sru = { ...op.renops[0].srus[0], pola_pencarian: 'Pola Pencarian E-SAR' };
  const bbb = T.buildRenops(op, { ...op.renops[0], srus: [sru] }).sections[1].isi;
  assert.match(bbb, /\nPOLA E-SAR TS /);
});
test('long indonesian date and time become TW', () => assert.equal(
  T.toSentence('Pada Jumat, 23 Oktober 2026, pukul 06.30 WITA, tim bergerak.'),
  'PADA TW 1023 0630 H KMA TIM BERGERAK TTK'));
test('time without date becomes HHMM H', () => assert.equal(
  T.toSentence('namun pada pukul 09.00 WITA korban ditemukan'),
  'NAMUN PADA PUKUL 0900 H KORBAN DITEMUKAN'));
test('whatsapp markdown removed', () => assert.equal(
  T.toSentence('menggunakan *Rescue Car* menuju _dermaga_'),
  'MENGGUNAKAN RESCUE CAR MENUJU DERMAGA'));
test('nama_operasi trailing dot not doubled', () => assert.equal(
  T.buildLaporanAwal({ ...op, nama_operasi: 'Kecelakaan Kapal di Kab. Konawe.' }).perihal,
  'LAPORAN AWAL OPERASI SAR TERHADAP KECELAKAAN KAPAL DI KAB TTK KONAWE TTK'));
test('penutupan victims: POB above recorded counts as DP, long statuses recognised', () => {
  const o = { ...op, jumlah_pob: 5, korban: [{ status_korban: 'S' }, { status_korban: 'MENINGGAL DUNIA' }], stats_korban: undefined };
  assert.equal(T.buildPenutupan(o, op.laporan_harian[1]).sections[2].isi,
    'DATA KORBAN TTK DUA\n1. SELAMAT : 1 ORANG\n2. MENINGGAL DUNIA : 1 ORANG\n3. DALAM PENCARIAN : 3 ORANG\n4. TOTAL POB : 5 ORANG');
});
test('penutupan victims use server stats_korban and show HILANG when present', () => {
  const o = { ...op, stats_korban: { s: 2, md: 1, dp: 0, h: 1, total: 4 } };
  assert.equal(T.buildPenutupan(o, op.laporan_harian[1]).sections[2].isi,
    'DATA KORBAN TTK DUA\n1. SELAMAT : 2 ORANG\n2. MENINGGAL DUNIA : 1 ORANG\n3. DALAM PENCARIAN : 0 ORANG\n4. HILANG : 1 ORANG\n5. TOTAL POB : 4 ORANG');
});
test('WIB and WIT keep their zone letter', () => {
  assert.equal(T.toSentence('pukul 09.00 WIB'), 'PUKUL 0900 G');
  assert.equal(T.toSentence('pukul 09.00 WIT'), 'PUKUL 0900 I');
  assert.equal(T.toSentence('Senin, 2 November 2026 pukul 10.15 WIB'), 'TW 1102 1015 G');
});
test('H.2 mid-sentence and bare HH:MM', () => {
  assert.equal(T.toSentence('pencarian H.2 nihil'), 'PENCARIAN HARI KE 2 NIHIL');
  assert.equal(T.toSentence('kembali jam 17:05 sore'), 'KEMBALI JAM 1705 H SORE');
  assert.equal(T.toSentence('tiba 17:05'), 'TIBA 1705');
});

// --- Lanjutan: penyatuan unsur/alut & hal tertunda ---
const sectionWith = (d, re) => d.sections.filter(s => re.test(s.isi)).map(s => s.kode);
test('lapbang: unsur, alut and cuaca in one section', () => {
  const d = T.buildLapbang(op, op.laporan_harian[0]);
  assert.deepEqual(sectionWith(d, /UNSUR SAR TERLIBAT TTK DUA/), ['BBB']);
  assert.deepEqual(sectionWith(d, /ALUT SAR TTK DUA/), ['BBB']);
  assert.equal(d.sections.length, 4);
});
test('penutupan: unsur and alut in one section with the same titles as laporan awal', () => {
  const d = T.buildPenutupan(op, op.laporan_harian[1]);
  assert.deepEqual(sectionWith(d, /UNSUR SAR TERLIBAT TTK DUA/), ['BBB']);
  assert.deepEqual(sectionWith(d, /ALUT SAR TTK DUA/), ['BBB']);
  assert.doesNotMatch(d.sections.map(s => s.isi).join('\n'), /DIKEMBALIKAN/);
  assert.match(d.sections[2].isi, /^DATA KORBAN TTK DUA/);
});
test('renops POLA line goes through sentence format', () => {
  const sru = { ...op.renops[0].srus[0], pola_pencarian: 'Parallel/Creeping, Line', track_spacing: '0.5 NM' };
  assert.match(T.buildRenops(op, { ...op.renops[0], srus: [sru] }).sections[1].isi, /\nPOLA PARALLEL CREEPING KMA LINE TS 0\.5 NM LUAS/);
});
test('parseSruArea strips rectangle labels and keeps circle radius', () => {
  assert.deepEqual(T.parseSruArea("Sektor: K (4 NM²)\nBatas Koordinat:\nA (NW): 03° 57' S - 122° 30' E").coords, ["03° 57' S - 122° 30' E"]);
  assert.deepEqual(T.parseSruArea("Sektor: L (3.14 NM²)\nPusat Datum (LKP): 03° 58' S - 122° 31' E\nRadius Sektor: 1.00 NM (1.85 km)\nLuas Area: 3.14 NM²").coords,
    ["Pusat Datum (LKP): 03° 58' S - 122° 31' E", 'Radius Sektor: 1.00 NM (1.85 km)']);
});
test('referenceOptions sorted by hari with unique labels', () => {
  const o = { ...op, renops: [{ id_renops: 9, hari_ke: 'H.3' }, { id_renops: 4, hari_ke: 'H.2' }, { id_renops: 7, hari_ke: 'H.2' }], laporan_harian: [] };
  assert.deepEqual(T.referenceOptions(o).renops.map(r => r.id + ':' + r.label), ['4:HARI KE 2', '7:HARI KE 2 (2)', '9:HARI KE 3']);
});
test('free-text unsur/alut used when detail rows are empty', () => {
  const o = { ...op, detail_unsur: [], detail_alut: [], unsur_terlibat: '• Rescuer Kendari : 6 org\n* Polairud : 3 org', alut_palsar: '- Rescue Car : 1 unit' };
  const ccc = T.buildLaporanAwal(o).sections[2].isi;
  assert.match(ccc, /UNSUR SAR TERLIBAT TTK DUA\n1\. RESCUER KENDARI : 6 ORG\n2\. POLAIRUD : 3 ORG/);
  assert.match(ccc, /ALUT SAR TTK DUA\n1\. RESCUE CAR : 1 UNIT/);
});
test('radial without distance reads with DENGAN', () => {
  const bbb = T.buildLaporanAwal({ ...op, jarak: '', radial: '135', eta_lokasi: '' }).sections[1].isi;
  assert.match(bbb, /MENUJU LKP MENGGUNAKAN .* DENGAN RADIAL 135 TTK$/);
});

// --- Pewarisan kepala naskah dari BRA sebelumnya (operasi & kategori sama) ---
const braList = [ // urutan API: id_bra DESC
  { id_bra: 9, kategori: 'MAPEL', no_bra: '9/SAR-304/1026', aksi: 'KA KSOP KENDARI', info: 'KABASARNAS', derajat: 'SGR' },
  { id_bra: 6, kategori: 'OPERASI_SAR', no_bra: '3/SAR-304/1026', aksi: 'KABASARNAS', info: 'POLRES KENDARI KMA KSOP KENDARI', derajat: 'KILAT' },
  { id_bra: 5, kategori: 'SAR_UMUM', no_bra: '2/SAR-304/1026', aksi: 'KABASARNAS', info: 'POLRES KENDARI', derajat: 'SGR' }
];
test('inherit: same category latest BRA gives menbra, aksi, info, derajat', () => {
  assert.deepEqual(T.inheritHeader({ bra_sar: braList }, 'OPERASI_SAR', 0),
    { menbra: '3/SAR-304/1026', aksi: 'KABASARNAS', info: 'POLRES KENDARI KMA KSOP KENDARI', derajat: 'KILAT' });
});
test('inherit: MAPEL menbra refers to latest OPERASI_SAR BRA, header from previous MAPEL', () => {
  assert.deepEqual(T.inheritHeader({ bra_sar: braList }, 'MAPEL', 0),
    { menbra: '3/SAR-304/1026', aksi: 'KA KSOP KENDARI', info: 'KABASARNAS', derajat: 'SGR' });
});
test('inherit: first MAPEL of operation still gets OPERASI_SAR menbra only', () => {
  assert.deepEqual(T.inheritHeader({ bra_sar: braList.slice(1) }, 'MAPEL', 0), { menbra: '3/SAR-304/1026' });
});
test('inherit: excludes the BRA being edited and returns null when none', () => {
  assert.equal(T.inheritHeader({ bra_sar: braList }, 'OPERASI_SAR', 6).menbra, '2/SAR-304/1026');
  assert.equal(T.inheritHeader({ bra_sar: [] }, 'OPERASI_SAR', 0), null);
  assert.equal(T.inheritHeader(null, 'OPERASI_SAR', 0), null);
});
