import React, { useState, useEffect } from 'react';
import { X, Copy, Check, Edit3, Save, Share2, Printer, FileText } from 'lucide-react';
import { showSuccessAlert, showToast } from '../utils/alerts';

interface ViewLaporanModalProps {
  isOpen: boolean;
  title: string;
  subtitle?: string;
  initialText: string;
  onClose: () => void;
  onSaveText?: (newText: string) => Promise<void> | void;
}

export const ViewLaporanModal: React.FC<ViewLaporanModalProps> = ({
  isOpen,
  title,
  subtitle,
  initialText,
  onClose,
  onSaveText,
}) => {
  const [text, setText] = useState<string>(initialText);
  const [isEditing, setIsEditing] = useState<boolean>(false);
  const [copied, setCopied] = useState<boolean>(false);
  const [isSaving, setIsSaving] = useState<boolean>(false);

  useEffect(() => {
    setText(initialText);
    setIsEditing(false);
    setCopied(false);
  }, [initialText, isOpen]);

  if (!isOpen) return null;

  const handleCopy = async () => {
    try {
      await navigator.clipboard.writeText(text);
      setCopied(true);
      showToast('Naskah laporan berhasil disalin ke clipboard!', 'success');
      setTimeout(() => setCopied(false), 2500);
    } catch (err) {
      console.error('Failed to copy', err);
    }
  };

  const handleSave = async () => {
    if (!onSaveText) {
      setIsEditing(false);
      return;
    }
    setIsSaving(true);
    try {
      await onSaveText(text);
      setIsEditing(false);
      await showSuccessAlert('Berhasil Disimpan!', 'Perubahan naskah laporan berhasil diperbarui.');
    } catch (err) {
      console.error('Failed to save updated text', err);
    } finally {
      setIsSaving(false);
    }
  };

  const handleWhatsAppShare = () => {
    const encoded = encodeURIComponent(text);
    const url = `https://api.whatsapp.com/send?text=${encoded}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  };

  const handlePrint = () => {
    const iframe = document.createElement('iframe');
    iframe.style.position = 'fixed';
    iframe.style.right = '0';
    iframe.style.bottom = '0';
    iframe.style.width = '0';
    iframe.style.height = '0';
    iframe.style.border = '0';
    iframe.style.visibility = 'hidden';
    document.body.appendChild(iframe);

    const doc = iframe.contentWindow?.document;
    if (!doc) {
      window.print();
      return;
    }

    doc.open();
    doc.write(`
      <!DOCTYPE html>
      <html>
        <head>
          <title>${title}</title>
          <style>
            @page { size: A4; margin: 15mm; }
            body { font-family: monospace, Courier, sans-serif; white-space: pre-wrap; padding: 20px; color: #000; line-height: 1.45; font-size: 10pt; }
          </style>
        </head>
        <body>${text.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</body>
      </html>
    `);
    doc.close();

    showToast('Membuka dialog cetak laporan...', 'info');

    setTimeout(() => {
      try {
        iframe.contentWindow?.focus();
        iframe.contentWindow?.print();
      } catch (e) {
        console.error(e);
      } finally {
        setTimeout(() => iframe.remove(), 2500);
      }
    }, 350);
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/80 backdrop-blur-sm animate-in fade-in duration-200">
      <div className="relative w-full max-w-4xl max-h-[92vh] flex flex-col bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl overflow-hidden">
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-3.5 bg-slate-800/90 border-b border-slate-700">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-orange-500/10 border border-orange-500/30 text-orange-400">
              <FileText className="w-5 h-5" />
            </div>
            <div>
              <h3 className="text-base font-bold text-white flex items-center gap-2">
                {title}
                <span className="px-2 py-0.5 text-[10px] font-mono font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 rounded">
                  FORMAT WA RESMI
                </span>
              </h3>
              {subtitle && <p className="text-xs text-slate-400">{subtitle}</p>}
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={onClose}
              className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-700 rounded-lg transition-colors"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Toolbar */}
        <div className="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 bg-slate-800/40 border-b border-slate-800 text-xs">
          <div className="flex items-center gap-2">
            {onSaveText && (
              <button
                onClick={() => {
                  if (isEditing) handleSave();
                  else setIsEditing(true);
                }}
                disabled={isSaving}
                className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md font-medium transition-colors ${
                  isEditing
                    ? 'bg-emerald-600 hover:bg-emerald-500 text-white'
                    : 'bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700'
                }`}
              >
                {isEditing ? (
                  <>
                    <Save className="w-3.5 h-3.5" />
                    <span>{isSaving ? 'Menyimpan...' : 'Simpan Perubahan'}</span>
                  </>
                ) : (
                  <>
                    <Edit3 className="w-3.5 h-3.5 text-amber-400" />
                    <span>Edit Redaksi Langsung</span>
                  </>
                )}
              </button>
            )}

            {isEditing && (
              <button
                onClick={() => {
                  setText(initialText);
                  setIsEditing(false);
                }}
                className="px-2.5 py-1.5 text-slate-400 hover:text-slate-200"
              >
                Batal
              </button>
            )}
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={handlePrint}
              title="Cetak Laporan"
              className="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 rounded-md transition-colors"
            >
              <Printer className="w-3.5 h-3.5 text-slate-400" />
              <span className="hidden sm:inline">Cetak</span>
            </button>

            <button
              onClick={handleWhatsAppShare}
              title="Buka Langsung di WhatsApp"
              className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-700/80 hover:bg-emerald-600 text-white border border-emerald-600/50 rounded-md font-medium transition-colors"
            >
              <Share2 className="w-3.5 h-3.5" />
              <span>Share ke WA</span>
            </button>

            <button
              id="btn-copy-wa-modal"
              onClick={handleCopy}
              className={`inline-flex items-center gap-1.5 px-4 py-1.5 rounded-md font-semibold transition-all ${
                copied
                  ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/30'
                  : 'bg-orange-600 hover:bg-orange-500 text-white shadow-md shadow-orange-600/30'
              }`}
            >
              {copied ? (
                <>
                  <Check className="w-4 h-4" />
                  <span>Tersalin!</span>
                </>
              ) : (
                <>
                  <Copy className="w-4 h-4" />
                  <span>Salin Teks WA</span>
                </>
              )}
            </button>
          </div>
        </div>

        {/* Content Viewer / Editor */}
        <div className="flex-1 p-5 overflow-y-auto bg-slate-950/70">
          {isEditing ? (
            <div className="flex flex-col h-full">
              <div className="mb-2 text-xs text-amber-400 flex items-center gap-1.5 font-medium">
                <span>Mode Edit Aktif:</span>
                <span className="text-slate-400">Silakan koreksi redaksi teks di bawah ini sebelum disalin atau disimpan.</span>
              </div>
              <textarea
                value={text}
                onChange={(e) => setText(e.target.value)}
                rows={18}
                className="w-full h-full min-h-[380px] p-4 bg-slate-900 border border-slate-700 rounded-xl font-mono text-xs sm:text-sm text-slate-200 leading-relaxed focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 resize-y"
              />
            </div>
          ) : (
            <div className="relative group">
              <div className="p-4 sm:p-6 bg-slate-900/90 border border-slate-800 rounded-xl shadow-inner font-mono text-xs sm:text-sm text-slate-200 whitespace-pre-wrap leading-relaxed select-text">
                {text}
              </div>
            </div>
          )}
        </div>

        {/* Footer info */}
        <div className="px-5 py-2.5 bg-slate-900 border-t border-slate-800 flex items-center justify-between text-[11px] text-slate-500">
          <span>KPP Kendari • Format Baku FILE AKTIF Basarnas</span>
          <span>{text.length} Karakter</span>
        </div>
      </div>
    </div>
  );
};
