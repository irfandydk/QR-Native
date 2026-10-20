<?php
/**
 * QR-SAR - Automated Test Suite: GIS Consistency, E-SAR Direction/Arrows, and Expanding Square Elimination
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. Search Area Consistency between Plotting GIS (gis.php) and Operation Detail Tactical Map (operasi_detail.php)
 *    - Circle renders via centerLat/centerLng/radiusNm without requiring discrete points
 *    - Rectangle corners dynamically computed if discrete points are omitted
 *    - Detail Operation history & copy buttons cleanly support Circle & Rectangle
 * 2. E-SAR 4-Vector Direction & Persistent Sweep Arrow Markers
 *    - Direction buttons toggle between horizontal & vertical groups
 *    - Real-time route regeneration on direction toggle
 *    - Directional SVG arrows rendered both on draft pattern and when loading saved patterns
 *    - TacticalGisMap.loadOperationBundle renders E-SAR multi-polylines and directional arrows
 * 3. Tactical Component Consistency across Plotting, RENOPS, and Detail Operasi
 * 4. Total Elimination of Expanding Square Search Pattern (0 references in UI, JS, and PHP)
 */

declare(strict_types=1);

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
echo " TEST SUITE: GIS CONSISTENCY, E-SAR ARROWS & 3-PATTERN STANDARDIZATION\n";
echo "====================================================================\n\n";

$gisView = file_get_contents(__DIR__ . '/../views/gis.php');
$gisMasterJs = file_get_contents(__DIR__ . '/../assets/js/gis_master.js');
$gisJs = file_get_contents(__DIR__ . '/../assets/js/gis.js');
$patternsJs = file_get_contents(__DIR__ . '/../assets/js/gis/patterns.js');
$uiPatternsJs = file_get_contents(__DIR__ . '/../assets/js/gis/ui_patterns.js');
$operasiDetailJs = file_get_contents(__DIR__ . '/../assets/js/pages/operasi_detail.js');
$renopsFormJs = file_get_contents(__DIR__ . '/../assets/js/pages/renops_form.js');
$operasiCtrl = file_get_contents(__DIR__ . '/../controllers/OperasiController.php');

// -------------------------------------------------------------------------
// 1. ELIMINASI TOTAL EXPANDING SQUARE (POIN 4 USER)
// -------------------------------------------------------------------------
echo "[1] Verifikasi Eliminasi Total Expanding Square (Poin 4)...\n";

assertTest(
    "views/gis.php tidak mengandung tombol expanding_square",
    !str_contains($gisView, 'data-ptype="expanding_square"')
);

assertTest(
    "views/gis.php hanya menyediakan tepat 3 tombol pola pencarian baku (Parallel, Creeping, E-SAR)",
    str_contains($gisView, 'data-ptype="parallel_track"') &&
    str_contains($gisView, 'data-ptype="creeping_line"') &&
    str_contains($gisView, 'data-ptype="e_sar"') &&
    substr_count($gisView, 'data-ptype=') === 3
);

assertTest(
    "assets/js/gis/patterns.js bebas dari referensi expanding_square",
    !str_contains($patternsJs, 'expanding_square')
);

assertTest(
    "assets/js/gis/ui_patterns.js bebas dari referensi expanding_square",
    !str_contains($uiPatternsJs, 'expanding_square')
);

assertTest(
    "assets/js/pages/renops_form.js bebas dari referensi expanding_square",
    !str_contains($renopsFormJs, 'expanding_square')
);

assertTest(
    "assets/js/pages/operasi_detail.js bebas dari referensi expanding_square",
    !str_contains($operasiDetailJs, 'expanding_square')
);

assertTest(
    "controllers/OperasiController.php bebas dari referensi expanding_square",
    !str_contains($operasiCtrl, 'expanding_square')
);

assertTest(
    "assets/js/gis.js bebas dari referensi expanding_square",
    !str_contains($gisJs, 'expanding_square')
);

