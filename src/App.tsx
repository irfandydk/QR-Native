import React, { useState, useEffect, useCallback } from 'react';
import { Header } from './components/Header';
import { DashboardList } from './components/DashboardList';
import { FormInisiasi } from './components/FormInisiasi';
import { DetailOperasiHub } from './components/DetailOperasiHub';
import { MasterDataModal } from './components/MasterDataModal';
import { MasterSettingsPage } from './components/MasterSettingsPage';
import { MasterDataPage } from './components/MasterDataPage';
import { AdminSettingsPage } from './components/AdminSettingsPage';
import { BraArchivePage } from './components/BraArchivePage';
import { ViewLaporanModal } from './components/ViewLaporanModal';
import { FormBraSar } from './components/FormBraSar';
import { OfficialBraDocument } from './components/OfficialBraDocument';
import { BasarnasLogo } from './components/BasarnasLogo';
import { BraListModal } from './components/BraListModal';
import { LoginPage } from './components/LoginPage';
import { Sidebar, ActiveNavView } from './components/Sidebar';
import { MapPlottingPage } from './components/MapPlottingPage';
import { useTheme } from './context/ThemeContext';
import { showToast } from './utils/alerts';
import { Operasi, MasterAlut, MasterTim, BraSar, KategoriBra, UserSession } from './types';
import { ShieldCheck, LogIn } from 'lucide-react';

