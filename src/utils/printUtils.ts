/**
 * Utility Cetak dan Unduh Dokumen Resmi Basarnas
 * Menjamin 100% konsistensi antara Tampilan Preview dan Hasil Cetak/Download PDF.
 */

import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas-pro';
import { BASARNAS_EMBLEM_SVG_URI } from '../components/BasarnasLogo';

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
    position: relative !important;
    text-align: center !important;
    display: flex !important;
    flex-direction: row !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 14px !important;
    page-break-inside: avoid !important;
    break-inside: avoid !important;
    page-break-after: auto !important;
    break-after: auto !important;
    margin-bottom: 6px !important;
    padding-bottom: 6px !important;
    border-bottom: 2px solid #000000 !important;
    min-height: 48px !important;
  }
  .bra-kop-text {
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    justify-content: center !important;
    text-align: center !important;
  }
  .bra-kop-text h1 {
    font-size: 20px !important;
    font-weight: 900 !important;
    line-height: 1.0 !important;
    margin: 0 !important;
    padding: 0 !important;
    text-align: center !important;
    letter-spacing: 0.8px !important;
  }
  .bra-kop-text h2 {
    font-size: 13px !important;
    font-weight: 700 !important;
    line-height: 1.0 !important;
    margin: 4px 0 0 0 !important;
    padding: 0 !important;
    text-align: center !important;
    letter-spacing: 0.5px !important;
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

    // Ambil semua stylesheet dan tag style aplikasi yang aktif
    let stylesHtml = '';
    const styleTags = Array.from(document.querySelectorAll('link[rel="stylesheet"], style'));
    for (const tag of styleTags) {
      stylesHtml += tag.outerHTML + '\n';
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
        <body style="background: #ffffff !important; margin: 0; padding: 0;">
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
            console.error('Iframe print error, falling back to window.print:', err);
            window.print();
            resolve(true);
          } finally {
            setTimeout(() => {
              iframe.remove();
            }, 3500);
          }
        }, 180);
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
 * Ekspor Dokumen Resmi BRA SAR langsung ke format PDF.
 * Menggunakan html2canvas dan jsPDF dengan segmentasi multi-halaman berpresisi tinggi,
 * mempertahankan margin standar Tata Naskah Dinas (Kiri 15mm, Kanan 12mm, Atas 12mm, Bawah 12mm)
 * serta memotong dokumen pada batas baris tabel alami tanpa memotong teks di tengah.
 */
/**
 * Menyalin computed styles dan menginjeksi border/layout inline secara eksplisit
 * agar rendering PDF di lingkungan produksi (deployed) tidak kehilangan garis batas
 * akibat pembatasan akses stylesheet eksternal (CORS / sheet.cssRules).
 */
