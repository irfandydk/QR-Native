import React, { useState, useEffect } from 'react';
import { Calendar, Clock, RotateCcw } from 'lucide-react';
import { useTheme } from '../context/ThemeContext';

interface DateTimeInput24HProps {
  id?: string;
  label?: string;
  value: string; // ISO string or YYYY-MM-DDTHH:mm or DD/MM/YY HH:mm
  onChange: (value: string) => void;
  required?: boolean;
  disabled?: boolean;
  placeholderDate?: string;
  showTime?: boolean;
  helperBadge?: string;
  helpText?: string;
}

// Helper functions for DD/MM/YY and 24H (HH:mm)
export function parseToDateParts(val?: string): { day: string; month: string; year: string; hours: string; minutes: string } {
  if (!val) {
    const now = new Date();
    const d = String(now.getDate()).padStart(2, '0');
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const y = String(now.getFullYear()).slice(-2);
    const h = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    return { day: d, month: m, year: y, hours: h, minutes: min };
  }

  // If already in DD/MM/YY HH:mm or DD/MM/YYYY HH:mm
  const dmyMatch = val.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})(?:\s+(\d{1,2}):(\d{2}))?/);
  if (dmyMatch) {
    const day = dmyMatch[1].padStart(2, '0');
    const month = dmyMatch[2].padStart(2, '0');
    const yr = dmyMatch[3].length === 4 ? dmyMatch[3].slice(-2) : dmyMatch[3];
    const hours = dmyMatch[4] ? dmyMatch[4].padStart(2, '0') : '08';
    const minutes = dmyMatch[5] ? dmyMatch[5].padStart(2, '0') : '00';
    return { day, month, year: yr, hours, minutes };
  }

  // Parse ISO or standard date
  try {
    const d = new Date(val);
    if (!isNaN(d.getTime())) {
      const day = String(d.getDate()).padStart(2, '0');
      const month = String(d.getMonth() + 1).padStart(2, '0');
      const year = String(d.getFullYear()).slice(-2);
      const hours = String(d.getHours()).padStart(2, '0');
      const minutes = String(d.getMinutes()).padStart(2, '0');
      return { day, month, year, hours, minutes };
    }
  } catch {
    // fallback
  }

  return { day: '01', month: '01', year: '26', hours: '08', minutes: '00' };
}

export function formatToISO(day: string, month: string, year: string, hours: string = '08', minutes: string = '00'): string {
  const fullYear = year.length === 2 ? `20${year}` : year;
  const mm = month.padStart(2, '0');
  const dd = day.padStart(2, '0');
  const hh = hours.padStart(2, '0');
  const min = minutes.padStart(2, '0');
  return `${fullYear}-${mm}-${dd}T${hh}:${min}`;
}

