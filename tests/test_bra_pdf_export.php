<?php
/**
 * QR-SAR Basarnas Kendari - Pengujian Ekspor PDF Vektor BRA & Konsistensi Cetak
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testPassed = 0;
$testTotal = 0;

if (!function_exists('assertCondition')) {
    function assertCondition(bool $cond, string $message): void {
        global $testPassed, $testTotal;
        $testTotal++;
        if ($cond) {
            $testPassed++;
            echo " [PASS] {$message}\n";
        } else {
            echo " [FAIL] {$message}\n";
        }
    }
}

echo "=== TEST BRA PDF VECTOR EXPORT & PRINT CONSISTENCY ===\n";

// 1. Verifikasi ketersediaan kelas BraPdfService
assertCondition(class_exists('BraPdfService'), 'Class BraPdfService harus tersedia');

if (class_exists('BraPdfService')) {
    // 2. Verifikasi findChromeBinary mendeteksi biner Chrome yang valid di server
    $chromeBin = BraPdfService::findChromeBinary();
    assertCondition($chromeBin !== null && is_executable($chromeBin), 'BraPdfService::findChromeBinary menemukan biner Chrome yang dapat dieksekusi: ' . ($chromeBin ?? 'NULL'));

    // 3. Verifikasi prepareHtmlForLocalRender mengubah path lokal menjadi file://
    $dummyHtml = '<html><head></head><body><img src="' . BASE_URL . '/assets/images/logo_basarnas.svg"><div class="print:hidden">Toolbar</div><div id="official-bra-document">Isi Dokumen BRA</div></body></html>';
    $prepared = BraPdfService::prepareHtmlForLocalRender($dummyHtml);
    assertCondition(str_contains($prepared, 'file://'), 'BraPdfService::prepareHtmlForLocalRender mengubah tautan aset menjadi file:// lokal');
    assertCondition(str_contains($prepared, 'print\\:hidden') || str_contains($prepared, 'display: none'), 'BraPdfService::prepareHtmlForLocalRender menginjeksi aturan print override');

    // 4. Verifikasi renderHtmlToPdf menghasilkan biner %PDF-
    $pdfBytes = BraPdfService::renderHtmlToPdf($dummyHtml);
    assertCondition(is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-') && strlen($pdfBytes) > 500, 'BraPdfService::renderHtmlToPdf menghasilkan biner PDF vektor valid via Chrome Headless');
}
