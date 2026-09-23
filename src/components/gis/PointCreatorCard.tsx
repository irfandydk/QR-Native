import React, { useRef, useState, useEffect } from 'react';
import {
  MapPin,
  Upload,
  Check,
  X,
  Target,
  Building2,
  Radio,
  Tent,
  Home,
  Ship,
  Sailboat,
  ShieldAlert,
  UserX,
  Plane,
  Flame,
  AlertTriangle,
  Move,
  Trash2,
  Sparkles,
} from 'lucide-react';
import { useTheme } from '../../context/ThemeContext';
import { PointIconType } from '../../utils/geoUtils';
import { DmsNumericEditor } from './DmsNumericEditor';

export interface PointPresetOption {
  type: PointIconType;
  label: string;
  color: string;
  icon: React.ComponentType<{ className?: string }>;
}

export const PRESET_POINT_ICONS: PointPresetOption[] = [
  { type: 'lkp', label: 'LKP Kejadian', color: '#dc2626', icon: Target },
  { type: 'kantor', label: 'Kantor SAR', color: '#1e3a8a', icon: Building2 },
  { type: 'pos_sar', label: 'Pos / Unit SAR', color: '#ea580c', icon: Radio },
  { type: 'posko_sar', label: 'Posko SAR (Taktis)', color: '#059669', icon: Tent },
  { type: 'rumah', label: 'Rumah / Shelter', color: '#16a34a', icon: Home },
  { type: 'kapal', label: 'Kapal SAR (KN)', color: '#0284c7', icon: Ship },
  { type: 'perahu', label: 'Perahu Nelayan', color: '#06b6d4', icon: Sailboat },
  { type: 'rib', label: 'RIB Basarnas', color: '#f97316', icon: ShieldAlert },
  { type: 'orang_hilang', label: 'Orang Hilang', color: '#ef4444', icon: UserX },
  { type: 'kecelakaan_kapal', label: 'Kecelakaan Kapal', color: '#b91c1c', icon: Flame },
  { type: 'kecelakaan_pesawat', label: 'Kecelakaan Udara', color: '#7c3aed', icon: Plane },
  { type: 'bencana', label: 'Bencana Alam', color: '#d97706', icon: AlertTriangle },
];

const ALPHABET_LIST = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('');

interface PointCreatorCardProps {
  name: string;
  setName: (name: string) => void;
  selectedIconType: PointIconType;
  setSelectedIconType: (type: PointIconType) => void;
  alphabetChar?: string;
  setAlphabetChar?: (char: string) => void;
  customIconUrl?: string;
  setCustomIconUrl: (url?: string) => void;
  lat: number;
  lng: number;
  onCoordsChange: (coords: { lat: number; lng: number }) => void;
  onSave: () => void;
  onCancel: () => void;
  isEditMode?: boolean;
}

