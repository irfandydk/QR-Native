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
$isiLaporan = $isiLaporan ?? '';
$masterTim = $masterTim ?? [];
$masterAlut = $masterAlut ?? [];
$initialUnsurData = $initialUnsurData ?? [];
$initialAlutData = $initialAlutData ?? [];
?>

<div class="max-w-7xl mx-auto space-y-6 pb-20">

  <!-- Breadcrumb & Top Bar -->
  <div class="bento-card-v1 cockpit-glass flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl shadow-sm transition-colors">
    <div class="space-y-1.5 flex-1 min-w-0">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>" class="btn-tactile hover:text-blue-500 transition-colors flex items-center gap-1 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          Detail Operasi
        </a>
        <span>/</span>
        <span class="text-blue-600 dark:text-blue-400 font-bold"><?= $editMode ? 'Edit LAPBANG' : 'Penerbitan LAPBANG' ?></span>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 class="text-lg sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          <?= $editMode ? "Edit Laporan Perkembangan ({$hariKe})" : "Penerbitan Laporan Perkembangan ({$hariKe})" ?>
        </h1>
        <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full <?= $editMode ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30' ?> uppercase tracking-wider shrink-0 font-mono">
          <?= $editMode ? 'MODE PERBAIKAN' : 'DOKUMEN LAPBANG' ?>
        </span>
      </div>
      <p class="text-xs font-semibold text-slate-700 dark:text-slate-300 line-clamp-1 max-w-2xl">
        Operasi: <?= e($operasi['nama_operasi']) ?>
      </p>
    </div>

    <!-- Quick Action Buttons -->
    <div class="flex items-center gap-2.5 shrink-0 self-end sm:self-auto">
      <button type="button" onclick="previewSitrepWa()" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <span>Pratinjau Format WA</span>
      </button>
      <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>&tab=sitrep" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition cursor-pointer shadow-xs">
        Batal
      </a>
    </div>
  </div>

  <form id="form-sitrep" method="POST" action="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $idOperasi ?><?= $editMode ? "&id_laporan={$idLaporan}" : "" ?>" class="space-y-6">
    <?= csrfInput() ?>
    <input type="hidden" name="id_operasi" value="<?= $idOperasi ?>">
    <?php if ($editMode): ?>
      <input type="hidden" name="id_laporan" value="<?= $idLaporan ?>">
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

    <!-- POIN 1: Jenis Laporan & Parameter Waktu -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-xs">
            01
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
              1. Klasifikasi Laporan & Parameter Waktu
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pilih jenis laporan perkembangan harian atau laporan resmi penutupan operasi SAR</p>
          </div>
        </div>
        <span class="text-[11px] font-bold px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
          LAPBANG
        </span>
      </div>

      <!-- Toggle Jenis Laporan (Perkembangan vs Penutupan) -->
      <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <label class="text-xs font-bold text-slate-800 dark:text-slate-200 block">
            Jenis Laporan Operasional:
          </label>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">
            Pilih "Penutupan Operasi" jika seluruh korban telah ditemukan atau operasi resmi dihentikan
          </p>
        </div>
        <div class="flex items-center gap-2">
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold <?= $jenisLaporan === 'PERKEMBANGAN' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700' ?>" id="label-jenis-perkembangan">
            <input type="radio" name="jenis_laporan" value="PERKEMBANGAN" <?= $jenisLaporan === 'PERKEMBANGAN' ? 'checked' : '' ?> onchange="handleJenisLaporanChange(this.value)" class="hidden">
            <span>Laporan Harian (LAPBANG)</span>
          </label>
          <label class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-xl border transition-all text-xs font-bold <?= $jenisLaporan === 'PENUTUPAN' ? 'bg-red-600 text-white border-red-600' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-300 border-slate-300 dark:border-slate-700' ?>" id="label-jenis-penutupan">
            <input type="radio" name="jenis_laporan" value="PENUTUPAN" <?= $jenisLaporan === 'PENUTUPAN' ? 'checked' : '' ?> onchange="handleJenisLaporanChange(this.value)" class="hidden">
            <span>Penutupan Operasi</span>
          </label>
        </div>
      </div>

      <?php
        $dayNumExtracted = (int)filter_var($hariKe, FILTER_SANITIZE_NUMBER_INT);
        $isH7Active = strtoupper(trim((string)$hariKe)) === 'H.7' || $dayNumExtracted >= 7;
      ?>
      <!-- Advisory Banner Batas Waktu Operasi SAR (UU No. 29/2014) -->
      <div id="h7-advisory-banner" class="p-4 rounded-2xl border <?= $isH7Active ? 'border-red-500/40 bg-red-500/10 text-red-900 dark:text-red-200' : 'border-amber-500/30 bg-amber-500/10 text-amber-900 dark:text-amber-200 hidden' ?> text-xs space-y-2.5 shadow-xs transition-all">
        <div class="flex items-start gap-3">
          <div class="w-8 h-8 rounded-xl <?= $isH7Active ? 'bg-red-500/20 text-red-600 dark:text-red-400' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400' ?> flex items-center justify-center shrink-0 font-bold text-sm">
            ⚖️
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h4 class="font-bold text-xs sm:text-sm uppercase tracking-wide <?= $isH7Active ? 'text-red-800 dark:text-red-300' : 'text-amber-800 dark:text-amber-300' ?>">
                Ketentuan Penutupan Operasi SAR Hari Ke-7 (Pasal 34 UU No. 29 Tahun 2014)
              </h4>
              <span class="px-2 py-0.5 rounded-full text-[11px] font-bold <?= $isH7Active ? 'bg-red-500/20 text-red-700 dark:text-red-300 border border-red-500/40' : 'bg-amber-500/20 text-amber-700 dark:text-amber-300 border border-amber-500/30' ?>">
                REGULASI RESMI BASARNAS
              </span>
            </div>
            <p class="text-[11px] leading-relaxed mt-1 text-slate-700 dark:text-slate-300">
              Sesuai UU No. 29 Tahun 2014, jangka waktu pelaksanaan operasi SAR adalah <strong>paling lama 7 (tujuh) hari</strong>. Pada Hari Ke-7, SMC dan Tim Gabungan melaksanakan evaluasi bersama potensi SAR dan keluarga korban. Jika hasil pencarian tidak menunjukkan tanda-tanda korban ditemukan, operasi resmi diusulkan untuk <strong>DITUTUP</strong>, atau dapat diperpanjang jika terdapat petunjuk baru yang valid.
            </p>
            <div class="flex flex-wrap items-center gap-2 pt-2">
              <button type="button" onclick="applyH7ClosureTemplate()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 text-white font-bold text-xs shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m0 0v2m0-2h2m-2 0H10"/><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                <span>1-Klik: Terapkan Naskah Resmi Penutupan H.7 (Nihil / Sesuai UU 29/2014)</span>
              </button>
              <button type="button" onclick="applyH7ExtensionTemplate()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span>1-Klik: Usulan Perpanjangan Operasi H.7</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
          <div class="field">
            <label class="field-label">Hari Operasi Ke <span class="text-red-500">*</span></label>
            <input class="field-input w-full font-bold" type="text" name="hari_ke" id="sitrep_hari_ke" required
            placeholder="Contoh: H.1, H.2"
            value="<?= e($hariKe) ?>"  />
          </div>
        </div>

        <div>
          <div class="field">
            <label class="field-label">Waktu Laporan (WITA) <span class="text-red-500">*</span></label>
            <input placeholder=" " class="field-input w-full font-medium" type="datetime-local" name="waktu_laporan" id="sitrep_waktu_laporan" required
            value="<?= e($waktuLaporan) ?>"  />
          </div>
        </div>

        <div id="wrapper-debrief" class="<?= $jenisLaporan === 'PENUTUPAN' ? '' : 'hidden' ?>">
          <div class="field">
            <label class="field-label">Waktu Debriefing Penutupan (WITA) <span class="text-red-500">*</span></label>
            <input placeholder=" " class="field-input w-full text-red-900 dark:text-red-200 font-bold" type="datetime-local" name="waktu_debrief" id="sitrep_waktu_debrief"
            value="<?= e($waktuDebrief) ?>"  />
          </div>
        </div>
      </div>

      <!-- Cuaca BMKG -->
      <div>
        <div class="field">
          <label class="field-label">Kondisi Cuaca Terkini (BMKG)</label>
          <input class="field-input w-full" type="text" name="cuaca" id="sitrep_cuaca"
          placeholder="Contoh: Cerah Berawan, Gelombang 0.5 - 1.25 M, Angin 5 - 15 Knots"
          value="<?= e($cuaca) ?>"  />
        </div>
      </div>
    </div>

    <!-- POIN 2: Hasil Pencarian & Tindakan Lapangan -->
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-xs">
            02
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
              2. Hasil Pencarian & Tindakan Lapangan
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Deskripsi kronologis hasil operasi hari ini, koordinat temuan, debriefing, dan rencana lanjutan</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button type="button" id="btn-quick-refine-sitrep" onclick="quickAiRefineSitrep(this)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-500/10 hover:bg-purple-600 text-purple-700 dark:text-purple-300 hover:text-white border border-purple-500/20 hover:border-purple-600 shadow-sm transition cursor-pointer" title="Rapikan narasi hasil operasi secara instan dengan AI Gemini">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span class="btn-text">Rapikan dengan AI</span>
          </button>
          <button type="button" id="btn-open-ai-sitrep-modal" onclick="openAiSitrepModal()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-600 hover:bg-purple-700 text-white shadow-sm transition cursor-pointer" title="Buka dialog interaktif Asisten AI untuk menyusun narasi resmi LAPBANG">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
            <span>Asisten AI LAPBANG</span>
          </button>
        </div>
      </div>

      <div class="space-y-3">
        <textarea name="isi_laporan" id="sitrep_isi_laporan" rows="5" required
          placeholder="Tuliskan hasil operasi SAR, temuan lapangan, debriefing atau status operasi..."
          class="control control-sm w-full leading-relaxed"><?= e($isiLaporan) ?></textarea>
      </div>
    </div>

    <!-- POIN 3: Mutasi Status Korban (POB) -->
    <?php if (!empty($operasi['korban'])): ?>
    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between pb-4 border-b border-slate-100 dark:border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
            03
          </div>
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
              3. Pembaruan Status Korban (Mutasi POB)
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Ubah status kondisi korban terkini berdasarkan hasil evakuasi lapangan</p>
          </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <div id="victim-status-badges" class="flex flex-wrap items-center gap-1.5 text-xs font-bold"></div>
          <span class="text-xs font-mono font-bold text-slate-500">
            Total POB: <?= count($operasi['korban']) ?> Orang
          </span>
        </div>
      </div>

      <div class="table-responsive rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
        <table class="table w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-slate-800/60 text-slate-500 dark:text-slate-400">
            <tr>
              <th class="py-2.5 px-3">No</th>
              <th class="py-2.5 px-3">Nama Korban</th>
              <th class="py-2.5 px-3">Umur</th>
              <th class="py-2.5 px-3">L/P</th>
              <th class="py-2.5 px-3" style="width: 250px;">Status Terkini</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
            <?php foreach ($operasi['korban'] as $idx => $k): 
              $st = strtoupper((string)($k['status_korban'] ?? 'DP'));
            ?>
            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30">
              <td class="py-2.5 px-3 font-mono text-slate-400"><?= $idx + 1 ?></td>
              <td class="py-2.5 px-3 font-bold text-slate-800 dark:text-slate-200"><?= e($k['nama_korban']) ?></td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400"><?= e($k['umur'] ?: '-') ?></td>
              <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400"><?= e($k['jk'] ?: '-') ?></td>
              <td class="py-2.5 px-3">
                <select name="update_korban[<?= (int)$k['id_korban'] ?>]" class="control control-sm w-full font-bold">
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
    </div>
    <?php endif; ?>

    <!-- POIN 4 & 5: Unsur SAR Terlibat & Alut/Palsar (Interactive Reorderable Editors) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

      <!-- Unsur SAR Terlibat -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-purple-500/15 text-purple-600 dark:text-purple-400 flex items-center justify-center font-bold text-xs">
              04
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                4. Unsur SAR Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data atau input manual, dan atur prioritas urutan</p>
            </div>
          </div>
          <span id="sitrep-total-personil" class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-600 dark:text-purple-400">
            Total: 0 Org
          </span>
        </div>

        <!-- Master Data Selector -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-2">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <div class="field">
              <label class="field-label">Pilih Master Tim:</label>
              <select id="select-master-tim" onchange="pickMasterTim(this.value)" class="field-input flex-1">
              <option value="">-- Cari / Pilih dari Master Data Tim Basarnas --</option>
              <?php foreach ($masterTim as $mt): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_tim' => $mt['id_tim'], 'nama_tim' => $mt['nama_tim']]), ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($mt['nama_tim']) ?>
                </option>
              <?php endforeach; ?>
            </select>
            </div>
          </div>
        </div>

        <!-- Input Bar (Manual Add) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-8">
            <input type="text" id="unsur-input-nama" placeholder="Ketik nama unsur (contoh: Tim Rescue Kansar, BPBD)..." class="control control-sm w-full">
          </div>
          <div class="sm:col-span-3 flex items-center gap-1.5">
            <input type="number" id="unsur-input-jml" min="1" max="999" value="1" placeholder="Qty" class="control control-sm w-full text-center font-bold">
            <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 shrink-0">Org</span>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddUnsurClick()" class="w-full h-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Unsur ke Daftar">
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
        <textarea name="unsur_text" id="unsur_text" class="hidden"><?= e($laporan['unsur_text'] ?? '') ?></textarea>
      </div>

      <!-- Alut & Palsar Terlibat -->
      <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-xs space-y-4 transition-colors">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
          <div class="flex items-center gap-2.5">
            <div class="w-7 h-7 rounded-xl bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-xs">
              05
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                5. Alut & Palsar Terlibat
              </h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Pilih dari Master Data atau input manual, dan atur prioritas urutan</p>
            </div>
          </div>
          <span id="sitrep-total-alut" class="text-xs font-mono font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
            Total: 0 Alut
          </span>
        </div>

        <!-- Master Data Selector -->
        <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/60 space-y-2">
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <div class="field">
              <label class="field-label">Pilih Master Alut:</label>
              <select id="select-master-alut" onchange="pickMasterAlut(this.value)" class="field-input flex-1">
              <option value="">-- Cari / Pilih dari Master Data Alut Basarnas --</option>
              <?php foreach ($masterAlut as $ma): ?>
                <option value="<?= htmlspecialchars(json_encode(['id_alut' => $ma['id_alut'], 'nama_alut' => $ma['nama_alut'], 'satuan' => $ma['satuan'] ?? 'Unit', 'kategori' => AlutKategori::resolve($ma)]), ENT_QUOTES, 'UTF-8') ?>">
                  [<?= htmlspecialchars(AlutKategori::label(AlutKategori::resolve($ma))) ?>] <?= htmlspecialchars($ma['nama_alut']) ?> (<?= htmlspecialchars($ma['satuan'] ?? 'Unit') ?>)
                </option>
              <?php endforeach; ?>
            </select>
            </div>
          </div>
        </div>

        <!-- Input Bar (Manual Add) -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 p-3 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40">
          <div class="sm:col-span-4">
            <input type="text" id="alut-input-nama" placeholder="Ketik nama alut / palsar (contoh: Rubber Boat, Aqua Eye)..." class="control control-sm w-full">
          </div>
          <div class="sm:col-span-3">
            <select id="alut-input-kategori" class="control control-sm w-full" title="Kategori Alut / Palsar">
              <?php foreach (AlutKategori::LABEL as $kodeKat => $labelKat): ?>
                <option value="<?= e($kodeKat) ?>"<?= $kodeKat === AlutKategori::PALSAR ? ' selected' : '' ?>><?= e($labelKat) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="sm:col-span-2">
            <input type="number" id="alut-input-jml" min="1" max="999" value="1" placeholder="Qty" class="control control-sm w-full text-center font-bold">
          </div>
          <div class="sm:col-span-2">
            <select id="alut-input-satuan" class="control control-sm w-full">
              <option value="Unit">Unit</option>
              <option value="Set">Set</option>
              <option value="Buah">Buah</option>
              <option value="Paket">Paket</option>
              <option value="Batang">Batang</option>
              <option value="Orang">Orang</option>
            </select>
          </div>
          <div class="sm:col-span-1">
            <button type="button" onclick="handleAddAlutClick()" class="w-full h-full py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-bold flex items-center justify-center shadow transition-all cursor-pointer" title="Tambah Alut ke Daftar">
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
        <textarea name="alut_text" id="alut_text" class="hidden"><?= e($laporan['alut_text'] ?? '') ?></textarea>
      </div>

    </div>

    <!-- Bottom Action Bar (Simpan / Batal) -->
    <div class="cockpit-glass sticky bottom-4 z-20 p-4 sm:p-5 rounded-2xl shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4 transition-colors">
      <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <span>Status Siklus:</span>
        <span class="font-bold font-mono text-blue-600 dark:text-blue-400"><?= e($hariKe) ?> Terintegrasi</span>
      </div>

      <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
        <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $idOperasi ?>&tab=sitrep" class="btn-tactile px-5 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 font-bold text-xs transition cursor-pointer">
          Batal
        </a>
        <button type="submit" id="btn-submit-sitrep" class="btn-tactile px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-md transition cursor-pointer flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span><?= $editMode ? 'Simpan Perubahan LAPBANG' : 'Terbitkan Laporan Perkembangan (LAPBANG)' ?></span>
        </button>
      </div>
    </div>

  </form>
