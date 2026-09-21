import React, { useState, useEffect } from 'react';
import {
  Radio,
  FileText,
  Ship,
  Plus,
  Trash2,
  Clock,
  Sparkles,
  ShieldCheck,
  Eye,
  Save,
  X,
  HelpCircle,
  Hash,
  Send,
  Building2,
  Anchor,
  Shield,
} from 'lucide-react';
import { BraSar, BraSection, KategoriBra, Operasi, Pejabat } from '../types';
import {
  formatMilitaryTW,
  getBulanTahunSuffix,
  getSectionCode,
  countBraWords,
  formatIndoDate,
  getDefaultBraTemplate,
  buildBraSectionsFromRenops,
  buildBraSectionsFromSitrep,
  buildBraSectionsFromPenutupan,
  buildBraSectionsFromLaporanAwal,
} from '../utils/braUtils';
import { OfficialBraDocument } from './OfficialBraDocument';
import { showSuccessAlert, showErrorAlert, showWarningAlert, showToast } from '../utils/alerts';
import { useTheme } from '../context/ThemeContext';

interface FormBraSarProps {
  initialKategori?: KategoriBra;
  operasiList?: Operasi[];
  preselectedOperasiId?: number | null;
  onSaveSuccess: (savedBra: BraSar) => void;
  onCancel: () => void;
}

