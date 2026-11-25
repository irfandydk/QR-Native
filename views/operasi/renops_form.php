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
$cuaca = $cuaca ?? '';
$masterTim = $masterTim ?? [];
$masterAlut = $masterAlut ?? [];
$initialUnsurData = $initialUnsurData ?? [];
$initialAlutData = $initialAlutData ?? [];
$initialSrus = $initialSrus ?? [];
$renops = $renops ?? [];
$satuanJarak = !empty($renops['satuan_jarak']) ? $renops['satuan_jarak'] : 'NM';
$gisBundle = $gisBundle ?? ['points' => [], 'polygons' => [], 'routes' => [], 'searchPatterns' => []];
$totalGisItems = count($gisBundle['polygons'] ?? []) + count($gisBundle['searchPatterns'] ?? []) + count($gisBundle['routes'] ?? []) + count($gisBundle['points'] ?? []);
$cancelUrl = BASE_URL . '/operasi_detail.php?id=' . $idOperasi . '&tab=renops';
?>

<div class="ops-form-shell">

  <!-- Unified Page Header -->
  <div class="bento-card-v1 cockpit-glass ops-form-header">
    <div class="space-y-1.5 flex-1 min-w-0">
      <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>" class="hover:text-orange-500 transition-colors inline-flex items-center gap-1 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          Detail Operasi
        </a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-orange-600 dark:text-orange-400 font-semibold"><?= $editMode ? 'Edit RENOPS' : 'RENOPS' ?></span>
      </nav>
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          <?= $editMode ? ("Edit Rencana Operasi SAR (" . e($hariKe) . ")") : ("Rencana Operasi SAR (" . e($hariKe) . ")") ?>
        </h1>
        <?php if ($editMode): ?>
          <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
            Mode Perbaikan
          </span>
        <?php endif; ?>
      </div>
      <?php if (!empty($operasi['nama_operasi'])): ?>
        <p class="text-xs font-medium text-slate-600 dark:text-slate-300 truncate max-w-2xl">
          <?= e($operasi['nama_operasi']) ?>
        </p>
      <?php endif; ?>
    </div>

    <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
      <button type="button" onclick="previewRenopsWa()" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Pratinjau WA</span>
      </button>
      <a href="<?= $cancelUrl ?>" class="btn-tactile inline-flex items-center justify-center px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition">
        Batal
      </a>
    </div>
  </div>

  <form id="form-renops" method="POST" action="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $idOperasi ?><?= $editMode ? "&id_renops={$idRenops}" : "" ?>" class="space-y-5">
    <?= csrfInput() ?>
    <input type="hidden" name="id_operasi" value="<?= $idOperasi ?>">
    <?php if ($editMode): ?>
      <input type="hidden" name="id_renops" value="<?= $idRenops ?>">
    <?php endif; ?>

    <?php if (!empty($operasi['waktu_reopen'])):
      $baseHariReopen = TemplateHelper::getBaseHariSebelumReopen($operasi);
    ?>
    <div class="px-4 py-3 rounded-2xl bg-amber-500/10 border border-amber-500/25 text-amber-900 dark:text-amber-200 flex items-center gap-3 text-xs">
      <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
      <span>Operasi dibuka kembali <strong><?= TemplateHelper::formatTanggalIndo($operasi['waktu_reopen']) ?> (<?= TemplateHelper::formatJamWITA($operasi['waktu_reopen']) ?> WITA)</strong>, format hari lanjutan <strong>H<?= $baseHariReopen ?> + 1, dst.</strong></span>
    </div>
    <?php endif; ?>

    <!-- 01: Parameter Waktu & Cuaca -->
    <section class="bento-card-v1 ops-section">
      <div class="ops-section-head">
        <div class="ops-section-title-group">
          <span class="ops-section-num">01</span>
          <h2 class="ops-section-title">Parameter Waktu &amp; Kondisi Cuaca</h2>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="field">
          <label for="renops_hari_ke" class="field-label">Hari Operasi Ke <span class="text-red-500">*</span></label>
          <input class="field-input w-full font-bold font-mono tabular-nums" type="text" name="hari_ke" id="renops_hari_ke" required
            placeholder="Contoh: H.1, H.2"
            value="<?= e($hariKe) ?>" />
        </div>

        <div class="field">
          <label for="renops_tanggal_laporan" class="field-label">Tanggal Pelaksanaan <span class="text-red-500">*</span></label>
          <input placeholder=" " class="field-input w-full font-mono tabular-nums" type="date" name="tanggal_laporan" id="renops_tanggal_laporan" required
            value="<?= e($tanggalLaporan) ?>" />
        </div>

        <div class="field">
          <label for="renops_waktu_briefing" class="field-label">Waktu Briefing (WITA) <span class="text-red-500">*</span></label>
          <input placeholder=" " class="field-input w-full font-mono tabular-nums" type="datetime-local" name="waktu_briefing" id="renops_waktu_briefing" required
            value="<?= e($waktuBriefing) ?>" />
        </div>

        <div class="field">
          <label for="renops_waktu_mulai_ops" class="field-label">Waktu Mulai Pergerakan (WITA) <span class="text-red-500">*</span></label>
          <input placeholder=" " class="field-input w-full font-mono tabular-nums" type="datetime-local" name="waktu_mulai_ops" id="renops_waktu_mulai_ops" required
            value="<?= e($waktuMulaiOps) ?>" />
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="field">
          <label for="renops_cuaca" class="field-label">Kondisi Cuaca (BMKG)</label>
          <input class="field-input w-full" type="text" name="cuaca" id="renops_cuaca"
            placeholder="Contoh: Cerah Berawan"
            value="<?= e($cuaca) ?>" />
        </div>
        <div class="field">
          <label for="renops_gelombang" class="field-label">Tinggi Gelombang</label>
          <input class="field-input w-full" type="text" name="gelombang" id="renops_gelombang"
            placeholder="Contoh: 0,2 Mtr"
            value="<?= e($gelombang ?? '') ?>" />
        </div>
        <div class="field">
          <label for="renops_angin" class="field-label">Arah &amp; Kecepatan Angin</label>
          <input class="field-input w-full" type="text" name="angin" id="renops_angin"
            placeholder="Contoh: 3,1 km/jam dari Utara"
            value="<?= e($angin ?? '') ?>" />
        </div>
      </div>
    </section>

    <!-- 02: Pembagian Sektor & Unit SRU -->
    <section class="bento-card-v1 ops-section">
      <div class="ops-section-head">
        <div class="ops-section-title-group">
          <span class="ops-section-num">02</span>
          <h2 class="ops-section-title">Pembagian Sektor &amp; Unit SRU</h2>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <!-- Master Satuan Jarak Switcher -->
          <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl border border-slate-200 dark:border-slate-700/80">
            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 px-1.5 hidden sm:inline">Satuan</span>
            <div id="renopsUnitSwitcher" class="inline-flex rounded-lg p-0.5 bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-700 text-[11px] font-bold">
              <button type="button" data-renops-unit="NM" class="renops-unit-btn px-2.5 py-1 rounded-md transition cursor-pointer font-bold bg-orange-500 text-white shadow-xs" title="Nautical Miles">
                NM
              </button>
              <button type="button" data-renops-unit="KM" class="renops-unit-btn px-2.5 py-1 rounded-md transition cursor-pointer font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white" title="Kilometer">
                KM
              </button>
              <button type="button" data-renops-unit="M" class="renops-unit-btn px-2.5 py-1 rounded-md transition cursor-pointer font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white" title="Meter">
                M
              </button>
            </div>
            <input type="hidden" name="satuan_jarak" id="renops_satuan_jarak" value="<?= e($satuanJarak) ?>">
          </div>

          <button type="button" onclick="openGisImportModal()" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/10 hover:bg-amber-500 text-amber-700 dark:text-amber-300 hover:text-white border border-amber-500/25 transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
            <span>Ambil dari Peta</span>
            <span id="gis-bundle-badge" class="px-1.5 py-0.5 rounded-md bg-amber-500/20 text-[11px] font-mono font-bold tabular-nums"><?= $totalGisItems ?></span>
          </button>

          <button type="button" id="btn-sync-all-gis-srus" onclick="syncAllPlottingSectorsToSru()" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-emerald-600 hover:bg-emerald-500 text-white transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <span>Sinkronkan Sektor</span>
          </button>

          <button type="button" onclick="addSruRow()" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-orange-600 hover:bg-orange-500 text-white transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span>Tambah SRU</span>
          </button>
        </div>
      </div>

      <div id="sru-container" class="space-y-3"></div>
    </section>

    <!-- 03 & 04: Unsur SAR Terlibat & Alut/Palsar -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- 03: Unsur SAR Terlibat -->
      <section class="bento-card-v1 ops-section">
        <div class="ops-section-head">
          <div class="ops-section-title-group">
            <span class="ops-section-num">03</span>
            <h3 class="ops-section-title">Unsur SAR Terlibat</h3>
          </div>
          <span id="renops-total-personil" class="ops-counter-pill">Total: 0 Org</span>
        </div>

        <div class="ops-subpanel">
          <div class="field">
            <label for="select-master-tim" class="field-label">Master Tim / Unsur</label>
            <select id="select-master-tim" onchange="pickMasterTim(this.value)" class="field-input w-full">
              <option value="">-- Pilih dari Master Data Tim --</option>
              <?php foreach ($masterTim as $mt): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_tim' => $mt['id_tim'], 'nama_tim' => $mt['nama_tim']]), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($mt['nama_tim']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 pt-2 border-t border-slate-200/70 dark:border-slate-800">
            <div class="sm:col-span-8">
              <input type="text" id="unsur-input-nama" aria-label="Nama Unsur" placeholder="Nama unsur / potensi SAR..." class="control control-sm w-full">
            </div>
            <div class="sm:col-span-3 flex items-center gap-1.5">
              <input type="number" inputmode="numeric" id="unsur-input-jml" aria-label="Jumlah Personil" min="1" max="999" value="1" placeholder="Qty" class="control control-sm w-full text-center font-bold font-mono tabular-nums">
              <span class="text-xs font-medium text-slate-500 dark:text-slate-400 shrink-0">Org</span>
            </div>
            <div class="sm:col-span-1">
              <button type="button" onclick="handleAddUnsurClick()" class="btn-tactile w-full h-full min-h-[34px] bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold flex items-center justify-center transition cursor-pointer" title="Tambah Unsur" aria-label="Tambah Unsur">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div id="unsur-items-container" class="space-y-2 min-h-[56px]"></div>

        <input type="hidden" name="detail_unsur_json" id="detail_unsur_json" value="">
        <textarea name="unsur_text" id="unsur_text" class="hidden"><?= e($renops['unsur_text'] ?? '') ?></textarea>
      </section>

      <!-- 04: Alut & Palsar Terlibat -->
      <section class="bento-card-v1 ops-section">
        <div class="ops-section-head">
          <div class="ops-section-title-group">
            <span class="ops-section-num">04</span>
            <h3 class="ops-section-title">Alut &amp; Palsar Terlibat</h3>
          </div>
          <span id="renops-total-alut" class="ops-counter-pill">Total: 0 Alut</span>
        </div>

        <div class="ops-subpanel">
          <div class="field">
            <label for="select-master-alut" class="field-label">Master Alut / Palsar</label>
            <select id="select-master-alut" onchange="pickMasterAlut(this.value)" class="field-input w-full">
              <option value="">-- Pilih dari Master Data Alut --</option>
              <?php foreach ($masterAlut as $ma): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_alut' => $ma['id_alut'], 'nama_alut' => $ma['nama_alut'], 'satuan' => $ma['satuan'] ?? 'Unit', 'kategori' => AlutKategori::resolve($ma)]), ENT_QUOTES, 'UTF-8') ?>">
                  [<?= htmlspecialchars(AlutKategori::label(AlutKategori::resolve($ma))) ?>] <?= htmlspecialchars($ma['nama_alut']) ?> (<?= htmlspecialchars($ma['satuan'] ?? 'Unit') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 pt-2 border-t border-slate-200/70 dark:border-slate-800">
            <div class="sm:col-span-4">
              <input type="text" id="alut-input-nama" aria-label="Nama Alut" placeholder="Nama alut / palsar..." class="control control-sm w-full">
            </div>
            <div class="sm:col-span-3">
              <select id="alut-input-kategori" aria-label="Kategori Alut" class="control control-sm w-full" title="Kategori Alut / Palsar">
                <?php foreach (AlutKategori::LABEL as $kodeKat => $labelKat): ?>
                  <option value="<?= e($kodeKat) ?>"<?= $kodeKat === AlutKategori::PALSAR ? ' selected' : '' ?>><?= e($labelKat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="sm:col-span-2">
              <input type="number" inputmode="numeric" id="alut-input-jml" aria-label="Jumlah Alut" min="1" max="999" value="1" placeholder="Qty" class="control control-sm w-full text-center font-bold font-mono tabular-nums">
            </div>
            <div class="sm:col-span-2">
              <select id="alut-input-satuan" aria-label="Satuan Alut" class="control control-sm w-full">
                <option value="Unit">Unit</option>
                <option value="Set">Set</option>
                <option value="Buah">Buah</option>
                <option value="Paket">Paket</option>
                <option value="Batang">Batang</option>
                <option value="Orang">Orang</option>
              </select>
            </div>
            <div class="sm:col-span-1">
              <button type="button" onclick="handleAddAlutClick()" class="btn-tactile w-full h-full min-h-[34px] bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold flex items-center justify-center transition cursor-pointer" title="Tambah Alut" aria-label="Tambah Alut">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
              </button>
            </div>
          </div>
        </div>

        <div id="alut-items-container" class="space-y-2 min-h-[56px]"></div>

        <input type="hidden" name="detail_alut_json" id="detail_alut_json" value="">
        <textarea name="alut_text" id="alut_text" class="hidden"><?= e($renops['alut_text'] ?? '') ?></textarea>
      </section>

    </div>

    <!-- Unified Sticky Action Bar -->
    <div class="cockpit-glass ops-action-bar">
      <div class="flex items-center gap-3">
        <a href="<?= $cancelUrl ?>" class="btn-tactile inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 font-semibold text-xs transition">
          Batal
        </a>
        <span class="ops-counter-pill hidden sm:inline-flex"><?= e($hariKe) ?></span>
      </div>

      <div class="flex items-center gap-2.5 justify-end">
        <button type="button" onclick="previewRenopsWa()" class="btn-tactile inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 font-semibold text-xs border border-slate-200 dark:border-slate-700 transition cursor-pointer">
          <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
          <span>Pratinjau Format WA</span>
        </button>

        <button type="submit" class="btn-tactile inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-semibold text-xs transition cursor-pointer shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span><?= $editMode ? 'Simpan Perubahan RENOPS' : 'Terbitkan RENOPS' ?></span>
        </button>
      </div>
    </div>

  </form>
</div>

<!-- Modal Pratinjau WhatsApp RENOPS -->
<div id="modal-preview-renops-wa" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md hidden">
  <div class="ops-modal-card max-w-2xl">
    <div class="ops-modal-head">
      <div class="flex items-center gap-2.5">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pratinjau Format WhatsApp RENOPS</h3>
      </div>
      <button type="button" onclick="closeModal('modal-preview-renops-wa')" aria-label="Tutup" class="ops-close-btn">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-5 overflow-y-auto flex-1 font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap select-all leading-relaxed bg-slate-50 dark:bg-slate-950" id="renops-wa-preview-content"></div>
    <div class="ops-modal-foot">
      <button type="button" onclick="closeModal('modal-preview-renops-wa')" class="btn-tactile px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
        Tutup
      </button>
      <button type="button" onclick="copyRenopsWa()" class="btn-tactile inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition cursor-pointer">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
        <span>Salin Teks WA</span>
      </button>
    </div>
  </div>
</div>

<!-- Modal Ambil Data Plotting ke RENOPS -->
<div id="modal-import-gis-renops" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md hidden">
  <div class="ops-modal-card max-w-4xl max-h-[92vh]">
    <div class="ops-modal-head bg-slate-50/70 dark:bg-slate-950/70">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/25 flex items-center justify-center shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
        </div>
        <div class="flex flex-wrap items-center gap-2 min-w-0">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">Data Peta Plotting</h3>
          <span class="ops-counter-pill text-[11px]">
            Satuan:&nbsp;<strong class="text-orange-600 dark:text-orange-400 renops-active-unit-label"><?= e($satuanJarak) ?></strong>
          </span>
        </div>
      </div>

      <div class="flex items-center gap-2 shrink-0">
        <button type="button" id="btn-refresh-gis-bundle" onclick="refreshGisBundle(true)" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/25 transition cursor-pointer">
          <svg id="icon-refresh-gis" class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 transition-transform duration-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
          </svg>
          <span id="label-refresh-gis">Refresh</span>
        </button>
        <button type="button" onclick="closeModal('modal-import-gis-renops')" aria-label="Tutup" class="ops-close-btn">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <div class="px-5 py-3 bg-orange-500/5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
      <div id="gis-import-checklist" class="text-xs text-slate-700 dark:text-slate-300 space-y-1 flex-1 min-w-0"></div>
      <button type="button" onclick="importAllPolygonsToSrus()" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-semibold bg-orange-600 hover:bg-orange-500 text-white transition cursor-pointer shrink-0">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span>Impor per SRU</span>
      </button>
    </div>

    <div class="px-5 pt-2.5 border-b border-slate-200 dark:border-slate-800 flex items-center gap-2 overflow-x-auto bg-white dark:bg-slate-900">
      <button type="button" onclick="switchGisImportTab('polygons')" id="btn-tab-polygons" class="tab-gis-btn px-3 py-2 text-xs font-semibold rounded-t-xl border-b-2 border-orange-500 text-orange-600 dark:text-orange-400 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>Search Area</span>
        <span id="tab-cnt-polygons" class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-mono tabular-nums"><?= count($gisBundle['polygons'] ?? []) ?></span>
      </button>
      <button type="button" onclick="switchGisImportTab('patterns')" id="btn-tab-patterns" class="tab-gis-btn px-3 py-2 text-xs font-semibold rounded-t-xl border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>Pola Pencarian</span>
        <span id="tab-cnt-patterns" class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-mono tabular-nums"><?= count($gisBundle['searchPatterns'] ?? []) ?></span>
      </button>
      <button type="button" onclick="switchGisImportTab('routes')" id="btn-tab-routes" class="tab-gis-btn px-3 py-2 text-xs font-semibold rounded-t-xl border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>Jalur / Rute</span>
        <span id="tab-cnt-routes" class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-mono tabular-nums"><?= count($gisBundle['routes'] ?? []) ?></span>
      </button>
      <button type="button" onclick="switchGisImportTab('points')" id="btn-tab-points" class="tab-gis-btn px-3 py-2 text-xs font-semibold rounded-t-xl border-b-2 border-transparent text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition cursor-pointer flex items-center gap-1.5 shrink-0">
        <span>Titik Taktis &amp; LKP</span>
        <span id="tab-cnt-points" class="px-1.5 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[11px] font-mono tabular-nums"><?= count($gisBundle['points'] ?? []) ?></span>
      </button>
    </div>

    <div class="p-5 overflow-y-auto flex-1 space-y-3 bg-slate-50/60 dark:bg-slate-950/40" id="gis-import-content-container"></div>

    <div class="ops-modal-foot flex-col sm:flex-row">
      <div class="text-xs text-slate-500 flex flex-wrap items-center gap-3">
        <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= $idOperasi ?>&include_base=1" target="_blank" class="text-orange-600 dark:text-orange-400 hover:underline inline-flex items-center gap-1 font-semibold">
          <span>Buka Peta Plotting</span>
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        </a>
        <span id="gis-last-sync-time" class="text-[11px] text-slate-400 dark:text-slate-500"></span>
      </div>
      <button type="button" onclick="closeModal('modal-import-gis-renops')" class="btn-tactile w-full sm:w-auto px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-800 text-center">
        Tutup
      </button>
    </div>
  </div>
</div>

<script>window.ALUT_KATEGORI = <?= jsonSafe(AlutKategori::configJs()) ?>;</script>
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
    'satuanJarak' => $satuanJarak,
]) ?>;
</script>
<script src="<?= assetUrl('assets/vendor/sortablejs/Sortable.min.js') ?>"></script>
<script src="<?= assetUrl('assets/js/alut_kategori.js') ?>"></script>
<script src="<?= assetUrl('assets/js/renops_import.js') ?>"></script>
<script src="<?= assetUrl('assets/js/pages/renops_form.js') ?>"></script>
