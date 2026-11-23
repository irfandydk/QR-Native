<?php
/**
 * QR-SAR - Sub-Halaman Studio Pembuat & Editor Kolase Dokumentasi Operasi SAR (Foto & Video)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Fitur:
 * - Urutan kartu logis: 1. Teks Kolase & Identitas Resmi -> 2. Gaya & Kanvas -> 3. Jumlah & Pola Grid -> 4. Unggah Media Sekaligus
 * - 2 Gaya Desain (Klasik Resmi KPP & Editorial Modern)
 * - 4-12 Slot Foto/Video, ukuran Vertikal/Persegi/Horizontal
 * - Kontrol penuh langsung pada pratinjau: klik unggah/ganti, seret pan, zoom +/-/Fit,
 *   seret handle untuk reorder, mute video, hapus 1 per 1, dan bulk delete
 * - Mode Edit penuh untuk memperbarui arsip dokumentasi yang telah tersimpan
 * - Ekspor kualitas tinggi Full HD (1080p), 2K QHD, hingga 4K Ultra HD / Original
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$idOps = (int)$operasi['id_operasi'];
$backDetailUrl = BASE_URL . '/operasi_detail.php?id=' . $idOps . '&tab=dokumentasi';
$newStudioUrl = BASE_URL . '/operasi.php?action=dokumentasi&id=' . $idOps;

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

$hasSavedProjectSlots = false;
if ($isEditMode && isset($editProject['slots']) && is_array($editProject['slots'])) {
    foreach ($editProject['slots'] as $s) {
        if (is_array($s) && !empty($s['url'])) {
            $hasSavedProjectSlots = true;
            break;
        }
    }
}

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
  .dok-studio-card { background-color: var(--bg-card); border: 1px solid var(--border-color); }
  .dok-chip { border: 1px solid var(--border-color); background: var(--bg-card); color: var(--text-main); transition: all 150ms ease; }
  .dok-chip:hover { border-color: color-mix(in srgb, var(--primary) 45%, var(--border-color)); }
  .dok-chip[data-active="true"] { background: var(--primary); border-color: var(--primary); color: #ffffff; font-weight: 700; }
  .dok-layout-btn { border: 1.5px solid var(--border-color); background: var(--bg-card); transition: all 150ms ease; }
  .dok-layout-btn:hover { border-color: color-mix(in srgb, var(--primary) 50%, var(--border-color)); }
  .dok-layout-btn[data-active="true"] { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 10%, var(--bg-card)); }
  .dok-dropzone { border: 2px dashed color-mix(in srgb, var(--primary) 40%, var(--border-color)); background: color-mix(in srgb, var(--primary) 5%, var(--bg-card)); transition: all 180ms ease; }
  .dok-dropzone.is-dragover { border-color: var(--primary); background: color-mix(in srgb, var(--primary) 12%, var(--bg-card)); }
  .dok-canvas-stage {
    background-image: radial-gradient(circle at 1px 1px, rgba(148, 163, 184, 0.22) 1px, transparent 0);
    background-size: 18px 18px;
  }
  .dok-ov-slot {
    position: absolute;
    pointer-events: auto;
    border-radius: 10px;
    transition: box-shadow 140ms ease, background-color 140ms ease;
    touch-action: none;
  }
  .dok-ov-slot[data-filled="true"] { cursor: grab; }
  .dok-ov-slot[data-filled="true"].is-panning { cursor: grabbing; }
  .dok-ov-slot[data-filled="false"] { cursor: pointer; }
  .dok-ov-slot[data-filled="false"]:hover { background-color: rgba(234, 88, 12, 0.08); box-shadow: inset 0 0 0 2px rgba(234, 88, 12, 0.55); }
  .dok-ov-slot[data-selected="true"] { box-shadow: inset 0 0 0 2.5px #ea580c; }
  .dok-ov-slot[data-bulk-checked="true"] { box-shadow: inset 0 0 0 3px #e11d48; background-color: rgba(225, 29, 72, 0.12); }
  .dok-ov-slot.is-drop-target { box-shadow: inset 0 0 0 3px #0ea5e9 !important; background-color: rgba(14, 165, 233, 0.16) !important; }
  .dok-ov-controls {
    opacity: 0;
    transition: opacity 140ms ease;
  }
  .dok-ov-slot:hover .dok-ov-controls,
  .dok-ov-slot[data-selected="true"] .dok-ov-controls,
  .dok-ov-slot[data-bulk-mode="true"] .dok-ov-controls,
  .dok-ov-slot[data-bulk-checked="true"] .dok-ov-controls {
    opacity: 1;
  }
  .dok-ov-pill {
    background: rgba(15, 23, 42, 0.84);
    color: #ffffff;
    backdrop-filter: blur(4px);
  }
</style>

<div class="max-w-7xl mx-auto space-y-5">
  <!-- Studio Header Bar -->
  <section class="dok-studio-card rounded-2xl px-4 sm:px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3 min-w-0">
      <a href="<?= e($backDetailUrl) ?>" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 transition shrink-0" title="Kembali ke Detail Operasi SAR" aria-label="Kembali ke Detail Operasi SAR">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      </a>
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2 text-[11px] font-mono text-slate-500 dark:text-slate-400">
          <span class="px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-700 dark:text-orange-400 font-bold">KOLASE DOKUMENTASI</span>
          <?php if ($isEditMode): ?>
            <span class="px-1.5 py-0.5 rounded bg-sky-500/15 text-sky-700 dark:text-sky-300 font-bold">MODE EDIT ARSIP #<?= $editDocId ?></span>
          <?php endif; ?>
          <span>Operasi #<?= $idOps ?></span>
          <span>&bull;</span>
          <span><?= e($hariLabel) ?></span>
        </div>
        <h1 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white truncate mt-0.5">
          <?= e((string)$operasi['nama_operasi']) ?>
        </h1>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 shrink-0">
      <?php if ($isEditMode): ?>
        <a href="<?= e($newStudioUrl) ?>" class="inline-flex items-center gap-1.5 h-9 px-3 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold transition no-underline">
          <span>Buat Baru</span>
        </a>
      <?php endif; ?>

      <button type="button" id="btnTopDownloadImg" class="inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-white text-xs font-semibold transition cursor-pointer">
        <svg class="w-4 h-4 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span>Unduh Gambar</span>
      </button>

      <button type="button" id="btnTopDownloadVid" class="hidden inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl border border-orange-500/40 bg-orange-500/10 hover:bg-orange-500/20 text-orange-700 dark:text-orange-300 text-xs font-bold transition cursor-pointer">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        <span>Unduh Video</span>
      </button>

      <button type="button" id="btnSaveArchive" class="inline-flex items-center gap-1.5 h-9 px-4 rounded-xl bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition cursor-pointer shadow-xs">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
        <span><?= $isEditMode ? 'Simpan Perubahan Arsip' : 'Simpan ke Arsip Operasi' ?></span>
      </button>
    </div>
  </section>

  <!-- Main 2-Column Studio Workbench -->
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-5 items-start">

    <!-- LEFT PANEL: Clean Compact Form Controls (xl:col-span-5) -->
    <div class="xl:col-span-5 space-y-4">

      <!-- Card 1: Teks Kolase & Identitas Resmi -->
      <section class="dok-studio-card rounded-2xl p-4 sm:p-5 space-y-3.5">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">1. Teks Kolase &amp; Identitas Resmi</h2>
          <?php if (AuthHelper::isAdmin()): ?>
            <a href="<?= BASE_URL ?>/admin.php?tab=satker" class="text-[11px] font-semibold text-orange-700 dark:text-orange-400 hover:underline">Atur Logo &amp; WA di Pengaturan &rarr;</a>
          <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label for="inputDokHari" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Hari Operasi</label>
            <input type="text" id="inputDokHari" value="<?= e($hariLabel) ?>" class="control control-sm w-full font-mono font-bold" placeholder="H1" />
          </div>
          <div class="sm:col-span-2">
            <label for="inputDokTanggal" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Label Tanggal</label>
            <input type="text" id="inputDokTanggal" value="<?= e($tanggalDefault) ?>" class="control control-sm w-full font-semibold" placeholder="Kamis, 12 November 2026" />
          </div>
        </div>

        <div>
          <label for="inputDokJudul" class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Judul Dokumentasi Operasi SAR</label>
          <textarea id="inputDokJudul" rows="2" class="control control-sm w-full font-semibold leading-snug" placeholder="Dok Operasi SAR H1 ..."><?= e($judulDefault) ?></textarea>
        </div>

        <!-- 3 Logo Toggles -->
        <div class="flex flex-wrap items-center gap-3 pt-1">
          <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
            <input type="checkbox" id="chkShowLogo1" <?= $showLogo1 ? 'checked' : '' ?> class="rounded accent-orange-600" />
            <span>Logo Basarnas</span>
          </label>
          <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
            <input type="checkbox" id="chkShowLogo2" <?= $showLogo2 ? 'checked' : '' ?> class="rounded accent-orange-600" />
            <span>Logo SAR Nasional</span>
          </label>
          <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer">
            <input type="checkbox" id="chkShowLogo3" <?= $showLogo3 ? 'checked' : '' ?> class="rounded accent-orange-600" />
            <span>Logo ANOA Rescue</span>
          </label>
        </div>

        <!-- Collapsible Kop & Footer Details -->
        <details class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/40 px-3.5 py-2.5">
          <summary class="text-xs font-semibold text-slate-700 dark:text-slate-300 cursor-pointer select-none">
            Sesuaikan Identitas Kop, WA &amp; Media Sosial Footer (Opsional)
          </summary>
          <div class="pt-3 space-y-2.5 text-xs">
            <div>
              <label for="inputHeaderNama" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Nama Kantor SAR</label>
              <input type="text" id="inputHeaderNama" value="<?= e(mb_strtoupper($satkerNama, 'UTF-8')) ?>" class="control control-sm w-full" />
            </div>
            <div>
              <label for="inputHeaderAlamat" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Alamat Kantor</label>
              <input type="text" id="inputHeaderAlamat" value="<?= e($satkerAlamat) ?>" class="control control-sm w-full" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
              <div>
                <label for="inputHeaderTelp" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Telepon Kantor (115 / Telp)</label>
                <input type="text" id="inputHeaderTelp" value="<?= e($satkerTelp) ?>" class="control control-sm w-full" placeholder="0401 3196 557" />
              </div>
              <div>
                <label for="inputHeaderWa" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Nomor WhatsApp (WA)</label>
                <input type="text" id="inputHeaderWa" value="<?= e($satkerWa) ?>" class="control control-sm w-full font-mono" placeholder="08114034115" />
              </div>
            </div>
            <div>
              <label for="inputHeaderEmail" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Email Resmi</label>
              <input type="text" id="inputHeaderEmail" value="<?= e($satkerEmail) ?>" class="control control-sm w-full" />
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1 border-t border-slate-200 dark:border-slate-800">
              <div>
                <label for="inputFooterWeb" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Website</label>
                <input type="text" id="inputFooterWeb" value="<?= e($footerWeb) ?>" class="control control-sm w-full" />
              </div>
              <div>
                <label for="inputFooterIg" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Instagram</label>
                <input type="text" id="inputFooterIg" value="<?= e($footerIg) ?>" class="control control-sm w-full" />
              </div>
              <div>
                <label for="inputFooterFb" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">Facebook</label>
                <input type="text" id="inputFooterFb" value="<?= e($footerFb) ?>" class="control control-sm w-full" />
              </div>
              <div>
                <label for="inputFooterX" class="block text-[11px] font-semibold text-slate-500 dark:text-slate-400 mb-1">X / Twitter</label>
                <input type="text" id="inputFooterX" value="<?= e($footerX) ?>" class="control control-sm w-full" />
              </div>
            </div>
          </div>
        </details>
      </section>

      <!-- Card 2: Gaya Desain & Ukuran Kanvas -->
      <section class="dok-studio-card rounded-2xl p-4 sm:p-5 space-y-4">
        <div class="space-y-2.5">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">2. Gaya Desain &amp; Ukuran Kanvas</h2>
          <!-- 2 Style Presets (Klasik Resmi KPP vs Editorial Modern) -->
          <div id="stylePresetGrid" class="grid grid-cols-2 gap-2">
            <button type="button" class="dok-layout-btn p-2.5 rounded-xl text-left cursor-pointer" data-style-id="klasik_kpp" data-active="true">
              <div class="flex items-center gap-2 mb-1">
                <span class="w-3.5 h-3.5 rounded-full bg-orange-600 shrink-0 ring-2 ring-orange-200 dark:ring-orange-900"></span>
                <span class="text-xs font-bold text-slate-900 dark:text-white">Klasik Resmi KPP</span>
              </div>
              <p class="text-[10px] text-slate-500 dark:text-slate-400 leading-snug">Kop &amp; footer oranye Basarnas penuh standar KPP Kendari.</p>
            </button>
            <button type="button" class="dok-layout-btn p-2.5 rounded-xl text-left cursor-pointer" data-style-id="editorial_modern" data-active="false">
              <div class="flex items-center gap-2 mb-1">
                <span class="w-3.5 h-3.5 rounded-full bg-slate-900 dark:bg-white shrink-0 ring-2 ring-orange-500/70"></span>
                <span class="text-xs font-bold text-slate-900 dark:text-white">Editorial Modern</span>
              </div>
              <p class="text-[10px] text-slate-500 dark:text-slate-400 leading-snug">Kop putih bersih lengkap kontak, tanggal di kicker &amp; footer ringkas.</p>
            </button>
          </div>
        </div>

        <div class="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-3">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Orientasi &amp; Rasio Kanvas</span>
            <span id="activeCanvasDimBadge" class="font-mono text-[11px] font-semibold text-slate-600 dark:text-slate-300">1620 &times; 2430 px (2K)</span>
          </div>

          <!-- Orientation Tabs -->
          <div class="grid grid-cols-3 gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200/80 dark:border-slate-700/80">
            <button type="button" class="dok-orient-tab h-8 rounded-lg text-xs font-semibold transition cursor-pointer" data-orient="vertical" data-active="true">Vertikal</button>
            <button type="button" class="dok-orient-tab h-8 rounded-lg text-xs font-semibold transition cursor-pointer" data-orient="horizontal" data-active="false">Horizontal</button>
            <button type="button" class="dok-orient-tab h-8 rounded-lg text-xs font-semibold transition cursor-pointer" data-orient="square" data-active="false">Persegi</button>
          </div>

          <!-- Dynamic Canvas Size Preset Grid -->
          <div id="canvasPresetGrid" class="grid grid-cols-2 gap-2"></div>
        </div>
      </section>

      <!-- Card 3: Jumlah Media (4-12) & Pola Grid -->
      <section class="dok-studio-card rounded-2xl p-4 sm:p-5 space-y-3.5">
        <div class="flex items-center justify-between gap-2">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">3. Jumlah Foto / Video &amp; Pola Grid</h2>
          <span class="text-[11px] font-mono font-semibold text-orange-700 dark:text-orange-400">Min 4 &ndash; Maks 12</span>
        </div>

        <!-- Slot Count Selector 4..12 -->
        <div id="slotCountBar" class="grid grid-cols-9 gap-1.5">
          <?php for ($n = 4; $n <= 12; $n++): ?>
            <button type="button"
                    class="dok-chip h-8 rounded-lg text-xs font-mono font-semibold cursor-pointer"
                    data-slot-count="<?= $n ?>"
                    data-active="<?= $n === 6 ? 'true' : 'false' ?>">
              <?= $n ?>
            </button>
          <?php endfor; ?>
        </div>

        <!-- Dynamic Layout Variant Thumbnails -->
        <div class="space-y-1.5">
          <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 block">Variasi Tata Letak Kolase</span>
          <div id="layoutVariantGrid" class="grid grid-cols-2 gap-2"></div>
        </div>
      </section>

      <!-- Card 4: Unggah Media Sekaligus (Bersih — Seluruh Pengaturan Per-Slot Langsung di Pratinjau) -->
      <section class="dok-studio-card rounded-2xl p-4 sm:p-5 space-y-3.5">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-200">4. Unggah Media Sekaligus</h2>
          <div class="flex items-center gap-2">
            <span id="filledSlotsBadge" class="font-mono text-[11px] font-bold px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">0 / 6 terisi</span>
            <button type="button" id="btnClearAllSlots" class="text-[11px] font-semibold text-rose-600 dark:text-rose-400 hover:underline cursor-pointer">Hapus Semua</button>
          </div>
        </div>

        <!-- Bulk Dropzone Only -->
        <label id="bulkMediaDropzone" class="dok-dropzone rounded-xl p-5 flex flex-col items-center justify-center text-center gap-1.5 cursor-pointer">
          <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/></svg>
          <span class="text-xs font-bold text-slate-800 dark:text-slate-100">Pilih atau Seret 4&ndash;12 Foto &amp; Video Sekaligus</span>
          <span class="text-[11px] text-slate-500 dark:text-slate-400">Untuk unggah satuan, geser posisi, zoom, tukar urutan, atau hapus &mdash; langsung atur pada kotak pratinjau di sebelah kanan.</span>
          <input type="file" id="bulkMediaInput" accept="image/*,video/*" multiple class="hidden" />
        </label>

        <!-- Hidden Single Slot Replace Input (triggered from preview overlay) -->
        <input type="file" id="singleSlotInput" accept="image/*,video/*" class="hidden" />
      </section>
    </div>

    <!-- RIGHT PANEL: Live Interactive Preview Stage & High-Res Export Console (xl:col-span-7) -->
    <div class="xl:col-span-7 xl:sticky xl:top-20 space-y-4">
      <section class="dok-studio-card rounded-2xl overflow-hidden">
        <!-- Preview Top Status Strip -->
        <div class="px-4 py-2.5 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/40 flex flex-wrap items-center justify-between gap-2 text-xs">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-bold text-slate-800 dark:text-slate-100">Pratinjau Interaktif</span>
            <span id="previewMetaPill" class="px-2 py-0.5 rounded-md bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[11px] font-semibold">2:3 &bull; 6 Slot</span>
            <span id="previewModeBadge" class="px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider">MODE FOTO</span>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" id="btnToggleBulkMode" class="hidden inline-flex items-center gap-1 h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-[11px] font-semibold transition cursor-pointer">
              <span>Pilih Banyak</span>
            </button>
          </div>
        </div>

        <?php if ($isEditMode && !$hasSavedProjectSlots): ?>
          <div class="px-4 py-2.5 border-b border-sky-200/80 dark:border-sky-900/60 bg-sky-50/90 dark:bg-sky-950/40 text-[11px] text-sky-900 dark:text-sky-200 flex flex-wrap items-center justify-between gap-2">
            <span><strong>Mode Edit Arsip #<?= $editDocId ?>:</strong> teks, gaya, dan tata letak sudah dimuat. Karena arsip lama belum menyimpan berkas sumber per-slot, unggah foto/video pada pratinjau untuk merender ulang dan mengganti arsip ini.</span>
          </div>
        <?php endif; ?>

        <!-- Contextual Bulk Delete Action Bar -->
        <div id="previewBulkBar" class="hidden px-4 py-2 border-b border-rose-200 dark:border-rose-900/60 bg-rose-50/90 dark:bg-rose-950/40 flex flex-wrap items-center justify-between gap-2 text-xs">
          <span id="bulkSelectedCountLabel" class="font-bold text-rose-700 dark:text-rose-300">0 slot dipilih</span>
          <div class="flex flex-wrap items-center gap-1.5">
            <button type="button" id="btnBulkSelectAll" class="h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 text-[11px] font-semibold hover:bg-slate-100 cursor-pointer">Pilih Semua</button>
            <button type="button" id="btnBulkDeleteSelected" class="h-7 px-2.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-[11px] font-bold cursor-pointer">Hapus Terpilih</button>
            <button type="button" id="btnBulkCancel" class="h-7 px-2.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 text-[11px] font-semibold hover:bg-slate-100 cursor-pointer">Batal</button>
          </div>
        </div>

        <!-- Video Playback & Recording Controls (Auto-visible when >= 1 video slot is present) -->
        <div id="videoPlaybackBar" class="hidden px-4 py-2.5 border-b border-slate-200/80 dark:border-slate-800 bg-orange-500/5 flex flex-wrap items-center justify-between gap-2.5 text-xs">
          <div class="flex items-center gap-2">
            <button type="button" id="btnToggleVideoPlay" class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg bg-orange-700 hover:bg-orange-800 text-white font-semibold text-xs cursor-pointer transition">
              <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
              <span id="labelToggleVideoPlay">Putar Video</span>
            </button>
            <label class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-slate-700 dark:text-slate-300">
              <span>Durasi:</span>
              <select id="selectVideoDuration" class="h-7 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-2 text-xs font-semibold cursor-pointer">
                <option value="auto" selected>Otomatis (maks 12 dtk)</option>
                <option value="5">5 detik</option>
                <option value="8">8 detik</option>
                <option value="12">12 detik</option>
                <option value="18">18 detik</option>
              </select>
            </label>
          </div>
          <span class="text-[11px] text-slate-600 dark:text-slate-400">Video kolase direkam 30 fps langsung di browser</span>
        </div>

        <!-- Export Progress Bar -->
        <div id="exportProgressWrap" class="hidden px-4 py-2.5 border-b border-slate-200 dark:border-slate-800 bg-slate-900 text-white space-y-1.5">
          <div class="flex items-center justify-between text-xs font-semibold">
            <span id="exportProgressLabel">Merender kolase resolusi tinggi...</span>
            <span id="exportProgressPct" class="font-mono">0%</span>
          </div>
          <div class="h-1.5 w-full rounded-full bg-slate-700 overflow-hidden">
            <div id="exportProgressBar" class="h-full bg-orange-500 transition-all duration-150" style="width: 0%"></div>
          </div>
        </div>

        <!-- Interactive Canvas Viewport + Pixel-Aligned Direct Control Overlay -->
        <div class="dok-canvas-stage p-4 sm:p-6 bg-slate-100/90 dark:bg-slate-950/90 flex flex-col items-center justify-center min-h-[440px] gap-2.5">
          <div id="collageCanvasWrapper" class="relative inline-block max-w-full rounded-xl shadow-2xl overflow-hidden bg-white select-none">
            <canvas id="collagePreviewCanvas"
                    width="1080"
                    height="1620"
                    class="block max-w-full max-h-[70vh] w-auto h-auto"
                    aria-label="Kanvas pratinjau kolase dokumentasi operasi SAR"></canvas>
            <div id="collageInteractiveOverlay" class="absolute inset-0 pointer-events-none"></div>
          </div>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 text-center">
            Seret di dalam foto untuk atur posisi &bull; Seret ikon handle untuk tukar urutan &bull; Gunakan tombol + / &minus; untuk zoom &bull; Klik silang atau centang untuk hapus.
          </p>
        </div>

        <!-- Bottom High-Res Quality & Export Strip -->
        <div class="px-4 py-3 border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-950/40 flex flex-wrap items-center justify-between gap-2.5">
          <div class="flex flex-wrap items-center gap-2 text-xs">
            <label class="inline-flex items-center gap-1.5">
              <span class="font-semibold text-slate-600 dark:text-slate-400">Kualitas:</span>
              <select id="selectExportQuality" class="h-8 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-2 text-xs font-semibold cursor-pointer">
                <option value="fhd">Full HD (1080p)</option>
                <option value="qhd" selected>2K QHD (Tajam)</option>
                <option value="uhd_4k">4K Ultra HD / Original</option>
              </select>
            </label>
            <label class="inline-flex items-center gap-1.5">
              <span class="font-semibold text-slate-600 dark:text-slate-400">Format:</span>
              <select id="selectImageFormat" class="h-8 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 px-2 text-xs font-semibold cursor-pointer">
                <option value="image/jpeg" selected>JPG (Tajam)</option>
                <option value="image/png">PNG (Lossless)</option>
              </select>
            </label>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" id="btnBottomDownloadImg" class="inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-800 dark:text-white text-xs font-semibold transition cursor-pointer">
              <span>Unduh Gambar</span>
            </button>
            <button type="button" id="btnBottomDownloadVid" class="hidden inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg bg-slate-900 dark:bg-slate-700 hover:bg-slate-800 text-white text-xs font-semibold transition cursor-pointer">
              <span>Ekspor Video Kolase</span>
            </button>
            <button type="button" id="btnBottomSaveArchive" class="inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition cursor-pointer">
              <span><?= $isEditMode ? 'Simpan Perubahan' : 'Simpan ke Arsip' ?></span>
            </button>
          </div>
        </div>
      </section>
    </div>
  </div>
</div>

<script>
window.__DOK_STUDIO_CONFIG__ = <?= jsonSafe([
    'idOperasi' => $idOps,
    'namaOperasi' => (string)($operasi['nama_operasi'] ?? ''),
    'hariLabel' => $hariLabel,
    'tanggalDefault' => $tanggalDefault,
    'judulDefault' => $judulDefault,
    'backDetailUrl' => $backDetailUrl,
    'apiEndpoint' => BASE_URL . '/api/index.php?path=/operasi/' . $idOps . '/dokumentasi',
    'sourceApiEndpoint' => BASE_URL . '/api/index.php?path=/operasi/' . $idOps . '/dokumentasi/source',
    'editDoc' => $editDocPayload,
    'logos' => [
        'logo1' => $logo1DataUri,
        'logo2' => $logo2DataUri,
        'logo3' => $logo3DataUri,
    ],
]) ?>;
</script>
<script src="<?= assetUrl('assets/js/dokumentasi/collage_layouts.js') ?>"></script>
<script src="<?= assetUrl('assets/js/pages/operasi_dokumentasi.js') ?>"></script>
