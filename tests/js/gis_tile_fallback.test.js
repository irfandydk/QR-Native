'use strict';
// Peta tidak blank saat zoom: tile citra tanpa data (404 / hitam pekat di laut lepas) diganti potongan tile induk.
const assert = require('node:assert/strict');
let failed = 0;
function test(name, fn) {
  try { fn(); console.log('PASS ' + name); } catch (e) { failed++; console.log('FAIL ' + name + '\n  ' + e.message.split('\n').join('\n  ')); }
}

let F = {};
try { F = require('../../assets/js/gis/tile_fallback.js'); } catch (e) { F = {}; }

test('parentTile: koordinat induk & area potong (dz=1, 2)', () => {
  assert.deepEqual(F.parentTile(5, 7, 17, 1), { x: 2, y: 3, z: 16, sx: 128, sy: 128, size: 128 });
  assert.deepEqual(F.parentTile(4, 6, 17, 1), { x: 2, y: 3, z: 16, sx: 0, sy: 0, size: 128 });
  assert.deepEqual(F.parentTile(7, 5, 18, 2), { x: 1, y: 1, z: 16, sx: 192, sy: 64, size: 64 });
});
test('isBlankTile: hitam pekat / transparan penuh = kosong; citra bervariasi = tidak', () => {
  const solid = (r, g, b, a) => { const d = new Uint8ClampedArray(256 * 256 * 4); for (let i = 0; i < d.length; i += 4) { d[i] = r; d[i + 1] = g; d[i + 2] = b; d[i + 3] = a; } return d; };
  assert.equal(F.isBlankTile(solid(0, 0, 0, 255)), true);
  assert.equal(F.isBlankTile(solid(0, 0, 0, 0)), true);
  assert.equal(F.isBlankTile(solid(80, 101, 156, 255)), false);
  const mix = solid(0, 0, 0, 255); mix[4 * 1000] = 90; mix[4 * 1000 + 1] = 120;
  assert.equal(F.isBlankTile(mix), false);
});

// Leaflet tiruan minimal: cukup untuk memanggil _tileOnLoad/_tileOnError layer fallback.
function fakeLeaflet() {
  const calls = { load: 0, error: 0 };
  function Base(url, opts) { this._url = url; this.options = opts || {}; this._map = {}; }
  Base.prototype._tileOnLoad = function () { calls.load++; };
  Base.prototype._tileOnError = function () { calls.error++; };
  Base.prototype._getSubdomain = () => '0';
  Base.prototype._getZoomForUrl = () => 17;
  Base.extend = function (props) {
    function Layer(url, opts) { Base.call(this, url, opts); }
    Layer.prototype = Object.assign(Object.create(Base.prototype), props);
    return Layer;
  };
  const L = {
    TileLayer: Base,
    Util: { emptyImageUrl: 'data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=', extend: Object.assign, template: (t) => t },
  };
  return { L, calls };
}
function stubBlankCanvasAndImage() {
  let images = 0;
  global.document = { createElement: () => ({ getContext: () => ({ drawImage() {}, getImageData: () => ({ data: new Uint8ClampedArray(256 * 256 * 4) }) }), toDataURL: () => 'data:' }) };
  global.Image = function () { images++; };
  return () => images;
}
const tileWithSrc = (src) => ({ getAttribute: (k) => (k === 'src' ? src : null), _qrCoords: { x: 5, y: 7, z: 17 } });

test('Tile yang sudah dikosongkan Leaflet (dibuang/dibatalkan) tidak memicu fallback', () => {
  const { L, calls } = fakeLeaflet();
  const imageCount = stubBlankCanvasAndImage();
  const layer = F.createLayer(L, 'u', {});
  layer._tileOnLoad(() => {}, tileWithSrc(L.Util.emptyImageUrl));
  layer._tileOnError(() => {}, tileWithSrc(L.Util.emptyImageUrl), new Error('x'));
  assert.equal(imageCount(), 0, 'tidak ada permintaan tile induk');
  assert.deepEqual(calls, { load: 1, error: 1 });
});
test('Layer yang sudah dilepas dari peta / offline tidak memicu fallback', () => {
  const { L, calls } = fakeLeaflet();
  const imageCount = stubBlankCanvasAndImage();
  const layer = F.createLayer(L, 'u', {});
  layer._map = null;
  layer._tileOnLoad(() => {}, tileWithSrc('https://t/1'));
  layer._map = {};
  const nav = Object.getOwnPropertyDescriptor(global, 'navigator');
  Object.defineProperty(global, 'navigator', { value: { onLine: false }, configurable: true });
  layer._tileOnError(() => {}, tileWithSrc('https://t/2'), new Error('x'));
  if (nav) Object.defineProperty(global, 'navigator', nav); else delete global.navigator;
  assert.equal(imageCount(), 0);
  assert.deepEqual(calls, { load: 1, error: 1 });
});
test('Tile kosong asli (hitam) tetap memicu fallback ke induk', () => {
  const { L } = fakeLeaflet();
  const imageCount = stubBlankCanvasAndImage();
  const layer = F.createLayer(L, 'u', {});
  layer._tileOnLoad(() => {}, tileWithSrc('https://t/3'));
  assert.equal(imageCount(), 1);
});

console.log(failed ? `${failed} FAIL` : 'ALL PASS');
process.exit(failed ? 1 : 0);
