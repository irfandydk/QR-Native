<?php
/**
 * QR-SAR - Detail Operasi SAR (Modular Architecture)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (empty($operasi)) {
    echo "<div class='p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300'>Operasi SAR tidak ditemukan.</div>";
    return;
}

$id = (int)$operasi['id_operasi'];
$isOpen = ($operasi['status'] ?? '') === 'OPEN';
$waktuMulaiOps = TemplateHelper::getWaktuMulaiAktual($operasi);
$tglMulaiOps = date('Y-m-d', strtotime($waktuMulaiOps));
$hariKe = TemplateHelper::hitungHariKe($operasi);
$statsKorban = $operasi['stats_korban'] ?? ['total' => 0, 's' => 0, 'md' => 0, 'dp' => 0, 'h' => 0];
$tglKejadian = TemplateHelper::formatTanggalIndo($operasi['waktu_kejadian']);
$jamKejadian = TemplateHelper::formatJamWITA($operasi['waktu_kejadian']);
$tglBerangkat = TemplateHelper::formatTanggalIndo($operasi['waktu_berangkat'] ?: $waktuMulaiOps);
$jamBerangkat = TemplateHelper::formatJamWITA($operasi['waktu_berangkat'] ?: $waktuMulaiOps);
$tglSelesai = !empty($operasi['waktu_selesai']) ? TemplateHelper::formatTanggalIndo($operasi['waktu_selesai']) : null;
$jamSelesai = !empty($operasi['waktu_selesai']) ? TemplateHelper::formatJamWITA($operasi['waktu_selesai']) : null;
$isReopened = !empty($operasi['waktu_reopen']);
$tglReopen = $isReopened ? TemplateHelper::formatTanggalIndo($operasi['waktu_reopen']) : null;
$jamReopen = $isReopened ? TemplateHelper::formatJamWITA($operasi['waktu_reopen']) : null;

$activeTab = $_GET['tab'] ?? 'hierarchy';
if ($activeTab === 'ringkasan') {
    $activeTab = 'hierarchy';
}
if ($activeTab === 'laporan') {
    $activeTab = 'sitrep';
}
if ($activeTab === 'dokumen_renops') {
    $activeTab = 'cetak';
}

$timeline = HierarchyHelper::getOperationalTimeline($operasi);
$nextRenops = HierarchyHelper::getNextRenopsDefaults($operasi);
$nextLapbang = HierarchyHelper::getNextLapbangDefaults($operasi);

$lkpCoords = GeoHelper::parseCoordinates($operasi['koordinat'] ?? '');
$hasLkpCoords = !empty($lkpCoords) && isset($lkpCoords['lat']) && isset($lkpCoords['lng']);
$lkpLat = $hasLkpCoords ? (float)$lkpCoords['lat'] : -4.037694;
$lkpLng = $hasLkpCoords ? (float)$lkpCoords['lng'] : 122.486694;

$countRenops = count($operasi['renops'] ?? []);
$countSitrep = count($operasi['laporan_harian'] ?? []);
$countKorban = count($operasi['korban'] ?? []);
$countBra = count($operasi['bra_sar'] ?? []);
$countDokumentasi = count($operasi['dokumentasi'] ?? []);

$gisBundle = $gisBundle ?? GisModel::getOperationBundle($id, true);
$cntPolys = count($gisBundle['polygons'] ?? []);
$cntPatterns = count($gisBundle['searchPatterns'] ?? []);
$cntRoutes = count($gisBundle['routes'] ?? []);
$cntPoints = count($gisBundle['points'] ?? []);
$totalComponents = $cntPolys + $cntPatterns + $cntRoutes + $cntPoints;

$pobTotal = (int)($statsKorban['total'] ?? $countKorban);
$pobS = (int)($statsKorban['s'] ?? 0);
$pobMd = (int)($statsKorban['md'] ?? 0);
$pobDp = (int)($statsKorban['dp'] ?? 0);
$pobH = (int)($statsKorban['h'] ?? 0);
?>

<style>
  .opsd-root {
    --opsd-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 12px 28px -20px rgba(15, 23, 42, 0.14);
    --opsd-spring: cubic-bezier(0.22, 1, 0.36, 1);
  }
  html.dark .opsd-root, html[data-theme="dark"] .opsd-root {
    --opsd-shadow: 0 1px 2px rgba(2, 6, 23, 0.45), 0 16px 34px -20px rgba(2, 6, 23, 0.72), inset 0 1px 0 rgba(255, 255, 255, 0.05);
  }
  .opsd-panel { background-color: var(--bg-card); border: 1px solid var(--border-color); box-shadow: var(--opsd-shadow); }
  .opsd-hairline-grid { background-color: var(--border-color); border: 1px solid var(--border-color); box-shadow: var(--opsd-shadow); }
  .opsd-hairline-cell { background-color: var(--bg-card); }
  .opsd-row-card { transition: border-color 180ms ease, box-shadow 200ms ease; }
  .opsd-row-card:hover { border-color: color-mix(in srgb, var(--primary) 36%, var(--border-color)); }
  .opsd-title { font-size: clamp(1.0625rem, 0.98rem + 0.45vw, 1.3125rem); line-height: 1.24; letter-spacing: -0.022em; text-wrap: balance; }
  .opsd-num { font-variant-numeric: tabular-nums; }
  .opsd-justify { text-align: justify; text-justify: inter-word; hyphens: auto; -webkit-hyphens: auto; }
  .opsd-accent-btn { background-color: var(--primary); color: #fff !important; border: 1px solid transparent; }
  .opsd-accent-btn:hover { background-color: var(--primary-hover); color: #fff !important; }
  .opsd-accent-soft { background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--m-accent-text, var(--primary)) !important; border: 1px solid color-mix(in srgb, var(--primary) 28%, transparent); }
  .opsd-accent-soft:hover { background-color: var(--primary); color: #fff !important; border-color: transparent; }
  .opsd-accent-badge { background-color: var(--primary); color: #fff; }
  .opsd-accent-text { color: var(--m-accent-text, var(--primary)); }
  .opsd-ghost-btn { background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
  .opsd-ghost-btn:hover { background-color: var(--bg-card-subtle); border-color: color-mix(in srgb, var(--primary) 32%, var(--border-color)); }
  .opsd-accent-btn:focus-visible, .opsd-accent-soft:focus-visible, .opsd-ghost-btn:focus-visible, .ops-detail-tabs .tab-btn:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
  .ops-detail-tabs { margin-bottom: 0 !important; gap: 0.125rem; scrollbar-width: none; }
  .ops-detail-tabs::-webkit-scrollbar { display: none; }
  .ops-detail-tabs .tab-btn { padding: 0.65rem 0.8rem; font-size: 0.75rem; font-weight: 600; border-radius: 0; border-bottom: 2px solid transparent; background: transparent !important; box-shadow: none !important; color: var(--text-muted); gap: 0.375rem; white-space: nowrap; }
  .ops-detail-tabs .tab-btn:hover { color: var(--text-main); background: color-mix(in srgb, var(--bg-card-subtle) 55%, transparent) !important; transform: none; }
  .ops-detail-tabs .tab-btn.active { color: var(--m-accent-text, var(--primary)) !important; border-bottom-color: var(--primary) !important; background: transparent !important; box-shadow: none !important; font-weight: 700; }
  .ops-detail-tabs .tab-btn .opsd-tab-count { font-family: 'JetBrains Mono', monospace; font-variant-numeric: tabular-nums; font-size: 0.65rem; padding: 0.08rem 0.4rem; border-radius: 9999px; background: var(--bg-card-subtle); color: var(--text-muted); }
  .ops-detail-tabs .tab-btn.active .opsd-tab-count { background: color-mix(in srgb, var(--primary) 14%, transparent); color: var(--m-accent-text, var(--primary)); }
  .ops-gis-seg button.bg-orange-600 { background-color: var(--primary); color: #fff; }
  @media (prefers-reduced-motion: no-preference) {
    .opsd-accent-btn:active, .opsd-accent-soft:active, .opsd-ghost-btn:active { transform: scale(0.98); }
  }
  @media (prefers-reduced-motion: reduce) {
    .opsd-accent-btn, .opsd-accent-soft, .opsd-ghost-btn, .opsd-row-card, .ops-detail-tabs .tab-btn { transition: none !important; transform: none !important; }
  }
</style>

<div class="opsd-root overflow-x-hidden w-full max-w-full">
  <div class="max-w-7xl mx-auto space-y-5">

    <!-- ======================================================== -->
    <!-- UNIFIED MISSION COMMAND PANEL + INTEGRATED TAB RAIL      -->
    <!-- ======================================================== -->
    <section aria-label="Ringkasan, aksi, dan navigasi operasi" class="opsd-panel rounded-2xl overflow-hidden">
      <!-- Top Row: Breadcrumb, Status, Mission Title & Action Cluster -->
      <div class="px-4 sm:px-5 pt-4 pb-3.5 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2.5 no-print">
          <div class="flex flex-wrap items-center gap-2 text-xs min-w-0">
            <a href="<?= BASE_URL ?>/operasi.php" class="inline-flex items-center gap-1.5 font-semibold text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition-colors">
              <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
              <span>Operasi SAR</span>
            </a>
            <span class="text-slate-300 dark:text-slate-700" aria-hidden="true">/</span>
            <span class="opsd-num font-mono text-[11px] font-semibold text-slate-500 dark:text-slate-400">#<?= $id ?></span>

            <?php if ($isOpen): ?>
              <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-bold tracking-wide bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0" aria-hidden="true"></span>
                <span>OPEN</span>
              </span>
              <span class="opsd-num font-mono text-xs font-bold opsd-accent-text"><?= e($hariKe) ?></span>
              <?php if ($isReopened): ?>
                <span class="px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-amber-500/15 text-amber-700 dark:text-amber-400" title="Dibuka kembali pada <?= $tglReopen ?> <?= $jamReopen ?> WITA">
                  DIPERPANJANG
                </span>
              <?php endif; ?>
            <?php else: ?>
              <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold tracking-wide bg-slate-200/80 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                CLOSED
              </span>
              <span class="opsd-num font-mono text-xs font-semibold text-slate-500 dark:text-slate-400"><?= e($hariKe) ?></span>
            <?php endif; ?>

            <?php if (!empty($operasi['jenis_kejadian'])): ?>
              <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800/90 text-slate-600 dark:text-slate-300">
                <?= e($operasi['jenis_kejadian']) ?>
              </span>
            <?php endif; ?>
          </div>

          <div class="flex flex-wrap items-center gap-1.5 min-w-0 max-w-full lg:shrink-0">
            <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= $id ?>&include_base=1" class="opsd-accent-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition whitespace-nowrap" title="Buka Peta Taktis GIS Operasi Ini">
              <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" y1="3" x2="9" y2="18"/><line x1="15" y1="6" x2="15" y2="21"/></svg>
              <span>Peta GIS</span>
            </a>

            <button type="button" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition cursor-pointer whitespace-nowrap" onclick="openModal('modal-view-wa-awal')" title="Lihat naskah laporan awal format WhatsApp">
              <svg class="w-3.5 h-3.5 text-emerald-500" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
              <span>Naskah WA</span>
            </button>

            <?php if ($isOpen): ?>
              <a href="<?= BASE_URL ?>/operasi_form.php?id=<?= $id ?>" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs transition whitespace-nowrap" title="Koreksi data inisiasi laporan awal">
                <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                <span>Edit</span>
              </a>

              <button type="button" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-rose-500/10 hover:bg-rose-600 text-rose-600 dark:text-rose-400 hover:text-white border border-rose-500/25 font-semibold text-xs transition cursor-pointer whitespace-nowrap" onclick="openModal('modal-close-ops-detail')" title="Tutup Operasi SAR secara resmi">
                <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>Tutup Operasi</span>
              </button>
            <?php else: ?>
              <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition cursor-pointer whitespace-nowrap" title="Perpanjang hari operasi SAR (buka hari lanjutan)">
                <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                <span>Perpanjang Operasi</span>
              </button>
            <?php endif; ?>

            <?php if (AuthHelper::canDelete()): ?>
              <form method="POST" action="<?= BASE_URL ?>/operasi.php?action=delete" class="inline-flex" data-confirm="Apakah Anda yakin ingin menghapus Operasi SAR ini secara permanen beserta seluruh Renops, Lapbang, dan data terkait? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus Operasi SAR Permanen" data-confirm-btn="Ya, Hapus Permanen" data-confirm-danger="true">
                <?= csrfInput() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:border-rose-200 dark:hover:border-rose-900 transition cursor-pointer" title="Hapus Operasi Permanen" aria-label="Hapus operasi permanen">
                  <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>

        <h1 class="opsd-title font-bold text-slate-900 dark:text-white max-w-4xl">
          <?= e($operasi['nama_operasi']) ?>
        </h1>
      </div>

      <!-- Hairline Metadata Strip -->
      <div class="px-4 sm:px-5 py-2.5 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/70 dark:bg-slate-950/35 flex flex-wrap items-center gap-x-5 gap-y-1.5 text-xs text-slate-600 dark:text-slate-400">
        <span class="inline-flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          <span>Mulai <strong class="font-semibold text-slate-800 dark:text-slate-200"><?= TemplateHelper::formatTanggalIndo($waktuMulaiOps) ?></strong> <span class="opsd-num font-mono text-[11px]">(<?= TemplateHelper::formatJamWITA($waktuMulaiOps) ?> WITA)</span></span>
        </span>

        <?php if (!empty($operasi['lokasi_kejadian'])): ?>
          <span class="inline-flex items-center gap-1.5 min-w-0" title="<?= e($operasi['lokasi_kejadian']) ?>">
            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
            <span class="truncate max-w-md"><?= e($operasi['lokasi_kejadian']) ?></span>
          </span>
        <?php endif; ?>

        <?php if (!empty($operasi['koordinat'])): ?>
          <span class="opsd-num font-mono text-[11px] px-2 py-0.5 rounded-md bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-slate-700 dark:text-slate-300">
            LKP <?= e($operasi['koordinat']) ?>
          </span>
        <?php endif; ?>

        <span class="opsd-num inline-flex items-center gap-1.5 font-mono text-[11px]">
          <span class="text-slate-500 dark:text-slate-400">POB <?= $pobTotal ?>:</span>
          <span class="text-emerald-700 dark:text-emerald-400 font-semibold"><?= $pobS ?> S</span>
          <span class="text-rose-700 dark:text-rose-400 font-semibold"><?= $pobMd ?> MD</span>
          <span class="text-amber-700 dark:text-amber-400 font-semibold"><?= $pobDp ?> DP</span>
          <?php if ($pobH > 0): ?><span class="text-slate-500 dark:text-slate-400 font-semibold"><?= $pobH ?> H</span><?php endif; ?>
        </span>

        <?php if (!$isOpen && $tglSelesai): ?>
          <span class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Ditutup <?= e($tglSelesai) ?></span>
        <?php endif; ?>
      </div>

      <!-- Integrated Flush Underline Tab Rail -->
      <nav aria-label="Navigasi modul detail operasi" class="tabs-header ops-detail-tabs border-t border-slate-200/80 dark:border-slate-800 px-2 sm:px-3 flex items-center overflow-x-auto no-print">
        <button type="button" class="tab-btn <?= $activeTab === 'hierarchy' ? 'active' : '' ?>" onclick="switchTab('hierarchy')">
          <span>Hirarki</span>
          <span class="opsd-tab-count"><?= count($timeline) ?></span>
        </button>
        <button type="button" class="tab-btn <?= $activeTab === 'renops' ? 'active' : '' ?>" onclick="switchTab('renops')">
          <span>RENOPS</span>
          <span class="opsd-tab-count"><?= $countRenops ?></span>
        </button>
        <button type="button" class="tab-btn <?= ($activeTab === 'sitrep' || $activeTab === 'laporan') ? 'active' : '' ?>" onclick="switchTab('sitrep')">
          <span>LAPBANG</span>
          <span class="opsd-tab-count"><?= $countSitrep ?></span>
        </button>
        <button type="button" class="tab-btn <?= $activeTab === 'cetak' ? 'active' : '' ?>" onclick="switchTab('cetak')">
          <span>Dokumen RENOPS</span>
          <span class="opsd-tab-count"><?= $countRenops ?></span>
        </button>
        <button type="button" class="tab-btn <?= $activeTab === 'bra' ? 'active' : '' ?>" onclick="switchTab('bra')">
          <span>BRA SAR</span>
          <span class="opsd-tab-count"><?= $countBra ?></span>
        </button>
        <button type="button" class="tab-btn <?= $activeTab === 'dokumentasi' ? 'active' : '' ?>" onclick="switchTab('dokumentasi')">
          <span>Dokumentasi</span>
          <span id="dok-tab-count" class="opsd-tab-count"><?= $countDokumentasi ?></span>
        </button>
        <button type="button" class="tab-btn <?= $activeTab === 'korban' ? 'active' : '' ?>" onclick="switchTab('korban')">
          <span>Korban</span>
          <span class="opsd-tab-count"><?= $countKorban ?></span>
        </button>
        <button type="button" class="tab-btn <?= $activeTab === 'kekuatan' ? 'active' : '' ?>" onclick="switchTab('kekuatan')">
          <span>Unsur &amp; Alut</span>
        </button>
        <button type="button" class="tab-btn <?= $activeTab === 'gis' ? 'active' : '' ?>" onclick="switchTab('gis')">
          <span>Peta GIS</span>
          <span id="gis-tab-count" class="opsd-tab-count <?= $totalComponents > 0 ? '' : 'hidden' ?>"><?= $totalComponents ?></span>
        </button>
      </nav>
    </section>

    <!-- ======================================================== -->
    <!-- TAB CONTENT PANES (MODULAR PARTIALS)                     -->
    <!-- ======================================================== -->
    <div>
      <?php
      $contextData = get_defined_vars();
      renderPartial('operasi/partials/tab_hierarchy', $contextData);
      renderPartial('operasi/partials/tab_renops', $contextData);
      renderPartial('operasi/partials/tab_sitrep', $contextData);
      renderPartial('operasi/partials/tab_cetak', $contextData);
      renderPartial('operasi/partials/tab_bra', $contextData);
      renderPartial('operasi/partials/tab_dokumentasi', $contextData);
      renderPartial('operasi/partials/tab_korban', $contextData);
      renderPartial('operasi/partials/tab_kekuatan', $contextData);
      renderPartial('operasi/partials/tab_gis', $contextData);
      renderPartial('operasi/partials/modals', $contextData);
      ?>
    </div>

  </div>
</div>

<script>
window.__OPS_DETAIL_CONFIG__ = <?= jsonSafe([
    'idOperasi' => (int)$operasi['id_operasi'],
    'namaOperasi' => $operasi['nama_operasi'] ?? '',
    'activeTab' => $activeTab ?? 'overview',
    'lkpLat' => $lkpLat ?? null,
    'lkpLng' => $lkpLng ?? null,
    'hasLkpCoords' => $hasLkpCoords ?? false,
    'gisBundle' => $gisBundle ?? [],
    'opsData' => [
        'id_operasi' => (int)$operasi['id_operasi'],
        'nama_operasi' => $operasi['nama_operasi'],
        'status' => $operasi['status'],
        'is_open' => $operasi['status'] === 'OPEN',
        'can_delete' => AuthHelper::canDelete(),
        'waktu_berangkat' => $operasi['waktu_berangkat'],
        'waktu_lapor' => $operasi['waktu_lapor'],
        'waktu_kejadian' => $operasi['waktu_kejadian'],
        'waktu_mulai_operasi' => $waktuMulaiOps,
        'tgl_mulai_operasi' => $tglMulaiOps,
        'waktu_reopen' => $operasi['waktu_reopen'] ?? null,
        'hari_sebelum_reopen' => $operasi['hari_sebelum_reopen'] ?? null,
        'detail_korban' => $operasi['korban'] ?? [],
        'detail_unsur' => $operasi['detail_unsur'] ?? [],
        'detail_alut' => $operasi['detail_alut'] ?? [],
        'renops' => $operasi['renops'] ?? [],
        'laporan' => $operasi['laporan_harian'] ?? []
    ]
]) ?>;
</script>
<script src="<?= assetUrl('assets/js/pages/operasi_detail.js') ?>"></script>
