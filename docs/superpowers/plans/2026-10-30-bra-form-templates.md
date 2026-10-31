# BRA Form Templates Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every BRA draft built from an operation's data follows the telegram format, and its source data and sender title are correct.

**Architecture:**
- Move all text-building logic out of the closure in `assets/js/pages/bra_form.js` into a new pure module, `assets/js/bra/bra_templates.js`. It exports `window.BraTemplates` for the browser and `module.exports` for node.
- Node tests the module directly with `node:assert`.
- `bra_form.js` keeps only the DOM wiring: buttons, H pickers, setting values, and the sender-title rule.

**Tech Stack:** vanilla JS (IIFE, no bundler), PHP 8 views, node for the module tests.

**Spec:** `docs/superpowers/specs/2026-10-30-bra-form-templates-design.md`

## Global Constraints

- No database changes, no new dependencies.
- **Narrative text** (spec, "Aturan format"):
  - Uppercase throughout.
  - Mid-sentence punctuation is converted: `,` becomes `KMA`, `.` becomes `TTK`, `:` becomes `TTK DUA`, and `/` is removed.
  - The content of `( )` is kept verbatim (uppercased only).
  - DMS coordinates and decimal numbers are kept verbatim.
- **Time:** `TW MMDD HHMM H` (H = WITA). A DB datetime `YYYY-MM-DD HH:MM:SS` is already WITA, so it is not converted again.
- **H mid-sentence:** written as `HARI KE X`.
- **Lists:**
  - Title format: `<JUDUL> TTK DUA`.
  - Items are numbered `1.`, `2.`, and SRU coordinates are lettered `A.`, `B.`.
  - Punctuation inside list items is kept.
  - An empty list is not shown at all, title included.
- **Sender title:** Laporan Awal uses the real title. Any other BRA linked to an operation uses `SMC`. A BRA not linked to an operation uses the real title.
- **Recipients:**
  - Operasi SAR:
    - AKSI is `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`.
    - INFO is empty, with placeholder `POLRES/POLSEK KMA KSOP KMA SROP SETEMPAT`.
  - Pemapelan:
    - AKSI is `KA KSOP KENDARI KMA KA SROP KENDARI`.
    - INFO is `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`.
  - Pergerakan Kapal:
    - AKSI is `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`.
    - INFO is `DEPUTI OPS DAN KESIAPSIAGAAN KMA DEPUTI SARPRAS DAN SISTEM KOMUNIKASI KMA DIREKTUR SARPRAS DAN DIREKTUR KESIAPSIAGAAN`.
- Transport keywords (whole word): `RESCUE CAR, RIB, RUBBER BOAT, LCR, KN, RB, MOBIL, TRUK, LONGBOAT, KAPAL, HELI`.
- Do not commit. The user commits when ready (user preference).

## Review Focus

1. **Operation switched quickly while a fetch is still running:** data from the old operation must not end up in the form. Test: browser step in Task 3.
2. **Location or name containing `,` `.` `/`** (e.g. `DS. LALOWARU, KEC. MORAMO/KONSEL`): becomes `KMA`/`TTK` and no symbol is left. Test in Task 1.
3. **DMS coordinates with decimal seconds** (`03° 58' 30.5" S - 122° 35' 12" E`) and decimal numbers (`2.5 NM`): unchanged. Test in Task 1.
4. **Operation with no transport alut, or with `jarak`/`radial`/`eta_lokasi` empty:** the BBB sentence stays valid, with no dangling `MENGGUNAKAN` and no stray `DENGAN JARAK`. Test in Task 2.
5. **`hari_ke` in `H.2`, `H-2`, `2`, or `HARI KE 2` format:** all read as 2. Test in Task 1.

---

### Task 1: Telegram formatting module

**Files:**
- Create: `assets/js/bra/bra_templates.js`
- Test: `tests/js/bra_templates.test.js`

