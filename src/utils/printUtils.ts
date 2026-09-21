/**
 * Utility Cetak dan Unduh Dokumen Resmi Basarnas
 * Menjamin 100% konsistensi antara Tampilan Preview dan Hasil Cetak/Download PDF.
 */

import html2pdf from 'html2pdf.js';

export const getPageSizeCss = (pageSize: string = 'A4'): string => {
  switch (pageSize.toUpperCase()) {
    case 'F4':
    case 'FOLIO':
      return '215mm 330mm';
    case 'LETTER':
      return 'letter portrait';
    case 'LEGAL':
      return 'legal portrait';
    case 'A4':
    default:
      return 'A4 portrait';
  }
};

export const getBasePrintStyles = (pageSize: string = 'A4', scale: number = 100) => `
  @page {
    size: ${getPageSizeCss(pageSize)};
    margin: 12mm 12mm 12mm 15mm; /* Standar Tata Naskah Dinas: Atas 12mm, Kanan 12mm, Bawah 12mm, Kiri 15mm */
  }
  *, *::before, *::after {
    box-sizing: border-box;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
  }
  html, body {
    margin: 0;
    padding: 0;
    background: #ffffff !important;
    color: #000000 !important;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 11px;
    line-height: 1.35;
    -webkit-font-smoothing: antialiased;
  }
  .print-wrapper {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 auto;
    background: #ffffff !important;
    color: #000000 !important;
    padding: 0 !important;
    transform-origin: top center;
    ${scale !== 100 ? `transform: scale(${scale / 100});` : ''}
  }
  #official-bra-document {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 auto !important;
    padding: 0 !important;
    border: none !important;
    box-shadow: none !important;
    background: #ffffff !important;
    color: #000000 !important;
    min-height: auto !important;
    height: auto !important;
    transform: none !important;
  }
  .print\\:hidden, .no-print, button {
    display: none !important;
  }

  /* Standalone Grid and Flexbox definitions for print & standalone HTML */
  .grid { display: grid !important; }
  .grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)) !important; }
  .grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)) !important; }
  .grid-cols-12 { grid-template-columns: repeat(12, minmax(0, 1fr)) !important; }
  .col-span-4 { grid-column: span 4 / span 4 !important; }
  .col-span-5 { grid-column: span 5 / span 5 !important; }
  .col-span-7 { grid-column: span 7 / span 7 !important; }
  .col-span-8 { grid-column: span 8 / span 8 !important; }
  .sm\\:col-span-4 { grid-column: span 4 / span 4 !important; }
  .sm\\:col-span-5 { grid-column: span 5 / span 5 !important; }
  .sm\\:col-span-7 { grid-column: span 7 / span 7 !important; }
  .sm\\:col-span-8 { grid-column: span 8 / span 8 !important; }

  .flex { display: flex !important; }
  .flex-col { flex-direction: column !important; }
  .flex-1 { flex: 1 1 0% !important; }
  .items-center { align-items: center !important; }
  .items-start { align-items: flex-start !important; }
  .justify-between { justify-content: space-between !important; }
  .justify-center { justify-content: center !important; }
  .text-center { text-align: center !important; }
  .text-right { text-align: right !important; }
  .text-justify { text-align: justify !important; }
  .shrink-0 { flex-shrink: 0 !important; }
  .uppercase { text-transform: uppercase !important; }
  .font-bold { font-weight: 700 !important; }
  .font-black, .font-extrabold { font-weight: 900 !important; }

  /* Pure black border overrides matching official telegram standard */
  .border, .border-2, .border-t, .border-b, .border-l, .border-r,
  table, tr, td, th {
    border-color: #000000 !important;
  }
  .border-2 { border: 2px solid #000000 !important; }
  .border-t-2 { border-top: 2px solid #000000 !important; }
  .border-b-2 { border-bottom: 2px solid #000000 !important; }
  .border-t { border-top: 1px solid #000000 !important; }
  .border-b { border-bottom: 1px solid #000000 !important; }
  .divide-y-2 > :not([hidden]) ~ :not([hidden]) {
    border-top: 2px solid #000000 !important;
  }
  .divide-x-2 > :not([hidden]) ~ :not([hidden]) {
    border-left: 2px solid #000000 !important;
  }
  .divide-y > :not([hidden]) ~ :not([hidden]) {
    border-top: 1px solid #000000 !important;
  }
  .divide-x > :not([hidden]) ~ :not([hidden]) {
    border-left: 1px solid #000000 !important;
  }

  /* Force background and text */
  p, span, div, h1, h2, h3, h4, strong, td, th {
    color: #000000 !important;
  }
  .bg-white, .bg-slate-900, .bg-slate-850, .bg-slate-800, .bg-slate-50 {
    background-color: #ffffff !important;
  }

  /* Vertical writing mode for SENKOM label */
  .senkom-vertical-label {
    writing-mode: vertical-rl !important;
    transform: rotate(180deg) !important;
    text-orientation: mixed !important;
    white-space: nowrap !important;
    top: 25px !important;
    bottom: auto !important;
    height: 280px !important;
    position: absolute !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }

  /* Multi-page flow: Natural document flow across pages without blank pages */
  .bra-kop {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: auto !important;
    break-after: auto !important;
    margin-bottom: 6px !important;
  }
  .bra-table-wrapper {
    width: 100% !important;
    page-break-inside: auto !important;
    break-inside: auto !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .bra-table-box {
    border: 2px solid #000000 !important;
    box-decoration-break: clone !important;
    -webkit-box-decoration-break: clone !important;
    page-break-inside: auto !important;
    break-inside: auto !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .bra-top-header {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .bra-upper-section {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .bra-no-header {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .bra-body-container,
  .bra-body-content,
  .bra-sections-list {
    page-break-inside: auto !important;
    break-inside: auto !important;
    page-break-after: auto !important;
    break-after: auto !important;
    min-height: 0 !important;
  }
  .bra-section-row {
    page-break-inside: auto !important;
    break-inside: auto !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .bra-section-row > div {
    page-break-inside: auto !important;
    break-inside: auto !important;
    page-break-after: auto !important;
    break-after: auto !important;
    orphans: 2;
    widows: 2;
  }
  .bra-twb-row {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }
  .bra-footer-section {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: auto !important;
    break-after: auto !important;
  }

  /* Utility classes support for print elements */
  .break-inside-avoid {
    page-break-inside: avoid !important;
    break-inside: avoid !important;
  }
  .break-after-auto {
    page-break-after: auto !important;
    break-after: auto !important;
  }

  img {
    max-width: 100%;
    page-break-inside: avoid;
  }
`;

