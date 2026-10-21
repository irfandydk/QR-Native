<?php
/**
 * QR-SAR - Automated Integration Test: Document Verification Token, HD QR Logo & PDF Anti-Cut
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

echo "=========================================================================\n";
echo "  PENGUJIAN SISTEM VERIFIKASI DOKUMEN OPERASIONAL, QR LOGO & PDF EXPORT  \n";
echo "  KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS       \n";
echo "=========================================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$desc}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

// 1. Pengujian Autoloading & Struktur DocVerifyHelper
echo "--- 1. Pengujian Class & Autoloading DocVerifyHelper ---\n";
assertTest("Class DocVerifyHelper tersedia di autoloader", class_exists('DocVerifyHelper'));
$types = DocVerifyHelper::supportedTypes();
assertTest("Mendukung 4 tipe dokumen resmi", count($types) === 4 && in_array('laporan_awal', $types) && in_array('renops', $types) && in_array('sitrep', $types) && in_array('penutupan', $types));

// 2. Mock Data Operasi
$mockOperasi = [
    'id_operasi' => 101,
    'nama_operasi' => 'KMM ORANG TENGGELAM DI TELUK KENDARI',
    'waktu_lapor' => '2026-10-21 08:30:00',
    'waktu_kejadian' => '2026-10-21 07:45:00',
    'status' => 'OPEN',
    'korban' => [],
    'jumlah_pob' => 1
];

$mockRenops = [
    'id_renops' => 202,
    'id_operasi' => 101,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'waktu_briefing' => '2026-10-21 06:30:00'
];

$mockSitrep = [
    'id_laporan' => 303,
    'id_operasi' => 101,
    'hari_ke' => 'H.1',
    'waktu_laporan' => '2026-10-21 17:30:00',
    'waktu_debrief' => '2026-10-21 17:30:00',
    'jenis_laporan' => 'PERKEMBANGAN'
];

$mockPenutupan = [
    'id_laporan' => 304,
    'id_operasi' => 101,
    'hari_ke' => 'H.2',
    'waktu_laporan' => '2026-10-22 14:00:00',
    'waktu_debrief' => '2026-10-22 14:00:00',
    'jenis_laporan' => 'PENUTUPAN'
];

// 3. Pengujian Kriptografis Token HMAC Dokumen
echo "\n--- 2. Pengujian Kriptografis Token HMAC Dokumen Operasi ---\n";

// Laporan Awal
$tokLapAwal = DocVerifyHelper::generateToken('laporan_awal', $mockOperasi);
assertTest("Token Laporan Awal berformat 16 char hex uppercase", strlen($tokLapAwal) === 16 && ctype_xdigit($tokLapAwal) && strtoupper($tokLapAwal) === $tokLapAwal);
assertTest("Token Laporan Awal valid diverifikasi", DocVerifyHelper::verifyToken('laporan_awal', $mockOperasi, null, $tokLapAwal));
assertTest("Token Laporan Awal salah ditolak", !DocVerifyHelper::verifyToken('laporan_awal', $mockOperasi, null, 'INVALIDTOKEN1234'));

// RENOPS
$tokRenops = DocVerifyHelper::generateToken('renops', $mockOperasi, $mockRenops);
assertTest("Token RENOPS berformat 16 char hex uppercase", strlen($tokRenops) === 16 && ctype_xdigit($tokRenops));
assertTest("Token RENOPS valid diverifikasi", DocVerifyHelper::verifyToken('renops', $mockOperasi, $mockRenops, $tokRenops));
assertTest("Token RENOPS beda ref_id ditolak", !DocVerifyHelper::verifyToken('renops', $mockOperasi, ['id_renops' => 999], $tokRenops));

// SITREP
$tokSitrep = DocVerifyHelper::generateToken('sitrep', $mockOperasi, $mockSitrep);
assertTest("Token SITREP berformat 16 char hex uppercase", strlen($tokSitrep) === 16 && ctype_xdigit($tokSitrep));
assertTest("Token SITREP valid diverifikasi", DocVerifyHelper::verifyToken('sitrep', $mockOperasi, $mockSitrep, $tokSitrep));

// Penutupan
$tokPenutupan = DocVerifyHelper::generateToken('penutupan', $mockOperasi, $mockPenutupan);
assertTest("Token Penutupan berformat 16 char hex uppercase", strlen($tokPenutupan) === 16 && ctype_xdigit($tokPenutupan));
assertTest("Token Penutupan valid diverifikasi", DocVerifyHelper::verifyToken('penutupan', $mockOperasi, $mockPenutupan, $tokPenutupan));

// 4. Pengujian URL Verifikasi Publik
echo "\n--- 3. Pengujian URL Verifikasi Publik Resmi ---\n";
$urlLapAwal = DocVerifyHelper::generateVerifyUrl('laporan_awal', $mockOperasi);
assertTest("URL Laporan Awal mengandung action=verify dan token", str_contains($urlLapAwal, 'action=verify') && str_contains($urlLapAwal, 'type=laporan_awal') && str_contains($urlLapAwal, "token={$tokLapAwal}"));

$urlRenops = DocVerifyHelper::generateVerifyUrl('renops', $mockOperasi, $mockRenops);
assertTest("URL RENOPS mengandung ref=202 dan token", str_contains($urlRenops, 'ref=202') && str_contains($urlRenops, "token={$tokRenops}"));

$urlSitrep = DocVerifyHelper::generateVerifyUrl('sitrep', $mockOperasi, $mockSitrep);
assertTest("URL SITREP mengandung ref=303 dan token", str_contains($urlSitrep, 'ref=303') && str_contains($urlSitrep, "token={$tokSitrep}"));

$urlPenutupan = DocVerifyHelper::generateVerifyUrl('penutupan', $mockOperasi, $mockPenutupan);
assertTest("URL Penutupan mengandung ref=304 dan token", str_contains($urlPenutupan, 'ref=304') && str_contains($urlPenutupan, "token={$tokPenutupan}"));

// 5. Pengujian Data URI Logo Aplikasi
echo "\n--- 4. Pengujian Logo HD Data URI ---\n";
$logoDataUri = getAppLogoDataUri();
assertTest("Logo Data URI non-empty", !empty($logoDataUri));
assertTest("Logo Data URI berformat data:image/", str_starts_with($logoDataUri, 'data:image/'));

// 6. Pengujian Integritas File Cetak Dokumen Operasi
echo "\n--- 5. Pengujian Integritas 4 File Cetak Dokumen Operasional ---\n";
$viewsDir = dirname(__DIR__) . '/views/operasi';

$filesToCheck = [
    'Laporan Awal' => $viewsDir . '/laporan_awal_print.php',
    'RENOPS' => $viewsDir . '/renops_print.php',
    'SITREP' => $viewsDir . '/sitrep_print.php',
    'Penutupan' => $viewsDir . '/penutupan_print.php'
];

foreach ($filesToCheck as $name => $filePath) {
    assertTest("File {$name} ada di filesystem", file_exists($filePath));
    $content = file_get_contents($filePath);
    
    assertTest("{$name} memiliki class .signature-block", str_contains($content, 'signature-block'));
    assertTest("{$name} memanggil DocVerifyHelper::generateVerifyUrl", str_contains($content, 'DocVerifyHelper::generateVerifyUrl'));
    assertTest("{$name} memanggil getAppLogoDataUri()", str_contains($content, 'getAppLogoDataUri()'));
    assertTest("{$name} memiliki fungsi drawCenterEmblem", str_contains($content, 'drawCenterEmblem'));
    assertTest("{$name} memiliki font-family Times New Roman", str_contains($content, "'Times New Roman', Times, serif"));
    assertTest("{$name} memiliki font-size 12pt", str_contains($content, '12pt'));
    assertTest("{$name} memiliki padding bawah 25mm", str_contains($content, '25mm'));
}

// 7. Pengujian Exporter PDF
echo "\n--- 6. Pengujian Engine PDF Exporter (pdf_document_exporter.js) ---\n";
$exporterPath = dirname(__DIR__) . '/assets/js/pdf_document_exporter.js';
assertTest("File pdf_document_exporter.js ada", file_exists($exporterPath));
$exporterContent = file_get_contents($exporterPath);

assertTest("PDF Exporter memiliki window.__pdfForbiddenZones", str_contains($exporterContent, '__pdfForbiddenZones'));
assertTest("PDF Exporter memiliki forbiddenZonesPx collision check", str_contains($exporterContent, 'forbiddenZonesPx'));
assertTest("PDF Exporter melindungi blok tanda tangan dari pemotongan", str_contains($exporterContent, 'insideForbidden.top - currentY'));
assertTest("PDF Exporter menyuntikkan font Times New Roman", str_contains($exporterContent, "Times New Roman"));
assertTest("PDF Exporter menyuntikkan ukuran font 12pt", str_contains($exporterContent, "12pt"));
assertTest("PDF Exporter menyuntikkan alignment text-justify", str_contains($exporterContent, "text-justify: inter-word"));
assertTest("PDF Exporter menyuntikkan padding bawah 25mm", str_contains($exporterContent, "25mm"));

echo "\n=========================================================================\n";
echo "  HASIL AKHIR PENGUJIAN: {$passCount} LULUS (PASS), {$failCount} GAGAL (FAIL)\n";
echo "=========================================================================\n\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
