import React, { useState, useEffect, useMemo } from 'react';
import { useTheme } from '../../context/ThemeContext';
import { decimalToDms, decimalToDmsParts, dmsPartsToDecimal } from '../../utils/geoUtils';

interface DmsNumericEditorProps {
  lat: number;
  lng: number;
  onChange: (coords: { lat: number; lng: number }) => void;
  className?: string;
  title?: string;
}

export const DmsNumericEditor: React.FC<DmsNumericEditorProps> = ({
  lat,
  lng,
  onChange,
  className,
  title = 'INPUT KOORDINAT NUMERIK (DMS)',
}) => {
  const { isDarkMode } = useTheme();

  const latParts = useMemo(() => decimalToDmsParts(lat, true), [lat]);
  const lngParts = useMemo(() => decimalToDmsParts(lng, false), [lng]);

  const [latDeg, setLatDeg] = useState<string>(String(latParts.deg));
  const [latMin, setLatMin] = useState<string>(String(latParts.min));
  const [latSec, setLatSec] = useState<string>(String(latParts.sec));
  const [latDir, setLatDir] = useState<'S' | 'N'>(latParts.dir as 'S' | 'N');

  const [lngDeg, setLngDeg] = useState<string>(String(lngParts.deg));
  const [lngMin, setLngMin] = useState<string>(String(lngParts.min));
  const [lngSec, setLngSec] = useState<string>(String(lngParts.sec));
  const [lngDir, setLngDir] = useState<'E' | 'W'>(lngParts.dir as 'E' | 'W');

  useEffect(() => {
    setLatDeg(String(latParts.deg));
    setLatMin(String(latParts.min));
    setLatSec(String(latParts.sec));
    setLatDir(latParts.dir as 'S' | 'N');

    setLngDeg(String(lngParts.deg));
    setLngMin(String(lngParts.min));
    setLngSec(String(lngParts.sec));
    setLngDir(lngParts.dir as 'E' | 'W');
  }, [latParts, lngParts]);

  const emitChange = (
    lDeg = latDeg,
    lMin = latMin,
    lSec = latSec,
    lDir = latDir,
    lnDeg = lngDeg,
    lnMin = lngMin,
    lnSec = lngSec,
    lnDir = lngDir
  ) => {
    const newLat = dmsPartsToDecimal(
      parseFloat(lDeg) || 0,
      parseFloat(lMin) || 0,
      parseFloat(lSec) || 0,
      lDir
    );
    const newLng = dmsPartsToDecimal(
      parseFloat(lnDeg) || 0,
      parseFloat(lnMin) || 0,
      parseFloat(lnSec) || 0,
      lnDir
    );
    onChange({ lat: newLat, lng: newLng });
  };

  return (
    <div
      className={`p-3 rounded-xl border text-xs space-y-2.5 ${
        isDarkMode ? 'bg-slate-950/80 border-slate-800' : 'bg-slate-50 border-slate-200'
      } ${className || ''}`}
    >
      <div className="flex items-center justify-between text-[11px] font-bold text-slate-400">
        <span>{title}</span>
        <span className="text-[10px] text-orange-500 font-mono">Angka Saja (Auto DMS)</span>
      </div>

      {/* Lintang (Latitude / S-N) */}
      <div className="space-y-1">
        <label className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 block">
          Lintang (Latitude / S-N):
        </label>
        <div className="grid grid-cols-4 gap-1.5 items-center">
          <div className="relative">
            <input
              type="number"
              min="0"
              max="90"
              value={latDeg}
              onChange={(e) => {
                setLatDeg(e.target.value);
                emitChange(e.target.value, latMin, latSec, latDir, lngDeg, lngMin, lngSec, lngDir);
              }}
              placeholder="04"
              className="w-full px-2 py-1.5 rounded-lg border text-center font-mono font-bold text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"
            />
            <span className="absolute right-1.5 top-1.5 text-[10px] text-slate-400 pointer-events-none font-bold">
              °
            </span>
          </div>

          <div className="relative">
            <input
              type="number"
              min="0"
              max="59"
              value={latMin}
              onChange={(e) => {
                setLatMin(e.target.value);
                emitChange(latDeg, e.target.value, latSec, latDir, lngDeg, lngMin, lngSec, lngDir);
              }}
              placeholder="05"
              className="w-full px-2 py-1.5 rounded-lg border text-center font-mono font-bold text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"
            />
            <span className="absolute right-1.5 top-1.5 text-[10px] text-slate-400 pointer-events-none font-bold">
              '
            </span>
          </div>

          <div className="relative">
            <input
              type="number"
              step="0.1"
              min="0"
              max="59.9"
              value={latSec}
              onChange={(e) => {
                setLatSec(e.target.value);
                emitChange(latDeg, latMin, e.target.value, latDir, lngDeg, lngMin, lngSec, lngDir);
              }}
              placeholder="03.0"
              className="w-full px-2 py-1.5 rounded-lg border text-center font-mono font-bold text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"
            />
            <span className="absolute right-1.5 top-1.5 text-[10px] text-slate-400 pointer-events-none font-bold">
              "
            </span>
          </div>

          <div>
            <select
              value={latDir}
              onChange={(e) => {
                const dir = e.target.value as 'S' | 'N';
                setLatDir(dir);
                emitChange(latDeg, latMin, latSec, dir, lngDeg, lngMin, lngSec, lngDir);
              }}
              className="w-full px-2 py-1.5 rounded-lg border text-center font-bold text-xs bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/30 cursor-pointer"
            >
              <option value="S">S (LS)</option>
              <option value="N">N (LU)</option>
            </select>
          </div>
        </div>
      </div>

      {/* Bujur (Longitude / E-W) */}
      <div className="space-y-1">
        <label className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 block">
          Bujur (Longitude / E-W):
        </label>
        <div className="grid grid-cols-4 gap-1.5 items-center">
          <div className="relative">
            <input
              type="number"
              min="0"
              max="180"
              value={lngDeg}
              onChange={(e) => {
                setLngDeg(e.target.value);
                emitChange(latDeg, latMin, latSec, latDir, e.target.value, lngMin, lngSec, lngDir);
              }}
              placeholder="122"
              className="w-full px-2 py-1.5 rounded-lg border text-center font-mono font-bold text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"
            />
            <span className="absolute right-1.5 top-1.5 text-[10px] text-slate-400 pointer-events-none font-bold">
              °
            </span>
          </div>

          <div className="relative">
            <input
              type="number"
              min="0"
              max="59"
              value={lngMin}
              onChange={(e) => {
                setLngMin(e.target.value);
                emitChange(latDeg, latMin, latSec, latDir, lngDeg, e.target.value, lngSec, lngDir);
              }}
              placeholder="25"
              className="w-full px-2 py-1.5 rounded-lg border text-center font-mono font-bold text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"
            />
            <span className="absolute right-1.5 top-1.5 text-[10px] text-slate-400 pointer-events-none font-bold">
              '
            </span>
          </div>

          <div className="relative">
            <input
              type="number"
              step="0.1"
              min="0"
              max="59.9"
              value={lngSec}
              onChange={(e) => {
                setLngSec(e.target.value);
                emitChange(latDeg, latMin, latSec, latDir, lngDeg, lngMin, e.target.value, lngDir);
              }}
              placeholder="42.0"
              className="w-full px-2 py-1.5 rounded-lg border text-center font-mono font-bold text-xs bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white"
            />
            <span className="absolute right-1.5 top-1.5 text-[10px] text-slate-400 pointer-events-none font-bold">
              "
            </span>
          </div>

          <div>
            <select
              value={lngDir}
              onChange={(e) => {
                const dir = e.target.value as 'E' | 'W';
                setLngDir(dir);
                emitChange(latDeg, latMin, latSec, latDir, lngDeg, lngMin, lngSec, dir);
              }}
              className="w-full px-2 py-1.5 rounded-lg border text-center font-bold text-xs bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/30 cursor-pointer"
            >
              <option value="E">E (BT)</option>
              <option value="W">W (BB)</option>
            </select>
          </div>
        </div>
      </div>

      {/* Live DMS String */}
      <div className="p-2 rounded-lg bg-orange-500/10 border border-orange-500/20 text-center font-mono text-[11px] font-bold text-orange-600 dark:text-orange-400">
        {decimalToDms(lat, lng)}
      </div>
    </div>
  );
};
