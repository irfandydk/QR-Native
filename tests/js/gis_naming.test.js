'use strict';
// GIS: pengelompokan titik per kategori (daftar ringkas) & penamaan otomatis titik/area/pola/rute.
const assert = require('node:assert/strict');
let failed = 0;
function test(name, fn) {
  try { fn(); console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n  ' + e.message.split('\n').join('\n  ')); }
}

let N = null;
try { N = require('../../assets/js/gis/naming.js'); } catch (e) { N = {}; }

test('groupPoints: pemetaan kategori ke kelompok', () => {
  const cats = ['Kantor SAR', 'POS SAR', 'Unit Siaga SAR', 'Dermaga SAR', 'LKP', 'Penemuan Korban', 'Lokasi Penemuan Korban', 'Posko', 'Entry Poin', 'Exit Poin', 'Tanpa Kategori', 'Aneh'];
  const pts = cats.map((c, i) => ({ id: 'p' + i, category: c }));
  const g = N.groupPoints(pts);
  const of = (key) => (g.find((x) => x.key === key) || { points: [] }).points.map((p) => p.category);
  assert.deepEqual(g.map((x) => x.key), ['pangkalan', 'dermaga', 'lkp', 'korban', 'posko', 'entry_exit', 'lain']);
  assert.deepEqual(of('pangkalan'), ['Kantor SAR', 'POS SAR', 'Unit Siaga SAR']);
  assert.deepEqual(of('korban'), ['Penemuan Korban', 'Lokasi Penemuan Korban']);
  assert.deepEqual(of('entry_exit'), ['Entry Poin', 'Exit Poin']);
  assert.deepEqual(of('lain'), ['Tanpa Kategori', 'Aneh']);
  assert.equal(g[0].label, 'Pangkalan SAR (Kantor/POS/USS)');
});
test('groupPoints: kelompok kosong tidak muncul, urutan titik dipertahankan, kategori kosong → lain', () => {
  const g = N.groupPoints([{ id: 'b', category: 'LKP' }, { id: 'a', category: '' }, { id: 'c', category: 'LKP' }]);
  assert.deepEqual(g.map((x) => x.key), ['lkp', 'lain']);
  assert.deepEqual(g[0].points.map((p) => p.id), ['b', 'c']);
  assert.deepEqual(N.groupPoints([]), []);
});

test('nextSruName: nomor terkecil yang belum dipakai', () => {
  assert.equal(N.nextSruName([{ name: 'SRU 1' }, { name: 'SRU 3' }]), 'SRU 2');
  assert.equal(N.nextSruName([]), 'SRU 1');
  assert.equal(N.nextSruName([{ name: 'sru 1' }, { name: 'Area Pantai' }, { name: 'SRU 2' }]), 'SRU 3');
});
test('patternAutoName & isAutoPatternName', () => {
  assert.equal(N.patternAutoName('SRU 1', 'creeping_line'), 'Pola Pencarian Area SRU 1 (CS)');
  assert.equal(N.patternAutoName('SRU 2', 'parallel_track'), 'Pola Pencarian Area SRU 2 (PS)');
  assert.equal(N.patternAutoName('SRU 3', 'e_sar'), 'Pola Pencarian Area SRU 3 (E-SAR)');
  assert.equal(N.patternAutoName('', 'parallel_track'), 'Pola Pencarian Area Datum LKP (PS)');
  assert.equal(N.isAutoPatternName('Pola Pencarian Area SRU 1 (PS)'), true);
  assert.equal(N.isAutoPatternName('Sapu Pantai'), false);
  assert.equal(N.isAutoPatternName(''), false);
});
test('routeAutoName: ke area, atau Rute N terkecil', () => {
  assert.equal(N.routeAutoName('SRU 2', []), 'Rute ke SRU 2');
  assert.equal(N.routeAutoName(null, [{ name: 'Rute 1' }]), 'Rute 2');
  assert.equal(N.routeAutoName('', [{ name: 'rute 2' }]), 'Rute 1');
});
test('pointAutoName: LKP tunggal, Titik N, Kategori N', () => {
  assert.equal(N.pointAutoName('LKP', []), 'LKP');
  assert.equal(N.pointAutoName('Penemuan Korban', [{ label: 'Penemuan Korban 1', category: 'Penemuan Korban' }]), 'Penemuan Korban 2');
  assert.equal(N.pointAutoName('Tanpa Kategori', [{ label: 'Titik 1' }, { label: 'Titik 2' }]), 'Titik 3');
  assert.equal(N.pointAutoName('Posko', []), 'Posko 1');
});

console.log(failed ? `${failed} FAIL` : 'ALL PASS');
process.exit(failed ? 1 : 0);
