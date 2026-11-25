<?php
/**
 * QR-SAR - Layout Top Header & Top-Right User Profile Popover
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Tinggi 64px (h-16) sejajar presisi dengan brand header sidebar.
 */

declare(strict_types=1);

$headerTitle = $navbarTitle ?? $pageTitle ?? 'Dashboard Operasi SAR';
$headerTitle = preg_replace('/\s*-\s*QR SAR.*$/i', '', (string)$headerTitle);
$headerSubtitle = $navbarSubtitle ?? 'Kantor Pencarian dan Pertolongan Kelas A Kendari · Basarnas';
?>
<?php if (($currentRoute ?? '') !== 'gis'): ?>
<header class="print:hidden navbar top-navbar no-print sticky top-0 z-30 h-16 backdrop-blur-md border-b transition-colors duration-200 bg-white/95 dark:bg-slate-900/90 border-slate-200/90 dark:border-slate-800 text-slate-900 dark:text-slate-100">
  <div class="w-full h-full px-4 sm:px-6 lg:pl-7 flex items-center justify-between gap-3">
    <!-- Left: Mobile Drawer Trigger & Page Context -->
    <div class="flex items-center gap-3 min-w-0">
      <button
        type="button"
        id="mobile-menu-btn"
        class="lg:hidden inline-flex items-center justify-center w-9 h-9 rounded-xl border text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800"
        title="Buka Navigasi Sistem"
        aria-label="Buka navigasi sistem"
      >
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
      </button>

      <div class="min-w-0">
        <span class="font-bold text-sm sm:text-[15px] tracking-tight truncate block text-slate-900 dark:text-slate-100 leading-snug">
          <?= e($headerTitle) ?>
        </span>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 font-medium tracking-tight truncate hidden md:block leading-tight mt-0.5">
          <?= e($headerSubtitle) ?>
        </p>
      </div>
    </div>

    <!-- Right: Global Theme Switch + Top-Right User Profile Trigger & Popover -->
    <div class="flex items-center gap-2 sm:gap-3 shrink-0">
      <button
        type="button"
        id="theme-toggle-btn"
        class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200/90 dark:border-slate-700/80 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800/80 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer shrink-0"
        title="Ganti Mode Gelap / Terang"
        aria-label="Ganti mode gelap atau terang"
      >
        <span id="theme-icon" class="flex items-center justify-center" aria-hidden="true">
          <svg class="w-4 h-4 hidden dark:block text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
          <svg class="w-4 h-4 block dark:hidden text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </span>
      </button>

      <span class="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-800 shrink-0" aria-hidden="true"></span>

      <?php
        $headerProfileCompact = false;
        require __DIR__ . '/partials/header_profile_menu.php';
      ?>
    </div>
  </div>
</header>
<?php endif; ?>

<main class="page-body">
