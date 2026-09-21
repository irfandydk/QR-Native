import React, { useState, useEffect, useRef } from 'react';
import QRCode from 'qrcode';
import {
  Printer,
  Copy,
  Check,
  QrCode,
  ShieldCheck,
  Download,
  ExternalLink,
  X,
  FileDown,
  Trash2,
  ZoomIn,
  ZoomOut,
} from 'lucide-react';
import { BraSar } from '../types';
import { BasarnasLogo } from './BasarnasLogo';
import { printElement, downloadDocumentHtml } from '../utils/printUtils';
import { showToast, showConfirmDialog } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface OfficialBraDocumentProps {
  bra: BraSar;
  onClose?: () => void;
  onEdit?: () => void;
  onDelete?: () => Promise<void> | void;
  isEmbedded?: boolean;
  logoUrl?: string;
  onLogoUpdated?: (newLogo: string) => void;
}

// Embedded Base Basarnas Vector SVG Data URI for instantaneous, offline-proof QR Center Logo rendering
const BASARNAS_EMBLEM_SVG_URI = `data:image/svg+xml;utf8,${encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
  <circle cx="50" cy="50" r="48" fill="#F97316" stroke="#C2410C" stroke-width="2" />
  <circle cx="50" cy="50" r="43" fill="#FEF3C7" stroke="#D97706" stroke-width="1.5" />
  <path id="tpa" d="M 18,50 A 32,32 0 1,1 82,50" fill="none" stroke="none" />
  <text font-size="7.5" font-weight="900" fill="#9A3412" letter-spacing="0.8">
    <textPath href="#tpa" startOffset="50%" text-anchor="middle">SAR NASIONAL</textPath>
  </text>
  <g transform="translate(50, 52)">
    <path d="M -30,-4 C -22,-14 -8,-8 0,4 C 8,-8 22,-14 30,-4 C 22,2 14,8 0,14 C -14,8 -22,2 -30,-4 Z" fill="#D97706" stroke="#78350F" stroke-width="0.8" />
    <path d="M -24,-2 C -18,-9 -6,-4 0,5 C 6,-4 18,-9 24,-2 C 18,3 10,7 0,10 C -10,7 -18,3 -24,-2 Z" fill="#F59E0B" />
    <circle cx="0" cy="-14" r="4" fill="none" stroke="#1E293B" stroke-width="1.8" />
    <line x1="-8" y1="-8" x2="8" y2="-8" stroke="#1E293B" stroke-width="2.2" stroke-linecap="round" />
    <line x1="0" y1="-14" x2="0" y2="12" stroke="#1E293B" stroke-width="2.5" stroke-linecap="round" />
    <path d="M -13,5 C -9,14 9,14 13,5" fill="none" stroke="#1E293B" stroke-width="2.2" stroke-linecap="round" />
    <polygon points="-13,3 -16,6 -11,7" fill="#1E293B" />
    <polygon points="13,3 16,6 11,7" fill="#1E293B" />
  </g>
  <path id="tpb" d="M 80,56 A 34,34 0 0,1 20,56" fill="none" stroke="none" />
  <text font-size="5.5" font-weight="800" fill="#B45309" letter-spacing="0.4">
    <textPath href="#tpb" startOffset="50%" text-anchor="middle">INDONESIA</textPath>
  </text>
</svg>
`)}`;

export const OfficialBraDocument: React.FC<OfficialBraDocumentProps> = ({
  bra,
  onClose,
  onEdit,
  onDelete,
  isEmbedded = false,
  logoUrl: initialLogoUrl,
  onLogoUpdated,
}) => {
  const { isDarkMode, accentColors } = useTheme();
  const [qrDataUrl, setQrDataUrl] = useState<string>('');
  const [copied, setCopied] = useState<boolean>(false);
  const [pageSize, setPageSize] = useState<'A4' | 'F4' | 'LETTER' | 'LEGAL'>('A4');
  const [pageScale, setPageScale] = useState<number>(100);
  const [currentLogo, setCurrentLogo] = useState<string>(
    initialLogoUrl || bra.logo_url || (typeof window !== 'undefined' ? localStorage.getItem('sar_custom_logo_url') || '' : '')
  );
  const docRef = useRef<HTMLDivElement>(null);

  // Sync initialLogoUrl and bra.logo_url and global satker settings
  useEffect(() => {
    if (initialLogoUrl) {
      setCurrentLogo(initialLogoUrl);
    } else if (bra.logo_url) {
      setCurrentLogo(bra.logo_url);
    }
  }, [initialLogoUrl, bra.logo_url]);

  useEffect(() => {
    const loadSettings = async () => {
      try {
        if (!initialLogoUrl && !bra.logo_url) {
          const localLogo = localStorage.getItem('sar_custom_logo_url');
          if (localLogo) setCurrentLogo(localLogo);
        }

        const res = await fetch('/api/settings');
        const data = await res.json();
        if (data.success && data.data && data.data.logo_url) {
          if (!initialLogoUrl && !bra.logo_url) {
            setCurrentLogo(data.data.logo_url);
          }
        }
      } catch (err) {
        console.error('Failed to load global logo for BRA:', err);
      }
    };

    loadSettings();
    const handleUpdate = (e: any) => {
      if (e?.detail?.logo_url) {
        setCurrentLogo(e.detail.logo_url);
      } else {
        loadSettings();
      }
    };
    window.addEventListener('satker-settings-updated', handleUpdate);
    return () => window.removeEventListener('satker-settings-updated', handleUpdate);
  }, [initialLogoUrl, bra.logo_url]);

  // Compute verify URL
  const verifyUrl = typeof window !== 'undefined'
    ? `${window.location.origin}/?viewBra=${bra.id_bra}`
    : `https://basarnas-kendari.go.id/bra/${bra.id_bra}`;

  // Generate QR Code with Embedded Logo in Center (Using Error Correction 'H' for 100% Reliable Scanning)
  useEffect(() => {
    const canvas = document.createElement('canvas');
    const size = 320;
    canvas.width = size;
    canvas.height = size;

    QRCode.toCanvas(
      canvas,
      verifyUrl,
      {
        width: size,
        margin: 1,
        color: {
          dark: '#0a0f1d',
          light: '#ffffff',
        },
        errorCorrectionLevel: 'H', // High error correction (30% recovery tolerance) ensures instantaneous scan
      },
      (err) => {
        if (err) {
          // Fallback to standard QR
          QRCode.toDataURL(verifyUrl, { width: 140, margin: 1, errorCorrectionLevel: 'H' }, (e2, url) => {
            if (!e2 && url) setQrDataUrl(url);
          });
          return;
        }

        const ctx = canvas.getContext('2d');
        if (!ctx) {
          setQrDataUrl(canvas.toDataURL('image/png'));
          return;
        }

        const logoImg = new Image();
        logoImg.crossOrigin = 'anonymous';

        logoImg.onload = () => {
          const logoSize = Math.floor(size * 0.22); // 22% of QR width/height (well within 30% H tolerance)
          const center = (size - logoSize) / 2;
          const bgRadius = Math.floor(logoSize / 2) + 5;

          // Clean white circular badge background behind logo
          ctx.fillStyle = '#ffffff';
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, bgRadius, 0, Math.PI * 2);
          ctx.fill();

          // Elegant subtle outer ring
          ctx.strokeStyle = '#e2e8f0';
          ctx.lineWidth = 1.5;
          ctx.stroke();

          // Render logo centered inside the badge
          ctx.save();
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2) - 1, 0, Math.PI * 2);
          ctx.clip();
          ctx.drawImage(logoImg, center, center, logoSize, logoSize);
          ctx.restore();

          setQrDataUrl(canvas.toDataURL('image/png'));
        };

        logoImg.onerror = () => {
          if (logoImg.src !== BASARNAS_EMBLEM_SVG_URI) {
            logoImg.src = BASARNAS_EMBLEM_SVG_URI;
          } else {
            setQrDataUrl(canvas.toDataURL('image/png'));
          }
        };

        logoImg.src = currentLogo || BASARNAS_EMBLEM_SVG_URI;
      }
    );
  }, [verifyUrl, bra.id_bra, currentLogo]);

  // Copy plain text telegram format (Poin 1: Format murni telegram tanpa kata SIFAT/SEGERA)
  const handleCopyTelegram = () => {
    let text = `BRA SAR\nBADAN NASIONAL PENCARIAN DAN PERTOLONGAN\n\n`;
    text += `DARI : ${bra.dari}\n`;
    text += `AKSI : ${bra.aksi}\n`;
    text += `INFO : ${bra.info}\n`;
    text += `TWU : ${bra.twu}\n`;
    text += `JUMLAH KATA : ${bra.jumlah_kata || `${bra.sections.reduce((acc, s) => acc + s.isi.split(/\s+/).length, 0)} KATA`}\n\n`;

    // Posisi Nomor dan Judul di atas AAA
    text += `NO.    : ${bra.no_bra}\n`;
    if (bra.menbra) text += `MENBRA : ${bra.menbra}\n`;
    if (bra.perihal) text += `${bra.perihal}\n\n`;

    bra.sections.forEach((sec) => {
      text += `${sec.kode} : ${sec.isi}\n\n`;
    });

    text += `TWB : ${bra.twb}\n`;
    text += `PENGIRIM : ${bra.pengirim}\n`;
    text += `NAMA : ${bra.nama_smc}\n`;
    text += `PANGKAT : ${bra.pangkat_smc}\n`;
    text += `JABATAN : ${bra.jabatan_smc}\n`;
    text += `LINK VERIFIKASI DIGITAL : ${verifyUrl}\n`;

    navigator.clipboard.writeText(text);
    setCopied(true);
    showToast('Naskah Telegram Berhasil Disalin!', 'success');
    setTimeout(() => setCopied(false), 2500);
  };

  const handlePrint = async () => {
    showToast(`Mempersiapkan Cetak Dokumen Resmi (${pageSize})...`, 'info');
    await printElement('official-bra-document', `BRA_SAR_${bra.no_bra.replace(/\//g, '_')}`, pageSize, pageScale);
  };

  const handleDownloadHtml = () => {
    downloadDocumentHtml('official-bra-document', `BRA_SAR_${bra.no_bra.replace(/\//g, '_')}.html`, pageSize, pageScale);
    showToast(`File Dokumen HTML (${pageSize}) Berhasil Diunduh!`, 'success');
  };

  const handleDownloadQr = () => {
    if (!qrDataUrl) return;
    const link = document.createElement('a');
    link.href = qrDataUrl;
    link.download = `QR_BRA_${bra.no_bra.replace(/\//g, '_')}.png`;
    link.click();
    showToast('Barcode QR Berhasil Diunduh!', 'success');
  };

  const handleDeleteClick = async () => {
    const confirmed = await showConfirmDialog(
      `Hapus Dokumen BRA SAR?`,
      `Apakah Anda yakin ingin menghapus naskah Berita Radio SAR "${bra.no_bra}" dari arsip? Tindakan ini tidak dapat dibatalkan.`
    );
    if (confirmed && onDelete) {
      await onDelete();
      showToast(`Dokumen BRA ${bra.no_bra} berhasil dihapus!`, 'success');
      if (onClose) onClose();
    }
  };

  return (
    <div className={`flex flex-col items-center ${isEmbedded ? '' : 'p-3 sm:p-6'}`}>
      {/* Top Action Toolbar (Hidden during print) */}
      <div className={`w-full max-w-4xl mb-4 print:hidden flex flex-wrap items-center justify-between gap-2.5 p-3 rounded-xl shadow-lg border transition-colors ${
        isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-md'
      }`}>
        <div className="flex flex-wrap items-center gap-2">
          <span className={`px-2.5 py-1 text-xs font-bold rounded uppercase tracking-wider border ${
            bra.kategori === 'MAPEL'
              ? 'bg-orange-500/20 text-orange-500 dark:text-orange-400 border-orange-500/30'
              : bra.kategori === 'PERGERAKAN_KAPAL'
              ? 'bg-cyan-500/20 text-cyan-600 dark:text-cyan-300 border-cyan-500/30'
              : 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border-amber-500/30'
          }`}>
            {bra.kategori === 'MAPEL'
              ? '📡 BRA MAPEL'
              : bra.kategori === 'PERGERAKAN_KAPAL'
              ? '🚢 BRA PERGERAKAN KAPAL'
              : '📋 BRA SAR OPERASI SAR'}
          </span>
          <span className={`font-mono text-xs font-semibold ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
            {bra.no_bra}
          </span>
        </div>

        {/* Paper Size & Scale Adjuster */}
        <div className="flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
          <span className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 pl-1">
            Kertas:
          </span>
          <select
            value={pageSize}
            onChange={(e) => setPageSize(e.target.value as any)}
            className="text-xs font-bold bg-white dark:bg-slate-900 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 rounded px-1.5 py-1 outline-none cursor-pointer"
            title="Pilih Ukuran Kertas Dokumen (Default A4)"
          >
            <option value="A4">A4 (210×297 mm) [Default]</option>
            <option value="F4">F4 / Folio (215×330 mm)</option>
            <option value="LETTER">Letter (216×279 mm)</option>
            <option value="LEGAL">Legal (216×356 mm)</option>
          </select>

          <div className="h-4 w-px bg-slate-300 dark:bg-slate-700 mx-0.5" />

          {/* Scale Adjustment */}
          <div className="flex items-center gap-1">
            <button
              onClick={() => setPageScale((prev) => Math.max(80, prev - 5))}
              className="p-1 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded"
              title="Perkecil Tampilan (Zoom Out)"
            >
              <ZoomOut className="w-3.5 h-3.5" />
            </button>
            <span className="text-[11px] font-mono font-bold text-slate-700 dark:text-slate-300 min-w-[34px] text-center">
              {pageScale}%
            </span>
            <button
              onClick={() => setPageScale((prev) => Math.min(125, prev + 5))}
              className="p-1 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 rounded"
              title="Perbesar Tampilan (Zoom In)"
            >
              <ZoomIn className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-1.5">
          {onEdit && (
            <button
              onClick={onEdit}
              className={`px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
                isDarkMode
                  ? 'text-slate-300 bg-slate-800 hover:bg-slate-700 border-slate-700'
                  : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
              }`}
            >
              Edit Form
            </button>
          )}

          <button
            onClick={handleCopyTelegram}
            className={`inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
              isDarkMode
                ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
            }`}
            title="Salin Naskah Telegram Radio"
          >
            {copied ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5 text-slate-400" />}
            <span className="hidden sm:inline">{copied ? 'Tersalin!' : 'Salin Naskah'}</span>
          </button>

          <button
            onClick={handleDownloadQr}
            className={`inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
              isDarkMode
                ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
            }`}
            title="Unduh Barcode QR Verifikasi"
          >
            <Download className="w-3.5 h-3.5 text-slate-400" />
            <span className="hidden sm:inline">Unduh QR</span>
          </button>

          <button
            onClick={handleDownloadHtml}
            className={`inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
              isDarkMode
                ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
            }`}
            title="Unduh Dokumen Mandiri (Bisa Dibuka Offline)"
          >
            <FileDown className="w-3.5 h-3.5 text-slate-400" />
            <span className="hidden sm:inline">HTML</span>
          </button>

          <button
            onClick={handlePrint}
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 rounded-lg shadow-md transition-all hover:scale-[1.02]`}
            title={`Cetak Langsung Lembar Dokumen Resmi (${pageSize}) - Bisa pilih Simpan sebagai PDF di dialog cetak browser`}
          >
            <Printer className="w-3.5 h-3.5" />
            <span>Cetak ({pageSize})</span>
          </button>

          {onDelete && (
            <button
              onClick={handleDeleteClick}
              className="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-xs font-semibold text-red-600 dark:text-red-400 bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 rounded-lg transition-colors"
              title="Hapus Dokumen BRA SAR ini dari arsip"
            >
              <Trash2 className="w-3.5 h-3.5" />
              <span className="hidden md:inline">Hapus</span>
            </button>
          )}

          {onClose && (
            <button
              onClick={onClose}
              className={`p-1.5 rounded-lg transition-colors ${
                isDarkMode
                  ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                  : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100'
              }`}
              title="Tutup"
            >
              <X className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>

      {/* Verification Notice Banner */}
      <div className={`w-full max-w-4xl mb-3 print:hidden border rounded-lg px-3.5 py-2 flex items-center justify-between text-xs transition-colors ${
        isDarkMode
          ? 'bg-emerald-950/40 border-emerald-500/30 text-emerald-300'
          : 'bg-emerald-50 border-emerald-300 text-emerald-800 shadow-sm'
      }`}>
        <div className="flex items-center gap-2">
          <ShieldCheck className="w-4 h-4 text-emerald-500 shrink-0" />
          <span>
            <strong>Dokumen Asli Resmi Terverifikasi:</strong> Tanda tangan pengirim (SMC) disahkan dengan Barcode QR Digital terenkripsi.
          </span>
        </div>
        <a
          href={verifyUrl}
          target="_blank"
          rel="noreferrer"
          className={`hidden sm:inline-flex items-center gap-1 text-[11px] hover:underline font-medium ${
            isDarkMode ? 'text-emerald-400' : 'text-emerald-700'
          }`}
        >
          <span>Buka Link Scan</span>
          <ExternalLink className="w-3 h-3" />
        </a>
      </div>

      {/* ============================================================== */}
      {/* THE OFFICIAL BRA SAR PAPER DOCUMENT CONTAINER (Print-perfect) */}
      {/* ============================================================== */}
      <div
        ref={docRef}
        id="official-bra-document"
        className={`w-full font-sans p-6 sm:p-8 transition-all shadow-2xl border-2 border-slate-300 print:border-none print:shadow-none print:p-0 print:max-w-none bg-white text-black ${
          pageSize === 'F4' || pageSize === 'LEGAL' ? 'max-w-[850px]' : 'max-w-4xl'
        }`}
        style={{
          transform: pageScale !== 100 ? `scale(${pageScale / 100})` : undefined,
          transformOrigin: 'top center',
        }}
      >
        {/* Header Kop: Logo sejajar dengan BRA SAR dan BADAN NASIONAL PENCARIAN DAN PERTOLONGAN */}
        <div
          className="bra-kop relative flex items-center justify-center gap-3 sm:gap-4 pb-2 mb-2 print:pb-1.5 print:mb-1.5 border-b-2 border-black text-black break-inside-avoid break-after-auto"
          style={{ pageBreakInside: 'avoid', breakInside: 'avoid', pageBreakAfter: 'auto', breakAfter: 'auto' }}
        >
          <BasarnasLogo size={48} logoUrl={currentLogo} className="shrink-0 drop-shadow-none" />
          <div className="bra-kop-text flex flex-col items-center justify-center text-center">
            <h1 className="text-xl sm:text-[22px] font-black tracking-wider uppercase leading-none text-black m-0 p-0 text-center">
              BRA SAR
            </h1>
            <h2 className="text-xs sm:text-[13px] md:text-[14px] font-bold tracking-wider uppercase leading-none text-black m-0 mt-1 p-0 text-center">
              BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
            </h2>
          </div>
        </div>

        {/* Main Body Container with Left SENKOM Note */}
        <div
          className="bra-table-wrapper relative pl-6 sm:pl-7 w-full"
          style={{ pageBreakInside: 'auto', breakInside: 'auto', pageBreakAfter: 'auto', breakAfter: 'auto' }}
        >
          {/* Left Vertical Side Label: RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM */}
          <div
            className="absolute left-0 top-0 bottom-0 w-5 flex items-center justify-center select-none pointer-events-none senkom-vertical-label"
            style={{
              writingMode: 'vertical-rl',
              transform: 'rotate(180deg)',
            }}
          >
            <span className="text-[8.5px] sm:text-[9px] font-bold tracking-widest uppercase text-center leading-none text-black whitespace-nowrap">
              RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM
            </span>
          </div>

          {/* Right Main Table Box (Thick Borders as per standard) */}
          <div
            className="bra-table-box border-2 border-black divide-y-2 divide-black text-[11px] sm:text-xs bg-white text-black w-full"
            style={{ pageBreakInside: 'auto', breakInside: 'auto', pageBreakAfter: 'auto', breakAfter: 'auto' }}
          >
            {/* SENKOM Top Header Row */}
            <div
              className="bra-top-header grid grid-cols-5 divide-x-2 divide-black text-center font-bold bg-white text-black break-inside-avoid break-after-auto"
              style={{ pageBreakInside: 'avoid', breakInside: 'avoid', pageBreakAfter: 'auto', breakAfter: 'auto' }}
            >
              <div className="py-1 px-1 print:py-0.5">Panggilan</div>
              <div className="py-1 px-1 print:py-0.5">Jenis</div>
              <div className="py-1 px-1 print:py-0.5">Nr. Stn</div>
              <div className="py-1 px-1 print:py-0.5">
                <div>Derajat</div>
                <div className="font-extrabold text-[11px] sm:text-xs border-t border-black mt-0.5 pt-0.5 text-black">
                  {bra.derajat || 'SGR'}
                </div>
              </div>
              <div className="py-1 px-1 print:py-0.5">Instruksi penerusan</div>
            </div>

            {/* Upper Section: 2 Columns */}
            {/* Left: DARI, AKSI, INFO (Tanpa Sifat) */}
            {/* Right: Tanggal Waktu TWU & Jumlah Kata */}
            <div
              className="bra-upper-section grid grid-cols-12 divide-x-2 divide-black bg-white text-black break-inside-avoid break-after-auto"
              style={{ pageBreakInside: 'avoid', breakInside: 'avoid', pageBreakAfter: 'auto', breakAfter: 'auto' }}
            >
              {/* Left Column (8 cols) */}
              <div className="col-span-7 sm:col-span-8 p-2.5 print:p-2 space-y-1.5 print:space-y-1">
                <div className="flex">
                  <span className="w-16 font-bold uppercase shrink-0 text-black">DARI</span>
                  <span className="mr-1">:</span>
                  <span className="font-bold uppercase tracking-tight text-black">{bra.dari}</span>
                </div>

                <div className="flex">
                  <span className="w-16 font-bold uppercase shrink-0 text-black">AKSI</span>
                  <span className="mr-1">:</span>
                  <span className="uppercase text-black">{bra.aksi}</span>
                </div>

                <div className="flex">
                  <span className="w-16 font-bold uppercase shrink-0 text-black">INFO</span>
                  <span className="mr-1">:</span>
                  <span className="uppercase text-black">{bra.info}</span>
                </div>
              </div>

              {/* Right Column (4 cols): Kolom Tanggal Waktu Sesuai Contoh Lampiran */}
              <div className="col-span-5 sm:col-span-4 divide-y-2 divide-black flex flex-col justify-between bg-white text-black">
                {/* Baris 1: TANGGAL WAKTU (Tanpa : dan Posisi Center) */}
                <div className="p-1.5 sm:p-2 print:p-1 flex items-center justify-center text-center min-h-[30px] print:min-h-[24px] bg-white">
                  <span className="font-bold uppercase text-[9.5px] sm:text-[10.5px] tracking-wider text-center text-black">
                    TANGGAL WAKTU
                  </span>
                </div>

                {/* Baris 2: TWU (misal: TWU 0918 2105 H sesuai contoh lampiran) */}
                <div className="p-1.5 sm:p-2 print:p-1 flex items-center justify-center text-center flex-1 min-h-[36px] print:min-h-[28px] bg-white">
                  <span className="font-black text-xs sm:text-sm tracking-wider uppercase text-black">
                    {bra.twu.startsWith('TWU') ? bra.twu : `TWU ${bra.twu}`}
                  </span>
                </div>

                {/* Baris 3: JUMLAH KATA */}
                <div className="p-1.5 sm:p-2 print:p-1 flex items-center justify-between min-h-[30px] print:min-h-[24px] bg-white">
                  <span className="font-bold uppercase text-[9.5px] sm:text-[10.5px] text-black">
                    JUMLAH KATA
                  </span>
                  <span className="font-bold text-xs uppercase text-black">
                    {bra.jumlah_kata || `${bra.sections.reduce((acc, s) => acc + s.isi.split(/\s+/).length, 0)} KATA`}
                  </span>
                </div>
              </div>
            </div>

            {/* Telegram Body Sections (AAA, BBB, CCC, etc.) */}
            {/* Bagian Nomor dan Judul di atas AAA tanpa kotak/kolom pembatas */}
            <div
              className="bra-body-container border-t-2 border-black p-3 sm:p-4 print:p-2.5 sm:print:p-3 bg-white text-black"
              style={{ pageBreakInside: 'auto', breakInside: 'auto', pageBreakAfter: 'auto', breakAfter: 'auto' }}
            >
              <div
                className="bra-body-content"
                style={{ pageBreakInside: 'auto', breakInside: 'auto', pageBreakAfter: 'auto', breakAfter: 'auto' }}
              >
                {/* Bagian NO dan Judul langsung di atas AAA tanpa tulisan Segera di sampingnya */}
                <div
                  className="bra-no-header mb-3.5 print:mb-2 break-inside-avoid break-after-auto"
                  style={{ pageBreakInside: 'avoid', breakInside: 'avoid', pageBreakAfter: 'auto', breakAfter: 'auto' }}
                >
                  <div className="space-y-1">
                    <div className="flex items-center text-xs sm:text-sm font-bold uppercase">
                      <span className="w-20 sm:w-24 font-black shrink-0 text-black">NO.</span>
                      <span className="w-3 text-center shrink-0">:</span>
                      <span className="font-extrabold tracking-wider ml-1 text-black">{bra.no_bra}</span>
                    </div>

                    {bra.menbra && (
                      <div className="flex items-center text-xs sm:text-sm font-bold uppercase">
                        <span className="w-20 sm:w-24 font-black shrink-0 text-black">MENBRA</span>
                        <span className="w-3 text-center shrink-0">:</span>
                        <span className="font-extrabold tracking-wider ml-1 text-black">{bra.menbra}</span>
                      </div>
                    )}
                  </div>

                  {bra.perihal && (
                    <div className="font-black uppercase text-xs sm:text-sm tracking-tight leading-snug mt-2.5 print:mt-1.5 mb-1 text-black">
                      {bra.perihal}
                    </div>
                  )}
                </div>

                {/* Isian Bagian Telegram: AAA, BBB, CCC, dll. */}
                <div
                  className="space-y-3.5 print:space-y-2 bra-sections-list"
                  style={{ pageBreakInside: 'auto', breakInside: 'auto', pageBreakAfter: 'auto', breakAfter: 'auto' }}
                >
                  {bra.sections.map((section, idx) => (
                    <div
                      key={idx}
                      className="bra-section-row flex items-start text-justify leading-relaxed print:leading-normal text-xs sm:text-[12.5px] break-after-auto"
                      style={{ pageBreakInside: 'auto', breakInside: 'auto', pageBreakAfter: 'auto', breakAfter: 'auto' }}
                    >
                      <span className="font-black text-xs sm:text-sm tracking-wider uppercase w-12 sm:w-14 shrink-0 text-black">
                        {section.kode} :
                      </span>
                      <div
                        className="flex-1 whitespace-pre-line uppercase font-normal text-xs sm:text-[12.5px] leading-relaxed print:leading-normal text-black"
                        style={{ pageBreakInside: 'auto', breakInside: 'auto', pageBreakAfter: 'auto', breakAfter: 'auto' }}
                      >
                        {section.isi}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* TWB di sudut kanan bawah area telegram tepat di atas garis pembatas */}
              <div
                className="bra-twb-row text-right font-black text-xs sm:text-sm tracking-wider uppercase pt-3 print:pt-2 pr-1 text-black break-inside-avoid break-after-auto"
                style={{ pageBreakInside: 'avoid', breakInside: 'avoid', pageBreakAfter: 'auto', breakAfter: 'auto' }}
              >
                TWB {bra.twb}
              </div>
            </div>

            {/* Bottom Footer Section: SMC / Pengirim with Barcode & SENKOM Signatures */}
            <div
              className="bra-footer-section grid grid-cols-12 divide-x-2 divide-black bg-white text-black break-inside-avoid break-after-auto"
              style={{ pageBreakInside: 'avoid', breakInside: 'avoid', pageBreakAfter: 'auto', breakAfter: 'auto' }}
            >
              {/* Left Column: Pengirim (SMC) with QR Barcode */}
              <div className="col-span-7 sm:col-span-7 p-2.5 print:p-2 flex flex-col justify-between">
                <div>
                  <div className="space-y-1">
                    <div className="flex">
                      <span className="w-20 font-bold uppercase shrink-0 text-black">PENGIRIM</span>
                      <span className="mr-1">:</span>
                      <span className="font-bold uppercase text-[11px] sm:text-xs text-black">
                        {bra.pengirim}
                      </span>
                    </div>

                    <div className="flex">
                      <span className="w-20 font-bold uppercase shrink-0 text-black">Nama</span>
                      <span className="mr-1">:</span>
                      <span className="font-bold uppercase text-[11px] sm:text-xs text-black">
                        {bra.nama_smc}
                      </span>
                    </div>

                    <div className="flex">
                      <span className="w-20 font-bold uppercase shrink-0 text-black">Pangkat</span>
                      <span className="mr-1">:</span>
                      <span className="uppercase text-[11px] sm:text-xs text-black">
                        {bra.pangkat_smc}
                      </span>
                    </div>

                    <div className="flex">
                      <span className="w-20 font-bold uppercase shrink-0 text-black">Jabatan</span>
                      <span className="mr-1">:</span>
                      <span className="font-bold uppercase text-[11px] sm:text-xs text-black">
                        {bra.jabatan_smc}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Tanda Tangan -> BARCODE QR CODE (Requirement 2) with Official Stamp */}
                <div className="mt-2 pt-2 border-t border-black">
                  <div className="flex items-start justify-between">
                    <div>
                      <span className="font-bold uppercase text-[10px] sm:text-[11px] block text-black">
                        Tanda Tangan :
                      </span>
                      <span className="text-[9px] font-mono block mt-0.5 text-gray-600">
                        (Barcode Digital Terverifikasi)
                      </span>
                    </div>

                    {/* Barcode QR Digital (Poin 3: Tanpa Stempel) */}
                    <div className="flex flex-col items-center">
                      {qrDataUrl ? (
                        <div className="p-1 bg-white border border-black rounded shadow-sm">
                          <img
                            src={qrDataUrl}
                            alt={`Barcode ${bra.no_bra}`}
                            className="w-24 h-24 sm:w-28 sm:h-28 print:w-20 print:h-20 object-contain"
                          />
                        </div>
                      ) : (
                        <div className="w-24 h-24 border border-dashed border-gray-400 flex items-center justify-center text-[10px] text-gray-500">
                          Memuat Barcode...
                        </div>
                      )}

                      <div className="text-[8.5px] font-mono text-center mt-1 text-gray-700">
                        SCAN UNTUK VERIFIKASI
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Right Column: SENKOM Processing Columns */}
              <div className="col-span-5 sm:col-span-5 flex flex-col justify-between">
                <div className="grid grid-cols-4 divide-x-2 divide-black border-b-2 border-black text-center font-bold py-1 text-[10px] sm:text-[11px] bg-white text-black">
                  <div>T. W. T</div>
                  <div>T. W. K</div>
                  <div>SISTEM</div>
                  <div>PARAF</div>
                </div>

                {/* Empty operator logging rows for SENKOM */}
                <div className="grid grid-cols-4 divide-x-2 divide-black flex-1 min-h-[110px] print:min-h-[85px] bg-white text-black">
                  <div className="p-1 text-center font-mono text-[10px] text-black">
                    {bra.twt || ''}
                  </div>
                  <div className="p-1 text-center font-mono text-[10px] text-black">
                    {bra.twk || ''}
                  </div>
                  <div className="p-1 text-center font-mono text-[10px] text-black">
                    {bra.sistem || ''}
                  </div>
                  <div className="p-1 text-center font-mono text-[10px] text-black">
                    {bra.paraf || ''}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Footer tiny text */}
        <div className="mt-2 text-right text-[8px] font-mono text-gray-600">
          SISTEM QR SAR • KANTOR PENCARIAN DAN PERTOLONGAN KENDARI • DOKUMEN ELEKTRONIK RESMI
        </div>

        {/* Embedded print style rules to guarantee multi-page flow and prevent KOP orphan */}
        <style dangerouslySetInnerHTML={{ __html: `
          @media print {
            @page {
              size: auto;
              margin: 12mm 12mm 12mm 15mm;
            }
            .bra-kop {
              position: relative !important;
              text-align: center !important;
              display: flex !important;
              flex-direction: row !important;
              align-items: center !important;
              justify-content: center !important;
              gap: 12px !important;
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
            .senkom-vertical-label {
              top: 25px !important;
              bottom: auto !important;
              height: 280px !important;
              position: absolute !important;
              page-break-inside: avoid !important;
              break-inside: avoid !important;
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
          }
        ` }} />
      </div>
    </div>
  );
};
