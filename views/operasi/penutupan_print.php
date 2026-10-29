<?php
/**
 * QR-SAR - Berita Acara Pengusulan Penutupan Operasi SAR
 * KPP Kendari - Basarnas
 * Standar Naskah Dinas Yuridis Resmi Basarnas Republik Indonesia
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

// Jika laporan penutupan belum dipilih tapi operasi ada, cari laporan jenis PENUTUPAN
if (!$laporan && $operasi) {
    $listLap = LaporanModel::getByOperasiId($idOps);
    foreach ($listLap as $l) {
        if (strtoupper($l['jenis_laporan']) === 'PENUTUPAN') {
            $laporan = $l;
            break;
        }
    }
    // Jika belum ada laporan penutupan resmi, gunakan laporan terakhir sebagai basis draft berita acara
    if (!$laporan && !empty($listLap)) {
        $laporan = end($listLap);
    }
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

$nomorBA = TemplateHelper::generateNomorBeritaAcara($operasi, $laporan ?? [], $satkerKode);

$waktuDebrief = $laporan['waktu_debrief'] ?? ($operasi['waktu_selesai'] ?? date('Y-m-d H:i:s'));
$tsDebrief = TemplateHelper::parseToTimestamp($waktuDebrief) ?? time();

$hariNama = TemplateHelper::getNamaHariIndo(date('l', $tsDebrief));
$tglDebrief = TemplateHelper::formatTanggalIndo($waktuDebrief);
$jamDebrief = TemplateHelper::formatJamWITA($waktuDebrief);

$tglKejadian = TemplateHelper::formatTanggalIndo($operasi['waktu_kejadian'] ?? '');
$jamKejadian = TemplateHelper::formatJamWITA($operasi['waktu_kejadian'] ?? '');

$statsKorban = TemplateHelper::hitungStatistikKorban($operasi['korban'] ?? [], (int)($operasi['jumlah_pob'] ?? 0));
$unsurList = !empty($laporan['detail_unsur']) ? $laporan['detail_unsur'] : ($operasi['detail_unsur'] ?? []);

$verifyUrl = DocVerifyHelper::generateVerifyUrl('penutupan', $operasi, $laporan);
$logoDataUri = getAppLogoDataUri();
$fullWaText = $laporan['full_text_wa'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Berita Acara Penutupan - <?= e($operasi['nama_operasi']) ?> - BASARNAS</title>
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
            line-height: 1.45 !important;
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
                <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $id ?>&tab=cetak" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    <span>Kembali ke Detail</span>
                </a>
                <span class="text-xs text-slate-500 hidden sm:inline">|</span>
                <span class="text-xs font-mono font-bold text-orange-400">OPS-#<?= $id ?></span>
                <span class="text-xs text-slate-300 font-semibold truncate max-w-[200px] sm:max-w-xs">Berita Acara Penutupan - <?= e($operasi['nama_operasi']) ?></span>
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

                <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= (int)$id ?>&include_base=1&capture_doc=PENUTUPAN" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-orange-950/60 hover:bg-orange-900/70 border border-orange-700/60 text-xs font-semibold text-orange-300 transition" title="Capture / Perbarui Area Peta 16:9 untuk Berita Acara Penutupan ini (1x)">
                    <span>Capture Peta 16:9</span>
                </a>

                <button type="button" id="btnDownloadPdf" data-pdf-filename="BERITA_ACARA_PENUTUPAN_OPS_<?= (int)$id ?>_<?= e($operasi['nama_operasi']) ?>" onclick="downloadOfficialPdf(null, this)" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-sm cursor-pointer" title="PDF berbentuk gambar (teks tidak bisa dicari). Untuk naskah resmi gunakan Cetak / Simpan PDF.">
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
        <article id="official-penutupan-doc" class="page-sheet text-black text-[10pt] leading-relaxed font-serif">
            
            <!-- KOP SURAT RESMI BASARNAS (partial bersama) -->
            <?php include __DIR__ . '/partials/print_kop.php'; ?>

            <!-- JUDUL DOKUMEN & NOMOR DINAS -->
            <div class="text-center my-4">
                <h2 class="doc-title">BERITA ACARA PENGUSULAN PENUTUPAN OPERASI PENCARIAN DAN PERTOLONGAN</h2>
                <div class="doc-number mt-1">Nomor : <?= e($nomorBA) ?></div>
            </div>

            <!-- PARAGRAF PEMBUKA -->
            <div class="text-justify mb-3 text-[9.5pt]">
                <p class="indent-8">
                    Pada hari ini <span class="font-bold"><?= e($hariNama) ?></span>, tanggal <span class="font-bold"><?= $tglDebrief ?></span>, pukul <span class="font-bold"><?= $jamDebrief ?> WITA</span>, bertempat di Posko Tim SAR Gabungan Kantor Pencarian dan Pertolongan Kendari, telah diselenggarakan rapat koordinasi dan debriefing penutupan Operasi Pencarian dan Pertolongan (SAR) terhadap kejadian musibah dengan rincian data sebagai berikut:
                </p>
            </div>

            <!-- TABEL DATA KEJADIAN & KORBAN -->
            <div class="mb-3 break-inside-avoid">
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
                        <td class="font-bold">4. Lokasi Kejadian (LKP)</td>
                        <td class="text-center">:</td>
                        <td class="font-mono font-semibold"><?= e(!empty($operasi['koordinat_lokasi']) ? $operasi['koordinat_lokasi'] : ($operasi['koordinat'] ?: '-')) ?></td>
                    </tr>
                    <tr>
                        <td class="font-bold">5. Jumlah Korban (POB)</td>
                        <td class="text-center">:</td>
                        <td class="font-bold">
                            Total <?= $statsKorban['total_pob'] ?> Orang &mdash; 
                            <span class="text-black font-semibold">Selamat: <?= $statsKorban['selamat'] ?> Orang</span>, 
                            <span class="text-black font-semibold">Meninggal Dunia: <?= $statsKorban['meninggal'] ?> Orang</span>,
                            <span class="text-black font-semibold">Dalam Pencarian: <?= $statsKorban['dalam_pencarian'] ?> Orang</span>,
                            <span class="text-black font-semibold">Hilang: <?= $statsKorban['hilang'] ?> Orang</span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- PERTIMBANGAN & KONSENSUS MUSYAWARAH -->
            <div class="mb-3 text-justify text-[9.5pt]" style="page-break-inside: auto; break-inside: auto;">
                <p class="font-bold uppercase text-[9.5pt] mb-1" style="page-break-after: avoid; break-after: avoid;">
                    HASIL EVALUASI DAN PERTIMBANGAN PENUTUPAN:
                </p>
                <div class="border border-slate-300 bg-slate-50 p-2.5 rounded text-[9pt] leading-normal mb-2 italic" style="page-break-inside: auto; break-inside: auto;">
                    <?= nl2br(e(($laporan['isi_laporan'] ?? '') ?: '-')) ?>
                </div>

                <?php
                require_once __DIR__ . '/../../models/DokumenPetaSnapshotModel.php';
                $mapSnapshot = DokumenPetaSnapshotModel::getSnapshot((int)$id, 'PENUTUPAN');
                $mapSnapshotSectionTitle = 'VISUALISASI PETA REKAPITULASI AREA PENCARIAN & PENEMUAN KORBAN (RASIO 16:9)';
                include __DIR__ . '/partials/print_map_snapshot_block.php';
                ?>

                <p class="indent-8 mt-2">
                    Berdasarkan hasil evaluasi pelaksanaan operasi pencarian dan pertolongan yang telah dilaksanakan oleh Tim SAR Gabungan, serta setelah dilakukan musyawarah mufakat bersama unsur Potensi SAR terkait dan perwakilan pihak keluarga korban, maka para pihak yang bertanda tangan di bawah ini secara bersama-sama menyatakan sepakat:
                </p>
            </div>

            <!-- BUTIR-BUTIR KESEPAKATAN -->
            <div class="mb-4 break-inside-avoid text-[9.5pt]">
                <ol class="list-decimal pl-6 space-y-1">
                    <li>
                        <span class="font-bold">Mengusulkan secara resmi kepada Kepala Badan Nasional Pencarian dan Pertolongan (Kabasarnas)</span> bahwa Operasi SAR terhadap <?= e($operasi['nama_operasi']) ?> dinyatakan <span class="font-bold uppercase">DITUTUP</span>.
                    </li>
                    <li>
                        Seluruh korban yang telah ditemukan telah diserahterimakan secara resmi kepada pihak berwenang / keluarga korban.
                    </li>
                    <li>
                        Seluruh unsur Potensi SAR dan Search and Rescue Unit (SRU) yang terlibat dikembalikan ke kesatuan dan instansi masing-masing disertai ucapan terima kasih dan apresiasi yang setinggi-tingginya atas dedikasi dan kerja sama dalam misi kemanusiaan ini.
                    </li>
                    <li>
                        Operasi SAR dapat dibuka kembali (<span class="italic font-semibold">Re-opened</span>) apabila di kemudian hari ditemukan informasi atau tanda-tanda baru yang dapat dipertanggungjawabkan mengenai keberadaan korban.
                    </li>
                </ol>
            </div>

            <p class="text-[12pt] mb-3 narrative-text text-justify">
                Demikian Berita Acara ini dibuat dan ditandatangani dengan sebenar-benarnya tanpa ada paksaan dari pihak manapun, untuk dipergunakan sebagaimana mestinya.
            </p>

            <!-- LEGALISASI & TANDA TANGAN MULTI-PIHAK (ANTI-TERPOTONG) -->
            <div class="signature-block mt-6 break-inside-avoid border-t border-black pt-3">
                <div class="flex justify-end text-[10.5pt] mb-2 font-serif">
                    <div class="text-left leading-tight">
                        <div>Dibuat di : <span class="font-bold">Kendari</span></div>
                        <div>Pada Tanggal : <span class="font-bold"><?= $tglDebrief ?></span></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 text-left text-[11pt] font-serif">
                    <!-- Kolom Kiri: Pihak Keluarga Korban (Align Left) -->
                    <div class="flex flex-col justify-between min-h-[140px] text-left">
                        <div>
                            <p class="font-bold uppercase text-[10.5pt] leading-tight m-0">PIHAK PERTAMA,</p>
                            <p class="text-[9.5pt] text-black leading-tight m-0 mt-0.5">Perwakilan Keluarga Korban</p>
                        </div>
                        <div class="mt-auto pt-6 leading-tight">
                            <p class="font-bold underline uppercase text-[10.5pt] m-0">( ............................................ )</p>
                            <p class="text-[9pt] text-black m-0 mt-0.5">Tanda Tangan & Nama Jelas</p>
                        </div>
                    </div>

                    <!-- Kolom Tengah: Saksi Unsur Potensi SAR (Align Left) -->
                    <div class="flex flex-col justify-between min-h-[140px] text-left">
                        <div>
                            <p class="font-bold uppercase text-[10.5pt] leading-tight m-0">PIHAK KEDUA,</p>
                            <p class="text-[9.5pt] text-black leading-tight m-0 mt-0.5">Saksi / Komandan Potensi SAR</p>
                        </div>
                        <div class="mt-auto pt-6 leading-tight">
                            <p class="font-bold underline uppercase text-[10.5pt] m-0">( ............................................ )</p>
                            <p class="text-[9pt] text-black m-0 mt-0.5">Instansi / Potensi Terkait</p>
                        </div>
                    </div>

                    <!-- Kolom Kanan: Search Mission Coordinator (SMC) (Align Left) -->
                    <div class="flex flex-col justify-between min-h-[140px] text-left">
                        <div>
                            <p class="font-bold uppercase text-[10.5pt] leading-tight m-0">MENGETAHUI / MENGESAHKAN:</p>
                            <p class="text-[9.5pt] text-black leading-tight m-0 mt-0.5">SAR Mission Coordinator (SMC)</p>
                        </div>
                        <div class="mt-auto pt-6 leading-tight">
                            <p class="font-bold underline uppercase text-[10.5pt] m-0"><?= strtoupper(e($defaultSmc['nama'] ?? 'AMIRUDDIN A.S., S.Sos.')) ?></p>
                            <p class="text-[9pt] m-0 mt-0.5"><?= e($defaultSmc['pangkat'] ?? 'PEMBINA (IV/a)') ?><?= !empty($defaultSmc['nip']) ? '<br>NIP. ' . e($defaultSmc['nip']) : '' ?></p>
                        </div>
                    </div>
                </div>

                <!-- QR CODE VERIFIKASI RESMI DENGAN LOGO HD DI TENGAH -->
                <div class="flex items-center justify-center gap-3 mt-4 pt-3 border-t border-dashed border-slate-300">
                    <div class="relative flex items-center justify-center">
                        <canvas id="qrCanvasPenutupan" class="w-20 h-20 border border-slate-300"></canvas>
                        <img id="qrImgPenutupan" class="w-20 h-20 border border-slate-300 hidden" alt="QR Code Verifikasi Resmi">
                    </div>
                    <div class="text-[8.5pt] font-mono leading-tight text-left">
                        <span class="font-bold text-black uppercase">BERKAS RESMI DINAS BASARNAS REPUBLIK INDONESIA</span><br>
                        <span class="text-black">Terdaftar pada Sistem Validasi QR-SAR KPP Kendari &bull; Nomor: <?= e($nomorBA) ?></span><br>
                        <span class="text-black italic">Dokumen ini sah dan memiliki kekuatan hukum administrasi operasional.</span>
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
        const canvas = document.getElementById('qrCanvasPenutupan');
        const imgEl = document.getElementById('qrImgPenutupan');

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
        const pageSheet = document.getElementById('official-penutupan-doc');
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
    </script>
</body>
</html>
