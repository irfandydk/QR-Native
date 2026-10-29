<?php
/**
 * QR-SAR - Admin Tab Partial: Log Aktivitas & Jejak Audit (Audit Trail)
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}
?>
<div class="space-y-6">

  <!-- Header & Filter Bar -->
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
        <div>
          <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>Log Aktivitas & Jejak Audit (Audit Trail)</span>
            <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
              REAL-TIME
            </span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Rekam jejak otentikasi, pergerakan misi SAR, penerbitan naskah BRA, dan pemeliharaan basis data
          </p>
        </div>
      </div>

      <!-- Ringkasan Counter & Aksi Pemeliharaan -->
      <div class="flex flex-wrap items-center gap-2">
        <div class="px-3 py-1.5 rounded-xl bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-xs">
          <span class="text-slate-400">Total Log:</span>
          <span class="font-bold text-slate-900 dark:text-white ml-1"><?= number_format($totalLogs) ?></span>
        </div>

        <!-- Tombol Ekspor CSV -->
        <a href="<?= BASE_URL ?>/admin.php?action=export_audit_csv<?= !empty($logActionFilter) ? '&log_action=' . urlencode($logActionFilter) : '' ?><?= !empty($logSearch) ? '&log_search=' . urlencode($logSearch) : '' ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-500/20 text-xs font-bold border border-blue-200 dark:border-blue-900/30 transition-colors cursor-pointer" title="Unduh Rekam Jejak Audit ke format CSV Excel">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
          <span>Ekspor CSV</span>
        </a>

        <!-- Tombol Pembersihan / Pruning Log Lama -->
        <form method="POST" action="<?= BASE_URL ?>/admin.php" class="inline m-0"
              data-confirm="PERINGATAN PEMELIHARAAN LOG AUDIT: Apakah Anda yakin ingin membersihkan seluruh rekaman log audit yang telah berumur lebih dari 180 hari? Tindakan ini akan mengosongkan arsip lama demi menjaga performa basis data."
              data-confirm-title="Pangkas Log Audit Lama"
              data-confirm-btn="Ya, Pangkas Log"
              data-confirm-danger="true">
          <?= csrfInput() ?>
          <input type="hidden" name="action" value="prune_audit">
          <input type="hidden" name="older_than_days" value="180">
          <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-100 dark:hover:bg-rose-500/20 text-xs font-bold border border-rose-200 dark:border-rose-900/30 transition-colors cursor-pointer" title="Pangkas log berumur lebih dari 180 hari">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            <span>Pangkas > 180 Hari</span>
          </button>
        </form>

        <?php if (!empty($logActionFilter) || !empty($logSearch)): ?>
          <a href="<?= BASE_URL ?>/admin.php?tab=logs" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-100 dark:hover:bg-red-500/20 text-xs font-bold border border-red-200 dark:border-red-900/30 transition-colors cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
            <span>Reset Filter</span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Form Filter & Pencarian -->
    <form method="GET" action="<?= BASE_URL ?>/admin.php" class="mt-4 grid grid-cols-1 sm:grid-cols-12 gap-3">
      <input type="hidden" name="tab" value="logs" />

      <!-- Filter Kategori Aksi -->
      <div class="sm:col-span-4">
        <div class="field">
          <label class="field-label">Kategori Aksi Operasional</label>
          <select name="log_action" class="field-input w-full font-medium">
          <option value="">Semua Kategori Aksi</option>
          <optgroup label="Autentikasi & Akun">
            <option value="LOGIN_SUCCESS" <?= $logActionFilter === 'LOGIN_SUCCESS' ? 'selected' : '' ?>>LOGIN_SUCCESS (Login Berhasil)</option>
            <option value="LOGIN_FAILED" <?= $logActionFilter === 'LOGIN_FAILED' ? 'selected' : '' ?>>LOGIN_FAILED (Gagal Login)</option>
            <option value="LOGOUT" <?= $logActionFilter === 'LOGOUT' ? 'selected' : '' ?>>LOGOUT (Keluar Sistem)</option>
            <option value="PROFILE_UPDATE" <?= $logActionFilter === 'PROFILE_UPDATE' ? 'selected' : '' ?>>PROFILE_UPDATE (Pembaruan Profil)</option>
            <option value="PASSWORD_CHANGE" <?= $logActionFilter === 'PASSWORD_CHANGE' ? 'selected' : '' ?>>PASSWORD_CHANGE (Ubah Kata Sandi)</option>
            <option value="USER_RESET" <?= $logActionFilter === 'USER_RESET' ? 'selected' : '' ?>>USER_RESET (Reset Pengguna)</option>
          </optgroup>
          <optgroup label="Operasi SAR">
            <option value="OPS_CREATE" <?= $logActionFilter === 'OPS_CREATE' ? 'selected' : '' ?>>OPS_CREATE (Buka Misi Operasi)</option>
            <option value="OPS_CLOSE" <?= $logActionFilter === 'OPS_CLOSE' ? 'selected' : '' ?>>OPS_CLOSE (Tutup Misi Operasi)</option>
            <option value="OPS_ROLLBACK" <?= $logActionFilter === 'OPS_ROLLBACK' ? 'selected' : '' ?>>OPS_ROLLBACK (Buka Kembali Misi)</option>
          </optgroup>
          <optgroup label="Naskah Berita SAR (BRA)">
            <option value="BRA_CREATE" <?= $logActionFilter === 'BRA_CREATE' ? 'selected' : '' ?>>BRA_CREATE (Terbitkan Naskah)</option>
            <option value="BRA_UPDATE" <?= $logActionFilter === 'BRA_UPDATE' ? 'selected' : '' ?>>BRA_UPDATE (Perbarui Naskah)</option>
            <option value="BRA_DELETE" <?= $logActionFilter === 'BRA_DELETE' ? 'selected' : '' ?>>BRA_DELETE (Hapus Naskah)</option>
          </optgroup>
          <optgroup label="Sistem & Basis Data">
            <option value="SETTINGS_UPDATE" <?= $logActionFilter === 'SETTINGS_UPDATE' ? 'selected' : '' ?>>SETTINGS_UPDATE (Pengaturan Satker)</option>
            <option value="DATABASE_BACKUP" <?= $logActionFilter === 'DATABASE_BACKUP' ? 'selected' : '' ?>>DATABASE_BACKUP (Ekspor Cadangan)</option>
            <option value="DATABASE_RESTORE" <?= $logActionFilter === 'DATABASE_RESTORE' ? 'selected' : '' ?>>DATABASE_RESTORE (Pemulihan Database)</option>
          </optgroup>
        </select>
        </div>
      </div>

      <!-- Pencarian Teks Bebas -->
      <div class="sm:col-span-6">
        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">Cari Keterangan / Pengguna / IP / Sasaran</label>
        <div class="relative">
          <input type="text" name="log_search" value="<?= e($logSearch ?? '') ?>" placeholder="Ketik kata kunci pencarian..." class="control control-sm w-full pl-9 pr-3" />
          <svg class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </div>
      </div>

      <!-- Tombol Filter -->
      <div class="sm:col-span-2 flex items-end">
        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-xs transition-colors cursor-pointer">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
          <span>Saring</span>
        </button>
      </div>
    </form>
  </div>

  <!-- Tabel Riwayat Log Audit -->
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400 uppercase font-bold tracking-wider text-[11px] border-b border-slate-100 dark:border-slate-800">
          <tr>
            <th class="py-3 px-4">Waktu (WITA)</th>
            <th class="py-3 px-4">Aktor / Otoritas</th>
            <th class="py-3 px-4">Aksi Operasional</th>
            <th class="py-3 px-4">Sasaran Objek</th>
            <th class="py-3 px-4">Uraian Aktivitas</th>
            <th class="py-3 px-4">Alamat IP / Klien</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
          <?php if (empty($auditLogs)): ?>
            <tr>
              <td colspan="6" class="py-12 text-center text-slate-400">
                <div class="flex flex-col items-center justify-center gap-2">
                  <svg class="w-8 h-8 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><line x1="9" x2="15" y1="9" y2="9"/><line x1="9" x2="15" y1="13" y2="13"/><line x1="9" x2="11" y1="17" y2="17"/></svg>
                  <p class="font-semibold text-xs">Tidak ada rekaman jejak audit yang sesuai dengan kriteria filter.</p>
                </div>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($auditLogs as $log): ?>
              <?php
                $action = strtoupper((string)($log['action'] ?? ''));
                $badgeClass = 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30';
                if (in_array($action, ['LOGIN_SUCCESS', 'DATABASE_BACKUP', 'BRA_CREATE'], true)) {
                    $badgeClass = 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
                } elseif (in_array($action, ['OPS_CREATE', 'BRA_UPDATE', 'SETTINGS_UPDATE'], true)) {
                    $badgeClass = 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30';
                } elseif (in_array($action, ['PROFILE_UPDATE', 'PASSWORD_CHANGE', 'PASSWORD_RESET', 'OPS_ROLLBACK', 'USER_RESET'], true)) {
                    $badgeClass = 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30';
                } elseif (in_array($action, ['LOGIN_FAILED', 'OPS_CLOSE', 'BRA_DELETE', 'DATABASE_RESTORE'], true)) {
                    $badgeClass = 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30';
                }
              ?>
              <tr class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                <!-- Kolom Waktu -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <div class="font-mono text-[11px] font-bold text-slate-900 dark:text-white">
                    <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                  </div>
                  <div class="text-[11px] text-slate-400 font-sans">
                    <?= date('l', strtotime($log['created_at'])) ?>
                  </div>
                </td>

                <!-- Kolom Aktor & Role -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <div class="font-bold text-slate-900 dark:text-white">
                    <?= e($log['username'] ?? 'system') ?>
                  </div>
                  <span class="inline-flex items-center text-[11px] font-bold uppercase px-2 py-0.2 rounded-full mt-0.5 <?= ($log['role'] ?? '') === 'ADMIN' ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400' ?>">
                    <?= e($log['role'] ?? 'GUEST') ?>
                  </span>
                </td>

                <!-- Kolom Aksi -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-[11px] font-bold border tracking-wide font-mono <?= $badgeClass ?>">
                    <?= e($action) ?>
                  </span>
                </td>

                <!-- Kolom Sasaran -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <?php if (!empty($log['target_type'])): ?>
                    <div class="font-semibold text-slate-700 dark:text-slate-300">
                      <?= e($log['target_type']) ?>
                    </div>
                    <?php if (!empty($log['target_id'])): ?>
                      <div class="text-[11px] font-mono text-slate-400">
                        #<?= e($log['target_id']) ?>
                      </div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-slate-400 text-[11px]">-</span>
                  <?php endif; ?>
                </td>

                <!-- Kolom Deskripsi -->
                <td class="py-3 px-4">
                  <div class="text-xs text-slate-800 dark:text-slate-200 leading-relaxed font-normal max-w-md sm:max-w-lg break-words">
                    <?= e($log['description']) ?>
                  </div>
                </td>

                <!-- Kolom IP / Klien -->
                <td class="py-3 px-4 whitespace-nowrap">
                  <div class="font-mono text-[11px] text-slate-600 dark:text-slate-400 font-semibold">
                    <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                  </div>
                  <?php if (!empty($log['user_agent'])): ?>
                    <div class="text-[11px] text-slate-400 truncate max-w-[140px]" title="<?= e($log['user_agent']) ?>">
                      <?= e(substr($log['user_agent'], 0, 30)) ?>...
                    </div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination Bar -->
    <?php if ($totalPages > 1): ?>
      <div class="p-4 bg-slate-50 dark:bg-slate-800/40 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="text-xs text-slate-500 dark:text-slate-400">
          Halaman <span class="font-bold text-slate-900 dark:text-white"><?= $logPage ?></span> dari <span class="font-bold text-slate-900 dark:text-white"><?= $totalPages ?></span> (Total <?= number_format($totalLogs) ?> baris)
        </div>
        <div class="flex items-center gap-1.5">
          <?php
            $buildQuery = function(int $p) use ($logActionFilter, $logSearch): string {
                $params = ['tab' => 'logs', 'page' => $p];
                if (!empty($logActionFilter)) $params['log_action'] = $logActionFilter;
                if (!empty($logSearch)) $params['log_search'] = $logSearch;
                return http_build_query($params);
            };
          ?>
          <!-- Previous Button -->
          <?php if ($logPage > 1): ?>
            <a href="<?= BASE_URL ?>/admin.php?<?= $buildQuery($logPage - 1) ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 text-xs font-bold transition-colors">
              &larr; Sebelumnya
            </a>
          <?php else: ?>
            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-slate-400 text-xs font-bold cursor-not-allowed">
              &larr; Sebelumnya
            </span>
          <?php endif; ?>

          <!-- Next Button -->
          <?php if ($logPage < $totalPages): ?>
            <a href="<?= BASE_URL ?>/admin.php?<?= $buildQuery($logPage + 1) ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 text-xs font-bold transition-colors">
              Selanjutnya &rarr;
            </a>
          <?php else: ?>
            <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 text-slate-400 text-xs font-bold cursor-not-allowed">
              Selanjutnya &rarr;
            </span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

  </div>

</div>