export default function App() {
  const { isDarkMode } = useTheme();

  // Instant synchronous check for QR scanning in URL
  const initialQrBraId = typeof window !== 'undefined' 
    ? new URLSearchParams(window.location.search).get('viewBra') || new URLSearchParams(window.location.search).get('bra')
    : null;

  const [currentUser, setCurrentUser] = useState<UserSession | null>(() => {
    try {
      const saved = localStorage.getItem('basarnas_user_session');
      return saved ? JSON.parse(saved) : null;
    } catch {
      return null;
    }
  });

  const [isGmpQuotaExceeded, setIsGmpQuotaExceeded] = useState<boolean>(false);

  useEffect(() => {
    const handleQuotaExceeded = () => {
      setIsGmpQuotaExceeded(true);
    };
    window.addEventListener('gmp-quota-exceeded', handleQuotaExceeded);
    return () => {
      window.removeEventListener('gmp-quota-exceeded', handleQuotaExceeded);
    };
  }, []);

  const [view, setView] = useState<
    | 'dashboard'
    | 'form-inisiasi'
    | 'detail-hub'
    | 'form-bra'
    | 'view-bra'
    | 'master-data'
    | 'admin-settings'
    | 'master-settings'
    | 'bra-archive'
    | 'map-plotting'
  >(() => {
    if (initialQrBraId) return 'dashboard';
    try {
      const savedView = localStorage.getItem('basarnas_last_active_view');
      const validViews = [
        'dashboard',
        'form-inisiasi',
        'detail-hub',
        'form-bra',
        'view-bra',
        'master-data',
        'admin-settings',
        'master-settings',
        'bra-archive',
        'map-plotting',
      ];
      if (savedView && validViews.includes(savedView)) {
        return savedView as any;
      }
    } catch {}
    return 'dashboard';
  });
  const [isSidebarOpen, setIsSidebarOpen] = useState<boolean>(true);
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = useState<boolean>(false);
  const [masterDataInitialTab, setMasterDataInitialTab] = useState<'alut' | 'tim' | 'pejabat'>('alut');
  const [adminSettingsInitialTab, setAdminSettingsInitialTab] = useState<'admin' | 'satker' | 'backup'>('admin');
  const [masterSettingsInitialTab, setMasterSettingsInitialTab] = useState<'alut' | 'tim' | 'pejabat' | 'satker' | 'keamanan' | 'backup' | 'admin'>('alut');
  const [selectedOperasiId, setSelectedOperasiId] = useState<number | null>(() => {
    try {
      const savedOpId = localStorage.getItem('basarnas_last_selected_operasi_id');
      return savedOpId ? parseInt(savedOpId, 10) : null;
    } catch {
      return null;
    }
  });
  const [editingOperasi, setEditingOperasi] = useState<Operasi | null>(null);

  // BRA SAR states
  const [braList, setBraList] = useState<BraSar[]>([]);
  const [activeBraKategori, setActiveBraKategori] = useState<KategoriBra>('MAPEL');
  const [activeBraDoc, setActiveBraDoc] = useState<BraSar | null>(null);
  const [showBraListModal, setShowBraListModal] = useState<boolean>(false);
  const [preselectedBraOpId, setPreselectedBraOpId] = useState<number | null>(null);
  const [isPublicScanView, setIsPublicScanView] = useState<boolean>(Boolean(initialQrBraId));
  const [isVerifyingQrDoc, setIsVerifyingQrDoc] = useState<boolean>(Boolean(initialQrBraId));
  const [showLoginModal, setShowLoginModal] = useState<boolean>(false);

  const [operasiList, setOperasiList] = useState<Operasi[]>([]);
  const [masterAlut, setMasterAlut] = useState<MasterAlut[]>([]);
  const [masterTim, setMasterTim] = useState<MasterTim[]>([]);
  const [appLogoUrl, setAppLogoUrl] = useState<string>(() => {
    return (typeof window !== 'undefined' ? localStorage.getItem('sar_custom_logo_url') : null) || '';
  });
  const [loading, setLoading] = useState<boolean>(true);

  // Modals
  const [showMasterModal, setShowMasterModal] = useState<boolean>(false);
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

  // Fetch all initial/updated data with strict anti-cache guarantees
  const fetchData = useCallback(async (isSilent = false) => {
    try {
      if (!isSilent) setLoading(true);
      const timestamp = Date.now();
      const fetchOpts: RequestInit = {
        cache: 'no-store',
        headers: {
          'Pragma': 'no-cache',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
        },
      };

      const [opRes, alutRes, timRes, braRes, setRes] = await Promise.all([
        fetch(`/api/operasi?_t=${timestamp}`, fetchOpts),
        fetch(`/api/master/alut?_t=${timestamp}`, fetchOpts),
        fetch(`/api/master/tim?_t=${timestamp}`, fetchOpts),
        fetch(`/api/bra?_t=${timestamp}`, fetchOpts),
        fetch(`/api/settings?_t=${timestamp}`, fetchOpts),
      ]);

      const [opData, alutData, timData, braData, setData] = await Promise.all([
        opRes.json(),
        alutRes.json(),
        timRes.json(),
        braRes.json(),
        setRes.json(),
      ]);

      if (opData.success) setOperasiList(opData.data);
      if (alutData.success) setMasterAlut(alutData.data);
      if (timData.success) setMasterTim(timData.data);
      if (braData.success) setBraList(braData.data);
      if (setData.success && setData.data?.logo_url) {
        setAppLogoUrl(setData.data.logo_url);
        try {
          localStorage.setItem('sar_custom_logo_url', setData.data.logo_url);
        } catch {}
      }
    } catch (err) {
      console.error('Failed to fetch fresh data:', err);
    } finally {
      if (!isSilent) setLoading(false);
    }
  }, []);

  // 1. Fetch data on initial mount
  useEffect(() => {
    fetchData(false);
  }, [fetchData]);

  // 2. Automatically load fresh data from DB whenever user changes view/page
  useEffect(() => {
    fetchData(true);
  }, [view, fetchData]);

  // 3. Multi-device real-time sync: Auto-fetch on window focus, tab visibility & periodic background polling
  useEffect(() => {
    const handleFocus = () => {
      fetchData(true);
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        fetchData(true);
      }
    };

    window.addEventListener('focus', handleFocus);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    // Background polling interval every 6 seconds when tab is active
    const pollingTimer = setInterval(() => {
      if (document.visibilityState === 'visible') {
        fetchData(true);
      }
    }, 6000);

    return () => {
      window.removeEventListener('focus', handleFocus);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      clearInterval(pollingTimer);
    };
  }, [fetchData]);

  // Synchronize currentUser if updated from Admin Settings or across tabs
  useEffect(() => {
    const handleAdminUpdated = (e: any) => {
      if (e.detail) {
        setCurrentUser((prev) => {
          const next = prev ? { ...prev, ...e.detail } : e.detail;
          try {
            localStorage.setItem('basarnas_user_session', JSON.stringify(next));
          } catch {}
          return next;
        });
      }
    };

    // Multi-tab / multi-device concurrent sync
    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'basarnas_user_session') {
        if (e.newValue) {
          try {
            setCurrentUser(JSON.parse(e.newValue));
          } catch {}
        } else {
          setCurrentUser(null);
        }
      }
    };

    window.addEventListener('qr_sar_admin_updated', handleAdminUpdated);
    window.addEventListener('storage', handleStorageChange);

    const handleLogoUpdated = (e: any) => {
      const newUrl = e?.detail?.logo_url || localStorage.getItem('sar_custom_logo_url');
      if (newUrl) setAppLogoUrl(newUrl);
    };
    window.addEventListener('sar_logo_updated', handleLogoUpdated);

    return () => {
      window.removeEventListener('qr_sar_admin_updated', handleAdminUpdated);
      window.removeEventListener('storage', handleStorageChange);
      window.removeEventListener('sar_logo_updated', handleLogoUpdated);
    };
  }, []);

  // Persist current active view and selectedOperasiId on change
  useEffect(() => {
    if (!isPublicScanView && view) {
      try {
        localStorage.setItem('basarnas_last_active_view', view);
        if (selectedOperasiId) {
          localStorage.setItem('basarnas_last_selected_operasi_id', String(selectedOperasiId));
        }
      } catch {}
    }
  }, [view, selectedOperasiId, isPublicScanView]);

  // Handle URL query for Direct QR Code Scan verification (?viewBra=123 or ?bra=123)
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search);
      const braId = params.get('viewBra') || params.get('bra');
      if (braId) {
        setIsPublicScanView(true);
        setIsVerifyingQrDoc(true);
        fetch(`/api/bra/${braId}`)
          .then((res) => res.json())
          .then((data) => {
            if (data.success && data.data) {
              setActiveBraDoc(data.data);
              setView('view-bra');
            } else {
              showToast('Dokumen BRA SAR tidak ditemukan atau nomor salah.', 'error');
            }
          })
          .catch((err) => {
            console.error('Failed to load BRA from QR link:', err);
            showToast('Gagal memverifikasi dokumen BRA.', 'error');
          })
          .finally(() => {
            setIsVerifyingQrDoc(false);
          });
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

  // If scanning QR from outside without login, show verification screen
  if (!currentUser && isVerifyingQrDoc) {
    return (
      <div className={`min-h-screen flex flex-col items-center justify-center p-6 ${
        isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'
      }`}>
        <div className="w-16 h-16 rounded-2xl bg-orange-500/10 border border-orange-500/30 flex items-center justify-center mb-4">
          <div className="w-8 h-8 border-3 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
        </div>
        <h2 className="text-base font-bold tracking-wide uppercase">Memverifikasi Dokumen BRA SAR</h2>
        <p className="text-xs text-slate-400 mt-1 max-w-sm text-center">
          Menghubungkan ke pusat validasi data telegram digital Kantor SAR Kendari...
        </p>
      </div>
    );
  }

  // If viewing via QR scan without login, render public verification view directly!
  if (!currentUser && (isPublicScanView || view === 'view-bra') && activeBraDoc) {
    return (
      <div className={`min-h-screen flex flex-col font-sans transition-colors duration-200 ${
        isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'
      }`}>
        {/* Public Header Banner */}
        <div className={`border-b px-4 py-3 shadow-md flex items-center justify-between gap-3 ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}>
          <div className="flex items-center gap-2.5">
            <BasarnasLogo size={36} logoUrl={activeBraDoc?.logo_url} className="shrink-0 drop-shadow-sm" />
            <div>
              <div className="flex items-center gap-2">
                <span className="font-extrabold text-sm tracking-wide">BASARNAS KENDARI</span>
                <span className="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                  <ShieldCheck className="w-3 h-3" />
                  Verifikasi Dokumen Resmi
                </span>
              </div>
              <p className="text-[10px] text-slate-400">Pusat Validasi Berita Radio SAR (BRA SAR) Digital</p>
            </div>
          </div>

          <button
            onClick={() => setShowLoginModal(true)}
            className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow transition-all"
          >
            <LogIn className="w-3.5 h-3.5" />
            <span>Login Petugas</span>
          </button>
        </div>

        {/* Public Document View */}
        <div className="flex-1 py-6 px-2 sm:px-6">
          <OfficialBraDocument
            bra={activeBraDoc}
            logoUrl={activeBraDoc.logo_url}
            onClose={() => {
              if (typeof window !== 'undefined' && window.location.search) {
                window.history.replaceState({}, document.title, window.location.pathname);
              }
              setIsPublicScanView(false);
            }}
          />
        </div>

        {/* Login modal popup for officers */}
        {showLoginModal && (
          <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
            <div className="w-full max-w-md relative">
              <button
                onClick={() => setShowLoginModal(false)}
                className="absolute right-3 top-3 text-slate-400 hover:text-white z-10 text-xs px-2 py-1 bg-black/40 rounded-lg"
              >
                Tutup ✕
              </button>
              <LoginPage
                onLoginSuccess={(user) => {
                  setCurrentUser(user);
                  setShowLoginModal(false);
                  setIsPublicScanView(false);
                  setView('dashboard');
                }}
              />
            </div>
          </div>
        )}
      </div>
    );
  }

  // If not logged in, show Login Page before entering Dashboard
  if (!currentUser) {
    return <LoginPage onLoginSuccess={(user) => setCurrentUser(user)} />;
  }

  // Find currently selected operation
  const currentOperasi = operasiList.find((o) => o.id_operasi === selectedOperasiId) || null;

  return (
    <div className={`min-h-screen flex font-sans transition-colors duration-200 ${
      isDarkMode ? 'bg-slate-950 text-slate-100' : 'bg-slate-50 text-slate-900'
    } selection:bg-orange-500 selection:text-white`}>
      {/* Left Sidebar Navigation (Pengaturan Admin, Master Data, Laporan, BRA SAR, Ploting Peta) */}
      <Sidebar
        currentView={view as ActiveNavView}
        onNavigate={(navView, tab) => {
          if (navView === 'admin-settings') {
            if (tab) setAdminSettingsInitialTab(tab as any);
            setView('admin-settings');
          } else if (navView === 'master-data') {
            if (tab) setMasterDataInitialTab(tab as any);
            setView('master-data');
          } else if (navView === 'master-settings') {
            // Smart router: keep backward compatibility
            if (tab === 'keamanan' || tab === 'admin') {
              setAdminSettingsInitialTab('admin');
              setView('admin-settings');
            } else if (tab === 'satker') {
              setAdminSettingsInitialTab('satker');
              setView('admin-settings');
            } else if (tab === 'backup') {
              setAdminSettingsInitialTab('backup');
              setView('admin-settings');
            } else {
              setMasterDataInitialTab((tab as any) || 'alut');
              setView('master-data');
            }
          } else {
            setView(navView as any);
          }
          if (navView === 'dashboard') {
            setSelectedOperasiId(null);
            setEditingOperasi(null);
            setActiveBraDoc(null);
          }
        }}
        currentUser={currentUser}
        onLogout={handleLogout}
        isOpen={isSidebarOpen}
        onToggle={() => setIsSidebarOpen((prev) => !prev)}
        isMobileOpen={isMobileSidebarOpen}
        onCloseMobile={() => setIsMobileSidebarOpen(false)}
        logoUrl={appLogoUrl}
      />

      {/* Main Container Wrapper with dynamic padding for sidebar */}
      <div className={`transition-all duration-300 flex-1 flex flex-col min-w-0 ${
        isSidebarOpen ? 'lg:pl-64' : 'lg:pl-20'
      }`}>
        {/* Google Maps Quota Warning Banner */}
        {isGmpQuotaExceeded && (
          <div className="bg-amber-50 border-b border-amber-200 text-amber-900 px-4 py-2.5 text-xs md:text-sm text-center sticky top-0 z-50 shadow-sm flex items-center justify-between">
            <span className="flex-1">
              Google Maps Platform quota reached. If you are the app owner, visit{' '}
              <a
                href="https://developers.google.com/maps/ai/ai-studio?utm_campaign=gmp_mcp_codeassist_v1_aistudio#quota_exceeded_errors"
                target="_blank"
                rel="noopener noreferrer"
                className="underline font-semibold text-amber-950 hover:text-amber-800"
              >
                maps developer site
              </a>{' '}
              for instructions to update your account.
            </span>
            <button
              onClick={() => setIsGmpQuotaExceeded(false)}
              className="ml-2 text-amber-900 hover:text-amber-950 font-bold px-1.5 py-0.5 cursor-pointer"
              title="Tutup pemberitahuan"
            >
              ×
            </button>
          </div>
        )}

        {/* Universal Top Navigation Header (Clean, view-context aware, no duplicate controls) */}
        <Header
          currentViewTitle={
            view === 'dashboard'
              ? 'DASHBOARD OPERASI SAR'
              : view === 'map-plotting'
              ? 'PLOTING PETA GEOSPASIAL SAR'
              : view === 'bra-archive' || view === 'view-bra' || view === 'form-bra'
              ? 'BERITA RADIO & ARSIP BRA SAR'
              : view === 'master-data'
              ? 'MASTER DATA OPERASIONAL SAR'
              : view === 'admin-settings'
              ? 'PENGATURAN SATKER & KEAMANAN ADMIN'
              : view === 'master-settings'
              ? 'PENGATURAN SISTEM SAR'
              : view === 'detail-hub'
              ? 'PUSAT KENDALI OPERASI SAR'
              : view === 'form-inisiasi'
              ? 'INISIASI OPERASI SAR BARU'
              : 'SISTEM SAR DIGITAL'
          }
          currentViewSubtitle={
            view === 'map-plotting'
              ? 'PENGUKURAN JARAK, LUAS POLYGON, POLA PENCARIAN IAMSAR & CUSTOM ICON KENDARI'
              : view === 'admin-settings'
              ? 'IDENTITAS SATKER, PROFIL ADMIN, SANDI AKUN, SERTA CADANGAN & PEMULIHAN BASIS DATA TERPADU'
              : view === 'master-data'
              ? 'MANAJEMEN ARMADA ALUT, PALASAR, TIM RESCUE & PEJABAT SMC'
              : 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI • BASARNAS'
          }
          onOpenMobileSidebar={() => setIsMobileSidebarOpen(true)}
        />

        {/* Main Content Area */}
        <main className="flex-1 pb-16">
          {loading && operasiList.length === 0 ? (
            <div className="flex flex-col items-center justify-center min-h-[60vh] gap-3">
              <div className="w-8 h-8 border-2 border-orange-500 border-t-transparent rounded-full animate-spin"></div>
              <p className="text-xs text-slate-400 font-medium">Memuat data SAR Kendari...</p>
            </div>
          ) : view === 'map-plotting' ? (
            <MapPlottingPage appLogoUrl={appLogoUrl} />
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
          ) : view === 'bra-archive' ? (
            <BraArchivePage
              braList={braList}
              onSelectBra={(bra) => {
                setActiveBraDoc(bra);
                setView('view-bra');
              }}
              onCreateBra={(kategori) => handleOpenCreateBra(kategori)}
              onDeleteBra={handleDeleteBra}
              onRefresh={() => fetchData(true)}
              onBack={() => setView('dashboard')}
            />
          ) : view === 'view-bra' && activeBraDoc ? (
            <div className="py-6">
              <OfficialBraDocument
                bra={activeBraDoc}
                onClose={() => {
                  if (selectedOperasiId) {
                    setView('detail-hub');
                  } else {
                    setView('bra-archive');
                  }
                }}
                onDelete={() => handleDeleteBra(activeBraDoc.id_bra)}
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
              braList={braList}
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
              onRefresh={() => fetchData(true)}
              onViewTextWA={(title, subtitle, text) => {
                setViewLaporanData({
                  isOpen: true,
                  title,
                  subtitle,
                  text,
                });
              }}
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
          ) : view === 'master-data' ? (
            <MasterDataPage
              initialTab={masterDataInitialTab}
              onBack={() => {
                if (selectedOperasiId) {
                  setView('detail-hub');
                } else {
                  setView('dashboard');
                }
              }}
              onRefreshData={fetchData}
            />
          ) : view === 'admin-settings' ? (
            <AdminSettingsPage
              initialTab={adminSettingsInitialTab}
              onBack={() => {
                if (selectedOperasiId) {
                  setView('detail-hub');
                } else {
                  setView('dashboard');
                }
              }}
              onRefreshData={fetchData}
              onUpdateCurrentUser={(updated) => {
                setCurrentUser((prev) => {
                  const nextUser = prev ? { ...prev, ...updated } : updated;
                  try {
                    localStorage.setItem('basarnas_user_session', JSON.stringify(nextUser));
                  } catch {}
                  return nextUser;
                });
              }}
            />
          ) : view === 'master-settings' ? (
            <MasterDataPage
              initialTab="alut"
              onBack={() => setView('dashboard')}
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

        {/* Footer info banner - strictly application name and office name */}
        <footer className={`border-t py-4 px-6 text-center text-xs transition-colors ${
          isDarkMode ? 'border-slate-800/80 bg-slate-900/50 text-slate-400' : 'border-slate-200 bg-white text-slate-600 shadow-xs'
        }`}>
          <p className="font-medium tracking-wide">
            <strong className="text-orange-500 font-bold">QR SAR System</strong> — Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari • BASARNAS
          </p>
        </footer>
      </div>

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

