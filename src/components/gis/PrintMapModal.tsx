import React, { useRef, useState, useEffect } from 'react';
import {
  Printer,
  X,
  FileDown,
  Compass,
  MapPin,
  Layers,
  Sparkles,
  Ruler,
  CheckCircle2,
  Image as ImageIcon,
  Camera,
  Maximize2,
  RefreshCw,
  Navigation,
  Anchor,
  HelpCircle,
  Clock,
  Gauge,
} from 'lucide-react';
import {
  LatLngPoint,
  SavedPoint,
  SavedPolygon,
  SavedSearchPattern,
  SavedDistanceRoute,
  decimalToDms,
  calculatePolylineDistance,
  PointIconType,
} from '../../utils/geoUtils';
import { createPointSvgHtml } from '../../utils/advancedMarkerUtils';
import { buildGoogleStaticMapUrl } from '../../utils/mapSnapshotGenerator';
import { PRESET_POINT_ICONS } from './PointCreatorCard';
import { BASARNAS_EMBLEM_SVG_URI } from '../BasarnasLogo';

interface PrintMapModalProps {
  isOpen: boolean;
  onClose: () => void;
  savedPoints: SavedPoint[];
  savedPolygons: SavedPolygon[];
  savedSearchPatterns: SavedSearchPattern[];
  savedDistanceRoutes: SavedDistanceRoute[];
  centerCoords: { lat: number; lng: number };
  basemapType: string;
  mapSnapshotUrl?: string | null;
  onRecaptureSnapshot?: () => Promise<string | null>;
  appLogoUrl?: string | null;
}

