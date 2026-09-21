import React, { useState, useEffect, useRef } from 'react';
import {
  Building2,
  MapPin,
  Plus,
  Edit2,
  Trash2,
  Save,
  Check,
  Search,
  RefreshCw,
  Star,
  StarOff,
  Navigation,
  Phone,
  Compass,
  X,
  Radio,
  Anchor,
} from 'lucide-react';
import { SarUnit, JenisSatuanSar } from '../types';
import { useTheme } from '../context/ThemeContext';
import { showSuccessAlert, showErrorAlert, showConfirmDialog, showToast } from '../utils/alerts';
import { decimalToDms, parseDmsToDecimal, DEFAULT_SAR_UNITS } from '../utils/geoUtils';
import { loadGoogleMaps } from '../utils/googleMapsLoader';

interface SarUnitsManagerProps {
  onUnitsUpdated?: () => void;
}

export const SarUnitsManager: React.FC<SarUnitsManagerProps> = ({ onUnitsUpdated }) => {
  const { isDarkMode, accentColors } = useTheme();

  const [units, setUnits] = useState<SarUnit[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');

  // Modal / Form state
  const [showModal, setShowModal] = useState(false);
  const [editingUnit, setEditingUnit] = useState<SarUnit | null>(null);

  // Form inputs
  const [namaUnit, setNamaUnit] = useState('');
  const [jenisUnit, setJenisUnit] = useState<JenisSatuanSar>('POS_SAR');
  const [wilayah, setWilayah] = useState('');
  const [latitude, setLatitude] = useState<number>(-4.017778);
  const [longitude, setLongitude] = useState<number>(122.569444);
  const [koordinatDms, setKoordinatDms] = useState('04° 01\' 04" S - 122° 34\' 10" E');
  const [alamat, setAlamat] = useState('');
  const [noTelp, setNoTelp] = useState('');
  const [isDefault, setIsDefault] = useState(false);
  const [isSaving, setIsSaving] = useState(false);

  // Mini map picker inside modal
  const modalMapRef = useRef<HTMLDivElement>(null);
  const modalMapInstance = useRef<google.maps.Map | null>(null);
  const modalMarkerInstance = useRef<google.maps.Marker | null>(null);

  const fetchUnits = async () => {
    try {
      setLoading(true);
      const res = await fetch('/api/sar-units');
      const json = await res.json();
      if (json.success && Array.isArray(json.data)) {
        setUnits(json.data);
      } else {
        setUnits(DEFAULT_SAR_UNITS);
      }
    } catch (err) {
      console.error('Failed to load SAR units:', err);
      setUnits(DEFAULT_SAR_UNITS);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUnits();
  }, []);

  // Open modal for add
  const handleOpenAdd = () => {
    setEditingUnit(null);
    setNamaUnit('');
    setJenisUnit('POS_SAR');
    setWilayah('');
    setLatitude(-4.017778);
    setLongitude(122.569444);
    setKoordinatDms(decimalToDms(-4.017778, 122.569444));
    setAlamat('');
    setNoTelp('');
    setIsDefault(false);
    setShowModal(true);
  };

  // Open modal for edit
  const handleOpenEdit = (unit: SarUnit) => {
    setEditingUnit(unit);
    setNamaUnit(unit.nama_unit);
    setJenisUnit(unit.jenis_unit);
    setWilayah(unit.wilayah);
    setLatitude(unit.latitude);
    setLongitude(unit.longitude);
    setKoordinatDms(unit.koordinat_dms || decimalToDms(unit.latitude, unit.longitude));
    setAlamat(unit.alamat || '');
    setNoTelp(unit.no_telp || '');
    setIsDefault(!!unit.is_default);
    setShowModal(true);
  };

  // Initialize mini map in modal
  useEffect(() => {
    if (!showModal) return;

    let isMounted = true;
    const initModalMap = async () => {
      try {
        let apiKey = 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc';
        try {
          const cfgRes = await fetch('/api/config/maps');
          const cfgData = await cfgRes.json();
          if (cfgData.success && cfgData.apiKey) {
            apiKey = cfgData.apiKey;
          }
        } catch {}

        await loadGoogleMaps(apiKey);

        if (!isMounted || !modalMapRef.current) return;

        const pos = { lat: latitude, lng: longitude };
        const map = new google.maps.Map(modalMapRef.current, {
          center: pos,
          zoom: 12,
          mapTypeId: google.maps.MapTypeId.ROADMAP,
          streetViewControl: false,
          mapTypeControl: false,
        });
        modalMapInstance.current = map;

        const marker = new google.maps.Marker({
          position: pos,
          map,
          draggable: true,
          title: 'Lokasi Pangkalan SAR',
        });
        modalMarkerInstance.current = marker;

        const updatePos = (lat: number, lng: number) => {
          setLatitude(lat);
          setLongitude(lng);
          setKoordinatDms(decimalToDms(lat, lng));
        };

        map.addListener('click', (e: google.maps.MapMouseEvent) => {
          if (e.latLng) {
            marker.setPosition(e.latLng);
            updatePos(e.latLng.lat(), e.latLng.lng());
          }
        });

        marker.addListener('dragend', (e: google.maps.MapMouseEvent) => {
          if (e.latLng) {
            updatePos(e.latLng.lat(), e.latLng.lng());
          }
        });
      } catch (e) {
        console.warn('Mini map load failed:', e);
      }
    };

    setTimeout(() => {
      initModalMap();
    }, 100);

    return () => {
      isMounted = false;
    };
  }, [showModal]);

  // Handle Lat/Lng manual input update
  const handleManualCoordsChange = (newLat: number, newLng: number) => {
    setLatitude(newLat);
    setLongitude(newLng);
    setKoordinatDms(decimalToDms(newLat, newLng));
    if (modalMapInstance.current && modalMarkerInstance.current) {
      const pos = new google.maps.LatLng(newLat, newLng);
      modalMapInstance.current.setCenter(pos);
      modalMarkerInstance.current.setPosition(pos);
    }
  };

  // Save Add/Edit
  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!namaUnit.trim() || !wilayah.trim()) {
      showErrorAlert('Validasi Gagal', 'Nama Unit dan Wilayah wajib diisi.');
      return;
    }

    setIsSaving(true);
    try {
      const payload = {
        nama_unit: namaUnit.trim(),
        jenis_unit: jenisUnit,
        wilayah: wilayah.trim(),
        latitude,
        longitude,
        koordinat_dms: koordinatDms,
        alamat: alamat.trim(),
        no_telp: noTelp.trim(),
        is_default: isDefault,
      };

      const url = editingUnit ? `/api/sar-units/${editingUnit.id_unit}` : '/api/sar-units';
      const method = editingUnit ? 'PUT' : 'POST';

      const res = await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });

      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Gagal menyimpan data pangkalan SAR');
      }

      await showSuccessAlert(
        editingUnit ? 'Pangkalan SAR Diperbarui' : 'Pangkalan SAR Ditambahkan',
        `Data "${payload.nama_unit}" berhasil disimpan ke sistem.`
      );

      setShowModal(false);
      fetchUnits();
      onUnitsUpdated?.();
    } catch (err: any) {
      showErrorAlert('Gagal Menyimpan', err.message);
    } finally {
      setIsSaving(false);
    }
  };

  // Delete Unit
  const handleDelete = async (unit: SarUnit) => {
    const confirmed = await showConfirmDialog(
      'Hapus Pangkalan SAR?',
      `Apakah Anda yakin ingin menghapus data "${unit.nama_unit}"? Tindakan ini tidak dapat dibatalkan.`
    );

    if (!confirmed) return;

    try {
      const res = await fetch(`/api/sar-units/${unit.id_unit}`, { method: 'DELETE' });
      const data = await res.json();
      if (!res.ok || !data.success) {
        throw new Error(data.error || 'Gagal menghapus');
      }
      showToast(`Pangkalan SAR "${unit.nama_unit}" berhasil dihapus`, 'success');
      fetchUnits();
      onUnitsUpdated?.();
    } catch (err: any) {
      showErrorAlert('Gagal Menghapus', err.message);
    }
  };

  // Set Default Unit
  const handleSetDefault = async (unit: SarUnit) => {
    try {
      const res = await fetch(`/api/sar-units/${unit.id_unit}/set-default`, { method: 'POST' });
      const data = await res.json();
      if (!res.ok || !data.success) throw new Error(data.error);
      showToast(`Pangkalan "${unit.nama_unit}" diset sebagai pangkalan utama default`, 'success');
      fetchUnits();
      onUnitsUpdated?.();
    } catch (err: any) {
      showErrorAlert('Gagal Set Default', err.message);
    }
  };

  const filteredUnits = units.filter(
    (u) =>
      u.nama_unit.toLowerCase().includes(searchQuery.toLowerCase()) ||
      u.wilayah.toLowerCase().includes(searchQuery.toLowerCase()) ||
      u.jenis_unit.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const cardBgClass = isDarkMode
    ? 'bg-slate-900 border-slate-800 text-slate-100'
    : 'bg-white border-slate-200 text-slate-900 shadow-md';

  const inputClass = `w-full px-3 py-2 text-xs rounded-xl border transition-colors outline-none focus:ring-2 ${accentColors.ring} ${
    isDarkMode
      ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
      : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
  }`;

  return (
    <div className="space-y-6">
      {/* Top Header & Action */}
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-bold uppercase tracking-wider text-slate-900 dark:text-white flex items-center gap-2">
            <Building2 className="w-5 h-5 text-orange-500" />
            <span>Master Lokasi & Koordinat Pangkalan SAR</span>
          </h2>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            Kelola data koordinat Kantor SAR, Pos SAR, Unit Siaga SAR, dan Kapal Negara untuk kalkulasi jarak rute jalan raya otomatis.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={fetchUnits}
            className={`p-2 rounded-xl border transition-colors ${
              isDarkMode
                ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                : 'bg-white hover:bg-slate-100 text-slate-700 border-slate-200 shadow-sm'
            }`}
            title="Muat Ulang Data"
          >
            <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          </button>

          <button
            type="button"
            onClick={handleOpenAdd}
            className={`inline-flex items-center gap-1.5 px-4 py-2 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-xl text-xs font-bold shadow-md transition-all hover:scale-[1.02]`}
          >
            <Plus className="w-4 h-4" />
            <span>Tambah Pangkalan SAR</span>
          </button>
        </div>
      </div>

      {/* Search Filter */}
      <div className="relative">
        <input
          type="text"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder="Cari nama pangkalan, wilayah, kabupaten..."
          className={inputClass}
        />
        <Search className="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" />
      </div>

      {/* Units Grid List */}
      {loading ? (
        <div className="py-12 text-center text-slate-400">
          <RefreshCw className="w-6 h-6 animate-spin mx-auto text-orange-500 mb-2" />
          <span className="text-xs">Memuat daftar pangkalan SAR...</span>
        </div>
      ) : filteredUnits.length === 0 ? (
        <div className="py-12 text-center text-slate-400">
          <p className="text-xs">Tidak ada data pangkalan SAR yang cocok dengan pencarian.</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredUnits.map((u) => {
            const isKantor = u.jenis_unit === 'KANTOR_SAR';
            const isPos = u.jenis_unit === 'POS_SAR';
            const isUnitSiaga = u.jenis_unit === 'UNIT_SIAGA';

            const badgeColor = isKantor
              ? 'bg-blue-500/15 text-blue-500 border-blue-500/30'
              : isPos
              ? 'bg-emerald-500/15 text-emerald-500 border-emerald-500/30'
              : isUnitSiaga
              ? 'bg-amber-500/15 text-amber-500 border-amber-500/30'
              : 'bg-purple-500/15 text-purple-500 border-purple-500/30';

            return (
              <div
                key={u.id_unit}
                className={`border rounded-2xl p-4 space-y-3 relative transition-all ${cardBgClass} ${
                  u.is_default ? 'ring-2 ring-orange-500/50' : ''
                }`}
              >
                {/* Header row */}
                <div className="flex items-start justify-between gap-2">
                  <div className="space-y-1">
                    <div className="flex items-center gap-1.5 flex-wrap">
                      <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${badgeColor}`}>
                        {isKantor ? '🏢 Kantor SAR' : isPos ? '🏠 Pos SAR' : isUnitSiaga ? '⚓ Unit Siaga' : '🚢 KN SAR'}
                      </span>
                      {u.is_default && (
                        <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-500 text-white flex items-center gap-1">
                          <Star className="w-2.5 h-2.5 fill-white" />
                          <span>Default Utama</span>
                        </span>
                      )}
                    </div>
                    <h3 className="text-sm font-bold text-slate-900 dark:text-white">{u.nama_unit}</h3>
                  </div>

                  {/* Actions */}
                  <div className="flex items-center gap-1">
                    <button
                      type="button"
                      onClick={() => handleSetDefault(u)}
                      title={u.is_default ? 'Pangkalan Utama' : 'Jadikan Pangkalan Utama'}
                      className={`p-1.5 rounded-lg border transition-colors ${
                        u.is_default
                          ? 'text-orange-500 border-orange-500/30 bg-orange-500/10'
                          : 'text-slate-400 hover:text-orange-500 border-transparent hover:border-slate-300 dark:hover:border-slate-700'
                      }`}
                    >
                      <Star className={`w-3.5 h-3.5 ${u.is_default ? 'fill-orange-500' : ''}`} />
                    </button>
                    <button
                      type="button"
                      onClick={() => handleOpenEdit(u)}
                      className="p-1.5 text-slate-400 hover:text-blue-500 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                      title="Edit Pangkalan"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      onClick={() => handleDelete(u)}
                      className="p-1.5 text-slate-400 hover:text-red-500 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
                      title="Hapus Pangkalan"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                {/* Info rows */}
                <div className="space-y-1.5 text-xs">
                  <div className="flex items-center gap-1.5 text-slate-500 dark:text-slate-400">
                    <MapPin className="w-3.5 h-3.5 text-orange-500 shrink-0" />
                    <span className="font-semibold text-slate-700 dark:text-slate-300">{u.wilayah}</span>
                  </div>

                  <div
                    className={`p-2 rounded-lg font-mono text-[11px] font-bold border ${
                      isDarkMode
                        ? 'bg-slate-950 border-slate-800 text-emerald-400'
                        : 'bg-emerald-50 border-emerald-200 text-emerald-800'
                    }`}
                  >
                    📍 {u.koordinat_dms || decimalToDms(u.latitude, u.longitude)}
                  </div>

                  {u.alamat && (
                    <p className="text-[11px] text-slate-400 line-clamp-2">{u.alamat}</p>
                  )}

                  {u.no_telp && (
                    <div className="flex items-center gap-1.5 text-[11px] text-slate-500">
                      <Phone className="w-3 h-3 text-slate-400" />
                      <span>{u.no_telp}</span>
                    </div>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Modal Add / Edit */}
      {showModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-150">
          <div
            className={`w-full max-w-2xl border rounded-2xl p-5 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto ${
              isDarkMode ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-900'
            }`}
          >
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
              <div className="flex items-center gap-2">
                <Building2 className="w-5 h-5 text-orange-500" />
                <h3 className="text-sm font-bold text-slate-900 dark:text-white">
                  {editingUnit ? 'Edit Data Pangkalan SAR' : 'Tambah Pangkalan SAR Baru'}
                </h3>
              </div>
              <button
                type="button"
                onClick={() => setShowModal(false)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1 rounded-lg"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSave} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold mb-1">
                    Nama Unit / Pangkalan <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={namaUnit}
                    onChange={(e) => setNamaUnit(e.target.value)}
                    placeholder="Contoh: Pos SAR Kolaka"
                    className={inputClass}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1">
                    Jenis Satuan <span className="text-red-500">*</span>
                  </label>
                  <select
                    value={jenisUnit}
                    onChange={(e) => setJenisUnit(e.target.value as JenisSatuanSar)}
                    className={inputClass}
                  >
                    <option value="KANTOR_SAR">Kantor Pencarian dan Pertolongan (Kansar)</option>
                    <option value="POS_SAR">Pos SAR</option>
                    <option value="UNIT_SIAGA">Unit Siaga SAR</option>
                    <option value="KN_SAR">Kapal Negara (KN / RB SAR)</option>
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold mb-1">
                    Wilayah Operasional / Kabupaten <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    required
                    value={wilayah}
                    onChange={(e) => setWilayah(e.target.value)}
                    placeholder="Contoh: Kabupaten Kolaka"
                    className={inputClass}
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold mb-1">No. Kontak / Emergency</label>
                  <input
                    type="text"
                    value={noTelp}
                    onChange={(e) => setNoTelp(e.target.value)}
                    placeholder="(0401) 3122115 / 0811-xxxx-xxxx"
                    className={inputClass}
                  />
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold mb-1">Alamat Lengkap</label>
                <input
                  type="text"
                  value={alamat}
                  onChange={(e) => setAlamat(e.target.value)}
                  placeholder="Jl. Dermaga Ferry, Latambaga, Kolaka..."
                  className={inputClass}
                />
              </div>

              {/* Map & Coordinates Picker */}
              <div className="space-y-2 pt-1">
                <div className="flex items-center justify-between">
                  <label className="block text-xs font-bold text-orange-500">
                    📍 Penentuan Titik Koordinat Peta Google:
                  </label>
                  <span className="text-[11px] font-mono font-bold text-emerald-500">
                    {koordinatDms}
                  </span>
                </div>

                <div className="w-full h-52 rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700">
                  <div ref={modalMapRef} className="w-full h-full bg-slate-950" />
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-[11px] text-slate-400">Latitude (Desimal)</label>
                    <input
                      type="number"
                      step="0.000001"
                      value={latitude}
                      onChange={(e) => handleManualCoordsChange(parseFloat(e.target.value) || 0, longitude)}
                      className={inputClass}
                    />
                  </div>
                  <div>
                    <label className="block text-[11px] text-slate-400">Longitude (Desimal)</label>
                    <input
                      type="number"
                      step="0.000001"
                      value={longitude}
                      onChange={(e) => handleManualCoordsChange(latitude, parseFloat(e.target.value) || 0)}
                      className={inputClass}
                    />
                  </div>
                </div>
              </div>

              {/* Default Checkbox */}
              <div className="flex items-center gap-2 pt-2">
                <input
                  type="checkbox"
                  id="is_default_check"
                  checked={isDefault}
                  onChange={(e) => setIsDefault(e.target.checked)}
                  className="rounded text-orange-500 focus:ring-orange-500 cursor-pointer"
                />
                <label htmlFor="is_default_check" className="text-xs font-semibold cursor-pointer">
                  Jadikan Pangkalan Ini Sebagai Default Asal Pergerakan Tim
                </label>
              </div>

              {/* Submit Buttons */}
              <div className="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                <button
                  type="button"
                  onClick={() => setShowModal(false)}
                  className={`px-4 py-2 rounded-xl text-xs font-semibold border ${
                    isDarkMode
                      ? 'bg-slate-800 hover:bg-slate-700 text-slate-300 border-slate-700'
                      : 'bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200'
                  }`}
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={isSaving}
                  className={`inline-flex items-center gap-1.5 px-5 py-2 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-xl text-xs font-bold shadow-md transition-all hover:scale-[1.02] disabled:opacity-50`}
                >
                  <Save className="w-4 h-4" />
                  <span>{isSaving ? 'Menyimpan...' : 'Simpan Pangkalan'}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
