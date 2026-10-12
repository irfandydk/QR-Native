<?php
/**
 * QR-SAR - Lembar Dokumen Resmi Berita Radio SAR (BRA)
 * KPP Kendari - Basarnas
 * 100% Replica of Official Basarnas Telegram Paper Sheet
 */

declare(strict_types=1);

$id = (int)($_GET['view'] ?? $_GET['id'] ?? 0);
$bra = BraModel::getById($id);

if (!$bra) {
    setFlash('error', 'Dokumen BRA SAR tidak ditemukan.');
    header("Location: " . BASE_URL . "/bra.php");
    exit;
}

$settings = SettingsModel::get();
$customLogo = getAppLogoUrl();
$logoDataUri = getAppLogoDataUri();

// Calculate total words in sections
$totalWords = 0;
$sections = $bra['sections'] ?? [];
foreach ($sections as $sec) {
    $words = preg_split('/\s+/', trim($sec['isi'] ?? ''));
    if (!empty($words) && $words[0] !== '') {
        $totalWords += count($words);
    }
}
$jumlahKataStr = $bra['jumlah_kata'] ?? ($totalWords > 0 ? "{$totalWords} KATA" : '-');

$verifyUrl = BASE_URL . "/bra.php?view=" . $bra['id_bra'];
?>

