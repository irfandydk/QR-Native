// Page Configuration from window.__OPS_FORM_CONFIG__
const opsFormCfg = window.__OPS_FORM_CONFIG__ || {};
const isOpsEditMode = !!opsFormCfg.isEditMode;
window.sarUnits = opsFormCfg.sarUnits || [];
window.satkerKode = opsFormCfg.satkerKode || '';

// Helper Presets
function setPresetOps(prefix) {
  const el = document.getElementById('nama_operasi');
  el.value = prefix;
  el.focus();
}

function setEtaPreset(text) {
  document.getElementById('eta_lokasi').value = text;
}

function insertKronologiPreset(text) {
  document.getElementById('kronologi').value = text;
}

// ── Interactive Unsur SAR Terlibat Editor (Reorderable + Master Data Reference) ──
let currentUnsurList = opsFormCfg.initialUnsurData || [];

function renderUnsurList() {
  const container = document.getElementById('unsur-items-container');
  if (!container) return;
  container.innerHTML = '';

  if (currentUnsurList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada unsur SAR yang ditambahkan. Gunakan dropdown Master Data Tim atau form di atas.
      </div>
    `;
  } else {
    currentUnsurList.forEach((item, idx) => {
      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_unsur || '')}" onchange="updateUnsurItem(${idx}, 'nama_unsur', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${item.jumlah_personil || 1}" onchange="updateUnsurItem(${idx}, 'jumlah_personil', parseInt(this.value, 10) || 1)" class="control control-sm w-12 font-bold text-center text-orange-600 dark:text-orange-400" />
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Org</span>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="moveUnsurRow(${idx}, -1)" ${idx === 0 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Atas (Prioritas Naik)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </button>
            <button type="button" onclick="moveUnsurRow(${idx}, 1)" ${idx === currentUnsurList.length - 1 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Bawah (Prioritas Turun)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <button type="button" onclick="removeUnsurRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Unsur">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
      container.appendChild(row);
    });
  }

  syncUnsurFields();
}

function handleSelectTimBerangkat(sel) {
  const customWrapper = document.getElementById('wrapper_custom_tim');
  const customInput = document.getElementById('tim_berangkat');
  const idInput = document.getElementById('id_tim_berangkat');
  if (!sel || !customInput) return;
  const opt = sel.options[sel.selectedIndex];

  if (sel.value === '__CUSTOM__') {
    if (customWrapper) customWrapper.classList.remove('hidden');
    customInput.focus();
    if (idInput) idInput.value = '0';
  } else if (sel.value !== '') {
    if (customWrapper) customWrapper.classList.add('hidden');
    customInput.value = sel.value;
    if (idInput) idInput.value = opt ? (opt.getAttribute('data-id') || '0') : '0';
    matchTeamToOriginBase(sel.value);
  } else {
    if (customWrapper) customWrapper.classList.add('hidden');
    customInput.value = '';
    if (idInput) idInput.value = '0';
  }
  if (typeof updateWaPreview === 'function') {
    updateWaPreview();
  }
}

function pickMasterTim(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_tim) {
      document.getElementById('unsur-input-nama').value = item.nama_tim;
      document.getElementById('unsur-input-jml').value = '1';
      const idEl = document.getElementById('unsur-input-id');
      if (idEl) idEl.value = item.id_tim || '';
      document.getElementById('unsur-input-nama').focus();
    }
  } catch (e) {}
}

function handleAddUnsurClick() {
  const namaInput = document.getElementById('unsur-input-nama');
  const jmlInput = document.getElementById('unsur-input-jml');
  const idInput = document.getElementById('unsur-input-id');

  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;
  const idTim = idInput ? (parseInt(idInput.value, 10) || null) : null;

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentUnsurList.push({
    id_tim: idTim,
    nama_unsur: nama,
    jumlah_personil: jml
  });

  namaInput.value = '';
  jmlInput.value = '1';
  if (idInput) idInput.value = '';
  const sel = document.getElementById('select-master-tim');
  if (sel) sel.value = '';

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
  const textEl = document.getElementById('unsur_terlibat');
  if (jsonEl) jsonEl.value = JSON.stringify(currentUnsurList);

  if (textEl) {
    if (currentUnsurList.length === 0) {
      textEl.value = '';
    } else {
      textEl.value = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Org`).join('\n');
    }
  }

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

// ── DMS Coordinates Auto-Formatter (No special symbol input required) ──
function padZero(num, size = 2) {
  let s = String(num === null || num === undefined ? 0 : num);
  while (s.length < size) s = "0" + s;
  return s;
}

function updateDmsCoordinate() {
  const latDeg = parseInt(document.getElementById('dms_lat_deg')?.value || '0', 10);
  const latMin = parseInt(document.getElementById('dms_lat_min')?.value || '0', 10);
  const latSecVal = parseFloat(document.getElementById('dms_lat_sec')?.value || '0');
  const latDir = document.getElementById('dms_lat_dir')?.value || 'S';

  const lngDeg = parseInt(document.getElementById('dms_lng_deg')?.value || '0', 10);
  const lngMin = parseInt(document.getElementById('dms_lng_min')?.value || '0', 10);
  const lngSecVal = parseFloat(document.getElementById('dms_lng_sec')?.value || '0');
  const lngDir = document.getElementById('dms_lng_dir')?.value || 'E';

  const latSecStr = isNaN(latSecVal) ? '00' : (latSecVal % 1 === 0 ? padZero(Math.floor(latSecVal), 2) : latSecVal.toFixed(1));
  const lngSecStr = isNaN(lngSecVal) ? '00' : (lngSecVal % 1 === 0 ? padZero(Math.floor(lngSecVal), 2) : lngSecVal.toFixed(1));

  const latStr = `${padZero(latDeg, 2)}° ${padZero(latMin, 2)}' ${latSecStr}" ${latDir}`;
  const lngStr = `${padZero(lngDeg, 3)}° ${padZero(lngMin, 2)}' ${lngSecStr}" ${lngDir}`;
  const formatted = `${latStr} - ${lngStr}`;

  const coordInput = document.getElementById('koordinat');
  if (coordInput) coordInput.value = formatted;

  const previewEl = document.getElementById('dms_preview_text');
  if (previewEl) previewEl.textContent = formatted;

  const stripLkpEl = document.getElementById('strip-summary-lkp');
  if (stripLkpEl) stripLkpEl.textContent = formatted;

  if (typeof syncDmsToTacticalMap === 'function') {
    syncDmsToTacticalMap();
  } else if (typeof calculateDistanceAndRadial === 'function') {
    calculateDistanceAndRadial(false);
  }

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

function bindDmsInputEvents() {
  ['dms_lat_deg', 'dms_lat_min', 'dms_lat_sec', 'dms_lat_dir', 'dms_lng_deg', 'dms_lng_min', 'dms_lng_sec', 'dms_lng_dir'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('input', updateDmsCoordinate);
      el.addEventListener('change', updateDmsCoordinate);
    }
  });
}

// ── Interactive Alut & Palsar Editor (Reorderable + Master Data Reference) ──
let currentAlutList = opsFormCfg.initialAlutData || [];

