import React, { useState, useEffect } from 'react';
import {
  Database,
  Ship,
  Users,
  Building2,
  Plus,
  Trash2,
  Edit2,
  ArrowUp,
  ArrowDown,
  Search,
  Check,
  X,
  ArrowLeft,
  Save,
  Shield,
  Briefcase,
  Layers,
  MapPin,
  RefreshCw,
  Compass,
} from 'lucide-react';
import { MasterAlut, MasterTim, Pejabat } from '../types';
import { useTheme } from '../context/ThemeContext';
import { showSuccessAlert, showErrorAlert, showConfirmDialog, showToast } from '../utils/alerts';
import { SarUnitsManager } from './SarUnitsManager';

interface MasterDataPageProps {
  onBack: () => void;
  onRefreshData?: () => void;
  initialTab?: 'alut' | 'tim' | 'pejabat' | 'pangkalan';
}

export const MasterDataPage: React.FC<MasterDataPageProps> = ({
  onBack,
  onRefreshData,
  initialTab = 'alut',
}) => {
  const { isDarkMode } = useTheme();

  const [activeTab, setActiveTab] = useState<'alut' | 'tim' | 'pejabat' | 'pangkalan'>(initialTab);
  const [loading, setLoading] = useState<boolean>(true);

  // Master Alut State
  const [alutList, setAlutList] = useState<MasterAlut[]>([]);
  const [searchAlut, setSearchAlut] = useState<string>('');
  const [editingAlutId, setEditingAlutId] = useState<number | null>(null);
  const [editAlutNama, setEditAlutNama] = useState<string>('');
  const [editAlutSatuan, setEditAlutSatuan] = useState<string>('Unit');
  const [newAlutNama, setNewAlutNama] = useState<string>('');
  const [newAlutSatuan, setNewAlutSatuan] = useState<string>('Unit');

  // Master Tim State
  const [timList, setTimList] = useState<MasterTim[]>([]);
  const [searchTim, setSearchTim] = useState<string>('');
  const [editingTimId, setEditingTimId] = useState<number | null>(null);
  const [editTimNama, setEditTimNama] = useState<string>('');
  const [newTimNama, setNewTimNama] = useState<string>('');

  // Pejabat State
  const [pejabatList, setPejabatList] = useState<Pejabat[]>([]);
  const [editingPejabatId, setEditingPejabatId] = useState<string | null>(null);
  const [pejabatForm, setPejabatForm] = useState<Omit<Pejabat, 'id'>>({
    nama: '',
    nip: '',
    pangkat: '',
    jabatan: '',
    is_default_smc: false,
  });
  const [showAddPejabat, setShowAddPejabat] = useState<boolean>(false);

  // Fetch all master data
  const loadAllData = async () => {
    try {
      setLoading(true);
      const timestamp = Date.now();
      const fetchOpts: RequestInit = {
        cache: 'no-store',
        headers: {
          'Pragma': 'no-cache',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
        },
      };

      const [alutRes, timRes, settingsRes] = await Promise.all([
        fetch(`/api/master/alut?_t=${timestamp}`, fetchOpts),
        fetch(`/api/master/tim?_t=${timestamp}`, fetchOpts),
        fetch(`/api/settings?_t=${timestamp}`, fetchOpts),
      ]);

      const [alutData, timData, settingsData] = await Promise.all([
        alutRes.json(),
        timRes.json(),
        settingsRes.json(),
      ]);

      if (alutData.success) setAlutList(alutData.data || []);
      if (timData.success) setTimList(timData.data || []);
      if (settingsData.success && settingsData.data) {
        setPejabatList(settingsData.data.pejabat_list || []);
      }
    } catch (err) {
      console.error('Failed to load master data:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadAllData();
  }, []);

  // --- MASTER ALUT HANDLERS ---
  const handleAddAlut = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAlutNama.trim()) return;

    try {
      const res = await fetch('/api/master/alut', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_alut: newAlutNama.trim(), satuan: newAlutSatuan }),
      });
      const data = await res.json();
      if (data.success) {
        setAlutList((prev) => [...prev, data.data]);
        setNewAlutNama('');
        setNewAlutSatuan('Unit');
        showSuccessAlert('Alut Berhasil Ditambahkan', `"${data.data.nama_alut}" telah terdaftar di Master Data.`);
        if (onRefreshData) onRefreshData();
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menambah Alut', err.message);
    }
  };

  const handleStartEditAlut = (item: MasterAlut) => {
    setEditingAlutId(item.id_alut);
    setEditAlutNama(item.nama_alut);
    setEditAlutSatuan(item.satuan);
  };

  const handleSaveEditAlut = async (id: number) => {
    if (!editAlutNama.trim()) return;
    try {
      const res = await fetch(`/api/master/alut/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_alut: editAlutNama.trim(), satuan: editAlutSatuan }),
      });
      const data = await res.json();
      if (data.success) {
        setAlutList((prev) => prev.map((a) => (a.id_alut === id ? data.data : a)));
        setEditingAlutId(null);
        showSuccessAlert('Alut Diperbarui', `Perubahan pada "${data.data.nama_alut}" berhasil disimpan.`);
        if (onRefreshData) onRefreshData();
      }
    } catch (err: any) {
      showErrorAlert('Gagal Mengubah Alut', err.message);
    }
  };

  const handleDeleteAlut = async (item: MasterAlut) => {
    const ok = await showConfirmDialog(
      `Hapus Alut "${item.nama_alut}"?`,
      `Apakah Anda yakin ingin menghapus alut "${item.nama_alut}" dari Master Data? Tindakan ini tidak dapat dibatalkan.`
    );
    if (!ok) return;

    try {
      const res = await fetch(`/api/master/alut/${item.id_alut}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.success) {
        setAlutList((prev) => prev.filter((a) => a.id_alut !== item.id_alut));
        showSuccessAlert('Alut Dihapus', `"${item.nama_alut}" berhasil dihapus dari Master Data.`);
        if (onRefreshData) onRefreshData();
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menghapus Alut', err.message);
    }
  };

  const handleMoveAlut = async (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= alutList.length) return;

    const updated = [...alutList];
    const [moved] = updated.splice(index, 1);
    updated.splice(targetIdx, 0, moved);
    setAlutList(updated);

    try {
      await fetch('/api/master/alut/reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: updated.map((a) => a.id_alut) }),
      });
      if (onRefreshData) onRefreshData();
    } catch (err) {
      console.error('Failed to save alut reorder:', err);
    }
  };

  // --- MASTER TIM HANDLERS ---
  const handleAddTim = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTimNama.trim()) return;

    try {
      const res = await fetch('/api/master/tim', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_tim: newTimNama.trim() }),
      });
      const data = await res.json();
      if (data.success) {
        setTimList((prev) => [...prev, data.data]);
        setNewTimNama('');
        showSuccessAlert('Tim Ditambahkan', `"${data.data.nama_tim}" telah ditambahkan ke Master Tim.`);
        if (onRefreshData) onRefreshData();
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menambah Tim', err.message);
    }
  };

  const handleStartEditTim = (item: MasterTim) => {
    setEditingTimId(item.id_tim);
    setEditTimNama(item.nama_tim);
  };

  const handleSaveEditTim = async (id: number) => {
    if (!editTimNama.trim()) return;
    try {
      const res = await fetch(`/api/master/tim/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_tim: editTimNama.trim() }),
      });
      const data = await res.json();
      if (data.success) {
        setTimList((prev) => prev.map((t) => (t.id_tim === id ? data.data : t)));
        setEditingTimId(null);
        showSuccessAlert('Tim Diperbarui', `Perubahan pada "${data.data.nama_tim}" berhasil disimpan.`);
        if (onRefreshData) onRefreshData();
      }
    } catch (err: any) {
      showErrorAlert('Gagal Mengubah Tim', err.message);
    }
  };

  const handleDeleteTim = async (item: MasterTim) => {
    const ok = await showConfirmDialog(
      `Hapus Tim "${item.nama_tim}"?`,
      `Apakah Anda yakin ingin menghapus tim "${item.nama_tim}" dari Master Data? Tindakan ini tidak dapat dibatalkan.`
    );
    if (!ok) return;

    try {
      const res = await fetch(`/api/master/tim/${item.id_tim}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.success) {
        setTimList((prev) => prev.filter((t) => t.id_tim !== item.id_tim));
        showSuccessAlert('Tim Dihapus', `"${item.nama_tim}" berhasil dihapus dari Master Data.`);
        if (onRefreshData) onRefreshData();
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menghapus Tim', err.message);
    }
  };

  const handleMoveTim = async (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= timList.length) return;

    const updated = [...timList];
    const [moved] = updated.splice(index, 1);
    updated.splice(targetIdx, 0, moved);
    setTimList(updated);

    try {
      await fetch('/api/master/tim/reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: updated.map((t) => t.id_tim) }),
      });
      if (onRefreshData) onRefreshData();
    } catch (err) {
      console.error('Failed to save tim reorder:', err);
    }
  };

  // --- PEJABAT HANDLERS ---
  const handleSaveNewPejabat = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pejabatForm.nama.trim() || !pejabatForm.jabatan.trim()) return;

    try {
      const res = await fetch('/api/settings/pejabat', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(pejabatForm),
      });
      const data = await res.json();
      if (data.success) {
        setPejabatList((prev) => [...prev, data.data]);
        setShowAddPejabat(false);
        setPejabatForm({
          nama: '',
          nip: '',
          pangkat: '',
          jabatan: '',
          is_default_smc: false,
        });
        showSuccessAlert('Pejabat Ditambahkan', `"${data.data.nama}" telah berhasil ditambahkan ke daftar pejabat.`);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menambah Pejabat', err.message);
    }
  };

  const handleDeletePejabat = async (p: Pejabat) => {
    const ok = await showConfirmDialog(
      `Hapus Pejabat "${p.nama}"?`,
      `Apakah Anda yakin ingin menghapus data pejabat "${p.nama}"? Data ini tidak akan lagi muncul pada opsi pilihan pengirim BRA SAR.`
    );
    if (!ok) return;

    try {
      const res = await fetch(`/api/settings/pejabat/${p.id}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.success) {
        setPejabatList((prev) => prev.filter((item) => item.id !== p.id));
        showSuccessAlert('Pejabat Dihapus', `Data "${p.nama}" berhasil dihapus.`);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menghapus Pejabat', err.message);
    }
  };

  // Filtered lists
  const filteredAlut = alutList.filter((a) =>
    a.nama_alut.toLowerCase().includes(searchAlut.toLowerCase())
  );
  const filteredTim = timList.filter((t) =>
    t.nama_tim.toLowerCase().includes(searchTim.toLowerCase())
  );

  return (
    <div className={`min-h-screen transition-colors duration-200 pb-16 ${
      isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'
    }`}>
      {/* Top Header Bar */}
      <div className={`border-b sticky top-14 z-20 backdrop-blur-md transition-colors ${
        isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white/95 border-slate-200 shadow-xs'
      }`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 py-3.5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={onBack}
                className={`p-2 rounded-xl border transition-colors cursor-pointer shrink-0 ${
                  isDarkMode
                    ? 'border-slate-800 hover:bg-slate-800 text-slate-400 hover:text-white'
                    : 'border-slate-200 hover:bg-slate-100 text-slate-600 hover:text-slate-900'
                }`}
                title="Kembali"
              >
                <ArrowLeft className="w-5 h-5" />
              </button>
              <div>
                <div className="flex items-center gap-2">
                  <Database className="w-5 h-5 text-orange-500" />
                  <span className="font-extrabold text-base sm:text-lg tracking-wide uppercase">
                    Master Data Operasional SAR
                  </span>
                  <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30">
                    DATABASE MASTER
                  </span>
                </div>
                <p className="text-[11px] text-slate-400 font-medium">
                  Kelola master armada Alut, regu Tim SAR, data pejabat berwenang SMC, dan pangkalan satuan SAR daerah
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2 self-end sm:self-auto">
              <button
                type="button"
                onClick={loadAllData}
                disabled={loading}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                  isDarkMode
                    ? 'bg-slate-800/80 hover:bg-slate-800 text-slate-300 border-slate-700'
                    : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200 shadow-xs'
                }`}
                title="Muat ulang master data"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin text-orange-500' : ''}`} />
                <span>Segarkan</span>
              </button>
            </div>
          </div>

          {/* Master Tabs Switcher */}
          <div className="flex items-center gap-2 mt-4 overflow-x-auto no-scrollbar border-t border-slate-100 dark:border-slate-800/80 pt-3">
            <button
              type="button"
              onClick={() => setActiveTab('alut')}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 ${
                activeTab === 'alut'
                  ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25'
                  : isDarkMode
                  ? 'bg-slate-800/60 text-slate-300 hover:bg-slate-800 border border-slate-700/60'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <Ship className="w-4 h-4" />
              <span>Master Alut & Palsar ({alutList.length})</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('tim')}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 ${
                activeTab === 'tim'
                  ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25'
                  : isDarkMode
                  ? 'bg-slate-800/60 text-slate-300 hover:bg-slate-800 border border-slate-700/60'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <Users className="w-4 h-4" />
              <span>Master Tim / SRU ({timList.length})</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('pejabat')}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 ${
                activeTab === 'pejabat'
                  ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25'
                  : isDarkMode
                  ? 'bg-slate-800/60 text-slate-300 hover:bg-slate-800 border border-slate-700/60'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <Briefcase className="w-4 h-4" />
              <span>Pejabat & SMC ({pejabatList.length})</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('pangkalan')}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 ${
                activeTab === 'pangkalan'
                  ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25'
                  : isDarkMode
                  ? 'bg-slate-800/60 text-slate-300 hover:bg-slate-800 border border-slate-700/60'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <MapPin className="w-4 h-4" />
              <span>Pangkalan & Pos SAR Daerah</span>
            </button>
          </div>
        </div>
      </div>

      {/* Main Content Area */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
        {loading ? (
          <div className="py-20 flex flex-col items-center justify-center gap-3">
            <div className="w-8 h-8 border-3 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
            <p className="text-xs text-slate-400">Memuat data master...</p>
          </div>
        ) : activeTab === 'alut' ? (
          /* ================= TAB 1: MASTER ALUT ================= */
          <div className="space-y-4">
            {/* Header Action Bar */}
            <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="relative w-full sm:w-80">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Cari alut atau palsar SAR..."
                  value={searchAlut}
                  onChange={(e) => setSearchAlut(e.target.value)}
                  className={`w-full pl-9 pr-3 py-2 text-xs rounded-xl border transition-colors outline-none focus:border-orange-500 ${
                    isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />
              </div>

              {/* Form Tambah Alut Baru */}
              <form onSubmit={handleAddAlut} className="flex items-center gap-2 w-full sm:w-auto">
                <input
                  type="text"
                  placeholder="Nama alut baru (misal: KN SAR Pacitan)"
                  value={newAlutNama}
                  onChange={(e) => setNewAlutNama(e.target.value)}
                  className={`flex-1 sm:w-64 px-3 py-2 text-xs rounded-xl border transition-colors outline-none focus:border-orange-500 ${
                    isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />
                <select
                  value={newAlutSatuan}
                  onChange={(e) => setNewAlutSatuan(e.target.value)}
                  className={`px-3 py-2 text-xs rounded-xl border transition-colors outline-none ${
                    isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                >
                  <option value="Unit">Unit</option>
                  <option value="Set">Set</option>
                  <option value="Buah">Buah</option>
                  <option value="Pcs">Pcs</option>
                  <option value="Pasang">Pasang</option>
                  <option value="Paket">Paket</option>
                </select>
                <button
                  type="submit"
                  disabled={!newAlutNama.trim()}
                  className="px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-md shadow-orange-600/20 transition-all disabled:opacity-50 cursor-pointer shrink-0"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Tambah</span>
                </button>
              </form>
            </div>

            {/* Alut Table */}
            <div className={`rounded-2xl border overflow-hidden ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className={`text-[11px] uppercase tracking-wider font-bold border-b ${
                    isDarkMode ? 'bg-slate-800/50 text-slate-400 border-slate-800' : 'bg-slate-50 text-slate-600 border-slate-200'
                  }`}>
                    <tr>
                      <th className="px-4 py-3 w-16 text-center">Urutan</th>
                      <th className="px-4 py-3">Nama Alat Utama / Palsar</th>
                      <th className="px-4 py-3 w-32">Satuan</th>
                      <th className="px-4 py-3 w-32 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-inherit">
                    {filteredAlut.length === 0 ? (
                      <tr>
                        <td colSpan={4} className="px-4 py-8 text-center text-slate-400">
                          Tidak ada alut yang sesuai pencarian.
                        </td>
                      </tr>
                    ) : (
                      filteredAlut.map((alut, idx) => {
                        const isEditing = editingAlutId === alut.id_alut;
                        return (
                          <tr key={alut.id_alut} className="hover:bg-slate-500/5 transition-colors">
                            <td className="px-4 py-3 text-center">
                              <div className="flex items-center justify-center gap-1">
                                <button
                                  type="button"
                                  onClick={() => handleMoveAlut(idx, 'up')}
                                  disabled={idx === 0}
                                  className="p-1 rounded hover:bg-slate-700 text-slate-400 hover:text-white disabled:opacity-20 cursor-pointer"
                                  title="Pindah ke Atas"
                                >
                                  <ArrowUp className="w-3 h-3" />
                                </button>
                                <span className="font-mono text-slate-400 text-[11px] w-6 text-center">{idx + 1}</span>
                                <button
                                  type="button"
                                  onClick={() => handleMoveAlut(idx, 'down')}
                                  disabled={idx === filteredAlut.length - 1}
                                  className="p-1 rounded hover:bg-slate-700 text-slate-400 hover:text-white disabled:opacity-20 cursor-pointer"
                                  title="Pindah ke Bawah"
                                >
                                  <ArrowDown className="w-3 h-3" />
                                </button>
                              </div>
                            </td>
                            <td className="px-4 py-3 font-semibold">
                              {isEditing ? (
                                <input
                                  type="text"
                                  value={editAlutNama}
                                  onChange={(e) => setEditAlutNama(e.target.value)}
                                  className={`w-full px-2 py-1 rounded-lg border text-xs outline-none ${
                                    isDarkMode ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                                  }`}
                                />
                              ) : (
                                <span>{alut.nama_alut}</span>
                              )}
                            </td>
                            <td className="px-4 py-3">
                              {isEditing ? (
                                <input
                                  type="text"
                                  value={editAlutSatuan}
                                  onChange={(e) => setEditAlutSatuan(e.target.value)}
                                  className={`w-24 px-2 py-1 rounded-lg border text-xs outline-none ${
                                    isDarkMode ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                                  }`}
                                />
                              ) : (
                                <span className="px-2 py-0.5 rounded-full bg-slate-500/10 text-slate-400 font-mono text-[11px]">
                                  {alut.satuan}
                                </span>
                              )}
                            </td>
                            <td className="px-4 py-3 text-right">
                              {isEditing ? (
                                <div className="flex items-center justify-end gap-1.5">
                                  <button
                                    type="button"
                                    onClick={() => handleSaveEditAlut(alut.id_alut)}
                                    className="p-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors cursor-pointer"
                                    title="Simpan"
                                  >
                                    <Check className="w-3.5 h-3.5" />
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => setEditingAlutId(null)}
                                    className="p-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors cursor-pointer"
                                    title="Batal"
                                  >
                                    <X className="w-3.5 h-3.5" />
                                  </button>
                                </div>
                              ) : (
                                <div className="flex items-center justify-end gap-1.5">
                                  <button
                                    type="button"
                                    onClick={() => handleStartEditAlut(alut)}
                                    className="p-1.5 text-slate-400 hover:text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors cursor-pointer"
                                    title="Ubah Alut"
                                  >
                                    <Edit2 className="w-3.5 h-3.5" />
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => handleDeleteAlut(alut)}
                                    className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors cursor-pointer"
                                    title="Hapus Alut"
                                  >
                                    <Trash2 className="w-3.5 h-3.5" />
                                  </button>
                                </div>
                              )}
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        ) : activeTab === 'tim' ? (
          /* ================= TAB 2: MASTER TIM ================= */
          <div className="space-y-4">
            <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="relative w-full sm:w-80">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Cari tim SAR atau regu..."
                  value={searchTim}
                  onChange={(e) => setSearchTim(e.target.value)}
                  className={`w-full pl-9 pr-3 py-2 text-xs rounded-xl border transition-colors outline-none focus:border-orange-500 ${
                    isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />
              </div>

              <form onSubmit={handleAddTim} className="flex items-center gap-2 w-full sm:w-auto">
                <input
                  type="text"
                  placeholder="Nama tim baru (misal: Tim Rescue Pos SAR Kolaka)"
                  value={newTimNama}
                  onChange={(e) => setNewTimNama(e.target.value)}
                  className={`flex-1 sm:w-72 px-3 py-2 text-xs rounded-xl border transition-colors outline-none focus:border-orange-500 ${
                    isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />
                <button
                  type="submit"
                  disabled={!newTimNama.trim()}
                  className="px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow-md shadow-orange-600/20 transition-all disabled:opacity-50 cursor-pointer shrink-0"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Tambah Tim</span>
                </button>
              </form>
            </div>

            <div className={`rounded-2xl border overflow-hidden ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="overflow-x-auto">
                <table className="w-full text-xs text-left">
                  <thead className={`text-[11px] uppercase tracking-wider font-bold border-b ${
                    isDarkMode ? 'bg-slate-800/50 text-slate-400 border-slate-800' : 'bg-slate-50 text-slate-600 border-slate-200'
                  }`}>
                    <tr>
                      <th className="px-4 py-3 w-16 text-center">Urutan</th>
                      <th className="px-4 py-3">Nama Tim / SRU SAR</th>
                      <th className="px-4 py-3 w-32 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-inherit">
                    {filteredTim.length === 0 ? (
                      <tr>
                        <td colSpan={3} className="px-4 py-8 text-center text-slate-400">
                          Tidak ada tim yang sesuai pencarian.
                        </td>
                      </tr>
                    ) : (
                      filteredTim.map((tim, idx) => {
                        const isEditing = editingTimId === tim.id_tim;
                        return (
                          <tr key={tim.id_tim} className="hover:bg-slate-500/5 transition-colors">
                            <td className="px-4 py-3 text-center">
                              <div className="flex items-center justify-center gap-1">
                                <button
                                  type="button"
                                  onClick={() => handleMoveTim(idx, 'up')}
                                  disabled={idx === 0}
                                  className="p-1 rounded hover:bg-slate-700 text-slate-400 hover:text-white disabled:opacity-20 cursor-pointer"
                                  title="Pindah ke Atas"
                                >
                                  <ArrowUp className="w-3 h-3" />
                                </button>
                                <span className="font-mono text-slate-400 text-[11px] w-6 text-center">{idx + 1}</span>
                                <button
                                  type="button"
                                  onClick={() => handleMoveTim(idx, 'down')}
                                  disabled={idx === filteredTim.length - 1}
                                  className="p-1 rounded hover:bg-slate-700 text-slate-400 hover:text-white disabled:opacity-20 cursor-pointer"
                                  title="Pindah ke Bawah"
                                >
                                  <ArrowDown className="w-3 h-3" />
                                </button>
                              </div>
                            </td>
                            <td className="px-4 py-3 font-semibold">
                              {isEditing ? (
                                <input
                                  type="text"
                                  value={editTimNama}
                                  onChange={(e) => setEditTimNama(e.target.value)}
                                  className={`w-full px-2 py-1 rounded-lg border text-xs outline-none ${
                                    isDarkMode ? 'bg-slate-800 border-slate-700 text-white' : 'bg-white border-slate-300'
                                  }`}
                                />
                              ) : (
                                <span>{tim.nama_tim}</span>
                              )}
                            </td>
                            <td className="px-4 py-3 text-right">
                              {isEditing ? (
                                <div className="flex items-center justify-end gap-1.5">
                                  <button
                                    type="button"
                                    onClick={() => handleSaveEditTim(tim.id_tim)}
                                    className="p-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors cursor-pointer"
                                    title="Simpan"
                                  >
                                    <Check className="w-3.5 h-3.5" />
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => setEditingTimId(null)}
                                    className="p-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors cursor-pointer"
                                    title="Batal"
                                  >
                                    <X className="w-3.5 h-3.5" />
                                  </button>
                                </div>
                              ) : (
                                <div className="flex items-center justify-end gap-1.5">
                                  <button
                                    type="button"
                                    onClick={() => handleStartEditTim(tim)}
                                    className="p-1.5 text-slate-400 hover:text-blue-400 hover:bg-blue-500/10 rounded-lg transition-colors cursor-pointer"
                                    title="Ubah Tim"
                                  >
                                    <Edit2 className="w-3.5 h-3.5" />
                                  </button>
                                  <button
                                    type="button"
                                    onClick={() => handleDeleteTim(tim)}
                                    className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors cursor-pointer"
                                    title="Hapus Tim"
                                  >
                                    <Trash2 className="w-3.5 h-3.5" />
                                  </button>
                                </div>
                              )}
                            </td>
                          </tr>
                        );
                      })
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        ) : activeTab === 'pejabat' ? (
          /* ================= TAB 3: DATA PEJABAT & SMC ================= */
          <div className="space-y-4">
            <div className={`p-5 rounded-2xl border space-y-4 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center justify-between pb-3 border-b border-inherit">
                <div className="flex items-center gap-2">
                  <Shield className="w-4 h-4 text-orange-500" />
                  <div>
                    <h2 className="text-sm font-bold uppercase tracking-wider">
                      Daftar Pejabat Penandatangan & SMC Berwenang
                    </h2>
                    <p className="text-[11px] text-slate-400">
                      Pejabat ini menjadi rujukan otomatis saat membuat dan menandatangani dokumen BRA SAR telegram
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={() => setShowAddPejabat(!showAddPejabat)}
                  className="px-3.5 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow cursor-pointer"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>{showAddPejabat ? 'Tutup Form' : 'Tambah Pejabat'}</span>
                </button>
              </div>

              {/* Form Tambah Pejabat Baru */}
              {showAddPejabat && (
                <form onSubmit={handleSaveNewPejabat} className={`p-4 rounded-xl border space-y-3 ${
                  isDarkMode ? 'bg-slate-950/80 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="text-xs font-bold text-orange-400 uppercase tracking-wider">
                    + Formulir Pejabat Penandatangan Baru
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                      <label className="block text-xs text-slate-400 mb-1">Nama Lengkap & Gelar *</label>
                      <input
                        type="text"
                        required
                        placeholder="AMIRUDDIN AS, S.Sos."
                        value={pejabatForm.nama}
                        onChange={(e) => setPejabatForm({ ...pejabatForm, nama: e.target.value })}
                        className={`w-full text-xs px-3 py-2 rounded-xl border ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs text-slate-400 mb-1">NIP / NRP</label>
                      <input
                        type="text"
                        placeholder="197405101998031002"
                        value={pejabatForm.nip}
                        onChange={(e) => setPejabatForm({ ...pejabatForm, nip: e.target.value })}
                        className={`w-full text-xs px-3 py-2 rounded-xl border ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs text-slate-400 mb-1">Pangkat / Golongan</label>
                      <input
                        type="text"
                        placeholder="PEMBINA (IV/a)"
                        value={pejabatForm.pangkat}
                        onChange={(e) => setPejabatForm({ ...pejabatForm, pangkat: e.target.value })}
                        className={`w-full text-xs px-3 py-2 rounded-xl border ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs text-slate-400 mb-1">Jabatan Kedinasan *</label>
                      <input
                        type="text"
                        required
                        placeholder="Kepala Kantor / Kasie Ops / SMC"
                        value={pejabatForm.jabatan}
                        onChange={(e) => setPejabatForm({ ...pejabatForm, jabatan: e.target.value })}
                        className={`w-full text-xs px-3 py-2 rounded-xl border ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>
                  </div>

                  <div className="flex items-center justify-between pt-2">
                    <label className="flex items-center gap-2 cursor-pointer text-xs text-slate-300">
                      <input
                        type="checkbox"
                        checked={pejabatForm.is_default_smc}
                        onChange={(e) => setPejabatForm({ ...pejabatForm, is_default_smc: e.target.checked })}
                        className="rounded border-slate-700 text-orange-500 focus:ring-orange-500"
                      />
                      <span>Jadikan Pejabat Pilihan Utama SMC (Default SMC)</span>
                    </label>

                    <div className="flex gap-2">
                      <button
                        type="button"
                        onClick={() => setShowAddPejabat(false)}
                        className="px-3 py-1.5 text-xs text-slate-400 hover:text-white cursor-pointer"
                      >
                        Batal
                      </button>
                      <button
                        type="submit"
                        className="px-4 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-semibold cursor-pointer"
                      >
                        Simpan Pejabat
                      </button>
                    </div>
                  </div>
                </form>
              )}

              {/* List of Pejabat Cards */}
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                {pejabatList.map((p) => (
                  <div
                    key={p.id}
                    className={`p-4 rounded-xl border flex flex-col justify-between space-y-3 transition-all ${
                      isDarkMode
                        ? 'bg-slate-950/80 border-slate-800 hover:border-slate-700'
                        : 'bg-slate-50 border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div className="space-y-1.5">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <h4 className="text-xs font-bold">{p.nama}</h4>
                          <div className="text-[11px] text-amber-500 font-medium">{p.jabatan}</div>
                        </div>
                        {p.is_default_smc && (
                          <span className="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-orange-500/20 text-orange-400 border border-orange-500/30">
                            Default SMC
                          </span>
                        )}
                      </div>

                      <div className="text-[11px] text-slate-400 space-y-0.5 pt-1 border-t border-inherit font-mono">
                        <div>NIP: {p.nip || '-'}</div>
                        <div>Pangkat: {p.pangkat || '-'}</div>
                      </div>
                    </div>

                    <div className="flex items-center justify-end gap-1.5 pt-2 border-t border-inherit">
                      <button
                        type="button"
                        onClick={() => handleDeletePejabat(p)}
                        className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors cursor-pointer"
                        title="Hapus Pejabat"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        ) : (
          /* ================= TAB 4: PANGKALAN & POS SAR DAERAH ================= */
          <div className="space-y-4">
            <SarUnitsManager />
          </div>
        )}
      </div>
    </div>
  );
};