**Interfaces:**
- Produces (on `BraTemplates`):
  - `toSentence(text: string, verbatim?: Record<string,string>): string`
    - Converts narrative text per the Global Constraints.
    - `{key}` slots in `text` are filled with `verbatim[key].toUpperCase()` *after* conversion.
    - Text inside `( )` is not converted.
  - `formatSectionText(text: string): string`
    - Applied line by line.
    - Lines that are list items (`/^\s*(\d+|[A-Z])\.\s/`) or list titles (ending in `TTK DUA`) are only uppercased.
    - Every other line goes through `toSentence`.
  - `militaryTime(value: string|Date|null): string`
    - Returns `MMDD HHMM H` (no `TW`), or `''` if empty or invalid.
    - Moved from `formatDateToMilitary` in `bra_form.js`.
  - `hariNumber(hariKe: string|number|null): number`
    - Returns 1 when it cannot be read.
  - `numberedList(title: string, items: string[]): string`
    - Returns `''` when `items` is empty.
  - `letteredList(title: string, items: string[]): string`
    - Same, with `A.` instead.
  - `unsurItems(rows: {nama_unsur, jumlah_personil}[]): string[]`
    - Returns `NAMA : N ORANG`.
  - `alutItems(rows: {nama_alut, jumlah_alut, satuan}[]): string[]`
    - Returns `NAMA : N SATUAN`.
  - `cuacaItems(w: {cuaca?, angin?, gelombang?}): string[]`
    - Returns `CUACA : ...`, `ANGIN : ...`, `GELOMBANG : ...`, skipping empty ones.
  - `isTransportAlut(nama: string): boolean`
  - `parseSruArea(text: string): {coords: string[], entry: string, exit: string}`
    - `coords`: non-empty lines after the `Batas Koordinat:` line, up to the first blank line.
    - When `Batas Koordinat:` is absent, `coords` comes from lines after `Sektor:` that contain `°`.
    - `entry` / `exit`: the `Koordinat:` line (prefix removed) right after `[Titik Entry Poin: ...]` / `[Titik Exit Poin: ...]`.
- File pattern:
  - Same IIFE as `assets/js/utils/common.js` (`global.BraTemplates = ...`).
  - Add `if (typeof module !== 'undefined') module.exports = BraTemplates;`.

- [ ] **Step 1: Write the failing tests**

Use `node:assert/strict` and a `test(name, fn)` helper that prints PASS/FAIL and sets `process.exitCode = 1` on failure.

```js
const T = require('../../assets/js/bra/bra_templates.js');
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
```

- [ ] **Step 2: Run the tests and confirm they fail**

Run: `node tests/js/bra_templates.test.js`
Expected: error `Cannot find module '../../assets/js/bra/bra_templates.js'`.

- [ ] **Step 3: Implement the functions in the Interfaces block in `assets/js/bra/bra_templates.js`**

- Port the existing logic from `bra_form.js`: `sanitizeTelegramText` (decimal protection, ISO date conversion, reduplication) and `formatTelegramReduplication` go into `toSentence`.
- Protect the `( )` content and the `{key}` slots the same way the decimals are protected (placeholder, then restore).
- `/` outside parentheses becomes a space.
- Match `isTransportAlut` keywords with `\b` on the uppercased string.

- [ ] **Step 4: Run the tests and confirm they pass**

Run: `node tests/js/bra_templates.test.js`
Expected: every line `PASS`, exit code 0.

---

### Task 2: Draft builders per BRA type

**Files:**
- Modify: `assets/js/bra/bra_templates.js`
- Test: `tests/js/bra_templates.test.js`

