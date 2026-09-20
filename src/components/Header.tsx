import React, { useState, useEffect, useRef } from 'react';
import {
  Shield,
  PlusCircle,
  Database,
  Download,
  PhoneCall,
  Clock,
  Radio,
  Sun,
  Moon,
  Palette,
  LogOut,
  User as UserIcon,
  Check,
  ChevronDown,
  Archive,
  FileEdit,
} from 'lucide-react';
import { KategoriBra, ThemePalette, UserSession } from '../types';
import { useTheme } from '../context/ThemeContext';
import { showConfirmDialog, showToast } from '../utils/alerts';

interface HeaderProps {
  currentUser?: UserSession | null;
  onLogout?: () => void;
  onNewOperasi: () => void;
  onOpenMasterData: () => void;
  onOpenSqlExport: () => void;
  onGoHome: () => void;
  onCreateBra?: (kategori: KategoriBra) => void;
  onOpenBraList?: () => void;
}

export const Header: React.FC<HeaderProps> = ({
  currentUser,
  onLogout,
  onNewOperasi,
  onOpenMasterData,
  onOpenSqlExport,
  onGoHome,
  onCreateBra,
  onOpenBraList,
}) => {
  const { isDarkMode, toggleDarkMode, themePalette, setThemePalette, accentColors } = useTheme();

  const [witaTime, setWitaTime] = useState<string>('');
  const [showThemeMenu, setShowThemeMenu] = useState(false);
  const [showUserMenu, setShowUserMenu] = useState(false);
  const [showBraMenu, setShowBraMenu] = useState(false);
  const [satkerLogo, setSatkerLogo] = useState<string | null>(null);
  const [satkerName, setSatkerName] = useState<string>('KPP KENDARI');

  const themeMenuRef = useRef<HTMLDivElement>(null);
  const userMenuRef = useRef<HTMLDivElement>(null);
  const braMenuRef = useRef<HTMLDivElement>(null);

  // Fetch satker logo & settings
  const loadSatkerSettings = async () => {
    try {
      const res = await fetch('/api/settings');
      const data = await res.json();
      if (data.success && data.data) {
        if (data.data.logo_url) {
          setSatkerLogo(data.data.logo_url);
        }
        if (data.data.nama_kantor) {
          setSatkerName(data.data.nama_kantor);
        }
      }
    } catch (err) {
      console.error('Failed to load satker settings for header:', err);
    }
  };

  useEffect(() => {
    loadSatkerSettings();
    const handleSettingsUpdated = () => {
      loadSatkerSettings();
    };
    window.addEventListener('satker-settings-updated', handleSettingsUpdated);
    return () => {
      window.removeEventListener('satker-settings-updated', handleSettingsUpdated);
    };
  }, []);

  // Close dropdowns when clicking outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (themeMenuRef.current && !themeMenuRef.current.contains(e.target as Node)) {
        setShowThemeMenu(false);
      }
      if (userMenuRef.current && !userMenuRef.current.contains(e.target as Node)) {
        setShowUserMenu(false);
      }
      if (braMenuRef.current && !braMenuRef.current.contains(e.target as Node)) {
        setShowBraMenu(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  useEffect(() => {
    const updateClock = () => {
      const d = new Date();
      // UTC + 8 hours for WITA
      const utc = d.getTime() + d.getTimezoneOffset() * 60000;
      const wita = new Date(utc + 3600000 * 8);

      const hh = String(wita.getHours()).padStart(2, '0');
      const mm = String(wita.getMinutes()).padStart(2, '0');
      const ss = String(wita.getSeconds()).padStart(2, '0');

      const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
      const months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
      ];

      const dayName = days[wita.getDay()];
      const date = wita.getDate();
      const month = months[wita.getMonth()];
      const year = wita.getFullYear();

      setWitaTime(`${dayName}, ${date} ${month} ${year} • ${hh}:${mm}:${ss} WITA`);
    };

    updateClock();
    const interval = setInterval(updateClock, 1000);
    return () => clearInterval(interval);
  }, []);

  const handleEmergencyCall = () => {
    showToast('Emergency Call 115 Basarnas: (0401) 3122115 / 115 Siaga 24 Jam', 'info');
  };

  const handleLogoutClick = async () => {
    const confirmed = await showConfirmDialog(
      'Konfirmasi Keluar',
      'Apakah Anda yakin ingin keluar dari sesi petugas SAR Kendari?',
      'Ya, Keluar'
    );
    if (confirmed && onLogout) {
      onLogout();
    }
  };

  const THEMES: Array<{ id: ThemePalette; label: string; desc: string; dotColor: string }> = [
    {
      id: 'orange',
      label: 'Basarnas Oranye',
      desc: 'Warna resmi Korps Basarnas & Siaga',
      dotColor: 'bg-orange-500',
    },
    {
      id: 'navy',
      label: 'Navy Maritim SAR',
      desc: 'Nuansa samudra biru & alut laut',
      dotColor: 'bg-cyan-500',
    },
    {
      id: 'emerald',
      label: 'Rimba Zamrud',
      desc: 'Nuansa hutan taktis & operasi darat',
      dotColor: 'bg-emerald-500',
    },
    {
      id: 'gold',
      label: 'Taktis Sunset',
      desc: 'Warna komando emas & baja',
      dotColor: 'bg-amber-500',
    },
  ];

  return (
    <header
      className={`sticky top-0 z-40 backdrop-blur-md border-b shadow-lg transition-colors duration-200 ${
        isDarkMode
          ? 'bg-slate-900/90 border-slate-800 text-slate-100'
          : 'bg-white/95 border-slate-200 text-slate-900'
      }`}
    >
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-18 gap-2">
          {/* Logo & Identity */}
          <div
            onClick={onGoHome}
            className="flex items-center gap-3 cursor-pointer group select-none shrink-0"
            title="Kembali ke Dashboard Utama"
          >
            {/* Display custom Satker Logo if uploaded, otherwise fallback to Shield Insignia */}
            {satkerLogo ? (
              <div className="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-white dark:bg-slate-800 p-1 flex items-center justify-center shadow-md shadow-orange-500/20 border border-orange-400/40 group-hover:scale-105 transition-transform duration-200 shrink-0 overflow-hidden">
                <img
                  src={satkerLogo}
                  alt="Logo Satker Basarnas"
                  referrerPolicy="no-referrer"
                  className="w-full h-full object-contain"
                  onError={() => setSatkerLogo(null)}
                />
              </div>
            ) : (
              <div className="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-gradient-to-br from-orange-500 via-orange-600 to-amber-600 flex items-center justify-center shadow-md shadow-orange-500/25 border border-orange-400/40 group-hover:scale-105 transition-transform duration-200 shrink-0">
                <Shield className="w-5 h-5 sm:w-6 sm:h-6 text-white drop-shadow" />
              </div>
            )}

            <div>
              <div className="flex items-center gap-2">
                <span className="text-lg sm:text-xl font-extrabold tracking-wider">
                  QR <span className="text-orange-500">SAR</span>
                </span>
                <span className="hidden sm:inline-block px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-orange-500/20 text-orange-600 dark:text-orange-400 border border-orange-500/30 rounded">
                  FILE AKTIF
                </span>
              </div>
              <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium tracking-tight uppercase">
                {satkerName} • Quick Report SAR
              </p>
            </div>
          </div>

          {/* Center: Live WITA Clock & Tactical Status */}
          <div
            className={`hidden xl:flex items-center gap-3 px-3.5 py-1.5 rounded-lg border text-xs ${
              isDarkMode
                ? 'bg-slate-800/80 border-slate-700/60 text-slate-300'
                : 'bg-slate-100 border-slate-200 text-slate-700'
            }`}
          >
            <div className="flex items-center gap-1.5 text-orange-500">
              <Radio className="w-3.5 h-3.5 animate-pulse text-emerald-500" />
              <span className="font-semibold text-emerald-600 dark:text-emerald-400 text-[11px] uppercase tracking-wider">
                LIVE
              </span>
            </div>
            <span className="opacity-40">|</span>
            <div className="flex items-center gap-1.5 font-mono">
              <Clock className="w-3.5 h-3.5 opacity-60" />
              <span>{witaTime || 'Memuat Waktu WITA...'}</span>
            </div>
            <span className="opacity-40">|</span>
            <button
              onClick={handleEmergencyCall}
              className="flex items-center gap-1 text-amber-600 dark:text-amber-400 font-bold text-[11px] hover:underline cursor-pointer"
              title="Hubungi Call Center 115 Kendari"
            >
              <PhoneCall className="w-3.5 h-3.5" />
              <span>115</span>
            </button>
          </div>

          {/* Right Action Buttons */}
          <div className="flex items-center gap-1.5 sm:gap-2">
            {/* Mode Malam / Siang Toggle (Poin 3) */}
            <button
              onClick={toggleDarkMode}
              className={`p-2 rounded-xl border text-xs font-medium transition-all ${
                isDarkMode
                  ? 'bg-slate-850 hover:bg-slate-800 text-amber-300 border-slate-700'
                  : 'bg-slate-100 hover:bg-slate-200 text-amber-600 border-slate-200'
              }`}
              title={isDarkMode ? 'Ganti ke Mode Siang (Light)' : 'Ganti ke Mode Malam (Dark)'}
            >
              {isDarkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
            </button>

            {/* Ubah Tema Color Picker (Poin 3) */}
            <div className="relative" ref={themeMenuRef}>
              <button
                onClick={() => setShowThemeMenu(!showThemeMenu)}
                className={`p-2 rounded-xl border text-xs font-medium flex items-center gap-1 transition-all ${
                  isDarkMode
                    ? 'bg-slate-850 hover:bg-slate-800 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
                title="Ubah Tema & Warna Aplikasi"
              >
                <Palette className="w-4 h-4 text-orange-500" />
                <ChevronDown className="w-3 h-3 opacity-60 hidden sm:inline" />
              </button>

              {showThemeMenu && (
                <div
                  className={`absolute right-0 mt-2 w-56 p-2 rounded-2xl border shadow-2xl z-50 animate-in fade-in zoom-in-95 duration-150 ${
                    isDarkMode
                      ? 'bg-slate-900 border-slate-700 text-slate-100'
                      : 'bg-white border-slate-200 text-slate-900'
                  }`}
                >
                  <div className="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-200 dark:border-slate-800 mb-1">
                    Pilihan Tema Visual
                  </div>
                  {THEMES.map((theme) => {
                    const isSelected = themePalette === theme.id;
                    return (
                      <button
                        key={theme.id}
                        type="button"
                        onClick={() => {
                          setThemePalette(theme.id);
                          setShowThemeMenu(false);
                          showToast(`Tema diubah ke ${theme.label}`);
                        }}
                        className={`w-full flex items-center justify-between p-2 rounded-xl text-left text-xs transition-colors ${
                          isSelected
                            ? 'bg-orange-500/15 text-orange-600 dark:text-orange-400 font-bold'
                            : 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300'
                        }`}
                      >
                        <div className="flex items-center gap-2.5">
                          <span className={`w-3 h-3 rounded-full ${theme.dotColor} shrink-0`} />
                          <div>
                            <div>{theme.label}</div>
                            <div className="text-[10px] font-normal opacity-60">{theme.desc}</div>
                          </div>
                        </div>
                        {isSelected && <Check className="w-4 h-4 text-orange-500 shrink-0" />}
                      </button>
                    );
                  })}
                </div>
              )}
            </div>

            {/* BRA SAR Dropdown Menu */}
            <div className="relative" ref={braMenuRef}>
              <button
                id="btn-bra-sar-menu"
                onClick={() => setShowBraMenu(!showBraMenu)}
                className={`inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border shadow-sm transition-all hover:scale-[1.02] active:scale-[0.98] ${
                  isDarkMode
                    ? 'text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 border-amber-500/40'
                    : 'text-amber-700 bg-amber-50 hover:bg-amber-100 border-amber-300'
                }`}
                title="Berita Radio SAR (BRA SAR) - Input & Arsip"
              >
                <Radio className="w-3.5 h-3.5 text-amber-500" />
                <span>BRA SAR</span>
                <ChevronDown className="w-3 h-3 opacity-60 ml-0.5" />
              </button>

              {showBraMenu && (
                <div className={`absolute right-0 mt-2 w-56 rounded-2xl shadow-2xl border p-1.5 z-50 animate-in fade-in slide-in-from-top-2 duration-150 ${
                  isDarkMode
                    ? 'bg-slate-900 border-slate-700 text-slate-200'
                    : 'bg-white border-slate-200 text-slate-800'
                }`}>
                  <div className="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 border-b border-slate-700/40 mb-1">
                    Opsi BRA SAR
                  </div>

                  {/* Opsi 1: Form Input BRA SAR */}
                  <button
                    onClick={() => {
                      setShowBraMenu(false);
                      if (onCreateBra) onCreateBra('SAR_UMUM');
                    }}
                    className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all text-left ${
                      isDarkMode
                        ? 'hover:bg-amber-500/20 text-amber-300'
                        : 'hover:bg-amber-50 text-amber-800'
                    }`}
                  >
                    <FileEdit className="w-4 h-4 text-amber-500 shrink-0" />
                    <div>
                      <div className="font-bold">Form Input BRA SAR</div>
                      <div className="text-[10px] font-normal opacity-70">Buat naskah telegram baru</div>
                    </div>
                  </button>

                  {/* Opsi 2: Tombol Arsip BRA SAR */}
                  <button
                    onClick={() => {
                      setShowBraMenu(false);
                      if (onOpenBraList) onOpenBraList();
                    }}
                    className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all text-left ${
                      isDarkMode
                        ? 'hover:bg-slate-800 text-slate-200'
                        : 'hover:bg-slate-100 text-slate-700'
                    }`}
                  >
                    <Archive className="w-4 h-4 text-orange-500 shrink-0" />
                    <div>
                      <div className="font-bold">Arsip BRA SAR</div>
                      <div className="text-[10px] font-normal opacity-70">Riwayat nomor & naskah resmi</div>
                    </div>
                  </button>
                </div>
              )}
            </div>

            {/* Master Data */}
            <button
              id="btn-master-data"
              onClick={onOpenMasterData}
              title="Kelola Master Alut, Tim Rescue & Pengaturan Pejabat"
              className={`inline-flex items-center gap-1.5 px-3 py-2 text-xs font-medium rounded-xl border transition-colors ${
                isDarkMode
                  ? 'text-slate-300 bg-slate-800 hover:bg-slate-700 border-slate-700'
                  : 'text-slate-700 bg-slate-100 hover:bg-slate-200 border-slate-200'
              }`}
            >
              <Database className="w-4 h-4 text-slate-400" />
              <span className="hidden lg:inline">Master Data & Settings</span>
            </button>

            {/* User Profile / Logout Dropdown (Poin 4: Halaman Login & Sesi Petugas) */}
            {currentUser && (
              <div className="relative pl-1 border-l border-slate-300 dark:border-slate-800" ref={userMenuRef}>
                <button
                  type="button"
                  onClick={() => setShowUserMenu(!showUserMenu)}
                  className={`flex items-center gap-2 p-1.5 rounded-xl border transition-colors ${
                    isDarkMode
                      ? 'bg-slate-850 hover:bg-slate-800 border-slate-700 text-slate-200'
                      : 'bg-slate-100 hover:bg-slate-200 border-slate-200 text-slate-800'
                  }`}
                  title={`Login sebagai: ${currentUser.nama}`}
                >
                  <div className="w-7 h-7 rounded-lg bg-orange-600 text-white font-bold text-xs flex items-center justify-center shrink-0">
                    {currentUser.nama.charAt(0)}
                  </div>
                  <div className="hidden md:block text-left text-xs leading-none max-w-[110px] truncate">
                    <div className="font-bold truncate">{currentUser.nama}</div>
                    <div className="text-[10px] opacity-60 truncate mt-0.5">
                      {currentUser.roleTitle.split(' ')[0]}
                    </div>
                  </div>
                  <ChevronDown className="w-3.5 h-3.5 opacity-50" />
                </button>

                {showUserMenu && (
                  <div
                    className={`absolute right-0 mt-2 w-64 p-3 rounded-2xl border shadow-2xl z-50 animate-in fade-in zoom-in-95 duration-150 ${
                      isDarkMode
                        ? 'bg-slate-900 border-slate-700 text-slate-100'
                        : 'bg-white border-slate-200 text-slate-900'
                    }`}
                  >
                    <div className="pb-2.5 mb-2.5 border-b border-slate-200 dark:border-slate-800">
                      <div className="font-bold text-xs text-orange-600 dark:text-orange-400">
                        {currentUser.nama}
                      </div>
                      <div className="text-[11px] font-mono opacity-75 mt-0.5">
                        NIP: {currentUser.nip}
                      </div>
                      <div className="text-[10px] text-slate-500 dark:text-slate-400 mt-1">
                        {currentUser.jabatan}
                      </div>
                    </div>

                    <button
                      type="button"
                      onClick={handleLogoutClick}
                      className="w-full flex items-center gap-2 px-3 py-2 text-xs font-semibold text-red-600 dark:text-red-400 hover:bg-red-500/10 rounded-xl transition-colors"
                    >
                      <LogOut className="w-4 h-4" />
                      <span>Keluar (Logout)</span>
                    </button>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </header>
  );
};