export const PointCreatorCard: React.FC<PointCreatorCardProps> = ({
  name,
  setName,
  selectedIconType,
  setSelectedIconType,
  alphabetChar = 'A',
  setAlphabetChar,
  customIconUrl,
  setCustomIconUrl,
  lat,
  lng,
  onCoordsChange,
  onSave,
  onCancel,
  isEditMode = false,
}) => {
  const { isDarkMode } = useTheme();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [activeIconTab, setActiveIconTab] = useState<'sar' | 'alphabet' | 'saved'>('sar');
  const [savedCustomIcons, setSavedCustomIcons] = useState<string[]>([]);
  const [isUploadingIcon, setIsUploadingIcon] = useState<boolean>(false);

  // Load persistent uploaded icons from server database and localStorage cache
  useEffect(() => {
    let isMounted = true;
    try {
      const stored = localStorage.getItem('basarnas_saved_custom_icons');
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed)) {
          setSavedCustomIcons(parsed);
        }
      }
    } catch {}

    const fetchServerIcons = async () => {
      try {
        const res = await fetch('/api/gis/custom-icons');
        if (res.ok) {
          const json = await res.json();
          if (json.success && Array.isArray(json.data) && isMounted) {
            const urls = json.data.map((item: any) => item.url || item);
            setSavedCustomIcons((prev) => {
              const merged = Array.from(new Set([...urls, ...prev]));
              try {
                localStorage.setItem('basarnas_saved_custom_icons', JSON.stringify(merged));
              } catch {}
              return merged;
            });
          }
        }
      } catch (err) {
        // Fallback to local
      }
    };

    fetchServerIcons();

    return () => {
      isMounted = false;
    };
  }, []);

  // Sync active tab if initial selection is alphabet or custom
  useEffect(() => {
    if (selectedIconType === 'alphabet') {
      setActiveIconTab('alphabet');
    } else if (customIconUrl) {
      setActiveIconTab('saved');
    }
  }, []);

  const handleFileUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async (event) => {
      const result = event.target?.result as string;
      if (!result) return;

      setIsUploadingIcon(true);
      let targetUrl = result;

      // Persist to server static storage via AJAX
      try {
        const upRes = await fetch('/api/gis/custom-icons/upload', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            imageBase64: result,
            extension: file.name.split('.').pop() || 'png',
            name: file.name.replace(/\.[^/.]+$/, ''),
          }),
        });
        if (upRes.ok) {
          const upJson = await upRes.json();
          if (upJson.success && upJson.url) {
            targetUrl = upJson.url;
          }
        }
      } catch (err) {
        console.warn('Icon uploaded locally, server upload failed:', err);
      } finally {
        setIsUploadingIcon(false);
      }

      setCustomIconUrl(targetUrl);
      setSelectedIconType('custom_upload');
      setActiveIconTab('saved');

      // Automatically persist to savedCustomIcons library
      setSavedCustomIcons((prev) => {
        const updated = [targetUrl, ...prev.filter((u) => u !== targetUrl)];
        try {
          localStorage.setItem('basarnas_saved_custom_icons', JSON.stringify(updated));
        } catch {}
        return updated;
      });
    };
    reader.readAsDataURL(file);
  };

  const handleDeleteSavedIcon = async (iconToDelete: string, e: React.MouseEvent) => {
    e.stopPropagation();
    const updated = savedCustomIcons.filter((u) => u !== iconToDelete);
    setSavedCustomIcons(updated);
    try {
      localStorage.setItem('basarnas_saved_custom_icons', JSON.stringify(updated));
    } catch {}

    // Also call server delete if it's an uploaded asset path
    try {
      const filename = iconToDelete.split('/').pop();
      if (filename) {
        await fetch(`/api/gis/custom-icons/${filename}`, { method: 'DELETE' });
      }
    } catch {}

    if (customIconUrl === iconToDelete) {
      setCustomIconUrl(undefined);
      setSelectedIconType('waypoint');
      setActiveIconTab('sar');
    }
  };

  return (
    <div
      className={`p-4 rounded-2xl border ${
        isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
      } shadow-lg space-y-3.5 animate-in fade-in`}
    >
      <div className="flex items-center justify-between pb-2 border-b border-inherit">
        <span className="text-xs font-black uppercase tracking-wider text-orange-500 flex items-center gap-1.5">
          <MapPin className="w-4 h-4" />
          {isEditMode ? 'Edit Titik Plotting' : 'Tambah Titik Baru di Peta'}
        </span>
        <button
          type="button"
          onClick={onCancel}
          className="p-1 rounded-lg hover:bg-slate-500/10 text-slate-400 cursor-pointer"
          title="Batal"
        >
          <X className="w-4 h-4" />
        </button>
      </div>

      {/* Real-time Draggable Status Notice */}
      <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-700 dark:text-amber-300 flex items-start gap-2">
        <Move className="w-4 h-4 text-amber-600 shrink-0 mt-0.5 animate-pulse" />
        <div>
          <strong>Marker aktif tampil langsung di peta beserta Nama Titik!</strong>
          <p className="mt-0.5 text-[10px] opacity-90">
            Geser (drag) pin langsung di peta atau sesuaikan koordinat di bawah untuk memindahkan posisi titik.
          </p>
        </div>
      </div>

      {/* Point Name Input */}
      <div>
        <label className="block text-[11px] font-bold text-slate-500 mb-1">
          Nama Titik / Objek (Akan muncul di atas pin peta):
        </label>
        <input
          type="text"
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="e.g. LKP KM Sumber Bahagia / Pos SAR Baubau"
          className={`w-full px-3 py-2 rounded-xl border text-xs font-semibold ${
            isDarkMode ? 'bg-slate-950 border-slate-700 text-white' : 'bg-slate-50 border-slate-300'
          }`}
        />
      </div>

      {/* Icon Selector Tabs */}
      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <label className="block text-[11px] font-bold text-slate-500">
            Pilihan Icon Titik:
          </label>
          <button
            type="button"
            onClick={() => fileInputRef.current?.click()}
            className="text-[10px] text-orange-500 hover:text-orange-400 font-bold flex items-center gap-1 cursor-pointer"
          >
            <Upload className="w-3 h-3" />
            + Upload Icon Baru
          </button>
        </div>

        {/* Tab Buttons */}
        <div className="grid grid-cols-3 gap-1 p-1 rounded-xl bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-[10px] font-bold">
          <button
            type="button"
            onClick={() => setActiveIconTab('sar')}
            className={`py-1.5 px-2 rounded-lg transition-all cursor-pointer ${
              activeIconTab === 'sar'
                ? 'bg-white dark:bg-slate-800 text-orange-600 dark:text-orange-400 shadow-xs'
                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            }`}
          >
            Icon SAR ({PRESET_POINT_ICONS.length})
          </button>
          <button
            type="button"
            onClick={() => setActiveIconTab('alphabet')}
            className={`py-1.5 px-2 rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1 ${
              activeIconTab === 'alphabet'
                ? 'bg-white dark:bg-slate-800 text-orange-600 dark:text-orange-400 shadow-xs'
                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            }`}
          >
            Huruf A - Z
          </button>
          <button
            type="button"
            onClick={() => setActiveIconTab('saved')}
            className={`py-1.5 px-2 rounded-lg transition-all cursor-pointer flex items-center justify-center gap-1 ${
              activeIconTab === 'saved'
                ? 'bg-white dark:bg-slate-800 text-orange-600 dark:text-orange-400 shadow-xs'
                : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'
            }`}
          >
            Tersimpan ({savedCustomIcons.length})
          </button>
        </div>

        {/* 1. SAR PRESET ICONS */}
        {activeIconTab === 'sar' && (
          <div className="grid grid-cols-4 gap-1.5 max-h-36 overflow-y-auto scrollbar-thin p-1 border border-slate-200 dark:border-slate-800 rounded-xl">
            {PRESET_POINT_ICONS.map((preset) => {
              const IconComp = preset.icon;
              const isSelected = selectedIconType === preset.type && !customIconUrl;
              return (
                <button
                  key={preset.type}
                  type="button"
                  onClick={() => {
                    setSelectedIconType(preset.type);
                    setCustomIconUrl(undefined);
                  }}
                  className={`p-1.5 rounded-xl border text-center flex flex-col items-center gap-1 transition-all cursor-pointer ${
                    isSelected
                      ? 'border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 font-bold ring-1 ring-orange-500'
                      : isDarkMode
                      ? 'border-slate-800 bg-slate-950 hover:bg-slate-800 text-slate-400'
                      : 'border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-600'
                  }`}
                >
                  <div
                    className="w-6 h-6 rounded-lg flex items-center justify-center text-white"
                    style={{ backgroundColor: preset.color }}
                  >
                    <IconComp className="w-3.5 h-3.5" />
                  </div>
                  <span className="text-[9px] line-clamp-1 leading-tight">{preset.label}</span>
                </button>
              );
            })}
          </div>
        )}

        {/* 2. ALPHABET A - Z ICONS */}
        {activeIconTab === 'alphabet' && (
          <div className="p-2 border border-slate-200 dark:border-slate-800 rounded-xl space-y-2">
            <div className="flex items-center justify-between text-[10px] text-slate-400">
              <span>Pilih huruf alfabet untuk dijadikan penanda titik:</span>
              <span className="font-bold text-orange-500">Terpilih: {alphabetChar}</span>
            </div>
            <div className="grid grid-cols-6 sm:grid-cols-9 gap-1.5 max-h-36 overflow-y-auto scrollbar-thin p-1">
              {ALPHABET_LIST.map((char) => {
                const isSelected = selectedIconType === 'alphabet' && alphabetChar === char && !customIconUrl;
                return (
                  <button
                    key={char}
                    type="button"
                    onClick={() => {
                      setSelectedIconType('alphabet');
                      if (setAlphabetChar) setAlphabetChar(char);
                      setCustomIconUrl(undefined);
                    }}
                    className={`h-8 rounded-lg font-black text-xs transition-all flex items-center justify-center cursor-pointer ${
                      isSelected
                        ? 'bg-orange-600 text-white shadow-md ring-2 ring-orange-500/40 scale-105'
                        : isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700'
                        : 'bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-300'
                    }`}
                  >
                    {char}
                  </button>
                );
              })}
            </div>
          </div>
        )}

        {/* 3. SAVED CUSTOM UPLOADED ICONS */}
        {activeIconTab === 'saved' && (
          <div className="p-2 border border-slate-200 dark:border-slate-800 rounded-xl space-y-2">
            {savedCustomIcons.length === 0 ? (
              <div className="p-4 text-center text-xs text-slate-400 space-y-2">
                <Sparkles className="w-5 h-5 mx-auto text-amber-500 opacity-80" />
                <p>Belum ada icon yang tersimpan.</p>
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  className="px-3 py-1.5 rounded-lg bg-orange-600 text-white font-bold text-[11px] inline-flex items-center gap-1.5 cursor-pointer shadow-sm"
                >
                  <Upload className="w-3.5 h-3.5" />
                  Upload Icon Pertama Sekarang
                </button>
              </div>
            ) : (
              <div className="grid grid-cols-4 sm:grid-cols-5 gap-2 max-h-36 overflow-y-auto scrollbar-thin p-1">
                {savedCustomIcons.map((url, idx) => {
                  const isSelected = customIconUrl === url;
                  return (
                    <div
                      key={idx}
                      onClick={() => {
                        setCustomIconUrl(url);
                        setSelectedIconType('custom_upload');
                      }}
                      className={`relative group aspect-square rounded-xl p-1.5 border flex items-center justify-center cursor-pointer transition-all ${
                        isSelected
                          ? 'border-emerald-500 bg-emerald-500/10 ring-2 ring-emerald-500'
                          : isDarkMode
                          ? 'border-slate-800 bg-slate-950 hover:bg-slate-800'
                          : 'border-slate-200 bg-slate-50 hover:bg-slate-100'
                      }`}
                    >
                      <img
                        src={url}
                        alt={`Saved Icon ${idx + 1}`}
                        className="w-full h-full object-contain rounded-md"
                        referrerPolicy="no-referrer"
                        onError={(e) => {
                          (e.currentTarget as HTMLImageElement).src =
                            'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23ea580c"><circle cx="12" cy="12" r="10" fill="%23fff" stroke="%23ea580c" stroke-width="2"/><circle cx="12" cy="12" r="5" fill="%23ea580c"/></svg>';
                        }}
                      />
                      {isSelected && (
                        <div className="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 text-white flex items-center justify-center">
                          <Check className="w-2.5 h-2.5 stroke-[3]" />
                        </div>
                      )}
                      <button
                        type="button"
                        onClick={(e) => handleDeleteSavedIcon(url, e)}
                        className="absolute top-1 right-1 p-1 bg-red-600 text-white rounded-md opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-700 cursor-pointer shadow-sm"
                        title="Hapus dari icon tersimpan"
                      >
                        <Trash2 className="w-2.5 h-2.5" />
                      </button>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        )}

        {/* Hidden File Input */}
        <input
          type="file"
          ref={fileInputRef}
          onChange={handleFileUpload}
          accept="image/*"
          className="hidden"
        />
      </div>

      {/* Pure Numeric DMS Editor */}
      <DmsNumericEditor
        lat={lat}
        lng={lng}
        onChange={onCoordsChange}
        title="KOORDINAT TITIK (DMS NUMERIK)"
      />

      {/* Save / Cancel buttons */}
      <div className="flex items-center gap-2 pt-1 border-t border-inherit">
        <button
          type="button"
          onClick={onSave}
          disabled={!name.trim()}
          className="flex-1 py-2.5 px-4 rounded-xl bg-orange-600 hover:bg-orange-500 disabled:opacity-40 text-white font-bold text-xs shadow-md shadow-orange-600/20 flex items-center justify-center gap-1.5 cursor-pointer transition-all"
        >
          <Check className="w-4 h-4" />
          <span>{isEditMode ? 'Simpan Perubahan Titik' : 'Simpan Titik ke Peta'}</span>
        </button>
        <button
          type="button"
          onClick={onCancel}
          className={`py-2.5 px-3 rounded-xl border text-xs font-semibold cursor-pointer ${
            isDarkMode ? 'border-slate-800 hover:bg-slate-800' : 'border-slate-200 hover:bg-slate-100'
          }`}
        >
          Batal
        </button>
      </div>
    </div>
  );
};

