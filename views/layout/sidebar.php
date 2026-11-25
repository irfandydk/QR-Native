<?php
/**
 * QR-SAR - Tactical Navigation Rail (Sidebar)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Profil pengguna & kontrol sesi dipindahkan ke header kanan atas (views/layout/navbar.php)
 * agar rail navigasi bersih, terstruktur, dan fokus pada alur operasional SAR.
 */

declare(strict_types=1);

$currentRoute = $currentRoute ?? 'dashboard';
$currentUser = $currentUser ?? AuthHelper::getCurrentUser();
$appSettings = SettingsModel::getSettings();
$logoUrl = getAppLogoUrl();
$openOpsCount = class_exists('OperasiModel') ? OperasiModel::countOpen() : 0;
$isOperasiActive = in_array($currentRoute, ['operasi', 'operasi_detail', 'operasi_form'], true);
$isAdminRole = strtoupper((string)($currentUser['role'] ?? 'USER')) === 'ADMIN';
$isGisRoute = ($currentRoute === 'gis');
?>

<!-- Mobile Backdrop -->
<div id="sidebarMobileBackdrop" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-xs lg:hidden transition-opacity hidden" aria-hidden="true"></div>

<!-- Main Sidebar Navigation Rail -->
<aside id="mainAppSidebar" aria-label="Bilah navigasi samping" class="sidebar fixed top-0 bottom-0 left-0 z-40 flex flex-col transition-all duration-300 ease-in-out border-r bg-white dark:bg-slate-900 border-slate-200/90 dark:border-slate-800 text-slate-800 dark:text-slate-100">

  <!-- Brand Header (h-16 / 64px sejajar presisi dengan top-navbar) -->
  <div class="sidebar-brand-header relative h-16 px-4 flex items-center justify-between gap-2 border-b border-inherit shrink-0">
    <a href="<?= BASE_URL ?>/index.php" class="sidebar-brand-link flex items-center gap-3 min-w-0 cursor-pointer group focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-orange-500 rounded-xl" title="Dashboard Utama KPP Kendari">
      <span class="w-9 h-9 rounded-xl bg-slate-100/90 dark:bg-slate-800/90 border border-slate-200/80 dark:border-slate-700/70 flex items-center justify-center shrink-0 p-1 transition-transform duration-200 group-hover:scale-[1.03]">
        <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" width="28" height="28" class="w-7 h-7 object-contain" onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'" />
      </span>
      <div class="sidebar-brand-text min-w-0 leading-tight">
        <span class="font-bold text-[12.5px] tracking-tight text-slate-900 dark:text-white block truncate">
          KPP KENDARI
        </span>
        <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400 block truncate mt-0.5">
          Sistem QR-SAR
        </span>
      </div>
    </a>

    <!-- Mobile Close Button (< lg) / Desktop Edge-Docked Collapse Toggle (>= lg) -->
    <button
      type="button"
      id="sidebarToggleCollapseBtn"
      class="sidebar-collapse-btn flex items-center justify-center w-7 h-7 rounded-lg border text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors cursor-pointer border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-900 shadow-xs shrink-0"
      title="Ciutkan / Perluas Sidebar"
      aria-label="Ciutkan atau perluas bilah navigasi"
      aria-expanded="true"
    >
      <span class="lg:hidden" aria-hidden="true">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </span>
      <span class="hidden lg:inline-flex items-center justify-center" id="collapseChevronIcon" aria-hidden="true">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
      </span>
    </button>
  </div>

  <!-- Primary Navigation Area -->
  <nav id="sidebarNavArea" aria-label="Menu utama sistem" class="relative flex-1 overflow-y-auto px-3 py-4 space-y-5 scrollbar-thin">

    <!-- Cluster 1: Operasional SAR -->
    <div class="space-y-1">
      <p class="sidebar-section-header px-2.5 pb-1 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500">
        Operasional
      </p>

      <!-- 1. Dashboard -->
      <a
        href="<?= BASE_URL ?>/index.php"
        title="Dashboard"
        <?= $currentRoute === 'dashboard' ? 'aria-current="page"' : '' ?>
        class="sidebar-nav-item w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[13px] transition-all group relative cursor-pointer <?= $currentRoute === 'dashboard' ? 'sidebar-active font-semibold' : 'font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-white' ?>"
      >
        <svg class="sidebar-nav-icon w-[18px] h-[18px] shrink-0 transition-colors <?= $currentRoute === 'dashboard' ? '' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200' ?>" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="7" height="9" x="3" y="3" rx="1.5"/><rect width="7" height="5" x="14" y="3" rx="1.5"/><rect width="7" height="9" x="14" y="12" rx="1.5"/><rect width="7" height="5" x="3" y="16" rx="1.5"/></svg>
        <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
          <span class="truncate">Dashboard</span>
        </div>
        <div class="sidebar-tooltip" role="tooltip">Dashboard</div>
      </a>

      <!-- 2. Operasi SAR -->
      <a
        href="<?= BASE_URL ?>/operasi.php"
        title="Operasi SAR<?= $openOpsCount > 0 ? " ({$openOpsCount} Aktif)" : '' ?>"
        <?= $isOperasiActive ? 'aria-current="page"' : '' ?>
        class="sidebar-nav-item w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[13px] transition-all group relative cursor-pointer <?= $isOperasiActive ? 'sidebar-active font-semibold' : 'font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-white' ?>"
      >
        <span class="relative shrink-0 inline-flex items-center justify-center">
          <svg class="sidebar-nav-icon w-[18px] h-[18px] transition-colors <?= $isOperasiActive ? '' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200' ?>" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
          <?php if ($openOpsCount > 0): ?>
            <span class="hidden sidebar-collapsed-dot absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-slate-900" aria-hidden="true"></span>
          <?php endif; ?>
        </span>
        <div class="sidebar-item-label flex-1 flex items-center justify-between gap-2 min-w-0 text-left">
          <span class="truncate">Operasi SAR</span>
          <?php if ($openOpsCount > 0): ?>
            <span class="text-[11px] font-mono font-semibold tabular-nums px-1.5 py-0.5 leading-none rounded-md bg-emerald-500/12 text-emerald-700 dark:text-emerald-400 shrink-0" title="<?= $openOpsCount ?> Operasi SAR Aktif">
              <?= $openOpsCount ?>
            </span>
          <?php endif; ?>
        </div>
        <div class="sidebar-tooltip" role="tooltip">Operasi SAR<?= $openOpsCount > 0 ? " ({$openOpsCount} Aktif)" : '' ?></div>
      </a>

      <!-- 3. Ploting Peta SAR -->
      <a
        href="<?= BASE_URL ?>/gis.php"
        title="Ploting Peta SAR"
        <?= $currentRoute === 'gis' ? 'aria-current="page"' : '' ?>
        class="sidebar-nav-item w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[13px] transition-all group relative cursor-pointer <?= $currentRoute === 'gis' ? 'sidebar-active font-semibold' : 'font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-white' ?>"
      >
        <svg class="sidebar-nav-icon w-[18px] h-[18px] shrink-0 transition-colors <?= $currentRoute === 'gis' ? '' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200' ?>" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="2" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="22"/></svg>
        <div class="sidebar-item-label flex-1 flex items-center justify-between gap-2 min-w-0 text-left">
          <span class="truncate">Ploting Peta SAR</span>
          <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 leading-none rounded-md bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 shrink-0">GIS</span>
        </div>
        <div class="sidebar-tooltip" role="tooltip">Ploting Peta SAR</div>
      </a>

      <!-- 4. BRA SAR -->
      <a
        href="<?= BASE_URL ?>/bra.php"
        title="BRA SAR"
        <?= $currentRoute === 'bra' ? 'aria-current="page"' : '' ?>
        class="sidebar-nav-item w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[13px] transition-all group relative cursor-pointer <?= $currentRoute === 'bra' ? 'sidebar-active font-semibold' : 'font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-white' ?>"
      >
        <svg class="sidebar-nav-icon w-[18px] h-[18px] shrink-0 transition-colors <?= $currentRoute === 'bra' ? '' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200' ?>" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
        <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
          <span class="truncate">BRA SAR</span>
        </div>
        <div class="sidebar-tooltip" role="tooltip">BRA SAR</div>
      </a>

      <!-- 5. Dokumentasi -->
      <a
        href="<?= BASE_URL ?>/dokumentasi.php"
        title="Dokumentasi"
        <?= $currentRoute === 'dokumentasi' ? 'aria-current="page"' : '' ?>
        class="sidebar-nav-item w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[13px] transition-all group relative cursor-pointer <?= $currentRoute === 'dokumentasi' ? 'sidebar-active font-semibold' : 'font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-white' ?>"
      >
        <svg class="sidebar-nav-icon w-[18px] h-[18px] shrink-0 transition-colors <?= $currentRoute === 'dokumentasi' ? '' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200' ?>" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
        <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
          <span class="truncate">Dokumentasi</span>
        </div>
        <div class="sidebar-tooltip" role="tooltip">Dokumentasi</div>
      </a>
    </div>

    <!-- Cluster 2: Data & Pengaturan -->
    <div class="pt-4 border-t border-slate-200/80 dark:border-slate-800/80 space-y-1">
      <p class="sidebar-section-header px-2.5 pb-1 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-slate-400 dark:text-slate-500">
        Sistem
      </p>

      <!-- 6. Master Data -->
      <a
        href="<?= BASE_URL ?>/master.php"
        title="Master Data"
        <?= ($currentRoute === 'master') ? 'aria-current="page"' : '' ?>
        class="sidebar-nav-item w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[13px] transition-all group relative cursor-pointer <?= ($currentRoute === 'master') ? 'sidebar-active font-semibold' : 'font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-white' ?>"
      >
        <svg class="sidebar-nav-icon w-[18px] h-[18px] shrink-0 transition-colors <?= ($currentRoute === 'master') ? '' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200' ?>" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
        <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
          <span class="truncate">Master Data</span>
        </div>
        <div class="sidebar-tooltip" role="tooltip">Master Data</div>
      </a>

      <!-- 7. Pengaturan Admin (Role !== USER) -->
      <?php if (($currentUser['role'] ?? '') !== 'USER'): ?>
        <a
          href="<?= BASE_URL ?>/admin.php"
          title="Pengaturan Admin"
          <?= ($currentRoute === 'admin') ? 'aria-current="page"' : '' ?>
          class="sidebar-nav-item w-full flex items-center gap-3 px-2.5 py-2.5 rounded-xl text-[13px] transition-all group relative cursor-pointer <?= ($currentRoute === 'admin') ? 'sidebar-active font-semibold' : 'font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100/80 dark:hover:bg-slate-800/70 hover:text-slate-900 dark:hover:text-white' ?>"
        >
          <svg class="sidebar-nav-icon w-[18px] h-[18px] shrink-0 transition-colors <?= ($currentRoute === 'admin') ? '' : 'text-slate-400 dark:text-slate-500 group-hover:text-slate-700 dark:group-hover:text-slate-200' ?>" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
          <div class="sidebar-item-label flex-1 flex items-center justify-between gap-2 min-w-0 text-left">
            <span class="truncate">Pengaturan Admin</span>
            <span class="text-[10px] font-mono font-semibold px-1.5 py-0.5 leading-none rounded-md bg-orange-500/12 text-orange-600 dark:text-orange-400 shrink-0">ADMIN</span>
          </div>
          <div class="sidebar-tooltip" role="tooltip">Pengaturan Admin</div>
        </a>
      <?php endif; ?>
    </div>
  </nav>

  <!-- Clean Operational Status Footer (Profil & Sesi kini berada di Header Kanan Atas) -->
  <div class="p-3 border-t border-inherit shrink-0 space-y-2">
    <!-- Expanded Status Dock: Live WITA + Darurat 115 + Aksen Warna -->
    <div class="sidebar-clock-full rounded-xl border border-slate-200/80 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/40 p-2.5 space-y-2">
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2 min-w-0" title="Waktu Indonesia Tengah (WITA)">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
          <span id="sidebarWitaTime" class="font-mono text-[11.5px] font-semibold tabular-nums text-slate-700 dark:text-slate-200 truncate">--:--:-- WITA</span>
        </div>

        <a
          href="tel:115"
          class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-red-500/12 text-red-600 dark:text-red-400 font-mono font-bold text-[11px] hover:bg-red-500/20 transition-colors shrink-0"
          title="Panggil Call Center Darurat 115"
        >
          <svg class="w-3 h-3" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <span>115</span>
        </a>
      </div>

      <!-- Accent Palette Switcher (otomatis disembunyikan pada tema Minimal Glass) -->
      <div class="relative sidebar-palette-row">
        <button
          type="button"
          id="sidebarPaletteBtn"
          aria-expanded="false"
          aria-controls="sidebarPalettePopover"
          class="w-full flex items-center justify-between gap-2 px-2 py-1.5 rounded-lg border text-[11px] font-medium transition-colors cursor-pointer bg-white hover:bg-slate-100 text-slate-600 border-slate-200/90 dark:bg-slate-900/80 dark:hover:bg-slate-800 dark:text-slate-300 dark:border-slate-700/80"
          title="Ganti Warna Aksen"
        >
          <span class="flex items-center gap-2 min-w-0">
            <span id="sidebarActiveSwatch" class="w-2.5 h-2.5 rounded-full shrink-0" style="background-color: var(--primary);" aria-hidden="true"></span>
            <span class="sidebar-ctrl-label truncate">Warna Aksen</span>
          </span>
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m18 15-6-6-6 6"/></svg>
        </button>

        <div id="sidebarPalettePopover" class="hidden absolute bottom-full left-0 right-0 mb-2 p-1.5 rounded-xl border shadow-xl z-50 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100">
          <p class="px-2 py-1 text-[10.5px] font-semibold uppercase tracking-wider text-slate-400">
            Warna Aksen
          </p>
          <button type="button" data-palette="orange" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-xs transition-colors cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
            <span class="flex items-center gap-2 min-w-0">
              <span class="w-2.5 h-2.5 rounded-full bg-orange-500 shrink-0"></span>
              <span class="truncate">Basarnas Orange</span>
            </span>
            <span class="palette-check hidden" aria-hidden="true"><svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
          </button>
          <button type="button" data-palette="navy" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-xs transition-colors cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
            <span class="flex items-center gap-2 min-w-0">
              <span class="w-2.5 h-2.5 rounded-full bg-blue-600 shrink-0"></span>
              <span class="truncate">Operasi Laut (Navy)</span>
            </span>
            <span class="palette-check hidden" aria-hidden="true"><svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
          </button>
          <button type="button" data-palette="emerald" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-xs transition-colors cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
            <span class="flex items-center gap-2 min-w-0">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-600 shrink-0"></span>
              <span class="truncate">SAR Hutan (Emerald)</span>
            </span>
            <span class="palette-check hidden" aria-hidden="true"><svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
          </button>
          <button type="button" data-palette="gold" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-xs transition-colors cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800">
            <span class="flex items-center gap-2 min-w-0">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-500 shrink-0"></span>
              <span class="truncate">Taktis Emas (Gold)</span>
            </span>
            <span class="palette-check hidden" aria-hidden="true"><svg class="w-3.5 h-3.5 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
          </button>
        </div>
      </div>
    </div>

    <!-- Collapsed Mini Emergency Dock -->
    <div class="sidebar-clock-mini flex flex-col items-center gap-2" id="sidebarClockMini" title="Darurat 115">
      <a href="tel:115" class="w-9 h-9 rounded-xl bg-red-500/12 text-red-600 dark:text-red-400 flex items-center justify-center hover:bg-red-500/20 transition-colors" title="Panggil Call Center 115" aria-label="Panggil Call Center Darurat 115">
        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </a>
    </div>
  </div>