export const FormBraSar: React.FC<FormBraSarProps> = ({
  initialKategori = 'MAPEL',
  operasiList = [],
  preselectedOperasiId = null,
  onSaveSuccess,
  onCancel,
}) => {
  const { isDarkMode, themePalette, accentColors } = useTheme();
  const [kategori, setKategori] = useState<KategoriBra>(initialKategori);
  const [selectedOperasiId, setSelectedOperasiId] = useState<number | null>(preselectedOperasiId);
  const [loadingNumber, setLoadingNumber] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [showPreview, setShowPreview] = useState<boolean>(false);

  // Form Fields
  const [noBra, setNoBra] = useState<string>('');
  const [nomorUrut, setNomorUrut] = useState<number>(191);
  const [satkerKode, setSatkerKode] = useState<string>('SAR-304');
  const [bulanTahun, setBulanTahun] = useState<string>(getBulanTahunSuffix());

  const [perihal, setPerihal] = useState<string>('');
  const [derajat, setDerajat] = useState<string>('SGR');
  const [dari, setDari] = useState<string>('KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI');
  const [aksi, setAksi] = useState<string>('');
  const [info, setInfo] = useState<string>('');
  const [sifat, setSifat] = useState<string>('SEGERA');
  const [menbra, setMenbra] = useState<string>('');

  const [tanggalWaktu, setTanggalWaktu] = useState<string>(formatIndoDate(new Date()));
  const [twu, setTwu] = useState<string>(formatMilitaryTW(new Date(), 'H'));
  const [twb, setTwb] = useState<string>(
    formatMilitaryTW(new Date(Date.now() - 15 * 60000), 'H')
  );

  // Dynamic sections: AAA, BBB, CCC, etc. (Requirement 1)
  const [sections, setSections] = useState<BraSection[]>([
    { kode: 'AAA', isi: '' },
    { kode: 'BBB', isi: '' },
    { kode: 'CCC', isi: '' },
  ]);

  // Pengirim / SMC
  const [pengirim, setPengirim] = useState<string>('KANTOR PENCARIAN DAN PERTOLONGAN KENDARI');
  const [namaSmc, setNamaSmc] = useState<string>('AMIRUDDIN AS, S.Sos.');
  const [pangkatSmc, setPangkatSmc] = useState<string>('PEMBINA (IV/a)');
  const [jabatanSmc, setJabatanSmc] = useState<string>('SMC');
  const [pejabatList, setPejabatList] = useState<Pejabat[]>([]);
  const [selectedPejabatId, setSelectedPejabatId] = useState<string>('');

  // Fetch pejabat list from App Settings
  useEffect(() => {
    fetch('/api/settings/pejabat')
      .then((r) => r.json())
      .then((d) => {
        if (d.success && Array.isArray(d.data)) {
          setPejabatList(d.data);
          const defaultSmc = d.data.find((p: Pejabat) => p.is_default_smc);
          if (defaultSmc) {
            setSelectedPejabatId(defaultSmc.id);
            setNamaSmc(defaultSmc.nama);
            setPangkatSmc(defaultSmc.pangkat || '');
            if (kategori === 'SAR_UMUM' || selectedOperasiId) {
              setJabatanSmc('SMC');
            } else {
              setJabatanSmc(defaultSmc.jabatan || 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI');
            }
          }
        }
      })
      .catch((err) => console.error('Failed to load pejabat list:', err));
  }, []);

  const handleSelectPejabat = (id: string) => {
    setSelectedPejabatId(id);
    const p = pejabatList.find((item) => item.id === id);
    if (!p) return;
    setNamaSmc(p.nama);
    setPangkatSmc(p.pangkat || '');
    // Requirement D3: Jabatan logic
    if (kategori === 'SAR_UMUM' || selectedOperasiId) {
      setJabatanSmc('SMC');
    } else {
      setJabatanSmc(p.jabatan || 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI');
    }
  };

  // Fetch next atomic sequence number from server
  const fetchNextNumber = async () => {
    try {
      setLoadingNumber(true);
      const res = await fetch(`/api/bra/next-number?satker=${satkerKode}`);
      const data = await res.json();
      if (data.success && data.data) {
        setNoBra(data.data.no_bra);
        setNomorUrut(data.data.nomor_urut);
        setBulanTahun(data.data.bulan_tahun);
      } else {
        // Fallback default
        const suffix = getBulanTahunSuffix();
        setNoBra(`191/${satkerKode}/${suffix}`);
        setNomorUrut(191);
        setBulanTahun(suffix);
      }
    } catch (err) {
      console.error('Failed to get next BRA number:', err);
      const suffix = getBulanTahunSuffix();
      setNoBra(`191/${satkerKode}/${suffix}`);
      setNomorUrut(191);
      setBulanTahun(suffix);
    } finally {
      setLoadingNumber(false);
    }
  };

  useEffect(() => {
    fetchNextNumber();
  }, [satkerKode]);

  // Populate or reset template based on Kategori & selected Operasi
  const applyTemplate = (cat: KategoriBra, opId: number | null) => {
    const selectedOp = operasiList.find((o) => o.id_operasi === opId) || null;
    const template = getDefaultBraTemplate(
      cat,
      noBra || `191/${satkerKode}/${bulanTahun}`,
      nomorUrut,
      satkerKode,
      bulanTahun,
      selectedOp
    );

    setDari(template.dari);
    setAksi(template.aksi);
    setInfo(template.info);
    setSifat(template.sifat || (cat === 'MAPEL' ? 'SEGERA' : ''));
    setMenbra(template.menbra || '');
    setPerihal(template.perihal || '');
    setSections(template.sections);
    setPengirim(template.pengirim);
    setNamaSmc(template.nama_smc);
    setPangkatSmc(template.pangkat_smc);
    // Requirement D3: Jabatan logic
    if (cat === 'SAR_UMUM' || opId) {
      setJabatanSmc('SMC');
    } else {
      setJabatanSmc('KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI');
    }
  };

  // Initial load
  useEffect(() => {
    applyTemplate(kategori, selectedOperasiId);
  }, [kategori]);

  const handleOperasiChange = (opIdStr: string) => {
    const opId = opIdStr ? parseInt(opIdStr, 10) : null;
    setSelectedOperasiId(opId);
    applyTemplate(kategori, opId);
  };

  // Muat rekomendasi otomatis dari Laporan Terkait operasi
  const handleLoadRecommendation = (type: 'RENOPS' | 'SITREP' | 'INISIASI' | 'PENUTUPAN', specificItem?: any) => {
    const selectedOp = operasiList.find((o) => o.id_operasi === selectedOperasiId);
    if (!selectedOp) {
      showWarningAlert('Pilih Operasi SAR', 'Silakan pilih Operasi SAR terlebih dahulu pada dropdown di atas.');
      return;
    }

    const now = new Date();
    const twuStr = formatMilitaryTW(now, 'H');
    const twbStr = formatMilitaryTW(new Date(now.getTime() - 15 * 60000), 'H');

    if (type === 'INISIASI') {
      const res = buildBraSectionsFromLaporanAwal(selectedOp, twbStr, twuStr);
      setSections(res.sections);
      setPerihal(res.perihal);
      setAksi(res.aksi);
      setInfo(res.info);
      setTwu(twuStr);
      setTwb(twbStr);
      showToast('Rekomendasi Berita SAR untuk Laporan Awal berhasil dimuat!', 'success');
    } else if (type === 'RENOPS') {
      const renopsList = [...(selectedOp.renops || [])].sort((a, b) => (b.id_renops || 0) - (a.id_renops || 0));
      if (renopsList.length === 0) {
        showWarningAlert('Data Belum Ada', 'Belum ada Rencana Operasi (Renops) yang dibuat untuk operasi SAR ini.');
        return;
      }
      const targetRenops = specificItem || renopsList[0];
      const res = buildBraSectionsFromRenops(selectedOp, targetRenops, twbStr, twuStr);
      setSections(res.sections);
      setPerihal(res.perihal);
      setAksi(res.aksi);
      setInfo(res.info);
      setTwu(twuStr);
      setTwb(twbStr);
      showToast(`Rekomendasi Rencana Operasi ${targetRenops.hari_ke || ''} berhasil dimuat!`, 'success');
    } else if (type === 'SITREP') {
      const sitrepList = [...(selectedOp.laporan_harian || [])].sort((a, b) => (b.id_laporan || 0) - (a.id_laporan || 0));
      if (sitrepList.length === 0) {
        showWarningAlert('Data Belum Ada', 'Belum ada Laporan Perkembangan (Lapbang) yang dibuat untuk operasi SAR ini.');
        return;
      }
      const targetSitrep = specificItem || sitrepList[0];
      if (targetSitrep.jenis_laporan === 'PENUTUPAN') {
        const res = buildBraSectionsFromPenutupan(selectedOp, targetSitrep, twbStr, twuStr);
        setSections(res.sections);
        setPerihal(res.perihal);
        setAksi(res.aksi);
        setInfo(res.info);
        setTwu(twuStr);
        setTwb(twbStr);
        showToast('Rekomendasi Laporan Penutupan Operasi berhasil dimuat!', 'success');
      } else {
        const res = buildBraSectionsFromSitrep(selectedOp, targetSitrep, twbStr, twuStr);
        setSections(res.sections);
        setPerihal(res.perihal);
        setAksi(res.aksi);
        setInfo(res.info);
        setTwu(twuStr);
        setTwb(twbStr);
        showToast(`Rekomendasi Laporan Perkembangan ${targetSitrep.hari_ke || ''} berhasil dimuat!`, 'success');
      }
    } else if (type === 'PENUTUPAN') {
      const sitrepList = [...(selectedOp.laporan_harian || [])].filter(l => l.jenis_laporan === 'PENUTUPAN');
      const closingSitrep = sitrepList[0] || null;
      const res = buildBraSectionsFromPenutupan(selectedOp, closingSitrep, twbStr, twuStr);
      setSections(res.sections);
      setPerihal(res.perihal);
      setAksi(res.aksi);
      setInfo(res.info);
      setTwu(twuStr);
      setTwb(twbStr);
      showToast('Rekomendasi Laporan Penutupan Operasi berhasil dimuat!', 'success');
    }
  };

  // Muat Preset Kapal (KN SAR Pacitan 102 / Rescue Boat 210) sesuai Lampiran Resmi
  const handleLoadVesselPreset = (vesselType: 'PACITAN' | 'RB210') => {
    const now = new Date();
    const twbStr = formatMilitaryTW(new Date(now.getTime() - 15 * 60000), 'H');
    if (vesselType === 'PACITAN') {
      setAksi('KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN');
      setInfo('DEPUTI OPS DAN KESIAPSIAGAAN KMA DEPUTI SARPRAS DAN SISTEM KOMUNIKASI KMA DIREKTUR SARPRAS DAN DIREKTUR KESIAPSIAGAAN');
      setPerihal('PELAYARAN KN SAR PACITAN 102 KENDARI DALAM RANGKA BKO OPRASI SAR KAPAL TENGGELAM KM VIRGO TRANSPORT 8 DISEPUTARAN LAUT JAWA - BANJARMASIN TTK');
      setSections([
        {
          kode: 'AAA',
          isi: `PD TW ${twbStr} KMA PELAYARAN KN SAR PACITAN 102 KENDARI DALAM RANGKA BKO OPERASI SAR KAPAL TENGGELAM KM VIRGO TRANSPORT 8 DISEPUTARAN LAUT JAWA - BANJARMASIN TTK`,
        },
        {
          kode: 'BBB',
          isi: `DATA KAPAL TTK DUA
NAMA : KN.SAR PACITAN 102
NAKHODA : RUSLAN, S.T
LOA : 59,80 M
TYPE : ALUMINIUM
TAHUN : 2013
ENDURANCE : 47 JAM /69,84%
SPEED MAX : 20 KNOT
SPEED ECO : 15 KNOT

REN LAYAR TTK DUA
RUTE : DERMAGA UTAMA BASARNAS KAWASAN TIMUR KMA KENDARI KMA MENUJU PERAIRAN LAUT JAWA - BANJARMASIN DENGAN KETERANGAN TTK DUA
HEADING : 266°
JARAK TEMPUH : 620 NM
ESTIMASI PERGERAKAN 45 JAM
ETD : ${formatIndoDate(now).toUpperCase()} - PKL. 20.45 WITA
ETA : ${formatIndoDate(new Date(now.getTime() + 48 * 3600000)).toUpperCase()} - PKL. 15.45 WIB

MUATAN POB TTK DUA
CREW KN SAR PACITAN : 20 ORANG
RESCUER : 13 ORANG KMA SEBAGAI BERIKUT TTK DUA
1. (OSC) 1 ORG
2. PENYELAM 7 ORG
3. PILOT DROWN 1 ORG
4. RESCUE 4 ORG
KADET : 4 ORANG
MEKANIK : 2 ORANG
ELTRECION : 1 ORANG
TOTAL POB : 40 ORANG

DATA PALSAR ALKOM , ALNAV & ALAT KESELAMATAN TTK DUA
SEA RIDER 1 UNIT ( 40 PK ) READY
SEKOCI 2 UNIT ( 40 PK ) READY
RUBBER BOAT 1 UNIT ( 25 PK ) READY
GPS TRACKING : 1 UNIT READY
RUGGEAR : 1 UNIT READY
STARLINK : 1 UNIT READY
DRONE : 1 UNIT READY
ALAT SELAM : 4 SET READY
KANTONG JENAZAH : 130 BH READY
ALAT MEDIS & EVAKUASI : 1 SET READY
TELP SATELITE : +8821650619537
VHF MARINE : 1 UNIT READY
GPS : 2 UNIT READY
AIS : 1 UNIT READY
RADAR : 1 UNIT READY
ECDIS : 1 UNIT READY
HANDI TALKY : 8 UNIT (HT) READY

DATA CUACA TTK DUA
PRAKICU : BERAWAN
ANGIN : TIMUR - BARAT, KECEP. 5 S/D 20 KM/JAM
GELOMBANG : 0,5 - 1,5 METER
(SUMBER BMKG.COM)

LAINS TTK DUA
MOHON BANTUAN BCC UNTUK PEMANTAUAN MELALUI IMSAR DAN GPS TRACKING TTK`,
        },
        {
          kode: 'CCC',
          isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KDI KRM TTK HBS`,
        },
      ]);
    } else {
      setAksi('KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN');
      setInfo('DEPUTI OPS DAN KESIAPSIAGAAN KMA DIREKTUR KESIAPSIAGAAN KMA DIREKTUR SARPRAS');
      setPerihal('RENCANA PELAYARAN RESCUE BOAT 210 KENDARI DALAM RANGKA PEMANASAN BERGERAK DAN PROFICIENCY ABK TTK');
      setSections([
        {
          kode: 'AAA',
          isi: `PD TW ${twbStr} KMA RENCANA PELAYARAN RESCUE BOAT 210 KENDARI DALAM RANGKA PEMANASAN BERGERAK DAN PROFICIENCY ABK DI SEKITAR PERAIRAN TELUK KENDARI TTK`,
        },
        {
          kode: 'BBB',
          isi: `DATA KAPAL TTK DUA
NAMA : RB 210 KENDARI
NAKHODA : JUMARDIN, S.E.
LOA : 36 METER
TYPE : KAPAL CEPAT KELAS II / SERI 36M
TAHUN : 2010
BAHAN : ALUMINIUM
BBM : 14.500 LITER (READY)
AIR TAWAR : 6.000 LITER (READY)
SPEED MAX : 26 KNOT
SPEED ECO : 18 KNOT

REN LAYAR TTK DUA
RUTE : DERMAGA UTAMA BASARNAS KAWASAN TIMUR KMA KENDARI MENUJU PERAIRAN TELUK KENDARI DAN SEKITARNYA DENGAN KETERANGAN TTK DUA
ESTIMASI PERGERAKAN : 4 JAM
ETD : ${formatIndoDate(now).toUpperCase()} - PKL. 09.00 WITA
ETA : ${formatIndoDate(now).toUpperCase()} - PKL. 13.00 WITA

MUATAN POB TTK DUA
CREW RB 210 : 12 ORANG
RESCUER : 4 ORANG
TOTAL POB : 16 ORANG

DATA PALSAR ALKOM , ALNAV & ALAT KESELAMATAN TTK DUA
RUBBER BOAT 1 UNIT ( 25 PK ) READY
LIFE JACKET : 20 BUAH READY
RING BUOY : 4 BUAH READY
ALAT SELAM : 2 SET READY
RADIO VHF MARINE : 1 UNIT READY
RADAR : 1 UNIT READY
GPS : 1 UNIT READY
AIS : 1 UNIT READY
HANDI TALKY : 4 UNIT READY

DATA CUACA TTK DUA
PRAKICU : CERAH BERAWAN
ANGIN : TENGGARA - SELATAN, KECEP. 5 S/D 15 KM/JAM
GELOMBANG : 0,5 - 1,25 METER
(SUMBER BMKG.COM)

LAINS TTK DUA
MOHON BANTUAN BCC UNTUK PEMANTAUAN MELALUI IMSAR DAN GPS TRACKING TTK`,
        },
        {
          kode: 'CCC',
          isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KDI KRM TTK HBS`,
        },
      ]);
    }
  };

  // Set TWU / TWB to current WITA
  const handleSetTwuNow = () => {
    setTwu(formatMilitaryTW(new Date(), 'H'));
  };

  const handleSetTwbNow = () => {
    setTwb(formatMilitaryTW(new Date(), 'H'));
  };

  // Section manipulation
  const handleAddSection = () => {
    const nextCode = getSectionCode(sections.length);
    setSections((prev) => [...prev, { kode: nextCode, isi: '' }]);
  };

  const handleRemoveSection = (index: number) => {
    if (sections.length <= 3) {
      showWarningAlert('Batas Minimal', 'Dokumen BRA SAR minimal memerlukan bagian AAA, BBB, dan CCC.');
      return;
    }
    setSections((prev) => prev.filter((_, i) => i !== index));
  };

  const handleSectionContentChange = (index: number, content: string) => {
    setSections((prev) => {
      const updated = [...prev];
      updated[index] = { ...updated[index], isi: content };
      return updated;
    });
  };

  const handleSectionCodeChange = (index: number, code: string) => {
    setSections((prev) => {
      const updated = [...prev];
      updated[index] = { ...updated[index], kode: code.toUpperCase() };
      return updated;
    });
  };

  // Submit
  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!twu || !twb) {
      showWarningAlert('Data Belum Lengkap', 'Tanggal Waktu Urut (TWU) dan Tanggal Waktu Buat (TWB) wajib diisi.');
      return;
    }

    if (sections.some((s) => !s.isi.trim())) {
      showWarningAlert('Bagian Kosong', 'Pastikan semua bagian telegram (AAA, BBB, CCC, dst) telah diisi teksnya.');
      return;
    }

    try {
      setSaving(true);
      const totalWords = countBraWords(sections, `${dari} ${aksi} ${info} ${perihal}`);

      const braPayload: Omit<BraSar, 'id_bra'> = {
        kategori,
        no_bra: noBra,
        nomor_urut: nomorUrut,
        satker_kode: satkerKode,
        bulan_tahun: bulanTahun,
        id_operasi: selectedOperasiId,
        nama_operasi: perihal,
        derajat,
        dari,
        aksi,
        info,
        sifat,
        menbra,
        perihal,
        tanggal_waktu: tanggalWaktu,
        twu,
        twb,
        jumlah_kata: `${totalWords} KATA`,
        sections,
        pengirim,
        nama_smc: namaSmc,
        pangkat_smc: pangkatSmc,
        jabatan_smc: jabatanSmc,
        created_at: new Date().toISOString(),
      };

      const res = await fetch('/api/bra', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(braPayload),
      });

      const data = await res.json();
      if (data.success && data.data) {
        await showSuccessAlert(
          'Dokumen BRA SAR Berhasil Disimpan!',
          `Dokumen nomor ${data.data.no_bra} telah tersimpan dan siap dicetak / disebarkan.`
        );
        onSaveSuccess(data.data);
      } else {
        showErrorAlert('Gagal Menyimpan', data.error || 'Gagal menyimpan dokumen BRA SAR');
      }
    } catch (err: any) {
      console.error('Error saving BRA SAR:', err);
      showErrorAlert('Kesalahan Jaringan', 'Terjadi kesalahan sistem saat menyimpan dokumen BRA SAR');
    } finally {
      setSaving(false);
    }
  };

  // Current preview object
  const previewBraObj: BraSar = {
    id_bra: 9999,
    kategori,
    no_bra: noBra || `191/${satkerKode}/${bulanTahun}`,
    nomor_urut: nomorUrut,
    satker_kode: satkerKode,
    bulan_tahun: bulanTahun,
    id_operasi: selectedOperasiId,
    nama_operasi: perihal,
    derajat,
    dari,
    aksi,
    info,
    sifat,
    menbra,
    perihal,
    tanggal_waktu: tanggalWaktu,
    twu,
    twb,
    jumlah_kata: `${countBraWords(sections)} KATA`,
    sections,
    pengirim,
    nama_smc: namaSmc,
    pangkat_smc: pangkatSmc,
    jabatan_smc: jabatanSmc,
    created_at: new Date().toISOString(),
  };

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-6">
      {/* Top Banner & Category Tabs */}
      <div className={`border rounded-2xl p-5 shadow-xl transition-colors ${
        isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
      }`}>
        <div className={`flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b pb-5 ${
          isDarkMode ? 'border-slate-800' : 'border-slate-200'
        }`}>
          <div>
            <div className="flex items-center gap-2.5">
              <div className={`w-9 h-9 rounded-lg border flex items-center justify-center ${accentColors.badgeBg} ${accentColors.badgeText} border-current/30`}>
                <Radio className="w-5 h-5 animate-pulse" />
              </div>
              <div>
                <h1 className={`text-lg font-bold tracking-wide ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                  Pembuatan Berita Radio SAR (BRA SAR)
                </h1>
                <p className={`text-xs ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                  Formulir Naskah Telegram Radio Resmi Standar Badan Nasional Pencarian dan Pertolongan
                </p>
              </div>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => setShowPreview(!showPreview)}
              className={`inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg border transition-colors ${
                isDarkMode
                  ? 'border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-200'
                  : 'border-slate-300 bg-white hover:bg-slate-100 text-slate-700 shadow-sm'
              }`}
            >
              <Eye className={`w-4 h-4 ${accentColors.text}`} />
              <span>{showPreview ? 'Sembunyikan Pratinjau' : 'Lihat Pratinjau Dokumen'}</span>
            </button>

            <button
              type="button"
              onClick={onCancel}
              className={`p-2 rounded-lg transition-colors ${
                isDarkMode
                  ? 'text-slate-400 hover:text-white hover:bg-slate-800'
                  : 'text-slate-500 hover:text-slate-900 hover:bg-slate-100'
              }`}
              title="Batal"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* 3 Kategori Selector Tabs */}
        <div className="mt-5 grid grid-cols-1 md:grid-cols-3 gap-3">
          {/* Tab 1: BRA Mapel */}
          <button
            type="button"
            onClick={() => setKategori('MAPEL')}
            className={`flex items-start gap-3.5 p-3.5 rounded-xl border text-left transition-all ${
              kategori === 'MAPEL'
                ? isDarkMode
                  ? `${accentColors.badgeBg} border-current ring-1 ring-current/40 shadow-lg text-white`
                  : 'bg-orange-50 border-orange-400 text-orange-950 ring-1 ring-orange-400/40 shadow-sm'
                : isDarkMode
                ? 'bg-slate-850/60 border-slate-800 hover:bg-slate-800 text-slate-400'
                : 'bg-slate-50 border-slate-200 hover:bg-slate-100 text-slate-600'
            }`}
          >
            <div
              className={`p-2 rounded-lg shrink-0 ${
                kategori === 'MAPEL'
                  ? 'bg-orange-500 text-white shadow-sm'
                  : isDarkMode
                  ? 'bg-slate-800 text-slate-500'
                  : 'bg-slate-200 text-slate-600'
              }`}
            >
              <Radio className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className={`text-sm font-bold ${isDarkMode ? 'text-slate-100' : 'text-slate-900'}`}>
                  1. BRA Mapel
                </span>
                <span className="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-orange-500/20 text-orange-600 dark:text-orange-300 border border-orange-500/30">
                  Broadcast Notice
                </span>
              </div>
              <p className={`text-xs mt-1 leading-snug ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Pemberitahuan kepada Syahbandar, KSOP, SROP, dan kapal-kapal yang melintas untuk pemantauan dan pemapelan korban hilang di laut.
              </p>
            </div>
          </button>

          {/* Tab 2: BRA SAR Operasi SAR */}
          <button
            type="button"
            onClick={() => setKategori('SAR_UMUM')}
            className={`flex items-start gap-3.5 p-3.5 rounded-xl border text-left transition-all ${
              kategori === 'SAR_UMUM'
                ? isDarkMode
                  ? `${accentColors.badgeBg} border-current ring-1 ring-current/40 shadow-lg text-white`
                  : 'bg-amber-50 border-amber-400 text-amber-950 ring-1 ring-amber-400/40 shadow-sm'
                : isDarkMode
                ? 'bg-slate-850/60 border-slate-800 hover:bg-slate-800 text-slate-400'
                : 'bg-slate-50 border-slate-200 hover:bg-slate-100 text-slate-600'
            }`}
          >
            <div
              className={`p-2 rounded-lg shrink-0 ${
                kategori === 'SAR_UMUM'
                  ? 'bg-amber-500 text-white shadow-sm'
                  : isDarkMode
                  ? 'bg-slate-800 text-slate-500'
                  : 'bg-slate-200 text-slate-600'
              }`}
            >
              <FileText className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className={`text-sm font-bold ${isDarkMode ? 'text-slate-100' : 'text-slate-900'}`}>
                  2. BRA SAR Operasi SAR
                </span>
                <span className="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-600 dark:text-amber-300 border border-amber-500/30">
                  Operasi Harian
                </span>
              </div>
              <p className={`text-xs mt-1 leading-snug ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Berita Radio perkembangan harian Operasi SAR kepada Kabasarnas, Pimti, Polairud, TNI AL, dan BPBD terkait rencana dan hasil pencarian.
              </p>
            </div>
          </button>

          {/* Tab 3: BRA Pergerakan Kapal */}
          <button
            type="button"
            onClick={() => setKategori('PERGERAKAN_KAPAL')}
            className={`flex items-start gap-3.5 p-3.5 rounded-xl border text-left transition-all ${
              kategori === 'PERGERAKAN_KAPAL'
                ? isDarkMode
                  ? `${accentColors.badgeBg} border-current ring-1 ring-current/40 shadow-lg text-white`
                  : 'bg-cyan-50 border-cyan-400 text-cyan-950 ring-1 ring-cyan-400/40 shadow-sm'
                : isDarkMode
                ? 'bg-slate-850/60 border-slate-800 hover:bg-slate-800 text-slate-400'
                : 'bg-slate-50 border-slate-200 hover:bg-slate-100 text-slate-600'
            }`}
          >
            <div
              className={`p-2 rounded-lg shrink-0 ${
                kategori === 'PERGERAKAN_KAPAL'
                  ? 'bg-cyan-500 text-white shadow-sm'
                  : isDarkMode
                  ? 'bg-slate-800 text-slate-500'
                  : 'bg-slate-200 text-slate-600'
              }`}
            >
              <Ship className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <span className={`text-sm font-bold ${isDarkMode ? 'text-slate-100' : 'text-slate-900'}`}>
                  3. BRA Pergerakan Kapal
                </span>
                <span className="text-[10px] font-semibold uppercase px-1.5 py-0.5 rounded bg-cyan-500/20 text-cyan-700 dark:text-cyan-300 border border-cyan-500/30">
                  Alut Laut & BKO
                </span>
              </div>
              <p className={`text-xs mt-1 leading-snug ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Pergerakan kapal SAR (KN SAR Pacitan 102 / RB 210) dalam rangka BKO Operasi SAR, pemanasan bergerak, atau pengiriman alutsista laut.
              </p>
            </div>
          </button>
        </div>

        {/* Quick Vessel Presets when PERGERAKAN_KAPAL is active */}
        {kategori === 'PERGERAKAN_KAPAL' && (
          <div className={`mt-4 p-3 rounded-xl border flex flex-wrap items-center justify-between gap-3 animate-in fade-in transition-colors ${
            isDarkMode
              ? 'bg-cyan-950/30 border-cyan-500/30 text-cyan-300'
              : 'bg-cyan-50 border-cyan-200 text-cyan-900'
          }`}>
            <div className="flex items-center gap-2 text-xs font-medium">
              <Anchor className="w-4 h-4 text-cyan-500 shrink-0" />
              <span>
                <strong>Pilihan Cepat Sesuai Format Lampiran:</strong> Muat format spesifikasi kapal resmi:
              </span>
            </div>
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => handleLoadVesselPreset('PACITAN')}
                className={`px-2.5 py-1 text-xs font-semibold rounded-lg border transition-colors ${
                  isDarkMode
                    ? 'bg-cyan-600/30 hover:bg-cyan-600/50 text-cyan-200 border-cyan-500/40'
                    : 'bg-white hover:bg-cyan-100 text-cyan-800 border-cyan-300 shadow-sm'
                }`}
                title="Muat format KN SAR Pacitan 102 (BKO Laut Jawa / Operasi SAR)"
              >
                ⚓ KN SAR Pacitan 102 (BKO)
              </button>
              <button
                type="button"
                onClick={() => handleLoadVesselPreset('RB210')}
                className={`px-2.5 py-1 text-xs font-semibold rounded-lg border transition-colors ${
                  isDarkMode
                    ? 'bg-cyan-600/30 hover:bg-cyan-600/50 text-cyan-200 border-cyan-500/40'
                    : 'bg-white hover:bg-cyan-100 text-cyan-800 border-cyan-300 shadow-sm'
                }`}
                title="Muat format Rescue Boat 210 (Pemanasan Bergerak Kendari)"
              >
                🚤 Rescue Boat 210 (Pemanasan)
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Live Preview Drawer (If toggled) */}
      {showPreview && (
        <div className={`p-4 rounded-2xl border shadow-2xl transition-colors ${
          isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-100 border-slate-300'
        }`}>
          <div className="flex items-center justify-between mb-3 text-xs">
            <span className={`font-semibold ${accentColors.text}`}>
              Pratinjau Hasil Cetak Sesuai Format Lampiran Resmi :
            </span>
            <button
              onClick={() => setShowPreview(false)}
              className={`hover:underline ${isDarkMode ? 'text-slate-400 hover:text-white' : 'text-slate-600 hover:text-slate-900'}`}
            >
              Tutup Pratinjau
            </button>
          </div>
          <OfficialBraDocument bra={previewBraObj} isEmbedded />
        </div>
      )}

      {/* Main Form */}
      <form onSubmit={handleSave} className="space-y-6">
        {/* Card 1: Penomoran Otomatis & Identitas Satker */}
        <div className={`rounded-2xl p-5 shadow-lg space-y-4 border transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between">
            <h2 className={`text-sm font-bold flex items-center gap-2 ${isDarkMode ? 'text-slate-200' : 'text-slate-900'}`}>
              <Hash className={`w-4 h-4 ${accentColors.text}`} />
              <span>Penomoran Otomatis & Sifat Berita</span>
            </h2>
            <div className={`flex items-center gap-1.5 text-[11px] px-2.5 py-1 rounded-full border ${
              isDarkMode
                ? 'text-emerald-400 bg-emerald-950/40 border-emerald-500/30'
                : 'text-emerald-700 bg-emerald-50 border-emerald-300'
            }`}>
              <ShieldCheck className="w-3.5 h-3.5" />
              <span>Proteksi Anti-Tabrakan Aktif (Atomic Server Lock)</span>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {/* Nomor Dokumen */}
            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                Nomor BRA (Otomatis)
              </label>
              <div className="relative">
                <input
                  type="text"
                  value={noBra}
                  onChange={(e) => setNoBra(e.target.value)}
                  className={`w-full font-mono font-bold text-sm rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                    isDarkMode
                      ? `bg-slate-800 border-slate-700 ${accentColors.text}`
                      : `bg-slate-50 border-slate-300 ${accentColors.text}`
                  }`}
                  placeholder="191/SAR-304/0926"
                  required
                />
                {loadingNumber && (
                  <span className={`absolute right-3 top-2.5 text-[10px] animate-pulse ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                    Mengambil nomor...
                  </span>
                )}
              </div>
              <p className={`text-[11px] mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Contoh: <strong className={isDarkMode ? 'text-slate-300' : 'text-slate-800'}>{nomorUrut}</strong> (nomor urut) /{' '}
                <strong className={isDarkMode ? 'text-slate-300' : 'text-slate-800'}>{satkerKode}</strong> (satker) /{' '}
                <strong className={isDarkMode ? 'text-slate-300' : 'text-slate-800'}>{bulanTahun}</strong> (bulan & tahun).
              </p>
            </div>

            {/* Sifat / Derajat */}
            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                Derajat Berita (SENKOM)
              </label>
              <input
                type="text"
                value={derajat}
                onChange={(e) => setDerajat(e.target.value)}
                className={`w-full text-sm font-mono uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-200 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
                placeholder="SGR"
              />
              <span className={`text-[10px] block mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                SGR = Segera (Default Basarnas)
              </span>
            </div>

            {/* Pilih Operasi (Opsional) */}
            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                Kaitkan ke Operasi SAR Aktif (Opsional)
              </label>
              <select
                value={selectedOperasiId || ''}
                onChange={(e) => handleOperasiChange(e.target.value)}
                className={`w-full text-xs rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-200 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
              >
                <option value="">-- Buat Mandiri (Tanpa Link Operasi) --</option>
                {operasiList.map((op) => (
                  <option key={op.id_operasi} value={op.id_operasi}>
                    #{op.id_operasi} - {op.nama_operasi}
                  </option>
                ))}
              </select>
              <span className={`text-[10px] block mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Pilih untuk otomatis mengisi data korban, alut, dan lokasi.
              </span>
            </div>
          </div>

          {/* Quick Recommendation from selected Operasi SAR reports */}
          {selectedOperasiId && (() => {
            const selectedOp = operasiList.find((o) => o.id_operasi === selectedOperasiId);
            const renopsList = selectedOp?.renops || [];
            const sitrepList = (selectedOp?.laporan_harian || []).filter(l => l.jenis_laporan !== 'PENUTUPAN');
            const penutupanList = (selectedOp?.laporan_harian || []).filter(l => l.jenis_laporan === 'PENUTUPAN');

            return (
              <div className={`p-4 rounded-xl border flex flex-col gap-3 animate-in fade-in transition-colors ${
                isDarkMode
                  ? 'bg-amber-950/20 border-amber-500/30 text-amber-200'
                  : 'bg-amber-50/80 border-amber-200 text-amber-900'
              }`}>
                <div className="flex items-center justify-between gap-2 flex-wrap text-xs">
                  <div className="flex items-center gap-2">
                    <Sparkles className="w-4 h-4 text-amber-500 shrink-0" />
                    <div>
                      <span className="font-bold">Muat Format Otomatis Berdasarkan Laporan Operasi:</span>
                      <p className={`text-[11px] ${isDarkMode ? 'text-amber-300/80' : 'text-amber-700'}`}>
                        Pilih sumber laporan untuk menghasilkan naskah Berita SAR standar Basarnas:
                      </p>
                    </div>
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-amber-500/20">
                  {/* 1. Laporan Awal (Inisiasi) */}
                  <button
                    type="button"
                    onClick={() => handleLoadRecommendation('INISIASI')}
                    className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all flex items-center gap-1.5 shadow-sm ${
                      isDarkMode
                        ? 'bg-slate-800 hover:bg-slate-700 text-amber-300 border-amber-500/40'
                        : 'bg-white hover:bg-amber-100/60 text-slate-800 border-slate-300'
                    }`}
                    title="Muat naskah Telegram Laporan Awal (AAA: Pelapor & LKP, BBB: Tim & Alut, CCC: Unsur & Cuaca, DDD: Lapbang Menyusul)"
                  >
                    <FileText className="w-3.5 h-3.5 text-amber-500" />
                    <span>Laporan Awal (Inisiasi)</span>
                  </button>

                  {/* 2. Renops (Rencana Operasi) */}
                  {renopsList.length > 0 && (
                    <div className="flex items-center gap-1">
                      <button
                        type="button"
                        onClick={() => handleLoadRecommendation('RENOPS')}
                        className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all flex items-center gap-1.5 shadow-sm ${
                          isDarkMode
                            ? 'bg-amber-600/30 hover:bg-amber-600/50 text-amber-200 border-amber-500/40'
                            : 'bg-white hover:bg-amber-100 text-amber-900 border-amber-300'
                        }`}
                        title="Muat Rencana Operasi terakhir"
                      >
                        <FileText className="w-3.5 h-3.5 text-blue-500" />
                        <span>Renops Terakhir ({renopsList[renopsList.length - 1].hari_ke || 'H.1'})</span>
                      </button>

                      {renopsList.length > 1 && (
                        <select
                          onChange={(e) => {
                            const val = e.target.value;
                            if (val) {
                              const r = renopsList.find(item => item.id_renops === parseInt(val, 10));
                              if (r) handleLoadRecommendation('RENOPS', r);
                            }
                          }}
                          defaultValue=""
                          className={`text-xs rounded-lg px-2 py-1.5 border transition-colors focus:outline-none ${
                            isDarkMode
                              ? 'bg-slate-800 text-slate-200 border-slate-700'
                              : 'bg-white text-slate-800 border-slate-300 shadow-sm'
                          }`}
                        >
                          <option value="" disabled>Pilih Renops Harian...</option>
                          {renopsList.map((r, idx) => (
                            <option key={r.id_renops || idx} value={r.id_renops}>
                              {r.hari_ke || `Hari Ke-${idx + 1}`} ({r.tanggal_laporan || 'Renops'})
                            </option>
                          ))}
                        </select>
                      )}
                    </div>
                  )}

                  {/* 3. Lapbang (Laporan Perkembangan) */}
                  {sitrepList.length > 0 && (
                    <div className="flex items-center gap-1">
                      <button
                        type="button"
                        onClick={() => handleLoadRecommendation('SITREP')}
                        className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all flex items-center gap-1.5 shadow-sm ${
                          isDarkMode
                            ? 'bg-amber-600/30 hover:bg-amber-600/50 text-amber-200 border-amber-500/40'
                            : 'bg-white hover:bg-amber-100 text-amber-900 border-amber-300'
                        }`}
                        title="Muat Laporan Perkembangan terakhir"
                      >
                        <Send className="w-3.5 h-3.5 text-emerald-500" />
                        <span>Lapbang Terakhir ({sitrepList[sitrepList.length - 1].hari_ke || 'H.1'})</span>
                      </button>

                      {sitrepList.length > 1 && (
                        <select
                          onChange={(e) => {
                            const val = e.target.value;
                            if (val) {
                              const s = sitrepList.find(item => item.id_laporan === parseInt(val, 10));
                              if (s) handleLoadRecommendation('SITREP', s);
                            }
                          }}
                          defaultValue=""
                          className={`text-xs rounded-lg px-2 py-1.5 border transition-colors focus:outline-none ${
                            isDarkMode
                              ? 'bg-slate-800 text-slate-200 border-slate-700'
                              : 'bg-white text-slate-800 border-slate-300 shadow-sm'
                          }`}
                        >
                          <option value="" disabled>Pilih Lapbang Harian...</option>
                          {sitrepList.map((s, idx) => (
                            <option key={s.id_laporan || idx} value={s.id_laporan}>
                              {s.hari_ke || `Hari Ke-${idx + 1}`} ({s.waktu_laporan ? new Date(s.waktu_laporan).toLocaleDateString('id-ID') : 'Lapbang'})
                            </option>
                          ))}
                        </select>
                      )}
                    </div>
                  )}

                  {/* 4. Penutupan Operasi (if exists or op status is CLOSED) */}
                  {(penutupanList.length > 0 || selectedOp?.status === 'CLOSED') && (
                    <button
                      type="button"
                      onClick={() => handleLoadRecommendation('PENUTUPAN', penutupanList[0])}
                      className={`px-3 py-1.5 text-xs font-semibold rounded-lg border transition-all flex items-center gap-1.5 shadow-sm ${
                        isDarkMode
                          ? 'bg-red-900/30 hover:bg-red-900/50 text-red-200 border-red-500/40'
                          : 'bg-red-50 hover:bg-red-100 text-red-900 border-red-300'
                      }`}
                      title="Muat naskah Telegram Penutupan Operasi SAR (Debriefing & Pengembalian Unsur)"
                    >
                      <ShieldCheck className="w-3.5 h-3.5 text-red-500" />
                      <span>Penutupan Operasi SAR</span>
                    </button>
                  )}
                </div>
              </div>
            );
          })()}
        </div>

        {/* Card 2: Format Militer TWU & TWB */}
        <div className={`rounded-2xl p-5 shadow-lg space-y-4 border transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between">
            <h2 className={`text-sm font-bold flex items-center gap-2 ${isDarkMode ? 'text-slate-200' : 'text-slate-900'}`}>
              <Clock className={`w-4 h-4 ${accentColors.text}`} />
              <span>Format Waktu Militer Basarnas (TWB & TWU)</span>
            </h2>
            <div className={`flex items-center gap-1 text-[11px] ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
              <HelpCircle className={`w-3.5 h-3.5 ${accentColors.text}`} />
              <span>Format: MMDD HHmm H (H = WITA / UTC+8)</span>
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {/* TWB: Tanggal Waktu Buat */}
            <div className={`p-3.5 rounded-xl border transition-colors ${
              isDarkMode ? 'bg-slate-850 border-slate-800' : 'bg-slate-50 border-slate-200'
            }`}>
              <div className="flex items-center justify-between mb-1.5">
                <label className={`text-xs font-semibold ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
                  TWB (Tanggal Waktu Buat)
                </label>
                <button
                  type="button"
                  onClick={handleSetTwbNow}
                  className={`text-[11px] font-medium inline-flex items-center gap-1 ${accentColors.text} hover:opacity-80 transition-opacity`}
                >
                  <Sparkles className="w-3 h-3" />
                  <span>Waktu Sekarang</span>
                </button>
              </div>
              <input
                type="text"
                value={twb}
                onChange={(e) => setTwb(e.target.value.toUpperCase())}
                className={`w-full font-mono font-bold text-sm uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-900 text-slate-100 border-slate-700'
                    : 'bg-white text-slate-900 border-slate-300'
                }`}
                placeholder="0920 0645 H"
                required
              />
              <p className={`text-[10px] mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Contoh: <strong className={isDarkMode ? 'text-slate-300' : 'text-slate-700'}>0920 0645 H</strong> (Bulan 09, Tanggal 20, Pukul 06:45 WITA).
              </p>
            </div>

            {/* TWU: Tanggal Waktu Urut / Kirim */}
            <div className={`p-3.5 rounded-xl border transition-colors ${
              isDarkMode ? 'bg-slate-850 border-slate-800' : 'bg-slate-50 border-slate-200'
            }`}>
              <div className="flex items-center justify-between mb-1.5">
                <label className={`text-xs font-semibold ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
                  TWU (Tanggal Waktu Urut / Kirim)
                </label>
                <button
                  type="button"
                  onClick={handleSetTwuNow}
                  className={`text-[11px] font-medium inline-flex items-center gap-1 ${accentColors.text} hover:opacity-80 transition-opacity`}
                >
                  <Sparkles className="w-3 h-3" />
                  <span>Waktu Sekarang</span>
                </button>
              </div>
              <input
                type="text"
                value={twu}
                onChange={(e) => setTwu(e.target.value.toUpperCase())}
                className={`w-full font-mono font-bold text-sm uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-900 text-slate-100 border-slate-700'
                    : 'bg-white text-slate-900 border-slate-300'
                }`}
                placeholder="0920 0700 H"
                required
              />
              <p className={`text-[10px] mt-1 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Dicetak pada kolom kanan atas telegram untuk arsip stasiun radio.
              </p>
            </div>
          </div>
        </div>

        {/* Card 3: Alamat Pengiriman (DARI, AKSI, INFO, PERIHAL) */}
        <div className={`rounded-2xl p-5 shadow-lg space-y-4 border transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <h2 className={`text-sm font-bold flex items-center gap-2 ${isDarkMode ? 'text-slate-200' : 'text-slate-900'}`}>
            <Send className={`w-4 h-4 ${accentColors.text}`} />
            <span>Alamat Pengiriman Telegram Radio (DARI / AKSI / INFO)</span>
          </h2>

          <div className="grid grid-cols-1 gap-3.5">
            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                DARI (Satker Asal)
              </label>
              <input
                type="text"
                value={dari}
                onChange={(e) => setDari(e.target.value.toUpperCase())}
                className={`w-full text-xs sm:text-sm font-medium uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-100 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
                required
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                AKSI (Tujuan Utama Tindakan)
              </label>
              <input
                type="text"
                value={aksi}
                onChange={(e) => setAksi(e.target.value.toUpperCase())}
                className={`w-full text-xs sm:text-sm font-medium uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-100 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
                placeholder={
                  kategori === 'MAPEL'
                    ? 'KA KSOP KENDARI KMA KA KSOP BAU BAU DAN KA SROP BAU BAU'
                    : 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN'
                }
                required
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                INFO (Tembusan Informasi)
              </label>
              <input
                type="text"
                value={info}
                onChange={(e) => setInfo(e.target.value.toUpperCase())}
                className={`w-full text-xs sm:text-sm font-medium uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-100 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
                placeholder="KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN"
                required
              />
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {kategori === 'MAPEL' ? (
                <div>
                  <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                    SIFAT
                  </label>
                  <input
                    type="text"
                    value={sifat}
                    onChange={(e) => setSifat(e.target.value.toUpperCase())}
                    className={`w-full text-xs sm:text-sm rounded-lg px-3 py-2 uppercase border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                      isDarkMode
                        ? 'bg-slate-800 text-slate-100 border-slate-700'
                        : 'bg-slate-50 text-slate-900 border-slate-300'
                    }`}
                    placeholder="SEGERA"
                  />
                </div>
              ) : (
                <div>
                  <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                    MENBRA (Nomor Rujukan BRA Sebelumnya)
                  </label>
                  <input
                    type="text"
                    value={menbra}
                    onChange={(e) => setMenbra(e.target.value.toUpperCase())}
                    className={`w-full text-xs sm:text-sm rounded-lg px-3 py-2 uppercase border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                      isDarkMode
                        ? 'bg-slate-800 text-slate-100 border-slate-700'
                        : 'bg-slate-50 text-slate-900 border-slate-300'
                    }`}
                    placeholder="Contoh: 197/SAR-304/0726 (Opsional)"
                  />
                </div>
              )}

              <div>
                <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                  PERIHAL / JUDUL OPERASI
                </label>
                <input
                  type="text"
                  value={perihal}
                  onChange={(e) => setPerihal(e.target.value.toUpperCase())}
                  className={`w-full text-xs sm:text-sm rounded-lg px-3 py-2 uppercase border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                    isDarkMode
                      ? 'bg-slate-800 text-slate-100 border-slate-700'
                      : 'bg-slate-50 text-slate-900 border-slate-300'
                  }`}
                  placeholder="KECELAKAAN KAPAL..."
                  required
                />
              </div>
            </div>
          </div>
        </div>

        {/* Card 4: Dynamic Sections (AAA, BBB, CCC, DDD, ...) */}
        <div className={`rounded-2xl p-5 shadow-lg space-y-4 border transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between flex-wrap gap-2">
            <div>
              <h2 className={`text-sm font-bold flex items-center gap-2 ${isDarkMode ? 'text-slate-200' : 'text-slate-900'}`}>
                <FileText className={`w-4 h-4 ${accentColors.text}`} />
                <span>Naskah Isi Berita Radio (Bagian Bertingkat AAA, BBB, CCC, dst)</span>
              </h2>
              <p className={`text-xs mt-0.5 ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                Minimal wajib terdapat bagian AAA, BBB, dan CCC. Anda dapat menambahkan DDD, EEE, dst sesuai kebutuhan operasional.
              </p>
            </div>

            <button
              type="button"
              onClick={handleAddSection}
              className={`inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 rounded-lg shadow-md transition-all hover:scale-[1.02] active:scale-[0.98]`}
            >
              <Plus className="w-3.5 h-3.5" />
              <span>+ Tambah Bagian ({getSectionCode(sections.length)})</span>
            </button>
          </div>

          <div className="space-y-4 mt-3">
            {sections.map((sec, idx) => (
              <div
                key={idx}
                className={`p-4 rounded-xl border space-y-2 relative transition-colors ${
                  isDarkMode ? 'bg-slate-850 border-slate-800' : 'bg-slate-50 border-slate-200'
                }`}
              >
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className={`w-7 h-7 rounded-lg border font-black text-xs flex items-center justify-center font-mono ${accentColors.badgeBg} ${accentColors.badgeText} border-current/30`}>
                      {sec.kode}
                    </span>
                    <input
                      type="text"
                      value={sec.kode}
                      onChange={(e) => handleSectionCodeChange(idx, e.target.value)}
                      className={`w-16 font-mono font-bold text-xs rounded px-2 py-1 uppercase text-center border transition-colors focus:outline-none focus:ring-1 ${accentColors.ring} ${
                        isDarkMode
                          ? 'bg-slate-900 text-slate-200 border-slate-700'
                          : 'bg-white text-slate-900 border-slate-300'
                      }`}
                    />
                    <span className={`text-xs font-medium ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                      {idx === 0 && (kategori === 'PERGERAKAN_KAPAL' ? '(Dasar & Rencana Pelayaran / BKO)' : '(Kronologi / Informasi Awal)')}
                      {idx === 1 && (kategori === 'MAPEL' ? '(Permintaan Mapel / Pemantauan Kapal)' : kategori === 'PERGERAKAN_KAPAL' ? '(Data Kapal, Ren Layar, POB, Palsar Alkom, Cuaca & Lains)' : '(Unsur SAR Terlibat, Alut, Palsar & Cuaca)')}
                      {idx === 2 && (kategori === 'PERGERAKAN_KAPAL' ? '(Laporan Perkembangan Menyusul / Penutup)' : '(Penutup / Rencana Tindak Lanjut)')}
                      {idx >= 3 && '(Bagian Tambahan)'}
                    </span>
                  </div>

                  {idx >= 3 && (
                    <button
                      type="button"
                      onClick={() => handleRemoveSection(idx)}
                      className="text-xs text-rose-500 hover:text-rose-600 p-1 rounded hover:bg-rose-500/10 transition-colors inline-flex items-center gap-1"
                      title="Hapus bagian ini"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                      <span>Hapus</span>
                    </button>
                  )}
                </div>

                <textarea
                  rows={4}
                  value={sec.isi}
                  onChange={(e) => handleSectionContentChange(idx, e.target.value.toUpperCase())}
                  className={`w-full font-mono text-xs sm:text-[13px] rounded-lg p-3 uppercase leading-relaxed border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                    isDarkMode
                      ? 'bg-slate-900 text-slate-100 border-slate-700'
                      : 'bg-white text-slate-900 border-slate-300'
                  }`}
                  placeholder={`Isi naskah telegram untuk bagian ${sec.kode}...`}
                  required
                />
              </div>
            ))}
          </div>
        </div>

        {/* Card 5: Pengesahan SMC & Barcode Digital */}
        <div className={`rounded-2xl p-5 shadow-lg space-y-4 border transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between">
            <h2 className={`text-sm font-bold flex items-center gap-2 ${isDarkMode ? 'text-slate-200' : 'text-slate-900'}`}>
              <Building2 className={`w-4 h-4 ${accentColors.text}`} />
              <span>Pengirim (SMC) & Barcode Tanda Tangan Digital</span>
            </h2>
            <span className={`text-[11px] font-medium px-2 py-0.5 rounded border ${
              isDarkMode
                ? 'text-emerald-400 bg-emerald-950/40 border-emerald-500/30'
                : 'text-emerald-700 bg-emerald-50 border-emerald-300'
            }`}>
              QR Code Terintegrasi
            </span>
          </div>

          {/* Selector Pejabat Terdaftar */}
          <div className={`p-3 rounded-xl border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 ${
            isDarkMode ? 'bg-slate-850/70 border-slate-800' : 'bg-slate-50 border-slate-200'
          }`}>
            <div className="flex items-center gap-2.5">
              <Shield className={`w-4 h-4 ${accentColors.text} shrink-0`} />
              <div>
                <span className={`text-xs font-bold block ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
                  Pilih Pejabat Penandatangan / Pengirim
                </span>
                <span className={`text-[11px] ${isDarkMode ? 'text-slate-400' : 'text-slate-500'}`}>
                  Data nama, pangkat, dan jabatan otomatis terisi sesuai Master Pejabat
                </span>
              </div>
            </div>
            <select
              value={selectedPejabatId}
              onChange={(e) => handleSelectPejabat(e.target.value)}
              className={`w-full sm:w-72 text-xs rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                isDarkMode
                  ? 'bg-slate-800 text-slate-100 border-slate-700'
                  : 'bg-white text-slate-900 border-slate-300 shadow-sm'
              }`}
            >
              <option value="">-- Pilih dari Master Pejabat --</option>
              {pejabatList.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.nama} - {p.jabatan}
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                Instansi Pengirim
              </label>
              <input
                type="text"
                value={pengirim}
                onChange={(e) => setPengirim(e.target.value.toUpperCase())}
                className={`w-full text-xs uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-100 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
                required
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                Nama Pejabat / SMC
              </label>
              <input
                type="text"
                value={namaSmc}
                onChange={(e) => setNamaSmc(e.target.value.toUpperCase())}
                className={`w-full text-xs uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-100 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
                required
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                Pangkat / Golongan
              </label>
              <input
                type="text"
                value={pangkatSmc}
                onChange={(e) => setPangkatSmc(e.target.value.toUpperCase())}
                className={`w-full text-xs uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-100 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
              />
            </div>

            <div>
              <label className={`block text-xs font-semibold mb-1 ${isDarkMode ? 'text-slate-300' : 'text-slate-700'}`}>
                Jabatan
              </label>
              <input
                type="text"
                value={jabatanSmc}
                onChange={(e) => setJabatanSmc(e.target.value.toUpperCase())}
                className={`w-full text-xs uppercase rounded-lg px-3 py-2 border transition-colors focus:outline-none focus:ring-2 ${accentColors.ring} ${
                  isDarkMode
                    ? 'bg-slate-800 text-slate-100 border-slate-700'
                    : 'bg-slate-50 text-slate-900 border-slate-300'
                }`}
              />
            </div>
          </div>

          <div className={`p-3 rounded-xl border flex items-center justify-between text-xs transition-colors ${
            isDarkMode ? 'bg-slate-850 border-slate-800 text-slate-400' : 'bg-slate-50 border-slate-200 text-slate-600'
          }`}>
            <div className="flex items-center gap-2">
              <ShieldCheck className={`w-4 h-4 ${accentColors.text} shrink-0`} />
              <span>
                <strong>Tanda Tangan Pengirim (SMC)</strong> otomatis digantikan dengan Barcode QR Digital berstempel resmi yang dapat di-scan langsung oleh siapapun untuk membuka dan memverifikasi keaslian naskah dokumen ini.
              </span>
            </div>
          </div>
        </div>

        {/* Bottom Action Footer */}
        <div className={`flex items-center justify-between pt-4 border-t ${
          isDarkMode ? 'border-slate-800' : 'border-slate-200'
        }`}>
          <button
            type="button"
            onClick={onCancel}
            className={`px-4 py-2 text-xs font-medium rounded-xl border transition-colors ${
              isDarkMode
                ? 'text-slate-400 hover:text-white bg-slate-800 hover:bg-slate-700 border-slate-700'
                : 'text-slate-600 hover:text-slate-900 bg-white hover:bg-slate-100 border-slate-300 shadow-sm'
            }`}
          >
            Batal
          </button>

          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setShowPreview(!showPreview)}
              className={`px-4 py-2 text-xs font-medium rounded-xl border transition-colors ${
                isDarkMode
                  ? 'text-slate-200 bg-slate-800 hover:bg-slate-700 border-slate-700'
                  : 'text-slate-700 bg-white hover:bg-slate-100 border-slate-300 shadow-sm'
              }`}
            >
              {showPreview ? 'Tutup Pratinjau' : 'Pratinjau Dokumen'}
            </button>

            <button
              type="submit"
              disabled={saving}
              className={`inline-flex items-center gap-2 px-6 py-2.5 text-xs font-bold text-white bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 rounded-xl shadow-lg transition-all hover:scale-[1.02] active:scale-[0.98] disabled:opacity-50`}
            >
              <Save className="w-4 h-4" />
              <span>{saving ? 'Menerbitkan Dokumen...' : 'Terbitkan Dokumen BRA SAR'}</span>
            </button>
          </div>
        </div>
      </form>
    </div>
  );
};
