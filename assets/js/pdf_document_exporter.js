/**
 * QR-SAR — Official Electronic Document PDF Exporter
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Mengonversi lembar-lembar Dokumen Rencana Operasi SAR (SMEAC, .page-sheet) menjadi satu PDF A4 yang tajam
 * dan anti-terpotong. Tiap lembar memakai orientasinya sendiri (data-pdf-orientation: portrait/landscape),
 * margin visual ~20mm (bawah 18mm) di semua halaman, plus header berjalan (data-pdf-header) & "Halaman X dari Y".
 */
(function () {
    'use strict';

    function sanitizeFilename(raw) {
        return String(raw || 'DOKUMEN_OPERASI_SAR')
            .replace(/[^a-zA-Z0-9_\-.]+/g, '_')
            .replace(/_+/g, '_')
            .replace(/^_|_$/g, '');
    }

    function showPdfAlert(title, text, icon) {
        if (typeof window.Swal !== 'undefined' && typeof window.Swal.fire === 'function') {
            window.Swal.fire({
                icon: icon || 'error',
                title: title || 'Informasi PDF',
                text: text || '',
                confirmButtonText: 'Tutup',
                confirmButtonColor: '#ea580c',
                background: '#0f172a',
                color: '#f8fafc'
            });
        }
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

                // Padding internal .page-sheet kiri 6px & kanan 24px: border kanan tabel dan teks justify tidak terpotong kanvas.
                // Landscape (data-pdf-orientation="landscape"): 297x210, margin atas sama di semua halaman.
                var landscape = !!(opt.jsPDF && opt.jsPDF.orientation === 'landscape');
                // Lembar berikutnya dalam satu PDF: halaman pertamanya juga halaman baru.
                var appendFirst = !!window.__pdfAppend;
                window.__pdfAppend = false;
                var pageWidthMm = landscape ? 297 : 210;
                var pageHeightMm = landscape ? 210 : 297;
                var leftMarginMm = (opt.margin && opt.margin[1] != null) ? opt.margin[1] : 13;
                var rightMarginMm = (opt.margin && opt.margin[3] != null) ? opt.margin[3] : 9;
                var bottomMarginMm = (opt.margin && opt.margin[2] != null) ? opt.margin[2] : 12;
                var innerWidthMm = pageWidthMm - leftMarginMm - rightMarginMm; // 188mm

                // Margin atas seragam di semua halaman.
                var topMarginPage1 = opt.margin[0];
                var topMarginPageNext = opt.margin[0];

                var innerHeightPage1Mm = pageHeightMm - topMarginPage1 - bottomMarginMm; // 275mm
                var innerHeightPageNextMm = pageHeightMm - topMarginPageNext - bottomMarginMm; // 270mm

                var maxSlicePxPage1 = Math.floor(canvasWidth * (innerHeightPage1Mm / innerWidthMm));
                var maxSlicePxPageNext = Math.floor(canvasWidth * (innerHeightPageNextMm / innerWidthMm));

                var mainCtx = canvas.getContext('2d');
                var sliceCanvas = document.createElement('canvas');
                sliceCanvas.width = canvasWidth;
                var sliceCtx = sliceCanvas.getContext('2d');

                var forbiddenZonesPx = (window.__pdfForbiddenZones || []).map(function (z) {
                    return {
                        top: Math.floor(z.topRatio * totalHeightPx),
                        bottom: Math.ceil(z.bottomRatio * totalHeightPx)
                    };
                });

                var currentY = 0;
                var pageIndex = 0;
                // Landscape: sisa yang hanya berisi padding bawah 25mm (pelindung tanda tangan) tidak dijadikan halaman kosong.
                var tailPx = Math.ceil(canvasWidth * 25 / innerWidthMm) + 6;

                while (currentY < totalHeightPx) {
                    var maxSliceH = (pageIndex === 0) ? maxSlicePxPage1 : maxSlicePxPageNext;
                    var remainingH = totalHeightPx - currentY;

                    if (pageIndex > 0 && remainingH <= tailPx) {
                        break;
                    }

                    var actualSliceH;
                    if (remainingH <= maxSliceH + 4) {
                        actualSliceH = remainingH;
                    } else {
                        var targetCutY = currentY + maxSliceH;

                        // Periksa apakah target pemotongan jatuh di dalam blok tanda tangan (zona terlarang)
                        var insideForbidden = null;
                        for (var f = 0; f < forbiddenZonesPx.length; f++) {
                            var zone = forbiddenZonesPx[f];
                            if (targetCutY > zone.top && targetCutY <= zone.bottom) {
                                insideForbidden = zone;
                                break;
                            }
                        }

                        if (insideForbidden) {
                            // Potong persis di atas blok tanda tangan agar seluruh blok tanda tangan
                            // (judul legalisasi, QR, nama, pangkat, dan NIP SMC) pindah bersama ke halaman berikutnya
                            if (insideForbidden.top - currentY >= 90) {
                                actualSliceH = insideForbidden.top - currentY;
                            } else {
                                // Jika blok tanda tangan sudah dimulai di awal halaman, pastikan muat hingga batas bawah blok
                                actualSliceH = Math.min(remainingH, insideForbidden.bottom - currentY);
                            }
                        } else {
                            var minCutY = currentY + Math.floor(maxSliceH * 0.75);
                            var safeCutY = mainCtx
                                ? findSafePageCutY(mainCtx, canvasWidth, targetCutY, minCutY)
                                : targetCutY;

                            // Lindungi agar safeCutY tidak memotong ke dalam zona tanda tangan
                            for (var f2 = 0; f2 < forbiddenZonesPx.length; f2++) {
                                var z2 = forbiddenZonesPx[f2];
                                if (safeCutY > z2.top && safeCutY <= z2.bottom) {
                                    safeCutY = z2.top;
                                    break;
                                }
                            }
                            actualSliceH = Math.max(100, safeCutY - currentY);
                        }
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

                    if (pageIndex > 0 || appendFirst) {
                        pdf.addPage('a4', landscape ? 'landscape' : 'portrait');
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

    /** Header berjalan & "Halaman X dari Y" untuk RENOPS SMEAC (@page margin box tidak ikut html2canvas); ukuran per halaman. */
    function stampHeaderFooter(pdf, runningHeader) {
        var totalPages = pdf.internal.getNumberOfPages();
        pdf.setFontSize(8);
        pdf.setTextColor(89, 89, 89);
        pdf.setDrawColor(166, 166, 166);
        pdf.setLineWidth(0.2);
        for (var p = 1; p <= totalPages; p++) {
            pdf.setPage(p);
            var w = pdf.internal.pageSize.getWidth();
            var h = pdf.internal.pageSize.getHeight();
            if (runningHeader) {
                pdf.text(runningHeader, w - 20, 13, { align: 'right' });
                pdf.line(20, 14.5, w - 20, 14.5);
            }
            pdf.text('Halaman ' + p + ' dari ' + totalPages, w / 2, h - 9, { align: 'center' });
        }
    }

    window.downloadOfficialPdf = function (customFilename, buttonEl) {
        const sheet = document.querySelector('.page-sheet');
        if (!sheet) {
            showPdfAlert('Dokumen Tidak Ditemukan', 'Elemen lembar dokumen (.page-sheet) tidak ditemukan.', 'error');
            return;
        }

        const btn = buttonEl || document.getElementById('btnDownloadPdf');
        const originalBtnHtml = btn ? btn.innerHTML : '';
        const rawName = customFilename
            || (btn && btn.getAttribute('data-pdf-filename'))
            || document.title
            || 'DOKUMEN_RESMI_BASARNAS';
        const finalFilename = sanitizeFilename(rawName.replace(/\.pdf$/i, '')) + '.pdf';

        // Prioritaskan Engine PDF Vektor Sisi Server (Chrome Headless --print-to-pdf) agar teks dapat dicari (find)
        // dan tampilan 100% identik dengan Cetak PDF (window.print).
        const vectorPdfUrl = btn ? btn.getAttribute('data-pdf-url') : '';
        if (vectorPdfUrl) {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="inline-flex items-center gap-1.5">⏳ <span>Menyiapkan PDF...</span></span>';
            }
            fetch(vectorPdfUrl, { credentials: 'same-origin' })
                .then(function (res) {
                    const ct = (res.headers.get('content-type') || '').toLowerCase();
                    if (res.ok && ct.indexOf('application/pdf') !== -1) {
                        return res.blob();
                    }
                    throw new Error('VECTOR_PDF_UNAVAILABLE');
                })
                .then(function (blob) {
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = finalFilename;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 5000);
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalBtnHtml;
                    }
                })
                .catch(function () {
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalBtnHtml;
                    }
                    // Fallback ke engine cetak vektor bawaan browser agar teks tetap dapat dicari (find)
                    const prevTitle = document.title;
                    document.title = finalFilename.replace(/\.pdf$/i, '');
                    window.print();
                    setTimeout(function () { document.title = prevTitle; }, 1000);
                });
            return;
        }

        if (typeof html2pdf === 'undefined') {
            showPdfAlert('Membuka Dialog Cetak', 'Pustaka PDF generator sedang dimuat atau tidak tersedia. Membuka dialog cetak browser...', 'info');
            window.print();
            return;
        }

        patchHtml2PdfWorker();

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="inline-flex items-center gap-1.5">⏳ <span>Memproses PDF...</span></span>';
        }

        // Deteksi koordinat vertikal elemen blok tanda tangan agar dilarang dipotong di tengah
        const sigSelector = '.signature-block';
        const zonesOf = function (el) {
            const rect = el.getBoundingClientRect();
            if (!(rect.height > 0)) return [];
            return Array.prototype.map.call(el.querySelectorAll(sigSelector), function (sb) {
                const r = sb.getBoundingClientRect();
                return {
                    topRatio: Math.max(0, (r.top - rect.top) / rect.height),
                    bottomRatio: Math.min(1, (r.bottom - rect.top) / rect.height)
                };
            });
        };

        // Beberapa lembar, tiap lembar orientasinya sendiri (isi portrait, struktur organisasi landscape), satu PDF.
        window.__pdfRunningHeader = sheet.dataset.pdfHeader || '';
        const sheets = Array.prototype.slice.call(document.querySelectorAll('.page-sheet'));
        const isLandscape = function (el) { return el.dataset.pdfOrientation === 'landscape'; };

        const printFontFamily = "'Times New Roman', Times, serif";
        const printFontSize = '12pt';
        const printLineHeight = '1.45';

        // 1. Simpan posisi scroll window saat ini dan reset ke (0, 0)
        const prevScrollX = window.scrollX || window.pageXOffset || 0;
        const prevScrollY = window.scrollY || window.pageYOffset || 0;
        window.scrollTo(0, 0);

        // 2-3. Simpan style inline asli lalu normalisasi dimensi cetak tiap lembar:
        // width 100% dari container html2pdf, padding kanan 24px & bawah 25mm (pelindung tanda tangan/NIP SMC).
        const STYLE_KEYS = ['transform', 'transformOrigin', 'boxShadow', 'border', 'margin', 'padding', 'width', 'maxWidth',
            'minHeight', 'boxSizing', 'overflow', 'overflowX', 'fontFamily', 'fontSize', 'lineHeight', 'zoom', 'breakBefore', 'pageBreakBefore'];
        const restores = sheets.map(function (el) {
            const prev = {};
            STYLE_KEYS.forEach(function (k) { prev[k] = el.style[k]; });
            Object.assign(el.style, {
                width: '100%', maxWidth: '100%', boxSizing: 'border-box', transform: 'none', border: 'none',
                boxShadow: 'none', margin: '0', minHeight: 'auto', padding: '0 24px 25mm 6px', overflow: 'visible',
                overflowX: 'visible', fontFamily: printFontFamily, fontSize: printFontSize, lineHeight: printLineHeight,
                // Zoom muat-pas-lebar layar (RENOPS) tidak boleh ikut ke kanvas html2pdf.
                zoom: '1',
                // Ganti halaman antarlembar diatur exporter; break-before cetak tidak boleh jadi spasi kosong html2pdf.
                breakBefore: 'auto',
                pageBreakBefore: 'auto'
            });
            return function () { STYLE_KEYS.forEach(function (k) { el.style[k] = prev[k]; }); };
        });

        // Satu kanvas per lembar: Safari iOS membatasi ~16,7 juta piksel (lebih besar = halaman kosong).
        // Turunkan skala untuk lembar panjang agar tetap di bawah batas (skala 2 hanya untuk lembar pendek).
        const MAX_CANVAS_PIXELS = 16000000;
        const optFor = function (el) {
            const sheetArea = Math.max(1, el.scrollWidth * el.scrollHeight);
            const canvasScale = Math.max(1, Math.min(2, Math.sqrt(MAX_CANVAS_PIXELS / sheetArea)));
            return {
                // [top, left, bottom, right] dalam mm: kiri 18 + padding 6px, kanan 14 + padding 24px -> margin visual ~20mm.
                margin: [20, 18, 18, 14],
                filename: finalFilename,
                image: {
                    type: 'jpeg',
                    quality: 0.95
                },
                html2canvas: {
                    scale: canvasScale,
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
                                font-family: ${printFontFamily} !important;
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
                                padding: 0 24px 25mm 6px !important;
                                box-shadow: none !important;
                                border: none !important;
                                overflow: visible !important;
                                overflow-x: visible !important;
                                word-wrap: break-word !important;
                                overflow-wrap: break-word !important;
                                font-family: ${printFontFamily} !important;
                                font-size: ${printFontSize} !important;
                                line-height: ${printLineHeight} !important;
                                text-align: justify !important;
                                text-justify: inter-word !important;
                            }
                            .page-sheet p, .page-sheet li {
                                font-family: ${printFontFamily} !important;
                                font-size: 10.5pt !important;
                                text-align: justify !important;
                                text-justify: inter-word !important;
                                line-height: 1.4 !important;
                            }
                            .page-sheet h1, .page-sheet h2, .page-sheet h3 {
                                font-family: ${printFontFamily} !important;
                                font-size: 12pt !important;
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
                                font-family: ${printFontFamily} !important;
                            }
                            .table-dinas {
                                font-size: 9pt !important;
                            }
                            .table-dinas th {
                                border: 1px solid #000000 !important;
                                background-color: #f1f5f9 !important;
                                font-weight: bold !important;
                                font-size: 8.5pt !important;
                                padding: 4px 6px !important;
                                text-align: center !important;
                                vertical-align: middle !important;
                                text-transform: uppercase !important;
                                font-family: ${printFontFamily} !important;
                            }
                            .table-dinas td {
                                border: 1px solid #000000 !important;
                                font-size: 9pt !important;
                                padding: 4px 6px !important;
                                vertical-align: top !important;
                                line-height: 1.3 !important;
                                word-wrap: break-word !important;
                                overflow-wrap: break-word !important;
                                word-break: break-word !important;
                                font-family: ${printFontFamily} !important;
                            }
                            .page-sheet th, .page-sheet td {
                                word-wrap: break-word !important;
                                overflow-wrap: break-word !important;
                                word-break: break-word !important;
                                font-family: ${printFontFamily} !important;
                            }
                            .signature-block {
                                page-break-inside: avoid !important;
                                break-inside: avoid !important;
                            }
                        `;
                            clonedDoc.head.appendChild(styleTag);
                        } catch (styleErr) {
                            console.warn('Gagal menyuntikkan style print:', styleErr);
                        }

                        // Salinan html2pdf = .page-sheet terakhir di dokumen kloning.
                        const cSheets = clonedDoc.querySelectorAll('.page-sheet');
                        const cSheet = cSheets[cSheets.length - 1];

                        // Ukur ulang zona tanda tangan pada tata letak ekspor, karena lembar mengalir ulang
                        // cukup jauh dari tampilan layar.
                        if (cSheet) {
                            try {
                                window.__pdfForbiddenZones = zonesOf(cSheet);
                            } catch (zoneErr) {
                                console.warn('Gagal mengukur ulang zona tanda tangan:', zoneErr);
                            }
                        }

                        // SVG inline (mis. diagram jaring komunikasi) berukuran CSS: html2canvas menggambarnya pada ukuran
                        // viewBox asli lalu memotongnya. Tetapkan atribut width/height = ukuran tampil di salinan.
                        if (cSheet) {
                            Array.prototype.forEach.call(cSheet.querySelectorAll('svg'), function (svg) {
                                const r = svg.getBoundingClientRect();
                                if (r.width > 0 && r.height > 0) {
                                    svg.setAttribute('width', String(Math.round(r.width)));
                                    svg.setAttribute('height', String(Math.round(r.height)));
                                }
                            });
                        }

                        // Pastikan seluruh <canvas> (QR Code) lembar ini tersalin sebagai <img> tajam
                        try {
                            const originalCanvases = el.querySelectorAll('canvas');
                            const clonedCanvases = cSheet ? cSheet.querySelectorAll('canvas') : [];
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
                    orientation: isLandscape(el) ? 'landscape' : 'portrait',
                    compress: true
                },
                pagebreak: {
                    mode: ['css', 'legacy'],
                    avoid: ['.break-inside-avoid', 'tr']
                }
            };
        };

        const restoreStylesAndState = function () {
            restores.forEach(function (fn) { fn(); });
            window.__pdfAppend = false;
            window.scrollTo(prevScrollX, prevScrollY);

            if (btn) {
                btn.disabled = false;
                btn.innerHTML = originalBtnHtml;
            }
        };

        window.__pdfAppend = false;
        window.__pdfForbiddenZones = zonesOf(sheets[0]);
        let worker = html2pdf().set(optFor(sheets[0])).from(sheets[0]).toPdf();
        sheets.slice(1).forEach(function (el) {
            worker = worker.then(function () {
                // Lembar berikut: kanvas & container baru, halaman pertamanya halaman baru di PDF yang sama.
                this.prop.canvas = null;
                this.prop.container = null;
                window.__pdfAppend = true;
                window.__pdfForbiddenZones = zonesOf(el);
            }).set(optFor(el)).from(el).toPdf();
        });
        worker = worker.get('pdf').then(function (pdf) {
            stampHeaderFooter(pdf, window.__pdfRunningHeader);
        });
        worker
            .save()
            .then(function () {
                restoreStylesAndState();
            })
            .catch(function (err) {
                console.error('Gagal mengekspor PDF:', err);
                restoreStylesAndState();
                showPdfAlert('Gagal Membuat PDF', 'Gagal membuat PDF otomatis: ' + (err && err.message ? err.message : err), 'error');
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
