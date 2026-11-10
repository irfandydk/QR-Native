/**
 * QR-SAR Tactical GIS - Fallback tile citra tanpa data (anti peta blank saat zoom).
 * File: assets/js/gis/tile_fallback.js — window.QrSarGis.TileFallback
 *
 * Di laut lepas citra satelit Google hanya ada sampai ±z16: lyrs=s mengembalikan 404, lyrs=y (hybrid) mengembalikan
 * tile hitam pekat. Tile seperti itu diganti potongan tile induk (zoom lebih rendah) yang diperbesar lewat canvas,
 * sehingga peta tetap bergambar; di darat (citra ada sampai z21) tile asli tetap dipakai.
 */
(function (global) {
  'use strict';

  const MAX_FALLBACK_LEVELS = 6;

  /** Tile induk dz tingkat di atas (x, y, z) dan area potongnya (piksel sumber) dalam tile induk 256 px. */
  function parentTile(x, y, z, dz) {
    const scale = 2 ** dz;
    const size = 256 / scale;
    return { x: Math.floor(x / scale), y: Math.floor(y / scale), z: z - dz, sx: (x % scale) * size, sy: (y % scale) * size, size };
  }

  /** Tile tanpa citra: seluruh piksel sama dan hitam pekat, atau seluruhnya transparan. data = RGBA ImageData.data. */
  function isBlankTile(data) {
    if (!data || data.length < 4) return false;
    const [r, g, b, a] = [data[0], data[1], data[2], data[3]];
    if (!(a === 0 || (r === 0 && g === 0 && b === 0))) return false;
    for (let i = 4; i < data.length; i += 4) {
      if (data[i] !== r || data[i + 1] !== g || data[i + 2] !== b || data[i + 3] !== a) return false;
    }
    return true;
  }

  function readPixels(img) {
    try {
      const c = document.createElement('canvas');
      c.width = c.height = 256;
      const g = c.getContext('2d');
      g.drawImage(img, 0, 0, 256, 256);
      return g.getImageData(0, 0, 256, 256).data;
    } catch (e) {
      return null; // tanpa CORS: anggap tile valid
    }
  }

  /** L.tileLayer dengan fallback ke tile induk untuk tile error/kosong. Butuh Leaflet 1.9 & tile ber-CORS. */
  function createLayer(L, url, options) {
    const Layer = L.TileLayer.extend({
      createTile(coords, done) {
        const tile = L.TileLayer.prototype.createTile.call(this, coords, done);
        tile._qrCoords = { x: coords.x, y: coords.y, z: this._getZoomForUrl() };
        return tile;
      },
      // Tile yang dibuang/dibatalkan Leaflet (src = emptyImageUrl), layer yang sudah dilepas, saat offline,
      // atau tile yang sudah selesai: langsung ke penanganan asli (tanpa baca piksel & tanpa permintaan induk).
      _qrSkip(tile) {
        return !this._map || !tile._qrCoords || tile._qrFallbackDone
          || (tile.getAttribute && tile.getAttribute('src') === L.Util.emptyImageUrl)
          || (typeof navigator !== 'undefined' && navigator.onLine === false);
      },
      _tileOnLoad(done, tile) {
        if (!this._qrSkip(tile)) {
          const px = readPixels(tile);
          if (px && isBlankTile(px)) {
            this._qrFallback(done, tile, 1);
            return;
          }
          tile._qrFallbackDone = true;
        }
        L.TileLayer.prototype._tileOnLoad.call(this, done, tile);
      },
      _tileOnError(done, tile, e) {
        if (!this._qrSkip(tile)) {
          this._qrFallback(done, tile, 1, e);
          return;
        }
        L.TileLayer.prototype._tileOnError.call(this, done, tile, e);
      },
      _qrFallback(done, tile, dz, e) {
        const c = tile._qrCoords;
        if (dz > MAX_FALLBACK_LEVELS || c.z - dz < (this.options.minNativeZoom || 0)) {
          tile._qrFallbackDone = true;
          L.TileLayer.prototype._tileOnError.call(this, done, tile, e || new Error('Tile tanpa citra'));
          return;
        }
        const p = parentTile(c.x, c.y, c.z, dz);
        const src = L.Util.template(this._url, L.Util.extend({
          r: '', s: this._getSubdomain({ x: p.x, y: p.y }), x: p.x, y: p.y, z: p.z,
        }, this.options));
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
          // Tile dibuang/dibatalkan selama menunggu induk: jangan isi ulang & jangan tandai selesai.
          if (!this._map || (tile.getAttribute && tile.getAttribute('src') === L.Util.emptyImageUrl)) return;
          const px = readPixels(img);
          if (px && isBlankTile(px)) {
            this._qrFallback(done, tile, dz + 1, e);
            return;
          }
          try {
            const cv = document.createElement('canvas');
            cv.width = cv.height = 256;
            cv.getContext('2d').drawImage(img, p.sx, p.sy, p.size, p.size, 0, 0, 256, 256);
            tile._qrFallbackDone = true;
            tile.src = cv.toDataURL('image/jpeg', 0.9); // onload berikutnya → _tileOnLoad asli
          } catch (err) {
            tile._qrFallbackDone = true;
            L.TileLayer.prototype._tileOnError.call(this, done, tile, err);
          }
        };
        img.onerror = () => {
          if (!this._map || (tile.getAttribute && tile.getAttribute('src') === L.Util.emptyImageUrl)) return;
          this._qrFallback(done, tile, dz + 1, e);
        };
        img.src = src;
      },
    });
    return new Layer(url, L.Util.extend({ crossOrigin: true }, options));
  }

  const TileFallback = { MAX_FALLBACK_LEVELS, parentTile, isBlankTile, createLayer };

  global.QrSarGis = global.QrSarGis || {};
  global.QrSarGis.TileFallback = TileFallback;
  if (typeof module !== 'undefined' && module.exports) module.exports = TileFallback;
})(typeof window !== 'undefined' ? window : globalThis);