function renderAlutList() {
  const container = document.getElementById('alut-items-container');
  if (!container) return;
  container.innerHTML = '';

  if (currentAlutList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada armada alut / palsar yang ditambahkan. Gunakan dropdown Master Data atau rekomendasi cepat di atas.
      </div>
    `;
  } else {
    currentAlutList.forEach((item, idx) => {
      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_alut || '')}" onchange="updateAlutItem(${idx}, 'nama_alut', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${item.jumlah_alut || 1}" onchange="updateAlutItem(${idx}, 'jumlah_alut', parseInt(this.value, 10) || 1)" class="control control-sm w-12 font-bold text-center text-orange-600 dark:text-orange-400" />
          <select onchange="updateAlutItem(${idx}, 'satuan', this.value)" class="control control-sm">
            <option value="Unit" ${item.satuan === 'Unit' ? 'selected' : ''}>Unit</option>
            <option value="Set" ${item.satuan === 'Set' ? 'selected' : ''}>Set</option>
            <option value="Buah" ${item.satuan === 'Buah' ? 'selected' : ''}>Buah</option>
            <option value="Paket" ${item.satuan === 'Paket' ? 'selected' : ''}>Paket</option>
            <option value="Batang" ${item.satuan === 'Batang' ? 'selected' : ''}>Batang</option>
            <option value="Orang" ${item.satuan === 'Orang' ? 'selected' : ''}>Orang</option>
          </select>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="moveAlutRow(${idx}, -1)" ${idx === 0 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Atas (Prioritas Naik)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </button>
            <button type="button" onclick="moveAlutRow(${idx}, 1)" ${idx === currentAlutList.length - 1 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Bawah (Prioritas Turun)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <button type="button" onclick="removeAlutRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Alut">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
      container.appendChild(row);
    });
  }

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
      const idEl = document.getElementById('alut-input-id');
      if (idEl) idEl.value = item.id_alut || '';
      document.getElementById('alut-input-nama').focus();
    }
  } catch (e) {}
}

function quickAddAlutPreset(nama, jml, sat) {
  currentAlutList.push({
    nama_alut: nama,
    jumlah_alut: jml || 1,
    satuan: sat || 'Unit'
  });
  renderAlutList();
  if (typeof showToast === 'function') {
    showToast(`Alut "${nama}" ditambahkan ke daftar!`, 'success');
  }
}

function handleAddAlutClick() {
  const namaInput = document.getElementById('alut-input-nama');
  const jmlInput = document.getElementById('alut-input-jml');
  const satInput = document.getElementById('alut-input-satuan');
  const idInput = document.getElementById('alut-input-id');

  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;
  const sat = satInput.value.trim() || 'Unit';
  const idAlut = idInput ? (parseInt(idInput.value, 10) || null) : null;

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentAlutList.push({
    id_alut: idAlut,
    nama_alut: nama,
    jumlah_alut: jml,
    satuan: sat
  });

  namaInput.value = '';
  jmlInput.value = '1';
  if (idInput) idInput.value = '';
  document.getElementById('select-master-alut').value = '';

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
    syncAlutFields();
  }
}

function syncAlutFields() {
  const jsonEl = document.getElementById('detail_alut_json');
  const textEl = document.getElementById('alut_palsar');
  if (jsonEl) jsonEl.value = JSON.stringify(currentAlutList);

  if (textEl) {
    if (currentAlutList.length === 0) {
      textEl.value = '';
    } else {
      textEl.value = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
    }
  }

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

// Data Injections from Backend
// sarUnits and satkerKode initialized above from opsFormCfg

// Helper escapeHtml (Unified with QrSarUtils)
const escapeHtml = (window.QrSarUtils && window.QrSarUtils.escapeHtml) || function(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
};

// ── Tactical LKP & Nautical Route Planner Engine ──
const tacticalState = {
  inlineMap: null,
  modalMap: null,
  routingMode: 'direct', // 'direct' | 'manual'
  originLat: -4.037857,
  originLng: 122.486653,
  originName: 'Kantor SAR Kendari',
  originIconUrl: '',
  originPointType: 'custom_upload',
  originCategory: 'Kantor SAR',
  lkpLat: -3.976387,
  lkpLng: 122.588475,
  manualWaypoints: [], // Array of { lat, lng, label }
  vesselSpeedKnots: 25.0,
  speedUnit: 'knots', // 'knots' | 'kmh'
  tacticalDistanceNm: 0.0,
  tacticalDistanceKm: 0.0,
  tacticalRadial: 0,
  tacticalEtaMinutes: 0,
  etaStr: '',
  isUpdatingFromMap: false,
  isUpdatingFromInput: false,
  inlineLayers: {
    originMarker: null,
    lkpMarker: null,
    routeLine: null,
    waypointMarkers: []
  },
  modalLayers: {
    originMarker: null,
    lkpMarker: null,
    routeLine: null,
    waypointMarkers: []
  }
};

function getLkpDecimalCoordinates() {
  const latDeg = parseInt(document.getElementById('dms_lat_deg')?.value || '0', 10);
  const latMin = parseInt(document.getElementById('dms_lat_min')?.value || '0', 10);
  const latSec = parseFloat(document.getElementById('dms_lat_sec')?.value || '0');
  const latDir = document.getElementById('dms_lat_dir')?.value || 'S';

  const lngDeg = parseInt(document.getElementById('dms_lng_deg')?.value || '0', 10);
  const lngMin = parseInt(document.getElementById('dms_lng_min')?.value || '0', 10);
  const lngSec = parseFloat(document.getElementById('dms_lng_sec')?.value || '0');
  const lngDir = document.getElementById('dms_lng_dir')?.value || 'E';

  let lat = latDeg + (latMin / 60) + ((isNaN(latSec) ? 0 : latSec) / 3600);
  if (latDir === 'S') lat = -lat;

  let lng = lngDeg + (lngMin / 60) + ((isNaN(lngSec) ? 0 : lngSec) / 3600);
  if (lngDir === 'W') lng = -lng;

  return { lat, lng };
}

function decimalToDmsParts(lat, lng) {
  const isLatS = lat < 0;
  const absLat = Math.abs(lat);
  let latDeg = Math.floor(absLat);
  let latMinFloat = (absLat - latDeg) * 60;
  let latMin = Math.floor(latMinFloat);
  let latSec = Math.round((latMinFloat - latMin) * 60 * 10) / 10;
  if (latSec >= 60) {
    latSec = 0;
    latMin += 1;
    if (latMin >= 60) {
      latMin = 0;
      latDeg += 1;
    }
  }

  const isLngW = lng < 0;
  const absLng = Math.abs(lng);
  let lngDeg = Math.floor(absLng);
  let lngMinFloat = (absLng - lngDeg) * 60;
  let lngMin = Math.floor(lngMinFloat);
  let lngSec = Math.round((lngMinFloat - lngMin) * 60 * 10) / 10;
  if (lngSec >= 60) {
    lngSec = 0;
    lngMin += 1;
    if (lngMin >= 60) {
      lngMin = 0;
      lngDeg += 1;
    }
  }

  return {
    latDeg,
    latMin,
    latSec,
    latDir: isLatS ? 'S' : 'N',
    lngDeg,
    lngMin,
    lngSec,
    lngDir: isLngW ? 'W' : 'E'
  };
}

function calculateDistanceAndBearing(lat1, lon1, lat2, lon2) {
  const R = 6371; // Earth radius in km
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = 
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * 
    Math.sin(dLon / 2) * Math.sin(dLon / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
  const km = R * c;
  const nm = km * 0.539957; // Nautical miles

  // Initial Compass Bearing / Radial
  const y = Math.sin(dLon) * Math.cos(lat2 * Math.PI / 180);
  const x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
            Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos(dLon);
  let brng = Math.atan2(y, x) * 180 / Math.PI;
  brng = (brng + 360) % 360;

  return { km, nm, bearing: brng };
}

// ── SVG Icons for Leaflet ──
function createLkpDivIcon() {
  return L.divIcon({
    className: 'sar-marker-lkp-container',
    html: `
      <div style="position: relative; width: 44px; height: 52px; cursor: grab;">
        <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 3px; background: rgba(220, 38, 38, 0.95); color: #fff; font-size: 10px; font-weight: 800; padding: 1.5px 7px; border-radius: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.8);">🚨 LKP</div>
        <svg width="44" height="52" viewBox="0 0 44 52">
          <circle cx="22" cy="18" r="17" fill="none" stroke="#dc2626" stroke-width="2" opacity="0.8">
            <animate attributeName="r" values="13;22;13" dur="2s" repeatCount="indefinite" />
            <animate attributeName="opacity" values="0.9;0.1;0.9" dur="2s" repeatCount="indefinite" />
          </circle>
          <path d="M22 48 C20 41, 7 27, 7 18 A15 15 0 1 1 37 18 C37 27, 24 41, 22 48 Z" fill="#dc2626" stroke="#ffffff" stroke-width="2.2" />
          <circle cx="22" cy="18" r="10" fill="#ffffff" />
          <circle cx="22" cy="18" r="7.5" fill="#dc2626" />
          <circle cx="22" cy="18" r="3" fill="#ffffff" />
          <line x1="12" y1="18" x2="32" y2="18" stroke="#ffffff" stroke-width="1.8" />
          <line x1="22" y1="8" x2="22" y2="28" stroke="#ffffff" stroke-width="1.8" />
        </svg>
      </div>
    `,
    iconSize: [44, 52],
    iconAnchor: [22, 50]
  });
}

function normalizeOriginIconUrl(url) {
  if (!url) return '';
  const u = String(url).trim();
  if (!u) return '';
  const idx = u.indexOf('/uploads/icons/');
  if (idx !== -1) {
    const origin = window.location.origin || '';
    return origin + u.substring(idx);
  }
  if (u.startsWith('/uploads/')) {
    const origin = window.location.origin || '';
    return origin + u;
  }
  return u;
}

function createOriginDivIcon(labelName, customUrl, pointType, category) {
  const shortName = labelName ? (labelName.length > 25 ? labelName.substring(0, 23) + '...' : labelName) : 'PANGKALAN';
  const normUrl = normalizeOriginIconUrl(customUrl || tacticalState.originIconUrl);
  
  // Dynamic pin color based on GIS category
  let pinColor = '#ea580c'; // Basarnas Orange default
  let badgeBg = 'rgba(234, 88, 12, 0.95)';
  const cat = (category || tacticalState.originCategory || '').toLowerCase();
  if (cat.includes('kantor')) {
    pinColor = '#c2410c'; // Deep Orange Kansar
    badgeBg = 'rgba(194, 65, 12, 0.95)';
  } else if (cat.includes('dermaga')) {
    pinColor = '#0284c7'; // Marine Blue
    badgeBg = 'rgba(2, 132, 199, 0.95)';
  } else if (cat.includes('siaga')) {
    pinColor = '#059669'; // Emerald
    badgeBg = 'rgba(5, 150, 105, 0.95)';
  }

  let innerSvgContent = '';
  if (normUrl) {
    const safeUrl = escapeHtml(normUrl);
    innerSvgContent = `
      <circle cx="19" cy="16" r="11" fill="#ffffff" />
      <image href="${safeUrl}" xlink:href="${safeUrl}" x="9" y="6" width="20" height="20" preserveAspectRatio="xMidYMid slice" />
    `;
  } else {
    innerSvgContent = `
      <circle cx="19" cy="16" r="10" fill="#ffffff" />
      <text x="19" y="16.5" font-family="'Segoe UI Emoji', Arial" font-size="11" text-anchor="middle" dominant-baseline="central">🏢</text>
    `;
  }

  return L.divIcon({
    className: 'sar-marker-origin-container',
    html: `
      <div style="position: relative; width: 38px; height: 46px; user-select: none;">
        <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 3px; background: ${badgeBg}; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.7); pointer-events: none; max-width: 200px; overflow: hidden; text-overflow: ellipsis; z-index: 10;">
          ${escapeHtml(shortName)}
        </div>
        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="46" viewBox="0 0 38 46" style="display:block;">
          <defs>
            <filter id="origin_shadow" x="-20%" y="-20%" width="140%" height="140%">
              <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000000" flood-opacity="0.45" />
            </filter>
          </defs>
          <g>
            <path d="M19 42 C17 36, 5 24, 5 16 A14 14 0 1 1 33 16 C33 24, 21 36, 19 42 Z" fill="${pinColor}" stroke="#ffffff" stroke-width="2" />
            ${innerSvgContent}
          </g>
        </svg>
      </div>
    `,
    iconSize: [38, 46],
    iconAnchor: [19, 44]
  });
}

function createWaypointDivIcon(idx) {
  return L.divIcon({
    className: 'sar-marker-wp-container',
    html: `
      <div style="position: relative; width: 32px; height: 38px; cursor: grab;">
        <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 2px; background: rgba(217, 119, 6, 0.95); color: #fff; font-size: 9px; font-weight: 800; padding: 1px 5px; border-radius: 4px; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.8);">WP ${idx}</div>
        <svg width="32" height="38" viewBox="0 0 32 38">
          <path d="M16 35 C14 29, 5 20, 5 13 A11 11 0 1 1 27 13 C27 20, 18 29, 16 35 Z" fill="#d97706" stroke="#ffffff" stroke-width="2" />
          <circle cx="16" cy="13" r="8" fill="#ffffff" />
          <text x="16" y="16.5" font-family="Arial, sans-serif" font-size="10" font-weight="900" fill="#92400e" text-anchor="middle">${idx}</text>
        </svg>
      </div>
    `,
    iconSize: [32, 38],
    iconAnchor: [16, 36]
  });
}

function addStandardTileLayers(map) {
  // 1. Google Maps Hybrid (Satelit Resolusi Tinggi + Label Garis Pantai/Jalan) — 100% Tanpa API Key
  const googleHybrid = L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
    maxNativeZoom: 20,
    maxZoom: 22,
    subdomains: ['0', '1', '2', '3'],
    attribution: 'Google Hybrid Satelit'
  });

  // 2. OpenStreetMap Standard (Peta Terbuka / Navigasi Darat & Pesisir) — 100% Tanpa API Key
  const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxNativeZoom: 19,
    maxZoom: 22,
    subdomains: ['a', 'b', 'c'],
    attribution: '© OpenStreetMap contributors'
  });

  // 3. Google Roadmap (Peta Jalan Vektor Bersih) — 100% Tanpa API Key
  const googleRoadmap = L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
    maxNativeZoom: 20,
    maxZoom: 22,
    subdomains: ['0', '1', '2', '3'],
    attribution: 'Google Maps Roadmap'
  });

  // 4. ESRI World Imagery (Citra Satelit Global) — 100% Tanpa API Key
  const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
    maxNativeZoom: 18,
    maxZoom: 22,
    attribution: 'Esri World Imagery'
  });

  // Set Google Hybrid sebagai layer utama aktif (paling jelas untuk operasi laut & darat SAR)
  googleHybrid.addTo(map);

  // Layer Switcher Control
  L.control.layers({
    '🛰️ Google Satelit Hybrid': googleHybrid,
    '🌍 OpenStreetMap': osm,
    '🗺️ Google Roadmap': googleRoadmap,
    '🛰️ Esri Satelit': esriSatellite
  }, null, { position: 'topright' }).addTo(map);
}

// ── Tactical Map Minimize / Collapsible Handler ──
function toggleTacticalMapMinimize(forceState) {
  const bodyEl = document.getElementById('tactical-map-body');
  const stripEl = document.getElementById('tactical-map-minimized-strip');
  const btnIcon = document.getElementById('btn-minimize-icon');
  const btnText = document.getElementById('btn-minimize-text');
  const btnToggle = document.getElementById('btn-toggle-map-minimize');

  if (!bodyEl || !stripEl) return;

  const isCurrentlyHidden = bodyEl.classList.contains('hidden');
  const willMinimize = (typeof forceState === 'boolean') ? forceState : !isCurrentlyHidden;

  if (willMinimize) {
    bodyEl.classList.add('hidden');
    stripEl.classList.remove('hidden');
    if (btnIcon) btnIcon.textContent = '🔼';
    if (btnText) btnText.textContent = 'Buka Peta';
    if (btnToggle) btnToggle.title = 'Buka / Tampilkan kembali peta taktis LKP';
    try {
      localStorage.setItem('qr_sar_map_minimized', 'true');
    } catch (e) {}
  } else {
    bodyEl.classList.remove('hidden');
    stripEl.classList.add('hidden');
    if (btnIcon) btnIcon.textContent = '🔽';
    if (btnText) btnText.textContent = 'Minimize';
    if (btnToggle) btnToggle.title = 'Sembunyikan / Perkecil tampilan peta untuk menghemat ruang';
    try {
      localStorage.setItem('qr_sar_map_minimized', 'false');
    } catch (e) {}

    // Invalidate Leaflet canvas dimensions so all tiles & markers display cleanly
    if (tacticalState.inlineMap) {
      setTimeout(function() {
        tacticalState.inlineMap.invalidateSize();
        fitMapToBounds(tacticalState.inlineMap);
      }, 60);
    }
  }
}

// ── Leaflet Tactical Map Initializer ──
function initLkpTacticalMap() {
  const container = document.getElementById('lkp_inline_map');
  if (!container || typeof L === 'undefined') return;

  // Initialize Origin Base coordinates from selector
  readOriginBaseFromSelect();

  // Initialize LKP coordinates from inputs or config
  const initialCoord = getLkpDecimalCoordinates();
  if (initialCoord && !isNaN(initialCoord.lat) && !isNaN(initialCoord.lng) && (initialCoord.lat !== 0 || initialCoord.lng !== 0)) {
    tacticalState.lkpLat = initialCoord.lat;
    tacticalState.lkpLng = initialCoord.lng;
  }

  // Pre-load existing GIS route waypoints if present (e.g. edit mode)
  if (opsFormCfg.existingRoute && Array.isArray(opsFormCfg.existingRoute.points)) {
    const pts = opsFormCfg.existingRoute.points;
    if (pts.length >= 1) {
      const orig = pts[0];
      const sel = document.getElementById('origin_base_select');
      if (sel) {
        for (let i = 0; i < sel.options.length; i++) {
          const opt = sel.options[i];
          const oLat = parseFloat(opt.getAttribute('data-lat'));
          const oLng = parseFloat(opt.getAttribute('data-lng'));
          if (!isNaN(oLat) && !isNaN(oLng) && Math.abs(oLat - Number(orig.lat)) < 0.005 && Math.abs(oLng - Number(orig.lng)) < 0.005) {
            sel.selectedIndex = i;
            readOriginBaseFromSelect();
            break;
          }
        }
      }
    }
    if (pts.length >= 3) {
      tacticalState.manualWaypoints = pts.slice(1, pts.length - 1).map((p, i) => ({
        lat: Number(p.lat),
        lng: Number(p.lng),
        label: p.label || ('WP ' + (i + 1))
      }));
      tacticalState.routingMode = 'manual';
    }
    if (opsFormCfg.existingRoute.vesselSpeedKnots) {
      tacticalState.vesselSpeedKnots = parseFloat(opsFormCfg.existingRoute.vesselSpeedKnots) || 25.0;
    }
  }

  // Set speed inputs & presets
  const speedNumEl = document.getElementById('input_vessel_speed_num');
  const unitSelectEl = document.getElementById('select_speed_unit');
  const speedPresetSel = document.getElementById('select_speed_preset');
  if (speedPresetSel) {
    const matchedOpt = Array.from(speedPresetSel.options).find(o => {
      const u = o.getAttribute('data-unit') || 'knots';
      const val = parseFloat(o.value);
      if (isNaN(val)) return false;
      const knotsVal = u === 'kmh' ? (val / 1.852) : val;
      return Math.abs(knotsVal - tacticalState.vesselSpeedKnots) < 0.05;
    });
    if (matchedOpt) {
      speedPresetSel.value = matchedOpt.value;
      const u = matchedOpt.getAttribute('data-unit') || 'knots';
      tacticalState.speedUnit = u;
      if (unitSelectEl) unitSelectEl.value = u;
      if (speedNumEl) speedNumEl.value = matchedOpt.value;
    } else {
      speedPresetSel.value = '__CUSTOM__';
      if (speedNumEl) speedNumEl.value = tacticalState.vesselSpeedKnots.toFixed(1);
      if (unitSelectEl) unitSelectEl.value = 'knots';
      tacticalState.speedUnit = 'knots';
    }
  }

  // Create Leaflet Map
  tacticalState.inlineMap = L.map('lkp_inline_map', {
    center: [tacticalState.lkpLat, tacticalState.lkpLng],
    zoom: 10,
    minZoom: 4,
    maxZoom: 22,
    zoomSnap: 0.5,
    zoomDelta: 0.5,
    zoomControl: true
  });

  addStandardTileLayers(tacticalState.inlineMap);

  // Markers & Route Line
  tacticalState.inlineLayers.originMarker = L.marker([tacticalState.originLat, tacticalState.originLng], {
    icon: createOriginDivIcon(tacticalState.originName, tacticalState.originIconUrl, tacticalState.originPointType, tacticalState.originCategory),
    zIndexOffset: 500
  }).addTo(tacticalState.inlineMap);

  tacticalState.inlineLayers.lkpMarker = L.marker([tacticalState.lkpLat, tacticalState.lkpLng], {
    icon: createLkpDivIcon(),
    draggable: true,
    zIndexOffset: 1000
  }).addTo(tacticalState.inlineMap);

  tacticalState.inlineLayers.routeLine = L.polyline([], {
    color: '#ea580c',
    weight: 3.5,
    opacity: 0.9,
    dashArray: '8, 8',
    lineJoin: 'round'
  }).addTo(tacticalState.inlineMap);

  // LKP Dragging Events
  tacticalState.inlineLayers.lkpMarker.on('drag', function(e) {
    const pos = e.target.getLatLng();
    onLkpMarkerDragged(pos.lat, pos.lng);
  });

  tacticalState.inlineLayers.lkpMarker.on('dragend', function(e) {
    const pos = e.target.getLatLng();
    onLkpMarkerDragged(pos.lat, pos.lng, true);
  });

  // Map Click Handler (direct repositioning or waypoint addition)
  tacticalState.inlineMap.on('click', function(e) {
    if (tacticalState.routingMode === 'manual') {
      addManualWaypoint(e.latlng);
    } else {
      tacticalState.inlineLayers.lkpMarker.setLatLng(e.latlng);
      onLkpMarkerDragged(e.latlng.lat, e.latlng.lng, true);
    }
  });

  // Fit initial bounds to include Origin and LKP
  fitMapToBounds(tacticalState.inlineMap);

  // Sync route mode UI
  setRoutingMode(tacticalState.routingMode, false);

  // Initial calculation
  redrawTacticalRoute();
  recalculateTacticalMetrics(false);

  // Restore persisted minimize state
  try {
    if (localStorage.getItem('qr_sar_map_minimized') === 'true') {
      toggleTacticalMapMinimize(true);
    }
  } catch (e) {}
}

function readOriginBaseFromSelect() {
  const originSelect = document.getElementById('origin_base_select');
  if (!originSelect) return;
  const opt = originSelect.options[originSelect.selectedIndex];
  if (!opt) return;

  const lat = parseFloat(opt.getAttribute('data-lat'));
  const lng = parseFloat(opt.getAttribute('data-lng'));
  const icon = opt.getAttribute('data-icon') || '';
  const pointType = opt.getAttribute('data-point-type') || 'custom_upload';
  const category = opt.getAttribute('data-category') || '';

  let name = opt.textContent.trim();
  // Strip emojis like 🏢, ⚓, 🚨
  name = name.replace(/^[\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}\s]+/u, '').trim();
  if (name.includes('—')) {
    name = name.split('—')[0].trim();
  }
  if (name.startsWith('[')) {
    const endBracket = name.indexOf(']');
    if (endBracket !== -1) {
      name = name.substring(endBracket + 1).trim();
    }
  }

  if (!isNaN(lat) && !isNaN(lng)) {
    tacticalState.originLat = lat;
    tacticalState.originLng = lng;
    tacticalState.originName = name;
    tacticalState.originIconUrl = icon;
    tacticalState.originPointType = pointType;
    tacticalState.originCategory = category;

    const latIn = document.getElementById('origin_lat');
    const lngIn = document.getElementById('origin_lng');
    const nameIn = document.getElementById('origin_name');
    const iconIn = document.getElementById('origin_icon');
    const typeIn = document.getElementById('origin_point_type');

    if (latIn) latIn.value = lat.toFixed(6);
    if (lngIn) lngIn.value = lng.toFixed(6);
    if (nameIn) nameIn.value = name;
    if (iconIn) iconIn.value = icon;
    if (typeIn) typeIn.value = pointType;
  }
}

function fitMapToBounds(mapInstance) {
  if (!mapInstance) return;
  const pts = [
    [tacticalState.originLat, tacticalState.originLng],
    ...tacticalState.manualWaypoints.map(w => [w.lat, w.lng]),
    [tacticalState.lkpLat, tacticalState.lkpLng]
  ];
  try {
    const bounds = L.latLngBounds(pts);
    mapInstance.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
  } catch (e) {}
}

// ── Bidirectional Sync: Map -> Form Inputs ──
function onLkpMarkerDragged(lat, lng, isFinal = false) {
  tacticalState.isUpdatingFromMap = true;
  tacticalState.lkpLat = lat;
  tacticalState.lkpLng = lng;

  const dms = decimalToDmsParts(lat, lng);
  const latDegEl = document.getElementById('dms_lat_deg');
  const latMinEl = document.getElementById('dms_lat_min');
  const latSecEl = document.getElementById('dms_lat_sec');
  const latDirEl = document.getElementById('dms_lat_dir');

  const lngDegEl = document.getElementById('dms_lng_deg');
  const lngMinEl = document.getElementById('dms_lng_min');
  const lngSecEl = document.getElementById('dms_lng_sec');
  const lngDirEl = document.getElementById('dms_lng_dir');

  if (latDegEl) latDegEl.value = padZero(dms.latDeg, 2);
  if (latMinEl) latMinEl.value = padZero(dms.latMin, 2);
  if (latSecEl) latSecEl.value = dms.latSec.toString();
  if (latDirEl) latDirEl.value = dms.latDir;

  if (lngDegEl) lngDegEl.value = padZero(dms.lngDeg, 3);
  if (lngMinEl) lngMinEl.value = padZero(dms.lngMin, 2);
  if (lngSecEl) lngSecEl.value = dms.lngSec.toString();
  if (lngDirEl) lngDirEl.value = dms.lngDir;

  const latSecStr = dms.latSec % 1 === 0 ? padZero(Math.floor(dms.latSec), 2) : dms.latSec.toFixed(1);
  const lngSecStr = dms.lngSec % 1 === 0 ? padZero(Math.floor(dms.lngSec), 2) : dms.lngSec.toFixed(1);

  const formatted = `${padZero(dms.latDeg, 2)}° ${padZero(dms.latMin, 2)}' ${latSecStr}" ${dms.latDir} - ${padZero(dms.lngDeg, 3)}° ${padZero(dms.lngMin, 2)}' ${lngSecStr}" ${dms.lngDir}`;
  
  const coordInput = document.getElementById('koordinat');
  if (coordInput) coordInput.value = formatted;
  const previewEl = document.getElementById('dms_preview_text');
  if (previewEl) previewEl.textContent = formatted;

  const lkpLatIn = document.getElementById('lkp_lat');
  const lkpLngIn = document.getElementById('lkp_lng');
  if (lkpLatIn) lkpLatIn.value = lat.toFixed(6);
  if (lkpLngIn) lkpLngIn.value = lng.toFixed(6);

  // Sync modal marker if open
  if (tacticalState.modalLayers.lkpMarker && tacticalState.modalLayers.lkpMarker !== this) {
    tacticalState.modalLayers.lkpMarker.setLatLng([lat, lng]);
  }

  redrawTacticalRoute();
  recalculateTacticalMetrics(false);

  if (isFinal && typeof updateLiveWa === 'function') {
    updateLiveWa();
  }

  tacticalState.isUpdatingFromMap = false;
}

