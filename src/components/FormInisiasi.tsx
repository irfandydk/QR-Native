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
} from 'lucide-react';
import { MasterAlut, MasterTim, Operasi, Korban, DetailUnsur, DetailAlut } from '../types';
import { generateLaporanAwalWA } from '../utils/templates';
import { showSuccessAlert, showErrorAlert, showToast } from '../utils/alerts';
import { DateTimeInput24H } from './DateTimeInput24H';
import { UnsurSarEditor } from './UnsurSarEditor';
import { AlutPalsarEditor } from './AlutPalsarEditor';

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
  const isEditing = !!initialOperasi;

  // Form states
  const [namaOperasi, setNamaOperasi] = useState(
    initialOperasi?.nama_operasi || 'KMM 1 Orang Terjatuh dari Longboat di Perairan Wakatobi'
  );
  const [namaPelapor, setNamaPelapor] = useState(initialOperasi?.nama_pelapor || '');
  const [hubunganPelapor, setHubunganPelapor] = useState(initialOperasi?.hubungan_pelapor || 'Masyarakat');
  const [noTelpPelapor, setNoTelpPelapor] = useState(initialOperasi?.no_telp_pelapor || '');

  // Helper to format ISO for datetime-local
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
    initialOperasi?.detail_tim_berangkat?.map((t) => t.id_tim) || [1]
  );

  // Location & Coordinates
  const [lokasiKejadian, setLokasiKejadian] = useState(
    initialOperasi?.lokasi_kejadian || ''
  );
  const [koordinat, setKoordinat] = useState(
    initialOperasi?.koordinat || '03° 58\' 30" S - 122° 35\' 12" E'
  );
  const [jarakAngka, setJarakAngka] = useState(
    initialOperasi?.jarak?.split(' ')[0] || '10'
  );
  const [jarakSatuan, setJarakSatuan] = useState(
    initialOperasi?.jarak?.split(' ')[1] || 'Nm'
  );
  const [etaLokasi, setEtaLokasi] = useState(
    initialOperasi?.eta_lokasi || '1 Jam 30 Menit'
  );

  // DMS helper mode
  const [showDmsHelper, setShowDmsHelper] = useState(false);
  const [latDeg, setLatDeg] = useState('03');
  const [latMin, setLatMin] = useState('58');
  const [latSec, setLatSec] = useState('30');
  const [latDir, setLatDir] = useState('S');

  const [lonDeg, setLonDeg] = useState('122');
  const [lonMin, setLonMin] = useState('35');
  const [lonSec, setLonSec] = useState('12');
  const [lonDir, setLonDir] = useState('E');

  // Kronologi & AI
  const [kronologi, setKronologi] = useState(initialOperasi?.kronologi || '');
  const [isAiDrafting, setIsAiDrafting] = useState(false);
  const [aiDraftPrompt, setAiDraftPrompt] = useState('');
  const [showAiModal, setShowAiModal] = useState(false);

  // Korban
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
            umur: '35 Tahun',
            jk: 'Laki-laki',
            alamat: '',
            status_korban: 'DP',
          },
        ]
  );

  // Unsur
  const [unsurList, setUnsurList] = useState<Omit<DetailUnsur, 'id_detail_unsur' | 'id_operasi'>[]>(
    initialOperasi?.detail_unsur && initialOperasi.detail_unsur.length > 0
      ? initialOperasi.detail_unsur.map((u) => ({
          nama_unsur: u.nama_unsur,
          jumlah_personil: u.jumlah_personil,
        }))
      : [
          { nama_unsur: 'Tim Rescue Kansar Kendari', jumlah_personil: 7 },
          { nama_unsur: 'Polairud', jumlah_personil: 3 },
          { nama_unsur: 'Masyarakat & Nelayan Setempat', jumlah_personil: 10 },
        ]
  );

  // Alut
  const [alutList, setAlutList] = useState<Omit<DetailAlut, 'id_detail_alut' | 'id_operasi'>[]>(
    initialOperasi?.detail_alut && initialOperasi.detail_alut.length > 0
      ? initialOperasi.detail_alut.map((a) => ({
          id_alut: a.id_alut,
          nama_alut: a.nama_alut,
          satuan: a.satuan,
          jumlah_alut: a.jumlah_alut,
        }))
      : [
          { id_alut: 1, nama_alut: 'Rescue Car', satuan: 'Unit', jumlah_alut: 1 },
          { id_alut: 6, nama_alut: 'Rubber Boat', satuan: 'Unit', jumlah_alut: 1 },
          { id_alut: 8, nama_alut: 'Aqua Eye', satuan: 'Set', jumlah_alut: 1 },
        ]
  );

  // Weather & Constraints
  const [cuaca, setCuaca] = useState(initialOperasi?.cuaca || 'Cerah Berawan');
  const [gelombang, setGelombang] = useState(initialOperasi?.gelombang || '0.5 - 1.25 M');
  const [angin, setAngin] = useState(
    initialOperasi?.angin || 'Barat Daya - Barat Laut, 5 - 15 Knots'
  );
  const [faktorPenghambat, setFaktorPenghambat] = useState(
    initialOperasi?.faktor_penghambat || 'Nihil'
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
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsAiDrafting(false);
    }
  };

  // Add / Remove Victim
  const handleAddKorban = () => {
    setKorbanList((prev) => [
      ...prev,
      {
        nama_korban: '',
        umur: '',
        jk: 'Laki-laki',
        alamat: '',
        status_korban: 'DP',
      },
    ]);
  };

  const handleRemoveKorban = (index: number) => {
    setKorbanList((prev) => prev.filter((_, i) => i !== index));
  };

  // Add / Remove Unsur
  const handleAddUnsur = () => {
    setUnsurList((prev) => [...prev, { nama_unsur: '', jumlah_personil: 5 }]);
  };

  const handleRemoveUnsur = (index: number) => {
    setUnsurList((prev) => prev.filter((_, i) => i !== index));
  };

  // Add / Remove Alut
  const handleAddAlut = () => {
    const first = masterAlut[0];
    setAlutList((prev) => [
      ...prev,
      {
        id_alut: first?.id_alut,
        nama_alut: first?.nama_alut || 'Rubber Boat',
        satuan: first?.satuan || 'Unit',
        jumlah_alut: 1,
      },
    ]);
  };

  const handleRemoveAlut = (index: number) => {
    setAlutList((prev) => prev.filter((_, i) => i !== index));
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

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
      {/* Top Bar Navigation */}
      <div className="flex items-center justify-between">
        <button
          onClick={onBack}
          className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-medium border border-slate-700 transition-colors"
        >
          <ArrowLeft className="w-4 h-4" />
          <span>Kembali ke Dashboard</span>
        </button>

        <div className="flex items-center gap-2">
          <span className="text-xs text-slate-400">
            {isEditing ? 'Mode Edit Data Operasi' : 'Penyusunan Laporan Awal (FILE AKTIF)'}
          </span>
        </div>
      </div>

      {errorMsg && (
        <div className="p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-xs font-semibold flex items-center justify-between shadow-lg">
          <span>⚠️ {errorMsg}</span>
          <button
            type="button"
            onClick={() => setErrorMsg('')}
            className="text-red-400 hover:text-white p-1 rounded transition-colors"
          >
            ✕
          </button>
        </div>
      )}

      {/* Main Grid: Form Left, WhatsApp Live Preview Right */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Form Container */}
        <form onSubmit={handleSubmit} className="lg:col-span-7 space-y-6">
          {/* Card 1: Nama & Sumber Info */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div className="flex items-center gap-2.5 pb-3 border-b border-slate-800">
              <div className="p-1.5 bg-orange-500/10 text-orange-400 rounded-md border border-orange-500/20">
                <FileText className="w-4 h-4" />
              </div>
              <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                1. Identitas Operasi & Sumber Informasi
              </h2>
            </div>

            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Nama / Judul Operasi SAR <span className="text-red-400">*</span>
              </label>
              <input
                type="text"
                required
                value={namaOperasi}
                onChange={(e) => setNamaOperasi(e.target.value)}
                placeholder="Contoh: KMM 1 Orang Terjatuh dari Longboat di Perairan Wakatobi"
                className="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-lg text-xs sm:text-sm text-white focus:outline-none focus:border-orange-500"
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label className="block text-xs text-slate-400 mb-1">Nama Pelapor</label>
                <input
                  type="text"
                  value={namaPelapor}
                  onChange={(e) => setNamaPelapor(e.target.value)}
                  placeholder="Bpk. La Ode..."
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                />
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">Hubungan / Instansi</label>
                <input
                  type="text"
                  value={hubunganPelapor}
                  onChange={(e) => setHubunganPelapor(e.target.value)}
                  placeholder="Keluarga / Polairud / Kades..."
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                />
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">No. HP Pelapor</label>
                <input
                  type="text"
                  value={noTelpPelapor}
                  onChange={(e) => setNoTelpPelapor(e.target.value)}
                  placeholder="0812-xxxx-xxxx"
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <DateTimeInput24H
                id="input-waktu-kejadian"
                label="Waktu Kejadian"
                value={waktuKejadian}
                onChange={(val) => setWaktuKejadian(val)}
                required
                helpText="Waktu insiden berlangsung (WITA / UTC+8)"
              />

              <DateTimeInput24H
                id="input-waktu-lapor"
                label="Waktu Lapor"
                value={waktuLapor}
                onChange={(val) => setWaktuLapor(val)}
                required
                helpText="Waktu info diterima Kansar (WITA / UTC+8)"
              />
            </div>
          </div>

          {/* Card 2: Pelaksanaan SAR & Keberangkatan */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div className="flex items-center gap-2.5 pb-3 border-b border-slate-800">
              <div className="p-1.5 bg-orange-500/10 text-orange-400 rounded-md border border-orange-500/20">
                <Navigation className="w-4 h-4" />
              </div>
              <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                2. Pelaksanaan SAR & Tim Berangkat
              </h2>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <DateTimeInput24H
                id="input-waktu-berangkat"
                label="Waktu Tim Berangkat"
                value={waktuBerangkat}
                onChange={(val) => setWaktuBerangkat(val)}
                required
                helpText="Waktu tim rescue bergerak (WITA / UTC+8)"
              />

              <DateTimeInput24H
                id="input-eta-tiba"
                label="Estimasi Tiba di LKP (ETA)"
                value={etaTiba}
                onChange={(val) => setEtaTiba(val)}
                required
                helpText="Perkiraan waktu tiba di lokasi kejadian"
              />
            </div>

            {/* Tim Berangkat selector */}
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1.5">
                Pilih Tim / Unit Siaga SAR yang Berangkat (Bisa Lebih dari 1)
              </label>
              <div className="flex flex-wrap gap-2">
                {masterTim.map((tim) => {
                  const isSelected = selectedTimIds.includes(tim.id_tim);
                  return (
                    <button
                      type="button"
                      key={tim.id_tim}
                      onClick={() => toggleTeam(tim.id_tim)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-medium border transition-all ${
                        isSelected
                          ? 'bg-orange-600/30 border-orange-500 text-orange-300 shadow-sm'
                          : 'bg-slate-950 border-slate-800 text-slate-400 hover:border-slate-700'
                      }`}
                    >
                      {isSelected ? '✓ ' : '+ '}
                      {tim.nama_tim}
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <div>
                <label className="block text-xs text-slate-400 mb-1">Jarak Tempuh</label>
                <div className="flex gap-2">
                  <input
                    type="number"
                    step="0.1"
                    value={jarakAngka}
                    onChange={(e) => setJarakAngka(e.target.value)}
                    placeholder="15"
                    className="flex-1 px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                  />
                  <select
                    value={jarakSatuan}
                    onChange={(e) => setJarakSatuan(e.target.value)}
                    className="px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                  >
                    <option value="Nm">Nm (Nautical Mile)</option>
                    <option value="Km">Km (Kilometer)</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">Estimasi Waktu Tempuh</label>
                <input
                  type="text"
                  value={etaLokasi}
                  onChange={(e) => setEtaLokasi(e.target.value)}
                  placeholder="Contoh: 1 Jam 30 Menit"
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                />
              </div>
            </div>
          </div>

          {/* Card 3: Lokasi & Titik Koordinat */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2.5">
                <div className="p-1.5 bg-orange-500/10 text-orange-400 rounded-md border border-orange-500/20">
                  <Compass className="w-4 h-4" />
                </div>
                <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                  3. Lokasi Kejadian & Titik Koordinat (LKP)
                </h2>
              </div>
              <button
                type="button"
                onClick={() => setShowDmsHelper(!showDmsHelper)}
                className="text-xs font-medium text-orange-400 hover:text-orange-300 underline"
              >
                {showDmsHelper ? 'Tutup Builder DMS' : '📐 Generator Derajat/Menit/Detik'}
              </button>
            </div>

            <div>
              <label className="block text-xs text-slate-400 mb-1">Deskripsi Lokasi / Perairan</label>
              <input
                type="text"
                required
                value={lokasiKejadian}
                onChange={(e) => setLokasiKejadian(e.target.value)}
                placeholder="Contoh: Perairan Karang Kapota, Kab. Wakatobi, Sulawesi Tenggara"
                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
              />
            </div>

            {showDmsHelper && (
              <div className="p-4 bg-slate-950 border border-orange-500/30 rounded-xl space-y-3">
                <div className="text-xs font-semibold text-orange-400">
                  Konverter Koordinat Format Basarnas (DMS):
                </div>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                  {/* Lintang */}
                  <div className="space-y-1">
                    <span className="text-slate-400 font-medium">Lintang (Latitude):</span>
                    <div className="flex items-center gap-1.5">
                      <input
                        type="text"
                        value={latDeg}
                        onChange={(e) => setLatDeg(e.target.value)}
                        className="w-12 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white"
                        placeholder="03"
                      />
                      <span>°</span>
                      <input
                        type="text"
                        value={latMin}
                        onChange={(e) => setLatMin(e.target.value)}
                        className="w-12 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white"
                        placeholder="58"
                      />
                      <span>'</span>
                      <input
                        type="text"
                        value={latSec}
                        onChange={(e) => setLatSec(e.target.value)}
                        className="w-12 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white"
                        placeholder="30"
                      />
                      <span>"</span>
                      <select
                        value={latDir}
                        onChange={(e) => setLatDir(e.target.value)}
                        className="px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white"
                      >
                        <option value="S">S (LS)</option>
                        <option value="N">N (LU)</option>
                      </select>
                    </div>
                  </div>

                  {/* Bujur */}
                  <div className="space-y-1">
                    <span className="text-slate-400 font-medium">Bujur (Longitude):</span>
                    <div className="flex items-center gap-1.5">
                      <input
                        type="text"
                        value={lonDeg}
                        onChange={(e) => setLonDeg(e.target.value)}
                        className="w-14 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white"
                        placeholder="122"
                      />
                      <span>°</span>
                      <input
                        type="text"
                        value={lonMin}
                        onChange={(e) => setLonMin(e.target.value)}
                        className="w-12 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white"
                        placeholder="35"
                      />
                      <span>'</span>
                      <input
                        type="text"
                        value={lonSec}
                        onChange={(e) => setLonSec(e.target.value)}
                        className="w-12 px-2 py-1 bg-slate-900 border border-slate-700 rounded text-center text-white"
                        placeholder="12"
                      />
                      <span>"</span>
                      <select
                        value={lonDir}
                        onChange={(e) => setLonDir(e.target.value)}
                        className="px-2 py-1 bg-slate-900 border border-slate-700 rounded text-white"
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
                    className="px-3 py-1 bg-orange-600 hover:bg-orange-500 text-white rounded text-xs font-medium"
                  >
                    Terapkan ke Koordinat
                  </button>
                </div>
              </div>
            )}

            <div>
              <label className="block text-xs text-slate-400 mb-1">
                Format Teks Koordinat Resmi
              </label>
              <input
                type="text"
                required
                value={koordinat}
                onChange={(e) => setKoordinat(e.target.value)}
                placeholder={'03° 58\' 30" S - 122° 35\' 12" E'}
                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs font-mono text-emerald-400 focus:outline-none focus:border-orange-500"
              />
            </div>
          </div>

          {/* Card 4: Kronologi Kejadian */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2.5">
                <div className="p-1.5 bg-orange-500/10 text-orange-400 rounded-md border border-orange-500/20">
                  <FileText className="w-4 h-4" />
                </div>
                <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                  4. Kronologi Kejadian
                </h2>
              </div>

              <button
                type="button"
                onClick={() => setShowAiModal(true)}
                className="inline-flex items-center gap-1.5 px-3 py-1 bg-gradient-to-r from-purple-600/30 to-indigo-600/30 hover:from-purple-600/50 hover:to-indigo-600/50 border border-purple-500/40 text-purple-300 rounded-lg text-xs font-medium transition-all"
              >
                <Sparkles className="w-3.5 h-3.5 text-purple-400" />
                <span>✨ Asisten AI Kronologi</span>
              </button>
            </div>

            <textarea
              rows={4}
              required
              value={kronologi}
              onChange={(e) => setKronologi(e.target.value)}
              placeholder="Ceritakan urutan kejadian dari saat korban berangkat, saat ditemukan tanda-tanda, hingga laporan masuk ke Kantor SAR..."
              className="w-full p-3 bg-slate-950 border border-slate-700 rounded-lg text-xs sm:text-sm text-slate-200 focus:outline-none focus:border-orange-500 leading-relaxed"
            />
          </div>

          {/* Card 5: Data Korban Dinamis */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-800">
              <div className="flex items-center gap-2.5">
                <div className="p-1.5 bg-orange-500/10 text-orange-400 rounded-md border border-orange-500/20">
                  <Users className="w-4 h-4" />
                </div>
                <div>
                  <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                    5. Data Korban ({korbanList.length} Orang)
                  </h2>
                  <p className="text-[11px] text-slate-400">POB otomatis terkalkulasi</p>
                </div>
              </div>

              <button
                type="button"
                onClick={handleAddKorban}
                className="inline-flex items-center gap-1 px-3 py-1 bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg text-xs font-medium transition-colors"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>Tambah Korban</span>
              </button>
            </div>

            <div className="space-y-3">
              {korbanList.map((k, idx) => (
                <div
                  key={idx}
                  className="p-3.5 bg-slate-950 border border-slate-800 rounded-xl space-y-3 relative group"
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-orange-400">
                      Korban #{idx + 1}
                    </span>
                    {korbanList.length > 1 && (
                      <button
                        type="button"
                        onClick={() => handleRemoveKorban(idx)}
                        className="text-slate-500 hover:text-red-400 p-1 rounded transition-colors"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                    <div className="sm:col-span-2">
                      <label className="block text-[11px] text-slate-400 mb-1">Nama Lengkap</label>
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
                        className="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded text-white"
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] text-slate-400 mb-1">Umur</label>
                      <input
                        type="text"
                        value={k.umur}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].umur = e.target.value;
                          setKorbanList(updated);
                        }}
                        placeholder="35 Tahun"
                        className="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded text-white"
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] text-slate-400 mb-1">Jenis Kelamin</label>
                      <select
                        value={k.jk}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].jk = e.target.value;
                          setKorbanList(updated);
                        }}
                        className="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded text-white"
                      >
                        <option value="Laki-laki">Laki-laki</option>
                        <option value="Perempuan">Perempuan</option>
                      </select>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-4 gap-2.5 text-xs">
                    <div className="sm:col-span-3">
                      <label className="block text-[11px] text-slate-400 mb-1">Alamat Korban</label>
                      <input
                        type="text"
                        value={k.alamat}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].alamat = e.target.value;
                          setKorbanList(updated);
                        }}
                        placeholder="Alamat domisili korban..."
                        className="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded text-white"
                      />
                    </div>

                    <div>
                      <label className="block text-[11px] text-slate-400 mb-1">Status Awal</label>
                      <select
                        value={k.status_korban}
                        onChange={(e) => {
                          const updated = [...korbanList];
                          updated[idx].status_korban = e.target.value as any;
                          setKorbanList(updated);
                        }}
                        className="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded text-amber-400 font-semibold"
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

          {/* Card 6: Unsur SAR Terlibat */}
          <UnsurSarEditor
            unsurList={unsurList}
            onChangeUnsur={setUnsurList}
            masterTim={masterTim}
            title="6. Unsur SAR Terlibat"
            description="Daftar instansi potensi SAR dan jumlah personel gabungan yang dikerahkan"
          />

          {/* Card 7: Alut & Palsar Terlibat */}
          <AlutPalsarEditor
            alutList={alutList}
            onChangeAlut={setAlutList}
            masterAlut={masterAlut}
            title="7. Alut & Palsar Terlibat"
            description="Daftar armada kapal/perahu, kendaraan, dan peralatan SAR yang dikerahkan"
          />

          {/* Card 8: Cuaca & Faktor Penghambat */}
          <div className="bg-slate-900 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
            <div className="flex items-center gap-2.5 pb-3 border-b border-slate-800">
              <div className="p-1.5 bg-orange-500/10 text-orange-400 rounded-md border border-orange-500/20">
                <Wind className="w-4 h-4" />
              </div>
              <h2 className="text-sm font-bold text-white uppercase tracking-wider">
                8. Kondisi Cuaca & Faktor Penghambat
              </h2>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div>
                <label className="block text-xs text-slate-400 mb-1">Kondisi Cuaca (BMKG)</label>
                <input
                  type="text"
                  value={cuaca}
                  onChange={(e) => setCuaca(e.target.value)}
                  placeholder="Cerah Berawan / Hujan Sedang..."
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                />
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">Tinggi Gelombang</label>
                <input
                  type="text"
                  value={gelombang}
                  onChange={(e) => setGelombang(e.target.value)}
                  placeholder="0.5 - 1.25 M"
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                />
              </div>

              <div>
                <label className="block text-xs text-slate-400 mb-1">Kecepatan & Arah Angin</label>
                <input
                  type="text"
                  value={angin}
                  onChange={(e) => setAngin(e.target.value)}
                  placeholder="Timur - Tenggara, 5 - 15 Knots"
                  className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs text-slate-400 mb-1">Faktor Penghambat (Opsional)</label>
              <input
                type="text"
                value={faktorPenghambat}
                onChange={(e) => setFaktorPenghambat(e.target.value)}
                placeholder="Nihil / Arus deras / Gelombang tinggi..."
                className="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
              />
            </div>
          </div>

          {/* Submit Action */}
          <div className="flex items-center justify-between pt-2">
            <button
              type="button"
              onClick={onBack}
              className="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold transition-colors"
            >
              Batal
            </button>

            <button
              id="btn-simpan-operasi"
              type="submit"
              disabled={isSubmitting}
              className="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white rounded-xl text-xs sm:text-sm font-bold shadow-lg shadow-orange-600/30 transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50"
            >
              <Save className="w-4 h-4" />
              <span>{isSubmitting ? 'Menyimpan...' : isEditing ? 'Simpan Pembaruan Data' : 'Kunci & Terbitkan Laporan Awal'}</span>
            </button>
          </div>
        </form>

        {/* Live Preview Column Right */}
        <div className="lg:col-span-5 space-y-4">
          <div className="sticky top-24 bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden flex flex-col max-h-[calc(100vh-120px)]">
            <div className="flex items-center justify-between px-4 py-3 bg-slate-800/90 border-b border-slate-700">
              <div className="flex items-center gap-2">
                <span className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <h3 className="text-xs font-bold text-white uppercase tracking-wider">
                  Live Preview: FILE AKTIF (Laporan Awal)
                </h3>
              </div>

              <button
                type="button"
                onClick={handleCopyPreview}
                className="inline-flex items-center gap-1.5 px-3 py-1 bg-orange-600 hover:bg-orange-500 text-white rounded-md text-xs font-semibold transition-colors shadow-sm"
              >
                {copiedPreview ? <Check className="w-3.5 h-3.5" /> : <Copy className="w-3.5 h-3.5" />}
                <span>{copiedPreview ? 'Tersalin!' : 'Salin WA'}</span>
              </button>
            </div>

            <div className="p-4 flex-1 overflow-y-auto bg-slate-950/80">
              <pre className="font-mono text-[11px] text-slate-300 whitespace-pre-wrap leading-relaxed select-text font-medium">
                {liveWaText}
              </pre>
            </div>

            <div className="px-4 py-2 bg-slate-900 border-t border-slate-800 text-[11px] text-slate-400 flex items-center justify-between">
              <span>Format Resmi Basarnas Kendari</span>
              <span>{liveWaText.length} Karakter</span>
            </div>
          </div>
        </div>
      </div>

      {/* AI Assistant Modal for Kronologi */}
      {showAiModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-150">
          <div className="w-full max-w-lg bg-slate-900 border border-purple-500/40 rounded-2xl p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between pb-2 border-b border-slate-800">
              <div className="flex items-center gap-2 text-purple-400">
                <Sparkles className="w-5 h-5" />
                <h3 className="text-sm font-bold text-white">Poles Kronologi Kejadian dengan AI</h3>
              </div>
              <button
                type="button"
                onClick={() => setShowAiModal(false)}
                className="text-slate-400 hover:text-white"
              >
                ✕
              </button>
            </div>

            <p className="text-xs text-slate-300">
              Masukkan catatan kasar, waktu, atau poin kejadian. Asisten AI akan merangkumnya menjadi narasi kronologi baku sesuai standar dinas Basarnas.
            </p>

            <textarea
              rows={4}
              value={aiDraftPrompt}
              onChange={(e) => setAiDraftPrompt(e.target.value)}
              placeholder="Contoh catatan: Korban pergi memancing subuh tadi di wakatobi naik perahu kecil, jam 7 ditemukan perahu muter mesin nyala tapi orangnya gak ada..."
              className="w-full p-3 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-purple-500"
            />

            <div className="flex justify-end gap-2 pt-1">
              <button
                type="button"
                onClick={() => setShowAiModal(false)}
                className="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-xs"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={isAiDrafting}
                onClick={handleGenerateAiKronologi}
                className="inline-flex items-center gap-1.5 px-4 py-1.5 bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white rounded-lg text-xs font-semibold shadow-md shadow-purple-600/30"
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
