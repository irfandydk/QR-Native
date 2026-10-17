<?php
/**
 * QR-SAR - Formulir Naskah Berita Radio SAR (BRA SAR)
 * Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas
 * 
 * Standar UI/UX Profesional:
 * - Mengikuti referensi resmi Basarnas & FormBraSar / OfficialBraDocument
 * - Tata letak rapi, bebas dari formulir berantakan, ergonomis & responsif
 * - Stepper interaktif & live split-screen preview
 * - Generator sandi telegram militer (TWU/TWB, AAA, BBB, CCC, TTK/KMA)
 * - Hasil pratinjau lembar kertas 100% presisi identik dengan bra_view.php
 * - 100% Pure Native PHP Architecture
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$bra = $isEdit ? BraModel::getById($id) : null;

$preselectedOpId = (int)($_GET['id_operasi'] ?? ($bra['id_operasi'] ?? 0));
$fromModul = trim((string)($_GET['from'] ?? ''));

// Tentukan kategori awal dengan prioritas query parameter
$paramKategori = !empty($_GET['kategori']) ? BraHelper::normalizeCategory(trim((string)$_GET['kategori'])) : null;
if ($paramKategori) {
    $initialKategori = $paramKategori;
} elseif (!empty($preselectedOpId) || !empty($fromModul)) {
    $initialKategori = BraHelper::normalizeCategory($bra['kategori'] ?? 'OPERASI_SAR');
} else {
    $initialKategori = BraHelper::normalizeCategory($bra['kategori'] ?? 'MAPEL');
}

$currentKategori = BraHelper::normalizeCategory($bra['kategori'] ?? $initialKategori);
$currentOpId = $bra['id_operasi'] ?? ($preselectedOpId ?: null);

// Integrasi Pre-fill Data Otomatis saat membuat BRA baru
$selectedOps = null;
if (!$isEdit && $preselectedOpId > 0) {
    $selectedOps = OperasiModel::getById($preselectedOpId);
    if ($selectedOps) {
        if (!$paramKategori) {
            $currentKategori = 'OPERASI_SAR';
        }
        if (!is_array($bra)) $bra = [];
        $bra['id_operasi'] = $preselectedOpId;
        $bra['nama_operasi'] = $selectedOps['nama_operasi'] ?? '';

        $autoTemplate = null;
        if ($currentKategori === 'MAPEL') {
            $autoTemplate = BraHelper::buildSectionsFromMapel($selectedOps);
        } elseif ($fromModul === 'renops') {
            $idRenops = (int)($_GET['id_renops'] ?? 0);
            $renopsTarget = null;
            if (!empty($selectedOps['renops'])) {
                foreach ($selectedOps['renops'] as $rn) {
                    if ($idRenops > 0 && (int)($rn['id_renops'] ?? 0) === $idRenops) {
                        $renopsTarget = $rn;
                        break;
                    }
                }
                if (!$renopsTarget) {
                    $renopsTarget = end($selectedOps['renops']);
                }
            }
            if ($renopsTarget) {
                $autoTemplate = BraHelper::buildSectionsFromRenops($selectedOps, $renopsTarget, $renopsTarget['srus'] ?? []);
            }
        } elseif ($fromModul === 'sitrep') {
            $idLaporan = (int)($_GET['id_laporan'] ?? 0);
            $sitrepTarget = null;
            if (!empty($selectedOps['laporan_harian'])) {
                foreach ($selectedOps['laporan_harian'] as $lp) {
                    if ($idLaporan > 0 && (int)($lp['id_laporan'] ?? 0) === $idLaporan) {
                        $sitrepTarget = $lp;
                        break;
                    }
                }
                if (!$sitrepTarget) {
                    $sitrepTarget = end($selectedOps['laporan_harian']);
                }
            }
            if ($sitrepTarget) {
                $autoTemplate = BraHelper::buildSectionsFromSitrep($selectedOps, $sitrepTarget, $selectedOps['korban'] ?? []);
            }
        } elseif ($fromModul === 'laporan_awal' || empty($bra['sections'])) {
            $autoTemplate = BraHelper::buildSectionsFromLaporanAwal($selectedOps);
        }

        if (!empty($autoTemplate)) {
            $bra['perihal'] = $autoTemplate['perihal'] ?? '';
            $bra['aksi'] = $autoTemplate['aksi'] ?? '';
            $bra['info'] = $autoTemplate['info'] ?? '';
            $bra['dari'] = $autoTemplate['dari'] ?? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
            $bra['sections'] = $autoTemplate['sections'] ?? [];
            if (!empty($autoTemplate['jabatan_smc'])) {
                $bra['jabatan_smc'] = $autoTemplate['jabatan_smc'];
            }
            if (!empty($autoTemplate['derajat'])) {
                $bra['derajat'] = $autoTemplate['derajat'];
            }
        }
    }
} elseif (!$isEdit && empty($bra['sections'])) {
    if ($currentKategori === 'MAPEL') {
        $defaultMapel = BraHelper::buildSectionsFromMapel();
        if (!is_array($bra)) $bra = [];
        $bra['perihal'] = $defaultMapel['perihal'];
        $bra['aksi'] = $defaultMapel['aksi'];
        $bra['info'] = $defaultMapel['info'];
        $bra['dari'] = $defaultMapel['dari'];
        $bra['sections'] = $defaultMapel['sections'];
        $bra['jabatan_smc'] = $defaultMapel['jabatan_smc'];
        $bra['derajat'] = $defaultMapel['derajat'];
    }
}

$operasiList = OperasiModel::getListSimple();
$settings = SettingsModel::get();
$pejabatList = $settings['pejabat_list'] ?? [];
$satkerKode = $settings['satker_kode'] ?? 'SAR-304';
$satkerNama = $settings['satker_nama'] ?? 'Kantor Pencarian dan Pertolongan Kendari';
$customLogo = getAppLogoUrl();
$logoDataUri = getAppLogoDataUri();

$seq = BraModel::getNextSequenceNumber($satkerKode);
$noBraPlaceholder = $seq['no_bra'];
$nomorUrutPlaceholder = $seq['nomor_urut'];
$bulanTahunPlaceholder = $seq['bulan_tahun'];

$now = date('Y-m-d H:i:s');
$twuPlaceholder = BraHelper::formatMilitaryTW($now, 'H');
$twbPlaceholder = BraHelper::formatMilitaryTW(date('Y-m-d H:i:s', strtotime('-15 minutes')), 'H');
?>

<!-- ── BRA Form Scoped Modern Stylesheet ── -->
<style>
  :root {
    --bra-card-radius: 1rem;
    --bra-border-smooth: rgba(226, 232, 240, 0.9);
  }
  [data-theme="dark"] {
    --bra-border-smooth: rgba(51, 65, 85, 0.8);
  }

  /* ── Layout & View Toggle ── */
  .bra-layout-container {
    transition: all 0.3s ease;
  }
  .bra-split-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: start;
  }
  @media (min-width: 1200px) {
    .bra-split-active .bra-split-grid {
      grid-template-columns: 1.15fr 0.85fr;
      gap: 1.75rem;
    }
    .bra-split-active .bra-live-sticky-preview {
      position: sticky;
      top: 5.5rem;
      max-height: calc(100vh - 7rem);
      overflow-y: auto;
      padding-right: 0.25rem;
    }
  }

  /* ── Stepper Navigation ── */
  .bra-stepper-wrap {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: var(--bra-card-radius);
    padding: 0.6rem 0.85rem;
    box-shadow: var(--shadow-sm);
  }
  .bra-stepper {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    overflow-x: auto;
    scrollbar-width: none;
  }
  .bra-stepper::-webkit-scrollbar { display: none; }
  .bra-step-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.45rem 0.85rem;
    border-radius: 0.65rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    text-decoration: none;
  }
  .bra-step-btn:hover {
    background: var(--bg-card-subtle);
    color: var(--primary);
  }
  .bra-step-btn.active {
    background: var(--primary-light);
    color: var(--primary);
    border-color: rgba(249, 115, 22, 0.3);
    box-shadow: 0 1px 3px rgba(249, 115, 22, 0.1);
  }
  .bra-step-num {
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 800;
    background: var(--bg-card-subtle);
    color: var(--text-muted);
    border: 1.5px solid currentColor;
    flex-shrink: 0;
  }
  .bra-step-btn.active .bra-step-num {
    background: var(--primary);
    color: #ffffff;
    border-color: var(--primary);
  }
  .bra-step-sep {
    color: var(--border-color);
    font-size: 0.75rem;
    user-select: none;
    flex-shrink: 0;
  }

  /* ── Form Cards & Fieldsets ── */
  .bra-card {
    background: var(--bg-card);
    border: 1.5px solid var(--border-color);
    border-radius: var(--bra-card-radius);
    padding: 1.25rem 1.4rem;
    box-shadow: var(--shadow-sm);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
  }
  .bra-card:focus-within {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  .bra-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 1.1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-color);
  }
  .bra-card-title {
    font-size: 0.85rem;
    font-weight: 800;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    letter-spacing: -0.01em;
  }
  .bra-card-desc {
    font-size: 0.7rem;
    color: var(--text-muted);
    margin-top: 0.15rem;
  }

  /* ── Input Grids & Labels ── */
  .bra-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.9rem; }
  .bra-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.9rem; }
  .bra-grid-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem; }
  @media (max-width: 768px) {
    .bra-grid-2, .bra-grid-3, .bra-grid-4 { grid-template-columns: 1fr; }
  }

  .bra-field {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
  }
  .bra-label {
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .bra-label .req { color: var(--danger); margin-left: 2px; }
  .bra-label .action-link {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--primary);
    text-transform: none;
    letter-spacing: normal;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
  }
  .bra-label .action-link:hover { text-decoration: underline; }

  .bra-control {
    width: 100%;
    padding: 0.6rem 0.85rem;
    font-size: 0.82rem;
    font-family: inherit;
    background: var(--bg-input);
    border: 1.5px solid var(--border-color);
    border-radius: 0.65rem;
    color: var(--text-main);
    transition: all 0.2s ease;
  }
  .bra-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  .bra-control::placeholder { color: var(--text-muted); opacity: 0.55; }
  .bra-control.mono { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
  .bra-control.upper { text-transform: uppercase; }
  .bra-control.bold { font-weight: 700; }
  textarea.bra-control { resize: vertical; min-height: 68px; line-height: 1.6; }
  .bra-hint { font-size: 0.67rem; color: var(--text-muted); opacity: 0.8; }

  /* ── 3 Kategori Tabs (Matching FormBraSar.tsx Reference) ── */
  .kat-tabs {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.75rem;
  }
  @media (max-width: 768px) { .kat-tabs { grid-template-columns: 1fr; } }
  .kat-tab-label { position: relative; cursor: pointer; }
  .kat-tab-label input[type="radio"] { position: absolute; opacity: 0; width: 0; height: 0; }
  .kat-tab-box {
    padding: 0.9rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: 0.85rem;
    background: var(--bg-card);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    height: 100%;
  }
  .kat-tab-box:hover {
    border-color: var(--primary);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
  }
  .kat-tab-label input:checked + .kat-tab-box {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  .kat-icon-wrap {
    width: 2.2rem;
    height: 2.2rem;
    border-radius: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    flex-shrink: 0;
    background: var(--bg-card-subtle);
    border: 1px solid var(--border-color);
    transition: all 0.2s ease;
  }
  .kat-tab-label input:checked + .kat-tab-box .kat-icon-wrap {
    background: var(--primary);
    color: #ffffff;
    border-color: var(--primary);
    box-shadow: 0 2px 6px rgba(249, 115, 22, 0.3);
  }
  .kat-tab-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
    margin-bottom: 0.2rem;
  }
  .kat-tab-title { font-size: 0.8rem; font-weight: 800; color: var(--text-main); }
  .kat-tab-pill {
    font-size: 0.62rem;
    font-weight: 700;
    text-transform: uppercase;
    padding: 0.15rem 0.45rem;
    border-radius: 0.4rem;
    background: rgba(249, 115, 22, 0.15);
    color: var(--primary);
    border: 1px solid rgba(249, 115, 22, 0.25);
  }
  .kat-tab-desc { font-size: 0.67rem; color: var(--text-muted); line-height: 1.45; }

  /* ── Presets & Assistant Banners ── */
  .bra-preset-banner {
    padding: 0.75rem 1rem;
    border-radius: 0.75rem;
    border: 1px solid var(--border-color);
    background: var(--bg-card-subtle);
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.6rem;
  }
  .preset-btn {
    padding: 0.35rem 0.65rem;
    font-size: 0.7rem;
    font-weight: 700;
    border-radius: 0.5rem;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-main);
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
  }
  .preset-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-1px);
    box-shadow: var(--shadow-sm);
  }
  .preset-btn.vessel { border-color: #06b6d4; color: #0891b2; }
  .preset-btn.vessel:hover { background: rgba(6, 182, 212, 0.1); }

  /* ── Section Row (Telegram Body AAA, BBB, CCC...) ── */
  .sec-item {
    background: var(--bg-card-subtle);
    border: 1.5px solid var(--border-color);
    border-radius: 0.75rem;
    padding: 0.85rem;
    transition: all 0.2s ease;
  }
  .sec-item:focus-within {
    border-color: var(--primary);
    background: var(--bg-card);
  }
  .sec-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
  }
  .sec-code-badge {
    font-family: 'JetBrains Mono', monospace;
    font-weight: 800;
    font-size: 0.75rem;
    color: var(--primary);
    background: var(--primary-light);
    padding: 0.2rem 0.6rem;
    border-radius: 0.45rem;
    border: 1px solid rgba(249, 115, 22, 0.25);
    letter-spacing: 0.05em;
  }
  .sec-hint-title {
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--text-muted);
  }
  .sec-del-btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.68rem;
    font-weight: 600;
    color: var(--danger);
    background: transparent;
    border: 1px solid transparent;
    border-radius: 0.4rem;
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
  }
  .sec-del-btn:hover {
    background: var(--danger-light);
    border-color: rgba(239, 68, 68, 0.3);
  }

  /* ── Sticky Action Bar ── */
  .bra-sticky-bar {
    position: sticky;
    bottom: 0.85rem;
    z-index: 30;
    background: var(--bg-card);
    border: 1.5px solid var(--border-color);
    border-radius: 1rem;
    padding: 0.75rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.08);
    backdrop-filter: blur(12px);
  }
  [data-theme="dark"] .bra-sticky-bar {
    background: rgba(30, 41, 59, 0.95);
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
  }

  .btn-primary-bra {
    padding: 0.65rem 1.6rem;
    font-size: 0.8rem;
    font-weight: 800;
    color: #ffffff;
    background: linear-gradient(135deg, var(--primary), #ea580c);
    border: none;
    border-radius: 0.65rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.3);
  }
  .btn-primary-bra:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(249, 115, 22, 0.4);
  }
  .btn-primary-bra:active { transform: scale(0.98); }

  .btn-outline-bra {
    padding: 0.55rem 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 0.6rem;
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-main);
    cursor: pointer;
    transition: all 0.15s ease;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
  }
  .btn-outline-bra:hover {
    border-color: var(--primary);
    color: var(--primary);
  }

  /* ── 100% Exact Official Basarnas Telegram Paper Stylesheet ── */
  .bra-official-paper {
    background: #ffffff !important;
    color: #000000 !important;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
    border: none !important;
    box-shadow: none !important;
    outline: none !important;
    width: 100%;
    margin: 0 auto;
    padding: 0 !important;
    transition: all 0.2s ease;
  }
  .bra-official-paper * {
    color: #000000 !important;
    border-color: #000000 !important;
  }
  .bra-official-paper .bra-kop {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding-bottom: 0.4rem;
    margin-bottom: 0.4rem;
    border-bottom: 2px solid #000000;
  }
  .bra-official-paper .senkom-vertical-label {
    user-select: none;
    pointer-events: none;
  }

  /* ── Modal Dialog Styles ── */
  .bra-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }
  .bra-modal-overlay.open { display: flex; }
  .bra-modal-overlay.hidden { display: none !important; }
  .bra-modal-card {
    background: var(--bg-card);
    border: 1.5px solid var(--border-color);
    border-radius: 1rem;
    max-width: 58rem;
    width: 100%;
    max-height: 92vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
  }
  .bra-modal-header {
    padding: 0.9rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
  }
  .bra-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: #cbd5e1;
    display: flex;
    justify-content: center;
  }
  [data-theme="dark"] .bra-modal-body {
    background: #090d16;
  }

  @media print {
    @page {
      size: A4 portrait;
      margin: 5mm 15mm 15mm 15mm;
    }
    body, html {
      background: #ffffff !important;
      color: #000000 !important;
      padding: 0 !important;
      margin: 0 !important;
      overflow: visible !important;
      height: auto !important;
      min-height: auto !important;
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    nav, aside, header, .sidebar, .navbar, .top-navbar,
    .bra-sticky-bar, .bra-card, .bra-stepper-wrap, .bra-modal-header,
    .btn-primary-bra, .btn-outline-bra, .bra-modal-overlay,
    .print\:hidden, [class*="print:hidden"] {
      display: none !important;
    }
    .app-container, .main-content, .page-body,
    .bra-layout-container, .bra-split-grid, .bra-live-sticky-preview {
      display: block !important;
      width: 100% !important;
      position: static !important;
      overflow: visible !important;
      height: auto !important;
      min-height: auto !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    #formBraSar {
      display: none !important;
    }
    .bra-official-paper {
      border: none !important;
      box-shadow: none !important;
      outline: none !important;
      width: 180mm !important;
      max-width: 180mm !important;
      padding: 0 4px 0 0 !important;
      margin: 0 auto !important;
      box-sizing: border-box !important;
      transform: none !important;
      page-break-inside: auto !important;
      break-inside: auto !important;
    }
    .bra-table-wrapper {
      box-sizing: border-box !important;
      max-width: 100% !important;
    }
    .bra-table-box {
      border: 2px solid #000000 !important;
      box-sizing: border-box !important;
      width: 100% !important;
      max-width: 100% !important;
      overflow-x: hidden !important;
    }
  }
</style>

<div class="space-y-5 bra-layout-container" id="braMainAppWrap">

  <!-- ════════════════════════════════════════════════════════ -->
  <!-- TOP HEADER & CONTROLS TOOLBAR                           -->
  <!-- ════════════════════════════════════════════════════════ -->
  <div class="print:hidden flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-2xl shadow-md border transition-colors"
       style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border-color: #334155;">
    
    <div class="flex items-center gap-3">
      <a href="<?= BASE_URL ?>/bra.php" class="p-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white/80 hover:text-white transition" title="Kembali ke Arsip Dokumen BRA">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
      </a>
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-base sm:text-lg font-black text-white tracking-tight">
            <?= $isEdit ? 'Edit Naskah Berita Radio SAR' : 'Pembuatan Berita Radio SAR (BRA SAR)' ?>
          </h1>
          <span class="text-[10px] font-mono px-2 py-0.5 rounded-full font-bold uppercase tracking-wider" style="background: var(--primary); color: #ffffff;">
            <?= $isEdit ? 'MODE EDIT' : 'DRAF BARU' ?>
          </span>
        </div>
        <p class="text-xs text-slate-300 mt-0.5">
          Formulir Naskah Telegram Radio Resmi Standar Badan Nasional Pencarian dan Pertolongan &bull; Satker: <strong><?= htmlspecialchars($satkerKode) ?></strong>
        </p>
      </div>
    </div>

    <!-- Toolbar: View Switcher, Format Kapital & Preview Modal -->
    <div class="flex flex-wrap items-center gap-2">
      <!-- Split View Toggle Button (Desktop) -->
      <button type="button" id="btnToggleSplitView" class="hidden xl:inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold rounded-lg border text-white transition-colors" style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2);">
        <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
        <span id="splitViewLabel">Split Pratinjau</span>
      </button>

      <!-- Telegram Upper & Punctuation Format Helper -->
      <button type="button" id="btnFormatTelegramPunctuation" class="btn-outline-bra text-xs" style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); color: #ffffff;" title="Format otomatis huruf kapital dan sandi tanda baca militer (TTK, KMA, TTK DUA)">
        🔠 Format Sandi Telegram
      </button>

      <!-- Pratinjau Modal -->
      <button type="button" id="btnOpenModalPreview" class="btn-outline-bra text-xs" style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.2); color: #ffffff;">
        <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
        Pratinjau Lembar Resmi
      </button>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════ -->
  <!-- STEPPER NAVIGATION                                      -->
  <!-- ════════════════════════════════════════════════════════ -->
  <div class="bra-stepper-wrap">
    <div class="bra-stepper" id="braStepperBar">
      <a href="#sec-kategori" class="bra-step-btn active" data-target="sec-kategori">
        <span class="bra-step-num">1</span>
        <span>Kategori &amp; Operasi</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-nomor" class="bra-step-btn" data-target="sec-nomor">
        <span class="bra-step-num">2</span>
        <span>Penomoran &amp; Administrasi</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-waktu-alamat" class="bra-step-btn" data-target="sec-waktu-alamat">
        <span class="bra-step-num">3</span>
        <span>Waktu Militer &amp; Alamat</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-batang-tubuh" class="bra-step-btn" data-target="sec-batang-tubuh">
        <span class="bra-step-num">4</span>
        <span>Batang Tubuh (AAA, BBB, CCC)</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-pengirim" class="bra-step-btn" data-target="sec-pengirim">
        <span class="bra-step-num">5</span>
        <span>Pengirim &amp; Senkom</span>
      </a>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════ -->
  <!-- MAIN SPLIT WORKSPACE: FORM (LEFT) + LIVE PAPER (RIGHT) -->
  <!-- ════════════════════════════════════════════════════════ -->
  <div class="bra-split-grid">

    <!-- ── FORM COLUMN ── -->
    <div class="space-y-5" id="braFormContainer">
      <form method="POST" action="<?= BASE_URL ?>/bra.php?action=save<?= $isEdit ? '&id=' . $id : '' ?>" id="braMasterForm" class="space-y-5">
        <?= csrfInput() ?>
        <?php if ($isEdit): ?>
          <input type="hidden" name="id_bra" value="<?= $id ?>">
        <?php endif; ?>
        <input type="hidden" name="satker_kode" value="<?= htmlspecialchars($satkerKode) ?>">
        <input type="hidden" name="bulan_tahun" id="hiddenBulanTahun" value="<?= htmlspecialchars($bra['bulan_tahun'] ?? $bulanTahunPlaceholder) ?>">
        <input type="hidden" name="nomor_urut" id="hiddenNomorUrut" value="<?= htmlspecialchars((string)($bra['nomor_urut'] ?? $nomorUrutPlaceholder)) ?>">
        <input type="hidden" name="nama_operasi" id="hiddenNamaOperasi" value="<?= htmlspecialchars($bra['nama_operasi'] ?? ($selectedOps['nama_operasi'] ?? '')) ?>">
        <input type="hidden" name="tanggal_waktu" id="hiddenTanggalWaktu" value="<?= htmlspecialchars($bra['tanggal_waktu'] ?? TemplateHelper::formatTanggalIndo(date('Y-m-d'))) ?>">
        <input type="hidden" name="jumlah_kata" id="hiddenJumlahKata" value="<?= htmlspecialchars($bra['jumlah_kata'] ?? '0 KATA') ?>">
        <input type="hidden" name="logo_url" value="<?= htmlspecialchars($customLogo) ?>">

        <!-- ────────────────────────────────────────────── -->
        <!-- SEKSI 1: KATEGORI & TAUTAN OPERASI SAR        -->
        <!-- ────────────────────────────────────────────── -->
        <div class="bra-card" id="sec-kategori">
          <div class="bra-card-header">
            <div>
              <div class="bra-card-title">
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-black text-white" style="background: var(--primary);">1</span>
                <span>Pilih Kategori Dokumen Berita Radio SAR</span>
              </div>
              <div class="bra-card-desc">Tentukan jenis berita resmi dan tautkan dengan data Operasi SAR aktif jika tersedia</div>
            </div>
            <span class="text-[11px] font-bold text-orange-600 dark:text-orange-400 font-mono">STANDAR BASARNAS</span>
          </div>

          <!-- 3 Kategori Selector Tabs (Identik dengan Referensi Resmi FormBraSar) -->
          <div class="kat-tabs mb-4">
            <!-- 1. MAPEL -->
            <label class="kat-tab-label">
              <input type="radio" name="kategori" value="MAPEL" <?= $currentKategori === 'MAPEL' ? 'checked' : '' ?> class="kategori-radio">
              <div class="kat-tab-box">
                <div class="kat-icon-wrap">📡</div>
                <div style="flex:1;">
                  <div class="kat-tab-header">
                    <span class="kat-tab-title">1. BRA Mapel</span>
                    <span class="kat-tab-pill">Broadcast Notice</span>
                  </div>
                  <div class="kat-tab-desc">Pemberitahuan kepada Syahbandar, KSOP, SROP, dan kapal-kapal yang melintas untuk pemantauan/pemapelan korban.</div>
                </div>
              </div>
            </label>

            <!-- 2. OPERASI SAR -->
            <label class="kat-tab-label">
              <input type="radio" name="kategori" value="OPERASI_SAR" <?= $currentKategori === 'OPERASI_SAR' ? 'checked' : '' ?> class="kategori-radio">
              <div class="kat-tab-box">
                <div class="kat-icon-wrap">📋</div>
                <div style="flex:1;">
                  <div class="kat-tab-header">
                    <span class="kat-tab-title">2. BRA Operasi SAR</span>
                    <span class="kat-tab-pill">Operasi SAR Harian</span>
                  </div>
                  <div class="kat-tab-desc">Berita Radio perkembangan Operasi SAR kepada Kabasarnas, Pimti, Polairud, TNI AL terkait laporan awal / renops / sitrep.</div>
                </div>
              </div>
            </label>

            <!-- 3. PERGERAKAN KAPAL -->
            <label class="kat-tab-label">
              <input type="radio" name="kategori" value="PERGERAKAN_KAPAL" <?= $currentKategori === 'PERGERAKAN_KAPAL' ? 'checked' : '' ?> class="kategori-radio">
              <div class="kat-tab-box">
                <div class="kat-icon-wrap">🚢</div>
                <div style="flex:1;">
                  <div class="kat-tab-header">
                    <span class="kat-tab-title">3. BRA Pergerakan Kapal</span>
                    <span class="kat-tab-pill">Alut Laut &amp; BKO</span>
                  </div>
                  <div class="kat-tab-desc">Pergerakan kapal SAR (KN SAR Pacitan 102 / RB 210) dalam rangka BKO Operasi, pemanasan bergerak, atau pengiriman alut.</div>
                </div>
              </div>
            </label>
          </div>

          <!-- Quick MAPEL Presets Bar (Muncul Khusus MAPEL) -->
          <div id="mapelPresetsContainer" class="bra-preset-banner mb-4 <?= $currentKategori === 'MAPEL' ? '' : 'hidden' ?>" style="border-color: rgba(59, 130, 246, 0.4); background: rgba(59, 130, 246, 0.08); display: <?= $currentKategori === 'MAPEL' ? 'block' : 'none' ?>;">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-2.5 pb-2.5 border-b border-blue-500/20">
              <div class="flex items-center gap-2 text-xs font-bold text-blue-900 dark:text-blue-300">
                <span class="text-base">📡</span>
                <div>
                  <span class="tracking-wide">Format Telegram Pemapelan Resmi (Senkom KPP Kendari)</span>
                  <span class="block text-[11px] font-normal text-muted dark:text-blue-200/70">Pemberitahuan resmi kepada KSOP, SROP, dan kapal-kapal yang melintas di sekitar LKP:</span>
                </div>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
              <span class="text-[11px] font-semibold text-blue-900 dark:text-blue-200">Aksi Cepat:</span>
              <button type="button" id="btnPresetLongboatHilangKontak" class="preset-btn flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white border-blue-700 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition" title="Muat draf telegram pemapelan resmi: 1. Longboat / Kapal Hilang Kontak">
                <span>🚤</span> <span>1. Longboat / Kapal Hilang Kontak</span>
              </button>
            </div>
          </div>

          <!-- Quick Vessel Presets Bar (Muncul Khusus PERGERAKAN_KAPAL) (ISSUE-10) -->
          <div id="vesselPresetsContainer" class="bra-preset-banner mb-4 <?= $currentKategori === 'PERGERAKAN_KAPAL' ? '' : 'hidden' ?>" style="border-color: rgba(6, 182, 212, 0.4); background: rgba(6, 182, 212, 0.08); display: <?= $currentKategori === 'PERGERAKAN_KAPAL' ? 'block' : 'none' ?>;">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-2.5 pb-2.5 border-b border-cyan-500/20">
              <div class="flex items-center gap-2 text-xs font-bold text-cyan-800 dark:text-cyan-300">
                <span class="text-base">🚢</span>
                <div>
                  <span class="tracking-wide">Format Telegram Pergerakan Alut Laut Resmi (KPP Kendari)</span>
                  <span class="block text-[11px] font-normal text-muted dark:text-cyan-200/70">Pilih salah satu dari 3 kapal resmi dan jenis pergerakan operasional:</span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <label for="selectVesselChoice" class="text-[11px] font-bold uppercase tracking-wider text-cyan-800 dark:text-cyan-300 shrink-0">Kapal:</label>
                <select id="selectVesselChoice" class="text-xs font-bold py-1.5 px-2.5 rounded-lg bg-white dark:bg-slate-800 border border-cyan-500/40 text-cyan-900 dark:text-cyan-200 cursor-pointer shadow-sm focus:outline-none focus:ring-1 focus:ring-cyan-500">
                  <option value="KN_SAR_PACITAN">⚓ KN SAR PACITAN 102</option>
                  <option value="RB_210">🚤 RESCUE BOAT 210</option>
                  <option value="RB_307">🚤 RESCUE BOAT 307</option>
                </select>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
              <span class="text-[11px] font-semibold text-cyan-900 dark:text-cyan-200">Aksi Gerak:</span>
              <button type="button" id="btnVesselTolak" class="preset-btn vessel flex items-center gap-1.5" title="Muat naskah Tolak / Keberangkatan Pelayaran">
                <span>🛫</span> <span>Tolak / Berlayar</span>
              </button>
              <button type="button" id="btnVesselPantauan" class="preset-btn vessel flex items-center gap-1.5" title="Muat naskah Pantauan Posisi & Cuaca Saat Berlayar">
                <span>🧭</span> <span>Pantauan Posisi</span>
              </button>
              <button type="button" id="btnVesselSandar" class="preset-btn vessel flex items-center gap-1.5" title="Muat naskah Sandar / Tiba di Pangkalan Dermaga">
                <span>⚓</span> <span>Sandar / Tiba</span>
              </button>
              <div class="hidden sm:inline-block w-px h-5 bg-cyan-500/30 mx-1"></div>
              <span class="text-[10px] font-medium text-cyan-800/80 dark:text-cyan-300/80 hidden lg:inline">Pintasan Cepat:</span>
              <button type="button" id="btnPresetPacitan" class="preset-btn vessel text-[11px] opacity-85 hover:opacity-100" title="Cepat: Muat KN SAR Pacitan 102 (BKO)">
                ⚓ Pacitan 102
              </button>
              <button type="button" id="btnPresetRB210" class="preset-btn vessel text-[11px] opacity-85 hover:opacity-100" title="Cepat: Muat RB 210 (Pemanasan)">
                🚤 RB 210
              </button>
              <button type="button" id="btnPresetRB307" class="preset-btn vessel text-[11px] opacity-85 hover:opacity-100" title="Cepat: Muat RB 307 (Patroli)">
                🚤 RB 307
              </button>
            </div>
          </div>

          <!-- Operasi SAR Connection Select -->
          <div class="bra-field">
            <label class="bra-label">
              <span>Tautkan ke Operasi SAR Aktif (Opsional)</span>
              <?php if (!empty($operasiList)): ?>
                <span class="text-[11px] font-normal text-muted"><?= count($operasiList) ?> Operasi Terdaftar</span>
              <?php endif; ?>
            </label>
            <select name="id_operasi" id="operasiSelect" class="bra-control">
              <option value="" data-nama="">— Buat Mandiri / Tanpa Kaitan Operasi SAR —</option>
              <?php foreach ($operasiList as $op): ?>
                <option value="<?= $op['id_operasi'] ?>" data-nama="<?= htmlspecialchars($op['nama_operasi']) ?>" <?= ($currentOpId === (int)$op['id_operasi']) ? 'selected' : '' ?>>
                  #OPS-<?= $op['id_operasi'] ?> &bull; <?= htmlspecialchars($op['nama_operasi']) ?> (Status: <?= htmlspecialchars($op['status'] ?? 'OPEN') ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <span class="bra-hint">Pilih operasi SAR untuk mengaktifkan fitur asisten pengisian otomatis data kronologi, LKP, alut, cuaca, dan personil.</span>
          </div>

          <!-- Smart Recommendation Toolbar (Terkait Operasi SAR) -->
          <div id="operasiRecommendationBar" class="bra-preset-banner mt-3 <?= $currentOpId ? '' : 'hidden' ?>" style="border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.08);">
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-800 dark:text-amber-300">
              <span>⚡</span>
              <span><strong>Rekomendasi Draf Naskah Otomatis:</strong> Pilih jenis naskah laporan:</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" id="btnAutoInisiasi" class="preset-btn" title="Muat naskah Laporan Awal (Inisiasi)">
                📄 Laporan Awal (Inisiasi)
              </button>
              <button type="button" id="btnAutoRenops" class="preset-btn" title="Muat Rencana Operasi harian terakhir">
                🎯 Renops Terakhir
              </button>
              <button type="button" id="btnAutoSitrep" class="preset-btn" title="Muat Laporan Perkembangan harian terakhir">
                📊 Lapbang (SITREP)
              </button>
              <button type="button" id="btnAutoPenutupan" class="preset-btn" title="Muat naskah Penutupan Operasi SAR">
                🔒 Penutupan Operasi
              </button>
              <button type="button" id="btnAutoMapel" class="preset-btn" title="Muat naskah Pemapelan Maritim dari data Operasi SAR">
                📡 Pemapelan (Mapel)
              </button>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────── -->
        <!-- SEKSI 2: PENOMORAN & ADMINISTRASI TELEGRAM     -->
        <!-- ────────────────────────────────────────────── -->
        <div class="bra-card" id="sec-nomor">
          <div class="bra-card-header">
            <div>
              <div class="bra-card-title">
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-black text-white" style="background: var(--info);">2</span>
                <span>Penomoran Naskah &amp; Administrasi SENKOM</span>
              </div>
              <div class="bra-card-desc">Nomor berita radio, derajat urgensi, dan rujukan naskah sebelumnya</div>
            </div>
            <div class="flex items-center gap-1.5 text-[10.5px] font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-300 dark:border-emerald-600/30">
              <span>🔒 Proteksi Urut Otomatis Aktif</span>
            </div>
          </div>

          <div class="bra-grid-3 mb-3">
            <!-- No BRA -->
            <div class="bra-field">
              <label class="bra-label">Nomor Naskah BRA <span class="req">*</span></label>
              <input type="text" name="no_bra" id="noBraInput" value="<?= htmlspecialchars(!empty($bra['no_bra']) ? $bra['no_bra'] : $noBraPlaceholder) ?>" placeholder="<?= $noBraPlaceholder ?>" class="bra-control mono bold upper" required>
              <input type="hidden" name="is_auto_no_bra" id="isAutoNoBra" value="<?= $isEdit ? '0' : '1' ?>">
              <span class="bra-hint">Format resmi: <code>[Nomor Urut]/<?= htmlspecialchars($satkerKode) ?>/[BulanTahun]</code></span>
              <div id="noBraWarning" class="hidden mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 rounded px-2 py-1"></div>
            </div>

            <!-- Derajat -->
            <div class="bra-field">
              <label class="bra-label">
                <span>Derajat Pengiriman <span class="req">*</span></span>
              </label>
              <div class="flex gap-1.5">
                <input type="text" name="derajat" id="derajatInput" value="<?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?>" placeholder="SGR" class="bra-control mono bold upper" required style="flex:1;">
                <button type="button" onclick="setDerajat('SGR')" class="preset-btn text-[10px]">SGR</button>
                <button type="button" onclick="setDerajat('KILAT')" class="preset-btn text-[10px]">KILAT</button>
                <button type="button" onclick="setDerajat('RUTIN')" class="preset-btn text-[10px]">RUTIN</button>
              </div>
              <span class="bra-hint">SGR = Segera (Default Basarnas), KILAT, atau RUTIN</span>
            </div>

            <!-- MENBRA (Rujukan Naskah Lalu) (ISSUE-08) -->
            <div class="bra-field" id="menbraFieldWrapper">
              <label class="bra-label" id="menbraLabelText">
                <span id="labelMenbraTitle">MENBRA (Rujukan BRA Lalu)</span>
              </label>
              <input type="text" name="menbra" id="menbraInput" value="<?= (strtoupper(trim((string)($bra['menbra'] ?? ''))) === 'SEGERA' || trim((string)($bra['menbra'] ?? '')) === '-') ? '' : htmlspecialchars($bra['menbra'] ?? '') ?>" placeholder="<?= max(1, $nomorUrutPlaceholder - 1) ?>/<?= $satkerKode ?>/<?= $bulanTahunPlaceholder ?>" class="bra-control mono upper">
              <span class="bra-hint" id="hintMenbraText">Nomor naskah BRA sebelumnya yang menjadi dasar rujukan (bila ada). Biarkan kosong jika naskah baru / tanpa rujukan.</span>
            </div>
          </div>

          <!-- Accordion Header Senkom Ekstra (Panggilan, Jenis, Nr. Stn, Instruksi) -->
          <details class="text-xs border rounded-xl p-3 bg-card-subtle transition" style="border-color: var(--border-color);">
            <summary class="font-bold cursor-pointer text-muted hover:text-main flex items-center justify-between select-none">
              <span>⚙️ Rincian Baris Header Senkom Lengkap (Panggilan, Jenis, Nr. Stn, Instruksi Penerusan)</span>
              <span class="text-[10px] font-normal text-muted">(Klik untuk buka / tutup)</span>
            </summary>
            <div class="bra-grid-4 mt-3 pt-3 border-t" style="border-color: var(--border-color);">
              <div class="bra-field">
                <label class="bra-label">Panggilan</label>
                <input type="text" name="panggilan" id="panggilanInput" value="<?= htmlspecialchars($bra['panggilan'] ?? '') ?>" class="bra-control mono" placeholder="—">
              </div>
              <div class="bra-field">
                <label class="bra-label">Jenis</label>
                <input type="text" name="jenis" id="jenisInput" value="<?= htmlspecialchars($bra['jenis'] ?? '') ?>" class="bra-control mono" placeholder="SAR">
              </div>
              <div class="bra-field">
                <label class="bra-label">Nr. Stn</label>
                <input type="text" name="nr_stn" id="nrStnInput" value="<?= htmlspecialchars($bra['nr_stn'] ?? '') ?>" class="bra-control mono" placeholder="—">
              </div>
              <div class="bra-field">
                <label class="bra-label">Instruksi Penerusan</label>
                <input type="text" name="instruksi_penerusan" id="instruksiPenerusanInput" value="<?= htmlspecialchars($bra['instruksi_penerusan'] ?? '') ?>" class="bra-control mono" placeholder="—">
              </div>
            </div>
          </details>
        </div>

        <!-- ────────────────────────────────────────────── -->
        <!-- SEKSI 3: WAKTU MILITER & ALAMAT TELEGRAM       -->
        <!-- ────────────────────────────────────────────── -->
        <div class="bra-card" id="sec-waktu-alamat">
          <div class="bra-card-header">
            <div>
              <div class="bra-card-title">
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-black text-white" style="background: var(--warning);">3</span>
                <span>Format Waktu Militer Basarnas &amp; Alamat Distribusi</span>
              </div>
              <div class="bra-card-desc">TWU (Waktu Kirim), TWB (Waktu Buat/Kejadian), dan alamat kedinasan resmi</div>
            </div>
            <span class="text-[11px] font-mono font-bold text-muted">ZONA HOTEL (WITA / UTC+8)</span>
          </div>

          <!-- TW Cards Grid -->
          <div class="bra-grid-2 mb-4">
            <!-- TWB -->
            <div class="p-3 rounded-xl border" style="background: var(--bg-card-subtle); border-color: var(--border-color);">
              <div class="flex items-center justify-between mb-1">
                <label class="bra-label">
                  <span>TWB (Tanggal Waktu Buat / Kejadian) <span class="req">*</span></span>
                </label>
                <button type="button" id="btnSetTwbNow" class="action-link text-xs">
                  ⚡ Waktu Sekarang
                </button>
              </div>
              <input type="text" name="twb" id="twbInput" value="<?= htmlspecialchars($bra['twb'] ?? '') ?>" placeholder="<?= $twbPlaceholder ?>" class="bra-control mono bold upper text-sm" required>
              <div class="flex items-center justify-between mt-1 text-[10px] text-muted">
                <span>Format: <code>MMDD HHmm H</code></span>
                <span>Contoh: <strong>0920 0645 H</strong></span>
              </div>
            </div>

            <!-- TWU -->
            <div class="p-3 rounded-xl border" style="background: var(--bg-card-subtle); border-color: var(--border-color);">
              <div class="flex items-center justify-between mb-1">
                <label class="bra-label">
                  <span>TWU (Tanggal Waktu Urut / Kirim) <span class="req">*</span></span>
                </label>
                <button type="button" id="btnSetTwuNow" class="action-link text-xs">
                  ⚡ Waktu Sekarang
                </button>
              </div>
              <input type="text" name="twu" id="twuInput" value="<?= htmlspecialchars($bra['twu'] ?? '') ?>" placeholder="<?= $twuPlaceholder ?>" class="bra-control mono bold upper text-sm" required>
              <div class="flex items-center justify-between mt-1 text-[10px] text-muted">
                <span>Dicetak di sudut kanan atas telegram</span>
                <span>Contoh: <strong>0920 0700 H</strong></span>
              </div>
            </div>
          </div>

          <!-- Address Fields: DARI, AKSI, INFO, PERIHAL -->
          <div class="space-y-3">
            <div class="bra-field">
              <label class="bra-label">DARI (Satker Asal Pengirim) <span class="req">*</span></label>
              <input type="text" name="dari" id="dariInput" value="<?= htmlspecialchars($bra['dari'] ?? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?>" class="bra-control bold upper" required>
            </div>

            <div class="bra-field">
              <label class="bra-label">AKSI (Alamat Tujuan Utama Tindak Lanjut) <span class="req">*</span></label>
              <textarea name="aksi" id="aksiInput" rows="2" class="bra-control upper font-semibold" placeholder="<?= $currentKategori === 'MAPEL' ? 'KA KSOP KENDARI DAN KMA KA SROP KENDARI' : 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN' ?>" required><?= htmlspecialchars($bra['aksi'] ?? ($currentKategori === 'MAPEL' ? 'KA KSOP KENDARI KMA KA SROP KENDARI' : '')) ?></textarea>
            </div>

            <div class="bra-field">
              <label class="bra-label">INFO (Tembusan Informasi)</label>
              <textarea name="info" id="infoInput" rows="2" class="bra-control upper" placeholder="KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN"><?= htmlspecialchars($bra['info'] ?? ($currentKategori === 'MAPEL' ? 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN' : '')) ?></textarea>
            </div>

            <div class="bra-field">
              <label class="bra-label">PERIHAL / Judul Berita Radio SAR <span class="req">*</span></label>
              <textarea name="perihal" id="perihalInput" rows="2" class="bra-control bold upper text-orange-600 dark:text-orange-400" placeholder="KECELAKAAN KAPAL..." required><?= htmlspecialchars($bra['perihal'] ?? '') ?></textarea>
              <span class="bra-hint">Judul naskah resmi telegram diakhiri dengan sandi <code>TTK</code></span>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────── -->
        <!-- SEKSI 4: BATANG TUBUH TELEGRAM (AAA, BBB, CCC) -->
        <!-- ────────────────────────────────────────────── -->
        <div class="bra-card" id="sec-batang-tubuh">
          <div class="bra-card-header">
            <div>
              <div class="bra-card-title">
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-black text-white" style="background: var(--primary);">4</span>
                <span>Batang Tubuh Naskah Berita Radio SAR</span>
              </div>
              <div class="bra-card-desc">Seksi bertingkat AAA, BBB, CCC, dst. Sesuai tata naskah telegram resmi militer</div>
            </div>
            <div class="flex items-center gap-2">
              <span id="liveWordCountBadge" class="text-[11px] font-mono font-bold px-2.5 py-1 rounded-lg" style="background: var(--bg-card-subtle); border: 1px solid var(--border-color); color: var(--text-main);">
                0 KATA
              </span>
              <button type="button" id="btnAddSectionBtn" class="btn-primary-bra" style="padding: 0.45rem 0.9rem; font-size: 0.75rem;">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span>+ Tambah Bagian</span>
              </button>
            </div>
          </div>

          <p class="text-xs text-muted mb-3">
            Minimal wajib terdapat bagian <strong>AAA</strong>, <strong>BBB</strong>, dan <strong>CCC</strong>. Anda dapat menambahkan <strong>DDD</strong>, <strong>EEE</strong>, dst sesuai kebutuhan operasional.
          </p>

          <div id="sectionsListContainer" class="space-y-3">
            <?php
              $sections = $bra['sections'] ?? [
                  ['kode' => 'AAA', 'isi' => ''],
                  ['kode' => 'BBB', 'isi' => ''],
                  ['kode' => 'CCC', 'isi' => ''],
              ];
            ?>
            <?php foreach ($sections as $index => $sec): ?>
              <div class="sec-item section-block" data-index="<?= $index ?>">
                <div class="sec-item-header">
                  <div class="flex items-center gap-2">
                    <span class="sec-code-badge"><?= htmlspecialchars($sec['kode']) ?></span>
                    <input type="hidden" name="sections[<?= $index ?>][kode]" value="<?= htmlspecialchars($sec['kode']) ?>" class="sec-code-input">
                    <?php if (!empty($sec['id'])): ?>
                      <input type="hidden" name="sections[<?= $index ?>][id]" value="<?= (int)$sec['id'] ?>">
                    <?php endif; ?>
                    <span class="sec-hint-title sec-hint-label">
                      <?= $index === 0 ? '(Kronologi / Informasi Awal / Dasar Pelayaran)' : ($index === 1 ? '(Unsur SAR Terlibat, Alut & Palsar / Rencana Pelayaran)' : ($index === 2 ? '(Penutup / Laporan Perkembangan Menyusul)' : '(Bagian Tambahan)')) ?>
                    </span>
                  </div>
                  <?php if ($index >= 3): ?>
                    <button type="button" class="sec-del-btn btn-delete-section" title="Hapus bagian ini">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      <span>Hapus</span>
                    </button>
                  <?php endif; ?>
                </div>
                <textarea name="sections[<?= $index ?>][isi]" rows="4" class="bra-control sec-content-textarea upper" placeholder="Isi naskah telegram untuk bagian <?= htmlspecialchars($sec['kode']) ?>..." required><?= htmlspecialchars($sec['isi'] ?? '') ?></textarea>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- ────────────────────────────────────────────── -->
        <!-- SEKSI 5: PENGIRIM (SMC) & CATATAN SENKOM       -->
        <!-- ────────────────────────────────────────────── -->
        <div class="bra-card" id="sec-pengirim">
          <div class="bra-card-header">
            <div>
              <div class="bra-card-title">
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-black text-white" style="background: var(--success);">5</span>
                <span>Pengirim (SMC) &amp; Register Stasiun Radio SENKOM</span>
              </div>
              <div class="bra-card-desc">Penandatangan naskah, Barcode QR Digital, dan register buku komunikasi</div>
            </div>
            <div class="flex items-center gap-1.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
              <span>QR Code Terverifikasi</span>
            </div>
          </div>

          <!-- Pejabat Preset Selector -->
          <?php if (!empty($pejabatList)): ?>
            <div class="bra-field mb-3 p-3 rounded-xl border bg-card-subtle" style="border-color: var(--border-color);">
              <label class="bra-label">
                <span>Pilih Cepat dari Master Pejabat / SMC</span>
                <span class="text-[10px] text-muted">Nama, Pangkat &amp; Jabatan Otomatis Terisi</span>
              </label>
              <select id="pejabatSelectControl" class="bra-control">
                <option value="">— Pilih Pejabat Penandatangan —</option>
                <?php foreach ($pejabatList as $p): ?>
                  <option value="<?= htmlspecialchars($p['id'] ?? '') ?>"
                          data-nama="<?= htmlspecialchars($p['nama'] ?? '') ?>"
                          data-pangkat="<?= htmlspecialchars($p['pangkat'] ?? '') ?>"
                          data-jabatan="<?= htmlspecialchars($p['jabatan'] ?? '') ?>"
                          <?= (!empty($p['is_default_smc']) && empty($bra['nama_smc'])) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['nama']) ?> &bull; <?= htmlspecialchars($p['pangkat'] ?? '-') ?> (<?= htmlspecialchars($p['jabatan'] ?? '-') ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          <?php endif; ?>

          <div class="bra-grid-2 mb-3">
            <div class="bra-field">
              <label class="bra-label">Instansi Pengirim <span class="req">*</span></label>
              <input type="text" name="pengirim" id="pengirimInput" value="<?= htmlspecialchars($bra['pengirim'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?>" class="bra-control bold upper" required>
            </div>
            <div class="bra-field">
              <label class="bra-label">Nama Lengkap Pejabat / SMC <span class="req">*</span></label>
              <input type="text" name="nama_smc" id="namaSmcInput" value="<?= htmlspecialchars($bra['nama_smc'] ?? 'AMIRUDDIN A.S., S.Sos.') ?>" class="bra-control bold upper" required>
            </div>
          </div>

          <div class="bra-grid-2 mb-4">
            <div class="bra-field">
              <label class="bra-label">Pangkat / Golongan <span class="req">*</span></label>
              <input type="text" name="pangkat_smc" id="pangkatSmcInput" value="<?= htmlspecialchars($bra['pangkat_smc'] ?? 'PEMBINA (IV/a)') ?>" class="bra-control upper" required>
            </div>
            <div class="bra-field">
              <label class="bra-label">Jabatan Kedinasan <span class="req">*</span></label>
              <input type="text" name="jabatan_smc" id="jabatanSmcInput" value="<?= htmlspecialchars($bra['jabatan_smc'] ?? ($currentKategori === 'MAPEL' || !$currentOpId ? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI' : 'SMC')) ?>" class="bra-control bold upper" required>
            </div>
          </div>

          <!-- Register SENKOM Ledger -->
          <div class="pt-3 border-t" style="border-color: var(--border-color);">
            <div class="bra-label mb-2">
              <span>Buku Komunikasi Radio SENKOM (Bisa Dilengkapi Operator Radio)</span>
              <span class="text-[10px] font-normal text-muted">Kolom Sudut Kanan Bawah Telegram</span>
            </div>
            <div class="bra-grid-4">
              <div class="bra-field">
                <label class="bra-label">T.W.T (Waktu Terima)</label>
                <input type="text" name="twt" id="twtInput" value="<?= htmlspecialchars($bra['twt'] ?? '') ?>" class="bra-control mono text-center text-xs" placeholder="—">
              </div>
              <div class="bra-field">
                <label class="bra-label">T.W.K (Waktu Kirim)</label>
                <input type="text" name="twk" id="twkInput" value="<?= htmlspecialchars($bra['twk'] ?? '') ?>" class="bra-control mono text-center text-xs" placeholder="—">
              </div>
              <div class="bra-field">
                <label class="bra-label">SISTEM (Frekuensi)</label>
                <input type="text" name="sistem" id="sistemInput" value="<?= htmlspecialchars($bra['sistem'] ?? '') ?>" class="bra-control mono text-center text-xs" placeholder="HF / VHF / SSB">
              </div>
              <div class="bra-field">
                <label class="bra-label">PARAF Operator</label>
                <input type="text" name="paraf" id="parafInput" value="<?= htmlspecialchars($bra['paraf'] ?? '') ?>" class="bra-control mono text-center text-xs" placeholder="Operator">
              </div>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────── -->
        <!-- STICKY ACTION FOOTER BAR                       -->
        <!-- ────────────────────────────────────────────── -->
        <div class="bra-sticky-bar">
          <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/bra.php" class="btn-outline-bra">
              Batal
            </a>
            <button type="button" id="btnOpenModalPreviewBottom" class="btn-outline-bra">
              <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              <span>Pratinjau Lembar Dokumen</span>
            </button>
          </div>

          <button type="submit" id="btnSubmitBra" class="btn-primary-bra">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span><?= $isEdit ? 'Simpan Perubahan Dokumen' : 'Terbitkan Dokumen BRA SAR' ?></span>
          </button>
        </div>
      </form>
    </div>

    <!-- ── LIVE SPLIT PREVIEW COLUMN (RIGHT SIDE ON WIDESCREEN) ── -->
    <div id="braLiveSplitColumn" class="hidden xl:block bra-live-sticky-preview">
      <div class="card p-3 mb-2 flex items-center justify-between text-xs" style="background: var(--bg-card); border-color: var(--border-color);">
        <div class="flex items-center gap-2 font-bold text-main">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Pratinjau Lembar Dokumen Berita Radio SAR (Live Sync)</span>
        </div>
        <button type="button" onclick="window.printLiveDocument()" class="btn-outline-bra py-1 px-2.5 text-[11px]" title="Cetak langsung dokumen resmi">
          🖨️ Cetak
        </button>
      </div>

      <!-- Screen-only card frame: provides drop-shadow on UI without leaking outer borders into PDF -->
      <div class="bg-white rounded-xl shadow-xl p-4 sm:p-6 border border-slate-200">
        <!-- Live Replicating Official Sheet (Identik 100% dengan bra_view.php) -->
        <div class="bra-official-paper w-full bg-white text-black p-0 border-0 shadow-none font-sans" id="braLivePaperPreview">
        
        <!-- Header Kop: Logo sejajar dengan BRA SAR dan BADAN NASIONAL PENCARIAN DAN PERTOLONGAN -->
        <div class="bra-kop relative flex items-center justify-center gap-4 pb-2 mb-2 border-b-2 border-black text-black">
          <div class="w-12 h-12 shrink-0">
            <?php if (!empty($customLogo)): ?>
              <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
            <?php else: ?>
              <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" class="w-full h-full object-contain">
            <?php endif; ?>
          </div>
          <div class="text-center">
            <h1 class="text-xl sm:text-[22px] font-black tracking-wider uppercase leading-none text-black m-0 p-0">
              BRA SAR
            </h1>
            <h2 class="text-xs sm:text-[13px] font-bold tracking-wider uppercase leading-none text-black m-0 mt-1 p-0">
              BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
            </h2>
          </div>
        </div>

        <!-- Main Body Container with Left SENKOM Note -->
        <div class="bra-table-wrapper relative pl-6 pr-1 sm:pl-7 sm:pr-1 w-full box-border">
          <!-- Left Vertical Side Label (Vector SVG: 100% Crisp, Offline & Zero-Shift) -->
          <div class="absolute left-0 top-0 w-5 flex items-center justify-center select-none pointer-events-none senkom-vertical-label" style="height: 260px; width: 20px;">
            <img src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAyMiAyNjAiIHdpZHRoPSIyMiIgaGVpZ2h0PSIyNjAiPjx0ZXh0IHg9Ii0xMzAiIHk9IjE1IiB0cmFuc2Zvcm09InJvdGF0ZSgtOTApIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiB0ZXh0TGVuZ3RoPSIyNDAiIGxlbmd0aEFkanVzdD0ic3BhY2luZyIgZm9udC1mYW1pbHk9IkFyaWFsLCBIZWx2ZXRpY2EsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iNy41IiBmb250LXdlaWdodD0iOTAwIiBmaWxsPSIjMDAwMDAwIj5SVUFOR0FOIERJTFVBUiBHQVJJUyBURUJBTCBESSBJU0kgT0xFSCBESU5BUyBTRU5LT008L3RleHQ+PC9zdmc+" 
                 alt="RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM" 
                 class="w-full h-full object-contain" />
          </div>

          <!-- Right Main Table Box (Thick Borders) -->
          <div class="bra-table-box border-2 border-black divide-y-2 divide-black text-[11px] sm:text-xs bg-white text-black w-full box-border">
            
            <!-- SENKOM Top Header Row -->
            <div class="bra-top-header grid grid-cols-5 divide-x-2 divide-black text-center font-bold bg-white text-black">
              <div class="py-1 px-1">
                <div>Panggilan</div>
                <div id="pvPanggilan" class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['panggilan'] ?? '—') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Jenis</div>
                <div id="pvJenis" class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['jenis'] ?? 'SAR') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Nr. Stn</div>
                <div id="pvNrStn" class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['nr_stn'] ?? '—') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Derajat</div>
                <div id="pvDerajat" class="font-extrabold text-[11px] sm:text-xs border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Instruksi penerusan</div>
                <div id="pvInstruksiPenerusan" class="font-mono text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['instruksi_penerusan'] ?? '—') ?>
                </div>
              </div>
            </div>

            <!-- Upper Section: 2 Columns -->
            <div class="grid grid-cols-12 divide-x-2 divide-black bg-white text-black">
              <!-- Left Column: DARI, AKSI, INFO -->
              <div class="col-span-8 p-2 space-y-1">
                <div class="flex">
                  <span class="w-14 font-bold uppercase shrink-0 text-black">DARI</span>
                  <span class="mr-1">:</span>
                  <span id="pvDari" class="font-bold uppercase tracking-tight text-black"><?= htmlspecialchars($bra['dari'] ?? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?></span>
                </div>
                <div class="flex">
                  <span class="w-14 font-bold uppercase shrink-0 text-black">AKSI</span>
                  <span class="mr-1">:</span>
                  <span id="pvAksi" class="uppercase text-black"><?= htmlspecialchars($bra['aksi'] ?? '') ?></span>
                </div>
                <div class="flex">
                  <span class="w-14 font-bold uppercase shrink-0 text-black">INFO</span>
                  <span class="mr-1">:</span>
                  <span id="pvInfo" class="uppercase text-black"><?= htmlspecialchars($bra['info'] ?? '') ?></span>
                </div>
              </div>

              <!-- Right Column: Tanggal Waktu TWU & Jumlah Kata -->
              <div class="col-span-4 divide-y-2 divide-black flex flex-col justify-between bg-white text-black">
                <div class="p-1 flex items-center justify-center text-center bg-white">
                  <span class="font-bold uppercase text-[9px] tracking-wider text-black">
                    TANGGAL WAKTU
                  </span>
                </div>
                <div class="p-1 flex items-center justify-center text-center flex-1 bg-white">
                  <span id="pvTwu" class="font-black text-[11px] tracking-wider uppercase text-black">
                    TWU <?= htmlspecialchars($bra['twu'] ?? $twuPlaceholder) ?>
                  </span>
                </div>
                <div class="p-1 flex items-center justify-between bg-white">
                  <span class="font-bold uppercase text-[8.5px] text-black">
                    JUMLAH KATA
                  </span>
                  <span id="pvJumlahKata" class="font-bold text-[9.5px] uppercase text-black">
                    <?= htmlspecialchars($bra['jumlah_kata'] ?? '0 KATA') ?>
                  </span>
                </div>
              </div>
            </div>

            <!-- Telegram Body Sections (AAA, BBB, CCC, etc.) -->
            <div class="border-t-2 border-black p-3 bg-white text-black">
              <!-- NO. & Menbra & Perihal -->
              <div class="mb-3">
                <div class="space-y-0.5">
                  <div class="flex items-start text-xs sm:text-[13px] font-bold uppercase">
                    <span class="w-16 font-black shrink-0 text-black">NO.</span>
                    <span class="w-4 text-center shrink-0 text-black font-black">:</span>
                    <span id="pvNoBra" class="flex-1 font-extrabold tracking-wider ml-1 text-black"><?= htmlspecialchars(!empty($bra['no_bra']) ? $bra['no_bra'] : $noBraPlaceholder) ?></span>
                  </div>
                  <div id="pvMenbraRow" class="flex items-start text-xs sm:text-[13px] font-bold uppercase <?= (empty($bra['menbra']) || strtoupper(trim((string)$bra['menbra'])) === 'SEGERA' || trim((string)$bra['menbra']) === '-') ? 'hidden' : '' ?>">
                    <span class="w-16 font-black shrink-0 text-black">MENBRA</span>
                    <span class="w-4 text-center shrink-0 text-black font-black">:</span>
                    <span id="pvMenbra" class="flex-1 font-extrabold tracking-wider ml-1 text-black"><?= (strtoupper(trim((string)($bra['menbra'] ?? ''))) === 'SEGERA' || trim((string)($bra['menbra'] ?? '')) === '-') ? '' : htmlspecialchars($bra['menbra'] ?? '') ?></span>
                  </div>
                </div>
                <div id="pvPerihal" class="font-black uppercase text-xs sm:text-sm tracking-tight leading-snug mt-2.5 mb-1 text-black text-justify">
                  <?= htmlspecialchars($bra['perihal'] ?? 'BERITA RADIO SAR') ?>
                </div>
              </div>

              <!-- Sections Rows -->
              <div id="pvSectionsContainer" class="space-y-3">
                <!-- Injected via Live JS -->
              </div>

              <!-- TWB at bottom right of telegram text -->
              <div id="pvTwb" class="text-right font-black text-xs sm:text-sm tracking-wider uppercase pt-3 pr-1 text-black">
                TWB <?= htmlspecialchars($bra['twb'] ?? $twbPlaceholder) ?>
              </div>
            </div>

            <!-- Bottom Footer Section: SMC / Pengirim with Barcode & SENKOM Signatures -->
            <div class="grid grid-cols-12 divide-x-2 divide-black bg-white text-black">
              <!-- Left Column: Pengirim (SMC) with QR Barcode -->
              <div class="col-span-7 p-2.5 flex flex-col justify-between">
                <div>
                  <div class="space-y-1">
                    <div class="flex">
                      <span class="w-20 font-bold uppercase shrink-0 text-black">PENGIRIM</span>
                      <span class="mr-1">:</span>
                      <span id="pvPengirim" class="font-bold uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['pengirim'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?></span>
                    </div>
                    <div class="flex">
                      <span class="w-20 font-bold uppercase shrink-0 text-black">Nama</span>
                      <span class="mr-1">:</span>
                      <span id="pvNamaSmc" class="font-bold uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['nama_smc'] ?? 'AMIRUDDIN A.S., S.Sos.') ?></span>
                    </div>
                    <div class="flex">
                      <span class="w-20 font-bold uppercase shrink-0 text-black">Pangkat</span>
                      <span class="mr-1">:</span>
                      <span id="pvPangkatSmc" class="uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['pangkat_smc'] ?? 'PEMBINA (IV/a)') ?></span>
                    </div>
                    <div class="flex">
                      <span class="w-20 font-bold uppercase shrink-0 text-black">Jabatan</span>
                      <span class="mr-1">:</span>
                      <span id="pvJabatanSmc" class="font-bold uppercase text-[11px] sm:text-xs text-black"><?= htmlspecialchars($bra['jabatan_smc'] ?? ($currentKategori === 'MAPEL' || !$currentOpId ? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI' : 'SMC')) ?></span>
                    </div>
                  </div>
                </div>

                <!-- Barcode QR Code Section -->
                <div class="mt-2 pt-2 border-t border-black">
                  <div class="flex items-start justify-between">
                    <div>
                      <span class="font-bold uppercase text-[10px] sm:text-[11px] block text-black">Tanda Tangan :</span>
                      <span class="text-[9px] font-mono block mt-0.5 text-gray-700">(Barcode Digital Terverifikasi)</span>
                    </div>
                    <div class="flex flex-col items-center">
                      <div class="p-0.5 bg-white border border-black rounded shadow-xs" style="width: 76px; height: 76px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="liveBraQrCanvas" width="160" height="160" style="width: 70px; height: 70px; max-width: 70px; max-height: 70px;" class="w-16 h-16 object-contain"></canvas>
                      </div>
                      <div class="text-[8px] font-mono text-center mt-0.5 text-gray-700">SCAN UNTUK VERIFIKASI</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right Column: SENKOM Processing Columns -->
              <div class="col-span-5 flex flex-col justify-between">
                <div class="grid grid-cols-4 divide-x-2 divide-black border-b-2 border-black text-center font-bold py-1 text-[9px] sm:text-[10px] bg-white text-black">
                  <div>T. W. T</div>
                  <div>T. W. K</div>
                  <div>SISTEM</div>
                  <div>PARAF</div>
                </div>
                <div class="grid grid-cols-4 divide-x-2 divide-black flex-1 min-h-[75px] bg-white text-black">
                  <div id="pvTwt" class="p-1 text-center font-mono text-[9px] sm:text-[10px] text-black"><?= htmlspecialchars($bra['twt'] ?? '') ?></div>
                  <div id="pvTwk" class="p-1 text-center font-mono text-[9px] sm:text-[10px] text-black"><?= htmlspecialchars($bra['twk'] ?? '') ?></div>
                  <div id="pvSistem" class="p-1 text-center font-mono text-[9px] sm:text-[10px] text-black"><?= htmlspecialchars($bra['sistem'] ?? '') ?></div>
                  <div id="pvParaf" class="p-1 text-center font-mono text-[9px] sm:text-[10px] text-black"><?= htmlspecialchars($bra['paraf'] ?? '') ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer tiny note -->
        <div class="mt-1.5 text-right text-[7.5px] font-mono text-gray-700">
          KANTOR PENCARIAN DAN PERTOLONGAN KENDARI &bull; SISTEM TELEGRAM RADIO ELEKTRONIK FILE AKTIF
        </div>
      </div>
      </div>
    </div>
  </div>
</div>


<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL FULL PRATINJAU LEMBAR RESMI CETAK                  -->
<!-- ════════════════════════════════════════════════════════ -->
<div id="modalFullBraPreview" class="bra-modal-overlay hidden print:hidden">
  <div class="bra-modal-card">
    <div class="bra-modal-header">
      <div class="flex items-center gap-2">
        <span class="text-lg">📄</span>
        <div>
          <div class="text-sm font-bold text-main">Pratinjau Lembar Naskah Resmi Berita Radio SAR</div>
          <div class="text-[11px] text-muted">Format 100% presisi telegram dinas Basarnas Kendari</div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="window.downloadBraPdf('braLivePaperPreview')" class="btn-primary-bra py-1.5 px-3 text-xs flex items-center gap-1.5" style="background: #2563eb;" title="Unduh Lembar Resmi Format PDF A4">
          <span>📥</span>
          <span>Unduh PDF (A4)</span>
        </button>
        <button type="button" onclick="window.printLiveDocument()" class="btn-primary-bra py-1.5 px-3 text-xs flex items-center gap-1.5" title="Cetak Naskah Resmi">
          <span>🖨️</span>
          <span>Cetak Dokumen</span>
        </button>
        <button type="button" id="btnCloseModalPreview" class="btn-outline-bra py-1.5 px-2.5 text-xs">
          ✕ Tutup
        </button>
      </div>
    </div>
    <div class="bra-modal-body" id="modalPreviewBody">
      <!-- Screen-only card frame for modal -->
      <div class="w-full max-w-4xl bg-white rounded-xl shadow-xl p-4 sm:p-6">
        <!-- Cloned or dynamically loaded paper -->
        <div id="modalPaperTarget" class="w-full"></div>
      </div>
    </div>
  </div>
</div>


<!-- ════════════════════════════════════════════════════════ -->
<!-- JAVASCRIPT ENGINE & DYNAMIC LOGIC                       -->
<!-- ════════════════════════════════════════════════════════ -->
<script src="<?= BASE_URL ?>/assets/vendor/html2pdf/html2pdf.bundle.min.js?v=<?= APP_VERSION ?>"></script>
<script>
window.__BRA_FORM_CONFIG__ = <?= jsonSafe([
    'twuPlaceholder' => $twuPlaceholder,
    'twbPlaceholder' => $twbPlaceholder,
    'noBraPlaceholder' => $noBraPlaceholder,
    'nomorUrutPlaceholder' => $nomorUrutPlaceholder,
    'satkerKode' => $satkerKode,
    'bulanTahunPlaceholder' => $bulanTahunPlaceholder,
    'verifyUrl' => BASE_URL . '/bra.php?view=' . ($id ?: 9999),
    'logoDataUri' => $logoDataUri,
]) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/pages/bra_form.js?v=<?= APP_VERSION ?>"></script>
