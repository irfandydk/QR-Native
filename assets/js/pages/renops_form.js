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
const defaultKoordinat = renopsCfg.koordinat || '-';

// Escape HTML utility
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
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
    if (!s.luas_area || s.luas_area === '10 Nm²' || s.luas_area.trim() === '') {
      const matchedPoly = (SAR_GIS_BUNDLE.polygons || []).find((p, pIdx) => {
        if (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase())) return true;
        if (idx === pIdx && (SAR_GIS_BUNDLE.polygons || []).length === currentSrus.length) return true;
        return false;
      });
      if (matchedPoly) {
        const aNum = calculatePolygonAreaNm2(matchedPoly);
        if (aNum > 0) {
          s.luas_area = `${aNum.toFixed(2)} Nm²`;
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
        const spacing = matchedPattern.trackSpacingNm ? Number(matchedPattern.trackSpacingNm).toFixed(2) : '1.00';
        const dist = matchedPattern.totalDistanceNm ? Number(matchedPattern.totalDistanceNm).toFixed(1) : '-';
        s.total_sapuan = isEsar
          ? `${legs} Personel E-SAR (Spasi: ${spacing} Nm, Total: ${dist} Nm)`
          : `${legs} Sapuan (Spasi: ${spacing} Nm, Total: ${dist} Nm)`;
        if (matchedPattern.patternType === 'creeping_line') s.pola_pencarian = 'Creeping Line Search';
        else if (matchedPattern.patternType === 'expanding_square') s.pola_pencarian = 'Expanding Square Search';
        else if (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search') s.pola_pencarian = 'Pola Pencarian E-SAR';
        else if (matchedPattern.patternType === 'track_line') s.pola_pencarian = 'Track Line Search';
      }
    }

    const card = document.createElement('div');
    card.className = 'p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-3';

    const luasVal = (s.luas_area && s.luas_area.trim() !== '' && s.luas_area !== '10 Nm²') ? s.luas_area : (s.luas_area || '');
    const polaVal = s.pola_pencarian || 'Parallel Track Search';
    const sapuanVal = s.total_sapuan || '';

    card.innerHTML = `
      <div class="flex items-center justify-between pb-2 border-b border-slate-200/80 dark:border-slate-800">
        <div class="flex flex-wrap items-center gap-2">
          <span class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-blue-500/15 text-blue-600 dark:text-blue-400">
            SRU ${idx + 1}
          </span>
          <input type="text" name="srus[${idx}][nama_sru]" value="${escapeHtml(s.nama_sru || '')}" placeholder="Nama SRU (contoh: SRU 1 Kapal RIB)" required class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].nama_sru = this.value">
          <button type="button" onclick="openGisImportModal(${idx})" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400 hover:text-amber-500 bg-amber-500/10 hover:bg-amber-500/20 px-2.5 py-1 rounded-lg border border-amber-500/25 transition cursor-pointer shadow-2xs" title="Ambil data sektor atau pola dari plotting untuk SRU ini">
            <span>🗺️</span>
            <span>Ambil Data Plotting</span>
          </button>
        </div>
        <button type="button" onclick="removeSruRow(${idx})" class="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition cursor-pointer" title="Hapus SRU">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Ringkasan Data Otomatis Taktis (Luas Area, Pola Pencarian & Total Sapuan) -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-xs">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 text-sm">
            📐
          </div>
          <div class="min-w-0">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Luas Area Pencarian</span>
            <span class="font-mono font-bold text-xs ${luasVal ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 italic'}">
              ${luasVal ? `✓ ${escapeHtml(luasVal)}` : 'Belum Terdeteksi (Klik Ambil Data)'}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0 text-sm">
            ⚡
          </div>
          <div class="min-w-0">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Pola Pencarian</span>
            <span class="font-bold text-xs text-orange-600 dark:text-orange-400 truncate block">
              ✓ ${escapeHtml(polaVal)}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 text-sm">
            🔁
          </div>
          <div class="min-w-0">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Sapuan / Lintasan</span>
            <span class="font-mono font-semibold text-[11px] ${sapuanVal ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 italic'} truncate block" title="${escapeHtml(sapuanVal)}">
              ${sapuanVal ? `✓ ${escapeHtml(sapuanVal)}` : 'Otomatis dari Pola SAR'}
            </span>
          </div>
        </div>
      </div>

      <!-- Hidden inputs for automated values -->
      <input type="hidden" name="srus[${idx}][id_sru]" value="${s.id_sru || ''}">
      <input type="hidden" name="srus[${idx}][luas_area]" value="${escapeHtml(s.luas_area || '')}">
      <input type="hidden" name="srus[${idx}][pola_pencarian]" value="${escapeHtml(s.pola_pencarian || 'Parallel Track Search')}">
      <input type="hidden" name="srus[${idx}][total_sapuan]" value="${escapeHtml(s.total_sapuan || '')}">
      <input type="hidden" name="srus[${idx}][track_spacing]" value="${escapeHtml(s.track_spacing || '')}">

      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Koordinat Batas Sektor & Rincian Tugas
          </label>
          <span class="text-[10px] text-slate-400 font-mono">Format Standar Basarnas DMS</span>
        </div>
        <textarea name="srus[${idx}][koordinat_area]" rows="3" placeholder="Koordinat batas sektor / tugas penyisiran (Otomatis diambil dari peta plotting)..." class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono leading-relaxed focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].koordinat_area = this.value">${escapeHtml(s.koordinat_area || '')}</textarea>
      </div>
    `;
    container.appendChild(card);
  });
}

