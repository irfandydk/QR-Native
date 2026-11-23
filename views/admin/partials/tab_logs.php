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
<div class="space-y-4">

  <!-- Unified Filter & Maintenance Bar (No Duplicate Sub-Header) -->
  <section aria-label="Filter dan pemeliharaan log audit" class="flex flex-col xl:flex-row xl:items-center justify-between gap-2.5">
    <form method="GET" action="<?= BASE_URL ?>/admin.php" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 flex-1 min-w-0">
      <input type="hidden" name="tab" value="logs" />

      <select name="log_action" aria-label="Filter kategori aksi operasional" class="control control-sm sm:w-56 font-medium">
        <option value="">Semua Kategori Aksi</option>
        <optgroup label="Autentikasi &amp; Akun">
          <option value="LOGIN_SUCCESS" <?= $logActionFilter === 'LOGIN_SUCCESS' ? 'selected' : '' ?>>LOGIN_SUCCESS (Login Berhasil)</option>
          <option value="LOGIN_FAILED" <?= $logActionFilter === 'LOGIN_FAILED' ? 'selected' : '' ?>>LOGIN_FAILED (Gagal Login)</option>
          <option value="LOGOUT" <?= $logActionFilter === 'LOGOUT' ? 'selected' : '' ?>>LOGOUT (Keluar Sistem)</option>
          <option value="PROFILE_UPDATE" <?= $logActionFilter === 'PROFILE_UPDATE' ? 'selected' : '' ?>>PROFILE_UPDATE (Pembaruan Profil)</option>
          <option value="PASSWORD_CHANGE" <?= $logActionFilter === 'PASSWORD_CHANGE' ? 'selected' : '' ?>>PASSWORD_CHANGE (Ubah Sandi)</option>
          <option value="USER_RESET" <?= $logActionFilter === 'USER_RESET' ? 'selected' : '' ?>>USER_RESET (Reset Pengguna)</option>
        </optgroup>
        <optgroup label="Operasi SAR">
          <option value="OPS_CREATE" <?= $logActionFilter === 'OPS_CREATE' ? 'selected' : '' ?>>OPS_CREATE (Buka Operasi)</option>
          <option value="OPS_CLOSE" <?= $logActionFilter === 'OPS_CLOSE' ? 'selected' : '' ?>>OPS_CLOSE (Tutup Operasi)</option>
          <option value="OPS_ROLLBACK" <?= $logActionFilter === 'OPS_ROLLBACK' ? 'selected' : '' ?>>OPS_ROLLBACK (Perpanjang Operasi)</option>
        </optgroup>
        <optgroup label="Naskah Berita SAR (BRA)">
          <option value="BRA_CREATE" <?= $logActionFilter === 'BRA_CREATE' ? 'selected' : '' ?>>BRA_CREATE (Terbitkan BRA)</option>
          <option value="BRA_UPDATE" <?= $logActionFilter === 'BRA_UPDATE' ? 'selected' : '' ?>>BRA_UPDATE (Perbarui BRA)</option>
          <option value="BRA_DELETE" <?= $logActionFilter === 'BRA_DELETE' ? 'selected' : '' ?>>BRA_DELETE (Hapus BRA)</option>
        </optgroup>
        <optgroup label="Sistem &amp; Basis Data">
          <option value="SETTINGS_UPDATE" <?= $logActionFilter === 'SETTINGS_UPDATE' ? 'selected' : '' ?>>SETTINGS_UPDATE (Pengaturan Satker)</option>
          <option value="DATABASE_BACKUP" <?= $logActionFilter === 'DATABASE_BACKUP' ? 'selected' : '' ?>>DATABASE_BACKUP (Ekspor Cadangan)</option>
          <option value="DATABASE_RESTORE" <?= $logActionFilter === 'DATABASE_RESTORE' ? 'selected' : '' ?>>DATABASE_RESTORE (Pemulihan DB)</option>
        </optgroup>
      </select>

      <div class="relative flex-1 min-w-0 sm:max-w-xs">
        <svg class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        <input type="text" name="log_search" value="<?= e($logSearch ?? '') ?>" placeholder="Cari keterangan, pengguna, IP..." aria-label="Cari log audit" class="control control-sm w-full pl-9 pr-3" />
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <button type="submit" class="adm-accent-btn inline-flex items-center justify-center h-9 px-3.5 rounded-xl font-bold text-xs transition cursor-pointer">
          <span>Saring</span>
        </button>

        <?php if (!empty($logActionFilter) || !empty($logSearch)): ?>
          <a href="<?= BASE_URL ?>/admin.php?tab=logs" class="adm-ghost-btn inline-flex items-center justify-center h-9 px-3 rounded-xl text-xs font-semibold transition no-underline">
            <span>Reset</span>
          </a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Maintenance Actions -->
    <div class="flex flex-wrap items-center gap-2 shrink-0">
      <a href="<?= BASE_URL ?>/admin.php?action=export_audit_csv<?= !empty($logActionFilter) ? '&log_action=' . urlencode($logActionFilter) : '' ?><?= !empty($logSearch) ? '&log_search=' . urlencode($logSearch) : '' ?>" class="adm-ghost-btn inline-flex items-center gap-1.5 h-9 px-3 rounded-xl text-xs font-semibold transition no-underline" title="Unduh Rekam Jejak Audit ke CSV">
        <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
        <span>Ekspor CSV</span>
      </a>

      <form method="POST" action="<?= BASE_URL ?>/admin.php" class="inline-flex m-0"
            data-confirm="PERINGATAN PEMELIHARAAN LOG AUDIT: Apakah Anda yakin ingin membersihkan seluruh rekaman log audit yang telah berumur lebih dari 180 hari? Tindakan ini akan mengosongkan arsip lama demi menjaga performa basis data."
            data-confirm-title="Pangkas Log Audit Lama"
            data-confirm-btn="Ya, Pangkas Log"
            data-confirm-danger="true">
        <?= csrfInput() ?>
        <input type="hidden" name="action" value="prune_audit">
        <input type="hidden" name="older_than_days" value="180">
        <button type="submit" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl bg-rose-500/10 hover:bg-rose-600 text-rose-600 dark:text-rose-400 hover:text-white text-xs font-semibold border border-rose-500/25 transition cursor-pointer" title="Pangkas log berumur lebih dari 180 hari">
          <span>Pangkas &gt; 180 Hari</span>
        </button>
      </form>
    </div>
  </section>

  <!-- Tabel Riwayat Log Audit -->
  <section class="bento-card-v1 rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
            <th class="py-3 px-4">Waktu (WITA)</th>
            <th class="py-3 px-4">Aktor</th>
            <th class="py-3 px-4">Aksi</th>
            <th class="py-3 px-4">Sasaran</th>
            <th class="py-3 px-4">Uraian Aktivitas</th>
            <th class="py-3 px-4">Alamat IP</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
          <?php if (empty($auditLogs)): ?>
            <tr>
              <td colspan="6" class="py-12 text-center text-slate-400 text-xs">
                Tidak ada rekaman jejak audit yang sesuai dengan kriteria filter.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($auditLogs as $log): ?>
              <?php
                $action = strtoupper((string)($log['action'] ?? ''));
                $badgeClass = 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300';
                if (in_array($action, ['LOGIN_SUCCESS', 'DATABASE_BACKUP', 'BRA_CREATE'], true)) {
                    $badgeClass = 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
                } elseif (in_array($action, ['OPS_CREATE', 'BRA_UPDATE', 'SETTINGS_UPDATE'], true)) {
                    $badgeClass = 'bg-sky-500/10 text-sky-700 dark:text-sky-300';
                } elseif (in_array($action, ['PROFILE_UPDATE', 'PASSWORD_CHANGE', 'PASSWORD_RESET', 'OPS_ROLLBACK', 'USER_RESET'], true)) {
                    $badgeClass = 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
                } elseif (in_array($action, ['LOGIN_FAILED', 'OPS_CLOSE', 'BRA_DELETE', 'DATABASE_RESTORE'], true)) {
                    $badgeClass = 'bg-rose-500/10 text-rose-700 dark:text-rose-400';
                }
              ?>
              <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors">
                <td class="py-3 px-4 whitespace-nowrap font-mono text-[11px] font-semibold text-slate-900 dark:text-white">
                  <?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?>
                </td>

                <td class="py-3 px-4 whitespace-nowrap">
                  <div class="font-semibold text-slate-900 dark:text-white"><?= e($log['username'] ?? 'system') ?></div>
                  <div class="text-[10px] font-mono <?= ($log['role'] ?? '') === 'ADMIN' ? 'adm-accent-text font-bold' : 'text-slate-400' ?>">
                    <?= e($log['role'] ?? 'GUEST') ?>
                  </div>
                </td>

                <td class="py-3 px-4 whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold font-mono <?= $badgeClass ?>">
                    <?= e($action) ?>
                  </span>
                </td>

                <td class="py-3 px-4 whitespace-nowrap">
                  <?php if (!empty($log['target_type'])): ?>
                    <div class="font-medium text-slate-700 dark:text-slate-300"><?= e($log['target_type']) ?></div>
                    <?php if (!empty($log['target_id'])): ?>
                      <div class="text-[11px] font-mono text-slate-400">#<?= e($log['target_id']) ?></div>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="text-slate-400">-</span>
                  <?php endif; ?>
                </td>

                <td class="py-3 px-4">
                  <div class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed max-w-md sm:max-w-lg break-words">
                    <?= e($log['description']) ?>
                  </div>
                </td>

                <td class="py-3 px-4 whitespace-nowrap">
                  <div class="font-mono text-[11px] text-slate-600 dark:text-slate-400">
                    <?= e($log['ip_address'] ?? '127.0.0.1') ?>
                  </div>
                  <?php if (!empty($log['user_agent'])): ?>
                    <div class="text-[10px] text-slate-400 truncate max-w-[130px]" title="<?= e($log['user_agent']) ?>">
                      <?= e(substr($log['user_agent'], 0, 26)) ?>...
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
      <div class="px-4 py-3 border-t border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
        <div class="text-slate-500 dark:text-slate-400">
          Halaman <strong class="font-mono text-slate-900 dark:text-white"><?= $logPage ?></strong> dari <strong class="font-mono text-slate-900 dark:text-white"><?= $totalPages ?></strong> (<?= number_format($totalLogs) ?> baris)
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
          <?php if ($logPage > 1): ?>
            <a href="<?= BASE_URL ?>/admin.php?<?= $buildQuery($logPage - 1) ?>" class="adm-ghost-btn inline-flex items-center h-8 px-3 rounded-lg font-semibold text-xs transition no-underline">
              &larr; Sebelumnya
            </a>
          <?php else: ?>
            <span class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-100 dark:border-slate-800 text-slate-400 text-xs font-medium cursor-not-allowed">
              &larr; Sebelumnya
            </span>
          <?php endif; ?>

          <?php if ($logPage < $totalPages): ?>
            <a href="<?= BASE_URL ?>/admin.php?<?= $buildQuery($logPage + 1) ?>" class="adm-ghost-btn inline-flex items-center h-8 px-3 rounded-lg font-semibold text-xs transition no-underline">
              Selanjutnya &rarr;
            </a>
          <?php else: ?>
            <span class="inline-flex items-center h-8 px-3 rounded-lg border border-slate-100 dark:border-slate-800 text-slate-400 text-xs font-medium cursor-not-allowed">
              Selanjutnya &rarr;
            </span>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </section>

</div>
