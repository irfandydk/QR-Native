<?php
/**
 * QR-SAR - Admin Settings View (Orchestrator)
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference AdminSettingsPage.tsx
 * Decomposed into modular partial views under views/admin/partials/
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$tab = $tab ?? 'admin';
$settings = $settings ?? [];
$dbStats = $dbStats ?? [];
$usersList = $usersList ?? [];
$auditLogs = $auditLogs ?? [];
$totalLogs = $totalLogs ?? 0;
$logPage = $logPage ?? 1;
$totalPages = $totalPages ?? 1;
$logActionFilter = $logActionFilter ?? null;
$logSearch = $logSearch ?? null;
$logoUrl = getAppLogoUrl();
?>

<div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-100 transition-colors pb-16">

  <!-- Header Bar -->
  <div class="border-b sticky top-0 z-20 backdrop-blur-md bg-white/95 dark:bg-slate-900/95 border-slate-200 dark:border-slate-800 shadow-xs">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        
        <div class="flex items-center gap-3">
          <a href="<?= BASE_URL ?>/index.php" class="p-2 rounded-xl border border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0" title="Kembali ke Dashboard">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          </a>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="font-black text-base sm:text-lg tracking-wide uppercase text-slate-900 dark:text-white">
                Pengaturan Satker & Keamanan Admin
              </h1>
              <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                HALAMAN KHUSUS
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
              Konfigurasi identitas kantor SAR, profil admin, sandi akun, serta cadangan basis data terpadu
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto">
          <a href="<?= BASE_URL ?>/admin.php?tab=<?= e($tab) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-750 text-xs font-semibold shadow-xs transition-colors cursor-pointer" title="Muat ulang pengaturan">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
            <span>Segarkan</span>
          </a>
        </div>
      </div>

      <!-- Tab Navigation matching AdminSettingsPage.tsx -->
      <div class="flex items-center gap-2 mt-4 overflow-x-auto no-scrollbar border-t border-slate-100 dark:border-slate-800/80 pt-3">
        
        <!-- Tab 1: admin -->
        <a href="<?= BASE_URL ?>/admin.php?tab=admin" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'admin' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
          <span>Profil & Sandi Admin</span>
        </a>

        <!-- Tab 2: user -->
        <a href="<?= BASE_URL ?>/admin.php?tab=user" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'user' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>Akun Pengguna (User)</span>
        </a>

        <!-- Tab 3: satker -->
        <a href="<?= BASE_URL ?>/admin.php?tab=satker" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'satker' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M16 14h.01"/></svg>
          <span>Pengaturan Satker & Aplikasi</span>
        </a>

        <!-- Tab 4: backup -->
        <a href="<?= BASE_URL ?>/admin.php?tab=backup" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'backup' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
          <span>Backup & Restore Database</span>
          <span class="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-extrabold border border-emerald-500/30">
            100% COVER
          </span>
        </a>

        <!-- Tab 5: logs (Audit Trail) -->
        <a href="<?= BASE_URL ?>/admin.php?tab=logs" class="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'logs' ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25 font-black' : 'bg-white dark:bg-slate-800/60 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700/60' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>Log Aktivitas (Audit Trail)</span>
          <span class="text-[9px] px-1.5 py-0.2 rounded-full bg-blue-500/20 text-blue-600 dark:text-blue-400 font-extrabold border border-blue-500/30">
            AUDIT
          </span>
        </a>

      </div>
    </div>
  </div>

  <!-- Main Content Area (Rendered via Partial Views) -->
  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">
    <?php
      $tabViewMap = [
          'admin'  => 'admin/partials/tab_admin',
          'user'   => 'admin/partials/tab_user',
          'satker' => 'admin/partials/tab_satker',
          'backup' => 'admin/partials/tab_backup',
          'logs'   => 'admin/partials/tab_logs',
      ];

      $activePartial = $tabViewMap[$tab] ?? 'admin/partials/tab_admin';
      renderPartial($activePartial, get_defined_vars());
    ?>
  </div>

</div>