function addSruRow() {
  const num = currentSrus.length + 1;
  currentSrus.push({
    nama_sru: `SRU ${num}`,
    koordinat_area: '',
    luas_area: '',
    pola_pencarian: 'Parallel Track Search',
    total_sapuan: ''
  });
  renderSruList();
}

function removeSruRow(idx) {
  currentSrus.splice(idx, 1);
  renderSruList();
}

// ── Unsur SAR Terlibat Editor ──
function renderUnsurList() {
  const container = document.getElementById('unsur-items-container');
  if (!container) return;
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
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-purple-500/15 text-purple-600 dark:text-purple-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_unsur || '')}" onchange="updateUnsurItem(${idx}, 'nama_unsur', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="flex items-center gap-1">
            <input type="number" min="1" max="999" value="${jml}" onchange="updateUnsurItem(${idx}, 'jumlah_personil', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-purple-600 dark:text-purple-400 focus:outline-none focus:border-orange-500" />
            <span class="text-xs font-medium text-slate-400">Org</span>
          </div>
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

// ── Alut & Palsar Terlibat Editor ──
function renderAlutList() {
  const container = document.getElementById('alut-items-container');
  if (!container) return;
  container.innerHTML = '';

  let totalAlut = 0;

  if (currentAlutList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada armada alut / palsar yang ditambahkan. Gunakan dropdown Master Data atau input manual di atas.
      </div>
    `;
  } else {
    currentAlutList.forEach((item, idx) => {
      const jml = parseInt(item.jumlah_alut, 10) || 1;
      totalAlut += jml;

      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_alut || '')}" onchange="updateAlutItem(${idx}, 'nama_alut', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${jml}" onchange="updateAlutItem(${idx}, 'jumlah_alut', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 focus:outline-none focus:border-orange-500" />
          <select onchange="updateAlutItem(${idx}, 'satuan', this.value)" class="px-1.5 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 focus:outline-none">
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
    satuan: sat
  });

  namaInput.value = '';
  jmlInput.value = '1';
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
  const textEl = document.getElementById('alut_text');
  if (jsonEl) jsonEl.value = JSON.stringify(currentAlutList);
  if (textEl) {
    textEl.value = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
  }
}

// ── WhatsApp Preview Modal ──
function cleanSruCoordinates(koor) {
  if (!koor) return '';
  return koor
    .split('\n')
    .filter(line => {
      const trim = line.trim();
      return !/^(pola\s*pencarian\s*:|total\s*sapuan\s*:|\[pola\s*sar|tipe\s*:)/i.test(trim);
    })
    .join('\n')
    .trim();
}

function previewRenopsWa() {
  const hariKe = document.getElementById('renops_hari_ke')?.value || 'H.1';
  const tgl = document.getElementById('renops_tanggal_laporan')?.value || '';
  const briefing = document.getElementById('renops_waktu_briefing')?.value || '';
  const mulai = document.getElementById('renops_waktu_mulai_ops')?.value || '';
  const cuaca = document.getElementById('renops_cuaca')?.value || 'Cerah Berawan';

  const tglStr = window.formatTanggalIndo ? window.formatTanggalIndo(tgl) : tgl;
  const namaOps = renopsCfg.namaOperasi || '';

  function formatTw(dtStr) {
    if (!dtStr) return 'TW 0000 0000 H';
    const d = new Date(dtStr);
    if (isNaN(d.getTime())) return 'TW 0000 0000 H';
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `TW ${mm}${dd} ${hh}${min} H`;
  }

  let sruList = [];
  if (currentSrus.length === 0) {
    sruList.push(`*SRU 1 Tim Rescue Kansar Kendari* melakukan penyisiran dgn koordinat :\nA: ${defaultKoordinat}\nLuas area pencarian : 10 Nm²\nPola pencarian : Parallel Track Search`);
  } else {
    currentSrus.forEach((s) => {
      const rawName = (s.nama_sru || 'SRU').replace(/^\*+|\*+$/g, '').trim();
      const cleanName = rawName || 'SRU 1';
      const nameFormatted = `*${cleanName}* melakukan penyisiran dgn koordinat :`;

      const koor = cleanSruCoordinates(s.koordinat_area) || defaultKoordinat;
      const pola = s.pola_pencarian || 'Parallel Track Search';

      const lines = [nameFormatted, koor];
      if (s.luas_area && s.luas_area.trim()) {
        const luasVal = s.luas_area.trim();
        if (!/^luas\s*area/i.test(luasVal)) {
          lines.push(`Luas area pencarian : ${luasVal}`);
        } else {
          lines.push(luasVal);
        }
      }
      lines.push(`Pola pencarian : ${pola}`);
      if (s.total_sapuan && s.total_sapuan.trim()) {
        const sapuanVal = s.total_sapuan.trim();
        if (!/^total\s*sapuan\s*:/i.test(sapuanVal)) {
          lines.push(`Total sapuan : ${sapuanVal}`);
        } else {
          lines.push(sapuanVal);
        }
      }
      sruList.push(lines.join('\n'));
    });
  }
  const sruOutput = sruList.join('\n\n');
  const sruCount = currentSrus.length || 1;

  const unsurText = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Orang`).join('\n') || '• Tim Rescue Kansar Kendari : 7 Orang';
  const alutText = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n') || '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat';

  const header = `FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
RENCANA OPS SAR ${hariKe.toUpperCase()}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
${tglStr}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan Rencana Ops ${namaOps}.
__________________________________________________`;

  const footer = `Demikian kami laporkan, mohon petunjuk dan arahan

Ttd

KKS Kendari
===========================
🏢 Kantor Pencarian Dan Pertolongan Kendari
📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116
☎️ Telp. : (0401) 3196557
📠 Fax. : (0401) 3196558
📱 WhatsApp : +62811-4034-115
🚨 Emergency Call : 115
🌐 Website : Www.Kendari.Basarnas.go.id
📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id
📲 Medsos : @Basarnaskendari`;

  const preview = `${header}

Rencana Ops ${hariKe.toUpperCase()} :

• PD ${formatTw(briefing)} Tim SAR Gabungan Melaksanakan Brifing Operasi.
• Pada ${formatTw(mulai)} Tim SAR Gabungan Melanjutkan Operasi Pencarian ${hariKe.toUpperCase()} Sesuai Rencana Operasi ${hariKe.toUpperCase()} dengan Tim SAR gabungan menuju ke entri point masing-masing.
Dengan Tim dibagi ${sruCount} SRU dengan area pencarian sbb :

${sruOutput}

1. Unsur SAR Terlibat :
${unsurText}

2. Alut dan Palsar :
${alutText}

3. Kondisi Cuaca :
• Cuaca: ${cuaca}
(Sumber BMKG)

${footer}`;

  document.getElementById('renops-wa-preview-content').textContent = preview;
  openModal('modal-preview-renops-wa');
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
  const el = document.getElementById(id);
  if (el) el.classList.remove('hidden');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
}

// ── GIS Plotting Import into RENOPS ──
// SAR_GIS_BUNDLE initialized from renopsCfg.gisBundle
let targetedSruIdxForImport = null;
let activeGisImportTab = 'polygons';
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

      // Re-render modal content
      renderGisImportModalContent();

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
  const abs = Math.abs(val);
  let deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  let min = Math.floor(minFloat);
  let sec = Number(((minFloat - min) * 60).toFixed(1));
  if (sec >= 60.0) {
    sec = 0.0;
    min += 1;
    if (min >= 60) {
      min = 0;
      deg += 1;
    }
  }
  const dir = isLat ? (val >= 0 ? 'U' : 'S') : (val >= 0 ? 'T' : 'B');
  const degPad = isLat ? String(deg).padStart(2, '0') : String(deg).padStart(3, '0');
  const secStr = sec.toFixed(1).padStart(4, '0');
  return `${degPad}° ${String(min).padStart(2, '0')}' ${secStr}" ${dir}`;
}

