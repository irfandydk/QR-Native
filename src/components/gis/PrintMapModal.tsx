import React, { useRef, useState } from 'react';
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
} from 'lucide-react';
import {
  LatLngPoint,
  SavedPoint,
  SavedPolygon,
  SavedSearchPattern,
  SavedDistanceRoute,
  decimalToDms,
  calculatePolylineDistance,
} from '../../utils/geoUtils';
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
}) => {
  const printAreaRef = useRef<HTMLDivElement>(null);
  const [docNumber, setDocNumber] = useState(
    `SAR-KDI/${new Date().getFullYear()}/${String(new Date().getMonth() + 1).padStart(2, '0')}/OPS-${String(
      Math.floor(Math.random() * 900) + 100
    )}`
  );
  const [operationTitle, setOperationTitle] = useState(
    'OPERASI PENCARIAN DAN PERTOLONGAN (SAR) KENDARI'
  );
  const [smcName, setSmcName] = useState('PETUGAS KOMUNIKASI / RESCUE OFFICER');
  const [notes, setNotes] = useState(
    'Data spasial peta plotting bersumber dari koordinat aktual datum LKP, rencana pola pencarian IAMSAR, serta rute navigasi unsur SAR.'
  );

  if (!isOpen) return null;

  const handlePrint = () => {
    window.print();
  };

  const currentDateFormatted = new Date().toLocaleDateString('id-ID', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-black/75 backdrop-blur-xs overflow-y-auto">
      <div className="relative w-full max-w-5xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 flex flex-col max-h-[92vh] overflow-hidden">
        {/* MODAL HEADER (NON-PRINT) */}
        <div className="flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 shrink-0 print:hidden">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center font-bold">
              <Printer className="w-5 h-5" />
            </div>
            <div>
              <h2 className="text-base font-black text-slate-800 dark:text-slate-100">
                Pratinjau &amp; Cetak Lembar Peta Operasi SAR
              </h2>
              <p className="text-xs text-slate-500">
                Format resmi tata naskah dinas Basarnas untuk peta plotting &amp; kalkulasi navigasi
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handlePrint}
              className="px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs flex items-center gap-2 shadow-md shadow-orange-600/20 cursor-pointer transition-all"
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
        <div className="px-6 py-2.5 bg-orange-500/5 border-b border-orange-500/10 grid grid-cols-1 md:grid-cols-3 gap-3 text-xs shrink-0 print:hidden">
          <div>
            <label className="text-[10px] font-bold text-slate-400 uppercase block mb-1">
              Nomor Operasi / Dokumen:
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
              Judul Operasi SAR:
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
              Nama Pejabat / SMC / Rescuer:
            </label>
            <input
              type="text"
              value={smcName}
              onChange={(e) => setSmcName(e.target.value)}
              className="w-full px-2.5 py-1 rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold"
            />
          </div>
        </div>

        {/* PRINTABLE DOCUMENT BODY */}
        <div className="flex-1 overflow-y-auto p-4 sm:p-8 bg-slate-100 dark:bg-slate-950/50">
          <div
            ref={printAreaRef}
            id="official-sar-map-document"
            className="w-full max-w-4xl mx-auto bg-white text-black p-8 shadow-md rounded-2xl border border-slate-300 font-sans print:p-0 print:border-none print:shadow-none print:max-w-full"
          >
            {/* KOP RESMI BASARNAS */}
            <div className="flex items-center justify-between border-b-2 border-black pb-4 mb-4">
              <div className="flex items-center gap-4">
                <img
                  src={BASARNAS_EMBLEM_SVG_URI}
                  alt="Logo Basarnas"
                  className="w-16 h-16 object-contain"
                />
                <div>
                  <h3 className="text-xs font-black tracking-widest text-slate-700 uppercase">
                    BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
                  </h3>
                  <h2 className="text-base font-black tracking-wide text-black uppercase">
                    KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI
                  </h2>
                  <p className="text-[10px] text-slate-600 leading-tight">
                    Jl. Kapten Piere Tendean No. 88 Kendari, Sulawesi Tenggara | Telp: (0401) 3122115 | Call Center: 115
                  </p>
                </div>
              </div>
              <div className="text-right font-mono text-[10px]">
                <div className="font-bold border border-black px-2 py-1 inline-block uppercase bg-slate-50">
                  LEMBAR PLOTTING SPASIAL
                </div>
                <div className="mt-1 text-slate-600">Dok: {docNumber}</div>
                <div className="text-slate-600">{currentDateFormatted}</div>
              </div>
            </div>

            {/* TITLE */}
            <div className="text-center my-3">
              <h1 className="text-sm font-black uppercase tracking-wider text-black">
                {operationTitle}
              </h1>
              <p className="text-[11px] text-slate-600 font-medium">
                Pusat Koordinat Radar: {decimalToDms(centerCoords.lat, centerCoords.lng)} ({centerCoords.lat.toFixed(5)}, {centerCoords.lng.toFixed(5)}) | Basemap: {basemapType.toUpperCase()}
              </p>
            </div>

            {/* SUMMARY STATS TILES */}
            <div className="grid grid-cols-4 gap-2 my-4 text-center">
              <div className="border border-black p-2 bg-slate-50">
                <span className="text-[9px] uppercase font-bold text-slate-600 block">Total Titik SAR</span>
                <span className="text-base font-black">{savedPoints.length}</span>
              </div>
              <div className="border border-black p-2 bg-slate-50">
                <span className="text-[9px] uppercase font-bold text-slate-600 block">Area Sektor</span>
                <span className="text-base font-black">{savedPolygons.length}</span>
              </div>
              <div className="border border-black p-2 bg-slate-50">
                <span className="text-[9px] uppercase font-bold text-slate-600 block">Pola Pencarian</span>
                <span className="text-base font-black">{savedSearchPatterns.length}</span>
              </div>
              <div className="border border-black p-2 bg-slate-50">
                <span className="text-[9px] uppercase font-bold text-slate-600 block">Rute Navigasi</span>
                <span className="text-base font-black">{savedDistanceRoutes.length}</span>
              </div>
            </div>

            {/* 1. TABEL TITIK-TITIK SAR */}
            <div className="mb-4">
              <div className="bg-black text-white px-2.5 py-1 text-[11px] font-black uppercase tracking-wider flex items-center justify-between">
                <span>1. DAFTAR TITIK OPERASI SAR (DATUM LKP, POSKO, ALUT &amp; WAYPOINT)</span>
                <span>{savedPoints.length} Titik</span>
              </div>
              <table className="w-full text-[10px] border border-black border-collapse mt-1">
                <thead>
                  <tr className="bg-slate-200 border-b border-black text-left">
                    <th className="border border-black p-1.5 w-8 text-center">No</th>
                    <th className="border border-black p-1.5">Nama Titik / Objek</th>
                    <th className="border border-black p-1.5 w-24">Jenis</th>
                    <th className="border border-black p-1.5">Koordinat Desimal</th>
                    <th className="border border-black p-1.5">Koordinat Derajat (DMS)</th>
                    <th className="border border-black p-1.5">Keterangan</th>
                  </tr>
                </thead>
                <tbody>
                  {savedPoints.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="p-2 text-center text-slate-500 italic border border-black">
                        Tidak ada titik tersimpan.
                      </td>
                    </tr>
                  ) : (
                    savedPoints.map((pt, idx) => (
                      <tr key={pt.id || idx} className="border-b border-black">
                        <td className="border border-black p-1.5 text-center font-bold">{idx + 1}</td>
                        <td className="border border-black p-1.5 font-bold">{pt.label || '-'}</td>
                        <td className="border border-black p-1.5 uppercase">{pt.pointType || 'Titik'}</td>
                        <td className="border border-black p-1.5 font-mono">
                          {pt.lat.toFixed(5)}, {pt.lng.toFixed(5)}
                        </td>
                        <td className="border border-black p-1.5 font-mono font-bold">
                          {decimalToDms(pt.lat, pt.lng)}
                        </td>
                        <td className="border border-black p-1.5">{pt.notes || '-'}</td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            {/* 2. TABEL AREA SEKTOR PENCARIAN (POLYGON) */}
            {savedPolygons.length > 0 && (
              <div className="mb-4">
                <div className="bg-black text-white px-2.5 py-1 text-[11px] font-black uppercase tracking-wider flex items-center justify-between">
                  <span>2. AREA SEKTOR PENCARIAN (POLYGON)</span>
                  <span>{savedPolygons.length} Area</span>
                </div>
                <div className="space-y-2 mt-1">
                  {savedPolygons.map((poly, idx) => (
                    <div key={poly.id || idx} className="border border-black p-2 bg-slate-50">
                      <div className="flex items-center justify-between text-[11px] font-bold border-b border-slate-300 pb-1 mb-1">
                        <span>
                          {idx + 1}. {poly.name}
                        </span>
                        <span className="font-mono text-[10px] text-slate-700">
                          {poly.points.length} Titik Sudut
                        </span>
                      </div>
                      <div className="grid grid-cols-2 sm:grid-cols-4 gap-1 text-[9px] font-mono">
                        {poly.points.map((p: LatLngPoint, pIdx: number) => (
                          <div key={pIdx} className="bg-white p-1 border border-slate-200 rounded">
                            <span className="font-bold text-slate-800">
                              Corner {p.label || `#${pIdx + 1}`}:
                            </span>{' '}
                            {decimalToDms(p.lat, p.lng)}
                          </div>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* 3. TABEL POLA PENCARIAN SAR (IAMSAR) */}
            {savedSearchPatterns.length > 0 && (
              <div className="mb-4">
                <div className="bg-black text-white px-2.5 py-1 text-[11px] font-black uppercase tracking-wider flex items-center justify-between">
                  <span>3. POLA PENCARIAN STANDAR IAMSAR</span>
                  <span>{savedSearchPatterns.length} Pola</span>
                </div>
                <table className="w-full text-[10px] border border-black border-collapse mt-1">
                  <thead>
                    <tr className="bg-slate-200 border-b border-black text-left">
                      <th className="border border-black p-1.5 w-8 text-center">No</th>
                      <th className="border border-black p-1.5">Nama Pola</th>
                      <th className="border border-black p-1.5">Tipe IAMSAR</th>
                      <th className="border border-black p-1.5">Track Spacing (S)</th>
                      <th className="border border-black p-1.5">Jumlah Legs</th>
                      <th className="border border-black p-1.5">Entry Corner (S)</th>
                    </tr>
                  </thead>
                  <tbody>
                    {savedSearchPatterns.map((pat, idx) => (
                      <tr key={pat.id || idx} className="border-b border-black">
                        <td className="border border-black p-1.5 text-center font-bold">{idx + 1}</td>
                        <td className="border border-black p-1.5 font-bold">{pat.name}</td>
                        <td className="border border-black p-1.5 uppercase font-mono">{pat.patternType || pat.type}</td>
                        <td className="border border-black p-1.5 font-mono">{pat.trackSpacingNm} NM</td>
                        <td className="border border-black p-1.5 font-mono">{pat.legsCount} Lintasan</td>
                        <td className="border border-black p-1.5 font-mono text-[9px]">
                          {pat.waypoints.length > 0
                            ? decimalToDms(pat.waypoints[0].lat, pat.waypoints[0].lng)
                            : '-'}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {/* 4. TABEL RUTE NAVIGASI & AZIMUTH */}
            {savedDistanceRoutes.length > 0 && (
              <div className="mb-4">
                <div className="bg-black text-white px-2.5 py-1 text-[11px] font-black uppercase tracking-wider flex items-center justify-between">
                  <span>4. RUTE NAVIGASI, JARAK &amp; AZIMUTH (HALUAN)</span>
                  <span>{savedDistanceRoutes.length} Rute</span>
                </div>
                <table className="w-full text-[10px] border border-black border-collapse mt-1">
                  <thead>
                    <tr className="bg-slate-200 border-b border-black text-left">
                      <th className="border border-black p-1.5 w-8 text-center">No</th>
                      <th className="border border-black p-1.5">Nama Rute</th>
                      <th className="border border-black p-1.5">Mode</th>
                      <th className="border border-black p-1.5">Jarak (NM / km)</th>
                      <th className="border border-black p-1.5">Haluan / Azimuth</th>
                      <th className="border border-black p-1.5">Speed / ETA</th>
                    </tr>
                  </thead>
                  <tbody>
                    {savedDistanceRoutes.map((rt, idx) => {
                      const dist = calculatePolylineDistance(rt.points);
                      const totalNm = rt.totalDistanceNm ?? dist.totalNm;
                      const totalKm = rt.totalDistanceKm ?? dist.totalKm;
                      return (
                        <tr key={rt.id || idx} className="border-b border-black">
                          <td className="border border-black p-1.5 text-center font-bold">{idx + 1}</td>
                          <td className="border border-black p-1.5 font-bold">{rt.name}</td>
                          <td className="border border-black p-1.5">{rt.mode === 'straight' ? 'Garis Lurus' : 'Multi-Point'}</td>
                          <td className="border border-black p-1.5 font-mono font-bold">
                            {totalNm.toFixed(2)} NM{' '}
                            <span className="text-[9px] text-slate-600 font-normal">
                              ({totalKm.toFixed(2)} km)
                            </span>
                          </td>
                          <td className="border border-black p-1.5 font-mono">{rt.headingFormatted || '-'}</td>
                          <td className="border border-black p-1.5">
                            {rt.vesselSpeedKnots ? `${rt.vesselSpeedKnots} kts` : '-'}
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}

            {/* CATATAN OPERASIONAL & PENGESAHAN */}
            <div className="grid grid-cols-2 gap-6 pt-4 border-t-2 border-black text-[10px] break-inside-avoid">
              <div>
                <h4 className="font-bold uppercase tracking-wider mb-1">Catatan Operasi &amp; Arahan SMC:</h4>
                <p className="p-2 border border-black bg-slate-50 min-h-16 text-slate-700 italic">
                  {notes}
                </p>
              </div>
              <div className="text-center flex flex-col items-center justify-between">
                <div>
                  <p>Kendari, {currentDateFormatted}</p>
                  <p className="font-bold uppercase mt-1">PETUGAS PLOTTING / SMC OPERASI SAR</p>
                </div>
                <div className="pt-12">
                  <p className="font-black underline uppercase">{smcName}</p>
                  <p className="text-[9px] text-slate-600">KANSA KENDARI / BASARNAS</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
