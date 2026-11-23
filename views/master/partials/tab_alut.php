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
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
  <!-- Left Column: Add New Alut Form (Admin) or Read-Only Info (User) -->
  <section class="bento-card-v1 p-5 rounded-2xl space-y-4">
    <div class="pb-3 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-sm font-bold text-slate-900 dark:text-white"><?= $isAdmin ? 'Tambah Alut / Palsar' : 'Otoritas Data Alut SAR' ?></h2>
    </div>

    <?php if ($isAdmin): ?>
      <form method="POST" action="<?= BASE_URL ?>/master.php?action=add_alut" class="space-y-3.5">
        <?= csrfInput() ?>
        <div class="field">
          <label class="field-label">Nama Alut / Peralatan SAR <span class="text-rose-500">*</span></label>
          <input type="text" name="nama_alut" required placeholder="Contoh: KN SAR Pacitan / RIB 01" class="field-input w-full font-medium">
        </div>

        <div class="field">
          <label class="field-label">Kategori <span class="text-rose-500">*</span></label>
          <select name="kategori" required class="field-input w-full font-medium">
            <?php foreach (AlutKategori::LABEL as $kode => $label): ?>
              <option value="<?= e($kode) ?>"><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label class="field-label">Satuan Ukuran</label>
          <input type="text" name="satuan" value="Unit" placeholder="Unit, Set, Buah" class="field-input w-full font-medium">
        </div>

        <button type="submit" class="mst-accent-btn w-full h-9 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          <span>Simpan ke Master Alut</span>
        </button>
      </form>
    <?php else: ?>
      <div class="space-y-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        <div class="inline-flex items-center px-2 py-0.5 rounded-md font-mono font-semibold text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
          Mode Baca (Operator SAR)
        </div>
        <p>Seluruh armada Alut dan Palsar di tabel samping dapat dipilih langsung pada formulir Operasi SAR, RENOPS, dan LAPBANG.</p>
        <p class="pl-3 border-l-2 border-slate-200 dark:border-slate-700 text-[11px] text-slate-500 dark:text-slate-400">
          Penambahan, perubahan, dan penghapusan master data dikelola oleh <strong class="text-slate-700 dark:text-slate-200">Administrator Utama</strong>.
        </p>
      </div>
    <?php endif; ?>
  </section>

  <!-- Right Column: Alut List Table -->
  <section class="lg:col-span-2 bento-card-v1 rounded-2xl overflow-hidden">
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Alut &amp; Palsar</h2>
        <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
          <?= count($masterAlut ?? []) ?>
        </span>
      </div>
      <div class="relative w-full sm:w-56">
        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="filterMasterAlut" value="<?= e($searchQ ?? '') ?>" placeholder="Cari nama alut..." aria-label="Cari nama alut" onkeyup="filterTable('filterMasterAlut', 'tableMasterAlut')" class="control control-sm w-full pl-8">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse" id="tableMasterAlut">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
            <th class="py-2.5 px-4 w-10">#</th>
            <th class="py-2.5 px-4">Nama Alut / Palsar</th>
            <th class="py-2.5 px-4">Kategori</th>
            <th class="py-2.5 px-4">Satuan</th>
            <?php if ($isAdmin): ?>
              <th class="py-2.5 px-4 text-right">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
          <?php foreach (($masterAlut ?? []) as $idx => $a): ?>
            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors">
              <td class="py-2.5 px-4 text-slate-400 font-mono"><?= $idx + 1 ?></td>
              <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">
                <?= e($a['nama_alut']) ?>
              </td>
              <td class="py-2.5 px-4 whitespace-nowrap">
                <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold <?= AlutKategori::isAlut(AlutKategori::resolve($a)) ? 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' : 'bg-amber-500/10 text-amber-700 dark:text-amber-400' ?>">
                  <?= e(AlutKategori::label(AlutKategori::resolve($a))) ?>
                </span>
              </td>
              <td class="py-2.5 px-4 whitespace-nowrap font-mono text-[11px] text-slate-600 dark:text-slate-300">
                <?= e($a['satuan'] ?? 'Unit') ?>
              </td>
              <?php if ($isAdmin): ?>
                <td class="py-2.5 px-4 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1 justify-end">
                    <button type="button"
                            data-id="<?= (int)$a['id_alut'] ?>"
                            data-nama="<?= e($a['nama_alut']) ?>"
                            data-satuan="<?= e($a['satuan'] ?? 'Unit') ?>"
                            data-kategori="<?= e(AlutKategori::resolve($a)) ?>"
                            onclick="openEditAlutModal(this)"
                            aria-label="Edit alut"
                            class="mst-ghost-btn inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition cursor-pointer"
                            title="Edit Alut">
                      <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    </button>
                    <form method="POST" action="<?= BASE_URL ?>/master.php?action=delete_alut" class="inline-flex" data-confirm="Apakah Anda yakin ingin menghapus alut &quot;<?= e($a['nama_alut']) ?>&quot; dari master data?" data-confirm-title="Hapus Master Alut" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                      <?= csrfInput() ?>
                      <input type="hidden" name="id" value="<?= (int)$a['id_alut'] ?>">
                      <button type="submit" aria-label="Hapus alut" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:border-rose-200 dark:hover:border-rose-900 transition cursor-pointer" title="Hapus Alut">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    </form>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($masterAlut)): ?>
            <tr>
              <td colspan="<?= $isAdmin ? 5 : 4 ?>" class="py-8 text-center text-slate-400">Belum ada data Alut SAR.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