export const PrintMapModal: React.FC<PrintMapModalProps> = ({
  isOpen,
  onClose,
  savedPoints,
  savedPolygons,
  savedSearchPatterns,
  savedDistanceRoutes,
  centerCoords,
  basemapType,
  mapSnapshotUrl: initialSnapshotUrl,
  onRecaptureSnapshot,
  appLogoUrl: propLogoUrl,
}) => {
  const printAreaRef = useRef<HTMLDivElement>(null);
  const [snapshotUrl, setSnapshotUrl] = useState<string | null>(initialSnapshotUrl || null);
  const [isCapturing, setIsCapturing] = useState(false);
  const [effectiveLogoUrl, setEffectiveLogoUrl] = useState<string>(
    propLogoUrl || localStorage.getItem('sar_custom_logo_url') || BASARNAS_EMBLEM_SVG_URI
  );

  const [docNumber, setDocNumber] = useState(
    `SAR-KDI/${new Date().getFullYear()}/${String(new Date().getMonth() + 1).padStart(2, '0')}/OPS-${String(
      Math.floor(Math.random() * 900) + 100
    )}`
  );
  const [operationTitle, setOperationTitle] = useState(
    'OPERASI PENCARIAN DAN PERTOLONGAN (SAR) KENDARI'
  );
  const [smcName, setSmcName] = useState('PETUGAS KOMUNIKASI / RESCUE OFFICER');
  const [operationScale, setOperationScale] = useState('1 : 50.000 (WGS 84 / Mercator)');

  // Fetch official satker logo from settings API if needed
  useEffect(() => {
    if (propLogoUrl) {
      setEffectiveLogoUrl(propLogoUrl);
      return;
    }
    const fetchLogo = async () => {
      try {
        const res = await fetch('/api/settings');
        if (res.ok) {
          const json = await res.json();
          if (json.success && json.data?.logo_url) {
            setEffectiveLogoUrl(json.data.logo_url);
            try {
              localStorage.setItem('sar_custom_logo_url', json.data.logo_url);
            } catch {}
          }
        }
      } catch {}
    };
    fetchLogo();
  }, [propLogoUrl]);

  // Sync snapshot url when prop changes or trigger auto-capture if blank
  useEffect(() => {
    if (initialSnapshotUrl) {
      setSnapshotUrl(initialSnapshotUrl);
    } else if (isOpen && onRecaptureSnapshot) {
      // Auto-trigger recapture if modal opened without a ready snapshot
      const timer = setTimeout(() => {
        handleRecapture();
      }, 120);
      return () => clearTimeout(timer);
    }
  }, [initialSnapshotUrl, isOpen]);

  if (!isOpen) return null;

  const staticMapFallbackUrl = buildGoogleStaticMapUrl({
    center: centerCoords,
    zoom: 11,
    mapType: basemapType,
    points: savedPoints,
    polygons: savedPolygons,
    routes: savedDistanceRoutes,
    patterns: savedSearchPatterns,
  });

  const effectiveSnapshotUrl = snapshotUrl || staticMapFallbackUrl;

  const handlePrint = () => {
    if (!printAreaRef.current) {
      window.print();
      return;
    }

    // Create a dedicated isolated print iframe to prevent any background UI from leaking into print preview/PDF
    const frame = document.createElement('iframe');
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    document.body.appendChild(frame);

    const doc = frame.contentWindow?.document;
    if (!doc) {
      window.print();
      return;
    }

    const contentHtml = printAreaRef.current.innerHTML;

    doc.open();
    doc.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>${operationTitle} - Lembar Peta SAR</title>
          <meta charset="utf-8" />
          <meta name="viewport" content="width=device-width, initial-scale=1" />
          <script src="https://cdn.tailwindcss.com"></script>
          <style>
            @page {
              size: A4 portrait;
              margin: 6mm 8mm;
            }
            body {
              font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
              background-color: #ffffff;
              color: #000000;
              margin: 0;
              padding: 0;
            }
            .print-avoid-break {
              break-inside: avoid !important;
              page-break-inside: avoid !important;
            }
            img {
              max-width: 100%;
            }
          </style>
        </head>
        <body class="bg-white text-black">
          <div style="padding: 4px;">
            ${contentHtml}
          </div>
        </body>
      </html>
    `);
    doc.close();

    // Wait for all images in the frame to load
    const images = doc.getElementsByTagName('img');
    let loadedCount = 0;
    const totalImages = images.length;

    const firePrint = () => {
      setTimeout(() => {
        try {
          frame.contentWindow?.focus();
          frame.contentWindow?.print();
        } catch {
          window.print();
        } finally {
          setTimeout(() => {
            if (document.body.contains(frame)) {
              document.body.removeChild(frame);
            }
          }, 1500);
        }
      }, 250);
    };

    if (totalImages === 0) {
      firePrint();
    } else {
      let fired = false;
      const onImgReady = () => {
        loadedCount++;
        if (loadedCount >= totalImages && !fired) {
          fired = true;
          firePrint();
        }
      };

      for (let i = 0; i < totalImages; i++) {
        const img = images[i];
        if (img.complete) {
          onImgReady();
        } else {
          img.onload = onImgReady;
          img.onerror = onImgReady;
        }
      }

      // Safety timeout
      setTimeout(() => {
        if (!fired) {
          fired = true;
          firePrint();
        }
      }, 1000);
    }
  };

  const handleRecapture = async () => {
    if (onRecaptureSnapshot) {
      setIsCapturing(true);
      try {
        const newUrl = await onRecaptureSnapshot();
        if (newUrl) setSnapshotUrl(newUrl);
      } finally {
        setIsCapturing(false);
      }
    }
  };

  const handleDownloadImage = () => {
    if (!snapshotUrl) return;
    const a = document.createElement('a');
    a.href = snapshotUrl;
    a.download = `Peta_Plotting_SAR_Kendari_${new Date().toISOString().slice(0, 10)}.png`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  };

  const currentDateFormatted = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/80 backdrop-blur-xs overflow-y-auto print:p-0 print:bg-white print:static print:overflow-visible">
      <div className="relative w-full max-w-6xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col max-h-[94vh] overflow-hidden print:max-h-none print:h-auto print:border-none print:shadow-none print:rounded-none">
        
        {/* MODAL TOPBAR (NON-PRINT) */}
        <div className="flex items-center justify-between px-6 py-3.5 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 shrink-0 print:hidden">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center font-bold">
              <Printer className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base font-black text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <span>Cetak Lembar Peta &amp; Legenda Spasial SAR</span>
                <span className="px-2 py-0.5 rounded-md bg-orange-500/10 text-orange-600 text-[10px] font-bold">
                  RESMI BASARNAS
                </span>
              </h2>
              <p className="text-xs text-slate-500">
                Mencetak tampilan visual peta aktual beserta keterangan objek yang HANYA tampil di area peta.
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {onRecaptureSnapshot && (
              <button
                type="button"
                onClick={handleRecapture}
                disabled={isCapturing}
                className="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-all"
                title="Ambil Ulang Tangkapan Layar Peta"
              >
                <RefreshCw className={`w-3.5 h-3.5 text-orange-500 ${isCapturing ? 'animate-spin' : ''}`} />
                <span className="hidden sm:inline">{isCapturing ? 'Mengambil Peta...' : 'Ambil Ulang Peta'}</span>
              </button>
            )}

            {snapshotUrl && (
              <button
                type="button"
                onClick={handleDownloadImage}
                className="px-3 py-2 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-all"
                title="Unduh Gambar Peta PNG"
              >
                <FileDown className="w-3.5 h-3.5 text-orange-500" />
                <span className="hidden sm:inline">Unduh Gambar PNG</span>
              </button>
            )}

            <button
              type="button"
              onClick={handlePrint}
              className="px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs flex items-center gap-2 shadow-md shadow-orange-600/20 cursor-pointer transition-all hover:scale-105"
            >
              <Printer className="w-4 h-4" />
              <span>Cetak Sekarang (Print / PDF)</span>
            </button>

            <button
              type="button"
              onClick={onClose}
              className="p-2 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* PRINT CONFIG BAR (NON-PRINT) */}
        <div className="px-6 py-2.5 bg-orange-500/5 border-b border-orange-500/10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs shrink-0 print:hidden">
          <div>
            <label className="text-[10px] font-bold text-slate-400 uppercase block mb-1">
              Nomor Operasi SAR:
            </label>
            <input
              type="text"
              value={docNumber}
              onChange={(e) => setDocNumber(e.target.value)}
              className="w-full px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-mono font-bold"
            />
          </div>
          <div>
            <label className="text-[10px] font-bold text-slate-400 uppercase block mb-1">
              Judul / Kejadian Operasi SAR:
            </label>
            <input
              type="text"
              value={operationTitle}
              onChange={(e) => setOperationTitle(e.target.value)}
              className="w-full px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold"
            />
          </div>
          <div>
            <label className="text-[10px] font-bold text-slate-400 uppercase block mb-1">
              Petugas SMC / Rescuer:
            </label>
            <input
              type="text"
              value={smcName}
              onChange={(e) => setSmcName(e.target.value)}
              className="w-full px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold"
            />
          </div>
          <div>
            <label className="text-[10px] font-bold text-slate-400 uppercase block mb-1">
              Skala / Proyeksi Peta:
            </label>
            <input
              type="text"
              value={operationScale}
              onChange={(e) => setOperationScale(e.target.value)}
              className="w-full px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold"
            />
          </div>
        </div>

        {/* PRINTABLE DOCUMENT CONTAINER */}
        <div className="flex-1 overflow-y-auto p-4 sm:p-6 bg-slate-100 dark:bg-slate-950/50 print:p-0 print:bg-white print:overflow-visible">
          <div
            ref={printAreaRef}
            id="official-sar-map-document"
            className="w-full max-w-5xl mx-auto bg-white text-black p-6 sm:p-8 shadow-xl rounded-2xl border border-slate-300 font-sans print:p-2 print:m-0 print:border-none print:shadow-none print:max-w-full print:rounded-none"
          >
            {/* 1. KOP RESMI BADAN NASIONAL PENCARIAN DAN PERTOLONGAN (DARI DATABASE GLOBAL) */}
            <div className="flex items-center justify-between border-b-2 border-black pb-3 mb-3">
              <div className="flex items-center gap-3 sm:gap-4">
                <img
                  src={effectiveLogoUrl || BASARNAS_EMBLEM_SVG_URI}
                  alt="Logo Resmi Basarnas"
                  className="w-16 h-16 object-contain"
                  onError={(e) => {
                    (e.currentTarget as HTMLImageElement).src = BASARNAS_EMBLEM_SVG_URI;
                  }}
                />
                <div>
                  <h3 className="text-[11px] font-black tracking-widest text-slate-800 uppercase">
                    BADAN NASIONAL PENCARIAN DAN PERTOLONGAN (BASARNAS)
                  </h3>
                  <h2 className="text-sm sm:text-base font-black tracking-wide text-black uppercase">
                    KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI
                  </h2>
                  <p className="text-[9px] sm:text-[10px] text-slate-600 leading-tight">
                    Jl. Poros Bandara Haluoleo, Baruga, Kota Kendari, Sulawesi Tenggara | Telp: (0401) 3122115 | Emergency Call: 115
                  </p>
                </div>
              </div>

              <div className="text-right font-mono text-[9px] sm:text-[10px]">
                <div className="font-extrabold border-2 border-black px-2.5 py-0.5 uppercase bg-slate-100 text-black inline-block">
                  LEMBAR PETA PLOTTING OPERASIONAL
                </div>
                <div className="mt-1 font-bold text-slate-800">No. Dok: {docNumber}</div>
                <div className="text-slate-600">{currentDateFormatted}</div>
              </div>
            </div>

            {/* 2. JUDUL OPERASI & METADATA SPASIAL */}
            <div className="text-center my-2.5 pb-2 border-b border-slate-200">
              <h1 className="text-sm sm:text-base font-black uppercase tracking-wider text-black">
                {operationTitle}
              </h1>
              <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-[10px] text-slate-700 font-mono mt-0.5">
                <span>
                  <strong>Pusat Koordinat:</strong> {decimalToDms(centerCoords.lat, centerCoords.lng)} ({centerCoords.lat.toFixed(5)}, {centerCoords.lng.toFixed(5)})
                </span>
                <span>•</span>
                <span>
                  <strong>Basemap:</strong> {basemapType.toUpperCase()}
                </span>
                <span>•</span>
                <span>
                  <strong>Datum / Proyeksi:</strong> {operationScale}
                </span>
              </div>
            </div>

            {/* 3. TAMPILAN PETA VISUAL AKTUAL (VISUAL MAP CANVAS DISPLAY) */}
            <div className="my-3 border-2 border-black rounded-lg overflow-hidden bg-slate-900 relative shadow-inner">
              {effectiveSnapshotUrl ? (
                <div className="relative w-full h-[380px] sm:h-[460px] md:h-[500px] flex items-center justify-center bg-slate-950">
                  <img
                    src={effectiveSnapshotUrl}
                    alt="Tampilan Peta Plotting SAR"
                    className="w-full h-full object-contain"
                    onError={(e) => {
                      if (staticMapFallbackUrl && e.currentTarget.src !== staticMapFallbackUrl) {
                        e.currentTarget.src = staticMapFallbackUrl;
                      }
                    }}
                  />

                  {/* Visual Compass Rose (North Arrow) Overlay on Map */}
                  <div className="absolute top-3 right-3 bg-white/90 text-black px-2 py-1.5 rounded-lg border border-black shadow-md flex flex-col items-center">
                    <div className="text-[10px] font-black text-red-600 leading-none">▲ U</div>
                    <Compass className="w-6 h-6 text-slate-800" />
                    <div className="text-[8px] font-bold text-slate-700 leading-none">NORTH</div>
                  </div>

                  {/* Graphic Scale Bar Overlay */}
                  <div className="absolute bottom-3 left-3 bg-white/90 text-black px-2.5 py-1 rounded-lg border border-black shadow-md font-mono text-[9px] flex items-center gap-2">
                    <span className="font-bold">Skala:</span>
                    <div className="flex items-center">
                      <div className="w-8 h-1.5 bg-black border border-black" />
                      <div className="w-8 h-1.5 bg-white border border-black" />
                      <div className="w-8 h-1.5 bg-black border border-black" />
                    </div>
                    <span>0 — 5 — 10 NM</span>
                  </div>

                  {/* Watermark / Badge */}
                  <div className="absolute bottom-3 right-3 bg-black/80 text-white px-2 py-0.5 rounded text-[8px] font-mono tracking-widest uppercase">
                    BASARNAS KENDARI GIS
                  </div>
                </div>
              ) : (
                <div className="w-full h-72 flex flex-col items-center justify-center bg-slate-100 text-slate-600 p-6 text-center">
                  <RefreshCw className={`w-10 h-10 text-orange-500 mb-2 ${isCapturing ? 'animate-spin' : ''}`} />
                  <p className="font-bold text-xs text-slate-800">
                    {isCapturing ? 'Sedang Mengambil Tangkapan Layar Peta...' : 'Tangkapan Visual Peta Sedang Dipersiapkan'}
                  </p>
                  <p className="text-[11px] text-slate-500 max-w-md mt-1">
                    Silakan klik tombol "Ambil Ulang Peta" di bagian atas untuk memperbarui tampilan visual peta.
                  </p>
                </div>
              )}
            </div>

            {/* 4. LEGENDA RESMI PETA SPASIAL (HANYA MEMUAT APA YANG TAMPIL PADA PETA) */}
            <div className="mt-4 space-y-3">
              <div className="bg-black text-white px-3 py-1 text-xs font-black uppercase tracking-wider flex items-center justify-between">
                <span>LEGENDA SPASIAL &amp; DATA KOORDINAT AREA TAMPILAN</span>
                <span className="text-[10px] font-mono font-normal">
                  {savedPoints.length} Poin • {savedPolygons.length} Sektor Area • {savedSearchPatterns.length} Pola SAR • {savedDistanceRoutes.length} Rute
                </span>
              </div>

              {/* GRID LEGENDA OBJEK & SIMBOL AKTIF */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px]">
                <div className="border border-black p-2 bg-slate-50 flex items-center gap-2">
                  <div className="w-4 h-4 rounded-full bg-red-600 text-white flex items-center justify-center font-bold text-[8px]">
                    L
                  </div>
                  <div>
                    <span className="font-bold block text-slate-900">Datum LKP</span>
                    <span className="text-[8px] text-slate-600">Last Known Position</span>
                  </div>
                </div>

                <div className="border border-black p-2 bg-slate-50 flex items-center gap-2">
                  <div className="w-4 h-4 rounded-lg bg-orange-500 text-white flex items-center justify-center font-bold text-[8px]">
                    P
                  </div>
                  <div>
                    <span className="font-bold block text-slate-900">Posko / Unit SAR</span>
                    <span className="text-[8px] text-slate-600">Pangkalan &amp; Rescuer</span>
                  </div>
                </div>

                <div className="border border-black p-2 bg-slate-50 flex items-center gap-2">
                  <div className="w-4 h-4 rounded-sm border-2 border-sky-600 bg-sky-500/20" />
                  <div>
                    <span className="font-bold block text-slate-900">Sektor Area (Polygon)</span>
                    <span className="text-[8px] text-slate-600">Batas Wilayah Operasi</span>
                  </div>
                </div>

                <div className="border border-black p-2 bg-slate-50 flex items-center gap-2">
                  <div className="w-5 h-1 bg-emerald-600" />
                  <div>
                    <span className="font-bold block text-slate-900">Pola SAR / Rute</span>
                    <span className="text-[8px] text-slate-600">IAMSAR &amp; Azimuth</span>
                  </div>
                </div>
              </div>

              {/* TABEL 1: DAFTAR TITIK-TITIK SPASIAL (POIN) YANG TAMPIL DI PETA */}
              {savedPoints.length > 0 ? (
                <div className="border border-black print-avoid-break">
                  <div className="bg-slate-200 px-2 py-1 text-[10px] font-bold border-b border-black flex items-center justify-between">
                    <span>A. DAFTAR TITIK SPASIAL PADA TAMPILAN PETA (POIN)</span>
                    <span>{savedPoints.length} Titik</span>
                  </div>
                  <table className="w-full text-[9px] border-collapse">
                    <thead>
                      <tr className="bg-slate-100 border-b border-black text-left font-bold">
                        <th className="p-1 border-r border-black w-6 text-center">No</th>
                        <th className="p-1 border-r border-black">Nama Titik &amp; Simbol</th>
                        <th className="p-1 border-r border-black w-32">Kategori Titik</th>
                        <th className="p-1 border-r border-black">Koordinat Derajat (DMS)</th>
                        <th className="p-1 border-r border-black">Koordinat Desimal</th>
                        <th className="p-1">Keterangan</th>
                      </tr>
                    </thead>
                    <tbody>
                      {savedPoints.map((pt, idx) => {
                        const preset = PRESET_POINT_ICONS.find((p) => p.type === pt.pointType);
                        const pointColor = preset ? preset.color : '#ea580c';
                        const displayCategory = pt.category || 'None';

                        return (
                          <tr key={pt.id || idx} className="border-b border-slate-300 print-avoid-break">
                            <td className="p-1 border-r border-black text-center font-bold">{idx + 1}</td>
                            <td className="p-1 border-r border-black font-bold">
                              <div className="flex items-center gap-1.5">
                                <div
                                  className="w-5 h-6 max-w-[20px] max-h-[24px] shrink-0 flex items-center justify-center overflow-hidden [&>svg]:w-full [&>svg]:h-full [&>svg]:max-w-full [&>svg]:max-h-full inline-block"
                                  dangerouslySetInnerHTML={{
                                    __html: createPointSvgHtml(
                                      pt.pointType || 'waypoint',
                                      pt.alphabetChar || String(idx + 1),
                                      pointColor,
                                      false,
                                      pt.customIconUrl,
                                      `print_pt_${pt.id || idx}`
                                    ),
                                  }}
                                />
                                <span className="text-[10px] leading-tight truncate">{pt.label || '-'}</span>
                              </div>
                            </td>
                            <td className="p-1 border-r border-black font-semibold text-slate-800 text-center">
                              <span className="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-300 text-[9px] font-bold inline-block">
                                {displayCategory}
                              </span>
                            </td>
                            <td className="p-1 border-r border-black font-mono font-bold text-[9.5px]">{decimalToDms(pt.lat, pt.lng)}</td>
                            <td className="p-1 border-r border-black font-mono text-[9px]">{pt.lat.toFixed(6)}, {pt.lng.toFixed(6)}</td>
                            <td className="p-1 text-slate-600 text-[9px]">{pt.notes || pt.description || '-'}</td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : null}

              {/* TABEL 2: AREA SEKTOR PENCARIAN (POLYGON) YANG TAMPIL DI PETA */}
              {savedPolygons.length > 0 ? (
                <div className="border border-black print-avoid-break">
                  <div className="bg-slate-200 px-2 py-1 text-[10px] font-bold border-b border-black flex items-center justify-between">
                    <span>B. SEKTOR AREA PENCARIAN (POLYGON)</span>
                    <span>{savedPolygons.length} Area</span>
                  </div>
                  <div className="p-2 space-y-1.5">
                    {savedPolygons.map((poly, idx) => (
                      <div key={poly.id || idx} className="border border-slate-400 p-1.5 bg-slate-50 print-avoid-break">
                        <div className="flex items-center justify-between text-[10px] font-bold border-b border-slate-300 pb-1 mb-1">
                          <span className="flex items-center gap-1.5">
                            <span
                              className="w-3 h-3 border border-black inline-block"
                              style={{ backgroundColor: poly.strokeColor || '#0284c7' }}
                            />
                            {idx + 1}. {poly.name}
                          </span>
                          <span className="font-mono text-[9px] text-slate-600">
                            {poly.points.length} Titik Sudut Perimeter
                          </span>
                        </div>
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-1 text-[8.5px] font-mono">
                          {poly.points.map((p: LatLngPoint, pIdx: number) => (
                            <div key={pIdx} className="bg-white p-1 border border-slate-300 rounded">
                              <span className="font-bold">Sudut {p.label || `#${pIdx + 1}`}:</span>{' '}
                              {decimalToDms(p.lat, p.lng)}
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : null}

              {/* TABEL 3: POLA PENCARIAN IAMSAR YANG TAMPIL DI PETA */}
              {savedSearchPatterns.length > 0 ? (
                <div className="border border-black print-avoid-break">
                  <div className="bg-slate-200 px-2 py-1 text-[10px] font-bold border-b border-black flex items-center justify-between">
                    <span>C. POLA PENCARIAN STANDAR IAMSAR</span>
                    <span>{savedSearchPatterns.length} Pola</span>
                  </div>
                  <table className="w-full text-[9px] border-collapse">
                    <thead>
                      <tr className="bg-slate-100 border-b border-black text-left font-bold">
                        <th className="p-1 border-r border-black w-6 text-center">No</th>
                        <th className="p-1 border-r border-black">Nama Pola</th>
                        <th className="p-1 border-r border-black">Tipe Pola IAMSAR</th>
                        <th className="p-1 border-r border-black">Track Spacing (S)</th>
                        <th className="p-1 border-r border-black">Jumlah Lintasan</th>
                        <th className="p-1">Koordinat Titik Awal (Entry Point)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {savedSearchPatterns.map((pat, idx) => (
                        <tr key={pat.id || idx} className="border-b border-slate-300 print-avoid-break">
                          <td className="p-1 border-r border-black text-center font-bold">{idx + 1}</td>
                          <td className="p-1 border-r border-black font-bold">{pat.name}</td>
                          <td className="p-1 border-r border-black uppercase font-mono">{pat.patternType || pat.type}</td>
                          <td className="p-1 border-r border-black font-mono font-bold">{pat.trackSpacingNm} NM</td>
                          <td className="p-1 border-r border-black font-mono">{pat.legsCount} Legs</td>
                          <td className="p-1 font-mono text-[8.5px]">
                            {pat.waypoints && pat.waypoints.length > 0
                              ? decimalToDms(pat.waypoints[0].lat, pat.waypoints[0].lng)
                              : '-'}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : null}

              {/* TABEL 4: RUTE NAVIGASI, JARAK, KECEPATAN & ESTIMASI ETA */}
              {savedDistanceRoutes.length > 0 ? (
                <div className="border border-black print-avoid-break">
                  <div className="bg-slate-200 px-2 py-1 text-[10px] font-bold border-b border-black flex items-center justify-between">
                    <span>D. RUTE PENGUKURAN JARAK, HALUAN, KECEPATAN &amp; ESTIMASI ETA</span>
                    <span>{savedDistanceRoutes.length} Rute</span>
                  </div>
                  <table className="w-full text-[9px] border-collapse">
                    <thead>
                      <tr className="bg-slate-100 border-b border-black text-left font-bold">
                        <th className="p-1 border-r border-black w-6 text-center">No</th>
                        <th className="p-1 border-r border-black">Nama Rute</th>
                        <th className="p-1 border-r border-black">Mode</th>
                        <th className="p-1 border-r border-black">Total Jarak (NM / km)</th>
                        <th className="p-1 border-r border-black">Haluan / Azimuth</th>
                        <th className="p-1 border-r border-black">Kecepatan Alut</th>
                        <th className="p-1">Estimasi Waktu Tempuh (ETA)</th>
                      </tr>
                    </thead>
                    <tbody>
                      {savedDistanceRoutes.map((rt, idx) => {
                        const dist = calculatePolylineDistance(rt.points);
                        const totalNm = rt.totalDistanceNm ?? dist.totalNm;
                        const totalKm = rt.totalDistanceKm ?? dist.totalKm;
                        const unit = rt.speedUnit || 'knot';
                        const speedVal = Number(rt.vesselSpeedKnots) || 25;
                        const etaMinutes =
                          unit === 'kmh'
                            ? totalKm > 0 && speedVal > 0
                              ? (totalKm / speedVal) * 60
                              : 0
                            : totalNm > 0 && speedVal > 0
                            ? (totalNm / speedVal) * 60
                            : 0;

                        const hrs = Math.floor(etaMinutes / 60);
                        const mins = Math.round(etaMinutes % 60);
                        const etaFormatted =
                          hrs > 0 && mins > 0
                            ? `${hrs} Jam ${mins} Menit`
                            : hrs > 0
                            ? `${hrs} Jam`
                            : `${mins} Menit`;

                        return (
                          <tr key={rt.id || idx} className="border-b border-slate-300 print-avoid-break">
                            <td className="p-1 border-r border-black text-center font-bold">{idx + 1}</td>
                            <td className="p-1 border-r border-black font-bold">{rt.name}</td>
                            <td className="p-1 border-r border-black">{rt.mode === 'straight' ? 'Garis Lurus' : 'Multi-Point'}</td>
                            <td className="p-1 border-r border-black font-mono font-bold">
                              {totalNm.toFixed(2)} NM{' '}
                              <span className="text-[8px] text-slate-600 font-normal">
                                ({totalKm.toFixed(2)} km)
                              </span>
                            </td>
                            <td className="p-1 border-r border-black font-mono font-bold">{rt.headingFormatted || '-'}</td>
                            <td className="p-1 border-r border-black font-mono font-bold">
                              {speedVal} {unit === 'kmh' ? 'KM/jam' : 'Knot'}
                            </td>
                            <td className="p-1 font-bold text-slate-800 font-mono">
                              {etaFormatted}
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              ) : null}

              {/* JIKA TIDAK ADA DATA APA PUN DI TAMPILAN PETA */}
              {savedPoints.length === 0 &&
                savedPolygons.length === 0 &&
                savedSearchPatterns.length === 0 &&
                savedDistanceRoutes.length === 0 && (
                  <div className="p-4 border border-dashed border-slate-400 text-center text-xs text-slate-500 rounded-lg">
                    Tidak ada objek plotting (poin/polygon/rute) yang berada di dalam area tampilan peta saat ini.
                  </div>
                )}
            </div>

            {/* 5. BLOK PENGESAHAN DAN TANDA TANGAN (SIGNATURE BLOCK) */}
            <div className="grid grid-cols-2 gap-8 mt-6 pt-4 border-t-2 border-black text-center text-[10px] print-avoid-break">
              <div>
                <p className="font-bold uppercase text-slate-700">MENGETAHUI,</p>
                <p className="font-black uppercase text-black">
                  KEPALA SEKSI OPERASI DAN SIAGA
                </p>
                <div className="h-16 flex items-end justify-center">
                  <p className="font-bold border-b border-black pb-0.5 inline-block min-w-40 uppercase">
                    ( ..................................................... )
                  </p>
                </div>
                <p className="text-[9px] text-slate-600 mt-0.5">KANTOR SAR KENDARI</p>
              </div>

              <div>
                <p className="font-bold uppercase text-slate-700">KENDARI, {currentDateFormatted.toUpperCase()}</p>
                <p className="font-black uppercase text-black">
                  SAR MISSION COORDINATOR (SMC) / PETUGAS
                </p>
                <div className="h-16 flex items-end justify-center">
                  <p className="font-bold border-b border-black pb-0.5 inline-block min-w-40 uppercase">
                    {smcName}
                  </p>
                </div>
                <p className="text-[9px] text-slate-600 mt-0.5">PETUGAS PLOTTING OPERASI</p>
              </div>
            </div>

          </div>
        </div>

        {/* CSS for Professional Print Formatting */}
        <style
          dangerouslySetInnerHTML={{
            __html: `
              @media print {
                body * {
                  visibility: hidden !important;
                }
                #official-sar-map-document,
                #official-sar-map-document * {
                  visibility: visible !important;
                }
                #official-sar-map-document {
                  position: absolute !important;
                  left: 0 !important;
                  top: 0 !important;
                  width: 100% !important;
                  max-width: 100% !important;
                  margin: 0 !important;
                  padding: 8mm !important;
                  box-shadow: none !important;
                  border: none !important;
                  background-color: white !important;
                  color: black !important;
                }
                .print-avoid-break {
                  break-inside: avoid !important;
                  page-break-inside: avoid !important;
                }
                @page {
                  size: A4 portrait;
                  margin: 6mm;
                }
              }
            `,
          }}
        />
      </div>
    </div>
  );
};
