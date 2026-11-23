<?php
/**
 * QR-SAR - Master Data Tab Partial: Potensi SAR & Tim Penyelamat
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$isAdmin = AuthHelper::isAdmin();
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
  <!-- Left Column: Add New Tim Form (Admin) or Read-Only Info (User) -->
  <section class="bento-card-v1 p-5 rounded-2xl space-y-4">
    <div class="pb-3 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-sm font-bold text-slate-900 dark:text-white"><?= $isAdmin ? 'Tambah Tim / Potensi SAR' : 'Otoritas Potensi SAR' ?></h2>
    </div>

    <?php if ($isAdmin): ?>
      <form method="POST" action="<?= BASE_URL ?>/master.php?action=add_tim" class="space-y-3.5">
        <?= csrfInput() ?>
        <div class="field">
          <label class="field-label">Nama Tim Penyelamat / Potensi SAR <span class="text-rose-500">*</span></label>
          <input type="text" name="nama_tim" required placeholder="Contoh: Tim Rescue KPP Kendari / Polairud" class="field-input w-full font-medium">
        </div>

        <button type="submit" class="mst-accent-btn w-full h-9 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          <span>Simpan ke Master Tim</span>
        </button>
      </form>
    <?php else: ?>
      <div class="space-y-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        <div class="inline-flex items-center px-2 py-0.5 rounded-md font-mono font-semibold text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
          Mode Baca (Operator SAR)
        </div>
        <p>Data instansi potensi dan tim SAR di tabel samping digunakan dalam pengerahan unsur pada Operasi SAR dan RENOPS.</p>
        <p class="pl-3 border-l-2 border-slate-200 dark:border-slate-700 text-[11px] text-slate-500 dark:text-slate-400">
          Penambahan organisasi baru dan perubahan nama instansi dikelola oleh <strong class="text-slate-700 dark:text-slate-200">Administrator Utama</strong>.
        </p>
      </div>
    <?php endif; ?>
  </section>

  <!-- Right Column: Tim List Table -->
  <section class="lg:col-span-2 bento-card-v1 rounded-2xl overflow-hidden">
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Tim &amp; Potensi SAR</h2>
        <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
          <?= count($masterTim ?? []) ?>
        </span>
      </div>
      <div class="relative w-full sm:w-56">
        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="filterMasterTim" value="<?= e($searchQ ?? '') ?>" placeholder="Cari nama potensi..." aria-label="Cari nama potensi" onkeyup="filterTable('filterMasterTim', 'tableMasterTim')" class="control control-sm w-full pl-8">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse" id="tableMasterTim">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
            <th class="py-2.5 px-4 w-10">#</th>
            <th class="py-2.5 px-4">Nama Tim / Instansi Potensi</th>
            <?php if ($isAdmin): ?>
              <th class="py-2.5 px-4 text-right">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
          <?php foreach (($masterTim ?? []) as $idx => $t): ?>
            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors">
              <td class="py-2.5 px-4 text-slate-400 font-mono"><?= $idx + 1 ?></td>
              <td class="py-2.5 px-4 font-semibold text-slate-900 dark:text-white">
                <?= e($t['nama_tim']) ?>
              </td>
              <?php if ($isAdmin): ?>
                <td class="py-2.5 px-4 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1 justify-end">
                    <button type="button"
                            data-id="<?= (int)$t['id_tim'] ?>"
                            data-nama="<?= e($t['nama_tim']) ?>"
                            onclick="openEditTimModal(this)"
                            aria-label="Edit tim potensi SAR"
                            class="mst-ghost-btn inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition cursor-pointer"
                            title="Edit Tim / Potensi SAR">
                      <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    </button>
                    <form method="POST" action="<?= BASE_URL ?>/master.php?action=delete_tim" class="inline-flex" data-confirm="Apakah Anda yakin ingin menghapus &quot;<?= e($t['nama_tim']) ?>&quot; dari master data?" data-confirm-title="Hapus Master Tim" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                      <?= csrfInput() ?>
                      <input type="hidden" name="id" value="<?= (int)$t['id_tim'] ?>">
                      <button type="submit" aria-label="Hapus tim potensi" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:border-rose-200 dark:hover:border-rose-900 transition cursor-pointer" title="Hapus Tim / Potensi">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    </form>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($masterTim)): ?>
            <tr>
              <td colspan="<?= $isAdmin ? 3 : 2 ?>" class="py-8 text-center text-slate-400">Belum ada data Potensi SAR.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