export const DateTimeInput24H: React.FC<DateTimeInput24HProps> = ({
  id,
  label,
  value,
  onChange,
  required = false,
  disabled = false,
  showTime = true,
  helperBadge,
  helpText,
}) => {
  const { isDarkMode, accentColors } = useTheme();
  const parts = parseToDateParts(value);

  const [dateStr, setDateStr] = useState<string>(`${parts.day}/${parts.month}/${parts.year}`);
  const [timeStr, setTimeStr] = useState<string>(`${parts.hours}:${parts.minutes}`);

  useEffect(() => {
    const p = parseToDateParts(value);
    setDateStr(`${p.day}/${p.month}/${p.year}`);
    setTimeStr(`${p.hours}:${p.minutes}`);
  }, [value]);

  const handleDateChange = (val: string) => {
    setDateStr(val);
    // Validate DD/MM/YY
    const match = val.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/);
    if (match) {
      const [_, d, m, y] = match;
      const [h, min] = timeStr.split(':');
      const iso = formatToISO(d, m, y, h || '08', min || '00');
      onChange(iso);
    }
  };

  const handleTimeChange = (val: string) => {
    setTimeStr(val);
    // Validate HH:mm (24H: 00:00 - 23:59)
    const match = val.match(/^([01]?\d|2[0-3]):([0-5]\d)$/);
    if (match) {
      const dmyMatch = dateStr.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2})$/);
      if (dmyMatch) {
        const [_, d, m, y] = dmyMatch;
        const iso = formatToISO(d, m, y, match[1], match[2]);
        onChange(iso);
      }
    }
  };

  const setNow = () => {
    const now = new Date();
    const d = String(now.getDate()).padStart(2, '0');
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const y = String(now.getFullYear()).slice(-2);
    const h = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    setDateStr(`${d}/${m}/${y}`);
    setTimeStr(`${h}:${min}`);
    onChange(formatToISO(d, m, y, h, min));
  };

  // Convert for hidden native picker sync
  const currentIsoDateOnly = () => {
    const p = parseToDateParts(value);
    const fullYear = `20${p.year}`;
    return `${fullYear}-${p.month}-${p.day}`;
  };

  const handleNativeDatePicked = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!e.target.value) return;
    const [y, m, d] = e.target.value.split('-');
    const yr = y.slice(-2);
    setDateStr(`${d}/${m}/${yr}`);
    const [h, min] = timeStr.split(':');
    onChange(formatToISO(d, m, yr, h || '08', min || '00'));
  };

  return (
    <div className="space-y-1.5" id={id}>
      {label && (
        <div className="flex items-center justify-between">
          <label className={`block text-xs font-semibold ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
            {label} {required && <span className="text-red-400">*</span>}
          </label>
          <div className="flex items-center gap-1.5">
            <span className="text-[10px] font-mono px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-400 border border-orange-500/20 font-bold">
              DD/MM/YY • 24 Jam
            </span>
            <button
              type="button"
              onClick={setNow}
              title="Set Waktu Saat Ini (WITA)"
              className="text-[10px] text-slate-400 hover:text-orange-400 flex items-center gap-0.5 transition-colors"
            >
              <RotateCcw className="w-2.5 h-2.5" />
              <span>Sekarang</span>
            </button>
          </div>
        </div>
      )}

      <div className="grid grid-cols-12 gap-2">
        {/* Date Input with DD/MM/YY */}
        <div className={`relative ${showTime ? 'col-span-7 sm:col-span-7' : 'col-span-12'}`}>
          <div className="relative flex items-center">
            <input
              type="text"
              required={required}
              disabled={disabled}
              value={dateStr}
              onChange={(e) => handleDateChange(e.target.value)}
              placeholder="DD/MM/YY"
              maxLength={8}
              className={`w-full pl-8 pr-8 py-2 rounded-xl text-xs font-mono tracking-wider border transition-colors outline-none focus:ring-2 ${accentColors.ring} ${
                isDarkMode
                  ? 'bg-slate-950 border-slate-800 text-white placeholder-slate-500'
                  : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
              }`}
            />
            <Calendar className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 pointer-events-none" />
            {/* Native date picker trigger for easy calendar icon click */}
            <input
              type="date"
              tabIndex={-1}
              value={currentIsoDateOnly()}
              onChange={handleNativeDatePicked}
              className="absolute right-2.5 w-4 h-4 opacity-0 cursor-pointer"
              title="Pilih Tanggal dari Kalender"
            />
            <Calendar className="w-3.5 h-3.5 text-orange-500 hover:text-orange-400 absolute right-2.5 pointer-events-none" />
          </div>
        </div>

        {/* Time Input 24 Jam (HH:mm) */}
        {showTime && (
          <div className="col-span-5 sm:col-span-5 relative flex items-center">
            <input
              type="text"
              required={required}
              disabled={disabled}
              value={timeStr}
              onChange={(e) => handleTimeChange(e.target.value)}
              placeholder="HH:mm (24 Jam)"
              maxLength={5}
              className={`w-full pl-8 pr-2.5 py-2 rounded-xl text-xs font-mono font-bold tracking-wider border transition-colors outline-none focus:ring-2 ${accentColors.ring} ${
                isDarkMode
                  ? 'bg-slate-950 border-slate-800 text-amber-400 placeholder-slate-500'
                  : 'bg-white border-slate-300 text-amber-600 placeholder-slate-400'
              }`}
            />
            <Clock className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 pointer-events-none" />
            <span className="absolute right-2 text-[10px] text-slate-400 pointer-events-none">WITA</span>
          </div>
        )}
      </div>

      {(helperBadge || helpText) && (
        <p className="text-[10px] text-slate-400 mt-0.5">{helpText || helperBadge}</p>
      )}
    </div>
  );
};
