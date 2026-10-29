<?php
/**
 * QR-SAR - Tactical Login Portal (Dual Theme Dark & Light Mode)
 * KPP Kendari - Badan Nasional Pencarian dan Pertolongan (Basarnas)
 * Professional Command Center Redesign
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$error = getFlash('error');
$success = getFlash('success');
$appSettings = SettingsModel::getSettings();
$uiTheme = (($appSettings['ui_theme'] ?? 'klasik') === 'minimal') ? 'minimal' : 'klasik';
$logoUrl = getAppLogoUrl();
$satkerNama = $appSettings['satker_nama'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI';
?>
<!DOCTYPE html>
<html lang="id" data-ui="<?= $uiTheme ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script>
  // Transisi antar halaman yang dibatalkan (klik cepat) bukan error: tangkap promise-nya
  ['pageswap', 'pagereveal'].forEach(function (t) {
      window.addEventListener(t, function (e) {
          if (e.viewTransition) { ["ready", "finished", "updateCallbackDone"].forEach(function (k) { if (e.viewTransition[k]) e.viewTransition[k].catch(function () {}); }); }
      });
  });
  </script>
  <title>Login</title>
  <link rel="icon" href="<?= e($logoUrl) ?>">

  <!-- Immediate Anti-Flicker Script -->
  <script>
    (function() {
      var savedTheme = localStorage.getItem('qr_sar_theme') || 'dark';
      document.documentElement.setAttribute('data-theme', savedTheme);
      if (savedTheme === 'dark') {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }
    })();
  </script>

  <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- SweetAlert2 CSS (Local Offline Vendor) -->
  <link rel="stylesheet" href="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">
  
  <!-- Tailwind CSS (Local Offline Vendor Engine) -->
  <link rel="stylesheet" href="<?= assetUrl('assets/css/tailwind.css') ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <!-- Tema tampilan global (Pengaturan Satker) -->
  <?php if ($uiTheme === 'minimal'): ?><link rel="stylesheet" href="<?= assetUrl('assets/css/theme-minimal.css') ?>"><?php endif; ?>

</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">

  <div class="flex justify-end p-4">
    <button type="button" id="loginThemeToggle" onclick="toggleLoginTheme()" class="btn btn-ghost btn-sm" title="Ganti Mode Gelap / Terang" aria-label="Ganti mode gelap atau terang">
      <span id="loginThemeIcon" class="flex items-center justify-center"></span>
      <span id="loginThemeText" class="sr-only">Mode Gelap</span>
    </button>
  </div>

  <main class="flex-1 flex items-center justify-center px-5 pb-10">
    <div class="w-full max-w-sm">
      <div class="text-center mb-8">
        <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="w-14 h-14 mx-auto object-contain">
        <h1 class="mt-4 text-xl font-semibold tracking-tight text-slate-900 dark:text-white">Masuk ke QR-SAR</h1>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><?= e($satkerNama) ?></p>
      </div>

      <div class="surface p-6 sm:p-7">
        <?php if ($error): ?>
          <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-300" role="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300" role="status"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" action="<?= BASE_URL ?>/login.php" id="loginForm" class="space-y-4" autocomplete="on">
          <?= csrfInput() ?>

          <div class="field">
            <label for="usernameInput" class="field-label">Nama pengguna</label>
            <input type="text" name="username" id="usernameInput" class="field-input" placeholder="contoh: admin" required autofocus autocomplete="username">
          </div>

          <div>
            <div class="field">
              <label for="passwordInput" class="field-label">Kata sandi</label>
              <input type="password" name="password" id="passwordInput" class="field-input pr-11" placeholder="Kata sandi akun" required autocomplete="current-password">
              <button type="button" onclick="togglePassVisibility()" id="btnTogglePassword" class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" title="Lihat / Sembunyikan Kata Sandi" aria-label="Lihat atau sembunyikan kata sandi" tabindex="-1">
                <svg id="passEyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg id="passEyeOffIcon" class="w-4 h-4 hidden text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
              </button>
            </div>
            <p id="capsLockWarning" class="hidden mt-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">Caps Lock aktif</p>
          </div>

          <button type="submit" id="btnLoginSubmit" class="btn btn-primary w-full py-3">Masuk</button>
        </form>
      </div>

      <p class="mt-8 text-center text-xs text-slate-400 dark:text-slate-500">QR-SAR v<?= APP_VERSION ?> &middot; Darurat <a href="tel:115" class="font-medium text-red-500 hover:underline">115</a></p>
    </div>
  </main>

  <!-- SweetAlert2 JS (Local Offline Vendor) & App JS -->
  <script src="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
  <script src="<?= assetUrl('assets/js/app.js') ?>"></script>

  <script>
    // Live Clock WITA
    function updateLiveClock() {
      try {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', {
          timeZone: 'Asia/Makassar',
          hour12: false,
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit'
        });
        const clockEl = document.getElementById('liveClockWita');
        if (clockEl) {
          clockEl.textContent = timeStr + ' WITA';
        }
      } catch (e) {
        // Fallback standard clock
        const now = new Date();
        const clockEl = document.getElementById('liveClockWita');
        if (clockEl) {
          clockEl.textContent = now.toTimeString().substring(0, 8) + ' WITA';
        }
      }
    }
    setInterval(updateLiveClock, 1000);
    updateLiveClock();

    // Password Visibility Peek Toggle
    function togglePassVisibility() {
      const input = document.getElementById('passwordInput');
      const eyeIcon = document.getElementById('passEyeIcon');
      const eyeOffIcon = document.getElementById('passEyeOffIcon');
      if (!input) return;

      if (input.type === 'password') {
        input.type = 'text';
        if (eyeIcon) eyeIcon.classList.add('hidden');
        if (eyeOffIcon) eyeOffIcon.classList.remove('hidden');
      } else {
        input.type = 'password';
        if (eyeIcon) eyeIcon.classList.remove('hidden');
        if (eyeOffIcon) eyeOffIcon.classList.add('hidden');
      }
    }

    // Caps Lock Real-time Detection
    const passInput = document.getElementById('passwordInput');
    const capsWarning = document.getElementById('capsLockWarning');
    if (passInput && capsWarning) {
      passInput.addEventListener('keyup', function(e) {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
          capsWarning.classList.remove('hidden');
        } else {
          capsWarning.classList.add('hidden');
        }
      });
      passInput.addEventListener('keydown', function(e) {
        if (e.getModifierState && e.getModifierState('CapsLock')) {
          capsWarning.classList.remove('hidden');
        } else {
          capsWarning.classList.add('hidden');
        }
      });
    }

    // Theme Switcher Sync
    function updateLoginThemeUI(theme) {
      const textEl = document.getElementById('loginThemeText');
      if (textEl) {
        textEl.textContent = theme === 'dark' ? 'Mode Terang' : 'Mode Gelap';
      }
      const iconEl = document.getElementById('loginThemeIcon');
      if (iconEl) {
        if (theme === 'dark') {
          iconEl.innerHTML = `<svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>`;
        } else {
          iconEl.innerHTML = `<svg class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;
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
        if (next === 'dark') document.documentElement.classList.add('dark');
        else document.documentElement.classList.remove('dark');
        localStorage.setItem('qr_sar_theme', next);
        updateLoginThemeUI(next);
      }
    }

    // Form Submission Interactive Feedback
    const loginForm = document.getElementById('loginForm');
    const btnSubmit = document.getElementById('btnLoginSubmit');
    if (loginForm && btnSubmit) {
      loginForm.addEventListener('submit', function() {
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `
          <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span>Memverifikasi Kredensial...</span>
        `;
        btnSubmit.classList.add('opacity-80', 'cursor-not-allowed');
      });
    }

    // Ikon tombol ikut berganti saat tema diubah lewat app.js
    window.addEventListener('themeChanged', function(e) {
      if (e && e.detail && e.detail.theme) updateLoginThemeUI(e.detail.theme);
    });

    document.addEventListener('DOMContentLoaded', function() {
      const cur = localStorage.getItem('qr_sar_theme') || 'dark';
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
