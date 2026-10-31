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
$autoDraft = null;
if (!$isEdit && $preselectedOpId > 0) {
    $selectedOps = OperasiModel::getById($preselectedOpId);
    if ($selectedOps) {
        if (!$paramKategori) {
            $currentKategori = 'OPERASI_SAR';
        }
        if (!is_array($bra)) $bra = [];
        $bra['id_operasi'] = $preselectedOpId;
        $bra['nama_operasi'] = $selectedOps['nama_operasi'] ?? '';

        // Draf naskah dibuat di browser oleh BraTemplates (assets/js/bra/bra_templates.js) setelah data operasi dimuat
        $autoDraft = [
            'from' => $fromModul !== '' ? $fromModul : ($currentKategori === 'MAPEL' ? 'mapel' : 'laporan_awal'),
            'id_renops' => (int)($_GET['id_renops'] ?? 0),
            'id_laporan' => (int)($_GET['id_laporan'] ?? 0),
        ];
    }
} elseif (!$isEdit && empty($bra['sections'])) {
    if ($currentKategori === 'MAPEL') {
        $defaultMapel = BraHelper::buildSectionsFromMapel();
        if (!is_array($bra)) $bra = [];
        $bra['perihal'] = $defaultMapel['perihal'];
        $bra['aksi'] = $bra['aksi'] ?? 'KA KSOP KENDARI KMA KA SROP KENDARI';
        $bra['info'] = $bra['info'] ?? 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';
        $bra['dari'] = $defaultMapel['dari'];
        $bra['sections'] = $defaultMapel['sections'];
        $bra['jabatan_smc'] = $defaultMapel['jabatan_smc'];
        $bra['derajat'] = $defaultMapel['derajat'];
    } elseif ($currentKategori === 'OPERASI_SAR') {
        if (!is_array($bra)) $bra = [];
        $bra['aksi'] = $bra['aksi'] ?? 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';
        $bra['info'] = $bra['info'] ?? ''; // Placeholder: POLRES/POLSEK KMA KSOP/SROP SETEMPAT
        $bra['dari'] = $bra['dari'] ?? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
    }
}

// Operasi CLOSED tidak ditawarkan, kecuali operasi yang sudah tertaut (mode edit / tautan dari halaman operasi)
$operasiList = array_values(array_filter(
    OperasiModel::getListSimple(),
    fn($o) => strtoupper((string)($o['status'] ?? 'OPEN')) !== 'CLOSED' || (int)$o['id_operasi'] === (int)$currentOpId
));
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
      display: block !important;
      position: sticky;
      top: 5.5rem;
      max-height: calc(100vh - 7rem);
      overflow-y: auto;
      padding-right: 0.25rem;
    }
    #braMainAppWrap:not(.bra-split-active) #braLiveSplitColumn {
      display: none !important;
    }
  }
  .bra-live-sticky-preview::-webkit-scrollbar {
    width: 6px;
  }
  .bra-live-sticky-preview::-webkit-scrollbar-track {
    background: transparent;
  }
  .bra-live-sticky-preview::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
    border-radius: 9999px;
  }

  /* ── Stepper Navigation ── */
  .bra-stepper-wrap {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 1.25rem;
    padding: 0.65rem 0.95rem;
    box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.05);
  }
  .bra-stepper {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    overflow-x: auto;
    scrollbar-width: none;
  }
  .bra-stepper::-webkit-scrollbar { display: none; }
  .bra-step-btn {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.9rem;
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    white-space: nowrap;
    text-decoration: none;
  }
  .bra-step-btn:hover {
    background: var(--bg-card-subtle);
    color: var(--primary);
    transform: translateY(-1px);
  }
  .bra-step-btn:active {
    transform: scale(0.97);
  }
  .bra-step-btn.active {
    background: var(--primary-light);
    color: var(--primary);
    border-color: rgba(249, 115, 22, 0.3);
    box-shadow: 0 2px 8px -2px rgba(249, 115, 22, 0.2);
  }
  .bra-step-num {
    width: 1.4rem;
    height: 1.4rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 700;
    background: var(--bg-card-subtle);
    color: var(--text-muted);
    border: 1px solid currentColor;
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
  /* Kartu & kontrol mengikuti komponen bersama (surface / control / field) di tailwind.css */
  .bra-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 1.25rem;
    padding: 1.35rem 1.5rem;
    box-shadow: 0 4px 20px -5px rgba(0, 0, 0, 0.04);
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
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
    font-size: 0.9rem;
    font-weight: 700;
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
    font-size: 0.75rem;
    font-weight: 600;
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

  /* Hanya kontrol tanpa floating label; .field-input diatur penuh oleh komponen bersama */
  .bra-control:not(.field-input) {
    width: 100%;
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-family: inherit;
    background: var(--bg-input);
    border: 1px solid var(--border-color);
    border-radius: 0.75rem;
    color: var(--text-main);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }
  .bra-control:not(.field-input):focus {
    outline: none;
    border-color: #f97316;
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.2);
  }
  .bra-control:not(.field-input)::placeholder { color: var(--text-muted); opacity: 0.6; }
  .bra-control.mono { font-family: 'JetBrains Mono', monospace; font-weight: 600; }
  .bra-control.upper { text-transform: uppercase; }
  .bra-control.bold { font-weight: 700; }
  textarea.bra-control { resize: vertical; line-height: 1.6; }
  textarea.bra-control:not(.field-input) { min-height: 68px; }
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
    padding: 0.85rem 1rem;
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    background: var(--bg-card);
    transition: all 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    display: flex;
    align-items: center;
    gap: 0.85rem;
    min-height: 4.25rem;
  }
  .kat-tab-box:hover {
    border-color: var(--primary);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px -2px rgba(249, 115, 22, 0.12);
  }
  .kat-tab-label input:checked + .kat-tab-box {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 0 0 3px var(--primary-light);
  }
  .kat-icon-wrap {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: var(--bg-card-subtle);
    border: 1px solid var(--border-color);
    transition: all 0.22s ease;
  }
  .kat-tab-label input:checked + .kat-tab-box .kat-icon-wrap {
    background: var(--primary);
    border-color: var(--primary);
  }
  .kat-tab-label input:checked + .kat-tab-box .kat-icon-wrap svg {
    color: #ffffff !important;
  }
  .kat-tab-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
  }
  .kat-tab-title { font-size: 0.85rem; font-weight: 700; color: var(--text-main); }
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
    box-shadow: var(--shadow-sm);
  }
  /* Tombol referensi tanpa data laporan: tampak nonaktif (utilitas opacity-40 tidak ada di tailwind.css hasil build) */
  .preset-btn:disabled,
  .preset-btn:disabled:hover {
    opacity: 0.4;
    cursor: not-allowed;
    border-color: var(--border-color);
    color: var(--text-muted);
    box-shadow: none;
  }

  /* ── Section Row (Telegram Body AAA, BBB, CCC...) ── */
  .sec-item {
    background: var(--bg-card-subtle);
    border: 1px solid var(--border-color);
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
    font-weight: 700;
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
    border: 1px solid var(--border-color);
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
    font-weight: 700;
    color: #ffffff;
    background: linear-gradient(135deg, var(--primary), #ea580c);
    border: none;
    border-radius: 0.65rem;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
  }
  .btn-primary-bra:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(249, 115, 22, 0.35);
    filter: brightness(1.05);
  }
  .btn-primary-bra:active { transform: scale(0.98); }

  .btn-outline-bra {
    padding: 0.55rem 1rem;
    font-size: 0.75rem;
    font-weight: 700;
    border-radius: 0.6rem;
    border: 1px solid var(--border-color);
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
    border-bottom: 1px solid #000000;
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
    border: 1px solid var(--border-color);
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
      border: 1px solid #000000 !important;
      box-sizing: border-box !important;
      width: 100% !important;
      max-width: 100% !important;
      overflow-x: hidden !important;
    }
  }
