<?php
/**
 * QR-SAR - Daftar Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Tata letak operasional tunggal (tanpa duplikasi header/sub-header),
 * efisien secara vertikal, mudah dipindai, dan adaptif penuh terhadap
 * tema Klasik (4 palet aksen), Minimal Glass, serta Mode Terang/Gelap.
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$stats = OperasiModel::getStats();
$operasis = OperasiModel::getAll();
$braStats = BraModel::getStats();
$latestBraList = BraModel::getLatest(1);

$currentUser = AuthHelper::getCurrentUser();
$isAdmin = AuthHelper::isAdmin();
$initialStatus = isset($_GET['status']) && in_array(strtoupper((string)$_GET['status']), ['ALL', 'OPEN', 'CLOSED'], true)
    ? strtoupper((string)$_GET['status'])
    : 'OPEN';

// Metrik operasional utama
$totalOpen = (int)($stats['operasi_aktif'] ?? 0);
$totalClosed = (int)($stats['operasi_selesai'] ?? 0);
$totalBra = (int)($braStats['total'] ?? 0);

$latestBra = !empty($latestBraList) ? $latestBraList[0] : null;
$latestBraId = !empty($latestBra['id_bra']) ? (int)$latestBra['id_bra'] : 0;
$latestBraNumber = $latestBra['no_bra'] ?? '-';
$latestBraHref = $latestBraId > 0 ? (BASE_URL . '/bra.php?view=' . $latestBraId) : (BASE_URL . '/bra.php');
$latestBraTime = $latestBra
    ? ($latestBra['twu'] ?: (!empty($latestBra['created_at']) ? TemplateHelper::formatJamWITA($latestBra['created_at']) . ' WITA' : 'Tercatat'))
    : '';
$latestBraSubtext = $latestBra
    ? (trim((string)($latestBra['kategori'] ?? 'BRA')) . ', ' . $latestBraTime)
    : 'Belum ada dokumen BRA terbit';

// Daftar bulan unik dari data operasi
$monthNamesId = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
    7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$monthMap = [];
foreach ($operasis as $op) {
    $dateStr = $op['waktu_kejadian'] ?: ($op['waktu_lapor'] ?: ($op['waktu_berangkat'] ?: ($op['created_at'] ?? '')));
    if (!$dateStr) continue;
    $time = strtotime($dateStr);
    if ($time === false) continue;
    $year = (int)date('Y', $time);
    $month = (int)date('n', $time);
    $key = sprintf('%04d-%02d', $year, $month);
    $label = $monthNamesId[$month] . ' ' . $year;
    if (!isset($monthMap[$key])) {
        $monthMap[$key] = [
            'key' => $key,
            'label' => $label,
            'count' => 0,
            'time' => strtotime("{$year}-{$month}-01")
        ];
    }
    $monthMap[$key]['count']++;
}
uasort($monthMap, fn($a, $b) => $b['time'] <=> $a['time']);
?>

<style>
  .ops-accent-btn {
    background-color: var(--primary);
    color: #ffffff !important;
    border: 1px solid transparent;
  }
  .ops-accent-btn:hover {
    background-color: var(--primary-hover);
    color: #ffffff !important;
  }
  .ops-accent-text {
    color: var(--m-accent-text, var(--primary));
  }
  .ops-status-tabs button.bg-orange-600 {
    background-color: var(--primary);
    color: #ffffff;
  }
  .ops-month-active {
    background-color: color-mix(in srgb, var(--primary) 10%, var(--bg-input)) !important;
    border-color: color-mix(in srgb, var(--primary) 55%, transparent) !important;
    color: var(--m-accent-text, var(--primary)) !important;
    font-weight: 700 !important;
  }
  .ops-accent-btn:focus-visible,
  .ops-ghost-btn:focus-visible,
  .ops-metric-trigger:focus-visible,
  .ops-action-btn:focus-visible,
  .ops-status-tabs button:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }
  .ops-card {
    position: relative;
  }
  .ops-card-strip {
    position: absolute;
    left: 0;
    top: 12px;
    bottom: 12px;
    width: 3px;
    border-radius: 0 3px 3px 0;
  }
  @media (prefers-reduced-motion: no-preference) {
    .ops-accent-btn,
    .ops-ghost-btn,
    .ops-action-btn {
      transition: transform 160ms cubic-bezier(0.16, 1, 0.3, 1), background-color 160ms ease, border-color 160ms ease, color 160ms ease;
    }
    .ops-accent-btn:active,
    .ops-ghost-btn:active,
    .ops-action-btn:active {
      transform: scale(0.98);
    }
    .ops-card-title,
    .ops-card-cue {
      transition: color 180ms ease, transform 200ms cubic-bezier(0.16, 1, 0.3, 1);
    }
  }
  @media (hover: hover) {
    .ops-card:hover {
      border-color: color-mix(in srgb, var(--primary) 42%, transparent);
    }
    .ops-card:hover .ops-card-title {
      color: var(--m-accent-text, var(--primary));
    }
    .ops-card:hover .ops-card-cue {
      color: var(--m-accent-text, var(--primary));
      transform: translateX(3px);
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .ops-accent-btn,
    .ops-ghost-btn,
    .ops-action-btn,
    .ops-card-title,
    .ops-card-cue {
      transition: none !important;
      transform: none !important;
    }
  }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-10 space-y-4">
  <h1 class="sr-only">Daftar Operasi SAR</h1>

  <!-- ================================================================== -->
  <!-- 1. COMPACT 4-METRIC INSTRUMENT RAIL (NO DUPLICATE HEADER)          -->
  <!-- ================================================================== -->
  <section aria-label="Ringkasan statistik operasi SAR" class="pb-3.5 border-b border-slate-200 dark:border-slate-800">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-y-3 gap-x-5 lg:gap-x-0 lg:divide-x lg:divide-slate-200 dark:lg:divide-slate-800">

      <!-- Metric 1: Operasi Aktif -->
      <div class="lg:pr-5">
        <button
          type="button"
          onclick="setStatusFilter('OPEN')"
          class="ops-metric-trigger w-full text-left rounded-lg cursor-pointer group"
        >
          <div class="flex items-center gap-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400">
            <?php if ($totalOpen > 0): ?>
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
            <?php endif; ?>
            <span>Operasi Aktif</span>
          </div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none">
              <?= $totalOpen ?>
            </span>
            <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">OPEN</span>
          </div>
        </button>
      </div>

      <!-- Metric 2: Operasi Selesai -->
      <div class="lg:px-5">
        <button
          type="button"
          onclick="setStatusFilter('CLOSED')"
          class="ops-metric-trigger w-full text-left rounded-lg cursor-pointer group"
        >
          <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Operasi Selesai</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none">
              <?= $totalClosed ?>
            </span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">CLOSED</span>
          </div>
        </button>
      </div>

      <!-- Metric 3: Berita Radio (BRA) -->
      <div class="lg:px-5">
        <a
          href="<?= BASE_URL ?>/bra.php"
          class="ops-metric-trigger block rounded-lg group"
        >
          <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Berita Radio (BRA)</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span class="text-xl sm:text-2xl font-bold font-mono tracking-tight ops-accent-text leading-none">
              <?= $totalBra ?>
            </span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">terbit</span>
          </div>
        </a>
      </div>

      <!-- Metric 4: BRA Terakhir -->
      <div class="lg:pl-5 min-w-0">
        <a
          href="<?= e($latestBraHref) ?>"
          class="ops-metric-trigger block rounded-lg group min-w-0"
        >
          <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">BRA Terakhir</div>
          <div class="mt-0.5 text-sm font-bold font-mono tracking-tight text-slate-900 dark:text-white truncate leading-snug" title="<?= e($latestBraNumber) ?>">
            <?= e($latestBraNumber) ?>
          </div>
          <div class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
            <?= e($latestBraSubtext) ?>
          </div>
        </a>
      </div>

    </div>
  </section>

  <!-- ================================================================== -->
  <!-- 2. UNIFIED COMMAND & FILTER BAR (SINGLE EFFICIENT ROW)             -->
  <!-- ================================================================== -->
  <section aria-label="Filter, pencarian, dan aksi operasi" class="space-y-2.5">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">

      <!-- Left: Search Input + Month Filter -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 flex-1 min-w-0">
        <div class="relative flex-1 min-w-0 lg:max-w-[270px]">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input
            type="text"
            id="dashSearchInput"
            placeholder="Cari operasi, lokasi, koordinat..."
            aria-label="Cari operasi, lokasi, atau koordinat"
            class="control control-sm w-full placeholder:text-slate-400 dark:placeholder:text-slate-500 pl-9 pr-8"
          />
          <button
            type="button"
            id="dashSearchClearBtn"
            onclick="clearDashSearch()"
            aria-label="Hapus pencarian"
            title="Hapus Pencarian"
            class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hidden cursor-pointer"
          >
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <div class="relative flex items-center shrink-0">
          <div class="absolute left-2.5 pointer-events-none text-slate-400">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
          </div>
          <select
            id="dashMonthSelect"
            onchange="onMonthFilterChange(this.value)"
            aria-label="Filter Bulan Operasi SAR"
            class="control control-sm w-full sm:w-auto font-medium cursor-pointer appearance-none pl-8 pr-7"
          >
            <option value="ALL">Semua Bulan</option>
            <?php foreach ($monthMap as $mOpt): ?>
              <option value="<?= e($mOpt['key']) ?>">
                <?= e($mOpt['label']) ?> (<?= $mOpt['count'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
          <div class="absolute right-2 pointer-events-none text-slate-400">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
          </div>
        </div>
      </div>

      <!-- Right: Status Tabs + Refresh + Primary CTA -->
      <div class="flex flex-wrap sm:flex-nowrap items-center justify-between sm:justify-end gap-2 shrink-0">

        <!-- Status Filter Tabs -->
        <div class="ops-status-tabs flex items-center max-w-full overflow-x-auto rounded-xl p-1 text-xs border bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-800" role="tablist" aria-label="Filter status operasi">
          <button
            type="button"
            id="tab-status-ALL"
            onclick="setStatusFilter('ALL')"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg <?= $initialStatus === 'ALL' ? 'font-bold bg-orange-600 text-white shadow-sm' : 'font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?> transition-colors cursor-pointer whitespace-nowrap"
          >
            <span>Semua</span>
            <span class="tab-badge px-1.5 py-0.5 rounded-full text-[11px] font-mono leading-none <?= $initialStatus === 'ALL' ? 'bg-white/20 text-white font-bold' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold' ?>">
              <?= count($operasis) ?>
            </span>
          </button>
          <button
            type="button"
            id="tab-status-OPEN"
            onclick="setStatusFilter('OPEN')"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg <?= $initialStatus === 'OPEN' ? 'font-bold bg-orange-600 text-white shadow-sm' : 'font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?> transition-colors cursor-pointer whitespace-nowrap"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0" aria-hidden="true"></span>
            <span>OPEN</span>
            <span class="tab-badge px-1.5 py-0.5 rounded-full text-[11px] font-mono leading-none <?= $initialStatus === 'OPEN' ? 'bg-white/20 text-white font-bold' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold' ?>">
              <?= $totalOpen ?>
            </span>
          </button>
          <button
            type="button"
            id="tab-status-CLOSED"
            onclick="setStatusFilter('CLOSED')"
            class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg <?= $initialStatus === 'CLOSED' ? 'font-bold bg-orange-600 text-white shadow-sm' : 'font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?> transition-colors cursor-pointer whitespace-nowrap"
          >
            <span>CLOSED</span>
            <span class="tab-badge px-1.5 py-0.5 rounded-full text-[11px] font-mono leading-none <?= $initialStatus === 'CLOSED' ? 'bg-white/20 text-white font-bold' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold' ?>">
              <?= $totalClosed ?>
            </span>
          </button>
        </div>

        <span class="hidden sm:block w-px h-6 bg-slate-200 dark:bg-slate-800 shrink-0" aria-hidden="true"></span>

        <!-- Action Cluster -->
        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            id="btnRefreshData"
            onclick="refreshDashboardData()"
            aria-label="Segarkan data real-time"
            title="Segarkan Data Real-Time"
            class="ops-ghost-btn inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 cursor-pointer shrink-0"
          >
            <svg id="refreshSpinnerIcon" class="w-4 h-4 ops-accent-text" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
            <span id="refreshLabel" class="sr-only">Segarkan</span>
          </button>

          <a
            id="btn-tambah-operasi-baru"
            href="<?= BASE_URL ?>/operasi_form.php"
            class="ops-accent-btn inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl text-xs font-bold whitespace-nowrap shadow-xs cursor-pointer"
          >
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>Operasi Baru</span>
          </a>
        </div>

      </div>
    </div>

    <!-- Active Filter Info Bar (Muncul saat filter non-default aktif) -->
    <div id="filterActiveInfoBar" class="px-3.5 py-2 rounded-xl border items-center justify-between gap-2 text-xs bg-slate-50 dark:bg-slate-900/70 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hidden">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="text-slate-500 dark:text-slate-400">Filter:</span>

        <span id="chipMonth" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hidden">
          <span id="chipMonthText">Bulan</span>
          <button type="button" onclick="resetMonthFilter()" aria-label="Hapus filter bulan" class="text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer">
            <svg class="w-3 h-3" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </span>

        <span id="chipStatus" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hidden">
          <span id="chipStatusText">Status</span>
          <button type="button" onclick="setStatusFilter('OPEN')" aria-label="Kembalikan ke status OPEN" class="text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer">
            <svg class="w-3 h-3" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </span>

        <span id="chipSearch" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 hidden">
          <span id="chipSearchText">Cari</span>
          <button type="button" onclick="clearDashSearch()" aria-label="Hapus kata kunci pencarian" class="text-slate-400 hover:text-slate-700 dark:hover:text-white cursor-pointer">
            <svg class="w-3 h-3" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </span>

        <span id="filteredCountText" class="text-slate-500 dark:text-slate-400 font-mono text-[11px]">
          (<?= count($operasis) ?> dari <?= count($operasis) ?> operasi)
        </span>
      </div>

      <button
        type="button"
        onclick="resetAllFilters()"
        class="text-[11px] font-semibold ops-accent-text hover:underline shrink-0 cursor-pointer"
      >
        Reset Filter
      </button>
    </div>
  </section>

  <!-- ================================================================== -->
  <!-- 3. OPERATIONS LIST (COMPACT OPERATIONAL DOSSIER CARDS)             -->
  <!-- ================================================================== -->
  <section id="operationsListContainer" aria-label="Daftar operasi SAR" class="space-y-3">

    <?php
    $initialVisibleCount = 0;
    foreach ($operasis as $opCheck) {
        $checkStatus = $opCheck['status'] ?? 'OPEN';
        if ($initialStatus === 'ALL' || $checkStatus === $initialStatus) {
            $initialVisibleCount++;
        }
    }
    ?>

    <!-- Empty State -->
    <div id="operationsEmptyState" class="bento-card-v1 p-10 sm:p-12 text-center rounded-2xl space-y-3.5 <?= $initialVisibleCount === 0 ? '' : 'hidden' ?>">
      <div class="w-11 h-11 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center border border-slate-200 dark:border-slate-700">
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
      </div>
      <div class="max-w-sm mx-auto space-y-1">
        <h2 id="emptyStateTitle" class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">
          <?= $initialStatus === 'OPEN' ? 'Tidak ada operasi SAR aktif' : 'Tidak ada data operasi ditemukan' ?>
        </h2>
        <p id="emptyStateSubtitle" class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          <?= $initialStatus === 'OPEN' ? 'Saat ini tidak ada operasi SAR yang berstatus OPEN.' : 'Belum ada operasi SAR yang tercatat pada tampilan ini.' ?>
        </p>
      </div>
      <div id="emptyStateActions" class="flex flex-wrap items-center justify-center gap-2.5 pt-1">
        <button
          type="button"
          id="emptyStateResetBtn"
          onclick="resetAllFilters()"
          class="hidden ops-ghost-btn px-4 py-2 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 cursor-pointer"
        >
          Reset Filter
        </button>
        <a
          href="<?= BASE_URL ?>/operasi_form.php"
          id="emptyStateInitBtn"
          class="ops-accent-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold shadow-xs cursor-pointer"
        >
          <span>Inisiasi Operasi Baru</span>
        </a>
      </div>
    </div>

    <!-- Operation Cards -->
    <?php foreach ($operasis as $op):
      $isOpen = ($op['status'] ?? 'OPEN') === 'OPEN';
      $isReopened = !empty($op['waktu_reopen']);
      $isInitialMatch = ($initialStatus === 'ALL') || (($op['status'] ?? 'OPEN') === $initialStatus);
      $pelaksanaan = TemplateHelper::hitungPelaksanaanOperasi($op);
      $resp = TemplateHelper::hitungResponseTime($op['waktu_lapor'], $op['waktu_berangkat']);

      $waktuBerangkat = $op['waktu_berangkat'] ?: ($op['waktu_kejadian'] ?: $op['waktu_lapor']);
      $tglBerangkat = $waktuBerangkat ? TemplateHelper::formatTanggalIndo($waktuBerangkat) : '-';
      $jamBerangkat = $waktuBerangkat ? TemplateHelper::formatJamWITA($waktuBerangkat) : '-';

      $tglLapor = !empty($op['waktu_lapor']) ? TemplateHelper::formatTanggalIndo($op['waktu_lapor']) : '-';
      $jamLapor = !empty($op['waktu_lapor']) ? TemplateHelper::formatJamWITA($op['waktu_lapor']) : '-';

      // Month key untuk filter klien
      $dateStr = $op['waktu_kejadian'] ?: ($op['waktu_lapor'] ?: ($op['waktu_berangkat'] ?: ($op['created_at'] ?? '')));
      $monthKey = $dateStr ? date('Y-m', strtotime($dateStr)) : '';

      $s = (int)($op['korban_s'] ?? 0);
      $md = (int)($op['korban_md'] ?? 0);
      $dp = (int)($op['korban_dp'] ?? 0);
      $h = (int)($op['korban_h'] ?? 0);
      $identified = (int)($op['total_korban_count'] ?? 0);
      // Aturan sama dengan SarCalculationService::hitungStatistikKorban: POB belum terdata dihitung DP
      $totalPob = max((int)($op['jumlah_pob'] ?? 0), $identified);
      $dp += $totalPob - $identified;

      $totalRenops = (int)($op['total_renops'] ?? 0);
      $totalLapbang = (int)($op['total_laporan'] ?? 0);

      $stripColor = $isOpen
          ? ($isReopened ? 'bg-amber-500' : 'bg-emerald-500')
          : 'bg-slate-300 dark:bg-slate-700';
    ?>
    <article
      class="operation-item-card bento-card-v1 ops-card rounded-2xl pl-5 pr-4 sm:pl-5 sm:pr-5 py-3.5 sm:py-4 cursor-pointer <?= $isInitialMatch ? '' : 'hidden' ?>"
      data-id="<?= $op['id_operasi'] ?>"
      data-status="<?= e($op['status']) ?>"
      data-month="<?= e($monthKey) ?>"
      data-search="<?= e(strtolower($op['nama_operasi'] . ' ' . $op['lokasi_kejadian'] . ' ' . ($op['koordinat'] ?? ''))) ?>"
      onclick="navigateToDetail(<?= $op['id_operasi'] ?>)"
    >
      <span class="ops-card-strip <?= $stripColor ?>" aria-hidden="true"></span>

      <!-- Row 1: Quiet Status Meta & Single Response Time Readout -->
      <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1.5">
        <div class="flex flex-wrap items-center gap-2 text-xs">
          <?php if ($isOpen): ?>
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
              <span>OPEN</span>
            </span>
          <?php else: ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-semibold tracking-wide bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
              <span>CLOSED</span>
            </span>
          <?php endif; ?>

          <?php if ($isReopened): ?>
            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400" title="Operasi SAR dibuka kembali pada <?= TemplateHelper::formatTanggalIndo($op['waktu_reopen']) ?>">
              DIPERPANJANG
            </span>
          <?php endif; ?>

          <span class="font-mono text-xs font-bold <?= $isOpen ? 'ops-accent-text' : 'text-slate-500 dark:text-slate-400' ?>" title="Hari Pelaksanaan SAR: <?= e($pelaksanaan['h_display']) ?>">
            <?= e($pelaksanaan['h_display']) ?>
          </span>

          <span class="font-mono text-[11px] text-slate-400 dark:text-slate-500">
            #<?= $op['id_operasi'] ?>
          </span>
        </div>

        <!-- Single Clean Response Time Readout -->
        <div class="flex items-center gap-1.5 text-xs">
          <?php if ($resp['is_quick']): ?>
            <span class="inline-flex items-center gap-1 font-mono text-xs font-semibold text-emerald-700 dark:text-emerald-400" title="Response Time: <?= $resp['formatted'] ?> (Lapor <?= $jamLapor ?> WITA ke Berangkat <?= $jamBerangkat ?> WITA)">
              <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
              <span>RT <?= $resp['formatted'] ?></span>
            </span>
            <span class="hidden sm:inline font-mono text-[11px] text-slate-400 dark:text-slate-500">
              (<?= $jamLapor ?> &rarr; <?= $jamBerangkat ?>)
            </span>
          <?php elseif ($resp['minutes'] !== null): ?>
            <span class="inline-flex items-center gap-1 font-mono text-xs font-semibold text-amber-700 dark:text-amber-400" title="Response Time: <?= $resp['formatted'] ?> (Lapor <?= $jamLapor ?> WITA ke Berangkat <?= $jamBerangkat ?> WITA)">
              <svg class="w-3.5 h-3.5 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>RT <?= $resp['formatted'] ?></span>
            </span>
            <span class="hidden sm:inline font-mono text-[11px] text-slate-400 dark:text-slate-500">
              (<?= $jamLapor ?> &rarr; <?= $jamBerangkat ?>)
            </span>
          <?php else: ?>
            <span class="text-[11px] text-slate-400 dark:text-slate-500">
              Respons belum lengkap
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Row 2: Mission Title + Location & Coordinates -->
      <div class="mt-2 space-y-1">
        <h2 class="ops-card-title text-base sm:text-[17px] font-bold tracking-tight text-slate-900 dark:text-white leading-snug">
          <?= e($op['nama_operasi']) ?>
        </h2>

        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
          <span class="inline-flex items-center gap-1.5 font-medium text-slate-700 dark:text-slate-300">
            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/></svg>
            <span><?= e($op['lokasi_kejadian']) ?></span>
          </span>
          <?php if (!empty($op['koordinat'])): ?>
            <span class="font-mono text-[11px] text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-800/90 px-2 py-0.5 rounded-md">
              <?= e($op['koordinat']) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Row 3: Hairline 4-Column Operational Spec Strip -->
      <dl class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 grid grid-cols-2 lg:grid-cols-4 gap-x-5 gap-y-2.5 text-xs">
        <div>
          <dt class="text-[11px] text-slate-500 dark:text-slate-400">Waktu Berangkat</dt>
          <dd class="mt-0.5 font-semibold text-slate-800 dark:text-slate-200 truncate">
            <?= $tglBerangkat ?>
          </dd>
          <dd class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
            <?= $waktuBerangkat ? $jamBerangkat . ' WITA' : 'Belum berangkat' ?>
          </dd>
        </div>

        <div>
          <dt class="text-[11px] text-slate-500 dark:text-slate-400">Pelaksanaan</dt>
          <dd class="mt-0.5 font-semibold truncate <?= $pelaksanaan['is_extended'] ? 'text-amber-700 dark:text-amber-400' : 'text-slate-800 dark:text-slate-200' ?>">
            <?= e($pelaksanaan['teks']) ?>
          </dd>
          <dd class="text-[11px] text-slate-500 dark:text-slate-400 truncate">
            <?= e($pelaksanaan['subteks']) ?>
          </dd>
        </div>

        <div>
          <dt class="text-[11px] text-slate-500 dark:text-slate-400">Korban (<?= $totalPob ?> Orang)</dt>
          <dd class="mt-1 flex items-center gap-1.5 flex-wrap font-mono text-[11px] font-semibold">
            <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400" title="Selamat">
              <?= $s ?> S
            </span>
            <span class="px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-700 dark:text-rose-400" title="Meninggal Dunia">
              <?= $md ?> MD
            </span>
            <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-700 dark:text-amber-400" title="Dalam Pencarian">
              <?= $dp ?> DP
            </span>
            <?php if ($h > 0): ?>
              <span class="px-1.5 py-0.5 rounded bg-slate-200/80 dark:bg-slate-800 text-slate-700 dark:text-slate-300" title="Hilang">
                <?= $h ?> H
              </span>
            <?php endif; ?>
          </dd>
        </div>

        <div>
          <dt class="text-[11px] text-slate-500 dark:text-slate-400">Dokumen Taktis</dt>
          <dd class="mt-1 flex items-center gap-1.5 flex-wrap font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-300">
            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800">
              <?= $totalRenops ?> Renops
            </span>
            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800">
              <?= $totalLapbang ?> Lapbang
            </span>
          </dd>
        </div>
      </dl>

      <!-- Row 4: Compact Action Footer -->
      <div class="mt-2.5 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400 min-w-0">
          <?php if ($tglLapor !== '-'): ?>
            <span class="truncate">Lapor <?= $tglLapor ?>, <?= $jamLapor ?> WITA</span>
          <?php else: ?>
            <span>Buka detail operasi</span>
          <?php endif; ?>
          <span class="ops-card-cue hidden sm:inline-flex items-center font-semibold text-slate-400 dark:text-slate-500 shrink-0" aria-hidden="true">
            &rarr;
          </span>
        </div>

        <!-- Action Buttons Group (stopPropagation) -->
        <div class="flex items-center gap-1.5 shrink-0" onclick="event.stopPropagation()">

          <!-- Salin WA Button -->
          <button
            type="button"
            id="btnCopyWa-<?= $op['id_operasi'] ?>"
            onclick="copyOpsWaText(event, <?= $op['id_operasi'] ?>)"
            aria-label="Salin teks WhatsApp laporan awal"
            title="Salin Teks WA Laporan Awal"
            class="ops-action-btn inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 cursor-pointer"
          >
            <svg id="iconCopy-<?= $op['id_operasi'] ?>" class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <svg id="iconCheck-<?= $op['id_operasi'] ?>" class="w-3.5 h-3.5 text-emerald-500 hidden" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            <span class="hidden sm:inline">Salin WA</span>
          </button>

          <!-- Lihat Format WA Button -->
          <button
            type="button"
            data-id="<?= (int)$op['id_operasi'] ?>"
            data-nama="<?= e($op['nama_operasi']) ?>"
            onclick="openOpsWaModal(this.dataset.id, this.dataset.nama)"
            aria-label="Lihat format WhatsApp resmi"
            title="Lihat Format WA Resmi"
            class="ops-action-btn p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white cursor-pointer"
          >
            <svg class="w-4 h-4 ops-accent-text" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>

          <!-- Edit Operasi Button -->
          <a
            href="<?= BASE_URL ?>/operasi_form.php?id=<?= $op['id_operasi'] ?>"
            aria-label="Edit data operasi"
            title="Edit Data Operasi"
            class="ops-action-btn p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white cursor-pointer"
          >
            <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5"/></svg>
          </a>

          <!-- Delete Button (Admin Only) -->
          <?php if ($isAdmin): ?>
            <button
              type="button"
              data-id="<?= (int)$op['id_operasi'] ?>"
              data-nama="<?= e($op['nama_operasi']) ?>"
              onclick="deleteOpsHandler(event, this.dataset.id, this.dataset.nama)"
              aria-label="Hapus operasi"
              title="Hapus Operasi"
              class="ops-action-btn p-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-500 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 hover:border-rose-200 dark:hover:border-rose-900 cursor-pointer"
            >
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          <?php endif; ?>

        </div>
      </div>
    </article>
    <?php endforeach; ?>

  </section>

</div>

<!-- ================================================================== -->
<!-- MODAL: LIHAT FORMAT WA RESMI                                       -->
<!-- ================================================================== -->
<div id="modalOpsWaPreview" class="modal-overlay">
  <div class="modal-container max-w-2xl">
    <div class="modal-header">
      <div class="min-w-0">
        <h3 class="font-bold text-sm sm:text-base text-slate-900 dark:text-white">
          Format WhatsApp Laporan Awal
        </h3>
        <p id="modalOpsWaSubtitle" class="text-xs ops-accent-text font-semibold mt-0.5 truncate"></p>
      </div>
      <button type="button" class="ops-action-btn p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer" onclick="closeModal('modalOpsWaPreview')" aria-label="Tutup modal" title="Tutup">
        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body space-y-3">
      <textarea
        id="modalOpsWaTextarea"
        readonly
        rows="14"
        aria-label="Naskah laporan awal format WhatsApp"
        class="control control-sm w-full font-mono leading-relaxed select-all"
      ></textarea>
    </div>
    <div class="modal-footer flex items-center justify-between">
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modalOpsWaPreview')">
        Tutup
      </button>
      <button type="button" class="btn btn-primary btn-sm flex items-center gap-1.5" onclick="copyModalWaText()">
        <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        <span>Salin Naskah WhatsApp</span>
      </button>
    </div>
  </div>
</div>

<!-- ================================================================== -->
<!-- CLIENT-SIDE FILTER & CONTROLLER JAVASCRIPT                         -->
<!-- ================================================================== -->
<script>
let currentStatusFilter = '<?= $initialStatus ?>';
let currentMonthFilter = 'ALL';
let currentSearchQuery = '';

function navigateToDetail(id) {
  window.location.href = '<?= BASE_URL ?>/operasi_detail.php?id=' + id;
}

// Search Handler
const searchInput = document.getElementById('dashSearchInput');
const clearSearchBtn = document.getElementById('dashSearchClearBtn');

if (searchInput) {
  searchInput.addEventListener('input', function(e) {
    currentSearchQuery = e.target.value.trim().toLowerCase();
    if (clearSearchBtn) {
      if (currentSearchQuery) {
        clearSearchBtn.classList.remove('hidden');
      } else {
        clearSearchBtn.classList.add('hidden');
      }
    }
    applyDashFilters();
  });
}

function clearDashSearch() {
  if (searchInput) searchInput.value = '';
  if (clearSearchBtn) clearSearchBtn.classList.add('hidden');
  currentSearchQuery = '';
  applyDashFilters();
}

function onMonthFilterChange(val) {
  currentMonthFilter = val;
  const select = document.getElementById('dashMonthSelect');
  if (select) {
    if (val !== 'ALL') {
      select.classList.add('ops-month-active');
    } else {
      select.classList.remove('ops-month-active');
    }
  }
  applyDashFilters();
}

function resetMonthFilter() {
  const select = document.getElementById('dashMonthSelect');
  if (select) select.value = 'ALL';
  onMonthFilterChange('ALL');
}

function setStatusFilter(status) {
  currentStatusFilter = status;
  ['ALL', 'OPEN', 'CLOSED'].forEach(st => {
    const btn = document.getElementById('tab-status-' + st);
    if (!btn) return;
    const badge = btn.querySelector('.tab-badge');
    if (st === status) {
      btn.className = 'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg font-bold transition-colors bg-orange-600 text-white shadow-sm cursor-pointer whitespace-nowrap';
      if (badge) {
        badge.className = 'tab-badge px-1.5 py-0.5 rounded-full text-[11px] font-mono leading-none bg-white/20 text-white font-bold';
      }
    } else {
      btn.className = 'inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg font-semibold transition-colors text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer whitespace-nowrap';
      if (badge) {
        badge.className = 'tab-badge px-1.5 py-0.5 rounded-full text-[11px] font-mono leading-none bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold';
      }
    }
  });
  applyDashFilters();
}

function resetAllFilters() {
  clearDashSearch();
  resetMonthFilter();
  setStatusFilter('OPEN');
}

function applyDashFilters() {
  const cards = document.querySelectorAll('.operation-item-card');
  const emptyState = document.getElementById('operationsEmptyState');
  const infoBar = document.getElementById('filterActiveInfoBar');

  const chipMonth = document.getElementById('chipMonth');
  const chipMonthText = document.getElementById('chipMonthText');
  const chipStatus = document.getElementById('chipStatus');
  const chipStatusText = document.getElementById('chipStatusText');
  const chipSearch = document.getElementById('chipSearch');
  const chipSearchText = document.getElementById('chipSearchText');
  const countText = document.getElementById('filteredCountText');

  let visibleCount = 0;
  const totalCount = cards.length;

  cards.forEach(card => {
    const cardStatus = card.getAttribute('data-status') || '';
    const cardMonth = card.getAttribute('data-month') || '';
    const cardSearch = card.getAttribute('data-search') || '';

    const matchStatus = currentStatusFilter === 'ALL' || cardStatus === currentStatusFilter;
    const matchMonth = currentMonthFilter === 'ALL' || cardMonth === currentMonthFilter;
    const matchSearch = !currentSearchQuery || cardSearch.includes(currentSearchQuery);

    if (matchStatus && matchMonth && matchSearch) {
      card.classList.remove('hidden');
      visibleCount++;
    } else {
      card.classList.add('hidden');
    }
  });

  // Empty state handling
  if (emptyState) {
    if (visibleCount === 0) {
      emptyState.classList.remove('hidden');
      const title = document.getElementById('emptyStateTitle');
      const subtitle = document.getElementById('emptyStateSubtitle');
      const resetBtn = document.getElementById('emptyStateResetBtn');
      const isFiltered = currentSearchQuery || currentMonthFilter !== 'ALL' || currentStatusFilter !== 'OPEN';

      if (title && subtitle) {
        if (isFiltered) {
          title.textContent = 'Tidak ada data operasi sesuai filter';
          subtitle.textContent = 'Ubah atau reset kata kunci dan filter yang sedang aktif.';
        } else {
          title.textContent = 'Tidak ada operasi SAR aktif';
          subtitle.textContent = 'Saat ini tidak ada operasi SAR yang berstatus OPEN.';
        }
      }

      if (resetBtn) {
        if (isFiltered) {
          resetBtn.classList.remove('hidden');
        } else {
          resetBtn.classList.add('hidden');
        }
      }
    } else {
      emptyState.classList.add('hidden');
    }
  }

  // Active info bar handling
  const isNonDefaultStatus = currentStatusFilter !== 'OPEN';
  const hasQueryOrMonth = Boolean(currentSearchQuery || currentMonthFilter !== 'ALL');
  const isFilterActive = hasQueryOrMonth || isNonDefaultStatus;

  if (infoBar) {
    if (isFilterActive) {
      infoBar.classList.remove('hidden');
      infoBar.classList.add('flex');

      if (currentMonthFilter !== 'ALL') {
        const sel = document.getElementById('dashMonthSelect');
        const monthLabel = sel ? sel.options[sel.selectedIndex]?.text?.replace(/\s*\(\d+\)$/, '') : currentMonthFilter;
        chipMonthText.textContent = 'Bulan: ' + monthLabel;
        chipMonth.classList.remove('hidden');
      } else {
        chipMonth.classList.add('hidden');
      }

      if (currentStatusFilter !== 'OPEN') {
        chipStatusText.textContent = 'Status: ' + (currentStatusFilter === 'ALL' ? 'Semua Operasi' : currentStatusFilter);
        chipStatus.classList.remove('hidden');
      } else {
        chipStatus.classList.add('hidden');
      }

      if (currentSearchQuery) {
        chipSearchText.textContent = 'Cari: "' + currentSearchQuery + '"';
        chipSearch.classList.remove('hidden');
      } else {
        chipSearch.classList.add('hidden');
      }

      if (countText) {
        countText.textContent = `(${visibleCount} dari ${totalCount} operasi)`;
      }
    } else {
      infoBar.classList.add('hidden');
      infoBar.classList.remove('flex');
    }
  }
}

// Refresh Data Handler
async function refreshDashboardData() {
  const icon = document.getElementById('refreshSpinnerIcon');
  const label = document.getElementById('refreshLabel');
  if (icon) icon.classList.add('animate-spin');
  if (label) label.textContent = 'Menyinkronkan...';

  try {
    await fetch('<?= BASE_URL ?>/api/index.php?path=/auth/ping&_t=' + Date.now());
    showToast('Data operasi SAR dan BRA berhasil disinkronkan!', 'success');
    setTimeout(() => location.reload(), 400);
  } catch (e) {
    showToast('Gagal menyinkronkan data real-time', 'error');
  } finally {
    setTimeout(() => {
      if (icon) icon.classList.remove('animate-spin');
      if (label) label.textContent = 'Segarkan';
    }, 600);
  }
}

// Copy WA Handler
async function copyOpsWaText(e, id) {
  e.stopPropagation();
  const iconCopy = document.getElementById('iconCopy-' + id);
  const iconCheck = document.getElementById('iconCheck-' + id);

  try {
    const res = await fetch('<?= BASE_URL ?>/api/operasi/' + id);
    const json = await res.json();
    if (json.success && json.data && json.data.full_text_wa) {
      await copyToClipboard(json.data.full_text_wa, 'Naskah Laporan Awal berhasil disalin!');
      if (iconCopy && iconCheck) {
        iconCopy.classList.add('hidden');
        iconCheck.classList.remove('hidden');
        setTimeout(() => {
          iconCopy.classList.remove('hidden');
          iconCheck.classList.add('hidden');
        }, 2000);
      }
    } else {
      showToast('Gagal mengambil naskah laporan WA', 'error');
    }
  } catch {
    showToast('Terjadi kesalahan jaringan', 'error');
  }
}

// View Format WA Modal
async function openOpsWaModal(id, title) {
  document.getElementById('modalOpsWaSubtitle').textContent = title;
  const textarea = document.getElementById('modalOpsWaTextarea');
  textarea.value = 'Memuat naskah Laporan Awal...';
  openModal('modalOpsWaPreview');

  try {
    const res = await fetch('<?= BASE_URL ?>/api/operasi/' + id);
    const json = await res.json();
    if (json.success && json.data && json.data.full_text_wa) {
      textarea.value = json.data.full_text_wa;
    } else {
      textarea.value = 'Gagal memuat format naskah WhatsApp.';
    }
  } catch {
    textarea.value = 'Terjadi kesalahan jaringan saat memuat naskah.';
  }
}

async function copyModalWaText() {
  const textarea = document.getElementById('modalOpsWaTextarea');
  if (textarea && textarea.value) {
    await copyToClipboard(textarea.value, 'Naskah Laporan Awal WhatsApp Berhasil Disalin!');
  }
}

// Delete Handler with SweetAlert2
async function deleteOpsHandler(e, id, name) {
  e.stopPropagation();
  const confirmed = await confirmAction(
    `Hapus Operasi "${name}"?`,
    `Apakah Anda yakin ingin menghapus data operasi SAR ini beserta seluruh arsip Renops dan Lapbang-nya? Tindakan ini tidak dapat dibatalkan.`,
    'Ya, Hapus Data',
    true
  );

  if (confirmed) {
    try {
      const res = await fetch('<?= BASE_URL ?>/api/operasi/' + id, { method: 'DELETE' });
      const json = await res.json();
      if (json.success) {
        showToast('Operasi SAR berhasil dihapus!', 'success');
        setTimeout(() => location.reload(), 700);
      } else {
        showToast(json.message || json.error || 'Gagal menghapus data operasi', 'error');
      }
    } catch {
      showToast('Terjadi kesalahan jaringan', 'error');
    }
  }
}

// Initial filter activation
setStatusFilter(currentStatusFilter);
</script>
