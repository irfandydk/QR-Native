<?php
/**
 * QR-SAR - Halaman cetak / publik Dokumen Rencana Operasi SAR (SMEAC, A4 landscape).
 * Merender versi TERBIT (konten beku) lewat partial renops_smeac_doc.php mode 'final',
 * atau pratinjau draf ($mode 'pratinjau', $versiLabel 'DRAF') sebelum QR terbit.
 *
 * Variabel: $doc (Kontrak Data Dokumen), $qrUrl (string URL verifikasi versi ini), $versiLabel (mis. "v2"),
 * $isPublic (bool: dibuka lewat token tanpa login), $backUrl (?string: tautan kembali, null = tanpa tautan),
 * $digantikanOleh (?array{versi:int, url:string}: versi ini DIGANTIKAN, tautan ke versi TERBIT terbaru).
 */

declare(strict_types=1);

$mode ??= 'final';
$pMeta = $doc['meta'] ?? [];
$pHari = (string)($pMeta['hari_label'] ?? '');
$pNama = (string)($pMeta['nama_operasi'] ?? '');
$pdfName = 'RENOPS_H' . $pHari . '_' . $versiLabel . '_OPS_' . (int)($pMeta['id_operasi'] ?? 0) . '_' . $pNama;
if (!empty($isPublic) && !empty($_GET['token'])) {
    $pdfDownloadUrl = BASE_URL . '/operasi.php?action=renops_publik&token=' . rawurlencode((string)$_GET['token']) . '&format=pdf';
} else {
    $idRenopsParam = (int)($pMeta['id_renops'] ?? ($_GET['id_renops'] ?? 0));
    $idOpsParam = (int)($pMeta['id_operasi'] ?? ($_GET['id'] ?? 0));
    $drafParam = ($mode === 'pratinjau' || !empty($_GET['draf'])) ? '&draf=1' : '';
    $pdfDownloadUrl = BASE_URL . '/operasi.php?action=print_renops&id=' . $idOpsParam . '&id_renops=' . $idRenopsParam . $drafParam . '&format=pdf';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($isPublic): ?><meta name="robots" content="noindex, nofollow"><?php endif; ?>
    <title>Rencana Operasi SAR Hari ke-<?= e($pHari) ?> - <?= e($pNama) ?> (<?= e($versiLabel) ?>)</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo_basarnas.svg">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/tailwind.css') ?>">
    <link rel="stylesheet" href="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">
    <style>
        body { background-color: #0f172a; }
        .doc-scroll { overflow-x: auto; }
        @media print {
            html, body { background: #ffffff !important; }
            .no-print, .no-print * { display: none !important; }
            .doc-scroll { overflow: visible; padding: 0 !important; }
        }
    </style>
</head>
<body class="antialiased min-h-screen">

    <header class="no-print sticky top-0 z-50 bg-slate-900/95 backdrop-blur border-b border-slate-800 text-white px-4 py-3 shadow-md">
        <div class="max-w-5xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <?php if ($backUrl): ?>
                    <a href="<?= e($backUrl) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                        <span>Kembali</span>
                    </a>
                <?php endif; ?>
                <span class="text-xs font-mono font-bold text-orange-400">RENOPS H.<?= e($pHari) ?> &bull; <?= e($versiLabel) ?></span>
                <span class="text-xs text-slate-300 font-semibold truncate max-w-[200px] sm:max-w-xs"><?= e($pNama) ?></span>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" id="btnDownloadPdf" data-pdf-filename="<?= e($pdfName) ?>" data-pdf-url="<?= e($pdfDownloadUrl) ?>" onclick="downloadOfficialPdf(null, this)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-sm cursor-pointer" title="Unduh berkas PDF resmi (teks tajam &amp; dapat dicari / find, identik dengan hasil cetak)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>Unduh PDF</span>
                </button>
                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition shadow-sm cursor-pointer" title="Naskah resmi: teks tajam &amp; bisa dicari. Pilih &quot;Simpan sebagai PDF&quot; pada dialog cetak.">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak / Simpan PDF</span>
                </button>
            </div>
        </div>
    </header>

    <?php if ($mode === 'pratinjau'): ?>
    <div class="no-print bg-amber-500 text-slate-950 px-4 py-2.5 text-center text-sm font-bold" role="status">
        DRAF &mdash; dokumen belum diterbitkan, tanpa QR verifikasi. Terbitkan QR di editor untuk naskah resmi.
    </div>
    <?php endif; ?>

    <?php if (!empty($digantikanOleh)): ?>
    <div class="no-print bg-amber-500 text-slate-950 px-4 py-2.5 text-center text-sm font-bold" role="alert">
        Versi ini telah digantikan oleh v<?= (int)$digantikanOleh['versi'] ?>.
        <a href="<?= e($digantikanOleh['url']) ?>" class="underline ml-1">Buka versi terbaru</a>
    </div>
    <?php endif; ?>

    <main class="doc-scroll py-4 px-2">
        <?php include __DIR__ . '/partials/renops_smeac_doc.php'; ?>
    </main>

    <script src="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>
    <script src="<?= assetUrl('assets/vendor/qrcode/qrcode.min.js') ?>"></script>
    <script src="<?= assetUrl('assets/vendor/html2pdf/html2pdf.bundle.min.js') ?>"></script>
    <script src="<?= assetUrl('assets/js/pdf_document_exporter.js') ?>"></script>
    <script>
    // Muat pas lebar layar (ponsel setelah pindai QR). Layar saja: @media print & exporter PDF memakai zoom 1.
    (function () {
        const box = document.querySelector('main.doc-scroll');
        const sheets = box ? Array.from(box.querySelectorAll('.page-sheet.renops-smeac')) : [];
        if (!sheets.length) return;
        // Lebar asli tiap lembar (portrait 210mm, landscape 297mm), diukur sebelum zoom.
        const widths = sheets.map((sh) => sh.offsetWidth);
        const supportsZoom = typeof document !== 'undefined' && document.body && ('zoom' in document.body.style);
        const fit = () => sheets.forEach((sh, i) => {
            const ratio = Math.min(1, (box.clientWidth - 16) / widths[i]);
            sh.style.zoom = String(Math.min(1, (box.clientWidth - 16) / widths[i]));
            if (!supportsZoom) {
                sh.style.transform = ratio < 1 ? `scale(${ratio})` : '';
                sh.style.transformOrigin = 'top left';
                sh.style.marginBottom = ratio < 1 ? `-${Math.round((1 - ratio) * sh.offsetHeight)}px` : '';
            }
        });
        fit();
        window.addEventListener('resize', fit);
    })();

    // QR verifikasi berlogo (dipindah dari renops_print.php): canvas digambar lalu disalin ke <img> agar tajam saat cetak/PDF.
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('qrCanvas');
        const imgEl = document.getElementById('qrImg');
        const logoDataUri = <?= jsonSafe(getAppLogoDataUri()) ?>;
        if (!canvas || !canvas.dataset.qrUrl || typeof QRCode === 'undefined') {
            return;
        }
        QRCode.toCanvas(canvas, canvas.dataset.qrUrl, {
            width: 200,
            margin: 1,
            color: { dark: '#0a0f1d', light: '#ffffff' },
            errorCorrectionLevel: 'H'
        }, function (err) {
            if (err) {
                console.warn('QR code generation error:', err);
                return;
            }
            const ctx = canvas.getContext('2d');
            if (!ctx) return;
            const size = canvas.width;
            const logoImg = new Image();

            function drawCenterEmblem() {
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
                    if (imgEl) {
                        const dataUrl = canvas.toDataURL('image/png');
                        imgEl.src = dataUrl;
                        imgEl.classList.remove('qr-off');
                        canvas.classList.add('qr-off');
                    }
                } catch (e) {
                    console.warn('Sync to img error:', e);
                }
            }

            if (logoDataUri) {
                logoImg.onload = drawCenterEmblem;
                logoImg.onerror = drawCenterEmblem;
                logoImg.src = logoDataUri;
            } else {
                drawCenterEmblem();
            }
        });
    });
    </script>
</body>
</html>
