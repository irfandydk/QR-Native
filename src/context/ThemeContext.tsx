import React, { createContext, useContext, useState, useEffect } from 'react';
import { ThemePalette } from '../types';

interface ThemeContextType {
  isDarkMode: boolean;
  toggleDarkMode: () => void;
  themePalette: ThemePalette;
  setThemePalette: (palette: ThemePalette) => void;
  accentColors: {
    name: string;
    primaryBg: string;
    hoverBg: string;
    text: string;
    border: string;
    ring: string;
    badgeBg: string;
    badgeText: string;
    gradient: string;
  };
}

const PALETTE_CONFIGS: Record<ThemePalette, ThemeContextType['accentColors']> = {
  orange: {
    name: 'Basarnas Rescue (Oranye)',
    primaryBg: 'bg-orange-600',
    hoverBg: 'hover:bg-orange-500',
    text: 'text-orange-500 dark:text-orange-400',
    border: 'border-orange-500',
    ring: 'focus:ring-orange-500',
    badgeBg: 'bg-orange-500/15',
    badgeText: 'text-orange-600 dark:text-orange-400',
    gradient: 'from-orange-500 to-amber-600',
  },
  navy: {
    name: 'Maritim SAR (Navy & Sian)',
    primaryBg: 'bg-cyan-600',
    hoverBg: 'hover:bg-cyan-500',
    text: 'text-cyan-600 dark:text-cyan-400',
    border: 'border-cyan-500',
    ring: 'focus:ring-cyan-500',
    badgeBg: 'bg-cyan-500/15',
    badgeText: 'text-cyan-700 dark:text-cyan-300',
    gradient: 'from-blue-600 to-cyan-500',
  },
  emerald: {
    name: 'Operasi Rimba (Zamrud)',
    primaryBg: 'bg-emerald-600',
    hoverBg: 'hover:bg-emerald-500',
    text: 'text-emerald-600 dark:text-emerald-400',
    border: 'border-emerald-500',
    ring: 'focus:ring-emerald-500',
    badgeBg: 'bg-emerald-500/15',
    badgeText: 'text-emerald-700 dark:text-emerald-300',
    gradient: 'from-emerald-600 to-teal-500',
  },
  gold: {
    name: 'Taktis Sunset (Emas & Baja)',
    primaryBg: 'bg-amber-600',
    hoverBg: 'hover:bg-amber-500',
    text: 'text-amber-600 dark:text-amber-400',
    border: 'border-amber-500',
    ring: 'focus:ring-amber-500',
    badgeBg: 'bg-amber-500/15',
    badgeText: 'text-amber-700 dark:text-amber-300',
    gradient: 'from-amber-500 to-yellow-600',
  },
};

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export const ThemeProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [isDarkMode, setIsDarkMode] = useState<boolean>(() => {
    const saved = localStorage.getItem('basarnas_dark_mode');
    return saved !== null ? saved === 'true' : true;
  });

  const [themePalette, setThemePaletteState] = useState<ThemePalette>(() => {
    const saved = localStorage.getItem('basarnas_theme_palette');
    return (saved as ThemePalette) || 'orange';
  });

  useEffect(() => {
    const root = document.documentElement;
    if (isDarkMode) {
      root.classList.add('dark');
      root.classList.remove('light');
      document.body.style.backgroundColor = '#0b0f19';
      document.body.style.color = '#f8fafc';
    } else {
      root.classList.remove('dark');
      root.classList.add('light');
      document.body.style.backgroundColor = '#f8fafc';
      document.body.style.color = '#0f172a';
    }
    localStorage.setItem('basarnas_dark_mode', String(isDarkMode));
  }, [isDarkMode]);

  useEffect(() => {
    const root = document.documentElement;
    root.setAttribute('data-theme', themePalette);
    const ACCENTS: Record<ThemePalette, { hex: string; glow: string }> = {
      orange: { hex: '#ea580c', glow: 'rgba(234, 88, 12, 0.15)' },
      navy: { hex: '#0891b2', glow: 'rgba(8, 145, 178, 0.15)' },
      emerald: { hex: '#059669', glow: 'rgba(5, 150, 105, 0.15)' },
      gold: { hex: '#d97706', glow: 'rgba(217, 119, 6, 0.15)' },
    };
    const accent = ACCENTS[themePalette] || ACCENTS.orange;
    root.style.setProperty('--accent-primary', accent.hex);
    root.style.setProperty('--accent-glow', accent.glow);
  }, [themePalette]);

  const setThemePalette = (palette: ThemePalette) => {
    setThemePaletteState(palette);
    localStorage.setItem('basarnas_theme_palette', palette);
  };

  const toggleDarkMode = () => {
    setIsDarkMode((prev) => !prev);
  };

  const accentColors = PALETTE_CONFIGS[themePalette] || PALETTE_CONFIGS.orange;

  return (
    <ThemeContext.Provider
      value={{
        isDarkMode,
        toggleDarkMode,
        themePalette,
        setThemePalette,
        accentColors,
      }}
    >
      {children}
    </ThemeContext.Provider>
  );
};

export const useTheme = () => {
  const context = useContext(ThemeContext);
  if (!context) {
    throw new Error('useTheme must be used within a ThemeProvider');
  }
  return context;
};
