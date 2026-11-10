// Data state initialized from window.__RENOPS_PAGE_CONFIG__
const renopsCfg = window.__RENOPS_PAGE_CONFIG__ || {};
let currentUnsurList = renopsCfg.initialUnsurData || [];
let currentAlutList = renopsCfg.initialAlutData || [];
let currentSrus = renopsCfg.initialSrus || [];
let SAR_GIS_BUNDLE = renopsCfg.gisBundle || { points: [], polygons: [], routes: [], searchPatterns: [] };
const idOperasi = renopsCfg.idOperasi || 0;
const isRenopsEditMode = !!renopsCfg.editMode;
const RENOPS_DRAFT_KEY = 'qr_sar_renops_draft_' + idOperasi;
const OPERASI_START_DATE = renopsCfg.operasiStartDate || null;
const OPERASI_REOPEN_DATE = renopsCfg.operasiReopenDate || null;
const OPERASI_BASE_HARI_REOPEN = renopsCfg.operasiBaseHariReopen || 0;

// Escape HTML utility (Unified with QrSarUtils)
const escapeHtml = (window.QrSarUtils && window.QrSarUtils.escapeHtml) || function(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
};
// Argumen string untuk handler onclick: JSON literal lalu di-escape untuk atribut HTML
const jsArg = (v) => escapeHtml(JSON.stringify(String(v ?? '')));

// Master Measurement Unit state (NM, KM, M)
let activeRenopsUnit = (function() {
  const fromCfg = renopsCfg.satuanJarak ? String(renopsCfg.satuanJarak).toUpperCase() : null;
  if (fromCfg && ['NM', 'KM', 'M'].includes(fromCfg)) return fromCfg;
  try {
    const fromStorage = localStorage.getItem('qr_sar_renops_unit') || localStorage.getItem('qr_sar_gis_unit');
    if (fromStorage && ['NM', 'KM', 'M'].includes(fromStorage.toUpperCase())) return fromStorage.toUpperCase();
  } catch (e) {}
  return 'NM';
})();

// Precision Mathematical Constants & Unit Formatters
const KM_PER_NM = 1.852;
const NM_PER_KM = 1 / 1.852; // ~0.539957
const KM2_PER_NM2 = 1.852 * 1.852; // 3.429904
const NM2_PER_KM2 = 1 / KM2_PER_NM2; // ~0.291553

function formatRenopsArea(sqNm) {
  const numNm2 = parseFloat(sqNm) || 0;
  const numKm2 = numNm2 * KM2_PER_NM2;
  const sqM = numKm2 * 1000000;
  if (activeRenopsUnit === 'KM') {
    return `${numKm2.toFixed(2)} KM² (${numNm2.toFixed(2)} NM²)`;
  } else if (activeRenopsUnit === 'M') {
    return `${Math.round(sqM).toLocaleString('id-ID')} m² (${numNm2.toFixed(2)} NM²)`;
  }
  return `${numNm2.toFixed(2)} NM² (${numKm2.toFixed(2)} KM²)`;
}

function formatRenopsAreaShort(sqNm) {
  const numNm2 = parseFloat(sqNm) || 0;
  const numKm2 = numNm2 * KM2_PER_NM2;
  const sqM = numKm2 * 1000000;
  if (activeRenopsUnit === 'KM') {
    return `${numKm2.toFixed(2)} KM²`;
  } else if (activeRenopsUnit === 'M') {
    return `${Math.round(sqM).toLocaleString('id-ID')} m²`;
  }
  return `${numNm2.toFixed(2)} NM²`;
}

function formatRenopsDistance(distNm) {
  const numNm = parseFloat(distNm) || 0;
  const numKm = numNm * KM_PER_NM;
  const numM = numKm * 1000;
  if (activeRenopsUnit === 'KM') {
    return `${numKm.toFixed(2)} km (${numNm.toFixed(2)} NM)`;
  } else if (activeRenopsUnit === 'M') {
    return `${Math.round(numM).toLocaleString('id-ID')} m (${numNm.toFixed(2)} NM)`;
  }
  return `${numNm.toFixed(2)} NM (${numKm.toFixed(2)} km)`;
}

function formatRenopsDistanceShort(distNm) {
  const numNm = parseFloat(distNm) || 0;
  const numKm = numNm * KM_PER_NM;
  const numM = numKm * 1000;
  if (activeRenopsUnit === 'KM') {
    return `${numKm.toFixed(1)} km`;
  } else if (activeRenopsUnit === 'M') {
    return `${Math.round(numM)} m`;
  }
  return `${numNm.toFixed(1)} NM`;
}

function formatRenopsSpacingShort(spacingNm) {
  const numNm = parseFloat(spacingNm) || 0;
  const numKm = numNm * KM_PER_NM;
  const numM = numKm * 1000;
  if (activeRenopsUnit === 'KM') {
    return numKm < 1 ? `${Math.round(numM)} m` : `${numKm.toFixed(2)} km`;
  } else if (activeRenopsUnit === 'M') {
    return `${Math.round(numM)} m`;
  }
  return `${numNm.toFixed(2)} NM`;
}

function formatRenopsSpeed(speedKnots) {
  const numKts = parseFloat(speedKnots) || 0;
  const numKmh = numKts * KM_PER_NM;
  if (activeRenopsUnit === 'KM') {
    return `${numKmh.toFixed(0)} km/jam (${numKts.toFixed(0)} Knots)`;
  } else if (activeRenopsUnit === 'M') {
    const numMs = (numKmh * 1000) / 3600;
    return `${numMs.toFixed(1)} m/s (${numKts.toFixed(0)} Knots)`;
  }
  return `${numKts.toFixed(0)} Knots (${numKmh.toFixed(0)} km/jam)`;
}

function setRenopsUnit(unit, isInitial = false) {
  if (!['NM', 'KM', 'M'].includes(unit)) unit = 'NM';
  const prevUnit = activeRenopsUnit;
  activeRenopsUnit = unit;

  try {
    localStorage.setItem('qr_sar_renops_unit', unit);
    localStorage.setItem('qr_sar_gis_unit', unit);
  } catch (e) {}

  // Update hidden input for form submission
  const hiddenInp = document.getElementById('renops_satuan_jarak');
  if (hiddenInp) hiddenInp.value = unit;

  // Update Section 2 Header switcher buttons
  document.querySelectorAll('.renops-unit-btn').forEach(btn => {
    const bUnit = btn.getAttribute('data-renops-unit');
    if (bUnit === unit) {
      btn.className = 'renops-unit-btn px-2.5 py-1 rounded-md transition cursor-pointer font-bold bg-orange-500 text-white shadow-xs';
    } else {
      btn.className = 'renops-unit-btn px-2.5 py-1 rounded-md transition cursor-pointer font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white';
    }
  });

  // Update modal label indicator
  document.querySelectorAll('.renops-active-unit-label').forEach(el => {
    el.textContent = unit;
  });

  if (!isInitial && prevUnit !== unit) {
    recalculateAllSrusByUnit(unit, prevUnit);
  }
}

function recalculateAllSrusByUnit(unit, prevUnit) {
  const polys = SAR_GIS_BUNDLE.polygons || [];
  const patterns = SAR_GIS_BUNDLE.searchPatterns || [];

  currentSrus.forEach((s, idx) => {
    // 1. Polygon / Area calculation
    const matchedPoly = polys.find((p, pIdx) => {
      if (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase())) return true;
      if (s.koordinat_area && p.name && s.koordinat_area.toLowerCase().includes(p.name.toLowerCase())) return true;
      if (idx === pIdx && polys.length === currentSrus.length) return true;
      return false;
    });

    if (matchedPoly) {
      const aNum = calculatePolygonAreaNm2(matchedPoly);
      if (aNum > 0) {
        s.luas_area = formatRenopsAreaShort(aNum);
      }
      if (matchedPoly.shapeType === 'circle') {
        const coordsText = getPolygonCoordsText(matchedPoly);
        s.koordinat_area = `Sektor: ${matchedPoly.name} (${s.luas_area})\n${coordsText}`;
      } else if (s.koordinat_area && s.koordinat_area.startsWith('Sektor:')) {
        const coordsText = getPolygonCoordsText(matchedPoly);
        s.koordinat_area = `Sektor: ${matchedPoly.name} (${s.luas_area})\nBatas Koordinat:\n${coordsText}`;
      }
    } else if (s.luas_area) {
      // Numerical regex extraction for manual areas
      const match = s.luas_area.match(/([0-9]+(?:\.[0-9]+)?)\s*(nm²|km²|m²)/i);
      if (match) {
        const val = parseFloat(match[1]);
        const u = match[2].toUpperCase();
        let valNm2 = val;
        if (u === 'KM²') valNm2 = val / KM2_PER_NM2;
        else if (u === 'M²') valNm2 = (val / 1000000) / KM2_PER_NM2;
        s.luas_area = formatRenopsAreaShort(valNm2);
      }
    }

    // 2. Pattern calculation
    const matchedPattern = patterns.find((p, pIdx) => {
      if (s.nama_sru && p.polygonName && s.nama_sru.toLowerCase().includes(p.polygonName.toLowerCase())) return true;
      if (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase())) return true;
      if (idx === pIdx && patterns.length === currentSrus.length) return true;
      return false;
    });

    if (matchedPattern) {
      const isEsar = (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search');
      const legs = matchedPattern.legsCount || (matchedPattern.waypoints ? Math.max(1, Math.floor(matchedPattern.waypoints.length / 2)) : 6);
      const spacing = formatRenopsSpacingShort(matchedPattern.trackSpacingNm || 1.0);
      const dist = matchedPattern.totalDistanceNm ? formatRenopsDistanceShort(matchedPattern.totalDistanceNm) : '-';
      s.total_sapuan = isEsar
        ? `${legs} Personel E-SAR (Spasi: ${spacing}, Total: ${dist})`
        : `${legs} Sapuan (Spasi: ${spacing}, Total: ${dist})`;
      s.track_spacing = spacing;
    } else if (s.total_sapuan) {
      // Numerical regex conversion for manual strings
      const spMatch = s.total_sapuan.match(/spasi:\s*([0-9]+(?:\.[0-9]+)?)\s*(nm|km|m)?/i);
      const distMatch = s.total_sapuan.match(/total:\s*([0-9]+(?:\.[0-9]+)?)\s*(nm|km|m)?/i);
      if (spMatch || distMatch) {
        let newTotal = s.total_sapuan;
        if (spMatch) {
          const rawSp = parseFloat(spMatch[1]);
          const spU = (spMatch[2] || prevUnit || 'NM').toUpperCase();
          let spNm = rawSp;
          if (spU === 'KM') spNm = rawSp / KM_PER_NM;
          else if (spU === 'M') spNm = rawSp / 1852;
          const newSpStr = formatRenopsSpacingShort(spNm);
          newTotal = newTotal.replace(spMatch[0], `Spasi: ${newSpStr}`);
        }
        if (distMatch) {
          const rawDist = parseFloat(distMatch[1]);
          const distU = (distMatch[2] || prevUnit || 'NM').toUpperCase();
          let distNm = rawDist;
          if (distU === 'KM') distNm = rawDist / KM_PER_NM;
          else if (distU === 'M') distNm = rawDist / 1852;
          const newDistStr = formatRenopsDistanceShort(distNm);
          newTotal = newTotal.replace(distMatch[0], `Total: ${newDistStr}`);
        }
        s.total_sapuan = newTotal;
      }
    }
  });

  renderSruList();
  if (typeof renderGisImportContent === 'function') {
    try { renderGisImportContent(); } catch (e) {}
  }
  if (typeof showToast === 'function') {
    const unitNames = { 'NM': 'Nautical Miles (NM)', 'KM': 'Kilometer (KM)', 'M': 'Meter (M)' };
    showToast(`Satuan jarak RENOPS diubah ke ${unitNames[unit] || unit}. Data dan perhitungan diperbarui!`, 'success');
  }
}

