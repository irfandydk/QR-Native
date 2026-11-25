<?php
/**
 * QR-SAR - Service Generator PDF Vektor Dokumen Rencana Operasi SAR (RENOPS)
 * Mendelegasikan eksekusi Chrome Headless ke ChromePdfService (dengan timeout & kunci konkurensi flock).
 */

declare(strict_types=1);

require_once __DIR__ . '/ChromePdfService.php';

class RenopsPdfService {
    private static function printOverrideStyle(): string {
        return '<style>'
            . 'html, body { background: #ffffff !important; }'
            . '.no-print, .no-print * { display: none !important; }'
            . '.doc-scroll { overflow: visible !important; padding: 0 !important; }'
            . '.page-sheet.renops-smeac { zoom: 1 !important; box-shadow: none !important; }'
            . '</style>';
    }

    public static function findChromeBinary(): ?string {
        return ChromePdfService::findChromeBinary();
    }

    public static function prepareHtmlForLocalRender(string $html): string {
        return ChromePdfService::prepareHtmlForLocalRender($html, self::printOverrideStyle());
    }

    public static function renderHtmlToPdf(string $html): ?string {
        return ChromePdfService::renderHtmlToPdf($html, self::printOverrideStyle(), 'qrsar_renops_');
    }
}