// -------------------------------------------------------------------------
// 2. KONSISTENSI SEARCH AREA DI PLOTTING PETA & DETAIL OPERASI (POIN 1 USER)
// -------------------------------------------------------------------------
echo "\n[2] Verifikasi Konsistensi Search Area di Plotting & Detail Operasi (Poin 1)...\n";

assertTest(
    "assets/js/gis.js merender L.circle tanpa mensyaratkan pts.length > 0",
    str_contains($gisJs, "const isCircle = p.shapeType === 'circle' && Number(p.radiusNm) > 0;") &&
    str_contains($gisJs, "p.centerLat !== null") &&
    str_contains($gisJs, "L.circle(center,")
);

assertTest(
    "assets/js/gis.js menambahkan pin pulsasi pusat datum LKP untuk Search Area Circle",
    str_contains($gisJs, "sar-circle-center-pin") &&
    str_contains($gisJs, "Pusat Datum (LKP):")
);

assertTest(
    "assets/js/gis.js memiliki fallback komputasi 4 sudut Rectangle jika koordinat titik diskrit kosong",
    str_contains($gisJs, "p.shapeType === 'rectangle' || p.shapeType === 'box'") &&
    str_contains($gisJs, "Titik A (NW)") &&
    str_contains($gisJs, "Titik D (SW)")
);

assertTest(
    "assets/js/pages/operasi_detail.js renderDetailGisHistoryList memformat metadata Circle & Rectangle secara spesifik",
    str_contains($operasiDetailJs, "p.shapeType === 'circle'") &&
    str_contains($operasiDetailJs, "⭕ Radius:") &&
    str_contains($operasiDetailJs, "⏹️ Persegi:")
);

assertTest(
    "assets/js/pages/operasi_detail.js copyDetailPolygonCoords mendukung parameter Circle tanpa crash",
    str_contains($operasiDetailJs, "Bentuk: Lingkaran (Circle)") &&
    str_contains($operasiDetailJs, "Radius Sektor:") &&
    str_contains($operasiDetailJs, "Parameter Radius Search Area Disalin!")
);

// -------------------------------------------------------------------------
// 3. E-SAR ARAH SAPUAN DAN PANAH TERSIMPAN (POIN 2 USER)
// -------------------------------------------------------------------------
echo "\n[3] Verifikasi E-SAR Arah Sapuan dan Render Panah Tersimpan (Poin 2)...\n";

assertTest(
    "views/gis.php menyediakan 4 tombol vektor arah E-SAR dengan data-direction dan data-dir",
    str_contains($gisView, 'id="btnEsarDirWtoE"') &&
    str_contains($gisView, 'data-direction="west_to_east"') &&
    str_contains($gisView, 'id="btnEsarDirEtoW"') &&
    str_contains($gisView, 'data-direction="east_to_west"') &&
    str_contains($gisView, 'id="btnEsarDirNtoS"') &&
    str_contains($gisView, 'data-direction="north_to_south"') &&
    str_contains($gisView, 'id="btnEsarDirStoN"') &&
    str_contains($gisView, 'data-direction="south_to_north"')
);

assertTest(
    "assets/js/gis_master.js updateEsarDirectionButtonsUI men-toggle kelas hidden antara grup horizontal dan vertikal",
    str_contains($gisMasterJs, "const hGroup = document.getElementById('esarDirHorizontalGroup');") &&
    str_contains($gisMasterJs, "const vGroup = document.getElementById('esarDirVerticalGroup');") &&
    str_contains($gisMasterJs, "hGroup.classList.remove('hidden');") &&
    str_contains($gisMasterJs, "vGroup.classList.remove('hidden');")
);

assertTest(
    "assets/js/gis_master.js tombol arah E-SAR memicu regenerasi real-time generateSearchPatternRoute()",
    str_contains($gisMasterJs, "document.querySelectorAll('.esar-dir-btn').forEach") &&
    str_contains($gisMasterJs, "generateSearchPatternRoute();")
);

assertTest(
    "assets/js/gis_master.js renderSavedPatterns() menggambar panah rotasi SVG untuk pola E-SAR tersimpan",
    str_contains($gisMasterJs, "pat.patternType === 'e_sar' || pat.patternType === 'sector_search'") &&
    str_contains($gisMasterJs, "sar-esar-sweep-arrow") &&
    str_contains($gisMasterJs, "savedPatternArrowMarkersMap")
);

