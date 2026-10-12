<?php
/**
 * QR-SAR - Login Screen (Dual Theme Dark & Light Mode)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

$error = getFlash('error');
$success = getFlash('success');
$appSettings = SettingsModel::getSettings();
$logoUrl = getAppLogoUrl();
$satkerNama = $appSettings['satker_nama'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Masuk Sistem &bull; QR-SAR Basarnas</title>
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

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  
  <!-- Tailwind CSS -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          fontFamily: {
            sans: ['"Plus Jakarta Sans"', 'sans-serif'],
            mono: ['"JetBrains Mono"', 'monospace'],
          },
          colors: {
            basarnas: {
              orange: '#F97316',
              amber: '#D97706',
              navy: '#0B1120',
              slate: '#1E293B',
            }
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 min-h-screen flex items-center justify-center p-4 relative overflow-hidden font-sans transition-colors duration-200">
  
  <!-- Subtle Glowing Radar Radial Background -->
  <div class="absolute -top-40 -right-40 w-96 h-96 bg-orange-500/15 dark:bg-orange-500/20 rounded-full blur-3xl pointer-events-none"></div>
  <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-amber-500/15 dark:bg-amber-500/15 rounded-full blur-3xl pointer-events-none"></div>

  <!-- Floating Global Theme Toggle Button -->
  <div class="fixed top-4 right-4 z-50">
    <button
      type="button"
      id="loginThemeToggle"
      onclick="toggleLoginTheme()"
      class="flex items-center gap-2 px-3 py-2 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-200 shadow-md backdrop-blur-md hover:bg-slate-100 dark:hover:bg-slate-700 transition cursor-pointer text-xs font-bold"
      title="Ganti Mode Gelap / Terang"
    >
      <span id="loginThemeIcon" class="flex items-center justify-center">
        <svg class="w-4 h-4 hidden dark:block text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg class="w-4 h-4 block dark:hidden text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </span>
      <span id="loginThemeText" class="hidden sm:inline">Mode Gelap</span>
    </button>
  </div>

  <div class="w-full max-w-md relative z-10">
    <!-- Card Container -->
    <div class="bg-white/95 dark:bg-slate-900/90 backdrop-blur-xl border border-slate-200 dark:border-slate-800 rounded-3xl p-7 sm:p-8 shadow-2xl dark:shadow-black/70 shadow-slate-300/60 transition-colors">
      
      <!-- Logo & Heading -->
      <div class="flex flex-col items-center text-center mb-7">
        <div class="w-20 h-20 mb-3.5 p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-orange-500/30 shadow-lg shadow-orange-500/10 flex items-center justify-center">
          <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
        </div>
        <h1 class="text-xl font-black uppercase tracking-wider text-slate-900 dark:text-white">QR-SAR KENDARI</h1>
        <p class="text-xs font-bold text-orange-600 dark:text-orange-400 uppercase tracking-widest mt-1">
          <?= e($satkerNama) ?>
        </p>
        <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
          Sistem Digitalisasi File Aktif &amp; Berita Radio SAR
        </span>
      </div>

      <!-- Flash Notification Box -->
      <?php if ($error): ?>
        <div class="mb-5 p-3.5 rounded-xl bg-red-500/15 border border-red-500/30 text-red-600 dark:text-red-400 text-xs flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <span class="font-medium"><?= htmlspecialchars($error) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="mb-5 p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs flex items-center gap-2">
          <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
          <span class="font-medium"><?= htmlspecialchars($success) ?></span>
        </div>
      <?php endif; ?>

      <!-- Quick Role Auto-fill Buttons -->
      <div class="mb-6 p-3 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800">
        <div class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-2 text-center">
          Pilih Cepat Akun Uji Coba:
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button type="button" onclick="fillCredentials('admin', 'Kendari304')" class="p-2.5 rounded-xl bg-white dark:bg-slate-900 hover:bg-orange-500/15 dark:hover:bg-orange-500/20 border border-slate-200 dark:border-slate-700/80 hover:border-orange-500/40 text-left transition group cursor-pointer shadow-xs">
            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-orange-600 dark:group-hover:text-orange-400">🛡️ Administrator</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">admin &bull; Kendari304</div>
          </button>
          <button type="button" onclick="fillCredentials('user', 'Kendari304')" class="p-2.5 rounded-xl bg-white dark:bg-slate-900 hover:bg-orange-500/15 dark:hover:bg-orange-500/20 border border-slate-200 dark:border-slate-700/80 hover:border-orange-500/40 text-left transition group cursor-pointer shadow-xs">
            <div class="text-xs font-bold text-slate-800 dark:text-slate-200 group-hover:text-orange-600 dark:group-hover:text-orange-400">📋 Operator Siaga</div>
            <div class="text-[10px] text-slate-500 dark:text-slate-400 font-mono mt-0.5">user &bull; Kendari304</div>
          </button>
        </div>
      </div>

      <!-- Login Form -->
      <form method="POST" action="<?= BASE_URL ?>/login.php" class="space-y-4">
        <?= csrfInput() ?>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Nama Pengguna (Username)</label>
          <div class="relative">
            <input type="text" name="username" id="usernameInput" placeholder="Masukkan username" required
                   class="w-full px-4 py-3 pl-10 text-sm bg-slate-50 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Kata Sandi (Password)</label>
          <div class="relative">
            <input type="password" name="password" id="passwordInput" placeholder="Masukkan kata sandi" required
                   class="w-full px-4 py-3 pl-10 pr-10 text-sm bg-slate-50 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition">
            <svg class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            <button type="button" onclick="togglePassVisibility()" class="absolute right-3.5 top-3.5 text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer" title="Lihat Kata Sandi">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.264 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
        </div>

        <button type="submit" class="w-full py-3.5 text-xs font-bold uppercase tracking-wider text-white bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 rounded-xl shadow-lg shadow-orange-500/25 transition transform hover:-translate-y-0.5 cursor-pointer active:translate-y-0">
          Masuk ke Portal SAR
        </button>
      </form>

      <!-- Footer Info -->
      <div class="mt-7 pt-5 border-t border-slate-200 dark:border-slate-800 text-center">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-orange-500/10 border border-orange-500/20 text-orange-600 dark:text-orange-400 text-[11px] font-bold">
          <span>🚨 Emergency Call: 115 Basarnas</span>
        </div>
        <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-2.5 font-mono">
          &copy; <?= date('Y') ?> Badan Nasional Pencarian dan Pertolongan KPP Kendari
        </p>
      </div>

    </div>
  </div>

  <!-- SweetAlert2 JS (Local Offline Bundle) & App JS -->
  <script src="<?= BASE_URL ?>/assets/js/sweetalert2.all.min.js?v=<?= APP_VERSION ?>"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>

  <script>
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

    function fillCredentials(u, p) {
      document.getElementById('usernameInput').value = u;
      document.getElementById('passwordInput').value = p;
      if (typeof showToast === 'function') {
        showToast('Kredensial akun ' + u + ' berhasil diisikan!', 'success');
      }
    }

    function togglePassVisibility() {
      const input = document.getElementById('passwordInput');
      input.type = input.type === 'password' ? 'text' : 'password';
    }

    document.addEventListener('DOMContentLoaded', function() {
      const cur = localStorage.getItem('qr_sar_theme') || 'dark';
      updateLoginThemeUI(cur);
      <?php if ($error): ?>
        if (typeof showToast === 'function') {
          showToast(<?= json_encode($error) ?>, 'error');
        }
      <?php endif; ?>
      <?php if ($success): ?>
        if (typeof showToast === 'function') {
          showToast(<?= json_encode($success) ?>, 'success');
        }
      <?php endif; ?>
    });
  </script>
</body>
</html>