function initRenopsUnitSwitcher() {
  document.querySelectorAll('.renops-unit-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const u = btn.getAttribute('data-renops-unit');
      if (u) setRenopsUnit(u);
    });
  });
  // Initialize with activeRenopsUnit
  setRenopsUnit(activeRenopsUnit, true);
}

// ── SRU Sector Manager ──
function renderSruList() {
  const container = document.getElementById('sru-container');
  if (!container) return;
  container.innerHTML = '';

  if (currentSrus.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada pembagian unit SRU. Klik tombol "+ Tambah SRU" di atas untuk menambahkan sektor pencarian.
      </div>
    `;
    return;
  }

  currentSrus.forEach((s, idx) => {
    // Auto-detect and sync area, pattern, and sweep from GIS plotting if missing or legacy placeholder
    if (!s.luas_area || s.luas_area === '10 Nm²' || s.luas_area === '10 NM²' || s.luas_area.trim() === '') {
      const matchedPoly = (SAR_GIS_BUNDLE.polygons || []).find((p, pIdx) => {
        if (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase())) return true;
        if (idx === pIdx && (SAR_GIS_BUNDLE.polygons || []).length === currentSrus.length) return true;
        return false;
      });
      if (matchedPoly) {
        const aNum = calculatePolygonAreaNm2(matchedPoly);
        if (aNum > 0) {
          s.luas_area = formatRenopsAreaShort(aNum);
        }
      }
    }

    if (!s.total_sapuan) {
      const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find((p, pIdx) => {
        if (s.nama_sru && p.polygonName && s.nama_sru.toLowerCase().includes(p.polygonName.toLowerCase())) return true;
        if (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase())) return true;
        if (idx === pIdx && (SAR_GIS_BUNDLE.searchPatterns || []).length === currentSrus.length) return true;
        return false;
      });
      if (matchedPattern) {
        const isEsar = (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search');
        const legs = matchedPattern.legsCount || (matchedPattern.waypoints ? Math.max(1, Math.floor(matchedPattern.waypoints.length / 2)) : 6);
        const spacing = formatRenopsSpacingShort(matchedPattern.trackSpacingNm || 1.0);
        const dist = matchedPattern.totalDistanceNm ? formatRenopsDistanceShort(matchedPattern.totalDistanceNm) : '-';
        s.total_sapuan = isEsar
          ? `${legs} Personel E-SAR (Spasi: ${spacing}, Total: ${dist})`
          : `${legs} Sapuan (Spasi: ${spacing}, Total: ${dist})`;
        s.track_spacing = spacing;
        if (matchedPattern.patternType === 'creeping_line') s.pola_pencarian = 'Creeping Line Search';
        else if (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search') s.pola_pencarian = 'Pola Pencarian E-SAR';
        else if (matchedPattern.patternType === 'track_line') s.pola_pencarian = 'Track Line Search';
        else s.pola_pencarian = 'Parallel Track Search';
      }
    }

    const card = document.createElement('div');
    card.className = 'p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-3';

    const luasVal = (s.luas_area && s.luas_area.trim() !== '' && s.luas_area !== '10 Nm²' && s.luas_area !== '10 NM²') ? s.luas_area : (s.luas_area || '');
    const polaVal = s.pola_pencarian || '';
    const sapuanVal = s.total_sapuan || '';

    const polygonsList = SAR_GIS_BUNDLE.polygons || [];
    const polyOptionsHtml = polygonsList.length > 0
      ? `<option value="">-- Pilih Sektor dari Plotting GIS (${polygonsList.length} Sektor Tersedia) --</option>` +
        polygonsList.map(p => {
          const aNum = calculatePolygonAreaNm2(p);
          const areaDisplay = formatRenopsAreaShort(aNum);
          const isMatched = (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase()));
          const shapeIcon = p.shapeType === 'circle' ? '⭕' : (p.shapeType === 'rectangle' ? '⏹️' : '📐');
          return `<option value="${escapeHtml(p.id)}" ${isMatched ? 'selected' : ''}>${shapeIcon} ${escapeHtml(p.name)} (${areaDisplay})</option>`;
        }).join('')
      : `<option value="">(Belum ada search area di plotting GIS)</option>`;

    card.innerHTML = `
      <div class="flex items-center justify-between pb-2 border-b border-slate-200/80 dark:border-slate-800">
        <div class="flex flex-wrap items-center gap-2">
          <span class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-blue-500/15 text-blue-600 dark:text-blue-400">
            SRU ${idx + 1}
          </span>
          <input type="text" name="srus[${idx}][nama_sru]" value="${escapeHtml(s.nama_sru || '')}" placeholder="Nama SRU (contoh: SRU 1 Kapal RIB)" required class="control control-sm font-bold" onchange="currentSrus[${idx}].nama_sru = this.value">
          <button type="button" onclick="openGisImportModal(${idx})" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400 hover:text-amber-500 bg-amber-500/10 hover:bg-amber-500/20 px-2.5 py-1 rounded-lg border border-amber-500/25 transition cursor-pointer shadow-2xs" title="Buka modal import GIS lengkap untuk SRU ini">
            <span>🗺️</span>
            <span>Import Plotting</span>
          </button>
        </div>
        <button type="button" onclick="removeSruRow(${idx})" class="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition cursor-pointer" title="Hapus SRU">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Quick Sector Linking Dropdown (Deteksi Langsung Search Area Baru) -->
      <div class="flex items-center gap-2 p-2 rounded-xl bg-orange-500/5 dark:bg-orange-500/10 border border-orange-500/20 text-xs">
        <span class="font-bold text-[11px] text-orange-600 dark:text-orange-400 uppercase tracking-wider shrink-0 flex items-center gap-1">
          <span>📐</span>
          <span>Sektor Plotting GIS:</span>
        </span>
        <select onchange="applySektorToPlotting(${idx}, this.value)" class="control control-sm flex-1 cursor-pointer">
          ${polyOptionsHtml}
        </select>
      </div>

      <!-- Ringkasan Data Otomatis Taktis (Luas Area, Pola Pencarian & Total Sapuan) -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-xs">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 text-sm">
            📐
          </div>
          <div class="min-w-0">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Luas Area Pencarian</span>
            <span class="font-mono font-bold text-xs ${luasVal ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 italic'}">
              ${luasVal ? `✓ ${escapeHtml(luasVal)}` : 'Belum Terdeteksi'}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0 text-sm">
            ⚡
          </div>
          <div class="min-w-0">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Pola Pencarian</span>
            <span class="font-bold text-xs ${polaVal ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400 italic'} truncate block">
              ${polaVal ? `✓ ${escapeHtml(polaVal)}` : '- (Belum ditentukan / Pilih dari Peta Plotting)'}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 text-sm">
            🔁
          </div>
          <div class="min-w-0">
            <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Sapuan / Lintasan</span>
            <span class="font-mono font-semibold text-[11px] ${sapuanVal ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 italic'} truncate block" title="${escapeHtml(sapuanVal)}">
              ${sapuanVal ? `✓ ${escapeHtml(sapuanVal)}` : 'Otomatis dari Pola SAR'}
            </span>
          </div>
        </div>
      </div>

      <!-- Hidden inputs for automated values -->
      <input type="hidden" name="srus[${idx}][id_sru]" value="${s.id_sru || ''}">
      <input type="hidden" name="srus[${idx}][luas_area]" value="${escapeHtml(s.luas_area || '')}">
      <input type="hidden" name="srus[${idx}][pola_pencarian]" value="${escapeHtml(s.pola_pencarian || '')}">
      <input type="hidden" name="srus[${idx}][total_sapuan]" value="${escapeHtml(s.total_sapuan || '')}">
      <input type="hidden" name="srus[${idx}][track_spacing]" value="${escapeHtml(s.track_spacing || '')}">

      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-[11px] font-bold text-slate-400 uppercase tracking-wider">
            Koordinat Batas Sektor & Rincian Tugas
          </label>
          <span class="text-[11px] text-slate-400 font-mono">Format Standar Basarnas DMS</span>
        </div>
        <textarea name="srus[${idx}][koordinat_area]" rows="3" placeholder="Koordinat batas sektor / tugas penyisiran (Otomatis diambil dari peta plotting)..." class="control control-sm w-full font-mono leading-relaxed" onchange="currentSrus[${idx}].koordinat_area = this.value">${escapeHtml(s.koordinat_area || '')}</textarea>
      </div>
    `;
    container.appendChild(card);
  });
}

function applySektorToPlotting(targetIdx, polyId) {
  if (targetIdx < 0 || targetIdx >= currentSrus.length) return;
  if (!polyId) return;

  const poly = (SAR_GIS_BUNDLE.polygons || []).find(p => String(p.id) === String(polyId));
  if (!poly) return;

  const areaNum = calculatePolygonAreaNm2(poly);
  const coordsText = getPolygonCoordsText(poly);
  const areaFormatted = formatRenopsAreaShort(areaNum);

  currentSrus[targetIdx].nama_sru = `SRU ${targetIdx + 1} - ${poly.name}`;
  currentSrus[targetIdx].luas_area = areaFormatted;
  currentSrus[targetIdx].koordinat_area = `Sektor: ${poly.name} (${areaFormatted})\n${coordsText}`;

  // Match pattern jika ada
  const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find(pat =>
    (pat.polygonId && String(pat.polygonId) === String(poly.id)) ||
    (pat.polygonName && poly.name && pat.polygonName.toLowerCase() === poly.name.toLowerCase())
  );

  if (matchedPattern) {
    const isEsar = (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search');
    const legs = matchedPattern.legsCount || (matchedPattern.waypoints ? Math.max(1, Math.floor(matchedPattern.waypoints.length / 2)) : 6);
    const spacing = formatRenopsSpacingShort(matchedPattern.trackSpacingNm || 1.0);
    const dist = matchedPattern.totalDistanceNm ? formatRenopsDistanceShort(matchedPattern.totalDistanceNm) : '-';
    currentSrus[targetIdx].total_sapuan = isEsar
      ? `${legs} Personel E-SAR (Spasi: ${spacing}, Total: ${dist})`
      : `${legs} Sapuan (Spasi: ${spacing}, Total: ${dist})`;
    currentSrus[targetIdx].track_spacing = spacing;
    if (matchedPattern.patternType === 'creeping_line') currentSrus[targetIdx].pola_pencarian = 'Creeping Line Search';
    else if (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search') currentSrus[targetIdx].pola_pencarian = 'Pola Pencarian E-SAR';
    else if (matchedPattern.patternType === 'track_line') currentSrus[targetIdx].pola_pencarian = 'Track Line Search';
    else currentSrus[targetIdx].pola_pencarian = 'Parallel Track Search';
  } else {
    currentSrus[targetIdx].total_sapuan = '';
    currentSrus[targetIdx].track_spacing = '';
    currentSrus[targetIdx].pola_pencarian = '';
  }

  renderSruList();
  if (typeof showToast === 'function') {
    showToast(`Sektor "${poly.name}" berhasil diterapkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

// Tombol "Sinkronkan Sektor Plotting ke SRU": alur yang sama dengan "Impor per SRU" (menggabung, SRU manual tetap).
async function syncAllPlottingSectorsToSru() {
  if ((SAR_GIS_BUNDLE.polygons || []).length === 0) {
    await refreshGisBundle(false);
  }
  importAllPolygonsToSrus();
}
window.syncAllPlottingSectorsToSru = syncAllPlottingSectorsToSru;
window.applySektorToPlotting = applySektorToPlotting;

function addSruRow() {
  const num = currentSrus.length + 1;
  currentSrus.push({
    nama_sru: `SRU ${num}`,
    koordinat_area: '',
    luas_area: '',
    pola_pencarian: '',
    total_sapuan: ''
  });
  renderSruList();
}

function removeSruRow(idx) {
  currentSrus.splice(idx, 1);
  renderSruList();
}

// ── Unsur SAR Terlibat Editor (Reorderable + Master Data Reference) ──
let unsurSortable = null;

// Drag-reorder (SortableJS, mouse & sentuh): urutan baru dibaca dari atribut indeks di DOM.
function reorderFromDom(container, attr, list) {
  return Array.from(container.querySelectorAll(`[${attr}]`)).map((el) => list[parseInt(el.getAttribute(attr), 10)]).filter(Boolean);
}

function renderUnsurList() {
  const container = document.getElementById('unsur-items-container');
  if (!container) return;
  if (unsurSortable) { unsurSortable.destroy(); unsurSortable = null; }
  container.innerHTML = '';

  let totalPersonil = 0;

  if (currentUnsurList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada unsur SAR yang ditambahkan. Gunakan dropdown Master Data atau input manual di atas.
      </div>
    `;
  } else {
    currentUnsurList.forEach((item, idx) => {
      const jml = parseInt(item.jumlah_personil, 10) || 1;
      totalPersonil += jml;

      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.setAttribute('data-unsur-idx', String(idx));
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="drag-handle text-slate-400 hover:text-slate-700 dark:hover:text-white px-0.5 select-none cursor-grab active:cursor-grabbing" style="cursor:grab;touch-action:none" title="Geser untuk mengubah urutan">⋮⋮</span>
          <span class="w-5 h-5 rounded-full bg-purple-500/15 text-purple-600 dark:text-purple-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_unsur || '')}" onchange="updateUnsurItem(${idx}, 'nama_unsur', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="flex items-center gap-1">
            <input type="number" min="1" max="999" value="${jml}" onchange="updateUnsurItem(${idx}, 'jumlah_personil', parseInt(this.value, 10) || 1)" class="control control-sm w-12 font-bold text-center text-purple-600 dark:text-purple-400" />
            <span class="text-xs font-medium text-slate-400">Org</span>
          </div>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="removeUnsurRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Unsur">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
      container.appendChild(row);
    });

    if (typeof Sortable !== 'undefined') {
      unsurSortable = new Sortable(container, {
        handle: '.drag-handle',
        animation: 150,
        onEnd: () => {
          currentUnsurList = reorderFromDom(container, 'data-unsur-idx', currentUnsurList);
          renderUnsurList();
        },
      });
    }
  }

  const badgeEl = document.getElementById('renops-total-personil');
  if (badgeEl) badgeEl.textContent = `Total: ${totalPersonil} Org`;

  syncUnsurFields();
}

function pickMasterTim(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_tim) {
      document.getElementById('unsur-input-nama').value = item.nama_tim;
      document.getElementById('unsur-input-jml').value = '3';
      document.getElementById('unsur-input-nama').focus();
    }
  } catch (e) {}
}

function handleAddUnsurClick() {
  const namaInput = document.getElementById('unsur-input-nama');
  const jmlInput = document.getElementById('unsur-input-jml');
  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentUnsurList.push({
    nama_unsur: nama,
    jumlah_personil: jml
  });

  namaInput.value = '';
  jmlInput.value = '1';
  document.getElementById('select-master-tim').value = '';
  renderUnsurList();
}

function moveUnsurRow(idx, direction) {
  const target = idx + direction;
  if (target < 0 || target >= currentUnsurList.length) return;
  const temp = currentUnsurList[idx];
  currentUnsurList[idx] = currentUnsurList[target];
  currentUnsurList[target] = temp;
  renderUnsurList();
}

function removeUnsurRow(idx) {
  currentUnsurList.splice(idx, 1);
  renderUnsurList();
}

function updateUnsurItem(idx, key, val) {
  if (currentUnsurList[idx]) {
    currentUnsurList[idx][key] = val;
    syncUnsurFields();
  }
}

function syncUnsurFields() {
  const jsonEl = document.getElementById('detail_unsur_json');
  const textEl = document.getElementById('unsur_text');
  if (jsonEl) jsonEl.value = JSON.stringify(currentUnsurList);
  if (textEl) {
    textEl.value = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Org`).join('\n');
  }
}

// ── Alut & Palsar Terlibat Editor (Reorderable + Master Data Reference) ──
let alutSortables = [];

function renderAlutList() {
  const container = document.getElementById('alut-items-container');
  if (!container) return;
  alutSortables.forEach((sb) => sb.destroy());
  alutSortables = [];
  container.innerHTML = '';

  let totalAlut = 0;

  if (currentAlutList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada armada alut / palsar yang ditambahkan. Gunakan dropdown Master Data atau input manual di atas.
      </div>
    `;
  } else {
    // Urutan simpan = urutan tampil: per kategori (Darat → Laut → Udara → PALSAR), sama dengan naskah WA.
    currentAlutList.forEach((item) => {
      if (!AlutKategoriUI.normalize(item.kategori)) item.kategori = AlutKategoriUI.kategoriOf(item);
      totalAlut += (parseInt(item.jumlah_alut, 10) || 1);
    });
    currentAlutList = AlutKategoriUI.sort(currentAlutList);
    AlutKategoriUI.groups(currentAlutList).forEach((grp) => {
      const head = document.createElement('div');
      head.className = 'text-[11px] font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400 pt-1';
      head.textContent = grp.label;
      container.appendChild(head);
      const box = document.createElement('div');
      box.className = 'space-y-2';
      box.setAttribute('data-alut-group', grp.kode);
      grp.items.forEach(({ item, idx }) => {
        const jml = parseInt(item.jumlah_alut, 10) || 1;
        const row = document.createElement('div');
        row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
        row.setAttribute('data-alut-idx', String(idx));
        row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="drag-handle text-slate-400 hover:text-slate-700 dark:hover:text-white px-0.5 select-none cursor-grab active:cursor-grabbing" style="cursor:grab;touch-action:none" title="Geser untuk mengubah urutan">⋮⋮</span>
          <span class="w-5 h-5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_alut || '')}" onchange="updateAlutItem(${idx}, 'nama_alut', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${jml}" onchange="updateAlutItem(${idx}, 'jumlah_alut', parseInt(this.value, 10) || 1)" class="control control-sm w-12 font-bold text-center text-emerald-600 dark:text-emerald-400" />
          <select onchange="updateAlutItem(${idx}, 'satuan', this.value)" class="control control-sm">
            <option value="Unit" ${item.satuan === 'Unit' ? 'selected' : ''}>Unit</option>
            <option value="Set" ${item.satuan === 'Set' ? 'selected' : ''}>Set</option>
            <option value="Buah" ${item.satuan === 'Buah' ? 'selected' : ''}>Buah</option>
            <option value="Paket" ${item.satuan === 'Paket' ? 'selected' : ''}>Paket</option>
            <option value="Batang" ${item.satuan === 'Batang' ? 'selected' : ''}>Batang</option>
            <option value="Orang" ${item.satuan === 'Orang' ? 'selected' : ''}>Orang</option>
          </select>
          <select onchange="updateAlutItem(${idx}, 'kategori', this.value)" class="control control-sm" title="Kategori">${AlutKategoriUI.options(AlutKategoriUI.kategoriOf(item))}</select>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="removeAlutRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Alut">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
        box.appendChild(row);
      });
      container.appendChild(box);
      if (typeof Sortable !== 'undefined') {
        // Satu instance per kelompok tanpa opsi group: drag hanya di dalam kategorinya.
        alutSortables.push(new Sortable(box, {
          handle: '.drag-handle',
          animation: 150,
          onEnd: () => {
            currentAlutList = reorderFromDom(container, 'data-alut-idx', currentAlutList);
            renderAlutList();
          },
        }));
      }
    });
  }

  const badgeEl = document.getElementById('renops-total-alut');
  if (badgeEl) badgeEl.textContent = `Total: ${totalAlut} Alut`;

  syncAlutFields();
}

function pickMasterAlut(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_alut) {
      document.getElementById('alut-input-nama').value = item.nama_alut;
      document.getElementById('alut-input-jml').value = '1';
      if (item.satuan) {
        document.getElementById('alut-input-satuan').value = item.satuan;
      }
      document.getElementById('alut-input-kategori').value = AlutKategoriUI.normalize(item.kategori) || AlutKategoriUI.tebak(item.nama_alut);
      document.getElementById('alut-input-kategori').dataset.manual = '1';
      document.getElementById('alut-input-nama').focus();
    }
  } catch (e) {}
}

function handleAddAlutClick() {
  const namaInput = document.getElementById('alut-input-nama');
  const jmlInput = document.getElementById('alut-input-jml');
  const satInput = document.getElementById('alut-input-satuan');

  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;
  const sat = satInput.value.trim() || 'Unit';

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentAlutList.push({
    nama_alut: nama,
    jumlah_alut: jml,
    satuan: sat,
    kategori: AlutKategoriUI.normalize(document.getElementById('alut-input-kategori').value) || AlutKategoriUI.tebak(nama)
  });

  namaInput.value = '';
  jmlInput.value = '1';
  document.getElementById('select-master-alut').value = '';
  AlutKategoriUI.resetInput('alut-input-kategori');
  renderAlutList();
}

function moveAlutRow(idx, direction) {
  const target = idx + direction;
  if (target < 0 || target >= currentAlutList.length) return;
  const temp = currentAlutList[idx];
  currentAlutList[idx] = currentAlutList[target];
  currentAlutList[target] = temp;
  renderAlutList();
}

function removeAlutRow(idx) {
  currentAlutList.splice(idx, 1);
  renderAlutList();
}

function updateAlutItem(idx, key, val) {
  if (currentAlutList[idx]) {
    currentAlutList[idx][key] = val;
    if (key === 'kategori') {
      renderAlutList();
      return;
    }
    syncAlutFields();
  }
}

function syncAlutFields() {
  const jsonEl = document.getElementById('detail_alut_json');
  const textEl = document.getElementById('alut_text');
  if (jsonEl) jsonEl.value = JSON.stringify(currentAlutList);
  if (textEl) {
    textEl.value = AlutKategoriUI.sort(currentAlutList).map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
  }
}

// ── WhatsApp Preview Modal ──

async function previewRenopsWa() {
  const form = document.getElementById('form-renops');
  const el = document.getElementById('renops-wa-preview-content');
  if (!form || !el) return;
  // Samakan dengan sinkronisasi saat submit agar pratinjau = naskah yang tersimpan
  syncUnsurFields();
  syncAlutFields();
  openModal('modal-preview-renops-wa');
  try {
    el.textContent = await window.QrSarUtils.fetchWaPreview(form, 'renops');
  } catch (err) {
    el.textContent = err.message;
  }
}

function copyRenopsWa() {
  const content = document.getElementById('renops-wa-preview-content')?.textContent || '';
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(content).then(() => {
      if (typeof showToast === 'function') showToast('Format WA RENOPS berhasil disalin!', 'success');
    }).catch(() => fallbackCopyRenopsText(content));
  } else {
    fallbackCopyRenopsText(content);
  }
}

function fallbackCopyRenopsText(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.focus();
  ta.select();
  try {
    document.execCommand('copy');
    if (typeof showToast === 'function') showToast('Format WA RENOPS berhasil disalin!', 'success');
  } catch (err) {
    if (typeof showToast === 'function') showToast('Gagal menyalin teks secara otomatis', 'error');
  }
  document.body.removeChild(ta);
}

function openModal(id) {
  if (typeof window.openModal === 'function' && window.openModal !== openModal) {
    window.openModal(id);
    return;
  }
  const el = typeof id === 'string' ? document.getElementById(id) : id;
  if (el) {
    el.classList.remove('hidden');
    el.classList.add('open');
    if (el.style.display === 'none' || !el.style.display) el.style.display = 'flex';
    document.body.style.overflow = 'hidden';
  }
}

function closeModal(id) {
  if (typeof window.closeModal === 'function' && window.closeModal !== closeModal) {
    window.closeModal(id);
    return;
  }
  const el = typeof id === 'string' ? document.getElementById(id) : id;
  if (el) {
    el.classList.add('hidden');
    el.classList.remove('open');
    el.style.display = 'none';
  }
  if (typeof window.ensureBodyScrollUnlocked === 'function') {
    window.ensureBodyScrollUnlocked();
  } else {
    document.body.style.overflow = '';
  }
}

// ── GIS Plotting Import into RENOPS ──
// SAR_GIS_BUNDLE initialized from renopsCfg.gisBundle
let targetedSruIdxForImport = null;
let activeGisImportTab = 'polygons';
let activeRenopsPointCatFilter = '';
let isRefreshingGis = false;

async function refreshGisBundle(showToastAlert = false) {
  if (isRefreshingGis) return;
  isRefreshingGis = true;

  const iconHeader = document.getElementById('icon-refresh-gis');
  const iconQuick = document.getElementById('icon-quick-refresh-gis');
  const labelHeader = document.getElementById('label-refresh-gis');
  const btnHeader = document.getElementById('btn-refresh-gis-bundle');
  const btnQuick = document.getElementById('btn-quick-refresh-gis');

  if (iconHeader) iconHeader.classList.add('animate-spin');
  if (iconQuick) iconQuick.classList.add('animate-spin');
  if (labelHeader) labelHeader.textContent = 'Memuat...';
  if (btnHeader) btnHeader.disabled = true;
  if (btnQuick) btnQuick.disabled = true;

  try {
    const res = await fetch(`${BASE_URL}/api/gis/bundle?id_operasi=${idOperasi}&include_base_map=1&_t=${Date.now()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    if (data.success) {
      const newBundle = data.data || {
        id_operasi: idOperasi,
        include_base_map: true,
        points: data.points || [],
        polygons: data.polygons || [],
        routes: data.routes || [],
        searchPatterns: data.searchPatterns || data.search_patterns || []
      };

      if (!newBundle.points) newBundle.points = data.points || [];
      if (!newBundle.polygons) newBundle.polygons = data.polygons || [];
      if (!newBundle.routes) newBundle.routes = data.routes || [];
      if (!newBundle.searchPatterns) newBundle.searchPatterns = data.searchPatterns || data.search_patterns || [];

      SAR_GIS_BUNDLE = newBundle;

      // Update badge counts
      const cntPoly = (SAR_GIS_BUNDLE.polygons || []).length;
      const cntPat = (SAR_GIS_BUNDLE.searchPatterns || []).length;
      const cntRte = (SAR_GIS_BUNDLE.routes || []).length;
      const cntPts = (SAR_GIS_BUNDLE.points || []).length;
      const totalAll = cntPoly + cntPat + cntRte + cntPts;

      const badgeMain = document.getElementById('gis-bundle-badge');
      if (badgeMain) badgeMain.textContent = totalAll;

      const badgePoly = document.getElementById('tab-cnt-polygons');
      if (badgePoly) badgePoly.textContent = cntPoly;

      const badgePat = document.getElementById('tab-cnt-patterns');
      if (badgePat) badgePat.textContent = cntPat;

      const badgeRte = document.getElementById('tab-cnt-routes');
      if (badgeRte) badgeRte.textContent = cntRte;

      const badgePts = document.getElementById('tab-cnt-points');
      if (badgePts) badgePts.textContent = cntPts;

      const syncTimeEl = document.getElementById('gis-last-sync-time');
      if (syncTimeEl) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        syncTimeEl.textContent = `(Sinkron: ${timeStr})`;
      }

      // Re-render modal content and update SRU sector pickers
      renderGisImportModalContent();
      renderGisImportChecklist();
      renderSruList();

      if (showToastAlert && typeof showToast === 'function') {
        showToast(`Data plotting peta berhasil diperbarui! (${totalAll} data taktis ditemukan)`, 'success');
      }
    } else {
      throw new Error(data.message || data.error || 'Gagal memuat data plotting');
    }
  } catch (err) {
    console.error('Error refreshing GIS bundle:', err);
    if (showToastAlert && typeof showToast === 'function') {
      showToast('Gagal memuat pembaruan data plotting: ' + err.message, 'error');
    }
  } finally {
    isRefreshingGis = false;
    if (iconHeader) iconHeader.classList.remove('animate-spin');
    if (iconQuick) iconQuick.classList.remove('animate-spin');
    if (labelHeader) labelHeader.textContent = 'Refresh Data';
    if (btnHeader) btnHeader.disabled = false;
    if (btnQuick) btnQuick.disabled = false;
  }
}

function formatDmsCoordPart(val, isLat) {
  return window.QrSarUtils.formatDmsPart(val, isLat);
}

function formatDmsPair(lat, lng) {
  return window.QrSarUtils.formatDmsPair(lat, lng);
}

function calculatePolygonAreaNm2(poly) {
  if (!poly) return 0;
  if (poly.areaNm2 && Number(poly.areaNm2) > 0) {
    return Number(poly.areaNm2);
  }
  if (poly.shapeType === 'circle' && poly.radiusNm) {
    return Math.round(Math.PI * Math.pow(Number(poly.radiusNm), 2) * 100) / 100;
  }
  if ((poly.shapeType === 'box' || poly.shapeType === 'rectangle') && poly.boxWidthNm && poly.boxHeightNm) {
    return Math.round(Number(poly.boxWidthNm) * Number(poly.boxHeightNm) * 100) / 100;
  }
  const pts = poly.points || [];
  if (!Array.isArray(pts) || pts.length < 3) return 0;

  let sumLat = 0;
  pts.forEach(p => {
    const lat = p.lat !== undefined ? p.lat : (Array.isArray(p) ? p[0] : 0);
    sumLat += Number(lat || 0);
  });
  const meanLatRad = (sumLat / pts.length) * (Math.PI / 180);
  const cosMeanLat = Math.cos(meanLatRad);

  let areaSum = 0;
  const n = pts.length;
  for (let i = 0; i < n; i++) {
    const j = (i + 1) % n;
    const pt1 = pts[i];
    const pt2 = pts[j];
    const lng1 = pt1.lng !== undefined ? pt1.lng : (Array.isArray(pt1) ? pt1[1] : 0);
    const lat1 = pt1.lat !== undefined ? pt1.lat : (Array.isArray(pt1) ? pt1[0] : 0);
    const lng2 = pt2.lng !== undefined ? pt2.lng : (Array.isArray(pt2) ? pt2[1] : 0);
    const lat2 = pt2.lat !== undefined ? pt2.lat : (Array.isArray(pt2) ? pt2[0] : 0);

    const x1 = Number(lng1) * 60.0 * cosMeanLat;
    const y1 = Number(lat1) * 60.0;
    const x2 = Number(lng2) * 60.0 * cosMeanLat;
    const y2 = Number(lat2) * 60.0;
    areaSum += (x1 * y2) - (x2 * y1);
  }
  const area = Math.abs(areaSum) / 2.0;
  return Math.round(area * 100) / 100;
}

function getPolygonCoordsText(poly) {
  if (!poly) return '';
  if (poly.shapeType === 'circle') {
    const pts = poly.points || [];
    const cLat = poly.centerLat !== undefined ? Number(poly.centerLat) : (pts[0]?.lat || 0);
    const cLng = poly.centerLng !== undefined ? Number(poly.centerLng) : (pts[0]?.lng || 0);
    const rNm = Number(poly.radiusNm || 1.0);
    const rKm = (rNm * 1.852).toFixed(2);
    const areaNm = calculatePolygonAreaNm2(poly);
    let radiusText = '';
    let areaText = '';
    if (activeRenopsUnit === 'KM') {
      radiusText = `${rKm} km (${rNm.toFixed(2)} NM)`;
      areaText = formatRenopsArea(areaNm);
    } else if (activeRenopsUnit === 'M') {
      radiusText = `${Math.round(rNm * 1852)} m (${rNm.toFixed(2)} NM)`;
      areaText = formatRenopsArea(areaNm);
    } else {
      radiusText = `${rNm.toFixed(2)} NM (${rKm} km)`;
      areaText = formatRenopsArea(areaNm);
    }
    return `Pusat Datum (LKP): ${formatDmsPair(cLat, cLng)}\nRadius Sektor: ${radiusText}\nLuas Area: ${areaText}`;
  }

  if (poly.shapeType === 'rectangle' && (!poly.points || poly.points.length === 0) && poly.centerLat && poly.centerLng && poly.boxWidthNm && poly.boxHeightNm) {
    const cLat = Number(poly.centerLat);
    const cLng = Number(poly.centerLng);
    const wNm = Number(poly.boxWidthNm);
    const hNm = Number(poly.boxHeightNm);
    const halfLatDeg = (hNm / 60.0) / 2.0;
    const cosLat = Math.cos(cLat * Math.PI / 180.0) || 1.0;
    const halfLngDeg = ((wNm / 60.0) / cosLat) / 2.0;
    const corners = [
      { label: 'A (NW)', lat: cLat + halfLatDeg, lng: cLng - halfLngDeg },
      { label: 'B (NE)', lat: cLat + halfLatDeg, lng: cLng + halfLngDeg },
      { label: 'C (SE)', lat: cLat - halfLatDeg, lng: cLng + halfLngDeg },
      { label: 'D (SW)', lat: cLat - halfLatDeg, lng: cLng - halfLngDeg }
    ];
    return corners.map(pt => `${pt.label}: ${formatDmsPair(pt.lat, pt.lng)}`).join('\n');
  }

  const pts = poly.points || [];
  if (!Array.isArray(pts) || pts.length === 0) return '';
  return pts.map((pt, idx) => {
    const lat = pt.lat !== undefined ? pt.lat : (Array.isArray(pt) ? pt[0] : 0);
    const lng = pt.lng !== undefined ? pt.lng : (Array.isArray(pt) ? pt[1] : 0);
    let lbl = pt.label ? String(pt.label).trim() : '';
    if (!lbl || lbl.toLowerCase().startsWith('titik')) {
      lbl = String.fromCharCode(65 + (idx % 26));
      if (idx >= 26) lbl += (Math.floor(idx / 26) + 1);
    }
    return `${lbl}: ${formatDmsPair(lat, lng)}`;
  }).join('\n');
}

function copyGisDmsText(encodedText, label) {
  const text = decodeURIComponent(encodedText);
  if (window.QrSarUtils) {
    window.QrSarUtils.copyToClipboard(text, `Koordinat ${label} berhasil disalin!`);
  } else if (typeof copyToClipboard === 'function') {
    copyToClipboard(text, `Koordinat ${label} berhasil disalin!`);
  } else if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      if (typeof showToast === 'function') showToast(`Koordinat ${label} berhasil disalin!`, 'success');
    });
  }
}