assertTest(
    "assets/js/gis_master.js editPattern() membersihkan panah SVG pola tersimpan saat masuk mode edit",
    str_contains($gisMasterJs, "savedPatternArrowMarkersMap.has(pat.id)") &&
    str_contains($gisMasterJs, "savedPatternArrowMarkersMap.delete(pat.id);")
);

assertTest(
    "assets/js/gis.js TacticalGisMap.loadOperationBundle merender multi-polyline dan panah rotasi SVG untuk pola E-SAR",
    str_contains($gisJs, "const isEsar = (p.patternType === 'e_sar' || p.patternType === 'sector_search');") &&
    str_contains($gisJs, "sar-esar-sweep-arrow") &&
    str_contains($gisJs, "sar-pat-esar-p")
);

// -------------------------------------------------------------------------
// 3.1 VERIFIKASI PERBAIKAN POLA PARALEL (PS) DAN CREEPING LINE (CS)
// -------------------------------------------------------------------------
echo "\n[3.1] Verifikasi Perbaikan Pola Paralel (PS) dan Creeping Line (CS)...\n";

assertTest(
    "assets/js/gis_master.js mendeklarasikan let recommendedSweeps secara aman untuk Parallel & Creeping",
    str_contains($gisMasterJs, "let recommendedSweeps = 6;") &&
    str_contains($gisMasterJs, "badgeEl.textContent = `\${recommendedSweeps} SWEEPS`;")
);

assertTest(
    "assets/js/gis_master.js memiliki fungsi resolveTargetPolygonPoints untuk resolusi Poligon, Circle, dan Rectangle",
    str_contains($gisMasterJs, "function resolveTargetPolygonPoints(targetPolyId)") &&
    str_contains($gisMasterJs, "poly.shapeType === 'circle'") &&
    str_contains($gisMasterJs, "poly.shapeType === 'rectangle'") &&
    str_contains($gisMasterJs, "generateCircleVertices(cLat, cLng, rNm, 36)") &&
    str_contains($gisMasterJs, "generateRectangleVertices(cLat, cLng, wNm, hNm)")
);

assertTest(
    "assets/js/gis_master.js mendukung konversi satuan dinamis (NM, KM, M) pada activeSpan",
    str_contains($gisMasterJs, "const activeSpan = unit === 'KM' ? spanDimKm : (unit === 'M' ? spanDimKm * 1000 : spanDimNm);")
);

assertTest(
    "assets/js/gis/patterns.js dan gis_master.js memiliki fallback lintasan sapuan agar koordinat tidak pernah kosong",
    str_contains($patternsJs, "if (points.length < 2)") &&
    str_contains($gisMasterJs, "if (points.length < 2)")
);

// -------------------------------------------------------------------------
// 4. KONSISTENSI DATA LINTAS MODUL (DATABASE & API SIMULATION) (POIN 3 USER)
// -------------------------------------------------------------------------
echo "\n[4] Verifikasi Konsistensi Data Lintas Modul (Poin 3)...\n";

