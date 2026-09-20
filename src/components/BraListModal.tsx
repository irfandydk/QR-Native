import React, { useState } from 'react';
import {
  Radio,
  FileText,
  Ship,
  Search,
  Plus,
  Trash2,
  Printer,
  X,
  ExternalLink,
  ShieldCheck,
  Calendar,
  Hash,
} from 'lucide-react';
import { BraSar, KategoriBra } from '../types';
import { showConfirmDialog } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface BraListModalProps {
  braList: BraSar[];
  isOpen: boolean;
  onClose: () => void;
  onSelectBra: (bra: BraSar) => void;
  onCreateNew: (kategori: KategoriBra) => void;
  onDeleteBra: (id: number) => void;
}

export const BraListModal: React.FC<BraListModalProps> = ({
  braList,
  isOpen,
  onClose,
  onSelectBra,
  onCreateNew,
  onDeleteBra,
}) => {
  const { isDarkMode, themePalette, accentColors } = useTheme();
  const [filterCat, setFilterCat] = useState<'ALL' | KategoriBra>('ALL');
  const [searchTerm, setSearchTerm] = useState<string>('');

  if (!isOpen) return null;

  const filtered = [...braList]
    .sort((a, b) => (b.nomor_urut || 0) - (a.nomor_urut || 0) || new Date(b.created_at).getTime() - new Date(a.created_at).getTime())
    .filter((b) => {
      if (filterCat !== 'ALL' && b.kategori !== filterCat) return false;
      if (searchTerm) {
        const q = searchTerm.toLowerCase();
        const matchNo = b.no_bra.toLowerCase().includes(q);
        const matchPerihal = (b.perihal || b.nama_operasi || '').toLowerCase().includes(q);
        const matchDari = b.dari.toLowerCase().includes(q);
        return matchNo || matchPerihal || matchDari;
      }
      return true;
    });

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-950/80 backdrop-blur-sm animate-in fade-in duration-200">
      <div className={`border rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl overflow-hidden transition-colors ${
        isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
      }`}>
        {/* Modal Header */}
        <div className={`p-4 sm:p-5 border-b flex items-center justify-between transition-colors ${
          isDarkMode ? 'border-slate-800' : 'border-slate-200'
        }`}>
          <div className="flex items-center gap-3">
            <div className={`w-10 h-10 rounded-xl border flex items-center justify-center ${accentColors.badgeBg} ${accentColors.badgeText} border-current/30`}>
              <Radio className="w-5 h-5" />
            </div>
            <div>
              <h2 className={`text-base font-bold tracking-wide ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                Arsip Berita Radio SAR (BRA SAR)
              </h2>
              <p className={`text-xs ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Daftar Naskah Telegram BRA Mapel & BRA SAR Operasi SAR (Urut Nomor Terakhir)
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <div className="hidden sm:flex items-center gap-1.5">
              <button
                onClick={() => onCreateNew('MAPEL')}
                className={`px-2.5 py-1.5 text-xs font-semibold rounded-lg text-white shadow transition-colors inline-flex items-center gap-1 bg-gradient-to-r ${accentColors.gradient} hover:opacity-90`}
              >
                <Plus className="w-3.5 h-3.5" />
                <span>+ BRA Mapel</span>
              </button>

              <button
                onClick={() => onCreateNew('SAR_UMUM')}
                className="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-amber-600 hover:bg-amber-500 text-white shadow transition-colors inline-flex items-center gap-1"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>+ BRA SAR Operasi SAR</span>
              </button>

              <button
                onClick={() => onCreateNew('PERGERAKAN_KAPAL')}
                className="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white shadow transition-colors inline-flex items-center gap-1"
              >
                <Ship className="w-3.5 h-3.5" />
                <span>+ BRA Pergerakan Kapal</span>
              </button>
            </div>

            <button
              onClick={onClose}
              className={`p-1.5 rounded-lg transition-colors ${
                isDarkMode ? 'text-slate-400 hover:text-white hover:bg-slate-800' : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100'
              }`}
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Filter & Search Bar */}
        <div className={`p-3.5 sm:p-4 border-b flex flex-col sm:flex-row items-center justify-between gap-3 transition-colors ${
          isDarkMode ? 'bg-slate-900/60 border-slate-800' : 'bg-slate-50 border-slate-200'
        }`}>
          {/* Category Tabs */}
          <div className={`flex items-center gap-1 p-1 rounded-xl border w-full sm:w-auto overflow-x-auto transition-colors ${
            isDarkMode ? 'bg-slate-800/80 border-slate-700/60' : 'bg-white border-slate-300'
          }`}>
            <button
              onClick={() => setFilterCat('ALL')}
              className={`px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors whitespace-nowrap ${
                filterCat === 'ALL'
                  ? `bg-gradient-to-r ${accentColors.gradient} text-white shadow`
                  : isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Semua ({braList.length})
            </button>
            <button
              onClick={() => setFilterCat('MAPEL')}
              className={`px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors whitespace-nowrap ${
                filterCat === 'MAPEL'
                  ? 'bg-orange-500 text-white shadow'
                  : isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              BRA Mapel ({braList.filter((b) => b.kategori === 'MAPEL').length})
            </button>
            <button
              onClick={() => setFilterCat('SAR_UMUM')}
              className={`px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors whitespace-nowrap ${
                filterCat === 'SAR_UMUM'
                  ? 'bg-amber-500 text-white shadow font-bold'
                  : isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              BRA SAR Operasi SAR ({braList.filter((b) => b.kategori === 'SAR_UMUM').length})
            </button>
            <button
              onClick={() => setFilterCat('PERGERAKAN_KAPAL')}
              className={`px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors whitespace-nowrap ${
                filterCat === 'PERGERAKAN_KAPAL'
                  ? 'bg-cyan-500 text-white shadow font-bold'
                  : isDarkMode ? 'text-slate-400 hover:text-slate-200' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              BRA Pergerakan Kapal ({braList.filter((b) => b.kategori === 'PERGERAKAN_KAPAL').length})
            </button>
          </div>

          {/* Search box */}
          <div className="relative w-full sm:w-64">
            <Search className={`w-4 h-4 absolute left-3 top-2.5 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`} />
            <input
              type="text"
              value={searchTerm}
              onChange={(e) => setSearchTerm(e.target.value)}
              placeholder="Cari nomor / perihal..."
              className={`w-full text-xs pl-9 pr-3 py-2 border rounded-xl focus:outline-none focus:ring-2 ${accentColors.ring} transition-colors ${
                isDarkMode
                  ? 'bg-slate-800 text-slate-200 border-slate-700 placeholder-slate-500'
                  : 'bg-white text-slate-900 border-slate-300 placeholder-slate-400 shadow-sm'
              }`}
            />
          </div>
        </div>

        {/* Document List */}
        <div className="flex-1 overflow-y-auto p-4 space-y-3">
          {filtered.length === 0 ? (
            <div className="text-center py-12 space-y-3">
              <FileText className={`w-12 h-12 mx-auto ${isDarkMode ? 'text-slate-600' : 'text-slate-400'}`} />
              <p className={`text-sm ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Belum ada dokumen BRA SAR yang sesuai filter.
              </p>
              <div className="flex flex-wrap items-center justify-center gap-2 pt-2">
                <button
                  onClick={() => onCreateNew('MAPEL')}
                  className="px-3 py-1.5 text-xs font-semibold text-white bg-orange-600 hover:bg-orange-500 rounded-lg shadow"
                >
                  + Buat BRA Mapel
                </button>
                <button
                  onClick={() => onCreateNew('SAR_UMUM')}
                  className="px-3 py-1.5 text-xs font-semibold text-white bg-amber-600 hover:bg-amber-500 rounded-lg shadow"
                >
                  + Buat BRA SAR Operasi SAR
                </button>
                <button
                  onClick={() => onCreateNew('PERGERAKAN_KAPAL')}
                  className="px-3 py-1.5 text-xs font-semibold text-white bg-cyan-600 hover:bg-cyan-500 rounded-lg shadow"
                >
                  + Buat BRA Pergerakan Kapal
                </button>
              </div>
            </div>
          ) : (
            filtered.map((bra) => (
              <div
                key={bra.id_bra}
                className={`border rounded-xl p-4 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-3 group ${
                  isDarkMode
                    ? 'bg-slate-850 border-slate-800 hover:border-slate-700'
                    : 'bg-slate-50 border-slate-200 hover:border-slate-300 shadow-sm'
                }`}
              >
                <div className="space-y-1.5 flex-1">
                  <div className="flex flex-wrap items-center gap-2">
                    <span
                      className={`text-[10px] font-bold uppercase px-2 py-0.5 rounded border ${
                        bra.kategori === 'MAPEL'
                          ? 'bg-orange-500/20 text-orange-600 dark:text-orange-400 border-orange-500/30'
                          : bra.kategori === 'PERGERAKAN_KAPAL'
                          ? 'bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border-cyan-500/30'
                          : 'bg-amber-500/20 text-amber-700 dark:text-amber-400 border-amber-500/30'
                      }`}
                    >
                      {bra.kategori === 'MAPEL'
                        ? '📡 BRA Mapel'
                        : bra.kategori === 'PERGERAKAN_KAPAL'
                        ? '🚢 BRA Pergerakan Kapal'
                        : '📋 BRA SAR Operasi SAR'}
                    </span>
                    <span className={`font-mono text-xs font-bold ${accentColors.text}`}>
                      {bra.no_bra}
                    </span>
                    <span className={`text-[11px] ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                      • TWU {bra.twu}
                    </span>
                  </div>

                  <h3 className={`text-xs sm:text-sm font-semibold line-clamp-1 ${isDarkMode ? 'text-slate-100' : 'text-slate-900'}`}>
                    {bra.perihal || bra.nama_operasi || 'Perihal Operasi SAR'}
                  </h3>

                  <div className={`flex flex-wrap items-center gap-3 text-[11px] ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                    <span className="flex items-center gap-1">
                      <Calendar className={`w-3 h-3 ${isDarkMode ? 'text-slate-500' : 'text-slate-400'}`} />
                      <span>{bra.tanggal_waktu}</span>
                    </span>
                    <span>•</span>
                    <span>TWB: {bra.twb}</span>
                    <span>•</span>
                    <span>SMC: {bra.nama_smc}</span>
                  </div>
                </div>

                {/* Action buttons */}
                <div className="flex items-center gap-2 shrink-0">
                  <button
                    onClick={() => onSelectBra(bra)}
                    className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 rounded-lg shadow transition-all hover:scale-[1.02]`}
                  >
                    <Printer className="w-3.5 h-3.5" />
                    <span>Lihat & Cetak</span>
                  </button>

                  <button
                    onClick={async () => {
                      const confirmed = await showConfirmDialog(
                        'Hapus Dokumen BRA SAR?',
                        `Apakah Anda yakin ingin menghapus dokumen BRA nomor "${bra.no_bra}"? Tindakan ini tidak dapat dibatalkan.`
                      );
                      if (confirmed) {
                        onDeleteBra(bra.id_bra);
                      }
                    }}
                    className={`p-1.5 rounded-lg transition-colors ${
                      isDarkMode
                        ? 'text-slate-400 hover:text-rose-400 hover:bg-rose-500/10'
                        : 'text-slate-500 hover:text-rose-600 hover:bg-rose-100'
                    }`}
                    title="Hapus Dokumen"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </div>
            ))
          )}
        </div>
      </div>
    </div>
  );
};
