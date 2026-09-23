import React, { useState, useEffect, useRef } from 'react';
import {
  ArrowLeft,
  ShieldCheck,
  Building2,
  Database,
  Save,
  Check,
  RefreshCw,
  Image as ImageIcon,
  Radio,
  Phone,
  Mail,
  MapPin,
  Lock,
  Eye,
  EyeOff,
  CheckCircle2,
  AlertCircle,
  Download,
  Upload,
  HardDrive,
  FileCheck,
  Sliders,
  Sparkles,
  FileCode,
  User,
  Compass,
  Layers,
  Map,
} from 'lucide-react';
import { AppSettings, UserSession } from '../types';
import { useTheme } from '../context/ThemeContext';
import { showSuccessAlert, showErrorAlert, showConfirmDialog, showToast } from '../utils/alerts';
import { DEFAULT_BASARNAS_LOGO } from './BasarnasLogo';

interface AdminSettingsPageProps {
  onBack: () => void;
  onRefreshData?: () => Promise<void>;
  initialTab?: 'admin' | 'satker' | 'backup';
  onUpdateCurrentUser?: (user: any) => void;
}

export const AdminSettingsPage: React.FC<AdminSettingsPageProps> = ({
  onBack,
  onRefreshData,
  initialTab = 'admin',
  onUpdateCurrentUser,
}) => {
  const { isDarkMode, accentColors } = useTheme();

  // Active Tab: 'admin' | 'satker' | 'backup'
  const [activeTab, setActiveTab] = useState<'admin' | 'satker' | 'backup'>(initialTab);

  useEffect(() => {
    if (initialTab) {
      setActiveTab(initialTab);
    }
  }, [initialTab]);

  // Loading states
  const [isLoading, setIsLoading] = useState(true);
  const [isSavingAdmin, setIsSavingAdmin] = useState(false);
  const [isSavingSatker, setIsSavingSatker] = useState(false);
  const [isUpdatingPassword, setIsUpdatingPassword] = useState(false);
  const [isExporting, setIsExporting] = useState(false);
  const [isImporting, setIsImporting] = useState(false);

  // Hidden File input ref for restore
  const fileInputRef = useRef<HTMLInputElement>(null);
  const logoFileInputRef = useRef<HTMLInputElement>(null);

  // --- TAB 1: ADMIN PROFILE & SECURITY STATES ---
  const [adminNama, setAdminNama] = useState('AMIRUDDIN AS, S.Sos.');
  const [adminNip, setAdminNip] = useState('197405101998031002');
  const [adminJabatan, setAdminJabatan] = useState('Kepala Kantor / Administrator Utama KPP Kendari');
  const [adminPangkat, setAdminPangkat] = useState('PEMBINA (IV/a)');
  const [adminUsername, setAdminUsername] = useState('admin');
  const [adminEmail, setAdminEmail] = useState('irfandydk@gmail.com');
  const [adminNoHp, setAdminNoHp] = useState('0811-4091-115');

  // Change Password states
  const [oldPassword, setOldPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [showOldPass, setShowOldPass] = useState(false);
  const [showNewPass, setShowNewPass] = useState(false);
  const [showConfirmPass, setShowConfirmPass] = useState(false);

  // --- TAB 2: SATKER & APP IDENTITY STATES ---
  const [satkerKode, setSatkerKode] = useState('SAR-304');
  const [satkerNama, setSatkerNama] = useState('KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI');
  const [satkerAlamat, setSatkerAlamat] = useState('Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan, Sulawesi Tenggara');
  const [satkerNoTelp, setSatkerNoTelp] = useState('(0401) 3122115 / Emergency 115');
  const [satkerCallSign, setSatkerCallSign] = useState('KANSAR KENDARI');
  const [satkerFrekuensi, setSatkerFrekuensi] = useState('115.000 MHz / 5827 kHz');
  const [satkerLogoUrl, setSatkerLogoUrl] = useState(DEFAULT_BASARNAS_LOGO);

  // --- TAB 3: BACKUP / RESTORE LIVE STATS ---
  const [dbStats, setDbStats] = useState<{
    operasi: number;
    korban: number;
    renops: number;
    laporan_harian: number;
    bra_sar: number;
    master_alut: number;
    master_tim: number;
    sar_units: number;
    pejabat: number;
    gis_routes: number;
    custom_icons: number;
  }>({
    operasi: 0,
    korban: 0,
    renops: 0,
    laporan_harian: 0,
    bra_sar: 0,
    master_alut: 0,
    master_tim: 0,
    sar_units: 0,
    pejabat: 0,
    gis_routes: 0,
    custom_icons: 0,
  });

  // Load initial settings and live stats
  const fetchAllSettings = async () => {
    try {
      setIsLoading(true);
      const [profRes, settRes, expRes] = await Promise.all([
        fetch('/api/auth/profile').catch(() => null),
        fetch('/api/settings').catch(() => null),
        fetch('/api/database/export').catch(() => null),
      ]);

      if (profRes && profRes.ok) {
        const pData = await profRes.json();
        if (pData.success && pData.data) {
          setAdminNama(pData.data.nama || 'AMIRUDDIN AS, S.Sos.');
          setAdminNip(pData.data.nip || '197405101998031002');
          setAdminJabatan(pData.data.jabatan || 'Kepala Kantor / Administrator Utama KPP Kendari');
          setAdminPangkat(pData.data.pangkat || 'PEMBINA (IV/a)');
          setAdminUsername(pData.data.username || 'admin');
          setAdminEmail(pData.data.email || 'irfandydk@gmail.com');
          setAdminNoHp(pData.data.no_hp || '0811-4091-115');
        }
      }

      if (settRes && settRes.ok) {
        const sData = await settRes.json();
        if (sData.success && sData.data) {
          const s = sData.data;
          setSatkerKode(s.satker_kode || 'SAR-304');
          setSatkerNama(s.satker_nama || 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI');
          setSatkerAlamat(s.alamat || 'Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan, Sulawesi Tenggara');
          setSatkerNoTelp(s.no_telp || '(0401) 3122115 / Emergency 115');
          setSatkerCallSign(s.call_sign || 'KANSAR KENDARI');
          setSatkerFrekuensi(s.frekuensi_default || '115.000 MHz / 5827 kHz');
          
          const localLogo = typeof window !== 'undefined' ? localStorage.getItem('sar_custom_logo_url') : null;
          setSatkerLogoUrl(localLogo || s.logo_url || DEFAULT_BASARNAS_LOGO);
        }
      }

      // Count GIS points, polygons, routes and icons from localStorage
      let gisCount = 0;
      let iconsCount = 0;
      let pointsCount = 0;
      let polysCount = 0;
      try {
        const pts = localStorage.getItem('basarnas_saved_map_points');
        if (pts) pointsCount = JSON.parse(pts).length || 0;
        const polys = localStorage.getItem('basarnas_saved_polygons');
        if (polys) polysCount = JSON.parse(polys).length || 0;
        const r = localStorage.getItem('basarnas_saved_distance_routes');
        if (r) gisCount = JSON.parse(r).length || 0;
        const ic = localStorage.getItem('basarnas_saved_custom_icons');
        if (ic) iconsCount = JSON.parse(ic).length || 0;
      } catch {}

      if (expRes && expRes.ok) {
        const expData = await expRes.json();
        if (expData.stats) {
          setDbStats({
            operasi: expData.stats.total_operasi || 0,
            korban: expData.stats.total_korban || 0,
            renops: expData.stats.total_renops || 0,
            laporan_harian: expData.stats.total_laporan_harian || 0,
            bra_sar: expData.stats.total_bra_sar || 0,
            master_alut: expData.stats.total_master_alut || 0,
            master_tim: expData.stats.total_master_tim || 0,
            sar_units: expData.stats.total_sar_units || expData.data?.sar_units?.length || 0,
            pejabat: expData.stats.total_pejabat || expData.data?.app_settings?.pejabat_list?.length || 0,
            gis_routes: gisCount + pointsCount + polysCount,
            custom_icons: iconsCount,
          });
        }
      }
    } catch (err) {
      console.error('Failed to load settings:', err);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchAllSettings();
  }, []);

  // --- SAVE ADMIN PROFILE ---
  const handleSaveAdminProfile = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!adminNama.trim() || !adminUsername.trim()) {
      showErrorAlert('Validasi Gagal', 'Nama lengkap dan username administrator wajib diisi.');
      return;
    }

    try {
      setIsSavingAdmin(true);
      const res = await fetch('/api/auth/profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          nama: adminNama.trim(),
          nip: adminNip.trim(),
          jabatan: adminJabatan.trim(),
          pangkat: adminPangkat.trim(),
          username: adminUsername.trim(),
          email: adminEmail.trim(),
          no_hp: adminNoHp.trim(),
        }),
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Gagal menyimpan profil administrator.');
      }

      // Synchronize session
      const updatedUser = {
        nama: adminNama.trim(),
        nip: adminNip.trim(),
        jabatan: adminJabatan.trim(),
        pangkat: adminPangkat.trim(),
        username: adminUsername.trim(),
        email: adminEmail.trim(),
        no_hp: adminNoHp.trim(),
      };
      if (onUpdateCurrentUser) {
        onUpdateCurrentUser(updatedUser);
      }
      try {
        const cur = localStorage.getItem('basarnas_user_session');
        if (cur) {
          const parsed = JSON.parse(cur);
          localStorage.setItem('basarnas_user_session', JSON.stringify({ ...parsed, ...updatedUser }));
        }
        window.dispatchEvent(new CustomEvent('qr_sar_admin_updated', { detail: updatedUser }));
      } catch {}

      showSuccessAlert(
        'Profil Administrator Tersimpan',
        'Informasi identitas dan kontak dinas administrator berhasil diperbarui.'
      );
    } catch (err: any) {
      showErrorAlert('Gagal Menyimpan Profil', err.message || 'Terjadi kesalahan sistem.');
    } finally {
      setIsSavingAdmin(false);
    }
  };

  // --- UPDATE PASSWORD ---
  const handleUpdatePassword = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!oldPassword.trim()) {
      showErrorAlert('Validasi Gagal', 'Silakan masukkan kata sandi saat ini / sandi lama.');
      return;
    }
    if (!newPassword.trim() || newPassword.length < 4) {
      showErrorAlert('Validasi Gagal', 'Kata sandi baru minimal harus 4 karakter.');
      return;
    }
    if (newPassword !== confirmPassword) {
      showErrorAlert('Validasi Gagal', 'Konfirmasi kata sandi baru tidak cocok.');
      return;
    }

    try {
      setIsUpdatingPassword(true);
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
        throw new Error(data.error || 'Gagal memperbarui kata sandi.');
      }

      setOldPassword('');
      setNewPassword('');
      setConfirmPassword('');
      showSuccessAlert(
        'Kata Sandi Berhasil Diperbarui',
        'Kata sandi akun administrator berhasil diubah. Gunakan kata sandi baru untuk login selanjutnya.'
      );
    } catch (err: any) {
      showErrorAlert('Gagal Mengubah Sandi', err.message || 'Terjadi kesalahan pada server.');
    } finally {
      setIsUpdatingPassword(false);
    }
  };

  // --- SAVE SATKER & LOGO ---
  const handleSaveSatker = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!satkerKode.trim() || !satkerNama.trim()) {
      showErrorAlert('Validasi Gagal', 'Kode satker dan nama kantor SAR wajib diisi.');
      return;
    }

    try {
      setIsSavingSatker(true);
      const payload: Partial<AppSettings> = {
        satker_kode: satkerKode.trim(),
        satker_nama: satkerNama.trim(),
        alamat: satkerAlamat.trim(),
        no_telp: satkerNoTelp.trim(),
        call_sign: satkerCallSign.trim(),
        frekuensi_default: satkerFrekuensi.trim(),
        logo_url: satkerLogoUrl.trim() || DEFAULT_BASARNAS_LOGO,
      };

      const res = await fetch('/api/settings', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Gagal menyimpan pengaturan satker.');
      }

      // Sync logo locally to immediate reflection
      try {
        const finalLogo = satkerLogoUrl.trim() || DEFAULT_BASARNAS_LOGO;
        localStorage.setItem('sar_custom_logo_url', finalLogo);
        window.dispatchEvent(new CustomEvent('sar_logo_updated', { detail: { logo_url: finalLogo } }));
      } catch {}

      showSuccessAlert(
        'Pengaturan Satker Disimpan',
        'Identitas kantor SAR, call sign radio, frekuensi, dan logo resmi berhasil diperbarui dan disinkronkan ke server.'
      );
      if (onRefreshData) onRefreshData();
    } catch (err: any) {
      showErrorAlert('Gagal Menyimpan Satker', err.message || 'Terjadi kesalahan sistem.');
    } finally {
      setIsSavingSatker(false);
    }
  };

  // Upload logo from local file directly to server
  const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
      showErrorAlert('Berkas Tidak Valid', 'Silakan pilih berkas gambar (PNG, JPG, SVG, atau WebP).');
      return;
    }

    const reader = new FileReader();
    reader.onload = async () => {
      if (typeof reader.result === 'string') {
        const base64Data = reader.result;
        setSatkerLogoUrl(base64Data);
        // Direct upload to server for cross-device persistence
        try {
          const upRes = await fetch('/api/upload/logo', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              imageBase64: base64Data,
              extension: file.name.split('.').pop() || 'png',
            }),
          });
          const upData = await upRes.json();
          if (upData.success && upData.logo_url) {
            setSatkerLogoUrl(upData.logo_url);
            try {
              localStorage.setItem('sar_custom_logo_url', upData.logo_url);
              window.dispatchEvent(new CustomEvent('sar_logo_updated', { detail: { logo_url: upData.logo_url } }));
            } catch {}
            showToast('Logo berhasil diunggah ke server dan disinkronkan ke seluruh sistem!', 'success');
            return;
          }
        } catch {
          // Fallback to local
        }
        showToast('Gambar logo berhasil dimuat. Klik "Simpan Pengaturan Satker" untuk menerapkan.', 'info');
      }
    };
    reader.readAsDataURL(file);
  };

  // --- COMPREHENSIVE BACKUP (EXPORT) ---
  const handleExportFullBackup = async () => {
    setIsExporting(true);
    try {
      showToast('Menyiapkan cadangan lengkap database dan aplikasi...', 'info');

      // 1. Fetch server database
      const res = await fetch('/api/database/export');
      if (!res.ok) {
        const errData = await res.json().catch(() => ({}));
        throw new Error(errData.error || 'Gagal mengunduh cadangan database dari server.');
      }
      const serverPayload = await res.json();

      // 2. Fetch all persistent client-side data
      let clientGisRoutes: any[] = [];
      let clientCustomIcons: any[] = [];
      let clientGisPoints: any[] = [];
      let clientGisPolygons: any[] = [];
      let clientLogo = '';
      try {
        const r = localStorage.getItem('basarnas_saved_distance_routes');
        if (r) clientGisRoutes = JSON.parse(r);
        const ic = localStorage.getItem('basarnas_saved_custom_icons');
        if (ic) clientCustomIcons = JSON.parse(ic);
        const pts = localStorage.getItem('basarnas_saved_map_points');
        if (pts) clientGisPoints = JSON.parse(pts);
        const polys = localStorage.getItem('basarnas_saved_polygons');
        if (polys) clientGisPolygons = JSON.parse(polys);
        clientLogo = localStorage.getItem('sar_custom_logo_url') || '';
      } catch (err) {
        console.warn('Could not read client local state:', err);
      }

      // 3. Compose Comprehensive Full Backup Object (100% Covered)
      const fullArchive = {
        version: '3.0.0-COMPREHENSIVE',
        application: 'SISTEM INFORMASI OPERASIONAL & QR SAR DIGITAL',
        office: 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI',
        exported_at: new Date().toISOString(),
        coverage_summary: {
          total_operasi: (serverPayload.data?.operasi || []).length,
          total_korban: (serverPayload.data?.korban || []).length,
          total_renops: (serverPayload.data?.renops || []).length,
          total_laporan_harian: (serverPayload.data?.laporan_harian || []).length,
          total_bra_sar: (serverPayload.data?.bra_sar || []).length,
          total_master_alut: (serverPayload.data?.master_alut || []).length,
          total_master_tim: (serverPayload.data?.master_tim || []).length,
          total_sar_units: (serverPayload.data?.sar_units || []).length,
          total_pejabat: (serverPayload.data?.app_settings?.pejabat_list || []).length,
          total_gis_points: clientGisPoints.length,
          total_gis_polygons: clientGisPolygons.length,
          total_gis_routes: clientGisRoutes.length,
          total_custom_icons: clientCustomIcons.length,
          has_custom_logo: Boolean(clientLogo || serverPayload.data?.app_settings?.logo_url),
        },
        // Database collections
        data: serverPayload.data || serverPayload,
        // Client GIS & Assets
        gis_points: clientGisPoints,
        gis_polygons: clientGisPolygons,
        gis_routes: clientGisRoutes,
        custom_icons: clientCustomIcons,
        client_logo_url: clientLogo,
      };

      const jsonStr = JSON.stringify(fullArchive, null, 2);
      const blob = new Blob([jsonStr], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      const dateStr = new Date().toISOString().slice(0, 10);
      link.href = url;
      link.download = `BACKUP_LENGKAP_SAR_KENDARI_${dateStr}.json`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

      showSuccessAlert(
        'Cadangan Berhasil Diunduh',
        `Berkas cadangan lengkap (.json) berhasil dibuat. Berkas ini mencakup seluruh data Operasi, Korban, Dokumen BRA, Renops, Alut, Tim, Pangkalan, Pejabat, Rute GIS, dan Ikon Kustom Peta.`
      );
    } catch (err: any) {
      showErrorAlert('Gagal Ekspor Cadangan', err.message || 'Terjadi kesalahan saat mengekspor database.');
    } finally {
      setIsExporting(false);
    }
  };

  // --- COMPREHENSIVE RESTORE (IMPORT) ---
  const handleImportFileSelected = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    e.target.value = '';

    if (!file.name.endsWith('.json')) {
      showErrorAlert('Format Tidak Valid', 'Silakan pilih berkas cadangan dengan format JSON (.json).');
      return;
    }

    try {
      setIsImporting(true);
      showToast('Membaca dan memverifikasi berkas cadangan JSON...', 'info');

      const text = await file.text();
      let parsed: any;
      try {
        parsed = JSON.parse(text);
      } catch {
        throw new Error('Berkas yang dipilih bukan berkas JSON yang valid atau rusak.');
      }

      if (!parsed || typeof parsed !== 'object') {
        throw new Error('Struktur berkas cadangan tidak valid.');
      }

      // Detect data root
      let dbData: any = parsed;
      if (parsed.data && typeof parsed.data === 'object' && !Array.isArray(parsed.data)) {
        dbData = parsed.data;
      } else if (parsed.database && typeof parsed.database === 'object') {
        dbData = parsed.database;
      } else if (parsed.backup && typeof parsed.backup === 'object') {
        dbData = parsed.backup;
      }

      // Count items
      const opCount = Array.isArray(dbData.operasi) ? dbData.operasi.length : (parsed.coverage_summary?.total_operasi || 0);
      const korbanCount = Array.isArray(dbData.korban) ? dbData.korban.length : (parsed.coverage_summary?.total_korban || 0);
      const braCount = Array.isArray(dbData.bra_sar) ? dbData.bra_sar.length : (parsed.coverage_summary?.total_bra_sar || 0);
      const renopsCount = Array.isArray(dbData.renops) ? dbData.renops.length : (parsed.coverage_summary?.total_renops || 0);
      const alutCount = Array.isArray(dbData.master_alut) ? dbData.master_alut.length : (parsed.coverage_summary?.total_master_alut || 0);
      const timCount = Array.isArray(dbData.master_tim) ? dbData.master_tim.length : (parsed.coverage_summary?.total_master_tim || 0);
      const gisRoutes = Array.isArray(parsed.gis_routes) ? parsed.gis_routes : [];
      const customIcons = Array.isArray(parsed.custom_icons) ? parsed.custom_icons : [];
      const gisPoints = Array.isArray(parsed.gis_points) ? parsed.gis_points : [];
      const gisPolygons = Array.isArray(parsed.gis_polygons) ? parsed.gis_polygons : [];

      const confirmed = await showConfirmDialog(
        'Konfirmasi Pemulihan Database',
        `Apakah Anda yakin ingin memulihkan basis data dari berkas ini?\n\n` +
        `Rincian Data yang Terverifikasi:\n` +
        `• ${opCount} Operasi SAR\n` +
        `• ${korbanCount} Data Korban\n` +
        `• ${braCount} Dokumen Resmi BRA SAR\n` +
        `• ${renopsCount} Rencana Operasi (Renops)\n` +
        `• ${alutCount} Master Alut & ${timCount} Master Tim\n` +
        `• ${gisPoints.length} Titik GIS, ${gisPolygons.length} Polygon, ${gisRoutes.length} Rute Jarak\n\n` +
        `Data yang ada di sistem saat ini akan diperbarui dengan isi berkas cadangan ini.`,
        'Ya, Pulihkan Sekarang',
        false
      );

      if (!confirmed) {
        setIsImporting(false);
        return;
      }

      // 1. Restore server-side database
      showToast('Memulihkan basis data ke server...', 'info');
      const res = await fetch('/api/database/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(dbData),
      });

      const resData = await res.json();
      if (!res.ok || !resData.success) {
        throw new Error(resData.error || 'Server menolak pemulihan database.');
      }

      // 2. Restore client-side GIS points, polygons, routes & custom icons & logo
      try {
        if (gisPoints.length > 0) {
          localStorage.setItem('basarnas_saved_map_points', JSON.stringify(gisPoints));
        }
        if (gisPolygons.length > 0) {
          localStorage.setItem('basarnas_saved_polygons', JSON.stringify(gisPolygons));
        }
        if (gisRoutes.length > 0) {
          localStorage.setItem('basarnas_saved_distance_routes', JSON.stringify(gisRoutes));
        }
        if (customIcons.length > 0) {
          localStorage.setItem('basarnas_saved_custom_icons', JSON.stringify(customIcons));
        }
        const restoredLogo = parsed.client_logo_url || dbData.app_settings?.logo_url;
        if (restoredLogo) {
          localStorage.setItem('sar_custom_logo_url', restoredLogo);
          window.dispatchEvent(new Event('sar_logo_updated'));
        }
        window.dispatchEvent(new Event('basarnas_gis_data_restored'));
      } catch (clientErr) {
        console.warn('Client storage restore note:', clientErr);
      }

      // 3. Refresh application state
      if (onRefreshData) await onRefreshData();
      await fetchAllSettings();

      showSuccessAlert(
        'Pemulihan Berhasil 100%',
        `Seluruh data operasional SAR, arsip dokumen, master alut, tim, rute GIS pemetaan, dan pengaturan satker telah berhasil dipulihkan secara penuh.`
      );
    } catch (err: any) {
      showErrorAlert('Gagal Memulihkan Database', err.message || 'Terjadi kesalahan selama proses pemulihan.');
    } finally {
      setIsImporting(false);
    }
  };

  // SQL Dump Download
  const handleExportSql = async () => {
    try {
      showToast('Menyiapkan berkas SQL Dump...', 'info');
      const res = await fetch('/api/export/sql');
      if (!res.ok) throw new Error('Gagal mengekspor berkas SQL.');
      const blob = await res.blob();
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      const dateStr = new Date().toISOString().slice(0, 10);
      link.href = url;
      link.download = `DUMP_DATABASE_SAR_KENDARI_${dateStr}.sql`;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
      showToast('Berkas SQL berhasil diunduh.', 'success');
    } catch (err: any) {
      showErrorAlert('Gagal Ekspor SQL', err.message);
    }
  };

  return (
    <div className={`min-h-screen transition-colors duration-200 pb-16 ${
      isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'
    }`}>
      {/* Hidden file input for restore */}
      <input
        type="file"
        ref={fileInputRef}
        accept=".json,application/json"
        onChange={handleImportFileSelected}
        className="hidden"
      />

      {/* Hidden file input for logo */}
      <input
        type="file"
        ref={logoFileInputRef}
        accept="image/*"
        onChange={handleLogoUpload}
        className="hidden"
      />

      {/* Header Bar */}
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
                  <span className="font-extrabold text-base sm:text-lg tracking-wide uppercase">
                    Pengaturan Satker & Keamanan Admin
                  </span>
                  <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30">
                    <ShieldCheck className="w-3 h-3" />
                    HALAMAN KHUSUS
                  </span>
                </div>
                <p className="text-[11px] text-slate-400 font-medium">
                  Konfigurasi identitas kantor SAR, profil admin, sandi akun, serta cadangan basis data terpadu
                </p>
              </div>
            </div>

            <div className="flex items-center gap-2 self-end sm:self-auto">
              <button
                type="button"
                onClick={fetchAllSettings}
                disabled={isLoading}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-all cursor-pointer ${
                  isDarkMode
                    ? 'bg-slate-800/80 hover:bg-slate-800 text-slate-300 border-slate-700'
                    : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-200 shadow-xs'
                }`}
                title="Muat ulang pengaturan"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${isLoading ? 'animate-spin text-orange-500' : ''}`} />
                <span>Segarkan</span>
              </button>
            </div>
          </div>

          {/* Dedicated Tab Navigation */}
          <div className="flex items-center gap-2 mt-4 overflow-x-auto no-scrollbar border-t border-slate-100 dark:border-slate-800/80 pt-3">
            <button
              type="button"
              onClick={() => setActiveTab('admin')}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 ${
                activeTab === 'admin'
                  ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25'
                  : isDarkMode
                  ? 'bg-slate-800/60 text-slate-300 hover:bg-slate-800 border border-slate-700/60'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <ShieldCheck className="w-4 h-4" />
              <span>Profil & Sandi Admin</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('satker')}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 ${
                activeTab === 'satker'
                  ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25'
                  : isDarkMode
                  ? 'bg-slate-800/60 text-slate-300 hover:bg-slate-800 border border-slate-700/60'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <Building2 className="w-4 h-4" />
              <span>Pengaturan Satker & Aplikasi</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('backup')}
              className={`flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer shrink-0 ${
                activeTab === 'backup'
                  ? 'bg-orange-600 text-white shadow-md shadow-orange-600/25'
                  : isDarkMode
                  ? 'bg-slate-800/60 text-slate-300 hover:bg-slate-800 border border-slate-700/60'
                  : 'bg-white text-slate-700 hover:bg-slate-100 border border-slate-200'
              }`}
            >
              <Database className="w-4 h-4" />
              <span>Backup & Restore Database</span>
              <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-500/20 text-emerald-400 font-extrabold border border-emerald-500/30">
                100% COVER
              </span>
            </button>
          </div>
        </div>
      </div>

      {/* Main Content Area */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 py-6">
        {/* ======================================================== */}
        {/* TAB 1: PROFIL & SANDI ADMINISTRATOR (NO EMAIL SERVER!)   */}
        {/* ======================================================== */}
        {activeTab === 'admin' && (
          <div className="space-y-6">
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
              {/* Left Column: Admin Identity Form (Col-span 2) */}
              <div className={`lg:col-span-2 p-6 rounded-2xl border ${
                isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
              }`}>
                <div className="flex items-center gap-3 pb-4 border-b border-inherit mb-5">
                  <div className="p-2.5 rounded-xl bg-orange-500/10 text-orange-500 border border-orange-500/20 shrink-0">
                    <User className="w-5 h-5" />
                  </div>
                  <div>
                    <h2 className="text-base font-bold">Profil Pejabat Administrator</h2>
                    <p className="text-xs text-slate-400">
                      Identitas kedinasan pimpinan dan akun administrator sistem KPP Kendari
                    </p>
                  </div>
                </div>

                <form onSubmit={handleSaveAdminProfile} className="space-y-4">
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Nama Lengkap & Gelar <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={adminNama}
                        onChange={(e) => setAdminNama(e.target.value)}
                        placeholder="Contoh: AMIRUDDIN AS, S.Sos."
                        required
                        className={`w-full px-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Nomor Induk Pegawai (NIP)
                      </label>
                      <input
                        type="text"
                        value={adminNip}
                        onChange={(e) => setAdminNip(e.target.value)}
                        placeholder="Contoh: 197405101998031002"
                        className={`w-full px-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Pangkat / Golongan
                      </label>
                      <input
                        type="text"
                        value={adminPangkat}
                        onChange={(e) => setAdminPangkat(e.target.value)}
                        placeholder="Contoh: PEMBINA (IV/a)"
                        className={`w-full px-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Jabatan Kedinasan
                      </label>
                      <input
                        type="text"
                        value={adminJabatan}
                        onChange={(e) => setAdminJabatan(e.target.value)}
                        placeholder="Contoh: Kepala Kantor / Administrator Utama KPP Kendari"
                        className={`w-full px-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Username Akun Login <span className="text-red-500">*</span>
                      </label>
                      <input
                        type="text"
                        value={adminUsername}
                        onChange={(e) => setAdminUsername(e.target.value)}
                        placeholder="admin"
                        required
                        className={`w-full px-3.5 py-2.5 rounded-xl text-xs font-mono font-bold border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-orange-400' : 'bg-slate-50 border-slate-300 text-orange-600'
                        }`}
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        No. HP / WhatsApp Dinas
                      </label>
                      <div className="relative">
                        <Phone className="w-3.5 h-3.5 absolute left-3.5 top-3 text-slate-400" />
                        <input
                          type="text"
                          value={adminNoHp}
                          onChange={(e) => setAdminNoHp(e.target.value)}
                          placeholder="0811-4091-115"
                          className={`w-full pl-9 pr-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                            isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                          }`}
                        />
                      </div>
                    </div>

                    <div className="sm:col-span-2">
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Alamat Email Resmi Administrator
                      </label>
                      <div className="relative">
                        <Mail className="w-3.5 h-3.5 absolute left-3.5 top-3 text-slate-400" />
                        <input
                          type="email"
                          value={adminEmail}
                          onChange={(e) => setAdminEmail(e.target.value)}
                          placeholder="irfandydk@gmail.com"
                          className={`w-full pl-9 pr-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                            isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                          }`}
                        />
                      </div>
                    </div>
                  </div>

                  <div className="pt-4 flex items-center justify-end">
                    <button
                      type="submit"
                      disabled={isSavingAdmin}
                      className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 shadow-md shadow-orange-600/25 transition-all disabled:opacity-50 cursor-pointer"
                    >
                      <Save className="w-4 h-4" />
                      <span>{isSavingAdmin ? 'Menyimpan Profil...' : 'Simpan Profil Administrator'}</span>
                    </button>
                  </div>
                </form>
              </div>

              {/* Right Column: Change Password Card (Col-span 1) */}
              <div className={`p-6 rounded-2xl border flex flex-col justify-between ${
                isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
              }`}>
                <div>
                  <div className="flex items-center gap-3 pb-4 border-b border-inherit mb-5">
                    <div className="p-2.5 rounded-xl bg-amber-500/10 text-amber-500 border border-amber-500/20 shrink-0">
                      <Lock className="w-5 h-5" />
                    </div>
                    <div>
                      <h2 className="text-base font-bold">Ubah Kata Sandi</h2>
                      <p className="text-xs text-slate-400">Perbarui kata sandi akun administrator</p>
                    </div>
                  </div>

                  <form onSubmit={handleUpdatePassword} className="space-y-3.5">
                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Kata Sandi Lama <span className="text-red-500">*</span>
                      </label>
                      <div className="relative">
                        <input
                          type={showOldPass ? 'text' : 'password'}
                          value={oldPassword}
                          onChange={(e) => setOldPassword(e.target.value)}
                          placeholder="Masukkan kata sandi lama"
                          required
                          className={`w-full px-3.5 py-2.5 pr-10 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                            isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                          }`}
                        />
                        <button
                          type="button"
                          onClick={() => setShowOldPass(!showOldPass)}
                          className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-200 cursor-pointer"
                        >
                          {showOldPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Kata Sandi Baru <span className="text-red-500">*</span>
                      </label>
                      <div className="relative">
                        <input
                          type={showNewPass ? 'text' : 'password'}
                          value={newPassword}
                          onChange={(e) => setNewPassword(e.target.value)}
                          placeholder="Minimal 4 karakter"
                          required
                          minLength={4}
                          className={`w-full px-3.5 py-2.5 pr-10 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                            isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                          }`}
                        />
                        <button
                          type="button"
                          onClick={() => setShowNewPass(!showNewPass)}
                          className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-200 cursor-pointer"
                        >
                          {showNewPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                        Konfirmasi Sandi Baru <span className="text-red-500">*</span>
                      </label>
                      <div className="relative">
                        <input
                          type={showConfirmPass ? 'text' : 'password'}
                          value={confirmPassword}
                          onChange={(e) => setConfirmPassword(e.target.value)}
                          placeholder="Ulangi kata sandi baru"
                          required
                          className={`w-full px-3.5 py-2.5 pr-10 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                            isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                          }`}
                        />
                        <button
                          type="button"
                          onClick={() => setShowConfirmPass(!showConfirmPass)}
                          className="absolute right-3 top-2.5 text-slate-400 hover:text-slate-200 cursor-pointer"
                        >
                          {showConfirmPass ? <EyeOff className="w-4 h-4" /> : <Eye className="w-4 h-4" />}
                        </button>
                      </div>
                    </div>

                    <div className="pt-2">
                      <button
                        type="submit"
                        disabled={isUpdatingPassword}
                        className="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 shadow transition-all disabled:opacity-50 cursor-pointer"
                      >
                        <Lock className="w-4 h-4 text-orange-500" />
                        <span>{isUpdatingPassword ? 'Memperbarui...' : 'Perbarui Kata Sandi'}</span>
                      </button>
                    </div>
                  </form>
                </div>

                <div className="mt-5 p-3.5 rounded-xl bg-orange-500/5 border border-orange-500/15 text-slate-400 text-[11px] space-y-1">
                  <div className="flex items-center gap-1.5 text-orange-500 font-bold">
                    <ShieldCheck className="w-3.5 h-3.5" />
                    <span>Petunjuk Keamanan:</span>
                  </div>
                  <p className="leading-relaxed">
                    Pastikan kata sandi baru dirahasiakan dan disimpan dengan baik. Kata sandi ini digunakan untuk otentikasi pimpinan dan staf admin KPP Kendari.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* ======================================================== */}
        {/* TAB 2: PENGATURAN SATKER & IDENTITAS APLIKASI            */}
        {/* ======================================================== */}
        {activeTab === 'satker' && (
          <div className="space-y-6">
            <div className={`p-6 rounded-2xl border ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center gap-3 pb-4 border-b border-inherit mb-5">
                <div className="p-2.5 rounded-xl bg-blue-500/10 text-blue-500 border border-blue-500/20 shrink-0">
                  <Building2 className="w-5 h-5" />
                </div>
                <div>
                  <h2 className="text-base font-bold">Identitas Satuan Kerja & Parameter Aplikasi</h2>
                  <p className="text-xs text-slate-400">
                    Konfigurasi nama satker, kode resmi, nomor telepon darurat, radio komunikasi, dan KOP dokumen
                  </p>
                </div>
              </div>

              <form onSubmit={handleSaveSatker} className="space-y-5">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                      Kode Satker Resmi <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={satkerKode}
                      onChange={(e) => setSatkerKode(e.target.value)}
                      placeholder="SAR-304"
                      required
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs font-mono font-bold border transition-colors outline-none focus:border-orange-500 ${
                        isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                      }`}
                    />
                    <p className="text-[11px] text-slate-400 mt-1">Digunakan pada penomoran resmi naskah BRA SAR (e.g. 191/SAR-304/0926)</p>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                      Nama Kantor / Satker SAR <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      value={satkerNama}
                      onChange={(e) => setSatkerNama(e.target.value)}
                      placeholder="KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI"
                      required
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs font-bold border transition-colors outline-none focus:border-orange-500 ${
                        isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                      }`}
                    />
                  </div>

                  <div className="md:col-span-2">
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                      Alamat Lengkap Kantor SAR
                    </label>
                    <div className="relative">
                      <MapPin className="w-3.5 h-3.5 absolute left-3.5 top-3 text-slate-400" />
                      <input
                        type="text"
                        value={satkerAlamat}
                        onChange={(e) => setSatkerAlamat(e.target.value)}
                        placeholder="Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan"
                        className={`w-full pl-9 pr-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                      Nomor Telepon Emergency / Siaga SAR
                    </label>
                    <div className="relative">
                      <Phone className="w-3.5 h-3.5 absolute left-3.5 top-3 text-slate-400" />
                      <input
                        type="text"
                        value={satkerNoTelp}
                        onChange={(e) => setSatkerNoTelp(e.target.value)}
                        placeholder="(0401) 3122115 / Emergency 115"
                        className={`w-full pl-9 pr-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>
                  </div>

                  <div>
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                      Call Sign Stasiun Radio SAR
                    </label>
                    <div className="relative">
                      <Radio className="w-3.5 h-3.5 absolute left-3.5 top-3 text-slate-400" />
                      <input
                        type="text"
                        value={satkerCallSign}
                        onChange={(e) => setSatkerCallSign(e.target.value)}
                        placeholder="KANSAR KENDARI"
                        className={`w-full pl-9 pr-3.5 py-2.5 rounded-xl text-xs font-bold border transition-colors outline-none focus:border-orange-500 ${
                          isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>
                  </div>

                  <div className="md:col-span-2">
                    <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                      Frekuensi Radio Standar Operasi
                    </label>
                    <input
                      type="text"
                      value={satkerFrekuensi}
                      onChange={(e) => setSatkerFrekuensi(e.target.value)}
                      placeholder="115.000 MHz / 5827 kHz"
                      className={`w-full px-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                        isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-slate-50 border-slate-300 text-slate-900'
                      }`}
                    />
                  </div>
                </div>

                {/* Logo Setting Section */}
                <div className="pt-4 border-t border-slate-100 dark:border-slate-800">
                  <h3 className="text-xs font-bold uppercase tracking-wider text-orange-500 mb-3">
                    Logo Resmi Kantor SAR (KOP Surat Dokumen & Cetak)
                  </h3>

                  <div className="flex flex-col sm:flex-row items-center gap-6 p-4 rounded-2xl bg-slate-500/5 border border-inherit">
                    <div className="flex flex-col items-center gap-2 shrink-0">
                      <div className="w-24 h-24 rounded-2xl border-2 border-orange-500/30 bg-white p-2.5 flex items-center justify-center shadow-sm">
                        <img
                          src={satkerLogoUrl}
                          alt="Logo Basarnas"
                          className="max-w-full max-h-full object-contain"
                          onError={(e) => {
                            (e.target as HTMLImageElement).src = DEFAULT_BASARNAS_LOGO;
                          }}
                        />
                      </div>
                      <span className="text-[10px] text-slate-400 font-medium">Pratinjau Logo</span>
                    </div>

                    <div className="flex-1 space-y-3 w-full">
                      <div>
                        <label className="block text-xs font-semibold mb-1 text-slate-600 dark:text-slate-300">
                          URL Gambar Logo
                        </label>
                        <input
                          type="url"
                          value={satkerLogoUrl}
                          onChange={(e) => setSatkerLogoUrl(e.target.value)}
                          placeholder="https://..."
                          className={`w-full px-3.5 py-2.5 rounded-xl text-xs border transition-colors outline-none focus:border-orange-500 ${
                            isDarkMode ? 'bg-slate-800/80 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                          }`}
                        />
                      </div>

                      <div className="flex flex-wrap items-center gap-2">
                        <button
                          type="button"
                          onClick={() => logoFileInputRef.current?.click()}
                          className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all cursor-pointer ${
                            isDarkMode
                              ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                              : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-200 shadow-xs'
                          }`}
                        >
                          <Upload className="w-3.5 h-3.5 text-orange-500" />
                          <span>Pilih Gambar dari Komputer</span>
                        </button>

                        <button
                          type="button"
                          onClick={() => setSatkerLogoUrl(DEFAULT_BASARNAS_LOGO)}
                          className={`inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition-all cursor-pointer ${
                            isDarkMode
                              ? 'text-slate-400 hover:text-white'
                              : 'text-slate-600 hover:text-slate-900'
                          }`}
                        >
                          <RefreshCw className="w-3.5 h-3.5" />
                          <span>Gunakan Logo Default Basarnas</span>
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="pt-4 flex items-center justify-end">
                  <button
                    type="submit"
                    disabled={isSavingSatker}
                    className="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 shadow-md shadow-orange-600/25 transition-all disabled:opacity-50 cursor-pointer"
                  >
                    <Save className="w-4 h-4" />
                    <span>{isSavingSatker ? 'Menyimpan...' : 'Simpan Pengaturan Satker'}</span>
                  </button>
                </div>
              </form>
            </div>
          </div>
        )}

        {/* ======================================================== */}
        {/* TAB 3: BACKUP & RESTORE DATABASE (COMPREHENSIVE COVER)   */}
        {/* ======================================================== */}
        {activeTab === 'backup' && (
          <div className="space-y-6">
            {/* Top Hero Banner */}
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
                    <div className="flex flex-wrap items-center gap-2">
                      <span className="text-[11px] font-mono font-bold px-2 py-0.5 rounded bg-orange-500/10 text-orange-400 border border-orange-500/20">
                        FULL APPLICATION BACKUP & RESTORE
                      </span>
                      <span className="text-xs text-slate-400">•</span>
                      <span className="text-xs text-emerald-500 font-semibold flex items-center gap-1">
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        100% Seluruh Data Aplikasi Tercover
                      </span>
                    </div>
                    <h2 className="text-lg sm:text-xl font-black mt-1">
                      Pusat Cadangan & Pemulihan Basis Data
                    </h2>
                    <p className="text-xs text-slate-400 mt-1 max-w-2xl leading-relaxed">
                      Sistem pencadangan menyeluruh yang mengamankan seluruh riwayat Operasi SAR, Korban, Rencana Operasi (Renops), Arsip BRA SAR Telegram, Master Alut & Tim, Pejabat SMC, Pengaturan Satker, hingga Rute Pengukuran GIS dan Ikon Peta Kustom dalam satu berkas terpadu.
                    </p>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 shrink-0">
                  <button
                    type="button"
                    onClick={handleExportFullBackup}
                    disabled={isExporting}
                    className="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold text-white bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 shadow-lg shadow-orange-600/25 transition-all disabled:opacity-50 cursor-pointer"
                  >
                    <Download className={`w-4 h-4 ${isExporting ? 'animate-bounce' : ''}`} />
                    <span>{isExporting ? 'Mengekspor Data...' : 'Ekspor Seluruh Database (.json)'}</span>
                  </button>

                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={isImporting}
                    className={`inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold border transition-all disabled:opacity-50 cursor-pointer ${
                      isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                        : 'bg-white hover:bg-slate-50 text-slate-700 border-slate-300 shadow-xs'
                    }`}
                  >
                    <Upload className={`w-4 h-4 text-orange-500 ${isImporting ? 'animate-pulse' : ''}`} />
                    <span>{isImporting ? 'Memulihkan...' : 'Impor & Pulihkan Database (.json)'}</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Live Data Metrics Grid */}
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
              <div className={`p-4 rounded-2xl border ${
                isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
              }`}>
                <span className="text-[11px] text-slate-400 block font-medium">Operasi SAR</span>
                <span className="text-xl font-black text-orange-500 mt-1 block">{dbStats.operasi}</span>
                <span className="text-[10px] text-slate-500">Berkas Operasi</span>
              </div>

              <div className={`p-4 rounded-2xl border ${
                isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
              }`}>
                <span className="text-[11px] text-slate-400 block font-medium">Data Korban</span>
                <span className="text-xl font-black text-red-500 mt-1 block">{dbStats.korban}</span>
                <span className="text-[10px] text-slate-500">Jiwa Tercatat</span>
              </div>

              <div className={`p-4 rounded-2xl border ${
                isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
              }`}>
                <span className="text-[11px] text-slate-400 block font-medium">Arsip BRA SAR</span>
                <span className="text-xl font-black text-blue-500 mt-1 block">{dbStats.bra_sar}</span>
                <span className="text-[10px] text-slate-500">Naskah Telegram</span>
              </div>

              <div className={`p-4 rounded-2xl border ${
                isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
              }`}>
                <span className="text-[11px] text-slate-400 block font-medium">Renops & SRU</span>
                <span className="text-xl font-black text-emerald-500 mt-1 block">{dbStats.renops}</span>
                <span className="text-[10px] text-slate-500">Rencana Operasi</span>
              </div>

              <div className={`p-4 rounded-2xl border ${
                isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
              }`}>
                <span className="text-[11px] text-slate-400 block font-medium">Master Alut & Tim</span>
                <span className="text-xl font-black text-purple-500 mt-1 block">
                  {dbStats.master_alut + dbStats.master_tim}
                </span>
                <span className="text-[10px] text-slate-500">{dbStats.master_alut} Alut • {dbStats.master_tim} Tim</span>
              </div>

              <div className={`p-4 rounded-2xl border ${
                isDarkMode ? 'bg-slate-900/80 border-slate-800' : 'bg-white border-slate-200 shadow-xs'
              }`}>
                <span className="text-[11px] text-slate-400 block font-medium">GIS & Ikon Peta</span>
                <span className="text-xl font-black text-cyan-500 mt-1 block">
                  {dbStats.gis_routes + dbStats.custom_icons}
                </span>
                <span className="text-[10px] text-slate-500">{dbStats.gis_routes} Rute • {dbStats.custom_icons} Ikon</span>
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
                      <h3 className="font-bold text-sm">1. Ekspor Cadangan Lengkap (.json)</h3>
                      <p className="text-xs text-slate-400">Unduh snapshot seluruh data operasional dan aset ke perangkat</p>
                    </div>
                  </div>

                  <p className="text-xs text-slate-300 dark:text-slate-400 leading-relaxed">
                    Menghasilkan satu berkas <code className="px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-400 font-mono text-[11px]">.json</code> komprehensif berisi:
                  </p>

                  <div className="space-y-2 pt-1 text-xs">
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Seluruh riwayat Operasi SAR, data korban, Renops harian & Lapbang.</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Arsip naskah BRA SAR dengan kode verifikasi QR digital.</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Master Alut, Tim SRU, Pejabat SMC, dan Satker Daerah.</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Seluruh rute pengukuran jarak/ETA GIS tersimpan & galeri ikon pin kustom.</span>
                    </div>
                    <div className="flex items-center gap-2 text-slate-400">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Identitas satker, KOP logo resmi, dan profil akun administrator.</span>
                    </div>
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-slate-800 space-y-2">
                  <button
                    type="button"
                    onClick={handleExportFullBackup}
                    disabled={isExporting}
                    className="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 shadow-md shadow-orange-600/20 transition-all disabled:opacity-50 cursor-pointer"
                  >
                    <Download className="w-4 h-4" />
                    <span>{isExporting ? 'Sedang Mengunduh...' : 'Unduh Cadangan Database (.json)'}</span>
                  </button>

                  <button
                    type="button"
                    onClick={handleExportSql}
                    className={`w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-semibold border transition-all cursor-pointer ${
                      isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                        : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                    }`}
                  >
                    <FileCode className="w-3.5 h-3.5 text-blue-500" />
                    <span>Ekspor Format SQL Relasional (.sql)</span>
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
                      <h3 className="font-bold text-sm">2. Impor & Pulihkan Database (.json)</h3>
                      <p className="text-xs text-slate-400">Pulihkan seluruh data aplikasi dari berkas cadangan JSON</p>
                    </div>
                  </div>

                  <p className="text-xs text-slate-300 dark:text-slate-400 leading-relaxed">
                    Unggah berkas cadangan database JSON untuk memulihkan seluruh data operasional, arsip, master data, dan pemetaan ke dalam sistem secara instan.
                  </p>

                  <div className="p-3.5 rounded-xl border border-amber-500/20 bg-amber-500/5 text-amber-600 dark:text-amber-400 text-xs space-y-1.5">
                    <div className="flex items-center gap-1.5 font-bold">
                      <AlertCircle className="w-3.5 h-3.5 shrink-0" />
                      <span>Jaminan Integritas & Keamanan:</span>
                    </div>
                    <p className="text-[11px] leading-relaxed text-slate-400">
                      Sebelum proses pemulihan dijalankan, sistem akan memvalidasi format berkas dan menampilkan jendela konfirmasi ringkasan seluruh item data yang akan dipulihkan.
                    </p>
                  </div>

                  <div className="space-y-1 text-[11px] text-slate-400 pt-1">
                    <div className="flex items-center gap-2">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Kompatibel dengan semua versi berkas cadangan aplikasi KPP Kendari.</span>
                    </div>
                    <div className="flex items-center gap-2">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                      <span>Memulihkan server database dan state pemetaan GIS secara otomatis.</span>
                    </div>
                  </div>
                </div>

                <div className="pt-4 border-t border-slate-100 dark:border-slate-800">
                  <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    disabled={isImporting}
                    className={`w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl text-xs font-bold border transition-all disabled:opacity-50 cursor-pointer ${
                      isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 text-white border-slate-700 shadow-md'
                        : 'bg-slate-100 hover:bg-slate-200 text-slate-800 border-slate-300 shadow-xs'
                    }`}
                  >
                    <Upload className="w-4 h-4 text-orange-500" />
                    <span>{isImporting ? 'Sedang Memproses Berkas...' : 'Pilih Berkas Cadangan JSON & Pulihkan'}</span>
                  </button>
                </div>
              </div>
            </div>

            {/* Comprehensive Scope Checklist */}
            <div className={`p-6 rounded-2xl border ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center gap-2 mb-4">
                <FileCheck className="w-5 h-5 text-emerald-500" />
                <h3 className="font-bold text-sm">Cakupan Seluruh Data yang Tercover & Dilindungi (100%)</h3>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 text-xs">
                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-orange-400">1. Operasi SAR & Status</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Semua riwayat operasi, koordinat kejadian, status (Aktif/Tutup), kronologi, dan log waktu WITA.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-red-400">2. Data Korban & Medis</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Identitas korban, kondisi (Selamat, Meninggal Dunia, Hilang/DP), usia, jenis kelamin, dan alamat.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-blue-400">3. Arsip Naskah BRA SAR</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Seluruh dokumen resmi telegram radio (MAPEL, SAR UMUM, PERGERAKAN KAPAL) beserta barcode validasi QR.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-emerald-400">4. Rencana Operasi (Renops)</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Rencana harian (H.1, H.2, dst.), pembagian Search and Rescue Unit (SRU), cuaca, dan pola pencarian.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-purple-400">5. Laporan Harian (Lapbang)</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Laporan perkembangan harian, debriefing, teks WhatsApp broadcast dinas, dan penutupan operasi.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-amber-400">6. Unsur & Alut Terlibat</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Daftar potensi unsur gabungan (TNI, POLRI, BPBD, Nelayan) dan alut palsar per operasi SAR.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-cyan-400">7. Rute Jarak & ETA GIS</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Seluruh rute pengukuran jarak, kecepatan, estimasi tiba (ETA), warna, dan ketebalan garis tersimpan.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-pink-400">8. Galeri Ikon Pin Peta</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Seluruh ikon custom marker yang pernah diunggah agar otomatis tersimpan dan siap digunakan kembali.
                  </p>
                </div>

                <div className={`p-3.5 rounded-xl border ${
                  isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}>
                  <div className="font-bold text-indigo-400">9. Master Alut, Tim & Pejabat</div>
                  <p className="text-slate-400 text-[11px] mt-1">
                    Master armada Basarnas, master tim regu, pos SAR daerah, serta daftar pejabat SMC berwenang.
                  </p>
                </div>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
