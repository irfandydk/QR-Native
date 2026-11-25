<?php
/**
 * QR-SAR - Layout Top Header & Top-Right User Profile Popover
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Tinggi 64px (h-16) sejajar presisi dengan brand header sidebar.
 * Panel akun di sudut kanan atas menggunakan Popover API + CSS Anchor Positioning
 * (dengan fallback viewport-clamped untuk browser tanpa position-area).
 */

declare(strict_types=1);

$headerTitle = $navbarTitle ?? $pageTitle ?? 'DASHBOARD OPERASI SAR';
$headerTitle = preg_replace('/\s*-\s*QR SAR.*$/i', '', (string)$headerTitle);
$headerSubtitle = $navbarSubtitle ?? 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI · BASARNAS';

$navUser = $currentUser ?? AuthHelper::getCurrentUser();
$navUserRole = strtoupper((string)($navUser['role'] ?? 'USER'));
$navIsAdmin = ($navUserRole === 'ADMIN');
$navUserName = trim((string)($navUser['nama'] ?? $navUser['username'] ?? 'Pengguna'));
$navUsername = trim((string)($navUser['username'] ?? ''));
$navUserJabatan = trim((string)($navUser['jabatan'] ?? ($navIsAdmin ? 'Administrator Sistem SAR' : 'Operator Siaga SAR')));
$navAvatarUrl = class_exists('UserModel') ? UserModel::resolveAvatarDisplayUrl((string)($navUser['avatar_url'] ?? '')) : '';
$navInitial = strtoupper(mb_substr($navUserName !== '' ? $navUserName : ($navUsername !== '' ? $navUsername : 'U'), 0, 1, 'UTF-8'));
$navRoleLabel = $navIsAdmin ? 'Admin' : 'User';
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
      <!-- Theme Toggle Button -->
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

      <!-- Top-Right Account Profile Trigger (Popover Button Group) -->
      <div class="relative flex items-center">
        <button
          type="button"
          id="headerProfileBtn"
          popovertarget="headerProfilePopover"
          popovertargetaction="toggle"
          aria-controls="headerProfilePopover"
          aria-expanded="false"
          class="header-profile-trigger inline-flex items-center gap-2.5 pl-1.5 pr-2 sm:pr-2.5 py-1 rounded-xl border border-slate-200/90 dark:border-slate-800 bg-slate-50/70 hover:bg-slate-100 dark:bg-slate-800/60 dark:hover:bg-slate-800 transition-colors cursor-pointer text-left max-w-[240px]"
          title="Akun: <?= e($navUserName) ?>"
        >
          <span class="header-avatar-box w-8 h-8 rounded-lg overflow-hidden shrink-0 flex items-center justify-center font-bold text-xs text-white ring-1 ring-black/5 dark:ring-white/10" style="background-color: var(--primary);">
            <?php if ($navAvatarUrl !== ''): ?>
              <img src="<?= e($navAvatarUrl) ?>" alt="Foto profil <?= e($navUserName) ?>" class="w-full h-full object-cover" />
            <?php else: ?>
              <span><?= e($navInitial) ?></span>
            <?php endif; ?>
          </span>

          <span class="hidden sm:flex flex-col min-w-0 leading-tight">
            <span class="flex items-center gap-1.5 min-w-0">
              <span class="text-xs font-semibold text-slate-900 dark:text-slate-100 truncate max-w-[120px] md:max-w-[148px]">
                <?= e($navUserName) ?>
              </span>
              <span class="text-[9.5px] font-mono font-bold uppercase px-1.5 py-0.5 leading-none rounded shrink-0 <?= $navIsAdmin ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400' : 'bg-sky-500/15 text-sky-600 dark:text-sky-400' ?>">
                <?= e($navRoleLabel) ?>
              </span>
            </span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate max-w-[148px] mt-0.5">
              <?= e($navUserJabatan) ?>
            </span>
          </span>

          <svg id="headerProfileChevron" class="w-3.5 h-3.5 text-slate-400 shrink-0 transition-transform duration-150" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
        </button>

        <!-- Anchored Top-Layer Account Popover (ditempatkan langsung setelah trigger agar tidak memicu aria-details implisit) -->
        <div
          id="headerProfilePopover"
          popover="auto"
          class="header-profile-popover w-72 max-w-[calc(100dvw-1.5rem)] rounded-2xl border border-slate-200/90 dark:border-slate-700/80 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 shadow-xl p-2"
        >
          <!-- Account Identity Summary -->
          <div class="flex items-center gap-3 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200/70 dark:border-slate-700/50">
            <div class="w-11 h-11 rounded-xl overflow-hidden shrink-0 flex items-center justify-center font-bold text-sm text-white ring-1 ring-black/5 dark:ring-white/10" style="background-color: var(--primary);">
              <?php if ($navAvatarUrl !== ''): ?>
                <img src="<?= e($navAvatarUrl) ?>" alt="Foto profil <?= e($navUserName) ?>" class="w-full h-full object-cover" />
              <?php else: ?>
                <span><?= e($navInitial) ?></span>
              <?php endif; ?>
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <p class="text-xs font-bold text-slate-900 dark:text-white truncate"><?= e($navUserName) ?></p>
                <span class="text-[9.5px] font-mono font-bold uppercase px-1.5 py-0.5 leading-none rounded shrink-0 <?= $navIsAdmin ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400' : 'bg-sky-500/15 text-sky-600 dark:text-sky-400' ?>">
                  <?= e($navRoleLabel) ?>
                </span>
              </div>
              <?php if ($navUsername !== ''): ?>
                <p class="text-[11px] font-mono text-slate-500 dark:text-slate-400 truncate">@<?= e($navUsername) ?></p>
              <?php endif; ?>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5"><?= e($navUserJabatan) ?></p>
            </div>
          </div>

          <hr class="my-2 border-slate-200/80 dark:border-slate-800" />

          <!-- Account Navigation Actions -->
          <div class="space-y-0.5">
            <?php if ($navIsAdmin): ?>
              <a href="<?= BASE_URL ?>/admin.php?tab=admin" class="header-popover-action w-full flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span>Kelola Akun &amp; Pengguna</span>
              </a>
              <a href="<?= BASE_URL ?>/admin.php?tab=satker" class="header-popover-action w-full flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9c.26.6.85 1 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <span>Pengaturan Satker</span>
              </a>
            <?php else: ?>
              <a href="<?= BASE_URL ?>/profile.php" class="header-popover-action w-full flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                <svg class="w-4 h-4 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.85" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Profil &amp; Foto Akun</span>
              </a>
            <?php endif; ?>
          </div>

          <hr class="my-2 border-slate-200/80 dark:border-slate-800" />

          <!-- Secure POST Logout -->
          <form action="<?= BASE_URL ?>/logout.php" method="POST" class="m-0 p-0">
            <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
            <button
              type="submit"
              id="headerLogoutBtn"
              data-confirm="Apakah Anda yakin ingin keluar dari sistem QR-SAR?"
              data-confirm-title="Konfirmasi Keluar Sesi"
              data-confirm-btn="Ya, Keluar"
              data-confirm-danger="true"
              class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors cursor-pointer border-0 bg-transparent text-left"
            >
              <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
              <span>Keluar dari Sistem</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<script>
