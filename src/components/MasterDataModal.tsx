import React, { useState } from 'react';
import { X, Plus, Anchor, Users, Trash2 } from 'lucide-react';
import { MasterAlut, MasterTim } from '../types';
import { showSuccessAlert, showErrorAlert } from '../utils/alerts';

interface MasterDataModalProps {
  isOpen: boolean;
  onClose: () => void;
  masterAlut: MasterAlut[];
  masterTim: MasterTim[];
  onAddAlut: (nama_alut: string, satuan: string) => Promise<void>;
  onAddTim: (nama_tim: string) => Promise<void>;
}

export const MasterDataModal: React.FC<MasterDataModalProps> = ({
  isOpen,
  onClose,
  masterAlut,
  masterTim,
  onAddAlut,
  onAddTim,
}) => {
  const [activeTab, setActiveTab] = useState<'alut' | 'tim'>('alut');
  const [newAlutNama, setNewAlutNama] = useState('');
  const [newAlutSatuan, setNewAlutSatuan] = useState('Unit');
  const [newTimNama, setNewTimNama] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errorMsg, setErrorMsg] = useState('');

  if (!isOpen) return null;

  const handleCreateAlut = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newAlutNama.trim()) return;
    setIsSubmitting(true);
    setErrorMsg('');
    try {
      const name = newAlutNama.trim();
      const unit = newAlutSatuan.trim() || 'Unit';
      await onAddAlut(name, unit);
      setNewAlutNama('');
      setNewAlutSatuan('Unit');
      await showSuccessAlert('Data Alut Ditambahkan', `Alut "${name}" (${unit}) berhasil ditambahkan ke database.`);
    } catch (err: any) {
      const msg = err.message || 'Gagal menambahkan alut';
      setErrorMsg(msg);
      showErrorAlert('Gagal Menambah Alut', msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleCreateTim = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newTimNama.trim()) return;
    setIsSubmitting(true);
    setErrorMsg('');
    try {
      const name = newTimNama.trim();
      await onAddTim(name);
      setNewTimNama('');
      await showSuccessAlert('Data Tim Ditambahkan', `Tim "${name}" berhasil ditambahkan ke database.`);
    } catch (err: any) {
      const msg = err.message || 'Gagal menambahkan tim';
      setErrorMsg(msg);
      showErrorAlert('Gagal Menambah Tim', msg);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="relative w-full max-w-2xl max-h-[90vh] flex flex-col bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 bg-slate-800/90 border-b border-slate-700">
          <div>
            <h3 className="text-base font-bold text-white">Master Data Operasional</h3>
            <p className="text-xs text-slate-400">Database Alut & Palsar serta Tim Rescue KPP Kendari</p>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Tab switch */}
        <div className="flex border-b border-slate-800 bg-slate-950/40 px-5 pt-2">
          <button
            onClick={() => setActiveTab('alut')}
            className={`flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors ${
              activeTab === 'alut'
                ? 'border-orange-500 text-orange-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Anchor className="w-4 h-4" />
            <span>Master Alut & Palsar ({masterAlut.length})</span>
          </button>

          <button
            onClick={() => setActiveTab('tim')}
            className={`flex items-center gap-2 px-4 py-2.5 text-xs font-semibold border-b-2 transition-colors ${
              activeTab === 'tim'
                ? 'border-orange-500 text-orange-400'
                : 'border-transparent text-slate-400 hover:text-slate-200'
            }`}
          >
            <Users className="w-4 h-4" />
            <span>Master Tim Rescue ({masterTim.length})</span>
          </button>
        </div>

        {errorMsg && (
          <div className="px-5 py-2 bg-red-900/40 border-b border-red-800 text-red-300 text-xs">
            {errorMsg}
          </div>
        )}

        {/* Tab Content */}
        <div className="p-5 flex-1 overflow-y-auto space-y-4">
          {activeTab === 'alut' ? (
            <>
              {/* Form Add Alut */}
              <form onSubmit={handleCreateAlut} className="flex flex-col sm:flex-row gap-2">
                <input
                  type="text"
                  placeholder="Nama Alut/Palsar (mis: Drone Thermal, RB 307)..."
                  value={newAlutNama}
                  onChange={(e) => setNewAlutNama(e.target.value)}
                  className="flex-1 px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-orange-500"
                />
                <select
                  value={newAlutSatuan}
                  onChange={(e) => setNewAlutSatuan(e.target.value)}
                  className="px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-orange-500"
                >
                  <option value="Unit">Unit</option>
                  <option value="Set">Set</option>
                  <option value="Lembar">Lembar</option>
                  <option value="Buah">Buah</option>
                  <option value="Pak">Pak</option>
                </select>
                <button
                  type="submit"
                  disabled={isSubmitting || !newAlutNama.trim()}
                  className="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-orange-600 hover:bg-orange-500 disabled:opacity-50 text-white rounded-lg text-xs font-semibold transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  <span>Tambah Alut</span>
                </button>
              </form>

              {/* Table Alut */}
              <div className="border border-slate-800 rounded-xl overflow-hidden bg-slate-950/40">
                <table className="w-full text-left text-xs">
                  <thead className="bg-slate-800/80 text-slate-400 font-semibold border-b border-slate-800">
                    <tr>
                      <th className="py-2.5 px-4 w-12 text-center">ID</th>
                      <th className="py-2.5 px-4">Nama Alut & Palsar</th>
                      <th className="py-2.5 px-4 w-28">Satuan Default</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/60 text-slate-200">
                    {masterAlut.map((alut) => (
                      <tr key={alut.id_alut} className="hover:bg-slate-800/40 transition-colors">
                        <td className="py-2 px-4 text-center font-mono text-slate-500">{alut.id_alut}</td>
                        <td className="py-2 px-4 font-medium">{alut.nama_alut}</td>
                        <td className="py-2 px-4">
                          <span className="px-2 py-0.5 rounded text-[11px] bg-slate-800 border border-slate-700 text-slate-300">
                            {alut.satuan}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          ) : (
            <>
              {/* Form Add Tim */}
              <form onSubmit={handleCreateTim} className="flex gap-2">
                <input
                  type="text"
                  placeholder="Nama Tim / Pos SAR (mis: Pos SAR Kolaka, ABK RB 210)..."
                  value={newTimNama}
                  onChange={(e) => setNewTimNama(e.target.value)}
                  className="flex-1 px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-orange-500"
                />
                <button
                  type="submit"
                  disabled={isSubmitting || !newTimNama.trim()}
                  className="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-600 hover:bg-orange-500 disabled:opacity-50 text-white rounded-lg text-xs font-semibold transition-colors"
                >
                  <Plus className="w-4 h-4" />
                  <span>Tambah Tim</span>
                </button>
              </form>

              {/* Table Tim */}
              <div className="border border-slate-800 rounded-xl overflow-hidden bg-slate-950/40">
                <table className="w-full text-left text-xs">
                  <thead className="bg-slate-800/80 text-slate-400 font-semibold border-b border-slate-800">
                    <tr>
                      <th className="py-2.5 px-4 w-12 text-center">ID</th>
                      <th className="py-2.5 px-4">Nama Tim / Unit Siaga SAR</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-800/60 text-slate-200">
                    {masterTim.map((tim) => (
                      <tr key={tim.id_tim} className="hover:bg-slate-800/40 transition-colors">
                        <td className="py-2 px-4 text-center font-mono text-slate-500">{tim.id_tim}</td>
                        <td className="py-2 px-4 font-medium">{tim.nama_tim}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
        </div>

        {/* Footer */}
        <div className="px-5 py-3 bg-slate-800/60 border-t border-slate-800 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs font-medium"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
};
