import React, { useState, useEffect, useMemo } from 'react';
import {
  ArrowLeft,
  X,
  Plus,
  Trash2,
  Sparkles,
  Save,
  MapPin,
  Clock,
  Compass,
  Ship,
  Users,
  Check,
  Copy,
  CloudSun,
  ShieldCheck,
  Send,
  FileText,
} from 'lucide-react';
import { Operasi, Renops, RenopsSRU, DetailUnsur, DetailAlut, MasterAlut } from '../types';
import { hitungHariKe, formatTW, generateRenopsWA } from '../utils/templates';
import { showSuccessAlert, showErrorAlert, showToast } from '../utils/alerts';
import { DateTimeInput24H } from './DateTimeInput24H';
import { UnsurAlutEditor } from './UnsurAlutEditor';
import { useTheme } from '../context/ThemeContext';

interface FormRenopsModalProps {
  isOpen: boolean;
  operasi: Operasi;
  masterAlut: MasterAlut[];
  onClose: () => void;
  onSuccess: (newRenops: Renops) => void;
}

export const FormRenopsModal: React.FC<FormRenopsModalProps> = ({
  isOpen,
  operasi,
  masterAlut,
  onClose,
  onSuccess,
}) => {
  const { isDarkMode } = useTheme();

  // Target report date default: today (WITA)
  const now = new Date();
  const todayDateStr = now.toISOString().split('T')[0];

  const [tanggalLaporan, setTanggalLaporan] = useState<string>(todayDateStr);

  // Auto-calculated H-day
  const hariKe = useMemo(() => {
    return hitungHariKe(operasi.waktu_berangkat || operasi.waktu_lapor, tanggalLaporan);
  }, [operasi.waktu_berangkat, operasi.waktu_lapor, tanggalLaporan]);

  // Briefing Time & Start Time default: 07:00 & 07:30 WITA
  const getInitialTime = (hours: number, minutes: number) => {
    const d = new Date(tanggalLaporan);
    d.setHours(hours, minutes, 0, 0);
    const tzOffset = d.getTimezoneOffset() * 60000;
    const local = new Date(d.getTime() - tzOffset);
    return local.toISOString().slice(0, 16);
  };

  const [waktuBriefing, setWaktuBriefing] = useState<string>(getInitialTime(7, 0));
  const [waktuMulaiOps, setWaktuMulaiOps] = useState<string>(getInitialTime(7, 30));
  const [cuaca, setCuaca] = useState<string>(operasi.cuaca || 'Cerah Berawan, Angin Barat 5-15 Kts, Gelombang 0.5 - 1.25 M');

  // Update briefing times when date changes
  useEffect(() => {
    setWaktuBriefing(getInitialTime(7, 0));
    setWaktuMulaiOps(getInitialTime(7, 30));
  }, [tanggalLaporan]);

  // SRU list
  const [srus, setSrus] = useState<Omit<RenopsSRU, 'id_sru' | 'id_renops'>[]>([
    {
      nama_sru: 'SRU 1 (Tim Rescue Kansar Kendari)',
      koordinat_area: `A: ${operasi.koordinat || '03° 58\' 30" S - 122° 35\' 12" E'}\nB: 03° 58' 30" S - 122° 40' 00" E\nC: 04° 03' 00" S - 122° 40' 00" E\nD: 04° 03' 00" S - 122° 35' 12" E`,
      luas_area: '10 Nm²',
      pola_pencarian: 'Parallel Track Search',
    },
    {
      nama_sru: 'SRU 2 (Unsur Gabungan & Nelayan)',
      koordinat_area: 'Penyisiran visual di sepanjang pesisir pantai dan pulau terdekat',
      luas_area: '6 Nm²',
      pola_pencarian: 'Creeping Line Search',
    },
  ]);

  // Inherited Unsur & Alut from current operation
  const [unsurList, setUnsurList] = useState<DetailUnsur[]>(
    operasi.detail_unsur ? JSON.parse(JSON.stringify(operasi.detail_unsur)) : []
  );
  const [alutList, setAlutList] = useState<DetailAlut[]>(
    operasi.detail_alut ? JSON.parse(JSON.stringify(operasi.detail_alut)) : []
  );

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isAiLoading, setIsAiLoading] = useState(false);
  const [copiedPreview, setCopiedPreview] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');

  // Live WA preview
  const liveWaText = useMemo(() => {
    const dummyRenops: Partial<Renops> = {
      hari_ke: hariKe,
      tanggal_laporan: tanggalLaporan,
      waktu_briefing: new Date(waktuBriefing).toISOString(),
      waktu_mulai_ops: new Date(waktuMulaiOps).toISOString(),
      cuaca,
    };

    const dummySrus: RenopsSRU[] = srus.map((s, idx) => ({
      ...s,
      id_sru: idx + 1,
      id_renops: 0,
    }));

    return generateRenopsWA(
      operasi,
      dummyRenops,
      dummySrus,
      operasi.korban || [],
      unsurList,
      alutList
    );
  }, [operasi, hariKe, tanggalLaporan, waktuBriefing, waktuMulaiOps, cuaca, srus, unsurList, alutList]);

  // AI SRU recommendation
  const handleAiRecommendSrus = async () => {
    setIsAiLoading(true);
    try {
      const res = await fetch('/api/ai/draft-sru-plan', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          namaOperasi: operasi.nama_operasi,
          lokasi: operasi.lokasi_kejadian,
          koordinat: operasi.koordinat,
          hariKe,
          jenisMedan: 'Perairan/Laut',
        }),
      });
      const data = await res.json();
      if (data.success && data.srus && Array.isArray(data.srus) && data.srus.length > 0) {
        setSrus(data.srus);
        showToast('Rekomendasi sektor SRU berhasil diisi otomatis oleh AI', 'success');
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsAiLoading(false);
    }
  };

  const handleAddSru = () => {
    setSrus((prev) => [
      ...prev,
      {
        nama_sru: `SRU ${prev.length + 1} (Unsur Gabungan)`,
        koordinat_area: `Sektor pencarian tambahan LKP ${operasi.lokasi_kejadian}`,
        luas_area: '8 Nm²',
        pola_pencarian: 'Parallel Track Search',
      },
    ]);
  };

  const handleRemoveSru = (idx: number) => {
    setSrus((prev) => prev.filter((_, i) => i !== idx));
  };

  const handleCopyPreview = async () => {
    try {
      await navigator.clipboard.writeText(liveWaText);
      setCopiedPreview(true);
      showToast('Naskah format WA Renops berhasil disalin!', 'success');
      setTimeout(() => setCopiedPreview(false), 2000);
    } catch (err) {
      console.error(err);
    }
  };

  const handleSubmit = async (e?: React.FormEvent) => {
    if (e) {
      e.preventDefault();
    }
    setErrorMsg('');
    if (!tanggalLaporan) {
      setErrorMsg('Tanggal rencana operasi wajib diisi');
      return;
    }
    setIsSubmitting(true);
    try {
      const renopsPayload = {
        hari_ke: hariKe,
        tanggal_laporan: tanggalLaporan,
        waktu_briefing: new Date(waktuBriefing).toISOString(),
        waktu_mulai_ops: new Date(waktuMulaiOps).toISOString(),
        cuaca: cuaca.trim(),
        unsur_text: '',
        alut_text: '',
        full_text_wa: liveWaText,
      };

      const res = await fetch(`/api/operasi/${operasi.id_operasi}/renops`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          renops: renopsPayload,
          srus,
          detail_unsur: unsurList,
          detail_alut: alutList,
        }),
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.error || 'Gagal menyimpan Rencana Operasi');
      }

      await showSuccessAlert(
        'Renops Berhasil Disimpan!',
        `Rencana Operasi SAR untuk ${hariKe} (${tanggalLaporan}) berhasil disimpan ke database.`
      );

      onSuccess(result.data);
      onClose();
    } catch (err: any) {
      const msg = err.message || 'Gagal menyimpan Rencana Operasi';
      setErrorMsg(msg);
      showErrorAlert('Gagal Menyimpan Renops', msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  if (!isOpen) return null;

  return (
    <div className={`fixed inset-0 z-50 overflow-y-auto min-h-screen flex flex-col transition-colors duration-200 animate-in fade-in ${
      isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-100 text-slate-900'
    }`}>
      {/* Full Page Sticky Top Bar */}
      <div className={`sticky top-0 z-30 border-b backdrop-blur-md transition-colors ${
        isDarkMode
          ? 'bg-slate-900/90 border-slate-800 text-slate-100'
          : 'bg-white/95 border-slate-200 text-slate-900 shadow-sm'
      }`}>
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <button
              onClick={onClose}
              className={`p-2 rounded-xl border transition-colors ${
                isDarkMode
                  ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-300'
                  : 'bg-slate-100 hover:bg-slate-200 border-slate-300 text-slate-700'
              }`}
              title="Tutup & Kembali"
            >
              <ArrowLeft className="w-5 h-5" />
            </button>
            <div>
              <div className="flex items-center gap-2">
                <span className="px-2 py-0.5 text-[10px] font-mono font-bold bg-orange-500/10 text-orange-500 border border-orange-500/20 rounded-md">
                  RENOPS SAR • {hariKe}
                </span>
                <span className="text-xs text-slate-400 font-mono hidden sm:inline">
                  {formatTW(new Date(waktuBriefing).toISOString())}
                </span>
              </div>
              <h2 className="text-base sm:text-lg font-black tracking-tight leading-tight truncate max-w-md sm:max-w-xl">
                Rencana Operasi SAR Harian
              </h2>
            </div>
          </div>

          {/* Action buttons on top bar */}
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={handleCopyPreview}
              className={`hidden sm:inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold rounded-xl border transition-colors ${
                isDarkMode
                  ? 'bg-slate-800 hover:bg-slate-700 border-slate-700 text-slate-200'
                  : 'bg-white hover:bg-slate-100 border-slate-300 text-slate-700 shadow-sm'
              }`}
            >
              {copiedPreview ? <Check className="w-4 h-4 text-emerald-500" /> : <Copy className="w-4 h-4 text-slate-400" />}
              <span>{copiedPreview ? 'Tersalin' : 'Salin Teks WA'}</span>
            </button>

            <button
              type="button"
              onClick={() => handleSubmit()}
              disabled={isSubmitting}
              className="inline-flex items-center gap-2 px-4 sm:px-5 py-2 bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold rounded-xl shadow-lg shadow-orange-600/30 transition-all disabled:opacity-50"
            >
              <Save className="w-4 h-4" />
              <span>{isSubmitting ? 'Menyimpan...' : 'Simpan Renops'}</span>
            </button>
          </div>
        </div>
      </div>

      {/* Main Workspace Layout */}
      <div className="max-w-7xl mx-auto w-full p-4 sm:p-6 lg:p-8 flex-1">
        {errorMsg && (
          <div className="mb-6 p-4 bg-red-500/10 border border-red-500/30 text-red-500 rounded-2xl text-xs font-semibold flex items-center justify-between">
            <span>⚠️ {errorMsg}</span>
            <button onClick={() => setErrorMsg('')} className="p-1 hover:text-red-400">✕</button>
          </div>
        )}

        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
          {/* Left Column: Form Controls (7 cols) */}
          <div className="lg:col-span-7 space-y-6">
            {/* CARD 1: Tanggal & Waktu Pelaksanaan */}
            <div className={`p-5 rounded-2xl border transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                <div className="flex items-center gap-2.5">
                  <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 border border-orange-500/20">
                    <Clock className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold">1. Waktu & Rencana Pelaksanaan</h3>
                    <p className="text-xs text-slate-400">Jadwal briefing, TW militer, dan waktu mulai gerak pencarian</p>
                  </div>
                </div>

                <div className="text-right">
                  <span className="px-2.5 py-1 rounded-lg text-xs font-bold font-mono bg-orange-500/10 text-orange-500 border border-orange-500/20">
                    {hariKe}
                  </span>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block text-xs font-semibold mb-1 text-slate-400">
                    Tanggal Rencana Operasi
                  </label>
                  <input
                    type="date"
                    required
                    value={tanggalLaporan}
                    onChange={(e) => setTanggalLaporan(e.target.value)}
                    className={`w-full px-3 py-2 rounded-xl text-xs border font-mono focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-700 text-white'
                        : 'bg-slate-50 border-slate-300 text-slate-900'
                    }`}
                  />
                  <p className="text-[10px] text-slate-400 mt-1 font-mono">
                    Otomatis dihitung: <strong className="text-orange-500">{hariKe}</strong>
                  </p>
                </div>

                <div>
                  <DateTimeInput24H
                    label="Waktu Briefing & Doa Bersama"
                    value={waktuBriefing}
                    onChange={setWaktuBriefing}
                    helpText="Waktu pengarahan tim di Posko SAR"
                  />
                </div>

                <div className="sm:col-span-2">
                  <DateTimeInput24H
                    label="Waktu Mulai Gerak Pencarian (Ops Dimulai)"
                    value={waktuMulaiOps}
                    onChange={setWaktuMulaiOps}
                    helpText="Estimasi seluruh SRU tolak dari dermaga / posko menuju sektor"
                  />
                </div>
              </div>
            </div>

            {/* CARD 2: Kondisi Cuaca & Wilayah SAR */}
            <div className={`p-5 rounded-2xl border transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center gap-2.5 pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                <div className="p-1.5 rounded-lg bg-cyan-500/10 text-cyan-500 border border-cyan-500/20">
                  <CloudSun className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="text-sm font-bold">2. Kondisi Cuaca & Parameter Laut/Darat</h3>
                  <p className="text-xs text-slate-400">Prakiraan cuaca BMKG maritim, gelombang, visibilitas dan arah angin</p>
                </div>
              </div>

              <div className="space-y-2">
                <textarea
                  rows={2}
                  value={cuaca}
                  onChange={(e) => setCuaca(e.target.value)}
                  placeholder="Cerah berawan, angin barat daya 5-15 kts, gelombang 0.5 - 1.25 meter..."
                  className={`w-full px-3 py-2 rounded-xl text-xs border focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white'
                      : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                />

                <div className="flex flex-wrap gap-1.5 pt-1">
                  {[
                    'Cerah Berawan',
                    'Hujan Ringan',
                    'Gelombang 0.5 - 1.25 M (Tenang)',
                    'Gelombang 1.25 - 2.5 M (Sedang)',
                    'Angin Barat 5-15 Kts',
                    'Arus ke arah Tenggara 0.4 Kts',
                  ].map((preset) => (
                    <button
                      key={preset}
                      type="button"
                      onClick={() => {
                        setCuaca((prev) => (prev ? `${prev}, ${preset}` : preset));
                      }}
                      className={`text-[10px] px-2 py-1 rounded-lg border transition-colors ${
                        isDarkMode
                          ? 'border-slate-800 bg-slate-800/40 text-slate-400 hover:text-white hover:bg-slate-800'
                          : 'border-slate-200 bg-slate-100 text-slate-600 hover:text-slate-900'
                      }`}
                    >
                      + {preset}
                    </button>
                  ))}
                </div>
              </div>
            </div>

            {/* CARD 3: Pembagian Search and Rescue Unit (SRU) */}
            <div className={`p-5 rounded-2xl border transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4 gap-2">
                <div className="flex items-center gap-2.5">
                  <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 border border-orange-500/20">
                    <Compass className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold">3. Pembagian SRU (Search and Rescue Unit)</h3>
                    <p className="text-xs text-slate-400">Pembagian area kerja, pola pencarian, dan koordinat sektor</p>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={handleAiRecommendSrus}
                    disabled={isAiLoading}
                    className={`inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl border text-[11px] font-semibold transition-colors ${
                      isDarkMode
                        ? 'bg-purple-500/10 border-purple-500/30 text-purple-400 hover:bg-purple-500/20'
                        : 'bg-purple-50 border-purple-200 text-purple-700 hover:bg-purple-100'
                    }`}
                  >
                    <Sparkles className="w-3.5 h-3.5 text-purple-500" />
                    <span>{isAiLoading ? 'Menganalisis...' : 'Saran Pola AI'}</span>
                  </button>

                  <button
                    type="button"
                    onClick={handleAddSru}
                    className="inline-flex items-center gap-1 px-2.5 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-[11px] font-bold shadow-sm transition-all"
                  >
                    <Plus className="w-3.5 h-3.5" />
                    <span>Tambah SRU</span>
                  </button>
                </div>
              </div>

              <div className="space-y-4">
                {srus.map((sru, idx) => (
                  <div
                    key={idx}
                    className={`p-4 rounded-xl border space-y-3 relative ${
                      isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span className="w-6 h-6 rounded-lg bg-orange-500 text-white font-bold font-mono text-xs flex items-center justify-center">
                          {idx + 1}
                        </span>
                        <input
                          type="text"
                          value={sru.nama_sru}
                          onChange={(e) => {
                            const newSrus = [...srus];
                            newSrus[idx].nama_sru = e.target.value;
                            setSrus(newSrus);
                          }}
                          className={`font-bold text-xs px-2.5 py-1 rounded-lg border focus:outline-none focus:ring-1 focus:ring-orange-500 ${
                            isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                          }`}
                        />
                      </div>

                      {srus.length > 1 && (
                        <button
                          type="button"
                          onClick={() => handleRemoveSru(idx)}
                          className="p-1 text-red-400 hover:text-red-300 rounded hover:bg-red-500/10 transition-colors"
                          title="Hapus SRU"
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      )}
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                      <div>
                        <label className="block text-[11px] font-semibold mb-1 text-slate-400">Luas Area Sektor</label>
                        <input
                          type="text"
                          value={sru.luas_area}
                          onChange={(e) => {
                            const newSrus = [...srus];
                            newSrus[idx].luas_area = e.target.value;
                            setSrus(newSrus);
                          }}
                          placeholder="e.g. 10 Nm², 2 km sepanjang pesisir"
                          className={`w-full px-2.5 py-1.5 rounded-lg border text-xs ${
                            isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                          }`}
                        />
                      </div>

                      <div>
                        <label className="block text-[11px] font-semibold mb-1 text-slate-400">Pola Pencarian SAR</label>
                        <input
                          type="text"
                          value={sru.pola_pencarian}
                          onChange={(e) => {
                            const newSrus = [...srus];
                            newSrus[idx].pola_pencarian = e.target.value;
                            setSrus(newSrus);
                          }}
                          placeholder="Parallel Track, Creeping Line, Penyelaman..."
                          className={`w-full px-2.5 py-1.5 rounded-lg border text-xs ${
                            isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                          }`}
                        />
                      </div>

                      <div className="sm:col-span-2">
                        <label className="block text-[11px] font-semibold mb-1 text-slate-400">
                          Koordinat Area / Deskripsi Kerja SRU
                        </label>
                        <textarea
                          rows={2}
                          value={sru.koordinat_area}
                          onChange={(e) => {
                            const newSrus = [...srus];
                            newSrus[idx].koordinat_area = e.target.value;
                            setSrus(newSrus);
                          }}
                          className={`w-full px-2.5 py-1.5 rounded-lg border font-mono text-xs ${
                            isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                          }`}
                        />
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            </div>

            {/* CARD 4: Personel & Alut Terlibat */}
            <div className={`p-5 rounded-2xl border transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <UnsurAlutEditor
                title="4. Unsur SAR & Alut Palsar Bertugas"
                description="Daftar instansi gabungan dan armada perahu/peralatan yang ditugaskan hari ini"
                unsurList={unsurList}
                alutList={alutList}
                masterAlut={masterAlut}
                onUnsurChange={setUnsurList}
                onAlutChange={setAlutList}
              />
            </div>
          </div>

          {/* Right Column: Sticky Live WhatsApp Preview (5 cols) */}
          <div className="lg:col-span-5 sticky top-20 space-y-4">
            <div className={`rounded-2xl border overflow-hidden shadow-lg transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200'
            }`}>
              {/* WhatsApp Live Preview Header */}
              <div className="p-4 bg-emerald-600 text-white flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <div className="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-xs">
                    WA
                  </div>
                  <div>
                    <h4 className="text-xs font-bold leading-tight">Live Pratinjau Naskah WhatsApp</h4>
                    <p className="text-[10px] text-emerald-100">Format Resmi Laporan Renops Korps Basarnas</p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={handleCopyPreview}
                  className="px-2.5 py-1 bg-white/15 hover:bg-white/25 rounded-lg text-xs font-semibold flex items-center gap-1 transition-colors"
                >
                  {copiedPreview ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                  <span>{copiedPreview ? 'Tersalin' : 'Salin'}</span>
                </button>
              </div>

              {/* Chat Bubble Container */}
              <div className={`p-4 font-mono text-xs leading-relaxed max-h-[72vh] overflow-y-auto ${
                isDarkMode ? 'bg-slate-950/90 text-slate-200' : 'bg-emerald-50/40 text-slate-800'
              }`}>
                <pre className="whitespace-pre-wrap font-sans text-xs select-all">
                  {liveWaText}
                </pre>
              </div>

              <div className={`p-3 border-t flex items-center justify-between text-[11px] ${
                isDarkMode ? 'bg-slate-950 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-500'
              }`}>
                <span>Karakter: {liveWaText.length}</span>
                <span className="font-semibold text-emerald-600 dark:text-emerald-400">Siap Kirim ke Grup Puskodal & Stakeholder</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
