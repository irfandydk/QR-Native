import React, { useState } from 'react';
import {
  Users,
  Ship,
  Plus,
  Trash2,
  ArrowUp,
  ArrowDown,
  Wrench,
  Shield,
  Layers,
} from 'lucide-react';
import { DetailUnsur, DetailAlut, MasterAlut, MasterTim } from '../types';
import { useTheme } from '../context/ThemeContext';

export interface UnsurItem {
  id_detail_unsur?: number;
  id_operasi?: number;
  nama_unsur: string;
  jumlah_personil: number;
}

export interface AlutItem {
  id_detail_alut?: number;
  id_operasi?: number;
  id_alut?: number;
  nama_alut: string;
  satuan: string;
  jumlah_alut: number;
}

interface UnsurAlutEditorProps {
  unsurList: UnsurItem[];
  onUnsurChange?: (list: any[]) => void;
  onChangeUnsur?: (list: any[]) => void;
  alutList: AlutItem[];
  onAlutChange?: (list: any[]) => void;
  onChangeAlut?: (list: any[]) => void;
  masterAlut?: MasterAlut[];
  masterTim?: MasterTim[];
  phaseLabel?: string;
  title?: string;
  description?: string;
  isCompact?: boolean;
}

export const UnsurAlutEditor: React.FC<UnsurAlutEditorProps> = ({
  unsurList,
  onUnsurChange,
  onChangeUnsur,
  alutList,
  onAlutChange,
  onChangeAlut,
  masterAlut = [],
  masterTim = [],
  phaseLabel = 'Fase Saat Ini',
  title,
  description,
  isCompact = false,
}) => {
  const { isDarkMode, accentColors } = useTheme();

  const emitUnsurChange = (list: any[]) => {
    if (onUnsurChange) onUnsurChange(list);
    if (onChangeUnsur) onChangeUnsur(list);
  };

  const emitAlutChange = (list: any[]) => {
    if (onAlutChange) onAlutChange(list);
    if (onChangeAlut) onChangeAlut(list);
  };

  // Quick preset inputs for new Unsur
  const [newUnsurNama, setNewUnsurNama] = useState('');
  const [newUnsurPersonel, setNewUnsurPersonel] = useState(3);

  // Quick preset inputs for new Alut
  const [newAlutNama, setNewAlutNama] = useState('');
  const [newAlutJumlah, setNewAlutJumlah] = useState(1);
  const [newAlutSatuan, setNewAlutSatuan] = useState('Unit');

  // --- UNSUR ACTIONS ---
  const handleAddUnsur = () => {
    if (!newUnsurNama.trim()) return;
    const newItem: UnsurItem = {
      id_detail_unsur: Date.now(),
      id_operasi: 0,
      nama_unsur: newUnsurNama.trim(),
      jumlah_personil: Number(newUnsurPersonel) || 1,
    };
    emitUnsurChange([...unsurList, newItem]);
    setNewUnsurNama('');
    setNewUnsurPersonel(3);
  };

  const handleRemoveUnsur = (index: number) => {
    const updated = [...unsurList];
    updated.splice(index, 1);
    emitUnsurChange(updated);
  };

  const handleMoveUnsur = (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= unsurList.length) return;
    const updated = [...unsurList];
    const [moved] = updated.splice(index, 1);
    updated.splice(targetIdx, 0, moved);
    emitUnsurChange(updated);
  };

  const handleUpdateUnsur = (index: number, field: keyof UnsurItem, val: any) => {
    const updated = [...unsurList];
    updated[index] = { ...updated[index], [field]: val };
    emitUnsurChange(updated);
  };

  // --- ALUT ACTIONS ---
  const handleAddAlut = () => {
    if (!newAlutNama.trim()) return;
    const matchedMaster = masterAlut.find(
      (m) => m.nama_alut.toLowerCase() === newAlutNama.trim().toLowerCase()
    );
    const newItem: AlutItem = {
      id_detail_alut: Date.now(),
      id_operasi: 0,
      id_alut: matchedMaster ? matchedMaster.id_alut : 0,
      nama_alut: newAlutNama.trim(),
      satuan: newAlutSatuan || 'Unit',
      jumlah_alut: Number(newAlutJumlah) || 1,
    };
    emitAlutChange([...alutList, newItem]);
    setNewAlutNama('');
    setNewAlutJumlah(1);
  };

  const handleRemoveAlut = (index: number) => {
    const updated = [...alutList];
    updated.splice(index, 1);
    emitAlutChange(updated);
  };

  const handleMoveAlut = (index: number, direction: 'up' | 'down') => {
    const targetIdx = direction === 'up' ? index - 1 : index + 1;
    if (targetIdx < 0 || targetIdx >= alutList.length) return;
    const updated = [...alutList];
    const [moved] = updated.splice(index, 1);
    updated.splice(targetIdx, 0, moved);
    emitAlutChange(updated);
  };

  const handleUpdateAlut = (index: number, field: keyof AlutItem, val: any) => {
    const updated = [...alutList];
    updated[index] = { ...updated[index], [field]: val };
    emitAlutChange(updated);
  };

  const totalPersonel = unsurList.reduce(
    (acc, u) => acc + (Number(u.jumlah_personil) || 0),
    0
  );
  const totalAlut = alutList.reduce(
    (acc, a) => acc + (Number(a.jumlah_alut) || 0),
    0
  );

  return (
    <div className="space-y-4">
      {title && (
        <div className="flex flex-col gap-0.5 pb-2 border-b border-slate-700/50">
          <h3 className={`text-sm font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
            {title}
          </h3>
          {description && (
            <p className="text-xs text-slate-400">{description}</p>
          )}
        </div>
      )}

      {/* Banner info kesinambungan */}
      <div className={`p-3 rounded-xl border flex items-center justify-between text-xs transition-colors ${
        isDarkMode
          ? 'bg-slate-900/80 border-slate-800 text-slate-300'
          : 'bg-orange-50/70 border-orange-200 text-orange-950'
      }`}>
        <div className="flex items-center gap-2">
          <Layers className="w-4 h-4 text-orange-500 shrink-0" />
          <span>
            <strong>Kesinambungan Data ({phaseLabel}):</strong> Total{' '}
            <strong className="text-orange-500">{unsurList.length} Unsur</strong> ({totalPersonel} Personel) &{' '}
            <strong className="text-amber-500">{alutList.length} Alut</strong> ({totalAlut} Unit/Set).
          </span>
        </div>
        <span className="text-[10px] text-slate-400 font-mono hidden sm:inline">
          Sinkron Otomatis Antar Fase
        </span>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {/* BAGIAN 1: UNSUR SAR TERLIBAT */}
        <div className={`p-4 rounded-xl border flex flex-col space-y-3 transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between pb-2 border-b border-slate-700/50">
            <div className="flex items-center gap-2">
              <Users className="w-4 h-4 text-blue-400" />
              <h4 className={`text-xs font-bold uppercase tracking-wider ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
                1. Unsur SAR Terlibat ({unsurList.length})
              </h4>
            </div>
            <span className="text-[11px] font-mono font-semibold px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">
              {totalPersonel} Personel
            </span>
          </div>

          {/* List Unsur with Reorder & Edit */}
          <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
            {unsurList.length === 0 ? (
              <p className="text-xs text-slate-500 text-center py-4 italic">
                Belum ada unsur SAR yang ditambahkan pada fase ini.
              </p>
            ) : (
              unsurList.map((unsur, idx) => (
                <div
                  key={unsur.id_detail_unsur || idx}
                  className={`flex items-center gap-2 p-2 rounded-lg border transition-all ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-800 hover:border-slate-700'
                      : 'bg-slate-50 border-slate-200 hover:border-slate-300'
                  }`}
                >
                  {/* Reorder Buttons */}
                  <div className="flex flex-col gap-0.5 shrink-0">
                    <button
                      type="button"
                      disabled={idx === 0}
                      onClick={() => handleMoveUnsur(idx, 'up')}
                      className="p-1 rounded text-slate-400 hover:text-orange-400 disabled:opacity-20 hover:bg-slate-800 transition-colors"
                      title="Geser Naik (Reorder)"
                    >
                      <ArrowUp className="w-3 h-3" />
                    </button>
                    <button
                      type="button"
                      disabled={idx === unsurList.length - 1}
                      onClick={() => handleMoveUnsur(idx, 'down')}
                      className="p-1 rounded text-slate-400 hover:text-orange-400 disabled:opacity-20 hover:bg-slate-800 transition-colors"
                      title="Geser Turun (Reorder)"
                    >
                      <ArrowDown className="w-3 h-3" />
                    </button>
                  </div>

                  <span className="text-[10px] font-mono text-slate-500 w-4 text-center">
                    {idx + 1}.
                  </span>

                  {/* Input Nama Unsur */}
                  <input
                    type="text"
                    value={unsur.nama_unsur}
                    onChange={(e) => handleUpdateUnsur(idx, 'nama_unsur', e.target.value)}
                    placeholder="Nama Instansi / Tim SAR"
                    className={`flex-1 text-xs px-2.5 py-1.5 rounded-lg border outline-none font-medium ${
                      isDarkMode
                        ? 'bg-slate-900 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />

                  {/* Input Jumlah Personel */}
                  <div className="flex items-center gap-1 w-24 shrink-0">
                    <input
                      type="number"
                      min={1}
                      value={unsur.jumlah_personil}
                      onChange={(e) =>
                        handleUpdateUnsur(idx, 'jumlah_personil', Math.max(1, parseInt(e.target.value) || 1))
                      }
                      className={`w-14 text-xs px-2 py-1.5 rounded-lg border text-center font-bold ${
                        isDarkMode
                          ? 'bg-slate-900 border-slate-700 text-blue-400'
                          : 'bg-white border-slate-300 text-blue-600'
                      }`}
                    />
                    <span className="text-[10px] text-slate-400">Org</span>
                  </div>

                  {/* Delete button */}
                  <button
                    type="button"
                    onClick={() => handleRemoveUnsur(idx)}
                    className="p-1.5 text-slate-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                    title="Hapus Unsur"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              ))
            )}
          </div>

          {/* Form Tambah Unsur */}
          <div className={`p-2.5 rounded-xl border space-y-2 ${
            isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-100/70 border-slate-200'
          }`}>
            <div className="text-[11px] font-semibold text-slate-400">
              + Tambah Unsur Baru pada Fase Ini:
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <div className="flex-1 min-w-[140px]">
                <input
                  type="text"
                  value={newUnsurNama}
                  onChange={(e) => setNewUnsurNama(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleAddUnsur())}
                  placeholder="Ketik nama unsur / potensi..."
                  list="unsur-suggestions"
                  className={`w-full text-xs px-2.5 py-1.5 rounded-lg border outline-none ${
                    isDarkMode
                      ? 'bg-slate-900 border-slate-700 text-white placeholder-slate-500'
                      : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                  }`}
                />
                <datalist id="unsur-suggestions">
                  {masterTim.map((t) => (
                    <option key={t.id_tim} value={t.nama_tim} />
                  ))}
                  <option value="Polairud" />
                  <option value="Pos TNI AL" />
                  <option value="BPBD Setempat" />
                  <option value="Masyarakat & Nelayan Setempat" />
                  <option value="Babinsa / Koramil" />
                  <option value="Bhabinkamtibmas / Polsek" />
                </datalist>
              </div>

              <div className="flex items-center gap-1 w-24 shrink-0">
                <input
                  type="number"
                  min={1}
                  value={newUnsurPersonel}
                  onChange={(e) => setNewUnsurPersonel(Math.max(1, parseInt(e.target.value) || 1))}
                  className={`w-14 text-xs px-2 py-1.5 rounded-lg border text-center font-bold ${
                    isDarkMode
                      ? 'bg-slate-900 border-slate-700 text-white'
                      : 'bg-white border-slate-300 text-slate-900'
                  }`}
                />
                <span className="text-[10px] text-slate-400">Org</span>
              </div>

              <button
                type="button"
                onClick={handleAddUnsur}
                disabled={!newUnsurNama.trim()}
                className="px-3 py-1.5 bg-blue-600 hover:bg-blue-500 disabled:opacity-40 text-white rounded-lg text-xs font-semibold flex items-center gap-1 transition-all"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>Tambah</span>
              </button>
            </div>
          </div>
        </div>

        {/* BAGIAN 2: ALUT SAR & PALSAR */}
        <div className={`p-4 rounded-xl border flex flex-col space-y-3 transition-colors ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200 shadow-sm'
        }`}>
          <div className="flex items-center justify-between pb-2 border-b border-slate-700/50">
            <div className="flex items-center gap-2">
              <Ship className="w-4 h-4 text-amber-400" />
              <h4 className={`text-xs font-bold uppercase tracking-wider ${isDarkMode ? 'text-slate-200' : 'text-slate-800'}`}>
                2. Alut & Peralatan SAR ({alutList.length})
              </h4>
            </div>
            <span className="text-[11px] font-mono font-semibold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20">
              {totalAlut} Sarana
            </span>
          </div>

          {/* List Alut with Reorder & Edit */}
          <div className="space-y-2 max-h-64 overflow-y-auto pr-1">
            {alutList.length === 0 ? (
              <p className="text-xs text-slate-500 text-center py-4 italic">
                Belum ada alut/sarana yang ditambahkan pada fase ini.
              </p>
            ) : (
              alutList.map((alut, idx) => (
                <div
                  key={alut.id_detail_alut || idx}
                  className={`flex items-center gap-2 p-2 rounded-lg border transition-all ${
                    isDarkMode
                      ? 'bg-slate-950 border-slate-800 hover:border-slate-700'
                      : 'bg-slate-50 border-slate-200 hover:border-slate-300'
                  }`}
                >
                  {/* Reorder Buttons */}
                  <div className="flex flex-col gap-0.5 shrink-0">
                    <button
                      type="button"
                      disabled={idx === 0}
                      onClick={() => handleMoveAlut(idx, 'up')}
                      className="p-1 rounded text-slate-400 hover:text-amber-400 disabled:opacity-20 hover:bg-slate-800 transition-colors"
                      title="Geser Naik (Reorder)"
                    >
                      <ArrowUp className="w-3 h-3" />
                    </button>
                    <button
                      type="button"
                      disabled={idx === alutList.length - 1}
                      onClick={() => handleMoveAlut(idx, 'down')}
                      className="p-1 rounded text-slate-400 hover:text-amber-400 disabled:opacity-20 hover:bg-slate-800 transition-colors"
                      title="Geser Turun (Reorder)"
                    >
                      <ArrowDown className="w-3 h-3" />
                    </button>
                  </div>

                  <span className="text-[10px] font-mono text-slate-500 w-4 text-center">
                    {idx + 1}.
                  </span>

                  {/* Input Nama Alut */}
                  <input
                    type="text"
                    value={alut.nama_alut}
                    onChange={(e) => handleUpdateAlut(idx, 'nama_alut', e.target.value)}
                    placeholder="Nama Alut / Palsar"
                    className={`flex-1 text-xs px-2.5 py-1.5 rounded-lg border outline-none font-medium ${
                      isDarkMode
                        ? 'bg-slate-900 border-slate-700 text-white'
                        : 'bg-white border-slate-300 text-slate-900'
                    }`}
                  />

                  {/* Input Jumlah & Satuan */}
                  <div className="flex items-center gap-1 w-28 shrink-0">
                    <input
                      type="number"
                      min={1}
                      value={alut.jumlah_alut}
                      onChange={(e) =>
                        handleUpdateAlut(idx, 'jumlah_alut', Math.max(1, parseInt(e.target.value) || 1))
                      }
                      className={`w-12 text-xs px-1.5 py-1.5 rounded-lg border text-center font-bold ${
                        isDarkMode
                          ? 'bg-slate-900 border-slate-700 text-amber-400'
                          : 'bg-white border-slate-300 text-amber-600'
                      }`}
                    />
                    <select
                      value={alut.satuan}
                      onChange={(e) => handleUpdateAlut(idx, 'satuan', e.target.value)}
                      className={`text-[10px] px-1.5 py-1.5 rounded-lg border font-medium ${
                        isDarkMode
                          ? 'bg-slate-900 border-slate-700 text-slate-300'
                          : 'bg-white border-slate-300 text-slate-700'
                      }`}
                    >
                      <option value="Unit">Unit</option>
                      <option value="Set">Set</option>
                      <option value="Buah">Buah</option>
                      <option value="Lembar">Lembar</option>
                      <option value="Paket">Paket</option>
                    </select>
                  </div>

                  {/* Delete button */}
                  <button
                    type="button"
                    onClick={() => handleRemoveAlut(idx)}
                    className="p-1.5 text-slate-500 hover:text-red-400 hover:bg-red-500/10 rounded-lg transition-colors"
                    title="Hapus Alut"
                  >
                    <Trash2 className="w-3.5 h-3.5" />
                  </button>
                </div>
              ))
            )}
          </div>

          {/* Form Tambah Alut */}
          <div className={`p-2.5 rounded-xl border space-y-2 ${
            isDarkMode ? 'bg-slate-950/60 border-slate-800' : 'bg-slate-100/70 border-slate-200'
          }`}>
            <div className="text-[11px] font-semibold text-slate-400">
              + Tambah Alut/Palsar Baru pada Fase Ini:
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <div className="flex-1 min-w-[140px]">
                <input
                  type="text"
                  value={newAlutNama}
                  onChange={(e) => setNewAlutNama(e.target.value)}
                  onKeyDown={(e) => e.key === 'Enter' && (e.preventDefault(), handleAddAlut())}
                  placeholder="Pilih atau ketik alut..."
                  list="alut-suggestions"
                  className={`w-full text-xs px-2.5 py-1.5 rounded-lg border outline-none ${
                    isDarkMode
                      ? 'bg-slate-900 border-slate-700 text-white placeholder-slate-500'
                      : 'bg-white border-slate-300 text-slate-900 placeholder-slate-400'
                  }`}
                />
                <datalist id="alut-suggestions">
                  {masterAlut.map((a) => (
                    <option key={a.id_alut} value={a.nama_alut} />
                  ))}
                </datalist>
              </div>

              <div className="flex items-center gap-1 w-28 shrink-0">
                <input
                  type="number"
                  min={1}
                  value={newAlutJumlah}
                  onChange={(e) => setNewAlutJumlah(Math.max(1, parseInt(e.target.value) || 1))}
                  className={`w-12 text-xs px-1.5 py-1.5 rounded-lg border text-center font-bold ${
                    isDarkMode
                      ? 'bg-slate-900 border-slate-700 text-white'
                      : 'bg-white border-slate-300 text-slate-900'
                  }`}
                />
                <select
                  value={newAlutSatuan}
                  onChange={(e) => setNewAlutSatuan(e.target.value)}
                  className={`text-[10px] px-1.5 py-1.5 rounded-lg border font-medium ${
                    isDarkMode
                      ? 'bg-slate-900 border-slate-700 text-slate-300'
                      : 'bg-white border-slate-300 text-slate-700'
                  }`}
                >
                  <option value="Unit">Unit</option>
                  <option value="Set">Set</option>
                  <option value="Buah">Buah</option>
                  <option value="Lembar">Lembar</option>
                  <option value="Paket">Paket</option>
                </select>
              </div>

              <button
                type="button"
                onClick={handleAddAlut}
                disabled={!newAlutNama.trim()}
                className="px-3 py-1.5 bg-amber-600 hover:bg-amber-500 disabled:opacity-40 text-white rounded-lg text-xs font-semibold flex items-center gap-1 transition-all"
              >
                <Plus className="w-3.5 h-3.5" />
                <span>Tambah</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
