/**
 * QR-SAR — Official Electronic Document PDF Exporter
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Mengonversi lembar naskah dinas (.page-sheet) menjadi dokumen elektronik PDF A4 Portrait
 * yang tajam, presisi, 100% identik dengan tampilan Print Preview, dan anti-terpotong.
 *
 * Spesifikasi Margin Resmi:
 * - Halaman 1: Margin Atas 10mm (Tinggi area cetak = 275mm)
 * - Halaman Seterusnya (2+): Margin Atas 15mm (Tinggi area cetak = 270mm)
 * - Margin Kiri Visual: 15mm, Margin Kanan Visual: 15mm
 * - Margin Bawah: 12mm
 */
(function () {
    'use strict';

    function sanitizeFilename(raw) {
        return String(raw || 'DOKUMEN_OPERASI_SAR')
            .replace(/[^a-zA-Z0-9_\-.]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_|_$/g, '');
    }

    /**
     * Memindai baris piksel kanvas di dekat batas bawah halaman untuk mencari
     * celah spasi putih antar-baris teks agar huruf tidak terbelah dua saat ganti halaman.
     */
    function findSafePageCutY(mainCtx, canvasWidth, targetCutY, minCutY) {
        var scanRange = Math.min(96, Math.max(0, targetCutY - minCutY));
        if (scanRange <= 4) {
            return targetCutY;
        }

        var startY = targetCutY - scanRange;
        var imgData;
        try {
            imgData = mainCtx.getImageData(0, startY, canvasWidth, scanRange + 1);
        } catch (e) {
            return targetCutY;
        }

        var data = imgData.data;
        var marginX = Math.min(24, Math.floor(canvasWidth * 0.03));
        var bestDy = scanRange;
        var minDark = Infinity;

        for (var dy = scanRange; dy >= 0; dy--) {
            var darkCount = 0;
            var rowOffset = dy * canvasWidth * 4;
            for (var x = marginX; x < canvasWidth - marginX; x++) {
                var idx = rowOffset + (x * 4);
                if (data[idx] < 225 || data[idx + 1] < 225 || data[idx + 2] < 225) {
                    darkCount++;
                }
            }

            if (darkCount === 0) {
                bestDy = dy;
                minDark = 0;
                break;
            }
            if (darkCount < minDark) {
                minDark = darkCount;
                bestDy = dy;
            }
        }

        return startY + bestDy;
    }

    /**
     * Mem-patch html2pdf.Worker.prototype.toPdf dengan aman menggunakan origToPdf
     * untuk menginisialisasi instance internal jsPDF, lalu memotong halaman sesuai
     * spesifikasi margin (Halaman 1 top 10mm, Halaman 2+ top 15mm) dengan zona aman kanan.
     */
    function patchHtml2PdfWorker() {
        if (typeof html2pdf === 'undefined' || !html2pdf.Worker || html2pdf.Worker.prototype.__customMarginPatched) {
            return;
        }
        var origToPdf = html2pdf.Worker.prototype.toPdf;
        html2pdf.Worker.prototype.__customMarginPatched = true;

        html2pdf.Worker.prototype.toPdf = function () {
            return this.thenList([
                function () {
                    return this.prop.canvas || this.toCanvas();
                },
                function () {
                    // Biarkan origToPdf bawaan html2pdf menginisialisasi this.prop.pdf (n.jsPDF internal)
                    // dengan kanvas dummy tinggi 0px sehingga loop halaman bawaan tidak dieksekusi (s = 0).
                    var realCanvas = this.prop.canvas;
                    var prevEnableLinks = this.opt.enableLinks;
                    this.prop.canvas = {
                        width: (realCanvas && realCanvas.width) ? realCanvas.width : 100,
                        height: 0
                    };
                    this.opt.enableLinks = false;

                    return origToPdf.call(this).then(function () {
                        this.prop.canvas = realCanvas;
                        this.opt.enableLinks = prevEnableLinks;
                    }.bind(this));
                }
            ]).then(function () {
                var canvas = this.prop.canvas;
                var opt = this.opt;
                var pdf = this.prop.pdf;
                if (!canvas || !pdf) {
                    return;
                }

                var totalHeightPx = canvas.height;
                var canvasWidth = canvas.width;
                if (totalHeightPx <= 0 || canvasWidth <= 0) {
                    return;
                }

                // Kanvas container memiliki lebar 188mm (leftMargin = 13mm, rightMargin = 9mm)
                // dengan padding internal .page-sheet kiri 6px (~1.6mm) dan kanan 24px (~6.3mm).
                // Sehingga posisi konten visual di atas kertas A4 (210mm) adalah:
                // - Margin Kiri Visual  = 13mm + 1.6mm = ~14.6mm (15mm)
                // - Margin Kanan Visual = 9mm  + 6.3mm = ~15.3mm (15mm)
                // - Tersedia 24px (6.3mm) ruang putih di dalam sisi kanan kanvas agar border kanan tabel
                //   dan teks rata kanan-kiri (justify) TIDAK PERNAH terpotong oleh batas kanvas!
                var pageWidthMm = 210;
                var pageHeightMm = 297;
                var leftMarginMm = (opt.margin && opt.margin[1] != null) ? opt.margin[1] : 13;
                var rightMarginMm = (opt.margin && opt.margin[3] != null) ? opt.margin[3] : 9;
                var bottomMarginMm = (opt.margin && opt.margin[2] != null) ? opt.margin[2] : 12;
                var innerWidthMm = pageWidthMm - leftMarginMm - rightMarginMm; // 188mm

                // Halaman 1: Margin Atas 10mm (Tinggi area konten = 275mm)
                // Halaman 2+: Margin Atas 15mm (Tinggi area konten = 270mm)
                var topMarginPage1 = 10;
                var topMarginPageNext = 15;

                var innerHeightPage1Mm = pageHeightMm - topMarginPage1 - bottomMarginMm; // 275mm
                var innerHeightPageNextMm = pageHeightMm - topMarginPageNext - bottomMarginMm; // 270mm

                var maxSlicePxPage1 = Math.floor(canvasWidth * (innerHeightPage1Mm / innerWidthMm));
                var maxSlicePxPageNext = Math.floor(canvasWidth * (innerHeightPageNextMm / innerWidthMm));

                var mainCtx = canvas.getContext('2d');
                var sliceCanvas = document.createElement('canvas');
                sliceCanvas.width = canvasWidth;
                var sliceCtx = sliceCanvas.getContext('2d');

                var currentY = 0;
                var pageIndex = 0;

                while (currentY < totalHeightPx) {
                    var maxSliceH = (pageIndex === 0) ? maxSlicePxPage1 : maxSlicePxPageNext;
                    var remainingH = totalHeightPx - currentY;

                    if (pageIndex > 0 && remainingH <= 6) {
                        break;
                    }

                    var actualSliceH;
                    if (remainingH <= maxSliceH + 4) {
                        actualSliceH = remainingH;
                    } else {
                        var targetCutY = currentY + maxSliceH;
                        var minCutY = currentY + Math.floor(maxSliceH * 0.75);
                        var safeCutY = mainCtx
                            ? findSafePageCutY(mainCtx, canvasWidth, targetCutY, minCutY)
                            : targetCutY;
                        actualSliceH = Math.max(100, safeCutY - currentY);
                    }

                    var topMarginMm = (pageIndex === 0) ? topMarginPage1 : topMarginPageNext;
                    var renderHeightMm = (actualSliceH * innerWidthMm) / canvasWidth;

                    sliceCanvas.height = actualSliceH;
                    sliceCtx.fillStyle = '#ffffff';
                    sliceCtx.fillRect(0, 0, canvasWidth, actualSliceH);
                    sliceCtx.drawImage(
                        canvas,
                        0,
                        currentY,
                        canvasWidth,
                        actualSliceH,
                        0,
                        0,
                        canvasWidth,
                        actualSliceH
                    );

                    if (pageIndex > 0) {
                        pdf.addPage();
                    }
                    var imgData = sliceCanvas.toDataURL('image/' + opt.image.type, opt.image.quality);
                    pdf.addImage(
                        imgData,
                        opt.image.type,
                        leftMarginMm,
                        topMarginMm,
                        innerWidthMm,
                        renderHeightMm
                    );

                    currentY += actualSliceH;
                    pageIndex++;
                }
            });
        };
    }

    window.downloadOfficialPdf = function (customFilename, buttonEl) {
        const sheet = document.querySelector('.page-sheet');
        if (!sheet) {
            alert('Elemen lembar dokumen (.page-sheet) tidak ditemukan.');
            return;
        }

        if (typeof html2pdf === 'undefined') {
            alert('Pustaka PDF generator sedang dimuat atau tidak tersedia. Membuka dialog cetak browser...');
            window.print();
            return;
        }

        patchHtml2PdfWorker();

        const btn = buttonEl || document.getElementById('btnDownloadPdf');
        const originalBtnHtml = btn ? btn.innerHTML : '';
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="inline-flex items-center gap-1.5">⏳ <span>Memproses PDF...</span></span>';
        }

        const rawName = customFilename
            || (btn && btn.getAttribute('data-pdf-filename'))
            || document.title
            || 'DOKUMEN_RESMI_BASARNAS';
        const finalFilename = sanitizeFilename(rawName.replace(/\.pdf$/i, '')) + '.pdf';

        const isLaporanAwal = (sheet.id === 'official-laporan-awal-doc');
        const printFontSize = isLaporanAwal ? '10pt' : '9.5pt';
        const printLineHeight = (sheet.id === 'official-renops-doc') ? '1.3' : '1.35';

        // 1. Simpan posisi scroll window saat ini dan reset ke (0, 0)
        const prevScrollX = window.scrollX || window.pageXOffset || 0;
        const prevScrollY = window.scrollY || window.pageYOffset || 0;
        window.scrollTo(0, 0);

        // 2. Simpan state style inline asli sebelum normalisasi dimensi cetak
        const prevTransform = sheet.style.transform;
        const prevTransformOrigin = sheet.style.transformOrigin;
        const prevBoxShadow = sheet.style.boxShadow;
        const prevBorder = sheet.style.border;
        const prevMargin = sheet.style.margin;
        const prevPadding = sheet.style.padding;
        const prevWidth = sheet.style.width;
        const prevMaxWidth = sheet.style.maxWidth;
        const prevMinHeight = sheet.style.minHeight;
        const prevBoxSizing = sheet.style.boxSizing;
        const prevOverflow = sheet.style.overflow;
        const prevOverflowX = sheet.style.overflowX;
        const prevFontSize = sheet.style.fontSize;
        const prevLineHeight = sheet.style.lineHeight;

        // 3. Gunakan width: 100% dari container html2pdf (188mm) dengan padding kanan 24px
        // sehingga terdapat ruang penyangga putih 24px di sebelah kanan di dalam kanvas itu sendiri.
        // Ini menjamin border kanan tabel dan huruf paling kanan tidak pernah terpotong oleh html2canvas.
        sheet.style.width = '100%';
        sheet.style.maxWidth = '100%';
        sheet.style.boxSizing = 'border-box';
        sheet.style.transform = 'none';
        sheet.style.border = 'none';
        sheet.style.boxShadow = 'none';
        sheet.style.margin = '0';
        sheet.style.minHeight = 'auto';
        sheet.style.padding = '0 24px 0 6px';
        sheet.style.overflow = 'visible';
        sheet.style.overflowX = 'visible';
        sheet.style.fontSize = printFontSize;
        sheet.style.lineHeight = printLineHeight;

        const opt = {
            margin: [10, 13, 12, 9], // [top, left, bottom, right] dalam mm -> innerWidth = 188mm
            filename: finalFilename,
            image: {
                type: 'jpeg',
                quality: 0.92
            },
            html2canvas: {
                scale: 2,
                useCORS: true,
                letterRendering: true,
                scrollX: 0,
                scrollY: 0,
                backgroundColor: '#ffffff',
                logging: false,
                onclone: function (clonedDoc) {
                    try {
                        const styleTag = clonedDoc.createElement('style');
                        styleTag.textContent = `
                            html, body {
                                background: #ffffff !important;
                                color: #000000 !important;
                                font-family: Arial, "Helvetica Neue", Helvetica, sans-serif !important;
                                font-size: ${printFontSize} !important;
                                line-height: ${printLineHeight} !important;
                                margin: 0 !important;
                                padding: 0 !important;
                            }
                            .no-print, .no-print * {
                                display: none !important;
                            }
                            .page-sheet {
                                width: 100% !important;
                                max-width: 100% !important;
                                min-height: auto !important;
                                box-sizing: border-box !important;
                                margin: 0 !important;
                                padding: 0 24px 0 6px !important;
                                box-shadow: none !important;
                                border: none !important;
                                overflow: visible !important;
                                overflow-x: visible !important;
                                word-wrap: break-word !important;
                                overflow-wrap: break-word !important;
                                font-size: ${printFontSize} !important;
                                line-height: ${printLineHeight} !important;
                            }
                            .page-sheet *, .page-sheet *::before, .page-sheet *::after {
                                box-sizing: border-box !important;
                                max-width: 100% !important;
                            }
                            .page-sheet table {
                                width: 100% !important;
                                max-width: 100% !important;
                                table-layout: fixed !important;
                                border-collapse: collapse !important;
                            }
                            .page-sheet th, .page-sheet td {
                                word-wrap: break-word !important;
                                overflow-wrap: break-word !important;
                                word-break: break-word !important;
                                white-space: normal !important;
                            }
                        `;
                        clonedDoc.head.appendChild(styleTag);
                    } catch (styleErr) {
                        console.warn('Gagal menyuntikkan style print:', styleErr);
                    }

                    // Pastikan seluruh <canvas> (QR Code) tersalin sebagai <img> tajam
                    try {
                        const originalCanvases = sheet.querySelectorAll('canvas');
                        const clonedCanvases = clonedDoc.querySelectorAll('canvas');
                        originalCanvases.forEach(function (origCv, i) {
                            const targetCv = clonedCanvases[i];
                            if (targetCv && origCv.width > 0 && origCv.height > 0) {
                                try {
                                    const img = clonedDoc.createElement('img');
                                    img.src = origCv.toDataURL('image/png');
                                    img.style.width = (origCv.style.width || (origCv.width + 'px'));
                                    img.style.height = (origCv.style.height || (origCv.height + 'px'));
                                    img.className = origCv.className;
                                    img.setAttribute('alt', 'QR Code');
                                    if (targetCv.parentNode) {
                                        targetCv.parentNode.replaceChild(img, targetCv);
                                    }
                                } catch (cvErr) {
                                    console.warn('Gagal mengkloning kanvas ke img:', cvErr);
                                }
                            }
                        });
                    } catch (cloneErr) {
                        console.warn('Error onclone canvas handler:', cloneErr);
                    }
                }
            },
            jsPDF: {
                unit: 'mm',
                format: 'a4',
                orientation: 'portrait',
                compress: true
            },
            pagebreak: {
                mode: ['css', 'legacy'],
                avoid: ['.break-inside-avoid', 'tr']
            }
        };

        const restoreStylesAndState = function () {
            sheet.style.transform = prevTransform;
            sheet.style.transformOrigin = prevTransformOrigin;
            sheet.style.boxShadow = prevBoxShadow;
            sheet.style.border = prevBorder;
            sheet.style.margin = prevMargin;
            sheet.style.padding = prevPadding;
            sheet.style.width = prevWidth;
            sheet.style.maxWidth = prevMaxWidth;
            sheet.style.minHeight = prevMinHeight;
            sheet.style.boxSizing = prevBoxSizing;
            sheet.style.overflow = prevOverflow;
            sheet.style.overflowX = prevOverflowX;
            sheet.style.fontSize = prevFontSize;
            sheet.style.lineHeight = prevLineHeight;

            window.scrollTo(prevScrollX, prevScrollY);

            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        };

        html2pdf()
            .set(opt)
            .from(sheet)
            .save()
            .then(function () {
                restoreStylesAndState();
            })
            .catch(function (err) {
                console.error('Gagal mengekspor PDF:', err);
                restoreStylesAndState();
                alert('Gagal membuat PDF otomatis: ' + (err && err.message ? err.message : err));
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        patchHtml2PdfWorker();
        const params = new URLSearchParams(window.location.search);
        if (params.get('download_pdf') === '1') {
            setTimeout(function () {
                const btn = document.getElementById('btnDownloadPdf');
                window.downloadOfficialPdf(null, btn);
            }, 650);
        }
    });
})();
