<?php
/**
 * QR-SAR - Portal Login (Editorial Split Minimalis & Stabil)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Tata letak split editorial berimbang dengan aset visual Basarnas lokal
 * (100% offline-first tanpa CDN eksternal), dukungan penuh Mode Terang/Gelap,
 * 4 palet aksen, tema Klasik & Minimal Glass, serta validasi form modern.
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$error = getFlash('error');
$success = getFlash('success');
if (empty($success) && isset($_GET['logout'])) {
    $success = 'Anda telah berhasil keluar dari sesi sistem.';
}
$appSettings = SettingsModel::getSettings();
$uiTheme = (($appSettings['ui_theme'] ?? 'klasik') === 'minimal') ? 'minimal' : 'klasik';
$defaultSystemTheme = $appSettings['default_theme'] ?? 'dark';
$logoUrl = getAppLogoUrl();
$satkerNama = trim((string)($appSettings['satker_nama'] ?? 'Kantor Pencarian dan Pertolongan Kelas A Kendari'));
$loginHeroUrl = assetUrl('assets/images/auth/login-sar-hero.jpg');
?>
<!DOCTYPE html>
<html lang="id" data-ui="<?= $uiTheme ?>" data-theme="<?= e($defaultSystemTheme) ?>" class="<?= $defaultSystemTheme === 'dark' ? 'dark' : '' ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <meta name="description" content="Portal Masuk QR-SAR - <?= e($satkerNama) ?> Basarnas">
  <script>
  // Transisi antar halaman yang dibatalkan (klik cepat) bukan error: tangkap promise-nya
  ['pageswap', 'pagereveal'].forEach(function (t) {
      window.addEventListener(t, function (e) {
          if (e.viewTransition) { ["ready", "finished", "updateCallbackDone"].forEach(function (k) { if (e.viewTransition[k]) e.viewTransition[k].catch(function () {}); }); }
      });
  });
  </script>
  <title>Masuk - QR-SAR</title>
  <link rel="icon" type="image/png" href="<?= assetUrl('favicon.png') ?>">
  <link rel="shortcut icon" href="<?= assetUrl('favicon.ico') ?>">
  <link rel="apple-touch-icon" href="<?= e($logoUrl) ?>">

  <!-- Immediate Anti-Flicker Theme & Palette Script -->
  <script>
    (function() {
      var sysDefault = <?= jsonSafe($defaultSystemTheme) ?>;
      var savedTheme = localStorage.getItem('qr_sar_theme') || sysDefault || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
      var savedPalette = localStorage.getItem('qr_sar_palette') || 'orange';
      document.documentElement.setAttribute('data-palette', savedPalette);
    })();
  </script>

  <!-- SweetAlert2 CSS (Local Offline Vendor) -->
  <link rel="stylesheet" href="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">

  <!-- App Design System & Local Offline Tailwind CSS -->
  <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
  <link rel="stylesheet" href="<?= assetUrl('assets/css/tailwind.css') ?>">
  <?php if ($uiTheme === 'minimal'): ?><link rel="stylesheet" href="<?= assetUrl('assets/css/theme-minimal.css') ?>"><?php endif; ?>

  <style>
    .login-shell {
      --login-spring: cubic-bezier(0.22, 1, 0.36, 1);
      --login-input-bg: var(--bg-input, #ffffff);
      --login-input-border: var(--border-color, #cbd5e1);
      --login-invalid: #dc2626;
      --login-autofill-ring: color-mix(in srgb, var(--primary) 55%, transparent);
    }
    html.dark .login-shell,
    html[data-theme="dark"] .login-shell {
      --login-invalid: #f87171;
    }

    .login-editorial-heading {
      font-size: clamp(1.75rem, 1.15rem + 1.6vw, 2.5rem);
      line-height: 1.12;
      letter-spacing: -0.03em;
      text-wrap: balance;
    }
    .login-form-heading {
      font-size: clamp(1.375rem, 1.2rem + 0.5vw, 1.625rem);
      line-height: 1.2;
      letter-spacing: -0.025em;
      text-wrap: balance;
    }

    /* Stable, clean, full-width form controls without floating label overlap risk */
    .login-input {
      width: 100%;
      border-radius: 0.75rem;
      border: 1px solid var(--login-input-border);
      background-color: var(--login-input-bg);
      color: var(--text-main);
      padding: 0.75rem 0.9375rem 0.75rem 2.625rem;
      font-size: 0.875rem;
      line-height: 1.4;
      transition: border-color 160ms ease, box-shadow 160ms ease, background-color 160ms ease;
    }
    .login-input::placeholder {
      color: var(--text-muted);
      opacity: 0.7;
    }
    .login-input:hover {
      border-color: color-mix(in srgb, var(--primary) 40%, var(--login-input-border));
    }
    .login-input:focus-visible {
      outline: 2px solid var(--primary);
      outline-offset: 2px;
      border-color: var(--primary);
    }

    /* Post-interaction validation (:user-invalid + fallback via :is()) */
    .login-input:is(:user-invalid, .user-invalid-fallback) {
      border-color: var(--login-invalid);
      box-shadow: inset 0 0 0 1px var(--login-invalid);
    }
    .login-field-error {
      display: none;
      margin-top: 0.375rem;
      font-size: 0.75rem;
      line-height: 1.4;
      font-weight: 500;
      color: var(--login-invalid);
    }
    .login-field:has(.login-input:is(:user-invalid, .user-invalid-fallback)) .login-field-error {
      display: flex;
      align-items: center;
      gap: 0.375rem;
    }

    /* Autofill non-color ring indicator without layout shift or removing focus outline */
    .login-input:is(:autofill, :-webkit-autofill) {
      box-shadow: inset 0 0 0 2px var(--login-autofill-ring);
      border-color: var(--primary);
    }
    .login-input:is(:autofill, :-webkit-autofill):focus-visible {
      outline: 2px solid var(--primary);
      outline-offset: 3px;
    }

    /* High-contrast primary submit CTA & tactile controls */
    .login-submit-btn {
      background-color: var(--primary);
      color: #ffffff !important;
      border: 1px solid transparent;
      box-shadow: 0 1px 2px rgba(15, 23, 42, 0.14), 0 10px 22px -10px color-mix(in srgb, var(--primary) 65%, transparent);
    }
    .login-submit-btn:hover:not(:disabled) {
      background-color: var(--primary-hover);
      color: #ffffff !important;
      box-shadow: 0 2px 8px rgba(15, 23, 42, 0.16), 0 14px 26px -10px color-mix(in srgb, var(--primary) 75%, transparent);
    }
    .login-submit-btn:focus-visible,
    .login-icon-btn:focus-visible,
    .login-pass-toggle:focus-visible {
      outline: 2px solid var(--primary);
      outline-offset: 2px;
    }

    /* Subtle ambient dot grid on form side (pure CSS offline) */
    .login-form-pane {
      position: relative;
      isolation: isolate;
    }
    .login-form-pane::before {
      content: "";
      position: absolute;
      inset: 0;
      z-index: -1;
      pointer-events: none;
      background-image: radial-gradient(36rem 24rem at 82% 8%, color-mix(in srgb, var(--primary) 7%, transparent), transparent 62%);
    }

    /* Visually hidden utility for live screen-reader announcements */
    .visually-hidden:where(:not(:focus-within, :active)) {
      position: absolute !important;
      clip-path: inset(50%) !important;
      overflow: hidden !important;
      width: 1px !important;
      height: 1px !important;
      margin: -1px !important;
      padding: 0 !important;
      border: 0 !important;
      white-space: nowrap !important;
    }

    /* GPU-only entrance & hover physics (strictly gated by prefers-reduced-motion) */
    @keyframes login-fade-up {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    @media (prefers-reduced-motion: no-preference) {
      .login-enter {
        animation: login-fade-up 440ms var(--login-spring) both;
      }
      .login-enter-delay {
        animation-delay: 80ms;
      }
      .login-submit-btn,
      .login-icon-btn {
        transition: transform 170ms var(--login-spring), background-color 160ms ease, border-color 160ms ease, box-shadow 180ms ease;
      }
      .login-submit-btn:active:not(:disabled),
      .login-icon-btn:active {
        transform: scale(0.98) translateY(1px);
      }
      .login-hero-img {
        transition: transform 700ms ease-out;
      }
    }
    @media (hover: hover) and (prefers-reduced-motion: no-preference) {
      .login-visual-stage:hover .login-hero-img {
        transform: scale(1.025);
      }
    }
    @media (prefers-reduced-motion: reduce) {
      .login-enter,
      .login-submit-btn,
      .login-icon-btn,
      .login-hero-img {
        animation: none !important;
        transition: none !important;
        transform: none !important;
      }
    }
  </style>
</head>
<body class="login-shell min-h-[100dvh] bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100 antialiased selection:bg-orange-500 selection:text-white">

  <main class="overflow-x-hidden w-full max-w-full min-h-[100dvh] grid grid-cols-1 lg:grid-cols-12">

    <!-- ================================================================ -->
    <!-- LEFT: EDITORIAL BASARNAS VISUAL STAGE (Desktop Split)            -->
    <!-- ================================================================ -->
    <section aria-label="Visual operasi pencarian dan pertolongan Basarnas" class="hidden lg:flex lg:col-span-7 p-4 xl:p-5 lg:h-[100dvh]">
      <div class="login-visual-stage login-enter group relative w-full h-full rounded-[1.75rem] overflow-hidden bg-slate-900 border border-slate-200/80 dark:border-white/10 shadow-xl">
        <img
          src="<?= e($loginHeroUrl) ?>"
          alt="Kapal patroli dan perahu karet penyelamat Basarnas melintasi perairan teluk saat fajar"
          width="896"
          height="1200"
          fetchpriority="high"
          class="login-hero-img absolute inset-0 w-full h-full object-cover object-center contrast-[1.03] saturate-[0.96]"
        >

        <!-- Clean dark scrim solely over tranquil lower ocean water -->
        <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/20 to-slate-950/10" aria-hidden="true"></div>

        <!-- Minimal top brand signature -->
        <div class="absolute top-6 left-7 right-7 flex items-center gap-3">
          <img
            src="<?= e($logoUrl) ?>"
            alt=""
            aria-hidden="true"
            width="34"
            height="34"
            class="w-8 h-8 object-contain drop-shadow"
            onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'"
          >
          <span class="text-xs font-semibold tracking-wide text-white/90 drop-shadow-sm">
            QR-SAR &middot; BASARNAS
          </span>
        </div>

        <!-- Wide 2-line editorial headline anchored cleanly at bottom -->
        <div class="absolute inset-x-0 bottom-0 p-8 xl:p-10 space-y-2.5">
          <p class="login-editorial-heading max-w-xl font-bold text-white drop-shadow-sm">
            Siap siaga di setiap koordinat.
          </p>
          <p class="text-sm text-slate-200/90 max-w-md leading-relaxed">
            <?= e($satkerNama) ?>
          </p>
        </div>
      </div>
    </section>

    <!-- ================================================================ -->
    <!-- RIGHT: MINIMALIST & STABLE SIGN-IN PANEL                         -->
    <!-- ================================================================ -->
    <section aria-labelledby="loginHeading" class="login-form-pane lg:col-span-5 flex flex-col justify-between min-h-[100dvh] px-5 py-5 sm:px-8 lg:px-10 xl:px-14">

      <!-- Minimal Floating Top Bar: Theme Toggle Only -->
      <header class="flex items-center justify-between lg:justify-end gap-3">
        <div class="flex items-center gap-2.5 lg:hidden min-w-0">
          <img
            src="<?= e($logoUrl) ?>"
            alt="Logo <?= e($satkerNama) ?>"
            width="32"
            height="32"
            class="w-8 h-8 object-contain shrink-0"
            onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'"
          >
          <span class="text-xs font-bold tracking-tight text-slate-800 dark:text-slate-100 truncate">QR-SAR</span>
        </div>

        <button
          type="button"
          id="loginThemeToggle"
          onclick="toggleLoginTheme()"
          class="login-icon-btn inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer shadow-xs shrink-0"
          title="Ganti Mode Gelap / Terang"
          aria-label="Ganti mode gelap atau terang"
        >
          <span id="loginThemeIcon" class="flex items-center justify-center"></span>
          <span id="loginThemeText" class="sr-only">Mode Gelap</span>
        </button>
      </header>

      <!-- Main Centered Minimalist Login Column -->
      <div class="login-enter login-enter-delay w-full max-w-[23.5rem] mx-auto my-auto py-6 sm:py-8">

        <!-- Compact Basarnas Visual Strip on Mobile/Tablet (< lg) -->
        <div class="lg:hidden mb-6 rounded-2xl overflow-hidden border border-slate-200/90 dark:border-slate-800 bg-slate-900 aspect-[16/8] shadow-sm relative">
          <img
            src="<?= e($loginHeroUrl) ?>"
            alt="Kapal patroli dan perahu karet penyelamat Basarnas melintasi perairan teluk saat fajar"
            width="896"
            height="1200"
            class="w-full h-full object-cover object-[50%_48%]"
          >
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/65 via-transparent to-transparent" aria-hidden="true"></div>
          <p class="absolute bottom-3 left-4 right-4 text-xs font-semibold text-white/95 truncate">
            Siap siaga di setiap koordinat.
          </p>
        </div>

        <!-- Crisp Identity & Heading -->
        <div class="space-y-2 mb-7">
          <img
            src="<?= e($logoUrl) ?>"
            alt="Logo Basarnas"
            width="44"
            height="44"
            class="hidden lg:block w-11 h-11 object-contain mb-4"
            onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'"
          >
          <h1 id="loginHeading" class="login-form-heading font-bold text-slate-900 dark:text-white">
            Masuk ke QR-SAR
          </h1>
          <p class="text-xs sm:text-sm text-slate-500 dark:text-slate-400">
            <?= e($satkerNama) ?>
          </p>
        </div>

        <!-- Flash Alerts -->
        <?php if ($error): ?>
          <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-red-200 bg-red-50 px-3.5 py-3 text-xs sm:text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" role="alert">
            <svg class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            <span><?= htmlspecialchars($error) ?></span>
          </div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="mb-5 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 text-xs sm:text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" role="status">
            <svg class="w-4 h-4 mt-0.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <span><?= htmlspecialchars($success) ?></span>
          </div>
        <?php endif; ?>

        <!-- Sign-In Form (Semantic HTML, Autofill-Ready, Post-Interaction Validation) -->
        <form method="POST" action="<?= BASE_URL ?>/login.php" id="loginForm" class="space-y-4" autocomplete="on">
          <?= csrfInput() ?>

          <!-- Username -->
          <div class="login-field">
            <label for="usernameInput" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Nama pengguna
            </label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
              </span>
              <input
                type="text"
                name="username"
                id="usernameInput"
                class="login-input"
                placeholder="Masukkan nama pengguna"
                required
                autofocus
                autocomplete="username"
                spellcheck="false"
                autocorrect="off"
                autocapitalize="none"
                enterkeyhint="next"
                aria-invalid="false"
                data-error="username-error"
              >
            </div>
            <p id="username-error" class="login-field-error">
              <span role="img" aria-label="Galat">
                <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </span>
              <span>Nama pengguna wajib diisi.</span>
            </p>
          </div>

          <!-- Password -->
          <div class="login-field">
            <label for="passwordInput" class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
              Kata sandi
            </label>
            <div class="relative">
              <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              </span>
              <input
                type="password"
                name="password"
                id="passwordInput"
                class="login-input !pr-11"
                placeholder="Masukkan kata sandi"
                required
                autocomplete="current-password"
                enterkeyhint="done"
                aria-invalid="false"
                data-error="password-error"
              >
              <button
                type="button"
                onclick="togglePassVisibility()"
                id="btnTogglePassword"
                class="login-pass-toggle absolute inset-y-0 right-0 flex items-center justify-center w-10 rounded-r-xl text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 cursor-pointer"
                title="Tampilkan / Sembunyikan Kata Sandi"
                aria-label="Tampilkan kata sandi"
                aria-pressed="false"
              >
                <svg id="passEyeIcon" class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="passEyeOffIcon" class="w-4 h-4 hidden" style="color: var(--m-accent-text, var(--primary));" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
              </button>
            </div>
            <p id="password-error" class="login-field-error">
              <span role="img" aria-label="Galat">
                <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              </span>
              <span>Kata sandi wajib diisi.</span>
            </p>
            <p id="capsLockWarning" class="hidden mt-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
              Caps Lock aktif
            </p>
          </div>

          <!-- Submit CTA -->
          <div class="pt-1.5">
            <button
              type="submit"
              id="btnLoginSubmit"
              class="login-submit-btn w-full inline-flex items-center justify-center gap-2 rounded-xl py-3 px-4 text-sm font-semibold tracking-wide cursor-pointer"
            >
              <span>Masuk</span>
              <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 5 7 7-7 7"/></svg>
            </button>
          </div>
        </form>
      </div>

      <!-- Quiet Minimal Footer -->
      <footer class="flex items-center justify-between gap-3 text-xs text-slate-400 dark:text-slate-500 pt-2">
        <span>QR-SAR v<?= APP_VERSION ?></span>
        <span>Darurat <a href="tel:115" class="font-semibold text-red-500 hover:underline">115</a></span>
      </footer>
    </section>

  </main>

  <!-- SweetAlert2 JS (Local Offline Vendor) & App JS -->
  <script src="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/app.js') ?>"></script>

  <script>
    // Password Visibility Toggle (keyboard accessible with aria-pressed sync)
    function togglePassVisibility() {
      const input = document.getElementById('passwordInput');
      const btn = document.getElementById('btnTogglePassword');
      const eyeIcon = document.getElementById('passEyeIcon');
      const eyeOffIcon = document.getElementById('passEyeOffIcon');
      if (!input) return;

      const showing = input.type === 'password';
      input.type = showing ? 'text' : 'password';
      if (eyeIcon) eyeIcon.classList.toggle('hidden', showing);
      if (eyeOffIcon) eyeOffIcon.classList.toggle('hidden', !showing);
      if (btn) {
        btn.setAttribute('aria-pressed', showing ? 'true' : 'false');
        btn.setAttribute('aria-label', showing ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
      }
    }

    // Real-time Caps Lock Detection
    const passInput = document.getElementById('passwordInput');
    const capsWarning = document.getElementById('capsLockWarning');
    if (passInput && capsWarning) {
      ['keyup', 'keydown'].forEach(function(evtName) {
        passInput.addEventListener(evtName, function(e) {
          if (e.getModifierState && e.getModifierState('CapsLock')) {
            capsWarning.classList.remove('hidden');
          } else {
            capsWarning.classList.add('hidden');
          }
        });
      });
    }

    // Post-interaction validation ARIA sync & :user-invalid fallback
    (function() {
      const form = document.getElementById('loginForm');
      if (!form) return;

      const supportsUserInvalid = typeof CSS !== 'undefined' && CSS.supports && CSS.supports('selector(:user-invalid)');
      const dirtyState = new WeakMap();
      const announced = new WeakSet();
      const announcer = document.createElement('div');
      announcer.setAttribute('aria-live', 'polite');
      announcer.className = 'visually-hidden';
      document.body.appendChild(announcer);
      let announceTimer;

      function linkError(el, shown) {
        const errorId = el.dataset.error;
        if (!errorId) return;
        const ids = (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(function(id) {
          return id && id !== errorId;
        });
        if (shown) ids.push(errorId);
        if (ids.length) el.setAttribute('aria-describedby', ids.join(' '));
        else el.removeAttribute('aria-describedby');
      }

      function announceError(el) {
        const err = document.getElementById(el.dataset.error || '');
        if (!err) return;
        clearTimeout(announceTimer);
        announcer.textContent = '';
        announceTimer = setTimeout(function() {
          announcer.textContent = err.textContent.replace(/\s+/g, ' ').trim();
        }, 350);
      }

      function syncField(el, shouldAnnounce) {
        if (!el || !el.matches || !el.matches('input')) return;
        let isInvalid = false;
        if (supportsUserInvalid) {
          isInvalid = el.matches(':user-invalid');
        } else {
          const st = dirtyState.get(el);
          isInvalid = !!(st && st.touched && !el.validity.valid);
          el.classList.toggle('user-invalid-fallback', isInvalid);
        }
        el.setAttribute('aria-invalid', isInvalid ? 'true' : 'false');
        linkError(el, isInvalid);
        if (isInvalid && shouldAnnounce && !announced.has(el)) {
          announced.add(el);
          announceError(el);
        } else if (!isInvalid) {
          announced.delete(el);
        }
      }

      form.addEventListener('blur', function(e) {
        if (!e.target.matches || !e.target.matches('input')) return;
        const st = dirtyState.get(e.target) || { interacted: false, touched: false };
        if (st.interacted) st.touched = true;
        dirtyState.set(e.target, st);
        syncField(e.target, true);
      }, true);

      form.addEventListener('input', function(e) {
        if (!e.target.matches || !e.target.matches('input')) return;
        const st = dirtyState.get(e.target) || { interacted: false, touched: false };
        st.interacted = true;
        dirtyState.set(e.target, st);
        syncField(e.target, false);
      });

      form.addEventListener('invalid', function(e) {
        if (!e.target.matches || !e.target.matches('input')) return;
        dirtyState.set(e.target, { interacted: true, touched: true });
        announced.add(e.target);
        syncField(e.target, false);
      }, true);
    })();

    // Theme Switcher Sync
    function updateLoginThemeUI(theme) {
      const textEl = document.getElementById('loginThemeText');
      if (textEl) {
        textEl.textContent = theme === 'dark' ? 'Mode Terang' : 'Mode Gelap';
      }
      const iconEl = document.getElementById('loginThemeIcon');
      if (iconEl) {
        if (theme === 'dark') {
          iconEl.innerHTML = '<svg class="w-4 h-4 text-amber-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>';
        } else {
          iconEl.innerHTML = '<svg class="w-4 h-4 text-slate-700" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';
        }
      }
    }

    function toggleLoginTheme() {
      if (typeof window.toggleAppTheme === 'function') {
        window.toggleAppTheme();
      } else {
        const isDark = document.documentElement.classList.contains('dark');
        const next = isDark ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        document.documentElement.classList.toggle('dark', next === 'dark');
        localStorage.setItem('qr_sar_theme', next);
        updateLoginThemeUI(next);
      }
    }

    // Form Submission Interactive Feedback (with bfcache pageshow restore)
    const loginForm = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnLoginSubmit');
    const defaultSubmitHtml = btnSubmit ? btnSubmit.innerHTML : '<span>Masuk</span>';
    if (loginForm && btnSubmit) {
      loginForm.addEventListener('submit', function() {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = '<svg class="animate-spin h-4 w-4 text-white inline-block" aria-hidden="true" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg><span>Memverifikasi...</span>';
        btnSubmit.classList.add('opacity-80', 'cursor-not-allowed');
      });
      window.addEventListener('pageshow', function() {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = defaultSubmitHtml;
        btnSubmit.classList.remove('opacity-80', 'cursor-not-allowed');
      });
    }

    window.addEventListener('themeChanged', function(e) {
      if (e && e.detail && e.detail.theme) updateLoginThemeUI(e.detail.theme);
    });

    document.addEventListener('DOMContentLoaded', function() {
      const cur = localStorage.getItem('qr_sar_theme') || document.documentElement.getAttribute('data-theme') || 'dark';
      updateLoginThemeUI(cur);
      <?php if ($error): ?>
        if (typeof showToast === 'function') {
          showToast(<?= jsonSafe($error) ?>, 'error');
        }
      <?php endif; ?>
      <?php if ($success): ?>
        if (typeof showToast === 'function') {
          showToast(<?= jsonSafe($success) ?>, 'success');
        }
      <?php endif; ?>
    });
  </script>
</body>
</html>