// ── Bidirectional Sync: Form Inputs -> Map ──
function syncDmsToTacticalMap() {
  if (tacticalState.isUpdatingFromMap) return;

  const coord = getLkpDecimalCoordinates();
  if (!coord || isNaN(coord.lat) || isNaN(coord.lng) || (coord.lat === 0 && coord.lng === 0)) return;

  tacticalState.lkpLat = coord.lat;
  tacticalState.lkpLng = coord.lng;

  const lkpLatIn = document.getElementById('lkp_lat');
  const lkpLngIn = document.getElementById('lkp_lng');
  if (lkpLatIn) lkpLatIn.value = coord.lat.toFixed(6);
  if (lkpLngIn) lkpLngIn.value = coord.lng.toFixed(6);

  if (tacticalState.inlineLayers.lkpMarker) {
    tacticalState.inlineLayers.lkpMarker.setLatLng([coord.lat, coord.lng]);
  }
  if (tacticalState.modalLayers.lkpMarker) {
    tacticalState.modalLayers.lkpMarker.setLatLng([coord.lat, coord.lng]);
  }

  redrawTacticalRoute();
  recalculateTacticalMetrics(false);
}

// ── Routing Mode Controls ──
function setRoutingMode(mode, updateState = true) {
  tacticalState.routingMode = mode;

  // Buttons state in inline card
  const btnDirect = document.getElementById('btn-mode-direct');
  const btnManual = document.getElementById('btn-mode-manual');
  const manualActions = document.getElementById('manual-route-actions');
  const modeBadge = document.getElementById('route-mode-badge');
  const instructionText = document.getElementById('map-instruction-text');

  // Buttons state in modal (supports both new map-modal-* and legacy modal-*)
  const modalBtnDirect = document.getElementById('map-modal-btn-mode-direct') || document.getElementById('modal-btn-mode-direct');
  const modalBtnManual = document.getElementById('map-modal-btn-mode-manual') || document.getElementById('modal-btn-mode-manual');
  const modalManualActions = document.getElementById('map-modal-manual-actions') || document.getElementById('modal-manual-actions');
  const modalModeBadge = document.getElementById('map-modal-route-mode-badge') || document.getElementById('modal-route-mode-badge');

  if (mode === 'manual') {
    if (btnDirect) {
      btnDirect.className = 'flex-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 cursor-pointer';
    }
    if (btnManual) {
      btnManual.className = 'flex-1 px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-white dark:bg-slate-700 text-orange-600 dark:text-orange-400 shadow-xs flex items-center justify-center gap-1.5 cursor-pointer';
    }
    if (manualActions) manualActions.classList.remove('hidden');

    if (modalBtnDirect) {
      modalBtnDirect.className = 'px-2.5 py-1 rounded-md text-[11px] font-medium text-slate-300 hover:text-white cursor-pointer';
    }
    if (modalBtnManual) {
      modalBtnManual.className = 'px-2.5 py-1 rounded-md text-[11px] font-bold bg-orange-600 text-white cursor-pointer';
    }
    if (modalManualActions) modalManualActions.classList.remove('hidden');

    if (modeBadge) {
      modeBadge.textContent = 'JALUR MANUAL (WAYPOINT)';
      modeBadge.className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30';
    }
    if (modalModeBadge) {
      modalModeBadge.textContent = 'JALUR MANUAL (WAYPOINT)';
      modalModeBadge.className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30';
    }

    if (instructionText) {
      instructionText.textContent = 'Mode Jalur Manual: Klik pada peta laut untuk menambah titik waypoint belokan.';
    }
  } else {
    if (btnDirect) {
      btnDirect.className = 'flex-1 px-3 py-1.5 rounded-lg text-xs font-bold transition-all bg-white dark:bg-slate-700 text-orange-600 dark:text-orange-400 shadow-xs flex items-center justify-center gap-1.5 cursor-pointer';
    }
    if (btnManual) {
      btnManual.className = 'flex-1 px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-all flex items-center justify-center gap-1.5 cursor-pointer';
    }
    if (manualActions) manualActions.classList.add('hidden');

    if (modalBtnDirect) {
      modalBtnDirect.className = 'px-2.5 py-1 rounded-md text-[11px] font-bold bg-orange-600 text-white cursor-pointer';
    }
    if (modalBtnManual) {
      modalBtnManual.className = 'px-2.5 py-1 rounded-md text-[11px] font-medium text-slate-300 hover:text-white cursor-pointer';
    }
    if (modalManualActions) modalManualActions.classList.add('hidden');

    if (modeBadge) {
      modeBadge.textContent = 'GARIS LURUS';
      modeBadge.className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30';
    }
    if (modalModeBadge) {
      modalModeBadge.textContent = 'GARIS LURUS';
      modalModeBadge.className = 'text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30';
    }

    if (instructionText) {
      instructionText.textContent = 'Geser pin merah 🚨 untuk mengatur LKP, atau klik peta untuk pindah posisi.';
    }
  }

  if (updateState) {
    redrawTacticalRoute();
    recalculateTacticalMetrics(false);
  }
}

