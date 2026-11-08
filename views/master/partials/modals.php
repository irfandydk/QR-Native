<?php
/**
 * QR-SAR - Master Data Partial: Edit Modals & Modal Handlers
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}
?>

<!-- ======================================================== -->
<!-- MODAL: EDIT ALUT / PALSAR                                -->
<!-- ======================================================== -->
<div id="modal-edit-alut" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Edit Master Alut & Palsar</h3>
          <p class="text-[11px] text-slate-400">Perbarui spesifikasi nama armada atau satuan perlengkapan</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-edit-alut')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        ✕
      </button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/master.php?action=edit_alut" class="flex flex-col flex-1 overflow-hidden">
      <?= csrfInput() ?>
      <input type="hidden" name="id_alut" id="edit_id_alut">

      <div class="p-6 space-y-4 overflow-y-auto">
        <div>
          <div class="field">
            <label class="field-label">Nama Alut / Peralatan SAR <span class="text-red-500">*</span></label>
            <input placeholder=" " type="text" name="nama_alut" id="edit_nama_alut" required class="field-input w-full font-medium">
          </div>
        </div>

        <div>
          <div class="field">
            <label class="field-label">Kategori <span class="text-red-500">*</span></label>
            <select name="kategori" id="edit_kategori_alut" required class="field-input w-full font-medium">
              <?php foreach (AlutKategori::LABEL as $kode => $label): ?>
                <option value="<?= e($kode) ?>"><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <div class="field">
            <label class="field-label">Satuan Ukuran</label>
            <input type="text" name="satuan" id="edit_satuan_alut" placeholder="Unit, Set, Buah, dsb." class="field-input w-full font-medium">
          </div>
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
        <button type="button" onclick="closeModal('modal-edit-alut')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-white font-bold text-xs shadow-md transition cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT POTENSI SAR (TIM)                            -->
<!-- ======================================================== -->
<div id="modal-edit-tim" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Edit Tim / Potensi SAR</h3>
          <p class="text-[11px] text-slate-400">Perbarui nama tim penyelamat Basarnas atau instansi potensi SAR gabungan</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-edit-tim')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        ✕
      </button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/master.php?action=edit_tim" class="flex flex-col flex-1 overflow-hidden">
      <?= csrfInput() ?>
      <input type="hidden" name="id_tim" id="edit_id_tim">

      <div class="p-6 space-y-4 overflow-y-auto">
        <div>
          <div class="field">
            <label class="field-label">Nama Tim Penyelamat / Potensi SAR <span class="text-red-500">*</span></label>
            <input placeholder=" " type="text" name="nama_tim" id="edit_nama_tim" required class="field-input w-full font-medium">
          </div>
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
        <button type="button" onclick="closeModal('modal-edit-tim')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-white font-bold text-xs shadow-md transition cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT PEJABAT BERWENANG & SMC                      -->
<!-- ======================================================== -->
<div id="modal-edit-pejabat" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Edit Data Pejabat Penandatangan / SMC</h3>
          <p class="text-[11px] text-slate-400">Perbarui identitas pejabat resmi penandatangan berkas operasional SAR</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-edit-pejabat')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        ✕
      </button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/master.php?action=edit_pejabat" class="flex flex-col flex-1 overflow-hidden">
      <?= csrfInput() ?>
      <input type="hidden" name="id" id="edit_id_pejabat">

      <div class="p-6 space-y-3.5 overflow-y-auto">
        <div>
          <div class="field">
            <label class="field-label">Nama Lengkap Pejabat <span class="text-red-500">*</span></label>
            <input placeholder=" " type="text" name="nama" id="edit_nama_pejabat" required class="field-input w-full font-medium">
          </div>
        </div>

        <div>
          <div class="field">
            <label class="field-label">Jabatan Resmi <span class="text-red-500">*</span></label>
            <input placeholder=" " type="text" name="jabatan" id="edit_jabatan_pejabat" required class="field-input w-full font-medium">
          </div>
        </div>

        <div class="grid grid-cols-2 gap-2.5">
          <div>
            <div class="field">
              <label class="field-label">Pangkat / Golongan</label>
              <input type="text" name="pangkat" id="edit_pangkat_pejabat" placeholder="PEMBINA (IV/a)" class="field-input w-full font-medium">
            </div>
          </div>
          <div>
            <div class="field">
              <label class="field-label">NIP</label>
              <input type="text" name="nip" id="edit_nip_pejabat" placeholder="197405101998031002" class="field-input w-full font-medium">
            </div>
          </div>
        </div>

        <div class="pt-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_default_smc" id="edit_is_default_smc" value="1" class="rounded text-orange-600 focus:ring-orange-500">
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Tetapkan sebagai Default SMC pada dokumen BRA</span>
          </label>
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
        <button type="button" onclick="closeModal('modal-edit-pejabat')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-white font-bold text-xs shadow-md transition cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function filterTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const filter = input.value.toLowerCase();
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = table.getElementsByTagName('tr');

  for (let i = 1; i < rows.length; i++) {
    const text = rows[i].textContent.toLowerCase();
    rows[i].style.display = text.includes(filter) ? '' : 'none';
  }
}

// Open Edit Alut Modal
function openEditAlutModal(id, nama, satuan, kategori) {
  document.getElementById('edit_id_alut').value = id;
  document.getElementById('edit_nama_alut').value = nama;
  document.getElementById('edit_satuan_alut').value = satuan || 'Unit';
  document.getElementById('edit_kategori_alut').value = kategori || 'PALSAR';
  openModal('modal-edit-alut');
}

// Open Edit Tim Modal
function openEditTimModal(id, nama) {
  document.getElementById('edit_id_tim').value = id;
  document.getElementById('edit_nama_tim').value = nama;
  openModal('modal-edit-tim');
}

// Open Edit Pejabat Modal
function openEditPejabatModal(id, nama, jabatan, pangkat, nip, isDefaultSmc) {
  document.getElementById('edit_id_pejabat').value = id;
  document.getElementById('edit_nama_pejabat').value = nama;
  document.getElementById('edit_jabatan_pejabat').value = jabatan;
  document.getElementById('edit_pangkat_pejabat').value = pangkat || '';
  document.getElementById('edit_nip_pejabat').value = nip || '';
  document.getElementById('edit_is_default_smc').checked = !!isDefaultSmc;
  openModal('modal-edit-pejabat');
}
// Auto-apply filter if pre-filled via search query
document.addEventListener('DOMContentLoaded', function() {
  ['filterMasterAlut', 'filterMasterTim', 'filterMasterPejabat'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el && el.value.trim() !== '') {
      var tableId = id === 'filterMasterAlut' ? 'tableMasterAlut' : (id === 'filterMasterTim' ? 'tableMasterTim' : 'tableMasterPejabat');
      filterTable(id, tableId);
    }
  });
});
</script>
