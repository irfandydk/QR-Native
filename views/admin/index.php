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
  <div class="border-b bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        
        <div class="flex items-center gap-3">
          <div>
            <div class="flex items-center gap-2">
              <h1 class="font-bold text-base sm:text-lg tracking-wide text-slate-900 dark:text-white">
                Pengaturan Satker & Keamanan Admin
              </h1>
              <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                HALAMAN KHUSUS
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">
              Konfigurasi identitas kantor SAR, profil admin, sandi akun, serta cadangan basis data terpadu
            </p>
          </div>
        </div>

      </div>

      <!-- Tab Navigation (High-End Tactile Segmented Bar) -->
      <div class="cockpit-glass p-1.5 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-xs flex items-center gap-1.5 mt-4 overflow-x-auto no-scrollbar">
        
        <!-- Tab 1: admin -->
        <a href="<?= BASE_URL ?>/admin.php?tab=admin" class="btn-tactile flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'admin' ? 'bg-orange-600 text-white shadow-md font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
          <span>Profil & Sandi Admin</span>
        </a>

        <!-- Tab 2: user -->
        <a href="<?= BASE_URL ?>/admin.php?tab=user" class="btn-tactile flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'user' ? 'bg-orange-600 text-white shadow-md font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <span>Akun Pengguna (User)</span>
        </a>

        <!-- Tab 3: satker -->
        <a href="<?= BASE_URL ?>/admin.php?tab=satker" class="btn-tactile flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'satker' ? 'bg-orange-600 text-white shadow-md font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="16" height="20" x="4" y="2" rx="2" ry="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M8 10h.01"/><path d="M16 10h.01"/><path d="M8 14h.01"/><path d="M16 14h.01"/></svg>
          <span>Pengaturan Satker & Aplikasi</span>
        </a>

        <!-- Tab 4: backup -->
        <a href="<?= BASE_URL ?>/admin.php?tab=backup" class="btn-tactile flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'backup' ? 'bg-orange-600 text-white shadow-md font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
          <span>Backup & Restore Database</span>
          <span class="text-[11px] px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 font-semibold border border-emerald-500/30">
            100% COVER
          </span>
        </a>

        <!-- Tab 5: logs (Audit Trail) -->
        <a href="<?= BASE_URL ?>/admin.php?tab=logs" class="btn-tactile flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 <?= $tab === 'logs' ? 'bg-orange-600 text-white shadow-md font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>Log Aktivitas (Audit Trail)</span>
          <span class="text-[11px] px-1.5 py-0.2 rounded-full bg-blue-500/20 text-blue-600 dark:text-blue-400 font-semibold border border-blue-500/30">
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