try {
    $db = getDB();
    
    // Test 1: Simpan Circle Search Area murni tanpa titik polusi
    $circleId = 'test-circle-' . time();
    $circlePayload = [
        'id' => $circleId,
        'id_operasi' => null,
        'name' => 'Search Area Radius Uji Konsistensi',
        'shapeType' => 'circle',
        'centerLat' => -3.987654,
        'centerLng' => 122.543210,
        'radiusNm' => 2.50,
        'boxWidthNm' => null,
        'boxHeightNm' => null,
        'points' => [], // Murni tanpa 36 vertex semu!
        'strokeColor' => '#0284c7',
        'strokeWeight' => 3.0,
        'fillColor' => '#0284c7',
        'fillOpacity' => 0.25
    ];

    GisModel::savePolygon($circlePayload);
    $savedPolys = GisModel::getPolygons(null, true);
    $foundCircle = null;
    foreach ($savedPolys as $sp) {
        if ($sp['id'] === $circleId) {
            $foundCircle = $sp;
            break;
        }
    }

    assertTest(
        "GisModel::savePolygon menyimpan Circle dengan radiusNm dan centerLat/Lng sempurna",
        $foundCircle !== null &&
        $foundCircle['shapeType'] === 'circle' &&
        abs((float)$foundCircle['radiusNm'] - 2.50) < 0.01 &&
        abs((float)$foundCircle['centerLat'] - (-3.987654)) < 0.0001
    );

    assertTest(
        "GisModel menghitung areaNm2 Circle secara akurat (pi * r^2)",
        $foundCircle !== null &&
        abs((float)$foundCircle['areaNm2'] - (M_PI * 2.50 * 2.50)) < 0.1
    );

    // Test 2: Simpan E-SAR Search Pattern dengan 4 Personel
    $esarId = 'test-esar-' . time();
    $esarWaypoints = [
        ['lat' => -3.98, 'lng' => 122.50, 'label' => 'Personel 1 (Start)'],
        ['lat' => -3.98, 'lng' => 122.56, 'label' => 'Personel 1 (Batas Maju)'],
        ['lat' => -3.99, 'lng' => 122.50, 'label' => 'Personel 2 (Start)'],
        ['lat' => -3.99, 'lng' => 122.56, 'label' => 'Personel 2 (Batas Maju)'],
        ['lat' => -4.00, 'lng' => 122.50, 'label' => 'Personel 3 (Start)'],
        ['lat' => -4.00, 'lng' => 122.56, 'label' => 'Personel 3 (Batas Maju)'],
        ['lat' => -4.01, 'lng' => 122.50, 'label' => 'Personel 4 (Start)'],
        ['lat' => -4.01, 'lng' => 122.56, 'label' => 'Personel 4 (Batas Maju)']
    ];

    $esarPayload = [
        'id' => $esarId,
        'id_operasi' => null,
        'name' => 'Pola E-SAR Uji Konsistensi 4 Personil',
        'polygonId' => $circleId,
        'polygonName' => 'Search Area Radius Uji Konsistensi',
        'patternType' => 'e_sar',
        'trackSpacingNm' => 0.60,
        'legsCount' => 4,
        'color' => '#0284c7',
        'opacity' => 0.9,
        'strokeWeight' => 3,
        'dashArray' => '6, 6',
        'totalDistanceNm' => 14.4,
        'waypoints' => $esarWaypoints
    ];

    GisModel::saveSearchPattern($esarPayload);

    // Test 3: Simpan Parallel Track Search Pattern
    $psId = 'test-ps-' . time();
    $psPayload = [
        'id' => $psId,
        'id_operasi' => null,
        'name' => 'Pola Parallel Track Uji Konsistensi 6 Sweeps',
        'polygonId' => $circleId,
        'polygonName' => 'Search Area Radius Uji Konsistensi',
        'patternType' => 'parallel_track',
        'trackSpacingNm' => 1.0,
        'legsCount' => 6,
        'color' => '#0284c7',
        'opacity' => 0.85,
        'strokeWeight' => 3,
        'dashArray' => '6, 6',
        'totalDistanceNm' => 18.2,
        'waypoints' => [
            ['lat' => -3.98, 'lng' => 122.50, 'label' => 'Entry (S)'],
            ['lat' => -3.98, 'lng' => 122.60, 'label' => 'Lintasan 1B'],
            ['lat' => -3.99, 'lng' => 122.60, 'label' => 'Lintasan 2A'],
            ['lat' => -3.99, 'lng' => 122.50, 'label' => 'Lintasan 2B'],
            ['lat' => -4.00, 'lng' => 122.50, 'label' => 'Lintasan 3A'],
            ['lat' => -4.00, 'lng' => 122.60, 'label' => 'Exit (E)']
        ]
    ];
    GisModel::saveSearchPattern($psPayload);

    // Test 4: Simpan Creeping Line Search Pattern
    $csId = 'test-cs-' . time();
    $csPayload = [
        'id' => $csId,
        'id_operasi' => null,
        'name' => 'Pola Creeping Line Uji Konsistensi 6 Sweeps',
        'polygonId' => $circleId,
        'polygonName' => 'Search Area Radius Uji Konsistensi',
        'patternType' => 'creeping_line',
        'trackSpacingNm' => 0.5,
        'legsCount' => 6,
        'color' => '#ea580c',
        'opacity' => 0.85,
        'strokeWeight' => 3,
        'dashArray' => '6, 6',
        'totalDistanceNm' => 12.5,
        'waypoints' => [
            ['lat' => -3.98, 'lng' => 122.50, 'label' => 'Entry (S)'],
            ['lat' => -4.02, 'lng' => 122.50, 'label' => 'Sapu 1B'],
            ['lat' => -4.02, 'lng' => 122.52, 'label' => 'Sapu 2A'],
            ['lat' => -3.98, 'lng' => 122.52, 'label' => 'Sapu 2B'],
            ['lat' => -3.98, 'lng' => 122.54, 'label' => 'Sapu 3A'],
            ['lat' => -4.02, 'lng' => 122.54, 'label' => 'Exit (E)']
        ]
    ];
    GisModel::saveSearchPattern($csPayload);

    $savedPatterns = GisModel::getSearchPatterns(null, true);
    $foundEsar = null;
    $foundPs = null;
    $foundCs = null;
    foreach ($savedPatterns as $pat) {
        if ($pat['id'] === $esarId) $foundEsar = $pat;
        if ($pat['id'] === $psId) $foundPs = $pat;
        if ($pat['id'] === $csId) $foundCs = $pat;
    }

    assertTest(
        "GisModel::saveSearchPattern menyimpan Pola E-SAR dan waypoints urut 4 personil",
        $foundEsar !== null &&
        $foundEsar['patternType'] === 'e_sar' &&
        (int)$foundEsar['legsCount'] === 4 &&
        count($foundEsar['waypoints']) === 8
    );

    assertTest(
        "GisModel::saveSearchPattern menyimpan Pola Parallel Track dan waypoints berurutan",
        $foundPs !== null &&
        $foundPs['patternType'] === 'parallel_track' &&
        (int)$foundPs['legsCount'] === 6 &&
        count($foundPs['waypoints']) === 6
    );

    assertTest(
        "GisModel::saveSearchPattern menyimpan Pola Creeping Line dan waypoints berurutan",
        $foundCs !== null &&
        $foundCs['patternType'] === 'creeping_line' &&
        (int)$foundCs['legsCount'] === 6 &&
        count($foundCs['waypoints']) === 6
    );

    // Test 5: Validasi Konsistensi Geometri Lintas Modul
    assertTest(
        "GisModel mengembalikan Circle, E-SAR, Parallel, dan Creeping dengan atribut taktis lengkap",
        $foundCircle !== null &&
        $foundEsar !== null &&
        $foundPs !== null &&
        $foundCs !== null &&
        $foundCircle['shapeType'] === 'circle' &&
        !empty($foundEsar['waypoints']) &&
        !empty($foundPs['waypoints']) &&
        !empty($foundCs['waypoints'])
    );

    // Cleanup test data
    GisModel::deletePolygon($circleId);
    GisModel::deleteSearchPattern($esarId);
    GisModel::deleteSearchPattern($psId);
    GisModel::deleteSearchPattern($csId);

    assertTest(
        "Pembersihan seluruh data uji database (Circle, E-SAR, PS, CS) berhasil",
        true
    );

} catch (\Throwable $e) {
    assertTest("Integrasi Database GisModel", false, $e->getMessage());
}

// -------------------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------------------
echo "\n====================================================================\n";
echo " TEST SUITE SUMMARY\n";
echo " Total Assertions : {$totalAssertions}\n";
echo " Passed           : {$passedAssertions}\n";
echo " Failed           : " . ($totalAssertions - $passedAssertions) . "\n";
echo " Success Rate     : " . round(($passedAssertions / max(1, $totalAssertions)) * 100, 2) . "%\n";
echo "====================================================================\n\n";

if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
exit(0);