function openGisImportModal(targetSruIdx = null) {
  targetedSruIdxForImport = (typeof targetSruIdx === 'number' && targetSruIdx >= 0) ? targetSruIdx : null;
  activeRenopsPointCatFilter = '';
  renderGisImportModalContent();
  renderGisImportChecklist();
  openModal('modal-import-gis-renops');
  refreshGisBundle(false);
}

function switchGisImportTab(tabName) {
  activeGisImportTab = tabName;
  ['polygons', 'patterns', 'routes', 'points'].forEach(t => {
    const btn = document.getElementById(`btn-tab-${t}`);
    if (btn) {
      if (t === tabName) {
        btn.classList.add('border-orange-500', 'text-orange-600', 'dark:text-orange-400');
        btn.classList.remove('border-transparent', 'text-slate-500');
      } else {
        btn.classList.remove('border-orange-500', 'text-orange-600', 'dark:text-orange-400');
        btn.classList.add('border-transparent', 'text-slate-500');
      }
    }
  });
  renderGisImportModalContent();
}

function getSruOptionsHtml(selectedIdx = null) {
  if (currentSrus.length === 0) {
    return `<option value="-1">+ Jadikan SRU 1 Baru</option>`;
  }
  return currentSrus.map((s, idx) => {
    const isSel = (selectedIdx === idx) || (targetedSruIdxForImport === idx && selectedIdx === null);
    const label = s.nama_sru ? `SRU ${idx + 1} (${escapeHtml(s.nama_sru)})` : `SRU ${idx + 1}`;
    return `<option value="${idx}" ${isSel ? 'selected' : ''}>${label}</option>`;
  }).join('') + `<option value="-1">+ Tambah Sebagai SRU Baru</option>`;
}

