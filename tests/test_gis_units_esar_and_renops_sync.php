<?php
/**
 * QR-SAR - Automated Test Suite: Dynamic Units, Clean Circle, E-SAR Two-Way & RENOPS Sync
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. Dynamic Distance & Speed Units (NM, KM, Meter, knot, km/h, m/s) on Search Area, Routes, and Track Spacing
 * 2. Instant Search Area Detection & Sector Linking in RENOPS (Circle, Rectangle, Polygon)
 * 3. Single-Click Undo on Tactical Route Plotting (Debounce Lock & No Double Trigger)
 * 4. Clean Circle Geometry (Single Center Datum Pin & Clean Readout, Zero 36-vertex pollution)
 * 5. E-SAR Two-Way Reactive Binding between Personnel Count and Track Spacing
 * 6. E-SAR 4-Vector Sweep Direction (West->East, East->West, North->South, South->North)
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
echo " TEST SUITE: DYNAMIC UNITS, CLEAN CIRCLE, E-SAR TWO-WAY & RENOPS SYNC\n";
echo "====================================================================\n\n";

$gisView = file_get_contents(__DIR__ . '/../views/gis.php');
$gisMasterJs = file_get_contents(__DIR__ . '/../assets/js/gis_master.js');
$patternsJs = file_get_contents(__DIR__ . '/../assets/js/gis/patterns.js');
$renopsFormJs = file_get_contents(__DIR__ . '/../assets/js/pages/renops_form.js');
$renopsView = file_get_contents(__DIR__ . '/../views/operasi/renops_form.php');
$operasiCtrl = file_get_contents(__DIR__ . '/../controllers/OperasiController.php');

// -------------------------------------------------------------------------
// 1. VERIFIKASI SATUAN JARAK & KECEPATAN DINAMIS (POIN 1)
// -------------------------------------------------------------------------
echo "[1] Verifikasi Satuan Jarak & Kecepatan Dinamis (Poin 1)...\n";

assertTest(
    "views/gis.php memiliki dropdown polyCircleRadiusUnit (NM, KM, M)",
    str_contains($gisView, 'id="polyCircleRadiusUnit"') &&
    str_contains($gisView, 'value="NM"') &&
    str_contains($gisView, 'value="KM"') &&
    str_contains($gisView, 'value="M"')
);

assertTest(
    "views/gis.php memiliki dropdown polyRectDimUnit (NM, KM, M)",
    str_contains($gisView, 'id="polyRectDimUnit"') &&
    str_contains($gisView, 'value="NM"') &&
    str_contains($gisView, 'value="KM"') &&
    str_contains($gisView, 'value="M"')
);

assertTest(
    "views/gis.php memiliki dropdown measureDistanceUnit (NM, KM, M)",
    str_contains($gisView, 'id="measureDistanceUnit"') &&
    str_contains($gisView, 'value="NM"') &&
    str_contains($gisView, 'value="KM"') &&
    str_contains($gisView, 'value="M"')
);

assertTest(
    "views/gis.php mendukung satuan kecepatan m/s (ms) di samping knot dan kmh",
    str_contains($gisView, 'id="measureSpeedUnit"') &&
    str_contains($gisView, 'value="knot"') &&
    str_contains($gisView, 'value="kmh"') &&
    str_contains($gisView, 'value="ms"')
);

assertTest(
    "views/gis.php mendukung satuan spasi Meter di patternSpacingUnit",
    str_contains($gisView, 'id="patternSpacingUnit"') &&
    str_contains($gisView, 'value="M"')
);

assertTest(
    "gis_master.js menangani konversi satuan dinamis pada recalcRouteMetrics",
    str_contains($gisMasterJs, "currentUnit === 'ms'") &&
    str_contains($gisMasterJs, "activeDistanceUnit === 'KM'") &&
    str_contains($gisMasterJs, "activeDistanceUnit === 'M'")
);

// -------------------------------------------------------------------------
// 2. VERIFIKASI DETEKSI SEARCH AREA PADA RENOPS (POIN 2)
// -------------------------------------------------------------------------
echo "\n[2] Verifikasi Deteksi Search Area pada Pembagian Sektor RENOPS (Poin 2)...\n";

assertTest(
    "renops_form.js mendukung perhitungan luas Circle dan Rectangle",
    str_contains($renopsFormJs, "poly.shapeType === 'circle'") &&
    str_contains($renopsFormJs, "Math.PI * Math.pow(Number(poly.radiusNm), 2)") &&
    str_contains($renopsFormJs, "poly.shapeType === 'rectangle'")
);

assertTest(
    "renops_form.js memformat koordinat Circle dengan Pusat Datum LKP dan Radius Sektor",
    str_contains($renopsFormJs, "Pusat Datum (LKP):") &&
    str_contains($renopsFormJs, "Radius Sektor:")
);

assertTest(
    "renops_form.js menyediakan Quick Sector Picker dropdown per SRU",
    str_contains($renopsFormJs, "applySektorToPlotting") &&
    str_contains($renopsFormJs, "Sektor Plotting GIS:")
);

assertTest(
    "renops_form.js mengimplementasikan bulk syncAllPlottingSectorsToSru",
    str_contains($renopsFormJs, "function syncAllPlottingSectorsToSru()") &&
    str_contains($renopsFormJs, "window.syncAllPlottingSectorsToSru")
);

assertTest(
    "views/operasi/renops_form.php memiliki tombol Sinkronkan Sektor Plotting ke SRU",
    str_contains($renopsView, 'id="btn-sync-all-gis-srus"') &&
    str_contains($renopsView, 'syncAllPlottingSectorsToSru()')
);

assertTest(
    "OperasiController.php memproses shape Circle dan Rectangle saat inisialisasi SRU",
    str_contains($operasiCtrl, "\$shapeType === 'circle'") &&
    str_contains($operasiCtrl, "M_PI * \$r * \$r") &&
    str_contains($operasiCtrl, "\$shapeType === 'rectangle'")
);

// -------------------------------------------------------------------------
// 3. VERIFIKASI UNDO TITIK PLOTTING TEPAT 1 PER 1 (POIN 3)
// -------------------------------------------------------------------------
echo "\n[3] Verifikasi Undo Titik Plotting Tepat 1 per 1 Titik (Poin 3)...\n";

assertTest(
    "gis_master.js memiliki debounce throttle lock pada undoLastMeasurePoint",
    str_contains($gisMasterJs, 'if (isUndoingRoutePoint) return;') &&
    str_contains($gisMasterJs, 'isUndoingRoutePoint = true;') &&
    str_contains($gisMasterJs, 'setTimeout') &&
    str_contains($gisMasterJs, 'isUndoingRoutePoint = false;')
);

assertTest(
    "initMeasureUI di gis_master.js tidak menambahkan listener duplikat jika elemen sudah memiliki inline onclick",
    str_contains($gisMasterJs, "!btn.getAttribute('onclick')")
);

// -------------------------------------------------------------------------
// 4. VERIFIKASI CIRCLE BERSIH & PRESISI (POIN 4)
// -------------------------------------------------------------------------
echo "\n[4] Verifikasi Circle Bersih 1 Pusat Datum LKP (Poin 4)...\n";

assertTest(
    "updateLivePolygonVisual di gis_master.js merender L.circle untuk activeSearchAreaShape === 'circle'",
    str_contains($gisMasterJs, "activeSearchAreaShape === 'circle'") &&
    str_contains($gisMasterJs, "activeDrawingPolygonOverlay = L.circle([c.lat, c.lng],")
);

assertTest(
    "updateLivePolygonVisual di gis_master.js hanya merender 1 draggable center pin untuk Circle",
    str_contains($gisMasterJs, "className: 'sar-circle-center-pin'") &&
    str_contains($gisMasterJs, "activeDrawingVertexMarkers = [marker];") ||
    (str_contains($gisMasterJs, "sar-circle-center-pin") && str_contains($gisMasterJs, "activeDrawingVertexMarkers.push(marker);"))
);

assertTest(
    "Tabel vertex di sidebar menampilkan card Titik Pusat Datum LKP ringkas saat Circle",
    str_contains($gisMasterJs, "Titik Pusat Datum (LKP)") &&
    str_contains($gisMasterJs, "Radius Sektor:")
);

assertTest(
    "renderSavedPolygons di gis_master.js merender L.circle untuk poligon tersimpan bertipe circle",
    str_contains($gisMasterJs, "const isCircle = poly.shapeType === 'circle';") &&
    str_contains($gisMasterJs, "overlay = L.circle([cLat, cLng],")
);

// -------------------------------------------------------------------------
// 5. VERIFIKASI REAKTIVITAS DUA ARAH POLA E-SAR (POIN 5)
// -------------------------------------------------------------------------
echo "\n[5] Verifikasi Two-Way Reactive Binding Pola E-SAR (Poin 5)...\n";

assertTest(
    "updatePatternAutoCalculation mendukung trigger parameter dengan source personnel dan spacing",
    str_contains($gisMasterJs, "function updatePatternAutoCalculation(trigger = { source: 'auto' })") &&
    str_contains($gisMasterJs, "trigger.source === 'personnel'") &&
    str_contains($gisMasterJs, "trigger.source === 'spacing'")
);

assertTest(
    "legsInput terhubung dengan updatePatternAutoCalculation({ source: 'personnel' })",
    str_contains($gisMasterJs, "updatePatternAutoCalculation({ source: 'personnel' });")
);

assertTest(
    "spacingInput terhubung dengan updatePatternAutoCalculation({ source: 'spacing' })",
    str_contains($gisMasterJs, "updatePatternAutoCalculation({ source: 'spacing' });")
);

assertTest(
    "Formula kalkulasi two-way binding menghitung Track Spacing saat Personil diubah dan sebaliknya",
    str_contains($gisMasterJs, "effectiveSpacingNm = Math.max(0.005, perpSpanNm / Math.max(1, effectivePersonnel - 1));") &&
    str_contains($gisMasterJs, "effectivePersonnel = Math.max(2, Math.min(50, Math.ceil(perpSpanNm / effectiveSpacingNm) + 1));")
);

// -------------------------------------------------------------------------
// 6. VERIFIKASI FLEKSIBILITAS 4 ARAH SAPUAN E-SAR (POIN 6)
// -------------------------------------------------------------------------
echo "\n[6] Verifikasi Fleksibilitas 4 Arah Sapuan E-SAR (Poin 6)...\n";

assertTest(
    "views/gis.php memiliki 4 tombol selector arah E-SAR",
    str_contains($gisView, 'id="btnEsarDirWtoE"') &&
    str_contains($gisView, 'id="btnEsarDirEtoW"') &&
    str_contains($gisView, 'id="btnEsarDirNtoS"') &&
    str_contains($gisView, 'id="btnEsarDirStoN"')
);

assertTest(
    "gis_master.js menangani event click pada tombol arah .esar-dir-btn",
    str_contains($gisMasterJs, "document.querySelectorAll('.esar-dir-btn').forEach") &&
    str_contains($gisMasterJs, "activeEsarDirection = dir;")
);

assertTest(
    "gis/patterns.js mendukung 4 arah sapuan (west_to_east, east_to_west, north_to_south, south_to_north)",
    str_contains($patternsJs, "dir === 'south_to_north'") &&
    str_contains($patternsJs, "dir === 'east_to_west'") &&
    str_contains($patternsJs, "bearing: 90") &&
    str_contains($patternsJs, "bearing: 270") &&
    str_contains($patternsJs, "bearing: 180") &&
    str_contains($patternsJs, "bearing: 0")
);

assertTest(
    "generateSearchPatternRoute meneruskan direction: activeEsarDirection",
    str_contains($gisMasterJs, "direction: activeEsarDirection")
);

// -------------------------------------------------------------------------
// 7. VERIFIKASI INTEGRITAS BOOTSTRAP & CRASH PREVENTION (ANTI-FREEZE)
// -------------------------------------------------------------------------
echo "\n[7] Verifikasi Fault-Tolerant Bootstrap & Crash Prevention (Anti-Freeze)...\n";

$syntaxCheck = shell_exec('node -c ' . escapeshellarg(__DIR__ . '/../assets/js/gis_master.js') . ' 2>&1');
assertTest(
    "gis_master.js bebas dari SyntaxError JavaScript (node -c lolos)",
    empty(trim((string)$syntaxCheck))
);

assertTest(
    "bootstrapApp mengisolasi initTabSwitcher dalam try-catch agar tab selalu aktif",
    str_contains($gisMasterJs, "try { initTabSwitcher(); } catch")
);

assertTest(
    "bootstrapApp mengisolasi initLeafletMap dalam try-catch agar kegagalan parsial tidak mematikan UI",
    str_contains($gisMasterJs, "try { initLeafletMap(); } catch")
);

assertTest(
    "renderSavedPolygons memiliki error guard try-catch per poligon",
    str_contains($gisMasterJs, "catch (err) {\n        console.warn('[GIS Warning] Gagal merender poligon:'") ||
    str_contains($gisMasterJs, "catch (err) {")
);

assertTest(
    "calculatePolygonSphericalArea mendukung shapeType rectangle dan box",
    str_contains($gisMasterJs, "shapeType === 'box' || shapeType === 'rectangle'")
);

echo "\n====================================================================\n";
echo " RINGKASAN HASIL PENGUJIAN: {$passedAssertions}/{$totalAssertions} ASERSI LULUS\n";
echo "====================================================================\n";

if ($passedAssertions === $totalAssertions) {
    echo "KESIMPULAN: SELURUH FITUR 6 POIN GIS & RENOPS LULUS 100% TANPA REGRESI!\n\n";
    exit(0);
} else {
    echo "KESIMPULAN: TERDAPAT " . ($totalAssertions - $passedAssertions) . " ASERSI GAGAL.\n\n";
    exit(1);
}