**Interfaces:**
- Consumes: everything from Task 1.
- Produces:
  - `type Draft = { perihal: string, aksi: string, info: string, kind: 'LAPORAN_AWAL'|'LANJUTAN', sections: {kode: string, isi: string}[] }`
  - `buildLaporanAwal(op): Draft`
  - `buildRenops(op, renops): Draft`
  - `buildLapbang(op, laporan): Draft`
  - `buildPenutupan(op, laporan): Draft`
  - `buildMapel(op): Draft`
  - `referenceOptions(op): { renops: {id, label}[], lapbang: {id, label}[], penutupan: object|null }`
    - `label` = `HARI KE X`.
    - `lapbang` = `laporan_harian` with `jenis_laporan !== 'PENUTUPAN'`.
    - `penutupan` = the last `PENUTUPAN`.
  - `senderTitle(kind: 'LAPORAN_AWAL'|'LANJUTAN', linked: boolean, realTitle: string): string`
- The `op` shape comes from `GET /api/operasi/{id}` (`models/OperasiModel.php:120-174`):
  - Fields: `nama_operasi, jenis_kejadian, nama_pelapor, hubungan_pelapor, no_telp_pelapor, waktu_lapor, tim_berangkat, lokasi_kejadian, koordinat, jarak, radial, eta_lokasi, cuaca, angin, gelombang, jumlah_pob`.
  - Lists: `korban[]{status_korban}`, `detail_unsur[]`, `detail_alut[]`, `detail_tim_berangkat[]{nama_tim}`.
  - `renops[]{id_renops, hari_ke, waktu_briefing, waktu_mulai_ops, cuaca, detail_unsur[], detail_alut[], srus[]{nama_sru, koordinat_area, luas_area, pola_pencarian, track_spacing}}`.
  - `laporan_harian[]{id_laporan, hari_ke, jenis_laporan, isi_laporan, waktu_laporan, waktu_debrief, cuaca, detail_unsur[], detail_alut[]}`.
- Unsur and alut sources: the document's own rows first, falling back to `op.detail_unsur` / `op.detail_alut`.
- Weather: Laporan Awal uses `{cuaca, angin, gelombang}` from `op`. RENOPS and Lapbang use `{cuaca}` from the document.
- Exact copy (`{}` = data; narrative goes through `toSentence`):

**Laporan Awal**
- Perihal: `LAPORAN AWAL OPERASI SAR TERHADAP {nama}.`
- AAA: `PD TW {militaryTime(waktu_lapor)} TERIMA INFO DARI {pelapor} ({hubungan}/ HP {no}) BAHWA TELAH TERJADI {nama} DI {lokasi} PADA PERKIRAAN KOORDINAT {koordinat}.`
  - Koordinat is verbatim.
  - Drop the parentheses when both `hubungan` and `no` are empty.
- BBB: `TERSEBUT AAA DIATAS, {tim} DIBERANGKATKAN MENUJU LKP[ MENGGUNAKAN {angkut}][ DENGAN JARAK {jarak}][ RADIAL {radial}][ DAN ETA {eta}].`
  - Each `[...]` is omitted when its data is empty.
  - `tim` = `detail_tim_berangkat` names joined with `DAN`, falling back to `tim_berangkat`.
  - `angkut` = transport alut `"{jumlah} {satuan} {nama}"` joined with `DAN`.
- CCC: `numberedList('UNSUR SAR TERLIBAT')`, then `numberedList('ALUT SAR')`, then `numberedList('KONDISI CUACA')`, separated by a blank line.
- DDD: `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI KRM TTK HBS`

**RENOPS**
- Perihal: `RENCANA OPERASI SAR HARI KE {X} TERHADAP {nama}.`
- AAA: `PD TW {briefing} TIM SAR GABUNGAN MELAKSANAKAN BRIEFING RENCANA OPERASI SAR HARI KE {X} TERHADAP {nama} DENGAN WAKTU MULAI PERGERAKAN TW {mulai}.`
- BBB: `PEMBAGIAN SRU TTK DUA`, then for SRU number n:
  ```
  {n}. SEARCH AREA SRU {n} TTK DUA
  A. ...
  POLA {pola}[ TS {track_spacing}][ LUAS {luas}]
  ENTRY DAN EXIT POINT {entry} {exit}
  ```
  - The entry/exit line is omitted when both are empty.
