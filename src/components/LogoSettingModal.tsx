import React, { useState, useRef } from 'react';
import { X, Upload, Check, RefreshCw, Image as ImageIcon, Shield } from 'lucide-react';
import { BasarnasLogo } from './BasarnasLogo';
import { showSuccessAlert, showWarningAlert } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface LogoSettingModalProps {
  isOpen: boolean;
  onClose: () => void;
  currentLogoUrl?: string;
  onLogoUpdated: (newLogoUrl: string) => void;
}

export const LogoSettingModal: React.FC<LogoSettingModalProps> = ({
  isOpen,
  onClose,
  currentLogoUrl = '',
  onLogoUpdated,
}) => {
  const { isDarkMode, themePalette, accentColors } = useTheme();
  const [logoMode, setLogoMode] = useState<'upload' | 'url' | 'default'>('upload');
  const [customUrl, setCustomUrl] = useState<string>(currentLogoUrl || '');
  const [previewUrl, setPreviewUrl] = useState<string>(currentLogoUrl || '');
  const [saving, setSaving] = useState<boolean>(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  if (!isOpen) return null;

  // Handle file select (PNG/JPG)
  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      showWarningAlert('Format Tidak Sesuai', 'Mohon pilih file gambar yang valid (PNG, JPG, SVG, WEBP)');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      showWarningAlert('Ukuran Terlalu Besar', 'Ukuran file logo maksimal adalah 5MB.');
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      const result = reader.result as string;
      setPreviewUrl(result);
      setCustomUrl(result);
      setLogoMode('upload');
    };
    reader.readAsDataURL(file);
  };

  // Handle Save
  const handleSave = async () => {
    try {
      setSaving(true);
      const finalUrl = logoMode === 'default' ? '' : previewUrl;

      // Update to backend
      const res = await fetch('/api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ logo_url: finalUrl }),
      });

      if (res.ok) {
        if (typeof window !== 'undefined') {
          if (finalUrl) {
            localStorage.setItem('sar_custom_logo_url', finalUrl);
          } else {
            localStorage.removeItem('sar_custom_logo_url');
          }
        }
        onLogoUpdated(finalUrl);
        await showSuccessAlert('Logo Berhasil Disimpan', 'Logo Kop Surat Resmi Basarnas berhasil diperbarui.');
        onClose();
      } else {
        showWarningAlert('Pemberitahuan', 'Gagal menyimpan pengaturan logo di server, tersimpan di perangkat lokal.');
      }
    } catch (err) {
      console.error('Failed to save logo setting:', err);
      // Fallback local save
      if (typeof window !== 'undefined') {
        const finalUrl = logoMode === 'default' ? '' : previewUrl;
        if (finalUrl) {
          localStorage.setItem('sar_custom_logo_url', finalUrl);
        } else {
          localStorage.removeItem('sar_custom_logo_url');
        }
        onLogoUpdated(finalUrl);
        await showSuccessAlert('Logo Berhasil Disimpan', 'Logo Kop Surat Resmi Basarnas berhasil diperbarui di perangkat lokal.');
        onClose();
      }
    } finally {
      setSaving(false);
    }
  };

  // Reset to default vector
  const handleResetToDefault = () => {
    setLogoMode('default');
    setPreviewUrl('');
    setCustomUrl('');
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/75 backdrop-blur-sm p-4 overflow-y-auto">
      <div className={`border rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-150 transition-colors ${
        isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
      }`}>
        {/* Header */}
        <div className={`flex items-center justify-between px-5 py-4 border-b transition-colors ${
          isDarkMode ? 'border-slate-800 bg-slate-850' : 'border-slate-200 bg-slate-50'
        }`}>
          <div className="flex items-center gap-2.5">
            <div className={`w-8 h-8 rounded-lg border flex items-center justify-center ${accentColors.badgeBg} ${accentColors.badgeText} border-current/30`}>
              <ImageIcon className="w-4 h-4" />
            </div>
            <div>
              <h2 className={`text-sm font-bold ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>Pengaturan Logo Resmi Kop Surat</h2>
              <p className={`text-[11px] ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Pilih atau unggah logo resmi Basarnas agar logo tidak acak
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className={`p-1.5 rounded-lg transition-colors ${
              isDarkMode ? 'text-slate-400 hover:text-white hover:bg-slate-800' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        {/* Body */}
        <div className="p-5 space-y-5 text-xs">
          {/* Live Preview Box */}
          <div className={`p-4 rounded-xl border flex flex-col items-center justify-center text-center transition-colors ${
            isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-50 border-slate-200'
          }`}>
            <span className={`text-[10px] font-semibold uppercase mb-2.5 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
              Pratinjau Logo Pada Kop Surat
            </span>
            <div className="w-20 h-20 bg-white p-2 rounded-xl shadow border border-slate-300 flex items-center justify-center">
              {logoMode === 'default' || !previewUrl ? (
                <BasarnasLogo size={64} />
              ) : (
                <img
                  src={previewUrl}
                  alt="Pratinjau Logo"
                  className="w-full h-full object-contain"
                />
              )}
            </div>
            <span className={`text-[11px] font-medium mt-2 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
              {logoMode === 'default' || !previewUrl
                ? 'Lambang Standar SAR Nasional (Vektor Presisi)'
                : 'Logo Kustom Resmi Terpasang'}
            </span>
          </div>

          {/* Mode Selector Tabs */}
          <div className={`grid grid-cols-3 gap-2 p-1 rounded-xl border text-center font-semibold text-[11px] transition-colors ${
            isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-100 border-slate-200'
          }`}>
            <button
              type="button"
              onClick={() => setLogoMode('upload')}
              className={`py-1.5 rounded-lg transition-all ${
                logoMode === 'upload'
                  ? `bg-gradient-to-r ${accentColors.gradient} text-white shadow`
                  : isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-950'
              }`}
            >
              Unggah File
            </button>
            <button
              type="button"
              onClick={() => setLogoMode('url')}
              className={`py-1.5 rounded-lg transition-all ${
                logoMode === 'url'
                  ? `bg-gradient-to-r ${accentColors.gradient} text-white shadow`
                  : isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-950'
              }`}
            >
              Link / URL Gambar
            </button>
            <button
              type="button"
              onClick={handleResetToDefault}
              className={`py-1.5 rounded-lg transition-all ${
                logoMode === 'default'
                  ? `bg-gradient-to-r ${accentColors.gradient} text-white shadow`
                  : isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-950'
              }`}
            >
              Vektor Default
            </button>
          </div>

          {/* Tab 1: Upload File */}
          {logoMode === 'upload' && (
            <div className="space-y-3">
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/svg+xml,image/webp"
                onChange={handleFileChange}
                className="hidden"
              />
              <div
                onClick={() => fileInputRef.current?.click()}
                className={`border-2 border-dashed rounded-xl p-6 text-center cursor-pointer transition-colors group ${
                  isDarkMode
                    ? 'border-slate-700 hover:border-orange-500/60 bg-slate-850 hover:bg-slate-800/80'
                    : 'border-slate-300 hover:border-orange-500/60 bg-slate-50 hover:bg-slate-100'
                }`}
              >
                <Upload className={`w-6 h-6 mx-auto mb-2 transition-colors ${accentColors.text}`} />
                <p className={`font-semibold ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
                  Klik untuk Memilih File Logo Resmi
                </p>
                <p className={`text-[10px] mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                  Mendukung PNG (disarankan transparansi), JPG, SVG, WEBP (Maks. 5MB)
                </p>
              </div>
            </div>
          )}

          {/* Tab 2: URL Input */}
          {logoMode === 'url' && (
            <div className="space-y-2">
              <label className={`block text-xs font-semibold ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                URL Gambar Logo
              </label>
              <input
                type="url"
                value={customUrl}
                onChange={(e) => {
                  setCustomUrl(e.target.value);
                  setPreviewUrl(e.target.value);
                }}
                placeholder="https://contoh.com/logo-basarnas-kendari.png"
                className={`w-full text-xs font-mono rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-200 border-slate-700'
                    : 'bg-white text-slate-900 border-slate-300 shadow-sm'
                }`}
              />
              <p className={`text-[10px] ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Tempelkan tautan langsung ke file gambar logo resmi instansi Anda.
              </p>
            </div>
          )}

          {/* Tab 3: Default Explanation */}
          {logoMode === 'default' && (
            <div className={`p-3.5 rounded-xl border space-y-1 transition-colors ${
              isDarkMode ? 'bg-slate-850 border-slate-800' : 'bg-slate-50 border-slate-200'
            }`}>
              <div className={`flex items-center gap-2 font-semibold ${isDarkMode ? 'text-slate-200' : 'text-slate-900'}`}>
                <Shield className={`w-4 h-4 ${accentColors.text}`} />
                <span>Format Lambang Standar Vektor Basarnas</span>
              </div>
              <p className={`text-[11px] leading-relaxed ${isDarkMode ? 'text-slate-400' : 'text-slate-600'}`}>
                Menggunakan gambar vektor lingkaran SAR Nasional resmi dengan jangkar dan sayap emas. Resolusi tajam tidak pecah saat dicetak.
              </p>
            </div>
          )}
        </div>

        {/* Footer Actions */}
        <div className={`px-5 py-3.5 border-t flex items-center justify-between transition-colors ${
          isDarkMode ? 'border-slate-800 bg-slate-850' : 'border-slate-200 bg-slate-50'
        }`}>
          <button
            type="button"
            onClick={handleResetToDefault}
            className={`text-xs inline-flex items-center gap-1.5 transition-colors ${
              isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <RefreshCw className="w-3.5 h-3.5" />
            <span>Pulihkan Default</span>
          </button>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={onClose}
              className={`px-3 py-1.5 text-xs rounded-lg transition-colors border ${
                isDarkMode
                  ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800 border-slate-700'
                  : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 border-slate-300'
              }`}
            >
              Batal
            </button>
            <button
              type="button"
              onClick={handleSave}
              disabled={saving}
              className={`px-4 py-1.5 text-xs font-semibold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 rounded-lg shadow transition-all hover:scale-[1.02] inline-flex items-center gap-1.5 disabled:opacity-50`}
            >
              <Check className="w-3.5 h-3.5" />
              <span>{saving ? 'Menyimpan...' : 'Simpan & Terapkan'}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
