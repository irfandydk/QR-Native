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
$braBackUrl = isset($backUrl) && is_string($backUrl) && $backUrl !== '' ? $backUrl : (BASE_URL . '/bra.php');
$braReturnTo = isset($returnTo) && is_string($returnTo) ? $returnTo : '';
$braReturnOp = isset($returnOp) ? (int)$returnOp : 0;

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

<!-- ── BRA Form Scoped Stylesheet (Aligned with Unified Operational Form System) ── -->
<style>
  .bra-form-shell {
    max-width: 92rem;
    margin-inline: auto;
    padding-bottom: 6rem;
    transition: max-width 0.2s ease;
  }
  #braMainAppWrap:not(.bra-split-active).bra-form-shell {
    max-width: 68rem;
  }

  /* ── Split Workspace Grid ── */
  .bra-split-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1.25rem;
    align-items: start;
  }
  .bra-live-sticky-preview {
    display: none;
  }
  @media (min-width: 1200px) {
    .bra-split-active .bra-split-grid {
      grid-template-columns: minmax(0, 1.16fr) minmax(0, 0.84fr);
      gap: 1.5rem;
    }
    .bra-split-active .bra-live-sticky-preview {
      display: block !important;
      position: sticky;
      top: 5rem;
      max-height: calc(100vh - 6.25rem);
      overflow-y: auto;
      overflow-x: hidden;
      padding-right: 0.25rem;
    }
    #braMainAppWrap:not(.bra-split-active) #braLiveSplitColumn {
      display: none !important;
    }
    /* Skala proporsional lembar A4 agar 5 kolom kop & isi DARI/AKSI/INFO bebas tumpukan di 1366px */
    .bra-split-active #braLivePaperPreview {
      width: 620px;
      zoom: 0.62;
    }
  }
  @media (min-width: 1440px) {
    .bra-split-active #braLivePaperPreview {
      zoom: 0.74;
    }
  }
  @media (min-width: 1680px) {
    .bra-split-active #braLivePaperPreview {
      zoom: 0.88;
    }
  }
  .bra-live-sticky-preview::-webkit-scrollbar {
    width: 5px;
  }
  .bra-live-sticky-preview::-webkit-scrollbar-track {
    background: transparent;
  }
  .bra-live-sticky-preview::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.35);
    border-radius: 9999px;
  }

  /* ── Section Anchor Offset ── */
  .bra-card {
    scroll-margin-top: 5.5rem;
  }

  /* ── Compact Stepper Rail ── */
  .bra-stepper-wrap {
    padding: 0.45rem;
    border-radius: 1rem;
  }
  .bra-stepper {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
  }
  .bra-step-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.45rem 0.75rem;
    border-radius: 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
    white-space: nowrap;
    text-decoration: none;
  }
  .bra-step-btn:hover {
    background: var(--bg-card-subtle);
    color: var(--text-main);
  }
  .bra-step-btn:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }
  .bra-step-btn.active {
    background: var(--primary-light);
    color: var(--m-accent-text, var(--primary));
    border-color: color-mix(in srgb, var(--primary) 28%, transparent);
  }
  .bra-step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.45rem;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.68rem;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    background: var(--bg-card-subtle);
    color: var(--text-muted);
    flex-shrink: 0;
  }
  .bra-step-btn.active .bra-step-num {
    background: var(--primary);
    color: #ffffff;
  }

  /* ── Accessible Visually Hidden Helper ── */
  .bra-visually-hidden {
    position: absolute;
    clip-path: inset(50%);
    overflow: hidden;
    width: 1px;
    height: 1px;
    margin: -1px;
    padding: 0;
    border: 0;
    white-space: nowrap;
  }

  /* ── 3 Kategori Selector Cards ── */
  .kat-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.75rem;
  }
  @media (max-width: 768px) {
    .kat-tabs {
      grid-template-columns: 1fr;
    }
  }
  .kat-tab-label {
    position: relative;
    cursor: pointer;
    min-width: 0;
    display: block;
  }
  .kat-tab-box {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.8rem 0.95rem;
    border: 1px solid var(--border-color);
    border-radius: 0.875rem;
    background: var(--bg-card);
    transition: border-color 150ms ease, background-color 150ms ease, box-shadow 150ms ease;
    height: 100%;
    min-width: 0;
  }
  .kat-tab-box:hover {
    border-color: color-mix(in srgb, var(--primary) 65%, transparent);
  }
  .kat-tab-label input:focus-visible + .kat-tab-box {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }
  .kat-tab-label input:checked + .kat-tab-box {
    border-color: var(--primary);
    background: var(--primary-light);
    box-shadow: 0 0 0 1px color-mix(in srgb, var(--primary) 35%, transparent);
  }
  .kat-icon-wrap {
    width: 2.15rem;
    height: 2.15rem;
    border-radius: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: var(--bg-card-subtle);
    color: var(--m-accent-text, var(--primary));
    transition: background-color 150ms ease, color 150ms ease;
  }
  .kat-tab-label input:checked + .kat-tab-box .kat-icon-wrap {
    background: var(--primary);
    color: #ffffff;
  }
  .kat-tab-title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-main);
    line-height: 1.3;
    min-width: 0;
  }
  .kat-tab-label input:checked + .kat-tab-box .kat-tab-title {
    color: var(--m-accent-text, var(--primary));
  }

  /* ── Presets & Draft Toolbar ── */
  .bra-draft-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.65rem;
  }
  .bra-draft-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
  }
  .bra-draft-combo {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
  }
  .preset-btn {
    height: 2rem;
    padding: 0 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    border-radius: 0.6rem;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-main);
    cursor: pointer;
    transition: border-color 150ms ease, color 150ms ease, background-color 150ms ease;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
  }
  .preset-btn:hover {
    border-color: var(--primary);
    color: var(--m-accent-text, var(--primary));
  }
  .preset-btn:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }
  .preset-btn-accent {
    background: var(--primary);
    border-color: var(--primary);
    color: #ffffff;
  }
  .preset-btn-accent:hover {
    background: var(--primary-hover);
    border-color: var(--primary-hover);
    color: #ffffff;
  }
  .preset-btn:disabled,
  .preset-btn:disabled:hover {
    opacity: 0.4;
    cursor: not-allowed;
    border-color: var(--border-color);
    color: var(--text-muted);
    box-shadow: none;
  }
  .bra-hari-select {
    height: 2rem;
    padding: 0 1.6rem 0 0.55rem;
    font-size: 0.75rem;
    font-weight: 600;
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    border-radius: 0.6rem;
    border: 1px solid var(--border-color);
    background-color: var(--bg-card);
    color: var(--text-main);
    cursor: pointer;
    transition: border-color 150ms ease;
  }
  .bra-hari-select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-light);
  }

  /* ── Unified Typography Modifiers for Form Inputs ── */
  .bra-control.mono {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }
  .bra-control.upper {
    text-transform: uppercase;
  }
  .bra-control.bold {
    font-weight: 700;
  }

  /* ── Derajat Field + Aligned Segmented Quick Picker ── */
  .bra-derajat-row {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 0.5rem;
  }
  .bra-derajat-row > .field {
    flex: 1 1 10rem;
    min-width: 0;
  }
  .bra-segmented {
    display: inline-flex;
    align-items: stretch;
    gap: 0.25rem;
    padding: 0.25rem;
    border-radius: 0.875rem;
    border: 1px solid var(--border-color);
    background: var(--bg-card-subtle);
  }
  .bra-seg-btn {
    padding: 0 0.8rem;
    min-height: 2.5rem;
    border-radius: 0.625rem;
    border: 1px solid transparent;
    background: transparent;
    color: var(--text-muted);
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
    white-space: nowrap;
  }
  .bra-seg-btn:hover {
    color: var(--text-main);
    background: var(--bg-card);
  }
  .bra-seg-btn.is-active {
    background: var(--bg-card);
    color: var(--m-accent-text, var(--primary));
    border-color: color-mix(in srgb, var(--primary) 35%, transparent);
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
  }
  .bra-seg-btn:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }

  /* ── Floating Field with Inline Right Action (TWB / TWU) ── */
  .bra-field-action > .field-label {
    max-width: calc(100% - 6.75rem);
  }
  .bra-field-action > .field-input {
    padding-right: 5.75rem;
  }
  .bra-inline-action {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    z-index: 2;
    padding: 0.35rem 0.65rem;
    border-radius: 0.55rem;
    border: 1px solid color-mix(in srgb, var(--primary) 26%, transparent);
    background: var(--primary-light);
    color: var(--m-accent-text, var(--primary));
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    transition: background-color 150ms ease, color 150ms ease;
    white-space: nowrap;
  }
  .bra-inline-action:hover {
    background: var(--primary);
    color: #ffffff;
  }
  .bra-inline-action:focus-visible {
    outline: 2px solid var(--primary);
    outline-offset: 2px;
  }

  /* ── Collapsible Header Senkom Tambahan ── */
  .bra-details {
    border-radius: 0.875rem;
    border: 1px solid var(--border-color);
    background: var(--bg-card-subtle);
    padding: 0.75rem 1rem;
  }
  .bra-details summary {
    list-style: none;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    cursor: pointer;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    user-select: none;
  }
  .bra-details summary::-webkit-details-marker {
    display: none;
  }
  .bra-details summary:hover {
    color: var(--text-main);
  }
  .bra-details-chevron {
    width: 1rem;
    height: 1rem;
    flex-shrink: 0;
    transition: transform 160ms ease;
  }
  .bra-details[open] .bra-details-chevron {
    transform: rotate(180deg);
  }

  /* ── Telegram Body Section Row (AAA, BBB, CCC...) ── */
  .sec-item {
    background: var(--bg-card-subtle);
    border: 1px solid var(--border-color);
    border-radius: 0.875rem;
    padding: 0.9rem;
    transition: border-color 150ms ease, background-color 150ms ease;
  }
  .sec-item:focus-within {
    border-color: color-mix(in srgb, var(--primary) 65%, transparent);
    background: var(--bg-card);
  }
  .sec-item-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    margin-bottom: 0.6rem;
  }
  .sec-code-badge {
    font-family: 'JetBrains Mono', ui-monospace, monospace;
    font-weight: 700;
    font-size: 0.75rem;
    font-variant-numeric: tabular-nums;
    color: var(--m-accent-text, var(--primary));
    background: var(--primary-light);
    padding: 0.22rem 0.6rem;
    border-radius: 0.5rem;
    border: 1px solid color-mix(in srgb, var(--primary) 26%, transparent);
    letter-spacing: 0.05em;
  }
  .sec-del-btn {
    padding: 0.28rem 0.55rem;
    font-size: 0.72rem;
    font-weight: 600;
    color: #dc2626;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 0.5rem;
    cursor: pointer;
    transition: background-color 150ms ease, border-color 150ms ease;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
  }
  .dark .sec-del-btn,
  [data-theme="dark"] .sec-del-btn {
    color: #f87171;
  }
  .sec-del-btn:hover {
    background: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.25);
  }
  .sec-del-btn:focus-visible {
    outline: 2px solid #ef4444;
    outline-offset: 2px;
  }
  .bra-inline-warning {
    margin-top: 0.4rem;
    padding: 0.45rem 0.65rem;
    border-radius: 0.55rem;
    font-size: 0.75rem;
    font-weight: 500;
    color: #b91c1c;
    background: #fef2f2;
    border: 1px solid #fecaca;
  }
  .dark .bra-inline-warning,
  [data-theme="dark"] .bra-inline-warning {
    color: #fca5a5;
    background: rgba(127, 29, 29, 0.35);
    border-color: rgba(185, 28, 28, 0.55);
  }
  @media (min-width: 1200px) {
    .bra-mobile-preview-btn {
      display: none !important;
    }
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
    background: rgba(15, 23, 42, 0.72);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 1rem;
  }
  .bra-modal-overlay.open { display: flex; }
  .bra-modal-overlay.hidden { display: none !important; }
  .bra-modal-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.25rem;
    background: #cbd5e1;
    display: flex;
    justify-content: center;
  }
  .dark .bra-modal-body,
  [data-theme="dark"] .bra-modal-body {
    background: #090d16;
  }

  @media (prefers-reduced-motion: reduce) {
    .bra-form-shell,
    .bra-step-btn,
    .kat-tab-box,
    .kat-icon-wrap,
    .preset-btn,
    .bra-seg-btn,
    .bra-inline-action,
    .bra-details-chevron,
    .sec-item {
      transition: none !important;
    }
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
    .bra-sticky-bar, .ops-action-bar, .ops-form-header, .bra-card,
    .bra-stepper-wrap, .ops-modal-head, .bra-modal-overlay,
    .print\:hidden, [class*="print:hidden"] {
      display: none !important;
    }
    .app-container, .main-content, .page-body,
    .bra-layout-container, .bra-form-shell, .bra-split-grid, .bra-live-sticky-preview {
      display: block !important;
      width: 100% !important;
      max-width: 100% !important;
      position: static !important;
      overflow: visible !important;
      height: auto !important;
      min-height: auto !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    #braMasterForm, #formBraSar {
      display: none !important;
    }
    .bra-official-paper {
      zoom: 1 !important;
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

<div class="bra-form-shell space-y-5 bra-layout-container bra-split-active" id="braMainAppWrap">

  <!-- Unified Page Header -->
  <div class="print:hidden bento-card-v1 cockpit-glass ops-form-header">
    <div class="space-y-1.5 flex-1 min-w-0">
      <nav aria-label="Breadcrumb" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
        <a href="<?= e($braBackUrl) ?>" class="hover:text-orange-500 transition-colors inline-flex items-center gap-1 font-semibold">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
          <?= ($braReturnTo === 'operasi' && $braReturnOp > 0) ? 'Detail Operasi' : 'Berita Radio SAR' ?>
        </a>
        <span class="text-slate-300 dark:text-slate-600">/</span>
        <span class="text-orange-600 dark:text-orange-400 font-semibold"><?= $isEdit ? 'Edit BRA SAR' : 'Buat BRA SAR' ?></span>
      </nav>
      <div class="flex flex-wrap items-center gap-2.5">
        <h1 id="braFormPageHeading" class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          <?= $isEdit ? 'Edit Berita Radio SAR' : 'Buat Berita Radio SAR' ?>
        </h1>
        <?php if ($isEdit): ?>
          <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-400 border border-amber-500/30">
            Mode Perbaikan
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 shrink-0 self-end sm:self-auto">
      <button type="button" id="btnToggleSplitView" class="btn-tactile hidden xl:inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
        <span id="splitViewLabel">Sembunyikan Lembar</span>
      </button>

      <button type="button" id="btnFormatTelegramPunctuation" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-white hover:bg-slate-50 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
        Format Sandi
      </button>

      <button type="button" id="btnOpenModalPreview" class="btn-tactile inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        <span>Pratinjau A4</span>
      </button>
    </div>
  </div>

  <!-- Compact Stepper Navigation -->
  <nav aria-label="Bagian formulir BRA SAR" class="print:hidden bento-card-v1 cockpit-glass bra-stepper-wrap">
    <div class="bra-stepper" id="braStepperBar">
      <a href="#sec-kategori" class="bra-step-btn active" data-target="sec-kategori" aria-current="step">
        <span class="bra-step-num">01</span>
        <span>Kategori &amp; Operasi</span>
      </a>
      <a href="#sec-nomor" class="bra-step-btn" data-target="sec-nomor">
        <span class="bra-step-num">02</span>
        <span>Penomoran</span>
      </a>
      <a href="#sec-waktu-alamat" class="bra-step-btn" data-target="sec-waktu-alamat">
        <span class="bra-step-num">03</span>
        <span>Waktu &amp; Alamat</span>
      </a>
      <a href="#sec-batang-tubuh" class="bra-step-btn" data-target="sec-batang-tubuh">
        <span class="bra-step-num">04</span>
        <span>Batang Tubuh</span>
      </a>
      <a href="#sec-pengirim" class="bra-step-btn" data-target="sec-pengirim">
        <span class="bra-step-num">05</span>
        <span>Pengirim &amp; SENKOM</span>
      </a>
    </div>
  </nav>

  <!-- Main Split Workspace: Form (Left) + Live Paper Preview (Right) -->
  <div class="bra-split-grid">

    <!-- Form Column -->
    <div class="space-y-5" id="braFormContainer">
      <form method="POST" action="<?= BASE_URL ?>/bra.php?action=save<?= $isEdit ? '&id=' . $id : '' ?>" id="braMasterForm" aria-labelledby="braFormPageHeading" class="space-y-5">
        <?= csrfInput() ?>
        <?php if ($isEdit): ?>
          <input type="hidden" name="id_bra" value="<?= $id ?>">
        <?php endif; ?>
        <?php if ($braReturnTo !== '' && $braReturnOp > 0): ?>
          <input type="hidden" name="return_to" value="<?= e($braReturnTo) ?>">
          <input type="hidden" name="return_op" value="<?= $braReturnOp ?>">
        <?php endif; ?>
        <input type="hidden" name="satker_kode" value="<?= htmlspecialchars($satkerKode) ?>">
        <input type="hidden" name="bulan_tahun" id="hiddenBulanTahun" value="<?= htmlspecialchars($bra['bulan_tahun'] ?? $bulanTahunPlaceholder) ?>">
        <input type="hidden" name="nomor_urut" id="hiddenNomorUrut" value="<?= htmlspecialchars((string)($bra['nomor_urut'] ?? $nomorUrutPlaceholder)) ?>">
        <input type="hidden" name="nama_operasi" id="hiddenNamaOperasi" value="<?= htmlspecialchars($bra['nama_operasi'] ?? ($selectedOps['nama_operasi'] ?? '')) ?>">
        <input type="hidden" name="tanggal_waktu" id="hiddenTanggalWaktu" value="<?= htmlspecialchars($bra['tanggal_waktu'] ?? TemplateHelper::formatTanggalIndo(date('Y-m-d'))) ?>">
        <input type="hidden" name="jumlah_kata" id="hiddenJumlahKata" value="<?= htmlspecialchars($bra['jumlah_kata'] ?? '0 KATA') ?>">
        <input type="hidden" name="logo_url" value="<?= htmlspecialchars($customLogo) ?>">

        <!-- 01: Kategori & Tautan Operasi -->
        <section class="bento-card-v1 ops-section bra-card" id="sec-kategori">
          <div class="ops-section-head">
            <div class="ops-section-title-group">
              <span class="ops-section-num">01</span>
              <h2 class="ops-section-title">Kategori &amp; Tautan Operasi</h2>
            </div>
          </div>

          <fieldset class="space-y-4">
            <legend class="sr-only">Kategori Berita Radio SAR</legend>
            <div class="kat-tabs">
              <label class="kat-tab-label">
                <input type="radio" name="kategori" value="MAPEL" <?= $currentKategori === 'MAPEL' ? 'checked' : '' ?> class="kategori-radio bra-visually-hidden">
                <span class="kat-tab-box">
                  <span class="kat-icon-wrap" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.9 19.1A10 10 0 0 1 2 12a10 10 0 0 1 2.9-7.1M19.1 4.9A10 10 0 0 1 22 12a10 10 0 0 1-2.9 7.1M7.75 16.25A6 6 0 0 1 6 12a6 6 0 0 1 1.75-4.25M16.25 7.75A6 6 0 0 1 18 12a6 6 0 0 1-1.75 4.25M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/></svg>
                  </span>
                  <span class="kat-tab-title">BRA Mapel</span>
                </span>
              </label>

              <label class="kat-tab-label">
                <input type="radio" name="kategori" value="OPERASI_SAR" <?= $currentKategori === 'OPERASI_SAR' ? 'checked' : '' ?> class="kategori-radio bra-visually-hidden">
                <span class="kat-tab-box">
                  <span class="kat-icon-wrap" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v3m0 14v3M2 12h3m14 0h3"/></svg>
                  </span>
                  <span class="kat-tab-title">BRA Operasi SAR</span>
                </span>
              </label>

              <label class="kat-tab-label">
                <input type="radio" name="kategori" value="PERGERAKAN_KAPAL" <?= $currentKategori === 'PERGERAKAN_KAPAL' ? 'checked' : '' ?> class="kategori-radio bra-visually-hidden">
                <span class="kat-tab-box">
                  <span class="kat-icon-wrap" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 17.5 4.5 19a3 3 0 0 0 4.24 0L10 17.74a3 3 0 0 1 4 0L15.26 19a3 3 0 0 0 4.24 0L21 17.5M4 14l2-8h12l2 8H4Zm8-11v3m-3-1.5h6"/></svg>
                  </span>
                  <span class="kat-tab-title">BRA Pergerakan Kapal</span>
                </span>
              </label>
            </div>
          </fieldset>

          <!-- Quick MAPEL Template Bar -->
          <div id="mapelPresetsContainer" class="ops-subpanel <?= $currentKategori === 'MAPEL' ? '' : 'hidden' ?>" style="display: <?= $currentKategori === 'MAPEL' ? 'block' : 'none' ?>;">
            <div class="bra-draft-row">
              <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Template Mapel</span>
              <button type="button" id="btnPresetLongboatHilangKontak" class="btn-tactile preset-btn preset-btn-accent">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                <span>Longboat / Kapal Hilang Kontak</span>
              </button>
            </div>
          </div>

          <!-- Operasi SAR Link Select -->
          <div class="field">
            <label for="operasiSelect" class="field-label">Operasi SAR Terkait (Opsional)</label>
            <select name="id_operasi" id="operasiSelect" class="field-input bra-control">
              <option value="" data-nama="">Tanpa Kaitan Operasi SAR</option>
              <?php foreach ($operasiList as $op): ?>
                <option value="<?= $op['id_operasi'] ?>" data-nama="<?= htmlspecialchars($op['nama_operasi']) ?>" <?= ($currentOpId === (int)$op['id_operasi']) ? 'selected' : '' ?>>
                  #OPS-<?= $op['id_operasi'] ?> &bull; <?= htmlspecialchars($op['nama_operasi']) ?> (<?= htmlspecialchars($op['status'] ?? 'OPEN') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Smart Draft Toolbar (Operasi SAR Terkait) -->
          <div id="operasiRecommendationBar" class="ops-subpanel <?= $currentOpId ? '' : 'hidden' ?>">
            <div class="bra-draft-row">
              <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 shrink-0">Isi Draf</span>
              <div class="bra-draft-actions">
                <button type="button" id="btnAutoInisiasi" class="btn-tactile preset-btn">Laporan Awal</button>
                <span class="bra-draft-combo">
                  <button type="button" id="btnAutoRenops" class="btn-tactile preset-btn">RENOPS</button>
                  <select id="selectRenopsHari" class="bra-hari-select" aria-label="Hari RENOPS"></select>
                </span>
                <span class="bra-draft-combo">
                  <button type="button" id="btnAutoSitrep" class="btn-tactile preset-btn">Lapbang</button>
                  <select id="selectLapbangHari" class="bra-hari-select" aria-label="Hari Lapbang"></select>
                </span>
                <button type="button" id="btnAutoPenutupan" class="btn-tactile preset-btn">Penutupan</button>
                <button type="button" id="btnAutoMapel" class="btn-tactile preset-btn">Mapel</button>
              </div>
            </div>
          </div>
        </section>

        <!-- 02: Penomoran & Administrasi -->
        <section class="bento-card-v1 ops-section bra-card" id="sec-nomor">
          <div class="ops-section-head">
            <div class="ops-section-title-group">
              <span class="ops-section-num">02</span>
              <h2 class="ops-section-title">Penomoran &amp; Administrasi</h2>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- No BRA -->
            <div>
              <div class="field">
                <label for="noBraInput" class="field-label">Nomor Naskah BRA <span class="text-red-500" aria-hidden="true">*</span></label>
                <input class="field-input bra-control mono bold upper" type="text" name="no_bra" id="noBraInput" value="<?= htmlspecialchars(!empty($bra['no_bra']) ? $bra['no_bra'] : $noBraPlaceholder) ?>" placeholder="<?= $noBraPlaceholder ?>" autocorrect="off" spellcheck="false" aria-describedby="noBraWarning" required>
              </div>
              <input type="hidden" name="is_auto_no_bra" id="isAutoNoBra" value="<?= $isEdit ? '0' : '1' ?>">
              <div id="noBraWarning" role="alert" aria-live="polite" class="hidden bra-inline-warning"></div>
            </div>

            <!-- MENBRA -->
            <div id="menbraFieldWrapper">
              <div class="field">
                <label id="menbraLabelText" for="menbraInput" class="field-label"><span id="labelMenbraTitle">MENBRA (Rujukan)</span></label>
                <input class="field-input bra-control mono upper" type="text" name="menbra" id="menbraInput" list="menbraOptions" autocomplete="off" autocorrect="off" spellcheck="false" value="<?= (strtoupper(trim((string)($bra['menbra'] ?? ''))) === 'SEGERA' || trim((string)($bra['menbra'] ?? '')) === '-') ? '' : htmlspecialchars($bra['menbra'] ?? '') ?>" placeholder="<?= max(1, $nomorUrutPlaceholder - 1) ?>/<?= $satkerKode ?>/<?= $bulanTahunPlaceholder ?>">
                <datalist id="menbraOptions"></datalist>
              </div>
            </div>
          </div>

          <!-- Derajat Pengiriman -->
          <?php $curDerajat = strtoupper(trim((string)($bra['derajat'] ?? 'SGR'))); ?>
          <div class="bra-derajat-row">
            <div class="field">
              <label for="derajatInput" class="field-label">Derajat Pengiriman <span class="text-red-500" aria-hidden="true">*</span></label>
              <input type="text" name="derajat" id="derajatInput" value="<?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?>" placeholder="SGR" class="field-input bra-control mono bold upper" autocorrect="off" spellcheck="false" required>
            </div>
            <div class="bra-segmented" role="group" aria-label="Pilihan derajat pengiriman">
              <button type="button" data-derajat="SGR" aria-pressed="<?= $curDerajat === 'SGR' ? 'true' : 'false' ?>" class="bra-seg-btn<?= $curDerajat === 'SGR' ? ' is-active' : '' ?>">SGR</button>
              <button type="button" data-derajat="KILAT" aria-pressed="<?= $curDerajat === 'KILAT' ? 'true' : 'false' ?>" class="bra-seg-btn<?= $curDerajat === 'KILAT' ? ' is-active' : '' ?>">KILAT</button>
              <button type="button" data-derajat="RUTIN" aria-pressed="<?= $curDerajat === 'RUTIN' ? 'true' : 'false' ?>" class="bra-seg-btn<?= $curDerajat === 'RUTIN' ? ' is-active' : '' ?>">RUTIN</button>
            </div>
          </div>

          <!-- Collapsible Header Senkom Tambahan -->
          <details class="bra-details">
            <summary>
              <span>Header Senkom Tambahan</span>
              <svg class="bra-details-chevron" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19 9-7 7-7-7"/></svg>
            </summary>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5 mt-3.5 pt-3.5 border-t border-slate-200/80 dark:border-slate-800">
              <div class="field">
                <label for="panggilanInput" class="field-label">Panggilan</label>
                <input class="field-input bra-control mono" type="text" name="panggilan" id="panggilanInput" value="<?= htmlspecialchars($bra['panggilan'] ?? '') ?>" placeholder="-" autocorrect="off" spellcheck="false">
              </div>
              <div class="field">
                <label for="jenisInput" class="field-label">Jenis</label>
                <input class="field-input bra-control mono" type="text" name="jenis" id="jenisInput" value="<?= htmlspecialchars($bra['jenis'] ?? '') ?>" placeholder="SAR" autocorrect="off" spellcheck="false">
              </div>
              <div class="field">
                <label for="nrStnInput" class="field-label">Nr. Stn</label>
                <input class="field-input bra-control mono" type="text" name="nr_stn" id="nrStnInput" value="<?= htmlspecialchars($bra['nr_stn'] ?? '') ?>" placeholder="-" autocorrect="off" spellcheck="false">
              </div>
              <div class="field">
                <label for="instruksiPenerusanInput" class="field-label">Instruksi Penerusan</label>
                <input class="field-input bra-control mono" type="text" name="instruksi_penerusan" id="instruksiPenerusanInput" value="<?= htmlspecialchars($bra['instruksi_penerusan'] ?? '') ?>" placeholder="-" autocorrect="off" spellcheck="false">
              </div>
            </div>
          </details>
        </section>

        <!-- 03: Waktu & Alamat -->
        <section class="bento-card-v1 ops-section bra-card" id="sec-waktu-alamat">
          <div class="ops-section-head">
            <div class="ops-section-title-group">
              <span class="ops-section-num">03</span>
              <h2 class="ops-section-title">Waktu &amp; Alamat</h2>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field bra-field-action">
              <label for="twbInput" class="field-label">TWB (WITA) <span class="text-red-500" aria-hidden="true">*</span></label>
              <input type="text" name="twb" id="twbInput" value="<?= htmlspecialchars($bra['twb'] ?? '') ?>" placeholder="<?= $twbPlaceholder ?>" class="field-input bra-control mono bold upper" autocorrect="off" spellcheck="false" required>
              <button type="button" id="btnSetTwbNow" class="bra-inline-action">Sekarang</button>
            </div>

            <div class="field bra-field-action">
              <label for="twuInput" class="field-label">TWU (WITA) <span class="text-red-500" aria-hidden="true">*</span></label>
              <input type="text" name="twu" id="twuInput" value="<?= htmlspecialchars($bra['twu'] ?? '') ?>" placeholder="<?= $twuPlaceholder ?>" class="field-input bra-control mono bold upper" autocorrect="off" spellcheck="false" required>
              <button type="button" id="btnSetTwuNow" class="bra-inline-action">Sekarang</button>
            </div>
          </div>

          <div class="space-y-4">
            <div class="field">
              <label for="dariInput" class="field-label">DARI <span class="text-red-500" aria-hidden="true">*</span></label>
              <input placeholder=" " class="field-input bra-control bold upper" type="text" name="dari" id="dariInput" value="<?= htmlspecialchars($bra['dari'] ?? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?>" required>
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
            <div class="field">
              <label for="aksiInput" class="field-label">AKSI <span class="text-red-500" aria-hidden="true">*</span></label>
              <textarea name="aksi" id="aksiInput" rows="2" class="field-input bra-control upper font-semibold" placeholder="<?= htmlspecialchars($placeholderAksi) ?>" required><?= htmlspecialchars($defaultAksiVal) ?></textarea>
            </div>

            <div class="field">
              <label for="infoInput" class="field-label">INFO</label>
              <textarea name="info" id="infoInput" rows="2" class="field-input bra-control upper" placeholder="<?= htmlspecialchars($placeholderInfo) ?>"><?= htmlspecialchars($defaultInfoVal) ?></textarea>
            </div>

            <div class="field">
              <label for="perihalInput" class="field-label">PERIHAL <span class="text-red-500" aria-hidden="true">*</span></label>
              <textarea name="perihal" id="perihalInput" rows="2" class="field-input bra-control bold upper text-orange-600 dark:text-orange-400" placeholder="KECELAKAAN KAPAL..." required><?= htmlspecialchars($bra['perihal'] ?? '') ?></textarea>
            </div>
          </div>
        </section>

        <!-- 04: Batang Tubuh Naskah -->
        <section class="bento-card-v1 ops-section bra-card" id="sec-batang-tubuh">
          <div class="ops-section-head">
            <div class="ops-section-title-group">
              <span class="ops-section-num">04</span>
              <h2 class="ops-section-title">Batang Tubuh Naskah</h2>
            </div>
            <div class="flex items-center gap-2 shrink-0">
              <span id="liveWordCountBadge" class="ops-counter-pill">0 KATA</span>
              <button type="button" id="btnAddSectionBtn" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white text-xs font-semibold transition cursor-pointer shadow-xs">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span>Tambah Bagian</span>
              </button>
            </div>
          </div>

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
                  </div>
                  <?php if ($index >= 3): ?>
                    <button type="button" class="sec-del-btn btn-delete-section" title="Hapus bagian ini">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                      <span>Hapus</span>
                    </button>
                  <?php endif; ?>
                </div>
                <textarea id="secContent<?= $index ?>" aria-label="Isi bagian <?= htmlspecialchars($sec['kode']) ?>" name="sections[<?= $index ?>][isi]" rows="4" class="control bra-control sec-content-textarea upper w-full text-sm leading-relaxed" placeholder="Isi bagian <?= htmlspecialchars($sec['kode']) ?>..." required><?= htmlspecialchars($sec['isi'] ?? '') ?></textarea>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- 05: Pengirim & Register SENKOM -->
        <section class="bento-card-v1 ops-section bra-card" id="sec-pengirim">
          <div class="ops-section-head">
            <div class="ops-section-title-group">
              <span class="ops-section-num">05</span>
              <h2 class="ops-section-title">Pengirim &amp; Register SENKOM</h2>
            </div>
          </div>

          <?php if (!empty($pejabatList)): ?>
            <div class="field">
              <label for="pejabatSelectControl" class="field-label">Master Pejabat / SMC</label>
              <select id="pejabatSelectControl" class="field-input bra-control">
                <option value="">Pilih Pejabat Penandatangan</option>
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

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
              <label for="pengirimInput" class="field-label">Instansi Pengirim <span class="text-red-500" aria-hidden="true">*</span></label>
              <input placeholder=" " class="field-input bra-control bold upper" type="text" name="pengirim" id="pengirimInput" value="<?= htmlspecialchars($bra['pengirim'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?>" required>
            </div>
            <div class="field">
              <label for="namaSmcInput" class="field-label">Nama Pejabat / SMC <span class="text-red-500" aria-hidden="true">*</span></label>
              <input placeholder=" " class="field-input bra-control bold upper" type="text" name="nama_smc" id="namaSmcInput" autocomplete="name" value="<?= htmlspecialchars($bra['nama_smc'] ?? 'AMIRUDDIN A.S., S.Sos.') ?>" required>
            </div>
            <div class="field">
              <label for="pangkatSmcInput" class="field-label">Pangkat / Golongan <span class="text-red-500" aria-hidden="true">*</span></label>
              <input placeholder=" " class="field-input bra-control upper" type="text" name="pangkat_smc" id="pangkatSmcInput" value="<?= htmlspecialchars($bra['pangkat_smc'] ?? 'PEMBINA (IV/a)') ?>" required>
            </div>
            <div class="field">
              <label for="jabatanSmcInput" class="field-label">Jabatan <span class="text-red-500" aria-hidden="true">*</span></label>
              <input placeholder=" " class="field-input bra-control bold upper" type="text" name="jabatan_smc" id="jabatanSmcInput" value="<?= htmlspecialchars($bra['jabatan_smc'] ?? ($currentKategori === 'MAPEL' || !$currentOpId ? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI' : 'SMC')) ?>" required>
            </div>
          </div>

          <fieldset class="pt-4 border-t border-slate-100 dark:border-slate-800">
            <legend class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-3">Register SENKOM</legend>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div class="field">
                <label for="twtInput" class="field-label">T.W.T</label>
                <input class="field-input bra-control mono text-center" type="text" name="twt" id="twtInput" value="<?= htmlspecialchars($bra['twt'] ?? '') ?>" placeholder="-" autocorrect="off" spellcheck="false">
              </div>
              <div class="field">
                <label for="twkInput" class="field-label">T.W.K</label>
                <input class="field-input bra-control mono text-center" type="text" name="twk" id="twkInput" value="<?= htmlspecialchars($bra['twk'] ?? '') ?>" placeholder="-" autocorrect="off" spellcheck="false">
              </div>
              <div class="field">
                <label for="sistemInput" class="field-label">Sistem</label>
                <input class="field-input bra-control mono text-center" type="text" name="sistem" id="sistemInput" value="<?= htmlspecialchars($bra['sistem'] ?? '') ?>" placeholder="HF / VHF" autocorrect="off" spellcheck="false">
              </div>
              <div class="field">
                <label for="parafInput" class="field-label">Paraf</label>
                <input class="field-input bra-control mono text-center" type="text" name="paraf" id="parafInput" value="<?= htmlspecialchars($bra['paraf'] ?? '') ?>" placeholder="-" autocorrect="off" spellcheck="false">
              </div>
            </div>
          </fieldset>
        </section>

        <!-- Unified Sticky Action Footer -->
        <div class="cockpit-glass ops-action-bar bra-sticky-bar">
          <div class="flex items-center gap-2">
            <a href="<?= e($braBackUrl) ?>" class="btn-tactile inline-flex items-center justify-center px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 font-semibold text-xs transition">
              Batal
            </a>
          </div>

          <div class="flex items-center gap-2.5 justify-end">
            <button type="button" id="btnOpenModalPreviewBottom" class="btn-tactile bra-mobile-preview-btn inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 font-semibold text-xs border border-slate-200 dark:border-slate-700 transition cursor-pointer">
              Pratinjau A4
            </button>
            <button type="submit" id="btnSubmitBra" class="btn-tactile inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-semibold text-xs transition cursor-pointer shadow-sm">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
              <span><?= $isEdit ? 'Simpan Perubahan' : 'Terbitkan BRA SAR' ?></span>
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Live Split Preview Column -->
    <div id="braLiveSplitColumn" class="bra-live-sticky-preview">
      <div class="print:hidden bento-card-v1 cockpit-glass rounded-2xl px-4 py-2.5 mb-3 flex items-center justify-between gap-2.5">
        <div class="flex items-center gap-2 min-w-0">
          <svg class="w-4 h-4 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
          <span class="text-xs font-bold text-slate-900 dark:text-white truncate">Pratinjau Lembar BRA SAR</span>
        </div>
        <span class="ops-counter-pill text-[11px] shrink-0">A4</span>
      </div>

      <!-- Screen-only card frame: provides drop-shadow on UI without leaking outer borders into PDF -->
      <div class="bg-white rounded-2xl shadow-md p-3 sm:p-4 border border-slate-200/90 dark:border-slate-800 overflow-x-auto">
        <!-- Live Replicating Official Sheet (Identik 100% dengan bra_view.php) -->
        <div class="bra-official-paper w-full bg-white text-black p-0 border-0 shadow-none font-sans" id="braLivePaperPreview">
        
        <!-- Header Kop: Logo sejajar dengan BRA SAR dan BADAN NASIONAL PENCARIAN DAN PERTOLONGAN -->
        <div class="bra-kop relative flex items-center justify-center gap-4 pb-2 mb-2 border-b-2 border-black text-black">
          <div class="w-12 h-12 shrink-0">
            <?php if (!empty($customLogo)): ?>
              <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
            <?php else: ?>
              <img src="<?= assetUrl('assets/images/logo_basarnas.svg') ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
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


<!-- Modal Full Pratinjau Lembar Resmi Cetak -->
<div id="modalFullBraPreview" class="bra-modal-overlay hidden print:hidden" role="dialog" aria-modal="true" aria-labelledby="modalBraPreviewTitle">
  <div class="ops-modal-card max-w-4xl">
    <div class="ops-modal-head">
      <div class="flex items-center gap-2.5 min-w-0">
        <svg class="w-4 h-4 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        <h3 id="modalBraPreviewTitle" class="text-sm font-bold text-slate-900 dark:text-white truncate">Pratinjau Lembar BRA SAR</h3>
      </div>
      <div class="flex items-center gap-2 shrink-0">
        <button type="button" onclick="window.downloadBraPdf('braLivePaperPreview')" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 transition cursor-pointer">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
          <span>Unduh PDF</span>
        </button>
        <button type="button" onclick="window.printLiveDocument()" class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-orange-600 hover:bg-orange-500 text-white transition cursor-pointer">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24-1.07-.672-2.126-.672-3.329 0-3.037 2.463-5.5 5.5-5.5s5.5 2.463 5.5 5.5c0 1.203-.432 2.259-.672 3.329M6.75 19.5h10.5a2.25 2.25 0 002.25-2.25V13.5a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 13.5v3.75a2.25 2.25 0 002.25 2.25zM6.75 19.5v-3h10.5v3"/></svg>
          <span>Cetak</span>
        </button>
        <button type="button" id="btnCloseModalPreview" aria-label="Tutup" class="ops-close-btn">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
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
<script>
window.__BRA_FORM_CONFIG__ = <?= jsonSafe([
    'twuPlaceholder' => $twuPlaceholder,
    'twbPlaceholder' => $twbPlaceholder,
    'noBraPlaceholder' => $noBraPlaceholder,
    'nomorUrutPlaceholder' => $nomorUrutPlaceholder,
    'satkerKode' => $satkerKode,
    'bulanTahunPlaceholder' => $bulanTahunPlaceholder,
    'verifyUrl' => BASE_URL . '/bra.php?action=verify&id=' . ($id ?: 9999),
    'idBra' => (int)($id ?: 0),
    'logoDataUri' => $logoDataUri,
    'autoDraft' => $autoDraft,
    'html2pdfUrl' => assetUrl('assets/vendor/html2pdf/html2pdf.bundle.min.js'),
]) ?>;
</script>
<script src="<?= assetUrl('assets/js/bra/bra_templates.js') ?>"></script>
<script src="<?= assetUrl('assets/js/pages/bra_form.js') ?>"></script>
