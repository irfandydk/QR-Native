import React, { useState, useEffect } from 'react';
import { X, Download, Copy, Check, Database, Code2 } from 'lucide-react';
import { showToast } from '../utils/alerts';

interface SqlExportModalProps {
  isOpen: boolean;
  onClose: () => void;
}

export const SqlExportModal: React.FC<SqlExportModalProps> = ({ isOpen, onClose }) => {
  const [sqlText, setSqlText] = useState<string>('');
  const [loading, setLoading] = useState<boolean>(true);
  const [copied, setCopied] = useState<boolean>(false);

  useEffect(() => {
    if (isOpen) {
      setLoading(true);
      fetch('/api/export/sql')
        .then((res) => res.text())
        .then((data) => {
          setSqlText(data);
          setLoading(false);
        })
        .catch((err) => {
          console.error(err);
          setSqlText('-- Gagal memuat SQL dump');
          setLoading(false);
        });
    }
  }, [isOpen]);

  if (!isOpen) return null;

  const handleDownload = () => {
    const blob = new Blob([sqlText], { type: 'application/sql' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'db_sar_qr.sql';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast('File db_sar_qr.sql berhasil diunduh!', 'success');
  };

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(sqlText);
      setCopied(true);
      showToast('Dump SQL berhasil disalin ke clipboard!', 'success');
      setTimeout(() => setCopied(false), 2000);
    } catch (err) {
      console.error(err);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="relative w-full max-w-3xl max-h-[90vh] flex flex-col bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-3.5 bg-slate-800/90 border-b border-slate-700">
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-lg bg-orange-500/10 border border-orange-500/30 text-orange-400">
              <Database className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-white flex items-center gap-2">
                Export MySQL Database
                <span className="px-2 py-0.5 text-[10px] font-mono bg-blue-500/20 text-blue-400 border border-blue-500/30 rounded">
                  db_sar_qr.sql
                </span>
              </h3>
              <p className="text-xs text-slate-400">
                Skema tabel & dump data MySQL lengkap sesuai spesifikasi sistem
              </p>
            </div>
          </div>
          <button
            onClick={onClose}
            className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded-lg transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Toolbar */}
        <div className="flex items-center justify-between px-5 py-2.5 bg-slate-800/40 border-b border-slate-800 text-xs">
          <div className="flex items-center gap-2 text-slate-400">
            <Code2 className="w-4 h-4 text-orange-400" />
            <span>Tabel: master_alut, master_tim, operasi, korban, renops, laporan_harian</span>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handleCopy}
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 rounded-md font-medium transition-colors"
            >
              {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" /> : <Copy className="w-3.5 h-3.5" />}
              <span>{copied ? 'Tersalin!' : 'Salin SQL'}</span>
            </button>
            <button
              onClick={handleDownload}
              className="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-md font-semibold transition-colors shadow-md shadow-orange-600/20"
            >
              <Download className="w-3.5 h-3.5" />
              <span>Download .sql</span>
            </button>
          </div>
        </div>

        {/* Content View */}
        <div className="flex-1 p-4 overflow-y-auto bg-slate-950">
          {loading ? (
            <div className="p-8 text-center text-slate-500 text-xs">Menyiapkan dump SQL...</div>
          ) : (
            <pre className="p-4 bg-slate-900/90 border border-slate-800 rounded-xl font-mono text-[11px] text-emerald-400/90 whitespace-pre-wrap leading-relaxed overflow-x-auto select-all">
              {sqlText}
            </pre>
          )}
        </div>

        {/* Footer */}
        <div className="px-5 py-2.5 bg-slate-900 border-t border-slate-800 flex justify-end">
          <button
            onClick={onClose}
            className="px-4 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-lg text-xs"
          >
            Tutup
          </button>
        </div>
      </div>
    </div>
  );
};
