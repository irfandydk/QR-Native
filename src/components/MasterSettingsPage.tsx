import React, { useState, useEffect, useRef } from 'react';
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
  Database,
  Download,
  Upload,
  FileCode,
  HardDrive,
  FileCheck,
  Navigation,
} from 'lucide-react';
import { MasterAlut, MasterTim, Pejabat, AppSettings } from '../types';
import { useTheme } from '../context/ThemeContext';
import { showSuccessAlert, showErrorAlert, showConfirmDialog, showToast } from '../utils/alerts';

interface MasterSettingsPageProps {
  onBack: () => void;
  masterAlut: MasterAlut[];
  masterTim: MasterTim[];
  onRefreshData: () => Promise<void>;
  initialTab?: 'alut' | 'tim' | 'pejabat' | 'satker' | 'keamanan' | 'backup' | 'admin';
  onUpdateCurrentUser?: (user: any) => void;
}

export const MasterSettingsPage: React.FC<MasterSettingsPageProps> = ({
  onBack,
  masterAlut,
  masterTim,
  onRefreshData,
  initialTab = 'alut',
  onUpdateCurrentUser,
}) => {
  const { isDarkMode, accentColors } = useTheme();

  // Active Tab
  const [activeTab, setActiveTab] = useState<'alut' | 'tim' | 'pejabat' | 'satker' | 'keamanan' | 'backup'>(
    initialTab === 'admin' ? 'keamanan' : initialTab
  );

  // Sync initialTab if changed from outside
  useEffect(() => {
    if (initialTab === 'admin') {
      setActiveTab('keamanan');
    } else if (initialTab) {
      setActiveTab(initialTab);
    }
  }, [initialTab]);

  // Database Backup / Restore states
  const [isExporting, setIsExporting] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Search queries
  const [searchAlut, setSearchAlut] = useState('');
  const [searchTim, setSearchTim] = useState('');
  const [searchPejabat, setSearchPejabat] = useState('');

  // Admin Profile & System Settings states (Poin 1, 2, 4)
  const [adminNama, setAdminNama] = useState('AMIRUDDIN AS, S.Sos.');
  const [adminNip, setAdminNip] = useState('197405101998031002');
  const [adminJabatan, setAdminJabatan] = useState('Kepala Kantor / Administrator Utama KPP Kendari');
  const [adminPangkat, setAdminPangkat] = useState('PEMBINA (IV/a)');
  const [adminUsername, setAdminUsername] = useState('admin');
  const [adminEmail, setAdminEmail] = useState('irfandydk@gmail.com');
  const [adminNoHp, setAdminNoHp] = useState('0811-4091-115');
  const [isSavingAdminProfile, setIsSavingAdminProfile] = useState(false);

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
      const [setRes, pejRes, profRes] = await Promise.all([
        fetch('/api/settings'),
        fetch('/api/settings/pejabat'),
        fetch('/api/auth/profile'),
      ]);

      const [setData, pejData, profData] = await Promise.all([
        setRes.json(),
        pejRes.json(),
        profRes.json().catch(() => ({ success: false })),
      ]);

      if (setData.success && setData.data) {
        setSettings((prev) => ({ ...prev, ...setData.data }));
      }
      if (pejData.success && Array.isArray(pejData.data)) {
        setPejabatList(pejData.data);
      }
      if (profData.success && profData.data) {
        const p = profData.data;
        if (p.nama) setAdminNama(p.nama);
        if (p.nip) setAdminNip(p.nip);
        if (p.jabatan) setAdminJabatan(p.jabatan);
        if (p.pangkat) setAdminPangkat(p.pangkat);
        if (p.username) setAdminUsername(p.username);
        if (p.email) setAdminEmail(p.email);
        if (p.no_hp) setAdminNoHp(p.no_hp);
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

  // Save Admin Profile Handler
  const handleSaveAdminProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!adminNama.trim()) {
      showErrorAlert('Validasi Gagal', 'Nama Administrator wajib diisi.');
      return;
    }
    if (!adminNip.trim()) {
      showErrorAlert('Validasi Gagal', 'NIP Administrator wajib diisi.');
      return;
    }

    setIsSavingAdminProfile(true);
    try {
      const res = await fetch('/api/auth/profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nama: adminNama.trim(),
          nip: adminNip.trim(),
          jabatan: adminJabatan.trim(),
          pangkat: adminPangkat.trim(),
          username: adminUsername.trim() || 'admin',
          email: adminEmail.trim() || 'irfandydk@gmail.com',
          no_hp: adminNoHp.trim(),
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Gagal menyimpan profil administrator.');
      }

      // Notify parent & application
      if (onUpdateCurrentUser && data.data) {
        onUpdateCurrentUser(data.data);
      }
      window.dispatchEvent(new CustomEvent('qr_sar_admin_updated', { detail: data.data }));

      await showSuccessAlert(
        'Data Admin Disimpan!',
        `Data Administrator (${adminNama} - NIP. ${adminNip}) dan pengaturan terkait berhasil diperbarui di database.`
      );
    } catch (err: any) {
      showErrorAlert('Gagal Menyimpan', err.message || 'Terjadi kesalahan sistem.');
    } finally {
      setIsSavingAdminProfile(false);
    }
  };

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

  // --- BACKUP & RESTORE DATABASE (JSON SUPORT APLIKASI) ---
  const handleExportDatabase = async () => {
    setIsExporting(true);
    try {
      showToast('Menyiapkan berkas cadangan database...', 'info');
      const res = await fetch('/api/database/export');
      if (!res.ok) {
        const errData = await res.json().catch(() => ({}));
        throw new Error(errData.error || 'Gagal mengunduh cadangan database.');
      }
      const data = await res.json();
      const jsonStr = JSON.stringify(data, null, 2);
      const blob = new Blob([jsonStr], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      const dateStr = new Date().toISOString().slice(0, 10);
      link.href = url;
      link.download = `BACKUP_DATABASE_SAR_KENDARI_${dateStr}.json`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

      showSuccessAlert(
        'Cadangan Berhasil Diunduh',
        `Berkas database JSON berhasil diekspor. Berkas ini kompatibel penuh untuk di-import kapan saja.`
      );
    } catch (err: any) {
      showErrorAlert('Gagal Ekspor Database', err.message || 'Terjadi kesalahan saat mengekspor database.');
    } finally {
      setIsExporting(false);
    }
  };

  const handleImportFileSelected = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Reset input so re-selecting same file triggers change
    e.target.value = '';

    if (!file.name.endsWith('.json')) {
      showErrorAlert('Format Berkas Tidak Valid', 'Silakan pilih berkas cadangan dengan format JSON (.json).');
      return;
    }

    try {
      setIsImporting(true);
      showToast('Membaca berkas cadangan JSON...', 'info');
      const text = await file.text();
      let parsedData: any;
      try {
        parsedData = JSON.parse(text);
      } catch (parseErr) {
        throw new Error('Berkas yang dipilih bukan file JSON yang valid atau rusak.');
      }

      // Validasi struktur minimal
      if (!parsedData || typeof parsedData !== 'object') {
        throw new Error('Struktur data cadangan tidak valid.');
      }

      // Deteksi fleksibel struktur data (bisa dibungkus dalam .data, .database, .backup, atau root langsung)
      let dataRoot: any = parsedData;
      if (parsedData.data && typeof parsedData.data === 'object' && !Array.isArray(parsedData.data)) {
        dataRoot = parsedData.data;
      } else if (parsedData.database && typeof parsedData.database === 'object') {
        dataRoot = parsedData.database;
      } else if (parsedData.backup && typeof parsedData.backup === 'object') {
        dataRoot = parsedData.backup;
      }

      const statsRoot = (parsedData.stats && typeof parsedData.stats === 'object')
        ? parsedData.stats
        : (dataRoot.stats && typeof dataRoot.stats === 'object' ? dataRoot.stats : null);

      const findArr = (...keys: string[]): any[] => {
        for (const k of keys) {
          if (Array.isArray(dataRoot[k]) && dataRoot[k].length > 0) return dataRoot[k];
          if (Array.isArray(parsedData[k]) && parsedData[k].length > 0) return parsedData[k];
        }
        for (const k of keys) {
          if (Array.isArray(dataRoot[k])) return dataRoot[k];
          if (Array.isArray(parsedData[k])) return parsedData[k];
        }
        return [];
      };

      const arrOperasi = findArr('operasi', 'operasi_sar', 'operasis', 'operations', 'data_operasi');
      const arrBra = findArr('bra_sar', 'bra', 'brasar', 'bra_sars', 'naskah_bra');
      const arrKorban = findArr('korban', 'korbans', 'data_korban', 'victims');
      const arrRenops = findArr('renops', 'rencana_operasi', 'renop');
      const arrLapbang = findArr('laporan_harian', 'lapbang', 'sitrep', 'laporans');
      const arrAlut = findArr('master_alut', 'alut', 'aluts', 'masterAlut');
      const arrTim = findArr('master_tim', 'tim', 'tims', 'masterTim');
      const arrUnits = findArr('sar_units', 'units', 'sarUnits', 'pangkalan');

      const importedSettings = (dataRoot.app_settings && typeof dataRoot.app_settings === 'object')
        ? dataRoot.app_settings
        : (dataRoot.settings && typeof dataRoot.settings === 'object'
            ? dataRoot.settings
            : (parsedData.app_settings && typeof parsedData.app_settings === 'object'
                ? parsedData.app_settings
                : (parsedData.settings && typeof parsedData.settings === 'object' ? parsedData.settings : null)));

      const totalOperasi = arrOperasi.length || statsRoot?.total_operasi || statsRoot?.operasi || statsRoot?.total_ops || 0;
      const totalBra = arrBra.length || statsRoot?.total_bra_sar || statsRoot?.bra_sar || statsRoot?.total_bra || statsRoot?.bra || 0;
      const totalKorban = arrKorban.length || statsRoot?.total_korban || statsRoot?.korban || 0;
      const totalRenops = arrRenops.length || statsRoot?.total_renops || statsRoot?.renops || 0;
      const totalLapbang = arrLapbang.length || statsRoot?.total_laporan_harian || statsRoot?.total_lapbang || statsRoot?.laporan_harian || statsRoot?.lapbang || 0;
      const totalAlut = arrAlut.length || statsRoot?.total_master_alut || statsRoot?.master_alut || statsRoot?.alut || 0;
      const totalTim = arrTim.length || statsRoot?.total_master_tim || statsRoot?.master_tim || statsRoot?.tim || 0;
      const totalUnits = arrUnits.length || statsRoot?.total_sar_units || statsRoot?.sar_units || 0;

      const sampleOp = arrOperasi[0]?.nama_operasi ? arrOperasi[0].nama_operasi : '';
      const sampleBra = arrBra[0]?.no_bra ? arrBra[0].no_bra : '';
      const satkerName = importedSettings?.satker_nama || '';
      const hasCustomLogo = Boolean(importedSettings?.logo_url);

      const confirmHtml = `
        <div style="text-align: left; font-size: 13px; line-height: 1.6; margin-top: 4px;">
          <p style="margin: 0 0 10px 0; color: #64748b; font-weight: 500;">
            Berkas cadangan berhasil dibaca dan diverifikasi. Berikut rincian data real yang terdeteksi di dalam berkas:
          </p>
          <div style="background: rgba(249, 115, 22, 0.08); border: 1px solid rgba(249, 115, 22, 0.25); border-radius: 10px; padding: 12px 14px; margin-bottom: 12px;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px 14px; font-size: 12px;">
              <div>🚁 <b>Operasi SAR:</b> <span style="color: #ea580c; font-weight: 700;">${totalOperasi} Data</span></div>
              <div>📄 <b>Naskah BRA SAR:</b> <span style="color: #ea580c; font-weight: 700;">${totalBra} Naskah</span></div>
              <div>👥 <b>Data Korban:</b> <span style="color: #ea580c; font-weight: 700;">${totalKorban} Orang</span></div>
              <div>📋 <b>Rencana Operasi:</b> <span style="color: #ea580c; font-weight: 700;">${totalRenops} Renops</span></div>
              <div>📝 <b>Laporan Harian:</b> <span style="color: #ea580c; font-weight: 700;">${totalLapbang} Lapbang</span></div>
              <div>🛠️ <b>Alut & Tim:</b> <span style="color: #ea580c; font-weight: 700;">${totalAlut} Alut • ${totalTim} Tim</span></div>
              ${totalUnits > 0 ? `<div>⚓ <b>Pangkalan/Unit:</b> <span style="color: #ea580c; font-weight: 700;">${totalUnits} Unit</span></div>` : ''}
            </div>
            ${satkerName || sampleOp || sampleBra || hasCustomLogo ? `
              <div style="margin-top: 10px; padding-top: 8px; border-top: 1px dashed rgba(249, 115, 22, 0.3); font-size: 11px; color: #475569;">
                ${satkerName ? `<div>🏢 <b>Satker:</b> ${satkerName}</div>` : ''}
                ${sampleOp ? `<div>🎯 <b>Contoh Operasi:</b> ${sampleOp}</div>` : ''}
                ${sampleBra ? `<div>🏷️ <b>Contoh BRA:</b> ${sampleBra}</div>` : ''}
                ${hasCustomLogo ? `<div>✨ <b>Logo Satker:</b> Terdeteksi & siap diterapkan langsung</div>` : ''}
              </div>
            ` : ''}
          </div>
          <p style="font-size: 12px; color: #64748b; margin: 0;">
            Apakah Anda yakin ingin memulihkan (import) database ini ke sistem? Seluruh koleksi akan disinkronkan secara instan.
          </p>
        </div>
      `;

      const confirmed = await showConfirmDialog(
        'Konfirmasi Pemulihan Database',
        confirmHtml,
        'Ya, Pulihkan Sekarang',
        false
      );

      if (!confirmed) {
        setIsImporting(false);
        return;
      }

      showToast('Memulihkan database sistem...', 'info');
      const res = await fetch('/api/database/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(parsedData),
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.error || 'Gagal memulihkan database.');
      }

      // Terapkan pengaturan satker dan logo langsung ke aplikasi
      const finalSettings = result.settings || importedSettings;
      if (finalSettings) {
        setSettings((prev) => ({ ...prev, ...finalSettings }));
        if (finalSettings.logo_url) {
          localStorage.setItem('sar_custom_logo_url', finalSettings.logo_url);
        }
        if (Array.isArray(finalSettings.pejabat_list) && finalSettings.pejabat_list.length > 0) {
          setPejabatList(finalSettings.pejabat_list);
        }
        // Kirim event global agar seluruh komponen (Header, Dokumen BRA, dll) langsung memakai logo & satker baru
        window.dispatchEvent(new CustomEvent('satker-settings-updated', { detail: finalSettings }));
      }

      showSuccessAlert(
        'Database Berhasil Dipulihkan',
        `Database telah berhasil dipulihkan secara penuh:\n` +
        `• ${result.stats?.operasi ?? totalOperasi} Operasi SAR\n` +
        `• ${result.stats?.bra_sar ?? totalBra} Naskah BRA SAR\n` +
        `• ${result.stats?.korban ?? totalKorban} Data Korban\n` +
        `• ${result.stats?.renops ?? totalRenops} Rencana Operasi\n` +
        `• ${result.stats?.laporan_harian ?? totalLapbang} Laporan Perkembangan\n` +
        `• ${result.stats?.master_alut ?? totalAlut} Alut & ${result.stats?.master_tim ?? totalTim} Tim SAR`
      );

      // Refresh seluruh data sistem
      await onRefreshData();
      await fetchSettingsAndPejabat();

      showSuccessAlert(
        'Database Berhasil Dipulihkan!',
        `Sinkronisasi tuntas: ${result.stats?.operasi ?? totalOperasi} Operasi SAR, ${result.stats?.bra_sar ?? totalBra} BRA SAR, Master Data, dan Pengaturan Satker/Logo telah langsung diterapkan ke aplikasi.`
      );
    } catch (err: any) {
      showErrorAlert('Gagal Memulihkan Database', err.message || 'Terjadi kesalahan pemulihan.');
    } finally {
      setIsImporting(false);
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

          <button
            onClick={() => setActiveTab('backup')}
            className={`flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap ${
              activeTab === 'backup'
                ? 'bg-orange-500 text-white shadow-md shadow-orange-500/20'
                : isDarkMode
                ? 'text-slate-400 hover:text-slate-200 hover:bg-slate-800'
                : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
            }`}
          >
            <Database className="w-4 h-4" />
            <span>Cadangan & Impor/Ekspor Database</span>
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

            {/* SECTION 1: PROFIL & DATA ADMINISTRATOR UTAMA (Poin 1, 2, 3) */}
            <div className={`p-6 rounded-2xl border ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800 mb-6">
                <div className="flex items-center gap-3">
                  <div className="p-2.5 rounded-xl bg-orange-500/10 text-orange-500 border border-orange-500/20">
                    <ShieldCheck className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-sm sm:text-base">Data Profil Administrator Sistem</h3>
                    <p className="text-xs text-slate-400">
                      Kelola identitas resmi, NIP kepegawaian, dan kontak administrator yang tampil di header dan dokumen operasional
                    </p>
                  </div>
                </div>
                <span className="hidden sm:inline-block px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-orange-500/10 text-orange-500 border border-orange-500/25">
                  AKUN RESMI KPP KENDARI
                </span>
              </div>

              <form onSubmit={handleSaveAdminProfile} className="space-y-4">
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  {/* Nama Lengkap Admin */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                      Nama Lengkap Administrator (dengan Gelar) <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={adminNama}
                      onChange={(e) => setAdminNama(e.target.value)}
                      placeholder="Contoh: AMIRUDDIN AS, S.Sos."
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                  </div>

                  {/* NIP Admin */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                      Nomor Induk Pegawai (NIP) <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      required
                      value={adminNip}
                      onChange={(e) => setAdminNip(e.target.value)}
                      placeholder="Contoh: 197405101998031002"
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs font-mono border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                  </div>

                  {/* Pangkat / Golongan */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                      Pangkat / Golongan Ruang
                    </label>
                    <input
                      type="text"
                      value={adminPangkat}
                      onChange={(e) => setAdminPangkat(e.target.value)}
                      placeholder="Contoh: PEMBINA (IV/a)"
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                  </div>

                  {/* Jabatan Resmi */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                      Jabatan Resmi Administrator
                    </label>
                    <input
                      type="text"
                      value={adminJabatan}
                      onChange={(e) => setAdminJabatan(e.target.value)}
                      placeholder="Contoh: Kepala Kantor / Administrator Utama KPP Kendari"
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                  </div>

                  {/* No HP / WhatsApp */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                      Nomor Handphone / WhatsApp Siaga
                    </label>
                    <input
                      type="text"
                      value={adminNoHp}
                      onChange={(e) => setAdminNoHp(e.target.value)}
                      placeholder="Contoh: 0811-4091-115"
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs font-mono border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                  </div>

                  {/* Email Resmi */}
                  <div>
                    <label className="block text-xs font-semibold mb-1.5 text-slate-400">
                      Email Pemulihan Sandi & Notifikasi <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="email"
                      required
                      value={adminEmail}
                      onChange={(e) => setAdminEmail(e.target.value)}
                      placeholder="irfandydk@gmail.com"
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                        isDarkMode
                          ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
                          : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                      }`}
                    />
                  </div>
                </div>

                <div className="pt-3 flex justify-end">
                  <button
                    type="submit"
                    disabled={isSavingAdminProfile}
                    className="inline-flex items-center gap-2 px-6 py-2.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-orange-600/30 transition-all disabled:opacity-50 cursor-pointer"
                  >
                    <Save className="w-4 h-4" />
                    <span>{isSavingAdminProfile ? 'Menyimpan Profil...' : 'Simpan Profil Administrator'}</span>
                  </button>
                </div>
              </form>
            </div>

            {/* SECTION 2: GANTI KATA SANDI ADMINISTRATOR */}
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

            {/* SECTION 4: EMAIL RESET TESTING & INFO CARD (Poin 4) */}
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
                      Fitur lupa kata sandi di halaman login terhubung langsung ke email resmi <strong className="text-cyan-400">{adminEmail || 'irfandydk@gmail.com'}</strong>. Anda dapat menguji pengiriman link pemulihan kapan saja.
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

        {/* TAB 6: CADANGAN & IMPOR/EKSPOR DATABASE (JSON SUPORT APLIKASI) */}
        {activeTab === 'backup' && (
          <div className="space-y-6 animate-in fade-in duration-150">
            {/* Hidden JSON file input */}
            <input
              type="file"
              ref={fileInputRef}
              accept=".json,application/json"
              onChange={handleImportFileSelected}
              className="hidden"
            />

            {/* Hero / Header Card */}
            <div className={`p-6 rounded-2xl border ${
              isDarkMode
                ? 'bg-gradient-to-br from-slate-900 via-slate-900 to-orange-950/30 border-slate-800'
                : 'bg-gradient-to-br from-white via-white to-orange-50/50 border-slate-200 shadow-sm'
            }`}>
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="flex items-start gap-4">
                  <div className="p-3.5 rounded-2xl bg-orange-500/10 text-orange-500 border border-orange-500/20 shrink-0">
                    <Database className="w-7 h-7" />
                  </div>
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-orange-500/10 text-orange-400 border border-orange-500/20">
                        PERSISTENSI BASIS DATA JSON
                      </span>
                      <span className="text-xs text-slate-400">•</span>
                      <span className="text-xs text-emerald-500 font-semibold flex items-center gap-1">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        Kompatibel 100% dengan Aplikasi
                      </span>
                    </div>
                    <h2 className="text-lg sm:text-xl font-black mt-1">
                      Manajemen Cadangan & Pemulihan Basis Data
                    </h2>
                    <p className="text-xs text-slate-400 mt-1 max-w-2xl leading-relaxed">
                      Sediakan berkas cadangan database dalam format JSON terstruktur yang dapat di-export dan di-import kapan saja. Fitur ini melindungi seluruh data Operasi SAR, Korban, Rencana Operasi (Renops), Arsip BRA SAR, dan Pengaturan Satker agar tidak hilang atau berubah saat pembaruan versi aplikasi / deploy ulang.
                    </p>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 shrink-0">
                  <button
                    type="button"
                    onClick={handleExportDatabase}
                    disabled={isExporting}
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 shadow-lg shadow-orange-600/25 transition-all disabled:opacity-50 cursor-pointer"
                  >
                    <Download className={`w-4 h-4 ${isExporting ? 'animate-bounce' : ''}`} />
                    <span>{isExporting ? 'Mengekspor Data...' : 'Ekspor Database (.json)'}</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={isImporting}
                    className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold border transition-all disabled:opacity-50 cursor-pointer ${
                      isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                        : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-300 shadow-sm'
                    }`}
                  >
                    <Upload className={`w-4 h-4 text-orange-500 ${isImporting ? 'animate-pulse' : ''}`} />
                    <span>{isImporting ? 'Memulihkan Data...' : 'Impor / Pulihkan Database'}</span>
                  </button>
                </div>
              </div>
            </div>

            {/* 2-Column Action Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              {/* Card 1: Ekspor Database */}
              <div className={`p-6 rounded-2xl border flex flex-col justify-between space-y-4 ${
                isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
              }`}>
                <div className="space-y-3">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 rounded-xl bg-amber-500/10 text-amber-500 border border-amber-500/20">
                      <Download className="w-5 h-5" />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm">1. Ekspor Database (Backup JSON)</h3>
                      <p className="text-xs text-slate-400">Unduh snapshot seluruh data operasional ke perangkat lokal</p>
                    </div>
                  </div>

                  <p className="text-xs text-slate-300 dark:text-slate-400 leading-relaxed">
                    Menghasilkan satu berkas <code className="px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-400 font-mono text-[11px]">.json</code> yang berisi seluruh riwayat Operasi SAR, data korban, rencana operasi harian (Renops), laporan perkembangan (Lapbang), naskah telegram resmi BRA SAR, daftar pejabat/SMC, master alut, serta konfigurasi global satker.
                  </p>

                  <div className="space-y-2 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Format standar JSON native, aman dan mudah diarsipkan.</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Dapat dibuka atau diverifikasi secara independen.</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Nama berkas otomatis mencantumkan tanggal pembuatan cadangan.</span>
                    </div>
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-slate-800">
                  <button
                    type="button"
                    onClick={handleExportDatabase}
                    disabled={isExporting}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 shadow-md shadow-orange-600/20 transition-all disabled:opacity-50 cursor-pointer"
                  >
                    <Download className="w-4 h-4" />
                    <span>{isExporting ? 'Sedang Mengunduh...' : 'Unduh Cadangan Database (.json)'}</span>
                  </button>
                </div>
              </div>

              {/* Card 2: Impor / Pulihkan Database */}
              <div className={`p-6 rounded-2xl border flex flex-col justify-between space-y-4 ${
                isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
              }`}>
                <div className="space-y-3">
                  <div className="flex items-center gap-3">
                    <div className="p-2.5 rounded-xl bg-cyan-500/10 text-cyan-500 border border-cyan-500/20">
                      <Upload className="w-5 h-5" />
                    </div>
                    <div>
                      <h3 className="font-bold text-sm">2. Impor Database (Restore JSON)</h3>
                      <p className="text-xs text-slate-400">Pulihkan seluruh data dari berkas cadangan JSON yang telah diekspor</p>
                    </div>
                  </div>

                  <p className="text-xs text-slate-300 dark:text-slate-400 leading-relaxed">
                    Unggah berkas cadangan database JSON untuk memulihkan seluruh data operasional SAR ke dalam sistem secara instan. Sistem akan menampilkan rincian jumlah item untuk konfirmasi sebelum menerapkan perubahan.
                  </p>

                  <div className="p-3 rounded-xl border border-amber-500/20 bg-amber-500/5 text-amber-600 dark:text-amber-400 text-xs space-y-1">
                    <div className="flex items-center gap-1.5 font-bold">
                      <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                      <span>Perhatian Sebelum Memulihkan:</span>
                    </div>
                    <p className="text-[11px] leading-relaxed text-slate-400">
                      Proses import akan memperbarui koleksi database dengan isi berkas yang diunggah. Pastikan Anda mengunduh cadangan terkini sebelum melakukan pemulihan.
                    </p>
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-slate-800">
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={isImporting}
                    className={`w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold border transition-all disabled:opacity-50 cursor-pointer ${
                      isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 text-white border-slate-700'
                        : 'bg-slate-100 hover:bg-slate-200 text-slate-800 border-slate-300'
                    }`}
                  >
                    <Upload className="w-4 h-4 text-orange-500" />
                    <span>{isImporting ? 'Sedang Memproses Berkas...' : 'Pilih Berkas Cadangan JSON & Pulihkan'}</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Scope of Backup Checklist Table */}
            <div className={`p-6 rounded-2xl border ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center gap-2 mb-4">
                <FileCheck className="w-5 h-5 text-emerald-500" />
                <h3 className="font-bold text-sm">Cakupan Data yang Disimpan & Dilindungi</h3>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs">
                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-orange-400">1. Operasi SAR & Status</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Semua riwayat operasi, jenis musibah, koordinat lokasi, status operasi (Aktif/Selesai/Tutup), dan log kejadian.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-cyan-400">2. Data Korban & Medis</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Identitas korban, kondisi (Selamat, Meninggal, Hilang), serta data evakuasi ke fasilitas medis.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-amber-400">3. Rencana Operasi & Lapbang</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Seluruh berkas Renops H.1 s.d H.7, pembagian SRU/Search Area, dan Laporan Perkembangan harian.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-emerald-400">4. Arsip Telegram BRA SAR</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Arsip lengkap naskah telegram BRA MAPEL, Pergerakan Kapal, dan BRA SAR Operasi SAR beserta tanda tangan QR digital terverifikasi.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-purple-400">5. Master Alut & Tim SAR</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Daftar alut perairan, darat, palsar evakuasi/medis, serta master tim rescue/pos SAR terdaftar.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-rose-400">6. Pengaturan Satker & Pejabat</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Nama Satker, Call Sign, Logo Global, Daftar Pejabat/SMC, Alamat, Kontak, dan kredensial admin.
                  </p>
                </div>
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
                  placeholder="Contoh: Syaiful Wijanarta, S.E."
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
