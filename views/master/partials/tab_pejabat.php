<?php
/**
 * QR-SAR - Master Data Tab Partial: Pejabat Berwenang & Penandatangan SMC
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$isAdmin = AuthHelper::isAdmin();
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
  <!-- Left Column: Add New Pejabat Form (Admin) or Read-Only Info (User) -->
  <section class="bento-card-v1 p-5 rounded-2xl space-y-4">
    <div class="pb-3 border-b border-slate-100 dark:border-slate-800">
      <h2 class="text-sm font-bold text-slate-900 dark:text-white"><?= $isAdmin ? 'Tambah Pejabat Baru' : 'Otoritas Pejabat & SMC' ?></h2>
    </div>

    <?php if ($isAdmin): ?>
      <form method="POST" action="<?= BASE_URL ?>/master.php?action=add_pejabat" class="space-y-3.5">
        <?= csrfInput() ?>
        <div class="field">
          <label class="field-label">Nama Lengkap Pejabat <span class="text-rose-500">*</span></label>
          <input type="text" name="nama" required placeholder="Contoh: AMIRUDDIN AS, S.Sos." class="field-input w-full font-medium">
        </div>

        <div class="field">
          <label class="field-label">Jabatan Resmi <span class="text-rose-500">*</span></label>
          <input type="text" name="jabatan" required placeholder="Contoh: KEPALA KANTOR / SMC KPP KENDARI" class="field-input w-full font-medium">
        </div>

        <div class="grid grid-cols-2 gap-2.5">
          <div class="field">
            <label class="field-label">Pangkat / Golongan</label>
            <input type="text" name="pangkat" placeholder="PEMBINA (IV/a)" class="field-input w-full font-medium">
          </div>
          <div class="field">
            <label class="field-label">NIP</label>
            <input type="text" name="nip" placeholder="197405101998031002" class="field-input w-full font-mono">
          </div>
        </div>

        <label class="flex items-center gap-2 pt-0.5 cursor-pointer text-xs text-slate-700 dark:text-slate-300">
          <input type="checkbox" name="is_default_smc" value="1" class="rounded border-slate-300 dark:border-slate-700">
          <span>Tetapkan sebagai Default SMC dokumen BRA</span>
        </label>

        <button type="submit" class="mst-accent-btn w-full h-9 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          <span>Simpan Data Pejabat</span>
        </button>
      </form>
    <?php else: ?>
      <div class="space-y-3 text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        <div class="inline-flex items-center px-2 py-0.5 rounded-md font-mono font-semibold text-[11px] bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
          Mode Baca (Operator SAR)
        </div>
        <p>Data pejabat berwenang dan SMC digunakan untuk penandatanganan resmi pada dokumen BRA, RENOPS, dan Penutupan SAR.</p>
        <p class="pl-3 border-l-2 border-slate-200 dark:border-slate-700 text-[11px] text-slate-500 dark:text-slate-400">
          Penetapan Default SMC dan pembaruan daftar pejabat dikelola oleh <strong class="text-slate-700 dark:text-slate-200">Administrator Utama</strong>.
        </p>
      </div>
    <?php endif; ?>
  </section>

  <!-- Right Column: Pejabat List Table -->
  <section class="lg:col-span-2 bento-card-v1 rounded-2xl overflow-hidden">
    <div class="px-4 sm:px-5 py-3.5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Pejabat &amp; SMC</h2>
        <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
          <?= count($pejabatList ?? []) ?>
        </span>
      </div>
      <div class="relative w-full sm:w-56">
        <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" id="filterMasterPejabat" value="<?= e($searchQ ?? '') ?>" placeholder="Cari nama pejabat..." aria-label="Cari nama pejabat" onkeyup="filterTable('filterMasterPejabat', 'tableMasterPejabat')" class="control control-sm w-full pl-8">
      </div>
    </div>

    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse" id="tableMasterPejabat">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
            <th class="py-2.5 px-4">Pejabat &amp; Jabatan</th>
            <th class="py-2.5 px-4">NIP / Pangkat</th>
            <th class="py-2.5 px-4">Status SMC</th>
            <?php if ($isAdmin): ?>
              <th class="py-2.5 px-4 text-right">Aksi</th>
            <?php endif; ?>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
          <?php foreach (($pejabatList ?? []) as $p): ?>
            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors">
              <td class="py-2.5 px-4">
                <div class="font-semibold text-slate-900 dark:text-white"><?= e($p['nama']) ?></div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5"><?= e($p['jabatan']) ?></div>
              </td>
              <td class="py-2.5 px-4 text-slate-600 dark:text-slate-300 font-mono text-[11px] whitespace-nowrap">
                <div><?= e($p['pangkat'] ?: '-') ?></div>
                <div class="text-slate-400 dark:text-slate-500">NIP <?= e($p['nip'] ?: '-') ?></div>
              </td>
              <td class="py-2.5 px-4 whitespace-nowrap">
                <?php if (!empty($p['is_default_smc'])): ?>
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                    DEFAULT SMC
                  </span>
                <?php elseif ($isAdmin): ?>
                  <form method="POST" action="<?= BASE_URL ?>/master.php?action=toggle_smc" class="inline-flex">
                    <?= csrfInput() ?>
                    <input type="hidden" name="id" value="<?= e((string)$p['id']) ?>">
                    <button type="submit" class="mst-ghost-btn px-2.5 py-1 rounded-md text-[11px] font-semibold transition cursor-pointer" title="Jadikan default SMC">
                      Set Default
                    </button>
                  </form>
                <?php else: ?>
                  <span class="text-slate-400 text-[11px]">-</span>
                <?php endif; ?>
              </td>
              <?php if ($isAdmin): ?>
                <td class="py-2.5 px-4 text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1 justify-end">
                    <button type="button"
                            data-id="<?= e((string)$p['id']) ?>"
                            data-nama="<?= e($p['nama']) ?>"
                            data-jabatan="<?= e($p['jabatan']) ?>"
                            data-pangkat="<?= e($p['pangkat'] ?? '') ?>"
                            data-nip="<?= e($p['nip'] ?? '') ?>"
                            data-is-default-smc="<?= !empty($p['is_default_smc']) ? '1' : '0' ?>"
                            onclick="openEditPejabatModal(this)"
                            aria-label="Edit pejabat"
                            class="mst-ghost-btn inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition cursor-pointer"
                            title="Edit Pejabat">
                      <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    </button>
                    <form method="POST" action="<?= BASE_URL ?>/master.php?action=delete_pejabat" class="inline-flex" data-confirm="Apakah Anda yakin ingin menghapus pejabat &quot;<?= e($p['nama']) ?>&quot;?" data-confirm-title="Hapus Pejabat" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                      <?= csrfInput() ?>
                      <input type="hidden" name="id" value="<?= e((string)$p['id']) ?>">
                      <button type="submit" aria-label="Hapus pejabat" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:border-rose-200 dark:hover:border-rose-900 transition cursor-pointer" title="Hapus Pejabat">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                      </button>
                    </form>
                  </div>
                </td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($pejabatList)): ?>
            <tr>
              <td colspan="<?= $isAdmin ? 4 : 3 ?>" class="py-8 text-center text-slate-400">Belum ada data Pejabat.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
