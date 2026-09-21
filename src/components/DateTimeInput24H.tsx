import React, { useMemo } from 'react';
import { Calendar, Clock, RotateCcw, Sparkles } from 'lucide-react';
import { useTheme } from '../context/ThemeContext';

interface DateTimeInput24HProps {
  id?: string;
  label?: string;
  value: string; // ISO string or YYYY-MM-DDTHH:mm or DD/MM/YY HH:mm
  onChange: (value: string) => void;
  required?: boolean;
  disabled?: boolean;
  helpText?: string;
  quickAddHours?: number[]; // e.g. [1, 2, 3] for ETA
}

/**
 * Normalize any input format (ISO, DD/MM/YY HH:mm, or YYYY-MM-DDTHH:mm) to standard HTML datetime-local format (YYYY-MM-DDTHH:mm)
 */
export function toDatetimeLocalValue(val?: string): string {
  if (!val) {
    const now = new Date();
    const Y = now.getFullYear();
    const M = String(now.getMonth() + 1).padStart(2, '0');
    const D = String(now.getDate()).padStart(2, '0');
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    return `${Y}-${M}-${D}T${h}:${m}`;
  }

  // Check if already in YYYY-MM-DDTHH:mm
  if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(val)) {
    return val.substring(0, 16);
  }

  // Check if in DD/MM/YY HH:mm or DD/MM/YYYY HH:mm
  const dmyMatch = val.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})(?:\s+(\d{1,2}):(\d{2}))?/);
  if (dmyMatch) {
    const day = dmyMatch[1].padStart(2, '0');
    const month = dmyMatch[2].padStart(2, '0');
    const year = dmyMatch[3].length === 2 ? `20${dmyMatch[3]}` : dmyMatch[3];
    const hour = dmyMatch[4] ? dmyMatch[4].padStart(2, '0') : '08';
    const min = dmyMatch[5] ? dmyMatch[5].padStart(2, '0') : '00';
    return `${year}-${month}-${day}T${hour}:${min}`;
  }

  // Try standard Date parsing
  try {
    const d = new Date(val);
    if (!isNaN(d.getTime())) {
      const Y = d.getFullYear();
      const M = String(d.getMonth() + 1).padStart(2, '0');
      const D = String(d.getDate()).padStart(2, '0');
      const h = String(d.getHours()).padStart(2, '0');
      const m = String(d.getMinutes()).padStart(2, '0');
      return `${Y}-${M}-${D}T${h}:${m}`;
    }
  } catch {}

  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}T08:00`;
}

/**
 * Format date-time for human friendly Indonesian display
 */
export function formatIndonesianDateTime(valStr: string): string {
  try {
    const d = new Date(valStr);
    if (isNaN(d.getTime())) return valStr;

    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = [
      'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
      'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    const dayName = days[d.getDay()];
    const dateNum = d.getDate();
    const monthName = months[d.getMonth()];
    const year = d.getFullYear();
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');

    return `${dayName}, ${dateNum} ${monthName} ${year} • Pukul ${hours}:${minutes} WITA`;
  } catch {
    return valStr;
  }
}

export const DateTimeInput24H: React.FC<DateTimeInput24HProps> = ({
  id,
  label,
  value,
  onChange,
  required = false,
  disabled = false,
  helpText,
  quickAddHours,
}) => {
  const { isDarkMode, accentColors } = useTheme();

  const datetimeValue = useMemo(() => toDatetimeLocalValue(value), [value]);
  const formattedDisplay = useMemo(() => formatIndonesianDateTime(datetimeValue), [datetimeValue]);

  // Set current time (Sekarang)
  const setNow = () => {
    const now = new Date();
    const Y = now.getFullYear();
    const M = String(now.getMonth() + 1).padStart(2, '0');
    const D = String(now.getDate()).padStart(2, '0');
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const iso = `${Y}-${M}-${D}T${h}:${m}`;
    onChange(iso);
  };

  // Add hours
  const handleAddHours = (hoursToAdd: number) => {
    const current = new Date(datetimeValue);
    current.setHours(current.getHours() + hoursToAdd);
    const Y = current.getFullYear();
    const M = String(current.getMonth() + 1).padStart(2, '0');
    const D = String(current.getDate()).padStart(2, '0');
    const h = String(current.getHours()).padStart(2, '0');
    const m = String(current.getMinutes()).padStart(2, '0');
    onChange(`${Y}-${M}-${D}T${h}:${m}`);
  };

  return (
    <div
      id={id}
      className={`p-4 rounded-2xl border transition-all ${
        isDarkMode
          ? 'bg-slate-900/95 border-slate-800 text-slate-100 shadow-md'
          : 'bg-white border-slate-200 text-slate-900 shadow-sm'
      }`}
    >
      {/* Label and Quick Actions Header */}
      <div className="flex flex-wrap items-center justify-between gap-2 mb-2.5">
        {label && (
          <label className={`block text-xs sm:text-sm font-bold ${isDarkMode ? 'text-slate-100' : 'text-slate-900'}`}>
            {label} {required && <span className="text-red-500">*</span>}
          </label>
        )}

        <div className="flex items-center gap-1.5 ml-auto">
          <span
            className={`text-[10px] font-mono px-2 py-0.5 rounded-full font-bold border ${
              isDarkMode
                ? 'bg-orange-500/10 text-orange-400 border-orange-500/20'
                : 'bg-orange-50 text-orange-600 border-orange-200'
            }`}
          >
            24 Jam (WITA)
          </span>

          <button
            type="button"
            onClick={setNow}
            disabled={disabled}
            className={`text-[11px] font-semibold flex items-center gap-1.5 px-2.5 py-1 rounded-xl border transition-all active:scale-95 ${
              isDarkMode
                ? 'bg-slate-800 hover:bg-slate-700 text-orange-400 border-slate-700 hover:border-orange-500/40'
                : 'bg-slate-100 hover:bg-orange-50 text-orange-600 border-slate-200 hover:border-orange-300'
            }`}
          >
            <RotateCcw className="w-3 h-3 text-orange-500" />
            <span>Sekarang</span>
          </button>
        </div>
      </div>

      {/* Main Google-Style Unified Date & Time Picker */}
      <div className="space-y-2">
        <div className="relative">
          <input
            type="datetime-local"
            value={datetimeValue}
            disabled={disabled}
            required={required}
            onChange={(e) => {
              if (e.target.value) {
                onChange(e.target.value);
              }
            }}
            className={`w-full py-2.5 px-3.5 text-sm sm:text-base font-semibold font-mono rounded-xl border transition-all outline-none focus:ring-2 ${accentColors.ring} ${
              isDarkMode
                ? 'bg-slate-950 border-slate-700 text-white focus:border-orange-500 [color-scheme:dark]'
                : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500 [color-scheme:light]'
            }`}
          />
        </div>

        {/* Formatted Date Reading & Quick Preset Buttons */}
        <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
          <div className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <Calendar className="w-3.5 h-3.5 text-orange-500 shrink-0" />
            <span className="font-medium">{formattedDisplay}</span>
          </div>

          {/* Quick Add Presets (e.g. +1 Jam, +2 Jam, +3 Jam) */}
          {quickAddHours && quickAddHours.length > 0 && (
            <div className="flex items-center gap-1.5">
              <span className="text-[10px] text-slate-400">Tambah:</span>
              {quickAddHours.map((hrs) => (
                <button
                  key={hrs}
                  type="button"
                  onClick={() => handleAddHours(hrs)}
                  className={`text-[10px] font-bold px-2 py-0.5 rounded-lg border transition-colors ${
                    isDarkMode
                      ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                      : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                  }`}
                >
                  +{hrs} Jam
                </button>
              ))}
            </div>
          )}
        </div>

        {helpText && (
          <p className="text-[11px] text-slate-400 pt-0.5">{helpText}</p>
        )}
      </div>
    </div>
  );
};
