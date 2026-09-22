import React from 'react';
import {
  Maximize2,
  Plus,
  Check,
  X,
  Palette,
  Trash2,
  Crosshair,
  Edit3,
  Sparkles,
  Layers,
} from 'lucide-react';
import { useTheme } from '../../context/ThemeContext';
import { LatLngPoint, calculatePolygonArea, decimalToDms } from '../../utils/geoUtils';

export interface SavedPolygon {
  id: string;
  name: string;
  points: LatLngPoint[];
  strokeColor: string;
  strokeWeight: number;
  fillColor: string;
  fillOpacity: number;
}

export const COLOR_PALETTE = [
  { label: 'Orange Basarnas', color: '#ea580c' },
  { label: 'Merah Darurat LKP', color: '#dc2626' },
  { label: 'Biru Laut Dalam', color: '#0284c7' },
  { label: 'Kuning Siaga', color: '#d97706' },
  { label: 'Hijau Search Area', color: '#16a34a' },
  { label: 'Ungu Taktis', color: '#9333ea' },
  { label: 'Teal Pesisir', color: '#0d9488' },
  { label: 'Abu-Abu Baja', color: '#475569' },
];

interface PolygonManagerProps {
  isDrawing: boolean;
  onStartDrawing: () => void;
  onCancelDrawing: () => void;
  onSaveDrawing: () => void;

  activePolygonName: string;
  setActivePolygonName: (name: string) => void;
  activePoints: LatLngPoint[];
  onRemovePoint: (index: number) => void;

  strokeColor: string;
  setStrokeColor: (color: string) => void;
  strokeWeight: number;
  setStrokeWeight: (weight: number) => void;
  fillColor: string;
  setFillColor: (color: string) => void;
  fillOpacity: number;
  setFillOpacity: (opacity: number) => void;

  savedPolygons: SavedPolygon[];
  onEditSavedPolygon: (polygon: SavedPolygon) => void;
  onDeleteSavedPolygon: (id: string, name: string) => void;
  onFocusPolygon: (points: LatLngPoint[]) => void;
  onApplySearchPatternToPolygon: (polygon: SavedPolygon) => void;
}

