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
$logoUrl = getAppLogoUrl();
$satkerNama = $appSettings['satker_nama'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

  <!-- SweetAlert2 CSS (Local Offline) -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sweetalert2.min.css?v=<?= APP_VERSION ?>">
  
  <!-- Tailwind CSS (Local Offline Engine) -->
  <script src="<?= BASE_URL ?>/assets/js/tailwind.min.js?v=<?= APP_VERSION ?>"></script>
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
              deep: '#C2410C',
              navy: '#0B1120',
              slate: '#1E293B',
              dark: '#060B13'
            }
          },
          animation: {
            'radar-spin': 'radar-spin 12s linear infinite',
            'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
          },
          keyframes: {
            'radar-spin': {
              'from': { transform: 'rotate(0deg)' },
              'to': { transform: 'rotate(360deg)' }
            }
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">

  <style>
    /* Tactical Maritime Grid & Radar Effects */
    .tactical-grid {
      background-size: 32px 32px;
      background-image: 
        linear-gradient(to right, rgba(255, 255, 255, 0.04) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(255, 255, 255, 0.04) 1px, transparent 1px);
    }
    .dark .tactical-grid {
      background-image: 
        linear-gradient(to right, rgba(249, 115, 22, 0.05) 1px, transparent 1px),
        linear-gradient(to bottom, rgba(249, 115, 22, 0.05) 1px, transparent 1px);
    }
    .radar-beam {
      background: conic-gradient(from 0deg at 50% 50%, rgba(249, 115, 22, 0) 0deg, rgba(249, 115, 22, 0) 280deg, rgba(249, 115, 22, 0.25) 360deg);
    }
    .login-glass-card {
      box-shadow: 
        0 25px 50px -12px rgba(0, 0, 0, 0.25),
        0 0 0 1px rgba(255, 255, 255, 0.1),
        0 0 35px -5px rgba(249, 115, 22, 0.15);
    }
    .dark .login-glass-card {
      box-shadow: 
        0 30px 60px -15px rgba(0, 0, 0, 0.8),
        0 0 0 1px rgba(249, 115, 22, 0.18),
        0 0 50px -10px rgba(249, 115, 22, 0.25);
    }
  </style>
</head>
<body class="bg-slate-100 dark:bg-basarnas-dark text-slate-800 dark:text-slate-100 min-h-screen flex flex-col justify-between p-4 sm:p-6 lg:p-8 relative overflow-x-hidden font-sans transition-colors duration-300">
  
  <!-- Ambient Tactical Background Glows -->
  <div class="fixed top-0 right-0 w-[500px] h-[500px] bg-orange-500/10 dark:bg-orange-500/15 rounded-full blur-[120px] pointer-events-none z-0"></div>
  <div class="fixed bottom-0 left-0 w-[450px] h-[450px] bg-amber-500/10 dark:bg-amber-500/10 rounded-full blur-[120px] pointer-events-none z-0"></div>

  <!-- 1. TOP UTILITY HEADER -->
  <header class="w-full max-w-6xl mx-auto flex items-center justify-between z-20 mb-4 sm:mb-6">
    <!-- Emergency Call Indicator -->
    <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/80 dark:bg-slate-900/80 border border-slate-200 dark:border-slate-800 backdrop-blur-md shadow-xs">
      <span class="w-2 h-2 rounded-full bg-red-500 animate-ping shrink-0"></span>
      <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 tracking-wide flex items-center gap-1.5">
        <span class="text-orange-600 dark:text-orange-400 font-extrabold">EMERGENCY:</span>
        <a href="tel:115" class="hover:underline font-mono text-slate-900 dark:text-white">115 BASARNAS</a>
      </span>
    </div>

    <!-- Theme Switcher Button -->
    <button
      type="button"
      id="loginThemeToggle"
      onclick="toggleLoginTheme()"
      class="flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-slate-200 dark:border-slate-800 bg-white/80 dark:bg-slate-900/80 text-slate-700 dark:text-slate-200 shadow-xs backdrop-blur-md hover:bg-slate-50 dark:hover:bg-slate-800 transition cursor-pointer text-xs font-bold"
      title="Ganti Mode Gelap / Terang"
    >
      <span id="loginThemeIcon" class="flex items-center justify-center">
        <svg class="w-4 h-4 hidden dark:block text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg class="w-4 h-4 block dark:hidden text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
      </span>
      <span id="loginThemeText" class="hidden sm:inline">Mode Gelap</span>
    </button>
  </header>

  <!-- 2. MAIN CONSOLE CONTAINER (SPLIT COMMAND CARD) -->
  <main class="w-full max-w-5xl mx-auto my-auto z-10">
    <div class="login-glass-card bg-white dark:bg-slate-900/90 rounded-3xl border border-slate-200 dark:border-slate-800/80 overflow-hidden grid grid-cols-1 lg:grid-cols-12 transition-all duration-300">
      
      <!-- LEFT PANEL: TACTICAL BRANDING & TELEMETRY (Desktop Only) -->
      <div class="hidden lg:flex lg:col-span-5 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 p-8 xl:p-10 flex-col justify-between text-white relative overflow-hidden border-r border-slate-800">
        
        <!-- Radar & Nautical Grid Graphics Background -->
        <div class="absolute inset-0 tactical-grid opacity-70 pointer-events-none"></div>
        <div class="absolute -right-24 -top-24 w-80 h-80 rounded-full border border-orange-500/20 pointer-events-none">
          <div class="w-full h-full rounded-full border border-orange-500/10 m-auto scale-75"></div>
          <div class="w-full h-full rounded-full border border-orange-500/10 m-auto scale-50"></div>
          <div class="absolute inset-0 rounded-full radar-beam animate-radar-spin opacity-40"></div>
        </div>

        <!-- Top: Logo & Satker Header -->
        <div class="relative z-10">
          <div class="flex items-center gap-3.5 mb-6">
            <div class="w-14 h-14 p-2 rounded-2xl bg-slate-900/90 border border-orange-500/40 shadow-lg shadow-orange-500/15 flex items-center justify-center backdrop-blur-md shrink-0">
              <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="w-full h-full object-contain drop-shadow">
            </div>
            <div>
              <div class="text-[10px] font-mono tracking-widest text-orange-400 font-extrabold uppercase">
                BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
              </div>
              <h2 class="text-sm font-black tracking-wide text-white uppercase mt-0.5">
                KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI
              </h2>
            </div>
          </div>

          <div class="space-y-2 mt-4">
            <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-orange-500/15 border border-orange-500/30 text-orange-400 font-mono text-[10px] font-bold">
              <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
              <span>SULTRA MARITIME</span>
            </div>
            <h1 class="text-2xl font-black uppercase tracking-tight text-white leading-tight">
            PORTAL <br><span class="text-transparent bg-clip-text bg-gradient-to-r from-orange-400 via-amber-400 to-orange-500">OPERASI SAR</span>
            </h1>
           
          </div>
        </div>

        <!-- Center: Live Tactical Telemetry Widget -->
        <div class="relative z-10 my-6 p-4 rounded-2xl bg-slate-900/80 border border-slate-800 backdrop-blur-md space-y-3">
          <div class="flex items-center justify-between border-b border-slate-800 pb-2">
            <span class="text-[10px] font-mono uppercase text-slate-400 font-bold flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
              STATUS TELEMETRI
            </span>
            <span class="text-[9px] font-mono text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
              SIAGA 24/7
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <div class="text-[10px] text-slate-400 font-medium">Waktu Lokal (WITA)</div>
              <div id="liveClockWita" class="text-sm font-mono font-bold text-white tracking-wider pt-0.5">
                <?= date('H:i:s') ?> WITA
              </div>
            </div>
            <div>
              <div class="text-[10px] text-slate-400 font-medium">Kansar Kendari GPS</div>
              <div class="text-[11px] font-mono font-semibold text-orange-300 pt-0.5 truncate" title="04°02'15.6''S 122°29'12.0''E">
                04°02'S 122°29'E
              </div>
            </div>
          </div>
        </div>

        <!-- Bottom: Operational Motto -->
        <div class="relative z-10 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px] text-slate-400">
          <div class="italic font-serif text-slate-300">
            "Avignam Jagat Samagram"
          </div>

        </div>

      </div>

      <!-- RIGHT PANEL: AUTHENTICATION FORM CONSOLE -->
      <div class="lg:col-span-7 p-7 sm:p-10 xl:p-12 flex flex-col justify-between bg-white dark:bg-slate-900/90 backdrop-blur-xl">
        
        <div>
          <!-- Mobile Header (Visible on < lg screens) -->
          <div class="lg:hidden flex flex-col items-center text-center mb-6">
            <div class="w-16 h-16 mb-3 p-2 rounded-2xl bg-slate-50 dark:bg-slate-950/80 border border-orange-500/30 shadow-md flex items-center justify-center">
              <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
            </div>
            <h2 class="text-lg font-black uppercase tracking-wider text-slate-900 dark:text-white">QR-SAR KENDARI</h2>
            <p class="text-[11px] font-bold text-orange-600 dark:text-orange-400 uppercase tracking-widest mt-0.5">
              <?= e($satkerNama) ?>
            </p>
          </div>

          <!-- Section Heading -->
          <div class="mb-6">
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800/80 text-slate-600 dark:text-slate-300 text-[10px] font-bold tracking-wide uppercase mb-2 border border-slate-200 dark:border-slate-700">
              <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <span>Autentikasi Personel Resmi</span>
            </div>
            <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
              Login
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">
              Masukkan nama pengguna dan kata sandi!
            </p>
          </div>

          <!-- Flash Notification Alert Boxes -->
          <?php if ($error): ?>
            <div class="mb-5 p-3.5 rounded-2xl bg-red-500/10 border border-red-500/30 text-red-600 dark:text-red-400 text-xs flex items-start gap-3 shadow-xs animate-pulse">
              <svg class="w-4 h-4 shrink-0 mt-0.5 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <div class="font-medium leading-relaxed"><?= htmlspecialchars($error) ?></div>
            </div>
          <?php endif; ?>

          <?php if ($success): ?>
            <div class="mb-5 p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs flex items-start gap-3 shadow-xs">
              <svg class="w-4 h-4 shrink-0 mt-0.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <div class="font-medium leading-relaxed"><?= htmlspecialchars($success) ?></div>
            </div>
          <?php endif; ?>

          <!-- Pure Credentials Login Form (Without Choice of Accounts) -->
          <form method="POST" action="<?= BASE_URL ?>/login.php" id="loginForm" class="space-y-4" autocomplete="on">
            <?= csrfInput() ?>

            <!-- Username Field -->
            <div>
              <label for="usernameInput" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                <span>Nama Pengguna</span>
                <span class="text-[10px] text-slate-400 font-mono font-normal">Username</span>
              </label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-orange-500 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <input 
                  type="text" 
                  name="username" 
                  id="usernameInput" 
                  placeholder="Ketik username akun Anda..." 
                  required 
                  autofocus
                  autocomplete="username"
                  class="w-full pl-10 pr-4 py-3 text-sm bg-slate-50 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-700/80 rounded-2xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all font-medium"
                >
              </div>
            </div>

            <!-- Password Field -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="passwordInput" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">
                  Kata Sandi
                </label>
                <!-- Caps Lock Warning Tooltip (Dynamic) -->
                <span id="capsLockWarning" class="hidden text-[10px] font-bold text-amber-500 flex items-center gap-1 font-mono">
                  <span>⚠️ Caps Lock Aktif</span>
                </span>
              </div>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 group-focus-within:text-orange-500 transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <input 
                  type="password" 
                  name="password" 
                  id="passwordInput" 
                  placeholder="Masukkan kata sandi..." 
                  required 
                  autocomplete="current-password"
                  class="w-full pl-10 pr-11 py-3 text-sm bg-slate-50 dark:bg-slate-950/80 border border-slate-300 dark:border-slate-700/80 rounded-2xl text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent transition-all font-medium"
                >
                <button 
                  type="button" 
                  onclick="togglePassVisibility()" 
                  id="btnTogglePassword"
                  class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition-colors cursor-pointer" 
                  title="Lihat / Sembunyikan Kata Sandi"
                  tabindex="-1"
                >
                  <svg id="passEyeIcon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                  <svg id="passEyeOffIcon" class="w-4 h-4 hidden text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" x2="22" y1="2" y2="22"/></svg>
                </button>
              </div>
            </div>

            <!-- Submit CTA Button -->
            <div class="pt-2">
              <button 
                type="submit" 
                id="btnLoginSubmit"
                class="w-full py-3.5 px-4 rounded-2xl text-xs sm:text-sm font-extrabold uppercase tracking-wider text-white bg-gradient-to-r from-orange-500 via-orange-600 to-amber-600 hover:from-orange-600 hover:to-amber-700 shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40 border border-orange-400/30 transition-all duration-200 transform hover:-translate-y-0.5 active:translate-y-0 cursor-pointer flex items-center justify-center gap-2 group"
              >
                <span>Login</span>
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
              </button>
            </div>
          </form>
        </div>

        <!-- Footer Security & Compliance Details -->
        <div class="mt-8 pt-5 border-t border-slate-200 dark:border-slate-800 text-center space-y-2">
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400 text-[10px] font-mono">
            <svg class="w-3 h-3 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Terenkripsi 256-bit SSL</span>
          </div>
         
        </div>

      </div>

    </div>
  </main>

  <!-- 3. PAGE FOOTER -->
  <footer class="w-full max-w-6xl mx-auto text-center z-10 mt-4 text-[11px] text-slate-400 dark:text-slate-500 font-mono">
    <span>QR-SAR Kendari v<?= APP_VERSION ?></span> &nbsp;&bull;&nbsp; 
    &copy; <?= date('Y') ?> KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI
  </footer>

  <!-- SweetAlert2 JS (Local Offline Bundle) & App JS -->
  <script src="<?= BASE_URL ?>/assets/js/sweetalert2.all.min.js?v=<?= APP_VERSION ?>"></script>
  <script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>

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
