// Page Configuration from window.__SITREP_FORM_CONFIG__
const sitrepFormCfg = window.__SITREP_FORM_CONFIG__ || {};
const idOperasi = sitrepFormCfg.idOperasi || 0;
const isSitrepEditMode = !!sitrepFormCfg.editMode;
const namaOps = sitrepFormCfg.namaOperasi || '';
const korbanList = sitrepFormCfg.korbanList || [];
const SITREP_DRAFT_KEY = 'qr_sar_sitrep_draft_' + idOperasi;
const OPERASI_START_DATE = sitrepFormCfg.operasiStartDate || null;
const OPERASI_REOPEN_DATE = sitrepFormCfg.operasiReopenDate || null;
const OPERASI_BASE_HARI_REOPEN = sitrepFormCfg.operasiBaseHariReopen || 0;

let currentUnsurList = sitrepFormCfg.initialUnsurData || [];
let currentAlutList = sitrepFormCfg.initialAlutData || [];

// Escape HTML utility
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── Toggle Perkembangan vs Penutupan ──
function handleJenisLaporanChange(val) {
  const isPenutupan = (val === 'PENUTUPAN');
  const debriefWrap = document.getElementById('wrapper-debrief');
  const lblPerkembangan = document.getElementById('label-jenis-perkembangan');
  const lblPenutupan = document.getElementById('label-jenis-penutupan');
  const btnSubmit = document.getElementById('btn-submit-sitrep');

  if (isPenutupan) {
    if (debriefWrap) debriefWrap.classList.remove('hidden');
    if (lblPenutupan) {
      lblPenutupan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-red-600 text-white border-red-600';
    }
    if (lblPerkembangan) {
      lblPerkembangan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700';
    }
    if (btnSubmit) {
      btnSubmit.classList.remove('bg-blue-600', 'hover:bg-blue-500');
      btnSubmit.classList.add('bg-red-600', 'hover:bg-red-500');
      btnSubmit.querySelector('span:last-child').textContent = 'Terbitkan Laporan Penutupan Operasi';
    }
  } else {
    if (debriefWrap) debriefWrap.classList.add('hidden');
    if (lblPerkembangan) {
      lblPerkembangan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-blue-600 text-white border-blue-600';
    }
    if (lblPenutupan) {
      lblPenutupan.className = 'cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700';
    }
    if (btnSubmit) {
      btnSubmit.classList.remove('bg-red-600', 'hover:bg-red-500');
      btnSubmit.classList.add('bg-blue-600', 'hover:bg-blue-500');
      btnSubmit.querySelector('span:last-child').textContent = 'Terbitkan Laporan Perkembangan (SITREP)';
    }
  }
}

// ── Quick Templates for Day 7 (H.7) Operations Closure / Extension per UU No. 29/2014 ──
function applyH7ClosureTemplate() {
  const radioPenutupan = document.querySelector('input[name="jenis_laporan"][value="PENUTUPAN"]');
  if (radioPenutupan) {
    radioPenutupan.checked = true;
    handleJenisLaporanChange('PENUTUPAN');
  }
  const debriefInput = document.getElementById('sitrep_waktu_debrief');
  if (debriefInput && !debriefInput.value) {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    debriefInput.value = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
  }
  const isiEl = document.getElementById('sitrep_isi_laporan');
  if (isiEl) {
    // namaOps initialized above from sitrepFormCfg
    isiEl.value = `1. Tim SAR Gabungan telah melaksanakan upaya pencarian dan pertolongan maksimal hingga hari ke-7 (H.7) sesuai Rencana Operasi (RENOPS H.7).\n2. Hingga pukul 17.00 WITA, hasil penyisiran lapangan di seluruh sektor pencarian masih NIHIL dan tidak ditemukan tanda-tanda keberadaan korban.\n3. Berdasarkan hasil evaluasi menyeluruh, debriefing bersama seluruh Potensi SAR yang terlibat, serta musyawarah bersama pihak keluarga korban, dengan merujuk pada ketentuan Pasal 34 Undang-Undang No. 29 Tahun 2014 tentang Pencarian dan Pertolongan, maka Operasi SAR ${namaOps} secara resmi diusulkan untuk DITUTUP.\n4. Seluruh unsur Potensi SAR yang terlibat dikembalikan ke kesatuan dan instansi masing-masing dengan ucapan terima kasih dan apresiasi setinggi-tingginya.\n5. Operasi SAR dapat dibuka kembali sewaktu-waktu apabila di kemudian hari ditemukan petunjuk atau tanda-tanda baru mengenai keberadaan korban.`;
    isiEl.focus();
  }
  if (typeof showToast === 'function') {
    showToast('Format naskah resmi Penutupan Operasi SAR H.7 (UU 29/2014) berhasil diterapkan.', 'success');
  }
}

