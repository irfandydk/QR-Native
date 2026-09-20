import React, { useState, useMemo } from 'react';
import {
  Search,
  Filter,
  PlusCircle,
  Compass,
  FileText,
  Copy,
  Check,
  Eye,
  Trash2,
  Clock,
  Users,
  ChevronRight,
  Database,
  Radio,
  ExternalLink,
} from 'lucide-react';
import { Operasi } from '../types';
import {
  formatTanggalIndo,
  formatJamWITA,
  hitungHariKe,
  hitungStatistikKorban,
} from '../utils/templates';
import { showConfirmDialog, showToast } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface DashboardListProps {
  operasiList: Operasi[];
  onSelectOperasi: (id: number) => void;
  onNewOperasi: () => void;
  onEditOperasi: (op: Operasi) => void;
  onDeleteOperasi: (id: number) => Promise<void>;
  onViewTextWA: (title: string, subtitle: string, text: string) => void;
  onOpenSqlModal: () => void;
}

export const DashboardList: React.FC<DashboardListProps> = ({
  operasiList,
  onSelectOperasi,
  onNewOperasi,
  onEditOperasi,
  onDeleteOperasi,
  onViewTextWA,
  onOpenSqlModal,
}) => {
  const { isDarkMode, themePalette, accentColors } = useTheme();
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<'ALL' | 'OPEN' | 'CLOSED'>('ALL');
  const [copiedId, setCopiedId] = useState<number | null>(null);

  // Filtered operations
  const filteredList = useMemo(() => {
    return operasiList.filter((op) => {
      const matchSearch =
        op.nama_operasi.toLowerCase().includes(searchQuery.toLowerCase()) ||
        op.lokasi_kejadian.toLowerCase().includes(searchQuery.toLowerCase()) ||
        (op.koordinat && op.koordinat.toLowerCase().includes(searchQuery.toLowerCase()));

      const matchStatus =
        statusFilter === 'ALL' || op.status === statusFilter;

      return matchSearch && matchStatus;
    });
  }, [operasiList, searchQuery, statusFilter]);

  // Overall Statistics
  const totalOpen = operasiList.filter((o) => o.status === 'OPEN').length;
  const totalClosed = operasiList.filter((o) => o.status === 'CLOSED').length;
  const totalKorban = operasiList.reduce(
    (acc, op) => acc + (op.korban?.length || op.jumlah_pob || 0),
    0
  );
  const totalSelamat = operasiList.reduce((acc, op) => {
    const s = (op.korban || []).filter((k) => k.status_korban === 'S').length;
    return acc + s;
  }, 0);

  const handleCopyWA = async (e: React.MouseEvent, op: Operasi) => {
    e.stopPropagation();
    try {
      await navigator.clipboard.writeText(op.full_text_wa);
      setCopiedId(op.id_operasi);
      showToast('Naskah Laporan Awal berhasil disalin!', 'success');
      setTimeout(() => setCopiedId(null), 2000);
    } catch (err) {
      console.error(err);
    }
  };

  const handleDelete = async (e: React.MouseEvent, op: Operasi) => {
    e.stopPropagation();
    const confirmed = await showConfirmDialog(
      `Hapus Operasi "${op.nama_operasi}"?`,
      `Apakah Anda yakin ingin menghapus data operasi SAR ini beserta seluruh arsip Renops dan Lapbang-nya? Tindakan ini tidak dapat dibatalkan.`
    );
    if (confirmed) {
      await onDeleteOperasi(op.id_operasi);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      {/* Metrics Row */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        <div className={`rounded-2xl p-4 sm:p-5 border transition-all duration-200 relative overflow-hidden ${
          isDarkMode ? 'bg-slate-900 border-slate-800 shadow-lg' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between">
            <span className={`text-xs font-semibold uppercase tracking-wider ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
              Operasi Aktif (OPEN)
            </span>
            <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
          </div>
          <div className="text-2xl sm:text-3xl font-extrabold text-emerald-500 dark:text-emerald-400 mt-2 font-mono">
            {totalOpen}
          </div>
          <p className={`text-[11px] mt-1 ${isDarkMode ? 'text-slate-500' : 'text-slate-400'}`}>Dalam proses penanganan SAR</p>
        </div>

        <div className={`rounded-2xl p-4 sm:p-5 border transition-all duration-200 ${
          isDarkMode ? 'bg-slate-900 border-slate-800 shadow-lg' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between">
            <span className={`text-xs font-semibold uppercase tracking-wider ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
              Operasi Selesai (CLOSED)
            </span>
            <Check className={`w-4 h-4 ${isDarkMode ? 'text-slate-500' : 'text-slate-400'}`} />
          </div>
          <div className={`text-2xl sm:text-3xl font-extrabold mt-2 font-mono ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
            {totalClosed}
          </div>
          <p className={`text-[11px] mt-1 ${isDarkMode ? 'text-slate-500' : 'text-slate-400'}`}>Debriefing & penutupan selesai</p>
        </div>

        <div className={`rounded-2xl p-4 sm:p-5 border transition-all duration-200 ${
          isDarkMode ? 'bg-slate-900 border-slate-800 shadow-lg' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between">
            <span className={`text-xs font-semibold uppercase tracking-wider ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
              Korban Selamat (S)
            </span>
            <Users className="w-4 h-4 text-emerald-500 dark:text-emerald-400" />
          </div>
          <div className="text-2xl sm:text-3xl font-extrabold text-emerald-500 dark:text-emerald-400 mt-2 font-mono">
            {totalSelamat}
          </div>
          <p className={`text-[11px] mt-1 ${isDarkMode ? 'text-slate-500' : 'text-slate-400'}`}>Evakuasi berhasil dalam keadaan selamat</p>
        </div>

        <div className={`rounded-2xl p-4 sm:p-5 border transition-all duration-200 ${
          isDarkMode ? 'bg-slate-900 border-slate-800 shadow-lg' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between">
            <span className={`text-xs font-semibold uppercase tracking-wider ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
              Total POB Tercatat
            </span>
            <Users className={`w-4 h-4 ${accentColors.text}`} />
          </div>
          <div className={`text-2xl sm:text-3xl font-extrabold mt-2 font-mono ${accentColors.text}`}>
            {totalKorban}
          </div>
          <p className={`text-[11px] mt-1 ${isDarkMode ? 'text-slate-500' : 'text-slate-400'}`}>Person on Board seluruh operasi</p>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className={`flex flex-col sm:flex-row items-center justify-between gap-3 rounded-2xl p-3 sm:p-4 border transition-all duration-200 ${
        isDarkMode ? 'bg-slate-900 border-slate-800 shadow-lg' : 'bg-white border-slate-200 shadow-sm'
      }`}>
        {/* Search */}
        <div className="relative w-full sm:w-80">
          <Search className={`w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 ${isDarkMode ? 'text-slate-400' : 'text-slate-400'}`} />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Cari operasi, perairan, koordinat..."
            className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border transition-colors outline-none focus:ring-2 ${accentColors.ring} ${
              isDarkMode
                ? 'bg-slate-950 border-slate-700 text-white placeholder:text-slate-500 focus:border-transparent'
                : 'bg-slate-50 border-slate-300 text-slate-900 placeholder:text-slate-400 focus:border-transparent'
            }`}
          />
        </div>

        {/* Status Filters & Actions */}
        <div className="flex flex-wrap items-center justify-end gap-2 w-full sm:w-auto">
          <div className={`flex items-center rounded-xl p-1 text-xs border ${
            isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-100 border-slate-200'
          }`}>
            {(['ALL', 'OPEN', 'CLOSED'] as const).map((st) => {
              const isActive = statusFilter === st;
              return (
                <button
                  key={st}
                  onClick={() => setStatusFilter(st)}
                  className={`px-3 py-1 rounded-lg font-semibold transition-all ${
                    isActive
                      ? `bg-gradient-to-r ${accentColors.gradient} text-white shadow-sm`
                      : isDarkMode
                        ? 'text-slate-400 hover:text-white'
                        : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  {st === 'ALL' ? 'Semua' : st}
                </button>
              );
            })}
          </div>

          <button
            id="btn-sql-export"
            onClick={onOpenSqlModal}
            className={`inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold border shadow-sm transition-all hover:scale-[1.02] active:scale-[0.98] ${
              isDarkMode
                ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                : 'bg-slate-100 hover:bg-slate-200 text-slate-800 border-slate-300'
            }`}
            title="Export Dump Database MySQL (db_sar_qr.sql)"
          >
            <Database className="w-3.5 h-3.5 text-blue-500" />
            <span>SQL Export</span>
          </button>

          <button
            id="btn-tambah-laporan-awal"
            onClick={onNewOperasi}
            className={`inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/25 border border-orange-500/30 transition-all hover:scale-[1.02] active:scale-[0.98]`}
          >
            <PlusCircle className="w-4 h-4" />
            <span>Tambah Laporan Awal</span>
          </button>
        </div>
      </div>

      {/* Operations List */}
      <div className="space-y-3">
        {filteredList.length === 0 ? (
          <div className={`p-12 text-center rounded-2xl border space-y-4 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <Radio className={`w-12 h-12 mx-auto ${isDarkMode ? 'text-slate-600' : 'text-slate-300'}`} />
            <div>
              <h3 className={`text-sm font-bold ${isDarkMode ? 'text-slate-300' : 'text-slate-800'}`}>Tidak ada data operasi ditemukan</h3>
              <p className={`text-xs mt-1 ${isDarkMode ? 'text-slate-500' : 'text-slate-500'}`}>
                {searchQuery
                  ? 'Coba ubah kata kunci pencarian Anda'
                  : 'Belum ada operasi SAR yang diinisiasi'}
              </p>
            </div>
            <button
              onClick={onNewOperasi}
              className={`px-4 py-2 bg-gradient-to-r ${accentColors.gradient} text-white rounded-xl text-xs font-semibold shadow-md`}
            >
              + Inisiasi Laporan Awal Sekarang
            </button>
          </div>
        ) : (
          filteredList.map((op) => {
            const isOpen = op.status === 'OPEN';
            const stats = hitungStatistikKorban(op.korban);
            const hariKe = hitungHariKe(op.waktu_berangkat || op.waktu_lapor);

            return (
              <div
                key={op.id_operasi}
                onClick={() => onSelectOperasi(op.id_operasi)}
                className={`group relative rounded-2xl p-4 sm:p-5 border transition-all cursor-pointer ${
                  isDarkMode
                    ? 'bg-slate-900 hover:bg-slate-850 border-slate-800 hover:border-slate-700 shadow-lg'
                    : 'bg-white hover:bg-slate-50/90 border-slate-200 hover:border-slate-300 shadow-sm hover:shadow-md'
                }`}
              >
                <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                  {/* Left Column: Title & Meta */}
                  <div className="space-y-2 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                      <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase tracking-wider border ${
                        isOpen
                          ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                          : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-700'
                      }`}>
                        {isOpen && <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>}
                        {op.status}
                      </span>

                      <span className={`px-2 py-0.5 rounded-md text-[11px] font-mono font-bold ${accentColors.badgeBg} ${accentColors.badgeText} border border-current/20`}>
                        {isOpen ? hariKe : 'SELESAI'}
                      </span>

                      <span className="text-[11px] text-slate-500 dark:text-slate-400">
                        ID: #{op.id_operasi}
                      </span>
                    </div>

                    <h3 className={`text-base sm:text-lg font-bold transition-colors ${
                      isDarkMode ? 'text-white' : 'text-slate-900'
                    }`}>
                      {op.nama_operasi}
                    </h3>

                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                      <div className="flex items-center gap-1">
                        <Compass className={`w-3.5 h-3.5 ${accentColors.text}`} />
                        <span className="text-slate-700 dark:text-slate-300 font-medium">{op.lokasi_kejadian}</span>
                      </div>
                      <span>•</span>
                      <div className="font-mono text-emerald-600 dark:text-emerald-400 font-medium">{op.koordinat}</div>
                      <span>•</span>
                      <div className="flex items-center gap-1">
                        <Clock className="w-3.5 h-3.5 opacity-60" />
                        <span>Berangkat: {formatTanggalIndo(op.waktu_berangkat)} ({formatJamWITA(op.waktu_berangkat)} WITA)</span>
                      </div>
                    </div>
                  </div>

                  {/* Right Column: POB Badges & Actions */}
                  <div className="flex flex-wrap items-center gap-3">
                    {/* Victim Stats */}
                    <div className={`flex items-center gap-2 p-1.5 px-3 rounded-xl text-xs border ${
                      isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-50 border-slate-200'
                    }`}>
                      <span className="text-slate-500 dark:text-slate-400 text-[11px] font-medium">POB:</span>
                      <span className="text-emerald-600 dark:text-emerald-400 font-bold">{stats.s} S</span>
                      <span className="text-slate-400 dark:text-slate-600">/</span>
                      <span className="text-red-500 dark:text-red-400 font-bold">{stats.md} MD</span>
                      <span className="text-slate-400 dark:text-slate-600">/</span>
                      <span className="text-amber-500 dark:text-amber-400 font-bold">{stats.dp} DP</span>
                    </div>

                    {/* Renops & Sitrep count pills */}
                    <div className="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
                      <span className={`px-2 py-1 rounded-lg border ${
                        isDarkMode ? 'bg-slate-800 border-slate-700 text-slate-300' : 'bg-slate-100 border-slate-200 text-slate-700'
                      }`}>
                        {op.renops?.length || 0} Renops
                      </span>
                      <span className={`px-2 py-1 rounded-lg border ${
                        isDarkMode ? 'bg-slate-800 border-slate-700 text-slate-300' : 'bg-slate-100 border-slate-200 text-slate-700'
                      }`}>
                        {op.laporan_harian?.length || 0} Lapbang
                      </span>
                    </div>

                    {/* Quick Button Group */}
                    <div className="flex items-center gap-1.5" onClick={(e) => e.stopPropagation()}>
                      <button
                        onClick={(e) => handleCopyWA(e, op)}
                        title="Salin Teks WA Laporan Awal"
                        className={`p-2 rounded-lg border transition-colors ${
                          isDarkMode
                            ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border-slate-700'
                            : 'bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 border-slate-200'
                        }`}
                      >
                        {copiedId === op.id_operasi ? (
                          <Check className="w-4 h-4 text-emerald-500" />
                        ) : (
                          <Copy className="w-4 h-4" />
                        )}
                      </button>

                      <button
                        onClick={() =>
                          onViewTextWA(
                            'LAPORAN AWAL OPS SAR (FILE AKTIF)',
                            op.nama_operasi,
                            op.full_text_wa
                          )
                        }
                        title="Lihat Format WA Resmi"
                        className={`p-2 rounded-lg border transition-colors ${
                          isDarkMode
                            ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white border-slate-700'
                            : 'bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-slate-900 border-slate-200'
                        }`}
                      >
                        <Eye className={`w-4 h-4 ${accentColors.text}`} />
                      </button>

                      <button
                        onClick={(e) => {
                          e.stopPropagation();
                          onEditOperasi(op);
                        }}
                        title="Edit Data Operasi"
                        className={`p-2 rounded-lg border transition-colors ${
                          isDarkMode
                            ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-amber-400 border-slate-700'
                            : 'bg-slate-100 hover:bg-slate-200 text-slate-600 hover:text-amber-600 border-slate-200'
                        }`}
                      >
                        <FileText className="w-4 h-4" />
                      </button>

                      <button
                        onClick={(e) => handleDelete(e, op)}
                        title="Hapus Operasi"
                        className={`p-2 rounded-lg border transition-colors ${
                          isDarkMode
                            ? 'bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-red-400 border-slate-700'
                            : 'bg-slate-100 hover:bg-slate-200 text-slate-500 hover:text-red-600 border-slate-200'
                        }`}
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>

                    {/* Enter Hub Arrow */}
                    <div className={`hidden sm:flex items-center ${accentColors.text} group-hover:translate-x-1 transition-transform`}>
                      <ChevronRight className="w-5 h-5" />
                    </div>
                  </div>
                </div>
              </div>
            );
          })
        )}
      </div>
    </div>
  );
};
