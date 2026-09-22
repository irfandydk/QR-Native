import React from 'react';
import { Menu, Shield } from 'lucide-react';
import { useTheme } from '../context/ThemeContext';

interface HeaderProps {
  currentViewTitle?: string;
  currentViewSubtitle?: string;
  onOpenMobileSidebar?: () => void;
}

export const Header: React.FC<HeaderProps> = ({
  currentViewTitle = 'DASHBOARD OPERASI SAR',
  currentViewSubtitle = 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI • BASARNAS',
  onOpenMobileSidebar,
}) => {
  const { isDarkMode } = useTheme();

  return (
    <header
      className={`sticky top-0 z-30 backdrop-blur-md border-b transition-colors duration-200 ${
        isDarkMode
          ? 'bg-slate-900/90 border-slate-800 text-slate-100'
          : 'bg-white/95 border-slate-200 text-slate-900 shadow-xs'
      }`}
    >
      <div className="w-full px-4 sm:px-6">
        <div className="flex items-center justify-between h-14 gap-3">
          {/* Left: Mobile Drawer Trigger (Only shown on small screens) & View Title */}
          <div className="flex items-center gap-3 min-w-0">
            {onOpenMobileSidebar && (
              <button
                type="button"
                onClick={onOpenMobileSidebar}
                className={`lg:hidden p-2 rounded-xl border text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0 ${
                  isDarkMode ? 'border-slate-800 hover:bg-slate-800' : 'border-slate-200 hover:bg-slate-100'
                }`}
                title="Buka Navigasi Sistem"
              >
                <Menu className="w-5 h-5" />
              </button>
            )}

            <div className="min-w-0">
              <div className="flex items-center gap-2">
                <span className="font-extrabold text-sm sm:text-base tracking-wide uppercase truncate">
                  {currentViewTitle}
                </span>
                <span className="hidden sm:inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30 shrink-0">
                  FILE AKTIF
                </span>
              </div>
              <p className="text-[10px] text-slate-400 font-medium tracking-tight truncate hidden sm:block">
                {currentViewSubtitle}
              </p>
            </div>
          </div>

          {/* Right: Clean Official Satker Indicator (No duplicate buttons) */}
          <div className="flex items-center gap-2 shrink-0">
            <div
              className={`hidden md:flex items-center gap-2 px-3 py-1 rounded-xl border text-[11px] font-semibold ${
                isDarkMode
                  ? 'bg-slate-800/80 border-slate-700/60 text-slate-300'
                  : 'bg-slate-100 border-slate-200 text-slate-700'
              }`}
            >
              <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              <span>SISTEM SIAGA SAR KENDARI</span>
            </div>
          </div>
        </div>
      </div>
    </header>
  );
};
