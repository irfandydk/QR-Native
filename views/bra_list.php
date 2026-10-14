<?php
/**
 * QR-SAR - Arsip Berita Radio SAR (BRA SAR)
 * Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas
 * 
 * Desain Redesain Taktis Dual-Mode:
 * - Card View (Grid Kartu Modern) & List View (Tabel Data Terstruktur)
 * - 4 Kartu Statistik Ringkasan Interaktif (Semua, MAPEL, SAR UMUM, KAPAL)
 * - Quick Actions Langsung (+ BRA Mapel, + BRA SAR, + BRA Kapal, Segarkan)
 * - Real-Time Search & Category Filter
 * - Full Dark/Light Mode & Palette Compatibility
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$kategoriFilter = strtoupper($_GET['kategori'] ?? 'SEMUA');
$searchQuery = trim($_GET['q'] ?? '');

// Counts for Summary Cards via single aggregated query
$braStats = BraModel::getStats();
$countAll = $braStats['total'];
$countMapel = $braStats['mapel'];
$countSarUmum = $braStats['sar_umum'];
$countKapal = $braStats['kapal'];

// Fetch filtered list directly from database without N+1 section queries
$katParam = ($kategoriFilter !== 'SEMUA') ? $kategoriFilter : null;
$braList = BraModel::getAll($katParam, $searchQuery !== '' ? $searchQuery : null, false);
$totalFiltered = count($braList);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

  <!-- ======================================================== -->
  <!-- 1. TOP HEADER & COMMAND ACTIONS -->
  <!-- ======================================================== -->
  <div class="p-5 sm:p-6 rounded-3xl border shadow-lg transition-colors bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
      <div class="flex items-start gap-3.5">
        <a href="<?= BASE_URL ?>/dashboard.php"
           class="p-2.5 rounded-2xl border transition-colors mt-0.5 border-slate-200 dark:border-slate-700 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 shrink-0"
           title="Kembali ke Dashboard">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
          <div class="flex flex-wrap items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl border flex items-center justify-center bg-orange-500/10 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 border-orange-500/30">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            </div>
            <h1 class="text-xl sm:text-2xl font-black tracking-tight text-slate-900 dark:text-white">
              Arsip Berita Radio SAR (BRA SAR)
            </h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              SENKOM 304 AKTIF
            </span>
          </div>
          <p class="text-xs mt-1.5 text-slate-600 dark:text-slate-400 leading-relaxed">
            Pusat data arsip telegram radio resmi Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari
          </p>
        </div>
      </div>

      <!-- Quick Action Buttons -->
      <div class="flex flex-wrap items-center gap-2">
        <a href="<?= BASE_URL ?>/bra.php"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all border-slate-300 dark:border-slate-700 bg-slate-50 hover:bg-slate-100 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200"
           title="Segarkan Data Real-Time">
          <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Segarkan</span>
        </a>

        <a href="<?= BASE_URL ?>/bra_form.php?kategori=MAPEL"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
          <span>📡</span>
          <span>+ BRA Mapel</span>
        </a>

        <a href="<?= BASE_URL ?>/bra_form.php?kategori=SAR_UMUM"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-md shadow-amber-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
          <span>📋</span>
          <span>+ BRA SAR</span>
        </a>

        <a href="<?= BASE_URL ?>/bra_form.php?kategori=PERGERAKAN_KAPAL"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold shadow-md shadow-cyan-600/20 transition-all hover:scale-[1.02] active:scale-[0.98]">
          <span>🚢</span>
          <span>+ BRA Kapal</span>
        </a>
      </div>
    </div>

    <!-- 4 Stat Summary Cards (Interactive Filters) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6 pt-5 border-t border-slate-200 dark:border-slate-800">
      <!-- Card 1: Semua -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=SEMUA<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-3.5 rounded-2xl border cursor-pointer transition-all <?= $kategoriFilter === 'SEMUA'
           ? 'bg-orange-50/80 dark:bg-slate-800 border-orange-400 dark:border-orange-500 ring-2 ring-orange-400/40 dark:ring-orange-500/50 shadow-sm'
           : 'bg-slate-50/80 dark:bg-slate-950/60 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
          <span>Semua Telegram</span>
          <span class="text-sm">📁</span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-black text-slate-900 dark:text-white">
            <?= $countAll ?>
          </span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>

      <!-- Card 2: MAPEL -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=MAPEL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-3.5 rounded-2xl border cursor-pointer transition-all <?= $kategoriFilter === 'MAPEL'
           ? 'bg-orange-50/80 dark:bg-slate-800 border-orange-400 dark:border-orange-500 ring-2 ring-orange-400/40 dark:ring-orange-500/50 shadow-sm'
           : 'bg-slate-50/80 dark:bg-slate-950/60 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
          <span>BRA Mapel Kapal</span>
          <span class="text-sm">📡</span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-black text-orange-600 dark:text-orange-400">
            <?= $countMapel ?>
          </span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>

      <!-- Card 3: SAR UMUM -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=SAR_UMUM<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-3.5 rounded-2xl border cursor-pointer transition-all <?= $kategoriFilter === 'SAR_UMUM'
           ? 'bg-amber-50/80 dark:bg-slate-800 border-amber-400 dark:border-amber-500 ring-2 ring-amber-400/40 dark:ring-amber-500/50 shadow-sm'
           : 'bg-slate-50/80 dark:bg-slate-950/60 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
          <span>BRA SAR Operasi SAR</span>
          <span class="text-sm">📋</span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-black text-amber-600 dark:text-amber-400">
            <?= $countSarUmum ?>
          </span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>

      <!-- Card 4: KAPAL -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=PERGERAKAN_KAPAL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-3.5 rounded-2xl border cursor-pointer transition-all <?= $kategoriFilter === 'PERGERAKAN_KAPAL'
           ? 'bg-cyan-50/80 dark:bg-slate-800 border-cyan-400 dark:border-cyan-500 ring-2 ring-cyan-400/40 dark:ring-cyan-500/50 shadow-sm'
           : 'bg-slate-50/80 dark:bg-slate-950/60 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-xs font-medium">
          <span>BRA Kapal SAR</span>
          <span class="text-sm">🚢</span>
        </div>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl font-black text-cyan-600 dark:text-cyan-400">
            <?= $countKapal ?>
          </span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>
    </div>
  </div>

  <!-- ======================================================== -->
  <!-- 2. FILTER TOOLBAR, SEARCH BAR & VIEW SWITCHER (CARD vs LIST) -->
  <!-- ======================================================== -->
  <div class="p-4 rounded-2xl border shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-3.5 transition-colors bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800">
    <!-- Category Pills -->
    <div class="flex flex-wrap items-center gap-1.5 w-full md:w-auto">
      <a href="<?= BASE_URL ?>/bra.php?kategori=SEMUA<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $kategoriFilter === 'SEMUA'
           ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
           : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
        Semua (<?= $countAll ?>)
      </a>
      <a href="<?= BASE_URL ?>/bra.php?kategori=MAPEL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $kategoriFilter === 'MAPEL'
           ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
           : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
        📡 Mapel (<?= $countMapel ?>)
      </a>
      <a href="<?= BASE_URL ?>/bra.php?kategori=SAR_UMUM<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $kategoriFilter === 'SAR_UMUM'
           ? 'bg-amber-500 text-white shadow-md shadow-amber-500/20'
           : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
        📋 Operasi SAR (<?= $countSarUmum ?>)
      </a>
      <a href="<?= BASE_URL ?>/bra.php?kategori=PERGERAKAN_KAPAL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="px-3 py-1.5 rounded-xl text-xs font-bold transition <?= $kategoriFilter === 'PERGERAKAN_KAPAL'
           ? 'bg-cyan-500 text-white shadow-md shadow-cyan-500/20'
           : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-200 dark:hover:bg-slate-700' ?>">
        🚢 Kapal SAR (<?= $countKapal ?>)
      </a>
    </div>

    <!-- Search Input & View Switcher Toggle -->
    <div class="flex items-center gap-2.5 w-full md:w-auto">
      <!-- Live Search Input -->
      <div class="relative flex-1 md:w-72">
        <svg class="w-4 h-4 absolute left-3.5 top-2.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text"
               id="bra-search-input"
               name="q"
               value="<?= htmlspecialchars($searchQuery) ?>"
               placeholder="Cari No BRA, Perihal, SMC, TWU..."
               class="w-full pl-10 pr-9 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500" />
        <button type="button"
                id="btn-clear-bra-search"
                onclick="clearBraSearch()"
                class="<?= empty($searchQuery) ? 'hidden' : '' ?> absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                title="Hapus Pencarian">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- View Switcher (Card vs List) Toggle -->
      <div class="flex items-center p-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 shrink-0">
        <!-- Button: Card View -->
        <button type="button"
                id="btn-view-card"
                onclick="setBraView('card')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                title="Tampilkan Model Kartu (Grid)">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>
          <span class="hidden sm:inline">Kartu</span>
        </button>

        <!-- Button: List View -->
        <button type="button"
                id="btn-view-list"
                onclick="setBraView('list')"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
                title="Tampilkan Model Tabel / List">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/><circle cx="2" cy="6" r="1"/><circle cx="2" cy="12" r="1"/><circle cx="2" cy="18" r="1"/></svg>
          <span class="hidden sm:inline">List</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Filter Active Info Row -->
  <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 px-1">
    <div>
      Menampilkan <strong class="text-slate-800 dark:text-slate-200"><?= $totalFiltered ?></strong> dari <?= $countAll ?> arsip naskah BRA SAR
      <?php if ($kategoriFilter !== 'SEMUA'): ?>
        • Kategori: <span class="font-bold text-orange-600 dark:text-orange-400"><?= e($kategoriFilter) ?></span>
      <?php endif; ?>
      <?php if ($searchQuery): ?>
        • Kata Kunci: "<span class="font-bold text-slate-800 dark:text-slate-200"><?= e($searchQuery) ?></span>"
      <?php endif; ?>
    </div>
  </div>

  <!-- ======================================================== -->
  <!-- 3. MAIN DOCUMENTS CONTAINER (DUAL MODE: CARD & LIST) -->
  <!-- ======================================================== -->
  <?php if (empty($braList)): ?>
    <div class="p-12 text-center rounded-3xl border border-dashed border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900">
      <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-orange-500/10 flex items-center justify-center text-orange-500 text-3xl">
        📡
      </div>
      <h3 class="text-base font-bold text-slate-900 dark:text-slate-100 mb-1">Tidak Ada Naskah BRA Ditemukan</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto mb-5 leading-relaxed">
        <?= $searchQuery ? 'Tidak ada hasil untuk pencarian "' . htmlspecialchars($searchQuery) . '".' : 'Belum ada dokumen Berita Radio SAR yang tersimpan pada kategori ini.' ?>
      </p>
      <div class="flex items-center justify-center gap-2">
        <a href="<?= BASE_URL ?>/bra_form.php?kategori=<?= $kategoriFilter === 'SEMUA' ? 'MAPEL' : $kategoriFilter ?>" class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 rounded-xl shadow-md transition">
          + Buat Naskah BRA Baru
        </a>
      </div>
    </div>
  <?php else: ?>

    <!-- ======================================================== -->
    <!-- VIEW MODE 1: GRID OF CARDS -->
    <!-- ======================================================== -->
    <div id="bra-container-cards" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach ($braList as $bra): ?>
        <?php
          $kategori = strtoupper($bra['kategori'] ?? 'MAPEL');
          $badgeClass = match($kategori) {
              'MAPEL' => 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30',
              'PERGERAKAN_KAPAL' => 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-300 border-cyan-500/30',
              default => 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
          };
          $kategoriIcon = match($kategori) {
              'MAPEL' => '📡',
              'PERGERAKAN_KAPAL' => '🚢',
              default => '📋',
          };
          $kategoriLabel = match($kategori) {
              'MAPEL' => 'BRA MAPEL',
              'PERGERAKAN_KAPAL' => 'BRA KAPAL',
              default => 'BRA SAR OPERASI SAR',
          };
          $derajat = strtoupper($bra['derajat'] ?? 'SGR');
          $searchDataStr = strtolower(($bra['no_bra'] ?? '') . ' ' . ($bra['perihal'] ?? '') . ' ' . ($bra['nama_smc'] ?? '') . ' ' . ($bra['pengirim'] ?? '') . ' ' . ($bra['twu'] ?? '') . ' ' . ($bra['twb'] ?? '') . ' ' . ($bra['nama_operasi'] ?? ''));
        ?>
        <div class="bra-item-card p-5 rounded-3xl border transition-all flex flex-col justify-between group bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 hover:border-orange-500/50 hover:shadow-lg" data-search="<?= htmlspecialchars($searchDataStr) ?>">
          <div>
            <!-- Top Card Header: Category Badge & Derajat -->
            <div class="flex items-start justify-between gap-2 mb-3">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border <?= $badgeClass ?>">
                <span><?= $kategoriIcon ?></span>
                <span><?= $kategoriLabel ?></span>
              </span>
              <span class="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                <?= htmlspecialchars($derajat) ?>
              </span>
            </div>

            <!-- Number & Copy Trigger -->
            <div class="mb-3">
              <div class="text-xs font-mono font-bold text-orange-600 dark:text-orange-400 mb-1 flex items-center justify-between">
                <span class="truncate"><?= htmlspecialchars($bra['no_bra'] ?? '') ?></span>
                <button type="button"
                        onclick="copyBraNo('<?= htmlspecialchars($bra['no_bra'] ?? '') ?>', this)"
                        title="Salin Nomor Dokumen"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer shrink-0">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                </button>
              </div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white line-clamp-2 leading-snug group-hover:text-orange-600 dark:group-hover:text-orange-400 transition" title="<?= htmlspecialchars($bra['perihal'] ?? '') ?>">
                <?= htmlspecialchars($bra['perihal'] ?? 'Naskah Berita Radio SAR') ?>
              </h3>
            </div>

            <!-- Meta Info Card -->
            <div class="space-y-1.5 text-[11px] text-slate-600 dark:text-slate-400 mb-3 bg-slate-50 dark:bg-slate-950/70 p-3 rounded-2xl border border-slate-100 dark:border-slate-800">
              <div class="flex items-center justify-between">
                <span>TWU (Waktu Buat):</span>
                <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($bra['twu'] ?? '-') ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span>TWB (Waktu Kirim):</span>
                <span class="font-mono font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($bra['twb'] ?? '-') ?></span>
              </div>
              <div class="flex items-center justify-between">
                <span>SMC / Pengirim:</span>
                <span class="font-medium text-slate-800 dark:text-slate-200 truncate max-w-[170px]"><?= htmlspecialchars($bra['nama_smc'] ?? ($bra['pengirim'] ?? '-')) ?></span>
              </div>
            </div>

            <!-- Telegram Preview Snippet (if available) -->
            <?php if (!empty($bra['sections'])): ?>
            <div class="p-2.5 rounded-xl bg-slate-100/70 dark:bg-slate-950/40 border border-slate-200/60 dark:border-slate-800/60 mb-3 font-mono text-[10px] text-slate-600 dark:text-slate-400 space-y-1">
              <?php foreach (array_slice($bra['sections'], 0, 2) as $sec): ?>
                <div class="truncate">
                  <span class="font-bold text-slate-700 dark:text-slate-300"><?= e($sec['kode']) ?>:</span> <?= e(mb_strimwidth($sec['isi'], 0, 60, '...')) ?>
                </div>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>
          </div>

          <!-- Bottom Card Buttons -->
          <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
            <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>"
               class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 rounded-xl shadow-xs transition hover:scale-[1.02]">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.264 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <span>Lihat Dokumen</span>
            </a>

            <a href="<?= BASE_URL ?>/bra_form.php?id=<?= $bra['id_bra'] ?>"
               class="p-2 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white bg-slate-100 dark:bg-slate-800 rounded-xl transition cursor-pointer"
               title="Koreksi / Edit Naskah">
              <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </a>

            <?php if (AuthHelper::canDelete()): ?>
              <form method="POST" action="<?= BASE_URL ?>/bra.php?action=delete" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus naskah Berita Radio SAR <?= htmlspecialchars($bra['no_bra']) ?>? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus Dokumen BRA SAR" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                <?= csrfInput() ?>
                <input type="hidden" name="id" value="<?= (int)$bra['id_bra'] ?>">
                <button type="submit" class="p-2 text-red-500 hover:text-red-700 bg-red-50 dark:bg-red-500/10 rounded-xl transition cursor-pointer" title="Hapus Dokumen">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ======================================================== -->
    <!-- VIEW MODE 2: STRUCTURED TACTICAL LIST / TABLE -->
    <!-- ======================================================== -->
    <div id="bra-container-list" class="rounded-3xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/40 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <th class="px-5 py-3.5">Nomor Telegram & Derajat</th>
              <th class="px-4 py-3.5">Kategori</th>
              <th class="px-4 py-3.5">Perihal & Pejabat SMC</th>
              <th class="px-4 py-3.5">Waktu (TWU / TWB)</th>
              <th class="px-4 py-3.5">Detail Sandi</th>
              <th class="px-5 py-3.5 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-xs">
            <?php foreach ($braList as $bra): ?>
              <?php
                $kategori = strtoupper($bra['kategori'] ?? 'MAPEL');
                $badgeClass = match($kategori) {
                    'MAPEL' => 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30',
                    'PERGERAKAN_KAPAL' => 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-300 border-cyan-500/30',
                    default => 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
                };
                $kategoriIcon = match($kategori) {
                    'MAPEL' => '📡',
                    'PERGERAKAN_KAPAL' => '🚢',
                    default => '📋',
                };
                $kategoriLabel = match($kategori) {
                    'MAPEL' => 'MAPEL',
                    'PERGERAKAN_KAPAL' => 'KAPAL',
                    default => 'OPERASI SAR',
                };
                $derajat = strtoupper($bra['derajat'] ?? 'SGR');
                $searchDataStr = strtolower(($bra['no_bra'] ?? '') . ' ' . ($bra['perihal'] ?? '') . ' ' . ($bra['nama_smc'] ?? '') . ' ' . ($bra['pengirim'] ?? '') . ' ' . ($bra['twu'] ?? '') . ' ' . ($bra['twb'] ?? '') . ' ' . ($bra['nama_operasi'] ?? ''));
              ?>
              <tr class="bra-item-row hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors" data-search="<?= htmlspecialchars($searchDataStr) ?>">
                <!-- Col 1: No BRA & Derajat -->
                <td class="px-5 py-4 align-top">
                  <div class="flex items-center gap-1.5 font-mono font-bold text-orange-600 dark:text-orange-400">
                    <span><?= htmlspecialchars($bra['no_bra'] ?? '-') ?></span>
                    <button type="button"
                            onclick="copyBraNo('<?= htmlspecialchars($bra['no_bra'] ?? '') ?>', this)"
                            title="Salin Nomor Dokumen"
                            class="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                  </div>
                  <div class="mt-1 flex items-center gap-1.5">
                    <span class="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                      <?= htmlspecialchars($derajat) ?>
                    </span>
                    <?php if (!empty($bra['id_operasi'])): ?>
                      <span class="text-[10px] text-slate-400 font-mono">Ops #<?= (int)$bra['id_operasi'] ?></span>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- Col 2: Kategori -->
                <td class="px-4 py-4 align-top">
                  <span class="inline-flex items-center gap-1 px-2.5 py-1 text-[11px] font-bold rounded-lg border <?= $badgeClass ?>">
                    <span><?= $kategoriIcon ?></span>
                    <span><?= $kategoriLabel ?></span>
                  </span>
                </td>

                <!-- Col 3: Perihal & SMC -->
                <td class="px-4 py-4 align-top max-w-md">
                  <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>" class="font-bold text-slate-900 dark:text-white hover:text-orange-600 dark:hover:text-orange-400 transition block leading-snug">
                    <?= htmlspecialchars($bra['perihal'] ?? 'Naskah Berita Radio SAR') ?>
                  </a>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-2">
                    <span>SMC: <strong class="text-slate-700 dark:text-slate-300"><?= htmlspecialchars($bra['nama_smc'] ?? ($bra['pengirim'] ?? '-')) ?></strong></span>
                    <?php if (!empty($bra['nama_operasi'])): ?>
                      <span>• <span class="truncate max-w-[140px] inline-block align-bottom"><?= htmlspecialchars($bra['nama_operasi']) ?></span></span>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- Col 4: Parameter Waktu -->
                <td class="px-4 py-4 align-top whitespace-nowrap font-mono text-[11px] text-slate-600 dark:text-slate-400">
                  <div>TWU: <strong class="text-slate-800 dark:text-slate-200"><?= htmlspecialchars($bra['twu'] ?? '-') ?></strong></div>
                  <div class="mt-0.5">TWB: <strong class="text-slate-800 dark:text-slate-200"><?= htmlspecialchars($bra['twb'] ?? '-') ?></strong></div>
                </td>

                <!-- Col 5: Detail Sandi -->
                <td class="px-4 py-4 align-top text-[11px] text-slate-500 dark:text-slate-400">
                  <div>Kata: <strong class="text-slate-800 dark:text-slate-200"><?= (int)($bra['jumlah_kata'] ?? 0) ?></strong></div>
                  <div>Seksi: <strong class="text-slate-800 dark:text-slate-200"><?= count($bra['sections'] ?? []) ?></strong></div>
                </td>

                <!-- Col 6: Aksi Cepat -->
                <td class="px-5 py-4 align-top text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5">
                    <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>"
                       class="px-2.5 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs transition"
                       title="Lihat Dokumen">
                      👁️ Lihat
                    </a>

                    <a href="<?= BASE_URL ?>/bra_form.php?id=<?= $bra['id_bra'] ?>"
                       class="p-1.5 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition"
                       title="Koreksi / Edit Naskah">
                      <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>

                    <?php if (AuthHelper::canDelete()): ?>
                      <form method="POST" action="<?= BASE_URL ?>/bra.php?action=delete" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus naskah Berita Radio SAR <?= htmlspecialchars($bra['no_bra']) ?>? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus Dokumen BRA SAR" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$bra['id_bra'] ?>">
                        <button type="submit" class="p-1.5 text-red-500 hover:text-red-700 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 rounded-xl transition cursor-pointer" title="Hapus Dokumen">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

  <?php endif; ?>

</div>

<!-- ======================================================== -->
<!-- 4. VIEW SWITCHER & UTILITY SCRIPTS -->
<!-- ======================================================== -->
<script>
function setBraView(mode) {
  const containerCards = document.getElementById('bra-container-cards');
  const containerList = document.getElementById('bra-container-list');
  const btnCard = document.getElementById('btn-view-card');
  const btnList = document.getElementById('btn-view-list');

  const activeBtnClasses = ['bg-white', 'dark:bg-slate-900', 'text-orange-600', 'dark:text-orange-400', 'shadow-xs'];
  const inactiveBtnClasses = ['text-slate-600', 'dark:text-slate-400', 'hover:text-slate-900', 'dark:hover:text-white'];

  if (mode === 'list') {
    if (containerCards) containerCards.classList.add('hidden');
    if (containerList) containerList.classList.remove('hidden');

    if (btnList) {
      btnList.classList.add(...activeBtnClasses);
      btnList.classList.remove(...inactiveBtnClasses);
    }
    if (btnCard) {
      btnCard.classList.remove(...activeBtnClasses);
      btnCard.classList.add(...inactiveBtnClasses);
    }
    localStorage.setItem('qr_sar_bra_view', 'list');
  } else {
    if (containerCards) containerCards.classList.remove('hidden');
    if (containerList) containerList.classList.add('hidden');

    if (btnCard) {
      btnCard.classList.add(...activeBtnClasses);
      btnCard.classList.remove(...inactiveBtnClasses);
    }
    if (btnList) {
      btnList.classList.remove(...activeBtnClasses);
      btnList.classList.add(...inactiveBtnClasses);
    }
    localStorage.setItem('qr_sar_bra_view', 'card');
  }
}

function copyBraNo(noBra, btnEl) {
  if (!navigator.clipboard) {
    // Fallback prompt or execCommand
    const tempInput = document.createElement('input');
    tempInput.value = noBra;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    if (typeof showToast === 'function') {
      showToast(`Nomor dokumen ${noBra} disalin!`, 'success');
    }
    return;
  }
  navigator.clipboard.writeText(noBra).then(() => {
    if (typeof showToast === 'function') {
      showToast(`Nomor dokumen ${noBra} disalin!`, 'success');
    }
  });
}

function filterBraLive(query) {
  const q = (query || '').toLowerCase().trim();
  const cards = document.querySelectorAll('.bra-item-card');
  const rows = document.querySelectorAll('.bra-item-row');
  const clearBtn = document.getElementById('btn-clear-bra-search');

  if (clearBtn) {
    if (q.length > 0) {
      clearBtn.classList.remove('hidden');
    } else {
      clearBtn.classList.add('hidden');
    }
  }

  cards.forEach(card => {
    const text = card.getAttribute('data-search') || card.textContent.toLowerCase();
    if (!q || text.includes(q)) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });

  rows.forEach(row => {
    const text = row.getAttribute('data-search') || row.textContent.toLowerCase();
    if (!q || text.includes(q)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

function clearBraSearch() {
  const input = document.getElementById('bra-search-input');
  if (input) {
    input.value = '';
    filterBraLive('');
    input.focus();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const savedView = localStorage.getItem('qr_sar_bra_view') || 'card';
  setBraView(savedView);

  const searchInput = document.getElementById('bra-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      filterBraLive(e.target.value);
    });
    // If there's an initial query, trigger filter once
    if (searchInput.value.trim().length > 0) {
      filterBraLive(searchInput.value.trim());
    }
  }
});
</script>
