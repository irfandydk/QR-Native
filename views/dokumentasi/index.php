<?php
/**
 * QR-SAR - Halaman Pusat Arsip Kronologis Dokumentasi Umum & Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Menampilkan seluruh kolase foto & video (baik Dokumentasi Umum maupun Dokumentasi Operasi SAR)
 * tersusun rapi berdasarkan waktu pembuatannya (per Bulan & Tahun).
 * Dapat diedit ulang penuh oleh seluruh pengguna login; tombol Hapus dikunci khusus Administrator.
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$countAll = (int)($stats['total'] ?? 0);
$countUmum = (int)($stats['umum'] ?? 0);
$countOperasi = (int)($stats['operasi'] ?? 0);
$countImage = (int)($stats['image'] ?? 0);
$countVideo = (int)($stats['video'] ?? 0);
$totalFiltered = count($items ?? []);
$canDelete = AuthHelper::canDelete('dokumentasi');

$hasActiveFilter = ($scopeFilter !== 'all') || ($mediaFilter !== '') || ($monthFilter !== '') || ($searchQuery !== '');

$buildScopeHref = static function (string $targetScope) use ($mediaFilter, $monthFilter, $searchQuery): string {
    $params = ['scope' => $targetScope];
    if ($mediaFilter !== '') {
        $params['media_type'] = $mediaFilter;
    }
    if ($monthFilter !== '') {
        $params['month'] = $monthFilter;
    }
    if ($searchQuery !== '') {
        $params['q'] = $searchQuery;
    }
    return BASE_URL . '/dokumentasi.php?' . http_build_query($params);
};

$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return $bytes . ' B';
};
?>

<style>
  .dokp-accent-btn { background-color: var(--primary); color: #fff !important; border: 1px solid transparent; }
  .dokp-accent-btn:hover { background-color: var(--primary-hover); color: #fff !important; }
  .dokp-accent-soft { background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--m-accent-text, var(--primary)) !important; border: 1px solid color-mix(in srgb, var(--primary) 26%, transparent); }
  .dokp-accent-soft:hover { background-color: var(--primary); color: #fff !important; border-color: transparent; }
  .dokp-accent-text { color: var(--m-accent-text, var(--primary)); }
  .dokp-ghost-btn { background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
  .dokp-ghost-btn:hover { background-color: var(--bg-card-subtle); }
  .dokp-panel { background-color: var(--bg-card); border: 1px solid var(--border-color); }
  .dokp-metric-item { position: relative; display: block; border-radius: 0.5rem; transition: color 160ms ease; }
  .dokp-metric-item[data-active="true"] .dokp-metric-label,
  .dokp-metric-item[data-active="true"] .dokp-metric-val { color: var(--m-accent-text, var(--primary)); }
  .dokp-metric-item[data-active="true"]::after { content: ""; position: absolute; left: 0; right: 0; bottom: -14px; height: 2px; background-color: var(--primary); border-radius: 2px; }
  .dokp-card { transition: border-color 160ms ease, box-shadow 160ms ease; }
  .dokp-card:hover { border-color: color-mix(in srgb, var(--primary) 42%, var(--border-color)); }
</style>

<div class="max-w-7xl mx-auto space-y-5">
  <h1 class="sr-only">Pusat Arsip Dokumentasi Umum dan Operasi SAR</h1>

  <!-- 1. Ringkasan 4 Metrik & Filter Cakupan Cepat -->
  <section aria-label="Ringkasan kategori dokumentasi kolase" class="pb-3.5 border-b border-slate-200 dark:border-slate-800">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-y-3 gap-x-5 lg:gap-x-0 lg:divide-x lg:divide-slate-200 dark:lg:divide-slate-800">

      <div class="lg:pr-5">
        <a href="<?= e($buildScopeHref('all')) ?>"
           data-active="<?= $scopeFilter === 'all' ? 'true' : 'false' ?>"
           class="dokp-metric-item no-underline">
          <div class="dokp-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">Semua Dokumentasi</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span id="dok-stat-total" class="dokp-metric-val text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countAll ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">arsip</span>
          </div>
        </a>
      </div>

      <div class="lg:px-5">
        <a href="<?= e($buildScopeHref('umum')) ?>"
           data-active="<?= $scopeFilter === 'umum' ? 'true' : 'false' ?>"
           class="dokp-metric-item no-underline">
          <div class="dokp-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">Dokumentasi Umum</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span id="dok-stat-umum" class="dokp-metric-val text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countUmum ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">tanpa operasi</span>
          </div>
        </a>
      </div>

      <div class="lg:px-5">
        <a href="<?= e($buildScopeHref('operasi')) ?>"
           data-active="<?= $scopeFilter === 'operasi' ? 'true' : 'false' ?>"
           class="dokp-metric-item no-underline">
          <div class="dokp-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">Operasi SAR</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span id="dok-stat-operasi" class="dokp-metric-val text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countOperasi ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">terkait operasi</span>
          </div>
        </a>
      </div>

      <div class="lg:pl-5">
        <div class="dokp-metric-item">
          <div class="dokp-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">Komposisi Media</div>
          <div class="mt-0.5 flex items-baseline gap-2.5">
            <span class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countImage ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">foto</span>
            <span class="text-slate-300 dark:text-slate-700">&bull;</span>
            <span class="text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countVideo ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">video</span>
          </div>
        </div>
      </div>

    </div>
  </section>

  <!-- 2. Bilah Pencarian, Filter Waktu & Media, dan Tombol Buat Dokumentasi -->
  <section aria-label="Pencarian, filter waktu, dan pembuatan dokumentasi">
    <form id="dokFilterForm" method="GET" action="<?= BASE_URL ?>/dokumentasi.php" class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
      <input type="hidden" name="scope" value="<?= e($scopeFilter) ?>" />

      <div class="flex flex-wrap items-center gap-2 flex-1 min-w-0">
        <!-- Input Pencarian -->
        <div class="relative flex-1 min-w-[220px] lg:max-w-sm">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input type="text"
                 id="dokSearchInput"
                 name="q"
                 value="<?= e($searchQuery) ?>"
                 placeholder="Cari judul kegiatan, kategori, atau operasi..."
                 aria-label="Cari arsip dokumentasi"
                 class="control control-sm w-full placeholder:text-slate-400 dark:placeholder:text-slate-500 pl-9 pr-8 text-xs" />
          <?php if ($searchQuery !== ''): ?>
            <a href="<?= e(BASE_URL . '/dokumentasi.php?' . http_build_query(array_filter(['scope' => $scopeFilter, 'media_type' => $mediaFilter, 'month' => $monthFilter]))) ?>"
               aria-label="Bersihkan kata kunci pencarian"
               class="absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 no-underline"
               title="Bersihkan pencarian">
              <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </a>
          <?php endif; ?>
        </div>

        <!-- Filter Jenis Media -->
        <select name="media_type"
                aria-label="Filter jenis media"
                onchange="this.form.submit()"
                class="control control-sm text-xs font-semibold w-auto min-w-[132px] cursor-pointer">
          <option value="" <?= $mediaFilter === '' ? 'selected' : '' ?>>Semua Media</option>
          <option value="image" <?= $mediaFilter === 'image' ? 'selected' : '' ?>>Foto Kolase</option>
          <option value="video" <?= $mediaFilter === 'video' ? 'selected' : '' ?>>Video Kolase</option>
        </select>

        <!-- Filter Bulan Pembuatan -->
        <select name="month"
                aria-label="Filter bulan pembuatan"
                onchange="this.form.submit()"
                class="control control-sm text-xs font-semibold w-auto min-w-[155px] cursor-pointer">
          <option value="" <?= $monthFilter === '' ? 'selected' : '' ?>>Semua Periode Waktu</option>
          <?php foreach ($availableMonths as $ym): ?>
            <option value="<?= e($ym) ?>" <?= $monthFilter === $ym ? 'selected' : '' ?>>
              <?= e(DokumentasiController::formatMonthYearIndo($ym)) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <?php if ($hasActiveFilter): ?>
          <a href="<?= BASE_URL ?>/dokumentasi.php" class="dokp-ghost-btn inline-flex items-center h-8 px-2.5 rounded-lg text-xs font-semibold no-underline">
            Reset
          </a>
        <?php endif; ?>

        <span class="hidden xl:inline text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap ml-1">
          Menampilkan <strong id="dok-filtered-count" class="font-mono text-slate-800 dark:text-slate-200"><?= $totalFiltered ?></strong> dari <?= $countAll ?> arsip
        </span>
      </div>

      <!-- Kanan: CTA Buat Dokumentasi -->
      <div class="flex items-center gap-2 shrink-0">
        <a href="<?= BASE_URL ?>/dokumentasi.php?action=create"
           class="dokp-accent-btn inline-flex items-center justify-center gap-1.5 h-9 px-4 rounded-xl font-bold text-xs transition no-underline shadow-xs">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
          <span>Buat Dokumentasi</span>
        </a>
      </div>
    </form>
  </section>

  <!-- 3. Daftar Arsip Kronologis Dikelompokkan Berdasarkan Waktu Pembuatan (Bulan & Tahun) -->
  <?php if (empty($groupedArchive)): ?>
    <section class="dokp-panel rounded-2xl p-10 text-center space-y-3">
      <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto border border-slate-200 dark:border-slate-700">
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
      </div>
      <div class="max-w-md mx-auto space-y-1">
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">
          <?= $hasActiveFilter ? 'Tidak Ada Arsip Dokumentasi yang Sesuai Filter' : 'Belum Ada Arsip Dokumentasi Kolase' ?>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          <?= $hasActiveFilter
              ? 'Coba ubah kata kunci pencarian, periode bulan, atau cakupan dokumentasi untuk menampilkan arsip lainnya.'
              : 'Buat kolase foto atau video berformat resmi KPP Kelas A Kendari untuk kegiatan umum kantor maupun operasi SAR aktif.' ?>
        </p>
      </div>
      <div class="pt-1 flex items-center justify-center gap-2">
        <?php if ($hasActiveFilter): ?>
          <a href="<?= BASE_URL ?>/dokumentasi.php" class="dokp-ghost-btn inline-flex items-center h-9 px-4 rounded-xl font-semibold text-xs transition no-underline">
            Tampilkan Semua Arsip
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/dokumentasi.php?action=create" class="dokp-accent-btn inline-flex items-center gap-1.5 h-9 px-4 rounded-xl font-semibold text-xs transition no-underline">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14"/></svg>
          <span>Buat Dokumentasi Baru</span>
        </a>
      </div>
    </section>
  <?php else: ?>
    <div id="dokChronologicalArchive" class="space-y-6">
      <?php foreach ($groupedArchive as $group): ?>
        <section data-month-group="<?= e($group['ym']) ?>" class="space-y-3">
          <!-- Penanda Grup Kronologis Bulan & Tahun -->
          <div class="flex items-center gap-3">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xs">
              <svg class="w-3.5 h-3.5 text-orange-600 dark:text-orange-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
              <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-100">
                <?= e($group['label']) ?>
              </h2>
              <span data-month-count class="px-1.5 py-0.2 rounded-md bg-slate-100 dark:bg-slate-800 font-mono text-[11px] font-bold text-slate-600 dark:text-slate-300">
                <?= count($group['items']) ?>
              </span>
            </div>
            <div class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></div>
          </div>

          <!-- Grid Kartu Arsip pada Bulan Ini -->
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($group['items'] as $dok):
              $dokId = (int)$dok['id_dokumentasi'];
              $idOps = ($dok['id_operasi'] ?? null) !== null ? (int)$dok['id_operasi'] : 0;
              $isUmum = ($idOps <= 0);
              $isVideo = ($dok['media_type'] ?? 'image') === 'video';
              $fileUrl = BASE_URL . '/' . ltrim((string)$dok['file_path'], '/');
              $thumbUrl = !empty($dok['thumb_path'])
                  ? BASE_URL . '/' . ltrim((string)$dok['thumb_path'], '/')
                  : (!$isVideo ? $fileUrl : '');
              $ext = pathinfo((string)$dok['file_path'], PATHINFO_EXTENSION) ?: ($isVideo ? 'webm' : 'jpg');
              $downloadName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)($dok['judul'] ?: ($isUmum ? 'DOK_KEGIATAN' : 'DOK_SAR'))) . '.' . $ext;
              $createdLabel = DokumentasiController::formatWitaTimestamp($dok['created_at'] ?? '');
              $editUrl = BASE_URL . '/dokumentasi.php?action=edit&id=' . $dokId;
            ?>
              <article data-dok-card="<?= $dokId ?>" class="dokp-card dokp-panel rounded-2xl overflow-hidden flex flex-col">
                <button type="button"
                        onclick="openDokArchiveModal(this.dataset.url, this.dataset.type, this.dataset.title)"
                        data-url="<?= e($fileUrl) ?>"
                        data-type="<?= $isVideo ? 'video' : 'image' ?>"
                        data-title="<?= e((string)$dok['judul']) ?>"
                        class="relative aspect-[4/3] w-full bg-slate-100 dark:bg-slate-950 overflow-hidden flex items-center justify-center group cursor-pointer border-b border-slate-100 dark:border-slate-800">
                  <?php if ($thumbUrl !== ''): ?>
                    <img src="<?= e($thumbUrl) ?>" alt="<?= e((string)$dok['judul']) ?>" loading="lazy" class="w-full h-full object-contain group-hover:scale-[1.02] transition-transform duration-300" />
                  <?php else: ?>
                    <video src="<?= e($fileUrl) ?>" muted playsinline preload="metadata" class="w-full h-full object-contain"></video>
                  <?php endif; ?>

                  <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $isVideo ? 'bg-slate-900/90 text-orange-300' : 'bg-slate-900/85 text-white' ?>">
                    <?= $isVideo ? 'VIDEO' : 'FOTO' ?>
                  </span>

                  <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-md bg-white/90 dark:bg-slate-900/90 text-slate-800 dark:text-slate-200 font-mono text-[10px] font-bold border border-slate-200/80 dark:border-slate-700/80">
                    <?= e((string)($dok['aspect_ratio'] ?? '2:3')) ?> &bull; <?= (int)($dok['slot_count'] ?? 6) ?> Slot
                  </span>

                  <?php if ($isVideo): ?>
                    <span class="absolute inset-0 flex items-center justify-center bg-slate-950/20 group-hover:bg-slate-950/35 transition-colors">
                      <span class="w-11 h-11 rounded-full bg-orange-600/95 text-white flex items-center justify-center shadow-lg">
                        <svg class="w-5 h-5 ml-0.5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                      </span>
                    </span>
                  <?php endif; ?>
                </button>

                <div class="p-3.5 flex-1 flex flex-col justify-between gap-3">
                  <div class="space-y-1.5">
                    <!-- Baris Kategori (Umum vs Operasi SAR) & Waktu Pembuatan -->
                    <div class="flex flex-wrap items-center justify-between gap-1.5 text-[11px]">
                      <?php if ($isUmum): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500/12 text-emerald-700 dark:text-emerald-400 border border-emerald-500/25 font-mono text-[10.5px] font-bold">
                          UMUM &bull; <?= e((string)($dok['hari_ke'] ?: 'KEGIATAN')) ?>
                        </span>
                      <?php else: ?>
                        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOps ?>&tab=dokumentasi"
                           title="<?= e((string)($dok['nama_operasi'] ?? 'Buka Detail Operasi SAR')) ?>"
                           class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-orange-500/12 hover:bg-orange-500/20 text-orange-700 dark:text-orange-400 border border-orange-500/25 font-mono text-[10.5px] font-bold no-underline transition">
                          <span>OPS #<?= $idOps ?> &bull; <?= e((string)($dok['hari_ke'] ?: 'H1')) ?></span>
                        </a>
                      <?php endif; ?>

                      <span class="font-mono text-[10.5px] text-slate-500 dark:text-slate-400" title="Waktu pembuatan arsip">
                        <?= e($createdLabel) ?>
                      </span>
                    </div>

                    <h3 class="text-xs font-bold text-slate-900 dark:text-white leading-snug line-clamp-2">
                      <?= e((string)$dok['judul']) ?>
                    </h3>

                    <div class="flex flex-wrap items-center gap-1.5 text-[11px] text-slate-500 dark:text-slate-400">
                      <?php if (!empty($dok['tanggal_label'])): ?>
                        <span class="truncate"><?= e((string)$dok['tanggal_label']) ?></span>
                        <span>&bull;</span>
                      <?php endif; ?>
                      <span class="font-mono"><?= $formatBytes((int)($dok['file_size'] ?? 0)) ?></span>
                    </div>
                  </div>

                  <!-- Tombol Aksi: Lihat, Edit, Unduh, dan Hapus (khusus Admin) -->
                  <div class="flex items-center justify-between gap-1.5 pt-2.5 border-t border-slate-100 dark:border-slate-800/80">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <button type="button"
                              onclick="openDokArchiveModal(this.dataset.url, this.dataset.type, this.dataset.title)"
                              data-url="<?= e($fileUrl) ?>"
                              data-type="<?= $isVideo ? 'video' : 'image' ?>"
                              data-title="<?= e((string)$dok['judul']) ?>"
                              class="dokp-accent-soft inline-flex items-center h-7 px-2.5 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                        <span>Lihat</span>
                      </button>
                      <a href="<?= e($editUrl) ?>"
                         class="dokp-ghost-btn inline-flex items-center gap-1 h-7 px-2.5 rounded-lg text-[11px] font-semibold transition no-underline"
                         title="Edit ulang kolase dokumentasi ini di Studio">
                        <svg class="w-3 h-3" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <span>Edit</span>
                      </a>
                      <a href="<?= e($fileUrl) ?>"
                         download="<?= e($downloadName) ?>"
                         class="dokp-ghost-btn inline-flex items-center h-7 px-2.5 rounded-lg text-[11px] font-semibold transition no-underline">
                        <span>Unduh</span>
                      </a>
                    </div>

                    <?php if (AuthHelper::canDelete('dokumentasi')): ?>
                      <button type="button"
                              onclick="deleteDokumentasiArsip(<?= $dokId ?>, this)"
                              class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:border-rose-200 dark:hover:border-rose-900 transition cursor-pointer"
                              title="Hapus Arsip Dokumentasi (Khusus Admin)"
                              aria-label="Hapus Arsip Dokumentasi">
                        <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                      </button>
                    <?php endif; ?>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<!-- Lightbox Pratinjau Arsip Dokumentasi -->
<div id="dok-archive-lightbox" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/85 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Pratinjau Arsip Dokumentasi" onclick="if(event.target===this) closeDokArchiveModal()">
  <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
      <h3 id="dok-archive-lightbox-title" class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate">Pratinjau Dokumentasi</h3>
      <button type="button" onclick="closeDokArchiveModal()" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" aria-label="Tutup pratinjau">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div id="dok-archive-lightbox-stage" class="p-4 bg-slate-950 flex-1 overflow-auto flex items-center justify-center min-h-[280px]"></div>
  </div>
</div>

<script>
window.openDokArchiveModal = function(url, type, title) {
  const modal = document.getElementById('dok-archive-lightbox');
  const stage = document.getElementById('dok-archive-lightbox-stage');
  const titleEl = document.getElementById('dok-archive-lightbox-title');
  if (!modal || !stage) return;
  if (titleEl) titleEl.textContent = title || 'Pratinjau Dokumentasi Kolase';
  stage.innerHTML = '';
  if (type === 'video') {
    const vid = document.createElement('video');
    vid.src = url;
    vid.controls = true;
    vid.autoplay = true;
    vid.playsInline = true;
    vid.className = 'max-h-[75vh] max-w-full rounded-xl shadow-lg bg-black';
    stage.appendChild(vid);
  } else {
    const img = document.createElement('img');
    img.src = url;
    img.alt = title || 'Dokumentasi Kolase';
    img.className = 'max-h-[75vh] max-w-full object-contain rounded-xl shadow-lg';
    stage.appendChild(img);
  }
  modal.classList.remove('hidden');
  modal.classList.add('flex');
};

window.closeDokArchiveModal = function() {
  const modal = document.getElementById('dok-archive-lightbox');
  const stage = document.getElementById('dok-archive-lightbox-stage');
  if (!modal) return;
  if (stage) {
    const vid = stage.querySelector('video');
    if (vid) vid.pause();
    stage.innerHTML = '';
  }
  modal.classList.add('hidden');
  modal.classList.remove('flex');
};

document.addEventListener('keydown', function(ev) {
  if (ev.key === 'Escape') {
    window.closeDokArchiveModal();
  }
});

<?php if ($canDelete): ?>
window.deleteDokumentasiArsip = async function(docId, btnEl) {
  let confirmed = false;
  if (typeof Swal !== 'undefined') {
    const res = await Swal.fire({
      title: 'Hapus Arsip Dokumentasi?',
      text: 'Berkas kolase foto/video beserta aset proyeknya akan dihapus permanen dari sistem.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e11d48',
      confirmButtonText: 'Ya, Hapus Permanen',
      cancelButtonText: 'Batal'
    });
    confirmed = !!res.isConfirmed;
  } else {
    confirmed = window.confirm('Hapus arsip dokumentasi ini secara permanen?');
  }
  if (!confirmed) return;

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : (window.CSRF_TOKEN || '');
  const baseUrl = window.BASE_URL || '';
  if (btnEl) btnEl.disabled = true;

  try {
    const resp = await fetch(`${baseUrl}/api/index.php?path=/dokumentasi/${docId}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    const data = await resp.json();
    if (!resp.ok || !data.success) {
      throw new Error(data.error || data.message || 'Gagal menghapus arsip dokumentasi.');
    }

    const card = document.querySelector(`[data-dok-card="${docId}"]`);
    if (card) {
      const monthSection = card.closest('[data-month-group]');
      card.remove();
      if (monthSection) {
        const leftInMonth = monthSection.querySelectorAll('[data-dok-card]').length;
        const badge = monthSection.querySelector('[data-month-count]');
        if (badge) badge.textContent = String(leftInMonth);
        if (leftInMonth === 0) monthSection.remove();
      }
    }

    const remainingAll = document.querySelectorAll('[data-dok-card]').length;
    const filteredEl = document.getElementById('dok-filtered-count');
    if (filteredEl) filteredEl.textContent = String(remainingAll);
    if (typeof showToast === 'function') showToast('Arsip dokumentasi berhasil dihapus.', 'success');
    if (remainingAll === 0) window.location.reload();
  } catch (err) {
    if (btnEl) btnEl.disabled = false;
    if (typeof showToast === 'function') showToast(err.message || 'Gagal menghapus arsip.', 'error');
  }
};
<?php endif; ?>
</script>
