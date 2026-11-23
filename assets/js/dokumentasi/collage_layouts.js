/**
 * QR-SAR - Pure Collage Layout & Geometry Engine (4 to 12 Photo/Video Slots)
 * Mendukung orientasi Vertikal (Portrait), Persegi (Square), dan Horizontal (Landscape).
 * Kompatibel untuk browser (window.QrSarCollageLayouts) dan Node.js unit test (module.exports).
 */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) {
    module.exports = api;
  }
  if (root) {
    root.QrSarCollageLayouts = api;
  }
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';

  const MIN_SLOTS = 4;
  const MAX_SLOTS = 12;

  const CANVAS_PRESETS = [
    { id: 'portrait_2_3', label: '2:3 Poster Vertikal (Standar)', ratio: '2:3', orientation: 'vertical', width: 1080, height: 1620 },
    { id: 'portrait_4_5', label: '4:5 Feed Portrait', ratio: '4:5', orientation: 'vertical', width: 1080, height: 1350 },
    { id: 'portrait_9_16', label: '9:16 Story / Reels', ratio: '9:16', orientation: 'vertical', width: 1080, height: 1920 },
    { id: 'portrait_a4', label: 'A4 Vertikal Cetak', ratio: 'A4', orientation: 'vertical', width: 1240, height: 1754 },
    { id: 'square_1_1', label: '1:1 Feed Persegi', ratio: '1:1', orientation: 'square', width: 1080, height: 1080 },
    { id: 'landscape_16_9', label: '16:9 Horizontal Widescreen', ratio: '16:9', orientation: 'horizontal', width: 1920, height: 1080 },
    { id: 'landscape_3_2', label: '3:2 Poster Horizontal', ratio: '3:2', orientation: 'horizontal', width: 1620, height: 1080 },
    { id: 'landscape_4_3', label: '4:3 Presentasi Horizontal', ratio: '4:3', orientation: 'horizontal', width: 1440, height: 1080 },
  ];

  const COLLAGE_STYLES = [
    {
      id: 'klasik_kpp',
      label: 'Klasik Resmi KPP',
      desc: 'Kop & footer oranye Basarnas penuh dengan pill tanggal & website.',
    },
    {
      id: 'editorial_modern',
      label: 'Editorial Modern',
      desc: 'Kop putih bersih beraksen oranye, judul rilis pers tegas & footer navy.',
    },
  ];

  const QUALITY_PRESETS = [
    { id: 'fhd', label: 'Full HD (1080p)', scale: 1.0, jpegQuality: 0.95, videoBitsPerSecond: 6000000 },
    { id: 'qhd', label: '2K QHD (Tajam — Rekomendasi)', scale: 1.5, jpegQuality: 0.97, videoBitsPerSecond: 10000000 },
    { id: 'uhd_4k', label: '4K Ultra HD / Original', scale: 2.0, jpegQuality: 0.98, videoBitsPerSecond: 16000000 },
  ];

  function getQualityPreset(qualityId) {
    return QUALITY_PRESETS.find((q) => q.id === qualityId) || QUALITY_PRESETS[1];
  }

  function resolveExportDimensions(canvasPreset, qualityId) {
    const preset = canvasPreset || CANVAS_PRESETS[0];
    const q = getQualityPreset(qualityId);
    const width = Math.round(preset.width * q.scale);
    const height = Math.round(preset.height * q.scale);
    return {
      id: q.id,
      label: q.label,
      scale: q.scale,
      width,
      height,
      jpegQuality: q.jpegQuality,
      videoBitsPerSecond: q.videoBitsPerSecond,
    };
  }

  function formatEmergencyLine(telp, wa) {
    const rawTelp = String(telp || '').replace(/^\s*EMERGENCY\s*CALL\s*:\s*/i, '').trim();
    const telpParts = [];
    if (rawTelp) {
      rawTelp.split('/').forEach((seg) => {
        const p = seg.trim();
        if (!p) return;
        if (/^(?:emergency\s*(?:call)?\s*:?\s*)?115$/i.test(p)) return;
        telpParts.push(p);
      });
    }
    const cleanWa = String(wa || '').replace(/^\s*(?:WA|WhatsApp)\s*:?\s*/i, '').trim();
    const parts = ['115', ...telpParts];
    if (cleanWa) parts.push('WA: ' + cleanWa);
    return 'EMERGENCY CALL : ' + parts.join(' / ');
  }

  function clampSlotCount(n) {
    const num = Math.round(Number(n) || 6);
    return Math.max(MIN_SLOTS, Math.min(MAX_SLOTS, num));
  }

  /**
   * Bangun daftar slot normalisasi [0..1] dari array jumlah kolom per baris.
   * Contoh: [2, 2, 2] -> 3 baris x 2 kolom (6 slot), [2, 3] -> 2 atas + 3 bawah (5 slot).
   */
  function rowsSpec(rowCounts, rowWeights) {
    const weights = Array.isArray(rowWeights) && rowWeights.length === rowCounts.length
      ? rowWeights
      : rowCounts.map(() => 1);
    const totalW = weights.reduce((a, b) => a + b, 0);
    const slots = [];
    let curY = 0;
    for (let r = 0; r < rowCounts.length; r++) {
      const cols = rowCounts[r];
      const h = weights[r] / totalW;
      const w = 1 / cols;
      for (let c = 0; c < cols; c++) {
        slots.push({ x: c * w, y: curY, w, h });
      }
      curY += h;
    }
    return slots;
  }

  /**
   * Bangun daftar slot normalisasi [0..1] dari array jumlah baris per kolom (vertikal slice).
   * Contoh: [1, 3] dengan colWeights [1.3, 1] -> 1 hero kiri besar + 3 bertumpuk kanan.
   */
  function colsSpec(colCounts, colWeights) {
    const weights = Array.isArray(colWeights) && colWeights.length === colCounts.length
      ? colWeights
      : colCounts.map(() => 1);
    const totalW = weights.reduce((a, b) => a + b, 0);
    const slots = [];
    let curX = 0;
    for (let c = 0; c < colCounts.length; c++) {
      const rows = colCounts[c];
      const w = weights[c] / totalW;
      const h = 1 / rows;
      for (let r = 0; r < rows; r++) {
        slots.push({ x: curX, y: r * h, w, h });
      }
      curX += w;
    }
    return slots;
  }

  const LAYOUT_BANK = {
    4: [
      { id: 'grid_4_2x2', label: '2x2 Simetris', prefer: 'all', slots: rowsSpec([2, 2]) },
      { id: 'hero_4_top', label: '1 Utama Atas + 3 Bawah', prefer: 'vertical', slots: rowsSpec([1, 3], [1.45, 1]) },
      { id: 'hero_4_left', label: '1 Utama Kiri + 3 Kanan', prefer: 'horizontal', slots: colsSpec([1, 3], [1.55, 1]) },
      { id: 'strip_4_cols', label: '4 Kolom Sejajar', prefer: 'horizontal', slots: rowsSpec([4]) },
    ],
    5: [
      { id: 'grid_5_2_3', label: '2 Atas + 3 Bawah', prefer: 'vertical', slots: rowsSpec([2, 3], [1.15, 1]) },
      { id: 'grid_5_1_4', label: '1 Utama + 4 (2x2)', prefer: 'vertical', slots: rowsSpec([1, 2, 2], [1.35, 1, 1]) },
      { id: 'grid_5_left', label: '1 Utama Kiri + 4 (2x2) Kanan', prefer: 'horizontal', slots: [
        { x: 0, y: 0, w: 0.5, h: 1 },
        { x: 0.5, y: 0, w: 0.25, h: 0.5 },
        { x: 0.75, y: 0, w: 0.25, h: 0.5 },
        { x: 0.5, y: 0.5, w: 0.25, h: 0.5 },
        { x: 0.75, y: 0.5, w: 0.25, h: 0.5 },
      ] },
      { id: 'grid_5_3_2', label: '3 Atas + 2 Bawah', prefer: 'horizontal', slots: rowsSpec([3, 2], [1, 1.15]) },
    ],
    6: [
      { id: 'v_6_2x3', label: '2x3 Vertikal (Standar KPP)', prefer: 'vertical', slots: rowsSpec([2, 2, 2]) },
      { id: 'h_6_3x2', label: '3x2 Horizontal', prefer: 'horizontal', slots: rowsSpec([3, 3]) },
      { id: 'hero_6_top', label: '1 Utama + 5 Pendamping', prefer: 'vertical', slots: rowsSpec([1, 2, 3], [1.3, 1, 0.95]) },
      { id: 'hero_6_left', label: '2 Besar Kiri + 4 Kanan', prefer: 'horizontal', slots: colsSpec([2, 2, 2], [1.35, 1, 1]) },
    ],
    7: [
      { id: 'v_7_1_2_2_2', label: '1 Utama + 6 (2x3)', prefer: 'vertical', slots: rowsSpec([1, 2, 2, 2], [1.25, 1, 1, 1]) },
      { id: 'h_7_3_4', label: '3 Atas + 4 Bawah', prefer: 'horizontal', slots: rowsSpec([3, 4], [1.15, 1]) },
      { id: 'ed_7_2_3_2', label: '2 + 3 + 2 Editorial', prefer: 'all', slots: rowsSpec([2, 3, 2]) },
    ],
    8: [
      { id: 'v_8_2x4', label: '2x4 Vertikal', prefer: 'vertical', slots: rowsSpec([2, 2, 2, 2]) },
      { id: 'h_8_4x2', label: '4x2 Horizontal', prefer: 'horizontal', slots: rowsSpec([4, 4]) },
      { id: 'ed_8_2_3_3', label: '2 Utama + 6 (3x2)', prefer: 'all', slots: rowsSpec([2, 3, 3], [1.25, 1, 1]) },
    ],
    9: [
      { id: 'grid_9_3x3', label: '3x3 Simetris', prefer: 'all', slots: rowsSpec([3, 3, 3]) },
      { id: 'hero_9_top', label: '1 Utama + 8 (4x2)', prefer: 'horizontal', slots: rowsSpec([1, 4, 4], [1.4, 1, 1]) },
      { id: 'v_9_2_2_2_3', label: '6 (2x3) + 3 Bawah', prefer: 'vertical', slots: rowsSpec([2, 2, 2, 3]) },
    ],
    10: [
      { id: 'v_10_2x5', label: '2x5 Vertikal', prefer: 'vertical', slots: rowsSpec([2, 2, 2, 2, 2]) },
      { id: 'h_10_5x2', label: '5x2 Horizontal', prefer: 'horizontal', slots: rowsSpec([5, 5]) },
      { id: 'ed_10_2_4_4', label: '2 Utama + 8 (4x2)', prefer: 'all', slots: rowsSpec([2, 4, 4], [1.3, 1, 1]) },
      { id: 'ed_10_3_4_3', label: '3 + 4 + 3 Editorial', prefer: 'all', slots: rowsSpec([3, 4, 3]) },
    ],
    11: [
      { id: 'v_11_2_3_3_3', label: '2 Utama + 9 (3x3)', prefer: 'vertical', slots: rowsSpec([2, 3, 3, 3], [1.2, 1, 1, 1]) },
      { id: 'h_11_3_4_4', label: '3 + 4 + 4 Seimbang', prefer: 'horizontal', slots: rowsSpec([3, 4, 4]) },
      { id: 'ed_11_4_3_4', label: '4 + 3 + 4 Editorial', prefer: 'all', slots: rowsSpec([4, 3, 4]) },
    ],
    12: [
      { id: 'v_12_3x4', label: '3x4 Vertikal', prefer: 'vertical', slots: rowsSpec([3, 3, 3, 3]) },
      { id: 'h_12_4x3', label: '4x3 Horizontal', prefer: 'horizontal', slots: rowsSpec([4, 4, 4]) },
      { id: 'v_12_2x6', label: '2x6 Panjang Vertikal', prefer: 'vertical', slots: rowsSpec([2, 2, 2, 2, 2, 2]) },
      { id: 'h_12_6x2', label: '6x2 Lebar Horizontal', prefer: 'horizontal', slots: rowsSpec([6, 6]) },
    ],
  };

  function getCanvasPreset(presetId) {
    return CANVAS_PRESETS.find((p) => p.id === presetId) || CANVAS_PRESETS[0];
  }

  function getLayoutsForCount(count) {
    const c = clampSlotCount(count);
    return LAYOUT_BANK[c] || LAYOUT_BANK[6];
  }

  function getLayoutById(count, layoutId) {
    const list = getLayoutsForCount(count);
    return list.find((l) => l.id === layoutId) || list[0];
  }

  function getDefaultLayoutId(count, orientation) {
    const list = getLayoutsForCount(count);
    const orient = orientation || 'vertical';
    const match = list.find((l) => l.prefer === orient) || list.find((l) => l.prefer === 'all') || list[0];
    return match.id;
  }

  /**
   * Konversi slot normalisasi [0..1] menjadi koordinat piksel presisi dengan jarak gutter (gapPx).
   */
  function computeSlotRects(layoutPreset, gridBox, gapPx) {
    const gap = Math.max(0, Number(gapPx) || 0);
    const half = gap / 2;
    const gx = Number(gridBox.x) || 0;
    const gy = Number(gridBox.y) || 0;
    const gw = Math.max(1, Number(gridBox.width) || 100);
    const gh = Math.max(1, Number(gridBox.height) || 100);
    const eps = 1e-4;

    return layoutPreset.slots.map((s) => {
      const leftPad = s.x <= eps ? 0 : half;
      const rightPad = (s.x + s.w) >= 1 - eps ? 0 : half;
      const topPad = s.y <= eps ? 0 : half;
      const bottomPad = (s.y + s.h) >= 1 - eps ? 0 : half;

      const x = gx + s.x * gw + leftPad;
      const y = gy + s.y * gh + topPad;
      const w = Math.max(1, s.w * gw - leftPad - rightPad);
      const h = Math.max(1, s.h * gh - topPad - bottomPad);
      const radius = Math.max(10, Math.min(26, Math.round(Math.min(w, h) * 0.065)));

      return { x, y, w, h, radius };
    });
  }

  /**
   * Hitung source crop (object-fit: cover) dengan dukungan zoom (1.0..2.5) dan geser pan (-100..100).
   */
  function computeCoverCrop(mediaW, mediaH, slotW, slotH, zoom, offsetX, offsetY) {
    const mw = Math.max(1, Number(mediaW) || 1);
    const mh = Math.max(1, Number(mediaH) || 1);
    const swRatio = Math.max(0.01, (Number(slotW) || 1) / Math.max(1, Number(slotH) || 1));
    const mRatio = mw / mh;
    const z = Math.max(1, Math.min(3, Number(zoom) || 1));

    let baseSw = mw;
    let baseSh = mh;
    if (mRatio > swRatio) {
      baseSw = mh * swRatio;
      baseSh = mh;
    } else {
      baseSw = mw;
      baseSh = mw / swRatio;
    }

    const sw = Math.min(mw, baseSw / z);
    const sh = Math.min(mh, baseSh / z);
    const maxPanX = Math.max(0, (mw - sw) / 2);
    const maxPanY = Math.max(0, (mh - sh) / 2);
    const normX = Math.max(-100, Math.min(100, Number(offsetX) || 0)) / 100;
    const normY = Math.max(-100, Math.min(100, Number(offsetY) || 0)) / 100;

    const sx = Math.max(0, Math.min(mw - sw, (mw - sw) / 2 + normX * maxPanX));
    const sy = Math.max(0, Math.min(mh - sh, (mh - sh) / 2 + normY * maxPanY));

    return { sx, sy, sw, sh };
  }

  /**
   * Pecah teks judul ke dalam beberapa baris sesuai lebar maksimum kanvas.
   */
  function wrapCanvasLines(measureFn, text, maxWidth, maxLines) {
    const clean = String(text || '').replace(/\s+/g, ' ').trim();
    if (!clean) return [];
    const limit = Math.max(1, Number(maxLines) || 4);
    const words = clean.split(' ');
    const lines = [];
    let current = '';

    for (let i = 0; i < words.length; i++) {
      const candidate = current ? current + ' ' + words[i] : words[i];
      if (!current || measureFn(candidate) <= maxWidth) {
        current = candidate;
      } else {
        lines.push(current);
        current = words[i];
        if (lines.length === limit - 1) {
          const rest = words.slice(i).join(' ');
          let trimmed = rest;
          while (trimmed.length > 3 && measureFn(trimmed) > maxWidth) {
            trimmed = trimmed.slice(0, -1);
          }
          if (trimmed.length < rest.length) {
            trimmed = trimmed.replace(/\s+\S*$/, '').trim() + '...';
          }
          lines.push(trimmed);
          return lines;
        }
      }
    }
    if (current) lines.push(current);
    return lines.slice(0, limit);
  }

  function formatCollageFilename(hariKe, namaOperasi, ext) {
    const safeHari = String(hariKe || 'H1').toUpperCase().replace(/[^A-Z0-9]+/g, '_').replace(/^_+|_+$/g, '') || 'H1';
    const safeOps = String(namaOperasi || 'OPERASI_SAR')
      .toUpperCase()
      .replace(/[^A-Z0-9]+/g, '_')
      .replace(/_+/g, '_')
      .replace(/^_+|_+$/g, '')
      .slice(0, 60) || 'OPERASI_SAR';
    const safeExt = String(ext || 'jpg').toLowerCase().replace(/[^a-z0-9]/g, '') || 'jpg';
    return `DOK_SAR_${safeHari}_${safeOps}.${safeExt}`;
  }

  return {
    MIN_SLOTS,
    MAX_SLOTS,
    CANVAS_PRESETS,
    COLLAGE_STYLES,
    QUALITY_PRESETS,
    LAYOUT_BANK,
    clampSlotCount,
    getCanvasPreset,
    getQualityPreset,
    resolveExportDimensions,
    formatEmergencyLine,
    getLayoutsForCount,
    getLayoutById,
    getDefaultLayoutId,
    computeSlotRects,
    computeCoverCrop,
    wrapCanvasLines,
    formatCollageFilename,
  };
});