</aside>

<!-- Main App Content Container (Balanced in footer.php) -->
<div class="main-content">

<!-- Interactive Sidebar Script -->
<script>
(function() {
  const sidebar = document.getElementById('mainAppSidebar');
  const mainContent = document.querySelector('.main-content');
  const backdrop = document.getElementById('sidebarMobileBackdrop');
  const toggleBtn = document.getElementById('sidebarToggleCollapseBtn');
  const chevronIcon = document.getElementById('collapseChevronIcon');

  // 1. Live WITA Clock
  function updateWita() {
    const el = document.getElementById('sidebarWitaTime');
    const mini = document.getElementById('sidebarClockMini');
    try {
      const now = new Date();
      const timeStr = new Intl.DateTimeFormat('id-ID', {
        timeZone: 'Asia/Makassar',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
      }).format(now);
      if (el) el.textContent = timeStr + ' WITA';
      if (mini) mini.title = `WITA: ${timeStr} | Darurat: 115`;
    } catch(e) {}
  }
  setInterval(updateWita, 1000);
  updateWita();

  // 2. Collapse / Expand Sidebar State (Saved in LocalStorage)
  function applyCollapseState(isCollapsed) {
    if (!sidebar) return;
    if (isCollapsed) {
      sidebar.classList.add('collapsed');
      if (mainContent) mainContent.classList.add('sidebar-collapsed');
      document.documentElement.classList.add('sidebar-is-collapsed');
      if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
      if (chevronIcon) {
        chevronIcon.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>`;
      }
    } else {
      sidebar.classList.remove('collapsed');
      if (mainContent) mainContent.classList.remove('sidebar-collapsed');
      document.documentElement.classList.remove('sidebar-is-collapsed');
      if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
      if (chevronIcon) {
        chevronIcon.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>`;
      }
    }
  }

  const savedCollapse = localStorage.getItem('qr_sar_sidebar_collapsed') === 'true';
  applyCollapseState(savedCollapse);

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (window.innerWidth < 1024) {
        sidebar.classList.remove('open');
        if (backdrop) backdrop.classList.add('hidden');
      } else {
        const nowCollapsed = !sidebar.classList.contains('collapsed');
        applyCollapseState(nowCollapsed);
        localStorage.setItem('qr_sar_sidebar_collapsed', nowCollapsed ? 'true' : 'false');
      }
    });
  }

  // Mobile menu drawer toggle (tombol ada di navbar.php)
  if (sidebar && backdrop) {
    document.addEventListener('click', (e) => {
      if (e.target.closest && e.target.closest('#mobile-menu-btn')) {
        sidebar.classList.add('open');
        backdrop.classList.remove('hidden');
      }
    });
    backdrop.addEventListener('click', () => {
      sidebar.classList.remove('open');
      backdrop.classList.add('hidden');
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) {
        sidebar.classList.remove('open');
        backdrop.classList.add('hidden');
      }
    });
  }

  // 3. Color Palette Picker Popover
  const paletteBtn = document.getElementById('sidebarPaletteBtn');
  const palettePopover = document.getElementById('sidebarPalettePopover');

  function updatePaletteUI(activePal) {
    document.querySelectorAll('.palette-choice').forEach(btn => {
      const p = btn.getAttribute('data-palette');
      const check = btn.querySelector('.palette-check');
      if (p === activePal) {
        btn.classList.add('bg-orange-500/15', 'text-orange-600', 'dark:text-orange-400', 'font-bold');
        if (check) check.classList.remove('hidden');
      } else {
        btn.classList.remove('bg-orange-500/15', 'text-orange-600', 'dark:text-orange-400', 'font-bold');
        if (check) check.classList.add('hidden');
      }
    });
  }

  const currentPalette = localStorage.getItem('qr_sar_palette') || document.documentElement.getAttribute('data-palette') || 'orange';
  updatePaletteUI(currentPalette);

  if (paletteBtn && palettePopover) {
    paletteBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const willOpen = palettePopover.classList.contains('hidden');
      palettePopover.classList.toggle('hidden');
      paletteBtn.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });

    document.querySelectorAll('.palette-choice').forEach(btn => {
      btn.addEventListener('click', () => {
        const p = btn.getAttribute('data-palette');
        if (typeof window.setAppPalette === 'function') {
          window.setAppPalette(p, true);
        } else {
          document.documentElement.setAttribute('data-palette', p);
          localStorage.setItem('qr_sar_palette', p);
          updatePaletteUI(p);
        }
        palettePopover.classList.add('hidden');
        paletteBtn.setAttribute('aria-expanded', 'false');
      });
    });

    document.addEventListener('click', (e) => {
      if (!palettePopover.contains(e.target) && !paletteBtn.contains(e.target)) {
        palettePopover.classList.add('hidden');
        paletteBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
</script>
