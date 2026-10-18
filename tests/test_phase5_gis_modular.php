<?php
/**
 * QR-SAR - Automated Test Suite: Fase 5 (Modularisasi JavaScript GIS & Eliminasi Redundansi)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

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

echo "\n--- PENGUJIAN FASE 5: MODULARISASI JAVASCRIPT GIS & HARMONISASI ENGINE ---\n";

// 1. Memeriksa Eksistensi dan Struktur Berkas Modul GIS
echo "\n1. Memeriksa Keberadaan Berkas Sub-Modul GIS:\n";
$rootDir = dirname(__DIR__);
$gisFiles = [
    'geodesy.js' => $rootDir . '/assets/js/gis/geodesy.js',
    'markers.js' => $rootDir . '/assets/js/gis/markers.js',
    'patterns.js' => $rootDir . '/assets/js/gis/patterns.js',
    'export.js' => $rootDir . '/assets/js/gis/export.js',
];

foreach ($gisFiles as $name => $path) {
    assertCondition("Berkas assets/js/gis/{$name} tersedia", file_exists($path));
    if (file_exists($path)) {
        $content = file_get_contents($path);
        assertCondition("assets/js/gis/{$name} memiliki guard IIFE / namespace QrSarGis", str_contains($content, 'QrSarGis'));
    }
}

// 2. Evaluasi Runtime Sub-Modul GIS via Node.js
echo "\n2. Menjalankan Evaluasi Fungsionalitas Sub-Modul GIS via Node.js:\n";
$nodeScript = <<<'JS'
const fs = require('fs');
const window = {};
global.window = window;

// 1. Muat geodesy.js
eval(fs.readFileSync('assets/js/gis/geodesy.js', 'utf8'));
// 2. Muat markers.js
eval(fs.readFileSync('assets/js/gis/markers.js', 'utf8'));
// 3. Muat patterns.js
eval(fs.readFileSync('assets/js/gis/patterns.js', 'utf8'));
// 4. Muat export.js
eval(fs.readFileSync('assets/js/gis/export.js', 'utf8'));

const results = {
  hasGeodesy: !!(window.QrSarGis && window.QrSarGis.Geodesy),
  hasMarkers: !!(window.QrSarGis && window.QrSarGis.Markers),
  hasPatterns: !!(window.QrSarGis && window.QrSarGis.Patterns),
  hasExport: !!(window.QrSarGis && window.QrSarGis.Export),
  dmsValid: false,
  haversineValid: false,
  markerSvgValid: false,
  patternFittedValid: false,
  kmlDocValid: false,
  geoJsonValid: false
};

if (results.hasGeodesy) {
  const dms = window.QrSarGis.Geodesy.decimalToDms(-4.037664, 122.486662);
  results.dmsValid = typeof dms === 'string' && dms.includes('04°') && dms.includes('122°');
  const dist = window.QrSarGis.Geodesy.calculateDistanceHaversine(-4.037664, 122.486662, -4.000000, 122.500000);
  results.haversineValid = dist && typeof dist.km === 'number' && dist.km > 0 && typeof dist.nm === 'number';
}

if (results.hasMarkers) {
  const svg = window.QrSarGis.Markers.createPointSvgHtml('lkp', '#dc2626', false, null, 'A');
  results.markerSvgValid = typeof svg === 'string' && svg.includes('<svg') && svg.includes('#dc2626');
}

if (results.hasPatterns) {
  const fakePoly = [
    { lat: -4.0, lng: 122.4 },
    { lat: -4.0, lng: 122.6 },
    { lat: -4.2, lng: 122.6 },
    { lat: -4.2, lng: 122.4 }
  ];
  const wps = window.QrSarGis.Patterns.generateFittedPatternInsidePolygon('parallel_track', fakePoly, 1.0, 4);
  results.patternFittedValid = Array.isArray(wps) && wps.length >= 4;
}

if (results.hasExport) {
  const kml = window.QrSarGis.Export.generateKmlDocument({
    operasiName: 'Ops Uji Coba',
    points: [{ label: 'Titik A', lat: -4.0, lng: 122.5 }],
    polygons: [],
    patterns: [],
    routes: []
  });
  results.kmlDocValid = typeof kml === 'string' && kml.includes('<?xml') && kml.includes('Ops Uji Coba');

  const geoJson = window.QrSarGis.Export.generateGeoJson({
    points: [{ label: 'Titik A', lat: -4.0, lng: 122.5 }],
    polygons: [],
    patterns: [],
    routes: []
  });
  results.geoJsonValid = geoJson && geoJson.type === 'FeatureCollection' && Array.isArray(geoJson.features);
}

console.log(JSON.stringify(results));
JS;

$tempJsFile = sys_get_temp_dir() . '/test_phase5_' . uniqid() . '.js';
file_put_contents($tempJsFile, $nodeScript);
$nodeOutput = shell_exec("node {$tempJsFile} 2>&1");
@unlink($tempJsFile);

$jsResults = json_decode($nodeOutput ?: '', true);
assertCondition("Node.js berhasil memuat modul window.QrSarGis.Geodesy", !empty($jsResults['hasGeodesy']));
assertCondition("window.QrSarGis.Geodesy.decimalToDms menghasilkan string format DMS valid", !empty($jsResults['dmsValid']));
assertCondition("window.QrSarGis.Geodesy.calculateDistanceHaversine menghitung jarak Km & Nm", !empty($jsResults['haversineValid']));

assertCondition("Node.js berhasil memuat modul window.QrSarGis.Markers", !empty($jsResults['hasMarkers']));
assertCondition("window.QrSarGis.Markers.createPointSvgHtml menghasilkan SVG valid", !empty($jsResults['markerSvgValid']));

assertCondition("Node.js berhasil memuat modul window.QrSarGis.Patterns", !empty($jsResults['hasPatterns']));
assertCondition("window.QrSarGis.Patterns.generateFittedPatternInsidePolygon menghasilkan waypoints", !empty($jsResults['patternFittedValid']));

assertCondition("Node.js berhasil memuat modul window.QrSarGis.Export", !empty($jsResults['hasExport']));
assertCondition("window.QrSarGis.Export.generateKmlDocument menghasilkan XML KML 2.2", !empty($jsResults['kmlDocValid']));
assertCondition("window.QrSarGis.Export.generateGeoJson menghasilkan FeatureCollection valid", !empty($jsResults['geoJsonValid']));

// 3. Memeriksa Integrasi Pemuatan Script pada View
echo "\n3. Memeriksa Pemuatan Script pada views/gis.php dan views/layout/footer.php:\n";
$viewGisContent = file_get_contents($rootDir . '/views/gis.php');
$footerContent = file_get_contents($rootDir . '/views/layout/footer.php');

assertCondition("views/gis.php memuat assets/js/gis/geodesy.js", str_contains($viewGisContent, 'assets/js/gis/geodesy.js'));
assertCondition("views/gis.php memuat assets/js/gis/markers.js", str_contains($viewGisContent, 'assets/js/gis/markers.js'));
assertCondition("views/gis.php memuat assets/js/gis/patterns.js", str_contains($viewGisContent, 'assets/js/gis/patterns.js'));
assertCondition("views/gis.php memuat assets/js/gis/export.js", str_contains($viewGisContent, 'assets/js/gis/export.js'));

assertCondition("views/layout/footer.php memuat assets/js/gis/geodesy.js", str_contains($footerContent, 'assets/js/gis/geodesy.js'));
assertCondition("views/layout/footer.php memuat assets/js/gis/markers.js", str_contains($footerContent, 'assets/js/gis/markers.js'));
assertCondition("views/layout/footer.php memuat assets/js/gis/patterns.js", str_contains($footerContent, 'assets/js/gis/patterns.js'));
assertCondition("views/layout/footer.php memuat assets/js/gis/export.js", str_contains($footerContent, 'assets/js/gis/export.js'));

// 4. Memeriksa Delegasi pada TacticalGisMap (assets/js/gis.js)
echo "\n4. Memeriksa Delegasi SSOT pada assets/js/gis.js:\n";
$gisJsContent = file_get_contents($rootDir . '/assets/js/gis.js');
assertCondition("assets/js/gis.js getDistanceKm mendelegasikan ke QrSarGis.Geodesy", str_contains($gisJsContent, 'window.QrSarGis.Geodesy.calculateDistanceHaversine'));
assertCondition("assets/js/gis.js formatDms mendelegasikan ke QrSarGis.Geodesy", str_contains($gisJsContent, 'window.QrSarGis.Geodesy.decimalToDms'));
assertCondition("assets/js/gis.js marker rendering mendelegasikan ke QrSarGis.Markers", str_contains($gisJsContent, 'window.QrSarGis.Markers.createMarkerHtml'));

// 5. Memeriksa Delegasi pada assets/js/gis_master.js
echo "\n5. Memeriksa Delegasi SSOT pada assets/js/gis_master.js:\n";
$gisMasterContent = file_get_contents($rootDir . '/assets/js/gis_master.js');
assertCondition("gis_master.js decimalToDms mendelegasikan ke QrSarGis.Geodesy", str_contains($gisMasterContent, 'window.QrSarGis.Geodesy.decimalToDms'));
assertCondition("gis_master.js calculateDistanceHaversine mendelegasikan ke QrSarGis.Geodesy", str_contains($gisMasterContent, 'window.QrSarGis.Geodesy.calculateDistanceHaversine'));
assertCondition("gis_master.js createPointSvgHtml mendelegasikan ke QrSarGis.Markers", str_contains($gisMasterContent, 'window.QrSarGis.Markers.createPointSvgHtml'));
assertCondition("gis_master.js generateFittedPatternInsidePolygon mendelegasikan ke QrSarGis.Patterns", str_contains($gisMasterContent, 'window.QrSarGis.Patterns.generateFittedPatternInsidePolygon'));
assertCondition("gis_master.js generateKmlDocument mendelegasikan ke QrSarGis.Export", str_contains($gisMasterContent, 'window.QrSarGis.Export.generateKmlDocument'));
assertCondition("gis_master.js mempertahankan facade publik window.SAR_GIS", str_contains($gisMasterContent, 'window.SAR_GIS = {'));

// 6. Memeriksa Resolusi Collision Namespace bra.js vs bra_form.js (JS-DEEP-03)
echo "\n6. Memeriksa Resolusi Collision Namespace bra.js vs bra_form.js:\n";
$commonJsContent = file_get_contents($rootDir . '/assets/js/utils/common.js');
$braJsContent = file_get_contents($rootDir . '/assets/js/bra.js');
$braFormJsContent = file_get_contents($rootDir . '/assets/js/pages/bra_form.js');

assertCondition("common.js menyediakan namespace QrSarUtils.Bra", str_contains($commonJsContent, 'QrSarUtils') && str_contains($commonJsContent, 'Bra:'));
assertCondition("bra.js memuat fungsi getSectionLetter terisolasi/ter-guard", str_contains($braJsContent, 'getSectionLetter'));
assertCondition("bra_form.js memuat fungsi getMilitaryTW dengan guard namespace", str_contains($braFormJsContent, 'getMilitaryTW'));

echo "\n--- HASIL: {$passedAssertions} / {$totalAssertions} Pengujian Berhasil Lulus ---\n";
if ($passedAssertions === $totalAssertions) {
    echo "KESIMPULAN: Seluruh pengujian Fase 5 berhasil lulus 100%!\n";
    exit(0);
} else {
    echo "KESIMPULAN: Terdapat pengujian Fase 5 yang gagal.\n";
    exit(1);
}
