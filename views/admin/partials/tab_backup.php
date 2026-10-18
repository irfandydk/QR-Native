<?php
/**
 * QR-SAR - Admin Tab Partial: Backup & Restore Basis Data (SQL)
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}
?>
<div class="space-y-6">
  
  <!-- Live Statistics Tiles (100% Coverage) -->
  <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
    <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
        </div>
        <div>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white">Statistik Basis Data Aktif</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">Ringkasan entitas data operasional yang tersimpan di server MySQL</p>
        </div>
      </div>
      <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
        STATUS: AKTIF & TERSINKRON
      </span>
    </div>

    <!-- Counter Cards Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3.5 mt-5">
      
      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Operasi SAR</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_operasi'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Data Korban</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_korban'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Dokumen BRA</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_bra_sar'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Renops SAR</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_renops'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Laporan Harian</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_laporan_harian'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Master Alut</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_master_alut'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Master Potensi</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_master_tim'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Pangkalan SAR</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_sar_units'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Pejabat & SMC</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_pejabat'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Titik GIS Map</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_gis_points'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Polygon Area</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_gis_polygons'] ?? 0) ?></p>
      </div>

      <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50">
        <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Rute Jarak GIS</span>
        <p class="text-xl font-black text-slate-900 dark:text-white mt-1"><?= (int)($dbStats['total_gis_routes'] ?? 0) ?></p>
      </div>

    </div>
  </div>

  <!-- Export & Restore Actions Row (Strictly SQL Format Only) -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    
    <!-- Card Ekspor Cadangan SQL -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs flex flex-col justify-between">
      <div>
        <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
          <div class="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Ekspor Dump Basis Data SQL (.sql)</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pencadangan resmi basis data MySQL server dalam format skrip SQL murni</p>
          </div>
        </div>

        <div class="space-y-4 my-5">
          <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/30 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">FORMAT RESMI .SQL</span>
                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">MySQL Database Dump File</h4>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Mencakup seluruh skrip DDL (struktur tabel) dan DML (seluruh record data) dari 23 tabel entitas operasional SAR, BRA, Renops, Korban, hingga konfigurasi sistem.
              </p>
            </div>
            <a href="<?= BASE_URL ?>/admin.php?action=export_sql" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/30 transition-all shrink-0 cursor-pointer">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
              <span>Unduh Cadangan SQL</span>
            </a>
          </div>

          <div class="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-blue-800 dark:text-blue-300 text-xs flex items-start gap-2.5">
            <svg class="w-4 h-4 text-blue-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="16" y2="12"/><line x1="12" x2="12.01" y1="8" y2="8"/></svg>
            <p class="text-[11px] leading-relaxed">
              Berkas dump SQL ini kompatibel 100% untuk migrasi langsung ke server MySQL / MariaDB via phpMyAdmin, CLI mysql, maupun fitur pemulihan sistem ini.
            </p>
          </div>
        </div>
      </div>

      <p class="text-[11px] text-slate-400 text-center pt-2">
        Lakukan pencadangan berkala sebelum pembaruan sistem atau penutupan operasi bulanan.
      </p>
    </div>

    <!-- Card Impor / Pemulihan Basis Data SQL -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs">
      <div class="flex items-center gap-3 pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pemulihan Basis Data (Restore SQL)</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400">Pulihkan seluruh data sistem dari berkas cadangan SQL (.sql)</p>
        </div>
      </div>

      <div class="p-3 my-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300 text-xs flex items-start gap-2.5">
        <svg class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" x2="12" y1="9" y2="13"/><line x1="12" x2="12.01" y1="17" y2="17"/></svg>
        <div>
          <p class="font-bold">Peringatan Pemulihan Data</p>
          <p class="text-[11px] opacity-90 mt-0.5">Memulihkan basis data dari berkas SQL akan menimpa tabel yang ada saat ini. Pastikan Anda telah mengunduh cadangan terbaru sebelum melanjutkan.</p>
        </div>
      </div>

      <form action="<?= BASE_URL ?>/admin.php?action=restore_sql" method="POST" enctype="multipart/form-data" class="space-y-4" data-confirm="Apakah Anda yakin ingin memulihkan seluruh basis data sistem dari berkas SQL yang dipilih? Tindakan ini akan menimpa data yang ada saat ini." data-confirm-title="Konfirmasi Pemulihan SQL" data-confirm-btn="Ya, Pulihkan Sekarang">
        <?= csrfInput() ?>
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Pilih Berkas Cadangan Database (.sql) <span class="text-red-500">*</span>
          </label>
          <input type="file" name="backup_file" accept=".sql" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-orange-50 file:text-orange-600 dark:file:bg-orange-500/20 dark:file:text-orange-400 cursor-pointer" />
        </div>

        <div class="pt-2">
          <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/30 transition-all cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            <span>Pulihkan Database dari SQL Sekarang</span>
          </button>
        </div>
      </form>
    </div>

  </div>

</div>