function addManualWaypoint(latlng) {
  const wpIndex = tacticalState.manualWaypoints.length + 1;
  tacticalState.manualWaypoints.push({
    lat: latlng.lat,
    lng: latlng.lng,
    label: `WP ${wpIndex}`
  });

  redrawTacticalRoute();
  recalculateTacticalMetrics(false);
  if (typeof showToast === 'function') {
    showToast(`Waypoint ${wpIndex} berhasil ditambahkan!`, 'info');
  }
}

function undoLastWaypoint() {
  if (tacticalState.manualWaypoints.length === 0) return;
  tacticalState.manualWaypoints.pop();
  redrawTacticalRoute();
  recalculateTacticalMetrics(false);
  if (typeof showToast === 'function') {
    showToast('Waypoint terakhir dihapus.', 'info');
  }
}

function clearManualRouteWaypoints() {
  if (tacticalState.manualWaypoints.length === 0) return;
  tacticalState.manualWaypoints = [];
  redrawTacticalRoute();
  recalculateTacticalMetrics(false);
  if (typeof showToast === 'function') {
    showToast('Seluruh waypoint rute telah dibersihkan.', 'info');
  }
}

// ── Redraw Route Polyline & Waypoints on Active Maps ──
function redrawTacticalRoute() {
  const wpCountPill = document.getElementById('waypoint-count-pill');
  const modalWpCountPill = document.getElementById('map-modal-waypoint-count-pill') || document.getElementById('modal-waypoint-count-pill');
  const countText = `${tacticalState.manualWaypoints.length} Waypoint`;
  if (wpCountPill) wpCountPill.textContent = countText;
  if (modalWpCountPill) modalWpCountPill.textContent = countText;

  // Build full coordinate sequence for polyline
  let latlngs = [];
  latlngs.push([tacticalState.originLat, tacticalState.originLng]);
  if (tacticalState.routingMode === 'manual' && tacticalState.manualWaypoints.length > 0) {
    tacticalState.manualWaypoints.forEach(wp => latlngs.push([wp.lat, wp.lng]));
  }
  latlngs.push([tacticalState.lkpLat, tacticalState.lkpLng]);

  // Update inline map polyline
  if (tacticalState.inlineLayers.routeLine) {
    tacticalState.inlineLayers.routeLine.setLatLngs(latlngs);
  }

  // Update modal map polyline
  if (tacticalState.modalLayers.routeLine) {
    tacticalState.modalLayers.routeLine.setLatLngs(latlngs);
  }

  // Rebuild waypoint markers on inline map
  if (tacticalState.inlineMap) {
    tacticalState.inlineLayers.waypointMarkers.forEach(m => m.remove());
    tacticalState.inlineLayers.waypointMarkers = [];

    if (tacticalState.routingMode === 'manual') {
      tacticalState.manualWaypoints.forEach((wp, idx) => {
        const marker = L.marker([wp.lat, wp.lng], {
          icon: createWaypointDivIcon(idx + 1),
          draggable: true,
          zIndexOffset: 700
        }).addTo(tacticalState.inlineMap);

        marker.on('drag', function(e) {
          const pos = e.target.getLatLng();
          tacticalState.manualWaypoints[idx].lat = pos.lat;
          tacticalState.manualWaypoints[idx].lng = pos.lng;
          redrawTacticalRoute();
          recalculateTacticalMetrics(false);
        });

        tacticalState.inlineLayers.waypointMarkers.push(marker);
      });
    }
  }

  // Rebuild waypoint markers on modal map
  if (tacticalState.modalMap) {
    tacticalState.modalLayers.waypointMarkers.forEach(m => m.remove());
    tacticalState.modalLayers.waypointMarkers = [];

    if (tacticalState.routingMode === 'manual') {
      tacticalState.manualWaypoints.forEach((wp, idx) => {
        const marker = L.marker([wp.lat, wp.lng], {
          icon: createWaypointDivIcon(idx + 1),
          draggable: true,
          zIndexOffset: 700
        }).addTo(tacticalState.modalMap);

        marker.on('drag', function(e) {
          const pos = e.target.getLatLng();
          tacticalState.manualWaypoints[idx].lat = pos.lat;
          tacticalState.manualWaypoints[idx].lng = pos.lng;
          redrawTacticalRoute();
          recalculateTacticalMetrics(false);
        });

        tacticalState.modalLayers.waypointMarkers.push(marker);
      });
    }
  }
}

