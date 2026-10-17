<?php
/**
 * QR-SAR - Lembar Dokumen Resmi Laporan Perkembangan (SITREP)
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
        setFlash('error', 'Dokumen Laporan Perkembangan (SITREP) tidak ditemukan.');
        header("Location: " . BASE_URL . "/operasi.php");
        exit;
    }
    http_response_code(404);
    exit('Dokumen Laporan Perkembangan (SITREP) tidak ditemukan.');
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

$verifyUrl = BASE_URL . "/operasi_detail.php?id={$id}&tab=sitrep";
$fullWaText = $laporan['full_text_wa'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SITREP <?= e($hariKe) ?> - <?= e($operasi['nama_operasi']) ?> - BASARNAS</title>
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
                font-size: 9.5pt !important;
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
            color: #000000;
        }
        .page-sheet {
            background-color: #ffffff;
            width: 210mm;
            min-height: 297mm;
            margin: 1rem auto;
            padding: 15mm 18mm;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.3);
            border-radius: 2px;
            box-sizing: border-box;
            transition: transform 0.2s ease-in-out;
        }
        .table-borderless td {
            padding: 2px 4px;
            vertical-align: top;
        }
        .table-dinas th, .table-dinas td {
            border: 1px solid #000000;
            padding: 4px 6px;
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
                <span class="text-xs text-slate-300 font-semibold truncate max-w-[200px] sm:max-w-xs"><?= e($operasi['nama_operasi']) ?> (SITREP <?= e($hariKe) ?>)</span>
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

                <button type="button" onclick="copyWaText()" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition" title="Salin Naskah WhatsApp SITREP">
                    <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <span class="hidden sm:inline">Salin WA</span>
                </button>

                <button type="button" onclick="window.print()" class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white text-xs font-bold transition shadow-sm cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak SITREP</span>
                </button>
            </div>
        </div>
    </header>

    <!-- OFFICIAL BASARNAS PAPER DOCUMENT SHEET -->
    <main class="flex-1 flex justify-center py-4 px-2">
        <article id="official-sitrep-doc" class="page-sheet text-black text-[9.5pt] leading-normal font-sans">
            
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
                    LAPORAN PERKEMBANGAN (SITREP) OPERASI SAR
                </h2>
                <p class="text-[10pt] font-bold uppercase mt-1 m-0">
                    HARI KE-<?= e($hariKe) ?>
                </p>
                <p class="text-[9.5pt] font-mono font-bold text-slate-800 mt-0.5 m-0">
                    Nomor : <?= e($nomorSitrep) ?>
                </p>
            </div>

            <!-- BAGIAN I: DATA OPERASI & SITUASI -->
            <div class="mb-3 break-inside-avoid">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9pt] mb-1">
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
                        <td><?= e($laporan['cuaca'] ?: ($operasi['cuaca'] ?: 'Cerah Berawan')) ?> (Gelombang: <?= e($operasi['gelombang'] ?: '0.5 - 1.25 M') ?>, Angin: <?= e($operasi['angin'] ?: 'Barat Daya 5-15 Kts') ?>)</td>
                    </tr>
                </table>
            </div>

            <!-- BAGIAN II: DATA KORBAN & STATISTIK -->
            <div class="mb-3 break-inside-avoid">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9pt] mb-1 flex justify-between items-center">
                    <span>II. DATA KORBAN (POB: <?= $statsKorban['total_pob'] ?> ORANG)</span>
                    <span class="text-[8pt] font-mono">
                        Selamat: <span class="text-emerald-700 font-bold"><?= $statsKorban['selamat'] ?></span> | 
                        MD: <span class="text-rose-700 font-bold"><?= $statsKorban['meninggal'] ?></span> | 
                        DP: <span class="text-amber-700 font-bold"><?= $statsKorban['dalam_pencarian'] ?></span>
                    </span>
                </div>

                <?php if (empty($korbanList)): ?>
                    <p class="text-[8.5pt] italic text-slate-600">Nihil data identitas korban spesifik terdaftar.</p>
                <?php else: ?>
                    <table class="w-full table-dinas text-[8pt] border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-center font-bold">
                                <th class="w-8">NO</th>
                                <th>NAMA LENGKAP KORBAN</th>
                                <th class="w-16">UMUR</th>
                                <th class="w-16">L/P</th>
                                <th>ALAMAT / ASAL</th>
                                <th class="w-28">STATUS TERAKHIR</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($korbanList as $idx => $k): 
                                $stLabel = TemplateHelper::getLabelStatusKorban($k['status_korban'] ?? '');
                                $stUpper = strtoupper($k['status_korban'] ?? '');
                                $colorClass = $stUpper === 'SELAMAT' ? 'text-emerald-800 font-bold' : ($stUpper === 'MENINGGAL' ? 'text-rose-800 font-bold' : 'text-amber-800 font-bold');
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
            <div class="mb-3 break-inside-avoid">
                <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[9pt] mb-1">
                    III. HASIL PELAKSANAAN OPERASI HARI INI
                </div>
                <div class="border border-slate-300 p-2 text-[8.5pt] leading-relaxed bg-slate-50">
                    <?= nl2br(e($laporan['isi_laporan'] ?: 'Pencarian telah dilaksanakan oleh Tim SAR Gabungan sesuai Rencana Operasi.')) ?>
                </div>
            </div>

            <!-- BAGIAN IV: ALUT DAN UNSUR TERLIBAT -->
            <div class="mb-3 break-inside-avoid grid grid-cols-2 gap-3 text-[8.5pt]">
                <div>
                    <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[8.5pt] mb-1">
                        IV. ALUT & PALSAR DIGUNAKAN
                    </div>
                    <?php if (!empty($alutList)): ?>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <?php foreach ($alutList as $a): ?>
                                <li><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?> <?= e($a['nama_alut']) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="italic text-slate-600"><?= nl2br(e($laporan['alut_text'] ?: ($operasi['alut_palsar'] ?: '-'))) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="bg-slate-100 border-l-4 border-orange-600 px-2 py-0.5 font-bold uppercase text-[8.5pt] mb-1">
                        V. UNSUR SAR TERLIBAT
                    </div>
                    <?php if (!empty($unsurList)): ?>
                        <ul class="list-disc pl-4 space-y-0.5">
                            <?php foreach ($unsurList as $u): ?>
                                <li><?= e($u['nama_unsur']) ?> (<?= (int)($u['jumlah_personil'] ?? 1) ?> Org)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="italic text-slate-600"><?= nl2br(e($laporan['unsur_text'] ?: ($operasi['unsur_terlibat'] ?: '-'))) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- LEGALISASI, TANDA TANGAN & QR CODE VERIFIKASI -->
            <div class="mt-4 break-inside-avoid border-t border-black pt-2">
                <div class="flex justify-end text-[9pt] mb-1">
                    <p>Dikeluarkan di : <span class="font-bold">Kendari</span><br>Pada Tanggal : <span class="font-bold"><?= $tglDebrief ?></span></p>
                </div>

                <div class="grid grid-cols-3 gap-2 text-center text-[8.5pt]">
                    <!-- Kolom Kiri: Koordinator Lapangan / OSC -->
                    <div class="flex flex-col justify-between h-36">
                        <div>
                            <p class="font-bold uppercase">On Scene Commander (OSC)</p>
                            <p class="text-[7.5pt] text-slate-700">Komandan Lapangan KPP Kendari</p>
                        </div>
                        <div class="mt-auto">
                            <p class="font-bold underline uppercase">KOMANDAN TIM RESCUE</p>
                            <p class="text-[7.5pt]">KPP Kendari</p>
                        </div>
                    </div>

                    <!-- Kolom Tengah: QR Code Verifikasi Digital -->
                    <div class="flex flex-col items-center justify-center p-1">
                        <canvas id="qrCanvasSitrep" class="w-20 h-20 border border-slate-300"></canvas>
                        <div class="text-[7pt] font-mono font-bold uppercase mt-1 leading-tight text-center">
                            TERVERIFIKASI SISTEM QR-SAR<br>
                            <span class="text-slate-500">KPP KENDARI &bull; <?= e($nomorSitrep) ?></span><br>
                            <span class="text-slate-400 font-normal">Sah Secara Elektronik</span>
                        </div>
                    </div>

                    <!-- Kolom Kanan: SAR Mission Coordinator (SMC) -->
                    <div class="flex flex-col justify-between h-36">
                        <div>
                            <p class="font-bold uppercase">SAR Mission Coordinator (SMC)</p>
                            <p class="text-[7.5pt] text-slate-700"><?= strtoupper(e($defaultSmc['jabatan'] ?? 'KEPALA SEKSI OPERASI DAN SIAGA')) ?></p>
                        </div>
                        <div class="mt-auto">
                            <p class="font-bold underline uppercase"><?= strtoupper(e($defaultSmc['nama'] ?? 'AMIRUDDIN A.S., S.Sos.')) ?></p>
                            <p class="text-[7.5pt]"><?= e($defaultSmc['pangkat'] ?? 'PEMBINA (IV/a)') ?><?= !empty($defaultSmc['nip']) ? ' &bull; NIP. ' . e($defaultSmc['nip']) : '' ?></p>
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
        const canvas = document.getElementById('qrCanvasSitrep');
        if (canvas && typeof QRCode !== 'undefined') {
            QRCode.toCanvas(canvas, verifyUrl, {
                width: 80,
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
            alert("Naskah WhatsApp SITREP berhasil disalin ke clipboard!");
        }).catch(err => {
            console.error("Gagal menyalin teks: ", err);
        });
    }
    </script>
</body>
</html>
