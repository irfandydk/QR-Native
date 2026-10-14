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

    <?php if (!empty($operasi['waktu_reopen'])): ?>
    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 flex items-center gap-3">
      <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
      <div class="text-xs">
        <p class="font-bold">Operasi SAR Diperpanjang / Dibuka Kembali</p>
        <p class="text-[11px] opacity-90">Operasi dibuka kembali pada <strong><?= TemplateHelper::formatTanggalIndo($operasi['waktu_reopen']) ?> (<?= TemplateHelper::formatJamWITA($operasi['waktu_reopen']) ?> WITA)</strong>. Perhitungan hari operasional (H.1, H.2, dst.) dimulai lagi sejak tanggal pembukaan ini.</p>
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
        <button type="button" onclick="addSruRow()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-500 text-white shadow-xs transition cursor-pointer">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          <span>+ Tambah SRU</span>
        </button>
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

<script>
// Data state
let currentUnsurList = <?= json_encode($initialUnsurData) ?>;
let currentAlutList = <?= json_encode($initialAlutData) ?>;
let currentSrus = <?= json_encode($initialSrus) ?>;

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
    const card = document.createElement('div');
    card.className = 'p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-3';
    card.innerHTML = `
      <div class="flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="px-2 py-0.5 rounded font-mono font-bold text-xs bg-blue-500/15 text-blue-600 dark:text-blue-400">
            SRU ${idx + 1}
          </span>
          <input type="text" name="srus[${idx}][nama_sru]" value="${escapeHtml(s.nama_sru || '')}" placeholder="Nama SRU (contoh: SRU 1 Kapal RIB)" required class="text-xs font-bold px-2 py-1 rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].nama_sru = this.value">
        </div>
        <button type="button" onclick="removeSruRow(${idx})" class="p-1 rounded text-slate-400 hover:text-red-500 cursor-pointer" title="Hapus SRU">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 text-xs">
        <div class="sm:col-span-6">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Koordinat Sektor & Tugas</label>
          <textarea name="srus[${idx}][koordinat_area]" rows="2" placeholder="Koordinat batas sektor / tugas penyisiran..." class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs leading-relaxed focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].koordinat_area = this.value">${escapeHtml(s.koordinat_area || '')}</textarea>
        </div>
        <div class="sm:col-span-3">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Luas Area</label>
          <input type="text" name="srus[${idx}][luas_area]" value="${escapeHtml(s.luas_area || '')}" placeholder="Contoh: 10 Nm²" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].luas_area = this.value">
        </div>
        <div class="sm:col-span-3">
          <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Pola Pencarian</label>
          <select name="srus[${idx}][pola_pencarian]" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-xs focus:outline-none focus:border-blue-500" onchange="currentSrus[${idx}].pola_pencarian = this.value">
            <option value="Parallel Track Search" ${s.pola_pencarian === 'Parallel Track Search' ? 'selected' : ''}>Parallel Track</option>
            <option value="Expanding Square Search" ${s.pola_pencarian === 'Expanding Square Search' ? 'selected' : ''}>Expanding Square</option>
            <option value="Sector Search" ${s.pola_pencarian === 'Sector Search' ? 'selected' : ''}>Sector Search</option>
            <option value="Creeping Line Search" ${s.pola_pencarian === 'Creeping Line Search' ? 'selected' : ''}>Creeping Line</option>
            <option value="Track Line Search" ${s.pola_pencarian === 'Track Line Search' ? 'selected' : ''}>Track Line</option>
          </select>
        </div>
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
    luas_area: '10 Nm²',
    pola_pencarian: 'Parallel Track Search'
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
function previewRenopsWa() {
  const hariKe = document.getElementById('renops_hari_ke')?.value || 'H.1';
  const tgl = document.getElementById('renops_tanggal_laporan')?.value || '';
  const briefing = document.getElementById('renops_waktu_briefing')?.value || '';
  const mulai = document.getElementById('renops_waktu_mulai_ops')?.value || '';
  const cuaca = document.getElementById('renops_cuaca')?.value || 'Cerah Berawan';

  const tglStr = window.formatTanggalIndo ? window.formatTanggalIndo(tgl) : tgl;
  const namaOps = <?= json_encode($operasi['nama_operasi']) ?>;
  const korbanList = <?= json_encode($operasi['korban'] ?? []) ?>;

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
    sruList.push(`*SRU 1 Tim Rescue Kansar Kendari melakukan penyisiran dgn koordinat\nA: <?= addslashes($operasi['koordinat'] ?: '-') ?>\nLuas area pencarian 10 Nm²\nPola pencarian : Parallel Track Search`);
  } else {
    currentSrus.forEach((s) => {
      const name = s.nama_sru ? (s.nama_sru.startsWith('*') ? s.nama_sru : `*${s.nama_sru}`) : '*SRU 1';
      const koor = s.koordinat_area || '<?= addslashes($operasi['koordinat'] ?: '-') ?>';
      const luas = s.luas_area || '-';
      const pola = s.pola_pencarian || 'Parallel Track Search';
      sruList.push(`${name} melakukan penyisiran dgn koordinat\n${koor}\nLuas area pencarian ${luas}\nPola pencarian : ${pola}`);
    });
  }
  const sruOutput = sruList.join('\n\n');
  const sruCount = currentSrus.length || 1;

  let sCount = 0, mdCount = 0, dpCount = 0, hCount = 0;
  const kList = [];
  korbanList.forEach((k, idx) => {
    const st = (k.status_korban || 'DP').toUpperCase();
    if (st === 'S') sCount++;
    else if (st === 'MD') mdCount++;
    else if (st === 'DP') dpCount++;
    else if (st === 'H') hCount++;
    else dpCount++;
    const label = st === 'DP' ? '' : ` (${st === 'S' ? 'Selamat (S)' : (st === 'MD' ? 'Meninggal Dunia (MD)' : 'Hilang / Nihil (H)')})`;
    kList.push(`${idx + 1}. Nama : ${k.nama_korban || '-'}${label}\n   Umur : ${k.umur || '-'}\n   Jenis Kelamin : ${k.jk || '-'}\n   Alamat : ${k.alamat || '-'}`);
  });
  const identitasText = kList.length > 0 ? kList.join('\n\n') : '1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -';

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

1. Data Korban: ${korbanList.length} Orang
• S : ${sCount} ORANG
• MD : ${mdCount} ORANG
• DP : ${dpCount} ORANG
• H : ${hCount} ORANG

2. Identitas Korban
${identitasText}

3. Unsur SAR Terlibat
${unsurText}

4. Alut dan Palsar
${alutText}

5. Kondisi Cuaca :
• Cuaca: ${cuaca}
(Sumber BMKG)

${footer}`;

  document.getElementById('renops-wa-preview-content').textContent = preview;
  openModal('modal-preview-renops-wa');
}