export const applyDirectDocumentStyles = (sourceEl: HTMLElement, targetEl: HTMLElement) => {
  try {
    const sourceNodes = [sourceEl, ...Array.from(sourceEl.querySelectorAll<HTMLElement>('*'))];
    const targetNodes = [targetEl, ...Array.from(targetEl.querySelectorAll<HTMLElement>('*'))];
    const count = Math.min(sourceNodes.length, targetNodes.length);

    for (let i = 0; i < count; i++) {
      const src = sourceNodes[i];
      const tgt = targetNodes[i];
      if (!src || !tgt || !src.style) continue;

      const comp = window.getComputedStyle(src);

      // Salin border
      if (comp.borderTopWidth && comp.borderTopWidth !== '0px') {
        tgt.style.borderTopWidth = comp.borderTopWidth;
        tgt.style.borderTopStyle = comp.borderTopStyle || 'solid';
        tgt.style.borderTopColor = '#000000';
      }
      if (comp.borderBottomWidth && comp.borderBottomWidth !== '0px') {
        tgt.style.borderBottomWidth = comp.borderBottomWidth;
        tgt.style.borderBottomStyle = comp.borderBottomStyle || 'solid';
        tgt.style.borderBottomColor = '#000000';
      }
      if (comp.borderLeftWidth && comp.borderLeftWidth !== '0px') {
        tgt.style.borderLeftWidth = comp.borderLeftWidth;
        tgt.style.borderLeftStyle = comp.borderLeftStyle || 'solid';
        tgt.style.borderLeftColor = '#000000';
      }
      if (comp.borderRightWidth && comp.borderRightWidth !== '0px') {
        tgt.style.borderRightWidth = comp.borderRightWidth;
        tgt.style.borderRightStyle = comp.borderRightStyle || 'solid';
        tgt.style.borderRightColor = '#000000';
      }

      // Salin display & grid / flex layout
      if (comp.display === 'grid') {
        tgt.style.display = 'grid';
        if (comp.gridTemplateColumns) {
          tgt.style.gridTemplateColumns = comp.gridTemplateColumns;
        }
      } else if (comp.display === 'flex') {
        tgt.style.display = 'flex';
        tgt.style.flexDirection = comp.flexDirection;
        tgt.style.alignItems = comp.alignItems;
        tgt.style.justifyContent = comp.justifyContent;
      }

      // Salin tipografi & perataan teks
      if (comp.textAlign) tgt.style.textAlign = comp.textAlign;
      if (comp.textTransform) tgt.style.textTransform = comp.textTransform;
      if (comp.fontWeight) tgt.style.fontWeight = comp.fontWeight;
      if (comp.fontSize) tgt.style.fontSize = comp.fontSize;

      // Paksa teks hitam dan background putih
      tgt.style.color = '#000000';
      if (
        comp.backgroundColor &&
        comp.backgroundColor !== 'rgba(0, 0, 0, 0)' &&
        comp.backgroundColor !== 'transparent'
      ) {
        tgt.style.backgroundColor = '#ffffff';
      }
    }
  } catch (err) {
    console.warn('Gagal menyalin computed styles:', err);
  }

  // Terapkan aturan struktural eksplisit sebagai jaminan 100%
  targetEl.querySelectorAll<HTMLElement>('.bra-table-box').forEach((el) => {
    el.style.border = '2px solid #000000';
    el.style.backgroundColor = '#ffffff';
    el.style.color = '#000000';
    el.style.width = '100%';
  });

  targetEl.querySelectorAll<HTMLElement>('.bra-kop').forEach((el) => {
    el.style.borderBottom = '2px solid #000000';
    el.style.display = 'flex';
    el.style.flexDirection = 'row';
    el.style.alignItems = 'center';
    el.style.justifyContent = 'center';
    el.style.paddingBottom = '6px';
    el.style.marginBottom = '6px';
  });

  targetEl.querySelectorAll<HTMLElement>('.border-2').forEach((el) => {
    el.style.border = '2px solid #000000';
  });
  targetEl.querySelectorAll<HTMLElement>('.border-b-2').forEach((el) => {
    el.style.borderBottom = '2px solid #000000';
  });
  targetEl.querySelectorAll<HTMLElement>('.border-t-2').forEach((el) => {
    el.style.borderTop = '2px solid #000000';
  });
  targetEl.querySelectorAll<HTMLElement>('.border-b').forEach((el) => {
    el.style.borderBottom = '1px solid #000000';
  });
  targetEl.querySelectorAll<HTMLElement>('.border-t').forEach((el) => {
    el.style.borderTop = '1px solid #000000';
  });

  // Divide-y-2 dan divide-x-2
  targetEl.querySelectorAll<HTMLElement>('.divide-y-2').forEach((parent) => {
    const children = Array.from(parent.children) as HTMLElement[];
    for (let i = 1; i < children.length; i++) {
      children[i].style.borderTop = '2px solid #000000';
    }
  });

  targetEl.querySelectorAll<HTMLElement>('.divide-x-2').forEach((parent) => {
    const children = Array.from(parent.children) as HTMLElement[];
    for (let i = 1; i < children.length; i++) {
      children[i].style.borderLeft = '2px solid #000000';
    }
  });

  // Grid definitions
  targetEl.querySelectorAll<HTMLElement>('.grid-cols-5').forEach((el) => {
    el.style.display = 'grid';
    el.style.gridTemplateColumns = 'repeat(5, minmax(0, 1fr))';
  });
  targetEl.querySelectorAll<HTMLElement>('.grid-cols-4').forEach((el) => {
    el.style.display = 'grid';
    el.style.gridTemplateColumns = 'repeat(4, minmax(0, 1fr))';
  });
  targetEl.querySelectorAll<HTMLElement>('.grid-cols-12').forEach((el) => {
    el.style.display = 'grid';
    el.style.gridTemplateColumns = 'repeat(12, minmax(0, 1fr))';
  });

  // Column spans
  targetEl.querySelectorAll<HTMLElement>('.col-span-7, .sm\\:col-span-8').forEach((el) => {
    el.style.gridColumn = 'span 8 / span 8';
  });
  targetEl.querySelectorAll<HTMLElement>('.col-span-5, .sm\\:col-span-4').forEach((el) => {
    el.style.gridColumn = 'span 4 / span 4';
  });
};

