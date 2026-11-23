<?php
/**
 * QR-SAR - Dashboard Beranda (Arsitektur Editorial Rapi, Ikon-First & Stabil)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Struktur 4 blok terpadu sejajar pada satu rel grid:
 * 1. Hero Editorial + Panorama Basarnas + Bar Operasional Terpadu (3 KPI & Operasi Aktif)
 * 2. Strip Alur Kerja 5 Tahap (hover-only, non-klik)
 * 3. Bento Fitur Utama 12x2 Gapless (hover-only, non-klik)
 * 4. Matriks Keunggulan & Manfaat 2 Kolom (hover-only, non-klik)
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$stats = isset($stats) && is_array($stats) ? $stats : OperasiModel::getStats();
$activeOperations = isset($activeOperations) && is_array($activeOperations) ? $activeOperations : [];
$appSettings = isset($appSettings) && is_array($appSettings) ? $appSettings : SettingsModel::getSettings();
$currentUser = isset($currentUser) && is_array($currentUser) ? $currentUser : AuthHelper::getCurrentUser();
$isAdmin = isset($isAdmin) ? (bool)$isAdmin : AuthHelper::isAdmin();

$totalOpen = (int)($stats['operasi_aktif'] ?? 0);
$totalClosed = (int)($stats['operasi_selesai'] ?? 0);
$totalBra = (int)($stats['total_bra'] ?? 0);

$satkerNama = trim((string)($appSettings['satker_nama'] ?? 'Kantor Pencarian dan Pertolongan Kendari'));
$userName = trim((string)($currentUser['nama'] ?? ($isAdmin ? 'Administrator SAR' : 'Petugas Siaga SAR')));
$userRoleLabel = $isAdmin ? 'Administrator' : 'Operator Siaga';
$logoUrl = getAppLogoUrl();

$imgHero = assetUrl('assets/images/dashboard/hero-sar-ops.jpg');
$imgGis = assetUrl('assets/images/dashboard/feature-gis-tactical.jpg');
$imgDesk = assetUrl('assets/images/dashboard/feature-command-desk.jpg');

$workflowSteps = [
    [
        'idx' => '01',
        'title' => 'Laporan awal',
        'icon' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    ],
    [
        'idx' => '02',
        'title' => 'Ploting GIS',
        'icon' => '<polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/>',
    ],
    [
        'idx' => '03',
        'title' => 'RENOPS',
        'icon' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><line x1="12" y1="3" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="21"/><line x1="3" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="21" y2="12"/>',
    ],
    [
        'idx' => '04',
        'title' => 'BRA & LAPBANG',
        'icon' => '<path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/>',
    ],
    [
        'idx' => '05',
        'title' => 'Penutupan',
        'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
    ],
];

$advantages = [
    [
        'title' => 'Offline-first jaringan lokal',
        'icon' => '<rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><line x1="6" y1="6.5" x2="6.01" y2="6.5"/><line x1="6" y1="17.5" x2="6.01" y2="17.5"/>',
    ],
    [
        'title' => 'Sinkron GIS & dokumen',
        'icon' => '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/>',
    ],
    [
        'title' => 'Standar Basarnas & IAMSAR',
        'icon' => '<circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/>',
    ],
    [
        'title' => 'RBAC & penguncian arsip',
        'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ],
];

$benefits = [
    [
        'title' => 'Pelaporan hitungan menit',
        'icon' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
    ],
    [
        'title' => 'Koordinat DMS presisi',
        'icon' => '<circle cx="12" cy="12" r="8"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>',
    ],
    [
        'title' => 'Format seragam lintas regu',
        'icon' => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
    ],
    [
        'title' => 'Verifikasi QR instan',
        'icon' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3"/><path d="M14 20h6"/>',
    ],
];
?>

<style>
  /* ====================================================================== */
  /* DASHBOARD ARCHITECTURAL GRID, HOVER PHYSICS & ADAPTIVE THEME TOKENS    */
  /* ====================================================================== */
  .dash-root {
    --dash-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 14px 32px -20px rgba(15, 23, 42, 0.14);
    --dash-shadow-hover: 0 6px 14px -4px rgba(15, 23, 42, 0.07), 0 22px 44px -20px color-mix(in srgb, var(--primary) 26%, rgba(15, 23, 42, 0.16));
    --dash-spring: cubic-bezier(0.22, 1, 0.36, 1);
  }
  html.dark .dash-root,
  html[data-theme="dark"] .dash-root {
    --dash-shadow: 0 1px 2px rgba(2, 6, 23, 0.45), 0 18px 38px -20px rgba(2, 6, 23, 0.72), inset 0 1px 0 rgba(255, 255, 255, 0.05);
    --dash-shadow-hover: 0 8px 20px -6px rgba(2, 6, 23, 0.58), 0 24px 48px -20px color-mix(in srgb, var(--primary) 30%, rgba(2, 6, 23, 0.78)), inset 0 1px 0 rgba(255, 255, 255, 0.09);
  }

  /* Wide editorial display typography (2-line iron rule) */
  .dash-hero-title {
    font-size: clamp(1.75rem, 1.15rem + 2vw, 2.625rem);
    line-height: 1.08;
    letter-spacing: -0.032em;
    text-wrap: balance;
  }
  .dash-section-title {
    font-size: clamp(1.0625rem, 0.98rem + 0.35vw, 1.25rem);
    line-height: 1.25;
    letter-spacing: -0.022em;
    text-wrap: balance;
  }
  .dash-card-title {
    letter-spacing: -0.015em;
    text-wrap: balance;
  }
  .dash-num {
    font-variant-numeric: tabular-nums;
  }

  /* Interactive Hero & Operational Bar controls */
  .dash-accent-btn {
    background-color: var(--primary);
    color: #ffffff !important;
    border: 1px solid transparent;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12), 0 10px 20px -10px color-mix(in srgb, var(--primary) 62%, transparent);
  }
  .dash-accent-btn:hover {
    background-color: var(--primary-hover);
    color: #ffffff !important;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.14), 0 14px 24px -10px color-mix(in srgb, var(--primary) 72%, transparent);
  }
  .dash-ghost-btn {
    background-color: var(--bg-card);
    color: var(--text-main);
    border: 1px solid var(--border-color);
  }
  .dash-ghost-btn:hover {
    background-color: var(--bg-card-subtle);
    border-color: color-mix(in srgb, var(--primary) 38%, var(--border-color));
  }
  .dash-accent-text {
    color: var(--m-accent-text, var(--primary));
  }
  .dash-text-link {
    color: var(--m-accent-text, var(--primary));
    text-underline-offset: 4px;
    text-decoration-thickness: 1.5px;
  }
  .dash-text-link:hover {
    text-decoration: underline;
    text-decoration-thickness: 2px;
  }
  .dash-accent-btn:focus-visible,
  .dash-ghost-btn:focus-visible,
  .dash-text-link:focus-visible,
  .dash-metric-link:focus-visible,
  .dash-op-row:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }

  /* Unified architectural surfaces & icon badges */
  .dash-panel {
    background-color: var(--bg-card);
    border: 1px solid var(--border-color);
    box-shadow: var(--dash-shadow);
  }
  .dash-hairline-grid {
    background-color: var(--border-color);
    border: 1px solid var(--border-color);
    box-shadow: var(--dash-shadow);
  }
  .dash-hairline-cell {
    background-color: var(--bg-card);
  }
  .dash-hover-card {
    cursor: default;
    user-select: none;
  }
  .dash-surface {
    box-shadow: var(--dash-shadow);
  }
  .dash-tile-tinted {
    background-color: color-mix(in srgb, var(--primary) 7%, var(--bg-card));
    border-color: color-mix(in srgb, var(--primary) 26%, var(--border-color));
  }
  .dash-icon-box {
    background-color: color-mix(in srgb, var(--primary) 10%, var(--bg-card-subtle));
    color: var(--m-accent-text, var(--primary));
    border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);
  }
  .dash-icon-box-neutral {
    background-color: var(--bg-card-subtle);
    color: var(--text-muted);
    border: 1px solid var(--border-color);
  }
  .dash-media-img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  /* Unified workflow segment top accent indicator */
  .dash-step-cell {
    position: relative;
  }
  .dash-step-cell::before {
    content: "";
    position: absolute;
    top: 0;
    left: 1.25rem;
    right: 1.25rem;
    height: 2px;
    background-color: var(--primary);
    border-radius: 0 0 2px 2px;
    transform-origin: left center;
    transform: scaleX(0);
    opacity: 0;
  }

  /* Subtle ambient glow behind hero */
  .dash-hero-band {
    position: relative;
    isolation: isolate;
  }
  .dash-hero-band::before {
    content: "";
    position: absolute;
    inset: -1.5rem -1rem auto -1rem;
    height: 18rem;
    z-index: -1;
    pointer-events: none;
    background: radial-gradient(46rem 16rem at 18% 0%, color-mix(in srgb, var(--primary) 8%, transparent), transparent 70%);
  }

  /* GPU-only entrance & hover physics (strictly gated by prefers-reduced-motion) */
  @keyframes dash-rise {
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
    .dash-stagger {
      animation: dash-rise 420ms var(--dash-spring) both;
    }
    .dash-stagger-1 { animation-delay: 20ms; }
    .dash-stagger-2 { animation-delay: 80ms; }
    .dash-stagger-3 { animation-delay: 140ms; }
    .dash-stagger-4 { animation-delay: 200ms; }

    .dash-accent-btn,
    .dash-ghost-btn {
      transition: transform 180ms var(--dash-spring), background-color 160ms ease, border-color 160ms ease, box-shadow 180ms ease;
    }
    .dash-accent-btn:active,
    .dash-ghost-btn:active {
      transform: scale(0.98) translateY(1px);
    }
    .dash-surface,
    .dash-step-cell,
    .dash-point-row,
    .dash-metric-link,
    .dash-op-row {
      transition: transform 220ms var(--dash-spring), border-color 200ms ease, box-shadow 240ms ease, background-color 180ms ease;
    }
    .dash-step-cell::before {
      transition: transform 280ms var(--dash-spring), opacity 220ms ease;
    }
    .dash-icon-box,
    .dash-icon-box-neutral {
      transition: transform 220ms var(--dash-spring), background-color 180ms ease, border-color 180ms ease, color 180ms ease;
    }
    .dash-media-img {
      transition: transform 700ms ease-out;
    }
    .dash-card-arrow {
      transition: transform 200ms var(--dash-spring), color 180ms ease;
    }
  }
  @media (hover: hover) {
    .dash-hero-visual:hover .dash-media-img,
    .dash-surface:hover .dash-media-img {
      transform: scale(1.03);
    }
    .dash-surface:hover {
      border-color: color-mix(in srgb, var(--primary) 42%, var(--border-color));
      box-shadow: var(--dash-shadow-hover);
      transform: translateY(-2px);
    }
    .dash-surface:hover .dash-icon-box {
      transform: scale(1.05);
      background-color: color-mix(in srgb, var(--primary) 16%, var(--bg-card-subtle));
    }
    .dash-step-cell:hover {
      background-color: color-mix(in srgb, var(--primary) 4%, transparent);
    }
    .dash-step-cell:hover::before {
      transform: scaleX(1);
      opacity: 1;
    }
    .dash-step-cell:hover .dash-icon-box {
      transform: scale(1.06);
      background-color: color-mix(in srgb, var(--primary) 17%, var(--bg-card-subtle));
    }
    .dash-point-row:hover {
      background-color: color-mix(in srgb, var(--primary) 4%, transparent);
    }
    .dash-point-row:hover .dash-icon-box,
    .dash-point-row:hover .dash-icon-box-neutral {
      transform: scale(1.06);
      background-color: color-mix(in srgb, var(--primary) 14%, var(--bg-card-subtle));
      border-color: color-mix(in srgb, var(--primary) 26%, transparent);
      color: var(--m-accent-text, var(--primary));
    }
    .dash-metric-link:hover {
      background-color: color-mix(in srgb, var(--primary) 5%, transparent);
    }
    .dash-op-row:hover {
      background-color: color-mix(in srgb, var(--primary) 5%, transparent);
    }
    .dash-op-row:hover .dash-card-arrow {
      transform: translateX(3px);
      color: var(--m-accent-text, var(--primary));
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .dash-stagger,
    .dash-media-img,
    .dash-card-arrow,
    .dash-accent-btn,
    .dash-ghost-btn,
    .dash-surface,
    .dash-step-cell,
    .dash-step-cell::before,
    .dash-point-row,
    .dash-metric-link,
    .dash-op-row,
    .dash-icon-box,
    .dash-icon-box-neutral {
      animation: none !important;
      transition: none !important;
      transform: none !important;
    }
  }
</style>

<div class="dash-root overflow-x-hidden w-full max-w-full">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-16 space-y-12 lg:space-y-14">

    <!-- ================================================================== -->
    <!-- 1. WIDE EDITORIAL HERO + PANORAMA + UNIFIED OPERATIONAL BAR        -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashHeroTitle" class="dash-hero-band dash-stagger dash-stagger-1 space-y-6">

      <!-- Clean Wide Editorial Header (H1 max 2 lines + 2 aligned CTAs) -->
      <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6">
        <div class="space-y-3 max-w-3xl">
          <div class="flex items-center gap-2.5">
            <img
              src="<?= e($logoUrl) ?>"
              alt="Logo resmi <?= e($satkerNama) ?>"
              width="32"
              height="32"
              class="w-8 h-8 object-contain shrink-0"
              onerror="this.src='<?= BASE_URL ?>/assets/images/logo_basarnas.svg'"
            >
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">
              <span class="font-semibold text-slate-800 dark:text-slate-200"><?= e($satkerNama) ?></span>
              &middot; <?= e($userName) ?> (<?= e($userRoleLabel) ?>)
            </p>
          </div>

          <h1 id="dashHeroTitle" class="dash-hero-title font-bold text-slate-900 dark:text-white">
            Pelaporan cepat dan ploting taktis SAR.
          </h1>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
          <a href="<?= BASE_URL ?>/operasi.php" class="dash-accent-btn inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs sm:text-sm font-semibold whitespace-nowrap">
            <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            <span>Buka Operasi SAR</span>
            <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 5 7 7-7 7"/></svg>
          </a>
          <a href="<?= BASE_URL ?>/gis.php" class="dash-ghost-btn inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold whitespace-nowrap">
            <svg class="w-4 h-4 shrink-0 dash-accent-text" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
            <span>Ploting Peta GIS</span>
          </a>
        </div>
      </div>

      <!-- Unified Visual Stage + Integrated Operational Strip (Zero Overlapping Clutter) -->
      <div class="dash-panel rounded-[1.5rem] overflow-hidden">
        <!-- Full-Width Clean Basarnas Maritime Panorama -->
        <div class="dash-hero-visual group relative aspect-[16/9] sm:aspect-[21/9] lg:aspect-[24/9] overflow-hidden bg-slate-900">
          <img
            src="<?= e($imgHero) ?>"
            alt="Kapal patroli pencarian dan pertolongan melintasi perairan teluk Sulawesi Tenggara pada pagi hari"
            width="1200"
            height="896"
            fetchpriority="high"
            class="dash-media-img object-[50%_54%] contrast-[1.03] saturate-[0.96]"
          >
          <div class="pointer-events-none absolute inset-x-0 bottom-0 h-16 bg-gradient-to-t from-slate-950/30 to-transparent" aria-hidden="true"></div>
        </div>

        <!-- Unified Bottom Operational Bar: 3 Live KPIs (Left) | Active Ops (Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-slate-200 dark:divide-slate-800 border-t border-slate-200 dark:border-slate-800">

          <!-- Left (5 cols): 3 Relevant Operational KPIs -->
          <div class="lg:col-span-5 grid grid-cols-3 divide-x divide-slate-200/80 dark:divide-slate-800">
            <a href="<?= BASE_URL ?>/operasi.php?status=OPEN" class="dash-metric-link flex items-center gap-2.5 px-3.5 sm:px-5 py-4 no-underline min-w-0">
              <span class="hidden sm:flex w-8 h-8 rounded-lg items-center justify-center shrink-0 <?= $totalOpen > 0 ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/25' : 'dash-icon-box-neutral' ?>" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
              </span>
              <div class="min-w-0">
                <div class="flex items-center gap-1.5 text-[0.75rem] font-medium text-slate-500 dark:text-slate-400">
                  <?php if ($totalOpen > 0): ?>
                    <span class="radar-beacon-active w-2 h-2 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
                  <?php endif; ?>
                  <span class="truncate">Aktif</span>
                </div>
                <div class="mt-0.5 flex items-baseline gap-1.5">
                  <span class="dash-num text-xl font-bold font-mono text-slate-900 dark:text-white leading-none"><?= $totalOpen ?></span>
                  <span class="text-[0.6875rem] font-semibold <?= $totalOpen > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' ?>">
                    <?= $totalOpen > 0 ? 'OPEN' : 'Siaga' ?>
                  </span>
                </div>
              </div>
            </a>

            <a href="<?= BASE_URL ?>/operasi.php?status=CLOSED" class="dash-metric-link flex items-center gap-2.5 px-3.5 sm:px-5 py-4 no-underline min-w-0">
              <span class="hidden sm:flex dash-icon-box-neutral w-8 h-8 rounded-lg items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
              </span>
              <div class="min-w-0">
                <div class="text-[0.75rem] font-medium text-slate-500 dark:text-slate-400 truncate">Selesai</div>
                <div class="mt-0.5 flex items-baseline gap-1.5">
                  <span class="dash-num text-xl font-bold font-mono text-slate-900 dark:text-white leading-none"><?= $totalClosed ?></span>
                  <span class="text-[0.6875rem] font-medium text-slate-500 dark:text-slate-400">arsip</span>
                </div>
              </div>
            </a>

            <a href="<?= BASE_URL ?>/bra.php" class="dash-metric-link flex items-center gap-2.5 px-3.5 sm:px-5 py-4 no-underline min-w-0">
              <span class="hidden sm:flex dash-icon-box w-8 h-8 rounded-lg items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
              </span>
              <div class="min-w-0">
                <div class="text-[0.75rem] font-medium text-slate-500 dark:text-slate-400 truncate">BRA SAR</div>
                <div class="mt-0.5 flex items-baseline gap-1.5">
                  <span class="dash-num text-xl font-bold font-mono dash-accent-text leading-none"><?= $totalBra ?></span>
                  <span class="text-[0.6875rem] font-medium text-slate-500 dark:text-slate-400">berkas</span>
                </div>
              </div>
            </a>
          </div>

          <!-- Right (7 cols): Live Active Operations Strip OR Calm Standby -->
          <div class="lg:col-span-7 flex flex-col justify-center">
            <?php if (!empty($activeOperations)): ?>
              <div class="divide-y divide-slate-200/80 dark:divide-slate-800">
                <?php foreach ($activeOperations as $op): ?>
                  <?php
                    $opId = (int)($op['id_operasi'] ?? 0);
                    $opName = trim((string)($op['nama_operasi'] ?? 'Operasi SAR'));
                    $opLoc = trim((string)($op['lokasi_kejadian'] ?? ''));
                  ?>
                  <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $opId ?>" class="dash-op-row flex items-center justify-between gap-3 px-4 sm:px-5 py-3.5 no-underline">
                    <div class="flex items-center gap-3 min-w-0">
                      <span class="dash-icon-box w-8 h-8 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                      </span>
                      <div class="min-w-0">
                        <div class="flex items-center gap-2 min-w-0">
                          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
                          <p class="text-xs sm:text-[0.8125rem] font-semibold text-slate-900 dark:text-white truncate">
                            <?= e($opName) ?>
                          </p>
                        </div>
                        <?php if ($opLoc !== ''): ?>
                          <p class="text-[0.75rem] text-slate-500 dark:text-slate-400 truncate mt-0.5"><?= e($opLoc) ?></p>
                        <?php endif; ?>
                      </div>
                    </div>
                    <svg class="dash-card-arrow w-4 h-4 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 5 7 7-7 7"/></svg>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="px-4 sm:px-5 py-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                  <span class="dash-icon-box-neutral w-8 h-8 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                  </span>
                  <p class="text-xs sm:text-[0.8125rem] font-semibold text-slate-800 dark:text-slate-200">
                    Status siaga terkendali &middot; <span class="font-normal text-slate-500 dark:text-slate-400">Tidak ada operasi aktif</span>
                  </p>
                </div>
                <a href="<?= BASE_URL ?>/operasi.php?status=CLOSED" class="dash-text-link inline-flex items-center gap-1 text-xs font-semibold whitespace-nowrap">
                  <span>Lihat arsip</span>
                  <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m13 5 7 7-7 7"/></svg>
                </a>
              </div>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </section>

    <!-- ================================================================== -->
    <!-- 2. ALUR KERJA (1 STRIP TERPADU 5 TAHAP, HOVER-ONLY, NON-KLIK)      -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashWorkflowTitle" class="dash-stagger dash-stagger-2 space-y-4">
      <h2 id="dashWorkflowTitle" class="dash-section-title font-bold text-slate-900 dark:text-white">
        Alur kerja
      </h2>

      <ol class="dash-hairline-grid rounded-2xl overflow-hidden grid grid-cols-2 sm:grid-cols-5 gap-px list-none p-0 m-0">
        <?php foreach ($workflowSteps as $idx => $step): ?>
          <li class="dash-hairline-cell flex <?= $idx === 4 ? 'col-span-2 sm:col-span-1' : '' ?>">
            <article class="dash-hover-card dash-step-cell flex-1 flex flex-col justify-between gap-4 p-4 sm:p-5">
              <div class="flex items-center justify-between gap-2">
                <span class="dash-icon-box w-9 h-9 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><?= $step['icon'] ?></svg>
                </span>
                <span class="dash-num font-mono text-xs font-semibold text-slate-400 dark:text-slate-500">
                  <?= e($step['idx']) ?>
                </span>
              </div>
              <h3 class="dash-card-title text-xs sm:text-sm font-semibold text-slate-900 dark:text-white">
                <?= e($step['title']) ?>
              </h3>
            </article>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>

    <!-- ================================================================== -->
    <!-- 3. FITUR UTAMA (BENTO 12x2 GAPLESS, HOVER-ONLY, NON-KLIK)          -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashFeaturesTitle" class="dash-stagger dash-stagger-3 space-y-4">
      <h2 id="dashFeaturesTitle" class="dash-section-title font-bold text-slate-900 dark:text-white">
        Fitur utama
      </h2>

      <div class="grid grid-cols-1 lg:grid-cols-12 grid-flow-dense gap-4">

        <!-- Left Centerpiece (7 cols x 2 rows): Ploting Peta GIS -->
        <article class="bento-card-v1 dash-hover-card dash-surface lg:col-span-7 lg:row-span-2 rounded-[1.375rem] overflow-hidden flex flex-col">
          <div class="aspect-[16/10] lg:aspect-auto lg:flex-1 min-h-[15rem] overflow-hidden bg-slate-100 dark:bg-slate-800">
            <img
              src="<?= e($imgGis) ?>"
              alt="Tampilan udara garis lintasan pencarian maritim IAMSAR di perairan teluk"
              width="1200"
              height="670"
              loading="lazy"
              decoding="async"
              class="dash-media-img"
            >
          </div>
          <div class="p-4 sm:p-5 flex items-center gap-3.5 border-t border-slate-100 dark:border-slate-800/80">
            <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
            </span>
            <div class="min-w-0">
              <h3 class="dash-card-title text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                Ploting peta GIS
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                Search Area, pola IAMSAR, ETA &amp; KML
              </p>
            </div>
          </div>
        </article>

        <!-- Top-Right (5 cols x 1 row): Operasi SAR & RENOPS -->
        <article class="bento-card-v1 dash-hover-card dash-surface lg:col-span-5 rounded-[1.375rem] overflow-hidden flex flex-col">
          <div class="aspect-[16/9] overflow-hidden bg-slate-100 dark:bg-slate-800">
            <img
              src="<?= e($imgDesk) ?>"
              alt="Peta laut terlipat, jangka navigasi maritim, radio VHF taktis, dan papan kerja operasi SAR"
              width="1200"
              height="896"
              loading="lazy"
              decoding="async"
              class="dash-media-img"
            >
          </div>
          <div class="p-4 sm:p-5 flex items-center gap-3.5 border-t border-slate-100 dark:border-slate-800/80">
            <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>
            </span>
            <div class="min-w-0">
              <h3 class="dash-card-title text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                Operasi SAR &amp; RENOPS
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                RENOPS, LAPBANG &amp; cetak resmi
              </p>
            </div>
          </div>
        </article>

        <!-- Bottom-Right (5 cols x 1 row): Paired Compact Modules (BRA + QR) -->
        <div class="lg:col-span-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-1 gap-4">
          <article class="bento-card-v1 dash-hover-card dash-surface dash-tile-tinted p-4 sm:p-5 rounded-2xl flex items-center justify-between gap-3">
            <div class="flex items-center gap-3.5 min-w-0">
              <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
              </span>
              <div class="min-w-0">
                <h3 class="dash-card-title text-sm font-bold text-slate-900 dark:text-white truncate">
                  Berita radio SAR (BRA)
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-400 truncate">
                  Telegram TWU/TWB &amp; PDF
                </p>
              </div>
            </div>
            <span class="dash-num font-mono text-xs font-semibold px-2 py-0.5 rounded-md bg-white/75 dark:bg-slate-900/60 dash-accent-text shrink-0">
              <?= $totalBra ?>
            </span>
          </article>

          <article class="bento-card-v1 dash-hover-card dash-surface p-4 sm:p-5 rounded-2xl flex items-center gap-3.5">
            <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3"/><path d="M14 20h6"/></svg>
            </span>
            <div class="min-w-0">
              <h3 class="dash-card-title text-sm font-bold text-slate-900 dark:text-white truncate">
                Verifikasi QR digital
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                Validasi publik RENOPS &amp; BRA resmi
              </p>
            </div>
          </article>
        </div>

      </div>
    </section>

    <!-- ================================================================== -->
    <!-- 4. KEUNGGULAN & MANFAAT (1 MATRIKS TERPADU 2 KOLOM, HOVER-ONLY)    -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashBenefitsTitle" class="dash-stagger dash-stagger-4 space-y-4">
      <h2 id="dashBenefitsTitle" class="dash-section-title font-bold text-slate-900 dark:text-white">
        Keunggulan dan manfaat
      </h2>

      <div class="dash-panel rounded-2xl overflow-hidden grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-slate-200 dark:divide-slate-800">
        <!-- Kolom Kiri: Keunggulan -->
        <div>
          <div class="px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35">
            <h3 class="text-xs font-semibold dash-accent-text">Keunggulan</h3>
          </div>
          <ul class="divide-y divide-slate-200/75 dark:divide-slate-800/80 list-none p-0 m-0">
            <?php foreach ($advantages as $adv): ?>
              <li class="dash-hover-card dash-point-row flex items-center gap-3.5 px-5 py-3.5">
                <span class="dash-icon-box w-8 h-8 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><?= $adv['icon'] ?></svg>
                </span>
                <span class="text-xs sm:text-[0.8125rem] font-semibold text-slate-800 dark:text-slate-100">
                  <?= e($adv['title']) ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- Kolom Kanan: Manfaat -->
        <div>
          <div class="px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35">
            <h3 class="text-xs font-semibold text-slate-600 dark:text-slate-400">Manfaat</h3>
          </div>
          <ul class="divide-y divide-slate-200/75 dark:divide-slate-800/80 list-none p-0 m-0">
            <?php foreach ($benefits as $ben): ?>
              <li class="dash-hover-card dash-point-row flex items-center gap-3.5 px-5 py-3.5">
                <span class="dash-icon-box-neutral w-8 h-8 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><?= $ben['icon'] ?></svg>
                </span>
                <span class="text-xs sm:text-[0.8125rem] font-semibold text-slate-800 dark:text-slate-100">
                  <?= e($ben['title']) ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </section>

  </div>
</div>