function copyRenopsWa() {
  const content = document.getElementById('renops-wa-preview-content')?.textContent || '';
  if (navigator.clipboard) {
    navigator.clipboard.writeText(content).then(() => {
      if (typeof showToast === 'function') showToast('Format WA RENOPS berhasil disalin!', 'success');
    });
  } else {
    const ta = document.createElement('textarea');
    ta.value = content;
    document.body.appendChild(ta);
    ta.select();
    document.execCommand('copy');
    document.body.removeChild(ta);
    if (typeof showToast === 'function') showToast('Format WA RENOPS berhasil disalin!', 'success');
  }
}

function openModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.remove('hidden');
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add('hidden');
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

// Real-time Day Recalculation Based on Input Date
const OPERASI_START_DATE = <?= json_encode(date('Y-m-d', strtotime(TemplateHelper::getWaktuMulaiAktual($operasi)))) ?>;

function recalculateRenopsHariKe() {
  const tglInput = document.getElementById('renops_tanggal_laporan')?.value;
  if (!tglInput || !OPERASI_START_DATE) return;
  const t1 = new Date(OPERASI_START_DATE + 'T00:00:00');
  const t2 = new Date(tglInput + 'T00:00:00');
  if (isNaN(t1.getTime()) || isNaN(t2.getTime())) return;
  const diffDays = Math.floor((t2 - t1) / (1000 * 60 * 60 * 24));
  const dayNum = Math.max(1, diffDays + 1);
  const inputHari = document.getElementById('renops_hari_ke');
  if (inputHari) {
    inputHari.value = 'H.' + dayNum;
  }
}

document.getElementById('renops_tanggal_laporan')?.addEventListener('input', recalculateRenopsHariKe);
document.getElementById('renops_tanggal_laporan')?.addEventListener('change', recalculateRenopsHariKe);

document.addEventListener('DOMContentLoaded', () => {
  renderSruList();
  renderUnsurList();
  renderAlutList();
  restoreRenopsDraft();
});
</script>
