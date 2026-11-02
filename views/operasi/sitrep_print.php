<?php
/**
 * QR-SAR - Lembar Dokumen Resmi Laporan Perkembangan (LAPBANG)
 * KPP Kendari - Basarnas
 * Standar Naskah Dinas Resmi Basarnas Republik Indonesia
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once dirname(__DIR__, 2) . '/config/app.php';
}

$idLaporan = (int)($_GET['id_laporan'] ?? 0);
$idOps = (int)($_GET['id'] ?? 0);

$laporan = null;
if ($idLaporan > 0) {
    $laporan = LaporanModel::getById($idLaporan);
    if ($laporan) {
        $idOps = (int)$laporan['id_operasi'];
    }
}

$operasi = $idOps > 0 ? OperasiModel::getById($idOps) : null;

// Jika laporan belum ditentukan tapi operasi ada, ambil laporan terbaru
if (!$laporan && $operasi) {
    $listLap = LaporanModel::getByOperasiId($idOps);
    if (!empty($listLap)) {
        $laporan = end($listLap);
        $idLaporan = (int)$laporan['id_laporan'];
    }
}

if (!$operasi || !$laporan) {
    if (AuthHelper::isLoggedIn()) {
        setFlash('error', 'Dokumen Laporan Perkembangan (LAPBANG) tidak ditemukan.');
        header("Location: " . BASE_URL . "/operasi.php");
        exit;
    }
    http_response_code(404);
    exit('Dokumen Laporan Perkembangan (LAPBANG) tidak ditemukan.');
}

$id = (int)$operasi['id_operasi'];
$settings = [];
try {
    if (class_exists('SettingsModel')) {
        $settings = SettingsModel::get();
    }
} catch (Throwable) {
    $settings = [];
}
$satkerKode = $settings['satker_kode'] ?? 'SAR-304';
$satkerNama = $settings['satker_nama'] ?? 'Kantor Pencarian dan Pertolongan Kelas A Kendari';

$customLogo = '';
try {
    if (function_exists('getAppLogoUrl')) {
        $customLogo = getAppLogoUrl();
    }
} catch (Throwable) {
    $customLogo = '';
}

$defaultSmc = null;
try {
    if (class_exists('MasterModel')) {
        $defaultSmc = MasterModel::getDefaultSmc();
    }
} catch (Throwable) {
    $defaultSmc = null;
}

$nomorSitrep = TemplateHelper::generateNomorSitrep($operasi, $laporan, $satkerKode);
$hariKe = strtoupper($laporan['hari_ke'] ?? 'H.1');

$waktuDebrief = $laporan['waktu_debrief'] ?: ($laporan['waktu_laporan'] ?: date('Y-m-d H:i:s'));
$tglDebrief = TemplateHelper::formatTanggalIndo($waktuDebrief);
$jamDebrief = TemplateHelper::formatJamWITA($waktuDebrief);

$tglKejadian = TemplateHelper::formatTanggalIndo($operasi['waktu_kejadian'] ?? '');
$jamKejadian = TemplateHelper::formatJamWITA($operasi['waktu_kejadian'] ?? '');

$statsKorban = TemplateHelper::hitungStatistikKorban($operasi['korban'] ?? [], (int)($operasi['jumlah_pob'] ?? 0));
$unsurList = !empty($laporan['detail_unsur']) ? $laporan['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
$alutList = !empty($laporan['detail_alut']) ? $laporan['detail_alut'] : ($operasi['detail_alut'] ?? []);
$korbanList = $operasi['korban'] ?? [];

$verifyUrl = DocVerifyHelper::generateVerifyUrl('sitrep', $operasi, $laporan);
$logoDataUri = getAppLogoDataUri();
$fullWaText = $laporan['full_text_wa'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LAPBANG <?= e($hariKe) ?> - <?= e($operasi['nama_operasi']) ?> - BASARNAS</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo_basarnas.svg">
    <link rel="stylesheet" href="<?= assetUrl('assets/css/tailwind.css') ?>">
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 15mm 15mm;
        }
        @page :first {
            margin-top: 10mm;
        }
        @media print {
            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: 'Times New Roman', Times, serif !important;
                font-size: 12pt !important;
                line-height: 1.4 !important;
            }
            .no-print, .no-print * {
                display: none !important;
            }
            .page-sheet {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 0 25mm 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                font-family: 'Times New Roman', Times, serif !important;
                font-size: 12pt !important;
                line-height: 1.4 !important;
            }
            .break-inside-avoid, .signature-block {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }
            a {
                text-decoration: none !important;
                color: inherit !important;
            }
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            background-color: #0f172a;
            color: #000000;
        }
        .page-sheet {
            background-color: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 12mm 16mm 25mm 16mm;
            box-sizing: border-box;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            transition: transform 0.2s ease-in-out;
            overflow-x: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.4;
        }
        .page-sheet p, .page-sheet li, .page-sheet .narrative-text {
            text-align: justify !important;
            text-justify: inter-word !important;
            line-height: 1.4 !important;
            font-size: 10.5pt;
        }
        .page-sheet table:not(.kop-table):not(.kop-contact-row):not(.kop-phone-table):not(.kop-contact-table) {
            font-family: 'Times New Roman', Times, serif !important;
            width: 100% !important;
            max-width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
        }
        .page-sheet .kop-table {
            width: 100% !important;
            border-collapse: collapse !important;
            table-layout: auto !important;
            border: none !important;
            font-family: Arial, Helvetica, sans-serif !important;
        }
        .page-sheet .kop-table td, .page-sheet .kop-table th {
            border: none !important;
            font-family: Arial, Helvetica, sans-serif !important;
        }
        .page-sheet .kop-contact-row {
            width: 100% !important;
            max-width: 100% !important;
            table-layout: auto !important;
            border-collapse: collapse !important;
            border: none !important;
            margin: 0 !important;
        }
        .page-sheet .kop-contact-row td {
            border: none !important;
            font-family: Arial, Helvetica, sans-serif !important;
        }
        .page-sheet .kop-contact-table, .page-sheet .kop-phone-table {
            width: auto !important;
            max-width: none !important;
            table-layout: auto !important;
            border-collapse: collapse !important;
            border: none !important;
            margin-left: auto !important;
            margin-right: 0 !important;
        }
        .page-sheet .kop-contact-table td, .page-sheet .kop-phone-table td {
            border: none !important;
            padding: 0.5px 3px !important;
            font-size: 8pt !important;
            line-height: 1.35 !important;
            font-family: Arial, Helvetica, sans-serif !important;
            white-space: nowrap !important;
        }
        .kop-surat-container, .kop-surat-container * {
            font-family: Arial, Helvetica, sans-serif !important;
        }
        .page-sheet th:not(.kop-table th):not(.kop-contact-row th):not(.kop-phone-table th),
        .page-sheet td:not(.kop-table td):not(.kop-contact-row td):not(.kop-phone-table td):not(.kop-contact-table td) {
            font-family: 'Times New Roman', Times, serif !important;
        }
        .table-dinas {
            font-size: 8.5pt !important;
        }
        .table-dinas th {
            border: 1px solid #000000 !important;
            background-color: #f1f5f9 !important;
            font-weight: bold !important;
            font-size: 8.5pt !important;
            padding: 4px 5px !important;
            text-align: center !important;
            vertical-align: middle !important;
            text-transform: uppercase !important;
        }
        .table-dinas td {
            border: 1px solid #000000 !important;
            font-size: 8.5pt !important;
            padding: 4px 5px !important;
            vertical-align: top !important;
            line-height: 1.25 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
        }
        .table-borderless td {
            font-size: 9.5pt !important;
            padding: 2.5px 4px !important;
            vertical-align: top !important;
            line-height: 1.35 !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
        }
        .page-sheet *, .page-sheet *::before, .page-sheet *::after {
            box-sizing: border-box !important;
            max-width: 100%;
        }
        .text-justify {
            text-align: justify !important;
            text-justify: inter-word;
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col justify-between">

    <!-- FLOATING ACTION TOOLBAR (Hidden when printing) -->
    <header class="no-print sticky top-0 z-50 bg-slate-900/95 backdrop-blur border-b border-slate-800 text-white px-4 py-3 shadow-md">
        <div class="max-w-5xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $id ?>&tab=sitrep" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    <span>Kembali ke Detail</span>
                </a>
                <span class="text-xs text-slate-500 hidden sm:inline">|</span>
                <span class="text-xs font-mono font-bold text-orange-400">OPS-#<?= $id ?></span>
                <span class="text-xs text-slate-300 font-semibold truncate max-w-[200px] sm:max-w-xs"><?= e($operasi['nama_operasi']) ?> (LAPBANG <?= e($hariKe) ?>)</span>
            </div>

            <!-- Paper Size, Zoom & Print Controls -->
            <div class="flex items-center gap-2">
                <div class="flex items-center gap-1 bg-slate-800 p-1 rounded-lg border border-slate-700 text-xs">
                    <button type="button" id="btnZoomOut" class="p-1 text-slate-400 hover:text-white rounded" title="Perkecil Tampilan">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
                    </button>
                    <span id="zoomLabel" class="font-mono font-bold text-[11px] text-slate-300 px-1">100%</span>
                    <button type="button" id="btnZoomIn" class="p-1 text-slate-400 hover:text-white rounded" title="Perbesar Tampilan">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    </button>
                </div>

                <button type="button" onclick="copyWaText()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition" title="Salin Naskah WhatsApp LAPBANG">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="hidden sm:inline">Salin WA</span>
                </button>

                <?php if (strtoupper(trim((string)($laporan['jenis_laporan'] ?? ''))) === 'PENUTUPAN'): ?>
                    <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=PENUTUPAN" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-950/60 hover:bg-orange-900/70 border border-orange-700/60 text-xs font-semibold text-orange-300 transition" title="Capture / Perbarui Area Peta 16:9 untuk Laporan Perkembangan Sekaligus Penutupan (1x)">
                        <span>Capture Peta 16:9</span>
                    </a>
                <?php endif; ?>

                <button type="button" id="btnDownloadPdf" data-pdf-filename="LAPBANG_<?= e($hariKe ?? 'HARIAN') ?>_OPS_<?= (int)$id ?>_<?= e($operasi['nama_operasi']) ?>" onclick="downloadOfficialPdf(null, this)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-sm cursor-pointer" title="PDF berbentuk gambar (teks tidak bisa dicari). Untuk naskah resmi gunakan Cetak / Simpan PDF.">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>PDF Gambar</span>
                </button>

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition shadow-sm cursor-pointer" title="Naskah resmi: teks tajam &amp; bisa dicari. Pilih &quot;Simpan sebagai PDF&quot; pada dialog cetak.">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak / Simpan PDF</span>
                </button>
            </div>
        </div>
    </header>

    <!-- OFFICIAL BASARNAS PAPER DOCUMENT SHEET -->
    <main class="flex-1 flex justify-center py-4 px-2">
        <article id="official-sitrep-doc" class="page-sheet text-black text-[9.5pt] leading-normal font-serif">
            
            <!-- KOP SURAT RESMI BASARNAS (partial bersama) -->
            <?php include __DIR__ . '/partials/print_kop.php'; ?>

            <!-- JUDUL DOKUMEN & NOMOR DINAS -->
            <div class="text-center my-4">
                <h2 class="doc-title">LAPORAN PERKEMBANGAN (LAPBANG) OPERASI SAR</h2>
                <div class="doc-sub">HARI KE-<?= e($hariKe) ?></div>
                <div class="doc-number">Nomor : <?= e($nomorSitrep) ?></div>
            </div>

            <!-- BAGIAN I: DATA OPERASI & SITUASI -->
            <div class="mb-3 break-inside-avoid">
                <div class="doc-section">
                    I. DATA OPERASI & SITUASI
                </div>
                <table class="w-full table-borderless text-[8.5pt]">
                    <tr>
                        <td class="w-48 font-bold">1. Nama Operasi SAR</td>
                        <td class="w-3 text-center">:</td>
                        <td class="font-bold uppercase"><?= e($operasi['nama_operasi']) ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">2. Waktu Kejadian & LKP</td>
                        <td class="text-center">:</td>
                        <td><?= $tglKejadian ?> Pukul <?= $jamKejadian ?> WITA &bull; Koordinat: <span class="font-mono font-semibold"><?= e(!empty($operasi['koordinat_lokasi']) ? $operasi['koordinat_lokasi'] : ($operasi['koordinat'] ?: '-')) ?></span></td>
                    </tr>
                    <tr>
                        <td class="font-bold">3. Waktu Debriefing Hari Ini</td>
                        <td class="text-center">:</td>
                        <td><?= $tglDebrief ?> Pukul <?= $jamDebrief ?> WITA</td>
                    </tr>
                    <tr>
                        <td class="font-bold">4. Kondisi Cuaca Hari Ini</td>
                        <td class="text-center">:</td>
                        <td><?= e($laporan['cuaca'] ?: ($operasi['cuaca'] ?: '-')) ?> (Gelombang: <?= e($operasi['gelombang'] ?: '-') ?>, Angin: <?= e($operasi['angin'] ?: 'Barat Daya 5-15 Kts') ?>)</td>
                    </tr>
                </table>
            </div>

            <!-- BAGIAN II: DATA KORBAN & STATISTIK -->
            <div class="mb-3 break-inside-avoid">
                <div class="doc-section flex justify-between items-center">
                    <span>II. DATA KORBAN (POB: <?= $statsKorban['total_pob'] ?> ORANG)</span>
                    <span class="text-[8pt] font-mono">
                        Selamat: <span class="text-black font-bold"><?= $statsKorban['selamat'] ?></span> | 
                        MD: <span class="text-black font-bold"><?= $statsKorban['meninggal'] ?></span> | 
                        DP: <span class="text-black font-bold"><?= $statsKorban['dalam_pencarian'] ?></span> |
                        H: <span class="text-black font-bold"><?= $statsKorban['hilang'] ?></span>
                    </span>
                </div>

                <?php if (empty($korbanList)): ?>
                    <p class="text-[8.5pt] italic text-black">Nihil data identitas korban spesifik terdaftar.</p>
                <?php else: ?>
                    <table class="w-full table-dinas text-[8pt] border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-center font-bold">
                                <th class="text-center" style="width: 6%;">NO</th>
                                <th style="width: 26%;">NAMA LENGKAP KORBAN</th>
                                <th class="text-center" style="width: 10%;">UMUR</th>
                                <th class="text-center" style="width: 8%;">L/P</th>
                                <th style="width: 30%;">ALAMAT / ASAL</th>
                                <th class="text-center" style="width: 20%;">STATUS TERAKHIR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($korbanList as $idx => $k): 
                                $stLabel = TemplateHelper::getLabelStatusKorban($k['status_korban'] ?? '');
                                $stUpper = strtoupper($k['status_korban'] ?? '');
                                $colorClass = $stUpper === 'SELAMAT' ? 'text-black font-bold' : ($stUpper === 'MENINGGAL' ? 'text-black font-bold' : 'text-black font-bold');
                            ?>
                                <tr>
                                    <td class="text-center font-bold"><?= $idx + 1 ?></td>
                                    <td class="font-semibold uppercase"><?= e($k['nama_korban']) ?></td>
                                    <td class="text-center"><?= e((string)($k['umur'] ?: '-')) ?> Thn</td>
                                    <td class="text-center"><?= e($k['jk'] ?? ($k['jenis_kelamin'] ?? '-')) ?></td>
                                    <td><?= e($k['alamat'] ?: '-') ?></td>
                                    <td class="text-center <?= $colorClass ?>"><?= e($stLabel) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- BAGIAN III: HASIL OPERASI PENCARIAN HARI INI -->
            <div class="mb-3" style="page-break-inside: auto; break-inside: auto;">
                <div class="doc-section" style="page-break-after: avoid; break-after: avoid;">
                    III. HASIL PELAKSANAAN OPERASI HARI INI
                </div>
                <div class="border border-slate-300 p-2 text-[8.5pt] leading-relaxed bg-slate-50 text-justify" style="page-break-inside: auto; break-inside: auto;">
                    <?= nl2br(e($laporan['isi_laporan'] ?: 'Pencarian telah dilaksanakan oleh Tim SAR Gabungan sesuai Rencana Operasi.')) ?>
                </div>

                <?php
                // Sesuai aturan: Laporan Perkembangan biasa TIDAK menggunakan capturan peta.
                // Hanya Laporan Perkembangan Sekaligus Penutupan (jenis_laporan === 'PENUTUPAN') yang menampilkan 1x capturan peta.
                if (strtoupper(trim((string)($laporan['jenis_laporan'] ?? ''))) === 'PENUTUPAN') {
                    require_once __DIR__ . '/../../models/DokumenPetaSnapshotModel.php';
                    $mapSnapshot = DokumenPetaSnapshotModel::getSnapshot((int)$id, 'PENUTUPAN');
                    $mapSnapshotSectionTitle = 'VISUALISASI PETA HASIL PENCARIAN & PENUTUPAN OPERASI SAR (RASIO 16:9)';
                    include __DIR__ . '/partials/print_map_snapshot_block.php';
                }
                ?>
            </div>

            <!-- BAGIAN IV: ALUT DAN UNSUR TERLIBAT -->
            <div class="mb-3 break-inside-avoid grid grid-cols-2 gap-3 text-[8.5pt]">
                <div>
                    <div class="doc-section">
                        IV. ALUT & PALSAR DIGUNAKAN
                    </div>
                    <?php if (!empty($alutList)): ?>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <?php foreach ($alutList as $a): ?>
                                <li><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?> <?= e($a['nama_alut']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="italic text-black text-justify"><?= nl2br(e($laporan['alut_text'] ?: ($operasi['alut_palsar'] ?: '-'))) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="doc-section">
                        V. UNSUR SAR TERLIBAT
                    </div>
                    <?php if (!empty($unsurList)): ?>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <?php foreach ($unsurList as $u): ?>
                                <li><?= e($u['nama_unsur']) ?> (<?= (int)($u['jumlah_personil'] ?? 1) ?> Org)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="italic text-black text-justify"><?= nl2br(e($laporan['unsur_text'] ?: ($operasi['unsur_terlibat'] ?: '-'))) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LEGALISASI, TANDA TANGAN & QR CODE VERIFIKASI (ANTI-TERPOTONG) -->
            <div class="signature-block mt-6 break-inside-avoid border-t border-black pt-3">
                <div class="flex justify-end text-[10.5pt] mb-2 font-serif">
                    <div class="text-left leading-tight">
                        <div>Dikeluarkan di : <span class="font-bold">Kendari</span></div>
                        <div>Pada Tanggal : <span class="font-bold"><?= $tglDebrief ?></span></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 text-left text-[11pt] font-serif">
                    <!-- Kolom Kiri: Koordinator Lapangan / OSC (Align Left) -->
                    <div class="flex flex-col justify-between min-h-[140px] text-left">
                        <div>
                            <p class="font-bold uppercase text-[11pt] leading-tight m-0">On Scene Commander (OSC),</p>
                            <p class="text-[9.5pt] text-black leading-tight m-0 mt-0.5">Komandan Lapangan KPP Kendari</p>
                        </div>
                        <div class="mt-auto pt-6 leading-tight">
                            <p class="font-bold underline uppercase text-[11pt] m-0">KOMANDAN TIM RESCUE</p>
                            <p class="text-[9.5pt] m-0 mt-0.5">KPP Kendari</p>
                        </div>
                    </div>

                    <!-- Kolom Tengah: QR Code Verifikasi Digital dengan Logo HD di Tengah -->
                    <div class="flex flex-col items-center justify-center p-1 text-center">
                        <div class="relative flex items-center justify-center">
                            <canvas id="qrCanvasSitrep" class="w-24 h-24 border border-slate-300"></canvas>
                            <img id="qrImgSitrep" class="w-24 h-24 border border-slate-300 hidden" alt="QR Code Verifikasi Resmi">
                        </div>
                        <div class="text-[8pt] font-mono font-bold uppercase mt-1.5 leading-tight text-center">
                            TERVERIFIKASI SISTEM QR-SAR<br>
                            <span class="text-black">KPP KENDARI &bull; <?= e($nomorSitrep) ?></span><br>
                            <span class="text-black font-normal">Sah Secara Elektronik</span>
                        </div>
                    </div>

                    <!-- Kolom Kanan: SAR Mission Coordinator (SMC) (Align Left) -->
                    <div class="flex flex-col justify-between min-h-[140px] text-left">
                        <div>
                            <p class="font-bold uppercase text-[11pt] leading-tight m-0">SAR Mission Coordinator (SMC),</p>
                            <p class="text-[9.5pt] text-black leading-tight m-0 mt-0.5"><?= strtoupper(e($defaultSmc['jabatan'] ?? 'KEPALA SEKSI OPERASI DAN SIAGA')) ?></p>
                        </div>
                        <div class="mt-auto pt-6 leading-tight">
                            <p class="font-bold underline uppercase text-[11pt] m-0"><?= strtoupper(e($defaultSmc['nama'] ?? 'AMIRUDDIN A.S., S.Sos.')) ?></p>
                            <p class="text-[9.5pt] m-0 mt-0.5"><?= e($defaultSmc['pangkat'] ?? 'PEMBINA (IV/a)') ?><?= !empty($defaultSmc['nip']) ? '<br>NIP. ' . e($defaultSmc['nip']) : '' ?></p>
                        </div>
                    </div>
                </div>
            </div>

        </article>
    </main>

    <!-- Hidden Raw WA text container for copying -->
    <div id="raw-wa-text-holder" class="hidden"><?= htmlspecialchars($fullWaText) ?></div>

    <!-- QR Code & PDF Exporter Scripts -->
    <script src="<?= assetUrl('assets/vendor/qrcode/qrcode.min.js') ?>"></script>
    <script src="<?= assetUrl('assets/vendor/html2pdf/html2pdf.bundle.min.js') ?>"></script>
    <script src="<?= assetUrl('assets/js/pdf_document_exporter.js') ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const verifyUrl = <?= jsonSafe($verifyUrl) ?>;
        const logoDataUri = <?= jsonSafe($logoDataUri) ?>;
        const canvas = document.getElementById('qrCanvasSitrep');
        const imgEl = document.getElementById('qrImgSitrep');

        if (canvas && typeof QRCode !== 'undefined') {
            QRCode.toCanvas(canvas, verifyUrl, {
                width: 200,
                margin: 1,
                color: { dark: '#000000', light: '#ffffff' },
                errorCorrectionLevel: 'H'
            }, function(err) {
                if (err) {
                    console.warn('QR code generation error:', err);
                    return;
                }
                const ctx = canvas.getContext('2d');
                if (!ctx) return;
                const size = canvas.width;
                const logoImg = new Image();

                function drawCenterEmblem() {
                    const logoSize = Math.floor(size * 0.22);
                    const center = (size - logoSize) / 2;
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

                    try {
                        const dataUrl = canvas.toDataURL('image/png');
                        if (imgEl) {
                            imgEl.src = dataUrl;
                            imgEl.classList.remove('hidden');
                            canvas.classList.add('hidden');
                        }
                    } catch (e) {
                        console.warn('Sync to img error:', e);
                    }
                }

                if (logoDataUri) {
                    logoImg.onload = drawCenterEmblem;
                    logoImg.onerror = drawCenterEmblem;
                    logoImg.src = logoDataUri;
                } else {
                    drawCenterEmblem();
                }
            });
        }


        // Zoom controls
        let currentZoom = 100;
        const pageSheet = document.getElementById('official-sitrep-doc');
        const zoomLabel = document.getElementById('zoomLabel');
        const btnZoomIn = document.getElementById('btnZoomIn');
        const btnZoomOut = document.getElementById('btnZoomOut');

        if (btnZoomIn && btnZoomOut && pageSheet && zoomLabel) {
            btnZoomIn.addEventListener('click', function() {
                if (currentZoom < 150) {
                    currentZoom += 10;
                    applyZoom();
                }
            });
            btnZoomOut.addEventListener('click', function() {
                if (currentZoom > 70) {
                    currentZoom -= 10;
                    applyZoom();
                }
            });
            function applyZoom() {
                zoomLabel.textContent = currentZoom + '%';
                pageSheet.style.transform = `scale(${currentZoom / 100})`;
                pageSheet.style.transformOrigin = 'top center';
            }
        }
    });

    function copyWaText() {
        const holder = document.getElementById('raw-wa-text-holder');
        if (!holder) return;
        const text = holder.innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert("Naskah WhatsApp LAPBANG berhasil disalin ke clipboard!");
        }).catch(err => {
            console.error("Gagal menyalin teks: ", err);
        });
    }
    </script>
</body>
</html>
