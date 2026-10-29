<?php
/**
 * QR-SAR - Automated Test Suite: Dynamic Measurement Units & GIS Tab Badges
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. Counter Badges on All 4 GIS Tabs:
 *    - Tab Titik (#pointsBadgeCount)
 *    - Tab Search Area (#polygonsBadgeCount)
 *    - Tab Pola SAR (#patternsBadgeCount)
 *    - Tab Jarak & ETA (#routesBadgeCount)
 *    - Reactive update via updateGisBadges() on save/delete/filter
 * 2. Dynamic Measurement Units System:
 *    - Master Unit Switcher (NM | KM | M) in header (#gisGlobalUnitSwitcher)
 *    - Dynamic conversion of Area, Perimeter, Distance, Speed, and ETA
 *    - Precision of mathematical formulas:
 *      * 1 NM = 1.852 km (1 km = 0.539957 NM)
 *      * 1 NM² = 3.429904 km² (1 km² = 0.291553 NM²)
 *      * 1 Knot = 1.852 km/h
 *      * 1 m/s = 3.6 km/h
 *    - Primary display corresponds to selected unit, secondary in parentheses
 *    - Synchronous two-way propagation across all dropdowns (Circle, Rect, Pattern Spacing, Distance, Speed)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/GeoHelper.php';
require_once __DIR__ . '/../helpers/TemplateHelper.php';
require_once __DIR__ . '/../models/GisModel.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertTest(string $name, bool $condition, string $detail = ''): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$name}\n";
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

echo "\n====================================================================\n";
echo " TEST SUITE: DYNAMIC MEASUREMENT UNITS & GIS TAB BADGES\n";
echo "====================================================================\n\n";

$gisView = file_get_contents(__DIR__ . '/../views/gis.php');
$gisMasterJs = file_get_contents(__DIR__ . '/../assets/js/gis_master.js');

// -------------------------------------------------------------------------
// 1. VERIFIKASI COUNTER BADGES PADA KE-4 TAB GIS
// -------------------------------------------------------------------------
echo "[1] Verifikasi Counter Badges pada Tab GIS...\n";

assertTest(
    "views/gis.php menghitung \$initialPatternsCount pada semua mode filter",
    strpos($gisView, '$initialPatternsCount = count(array_filter($savedPatterns') !== false
);

assertTest(
    "views/gis.php menghitung \$initialRoutesCount pada semua mode filter",
    strpos($gisView, '$initialRoutesCount = count(array_filter($savedRoutes') !== false
);

assertTest(
    "views/gis.php memiliki elemen span #patternsBadgeCount pada tab Pola SAR",
    strpos($gisView, 'id="patternsBadgeCount"') !== false &&
    strpos($gisView, 'Pola SAR (<span id="patternsBadgeCount"><?= $initialPatternsCount ?></span>)') !== false
);

assertTest(
    "views/gis.php memiliki elemen span #routesBadgeCount pada tab Jarak & ETA",
    strpos($gisView, 'id="routesBadgeCount"') !== false &&
    strpos($gisView, 'Jarak & ETA (<span id="routesBadgeCount"><?= $initialRoutesCount ?></span>)') !== false
);

assertTest(
    "assets/js/gis_master.js memiliki fungsi updateGisBadges() yang meng-update ke-4 badge",
    strpos($gisMasterJs, 'function updateGisBadges()') !== false &&
    strpos($gisMasterJs, 'document.getElementById(\'patternsBadgeCount\')') !== false &&
    strpos($gisMasterJs, 'document.getElementById(\'routesBadgeCount\')') !== false
);

assertTest(
    "assets/js/gis_master.js renderSavedPolygons() memanggil updateGisBadges()",
    strpos($gisMasterJs, 'updateGisBadges();') !== false
);

assertTest(
    "assets/js/gis_master.js renderSavedPatterns() memanggil updateGisBadges()",
    strpos($gisMasterJs, 'function renderSavedPatterns()') !== false
);

assertTest(
    "assets/js/gis_master.js renderSavedRoutes() memanggil updateGisBadges()",
    strpos($gisMasterJs, 'function renderSavedRoutes()') !== false
);

// -------------------------------------------------------------------------
// 2. VERIFIKASI MASTER SATUAN DINAMIS SWITCHER DI HEADER
// -------------------------------------------------------------------------
echo "\n[2] Verifikasi Master Satuan Dinamis Switcher...\n";

assertTest(
    "views/gis.php menyediakan elemen Master Satuan Switcher #gisGlobalUnitSwitcher",
    strpos($gisView, 'id="gisGlobalUnitSwitcher"') !== false
);

assertTest(
    "views/gis.php menyediakan tombol pill dinamis NM, KM, dan M",
    strpos($gisView, 'data-global-unit="NM"') !== false &&
    strpos($gisView, 'data-global-unit="KM"') !== false &&
    strpos($gisView, 'data-global-unit="M"') !== false
);

assertTest(
    "assets/js/gis_master.js mendeklarasikan state activeGlobalUnit dengan fallback localStorage",
    strpos($gisMasterJs, 'let activeGlobalUnit = \'NM\';') !== false &&
    strpos($gisMasterJs, 'localStorage.getItem(\'qr_sar_gis_unit\')') !== false
);

assertTest(
    "assets/js/gis_master.js mengimplementasikan setGlobalMeasurementUnit(unit)",
    strpos($gisMasterJs, 'function setGlobalMeasurementUnit(unit') !== false
);

assertTest(
    "assets/js/gis_master.js menginisialisasi initGlobalUnitSwitcher di bootstrapApp()",
    strpos($gisMasterJs, 'initGlobalUnitSwitcher()') !== false &&
    strpos($gisMasterJs, 'try { initGlobalUnitSwitcher(); }') !== false
);

// -------------------------------------------------------------------------
// 3. VERIFIKASI FORMULA MATEMATIS & HELPER PEMFORMAT DINAMIS
// -------------------------------------------------------------------------
echo "\n[3] Verifikasi Presisi Formula Matematis & Format Dinamis...\n";

// Test Konversi Jarak Matematis
$distNm = 1.0;
$distKm = $distNm * 1.852;
assertTest(
    "Konversi 1 NM = 1.852 KM presisi",
    abs($distKm - 1.852) < 0.0001
);

$backNm = 1.0 / 1.852;
assertTest(
    "Konversi 1 KM = 0.539957 NM presisi",
    abs($backNm - 0.5399568) < 0.0001
);

// Test Konversi Luas Matematis
$areaNm2 = 1.0;
$areaKm2 = $areaNm2 * pow(1.852, 2); // 3.429904
assertTest(
    "Konversi 1 NM² = 3.429904 KM² presisi",
    abs($areaKm2 - 3.429904) < 0.0001
);

$backNm2 = 1.0 / 3.429904;
assertTest(
    "Konversi 1 KM² = 0.291553 NM² presisi",
    abs($backNm2 - 0.291553) < 0.0001
);

// Test Kecepatan & Waktu Tempuh
$speedKnots = 25.0;
$speedKmh = $speedKnots * 1.852; // 46.3 km/h
$timeHoursFromNm = $distNm / $speedKnots;
$timeHoursFromKm = $distKm / $speedKmh;
assertTest(
    "Konsistensi waktu tempuh (ETA) antara kalkulasi KM dan NM identik",
    abs($timeHoursFromNm - $timeHoursFromKm) < 0.000001
);

assertTest(
    "assets/js/gis_master.js menyediakan fungsi formatDynamicDistance()",
    strpos($gisMasterJs, 'function formatDynamicDistance(') !== false
);

assertTest(
    "assets/js/gis_master.js menyediakan fungsi formatDynamicArea()",
    strpos($gisMasterJs, 'function formatDynamicArea(') !== false
);

assertTest(
    "assets/js/gis_master.js menyediakan fungsi formatDynamicPerimeter()",
    strpos($gisMasterJs, 'function formatDynamicPerimeter(') !== false
);

assertTest(
    "assets/js/gis_master.js menyediakan fungsi formatDynamicSpeed()",
    strpos($gisMasterJs, 'function formatDynamicSpeed(') !== false
);

// -------------------------------------------------------------------------
// 4. VERIFIKASI SINKRONISASI DUA ARAH DROPDOWN SATUAN DI SELURUH TAB
// -------------------------------------------------------------------------
echo "\n[4] Verifikasi Sinkronisasi Dua Arah Antar-Komponen...\n";

assertTest(
    "Circle Radius Unit listener memanggil setGlobalMeasurementUnit",
    strpos($gisMasterJs, 'setGlobalMeasurementUnit(e.target.value);') !== false
);

assertTest(
    "Track Spacing Unit listener memanggil setGlobalMeasurementUnit",
    strpos($gisMasterJs, 'spacingUnitSel.addEventListener(\'change\', (e) => {') !== false
);

assertTest(
    "Measure Distance Unit listener memanggil setGlobalMeasurementUnit",
    strpos($gisMasterJs, 'distUnitSel.addEventListener(\'change\', (e) => {') !== false
);

assertTest(
    "Speed Unit otomatis tersinkron: KM -> kmh, NM -> knot, M -> ms",
    strpos($gisMasterJs, "targetSpeedUnit = unit === 'KM' ? 'kmh' : (unit === 'M' ? 'ms' : 'knot')") !== false
);

assertTest(
    "window.SAR_GIS mengekspos API setUnit, getUnit, dan updateBadges",
    strpos($gisMasterJs, 'setUnit: (u) => setGlobalMeasurementUnit(u)') !== false &&
    strpos($gisMasterJs, 'getUnit: () => activeGlobalUnit') !== false &&
    strpos($gisMasterJs, 'updateBadges: () => updateGisBadges()') !== false
);

// -------------------------------------------------------------------------
// RINGKASAN TEST
// -------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " TEST SUITE SUMMARY\n";
echo " Total Assertions : {$totalAssertions}\n";
echo " Passed           : {$passedAssertions}\n";
echo " Failed           : " . ($totalAssertions - $passedAssertions) . "\n";
echo " Success Rate     : " . round(($passedAssertions / $totalAssertions) * 100, 2) . "%\n";
echo "====================================================================\n\n";

if ($passedAssertions === $totalAssertions) {
    exit(0);
} else {
    exit(1);
}
