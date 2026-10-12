<?php
/**
 * QR-SAR - Dashboard View
 * KPP Kendari - Basarnas
 * 100% Faithful Implementation matching src/components/DashboardList.tsx
 */

declare(strict_types=1);

$stats = OperasiModel::getStats();
$operasis = OperasiModel::getAll();
$braList = BraModel::getAll();

$currentUser = AuthHelper::getCurrentUser();
$isAdmin = AuthHelper::isAdmin();

// Overall Metrics
$totalOpen = (int)($stats['operasi_aktif'] ?? 0);
$totalClosed = (int)($stats['operasi_selesai'] ?? 0);
$totalBra = count($braList);

$latestBra = !empty($braList) ? $braList[0] : null;
$latestBraNumber = $latestBra['no_bra'] ?? '-';
$latestBraSubtext = $latestBra
    ? ($latestBra['kategori'] . ' • ' . ($latestBra['twu'] ?: (!empty($latestBra['created_at']) ? TemplateHelper::formatJamWITA($latestBra['created_at']) . ' WITA' : 'Tercatat')))
    : 'Belum ada dokumen BRA terbit';

$totalSelamat = (int)($stats['korban_selamat'] ?? 0);
$totalMeninggal = (int)($stats['korban_meninggal'] ?? 0);
$totalHilang = (int)($stats['korban_dalam_pencarian'] ?? 0) + (int)($stats['korban_hilang'] ?? 0);
$totalKorban = (int)($stats['total_korban'] ?? 0);

