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

  function extractImportGroup(item) {
    if (!item || typeof item !== 'object') return '';
    const raw = String(item.import_group || item.importGroup || '').trim();
    if (raw !== '') return raw;
    const cat = String(item.category || '').trim();
    if (/^Import\s+\d+$/i.test(cat)) return cat;
    return '';
  }

  /** @return {Array<{key: string, label: string, isImportGroup?: boolean, importGroup?: string, points: object[]}>} hanya kelompok berisi, urutan titik dipertahankan */
  function groupPoints(points) {
    const regularPts = [];
    const importMap = new Map();

    (points || []).forEach((p) => {
      const impGrp = extractImportGroup(p);
      if (impGrp !== '') {
        if (!importMap.has(impGrp)) importMap.set(impGrp, []);
        importMap.get(impGrp).push(p);
      } else {
        regularPts.push(p);
      }
    });

    const baseGroups = POINT_GROUPS.map((g) => ({
      key: g.key,
      label: g.label,
      isImportGroup: false,
      points: regularPts.filter((p) => groupKeyOf(p && p.category) === g.key),
    })).filter((g) => g.points.length > 0);

    const importGroups = [];
    importMap.forEach((pts, grpName) => {
      importGroups.push({
        key: 'import:' + grpName,
        label: grpName,
        isImportGroup: true,
        importGroup: grpName,
        points: pts,
      });
    });

    return baseGroups.concat(importGroups);
  }

  /** @return {Array<{key: string, label: string, isImportGroup: boolean, importGroup?: string, items: object[]}>} */
  function groupEntities(items, defaultLabel = 'Daftar Item', defaultKey = 'default') {
    const regularItems = [];
    const importMap = new Map();

    (items || []).forEach((item) => {
      const impGrp = extractImportGroup(item);
      if (impGrp !== '') {
        if (!importMap.has(impGrp)) importMap.set(impGrp, []);
        importMap.get(impGrp).push(item);
      } else {
        regularItems.push(item);
      }
    });

    const groups = [];
    if (regularItems.length > 0) {
      groups.push({
        key: defaultKey,
        label: defaultLabel,
        isImportGroup: false,
        items: regularItems,
      });
    }

    importMap.forEach((grpItems, grpName) => {
      groups.push({
        key: 'import:' + grpName,
        label: grpName,
        isImportGroup: true,
        importGroup: grpName,
        items: grpItems,
      });
    });

    return groups;
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

  const Naming = { POINT_GROUPS, groupKeyOf, extractImportGroup, groupPoints, groupEntities, nextSruName, patternAutoName, isAutoPatternName, routeAutoName, pointAutoName };

  global.QrSarGis = global.QrSarGis || {};
  global.QrSarGis.Naming = Naming;
  if (typeof module !== 'undefined' && module.exports) module.exports = Naming;
})(typeof window !== 'undefined' ? window : globalThis);
