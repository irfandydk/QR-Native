<?php
/**
 * QR-SAR - Dashboard Beranda (Arsitektur Editorial, Visual Fotografi SAR & Komando Terpadu)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Struktur 4 blok berbeda pada satu rel grid adaptif (Klasik, Minimal Glass, Terang & Gelap):
 * 1. Asymmetric Split Editorial Hero + Panorama Basarnas + Rel Komando 4 KPI & Operasi Aktif
 * 2. Rel Siklus Operasional 6 Tahap (hover-only, non-klik)
 * 3. Bento Fitur Utama 12x2 Asimetris dengan 4 Fotografi Generatif & Tile Verifikasi QR (hover-only)
 * 4. Matriks 2x2 Keunggulan & Manfaat (hover-only, non-klik)
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$stats = isset($stats) && is_array($stats) ? $stats : OperasiModel::getStats();
$activeOperations = isset($activeOperations) && is_array($activeOperations) ? $activeOperations : [];
$dokStats = isset($dokStats) && is_array($dokStats)
    ? $dokStats
    : (class_exists('OperasiDokumentasiModel')
        ? OperasiDokumentasiModel::getArchiveStats()
        : ['total' => 0, 'umum' => 0, 'operasi' => 0, 'image' => 0, 'video' => 0]);
$appSettings = isset($appSettings) && is_array($appSettings) ? $appSettings : SettingsModel::getSettings();
$currentUser = isset($currentUser) && is_array($currentUser) ? $currentUser : AuthHelper::getCurrentUser();
$isAdmin = isset($isAdmin) ? (bool)$isAdmin : AuthHelper::isAdmin();

$totalOpen = (int)($stats['operasi_aktif'] ?? 0);
$totalClosed = (int)($stats['operasi_selesai'] ?? 0);
$totalBra = (int)($stats['total_bra'] ?? 0);
$totalDok = (int)($dokStats['total'] ?? 0);

$satkerNama = trim((string)($appSettings['satker_nama'] ?? 'Kantor Pencarian dan Pertolongan Kendari'));
$userName = trim((string)($currentUser['nama'] ?? ($isAdmin ? 'Administrator SAR' : 'Petugas Siaga SAR')));
$userRoleLabel = $isAdmin ? 'Administrator' : 'Operator Siaga';
$logoUrl = getAppLogoUrl();

$imgHero = assetUrl('assets/images/dashboard/hero-sar-ops.jpg');
$imgGis = assetUrl('assets/images/dashboard/feature-gis-tactical.jpg');
$imgDokumentasi = assetUrl('assets/images/dashboard/feature-dokumentasi-kolase.jpg');
$imgDesk = assetUrl('assets/images/dashboard/feature-command-desk.jpg');
$imgBra = assetUrl('assets/images/dashboard/feature-bra-radio.jpg');

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
        'title' => 'Dokumentasi',
        'icon' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
    ],
    [
        'idx' => '06',
        'title' => 'Penutupan',
        'icon' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/>',
    ],
];

$advantages = [
    [
        'title' => 'Offline-first jaringan lokal',
        'desc' => 'Tetap beroperasi stabil tanpa ketergantungan aset cloud eksternal.',
        'icon' => '<rect x="2" y="3" width="20" height="7" rx="2"/><rect x="2" y="14" width="20" height="7" rx="2"/><line x1="6" y1="6.5" x2="6.01" y2="6.5"/><line x1="6" y1="17.5" x2="6.01" y2="17.5"/>',
    ],
    [
        'title' => 'Sinkron GIS dan dokumen',
        'desc' => 'Area pencarian dan lintasan peta langsung terhubung ke RENOPS.',
        'icon' => '<path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/>',
    ],
    [
        'title' => 'Kolase foto dan video terarsip',
        'desc' => 'Susun dokumentasi 4 hingga 12 slot dengan suara dan sunting ulang.',
        'icon' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/>',
    ],
    [
        'title' => 'RBAC dan penguncian arsip',
        'desc' => 'Kontrol akses peran operator dan administrator menjaga integritas data.',
        'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
    ],
];

$benefits = [
    [
        'title' => 'Pelaporan hitungan menit',
        'desc' => 'Format WhatsApp, naskah dinas, dan PDF siap kirim tanpa ketik ulang.',
        'icon' => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
    ],
    [
        'title' => 'Koordinat DMS presisi',
        'desc' => 'Konversi otomatis titik LKP, jarak tempuh, dan arah radial pangkalan.',
        'icon' => '<circle cx="12" cy="12" r="8"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>',
    ],
    [
        'title' => 'Format seragam lintas regu',
        'desc' => 'Standar baku Basarnas dan IAMSAR terjaga pada setiap giliran siaga.',
        'icon' => '<rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><rect x="13" y="13" width="8" height="8" rx="1.5"/>',
    ],
    [
        'title' => 'Verifikasi QR instan',
        'desc' => 'Pindai QR cetak untuk memastikan keabsahan dokumen RENOPS dan BRA.',
        'icon' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3"/><path d="M14 20h6"/>',
    ],
];
?>

<style>
  /* ====================================================================== */
  /* DASHBOARD ARCHITECTURAL SYSTEM, HOVER PHYSICS & ADAPTIVE THEME TOKENS  */
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

  /* Display & Editorial Typography */
  .dash-hero-title {
    font-size: clamp(1.875rem, 1.3rem + 1.85vw, 2.75rem);
    line-height: 1.08;
    letter-spacing: -0.032em;
    text-wrap: balance;
  }
  .dash-section-title {
    font-size: clamp(1.125rem, 1rem + 0.4vw, 1.3125rem);
    line-height: 1.24;
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

  /* Interactive Hero & Command Rail Controls */
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

  /* Architectural Surfaces, Hairline Grids & Container-Aware Cards */
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
  .dash-feature-card {
    container-type: inline-size;
    overflow: clip;
  }
  .dash-surface {
    box-shadow: var(--dash-shadow);
  }
  .dash-tile-tinted {
    background-color: color-mix(in srgb, var(--primary) 7%, var(--bg-card));
    border-color: color-mix(in srgb, var(--primary) 26%, var(--border-color));
    background-image: radial-gradient(color-mix(in srgb, var(--primary) 16%, transparent) 1px, transparent 1px);
    background-size: 16px 16px;
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
  .dash-hero-frame {
    overflow: clip;
    box-shadow: var(--dash-shadow);
  }

  /* Workflow Top Hairline Sweep */
  .dash-step-cell {
    position: relative;
  }
  .dash-step-cell::before {
    content: "";
    position: absolute;
    top: 0;
    left: 1rem;
    right: 1rem;
    height: 2px;
    background-color: var(--primary);
    border-radius: 0 0 2px 2px;
    transform-origin: left center;
    transform: scaleX(0);
    opacity: 0;
  }

  /* Subtle Ambient Wash Behind Hero */
  .dash-hero-band {
    position: relative;
    isolation: isolate;
  }
  .dash-hero-band::before {
    content: "";
    position: absolute;
    inset: -1.5rem -1rem auto -1rem;
    height: 20rem;
    z-index: -1;
    pointer-events: none;
    background: radial-gradient(48rem 18rem at 16% 0%, color-mix(in srgb, var(--primary) 8%, transparent), transparent 70%);
  }

  /* GPU-Only Entrance & Hover Motion (Gated by prefers-reduced-motion) */
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
    .dash-point-tile,
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
      transition: transform 650ms var(--dash-spring);
    }
    .dash-card-arrow {
      transition: transform 200ms var(--dash-spring), color 180ms ease;
    }
  }
  @media (hover: hover) {
    .dash-hero-frame:hover .dash-media-img,
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
    .dash-point-tile:hover {
      background-color: color-mix(in srgb, var(--primary) 4%, var(--bg-card));
    }
    .dash-point-tile:hover .dash-icon-box,
    .dash-point-tile:hover .dash-icon-box-neutral {
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
    .dash-point-tile,
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

<div class="dash-root overflow-x-clip w-full max-w-full">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6 pb-16 space-y-12 lg:space-y-14">

    <!-- ================================================================== -->
    <!-- 1. ASYMMETRIC SPLIT EDITORIAL HERO + INTEGRATED 4-KPI COMMAND RAIL -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashHeroTitle" class="dash-hero-band dash-stagger dash-stagger-1 space-y-6">

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 lg:gap-8 items-center">
        <!-- Left (5 cols): Satker Identity, Wide 2-Line Headline, Value Copy, 2 CTAs -->
        <div class="lg:col-span-5 space-y-5">
          <div class="flex items-center gap-3 min-w-0">
            <img
              src="<?= e($logoUrl) ?>"
              alt="Logo resmi <?= e($satkerNama) ?>"
              width="36"
              height="36"
              class="w-9 h-9 object-contain shrink-0"
              onerror="this.src='<?= assetUrl('assets/images/logo_basarnas.svg') ?>'"
            >
            <div class="min-w-0 leading-tight">
              <p class="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">
                <?= e($satkerNama) ?>
              </p>
              <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                <?= e($userName) ?>, <?= e($userRoleLabel) ?>
              </p>
            </div>
          </div>

          <h1 id="dashHeroTitle" class="dash-hero-title font-bold text-slate-900 dark:text-white">
            Pelaporan cepat, ploting GIS, dan dokumentasi SAR.
          </h1>

          <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed max-w-[46ch]">
            Kelola laporan operasi, peta pencarian IAMSAR, berita radio, dan kolase dokumentasi lapangan dalam satu ruang kerja.
          </p>

          <div class="flex flex-wrap items-center gap-2.5 pt-1">
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

        <!-- Right (7 cols): Full-Bleed Clean Basarnas Vessel Panorama -->
        <div class="lg:col-span-7">
          <div class="dash-hero-frame rounded-[1.5rem] border border-slate-200/90 dark:border-slate-800 bg-slate-900">
            <img
              src="<?= e($imgHero) ?>"
              alt="Kapal patroli pencarian dan pertolongan melintasi perairan teluk Sulawesi Tenggara pada pagi hari"
              width="1200"
              height="896"
              fetchpriority="high"
              class="dash-media-img aspect-[16/10] object-[50%_54%] contrast-[1.03] saturate-[0.96]"
            >
          </div>
        </div>
      </div>

      <!-- Unified Operational Command Rail: 4 Live KPIs (Left) | Active Operations (Right) -->
      <div class="dash-panel rounded-2xl overflow-hidden grid grid-cols-1 lg:grid-cols-12 divide-y lg:divide-y-0 lg:divide-x divide-slate-200 dark:divide-slate-800">

        <!-- Left (7 cols): 4 Clickable Operational KPIs -->
        <div class="lg:col-span-7 grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-slate-200/80 dark:divide-slate-800">
          <a href="<?= BASE_URL ?>/operasi.php?status=OPEN" class="dash-metric-link flex items-center gap-2.5 px-4 py-4 no-underline min-w-0">
            <span class="hidden sm:flex w-8 h-8 rounded-lg items-center justify-center shrink-0 <?= $totalOpen > 0 ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/25' : 'dash-icon-box-neutral' ?>" aria-hidden="true">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <div class="min-w-0">
              <div class="flex items-center gap-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">
                <?php if ($totalOpen > 0): ?>
                  <span class="radar-beacon-active w-2 h-2 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="truncate">Aktif</span>
              </div>
              <div class="mt-0.5 flex items-baseline gap-1.5">
                <span class="dash-num text-xl font-bold font-mono text-slate-900 dark:text-white leading-none"><?= $totalOpen ?></span>
                <span class="text-[11px] font-semibold <?= $totalOpen > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-500 dark:text-slate-400' ?>">
                  <?= $totalOpen > 0 ? 'OPEN' : 'Siaga' ?>
                </span>
              </div>
            </div>
          </a>

          <a href="<?= BASE_URL ?>/operasi.php?status=CLOSED" class="dash-metric-link flex items-center gap-2.5 px-4 py-4 no-underline min-w-0">
            <span class="hidden sm:flex dash-icon-box-neutral w-8 h-8 rounded-lg items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
            </span>
            <div class="min-w-0">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">Selesai</div>
              <div class="mt-0.5 flex items-baseline gap-1.5">
                <span class="dash-num text-xl font-bold font-mono text-slate-900 dark:text-white leading-none"><?= $totalClosed ?></span>
                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">arsip</span>
              </div>
            </div>
          </a>

          <a href="<?= BASE_URL ?>/bra.php" class="dash-metric-link flex items-center gap-2.5 px-4 py-4 no-underline min-w-0">
            <span class="hidden sm:flex dash-icon-box w-8 h-8 rounded-lg items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
            </span>
            <div class="min-w-0">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">BRA SAR</div>
              <div class="mt-0.5 flex items-baseline gap-1.5">
                <span class="dash-num text-xl font-bold font-mono dash-accent-text leading-none"><?= $totalBra ?></span>
                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">berkas</span>
              </div>
            </div>
          </a>

          <a href="<?= BASE_URL ?>/dokumentasi.php" class="dash-metric-link flex items-center gap-2.5 px-4 py-4 no-underline min-w-0">
            <span class="hidden sm:flex dash-icon-box w-8 h-8 rounded-lg items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
            </span>
            <div class="min-w-0">
              <div class="text-xs font-medium text-slate-500 dark:text-slate-400 truncate">Dokumentasi</div>
              <div class="mt-0.5 flex items-baseline gap-1.5">
                <span class="dash-num text-xl font-bold font-mono dash-accent-text leading-none"><?= $totalDok ?></span>
                <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">kolase</span>
              </div>
            </div>
          </a>
        </div>

        <!-- Right (5 cols): Active Operations Feed OR Standby State -->
        <div class="lg:col-span-5 flex flex-col justify-center">
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
                      <p class="text-xs sm:text-[13px] font-semibold text-slate-900 dark:text-white truncate">
                        <?= e($opName) ?>
                      </p>
                      <?php if ($opLoc !== ''): ?>
                        <p class="text-xs text-slate-500 dark:text-slate-400 truncate mt-0.5"><?= e($opLoc) ?></p>
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
                <p class="text-xs sm:text-[13px] font-semibold text-slate-800 dark:text-slate-200">
                  Status siaga terkendali
                  <span class="block sm:inline font-normal text-slate-500 dark:text-slate-400 sm:ml-1.5">Tidak ada operasi aktif</span>
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
    </section>

    <!-- ================================================================== -->
    <!-- 2. ALUR KERJA (6 TAHAP TERPADU TERMASUK DOKUMENTASI, HOVER-ONLY)   -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashWorkflowTitle" class="dash-stagger dash-stagger-2 space-y-4">
      <h2 id="dashWorkflowTitle" class="dash-section-title font-bold text-slate-900 dark:text-white">
        Alur kerja
      </h2>

      <ol class="dash-hairline-grid rounded-2xl overflow-hidden grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-px list-none p-0 m-0">
        <?php foreach ($workflowSteps as $step): ?>
          <li class="dash-hairline-cell flex">
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
    <!-- 3. FITUR UTAMA (BENTO 12x2 ASIMETRIS, 5 MODUL, 4 FOTO GENERATIF)   -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashFeaturesTitle" class="dash-stagger dash-stagger-3 space-y-4">
      <h2 id="dashFeaturesTitle" class="dash-section-title font-bold text-slate-900 dark:text-white">
        Fitur utama
      </h2>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-4">

        <!-- Row 1 Left (7 cols): Ploting peta GIS -->
        <article class="bento-card-v1 dash-hover-card dash-surface dash-feature-card md:col-span-2 lg:col-span-7 rounded-[1.375rem] flex flex-col">
          <img
            src="<?= e($imgGis) ?>"
            alt="Tampilan udara garis lintasan pencarian maritim IAMSAR di perairan teluk"
            width="1200"
            height="669"
            loading="lazy"
            decoding="async"
            class="dash-media-img aspect-[16/9] lg:flex-1 lg:min-h-[17.5rem] bg-slate-100 dark:bg-slate-800"
          >
          <div class="p-4 sm:p-5 flex items-center gap-3.5 border-t border-slate-100 dark:border-slate-800/80">
            <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
            </span>
            <div class="min-w-0">
              <h3 class="dash-card-title text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                Ploting peta GIS
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                Search Area, pola IAMSAR, ETA, dan ekspor KML
              </p>
            </div>
          </div>
        </article>

        <!-- Row 1 Right (5 cols): Dokumentasi Kolase Foto & Video (FITUR BARU) -->
        <article class="bento-card-v1 dash-hover-card dash-surface dash-feature-card md:col-span-1 lg:col-span-5 rounded-[1.375rem] flex flex-col">
          <img
            src="<?= e($imgDokumentasi) ?>"
            alt="Enam cetakan foto dokumentasi operasi pencarian pesisir tersusun rapi bersama kamera lapangan tahan air di atas meja kerja"
            width="1200"
            height="896"
            loading="lazy"
            decoding="async"
            class="dash-media-img aspect-[16/10] lg:flex-1 lg:min-h-[17.5rem] bg-slate-100 dark:bg-slate-800"
          >
          <div class="p-4 sm:p-5 flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800/80">
            <div class="flex items-center gap-3.5 min-w-0">
              <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
              </span>
              <div class="min-w-0">
                <h3 class="dash-card-title text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                  Dokumentasi kolase
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                  Kolase foto, video bersuara, dan arsip kronologis
                </p>
              </div>
            </div>
            <span class="dash-num font-mono text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 dash-accent-text shrink-0">
              <?= $totalDok ?>
            </span>
          </div>
        </article>

        <!-- Row 2 Left (5 cols): Operasi SAR & RENOPS -->
        <article class="bento-card-v1 dash-hover-card dash-surface dash-feature-card md:col-span-1 lg:col-span-5 rounded-[1.375rem] flex flex-col">
          <img
            src="<?= e($imgDesk) ?>"
            alt="Peta laut terlipat, jangka navigasi maritim, radio VHF taktis, dan papan kerja operasi SAR"
            width="1200"
            height="896"
            loading="lazy"
            decoding="async"
            class="dash-media-img aspect-[16/10] bg-slate-100 dark:bg-slate-800"
          >
          <div class="p-4 sm:p-5 flex items-center gap-3.5 border-t border-slate-100 dark:border-slate-800/80">
            <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6"/><path d="M9 16h4"/></svg>
            </span>
            <div class="min-w-0">
              <h3 class="dash-card-title text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                Operasi SAR &amp; RENOPS
              </h3>
              <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                Laporan awal, RENOPS, LAPBANG, dan cetak resmi
              </p>
            </div>
          </div>
        </article>

        <!-- Row 2 Center (4 cols): Berita radio SAR (BRA) -->
        <article class="bento-card-v1 dash-hover-card dash-surface dash-feature-card md:col-span-1 lg:col-span-4 rounded-[1.375rem] flex flex-col">
          <img
            src="<?= e($imgBra) ?>"
            alt="Perangkat radio komunikasi maritim dengan gagang telepon dan buku catatan pengiriman di meja komando"
            width="1200"
            height="896"
            loading="lazy"
            decoding="async"
            class="dash-media-img aspect-[16/10] bg-slate-100 dark:bg-slate-800"
          >
          <div class="p-4 sm:p-5 flex items-center justify-between gap-3 border-t border-slate-100 dark:border-slate-800/80">
            <div class="flex items-center gap-3.5 min-w-0">
              <span class="dash-icon-box w-10 h-10 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4.9 19.1C1 15.2 1 8.8 4.9 4.9"/><path d="M7.8 16.2c-2.3-2.3-2.3-6.1 0-8.5"/><circle cx="12" cy="12" r="2"/><path d="M16.2 7.8c2.3 2.3 2.3 6.1 0 8.5"/><path d="M19.1 4.9C23 8.8 23 15.2 19.1 19.1"/></svg>
              </span>
              <div class="min-w-0">
                <h3 class="dash-card-title text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate">
                  Berita radio SAR (BRA)
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 truncate">
                  Telegram TWU/TWB dan PDF
                </p>
              </div>
            </div>
            <span class="dash-num font-mono text-xs font-semibold px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 dash-accent-text shrink-0">
              <?= $totalBra ?>
            </span>
          </div>
        </article>

        <!-- Row 2 Right (3 cols): Verifikasi QR digital (Tinted Architectural Contrast Tile) -->
        <article class="bento-card-v1 dash-hover-card dash-surface dash-tile-tinted dash-feature-card md:col-span-1 lg:col-span-3 rounded-[1.375rem] p-5 sm:p-6 flex flex-col justify-between gap-6">
          <span class="dash-icon-box w-11 h-11 rounded-xl flex items-center justify-center shrink-0" aria-hidden="true">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3z"/><path d="M20 14v3"/><path d="M14 20h6"/></svg>
          </span>

          <div class="space-y-2">
            <h3 class="dash-card-title text-base font-bold text-slate-900 dark:text-white">
              Verifikasi QR digital
            </h3>
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              Validasi publik keaslian dokumen RENOPS dan berita radio resmi langsung dari lembar cetak.
            </p>
          </div>

          <div class="pt-3 border-t border-slate-200/70 dark:border-slate-700/60 flex items-center justify-between text-[11px] font-mono font-semibold text-slate-600 dark:text-slate-300">
            <span>RENOPS</span>
            <span>BRA SAR</span>
            <span class="dash-accent-text">Publik</span>
          </div>
        </article>

      </div>
    </section>

    <!-- ================================================================== -->
    <!-- 4. KEUNGGULAN & MANFAAT (DUAL 2x2 HAIRLINE SPEC MATRIX, HOVER)     -->
    <!-- ================================================================== -->
    <section aria-labelledby="dashBenefitsTitle" class="dash-stagger dash-stagger-4 space-y-4">
      <h2 id="dashBenefitsTitle" class="dash-section-title font-bold text-slate-900 dark:text-white">
        Keunggulan dan manfaat
      </h2>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- Panel Kiri: Keunggulan -->
        <div class="dash-panel rounded-2xl overflow-hidden flex flex-col">
          <div class="px-5 py-3.5 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35">
            <h3 class="text-xs font-semibold dash-accent-text">Keunggulan</h3>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-slate-200/80 dark:bg-slate-800 flex-1">
            <?php foreach ($advantages as $adv): ?>
              <article class="dash-hover-card dash-point-tile dash-hairline-cell p-4 sm:p-5 flex flex-col gap-3">
                <span class="dash-icon-box w-8 h-8 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><?= $adv['icon'] ?></svg>
                </span>
                <div class="space-y-1">
                  <h4 class="text-xs sm:text-[13px] font-semibold text-slate-900 dark:text-white leading-snug">
                    <?= e($adv['title']) ?>
                  </h4>
                  <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    <?= e($adv['desc']) ?>
                  </p>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Panel Kanan: Manfaat -->
        <div class="dash-panel rounded-2xl overflow-hidden flex flex-col">
          <div class="px-5 py-3.5 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35">
            <h3 class="text-xs font-semibold text-slate-700 dark:text-slate-300">Manfaat</h3>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-px bg-slate-200/80 dark:bg-slate-800 flex-1">
            <?php foreach ($benefits as $ben): ?>
              <article class="dash-hover-card dash-point-tile dash-hairline-cell p-4 sm:p-5 flex flex-col gap-3">
                <span class="dash-icon-box-neutral w-8 h-8 rounded-lg flex items-center justify-center shrink-0" aria-hidden="true">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><?= $ben['icon'] ?></svg>
                </span>
                <div class="space-y-1">
                  <h4 class="text-xs sm:text-[13px] font-semibold text-slate-900 dark:text-white leading-snug">
                    <?= e($ben['title']) ?>
                  </h4>
                  <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                    <?= e($ben['desc']) ?>
                  </p>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </section>

  </div>
</div>