function applyH7ExtensionTemplate() {
  const radioPerkembangan = document.querySelector('input[name="jenis_laporan"][value="PERKEMBANGAN"]');
  if (radioPerkembangan) {
    radioPerkembangan.checked = true;
    handleJenisLaporanChange('PERKEMBANGAN');
  }
  const isiEl = document.getElementById('sitrep_isi_laporan');
  if (isiEl) {
    // namaOps initialized above from sitrepFormCfg
    isiEl.value = `1. Pada pelaksanaan operasi SAR hari ke-7 (H.7), Tim SAR Gabungan memperoleh petunjuk dan laporan informasi mengenai kemungkinan posisi korban di lapangan.\n2. Berdasarkan hasil analisa SMC dan kesepakatan bersama Potensi SAR serta pihak keluarga korban, diputuskan bahwa Operasi SAR ${namaOps} diusulkan untuk DIPERPANJANG selama 3 (tiga) hari ke depan sesuai wewenang Kepala Kantor SAR guna mengoptimalkan pencarian.\n3. Rencana operasi lanjutan (RENOPS H.8) akan difokuskan pada perluasan area pencarian sesuai datum dan perkembangan terkini.`;
    isiEl.focus();
  }
  if (typeof showToast === 'function') {
    showToast('Format naskah Usulan Perpanjangan Operasi H.7 berhasil diterapkan.', 'info');
  }
}

// Auto reveal H.7 banner on user input
document.addEventListener('DOMContentLoaded', function() {
  const hariInput = document.getElementById('sitrep_hari_ke');
  if (hariInput) {
    hariInput.addEventListener('input', function(e) {
      const val = (e.target.value || '').toUpperCase().trim();
      const banner = document.getElementById('h7-advisory-banner');
      if (!banner) return;
      const num = parseInt(val.replace(/\D/g, ''), 10);
      if (val.includes('7') || num >= 7 || val === 'H.7') {
        banner.classList.remove('hidden');
      }
    });
  }
});