- CCC: the same three lists as Laporan Awal.
- DDD: `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`

**Lapbang**
- Perihal: `LAPORAN PERKEMBANGAN HARI KE {X} OPERASI SAR TERHADAP {nama}.`
- AAA: `PD TW {waktu_laporan} TERIMA INFO DARI OSC TIM SAR GABUNGAN BAHWA {isi_laporan}.`
- BBB: `numberedList('ALUT SAR')`
- CCC: `numberedList('UNSUR SAR TERLIBAT')`
- DDD: `numberedList('KONDISI CUACA')` + blank line + `PADA TW {waktu_laporan} PENCARIAN HARI KE {X} DIHENTIKAN SEMENTARA DAN AKAN DILANJUTKAN KEMBALI PADA HARI KE {X+1}.`
  - My call: replaces the old fixed `HASIL NIHIL` / `TW 0700 H` sentence.
- EEE: `DMKN SBG LAPORAN PERKEMBANGAN HARI KE {X} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`
- No victim data.

**Lapbang dan Penutupan**
- Perihal: `LAPORAN PERKEMBANGAN DAN PENUTUPAN OPERASI SAR TERHADAP {nama}.`
- AAA: `PD TW {waktu_debrief or waktu_laporan} TIM SAR GABUNGAN MELAKSANAKAN DEBRIEFING PENUTUPAN OPERASI SAR TERHADAP {nama} DENGAN HASIL {isi_laporan}.`
- BBB: `numberedList('ALUT SAR DIKEMBALIKAN KE PANGKALAN')`
- CCC: `numberedList('UNSUR SAR DIKEMBALIKAN KE KESATUAN MASINGS')`
- DDD: `numberedList('DATA KORBAN', ['SELAMAT : {S} ORANG', 'MENINGGAL DUNIA : {MD} ORANG', 'DALAM PENCARIAN : {other} ORANG', 'TOTAL POB : {jumlah_pob or total} ORANG'])`
- EEE: `DENGAN DEMIKIAN OPERASI SAR TERHADAP {nama} DINYATAKAN DITUTUP. ATAS KERJASAMA SELURUH UNSUR SAR TERLIBAT DIUCAPKAN TERIMA KASIH.`
- FFF: `DMKN SBG LAPORAN PERKEMBANGAN DAN PENUTUPAN OPERASI SAR KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`

**Pemapelan**
- Perihal: `PERMINTAAN PEMAPELAN DAN PEMANTAUAN TERHADAP {nama}.`
- AAA: the Laporan Awal AAA sentence + ` TERHADAP KEJADIAN TERSEBUT {tim} TELAH DIBERANGKATKAN MENUJU LKP UNTUK MELAKSANAKAN PENCARIAN.`
- BBB (current text): `TERKAIT INFO MUSIBAH TSB MHN BANTUAN PEMANTAUAN DAN PEMAPELAN KEPADA KAPALS YANG MELINTASI DI AREA TERSEBUT KMA APABILA MENEMUKAN TANDAS KEBERADAAN KAPAL TERSEBUT AGAR DAPAT MEMBERIKAN PERTOLONGAN PERTAMA SERTA MELAPORKAN KPD BASARNAS KENDARI TTK`
- CCC: `ATAS PERHATIAN DAN KERJASAMANYA DIUCAPKAN TERIMA KASIH KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS`
- AKSI and INFO follow the Pemapelan row in Global Constraints.

`kind`: Laporan Awal is `LAPORAN_AWAL`. Every other type is `LANJUTAN`.

- [ ] **Step 1: Write the failing tests (append)**

