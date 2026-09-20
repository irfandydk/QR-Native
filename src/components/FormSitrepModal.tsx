import React, { useState, useMemo } from 'react';
import {
  ArrowLeft,
  X,
  Sparkles,
  Save,
  Clock,
  AlertTriangle,
  Users,
  Check,
  Copy,
  Lock,
  FileText,
  ShieldCheck,
  Send,
  HelpCircle,
} from 'lucide-react';
import { Operasi, LaporanHarian, Korban, DetailUnsur, DetailAlut, StatusKorban, MasterAlut } from '../types';
import { hitungHariKe, generateLapbangWA, formatTW } from '../utils/templates';
import { showSuccessAlert, showErrorAlert, showWarningAlert, showToast } from '../utils/alerts';
import { DateTimeInput24H } from './DateTimeInput24H';
import { UnsurSarEditor } from './UnsurSarEditor';
import { AlutPalsarEditor } from './AlutPalsarEditor';
import { useTheme } from '../context/ThemeContext';

interface FormSitrepModalProps {
  isOpen: boolean;
  operasi: Operasi;
  masterAlut?: MasterAlut[];
  onClose: () => void;
  onSuccess: (newLaporan: LaporanHarian) => void;
}

export const FormSitrepModal: React.FC<FormSitrepModalProps> = ({
  isOpen,
  operasi,
  masterAlut = [],
  onClose,
  onSuccess,
}) => {
  const { isDarkMode } = useTheme();
  const now = new Date();

  // Helper for datetime-local
  const getLocalDateTimeString = (dateObj: Date) => {
    const tzOffset = dateObj.getTimezoneOffset() * 60000;
    return new Date(dateObj.getTime() - tzOffset).toISOString().slice(0, 16);
  };

  // Day calculation
  const hariKe = useMemo(() => {
    return hitungHariKe(operasi.waktu_berangkat || operasi.waktu_lapor, now);
  }, [operasi.waktu_berangkat, operasi.waktu_lapor]);

  const [waktuLaporan, setWaktuLaporan] = useState<string>(getLocalDateTimeString(now));
  const [isiLaporan, setIsLaporan] = useState<string>(
    'Tim SAR Gabungan telah melaksanakan penyisiran sesuai Rencana Operasi. Hingga sore hari pencarian dihentikan sementara dengan hasil NIHIL dan akan dilanjutkan esok hari sesuai Renops Harian.'
  );

  // Toggle Penutupan Operasi
  const [isPenutupan, setIsPenutupan] = useState<boolean>(false);
  const [waktuDebrief, setWaktuDebrief] = useState<string>(getLocalDateTimeString(now));

  // Victim status update copy
  const [victimStatuses, setVictimStatuses] = useState<
    { id_korban: number; nama_korban: string; status_korban: StatusKorban }[]
  >(
    (operasi.korban || []).map((k) => ({
      id_korban: k.id_korban,
      nama_korban: k.nama_korban,
      status_korban: k.status_korban,
    }))
  );

  // Inherited Unsur & Alut
  const [unsurList, setUnsurList] = useState<DetailUnsur[]>(
    operasi.detail_unsur ? JSON.parse(JSON.stringify(operasi.detail_unsur)) : []
  );
  const [alutList, setAlutList] = useState<DetailAlut[]>(
    operasi.detail_alut ? JSON.parse(JSON.stringify(operasi.detail_alut)) : []
  );

  // AI draft states
  const [isAiLoading, setIsAiLoading] = useState<boolean>(false);
  const [tindakanInput, setTindakanInput] = useState<string>('');
  const [hasilInput, setHasilInput] = useState<string>('Nihil');
  const [kendalaInput, setKendalaInput] = useState<string>('');
  const [showAiBox, setShowAiBox] = useState<boolean>(false);

  const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
  const [copiedPreview, setCopiedPreview] = useState<boolean>(false);
  const [errorMsg, setErrorMsg] = useState<string>('');
  const [confirmCloseChecked, setConfirmCloseChecked] = useState<boolean>(false);

  // Live WA text preview
  const liveWaText = useMemo(() => {
    const dummyLaporan: Partial<LaporanHarian> = {
      hari_ke: hariKe,
      jenis_laporan: isPenutupan ? 'PENUTUPAN' : 'PERKEMBANGAN',
      isi_laporan: isiLaporan,
      waktu_debrief: isPenutupan ? new Date(waktuDebrief).toISOString() : undefined,
      waktu_laporan: new Date(waktuLaporan).toISOString(),
    };

    // Apply updated victim statuses to preview
    const previewKorbanList: Korban[] = (operasi.korban || []).map((k) => {
      const found = victimStatuses.find((v) => v.id_korban === k.id_korban);
      return {
        ...k,
        status_korban: found ? found.status_korban : k.status_korban,
      };
    });

    return generateLapbangWA(
      operasi,
      dummyLaporan,
      previewKorbanList,
      unsurList,
      alutList,
      isPenutupan
    );
  }, [
    operasi,
    hariKe,
    isPenutupan,
    isiLaporan,
    waktuDebrief,
    waktuLaporan,
    victimStatuses,
    unsurList,
    alutList,
  ]);

  // AI draft Sitrep
  const handleGenerateAiSitrep = async () => {
    setIsAiLoading(true);
    try {
      const res = await fetch('/api/ai/draft-sitrep', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          hariKe,
          tindakanHariIni: tindakanInput || 'Penyisiran darat/air sesuai Renops',
          hasil: hasilInput,
          kendala: kendalaInput,
          namaOperasi: operasi.nama_operasi,
        }),
      });
      const data = await res.json();
      if (data.success && data.text) {
        setIsLaporan(data.text);
        setShowAiBox(false);
        showToast('Narasi Lapbang berhasil digenerate AI!', 'success');
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsAiLoading(false);
    }
  };

  const handleCopyPreview = async () => {
    try {
      await navigator.clipboard.writeText(liveWaText);
      setCopiedPreview(true);
      showToast('Naskah format WA Lapbang berhasil disalin!', 'success');
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

    if (!isiLaporan.trim()) {
      setErrorMsg('Narasi hasil pencarian hari ini wajib diisi!');
      showErrorAlert('Validasi Gagal', 'Narasi hasil pencarian hari ini wajib diisi.');
      return;
    }

    if (isPenutupan && !confirmCloseChecked) {
      setErrorMsg('Mohon centang persetujuan penutupan Operasi SAR terlebih dahulu.');
      showWarningAlert('Persetujuan Diperlukan', 'Mohon centang persetujuan penutupan Operasi SAR.');
      return;
    }

    setIsSubmitting(true);
    try {
      const laporanPayload = {
        hari_ke: hariKe,
        jenis_laporan: isPenutupan ? 'PENUTUPAN' : 'PERKEMBANGAN',
        isi_laporan: isiLaporan.trim(),
        korban_selamat_update: victimStatuses.filter((v) => v.status_korban === 'S').length,
        korban_meninggal_update: victimStatuses.filter((v) => v.status_korban === 'MD').length,
        korban_hilang_update: victimStatuses.filter((v) => v.status_korban === 'DP' || v.status_korban === 'H').length,
        waktu_debrief: isPenutupan ? new Date(waktuDebrief).toISOString() : undefined,
        unsur_text: '',
        alut_text: '',
        full_text_wa: liveWaText,
        waktu_laporan: new Date(waktuLaporan).toISOString(),
      };

      const res = await fetch(`/api/operasi/${operasi.id_operasi}/laporan`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          laporan: laporanPayload,
          updated_korban: isPenutupan ? victimStatuses : undefined,
          detail_unsur: unsurList,
          detail_alut: alutList,
        }),
      });

      const result = await res.json();
      if (!res.ok || !result.success) {
        throw new Error(result.error || 'Gagal menyimpan Laporan Perkembangan');
      }

      await showSuccessAlert(
        isPenutupan ? 'Operasi SAR Ditutup & Lapbang Tersimpan!' : 'Laporan Perkembangan Berhasil Disimpan!',
        `Laporan Perkembangan Harian (${hariKe}) berhasil disimpan.`
      );

      onSuccess(result.data);
      onClose();
    } catch (err: any) {
      const msg = err.message || 'Gagal menyimpan laporan';
      setErrorMsg(msg);
      showErrorAlert('Gagal Menyimpan Lapbang', msg);
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
                <span className={`px-2 py-0.5 text-[10px] font-mono font-bold rounded-md border ${
                  isPenutupan
                    ? 'bg-red-500/10 text-red-500 border-red-500/20'
                    : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'
                }`}>
                  {isPenutupan ? 'LAPBANG PENUTUPAN OPS' : `LAPBANG HARIAN • ${hariKe}`}
                </span>
                <span className="text-xs text-slate-400 font-mono hidden sm:inline">
                  {formatTW(new Date(waktuLaporan).toISOString())}
                </span>
              </div>
              <h2 className="text-base sm:text-lg font-black tracking-tight leading-tight truncate max-w-md sm:max-w-xl">
                {isPenutupan ? 'Laporan Perkembangan & Terminasi Operasi SAR' : 'Laporan Perkembangan (SITREP / Lapbang)'}
              </h2>
            </div>
          </div>

          {/* Top Bar Action Buttons */}
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
              <span>{copiedPreview ? 'Tersalin' : 'Salin WA'}</span>
            </button>

            <button
              type="button"
              onClick={() => handleSubmit()}
              disabled={isSubmitting}
              className={`inline-flex items-center gap-2 px-4 sm:px-5 py-2 text-white text-xs font-bold rounded-xl shadow-lg transition-all disabled:opacity-50 ${
                isPenutupan
                  ? 'bg-red-600 hover:bg-red-500 shadow-red-600/30'
                  : 'bg-orange-600 hover:bg-orange-500 shadow-orange-600/30'
              }`}
            >
              {isPenutupan ? <Lock className="w-4 h-4" /> : <Save className="w-4 h-4" />}
              <span>{isSubmitting ? 'Menyimpan...' : isPenutupan ? 'Tutup Operasi & Simpan' : 'Simpan Lapbang'}</span>
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
            {/* CARD 1: Waktu & Tanggal Laporan */}
            <div className={`p-5 rounded-2xl border transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                <div className="flex items-center gap-2.5">
                  <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 border border-orange-500/20">
                    <Clock className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold">1. Waktu Pengiriman Laporan Perkembangan</h3>
                    <p className="text-xs text-slate-400">Waktu rekapitulasi harian situasi lapangan</p>
                  </div>
                </div>

                <span className="px-2.5 py-1 rounded-lg text-xs font-bold font-mono bg-orange-500/10 text-orange-500 border border-orange-500/20">
                  {hariKe}
                </span>
              </div>

              <DateTimeInput24H
                label="Tanggal & Jam Laporan (WITA)"
                value={waktuLaporan}
                onChange={setWaktuLaporan}
                helpText="Jam pengiriman berita harian ke Senkom / Kantor Pusat Basarnas"
              />
            </div>

            {/* CARD 2: Update Status Korban */}
            <div className={`p-5 rounded-2xl border transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4">
                <div className="flex items-center gap-2.5">
                  <div className="p-1.5 rounded-lg bg-amber-500/10 text-amber-500 border border-amber-500/20">
                    <Users className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold">2. Pembaruan Status Korban ({victimStatuses.length} Orang)</h3>
                    <p className="text-xs text-slate-400">Perbarui kondisi korban apabila ditemukan hari ini</p>
                  </div>
                </div>
              </div>

              {victimStatuses.length === 0 ? (
                <p className="text-xs text-slate-400 italic">Data korban awal tidak tercatat dalam inisiasi.</p>
              ) : (
                <div className="space-y-3">
                  {victimStatuses.map((v, idx) => (
                    <div
                      key={v.id_korban}
                      className={`p-3 rounded-xl border flex flex-col sm:flex-row sm:items-center justify-between gap-3 ${
                        isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-50 border-slate-200'
                      }`}
                    >
                      <div className="flex items-center gap-2">
                        <span className="w-6 h-6 rounded-lg bg-slate-800 text-slate-300 font-mono text-xs flex items-center justify-center">
                          {idx + 1}
                        </span>
                        <div>
                          <div className="font-bold text-xs">{v.nama_korban}</div>
                          <div className="text-[10px] text-slate-400 font-mono">ID: #{v.id_korban}</div>
                        </div>
                      </div>

                      <div className="flex flex-wrap items-center gap-1.5">
                        {[
                          { value: 'DP' as StatusKorban, label: 'Dalam Pencarian (DP)', activeClass: 'bg-amber-600 text-white border-amber-500 shadow-sm' },
                          { value: 'S' as StatusKorban, label: 'Selamat (S)', activeClass: 'bg-emerald-600 text-white border-emerald-500 shadow-sm' },
                          { value: 'MD' as StatusKorban, label: 'Meninggal Dunia (MD)', activeClass: 'bg-red-600 text-white border-red-500 shadow-sm' },
                          { value: 'H' as StatusKorban, label: 'Hilang (H)', activeClass: 'bg-purple-600 text-white border-purple-500 shadow-sm' },
                        ].map((opt) => (
                          <button
                            key={opt.value}
                            type="button"
                            onClick={() => {
                              const updated = [...victimStatuses];
                              updated[idx].status_korban = opt.value;
                              setVictimStatuses(updated);
                            }}
                            className={`px-2.5 py-1 rounded-lg text-xs font-semibold border transition-all ${
                              v.status_korban === opt.value
                                ? opt.activeClass
                                : isDarkMode
                                ? 'bg-slate-900 border-slate-700 text-slate-400 hover:text-slate-200'
                                : 'bg-white border-slate-300 text-slate-600 hover:text-slate-900'
                            }`}
                          >
                            {opt.label}
                          </button>
                        ))}
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>

            {/* CARD 3: Narasi Hasil Pencarian & Tindakan Hari Ini */}
            <div className={`p-5 rounded-2xl border transition-colors ${
              isDarkMode ? 'bg-slate-900/90 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800 mb-4 gap-2">
                <div className="flex items-center gap-2.5">
                  <div className="p-1.5 rounded-lg bg-orange-500/10 text-orange-500 border border-orange-500/20">
                    <FileText className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className="text-sm font-bold">3. Hasil Pencarian & Evaluasi Lapangan</h3>
                    <p className="text-xs text-slate-400">Deskripsi tindakan tim SAR gabungan, hasil temuan, dan kendala</p>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={() => setShowAiBox(!showAiBox)}
                  className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border text-xs font-bold transition-colors ${
                    isDarkMode
                      ? 'bg-purple-500/10 border-purple-500/30 text-purple-400 hover:bg-purple-500/20'
                      : 'bg-purple-50 border-purple-200 text-purple-700 hover:bg-purple-100'
                  }`}
                >
                  <Sparkles className="w-3.5 h-3.5 text-purple-500" />
                  <span>{showAiBox ? 'Tutup Asisten AI' : 'Bantu Narasi AI'}</span>
                </button>
              </div>

              {/* AI Generator Helper Box */}
              {showAiBox && (
                <div className={`p-4 rounded-xl border mb-4 space-y-3 animate-in fade-in slide-in-from-top-2 duration-150 ${
                  isDarkMode ? 'bg-purple-950/20 border-purple-500/30' : 'bg-purple-50 border-purple-200'
                }`}>
                  <div className="text-xs font-bold text-purple-500 flex items-center gap-1.5">
                    <Sparkles className="w-3.5 h-3.5" />
                    <span>Asisten AI Pembuat Narasi Lapbang Otomatis</span>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                      <label className="block font-semibold mb-1 text-slate-400">Hasil Pencarian</label>
                      <select
                        value={hasilInput}
                        onChange={(e) => setHasilInput(e.target.value)}
                        className={`w-full px-2.5 py-1.5 rounded-lg border ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      >
                        <option value="Nihil">Nihil (Belum Ditemukan)</option>
                        <option value="Korban Ditemukan Selamat">Korban Ditemukan Selamat</option>
                        <option value="Korban Ditemukan Meninggal Dunia (MD)">Korban Ditemukan Meninggal Dunia</option>
                      </select>
                    </div>

                    <div className="sm:col-span-2">
                      <label className="block font-semibold mb-1 text-slate-400">Kendala Lapangan (Jika ada)</label>
                      <input
                        type="text"
                        placeholder="Contoh: Gelombang tinggi 2m, jarak pandang terbatas..."
                        value={kendalaInput}
                        onChange={(e) => setKendalaInput(e.target.value)}
                        className={`w-full px-2.5 py-1.5 rounded-lg border ${
                          isDarkMode ? 'bg-slate-900 border-slate-700 text-white' : 'bg-white border-slate-300 text-slate-900'
                        }`}
                      />
                    </div>
                  </div>

                  <div className="flex justify-end">
                    <button
                      type="button"
                      onClick={handleGenerateAiSitrep}
                      disabled={isAiLoading}
                      className="px-4 py-1.5 bg-purple-600 hover:bg-purple-500 text-white rounded-lg text-xs font-bold shadow-md shadow-purple-600/20 disabled:opacity-50"
                    >
                      {isAiLoading ? 'Menyusun Narasi...' : 'Terapkan Narasi ke Form'}
                    </button>
                  </div>
                </div>
              )}

              <div className="space-y-2">
                <textarea
                  rows={4}
                  required
                  value={isiLaporan}
                  onChange={(e) => setIsLaporan(e.target.value)}
                  className={`w-full px-3 py-2 rounded-xl text-xs border leading-relaxed focus:outline-none focus:ring-2 focus:ring-orange-500 ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-700 text-white'
                      : 'bg-slate-50 border-slate-300 text-slate-900'
                  }`}
                  placeholder="Tuliskan perkembangan hasil operasi SAR hari ini..."
                />

                <div className="flex flex-wrap gap-1.5 pt-1">
                  {[
                    'Hasil pencarian hari ini masih NIHIL.',
                    'Pencarian dihentikan sementara dan dilanjutkan esok hari pkl 07.00 WITA.',
                    'Korban berhasil dievakuasi ke RS Bhayangkara.',
                    'Cuaca sore hari hujan deras disertai angin kencang.',
                  ].map((snip) => (
                    <button
                      key={snip}
                      type="button"
                      onClick={() => setIsLaporan((prev) => (prev ? `${prev} ${snip}` : snip))}
                      className={`text-[10px] px-2 py-1 rounded-lg border transition-colors ${
                        isDarkMode
                          ? 'border-slate-800 bg-slate-800/40 text-slate-400 hover:text-white hover:bg-slate-800'
                          : 'border-slate-200 bg-slate-100 text-slate-600 hover:text-slate-900'
                      }`}
                    >
                      + {snip}
                    </button>
                  ))}
                </div>
              </div>
            </div>

            {/* CARD 4: Unsur SAR Terlibat */}
            <UnsurSarEditor
              title="4. Unsur SAR Terlibat Hari Ini"
              description="Daftar potensi SAR gabungan dan jumlah personel yang bertugas di lapangan"
              unsurList={unsurList}
              onChangeUnsur={setUnsurList}
            />

            {/* CARD 5: Alut & Palsar yang Dikerahkan */}
            <AlutPalsarEditor
              title="5. Alut & Palsar yang Digunakan Hari Ini"
              description="Daftar armada perahu/kapal, kendaraan, dan peralatan khusus yang dikerahkan"
              alutList={alutList}
              masterAlut={masterAlut}
              onChangeAlut={setAlutList}
            />

            {/* CARD 6: Opsi Terminasi / Penutupan Operasi */}
            <div className={`p-5 rounded-2xl border transition-all ${
              isPenutupan
                ? isDarkMode
                  ? 'bg-red-950/20 border-red-500/40'
                  : 'bg-red-50 border-red-200 shadow-sm'
                : isDarkMode
                ? 'bg-slate-900/90 border-slate-800'
                : 'bg-white border-slate-200 shadow-sm'
            }`}>
              <div className="flex items-center justify-between">
                <div className="flex items-center gap-2.5">
                  <div className={`p-1.5 rounded-lg border ${
                    isPenutupan
                      ? 'bg-red-500/20 border-red-500/40 text-red-500'
                      : 'bg-slate-800 border-slate-700 text-slate-400'
                  }`}>
                    <Lock className="w-4 h-4" />
                  </div>
                  <div>
                    <h3 className={`text-sm font-bold ${isPenutupan ? 'text-red-500' : ''}`}>
                      Terminasi / Penutupan Operasi SAR (Ops Closed)
                    </h3>
                    <p className="text-xs text-slate-400">
                      Aktifkan jika seluruh korban telah ditemukan atau operasi resmi dihentikan
                    </p>
                  </div>
                </div>

                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={isPenutupan}
                    onChange={(e) => setIsPenutupan(e.target.checked)}
                    className="sr-only peer"
                  />
                  <div className="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                </label>
              </div>

              {isPenutupan && (
                <div className="mt-4 pt-4 border-t border-red-200 dark:border-red-900/50 space-y-4 animate-in fade-in">
                  <DateTimeInput24H
                    label="Waktu Debriefing & Penutupan Resmi (WITA)"
                    value={waktuDebrief}
                    onChange={setWaktuDebrief}
                    helpText="Waktu upacara penutupan operasi dan pelepasan unsur SAR kembali ke kesatuan"
                  />

                  <div className="p-3 bg-red-500/10 border border-red-500/30 rounded-xl space-y-2">
                    <label className="flex items-start gap-2.5 cursor-pointer">
                      <input
                        type="checkbox"
                        checked={confirmCloseChecked}
                        onChange={(e) => setConfirmCloseChecked(e.target.checked)}
                        className="w-4 h-4 mt-0.5 rounded text-red-600 focus:ring-red-500 border-slate-700"
                      />
                      <span className="text-xs text-red-600 dark:text-red-400 font-semibold leading-tight">
                        Saya mengonfirmasi bahwa operasi SAR ini resmi DITUTUP atas persetujuan SMC, dan seluruh unsur SAR dikembalikan ke kesatuan masing-masing.
                      </span>
                    </label>
                  </div>
                </div>
              )}
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
                    <p className="text-[10px] text-emerald-100">
                      {isPenutupan ? 'Format Naskah Penutupan Operasi SAR' : 'Format SITREP Harian Korps Basarnas'}
                    </p>
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
                <span className="font-semibold text-emerald-600 dark:text-emerald-400">
                  {isPenutupan ? 'Status: Operasi Selesai' : 'Status: Siap Laporkan ke Senkom'}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