export const printElement = (
  elementId: string,
  docTitle = 'Dokumen Resmi Basarnas',
  pageSize: string = 'A4',
  scale: number = 100
): Promise<boolean> => {
  return new Promise((resolve) => {
    const element = document.getElementById(elementId);
    if (!element) {
      console.error(`Element with id #${elementId} not found.`);
      window.print();
      resolve(false);
      return;
    }

    // Buat iframe tersembunyi khusus untuk proses print
    const existingFrame = document.getElementById('basarnas-print-iframe');
    if (existingFrame) {
      existingFrame.remove();
    }

    const iframe = document.createElement('iframe');
    iframe.id = 'basarnas-print-iframe';
    // Berikan lebar nyata (850px) agar media query desktop (sm:) aktif dan layout sama persis dengan preview
    iframe.style.position = 'fixed';
    iframe.style.left = '-99999px';
    iframe.style.top = '0';
    iframe.style.width = '850px';
    iframe.style.height = '1200px';
    iframe.style.border = 'none';

    document.body.appendChild(iframe);

    const iframeDoc = iframe.contentWindow?.document;
    if (!iframeDoc) {
      window.print();
      resolve(true);
      return;
    }

    // Ambil stylesheet aplikasi yang aktif
    let stylesHtml = '';
    const styleSheets = Array.from(document.styleSheets);
    for (const sheet of styleSheets) {
      try {
        if (sheet.cssRules) {
          const rules = Array.from(sheet.cssRules)
            .map((r) => r.cssText)
            .join('\n');
          stylesHtml += `<style>${rules}</style>`;
        }
      } catch (e) {
        if (sheet.href) {
          stylesHtml += `<link rel="stylesheet" href="${sheet.href}">`;
        }
      }
    }

    iframeDoc.open();
    iframeDoc.write(`
      <!DOCTYPE html>
      <html lang="id">
        <head>
          <meta charset="utf-8">
          <title>${docTitle}</title>
          ${stylesHtml}
          <style>
            ${getBasePrintStyles(pageSize, scale)}
          </style>
        </head>
        <body>
          <div class="print-wrapper">
            ${element.outerHTML}
          </div>
        </body>
      </html>
    `);
    iframeDoc.close();

    // Tunggu gambar (Logo & QR Code) selesai dimuat di iframe sebelum trigger print
    const checkImagesLoaded = () => {
      const images = Array.from(iframeDoc.images);
      return Promise.all(
        images.map(
          (img) =>
            new Promise((imgResolve) => {
              if (img.complete) {
                imgResolve(true);
              } else {
                img.onload = () => imgResolve(true);
                img.onerror = () => imgResolve(true);
              }
            })
        )
      );
    };

    setTimeout(() => {
      checkImagesLoaded().then(() => {
        setTimeout(() => {
          try {
            iframe.contentWindow?.focus();
            iframe.contentWindow?.print();
            resolve(true);
          } catch (err) {
            console.error('Iframe print error, falling back:', err);
            window.print();
            resolve(true);
          } finally {
            setTimeout(() => {
              iframe.remove();
            }, 3000);
          }
        }, 150);
      });
    }, 250);
  });
};