export const exportDocumentToPdf = async (
  elementId: string,
  filename = 'dokumen.pdf',
  pageSize: string = 'A4'
): Promise<void> => {
  const element = document.getElementById(elementId);
  if (!element) {
    throw new Error(`Elemen #${elementId} tidak ditemukan.`);
  }

  // Format dimensi fisik kertas dalam satuan milimeter (mm)
  const paperDimensions: Record<
    string,
    { width: number; height: number; format: string | [number, number] }
  > = {
    A4: { width: 210, height: 297, format: 'a4' },
    F4: { width: 215, height: 330, format: [215, 330] },
    FOLIO: { width: 215, height: 330, format: [215, 330] },
    LETTER: { width: 215.9, height: 279.4, format: 'letter' },
    LEGAL: { width: 215.9, height: 355.6, format: 'legal' },
  };

  const key = pageSize.toUpperCase();
  const targetPaper = paperDimensions[key] || paperDimensions.A4;

  // Standar Tata Naskah Dinas Basarnas:
  // Atas: 12mm, Bawah: 12mm, Kiri: 15mm, Kanan: 12mm
  const marginLeft = 15;
  const marginRight = 12;
  const marginTop = 12;
  const marginBottom = 12;
  const usableWidth = targetPaper.width - marginLeft - marginRight;
  const usableHeight = targetPaper.height - marginTop - marginBottom;

  const cleanFilename = filename.toLowerCase().endsWith('.pdf') ? filename : `${filename}.pdf`;

  // Buat container rendering tersembunyi di dalam viewport nyata (zIndex: -99999)
  // agar getBoundingClientRect dan layout rendering menghasilkan dimensi yang akurat
  const offscreenContainer = document.createElement('div');
  offscreenContainer.style.position = 'fixed';
  offscreenContainer.style.top = '0';
  offscreenContainer.style.left = '0';
  offscreenContainer.style.width = '840px';
  offscreenContainer.style.backgroundColor = '#ffffff';
  offscreenContainer.style.zIndex = '-99999';
  offscreenContainer.style.pointerEvents = 'none';
  offscreenContainer.style.opacity = '1';

  // Klon elemen dokumen
  const clonedEl = element.cloneNode(true) as HTMLElement;
  clonedEl.style.width = '800px';
  clonedEl.style.maxWidth = '800px';
  clonedEl.style.minWidth = '800px';
  clonedEl.style.transform = 'none';
  clonedEl.style.boxShadow = 'none';
  clonedEl.style.border = 'none';
  clonedEl.style.margin = '0';
  clonedEl.style.padding = '20px 24px';
  clonedEl.style.backgroundColor = '#ffffff';
  clonedEl.style.color = '#000000';

  // Suntikkan stylesheet mandiri langsung ke dalam dokumen kloning
  const embeddedStyle = document.createElement('style');
  embeddedStyle.textContent = `
    ${getBasePrintStyles(pageSize, 100)}
    *, *::before, *::after {
      border-color: #000000 !important;
      color: #000000 !important;
      box-sizing: border-box !important;
    }
  `;
  clonedEl.prepend(embeddedStyle);

  // Terapkan salinan computed styles dan inline border ke elemen klon
  applyDirectDocumentStyles(element, clonedEl);

  // Sanitasi SEMUA gambar di clonedEl agar tidak ada tainted canvas atau CORS issue
  const images = clonedEl.querySelectorAll('img');
  images.forEach((img) => {
    if (!img.src || (!img.src.startsWith('data:') && !img.src.startsWith('blob:'))) {
      img.src = BASARNAS_EMBLEM_SVG_URI;
    }
  });

  offscreenContainer.appendChild(clonedEl);
  document.body.appendChild(offscreenContainer);

  try {
    // Beri jeda kecil agar browser sempat melakukan reflow dan layout lengkap
    await new Promise((r) => setTimeout(r, 60));

    // Rekam posisi vertikal setiap baris agar pemotongan halaman tidak memotong teks/baris
    const rowNodes = clonedEl.querySelectorAll(
      '.bra-kop, .bra-top-header, .bra-upper-section, .bra-no-header, .bra-section-row, .bra-twb-row, .bra-footer-section'
    );
    const containerRect = clonedEl.getBoundingClientRect();
    const clonedHeight = containerRect.height;
    const bps: number[] = [];
    rowNodes.forEach((node) => {
      const r = node.getBoundingClientRect();
      const offsetBottom = r.bottom - containerRect.top;
      if (offsetBottom > 10) {
        bps.push(offsetBottom);
      }
    });
    const recordedBreakpoints = bps.sort((a, b) => a - b);

    // Render canvas dengan opsi paling stabil
    let canvas: HTMLCanvasElement;
    try {
      canvas = await html2canvas(clonedEl, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        windowWidth: 1024,
        onclone: (clonedDoc) => {
          const headStyle = clonedDoc.createElement('style');
          headStyle.textContent = getBasePrintStyles(pageSize, 100);
          clonedDoc.head.appendChild(headStyle);
        },
      });
    } catch (primaryCanvasError) {
      console.warn('html2canvas utama gagal, mencoba fallback rendering:', primaryCanvasError);
      canvas = await html2canvas(element, {
        scale: 1.5,
        useCORS: true,
        allowTaint: true,
        backgroundColor: '#ffffff',
        logging: false,
        onclone: (clonedDoc) => {
          const headStyle = clonedDoc.createElement('style');
          headStyle.textContent = getBasePrintStyles(pageSize, 100);
          clonedDoc.head.appendChild(headStyle);
        },
      });
    }

    const pdf = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: targetPaper.format,
    });

    const totalContentHeightInPdf = (canvas.height * usableWidth) / canvas.width;

    // Jika konten muat dalam 1 halaman (atau sedikit melebihi batas hingga 5%),
    // muat pada 1 halaman utuh sesuai konvensi naskah dinas resmi
    if (totalContentHeightInPdf <= usableHeight * 1.05) {
      const renderHeight = Math.min(usableHeight, totalContentHeightInPdf);
      let imgData: string;
      try {
        imgData = canvas.toDataURL('image/jpeg', 0.98);
      } catch {
        imgData = canvas.toDataURL('image/png');
      }
      pdf.addImage(imgData, 'JPEG', marginLeft, marginTop, usableWidth, renderHeight);
    } else {
      // Hitung konversi tinggi piksel kanvas ke tinggi halaman PDF
      const pageHeightInPixels = Math.floor((usableHeight * canvas.width) / usableWidth);
      const scaleRatio = clonedHeight > 0 ? canvas.height / clonedHeight : 1;
      const canvasBreakpoints = recordedBreakpoints.map((bp) => bp * scaleRatio);

      let currentY = 0;
      let pageIndex = 0;

      while (currentY < canvas.height) {
        const remainingHeight = canvas.height - currentY;
        const targetEndY = currentY + pageHeightInPixels;

        let sliceHeight: number;
        if (remainingHeight <= pageHeightInPixels) {
          sliceHeight = remainingHeight;
        } else {
          // Cari breakpoint terdekat sebelum targetEndY (minimal 65% tinggi halaman agar tidak terlalu kosong)
          const minAcceptableY = currentY + pageHeightInPixels * 0.65;
          const validBp = canvasBreakpoints
            .filter((bp) => bp <= targetEndY && bp >= minAcceptableY)
            .pop();

          if (validBp && validBp > currentY) {
            sliceHeight = Math.floor(validBp - currentY);
          } else {
            sliceHeight = pageHeightInPixels;
          }
        }

        if (sliceHeight <= 0 || isNaN(sliceHeight)) {
          sliceHeight = remainingHeight;
        }

        const sliceCanvas = document.createElement('canvas');
        sliceCanvas.width = canvas.width;
        sliceCanvas.height = sliceHeight;

        const ctx = sliceCanvas.getContext('2d');
        if (ctx) {
          ctx.fillStyle = '#ffffff';
          ctx.fillRect(0, 0, sliceCanvas.width, sliceCanvas.height);
          ctx.drawImage(
            canvas,
            0,
            currentY,
            canvas.width,
            sliceHeight,
            0,
            0,
            sliceCanvas.width,
            sliceHeight
          );
        }

        let sliceImgData: string;
        try {
          sliceImgData = sliceCanvas.toDataURL('image/jpeg', 0.98);
        } catch {
          sliceImgData = sliceCanvas.toDataURL('image/png');
        }
        const renderHeightInPdf = (sliceHeight * usableWidth) / canvas.width;

        if (pageIndex > 0) {
          pdf.addPage(targetPaper.format, 'portrait');
        }

        pdf.addImage(sliceImgData, 'JPEG', marginLeft, marginTop, usableWidth, renderHeightInPdf);

        currentY += sliceHeight;
        pageIndex++;
      }
    }

    // Trigger unduh dengan dukungan native jsPDF dan fallback Blob URL untuk kehandalan penuh
    try {
      pdf.save(cleanFilename);
    } catch (saveErr) {
      console.warn('Fallback ke blob download:', saveErr);
      const pdfBlob = pdf.output('blob');
      const blobUrl = URL.createObjectURL(pdfBlob);
      const downloadLink = document.createElement('a');
      downloadLink.href = blobUrl;
      downloadLink.download = cleanFilename;
      document.body.appendChild(downloadLink);
      downloadLink.click();
      document.body.removeChild(downloadLink);
      setTimeout(() => URL.revokeObjectURL(blobUrl), 15000);
    }
  } finally {
    // Bersihkan elemen kloning offscreen dari DOM
    if (offscreenContainer.parentNode) {
      offscreenContainer.parentNode.removeChild(offscreenContainer);
    }
  }
};
