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
} from 'lucide-react';
import { MasterAlut, MasterTim, Pejabat, AppSettings } from '../types';
import { useTheme } from '../context/ThemeContext';
import { showSuccessAlert, showErrorAlert, showConfirmDialog } from '../utils/alerts';

interface MasterDataPageProps {
  onBack: () => void;
  onRefreshData?: () => void;
}

export const MasterDataPage: React.FC<MasterDataPageProps> = ({ onBack, onRefreshData }) => {
  const { isDarkMode, accentColors } = useTheme();

  const [activeTab, setActiveTab] = useState<'alut' | 'tim' | 'settings'>('alut');
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

  // Pejabat & Settings State
  const [pejabatList, setPejabatList] = useState<Pejabat[]>([]);
  const [satkerKode, setSatkerKode] = useState<string>('SAR-304');
  const [satkerNama, setSatkerNama] = useState<string>('KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI');
  const [editingPejabatId, setEditingPejabatId] = useState<string | null>(null);
  const [pejabatForm, setPejabatForm] = useState<Omit<Pejabat, 'id'>>({
    nama: '',
    nip: '',
    pangkat: '',
    jabatan: '',
    is_default_smc: false,
  });
  const [showAddPejabat, setShowAddPejabat] = useState<boolean>(false);

  // Fetch all master & settings data
  const loadAllData = async () => {
    try {
      setLoading(true);
      const [alutRes, timRes, settingsRes] = await Promise.all([
        fetch('/api/master/alut'),
        fetch('/api/master/tim'),
        fetch('/api/settings'),
      ]);

      const [alutData, timData, settingsData] = await Promise.all([
        alutRes.json(),
        timRes.json(),
        settingsRes.json(),
      ]);

      if (alutData.success) setAlutList(alutData.data);
      if (timData.success) setTimList(timData.data);
      if (settingsData.success && settingsData.data) {
        setSatkerKode(settingsData.data.satker_kode || 'SAR-304');
        setSatkerNama(settingsData.data.satker_nama || 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI');
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
      `Apakah Anda yakin ingin menghapus "${item.nama_alut}" dari Master Data?`
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
        showSuccessAlert('Tim Berhasil Ditambahkan', `"${data.data.nama_tim}" telah terdaftar di Master Data.`);
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
      `Apakah Anda yakin ingin menghapus "${item.nama_tim}" dari Master Data?`
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

  // --- PEJABAT & SETTINGS HANDLERS ---
  const handleSaveSatkerSettings = async () => {
    try {
      const res = await fetch('/api/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ satker_kode: satkerKode, satker_nama: satkerNama }),
      });
      const data = await res.json();
      if (data.success) {
        showSuccessAlert('Pengaturan Disimpan', 'Identitas Satuan Kerja berhasil diperbarui.');
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menyimpan Pengaturan', err.message);
    }
  };

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

  const handleUpdatePejabat = async (id: string, updatedFields: Partial<Pejabat>) => {
    try {
      const res = await fetch(`/api/settings/pejabat/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updatedFields),
      });
      const data = await res.json();
      if (data.success) {
        setPejabatList((prev) => prev.map((p) => (p.id === id ? data.data : p)));
        setEditingPejabatId(null);
        showSuccessAlert('Data Pejabat Diperbarui', `Data "${data.data.nama}" berhasil diperbarui.`);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Mengubah Pejabat', err.message);
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

  // Filters
  const filteredAlut = alutList.filter((a) =>
    a.nama_alut.toLowerCase().includes(searchAlut.toLowerCase())
  );
  const filteredTim = timList.filter((t) =>
    t.nama_tim.toLowerCase().includes(searchTim.toLowerCase())
  );

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      {/* Top Header Navigation */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-700/50">
        <div className="flex items-center gap-3">
          <button
            onClick={onBack}
            className={`p-2 rounded-xl border transition-all ${
              isDarkMode
                ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-200 shadow-sm'
            }`}
            title="Kembali ke Dashboard"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div>
            <div className="flex items-center gap-2">
              <Database className="w-5 h-5 text-orange-500" />
              <h1 className={`text-lg sm:text-xl font-bold tracking-tight ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                Manajemen Master Data & Pengaturan Aplikasi
              </h1>
            </div>
            <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
              Halaman khusus CRUD, Pengaturan Pejabat Penandatangan, dan Reorder Alut/Tim SAR
            </p>
          </div>
        </div>

        {/* Tab Switcher */}
        <div className={`flex items-center rounded-xl p-1 text-xs border self-start sm:self-auto ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-slate-100 border-slate-200'
        }`}>
          <button
            onClick={() => setActiveTab('alut')}
            className={`flex items-center gap-1.5 px-3 py-2 rounded-lg font-semibold transition-all ${
              activeTab === 'alut'
                ? 'bg-amber-600 text-white shadow-sm'
                : isDarkMode ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Ship className="w-3.5 h-3.5" />
            <span>Master Alut ({alutList.length})</span>
          </button>

          <button
            onClick={() => setActiveTab('tim')}
            className={`flex items-center gap-1.5 px-3 py-2 rounded-lg font-semibold transition-all ${
              activeTab === 'tim'
                ? 'bg-blue-600 text-white shadow-sm'
                : isDarkMode ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Users className="w-3.5 h-3.5" />
            <span>Master Tim ({timList.length})</span>
          </button>

          <button
            onClick={() => setActiveTab('settings')}
            className={`flex items-center gap-1.5 px-3 py-2 rounded-lg font-semibold transition-all ${
              activeTab === 'settings'
                ? 'bg-emerald-600 text-white shadow-sm'
                : isDarkMode ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Briefcase className="w-3.5 h-3.5" />
            <span>Data Pejabat & Settings</span>
          </button>
        </div>
      </div>

      {loading ? (
        <div className="py-16 flex flex-col items-center justify-center gap-3">
          <div className="w-8 h-8 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
          <p className="text-xs text-slate-400">Memuat Master Data...</p>
        </div>
      ) : activeTab === 'alut' ? (
        /* ================= TAB 1: MASTER ALUT ================= */
        <div className="space-y-4">
          {/* Header Action Bar */}
          <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="relative w-full sm:w-80">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                value={searchAlut}
                onChange={(e) => setSearchAlut(e.target.value)}
                placeholder="Cari alut / peralatan..."
                className={`w-full pl-9 pr-3 py-2 rounded-xl text-xs border outline-none ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                    : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
                }`}
              />
            </div>

            {/* Quick Add Alut */}
            <form onSubmit={handleAddAlut} className="flex flex-wrap items-center gap-2 w-full sm:w-auto">
              <input
                type="text"
                required
                value={newAlutNama}
                onChange={(e) => setNewAlutNama(e.target.value)}
                placeholder="Nama Alut Baru..."
                className={`flex-1 sm:w-56 text-xs px-3 py-2 rounded-xl border outline-none ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                    : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
                }`}
              />
              <select
                value={newAlutSatuan}
                onChange={(e) => setNewAlutSatuan(e.target.value)}
                className={`text-xs px-2.5 py-2 rounded-xl border font-medium ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-slate-300'
                    : 'bg-slate-50 border-slate-300 text-slate-800'
                }`}
              >
                <option value="Unit">Unit</option>
                <option value="Set">Set</option>
                <option value="Buah">Buah</option>
                <option value="Lembar">Lembar</option>
                <option value="Paket">Paket</option>
              </select>
              <button
                type="submit"
                className="px-4 py-2 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition-all active:scale-95"
              >
                <Plus className="w-4 h-4" />
                <span>Tambah Alut</span>
              </button>
            </form>
          </div>

          {/* Table List of Master Alut */}
          <div className={`rounded-2xl border overflow-hidden ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className={`border-b font-semibold uppercase tracking-wider ${
                    isDarkMode ? 'bg-slate-950 text-slate-400 border-slate-800' : 'bg-slate-50 text-slate-500 border-slate-200'
                  }`}>
                    <th className="p-3.5 w-16 text-center">Urutan</th>
                    <th className="p-3.5 w-12 text-center">No</th>
                    <th className="p-3.5">Nama Alut / Peralatan SAR</th>
                    <th className="p-3.5 w-32">Satuan</th>
                    <th className="p-3.5 w-32 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {filteredAlut.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="p-8 text-center text-slate-500">
                        Tidak ada data alut yang cocok dengan kata kunci.
                      </td>
                    </tr>
                  ) : (
                    filteredAlut.map((alut, idx) => {
                      const isEditing = editingAlutId === alut.id_alut;
                      return (
                        <tr
                          key={alut.id_alut}
                          className={`hover:bg-slate-800/20 transition-colors ${
                            isEditing ? (isDarkMode ? 'bg-slate-850' : 'bg-amber-50/50') : ''
                          }`}
                        >
                          {/* Reorder Buttons */}
                          <td className="p-3 text-center">
                            <div className="flex items-center justify-center gap-1">
                              <button
                                type="button"
                                disabled={idx === 0}
                                onClick={() => handleMoveAlut(idx, 'up')}
                                className="p-1 rounded text-slate-400 hover:text-amber-400 disabled:opacity-20 transition-colors"
                                title="Geser Urutan Naik"
                              >
                                <ArrowUp className="w-3.5 h-3.5" />
                              </button>
                              <button
                                type="button"
                                disabled={idx === alutList.length - 1}
                                onClick={() => handleMoveAlut(idx, 'down')}
                                className="p-1 rounded text-slate-400 hover:text-amber-400 disabled:opacity-20 transition-colors"
                                title="Geser Urutan Turun"
                              >
                                <ArrowDown className="w-3.5 h-3.5" />
                              </button>
                            </div>
                          </td>

                          <td className="p-3 text-center font-mono text-slate-500">
                            {idx + 1}
                          </td>

                          {/* Nama Alut */}
                          <td className="p-3 font-medium">
                            {isEditing ? (
                              <input
                                type="text"
                                value={editAlutNama}
                                onChange={(e) => setEditAlutNama(e.target.value)}
                                className={`w-full text-xs px-2.5 py-1.5 rounded-lg border font-medium ${
                                  isDarkMode
                                    ? 'bg-slate-950 border-slate-700 text-white'
                                    : 'bg-white border-slate-300 text-slate-900'
                                }`}
                              />
                            ) : (
                              <div className="flex items-center gap-2">
                                <Ship className="w-3.5 h-3.5 text-amber-500 shrink-0" />
                                <span className={isDarkMode ? 'text-slate-200' : 'text-slate-800'}>
                                  {alut.nama_alut}
                                </span>
                              </div>
                            )}
                          </td>

                          {/* Satuan */}
                          <td className="p-3">
                            {isEditing ? (
                              <select
                                value={editAlutSatuan}
                                onChange={(e) => setEditAlutSatuan(e.target.value)}
                                className={`text-xs px-2 py-1.5 rounded-lg border ${
                                  isDarkMode
                                    ? 'bg-slate-950 border-slate-700 text-slate-200'
                                    : 'bg-white border-slate-300 text-slate-800'
                                }`}
                              >
                                <option value="Unit">Unit</option>
                                <option value="Set">Set</option>
                                <option value="Buah">Buah</option>
                                <option value="Lembar">Lembar</option>
                                <option value="Paket">Paket</option>
                              </select>
                            ) : (
                              <span className="px-2 py-0.5 rounded-md text-[10px] font-mono bg-slate-800 text-amber-400 border border-slate-700">
                                {alut.satuan}
                              </span>
                            )}
                          </td>

                          {/* Aksi */}
                          <td className="p-3 text-right">
                            {isEditing ? (
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  type="button"
                                  onClick={() => handleSaveEditAlut(alut.id_alut)}
                                  className="p-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors"
                                  title="Simpan Perubahan"
                                >
                                  <Check className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => setEditingAlutId(null)}
                                  className="p-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors"
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
                                  className="p-1.5 text-slate-400 hover:text-amber-400 hover:bg-slate-800 rounded-lg transition-colors"
                                  title="Edit Alut"
                                >
                                  <Edit2 className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeleteAlut(alut)}
                                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
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
          {/* Header Action Bar */}
          <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="relative w-full sm:w-80">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                value={searchTim}
                onChange={(e) => setSearchTim(e.target.value)}
                placeholder="Cari tim / unit siaga SAR..."
                className={`w-full pl-9 pr-3 py-2 rounded-xl text-xs border outline-none ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                    : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
                }`}
              />
            </div>

            {/* Quick Add Tim */}
            <form onSubmit={handleAddTim} className="flex items-center gap-2 w-full sm:w-auto">
              <input
                type="text"
                required
                value={newTimNama}
                onChange={(e) => setNewTimNama(e.target.value)}
                placeholder="Nama Tim / Unit SAR Baru..."
                className={`flex-1 sm:w-72 text-xs px-3 py-2 rounded-xl border outline-none ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                    : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
                }`}
              />
              <button
                type="submit"
                className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow transition-all active:scale-95"
              >
                <Plus className="w-4 h-4" />
                <span>Tambah Tim</span>
              </button>
            </form>
          </div>

          {/* Table List of Master Tim */}
          <div className={`rounded-2xl border overflow-hidden ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className={`border-b font-semibold uppercase tracking-wider ${
                    isDarkMode ? 'bg-slate-950 text-slate-400 border-slate-800' : 'bg-slate-50 text-slate-500 border-slate-200'
                  }`}>
                    <th className="p-3.5 w-16 text-center">Urutan</th>
                    <th className="p-3.5 w-12 text-center">No</th>
                    <th className="p-3.5">Nama Tim / Unit Siaga SAR</th>
                    <th className="p-3.5 w-32 text-right">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-800/60">
                  {filteredTim.length === 0 ? (
                    <tr>
                      <td colSpan={4} className="p-8 text-center text-slate-500">
                        Tidak ada data tim yang cocok dengan kata kunci.
                      </td>
                    </tr>
                  ) : (
                    filteredTim.map((tim, idx) => {
                      const isEditing = editingTimId === tim.id_tim;
                      return (
                        <tr
                          key={tim.id_tim}
                          className={`hover:bg-slate-800/20 transition-colors ${
                            isEditing ? (isDarkMode ? 'bg-slate-850' : 'bg-blue-50/50') : ''
                          }`}
                        >
                          {/* Reorder Buttons */}
                          <td className="p-3 text-center">
                            <div className="flex items-center justify-center gap-1">
                              <button
                                type="button"
                                disabled={idx === 0}
                                onClick={() => handleMoveTim(idx, 'up')}
                                className="p-1 rounded text-slate-400 hover:text-blue-400 disabled:opacity-20 transition-colors"
                                title="Geser Urutan Naik"
                              >
                                <ArrowUp className="w-3.5 h-3.5" />
                              </button>
                              <button
                                type="button"
                                disabled={idx === timList.length - 1}
                                onClick={() => handleMoveTim(idx, 'down')}
                                className="p-1 rounded text-slate-400 hover:text-blue-400 disabled:opacity-20 transition-colors"
                                title="Geser Urutan Turun"
                              >
                                <ArrowDown className="w-3.5 h-3.5" />
                              </button>
                            </div>
                          </td>

                          <td className="p-3 text-center font-mono text-slate-500">
                            {idx + 1}
                          </td>

                          {/* Nama Tim */}
                          <td className="p-3 font-medium">
                            {isEditing ? (
                              <input
                                type="text"
                                value={editTimNama}
                                onChange={(e) => setEditTimNama(e.target.value)}
                                className={`w-full text-xs px-2.5 py-1.5 rounded-lg border font-medium ${
                                  isDarkMode
                                    ? 'bg-slate-950 border-slate-700 text-white'
                                    : 'bg-white border-slate-300 text-slate-900'
                                }`}
                              />
                            ) : (
                              <div className="flex items-center gap-2">
                                <Users className="w-3.5 h-3.5 text-blue-400 shrink-0" />
                                <span className={isDarkMode ? 'text-slate-200' : 'text-slate-800'}>
                                  {tim.nama_tim}
                                </span>
                              </div>
                            )}
                          </td>

                          {/* Aksi */}
                          <td className="p-3 text-right">
                            {isEditing ? (
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  type="button"
                                  onClick={() => handleSaveEditTim(tim.id_tim)}
                                  className="p-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors"
                                  title="Simpan Perubahan"
                                >
                                  <Check className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => setEditingTimId(null)}
                                  className="p-1.5 bg-slate-700 hover:bg-slate-600 text-slate-300 rounded-lg transition-colors"
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
                                  className="p-1.5 text-slate-400 hover:text-blue-400 hover:bg-slate-800 rounded-lg transition-colors"
                                  title="Edit Tim"
                                >
                                  <Edit2 className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeleteTim(tim)}
                                  className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
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
      ) : (
        /* ================= TAB 3: SETTINGS & PEJABAT ================= */
        <div className="space-y-6">
          {/* Card 1: Identitas Satker */}
          <div className={`p-5 rounded-2xl border space-y-4 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="flex items-center gap-2 pb-3 border-b border-slate-800">
              <Building2 className="w-4 h-4 text-emerald-400" />
              <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                1. Pengaturan Identitas Satuan Kerja (Satker)
              </h2>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label className="block text-xs text-slate-400 mb-1">Kode Satker Resmi (Contoh: SAR-304)</label>
                <input
                  type="text"
                  value={satkerKode}
                  onChange={(e) => setSatkerKode(e.target.value)}
                  className={`w-full text-xs px-3 py-2 rounded-xl border font-mono font-bold ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-amber-400'
                      : 'bg-slate-50 border-slate-300 text-amber-600'
                  }`}
                />
              </div>

              <div className="sm:col-span-2">
                <label className="block text-xs text-slate-400 mb-1">Nama Satuan Kerja Lengkap</label>
                <input
                  type="text"
                  value={satkerNama}
                  onChange={(e) => setSatkerNama(e.target.value)}
                  className={`w-full text-xs px-3 py-2 rounded-xl border font-medium ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white'
                      : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />
              </div>
            </div>

            <div className="flex justify-end pt-1">
              <button
                type="button"
                onClick={handleSaveSatkerSettings}
                className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow"
              >
                <Save className="w-3.5 h-3.5" />
                <span>Simpan Pengaturan Satker</span>
              </button>
            </div>
          </div>

          {/* Card 2: Kelola Pejabat Penandatangan */}
          <div className={`p-5 rounded-2xl border space-y-4 ${
            isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2">
                <Shield className="w-4 h-4 text-orange-400" />
                <div>
                  <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    2. Data Pejabat Penandatangan (Pengirim BRA SAR)
                  </h2>
                  <p className="text-[11px] text-slate-400">
                    Data pejabat ini menjadi sumber pilihan dropdown otomatis untuk pengirim BRA SAR
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setShowAddPejabat(!showAddPejabat)}
                className="px-3.5 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold flex items-center gap-1.5 shadow"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>{showAddPejabat ? 'Tutup Form' : 'Tambah Pejabat'}</span>
              </button>
            </div>

            {/* Form Tambah Pejabat Baru */}
            {showAddPejabat && (
              <form onSubmit={handleSaveNewPejabat} className={`p-4 rounded-xl border space-y-3 ${
                isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-50 border-slate-200'
              }`}>
                <div className="text-xs font-bold text-orange-400 uppercase tracking-wider">
                  + Form Data Pejabat Baru
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                  <div>
                    <label className="block text-xs text-slate-400 mb-1">Nama Lengkap & Gelar *</label>
                    <input
                      type="text"
                      required
                      placeholder="Contoh: AMIRUDDIN AS, S.Sos."
                      value={pejabatForm.nama}
                      onChange={(e) => setPejabatForm({ ...pejabatForm, nama: e.target.value })}
                      className={`w-full text-xs px-3 py-2 rounded-xl border ${
                        isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300'
                      }`}
                    />
                  </div>

                  <div>
                    <label className="block text-xs text-slate-400 mb-1">NIP / NRP</label>
                    <input
                      type="text"
                      placeholder="Contoh: 197405101998031002"
                      value={pejabatForm.nip}
                      onChange={(e) => setPejabatForm({ ...pejabatForm, nip: e.target.value })}
                      className={`w-full text-xs px-3 py-2 rounded-xl border ${
                        isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300'
                      }`}
                    />
                  </div>

                  <div>
                    <label className="block text-xs text-slate-400 mb-1">Pangkat / Golongan</label>
                    <input
                      type="text"
                      placeholder="Contoh: PEMBINA (IV/a)"
                      value={pejabatForm.pangkat}
                      onChange={(e) => setPejabatForm({ ...pejabatForm, pangkat: e.target.value })}
                      className={`w-full text-xs px-3 py-2 rounded-xl border ${
                        isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300'
                      }`}
                    />
                  </div>

                  <div>
                    <label className="block text-xs text-slate-400 mb-1">Jabatan Definitif *</label>
                    <input
                      type="text"
                      required
                      placeholder="Kepala Kantor / Kasie Ops / SMC"
                      value={pejabatForm.jabatan}
                      onChange={(e) => setPejabatForm({ ...pejabatForm, jabatan: e.target.value })}
                      className={`w-full text-xs px-3 py-2 rounded-xl border ${
                        isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300'
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
                    <span>Jadikan Pejabat Default SMC (Operasi SAR)</span>
                  </label>

                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => setShowAddPejabat(false)}
                      className="px-3 py-1.5 text-xs text-slate-400 hover:text-white"
                    >
                      Batal
                    </button>
                    <button
                      type="submit"
                      className="px-4 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-semibold"
                    >
                      Simpan Pejabat
                    </button>
                  </div>
                </div>
              </form>
            )}

            {/* List of Pejabat Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
              {pejabatList.map((p) => {
                const isEditing = editingPejabatId === p.id;
                return (
                  <div
                    key={p.id}
                    className={`p-4 rounded-xl border flex flex-col justify-between space-y-3 transition-all ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-800 hover:border-slate-700'
                        : 'bg-slate-50 border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div className="space-y-1.5">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <h4 className={`text-xs font-bold ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                            {p.nama}
                          </h4>
                          <div className="text-[11px] text-amber-500 font-medium">
                            {p.jabatan}
                          </div>
                        </div>
                        {p.is_default_smc && (
                          <span className="px-1.5 py-0.5 text-[9px] font-bold uppercase rounded bg-orange-500/20 text-orange-400 border border-orange-500/30">
                            Default SMC
                          </span>
                        )}
                      </div>

                      <div className="text-[11px] text-slate-400 space-y-0.5 pt-1 border-t border-slate-800/60 font-mono">
                        <div>NIP: {p.nip || '-'}</div>
                        <div>Pangkat: {p.pangkat || '-'}</div>
                      </div>
                    </div>

                    <div className="flex items-center justify-end gap-1.5 pt-2 border-t border-slate-800/40">
                      <button
                        type="button"
                        onClick={() => handleDeletePejabat(p)}
                        className="p-1.5 text-slate-400 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                        title="Hapus Pejabat"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