function formatDmsPair(lat, lng) {
  return `${formatDmsCoordPart(lat, true)} - ${formatDmsCoordPart(lng, false)}`;
}

function calculatePolygonAreaNm2(poly) {
  if (!poly) return 0;
  if (poly.areaNm2 && Number(poly.areaNm2) > 0) {
    return Number(poly.areaNm2);
  }
  if (poly.shapeType === 'circle' && poly.radiusNm) {
    return Math.round(Math.PI * Math.pow(Number(poly.radiusNm), 2) * 100) / 100;
  }
  if (poly.shapeType === 'box' && poly.boxWidthNm && poly.boxHeightNm) {
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
  const pts = poly.points || [];
  if (!Array.isArray(pts) || pts.length === 0) return '';
  return pts.map((pt, idx) => {
    const lat = pt.lat !== undefined ? pt.lat : (Array.isArray(pt) ? pt[0] : 0);
    const lng = pt.lng !== undefined ? pt.lng : (Array.isArray(pt) ? pt[1] : 0);
    const lbl = pt.label || `Titik ${String.fromCharCode(65 + idx)}`;
    return `${lbl}: ${formatDmsPair(lat, lng)}`;
  }).join('\n');
}

function copyGisDmsText(encodedText, label) {
  const text = decodeURIComponent(encodedText);
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      if (typeof showToast === 'function') showToast(`Koordinat ${label} berhasil disalin!`, 'success');
    });
  } else {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    if (typeof showToast === 'function') showToast(`Koordinat ${label} berhasil disalin!`, 'success');
  }
}

