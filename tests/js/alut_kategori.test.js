'use strict';
// Kategori alut di form: resolver JS sama urutannya dengan server — kategori item → master (id, lalu nama) → kata kunci.
const assert = require('node:assert/strict');
let failed = 0;
function test(name, fn) {
  try { fn(); console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n  ' + e.message.split('\n').join('\n  ')); }
}

global.window = { ALUT_KATEGORI: {
  urutan: [{ kode: 'ALUT_DARAT', label: 'ALUT Darat' }, { kode: 'ALUT_LAUT', label: 'ALUT Laut' }, { kode: 'ALUT_UDARA', label: 'ALUT Udara' }, { kode: 'PALSAR', label: 'PALSAR' }],
  kata: { ALUT_LAUT: ['kapal', 'boat'], ALUT_UDARA: ['drone'], ALUT_DARAT: ['car'] },
  master: { id: { 7: 'ALUT_LAUT', 9: 'PALSAR' }, nama: { 'kn sar pacitan': 'ALUT_LAUT', 'rescue carrier': 'ALUT_DARAT', 'drone cadangan': 'PALSAR' } },
} };
require('../../assets/js/alut_kategori.js');
const K = window.AlutKategoriUI;

test('nama ketik-bebas cocok master (huruf/spasi berbeda)', () => {
  assert.equal(K.tebak('  kn  SAR pacitan '), 'ALUT_LAUT');
  assert.equal(K.tebak('Rescue Carrier'), 'ALUT_DARAT');
  assert.equal(K.tebak('Drone Cadangan'), 'PALSAR');
});
test('tanpa master: kata kunci, selain itu PALSAR', () => {
  assert.equal(K.tebak('Speed Boat Warga'), 'ALUT_LAUT');
  assert.equal(K.tebak('Senter'), 'PALSAR');
});
test('kategoriOf: kategori item → master id → master nama → kata kunci', () => {
  assert.equal(K.kategoriOf({ nama_alut: 'KN SAR Pacitan', kategori: 'PALSAR' }), 'PALSAR');
  assert.equal(K.kategoriOf({ id_alut: 7, nama_alut: 'Nama Lain' }), 'ALUT_LAUT');
  assert.equal(K.kategoriOf({ id_alut: 9, nama_alut: 'Rescue Car' }), 'PALSAR');
  assert.equal(K.kategoriOf({ nama_alut: 'kn sar pacitan' }), 'ALUT_LAUT');
  assert.equal(K.kategoriOf({ nama_alut: 'Rescue Car' }), 'ALUT_DARAT');
});
test('tanpa konfigurasi master tetap jalan', () => {
  delete window.ALUT_KATEGORI.master;
  delete require.cache[require.resolve('../../assets/js/alut_kategori.js')];
  require('../../assets/js/alut_kategori.js');
  assert.equal(window.AlutKategoriUI.tebak('Rescue Car'), 'ALUT_DARAT');
});

console.log(failed ? `${failed} FAIL` : 'ALL PASS');
process.exit(failed ? 1 : 0);