// ── Quick AI Refinement & Interactive Assistant for Sitrep ──
async function quickAiRefineSitrep(btn) {
  const isiEl = document.getElementById('sitrep_isi_laporan');
  if (!isiEl) return;
  const rawText = isiEl.value.trim();
  if (!rawText) {
    if (typeof showToast === 'function') showToast('Ketikkan ringkasan hasil lapangan terlebih dahulu untuk dirapikan AI.', 'warning');
    isiEl.focus();
    return;
  }

  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-3.5 h-3.5 inline mr-1 text-purple-600 dark:text-purple-300" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Merapikan...</span>`;
  }

  if (typeof showToast === 'function') showToast('Menyusun naskah laporan resmi dengan AI Gemini...', 'info');

  try {
    const res = await fetch((window.BASE_URL || '') + '/api/index.php?path=/ai/refine-sitrep', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        catatan: rawText,
        rawDraft: rawText,
        hari_ke: document.getElementById('sitrep_hari_ke')?.value || 'H.1',
        hariKe: document.getElementById('sitrep_hari_ke')?.value || 'H.1',
        nama_operasi: namaOps,
        namaOperasi: namaOps
      })
    });
    const json = await res.json();
    if (json.success && json.text) {
      isiEl.value = json.text;
      isiEl.classList.add('ring-4', 'ring-purple-500/50', 'transition-all');
      setTimeout(() => {
        isiEl.classList.remove('ring-4', 'ring-purple-500/50');
      }, 2000);
      if (typeof showToast === 'function') showToast('Laporan berhasil dirapikan sesuai format resmi Basarnas!', 'success');
    } else {
      if (typeof showToast === 'function') showToast(json.error || 'Gagal merapikan teks AI', 'error');
    }
  } catch (e) {
    console.error('AI Error:', e);
    if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan AI', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

function openAiSitrepModal() {
  const current = document.getElementById('sitrep_isi_laporan')?.value || '';
  const draftInputEl = document.getElementById('ai-sitrep-draft-input');
  if (draftInputEl && !draftInputEl.value.trim() && current.trim()) {
    draftInputEl.value = current;
  }
  openModal('modal-ai-sitrep');
}

function setAiSitrepDraftInput(txt) {
  const el = document.getElementById('ai-sitrep-draft-input');
  if (el) {
    el.value = txt;
    el.focus();
  }
}

async function generateAiSitrep() {
  const draftInput = document.getElementById('ai-sitrep-draft-input')?.value.trim() || '';
  if (!draftInput) {
    if (typeof showToast === 'function') showToast('Silakan tuliskan catatan lapangan terlebih dahulu untuk disusun oleh Asisten AI', 'warning');
    document.getElementById('ai-sitrep-draft-input')?.focus();
    return;
  }

  const btn = document.getElementById('btn-generate-ai-sitrep');
  const originalHtml = btn ? btn.innerHTML : '';
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = `<svg class="animate-spin w-3.5 h-3.5 inline mr-1 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Menyusun Narasi SITREP...</span>`;
  }

  if (typeof showToast === 'function') showToast('Asisten AI sedang menyusun narasi resmi SITREP...', 'info');

  try {
    const res = await fetch((window.BASE_URL || '') + '/api/index.php?path=/ai/refine-sitrep', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        catatan: draftInput,
        rawDraft: draftInput,
        hari_ke: document.getElementById('sitrep_hari_ke')?.value || 'H.1',
        hariKe: document.getElementById('sitrep_hari_ke')?.value || 'H.1',
        nama_operasi: namaOps,
        namaOperasi: namaOps
      })
    });
    const json = await res.json();
    if (json.success && json.text) {
      document.getElementById('ai-sitrep-result-output').value = json.text;
      if (typeof showToast === 'function') showToast('Catatan lapangan berhasil disusun menjadi narasi resmi Basarnas!', 'success');
    } else {
      if (typeof showToast === 'function') showToast(json.error || 'Gagal menyusun narasi AI', 'error');
    }
  } catch (err) {
    console.error('generateAiSitrep error:', err);
    if (typeof showToast === 'function') showToast('Terjadi kesalahan jaringan AI', 'error');
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    }
  }
}

