import React, { useState, useEffect, useMemo } from 'react';
import {
  ArrowLeft,
  Save,
  Sparkles,
  Plus,
  Trash2,
  Copy,
  Check,
  Navigation,
  Ship,
  Users,
  AlertTriangle,
  Wind,
  Compass,
  FileText,
  Clock,
  MapPin,
  Phone,
  Shield,
  UserCheck,
  RotateCcw,
  CloudSun,
  Waves,
  Car,
} from 'lucide-react';
import { MasterAlut, MasterTim, Operasi, Korban, DetailUnsur, DetailAlut } from '../types';
import { generateLaporanAwalWA } from '../utils/templates';
import { showSuccessAlert, showErrorAlert, showToast } from '../utils/alerts';
import { DateTimeInput24H } from './DateTimeInput24H';
import { UnsurSarEditor } from './UnsurSarEditor';
import { AlutPalsarEditor } from './AlutPalsarEditor';
import { useTheme } from '../context/ThemeContext';

interface FormInisiasiProps {
  initialOperasi?: Operasi | null;
  masterAlut: MasterAlut[];
  masterTim: MasterTim[];
  onBack: () => void;
  onSaveSuccess: (op: Operasi) => void;
}

export const FormInisiasi: React.FC<FormInisiasiProps> = ({
  initialOperasi,
  masterAlut,
  masterTim,
  onBack,
  onSaveSuccess,
}) => {
  const { isDarkMode, accentColors, themePalette } = useTheme();
  const isEditing = !!initialOperasi;

  // Form states (Clean empty defaults with informative placeholders)
  const [namaOperasi, setNamaOperasi] = useState(initialOperasi?.nama_operasi || '');
  const [namaPelapor, setNamaPelapor] = useState(initialOperasi?.nama_pelapor || '');
  const [hubunganPelapor, setHubunganPelapor] = useState(initialOperasi?.hubungan_pelapor || '');
  const [noTelpPelapor, setNoTelpPelapor] = useState(initialOperasi?.no_telp_pelapor || '');

  // Helper to format ISO for datetime
  const formatDatetimeLocal = (isoString?: string) => {
    if (!isoString) {
      const now = new Date();
      now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
      return now.toISOString().slice(0, 16);
    }
    try {
      const d = new Date(isoString);
      d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
      return d.toISOString().slice(0, 16);
    } catch {
      return '';
    }
  };

  const [waktuKejadian, setWaktuKejadian] = useState(
    formatDatetimeLocal(initialOperasi?.waktu_kejadian)
  );
  const [waktuLapor, setWaktuLapor] = useState(
    formatDatetimeLocal(initialOperasi?.waktu_lapor)
  );
  const [waktuBerangkat, setWaktuBerangkat] = useState(
    formatDatetimeLocal(initialOperasi?.waktu_berangkat)
  );
  const [etaTiba, setEtaTiba] = useState(
    formatDatetimeLocal(initialOperasi?.eta_tiba)
  );

  // Selected departure teams
  const [selectedTimIds, setSelectedTimIds] = useState<number[]>(
    initialOperasi?.detail_tim_berangkat?.map((t) => t.id_tim) || []
  );

  // Location & Coordinates
  const [lokasiKejadian, setLokasiKejadian] = useState(
    initialOperasi?.lokasi_kejadian || ''
  );
  const [koordinat, setKoordinat] = useState(
    initialOperasi?.koordinat || ''
  );
  const [jarakAngka, setJarakAngka] = useState(
    initialOperasi?.jarak?.split(' ')[0] || ''
  );
  const [jarakSatuan, setJarakSatuan] = useState(
    initialOperasi?.jarak?.split(' ')[1] || 'Nm'
  );
  const [etaLokasi, setEtaLokasi] = useState(
    initialOperasi?.eta_lokasi || ''
  );

  // DMS helper mode
  const [showDmsHelper, setShowDmsHelper] = useState(false);
  const [latDeg, setLatDeg] = useState('04');
  const [latMin, setLatMin] = useState('05');
  const [latSec, setLatSec] = useState('03');
  const [latDir, setLatDir] = useState('S');

  const [lonDeg, setLonDeg] = useState('122');
  const [lonMin, setLonMin] = useState('25');
  const [lonSec, setLonSec] = useState('42');
  const [lonDir, setLonDir] = useState('E');

  // Kronologi & AI
  const [kronologi, setKronologi] = useState(initialOperasi?.kronologi || '');
  const [isAiDrafting, setIsAiDrafting] = useState(false);
  const [aiDraftPrompt, setAiDraftPrompt] = useState('');
  const [showAiModal, setShowAiModal] = useState(false);

  // Korban - default empty 1 entry
  const [korbanList, setKorbanList] = useState<Omit<Korban, 'id_korban' | 'id_operasi'>[]>(
    initialOperasi?.korban && initialOperasi.korban.length > 0
      ? initialOperasi.korban.map((k) => ({
          nama_korban: k.nama_korban,
          umur: k.umur,
          jk: k.jk,
          alamat: k.alamat,
          status_korban: k.status_korban,
        }))
      : [
          {
            nama_korban: '',
            umur: '',
            jk: 'Laki-laki',
            alamat: '',
            status_korban: 'DP',
          },
        ]
  );

  // Unsur - default empty
  const [unsurList, setUnsurList] = useState<Omit<DetailUnsur, 'id_detail_unsur' | 'id_operasi'>[]>(
    initialOperasi?.detail_unsur && initialOperasi.detail_unsur.length > 0
      ? initialOperasi.detail_unsur.map((u) => ({
          nama_unsur: u.nama_unsur,
          jumlah_personil: u.jumlah_personil,
        }))
      : []
  );

  // Alut - default empty
  const [alutList, setAlutList] = useState<Omit<DetailAlut, 'id_detail_alut' | 'id_operasi'>[]>(
    initialOperasi?.detail_alut && initialOperasi.detail_alut.length > 0
      ? initialOperasi.detail_alut.map((a) => ({
          id_alut: a.id_alut,
          nama_alut: a.nama_alut,
          satuan: a.satuan,
          jumlah_alut: a.jumlah_alut,
        }))
      : []
  );

  // Weather & Constraints - default empty
  const [cuaca, setCuaca] = useState(initialOperasi?.cuaca || '');
  const [gelombang, setGelombang] = useState(initialOperasi?.gelombang || '');
  const [angin, setAngin] = useState(initialOperasi?.angin || '');
  const [faktorPenghambat, setFaktorPenghambat] = useState(
    initialOperasi?.faktor_penghambat || ''
  );

  // Status & UI
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [copiedPreview, setCopiedPreview] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');

  // Apply DMS builder
  const handleApplyDms = () => {
    const formatted = `${latDeg.padStart(2, '0')}° ${latMin.padStart(2, '0')}' ${latSec.padStart(2, '0')}" ${latDir} - ${lonDeg.padStart(3, '0')}° ${lonMin.padStart(2, '0')}' ${lonSec.padStart(2, '0')}" ${lonDir}`;
    setKoordinat(formatted);
    setShowDmsHelper(false);
    showToast('Format koordinat DMS berhasil diterapkan!', 'success');
  };

  // Toggle selected departure team
  const toggleTeam = (id: number) => {
    setSelectedTimIds((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    );
  };

  // Selected teams string for preview & save
  const timBerangkatString = useMemo(() => {
    return selectedTimIds
      .map((id) => masterTim.find((t) => t.id_tim === id)?.nama_tim)
      .filter(Boolean)
      .join(', ') || 'Tim Rescue Kansar Kendari';
  }, [selectedTimIds, masterTim]);

  // Combined Unsur string
  const unsurTerlibatString = useMemo(() => {
    return unsurList.map((u) => `${u.nama_unsur} (${u.jumlah_personil} Org)`).join(', ');
  }, [unsurList]);

  // Combined Alut string
  const alutPalsarString = useMemo(() => {
    return alutList.map((a) => `${a.jumlah_alut} ${a.satuan} ${a.nama_alut}`).join(', ');
  }, [alutList]);

  // Live WhatsApp text preview
  const liveWaText = useMemo(() => {
    const partialOp: Partial<Operasi> = {
      nama_operasi: namaOperasi,
      nama_pelapor: namaPelapor,
      hubungan_pelapor: hubunganPelapor,
      no_telp_pelapor: noTelpPelapor,
      waktu_kejadian: new Date(waktuKejadian).toISOString(),
      waktu_lapor: new Date(waktuLapor).toISOString(),
      waktu_berangkat: new Date(waktuBerangkat).toISOString(),
      tim_berangkat: timBerangkatString,
      eta_tiba: new Date(etaTiba).toISOString(),
      lokasi_kejadian: lokasiKejadian,
      koordinat,
      jarak: `${jarakAngka} ${jarakSatuan}`,
      eta_lokasi: etaLokasi,
      kronologi,
      faktor_penghambat: faktorPenghambat,
      cuaca,
      gelombang,
      angin,
    };

    const dummyKorban = korbanList.map((k, i) => ({
      ...k,
      id_korban: i + 1,
      id_operasi: 0,
    }));

    const dummyUnsur = unsurList.map((u, i) => ({
      ...u,
      id_detail_unsur: i + 1,
      id_operasi: 0,
    }));

    const dummyAlut = alutList.map((a, i) => ({
      ...a,
      id_detail_alut: i + 1,
      id_operasi: 0,
    }));

    return generateLaporanAwalWA(partialOp, dummyKorban, dummyUnsur, dummyAlut);
  }, [
    namaOperasi,
    namaPelapor,
    hubunganPelapor,
    noTelpPelapor,
    waktuKejadian,
    waktuLapor,
    waktuBerangkat,
    timBerangkatString,
    etaTiba,
    lokasiKejadian,
    koordinat,
    jarakAngka,
    jarakSatuan,
    etaLokasi,
    kronologi,
    faktorPenghambat,
    cuaca,
    gelombang,
    angin,
    korbanList,
    unsurList,
    alutList,
  ]);

  // AI draft kronologi
  const handleGenerateAiKronologi = async () => {
    setIsAiDrafting(true);
    try {
      const res = await fetch('/api/ai/draft-kronologi', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          draftInput: aiDraftPrompt || kronologi,
          namaOperasi,
          lokasi: lokasiKejadian,
          waktuKejadian,
        }),
      });
      const data = await res.json();
      if (data.success && data.text) {
        setKronologi(data.text);
        setShowAiModal(false);
        showToast('Kronologi berhasil dirapikan dengan standar Basarnas!', 'success');
      } else {
        throw new Error(data.error || 'Gagal memproses kronologi AI');
      }
    } catch (err: any) {
      console.error(err);
      showErrorAlert('Gagal AI Kronologi', err.message || 'Gagal menghubungi server AI');
    } finally {
      setIsAiDrafting(false);
    }
  };

  // Quick Preset Insert for Kronologi
  const handleInsertKronologiPreset = (presetText: string) => {
    setKronologi(presetText);
    showToast('Template kronologi diterapkan!', 'info');
  };

  // Add / Remove Victim
  const handleAddKorban = () => {
    setKorbanList((prev) => [
      ...prev,
      {
        nama_korban: '',
        umur: '30 Tahun',
        jk: 'Laki-laki',
        alamat: '',
        status_korban: 'DP',
      },
    ]);
  };

  const handleRemoveKorban = (index: number) => {
    setKorbanList((prev) => prev.filter((_, i) => i !== index));
  };

  // Copy preview
  const handleCopyPreview = async () => {
    try {
      await navigator.clipboard.writeText(liveWaText);
      setCopiedPreview(true);
      showToast('Naskah format WA Laporan Awal berhasil disalin!', 'success');
      setTimeout(() => setCopiedPreview(false), 2000);
    } catch (err) {
      console.error(err);
    }
  };

  // Submit
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErrorMsg('');
    if (!namaOperasi.trim()) {
      setErrorMsg('Nama Operasi SAR wajib diisi!');
      showErrorAlert('Validasi Gagal', 'Nama Operasi SAR wajib diisi.');
      return;
    }

    setIsSubmitting(true);
    try {
      const payloadOperasi = {
        nama_operasi: namaOperasi.trim(),
        nama_pelapor: namaPelapor.trim(),
        hubungan_pelapor: hubunganPelapor.trim(),
        no_telp_pelapor: noTelpPelapor.trim(),
        waktu_kejadian: new Date(waktuKejadian).toISOString(),
        waktu_lapor: new Date(waktuLapor).toISOString(),
        waktu_berangkat: new Date(waktuBerangkat).toISOString(),
        tim_berangkat: timBerangkatString,
        eta_tiba: new Date(etaTiba).toISOString(),
        lokasi_kejadian: lokasiKejadian.trim(),
        koordinat: koordinat.trim(),
        jarak: `${jarakAngka} ${jarakSatuan}`,
        eta_lokasi: etaLokasi.trim(),
        kronologi: kronologi.trim(),
        jumlah_pob: korbanList.length,
        unsur_terlibat: unsurTerlibatString,
        alut_palsar: alutPalsarString,
        faktor_penghambat: faktorPenghambat.trim(),
        cuaca: cuaca.trim(),
        gelombang: gelombang.trim(),
        angin: angin.trim(),
        full_text_wa: liveWaText,
      };

      const url = isEditing ? `/api/operasi/${initialOperasi.id_operasi}` : '/api/operasi';
      const method = isEditing ? 'PUT' : 'POST';

      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          operasi: payloadOperasi,
          korban: korbanList,
          detail_unsur: unsurList,
          detail_alut: alutList,
          tim_berangkat_ids: selectedTimIds,
        }),
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.error || 'Gagal menyimpan operasi');
      }

      await showSuccessAlert(
        isEditing ? 'Operasi SAR Berhasil Diperbarui!' : 'Operasi SAR Berhasil Dibuat!',
        `Data "${payloadOperasi.nama_operasi}" telah berhasil disimpan ke dalam sistem.`
      );

      onSaveSuccess(result.data);
    } catch (err: any) {
      const msg = err.message || 'Terjadi kesalahan sistem saat menyimpan';
      setErrorMsg(msg);
      showErrorAlert('Gagal Menyimpan Operasi', msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  // Reusable styling helpers based on ThemeContext
  const cardBgClass = isDarkMode
    ? 'bg-slate-900/90 border-slate-800 text-slate-100 shadow-xl'
    : 'bg-white border-slate-200 text-slate-900 shadow-md';

  const innerBoxClass = isDarkMode
    ? 'bg-slate-950/70 border-slate-800 text-slate-200'
    : 'bg-slate-50 border-slate-200 text-slate-800';

  const inputClass = `w-full px-3 py-2 text-xs rounded-xl border transition-colors outline-none focus:ring-2 ${accentColors.ring} ${
    isDarkMode
      ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500 focus:border-orange-500'
      : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400 focus:border-orange-500'
  }`;

  const labelClass = `block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`;

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      {/* Top Bar Navigation */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <button
          onClick={onBack}
          className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all hover:scale-[1.02] active:scale-[0.98] ${
            isDarkMode
              ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
              : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-200 shadow-sm'
          }`}
        >
          <ArrowLeft className="w-4 h-4 text-orange-500" />
          <span>Kembali ke Dashboard</span>
        </button>

        <div className="flex items-center gap-2">
          <span
            className={`px-3 py-1 rounded-full text-xs font-bold border ${
              isDarkMode
                ? 'bg-orange-500/10 text-orange-400 border-orange-500/20'
                : 'bg-orange-50 text-orange-600 border-orange-200'
            }`}
          >
            {isEditing ? 'Mode Edit Data Operasi' : 'Penyusunan Laporan Awal (FILE AKTIF)'}
          </span>
        </div>
      </div>

      {errorMsg && (
        <div className="p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-500 text-xs font-semibold flex items-center justify-between shadow-lg">
          <div className="flex items-center gap-2">
            <AlertTriangle className="w-4 h-4 text-red-500" />
            <span>{errorMsg}</span>
          </div>
          <button
            type="button"
            onClick={() => setErrorMsg('')}
            className="text-red-500 hover:text-red-700 p-1 rounded transition-colors"
          >
            ✕
          </button>
        </div>
      )}

      {/* Main Grid: Form Left, WhatsApp Live Preview Right */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {/* Form Container */}
        <form onSubmit={handleSubmit} className="lg:col-span-7 space-y-6">
          {/* ============================================================
              POIN 1: Identitas Operasi & Sumber Informasi
             ============================================================ */}
          <div className={`border rounded-2xl p-5 space-y-4 ${cardBgClass}`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2.5">
                <div
                  className={`p-2 rounded-xl text-white shadow-md bg-gradient-to-r ${accentColors.gradient}`}
                >
                  <FileText className="w-4 h-4" />
                </div>
                <div>
                  <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    1. Identitas Operasi & Sumber Informasi
                  </h2>
                  <p className="text-[11px] text-slate-400">
                    Nama resmi operasi SAR dan data pelapor awal
                  </p>
                </div>
              </div>
            </div>

            {/* Nama Operasi */}
            <div>
              <label className={labelClass}>
                Nama / Judul Operasi SAR <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                required
                value={namaOperasi}
                onChange={(e) => setNamaOperasi(e.target.value)}
                placeholder="Operasi SAR, Kecelakaan Kapal / Kondisi Membahayakan Manusia / Bencana Banjir di Kota Kendari"
                className={`${inputClass} font-semibold text-sm`}
              />
            </div>

            {/* Sumber Informasi / Data Pelapor */}
            <div className={`p-3.5 rounded-xl border space-y-3 ${innerBoxClass}`}>
              <div className="flex items-center gap-2">
                <UserCheck className="w-3.5 h-3.5 text-orange-500" />
                <span className={`text-xs font-bold uppercase tracking-wider ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                  Data Pelapor Awal
                </span>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                  <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                    Nama Pelapor
                  </label>
                  <input
                    type="text"
                    value={namaPelapor}
                    onChange={(e) => setNamaPelapor(e.target.value)}
                    placeholder="Bpk. La Ode..."
                    className={inputClass}
                  />
                </div>

                <div>
                  <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                    Hubungan / Instansi
                  </label>
                  <input
                    type="text"
                    value={hubunganPelapor}
                    onChange={(e) => setHubunganPelapor(e.target.value)}
                    placeholder="Keluarga / Polairud / Kades..."
                    className={inputClass}
                  />
                  {/* Quick chips for hubungan */}
                  <div className="flex flex-wrap gap-1 mt-1.5">
                    {['Masyarakat', 'Keluarga', 'Polairud', 'Pos SAR', 'BPBD', 'Kades'].map((hub) => (
                      <button
                        key={hub}
                        type="button"
                        onClick={() => setHubunganPelapor(hub)}
                        className={`text-[9px] px-1.5 py-0.5 rounded border transition-colors ${
                          hubunganPelapor === hub
                            ? 'bg-orange-500/20 text-orange-500 border-orange-500/40 font-bold'
                            : 'bg-slate-200/50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-transparent hover:border-slate-400'
                        }`}
                      >
                        {hub}
                      </button>
                    ))}
                  </div>
                </div>

                <div>
                  <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                    No. HP Pelapor
                  </label>
                  <div className="relative">
                    <input
                      type="text"
                      value={noTelpPelapor}
                      onChange={(e) => setNoTelpPelapor(e.target.value)}
                      placeholder="0812-xxxx-xxxx"
                      className={inputClass}
                    />
                    <Phone className="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-2.5 pointer-events-none" />
                  </div>
                </div>
              </div>
            </div>

            {/* Waktu Kejadian & Waktu Lapor (Modern Google-Style Sequential Pickers) */}
            <div className="space-y-3 pt-2">
              <DateTimeInput24H
                id="input-waktu-kejadian"
                label="1.1 Waktu Kejadian (Time of Incident)"
                value={waktuKejadian}
                onChange={(val) => setWaktuKejadian(val)}
                required
                helpText="Waktu estimasi awal insiden terjadi (WITA / UTC+8)"
              />

              <DateTimeInput24H
                id="input-waktu-lapor"
                label="1.2 Waktu Lapor / Terima Info (Time of Report)"
                value={waktuLapor}
                onChange={(val) => setWaktuLapor(val)}
                required
                helpText="Waktu saat informasi resmi diterima oleh Kansar Kendari (WITA / UTC+8)"
              />
            </div>
          </div>

          {/* ============================================================
              POIN 2: Pelaksanaan SAR & Tim Berangkat
             ============================================================ */}
          <div className={`border rounded-2xl p-5 space-y-4 ${cardBgClass}`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2.5">
                <div
                  className={`p-2 rounded-xl text-white shadow-md bg-gradient-to-r ${accentColors.gradient}`}
                >
                  <Navigation className="w-4 h-4" />
                </div>
                <div>
                  <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    2. Pelaksanaan SAR & Tim Berangkat
                  </h2>
                  <p className="text-[11px] text-slate-400">
                    Waktu pergerakan tim rescue, estimasi tiba, dan jarak tempuh jalur jalan raya
                  </p>
                </div>
              </div>
            </div>

            {/* Waktu Berangkat & ETA Tiba (Sequential Modern Pickers) */}
            <div className="space-y-3">
              <DateTimeInput24H
                id="input-waktu-berangkat"
                label="2.1 Waktu Tim Berangkat (Time of Departure)"
                value={waktuBerangkat}
                onChange={(val) => setWaktuBerangkat(val)}
                required
                helpText="Waktu tim rescue mulai bergerak dari pangkalan menuju LKP"
                quickAddHours={[1, 2]}
              />

              <DateTimeInput24H
                id="input-eta-tiba"
                label="2.2 Estimasi Waktu Tiba di LKP (ETA)"
                value={etaTiba}
                onChange={(val) => setEtaTiba(val)}
                required
                helpText="Perkiraan waktu tim rescue tiba di lokasi kejadian (LKP)"
                quickAddHours={[1, 2, 3]}
              />
            </div>

            {/* Tim Berangkat selector */}
            <div className={`p-4 rounded-xl border space-y-2.5 ${innerBoxClass}`}>
              <div className="flex items-center justify-between">
                <label className="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                  <Users className="w-3.5 h-3.5 text-orange-500" />
                  <span>Pilih Tim / Unit Siaga SAR yang Diberangkatkan:</span>
                </label>
                <span className="text-[10px] text-orange-500 font-bold px-2 py-0.5 rounded-full bg-orange-500/10 border border-orange-500/20">
                  {selectedTimIds.length} Tim Terpilih
                </span>
              </div>
              <div className="flex flex-wrap gap-2 pt-1">
                {masterTim.map((tim) => {
                  const isSelected = selectedTimIds.includes(tim.id_tim);
                  return (
                    <button
                      type="button"
                      key={tim.id_tim}
                      onClick={() => toggleTeam(tim.id_tim)}
                      className={`px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all ${
                        isSelected
                          ? `bg-gradient-to-r ${accentColors.gradient} text-white border-orange-500/50 shadow-sm scale-[1.02]`
                          : isDarkMode
                          ? 'bg-slate-900 border-slate-700 text-slate-300 hover:border-slate-500'
                          : 'bg-white border-slate-300 text-slate-700 hover:border-slate-400'
                      }`}
                    >
                      {isSelected ? '✓ ' : '+ '}
                      {tim.nama_tim}
                    </button>
                  );
                })}
              </div>
            </div>

            {/* Jarak Tempuh & Estimasi Waktu Tempuh */}
            <div
              className={`p-4 sm:p-5 rounded-2xl border transition-all ${
                isDarkMode
                  ? 'bg-slate-950/80 border-orange-500/30'
                  : 'bg-gradient-to-br from-orange-50/60 to-amber-50/30 border-orange-200 shadow-sm'
              }`}
            >
              <div className="flex flex-wrap items-center justify-between gap-2 pb-3 mb-3 border-b border-orange-500/20">
                <div className="flex items-center gap-2">
                  <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500">
                    <Car className="w-4 h-4" />
                  </div>
                  <div>
                    <span className="text-xs font-bold uppercase tracking-wider text-slate-900 dark:text-white block">
                      Ukuran Jarak Tempuh & Estimasi Waktu Perjalanan
                    </span>
                    <span className="text-[11px] text-slate-500 dark:text-slate-400">
                      Jarak dan estimasi waktu tempuh menuju lokasi kejadian (LKP)
                    </span>
                  </div>
                </div>
                <span className="text-[10px] font-bold text-orange-600 dark:text-orange-400 px-2.5 py-1 rounded-full bg-orange-500/10 border border-orange-500/20 shrink-0">
                  Jarak & ETA
                </span>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-5">
                {/* 1. Jarak Tempuh Input with Integrated Unit Selector */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-slate-700 dark:text-slate-300">
                      Jarak Tempuh ke LKP
                    </label>
                    <span className="text-[10px] text-slate-400 font-mono">
                      {jarakSatuan === 'Km' ? 'Jalur Darat (Km)' : 'Jalur Laut (Nm)'}
                    </span>
                  </div>
                  
                  <div className="flex items-stretch rounded-xl border overflow-hidden shadow-xs focus-within:ring-2 focus-within:ring-orange-500 transition-all">
                    <input
                      type="number"
                      step="0.1"
                      min="0"
                      value={jarakAngka}
                      onChange={(e) => setJarakAngka(e.target.value)}
                      placeholder="0.0"
                      className={`min-w-0 flex-1 text-base sm:text-lg font-bold font-mono px-3.5 py-2.5 outline-none ${
                        isDarkMode
                          ? 'bg-slate-900 text-white placeholder-slate-600'
                          : 'bg-white text-slate-900 placeholder-slate-300'
                      }`}
                    />
                    <div className="border-l border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800/80 flex items-center shrink-0">
                      <select
                        value={jarakSatuan}
                        onChange={(e) => setJarakSatuan(e.target.value)}
                        className={`px-3 py-2 text-xs font-bold bg-transparent outline-none cursor-pointer text-slate-700 dark:text-slate-200`}
                      >
                        <option value="Km" className={isDarkMode ? 'bg-slate-900 text-white' : 'bg-white text-slate-900'}>
                          Kilometer (Km)
                        </option>
                        <option value="Nm" className={isDarkMode ? 'bg-slate-900 text-white' : 'bg-white text-slate-900'}>
                          Mil Laut (Nm)
                        </option>
                      </select>
                    </div>
                  </div>
                </div>

                {/* 2. Estimasi Waktu Tempuh Input with Quick Preset Chips */}
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between">
                    <label className="text-xs font-bold text-slate-700 dark:text-slate-300">
                      Estimasi Waktu Tempuh Perjalanan
                    </label>
                    <span className="text-[10px] text-slate-400 font-mono">
                      Format: Jam & Menit
                    </span>
                  </div>

                  <input
                    type="text"
                    value={etaLokasi}
                    onChange={(e) => setEtaLokasi(e.target.value)}
                    placeholder="Contoh: 1 Jam 30 Menit"
                    className={`w-full text-xs sm:text-sm font-semibold px-3.5 py-2.5 rounded-xl border outline-none transition-all focus:ring-2 ${accentColors.ring} ${
                      isDarkMode
                        ? 'bg-slate-900 border-slate-700 text-white focus:border-orange-500'
                        : 'bg-white border-slate-300 text-slate-900 focus:border-orange-500 shadow-xs'
                    }`}
                  />

                  {/* Preset Pills with wrap */}
                  <div className="flex flex-wrap items-center gap-1.5 pt-1">
                    <span className="text-[10px] text-slate-400 mr-0.5">Pintasan:</span>
                    {['30 Menit', '45 Menit', '1 Jam', '1 Jam 30 Menit', '2 Jam', '3 Jam'].map((et) => (
                      <button
                        key={et}
                        type="button"
                        onClick={() => setEtaLokasi(et)}
                        className={`text-[10px] font-semibold px-2 py-0.5 rounded-lg border transition-all cursor-pointer ${
                          etaLokasi === et
                            ? 'bg-orange-500 text-white border-orange-500 font-bold shadow-xs'
                            : isDarkMode
                            ? 'bg-slate-900 text-slate-400 border-slate-700 hover:text-slate-200 hover:border-slate-600'
                            : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300 hover:bg-slate-50 shadow-xs'
                        }`}
                      >
                        {et}
                      </button>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          </div>

          {/* ============================================================
              POIN 3: Lokasi Kejadian & Titik Koordinat (LKP)
             ============================================================ */}
          <div className={`border rounded-2xl p-5 space-y-4 ${cardBgClass}`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2.5">
                <div
                  className={`p-2 rounded-xl text-white shadow-md bg-gradient-to-r ${accentColors.gradient}`}
                >
                  <Compass className="w-4 h-4" />
                </div>
                <div>
                  <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    3. Lokasi Kejadian & Titik Koordinat (LKP)
                  </h2>
                  <p className="text-[11px] text-slate-400">
                    Deskripsi lokasi insiden dan format koordinat resmi Basarnas
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setShowDmsHelper(!showDmsHelper)}
                className="text-xs font-bold text-orange-500 hover:text-orange-600 underline cursor-pointer"
              >
                {showDmsHelper ? 'Tutup Konverter DMS' : '📐 Generator Segmented DMS'}
              </button>
            </div>

            {/* DMS Helper Box */}
            {showDmsHelper && (
              <div className={`p-4 rounded-xl border space-y-3 ${innerBoxClass} border-orange-500/30`}>
                <div className="text-xs font-bold text-orange-500">
                  Konverter Koordinat Format Basarnas (Derajat, Menit, Detik):
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                  {/* Lintang */}
                  <div className="space-y-1">
                    <span className="text-slate-500 dark:text-slate-400 font-semibold">Lintang (Latitude):</span>
                    <div className="flex flex-wrap items-center gap-1.5">
                      <input
                        type="text"
                        value={latDeg}
                        onChange={(e) => setLatDeg(e.target.value)}
                        className={`w-12 px-2 py-1.5 border rounded-lg text-center font-mono ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                        placeholder="04"
                      />
                      <span className="font-bold">°</span>
                      <input
                        type="text"
                        value={latMin}
                        onChange={(e) => setLatMin(e.target.value)}
                        className={`w-12 px-2 py-1.5 border rounded-lg text-center font-mono ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                        placeholder="05"
                      />
                      <span className="font-bold">'</span>
                      <input
                        type="text"
                        value={latSec}
                        onChange={(e) => setLatSec(e.target.value)}
                        className={`w-12 px-2 py-1.5 border rounded-lg text-center font-mono ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                        placeholder="03"
                      />
                      <span className="font-bold">"</span>
                      <select
                        value={latDir}
                        onChange={(e) => setLatDir(e.target.value)}
                        className={`px-2 py-1.5 border rounded-lg font-bold ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      >
                        <option value="S">S (LS)</option>
                        <option value="N">N (LU)</option>
                      </select>
                    </div>
                  </div>

                  {/* Bujur */}
                  <div className="space-y-1">
                    <span className="text-slate-500 dark:text-slate-400 font-semibold">Bujur (Longitude):</span>
                    <div className="flex flex-wrap items-center gap-1.5">
                      <input
                        type="text"
                        value={lonDeg}
                        onChange={(e) => setLonDeg(e.target.value)}
                        className={`w-14 px-2 py-1.5 border rounded-lg text-center font-mono ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                        placeholder="122"
                      />
                      <span className="font-bold">°</span>
                      <input
                        type="text"
                        value={lonMin}
                        onChange={(e) => setLonMin(e.target.value)}
                        className={`w-12 px-2 py-1.5 border rounded-lg text-center font-mono ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                        placeholder="25"
                      />
                      <span className="font-bold">'</span>
                      <input
                        type="text"
                        value={lonSec}
                        onChange={(e) => setLonSec(e.target.value)}
                        className={`w-12 px-2 py-1.5 border rounded-lg text-center font-mono ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                        placeholder="42"
                      />
                      <span className="font-bold">"</span>
                      <select
                        value={lonDir}
                        onChange={(e) => setLonDir(e.target.value)}
                        className={`px-2 py-1.5 border rounded-lg font-bold ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      >
                        <option value="E">E (BT)</option>
                        <option value="W">W (BB)</option>
                      </select>
                    </div>
                  </div>
                </div>

                <div className="flex justify-end pt-1">
                  <button
                    type="button"
                    onClick={handleApplyDms}
                    className={`px-3 py-1.5 bg-gradient-to-r ${accentColors.gradient} text-white rounded-lg text-xs font-bold shadow-sm`}
                  >
                    Terapkan ke Kolom Koordinat
                  </button>
                </div>
              </div>
            )}

            {/* Format Teks Koordinat Resmi Basarnas */}
            <div>
              <label className={labelClass}>
                Format Teks Koordinat Resmi Basarnas <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                required
                value={koordinat}
                onChange={(e) => setKoordinat(e.target.value)}
                placeholder={'04° 05\' 03" S - 122° 25\' 42" E'}
                className={`w-full px-3.5 py-2.5 text-xs sm:text-sm font-mono font-bold tracking-wider rounded-xl border outline-none transition-all focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-950 border-slate-700 text-emerald-400 focus:border-emerald-500'
                    : 'bg-emerald-50/50 border-emerald-300 text-emerald-800 focus:border-emerald-500'
                }`}
              />
              <p className="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
                Contoh: 04° 05' 03" S - 122° 25' 42" E
              </p>
            </div>

            {/* Deskripsi Lokasi Wilayah Perairan */}
            <div>
              <label className={labelClass}>
                Deskripsi Lokasi / Wilayah Kejadian <span className="text-red-500">*</span>
              </label>
              <div className="relative">
                <input
                  type="text"
                  required
                  value={lokasiKejadian}
                  onChange={(e) => setLokasiKejadian(e.target.value)}
                  placeholder="Contoh: Perairan Karang Kapota, Kab. Wakatobi, Sulawesi Tenggara"
                  className={inputClass}
                />
                <MapPin className="w-3.5 h-3.5 text-slate-400 absolute right-2.5 top-2.5 pointer-events-none" />
              </div>
            </div>
          </div>

          {/* ============================================================
              POIN 4: Kronologi Kejadian
             ============================================================ */}
          <div className={`border rounded-2xl p-5 space-y-4 ${cardBgClass}`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2.5">
                <div
                  className={`p-2 rounded-xl text-white shadow-md bg-gradient-to-r ${accentColors.gradient}`}
                >
                  <FileText className="w-4 h-4" />
                </div>
                <div>
                  <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    4. Kronologi Kejadian
                  </h2>
                  <p className="text-[11px] text-slate-400">
                    Alur cerita insiden dan perkembangan awal
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={() => setShowAiModal(true)}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gradient-to-r from-purple-600/20 to-indigo-600/20 hover:from-purple-600/30 hover:to-indigo-600/30 border border-purple-500/40 text-purple-400 rounded-xl text-xs font-bold transition-all shadow-sm"
              >
                <Sparkles className="w-3.5 h-3.5 text-purple-400" />
                <span>Asisten AI Kronologi</span>
              </button>
            </div>

            {/* Quick preset buttons */}
            <div className="flex flex-wrap gap-1.5">
              <span className="text-[10px] text-slate-400 self-center mr-1">Template Cepat:</span>
              <button
                type="button"
                onClick={() =>
                  handleInsertKronologiPreset(
                    'Pada tanggal tersebut korban pergi melaut mencari ikan menggunakan perahu/longboat di sekitar perairan setempat. Hingga batas waktu kepulangan yang biasanya korban belum kembali ke rumah, dan pencarian awal oleh nelayan setempat hanya menemukan perahu korban dalam keadaan mesin menyala tanpa pengemudi.'
                  )
                }
                className={`text-[10px] px-2 py-1 rounded-lg border transition-colors ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
              >
                Man Overboard / Nelayan
              </button>

              <button
                type="button"
                onClick={() =>
                  handleInsertKronologiPreset(
                    'Kapal mengalami mati mesin (engine failure) saat dalam pelayaran akibat cuaca buruk dan gelombang tinggi, sehingga terombang-ambing di perairan dan membutuhkan evakuasi pertolongan SAR.'
                  )
                }
                className={`text-[10px] px-2 py-1 rounded-lg border transition-colors ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
              >
                Mati Mesin Kapal
              </button>

              <button
                type="button"
                onClick={() =>
                  handleInsertKronologiPreset(
                    'Korban dilaporkan mandi/berenang di sekitar pantai/muara sungai kemudian terseret arus deras dan tenggelam. Saksi di lokasi berusaha menolong namun korban hilang dari pandangan.'
                  )
                }
                className={`text-[10px] px-2 py-1 rounded-lg border transition-colors ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
              >
                Terseret Arus Pantai
              </button>
            </div>

            <textarea
              rows={4}
              required
              value={kronologi}
              onChange={(e) => setKronologi(e.target.value)}
              placeholder="Ceritakan urutan kejadian secara rinci: dari saat korban berangkat, tanda-tanda ditemukan, hingga laporan masuk ke Kantor SAR..."
              className={`${inputClass} leading-relaxed font-sans`}
            />

            <div className="flex justify-between items-center text-[10px] text-slate-400">
              <span>Gunakan bahasa baku dinas SAR</span>
              <span>{kronologi.length} karakter</span>
            </div>
          </div>

          {/* ============================================================
              POIN 5: Data Korban Dinamis (POB)
             ============================================================ */}
          <div className={`border rounded-2xl p-5 space-y-4 ${cardBgClass}`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2.5">
                <div
                  className={`p-2 rounded-xl text-white shadow-md bg-gradient-to-r ${accentColors.gradient}`}
                >
                  <Users className="w-4 h-4" />
                </div>
                <div>
                  <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    5. Data Korban ({korbanList.length} Orang)
                  </h2>
                  <p className="text-[11px] text-slate-400">
                    Jumlah Person on Board (POB) otomatis terkalkulasi
                  </p>
                </div>
              </div>

              <button
                type="button"
                onClick={handleAddKorban}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border transition-all ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-200 border-slate-700'
                    : 'bg-white hover:bg-slate-100 text-slate-800 border-slate-300 shadow-sm'
                }`}
              >
                <Plus className="w-3.5 h-3.5 text-orange-500" />
                <span>Tambah Korban</span>
              </button>
            </div>

            <div className="space-y-3">
              {korbanList.map((k, idx) => (
                <div
                  key={idx}
                  className={`p-3.5 rounded-xl border space-y-3 relative group transition-colors ${innerBoxClass}`}
                >
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className="w-5 h-5 rounded-full bg-orange-500 text-white text-[10px] font-bold flex items-center justify-center">
                        {idx + 1}
                      </span>
                      <span className="text-xs font-bold text-orange-500">
                        Korban #{idx + 1}
                      </span>
                    </div>

                    {korbanList.length > 1 && (
                      <button
                        type="button"
                        onClick={() => handleRemoveKorban(idx)}
                        className="text-slate-400 hover:text-red-500 p-1 rounded-lg transition-colors"
                        title="Hapus Korban Ini"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                    <div className="sm:col-span-2">
                      <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                        Nama Lengkap
                      </label>
                      <input
                        type="text"
                        required
                        value={k.nama_korban}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].nama_korban = e.target.value;
                          setKorbanList(updated);
                        }}
                        placeholder="Nama Korban..."
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                        Umur
                      </label>
                      <input
                        type="text"
                        value={k.umur}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].umur = e.target.value;
                          setKorbanList(updated);
                        }}
                        placeholder="35 Tahun"
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                        Jenis Kelamin
                      </label>
                      <select
                        value={k.jk}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].jk = e.target.value;
                          setKorbanList(updated);
                        }}
                        className={`${inputClass} font-semibold cursor-pointer`}
                      >
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                    <div className="sm:col-span-3">
                      <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                        Alamat / Asal Korban
                      </label>
                      <input
                        type="text"
                        value={k.alamat}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].alamat = e.target.value;
                          setKorbanList(updated);
                        }}
                        placeholder="Alamat domisili korban..."
                        className={inputClass}
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] text-slate-500 dark:text-slate-400 mb-1">
                        Status Awal
                      </label>
                      <select
                        value={k.status_korban}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].status_korban = e.target.value as any;
                          setKorbanList(updated);
                        }}
                        className={`${inputClass} font-bold cursor-pointer ${
                          k.status_korban === 'DP'
                            ? 'text-amber-500'
                            : k.status_korban === 'S'
                            ? 'text-emerald-500'
                            : k.status_korban === 'MD'
                            ? 'text-red-500'
                            : 'text-slate-400'
                        }`}
                      >
                        <option value="DP">DP (Dalam Pencarian)</option>
                        <option value="S">S (Selamat)</option>
                        <option value="MD">MD (Meninggal Dunia)</option>
                        <option value="H">H (Hilang / Nihil)</option>
                      </select>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* ============================================================
              POIN 6: Unsur SAR Terlibat
             ============================================================ */}
          <UnsurSarEditor
            unsurList={unsurList}
            onChangeUnsur={setUnsurList}
            masterTim={masterTim}
            title="6. Unsur SAR Terlibat"
            description="Daftar instansi potensi SAR gabungan dan jumlah personel yang dikerahkan"
          />

          {/* ============================================================
              POIN 7: Alut & Palsar Terlibat
             ============================================================ */}
          <AlutPalsarEditor
            alutList={alutList}
            onChangeAlut={setAlutList}
            masterAlut={masterAlut}
            title="7. Alut & Palsar Terlibat"
            description="Daftar armada kapal/perahu, kendaraan, dan peralatan SAR yang dikerahkan"
          />

          {/* ============================================================
              POIN 8: Cuaca & Faktor Penghambat
             ============================================================ */}
          <div className={`border rounded-2xl p-5 space-y-4 ${cardBgClass}`}>
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2.5">
                <div
                  className={`p-2 rounded-xl text-white shadow-md bg-gradient-to-r ${accentColors.gradient}`}
                >
                  <Wind className="w-4 h-4" />
                </div>
                <div>
                  <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                    8. Kondisi Cuaca & Faktor Penghambat
                  </h2>
                  <p className="text-[11px] text-slate-400">
                    Parameter meteorologi BMKG dan kendala di lapangan
                  </p>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label className={labelClass}>
                  Kondisi Cuaca (BMKG)
                </label>
                <input
                  type="text"
                  value={cuaca}
                  onChange={(e) => setCuaca(e.target.value)}
                  placeholder="Cerah Berawan / Hujan Sedang..."
                  className={inputClass}
                />
                {/* Cuaca quick pills */}
                <div className="flex flex-wrap gap-1 mt-1.5">
                  {['Cerah', 'Cerah Berawan', 'Berawan', 'Hujan Ringan', 'Hujan Lebat'].map((c) => (
                    <button
                      key={c}
                      type="button"
                      onClick={() => setCuaca(c)}
                      className={`text-[9px] px-1.5 py-0.5 rounded border transition-colors ${
                        cuaca === c
                          ? 'bg-orange-500/20 text-orange-500 border-orange-500/40 font-bold'
                          : 'bg-slate-200/50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-transparent hover:border-slate-400'
                      }`}
                    >
                      {c}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className={labelClass}>
                  Tinggi Gelombang
                </label>
                <input
                  type="text"
                  value={gelombang}
                  onChange={(e) => setGelombang(e.target.value)}
                  placeholder="0.5 - 1.25 M"
                  className={inputClass}
                />
                {/* Gelombang quick pills */}
                <div className="flex flex-wrap gap-1 mt-1.5">
                  {['0.1 - 0.5 M', '0.5 - 1.25 M', '1.25 - 2.5 M', '2.5 - 4.0 M'].map((g) => (
                    <button
                      key={g}
                      type="button"
                      onClick={() => setGelombang(g)}
                      className={`text-[9px] px-1.5 py-0.5 rounded border transition-colors ${
                        gelombang === g
                          ? 'bg-orange-500/20 text-orange-500 border-orange-500/40 font-bold'
                          : 'bg-slate-200/50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-transparent hover:border-slate-400'
                      }`}
                    >
                      {g}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className={labelClass}>
                  Kecepatan & Arah Angin
                </label>
                <input
                  type="text"
                  value={angin}
                  onChange={(e) => setAngin(e.target.value)}
                  placeholder="Timur - Tenggara, 5 - 15 Knots"
                  className={inputClass}
                />
                {/* Angin quick pills */}
                <div className="flex flex-wrap gap-1 mt-1.5">
                  {['5 - 15 Knots', '10 - 20 Knots', '15 - 25 Knots'].map((a) => (
                    <button
                      key={a}
                      type="button"
                      onClick={() => setAngin(`Barat Daya - Barat Laut, ${a}`)}
                      className="text-[9px] px-1.5 py-0.5 rounded bg-slate-200/50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-transparent hover:border-slate-400"
                    >
                      {a}
                    </button>
                  ))}
                </div>
              </div>
            </div>

            <div>
              <label className={labelClass}>
                Faktor Penghambat (Opsional)
              </label>
              <input
                type="text"
                value={faktorPenghambat}
                onChange={(e) => setFaktorPenghambat(e.target.value)}
                placeholder="Nihil / Arus deras / Gelombang tinggi / Jarak pandang terbatas..."
                className={inputClass}
              />
              {/* Penghambat quick pills */}
              <div className="flex flex-wrap gap-1 mt-1.5">
                {['Nihil', 'Arus Deras', 'Gelombang Tinggi', 'Jarak Pandang Terbatas', 'Hujan Lebat'].map((f) => (
                  <button
                    key={f}
                    type="button"
                    onClick={() => setFaktorPenghambat(f)}
                    className={`text-[9px] px-1.5 py-0.5 rounded border transition-colors ${
                      faktorPenghambat === f
                        ? 'bg-orange-500/20 text-orange-500 border-orange-500/40 font-bold'
                        : 'bg-slate-200/50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-transparent hover:border-slate-400'
                    }`}
                  >
                    {f}
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Action Submit Buttons */}
          <div className="flex items-center justify-between pt-2">
            <button
              type="button"
              onClick={onBack}
              className={`px-4 py-2.5 rounded-xl text-xs font-semibold border transition-colors ${
                isDarkMode
                  ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                  : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-300 shadow-sm'
              }`}
            >
              Batal
            </button>

            <button
              id="btn-simpan-operasi"
              type="submit"
              disabled={isSubmitting}
              className={`inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-xl text-xs sm:text-sm font-bold shadow-lg shadow-orange-600/30 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50`}
            >
              <Save className="w-4 h-4" />
              <span>
                {isSubmitting
                  ? 'Menyimpan...'
                  : isEditing
                  ? 'Simpan Pembaruan Data'
                  : 'Kunci & Terbitkan Laporan Awal'}
              </span>
            </button>
          </div>
        </form>

        {/* Live Preview Column Right */}
        <div className="lg:col-span-5 space-y-4">
          <div
            className={`sticky top-24 border rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[calc(100vh-120px)] transition-colors ${cardBgClass}`}
          >
            <div
              className={`flex items-center justify-between px-4 py-3 border-b ${
                isDarkMode ? 'bg-slate-800/90 border-slate-700' : 'bg-slate-100 border-slate-200'
              }`}
            >
              <div className="flex items-center gap-2">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <h3 className={`text-xs font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                  Live Preview: FILE AKTIF (Laporan Awal)
                </h3>
              </div>

              <button
                type="button"
                onClick={handleCopyPreview}
                className={`inline-flex items-center gap-1.5 px-3 py-1 bg-gradient-to-r ${accentColors.gradient} text-white rounded-lg text-xs font-bold transition-all shadow-sm hover:scale-[1.02] active:scale-[0.98]`}
              >
                {copiedPreview ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                <span>{copiedPreview ? 'Tersalin!' : 'Salin WA'}</span>
              </button>
            </div>

            <div
              className={`p-4 flex-1 overflow-y-auto ${
                isDarkMode ? 'bg-slate-950/80 text-slate-300' : 'bg-slate-50 text-slate-800'
              }`}
            >
              <pre className="font-mono text-[11px] whitespace-pre-wrap leading-relaxed select-text font-medium">
                {liveWaText}
              </pre>
            </div>

            <div
              className={`px-4 py-2 border-t text-[11px] flex items-center justify-between ${
                isDarkMode
                  ? 'bg-slate-900 border-slate-800 text-slate-400'
                  : 'bg-slate-100 border-slate-200 text-slate-600'
              }`}
            >
              <span>Format Standar Basarnas Kendari</span>
              <span className="font-mono font-bold">{liveWaText.length} Karakter</span>
            </div>
          </div>
        </div>
      </div>

      {/* AI Assistant Modal for Kronologi */}
      {showAiModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-150">
          <div
            className={`w-full max-w-lg border rounded-2xl p-5 shadow-2xl space-y-4 ${
              isDarkMode ? 'bg-slate-900 border-purple-500/40 text-slate-100' : 'bg-white border-purple-300 text-slate-900'
            }`}
          >
            <div className="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2 text-purple-500">
                <Sparkles className="w-5 h-5" />
                <h3 className={`text-sm font-bold ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                  Poles Kronologi Kejadian dengan AI
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setShowAiModal(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1 rounded-lg"
              >
                ✕
              </button>
            </div>

            <p className="text-xs text-slate-500 dark:text-slate-300 leading-relaxed">
              Masukkan catatan kasar, perkiraan waktu, atau poin kronologi. Asisten AI akan merangkumnya menjadi narasi kronologi baku sesuai standar dinas Basarnas.
            </p>

            <textarea
              rows={4}
              value={aiDraftPrompt}
              onChange={(e) => setAiDraftPrompt(e.target.value)}
              placeholder="Contoh catatan: Korban pergi memancing subuh tadi di wakatobi naik perahu kecil, jam 7 ditemukan perahu muter mesin nyala tapi orangnya gak ada..."
              className={inputClass}
            />

            <div className="flex justify-end gap-2 pt-1">
              <button
                type="button"
                onClick={() => setShowAiModal(false)}
                className={`px-3 py-1.5 rounded-xl text-xs font-semibold border ${
                  isDarkMode
                    ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                    : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                }`}
              >
                Batal
              </button>
              <button
                type="button"
                disabled={isAiDrafting}
                onClick={handleGenerateAiKronologi}
                className="inline-flex items-center gap-1.5 px-4 py-1.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-md shadow-purple-600/30 transition-all hover:scale-[1.02]"
              >
                <Sparkles className="w-3.5 h-3.5" />
                <span>{isAiDrafting ? 'Menyusun Narasi...' : 'Generate Kronologi'}</span>
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
