import React, { useState, useEffect, useRef } from 'react';
import QRCode from 'qrcode';
import { Printer, Copy, Check, QrCode, ShieldCheck, Download, ExternalLink, X, Image as ImageIcon, FileDown } from 'lucide-react';
import { BraSar } from '../types';
import { BasarnasLogo } from './BasarnasLogo';
import { LogoSettingModal } from './LogoSettingModal';
import { printElement, downloadDocumentHtml } from '../utils/printUtils';
import { showToast } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface OfficialBraDocumentProps {
  bra: BraSar;
  onClose?: () => void;
  onEdit?: () => void;
  isEmbedded?: boolean;
  logoUrl?: string;
  onLogoUpdated?: (newLogo: string) => void;
}

export const OfficialBraDocument: React.FC<OfficialBraDocumentProps> = ({
  bra,
  onClose,
  onEdit,
  isEmbedded = false,
  logoUrl: initialLogoUrl,
  onLogoUpdated,
}) => {
  const { isDarkMode, accentColors } = useTheme();
  const [qrDataUrl, setQrDataUrl] = useState<string>('');
  const [copied, setCopied] = useState<boolean>(false);
  const [showLogoModal, setShowLogoModal] = useState<boolean>(false);
  const [currentLogo, setCurrentLogo] = useState<string>(
    initialLogoUrl || (typeof window !== 'undefined' ? localStorage.getItem('sar_custom_logo_url') || '' : '')
  );
  const docRef = useRef<HTMLDivElement>(null);

  // Sync initialLogoUrl
  useEffect(() => {
    if (initialLogoUrl !== undefined) {
      setCurrentLogo(initialLogoUrl);
    }
  }, [initialLogoUrl]);

  // Compute verify URL
  const verifyUrl = typeof window !== 'undefined'
    ? `${window.location.origin}/?viewBra=${bra.id_bra}`
    : `https://basarnas-kendari.go.id/bra/${bra.id_bra}`;

  useEffect(() => {
    // Generate QR Code as DataURL
    QRCode.toDataURL(
      verifyUrl,
      {
        width: 140,
        margin: 1,
        color: {
          dark: '#0f172a',
          light: '#ffffff',
        },
        errorCorrectionLevel: 'M',
      },
      (err, url) => {
        if (!err && url) {
          setQrDataUrl(url);
        }
      }
    );
  }, [verifyUrl, bra.id_bra]);

  // Copy plain text telegram format (Poin 1: NO dan Judul diletakkan di atas AAA)
  const handleCopyTelegram = () => {
    let text = `BRA SAR\nBADAN NASIONAL PENCARIAN DAN PERTOLONGAN\n\n`;
    text += `DARI : ${bra.dari}\n`;
    text += `AKSI : ${bra.aksi}\n`;
    text += `INFO : ${bra.info}\n`;
    if (bra.sifat) text += `SIFAT : ${bra.sifat}\n`;
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
    showToast('Mempersiapkan Cetak Dokumen Resmi...', 'info');
    await printElement('official-bra-document', `BRA_SAR_${bra.no_bra.replace(/\//g, '_')}`);
  };

  const handleDownloadHtml = () => {
    downloadDocumentHtml('official-bra-document', `BRA_SAR_${bra.no_bra.replace(/\//g, '_')}.html`);
    showToast('File Dokumen HTML Berhasil Diunduh!', 'success');
  };

  const handleDownloadQr = () => {
    if (!qrDataUrl) return;
    const link = document.createElement('a');
    link.href = qrDataUrl;
    link.download = `QR_BRA_${bra.no_bra.replace(/\//g, '_')}.png`;
    link.click();
    showToast('Barcode QR Berhasil Diunduh!', 'success');
  };

  return (
    <div className={`flex flex-col items-center ${isEmbedded ? '' : 'p-3 sm:p-6'}`}>
      {/* Top Action Toolbar (Hidden during print) */}
      <div className={`w-full max-w-4xl mb-4 print:hidden flex flex-wrap items-center justify-between gap-2.5 p-3 rounded-xl shadow-lg border transition-colors ${
        isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-md'
      }`}>
        <div className="flex items-center gap-2">
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
              : '📋 BRA SAR UMUM'}
          </span>
          <span className={`font-mono text-xs font-semibold ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
            {bra.no_bra}
          </span>
        </div>

        <div className="flex items-center gap-2">
          {/* Setting Logo Button (Poin 4) */}
          <button
            onClick={() => setShowLogoModal(true)}
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
              isDarkMode
                ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
            }`}
            title="Pengaturan Logo Kop Resmi Basarnas"
          >
            <ImageIcon className={`w-3.5 h-3.5 ${accentColors.text}`} />
            <span>Atur Logo</span>
          </button>

          {onEdit && (
            <button
              onClick={onEdit}
              className={`px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
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
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
              isDarkMode
                ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
            }`}
            title="Salin Naskah Telegram Radio"
          >
            {copied ? <Check className="w-3.5 h-3.5 text-emerald-500" /> : <Copy className="w-3.5 h-3.5 text-slate-400" />}
            <span>{copied ? 'Tersalin!' : 'Salin Naskah'}</span>
          </button>

          <button
            onClick={handleDownloadQr}
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
              isDarkMode
                ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
            }`}
            title="Unduh Barcode QR Verifikasi"
          >
            <Download className="w-3.5 h-3.5 text-slate-400" />
            <span className="hidden sm:inline">Unduh Barcode</span>
          </button>

          <button
            onClick={handleDownloadHtml}
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-lg border transition-colors ${
              isDarkMode
                ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-300 shadow-sm'
            }`}
            title="Unduh Dokumen Mandiri (Bisa Dibuka Offline)"
          >
            <FileDown className="w-3.5 h-3.5 text-slate-400" />
            <span className="hidden sm:inline">Unduh HTML</span>
          </button>

          <button
            onClick={handlePrint}
            className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 rounded-lg shadow-md transition-all hover:scale-[1.02]`}
            title="Cetak Langsung Lembar Dokumen Resmi A4"
          >
            <Printer className="w-3.5 h-3.5" />
            <span>Cetak Dokumen (PDF)</span>
          </button>

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
        className={`w-full max-w-4xl font-sans p-6 sm:p-10 transition-colors shadow-2xl border print:border-none print:shadow-none print:p-0 print:max-w-none print:bg-white print:text-black ${
          isDarkMode
            ? 'bg-slate-900 text-slate-100 border-slate-700'
            : 'bg-white text-slate-950 border-gray-300 shadow-xl'
        }`}
        style={{ minHeight: '1050px' }}
      >
        {/* Header: Basarnas Logo + Titles (KOP) */}
        <div className="flex items-center justify-center gap-3.5 sm:gap-4 pb-3 mb-2">
          {/* Logo on Left of KOP Title */}
          <div className="shrink-0 flex items-center justify-center">
            <BasarnasLogo size={50} logoUrl={currentLogo} />
          </div>

          <div className="text-center">
            <h1 className={`text-xl sm:text-2xl font-black tracking-wide uppercase leading-tight print:text-black ${
              isDarkMode ? 'text-white' : 'text-slate-950'
            }`}>
              BRA SAR
            </h1>
            <h2 className={`text-xs sm:text-sm font-bold tracking-wider uppercase mt-0.5 print:text-black ${
              isDarkMode ? 'text-slate-300' : 'text-slate-800'
            }`}>
              BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
            </h2>
          </div>
        </div>

        {/* Main Body Grid Container with Left SENKOM Note */}
        <div className="flex w-full">
          {/* Left Vertical Side Label: RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM */}
          <div
            className="w-7 sm:w-8 shrink-0 flex items-center justify-center border-r-2 border-transparent mr-1 select-none"
            style={{
              writingMode: 'vertical-rl',
              transform: 'rotate(180deg)',
            }}
          >
            <span className={`text-[9px] sm:text-[10px] font-bold tracking-widest uppercase text-center leading-none print:text-gray-800 ${
              isDarkMode ? 'text-slate-400' : 'text-gray-800'
            }`}>
              RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM
            </span>
          </div>

          {/* Right Main Table Box (Thick Borders as per standard) */}
          <div className={`flex-1 border-2 divide-y-2 text-[11px] sm:text-xs print:border-black print:divide-black ${
            isDarkMode ? 'border-slate-700 divide-slate-700' : 'border-black divide-black'
          }`}>
            {/* SENKOM Top Header Row */}
            <div className={`grid grid-cols-5 divide-x-2 text-center font-bold print:border-black print:divide-black print:bg-white print:text-black ${
              isDarkMode
                ? 'divide-slate-700 bg-slate-800/80 text-slate-200'
                : 'divide-black bg-white text-black'
            }`}>
              <div className="py-1 px-1">Panggilan</div>
              <div className="py-1 px-1">Jenis</div>
              <div className="py-1 px-1">Nr. Stn</div>
              <div className="py-1 px-1">
                <div>Derajat</div>
                <div className={`font-extrabold text-[11px] sm:text-xs border-t mt-0.5 pt-0.5 print:border-black print:text-black ${
                  isDarkMode ? 'border-slate-600 text-amber-400' : 'border-black text-black'
                }`}>
                  {bra.derajat || 'SGR'}
                </div>
              </div>
              <div className="py-1 px-1">Instruksi penerusan</div>
            </div>

            {/* Upper Section: 2 Columns */}
            {/* Left: DARI, AKSI, INFO, SIFAT */}
            {/* Right: Poin 5 - 3 Baris Kolom Berturut-turut */}
            <div className={`grid grid-cols-12 divide-x-2 print:divide-black print:bg-white ${
              isDarkMode ? 'divide-slate-700 bg-slate-900' : 'divide-black bg-white'
            }`}>
              {/* Left Column (8 cols) */}
              <div className="col-span-7 sm:col-span-8 p-2.5 space-y-1.5">
                <div className="flex">
                  <span className={`w-16 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>DARI</span>
                  <span className="mr-1">:</span>
                  <span className={`font-bold uppercase tracking-tight print:text-black ${isDarkMode ? 'text-white' : 'text-black'}`}>{bra.dari}</span>
                </div>

                <div className="flex">
                  <span className={`w-16 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>AKSI</span>
                  <span className="mr-1">:</span>
                  <span className={`uppercase print:text-black ${isDarkMode ? 'text-slate-200' : 'text-black'}`}>{bra.aksi}</span>
                </div>

                <div className="flex">
                  <span className={`w-16 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>INFO</span>
                  <span className="mr-1">:</span>
                  <span className={`uppercase print:text-black ${isDarkMode ? 'text-slate-200' : 'text-black'}`}>{bra.info}</span>
                </div>

                {bra.sifat && (
                  <div className="flex">
                    <span className={`w-16 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>SIFAT</span>
                    <span className="mr-1">:</span>
                    <span className={`font-bold uppercase print:text-black ${isDarkMode ? 'text-amber-400' : 'text-black'}`}>{bra.sifat}</span>
                  </div>
                )}
              </div>

              {/* Right Column (4 cols): Poin 2 & 4 - Kolom Tanggal Waktu Sesuai Contoh Lampiran */}
              <div className={`col-span-5 sm:col-span-4 divide-y-2 flex flex-col justify-between print:divide-black ${
                isDarkMode ? 'divide-slate-700' : 'divide-black'
              }`}>
                {/* Baris 1: TANGGAL WAKTU : */}
                <div className={`p-1.5 sm:p-2 flex items-center min-h-[30px] print:bg-white ${
                  isDarkMode ? 'bg-slate-850' : 'bg-white'
                }`}>
                  <span className={`font-bold uppercase text-[9.5px] sm:text-[10.5px] print:text-black ${
                    isDarkMode ? 'text-slate-300' : 'text-black'
                  }`}>
                    TANGGAL WAKTU :
                  </span>
                </div>

                {/* Baris 2: TWU (misal: TWU 0918 2105 H sesuai contoh lampiran) */}
                <div className={`p-1.5 sm:p-2 flex items-center justify-center text-center flex-1 min-h-[36px] print:bg-white ${
                  isDarkMode ? 'bg-slate-850' : 'bg-white'
                }`}>
                  <span className={`font-black text-xs sm:text-sm tracking-wider uppercase print:text-black ${
                    isDarkMode ? 'text-amber-400' : 'text-black'
                  }`}>
                    {bra.twu.startsWith('TWU') ? bra.twu : `TWU ${bra.twu}`}
                  </span>
                </div>

                {/* Baris 3: JUMLAH KATA */}
                <div className={`p-1.5 sm:p-2 flex items-center justify-between min-h-[30px] print:bg-white ${
                  isDarkMode ? 'bg-slate-850' : 'bg-white'
                }`}>
                  <span className={`font-bold uppercase text-[9.5px] sm:text-[10.5px] print:text-black ${
                    isDarkMode ? 'text-slate-300' : 'text-black'
                  }`}>
                    JUMLAH KATA
                  </span>
                  <span className={`font-bold text-xs uppercase print:text-black ${
                    isDarkMode ? 'text-slate-200' : 'text-black'
                  }`}>
                    {bra.jumlah_kata || `${bra.sections.reduce((acc, s) => acc + s.isi.split(/\s+/).length, 0)} KATA`}
                  </span>
                </div>
              </div>
            </div>

            {/* Telegram Body Sections (AAA, BBB, CCC, etc.) */}
            {/* Poin 1: Nomor dan Judul TIDAK PERLU KOLOM TERSENDIRI, CUKUP DIATAS AAA */}
            <div className={`border-t-2 p-3 sm:p-4 min-h-[420px] flex flex-col justify-between print:border-black print:bg-white ${
              isDarkMode ? 'border-slate-700 bg-slate-900' : 'border-black bg-white'
            }`}>
              <div>
                {/* Bagian NO dan Judul langsung di atas AAA tanpa kotak/kolom pembatas */}
                <div className="mb-3.5">
                  <div className="space-y-1">
                    <div className="flex items-center text-xs sm:text-sm font-bold uppercase">
                      <span className={`w-20 sm:w-24 font-black shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-800'}`}>NO.</span>
                      <span className="w-3 text-center shrink-0">:</span>
                      <span className={`font-extrabold tracking-wider ml-1 print:text-black ${isDarkMode ? 'text-amber-400' : 'text-black'}`}>{bra.no_bra}</span>
                      {bra.sifat && (
                        <span className={`ml-3 px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wider print:bg-black print:text-white ${
                          isDarkMode ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-black text-white'
                        }`}>
                          {bra.sifat}
                        </span>
                      )}
                    </div>

                    {bra.menbra && (
                      <div className="flex items-center text-xs sm:text-sm font-bold uppercase">
                        <span className={`w-20 sm:w-24 font-black shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-800'}`}>MENBRA</span>
                        <span className="w-3 text-center shrink-0">:</span>
                        <span className={`font-extrabold tracking-wider ml-1 print:text-black ${isDarkMode ? 'text-slate-200' : 'text-black'}`}>{bra.menbra}</span>
                      </div>
                    )}
                  </div>

                  {bra.perihal && (
                    <div className={`font-black uppercase text-xs sm:text-sm tracking-tight leading-snug mt-2.5 mb-1 print:text-black ${
                      isDarkMode ? 'text-white' : 'text-black'
                    }`}>
                      {bra.perihal}
                    </div>
                  )}
                </div>

                {/* Isian Bagian Telegram: AAA, BBB, CCC, dll. */}
                <div className="space-y-4">
                  {bra.sections.map((section, idx) => (
                    <div key={idx} className="flex items-start text-justify leading-relaxed">
                      <span className={`font-black text-xs sm:text-sm tracking-wider uppercase w-12 sm:w-14 shrink-0 print:text-black ${
                        isDarkMode ? 'text-amber-400' : 'text-black'
                      }`}>
                        {section.kode} :
                      </span>
                      <div className={`flex-1 whitespace-pre-line uppercase font-normal text-xs sm:text-[12.5px] leading-relaxed print:text-black ${
                        isDarkMode ? 'text-slate-200' : 'text-black'
                      }`}>
                        {section.isi}
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              {/* TWB di sudut kanan bawah area telegram tepat di atas garis pembatas */}
              <div className={`text-right font-black text-xs sm:text-sm tracking-wider uppercase pt-4 pr-1 print:text-black ${
                isDarkMode ? 'text-amber-400' : 'text-black'
              }`}>
                TWB {bra.twb}
              </div>
            </div>

            {/* Bottom Footer Section: SMC / Pengirim with Barcode & SENKOM Signatures */}
            <div className={`grid grid-cols-12 divide-x-2 print:divide-black print:bg-white ${
              isDarkMode ? 'divide-slate-700 bg-slate-900' : 'divide-black bg-white'
            }`}>
              {/* Left Column: Pengirim (SMC) with QR Barcode */}
              <div className="col-span-7 sm:col-span-7 p-2.5 flex flex-col justify-between">
                <div>
                  <div className="space-y-1">
                    <div className="flex">
                      <span className={`w-20 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>PENGIRIM</span>
                      <span className="mr-1">:</span>
                      <span className={`font-bold uppercase text-[11px] sm:text-xs print:text-black ${isDarkMode ? 'text-white' : 'text-black'}`}>
                        {bra.pengirim}
                      </span>
                    </div>

                    <div className="flex">
                      <span className={`w-20 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>Nama</span>
                      <span className="mr-1">:</span>
                      <span className={`font-bold uppercase text-[11px] sm:text-xs print:text-black ${isDarkMode ? 'text-slate-100' : 'text-black'}`}>
                        {bra.nama_smc}
                      </span>
                    </div>

                    <div className="flex">
                      <span className={`w-20 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>Pangkat</span>
                      <span className="mr-1">:</span>
                      <span className={`uppercase text-[11px] sm:text-xs print:text-black ${isDarkMode ? 'text-slate-300' : 'text-black'}`}>
                        {bra.pangkat_smc}
                      </span>
                    </div>

                    <div className="flex">
                      <span className={`w-20 font-bold uppercase shrink-0 print:text-black ${isDarkMode ? 'text-slate-400' : 'text-gray-700'}`}>Jabatan</span>
                      <span className="mr-1">:</span>
                      <span className={`font-bold uppercase text-[11px] sm:text-xs print:text-black ${isDarkMode ? 'text-slate-200' : 'text-black'}`}>
                        {bra.jabatan_smc}
                      </span>
                    </div>
                  </div>
                </div>

                {/* Tanda Tangan -> BARCODE QR CODE (Requirement 2) with Official Stamp */}
                <div className={`mt-2 pt-2 border-t print:border-gray-300 ${isDarkMode ? 'border-slate-700' : 'border-gray-300'}`}>
                  <div className="flex items-start justify-between">
                    <div>
                      <span className={`font-bold uppercase text-[10px] sm:text-[11px] block print:text-black ${
                        isDarkMode ? 'text-slate-300' : 'text-black'
                      }`}>
                        Tanda Tangan :
                      </span>
                      <span className={`text-[9px] font-mono block mt-0.5 print:text-gray-500 ${
                        isDarkMode ? 'text-slate-400' : 'text-gray-500'
                      }`}>
                        (Barcode Digital Terverifikasi)
                      </span>
                    </div>

                    {/* Barcode QR Digital (Poin 3: Tanpa Stempel) */}
                    <div className="flex flex-col items-center">
                      {qrDataUrl ? (
                        <div className="p-1 bg-white border border-gray-400 rounded shadow-sm">
                          <img
                            src={qrDataUrl}
                            alt={`Barcode ${bra.no_bra}`}
                            className="w-24 h-24 sm:w-28 sm:h-28 object-contain"
                          />
                        </div>
                      ) : (
                        <div className={`w-24 h-24 border border-dashed flex items-center justify-center text-[10px] ${
                          isDarkMode ? 'border-slate-700 text-slate-500' : 'border-gray-400 text-gray-400'
                        }`}>
                          Memuat Barcode...
                        </div>
                      )}

                      <div className={`text-[8.5px] font-mono text-center mt-1 print:text-gray-600 ${
                        isDarkMode ? 'text-slate-400' : 'text-gray-600'
                      }`}>
                        SCAN UNTUK VERIFIKASI
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Right Column: SENKOM Processing Columns */}
              <div className="col-span-5 sm:col-span-5 flex flex-col justify-between">
                <div className={`grid grid-cols-4 divide-x-2 border-b-2 text-center font-bold py-1 text-[10px] sm:text-[11px] print:border-black print:divide-black print:bg-white print:text-black ${
                  isDarkMode
                    ? 'divide-slate-700 border-slate-700 bg-slate-800/80 text-slate-300'
                    : 'divide-black border-black bg-white text-black'
                }`}>
                  <div>T. W. T</div>
                  <div>T. W. K</div>
                  <div>SISTEM</div>
                  <div>PARAF</div>
                </div>

                {/* Empty operator logging rows for SENKOM */}
                <div className={`grid grid-cols-4 divide-x-2 flex-1 min-h-[120px] print:divide-black print:bg-white ${
                  isDarkMode ? 'divide-slate-700 bg-slate-900' : 'divide-black bg-white'
                }`}>
                  <div className={`p-1 text-center font-mono text-[10px] print:text-gray-700 ${
                    isDarkMode ? 'text-slate-400' : 'text-gray-700'
                  }`}>
                    {bra.twt || ''}
                  </div>
                  <div className={`p-1 text-center font-mono text-[10px] print:text-gray-700 ${
                    isDarkMode ? 'text-slate-400' : 'text-gray-700'
                  }`}>
                    {bra.twk || ''}
                  </div>
                  <div className={`p-1 text-center font-mono text-[10px] print:text-gray-700 ${
                    isDarkMode ? 'text-slate-400' : 'text-gray-700'
                  }`}>
                    {bra.sistem || ''}
                  </div>
                  <div className={`p-1 text-center font-mono text-[10px] print:text-gray-700 ${
                    isDarkMode ? 'text-slate-400' : 'text-gray-700'
                  }`}>
                    {bra.paraf || ''}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* Footer tiny text */}
        <div className={`mt-2 text-right text-[8px] font-mono print:text-gray-500 ${
          isDarkMode ? 'text-slate-500' : 'text-gray-400'
        }`}>
          SISTEM QR SAR • KANTOR PENCARIAN DAN PERTOLONGAN KENDARI • DOKUMEN ELEKTRONIK RESMI
        </div>
      </div>

      {/* Modal Pengaturan Logo (Poin 4) */}
      <LogoSettingModal
        isOpen={showLogoModal}
        onClose={() => setShowLogoModal(false)}
        currentLogoUrl={currentLogo}
        onLogoUpdated={(newLogo) => {
          setCurrentLogo(newLogo);
          onLogoUpdated?.(newLogo);
        }}
      />
    </div>
  );
};