<div class="space-y-6">
  <!-- Top Action Toolbar (Hidden on print) -->
  <div class="print:hidden card p-4 flex flex-wrap items-center justify-between gap-3 shadow-lg border border-slate-200 dark:border-slate-800">
    <div class="flex flex-wrap items-center gap-2">
      <a href="<?= BASE_URL ?>/bra.php" class="p-2 text-slate-500 hover:text-slate-800 dark:hover:text-slate-200 bg-slate-100 dark:bg-slate-800 rounded-lg transition" title="Kembali ke Daftar">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      </a>

      <span class="px-2.5 py-1 text-xs font-bold rounded-lg border uppercase tracking-wider <?= $bra['kategori'] === 'MAPEL' ? 'bg-orange-500/20 text-orange-600 dark:text-orange-400 border-orange-500/30' : ($bra['kategori'] === 'PERGERAKAN_KAPAL' ? 'bg-cyan-500/20 text-cyan-600 dark:text-cyan-300 border-cyan-500/30' : 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border-amber-500/30') ?>">
        <?= $bra['kategori'] === 'MAPEL' ? '📡 BRA MAPEL' : ($bra['kategori'] === 'PERGERAKAN_KAPAL' ? '🚢 BRA KAPAL' : '📋 BRA SAR OPERASI SAR') ?>
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
      <a href="<?= BASE_URL ?>/bra.php?action=edit&id=<?= $bra['id_bra'] ?>" class="px-2.5 py-1.5 text-xs font-medium rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-100 transition">
        Edit Form
      </a>

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

      <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 rounded-lg shadow-md transition" title="Cetak atau Simpan PDF">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span>Cetak</span>
      </button>
    </div>
  </div>

  <!-- Verification Notice Banner (Hidden on print) -->
  <div class="print:hidden card p-3 flex items-center justify-between gap-3 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-300 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-300 text-xs">
    <div class="flex items-center gap-2">
      <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      <span><strong>Dokumen Asli Resmi Terverifikasi:</strong> Naskah telegram radio ini disahkan dengan Barcode QR Digital terenkripsi.</span>
    </div>
    <span class="font-mono text-[11px] text-emerald-600 dark:text-emerald-400">Status: RESMI AKTIF</span>
  </div>

  <!-- ============================================================== -->
  <!-- THE OFFICIAL BRA SAR PAPER DOCUMENT CONTAINER (Print-perfect) -->
  <!-- ============================================================== -->
  <div class="flex justify-center w-full">
    <div id="official-bra-document" class="w-full max-w-4xl bg-white text-black p-6 sm:p-8 border-2 border-slate-300 shadow-2xl transition-all font-sans print:border-none print:shadow-none print:p-0 print:max-w-none">
      
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
      <div class="bra-table-wrapper relative pl-6 sm:pl-7 w-full">
        <!-- Left Vertical Side Label -->
        <div class="absolute left-0 top-0 bottom-0 w-5 flex items-center justify-center select-none pointer-events-none senkom-vertical-label" style="writing-mode: vertical-rl; transform: rotate(180deg);">
          <span class="text-[8.5px] sm:text-[9px] font-bold tracking-widest uppercase text-center leading-none text-black whitespace-nowrap">
            RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM
          </span>
        </div>

        <!-- Right Main Table Box (Thick Borders) -->
        <div class="bra-table-box border-2 border-black divide-y-2 divide-black text-[11px] sm:text-xs bg-white text-black w-full">
          
          <!-- SENKOM Top Header Row -->
          <div class="bra-top-header grid grid-cols-5 divide-x-2 divide-black text-center font-bold bg-white text-black">
            <div class="py-1 px-1">
              <div>Panggilan</div>
              <div class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                <?= htmlspecialchars($bra['panggilan'] ?: '—') ?>
              </div>
            </div>
            <div class="py-1 px-1">
              <div>Jenis</div>
              <div class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                <?= htmlspecialchars($bra['jenis'] ?: 'SAR') ?>
              </div>
            </div>
            <div class="py-1 px-1">
              <div>Nr. Stn</div>
              <div class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
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
              <div class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
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
                <span class="font-bold uppercase text-[9.5px] sm:text-[10.5px] tracking-wider text-center text-black">
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
                <span class="font-bold uppercase text-[9.5px] sm:text-[10.5px] text-black">
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
                <?php if (!empty($bra['menbra'])): ?>
                  <div class="flex items-start text-xs sm:text-[13px] leading-relaxed uppercase">
                    <span class="w-14 sm:w-16 font-black shrink-0 text-black leading-relaxed">MENBRA</span>
                    <span class="w-4 text-center shrink-0 text-black font-black leading-relaxed">:</span>
                    <span class="font-extrabold tracking-wider ml-1 text-black flex-1 leading-relaxed"><?= htmlspecialchars($bra['menbra']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <?php if (!empty($bra['perihal'])): ?>
                <div class="font-black uppercase text-xs sm:text-sm tracking-tight leading-snug mt-2.5 mb-1 text-black">
                  <?= htmlspecialchars($bra['perihal']) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Sections Rows -->
            <div class="space-y-3 bra-sections-list">
              <?php foreach ($sections as $sec): ?>
                <div class="bra-section-row flex items-start text-xs sm:text-[13px] leading-relaxed">
                  <span class="w-14 sm:w-16 font-black tracking-wider uppercase shrink-0 text-black leading-relaxed"><?= htmlspecialchars($sec['kode'] ?? '') ?></span>
                  <span class="w-4 text-center shrink-0 text-black font-black leading-relaxed">:</span>
                  <div class="flex-1 whitespace-pre-line uppercase font-normal leading-relaxed ml-1 text-black"><?= htmlspecialchars(trim((string)($sec['isi'] ?? ''))) ?></div>
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
                    <span class="font-bold uppercase text-[10px] sm:text-[11px] block text-black">Tanda Tangan :</span>
                    <span class="text-[9px] font-mono block mt-0.5 text-gray-600">(Barcode Digital Terverifikasi)</span>
                  </div>
                  <div class="flex flex-col items-center">
                    <div class="p-0.5 bg-white border border-black rounded shadow-xs" style="width: 82px; height: 82px; display: flex; align-items: center; justify-content: center;">
                      <canvas id="braQrCanvas" width="180" height="180" style="width: 76px; height: 76px; max-width: 76px; max-height: 76px;" class="w-19 h-19 print:w-16 print:h-16 object-contain"></canvas>
                      <img id="braQrImage" style="width: 76px; height: 76px; max-width: 76px; max-height: 76px;" class="w-19 h-19 print:w-16 print:h-16 object-contain hidden" alt="Barcode QR Verifikasi" />
                    </div>
                    <div class="text-[8px] font-mono text-center mt-0.5 text-gray-700">SCAN UNTUK VERIFIKASI</div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Right Column: SENKOM Processing Columns -->
            <div class="col-span-5 sm:col-span-5 flex flex-col justify-between">
              <div class="grid grid-cols-4 divide-x-2 divide-black border-b-2 border-black text-center font-bold py-1 text-[10px] sm:text-[11px] bg-white text-black">
                <div>T. W. T</div>
                <div>T. W. K</div>
                <div>SISTEM</div>
                <div>PARAF</div>
              </div>
              <div class="grid grid-cols-4 divide-x-2 divide-black flex-1 min-h-[110px] bg-white text-black">
                <div class="p-1 text-center font-mono text-[10px] text-black"><?= htmlspecialchars($bra['twt'] ?? '') ?></div>
                <div class="p-1 text-center font-mono text-[10px] text-black"><?= htmlspecialchars($bra['twk'] ?? '') ?></div>
                <div class="p-1 text-center font-mono text-[10px] text-black"><?= htmlspecialchars($bra['sistem'] ?? '') ?></div>
                <div class="p-1 text-center font-mono text-[10px] text-black"><?= htmlspecialchars($bra['paraf'] ?? '') ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Footer tiny note -->
      <div class="mt-2 text-right text-[8px] font-mono text-gray-600">
        KANTOR PENCARIAN DAN PERTOLONGAN KENDARI &bull; SISTEM TELEGRAM RADIO ELEKTRONIK FILE AKTIF
      </div>
    </div>
  </div>
</div>

<!-- QR Code Library & Canvas Script -->
<script src="<?= BASE_URL ?>/assets/js/qrcode.min.js?v=<?= APP_VERSION ?>"></script>
<script>
(function() {
  const verifyUrl = <?= json_encode($verifyUrl) ?>;
  const noBra = <?= json_encode($bra['no_bra'] ?? 'BRA-SAR') ?>;
  const logoDataUri = <?= json_encode($logoDataUri) ?>;

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
      txt += "DARI : <?= addslashes($bra['dari'] ?? '') ?>\n";
      txt += "AKSI : <?= addslashes($bra['aksi'] ?? '') ?>\n";
      txt += "INFO : <?= addslashes($bra['info'] ?? '') ?>\n";
      txt += "TWU  : <?= addslashes($bra['twu'] ?? '') ?>\n";
      txt += "JUMLAH KATA : <?= addslashes($jumlahKataStr) ?>\n\n";
      txt += "NO.    : <?= addslashes($bra['no_bra'] ?? '') ?>\n";
      <?php if (!empty($bra['menbra'])): ?>
      txt += "MENBRA : <?= addslashes($bra['menbra']) ?>\n";
      <?php endif; ?>
      <?php if (!empty($bra['perihal'])): ?>
      txt += "<?= addslashes($bra['perihal']) ?>\n\n";
      <?php endif; ?>
      <?php foreach ($sections as $s): ?>
      txt += "<?= addslashes($s['kode'] ?? '') ?> : <?= addslashes(str_replace(["\r", "\n"], ["", " "], $s['isi'] ?? '')) ?>\n\n";
      <?php endforeach; ?>
      txt += "TWB : <?= addslashes($bra['twb'] ?? '') ?>\n";
      txt += "PENGIRIM : <?= addslashes($bra['pengirim'] ?? '') ?>\n";
      txt += "Nama : <?= addslashes($bra['nama_smc'] ?? '') ?>\n";
      txt += "Pangkat : <?= addslashes($bra['pangkat_smc'] ?? '') ?>\n";
      txt += "Jabatan : <?= addslashes($bra['jabatan_smc'] ?? '') ?>\n";
      txt += "LINK VERIFIKASI DIGITAL : " + verifyUrl + "\n";

      navigator.clipboard.writeText(txt).then(() => {
        if (typeof showToast === 'function') showToast('Naskah Telegram Berhasil Disalin!', 'success');
      });
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

  // Download Standalone HTML
  const btnDlHtml = document.getElementById('btnDownloadHtml');
  if (btnDlHtml && doc) {
    btnDlHtml.addEventListener('click', function() {
      const htmlContent = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>BRA SAR - ${noBra}</title>
<link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
<style>
@media print { body { padding: 0; } }
</style>
</head>
<body class="bg-gray-100 p-8 flex justify-center">
${doc.outerHTML}
</body>
</html>`;
      const blob = new Blob([htmlContent], { type: 'text/html' });
      const a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'BRA_SAR_' + noBra.replace(/[\/\\]/g, '_') + '.html';
      a.click();
      if (typeof showToast === 'function') showToast('Dokumen HTML Mandiri Berhasil Diunduh!', 'success');
    });
  }
})();
</script>
