'use strict';
// Flip pola Parallel Track & Creeping Line: titik awal fleksibel (cermin timur-barat / utara-selatan) tetap di dalam area.
const assert = require('node:assert/strict');
let failed = 0;
function test(name, fn) {
  try { fn(); console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n  ' + e.message.split('\n').join('\n  ')); }
}

const P = require('../../assets/js/gis/patterns.js').QrSarGis.Patterns;
const persegi = [{ lat: -4.0, lng: 122.0 }, { lat: -4.0, lng: 122.2 }, { lat: -4.2, lng: 122.2 }, { lat: -4.2, lng: 122.0 }];
const cLat = -4.1, cLng = 122.1;
const near = (a, b) => Math.abs(a - b) < 1e-9;

for (const type of ['parallel_track', 'creeping_line']) {
  test(`${type}: flipH mencerminkan timur-barat, flipV utara-selatan, jumlah titik sama`, () => {
    const base = P.generateFittedPatternInsidePolygon(type, persegi, 2, 5, {});
    const h = P.generateFittedPatternInsidePolygon(type, persegi, 2, 5, { flipH: true });
    const v = P.generateFittedPatternInsidePolygon(type, persegi, 2, 5, { flipV: true });
    const hv = P.generateFittedPatternInsidePolygon(type, persegi, 2, 5, { flipH: true, flipV: true });
    assert.ok(base.length > 2);
    assert.equal(h.length, base.length);
    assert.equal(v.length, base.length);
    base.forEach((p, i) => {
      assert.ok(near(h[i].lng, 2 * cLng - p.lng) && near(h[i].lat, p.lat), `flipH titik ${i}`);
      assert.ok(near(v[i].lat, 2 * cLat - p.lat) && near(v[i].lng, p.lng), `flipV titik ${i}`);
      assert.ok(near(hv[i].lat, 2 * cLat - p.lat) && near(hv[i].lng, 2 * cLng - p.lng), `flipH+V titik ${i}`);
      assert.equal(h[i].label, p.label);
    });
    assert.notEqual(Math.sign(base[0].lng - cLng), Math.sign(h[0].lng - cLng), 'titik awal pindah sisi timur/barat');
  });
}

test('Review Focus 5: poligon cekung (L) + creeping_line flipH+flipV → semua titik di dalam poligon', () => {
  const L = [
    { lat: -4.0, lng: 122.0 }, { lat: -4.0, lng: 122.1 }, { lat: -4.1, lng: 122.1 },
    { lat: -4.1, lng: 122.2 }, { lat: -4.2, lng: 122.2 }, { lat: -4.2, lng: 122.0 },
  ];
  for (const opts of [{ flipH: true }, { flipV: true }, { flipH: true, flipV: true }]) {
    const wps = P.generateFittedPatternInsidePolygon('creeping_line', L, 1, 6, opts);
    assert.ok(wps.length > 2);
    wps.forEach((w, i) => assert.ok(P.isPointInPolygon(w.lat, w.lng, L), `titik ${i} di luar (${JSON.stringify(opts)})`));
  }
});

test('E-SAR tidak terpengaruh flip', () => {
  const a = P.generateFittedPatternInsidePolygon('e_sar', persegi, 1, 4, {});
  const b = P.generateFittedPatternInsidePolygon('e_sar', persegi, 1, 4, { flipH: true, flipV: true });
  assert.deepEqual(b, a);
});

console.log(failed ? `${failed} FAIL` : 'ALL PASS');
process.exit(failed ? 1 : 0);