// ── Speed Presets & Manual Speed Calculation ──
function handleSpeedPresetChange(sel) {
  if (!sel) return;
  const speedNumInput = document.getElementById('input_vessel_speed_num');
  const unitSelect = document.getElementById('select_speed_unit');

  if (sel.value === '__CUSTOM__') {
    if (speedNumInput) speedNumInput.focus();
    return;
  }

  const opt = sel.options[sel.selectedIndex];
  const unit = opt ? (opt.getAttribute('data-unit') || 'knots') : 'knots';
  const speedVal = parseFloat(sel.value);

  if (!isNaN(speedVal) && speedVal > 0) {
    tacticalState.speedUnit = unit;
    if (unitSelect) unitSelect.value = unit;
    if (speedNumInput) speedNumInput.value = speedVal.toString();

    if (unit === 'kmh') {
      tacticalState.vesselSpeedKnots = speedVal / 1.852;
    } else {
      tacticalState.vesselSpeedKnots = speedVal;
    }

    const speedKnotsHidden = document.getElementById('vessel_speed_knots');
    if (speedKnotsHidden) speedKnotsHidden.value = tacticalState.vesselSpeedKnots.toFixed(2);

    recalculateTacticalMetrics(false);
  }
}

function handleManualSpeedInput(val) {
  const num = parseFloat(val);
  if (isNaN(num) || num <= 0) return;

  if (tacticalState.speedUnit === 'kmh') {
    tacticalState.vesselSpeedKnots = num / 1.852;
  } else {
    tacticalState.vesselSpeedKnots = num;
  }

  const speedKnotsHidden = document.getElementById('vessel_speed_knots');
  if (speedKnotsHidden) speedKnotsHidden.value = tacticalState.vesselSpeedKnots.toFixed(2);

  // Set preset select to custom if value doesn't match
  const speedPresetSel = document.getElementById('select_speed_preset');
  if (speedPresetSel) {
    const matched = Array.from(speedPresetSel.options).find(o => {
      const oUnit = o.getAttribute('data-unit') || 'knots';
      return oUnit === tacticalState.speedUnit && Math.abs(parseFloat(o.value) - num) < 0.05;
    });
    speedPresetSel.value = matched ? matched.value : '__CUSTOM__';
  }

  recalculateTacticalMetrics(false);
}

function handleSpeedUnitChange(unit) {
  const prevUnit = tacticalState.speedUnit;
  tacticalState.speedUnit = unit;
  const speedNumInput = document.getElementById('input_vessel_speed_num');
  if (!speedNumInput) return;

  if (unit === 'kmh' && prevUnit === 'knots') {
    const kmh = tacticalState.vesselSpeedKnots * 1.852;
    speedNumInput.value = (Math.round(kmh * 10) / 10).toString();
  } else if (unit === 'knots' && prevUnit === 'kmh') {
    speedNumInput.value = (Math.round(tacticalState.vesselSpeedKnots * 10) / 10).toString();
  }

  // Match preset dropdown if applicable
  const speedPresetSel = document.getElementById('select_speed_preset');
  if (speedPresetSel) {
    const curVal = parseFloat(speedNumInput.value);
    const matched = Array.from(speedPresetSel.options).find(o => {
      const oUnit = o.getAttribute('data-unit') || 'knots';
      return oUnit === unit && Math.abs(parseFloat(o.value) - curVal) < 0.05;
    });
    speedPresetSel.value = matched ? matched.value : '__CUSTOM__';
  }

  recalculateTacticalMetrics(false);
}

// ── Tactical Metrics Calculation (Distance, Radial, ETA) ──
function recalculateTacticalMetrics(autoApply = false) {
  // Build sequence: Origin -> Waypoints -> LKP
  const pts = [
    { lat: tacticalState.originLat, lng: tacticalState.originLng, label: tacticalState.originName }
  ];

  if (tacticalState.routingMode === 'manual') {
    tacticalState.manualWaypoints.forEach(w => pts.push(w));
  }

  pts.push({ lat: tacticalState.lkpLat, lng: tacticalState.lkpLng, label: 'LKP' });

  // Calculate cumulative distance
  let totalKm = 0;
  let totalNm = 0;

  for (let i = 0; i < pts.length - 1; i++) {
    const leg = calculateDistanceAndBearing(pts[i].lat, pts[i].lng, pts[i+1].lat, pts[i+1].lng);
    totalKm += leg.km;
    totalNm += leg.nm;
  }

  // Radial LKP selalu dihitung langsung dari Titik Awal (Origin Base) ke LKP (Standar Operasi SAR)
  const directLkp = calculateDistanceAndBearing(tacticalState.originLat, tacticalState.originLng, tacticalState.lkpLat, tacticalState.lkpLng);
  const departureRadial = Math.round(directLkp.bearing);

  // ETA Calculation based on tacticalState.vesselSpeedKnots
  const speedKnots = tacticalState.vesselSpeedKnots > 0 ? tacticalState.vesselSpeedKnots : 25.0;
  const hours = totalNm / speedKnots;
  const totalMins = Math.max(1, Math.round(hours * 60));

  let etaStr = '';
  if (totalMins < 60) {
    etaStr = `${totalMins} Menit`;
  } else {
    const h = Math.floor(totalMins / 60);
    const m = totalMins % 60;
    etaStr = m > 0 ? `${h} Jam ${m} Menit` : `${h} Jam`;
  }

  tacticalState.tacticalDistanceNm = totalNm;
  tacticalState.tacticalDistanceKm = totalKm;
  tacticalState.tacticalRadial = departureRadial;
  tacticalState.tacticalEtaMinutes = totalMins;
  tacticalState.etaStr = etaStr;

  // Update Inline Summary Elements
  const distEl = document.getElementById('tactical-summary-dist');
  const kmEl = document.getElementById('tactical-summary-km');
  const radEl = document.getElementById('tactical-summary-radial');
  const etaEl = document.getElementById('tactical-summary-eta');

  if (distEl) distEl.textContent = `${totalNm.toFixed(1)} NM`;
  if (kmEl) kmEl.textContent = `(${totalKm.toFixed(1)} KM)`;
  if (radEl) radEl.textContent = `${departureRadial.toString().padStart(3, '0')}°`;
  if (etaEl) etaEl.textContent = etaStr;

  // Update Minimized Strip Elements (shown when map is minimized)
  const stripDistEl = document.getElementById('strip-summary-dist');
  const stripEtaEl = document.getElementById('strip-summary-eta');
  const stripLkpEl = document.getElementById('strip-summary-lkp');

  if (stripDistEl) stripDistEl.textContent = `${totalNm.toFixed(1)} NM (${totalKm.toFixed(1)} KM)`;
  if (stripEtaEl) stripEtaEl.textContent = etaStr;
  if (stripLkpEl) {
    const formattedCoord = document.getElementById('koordinat')?.value || document.getElementById('dms_preview_text')?.textContent;
    if (formattedCoord && formattedCoord.trim()) {
      stripLkpEl.textContent = formattedCoord.trim();
    } else {
      stripLkpEl.textContent = `${tacticalState.lkpLat.toFixed(5)}, ${tacticalState.lkpLng.toFixed(5)}`;
    }
  }

  // Update Modal Summary Elements (supports both new map-modal-* and legacy modal-*)
  const mDistEl = document.getElementById('map-modal-tactical-dist') || document.getElementById('modal-tactical-dist');
  const mKmEl = document.getElementById('map-modal-tactical-km') || document.getElementById('modal-tactical-km');
  const mRadEl = document.getElementById('map-modal-tactical-radial') || document.getElementById('modal-tactical-radial');
  const mEtaEl = document.getElementById('map-modal-tactical-eta') || document.getElementById('modal-tactical-eta');

  if (mDistEl) mDistEl.textContent = `${totalNm.toFixed(1)} NM`;
  if (mKmEl) mKmEl.textContent = `(${totalKm.toFixed(1)} KM)`;
  if (mRadEl) mRadEl.textContent = `${departureRadial.toString().padStart(3, '0')}°`;
  if (mEtaEl) mEtaEl.textContent = etaStr;

  // Update Origin Feedback text
  const fbEl = document.getElementById('origin_calc_feedback');
  if (fbEl) {
    const routeTypeStr = tacticalState.routingMode === 'manual' ? `Jalur Navigasi (${tacticalState.manualWaypoints.length} WP)` : 'Garis Lurus';
    fbEl.innerHTML = `📍 Terkalkulasi dari <strong>${escapeHtml(tacticalState.originName)}</strong> ke LKP [${routeTypeStr}]: <strong>${totalNm.toFixed(1)} NM</strong> (${totalKm.toFixed(1)} KM), Radial <strong>${departureRadial.toString().padStart(3, '0')}°</strong>`;
  }

  // Update Hidden Inputs for GIS & Backend Sync
  const manualRouteHidden = document.getElementById('manual_route_json');
  if (manualRouteHidden) {
    manualRouteHidden.value = JSON.stringify(pts);
  }

  const tacticalRouteHidden = document.getElementById('tactical_route_data');
  if (tacticalRouteHidden) {
    tacticalRouteHidden.value = JSON.stringify({
      routingMode: tacticalState.routingMode,
      origin: { lat: tacticalState.originLat, lng: tacticalState.originLng, name: tacticalState.originName },
      lkp: { lat: tacticalState.lkpLat, lng: tacticalState.lkpLng },
      waypoints: tacticalState.manualWaypoints,
      totalNm: totalNm,
      totalKm: totalKm,
      radial: departureRadial,
      speedKnots: tacticalState.vesselSpeedKnots,
      etaMinutes: totalMins,
      etaStr: etaStr,
      points: pts
    });
  }

  const speedHidden = document.getElementById('vessel_speed_knots');
  if (speedHidden) {
    speedHidden.value = speedKnots.toFixed(2);
  }

  const oLatIn = document.getElementById('origin_lat');
  const oLngIn = document.getElementById('origin_lng');
  const oNameIn = document.getElementById('origin_name');
  if (oLatIn) oLatIn.value = tacticalState.originLat;
  if (oLngIn) oLngIn.value = tacticalState.originLng;
  if (oNameIn) oNameIn.value = tacticalState.originName;

  if (autoApply) {
    applyTacticalRouteToForm();
  }
}

// ── Apply Calculated Tactical Values into Form Fields ──
function applyTacticalRouteToForm() {
  const jarakUnit = (document.getElementById('jarak_unit')?.value || 'NM').toUpperCase();
  currentJarakUnit = jarakUnit;
  const jarakAngkaEl = document.getElementById('jarak_angka');
  const radialAngkaEl = document.getElementById('radial_angka');
  const etaLokasiEl = document.getElementById('eta_lokasi');

  if (jarakAngkaEl) {
    jarakAngkaEl.value = (jarakUnit === 'KM' ? tacticalState.tacticalDistanceKm : tacticalState.tacticalDistanceNm).toFixed(1);
  }
  if (radialAngkaEl) {
    radialAngkaEl.value = tacticalState.tacticalRadial.toString().padStart(3, '0');
  }
  if (etaLokasiEl) {
    etaLokasiEl.value = tacticalState.etaStr;
  }

  // Auto-calculate eta_tiba datetime if waktu_berangkat is filled
  const waktuBerangkatEl = document.getElementById('waktu_berangkat');
  const etaTibaEl = document.getElementById('eta_tiba');
  if (waktuBerangkatEl && waktuBerangkatEl.value && etaTibaEl) {
    try {
      const bDate = new Date(waktuBerangkatEl.value);
      if (!isNaN(bDate.getTime())) {
        const arrivalDate = new Date(bDate.getTime() + tacticalState.tacticalEtaMinutes * 60000);
        // Format to YYYY-MM-DDTHH:mm for datetime-local input
        const y = arrivalDate.getFullYear();
        const m = padZero(arrivalDate.getMonth() + 1, 2);
        const d = padZero(arrivalDate.getDate(), 2);
        const hh = padZero(arrivalDate.getHours(), 2);
        const mm = padZero(arrivalDate.getMinutes(), 2);
        etaTibaEl.value = `${y}-${m}-${d}T${hh}:${mm}`;
      }
    } catch (e) {}
  }

  syncJarakDanRadial();

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }

  if (typeof showToast === 'function') {
    showToast(`Hasil plotting rute (${tacticalState.tacticalDistanceNm.toFixed(1)} NM, ETA ${tacticalState.etaStr}) diterapkan ke formulir!`, 'success');
  }
}

