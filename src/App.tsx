import React, { useState, useEffect, useCallback } from 'react';
import { Header } from './components/Header';
import { DashboardList } from './components/DashboardList';
import { FormInisiasi } from './components/FormInisiasi';
import { DetailOperasiHub } from './components/DetailOperasiHub';
import { MasterDataModal } from './components/MasterDataModal';
import { MasterSettingsPage } from './components/MasterSettingsPage';
import { SqlExportModal } from './components/SqlExportModal';
import { ViewLaporanModal } from './components/ViewLaporanModal';
import { FormBraSar } from './components/FormBraSar';
import { OfficialBraDocument } from './components/OfficialBraDocument';
import { BraListModal } from './components/BraListModal';
import { LoginPage } from './components/LoginPage';
import { useTheme } from './context/ThemeContext';
import { showToast } from './utils/alerts';
import { Operasi, MasterAlut, MasterTim, BraSar, KategoriBra, UserSession } from './types';

export default function App() {
  const { isDarkMode } = useTheme();
  const [currentUser, setCurrentUser] = useState<UserSession | null>(() => {
    try {
      const saved = localStorage.getItem('basarnas_user_session');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  const [view, setView] = useState<'dashboard' | 'form-inisiasi' | 'detail-hub' | 'form-bra' | 'view-bra' | 'master-settings'>('dashboard');
  const [selectedOperasiId, setSelectedOperasiId] = useState<number | null>(null);
  const [editingOperasi, setEditingOperasi] = useState<Operasi | null>(null);

  // BRA SAR states
  const [braList, setBraList] = useState<BraSar[]>([]);
  const [activeBraKategori, setActiveBraKategori] = useState<KategoriBra>('MAPEL');
  const [activeBraDoc, setActiveBraDoc] = useState<BraSar | null>(null);
  const [showBraListModal, setShowBraListModal] = useState<boolean>(false);
  const [preselectedBraOpId, setPreselectedBraOpId] = useState<number | null>(null);

  const [operasiList, setOperasiList] = useState<Operasi[]>([]);
  const [masterAlut, setMasterAlut] = useState<MasterAlut[]>([]);
  const [masterTim, setMasterTim] = useState<MasterTim[]>([]);
  const [loading, setLoading] = useState<boolean>(true);

  // Modals
  const [showMasterModal, setShowMasterModal] = useState<boolean>(false);
  const [showSqlModal, setShowSqlModal] = useState<boolean>(false);
  const [viewLaporanData, setViewLaporanData] = useState<{
    isOpen: boolean;
    title: string;
    subtitle: string;
    text: string;
  }>({
    isOpen: false,
    title: '',
    subtitle: '',
    text: '',
  });

  // Fetch all initial data
  const fetchData = useCallback(async () => {
    try {
      setLoading(true);
      const [opRes, alutRes, timRes, braRes] = await Promise.all([
        fetch('/api/operasi'),
        fetch('/api/master/alut'),
        fetch('/api/master/tim'),
        fetch('/api/bra'),
      ]);

      const [opData, alutData, timData, braData] = await Promise.all([
        opRes.json(),
        alutRes.json(),
        timRes.json(),
        braRes.json(),
      ]);

      if (opData.success) setOperasiList(opData.data);
      if (alutData.success) setMasterAlut(alutData.data);
      if (timData.success) setMasterTim(timData.data);
      if (braData.success) setBraList(braData.data);
    } catch (err) {
      console.error('Failed to fetch data:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  // Handle URL query for Direct QR Code Scan verification (?viewBra=123)
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search);
      const braId = params.get('viewBra') || params.get('bra');
      if (braId) {
        fetch(`/api/bra/${braId}`)
          .then((res) => res.json())
          .then((data) => {
            if (data.success && data.data) {
              setActiveBraDoc(data.data);
              setView('view-bra');
            }
          })
          .catch((err) => console.error('Failed to load BRA from QR link:', err));
      }
    }
  }, []);

  // BRA handlers
  const handleOpenCreateBra = (kategori: KategoriBra, opId?: number) => {
    setActiveBraKategori(kategori);
    setPreselectedBraOpId(opId || selectedOperasiId || null);
    setView('form-bra');
  };

  const handleSelectBraDoc = (bra: BraSar) => {
    setActiveBraDoc(bra);
    setShowBraListModal(false);
    setView('view-bra');
  };

  const handleDeleteBra = async (id: number) => {
    try {
      const res = await fetch(`/api/bra/${id}`, { method: 'DELETE' });
      if (res.ok) {
        setBraList((prev) => prev.filter((b) => b.id_bra !== id));
        if (activeBraDoc?.id_bra === id) {
          setActiveBraDoc(null);
          setView('dashboard');
        }
      }
    } catch (err) {
      console.error('Failed to delete BRA SAR:', err);
    }
  };

  // Refresh single operation
  const handleRefreshOperasi = async (id: number) => {
    try {
      const res = await fetch(`/api/operasi/${id}`);
      const data = await res.json();
      if (data.success && data.data) {
        setOperasiList((prev) =>
          prev.map((op) => (op.id_operasi === id ? data.data : op))
        );
      }
    } catch (err) {
      console.error('Failed to refresh operation:', err);
    }
  };

  // Delete operation
  const handleDeleteOperasi = async (id: number) => {
    try {
      const res = await fetch(`/api/operasi/${id}`, { method: 'DELETE' });
      if (res.ok) {
        setOperasiList((prev) => prev.filter((o) => o.id_operasi !== id));
        if (selectedOperasiId === id) {
          setView('dashboard');
          setSelectedOperasiId(null);
        }
      }
    } catch (err) {
      console.error('Failed to delete operation:', err);
    }
  };

  const handleAddAlut = async (nama_alut: string, satuan: string) => {
    const res = await fetch('/api/master/alut', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nama_alut, satuan }),
    });
    const data = await res.json();
    if (data.success && data.data) {
      setMasterAlut((prev) => [...prev, data.data]);
    }
  };

  const handleAddTim = async (nama_tim: string) => {
    const res = await fetch('/api/master/tim', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ nama_tim }),
    });
    const data = await res.json();
    if (data.success && data.data) {
      setMasterTim((prev) => [...prev, data.data]);
    }
  };

  // Logout handler
  const handleLogout = () => {
    localStorage.removeItem('basarnas_user_session');
    setCurrentUser(null);
    showToast('Berhasil keluar dari sistem SAR.', 'info');
  };

  // If not logged in, show Login Page before entering Dashboard
  if (!currentUser) {
    return <LoginPage onLoginSuccess={(user) => setCurrentUser(user)} />;
  }

  // Find currently selected operation
  const currentOperasi = operasiList.find((o) => o.id_operasi === selectedOperasiId) || null;

  return (
    <div className={`min-h-screen flex flex-col font-sans transition-colors duration-200 ${
      isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'
    } selection:bg-orange-500 selection:text-white`}>
      {/* Universal Top Navigation Header */}
      <Header
        currentUser={currentUser}
        onLogout={handleLogout}
        onNewOperasi={() => {
          setEditingOperasi(null);
          setView('form-inisiasi');
        }}
        onOpenMasterData={() => setView('master-settings')}
        onOpenSqlExport={() => setShowSqlModal(true)}
        onGoHome={() => {
          setView('dashboard');
          setSelectedOperasiId(null);
          setEditingOperasi(null);
          setActiveBraDoc(null);
        }}
        onCreateBra={(kategori) => handleOpenCreateBra(kategori)}
        onOpenBraList={() => setShowBraListModal(true)}
      />

      {/* Main Content Area */}
      <main className="flex-1 pb-16">
        {loading && operasiList.length === 0 ? (
          <div className="flex flex-col items-center justify-center min-h-[60vh] gap-3">
            <div className="w-8 h-8 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
            <p className="text-xs text-slate-400 font-medium">Memuat data SAR Kendari...</p>
          </div>
        ) : view === 'form-bra' ? (
          <FormBraSar
            initialKategori={activeBraKategori}
            operasiList={operasiList}
            preselectedOperasiId={preselectedBraOpId}
            onCancel={() => {
              setView(selectedOperasiId ? 'detail-hub' : 'dashboard');
            }}
            onSaveSuccess={(savedBra) => {
              setBraList((prev) => [savedBra, ...prev]);
              setActiveBraDoc(savedBra);
              setView('view-bra');
            }}
          />
        ) : view === 'view-bra' && activeBraDoc ? (
          <div className="py-6">
            <OfficialBraDocument
              bra={activeBraDoc}
              onClose={() => {
                setView(selectedOperasiId ? 'detail-hub' : 'dashboard');
              }}
              onEdit={() => {
                setActiveBraKategori(activeBraDoc.kategori);
                setPreselectedBraOpId(activeBraDoc.id_operasi || null);
                setView('form-bra');
              }}
            />
          </div>
        ) : view === 'dashboard' ? (
          <DashboardList
            operasiList={operasiList}
            onSelectOperasi={(id) => {
              setSelectedOperasiId(id);
              setView('detail-hub');
            }}
            onNewOperasi={() => {
              setEditingOperasi(null);
              setView('form-inisiasi');
            }}
            onEditOperasi={(op) => {
              setEditingOperasi(op);
              setView('form-inisiasi');
            }}
            onDeleteOperasi={handleDeleteOperasi}
            onViewTextWA={(title, subtitle, text) => {
              setViewLaporanData({
                isOpen: true,
                title,
                subtitle,
                text,
              });
            }}
            onOpenSqlModal={() => setShowSqlModal(true)}
          />
        ) : view === 'form-inisiasi' ? (
          <FormInisiasi
            initialOperasi={editingOperasi}
            masterAlut={masterAlut}
            masterTim={masterTim}
            onBack={() => {
              setView(selectedOperasiId ? 'detail-hub' : 'dashboard');
            }}
            onSaveSuccess={(savedOp) => {
              fetchData();
              setSelectedOperasiId(savedOp.id_operasi);
              setView('detail-hub');
            }}
          />
        ) : view === 'detail-hub' && currentOperasi ? (
          <DetailOperasiHub
            operasi={currentOperasi}
            masterAlut={masterAlut}
            masterTim={masterTim}
            onBack={() => {
              setView('dashboard');
              setSelectedOperasiId(null);
            }}
            onEditOperasi={() => {
              setEditingOperasi(currentOperasi);
              setView('form-inisiasi');
            }}
            onRefreshOperasi={handleRefreshOperasi}
            onDeleteOperasi={handleDeleteOperasi}
            onCreateBraMapel={() => handleOpenCreateBra('MAPEL', currentOperasi.id_operasi)}
            onCreateBraSarUmum={() => handleOpenCreateBra('SAR_UMUM', currentOperasi.id_operasi)}
          />
        ) : view === 'master-settings' ? (
          <MasterSettingsPage
            onBack={() => {
              if (selectedOperasiId) {
                setView('detail-hub');
              } else {
                setView('dashboard');
              }
            }}
            masterAlut={masterAlut}
            masterTim={masterTim}
            onRefreshData={fetchData}
          />
        ) : (
          <div className="max-w-7xl mx-auto p-8 text-center">
            <p className="text-sm text-slate-400">Data operasi tidak ditemukan.</p>
            <button
              onClick={() => setView('dashboard')}
              className="mt-3 px-4 py-2 bg-orange-600 text-white rounded-lg text-xs"
            >
              Kembali ke Dashboard
            </button>
          </div>
        )}
      </main>

      {/* Footer info banner */}
      <footer className={`border-t py-4 px-6 text-center text-[11px] transition-colors ${
        isDarkMode ? 'border-slate-800/80 bg-slate-900/50 text-slate-400' : 'border-slate-200 bg-white text-slate-600 shadow-sm'
      }`}>
        <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
          <p>
            <strong>QR SAR System</strong> — Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari • BASARNAS
          </p>
          <div className="flex items-center gap-4">
            <button
              onClick={() => setShowBraListModal(true)}
              className="hover:text-amber-500 transition-colors"
            >
              Arsip BRA SAR ({braList.length})
            </button>
            <span>•</span>
            <span>Standar Format WhatsApp "FILE AKTIF"</span>
            <span>•</span>
            <button
              onClick={() => setShowSqlModal(true)}
              className="hover:text-orange-500 underline transition-colors"
            >
              Export db_sar_qr.sql
            </button>
          </div>
        </div>
      </footer>

      {/* BRA SAR List Modal */}
      <BraListModal
        isOpen={showBraListModal}
        braList={braList}
        onClose={() => setShowBraListModal(false)}
        onSelectBra={handleSelectBraDoc}
        onCreateNew={(kategori) => {
          setShowBraListModal(false);
          handleOpenCreateBra(kategori);
        }}
        onDeleteBra={handleDeleteBra}
      />

      {/* Master Data Modal (Alut & Tim) */}
      <MasterDataModal
        isOpen={showMasterModal}
        onClose={() => setShowMasterModal(false)}
        masterAlut={masterAlut}
        masterTim={masterTim}
        onAddAlut={handleAddAlut}
        onAddTim={handleAddTim}
      />

      {/* SQL Export Modal */}
      <SqlExportModal
        isOpen={showSqlModal}
        onClose={() => setShowSqlModal(false)}
      />

      {/* Quick View WhatsApp Text Modal */}
      <ViewLaporanModal
        isOpen={viewLaporanData.isOpen}
        title={viewLaporanData.title}
        subtitle={viewLaporanData.subtitle}
        initialText={viewLaporanData.text}
        onClose={() => setViewLaporanData({ ...viewLaporanData, isOpen: false })}
      />
    </div>
  );
}

