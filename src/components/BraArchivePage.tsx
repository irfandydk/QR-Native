import React, { useState, useMemo } from 'react';
import {
  Radio,
  FileText,
  Anchor,
  Search,
  Plus,
  ArrowLeft,
  Calendar,
  Clock,
  Printer,
  Eye,
  Copy,
  Check,
  ShieldCheck,
  Filter,
  FileDown,
  LayoutGrid,
  List,
  Sparkles,
  ExternalLink,
  Trash2,
} from 'lucide-react';
import { BraSar, KategoriBra } from '../types';
import { useTheme } from '../context/ThemeContext';
import { printElement, downloadDocumentHtml } from '../utils/printUtils';
import { showToast, showConfirmDialog } from '../utils/alerts';

interface BraArchivePageProps {
  braList: BraSar[];
  onSelectBra: (bra: BraSar) => void;
  onCreateBra: (kategori: KategoriBra) => void;
  onDeleteBra?: (id: number) => Promise<void> | void;
  onBack: () => void;
}

export const BraArchivePage: React.FC<BraArchivePageProps> = ({
  braList,
  onSelectBra,
  onCreateBra,
  onDeleteBra,
  onBack,
}) => {
  const { isDarkMode, accentColors } = useTheme();
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedKategori, setSelectedKategori] = useState<'SEMUA' | KategoriBra>('SEMUA');
  const [viewMode, setViewMode] = useState<'grid' | 'table'>('grid');
  const [copiedId, setCopiedId] = useState<number | null>(null);

  // Filtered List - Urutan BRA SAR Terbaru
  const filteredBraList = useMemo(() => {
    // Urutan BRA SAR terbaru (id_bra descending)
    const sorted = [...braList].sort((a, b) => (b.id_bra || 0) - (a.id_bra || 0));

    return sorted.filter((item) => {
      const matchesKategori =
        selectedKategori === 'SEMUA' || item.kategori === selectedKategori;

      const q = searchQuery.toLowerCase().trim();
      const matchesSearch =
        !q ||
        (item.no_bra && item.no_bra.toLowerCase().includes(q)) ||
        (item.perihal && item.perihal.toLowerCase().includes(q)) ||
        (item.nama_smc && item.nama_smc.toLowerCase().includes(q)) ||
        (item.dari && item.dari.toLowerCase().includes(q)) ||
        (item.aksi && item.aksi.toLowerCase().includes(q)) ||
        (item.twu && item.twu.toLowerCase().includes(q)) ||
        (item.twb && item.twb.toLowerCase().includes(q));

      return matchesKategori && matchesSearch;
    });
  }, [braList, selectedKategori, searchQuery]);

  // Summary counts
  const counts = useMemo(() => {
    return {
      all: braList.length,
      mapel: braList.filter((b) => b.kategori === 'MAPEL').length,
      sarUmum: braList.filter((b) => b.kategori === 'SAR_UMUM').length,
      kapal: braList.filter((b) => b.kategori === 'PERGERAKAN_KAPAL').length,
    };
  }, [braList]);

  const handleCopyNoBra = (id: number, noBra: string, e: React.MouseEvent) => {
    e.stopPropagation();
    navigator.clipboard.writeText(noBra);
    setCopiedId(id);
    showToast(`Nomor BRA ${noBra} disalin!`, 'success');
    setTimeout(() => setCopiedId(null), 2000);
  };

  const handleQuickPrint = async (bra: BraSar, e: React.MouseEvent) => {
    e.stopPropagation();
    onSelectBra(bra);
  };

  const handleDeleteBra = async (bra: BraSar, e: React.MouseEvent) => {
    e.stopPropagation();
    const confirmed = await showConfirmDialog(
      `Hapus BRA SAR ${bra.no_bra}?`,
      `Apakah Anda yakin ingin menghapus naskah Berita Radio SAR "${bra.perihal || bra.no_bra}" dari arsip? Tindakan ini tidak dapat dibatalkan.`
    );
    if (confirmed && onDeleteBra) {
      await onDeleteBra(bra.id_bra);
      showToast(`Dokumen BRA ${bra.no_bra} berhasil dihapus!`, 'success');
    }
  };

  const getKategoriBadge = (kategori: KategoriBra) => {
    switch (kategori) {
      case 'MAPEL':
        return {
          label: 'BRA MAPEL',
          bg: 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30',
          icon: <Radio className="w-3.5 h-3.5" />,
        };
      case 'PERGERAKAN_KAPAL':
        return {
          label: 'BRA KAPAL',
          bg: 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-300 border-cyan-500/30',
          icon: <Anchor className="w-3.5 h-3.5" />,
        };
      case 'SAR_UMUM':
      default:
        return {
          label: 'BRA SAR UMUM',
          bg: 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
          icon: <FileText className="w-3.5 h-3.5" />,
        };
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      {/* Top Header & Breadcrumb */}
      <div className={`p-6 rounded-2xl border shadow-xl transition-colors ${
        isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
      }`}>
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="flex items-start gap-3">
            <button
              onClick={onBack}
              className={`p-2 rounded-xl border transition-colors mt-0.5 ${
                isDarkMode
                  ? 'border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-300'
                  : 'border-slate-200 bg-slate-100 hover:bg-slate-200 text-slate-700'
              }`}
              title="Kembali ke Dashboard"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>
            <div>
              <div className="flex items-center gap-2">
                <div className={`w-8 h-8 rounded-lg border flex items-center justify-center ${accentColors.badgeBg} ${accentColors.badgeText} border-current/30`}>
                  <Radio className="w-4 h-4" />
                </div>
                <h1 className={`text-xl font-bold tracking-tight ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                  Arsip Berita Radio SAR (BRA SAR)
                </h1>
              </div>
              <p className={`text-xs mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Pusat data arsip telegram radio resmi Kantor Pencarian dan Pertolongan (KPP) Kendari
              </p>
            </div>
          </div>

          {/* Quick Create Buttons */}
          <div className="flex flex-wrap items-center gap-2">
            <button
              onClick={() => onCreateBra('MAPEL')}
              className="inline-flex items-center gap-1.5 px-3 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-md transition-all hover:scale-105"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>+ Buat BRA Mapel</span>
            </button>
            <button
              onClick={() => onCreateBra('SAR_UMUM')}
              className="inline-flex items-center gap-1.5 px-3 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-md transition-all hover:scale-105"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>+ Buat BRA SAR Umum</span>
            </button>
            <button
              onClick={() => onCreateBra('PERGERAKAN_KAPAL')}
              className="inline-flex items-center gap-1.5 px-3 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold shadow-md transition-all hover:scale-105"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>+ Buat BRA Kapal</span>
            </button>
          </div>
        </div>

        {/* 4 Stat Summary Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-6 pt-5 border-t border-slate-200 dark:border-slate-800">
          <div
            onClick={() => setSelectedKategori('SEMUA')}
            className={`p-3.5 rounded-xl border cursor-pointer transition-all ${
              selectedKategori === 'SEMUA'
                ? isDarkMode
                  ? 'bg-slate-800 border-orange-500 ring-1 ring-orange-500/50'
                  : 'bg-orange-50/60 border-orange-400 ring-1 ring-orange-400/40'
                : isDarkMode
                ? 'bg-slate-950/60 border-slate-800 hover:bg-slate-800/80'
                : 'bg-slate-50 border-slate-200 hover:bg-slate-100'
            }`}
          >
            <div className="flex items-center justify-between text-slate-400 text-xs font-medium">
              <span>Total Semua Arsip</span>
              <Radio className="w-4 h-4 text-orange-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-2">
              <span className={`text-2xl font-black ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                {counts.all}
              </span>
              <span className="text-[11px] text-slate-400 font-medium">Dokumen</span>
            </div>
          </div>

          <div
            onClick={() => setSelectedKategori('MAPEL')}
            className={`p-3.5 rounded-xl border cursor-pointer transition-all ${
              selectedKategori === 'MAPEL'
                ? isDarkMode
                  ? 'bg-slate-800 border-orange-500 ring-1 ring-orange-500/50'
                  : 'bg-orange-50/60 border-orange-400 ring-1 ring-orange-400/40'
                : isDarkMode
                ? 'bg-slate-950/60 border-slate-800 hover:bg-slate-800/80'
                : 'bg-slate-50 border-slate-200 hover:bg-slate-100'
            }`}
          >
            <div className="flex items-center justify-between text-slate-400 text-xs font-medium">
              <span>BRA Mapel Kapal</span>
              <Radio className="w-4 h-4 text-orange-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-2">
              <span className={`text-2xl font-black ${isDarkMode ? 'text-orange-400' : 'text-orange-600'}`}>
                {counts.mapel}
              </span>
              <span className="text-[11px] text-slate-400 font-medium">Dokumen</span>
            </div>
          </div>

          <div
            onClick={() => setSelectedKategori('SAR_UMUM')}
            className={`p-3.5 rounded-xl border cursor-pointer transition-all ${
              selectedKategori === 'SAR_UMUM'
                ? isDarkMode
                  ? 'bg-slate-800 border-amber-500 ring-1 ring-amber-500/50'
                  : 'bg-amber-50/60 border-amber-400 ring-1 ring-amber-400/40'
                : isDarkMode
                ? 'bg-slate-950/60 border-slate-800 hover:bg-slate-800/80'
                : 'bg-slate-50 border-slate-200 hover:bg-slate-100'
            }`}
          >
            <div className="flex items-center justify-between text-slate-400 text-xs font-medium">
              <span>BRA SAR Umum</span>
              <FileText className="w-4 h-4 text-amber-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-2">
              <span className={`text-2xl font-black ${isDarkMode ? 'text-amber-400' : 'text-amber-600'}`}>
                {counts.sarUmum}
              </span>
              <span className="text-[11px] text-slate-400 font-medium">Dokumen</span>
            </div>
          </div>

          <div
            onClick={() => setSelectedKategori('PERGERAKAN_KAPAL')}
            className={`p-3.5 rounded-xl border cursor-pointer transition-all ${
              selectedKategori === 'PERGERAKAN_KAPAL'
                ? isDarkMode
                  ? 'bg-slate-800 border-cyan-500 ring-1 ring-cyan-500/50'
                  : 'bg-cyan-50/60 border-cyan-400 ring-1 ring-cyan-400/40'
                : isDarkMode
                ? 'bg-slate-950/60 border-slate-800 hover:bg-slate-800/80'
                : 'bg-slate-50 border-slate-200 hover:bg-slate-100'
            }`}
          >
            <div className="flex items-center justify-between text-slate-400 text-xs font-medium">
              <span>BRA Kapal SAR</span>
              <Anchor className="w-4 h-4 text-cyan-500" />
            </div>
            <div className="mt-2 flex items-baseline gap-2">
              <span className={`text-2xl font-black ${isDarkMode ? 'text-cyan-400' : 'text-cyan-600'}`}>
                {counts.kapal}
              </span>
              <span className="text-[11px] text-slate-400 font-medium">Dokumen</span>
            </div>
          </div>
        </div>
      </div>

      {/* Filter Toolbar & Search */}
      <div className={`p-4 rounded-2xl border shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-3 transition-colors ${
        isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
      }`}>
        {/* Search bar */}
        <div className="relative flex-1">
          <Search className="w-4 h-4 absolute left-3.5 top-3 text-slate-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Cari nomor dokumen (misal: 191/SAR-304), perihal, SMC, TWU..."
            className={`w-full pl-9.5 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 ${accentColors.ring} ${
              isDarkMode
                ? 'bg-slate-950 border-slate-700 text-white placeholder:text-slate-500'
                : 'bg-slate-50 border-slate-300 text-slate-900 placeholder:text-slate-400'
            }`}
          />
          {searchQuery && (
            <button
              onClick={() => setSearchQuery('')}
              className="absolute right-3 top-2.5 text-xs text-slate-400 hover:text-slate-200"
            >
              ✕
            </button>
          )}
        </div>

        {/* Category Tabs & View Switcher */}
        <div className="flex flex-wrap items-center gap-2">
          <div className={`p-1 rounded-xl border flex items-center gap-1 ${
            isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-100 border-slate-200'
          }`}>
            {(['SEMUA', 'MAPEL', 'SAR_UMUM', 'PERGERAKAN_KAPAL'] as const).map((cat) => (
              <button
                key={cat}
                onClick={() => setSelectedKategori(cat)}
                className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-all ${
                  selectedKategori === cat
                    ? isDarkMode
                      ? 'bg-orange-500 text-white shadow-sm'
                      : 'bg-white text-orange-600 shadow-sm font-bold'
                    : isDarkMode
                    ? 'text-slate-400 hover:text-slate-200'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                {cat === 'SEMUA'
                  ? 'Semua'
                  : cat === 'MAPEL'
                  ? 'Mapel'
                  : cat === 'SAR_UMUM'
                  ? 'SAR Umum'
                  : 'Kapal'}
              </button>
            ))}
          </div>

          <div className={`p-1 rounded-xl border flex items-center gap-1 ${
            isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-100 border-slate-200'
          }`}>
            <button
              onClick={() => setViewMode('grid')}
              className={`p-1.5 rounded-lg transition-colors ${
                viewMode === 'grid'
                  ? isDarkMode ? 'bg-slate-800 text-orange-400' : 'bg-white text-orange-600 shadow-sm'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
              title="Tampilan Grid Kartu"
            >
              <LayoutGrid className="w-4 h-4" />
            </button>
            <button
              onClick={() => setViewMode('table')}
              className={`p-1.5 rounded-lg transition-colors ${
                viewMode === 'table'
                  ? isDarkMode ? 'bg-slate-800 text-orange-400' : 'bg-white text-orange-600 shadow-sm'
                  : 'text-slate-400 hover:text-slate-200'
              }`}
              title="Tampilan Tabel Rinci"
            >
              <List className="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Main List Content */}
      {filteredBraList.length === 0 ? (
        <div className={`p-12 text-center rounded-2xl border transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="w-14 h-14 rounded-2xl bg-orange-500/15 text-orange-500 flex items-center justify-center mx-auto mb-3">
            <Radio className="w-7 h-7" />
          </div>
          <h3 className={`text-base font-bold ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
            Tidak Ada Dokumen BRA Ditemukan
          </h3>
          <p className={`text-xs mt-1 max-w-md mx-auto ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
            {searchQuery
              ? `Tidak ditemukan arsip dokumen yang cocok dengan kata kunci "${searchQuery}". Coba kata kunci lain.`
              : 'Belum ada dokumen Berita Radio SAR yang diterbitkan dalam kategori ini.'}
          </p>
          <div className="mt-4 flex items-center justify-center gap-2">
            <button
              onClick={() => onCreateBra('MAPEL')}
              className="px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold rounded-xl shadow-md transition-all"
            >
              + Terbitkan Dokumen BRA Baru
            </button>
          </div>
        </div>
      ) : viewMode === 'grid' ? (
        /* GRID VIEW */
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredBraList.map((bra) => {
            const badge = getKategoriBadge(bra.kategori);
            return (
              <div
                key={bra.id_bra}
                onClick={() => onSelectBra(bra)}
                className={`p-5 rounded-2xl border cursor-pointer transition-all duration-200 group flex flex-col justify-between hover:shadow-xl hover:scale-[1.01] ${
                  isDarkMode
                    ? 'bg-slate-900 border-slate-800 hover:border-orange-500/50'
                    : 'bg-white border-slate-200 hover:border-orange-400 shadow-sm'
                }`}
              >
                <div>
                  {/* Top Bar: Badge & Copy No BRA */}
                  <div className="flex items-center justify-between gap-2 mb-3">
                    <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-md border ${badge.bg}`}>
                      {badge.icon}
                      <span>{badge.label}</span>
                    </span>

                    <button
                      type="button"
                      onClick={(e) => handleCopyNoBra(bra.id_bra, bra.no_bra, e)}
                      className={`inline-flex items-center gap-1 px-2 py-1 text-[11px] font-mono rounded border transition-colors ${
                        copiedId === bra.id_bra
                          ? 'bg-emerald-500/20 text-emerald-400 border-emerald-500/40'
                          : isDarkMode
                          ? 'bg-slate-800 text-slate-300 border-slate-700 hover:bg-slate-700'
                          : 'bg-slate-100 text-slate-700 border-slate-200 hover:bg-slate-200'
                      }`}
                      title="Salin Nomor Dokumen"
                    >
                      {copiedId === bra.id_bra ? <Check className="w-3 h-3 text-emerald-400" /> : <Copy className="w-3 h-3" />}
                      <span>{bra.no_bra}</span>
                    </button>
                  </div>

                  {/* Perihal */}
                  <h3 className={`text-xs font-bold leading-snug line-clamp-2 group-hover:text-orange-500 transition-colors ${
                    isDarkMode ? 'text-white' : 'text-slate-900'
                  }`}>
                    {bra.perihal || 'BERITA RADIO SAR'}
                  </h3>

                  {/* Metadata TWU, TWB & SMC */}
                  <div className="mt-3.5 space-y-1.5 text-[11px]">
                    <div className="flex items-center justify-between text-slate-400">
                      <span className="flex items-center gap-1 font-mono">
                        <Clock className="w-3 h-3 text-orange-500" />
                        TWU: <strong>{bra.twu}</strong>
                      </span>
                      <span className="font-mono">
                        TWB: <strong>{bra.twb}</strong>
                      </span>
                    </div>

                    <div className={`p-2.5 rounded-lg border text-[11px] ${
                      isDarkMode ? 'bg-slate-950/60 border-slate-800 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'
                    }`}>
                      <div className="flex items-center justify-between">
                        <span className="font-semibold text-slate-400">SMC / Penandatangan:</span>
                        <span className="font-bold text-orange-500">{bra.nama_smc}</span>
                      </div>
                      <div className="text-[10px] text-slate-400 truncate mt-0.5">
                        {bra.jabatan_smc} • {bra.pangkat_smc}
                      </div>
                    </div>
                  </div>
                </div>

                {/* Bottom Actions */}
                <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                  <div className="flex items-center gap-1 text-[11px] text-emerald-500">
                    <ShieldCheck className="w-3.5 h-3.5" />
                    <span>QR Terverifikasi</span>
                  </div>

                  <div className="flex items-center gap-1.5">
                    <button
                      type="button"
                      onClick={(e) => handleQuickPrint(bra, e)}
                      className={`p-1.5 rounded-lg border transition-colors ${
                        isDarkMode
                          ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                          : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                      }`}
                      title="Lihat & Cetak Dokumen"
                    >
                      <Eye className="w-3.5 h-3.5" />
                    </button>

                    {onDeleteBra && (
                      <button
                        type="button"
                        onClick={(e) => handleDeleteBra(bra, e)}
                        className="p-1.5 rounded-lg border border-red-500/30 text-red-500 hover:bg-red-500/15 transition-colors"
                        title="Hapus Dokumen BRA SAR"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      ) : (
        /* TABLE VIEW */
        <div className={`rounded-2xl border overflow-hidden shadow-xl transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className={`border-b text-[11px] uppercase tracking-wider font-bold ${
                  isDarkMode
                    ? 'bg-slate-950/80 border-slate-800 text-slate-400'
                    : 'bg-slate-50 border-slate-200 text-slate-600'
                }`}>
                  <th className="py-3 px-4">Kategori</th>
                  <th className="py-3 px-4">Nomor Dokumen</th>
                  <th className="py-3 px-4">Perihal / Operasi</th>
                  <th className="py-3 px-4">TWU / TWB</th>
                  <th className="py-3 px-4">SMC / Penandatangan</th>
                  <th className="py-3 px-4 text-center">Aksi</th>
                </tr>
              </thead>
              <tbody className={`divide-y ${isDarkMode ? 'divide-slate-800' : 'divide-slate-200'}`}>
                {filteredBraList.map((bra) => {
                  const badge = getKategoriBadge(bra.kategori);
                  return (
                    <tr
                      key={bra.id_bra}
                      onClick={() => onSelectBra(bra)}
                      className={`cursor-pointer transition-colors ${
                        isDarkMode ? 'hover:bg-slate-800/60' : 'hover:bg-slate-50'
                      }`}
                    >
                      <td className="py-3 px-4 whitespace-nowrap">
                        <span className={`inline-flex items-center gap-1 px-2.5 py-0.5 text-[10px] font-bold rounded border ${badge.bg}`}>
                          {badge.icon}
                          <span>{badge.label}</span>
                        </span>
                      </td>

                      <td className="py-3 px-4 whitespace-nowrap font-mono font-bold text-orange-500">
                        {bra.no_bra}
                      </td>

                      <td className="py-3 px-4 max-w-xs">
                        <div className={`font-semibold line-clamp-1 ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                          {bra.perihal}
                        </div>
                        <div className="text-[10px] text-slate-400 truncate">
                          DARI: {bra.dari} → AKSI: {bra.aksi}
                        </div>
                      </td>

                      <td className="py-3 px-4 whitespace-nowrap font-mono text-[11px]">
                        <div>TWU: <strong className={isDarkMode ? 'text-slate-200' : 'text-slate-800'}>{bra.twu}</strong></div>
                        <div className="text-slate-400 text-[10px]">TWB: {bra.twb}</div>
                      </td>

                      <td className="py-3 px-4 whitespace-nowrap">
                        <div className={`font-semibold ${isDarkMode ? 'text-slate-200' : 'text-slate-900'}`}>
                          {bra.nama_smc}
                        </div>
                        <div className="text-[10px] text-slate-400">
                          {bra.jabatan_smc}
                        </div>
                      </td>

                      <td className="py-3 px-4 text-center whitespace-nowrap" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-center gap-1.5">
                          <button
                            onClick={() => onSelectBra(bra)}
                            className="px-2.5 py-1 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-semibold inline-flex items-center gap-1"
                          >
                            <Eye className="w-3 h-3" />
                            <span>Buka</span>
                          </button>
                          {onDeleteBra && (
                            <button
                              onClick={(e) => handleDeleteBra(bra, e)}
                              className="p-1 rounded-lg border border-red-500/30 text-red-500 hover:bg-red-500/15 transition-colors"
                              title="Hapus Dokumen"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};
