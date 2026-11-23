<?php
/**
 * QR-SAR - Automated Test Suite: Fase 10 (Verifikasi Modul JS Aktif & Pembersihan Dead JS)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/app.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertCondition(string $description, bool $condition): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "\n====================================================================\n";
echo "   UJI OTOMATIS FASE 10: MODUL JS AKTIF & PEMBERSIHAN DEAD SCRIPTS  \n";
echo "====================================================================\n";

$rootDir = dirname(__DIR__);

// --- 1. MEMERIKSA MODUL JS GIS AKTIF (SSOT) ---
echo "\n--- 1. MEMERIKSA MODUL JS GIS AKTIF (SSOT) ---\n";
$activeGisModules = [
    'assets/js/gis/geodesy.js',
    'assets/js/gis/markers.js',
    'assets/js/gis/patterns.js',
    'assets/js/gis/naming.js',
    'assets/js/gis/export.js',
    'assets/js/gis/kml_importer.js',
    'assets/js/gis/tile_fallback.js',
    'assets/js/gis_master.js',
];

$gisViewContent = (string)file_get_contents($rootDir . '/views/gis.php');
foreach ($activeGisModules as $relPath) {
    assertCondition("Modul aktif {$relPath} tersedia dan dimuat di views/gis.php",
        file_exists($rootDir . '/' . $relPath) && str_contains($gisViewContent, $relPath)
    );
}

// --- 2. MEMASTIKAN 8 FILE JS ZOMBIE MATI TELAH DIHAPUS BERSIH ---
echo "\n--- 2. MEMASTIKAN FILE JS ZOMBIE MATI TELAH DIHAPUS BERSIH ---\n";
$removedDeadScripts = [
    'assets/js/gis/layers.js',
    'assets/js/gis/ui_search.js',
    'assets/js/gis/ui_points.js',
    'assets/js/gis/ui_polygons.js',
    'assets/js/gis/ui_patterns.js',
    'assets/js/gis/ui_routes.js',
    'assets/js/pages/operasi_form_map.js',
    'assets/js/bra.js',
];

$opsFormContent = (string)file_get_contents($rootDir . '/views/operasi/form.php');
$footerContent = (string)file_get_contents($rootDir . '/views/layout/footer.php');
foreach ($removedDeadScripts as $relPath) {
    assertCondition("Dead script {$relPath} telah dihapus dari disk", !file_exists($rootDir . '/' . $relPath));
    assertCondition("Dead script {$relPath} tidak lagi dimuat di view/layout",
        !str_contains($gisViewContent, $relPath)
        && !str_contains($opsFormContent, $relPath)
        && !str_contains($footerContent, $relPath)
    );
}

// --- 3. MEMASTIKAN operasi_form TIDAK MEMUAT 5 SKRIP GIS TAK TERPAKAI ---
assertCondition("footer.php memisahkan \$requiresTacticalGis agar operasi_form hanya memuat Leaflet",
    str_contains($footerContent, '$requiresTacticalGis')
    && str_contains($footerContent, "(\$currentRoute ?? '') !== 'operasi_form'")
);

echo "\n====================================================================\n";
echo "  Total Pemeriksaan Asersi : {$totalAssertions}\n";
echo "  Lulus (PASS)             : {$passedAssertions}\n";
echo "  Gagal (FAIL)             : " . ($totalAssertions - $passedAssertions) . "\n";
echo "====================================================================\n";

if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