export const PolygonManager: React.FC<PolygonManagerProps> = ({
  isDrawing,
  onStartDrawing,
  onCancelDrawing,
  onSaveDrawing,
  activePolygonName,
  setActivePolygonName,
  activePoints,
  onRemovePoint,
  strokeColor,
  setStrokeColor,
  strokeWeight,
  setStrokeWeight,
  fillColor,
  setFillColor,
  fillOpacity,
  setFillOpacity,
  savedPolygons,
  onEditSavedPolygon,
  onDeleteSavedPolygon,
  onFocusPolygon,
  onApplySearchPatternToPolygon,
}) => {
  const { isDarkMode } = useTheme();
  const currentArea = calculatePolygonArea(activePoints);

  return (
    <div className="space-y-3">
      {/* DRAWING / EDITING MODE PANEL */}
      {isDrawing ? (
        <div
          className={`p-4 rounded-2xl border ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          } shadow-md space-y-3.5 animate-in fade-in`}
        >
          <div className="flex items-center justify-between pb-2 border-b border-inherit">
            <span className="text-xs font-black uppercase tracking-wider text-orange-500 flex items-center gap-1.5">
              <Maximize2 className="w-4 h-4" />
              {activePolygonName ? `Edit: ${activePolygonName}` : 'Gambar Polygon Baru'}
            </span>
            <button
              type="button"
              onClick={onCancelDrawing}
              className="p-1 rounded-lg hover:bg-slate-500/10 text-slate-400 cursor-pointer"
              title="Batal"
            >
              <X className="w-4 h-4" />
            </button>
          </div>

          {/* Polygon Name */}
          <div>
            <label className="block text-[11px] font-bold text-slate-500 mb-1">
              Nama / Sektor Polygon:
            </label>
            <input
              type="text"
              value={activePolygonName}
              onChange={(e) => setActivePolygonName(e.target.value)}
              placeholder="e.g. Sektor Pencarian Alpha / Area Perairan Kolaka"
              className={`w-full px-3 py-2 rounded-xl border text-xs font-semibold ${
                isDarkMode ? 'bg-slate-950 border-slate-700 text-white' : 'bg-slate-50 border-slate-300'
              }`}
            />
          </div>

          {/* Instruction banner */}
          <div className="p-2.5 rounded-xl bg-orange-500/10 border border-orange-500/20 text-[11px] text-orange-700 dark:text-orange-300">
            👉 <strong>Klik di atas peta</strong> untuk menambah Titik A, B, C, dst.
            Semua titik <strong>dapat langsung digeser</strong> pada peta!
          </div>

          {/* Current Area Metrics */}
          <div className="grid grid-cols-2 gap-2 text-xs">
            <div className="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
              <span className="text-[10px] text-slate-400 block font-semibold">Luas Search Area</span>
              <span className="text-base font-black text-emerald-600 dark:text-emerald-400 font-mono">
                {currentArea.sqNm} <span className="text-xs font-sans">NM²</span>
              </span>
              <span className="text-[10px] text-slate-500 block font-mono">
                {currentArea.sqKm} km² ({currentArea.hectares} Ha)
              </span>
            </div>

            <div className="p-2.5 rounded-xl bg-orange-500/10 border border-orange-500/20">
              <span className="text-[10px] text-slate-400 block font-semibold">Keliling Perimeter</span>
              <span className="text-base font-black text-orange-600 dark:text-orange-400 font-mono">
                {currentArea.perimeterNm} <span className="text-xs font-sans">NM</span>
              </span>
              <span className="text-[10px] text-slate-500 block font-mono">
                {currentArea.perimeterKm} km
              </span>
            </div>
          </div>

          {/* STYLING CONTROLS: Stroke Color, Weight, Fill Color, Fill Opacity */}
          <div className="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
            <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
              <Palette className="w-3.5 h-3.5 text-orange-500" />
              Penyesuaian Garis & Area (Masing-Masing)
            </span>

            {/* Stroke Color */}
            <div className="space-y-1">
              <div className="flex items-center justify-between text-xs">
                <span className="text-[11px] font-semibold text-slate-500">1. Warna Garis Batas:</span>
                <span className="font-mono font-bold text-xs" style={{ color: strokeColor }}>
                  {strokeColor.toUpperCase()}
                </span>
              </div>
              <div className="grid grid-cols-4 gap-1.5">
                {COLOR_PALETTE.map((c) => (
                  <button
                    key={c.color}
                    type="button"
                    onClick={() => setStrokeColor(c.color)}
                    className={`h-6 rounded-lg border flex items-center justify-center transition-transform hover:scale-105 cursor-pointer ${
                      strokeColor === c.color ? 'ring-2 ring-white border-white scale-105' : 'border-transparent'
                    }`}
                    style={{ backgroundColor: c.color }}
                    title={c.label}
                  >
                    {strokeColor === c.color && <Check className="w-3 h-3 text-white" />}
                  </button>
                ))}
              </div>
            </div>

            {/* Stroke Weight */}
            <div className="space-y-1">
              <label className="text-[10px] text-slate-400 font-semibold block">
                Ketebalan Garis Batas:
              </label>
              <div className="grid grid-cols-4 gap-1.5">
                {[1.5, 2.5, 3.5, 5].map((w) => (
                  <button
                    key={w}
                    type="button"
                    onClick={() => setStrokeWeight(w)}
                    className={`py-1 text-xs font-mono font-bold rounded-lg border cursor-pointer transition-colors ${
                      strokeWeight === w
                        ? 'bg-orange-500 text-white border-orange-500'
                        : isDarkMode
                        ? 'border-slate-800 hover:bg-slate-800 text-slate-300'
                        : 'border-slate-200 hover:bg-slate-100 text-slate-700'
                    }`}
                  >
                    {w}px
                  </button>
                ))}
              </div>
            </div>

            {/* Fill Color */}
            <div className="space-y-1 pt-1.5 border-t border-inherit">
              <div className="flex items-center justify-between text-xs">
                <span className="text-[11px] font-semibold text-slate-500">2. Warna Area Isi:</span>
                <span className="font-mono font-bold text-xs" style={{ color: fillColor }}>
                  {fillColor.toUpperCase()}
                </span>
              </div>
              <div className="grid grid-cols-4 gap-1.5">
                {COLOR_PALETTE.map((c) => (
                  <button
                    key={c.color}
                    type="button"
                    onClick={() => setFillColor(c.color)}
                    className={`h-6 rounded-lg border flex items-center justify-center transition-transform hover:scale-105 cursor-pointer ${
                      fillColor === c.color ? 'ring-2 ring-white border-white scale-105' : 'border-transparent'
                    }`}
                    style={{ backgroundColor: c.color }}
                    title={c.label}
                  >
                    {fillColor === c.color && <Check className="w-3 h-3 text-white" />}
                  </button>
                ))}
              </div>
            </div>

            {/* Fill Opacity */}
            <div className="space-y-1 pt-1.5 border-t border-inherit">
              <div className="flex items-center justify-between text-xs">
                <span className="text-[10px] text-slate-400 font-semibold">Transparansi Area:</span>
                <span className="font-mono font-bold text-orange-500">
                  {Math.round(fillOpacity * 100)}%
                </span>
              </div>
              <input
                type="range"
                min="0.05"
                max="0.85"
                step="0.05"
                value={fillOpacity}
                onChange={(e) => setFillOpacity(parseFloat(e.target.value))}
                className="w-full accent-orange-600 cursor-pointer"
              />
            </div>
          </div>

          {/* Vertices List */}
          <div className="space-y-1.5">
            <span className="text-[10px] font-bold text-slate-400 uppercase block">
              Titik Sudut ({activePoints.length} Titik):
            </span>
            {activePoints.length === 0 ? (
              <p className="text-xs text-slate-400 italic">Belum ada titik. Klik peta untuk mulai menggambar.</p>
            ) : (
              <div className="max-h-36 overflow-y-auto space-y-1 scrollbar-thin pr-1">
                {activePoints.map((pt, idx) => {
                  const letter = String.fromCharCode(65 + (idx % 26));
                  return (
                    <div
                      key={idx}
                      className="p-1.5 rounded-lg border text-xs flex items-center justify-between bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800"
                    >
                      <div className="flex items-center gap-1.5">
                        <span className="w-5 h-5 rounded-full bg-orange-600 text-white font-black flex items-center justify-center text-[10px]">
                          {letter}
                        </span>
                        <span className="font-bold text-xs">{pt.label || `Titik ${letter}`}</span>
                        <span className="font-mono text-[10px] text-slate-400">
                          {decimalToDms(pt.lat, pt.lng)}
                        </span>
                      </div>
                      <button
                        type="button"
                        onClick={() => onRemovePoint(idx)}
                        className="p-1 text-red-500 hover:bg-red-500/10 rounded cursor-pointer"
                        title="Hapus titik"
                      >
                        <Trash2 className="w-3 h-3" />
                      </button>
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* Action Buttons */}
          <div className="flex items-center gap-2 pt-1 border-t border-inherit">
            <button
              type="button"
              onClick={onSaveDrawing}
              disabled={activePoints.length < 3}
              className="flex-1 py-2.5 px-3 rounded-xl bg-orange-600 hover:bg-orange-500 disabled:opacity-40 text-white font-bold text-xs shadow-md shadow-orange-600/20 flex items-center justify-center gap-1.5 cursor-pointer transition-all"
            >
              <Check className="w-4 h-4" />
              <span>Simpan Polygon ke Daftar</span>
            </button>
            <button
              type="button"
              onClick={onCancelDrawing}
              className={`py-2.5 px-3 rounded-xl border text-xs font-semibold cursor-pointer ${
                isDarkMode ? 'border-slate-800 hover:bg-slate-800' : 'border-slate-200 hover:bg-slate-100'
              }`}
            >
              Batal
            </button>
          </div>
        </div>
      ) : (
        /* SAVED POLYGONS OVERVIEW */
        <div className="space-y-3">
          <button
            type="button"
            onClick={onStartDrawing}
            className="w-full py-2.5 px-4 rounded-2xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white font-bold text-xs shadow-md shadow-orange-600/20 flex items-center justify-center gap-2 cursor-pointer transition-all"
          >
            <Plus className="w-4 h-4" />
            <span>Tambah Polygon Baru di Peta</span>
          </button>

          {/* List of Saved Polygons */}
          <div className="space-y-2">
            <div className="flex items-center justify-between text-xs text-slate-400 font-bold px-1">
              <span>POLYGON TERSIMPAN ({savedPolygons.length})</span>
            </div>

            {savedPolygons.length === 0 ? (
              <div
                className={`p-6 rounded-2xl border text-center space-y-2 ${
                  isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                }`}
              >
                <Maximize2 className="w-8 h-8 mx-auto text-slate-400" />
                <p className="text-xs font-bold text-slate-500">Belum ada polygon tersimpan.</p>
                <p className="text-[11px] text-slate-400">
                  Klik "Tambah Polygon Baru" untuk mulai menggambar area pencarian di peta.
                </p>
              </div>
            ) : (
              savedPolygons.map((poly) => {
                const area = calculatePolygonArea(poly.points);
                return (
                  <div
                    key={poly.id}
                    className={`p-3.5 rounded-2xl border space-y-2.5 transition-all ${
                      isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                    } shadow-xs`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="flex items-center gap-2 min-w-0">
                        <div
                          className="w-4 h-4 rounded-full border-2 shrink-0"
                          style={{ borderColor: poly.strokeColor, backgroundColor: poly.fillColor }}
                        />
                        <div className="min-w-0">
                          <p className="font-bold text-xs truncate text-slate-900 dark:text-white">
                            {poly.name}
                          </p>
                          <span className="text-[10px] font-bold text-orange-500">
                            {poly.points.length} Titik Sudut (A-{String.fromCharCode(64 + poly.points.length)})
                          </span>
                        </div>
                      </div>

                      <div className="flex items-center gap-1 shrink-0">
                        <button
                          type="button"
                          onClick={() => onFocusPolygon(poly.points)}
                          className="p-1.5 rounded-lg hover:bg-slate-500/10 text-slate-400 hover:text-orange-500 cursor-pointer"
                          title="Fokus peta ke polygon ini"
                        >
                          <Crosshair className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => onEditSavedPolygon(poly)}
                          className="p-1.5 rounded-lg hover:bg-slate-500/10 text-slate-400 hover:text-orange-500 cursor-pointer"
                          title="Edit polygon"
                        >
                          <Edit3 className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => onDeleteSavedPolygon(poly.id, poly.name)}
                          className="p-1.5 rounded-lg hover:bg-red-500/10 text-slate-400 hover:text-red-500 cursor-pointer"
                          title="Hapus polygon"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>

                    {/* Area Readout */}
                    <div className="grid grid-cols-2 gap-2 text-[11px] p-2 rounded-xl bg-slate-50 dark:bg-slate-950 font-mono">
                      <div>
                        <span className="text-slate-400 block text-[9px]">LUAS:</span>
                        <span className="font-bold text-emerald-600 dark:text-emerald-400">
                          {area.sqNm} NM²
                        </span>
                        <span className="text-slate-500 block text-[9px]">({area.sqKm} km²)</span>
                      </div>
                      <div>
                        <span className="text-slate-400 block text-[9px]">KELILING:</span>
                        <span className="font-bold text-orange-600 dark:text-orange-400">
                          {area.perimeterNm} NM
                        </span>
                        <span className="text-slate-500 block text-[9px]">({area.perimeterKm} km)</span>
                      </div>
                    </div>

                    {/* Apply Search Pattern Button (Requirement 6) */}
                    <button
                      type="button"
                      onClick={() => onApplySearchPatternToPolygon(poly)}
                      className="w-full py-1.5 px-3 rounded-xl bg-sky-600/10 hover:bg-sky-600/20 text-sky-600 dark:text-sky-400 border border-sky-500/30 font-bold text-xs flex items-center justify-center gap-1.5 cursor-pointer transition-colors"
                    >
                      <Sparkles className="w-3.5 h-3.5" />
                      <span>Terapkan Pola Pencarian di Polygon Ini</span>
                    </button>
                  </div>
                );
              })
            )}
          </div>
        </div>
      )}
    </div>
  );
};
