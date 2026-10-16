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
// Data state
let currentUnsurList = <?= jsonSafe($initialUnsurData) ?>;
let currentAlutList = <?= jsonSafe($initialAlutData) ?>;
let currentSrus = <?= jsonSafe($initialSrus) ?>;

// Escape HTML utility
function escapeHtml(str) {
  if (!str) return '';
  return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ── SRU Sector Manager ──
function renderSruList() {
  const container = document.getElementById('sru-container');
  if (!container) return;
  container.innerHTML = '';

  if (currentSrus.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada pembagian unit SRU. Klik tombol "+ Tambah SRU" di atas untuk menambahkan sektor pencarian.
      </div>
    `;
    return;
  }

  currentSrus.forEach((s, idx) => {
    // Auto-detect and sync area, pattern, and sweep from GIS plotting if missing or legacy placeholder
    if (!s.luas_area || s.luas_area === '10 Nm²' || s.luas_area.trim() === '') {
      const matchedPoly = (SAR_GIS_BUNDLE.polygons || []).find((p, pIdx) => {
        if (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase())) return true;
        if (idx === pIdx && (SAR_GIS_BUNDLE.polygons || []).length === currentSrus.length) return true;
        return false;
      });
      if (matchedPoly) {
        const aNum = calculatePolygonAreaNm2(matchedPoly);
        if (aNum > 0) {
          s.luas_area = `${aNum.toFixed(2)} Nm²`;
        }
      }
    }

    if (!s.total_sapuan) {
      const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find((p, pIdx) => {
        if (s.nama_sru && p.polygonName && s.nama_sru.toLowerCase().includes(p.polygonName.toLowerCase())) return true;
        if (s.nama_sru && p.name && s.nama_sru.toLowerCase().includes(p.name.toLowerCase())) return true;
        if (idx === pIdx && (SAR_GIS_BUNDLE.searchPatterns || []).length === currentSrus.length) return true;
        return false;
      });
      if (matchedPattern) {
        const isEsar = (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search');
        const legs = matchedPattern.legsCount || (matchedPattern.waypoints ? Math.max(1, Math.floor(matchedPattern.waypoints.length / 2)) : 6);
        const spacing = matchedPattern.trackSpacingNm ? Number(matchedPattern.trackSpacingNm).toFixed(2) : '1.00';
        const dist = matchedPattern.totalDistanceNm ? Number(matchedPattern.totalDistanceNm).toFixed(1) : '-';
        s.total_sapuan = isEsar
          ? `${legs} Personel E-SAR (Spasi: ${spacing} Nm, Total: ${dist} Nm)`
          : `${legs} Sapuan (Spasi: ${spacing} Nm, Total: ${dist} Nm)`;
        if (matchedPattern.patternType === 'creeping_line') s.pola_pencarian = 'Creeping Line Search';
        else if (matchedPattern.patternType === 'expanding_square') s.pola_pencarian = 'Expanding Square Search';
        else if (matchedPattern.patternType === 'e_sar' || matchedPattern.patternType === 'sector_search') s.pola_pencarian = 'Pola Pencarian E-SAR';
        else if (matchedPattern.patternType === 'track_line') s.pola_pencarian = 'Track Line Search';
      }
    }

    const card = document.createElement('div');
    card.className = 'p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-3';

    const luasVal = (s.luas_area && s.luas_area.trim() !== '' && s.luas_area !== '10 Nm²') ? s.luas_area : (s.luas_area || '');
    const polaVal = s.pola_pencarian || 'Parallel Track Search';
    const sapuanVal = s.total_sapuan || '';

    card.innerHTML = `
      <div class="flex items-center justify-between pb-2 border-b border-slate-200/80 dark:border-slate-800">
        <div class="flex flex-wrap items-center gap-2">
          <span class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-blue-500/15 text-blue-600 dark:text-blue-400">
            SRU ${idx + 1}
          </span>
          <input type="text" name="srus[${idx}][nama_sru]" value="${escapeHtml(s.nama_sru || '')}" placeholder="Nama SRU (contoh: SRU 1 Kapal RIB)" required class="text-xs font-bold px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].nama_sru = this.value">
          <button type="button" onclick="openGisImportModal(${idx})" class="inline-flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400 hover:text-amber-500 bg-amber-500/10 hover:bg-amber-500/20 px-2.5 py-1 rounded-lg border border-amber-500/25 transition cursor-pointer shadow-2xs" title="Ambil data sektor atau pola dari plotting untuk SRU ini">
            <span>🗺️</span>
            <span>Ambil Data Plotting</span>
          </button>
        </div>
        <button type="button" onclick="removeSruRow(${idx})" class="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition cursor-pointer" title="Hapus SRU">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Ringkasan Data Otomatis Taktis (Luas Area, Pola Pencarian & Total Sapuan) -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 p-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-xs">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 text-sm">
            📐
          </div>
          <div class="min-w-0">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Luas Area Pencarian</span>
            <span class="font-mono font-bold text-xs ${luasVal ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 italic'}">
              ${luasVal ? `✓ ${escapeHtml(luasVal)}` : 'Belum Terdeteksi (Klik Ambil Data)'}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-orange-500/15 text-orange-600 dark:text-orange-400 flex items-center justify-center shrink-0 text-sm">
            ⚡
          </div>
          <div class="min-w-0">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Pola Pencarian</span>
            <span class="font-bold text-xs text-orange-600 dark:text-orange-400 truncate block">
              ✓ ${escapeHtml(polaVal)}
            </span>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 text-sm">
            🔁
          </div>
          <div class="min-w-0">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Sapuan / Lintasan</span>
            <span class="font-mono font-semibold text-[11px] ${sapuanVal ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400 italic'} truncate block" title="${escapeHtml(sapuanVal)}">
              ${sapuanVal ? `✓ ${escapeHtml(sapuanVal)}` : 'Otomatis dari Pola SAR'}
            </span>
          </div>
        </div>
      </div>

      <!-- Hidden inputs for automated values -->
      <input type="hidden" name="srus[${idx}][id_sru]" value="${s.id_sru || ''}">
      <input type="hidden" name="srus[${idx}][luas_area]" value="${escapeHtml(s.luas_area || '')}">
      <input type="hidden" name="srus[${idx}][pola_pencarian]" value="${escapeHtml(s.pola_pencarian || 'Parallel Track Search')}">
      <input type="hidden" name="srus[${idx}][total_sapuan]" value="${escapeHtml(s.total_sapuan || '')}">
      <input type="hidden" name="srus[${idx}][track_spacing]" value="${escapeHtml(s.track_spacing || '')}">

      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider">
            Koordinat Batas Sektor & Rincian Tugas
          </label>
          <span class="text-[10px] text-slate-400 font-mono">Format Standar Basarnas DMS</span>
        </div>
        <textarea name="srus[${idx}][koordinat_area]" rows="3" placeholder="Koordinat batas sektor / tugas penyisiran (Otomatis diambil dari peta plotting)..." class="w-full px-3 py-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs font-mono leading-relaxed focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].koordinat_area = this.value">${escapeHtml(s.koordinat_area || '')}</textarea>
      </div>
    `;
    container.appendChild(card);
  });
}

function addSruRow() {
  const num = currentSrus.length + 1;
  currentSrus.push({
    nama_sru: `SRU ${num}`,
    koordinat_area: '',
    luas_area: '',
    pola_pencarian: 'Parallel Track Search',
    total_sapuan: ''
  });
  renderSruList();
}

function removeSruRow(idx) {
  currentSrus.splice(idx, 1);
  renderSruList();
}

// ── Unsur SAR Terlibat Editor ──
function renderUnsurList() {
  const container = document.getElementById('unsur-items-container');
  if (!container) return;
  container.innerHTML = '';

  let totalPersonil = 0;

  if (currentUnsurList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada unsur SAR yang ditambahkan. Gunakan dropdown Master Data atau input manual di atas.
      </div>
    `;
  } else {
    currentUnsurList.forEach((item, idx) => {
      const jml = parseInt(item.jumlah_personil, 10) || 1;
      totalPersonil += jml;

      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-purple-500/15 text-purple-600 dark:text-purple-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_unsur || '')}" onchange="updateUnsurItem(${idx}, 'nama_unsur', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <div class="flex items-center gap-1">
            <input type="number" min="1" max="999" value="${jml}" onchange="updateUnsurItem(${idx}, 'jumlah_personil', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-purple-600 dark:text-purple-400 focus:outline-none focus:border-orange-500" />
            <span class="text-xs font-medium text-slate-400">Org</span>
          </div>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="moveUnsurRow(${idx}, -1)" ${idx === 0 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Atas (Prioritas Naik)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </button>
            <button type="button" onclick="moveUnsurRow(${idx}, 1)" ${idx === currentUnsurList.length - 1 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Bawah (Prioritas Turun)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <button type="button" onclick="removeUnsurRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Unsur">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
      container.appendChild(row);
    });
  }

  const badgeEl = document.getElementById('renops-total-personil');
  if (badgeEl) badgeEl.textContent = `Total: ${totalPersonil} Org`;

  syncUnsurFields();
}

function pickMasterTim(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_tim) {
      document.getElementById('unsur-input-nama').value = item.nama_tim;
      document.getElementById('unsur-input-jml').value = '3';
      document.getElementById('unsur-input-nama').focus();
    }
  } catch (e) {}
}

