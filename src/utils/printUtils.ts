/**
 * Utility Cetak Dokumen Resmi Basarnas
 * Mendukung pencetakan isolasi via hidden iframe yang bekerja 100% pada semua browser
 * dan tidak terblokir oleh pembatasan iframe / sandbox.
 */

export const printElement = (elementId: string, docTitle = 'Dokumen Resmi Basarnas'): Promise<boolean> => {
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
      // Fallback ke window.print biasa
      window.print();
      resolve(true);
      return;
    }

    // Kumpulkan CSS dari halaman induk agar layout Tailwind dan border tercetak presisi
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
        // Cross-origin stylesheet, buat link rel
        if (sheet.href) {
          stylesHtml += `<link rel="stylesheet" href="${sheet.href}">`;
        }
      }
    }

    // Tambahkan CSS cetak resmi presisi tinggi (A4, margin 12mm, warna hitam pekat)
    const customPrintStyles = `
      <style>
        @page {
          size: A4 portrait;
          margin: 10mm 12mm 10mm 12mm;
        }
        *, *::before, *::after {
          box-sizing: border-box;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        body {
          margin: 0;
          padding: 0;
          background: #ffffff !important;
          color: #000000 !important;
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
          font-size: 11pt;
          line-height: 1.35;
        }
        #official-bra-document, .print-container {
          width: 100% !important;
          max-width: 100% !important;
          margin: 0 auto !important;
          padding: 0 !important;
          border: none !important;
          box-shadow: none !important;
          background: #ffffff !important;
          color: #000000 !important;
        }
        .print\\:hidden, button, .no-print {
          display: none !important;
        }
        table, tr, td, th {
          border-color: #000000 !important;
        }
        img {
          max-width: 100%;
          page-break-inside: avoid;
        }
      </style>
    `;

    iframeDoc.open();
    iframeDoc.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="utf-8">
          <title>${docTitle}</title>
          ${stylesHtml}
          ${customPrintStyles}
        </head>
        <body>
          <div class="print-container">
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
 * Unduh Dokumen sebagai file HTML mandiri (bisa dibuka dan dicetak di Chrome/Edge)
 */
export const downloadDocumentHtml = (elementId: string, filename = 'Dokumen_SAR.html') => {
  const element = document.getElementById(elementId);
  if (!element) return;

  const htmlContent = `
    <!DOCTYPE html>
    <html lang="id">
      <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>${filename.replace('.html', '')}</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
          @page { size: A4; margin: 12mm; }
          body { background: #fff; color: #000; font-family: sans-serif; padding: 20px; }
          .print-toolbar { text-align: center; margin-bottom: 20px; }
          @media print { .print-toolbar { display: none; } body { padding: 0; } }
        </style>
      </head>
      <body>
        <div class="print-toolbar">
          <button onclick="window.print()" style="padding: 10px 20px; background: #ea580c; color: white; border: none; border-radius: 8px; font-weight: bold; cursor: pointer;">
            🖨️ Cetak Dokumen Ini (PDF / Print)
          </button>
        </div>
        <div style="max-width: 800px; margin: 0 auto; background: white;">
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
