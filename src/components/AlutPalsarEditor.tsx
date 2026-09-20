import React, { useState } from 'react';
import {
  Ship,
  Plus,
  Trash2,
  ArrowUp,
  ArrowDown,
  Wrench,
  Layers,
} from 'lucide-react';
import { DetailAlut, MasterAlut } from '../types';
import { useTheme } from '../context/ThemeContext';

export interface AlutItem {
  id_detail_alut?: number;
  id_operasi?: number;
  id_alut?: number;
  nama_alut: string;
  satuan: string;
  jumlah_alut: number;
}

interface AlutPalsarEditorProps {
  alutList: AlutItem[];
  onChangeAlut: (list: AlutItem[]) => void;
  masterAlut?: MasterAlut[];
  title?: string;
  description?: string;
  phaseLabel?: string;
}

const COMMON_ALUT_PRESETS = [
  { nama: 'Rescue Car', satuan: 'Unit', jumlah: 1 },
  { nama: 'Rubber Boat + Mopel', satuan: 'Unit', jumlah: 1 },
  { nama: 'RIB (Rigid Inflatable Boat)', satuan: 'Unit', jumlah: 1 },
  { nama: 'Aqua Eye (Sonar Scanner)', satuan: 'Unit', jumlah: 1 },
  { nama: 'Underwater Search Device (UWSD)', satuan: 'Unit', jumlah: 1 },
  { nama: 'Peralatan Selam (Diving Gear)', satuan: 'Set', jumlah: 2 },
  { nama: 'Palsar Medis (First Aid Kit)', satuan: 'Set', jumlah: 1 },
  { nama: 'Palsar Komunikasi & Alkom', satuan: 'Set', jumlah: 1 },
  { nama: 'Palsar Evakuasi & Tandu', satuan: 'Set', jumlah: 1 },
  { nama: 'Longboat / Perahu Masyarakat', satuan: 'Unit', jumlah: 2 },
  { nama: 'Drone Thermal / Surveillance', satuan: 'Unit', jumlah: 1 },
];