(function() {
  const trigger = document.getElementById('headerProfileBtn');
  const panel = document.getElementById('headerProfilePopover');
  const chevron = document.getElementById('headerProfileChevron');
  const logoutBtn = document.getElementById('headerLogoutBtn');
  if (!trigger || !panel) return;

  const supportsPopover = Object.prototype.hasOwnProperty.call(HTMLElement.prototype, 'popover');
  const supportsAnchor = typeof CSS !== 'undefined' && CSS.supports && CSS.supports('position-area', 'block-end');

  function positionFallback() {
    if (supportsAnchor) return;
    const rect = trigger.getBoundingClientRect();
    const panelRect = panel.getBoundingClientRect();
    const pw = panelRect.width || 288;
    const ph = panelRect.height || 240;
    const pad = 12;
    let top = rect.bottom + 8;
    if (top + ph > window.innerHeight - pad && rect.top - ph - 8 >= pad) {
      top = rect.top - ph - 8;
    }
    let left = rect.right - pw;
    left = Math.max(pad, Math.min(left, window.innerWidth - pw - pad));
    panel.style.top = Math.round(top) + 'px';
    panel.style.left = Math.round(left) + 'px';
  }

  function syncExpanded(isOpen) {
    trigger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (chevron) chevron.style.transform = isOpen ? 'rotate(180deg)' : '';
  }

  if (supportsPopover) {
    panel.addEventListener('toggle', function(e) {
      const open = e.newState === 'open';
      syncExpanded(open);
      if (open && !supportsAnchor) {
        requestAnimationFrame(positionFallback);
      }
    });
    window.addEventListener('resize', function() {
      if (!supportsAnchor && panel.matches(':popover-open')) {
        positionFallback();
      }
    });
  } else {
    // Offline fallback untuk browser lama tanpa Popover API
    panel.classList.add('header-popover-fallback');
    trigger.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const willOpen = !panel.classList.contains('is-open');
      panel.classList.toggle('is-open', willOpen);
      syncExpanded(willOpen);
      if (willOpen) requestAnimationFrame(positionFallback);
    });
    document.addEventListener('click', function(e) {
      if (panel.classList.contains('is-open') && !panel.contains(e.target) && !trigger.contains(e.target)) {
        panel.classList.remove('is-open');
        syncExpanded(false);
      }
    });
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && panel.classList.contains('is-open')) {
        panel.classList.remove('is-open');
        syncExpanded(false);
        trigger.focus();
      }
    });
  }

  // Tutup popover saat tombol keluar membuka dialog konfirmasi SweetAlert2
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function() {
      if (supportsPopover && panel.matches(':popover-open')) {
        try { panel.hidePopover(); } catch (_) {}
      } else {
        panel.classList.remove('is-open');
        syncExpanded(false);
      }
    });
  }
})();
</script>
<?php endif; ?>

<main class="page-body">