function renderGisImportModalContent() {
  const container = document.getElementById('gis-import-content-container');
  if (!container) return;

  if (activeGisImportTab === 'polygons') {
    const polys = SAR_GIS_BUNDLE.polygons || [];
    if (polys.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">⬡</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Search Area (Poligon) tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk menggambar poligon batas pencarian operasi atau base map.</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }
    container.innerHTML = polys.map(p => {
      const isBaseMap = !p.id_operasi;
      const areaNum = calculatePolygonAreaNm2(p);
      const area = areaNum > 0 ? formatRenopsAreaShort(areaNum) : '-';
      const pts = p.points || [];
      const coordsPreview = getPolygonCoordsText(p);
      const selId = `sel-poly-target-${p.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${p.strokeColor || '#2563eb'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(p.name || 'Sektor Pencarian')}</span>
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map / Wilayah Kerja' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Luas: <span class="text-blue-600 dark:text-blue-400">${area}</span> &bull; ${pts.length} Titik Sudut
            </div>
          </div>
          <div class="text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 whitespace-pre-line max-h-24 overflow-y-auto leading-relaxed select-all">
${coordsPreview || '(Koordinat sudut tidak tersedia)'}
          </div>
          <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <button type="button" onclick="copyGisDmsText(${jsArg(encodeURIComponent(coordsPreview))}, ${jsArg(p.name)})" class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 transition cursor-pointer">
              📋 Salin Koordinat
            </button>
            <div class="flex items-center gap-2">
              <select id="${selId}" class="control control-sm">
                ${getSruOptionsHtml()}
              </select>
              <button type="button" onclick="handleImportPolyClick(${jsArg(p.id)}, ${jsArg(selId)})" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition cursor-pointer">
                Terapkan ke SRU
              </button>
            </div>
          </div>
        </div>
      `;
    }).join('');
  } else if (activeGisImportTab === 'patterns') {
    const patterns = SAR_GIS_BUNDLE.searchPatterns || [];
    if (patterns.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">⚡</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Pola Pencarian SAR tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk men-generate Pola Pencarian (Parallel, Expanding Square, E-SAR, dll).</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }
    container.innerHTML = patterns.map(p => {
      const isBaseMap = !p.id_operasi;
      const typeLabels = {
        'creeping_line': 'Creeping Line Search',
        'parallel_track': 'Parallel Track Search',
        'e_sar': 'Pola Pencarian E-SAR',
        'sector_search': 'Pola Pencarian E-SAR',
        'track_line': 'Track Line Search'
      };
      const typeLabel = typeLabels[p.patternType] || p.patternType || 'Parallel Track Search';
      const selId = `sel-pattern-target-${p.id}`;
      const waypoints = p.waypoints || [];
      const dist = p.totalDistanceNm ? formatRenopsDistanceShort(p.totalDistanceNm) : '-';
      const spacing = p.trackSpacingNm ? formatRenopsSpacingShort(p.trackSpacingNm) : '-';
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${p.color || '#ea580c'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(p.name || 'Pola Lintasan SAR')}</span>
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                ${escapeHtml(typeLabel)}
              </span>
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Spacing: <span class="text-orange-600 dark:text-orange-400">${spacing}</span> &bull; Jarak: <span>${dist}</span>
            </div>
          </div>
          <div class="text-[11px] text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
            <span>Sektor Asal: <strong>${escapeHtml(p.polygonName || '-')}</strong></span>
            <span>Jumlah Waypoint: <strong>${waypoints.length} titik</strong></span>
            <span>Lintasan/Sapu: <strong>${p.legsCount || '-'}</strong></span>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
            <select id="${selId}" class="control control-sm">
              ${getSruOptionsHtml()}
            </select>
            <button type="button" onclick="handleImportPatternClick(${jsArg(p.id)}, ${jsArg(selId)})" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition cursor-pointer">
              Terapkan Pola ke SRU
            </button>
          </div>
        </div>
      `;
    }).join('');
  } else if (activeGisImportTab === 'routes') {
    const routes = SAR_GIS_BUNDLE.routes || [];
    if (routes.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">↗️</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Jalur / Rute tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk menambahkan rute laut/darat.</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }
    container.innerHTML = routes.map(r => {
      const isBaseMap = !r.id_operasi;
      const dist = r.totalDistanceNm ? formatRenopsDistanceShort(r.totalDistanceNm) : '-';
      const speed = r.vesselSpeedKnots ? formatRenopsSpeed(r.vesselSpeedKnots) : (activeRenopsUnit === 'KM' ? '46 km/jam' : '25 Knots');
      const pts = r.points || [];
      const selId = `sel-route-target-${r.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${r.strokeColor || '#059669'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(r.name || 'Jalur Operasi')}</span>
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Panjang: <span class="text-emerald-600 dark:text-emerald-400">${dist}</span> &bull; Kecepatan: <span>${speed}</span>
            </div>
          </div>
          <div class="text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <span>Jumlah Waypoint: <strong>${pts.length} titik koordinat</strong></span>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
            <select id="${selId}" class="control control-sm">
              ${getSruOptionsHtml()}
            </select>
            <button type="button" onclick="handleImportRouteClick(${jsArg(r.id)}, ${jsArg(selId)})" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition cursor-pointer">
              Sisipkan ke SRU
            </button>
          </div>
        </div>
      `;
    }).join('');
  } else if (activeGisImportTab === 'points') {
    const points = SAR_GIS_BUNDLE.points || [];
    if (points.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">📍</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Titik Taktis tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk menambahkan titik LKP, Posko, Helipad, atau temuan.</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }

    const uniqueCategories = Array.from(new Set(points.map(pt => (pt.category || '').trim()).filter(Boolean)))
      .sort((a, b) => a.localeCompare(b, 'id'));

    if (activeRenopsPointCatFilter && !uniqueCategories.includes(activeRenopsPointCatFilter)) {
      activeRenopsPointCatFilter = '';
    }

    const catOptionsHtml = uniqueCategories.map(cat => {
      const isSel = cat === activeRenopsPointCatFilter ? 'selected' : '';
      return `<option value="${escapeHtml(cat)}" ${isSel}>${escapeHtml(cat)}</option>`;
    }).join('');

    const filterHeaderHtml = `
      <div class="flex items-center gap-2 mb-3">
        <label class="text-xs font-semibold text-slate-500 whitespace-nowrap">Filter Kategori:</label>
        <select id="filter-renops-point-cat" class="text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2.5 py-1.5 text-slate-700 dark:text-slate-300 focus:outline-none focus:border-orange-500 flex-1 sm:max-w-xs">
          <option value="">Semua Kategori</option>
          ${catOptionsHtml}
        </select>
      </div>
    `;

    const filteredPoints = activeRenopsPointCatFilter
      ? points.filter(pt => (pt.category || '').trim() === activeRenopsPointCatFilter)
      : points;

    let cardsHtml = '';
    if (filteredPoints.length === 0) {
      cardsHtml = `
        <div class="p-6 text-center text-slate-400 text-xs rounded-xl border border-dashed border-slate-300 dark:border-slate-800">
          Tidak ada titik dalam kategori "${escapeHtml(activeRenopsPointCatFilter)}".
        </div>`;
    } else {
      cardsHtml = filteredPoints.map(pt => {
        const isBaseMap = !pt.id_operasi;
        const dms = formatDmsPair(Number(pt.lat), Number(pt.lng));
        const selId = `sel-pt-target-${pt.id}`;
        const catName = pt.category || 'Taktis';
        const descTip = pt.description ? escapeHtml(pt.description) : '';
        return `
          <div class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:border-slate-300 dark:hover:border-slate-700 transition"${descTip ? ` title="${descTip}"` : ''}>
            <div class="flex items-center gap-2.5 min-w-0 flex-1">
              <span class="text-base shrink-0 select-none">📍</span>
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <span class="font-bold text-xs text-slate-900 dark:text-white truncate">${escapeHtml(pt.label || 'Titik Taktis')}</span>
                  <span class="text-[10px] font-bold px-1.5 py-0.5 rounded ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20'} shrink-0">
                    ${escapeHtml(catName)}
                  </span>
                  ${isBaseMap ? `<span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 shrink-0">Base Map</span>` : ''}
                </div>
                <div class="flex items-center gap-2 mt-0.5">
                  <span class="text-[11px] font-mono text-slate-500 dark:text-slate-400 truncate">${dms}</span>
                  ${pt.description ? `<span class="text-[11px] text-slate-400 truncate hidden md:inline" title="${descTip}">• ${descTip}</span>` : ''}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-center">
              <button type="button" onclick="copyGisDmsText(${jsArg(encodeURIComponent(dms))}, ${jsArg(pt.label)})" class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 transition cursor-pointer" title="Salin DMS">
                📋 Salin
              </button>
              <select id="${selId}" class="control control-xs py-1 text-xs max-w-[140px] sm:max-w-[170px]">
                ${getSruOptionsHtml()}
              </select>
              <button type="button" onclick="handleImportPointClick(${jsArg(pt.id)}, ${jsArg(selId)})" class="px-3 py-1 text-xs font-semibold rounded-lg bg-purple-600 hover:bg-purple-500 text-white shadow-xs transition cursor-pointer shrink-0">
                Sisipkan ke SRU
              </button>
            </div>
          </div>
        `;
      }).join('');
    }

    container.innerHTML = filterHeaderHtml + `<div class="space-y-2" id="renops-points-cards-list">${cardsHtml}</div>`;

    const catSelectEl = document.getElementById('filter-renops-point-cat');
    if (catSelectEl) {
      catSelectEl.addEventListener('change', (e) => {
        activeRenopsPointCatFilter = e.target.value;
        renderGisImportModalContent();
      });
    }
  }
}

function handleImportPolyClick(polyId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  if (targetIdx === -1) {
    importPolygonToNewSru(polyId);
  } else {
    importPolygonToSru(polyId, targetIdx);
  }
}

function handleImportPatternClick(patId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  if (targetIdx === -1) {
    importPatternToNewSru(patId);
  } else {
    importPatternToSru(patId, targetIdx);
  }
}

function handleImportRouteClick(routeId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  importRouteToSru(routeId, targetIdx);
}

function handleImportPointClick(ptId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  importPointToSru(ptId, targetIdx);
}

function getPatternDetails(matchedPattern) {
  if (!matchedPattern) return { pola: '', totalSapuan: '', spacing: '', detailText: '' };
  
  let pola = '';
  const pt = matchedPattern.patternType || matchedPattern.type;
  if (pt === 'creeping_line') pola = 'Creeping Line Search';
  else if (pt === 'e_sar' || pt === 'sector_search') pola = 'Pola Pencarian E-SAR';
  else if (pt === 'track_line') pola = 'Track Line Search';
  else pola = 'Parallel Track Search';

  const isEsar = (pt === 'e_sar' || pt === 'sector_search');
  const legs = matchedPattern.legsCount || (matchedPattern.waypoints ? Math.max(1, Math.floor(matchedPattern.waypoints.length / 2)) : 6);
  const spacing = formatRenopsSpacingShort(matchedPattern.trackSpacingNm || 1.0);
  const dist = matchedPattern.totalDistanceNm ? formatRenopsDistanceShort(matchedPattern.totalDistanceNm) : '-';

  const totalSapuan = isEsar
    ? `${legs} Personel E-SAR (Spasi: ${spacing}, Total: ${dist})`
    : `${legs} Sapuan (Spasi: ${spacing}, Total: ${dist})`;

  const detailText = '';

  return { pola, totalSapuan, spacing, detailText };
}

// ── Impor per SRU: satu search area = satu SRU; pola & rute dipasangkan lewat polygonId (RenopsImport) ──
function gisImportItems() {
  return RenopsImport.checklist(SAR_GIS_BUNDLE.polygons || [], SAR_GIS_BUNDLE.searchPatterns || [], SAR_GIS_BUNDLE.routes || []);
}

// Nilai bawaan baris SRU baru hasil impor (baris yang sudah ada tidak diberi bawaan: isian manual tetap).
const SRU_DEFAULTS = { pola_pencarian: '', total_sapuan: '', track_spacing: '' };

/** Field SRU dari satu area: nama = nama area, tugas (Sektor, Rute, Batas Koordinat), luas; pola hanya bila area punya pola. */
function describeAreaForSru(item) {
  const poly = (SAR_GIS_BUNDLE.polygons || []).find((p) => String(p.id) === String(item.id));
  const name = item.name || 'Sektor Pencarian';
  const areaNum = poly ? calculatePolygonAreaNm2(poly) : 0;
  const area = areaNum > 0 ? formatRenopsAreaShort(areaNum) : (poly && poly.areaNm2 ? formatRenopsAreaShort(Number(poly.areaNm2)) : '');
  const coordsText = poly ? getPolygonCoordsText(poly) : '';
  let tugas = `Sektor: ${name}${area ? ` (${area})` : ''}`;
  if (item.route) {
    const r = item.route;
    const nm = r.totalDistanceNm || RenopsImport.routeLengthNm(r.points || []);
    const dist = nm > 0 ? formatRenopsDistanceShort(nm) : '-';
    const speed = r.vesselSpeedKnots ? formatRenopsSpeed(r.vesselSpeedKnots) : '-';
    // Sebelum "Batas Koordinat": baris sesudahnya dibaca BRA sebagai koordinat.
    tugas += `\nRute: ${r.name} (Panjang: ${dist}, Kecepatan: ${speed})`;
  }
  if (coordsText) tugas += `\nBatas Koordinat:\n${coordsText}`;
  const fields = { nama_sru: name, koordinat_area: tugas, luas_area: area };
  if (item.pattern) {
    const patInfo = getPatternDetails(item.pattern);
    fields.pola_pencarian = patInfo.pola;
    fields.total_sapuan = patInfo.totalSapuan;
    fields.track_spacing = patInfo.spacing;
  }
  return fields;
}

/** Daftar periksa di modal: tiap area ✓/✗ pola & rute, sebelum impor. */
function renderGisImportChecklist() {
  const box = document.getElementById('gis-import-checklist');
  if (!box) return;
  const items = gisImportItems();
  if (items.length === 0) {
    box.innerHTML = '<span class="text-[11px] text-slate-500">Belum ada Search Area di peta plotting.</span>';
    return;
  }
  const tanda = (ok, label) => `<span class="${ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'} font-bold">${ok ? '✓' : '✗'} ${label}</span>`;
  box.innerHTML = items.map((it) => `
    <div class="flex items-center justify-between gap-2 text-[11px]">
      <span class="font-bold text-slate-700 dark:text-slate-200 truncate">${escapeHtml(it.name || '(Tanpa Nama)')}</span>
      <span class="flex items-center gap-2 shrink-0">${tanda(!!it.pattern, 'Pola')}${tanda(!!it.route, 'Rute')}</span>
    </div>`).join('');
}

function importAllPolygonsToSrus() {
  const items = gisImportItems();
  if (items.length === 0) {
    if (typeof showToast === 'function') showToast('Tidak ada Search Area (Poligon) yang tersimpan di peta plotting.', 'info');
    return;
  }
  // Menggabung, bukan mengganti: SRU bernama sama diperbarui, area baru ditambah, SRU manual tetap.
  const sebelum = currentSrus.length;
  currentSrus = RenopsImport.mergeAreasIntoSrus(currentSrus, items, describeAreaForSru, SRU_DEFAULTS);
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    const baru = currentSrus.length - sebelum;
    showToast(`${items.length} Search Area diimpor per SRU: ${baru} SRU baru, ${items.length - baru} diperbarui.`, 'success');
  }
}

function importPolygonToSru(polyId, targetIdx) {
  const item = gisImportItems().find((it) => String(it.id) === String(polyId));
  if (!item) return;
  currentSrus[targetIdx] = Object.assign(currentSrus[targetIdx] || { ...SRU_DEFAULTS }, describeAreaForSru(item));
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Data sektor "${item.name}" berhasil diterapkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

function importPolygonToNewSru(polyId) {
  const item = gisImportItems().find((it) => String(it.id) === String(polyId));
  if (!item) return;
  currentSrus.push({ ...SRU_DEFAULTS, ...describeAreaForSru(item) });
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`SRU baru "${item.name}" berhasil ditambahkan!`, 'success');
  }
}

function importPatternToSru(patId, targetIdx) {
  const pat = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => String(p.id) === String(patId));
  if (!pat) return;
  if (!currentSrus[targetIdx]) {
    currentSrus[targetIdx] = { nama_sru: `SRU ${targetIdx + 1}`, koordinat_area: '', luas_area: '', pola_pencarian: '', total_sapuan: '' };
  }
  
  const patInfo = getPatternDetails(pat);
  currentSrus[targetIdx].pola_pencarian = patInfo.pola;
  currentSrus[targetIdx].total_sapuan = patInfo.totalSapuan;
  currentSrus[targetIdx].track_spacing = patInfo.spacing;

  const linkedPoly = (SAR_GIS_BUNDLE.polygons || []).find(p => (pat.polygonId && p.id === pat.polygonId) || (pat.polygonName && p.name === pat.polygonName));
  if (linkedPoly) {
    const areaNum = calculatePolygonAreaNm2(linkedPoly);
    const area = areaNum > 0 ? formatRenopsAreaShort(areaNum) : (linkedPoly.areaNm2 ? formatRenopsAreaShort(Number(linkedPoly.areaNm2)) : '');
    if (area && !currentSrus[targetIdx].luas_area) {
      currentSrus[targetIdx].luas_area = area;
    }
    const coordsText = getPolygonCoordsText(linkedPoly);
    if (!currentSrus[targetIdx].koordinat_area) {
      currentSrus[targetIdx].koordinat_area = `Sektor: ${linkedPoly.name || 'Sektor Pencarian'}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '');
    }
  }
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Pola pencarian "${pat.name}" berhasil diterapkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

function importPatternToNewSru(patId) {
  const pat = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => String(p.id) === String(patId));
  if (!pat) return;
  const num = currentSrus.length + 1;
  const patInfo = getPatternDetails(pat);

  let area = '';
  let coordsText = '';
  const linkedPoly = (SAR_GIS_BUNDLE.polygons || []).find(p => (pat.polygonId && p.id === pat.polygonId) || (pat.polygonName && p.name === pat.polygonName));
  if (linkedPoly) {
    const areaNum = calculatePolygonAreaNm2(linkedPoly);
    area = areaNum > 0 ? formatRenopsAreaShort(areaNum) : (linkedPoly.areaNm2 ? formatRenopsAreaShort(Number(linkedPoly.areaNm2)) : '');
    coordsText = getPolygonCoordsText(linkedPoly);
  }

  let taskText = linkedPoly
    ? `Sektor: ${linkedPoly.name || 'Sektor Pencarian'}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '')
    : `Penyisiran pencarian SAR menggunakan pola ${pat.name}`;

  currentSrus.push({
    nama_sru: `SRU ${num} (${patInfo.pola})`,
    koordinat_area: taskText,
    luas_area: area,
    pola_pencarian: patInfo.pola,
    total_sapuan: patInfo.totalSapuan,
    track_spacing: patInfo.spacing
  });
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`SRU baru dengan pola "${pat.name}" berhasil ditambahkan!`, 'success');
  }
}

function importRouteToSru(routeId, targetIdx) {
  const r = (SAR_GIS_BUNDLE.routes || []).find(item => String(item.id) === String(routeId));
  if (!r) return;
  
  const dist = r.totalDistanceNm ? formatRenopsDistanceShort(r.totalDistanceNm) : '-';
  const speed = r.vesselSpeedKnots ? formatRenopsSpeed(r.vesselSpeedKnots) : (activeRenopsUnit === 'KM' ? '46 km/jam' : '25 Knots');
  const totalSapuan = `1 Lintasan Track Line (Panjang: ${dist}, Kecepatan: ${speed})`;
  const pts = r.points || [];
  let routeCoordsText = '';
  if (Array.isArray(pts) && pts.length > 0) {
    routeCoordsText = pts.map((pt, idx) => {
      const lbl = pt.label || `Waypoint ${idx + 1}`;
      return `${lbl}: ${formatDmsPair(Number(pt.lat), Number(pt.lng))}`;
    }).join('\n');
  }

  const routeDesc = `Jalur/Rute: ${r.name}\nPanjang Lintasan: ${dist} | Kecepatan Alut: ${speed}` + (routeCoordsText ? `\nKoordinat Lintasan:\n${routeCoordsText}` : '');
  const corridorLuas = `${dist} (Koridor)`;

  if (targetIdx === -1 || !currentSrus[targetIdx]) {
    const num = currentSrus.length + 1;
    currentSrus.push({
      nama_sru: `SRU ${num} - Jalur ${r.name}`,
      koordinat_area: routeDesc,
      luas_area: corridorLuas,
      pola_pencarian: 'Track Line Search',
      total_sapuan: totalSapuan
    });
    targetIdx = currentSrus.length - 1;
  } else {
    currentSrus[targetIdx].pola_pencarian = 'Track Line Search';
    currentSrus[targetIdx].total_sapuan = totalSapuan;
    if (!currentSrus[targetIdx].luas_area) currentSrus[targetIdx].luas_area = corridorLuas;
    currentSrus[targetIdx].koordinat_area = (currentSrus[targetIdx].koordinat_area || '') + '\n\n' + routeDesc;
  }
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Jalur "${r.name}" berhasil disisipkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

function importPointToSru(ptId, targetIdx) {
  const pt = (SAR_GIS_BUNDLE.points || []).find(item => String(item.id) === String(ptId));
  if (!pt) return;
  const dms = formatDmsPair(Number(pt.lat), Number(pt.lng));
  const catLower = (pt.category || '').toLowerCase();
  const labelLower = (pt.label || '').toLowerCase();
  let ptDesc = '';
  if (catLower.includes('entry') || labelLower.includes('entry')) {
    ptDesc = `[Titik Entry Poin: ${pt.label}]\nKoordinat: ${dms}${pt.description ? '\nKeterangan: ' + pt.description : ''}`;
  } else if (catLower.includes('exit') || labelLower.includes('exit')) {
    ptDesc = `[Titik Exit Poin: ${pt.label}]\nKoordinat: ${dms}${pt.description ? '\nKeterangan: ' + pt.description : ''}`;
  } else {
    ptDesc = `[Titik ${pt.category || 'Taktis'}: ${pt.label}]\nKoordinat: ${dms}${pt.description ? '\nKeterangan: ' + pt.description : ''}`;
  }
  
  if (targetIdx === -1 || !currentSrus[targetIdx]) {
    const num = currentSrus.length + 1;
    currentSrus.push({
      nama_sru: `SRU ${num} - ${pt.label}`,
      koordinat_area: ptDesc.trim(),
      luas_area: '',
      pola_pencarian: '',
      total_sapuan: ''
    });
    targetIdx = currentSrus.length - 1;
  } else {
    currentSrus[targetIdx].koordinat_area = (currentSrus[targetIdx].koordinat_area || '') + '\n\n' + ptDesc.trim();
  }
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Titik "${pt.label}" berhasil disisipkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

// --- AUTO-SAVE DRAFT (LOCAL STORAGE RESILIENCE) ---
// RENOPS_DRAFT_KEY and isRenopsEditMode initialized above from renopsCfg

function saveRenopsDraft() {
  if (isRenopsEditMode) return;
  const draft = {
    tanggal: document.getElementById('input-tanggal')?.value,
    waktu_briefing: document.getElementById('input-waktu-briefing')?.value,
    waktu_mulai: document.getElementById('input-waktu-mulai')?.value,
    cuaca: document.getElementById('input-cuaca')?.value,
    komunikasi: document.getElementById('input-komunikasi')?.value,
    catatan: document.getElementById('input-catatan')?.value,
    srus: currentSrus,
    unsur: currentUnsurList,
    alut: currentAlutList,
    savedAt: new Date().toISOString()
  };
  localStorage.setItem(RENOPS_DRAFT_KEY, JSON.stringify(draft));
}

function restoreRenopsDraft() {
  if (isRenopsEditMode) return;
  const raw = localStorage.getItem(RENOPS_DRAFT_KEY);
  if (!raw) return;
  try {
    const d = JSON.parse(raw);
    if (!d) return;
    if (d.cuaca) document.getElementById('input-cuaca').value = d.cuaca;
    if (d.komunikasi) document.getElementById('input-komunikasi').value = d.komunikasi;
    if (d.catatan) document.getElementById('input-catatan').value = d.catatan;
    if (Array.isArray(d.srus) && d.srus.length > 0) { currentSrus = d.srus; renderSruList(); }
    if (Array.isArray(d.unsur) && d.unsur.length > 0) { currentUnsurList = d.unsur; renderUnsurList(); }
    if (Array.isArray(d.alut) && d.alut.length > 0) { currentAlutList = d.alut; renderAlutList(); }
    
    if (typeof showToast === 'function') {
      showToast('Draf pengisian lokal berhasil dipulihkan otomatis.', 'info');
    }
  } catch (e) {}
}

function clearRenopsDraft() {
  localStorage.removeItem(RENOPS_DRAFT_KEY);
}

let renopsDraftTimeout = null;
document.addEventListener('input', (e) => {
  if (isRenopsEditMode) return;
  clearTimeout(renopsDraftTimeout);
  renopsDraftTimeout = setTimeout(saveRenopsDraft, 1000);
});

// Real-time Day Recalculation & Schedule Alignment Based on Input Date
// OPERASI_START_DATE, OPERASI_REOPEN_DATE, OPERASI_BASE_HARI_REOPEN initialized above from renopsCfg

function recalculateRenopsHariKe() {
  const tglInput = document.getElementById('renops_tanggal_laporan')?.value;
  if (!tglInput || !OPERASI_START_DATE) return;
  const t2 = new Date(tglInput + 'T00:00:00');
  if (isNaN(t2.getTime())) return;

  const inputHari = document.getElementById('renops_hari_ke');
  if (!inputHari) return;

  if (OPERASI_REOPEN_DATE) {
    const tReopen = new Date(OPERASI_REOPEN_DATE + 'T00:00:00');
    if (!isNaN(tReopen.getTime()) && t2 >= tReopen) {
      const extDay = Math.max(1, Math.floor((t2 - tReopen) / 86400000) + 1);
      inputHari.value = 'H' + OPERASI_BASE_HARI_REOPEN + ' + ' + extDay;
      return;
    }
  }

  const t1 = new Date(OPERASI_START_DATE + 'T00:00:00');
  if (isNaN(t1.getTime())) return;
  const diffDays = Math.floor((t2 - t1) / 86400000);
  const dayNum = Math.max(1, diffDays + 1);
  inputHari.value = 'H.' + dayNum;
}

function syncRenopsScheduleDates() {
  const tglInput = document.getElementById('renops_tanggal_laporan')?.value;
  if (!tglInput) return;

  const briefingEl = document.getElementById('renops_waktu_briefing');
  if (briefingEl && briefingEl.value) {
    const timePart = briefingEl.value.split('T')[1] || '07:00';
    briefingEl.value = `${tglInput}T${timePart}`;
  }
  const mulaiEl = document.getElementById('renops_waktu_mulai_ops');
  if (mulaiEl && mulaiEl.value) {
    const timePart = mulaiEl.value.split('T')[1] || '07:30';
    mulaiEl.value = `${tglInput}T${timePart}`;
  }
}

document.getElementById('renops_tanggal_laporan')?.addEventListener('input', () => {
  recalculateRenopsHariKe();
  syncRenopsScheduleDates();
});
document.getElementById('renops_tanggal_laporan')?.addEventListener('change', () => {
  recalculateRenopsHariKe();
  syncRenopsScheduleDates();
});

// Ensure all dynamic JSON fields are synced and draft is cleared on submission
document.getElementById('form-renops')?.addEventListener('submit', function() {
  recalculateRenopsHariKe();
  syncUnsurFields();
  syncAlutFields();
  clearRenopsDraft();
});

document.addEventListener('DOMContentLoaded', () => {
  initRenopsUnitSwitcher();
  recalculateRenopsHariKe();
  renderSruList();
  renderUnsurList();
  renderAlutList();
  restoreRenopsDraft();
  recalculateRenopsHariKe();
});

// Expose public API for external scripts, testing & integration
window.RENOPS_FORM = {
  setUnit: setRenopsUnit,
  getUnit: () => activeRenopsUnit,
  formatArea: formatRenopsArea,
  formatAreaShort: formatRenopsAreaShort,
  formatDistance: formatRenopsDistance,
  formatDistanceShort: formatRenopsDistanceShort,
  formatSpacingShort: formatRenopsSpacingShort,
  formatSpeed: formatRenopsSpeed
};

// Cross-tab auto-sync when plotting data is saved/updated/deleted in GIS map tab
window.addEventListener('storage', (e) => {
  if (e.key === 'qr_sar_gis_last_update') {
    refreshGisBundle(false);
  }
});

// Auto-sync when returning to this tab if import modal is active
window.addEventListener('focus', () => {
  const modal = document.getElementById('modal-import-gis-renops');
  if (modal && !modal.classList.contains('hidden')) {
    refreshGisBundle(false);
  }
});

// Kategori input alut: tebakan otomatis dari nama sampai pengguna memilih sendiri.
document.addEventListener('DOMContentLoaded', () => AlutKategoriUI.bindInput('alut-input-nama', 'alut-input-kategori'));