export const AlutPalsarEditor: React.FC<AlutPalsarEditorProps> = ({
  alutList,
  onChangeAlut,
  masterAlut = [],
  title = 'Alut & Palsar (Armada & Peralatan Khusus)',
  description = 'Daftar armada kapal, kendaraan darat, dan peralatan SAR yang dikerahkan',
  phaseLabel,
}) => {
  const { isDarkMode } = useTheme();

  const [namaAlut, setNamaAlut] = useState('');
  const [jumlah, setJumlah] = useState(1);
  const [satuan, setSatuan] = useState('Unit');

  const handleAdd = () => {
    if (!namaAlut.trim()) return;
    const matchedMaster = masterAlut.find(
      (m) => m.nama_alut.toLowerCase() === namaAlut.trim().toLowerCase()
    );
    const newItem: AlutItem = {
      id_detail_alut: Date.now(),
      id_operasi: 0,
      id_alut: matchedMaster ? matchedMaster.id_alut : 0,
      nama_alut: namaAlut.trim(),
      satuan: satuan || 'Unit',
      jumlah_alut: Number(jumlah) || 1,
    };
    onChangeAlut([...alutList, newItem]);
    setNamaAlut('');
    setJumlah(1);
  };

  const handleRemove = (index: number) => {
    const updated = [...alutList];
    updated.splice(index, 1);
    onChangeAlut(updated);
  };

  const handleMove = (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= alutList.length) return;
    const updated = [...alutList];
    const [moved] = updated.splice(index, 1);
    updated.splice(targetIdx, 0, moved);
    onChangeAlut(updated);
  };

  const handleUpdate = (index: number, field: keyof AlutItem, val: any) => {
    const updated = [...alutList];
    updated[index] = { ...updated[index], [field]: val };
    onChangeAlut(updated);
  };

  const handleAddPreset = (preset: { nama: string; satuan: string; jumlah: number }) => {
    if (alutList.some((a) => a.nama_alut.toLowerCase() === preset.nama.toLowerCase())) {
      return;
    }
    const matchedMaster = masterAlut.find(
      (m) => m.nama_alut.toLowerCase() === preset.nama.toLowerCase()
    );
    const newItem: AlutItem = {
      id_detail_alut: Date.now(),
      id_operasi: 0,
      id_alut: matchedMaster ? matchedMaster.id_alut : 0,
      nama_alut: preset.nama,
      satuan: preset.satuan,
      jumlah_alut: preset.jumlah,
    };
    onChangeAlut([...alutList, newItem]);
  };

  const totalAlut = alutList.reduce(
    (acc, a) => acc + (Number(a.jumlah_alut) || 0),
    0
  );

  return (
    <div
      className={`rounded-2xl p-5 border shadow-xl transition-colors space-y-4 ${
        isDarkMode
          ? 'bg-slate-900/90 border-slate-800'
          : 'bg-white border-slate-200 shadow-sm'
      }`}
    >
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-slate-700/50 gap-2">
        <div className="flex items-center gap-2.5">
          <div className="p-2 bg-amber-500/10 text-amber-400 rounded-xl border border-amber-500/20 shrink-0">
            <Ship className="w-5 h-5" />
          </div>
          <div>
            <h2 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
              {title}
            </h2>
            <p className="text-[11px] text-slate-400">
              {description}
            </p>
          </div>
        </div>

        {/* Counter Badge */}
        <div className="flex items-center gap-2 self-start sm:self-auto">
          <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
            {alutList.length} Item
          </span>
          <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-mono">
            Total {totalAlut} Armada/Set
          </span>
        </div>
      </div>

      {/* Quick Add Preset Buttons */}
      <div>
        <label className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          + Klik Cepat Tambah Alut / Peralatan:
        </label>
        <div className="flex flex-wrap gap-1.5">
          {COMMON_ALUT_PRESETS.map((preset) => {
            const isAdded = alutList.some(
              (a) => a.nama_alut.toLowerCase() === preset.nama.toLowerCase()
            );
            return (
              <button
                key={preset.nama}
                type="button"
                onClick={() => handleAddPreset(preset)}
                disabled={isAdded}
                className={`px-2.5 py-1 rounded-lg text-xs font-medium border transition-all ${
                  isAdded
                    ? 'bg-amber-500/15 text-amber-400 border-amber-500/30 opacity-60 cursor-default'
                    : isDarkMode
                    ? 'bg-slate-950 border-slate-700/80 text-slate-300 hover:bg-slate-800 hover:border-slate-600'
                    : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100 hover:border-slate-300 shadow-sm'
                }`}
              >
                {isAdded ? '✓ ' : '+ '}
                {preset.nama}
              </button>
            );
          })}
        </div>
      </div>

      {/* Manual Input Form */}
      <div
        className={`p-3.5 rounded-xl border space-y-2.5 ${
          isDarkMode
            ? 'bg-slate-950/80 border-slate-800'
            : 'bg-slate-50 border-slate-200'
        }`}
      >
        <label className="block text-xs font-semibold text-slate-400">
          Tambah Alut / Peralatan Baru:
        </label>
        <div className="grid grid-cols-1 sm:grid-cols-12 gap-2">
          <div className="sm:col-span-7">
            <input
              type="text"
              value={namaAlut}
              onChange={(e) => setNamaAlut(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  handleAdd();
                }
              }}
              placeholder="Ketik nama alut (e.g. RIB 01, Aqua Eye, Mobil Rescue)..."
              className={`w-full px-3 py-2 text-xs rounded-lg border focus:outline-none focus:border-amber-500 ${
                isDarkMode
                  ? 'bg-slate-900 border-slate-700 text-white placeholder-slate-500'
                  : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
              }`}
            />
          </div>

          <div className="sm:col-span-2">
            <input
              type="number"
              min="1"
              max="999"
              value={jumlah}
              onChange={(e) => setJumlah(Math.max(1, parseInt(e.target.value, 10) || 1))}
              placeholder="Qty"
              className={`w-full px-3 py-2 text-xs rounded-lg border text-center font-bold focus:outline-none focus:border-amber-500 ${
                isDarkMode
                  ? 'bg-slate-900 border-slate-700 text-white'
                  : 'bg-white border-slate-300 text-slate-900'
              }`}
            />
          </div>

          <div className="sm:col-span-2">
            <select
              value={satuan}
              onChange={(e) => setSatuan(e.target.value)}
              className={`w-full px-2 py-2 text-xs rounded-lg border focus:outline-none focus:border-amber-500 ${
                isDarkMode
                  ? 'bg-slate-900 border-slate-700 text-white'
                  : 'bg-white border-slate-300 text-slate-900'
              }`}
            >
              <option value="Unit">Unit</option>
              <option value="Set">Set</option>
              <option value="Buah">Buah</option>
              <option value="Paket">Paket</option>
            </select>
          </div>

          <div className="sm:col-span-1">
            <button
              type="button"
              onClick={handleAdd}
              disabled={!namaAlut.trim()}
              className="w-full h-full py-2 px-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 text-white rounded-lg text-xs font-bold flex items-center justify-center gap-1 shadow transition-all"
            >
              <Plus className="w-3.5 h-3.5" />
            </button>
          </div>
        </div>
      </div>

      {/* Table / List of Added Alut */}
      <div className="space-y-2">
        {alutList.length === 0 ? (
          <div
            className={`p-6 text-center rounded-xl border border-dashed text-xs ${
              isDarkMode
                ? 'border-slate-800 text-slate-500'
                : 'border-slate-300 text-slate-400'
            }`}
          >
            Belum ada armada atau peralatan yang ditambahkan. Gunakan tombol preset di atas atau input manual.
          </div>
        ) : (
          <div className="space-y-2">
            {alutList.map((item, idx) => (
              <div
                key={idx}
                className={`p-3 rounded-xl border flex items-center justify-between gap-3 transition-colors ${
                  isDarkMode
                    ? 'bg-slate-950/70 border-slate-800 hover:border-slate-700'
                    : 'bg-white border-slate-200 hover:border-slate-300 shadow-sm'
                }`}
              >
                <div className="flex items-center gap-2.5 flex-1 min-w-0">
                  <span className="w-6 h-6 rounded-full bg-amber-500/20 text-amber-400 font-bold text-xs flex items-center justify-center shrink-0">
                    {idx + 1}
                  </span>
                  <input
                    type="text"
                    value={item.nama_alut}
                    onChange={(e) => handleUpdate(idx, 'nama_alut', e.target.value)}
                    className={`flex-1 text-xs font-medium px-2 py-1 rounded border border-transparent hover:border-slate-600 focus:border-amber-500 focus:outline-none ${
                      isDarkMode ? 'bg-transparent text-slate-100' : 'bg-transparent text-slate-900'
                    }`}
                  />
                </div>

                <div className="flex items-center gap-2 shrink-0">
                  <div className="flex items-center gap-1">
                    <input
                      type="number"
                      min="1"
                      value={item.jumlah_alut}
                      onChange={(e) =>
                        handleUpdate(
                          idx,
                          'jumlah_alut',
                          Math.max(1, parseInt(e.target.value, 10) || 1)
                        )
                      }
                      className={`w-12 px-1.5 py-1 text-xs font-bold rounded border text-center focus:outline-none focus:border-amber-500 ${
                        isDarkMode
                          ? 'bg-slate-900 border-slate-700 text-amber-400'
                          : 'bg-slate-100 border-slate-300 text-amber-700'
                      }`}
                    />
                    <select
                      value={item.satuan}
                      onChange={(e) => handleUpdate(idx, 'satuan', e.target.value)}
                      className={`px-1.5 py-1 text-xs rounded border focus:outline-none ${
                        isDarkMode
                          ? 'bg-slate-900 border-slate-700 text-slate-300'
                          : 'bg-slate-100 border-slate-300 text-slate-700'
                      }`}
                    >
                      <option value="Unit">Unit</option>
                      <option value="Set">Set</option>
                      <option value="Buah">Buah</option>
                      <option value="Paket">Paket</option>
                    </select>
                  </div>

                  <div className="flex items-center gap-0.5 border-l border-slate-700/50 pl-2">
                    <button
                      type="button"
                      disabled={idx === 0}
                      onClick={() => handleMove(idx, 'up')}
                      className="p-1 text-slate-400 hover:text-white disabled:opacity-30"
                      title="Geser ke Atas"
                    >
                      <ArrowUp className="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      disabled={idx === alutList.length - 1}
                      onClick={() => handleMove(idx, 'down')}
                      className="p-1 text-slate-400 hover:text-white disabled:opacity-30"
                      title="Geser ke Bawah"
                    >
                      <ArrowDown className="w-3.5 h-3.5" />
                    </button>
                    <button
                      type="button"
                      onClick={() => handleRemove(idx)}
                      className="p-1 text-slate-400 hover:text-red-400"
                      title="Hapus Alut"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
};
