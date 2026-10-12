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

$id = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$bra = $isEdit ? BraModel::getById($id) : null;

$initialKategori = $_GET['kategori'] ?? ($bra['kategori'] ?? 'MAPEL');
$preselectedOpId = (int)($_GET['id_operasi'] ?? ($bra['id_operasi'] ?? 0));

$operasiList = OperasiModel::getAll();
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
$twuPlaceholder = BraHelper::formatMilitaryTw($now, 'H');
$twbPlaceholder = BraHelper::formatMilitaryTw(date('Y-m-d H:i:s', strtotime('-15 minutes')), 'H');

$currentKategori = strtoupper($bra['kategori'] ?? $initialKategori);
$currentOpId = $bra['id_operasi'] ?? ($preselectedOpId ?: null);
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
    border: 2px solid #000000;
    box-shadow: var(--shadow-lg);
    width: 100%;
    margin: 0 auto;
    padding: 1.25rem 1.5rem;
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
    writing-mode: vertical-rl;
    transform: rotate(180deg);
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
    body, html {
      background: #ffffff !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    nav, aside, header, .bra-sticky-bar, .bra-card, .bra-stepper-wrap, .bra-modal-header, .btn-primary-bra, .btn-outline-bra {
      display: none !important;
    }
    .bra-layout-container, .bra-split-grid, .bra-live-sticky-preview {
      display: block !important;
      width: 100% !important;
      position: static !important;
      overflow: visible !important;
      padding: 0 !important;
      margin: 0 !important;
    }
    #formBraSar {
      display: none !important;
    }
    .bra-official-paper {
      border: 2px solid #000000 !important;
      box-shadow: none !important;
      width: 100% !important;
      max-width: 100% !important;
      padding: 0 !important;
      margin: 0 auto !important;
    }
  }
</style>