</div>

<!-- Modal Pratinjau WhatsApp LAPBANG -->
<div id="modal-preview-sitrep-wa" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs hidden">
  <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 w-full max-w-2xl overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
    <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pratinjau Format WhatsApp LAPBANG</h3>
      </div>
      <button type="button" onclick="closeModal('modal-preview-sitrep-wa')" aria-label="Tutup" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div class="p-5 overflow-y-auto flex-1 font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap select-all leading-relaxed bg-slate-50 dark:bg-slate-950" id="sitrep-wa-preview-content"></div>
    <div class="p-4 border-t border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900 flex items-center justify-between">
      <span class="text-xs text-slate-400">Siap dikirim ke grup komando</span>
      <div class="flex items-center gap-2">
        <button type="button" onclick="copySitrepWa()" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs transition cursor-pointer flex items-center gap-1.5">
          <span></span>
          <span>Salin Teks WA</span>
        </button>
        <button type="button" onclick="closeModal('modal-preview-sitrep-wa')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs">
          Tutup
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Asisten AI LAPBANG -->
<div id="modal-ai-sitrep" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-6xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-purple-500/15 text-purple-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Asisten AI Laporan Perkembangan (LAPBANG)</h3>
          <p class="text-[11px] text-slate-400">Menyusun ulang hasil operasi harian menjadi narasi resmi standar Basarnas</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-ai-sitrep')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
        ✕
      </button>
    </div>

    <div class="p-6 overflow-y-auto grid md:grid-cols-2 gap-5">
      <div>
        <div class="field">
          <label class="field-label">Catatan / Catatan Mentah Lapangan:</label>
          <textarea id="ai-sitrep-draft-input" rows="14" style="min-height: 20rem;" placeholder="Tuliskan catatan hasil pencarian hari ini (contoh: tim sru 1 menyisir 5 mil laut, ombak tinggi, hasil sampai sore masih nihil, besok lanjut jam 7 pagi...)" class="field-input w-full"></textarea>
        </div>
        <p class="text-[11px] text-purple-600 dark:text-purple-400 mt-1">Asisten AI akan merangkai kalimat menjadi narasi resmi laporan perkembangan harian Basarnas.</p>

        <div class="flex justify-end mt-3">
        <button type="button" id="btn-generate-ai-sitrep" onclick="generateAiSitrep()" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-sm transition cursor-pointer">
          Susun Narasi Resmi
        </button>
        </div>
      </div>

      <div>
        <div class="field">
          <label class="field-label">Hasil Narasi Resmi Basarnas:</label>
          <textarea id="ai-sitrep-result-output" rows="14" style="min-height: 20rem;" placeholder="Hasil olahan narasi AI akan muncul di sini..." class="field-input w-full leading-relaxed font-mono"></textarea>
        </div>
      </div>
    </div>

    <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
      <button type="button" onclick="closeModal('modal-ai-sitrep')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
        Batal
      </button>
      <button type="button" onclick="applyAiSitrep()" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs shadow-md transition cursor-pointer">
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
<script src="<?= assetUrl('assets/js/alut_kategori.js') ?>"></script>
<script src="<?= assetUrl('assets/js/pages/sitrep_form.js') ?>"></script>
