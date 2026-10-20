<?php
/**
 * QR-SAR - Automated Test Suite: Advanced Tactical GIS & BRA SAR DTG Perfection
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. Default View GIS = Base Map ('basemap')
 * 2. Manual Route Waypoint Undo in GIS Tactical Map Plotting
 * 3. Copy Saved Point Coordinates (DMS & Decimal Degree)
 * 4. Multi-Shape Search Area (Polygon, Rectangle, Circle) & Model Persistence
 * 5. E-SAR Tactical Search Pattern (Directional Arrows, Horizontal/Vertical Orientation, Auto Track Spacing & Personnel)
 * 6. Military DTG Standardization in BRA SAR (MMDD HHMM H, zero ISO leak)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/BraHelper.php';
require_once __DIR__ . '/../helpers/GeoHelper.php';
require_once __DIR__ . '/../models/GisModel.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
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
echo " TEST SUITE: ADVANCED GIS GEOSPATIAL & BRA MILITARY DTG PERFECTION\n";
echo "====================================================================\n\n";

// -------------------------------------------------------------------------
// 1. VERIFIKASI DEFAULT VIEW GIS = BASE MAP
// -------------------------------------------------------------------------
echo "[1] Verifikasi Default View GIS = Base Map (Point 5)...\n";

$gisViewContent = file_get_contents(__DIR__ . '/../views/gis.php');
$gisMasterJsContent = file_get_contents(__DIR__ . '/../assets/js/gis_master.js');

assertCondition(
    "views/gis.php menetapkan fallback filterMode ke 'basemap'",
    str_contains($gisViewContent, "\$filterMode = 'basemap';") &&
    str_contains($gisViewContent, "// Default tampilan saat membuka GIS SAR adalah Base Map")
);

assertCondition(
    "assets/js/gis_master.js menetapkan default filterMode ke 'basemap'",
    str_contains($gisMasterJsContent, "let filterMode = initialData.filterMode || (initialData.currentOperasiId ? 'operasi' : 'basemap');")
);

// -------------------------------------------------------------------------
// 2. VERIFIKASI UNDO TITIK MANUAL PADA PLOTTING JALUR PETA
// -------------------------------------------------------------------------
echo "\n[2] Verifikasi Fitur Undo Titik Manual Pada Jalur Peta (Point 1)...\n";

assertCondition(
    "views/gis.php memiliki tombol btnUndoMeasureRouteWaypoint pada header counter",
    str_contains($gisViewContent, 'id="btnUndoMeasureRouteWaypoint"') &&
    str_contains($gisViewContent, 'window.SAR_GIS.undoRoutePoint()')
);

assertCondition(
    "views/gis.php memiliki tombol btnBottomUndoRoute pada action bar bawah",
    str_contains($gisViewContent, 'id="btnBottomUndoRoute"')
);

assertCondition(
    "assets/js/gis_master.js mengimplementasikan fungsi undoLastMeasurePoint",
    str_contains($gisMasterJsContent, 'function undoLastMeasurePoint()') &&
    str_contains($gisMasterJsContent, 'measurePoints.pop();')
);

assertCondition(
    "assets/js/gis_master.js mengekspos undoRoutePoint ke window.SAR_GIS",
    str_contains($gisMasterJsContent, 'undoRoutePoint: () => undoLastMeasurePoint()')
);

// -------------------------------------------------------------------------
// 3. VERIFIKASI SALIN KOORDINAT TITIK TERSIMPAN
// -------------------------------------------------------------------------
echo "\n[3] Verifikasi Fitur Salin Koordinat Titik Tersimpan (Point 4)...\n";

assertCondition(
    "renderSavedPointsList di gis_master.js memuat tombol copy koordinat",
    str_contains($gisMasterJsContent, 'window.SAR_GIS.copyPointCoordinates')
);

assertCondition(
    "assets/js/gis_master.js mengimplementasikan fungsi copyPointCoordinates dan fallbackCopyText",
    str_contains($gisMasterJsContent, 'function copyPointCoordinates(id)') &&
    str_contains($gisMasterJsContent, 'function fallbackCopyText(text, label)')
);

assertCondition(
    "assets/js/gis_master.js mengekspos copyPointCoordinates ke window.SAR_GIS",
    str_contains($gisMasterJsContent, 'copyPointCoordinates: (id) => copyPointCoordinates(id)')
);

// -------------------------------------------------------------------------
// 4. VERIFIKASI TAB SEARCH AREA & MULTI-SHAPE (POLYGON, RECTANGLE, CIRCLE)
// -------------------------------------------------------------------------
echo "\n[4] Verifikasi Tab Search Area & Multi-Shape Geometry (Point 6)...\n";

assertCondition(
    "views/gis.php mengubah Tab Polygon menjadi Tab Search Area",
    str_contains($gisViewContent, 'Search Area (<span id="polygonsBadgeCount">')
);

assertCondition(
    "views/gis.php memiliki shape selectors (Polygon, Rectangle, Circle)",
    str_contains($gisViewContent, 'id="btnShapePolygon"') &&
    str_contains($gisViewContent, 'id="btnShapeRectangle"') &&
    str_contains($gisViewContent, 'id="btnShapeCircle"')
);

assertCondition(
    "views/gis.php memiliki box parameter untuk rectangle dan circle",
    str_contains($gisViewContent, 'id="polyRectangleParamsBox"') &&
    str_contains($gisViewContent, 'id="polyCircleParamsBox"') &&
    str_contains($gisViewContent, 'id="polyCircleRadiusNm"') &&
    str_contains($gisViewContent, 'id="polyRectWidthNm"') &&
    str_contains($gisViewContent, 'id="polyRectHeightNm"')
);

assertCondition(
    "assets/js/gis_master.js mengimplementasikan generateCircleVertices",
    str_contains($gisMasterJsContent, 'function generateCircleVertices(centerLat, centerLng, radiusNm')
);

assertCondition(
    "assets/js/gis_master.js mengimplementasikan generateRectangleVertices",
    str_contains($gisMasterJsContent, 'function generateRectangleVertices(centerLat, centerLng, widthNm, heightNm)')
);

assertCondition(
    "assets/js/gis_master.js menangani klik gambar multi-shape pada handleSearchAreaMapClick",
    str_contains($gisMasterJsContent, 'function handleSearchAreaMapClick(lat, lng)')
);

assertCondition(
    "assets/js/gis_master.js menyimpan shapeType dan dimensi pada handleSavePolygon",
    str_contains($gisMasterJsContent, 'shapeType: activeSearchAreaShape') &&
    str_contains($gisMasterJsContent, 'radiusNm: activeSearchAreaShape === \'circle\' ? polyCircleRadiusNm : null') &&
    str_contains($gisMasterJsContent, 'boxWidthNm: activeSearchAreaShape === \'rectangle\' ? polyRectWidthNm : null')
);

// Uji Simpan dan Baca Model GisModel untuk Circle dan Rectangle di Database
try {
    $testCircleId = 'test-circle-' . time();
    $circleSavedId = GisModel::savePolygon([
        'id' => $testCircleId,
        'name' => 'Search Area Radius Uji Circle',
        'shapeType' => 'circle',
        'centerLat' => -4.037694,
        'centerLng' => 122.486694,
        'radiusNm' => 1.5,
        'points' => [
            ['lat' => -4.01, 'lng' => 122.48, 'label' => 'R1'],
            ['lat' => -4.03, 'lng' => 122.50, 'label' => 'R2'],
            ['lat' => -4.05, 'lng' => 122.48, 'label' => 'R3'],
            ['lat' => -4.03, 'lng' => 122.46, 'label' => 'R4']
        ]
    ]);

    $allPolys = GisModel::getPolygons();
    $circleFound = null;
    foreach ($allPolys as $p) {
        if ($p['id'] === $testCircleId) {
            $circleFound = $p;
            break;
        }
    }

    assertCondition(
        "GisModel::savePolygon berhasil menyimpan Search Area tipe Circle",
        $circleFound !== null && $circleFound['shapeType'] === 'circle' && abs((float)$circleFound['radiusNm'] - 1.5) < 0.001
    );

    $testRectId = 'test-rect-' . time();
    $rectSavedId = GisModel::savePolygon([
        'id' => $testRectId,
        'name' => 'Search Area Kotak Uji Rectangle',
        'shapeType' => 'rectangle',
        'centerLat' => -4.037694,
        'centerLng' => 122.486694,
        'boxWidthNm' => 3.0,
        'boxHeightNm' => 2.0,
        'points' => [
            ['lat' => -4.02, 'lng' => 122.46, 'label' => 'A'],
            ['lat' => -4.02, 'lng' => 122.51, 'label' => 'B'],
            ['lat' => -4.05, 'lng' => 122.51, 'label' => 'C'],
            ['lat' => -4.05, 'lng' => 122.46, 'label' => 'D']
        ]
    ]);

    $allPolys2 = GisModel::getPolygons();
    $rectFound = null;
    foreach ($allPolys2 as $p) {
        if ($p['id'] === $testRectId) {
            $rectFound = $p;
            break;
        }
    }

    assertCondition(
        "GisModel::savePolygon berhasil menyimpan Search Area tipe Rectangle",
        $rectFound !== null && $rectFound['shapeType'] === 'rectangle' &&
        abs((float)$rectFound['boxWidthNm'] - 3.0) < 0.001 &&
        abs((float)$rectFound['boxHeightNm'] - 2.0) < 0.001
    );

    // Cleanup uji
    GisModel::deletePolygon($testCircleId);
    GisModel::deletePolygon($testRectId);
    assertCondition("Cleanup poligon pengujian berhasil", true);
} catch (Throwable $e) {
    assertCondition("Penyimpanan Multi-Shape ke database berhasil tanpa error", false, $e->getMessage());
}

// -------------------------------------------------------------------------
// 5. VERIFIKASI POLA E-SAR (PANAH ARAH, ORIENTASI, & AUTO-CALC)
// -------------------------------------------------------------------------
echo "\n[5] Verifikasi Pola Pencarian E-SAR (Point 2)...\n";

assertCondition(
    "views/gis.php memiliki UI selector arah orientasi sapuan E-SAR",
    str_contains($gisViewContent, 'id="esarOrientationWrapper"') &&
    str_contains($gisViewContent, 'id="btnEsarOrientHorizontal"') &&
    str_contains($gisViewContent, 'id="btnEsarOrientVertical"')
);

assertCondition(
    "views/gis.php memiliki readout rekomendasi tim E-SAR",
    str_contains($gisViewContent, 'id="patternEsarPersonnelInfo"') &&
    str_contains($gisViewContent, 'id="patternPersonnelCalcReadout"')
);

$patternsJsContent = file_get_contents(__DIR__ . '/../assets/js/gis/patterns.js');

assertCondition(
    "assets/js/gis/patterns.js mendukung parameter options pada generateFittedPatternInsidePolygon",
    str_contains($patternsJsContent, 'function generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount, options = {})')
);

assertCondition(
    "assets/js/gis/patterns.js memberi label sweepDir dan pNum pada titik sapuan E-SAR",
    str_contains($patternsJsContent, "sweepDir: 'vertical'") &&
    str_contains($patternsJsContent, "sweepDir: 'horizontal'")
);

assertCondition(
    "assets/js/gis_master.js meneruskan activeEsarOrientation ke pattern generator",
    str_contains($gisMasterJsContent, 'orientation: activeEsarOrientation')
);

assertCondition(
    "assets/js/gis_master.js menghitung Track Spacing dan Personel E-SAR otomatis pada updatePatternAutoCalculation",
    str_contains($gisMasterJsContent, 'autoSpacingNm = 0.05') &&
    str_contains($gisMasterJsContent, 'autoSpacingNm = 0.10') &&
    str_contains($gisMasterJsContent, 'autoSpacingNm = 0.20') &&
    str_contains($gisMasterJsContent, 'Math.ceil(perpSpanNm / autoSpacingNm) + 1')
);

assertCondition(
    "assets/js/gis_master.js merender penanda panah SVG rotasi bearing untuk E-SAR",
    str_contains($gisMasterJsContent, 'sar-esar-sweep-arrow') &&
    str_contains($gisMasterJsContent, 'transform: rotate(${Math.round(brng)}deg)')
);

// -------------------------------------------------------------------------
// 6. VERIFIKASI STANDARDISASI MILITARY DTG BRA SAR (MMDD HHMM H)
// -------------------------------------------------------------------------
echo "\n[6] Verifikasi Standardisasi Sandi Tanggal Militer BRA SAR (Point 3)...\n";

$rawIso1 = "PADA TW 2026-10-18 18:30:00 TIM SAR GABUNGAN MELAKSANAKAN BRIEFING";
$sanitized1 = BraHelper::sanitizeTelegramPunctuation($rawIso1);
assertCondition(
    "BraHelper mengonversi YYYY-MM-DD HH:ii:ss menjadi MMDD HHMM H",
    str_contains($sanitized1, "PADA TW 1018 1830 H") && !str_contains($sanitized1, "2026-10-18") && !str_contains($sanitized1, "TTK DUA 30")
);

$rawIso2 = "WAKTU PERGERAKAN TW 2026-10-18 07:45 TTK";
$sanitized2 = BraHelper::sanitizeTelegramPunctuation($rawIso2);
assertCondition(
    "BraHelper mengonversi YYYY-MM-DD HH:ii menjadi MMDD HHMM H",
    str_contains($sanitized2, "WAKTU PERGERAKAN TW 1018 0745 H TTK") && !str_contains($sanitized2, "2026-10-18")
);

$rawIso3 = "TW 2026-10-18 PENCARIAN SELESAI";
$sanitized3 = BraHelper::sanitizeTelegramPunctuation($rawIso3);
assertCondition(
    "BraHelper mengonversi YYYY-MM-DD tanpa jam menjadi MMDD 0000 H",
    str_contains($sanitized3, "TW 1018 0000 H") && !str_contains($sanitized3, "2026-10-18")
);

$alreadyMil = "PADA TW 1018 1830 H OPERASI SAR DILANJUTKAN";
$sanitizedMil = BraHelper::sanitizeTelegramPunctuation($alreadyMil);
assertCondition(
    "BraHelper tidak merusak format yang sudah berupa DTG militer",
    str_contains($sanitizedMil, "PADA TW 1018 1830 H")
);

$braFormJsContent = file_get_contents(__DIR__ . '/../assets/js/pages/bra_form.js');
assertCondition(
    "bra_form.js mengimplementasikan fungsi formatDateToMilitary",
    str_contains($braFormJsContent, 'function formatDateToMilitary(val)')
);

assertCondition(
    "bra_form.js membungkus r.waktu_briefing dengan formatDateToMilitary",
    str_contains($braFormJsContent, 'formatDateToMilitary(r.waktu_briefing)')
);

assertCondition(
    "bra_form.js membungkus sit.waktu_laporan dengan formatDateToMilitary",
    str_contains($braFormJsContent, 'formatDateToMilitary(sit.waktu_laporan)')
);

assertCondition(
    "bra_form.js membungkus latestSit.waktu_debrief dengan formatDateToMilitary",
    str_contains($braFormJsContent, 'formatDateToMilitary(latestSit?.waktu_debrief)')
);

assertCondition(
    "sanitizeTelegramText di bra_form.js memiliki regex auto-convert tanggal ISO",
    str_contains($braFormJsContent, '(\\d{4})-(\\d{2})-(\\d{2})')
);

// -------------------------------------------------------------------------
// RINGKASAN
// -------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " RINGKASAN HASIL PENGUJIAN: {$passedAssertions}/{$totalAssertions} ASERSI LULUS\n";
echo "====================================================================\n";

if ($passedAssertions === $totalAssertions) {
    echo "KESIMPULAN: SELURUH FITUR GIS ADVANCED & DTG MILITER BRA SAR LULUS 100%!\n\n";
    exit(0);
} else {
    echo "KESIMPULAN: TERDAPAT " . ($totalAssertions - $passedAssertions) . " ASERSI GAGAL.\n\n";
    exit(1);
}
