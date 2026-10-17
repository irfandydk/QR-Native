<?php
/**
 * QR-SAR - Dedicated View Form Rencana Operasi (RENOPS)
 * KPP Kendari - Basarnas
 *
 * @var int $idOperasi
 * @var bool $editMode
 * @var int $idRenops
 * @var array $operasi
 * @var array $masterTim
 * @var array $masterAlut
 * @var string $hariKe
 * @var string $tanggalLaporan
 * @var string $waktuBriefing
 * @var string $waktuMulaiOps
 * @var string $cuaca
 * @var array $initialUnsurData
 * @var array $initialAlutData
 * @var array $initialSrus
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

// Defensive fallback initialization for IDE static analysis and safe isolated view evaluation
$idOperasi = $idOperasi ?? (int)($_GET['id_operasi'] ?? 0);
$editMode = $editMode ?? false;
$idRenops = $idRenops ?? (int)($_GET['id_renops'] ?? 0);
$operasi = $operasi ?? [];
$hariKe = $hariKe ?? 'H.1';
$tanggalLaporan = $tanggalLaporan ?? date('Y-m-d');
$waktuBriefing = $waktuBriefing ?? date('Y-m-d\T07:00');
$waktuMulaiOps = $waktuMulaiOps ?? date('Y-m-d\T07:30');
$cuaca = $cuaca ?? 'Cerah Berawan';
$masterTim = $masterTim ?? [];
$masterAlut = $masterAlut ?? [];
$initialUnsurData = $initialUnsurData ?? [];
$initialAlutData = $initialAlutData ?? [];
$initialSrus = $initialSrus ?? [];
$gisBundle = $gisBundle ?? ['points' => [], 'polygons' => [], 'routes' => [], 'searchPatterns' => []];
$totalGisItems = count($gisBundle['polygons'] ?? []) + count($gisBundle['searchPatterns'] ?? []) + count($gisBundle['routes'] ?? []) + count($gisBundle['points'] ?? []);
?>

<div class="max-w-7xl mx-auto space-y-6 pb-20">

  <!-- Breadcrumb & Top Bar -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors">
    <div class="space-y-1.5 flex-1 min-w-0">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>" class="hover:text-orange-500 transition-colors flex items-center gap-1 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          Detail Operasi
        </a>
        <span>/</span>
        <span class="text-orange-600 dark:text-orange-400 font-bold"><?= $editMode ? 'Edit RENOPS' : 'Penerbitan RENOPS' ?></span>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
          <?= $editMode ? "Edit Rencana Operasi SAR ({$hariKe})" : "Penerbitan Rencana Operasi SAR ({$hariKe})" ?>
        </h1>
        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full <?= $editMode ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30' ?> uppercase tracking-wider shrink-0">
          <?= $editMode ? 'MODE PERBAIKAN' : 'DOKUMEN RENOPS' ?>
        </span>
      </div>
      <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 line-clamp-1 max-w-2xl">
        Operasi: <?= e($operasi['nama_operasi']) ?>
      </p>
    </div>

    <!-- Quick Action Buttons -->
    <div class="flex items-center gap-2.5 shrink-0 self-end sm:self-auto">
      <button type="button" onclick="previewRenopsWa()" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Pratinjau Format WA</span>
      </button>
      <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>&tab=renops" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        Batal
      </a>
    </div>
  </div>

  <form id="form-renops" method="POST" action="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $idOperasi ?><?= $editMode ? "&id_renops={$idRenops}" : "" ?>" class="space-y-6">
    <?= csrfInput() ?>
    <input type="hidden" name="id_operasi" value="<?= $idOperasi ?>">
    <?php if ($editMode): ?>
      <input type="hidden" name="id_renops" value="<?= $idRenops ?>">
    <?php endif; ?>

    <?php if (!empty($operasi['waktu_reopen'])): 
      $baseHariReopen = TemplateHelper::getBaseHariSebelumReopen($operasi);
    ?>
    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 flex items-center gap-3">
      <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
      <div class="text-xs">
        <p class="font-bold">Operasi SAR Diperpanjang / Dibuka Kembali</p>
        <p class="text-[11px] opacity-90">Operasi dibuka kembali pada <strong><?= TemplateHelper::formatTanggalIndo($operasi['waktu_reopen']) ?> (<?= TemplateHelper::formatJamWITA($operasi['waktu_reopen']) ?> WITA)</strong>. Perhitungan hari operasional lanjutan menggunakan format <strong>H<?= $baseHariReopen ?> + 1, H<?= $baseHariReopen ?> + 2, dst.</strong> sejak tanggal pembukaan ini.</p>
      </div>
    </div>
    <?php endif; ?>

    <!-- POIN 1: Parameter Waktu & Siklus Operasi -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center font-black text-xs">
            01
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              1. Parameter Waktu & Siklus Operasi
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Jadwal briefing, tanggal pelaksanaan, dan estimasi waktu pergerakan tim lapangan</p>
          </div>
        </div>
        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
          SIKLUS HARIAN
        </span>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Hari Operasi Ke <span class="text-red-500">*</span>
          </label>
          <input type="text" name="hari_ke" id="renops_hari_ke" required
            placeholder="Contoh: H.1, H.2"
            value="<?= e($hariKe) ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Tanggal Pelaksanaan <span class="text-red-500">*</span>
          </label>
          <input type="date" name="tanggal_laporan" id="renops_tanggal_laporan" required
            value="<?= e($tanggalLaporan) ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-medium focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Waktu Briefing (WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="waktu_briefing" id="renops_waktu_briefing" required
            value="<?= e($waktuBriefing) ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-medium focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
            Waktu Mulai Pergerakan (WITA) <span class="text-red-500">*</span>
          </label>
          <input type="datetime-local" name="waktu_mulai_ops" id="renops_waktu_mulai_ops" required
            value="<?= e($waktuMulaiOps) ?>"
            class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs font-medium focus:outline-none focus:ring-2 focus:ring-orange-500" />
        </div>
      </div>

      <!-- Cuaca BMKG -->
      <div>
        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
          Prakiraan Cuaca Lapangan (BMKG)
        </label>
        <input type="text" name="cuaca" id="renops_cuaca"
          placeholder="Contoh: Cerah Berawan, Angin Barat Daya 5-15 Knots, Gelombang 0.5 - 1.25 M"
          value="<?= e($cuaca) ?>"
          class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800/80 text-slate-900 dark:text-white text-xs focus:outline-none focus:ring-2 focus:ring-orange-500" />
        <div class="flex flex-wrap items-center gap-1.5 mt-2">
          <span class="text-[11px] font-semibold text-slate-400 mr-1">Preset Cepat Cuaca:</span>
          <?php foreach (['Cerah Berawan', 'Berawan', 'Hujan Ringan', 'Hujan Sedang / Berangin'] as $cw): ?>
            <button type="button" onclick="document.getElementById('renops_cuaca').value='<?= $cw ?>'" class="text-[10px] px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-orange-500 border border-slate-200 dark:border-slate-700 cursor-pointer">
              <?= $cw ?>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- POIN 2: Pembagian Sektor & Search and Rescue Unit (SRU) -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-xs">
            02
          </div>
          <div>
            <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
              2. Pembagian Sektor & Unit SRU (Search and Rescue Unit)
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pembagian tugas satuan lapangan, sektor koordinat pencarian, luas area, dan pola penyisiran</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" onclick="openGisImportModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-white shadow-xs transition cursor-pointer" title="Ambil Search Area, Pola Pencarian, Jalur dan Titik Taktis dari Peta Plotting">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            <span>Ambil Data dari Peta Plotting</span>
            <span id="gis-bundle-badge" class="px-1.5 py-0.5 rounded-full bg-black/20 text-[10px] font-mono font-bold"><?= $totalGisItems ?></span>
          </button>
          <button type="button" id="btn-quick-refresh-gis" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 shadow-2xs transition cursor-pointer" title="Refresh / Sinkronkan data plotting peta terbaru">
            <svg id="icon-quick-refresh-gis" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <span class="hidden sm:inline">Refresh Data</span>
          </button>
          <button type="button" onclick="addSruRow()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white shadow-xs transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            <span>+ Tambah SRU</span>
          </button>
        </div>
      </div>

      <div id="sru-container" class="space-y-3">
        <!-- SRU Rows will be populated here -->
      </div>
    </div>

    <!-- POIN 3 & 4: Unsur SAR Terlibat & Alut/Palsar (Interactive Reorderable Editors) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Unsur SAR Terlibat -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center font-black text-xs">
              03
            </div>
            <div>
              <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                3. Unsur SAR Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data atau input manual, dan atur prioritas urutan</p>
            </div>
          </div>
          <span id="renops-total-personil" class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400">
            Total: 0 Org
          </span>
        </div>

        <!-- Master Data Selector -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-2">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
              Pilih Master Tim:
            </label>
            <select id="select-master-tim" onchange="pickMasterTim(this.value)" class="flex-1 px-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">-- Cari / Pilih dari Master Data Tim Basarnas --</option>
              <?php foreach ($masterTim as $mt): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_tim' => $mt['id_tim'], 'nama_tim' => $mt['nama_tim']]), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($mt['nama_tim']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Input Bar (Manual Add) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-8">
            <input type="text" id="unsur-input-nama" placeholder="Ketik nama unsur (contoh: Tim Rescue Kansar, TNI AL)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div class="sm:col-span-3 flex items-center gap-1.5">
            <input type="number" id="unsur-input-jml" min="1" max="999" value="1" placeholder="Qty" class="w-full px-2 py-2 text-xs text-center font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 shrink-0">Org</span>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddUnsurClick()" class="w-full h-full py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Unsur ke Daftar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Reorderable List Container -->
        <div id="unsur-items-container" class="space-y-2 min-h-[60px]">
          <!-- Injected via JavaScript -->
        </div>

        <!-- Hidden inputs for backend synchronization -->
        <input type="hidden" name="detail_unsur_json" id="detail_unsur_json" value="">
        <textarea name="unsur_text" id="unsur_text" class="hidden"><?= e($renops['unsur_text'] ?? '') ?></textarea>
      </div>

      <!-- Alut & Palsar Terlibat -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-xs">
              04
            </div>
            <div>
              <h3 class="text-sm font-bold uppercase tracking-wider text-slate-900 dark:text-white">
                4. Alut & Palsar Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data atau input manual, dan atur prioritas urutan</p>
            </div>
          </div>
          <span id="renops-total-alut" class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            Total: 0 Alut
          </span>
        </div>

        <!-- Master Data Selector -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-2">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <label class="text-xs font-bold text-slate-700 dark:text-slate-300 shrink-0">
              Pilih Master Alut:
            </label>
            <select id="select-master-alut" onchange="pickMasterAlut(this.value)" class="flex-1 px-3 py-1.5 text-xs rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="">-- Cari / Pilih dari Master Data Alut Basarnas --</option>
              <?php foreach ($masterAlut as $ma): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_alut' => $ma['id_alut'], 'nama_alut' => $ma['nama_alut'], 'satuan' => $ma['satuan'] ?? 'Unit', 'kategori' => $ma['kategori'] ?? 'ALUT']), ENT_QUOTES, 'UTF-8') ?>">
                  [<?= htmlspecialchars($ma['kategori'] ?? 'ALUT') ?>] <?= htmlspecialchars($ma['nama_alut']) ?> (<?= htmlspecialchars($ma['satuan'] ?? 'Unit') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <!-- Input Bar (Manual Add) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-7">
            <input type="text" id="alut-input-nama" placeholder="Ketik nama alut / palsar (contoh: Rescue Car, RIB 01)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div class="sm:col-span-2">
            <input type="number" id="alut-input-jml" min="1" max="999" value="1" placeholder="Qty" class="w-full px-2 py-2 text-xs text-center font-bold rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
          </div>
          <div class="sm:col-span-2">
            <select id="alut-input-satuan" class="w-full px-2 py-2 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-orange-500">
              <option value="Unit">Unit</option>
              <option value="Set">Set</option>
              <option value="Buah">Buah</option>
              <option value="Paket">Paket</option>
              <option value="Batang">Batang</option>
              <option value="Orang">Orang</option>
            </select>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddAlutClick()" class="w-full h-full py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Alut ke Daftar">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </button>
          </div>
        </div>

        <!-- Reorderable List Container -->
        <div id="alut-items-container" class="space-y-2 min-h-[60px]">
          <!-- Injected via JavaScript -->
        </div>

        <!-- Hidden inputs for backend synchronization -->
        <input type="hidden" name="detail_alut_json" id="detail_alut_json" value="">
        <textarea name="alut_text" id="alut_text" class="hidden"><?= e($renops['alut_text'] ?? '') ?></textarea>
      </div>

    </div>

    <!-- Bottom Action Bar (Simpan / Batal) -->
    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-center justify-between gap-4 transition-colors">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <span>Status Siklus:</span>
        <span class="font-bold font-mono text-orange-600 dark:text-orange-400"><?= e($hariKe) ?> Terintegrasi</span>
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>&tab=renops" class="px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 font-bold text-xs transition">
          Batal
        </a>
        <button type="submit" class="px-6 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-lg shadow-orange-600/30 hover:scale-[1.02] active:scale-[0.98] transition cursor-pointer flex items-center gap-2">
          <span>💾</span>
          <span><?= $editMode ? 'Simpan Perubahan RENOPS' : 'Terbitkan Rencana Operasi (RENOPS)' ?></span>
        </button>
      </div>
    </div>

  </form>
</div>

<!-- Modal Pratinjau WhatsApp RENOPS -->
<div id="modal-preview-renops-wa" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs hidden">
  <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
    <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pratinjau Format WhatsApp RENOPS</h3>
      </div>
      <button type="button" onclick="closeModal('modal-preview-renops-wa')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-5 overflow-y-auto flex-1 font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap select-all leading-relaxed bg-slate-50 dark:bg-slate-950" id="renops-wa-preview-content"></div>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-between">
      <span class="text-xs text-slate-400">Siap dikirim ke grup komando</span>
      <div class="flex items-center gap-2">
        <button type="button" onclick="copyRenopsWa()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition cursor-pointer flex items-center gap-1.5">
          <span>📋</span>
          <span>Salin Teks WA</span>
        </button>
        <button type="button" onclick="closeModal('modal-preview-renops-wa')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Ambil Data Plotting ke RENOPS -->
<div id="modal-import-gis-renops" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs hidden">
  <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 w-full max-w-4xl overflow-hidden shadow-2xl flex flex-col max-h-[92vh]">
    <!-- Header -->
    <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/70 dark:bg-slate-950/70">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-lg">
          🗺️
        </div>
        <div>
          <h3 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <span>Ambil Data Taktis dari Peta Plotting</span>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
              Operasi & Base Map
            </span>
          </h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">
            Pilih Search Area, Pola Pencarian SAR, Jalur, atau Titik Taktis untuk dimasukkan langsung ke lembar RENOPS.
          </p>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" id="btn-refresh-gis-bundle" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30 transition cursor-pointer shadow-2xs" title="Muat ulang data plotting dari peta">
          <svg id="icon-refresh-gis" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 transition-transform duration-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          <span id="label-refresh-gis">Refresh Data</span>
        </button>
        <button type="button" onclick="closeModal('modal-import-gis-renops')" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-xl transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Quick Action Banner -->
    <div class="px-5 py-3 bg-gradient-to-r from-amber-500/10 via-orange-500/10 to-blue-500/10 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
      <div class="text-xs text-slate-700 dark:text-slate-300">
        <span class="font-bold text-amber-600 dark:text-amber-400">⚡ Aksi Cepat Otomatis:</span>
        <span class="text-slate-500 dark:text-slate-400">Generate seluruh sektor Search Area menjadi unit-unit SRU harian sekaligus.</span>
      </div>
      <button type="button" onclick="importAllPolygonsToSrus()" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-600 hover:bg-amber-500 text-white shadow-xs transition cursor-pointer shrink-0">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span>Generate Semua Area ke SRU</span>
      </button>
    </div>

    <!-- Tabs Navigation -->
    <div class="px-5 pt-3 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2 overflow-x-auto bg-white dark:bg-slate-900">
      <button type="button" onclick="switchGisImportTab('polygons')" id="btn-tab-polygons" class="tab-gis-btn px-3 py-2 text-xs font-bold rounded-t-xl border-b-2 border-orange-500 text-orange-600 dark:text-orange-400 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>⬡ Search Area (Poligon)</span>
        <span id="tab-cnt-polygons" class="px-1.5 py-0.2 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-mono"><?= count($gisBundle['polygons'] ?? []) ?></span>
      </button>
      <button type="button" onclick="switchGisImportTab('patterns')" id="btn-tab-patterns" class="tab-gis-btn px-3 py-2 text-xs font-bold rounded-t-xl border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>⚡ Pola Pencarian SAR</span>
        <span id="tab-cnt-patterns" class="px-1.5 py-0.2 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-mono"><?= count($gisBundle['searchPatterns'] ?? []) ?></span>
      </button>
      <button type="button" onclick="switchGisImportTab('routes')" id="btn-tab-routes" class="tab-gis-btn px-3 py-2 text-xs font-bold rounded-t-xl border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>↗️ Jalur / Rute</span>
        <span id="tab-cnt-routes" class="px-1.5 py-0.2 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-mono"><?= count($gisBundle['routes'] ?? []) ?></span>
      </button>
      <button type="button" onclick="switchGisImportTab('points')" id="btn-tab-points" class="tab-gis-btn px-3 py-2 text-xs font-bold rounded-t-xl border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>📍 Titik Taktis & LKP</span>
        <span id="tab-cnt-points" class="px-1.5 py-0.2 rounded-full bg-slate-100 dark:bg-slate-800 text-[10px] font-mono"><?= count($gisBundle['points'] ?? []) ?></span>
      </button>
    </div>

    <!-- Tab Content Containers -->
    <div class="p-5 overflow-y-auto flex-1 space-y-3 bg-slate-50/60 dark:bg-slate-950/40" id="gis-import-content-container">
      <!-- Populated via JavaScript -->
    </div>

    <!-- Modal Footer -->
    <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="text-[11px] text-slate-400 flex flex-wrap items-center gap-2 sm:gap-3">
        <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= $idOperasi ?>&include_base=1" target="_blank" class="text-blue-500 hover:underline flex items-center gap-1 font-semibold">
          <span>Buka Peta Plotting di Tab Baru ↗</span>
        </a>
        <span class="text-slate-300 dark:text-slate-700 hidden sm:inline">&bull;</span>
        <button type="button" onclick="refreshGisBundle(true)" class="text-xs text-amber-600 dark:text-amber-400 hover:underline flex items-center gap-1 cursor-pointer font-medium">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span>Sinkronkan Data Peta Sekarang</span>
        </button>
        <span id="gis-last-sync-time" class="text-[10px] text-slate-400 dark:text-slate-500"></span>
      </div>
      <button type="button" onclick="closeModal('modal-import-gis-renops')" class="w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 text-center">
        Tutup
      </button>
    </div>
  </div>
</div>

<script>
window.__RENOPS_PAGE_CONFIG__ = <?= jsonSafe([
    'idOperasi' => $idOperasi,
    'editMode' => $editMode,
    'initialUnsurData' => $initialUnsurData,
    'initialAlutData' => $initialAlutData,
    'initialSrus' => $initialSrus,
    'gisBundle' => $gisBundle,
    'namaOperasi' => $operasi['nama_operasi'] ?? '',
    'koordinat' => $operasi['koordinat'] ?: '-',
    'operasiStartDate' => date('Y-m-d', strtotime(TemplateHelper::getWaktuMulaiAwal($operasi))),
    'operasiReopenDate' => !empty($operasi['waktu_reopen']) ? date('Y-m-d', strtotime((string)$operasi['waktu_reopen'])) : null,
    'operasiBaseHariReopen' => (int)TemplateHelper::getBaseHariSebelumReopen($operasi),
]) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/pages/renops_form.js?v=<?= APP_VERSION ?>"></script>
