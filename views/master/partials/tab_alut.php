<?php
/**
 * QR-SAR - Master Data Tab Partial: Alut & Palsar SAR
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$isAdmin = AuthHelper::isAdmin();
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- Left Column: Add New Alut Form (Admin) or Access Info Card (User) -->
  <div class="card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
    <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
      <span class="p-1.5 rounded-lg text-orange-600 dark:text-orange-400" style="background-color: var(--primary-light); color: var(--primary);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      </span>
      <h2 class="text-sm font-bold text-slate-900 dark:text-white"><?= $isAdmin ? 'Tambah Alut / Palsar Baru' : 'Otoritas Data Alut SAR' ?></h2>
    </div>

    <?php if ($isAdmin): ?>
    <form method="POST" action="<?= BASE_URL ?>/master.php?action=add_alut" class="space-y-4">
      <?= csrfInput() ?>
      <div>
        <div class="field">
          <label class="field-label">Nama Alut / Peralatan SAR <span class="text-red-500">*</span></label>
          <input type="text" name="nama_alut" required placeholder="Contoh: KN SAR Pacitan / Rigid Inflatable Boat (RIB)" class="field-input w-full font-medium">
        </div>
      </div>

      <div>
        <div class="field">
          <label class="field-label">Satuan Ukuran</label>
          <input type="text" name="satuan" value="Unit" placeholder="Unit, Set, Buah, dsb." class="field-input w-full font-medium">
        </div>
      </div>

      <button type="submit" class="w-full py-2.5 text-xs font-bold text-white rounded-xl transition shadow-md flex items-center justify-center gap-2 cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        <span>Simpan ke Master Alut</span>
      </button>
    </form>
    <?php else: ?>
    <div class="space-y-3">
      <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
        Anda saat ini masuk dengan hak akses <span class="inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30 text-[11px]">OPERATOR SAR</span>.
      </p>
      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/80 dark:border-slate-800 text-[11px] text-slate-500 dark:text-slate-400 space-y-2">
        <div class="flex items-center gap-1.5 font-bold text-slate-700 dark:text-slate-200">
          <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
          <span>Mode Baca (Read-Only)</span>
        </div>
        <p>• Seluruh armada Alut/Palsar di samping dapat dipilih langsung pada formulir Operasi SAR, RENOPS, dan LAPBANG.</p>
        <p>• Penambahan armada baru, pengubahan nama/satuan, serta penghapusan master data dipusatkan pada <strong>Administrator Utama</strong>.</p>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right Column: Alut List Table -->
  <div class="lg:col-span-2 card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Master Alut & Palsar</h2>
        <p class="text-[11px] text-slate-400">Total terdata: <?= count($masterAlut ?? []) ?> armada dan peralatan operasi SAR</p>
      </div>
      <div>
        <input type="text" id="filterMasterAlut" value="<?= e($searchQ ?? '') ?>" placeholder="Cari nama alut..." onkeyup="filterTable('filterMasterAlut', 'tableMasterAlut')" class="control control-sm w-48">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs" id="tableMasterAlut">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold text-[11px] uppercase tracking-wider">
            <th class="py-2.5 px-3">#</th>
            <th class="py-2.5 px-3">Nama Alut / Palsar</th>
            <th class="py-2.5 px-3">Satuan</th>
            <?php if ($isAdmin): ?>
            <th class="py-2.5 px-3 text-right">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
          <?php foreach (($masterAlut ?? []) as $idx => $a): ?>
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
              <td class="py-2.5 px-3 text-slate-400 font-mono"><?= $idx + 1 ?></td>
              <td class="py-2.5 px-3 font-bold text-slate-800 dark:text-slate-100">
                <?= e($a['nama_alut']) ?>
              </td>
              <td class="py-2.5 px-3 text-slate-500 dark:text-slate-400">
                <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                  <?= e($a['satuan'] ?? 'Unit') ?>
                </span>
              </td>
              <?php if ($isAdmin): ?>
              <td class="py-2.5 px-3 text-right">
                <div class="inline-flex items-center gap-1 justify-end">
                  <button type="button" onclick="openEditAlutModal(<?= (int)$a['id_alut'] ?>, '<?= e(addslashes($a['nama_alut'])) ?>', '<?= e(addslashes($a['satuan'] ?? 'Unit')) ?>')" class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Edit Alut">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  </button>
                  <form method="POST" action="<?= BASE_URL ?>/master.php?action=delete_alut" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus alut &quot;<?= e($a['nama_alut']) ?>&quot; dari master data?" data-confirm-title="Hapus Master Alut" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                    <?= csrfInput() ?>
                    <input type="hidden" name="id" value="<?= (int)$a['id_alut'] ?>">
                    <button type="submit" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Hapus Alut">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                  </form>
                </div>
              </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($masterAlut)): ?>
            <tr>
              <td colspan="<?= $isAdmin ? 4 : 3 ?>" class="py-8 text-center text-slate-400 italic">Belum ada data Alut SAR.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