function applyAiSitrep() {
  const result = document.getElementById('ai-sitrep-result-output')?.value.trim() || '';
  if (!result) {
    if (typeof showToast === 'function') showToast('Belum ada narasi yang dihasilkan', 'warning');
    return;
  }
  const isiEl = document.getElementById('sitrep_isi_laporan');
  if (isiEl) {
    isiEl.value = result;
    isiEl.classList.add('ring-4', 'ring-purple-500/50', 'transition-all');
    setTimeout(() => {
      isiEl.classList.remove('ring-4', 'ring-purple-500/50');
    }, 2000);
  }
  closeModal('modal-ai-sitrep');
  if (typeof showToast === 'function') showToast('Narasi resmi berhasil diterapkan ke form!', 'success');
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
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-blue-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-purple-500/15 text-purple-600 dark:text-purple-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_unsur || '')}" onchange="updateUnsurItem(${idx}, 'nama_unsur', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-blue-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="flex items-center gap-1">
            <input type="number" min="1" max="999" value="${jml}" onchange="updateUnsurItem(${idx}, 'jumlah_personil', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-purple-600 dark:text-purple-400 focus:outline-none focus:border-blue-500" />
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

  const badgeEl = document.getElementById('sitrep-total-personil');
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
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-blue-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_alut || '')}" onchange="updateAlutItem(${idx}, 'nama_alut', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-blue-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${jml}" onchange="updateAlutItem(${idx}, 'jumlah_alut', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 focus:outline-none focus:border-blue-500" />
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

  const badgeEl = document.getElementById('sitrep-total-alut');
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
function previewSitrepWa() {
  const hariKe = document.getElementById('sitrep_hari_ke')?.value || 'H.1';
  const waktuLap = document.getElementById('sitrep_waktu_laporan')?.value || '';
  const isPenutupan = document.querySelector('input[name="jenis_laporan"]:checked')?.value === 'PENUTUPAN';
  const waktuDebrief = document.getElementById('sitrep_waktu_debrief')?.value || '';
  const cuaca = document.getElementById('sitrep_cuaca')?.value || 'Cerah Berawan';
  const isi = document.getElementById('sitrep_isi_laporan')?.value || '-';

  const tglStr = window.formatTanggalIndo ? window.formatTanggalIndo(waktuLap) : waktuLap;
  // namaOps initialized above from sitrepFormCfg
  // korbanList initialized above from sitrepFormCfg

  const unsurText = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Orang`).join('\n') || '• Tim Rescue Kansar Kendari : 7 Orang';
  const alutText = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n') || '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat';

  let sCount = 0, mdCount = 0, dpCount = 0, hCount = 0;
  const kList = [];
  korbanList.forEach((k, idx) => {
    const rowSelect = document.querySelector(`select[name="update_korban[${k.id_korban}]"]`) || document.querySelector(`select[name="update_korban[${k.id_korban}][status_korban]"]`);
    const st = (rowSelect ? rowSelect.value : (k.status_korban || 'DP')).toUpperCase();
    if (st === 'S') sCount++;
    else if (st === 'MD') mdCount++;
    else if (st === 'DP') dpCount++;
    else if (st === 'H') hCount++;
    else dpCount++;
    const label = st === 'S' ? 'Selamat (S)' : (st === 'MD' ? 'Meninggal Dunia (MD)' : (st === 'H' ? 'Hilang / Nihil (H)' : 'Dalam Pencarian (DP)'));
    kList.push(`${idx + 1}. Nama : ${k.nama_korban || '-'} [Status: ${label}]\n   Umur : ${k.umur || '-'}\n   Jenis Kelamin : ${k.jk || '-'}\n   Alamat : ${k.alamat || '-'}`);
  });
  const identitasText = kList.length > 0 ? kList.join('\n\n') : '1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -';

  let klausulPenutupan = '';
  if (isPenutupan) {
    const tglDeb = window.formatTanggalSimple ? window.formatTanggalSimple(waktuDebrief) : (window.formatTanggalIndo ? window.formatTanggalIndo(waktuDebrief) : waktuDebrief);
    const jamDeb = window.formatJamWita ? window.formatJamWita(waktuDebrief) : waktuDebrief;
    klausulPenutupan = `\n\n• Pada tanggal ${tglDeb} Pukul ${jamDeb} WITA, Tim SAR Gabungan melakukan debriefing penutupan Ops SAR terhadap ${namaOps}, selanjutnya operasi SAR diusulkan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih.`;
  }

  const headerTitle = isPenutupan ? `LAPBANG OPS SAR ${hariKe.toUpperCase()} DAN PENUTUPAN` : `LAPBANG OPS SAR ${hariKe.toUpperCase()}`;

  const header = `FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
${headerTitle}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
${tglStr}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan Perkembangan Ops ${namaOps}.
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

Hasil Ops SAR ${hariKe.toUpperCase()}:

${isi}${klausulPenutupan}

1. Data Korban: ${korbanList.length} Orang
• S : ${sCount} ORANG
• MD : ${mdCount} ORANG
• DP : ${dpCount} ORANG
• H : ${hCount} ORANG

2. Identitas Korban
${identitasText}

3. Unsur SAR Terlibat
${unsurText}

4. Alut dan Palsar
${alutText}

${footer}`;

  document.getElementById('sitrep-wa-preview-content').textContent = preview;
  openModal('modal-preview-sitrep-wa');
}

function copySitrepWa() {
  const content = document.getElementById('sitrep-wa-preview-content')?.textContent || '';
  if (navigator.clipboard) {
    navigator.clipboard.writeText(content).then(() => {
      if (typeof showToast === 'function') showToast('Format WA SITREP berhasil disalin!', 'success');
    });
  } else {
    const ta = document.createElement('textarea');
    ta.value = content;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    if (typeof showToast === 'function') showToast('Format WA SITREP berhasil disalin!', 'success');
  }
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.remove('hidden');
    el.style.display = 'flex';
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) {
    el.classList.add('hidden');
    el.style.display = 'none';
  }
}

// --- AUTO-SAVE DRAFT (LOCAL STORAGE RESILIENCE) ---
// SITREP_DRAFT_KEY and isSitrepEditMode initialized above from sitrepFormCfg

function saveSitrepDraft() {
  if (isSitrepEditMode) return;
  const draft = {
    waktu_laporan: document.getElementById('input-waktu-laporan')?.value,
    waktu_debriefing: document.getElementById('input-waktu-debriefing')?.value,
    isi_laporan: document.getElementById('input-isi-laporan')?.value,
    unsur: currentUnsurList,
    alut: currentAlutList,
    savedAt: new Date().toISOString()
  };
  localStorage.setItem(SITREP_DRAFT_KEY, JSON.stringify(draft));
}

function restoreSitrepDraft() {
  if (isSitrepEditMode) return;
  const raw = localStorage.getItem(SITREP_DRAFT_KEY);
  if (!raw) return;
  try {
    const d = JSON.parse(raw);
    if (!d) return;
    if (d.isi_laporan) document.getElementById('input-isi-laporan').value = d.isi_laporan;
    if (d.waktu_debriefing && document.getElementById('input-waktu-debriefing')) {
      document.getElementById('input-waktu-debriefing').value = d.waktu_debriefing;
    }
    if (Array.isArray(d.unsur) && d.unsur.length > 0) { currentUnsurList = d.unsur; renderUnsurList(); }
    if (Array.isArray(d.alut) && d.alut.length > 0) { currentAlutList = d.alut; renderAlutList(); }
    
    if (typeof showToast === 'function') {
      showToast('Draf SITREP tersimpan berhasil dipulihkan otomatis.', 'info');
    }
  } catch (e) {}
}

function clearSitrepDraft() {
  localStorage.removeItem(SITREP_DRAFT_KEY);
}

let sitrepDraftTimeout = null;
document.addEventListener('input', (e) => {
  if (isSitrepEditMode) return;
  clearTimeout(sitrepDraftTimeout);
  sitrepDraftTimeout = setTimeout(saveSitrepDraft, 1000);
});

document.getElementById('form-sitrep')?.addEventListener('submit', () => {
  recalculateSitrepHariKe();
  clearSitrepDraft();
});

// Real-time Day Recalculation Based on Input Date
// OPERASI_START_DATE, OPERASI_REOPEN_DATE, OPERASI_BASE_HARI_REOPEN initialized above from sitrepFormCfg

function recalculateSitrepHariKe() {
  const dtInput = document.getElementById('sitrep_waktu_laporan')?.value;
  if (!dtInput || !OPERASI_START_DATE) return;
  const tglOnly = dtInput.split('T')[0];
  const t2 = new Date(tglOnly + 'T00:00:00');
  if (isNaN(t2.getTime())) return;

  const inputHari = document.getElementById('sitrep_hari_ke');
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

function updateVictimStatusBadges() {
  const selects = document.querySelectorAll('select[name^="update_korban"]');
  if (selects.length === 0) return;
  let s = 0, md = 0, dp = 0, h = 0;
  selects.forEach(sel => {
    const val = (sel.value || 'DP').toUpperCase();
    if (val === 'S') s++;
    else if (val === 'MD') md++;
    else if (val === 'DP') dp++;
    else if (val === 'H') h++;
    else dp++;
  });
  const badgeContainer = document.getElementById('victim-status-badges');
  if (badgeContainer) {
    badgeContainer.innerHTML = `
      <span class="px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">🟢 Selamat: ${s}</span>
      <span class="px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">🔴 MD: ${md}</span>
      <span class="px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">🟡 DP: ${dp}</span>
      ${h > 0 ? `<span class="px-2 py-0.5 rounded-full bg-slate-500/15 text-slate-600 dark:text-slate-400 border border-slate-500/30">⚪ Hilang: ${h}</span>` : ''}
    `;
  }
}

function initVictimStatusListeners() {
  const selects = document.querySelectorAll('select[name^="update_korban"]');
  selects.forEach(sel => {
    sel.addEventListener('change', () => {
      updateVictimStatusBadges();
      const previewModal = document.getElementById('modal-preview-wa-sitrep');
      if (previewModal && !previewModal.classList.contains('hidden')) {
        previewSitrepWA();
      }
    });
  });
  updateVictimStatusBadges();
}

document.getElementById('sitrep_waktu_laporan')?.addEventListener('input', recalculateSitrepHariKe);
document.getElementById('sitrep_waktu_laporan')?.addEventListener('change', recalculateSitrepHariKe);

document.addEventListener('DOMContentLoaded', () => {
  recalculateSitrepHariKe();
  renderUnsurList();
  renderAlutList();
  initVictimStatusListeners();
  restoreSitrepDraft();
  recalculateSitrepHariKe();
});