// ── Recenter / Pan Map Helpers ──
function centerMapOnLkp(isModal = false) {
  const targetMap = isModal ? tacticalState.modalMap : tacticalState.inlineMap;
  if (targetMap && !isNaN(tacticalState.lkpLat) && !isNaN(tacticalState.lkpLng)) {
    targetMap.flyTo([tacticalState.lkpLat, tacticalState.lkpLng], 12, { duration: 1 });
  }
}

function centerMapOnOrigin(isModal = false) {
  const targetMap = isModal ? tacticalState.modalMap : tacticalState.inlineMap;
  if (targetMap && !isNaN(tacticalState.originLat) && !isNaN(tacticalState.originLng)) {
    targetMap.flyTo([tacticalState.originLat, tacticalState.originLng], 12, { duration: 1 });
  }
}

// ── Fullscreen Map Modal Initializer & Handlers ──
function openFullscreenMapModal() {
  openModal('modal-fullscreen-map');

  setTimeout(function() {
    if (!tacticalState.modalMap) {
      tacticalState.modalMap = L.map('lkp_modal_map', {
        center: [tacticalState.lkpLat, tacticalState.lkpLng],
        zoom: 10,
        minZoom: 4,
        maxZoom: 22,
        zoomSnap: 0.5,
        zoomDelta: 0.5,
        zoomControl: true
      });

      addStandardTileLayers(tacticalState.modalMap);

      tacticalState.modalLayers.originMarker = L.marker([tacticalState.originLat, tacticalState.originLng], {
        icon: createOriginDivIcon(tacticalState.originName, tacticalState.originIconUrl, tacticalState.originPointType, tacticalState.originCategory),
        zIndexOffset: 500
      }).addTo(tacticalState.modalMap);

      tacticalState.modalLayers.lkpMarker = L.marker([tacticalState.lkpLat, tacticalState.lkpLng], {
        icon: createLkpDivIcon(),
        draggable: true,
        zIndexOffset: 1000
      }).addTo(tacticalState.modalMap);

      tacticalState.modalLayers.routeLine = L.polyline([], {
        color: '#ea580c',
        weight: 4,
        opacity: 0.95,
        dashArray: '8, 8',
        lineJoin: 'round'
      }).addTo(tacticalState.modalMap);

      tacticalState.modalLayers.lkpMarker.on('drag', function(e) {
        const pos = e.target.getLatLng();
        onLkpMarkerDragged(pos.lat, pos.lng);
        if (tacticalState.inlineLayers.lkpMarker) {
          tacticalState.inlineLayers.lkpMarker.setLatLng(pos);
        }
      });

      tacticalState.modalLayers.lkpMarker.on('dragend', function(e) {
        const pos = e.target.getLatLng();
        onLkpMarkerDragged(pos.lat, pos.lng, true);
      });

      tacticalState.modalMap.on('click', function(e) {
        if (tacticalState.routingMode === 'manual') {
          addManualWaypoint(e.latlng);
        } else {
          tacticalState.modalLayers.lkpMarker.setLatLng(e.latlng);
          if (tacticalState.inlineLayers.lkpMarker) {
            tacticalState.inlineLayers.lkpMarker.setLatLng(e.latlng);
          }
          onLkpMarkerDragged(e.latlng.lat, e.latlng.lng, true);
        }
      });
    }

    tacticalState.modalMap.invalidateSize();
    redrawTacticalRoute();
    fitMapToBounds(tacticalState.modalMap);
  }, 100);
}

function closeFullscreenMapModal() {
  closeModal('modal-fullscreen-map');
  if (typeof window.ensureBodyScrollUnlocked === 'function') {
    window.ensureBodyScrollUnlocked();
  }
  setTimeout(function() {
    if (tacticalState.inlineMap) {
      tacticalState.inlineMap.invalidateSize();
      redrawTacticalRoute();
    }
    if (typeof window.ensureBodyScrollUnlocked === 'function') {
      window.ensureBodyScrollUnlocked();
    }
  }, 100);
}

function applyAndCloseFullscreenMapModal() {
  applyTacticalRouteToForm();
  closeFullscreenMapModal();
  if (typeof window.ensureBodyScrollUnlocked === 'function') {
    window.ensureBodyScrollUnlocked();
  }
}

// ── Backward Compatibility Wrapper ──
function calculateDistanceAndRadial(autoApply = true) {
  readOriginBaseFromSelect();
  const icon = createOriginDivIcon(tacticalState.originName, tacticalState.originIconUrl, tacticalState.originPointType, tacticalState.originCategory);
  if (tacticalState.inlineLayers.originMarker) {
    tacticalState.inlineLayers.originMarker.setLatLng([tacticalState.originLat, tacticalState.originLng]);
    tacticalState.inlineLayers.originMarker.setIcon(icon);
  }
  if (tacticalState.modalLayers.originMarker) {
    tacticalState.modalLayers.originMarker.setLatLng([tacticalState.originLat, tacticalState.originLng]);
    tacticalState.modalLayers.originMarker.setIcon(icon);
  }
  redrawTacticalRoute();
  recalculateTacticalMetrics(autoApply);
}

function handleOriginBaseChange() {
  calculateDistanceAndRadial(true);
}

function matchTeamToOriginBase(teamName) {
  if (!teamName) return;
  const tn = teamName.toLowerCase();
  const originSelect = document.getElementById('origin_base_select');
  if (!originSelect) return;

  let matchedIndex = -1;
  for (let i = 0; i < originSelect.options.length; i++) {
    const text = originSelect.options[i].textContent.toLowerCase();
    if (tn.includes('kolaka utara') && text.includes('kolaka utara')) { matchedIndex = i; break; }
    if (tn.includes('kolaka') && !tn.includes('utara') && text.includes('kolaka')) { matchedIndex = i; break; }
    if ((tn.includes('bau') || tn.includes('baubau')) && text.includes('bau')) { matchedIndex = i; break; }
    if (tn.includes('wakatobi') && text.includes('wakatobi')) { matchedIndex = i; break; }
    if (tn.includes('konawe utara') && text.includes('konawe utara')) { matchedIndex = i; break; }
    if (tn.includes('muna') && text.includes('muna')) { matchedIndex = i; break; }
    if ((tn.includes('dermaga') || tn.includes('pacitan') || tn.includes('rb 210') || tn.includes('rb 307') || tn.includes('kapal')) && text.includes('dermaga')) { matchedIndex = i; break; }
    if ((tn.includes('kendari') || tn.includes('kansar') || tn.includes('smc')) && (text.includes('kantor sar kendari') || text.includes('kansar'))) { matchedIndex = i; break; }
  }

  if (matchedIndex !== -1) {
    originSelect.selectedIndex = matchedIndex;
    calculateDistanceAndRadial(true);
  }
}

// ── Auto-Convert Distance Value when Unit Changes (NM <-> KM) ──
let currentJarakUnit = (document.getElementById('jarak_unit')?.value || 'NM').toUpperCase();

function handleJarakUnitChange(newUnit) {
  const inputEl = document.getElementById('jarak_angka');
  const unitNormalized = (newUnit || 'NM').toUpperCase();

  if (inputEl && inputEl.value !== '') {
    const val = parseFloat(inputEl.value);
    if (!isNaN(val) && val > 0) {
      if (unitNormalized === 'KM' && currentJarakUnit === 'NM') {
        // NM ke KM: dikalikan 1.852
        const converted = Math.round(val * 1.852 * 10) / 10;
        inputEl.value = converted.toString();
      } else if (unitNormalized === 'NM' && currentJarakUnit === 'KM') {
        // KM ke NM: dibagi 1.852
        const converted = Math.round((val / 1.852) * 10) / 10;
        inputEl.value = converted.toString();
      }
    }
  }
  currentJarakUnit = unitNormalized;
  syncJarakDanRadial();
}
window.handleJarakUnitChange = handleJarakUnitChange;

function syncJarakDanRadial() {
  const numJarak = document.getElementById('jarak_angka')?.value?.trim();
  const unitJarak = document.getElementById('jarak_unit')?.value || 'NM';
  const hiddenJarak = document.getElementById('jarak');
  if (hiddenJarak) {
    hiddenJarak.value = numJarak ? `${numJarak} ${unitJarak}` : '';
  }

  const numRadial = document.getElementById('radial_angka')?.value?.trim();
  const hiddenRadial = document.getElementById('radial');
  if (hiddenRadial) {
    hiddenRadial.value = numRadial ? `${numRadial}°` : '';
  }

  if (typeof updateLiveWa === 'function') {
    updateLiveWa();
  }
}

let pobIsManuallyEdited = false;

// Dynamic Victims
function addKorbanRow() {
  const tbody = document.getElementById('korban-body');
  const count = tbody.querySelectorAll('.korban-row').length;
  const num = count + 1;

  const tr = document.createElement('tr');
  tr.className = 'korban-row';
  tr.innerHTML = `
    <td class="row-num py-3 px-2 text-center font-mono font-bold text-slate-400">${num}</td>
    <td class="py-3 px-3">
      <input type="hidden" name="korban[${count}][id_korban]" value="" />
      <input type="text" name="korban[${count}][nama_korban]" required placeholder="Nama korban..." class="control control-sm w-full font-semibold" />
    </td>
    <td class="py-3 px-3">
      <input type="text" name="korban[${count}][umur]" placeholder="Contoh: 28 Thn" class="control control-sm w-full" />
    </td>
    <td class="py-3 px-3">
      <select name="korban[${count}][jk]" class="control control-sm w-full">
        <option value="Laki-laki">Laki-laki</option>
        <option value="Perempuan">Perempuan</option>
      </select>
    </td>
    <td class="py-3 px-3">
      <select name="korban[${count}][status_korban]" class="control control-sm w-full font-bold">
        <option value="DP" selected>🟡 Dalam Pencarian (DP)</option>
        <option value="S">🟢 Selamat (S)</option>
        <option value="MD">🔴 Meninggal Dunia (MD)</option>
        <option value="H">⚪ Hilang / Nihil (H)</option>
      </select>
    </td>
    <td class="py-3 px-3">
      <input type="text" name="korban[${count}][alamat]" placeholder="Alamat domisili..." class="control control-sm w-full" />
    </td>
    <td class="py-3 px-2 text-center">
      <button type="button" onclick="removeKorbanRow(this)" class="p-1.5 rounded-lg text-red-500 hover:bg-red-500/10 transition cursor-pointer" title="Hapus Korban">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
      </button>
    </td>
  `;
  tbody.appendChild(tr);

  // Update total POB input only if not manually overridden with a higher custom number
  const pobInput = document.getElementById('jumlah_pob');
  if (pobInput) {
    const currentPob = parseInt(pobInput.value, 10) || 0;
    if (!pobIsManuallyEdited || currentPob < num) {
      pobInput.value = Math.max(currentPob, num);
    }
  }

  if (typeof updateWaPreview === 'function') {
    updateWaPreview();
  }
}