Build a fixture `op` in the test with:
- `detail_alut`: `Rescue Car 1 UNIT`, `RIB 1 UNIT`, `Palsar Medis 1 SET`.
- `detail_unsur`: `Rescuer Kendari 6`.
- `korban`: `S`, `MD`, `DP`.
- One `renops` with `hari_ke 'H.2'` and one SRU, using the `parseSruArea` text from Task 1.
- `laporan_harian`: one `PERKEMBANGAN` H.2 and one `PENUTUPAN`.

```js
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
  const bbb = T.buildRenops(op, op.renops[0]).sections[1].isi;
  assert.match(bbb, /^PEMBAGIAN SRU TTK DUA\n1\. SEARCH AREA SRU 1 TTK DUA\nA\. /);
  assert.match(bbb, /ENTRY DAN EXIT POINT /);
  assert.match(T.buildRenops(op, op.renops[0]).perihal, /HARI KE 2/);
});
test('lapbang has no victim data, penutupan has', () => {
  assert.doesNotMatch(T.buildLapbang(op, op.laporan_harian[0]).sections.map(s => s.isi).join('\n'), /DATA KORBAN/);
  assert.match(T.buildPenutupan(op, op.laporan_harian[1]).sections[3].isi, /SELAMAT : 1 ORANG\n2\. MENINGGAL DUNIA : 1 ORANG\n3\. DALAM PENCARIAN : 1 ORANG/);
});
test('referenceOptions', () => {
  const r = T.referenceOptions(op);
  assert.equal(r.renops.length, 1);
  assert.equal(r.lapbang.length, 1);
  assert.ok(r.penutupan);
  assert.deepEqual(T.referenceOptions({ ...op, renops: [], laporan_harian: [] }), { renops: [], lapbang: [], penutupan: null });
});
test('senderTitle', () => {
  assert.equal(T.senderTitle('LAPORAN_AWAL', true, 'KEPALA KANTOR X'), 'KEPALA KANTOR X');
  assert.equal(T.senderTitle('LANJUTAN', true, 'KEPALA KANTOR X'), 'SMC');
  assert.equal(T.senderTitle('LANJUTAN', false, 'KEPALA KANTOR X'), 'KEPALA KANTOR X');
});
test('operasi SAR info empty', () => assert.equal(T.buildLaporanAwal(op).info, ''));
```

- [ ] **Step 2: Run and confirm the new tests fail**

Run: `node tests/js/bra_templates.test.js`
Expected: FAIL on `T.buildLaporanAwal is not a function`. The Task 1 tests stay PASS.

- [ ] **Step 3: Implement the builders, `referenceOptions` and `senderTitle` with the exact copy above**

- [ ] **Step 4: Run and confirm everything passes**

Run: `node tests/js/bra_templates.test.js`
Expected: all PASS, exit code 0.

---

### Task 3: Wire the module into the BRA form

**Files:**
- Modify: `views/bra/form.php`
  - Script include near `:1683`.
  - Reference buttons at `:990-1004`.
  - INFO placeholder at `:1190`.
- Modify: `assets/js/pages/bra_form.js`:
  - `:408-515`: remove `formatTelegramReduplication`, `formatDateToMilitary`, `sanitizeTelegramText`, and switch their callers to `BraTemplates`.
  - `:517-615`: `updateCategoryUI`, recipients per category.
  - `:625-729`: `updateRecommendationButtonState` and the operation change handler.
  - `:743-765`: `setSectionsContent`.
  - `:766-1047`: remove the builders and switch the buttons to `BraTemplates`.
  - `:1107+`: `applyVesselPreset`.

**Interfaces:**
- Consumes: `BraTemplates.*` (Tasks 1–2).
- Produces:
  - DOM IDs `selectRenopsHari` and `selectLapbangHari` (each a `<select>` next to its button).
  - Button `btnAutoPenutupan` labelled "Lapbang dan Penutupan".

- [ ] **Step 1: Load the module**

Add `<script src="<?= assetUrl('assets/js/bra/bra_templates.js') ?>"></script>` before `bra_form.js` in `views/bra/form.php`.