function openGisImportModal(targetSruIdx = null) {
  targetedSruIdxForImport = (typeof targetSruIdx === 'number' && targetSruIdx >= 0) ? targetSruIdx : null;
  renderGisImportModalContent();
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
      const area = areaNum > 0 ? areaNum.toFixed(2) : '-';
      const pts = p.points || [];
      const coordsPreview = getPolygonCoordsText(p);
      const selId = `sel-poly-target-${p.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${p.strokeColor || '#2563eb'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(p.name || 'Sektor Pencarian')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map / Wilayah Kerja' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Luas: <span class="text-blue-600 dark:text-blue-400">${area} Nm²</span> &bull; ${pts.length} Titik Sudut
            </div>
          </div>
          <div class="text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 whitespace-pre-line max-h-24 overflow-y-auto leading-relaxed select-all">
${coordsPreview || '(Koordinat sudut tidak tersedia)'}
          </div>
          <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <button type="button" onclick="copyGisDmsText(\`${encodeURIComponent(coordsPreview)}\`, '${escapeHtml(p.name)}')" class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 transition cursor-pointer">
              📋 Salin Koordinat
            </button>
            <div class="flex items-center gap-2">
              <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                ${getSruOptionsHtml()}
              </select>
              <button type="button" onclick="handleImportPolyClick('${p.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition cursor-pointer">
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
        'expanding_square': 'Expanding Square Search',
        'e_sar': 'Pola Pencarian E-SAR',
        'sector_search': 'Pola Pencarian E-SAR',
        'track_line': 'Track Line Search'
      };
      const typeLabel = typeLabels[p.patternType] || p.patternType || 'Parallel Track Search';
      const selId = `sel-pattern-target-${p.id}`;
      const waypoints = p.waypoints || [];
      const dist = p.totalDistanceNm ? Number(p.totalDistanceNm).toFixed(1) : '-';
      const spacing = p.trackSpacingNm ? Number(p.trackSpacingNm).toFixed(2) : '-';
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${p.color || '#ea580c'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(p.name || 'Pola Lintasan SAR')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                ${escapeHtml(typeLabel)}
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Spacing: <span class="text-orange-600 dark:text-orange-400">${spacing} Nm</span> &bull; Jarak: <span>${dist} Nm</span>
            </div>
          </div>
          <div class="text-[11px] text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
            <span>Sektor Asal: <strong>${escapeHtml(p.polygonName || '-')}</strong></span>
            <span>Jumlah Waypoint: <strong>${waypoints.length} titik</strong></span>
            <span>Lintasan/Sapu: <strong>${p.legsCount || '-'}</strong></span>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
            <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
              ${getSruOptionsHtml()}
            </select>
            <button type="button" onclick="handleImportPatternClick('${p.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition cursor-pointer">
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
      const dist = r.totalDistanceNm ? Number(r.totalDistanceNm).toFixed(1) : '-';
      const speed = r.vesselSpeedKnots ? Number(r.vesselSpeedKnots).toFixed(0) : '25';
      const pts = r.points || [];
      const selId = `sel-route-target-${r.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${r.strokeColor || '#059669'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(r.name || 'Jalur Operasi')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Panjang: <span class="text-emerald-600 dark:text-emerald-400">${dist} Nm</span> &bull; Kecepatan: <span>${speed} Knots</span>
            </div>
          </div>
          <div class="text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <span>Jumlah Waypoint: <strong>${pts.length} titik koordinat</strong></span>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
            <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
              ${getSruOptionsHtml()}
            </select>
            <button type="button" onclick="handleImportRouteClick('${r.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition cursor-pointer">
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
    container.innerHTML = points.map(pt => {
      const isBaseMap = !pt.id_operasi;
      const dms = formatDmsPair(Number(pt.lat), Number(pt.lng));
      const selId = `sel-pt-target-${pt.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="text-base">📍</span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(pt.label || 'Titik Taktis')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                ${escapeHtml(pt.category || 'Taktis')}
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              ${dms}
            </div>
          </div>
          ${pt.description ? `<p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">${escapeHtml(pt.description)}</p>` : ''}
          <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <button type="button" onclick="copyGisDmsText(\`${encodeURIComponent(dms)}\`, '${escapeHtml(pt.label)}')" class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 transition cursor-pointer">
              📋 Salin DMS
            </button>
            <div class="flex items-center gap-2">
              <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                ${getSruOptionsHtml()}
              </select>
              <button type="button" onclick="handleImportPointClick('${pt.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-purple-600 hover:bg-purple-500 text-white shadow-xs transition cursor-pointer">
                Sisipkan ke SRU
              </button>
            </div>
          </div>
        </div>
      `;
    }).join('');
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
  if (!matchedPattern) return { pola: 'Parallel Track Search', totalSapuan: '', spacing: '', detailText: '' };
  
  let pola = 'Parallel Track Search';
  const pt = matchedPattern.patternType;
  if (pt === 'creeping_line') pola = 'Creeping Line Search';
  else if (pt === 'expanding_square') pola = 'Expanding Square Search';
  else if (pt === 'e_sar' || pt === 'sector_search') pola = 'Pola Pencarian E-SAR';
  else if (pt === 'track_line') pola = 'Track Line Search';
  else pola = 'Parallel Track Search';

  const isEsar = (pt === 'e_sar' || pt === 'sector_search');
  const legs = matchedPattern.legsCount || (matchedPattern.waypoints ? Math.max(1, Math.floor(matchedPattern.waypoints.length / 2)) : 6);
  const spacing = matchedPattern.trackSpacingNm ? Number(matchedPattern.trackSpacingNm).toFixed(2) : '1.00';
  const dist = matchedPattern.totalDistanceNm ? Number(matchedPattern.totalDistanceNm).toFixed(1) : '-';

  const totalSapuan = isEsar
    ? `${legs} Personel E-SAR (Spasi: ${spacing} Nm, Total: ${dist} Nm)`
    : `${legs} Sapuan (Spasi: ${spacing} Nm, Total: ${dist} Nm)`;

  const detailText = '';

  return { pola, totalSapuan, spacing, detailText };
}

function importAllPolygonsToSrus() {
  const polys = SAR_GIS_BUNDLE.polygons || [];
  if (polys.length === 0) {
    if (typeof showToast === 'function') showToast('Tidak ada Search Area (Poligon) yang tersimpan di peta plotting.', 'info');
    return;
  }
  
  if (currentSrus.length > 0 && !confirm(`Ditemukan ${polys.length} Search Area di peta plotting. Apakah Anda ingin mengonversi seluruh Search Area ini menjadi unit SRU RENOPS?`)) {
    return;
  }
  
  currentSrus = polys.map((poly, idx) => {
    const sruNum = idx + 1;
    const name = poly.name || `Sektor ${String.fromCharCode(65 + idx)}`;
    const areaNum = calculatePolygonAreaNm2(poly);
    const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (poly.areaNm2 ? `${Number(poly.areaNm2).toFixed(2)} Nm²` : '');
    const coordsText = getPolygonCoordsText(poly);
    
    const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => p.polygonId === poly.id || (p.polygonName && poly.name && p.polygonName === poly.name));
    const patInfo = getPatternDetails(matchedPattern);

    const taskText = `Sektor: ${name}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '');

    return {
      nama_sru: `SRU ${sruNum} - ${name}`,
      koordinat_area: taskText,
      luas_area: area,
      pola_pencarian: patInfo.pola,
      total_sapuan: patInfo.totalSapuan
    };
  });
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Berhasil men-generate ${currentSrus.length} unit SRU dari Search Area plotting!`, 'success');
  }
}

function importPolygonToSru(polyId, targetIdx) {
  const poly = (SAR_GIS_BUNDLE.polygons || []).find(p => String(p.id) === String(polyId));
  if (!poly) return;
  if (!currentSrus[targetIdx]) {
    currentSrus[targetIdx] = { nama_sru: `SRU ${targetIdx + 1}`, koordinat_area: '', luas_area: '', pola_pencarian: 'Parallel Track Search', total_sapuan: '' };
  }
  
  const name = poly.name || 'Sektor Pencarian';
  const areaNum = calculatePolygonAreaNm2(poly);
  const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (poly.areaNm2 ? `${Number(poly.areaNm2).toFixed(2)} Nm²` : '');
  const coordsText = getPolygonCoordsText(poly);
  
  const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => p.polygonId === poly.id || (p.polygonName && poly.name && p.polygonName === poly.name));
  const patInfo = getPatternDetails(matchedPattern);

  currentSrus[targetIdx].nama_sru = `SRU ${targetIdx + 1} - ${name}`;
  currentSrus[targetIdx].luas_area = area;
  currentSrus[targetIdx].pola_pencarian = patInfo.pola;
  currentSrus[targetIdx].total_sapuan = patInfo.totalSapuan;
  currentSrus[targetIdx].koordinat_area = `Sektor: ${name}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '');
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Data sektor "${name}" berhasil diterapkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

function importPolygonToNewSru(polyId) {
  const poly = (SAR_GIS_BUNDLE.polygons || []).find(p => String(p.id) === String(polyId));
  if (!poly) return;
  const num = currentSrus.length + 1;
  const name = poly.name || `Sektor ${String.fromCharCode(64 + num)}`;
  const areaNum = calculatePolygonAreaNm2(poly);
  const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (poly.areaNm2 ? `${Number(poly.areaNm2).toFixed(2)} Nm²` : '');
  const coordsText = getPolygonCoordsText(poly);
  
  const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => p.polygonId === poly.id || (p.polygonName && poly.name && p.polygonName === poly.name));
  const patInfo = getPatternDetails(matchedPattern);

  currentSrus.push({
    nama_sru: `SRU ${num} - ${name}`,
    koordinat_area: `Sektor: ${name}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : ''),
    luas_area: area,
    pola_pencarian: patInfo.pola,
    total_sapuan: patInfo.totalSapuan
  });
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`SRU baru "${name}" berhasil ditambahkan!`, 'success');
  }
}

function importPatternToSru(patId, targetIdx) {
  const pat = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => String(p.id) === String(patId));
  if (!pat) return;
  if (!currentSrus[targetIdx]) {
    currentSrus[targetIdx] = { nama_sru: `SRU ${targetIdx + 1}`, koordinat_area: '', luas_area: '', pola_pencarian: 'Parallel Track Search', total_sapuan: '' };
  }
  
  const patInfo = getPatternDetails(pat);
  currentSrus[targetIdx].pola_pencarian = patInfo.pola;
  currentSrus[targetIdx].total_sapuan = patInfo.totalSapuan;
  currentSrus[targetIdx].track_spacing = patInfo.spacing;

  const linkedPoly = (SAR_GIS_BUNDLE.polygons || []).find(p => (pat.polygonId && p.id === pat.polygonId) || (pat.polygonName && p.name === pat.polygonName));
  if (linkedPoly) {
    const areaNum = calculatePolygonAreaNm2(linkedPoly);
    const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (linkedPoly.areaNm2 ? `${Number(linkedPoly.areaNm2).toFixed(2)} Nm²` : '');
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
    area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (linkedPoly.areaNm2 ? `${Number(linkedPoly.areaNm2).toFixed(2)} Nm²` : '');
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
  
  const dist = r.totalDistanceNm ? Number(r.totalDistanceNm).toFixed(1) : '-';
  const speed = r.vesselSpeedKnots ? Number(r.vesselSpeedKnots).toFixed(0) : '25';
  const totalSapuan = `1 Lintasan Track Line (Panjang: ${dist} Nm, Kecepatan: ${speed} Knots)`;
  const pts = r.points || [];
  let routeCoordsText = '';
  if (Array.isArray(pts) && pts.length > 0) {
    routeCoordsText = pts.map((pt, idx) => {
      const lbl = pt.label || `Waypoint ${idx + 1}`;
      return `${lbl}: ${formatDmsPair(Number(pt.lat), Number(pt.lng))}`;
    }).join('\n');
  }

  const routeDesc = `Jalur/Rute: ${r.name}\nPanjang Lintasan: ${dist} Nm | Kecepatan Alut: ${speed} Knots` + (routeCoordsText ? `\nKoordinat Lintasan:\n${routeCoordsText}` : '');

  if (targetIdx === -1 || !currentSrus[targetIdx]) {
    const num = currentSrus.length + 1;
    currentSrus.push({
      nama_sru: `SRU ${num} - Jalur ${r.name}`,
      koordinat_area: routeDesc,
      luas_area: `${dist} Nm (Koridor)`,
      pola_pencarian: 'Track Line Search',
      total_sapuan: totalSapuan
    });
    targetIdx = currentSrus.length - 1;
  } else {
    currentSrus[targetIdx].pola_pencarian = 'Track Line Search';
    currentSrus[targetIdx].total_sapuan = totalSapuan;
    if (!currentSrus[targetIdx].luas_area) currentSrus[targetIdx].luas_area = `${dist} Nm (Koridor)`;
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
  const ptDesc = `[Titik ${pt.category || 'Taktis'}: ${pt.label}]\nKoordinat: ${dms}${pt.description ? '\nKeterangan: ' + pt.description : ''}`;
  
  if (targetIdx === -1 || !currentSrus[targetIdx]) {
    const num = currentSrus.length + 1;
    currentSrus.push({
      nama_sru: `SRU ${num} - ${pt.label}`,
      koordinat_area: ptDesc.trim(),
      luas_area: '',
      pola_pencarian: 'Parallel Track Search',
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
  recalculateRenopsHariKe();
  renderSruList();
  renderUnsurList();
  renderAlutList();
  restoreRenopsDraft();
  recalculateRenopsHariKe();
});

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
