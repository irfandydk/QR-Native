<?php
/**
 * QR-SAR - Dedicated View Form Laporan Perkembangan & Penutupan (SITREP / LAPBANG)
 * KPP Kendari - Basarnas
 *
 * @var int $idOperasi
 * @var bool $editMode
 * @var int $idLaporan
 * @var array $operasi
 * @var array $masterTim
 * @var array $masterAlut
 * @var string $hariKe
 * @var string $jenisLaporan
 * @var string $waktuLaporan
 * @var string $waktuDebrief
 * @var string $cuaca
 * @var string $gelombang
 * @var string $angin
 * @var string $isiLaporan
 * @var array $initialUnsurData
 * @var array $initialAlutData
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

// Defensive fallback initialization for IDE static analysis and safe isolated view evaluation
$idOperasi = $idOperasi ?? (int)($_GET['id_operasi'] ?? 0);
$editMode = $editMode ?? false;
$idLaporan = $idLaporan ?? (int)($_GET['id_laporan'] ?? 0);
$operasi = $operasi ?? [];
$hariKe = $hariKe ?? 'H.1';
$jenisLaporan = $jenisLaporan ?? 'PERKEMBANGAN';
$waktuLaporan = $waktuLaporan ?? date('Y-m-d\TH:i');
$waktuDebrief = $waktuDebrief ?? date('Y-m-d\TH:i');
$cuaca = $cuaca ?? '';
$gelombang = $gelombang ?? '';
$angin = $angin ?? '';
$isiLaporan = $isiLaporan ?? '';
$masterTim = $masterTim ?? [];
$masterAlut = $masterAlut ?? [];
$initialUnsurData = $initialUnsurData ?? [];
$initialAlutData = $initialAlutData ?? [];
$cancelUrl = BASE_URL . '/operasi_detail.php?id=' . $idOperasi . '&tab=sitrep';
$hasKorban = !empty($operasi['korban']);
$unsurSecNum = $hasKorban ? '04' : '03';
$alutSecNum = $hasKorban ? '05' : '04';
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
        <span class="text-orange-600 dark:text-orange-400 font-semibold"><?= $editMode ? 'Edit LAPBANG' : 'LAPBANG' ?></span>
      </nav>
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          <?= $editMode ? ("Edit Laporan Perkembangan (" . e($hariKe) . ")") : ("Laporan Perkembangan (" . e($hariKe) . ")") ?>
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
      <button type="button" onclick="previewSitrepWa()" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Pratinjau WA</span>
      </button>
      <a href="<?= $cancelUrl ?>" class="btn-tactile inline-flex items-center justify-center px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition">
        Batal
      </a>
    </div>
  </div>

  <form id="form-sitrep" method="POST" action="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $idOperasi ?><?= $editMode ? "&id_laporan={$idLaporan}" : "" ?>" class="space-y-5">
    <?= csrfInput() ?>
    <input type="hidden" name="id_operasi" value="<?= $idOperasi ?>">
    <?php if ($editMode): ?>
      <input type="hidden" name="id_laporan" value="<?= $idLaporan ?>">
    <?php endif; ?>

    <?php if (!empty($operasi['waktu_reopen'])):
      $baseHariReopen = TemplateHelper::getBaseHariSebelumReopen($operasi);
    ?>
    <div class="px-4 py-3 rounded-2xl bg-amber-500/10 border border-amber-500/25 text-amber-900 dark:text-amber-200 flex items-center gap-3 text-xs">
      <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
      <span>Operasi dibuka kembali <strong><?= TemplateHelper::formatTanggalIndo($operasi['waktu_reopen']) ?> (<?= TemplateHelper::formatJamWITA($operasi['waktu_reopen']) ?> WITA)</strong>, format hari lanjutan <strong>H<?= $baseHariReopen ?> + 1, dst.</strong></span>
    </div>
    <?php endif; ?>

    <!-- 01: Klasifikasi Laporan & Parameter Waktu -->
    <section class="bento-card-v1 ops-section">
      <div class="ops-section-head">
        <div class="ops-section-title-group">
          <span class="ops-section-num">01</span>
          <h2 class="ops-section-title">Klasifikasi Laporan &amp; Parameter Waktu</h2>
        </div>

        <?php $isClosedPenutupanEdit = (($operasi['status'] ?? '') === 'CLOSED' && $editMode && $jenisLaporan === 'PENUTUPAN'); ?>
        <div class="flex items-center gap-2">
          <?php if ($isClosedPenutupanEdit): ?>
            <input type="hidden" name="jenis_laporan" value="PENUTUPAN">
          <?php endif; ?>
          <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border transition-all text-xs font-semibold <?= $jenisLaporan === 'PERKEMBANGAN' ? 'bg-orange-600 text-white border-orange-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700' ?> <?= $isClosedPenutupanEdit ? 'opacity-40 cursor-not-allowed' : 'cursor-pointer' ?>" id="label-jenis-perkembangan" title="<?= $isClosedPenutupanEdit ? 'Operasi berstatus CLOSED: jenis laporan terkunci pada Penutupan' : '' ?>">
            <input type="radio" name="jenis_laporan" value="PERKEMBANGAN" <?= $jenisLaporan === 'PERKEMBANGAN' ? 'checked' : '' ?> <?= $isClosedPenutupanEdit ? 'disabled' : '' ?> onchange="handleJenisLaporanChange(this.value)" class="hidden">
            <span>Laporan Harian</span>
          </label>
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border transition-all text-xs font-semibold <?= $jenisLaporan === 'PENUTUPAN' ? 'bg-red-600 text-white border-red-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700' ?>" id="label-jenis-penutupan">
            <input type="radio" name="jenis_laporan" value="PENUTUPAN" <?= $jenisLaporan === 'PENUTUPAN' ? 'checked' : '' ?> onchange="handleJenisLaporanChange(this.value)" class="hidden">
            <span>Penutupan Operasi</span>
          </label>
        </div>
      </div>

      <?php
        $dayNumExtracted = (int)filter_var($hariKe, FILTER_SANITIZE_NUMBER_INT);
        $isH7Active = strtoupper(trim((string)$hariKe)) === 'H.7' || $dayNumExtracted >= 7;
      ?>
      <!-- Advisory Banner Batas Waktu Operasi SAR (Pasal 34 UU No. 29/2014) -->
      <div id="h7-advisory-banner" class="p-4 rounded-xl border <?= $isH7Active ? 'border-red-500/35 bg-red-500/10 text-red-900 dark:text-red-200' : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200 hidden' ?> text-xs">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-lg <?= $isH7Active ? 'bg-red-500/15 text-red-600 dark:text-red-400' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400' ?> flex items-center justify-center shrink-0">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m-7-7 3-7 3 7m-6 0a3 3 0 0 0 6 0m5-7 3 7 3-7m-6 7a3 3 0 0 0 6 0M5 7h14"/></svg>
            </div>
            <h4 class="font-bold <?= $isH7Active ? 'text-red-800 dark:text-red-300' : 'text-amber-800 dark:text-amber-300' ?>">
              Ketentuan Operasi SAR Hari Ke-7 (Pasal 34 UU No. 29 Tahun 2014)
            </h4>
          </div>
          <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button type="button" onclick="applyH7ClosureTemplate()" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 text-white font-semibold text-xs transition cursor-pointer">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m0 0v2m0-2h2m-2 0H10"/><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <span>Naskah Penutupan H.7</span>
            </button>
            <button type="button" onclick="applyH7ExtensionTemplate()" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600 text-white font-semibold text-xs transition cursor-pointer">
              <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <span>Usulan Perpanjangan H.7</span>
            </button>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="field">
          <label for="sitrep_hari_ke" class="field-label">Hari Operasi Ke <span class="text-red-500">*</span></label>
          <input class="field-input w-full font-bold font-mono tabular-nums" type="text" name="hari_ke" id="sitrep_hari_ke" required
            placeholder="Contoh: H.1, H.2"
            value="<?= e($hariKe) ?>" />
        </div>

        <div class="field">
          <label for="sitrep_waktu_laporan" class="field-label">Waktu Laporan (WITA) <span class="text-red-500">*</span></label>
          <input placeholder=" " class="field-input w-full font-mono tabular-nums" type="datetime-local" name="waktu_laporan" id="sitrep_waktu_laporan" required
            value="<?= e($waktuLaporan) ?>" />
        </div>

        <div id="wrapper-debrief" class="<?= $jenisLaporan === 'PENUTUPAN' ? '' : 'hidden' ?>">
          <div class="field">
            <label for="sitrep_waktu_debrief" class="field-label">Waktu Debriefing Penutupan (WITA) <span class="text-red-500">*</span></label>
            <input placeholder=" " class="field-input w-full font-mono tabular-nums text-red-700 dark:text-red-300 font-bold" type="datetime-local" name="waktu_debrief" id="sitrep_waktu_debrief"
              value="<?= e($waktuDebrief) ?>" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="field">
          <label for="sitrep_cuaca" class="field-label">Kondisi Cuaca (BMKG)</label>
          <input class="field-input w-full" type="text" name="cuaca" id="sitrep_cuaca"
            placeholder="Contoh: Cerah Berawan"
            value="<?= e($cuaca) ?>" />
        </div>
        <div class="field">
          <label for="sitrep_gelombang" class="field-label">Tinggi Gelombang</label>
          <input class="field-input w-full" type="text" name="gelombang" id="sitrep_gelombang"
            placeholder="Contoh: 0,2 Mtr"
            value="<?= e($gelombang) ?>" />
        </div>
        <div class="field">
          <label for="sitrep_angin" class="field-label">Arah &amp; Kecepatan Angin</label>
          <input class="field-input w-full" type="text" name="angin" id="sitrep_angin"
            placeholder="Contoh: 3,1 km/jam dari Utara"
            value="<?= e($angin) ?>" />
        </div>
      </div>
    </section>

    <!-- 02: Hasil Pencarian & Tindakan Lapangan -->
    <section class="bento-card-v1 ops-section">
      <div class="ops-section-head">
        <div class="ops-section-title-group">
          <span class="ops-section-num">02</span>
          <h2 class="ops-section-title">Hasil Pencarian &amp; Tindakan Lapangan</h2>
        </div>

        <div class="flex items-center gap-2">
          <button type="button" id="btn-quick-refine-sitrep" onclick="quickAiRefineSitrep(this)" class="btn-tactile ops-ai-soft-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span class="btn-text">Rapikan AI</span>
          </button>
          <button type="button" id="btn-open-ai-sitrep-modal" onclick="openAiSitrepModal()" class="btn-tactile ops-ai-accent-btn inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span>Asisten AI</span>
          </button>
        </div>
      </div>

      <div>
        <label for="sitrep_isi_laporan" class="sr-only">Hasil Pencarian dan Tindakan Lapangan</label>
        <textarea name="isi_laporan" id="sitrep_isi_laporan" rows="5" required
          placeholder="Tuliskan hasil operasi SAR, temuan lapangan, debriefing, atau rencana lanjutan..."
          class="control w-full text-sm leading-relaxed"><?= e($isiLaporan) ?></textarea>
      </div>
    </section>

    <!-- 03: Pembaruan Status Korban (POB) -->
    <?php if ($hasKorban): ?>
    <section class="bento-card-v1 ops-section">
      <div class="ops-section-head">
        <div class="ops-section-title-group">
          <span class="ops-section-num">03</span>
          <h2 class="ops-section-title">Pembaruan Status Korban (POB)</h2>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <div id="victim-status-badges" class="flex flex-wrap items-center gap-1.5 text-xs font-semibold"></div>
          <span class="ops-counter-pill">Total POB: <?= count($operasi['korban']) ?></span>
        </div>
      </div>

      <div class="table-responsive rounded-xl border border-slate-200/80 dark:border-slate-800 overflow-hidden">
        <table class="table w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400">
            <tr class="border-b border-slate-200 dark:border-slate-800 uppercase tracking-wider text-[11px] font-semibold">
              <th class="py-2.5 px-3 w-12 text-center">No</th>
              <th class="py-2.5 px-3">Nama Korban</th>
              <th class="py-2.5 px-3 w-28">Umur</th>
              <th class="py-2.5 px-3 w-32">L/P</th>
              <th class="py-2.5 px-3" style="width: 250px;">Status Terkini</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($operasi['korban'] as $idx => $k):
              $st = strtoupper((string)($k['status_korban'] ?? 'DP'));
            ?>
            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
              <td class="py-2.5 px-3 text-center font-mono tabular-nums text-slate-400"><?= $idx + 1 ?></td>
              <td class="py-2.5 px-3 font-semibold text-slate-800 dark:text-slate-200"><?= e($k['nama_korban']) ?></td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400"><?= e($k['umur'] ?: '-') ?></td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400"><?= e($k['jk'] ?: '-') ?></td>
              <td class="py-2.5 px-3">
                <select name="update_korban[<?= (int)$k['id_korban'] ?>]" aria-label="Status <?= e($k['nama_korban']) ?>" class="control control-sm w-full font-semibold">
                  <option value="DP" <?= $st === 'DP' ? 'selected' : '' ?>>Dalam Pencarian (DP)</option>
                  <option value="S" <?= $st === 'S' ? 'selected' : '' ?>>Selamat (S)</option>
                  <option value="MD" <?= $st === 'MD' ? 'selected' : '' ?>>Meninggal Dunia (MD)</option>
                  <option value="H" <?= $st === 'H' ? 'selected' : '' ?>>Hilang / Nihil (H)</option>
                </select>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
    <?php endif; ?>

    <!-- Unsur SAR Terlibat & Alut/Palsar -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      <!-- Unsur SAR Terlibat -->
      <section class="bento-card-v1 ops-section">
        <div class="ops-section-head">
          <div class="ops-section-title-group">
            <span class="ops-section-num"><?= $unsurSecNum ?></span>
            <h3 class="ops-section-title">Unsur SAR Terlibat</h3>
          </div>
          <span id="sitrep-total-personil" class="ops-counter-pill">Total: 0 Org</span>
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
        <textarea name="unsur_text" id="unsur_text" class="hidden"><?= e($laporan['unsur_text'] ?? '') ?></textarea>
      </section>

      <!-- Alut & Palsar Terlibat -->
      <section class="bento-card-v1 ops-section">
        <div class="ops-section-head">
          <div class="ops-section-title-group">
            <span class="ops-section-num"><?= $alutSecNum ?></span>
            <h3 class="ops-section-title">Alut &amp; Palsar Terlibat</h3>
          </div>
          <span id="sitrep-total-alut" class="ops-counter-pill">Total: 0 Alut</span>
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
        <textarea name="alut_text" id="alut_text" class="hidden"><?= e($laporan['alut_text'] ?? '') ?></textarea>
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
        <button type="button" onclick="previewSitrepWa()" class="btn-tactile inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 font-semibold text-xs border border-slate-200 dark:border-slate-700 transition cursor-pointer">
          <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
          <span>Pratinjau Format WA</span>
        </button>

        <button type="submit" id="btn-submit-sitrep" class="btn-tactile inline-flex items-center gap-2 px-5 py-2.5 rounded-xl <?= $jenisLaporan === 'PENUTUPAN' ? 'bg-red-600 hover:bg-red-500' : 'bg-orange-600 hover:bg-orange-500' ?> text-white font-semibold text-xs transition cursor-pointer shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span><?= $jenisLaporan === 'PENUTUPAN' ? ($editMode ? 'Simpan Laporan Penutupan' : 'Terbitkan Laporan Penutupan') : ($editMode ? 'Simpan Perubahan LAPBANG' : 'Terbitkan LAPBANG') ?></span>
        </button>
      </div>
    </div>

  </form>
</div>

<!-- Modal Pratinjau WhatsApp LAPBANG -->
<div id="modal-preview-sitrep-wa" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md hidden">
  <div class="ops-modal-card max-w-2xl">
    <div class="ops-modal-head">
      <div class="flex items-center gap-2.5">
        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 shrink-0"></span>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pratinjau Format WhatsApp LAPBANG</h3>
      </div>
      <button type="button" onclick="closeModal('modal-preview-sitrep-wa')" aria-label="Tutup" class="ops-close-btn">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-5 overflow-y-auto flex-1 font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap select-all leading-relaxed bg-slate-50 dark:bg-slate-950" id="sitrep-wa-preview-content"></div>
    <div class="ops-modal-foot">
      <button type="button" onclick="closeModal('modal-preview-sitrep-wa')" class="btn-tactile px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
        Tutup
      </button>
      <button type="button" onclick="copySitrepWa()" class="btn-tactile inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs transition cursor-pointer">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
        <span>Salin Teks WA</span>
      </button>
    </div>
  </div>
</div>

<!-- Modal Asisten AI LAPBANG -->
<div id="modal-ai-sitrep" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-md hidden" style="display: none;">
  <div class="ops-modal-card max-w-5xl">
    <div class="ops-modal-head">
      <div class="flex items-center gap-2.5">
        <div class="w-7 h-7 rounded-lg ops-ai-soft-badge flex items-center justify-center">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Asisten AI LAPBANG</h3>
      </div>
      <button type="button" onclick="closeModal('modal-ai-sitrep')" aria-label="Tutup" class="ops-close-btn">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="p-5 sm:p-6 overflow-y-auto grid md:grid-cols-2 gap-5">
      <div class="space-y-3">
        <div class="field">
          <label for="ai-sitrep-draft-input" class="field-label">Catatan Mentah Lapangan</label>
          <textarea id="ai-sitrep-draft-input" rows="14" style="min-height: 20rem;" placeholder="Tuliskan catatan hasil pencarian hari ini..." class="field-input w-full"></textarea>
        </div>
        <div class="flex justify-end">
          <button type="button" id="btn-generate-ai-sitrep" onclick="generateAiSitrep()" class="btn-tactile ops-ai-accent-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl font-semibold text-xs transition cursor-pointer">
            Susun Narasi Resmi
          </button>
        </div>
      </div>

      <div class="field">
        <label for="ai-sitrep-result-output" class="field-label">Hasil Narasi Resmi</label>
        <textarea id="ai-sitrep-result-output" rows="14" style="min-height: 20rem;" placeholder="Hasil olahan narasi AI akan muncul di sini..." class="field-input w-full leading-relaxed font-mono"></textarea>
      </div>
    </div>

    <div class="ops-modal-foot">
      <button type="button" onclick="closeModal('modal-ai-sitrep')" class="btn-tactile px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
        Batal
      </button>
      <button type="button" onclick="applyAiSitrep()" class="btn-tactile ops-ai-accent-btn inline-flex items-center gap-1.5 px-4 py-2 rounded-xl font-semibold text-xs transition cursor-pointer">
        Terapkan ke Form
      </button>
    </div>
  </div>
</div>

<script>window.ALUT_KATEGORI = <?= jsonSafe(AlutKategori::configJs()) ?>;</script>
<script>
window.__SITREP_FORM_CONFIG__ = <?= jsonSafe([
    'idOperasi' => $idOperasi,
    'editMode' => $editMode,
    'initialUnsurData' => $initialUnsurData,
    'initialAlutData' => $initialAlutData,
    'namaOperasi' => $operasi['nama_operasi'] ?? '',
    'korbanList' => $operasi['korban'] ?? [],
    'operasiStartDate' => date('Y-m-d', strtotime(TemplateHelper::getWaktuMulaiAwal($operasi))),
    'operasiReopenDate' => !empty($operasi['waktu_reopen']) ? date('Y-m-d', strtotime((string)$operasi['waktu_reopen'])) : null,
    'operasiBaseHariReopen' => (int)TemplateHelper::getBaseHariSebelumReopen($operasi),
]) ?>;
</script>
<script src="<?= assetUrl('assets/vendor/sortablejs/Sortable.min.js') ?>"></script>
<script src="<?= assetUrl('assets/js/alut_kategori.js') ?>"></script>
<script src="<?= assetUrl('assets/js/pages/sitrep_form.js') ?>"></script>
