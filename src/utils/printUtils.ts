/**
 * Utility Cetak dan Unduh Dokumen Resmi Basarnas
 * Menjamin 100% konsistensi antara Tampilan Preview dan Hasil Cetak/Download PDF.
 */

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
    margin: 8mm 10mm 8mm 10mm;
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
  }
  .print-wrapper {
    width: 100%;
    max-width: ${pageSize === 'F4' || pageSize === 'LEGAL' ? '850px' : '820px'};
    margin: 0 auto;
    background: #ffffff !important;
    color: #000000 !important;
    padding: 10px;
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
  }
  .print\\:hidden, .no-print, button {
    display: none !important;
  }
  /* Table & Border overrides to guarantee high contrast black lines */
  table, tr, td, th {
    border-color: #000000 !important;
  }
  .border, .border-2, .border-t, .border-b, .border-l, .border-r,
  .divide-y, .divide-y-2, .divide-x, .divide-x-2 {
    border-color: #000000 !important;
  }
  .divide-y > :not([hidden]) ~ :not([hidden]),
  .divide-y-2 > :not([hidden]) ~ :not([hidden]) {
    border-top-color: #000000 !important;
  }
  .divide-x > :not([hidden]) ~ :not([hidden]),
  .divide-x-2 > :not([hidden]) ~ :not([hidden]) {
    border-left-color: #000000 !important;
  }
  /* Ensure all text in document is solid black */
  p, span, div, h1, h2, h3, h4, strong {
    color: #000000 !important;
  }
  .text-amber-500, .text-amber-600, .text-orange-500, .text-orange-600,
  .text-slate-400, .text-slate-500, .text-slate-600, .text-slate-700, .text-slate-800, .text-slate-900,
  .text-gray-400, .text-gray-500, .text-gray-600, .text-gray-700, .text-gray-800, .text-gray-900 {
    color: #000000 !important;
  }
  .bg-slate-900, .bg-slate-800, .bg-slate-850, .bg-slate-950, .bg-slate-50, .bg-gray-50, .bg-white {
    background-color: #ffffff !important;
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
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.visibility = 'hidden';

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

    // Beri jeda sejenak untuk rendering gambar / QR code
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
    }, 450);
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

