import React, { useState, useEffect } from 'react';
import { Calendar, Clock, RotateCcw, ChevronDown, Sparkles } from 'lucide-react';
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
  quickAddHours?: number[]; // e.g. [1, 2, 3] for ETA
}

const MONTHS_ID = [
  { val: '01', name: 'Jan' },
  { val: '02', name: 'Feb' },
  { val: '03', name: 'Mar' },
  { val: '04', name: 'Apr' },
  { val: '05', name: 'Mei' },
  { val: '06', name: 'Jun' },
  { val: '07', name: 'Jul' },
  { val: '08', name: 'Agu' },
  { val: '09', name: 'Sep' },
  { val: '10', name: 'Okt' },
  { val: '11', name: 'Nov' },
  { val: '12', name: 'Des' },
];

export function parseToDateParts(val?: string): {
  day: string;
  month: string;
  year: string;
  hours: string;
  minutes: string;
} {
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

  const now = new Date();
  return {
    day: String(now.getDate()).padStart(2, '0'),
    month: String(now.getMonth() + 1).padStart(2, '0'),
    year: String(now.getFullYear()).slice(-2),
    hours: '08',
    minutes: '00',
  };
}

export function formatToISO(
  day: string,
  month: string,
  year: string,
  hours: string = '08',
  minutes: string = '00'
): string {
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
  quickAddHours,
}) => {
  const { isDarkMode, accentColors } = useTheme();
  const parts = parseToDateParts(value);

  const [day, setDay] = useState(parts.day);
  const [month, setMonth] = useState(parts.month);
  const [year, setYear] = useState(parts.year);
  const [hours, setHours] = useState(parts.hours);
  const [minutes, setMinutes] = useState(parts.minutes);

  useEffect(() => {
    const p = parseToDateParts(value);
    setDay(p.day);
    setMonth(p.month);
    setYear(p.year);
    setHours(p.hours);
    setMinutes(p.minutes);
  }, [value]);

  const updateDateTime = (newDay: string, newMonth: string, newYear: string, newH: string, newMin: string) => {
    setDay(newDay);
    setMonth(newMonth);
    setYear(newYear);
    setHours(newH);
    setMinutes(newMin);
    onChange(formatToISO(newDay, newMonth, newYear, newH, newMin));
  };

  const setNow = () => {
    const now = new Date();
    const d = String(now.getDate()).padStart(2, '0');
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const y = String(now.getFullYear()).slice(-2);
    const h = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    updateDateTime(d, m, y, h, min);
  };

  const addMinutes = (minsToAdd: number) => {
    const fullYear = year.length === 2 ? `20${year}` : year;
    const current = new Date(`${fullYear}-${month}-${day}T${hours}:${minutes}:00`);
    current.setMinutes(current.getMinutes() + minsToAdd);

    const d = String(current.getDate()).padStart(2, '0');
    const m = String(current.getMonth() + 1).padStart(2, '0');
    const y = String(current.getFullYear()).slice(-2);
    const h = String(current.getHours()).padStart(2, '0');
    const min = String(current.getMinutes()).padStart(2, '0');
    updateDateTime(d, m, y, h, min);
  };

  // Convert for native date picker sync
  const currentIsoDateOnly = () => {
    const fullYear = `20${year}`;
    return `${fullYear}-${month}-${day}`;
  };

  const handleNativeDatePicked = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (!e.target.value) return;
    const [y, m, d] = e.target.value.split('-');
    const yr = y.slice(-2);
    updateDateTime(d, m, yr, hours, minutes);
  };

  // Generate lists for dropdowns
  const daysList = Array.from({ length: 31 }, (_, i) => String(i + 1).padStart(2, '0'));
  const hoursList = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, '0'));
  const minutesList = Array.from({ length: 60 }, (_, i) => String(i).padStart(2, '0'));
  const yearsList = ['24', '25', '26', '27', '28', '29', '30'];

  return (
    <div
      className={`p-3 rounded-xl border transition-colors ${
        isDarkMode
          ? 'bg-slate-900/90 border-slate-800'
          : 'bg-white border-slate-200 shadow-sm'
      }`}
      id={id}
    >
      {label && (
        <div className="flex items-center justify-between mb-2">
          <label className={`block text-xs font-bold ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
            {label} {required && <span className="text-red-500">*</span>}
          </label>
          <div className="flex items-center gap-1.5">
            <span
              className={`text-[10px] font-mono px-1.5 py-0.5 rounded font-bold border ${
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
              title="Set Waktu Sekarang"
              className={`text-[10px] flex items-center gap-1 px-1.5 py-0.5 rounded border transition-colors ${
                isDarkMode
                  ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                  : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
              }`}
            >
              <RotateCcw className="w-2.5 h-2.5 text-orange-500" />
              <span>Sekarang</span>
            </button>
          </div>
        </div>
      )}

      <div className="space-y-2">
        {/* Row 1: Tanggal Picker Dropdowns */}
        <div className="flex items-center gap-1.5">
          <div className="relative flex items-center">
            <Calendar className="w-3.5 h-3.5 text-slate-400 mr-1.5 flex-shrink-0" />
          </div>

          {/* Tanggal */}
          <div className="flex-1">
            <select
              value={day}
              disabled={disabled}
              onChange={(e) => updateDateTime(e.target.value, month, year, hours, minutes)}
              className={`w-full py-1.5 px-2 text-xs font-semibold rounded-lg border outline-none cursor-pointer ${
                isDarkMode
                  ? 'bg-slate-950 border-slate-700 text-slate-100 focus:border-orange-500'
                  : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500'
              }`}
              title="Pilih Tanggal"
            >
              {daysList.map((d) => (
                <option key={d} value={d}>
                  Tgl {d}
                </option>
              ))}
            </select>
          </div>

          {/* Bulan */}
          <div className="flex-1">
            <select
              value={month}
              disabled={disabled}
              onChange={(e) => updateDateTime(day, e.target.value, year, hours, minutes)}
              className={`w-full py-1.5 px-2 text-xs font-semibold rounded-lg border outline-none cursor-pointer ${
                isDarkMode
                  ? 'bg-slate-950 border-slate-700 text-slate-100 focus:border-orange-500'
                  : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500'
              }`}
              title="Pilih Bulan"
            >
              {MONTHS_ID.map((m) => (
                <option key={m.val} value={m.val}>
                  {m.name}
                </option>
              ))}
            </select>
          </div>

          {/* Tahun */}
          <div className="w-20">
            <select
              value={year}
              disabled={disabled}
              onChange={(e) => updateDateTime(day, month, e.target.value, hours, minutes)}
              className={`w-full py-1.5 px-2 text-xs font-semibold rounded-lg border outline-none cursor-pointer ${
                isDarkMode
                  ? 'bg-slate-950 border-slate-700 text-slate-100 focus:border-orange-500'
                  : 'bg-slate-50 border-slate-300 text-slate-900 focus:border-orange-500'
              }`}
              title="Pilih Tahun"
            >
              {yearsList.map((y) => (
                <option key={y} value={y}>
                  20{y}
                </option>
              ))}
            </select>
          </div>

          {/* Quick Calendar Popover Trigger */}
          <div className="relative">
            <input
              type="date"
              tabIndex={-1}
              value={currentIsoDateOnly()}
              onChange={handleNativeDatePicked}
              className="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
              title="Buka Kalender"
            />
            <button
              type="button"
              className={`p-1.5 rounded-lg border transition-colors ${
                isDarkMode
                  ? 'bg-slate-800 border-slate-700 text-orange-400 hover:bg-slate-700'
                  : 'bg-slate-100 border-slate-200 text-orange-600 hover:bg-slate-200'
              }`}
              title="Buka Kalender Pemilih Tanggal"
            >
              <Calendar className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        {/* Row 2: Waktu (Jam & Menit) Dropdowns */}
        {showTime && (
          <div className="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-200/50 dark:border-slate-800/80">
            <div className="flex items-center gap-1.5 flex-1 min-w-[200px]">
              <Clock className="w-3.5 h-3.5 text-amber-500 mr-1 flex-shrink-0" />

              {/* Pilihan Jam (00 - 23) */}
              <div className="flex-1 flex items-center gap-1">
                <span className="text-[11px] font-medium text-slate-400">Jam:</span>
                <select
                  value={hours}
                  disabled={disabled}
                  onChange={(e) => updateDateTime(day, month, year, e.target.value, minutes)}
                  className={`w-full py-1 px-2 text-xs font-mono font-bold rounded-lg border outline-none cursor-pointer ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-amber-400 focus:border-orange-500'
                      : 'bg-slate-50 border-slate-300 text-amber-700 focus:border-orange-500'
                  }`}
                  title="Pilih Jam (00 - 23)"
                >
                  {hoursList.map((h) => (
                    <option key={h} value={h}>
                      {h} : 00
                    </option>
                  ))}
                </select>
              </div>

              <span className="font-bold text-slate-400">:</span>

              {/* Pilihan Menit (00 - 59) */}
              <div className="flex-1 flex items-center gap-1">
                <span className="text-[11px] font-medium text-slate-400">Mnt:</span>
                <select
                  value={minutes}
                  disabled={disabled}
                  onChange={(e) => updateDateTime(day, month, year, hours, e.target.value)}
                  className={`w-full py-1 px-2 text-xs font-mono font-bold rounded-lg border outline-none cursor-pointer ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-amber-400 focus:border-orange-500'
                      : 'bg-slate-50 border-slate-300 text-amber-700 focus:border-orange-500'
                  }`}
                  title="Pilih Menit (00 - 59)"
                >
                  {minutesList.map((m) => (
                    <option key={m} value={m}>
                      {m}
                    </option>
                  ))}
                </select>
              </div>

              <span className="text-[10px] font-bold text-slate-400 px-1">WITA</span>
            </div>

            {/* Quick offset buttons (e.g. +30m, +1 Jam, +2 Jam) */}
            <div className="flex items-center gap-1">
              <button
                type="button"
                onClick={() => addMinutes(30)}
                className={`px-1.5 py-0.5 text-[10px] font-medium rounded border transition-colors ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
                title="Tambah 30 Menit"
              >
                +30m
              </button>
              <button
                type="button"
                onClick={() => addMinutes(60)}
                className={`px-1.5 py-0.5 text-[10px] font-medium rounded border transition-colors ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
                title="Tambah 1 Jam"
              >
                +1 Jam
              </button>
              {quickAddHours?.map((qh) => (
                <button
                  key={qh}
                  type="button"
                  onClick={() => addMinutes(qh * 60)}
                  className={`px-1.5 py-0.5 text-[10px] font-medium rounded border transition-colors ${
                    isDarkMode
                      ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                      : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                  }`}
                  title={`Tambah ${qh} Jam`}
                >
                  +{qh} Jam
                </button>
              ))}
            </div>
          </div>
        )}
      </div>

      {(helperBadge || helpText) && (
        <p className="text-[10px] text-slate-500 dark:text-slate-400 mt-1.5">
          {helpText || helperBadge}
        </p>
      )}
    </div>
  );
};
