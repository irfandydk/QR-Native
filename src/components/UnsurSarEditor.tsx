import React, { useState } from 'react';
import {
  Users,
  Plus,
  Trash2,
  ArrowUp,
  ArrowDown,
  Building2,
  Sparkles,
} from 'lucide-react';
import { DetailUnsur, MasterTim } from '../types';
import { useTheme } from '../context/ThemeContext';

export interface UnsurItem {
  id_detail_unsur?: number;
  id_operasi?: number;
  nama_unsur: string;
  jumlah_personil: number;
}

interface UnsurSarEditorProps {
  unsurList: UnsurItem[];
  onChangeUnsur: (list: UnsurItem[]) => void;
  masterTim?: MasterTim[];
  title?: string;
  description?: string;
  phaseLabel?: string;
}

const COMMON_UNSUR_PRESETS = [
  'Tim Rescue Pos SAR',
  'Polairud Polda / Polres',
  'TNI AL / Posmat',
  'BPBD Kab/Kota',
  'Damkar & Penyelamatan',
  'Polsek & Koramil Setempat',
  'Masyarakat & Nelayan Setempat',
  'Keluarga Korban',
  'Dinas Kesehatan / Puskesmas',
  'Bhayangkara SAR',
  'Pramuka Peduli',
];

export const UnsurSarEditor: React.FC<UnsurSarEditorProps> = ({
  unsurList,
  onChangeUnsur,
  masterTim = [],
  title = 'Unsur SAR Terlibat (Instansi & Personel)',
  description = 'Daftar instansi potensi SAR gabungan dan jumlah personel yang dikerahkan',
  phaseLabel,
}) => {
  const { isDarkMode, accentColors } = useTheme();

  const [namaUnsur, setNamaUnsur] = useState('');
  const [personel, setPersonel] = useState(3);

  const handleAdd = () => {
    if (!namaUnsur.trim()) return;
    const newItem: UnsurItem = {
      id_detail_unsur: Date.now(),
      id_operasi: 0,
      nama_unsur: namaUnsur.trim(),
      jumlah_personil: Number(personel) || 1,
    };
    onChangeUnsur([...unsurList, newItem]);
    setNamaUnsur('');
    setPersonel(3);
  };

  const handleRemove = (index: number) => {
    const updated = [...unsurList];
    updated.splice(index, 1);
    onChangeUnsur(updated);
  };

  const handleMove = (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= unsurList.length) return;
    const updated = [...unsurList];
    const [moved] = updated.splice(index, 1);
    updated.splice(targetIdx, 0, moved);
    onChangeUnsur(updated);
  };

  const handleUpdate = (index: number, field: keyof UnsurItem, val: any) => {
    const updated = [...unsurList];
    updated[index] = { ...updated[index], [field]: val };
    onChangeUnsur(updated);
  };

  const handleAddPreset = (name: string) => {
    if (unsurList.some((u) => u.nama_unsur.toLowerCase() === name.toLowerCase())) {
      return;
    }
    const newItem: UnsurItem = {
      id_detail_unsur: Date.now(),
      id_operasi: 0,
      nama_unsur: name,
      jumlah_personil: name.includes('Pos SAR') || name.includes('Kansar') ? 6 : 4,
    };
    onChangeUnsur([...unsurList, newItem]);
  };

  const totalPersonel = unsurList.reduce(
    (acc, u) => acc + (Number(u.jumlah_personil) || 0),
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
          <div className="p-2 bg-blue-500/10 text-blue-400 rounded-xl border border-blue-500/20 shrink-0">
            <Users className="w-5 h-5" />
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
          <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-500/15 text-blue-500 dark:text-blue-300 border border-blue-500/30">
            {unsurList.length} Instansi
          </span>
          <span className="px-2.5 py-1 text-xs font-bold rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 font-mono">
            Total {totalPersonel} Personel
          </span>
        </div>
      </div>

      {/* Quick Add Preset Buttons */}
      <div>
        <label className="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1.5">
          + Klik Cepat Tambah Potensi SAR:
        </label>
        <div className="flex flex-wrap gap-1.5">
          {COMMON_UNSUR_PRESETS.map((preset) => {
            const isAdded = unsurList.some(
              (u) => u.nama_unsur.toLowerCase() === preset.toLowerCase()
            );
            return (
              <button
                key={preset}
                type="button"
                onClick={() => handleAddPreset(preset)}
                disabled={isAdded}
                className={`px-2.5 py-1 rounded-lg text-xs font-medium border transition-all ${
                  isAdded
                    ? 'bg-blue-500/15 text-blue-400 border-blue-500/30 opacity-60 cursor-default'
                    : isDarkMode
                    ? 'bg-slate-950 border-slate-700/80 text-slate-300 hover:bg-slate-800 hover:border-slate-600'
                    : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100 hover:border-slate-300 shadow-sm'
                }`}
              >
                {isAdded ? '✓ ' : '+ '}
                {preset}
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
          Tambah Instansi / Unsur Baru:
        </label>
        <div className="grid grid-cols-1 sm:grid-cols-12 gap-2">
          <div className="sm:col-span-8">
            <input
              type="text"
              value={namaUnsur}
              onChange={(e) => setNamaUnsur(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  handleAdd();
                }
              }}
              placeholder="Ketik nama instansi (e.g. Polairud Bau-Bau, TNI AL, BPBD)..."
              className={`w-full px-3 py-2 text-xs rounded-lg border focus:outline-none focus:border-blue-500 ${
                isDarkMode
                  ? 'bg-slate-900 border-slate-700 text-white placeholder-slate-500'
                  : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
              }`}
            />
          </div>

          <div className="sm:col-span-2">
            <div className="relative">
              <input
                type="number"
                min="1"
                max="999"
                value={personel}
                onChange={(e) => setPersonel(Math.max(1, parseInt(e.target.value, 10) || 1))}
                placeholder="Jumlah"
                className={`w-full px-3 py-2 text-xs rounded-lg border text-center font-bold focus:outline-none focus:border-blue-500 ${
                  isDarkMode
                    ? 'bg-slate-900 border-slate-700 text-white'
                    : 'bg-white border-slate-300 text-slate-900'
                }`}
              />
              <span className="absolute right-2.5 top-2 text-[10px] text-slate-400 pointer-events-none">
                Org
              </span>
            </div>
          </div>

          <div className="sm:col-span-2">
            <button
              type="button"
              onClick={handleAdd}
              disabled={!namaUnsur.trim()}
              className="w-full h-full py-2 px-3 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white rounded-lg text-xs font-bold flex items-center justify-center gap-1.5 shadow transition-all"
            >
              <Plus className="w-3.5 h-3.5" />
              <span>Tambah</span>
            </button>
          </div>
        </div>
      </div>

      {/* Table / List of Added Unsur */}
      <div className="space-y-2">
        {unsurList.length === 0 ? (
          <div
            className={`p-6 text-center rounded-xl border border-dashed text-xs ${
              isDarkMode
                ? 'border-slate-800 text-slate-500'
                : 'border-slate-300 text-slate-400'
            }`}
          >
            Belum ada unsur SAR yang ditambahkan. Gunakan tombol preset di atas atau input nama instansi secara manual.
          </div>
        ) : (
          <div className="space-y-2">
            {unsurList.map((item, idx) => (
              <div
                key={idx}
                className={`p-3 rounded-xl border flex items-center justify-between gap-3 transition-colors ${
                  isDarkMode
                    ? 'bg-slate-950/70 border-slate-800 hover:border-slate-700'
                    : 'bg-white border-slate-200 hover:border-slate-300 shadow-sm'
                }`}
              >
                <div className="flex items-center gap-2.5 flex-1 min-w-0">
                  <span className="w-6 h-6 rounded-full bg-blue-500/20 text-blue-400 font-bold text-xs flex items-center justify-center shrink-0">
                    {idx + 1}
                  </span>
                  <input
                    type="text"
                    value={item.nama_unsur}
                    onChange={(e) => handleUpdate(idx, 'nama_unsur', e.target.value)}
                    className={`flex-1 text-xs font-medium px-2 py-1 rounded border border-transparent hover:border-slate-600 focus:border-blue-500 focus:outline-none ${
                      isDarkMode ? 'bg-transparent text-slate-100' : 'bg-transparent text-slate-900'
                    }`}
                  />
                </div>

                <div className="flex items-center gap-2 shrink-0">
                  <div className="flex items-center gap-1">
                    <input
                      type="number"
                      min="1"
                      value={item.jumlah_personil}
                      onChange={(e) =>
                        handleUpdate(
                          idx,
                          'jumlah_personil',
                          Math.max(1, parseInt(e.target.value, 10) || 1)
                        )
                      }
                      className={`w-14 px-2 py-1 text-xs font-bold rounded border text-center focus:outline-none focus:border-blue-500 ${
                        isDarkMode
                          ? 'bg-slate-900 border-slate-700 text-emerald-400'
                          : 'bg-slate-100 border-slate-300 text-emerald-700'
                      }`}
                    />
                    <span className="text-[11px] text-slate-400">Org</span>
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
                      disabled={idx === unsurList.length - 1}
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
                      title="Hapus Unsur"
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
