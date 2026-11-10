/**
 * QR-SAR - Impor plotting GIS per SRU di form RENOPS (logika murni, teruji Node). window.RenopsImport
 *
 * Satu search area = satu SRU. Pola & rute dipasangkan lewat keterkaitan tersimpan (polygonId), bukan ketikan nama.
 * Penggabungan memperbarui baris SRU yang namanya sama dengan nama area (termasuk format impor lama
 * "SRU n - {nama area}"), menambah area baru di akhir, dan tidak menyentuh baris SRU manual lain.
 */
(function (global) {
  'use strict';

  const norm = (t) => String(t || '').trim().toLowerCase();

  /** @return {Array<{id: string, name: string, pattern: object|null, route: object|null}>} */
  function checklist(polygons, patterns, routes) {
    return (polygons || []).map((poly) => {
      const pattern = (patterns || []).find((p) => p.polygonId && String(p.polygonId) === String(poly.id))
        || (patterns || []).find((p) => !p.polygonId && p.polygonName && norm(p.polygonName) === norm(poly.name))
        || null;
      const route = (routes || []).find((r) => r.polygonId && String(r.polygonId) === String(poly.id)) || null;
      return { id: String(poly.id), name: String(poly.name || ''), pattern, route };
    }).sort((a, b) => a.name.localeCompare(b.name, 'id', { numeric: true, sensitivity: 'base' })); // SRU 1, 2, …, 10
  }

  function cocok(namaSru, namaArea) {
    const n = norm(namaSru);
    const a = norm(namaArea);
    if (a === '') return false;
    if (n === a) return true;
    const m = /^sru\s*\d+\s*-\s*(.+)$/i.exec(String(namaSru || '').trim());
    return !!m && norm(m[1]) === a;
  }

  /**
   * @return {object[]} larik baru; describe(item) → field SRU (minimal nama_sru). Tiap baris dicocokkan paling banyak
   * sekali (area bernama kembar tidak saling menimpa); baris baru = defaults + field area.
   */
  function mergeAreasIntoSrus(srus, items, describe, defaults = {}) {
    const out = (srus || []).map((s) => ({ ...s }));
    const used = new Set();
    (items || []).forEach((item) => {
      const fields = describe(item);
      const idx = out.findIndex((s, i) => !used.has(i) && cocok(s.nama_sru, item.name));
      if (idx >= 0) {
        out[idx] = Object.assign(out[idx], fields);
        used.add(idx);
      } else {
        out.push({ ...defaults, ...fields });
        used.add(out.length - 1);
      }
    });
    return out;
  }

  /** Panjang rute (NM) dari titik-titiknya; GisRoute tidak menyimpan panjang. */
  function routeLengthNm(points) {
    const R_NM = 3440.065;
    const rad = (d) => (Number(d) * Math.PI) / 180;
    let total = 0;
    for (let i = 1; i < (points || []).length; i++) {
      const a = points[i - 1];
      const b = points[i];
      const dLat = rad(b.lat - a.lat);
      const dLng = rad(b.lng - a.lng);
      const h = Math.sin(dLat / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2;
      total += 2 * R_NM * Math.asin(Math.min(1, Math.sqrt(h)));
    }
    return total;
  }

  const RenopsImport = { checklist, mergeAreasIntoSrus, routeLengthNm };

  global.RenopsImport = RenopsImport;
  if (typeof module !== 'undefined' && module.exports) module.exports = RenopsImport;
})(typeof window !== 'undefined' ? window : globalThis);
