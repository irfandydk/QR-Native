<?php
/**
 * QR-SAR - Automated Test Suite: Fase 10 (Frontend JS Modular Decomposition)
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

echo "\n====================================================================\n";
echo "   UJI OTOMATIS FASE 10: FRONTEND JS MODULAR DECOMPOSITION          \n";
echo "====================================================================\n";

$rootDir = dirname(__DIR__);

// --- 1. MEMERIKSA EKSISTENSI BERKAS MODUL JAVASCRIPT BARU ---
echo "\n--- 1. MEMERIKSA EKSISTENSI BERKAS SUB-MODUL JAVASCRIPT ---\n";
$jsFiles = [
    'assets/js/gis/layers.js',
    'assets/js/gis/ui_search.js',
    'assets/js/gis/ui_points.js',
    'assets/js/gis/ui_polygons.js',
    'assets/js/gis/ui_patterns.js',
    'assets/js/gis/ui_routes.js',
    'assets/js/pages/operasi_form_map.js'
];

foreach ($jsFiles as $relPath) {
    $fullPath = $rootDir . '/' . $relPath;
    assertCondition("Berkas fisik {$relPath} tersedia", file_exists($fullPath));
    if (file_exists($fullPath)) {
        $content = file_get_contents($fullPath);
        assertCondition("{$relPath} menggunakan strict mode ('use strict')", str_contains($content, "'use strict'"));
        assertCondition("{$relPath} mendaftarkan namespace window.QRSAR", str_contains($content, 'window.QRSAR'));
    }
}

// --- 2. EVALUASI SINTAKS & FUNGSIONALITAS RUNTIME VIA NODE.JS ---
echo "\n--- 2. MENJALANKAN EVALUASI RUNTIME VIA NODE.JS ENGINE ---\n";

$nodeScript = <<<'JS'
const fs = require('fs');

// Mock browser environment
const window = {
  __OPS_FORM_CONFIG__: {
    defaultLat: -4.037857,
    defaultLng: 122.486653
  }
};
global.window = window;
global.document = {
  getElementById: () => null,
  querySelector: () => null,
  querySelectorAll: () => []
};

// Mock Leaflet
global.L = {
  tileLayer: (url, opts) => ({ url, opts }),
  divIcon: (opts) => ({ opts }),
  latLngBounds: () => ({ extend: () => {}, isValid: () => true }),
  polyline: () => ({ addTo: () => {}, setLatLngs: () => {} }),
  marker: () => ({ addTo: () => {}, on: () => {}, setLatLng: () => {} })
};

// Mock utils
window.QrSarUtils = {
  escapeHtml: (s) => String(s || '')
};

// 1. Muat Modul-Modul GIS UI
eval(fs.readFileSync('assets/js/gis/layers.js', 'utf8'));
eval(fs.readFileSync('assets/js/gis/ui_search.js', 'utf8'));
eval(fs.readFileSync('assets/js/gis/ui_points.js', 'utf8'));
eval(fs.readFileSync('assets/js/gis/ui_polygons.js', 'utf8'));
eval(fs.readFileSync('assets/js/gis/ui_patterns.js', 'utf8'));
eval(fs.readFileSync('assets/js/gis/ui_routes.js', 'utf8'));

// 2. Muat Modul Operasi Form Map
eval(fs.readFileSync('assets/js/pages/operasi_form_map.js', 'utf8'));

const results = {
  gisNamespace: !!(window.QRSAR && window.QRSAR.GIS),
  opsFormMapNamespace: !!(window.QRSAR && window.QRSAR.OperasiFormMap),
  layersValid: false,
  gazetteerValid: false,
  pointsPresetValid: false,
  polygonCalcValid: false,
  patternRaycastValid: false,
  routesCalcValid: false,
  dmsPartsValid: false,
  distanceCalcValid: false
};

// Uji Layers
if (window.QRSAR.GIS.Layers && typeof window.QRSAR.GIS.Layers.createTileLayers === 'function') {
  const tl = window.QRSAR.GIS.Layers.createTileLayers();
  results.layersValid = !!(tl && tl.hybrid && tl.osm && tl.roadmap && tl.satellite && tl.terrain);
}

// Uji Search Gazetteer
if (window.QRSAR.GIS.Search && Array.isArray(window.QRSAR.GIS.Search.localGazetteer)) {
  const matches = window.QRSAR.GIS.Search.localGazetteer.filter(item => item.name.toLowerCase().includes('kendari'));
  results.gazetteerValid = Array.isArray(matches) && matches.length > 0;
}

// Uji Points
if (window.QRSAR.GIS.Points && typeof window.QRSAR.GIS.Points.getVerifiedPresets === 'function') {
  const icons = window.QRSAR.GIS.Points.getVerifiedPresets();
  results.pointsPresetValid = Array.isArray(icons) && icons.length >= 5;
}

// Uji Polygons
if (window.QRSAR.GIS.Polygons && typeof window.QRSAR.GIS.Polygons.calculateArea === 'function') {
  const testCoords = [
    { lat: -4.0, lng: 122.4 },
    { lat: -4.0, lng: 122.6 },
    { lat: -4.2, lng: 122.6 },
    { lat: -4.2, lng: 122.4 }
  ];
  const area = window.QRSAR.GIS.Polygons.calculateArea(testCoords);
  results.polygonCalcValid = area && typeof area.km2 === 'number' && area.km2 > 100;
}

// Uji PatternsUI
if (window.QRSAR.GIS.PatternsUI && typeof window.QRSAR.GIS.PatternsUI.isPointInPolygon === 'function') {
  const poly = [
    { lat: -4.0, lng: 122.4 },
    { lat: -4.0, lng: 122.6 },
    { lat: -4.2, lng: 122.6 },
    { lat: -4.2, lng: 122.4 }
  ];
  const inside = window.QRSAR.GIS.PatternsUI.isPointInPolygon(-4.1, 122.5, poly);
  const outside = window.QRSAR.GIS.PatternsUI.isPointInPolygon(-5.0, 123.0, poly);
  results.patternRaycastValid = inside === true && outside === false;
}

// Uji Routes
if (window.QRSAR.GIS.Routes && typeof window.QRSAR.GIS.Routes.computeRouteMetrics === 'function') {
  const wps = [
    { lat: -4.037857, lng: 122.486653 },
    { lat: -3.976387, lng: 122.588475 }
  ];
  const route = window.QRSAR.GIS.Routes.computeRouteMetrics(wps, 25.0);
  results.routesCalcValid = route && typeof route.totalNm === 'number' && route.totalNm > 0;
}

// Uji OperasiFormMap DMS parts & Distance
if (window.QRSAR.OperasiFormMap) {
  const dms = window.QRSAR.OperasiFormMap.decimalToDmsParts(-4.037857, 122.486653);
  results.dmsPartsValid = dms && dms.latDeg === 4 && dms.latMin === 2 && dms.latDir === 'S' && dms.lngDeg === 122;

  const metrics = window.QRSAR.OperasiFormMap.calculateDistanceAndBearing(-4.037857, 122.486653, -3.976387, 122.588475);
  results.distanceCalcValid = metrics && metrics.nm > 0 && metrics.bearing >= 0 && metrics.bearing <= 360;
}

console.log(JSON.stringify(results));
JS;

$tempJsFile = sys_get_temp_dir() . '/test_phase10_' . uniqid() . '.js';
file_put_contents($tempJsFile, $nodeScript);

$nodeCmd = 'node ' . escapeshellarg($tempJsFile);
$nodeOutput = shell_exec($nodeCmd);
@unlink($tempJsFile);

$jsResults = json_decode((string)$nodeOutput, true);

assertCondition("Node.js berhasil mengeksekusi suite pengujian modul JS", is_array($jsResults));

if (is_array($jsResults)) {
    assertCondition("Namespace window.QRSAR.GIS terdefinisi dengan benar", $jsResults['gisNamespace'] ?? false);
    assertCondition("Namespace window.QRSAR.OperasiFormMap terdefinisi dengan benar", $jsResults['opsFormMapNamespace'] ?? false);
    assertCondition("Layers module menyediakan 5 provider basemap taktis", $jsResults['layersValid'] ?? false);
    assertCondition("UiSearch memuat gazetteer Sultra dan pencarian lokal", $jsResults['gazetteerValid'] ?? false);
    assertCondition("UiPoints menyediakan kategori preset icon maritim/darat", $jsResults['pointsPresetValid'] ?? false);
    assertCondition("UiPolygons menghitung luas area bola bumi (Spherical Area)", $jsResults['polygonCalcValid'] ?? false);
    assertCondition("UiPatterns menjalankan algoritma ray-casting point-in-polygon", $jsResults['patternRaycastValid'] ?? false);
    assertCondition("UiRoutes menghitung jarak nautical miles & heading waypoint", $jsResults['routesCalcValid'] ?? false);
    assertCondition("OperasiFormMap mengonversi desimal ke DMS parts secara akurat", $jsResults['dmsPartsValid'] ?? false);
    assertCondition("OperasiFormMap menghitung jarak & radial LKP akurat", $jsResults['distanceCalcValid'] ?? false);
}

// --- 3. MEMERIKSA INTEGRASI SCRIPT PADA VIEW TEMPLATES ---
echo "\n--- 3. MEMERIKSA INTEGRASI TAG SCRIPT PADA VIEW TEMPLATES ---\n";

$gisViewContent = file_get_contents($rootDir . '/views/gis.php');
assertCondition("views/gis.php memuat script layers.js", str_contains($gisViewContent, 'assets/js/gis/layers.js'));
assertCondition("views/gis.php memuat script ui_search.js", str_contains($gisViewContent, 'assets/js/gis/ui_search.js'));
assertCondition("views/gis.php memuat script ui_points.js", str_contains($gisViewContent, 'assets/js/gis/ui_points.js'));
assertCondition("views/gis.php memuat script ui_polygons.js", str_contains($gisViewContent, 'assets/js/gis/ui_polygons.js'));
assertCondition("views/gis.php memuat script ui_patterns.js", str_contains($gisViewContent, 'assets/js/gis/ui_patterns.js'));
assertCondition("views/gis.php memuat script ui_routes.js", str_contains($gisViewContent, 'assets/js/gis/ui_routes.js'));

$opsFormContent = file_get_contents($rootDir . '/views/operasi/form.php');
assertCondition("views/operasi/form.php memuat script operasi_form_map.js", str_contains($opsFormContent, 'assets/js/pages/operasi_form_map.js'));

echo "\n====================================================================\n";
echo "  Total Pemeriksaan Asersi : {$totalAssertions}\n";
echo "  Lulus (PASS)             : {$passedAssertions}\n";
echo "  Gagal (FAIL)             : " . ($totalAssertions - $passedAssertions) . "\n";
echo "====================================================================\n";

if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