function handleAddUnsurClick() {
  const namaInput = document.getElementById('unsur-input-nama');
  const jmlInput = document.getElementById('unsur-input-jml');
  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentUnsurList.push({
    nama_unsur: nama,
    jumlah_personil: jml
  });

  namaInput.value = '';
  jmlInput.value = '1';
  document.getElementById('select-master-tim').value = '';
  renderUnsurList();
}

function moveUnsurRow(idx, direction) {
  const target = idx + direction;
  if (target < 0 || target >= currentUnsurList.length) return;
  const temp = currentUnsurList[idx];
  currentUnsurList[idx] = currentUnsurList[target];
  currentUnsurList[target] = temp;
  renderUnsurList();
}

function removeUnsurRow(idx) {
  currentUnsurList.splice(idx, 1);
  renderUnsurList();
}

function updateUnsurItem(idx, key, val) {
  if (currentUnsurList[idx]) {
    currentUnsurList[idx][key] = val;
    syncUnsurFields();
  }
}

function syncUnsurFields() {
  const jsonEl = document.getElementById('detail_unsur_json');
  const textEl = document.getElementById('unsur_text');
  if (jsonEl) jsonEl.value = JSON.stringify(currentUnsurList);
  if (textEl) {
    textEl.value = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Org`).join('\n');
  }
}

// ── Alut & Palsar Terlibat Editor ──
function renderAlutList() {
  const container = document.getElementById('alut-items-container');
  if (!container) return;
  container.innerHTML = '';

  let totalAlut = 0;

  if (currentAlutList.length === 0) {
    container.innerHTML = `
      <div class="p-4 text-center rounded-xl border border-dashed border-slate-300 dark:border-slate-800 text-xs text-slate-400">
        Belum ada armada alut / palsar yang ditambahkan. Gunakan dropdown Master Data atau input manual di atas.
      </div>
    `;
  } else {
    currentAlutList.forEach((item, idx) => {
      const jml = parseInt(item.jumlah_alut, 10) || 1;
      totalAlut += jml;

      const row = document.createElement('div');
      row.className = 'p-2.5 rounded-xl border flex items-center justify-between gap-2.5 bg-slate-50 dark:bg-slate-950/70 border-slate-200 dark:border-slate-800 hover:border-orange-500/40 transition-colors';
      row.innerHTML = `
        <div class="flex items-center gap-2 flex-1 min-w-0">
          <span class="w-5 h-5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold text-[11px] flex items-center justify-center shrink-0">
            ${idx + 1}
          </span>
          <input type="text" value="${escapeHtml(item.nama_alut || '')}" onchange="updateAlutItem(${idx}, 'nama_alut', this.value)" class="flex-1 text-xs font-semibold px-2 py-1 rounded bg-transparent border border-transparent hover:border-slate-300 dark:hover:border-slate-700 focus:border-orange-500 focus:outline-none text-slate-900 dark:text-white" />
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <input type="number" min="1" max="999" value="${jml}" onchange="updateAlutItem(${idx}, 'jumlah_alut', parseInt(this.value, 10) || 1)" class="w-12 px-1.5 py-1 text-xs font-bold text-center rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-emerald-600 dark:text-emerald-400 focus:outline-none focus:border-orange-500" />
          <select onchange="updateAlutItem(${idx}, 'satuan', this.value)" class="px-1.5 py-1 text-xs rounded border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 focus:outline-none">
            <option value="Unit" ${item.satuan === 'Unit' ? 'selected' : ''}>Unit</option>
            <option value="Set" ${item.satuan === 'Set' ? 'selected' : ''}>Set</option>
            <option value="Buah" ${item.satuan === 'Buah' ? 'selected' : ''}>Buah</option>
            <option value="Paket" ${item.satuan === 'Paket' ? 'selected' : ''}>Paket</option>
            <option value="Batang" ${item.satuan === 'Batang' ? 'selected' : ''}>Batang</option>
            <option value="Orang" ${item.satuan === 'Orang' ? 'selected' : ''}>Orang</option>
          </select>
          <div class="flex items-center gap-0.5 border-l border-slate-200 dark:border-slate-800 pl-2">
            <button type="button" onclick="moveAlutRow(${idx}, -1)" ${idx === 0 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Atas (Prioritas Naik)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
            </button>
            <button type="button" onclick="moveAlutRow(${idx}, 1)" ${idx === currentAlutList.length - 1 ? 'disabled' : ''} class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-white disabled:opacity-30 cursor-pointer" title="Geser ke Bawah (Prioritas Turun)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <button type="button" onclick="removeAlutRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus Alut">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
      `;
      container.appendChild(row);
    });
  }

  const badgeEl = document.getElementById('renops-total-alut');
  if (badgeEl) badgeEl.textContent = `Total: ${totalAlut} Alut`;

  syncAlutFields();
}

function pickMasterAlut(jsonStr) {
  if (!jsonStr) return;
  try {
    const item = JSON.parse(jsonStr);
    if (item && item.nama_alut) {
      document.getElementById('alut-input-nama').value = item.nama_alut;
      document.getElementById('alut-input-jml').value = '1';
      if (item.satuan) {
        document.getElementById('alut-input-satuan').value = item.satuan;
      }
      document.getElementById('alut-input-nama').focus();
    }
  } catch (e) {}
}

function handleAddAlutClick() {
  const namaInput = document.getElementById('alut-input-nama');
  const jmlInput = document.getElementById('alut-input-jml');
  const satInput = document.getElementById('alut-input-satuan');

  const nama = namaInput.value.trim();
  const jml = parseInt(jmlInput.value, 10) || 1;
  const sat = satInput.value.trim() || 'Unit';

  if (!nama) {
    namaInput.focus();
    return;
  }

  currentAlutList.push({
    nama_alut: nama,
    jumlah_alut: jml,
    satuan: sat
  });

  namaInput.value = '';
  jmlInput.value = '1';
  document.getElementById('select-master-alut').value = '';
  renderAlutList();
}

function moveAlutRow(idx, direction) {
  const target = idx + direction;
  if (target < 0 || target >= currentAlutList.length) return;
  const temp = currentAlutList[idx];
  currentAlutList[idx] = currentAlutList[target];
  currentAlutList[target] = temp;
  renderAlutList();
}

function removeAlutRow(idx) {
  currentAlutList.splice(idx, 1);
  renderAlutList();
}

function updateAlutItem(idx, key, val) {
  if (currentAlutList[idx]) {
    currentAlutList[idx][key] = val;
    syncAlutFields();
  }
}

function syncAlutFields() {
  const jsonEl = document.getElementById('detail_alut_json');
  const textEl = document.getElementById('alut_text');
  if (jsonEl) jsonEl.value = JSON.stringify(currentAlutList);
  if (textEl) {
    textEl.value = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n');
  }
}

// ── WhatsApp Preview Modal ──
function cleanSruCoordinates(koor) {
  if (!koor) return '';
  return koor
    .split('\n')
    .filter(line => {
      const trim = line.trim();
      return !/^(pola\s*pencarian\s*:|total\s*sapuan\s*:|\[pola\s*sar|tipe\s*:)/i.test(trim);
    })
    .join('\n')
    .trim();
}

function previewRenopsWa() {
  const hariKe = document.getElementById('renops_hari_ke')?.value || 'H.1';
  const tgl = document.getElementById('renops_tanggal_laporan')?.value || '';
  const briefing = document.getElementById('renops_waktu_briefing')?.value || '';
  const mulai = document.getElementById('renops_waktu_mulai_ops')?.value || '';
  const cuaca = document.getElementById('renops_cuaca')?.value || 'Cerah Berawan';

  const tglStr = window.formatTanggalIndo ? window.formatTanggalIndo(tgl) : tgl;
  const namaOps = <?= jsonSafe($operasi['nama_operasi']) ?>;

  function formatTw(dtStr) {
    if (!dtStr) return 'TW 0000 0000 H';
    const d = new Date(dtStr);
    if (isNaN(d.getTime())) return 'TW 0000 0000 H';
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hh = String(d.getHours()).padStart(2, '0');
    const min = String(d.getMinutes()).padStart(2, '0');
    return `TW ${mm}${dd} ${hh}${min} H`;
  }

  let sruList = [];
  if (currentSrus.length === 0) {
    sruList.push(`*SRU 1 Tim Rescue Kansar Kendari* melakukan penyisiran dgn koordinat :\nA: <?= addslashes($operasi['koordinat'] ?: '-') ?>\nLuas area pencarian : 10 Nm²\nPola pencarian : Parallel Track Search`);
  } else {
    currentSrus.forEach((s) => {
      const rawName = (s.nama_sru || 'SRU').replace(/^\*+|\*+$/g, '').trim();
      const cleanName = rawName || 'SRU 1';
      const nameFormatted = `*${cleanName}* melakukan penyisiran dgn koordinat :`;

      const koor = cleanSruCoordinates(s.koordinat_area) || '<?= addslashes($operasi['koordinat'] ?: '-') ?>';
      const pola = s.pola_pencarian || 'Parallel Track Search';

      const lines = [nameFormatted, koor];
      if (s.luas_area && s.luas_area.trim()) {
        const luasVal = s.luas_area.trim();
        if (!/^luas\s*area/i.test(luasVal)) {
          lines.push(`Luas area pencarian : ${luasVal}`);
        } else {
          lines.push(luasVal);
        }
      }
      lines.push(`Pola pencarian : ${pola}`);
      if (s.total_sapuan && s.total_sapuan.trim()) {
        const sapuanVal = s.total_sapuan.trim();
        if (!/^total\s*sapuan\s*:/i.test(sapuanVal)) {
          lines.push(`Total sapuan : ${sapuanVal}`);
        } else {
          lines.push(sapuanVal);
        }
      }
      sruList.push(lines.join('\n'));
    });
  }
  const sruOutput = sruList.join('\n\n');
  const sruCount = currentSrus.length || 1;

  const unsurText = currentUnsurList.map(u => `• ${u.nama_unsur} : ${u.jumlah_personil || 1} Orang`).join('\n') || '• Tim Rescue Kansar Kendari : 7 Orang';
  const alutText = currentAlutList.map(a => `• ${a.jumlah_alut} ${a.satuan || 'Unit'} ${a.nama_alut}`).join('\n') || '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat';

  const header = `FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
RENCANA OPS SAR ${hariKe.toUpperCase()}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
${tglStr}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan Rencana Ops ${namaOps}.
__________________________________________________`;

  const footer = `Demikian kami laporkan, mohon petunjuk dan arahan

Ttd

KKS Kendari
===========================
🏢 Kantor Pencarian Dan Pertolongan Kendari
📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116
☎️ Telp. : (0401) 3196557
📠 Fax. : (0401) 3196558
📱 WhatsApp : +62811-4034-115
🚨 Emergency Call : 115
🌐 Website : Www.Kendari.Basarnas.go.id
📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id
📲 Medsos : @Basarnaskendari`;

  const preview = `${header}

Rencana Ops ${hariKe.toUpperCase()} :

• PD ${formatTw(briefing)} Tim SAR Gabungan Melaksanakan Brifing Operasi.
• Pada ${formatTw(mulai)} Tim SAR Gabungan Melanjutkan Operasi Pencarian ${hariKe.toUpperCase()} Sesuai Rencana Operasi ${hariKe.toUpperCase()} dengan Tim SAR gabungan menuju ke entri point masing-masing.
Dengan Tim dibagi ${sruCount} SRU dengan area pencarian sbb :

${sruOutput}

1. Unsur SAR Terlibat :
${unsurText}

2. Alut dan Palsar :
${alutText}

3. Kondisi Cuaca :
• Cuaca: ${cuaca}
(Sumber BMKG)

${footer}`;

  document.getElementById('renops-wa-preview-content').textContent = preview;
  openModal('modal-preview-renops-wa');
}

function copyRenopsWa() {
  const content = document.getElementById('renops-wa-preview-content')?.textContent || '';
  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(content).then(() => {
      if (typeof showToast === 'function') showToast('Format WA RENOPS berhasil disalin!', 'success');
    }).catch(() => fallbackCopyRenopsText(content));
  } else {
    fallbackCopyRenopsText(content);
  }
}

function fallbackCopyRenopsText(text) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.left = '-9999px';
  document.body.appendChild(ta);
  ta.focus();
  ta.select();
  try {
    document.execCommand('copy');
    if (typeof showToast === 'function') showToast('Format WA RENOPS berhasil disalin!', 'success');
  } catch (err) {
    if (typeof showToast === 'function') showToast('Gagal menyalin teks secara otomatis', 'error');
  }
  document.body.removeChild(ta);
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('hidden');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
}

// ── GIS Plotting Import into RENOPS ──
let SAR_GIS_BUNDLE = <?= jsonSafe($gisBundle) ?>;
let targetedSruIdxForImport = null;
let activeGisImportTab = 'polygons';
let isRefreshingGis = false;

async function refreshGisBundle(showToastAlert = false) {
  if (isRefreshingGis) return;
  isRefreshingGis = true;

  const iconHeader = document.getElementById('icon-refresh-gis');
  const iconQuick = document.getElementById('icon-quick-refresh-gis');
  const labelHeader = document.getElementById('label-refresh-gis');
  const btnHeader = document.getElementById('btn-refresh-gis-bundle');
  const btnQuick = document.getElementById('btn-quick-refresh-gis');

  if (iconHeader) iconHeader.classList.add('animate-spin');
  if (iconQuick) iconQuick.classList.add('animate-spin');
  if (labelHeader) labelHeader.textContent = 'Memuat...';
  if (btnHeader) btnHeader.disabled = true;
  if (btnQuick) btnQuick.disabled = true;

  try {
    const res = await fetch(`${BASE_URL}/api/gis/bundle?id_operasi=<?= (int)$idOperasi ?>&include_base_map=1&_t=${Date.now()}`);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();

    if (data.success) {
      const newBundle = data.data || {
        id_operasi: <?= (int)$idOperasi ?>,
        include_base_map: true,
        points: data.points || [],
        polygons: data.polygons || [],
        routes: data.routes || [],
        searchPatterns: data.searchPatterns || data.search_patterns || []
      };

      if (!newBundle.points) newBundle.points = data.points || [];
      if (!newBundle.polygons) newBundle.polygons = data.polygons || [];
      if (!newBundle.routes) newBundle.routes = data.routes || [];
      if (!newBundle.searchPatterns) newBundle.searchPatterns = data.searchPatterns || data.search_patterns || [];

      SAR_GIS_BUNDLE = newBundle;

      // Update badge counts
      const cntPoly = (SAR_GIS_BUNDLE.polygons || []).length;
      const cntPat = (SAR_GIS_BUNDLE.searchPatterns || []).length;
      const cntRte = (SAR_GIS_BUNDLE.routes || []).length;
      const cntPts = (SAR_GIS_BUNDLE.points || []).length;
      const totalAll = cntPoly + cntPat + cntRte + cntPts;

      const badgeMain = document.getElementById('gis-bundle-badge');
      if (badgeMain) badgeMain.textContent = totalAll;

      const badgePoly = document.getElementById('tab-cnt-polygons');
      if (badgePoly) badgePoly.textContent = cntPoly;

      const badgePat = document.getElementById('tab-cnt-patterns');
      if (badgePat) badgePat.textContent = cntPat;

      const badgeRte = document.getElementById('tab-cnt-routes');
      if (badgeRte) badgeRte.textContent = cntRte;

      const badgePts = document.getElementById('tab-cnt-points');
      if (badgePts) badgePts.textContent = cntPts;

      const syncTimeEl = document.getElementById('gis-last-sync-time');
      if (syncTimeEl) {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        syncTimeEl.textContent = `(Sinkron: ${timeStr})`;
      }

      // Re-render modal content
      renderGisImportModalContent();

      if (showToastAlert && typeof showToast === 'function') {
        showToast(`Data plotting peta berhasil diperbarui! (${totalAll} data taktis ditemukan)`, 'success');
      }
    } else {
      throw new Error(data.message || data.error || 'Gagal memuat data plotting');
    }
  } catch (err) {
    console.error('Error refreshing GIS bundle:', err);
    if (showToastAlert && typeof showToast === 'function') {
      showToast('Gagal memuat pembaruan data plotting: ' + err.message, 'error');
    }
  } finally {
    isRefreshingGis = false;
    if (iconHeader) iconHeader.classList.remove('animate-spin');
    if (iconQuick) iconQuick.classList.remove('animate-spin');
    if (labelHeader) labelHeader.textContent = 'Refresh Data';
    if (btnHeader) btnHeader.disabled = false;
    if (btnQuick) btnQuick.disabled = false;
  }
}

function formatDmsCoordPart(val, isLat) {
  const abs = Math.abs(val);
  let deg = Math.floor(abs);
  const minFloat = (abs - deg) * 60;
  let min = Math.floor(minFloat);
  let sec = Number(((minFloat - min) * 60).toFixed(1));
  if (sec >= 60.0) {
    sec = 0.0;
    min += 1;
    if (min >= 60) {
      min = 0;
      deg += 1;
    }
  }
  const dir = isLat ? (val >= 0 ? 'U' : 'S') : (val >= 0 ? 'T' : 'B');
  const degPad = isLat ? String(deg).padStart(2, '0') : String(deg).padStart(3, '0');
  const secStr = sec.toFixed(1).padStart(4, '0');
  return `${degPad}° ${String(min).padStart(2, '0')}' ${secStr}" ${dir}`;
}

function formatDmsPair(lat, lng) {
  return `${formatDmsCoordPart(lat, true)} - ${formatDmsCoordPart(lng, false)}`;
}

function calculatePolygonAreaNm2(poly) {
  if (!poly) return 0;
  if (poly.areaNm2 && Number(poly.areaNm2) > 0) {
    return Number(poly.areaNm2);
  }
  if (poly.shapeType === 'circle' && poly.radiusNm) {
    return Math.round(Math.PI * Math.pow(Number(poly.radiusNm), 2) * 100) / 100;
  }
  if (poly.shapeType === 'box' && poly.boxWidthNm && poly.boxHeightNm) {
    return Math.round(Number(poly.boxWidthNm) * Number(poly.boxHeightNm) * 100) / 100;
  }
  const pts = poly.points || [];
  if (!Array.isArray(pts) || pts.length < 3) return 0;

  let sumLat = 0;
  pts.forEach(p => {
    const lat = p.lat !== undefined ? p.lat : (Array.isArray(p) ? p[0] : 0);
    sumLat += Number(lat || 0);
  });
  const meanLatRad = (sumLat / pts.length) * (Math.PI / 180);
  const cosMeanLat = Math.cos(meanLatRad);

  let areaSum = 0;
  const n = pts.length;
  for (let i = 0; i < n; i++) {
    const j = (i + 1) % n;
    const pt1 = pts[i];
    const pt2 = pts[j];
    const lng1 = pt1.lng !== undefined ? pt1.lng : (Array.isArray(pt1) ? pt1[1] : 0);
    const lat1 = pt1.lat !== undefined ? pt1.lat : (Array.isArray(pt1) ? pt1[0] : 0);
    const lng2 = pt2.lng !== undefined ? pt2.lng : (Array.isArray(pt2) ? pt2[1] : 0);
    const lat2 = pt2.lat !== undefined ? pt2.lat : (Array.isArray(pt2) ? pt2[0] : 0);

    const x1 = Number(lng1) * 60.0 * cosMeanLat;
    const y1 = Number(lat1) * 60.0;
    const x2 = Number(lng2) * 60.0 * cosMeanLat;
    const y2 = Number(lat2) * 60.0;
    areaSum += (x1 * y2) - (x2 * y1);
  }
  const area = Math.abs(areaSum) / 2.0;
  return Math.round(area * 100) / 100;
}

function getPolygonCoordsText(poly) {
  const pts = poly.points || [];
  if (!Array.isArray(pts) || pts.length === 0) return '';
  return pts.map((pt, idx) => {
    const lat = pt.lat !== undefined ? pt.lat : (Array.isArray(pt) ? pt[0] : 0);
    const lng = pt.lng !== undefined ? pt.lng : (Array.isArray(pt) ? pt[1] : 0);
    const lbl = pt.label || `Titik ${String.fromCharCode(65 + idx)}`;
    return `${lbl}: ${formatDmsPair(lat, lng)}`;
  }).join('\n');
}

function copyGisDmsText(encodedText, label) {
  const text = decodeURIComponent(encodedText);
  if (navigator.clipboard) {
    navigator.clipboard.writeText(text).then(() => {
      if (typeof showToast === 'function') showToast(`Koordinat ${label} berhasil disalin!`, 'success');
    });
  } else {
    const ta = document.createElement('textarea');
    ta.value = text;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    if (typeof showToast === 'function') showToast(`Koordinat ${label} berhasil disalin!`, 'success');
  }
}

function openGisImportModal(targetSruIdx = null) {
  targetedSruIdxForImport = (typeof targetSruIdx === 'number' && targetSruIdx >= 0) ? targetSruIdx : null;
  renderGisImportModalContent();
  openModal('modal-import-gis-renops');
  refreshGisBundle(false);
}

function switchGisImportTab(tabName) {
  activeGisImportTab = tabName;
  ['polygons', 'patterns', 'routes', 'points'].forEach(t => {
    const btn = document.getElementById(`btn-tab-${t}`);
    if (btn) {
      if (t === tabName) {
        btn.classList.add('border-orange-500', 'text-orange-600', 'dark:text-orange-400');
        btn.classList.remove('border-transparent', 'text-slate-500');
      } else {
        btn.classList.remove('border-orange-500', 'text-orange-600', 'dark:text-orange-400');
        btn.classList.add('border-transparent', 'text-slate-500');
      }
    }
  });
  renderGisImportModalContent();
}

function getSruOptionsHtml(selectedIdx = null) {
  if (currentSrus.length === 0) {
    return `<option value="-1">+ Jadikan SRU 1 Baru</option>`;
  }
  return currentSrus.map((s, idx) => {
    const isSel = (selectedIdx === idx) || (targetedSruIdxForImport === idx && selectedIdx === null);
    const label = s.nama_sru ? `SRU ${idx + 1} (${escapeHtml(s.nama_sru)})` : `SRU ${idx + 1}`;
    return `<option value="${idx}" ${isSel ? 'selected' : ''}>${label}</option>`;
  }).join('') + `<option value="-1">+ Tambah Sebagai SRU Baru</option>`;
}

function renderGisImportModalContent() {
  const container = document.getElementById('gis-import-content-container');
  if (!container) return;

  if (activeGisImportTab === 'polygons') {
    const polys = SAR_GIS_BUNDLE.polygons || [];
    if (polys.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">⬡</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Search Area (Poligon) tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk menggambar poligon batas pencarian operasi atau base map.</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }
    container.innerHTML = polys.map(p => {
      const isBaseMap = !p.id_operasi;
      const areaNum = calculatePolygonAreaNm2(p);
      const area = areaNum > 0 ? areaNum.toFixed(2) : '-';
      const pts = p.points || [];
      const coordsPreview = getPolygonCoordsText(p);
      const selId = `sel-poly-target-${p.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${p.strokeColor || '#2563eb'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(p.name || 'Sektor Pencarian')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map / Wilayah Kerja' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Luas: <span class="text-blue-600 dark:text-blue-400">${area} Nm²</span> &bull; ${pts.length} Titik Sudut
            </div>
          </div>
          <div class="text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 whitespace-pre-line max-h-24 overflow-y-auto leading-relaxed select-all">
${coordsPreview || '(Koordinat sudut tidak tersedia)'}
          </div>
          <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <button type="button" onclick="copyGisDmsText(\`${encodeURIComponent(coordsPreview)}\`, '${escapeHtml(p.name)}')" class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 transition cursor-pointer">
              📋 Salin Koordinat
            </button>
            <div class="flex items-center gap-2">
              <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                ${getSruOptionsHtml()}
              </select>
              <button type="button" onclick="handleImportPolyClick('${p.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition cursor-pointer">
                Terapkan ke SRU
              </button>
            </div>
          </div>
        </div>
      `;
    }).join('');
  } else if (activeGisImportTab === 'patterns') {
    const patterns = SAR_GIS_BUNDLE.searchPatterns || [];
    if (patterns.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">⚡</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Pola Pencarian SAR tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk men-generate Pola Pencarian (Parallel, Expanding Square, E-SAR, dll).</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }
    container.innerHTML = patterns.map(p => {
      const isBaseMap = !p.id_operasi;
      const typeLabels = {
        'creeping_line': 'Creeping Line Search',
        'parallel_track': 'Parallel Track Search',
        'expanding_square': 'Expanding Square Search',
        'e_sar': 'Pola Pencarian E-SAR',
        'sector_search': 'Pola Pencarian E-SAR',
        'track_line': 'Track Line Search'
      };
      const typeLabel = typeLabels[p.patternType] || p.patternType || 'Parallel Track Search';
      const selId = `sel-pattern-target-${p.id}`;
      const waypoints = p.waypoints || [];
      const dist = p.totalDistanceNm ? Number(p.totalDistanceNm).toFixed(1) : '-';
      const spacing = p.trackSpacingNm ? Number(p.trackSpacingNm).toFixed(2) : '-';
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${p.color || '#ea580c'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(p.name || 'Pola Lintasan SAR')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                ${escapeHtml(typeLabel)}
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Spacing: <span class="text-orange-600 dark:text-orange-400">${spacing} Nm</span> &bull; Jarak: <span>${dist} Nm</span>
            </div>
          </div>
          <div class="text-[11px] text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
            <span>Sektor Asal: <strong>${escapeHtml(p.polygonName || '-')}</strong></span>
            <span>Jumlah Waypoint: <strong>${waypoints.length} titik</strong></span>
            <span>Lintasan/Sapu: <strong>${p.legsCount || '-'}</strong></span>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
            <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
              ${getSruOptionsHtml()}
            </select>
            <button type="button" onclick="handleImportPatternClick('${p.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-orange-600 hover:bg-orange-500 text-white shadow-xs transition cursor-pointer">
              Terapkan Pola ke SRU
            </button>
          </div>
        </div>
      `;
    }).join('');
  } else if (activeGisImportTab === 'routes') {
    const routes = SAR_GIS_BUNDLE.routes || [];
    if (routes.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">↗️</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Jalur / Rute tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk menambahkan rute laut/darat.</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }
    container.innerHTML = routes.map(r => {
      const isBaseMap = !r.id_operasi;
      const dist = r.totalDistanceNm ? Number(r.totalDistanceNm).toFixed(1) : '-';
      const speed = r.vesselSpeedKnots ? Number(r.vesselSpeedKnots).toFixed(0) : '25';
      const pts = r.points || [];
      const selId = `sel-route-target-${r.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="w-3.5 h-3.5 rounded-full" style="background-color: ${r.strokeColor || '#059669'};"></span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(r.name || 'Jalur Operasi')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              Panjang: <span class="text-emerald-600 dark:text-emerald-400">${dist} Nm</span> &bull; Kecepatan: <span>${speed} Knots</span>
            </div>
          </div>
          <div class="text-[11px] font-mono text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <span>Jumlah Waypoint: <strong>${pts.length} titik koordinat</strong></span>
          </div>
          <div class="flex flex-wrap items-center justify-end gap-2 pt-1">
            <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
              ${getSruOptionsHtml()}
            </select>
            <button type="button" onclick="handleImportRouteClick('${r.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white shadow-xs transition cursor-pointer">
              Sisipkan ke SRU
            </button>
          </div>
        </div>
      `;
    }).join('');
  } else if (activeGisImportTab === 'points') {
    const points = SAR_GIS_BUNDLE.points || [];
    if (points.length === 0) {
      container.innerHTML = `
        <div class="p-8 text-center text-slate-400 text-xs rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 space-y-3">
          <div class="text-3xl">📍</div>
          <div class="font-bold text-slate-700 dark:text-slate-200">Belum ada Titik Taktis tersimpan</div>
          <p class="text-slate-500">Buka <a href="${BASE_URL}/gis.php?id_operasi=${SAR_GIS_BUNDLE.id_operasi}&include_base=1" target="_blank" class="text-blue-500 underline font-semibold">Peta Plotting</a> untuk menambahkan titik LKP, Posko, Helipad, atau temuan.</p>
          <div class="pt-1 flex justify-center">
            <button type="button" onclick="refreshGisBundle(true)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span>Refresh / Cek Data Baru</span>
            </button>
          </div>
        </div>`;
      return;
    }
    container.innerHTML = points.map(pt => {
      const isBaseMap = !pt.id_operasi;
      const dms = formatDmsPair(Number(pt.lat), Number(pt.lng));
      const selId = `sel-pt-target-${pt.id}`;
      return `
        <div class="p-4 rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-xs space-y-3">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-100 dark:border-slate-800">
            <div class="flex items-center gap-2">
              <span class="text-base">📍</span>
              <span class="font-bold text-xs sm:text-sm text-slate-900 dark:text-white">${escapeHtml(pt.label || 'Titik Taktis')}</span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20">
                ${escapeHtml(pt.category || 'Taktis')}
              </span>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${isBaseMap ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20'}">
                ${isBaseMap ? 'Base Map' : 'Operasi Ini'}
              </span>
            </div>
            <div class="text-xs font-mono font-bold text-slate-600 dark:text-slate-300">
              ${dms}
            </div>
          </div>
          ${pt.description ? `<p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">${escapeHtml(pt.description)}</p>` : ''}
          <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <button type="button" onclick="copyGisDmsText(\`${encodeURIComponent(dms)}\`, '${escapeHtml(pt.label)}')" class="px-2.5 py-1 text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 transition cursor-pointer">
              📋 Salin DMS
            </button>
            <div class="flex items-center gap-2">
              <select id="${selId}" class="px-2.5 py-1 text-xs rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white">
                ${getSruOptionsHtml()}
              </select>
              <button type="button" onclick="handleImportPointClick('${pt.id}', '${selId}')" class="px-3.5 py-1 text-xs font-bold rounded-lg bg-purple-600 hover:bg-purple-500 text-white shadow-xs transition cursor-pointer">
                Sisipkan ke SRU
              </button>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }
}

function handleImportPolyClick(polyId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  if (targetIdx === -1) {
    importPolygonToNewSru(polyId);
  } else {
    importPolygonToSru(polyId, targetIdx);
  }
}

function handleImportPatternClick(patId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  if (targetIdx === -1) {
    importPatternToNewSru(patId);
  } else {
    importPatternToSru(patId, targetIdx);
  }
}

function handleImportRouteClick(routeId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  importRouteToSru(routeId, targetIdx);
}

function handleImportPointClick(ptId, selectId) {
  const sel = document.getElementById(selectId);
  const targetIdx = sel ? parseInt(sel.value, 10) : -1;
  importPointToSru(ptId, targetIdx);
}

function getPatternDetails(matchedPattern) {
  if (!matchedPattern) return { pola: 'Parallel Track Search', totalSapuan: '', spacing: '', detailText: '' };
  
  let pola = 'Parallel Track Search';
  const pt = matchedPattern.patternType;
  if (pt === 'creeping_line') pola = 'Creeping Line Search';
  else if (pt === 'expanding_square') pola = 'Expanding Square Search';
  else if (pt === 'e_sar' || pt === 'sector_search') pola = 'Pola Pencarian E-SAR';
  else if (pt === 'track_line') pola = 'Track Line Search';
  else pola = 'Parallel Track Search';

  const isEsar = (pt === 'e_sar' || pt === 'sector_search');
  const legs = matchedPattern.legsCount || (matchedPattern.waypoints ? Math.max(1, Math.floor(matchedPattern.waypoints.length / 2)) : 6);
  const spacing = matchedPattern.trackSpacingNm ? Number(matchedPattern.trackSpacingNm).toFixed(2) : '1.00';
  const dist = matchedPattern.totalDistanceNm ? Number(matchedPattern.totalDistanceNm).toFixed(1) : '-';

  const totalSapuan = isEsar
    ? `${legs} Personel E-SAR (Spasi: ${spacing} Nm, Total: ${dist} Nm)`
    : `${legs} Sapuan (Spasi: ${spacing} Nm, Total: ${dist} Nm)`;

  const detailText = '';

  return { pola, totalSapuan, spacing, detailText };
}

function importAllPolygonsToSrus() {
  const polys = SAR_GIS_BUNDLE.polygons || [];
  if (polys.length === 0) {
    if (typeof showToast === 'function') showToast('Tidak ada Search Area (Poligon) yang tersimpan di peta plotting.', 'info');
    return;
  }
  
  if (currentSrus.length > 0 && !confirm(`Ditemukan ${polys.length} Search Area di peta plotting. Apakah Anda ingin mengonversi seluruh Search Area ini menjadi unit SRU RENOPS?`)) {
    return;
  }
  
  currentSrus = polys.map((poly, idx) => {
    const sruNum = idx + 1;
    const name = poly.name || `Sektor ${String.fromCharCode(65 + idx)}`;
    const areaNum = calculatePolygonAreaNm2(poly);
    const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (poly.areaNm2 ? `${Number(poly.areaNm2).toFixed(2)} Nm²` : '');
    const coordsText = getPolygonCoordsText(poly);
    
    const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => p.polygonId === poly.id || (p.polygonName && poly.name && p.polygonName === poly.name));
    const patInfo = getPatternDetails(matchedPattern);

    const taskText = `Sektor: ${name}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '');

    return {
      nama_sru: `SRU ${sruNum} - ${name}`,
      koordinat_area: taskText,
      luas_area: area,
      pola_pencarian: patInfo.pola,
      total_sapuan: patInfo.totalSapuan
    };
  });
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Berhasil men-generate ${currentSrus.length} unit SRU dari Search Area plotting!`, 'success');
  }
}

function importPolygonToSru(polyId, targetIdx) {
  const poly = (SAR_GIS_BUNDLE.polygons || []).find(p => String(p.id) === String(polyId));
  if (!poly) return;
  if (!currentSrus[targetIdx]) {
    currentSrus[targetIdx] = { nama_sru: `SRU ${targetIdx + 1}`, koordinat_area: '', luas_area: '', pola_pencarian: 'Parallel Track Search', total_sapuan: '' };
  }
  
  const name = poly.name || 'Sektor Pencarian';
  const areaNum = calculatePolygonAreaNm2(poly);
  const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (poly.areaNm2 ? `${Number(poly.areaNm2).toFixed(2)} Nm²` : '');
  const coordsText = getPolygonCoordsText(poly);
  
  const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => p.polygonId === poly.id || (p.polygonName && poly.name && p.polygonName === poly.name));
  const patInfo = getPatternDetails(matchedPattern);

  currentSrus[targetIdx].nama_sru = `SRU ${targetIdx + 1} - ${name}`;
  currentSrus[targetIdx].luas_area = area;
  currentSrus[targetIdx].pola_pencarian = patInfo.pola;
  currentSrus[targetIdx].total_sapuan = patInfo.totalSapuan;
  currentSrus[targetIdx].koordinat_area = `Sektor: ${name}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '');
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Data sektor "${name}" berhasil diterapkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

function importPolygonToNewSru(polyId) {
  const poly = (SAR_GIS_BUNDLE.polygons || []).find(p => String(p.id) === String(polyId));
  if (!poly) return;
  const num = currentSrus.length + 1;
  const name = poly.name || `Sektor ${String.fromCharCode(64 + num)}`;
  const areaNum = calculatePolygonAreaNm2(poly);
  const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (poly.areaNm2 ? `${Number(poly.areaNm2).toFixed(2)} Nm²` : '');
  const coordsText = getPolygonCoordsText(poly);
  
  const matchedPattern = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => p.polygonId === poly.id || (p.polygonName && poly.name && p.polygonName === poly.name));
  const patInfo = getPatternDetails(matchedPattern);

  currentSrus.push({
    nama_sru: `SRU ${num} - ${name}`,
    koordinat_area: `Sektor: ${name}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : ''),
    luas_area: area,
    pola_pencarian: patInfo.pola,
    total_sapuan: patInfo.totalSapuan
  });
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`SRU baru "${name}" berhasil ditambahkan!`, 'success');
  }
}

function importPatternToSru(patId, targetIdx) {
  const pat = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => String(p.id) === String(patId));
  if (!pat) return;
  if (!currentSrus[targetIdx]) {
    currentSrus[targetIdx] = { nama_sru: `SRU ${targetIdx + 1}`, koordinat_area: '', luas_area: '', pola_pencarian: 'Parallel Track Search', total_sapuan: '' };
  }
  
  const patInfo = getPatternDetails(pat);
  currentSrus[targetIdx].pola_pencarian = patInfo.pola;
  currentSrus[targetIdx].total_sapuan = patInfo.totalSapuan;
  currentSrus[targetIdx].track_spacing = patInfo.spacing;

  const linkedPoly = (SAR_GIS_BUNDLE.polygons || []).find(p => (pat.polygonId && p.id === pat.polygonId) || (pat.polygonName && p.name === pat.polygonName));
  if (linkedPoly) {
    const areaNum = calculatePolygonAreaNm2(linkedPoly);
    const area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (linkedPoly.areaNm2 ? `${Number(linkedPoly.areaNm2).toFixed(2)} Nm²` : '');
    if (area && !currentSrus[targetIdx].luas_area) {
      currentSrus[targetIdx].luas_area = area;
    }
    const coordsText = getPolygonCoordsText(linkedPoly);
    if (!currentSrus[targetIdx].koordinat_area) {
      currentSrus[targetIdx].koordinat_area = `Sektor: ${linkedPoly.name || 'Sektor Pencarian'}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '');
    }
  }
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Pola pencarian "${pat.name}" berhasil diterapkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

function importPatternToNewSru(patId) {
  const pat = (SAR_GIS_BUNDLE.searchPatterns || []).find(p => String(p.id) === String(patId));
  if (!pat) return;
  const num = currentSrus.length + 1;
  const patInfo = getPatternDetails(pat);

  let area = '';
  let coordsText = '';
  const linkedPoly = (SAR_GIS_BUNDLE.polygons || []).find(p => (pat.polygonId && p.id === pat.polygonId) || (pat.polygonName && p.name === pat.polygonName));
  if (linkedPoly) {
    const areaNum = calculatePolygonAreaNm2(linkedPoly);
    area = areaNum > 0 ? `${areaNum.toFixed(2)} Nm²` : (linkedPoly.areaNm2 ? `${Number(linkedPoly.areaNm2).toFixed(2)} Nm²` : '');
    coordsText = getPolygonCoordsText(linkedPoly);
  }

  let taskText = linkedPoly
    ? `Sektor: ${linkedPoly.name || 'Sektor Pencarian'}${area ? ` (${area})` : ''}` + (coordsText ? `\nBatas Koordinat:\n${coordsText}` : '')
    : `Penyisiran pencarian SAR menggunakan pola ${pat.name}`;

  currentSrus.push({
    nama_sru: `SRU ${num} (${patInfo.pola})`,
    koordinat_area: taskText,
    luas_area: area,
    pola_pencarian: patInfo.pola,
    total_sapuan: patInfo.totalSapuan,
    track_spacing: patInfo.spacing
  });
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`SRU baru dengan pola "${pat.name}" berhasil ditambahkan!`, 'success');
  }
}

function importRouteToSru(routeId, targetIdx) {
  const r = (SAR_GIS_BUNDLE.routes || []).find(item => String(item.id) === String(routeId));
  if (!r) return;
  
  const dist = r.totalDistanceNm ? Number(r.totalDistanceNm).toFixed(1) : '-';
  const speed = r.vesselSpeedKnots ? Number(r.vesselSpeedKnots).toFixed(0) : '25';
  const totalSapuan = `1 Lintasan Track Line (Panjang: ${dist} Nm, Kecepatan: ${speed} Knots)`;
  const pts = r.points || [];
  let routeCoordsText = '';
  if (Array.isArray(pts) && pts.length > 0) {
    routeCoordsText = pts.map((pt, idx) => {
      const lbl = pt.label || `Waypoint ${idx + 1}`;
      return `${lbl}: ${formatDmsPair(Number(pt.lat), Number(pt.lng))}`;
    }).join('\n');
  }

  const routeDesc = `Jalur/Rute: ${r.name}\nPanjang Lintasan: ${dist} Nm | Kecepatan Alut: ${speed} Knots` + (routeCoordsText ? `\nKoordinat Lintasan:\n${routeCoordsText}` : '');

  if (targetIdx === -1 || !currentSrus[targetIdx]) {
    const num = currentSrus.length + 1;
    currentSrus.push({
      nama_sru: `SRU ${num} - Jalur ${r.name}`,
      koordinat_area: routeDesc,
      luas_area: `${dist} Nm (Koridor)`,
      pola_pencarian: 'Track Line Search',
      total_sapuan: totalSapuan
    });
    targetIdx = currentSrus.length - 1;
  } else {
    currentSrus[targetIdx].pola_pencarian = 'Track Line Search';
    currentSrus[targetIdx].total_sapuan = totalSapuan;
    if (!currentSrus[targetIdx].luas_area) currentSrus[targetIdx].luas_area = `${dist} Nm (Koridor)`;
    currentSrus[targetIdx].koordinat_area = (currentSrus[targetIdx].koordinat_area || '') + '\n\n' + routeDesc;
  }
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Jalur "${r.name}" berhasil disisipkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

function importPointToSru(ptId, targetIdx) {
  const pt = (SAR_GIS_BUNDLE.points || []).find(item => String(item.id) === String(ptId));
  if (!pt) return;
  const dms = formatDmsPair(Number(pt.lat), Number(pt.lng));
  const ptDesc = `[Titik ${pt.category || 'Taktis'}: ${pt.label}]\nKoordinat: ${dms}${pt.description ? '\nKeterangan: ' + pt.description : ''}`;
  
  if (targetIdx === -1 || !currentSrus[targetIdx]) {
    const num = currentSrus.length + 1;
    currentSrus.push({
      nama_sru: `SRU ${num} - ${pt.label}`,
      koordinat_area: ptDesc.trim(),
      luas_area: '',
      pola_pencarian: 'Parallel Track Search',
      total_sapuan: ''
    });
    targetIdx = currentSrus.length - 1;
  } else {
    currentSrus[targetIdx].koordinat_area = (currentSrus[targetIdx].koordinat_area || '') + '\n\n' + ptDesc.trim();
  }
  
  renderSruList();
  closeModal('modal-import-gis-renops');
  if (typeof showToast === 'function') {
    showToast(`Titik "${pt.label}" berhasil disisipkan ke SRU ${targetIdx + 1}!`, 'success');
  }
}

// --- AUTO-SAVE DRAFT (LOCAL STORAGE RESILIENCE) ---
const RENOPS_DRAFT_KEY = 'qr_sar_renops_draft_<?= $idOperasi ?>';
const isRenopsEditMode = <?= $editMode ? 'true' : 'false' ?>;

function saveRenopsDraft() {
  if (isRenopsEditMode) return;
  const draft = {
    tanggal: document.getElementById('input-tanggal')?.value,
    waktu_briefing: document.getElementById('input-waktu-briefing')?.value,
    waktu_mulai: document.getElementById('input-waktu-mulai')?.value,
    cuaca: document.getElementById('input-cuaca')?.value,
    komunikasi: document.getElementById('input-komunikasi')?.value,
    catatan: document.getElementById('input-catatan')?.value,
    srus: currentSrus,
    unsur: currentUnsurList,
    alut: currentAlutList,
    savedAt: new Date().toISOString()
  };
  localStorage.setItem(RENOPS_DRAFT_KEY, JSON.stringify(draft));
}

function restoreRenopsDraft() {
  if (isRenopsEditMode) return;
  const raw = localStorage.getItem(RENOPS_DRAFT_KEY);
  if (!raw) return;
  try {
    const d = JSON.parse(raw);
    if (!d) return;
    if (d.cuaca) document.getElementById('input-cuaca').value = d.cuaca;
    if (d.komunikasi) document.getElementById('input-komunikasi').value = d.komunikasi;
    if (d.catatan) document.getElementById('input-catatan').value = d.catatan;
    if (Array.isArray(d.srus) && d.srus.length > 0) { currentSrus = d.srus; renderSruList(); }
    if (Array.isArray(d.unsur) && d.unsur.length > 0) { currentUnsurList = d.unsur; renderUnsurList(); }
    if (Array.isArray(d.alut) && d.alut.length > 0) { currentAlutList = d.alut; renderAlutList(); }
    
    if (typeof showToast === 'function') {
      showToast('Draf pengisian lokal berhasil dipulihkan otomatis.', 'info');
    }
  } catch (e) {}
}

function clearRenopsDraft() {
  localStorage.removeItem(RENOPS_DRAFT_KEY);
}

let renopsDraftTimeout = null;
document.addEventListener('input', (e) => {
  if (isRenopsEditMode) return;
  clearTimeout(renopsDraftTimeout);
  renopsDraftTimeout = setTimeout(saveRenopsDraft, 1000);
});

// Real-time Day Recalculation & Schedule Alignment Based on Input Date
const OPERASI_START_DATE = <?= jsonSafe(date('Y-m-d', strtotime(TemplateHelper::getWaktuMulaiAwal($operasi)))) ?>;
const OPERASI_REOPEN_DATE = <?= jsonSafe(!empty($operasi['waktu_reopen']) ? date('Y-m-d', strtotime((string)$operasi['waktu_reopen'])) : null) ?>;
const OPERASI_BASE_HARI_REOPEN = <?= (int)TemplateHelper::getBaseHariSebelumReopen($operasi) ?>;

function recalculateRenopsHariKe() {
  const tglInput = document.getElementById('renops_tanggal_laporan')?.value;
  if (!tglInput || !OPERASI_START_DATE) return;
  const t2 = new Date(tglInput + 'T00:00:00');
  if (isNaN(t2.getTime())) return;

  const inputHari = document.getElementById('renops_hari_ke');
  if (!inputHari) return;

  if (OPERASI_REOPEN_DATE) {
    const tReopen = new Date(OPERASI_REOPEN_DATE + 'T00:00:00');
    if (!isNaN(tReopen.getTime()) && t2 >= tReopen) {
      const extDay = Math.max(1, Math.floor((t2 - tReopen) / 86400000) + 1);
      inputHari.value = 'H' + OPERASI_BASE_HARI_REOPEN + ' + ' + extDay;
      return;
    }
  }

  const t1 = new Date(OPERASI_START_DATE + 'T00:00:00');
  if (isNaN(t1.getTime())) return;
  const diffDays = Math.floor((t2 - t1) / 86400000);
  const dayNum = Math.max(1, diffDays + 1);
  inputHari.value = 'H.' + dayNum;
}

function syncRenopsScheduleDates() {
  const tglInput = document.getElementById('renops_tanggal_laporan')?.value;
  if (!tglInput) return;

  const briefingEl = document.getElementById('renops_waktu_briefing');
  if (briefingEl && briefingEl.value) {
    const timePart = briefingEl.value.split('T')[1] || '07:00';
    briefingEl.value = `${tglInput}T${timePart}`;
  }
  const mulaiEl = document.getElementById('renops_waktu_mulai_ops');
  if (mulaiEl && mulaiEl.value) {
    const timePart = mulaiEl.value.split('T')[1] || '07:30';
    mulaiEl.value = `${tglInput}T${timePart}`;
  }
}

document.getElementById('renops_tanggal_laporan')?.addEventListener('input', () => {
  recalculateRenopsHariKe();
  syncRenopsScheduleDates();
});
document.getElementById('renops_tanggal_laporan')?.addEventListener('change', () => {
  recalculateRenopsHariKe();
  syncRenopsScheduleDates();
});

// Ensure all dynamic JSON fields are synced and draft is cleared on submission
document.getElementById('form-renops')?.addEventListener('submit', function() {
  recalculateRenopsHariKe();
  syncUnsurFields();
  syncAlutFields();
  clearRenopsDraft();
});

document.addEventListener('DOMContentLoaded', () => {
  recalculateRenopsHariKe();
  renderSruList();
  renderUnsurList();
  renderAlutList();
  restoreRenopsDraft();
  recalculateRenopsHariKe();
});

// Cross-tab auto-sync when plotting data is saved/updated/deleted in GIS map tab
window.addEventListener('storage', (e) => {
  if (e.key === 'qr_sar_gis_last_update') {
    refreshGisBundle(false);
  }
});

// Auto-sync when returning to this tab if import modal is active
window.addEventListener('focus', () => {
  const modal = document.getElementById('modal-import-gis-renops');
  if (modal && !modal.classList.contains('hidden')) {
    refreshGisBundle(false);
  }
});
</script>
