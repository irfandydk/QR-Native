<?php
/**
 * QR-SAR - Automated Integration Test: Document Verification Token, HD QR Logo & PDF Anti-Cut
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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
// Dokumen cetak Laporan Awal, LAPBANG & Penutupan dihapus: QR lama hanya untuk RENOPS.
assertTest("Hanya tipe RENOPS yang dikenali", DocVerifyHelper::normalizeType('renops') === 'renops'
    && DocVerifyHelper::normalizeType('laporan_awal') === null && DocVerifyHelper::normalizeType('sitrep') === null && DocVerifyHelper::normalizeType('penutupan') === null);

// 2. Mock Data Operasi
$mockOperasi = [
    'id_operasi' => 101,
    'nama_operasi' => 'KMM ORANG TENGGELAM DI TELUK KENDARI',
    'waktu_lapor' => '2026-10-21 08:30:00',
    'status' => 'OPEN',
];
$mockRenops = [
    'id_renops' => 202,
    'id_operasi' => 101,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'waktu_briefing' => '2026-10-21 06:30:00'
];

// 3. Pengujian Kriptografis Token HMAC QR RENOPS lama
echo "\n--- 2. Pengujian Token HMAC QR RENOPS lama ---\n";
$tokRenops = DocVerifyHelper::generateToken($mockOperasi, $mockRenops);
assertTest("Token RENOPS berformat 16 char hex uppercase", strlen($tokRenops) === 16 && ctype_xdigit($tokRenops) && strtoupper($tokRenops) === $tokRenops);
assertTest("Token RENOPS valid diverifikasi", DocVerifyHelper::verifyToken($mockOperasi, $mockRenops, $tokRenops));
assertTest("Token RENOPS beda ref_id ditolak", !DocVerifyHelper::verifyToken($mockOperasi, ['id_renops' => 999], $tokRenops));
assertTest("Token salah ditolak", !DocVerifyHelper::verifyToken($mockOperasi, $mockRenops, 'INVALIDTOKEN1234'));
// Rumus token tidak boleh berubah: QR RENOPS lama yang sudah tercetak tetap sah.
$expectRumus = strtoupper(substr(hash_hmac('sha256', 'BASARNAS_OPS_DOC:renops:101:202:2026-10-21:SMC UJI:KMM ORANG TENGGELAM DI TELUK KENDARI', APP_KEY), 0, 16));
assertTest("Rumus token QR RENOPS lama tetap sama", DocVerifyHelper::generateToken($mockOperasi, $mockRenops, 'SMC Uji') === $expectRumus);
assertTest("Token legacy RENOPS tetap sah", DocVerifyHelper::verifyToken($mockOperasi, $mockRenops,
    strtoupper(substr(hash_hmac('sha256', 'BASARNAS_OPS_DOC:renops:101:202', APP_KEY), 0, 16))));
assertTest("URL cetak verifikasi menuju print_renops", DocVerifyHelper::getPrintUrl(101, 202) === BASE_URL . '/operasi.php?action=print_renops&id=101&id_renops=202');

// 5. Pengujian Data URI Logo Aplikasi
echo "\n--- 4. Pengujian Logo HD Data URI ---\n";
$logoDataUri = getAppLogoDataUri();
assertTest("Logo Data URI non-empty", !empty($logoDataUri));
assertTest("Logo Data URI berformat data:image/", str_starts_with($logoDataUri, 'data:image/'));

// 6. Dokumen cetak lama sudah dihapus
echo "\n--- 5. Pengujian Berkas Cetak Dokumen ---\n";
$viewsDir = dirname(__DIR__) . '/views/operasi';
foreach (['laporan_awal_print.php', 'sitrep_print.php', 'penutupan_print.php'] as $f) {
    assertTest("Berkas cetak lama {$f} sudah dihapus", !file_exists($viewsDir . '/' . $f));
}

// RENOPS format SMEAC (A4 landscape): halaman cetak + partial dokumen, tanpa aturan tipografi portrait
$renopsSmeac = file_get_contents($viewsDir . '/renops_smeac_print.php') . file_get_contents($viewsDir . '/partials/renops_smeac_doc.php');
assertTest("RENOPS (SMEAC) memiliki .signature-block, getAppLogoDataUri() & drawCenterEmblem",
    str_contains($renopsSmeac, 'signature-block') && str_contains($renopsSmeac, 'getAppLogoDataUri()') && str_contains($renopsSmeac, 'drawCenterEmblem'));

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
