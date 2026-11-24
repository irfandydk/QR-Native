<?php
/**
 * QR-SAR - Halaman Publik Read-Only Dokumen Resmi Berita Radio SAR (BRA)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dibuka melalui tombol "Buka dokumen" dari halaman verifikasi QR BRA SAR.
 * Hanya menyediakan lembar naskah resmi + Unduh PDF dan Cetak / Simpan PDF.
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$customLogo = getAppLogoUrl();
$logoDataUri = getAppLogoDataUri();
$sections = $bra['sections'] ?? [];
$totalWords = BraHelper::countBraWords($sections, $bra['perihal'] ?? '');
$jumlahKataStr = $totalWords > 0 ? "{$totalWords} KATA" : (!empty($bra['jumlah_kata']) ? $bra['jumlah_kata'] : '-');

$verifyUrl = $verifyUrl ?? BraModel::generateVerifyUrl($bra);
$cleanNoBra = trim((string)preg_replace('/[^a-zA-Z0-9_\-.]/', '_', (string)($bra['no_bra'] ?? 'BRA_SAR')), '_');
$pdfFilename = 'BRA-SAR-' . ($cleanNoBra !== '' ? $cleanNoBra : 'DOKUMEN') . '.pdf';
$pdfDownloadUrl = $pdfDownloadUrl ?? (BraModel::generatePublicDocUrl($bra) . '&format=pdf');

$braTitleDisplay = trim((string)($bra['nama_operasi'] ?? '')) !== ''
    ? (string)$bra['nama_operasi']
    : (trim((string)($bra['perihal'] ?? '')) !== '' ? (string)$bra['perihal'] : 'Berita Radio SAR (BRA)');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>BRA SAR <?= e((string)($bra['no_bra'] ?? '')) ?> - <?= e($braTitleDisplay) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= assetUrl('assets/images/logo_basarnas.svg') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/tailwind.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">
    <style>
        body { background-color: #0f172a; }
        .doc-scroll { overflow-x: auto; }
        @page { size: A4 portrait; margin: 5mm 15mm 15mm 15mm; }
        @media print {
            html, body { background: #ffffff !important; margin: 0 !important; padding: 0 !important; }
            .no-print, .no-print * { display: none !important; }
            .doc-scroll { overflow: visible !important; padding: 0 !important; }
            .bra-paper-shell { padding: 0 !important; border: none !important; box-shadow: none !important; border-radius: 0 !important; max-width: 100% !important; }
            #official-bra-document, .bra-official-paper { box-shadow: none !important; border: none !important; margin: 0 auto !important; width: 100% !important; max-width: 100% !important; }
        }
    </style>
</head>
<body class="antialiased min-h-screen">

    <header class="no-print sticky top-0 z-50 bg-slate-900/95 backdrop-blur border-b border-slate-800 text-white px-4 py-3 shadow-md">
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="text-xs font-mono font-bold text-orange-400 shrink-0">BRA SAR<?= !empty($bra['no_bra']) ? ' &bull; ' . e((string)$bra['no_bra']) : '' ?></span>
                <span class="text-xs text-slate-300 font-semibold truncate max-w-[200px] sm:max-w-md"><?= e($braTitleDisplay) ?></span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" id="btnDownloadPdf"
                        data-pdf-filename="<?= e($pdfFilename) ?>"
                        data-pdf-url="<?= e($pdfDownloadUrl) ?>"
                        onclick="window.downloadBraDocumentPdf(this)"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-xs font-bold transition shadow-sm cursor-pointer"
                        title="Unduh berkas PDF resmi">
                    <svg class="w-4 h-4 text-sky-400 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>Unduh PDF</span>
                </button>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-orange-700 hover:bg-orange-800 text-white text-xs font-bold transition shadow-sm cursor-pointer" title="Naskah resmi: pilih &quot;Simpan sebagai PDF&quot; pada dialog cetak.">
                    <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Cetak / Simpan PDF</span>
                </button>
            </div>
        </div>
    </header>

    <main class="doc-scroll py-6 px-3 sm:px-4 print:p-0">
        <div class="flex justify-center w-full">
            <div class="bra-paper-shell w-full max-w-4xl bg-white rounded-2xl border border-slate-800/40 shadow-2xl p-4 sm:p-7 print:p-0 print:border-0 print:shadow-none print:rounded-none">
                <div id="official-bra-document" class="page-sheet bra-official-paper w-full bg-white text-black p-0 border-0 shadow-none font-sans print:p-0">

                    <!-- Header Kop -->
                    <div class="bra-kop relative flex items-center justify-center gap-4 pb-2 mb-2 border-b-2 border-black text-black">
                        <div class="w-12 h-12 shrink-0">
                            <?php if (!empty($customLogo)): ?>
                                <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
                            <?php else: ?>
                                <img src="<?= assetUrl('assets/images/logo_basarnas.svg') ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
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
                        <div class="absolute left-0 top-0 w-5 flex items-center justify-center select-none pointer-events-none senkom-vertical-label" style="height: 260px; width: 20px;">
                            <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMiAyNjAiIHdpZHRoPSIyMiIgaGVpZ2h0PSIyNjAiPjx0ZXh0IHg9Ii0xMzAiIHk9IjE1IiB0cmFuc2Zvcm09InJvdGF0ZSgtOTApIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiB0ZXh0TGVuZ3RoPSIyNDAiIGxlbmd0aEFkanVzdD0ic3BhY2luZyIgZm9udC1mYW1pbHk9IkFyaWFsLCBIZWx2ZXRpY2EsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iNy41IiBmb250LXdlaWdodD0iOTAwIiBmaWxsPSIjMDAwMDAwIj5SVUFOR0FOIERJTFVBUiBHQVJJUyBURUJBTCBESSBJU0kgT0xFSCBESU5BUyBTRU5LT008L3RleHQ+PC9zdmc+"
                                 alt="RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM"
                                 class="w-full h-full object-contain" />
                        </div>

                        <div class="bra-table-box border-2 border-black divide-y-2 divide-black text-[11px] sm:text-xs bg-white text-black w-full box-border">
                            <!-- SENKOM Top Header Row -->
                            <div class="bra-top-header grid grid-cols-5 divide-x-2 divide-black text-center font-bold bg-white text-black">
                                <div class="py-1 px-1">
                                    <div>Panggilan</div>
                                    <div class="font-mono text-[11px] border-t border-black mt-0.5 pt-0.5 text-black"><?= htmlspecialchars($bra['panggilan'] ?: '—') ?></div>
                                </div>
                                <div class="py-1 px-1">
                                    <div>Jenis</div>
                                    <div class="font-mono text-[11px] border-t border-black mt-0.5 pt-0.5 text-black"><?= htmlspecialchars($bra['jenis'] ?: 'SAR') ?></div>
                                </div>
                                <div class="py-1 px-1">
                                    <div>Nr. Stn</div>
                                    <div class="font-mono text-[11px] border-t border-black mt-0.5 pt-0.5 text-black"><?= htmlspecialchars($bra['nr_stn'] ?: '—') ?></div>
                                </div>
                                <div class="py-1 px-1">
                                    <div>Derajat</div>
                                    <div class="font-extrabold text-[11px] sm:text-xs border-t border-black mt-0.5 pt-0.5 text-black"><?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?></div>
                                </div>
                                <div class="py-1 px-1">
                                    <div>Instruksi penerusan</div>
                                    <div class="font-mono text-[11px] border-t border-black mt-0.5 pt-0.5 text-black"><?= htmlspecialchars($bra['instruksi_penerusan'] ?: '—') ?></div>
                                </div>
                            </div>

                            <!-- Upper Section: 2 Columns -->
                            <div class="bra-upper-section grid grid-cols-12 divide-x-2 divide-black bg-white text-black">
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

                                <div class="col-span-5 sm:col-span-4 divide-y-2 divide-black flex flex-col justify-between bg-white text-black">
                                    <div class="p-1.5 sm:p-2 flex items-center justify-center text-center min-h-[30px] bg-white">
                                        <span class="font-bold uppercase text-[11px] tracking-wider text-center text-black">TANGGAL WAKTU</span>
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
                                        <span class="font-bold uppercase text-[11px] text-black">JUMLAH KATA</span>
                                        <span class="font-bold text-xs uppercase text-black"><?= htmlspecialchars($jumlahKataStr) ?></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Telegram Body Sections -->
                            <div class="bra-body-container border-t-2 border-black p-3 sm:p-4 bg-white text-black">
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

                                <div class="bra-twb-row text-right font-black text-xs sm:text-sm tracking-wider uppercase pt-3 pr-1 text-black">
                                    TWB <?= htmlspecialchars($bra['twb'] ?? '') ?>
                                </div>
                            </div>

                            <!-- Bottom Footer Section: SMC / Pengirim with Barcode & SENKOM Columns -->
                            <div class="bra-footer-section grid grid-cols-12 divide-x-2 divide-black bg-white text-black">
                                <div class="col-span-7 p-2.5 flex flex-col justify-between">
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

                                    <div class="mt-2 pt-2 border-t border-black">
                                        <div class="flex items-start justify-between">
                                            <div>
                                                <span class="font-bold uppercase text-[11px] block text-black">Tanda Tangan :</span>
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

                                <div class="col-span-5 flex flex-col justify-between">
                                    <div class="grid grid-cols-4 divide-x-2 divide-black border-b-2 border-black text-center font-bold py-1 text-[11px] bg-white text-black">
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

                    <div class="mt-2 text-right text-[11px] font-mono text-gray-600">
                        KANTOR PENCARIAN DAN PERTOLONGAN KENDARI &bull; SISTEM TELEGRAM RADIO ELEKTRONIK FILE AKTIF
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
    <script src="<?= assetUrl('assets/vendor/qrcode/qrcode.min.js') ?>"></script>
    <script src="<?= assetUrl('assets/vendor/html2pdf/html2pdf.bundle.min.js') ?>" defer fetchpriority="low"></script>
    <script src="<?= assetUrl('assets/js/pdf_document_exporter.js') ?>" defer fetchpriority="low"></script>
    <script>
    (function() {
        const verifyUrl = <?= jsonSafe($verifyUrl) ?>;
        const noBra = <?= jsonSafe($bra['no_bra'] ?? 'BRA-SAR') ?>;
        const logoDataUri = <?= jsonSafe($logoDataUri) ?>;
        const doc = document.getElementById('official-bra-document');

        function renderDocQr(retries = 0) {
            const canvas = document.getElementById('braQrCanvas');
            const qrImg = document.getElementById('braQrImage');
            if (!canvas) {
                if (retries < 30) setTimeout(function() { renderDocQr(retries + 1); }, 60);
                return;
            }
            const qrEngine = window.QRCode || (typeof QRCode !== 'undefined' ? QRCode : null);
            if (!qrEngine) {
                if (retries < 30) setTimeout(function() { renderDocQr(retries + 1); }, 60);
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

                    try {
                        const dataUrl = canvas.toDataURL('image/png');
                        if (qrImg) {
                            qrImg.src = dataUrl;
                            qrImg.classList.remove('hidden');
                            canvas.classList.add('hidden');
                        }
                    } catch (e) {
                        canvas.classList.remove('hidden');
                    }
                }

                logoImg.onload = drawBadgeAndLogo;
                logoImg.onerror = drawBadgeAndLogo;
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

        window.downloadBraDocumentPdf = function(btnEl) {
            const btn = btnEl || document.getElementById('btnDownloadPdf');
            if (typeof window.downloadOfficialPdf === 'function') {
                window.downloadOfficialPdf(null, btn);
                return;
            }
            if (!doc || typeof html2pdf === 'undefined') {
                window.print();
                return;
            }
            const cleanNo = noBra.replace(/[^a-zA-Z0-9_\-]/g, '_');
            html2pdf().set({
                margin: [5, 15, 15, 15],
                filename: `BRA-SAR-${cleanNo}.pdf`,
                image: { type: 'jpeg', quality: 0.85 },
                html2canvas: { scale: 2, useCORS: true, letterRendering: true, backgroundColor: '#ffffff' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait', compress: true },
                pagebreak: { mode: ['css', 'legacy'], avoid: ['.bra-text-line', '.bra-kop', '.bra-top-header', '.bra-footer-section'] }
            }).from(doc).save();
        };
    })();
    </script>
</body>
</html>
