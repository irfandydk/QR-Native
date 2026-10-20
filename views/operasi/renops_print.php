<?php
/**
 * QR-SAR - Lembar Dokumen Resmi Rencana Operasi SAR (RENOPS)
 * KPP Kendari - Basarnas
 * Standar Naskah Dinas Resmi Basarnas Republik Indonesia
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once dirname(__DIR__, 2) . '/config/app.php';
}

$idRenops = (int)($_GET['id_renops'] ?? 0);
$idOps = (int)($_GET['id'] ?? 0);

$renops = null;
if ($idRenops > 0) {
    $renops = RenopsModel::getById($idRenops);
    if ($renops) {
        $idOps = (int)$renops['id_operasi'];
    }
}

$operasi = $idOps > 0 ? OperasiModel::getById($idOps) : null;

// Jika renops belum ditentukan tapi operasi ada, ambil renops terbaru
if (!$renops && $operasi) {
    $listRenops = RenopsModel::getByOperasiId($idOps);
    if (!empty($listRenops)) {
        $renops = end($listRenops);
        $idRenops = (int)$renops['id_renops'];
    }
}

if (!$operasi || !$renops) {
    if (AuthHelper::isLoggedIn()) {
        setFlash('error', 'Dokumen Rencana Operasi SAR (RENOPS) tidak ditemukan.');
        header("Location: " . BASE_URL . "/operasi.php");
        exit;
    }
    http_response_code(404);
    exit('Dokumen Rencana Operasi SAR (RENOPS) tidak ditemukan.');
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

$nomorRenops = TemplateHelper::generateNomorRenops($operasi, $renops, $satkerKode);
$hariKe = strtoupper($renops['hari_ke'] ?? 'H.1');

$waktuBriefing = $renops['waktu_briefing'] ?? date('Y-m-d H:i:s');
$tglBriefing = TemplateHelper::formatTanggalIndo($waktuBriefing);
$jamBriefing = TemplateHelper::formatJamWITA($waktuBriefing);
$waktuMulai = $renops['waktu_mulai_ops'] ?? date('Y-m-d H:i:s');
$jamMulai = TemplateHelper::formatJamWITA($waktuMulai);

$tglKejadian = TemplateHelper::formatTanggalIndo($operasi['waktu_kejadian'] ?? '');
$jamKejadian = TemplateHelper::formatJamWITA($operasi['waktu_kejadian'] ?? '');

$verifyUrl = BASE_URL . "/operasi_detail.php?id={$id}&tab=renops";
$fullWaText = $renops['full_text_wa'] ?? '';
$srus = $renops['srus'] ?? [];
$unsurList = !empty($renops['detail_unsur']) ? $renops['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
$alutList = !empty($renops['detail_alut']) ? $renops['detail_alut'] : ($operasi['detail_alut'] ?? []);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RENOPS <?= e($hariKe) ?> - <?= e($operasi['nama_operasi']) ?> - BASARNAS</title>
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/logo_basarnas.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 12mm 15mm;
        }
        @page :first {
            margin-top: 10mm;
        }
        @media print {
            html, body {
                background: #ffffff !important;
                color: #000000 !important;
                font-family: Arial, "Helvetica Neue", Helvetica, sans-serif !important;
                font-size: 9.5pt !important;
                line-height: 1.3 !important;
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
            color: #000000;
        }
        .page-sheet {
            background-color: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            padding: 12mm 16mm 14mm 16mm;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            border-radius: 2px;
            box-sizing: border-box;
            transition: transform 0.2s ease-in-out;
            overflow-x: hidden;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .page-sheet *, .page-sheet *::before, .page-sheet *::after {
            box-sizing: border-box !important;
            max-width: 100%;
        }
        .page-sheet table {
            width: 100% !important;
            max-width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse !important;
        }
        .table-dinas th, .table-dinas td {
            border: 1px solid #000000;
            padding: 4px 6px;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
            word-break: break-word !important;
        }
        .table-borderless td {
            padding: 2px 4px;
            vertical-align: top;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
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
                <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    <span>Kembali ke Detail</span>
                </a>
                <span class="text-xs text-slate-500 hidden sm:inline">|</span>
                <span class="text-xs font-mono font-bold text-orange-400">OPS-#<?= $id ?></span>
                <span class="text-xs text-slate-300 font-semibold truncate max-w-[200px] sm:max-w-xs"><?= e($operasi['nama_operasi']) ?> (<?= e($hariKe) ?>)</span>
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

                <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=RENOPS&capture_ref=<?= (int)($renops['id_renops'] ?? 0) ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-700 hover:bg-emerald-600 text-xs font-bold text-white transition no-underline" title="Ambil atau Perbarui Capture Peta 16:9 untuk RENOPS Hari ke-<?= e($hariKe) ?> ini">
                    <span>📸 Capture Peta 16:9</span>
                </a>

                <button type="button" onclick="copyWaText()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition" title="Salin Naskah WhatsApp RENOPS">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="hidden sm:inline">Salin WA</span>
                </button>

                <button type="button" id="btnDownloadPdf" data-pdf-filename="RENOPS_H.<?= e($hariKe) ?>_OPS_<?= (int)$id ?>_<?= e($operasi['nama_operasi']) ?>" onclick="downloadOfficialPdf(null, this)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-sm cursor-pointer" title="Download Dokumen Elektronik PDF (Ukuran Optimal A4 Portrait)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    <span>Download PDF</span>
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
        <article id="official-renops-doc" class="page-sheet text-black text-[10pt] leading-normal font-sans">
            
            <!-- KOP SURAT RESMI BASARNAS -->
            <div class="relative flex items-center justify-between gap-4 pb-2 text-black border-b-2 border-black">
                <div class="w-20 h-20 shrink-0 flex items-center justify-center">
                    <?php if (!empty($customLogo)): ?>
                        <img src="<?= htmlspecialchars($customLogo) ?>" alt="Logo Basarnas" class="w-full h-full object-contain">
                    <?php else: ?>
                        <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" class="w-full h-full object-contain">
                    <?php endif; ?>
                </div>

                <div class="text-center flex-1 px-2">
                    <h2 class="text-[11.5pt] font-bold tracking-wider uppercase leading-snug m-0 p-0 text-black">
                        BADAN NASIONAL PENCARIAN DAN PERTOLONGAN
                    </h2>
                    <h1 class="text-[13.5pt] font-black tracking-wider uppercase leading-tight m-0 mt-0.5 p-0 text-black">
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
                    <img src="<?= BASE_URL ?>/assets/images/logo_basarnas.svg" alt="Logo Basarnas" class="w-full h-full object-contain opacity-0">
                </div>
            </div>

            <!-- JUDUL DOKUMEN & NOMOR DINAS -->
            <div class="text-center my-4">
                <h2 class="text-[12pt] font-black uppercase tracking-wide underline underline-offset-4 m-0">
                    RENCANA OPERASI PENCARIAN DAN PERTOLONGAN (RENOPS)
                </h2>
                <p class="text-[10pt] font-bold uppercase mt-1 m-0">
                    HARI KE-<?= e($hariKe) ?>
                </p>
                <p class="text-[9.5pt] font-mono font-bold text-slate-800 mt-0.5 m-0">
                    Nomor : <?= e($nomorRenops) ?>
                </p>
            </div>

            <!-- BAGIAN I: DATA KEJADIAN & SITUASI UMUM -->
            <div class="mb-3 break-inside-avoid">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9.5pt] mb-1">
                    I. DATA KEJADIAN & SITUASI UMUM
                </div>
                <table class="w-full table-borderless text-[9pt]">
                    <tr>
                        <td class="w-48 font-bold">1. Nama Operasi SAR</td>
                        <td class="w-3 text-center">:</td>
                        <td class="font-bold uppercase"><?= e($operasi['nama_operasi']) ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">2. Jenis Kejadian / Musibah</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['jenis_kejadian'] ?: 'Kecelakaan Kapal / Kondisi Membahayakan Manusia') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">3. Waktu Kejadian</td>
                        <td class="text-center">:</td>
                        <td><?= $tglKejadian ?> Pukul <?= $jamKejadian ?> WITA</td>
                    </tr>
                    <tr>
                        <td class="font-bold">4. Titik Duga (LKP)</td>
                        <td class="text-center">:</td>
                        <td class="font-mono font-semibold"><?= e(!empty($operasi['koordinat_lokasi']) ? $operasi['koordinat_lokasi'] : ($operasi['koordinat'] ?: '-')) ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">5. Jarak & Radial dari Kantor/Pos</td>
                        <td class="text-center">:</td>
                        <td><?= e($operasi['jarak'] ?: '-') ?> / Radial <?= e($operasi['radial'] ?: '-') ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">6. Prakiraan Cuaca Hari Ini</td>
                        <td class="text-center">:</td>
                        <td><?= e($renops['cuaca'] ?: ($operasi['cuaca'] ?: 'Cerah Berawan')) ?> (Gelombang: <?= e($operasi['gelombang'] ?: '0.5 - 1.25 M') ?>, Angin: <?= e($operasi['angin'] ?: 'Barat Daya 5-15 Kts') ?>)</td>
                    </tr>
                </table>
            </div>

            <!-- BAGIAN II: PETA RENCANA OPERASI, PEMBAGIAN SRU, DAN LEGENDA PETA -->
            <div class="mb-3" style="page-break-inside: auto; break-inside: auto;">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9.5pt] mb-1.5 flex justify-between items-center" style="page-break-after: avoid; break-after: avoid;">
                    <span>II. PETA RENCANA OPERASI, PEMBAGIAN SATUAN TUGAS (SRU) &amp; LEGENDA PETA</span>
                    <span class="text-[8pt] font-mono font-normal">
                        <?= !empty($renops['satuan_jarak']) ? 'Satuan: ' . e($renops['satuan_jarak']) . ' | ' : '' ?>Total: <?= count($srus) ?> SRU
                    </span>
                </div>

                <?php
                require_once dirname(__DIR__, 2) . '/models/DokumenPetaSnapshotModel.php';
                $mapSnapshot = DokumenPetaSnapshotModel::getSnapshot((int)$id, 'RENOPS', !empty($renops['id_renops']) ? (int)$renops['id_renops'] : null);

                // 1. TAMPILKAN PETA TERLEBIH DAHULU (16:9)
                $mapBlockTitle = '1. PETA RENCANA OPERASI HARI KE-' . $hariKe . ' (SEKTOR SEARCH AREA & POLA PENCARIAN - 16:9)';
                $mapRenderMode = 'IMAGE_ONLY';
                include __DIR__ . '/partials/print_map_snapshot_block.php';
                ?>

                <!-- 2. LALU TABEL PEMBAGIAN SRU -->
                <div style="font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-top: 6px; margin-bottom: 4px; color: #000; page-break-after: avoid; break-after: avoid;">
                    <?= !empty($mapSnapshot) ? '2. TABEL PEMBAGIAN SATUAN TUGAS (SEARCH AND RESCUE UNIT / SRU)' : 'TABEL PEMBAGIAN SATUAN TUGAS (SEARCH AND RESCUE UNIT / SRU)' ?>
                </div>
                <?php if (empty($srus)): ?>
                    <p class="text-[9pt] italic text-slate-600 p-2 border border-slate-300">Belum ada pembagian sektor SRU yang tercatat.</p>
                <?php else: ?>
                    <table class="w-full table-dinas text-[8.5pt] border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-center font-bold">
                                <th class="text-center" style="width: 5%;">NO</th>
                                <th style="width: 14%;">SRU</th>
                                <th style="width: 33%;">WILAYAH / SEKTOR PENCARIAN (KOORDINAT AREA)</th>
                                <th class="text-center" style="width: 11%;">LUAS</th>
                                <th style="width: 17%;">POLA PENCARIAN</th>
                                <th class="text-center" style="width: 10%;">SPACING</th>
                                <th class="text-center" style="width: 10%;">SAPUAN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($srus as $idx => $s): ?>
                                <tr>
                                    <td class="text-center font-bold"><?= $idx + 1 ?></td>
                                    <td class="font-bold uppercase"><?= e($s['nama_sru']) ?></td>
                                    <td class="font-mono text-[8pt] leading-tight">
                                        <?= nl2br(e($s['koordinat_area'])) ?>
                                    </td>
                                    <td class="text-center"><?= e($s['luas_area'] ?: '-') ?></td>
                                    <td><?= e($s['pola_pencarian'] ?: 'Parallel Track Search') ?></td>
                                    <td class="text-center"><?= e($s['track_spacing'] ?: '-') ?></td>
                                    <td class="text-center"><?= e($s['total_sapuan'] ?: '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <?php
                // 3. DAN TERAKHIR LEGENDA PETA (KETERANGAN KOMPONEN PETA)
                $mapLegendTitle = '3. LEGENDA & KETERANGAN KOMPONEN PETA RENCANA OPERASI (H.' . $hariKe . ')';
                $mapRenderMode = 'LEGEND_ONLY';
                include __DIR__ . '/partials/print_map_snapshot_block.php';
                ?>
            </div>

            <!-- BAGIAN III: ALUT DAN SARANA PENCARIAN -->
            <div class="mb-3 break-inside-avoid">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9.5pt] mb-1">
                    III. ALUT DAN PERALATAN SAR (PALSAR)
                </div>
                <?php if (!empty($alutList)): ?>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-[8.5pt]">
                        <?php foreach ($alutList as $a): ?>
                            <div>&bull; <span class="font-semibold"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></span> <?= e($a['nama_alut']) ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-[9pt] italic text-slate-600 text-justify"><?= nl2br(e($renops['alut_text'] ?: ($operasi['alut_palsar'] ?: '-'))) ?></p>
                <?php endif; ?>
            </div>

            <!-- BAGIAN IV: KOMPOSISI UNSUR TERLIBAT -->
            <div class="mb-3 break-inside-avoid">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9.5pt] mb-1">
                    IV. KOMPOSISI UNSUR SAR TERLIBAT
                </div>
                <?php if (!empty($unsurList)): ?>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-0.5 text-[8.5pt]">
                        <?php foreach ($unsurList as $u): ?>
                            <div>&bull; <?= e($u['nama_unsur']) ?> : <span class="font-semibold"><?= (int)($u['jumlah_personil'] ?? 1) ?> Orang</span></div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-[9pt] italic text-slate-600 text-justify"><?= nl2br(e($renops['unsur_text'] ?: ($operasi['unsur_terlibat'] ?: '-'))) ?></p>
                <?php endif; ?>
            </div>

            <!-- BAGIAN V: JADWAL & INSTRUKSI OPERASIONAL -->
            <div class="mb-4 break-inside-avoid">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9.5pt] mb-1">
                    V. JADWAL DAN INSTRUKSI OPERASIONAL
                </div>
                <table class="w-full table-borderless text-[8.5pt]">
                    <tr>
                        <td class="w-32 font-bold">&bull; <?= $jamBriefing ?> WITA</td>
                        <td class="w-2">:</td>
                        <td class="text-justify">Pelaksanaan Briefing Awal Tim SAR Gabungan, pembagian lembar tugas SRU, serta pengecekan kelaikan Alut dan APD.</td>
                    </tr>
                    <tr>
                        <td class="font-bold">&bull; <?= $jamMulai ?> WITA</td>
                        <td class="w-2">:</td>
                        <td class="text-justify">Seluruh SRU bergerak menuju sektor pencarian masing-masing dan melaksanakan penyisiran sesuai pola sapuan yang telah ditetapkan.</td>
                    </tr>
                    <tr>
                        <td class="font-bold">&bull; Setiap 60 Menit</td>
                        <td class="w-2">:</td>
                        <td class="text-justify">Komandan SRU wajib melaporkan posisi koordinat, kondisi cuaca riil di sektor, dan perkembangan pencarian kepada Posko SAR Kendari.</td>
                    </tr>
                    <tr>
                        <td class="font-bold">&bull; 17:30 WITA</td>
                        <td class="w-2">:</td>
                        <td class="text-justify">Pencarian hari ini dihentikan sementara dan seluruh SRU ditarik kembali ke pangkalan/Posko untuk pelaksanaan Debriefing dan evaluasi harian.</td>
                    </tr>
                </table>
            </div>

            <!-- LEGALISASI, TANDA TANGAN & QR CODE VERIFIKASI -->
            <div class="mt-4 break-inside-avoid border-t border-black pt-2">
                <div class="flex justify-end text-[9.5pt] mb-1">
                    <p>Dikeluarkan di : <span class="font-bold">Kendari</span><br>Pada Tanggal : <span class="font-bold"><?= $tglBriefing ?></span></p>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center text-[9pt]">
                    <!-- Kolom Kiri: Koordinator Lapangan / OSC -->
                    <div class="flex flex-col justify-between h-36">
                        <div>
                            <p class="font-bold uppercase">On Scene Commander (OSC)</p>
                            <p class="text-[8pt] text-slate-700">Komandan Lapangan KPP Kendari</p>
                        </div>
                        <div class="mt-auto">
                            <p class="font-bold underline uppercase">KOMANDAN TIM RESCUE</p>
                            <p class="text-[8pt]">KPP Kendari</p>
                        </div>
                    </div>

                    <!-- Kolom Tengah: QR Code Verifikasi Digital -->
                    <div class="flex flex-col items-center justify-center p-1">
                        <canvas id="qrCanvasRenops" class="w-24 h-24 border border-slate-300"></canvas>
                        <div class="text-[7.5pt] font-mono font-bold uppercase mt-1 leading-tight text-center">
                            TERVERIFIKASI SISTEM QR-SAR<br>
                            <span class="text-slate-500">KPP KENDARI &bull; <?= e($nomorRenops) ?></span><br>
                            <span class="text-slate-400 font-normal">Sah Secara Elektronik</span>
                        </div>
                    </div>

                    <!-- Kolom Kanan: SAR Mission Coordinator (SMC) -->
                    <div class="flex flex-col justify-between h-36">
                        <div>
                            <p class="font-bold uppercase">SAR Mission Coordinator (SMC)</p>
                            <p class="text-[8pt] text-slate-700"><?= strtoupper(e($defaultSmc['jabatan'] ?? 'KEPALA SEKSI OPERASI DAN SIAGA')) ?></p>
                        </div>
                        <div class="mt-auto">
                            <p class="font-bold underline uppercase"><?= strtoupper(e($defaultSmc['nama'] ?? 'AMIRUDDIN A.S., S.Sos.')) ?></p>
                            <p class="text-[8pt]"><?= e($defaultSmc['pangkat'] ?? 'PEMBINA (IV/a)') ?><?= !empty($defaultSmc['nip']) ? ' &bull; NIP. ' . e($defaultSmc['nip']) : '' ?></p>
                        </div>
                    </div>
                </div>
            </div>

        </article>
    </main>

    <!-- Hidden Raw WA text container for copying -->
    <div id="raw-wa-text-holder" class="hidden"><?= htmlspecialchars($fullWaText) ?></div>

    <!-- QR Code & PDF Exporter Scripts -->
    <script src="<?= BASE_URL ?>/assets/vendor/qrcode/qrcode.min.js?v=<?= APP_VERSION ?>"></script>
    <script src="<?= BASE_URL ?>/assets/vendor/html2pdf/html2pdf.bundle.min.js?v=<?= APP_VERSION ?>"></script>
    <script src="<?= BASE_URL ?>/assets/js/pdf_document_exporter.js?v=<?= APP_VERSION ?>_<?= @filemtime(dirname(__DIR__, 2) . '/assets/js/pdf_document_exporter.js') ?: time() ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const verifyUrl = <?= jsonSafe($verifyUrl) ?>;
        const canvas = document.getElementById('qrCanvasRenops');
        if (canvas && typeof QRCode !== 'undefined') {
            QRCode.toCanvas(canvas, verifyUrl, {
                width: 96,
                margin: 1,
                color: {
                    dark: '#000000',
                    light: '#ffffff'
                }
            }, function (error) {
                if (error) console.error("QR Code Error:", error);
            });
        }

        // Zoom controls
        let currentZoom = 100;
        const pageSheet = document.getElementById('official-renops-doc');
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
            alert("Naskah WhatsApp RENOPS berhasil disalin ke clipboard!");
        }).catch(err => {
            console.error("Gagal menyalin teks: ", err);
        });
    }
    </script>
</body>
</html>