- [ ] **Step 2: `setSectionsContent(sections)` builds elements through the DOM**

- Build `div`, `span`, `input[type=hidden]` and `textarea` with `createElement`.
- Set `textarea.value = s.isi` and `input.value = s.kode`.
- Keep the same classes, names (`sections[i][kode]` / `sections[i][isi]`) and delete button as today.
- Placeholder = `getSectionHint(idx, kategori)`.
- No data goes through `innerHTML`.

- [ ] **Step 3: One `applyDraft(draft: Draft)` in `bra_form.js`**

- Fills perihal, aksi and info, then `setSectionsContent`.
- Sets `jabatanSmcInput` = `BraTemplates.senderTitle(draft.kind, true, realTitle)`.
- `realTitle` = the `data-jabatan` of the selected pejabat, or `KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI`.
- Calls `syncLivePreview()`.

- [ ] **Step 4: Reference buttons and H pickers**

- After the operation data loads, `referenceOptions(op)` fills `selectRenopsHari` / `selectLapbangHari`.
- It sets `disabled` (attribute, not opacity) plus a reason `title` on:
  - RENOPS, Lapbang and Lapbang dan Penutupan, when their data is empty;
  - all five buttons, when no operation is selected.
- Each button calls the matching `build*` with the document picked in its select, then `applyDraft`.

- [ ] **Step 5: Ignore stale responses**

- In the operation change handler, store `const requested = opId`.
- After `await loadOperasiData`, return immediately if `opSelect.value !== requested`.
- Keep the loaded operation in `currentOp` and use it in the button handlers instead of fetching again.

- [ ] **Step 6: Recipients, sender title and vessel preset**

- `updateCategoryUI` follows the Recipients section of Global Constraints.
- Operasi SAR INFO is emptied, and its placeholder is set in the view too.
- When no operation is linked, the sender title = `realTitle`.
- `applyVesselPreset` only fills the header (perihal, aksi, info, panggilan). Its sections are reset to empty AAA/BBB/CCC, which only show placeholders.

- [ ] **Step 7: The "Format Sandi" button uses the module**

`btnFormatTelegramPunctuation` calls `BraTemplates.formatSectionText` on textareas and perihal. Fields already excluded today stay uppercase only.

- [ ] **Step 8: Verify in the browser**

On `/bra.php?action=create` in the browser pane, with local dev data:
- **(a)** Choose an operation that has no RENOPS. The RENOPS button is `disabled`.
- **(b)** Laporan Awal fills AAA–DDD per the spec, and the sender title is the real title.
- **(c)** RENOPS: change H in the picker. The draft follows that H, and the sender title becomes `SMC`.
- **(d)** Choose operation A, then switch quickly to B. Every draft that loads mentions B's name.
- **(e)** Pergerakan Kapal plus a vessel preset leaves the sections empty, with placeholders only.
- **(f)** The console shows no errors.

Also run `node tests/js/bra_templates.test.js`. Expected: all PASS.

---

### Task 4: Dropdowns with overlapping text

**Files:**
- Modify: the `<style>` block in `views/bra/form.php` (around `:355-381`), or `assets/css/theme-minimal.css:202-219` if the cause is in the shared `.field-input`.

- [ ] **Step 1: Reproduce**

- In the browser pane at `/bra.php?action=create`, inspect `#operasiSelect`, `#pejabatSelectControl` and `#selectVesselChoice` in both the default and minimal themes.
- Write down the CSS rule that makes the label, the value or the placeholder overlap (e.g. a floating-label `position:absolute` or a `padding-top` not applied to `select`).

- [ ] **Step 2: Fix that rule for `select.field-input`**

Change it so the label sits above the field and the selected value is readable. Do not change other inputs.

- [ ] **Step 3: Verify**

- All three dropdowns are readable in both the default and minimal themes, at desktop and mobile width (`resize_window` preset mobile).
- Text inputs with a floating label still look as before.
