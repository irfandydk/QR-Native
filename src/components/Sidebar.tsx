import React, { useState, useEffect } from 'react';
import {
  LayoutDashboard,
  Map,
  Radio,
  Database,
  ShieldCheck,
  LogOut,
  ChevronLeft,
  ChevronRight,
  User,
  Sun,
  Moon,
  Palette,
  Clock,
  PhoneCall,
  Check,
  X,
} from 'lucide-react';
import { BasarnasLogo } from './BasarnasLogo';
import { useTheme } from '../context/ThemeContext';
import { UserSession, ThemePalette } from '../types';
import { showToast } from '../utils/alerts';

export type ActiveNavView =
  | 'dashboard'
  | 'map-plotting'
  | 'bra-archive'
  | 'master-data'
  | 'admin-settings'
  | 'master-settings'
  | 'form-inisiasi'
  | 'detail-hub'
  | 'form-bra'
  | 'view-bra';

interface SidebarProps {
  currentView: ActiveNavView;
  onNavigate: (view: ActiveNavView, tab?: string) => void;
  currentUser: UserSession | null;
  onLogout: () => void;
  isOpen: boolean;
  onToggle: () => void;
  isMobileOpen: boolean;
  onCloseMobile: () => void;
  logoUrl?: string;
}

export const Sidebar: React.FC<SidebarProps> = ({
  currentView,
  onNavigate,
  currentUser,
  onLogout,
  isOpen,
  onToggle,
  isMobileOpen,
  onCloseMobile,
  logoUrl,
}) => {
  const { isDarkMode, toggleDarkMode, themePalette, setThemePalette, accentColors } = useTheme();
  const [witaTime, setWitaTime] = useState<string>('');
  const [showThemePicker, setShowThemePicker] = useState<boolean>(false);

  // Live WITA Clock
  useEffect(() => {
    const updateTime = () => {
      const now = new Date();
      const options: Intl.DateTimeFormatOptions = {
        timeZone: 'Asia/Makassar',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
      };
      const timeStr = new Intl.DateTimeFormat('id-ID', options).format(now);
      setWitaTime(`${timeStr} WITA`);
    };

    updateTime();
    const timer = setInterval(updateTime, 1000);
    return () => clearInterval(timer);
  }, []);

  const THEMES: Array<{ id: ThemePalette; label: string; dotColor: string }> = [
    { id: 'orange', label: 'Basarnas Orange', dotColor: 'bg-orange-500' },
    { id: 'navy', label: 'Operasi Laut (Navy)', dotColor: 'bg-blue-600' },
    { id: 'emerald', label: 'SAR Hutan (Emerald)', dotColor: 'bg-emerald-600' },
    { id: 'gold', label: 'Taktis Emas (Gold)', dotColor: 'bg-amber-500' },
  ];

  const navItems = [
    {
      id: 'dashboard',
      label: 'Dashboard Operasi',
      subtitle: 'Daftar & status operasi SAR',
      icon: LayoutDashboard,
      isActive: currentView === 'dashboard' || currentView === 'detail-hub' || currentView === 'form-inisiasi',
      onClick: () => onNavigate('dashboard'),
    },
    {
      id: 'map-plotting',
      label: 'Ploting Peta SAR',
      subtitle: 'Jarak, luas, polygon & search area',
      icon: Map,
      badge: 'GIS',
      badgeColor: 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30',
      isActive: currentView === 'map-plotting',
      onClick: () => onNavigate('map-plotting'),
    },
    {
      id: 'bra-archive',
      label: 'BRA SAR',
      subtitle: 'Berita Radio & dokumen QR',
      icon: Radio,
      isActive: currentView === 'bra-archive' || currentView === 'form-bra' || currentView === 'view-bra',
      onClick: () => onNavigate('bra-archive'),
    },
    {
      id: 'master-data',
      label: 'Master Data',
      subtitle: 'Alut, tim SAR & pejabat',
      icon: Database,
      isActive: currentView === 'master-data' || currentView === 'master-settings',
      onClick: () => onNavigate('master-data', 'alut'),
    },
    {
      id: 'admin-settings',
      label: 'Pengaturan Admin',
      subtitle: 'Sandi admin, satker & backup',
      icon: ShieldCheck,
      badge: 'TERPISAH',
      badgeColor: 'bg-orange-500/20 text-orange-600 dark:text-orange-400 border border-orange-500/30',
      isActive: currentView === 'admin-settings',
      onClick: () => onNavigate('admin-settings', 'admin'),
    },
  ];

  return (
    <>
      {/* Mobile Backdrop */}
      {isMobileOpen && (
        <div
          onClick={onCloseMobile}
          className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden transition-opacity"
        />
      )}

      {/* Main Sidebar Container */}
      <aside
        className={`fixed top-0 bottom-0 left-0 z-40 flex flex-col transition-all duration-300 ease-in-out border-r ${
          isDarkMode
            ? 'bg-slate-900 border-slate-800 text-slate-100'
            : 'bg-white border-slate-200 text-slate-800 shadow-sm'
        } ${isOpen ? 'w-64' : 'w-20'} ${
          isMobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        }`}
      >
        {/* Brand Header + The SINGLE Minimize/Toggle Button */}
        <div className="h-16 px-3.5 flex items-center justify-between border-b border-inherit shrink-0">
          <div
            className="flex items-center gap-2.5 overflow-hidden cursor-pointer group"
            onClick={() => onNavigate('dashboard')}
            title="Dashboard Utama KPP Kendari"
          >
            <BasarnasLogo logoUrl={logoUrl} size={34} className="shrink-0 drop-shadow-sm group-hover:scale-105 transition-transform" />
            {isOpen && (
              <div className="min-w-0 transition-opacity duration-200">
                <span className="font-extrabold text-xs tracking-wider text-orange-600 dark:text-orange-500 block truncate">
                  KPP KENDARI
                </span>
                <span className="text-[10px] text-slate-500 dark:text-slate-400 block truncate font-medium">
                  Sistem QR SAR Digital
                </span>
              </div>
            )}
          </div>

          {/* THE SINGLE Minimize/Collapse Button on Desktop, Close Button on Mobile */}
          <button
            type="button"
            onClick={() => {
              if (window.innerWidth < 1024) {
                onCloseMobile();
              } else {
                onToggle();
              }
            }}
            className={`flex items-center justify-center w-7 h-7 rounded-lg border text-slate-400 hover:text-slate-200 transition-colors cursor-pointer ${
              isDarkMode ? 'border-slate-800 bg-slate-800/60' : 'border-slate-200 bg-slate-100 text-slate-600'
            }`}
            title={isOpen ? 'Ciutkan Sidebar' : 'Perluas Sidebar'}
          >
            <span className="lg:hidden">
              <X className="w-4 h-4" />
            </span>
            <span className="hidden lg:inline">
              {isOpen ? <ChevronLeft className="w-4 h-4" /> : <ChevronRight className="w-4 h-4" />}
            </span>
          </button>
        </div>

        {/* Navigation Items Area */}
        <div className="flex-1 overflow-y-auto px-2.5 py-4 space-y-1.5 scrollbar-thin">
          {isOpen && (
            <p className="px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
              Navigasi Sistem
            </p>
          )}

          {navItems.map((item) => {
            const Icon = item.icon;
            return (
              <button
                key={item.id}
                type="button"
                onClick={() => {
                  item.onClick();
                  onCloseMobile();
                }}
                title={!isOpen ? item.label : undefined}
                className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-xl text-xs font-semibold transition-all group relative cursor-pointer ${
                  item.isActive
                    ? isDarkMode
                      ? 'bg-orange-500/15 text-orange-400 border border-orange-500/30'
                      : 'bg-orange-50 text-orange-600 border border-orange-200 font-bold'
                    : isDarkMode
                    ? 'text-slate-300 hover:bg-slate-800/80 hover:text-white'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                }`}
              >
                <Icon
                  className={`w-4 h-4 shrink-0 transition-transform group-hover:scale-110 ${
                    item.isActive ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400'
                  }`}
                />
                {isOpen && (
                  <div className="flex-1 flex items-center justify-between min-w-0 text-left">
                    <span className="truncate">{item.label}</span>
                    {item.badge && (
                      <span className={`text-[9px] font-bold px-1.5 py-0.2 rounded-full ${item.badgeColor}`}>
                        {item.badge}
                      </span>
                    )}
                  </div>
                )}
                {/* Tooltip when collapsed */}
                {!isOpen && (
                  <div className="absolute left-full ml-2 px-2.5 py-1 bg-slate-900 text-white text-[11px] font-medium rounded-lg opacity-0 pointer-events-none group-hover:opacity-100 transition-opacity z-50 whitespace-nowrap shadow-lg border border-slate-700">
                    {item.label}
                  </div>
                )}
              </button>
            );
          })}
        </div>

        {/* Unified System Controls & User Footer */}
        <div className="p-2.5 border-t border-inherit shrink-0 space-y-2">
          {/* Live Clock & Emergency 115 (Unified here) */}
          {isOpen ? (
            <div
              className={`p-2 rounded-xl border text-[11px] flex items-center justify-between transition-colors ${
                isDarkMode ? 'bg-slate-800/60 border-slate-800 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'
              }`}
            >
              <div className="flex items-center gap-1.5 font-mono text-[10px]">
                <Clock className="w-3.5 h-3.5 text-orange-500 shrink-0" />
                <span className="truncate">{witaTime || 'WITA'}</span>
              </div>
              <a
                href="tel:115"
                className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-red-500/10 text-red-500 font-bold text-[10px] hover:bg-red-500/20 transition-colors"
                title="Panggil Call Center 115"
              >
                <PhoneCall className="w-3 h-3" />
                <span>115</span>
              </a>
            </div>
          ) : (
            <div className="flex justify-center" title={`WITA: ${witaTime} | Emergency: 115`}>
              <a
                href="tel:115"
                className="w-8 h-8 rounded-lg bg-red-500/10 text-red-500 flex items-center justify-center hover:bg-red-500/20 transition-colors"
              >
                <PhoneCall className="w-3.5 h-3.5" />
              </a>
            </div>
          )}

          {/* Theme & Dark Mode Controls (Unified in Sidebar) */}
          <div className="grid grid-cols-2 gap-1.5">
            <button
              type="button"
              onClick={toggleDarkMode}
              className={`flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium border transition-colors cursor-pointer ${
                isDarkMode
                  ? 'bg-slate-800 hover:bg-slate-700 text-amber-300 border-slate-700'
                  : 'bg-slate-100 hover:bg-slate-200 text-amber-600 border-slate-200'
              }`}
              title={isDarkMode ? 'Beralih ke Mode Terang' : 'Beralih ke Mode Gelap'}
            >
              {isDarkMode ? <Sun className="w-3.5 h-3.5" /> : <Moon className="w-3.5 h-3.5" />}
              {isOpen && <span className="truncate">{isDarkMode ? 'Terang' : 'Gelap'}</span>}
            </button>

            <div className="relative">
              <button
                type="button"
                onClick={() => setShowThemePicker(!showThemePicker)}
                className={`w-full flex items-center justify-center gap-1.5 py-1.5 px-2 rounded-xl text-[11px] font-medium border transition-colors cursor-pointer ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
                title="Ganti Tema Warna"
              >
                <Palette className="w-3.5 h-3.5 text-orange-500" />
                {isOpen && <span className="truncate">Tema</span>}
              </button>

              {/* Theme Picker Dropdown Popover */}
              {showThemePicker && (
                <div
                  className={`absolute bottom-full left-0 mb-2 w-48 p-1.5 rounded-xl border shadow-xl z-50 animate-in fade-in duration-100 ${
                    isDarkMode ? 'bg-slate-900 border-slate-700 text-slate-100' : 'bg-white border-slate-200 text-slate-900'
                  }`}
                >
                  <p className="px-2 py-1 text-[9px] font-bold uppercase tracking-wider text-slate-400">
                    Warna Aksen
                  </p>
                  {THEMES.map((theme) => {
                    const isSelected = themePalette === theme.id;
                    return (
                      <button
                        key={theme.id}
                        type="button"
                        onClick={() => {
                          setThemePalette(theme.id);
                          setShowThemePicker(false);
                          showToast(`Tema visual ${theme.label} diterapkan`);
                        }}
                        className={`w-full flex items-center justify-between p-1.5 rounded-lg text-left text-[11px] transition-colors cursor-pointer ${
                          isSelected
                            ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold'
                            : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'
                        }`}
                      >
                        <div className="flex items-center gap-2">
                          <span className={`w-2.5 h-2.5 rounded-full ${theme.dotColor}`} />
                          <span className="truncate">{theme.label}</span>
                        </div>
                        {isSelected && <Check className="w-3.5 h-3.5 text-orange-500 shrink-0" />}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>
          </div>

          {/* User Session & Logout */}
          <div
            className={`flex items-center gap-2 p-1.5 rounded-xl ${
              isDarkMode ? 'bg-slate-800/50' : 'bg-slate-100/70'
            }`}
          >
            <div className="w-7 h-7 rounded-lg bg-orange-600 text-white flex items-center justify-center font-bold text-xs shrink-0 shadow-xs">
              <User className="w-3.5 h-3.5" />
            </div>
            {isOpen && (
              <div className="min-w-0 flex-1">
                <p className="text-xs font-bold truncate">
                  {currentUser?.nama || currentUser?.username || 'Administrator'}
                </p>
                <p className="text-[10px] text-slate-400 truncate">
                  {currentUser?.jabatan || 'Admin SAR Kendari'}
                </p>
              </div>
            )}
            <button
              type="button"
              onClick={onLogout}
              className="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 transition-colors cursor-pointer shrink-0"
              title="Keluar dari sistem"
            >
              <LogOut className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </aside>
    </>
  );
};
