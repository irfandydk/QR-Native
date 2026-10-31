<?php
/**
 * QR-SAR - Tactical Sidebar Navigation
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference src/components/Sidebar.tsx
 */

declare(strict_types=1);

$currentRoute = $currentRoute ?? 'dashboard';
$currentUser = $currentUser ?? AuthHelper::getCurrentUser();
$appSettings = SettingsModel::getSettings();
$logoUrl = getAppLogoUrl();
?>

<!-- Mobile Backdrop -->
<div id="sidebarMobileBackdrop" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden transition-opacity hidden"></div>

<!-- Main Sidebar Container -->
<aside id="mainAppSidebar" class="sidebar fixed top-0 bottom-0 left-0 z-40 flex flex-col transition-all duration-300 ease-in-out border-r bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 shadow-sm">
  
  <!-- Brand Header + The SINGLE Minimize/Toggle Button -->
  <div class="sidebar-brand-header h-16 px-3.5 flex items-center justify-between border-b border-inherit shrink-0">
    <a href="<?= BASE_URL ?>/index.php" class="flex items-center gap-2.5 overflow-hidden cursor-pointer group" title="Dashboard Utama KPP Kendari">
      <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" width="34" height="34" class="shrink-0 drop-shadow-sm group-hover:scale-105 transition-transform" onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'" />
      <div class="sidebar-brand-text min-w-0 transition-opacity duration-200">
        <span class="font-semibold text-xs tracking-wider text-orange-600 dark:text-orange-500 block truncate">
          KPP KENDARI
        </span>
        <span class="text-[11px] text-slate-500 dark:text-slate-400 block truncate font-medium">
          Sistem QR SAR Digital
        </span>
      </div>
    </a>

    <!-- Minimize/Collapse Button on Desktop, Close Button on Mobile -->
    <button type="button" id="sidebarToggleCollapseBtn" class="flex items-center justify-center w-7 h-7 rounded-lg border text-slate-400 hover:text-slate-200 transition-colors cursor-pointer border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800/60" title="Ciutkan / Perluas Sidebar">
      <span class="lg:hidden">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
      </span>
      <span class="hidden lg:inline" id="collapseChevronIcon">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
      </span>
    </button>
  </div>

  <!-- Navigation Items Area -->
  <div id="sidebarNavArea" class="relative flex-1 overflow-y-auto px-2.5 py-4 space-y-1.5 scrollbar-thin">
    <p class="sidebar-section-header px-3 pb-1 text-[11px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
      Navigasi Sistem
    </p>

    <!-- 1. Dashboard Operasi -->
    <a href="<?= BASE_URL ?>/index.php" title="Dashboard Operasi" class="sidebar-nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all group relative cursor-pointer <?= in_array($currentRoute, ['dashboard', 'operasi', 'operasi_detail', 'operasi_form']) ? 'sidebar-active bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-500/30 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 hover:text-slate-900 dark:hover:text-white' ?>">
      <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110 <?= in_array($currentRoute, ['dashboard', 'operasi', 'operasi_detail', 'operasi_form']) ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400' ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
      <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
        <span class="truncate">Dashboard Operasi</span>
      </div>
      <div class="sidebar-tooltip absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-[11px] font-medium rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap shadow-lg border border-slate-700">
        Dashboard Operasi
      </div>
    </a>

    <!-- 2. Ploting Peta SAR -->
    <a href="<?= BASE_URL ?>/gis.php" title="Ploting Peta SAR" class="sidebar-nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all group relative cursor-pointer <?= $currentRoute === 'gis' ? 'sidebar-active bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-500/30 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 hover:text-slate-900 dark:hover:text-white' ?>">
      <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110 <?= $currentRoute === 'gis' ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400' ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="2" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="22"/></svg>
      <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
        <span class="truncate">Ploting Peta SAR</span>
        <span class="text-[11px] font-bold px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">GIS</span>
      </div>
      <div class="sidebar-tooltip absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-[11px] font-medium rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap shadow-lg border border-slate-700">
        Ploting Peta SAR
      </div>
    </a>

    <!-- 3. BRA SAR -->
    <a href="<?= BASE_URL ?>/bra.php" title="BRA SAR" class="sidebar-nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all group relative cursor-pointer <?= $currentRoute === 'bra' ? 'sidebar-active bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-500/30 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 hover:text-slate-900 dark:hover:text-white' ?>">
      <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110 <?= $currentRoute === 'bra' ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400' ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
      <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
        <span class="truncate">BRA SAR</span>
      </div>
      <div class="sidebar-tooltip absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-[11px] font-medium rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap shadow-lg border border-slate-700">
        BRA SAR
      </div>
    </a>

    <!-- 4. Master Data -->
    <a href="<?= BASE_URL ?>/master.php" title="Master Data" class="sidebar-nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all group relative cursor-pointer <?= ($currentRoute === 'master') ? 'sidebar-active bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-500/30 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 hover:text-slate-900 dark:hover:text-white' ?>">
      <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110 <?= ($currentRoute === 'master') ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400' ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
      <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
        <span class="truncate">Master Data</span>
      </div>
      <div class="sidebar-tooltip absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-[11px] font-medium rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap shadow-lg border border-slate-700">
        Master Data
      </div>
    </a>

    <!-- 5. Pengaturan Admin (Role !== USER) -->
    <?php if (($currentUser['role'] ?? '') !== 'USER'): ?>
      <a href="<?= BASE_URL ?>/admin.php" title="Pengaturan Admin" class="sidebar-nav-item w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all group relative cursor-pointer <?= ($currentRoute === 'admin') ? 'sidebar-active bg-orange-50 dark:bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-200 dark:border-orange-500/30 font-bold' : 'text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800/80 hover:text-slate-900 dark:hover:text-white' ?>">
        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110 <?= ($currentRoute === 'admin') ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400' ?>" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
        <div class="sidebar-item-label flex-1 flex items-center justify-between min-w-0 text-left">
          <span class="truncate">Pengaturan Admin</span>
          <span class="text-[11px] font-bold px-1.5 py-0.2 rounded-full bg-orange-500/20 text-orange-600 dark:text-orange-400 border border-orange-500/30">ADMIN</span>
        </div>
        <div class="sidebar-tooltip absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-[11px] font-medium rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap shadow-lg border border-slate-700">
          Pengaturan Admin
        </div>
      </a>
    <?php endif; ?>
  </div>

  <!-- Unified System Controls & User Footer -->
  <div class="p-2 border-t border-inherit shrink-0 space-y-1.5">
    <!-- Live Clock & Quick Utility Dock -->
    <div class="sidebar-clock-full px-2 py-1.5 rounded-xl border text-[11px] flex items-center justify-between transition-colors bg-slate-50 dark:bg-slate-800/60 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300">
      <div class="flex items-center gap-1.5 font-mono text-[11px] min-w-0" title="Waktu Indonesia Tengah">
        <svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span id="sidebarWitaTime" class="truncate font-bold">--:--:-- WITA</span>
      </div>
      <div class="flex items-center gap-1 shrink-0">
        <!-- Emergency Call 115 -->
        <a href="tel:115" class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-lg bg-red-500/10 text-red-500 font-bold text-[11px] hover:bg-red-500/20 transition-colors shrink-0" title="Panggil Call Center 115">
          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
          <span>115</span>
        </a>


        <!-- Palette Dropdown Popover Button -->
        <div class="relative">
          <button type="button" id="sidebarPaletteBtn" class="p-1 rounded-lg border transition-colors cursor-pointer bg-white hover:bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700" title="Ganti Tema Warna">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
            <span class="sidebar-ctrl-label hidden">Tema</span>
          </button>

          <!-- Theme Picker Popover Menu -->
          <div id="sidebarPalettePopover" class="hidden absolute bottom-full right-0 mb-2 w-48 p-1.5 rounded-xl border shadow-xl z-50 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-slate-100">
            <p class="px-2 py-1 text-[11px] font-bold uppercase tracking-wider text-slate-400">
              Warna Aksen
            </p>
            <button type="button" data-palette="orange" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-[11px] transition-colors cursor-pointer">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
                <span class="truncate">Basarnas Orange</span>
              </div>
              <span class="palette-check hidden"><svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            </button>
            <button type="button" data-palette="navy" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-[11px] transition-colors cursor-pointer">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-blue-600"></span>
                <span class="truncate">Operasi Laut (Navy)</span>
              </div>
              <span class="palette-check hidden"><svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            </button>
            <button type="button" data-palette="emerald" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-[11px] transition-colors cursor-pointer">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-600"></span>
                <span class="truncate">SAR Hutan (Emerald)</span>
              </div>
              <span class="palette-check hidden"><svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            </button>
            <button type="button" data-palette="gold" class="palette-choice w-full flex items-center justify-between p-1.5 rounded-lg text-left text-[11px] transition-colors cursor-pointer">
              <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                <span class="truncate">Taktis Emas (Gold)</span>
              </div>
              <span class="palette-check hidden"><svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg></span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Collapsed Mini Clock / Call 115 -->
    <div class="sidebar-clock-mini flex justify-center" id="sidebarClockMini" title="Emergency 115">
      <a href="tel:115" class="w-8 h-8 rounded-lg bg-red-500/10 text-red-500 flex items-center justify-center hover:bg-red-500/20 transition-colors" title="Panggil Call Center 115">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      </a>
    </div>

    <!-- User Session & Logout -->
    <div class="sidebar-footer-user flex items-center gap-2 p-1.5 rounded-xl bg-slate-100/70 dark:bg-slate-800/50">
      <a href="<?= BASE_URL ?>/profile.php" class="w-7 h-7 rounded-lg user-avatar-badge bg-orange-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs hover:opacity-90 transition cursor-pointer" title="Kelola Profil & Kata Sandi">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </a>
      <a href="<?= BASE_URL ?>/profile.php" class="sidebar-user-block min-w-0 flex-1 hover:opacity-80 transition cursor-pointer block" title="Kelola Profil & Kata Sandi">
        <div class="flex items-center gap-1.5">
          <p class="text-xs font-bold truncate text-slate-800 dark:text-slate-100">
            <?= e($currentUser['nama'] ?? $currentUser['username'] ?? 'Pengguna') ?>
          </p>
          <span class="text-[11px] font-bold uppercase px-1 py-0.5 rounded <?= ($currentUser['role'] ?? '') === 'USER' ? 'bg-blue-500/20 text-blue-500 dark:text-blue-400 border border-blue-500/30' : 'bg-orange-500/20 text-orange-600 dark:text-orange-400 border border-orange-500/30' ?>">
            <?= ($currentUser['role'] ?? '') === 'USER' ? 'User' : 'Admin' ?>
          </span>
        </div>
        <p class="text-[11px] text-slate-400 truncate">
          <?= e($currentUser['jabatan'] ?? (($currentUser['role'] ?? '') === 'USER' ? 'Operator SAR Kendari' : 'Admin SAR Kendari')) ?>
        </p>
      </a>
      <form action="<?= BASE_URL ?>/logout.php" method="POST" class="inline m-0 p-0 shrink-0">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <button type="submit" data-confirm="Apakah Anda yakin ingin keluar dari sistem QR-SAR?" data-confirm-title="Konfirmasi Keluar Sesi" data-confirm-btn="Ya, Keluar" class="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer shrink-0 border-0 bg-transparent flex items-center justify-center" title="Keluar dari sistem">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
        </button>
      </form>
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
      if (mini) mini.title = `WITA: ${timeStr} | Emergency: 115`;
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
      if (chevronIcon) {
        chevronIcon.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>`;
      }
    } else {
      sidebar.classList.remove('collapsed');
      if (mainContent) mainContent.classList.remove('sidebar-collapsed');
      document.documentElement.classList.remove('sidebar-is-collapsed');
      if (chevronIcon) {
        chevronIcon.innerHTML = `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>`;
      }
    }
  }

  const savedCollapse = localStorage.getItem('qr_sar_sidebar_collapsed') === 'true';
  applyCollapseState(savedCollapse);

  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      if (window.innerWidth < 1024) {
        // Mobile close
        sidebar.classList.remove('open');
        backdrop.classList.add('hidden');
      } else {
        // Desktop toggle
        const nowCollapsed = !sidebar.classList.contains('collapsed');
        applyCollapseState(nowCollapsed);
        localStorage.setItem('qr_sar_sidebar_collapsed', nowCollapsed ? 'true' : 'false');
      }
    });
  }

  // Mobile menu button hook (delegasi: tombol ada di navbar yang dirender setelah sidebar)
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
  }

  // 3. Theme State Display in Sidebar
  const themeIcon = document.getElementById('sidebarThemeIcon');
  const themeLabel = document.getElementById('sidebarThemeLabel');

  function updateSidebarThemeUI(theme) {
    if (themeIcon) {
      if (theme === 'dark') {
        themeIcon.innerHTML = `<svg class="w-3.5 h-3.5 text-amber-300" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.93 4.93 1.41 1.41"/><path d="m17.66 17.66 1.41 1.41"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m6.34 17.66-1.41 1.41"/><path d="m19.07 4.93-1.41 1.41"/></svg>`;
      } else {
        themeIcon.innerHTML = `<svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>`;
      }
    }
    if (themeLabel) {
      themeLabel.textContent = theme === 'dark' ? 'Terang' : 'Gelap';
    }
  }

  const currentTheme = localStorage.getItem('qr_sar_theme') || document.documentElement.getAttribute('data-theme') || 'dark';
  updateSidebarThemeUI(currentTheme);

  // React to theme changes emitted globally by app.js (no duplicate click listener)
  window.addEventListener('themeChanged', (e) => {
    if (e && e.detail && e.detail.theme) {
      updateSidebarThemeUI(e.detail.theme);
    }
  });

  // 4. Color Palette Picker Popover
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
      palettePopover.classList.toggle('hidden');
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
      });
    });

    document.addEventListener('click', (e) => {
      if (!palettePopover.contains(e.target) && !paletteBtn.contains(e.target)) {
        palettePopover.classList.add('hidden');
      }
    });
  }
})();
</script>