function removeKorbanRow(btn) {
  const tbody = document.getElementById('korban-body');
  const rows = tbody.querySelectorAll('.korban-row');
  if (rows.length <= 1) {
    if (typeof showToast === 'function') showToast('Minimal harus ada 1 data korban atau POB', 'warning');
    return;
  }
  btn.closest('.korban-row').remove();
  
  // Re-number
  const remRows = tbody.querySelectorAll('.korban-row');
  remRows.forEach((r, idx) => {
    r.querySelector('.row-num').textContent = idx + 1;
    r.querySelectorAll('input, select').forEach(el => {
      if (el.name) el.name = el.name.replace(/\[\d+\]/, `[${idx}]`);
    });
  });

  const pobInput = document.getElementById('jumlah_pob');
  if (pobInput && !pobIsManuallyEdited) {
    pobInput.value = remRows.length;
  }

  if (typeof updateWaPreview === 'function') {
    updateWaPreview();
  }
}

// Modal Helper Delegation (Unified with app.js central modal engine)
function openModal(id) {
  if (typeof window.openModal === 'function' && window.openModal !== openModal) {
    return window.openModal(id);
  }
  const m = typeof id === 'string' ? document.getElementById(id) : id;
  if (!m) return;
  m.__prevActiveElement = document.activeElement;
  m.classList.remove('hidden');
  m.classList.add('open');
  m.style.display = 'flex';
  document.body.style.overflow = 'hidden';
}

function closeModal(id) {
  if (typeof window.closeModal === 'function' && window.closeModal !== closeModal) {
    return window.closeModal(id);
  }
  const m = typeof id === 'string' ? document.getElementById(id) : id;
  if (!m) return;
  m.classList.remove('open');
  m.classList.add('hidden');
  m.style.display = 'none';

  if (typeof window.ensureBodyScrollUnlocked === 'function') {
    window.ensureBodyScrollUnlocked();
  } else {
    document.body.style.overflow = '';
  }
}
window.openModal = openModal;
window.closeModal = closeModal;

// Pratinjau WA diambil dari server (generator yang sama dengan saat simpan)
let waPreviewSeq = 0;
async function previewWhatsApp() {
  const form = document.getElementById('form-inisiasi-ops');
  const waEl = document.getElementById('wa-preview-text');
  if (!form || !waEl) return;
  // Samakan dengan sinkronisasi saat submit agar pratinjau = naskah yang tersimpan
  if (typeof syncUnsurFields === 'function') syncUnsurFields();
  if (typeof syncAlutFields === 'function') syncAlutFields();
  const seq = ++waPreviewSeq;
  openModal('modal-wa-preview');
  try {
    const text = await window.QrSarUtils.fetchWaPreview(form, 'laporan_awal');
    if (seq === waPreviewSeq) waEl.textContent = text;
  } catch (err) {
    if (seq === waPreviewSeq) waEl.textContent = err.message;
  }
}

function updateWaPreview() {
  const modal = document.getElementById('modal-wa-preview');
  if (modal && !modal.classList.contains('hidden') && modal.style.display !== 'none') {
    previewWhatsApp();
  }
}
window.updateWaPreview = updateWaPreview;
window.updateLiveWa = updateWaPreview;

function copyWaText() {
  const txt = document.getElementById('wa-preview-text')?.textContent || '';
  if (typeof copyToClipboard === 'function') {
    copyToClipboard(txt, 'Naskah Laporan Awal WhatsApp Berhasil Disalin!');
  } else {
    navigator.clipboard.writeText(txt).then(() => {
      if (typeof showToast === 'function') showToast('Naskah WhatsApp Berhasil Disalin!', 'success');
    });
  }
}

// Preset input helper for modal
function setAiDraftInput(text) {
  const el = document.getElementById('ai-draft-input');
  if (el) {
    el.value = text;
    el.focus();
  }
}

// AI Kronologi Modal Handler
function openAiKronologiModal() {
  const current = document.getElementById('kronologi')?.value || '';
  const draftInputEl = document.getElementById('ai-draft-input');
  if (draftInputEl && !draftInputEl.value.trim() && current.trim()) {
    draftInputEl.value = current;
  }
  openModal('modal-ai-kronologi');
}

// Helper to gather rich form context for AI
function getAiKronologiFormContext() {
  const namaOps = document.getElementById('nama_operasi')?.value.trim() || '';
  const lokasi = document.getElementById('lokasi_kejadian')?.value.trim() || '';
  const waktu = document.getElementById('waktu_kejadian')?.value.trim() || '';
  const namaPelapor = document.getElementById('nama_pelapor')?.value.trim() || '';
  const hubPelapor = document.getElementById('hubungan_pelapor')?.value.trim() || '';
  const pelapor = namaPelapor ? (namaPelapor + (hubPelapor ? ' (' + hubPelapor + ')' : '')) : '';
  const waktuLapor = document.getElementById('waktu_lapor')?.value || '';

  // Extract korban list from table if any
  const korbanRows = document.querySelectorAll('.korban-row');
  let korbanArr = [];
  korbanRows.forEach(r => {
    const kName = r.querySelector('input[name*="[nama_korban]"]')?.value.trim();
    const kUmur = r.querySelector('input[name*="[umur]"]')?.value.trim();
    if (kName && kName !== '-') {
      korbanArr.push(`${kName}${kUmur && kUmur !== '-' ? ' (' + kUmur + ' thn)' : ''}`);
    }
  });

  return {
    namaOperasi: namaOps,
    lokasi,
    waktuKejadian: waktu,
    pelapor,
    waktuLapor,
    korbanInfo: korbanArr.length > 0 ? korbanArr.join(', ') : ''
  };
}

// 1-Click Quick AI Draft directly into form #kronologi
async function quickDraftAiKronologi(btn) {
  const kronologiEl = document.getElementById('kronologi');
  const existingKronologi = kronologiEl ? kronologiEl.value.trim() : '';
  const ctx = getAiKronologiFormContext();

  let draftInput = existingKronologi;
  if (!draftInput) {
    if (ctx.namaOperasi || ctx.lokasi) {
      draftInput = `Terjadi musibah sesuai data operasi ${ctx.namaOperasi} di lokasi ${ctx.lokasi}. Korban membutuhkan pertolongan SAR segera.`;
    } else {
      draftInput = 'Korban dilaporkan mengalami musibah saat beraktivitas dan memerlukan pertolongan SAR segera.';
    }
  }

  const originalContent = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-3.5 h-3.5 inline mr-1 text-purple-600 dark:text-purple-300" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Menyusun AI...</span>`;
  }

  if (typeof showToast === 'function') showToast('Menyusun draf kronologi resmi dengan AI Gemini...', 'info');

  const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || window.CSRF_TOKEN || '';

  try {
    const res = await fetch((window.BASE_URL || '') + '/api/ai/draft-kronologi', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({
        csrf_token: csrfToken,
        draftInput,
        ...ctx
      })
    });
    const json = await res.json();
    if (json.success && json.text) {
      if (kronologiEl) {
        kronologiEl.value = json.text;
        kronologiEl.classList.add('ring-4', 'ring-purple-500/50', 'transition-all');
        setTimeout(() => {
          kronologiEl.classList.remove('ring-4', 'ring-purple-500/50');
        }, 2000);
      }
      if (typeof showToast === 'function') showToast('Draf kronologi resmi berhasil disusun oleh AI!', 'success');
    } else {
      if (typeof showToast === 'function') showToast(json.error || 'Gagal memproses draf AI', 'error');
    }
  } catch (err) {
    console.error('quickDraftAiKronologi error:', err);
    if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan AI', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalContent;
    }
  }
}

