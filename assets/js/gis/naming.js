/**
 * QR-SAR Tactical GIS - Pengelompokan titik & penamaan otomatis objek peta (logika murni, teruji Node).
 * File: assets/js/gis/naming.js — window.QrSarGis.Naming
 */
(function (global) {
  'use strict';

  // Kelompok daftar titik (urutan tampil). Kantor/POS/USS digabung agar titik lain tidak tenggelam.
  const POINT_GROUPS = [
    { key: 'pangkalan', label: 'Pangkalan SAR (Kantor/POS/USS)', categories: ['Kantor SAR', 'POS SAR', 'Unit Siaga SAR'] },
    { key: 'dermaga', label: 'Dermaga SAR', categories: ['Dermaga SAR'] },
    { key: 'lkp', label: 'LKP', categories: ['LKP'] },
    { key: 'korban', label: 'Penemuan Korban', categories: ['Penemuan Korban', 'Lokasi Penemuan Korban'] },
    { key: 'posko', label: 'Posko', categories: ['Posko'] },
    { key: 'entry_exit', label: 'Entry / Exit Poin', categories: ['Entry Poin', 'Exit Poin'] },
    { key: 'lain', label: 'Tanpa Kategori', categories: [] },
  ];

  function groupKeyOf(category) {
    const g = POINT_GROUPS.find((x) => x.categories.includes(String(category || '')));
    return g ? g.key : 'lain';
  }

  /** @return {Array<{key: string, label: string, points: object[]}>} hanya kelompok berisi, urutan titik dipertahankan */
  function groupPoints(points) {
    return POINT_GROUPS.map((g) => ({
      key: g.key,
      label: g.label,
      points: (points || []).filter((p) => groupKeyOf(p && p.category) === g.key),
    })).filter((g) => g.points.length > 0);
  }

  // ── Penamaan otomatis (hanya dipakai bila nama kosong; nama ketikan operator tidak pernah ditimpa) ──
  const escapeRe = (t) => String(t).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  /** Bilangan bulat terkecil ≥1 yang belum dipakai nama berbentuk "<prefix> N" (tak peka huruf besar/kecil). */
  function nextFree(names, prefix) {
    const re = new RegExp('^' + escapeRe(prefix) + ' (\\d+)$', 'i');
    const used = new Set((names || []).map((n) => {
      const m = re.exec(String(n || '').trim());
      return m ? parseInt(m[1], 10) : null;
    }).filter((n) => n !== null));
    let n = 1;
    while (used.has(n)) n++;
    return n;
  }

  function nextSruName(polygons) {
    return 'SRU ' + nextFree((polygons || []).map((p) => p && p.name), 'SRU');
  }

  const PATTERN_ABBR = { parallel_track: 'PS', creeping_line: 'CS', e_sar: 'E-SAR', sector_search: 'E-SAR' };

  function patternAutoName(areaName, patternType) {
    const area = String(areaName || '').trim() || 'Datum LKP';
    return `Pola Pencarian Area ${area} (${PATTERN_ABBR[patternType] || 'PS'})`;
  }

  function isAutoPatternName(name) {
    return /^Pola Pencarian Area .+ \((PS|CS|E-SAR)\)$/.test(String(name || '').trim());
  }

  function routeAutoName(areaName, routes) {
    const area = String(areaName || '').trim();
    return area ? `Rute ke ${area}` : 'Rute ' + nextFree((routes || []).map((r) => r && r.name), 'Rute');
  }

  function pointAutoName(category, points) {
    const cat = String(category || '').trim() || 'Tanpa Kategori';
    if (cat === 'LKP') return 'LKP';
    const prefix = cat === 'Tanpa Kategori' ? 'Titik' : cat;
    return `${prefix} ` + nextFree((points || []).map((p) => p && p.label), prefix);
  }

  const Naming = { POINT_GROUPS, groupKeyOf, groupPoints, nextSruName, patternAutoName, isAutoPatternName, routeAutoName, pointAutoName };

  global.QrSarGis = global.QrSarGis || {};
  global.QrSarGis.Naming = Naming;
  if (typeof module !== 'undefined' && module.exports) module.exports = Naming;
})(typeof window !== 'undefined' ? window : globalThis);
