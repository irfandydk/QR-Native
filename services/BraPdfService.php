<?php
/**
 * QR-SAR - Service Generator PDF Vektor Dokumen Berita Radio SAR (BRA)
 * Mendelegasikan eksekusi Chrome Headless ke ChromePdfService (dengan timeout & kunci konkurensi flock).
 */

declare(strict_types=1);

require_once __DIR__ . '/ChromePdfService.php';

class BraPdfService {
    private static function printOverrideStyle(): string {
        return '<style>'
            . '@page { size: A4 portrait; margin: 5mm 15mm 15mm 15mm; }'
            . 'html, body { background: #ffffff !important; color: #000000 !important; margin: 0 !important; padding: 0 !important; }'
            . '.print\:hidden, [class*="print:hidden"], .no-print, header, footer, .navbar, .top-navbar, .sidebar, #mobile-menu-btn { display: none !important; }'
            . '.app-container, .main-content, .page-body { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; display: block !important; }'
            . '#official-bra-document, .bra-official-paper { box-shadow: none !important; border: none !important; margin: 0 auto !important; width: 180mm !important; max-width: 180mm !important; background: #ffffff !important; color: #000000 !important; }'
            . '</style>';
    }

    public static function findChromeBinary(): ?string {
        return ChromePdfService::findChromeBinary();
    }

    public static function prepareHtmlForLocalRender(string $html): string {
        return ChromePdfService::prepareHtmlForLocalRender($html, self::printOverrideStyle());
    }

    public static function renderHtmlToPdf(string $html): ?string {
        return ChromePdfService::renderHtmlToPdf($html, self::printOverrideStyle(), 'qrsar_bra_');
    }
}