// Generate narrative inside Modal (Asisten AI Kronologi)
async function generateAiKronologi() {
  const draftInput = document.getElementById('ai-draft-input')?.value.trim() || '';

  if (!draftInput) {
    if (typeof showToast === 'function') showToast('Silakan tuliskan cerita atau laporan mentah terlebih dahulu untuk disusun ulang oleh Asisten AI', 'warning');
    document.getElementById('ai-draft-input')?.focus();
    return;
  }

  const btn = document.getElementById('btn-generate-ai');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-3.5 h-3.5 inline mr-1 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Menyusun Ulang Narasi...</span>`;
  }

  if (typeof showToast === 'function') showToast('Asisten AI sedang menyusun ulang laporan mentah...', 'info');

  const csrfToken = document.querySelector('input[name="csrf_token"]')?.value || window.CSRF_TOKEN || '';

  try {
    const res = await fetch((window.BASE_URL || '') + '/api/ai/refine-kronologi', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken
      },
      body: JSON.stringify({
        csrf_token: csrfToken,
        rawDraft: draftInput,
        mode: 'refine_raw'
      })
    });
    const json = await res.json();
    if (json.success && json.text) {
      document.getElementById('ai-result-output').value = json.text;
      if (typeof showToast === 'function') showToast('Laporan mentah berhasil disusun ulang menjadi narasi resmi!', 'success');
    } else {
      if (typeof showToast === 'function') showToast(json.error || 'Gagal menyusun narasi AI', 'error');
    }
  } catch (err) {
    console.error('generateAiKronologi error:', err);
    if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan AI', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

function applyAiKronologi() {
  const result = document.getElementById('ai-result-output')?.value.trim() || '';
  if (!result) {
    if (typeof showToast === 'function') showToast('Belum ada narasi yang dihasilkan', 'warning');
    return;
  }
  const kronologiEl = document.getElementById('kronologi');
  if (kronologiEl) {
    kronologiEl.value = result;
    kronologiEl.classList.add('ring-4', 'ring-purple-500/50', 'transition-all');
    setTimeout(() => {
      kronologiEl.classList.remove('ring-4', 'ring-purple-500/50');
    }, 2000);
  }
  closeModal('modal-ai-kronologi');
  if (typeof showToast === 'function') showToast('Narasi resmi diterapkan ke kronologi form!', 'success');
}

// ── LocalStorage Draft & Auto-Recovery ──
const DRAFT_KEY = 'qrsar_form_draft_inisiasi';

function checkLocalStorageDraft() {
  if (isOpsEditMode) return;
  try {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    const draft = JSON.parse(raw);
    if (draft && draft.nama_operasi && draft.timestamp && (Date.now() - draft.timestamp) < 86400000 * 3) {
      const banner = document.getElementById('draft-restore-alert');
      if (banner) banner.classList.remove('hidden');
    }
  } catch (e) {}
}

function saveLocalStorageDraft() {
  if (isOpsEditMode) return;
  try {
    const form = document.getElementById('form-inisiasi-ops');
    if (!form) return;
    const data = {
      timestamp: Date.now(),
      nama_operasi: document.getElementById('nama_operasi')?.value || '',
      nama_pelapor: document.getElementById('nama_pelapor')?.value || '',
      hubungan_pelapor: document.getElementById('hubungan_pelapor')?.value || '',
      no_telp_pelapor: document.getElementById('no_telp_pelapor')?.value || '',
      lokasi_kejadian: document.getElementById('lokasi_kejadian')?.value || '',
      waktu_lapor: document.getElementById('waktu_lapor')?.value || '',
      waktu_kejadian: document.getElementById('waktu_kejadian')?.value || '',
      waktu_berangkat: document.getElementById('waktu_berangkat')?.value || '',
      eta_tiba: document.getElementById('eta_tiba')?.value || '',
      eta_lokasi: document.getElementById('eta_lokasi')?.value || '',
      tim_berangkat: document.getElementById('tim_berangkat')?.value || '',
      kronologi: document.getElementById('kronologi')?.value || '',
      faktor_penghambat: document.getElementById('faktor_penghambat')?.value || '',
      cuaca: document.getElementById('cuaca')?.value || '',
      gelombang: document.getElementById('gelombang')?.value || '',
      angin: document.getElementById('angin')?.value || '',
      jumlah_pob: document.getElementById('jumlah_pob')?.value || '',
      lat_deg: document.getElementById('dms_lat_deg')?.value || '',
      lat_min: document.getElementById('dms_lat_min')?.value || '',
      lat_sec: document.getElementById('dms_lat_sec')?.value || '',
      lat_dir: document.getElementById('dms_lat_dir')?.value || 'S',
      lng_deg: document.getElementById('dms_lng_deg')?.value || '',
      lng_min: document.getElementById('dms_lng_min')?.value || '',
      lng_sec: document.getElementById('dms_lng_sec')?.value || '',
      lng_dir: document.getElementById('dms_lng_dir')?.value || 'E',
      jarak_angka: document.getElementById('jarak_angka')?.value || '',
      jarak_unit: document.getElementById('jarak_unit')?.value || 'NM',
      radial_angka: document.getElementById('radial_angka')?.value || '',
      origin_base_id: document.getElementById('origin_base_select')?.value || '',
      alutList: currentAlutList,
      unsurList: currentUnsurList,
      manualWaypoints: tacticalState.manualWaypoints,
      routingMode: tacticalState.routingMode,
      vesselSpeedKnots: tacticalState.vesselSpeedKnots
    };

    if (data.nama_operasi || data.nama_pelapor || data.lokasi_kejadian) {
      localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
    }
  } catch (e) {}
}

function restoreLocalStorageDraft() {
  try {
    const raw = localStorage.getItem(DRAFT_KEY);
    if (!raw) return;
    const d = JSON.parse(raw);
    if (!d) return;

    if (d.nama_operasi) document.getElementById('nama_operasi').value = d.nama_operasi;
    if (d.nama_pelapor) document.getElementById('nama_pelapor').value = d.nama_pelapor;
    if (d.hubungan_pelapor) document.getElementById('hubungan_pelapor').value = d.hubungan_pelapor;
    if (d.no_telp_pelapor) document.getElementById('no_telp_pelapor').value = d.no_telp_pelapor;
    if (d.lokasi_kejadian) document.getElementById('lokasi_kejadian').value = d.lokasi_kejadian;
    if (d.waktu_lapor) document.getElementById('waktu_lapor').value = d.waktu_lapor;
    if (d.waktu_kejadian) document.getElementById('waktu_kejadian').value = d.waktu_kejadian;
    if (d.waktu_berangkat) document.getElementById('waktu_berangkat').value = d.waktu_berangkat;
    if (d.eta_tiba) document.getElementById('eta_tiba').value = d.eta_tiba;
    if (d.eta_lokasi) document.getElementById('eta_lokasi').value = d.eta_lokasi;
    if (d.tim_berangkat) document.getElementById('tim_berangkat').value = d.tim_berangkat;
    if (d.kronologi) document.getElementById('kronologi').value = d.kronologi;
    if (d.faktor_penghambat) document.getElementById('faktor_penghambat').value = d.faktor_penghambat;
    if (d.cuaca) document.getElementById('cuaca').value = d.cuaca;
    if (d.gelombang) document.getElementById('gelombang').value = d.gelombang;
    if (d.angin) document.getElementById('angin').value = d.angin;
    if (d.jumlah_pob) document.getElementById('jumlah_pob').value = d.jumlah_pob;

    if (d.lat_deg) document.getElementById('dms_lat_deg').value = d.lat_deg;
    if (d.lat_min) document.getElementById('dms_lat_min').value = d.lat_min;
    if (d.lat_sec) document.getElementById('dms_lat_sec').value = d.lat_sec;
    if (d.lat_dir) document.getElementById('dms_lat_dir').value = d.lat_dir;
    if (d.lng_deg) document.getElementById('dms_lng_deg').value = d.lng_deg;
    if (d.lng_min) document.getElementById('dms_lng_min').value = d.lng_min;
    if (d.lng_sec) document.getElementById('dms_lng_sec').value = d.lng_sec;
    if (d.lng_dir) document.getElementById('dms_lng_dir').value = d.lng_dir;

    if (d.jarak_angka) document.getElementById('jarak_angka').value = d.jarak_angka;
    if (d.jarak_unit) document.getElementById('jarak_unit').value = d.jarak_unit;
    if (d.radial_angka) document.getElementById('radial_angka').value = d.radial_angka;

    if (d.origin_base_id && document.getElementById('origin_base_select')) {
      document.getElementById('origin_base_select').value = d.origin_base_id;
    }

    if (Array.isArray(d.alutList) && d.alutList.length > 0) {
      currentAlutList = d.alutList;
      renderAlutList();
    }
    if (Array.isArray(d.unsurList) && d.unsurList.length > 0) {
      currentUnsurList = d.unsurList;
      renderUnsurList();
    }

    if (Array.isArray(d.manualWaypoints)) {
      tacticalState.manualWaypoints = d.manualWaypoints;
    }
    if (d.routingMode) {
      tacticalState.routingMode = d.routingMode;
      setRoutingMode(d.routingMode, false);
    }
    if (d.vesselSpeedKnots) {
      tacticalState.vesselSpeedKnots = parseFloat(d.vesselSpeedKnots) || 25.0;
      const speedInput = document.getElementById('input_vessel_speed_num');
      if (speedInput) speedInput.value = tacticalState.vesselSpeedKnots;
    }

    updateDmsCoordinate();
    syncJarakDanRadial();
    calculateDistanceAndRadial(false);
    redrawTacticalRoute();
    recalculateTacticalMetrics(false);

    const banner = document.getElementById('draft-restore-alert');
    if (banner) banner.classList.add('hidden');

    if (typeof showToast === 'function') {
      showToast('Draf formulir berhasil dipulihkan!', 'success');
    }
  } catch (e) {
    console.error('restoreLocalStorageDraft error:', e);
  }
}

function discardLocalStorageDraft() {
  localStorage.removeItem(DRAFT_KEY);
  const banner = document.getElementById('draft-restore-alert');
  if (banner) banner.classList.add('hidden');
  if (typeof showToast === 'function') {
    showToast('Draf lokal telah dihapus.', 'info');
  }
}

// ── DOM Initialization ──
document.addEventListener('DOMContentLoaded', function() {
  renderAlutList();
  renderUnsurList();
  bindDmsInputEvents();
  updateDmsCoordinate();
  syncJarakDanRadial();
  initLkpTacticalMap();
  calculateDistanceAndRadial(false);
  checkLocalStorageDraft();

  // Watch for manual POB edits
  const pobInput = document.getElementById('jumlah_pob');
  if (pobInput) {
    pobInput.addEventListener('input', function() {
      pobIsManuallyEdited = true;
      if (typeof updateWaPreview === 'function') updateWaPreview();
    });
    pobInput.addEventListener('change', function() {
      pobIsManuallyEdited = true;
      if (typeof updateWaPreview === 'function') updateWaPreview();
    });
  }

  // Auto-sync waktu_berangkat date when waktu_lapor date is changed
  const waktuLaporEl = document.getElementById('waktu_lapor');
  const waktuBerangkatEl = document.getElementById('waktu_berangkat');
  if (waktuLaporEl && waktuBerangkatEl) {
    waktuLaporEl.addEventListener('change', () => {
      if (waktuLaporEl.value) {
        const newDatePart = waktuLaporEl.value.split('T')[0];
        if (waktuBerangkatEl.value) {
          const bParts = waktuBerangkatEl.value.split('T');
          const bTimePart = bParts[1] || '08:00';
          waktuBerangkatEl.value = `${newDatePart}T${bTimePart}`;
        } else {
          waktuBerangkatEl.value = `${newDatePart}T08:00`;
        }
      }
      updateWaPreview();
    });
  }

  // Watch for date inputs to update live preview
  ['waktu_lapor', 'waktu_berangkat', 'waktu_kejadian', 'eta_tiba'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', () => {
        updateWaPreview();
      });
    }
  });

  // Auto-save form inputs & Pre-Submit Form Guard (ISSUE-LAPAWAL-05)
  const formEl = document.getElementById('form-inisiasi-ops');
  if (formEl) {
    formEl.addEventListener('input', saveLocalStorageDraft);
    formEl.addEventListener('change', saveLocalStorageDraft);
    formEl.addEventListener('submit', function() {
      // 1. Force final synchronization of dynamic tables into hidden JSON inputs
      if (typeof syncUnsurFields === 'function') syncUnsurFields();
      if (typeof syncAlutFields === 'function') syncAlutFields();
      if (typeof updateDmsCoordinate === 'function') updateDmsCoordinate();
      if (typeof syncManualRouteToJson === 'function') syncManualRouteToJson();

      // 2. Validate hidden JSON fields aren't accidentally empty if lists have data
      const unsurJson = document.getElementById('detail_unsur_json');
      if (unsurJson && (!unsurJson.value || unsurJson.value === '[]') && typeof currentUnsurList !== 'undefined' && currentUnsurList.length > 0) {
        unsurJson.value = JSON.stringify(currentUnsurList);
      }
      const alutJson = document.getElementById('detail_alut_json');
      if (alutJson && (!alutJson.value || alutJson.value === '[]') && typeof currentAlutList !== 'undefined' && currentAlutList.length > 0) {
        alutJson.value = JSON.stringify(currentAlutList);
      }

      // 3. Clear local draft on submission
      localStorage.removeItem(DRAFT_KEY);
    });
  }
  setInterval(saveLocalStorageDraft, 10000);

  // Keepalive Heartbeat: Ping server every 5 minutes to keep PHP session active
  setInterval(function() {
    fetch((window.BASE_URL || '') + '/api/auth/ping', { method: 'GET' }).catch(() => {});
  }, 300000);
});

// Standardized QRSAR Global Namespace
window.QRSAR = window.QRSAR || {};
window.QRSAR.OperasiForm = {
  renderUnsurList,
  handleSelectTimBerangkat,
  pickMasterTim,
  moveUnsurRow,
  removeUnsurRow,
  updateUnsurItem,
  syncUnsurFields,
  renderAlutList,
  pickMasterAlut,
  quickAddAlutPreset,
  handleAddAlutClick,
  moveAlutRow,
  removeAlutRow,
  updateAlutItem,
  syncAlutFields,
  addKorbanRow,
  removeKorbanRow
};
