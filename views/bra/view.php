<?php
/**
 * QR-SAR - Lembar Dokumen Resmi Berita Radio SAR (BRA)
 * KPP Kendari - Basarnas
 * 100% Replica of Official Basarnas Telegram Paper Sheet
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (!isset($bra) || empty($bra)) {
    $id = (int)($_GET['view'] ?? $_GET['id'] ?? 0);
    $bra = $id > 0 ? BraModel::getById($id) : null;
}

if (!$bra) {
    if (AuthHelper::isLoggedIn()) {
        setFlash('error', 'Dokumen BRA SAR tidak ditemukan.');
        header("Location: " . BASE_URL . "/bra.php");
        exit;
    }
    require_once VIEWS_DIR . '/bra/verify_invalid.php';
    exit;
}

$settings = SettingsModel::get();
$customLogo = getAppLogoUrl();
$logoDataUri = getAppLogoDataUri();

// Calculate total words in sections + perihal using standardized BraHelper
$sections = $bra['sections'] ?? [];
$totalWords = BraHelper::countBraWords($sections, $bra['perihal'] ?? '');
$jumlahKataStr = $totalWords > 0 ? "{$totalWords} KATA" : (!empty($bra['jumlah_kata']) ? $bra['jumlah_kata'] : '-');

$docHash = BraModel::generateVerifyToken($bra);
$verifyUrl = BraModel::generateVerifyUrl($bra);
$isGuest = !AuthHelper::isLoggedIn();
$verifyMode = $verificationStatus ?? (isset($_GET['token']) && BraModel::verifyToken($bra, (string)$_GET['token']) ? 'VERIFIED_SIGNATURE' : 'LEGACY_VERIFIED');
?>

<div class="space-y-6">
  <!-- Top Action Toolbar (Hidden on print) -->
  <div class="print:hidden card p-4 flex flex-wrap items-center justify-between gap-3 shadow-lg border border-slate-200 dark:border-slate-800">
    <div class="flex flex-wrap items-center gap-2">
      <?php if (!$isGuest): ?>
        <a href="<?= BASE_URL ?>/bra.php" class="p-2 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800 rounded-lg transition" title="Kembali ke Daftar">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
      <?php endif; ?>

      <span class="px-2.5 py-1 text-xs font-bold rounded-lg border uppercase tracking-wider <?= $bra['kategori'] === 'MAPEL' ? 'bg-orange-500/20 text-orange-600 dark:text-orange-400 border-orange-500/30' : ($bra['kategori'] === 'PERGERAKAN_KAPAL' ? 'bg-cyan-500/20 text-cyan-600 dark:text-cyan-300 border-cyan-500/30' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border-amber-500/30') ?>">
        <?= $bra['kategori'] === 'MAPEL' ? '📡 BRA MAPEL' : ($bra['kategori'] === 'PERGERAKAN_KAPAL' ? '🚢 BRA KAPAL' : '📋 BRA OPERASI SAR') ?>
      </span>

      <span class="font-mono text-xs font-bold text-slate-800 dark:text-slate-200">
        <?= htmlspecialchars($bra['no_bra']) ?>
      </span>
    </div>

    <!-- Paper Size & Zoom Controls -->
    <div class="flex items-center gap-2">
      <div class="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
        <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400 pl-1">Kertas:</span>
        <select id="paperSizeSelect" class="text-xs font-bold bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded px-2 py-1 outline-none cursor-pointer">
          <option value="A4" selected>A4 (210×297 mm) [Default]</option>
          <option value="F4">F4 / Folio (215×330 mm)</option>
          <option value="LETTER">Letter (216×279 mm)</option>
          <option value="LEGAL">Legal (216×356 mm)</option>
        </select>
        <div class="h-4 w-px bg-slate-300 dark:bg-slate-700 mx-0.5"></div>
        <button type="button" id="btnZoomOut" class="p-1 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded" title="Zoom Out">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg>
        </button>
        <span id="zoomLabel" class="text-[11px] font-mono font-bold text-slate-700 dark:text-slate-300 min-w-[34px] text-center">100%</span>
        <button type="button" id="btnZoomIn" class="p-1 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded" title="Zoom In">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        </button>
      </div>

      <!-- Action Buttons -->
      <?php if (!$isGuest): ?>
        <a href="<?= BASE_URL ?>/bra.php?action=edit&id=<?= $bra['id_bra'] ?>" class="px-2.5 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 transition">
          Edit Form
        </a>
      <?php endif; ?>

      <button type="button" id="btnCopyTelegram" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 transition" title="Salin Naskah Telegram Plain Text">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
        <span class="hidden sm:inline">Salin Naskah</span>
      </button>

      <button type="button" id="btnDownloadQr" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 transition" title="Unduh Barcode QR">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span class="hidden sm:inline">Unduh QR</span>
      </button>

      <button type="button" id="btnDownloadHtml" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 transition" title="Unduh Dokumen Mandiri HTML">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span class="hidden sm:inline">HTML</span>
      </button>

      <button type="button" onclick="window.downloadBraDocumentPdf()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-md transition" title="PDF berbentuk gambar (teks tidak bisa dicari). Untuk naskah resmi gunakan Cetak / Simpan PDF.">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        <span>PDF Gambar</span>
      </button>

      <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-orange-600 hover:bg-orange-700 rounded-lg shadow-md transition" title="Naskah resmi: teks tajam &amp; bisa dicari. Pilih &quot;Simpan sebagai PDF&quot; pada dialog cetak.">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span>Cetak / Simpan PDF</span>
      </button>
    </div>
  </div>

  <!-- Official Digital Verification Certificate Banner (Public & Authenticated) -->
  <div class="print:hidden rounded-2xl border border-emerald-500/40 bg-emerald-50/50 dark:bg-emerald-950/30 p-4 sm:p-5 shadow-sm space-y-3">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-emerald-200 dark:border-emerald-800/60">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </div>
        <div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 rounded text-[11px] font-black <?= $verifyMode === 'VERIFIED_SIGNATURE' ? 'bg-emerald-600 text-white' : 'bg-teal-600 text-white' ?> tracking-wider uppercase">
              <?= $verifyMode === 'VERIFIED_SIGNATURE' ? 'TERVALIDASI DIGITAL • TANDA TANGAN SAH' : 'TERVALIDASI RESMI • ARSIP BASARNAS' ?>
            </span>
            <span class="text-xs font-mono font-bold text-slate-500 dark:text-slate-400">HASH: <?= htmlspecialchars($docHash) ?></span>
          </div>
          <h3 class="text-sm font-black text-slate-900 dark:text-white mt-0.5">
            Sertifikat Keabsahan Dokumen Digital Berita Radio SAR (BRA)
          </h3>
        </div>
      </div>
      <div class="text-left sm:text-right">
        <span class="text-[11px] font-bold text-emerald-700 dark:text-emerald-300 block">BASARNAS KPP KENDARI</span>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">
          <?= $verifyMode === 'VERIFIED_SIGNATURE' ? 'Integritas: ASLI & SAH (Tanda Tangan Kriptografis)' : 'Integritas: ASLI & SAH (Tercatat Dalam Arsip)' ?>
        </span>
      </div>
    </div>

    <!-- Verification Metadata Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
      <div class="p-2.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-emerald-100 dark:border-emerald-900/30">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Nomor Registrasi</span>
        <span class="font-bold font-mono text-slate-800 dark:text-slate-200 mt-0.5 block truncate"><?= htmlspecialchars($bra['no_bra']) ?></span>
      </div>
      <div class="p-2.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-emerald-100 dark:border-emerald-900/30">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Waktu Sandi (TWU)</span>
        <span class="font-bold font-mono text-slate-800 dark:text-slate-200 mt-0.5 block truncate"><?= htmlspecialchars($bra['twu']) ?></span>
      </div>
      <div class="p-2.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-emerald-100 dark:border-emerald-900/30">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Penandatangan (SMC)</span>
        <span class="font-bold text-slate-800 dark:text-slate-200 mt-0.5 block truncate"><?= htmlspecialchars($bra['nama_smc'] ?: 'KEPALA KANSAR KENDARI') ?></span>
      </div>
      <div class="p-2.5 rounded-xl bg-white/80 dark:bg-slate-800/80 border border-emerald-100 dark:border-emerald-900/30">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block">Derajat Kecepatan</span>
        <span class="font-bold font-mono text-slate-800 dark:text-slate-200 mt-0.5 block"><?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?> (SEGERA)</span>
      </div>
    </div>
  </div>

  <!-- ============================================================== -->
  <!-- THE OFFICIAL BRA SAR PAPER DOCUMENT CONTAINER (Print-perfect) -->
  <!-- ============================================================== -->
  <div class="flex justify-center w-full py-2">
    <!-- Screen-only card frame: provides drop-shadow on UI without leaking outer borders into PDF -->
    <div class="w-full max-w-4xl bg-white rounded-xl shadow-xl p-4 sm:p-6 print:p-0 print:shadow-none print:rounded-none">
      <div id="official-bra-document" class="w-full bg-white text-black p-0 border-0 shadow-none font-sans print:p-0">
        
        <!-- Header Kop: Logo sejajar dengan BRA SAR dan BADAN NASIONAL PENCARIAN DAN PERTOLONGAN -->
        <div class="bra-kop relative flex items-center justify-center gap-4 pb-2 mb-2 border-b-2 border-black text-black">
          <div class="w-12 h-12 shrink-0">
            <?php if (!empty($customLogo)): ?>
              <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
            <?php else: ?>
              <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" class="w-full h-full object-contain">
            <?php endif; ?>
          </div>
          <div class="text-center">
            <h1 class="text-xl sm:text-[22px] font-black tracking-wider uppercase leading-none text-black m-0 p-0">
              BRA SAR
            </h1>
            <h2 class="text-xs sm:text-[13px] font-bold tracking-wider uppercase leading-none text-black m-0 mt-1 p-0">
              BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
            </h2>
          </div>
        </div>

        <!-- Main Body Container with Left SENKOM Note -->
        <div class="bra-table-wrapper relative pl-6 pr-1 sm:pl-7 sm:pr-1 w-full box-border">
          <!-- Left Vertical Side Label (Vector SVG: 100% Crisp, Offline & Zero-Shift) -->
          <div class="absolute left-0 top-0 w-5 flex items-center justify-center select-none pointer-events-none senkom-vertical-label" style="height: 260px; width: 20px;">
            <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMiAyNjAiIHdpZHRoPSIyMiIgaGVpZ2h0PSIyNjAiPjx0ZXh0IHg9Ii0xMzAiIHk9IjE1IiB0cmFuc2Zvcm09InJvdGF0ZSgtOTApIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiB0ZXh0TGVuZ3RoPSIyNDAiIGxlbmd0aEFkanVzdD0ic3BhY2luZyIgZm9udC1mYW1pbHk9IkFyaWFsLCBIZWx2ZXRpY2EsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iNy41IiBmb250LXdlaWdodD0iOTAwIiBmaWxsPSIjMDAwMDAwIj5SVUFOR0FOIERJTFVBUiBHQVJJUyBURUJBTCBESSBJU0kgT0xFSCBESU5BUyBTRU5LT008L3RleHQ+PC9zdmc+" 
                 alt="RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM" 
                 class="w-full h-full object-contain" />
          </div>

          <!-- Right Main Table Box (Thick Borders) -->
          <div class="bra-table-box border-2 border-black divide-y-2 divide-black text-[11px] sm:text-xs bg-white text-black w-full box-border">
          
          <!-- SENKOM Top Header Row -->
          <div class="bra-top-header grid grid-cols-5 divide-x-2 divide-black text-center font-bold bg-white text-black">
            <div class="py-1 px-1">
              <div>Panggilan</div>
              <div class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                <?= htmlspecialchars($bra['panggilan'] ?: '—') ?>
              </div>
            </div>
            <div class="py-1 px-1">
              <div>Jenis</div>
              <div class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                <?= htmlspecialchars($bra['jenis'] ?: 'SAR') ?>
              </div>
            </div>
            <div class="py-1 px-1">
              <div>Nr. Stn</div>
              <div class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                <?= htmlspecialchars($bra['nr_stn'] ?: '—') ?>
              </div>
            </div>
            <div class="py-1 px-1">
              <div>Derajat</div>
              <div class="font-extrabold text-[11px] sm:text-xs border-t border-black mt-0.5 pt-0.5 text-black">
                <?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?>
              </div>
            </div>
            <div class="py-1 px-1">
              <div>Instruksi penerusan</div>
              <div class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                <?= htmlspecialchars($bra['instruksi_penerusan'] ?: '—') ?>
              </div>
            </div>
          </div>

          <!-- Upper Section: 2 Columns -->
          <div class="bra-upper-section grid grid-cols-12 divide-x-2 divide-black bg-white text-black">
            <!-- Left Column: DARI, AKSI, INFO -->
            <div class="col-span-7 sm:col-span-8 p-2.5 space-y-1.5">
              <div class="flex">
                <span class="w-16 font-bold uppercase shrink-0 text-black">DARI</span>
                <span class="mr-1">:</span>
                <span class="font-bold uppercase tracking-tight text-black"><?= htmlspecialchars($bra['dari'] ?? '') ?></span>
              </div>
              <div class="flex">
                <span class="w-16 font-bold uppercase shrink-0 text-black">AKSI</span>
                <span class="mr-1">:</span>
                <span class="uppercase text-black"><?= htmlspecialchars($bra['aksi'] ?? '') ?></span>
              </div>
              <div class="flex">
                <span class="w-16 font-bold uppercase shrink-0 text-black">INFO</span>
                <span class="mr-1">:</span>
                <span class="uppercase text-black"><?= htmlspecialchars($bra['info'] ?? '') ?></span>
              </div>
            </div>

            <!-- Right Column: Tanggal Waktu TWU & Jumlah Kata -->
            <div class="col-span-5 sm:col-span-4 divide-y-2 divide-black flex flex-col justify-between bg-white text-black">
              <div class="p-1.5 sm:p-2 flex items-center justify-center text-center min-h-[30px] bg-white">
                <span class="font-bold uppercase text-[11px] sm:text-[11px] tracking-wider text-center text-black">
                  TANGGAL WAKTU
                </span>
              </div>
              <div class="p-1.5 sm:p-2 flex items-center justify-center text-center flex-1 min-h-[36px] bg-white">
                <span class="font-black text-xs sm:text-sm tracking-wider uppercase text-black">
                  <?php
                    $twuVal = $bra['twu'] ?? '';
                    echo str_starts_with($twuVal, 'TWU') ? htmlspecialchars($twuVal) : 'TWU ' . htmlspecialchars($twuVal);
                  ?>
                </span>
              </div>
              <div class="p-1.5 sm:p-2 flex items-center justify-between min-h-[30px] bg-white">
                <span class="font-bold uppercase text-[11px] sm:text-[11px] text-black">
                  JUMLAH KATA
                </span>
                <span class="font-bold text-xs uppercase text-black">
                  <?= htmlspecialchars($jumlahKataStr) ?>
                </span>
              </div>
            </div>
          </div>

          <!-- Telegram Body Sections (AAA, BBB, CCC, etc.) -->
          <div class="bra-body-container border-t-2 border-black p-3 sm:p-4 bg-white text-black">
            <!-- NO. & Menbra & Perihal -->
            <div class="bra-no-header mb-3.5">
              <div class="space-y-1">
                <div class="flex items-start text-xs sm:text-[13px] leading-relaxed uppercase">
                  <span class="w-14 sm:w-16 font-black shrink-0 text-black leading-relaxed">NO.</span>
                  <span class="w-4 text-center shrink-0 text-black font-black leading-relaxed">:</span>
                  <span class="font-extrabold tracking-wider ml-1 text-black flex-1 leading-relaxed"><?= htmlspecialchars($bra['no_bra'] ?? '') ?></span>
                </div>
                <?php if (!empty($bra['menbra']) && strtoupper(trim((string)$bra['menbra'])) !== 'SEGERA' && trim((string)$bra['menbra']) !== '-'): ?>
                  <div class="flex items-start text-xs sm:text-[13px] leading-relaxed uppercase">
                    <span class="w-14 sm:w-16 font-black shrink-0 text-black leading-relaxed">MENBRA</span>
                    <span class="w-4 text-center shrink-0 text-black font-black leading-relaxed">:</span>
                    <span class="font-extrabold tracking-wider ml-1 text-black flex-1 leading-relaxed"><?= htmlspecialchars($bra['menbra']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (!empty($bra['perihal'])): ?>
                <div class="font-black uppercase text-xs sm:text-sm tracking-tight leading-snug mt-2.5 mb-1 text-black text-justify">
                  <?= htmlspecialchars($bra['perihal']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Sections Rows -->
            <div class="space-y-3 bra-sections-list">
              <?php foreach ($sections as $sec): ?>
                <?php
                  $rawText = trim((string)($sec['isi'] ?? ''));
                  $rawLines = preg_split('/\r\n|\r|\n/', $rawText);
                  $textLines = [];
                  foreach ($rawLines as $rLine) {
                      $rLine = trim($rLine);
                      if ($rLine === '') {
                          $textLines[] = '';
                          continue;
                      }
                      if (mb_strlen($rLine) > 500 && preg_match('/\bTTK(?!\s+DUA)\b/i', $rLine)) {
                          $parts = preg_split('/(\bTTK(?!\s+DUA)\b\s*)/i', $rLine, -1, PREG_SPLIT_DELIM_CAPTURE);
                          $currentChunk = '';
                          for ($i = 0; $i < count($parts); $i++) {
                              $currentChunk .= $parts[$i];
                              if (preg_match('/^TTK$/i', trim($parts[$i])) || $i === count($parts) - 1) {
                                  if (mb_strlen($currentChunk) >= 250 || $i === count($parts) - 1) {
                                      $trimChunk = trim($currentChunk);
                                      if ($trimChunk !== '') {
                                          $textLines[] = $trimChunk;
                                      }
                                      $currentChunk = '';
                                  }
                              }
                          }
                          if (trim($currentChunk) !== '') {
                              $textLines[] = trim($currentChunk);
                          }
                      } else {
                          $textLines[] = $rLine;
                      }
                  }
                ?>
                <div class="bra-section-row flex items-start text-xs sm:text-[13px] leading-relaxed">
                  <span class="w-14 sm:w-16 font-black tracking-wider uppercase shrink-0 text-black leading-relaxed"><?= htmlspecialchars($sec['kode'] ?? '') ?></span>
                  <span class="w-4 text-center shrink-0 text-black font-black leading-relaxed">:</span>
                  <div class="flex-1 uppercase font-normal leading-relaxed ml-1 text-black bra-section-content text-justify">
                    <?php foreach ($textLines as $tLine):
                      $tTrim = trim($tLine);
                      if ($tTrim === ''): ?>
                        <div class="bra-text-line h-2.5"></div>
                      <?php else: ?>
                        <div class="bra-text-line text-justify"><?= htmlspecialchars($tTrim) ?></div>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <!-- TWB at bottom right of telegram text -->
            <div class="bra-twb-row text-right font-black text-xs sm:text-sm tracking-wider uppercase pt-3 pr-1 text-black">
              TWB <?= htmlspecialchars($bra['twb'] ?? '') ?>
            </div>
          </div>

          <!-- Bottom Footer Section: SMC / Pengirim with Barcode & SENKOM Signatures -->
          <div class="bra-footer-section grid grid-cols-12 divide-x-2 divide-black bg-white text-black">
            <!-- Left Column: Pengirim (SMC) with QR Barcode -->
            <div class="col-span-7 sm:col-span-7 p-2.5 flex flex-col justify-between">
              <div>
                <div class="space-y-1">
                  <div class="flex">
                    <span class="w-20 font-bold uppercase shrink-0 text-black">PENGIRIM</span>
                    <span class="mr-1">:</span>
                    <span class="font-bold uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['pengirim'] ?? '') ?></span>
                  </div>
                  <div class="flex">
                    <span class="w-20 font-bold uppercase shrink-0 text-black">Nama</span>
                    <span class="mr-1">:</span>
                    <span class="font-bold uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['nama_smc'] ?? '') ?></span>
                  </div>
                  <div class="flex">
                    <span class="w-20 font-bold uppercase shrink-0 text-black">Pangkat</span>
                    <span class="mr-1">:</span>
                    <span class="uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['pangkat_smc'] ?? '') ?></span>
                  </div>
                  <div class="flex">
                    <span class="w-20 font-bold uppercase shrink-0 text-black">Jabatan</span>
                    <span class="mr-1">:</span>
                    <span class="font-bold uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['jabatan_smc'] ?? '') ?></span>
                  </div>
                </div>
              </div>

              <!-- Barcode QR Code Section -->
              <div class="mt-2 pt-2 border-t border-black">
                <div class="flex items-start justify-between">
                  <div>
                    <span class="font-bold uppercase text-[11px] sm:text-[11px] block text-black">Tanda Tangan :</span>
                    <span class="text-[11px] font-mono block mt-0.5 text-gray-600">(Barcode Digital Terverifikasi)</span>
                  </div>
                  <div class="flex flex-col items-center">
                    <div class="p-0.5 bg-white border border-black rounded shadow-xs" style="width: 82px; height: 82px; display: flex; align-items: center; justify-content: center;">
                      <canvas id="braQrCanvas" width="180" height="180" style="width: 76px; height: 76px; max-width: 76px; max-height: 76px;" class="w-19 h-19 print:w-16 print:h-16 object-contain"></canvas>
                      <img id="braQrImage" style="width: 76px; height: 76px; max-width: 76px; max-height: 76px;" class="w-19 h-19 print:w-16 print:h-16 object-contain hidden" alt="Barcode QR Verifikasi" />
                    </div>
                    <div class="text-[11px] font-mono text-center mt-0.5 text-gray-700">SCAN UNTUK VERIFIKASI</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column: SENKOM Processing Columns -->
            <div class="col-span-5 sm:col-span-5 flex flex-col justify-between">
              <div class="grid grid-cols-4 divide-x-2 divide-black border-b-2 border-black text-center font-bold py-1 text-[11px] sm:text-[11px] bg-white text-black">
                <div>T. W. T</div>
                <div>T. W. K</div>
                <div>SISTEM</div>
                <div>PARAF</div>
              </div>
              <div class="grid grid-cols-4 divide-x-2 divide-black flex-1 min-h-[110px] bg-white text-black">
                <div class="p-1 text-center font-mono text-[11px] text-black"><?= htmlspecialchars($bra['twt'] ?? '') ?></div>
                <div class="p-1 text-center font-mono text-[11px] text-black"><?= htmlspecialchars($bra['twk'] ?? '') ?></div>
                <div class="p-1 text-center font-mono text-[11px] text-black"><?= htmlspecialchars($bra['sistem'] ?? '') ?></div>
                <div class="p-1 text-center font-mono text-[11px] text-black"><?= htmlspecialchars($bra['paraf'] ?? '') ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer tiny note -->
      <div class="mt-2 text-right text-[11px] font-mono text-gray-600">
        KANTOR PENCARIAN DAN PERTOLONGAN KENDARI &bull; SISTEM TELEGRAM RADIO ELEKTRONIK FILE AKTIF
      </div>
    </div>
  </div>
</div>
</div>

<!-- QR Code & PDF Libraries (Local Offline Vendor) -->
<script src="<?= assetUrl('assets/vendor/qrcode/qrcode.min.js') ?>"></script>
<script src="<?= assetUrl('assets/vendor/html2pdf/html2pdf.bundle.min.js') ?>"></script>
<script>
(function() {
  const verifyUrl = <?= jsonSafe($verifyUrl) ?>;
  const noBra = <?= jsonSafe($bra['no_bra'] ?? 'BRA-SAR') ?>;
  const logoDataUri = <?= jsonSafe($logoDataUri) ?>;

  function renderDocQr(retries = 0) {
    const canvas = document.getElementById('braQrCanvas');
    const qrImg = document.getElementById('braQrImage');
    if (!canvas) {
      if (retries < 30) setTimeout(function() { renderDocQr(retries + 1); }, 60);
      return;
    }

    const qrEngine = window.QRCode || (typeof QRCode !== 'undefined' ? QRCode : null);
    if (!qrEngine) {
      if (retries < 30) {
        setTimeout(function() { renderDocQr(retries + 1); }, 60);
      }
      return;
    }

    qrEngine.toCanvas(canvas, verifyUrl, {
      width: 200,
      margin: 1,
      color: { dark: '#0a0f1d', light: '#ffffff' },
      errorCorrectionLevel: 'H'
    }, function(err) {
      if (err) {
        console.warn('QR Code canvas error:', err);
        return;
      }

      const ctx = canvas.getContext('2d');
      if (!ctx) return;
      const size = canvas.width;
      const logoImg = new Image();

      function drawBadgeAndLogo() {
        const logoSize = Math.floor(size * 0.22);
        const center = (size - logoSize) / 2;
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2) + 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#cbd5e1';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        if (logoImg.naturalWidth > 0) {
          ctx.save();
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2), 0, Math.PI * 2);
          ctx.clip();
          ctx.drawImage(logoImg, center, center, logoSize, logoSize);
          ctx.restore();
        } else {
          // Basarnas Emblem Vector Badge Fallback
          ctx.fillStyle = '#ea580c';
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2), 0, Math.PI * 2);
          ctx.fill();
          ctx.fillStyle = '#ffffff';
          ctx.font = 'bold ' + Math.floor(logoSize * 0.44) + 'px sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText('SAR', size / 2, size / 2 + 1);
        }

        // Synchronize to permanent image tag for resilient printing & PDF
        try {
          const dataUrl = canvas.toDataURL('image/png');
          if (qrImg) {
            qrImg.src = dataUrl;
            qrImg.classList.remove('hidden');
            canvas.classList.add('hidden');
          }
        } catch (e) {
          // Keep canvas visible if toDataURL fails
          canvas.classList.remove('hidden');
        }
      }

      logoImg.onload = drawBadgeAndLogo;
      logoImg.onerror = function() { drawBadgeAndLogo(); };
      if (logoDataUri) {
        logoImg.src = logoDataUri;
        if (logoImg.complete && logoImg.naturalWidth > 0) {
          drawBadgeAndLogo();
        }
      } else {
        drawBadgeAndLogo();
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { renderDocQr(); });
  } else {
    renderDocQr();
  }
  window.addEventListener('load', function() { renderDocQr(); });

  // Paper Size & Scale Adjuster
  const doc = document.getElementById('official-bra-document');
  const paperSelect = document.getElementById('paperSizeSelect');
  let currentScale = 100;

  if (paperSelect && doc) {
    paperSelect.addEventListener('change', function(e) {
      const val = e.target.value;
      if (val === 'F4' || val === 'LEGAL') {
        doc.style.maxWidth = '850px';
      } else {
        doc.style.maxWidth = '56rem';
      }
    });
  }

  const zoomLabel = document.getElementById('zoomLabel');
  const btnZoomIn = document.getElementById('btnZoomIn');
  const btnZoomOut = document.getElementById('btnZoomOut');

  if (btnZoomIn && btnZoomOut && doc) {
    btnZoomIn.addEventListener('click', function() {
      currentScale = Math.min(125, currentScale + 5);
      doc.style.transform = `scale(${currentScale / 100})`;
      doc.style.transformOrigin = 'top center';
      if (zoomLabel) zoomLabel.textContent = currentScale + '%';
    });
    btnZoomOut.addEventListener('click', function() {
      currentScale = Math.max(80, currentScale - 5);
      doc.style.transform = `scale(${currentScale / 100})`;
      doc.style.transformOrigin = 'top center';
      if (zoomLabel) zoomLabel.textContent = currentScale + '%';
    });
  }

  // Copy Plain Text Telegram
  const btnCopy = document.getElementById('btnCopyTelegram');
  if (btnCopy) {
    btnCopy.addEventListener('click', function() {
      let txt = "BRA SAR\nBADAN NASIONAL PENCARIAN DAN PERTOLONGAN\n\n";
      txt += "DARI : " + <?= jsonSafe((string)($bra['dari'] ?? '')) ?> + "\n";
      txt += "AKSI : " + <?= jsonSafe((string)($bra['aksi'] ?? '')) ?> + "\n";
      txt += "INFO : " + <?= jsonSafe((string)($bra['info'] ?? '')) ?> + "\n";
      txt += "TWU  : " + <?= jsonSafe((string)($bra['twu'] ?? '')) ?> + "\n";
      txt += "JUMLAH KATA : " + <?= jsonSafe((string)($jumlahKataStr)) ?> + "\n\n";
      txt += "NO.    : " + <?= jsonSafe((string)($bra['no_bra'] ?? '')) ?> + "\n";
      <?php if (!empty($bra['menbra']) && strtoupper(trim((string)$bra['menbra'])) !== 'SEGERA' && trim((string)$bra['menbra']) !== '-'): ?>
      txt += "MENBRA : " + <?= jsonSafe((string)($bra['menbra'])) ?> + "\n";
      <?php endif; ?>
      <?php if (!empty($bra['perihal'])): ?>
      txt += "" + <?= jsonSafe((string)($bra['perihal'])) ?> + "\n\n";
      <?php endif; ?>
      <?php foreach ($sections as $s): 
          $isiRaw = trim((string)($s['isi'] ?? ''));
          $lines = preg_split('/\r?\n/', $isiRaw);
          $first = array_shift($lines);
      ?>
      txt += "" + <?= jsonSafe((string)($s['kode'] ?? '')) ?> + " : " + <?= jsonSafe((string)$first) ?> + "\n";
      <?php foreach ($lines as $subLine): 
          $subTrim = trim((string)$subLine);
          if ($subTrim === '') continue;
      ?>
      txt += "      " + <?= jsonSafe((string)($subTrim)) ?> + "\n";
      <?php endforeach; ?>
      txt += "\n";
      <?php endforeach; ?>
      txt += "TWB : " + <?= jsonSafe((string)($bra['twb'] ?? '')) ?> + "\n";
      txt += "PENGIRIM : " + <?= jsonSafe((string)($bra['pengirim'] ?? '')) ?> + "\n";
      txt += "NAMA     : " + <?= jsonSafe((string)($bra['nama_smc'] ?? '')) ?> + "\n";
      txt += "PANGKAT  : " + <?= jsonSafe((string)($bra['pangkat_smc'] ?? '')) ?> + "\n";
      txt += "JABATAN  : " + <?= jsonSafe((string)($bra['jabatan_smc'] ?? '')) ?> + "\n";
      txt += "LINK VERIFIKASI DIGITAL : " + verifyUrl + "\n";

      if (typeof copyToClipboard === 'function') {
        copyToClipboard(txt, 'Naskah Telegram Berhasil Disalin!');
      } else if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(txt).then(() => {
          if (typeof showToast === 'function') showToast('Naskah Telegram Berhasil Disalin!', 'success');
        });
      } else {
        const ta = document.createElement('textarea');
        ta.value = txt;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        if (typeof showToast === 'function') showToast('Naskah Telegram Berhasil Disalin!', 'success');
      }
    });
  }

  // Download QR Code image
  const btnDlQr = document.getElementById('btnDownloadQr');
  if (btnDlQr) {
    btnDlQr.addEventListener('click', function() {
      const canvas = document.getElementById('braQrCanvas');
      const img = document.getElementById('braQrImage');
      const srcUrl = (img && img.src && !img.classList.contains('hidden')) ? img.src : (canvas ? canvas.toDataURL('image/png') : null);
      if (!srcUrl) return;

      const link = document.createElement('a');
      link.download = 'QR_BRA_' + noBra.replace(/[\/\\]/g, '_') + '.png';
      link.href = srcUrl;
      link.click();
      if (typeof showToast === 'function') showToast('Barcode QR Berhasil Diunduh!', 'success');
    });
  }

  // Ensure print layout is completely unscaled and unmodified by screen zoom
  window.addEventListener('beforeprint', function() {
    if (doc) doc.style.transform = 'none';
  });
  window.addEventListener('afterprint', function() {
    if (doc && currentScale !== 100) {
      doc.style.transform = `scale(${currentScale / 100})`;
      doc.style.transformOrigin = 'top center';
    }
  });

  // High-fidelity Client-Side PDF Downloader (html2pdf A4 Retina 300 DPI)
  window.downloadBraDocumentPdf = function() {
    if (!doc) return;
    if (typeof html2pdf === 'undefined') {
      if (typeof showToast === 'function') showToast('Modul generator PDF sedang dimuat...', 'info');
      return;
    }
    const prevTransform = doc.style.transform;
    const prevBorder = doc.style.border;
    const prevBoxShadow = doc.style.boxShadow;
    const prevPadding = doc.style.padding;
    const prevWidth = doc.style.width;
    const prevMaxWidth = doc.style.maxWidth;
    const prevBoxSizing = doc.style.boxSizing;

    // Standardize to exact printable A4 width (180mm = 680px at 96 DPI)
    // with 15mm (1.5 cm) margins on all 4 sides.
    // 680px * (267 / 180) = 1008.66px, exactly matching html2pdf inner.px.height (1009px).
    // Eliminates pagebreak drift to 0px across all pages and prevents text cuts in CCC and DDD.
    doc.style.width = '680px';
    doc.style.maxWidth = '680px';
    doc.style.boxSizing = 'border-box';
    doc.style.transform = 'none';
    doc.style.border = 'none';
    doc.style.boxShadow = 'none';
    // 4px right safety padding ensures the 2px right border is 100% inside canvas
    doc.style.padding = '0 4px 0 0';

    const cleanNo = noBra.replace(/[^a-zA-Z0-9_\-]/g, '_');
    const opt = {
      margin: [5, 15, 15, 15],
      filename: `BRA-SAR-${cleanNo}.pdf`,
      image: { type: 'jpeg', quality: 0.85 },
      html2canvas: {
        // Turunkan skala untuk naskah panjang agar kanvas di bawah batas ~16,7 juta piksel Safari iOS
        scale: Math.max(1, Math.min(2, Math.sqrt(16000000 / Math.max(1, doc.scrollWidth * doc.scrollHeight)))),
        useCORS: true,
        letterRendering: true,
        scrollX: 0,
        scrollY: 0,
        backgroundColor: '#ffffff'
      },
      jsPDF: {
        unit: 'mm',
        format: 'a4',
        orientation: 'portrait',
        compress: true
      },
      pagebreak: {
        mode: ['css', 'legacy'],
        avoid: ['.bra-text-line', '.bra-kop', '.bra-top-header', '.bra-footer-section']
      }
    };

    if (typeof showToast === 'function') {
      showToast('Memproses berkas PDF resmi Basarnas...', 'info');
    }

    const restoreStyles = function() {
      doc.style.transform = prevTransform;
      doc.style.border = prevBorder;
      doc.style.boxShadow = prevBoxShadow;
      doc.style.padding = prevPadding;
      doc.style.width = prevWidth;
      doc.style.maxWidth = prevMaxWidth;
      doc.style.boxSizing = prevBoxSizing;
    };

    html2pdf().set(opt).from(doc).save().then(function() {
      restoreStyles();
      if (typeof showToast === 'function') {
        showToast('Dokumen PDF resmi berhasil diunduh!', 'success');
      }
    }).catch(function(err) {
      restoreStyles();
      console.error('PDF generation error:', err);
      if (typeof showToast === 'function') {
        showToast('Gagal membuat PDF: ' + err.message, 'error');
      }
    });
  };

  // Download Standalone HTML
  const btnDlHtml = document.getElementById('btnDownloadHtml');
  if (btnDlHtml && doc) {
    btnDlHtml.addEventListener('click', function() {
      const clone = doc.cloneNode(true);
      const canvas = document.getElementById('braQrCanvas');
      if (canvas) {
        const qrImg = clone.querySelector('#braQrImage');
        const qrCanvas = clone.querySelector('#braQrCanvas');
        if (qrImg) {
          qrImg.src = canvas.toDataURL('image/png');
          qrImg.classList.remove('hidden');
          qrImg.style.display = 'block';
        }
        if (qrCanvas) {
          qrCanvas.remove();
        }
      }
      const htmlContent = `<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>BRA SAR - ${noBra}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; background: #f1f5f9; padding: 2rem; display: flex; justify-content: center; margin: 0; color: #000; }
  #braDocument { background: #fff; max-width: 850px; width: 100%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border-radius: 4px; padding: 1.5cm; }
  .font-bold { font-weight: 700 !important; }
  .uppercase { text-transform: uppercase !important; }
  .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important; }
  .text-black { color: #000 !important; }
  .text-center { text-align: center !important; }
  .flex { display: flex !important; }
  .items-center { align-items: center !important; }
  .items-start { align-items: flex-start !important; }
  .justify-between { justify-content: space-between !important; }
  .border-b-2 { border-bottom-width: 2px !important; }
  .border-black { border-color: #000 !important; }
  .border { border: 1px solid #000 !important; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #000; padding: 4px 6px; }
  img { max-width: 100%; height: auto; }
  @media print {
    body { padding: 0; background: #fff; }
    #braDocument { box-shadow: none !important; border: none !important; width: 100% !important; max-width: 100% !important; margin: 0 !important; padding: 1cm !important; }
  }
</style>
</head>
<body>
${clone.outerHTML}
</body>
</html>`;
      const blob = new Blob([htmlContent], { type: 'text/html;charset=utf-8' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'BRA_SAR_' + noBra.replace(/[\/\\]/g, '_') + '.html';
      a.click();
      if (typeof showToast === 'function') showToast('Dokumen HTML Mandiri Berhasil Diunduh!', 'success');
    });
  }
})();
</script>