</style>

<div class="space-y-5 bra-layout-container bra-split-active" id="braMainAppWrap">

  <!-- ════════════════════════════════════════════════════════ -->
  <!-- TOP HEADER & CONTROLS TOOLBAR                           -->
  <!-- ════════════════════════════════════════════════════════ -->
  <div class="print:hidden bento-card-v1 cockpit-glass flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-2xl shadow-sm">
    
    <div class="flex items-center gap-3">
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white tracking-tight">
            <?= $isEdit ? 'Edit Naskah Berita Radio SAR' : 'Pembuatan Berita Radio SAR (BRA SAR)' ?>
          </h1>
          <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-700 dark:text-orange-300 font-mono">
            <?= $isEdit ? 'MODE EDIT' : 'DRAF BARU' ?>
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
          Formulir Naskah Telegram Radio Resmi Standar Badan Nasional Pencarian dan Pertolongan &bull; Satker: <strong><?= htmlspecialchars($satkerKode) ?></strong>
        </p>
      </div>
    </div>

    <!-- Toolbar: View Switcher & Format Sandi Telegram -->
    <div class="flex flex-wrap items-center gap-2">
      <!-- Split View Toggle Button (Desktop) -->
      <button type="button" id="btnToggleSplitView" class="btn-tactile btn btn-secondary btn-sm hidden xl:inline-flex rounded-xl" title="Tampilkan atau sembunyikan lembar pratinjau live di sisi kanan">
        <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
        <span id="splitViewLabel">Sembunyikan Lembar</span>
      </button>

      <!-- Telegram Upper & Punctuation Format Helper -->
      <button type="button" id="btnFormatTelegramPunctuation" class="btn-tactile btn btn-secondary btn-sm rounded-xl" title="Format otomatis huruf kapital dan sandi tanda baca militer (TTK, KMA, TTK DUA)">
        Format Sandi Telegram
      </button>
    </div>
  </div>

  <!-- ════════════════════════════════════════════════════════ -->
  <!-- STEPPER NAVIGATION                                      -->
  <!-- ════════════════════════════════════════════════════════ -->
  <div class="bra-stepper-wrap cockpit-glass rounded-2xl p-1.5 border border-slate-200/80 dark:border-white/10 shadow-sm">
    <div class="bra-stepper" id="braStepperBar">
      <a href="#sec-kategori" class="bra-step-btn btn-tactile active" data-target="sec-kategori">
        <span class="bra-step-num">1</span>
        <span>Kategori &amp; Operasi</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-nomor" class="bra-step-btn btn-tactile" data-target="sec-nomor">
        <span class="bra-step-num">2</span>
        <span>Penomoran &amp; Administrasi</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-waktu-alamat" class="bra-step-btn btn-tactile" data-target="sec-waktu-alamat">
        <span class="bra-step-num">3</span>
        <span>Waktu Militer &amp; Alamat</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-batang-tubuh" class="bra-step-btn btn-tactile" data-target="sec-batang-tubuh">
        <span class="bra-step-num">4</span>
        <span>Batang Tubuh (AAA, BBB, CCC)</span>
      </a>
      <span class="bra-step-sep">&rsaquo;</span>
      <a href="#sec-pengirim" class="bra-step-btn btn-tactile" data-target="sec-pengirim">
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
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white" style="background: var(--primary);">1</span>
                <span>Pilih Kategori Dokumen Berita Radio SAR</span>
              </div>
              <div class="bra-card-desc">Tentukan jenis berita resmi dan tautkan dengan data Operasi SAR aktif jika tersedia</div>
            </div>
            <span class="text-[11px] font-bold text-orange-600 dark:text-orange-400 font-mono">STANDAR BASARNAS</span>
          </div>

          <!-- 3 Kategori Selector Tabs (Identik dengan Referensi Resmi FormBraSar) -->
          <div class="kat-tabs mb-4">
            <!-- 1. MAPEL -->
            <label class="kat-tab-label group">
              <input type="radio" name="kategori" value="MAPEL" <?= $currentKategori === 'MAPEL' ? 'checked' : '' ?> class="kategori-radio">
              <div class="kat-tab-box">
                <div class="kat-icon-wrap">
                  <svg class="w-5 h-5 text-orange-500 transition-transform group-hover:scale-105" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.9 19.1A10 10 0 0 1 2 12a10 10 0 0 1 2.9-7.1M19.1 4.9A10 10 0 0 1 22 12a10 10 0 0 1-2.9 7.1M7.75 16.25A6 6 0 0 1 6 12a6 6 0 0 1 1.75-4.25M16.25 7.75A6 6 0 0 1 18 12a6 6 0 0 1-1.75 4.25M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="kat-tab-header">
                    <span class="kat-tab-title">1. BRA Mapel</span>
                    <span class="kat-tab-pill">Broadcast Notice</span>
                  </div>
                </div>
              </div>
            </label>

            <!-- 2. OPERASI SAR -->
            <label class="kat-tab-label group">
              <input type="radio" name="kategori" value="OPERASI_SAR" <?= $currentKategori === 'OPERASI_SAR' ? 'checked' : '' ?> class="kategori-radio">
              <div class="kat-tab-box">
                <div class="kat-icon-wrap">
                  <svg class="w-5 h-5 text-orange-500 transition-transform group-hover:scale-105" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="kat-tab-header">
                    <span class="kat-tab-title">2. BRA Operasi SAR</span>
                    <span class="kat-tab-pill">Operasi SAR Harian</span>
                  </div>
                </div>
              </div>
            </label>

            <!-- 3. PERGERAKAN KAPAL -->
            <label class="kat-tab-label group">
              <input type="radio" name="kategori" value="PERGERAKAN_KAPAL" <?= $currentKategori === 'PERGERAKAN_KAPAL' ? 'checked' : '' ?> class="kategori-radio">
              <div class="kat-tab-box">
                <div class="kat-icon-wrap">
                  <svg class="w-5 h-5 text-orange-500 transition-transform group-hover:scale-105" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17.5 4.5 19a3 3 0 0 0 4.24 0L10 17.74a3 3 0 0 1 4 0L15.26 19a3 3 0 0 0 4.24 0L21 17.5M4 14l2-8h12l2 8H4Zm8-11v3m-3-1.5h6"/></svg>
                </div>
                <div class="min-w-0 flex-1">
                  <div class="kat-tab-header">
                    <span class="kat-tab-title">3. BRA Pergerakan Kapal</span>
                    <span class="kat-tab-pill">Alut Laut &amp; BKO</span>
                  </div>
                </div>
              </div>
            </label>
          </div>

          <!-- Quick MAPEL Presets Bar (Muncul Khusus MAPEL) -->
          <div id="mapelPresetsContainer" class="bra-preset-banner mb-4 <?= $currentKategori === 'MAPEL' ? '' : 'hidden' ?>" style="border-color: rgba(59, 130, 246, 0.4); background: rgba(59, 130, 246, 0.08); display: <?= $currentKategori === 'MAPEL' ? 'block' : 'none' ?>;">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-2.5 pb-2.5 border-b border-blue-500/20">
              <div class="flex items-center gap-2 text-xs font-bold text-blue-900 dark:text-blue-300">
                <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
                <div>
                  <span class="tracking-wide">Format Telegram Pemapelan Resmi (Senkom KPP Kendari)</span>
                  <span class="block text-[11px] font-normal text-muted dark:text-blue-200/70">Pemberitahuan resmi kepada KSOP, SROP, dan kapal-kapal yang melintas di sekitar LKP:</span>
                </div>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2 pt-1">
              <span class="text-[11px] font-semibold text-blue-900 dark:text-blue-200">Aksi Cepat:</span>
              <button type="button" id="btnPresetLongboatHilangKontak" class="btn-tactile preset-btn flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white border-blue-700 px-3 py-1.5 rounded-lg text-xs font-bold shadow-sm transition" title="Muat draf telegram pemapelan resmi: 1. Longboat / Kapal Hilang Kontak">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                <span>1. Longboat / Kapal Hilang Kontak</span>
              </button>
            </div>
          </div>

          <!-- Operasi SAR Connection Select -->
          <div class="bra-field">
            <div class="field">
              <label class="field-label"><span>Tautkan ke Operasi SAR Aktif (Opsional)</span> <?php if (!empty($operasiList)): ?> <span class="text-[11px] font-normal text-muted"><?= count($operasiList) ?> Operasi Terdaftar</span> <?php endif; ?></label>
              <select name="id_operasi" id="operasiSelect" class="field-input bra-control">
              <option value="" data-nama="">— Buat Mandiri / Tanpa Kaitan Operasi SAR —</option>
              <?php foreach ($operasiList as $op): ?>
                <option value="<?= $op['id_operasi'] ?>" data-nama="<?= htmlspecialchars($op['nama_operasi']) ?>" <?= ($currentOpId === (int)$op['id_operasi']) ? 'selected' : '' ?>>
                  #OPS-<?= $op['id_operasi'] ?> &bull; <?= htmlspecialchars($op['nama_operasi']) ?> (Status: <?= htmlspecialchars($op['status'] ?? 'OPEN') ?>)
                </option>
              <?php endforeach; ?>
            </select>
            </div>
            <span class="bra-hint">Pilih operasi SAR untuk mengaktifkan fitur asisten pengisian otomatis data kronologi, LKP, alut, cuaca, dan personil.</span>
          </div>

          <!-- Smart Recommendation Toolbar (Terkait Operasi SAR) -->
          <div id="operasiRecommendationBar" class="bra-preset-banner mt-3 <?= $currentOpId ? '' : 'hidden' ?>" style="border-color: rgba(245, 158, 11, 0.4); background: rgba(245, 158, 11, 0.08);">
            <div class="flex items-center gap-2 text-xs font-semibold text-amber-800 dark:text-amber-300">
              <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z"/></svg>
              <span><strong>Rekomendasi Draf Naskah Otomatis:</strong> Pilih jenis naskah laporan:</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button type="button" id="btnAutoInisiasi" class="btn-tactile preset-btn" title="Muat naskah Laporan Awal (Inisiasi)">
                Laporan Awal (Inisiasi)
              </button>
              <span class="inline-flex items-center gap-1">
                <button type="button" id="btnAutoRenops" class="btn-tactile preset-btn" title="Muat Rencana Operasi SAR pada hari terpilih">
                  RENOPS
                </button>
                <select id="selectRenopsHari" class="bra-control text-xs py-1" aria-label="Pilih hari RENOPS"></select>
              </span>
              <span class="inline-flex items-center gap-1">
                <button type="button" id="btnAutoSitrep" class="btn-tactile preset-btn" title="Muat Laporan Perkembangan pada hari terpilih">
                  Lapbang
                </button>
                <select id="selectLapbangHari" class="bra-control text-xs py-1" aria-label="Pilih hari Lapbang"></select>
              </span>
              <button type="button" id="btnAutoPenutupan" class="btn-tactile preset-btn" title="Muat Laporan Perkembangan dan Penutupan Operasi SAR">
                Lapbang dan Penutupan
              </button>
              <button type="button" id="btnAutoMapel" class="btn-tactile preset-btn" title="Muat naskah Pemapelan Maritim dari data Operasi SAR">
                Pemapelan (Mapel)
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
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white" style="background: var(--info);">2</span>
                <span>Penomoran Naskah &amp; Administrasi SENKOM</span>
              </div>
              <div class="bra-card-desc">Nomor berita radio, derajat urgensi, dan rujukan naskah sebelumnya</div>
            </div>
            <div class="flex items-center gap-1.5 text-[11px] font-mono text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/40 px-2 py-0.5 rounded border border-emerald-300 dark:border-emerald-600/30">
              <span>Proteksi Urut Otomatis Aktif</span>
            </div>
          </div>

          <div class="bra-grid-3 mb-3">
            <!-- No BRA -->
            <div class="bra-field">
              <div class="field">
                <label class="field-label">Nomor Naskah BRA <span class="req">*</span></label>
                <input class="field-input bra-control mono bold upper" type="text" name="no_bra" id="noBraInput" value="<?= htmlspecialchars(!empty($bra['no_bra']) ? $bra['no_bra'] : $noBraPlaceholder) ?>" placeholder="<?= $noBraPlaceholder ?>"  required>
              </div>
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
                <input type="text" name="derajat" id="derajatInput" value="<?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?>" placeholder="SGR" class="bra-control mono bold upper flex-1 min-w-[4.5rem]" required style="flex:1;">
                <button type="button" onclick="setDerajat('SGR')" class="preset-btn text-[11px]">SGR</button>
                <button type="button" onclick="setDerajat('KILAT')" class="preset-btn text-[11px]">KILAT</button>
                <button type="button" onclick="setDerajat('RUTIN')" class="preset-btn text-[11px]">RUTIN</button>
              </div>
              <span class="bra-hint">SGR = Segera (Default Basarnas), KILAT, atau RUTIN</span>
            </div>

            <!-- MENBRA (Rujukan Naskah Lalu) (ISSUE-08) -->
            <div class="bra-field" id="menbraFieldWrapper">
              <div class="field">
                <label id="menbraLabelText" class="field-label"><span id="labelMenbraTitle">MENBRA (Rujukan BRA Lalu)</span></label>
                <input class="field-input bra-control mono upper" type="text" name="menbra" id="menbraInput" list="menbraOptions" autocomplete="off" value="<?= (strtoupper(trim((string)($bra['menbra'] ?? ''))) === 'SEGERA' || trim((string)($bra['menbra'] ?? '')) === '-') ? '' : htmlspecialchars($bra['menbra'] ?? '') ?>" placeholder="<?= max(1, $nomorUrutPlaceholder - 1) ?>/<?= $satkerKode ?>/<?= $bulanTahunPlaceholder ?>" >
                <datalist id="menbraOptions"></datalist>
              </div>
              <span class="bra-hint" id="hintMenbraText">Nomor naskah BRA sebelumnya yang menjadi dasar rujukan (bila ada). Biarkan kosong jika naskah baru / tanpa rujukan.</span>
            </div>
          </div>

          <!-- Accordion Header Senkom Ekstra (Panggilan, Jenis, Nr. Stn, Instruksi) -->
          <details class="text-xs border rounded-xl p-3 bg-card-subtle transition" style="border-color: var(--border-color);">
            <summary class="font-bold cursor-pointer text-muted hover:text-main flex items-center justify-between select-none">
              <span>Rincian Baris Header Senkom Lengkap (Panggilan, Jenis, Nr. Stn, Instruksi Penerusan)</span>
              <span class="text-[11px] font-normal text-muted">(Klik untuk buka / tutup)</span>
            </summary>
            <div class="bra-grid-4 mt-3 pt-3 border-t" style="border-color: var(--border-color);">
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">Panggilan</label>
                  <input class="field-input bra-control mono" type="text" name="panggilan" id="panggilanInput" value="<?= htmlspecialchars($bra['panggilan'] ?? '') ?>"  placeholder="—">
                </div>
              </div>
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">Jenis</label>
                  <input class="field-input bra-control mono" type="text" name="jenis" id="jenisInput" value="<?= htmlspecialchars($bra['jenis'] ?? '') ?>"  placeholder="SAR">
                </div>
              </div>
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">Nr. Stn</label>
                  <input class="field-input bra-control mono" type="text" name="nr_stn" id="nrStnInput" value="<?= htmlspecialchars($bra['nr_stn'] ?? '') ?>"  placeholder="—">
                </div>
              </div>
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">Instruksi Penerusan</label>
                  <input class="field-input bra-control mono" type="text" name="instruksi_penerusan" id="instruksiPenerusanInput" value="<?= htmlspecialchars($bra['instruksi_penerusan'] ?? '') ?>"  placeholder="—">
                </div>
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
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white" style="background: var(--warning);">3</span>
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
                  Waktu Sekarang
                </button>
              </div>
              <input type="text" name="twb" id="twbInput" value="<?= htmlspecialchars($bra['twb'] ?? '') ?>" placeholder="<?= $twbPlaceholder ?>" class="bra-control mono bold upper text-sm" required>
              <div class="flex items-center justify-between mt-1 text-[11px] text-muted">
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
                  Waktu Sekarang
                </button>
              </div>
              <input type="text" name="twu" id="twuInput" value="<?= htmlspecialchars($bra['twu'] ?? '') ?>" placeholder="<?= $twuPlaceholder ?>" class="bra-control mono bold upper text-sm" required>
              <div class="flex items-center justify-between mt-1 text-[11px] text-muted">
                <span>Dicetak di sudut kanan atas telegram</span>
                <span>Contoh: <strong>0920 0700 H</strong></span>
              </div>
            </div>
          </div>

          <!-- Address Fields: DARI, AKSI, INFO, PERIHAL -->
          <div class="space-y-3">
            <div class="bra-field">
              <div class="field">
                <label class="field-label">DARI (Satker Asal Pengirim) <span class="req">*</span></label>
                <input placeholder=" " class="field-input bra-control bold upper" type="text" name="dari" id="dariInput" value="<?= htmlspecialchars($bra['dari'] ?? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?>"  required>
              </div>
            </div>

            <?php
              $infoKapal = 'DEPUTI OPS DAN KESIAPSIAGAAN KMA DEPUTI SARPRAS DAN SISTEM KOMUNIKASI KMA DIREKTUR SARPRAS DAN DIREKTUR KESIAPSIAGAAN';

              $placeholderAksi = ($currentKategori === 'MAPEL')
                ? 'KA KSOP KENDARI KMA KA SROP KENDARI'
                : 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';

              $defaultAksiVal = $bra['aksi'] ?? (
                $currentKategori === 'MAPEL'
                  ? 'KA KSOP KENDARI KMA KA SROP KENDARI'
                  : 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN'
              );

              $placeholderInfo = ($currentKategori === 'OPERASI_SAR')
                ? 'POLRES/POLSEK KMA KSOP KMA SROP SETEMPAT'
                : ($currentKategori === 'PERGERAKAN_KAPAL' ? $infoKapal : 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN');

              $defaultInfoVal = $bra['info'] ?? match ($currentKategori) {
                'MAPEL' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
                'PERGERAKAN_KAPAL' => $infoKapal,
                default => '',
              };
            ?>
            <!-- Standar Pemapelan: KA KSOP KENDARI DAN KMA KA SROP KENDARI -->
            <div class="bra-field">
              <div class="field">
                <label class="field-label">AKSI (Alamat Tujuan Utama Tindak Lanjut) <span class="req">*</span></label>
                <textarea name="aksi" id="aksiInput" rows="2" class="field-input bra-control upper font-semibold" placeholder="<?= htmlspecialchars($placeholderAksi) ?>" required><?= htmlspecialchars($defaultAksiVal) ?></textarea>
              </div>
            </div>

            <div class="bra-field">
              <div class="field">
                <label class="field-label">INFO (Tembusan Informasi)</label>
                <textarea name="info" id="infoInput" rows="2" class="field-input bra-control upper" placeholder="<?= htmlspecialchars($placeholderInfo) ?>"><?= htmlspecialchars($defaultInfoVal) ?></textarea>
              </div>
            </div>

            <div class="bra-field">
              <div class="field">
                <label class="field-label">PERIHAL / Judul Berita Radio SAR <span class="req">*</span></label>
                <textarea name="perihal" id="perihalInput" rows="2" class="field-input bra-control bold upper text-orange-600 dark:text-orange-400" placeholder="KECELAKAAN KAPAL..." required><?= htmlspecialchars($bra['perihal'] ?? '') ?></textarea>
              </div>
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
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white" style="background: var(--primary);">4</span>
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
                <span>Tambah Bagian</span>
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
                <span class="w-6 h-6 rounded-md flex items-center justify-center text-xs font-bold text-white" style="background: var(--success);">5</span>
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
              <div class="field">
                <label class="field-label"><span>Pilih Cepat dari Master Pejabat / SMC</span> <span class="text-[11px] text-muted">Nama, Pangkat &amp; Jabatan Otomatis Terisi</span></label>
                <select id="pejabatSelectControl" class="field-input bra-control">
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
            </div>
          <?php endif; ?>

          <div class="bra-grid-2 mb-3">
            <div class="bra-field">
              <div class="field">
                <label class="field-label">Instansi Pengirim <span class="req">*</span></label>
                <input placeholder=" " class="field-input bra-control bold upper" type="text" name="pengirim" id="pengirimInput" value="<?= htmlspecialchars($bra['pengirim'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?>"  required>
              </div>
            </div>
            <div class="bra-field">
              <div class="field">
                <label class="field-label">Nama Lengkap Pejabat / SMC <span class="req">*</span></label>
                <input placeholder=" " class="field-input bra-control bold upper" type="text" name="nama_smc" id="namaSmcInput" value="<?= htmlspecialchars($bra['nama_smc'] ?? 'AMIRUDDIN A.S., S.Sos.') ?>"  required>
              </div>
            </div>
          </div>

          <div class="bra-grid-2 mb-4">
            <div class="bra-field">
              <div class="field">
                <label class="field-label">Pangkat / Golongan <span class="req">*</span></label>
                <input placeholder=" " class="field-input bra-control upper" type="text" name="pangkat_smc" id="pangkatSmcInput" value="<?= htmlspecialchars($bra['pangkat_smc'] ?? 'PEMBINA (IV/a)') ?>"  required>
              </div>
            </div>
            <div class="bra-field">
              <div class="field">
                <label class="field-label">Jabatan Kedinasan <span class="req">*</span></label>
                <input placeholder=" " class="field-input bra-control bold upper" type="text" name="jabatan_smc" id="jabatanSmcInput" value="<?= htmlspecialchars($bra['jabatan_smc'] ?? ($currentKategori === 'MAPEL' || !$currentOpId ? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI' : 'SMC')) ?>"  required>
              </div>
            </div>
          </div>

          <!-- Register SENKOM Ledger -->
          <div class="pt-3 border-t" style="border-color: var(--border-color);">
            <div class="bra-label mb-2">
              <span>Buku Komunikasi Radio SENKOM (Bisa Dilengkapi Operator Radio)</span>
              <span class="text-[11px] font-normal text-muted">Kolom Sudut Kanan Bawah Telegram</span>
            </div>
            <div class="bra-grid-4">
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">T.W.T (Waktu Terima)</label>
                  <input class="field-input bra-control mono text-center" type="text" name="twt" id="twtInput" value="<?= htmlspecialchars($bra['twt'] ?? '') ?>"  placeholder="—">
                </div>
              </div>
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">T.W.K (Waktu Kirim)</label>
                  <input class="field-input bra-control mono text-center" type="text" name="twk" id="twkInput" value="<?= htmlspecialchars($bra['twk'] ?? '') ?>"  placeholder="—">
                </div>
              </div>
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">SISTEM (Frekuensi)</label>
                  <input class="field-input bra-control mono text-center" type="text" name="sistem" id="sistemInput" value="<?= htmlspecialchars($bra['sistem'] ?? '') ?>"  placeholder="HF / VHF / SSB">
                </div>
              </div>
              <div class="bra-field">
                <div class="field">
                  <label class="field-label">PARAF Operator</label>
                  <input class="field-input bra-control mono text-center" type="text" name="paraf" id="parafInput" value="<?= htmlspecialchars($bra['paraf'] ?? '') ?>"  placeholder="Operator">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ────────────────────────────────────────────── -->
        <!-- STICKY ACTION FOOTER BAR                       -->
        <!-- ────────────────────────────────────────────── -->
        <div class="bra-sticky-bar cockpit-glass rounded-2xl shadow-xl">
          <div class="flex items-center gap-2">
            <a href="<?= BASE_URL ?>/bra.php" class="btn-tactile btn-outline-bra rounded-xl">
              Batal
            </a>
          </div>

          <button type="submit" id="btnSubmitBra" class="btn-tactile btn-primary-bra rounded-xl shadow-md">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            <span><?= $isEdit ? 'Simpan Perubahan Dokumen' : 'Terbitkan Dokumen BRA SAR' ?></span>
          </button>
        </div>
      </form>
    </div>

    <!-- ── LIVE SPLIT PREVIEW COLUMN (RIGHT SIDE ON WIDESCREEN) ── -->
    <div id="braLiveSplitColumn" class="bra-live-sticky-preview">
      <div class="cockpit-glass rounded-2xl p-3 mb-3 border border-slate-200/80 dark:border-white/10 shadow-sm flex items-center justify-between gap-2.5">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-orange-500/10 dark:bg-orange-500/20 text-orange-500 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
          </div>
          <div>
            <div class="flex items-center gap-2 text-xs font-bold text-main leading-tight">
              <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
              <span>Pratinjau Lembar Dokumen BRA SAR (Live Sync)</span>
            </div>
            <div class="text-[11px] text-muted">Format baku telegram dinas Basarnas Kendari</div>
          </div>
        </div>
        <div class="hidden sm:flex items-center gap-1.5 text-[11px] font-mono text-muted">
          <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
          <span>A4 Presisi</span>
        </div>
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
            <h1 class="text-xl sm:text-[22px] font-bold leading-none text-black m-0 p-0">
              BRA SAR
            </h1>
            <h2 class="text-xs sm:text-[13px] font-bold leading-none text-black m-0 mt-1 p-0">
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
                <div id="pvPanggilan" class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['panggilan'] ?? '—') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Jenis</div>
                <div id="pvJenis" class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['jenis'] ?? 'SAR') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Nr. Stn</div>
                <div id="pvNrStn" class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['nr_stn'] ?? '—') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Derajat</div>
                <div id="pvDerajat" class="font-semibold text-[11px] sm:text-xs border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Instruksi penerusan</div>
                <div id="pvInstruksiPenerusan" class="font-mono text-[11px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
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
                  <span class="font-bold uppercase text-[11px] tracking-wider text-black">
                    TANGGAL WAKTU
                  </span>
                </div>
                <div class="p-1 flex items-center justify-center text-center flex-1 bg-white">
                  <span id="pvTwu" class="font-black text-[11px] tracking-wider uppercase text-black">
                    TWU <?= htmlspecialchars($bra['twu'] ?? $twuPlaceholder) ?>
                  </span>
                </div>
                <div class="p-1 flex items-center justify-between bg-white">
                  <span class="font-bold uppercase text-[11px] text-black">
                    JUMLAH KATA
                  </span>
                  <span id="pvJumlahKata" class="font-bold text-[11px] uppercase text-black">
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
                    <span class="w-16 font-bold shrink-0 text-black">NO.</span>
                    <span class="w-4 text-center shrink-0 text-black font-bold">:</span>
                    <span id="pvNoBra" class="flex-1 font-semibold tracking-wider ml-1 text-black"><?= htmlspecialchars(!empty($bra['no_bra']) ? $bra['no_bra'] : $noBraPlaceholder) ?></span>
                  </div>
                  <div id="pvMenbraRow" class="flex items-start text-xs sm:text-[13px] font-bold uppercase <?= (empty($bra['menbra']) || strtoupper(trim((string)$bra['menbra'])) === 'SEGERA' || trim((string)$bra['menbra']) === '-') ? 'hidden' : '' ?>">
                    <span class="w-16 font-bold shrink-0 text-black">MENBRA</span>
                    <span class="w-4 text-center shrink-0 text-black font-bold">:</span>
                    <span id="pvMenbra" class="flex-1 font-semibold tracking-wider ml-1 text-black"><?= (strtoupper(trim((string)($bra['menbra'] ?? ''))) === 'SEGERA' || trim((string)($bra['menbra'] ?? '')) === '-') ? '' : htmlspecialchars($bra['menbra'] ?? '') ?></span>
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
                      <span class="font-bold uppercase text-[11px] sm:text-[11px] block text-black">Tanda Tangan :</span>
                      <span class="text-[11px] font-mono block mt-0.5 text-gray-700">(Barcode Digital Terverifikasi)</span>
                    </div>
                    <div class="flex flex-col items-center">
                      <div class="p-0.5 bg-white border border-black rounded shadow-xs" style="width: 76px; height: 76px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="liveBraQrCanvas" width="160" height="160" style="width: 70px; height: 70px; max-width: 70px; max-height: 70px;" class="w-16 h-16 object-contain"></canvas>
                      </div>
                      <div class="text-[11px] font-mono text-center mt-0.5 text-gray-700">SCAN UNTUK VERIFIKASI</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right Column: SENKOM Processing Columns -->
              <div class="col-span-5 flex flex-col justify-between">
                <div class="grid grid-cols-4 divide-x-2 divide-black border-b-2 border-black text-center font-bold py-1 text-[11px] sm:text-[11px] bg-white text-black">
                  <div>T. W. T</div>
                  <div>T. W. K</div>
                  <div>SISTEM</div>
                  <div>PARAF</div>
                </div>
                <div class="grid grid-cols-4 divide-x-2 divide-black flex-1 min-h-[75px] bg-white text-black">
                  <div id="pvTwt" class="p-1 text-center font-mono text-[11px] sm:text-[11px] text-black"><?= htmlspecialchars($bra['twt'] ?? '') ?></div>
                  <div id="pvTwk" class="p-1 text-center font-mono text-[11px] sm:text-[11px] text-black"><?= htmlspecialchars($bra['twk'] ?? '') ?></div>
                  <div id="pvSistem" class="p-1 text-center font-mono text-[11px] sm:text-[11px] text-black"><?= htmlspecialchars($bra['sistem'] ?? '') ?></div>
                  <div id="pvParaf" class="p-1 text-center font-mono text-[11px] sm:text-[11px] text-black"><?= htmlspecialchars($bra['paraf'] ?? '') ?></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer tiny note -->
        <div class="mt-1.5 text-right text-[11px] font-mono text-gray-700">
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
        <svg class="w-5 h-5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        <div>
          <div class="text-sm font-bold text-main">Pratinjau Lembar Naskah Resmi Berita Radio SAR</div>
          <div class="text-[11px] text-muted">Format 100% presisi telegram dinas Basarnas Kendari</div>
        </div>
      </div>
      <div class="flex items-center gap-2">
        <button type="button" onclick="window.downloadBraPdf('braLivePaperPreview')" class="btn-tactile btn-primary-bra py-1.5 px-3 text-xs flex items-center gap-1.5" style="background: #2563eb;" title="Unduh Lembar Resmi Format PDF A4">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
          <span>Unduh PDF (A4)</span>
        </button>
        <button type="button" onclick="window.printLiveDocument()" class="btn-tactile btn-primary-bra py-1.5 px-3 text-xs flex items-center gap-1.5" title="Cetak Naskah Resmi">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.07-.672-2.126-.672-3.329 0-3.037 2.463-5.5 5.5-5.5s5.5 2.463 5.5 5.5c0 1.203-.432 2.259-.672 3.329M6.75 19.5h10.5a2.25 2.25 0 002.25-2.25V13.5a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 13.5v3.75a2.25 2.25 0 002.25 2.25zM6.75 19.5v-3h10.5v3"/></svg>
          <span>Cetak Dokumen</span>
        </button>
        <button type="button" id="btnCloseModalPreview" class="btn-tactile btn-outline-bra py-1.5 px-2.5 text-xs">
          Tutup
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
<script src="<?= assetUrl('assets/vendor/html2pdf/html2pdf.bundle.min.js') ?>"></script>
<script>
window.__BRA_FORM_CONFIG__ = <?= jsonSafe([
    'twuPlaceholder' => $twuPlaceholder,
    'twbPlaceholder' => $twbPlaceholder,
    'noBraPlaceholder' => $noBraPlaceholder,
    'nomorUrutPlaceholder' => $nomorUrutPlaceholder,
    'satkerKode' => $satkerKode,
    'bulanTahunPlaceholder' => $bulanTahunPlaceholder,
    'verifyUrl' => BASE_URL . '/bra.php?view=' . ($id ?: 9999),
    'idBra' => (int)($id ?: 0),
    'logoDataUri' => $logoDataUri,
    'autoDraft' => $autoDraft,
]) ?>;
</script>
<script src="<?= assetUrl('assets/js/bra/bra_templates.js') ?>"></script>
<script src="<?= assetUrl('assets/js/pages/bra_form.js') ?>"></script>
