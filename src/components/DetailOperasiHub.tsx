import React, { useState } from 'react';
import {
  ArrowLeft,
  Calendar,
  Clock,
  Compass,
  FileText,
  PlusCircle,
  Copy,
  Check,
  Eye,
  Trash2,
  Edit,
  RotateCcw,
  Users,
  Ship,
  Shield,
  AlertCircle,
  Lock,
  Radio,
} from 'lucide-react';
import { Operasi, Renops, LaporanHarian, MasterAlut, MasterTim, UserSession } from '../types';
import {
  formatTanggalIndo,
  formatJamWITA,
  hitungHariKe,
  hitungStatistikKorban,
  getLabelStatusKorban,
  formatTW,
} from '../utils/templates';
import { getOperationalTimeline } from '../utils/operationHierarchy';
import { FormRenopsModal } from './FormRenopsModal';
import { FormSitrepModal } from './FormSitrepModal';
import { ViewLaporanModal } from './ViewLaporanModal';
import { showSuccessAlert, showConfirmDialog, showToast } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface DetailOperasiHubProps {
  operasi: Operasi;
  masterAlut: MasterAlut[];
  masterTim: MasterTim[];
  currentUser?: UserSession | null;
  onBack: () => void;
  onEditOperasi: () => void;
  onRefreshOperasi: (id: number) => Promise<void>;
  onDeleteOperasi: (id: number) => Promise<void>;
  onCreateBraMapel?: () => void;
  onCreateBraSarUmum?: () => void;
}

