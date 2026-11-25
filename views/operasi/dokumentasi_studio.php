<?php
/**
 * QR-SAR - Studio Pembuat & Editor Kolase Dokumentasi (Foto & Video)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Fitur:
 * - Layout laptop fokus pratinjau (>= 75% layar) dengan panel form kiri ringkas tanpa sub-teks
 * - Kontrol slot kontekstual terpadu anti-overflow untuk mobile & desktop + fitur Trim video maks 3 menit
 * - Auto-draft IndexedDB (tahan refresh halaman), chunked upload 4 MB (lolos batas Cloudflare/PHP),
 *   render video FFmpeg server-side hingga 150 MB, dan 1 proses loading terpadu (0% -> 100%)
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$idOps = (isset($operasi) && is_array($operasi) && !empty($operasi['id_operasi'])) ? (int)$operasi['id_operasi'] : 0;
$allowOperasiSelect = !empty($allowOperasiSelect);
$activeOperasiOptions = (isset($activeOperasiOptions) && is_array($activeOperasiOptions)) ? $activeOperasiOptions : [];
$generalDefaults = (isset($generalDefaults) && is_array($generalDefaults))
    ? $generalDefaults
    : ['hariLabel' => 'KEGIATAN', 'judulDefault' => 'Dokumentasi Kegiatan KPP Kelas A Kendari'];

$backDetailUrl = (isset($backDetailUrl) && trim((string)$backDetailUrl) !== '')
    ? (string)$backDetailUrl
    : ($idOps > 0 ? BASE_URL . '/operasi_detail.php?id=' . $idOps . '&tab=dokumentasi' : BASE_URL . '/dokumentasi.php');
$backDetailLabel = (isset($backDetailLabel) && trim((string)$backDetailLabel) !== '')
    ? (string)$backDetailLabel
    : ($idOps > 0 ? 'Kembali ke Detail Operasi SAR' : 'Kembali ke Arsip Dokumentasi');
$newStudioUrl = (isset($newStudioUrl) && trim((string)$newStudioUrl) !== '')
    ? (string)$newStudioUrl
    : ($idOps > 0 && !$allowOperasiSelect ? BASE_URL . '/operasi.php?action=dokumentasi&id=' . $idOps : BASE_URL . '/dokumentasi.php?action=create');
$apiEndpoint = (isset($apiEndpoint) && trim((string)$apiEndpoint) !== '')
    ? (string)$apiEndpoint
    : ($idOps > 0 && !$allowOperasiSelect ? BASE_URL . '/api/index.php?path=/operasi/' . $idOps . '/dokumentasi' : BASE_URL . '/api/index.php?path=/dokumentasi');
$sourceApiEndpoint = (isset($sourceApiEndpoint) && trim((string)$sourceApiEndpoint) !== '')
    ? (string)$sourceApiEndpoint
    : ($idOps > 0 && !$allowOperasiSelect ? BASE_URL . '/api/index.php?path=/operasi/' . $idOps . '/dokumentasi/source' : BASE_URL . '/api/index.php?path=/dokumentasi/source');

$uploadApiBase = BASE_URL . '/api/index.php?path=/dokumentasi/upload';
$renderApiEndpoint = BASE_URL . '/api/index.php?path=/dokumentasi/render';
$jobsApiBase = BASE_URL . '/api/index.php?path=/dokumentasi/jobs';
$studioCapabilities = class_exists('DokumentasiVideoService')
    ? DokumentasiVideoService::getCapabilities()
    : [
        'server_render' => false,
        'chunk_size_bytes' => 4194304,
        'max_final_bytes' => 157286400,
        'max_video_duration_seconds' => 180,
        'source_size_limit_bytes' => null,
    ];

$editDok = (isset($editDok) && is_array($editDok)) ? $editDok : null;
$editProject = (isset($editProject) && is_array($editProject)) ? $editProject : null;
$isEditMode = ($editDok !== null && !empty($editDok['id_dokumentasi']));
$editDocId = $isEditMode ? (int)$editDok['id_dokumentasi'] : 0;

$pHeader = ($isEditMode && isset($editProject['header']) && is_array($editProject['header'])) ? $editProject['header'] : [];
$pFooter = ($isEditMode && isset($editProject['footer']) && is_array($editProject['footer'])) ? $editProject['footer'] : [];
$pLogos = ($isEditMode && isset($editProject['logos']) && is_array($editProject['logos'])) ? $editProject['logos'] : [];

$satkerNama = trim((string)($pHeader['nama'] ?? ($settings['satker_nama'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI')));
$satkerAlamat = trim((string)($pHeader['alamat'] ?? ($settings['alamat'] ?? 'Jl. Kapten Pierre Tendean, Kota Kendari-Sulawesi Tenggara 93116')));
$satkerTelp = trim((string)($pHeader['telp'] ?? ($settings['no_telp'] ?? '0401 3196 557')));
$satkerWa = trim((string)($pHeader['wa'] ?? ($settings['no_wa'] ?? '')));
$satkerEmail = trim((string)($pHeader['email'] ?? ($settings['email'] ?? 'sar.kendari@basarnas.go.id')));

$footerWeb = trim((string)($pFooter['web'] ?? 'www.kendari.basarnas.go.id'));
$footerIg = trim((string)($pFooter['ig'] ?? 'kantorsar_kendari'));
$footerFb = trim((string)($pFooter['fb'] ?? 'Basarnas Kendari'));
$footerX = trim((string)($pFooter['x'] ?? 'basarnaskendari'));

$showLogo1 = !array_key_exists('logo1', $pLogos) || (bool)$pLogos['logo1'];
$showLogo2 = !array_key_exists('logo2', $pLogos) || (bool)$pLogos['logo2'];
$showLogo3 = !array_key_exists('logo3', $pLogos) || (bool)$pLogos['logo3'];

$editDocPayload = null;
if ($isEditMode) {
    $editDocPayload = [
        'id' => $editDocId,
        'judul' => (string)($editDok['judul'] ?? ''),
        'hari_ke' => (string)($editDok['hari_ke'] ?? $hariLabel),
        'tanggal_label' => (string)($editDok['tanggal_label'] ?? $tanggalDefault),
        'aspect_ratio' => (string)($editDok['aspect_ratio'] ?? '2:3'),
        'orientation' => (string)($editDok['orientation'] ?? 'vertical'),
        'slot_count' => (int)($editDok['slot_count'] ?? 6),
        'layout_preset' => (string)($editDok['layout_preset'] ?? 'v_6_2x3'),
        'media_type' => (string)($editDok['media_type'] ?? 'image'),
        'file_url' => !empty($editDok['file_path']) ? BASE_URL . '/' . ltrim((string)$editDok['file_path'], '/') : '',
        'project' => $editProject,
    ];
}
?>

<style>
  .dok-studio-shell {
    width: 100%;
    max-width: 100%;
  }
  .dok-studio-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 0.875rem;
    align-items: start;
  }
  @media (min-width: 1024px) {
    .dok-studio-grid {
      /* Panel form <= 23% (maks 290px), Panggung pratinjau >= 76% pada layar laptop */
      grid-template-columns: minmax(226px, min(23%, 290px)) minmax(0, 1fr);
      gap: 0.75rem;
    }
  }
  .dok-studio-card { background-color: var(--bg-card); border: 1px solid var(--border-color); }
  .dok-chip { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); transition: all 140ms ease; }
  .dok-chip:hover { border-color: color-mix(in srgb, var(--primary) 45%, var(--border-color)); }
  .dok-chip[data-active="true"] { background: var(--primary); border-color: var(--primary); color: #ffffff; font-weight: 700; }
  .dok-layout-btn { border: 1.5px solid var(--border-color); background: var(--bg-card); transition: all 140ms ease; }
  .dok-layout-btn:hover { border-color: color-mix(in srgb, var(--primary) 50%, var(--border-color)); }
  .dok-layout-btn[data-active="true"] { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 10%, var(--bg-card)); }
  .dok-tab-btn {
    border-bottom: 2px solid transparent;
    color: var(--text-muted);
    transition: color 140ms ease, border-color 140ms ease, background-color 140ms ease;
  }
  .dok-tab-btn:hover { color: var(--text-main); }
  .dok-tab-btn[data-active="true"] {
    color: var(--primary);
    border-bottom-color: var(--primary);
    background-color: color-mix(in srgb, var(--primary) 8%, transparent);
    font-weight: 700;
  }
  .dok-dropzone { border: 2px dashed color-mix(in srgb, var(--primary) 42%, var(--border-color)); background: color-mix(in srgb, var(--primary) 5%, var(--bg-card)); transition: all 160ms ease; }
  .dok-dropzone.is-dragover { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 12%, var(--bg-card)); }
  .dok-canvas-stage {
    background-image: radial-gradient(circle at 1px 1px, rgba(148, 163, 184, 0.22) 1px, transparent 0);
    background-size: 18px 18px;
  }
  .dok-ov-slot {
    position: absolute;
    pointer-events: auto;
    border-radius: 10px;
    overflow: hidden;
    transition: box-shadow 140ms ease, background-color 140ms ease;
    touch-action: none;
  }
  .dok-ov-slot[data-filled="true"] { cursor: grab; }
  .dok-ov-slot[data-filled="true"].is-panning { cursor: grabbing; }
  .dok-ov-slot[data-filled="false"] { cursor: pointer; }
  .dok-ov-slot[data-filled="false"]:hover { background-color: rgba(234, 88, 12, 0.08); box-shadow: inset 0 0 0 2px rgba(234, 88, 12, 0.55); }
  .dok-ov-slot[data-selected="true"] { box-shadow: inset 0 0 0 2.5px #ea580c; }
  .dok-ov-slot[data-swap-source="true"] { box-shadow: inset 0 0 0 3px #0284c7 !important; background-color: rgba(2, 132, 199, 0.16) !important; }
  .dok-ov-slot[data-swap-ready="true"]:hover { box-shadow: inset 0 0 0 3px #10b981 !important; background-color: rgba(16, 185, 129, 0.16) !important; cursor: pointer; }
  .dok-ov-slot[data-bulk-checked="true"] { box-shadow: inset 0 0 0 3px #e11d48; background-color: rgba(225, 29, 72, 0.14); }
  .dok-ov-slot.is-drop-target { box-shadow: inset 0 0 0 3px #0ea5e9 !important; background-color: rgba(14, 165, 233, 0.16) !important; }
  .dok-ov-badge {
    background: rgba(15, 23, 42, 0.78);
    color: #ffffff;
    backdrop-filter: blur(4px);
    line-height: 1;
  }
  .dok-trim-range {
    -webkit-appearance: none;
    appearance: none;
    width: 100%;
    height: 6px;
    border-radius: 9999px;
    background: #334155;
    outline: none;
  }
  .dok-trim-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 9999px;
    background: #f97316;
    border: 2px solid #ffffff;
    cursor: pointer;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
  }
</style>

<div class="dok-studio-shell space-y-3">
  <!-- Studio Header Bar (Ringkas & Anti-Overflow pada Mobile) -->
  <section class="dok-studio-card rounded-2xl px-3.5 sm:px-4 py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
    <div class="flex items-center gap-2.5 min-w-0">
      <a href="<?= e($backDetailUrl) ?>" class="inline-flex items-center justify-center w-8 h-8 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition shrink-0" title="<?= e($backDetailLabel) ?>" aria-label="<?= e($backDetailLabel) ?>">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      </a>
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-1.5 text-[10.5px] font-mono text-slate-500 dark:text-slate-400">
          <?php if ($isEditMode): ?>
            <span class="px-1.5 py-0.5 rounded bg-sky-500/15 text-sky-700 dark:text-sky-300 font-bold">EDIT #<?= $editDocId ?></span>
          <?php endif; ?>
          <span id="studioHeaderScopeLabel" class="font-semibold text-orange-700 dark:text-orange-400"><?= $idOps > 0 ? 'Operasi #' . $idOps : 'Dokumentasi Umum' ?></span>
          <span>&bull;</span>
          <span id="studioHeaderHariLabel"><?= e($hariLabel) ?></span>
          <span id="draftRestoredBadge" class="hidden px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-bold">DRAF PULIH</span>
        </div>
        <h1 id="studioHeaderTitle" class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate">
          <?= e($idOps > 0 ? (string)($operasi['nama_operasi'] ?? '') : 'Studio Dokumentasi KPP Kendari') ?>
        </h1>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-1.5 w-full sm:w-auto sm:justify-end">
      <?php if ($isEditMode): ?>
        <a href="<?= e($newStudioUrl) ?>" class="inline-flex items-center justify-center h-8 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition no-underline">
          Baru
        </a>
      <?php endif; ?>

      <button type="button" id="btnTopDownloadImg" class="inline-flex items-center justify-center gap-1.5 h-8 px-2.5 sm:px-3 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-white text-xs font-semibold transition cursor-pointer">
        <svg class="w-3.5 h-3.5 text-sky-600 dark:text-sky-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span>Foto</span>
      </button>

      <button type="button" id="btnTopDownloadVid" class="hidden inline-flex items-center justify-center gap-1.5 h-8 px-2.5 sm:px-3 rounded-lg border border-orange-500/40 bg-orange-500/10 hover:bg-orange-500/20 text-orange-700 dark:text-orange-300 text-xs font-bold transition cursor-pointer">
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        <span>MP4</span>
      </button>

      <button type="button" id="btnSaveArchive" class="inline-flex items-center justify-center gap-1.5 h-8 px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition cursor-pointer shadow-xs flex-1 sm:flex-initial">
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
        <span><?= $isEditMode ? 'Simpan Perubahan' : 'Simpan Arsip' ?></span>
      </button>
    </div>
  </section>

  <!-- Main Studio Workbench: <= 24% Compact Left Form | >= 76% Preview Focus on Laptop -->
  <div class="dok-studio-grid">

    <!-- LEFT PANEL: Ultra-Clean Compact Tabbed Controls (No Explanatory Sub-Texts) -->
    <aside class="dok-studio-card rounded-2xl overflow-hidden lg:sticky lg:top-20">
      <!-- 4-Tab Studio Inspector Navigation -->
      <div class="grid grid-cols-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40" role="tablist" aria-label="Pengaturan Kolase">
        <button type="button" role="tab" class="dok-tab-btn h-9 px-1 text-[11px] font-semibold cursor-pointer truncate" data-studio-tab="teks" data-active="true">Teks</button>
        <button type="button" role="tab" class="dok-tab-btn h-9 px-1 text-[11px] font-semibold cursor-pointer truncate" data-studio-tab="desain" data-active="false">Desain</button>
        <button type="button" role="tab" class="dok-tab-btn h-9 px-1 text-[11px] font-semibold cursor-pointer truncate" data-studio-tab="grid" data-active="false">Grid</button>
        <button type="button" role="tab" class="dok-tab-btn h-9 px-1 text-[11px] font-semibold cursor-pointer flex items-center justify-center gap-0.5 min-w-0" data-studio-tab="media" data-active="false">
          <span class="truncate">Media</span>
          <span id="filledSlotsBadge" class="font-mono text-[9.5px] px-1 rounded bg-slate-200/80 dark:bg-slate-800 text-slate-700 dark:text-slate-300 shrink-0">0/6</span>
        </button>
      </div>

      <!-- TAB 1: Teks & Identitas -->
      <div data-studio-panel="teks" class="p-3 space-y-2.5">
        <?php if ($allowOperasiSelect): ?>
          <div>
            <label for="selectDokOperasi" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Operasi SAR</label>
            <select id="selectDokOperasi" class="control control-sm w-full text-xs font-semibold cursor-pointer">
              <option value="0" <?= $idOps === 0 ? 'selected' : '' ?>>Dokumentasi Umum</option>
              <?php foreach ($activeOperasiOptions as $opt): ?>
                <option value="<?= (int)$opt['id'] ?>" <?= $idOps === (int)$opt['id'] ? 'selected' : '' ?>>
                  <?= (($opt['status'] ?? 'OPEN') !== 'OPEN') ? '[SELESAI] ' : '[AKTIF] ' ?>OPS #<?= (int)$opt['id'] ?> - <?= e((string)$opt['nama']) ?> (<?= e((string)$opt['hariLabel']) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

        <div class="space-y-2">
          <div>
            <label id="labelInputDokHari" for="inputDokHari" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1"><?= $idOps > 0 ? 'Hari' : 'Kategori' ?></label>
            <input type="text" id="inputDokHari" value="<?= e($hariLabel) ?>" class="control control-sm w-full font-mono font-bold text-xs" placeholder="<?= $idOps > 0 ? 'H1' : 'KEGIATAN' ?>" />
          </div>
          <div>
            <label for="inputDokTanggal" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Tanggal</label>
            <input type="text" id="inputDokTanggal" value="<?= e($tanggalDefault) ?>" class="control control-sm w-full font-semibold text-xs" placeholder="Kamis, 12 November 2026" />
          </div>
        </div>

        <div>
          <label id="labelInputDokJudul" for="inputDokJudul" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Judul Dokumentasi</label>
          <textarea id="inputDokJudul" rows="3" class="control control-sm w-full font-semibold text-xs leading-snug" placeholder="<?= $idOps > 0 ? 'Dok Operasi SAR H1 ...' : 'Dokumentasi Kegiatan KPP Kelas A Kendari ...' ?>"><?= e($judulDefault) ?></textarea>
        </div>

        <!-- Logo Toggles -->
        <div>
          <span class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Logo Kop</span>
          <div class="flex flex-wrap items-center gap-1.5">
            <label class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-800 text-[11px] font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" id="chkShowLogo1" <?= $showLogo1 ? 'checked' : '' ?> class="rounded accent-orange-600" />
              <span>Basarnas</span>
            </label>
            <label class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-800 text-[11px] font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" id="chkShowLogo2" <?= $showLogo2 ? 'checked' : '' ?> class="rounded accent-orange-600" />
              <span>SAR Nas</span>
            </label>
            <label class="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border border-slate-200 dark:border-slate-800 text-[11px] font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
              <input type="checkbox" id="chkShowLogo3" <?= $showLogo3 ? 'checked' : '' ?> class="rounded accent-orange-600" />
              <span>ANOA</span>
            </label>
          </div>
        </div>

        <!-- Collapsible Kop & Footer -->
        <details class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 px-3 py-2">
          <summary class="text-[11px] font-semibold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
            Identitas Kop &amp; Footer
          </summary>
          <div class="pt-2.5 space-y-2 text-xs">
            <div>
              <label for="inputHeaderNama" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Kantor SAR</label>
              <input type="text" id="inputHeaderNama" value="<?= e(mb_strtoupper($satkerNama, 'UTF-8')) ?>" class="control control-sm w-full text-xs" />
            </div>
            <div>
              <label for="inputHeaderAlamat" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Alamat</label>
              <input type="text" id="inputHeaderAlamat" value="<?= e($satkerAlamat) ?>" class="control control-sm w-full text-xs" />
            </div>
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label for="inputHeaderTelp" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Telepon</label>
                <input type="text" id="inputHeaderTelp" value="<?= e($satkerTelp) ?>" class="control control-sm w-full text-xs" />
              </div>
              <div>
                <label for="inputHeaderWa" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">WhatsApp</label>
                <input type="text" id="inputHeaderWa" value="<?= e($satkerWa) ?>" class="control control-sm w-full font-mono text-xs" />
              </div>
            </div>
            <div>
              <label for="inputHeaderEmail" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Email</label>
              <input type="text" id="inputHeaderEmail" value="<?= e($satkerEmail) ?>" class="control control-sm w-full text-xs" />
            </div>
            <div class="grid grid-cols-2 gap-2 pt-1.5 border-t border-slate-200 dark:border-slate-800">
              <div>
                <label for="inputFooterWeb" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Website</label>
                <input type="text" id="inputFooterWeb" value="<?= e($footerWeb) ?>" class="control control-sm w-full text-xs" />
              </div>
              <div>
                <label for="inputFooterIg" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Instagram</label>
                <input type="text" id="inputFooterIg" value="<?= e($footerIg) ?>" class="control control-sm w-full text-xs" />
              </div>
              <div>
                <label for="inputFooterFb" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">Facebook</label>
                <input type="text" id="inputFooterFb" value="<?= e($footerFb) ?>" class="control control-sm w-full text-xs" />
              </div>
              <div>
                <label for="inputFooterX" class="block text-[10.5px] font-semibold text-slate-500 dark:text-slate-400 mb-0.5">X / Twitter</label>
                <input type="text" id="inputFooterX" value="<?= e($footerX) ?>" class="control control-sm w-full text-xs" />
              </div>
            </div>
          </div>
        </details>
      </div>

      <!-- TAB 2: Gaya, Kanvas & Kualitas -->
      <div data-studio-panel="desain" class="hidden p-3.5 space-y-3">
        <div>
          <span class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Gaya Desain</span>
          <div id="stylePresetGrid" class="grid grid-cols-2 gap-1.5">
            <button type="button" class="dok-layout-btn px-2.5 py-2 rounded-xl text-left cursor-pointer flex items-center gap-2" data-style-id="klasik_kpp" data-active="true">
              <span class="w-3 h-3 rounded-full bg-orange-600 shrink-0 ring-2 ring-orange-200 dark:ring-orange-900"></span>
              <span class="text-[11px] font-bold text-slate-900 dark:text-white leading-tight">Klasik KPP</span>
            </button>
            <button type="button" class="dok-layout-btn px-2.5 py-2 rounded-xl text-left cursor-pointer flex items-center gap-2" data-style-id="editorial_modern" data-active="false">
              <span class="w-3 h-3 rounded-full bg-slate-900 dark:bg-white shrink-0 ring-2 ring-orange-500/70"></span>
              <span class="text-[11px] font-bold text-slate-900 dark:text-white leading-tight">Editorial</span>
            </button>
          </div>
        </div>

        <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Orientasi</span>
            <span id="activeCanvasDimBadge" class="font-mono text-[10.5px] font-semibold text-slate-500 dark:text-slate-400">1620 &times; 2430 px</span>
          </div>
          <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80">
            <button type="button" class="dok-orient-tab h-7 rounded-lg text-[11px] font-semibold transition cursor-pointer" data-orient="vertical" data-active="true">Vertikal</button>
            <button type="button" class="dok-orient-tab h-7 rounded-lg text-[11px] font-semibold transition cursor-pointer" data-orient="horizontal" data-active="false">Horizontal</button>
            <button type="button" class="dok-orient-tab h-7 rounded-lg text-[11px] font-semibold transition cursor-pointer" data-orient="square" data-active="false">Persegi</button>
          </div>
          <div id="canvasPresetGrid" class="grid grid-cols-2 gap-1.5"></div>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-100 dark:border-slate-800">
          <div>
            <label for="selectExportQuality" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Kualitas</label>
            <select id="selectExportQuality" class="control control-sm w-full text-xs font-semibold cursor-pointer">
              <option value="fhd">Full HD (1080p)</option>
              <option value="qhd" selected>2K QHD</option>
              <option value="uhd_4k">4K UHD</option>
            </select>
          </div>
          <div id="wrapSelectImageFormat">
            <label for="selectImageFormat" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Format Foto</label>
            <select id="selectImageFormat" class="control control-sm w-full text-xs font-semibold cursor-pointer">
              <option value="image/jpeg" selected>JPG</option>
              <option value="image/png">PNG</option>
            </select>
          </div>
        </div>
      </div>

      <!-- TAB 3: Jumlah Slot & Pola Grid -->
      <div data-studio-panel="grid" class="hidden p-3.5 space-y-3">
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400">Jumlah Slot</span>
            <span class="text-[10.5px] font-mono font-semibold text-orange-700 dark:text-orange-400">4 &ndash; 12</span>
          </div>
          <div id="slotCountBar" class="grid grid-cols-5 gap-1.5">
            <?php for ($n = 4; $n <= 12; $n++): ?>
              <button type="button"
                      class="dok-chip h-8 rounded-lg text-xs font-mono font-semibold cursor-pointer"
                      data-slot-count="<?= $n ?>"
                      data-active="<?= $n === 6 ? 'true' : 'false' ?>">
                <?= $n ?>
              </button>
            <?php endfor; ?>
          </div>
        </div>

        <div class="space-y-1.5 pt-2 border-t border-slate-100 dark:border-slate-800">
          <span class="text-[11px] font-semibold text-slate-600 dark:text-slate-400 block">Tata Letak</span>
          <div id="layoutVariantGrid" class="grid grid-cols-1 gap-1.5"></div>
        </div>
      </div>

      <!-- TAB 4: Unggah Media -->
      <div data-studio-panel="media" class="hidden p-3.5 space-y-3">
        <label id="bulkMediaDropzone" class="dok-dropzone rounded-xl p-4 flex flex-col items-center justify-center text-center gap-1.5 cursor-pointer">
          <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
          <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Pilih Foto / Video</span>
          <input type="file" id="bulkMediaInput" accept="image/*,video/*" multiple class="hidden" />
        </label>

        <div class="flex items-center justify-between gap-2 pt-1">
          <span id="filledSlotsSummaryText" class="text-[11px] font-mono font-semibold text-slate-600 dark:text-slate-400">0 / 6 slot terisi</span>
          <button type="button" id="btnClearAllSlots" class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:underline cursor-pointer">Hapus Semua</button>
        </div>
      </div>

      <!-- Hidden Single Slot Replace Input -->
      <input type="file" id="singleSlotInput" accept="image/*,video/*" class="hidden" />
    </aside>

    <!-- RIGHT PANEL: Dominant Live Interactive Preview Stage (>= 76% Laptop Width) -->
    <section class="dok-studio-card rounded-2xl overflow-hidden min-w-0">
      <!-- Compact Preview Status & Quick Toolbar -->
      <div class="px-3.5 py-2 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/40 flex flex-wrap items-center justify-between gap-2 text-xs">
        <div class="flex flex-wrap items-center gap-1.5 min-w-0">
          <span id="previewMetaPill" class="px-2 py-0.5 rounded-md bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[11px] font-semibold">2:3 &bull; 6 Slot &bull; 2K</span>
          <span id="previewModeBadge" class="px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider">FOTO</span>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
          <button type="button" id="btnQuickAddMedia" class="inline-flex items-center gap-1 h-7 px-2.5 rounded-lg bg-orange-500/10 hover:bg-orange-500/20 text-orange-700 dark:text-orange-300 border border-orange-500/30 text-[11px] font-bold transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
            <span>Media</span>
          </button>
          <button type="button" id="btnToggleBulkMode" class="hidden inline-flex items-center gap-1 h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-semibold transition cursor-pointer">
            <span>Pilih Banyak</span>
          </button>
        </div>
      </div>

      <!-- Single Unified Loading Progress Bar (0% -> 100% Monotonic) -->
      <div id="exportProgressWrap" class="hidden px-3.5 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-slate-900 text-white space-y-1.5">
        <div class="flex items-center justify-between gap-2 text-xs font-semibold">
          <span id="exportProgressLabel" class="truncate">Menyimpan dokumentasi...</span>
          <div class="flex items-center gap-2 shrink-0">
            <span id="exportProgressPct" class="font-mono text-orange-400">0%</span>
            <button type="button" id="btnCancelExport" class="h-6 px-2 rounded-md border border-slate-600 hover:border-rose-400 hover:text-rose-300 text-[10.5px] font-semibold text-slate-300 transition cursor-pointer">
              Batal
            </button>
          </div>
        </div>
        <div class="h-1.5 w-full rounded-full bg-slate-700 overflow-hidden">
          <div id="exportProgressBar" class="h-full bg-orange-500 transition-all duration-150" style="width: 0%"></div>
        </div>
      </div>

      <!-- Contextual Bulk Delete Action Bar -->
      <div id="previewBulkBar" class="hidden px-3.5 py-2 border-b border-rose-200 dark:border-rose-900/60 bg-rose-50/90 dark:bg-rose-950/40 flex flex-wrap items-center justify-between gap-2 text-xs">
        <span id="bulkSelectedCountLabel" class="font-bold text-rose-700 dark:text-rose-300">0 slot dipilih</span>
        <div class="flex flex-wrap items-center gap-1.5">
          <button type="button" id="btnBulkSelectAll" class="h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 text-[11px] font-semibold hover:bg-slate-100 cursor-pointer">Pilih Semua</button>
          <button type="button" id="btnBulkDeleteSelected" class="h-7 px-2.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-bold cursor-pointer">Hapus Terpilih</button>
          <button type="button" id="btnBulkCancel" class="h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 text-[11px] font-semibold hover:bg-slate-100 cursor-pointer">Batal</button>
        </div>
      </div>

      <!-- Video Playback & Max 3-Minute Duration Strip (Auto-visible when >= 1 video slot) -->
      <div id="videoPlaybackBar" class="hidden px-3.5 py-2 border-b border-slate-200/80 dark:border-slate-800 bg-orange-500/5 flex flex-wrap items-center justify-between gap-2 text-xs">
        <div class="flex flex-wrap items-center gap-2">
          <button type="button" id="btnToggleVideoPlay" class="inline-flex items-center gap-1.5 h-7 px-3 rounded-lg bg-orange-700 hover:bg-orange-800 text-white font-semibold text-[11px] cursor-pointer transition">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
            <span id="labelToggleVideoPlay">Putar</span>
          </button>
          <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-700 dark:text-slate-300">
            <span>Durasi</span>
            <select id="selectVideoDuration" class="h-7 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-[11px] font-semibold cursor-pointer">
              <option value="auto" selected>Otomatis (maks 3 mnt)</option>
              <option value="15">15 detik</option>
              <option value="30">30 detik</option>
              <option value="60">1 menit</option>
              <option value="120">2 menit</option>
              <option value="180">3 menit</option>
            </select>
          </label>
        </div>
        <span id="videoEffectiveDurPill" class="font-mono text-[11px] font-semibold text-slate-600 dark:text-slate-300">00:00 / 03:00</span>
      </div>

      <!-- Contextual Selected Slot Action Bar (100% Inside Card — Fixes Mobile Button Overflow) -->
      <div id="slotActionBar" class="hidden px-3 py-2 border-b border-slate-200/80 dark:border-slate-800 bg-white dark:bg-slate-900/90 flex flex-wrap items-center justify-between gap-1.5 text-xs">
        <div class="flex items-center gap-1.5 min-w-0">
          <span id="slotBarBadge" class="px-1.5 py-0.5 rounded bg-orange-600 text-white font-mono text-[10.5px] font-bold shrink-0">#1</span>
          <span id="slotBarName" class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 truncate max-w-[130px] sm:max-w-[200px]">Media</span>
          <span id="slotBarTrimPill" class="hidden px-1.5 py-0.5 rounded bg-sky-500/15 text-sky-700 dark:text-sky-300 font-mono text-[10px] font-bold shrink-0"></span>
        </div>

        <div class="flex flex-wrap items-center gap-1 max-w-full">
          <button type="button" id="btnSlotReplace" class="inline-flex items-center gap-1 h-7 px-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-semibold cursor-pointer">
            Ganti
          </button>

          <div class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 overflow-hidden">
            <button type="button" id="btnSlotZoomOut" class="h-7 px-2 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-mono text-xs font-bold cursor-pointer" title="Perkecil">&minus;</button>
            <button type="button" id="btnSlotZoomFit" class="h-7 px-2 border-x border-slate-300 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-semibold cursor-pointer" title="Reset Fit">Fit</button>
            <button type="button" id="btnSlotZoomIn" class="h-7 px-2 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-mono text-xs font-bold cursor-pointer" title="Perbesar">+</button>
          </div>

          <button type="button" id="btnSlotSwap" class="inline-flex items-center gap-1 h-7 px-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-sky-50 dark:hover:bg-sky-950/50 text-slate-700 dark:text-slate-200 text-[11px] font-semibold cursor-pointer">
            Tukar
          </button>

          <button type="button" id="btnSlotMute" class="hidden inline-flex items-center gap-1 h-7 px-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-semibold cursor-pointer">
            Suara
          </button>

          <button type="button" id="btnSlotTrim" class="hidden inline-flex items-center gap-1 h-7 px-2 rounded-lg border border-orange-500/40 bg-orange-500/10 hover:bg-orange-500/20 text-orange-700 dark:text-orange-300 text-[11px] font-bold cursor-pointer">
            Trim
          </button>

          <button type="button" id="btnSlotDelete" class="inline-flex items-center justify-center h-7 px-2 rounded-lg border border-rose-300 dark:border-rose-900 bg-rose-50 dark:bg-rose-950/40 hover:bg-rose-600 hover:text-white text-rose-600 dark:text-rose-400 text-[11px] font-semibold cursor-pointer">
            Hapus
          </button>
        </div>
      </div>

      <!-- Swap Mode Notice Strip -->
      <div id="swapModeBar" class="hidden px-3.5 py-1.5 border-b border-sky-200 dark:border-sky-900 bg-sky-50 dark:bg-sky-950/40 flex items-center justify-between gap-2 text-[11px]">
        <span id="swapModeLabel" class="font-semibold text-sky-800 dark:text-sky-200">Pilih slot tujuan untuk ditukar</span>
        <button type="button" id="btnCancelSwap" class="px-2 py-0.5 rounded bg-white dark:bg-slate-900 border border-sky-300 dark:border-sky-800 text-sky-700 dark:text-sky-300 font-semibold cursor-pointer">Batal</button>
      </div>

      <!-- Interactive Canvas Viewport -->
      <div class="dok-canvas-stage p-3 sm:p-5 bg-slate-100/90 dark:bg-slate-950/90 flex flex-col items-center justify-center min-h-[460px] lg:min-h-[74vh]">
        <div id="collageCanvasWrapper" class="relative inline-block max-w-full rounded-xl shadow-2xl overflow-hidden bg-white select-none">
          <canvas id="collagePreviewCanvas"
                  width="1080"
                  height="1620"
                  class="block max-w-full max-h-[76vh] lg:max-h-[82vh] w-auto h-auto"
                  aria-label="Kanvas pratinjau kolase dokumentasi"></canvas>
          <div id="collageInteractiveOverlay" class="absolute inset-0 pointer-events-none"></div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Video Trim Modal (Maksimal 3 Menit / 180 Detik) -->
<div id="videoTrimModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/80 backdrop-blur-sm p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="trimModalTitle">
  <div class="w-full max-w-xl bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
      <h2 id="trimModalTitle" class="text-sm font-bold text-slate-900 dark:text-white truncate">Potong Video</h2>
      <button type="button" id="btnCloseTrimModal" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" aria-label="Tutup">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <div class="p-4 space-y-3.5 overflow-y-auto">
      <div class="rounded-xl bg-slate-950 overflow-hidden flex items-center justify-center">
        <video id="trimPreviewVideo" controls playsinline class="w-full max-h-[38vh] bg-black"></video>
      </div>

      <!-- Visual Window Bar -->
      <div class="space-y-2">
        <div class="relative h-2.5 w-full rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
          <div id="trimSelectionTrack" class="absolute top-0 bottom-0 bg-orange-500 rounded-full" style="left: 0%; width: 100%;"></div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="space-y-1">
            <div class="flex items-center justify-between text-[11px] font-semibold">
              <span class="text-slate-600 dark:text-slate-400">Mulai</span>
              <span id="trimStartLabel" class="font-mono text-slate-900 dark:text-white">00:00.0</span>
            </div>
            <input type="range" id="trimStartRange" min="0" max="10" step="0.1" value="0" class="dok-trim-range" />
          </div>
          <div class="space-y-1">
            <div class="flex items-center justify-between text-[11px] font-semibold">
              <span class="text-slate-600 dark:text-slate-400">Akhir</span>
              <span id="trimEndLabel" class="font-mono text-slate-900 dark:text-white">00:10.0</span>
            </div>
            <input type="range" id="trimEndRange" min="0" max="10" step="0.1" value="10" class="dok-trim-range" />
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
        <div class="flex flex-wrap items-center gap-1.5">
          <button type="button" id="btnTrimSetStartHere" class="h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-[11px] font-semibold text-slate-700 dark:text-slate-200 cursor-pointer">Set Mulai</button>
          <button type="button" id="btnTrimSetEndHere" class="h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-[11px] font-semibold text-slate-700 dark:text-slate-200 cursor-pointer">Set Akhir</button>
          <button type="button" id="btnTrimPlaySelection" class="h-7 px-2.5 rounded-lg bg-slate-900 dark:bg-slate-700 text-white text-[11px] font-semibold cursor-pointer">Putar Potongan</button>
          <button type="button" id="btnTrimReset" class="h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-300 cursor-pointer">Reset</button>
        </div>
        <span id="trimDurationBadge" class="font-mono text-xs font-bold text-orange-600 dark:text-orange-400">00:10 / 03:00</span>
      </div>
    </div>

    <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/50 flex items-center justify-end gap-2">
      <button type="button" id="btnCancelTrim" class="h-8 px-3.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-200 cursor-pointer">Batal</button>
      <button type="button" id="btnApplyTrim" class="h-8 px-4 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold cursor-pointer">Terapkan</button>
    </div>
  </div>
</div>

<script>
window.__DOK_STUDIO_CONFIG__ = <?= jsonSafe([
    'idOperasi' => $idOps,
    'namaOperasi' => $idOps > 0 ? (string)($operasi['nama_operasi'] ?? '') : '',
    'allowOperasiSelect' => $allowOperasiSelect,
    'activeOperasiOptions' => $activeOperasiOptions,
    'generalDefaults' => $generalDefaults,
    'hariLabel' => $hariLabel,
    'tanggalDefault' => $tanggalDefault,
    'judulDefault' => $judulDefault,
    'backDetailUrl' => $backDetailUrl,
    'apiEndpoint' => $apiEndpoint,
    'sourceApiEndpoint' => $sourceApiEndpoint,
    'uploadApiBase' => $uploadApiBase,
    'renderApiEndpoint' => $renderApiEndpoint,
    'jobsApiBase' => $jobsApiBase,
    'capabilities' => $studioCapabilities,
    'editDoc' => $editDocPayload,
    'logos' => [
        'logo1' => $logo1DataUri,
        'logo2' => $logo2DataUri,
        'logo3' => $logo3DataUri,
    ],
]) ?>;
</script>
<script src="<?= assetUrl('assets/js/dokumentasi/collage_layouts.js') ?>"></script>
<script src="<?= assetUrl('assets/js/dokumentasi/studio_draft_store.js') ?>"></script>
<script src="<?= assetUrl('assets/js/dokumentasi/chunk_uploader.js') ?>"></script>
<script src="<?= assetUrl('assets/js/pages/operasi_dokumentasi.js') ?>"></script>
