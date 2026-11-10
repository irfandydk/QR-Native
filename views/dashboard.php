<?php
/**
 * QR-SAR - Dashboard View
 * KPP Kendari - Basarnas
 * 100% Faithful Implementation matching src/components/DashboardList.tsx
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

// Overall Metrics
$totalOpen = (int)($stats['operasi_aktif'] ?? 0);
$totalClosed = (int)($stats['operasi_selesai'] ?? 0);
$totalBra = (int)($braStats['total'] ?? 0);

$latestBra = !empty($latestBraList) ? $latestBraList[0] : null;
$latestBraNumber = $latestBra['no_bra'] ?? '-';
$latestBraSubtext = $latestBra
    ? ($latestBra['kategori'] . ' • ' . ($latestBra['twu'] ?: (!empty($latestBra['created_at']) ? TemplateHelper::formatJamWITA($latestBra['created_at']) . ' WITA' : 'Tercatat')))
    : 'Belum ada dokumen BRA terbit';

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
  <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-3.5">
    
    <!-- Metric 1: Operasi SAR Aktif -->
    <div class="bento-card-v1 p-3 sm:p-3.5 rounded-2xl relative overflow-hidden group">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-mono">
          Operasi Aktif
        </span>
        <span class="radar-beacon-active">
          <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 z-10"></span>
        </span>
      </div>
      <div class="flex items-baseline justify-between gap-1.5 mt-1.5">
        <div class="text-2xl sm:text-3xl font-bold text-emerald-500 dark:text-emerald-400 font-mono tracking-tight leading-none">
          <?= $totalOpen ?>
        </div>
        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
          OPEN
        </span>
      </div>
      <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500 truncate">
        Dalam penanganan SAR
      </p>
    </div>

    <!-- Metric 2: Operasi SAR Tutup -->
    <div class="bento-card-v1 p-3 sm:p-3.5 rounded-2xl relative overflow-hidden group">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-mono">
          Operasi Selesai
        </span>
        <div class="w-5 h-5 rounded-md bg-slate-500/10 dark:bg-slate-800 flex items-center justify-center border border-slate-200/50 dark:border-slate-700/50">
          <svg class="w-3 h-3 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
      </div>
      <div class="flex items-baseline justify-between gap-1.5 mt-1.5">
        <div class="text-2xl sm:text-3xl font-bold font-mono text-slate-700 dark:text-slate-300 tracking-tight leading-none">
          <?= $totalClosed ?>
        </div>
        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
          CLOSED
        </span>
      </div>
      <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500 truncate">
        Debriefing &amp; penutupan
      </p>
    </div>

    <!-- Metric 3: Jumlah BRA SAR -->
    <div class="bento-card-v1 p-3 sm:p-3.5 rounded-2xl relative overflow-hidden group">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-mono">
          Berita SAR (BRA)
        </span>
        <div class="w-5 h-5 rounded-md bg-orange-500/10 dark:bg-orange-500/15 flex items-center justify-center border border-orange-500/30">
          <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0 14.14"/></svg>
        </div>
      </div>
      <div class="flex items-baseline justify-between gap-1.5 mt-1.5">
        <div class="text-2xl sm:text-3xl font-bold text-orange-600 dark:text-orange-400 font-mono tracking-tight leading-none">
          <?= $totalBra ?>
        </div>
        <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">
          TERBIT
        </span>
      </div>
      <p class="text-[11px] mt-1 text-slate-400 dark:text-slate-500 truncate">
        Telegram radio terbit
      </p>
    </div>

    <!-- Metric 4: Nomor Terakhir BRA SAR -->
    <div class="bento-card-v1 p-3 sm:p-3.5 rounded-2xl relative overflow-hidden group">
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 font-mono">
          BRA Terakhir
        </span>
        <div class="w-5 h-5 rounded-md bg-blue-500/10 dark:bg-blue-500/15 flex items-center justify-center border border-blue-500/30">
          <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        </div>
      </div>
      <div class="mt-1.5">
        <div class="text-sm sm:text-base font-bold font-mono tracking-tight truncate text-slate-900 dark:text-white" title="<?= e($latestBraNumber) ?>">
          <?= e($latestBraNumber) ?>
        </div>
      </div>
      <p class="text-[11px] mt-0.5 truncate text-slate-400 dark:text-slate-500">
        <?= e($latestBraSubtext) ?>
      </p>
    </div>

  </div>

  <!-- ============================================================== -->
  <!-- FILTER AND SEARCH BAR (MATCHING DashboardList.tsx)              -->
  <!-- ============================================================== -->
  <div class="cockpit-glass flex flex-col lg:flex-row items-center justify-between gap-3 rounded-2xl p-3 sm:p-4">
    
    <!-- Search Input -->
    <div class="relative w-full lg:w-72">
      <svg class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
      <input
        type="text"
        id="dashSearchInput"
        placeholder="Cari operasi, perairan, koordinat..."
        class="control control-sm w-full placeholder:text-slate-400 dark:placeholder:text-slate-500 pl-9 pr-8"
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
          class="control control-sm font-semibold cursor-pointer appearance-none pl-8 pr-7"
        >
          <option value="ALL">Semua Bulan Operasi</option>
          <?php foreach ($monthMap as $mOpt): ?>
            <option value="<?= e($mOpt['key']) ?>">
              <?= e($mOpt['label']) ?> (<?= $mOpt['count'] ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="absolute right-2.5 pointer-events-none text-slate-400 text-[11px]">
          ▼
        </div>
      </div>

      <!-- Status Filter Tabs -->
      <div class="flex items-center max-w-full overflow-x-auto rounded-xl p-1 text-xs border bg-slate-100 dark:bg-slate-950 border-slate-200 dark:border-slate-800 shadow-xs">
        <button
          type="button"
          id="tab-status-ALL"
          onclick="setStatusFilter('ALL')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg <?= $initialStatus === 'ALL' ? 'font-bold bg-orange-600 text-white shadow-sm' : 'font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?> transition-all cursor-pointer"
        >
          <span>Semua</span>
          <span class="tab-badge px-1.5 py-0.2 rounded-full text-[11px] font-mono <?= $initialStatus === 'ALL' ? 'bg-white/20 text-white font-bold' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold' ?>">
            <?= count($operasis) ?>
          </span>
        </button>
        <button
          type="button"
          id="tab-status-OPEN"
          onclick="setStatusFilter('OPEN')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg <?= $initialStatus === 'OPEN' ? 'font-bold bg-orange-600 text-white shadow-sm' : 'font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?> transition-all cursor-pointer"
        >
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>OPEN</span>
          <span class="tab-badge px-1.5 py-0.2 rounded-full text-[11px] font-mono <?= $initialStatus === 'OPEN' ? 'bg-white/20 text-white font-bold' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold' ?>">
            <?= $totalOpen ?>
          </span>
        </button>
        <button
          type="button"
          id="tab-status-CLOSED"
          onclick="setStatusFilter('CLOSED')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg <?= $initialStatus === 'CLOSED' ? 'font-bold bg-orange-600 text-white shadow-sm' : 'font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' ?> transition-all cursor-pointer"
        >
          <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          <span>CLOSED</span>
          <span class="tab-badge px-1.5 py-0.2 rounded-full text-[11px] font-mono <?= $initialStatus === 'CLOSED' ? 'bg-white/20 text-white font-bold' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold' ?>">
            <?= $totalClosed ?>
          </span>
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
        class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-600 hover:opacity-95 text-white rounded-xl text-xs font-bold shadow-md border border-orange-500/30 transition-all hover:scale-[1.02] active:scale-[0.98] cursor-pointer"
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
        <button type="button" onclick="resetMonthFilter()" class="hover:text-orange-800 dark:hover:text-orange-200 ml-0.5 cursor-pointer"></button>
      </span>

      <span id="chipStatus" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30 hidden">
        <span id="chipStatusText">Status</span>
        <button type="button" onclick="setStatusFilter('OPEN')" class="hover:text-blue-800 dark:hover:text-blue-200 ml-0.5 cursor-pointer"></button>
      </span>

      <span id="chipSearch" class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-slate-500/15 text-slate-600 dark:text-slate-300 border border-slate-500/30 hidden">
        <span id="chipSearchText">Cari</span>
        <button type="button" onclick="clearDashSearch()" class="hover:text-slate-900 dark:hover:text-white ml-0.5 cursor-pointer"></button>
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
    
    <?php
    $initialVisibleCount = 0;
    foreach ($operasis as $opCheck) {
        $checkStatus = $opCheck['status'] ?? 'OPEN';
        if ($initialStatus === 'ALL' || $checkStatus === $initialStatus) {
            $initialVisibleCount++;
        }
    }
    ?>
    <!-- Tactical Empty State Container -->
    <div id="operationsEmptyState" class="p-10 sm:p-12 text-center rounded-2xl border space-y-4 bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 shadow-xs <?= $initialVisibleCount === 0 ? '' : 'hidden' ?>">
      <div class="w-14 h-14 mx-auto rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center border border-orange-500/20 radar-sweep-tactical">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
      </div>
      <div class="max-w-md mx-auto">
        <h3 id="emptyStateTitle" class="text-sm font-bold text-slate-900 dark:text-white">
          <?= $initialStatus === 'OPEN' ? 'Tidak ada operasi SAR aktif' : 'Tidak ada data operasi ditemukan' ?>
        </h3>
        <p id="emptyStateSubtitle" class="text-xs mt-1 text-slate-500 dark:text-slate-400">
          <?= $initialStatus === 'OPEN' ? 'Saat ini tidak ada operasi SAR yang berstatus OPEN' : 'Belum ada operasi SAR yang diinisiasi' ?>
        </p>
      </div>
      <div id="emptyStateActions" class="flex flex-wrap items-center justify-center gap-2 pt-1">
        <button
          type="button"
          id="emptyStateResetBtn"
          onclick="resetAllFilters()"
          class="hidden px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold border border-slate-300 dark:border-slate-700 transition cursor-pointer"
        >
          Reset Filter Pencarian
        </button>
        <a
          href="<?= BASE_URL ?>/operasi_form.php"
          id="emptyStateInitBtn"
          class="inline-block px-4 py-2 bg-orange-600 hover:opacity-95 text-white rounded-xl text-xs font-semibold shadow-md transition cursor-pointer"
        >
          + Inisiasi Laporan Awal Sekarang
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
      
      // Calculate month key
      $dateStr = $op['waktu_kejadian'] ?: ($op['waktu_lapor'] ?: ($op['waktu_berangkat'] ?: ($op['created_at'] ?? '')));
      $monthKey = $dateStr ? date('Y-m', strtotime($dateStr)) : '';
      
      $s = (int)($op['korban_s'] ?? 0);
      $md = (int)($op['korban_md'] ?? 0);
      $dp = (int)($op['korban_dp'] ?? 0);
      $h = (int)($op['korban_h'] ?? 0);
      $identified = (int)($op['total_korban_count'] ?? 0);
      // Aturan sama dengan SarCalculationService::hitungStatistikKorban: POB yang belum terdata dihitung DP
      $totalPob = max((int)($op['jumlah_pob'] ?? 0), $identified);
      $dp += $totalPob - $identified;

      $totalRenops = (int)($op['total_renops'] ?? 0);
      $totalLapbang = (int)($op['total_laporan'] ?? 0);
    ?>
    <div
      class="operation-item-card bento-card-v1 p-3.5 sm:p-4 transition-all cursor-pointer hover:border-orange-500/50 dark:hover:border-orange-500/50 <?= $isOpen ? ($isReopened ? 'border-l-4 border-l-amber-500' : 'border-l-4 border-l-emerald-500') : 'border-l-4 border-l-slate-400 dark:border-l-slate-600' ?> <?= $isInitialMatch ? '' : 'hidden' ?>"
      data-id="<?= $op['id_operasi'] ?>"
      data-status="<?= e($op['status']) ?>"
      data-month="<?= e($monthKey) ?>"
      data-search="<?= e(strtolower($op['nama_operasi'] . ' ' . $op['lokasi_kejadian'] . ' ' . ($op['koordinat'] ?? ''))) ?>"
      onclick="navigateToDetail(<?= $op['id_operasi'] ?>)"
    >
      <!-- Top Row: Status, H-X, ID, and Response Time Badge -->
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
          
          <!-- Status Badge -->
          <?php if ($isOpen): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              <span>OPEN</span>
            </span>
          <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
              <svg class="w-3 h-3 text-slate-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
              <span>CLOSED</span>
            </span>
          <?php endif; ?>

          <!-- Reopened / Extension Pill if Reopened -->
          <?php if ($isReopened): ?>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-mono font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30" title="Operasi SAR dibuka kembali pada <?= TemplateHelper::formatTanggalIndo($op['waktu_reopen']) ?>">
              <svg class="w-3 h-3 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              <span>DIPERPANJANG</span>
            </span>
          <?php endif; ?>

          <!-- H-X Badge -->
          <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-mono font-bold border <?= $isOpen ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-700' ?>" title="Hari Pelaksanaan SAR: <?= e($pelaksanaan['h_display']) ?>">
            <span><?= e($pelaksanaan['h_display']) ?></span>
          </span>

          <!-- ID Badge (Opsional) -->
          <span class="text-[11px] font-mono font-medium px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800/80 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
            #<?= $op['id_operasi'] ?>
          </span>

        </div>

        <!-- Right: Response Time Badge -->
        <div class="flex items-center gap-2">
          <?php if ($resp['is_quick']): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30" title="Response Time: <?= $resp['formatted'] ?> (Info diterima <?= $jamLapor ?> Berangkat <?= $jamBerangkat ?>)">
              <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"/></svg>
              <span>Response Time: <?= $resp['formatted'] ?></span>
            </span>
          <?php elseif ($resp['minutes'] !== null): ?>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30" title="Response Time: <?= $resp['formatted'] ?> (Info diterima <?= $jamLapor ?> Berangkat <?= $jamBerangkat ?>)">
              <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>Response Time: <?= $resp['formatted'] ?></span>
            </span>
          <?php else: ?>
            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700">
              <svg class="w-3 h-3 opacity-60" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
              <span>Respons: Belum Lengkap</span>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Main Title: Judul Operasi -->
      <div class="mt-2">
        <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white group-hover:text-orange-600 dark:group-hover:text-orange-400 transition-colors leading-snug">
          <?= e($op['nama_operasi']) ?>
        </h3>
      </div>

      <!-- Lokasi & Koordinat -->
      <div class="mt-1.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xs text-slate-600 dark:text-slate-400">
        <div class="flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
          <span class="text-slate-800 dark:text-slate-200 font-semibold"><?= e($op['lokasi_kejadian']) ?></span>
        </div>
        <span class="text-slate-300 dark:text-slate-700 hidden sm:inline">•</span>
        <div class="flex items-center gap-1 font-mono text-[11px] text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
          <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/></svg>
          <span><?= e($op['koordinat']) ?></span>
        </div>
      </div>

      <!-- Tactical Metrics Grid: Waktu Mulai, Response Time Detail, POB, Renops & Lapbang -->
      <div class="mt-3 grid grid-cols-2 lg:grid-cols-4 gap-2 text-xs">
        
        <!-- Metric 1: Waktu Mulai (Dihitung Sejak Tim Berangkat) -->
        <div class="p-2 sm:p-2.5 rounded-xl border bg-slate-50/70 dark:bg-slate-950/40 border-slate-200/70 dark:border-slate-800/70 flex flex-col justify-between">
          <div class="flex items-center gap-1.5 text-[11px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 font-mono">
            <svg class="w-3 h-3 text-blue-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="5 3 19 12 5 21 5 3"/></svg>
            <span class="truncate">Waktu Berangkat</span>
          </div>
          <div class="mt-1 font-semibold text-slate-800 dark:text-slate-200 leading-tight truncate">
            <?= $tglBerangkat ?>
          </div>
          <div class="text-[11px] font-mono text-blue-600 dark:text-blue-400 font-bold mt-0.5">
            <?= $waktuBerangkat ? $jamBerangkat . ' WITA' : 'Belum Berangkat' ?>
          </div>
        </div>

        <!-- Metric 2: Response Time Details -->
        <div class="p-2 sm:p-2.5 rounded-xl border bg-slate-50/70 dark:bg-slate-950/40 border-slate-200/70 dark:border-slate-800/70 flex flex-col justify-between">
          <div class="flex items-center gap-1.5 text-[11px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 font-mono">
            <svg class="w-3 h-3 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span class="truncate">Response Time</span>
          </div>
          <div class="mt-1 font-bold font-mono text-xs sm:text-sm <?= $resp['is_quick'] ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' ?>">
            <?= $resp['formatted'] ?>
          </div>
          <div class="text-[11px] text-slate-400 dark:text-slate-500 truncate mt-0.5" title="Lapor: <?= $jamLapor ?> WITA ➔ Berangkat: <?= $jamBerangkat ?> WITA">
            <?= $jamLapor ?> ➔ <?= $jamBerangkat ?>
          </div>
        </div>

        <!-- Metric 3: Jumlah POB (Breakdown S / MD / DP) -->
        <div class="p-2 sm:p-2.5 rounded-xl border bg-slate-50/70 dark:bg-slate-950/40 border-slate-200/70 dark:border-slate-800/70 flex flex-col justify-between">
          <div class="flex items-center justify-between text-[11px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 font-mono">
            <div class="flex items-center gap-1 truncate">
              <svg class="w-3 h-3 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <span class="truncate">POB: <?= $totalPob ?> Jiwa</span>
            </div>
          </div>
          <div class="mt-1 flex items-center gap-1 flex-wrap">
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/25" title="Selamat">
              <?= $s ?> S
            </span>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/25" title="Meninggal Dunia">
              <?= $md ?> MD
            </span>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/25" title="Dalam Pencarian">
              <?= $dp ?> DP
            </span>
            <?php if ($h > 0): ?>
            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-500/15 text-slate-600 dark:text-slate-300 border border-slate-500/25" title="Hilang">
              <?= $h ?> H
            </span>
            <?php endif; ?>
          </div>
          <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 truncate">
            Status Korban
          </div>
        </div>

        <!-- Metric 4: Jumlah Renops dan Lapbang -->
        <div class="p-2 sm:p-2.5 rounded-xl border bg-slate-50/70 dark:bg-slate-950/40 border-slate-200/70 dark:border-slate-800/70 flex flex-col justify-between">
          <div class="flex items-center gap-1.5 text-[11px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 font-mono">
            <svg class="w-3 h-3 text-purple-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            <span class="truncate">Dokumen Taktis</span>
          </div>
          <div class="mt-1 flex items-center gap-1.5">
            <span class="px-1.5 py-0.5 rounded-md font-mono font-bold text-[11px] bg-purple-500/15 text-purple-600 dark:text-purple-400 border border-purple-500/25">
              <?= $totalRenops ?> Renops
            </span>
            <span class="px-1.5 py-0.5 rounded-md font-mono font-bold text-[11px] bg-indigo-500/15 text-indigo-600 dark:text-indigo-400 border border-indigo-500/25">
              <?= $totalLapbang ?> Lapbang
            </span>
          </div>
          <div class="text-[11px] text-slate-400 dark:text-slate-500 mt-0.5 truncate">
            Telah Diterbitkan
          </div>
        </div>

      </div>

      <!-- Bottom Row: Hari Pelaksanaan & Action Buttons -->
      <div class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
        
        <!-- Hari Pelaksanaan -->
        <div class="flex items-center gap-2 flex-wrap">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold shadow-xs <?= $pelaksanaan['is_extended'] ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30' : ($isOpen ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700') ?>">
            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
            <span>Pelaksanaan Operasi: <strong><?= e($pelaksanaan['teks']) ?></strong></span>
          </span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">
            (<?= e($pelaksanaan['subteks']) ?>)
          </span>
        </div>

        <!-- Action Buttons Group (stopPropagation) -->
        <div class="flex items-center gap-1.5 self-end sm:self-auto shrink-0" onclick="event.stopPropagation()">
          
          <!-- Salin WA Button -->
          <button
            type="button"
            id="btnCopyWa-<?= $op['id_operasi'] ?>"
            onclick="copyOpsWaText(event, <?= $op['id_operasi'] ?>)"
            title="Salin Teks WA Laporan Awal"
            class="btn-tactile p-1.5 sm:p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white border-slate-200 dark:border-slate-700 cursor-pointer"
          >
            <svg id="iconCopy-<?= $op['id_operasi'] ?>" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
            <svg id="iconCheck-<?= $op['id_operasi'] ?>" class="w-4 h-4 text-emerald-500 hidden" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
          </button>

          <!-- Lihat Format WA Button -->
          <button
            type="button"
            data-id="<?= (int)$op['id_operasi'] ?>"
            data-nama="<?= e($op['nama_operasi']) ?>"
            onclick="openOpsWaModal(this.dataset.id, this.dataset.nama)"
            title="Lihat Format WA Resmi"
            class="btn-tactile p-1.5 sm:p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white border-slate-200 dark:border-slate-700 cursor-pointer"
          >
            <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
          </button>

          <!-- Edit Operasi Button -->
          <a
            href="<?= BASE_URL ?>/operasi_form.php?id=<?= $op['id_operasi'] ?>"
            title="Edit Data Operasi"
            class="btn-tactile p-1.5 sm:p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-600 hover:text-amber-600 dark:text-slate-300 dark:hover:text-amber-400 border-slate-200 dark:border-slate-700 cursor-pointer"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
          </a>

          <!-- Delete Button (Admin Only) -->
          <?php if ($isAdmin): ?>
            <button
              type="button"
              data-id="<?= (int)$op['id_operasi'] ?>"
              data-nama="<?= e($op['nama_operasi']) ?>"
              onclick="deleteOpsHandler(event, this.dataset.id, this.dataset.nama)"
              title="Hapus Operasi"
              class="btn-tactile p-1.5 sm:p-2 rounded-lg border transition-colors bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-500 hover:text-red-600 dark:text-slate-400 dark:hover:text-red-400 border-slate-200 dark:border-slate-700 cursor-pointer"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
          <?php endif; ?>

          <!-- Enter Hub Arrow -->
          <div class="hidden sm:flex items-center text-orange-500 group-hover:translate-x-1.5 transition-transform ml-1">
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
        <h3 class="font-semibold text-sm sm:text-base text-slate-900 dark:text-white flex items-center gap-2">
          <span>LAPORAN AWAL OPS SAR (FILE AKTIF)</span>
        </h3>
        <p id="modalOpsWaSubtitle" class="text-xs text-orange-600 dark:text-orange-400 font-semibold mt-0.5"></p>
      </div>
      <button type="button" class="btn-tactile p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer" onclick="closeModal('modalOpsWaPreview')" title="Tutup">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="modal-body space-y-3">
      <div class="relative">
        <textarea
          id="modalOpsWaTextarea"
          readonly
          rows="14"
          class="control control-sm w-full font-mono leading-relaxed select-all"
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
    const badge = btn.querySelector('.tab-badge');
    if (st === status) {
      btn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-bold transition-all bg-orange-600 text-white shadow-sm cursor-pointer';
      if (badge) {
        badge.className = 'tab-badge px-1.5 py-0.2 rounded-full text-[11px] font-mono bg-white/20 text-white font-bold';
      }
    } else {
      btn.className = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg font-semibold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer';
      if (badge) {
        badge.className = 'tab-badge px-1.5 py-0.2 rounded-full text-[11px] font-mono bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold';
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
          subtitle.textContent = 'Coba ubah atau reset kata kunci dan filter pencarian Anda';
        } else {
          title.textContent = 'Tidak ada operasi SAR aktif';
          subtitle.textContent = 'Saat ini tidak ada operasi SAR yang berstatus OPEN';
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
