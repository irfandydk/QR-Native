import React, { useState, useEffect } from 'react';
import {
  ArrowLeft,
  Ship,
  Users,
  ShieldCheck,
  Building2,
  Plus,
  Edit2,
  Trash2,
  Save,
  Check,
  Search,
  RefreshCw,
  Image as ImageIcon,
  Radio,
  Phone,
  Mail,
  MapPin,
  Sparkles,
  AlertCircle,
  X,
  Sliders,
  KeyRound,
  Lock,
  Eye,
  EyeOff,
  CheckCircle2,
  Send,
  ShieldAlert,
} from 'lucide-react';
import { MasterAlut, MasterTim, Pejabat, AppSettings } from '../types';
import { useTheme } from '../context/ThemeContext';
import { showSuccessAlert, showErrorAlert, showConfirmDialog, showToast } from '../utils/alerts';

interface MasterSettingsPageProps {
  onBack: () => void;
  masterAlut: MasterAlut[];
  masterTim: MasterTim[];
  onRefreshData: () => Promise<void>;
}

export const MasterSettingsPage: React.FC<MasterSettingsPageProps> = ({
  onBack,
  masterAlut,
  masterTim,
  onRefreshData,
}) => {
  const { isDarkMode, accentColors } = useTheme();

  // Active Tab
  const [activeTab, setActiveTab] = useState<'alut' | 'tim' | 'pejabat' | 'satker' | 'keamanan'>('alut');

  // Search queries
  const [searchAlut, setSearchAlut] = useState('');
  const [searchTim, setSearchTim] = useState('');
  const [searchPejabat, setSearchPejabat] = useState('');

  // Password management states
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showOldPassword, setShowOldPassword] = useState(false);
  const [showNewPassword, setShowNewPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);
  const [isUpdatingPassword, setIsUpdatingPassword] = useState(false);
  const [isSendingTestReset, setIsSendingTestReset] = useState(false);

  // Pejabat list from API
  const [pejabatList, setPejabatList] = useState<Pejabat[]>([]);
  const [loadingPejabat, setLoadingPejabat] = useState(false);

  // App Settings from API
  const [settings, setSettings] = useState<AppSettings>({
    satker_nama: 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI',
    satker_kode: 'SAR-304',
    call_sign: 'KANSAR KENDARI',
    frekuensi_default: 'VHF Marine Ch 16 / HF 5828 kHz',
    alamat: 'Jl. Bandara Haluoleo No. 1, Ranomeeto, Konawe Selatan, Sulawesi Tenggara 93372',
    no_telp: '(0401) 3122115 / Emergency 115',
    email: 'sar_kendari@basarnas.go.id',
    logo_url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d4/Logo_Basarnas.svg/300px-Logo_Basarnas.svg.png',
  });
  const [isSavingSettings, setIsSavingSettings] = useState(false);

  // Form states for adding Alut
  const [newAlutNama, setNewAlutNama] = useState('');
  const [newAlutSatuan, setNewAlutSatuan] = useState('Unit');
  const [isAddingAlut, setIsAddingAlut] = useState(false);

  // Edit Alut modal / inline
  const [editingAlut, setEditingAlut] = useState<MasterAlut | null>(null);

  // Form states for adding Tim
  const [newTimNama, setNewTimNama] = useState('');
  const [isAddingTim, setIsAddingTim] = useState(false);

  // Edit Tim modal / inline
  const [editingTim, setEditingTim] = useState<MasterTim | null>(null);

  // Pejabat form modal
  const [showPejabatModal, setShowPejabatModal] = useState(false);
  const [editingPejabat, setEditingPejabat] = useState<Pejabat | null>(null);
  const [pejabatForm, setPejabatForm] = useState({
    nama: '',
    nip: '',
    pangkat: '',
    jabatan: '',
    is_default_smc: false,
  });

  // Load Settings & Pejabat on mount
  const fetchSettingsAndPejabat = async () => {
    try {
      setLoadingPejabat(true);
      const [setRes, pejRes] = await Promise.all([
        fetch('/api/settings'),
        fetch('/api/settings/pejabat'),
      ]);

      const [setData, pejData] = await Promise.all([setRes.json(), pejRes.json()]);

      if (setData.success && setData.data) {
        setSettings((prev) => ({ ...prev, ...setData.data }));
      }
      if (pejData.success && Array.isArray(pejData.data)) {
        setPejabatList(pejData.data);
      }
    } catch (err) {
      console.error('Failed to load settings or pejabat:', err);
    } finally {
      setLoadingPejabat(false);
    }
  };

  useEffect(() => {
    fetchSettingsAndPejabat();
  }, []);

  // --- ALUT HANDLERS ---
  const handleCreateAlut = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAlutNama.trim()) return;
    try {
      const res = await fetch('/api/master/alut', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_alut: newAlutNama.trim(), satuan: newAlutSatuan.trim() || 'Unit' }),
      });
      const data = await res.json();
      if (data.success) {
        await onRefreshData();
        setNewAlutNama('');
        setNewAlutSatuan('Unit');
        setIsAddingAlut(false);
        showSuccessAlert('Alut Ditambahkan', `Alut "${newAlutNama}" berhasil disimpan ke database.`);
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Tambah Alut', err.message || 'Terjadi kesalahan sistem.');
    }
  };

  const handleUpdateAlut = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingAlut || !editingAlut.nama_alut.trim()) return;
    try {
      const res = await fetch(`/api/master/alut/${editingAlut.id_alut}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nama_alut: editingAlut.nama_alut.trim(),
          satuan: editingAlut.satuan.trim() || 'Unit',
        }),
      });
      const data = await res.json();
      if (data.success) {
        await onRefreshData();
        setEditingAlut(null);
        showSuccessAlert('Alut Diperbarui', `Data alut "${editingAlut.nama_alut}" berhasil diperbarui.`);
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Perbarui Alut', err.message || 'Terjadi kesalahan sistem.');
    }
  };

  const handleDeleteAlut = async (id: number, nama: string) => {
    const confirmed = await showConfirmDialog(
      `Hapus Alut "${nama}"?`,
      'Data alut ini akan dihapus dari daftar master. Tindakan ini tidak dapat dibatalkan.'
    );
    if (!confirmed) return;
    try {
      const res = await fetch(`/api/master/alut/${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.success) {
        await onRefreshData();
        showToast(`Alut "${nama}" berhasil dihapus`, 'info');
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Hapus Alut', err.message || 'Terjadi kesalahan.');
    }
  };

  // --- TIM HANDLERS ---
  const handleCreateTim = async (e: React.FormEvent) => {
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
        await onRefreshData();
        setNewTimNama('');
        setIsAddingTim(false);
        showSuccessAlert('Tim Ditambahkan', `Tim "${newTimNama}" berhasil disimpan.`);
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Tambah Tim', err.message || 'Terjadi kesalahan.');
    }
  };

  const handleUpdateTim = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingTim || !editingTim.nama_tim.trim()) return;
    try {
      const res = await fetch(`/api/master/tim/${editingTim.id_tim}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_tim: editingTim.nama_tim.trim() }),
      });
      const data = await res.json();
      if (data.success) {
        await onRefreshData();
        setEditingTim(null);
        showSuccessAlert('Tim Diperbarui', `Data tim "${editingTim.nama_tim}" berhasil disimpan.`);
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Perbarui Tim', err.message || 'Terjadi kesalahan.');
    }
  };

  const handleDeleteTim = async (id: number, nama: string) => {
    const confirmed = await showConfirmDialog(
      `Hapus Tim "${nama}"?`,
      'Data tim/pos SAR ini akan dihapus dari daftar master tim.'
    );
    if (!confirmed) return;
    try {
      const res = await fetch(`/api/master/tim/${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.success) {
        await onRefreshData();
        showToast(`Tim "${nama}" berhasil dihapus`, 'info');
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Hapus Tim', err.message || 'Terjadi kesalahan.');
    }
  };

  // --- PEJABAT HANDLERS ---
  const handleOpenAddPejabat = () => {
    setEditingPejabat(null);
    setPejabatForm({
      nama: '',
      nip: '',
      pangkat: '',
      jabatan: '',
      is_default_smc: false,
    });
    setShowPejabatModal(true);
  };

  const handleOpenEditPejabat = (p: Pejabat) => {
    setEditingPejabat(p);
    setPejabatForm({
      nama: p.nama,
      nip: p.nip || '',
      pangkat: p.pangkat || '',
      jabatan: p.jabatan,
      is_default_smc: !!p.is_default_smc,
    });
    setShowPejabatModal(true);
  };

  const handleSavePejabat = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!pejabatForm.nama.trim() || !pejabatForm.jabatan.trim()) {
      showErrorAlert('Data Tidak Lengkap', 'Nama Pejabat dan Jabatan wajib diisi.');
      return;
    }

    try {
      if (editingPejabat) {
        // Update
        const res = await fetch(`/api/settings/pejabat/${editingPejabat.id}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(pejabatForm),
        });
        const data = await res.json();
        if (data.success) {
          setPejabatList((prev) =>
            prev.map((item) => (item.id === editingPejabat.id ? data.data : item))
          );
          setShowPejabatModal(false);
          showSuccessAlert('Pejabat Diperbarui', `Data ${pejabatForm.nama} berhasil diperbarui.`);
        } else {
          throw new Error(data.error);
        }
      } else {
        // Add
        const res = await fetch('/api/settings/pejabat', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(pejabatForm),
        });
        const data = await res.json();
        if (data.success) {
          setPejabatList((prev) => [...prev, data.data]);
          setShowPejabatModal(false);
          showSuccessAlert('Pejabat Ditambahkan', `Data ${pejabatForm.nama} berhasil ditambahkan.`);
        } else {
          throw new Error(data.error);
        }
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menyimpan Pejabat', err.message || 'Terjadi kesalahan server.');
    }
  };

  const handleDeletePejabat = async (id: string, nama: string) => {
    const confirmed = await showConfirmDialog(
      `Hapus Pejabat "${nama}"?`,
      'Data pejabat ini akan dihapus dari daftar pejabat resmi KPP Kendari.'
    );
    if (!confirmed) return;

    try {
      const res = await fetch(`/api/settings/pejabat/${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.success) {
        setPejabatList((prev) => prev.filter((p) => p.id !== id));
        showToast(`Data "${nama}" berhasil dihapus`, 'info');
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Hapus Pejabat', err.message || 'Terjadi kesalahan.');
    }
  };

  const handleToggleDefaultSmc = async (id: string, currentStatus: boolean) => {
    try {
      const updatedList = pejabatList.map((p) => ({
        ...p,
        is_default_smc: p.id === id ? !currentStatus : false,
      }));

      // Update API
      await fetch(`/api/settings/pejabat/${id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ is_default_smc: !currentStatus }),
      });

      setPejabatList(updatedList);
      showToast(!currentStatus ? 'Ditetapkan sebagai SMC Default' : 'SMC Default dinonaktifkan', 'success');
    } catch (err) {
      console.error(err);
    }
  };

  // --- SATKER SETTINGS HANDLERS ---
  const handleSaveSatkerSettings = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSavingSettings(true);
    try {
      const res = await fetch('/api/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settings),
      });
      const data = await res.json();
      if (data.success) {
        if (settings.logo_url) {
          localStorage.setItem('sar_custom_logo_url', settings.logo_url);
        }
        window.dispatchEvent(new CustomEvent('satker-settings-updated', { detail: settings }));
        showSuccessAlert('Pengaturan Disimpan', 'Informasi satuan kerja dan aplikasi berhasil diperbarui.');
      } else {
        throw new Error(data.error);
      }
    } catch (err: any) {
      showErrorAlert('Gagal Menyimpan Pengaturan', err.message || 'Terjadi kesalahan server.');
    } finally {
      setIsSavingSettings(false);
    }
  };

  const handleImageFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = () => {
        if (typeof reader.result === 'string') {
          setSettings((prev) => ({ ...prev, logo_url: reader.result as string }));
          showToast('File gambar logo berhasil dimuat!', 'success');
        }
      };
      reader.readAsDataURL(file);
    }
  };

  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!oldPassword.trim()) {
      showErrorAlert('Validasi Gagal', 'Masukkan kata sandi lama / saat ini.');
      return;
    }
    if (!newPassword.trim() || newPassword.length < 4) {
      showErrorAlert('Validasi Gagal', 'Kata sandi baru minimal harus 4 karakter.');
      return;
    }
    if (newPassword !== confirmPassword) {
      showErrorAlert('Validasi Gagal', 'Konfirmasi kata sandi baru tidak sesuai.');
      return;
    }

    setIsUpdatingPassword(true);
    try {
      const res = await fetch('/api/auth/change-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          oldPassword: oldPassword.trim(),
          newPassword: newPassword.trim(),
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Gagal mengubah kata sandi.');
      }

      setOldPassword('');
      setNewPassword('');
      setConfirmPassword('');
      await showSuccessAlert(
        'Kata Sandi Diperbarui',
        'Kata sandi administrator berhasil diubah. Gunakan kata sandi baru untuk login selanjutnya.'
      );
    } catch (err: any) {
      showErrorAlert('Gagal Mengubah Sandi', err.message || 'Terjadi kesalahan sistem.');
    } finally {
      setIsUpdatingPassword(false);
    }
  };

  const handleSendTestReset = async () => {
    setIsSendingTestReset(true);
    try {
      const res = await fetch('/api/auth/forgot-password', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: 'irfandydk@gmail.com' }),
      });
      const data = await res.json();
      if (data.success) {
        await showSuccessAlert(
          'Tautan Reset Terkirim',
          `Tautan dan token pemulihan kata sandi telah dikirim ke email ${data.email || 'irfandydk@gmail.com'}.`
        );
      } else {
        throw new Error(data.error || 'Gagal memproses pengiriman email reset.');
      }
    } catch (err: any) {
      showErrorAlert('Gagal Mengirim', err.message || 'Terjadi kesalahan server.');
    } finally {
      setIsSendingTestReset(false);
    }
  };

  // Filters
  const filteredAlut = masterAlut.filter((a) =>
    a.nama_alut.toLowerCase().includes(searchAlut.toLowerCase()) ||
    a.satuan.toLowerCase().includes(searchAlut.toLowerCase())
  );

  const filteredTim = masterTim.filter((t) =>
    t.nama_tim.toLowerCase().includes(searchTim.toLowerCase())
  );

  const filteredPejabat = pejabatList.filter((p) =>
    p.nama.toLowerCase().includes(searchPejabat.toLowerCase()) ||
    p.jabatan.toLowerCase().includes(searchPejabat.toLowerCase()) ||
    (p.nip && p.nip.includes(searchPejabat))
  );

  return (
    <div className={`min-h-screen py-8 px-4 sm:px-6 lg:px-8 transition-colors duration-200 ${
      isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'
    }`}>
      <div className="max-w-7xl mx-auto space-y-6">
        {/* Top Header Bar with Breadcrumb & Back */}
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
          <div className="flex items-center gap-3">
            <button
              onClick={onBack}
              className={`p-2.5 rounded-xl border transition-colors ${
                isDarkMode
                  ? 'bg-slate-900 hover:bg-slate-800 border-slate-700 text-slate-300'
                  : 'bg-white hover:bg-slate-100 border-slate-300 text-slate-700 shadow-sm'
              }`}
              title="Kembali ke Dashboard Utama"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>
            <div>
              <div className="flex items-center gap-2">
                <span className="text-xs font-mono font-semibold px-2 py-0.5 rounded bg-orange-500/10 text-orange-500 border border-orange-500/20">
                  SISTEM BASARNAS
                </span>
                <span className="text-xs text-slate-400">•</span>
                <span className="text-xs text-slate-400 font-medium">Pengaturan & Basis Data</span>
              </div>
              <h1 className="text-xl sm:text-2xl font-black tracking-tight mt-0.5">
                Master Data & Pengaturan Aplikasi
              </h1>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={async () => {
                await onRefreshData();
                await fetchSettingsAndPejabat();
                showToast('Data master berhasil diperbarui', 'info');
              }}
              className={`inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border transition-colors ${
                isDarkMode
                  ? 'bg-slate-900 hover:bg-slate-800 border-slate-700 text-slate-300'
                  : 'bg-white hover:bg-slate-100 border-slate-300 text-slate-700 shadow-sm'
              }`}
            >
              <RefreshCw className="w-3.5 h-3.5" />
              <span>Sinkronkan Data</span>
            </button>
          </div>
        </div>

        {/* Quick Stats Grid */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
          <div className={`p-4 rounded-2xl border transition-colors ${
            isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-slate-400">Total Alut / Palsar</span>
              <Ship className="w-4 h-4 text-orange-500" />
            </div>
            <div className="text-2xl font-black mt-2">{masterAlut.length}</div>
            <div className="text-[11px] text-slate-400 mt-0.5">Armada & Peralatan</div>
          </div>

          <div className={`p-4 rounded-2xl border transition-colors ${
            isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-slate-400">Total Tim Rescue</span>
              <Users className="w-4 h-4 text-cyan-500" />
            </div>
            <div className="text-2xl font-black mt-2">{masterTim.length}</div>
            <div className="text-[11px] text-slate-400 mt-0.5">Pos & Unit SAR</div>
          </div>

          <div className={`p-4 rounded-2xl border transition-colors ${
            isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-slate-400">Pejabat Terdaftar</span>
              <ShieldCheck className="w-4 h-4 text-emerald-500" />
            </div>
            <div className="text-2xl font-black mt-2">{pejabatList.length}</div>
            <div className="text-[11px] text-slate-400 mt-0.5">Personel Pejabat & SMC</div>
          </div>

          <div className={`p-4 rounded-2xl border transition-colors ${
            isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
          }`}>
            <div className="flex items-center justify-between">
              <span className="text-xs font-medium text-slate-400">Kode Stasiun / Satker</span>
              <Building2 className="w-4 h-4 text-amber-500" />
            </div>
            <div className="text-xl font-mono font-bold mt-2 truncate text-amber-500">{settings.satker_kode || 'SAR-304'}</div>
            <div className="text-[11px] text-slate-400 mt-0.5 truncate">{settings.call_sign || 'KANSAR KENDARI'}</div>
          </div>
        </div>

        {/* Navigation Tabs Bar */}
        <div className={`flex items-center gap-1 p-1.5 rounded-2xl border overflow-x-auto ${
          isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <button
            onClick={() => setActiveTab('alut')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
              activeTab === 'alut'
                ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
                : isDarkMode
                ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Ship className="w-4 h-4" />
            <span>Master Alut & Palsar ({masterAlut.length})</span>
          </button>

          <button
            onClick={() => setActiveTab('tim')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
              activeTab === 'tim'
                ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
                : isDarkMode
                ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Users className="w-4 h-4" />
            <span>Master Tim Rescue ({masterTim.length})</span>
          </button>

          <button
            onClick={() => setActiveTab('pejabat')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
              activeTab === 'pejabat'
                ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
                : isDarkMode
                ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <ShieldCheck className="w-4 h-4" />
            <span>Manajemen Pejabat SAR ({pejabatList.length})</span>
          </button>

          <button
            onClick={() => setActiveTab('satker')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
              activeTab === 'satker'
                ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
                : isDarkMode
                ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Sliders className="w-4 h-4" />
            <span>Pengaturan Satker & Aplikasi</span>
          </button>

          <button
            onClick={() => setActiveTab('keamanan')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
              activeTab === 'keamanan'
                ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
                : isDarkMode
                ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <KeyRound className="w-4 h-4" />
            <span>Keamanan & Sandi Admin</span>
          </button>
        </div>

        {/* TAB 1: MASTER ALUT */}
        {activeTab === 'alut' && (
          <div className="space-y-4 animate-in fade-in duration-150">
            {/* Action & Search Bar */}
            <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="relative w-full sm:w-80">
                <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  placeholder="Cari armada alut / satuan..."
                  value={searchAlut}
                  onChange={(e) => setSearchAlut(e.target.value)}
                  className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                      : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
                  }`}
                />
              </div>

              <button
                onClick={() => setIsAddingAlut(!isAddingAlut)}
                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20 transition-all"
              >
                <Plus className="w-4 h-4" />
                <span>Tambah Alut Baru</span>
              </button>
            </div>

            {/* Add Alut Form Drawer */}
            {isAddingAlut && (
              <form
                onSubmit={handleCreateAlut}
                className={`p-5 rounded-2xl border space-y-4 animate-in fade-in slide-in-from-top-2 duration-150 ${
                  isDarkMode ? 'bg-slate-900 border-orange-500/40' : 'bg-orange-50/50 border-orange-200 shadow-sm'
                }`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Plus className="w-4 h-4 text-orange-500" />
                    <h3 className="text-sm font-bold">Tambah Alut / Peralatan SAR Baru</h3>
                  </div>
                  <button
                    type="button"
                    onClick={() => setIsAddingAlut(false)}
                    className="text-slate-400 hover:text-slate-200 p-1"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-semibold mb-1 text-slate-400">Nama Alut / Palsar</label>
                    <input
                      type="text"
                      required
                      placeholder="Contoh: RIB 01 Kansar Kendari, Aqua Eye, Rescue Car..."
                      value={newAlutNama}
                      onChange={(e) => setNewAlutNama(e.target.value)}
                      className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white'
                          : 'bg-white border-slate-300 text-slate-900'
                      }`}
                    />
                  </div>

                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-400">Satuan Standar</label>
                    <input
                      type="text"
                      required
                      placeholder="Unit, Set, Lembar, Org..."
                      value={newAlutSatuan}
                      onChange={(e) => setNewAlutSatuan(e.target.value)}
                      className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white'
                          : 'bg-white border-slate-300 text-slate-900'
                      }`}
                    />
                  </div>
                </div>

                <div className="flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setIsAddingAlut(false)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-medium border ${
                      isDarkMode ? 'border-slate-700 text-slate-300' : 'border-slate-300 text-slate-700'
                    }`}
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    className="px-4 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold shadow-md shadow-orange-600/20"
                  >
                    Simpan Alut
                  </button>
                </div>
              </form>
            )}

            {/* Alut Table / Cards Grid */}
            <div className={`rounded-2xl border overflow-hidden ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className={`border-b uppercase font-bold tracking-wider text-[11px] ${
                    isDarkMode ? 'bg-slate-950/60 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-500'
                  }`}>
                    <tr>
                      <th className="px-4 py-3 w-16 text-center">No</th>
                      <th className="px-4 py-3">Nama Alut & Peralatan SAR</th>
                      <th className="px-4 py-3 w-32">Satuan</th>
                      <th className="px-4 py-3 w-36 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                    {filteredAlut.length === 0 ? (
                      <tr>
                        <td colSpan={4} className="px-4 py-8 text-center text-slate-400">
                          Tidak ada data alut yang sesuai pencarian.
                        </td>
                      </tr>
                    ) : (
                      filteredAlut.map((item, idx) => (
                        <tr
                          key={item.id_alut}
                          className={`transition-colors ${
                            isDarkMode ? 'hover:bg-slate-800/50' : 'hover:bg-slate-50'
                          }`}
                        >
                          <td className="px-4 py-3 text-center text-slate-400 font-mono">
                            {idx + 1}
                          </td>
                          <td className="px-4 py-3">
                            {editingAlut?.id_alut === item.id_alut ? (
                              <input
                                type="text"
                                value={editingAlut.nama_alut}
                                onChange={(e) =>
                                  setEditingAlut({ ...editingAlut, nama_alut: e.target.value })
                                }
                                className={`w-full px-2.5 py-1.5 rounded-lg border text-xs ${
                                  isDarkMode
                                    ? 'bg-slate-950 border-orange-500 text-white'
                                    : 'bg-white border-orange-500 text-slate-900'
                                }`}
                              />
                            ) : (
                              <div className="font-semibold">{item.nama_alut}</div>
                            )}
                          </td>
                          <td className="px-4 py-3">
                            {editingAlut?.id_alut === item.id_alut ? (
                              <input
                                type="text"
                                value={editingAlut.satuan}
                                onChange={(e) =>
                                  setEditingAlut({ ...editingAlut, satuan: e.target.value })
                                }
                                className={`w-24 px-2.5 py-1.5 rounded-lg border text-xs ${
                                  isDarkMode
                                    ? 'bg-slate-950 border-orange-500 text-white'
                                    : 'bg-white border-orange-500 text-slate-900'
                                }`}
                              />
                            ) : (
                              <span className={`px-2 py-0.5 rounded-md font-mono text-[11px] border ${
                                isDarkMode
                                  ? 'bg-slate-800 border-slate-700 text-slate-300'
                                  : 'bg-slate-100 border-slate-200 text-slate-700'
                              }`}>
                                {item.satuan}
                              </span>
                            )}
                          </td>
                          <td className="px-4 py-3 text-right">
                            {editingAlut?.id_alut === item.id_alut ? (
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  type="button"
                                  onClick={handleUpdateAlut}
                                  className="p-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors"
                                  title="Simpan Perubahan"
                                >
                                  <Save className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => setEditingAlut(null)}
                                  className="p-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors"
                                  title="Batal"
                                >
                                  <X className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            ) : (
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  type="button"
                                  onClick={() => setEditingAlut(item)}
                                  className={`p-1.5 rounded-lg border transition-colors ${
                                    isDarkMode
                                      ? 'hover:bg-slate-800 border-slate-700 text-slate-300'
                                      : 'hover:bg-slate-100 border-slate-200 text-slate-700'
                                  }`}
                                  title="Edit Alut"
                                >
                                  <Edit2 className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeleteAlut(item.id_alut, item.nama_alut)}
                                  className="p-1.5 rounded-lg border border-red-500/20 text-red-500 hover:bg-red-500/10 transition-colors"
                                  title="Hapus Alut"
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            )}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {/* TAB 2: MASTER TIM */}
        {activeTab === 'tim' && (
          <div className="space-y-4 animate-in fade-in duration-150">
            {/* Action & Search Bar */}
            <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="relative w-full sm:w-80">
                <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  placeholder="Cari tim rescue / pos SAR..."
                  value={searchTim}
                  onChange={(e) => setSearchTim(e.target.value)}
                  className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                      : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
                  }`}
                />
              </div>

              <button
                onClick={() => setIsAddingTim(!isAddingTim)}
                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20 transition-all"
              >
                <Plus className="w-4 h-4" />
                <span>Tambah Tim Rescue Baru</span>
              </button>
            </div>

            {/* Add Tim Form Drawer */}
            {isAddingTim && (
              <form
                onSubmit={handleCreateTim}
                className={`p-5 rounded-2xl border space-y-4 animate-in fade-in slide-in-from-top-2 duration-150 ${
                  isDarkMode ? 'bg-slate-900 border-orange-500/40' : 'bg-orange-50/50 border-orange-200 shadow-sm'
                }`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Plus className="w-4 h-4 text-orange-500" />
                    <h3 className="text-sm font-bold">Tambah Tim Rescue / Pos SAR Baru</h3>
                  </div>
                  <button
                    type="button"
                    onClick={() => setIsAddingTim(false)}
                    className="text-slate-400 hover:text-slate-200 p-1"
                  >
                    <X className="w-4 h-4" />
                  </button>
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-400">Nama Tim / Pos / Unit Siaga SAR</label>
                  <input
                    type="text"
                    required
                    placeholder="Contoh: Pos SAR Kolaka, Tim Rescue Kansar Kendari, Pos SAR Wakatobi..."
                    value={newTimNama}
                    onChange={(e) => setNewTimNama(e.target.value)}
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div className="flex justify-end gap-2">
                  <button
                    type="button"
                    onClick={() => setIsAddingTim(false)}
                    className={`px-3 py-1.5 rounded-lg text-xs font-medium border ${
                      isDarkMode ? 'border-slate-700 text-slate-300' : 'border-slate-300 text-slate-700'
                    }`}
                  >
                    Batal
                  </button>
                  <button
                    type="submit"
                    className="px-4 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-lg text-xs font-bold shadow-md shadow-orange-600/20"
                  >
                    Simpan Tim
                  </button>
                </div>
              </form>
            )}

            {/* Tim Table */}
            <div className={`rounded-2xl border overflow-hidden ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className={`border-b uppercase font-bold tracking-wider text-[11px] ${
                    isDarkMode ? 'bg-slate-950/60 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-500'
                  }`}>
                    <tr>
                      <th className="px-4 py-3 w-16 text-center">No</th>
                      <th className="px-4 py-3">Nama Tim Rescue / Pos SAR</th>
                      <th className="px-4 py-3 w-36 text-right">Aksi</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                    {filteredTim.length === 0 ? (
                      <tr>
                        <td colSpan={3} className="px-4 py-8 text-center text-slate-400">
                          Tidak ada data tim yang sesuai pencarian.
                        </td>
                      </tr>
                    ) : (
                      filteredTim.map((item, idx) => (
                        <tr
                          key={item.id_tim}
                          className={`transition-colors ${
                            isDarkMode ? 'hover:bg-slate-800/50' : 'hover:bg-slate-50'
                          }`}
                        >
                          <td className="px-4 py-3 text-center text-slate-400 font-mono">
                            {idx + 1}
                          </td>
                          <td className="px-4 py-3">
                            {editingTim?.id_tim === item.id_tim ? (
                              <input
                                type="text"
                                value={editingTim.nama_tim}
                                onChange={(e) =>
                                  setEditingTim({ ...editingTim, nama_tim: e.target.value })
                                }
                                className={`w-full px-2.5 py-1.5 rounded-lg border text-xs ${
                                  isDarkMode
                                    ? 'bg-slate-950 border-orange-500 text-white'
                                    : 'bg-white border-orange-500 text-slate-900'
                                }`}
                              />
                            ) : (
                              <div className="font-semibold">{item.nama_tim}</div>
                            )}
                          </td>
                          <td className="px-4 py-3 text-right">
                            {editingTim?.id_tim === item.id_tim ? (
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  type="button"
                                  onClick={handleUpdateTim}
                                  className="p-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg transition-colors"
                                  title="Simpan Perubahan"
                                >
                                  <Save className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => setEditingTim(null)}
                                  className="p-1.5 bg-slate-700 hover:bg-slate-600 text-white rounded-lg transition-colors"
                                  title="Batal"
                                >
                                  <X className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            ) : (
                              <div className="flex items-center justify-end gap-1.5">
                                <button
                                  type="button"
                                  onClick={() => setEditingTim(item)}
                                  className={`p-1.5 rounded-lg border transition-colors ${
                                    isDarkMode
                                      ? 'hover:bg-slate-800 border-slate-700 text-slate-300'
                                      : 'hover:bg-slate-100 border-slate-200 text-slate-700'
                                  }`}
                                  title="Edit Tim"
                                >
                                  <Edit2 className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeleteTim(item.id_tim, item.nama_tim)}
                                  className="p-1.5 rounded-lg border border-red-500/20 text-red-500 hover:bg-red-500/10 transition-colors"
                                  title="Hapus Tim"
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            )}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        )}

        {/* TAB 3: MANAJEMEN PEJABAT SAR */}
        {activeTab === 'pejabat' && (
          <div className="space-y-4 animate-in fade-in duration-150">
            {/* Header & Search */}
            <div className={`p-4 rounded-2xl border flex flex-col sm:flex-row items-center justify-between gap-3 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="relative w-full sm:w-80">
                <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  placeholder="Cari nama, NIP, atau jabatan..."
                  value={searchPejabat}
                  onChange={(e) => setSearchPejabat(e.target.value)}
                  className={`w-full pl-9 pr-4 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                      : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
                  }`}
                />
              </div>

              <button
                onClick={handleOpenAddPejabat}
                className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20 transition-all"
              >
                <Plus className="w-4 h-4" />
                <span>Tambah Data Pejabat</span>
              </button>
            </div>

            {/* Pejabat Cards Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {filteredPejabat.length === 0 ? (
                <div className={`col-span-2 p-8 text-center rounded-2xl border ${
                  isDarkMode ? 'bg-slate-900/50 border-slate-800 text-slate-400' : 'bg-white border-slate-200 text-slate-500'
                }`}>
                  Tidak ada data pejabat yang cocok.
                </div>
              ) : (
                filteredPejabat.map((p) => (
                  <div
                    key={p.id}
                    className={`p-5 rounded-2xl border relative flex flex-col justify-between transition-all ${
                      p.is_default_smc
                        ? isDarkMode
                          ? 'bg-slate-900/90 border-orange-500/40 shadow-lg shadow-orange-950/20'
                          : 'bg-orange-50/40 border-orange-300 shadow-sm'
                        : isDarkMode
                        ? 'bg-slate-900/80 border-slate-800 hover:border-slate-700'
                        : 'bg-white border-slate-200 hover:border-slate-300 shadow-sm'
                    }`}
                  >
                    <div>
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex items-center gap-2.5">
                          <div className={`w-9 h-9 rounded-xl font-bold flex items-center justify-center text-sm border ${
                            p.is_default_smc
                              ? 'bg-orange-500 text-white border-orange-400'
                              : isDarkMode
                              ? 'bg-slate-800 text-slate-300 border-slate-700'
                              : 'bg-slate-100 text-slate-700 border-slate-200'
                          }`}>
                            {p.nama.charAt(0)}
                          </div>
                          <div>
                            <h3 className="font-bold text-sm leading-tight">{p.nama}</h3>
                            <p className="text-[11px] font-mono text-slate-400 mt-0.5">
                              NIP. {p.nip || '-'}
                            </p>
                          </div>
                        </div>

                        {p.is_default_smc && (
                          <span className="px-2 py-0.5 bg-orange-500/10 text-orange-400 border border-orange-500/30 rounded-full text-[10px] font-bold tracking-wider uppercase shrink-0">
                            Default SMC
                          </span>
                        )}
                      </div>

                      <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 space-y-1.5 text-xs">
                        <div className="flex items-center justify-between">
                          <span className="text-slate-400">Pangkat / Golongan:</span>
                          <span className="font-semibold">{p.pangkat || '-'}</span>
                        </div>
                        <div className="flex items-start justify-between gap-2">
                          <span className="text-slate-400 shrink-0">Jabatan:</span>
                          <span className="font-semibold text-right leading-tight">{p.jabatan}</span>
                        </div>
                      </div>
                    </div>

                    <div className="mt-5 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-2">
                      <button
                        type="button"
                        onClick={() => handleToggleDefaultSmc(p.id, !!p.is_default_smc)}
                        className={`text-[11px] font-semibold px-2.5 py-1 rounded-lg border transition-colors ${
                          p.is_default_smc
                            ? 'bg-orange-500/20 text-orange-400 border-orange-500/40 hover:bg-orange-500/30'
                            : isDarkMode
                            ? 'text-slate-400 hover:text-slate-200 border-slate-700 hover:bg-slate-800'
                            : 'text-slate-600 hover:text-slate-900 border-slate-200 hover:bg-slate-100'
                        }`}
                      >
                        {p.is_default_smc ? '★ SMC Aktif' : 'Jadikan SMC Default'}
                      </button>

                      <div className="flex items-center gap-1.5">
                        <button
                          type="button"
                          onClick={() => handleOpenEditPejabat(p)}
                          className={`p-1.5 rounded-lg border transition-colors ${
                            isDarkMode
                              ? 'hover:bg-slate-800 border-slate-700 text-slate-300'
                              : 'hover:bg-slate-100 border-slate-200 text-slate-700'
                          }`}
                          title="Edit Pejabat"
                        >
                          <Edit2 className="w-3.5 h-3.5" />
                        </button>
                        <button
                          type="button"
                          onClick={() => handleDeletePejabat(p.id, p.nama)}
                          className="p-1.5 rounded-lg border border-red-500/20 text-red-500 hover:bg-red-500/10 transition-colors"
                          title="Hapus Pejabat"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        )}

        {/* TAB 4: PENGATURAN APLIKASI & SATKER */}
        {activeTab === 'satker' && (
          <form onSubmit={handleSaveSatkerSettings} className="space-y-6 animate-in fade-in duration-150">
            {/* Logo Satker & Preview Card */}
            <div className={`p-6 rounded-2xl border space-y-4 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800">
                <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 border border-orange-500/20">
                  <ImageIcon className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold">Logo & Identitas Visual Satker</h3>
                  <p className="text-xs text-slate-400">Logo resmi yang ditampilkan di kop naskah BRA SAR, QR preview, dan header sistem</p>
                </div>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-12 gap-6 items-center">
                {/* Logo Preview Box */}
                <div className="md:col-span-4 flex flex-col items-center justify-center p-4 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 bg-slate-100/50 dark:bg-slate-950/50 text-center gap-2">
                  <div className="w-24 h-24 rounded-xl bg-white p-2 border border-slate-200 shadow-sm flex items-center justify-center overflow-hidden">
                    {settings.logo_url ? (
                      <img
                        src={settings.logo_url}
                        alt="Logo Satker"
                        className="max-h-full max-w-full object-contain"
                      />
                    ) : (
                      <ImageIcon className="w-8 h-8 text-slate-400" />
                    )}
                  </div>
                  <span className="text-[11px] font-semibold text-slate-400">Pratinjau Logo</span>
                </div>

                {/* Logo URL Input & File Uploader */}
                <div className="md:col-span-8 space-y-3">
                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-400">
                      URL Gambar Logo Satker
                    </label>
                    <input
                      type="url"
                      value={settings.logo_url || ''}
                      onChange={(e) => setSettings({ ...settings, logo_url: e.target.value })}
                      placeholder="https://... atau gunakan tombol di bawah"
                      className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white'
                          : 'bg-white border-slate-300 text-slate-900'
                      }`}
                    />
                  </div>

                  <div className="flex flex-wrap items-center gap-2">
                    <label className={`cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors ${
                      isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-200'
                        : 'bg-slate-100 hover:bg-slate-200 border-slate-300 text-slate-700'
                    }`}>
                      <Plus className="w-3.5 h-3.5" />
                      <span>Unggah File Logo</span>
                      <input
                        type="file"
                        accept="image/*"
                        onChange={handleImageFileChange}
                        className="hidden"
                      />
                    </label>

                    <button
                      type="button"
                      onClick={() => {
                        setSettings((prev) => ({
                          ...prev,
                          logo_url: 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d4/Logo_Basarnas.svg/300px-Logo_Basarnas.svg.png',
                        }));
                        showToast('Logo resmi Basarnas diterapkan', 'info');
                      }}
                      className={`px-3 py-1.5 rounded-lg border text-xs font-medium ${
                        isDarkMode
                          ? 'border-slate-800 text-slate-400 hover:text-slate-200'
                          : 'border-slate-200 text-slate-600 hover:text-slate-900'
                      }`}
                    >
                      Gunakan Logo Standar Basarnas
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* Informasi Kantor Satker Card */}
            <div className={`p-6 rounded-2xl border space-y-4 ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800">
                <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 border border-orange-500/20">
                  <Building2 className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold">Informasi Satuan Kerja (Satker)</h3>
                  <p className="text-xs text-slate-400">Data resmi kantor SAR untuk penomoran naskah, koordinasi dan header laporan</p>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2">
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Nama Satuan Kerja / Kantor SAR
                  </label>
                  <input
                    type="text"
                    required
                    value={settings.satker_nama || ''}
                    onChange={(e) => setSettings({ ...settings, satker_nama: e.target.value })}
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 font-bold ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Kode Satker / Stasiun (untuk Penomoran Dokumen)
                  </label>
                  <input
                    type="text"
                    required
                    value={settings.satker_kode || ''}
                    onChange={(e) => setSettings({ ...settings, satker_kode: e.target.value })}
                    placeholder="SAR-304"
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Call Sign Stasiun Radio
                  </label>
                  <input
                    type="text"
                    value={settings.call_sign || ''}
                    onChange={(e) => setSettings({ ...settings, call_sign: e.target.value })}
                    placeholder="KANSAR KENDARI"
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 font-mono ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Frekuensi Komunikasi Siaga Standar
                  </label>
                  <input
                    type="text"
                    value={settings.frekuensi_default || ''}
                    onChange={(e) => setSettings({ ...settings, frekuensi_default: e.target.value })}
                    placeholder="VHF Marine Ch 16 / HF 5828 kHz"
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Nomor Telepon Hotline / Emergency Siaga 24 Jam
                  </label>
                  <input
                    type="text"
                    value={settings.no_telp || ''}
                    onChange={(e) => setSettings({ ...settings, no_telp: e.target.value })}
                    placeholder="(0401) 3122115 / 115"
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Email Resmi Satker
                  </label>
                  <input
                    type="email"
                    value={settings.email || ''}
                    onChange={(e) => setSettings({ ...settings, email: e.target.value })}
                    placeholder="sar_kendari@basarnas.go.id"
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div className="sm:col-span-2">
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Alamat Lengkap Kantor
                  </label>
                  <textarea
                    rows={2}
                    value={settings.alamat || ''}
                    onChange={(e) => setSettings({ ...settings, alamat: e.target.value })}
                    className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />
                </div>
              </div>

              <div className="pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-end">
                <button
                  type="submit"
                  disabled={isSavingSettings}
                  className="inline-flex items-center gap-2 px-6 py-2.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-orange-600/30 transition-all disabled:opacity-50"
                >
                  <Save className="w-4 h-4" />
                  <span>{isSavingSettings ? 'Menyimpan...' : 'Simpan Pengaturan Satker'}</span>
                </button>
              </div>
            </div>
          </form>
        )}

        {/* TAB 5: KEAMANAN & SANDI ADMIN */}
        {activeTab === 'keamanan' && (
          <div className="space-y-6 animate-in fade-in duration-150">
            {/* Account Info & Status Grid */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className={`p-5 rounded-2xl border space-y-2 ${
                isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
              }`}>
                <div className="flex items-center justify-between text-slate-400">
                  <span className="text-xs font-semibold">User Administrator</span>
                  <KeyRound className="w-4 h-4 text-orange-500" />
                </div>
                <div className="text-xl font-black font-mono text-orange-500">admin</div>
                <p className="text-[11px] text-slate-400">
                  User tunggal terdaftar untuk otorisasi sistem SAR Kendari.
                </p>
              </div>

              <div className={`p-5 rounded-2xl border space-y-2 ${
                isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
              }`}>
                <div className="flex items-center justify-between text-slate-400">
                  <span className="text-xs font-semibold">Email Pemulihan Resmi</span>
                  <Mail className="w-4 h-4 text-cyan-500" />
                </div>
                <div className="text-sm font-bold font-mono text-cyan-600 dark:text-cyan-400 truncate">
                  irfandydk@gmail.com
                </div>
                <p className="text-[11px] text-slate-400">
                  Tujuan pengiriman link dan token reset kata sandi darurat.
                </p>
              </div>

              <div className={`p-5 rounded-2xl border space-y-2 ${
                isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
              }`}>
                <div className="flex items-center justify-between text-slate-400">
                  <span className="text-xs font-semibold">Status Keamanan</span>
                  <ShieldCheck className="w-4 h-4 text-emerald-500" />
                </div>
                <div className="text-sm font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4" />
                  <span>Aktif & Terproteksi</span>
                </div>
                <p className="text-[11px] text-slate-400">
                  Tersimpan di basis data konfigurasi aplikasi.
                </p>
              </div>
            </div>

            {/* Change Password Form Card */}
            <div className={`p-6 rounded-2xl border ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center gap-3 pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
                <div className="p-2.5 rounded-xl bg-orange-500/10 text-orange-500 border border-orange-500/20">
                  <Lock className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-bold text-sm sm:text-base">Ganti Kata Sandi Administrator</h3>
                  <p className="text-xs text-slate-400">
                    Perbarui kata sandi untuk akun administrator utama sistem (default: <span className="font-mono font-bold text-orange-500">Kendari2026</span>)
                  </p>
                </div>
              </div>

              <form onSubmit={handleUpdatePassword} className="space-y-4 max-w-xl">
                {/* Old Password */}
                <div>
                  <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                    Kata Sandi Saat Ini / Sandi Lama <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showOldPassword ? 'text' : 'password'}
                      required
                      value={oldPassword}
                      onChange={(e) => setOldPassword(e.target.value)}
                      placeholder="Masukkan kata sandi saat ini"
                      className={`w-full pl-3.5 pr-10 py-2.5 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowOldPassword(!showOldPassword)}
                      className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200 cursor-pointer"
                    >
                      {showOldPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                {/* New Password */}
                <div>
                  <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                    Kata Sandi Baru <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showNewPassword ? 'text' : 'password'}
                      required
                      value={newPassword}
                      onChange={(e) => setNewPassword(e.target.value)}
                      placeholder="Minimal 4 karakter"
                      className={`w-full pl-3.5 pr-10 py-2.5 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowNewPassword(!showNewPassword)}
                      className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200 cursor-pointer"
                    >
                      {showNewPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                {/* Confirm New Password */}
                <div>
                  <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                    Konfirmasi Kata Sandi Baru <span className="text-red-500">*</span>
                  </label>
                  <div className="relative">
                    <input
                      type={showConfirmPassword ? 'text' : 'password'}
                      required
                      value={confirmPassword}
                      onChange={(e) => setConfirmPassword(e.target.value)}
                      placeholder="Ulangi kata sandi baru"
                      className={`w-full pl-3.5 pr-10 py-2.5 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                    <button
                      type="button"
                      onClick={() => setShowConfirmPassword(!showConfirmPassword)}
                      className="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200 cursor-pointer"
                    >
                      {showConfirmPassword ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                    </button>
                  </div>
                </div>

                <div className="pt-2">
                  <button
                    type="submit"
                    disabled={isUpdatingPassword}
                    className="inline-flex items-center gap-2 px-6 py-2.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-orange-600/30 transition-all disabled:opacity-50 cursor-pointer"
                  >
                    <Save className="w-4 h-4" />
                    <span>{isUpdatingPassword ? 'Memperbarui Sandi...' : 'Perbarui Kata Sandi Admin'}</span>
                  </button>
                </div>
              </form>
            </div>

            {/* Email Reset Testing & Info Card */}
            <div className={`p-6 rounded-2xl border ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div className="flex items-start gap-3">
                  <div className="p-2.5 rounded-xl bg-cyan-500/10 text-cyan-500 border border-cyan-500/20 shrink-0">
                    <Mail className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm">Layanan Pemulihan Email Darurat</h3>
                    <p className="text-xs text-slate-400 mt-0.5 leading-relaxed">
                      Fitur lupa kata sandi di halaman login terhubung langsung ke email resmi <strong className="text-cyan-400">irfandydk@gmail.com</strong>. Anda dapat menguji pengiriman link pemulihan kapan saja.
                    </p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={handleSendTestReset}
                  disabled={isSendingTestReset}
                  className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold border border-cyan-500/30 bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 hover:bg-cyan-500/20 transition-all shrink-0 cursor-pointer"
                >
                  <Send className={`w-3.5 h-3.5 ${isSendingTestReset ? 'animate-spin' : ''}`} />
                  <span>{isSendingTestReset ? 'Mengirim...' : 'Uji Kirim Link Reset ke Email'}</span>
                </button>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Pejabat Edit/Add Modal */}
      {showPejabatModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-150">
          <div className={`relative w-full max-w-lg rounded-2xl border p-6 shadow-2xl space-y-4 ${
            isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-200 text-slate-900'
          }`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <ShieldCheck className="w-5 h-5 text-orange-500" />
                <h3 className="font-bold text-sm">
                  {editingPejabat ? 'Edit Data Pejabat' : 'Tambah Pejabat Baru'}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setShowPejabatModal(false)}
                className="text-slate-400 hover:text-slate-200 p-1"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleSavePejabat} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-slate-400 mb-1 font-semibold">Nama Lengkap & Gelar</label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: H. AMINUDDIN P.S., S.Sos., M.M."
                  value={pejabatForm.nama}
                  onChange={(e) => setPejabatForm({ ...pejabatForm, nama: e.target.value })}
                  className={`w-full px-3 py-2 rounded-xl border ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white'
                      : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-slate-400 mb-1 font-semibold">NIP</label>
                  <input
                    type="text"
                    placeholder="19780412..."
                    value={pejabatForm.nip}
                    onChange={(e) => setPejabatForm({ ...pejabatForm, nip: e.target.value })}
                    className={`w-full px-3 py-2 rounded-xl border ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-slate-50 border-slate-300 text-slate-900'
                    }`}
                  />
                </div>

                <div>
                  <label className="block text-slate-400 mb-1 font-semibold">Pangkat / Golongan</label>
                  <input
                    type="text"
                    placeholder="PENATA TK. I (III/d)"
                    value={pejabatForm.pangkat}
                    onChange={(e) => setPejabatForm({ ...pejabatForm, pangkat: e.target.value })}
                    className={`w-full px-3 py-2 rounded-xl border ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-slate-50 border-slate-300 text-slate-900'
                    }`}
                  />
                </div>
              </div>

              <div>
                <label className="block text-slate-400 mb-1 font-semibold">Jabatan Struktural</label>
                <input
                  type="text"
                  required
                  placeholder="Contoh: Kepala Seksi Operasi dan Siaga"
                  value={pejabatForm.jabatan}
                  onChange={(e) => setPejabatForm({ ...pejabatForm, jabatan: e.target.value })}
                  className={`w-full px-3 py-2 rounded-xl border ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white'
                      : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />
              </div>

              <div className="pt-2">
                <label className="flex items-center gap-2 cursor-pointer select-none">
                  <input
                    type="checkbox"
                    checked={pejabatForm.is_default_smc}
                    onChange={(e) =>
                      setPejabatForm({ ...pejabatForm, is_default_smc: e.target.checked })
                    }
                    className="w-4 h-4 rounded text-orange-600 focus:ring-orange-500 border-slate-700"
                  />
                  <span className="font-semibold text-xs">Jadikan sebagai SAR Mission Coordinator (SMC) Default</span>
                </label>
                <p className="text-[10px] text-slate-400 ml-6 mt-0.5">
                  Pejabat ini akan otomatis terpilih saat pembuatan berkas Renops, Lapbang, dan naskah telegram BRA SAR.
                </p>
              </div>

              <div className="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowPejabatModal(false)}
                  className={`px-4 py-2 rounded-xl text-xs font-semibold border ${
                    isDarkMode ? 'border-slate-700 text-slate-300' : 'border-slate-300 text-slate-700'
                  }`}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-md shadow-orange-600/20"
                >
                  Simpan Data Pejabat
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