/**
 * Unduh Dokumen sebagai file HTML mandiri yang 100% presisi dan mandiri
 */
export const downloadDocumentHtml = (
  elementId: string,
  filename = 'Dokumen_BRA_SAR.html',
  pageSize: string = 'A4',
  scale: number = 100
) => {
  const element = document.getElementById(elementId);
  if (!element) return;

  // Kumpulkan style aplikasi
  let stylesHtml = '';
  const styleSheets = Array.from(document.styleSheets);
  for (const sheet of styleSheets) {
    try {
      if (sheet.cssRules) {
        const rules = Array.from(sheet.cssRules)
          .map((r) => r.cssText)
          .join('\n');
        stylesHtml += `<style>${rules}</style>`;
      }
    } catch (e) {
      if (sheet.href) {
        stylesHtml += `<link rel="stylesheet" href="${sheet.href}">`;
      }
    }
  }

  const htmlContent = `
    <!DOCTYPE html>
    <html lang="id">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>${filename.replace('.html', '')}</title>
        ${stylesHtml}
        <style>
          ${getBasePrintStyles(pageSize, scale)}
          .action-bar {
            max-width: 820px;
            margin: 15px auto;
            padding: 12px 18px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
          }
          .action-bar button {
            padding: 8px 16px;
            background: #ea580c;
            color: #ffffff;
            font-weight: bold;
            font-size: 13px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
          }
          .action-bar button:hover {
            background: #c2410c;
          }
          @media print {
            .action-bar { display: none !important; }
            .print-wrapper { padding: 0 !important; }
          }
        </style>
      </head>
      <body>
        <div class="action-bar no-print">
          <div>
            <strong style="color: #0f172a; font-size: 14px;">📄 Lembar Dokumen Resmi BRA SAR (${pageSize.toUpperCase()})</strong>
            <p style="margin: 2px 0 0 0; font-size: 11px; color: #64748b;">Standar Format Korps Basarnas • Siap Dicetak ke PDF</p>
          </div>
          <button onclick="window.print()">
            🖨️ Cetak / Simpan PDF
          </button>
        </div>
        <div class="print-wrapper">
          ${element.outerHTML}
        </div>
      </body>
    </html>
  `;

  const blob = new Blob([htmlContent], { type: 'text/html;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  URL.revokeObjectURL(link.href);
};

/**
 * Ekspor Dokumen Resmi BRA SAR langsung ke format PDF menggunakan html2pdf.js.
 * Memaksimalkan muatan halaman pertama dan menjamin kelancaran multi-page flow.
 */
export const exportDocumentToPdf = async (
  elementId: string,
  filename = 'dokumen.pdf',
  pageSize: string = 'A4'
): Promise<void> => {
  const element = document.getElementById(elementId);
  if (!element) {
    throw new Error(`Elemen #${elementId} tidak ditemukan.`);
  }

  // Format jsPDF
  const jsPdfFormat = (() => {
    switch (pageSize.toUpperCase()) {
      case 'F4':
      case 'FOLIO':
        return [215, 330] as [number, number];
      case 'LEGAL':
        return 'legal';
      case 'LETTER':
        return 'letter';
      case 'A4':
      default:
        return 'a4';
    }
  })();

  // Simpan style asli sebelum ekspor
  const prevTransform = element.style.transform;
  const prevBoxShadow = element.style.boxShadow;
  const prevBorder = element.style.border;

  // Normalisasi scale / transform untuk rendering html2canvas yang tajam dan akurat
  element.style.transform = 'none';
  element.style.boxShadow = 'none';
  element.style.border = 'none';

  const cleanFilename = filename.toLowerCase().endsWith('.pdf') ? filename : `${filename}.pdf`;

  const opt = {
    margin: [12, 12, 12, 15] as [number, number, number, number],
    filename: cleanFilename,
    image: { type: 'jpeg' as const, quality: 0.98 },
    enableLinks: true,
    html2canvas: {
      scale: 2,
      useCORS: true,
      logging: false,
      backgroundColor: '#ffffff',
      windowWidth: element.scrollWidth,
    },
    jsPDF: {
      unit: 'mm',
      format: jsPdfFormat,
      orientation: 'portrait' as const,
    },
    pagebreak: {
      mode: ['css', 'legacy'],
      avoid: [
        '.bra-kop',
        '.bra-top-header',
        '.bra-upper-section',
        '.bra-no-header',
        '.bra-twb-row',
        '.bra-footer-section',
      ],
    },
  };

  try {
    await (html2pdf() as any).set(opt).from(element).save();
  } finally {
    element.style.transform = prevTransform;
    element.style.boxShadow = prevBoxShadow;
    element.style.border = prevBorder;
  }
};