export const DetailOperasiHub: React.FC<DetailOperasiHubProps> = ({
  operasi,
  masterAlut,
  masterTim,
  currentUser,
  onBack,
  onEditOperasi,
  onRefreshOperasi,
  onDeleteOperasi,
  onCreateBraMapel,
  onCreateBraSarUmum,
}) => {
  const { isDarkMode, themePalette, accentColors } = useTheme();
  const [activeTab, setActiveTab] = useState<'hierarchy' | 'renops' | 'sitrep' | 'korban' | 'kekuatan'>('hierarchy');

  // Modals
  const [showRenopsModal, setShowRenopsModal] = useState(false);
  const [editingRenops, setEditingRenops] = useState<Renops | null>(null);

  const [showSitrepModal, setShowSitrepModal] = useState(false);
  const [editingSitrep, setEditingSitrep] = useState<LaporanHarian | null>(null);

  // View Text Modal
  const [viewModalData, setViewModalData] = useState<{
    isOpen: boolean;
    title: string;
    subtitle: string;
    text: string;
    onSave?: (newText: string) => Promise<void>;
  }>({
    isOpen: false,
    title: '',
    subtitle: '',
    text: '',
  });

  // Copied indicator
  const [copiedId, setCopiedId] = useState<string | null>(null);

  const statsKorban = hitungStatistikKorban(operasi.korban);
  const hariKeSekarang = hitungHariKe(operasi.waktu_berangkat || operasi.waktu_lapor);
  const isClosed = operasi.status === 'CLOSED';

  const handleCopyText = async (id: string, text: string) => {
    try {
      await navigator.clipboard.writeText(text);
      setCopiedId(id);
      showToast('Naskah teks berhasil disalin ke clipboard!', 'success');
      setTimeout(() => setCopiedId(null), 2000);
    } catch (err) {
      console.error(err);
    }
  };

  // Rollback penutupan
  const handleRollbackPenutupan = async () => {
    const confirmed = await showConfirmDialog(
      'Rollback Status Operasi ke OPEN?',
      'Apakah Anda yakin ingin membatalkan penutupan operasi ini? Status akan dikembalikan menjadi OPEN untuk melanjutkan pencarian.'
    );
    if (!confirmed) return;

    try {
      const res = await fetch(`/api/operasi/${operasi.id_operasi}/rollback-penutupan`, {
        method: 'POST',
      });
      const data = await res.json();
      if (data.success) {
        await onRefreshOperasi(operasi.id_operasi);
        await showSuccessAlert('Operasi Dibuka Kembali', 'Status operasi SAR berhasil dikembalikan ke status OPEN.');
      }
    } catch (err) {
      console.error(err);
    }
  };

  // Delete Renops with SweetAlert
  const handleDeleteRenops = async (id_renops: number, hari_ke?: string) => {
    if (currentUser?.role === 'USER') {
      showToast('Akses ditolak: Pengguna tidak memiliki izin menghapus Rencana Operasi.', 'error');
      return;
    }
    const confirmed = await showConfirmDialog(
      `Hapus Rencana Operasi ${hari_ke || ''}?`,
      `Apakah Anda yakin ingin menghapus Rencana Operasi ${hari_ke || ''}? Seluruh data pembagian SRU dan draf teks WA Renops ini akan dihapus secara permanen.`
    );
    if (!confirmed) return;

    try {
      const res = await fetch(`/api/renops/${id_renops}`, {
        method: 'DELETE',
        headers: { 'X-User-Role': currentUser?.role || 'ADMIN' },
      });
      if (res.ok) {
        await onRefreshOperasi(operasi.id_operasi);
        await showSuccessAlert('Berhasil Dihapus', `Rencana Operasi SAR ${hari_ke || ''} berhasil dihapus.`);
      }
    } catch (err) {
      console.warn(err);
    }
  };

  // Delete Sitrep with SweetAlert
  const handleDeleteSitrep = async (id_laporan: number, hari_ke?: string, isPenutupan?: boolean) => {
    if (currentUser?.role === 'USER') {
      showToast('Akses ditolak: Pengguna tidak memiliki izin menghapus Laporan Perkembangan.', 'error');
      return;
    }
    const confirmed = await showConfirmDialog(
      `Hapus ${isPenutupan ? 'Laporan Penutupan' : 'Laporan Perkembangan'} ${hari_ke || ''}?`,
      isPenutupan
        ? `Laporan ini berstatus PENUTUPAN OPERASI. Menghapus laporan ini akan secara otomatis mengembalikan status operasi SAR menjadi OPEN (berjalan). Lanjutkan?`
        : `Apakah Anda yakin ingin menghapus Laporan Perkembangan ${hari_ke || ''}?`
    );
    if (!confirmed) return;

    try {
      const res = await fetch(`/api/laporan/${id_laporan}`, {
        method: 'DELETE',
        headers: { 'X-User-Role': currentUser?.role || 'ADMIN' },
      });
      const data = await res.json();
      if (data.success) {
        await onRefreshOperasi(operasi.id_operasi);
        await showSuccessAlert('Berhasil Dihapus', `${isPenutupan ? 'Laporan Penutupan' : 'Laporan Perkembangan'} ${hari_ke || ''} berhasil dihapus.`);
      }
    } catch (err) {
      console.warn(err);
    }
  };

  // Delete Entire Operasi
  const handleDeleteThisOperasi = async () => {
    if (currentUser?.role === 'USER') {
      showToast('Akses ditolak: Pengguna tidak memiliki izin menghapus data operasi SAR.', 'error');
      return;
    }
    const confirmed = await showConfirmDialog(
      `Hapus Operasi "${operasi.nama_operasi}"?`,
      'Apakah Anda yakin ingin menghapus operasi ini beserta seluruh arsip Renops dan Lapbang-nya? Tindakan ini permanen.'
    );
    if (!confirmed) return;
    await onDeleteOperasi(operasi.id_operasi);
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      {/* Top Breadcrumb & Controls */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          onClick={onBack}
          className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
            isDarkMode
              ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
              : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300 shadow-sm'
          }`}
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Kembali ke Daftar Operasi</span>
        </button>

        <div className="flex items-center gap-2">
          <button
            onClick={onEditOperasi}
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${
              isDarkMode
                ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300 shadow-sm'
            }`}
          >
            <Edit className="w-3.5 h-3.5 text-amber-500" />
            <span>Koreksi / Edit Data Awal</span>
          </button>

          <button
            onClick={() =>
              setViewModalData({
                isOpen: true,
                title: 'LAPORAN AWAL OPS SAR',
                subtitle: operasi.nama_operasi,
                text: operasi.full_text_wa,
                onSave: async (newText) => {
                  await fetch(`/api/operasi/${operasi.id_operasi}`, {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ operasi: { full_text_wa: newText } }),
                  });
                  await onRefreshOperasi(operasi.id_operasi);
                },
              })
            }
            className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${
              isDarkMode
                ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300 shadow-sm'
            }`}
          >
            <Eye className={`w-3.5 h-3.5 ${accentColors.text}`} />
            <span>Lihat Teks WA Laporan Awal</span>
          </button>

          {onCreateBraMapel && (
            <button
              onClick={onCreateBraMapel}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${accentColors.badgeBg} ${accentColors.badgeText} border-current/30 hover:opacity-90`}
              title="Buat Berita Radio Pemapelan (BRA Mapel)"
            >
              <Radio className="w-3.5 h-3.5" />
              <span>+ BRA Mapel</span>
            </button>
          )}

          {onCreateBraSarUmum && (
            <button
              onClick={onCreateBraSarUmum}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${
                isDarkMode
                  ? 'bg-amber-600/20 hover:bg-amber-600/30 text-amber-300 border-amber-500/40'
                  : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-300'
              }`}
              title="Buat Berita Radio SAR Operasi SAR Perkembangan"
            >
              <FileText className="w-3.5 h-3.5 text-amber-500" />
              <span>+ BRA SAR Operasi SAR</span>
            </button>
          )}

          {currentUser?.role !== 'USER' && (
            <button
              onClick={handleDeleteThisOperasi}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors ${
                isDarkMode
                  ? 'bg-red-500/10 hover:bg-red-500/20 text-red-400 border-red-500/30'
                  : 'bg-red-50 hover:bg-red-100 text-red-600 border-red-200'
              }`}
              title="Hapus Operasi Ini"
            >
              <Trash2 className="w-3.5 h-3.5" />
              <span className="hidden sm:inline">Hapus Operasi</span>
            </button>
          )}
        </div>
      </div>

      {/* Main Operational Banner Card */}
      <div className={`relative rounded-2xl p-5 sm:p-6 border transition-all overflow-hidden ${
        isDarkMode
          ? 'bg-slate-900 border-slate-800 shadow-2xl'
          : 'bg-white border-slate-200 shadow-md'
      }`}>
        {/* Subtle accent glow */}
        <div className={`absolute top-0 right-0 w-80 h-80 rounded-full blur-3xl pointer-events-none -mr-20 -mt-20 opacity-30 ${
          isClosed ? 'bg-slate-500' : 'bg-orange-500'
        }`} />

        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 relative z-10">
          <div className="space-y-2">
            <div className="flex flex-wrap items-center gap-2">
              <span className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider border ${
                isClosed
                  ? 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400 border-slate-300 dark:border-slate-700'
                  : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
              }`}>
                {!isClosed && <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>}
                {operasi.status}
              </span>

              <span className={`px-2.5 py-0.5 rounded-full text-xs font-mono font-bold border border-current/20 ${accentColors.badgeBg} ${accentColors.badgeText}`}>
                {isClosed ? 'OPERASI SELESAI' : `OPERASI BERJALAN (${hariKeSekarang})`}
              </span>

              <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium border ${
                isDarkMode ? 'bg-slate-800 text-slate-300 border-slate-700' : 'bg-slate-100 text-slate-700 border-slate-300'
              }`}>
                ID Ops: #{operasi.id_operasi}
              </span>
            </div>

            <h1 className={`text-xl sm:text-2xl font-extrabold tracking-tight ${
              isDarkMode ? 'text-white' : 'text-slate-900'
            }`}>
              {operasi.nama_operasi}
            </h1>

            <div className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400">
              <div className="flex items-center gap-1">
                <Compass className={`w-3.5 h-3.5 ${accentColors.text}`} />
                <span className="text-slate-700 dark:text-slate-300 font-medium">{operasi.lokasi_kejadian}</span>
              </div>
              <span>•</span>
              <div className="flex items-center gap-1 font-mono text-emerald-600 dark:text-emerald-400 font-medium">
                <span>{operasi.koordinat}</span>
              </div>
              <span>•</span>
              <div className="flex items-center gap-1">
                <Clock className="w-3.5 h-3.5 opacity-60" />
                <span>Berangkat: {formatTanggalIndo(operasi.waktu_berangkat)} ({formatJamWITA(operasi.waktu_berangkat)} WITA)</span>
              </div>
            </div>
          </div>

          {/* Quick Stats & Action Hub */}
          <div className="flex flex-wrap items-center gap-3">
            {/* Victim Breakdown Pill */}
            <div className={`flex items-center gap-2 p-2 px-3 rounded-xl text-xs border ${
              isDarkMode ? 'bg-slate-950/70 border-slate-800' : 'bg-slate-50 border-slate-200'
            }`}>
              <span className="text-slate-500 dark:text-slate-400 font-medium">POB ({statsKorban.total}):</span>
              <span className="text-emerald-600 dark:text-emerald-400 font-bold">{statsKorban.s} S</span>
              <span className="text-slate-400 dark:text-slate-600">|</span>
              <span className="text-red-500 dark:text-red-400 font-bold">{statsKorban.md} MD</span>
              <span className="text-slate-400 dark:text-slate-600">|</span>
              <span className="text-amber-500 dark:text-amber-400 font-bold">{statsKorban.dp} DP</span>
              <span className="text-slate-400 dark:text-slate-600">|</span>
              <span className="text-slate-600 dark:text-slate-400 font-bold">{statsKorban.h} H</span>
            </div>

            {/* If Closed: Rollback button */}
            {isClosed ? (
              <button
                onClick={handleRollbackPenutupan}
                className={`inline-flex items-center gap-1.5 px-3 py-2 border rounded-xl text-xs font-semibold transition-colors ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-amber-300 border-amber-500/30'
                    : 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-300'
                }`}
              >
                <RotateCcw className="w-3.5 h-3.5" />
                <span>Rollback / Buka Kembali Operasi</span>
              </button>
            ) : (
              <div className="flex items-center gap-2">
                <button
                  id="btn-buat-renops"
                  onClick={() => setShowRenopsModal(true)}
                  className={`inline-flex items-center gap-1.5 px-3.5 py-2 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20 transition-all hover:scale-[1.02]`}
                >
                  <PlusCircle className="w-4 h-4" />
                  <span>+ Renops H.X</span>
                </button>

                <button
                  id="btn-buat-sitrep"
                  onClick={() => setShowSitrepModal(true)}
                  className={`inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-bold border transition-colors shadow-sm ${
                    isDarkMode
                      ? 'bg-slate-800 hover:bg-slate-700 text-white border-slate-700'
                      : 'bg-slate-100 hover:bg-slate-200 text-slate-800 border-slate-300'
                  }`}
                >
                  <PlusCircle className="w-4 h-4 text-emerald-500" />
                  <span>+ Lapbang / Tutup</span>
                </button>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Hub Tabs Navigation */}
      <div className={`border-b flex flex-wrap gap-2 text-xs font-semibold ${
        isDarkMode ? 'border-slate-800' : 'border-slate-200'
      }`}>
        <button
          onClick={() => setActiveTab('hierarchy')}
          className={`flex items-center gap-2 px-4 py-3 border-b-2 transition-colors ${
            activeTab === 'hierarchy'
              ? `${accentColors.border} ${accentColors.text} ${accentColors.badgeBg}`
              : isDarkMode
                ? 'border-transparent text-slate-400 hover:text-slate-200'
                : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Clock className="w-4 h-4" />
          <span>Hirarki Dokumen SAR ({getOperationalTimeline(operasi).length})</span>
        </button>

        <button
          onClick={() => setActiveTab('renops')}
          className={`flex items-center gap-2 px-4 py-3 border-b-2 transition-colors ${
            activeTab === 'renops'
              ? `${accentColors.border} ${accentColors.text} ${accentColors.badgeBg}`
              : isDarkMode
                ? 'border-transparent text-slate-400 hover:text-slate-200'
                : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Compass className="w-4 h-4" />
          <span>Rencana Operasi ({operasi.renops?.length || 0})</span>
        </button>

        <button
          onClick={() => setActiveTab('sitrep')}
          className={`flex items-center gap-2 px-4 py-3 border-b-2 transition-colors ${
            activeTab === 'sitrep'
              ? `${accentColors.border} ${accentColors.text} ${accentColors.badgeBg}`
              : isDarkMode
                ? 'border-transparent text-slate-400 hover:text-slate-200'
                : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <FileText className="w-4 h-4" />
          <span>Laporan Perkembangan & Penutupan ({operasi.laporan_harian?.length || 0})</span>
        </button>

        <button
          onClick={() => setActiveTab('korban')}
          className={`flex items-center gap-2 px-4 py-3 border-b-2 transition-colors ${
            activeTab === 'korban'
              ? `${accentColors.border} ${accentColors.text} ${accentColors.badgeBg}`
              : isDarkMode
                ? 'border-transparent text-slate-400 hover:text-slate-200'
                : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Users className="w-4 h-4" />
          <span>Data Korban & Kronologi</span>
        </button>

        <button
          onClick={() => setActiveTab('kekuatan')}
          className={`flex items-center gap-2 px-4 py-3 border-b-2 transition-colors ${
            activeTab === 'kekuatan'
              ? `${accentColors.border} ${accentColors.text} ${accentColors.badgeBg}`
              : isDarkMode
                ? 'border-transparent text-slate-400 hover:text-slate-200'
                : 'border-transparent text-slate-500 hover:text-slate-800'
          }`}
        >
          <Ship className="w-4 h-4" />
          <span>Kekuatan Unsur & Alut Terkini</span>
        </button>
      </div>

      {/* Tab 0: Alur Hirarki Laporan */}
      {activeTab === 'hierarchy' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <h2 className={`text-sm font-bold uppercase tracking-wider ${
                isDarkMode ? 'text-slate-200' : 'text-slate-800'
              }`}>
                Alur Hirarki Laporan Operasi SAR
              </h2>
              <p className="text-xs text-slate-400 mt-0.5">
                Urutan terstruktur: Laporan Awal ➔ Renops H.1 ➔ Lapbang H.1 ➔ Renops H.2 ➔ Lapbang H.2 ➔ dst.
              </p>
            </div>

            {!isClosed && (
              <div className="flex items-center gap-2">
                <button
                  onClick={() => {
                    setEditingRenops(null);
                    setShowRenopsModal(true);
                  }}
                  className={`inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r ${accentColors.gradient} text-white rounded-lg text-xs font-semibold shadow-md`}
                >
                  <PlusCircle className="w-3.5 h-3.5" />
                  <span>+ Renops Baru</span>
                </button>
                <button
                  onClick={() => {
                    setEditingSitrep(null);
                    setShowSitrepModal(true);
                  }}
                  className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                    isDarkMode ? 'bg-slate-800 text-white border-slate-700' : 'bg-white text-slate-800 border-slate-300'
                  }`}
                >
                  <PlusCircle className="w-3.5 h-3.5 text-emerald-500" />
                  <span>+ Lapbang Baru</span>
                </button>
              </div>
            )}
          </div>

          <div className="relative pl-6 space-y-4 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-orange-500/30">
            {getOperationalTimeline(operasi).map((doc, idx) => (
              <div
                key={doc.id}
                className={`relative p-4 rounded-2xl border transition-all ${
                  isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
                }`}
              >
                <div className="absolute -left-6 top-4 w-4 h-4 rounded-full border-2 border-white dark:border-slate-950 bg-orange-500 flex items-center justify-center text-[8px] font-bold text-white">
                  {idx + 1}
                </div>

                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div>
                    <div className="flex items-center gap-2">
                      <span className={`px-2 py-0.5 text-[10px] font-mono font-bold rounded-md border ${
                        doc.type === 'LAPORAN_AWAL'
                          ? 'bg-blue-500/15 text-blue-500 border-blue-500/30'
                          : doc.type === 'RENOPS'
                          ? 'bg-orange-500/15 text-orange-500 border-orange-500/30'
                          : doc.type === 'PENUTUPAN'
                          ? 'bg-red-500/15 text-red-500 border-red-500/30'
                          : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                      }`}>
                        {doc.type} • {doc.hariKe}
                      </span>
                      <span className="text-xs font-bold text-slate-800 dark:text-slate-200">{doc.label}</span>
                    </div>
                    <p className="text-xs text-slate-400 mt-1">
                      Waktu/TW: {formatTanggalIndo(doc.waktu)} ({formatJamWITA(doc.waktu)} WITA)
                      {doc.cuaca ? ` • Cuaca: ${doc.cuaca}` : ''}
                    </p>
                  </div>

                  <div className="flex items-center gap-2">
                    {doc.type === 'LAPORAN_AWAL' && (
                      <button
                        onClick={() =>
                          setViewModalData({
                            isOpen: true,
                            title: 'LAPORAN AWAL OPS SAR',
                            subtitle: operasi.nama_operasi,
                            text: operasi.full_text_wa,
                          })
                        }
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                          isDarkMode ? 'bg-slate-800 text-slate-200 border-slate-700' : 'bg-slate-100 text-slate-700 border-slate-300'
                        }`}
                      >
                        Lihat Naskah WA
                      </button>
                    )}

                    {doc.type === 'RENOPS' && (
                      <button
                        onClick={() => setActiveTab('renops')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                          isDarkMode ? 'bg-slate-800 text-orange-400 border-slate-700' : 'bg-slate-100 text-orange-600 border-slate-300'
                        }`}
                      >
                        Buka Tab Renops
                      </button>
                    )}

                    {(doc.type === 'LAPBANG' || doc.type === 'PENUTUPAN') && (
                      <button
                        onClick={() => setActiveTab('sitrep')}
                        className={`px-3 py-1.5 rounded-lg text-xs font-semibold border ${
                          isDarkMode ? 'bg-slate-800 text-emerald-400 border-slate-700' : 'bg-slate-100 text-emerald-600 border-slate-300'
                        }`}
                      >
                        Buka Tab Lapbang
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Tab 1: Rencana Operasi (Renops H.X) */}
      {activeTab === 'renops' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className={`text-sm font-bold uppercase tracking-wider ${
              isDarkMode ? 'text-slate-200' : 'text-slate-800'
            }`}>
              Daftar Rencana Operasi Harian (Renops)
            </h2>
            {!isClosed && (
              <button
                onClick={() => {
                  setEditingRenops(null);
                  setShowRenopsModal(true);
                }}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r ${accentColors.gradient} text-white rounded-lg text-xs font-semibold shadow-md`}
              >
                <PlusCircle className="w-3.5 h-3.5" />
                <span>Buat Renops Hari Baru</span>
              </button>
            )}
          </div>

          {(!operasi.renops || operasi.renops.length === 0) ? (
            <div className={`p-8 text-center rounded-2xl border space-y-3 ${
              isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              <Compass className={`w-10 h-10 mx-auto ${isDarkMode ? 'text-slate-600' : 'text-slate-300'}`} />
              <p className={`text-xs ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>Belum ada Rencana Operasi yang dibuat untuk operasi ini.</p>
              {!isClosed && (
                <button
                  onClick={() => {
                    setEditingRenops(null);
                    setShowRenopsModal(true);
                  }}
                  className={`px-4 py-2 bg-gradient-to-r ${accentColors.gradient} text-white rounded-lg text-xs font-semibold shadow`}
                >
                  + Buat Renops H.1 Sekarang
                </button>
              )}
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {operasi.renops.map((r) => (
                <div
                  key={r.id_renops}
                  className={`rounded-2xl p-5 border space-y-4 transition-all ${
                    isDarkMode
                      ? 'bg-slate-900 border-slate-800 hover:border-slate-700 shadow-lg'
                      : 'bg-white border-slate-200 hover:border-slate-300 shadow-sm'
                  }`}
                >
                  <div className="flex items-start justify-between">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className={`px-2.5 py-0.5 rounded text-xs font-bold font-mono border border-current/20 ${accentColors.badgeBg} ${accentColors.badgeText}`}>
                          {r.hari_ke}
                        </span>
                        <h3 className={`text-sm font-bold ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                          Rencana Operasi SAR {r.hari_ke}
                        </h3>
                      </div>
                      <p className={`text-xs mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                        {formatTanggalIndo(r.tanggal_laporan)}
                      </p>
                    </div>

                    {currentUser?.role !== 'USER' && (
                      <button
                        type="button"
                        onClick={() => handleDeleteRenops(r.id_renops, r.hari_ke)}
                        title="Hapus Rencana Operasi"
                        className={`inline-flex items-center justify-center p-2 rounded-lg border transition-all active:scale-95 cursor-pointer shadow-sm ${
                          isDarkMode
                            ? 'bg-slate-800/80 hover:bg-red-500/20 text-slate-400 hover:text-red-400 border-slate-700/60'
                            : 'bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 border-slate-200'
                        }`}
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    )}
                  </div>

                  {/* SRU Summary */}
                  {r.srus && r.srus.length > 0 && (
                    <div className={`space-y-1.5 p-3 rounded-xl text-xs border ${
                      isDarkMode ? 'bg-slate-950/60 border-slate-800/80' : 'bg-slate-50 border-slate-200'
                    }`}>
                      <span className="font-semibold text-slate-500 dark:text-slate-400 text-[11px] uppercase tracking-wider">
                        Pembagian SRU ({r.srus.length}):
                      </span>
                      {r.srus.map((s, idx) => (
                        <div key={idx} className="flex items-center justify-between">
                          <span className={`font-medium truncate ${accentColors.text}`}>{s.nama_sru}</span>
                          <span className="text-[11px] text-slate-500 dark:text-slate-400">{s.pola_pencarian}</span>
                        </div>
                      ))}
                    </div>
                  )}

                  {/* Briefing Time */}
                  <div className="text-xs text-slate-500 dark:text-slate-400 flex items-center justify-between pt-1">
                    <span>Briefing: {formatJamWITA(r.waktu_briefing)} WITA</span>
                    <span>Mulai Ops: {formatJamWITA(r.waktu_mulai_ops)} WITA</span>
                  </div>

                  {/* Action Buttons */}
                  <div className={`flex items-center justify-end gap-2 pt-2 border-t ${
                    isDarkMode ? 'border-slate-800' : 'border-slate-100'
                  }`}>
                    <button
                      onClick={() => {
                        setEditingRenops(r);
                        setShowRenopsModal(true);
                      }}
                      className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                        isDarkMode
                          ? 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border-amber-500/30'
                          : 'bg-amber-50 hover:bg-amber-100 text-amber-700 border-amber-200'
                      }`}
                      title="Edit Rencana Operasi"
                    >
                      <Edit className="w-3.5 h-3.5" />
                      <span>Edit Renops</span>
                    </button>

                    <button
                      onClick={() => handleCopyText(`renops-${r.id_renops}`, r.full_text_wa)}
                      className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                        isDarkMode
                          ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                          : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                      }`}
                    >
                      {copiedId === `renops-${r.id_renops}` ? (
                        <Check className="w-3.5 h-3.5 text-emerald-500" />
                      ) : (
                        <Copy className="w-3.5 h-3.5" />
                      )}
                      <span>{copiedId === `renops-${r.id_renops}` ? 'Tersalin!' : 'Salin WA'}</span>
                    </button>

                    <button
                      onClick={() =>
                        setViewModalData({
                          isOpen: true,
                          title: `RENCANA OPS SAR ${r.hari_ke.toUpperCase()}`,
                          subtitle: operasi.nama_operasi,
                          text: r.full_text_wa,
                        })
                      }
                      className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-lg text-xs font-semibold shadow-md transition-colors`}
                    >
                      <Eye className="w-3.5 h-3.5" />
                      <span>Lihat Format WA</span>
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Tab 2: Laporan Perkembangan (Lapbang / Sitrep) */}
      {activeTab === 'sitrep' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className={`text-sm font-bold uppercase tracking-wider ${
              isDarkMode ? 'text-slate-200' : 'text-slate-800'
            }`}>
              Daftar Laporan Perkembangan & Penutupan (Sitrep)
            </h2>
            {!isClosed && (
              <button
                onClick={() => {
                  setEditingSitrep(null);
                  setShowSitrepModal(true);
                }}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r ${accentColors.gradient} text-white rounded-lg text-xs font-semibold shadow-md`}
              >
                <PlusCircle className="w-3.5 h-3.5" />
                <span>Buat Lapbang Harian / Tutup</span>
              </button>
            )}
          </div>

          {(!operasi.laporan_harian || operasi.laporan_harian.length === 0) ? (
            <div className={`p-8 text-center rounded-2xl border space-y-3 ${
              isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              <FileText className={`w-10 h-10 mx-auto ${isDarkMode ? 'text-slate-600' : 'text-slate-300'}`} />
              <p className={`text-xs ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>Belum ada Laporan Perkembangan yang dibuat.</p>
              {!isClosed && (
                <button
                  onClick={() => {
                    setEditingSitrep(null);
                    setShowSitrepModal(true);
                  }}
                  className={`px-4 py-2 bg-gradient-to-r ${accentColors.gradient} text-white rounded-lg text-xs font-semibold shadow`}
                >
                  + Buat Lapbang H.1 Sekarang
                </button>
              )}
            </div>
          ) : (
            <div className="space-y-3">
              {operasi.laporan_harian.map((l) => {
                const isPenutupan = l.jenis_laporan === 'PENUTUPAN';
                return (
                  <div
                    key={l.id_laporan}
                    className={`rounded-2xl p-5 border shadow-lg space-y-3 transition-all ${
                      isPenutupan
                        ? isDarkMode
                          ? 'border-red-500/50 bg-gradient-to-br from-slate-900 via-slate-900 to-red-950/20'
                          : 'border-red-300 bg-red-50/40'
                        : isDarkMode
                          ? 'bg-slate-900 border-slate-800 hover:border-slate-700'
                          : 'bg-white border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex items-center gap-2.5">
                        <span className={`px-2.5 py-0.5 rounded text-xs font-bold font-mono border ${
                          isPenutupan
                            ? 'bg-red-500/20 text-red-500 dark:text-red-400 border-red-500/30'
                            : 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border-emerald-500/30'
                        }`}>
                          {l.hari_ke}
                        </span>

                        <div>
                          <h3 className={`text-sm font-bold flex items-center gap-2 ${
                            isDarkMode ? 'text-white' : 'text-slate-900'
                          }`}>
                            {isPenutupan ? `LAPBANG OPS SAR ${l.hari_ke} DAN PENUTUPAN` : `LAPBANG OPS SAR ${l.hari_ke}`}
                            {isPenutupan && (
                              <span className="px-2 py-0.5 text-[10px] font-bold bg-red-600 text-white rounded uppercase">
                                SELESAI
                              </span>
                            )}
                          </h3>
                          <p className="text-xs text-slate-500 dark:text-slate-400">
                            {formatTanggalIndo(l.waktu_laporan)} • Pukul {formatJamWITA(l.waktu_laporan)} WITA
                          </p>
                        </div>
                      </div>

                      {currentUser?.role !== 'USER' && (
                        <button
                          type="button"
                          onClick={() => handleDeleteSitrep(l.id_laporan, l.hari_ke, isPenutupan)}
                          title="Hapus Laporan"
                          className={`inline-flex items-center justify-center p-2 rounded-lg border transition-all active:scale-95 cursor-pointer shadow-sm ${
                            isDarkMode
                              ? 'bg-slate-800/80 hover:bg-red-500/20 text-slate-400 hover:text-red-400 border-slate-700/60'
                              : 'bg-slate-100 hover:bg-red-50 text-slate-500 hover:text-red-600 border-slate-200'
                          }`}
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      )}
                    </div>

                    <div className={`p-3 rounded-xl text-xs leading-relaxed font-sans select-text border ${
                      isDarkMode
                        ? 'bg-slate-950/70 border-slate-800 text-slate-200'
                        : 'bg-slate-50 border-slate-200 text-slate-800'
                    }`}>
                      {l.isi_laporan}
                    </div>

                    <div className="flex items-center justify-end gap-2 pt-1">
                      <button
                        onClick={() => {
                          setEditingSitrep(l);
                          setShowSitrepModal(true);
                        }}
                        className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                          isDarkMode
                            ? 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-400 border-amber-500/30'
                            : 'bg-amber-50 hover:bg-amber-100 text-amber-700 border-amber-200'
                        }`}
                        title={isPenutupan ? 'Edit Laporan Penutupan' : 'Edit Laporan Perkembangan'}
                      >
                        <Edit className="w-3.5 h-3.5" />
                        <span>{isPenutupan ? 'Edit Penutupan' : 'Edit Lapbang'}</span>
                      </button>

                      <button
                        onClick={() => handleCopyText(`sitrep-${l.id_laporan}`, l.full_text_wa)}
                        className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors ${
                          isDarkMode
                            ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                            : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                        }`}
                      >
                        {copiedId === `sitrep-${l.id_laporan}` ? (
                          <Check className="w-3.5 h-3.5 text-emerald-500" />
                        ) : (
                          <Copy className="w-3.5 h-3.5" />
                        )}
                        <span>{copiedId === `sitrep-${l.id_laporan}` ? 'Tersalin!' : 'Salin WA'}</span>
                      </button>

                      <button
                        onClick={() =>
                          setViewModalData({
                            isOpen: true,
                            title: isPenutupan
                              ? `LAPBANG OPS SAR ${l.hari_ke.toUpperCase()} DAN PENUTUPAN`
                              : `LAPBANG OPS SAR ${l.hari_ke.toUpperCase()}`,
                            subtitle: operasi.nama_operasi,
                            text: l.full_text_wa,
                          })
                        }
                        className={`inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-lg text-xs font-semibold shadow-md transition-colors`}
                      >
                        <Eye className="w-3.5 h-3.5" />
                        <span>Lihat Format WA</span>
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Tab 3: Data Korban & Kronologi */}
      {activeTab === 'korban' && (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* Victims List */}
          <div className={`lg:col-span-7 rounded-2xl p-5 border shadow-xl space-y-4 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <h3 className={`text-sm font-bold uppercase tracking-wider flex items-center gap-2 ${
              isDarkMode ? 'text-white' : 'text-slate-900'
            }`}>
              <Users className={`w-4 h-4 ${accentColors.text}`} />
              <span>Daftar Identitas Korban ({operasi.korban?.length || 0} Orang)</span>
            </h3>

            {(!operasi.korban || operasi.korban.length === 0) ? (
              <p className="text-xs text-slate-500 dark:text-slate-400">Tidak ada data korban.</p>
            ) : (
              <div className={`divide-y ${isDarkMode ? 'divide-slate-800' : 'divide-slate-100'}`}>
                {operasi.korban.map((k, idx) => (
                  <div key={k.id_korban} className="py-3 flex items-start justify-between text-xs">
                    <div className="space-y-1">
                      <div className="flex items-center gap-2">
                        <span className={`font-bold text-sm ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                          {idx + 1}. {k.nama_korban}
                        </span>
                        <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${
                          k.status_korban === 'S'
                            ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30'
                            : k.status_korban === 'MD'
                            ? 'bg-red-500/20 text-red-600 dark:text-red-400 border border-red-500/30'
                            : k.status_korban === 'DP'
                            ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30'
                            : 'bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300'
                        }`}>
                          {getLabelStatusKorban(k.status_korban)}
                        </span>
                      </div>
                      <p className="text-slate-500 dark:text-slate-400">
                        Umur: {k.umur || '-'} • JK: {k.jk || '-'}
                      </p>
                      <p className="text-slate-500 dark:text-slate-400">Alamat: {k.alamat || '-'}</p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Kronologi & Meta */}
          <div className="lg:col-span-5 space-y-4">
            <div className={`rounded-2xl p-5 border shadow-xl space-y-3 ${
              isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              <h3 className={`text-sm font-bold uppercase tracking-wider flex items-center gap-2 ${
                isDarkMode ? 'text-white' : 'text-slate-900'
              }`}>
                <FileText className={`w-4 h-4 ${accentColors.text}`} />
                <span>Kronologi Kejadian</span>
              </h3>
              <p className={`text-xs leading-relaxed whitespace-pre-wrap p-3.5 rounded-xl border ${
                isDarkMode ? 'bg-slate-950/60 border-slate-800 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'
              }`}>
                {operasi.kronologi || '-'}
              </p>
            </div>

            <div className={`rounded-2xl p-5 border shadow-xl space-y-3 text-xs ${
              isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              <h3 className={`text-sm font-bold uppercase tracking-wider flex items-center gap-2 ${
                isDarkMode ? 'text-white' : 'text-slate-900'
              }`}>
                <AlertCircle className={`w-4 h-4 ${accentColors.text}`} />
                <span>Informasi Pelapor & Cuaca</span>
              </h3>
              <div className={`space-y-1.5 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                <p><strong>Pelapor:</strong> {operasi.nama_pelapor} ({operasi.hubungan_pelapor})</p>
                <p><strong>No. HP:</strong> {operasi.no_telp_pelapor || '-'}</p>
                <p><strong>Cuaca:</strong> {operasi.cuaca || '-'}</p>
                <p><strong>Gelombang:</strong> {operasi.gelombang || '-'}</p>
                <p><strong>Angin:</strong> {operasi.angin || '-'}</p>
                <p><strong>Faktor Penghambat:</strong> {operasi.faktor_penghambat || 'Nihil'}</p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Tab 4: Kekuatan Unsur & Alut Terkini */}
      {activeTab === 'kekuatan' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Unsur */}
          <div className={`rounded-2xl p-5 border shadow-xl space-y-4 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <h3 className={`text-sm font-bold uppercase tracking-wider flex items-center gap-2 ${
              isDarkMode ? 'text-white' : 'text-slate-900'
            }`}>
              <Users className={`w-4 h-4 ${accentColors.text}`} />
              <span>Unsur SAR Terlibat</span>
            </h3>

            <div className={`divide-y text-xs ${isDarkMode ? 'divide-slate-800' : 'divide-slate-100'}`}>
              {(operasi.detail_unsur || []).map((u) => (
                <div key={u.id_detail_unsur} className="py-2.5 flex items-center justify-between">
                  <span className={`font-medium ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>• {u.nama_unsur}</span>
                  <span className={`px-2 py-0.5 rounded font-mono font-semibold ${
                    isDarkMode ? 'bg-slate-800 text-slate-300' : 'bg-slate-100 text-slate-700'
                  }`}>
                    {u.jumlah_personil} Org
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Alut & Palsar */}
          <div className={`rounded-2xl p-5 border shadow-xl space-y-4 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
          }`}>
            <h3 className={`text-sm font-bold uppercase tracking-wider flex items-center gap-2 ${
              isDarkMode ? 'text-white' : 'text-slate-900'
            }`}>
              <Ship className={`w-4 h-4 ${accentColors.text}`} />
              <span>Alut & Peralatan SAR (Palsar)</span>
            </h3>

            <div className={`divide-y text-xs ${isDarkMode ? 'divide-slate-800' : 'divide-slate-100'}`}>
              {(operasi.detail_alut || []).map((a) => (
                <div key={a.id_detail_alut} className="py-2.5 flex items-center justify-between">
                  <span className={`font-medium ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>• {a.nama_alut}</span>
                  <span className={`px-2 py-0.5 rounded font-mono font-semibold ${
                    isDarkMode ? 'bg-slate-800 text-orange-400' : 'bg-slate-100 text-orange-600'
                  }`}>
                    {a.jumlah_alut} {a.satuan}
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Renops Modal */}
      <FormRenopsModal
        isOpen={showRenopsModal}
        operasi={operasi}
        masterAlut={masterAlut}
        editRenops={editingRenops}
        onClose={() => {
          setShowRenopsModal(false);
          setEditingRenops(null);
        }}
        onSuccess={async () => {
          await onRefreshOperasi(operasi.id_operasi);
        }}
      />

      {/* Sitrep Modal */}
      <FormSitrepModal
        isOpen={showSitrepModal}
        operasi={operasi}
        masterAlut={masterAlut}
        editSitrep={editingSitrep}
        onClose={() => {
          setShowSitrepModal(false);
          setEditingSitrep(null);
        }}
        onSuccess={async () => {
          await onRefreshOperasi(operasi.id_operasi);
        }}
      />

      {/* Text View / Edit Modal */}
      <ViewLaporanModal
        isOpen={viewModalData.isOpen}
        title={viewModalData.title}
        subtitle={viewModalData.subtitle}
        initialText={viewModalData.text}
        onClose={() => setViewModalData({ ...viewModalData, isOpen: false })}
        onSaveText={viewModalData.onSave}
      />
    </div>
  );
};