<div class="space-y-5 bra-layout-container" id="braMainAppWrap">

  <!-- ════════════════════════════════════════════════════════ -->
  <!-- TOP HEADER & CONTROLS TOOLBAR                           -->
  <!-- ════════════════════════════════════════════════════════ -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-4 sm:p-5 rounded-2xl shadow-md border transition-colors"
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
        <input type="hidden" name="nama_operasi" id="hiddenNamaOperasi" value="<?= htmlspecialchars($bra['nama_operasi'] ?? '') ?>">
        <input type="hidden" name="tanggal_waktu" id="hiddenTanggalWaktu" value="<?= htmlspecialchars($bra['tanggal_waktu'] ?? date('d F Y')) ?>">
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

            <!-- 2. SAR UMUM -->
            <label class="kat-tab-label">
              <input type="radio" name="kategori" value="SAR_UMUM" <?= $currentKategori === 'SAR_UMUM' ? 'checked' : '' ?> class="kategori-radio">
              <div class="kat-tab-box">
                <div class="kat-icon-wrap">📋</div>
                <div style="flex:1;">
                  <div class="kat-tab-header">
                    <span class="kat-tab-title">2. BRA Operasi SAR</span>
                    <span class="kat-tab-pill">Operasi Harian</span>
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

          <!-- Quick Vessel Presets Bar (Muncul Khusus PERGERAKAN_KAPAL) -->
          <div id="vesselPresetsContainer" class="bra-preset-banner mb-4 <?= $currentKategori === 'PERGERAKAN_KAPAL' ? '' : 'hidden' ?>" style="border-color: rgba(6, 182, 212, 0.4); background: rgba(6, 182, 212, 0.08);">
            <div class="flex items-center gap-2 text-xs font-semibold text-cyan-800 dark:text-cyan-300">
              <span>🚢</span>
              <span><strong>Pilihan Cepat Format Lampiran Kapal:</strong> Muat spesifikasi teknis alut resmi:</span>
            </div>
            <div class="flex items-center gap-2">
              <button type="button" id="btnPresetPacitan" class="preset-btn vessel">
                ⚓ KN SAR Pacitan 102 (BKO)
              </button>
              <button type="button" id="btnPresetRB210" class="preset-btn vessel">
                🚤 Rescue Boat 210 (Pemanasan)
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
              <option value="">— Buat Mandiri / Tanpa Kaitan Operasi SAR —</option>
              <?php foreach ($operasiList as $op): ?>
                <option value="<?= $op['id_operasi'] ?>" <?= ($currentOpId === (int)$op['id_operasi']) ? 'selected' : '' ?>>
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
              <span class="bra-hint">Format resmi: <code>[Nomor Urut]/<?= htmlspecialchars($satkerKode) ?>/[BulanTahun]</code></span>
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

            <!-- MENBRA / Sifat -->
            <div class="bra-field" id="menbraFieldWrapper">
              <label class="bra-label" id="menbraLabelText">
                <span id="labelMenbraTitle">MENBRA (Rujukan BRA Lalu)</span>
              </label>
              <input type="text" name="menbra" id="menbraInput" value="<?= htmlspecialchars($bra['menbra'] ?? '') ?>" placeholder="<?= max(1, $nomorUrutPlaceholder - 1) ?>/<?= $satkerKode ?>/<?= $bulanTahunPlaceholder ?>" class="bra-control mono upper">
              <span class="bra-hint" id="hintMenbraText">Nomor naskah BRA sebelumnya yang menjadi dasar rujukan (bila ada).</span>
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
              <textarea name="aksi" id="aksiInput" rows="2" class="bra-control upper font-semibold" placeholder="KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN" required><?= htmlspecialchars($bra['aksi'] ?? '') ?></textarea>
            </div>

            <div class="bra-field">
              <label class="bra-label">INFO (Tembusan Informasi)</label>
              <textarea name="info" id="infoInput" rows="2" class="bra-control upper" placeholder="DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN"><?= htmlspecialchars($bra['info'] ?? '') ?></textarea>
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
              <input type="text" name="nama_smc" id="namaSmcInput" value="<?= htmlspecialchars($bra['nama_smc'] ?? 'AMIRUDDIN AS, S.Sos.') ?>" class="bra-control bold upper" required>
            </div>
          </div>

          <div class="bra-grid-2 mb-4">
            <div class="bra-field">
              <label class="bra-label">Pangkat / Golongan <span class="req">*</span></label>
              <input type="text" name="pangkat_smc" id="pangkatSmcInput" value="<?= htmlspecialchars($bra['pangkat_smc'] ?? 'PEMBINA (IV/a)') ?>" class="bra-control upper" required>
            </div>
            <div class="bra-field">
              <label class="bra-label">Jabatan Kedinasan <span class="req">*</span></label>
              <input type="text" name="jabatan_smc" id="jabatanSmcInput" value="<?= htmlspecialchars($bra['jabatan_smc'] ?? ($currentOpId ? 'SMC' : 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI')) ?>" class="bra-control bold upper" required>
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

      <!-- Live Replicating Official Sheet (Identik 100% dengan bra_view.php) -->
      <div class="bra-official-paper rounded-xl" id="braLivePaperPreview">
        
        <!-- Header Kop: Logo sejajar dengan BRA SAR dan BADAN NASIONAL PENCARIAN DAN PERTOLONGAN -->
        <div class="bra-kop pb-1.5 mb-1.5 border-b-2 border-black text-black">
          <div class="w-10 h-10 shrink-0">
            <?php if (!empty($customLogo)): ?>
              <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
            <?php else: ?>
              <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" class="w-full h-full object-contain">
            <?php endif; ?>
          </div>
          <div class="text-center">
            <h1 class="text-lg font-black tracking-wider uppercase leading-none text-black m-0 p-0">
              BRA SAR
            </h1>
            <h2 class="text-[10px] font-bold tracking-wider uppercase leading-none text-black m-0 mt-1 p-0">
              BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
            </h2>
          </div>
        </div>

        <!-- Main Body Container with Left SENKOM Note -->
        <div class="relative pl-5 w-full">
          <!-- Left Vertical Side Label -->
          <div class="absolute left-0 top-0 bottom-0 w-4 flex items-center justify-center select-none pointer-events-none senkom-vertical-label">
            <span class="text-[7.5px] font-bold tracking-widest uppercase text-center leading-none text-black whitespace-nowrap">
              RUANGAN DILUAR GARIS TEBAL DI ISI OLEH DINAS SENKOM
            </span>
          </div>

          <!-- Right Main Table Box (Thick Borders) -->
          <div class="border-2 border-black divide-y-2 divide-black text-[10px] bg-white text-black w-full">
            
            <!-- SENKOM Top Header Row -->
            <div class="bra-top-header grid grid-cols-5 divide-x-2 divide-black text-center font-bold bg-white text-black text-[9px] leading-tight">
              <div class="py-1 px-1">
                <div>Panggilan</div>
                <div id="pvPanggilan" class="font-mono text-[9px] sm:text-[10px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['panggilan'] ?? '—') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Jenis</div>
                <div id="pvJenis" class="font-mono text-[9px] sm:text-[10px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['jenis'] ?? 'SAR') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Nr. Stn</div>
                <div id="pvNrStn" class="font-mono text-[9px] sm:text-[10px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['nr_stn'] ?? '—') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Derajat</div>
                <div id="pvDerajat" class="font-extrabold text-[10px] sm:text-[11px] border-t border-black mt-0.5 pt-0.5 text-black">
                  <?= htmlspecialchars($bra['derajat'] ?? 'SGR') ?>
                </div>
              </div>
              <div class="py-1 px-1">
                <div>Instruksi penerusan</div>
                <div id="pvInstruksiPenerusan" class="font-mono text-[9px] sm:text-[10px] border-t border-black mt-0.5 pt-0.5 text-black">
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
            <div class="border-t-2 border-black p-2.5 bg-white text-black">
              <!-- NO. & Menbra & Perihal -->
              <div class="mb-2.5">
                <div class="space-y-0.5">
                  <div class="flex items-start text-[11px] font-bold uppercase">
                    <span class="w-14 font-black shrink-0 text-black">NO.</span>
                    <span class="w-3 text-center shrink-0 text-black font-black">:</span>
                    <span id="pvNoBra" class="flex-1 font-extrabold tracking-wider ml-1 text-black"><?= htmlspecialchars(!empty($bra['no_bra']) ? $bra['no_bra'] : $noBraPlaceholder) ?></span>
                  </div>
                  <div id="pvMenbraRow" class="flex items-start text-[11px] font-bold uppercase <?= empty($bra['menbra']) ? 'hidden' : '' ?>">
                    <span class="w-14 font-black shrink-0 text-black">MENBRA</span>
                    <span class="w-3 text-center shrink-0 text-black font-black">:</span>
                    <span id="pvMenbra" class="flex-1 font-extrabold tracking-wider ml-1 text-black"><?= htmlspecialchars($bra['menbra'] ?? '') ?></span>
                  </div>
                </div>
                <div id="pvPerihal" class="font-black uppercase text-[11px] tracking-tight leading-snug mt-2 mb-1 text-black">
                  <?= htmlspecialchars($bra['perihal'] ?? 'BERITA RADIO SAR') ?>
                </div>
              </div>

              <!-- Sections Rows -->
              <div id="pvSectionsContainer" class="space-y-2">
                <!-- Injected via Live JS -->
              </div>

              <!-- TWB at bottom right of telegram text -->
              <div id="pvTwb" class="text-right font-black text-[11px] tracking-wider uppercase pt-2 pr-1 text-black">
                TWB <?= htmlspecialchars($bra['twb'] ?? $twbPlaceholder) ?>
              </div>
            </div>

            <!-- Bottom Footer Section: SMC / Pengirim with Barcode & SENKOM Signatures -->
            <div class="grid grid-cols-12 divide-x-2 divide-black bg-white text-black">
              <!-- Left Column: Pengirim (SMC) with QR Barcode -->
              <div class="col-span-7 p-2 flex flex-col justify-between">
                <div>
                  <div class="space-y-0.5">
                    <div class="flex">
                      <span class="w-16 font-bold uppercase shrink-0 text-black">PENGIRIM</span>
                      <span class="mr-1">:</span>
                      <span id="pvPengirim" class="font-bold uppercase text-[9.5px] text-black"><?= htmlspecialchars($bra['pengirim'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI') ?></span>
                    </div>
                    <div class="flex">
                      <span class="w-16 font-bold uppercase shrink-0 text-black">Nama</span>
                      <span class="mr-1">:</span>
                      <span id="pvNamaSmc" class="font-bold uppercase text-[9.5px] text-black"><?= htmlspecialchars($bra['nama_smc'] ?? 'AMIRUDDIN AS, S.Sos.') ?></span>
                    </div>
                    <div class="flex">
                      <span class="w-16 font-bold uppercase shrink-0 text-black">Pangkat</span>
                      <span class="mr-1">:</span>
                      <span id="pvPangkatSmc" class="uppercase text-[9.5px] text-black"><?= htmlspecialchars($bra['pangkat_smc'] ?? 'PEMBINA (IV/a)') ?></span>
                    </div>
                    <div class="flex">
                      <span class="w-16 font-bold uppercase shrink-0 text-black">Jabatan</span>
                      <span class="mr-1">:</span>
                      <span id="pvJabatanSmc" class="font-bold uppercase text-[9.5px] text-black"><?= htmlspecialchars($bra['jabatan_smc'] ?? 'SMC') ?></span>
                    </div>
                  </div>
                </div>

                <!-- Barcode QR Code Section -->
                <div class="mt-1.5 pt-1.5 border-t border-black">
                  <div class="flex items-start justify-between">
                    <div>
                      <span class="font-bold uppercase text-[9px] block text-black">Tanda Tangan :</span>
                      <span class="text-[8px] font-mono block mt-0.5 text-gray-700">(Barcode Digital Terverifikasi)</span>
                    </div>
                    <div class="flex flex-col items-center">
                      <div class="p-0.5 bg-white border border-black rounded shadow-xs" style="width: 72px; height: 72px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="liveBraQrCanvas" width="160" height="160" style="width: 66px; height: 66px; max-width: 66px; max-height: 66px;" class="w-16 h-16 object-contain"></canvas>
                      </div>
                      <div class="text-[7.5px] font-mono text-center mt-0.5 text-gray-700">SCAN UNTUK VERIFIKASI</div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Right Column: SENKOM Processing Columns -->
              <div class="col-span-5 flex flex-col justify-between">
                <div class="grid grid-cols-4 divide-x-2 divide-black border-b-2 border-black text-center font-bold py-0.5 text-[8.5px] bg-white text-black">
                  <div>T. W. T</div>
                  <div>T. W. K</div>
                  <div>SISTEM</div>
                  <div>PARAF</div>
                </div>
                <div class="grid grid-cols-4 divide-x-2 divide-black flex-1 min-h-[75px] bg-white text-black">
                  <div id="pvTwt" class="p-0.5 text-center font-mono text-[8.5px] text-black"><?= htmlspecialchars($bra['twt'] ?? '') ?></div>
                  <div id="pvTwk" class="p-0.5 text-center font-mono text-[8.5px] text-black"><?= htmlspecialchars($bra['twk'] ?? '') ?></div>
                  <div id="pvSistem" class="p-0.5 text-center font-mono text-[8.5px] text-black"><?= htmlspecialchars($bra['sistem'] ?? '') ?></div>
                  <div id="pvParaf" class="p-0.5 text-center font-mono text-[8.5px] text-black"><?= htmlspecialchars($bra['paraf'] ?? '') ?></div>
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


<!-- ════════════════════════════════════════════════════════ -->
<!-- MODAL FULL PRATINJAU LEMBAR RESMI CETAK                  -->
<!-- ════════════════════════════════════════════════════════ -->
<div id="modalFullBraPreview" class="bra-modal-overlay">
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
        <button type="button" onclick="window.printLiveDocument()" class="btn-primary-bra py-1.5 px-3 text-xs">
          🖨️ Cetak Dokumen
        </button>
        <button type="button" id="btnCloseModalPreview" class="btn-outline-bra py-1.5 px-2.5 text-xs">
          ✕ Tutup
        </button>
      </div>
    </div>
    <div class="bra-modal-body" id="modalPreviewBody">
      <!-- Cloned or dynamically loaded paper -->
      <div id="modalPaperTarget" class="w-full max-w-4xl"></div>
    </div>
  </div>
</div>


<!-- ════════════════════════════════════════════════════════ -->
<!-- JAVASCRIPT ENGINE & DYNAMIC LOGIC                       -->
<!-- ════════════════════════════════════════════════════════ -->
<script>
function initBraForm() {
  const form = document.getElementById('braMasterForm');
  const sectionsContainer = document.getElementById('sectionsListContainer');
  const btnAddSection = document.getElementById('btnAddSectionBtn');
  const liveWordCountBadge = document.getElementById('liveWordCountBadge');
  const hiddenJumlahKata = document.getElementById('hiddenJumlahKata');
  const opSelect = document.getElementById('operasiSelect');
  const pejabatSelect = document.getElementById('pejabatSelectControl');
  const kategoriRadios = document.querySelectorAll('.kategori-radio');
  const vesselPresets = document.getElementById('vesselPresetsContainer');
  const opRecBar = document.getElementById('operasiRecommendationBar');

  // Preview elements
  const pvPanggilan = document.getElementById('pvPanggilan');
  const pvJenis = document.getElementById('pvJenis');
  const pvNrStn = document.getElementById('pvNrStn');
  const pvInstruksiPenerusan = document.getElementById('pvInstruksiPenerusan');
  const pvDerajat = document.getElementById('pvDerajat');
  const pvDari = document.getElementById('pvDari');
  const pvAksi = document.getElementById('pvAksi');
  const pvInfo = document.getElementById('pvInfo');
  const pvTwu = document.getElementById('pvTwu');
  const pvTwb = document.getElementById('pvTwb');
  const pvJumlahKata = document.getElementById('pvJumlahKata');
  const pvNoBra = document.getElementById('pvNoBra');
  const pvMenbra = document.getElementById('pvMenbra');
  const pvMenbraRow = document.getElementById('pvMenbraRow');
  const pvPerihal = document.getElementById('pvPerihal');
  const pvSections = document.getElementById('pvSectionsContainer');
  const pvPengirim = document.getElementById('pvPengirim');
  const pvNamaSmc = document.getElementById('pvNamaSmc');
  const pvPangkatSmc = document.getElementById('pvPangkatSmc');
  const pvJabatanSmc = document.getElementById('pvJabatanSmc');
  const pvTwt = document.getElementById('pvTwt');
  const pvTwk = document.getElementById('pvTwk');
  const pvSistem = document.getElementById('pvSistem');
  const pvParaf = document.getElementById('pvParaf');

  // Military TW Format Helper
  function getMilitaryTW(dateObj) {
    const d = dateObj || new Date();
    // Compute in WITA (UTC+8)
    const utc = d.getTime() + d.getTimezoneOffset() * 60000;
    const wita = new Date(utc + 8 * 3600000);
    const mm = String(wita.getMonth() + 1).padStart(2, '0');
    const dd = String(wita.getDate()).padStart(2, '0');
    const hh = String(wita.getHours()).padStart(2, '0');
    const min = String(wita.getMinutes()).padStart(2, '0');
    return `${mm}${dd} ${hh}${min} H`;
  }

  // Section letter helper (0 -> AAA, 1 -> BBB, ...)
  function getSectionLetter(idx) {
    const char = String.fromCharCode(65 + (idx % 26));
    return char.repeat(3);
  }

  function getSectionHint(idx, kat) {
    if (idx === 0) return kat === 'PERGERAKAN_KAPAL' ? '(Dasar & Rencana Pelayaran / BKO)' : '(Kronologi / Informasi Awal)';
    if (idx === 1) return kat === 'MAPEL' ? '(Permintaan Mapel / Pemantauan Kapal)' : (kat === 'PERGERAKAN_KAPAL' ? '(Data Kapal, Ren Layar, POB & Palsar)' : '(Unsur SAR Terlibat, Alut, Palsar & Cuaca)');
    if (idx === 2) return kat === 'PERGERAKAN_KAPAL' ? '(Laporan Perkembangan Menyusul / Penutup)' : '(Penutup / Rencana Tindak Lanjut)';
    return '(Bagian Tambahan)';
  }

  // Quick Derajat setter
  window.setDerajat = function(val) {
    document.getElementById('derajatInput').value = val;
    syncLivePreview();
  };

  // Stepper Smooth Scroll & Active Indicator
  const stepperBar = document.getElementById('braStepperBar');
  if (stepperBar) {
    stepperBar.querySelectorAll('.bra-step-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('data-target');
        const targetEl = document.getElementById(targetId);
        if (targetEl) {
          targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
          stepperBar.querySelectorAll('.bra-step-btn').forEach(b => b.classList.remove('active'));
          this.classList.add('active');
        }
      });
    });

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            const id = entry.target.id;
            stepperBar.querySelectorAll('.bra-step-btn').forEach(b => {
              if (b.getAttribute('data-target') === id) b.classList.add('active');
              else b.classList.remove('active');
            });
          }
        });
      }, { threshold: 0.35 });
      document.querySelectorAll('.bra-card[id]').forEach(c => observer.observe(c));
    }
  }

  // Re-index all sections
  function reindexSections() {
    const currentKat = getSelectedKategori();
    const rows = sectionsContainer.querySelectorAll('.section-block');
    rows.forEach((row, idx) => {
      const code = getSectionLetter(idx);
      const badge = row.querySelector('.sec-code-badge');
      const hidden = row.querySelector('.sec-code-input');
      const ta = row.querySelector('.sec-content-textarea');
      const hint = row.querySelector('.sec-hint-label');

      if (badge) badge.textContent = code;
      if (hidden) { hidden.value = code; hidden.name = `sections[${idx}][kode]`; }
      if (ta) { ta.name = `sections[${idx}][isi]`; ta.placeholder = `Isi naskah telegram untuk bagian ${code}...`; }
      if (hint) hint.textContent = getSectionHint(idx, currentKat);

      // Handle remove button (only >= 3)
      let delBtn = row.querySelector('.btn-delete-section');
      if (idx >= 3) {
        if (!delBtn) {
          const header = row.querySelector('.sec-item-header');
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'sec-del-btn btn-delete-section';
          btn.innerHTML = `<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg><span>Hapus</span>`;
          header.appendChild(btn);
        }
      } else {
        if (delBtn) delBtn.remove();
      }
    });
    syncLivePreview();
  }

  // Add section
  if (btnAddSection) {
    btnAddSection.addEventListener('click', function() {
      const rows = sectionsContainer.querySelectorAll('.section-block');
      const nextIdx = rows.length;
      const nextCode = getSectionLetter(nextIdx);

      const div = document.createElement('div');
      div.className = 'sec-item section-block';
      div.setAttribute('data-index', nextIdx);
      div.innerHTML = `
        <div class="sec-item-header">
          <div class="flex items-center gap-2">
            <span class="sec-code-badge">${nextCode}</span>
            <input type="hidden" name="sections[${nextIdx}][kode]" value="${nextCode}" class="sec-code-input">
            <span class="sec-hint-title sec-hint-label">(Bagian Tambahan)</span>
          </div>
          <button type="button" class="sec-del-btn btn-delete-section" title="Hapus bagian ini">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <span>Hapus</span>
          </button>
        </div>
        <textarea name="sections[${nextIdx}][isi]" rows="4" class="bra-control sec-content-textarea upper" placeholder="Isi naskah telegram untuk bagian ${nextCode}..." required></textarea>
      `;
      sectionsContainer.appendChild(div);
      div.querySelector('textarea').focus();
      reindexSections();
    });
  }

  // Remove section
  sectionsContainer.addEventListener('click', function(e) {
    const delBtn = e.target.closest('.btn-delete-section');
    if (delBtn) {
      const rows = sectionsContainer.querySelectorAll('.section-block');
      if (rows.length <= 3) {
        if (typeof showToast === 'function') showToast('Dokumen BRA SAR minimal memerlukan bagian AAA, BBB, dan CCC.', 'warning');
        return;
      }
      delBtn.closest('.section-block').remove();
      reindexSections();
    }
  });

  // Section textarea input -> Live sync & uppercase
  sectionsContainer.addEventListener('input', function(e) {
    if (e.target.classList.contains('sec-content-textarea')) {
      syncLivePreview();
    }
  });

  // Calculate words
  function calculateWordCount() {
    let combined = '';
    sectionsContainer.querySelectorAll('.sec-content-textarea').forEach(ta => {
      combined += ' ' + ta.value.trim();
    });
    const perihal = document.getElementById('perihalInput').value.trim();
    combined += ' ' + perihal;

    const words = combined.split(/\s+/).filter(Boolean);
    const count = words.length;
    const str = `${count} KATA`;

    if (liveWordCountBadge) liveWordCountBadge.textContent = str;
    if (hiddenJumlahKata) hiddenJumlahKata.value = str;
    if (pvJumlahKata) pvJumlahKata.textContent = str;
    return count;
  }

  function getSelectedKategori() {
    const checked = document.querySelector('input[name="kategori"]:checked');
    return checked ? checked.value : 'MAPEL';
  }

  // Live Sync Engine between form and official paper preview
  function syncLivePreview() {
    calculateWordCount();

    if (pvPanggilan) pvPanggilan.textContent = document.getElementById('panggilanInput').value.trim() || '—';
    if (pvJenis) pvJenis.textContent = document.getElementById('jenisInput').value.trim() || 'SAR';
    if (pvNrStn) pvNrStn.textContent = document.getElementById('nrStnInput').value.trim() || '—';
    if (pvInstruksiPenerusan) pvInstruksiPenerusan.textContent = document.getElementById('instruksiPenerusanInput').value.trim() || '—';
    if (pvDerajat) pvDerajat.textContent = document.getElementById('derajatInput').value.trim() || 'SGR';

    if (pvDari) pvDari.textContent = document.getElementById('dariInput').value.trim() || 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
    if (pvAksi) pvAksi.textContent = document.getElementById('aksiInput').value.trim() || '—';
    if (pvInfo) pvInfo.textContent = document.getElementById('infoInput').value.trim() || '—';

    const twuVal = document.getElementById('twuInput').value.trim();
    if (pvTwu) pvTwu.textContent = twuVal.startsWith('TWU') ? twuVal : `TWU ${twuVal || '<?= $twuPlaceholder ?>'}`;

    const twbVal = document.getElementById('twbInput').value.trim();
    if (pvTwb) pvTwb.textContent = twbVal.startsWith('TWB') ? twbVal : `TWB ${twbVal || '<?= $twbPlaceholder ?>'}`;

    if (pvNoBra) pvNoBra.textContent = document.getElementById('noBraInput').value.trim() || '<?= $noBraPlaceholder ?>';

    const menbraVal = document.getElementById('menbraInput').value.trim();
    if (pvMenbra) pvMenbra.textContent = menbraVal;
    if (pvMenbraRow) {
      if (menbraVal) pvMenbraRow.classList.remove('hidden');
      else pvMenbraRow.classList.add('hidden');
    }

    if (pvPerihal) pvPerihal.textContent = document.getElementById('perihalInput').value.trim() || 'BERITA RADIO SAR TTK';

    // Sections
    if (pvSections) {
      pvSections.innerHTML = '';
      sectionsContainer.querySelectorAll('.section-block').forEach(row => {
        const code = row.querySelector('.sec-code-badge').textContent.trim();
        const text = row.querySelector('.sec-content-textarea').value.trim();
        if (text) {
          const div = document.createElement('div');
          div.className = 'flex items-start text-justify leading-relaxed text-[11px]';
          div.innerHTML = `
            <span class="w-14 font-black tracking-wider uppercase shrink-0 text-black">${code}</span>
            <span class="w-3 text-center shrink-0 text-black font-black">:</span>
            <div class="flex-1 whitespace-pre-line uppercase font-normal leading-relaxed ml-1 text-black">${escapeHtml(text)}</div>
          `;
          pvSections.appendChild(div);
        }
      });
    }

    if (pvPengirim) pvPengirim.textContent = document.getElementById('pengirimInput').value.trim() || 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
    if (pvNamaSmc) pvNamaSmc.textContent = document.getElementById('namaSmcInput').value.trim() || 'AMIRUDDIN AS, S.Sos.';
    if (pvPangkatSmc) pvPangkatSmc.textContent = document.getElementById('pangkatSmcInput').value.trim() || 'PEMBINA (IV/a)';
    if (pvJabatanSmc) pvJabatanSmc.textContent = document.getElementById('jabatanSmcInput').value.trim() || 'SMC';

    if (pvTwt) pvTwt.textContent = document.getElementById('twtInput').value.trim() || '—';
    if (pvTwk) pvTwk.textContent = document.getElementById('twkInput').value.trim() || '—';
    if (pvSistem) pvSistem.textContent = document.getElementById('sistemInput').value.trim() || '—';
    if (pvParaf) pvParaf.textContent = document.getElementById('parafInput').value.trim() || '—';
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  // Bind live sync to all form inputs
  const liveSyncInputs = form.querySelectorAll('input, textarea, select');
  liveSyncInputs.forEach(el => {
    el.addEventListener('input', syncLivePreview);
    el.addEventListener('change', syncLivePreview);
  });

  // Buttons for Waktu Sekarang
  document.getElementById('btnSetTwbNow').addEventListener('click', function() {
    document.getElementById('twbInput').value = getMilitaryTW(new Date(Date.now() - 15 * 60000));
    syncLivePreview();
  });
  document.getElementById('btnSetTwuNow').addEventListener('click', function() {
    document.getElementById('twuInput').value = getMilitaryTW(new Date());
    syncLivePreview();
  });

  // Format Sandi Telegram (Kapital & TTK/KMA/TTK DUA)
  document.getElementById('btnFormatTelegramPunctuation').addEventListener('click', function() {
    form.querySelectorAll('textarea, input[type="text"]').forEach(el => {
      if (['noBraInput','menbraInput','twuInput','twbInput','twtInput','twkInput','sistemInput','parafInput','panggilanInput','nrStnInput'].includes(el.id)) {
        el.value = el.value.toUpperCase();
        return;
      }
      let val = el.value.toUpperCase();
      val = val.replace(/\.\s+/g, ' TTK ').replace(/\,\s+/g, ' KMA ').replace(/\:\s+/g, ' TTK DUA ');
      el.value = val;
    });
    syncLivePreview();
    if (typeof showToast === 'function') showToast('Naskah berhasil diformat ke Huruf Kapital Baku Telegram!', 'success');
  });

  // Category switch handler
  kategoriRadios.forEach(radio => {
    radio.addEventListener('change', function() {
      const kat = this.value;
      const menbraTitle = document.getElementById('labelMenbraTitle');
      const menbraInput = document.getElementById('menbraInput');
      const hintMenbra = document.getElementById('hintMenbraText');

      if (kat === 'PERGERAKAN_KAPAL') {
        vesselPresets.classList.remove('hidden');
        if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan Lalu)';
        if (menbraInput) menbraInput.placeholder = 'Rujukan naskah pergerakan kapal sebelumnya';
      } else if (kat === 'MAPEL') {
        vesselPresets.classList.add('hidden');
        if (menbraTitle) menbraTitle.textContent = 'SIFAT BERITA';
        if (menbraInput) {
          menbraInput.placeholder = 'SEGERA';
          if (!menbraInput.value) menbraInput.value = 'SEGERA';
        }
      } else {
        vesselPresets.classList.add('hidden');
        if (menbraTitle) menbraTitle.textContent = 'MENBRA (Rujukan BRA Lalu)';
        if (menbraInput) menbraInput.placeholder = '<?= $nomorUrutPlaceholder - 1 ?>/<?= $satkerKode ?>/<?= $bulanTahunPlaceholder ?>';
      }

      reindexSections();
    });
  });

  // Operasi Select handler
  opSelect.addEventListener('change', function() {
    const opId = this.value;
    if (opId) {
      opRecBar.classList.remove('hidden');
      document.getElementById('jabatanSmcInput').value = 'SMC';
    } else {
      opRecBar.classList.add('hidden');
      document.getElementById('jabatanSmcInput').value = 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI';
    }
    syncLivePreview();
  });

  // Pejabat Select Auto-fill
  if (pejabatSelect) {
    pejabatSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      if (opt && opt.value) {
        document.getElementById('namaSmcInput').value = opt.getAttribute('data-nama') || '';
        document.getElementById('pangkatSmcInput').value = opt.getAttribute('data-pangkat') || '';
        const isOps = opSelect && opSelect.value;
        document.getElementById('jabatanSmcInput').value = isOps ? 'SMC' : (opt.getAttribute('data-jabatan') || 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI');
        syncLivePreview();
      }
    });
  }

  // API Load Operasi SAR data
  async function loadOperasiData(opId) {
    if (!opId) return null;
    try {
      const res = await fetch(`<?= BASE_URL ?>/api/operasi/${opId}`);
      const json = await res.json();
      return json.success ? json.data : null;
    } catch (e) {
      console.warn('Gagal memuat detail operasi:', e);
      return null;
    }
  }

  function setSectionsContent(sectionsArr) {
    sectionsContainer.innerHTML = '';
    sectionsArr.forEach((s, idx) => {
      const div = document.createElement('div');
      div.className = 'sec-item section-block';
      div.setAttribute('data-index', idx);
      div.innerHTML = `
        <div class="sec-item-header">
          <div class="flex items-center gap-2">
            <span class="sec-code-badge">${s.kode}</span>
            <input type="hidden" name="sections[${idx}][kode]" value="${s.kode}" class="sec-code-input">
            <span class="sec-hint-title sec-hint-label">${getSectionHint(idx, getSelectedKategori())}</span>
          </div>
          ${idx >= 3 ? `<button type="button" class="sec-del-btn btn-delete-section" title="Hapus bagian ini"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg><span>Hapus</span></button>` : ''}
        </div>
        <textarea name="sections[${idx}][isi]" rows="4" class="bra-control sec-content-textarea upper" placeholder="Isi naskah telegram untuk bagian ${s.kode}..." required>${s.isi}</textarea>
      `;
      sectionsContainer.appendChild(div);
    });
    reindexSections();
  }

  // 1. Rekomendasi: Laporan Awal (Inisiasi)
  document.getElementById('btnAutoInisiasi').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) {
      if (typeof showToast === 'function') showToast('Pilih Operasi SAR pada dropdown terlebih dahulu.', 'warning');
      return;
    }
    const op = await loadOperasiData(opId);
    if (!op) return;

    const twb = document.getElementById('twbInput').value || getMilitaryTW(new Date(Date.now() - 15 * 60000));
    const twu = document.getElementById('twuInput').value || getMilitaryTW(new Date());

    const pelapor = (op.nama_pelapor || 'BAPAK POLSEK').toUpperCase();
    const hubungan = (op.hubungan_pelapor || 'MASYARAKAT').toUpperCase();
    const noHp = op.no_telp_pelapor ? `HP ${op.no_telp_pelapor}` : 'HP. -';
    const infoPelapor = `${pelapor} (${hubungan} / ${noHp})`;
    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();
    const lokasi = (op.lokasi_kejadian || 'PERAIRAN SULTRA').toUpperCase();
    const koordinat = (op.koordinat || '03° 58\' 30" S - 122° 35\' 12" E').toUpperCase();
    const timBerangkat = (op.tim_berangkat || 'TIM RESCUE KANSAR KENDARI').toUpperCase();

    document.getElementById('perihalInput').value = `LAPORAN AWAL OPERASI SAR TERHADAP ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN`;
    document.getElementById('derajatInput').value = 'SGR';

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${twb} TERIMA INFO DARI ${infoPelapor} BAHWA TELAH TERJADI ${namaOps} DI ${lokasi} PADA KOORDINAT ${koordinat} TTK` },
      { kode: 'BBB', isi: `TERSEBUT AAA DIATAS MAKA PADA TW ${twu} ${timBerangkat} DIBERANGKATKAN MENUJU LKP DENGAN MENGGUNAKAN ${(op.alut_palsar || '1 UNIT RESCUE CAR DAN 1 UNIT RUBBER BOAT').toUpperCase().replace(/\n/g, ' KMA ')} DENGAN ESTIMASI WAKTU TEMPUH ${op.eta_lokasi || 'SEGERA'} TTK` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT TTK DUA\n${(op.unsur_terlibat || '* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}\n\nALUT DAN PALSAR TTK DUA\n${(op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}\n\nKONDISI CUACA TTK DUA\n• CUACA : ${(op.cuaca || 'CERAH BERAWAN').toUpperCase()}\n• ANGIN : ${(op.angin || 'TIMUR LAUT - TENGGARA, 5-15 KNOTS').toUpperCase()}\n• GELOMBANG : ${(op.gelombang || '0.5 - 1.25 METER').toUpperCase()}` },
      { kode: 'DDD', isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast('Rekomendasi Naskah Laporan Awal berhasil dimuat!', 'success');
  });

  // 2. Rekomendasi: Renops Terakhir
  document.getElementById('btnAutoRenops').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) return;
    const op = await loadOperasiData(opId);
    if (!op || !op.renops || op.renops.length === 0) {
      if (typeof showToast === 'function') showToast('Belum ada data Rencana Operasi (Renops) pada operasi ini.', 'warning');
      return;
    }
    const r = op.renops[op.renops.length - 1];
    const hariKe = (r.hari_ke || 'H.1').toUpperCase();
    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();

    document.getElementById('perihalInput').value = `RENCANA OPERASI SAR ${hariKe} ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN`;

    let sruText = '';
    if (r.srus && Array.isArray(r.srus) && r.srus.length > 0) {
      sruText = r.srus.map(s => `* ${s.nama_sru.toUpperCase()} DENGAN POLA ${s.pola_pencarian.toUpperCase()} LUAS ${s.luas_area.toUpperCase()} AREA: ${s.koordinat_area.replace(/\n/g, ' / ')}`).join('\n');
    } else {
      sruText = `* SRU 1: PENYISIRAN PARALLEL TRACK SEARCH DISEPUTARAN LKP\n* SRU 2: PENYISIRAN PESISIR PANTAI`;
    }

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${r.waktu_briefing || document.getElementById('twbInput').value} TIM SAR GABUNGAN MELAKSANAKAN BRIEFING PEMBAGIAN SEKTOR PENCARIAN RENCANA OPERASI SAR ${hariKe} TERHADAP ${namaOps} DENGAN WAKTU MULAI PERGERAKAN TW ${r.waktu_mulai_ops || document.getElementById('twuInput').value} TTK` },
      { kode: 'BBB', isi: `RENCANA PEMBAGIAN SRU TTK DUA\n${sruText}\n\nKONDISI METEOROLOGI / CUACA TTK DUA\n• CUACA : ${(r.cuaca || op.cuaca || 'CERAH BERAWAN').toUpperCase()}` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT TTK DUA\n${(r.unsur_text || op.unsur_terlibat || '* RESCUER KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}\n\nALUT DAN PALSAR TTK DUA\n${(r.alut_text || op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}` },
      { kode: 'DDD', isi: `DMKN SBG RENCANA OPERASI ${hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast(`Rekomendasi Rencana Operasi ${hariKe} berhasil dimuat!`, 'success');
  });

  // 3. Rekomendasi: SITREP (Lapbang)
  document.getElementById('btnAutoSitrep').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) return;
    const op = await loadOperasiData(opId);
    if (!op || !op.laporan_harian || op.laporan_harian.length === 0) {
      if (typeof showToast === 'function') showToast('Belum ada data Laporan Perkembangan (SITREP) pada operasi ini.', 'warning');
      return;
    }
    const sit = op.laporan_harian[op.laporan_harian.length - 1];
    const hariKe = (sit.hari_ke || 'H.1').toUpperCase();
    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();

    document.getElementById('perihalInput').value = `LAPORAN PERKEMBANGAN ${hariKe} OPERASI SAR ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN`;

    // Compute victim stats
    const victims = op.korban || [];
    let sCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'S').length;
    let mdCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'MD').length;
    let dpCount = victims.filter(k => !['S', 'MD'].includes((k.status_korban || '').toUpperCase())).length;

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${sit.waktu_laporan || document.getElementById('twuInput').value} TERIMA INFO DARI OSC TIM SAR GABUNGAN BAHWA ${(sit.isi_laporan || 'PENCARIAN TELAH DILAKSANAKAN SESUAI RENOPS DENGAN HASIL KORBAN BELUM DITEMUKAN (NIHIL)').toUpperCase()} TTK` },
      { kode: 'BBB', isi: `ALUT DAN PALSAR DIKERAHKAN TTK DUA\n${(sit.alut_text || op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT TTK DUA\n${(sit.unsur_text || op.unsur_terlibat || '* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}` },
      { kode: 'DDD', isi: `DATA REKAPITULASI KORBAN TTK DUA\n* KORBAN SELAMAT : ${sCount} ORANG\n* KORBAN MENINGGAL DUNIA : ${mdCount} ORANG\n* KORBAN DALAM PENCARIAN (DP) : ${dpCount} ORANG\n* TOTAL POB : ${op.jumlah_pob || (sCount + mdCount + dpCount)} ORANG` },
      { kode: 'EEE', isi: `KONDISI CUACA TTK DUA\n• CUACA : ${(sit.cuaca || op.cuaca || 'CERAH BERAWAN').toUpperCase()}\n• GELOMBANG : ${(op.gelombang || '0.5 - 1.25 METER').toUpperCase()}\n\nPADA TW ${document.getElementById('twuInput').value} PENCARIAN DIHENTIKAN SEMENTARA DENGAN HASIL NIHIL KMA OPERASI AKAN DILANJUTKAN KEMBALI BESOK PAGI PADA TW 0700 H TTK` },
      { kode: 'FFF', isi: `DMKN SBG LAPORAN PERKEMBANGAN ${hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast(`Rekomendasi Lapbang ${hariKe} berhasil dimuat!`, 'success');
  });

  // 4. Rekomendasi: Penutupan Operasi
  document.getElementById('btnAutoPenutupan').addEventListener('click', async function() {
    const opId = opSelect.value;
    if (!opId) return;
    const op = await loadOperasiData(opId);
    if (!op) return;

    const namaOps = (op.nama_operasi || 'OPERASI SAR').toUpperCase();
    document.getElementById('perihalInput').value = `LAPORAN PERKEMBANGAN DAN PENUTUPAN OPERASI SAR TERHADAP ${namaOps} TTK`;
    document.getElementById('aksiInput').value = `KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN`;
    document.getElementById('infoInput').value = `DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN KMA GUBERNUR SULTRA`;

    const victims = op.korban || [];
    let sCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'S').length;
    let mdCount = victims.filter(k => (k.status_korban || '').toUpperCase() === 'MD').length;
    let dpCount = victims.filter(k => !['S', 'MD'].includes((k.status_korban || '').toUpperCase())).length;

    const latestSit = (op.laporan_harian && op.laporan_harian.length > 0) ? op.laporan_harian[op.laporan_harian.length - 1] : null;

    setSectionsContent([
      { kode: 'AAA', isi: `PADA TW ${latestSit?.waktu_debrief || document.getElementById('twuInput').value} TIM SAR GABUNGAN MELAKSANAKAN DEBRIEFING DAN EVALUASI PENUTUPAN OPERASI SAR TERHADAP ${namaOps} KMA HASIL AKHIR ${(latestSit?.isi_laporan || 'SELURUH KORBAN TELAH BERHASIL DIEVAKUASI DAN OPERASI SAR DINYATAKAN SELESAI').toUpperCase()} TTK` },
      { kode: 'BBB', isi: `ALUT DAN PALSAR DIKEMBALIKAN KE PANGKALAN TTK DUA\n${(latestSit?.alut_text || op.alut_palsar || '• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT').toUpperCase()}` },
      { kode: 'CCC', isi: `UNSUR SAR TERLIBAT DIKEMBALIKAN KE KESATUAN MASING-MASING TTK DUA\n${(latestSit?.unsur_text || op.unsur_terlibat || '* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG').toUpperCase()}` },
      { kode: 'DDD', isi: `DATA REKAPITULASI KORBAN AKHIR TTK DUA\n* KORBAN SELAMAT : ${sCount} ORANG\n* KORBAN MENINGGAL DUNIA : ${mdCount} ORANG\n* KORBAN DALAM PENCARIAN (DP) : ${dpCount} ORANG\n* TOTAL POB : ${op.jumlah_pob || (sCount + mdCount + dpCount)} ORANG` },
      { kode: 'EEE', isi: `DENGAN DITEMUKANNYA SELURUH KORBAN MAKA OPERASI SAR DINYATAKAN DITUTUP KMA UCAPAN TERIMA KASIH ATAS KERJASAMANYA KEPADA SELURUH UNSUR SAR TERLIBAT TTK` },
      { kode: 'FFF', isi: `DMKN SBG LAPORAN PENUTUPAN OPERASI SAR KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast('Rekomendasi Naskah Penutupan Operasi SAR berhasil dimuat!', 'success');
  });

  // 5. Preset Kapal: KN SAR Pacitan 102
  document.getElementById('btnPresetPacitan').addEventListener('click', function() {
    document.querySelector('input[name="kategori"][value="PERGERAKAN_KAPAL"]').checked = true;
    document.getElementById('perihalInput').value = 'PELAYARAN KN SAR PACITAN 102 KENDARI DALAM RANGKA BKO OPRASI SAR KAPAL TENGGELAM KM VIRGO TRANSPORT 8 DISEPUTARAN LAUT JAWA - BANJARMASIN TTK';
    document.getElementById('aksiInput').value = 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';
    document.getElementById('infoInput').value = 'DEPUTI OPS DAN KESIAPSIAGAAN KMA DEPUTI SARPRAS DAN SISTEM KOMUNIKASI KMA DIREKTUR SARPRAS DAN DIREKTUR KESIAPSIAGAAN';
    document.getElementById('derajatInput').value = 'SGR';

    const twbVal = document.getElementById('twbInput').value || '<?= $twbPlaceholder ?>';

    setSectionsContent([
      { kode: 'AAA', isi: `PD TW ${twbVal} KMA PELAYARAN KN SAR PACITAN 102 KENDARI DALAM RANGKA BKO OPERASI SAR KAPAL TENGGELAM KM VIRGO TRANSPORT 8 DISEPUTARAN LAUT JAWA - BANJARMASIN TTK` },
      { kode: 'BBB', isi: `DATA KAPAL TTK DUA\nNAMA : KN.SAR PACITAN 102\nNAKHODA : RUSLAN, S.T\nLOA : 59,80 M\nTYPE : ALUMINIUM\nTAHUN : 2013\nENDURANCE : 47 JAM / 69,84%\nSPEED MAX : 20 KNOT\nSPEED ECO : 15 KNOT\n\nREN LAYAR TTK DUA\nRUTE : DERMAGA UTAMA BASARNAS KAWASAN TIMUR KMA KENDARI MENUJU PERAIRAN LAUT JAWA - BANJARMASIN\nHEADING : 266°\nJARAK TEMPUH : 620 NM\nESTIMASI PERGERAKAN : 45 JAM\n\nMUATAN POB TTK DUA\nCREW KN SAR PACITAN : 20 ORANG\nRESCUER : 13 ORANG (PENYELAM 7 ORG KMA RESCUE 4 ORG KMA PILOT DRONE 1 ORG KMA OSC 1 ORG)\nTOTAL POB : 40 ORANG\n\nDATA PALSAR ALKOM ALNAV TTK DUA\nSEA RIDER 1 UNIT (40 PK) READY KMA SEKOCI 2 UNIT READY KMA ALAT SELAM 4 SET READY KMA RADAR AIS ECDIS READY TTK` },
      { kode: 'CCC', isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA NAKHODA KN SAR PACITAN 102 KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast('Preset Resmi KN SAR Pacitan 102 berhasil dimuat!', 'success');
  });

  // 6. Preset Kapal: Rescue Boat 210
  document.getElementById('btnPresetRB210').addEventListener('click', function() {
    document.querySelector('input[name="kategori"][value="PERGERAKAN_KAPAL"]').checked = true;
    document.getElementById('perihalInput').value = 'RENCANA PELAYARAN RESCUE BOAT 210 KENDARI DALAM RANGKA PEMANASAN BERGERAK DAN PROFICIENCY ABK TTK';
    document.getElementById('aksiInput').value = 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN';
    document.getElementById('infoInput').value = 'DEPUTI OPS DAN KESIAPSIAGAAN KMA DIREKTUR KESIAPSIAGAAN KMA DIREKTUR SARPRAS';
    document.getElementById('derajatInput').value = 'RUTIN';

    const twbVal = document.getElementById('twbInput').value || '<?= $twbPlaceholder ?>';

    setSectionsContent([
      { kode: 'AAA', isi: `PD TW ${twbVal} KMA RENCANA PELAYARAN RESCUE BOAT 210 KENDARI DALAM RANGKA PEMANASAN BERGERAK DAN PROFICIENCY ABK DI SEKITAR PERAIRAN TELUK KENDARI TTK` },
      { kode: 'BBB', isi: `DATA KAPAL TTK DUA\nNAMA : RB 210 KENDARI\nNAKHODA : JUMARDIN, S.E.\nLOA : 36 METER\nSPEED MAX : 26 KNOT\nBBM : 14.500 LITER (READY)\nAIR TAWAR : 6.000 LITER (READY)\n\nREN LAYAR TTK DUA\nRUTE : DERMAGA UTAMA BASARNAS KENDARI MENUJU PERAIRAN TELUK KENDARI\nESTIMASI : 4 JAM\nPOB : 16 ORANG (CREW 12 KMA RESCUER 4)\n\nPALSAR ALKOM TTK DUA\nRUBBER BOAT 1 UNIT (25 PK) KMA LIFE JACKET 20 BUAH KMA ALAT SELAM 2 SET KMA VHF MARINE RADAR GPS AIS READY TTK` },
      { kode: 'CCC', isi: `LAPORAN PERKEMBANGAN MENYUSUL KMA NAKHODA RB 210 KRM TTK HBS` }
    ]);
    if (typeof showToast === 'function') showToast('Preset Resmi Rescue Boat 210 berhasil dimuat!', 'success');
  });

  // QR Code Rendering for Live Preview & Modal Preview
  const verifyUrl = '<?= BASE_URL ?>/bra.php?view=<?= $id ?: 9999 ?>';
  const logoDataUri = <?= json_encode($logoDataUri) ?>;

  function renderBraQrCode(targetCanvas, retries = 0) {
    if (!targetCanvas) return;
    if (typeof QRCode === 'undefined') {
      if (retries < 25) {
        setTimeout(function() { renderBraQrCode(targetCanvas, retries + 1); }, 80);
      }
      return;
    }
    QRCode.toCanvas(targetCanvas, verifyUrl, {
      width: 160,
      margin: 1,
      color: { dark: '#0a0f1d', light: '#ffffff' },
      errorCorrectionLevel: 'H'
    }, function(err) {
      if (err) {
        console.error('QR code error:', err);
        return;
      }
      targetCanvas.style.width = '66px';
      targetCanvas.style.height = '66px';
      targetCanvas.style.maxWidth = '66px';
      targetCanvas.style.maxHeight = '66px';
      const ctx = targetCanvas.getContext('2d');
      const size = targetCanvas.width;
      const logoImg = new Image();

      function drawBadgeAndLogo() {
        const logoSize = Math.floor(size * 0.22);
        const center = (size - logoSize) / 2;
        // White circular background badge
        ctx.fillStyle = '#ffffff';
        ctx.beginPath();
        ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2) + 4, 0, Math.PI * 2);
        ctx.fill();
        ctx.strokeStyle = '#cbd5e1';
        ctx.lineWidth = 1.5;
        ctx.stroke();

        if (logoImg.naturalWidth > 0) {
          ctx.save();
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2), 0, Math.PI * 2);
          ctx.clip();
          ctx.drawImage(logoImg, center, center, logoSize, logoSize);
          ctx.restore();
        } else {
          // Emergency fallback vector badge with 'SAR' text
          ctx.fillStyle = '#ea580c';
          ctx.beginPath();
          ctx.arc(size / 2, size / 2, Math.floor(logoSize / 2), 0, Math.PI * 2);
          ctx.fill();
          ctx.fillStyle = '#ffffff';
          ctx.font = 'bold ' + Math.floor(logoSize * 0.44) + 'px sans-serif';
          ctx.textAlign = 'center';
          ctx.textBaseline = 'middle';
          ctx.fillText('SAR', size / 2, size / 2 + 1);
        }
      }

      logoImg.onload = drawBadgeAndLogo;
      logoImg.onerror = function() {
        drawBadgeAndLogo();
      };
      logoImg.src = logoDataUri;
      if (logoImg.complete && logoImg.naturalWidth > 0) {
        drawBadgeAndLogo();
      }
    });
  }

  function updateLiveQrCode() {
    const liveCanvas = document.getElementById('liveBraQrCanvas');
    if (liveCanvas) {
      renderBraQrCode(liveCanvas);
    }
  }

  // Toggle Split View Layout (Desktop)
  const appWrap = document.getElementById('braMainAppWrap');
  const btnToggleSplit = document.getElementById('btnToggleSplitView');
  const splitLabel = document.getElementById('splitLabel');
  let isSplitActive = true;

  if (btnToggleSplit && appWrap) {
    appWrap.classList.add('bra-split-active');
    btnToggleSplit.addEventListener('click', function() {
      isSplitActive = !isSplitActive;
      if (isSplitActive) {
        appWrap.classList.add('bra-split-active');
        splitLabel.textContent = 'Split Pratinjau';
      } else {
        appWrap.classList.remove('bra-split-active');
        splitLabel.textContent = 'Buka Pratinjau';
      }
    });
  }

  // Modal Full Preview
  const modalPreview = document.getElementById('modalFullBraPreview');
  const btnOpenModal = document.getElementById('btnOpenModalPreview');
  const btnOpenModalBottom = document.getElementById('btnOpenModalPreviewBottom');
  const btnCloseModal = document.getElementById('btnCloseModalPreview');
  const modalTarget = document.getElementById('modalPaperTarget');

  function openModalPaperPreview() {
    syncLivePreview();
    const livePaper = document.getElementById('braLivePaperPreview');
    if (livePaper && modalTarget) {
      modalTarget.innerHTML = livePaper.outerHTML;
      // Re-render QR code onto the newly cloned canvas in modal
      const modalCanvas = modalTarget.querySelector('canvas');
      if (modalCanvas) {
        modalCanvas.id = 'modalBraQrCanvas';
        modalCanvas.style.width = '72px';
        modalCanvas.style.height = '72px';
        modalCanvas.style.maxWidth = '72px';
        modalCanvas.style.maxHeight = '72px';
        renderBraQrCode(modalCanvas);
      }
    }
    modalPreview.classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function closeModalPaperPreview() {
    modalPreview.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (btnOpenModal) btnOpenModal.addEventListener('click', openModalPaperPreview);
  if (btnOpenModalBottom) btnOpenModalBottom.addEventListener('click', openModalPaperPreview);
  if (btnCloseModal) btnCloseModal.addEventListener('click', closeModalPaperPreview);
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && modalPreview.classList.contains('open')) closeModalPaperPreview();
  });

  // Print helper
  window.printLiveDocument = function() {
    window.print();
  };

  // Form submission validation
  form.addEventListener('submit', function(e) {
    calculateWordCount();
    const noBra = document.getElementById('noBraInput').value.trim();
    if (!noBra) {
      e.preventDefault();
      if (typeof showToast === 'function') showToast('Nomor Dokumen BRA wajib diisi.', 'error');
      document.getElementById('noBraInput').focus();
      return;
    }
    const emptySec = Array.from(sectionsContainer.querySelectorAll('.sec-content-textarea')).some(ta => !ta.value.trim());
    if (emptySec) {
      e.preventDefault();
      if (typeof showToast === 'function') showToast('Semua bagian telegram wajib diisi teksnya.', 'warning');
      return;
    }
  });

  // Initial run
  reindexSections();
  syncLivePreview();
  updateLiveQrCode();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initBraForm);
} else {
  initBraForm();
}
window.addEventListener('load', function() {
  updateLiveQrCode();
});
</script>
