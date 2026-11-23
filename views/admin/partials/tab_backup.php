<?php
/**
 * QR-SAR - Admin Tab Partial: Backup & Restore Basis Data (SQL)
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$statItems = [
    ['Operasi SAR', (int)($dbStats['total_operasi'] ?? 0)],
    ['Data Korban', (int)($dbStats['total_korban'] ?? 0)],
    ['Dokumen BRA', (int)($dbStats['total_bra_sar'] ?? 0)],
    ['Renops SAR', (int)($dbStats['total_renops'] ?? 0)],
    ['Laporan Harian', (int)($dbStats['total_laporan_harian'] ?? 0)],
    ['Master Alut', (int)($dbStats['total_master_alut'] ?? 0)],
    ['Master Potensi', (int)($dbStats['total_master_tim'] ?? 0)],
    ['Pangkalan SAR', (int)($dbStats['total_sar_units'] ?? 0)],
    ['Pejabat & SMC', (int)($dbStats['total_pejabat'] ?? 0)],
    ['Titik GIS Map', (int)($dbStats['total_gis_points'] ?? 0)],
    ['Polygon Area', (int)($dbStats['total_gis_polygons'] ?? 0)],
    ['Rute Jarak GIS', (int)($dbStats['total_gis_routes'] ?? 0)],
];
?>
<div class="space-y-5">

  <!-- Hairline Database Statistics Grid (Zero 12-Box Nesting) -->
  <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
      <div>
        <h2 class="text-sm font-bold text-slate-900 dark:text-white">Statistik Basis Data Aktif</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Ringkasan seluruh entitas operasional pada server MySQL.</p>
      </div>
      <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md font-mono text-[11px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500" aria-hidden="true"></span>
        <span>Tersinkron</span>
      </span>
    </div>

    <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-x-5 gap-y-4">
      <?php foreach ($statItems as [$label, $val]): ?>
        <div class="border-t border-slate-100 dark:border-slate-800/80 pt-2.5">
          <dt class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><?= e($label) ?></dt>
          <dd class="text-xl font-bold font-mono text-slate-900 dark:text-white mt-0.5 leading-none"><?= $val ?></dd>
        </div>
      <?php endforeach; ?>
    </dl>
  </section>

  <!-- Export & Restore Actions Row (SQL Only) -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

    <!-- Card 1: Ekspor SQL -->
    <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
      <div class="flex items-start justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Ekspor Dump Basis Data (.sql)</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Cadangan penuh struktur DDL dan seluruh data DML 23 tabel.</p>
        </div>
        <span class="font-mono text-[11px] font-semibold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 shrink-0">.SQL</span>
      </div>

      <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
        Berkas dump SQL kompatibel langsung untuk migrasi MySQL / MariaDB via phpMyAdmin, CLI mysql, maupun pemulihan pada halaman ini. Lakukan pencadangan berkala sebelum pembaruan sistem.
      </p>

      <div class="pt-1">
        <a href="<?= BASE_URL ?>/admin.php?action=export_sql" class="adm-accent-btn inline-flex items-center justify-center gap-2 w-full sm:w-auto h-9 px-4 rounded-xl font-bold text-xs transition no-underline">
          <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
          <span>Unduh Cadangan SQL</span>
        </a>
      </div>
    </section>

    <!-- Card 2: Pemulihan SQL -->
    <section class="bento-card-v1 rounded-2xl p-5 sm:p-6 space-y-4">
      <div class="pb-3 border-b border-slate-100 dark:border-slate-800">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pemulihan Basis Data (Restore SQL)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Pulihkan seluruh data sistem dari berkas cadangan SQL.</p>
      </div>

      <div class="pl-3 py-1 border-l-2 border-amber-500 text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
        <strong class="text-slate-800 dark:text-slate-200">Perhatian:</strong> Pemulihan akan menimpa tabel saat ini. Pastikan Anda sudah mengunduh cadangan terbaru sebelum melanjutkan.
      </div>

      <form action="<?= BASE_URL ?>/admin.php?action=restore_sql" method="POST" enctype="multipart/form-data" class="space-y-3.5" data-confirm="Apakah Anda yakin ingin memulihkan seluruh basis data sistem dari berkas SQL yang dipilih? Tindakan ini akan menimpa data yang ada saat ini." data-confirm-title="Konfirmasi Pemulihan SQL" data-confirm-btn="Ya, Pulihkan Sekarang">
        <?= csrfInput() ?>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">
            Berkas Cadangan (.sql) <span class="text-rose-500">*</span>
          </label>
          <input type="file" name="backup_file" accept=".sql" required class="w-full px-3 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs file:mr-3 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 dark:file:bg-slate-800 dark:file:text-slate-200 cursor-pointer" />
        </div>

        <button type="submit" class="w-full h-9 inline-flex items-center justify-center gap-2 px-4 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition cursor-pointer">
          <span>Pulihkan Database dari SQL</span>
        </button>
      </form>
    </section>

  </div>

</div>
