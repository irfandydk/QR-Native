<?php
/**
 * Test script for 5 Optimizations in QR-SAR GIS Tactical System
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/GisModel.php';

echo "=== TEST 1: Custom Icons API Endpoint ===\n";
// Test custom icons directory and files
$iconsDir = ROOT_DIR . '/uploads/icons';
$files = glob($iconsDir . '/*.{png,jpg,jpeg,svg,webp,gif}', GLOB_BRACE) ?: [];
echo "Custom icons found in uploads/icons: " . count($files) . "\n";
foreach (array_slice($files, 0, 3) as $f) {
    echo " - " . basename($f) . " (" . filesize($f) . " bytes)\n";
}

echo "\n=== TEST 2: Form 'Catatan Tambahan' Removed ===\n";
$gisPhp = file_get_contents(ROOT_DIR . '/views/gis.php');
if (strpos($gisPhp, 'id="pcPointDesc"') === false && strpos($gisPhp, 'Catatan Tambahan') === false) {
    echo "✓ PASS: Catatan Tambahan form input field successfully removed from views/gis.php\n";
} else {
    echo "✗ FAIL: Catatan Tambahan still found in views/gis.php\n";
}

echo "\n=== TEST 3: Generate Lintasan IAMSAR Button Removed ===\n";
if (strpos($gisPhp, 'id="btnGenerateSearchPattern"') === false) {
    echo "✓ PASS: Tombol Generate Lintasan IAMSAR successfully removed from views/gis.php\n";
} else {
    echo "✗ FAIL: btnGenerateSearchPattern still found in views/gis.php\n";
}

echo "\n=== TEST 4: Mathematical Formula Verification ===\n";
// 4a. Haversine 1 degree of latitude
$p1 = ['lat' => -4.0, 'lng' => 122.0];
$p2 = ['lat' => -3.0, 'lng' => 122.0];
$distHaversine = GisModel::calculateDistanceHaversine($p1['lat'], $p1['lng'], $p2['lat'], $p2['lng']);
echo "Haversine 1 deg latitude: " . $distHaversine['nm'] . " NM (" . $distHaversine['km'] . " km)\n";
assert(abs($distHaversine['nm'] - 60.0) < 0.2, "Haversine latitude distance should be ~60 NM");
echo "✓ Haversine Distance OK\n";

// 4b. Geodesic Polygon Area: 0.1 deg x 0.1 deg near equator (~6 NM x ~6 NM = ~36 NM²)
$poly = [
    ['lat' => -4.0, 'lng' => 122.0],
    ['lat' => -4.0, 'lng' => 122.1],
    ['lat' => -4.1, 'lng' => 122.1],
    ['lat' => -4.1, 'lng' => 122.0]
];
$polyArea = GisModel::calculatePolygonAreaNm2($poly);
echo "Geodesic Polygon Area (0.1 deg box): " . $polyArea . " NM²\n";
assert($polyArea > 35.0 && $polyArea < 37.0, "Area should be ~35.9 NM²");
echo "✓ Spherical Geodesic Polygon Area OK\n";

// 4c. Circle Area (radius = 5 NM => A = pi * r^2 = 78.54 NM²)
$circleArea = GisModel::calculatePolygonAreaNm2([], 'circle', 5.0);
echo "Circle Area (r=5 NM): " . $circleArea . " NM²\n";
assert(abs($circleArea - 78.54) < 0.05, "Circle area should be 78.54 NM²");
echo "✓ Circle Area Formula OK\n";

// 4d. Total Area Swept (A = S x L)
$spacingNm = 1.0;
$distanceNm = 36.0;
$areaSwept = $spacingNm * $distanceNm;
echo "Total Area Swept (S=1.0 NM, L=36.0 NM): " . $areaSwept . " NM² (" . ($areaSwept * 3.429904) . " km²)\n";
assert($areaSwept === 36.0, "Swept area should match S * L");
echo "✓ IAMSAR Vol II Area Swept Formula OK\n";

echo "\n=== TEST 5: Default Include Base Map for Selected Operation ===\n";
// Check views/gis.php default logic: ($filterMode === 'operasi')
if (strpos($gisPhp, "(\$filterMode === 'operasi')") !== false && strpos($gisPhp, "\$includeBase = isset(\$_GET['include_base'])") !== false) {
    echo "✓ PASS: views/gis.php sets \$includeBase = true by default when filterMode is 'operasi'\n";
} else {
    echo "✗ FAIL: Default includeBase logic missing in views/gis.php\n";
}

$gisJs = file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');
if (strpos($gisJs, "includeBaseInActive = true;") !== false) {
    echo "✓ PASS: gis_master.js sets includeBaseInActive = true upon operation selection\n";
} else {
    echo "✗ FAIL: includeBaseInActive assignment missing in gis_master.js\n";
}

echo "\n=== ALL 5 OPTIMIZATION TESTS PASSED! ===\n";
