'use strict';
// Impor per SRU (form RENOPS): daftar periksa area→pola/rute & penggabungan baris SRU tanpa duplikat.
const assert = require('node:assert/strict');
let failed = 0;
function test(name, fn) {
  try { fn(); console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n  ' + e.message.split('\n').join('\n  ')); }
}

let R = {};
try { R = require('../../assets/js/renops_import.js'); } catch (e) { R = {}; }

const polys = [{ id: 'p1', name: 'SRU 1' }, { id: 'p2', name: 'SRU 2' }, { id: 'p3', name: 'SRU 3' }];
const pats = [{ id: 'a', polygonId: 'p1' }, { id: 'b', polygonId: '', polygonName: 'SRU 2' }];
const routes = [{ id: 'r1', polygonId: 'p1' }, { id: 'r9', polygonId: null }];

test('checklist: pola via polygonId (cadangan polygonName), rute via polygonId, urutan area', () => {
  const c = R.checklist(polys, pats, routes);
  assert.deepEqual(c.map((x) => [x.id, x.name, x.pattern && x.pattern.id, x.route && x.route.id]),
    [['p1', 'SRU 1', 'a', 'r1'], ['p2', 'SRU 2', 'b', null], ['p3', 'SRU 3', null, null]]);
});

test('Review Focus 4: gabung — SRU format lama diperbarui, SRU manual utuh, area baru ditambah, idempoten', () => {
  const describe = (item) => ({ nama_sru: item.name, koordinat_area: `Area ${item.name}` });
  const items = R.checklist(polys.slice(0, 2), pats, routes);
  const srus = [{ nama_sru: 'SRU 1 - SRU 1', unsur: 'X' }, { nama_sru: 'Tim Darat' }];
  const out = R.mergeAreasIntoSrus(srus, items, describe);
  assert.equal(out.length, 3);
  assert.deepEqual(out[0], { nama_sru: 'SRU 1', unsur: 'X', koordinat_area: 'Area SRU 1' });
  assert.deepEqual(out[1], { nama_sru: 'Tim Darat' });
  assert.equal(out[2].nama_sru, 'SRU 2');
  assert.deepEqual(srus[0], { nama_sru: 'SRU 1 - SRU 1', unsur: 'X' }, 'masukan tidak dimutasi');
  const again = R.mergeAreasIntoSrus(out, items, describe);
  assert.equal(again.length, 3);
});

test('gabung: cocok tak peka huruf besar/kecil & spasi', () => {
  const out = R.mergeAreasIntoSrus([{ nama_sru: '  sru 2 ' }], R.checklist([polys[1]], [], []), (it) => ({ nama_sru: it.name }));
  assert.equal(out.length, 1);
  assert.equal(out[0].nama_sru, 'SRU 2');
});

test('checklist: urutan natural nama area (bundle ORDER BY id DESC → SRU 2, SRU 1)', () => {
  const c = R.checklist([{ id: 'x', name: 'SRU 10' }, { id: 'y', name: 'SRU 2' }, { id: 'z', name: 'SRU 1' }], [], []);
  assert.deepEqual(c.map((x) => x.name), ['SRU 1', 'SRU 2', 'SRU 10']);
});
test('gabung: nama area kembar tidak saling menimpa (satu baris dicocokkan sekali), tetap idempoten', () => {
  const items = R.checklist([{ id: 'a', name: 'SRU 1' }, { id: 'b', name: 'SRU 1' }], [], []);
  const d = (it) => ({ nama_sru: it.name, sumber: it.id });
  const out = R.mergeAreasIntoSrus([{ nama_sru: 'SRU 1' }], items, d);
  assert.deepEqual(out.map((x) => x.sumber), ['a', 'b']);
  assert.equal(R.mergeAreasIntoSrus(out, items, d).length, 2);
});
test('gabung: baris baru mendapat nilai bawaan; baris lama tidak (isian manual tetap)', () => {
  const defaults = { pola_pencarian: 'Parallel Track Search', total_sapuan: '' };
  const items = R.checklist([{ id: 'a', name: 'SRU 1' }, { id: 'b', name: 'SRU 2' }], [], []);
  const out = R.mergeAreasIntoSrus([{ nama_sru: 'SRU 1', pola_pencarian: 'Creeping Line Search' }], items, (it) => ({ nama_sru: it.name }), defaults);
  assert.equal(out[0].pola_pencarian, 'Creeping Line Search');
  assert.equal(out[1].pola_pencarian, 'Parallel Track Search');
});

test('routeLengthNm: panjang rute dari titik (haversine, NM)', () => {
  assert.equal(R.routeLengthNm([]), 0);
  assert.equal(R.routeLengthNm([{ lat: 0, lng: 0 }]), 0);
  const nm = R.routeLengthNm([{ lat: 0, lng: 0 }, { lat: 1, lng: 0 }]); // 1° lintang ≈ 60 NM
  assert.ok(Math.abs(nm - 60.04) < 0.1, String(nm));
  assert.ok(Math.abs(R.routeLengthNm([{ lat: 0, lng: 0 }, { lat: 1, lng: 0 }, { lat: 2, lng: 0 }]) - 2 * nm) < 1e-9);
});

test('wiring form RENOPS: modul dimuat, tombol "Impor per SRU", daftar periksa, gabung via modul', () => {
  const fs = require('node:fs');
  const path = require('node:path');
  const view = fs.readFileSync(path.join(__dirname, '../../views/operasi/renops_form.php'), 'utf8');
  const js = fs.readFileSync(path.join(__dirname, '../../assets/js/pages/renops_form.js'), 'utf8');
  assert.ok(view.includes("assetUrl('assets/js/renops_import.js')") && view.indexOf('renops_import.js') < view.indexOf('pages/renops_form.js'));
  assert.ok(view.includes('Impor per SRU') && view.includes('id="gis-import-checklist"'));
  assert.ok(js.includes('RenopsImport.mergeAreasIntoSrus(') && js.includes('RenopsImport.checklist('));
  assert.ok(!js.includes('`SRU ${num} - ${name}`') && !js.includes('`SRU ${targetIdx + 1} - ${name}`'), 'nama SRU = nama area');
  const desc = js.slice(js.indexOf('function describeAreaForSru('), js.indexOf('function renderGisImportChecklist('));
  assert.ok(/if \(item\.pattern\)/.test(desc) && !/pola_pencarian: patInfo\.pola,\n    total_sapuan/.test(desc), 'pola hanya ditulis bila area punya pola');
  assert.ok(js.includes('SRU_DEFAULTS') && js.includes('RenopsImport.mergeAreasIntoSrus(currentSrus, items, describeAreaForSru, SRU_DEFAULTS)'), 'baris baru diberi nilai bawaan');
  const sync = js.slice(js.indexOf('async function syncAllPlottingSectorsToSru('), js.indexOf('async function syncAllPlottingSectorsToSru(') + 900);
  assert.ok(sync.includes('importAllPolygonsToSrus()') && !sync.includes('currentSrus = polys.map'), 'tombol Sinkronkan memakai alur gabung (SRU manual tidak terhapus)');
});

test('Task 1: Hapus default pola pencarian Parallel Track Search pada form input RENOPS', () => {
  const fs = require('node:fs');
  const path = require('node:path');
  const js = fs.readFileSync(path.join(__dirname, '../../assets/js/pages/renops_form.js'), 'utf8');

  // 1. SRU_DEFAULTS harus memiliki pola_pencarian kosong ''
  assert.match(js, /const\s+SRU_DEFAULTS\s*=\s*\{\s*pola_pencarian:\s*'',/);

  // 2. addSruRow harus menginisialisasi pola_pencarian: ''
  assert.match(js, /function\s+addSruRow\(\)\s*\{[\s\S]*?pola_pencarian:\s*'',/);

  // 3. Fallback s.pola_pencarian tidak boleh hardcoded 'Parallel Track Search'
  assert.doesNotMatch(js, /const\s+polaVal\s*=\s*s\.pola_pencarian\s*\|\|\s*['"]Parallel Track Search['"]/);
  assert.match(js, /const\s+polaVal\s*=\s*s\.pola_pencarian\s*\|\|\s*''/);

  // 4. Hidden input srus[idx][pola_pencarian] tidak boleh fallback ke 'Parallel Track Search'
  assert.doesNotMatch(js, /name="srus\[\$\{idx\}\]\[pola_pencarian\]"\s+value="\$\{escapeHtml\(s\.pola_pencarian\s*\|\|\s*'Parallel Track Search'\)\}"/);
  assert.match(js, /name="srus\[\$\{idx\}\]\[pola_pencarian\]"\s+value="\$\{escapeHtml\(s\.pola_pencarian\s*\|\|\s*''\)\}"/);

  // 5. Render pola pencarian saat kosong menampilkan teks "- (Belum ditentukan / Pilih dari Peta Plotting)"
  assert.ok(js.includes('- (Belum ditentukan / Pilih dari Peta Plotting)'));

  // 6. getPatternDetails saat !matchedPattern harus mengembalikan pola: ''
  assert.match(js, /if\s*\(!matchedPattern\)\s*return\s*\{\s*pola:\s*'',/);

  // 7. importPointToSru harus pola_pencarian: ''
  assert.match(js, /function\s+importPointToSru[\s\S]*?pola_pencarian:\s*'',/);
});

test('Task 6: Dropdown filter kategori titik & card minimalis di GIS dan Modal Import RENOPS', () => {
  const fs = require('node:fs');
  const path = require('node:path');
  const gisView = fs.readFileSync(path.join(__dirname, '../../views/gis.php'), 'utf8');
  const gisJs = fs.readFileSync(path.join(__dirname, '../../assets/js/gis_master.js'), 'utf8');
  const renopsJs = fs.readFileSync(path.join(__dirname, '../../assets/js/pages/renops_form.js'), 'utf8');

  // 1. views/gis.php memiliki select #filterGisPointCategory di atas #savedPointsListContainer
  assert.ok(gisView.includes('id="filterGisPointCategory"'));
  assert.ok(gisView.indexOf('id="filterGisPointCategory"') < gisView.indexOf('id="savedPointsListContainer"'));

  // 2. assets/js/gis_master.js memiliki event listener dan fungsi update opsi kategori dinamis
  assert.match(gisJs, /updateGisPointCategoryOptions/);
  assert.match(gisJs, /filterGisPointCategory/);
  assert.match(gisJs, /const\s+filteredPoints\s*=\s*selectedCat/);

  // 3. Card titik di gis_master.js ramping
  assert.match(gisJs, /px-2\s+py-1\.5\s+rounded-xl\s+border/);

  // 4. renops_form.js memiliki filter dropdown kategori #filter-renops-point-cat pada tab points
  assert.ok(renopsJs.includes('id="filter-renops-point-cat"'));
  assert.ok(renopsJs.includes('Filter Kategori:'));
  assert.match(renopsJs, /activeRenopsPointCatFilter/);

  // 5. Card titik taktis di modal import renops_form.js kompak minimalis
  assert.match(renopsJs, /p-2\.5\s+rounded-xl\s+border\s+border-slate-200\s+dark:border-slate-800\s+bg-white\s+dark:bg-slate-900\s+shadow-xs\s+flex\s+flex-col\s+sm:flex-row/);
  assert.ok(renopsJs.includes('📋 Salin'));
  assert.ok(renopsJs.includes('Sisipkan ke SRU'));
  assert.match(renopsJs, /Titik Entry Poin/);
  assert.match(renopsJs, /Titik Exit Poin/);
});

console.log(failed ? `${failed} FAIL` : 'ALL PASS');
process.exit(failed ? 1 : 0);