// Extract distinct list of months from operasis
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
  
  <!-- ============================================================== -->
  <!-- 4 PRIMARY METRICS ROW (MATCHING DashboardList.tsx)              -->
  <!-- ============================================================== -->
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
    
    <!-- Metric 1: Operasi SAR Aktif -->
    <div class="rounded-2xl p-4 sm:p-5 border transition-all duration-200 relative overflow-hidden bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          Operasi SAR Aktif
        </span>
        <span class="flex h-2.5 w-2.5 relative">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
        </span>
      </div>
      <div class="text-2xl sm:text-3xl font-extrabold text-emerald-500 dark:text-emerald-400 mt-2 font-mono">
        <?= $totalOpen ?>
      </div>
      <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500">
        Dalam proses penanganan SAR (OPEN)
      </p>
    </div>

    <!-- Metric 2: Operasi SAR Tutup -->
    <div class="rounded-2xl p-4 sm:p-5 border transition-all duration-200 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          Operasi SAR Tutup
        </span>
        <div class="w-5 h-5 rounded-full bg-slate-500/10 dark:bg-slate-700/50 flex items-center justify-center">
          <svg class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
      </div>
      <div class="text-2xl sm:text-3xl font-extrabold mt-2 font-mono text-slate-700 dark:text-slate-300">
        <?= $totalClosed ?>
      </div>
      <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500">
        Debriefing &amp; penutupan selesai (CLOSED)
      </p>
    </div>

    <!-- Metric 3: Jumlah BRA SAR -->
    <div class="rounded-2xl p-4 sm:p-5 border transition-all duration-200 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          Jumlah BRA SAR
        </span>
        <div class="w-5 h-5 rounded-full bg-orange-500/10 flex items-center justify-center">
          <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
        </div>
      </div>
      <div class="text-2xl sm:text-3xl font-extrabold text-orange-600 dark:text-orange-400 mt-2 font-mono">
        <?= $totalBra ?>
      </div>
      <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500">
        Dokumen Berita Radio telegram terbit
      </p>
    </div>

    <!-- Metric 4: Nomor Terakhir BRA SAR -->
    <div class="rounded-2xl p-4 sm:p-5 border transition-all duration-200 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg">
      <div class="flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
          Nomor BRA Terakhir
        </span>
        <div class="w-5 h-5 rounded-full bg-blue-500/10 flex items-center justify-center">
          <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
      </div>
      <div class="text-base sm:text-lg lg:text-xl font-black mt-2 font-mono tracking-tight truncate text-slate-900 dark:text-white" title="<?= e($latestBraNumber) ?>">
        <?= e($latestBraNumber) ?>
      </div>
      <p class="text-[11px] mt-1 truncate text-slate-400 dark:text-slate-500">
        <?= e($latestBraSubtext) ?>
      </p>
    </div>

  </div>

  <!-- ============================================================== -->
  <!-- SITUATIONAL KORBAN SUMMARY PILL BAR                            -->
  <!-- ============================================================== -->
  <div class="px-4 py-2.5 rounded-xl border flex flex-wrap items-center justify-between gap-3 text-xs bg-slate-50 dark:bg-slate-900/60 border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300">
    <div class="flex items-center gap-2">
      <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span class="font-bold text-[11px] uppercase tracking-wider text-slate-400">Rekap Korban &amp; POB:</span>
    </div>
    <div class="flex items-center gap-3 sm:gap-4 flex-wrap text-xs">
      <span class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400">
        <span>Selamat:</span>
        <strong class="font-mono"><?= $totalSelamat ?></strong>
      </span>
      <span class="text-slate-400">•</span>
      <span class="inline-flex items-center gap-1 font-semibold text-red-600 dark:text-red-400">
        <span>Meninggal:</span>
        <strong class="font-mono"><?= $totalMeninggal ?></strong>
      </span>
      <span class="text-slate-400">•</span>
      <span class="inline-flex items-center gap-1 font-semibold text-amber-600 dark:text-amber-400">
        <span>Hilang / DP:</span>
        <strong class="font-mono"><?= $totalHilang ?></strong>
      </span>
      <span class="text-slate-400">•</span>
      <span class="inline-flex items-center gap-1 font-bold text-slate-700 dark:text-slate-200">
        <span>Total POB:</span>
        <strong class="font-mono"><?= $totalKorban ?></strong>
      </span>
    </div>
  </div>

  <!-- ============================================================== -->
  <!-- FILTER AND SEARCH BAR (MATCHING DashboardList.tsx)              -->
  <!-- ============================================================== -->
  <div class="flex flex-col lg:flex-row items-center justify-between gap-3 rounded-2xl p-3 sm:p-4 border transition-all duration-200 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-sm dark:shadow-lg">
    
    <!-- Search Input -->
    <div class="relative w-full lg:w-72">
      <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input
        type="text"
        id="dashSearchInput"
        placeholder="Cari operasi, perairan, koordinat..."
        class="w-full pl-9 pr-8 py-2 rounded-xl text-xs border transition-colors outline-none focus:ring-2 focus:ring-orange-500 bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:border-transparent"
      />
      <button
        type="button"
        id="dashSearchClearBtn"
        onclick="clearDashSearch()"
        class="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 hidden cursor-pointer"
        title="Hapus Pencarian"
      >
        ✕
      </button>
    </div>

    <!-- Month Filter, Status Tabs & Actions -->
    <div class="flex flex-wrap items-center justify-end gap-2 w-full lg:w-auto">
      
      <!-- Month Filter Dropdown -->
      <div class="relative flex items-center">
        <div class="absolute left-2.5 pointer-events-none">
          <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        </div>
        <select
          id="dashMonthSelect"
          onchange="onMonthFilterChange(this.value)"
          aria-label="Filter Bulan Operasi SAR"
          class="pl-8 pr-7 py-2 rounded-xl text-xs font-semibold border transition-colors outline-none cursor-pointer appearance-none bg-slate-50 dark:bg-slate-950 border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:border-slate-400 dark:hover:border-slate-600"
        >
          <option value="ALL">Semua Bulan Operasi</option>
          <?php foreach ($monthMap as $mOpt): ?>
            <option value="<?= e($mOpt['key']) ?>">
              <?= e($mOpt['label']) ?> (<?= $mOpt['count'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="absolute right-2.5 pointer-events-none text-slate-400 text-[10px]">
          ▼
        </div>
      </div>

      <!-- Status Filter Tabs -->
      <div class="flex items-center rounded-xl p-1 text-xs border bg-slate-100 dark:bg-slate-950 border-slate-200 dark:border-slate-800">
        <button
          type="button"
          id="tab-status-ALL"
          onclick="setStatusFilter('ALL')"
          class="px-3 py-1 rounded-lg font-semibold transition-all bg-gradient-to-r from-orange-500 to-amber-600 text-white shadow-sm cursor-pointer"
        >
          Semua
        </button>
        <button
          type="button"
          id="tab-status-OPEN"
          onclick="setStatusFilter('OPEN')"
          class="px-3 py-1 rounded-lg font-semibold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer"
        >
          OPEN
        </button>
        <button
          type="button"
          id="tab-status-CLOSED"
          onclick="setStatusFilter('CLOSED')"
          class="px-3 py-1 rounded-lg font-semibold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer"
        >
          CLOSED
        </button>
      </div>

      <!-- Segarkan Button -->
      <button
        type="button"
        id="btnRefreshData"
        onclick="refreshDashboardData()"
        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 cursor-pointer shadow-xs"
        title="Segarkan Data Real-Time"
      >
        <svg id="refreshSpinnerIcon" class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
        <span class="hidden sm:inline" id="refreshLabel">Segarkan</span>
      </button>

      <!-- Tambah Operasi Baru Button -->
      <a
        id="btn-tambah-operasi-baru"
        href="<?= BASE_URL ?>/operasi_form.php"
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r from-orange-500 to-amber-600 hover:opacity-95 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/25 border border-orange-500/30 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer"
      >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        <span>Operasi Baru</span>
      </a>

    </div>
  </div>

  <!-- ============================================================== -->
  <!-- FILTER ACTIVE INFO BAR (Dinamis jika filter aktif)              -->
  <!-- ============================================================== -->
  <div id="filterActiveInfoBar" class="px-4 py-2 rounded-xl border items-center justify-between text-xs transition-colors bg-slate-50 dark:bg-slate-900/60 border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hidden">
    <div class="flex items-center gap-2 flex-wrap">
      <span class="text-slate-400 font-medium">Filter Aktif:</span>

      <span id="chipMonth" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30 hidden">
        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
        <span id="chipMonthText">Bulan</span>
        <button type="button" onclick="resetMonthFilter()" class="hover:text-orange-800 dark:hover:text-orange-200 ml-0.5 cursor-pointer">✕</button>
      </span>

      <span id="chipStatus" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30 hidden">
        <span id="chipStatusText">Status</span>
        <button type="button" onclick="setStatusFilter('ALL')" class="hover:text-blue-800 dark:hover:text-blue-200 ml-0.5 cursor-pointer">✕</button>
      </span>

      <span id="chipSearch" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-500/15 text-slate-600 dark:text-slate-300 border border-slate-500/30 hidden">
        <span id="chipSearchText">Cari</span>
        <button type="button" onclick="clearDashSearch()" class="hover:text-slate-900 dark:hover:text-white ml-0.5 cursor-pointer">✕</button>
      </span>

      <span id="filteredCountText" class="text-slate-400 text-[11px]">
        (<?= count($operasis) ?> dari <?= count($operasis) ?> operasi)
      </span>
    </div>

    <button
      type="button"
      onclick="resetAllFilters()"
      class="text-[11px] font-semibold text-orange-600 dark:text-orange-400 hover:underline shrink-0 cursor-pointer"
    >
      Reset Semua Filter
    </button>
  </div>

  <!-- ============================================================== -->
  <!-- OPERATIONS CARD LIST (MATCHING DashboardList.tsx)              -->
  <!-- ============================================================== -->
  <div id="operationsListContainer" class="space-y-3">
    
    <!-- Empty State Container -->
    <div id="operationsEmptyState" class="p-12 text-center rounded-2xl border space-y-4 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 <?= empty($operasis) ? '' : 'hidden' ?>">
      <div class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 flex items-center justify-center">
        <svg class="w-12 h-12" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
      </div>
      <div>
        <h3 id="emptyStateTitle" class="text-sm font-bold text-slate-800 dark:text-slate-300">Tidak ada data operasi ditemukan</h3>
        <p id="emptyStateSubtitle" class="text-xs mt-1 text-slate-500">
          Belum ada operasi SAR yang diinisiasi
        </p>
      </div>
      <a
        href="<?= BASE_URL ?>/operasi_form.php"
        class="inline-block px-4 py-2 bg-gradient-to-r from-orange-500 to-amber-600 text-white rounded-xl text-xs font-semibold shadow-md hover:opacity-95"
      >
        + Inisiasi Laporan Awal Sekarang
      </a>
    </div>

    <!-- Operation Cards -->
    <?php foreach ($operasis as $op): 
      $isOpen = ($op['status'] ?? 'OPEN') === 'OPEN';
      $hariKe = TemplateHelper::hitungHariKe($op['waktu_berangkat'] ?: $op['waktu_lapor']);
      $tglBerangkat = TemplateHelper::formatTanggalIndo($op['waktu_berangkat'] ?: $op['waktu_kejadian']);
      $jamBerangkat = TemplateHelper::formatJamWITA($op['waktu_berangkat'] ?: $op['waktu_kejadian']);
      
      // Calculate month key
      $dateStr = $op['waktu_kejadian'] ?: ($op['waktu_lapor'] ?: ($op['waktu_berangkat'] ?: ($op['created_at'] ?? '')));
      $monthKey = $dateStr ? date('Y-m', strtotime($dateStr)) : '';
      
      $s = (int)($op['korban_s'] ?? 0);
      $md = (int)($op['korban_md'] ?? 0);
      $dp = (int)($op['korban_dp'] ?? 0);
      $totalPob = (int)($op['jumlah_pob'] ?? 0);
      if ($totalPob === 0) $totalPob = $s + $md + $dp;
    ?>
    <div
      class="operation-item-card group relative rounded-2xl p-4 sm:p-5 border transition-all cursor-pointer bg-white dark:bg-slate-900 hover:bg-slate-50/90 dark:hover:bg-slate-800/80 border-slate-200 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700 shadow-sm hover:shadow-md dark:shadow-lg"
      data-id="<?= $op['id_operasi'] ?>"
      data-status="<?= e($op['status']) ?>"
      data-month="<?= e($monthKey) ?>"
      data-search="<?= e(strtolower($op['nama_operasi'] . ' ' . $op['lokasi_kejadian'] . ' ' . ($op['koordinat'] ?? ''))) ?>"
      onclick="navigateToDetail(<?= $op['id_operasi'] ?>)"
    >
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        
        <!-- Left Column: Title & Meta -->
        <div class="space-y-2 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border <?= $isOpen ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-700' ?>">
              <?php if ($isOpen): ?>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <?php endif; ?>
              <span><?= e($op['status']) ?></span>
            </span>

            <span class="px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
              <?= $isOpen ? $hariKe : 'SELESAI' ?>
            </span>

            <span class="text-[11px] text-slate-500 dark:text-slate-400">
              ID: #<?= $op['id_operasi'] ?>
            </span>
          </div>

          <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white transition-colors">
            <?= e($op['nama_operasi']) ?>
          </h3>

          <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
            <div class="flex items-center gap-1">
              <svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
              <span class="text-slate-700 dark:text-slate-300 font-medium"><?= e($op['lokasi_kejadian']) ?></span>
            </div>
            <span>•</span>
            <div class="font-mono text-emerald-600 dark:text-emerald-400 font-medium"><?= e($op['koordinat']) ?></div>
            <span>•</span>
            <div class="flex items-center gap-1">
              <svg class="w-3.5 h-3.5 opacity-60 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>Berangkat: <?= $tglBerangkat ?> (<?= $jamBerangkat ?> WITA)</span>
            </div>
          </div>
        </div>

        <!-- Right Column: POB Badges & Actions -->
        <div class="flex flex-wrap items-center gap-3">
          
          <!-- Victim Stats -->
          <div class="flex items-center gap-2 p-1.5 px-3 rounded-xl text-xs border bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800">
            <span class="text-slate-500 dark:text-slate-400 text-[11px] font-medium">POB:</span>
            <span class="text-emerald-600 dark:text-emerald-400 font-bold"><?= $s ?> S</span>
            <span class="text-slate-400 dark:text-slate-600">/</span>
            <span class="text-red-500 dark:text-red-400 font-bold"><?= $md ?> MD</span>
            <span class="text-slate-400 dark:text-slate-600">/</span>
            <span class="text-amber-500 dark:text-amber-400 font-bold"><?= $dp ?> DP</span>
          </div>

          <!-- Renops & Sitrep count pills -->
          <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <span class="px-2 py-1 rounded-lg border bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium">
              <?= (int)($op['total_renops'] ?? 0) ?> Renops
            </span>
            <span class="px-2 py-1 rounded-lg border bg-slate-100 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-medium">
              <?= (int)($op['total_laporan'] ?? 0) ?> Lapbang
            </span>
          </div>

          <!-- Quick Button Group (stopPropagation) -->
          <div class="flex items-center gap-1.5" onclick="event.stopPropagation()">
            
            <!-- Salin WA Button -->
            <button
              type="button"
              id="btnCopyWa-<?= $op['id_operasi'] ?>"
              onclick="copyOpsWaText(event, <?= $op['id_operasi'] ?>)"
              title="Salin Teks WA Laporan Awal"
              class="p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white border-slate-200 dark:border-slate-700 cursor-pointer"
            >
              <svg id="iconCopy-<?= $op['id_operasi'] ?>" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
              <svg id="iconCheck-<?= $op['id_operasi'] ?>" class="w-4 h-4 text-emerald-500 hidden" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
            </button>

            <!-- Lihat Format WA Button -->
            <button
              type="button"
              onclick="openOpsWaModal(<?= $op['id_operasi'] ?>, '<?= e(addslashes($op['nama_operasi'])) ?>')"
              title="Lihat Format WA Resmi"
              class="p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white border-slate-200 dark:border-slate-700 cursor-pointer"
            >
              <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>

            <!-- Edit Operasi Button -->
            <a
              href="<?= BASE_URL ?>/operasi_form.php?id=<?= $op['id_operasi'] ?>"
              title="Edit Data Operasi"
              class="p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 hover:text-amber-600 dark:text-slate-300 dark:hover:text-amber-400 border-slate-200 dark:border-slate-700 cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            </a>

            <!-- Delete Button (Admin Only) -->
            <?php if ($isAdmin): ?>
              <button
                type="button"
                onclick="deleteOpsHandler(event, <?= $op['id_operasi'] ?>, '<?= e(addslashes($op['nama_operasi'])) ?>')"
                title="Hapus Operasi"
                class="p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 border-slate-200 dark:border-slate-700 cursor-pointer"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
              </button>
            <?php endif; ?>

          </div>

          <!-- Enter Hub Arrow -->
          <div class="hidden sm:flex items-center text-orange-500 group-hover:translate-x-1 transition-transform">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
          </div>

        </div>

      </div>
    </div>
    <?php endforeach; ?>

  </div>

</div>

<!-- ============================================================== -->
<!-- MODAL: LIHAT FORMAT WA RESMI (MATCHING DashboardList.tsx)       -->
<!-- ============================================================== -->
<div id="modalOpsWaPreview" class="modal-overlay">
  <div class="modal-container max-w-2xl">
    <div class="modal-header">
      <div>
        <h3 class="font-extrabold text-sm sm:text-base text-slate-900 dark:text-white uppercase flex items-center gap-2">
          <span>LAPORAN AWAL OPS SAR (FILE AKTIF)</span>
        </h3>
        <p id="modalOpsWaSubtitle" class="text-xs text-orange-600 dark:text-orange-400 font-semibold mt-0.5"></p>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modalOpsWaPreview')">✕</button>
    </div>
    <div class="modal-body space-y-3">
      <div class="relative">
        <textarea
          id="modalOpsWaTextarea"
          readonly
          rows="14"
          class="w-full p-4 rounded-xl font-mono text-xs leading-relaxed border bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 focus:outline-none select-all"
        ></textarea>
      </div>
    </div>
    <div class="modal-footer flex items-center justify-between">
      <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modalOpsWaPreview')">
        Tutup
      </button>
      <button type="button" class="btn btn-primary btn-sm flex items-center gap-1.5" onclick="copyModalWaText()">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
        <span>Salin Naskah WhatsApp</span>
      </button>
    </div>
  </div>
</div>

<!-- ============================================================== -->
<!-- DASHBOARD CLIENT-SIDE FILTER & CONTROLLER JAVASCRIPT           -->
<!-- ============================================================== -->
<script>
let currentStatusFilter = 'ALL';
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
      select.classList.add('bg-orange-500/15', 'border-orange-500', 'text-orange-600', 'dark:text-orange-400', 'font-bold');
    } else {
      select.classList.remove('bg-orange-500/15', 'border-orange-500', 'text-orange-600', 'dark:text-orange-400', 'font-bold');
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
    if (st === status) {
      btn.className = 'px-3 py-1 rounded-lg font-semibold transition-all bg-gradient-to-r from-orange-500 to-amber-600 text-white shadow-sm cursor-pointer';
    } else {
      btn.className = 'px-3 py-1 rounded-lg font-semibold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer';
    }
  });
  applyDashFilters();
}

function resetAllFilters() {
  clearDashSearch();
  resetMonthFilter();
  setStatusFilter('ALL');
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
      if (title && subtitle) {
        if (currentSearchQuery || currentMonthFilter !== 'ALL' || currentStatusFilter !== 'ALL') {
          title.textContent = 'Tidak ada data operasi sesuai filter';
          subtitle.textContent = 'Coba ubah atau reset kata kunci dan filter bulan Anda';
        } else {
          title.textContent = 'Tidak ada data operasi ditemukan';
          subtitle.textContent = 'Belum ada operasi SAR yang diinisiasi';
        }
      }
    } else {
      emptyState.classList.add('hidden');
    }
  }

  // Active info bar handling
  const isFilterActive = currentStatusFilter !== 'ALL' || currentMonthFilter !== 'ALL' || currentSearchQuery;
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

      if (currentStatusFilter !== 'ALL') {
        chipStatusText.textContent = 'Status: ' + currentStatusFilter;
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
    await fetch('<?= BASE_URL ?>/api/operasi?_t=' + Date.now());
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
        showToast(json.error || 'Gagal menghapus data operasi', 'error');
      }
    } catch {
      showToast('Terjadi kesalahan jaringan', 'error');
    }
  }
}
</script>
