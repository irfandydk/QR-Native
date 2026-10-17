<?php
/**
 * QR-SAR - Lembar Dokumen Resmi Laporan Awal Operasi SAR
 * KPP Kendari - Basarnas
 * Standar Naskah Dinas Resmi Basarnas Republik Indonesia
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once dirname(__DIR__, 2) . '/config/app.php';
}

if (!isset($operasi) || empty($operasi)) {
    $id = (int)($_GET['id'] ?? 0);
    $operasi = $id > 0 ? OperasiModel::getById($id) : null;
}

if (!$operasi) {
    if (AuthHelper::isLoggedIn()) {
        setFlash('error', 'Dokumen Operasi SAR tidak ditemukan.');
        header("Location: " . BASE_URL . "/operasi.php");
        exit;
    }
    http_response_code(404);
    exit('Dokumen Operasi SAR tidak ditemukan.');
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

$nomorLapAwal = TemplateHelper::generateNomorLaporanAwal($operasi, $satkerKode);
$statsKorban = TemplateHelper::hitungStatistikKorban($operasi['korban'] ?? [], (int)($operasi['jumlah_pob'] ?? 0));

$tglLapor = TemplateHelper::formatTanggalIndo($operasi['waktu_lapor'] ?? '');
$jamLapor = TemplateHelper::formatJamWITA($operasi['waktu_lapor'] ?? '');
$tglKejadian = TemplateHelper::formatTanggalIndo($operasi['waktu_kejadian'] ?? '');
$jamKejadian = TemplateHelper::formatJamWITA($operasi['waktu_kejadian'] ?? '');
$tglBerangkat = TemplateHelper::formatTanggalIndo($operasi['waktu_berangkat'] ?? '');
$jamBerangkat = TemplateHelper::formatJamWITA($operasi['waktu_berangkat'] ?? '');
$tglEta = TemplateHelper::formatTanggalIndo($operasi['eta_tiba'] ?? '');
$jamEta = TemplateHelper::formatJamWITA($operasi['eta_tiba'] ?? '');
$tglCetak = TemplateHelper::formatTanggalIndo(date('Y-m-d H:i:s'));

$verifyUrl = BASE_URL . "/operasi_detail.php?id={$id}";
$fullWaText = $operasi['full_text_wa'] ?? '';
if (empty($fullWaText)) {
    $fullWaText = TemplateHelper::generateLaporanAwalWA($operasi, $operasi['korban'] ?? [], $operasi['detail_unsur'] ?? [], $operasi['detail_alut'] ?? []);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Awal Operasi SAR - <?= e($operasi['nama_operasi']) ?> - BASARNAS</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo_basarnas.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 10mm 15mm 12mm 15mm;
        }
        @media print {
            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: Arial, "Helvetica Neue", Helvetica, sans-serif !important;
                font-size: 10pt !important;
                line-height: 1.35 !important;
            }
            .no-print, .no-print * {
                display: none !important;
            }
            .page-sheet {
                box-shadow: none !important;
                border: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .break-inside-avoid {
                page-break-inside: avoid;
            }
            a {
                text-decoration: none !important;
                color: inherit !important;
            }
        }
        body {
            font-family: Arial, "Helvetica Neue", Helvetica, sans-serif;
            background-color: #0f172a;
            color: #1e293b;
        }
        .page-sheet {
            background-color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            width: 210mm;
            min-height: 297mm;
            margin: 20px auto;
            padding: 12mm 16mm 14mm 16mm;
            box-sizing: border-box;
            transition: transform 0.2s ease-in-out;
        }
        .table-dinas th, .table-dinas td {
            border: 1px solid #000000;
            padding: 4px 6px;
        }
        .table-borderless td {
            padding: 2px 4px;
            vertical-align: top;
        }
    </style>
</head>
<body class="antialiased min-h-screen flex flex-col justify-between">

    <!-- FLOATING ACTION TOOLBAR (Hidden when printing) -->
    <header class="no-print sticky top-0 z-50 bg-slate-900/95 backdrop-blur border-b border-slate-800 text-white px-4 py-3 shadow-md">
        <div class="max-w-5xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    <span>Kembali ke Detail</span>
                </a>
                <span class="text-xs text-slate-500 hidden sm:inline">|</span>
                <span class="text-xs font-mono font-bold text-orange-400">OPS-#<?= $id ?></span>
                <span class="text-xs text-slate-300 font-semibold truncate max-w-[200px] sm:max-w-xs"><?= e($operasi['nama_operasi']) ?></span>
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

                <button type="button" onclick="copyWaText()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition" title="Salin Naskah WhatsApp Laporan Awal">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="hidden sm:inline">Salin WA</span>
                </button>

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition shadow-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak Dokumen</span>
                </button>
            </div>
        </div>
    </header>

    <!-- OFFICIAL BASARNAS PAPER DOCUMENT SHEET -->
    <main class="flex-1 flex justify-center py-4 px-2">
        <article id="official-laporan-awal-doc" class="page-sheet text-black text-[10.5pt] leading-normal font-sans">
            
            <!-- KOP SURAT RESMI BASARNAS -->
            <div class="relative flex items-center justify-between gap-4 pb-2 text-black">
                <div class="w-20 h-20 shrink-0 flex items-center justify-center">
                    <?php if (!empty($customLogo)): ?>
                        <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
                    <?php else: ?>
                        <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" class="w-full h-full object-contain">
                    <?php endif; ?>
                </div>

                <div class="text-center flex-1 px-2">
                    <h2 class="text-[12pt] font-bold tracking-wider uppercase leading-snug m-0 p-0 text-black">
                        BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
                    </h2>
                    <h1 class="text-[14pt] font-black tracking-wider uppercase leading-tight m-0 mt-0.5 p-0 text-black">
                        KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
                    </h1>
                    <p class="text-[8pt] leading-tight m-0 mt-1 text-slate-800">
                        Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sulawesi Tenggara 93116
                    </p>
                    <p class="text-[8pt] leading-tight m-0 text-slate-800">
                        Telepon: (0401) 3196557 &bull; Faksimile: (0401) 3196558 &bull; Emergency Call: 115
                    </p>
                    <p class="text-[7.5pt] italic leading-tight m-0 text-slate-700">
                        Laman: kendari.basarnas.go.id &bull; Pos-el: sar304kendari@gmail.com
                    </p>
                </div>

                <div class="w-20 h-20 shrink-0 hidden sm:flex items-center justify-center">
                    <!-- Right balanced spacer / SAR Badge placeholder -->
                    <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" class="w-full h-full object-contain opacity-0">
                </div>
            </div>

            <!-- GARIS GANDA KOP DINAS (Double Horizontal Line) -->
            <div class="w-full mb-4">
                <div class="border-b-[2.5px] border-black pb-[1.5px]">
                    <div class="border-b border-black"></div>
                </div>
            </div>

            <!-- JUDUL DOKUMEN -->
            <div class="text-center mb-4">
                <h3 class="text-[13pt] font-black uppercase tracking-wider underline underline-offset-4 m-0 p-0">
                    LAPORAN AWAL OPERASI SAR
                </h3>
                <div class="text-[10pt] font-mono font-bold tracking-wide mt-1">
                    NOMOR: <?= htmlspecialchars($nomorLapAwal) ?>
                </div>
                <div class="text-[10.5pt] font-bold uppercase mt-1">
                    TENTANG: <?= e($operasi['nama_operasi']) ?>
                </div>
            </div>

            <!-- I. DATA INFORMASI AWAL KEJADIAN -->
            <div class="mb-3 break-inside-avoid">
                <div class="font-bold text-[10.5pt] uppercase bg-slate-100 px-2 py-0.5 border border-black mb-1.5">
                    I. SUMBER INFORMASI & LAPORAN KEJADIAN
                </div>
                <table class="w-full table-borderless text-[10pt]">
                    <tr>
                        <td class="w-48 font-bold">1. Waktu Terima Laporan</td>
                        <td class="w-3 text-center">:</td>
                        <td><?= $tglLapor ?> pukul <?= $jamLapor ?> WITA</td>
                    </tr>
                    <tr>
                        <td class="font-bold">2. Nama Pelapor</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['nama_pelapor'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">3. Hubungan / Status</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['hubungan_pelapor'] ?: 'Masyarakat') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">4. Nomor Telepon / Kontak</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['no_telp_pelapor'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">5. Waktu Kejadian</td>
                        <td class="text-center">:</td>
                        <td><?= $tglKejadian ?> pukul <?= $jamKejadian ?> WITA</td>
                    </tr>
                </table>
            </div>

            <!-- II. LOKASI KEJADIAN & DATA NAVIGASI (LKP) -->
            <div class="mb-3 break-inside-avoid">
                <div class="font-bold text-[10.5pt] uppercase bg-slate-100 px-2 py-0.5 border border-black mb-1.5">
                    II. LOKASI KEJADIAN & LAST KNOWN POSITION (LKP)
                </div>
                <table class="w-full table-borderless text-[10pt]">
                    <tr>
                        <td class="w-48 font-bold">1. Lokasi / Wilayah</td>
                        <td class="w-3 text-center">:</td>
                        <td><?= e($operasi['lokasi_kejadian'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">2. Titik Koordinat LKP (DMS)</td>
                        <td class="text-center">:</td>
                        <td class="font-mono font-bold"><?= e($operasi['koordinat'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">3. Jarak Tempuh & Radial</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['jarak'] ?: '-') ?><?= !empty($operasi['radial']) ? " (Radial {$operasi['radial']}°)" : '' ?> dari Pangkalan Keberangkatan</td>
                    </tr>
                    <tr>
                        <td class="font-bold">4. Estimasi Waktu Tempuh</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['eta_lokasi'] ?: '-') ?> (Estimasi Tiba / ETA: <?= $tglEta ?> pukul <?= $jamEta ?> WITA)</td>
                    </tr>
                </table>
            </div>

            <!-- III. DATA KORBAN / PEOPLE ON BOARD (POB) -->
            <div class="mb-3 break-inside-avoid">
                <div class="font-bold text-[10.5pt] uppercase bg-slate-100 px-2 py-0.5 border border-black mb-1.5 flex justify-between items-center">
                    <span>III. DATA KORBAN / PEOPLE ON BOARD (POB)</span>
                    <span class="font-mono text-[9.5pt]">TOTAL POB: <?= (int)$statsKorban['total'] ?> ORANG</span>
                </div>

                <!-- Mini Status Table -->
                <div class="grid grid-cols-4 gap-1 text-center font-bold text-[9pt] mb-2">
                    <div class="border border-black p-1 bg-white">
                        <span class="block text-slate-600 text-[8pt]">SELAMAT (S)</span>
                        <span class="text-[11pt]"><?= (int)$statsKorban['s'] ?> Org</span>
                    </div>
                    <div class="border border-black p-1 bg-white">
                        <span class="block text-slate-600 text-[8pt]">MENINGGAL (MD)</span>
                        <span class="text-[11pt]"><?= (int)$statsKorban['md'] ?> Org</span>
                    </div>
                    <div class="border border-black p-1 bg-white">
                        <span class="block text-slate-600 text-[8pt]">PENCARIAN (DP)</span>
                        <span class="text-[11pt]"><?= (int)$statsKorban['dp'] ?> Org</span>
                    </div>
                    <div class="border border-black p-1 bg-white">
                        <span class="block text-slate-600 text-[8pt]">HILANG / NIHIL (H)</span>
                        <span class="text-[11pt]"><?= (int)$statsKorban['h'] ?> Org</span>
                    </div>
                </div>

                <!-- Tabel Rinci Identitas Korban -->
                <table class="w-full table-dinas text-[9.5pt]">
                    <thead class="bg-slate-50 font-bold uppercase text-[8.5pt]">
                        <tr>
                            <th class="w-8 text-center">No</th>
                            <th>Nama Lengkap Korban</th>
                            <th class="w-16 text-center">Umur</th>
                            <th class="w-16 text-center">JK</th>
                            <th>Alamat Domisili</th>
                            <th class="w-28 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($operasi['korban']) && empty($statsKorban['unrecorded_dp'])): ?>
                        <tr>
                            <td colspan="6" class="text-center py-2 text-slate-500 italic">Data korban nihil atau belum terdata secara spesifik.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach (($operasi['korban'] ?? []) as $idx => $k): ?>
                            <tr>
                                <td class="text-center font-bold"><?= $idx + 1 ?></td>
                                <td class="font-bold"><?= e($k['nama_korban']) ?></td>
                                <td class="text-center"><?= e($k['umur'] ?: '-') ?></td>
                                <td class="text-center"><?= e($k['jk'] ?: '-') ?></td>
                                <td><?= e($k['alamat'] ?: '-') ?></td>
                                <td class="text-center font-bold font-mono text-[9pt]">
                                    <?= TemplateHelper::getLabelStatusKorban($k['status_korban']) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (!empty($statsKorban['unrecorded_dp'])): ?>
                            <tr class="bg-slate-50 font-medium">
                                <td class="text-center font-bold"><?= count($operasi['korban'] ?? []) + 1 ?></td>
                                <td class="font-bold italic" colspan="4">
                                    <?= (int)$statsKorban['unrecorded_dp'] ?> Orang Korban Belum Terdata Identitasnya (POB Belum Teridentifikasi)
                                </td>
                                <td class="text-center font-bold font-mono text-[9pt]">
                                    Dalam Pencarian (DP)
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- IV. PELAKSANAAN SAR, UNSUR & ALUT -->
            <div class="mb-3 break-inside-avoid">
                <div class="font-bold text-[10.5pt] uppercase bg-slate-100 px-2 py-0.5 border border-black mb-1.5">
                    IV. PELAKSANAAN SAR, TIM & ALUT DIKERAHKAN
                </div>
                <table class="w-full table-borderless text-[10pt] mb-2">
                    <tr>
                        <td class="w-48 font-bold">1. Waktu Keberangkatan Tim</td>
                        <td class="w-3 text-center">:</td>
                        <td><?= $tglBerangkat ?> pukul <?= $jamBerangkat ?> WITA</td>
                    </tr>
                    <tr>
                        <td class="font-bold">2. Tim SAR Diberangkatkan</td>
                        <td class="text-center">:</td>
                        <td class="font-bold"><?= e($operasi['tim_berangkat'] ?: 'Tim Rescue Kansar Kendari') ?></td>
                    </tr>
                </table>

                <div class="grid grid-cols-2 gap-3 text-[9.5pt]">
                    <!-- Unsur SAR Terlibat -->
                    <div class="border border-black p-2">
                        <span class="font-bold uppercase block text-[8.5pt] border-b border-black pb-1 mb-1.5">Unsur SAR Terlibat:</span>
                        <?php if (!empty($operasi['detail_unsur'])): ?>
                            <ul class="list-disc list-inside space-y-0.5">
                                <?php foreach ($operasi['detail_unsur'] as $u): ?>
                                    <li><span class="font-bold"><?= e($u['nama_unsur']) ?></span> (<?= (int)$u['jumlah_personil'] ?> Personil)</li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="whitespace-pre-line"><?= e($operasi['unsur_terlibat'] ?: '• Tim Rescue Kansar Kendari') ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Alut dan Palsar -->
                    <div class="border border-black p-2">
                        <span class="font-bold uppercase block text-[8.5pt] border-b border-black pb-1 mb-1.5">Alat Utama (Alut) & Palsar:</span>
                        <?php if (!empty($operasi['detail_alut'])): ?>
                            <ul class="list-disc list-inside space-y-0.5">
                                <?php foreach ($operasi['detail_alut'] as $a): ?>
                                    <li><span class="font-bold"><?= (int)$a['jumlah_alut'] ?> <?= e($a['satuan'] ?: 'Unit') ?></span> <?= e($a['nama_alut']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="whitespace-pre-line"><?= e($operasi['alut_palsar'] ?: '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat') ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- V. KRONOLOGI KEJADIAN -->
            <div class="mb-3 break-inside-avoid">
                <div class="font-bold text-[10.5pt] uppercase bg-slate-100 px-2 py-0.5 border border-black mb-1.5">
                    V. KRONOLOGI KEJADIAN
                </div>
                <div class="p-2 border border-black text-[10pt] leading-relaxed text-justify">
                    <?= nl2br(e($operasi['kronologi'] ?: 'Kronologi kejadian belum terinci.')) ?>
                </div>
            </div>

            <!-- VI. KONDISI METEOROLOGI & FAKTOR PENGHAMBAT -->
            <div class="mb-4 break-inside-avoid">
                <div class="font-bold text-[10.5pt] uppercase bg-slate-100 px-2 py-0.5 border border-black mb-1.5">
                    VI. KONDISI CUACA & FAKTOR PENGHAMBAT
                </div>
                <table class="w-full table-borderless text-[10pt]">
                    <tr>
                        <td class="w-48 font-bold">1. Cuaca di Lapangan</td>
                        <td class="w-3 text-center">:</td>
                        <td><?= e($operasi['cuaca'] ?: 'Cerah Berawan') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">2. Tinggi Gelombang</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['gelombang'] ?: '0.5 - 1.25 Meter') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">3. Kecepatan & Arah Angin</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['angin'] ?: 'Barat Daya, 5 - 15 Knots') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">4. Faktor Penghambat</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['faktor_penghambat'] ?: 'Nihil') ?></td>
                    </tr>
                </table>
            </div>

            <!-- LEGALISASI, TANDA TANGAN & QR CODE VERIFIKASI -->
            <div class="mt-6 break-inside-avoid border-t border-black pt-3">
                <div class="flex justify-end text-[10pt] mb-2">
                    <p>Dikeluarkan di : <span class="font-bold">Kendari</span><br>Pada Tanggal : <span class="font-bold"><?= $tglLapor ?></span></p>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center text-[9.5pt]">
                    <!-- Kolom Kiri: Senkom -->
                    <div class="flex flex-col justify-between h-40">
                        <div>
                            <p class="font-bold uppercase">Petugas Siaga Komunikasi</p>
                            <p class="text-[8.5pt] text-slate-700">Dinas Senkom KPP Kendari</p>
                        </div>
                        <div class="mt-auto">
                            <p class="font-bold underline uppercase">SENKOM SIAGA</p>
                            <p class="text-[8.5pt]">KPP Kendari</p>
                        </div>
                    </div>

                    <!-- Kolom Tengah: QR Code Verifikasi Digital -->
                    <div class="flex flex-col items-center justify-center p-1">
                        <canvas id="qrCanvasLaporanAwal" class="w-24 h-24 border border-slate-300"></canvas>
                        <div class="text-[7.5pt] font-mono font-bold uppercase mt-1 leading-tight text-center">
                            TERVERIFIKASI SISTEM QR-SAR<br>
                            <span class="text-slate-500">KPP KENDARI &bull; OPS-#<?= $id ?></span><br>
                            <span class="text-slate-400 font-normal">Sah Secara Elektronik</span>
                        </div>
                    </div>

                    <!-- Kolom Kanan: SAR Mission Coordinator (SMC) -->
                    <div class="flex flex-col justify-between h-40">
                        <div>
                            <p class="font-bold uppercase">SAR Mission Coordinator (SMC)</p>
                            <p class="text-[8.5pt] text-slate-700"><?= strtoupper(e($defaultSmc['jabatan'] ?? 'KEPALA SEKSI OPERASI DAN SIAGA')) ?></p>
                        </div>
                        <div class="mt-auto">
                            <p class="font-bold underline uppercase"><?= strtoupper(e($defaultSmc['nama'] ?? 'AMIRUDDIN A.S., S.Sos.')) ?></p>
                            <p class="text-[8.5pt]"><?= e($defaultSmc['pangkat'] ?? 'PEMBINA (IV/a)') ?><?= !empty($defaultSmc['nip']) ? ' &bull; NIP. ' . e($defaultSmc['nip']) : '' ?></p>
                        </div>
                    </div>
                </div>
            </div>

        </article>
    </main>

    <!-- Hidden Raw WA text container for copying -->
    <div id="raw-wa-text-holder" class="hidden"><?= htmlspecialchars($fullWaText) ?></div>

    <!-- QR Code Script -->
    <script src="<?= BASE_URL ?>/assets/vendor/qrcode/qrcode.min.js?v=<?= APP_VERSION ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const verifyUrl = <?= jsonSafe($verifyUrl) ?>;
        const canvas = document.getElementById('qrCanvasLaporanAwal');

        if (canvas && typeof QRCode !== 'undefined') {
            QRCode.toCanvas(canvas, verifyUrl, {
                width: 100,
                margin: 1,
                color: { dark: '#000000', light: '#ffffff' },
                errorCorrectionLevel: 'M'
            }, function(err) {
                if (err) console.warn('QR code generation error:', err);
            });
        }

        // Zoom Handling
        let zoomLevel = 1.0;
        const docEl = document.getElementById('official-laporan-awal-doc');
        const zoomLabel = document.getElementById('zoomLabel');

        document.getElementById('btnZoomIn')?.addEventListener('click', () => {
            if (zoomLevel < 1.4) {
                zoomLevel = parseFloat((zoomLevel + 0.1).toFixed(1));
                applyZoom();
            }
        });

        document.getElementById('btnZoomOut')?.addEventListener('click', () => {
            if (zoomLevel > 0.7) {
                zoomLevel = parseFloat((zoomLevel - 0.1).toFixed(1));
                applyZoom();
            }
        });

        function applyZoom() {
            if (!docEl) return;
            docEl.style.transform = `scale(${zoomLevel})`;
            docEl.style.transformOrigin = 'top center';
            if (zoomLabel) zoomLabel.textContent = `${Math.round(zoomLevel * 100)}%`;
        }
    });

    function copyWaText() {
        const text = document.getElementById('raw-wa-text-holder')?.textContent || '';
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Naskah WhatsApp Laporan Awal berhasil disalin ke clipboard!');
            });
        } else {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            alert('Naskah WhatsApp Laporan Awal berhasil disalin!');
        }
    }
    </script>
</body>
</html>
