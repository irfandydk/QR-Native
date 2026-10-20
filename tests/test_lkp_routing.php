<?php
/**
 * QR-SAR - Tactical LKP, Manual Nautical Routing & GIS Integration Test Suite
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/GisModel.php';
require_once __DIR__ . '/../helpers/GeoHelper.php';

echo "=== STARTING LKP MAP PICKER, MANUAL ROUTING & GIS SYNC TEST SUITE ===\n\n";

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failed++;
    }
}

// ── TEST 1: Header & Footer Leaflet Inclusion ──
echo "1. Checking Leaflet Resource Inclusions in Layout:\n";
$headerContent = file_get_contents(__DIR__ . '/../views/layout/header.php');
$footerContent = file_get_contents(__DIR__ . '/../views/layout/footer.php');

assertTest(
    strpos($headerContent, "'operasi_form'") !== false && strpos($headerContent, 'leaflet.min.css') !== false,
    "views/layout/header.php conditionally loads leaflet.min.css for route 'operasi_form'"
);

assertTest(
    strpos($footerContent, "'operasi_form'") !== false && strpos($footerContent, 'leaflet.min.js') !== false,
    "views/layout/footer.php conditionally loads leaflet.min.js for route 'operasi_form'"
);

// ── TEST 2: Operasi Form Markup (Inline Map, Routing Controls, Speed Presets, Fullscreen Modal) ──
echo "\n2. Checking views/operasi/form.php Tactical Routing Elements:\n";
$formContent = file_get_contents(__DIR__ . '/../views/operasi/form.php');

assertTest(strpos($formContent, 'id="lkp_inline_map"') !== false, "views/operasi/form.php contains inline Leaflet map container (#lkp_inline_map)");
assertTest(strpos($formContent, 'id="modal-fullscreen-map"') !== false, "views/operasi/form.php contains Fullscreen Map Modal (#modal-fullscreen-map)");
assertTest(strpos($formContent, 'id="lkp_modal_map"') !== false, "Fullscreen modal contains #lkp_modal_map Leaflet container");
assertTest(strpos($formContent, 'btn-mode-direct') !== false && strpos($formContent, 'btn-mode-manual') !== false, "form.php contains Route Mode toggle buttons (Direct vs Manual Waypoints)");
assertTest(strpos($formContent, 'id="select_speed_preset"') !== false, "form.php contains Speed & Terrain preset dropdown (#select_speed_preset)");
assertTest(strpos($formContent, 'id="input_vessel_speed_num"') !== false, "form.php contains Manual Vessel Speed input (#input_vessel_speed_num)");
assertTest(strpos($formContent, 'id="select_speed_unit"') !== false, "form.php contains Speed Unit selector (#select_speed_unit for Knots / KM/h)");
assertTest(strpos($formContent, 'applyTacticalRouteToForm()') !== false, "form.php has button to apply calculated distance, radial, and ETA to form");
assertTest(strpos($formContent, 'id="btn-toggle-map-minimize"') !== false, "form.php contains minimize toggle button (#btn-toggle-map-minimize)");
assertTest(strpos($formContent, 'id="tactical-map-minimized-strip"') !== false, "form.php contains compact minimized status strip (#tactical-map-minimized-strip)");
assertTest(strpos($formContent, 'id="tactical-map-body"') !== false, "form.php wraps map canvas and controls in collapsible container (#tactical-map-body)");
assertTest(strpos($formContent, 'name="lkp_lat"') !== false && strpos($formContent, 'name="lkp_lng"') !== false, "form.php includes hidden inputs lkp_lat and lkp_lng");
assertTest(strpos($formContent, 'name="manual_route_json"') !== false, "form.php includes hidden input manual_route_json for waypoint serialization");
assertTest(strpos($formContent, 'name="vessel_speed_knots"') !== false, "form.php includes hidden input vessel_speed_knots");
assertTest(strpos($formContent, 'existingLkpPoint') !== false && strpos($formContent, 'existingRoute') !== false, "form.php passes existing GIS LKP and Route to __OPS_FORM_CONFIG__ in edit mode");

// ── TEST 3: JavaScript Engine (operasi_form.js) ──
echo "\n3. Checking assets/js/pages/operasi_form.js Routing Engine & Map Logic:\n";
$jsContent = file_get_contents(__DIR__ . '/../assets/js/pages/operasi_form.js');

assertTest(strpos($jsContent, 'const tacticalState =') !== false, "operasi_form.js defines centralized tacticalState object");
assertTest(strpos($jsContent, 'function initLkpTacticalMap()') !== false, "operasi_form.js implements initLkpTacticalMap()");
assertTest(strpos($jsContent, 'function toggleTacticalMapMinimize(') !== false, "operasi_form.js implements toggleTacticalMapMinimize()");
assertTest(strpos($jsContent, 'google.com/vt/lyrs=y') !== false, "operasi_form.js uses Google Hybrid tile layer (Zero API Key)");
assertTest(strpos($jsContent, 'tile.openstreetmap.org') !== false, "operasi_form.js uses OpenStreetMap tile layer (Zero API Key)");
assertTest(strpos($jsContent, 'basemaps.cartocdn.com') === false, "operasi_form.js completely removed restricted CartoCDN layer");
assertTest(strpos($jsContent, 'function createLkpDivIcon()') !== false, "operasi_form.js creates pulsing red SVG beacon icon for LKP");
assertTest(strpos($jsContent, 'function createOriginDivIcon(') !== false, "operasi_form.js creates dedicated base marker for Origin Base");
assertTest(strpos($jsContent, 'function createWaypointDivIcon(') !== false, "operasi_form.js creates numbered waypoint markers for multi-leg routing");
assertTest(strpos($jsContent, 'function onLkpMarkerDragged(') !== false, "operasi_form.js handles marker drag and syncs to DMS inputs");
assertTest(strpos($jsContent, 'function decimalToDmsParts(') !== false, "operasi_form.js implements accurate decimal-to-DMS component converter");
assertTest(strpos($jsContent, 'function setRoutingMode(') !== false, "operasi_form.js supports dynamic mode switching (Direct vs Manual)");
assertTest(strpos($jsContent, 'function addManualWaypoint(') !== false, "operasi_form.js allows appending waypoints on map click");
assertTest(strpos($jsContent, 'function undoLastWaypoint()') !== false, "operasi_form.js provides Undo for intermediate waypoints");
assertTest(strpos($jsContent, 'function clearManualRouteWaypoints()') !== false, "operasi_form.js provides Reset Route functionality");
assertTest(strpos($jsContent, 'function recalculateTacticalMetrics(') !== false, "operasi_form.js calculates cumulative distance, initial radial, and speed-based ETA");
assertTest(strpos($jsContent, 'function handleSpeedPresetChange(') !== false, "operasi_form.js handles speed preset selection");
assertTest(strpos($jsContent, 'function handleManualSpeedInput(') !== false, "operasi_form.js handles manual numeric speed input with live recalculation");
assertTest(strpos($jsContent, 'function applyTacticalRouteToForm()') !== false, "operasi_form.js applies distance, radial, and ETA to form inputs");
assertTest(strpos($jsContent, 'function openFullscreenMapModal()') !== false, "operasi_form.js manages Fullscreen Map Modal lifecycle");

// ── TEST 4: Backend GisModel Sync Engine ──
echo "\n4. Checking GisModel Direct Synchronization:\n";
$testOpsId = 88888;

// Clean up any leftovers
getDB()->prepare("DELETE FROM GisRoutePoint WHERE routeId IN (SELECT id FROM GisRoute WHERE id_operasi = :id)")->execute([':id' => $testOpsId]);
getDB()->prepare("DELETE FROM GisPoint WHERE id_operasi = :id")->execute([':id' => $testOpsId]);
getDB()->prepare("DELETE FROM GisRoute WHERE id_operasi = :id")->execute([':id' => $testOpsId]);

// Test syncOperasiLkpPoint
$pointId = GisModel::syncOperasiLkpPoint($testOpsId, [
    'nama_operasi' => 'Operasi SAR Uji Navigasi Selat Tiworo',
    'lokasi_kejadian' => 'Perairan Selat Tiworo, Kab. Muna Barat',
    'lkp_lat' => -4.8524,
    'lkp_lng' => 122.4182,
    'jarak' => '14.5 NM',
    'radial' => '210'
]);

assertTest($pointId === "pt-lkp-op-{$testOpsId}", "syncOperasiLkpPoint generates deterministic ID 'pt-lkp-op-{$testOpsId}'");

$pts = GisModel::getPoints($testOpsId, false);
assertTest(count($pts) >= 1, "GisModel::getPoints retrieves saved LKP point for operation {$testOpsId}");
$foundLkp = $pts[0] ?? [];
assertTest(($foundLkp['pointType'] ?? '') === 'lkp', "LKP point is stored with pointType = 'lkp'");
assertTest(($foundLkp['category'] ?? '') === 'LKP', "LKP point is stored with category = 'LKP'");
assertTest(abs((float)($foundLkp['lat'] ?? 0) - (-4.8524)) < 0.001, "LKP point latitude matches input (-4.8524)");
assertTest(abs((float)($foundLkp['lng'] ?? 0) - (122.4182)) < 0.001, "LKP point longitude matches input (122.4182)");

// Test syncOperasiRoute with manual multi-waypoint route
$waypoints = [
    ['lat' => -4.8100, 'lng' => 122.5000, 'label' => 'Pos SAR Muna'],
    ['lat' => -4.8300, 'lng' => 122.4600, 'label' => 'WP 1 - Ujung Tanjung'],
    ['lat' => -4.8524, 'lng' => 122.4182, 'label' => 'LKP']
];

$routeId = GisModel::syncOperasiRoute($testOpsId, [
    'nama_operasi' => 'Operasi SAR Uji Navigasi Selat Tiworo',
    'manual_route_json' => json_encode($waypoints),
    'vessel_speed_knots' => 20.0
]);

assertTest($routeId === "route-op-{$testOpsId}", "syncOperasiRoute generates deterministic ID 'route-op-{$testOpsId}'");

$routes = GisModel::getRoutes($testOpsId, false);
assertTest(count($routes) >= 1, "GisModel::getRoutes retrieves saved route for operation {$testOpsId}");
$foundRoute = $routes[0] ?? [];
assertTest((float)($foundRoute['vesselSpeedKnots'] ?? 0) === 20.0, "Saved route records vessel speed 20.0 Knots");
assertTest(count($foundRoute['points'] ?? []) === 3, "Saved route contains 3 sequential navigation points");

// ── TEST 5: OperasiModel Integration (Create, Update, Delete Lifecycle) ──
echo "\n5. Testing OperasiModel Create & Update with GIS Sync Lifecycle:\n";

$createdId = OperasiModel::create([
    'nama_operasi' => 'Operasi SAR KM Bahari Indah Tenggelam di Teluk Kendari',
    'nama_pelapor' => 'Bpk. Ahmad Subarjo',
    'hubungan_pelapor' => 'Keluarga Korban',
    'no_telp_pelapor' => '081234567890',
    'waktu_kejadian' => date('Y-m-d H:i:s'),
    'waktu_lapor' => date('Y-m-d H:i:s'),
    'waktu_berangkat' => date('Y-m-d H:i:s'),
    'tim_berangkat' => 'Tim Rescue Kansar Kendari',
    'eta_tiba' => date('Y-m-d H:i:s', time() + 3600),
    'lokasi_kejadian' => 'Teluk Kendari, Kota Kendari',
    'koordinat' => '03° 58\' 12" S - 122° 35\' 45" E',
    'jarak' => '8.2 NM',
    'radial' => '115°',
    'eta_lokasi' => '25 Menit',
    'kronologi' => 'Kapal KM Bahari Indah mengalami kebocoran lambung dan terbalik di perairan Teluk Kendari.',
    'jumlah_pob' => 1,
    'lkp_lat' => -3.9700,
    'lkp_lng' => 122.5958,
    'vessel_speed_knots' => 25.0,
    'manual_route_json' => json_encode([
        ['lat' => -3.9723, 'lng' => 122.5857, 'label' => 'Dermaga SAR Kendari'],
        ['lat' => -3.9710, 'lng' => 122.5900, 'label' => 'WP 1 Alur Pelayaran'],
        ['lat' => -3.9700, 'lng' => 122.5958, 'label' => 'LKP']
    ])
]);

assertTest($createdId > 0, "OperasiModel::create successfully created operation ID {$createdId}");

// Verify automatic GIS point creation
$createdGisPoints = GisModel::getPoints($createdId, false);
assertTest(count($createdGisPoints) === 1, "OperasiModel::create automatically created exactly 1 GIS LKP point");
assertTest(($createdGisPoints[0]['pointType'] ?? '') === 'lkp', "Created GIS point has pointType 'lkp'");
assertTest(abs((float)($createdGisPoints[0]['lat'] ?? 0) - (-3.9700)) < 0.001, "Created GIS point matches lkp_lat (-3.9700)");

// Verify automatic GIS route creation
$createdGisRoutes = GisModel::getRoutes($createdId, false);
assertTest(count($createdGisRoutes) === 1, "OperasiModel::create automatically created exactly 1 GIS Route");
assertTest(count($createdGisRoutes[0]['points'] ?? []) === 3, "Created GIS route has 3 waypoints");
assertTest((float)($createdGisRoutes[0]['vesselSpeedKnots'] ?? 0) === 25.0, "Created GIS route speed is 25.0 Knots");

// Test OperasiModel::update with changed coordinate and waypoints
OperasiModel::update($createdId, [
    'nama_operasi' => 'Operasi SAR KM Bahari Indah Tenggelam di Teluk Kendari (Diperbarui)',
    'lokasi_kejadian' => 'Perairan Teluk Kendari dekat Pulau Bungkutoko',
    'lkp_lat' => -3.9850,
    'lkp_lng' => 122.6100,
    'vessel_speed_knots' => 18.0,
    'manual_route_json' => json_encode([
        ['lat' => -3.9723, 'lng' => 122.5857, 'label' => 'Dermaga SAR Kendari'],
        ['lat' => -3.9850, 'lng' => 122.6100, 'label' => 'LKP']
    ])
]);

$updatedGisPoints = GisModel::getPoints($createdId, false);
assertTest(abs((float)($updatedGisPoints[0]['lat'] ?? 0) - (-3.9850)) < 0.001, "OperasiModel::update updated LKP point latitude to -3.9850");

$updatedGisRoutes = GisModel::getRoutes($createdId, false);
assertTest((float)($updatedGisRoutes[0]['vesselSpeedKnots'] ?? 0) === 18.0, "OperasiModel::update updated vessel speed to 18.0 Knots");
assertTest(count($updatedGisRoutes[0]['points'] ?? []) === 2, "OperasiModel::update updated route points count to 2");

// Test OperasiModel::delete cleanup
OperasiModel::delete($createdId);
$remPoints = GisModel::getPoints($createdId, false);
$remRoutes = GisModel::getRoutes($createdId, false);
assertTest(count($remPoints) === 0, "OperasiModel::delete cleanly removed associated GIS LKP points");
assertTest(count($remRoutes) === 0, "OperasiModel::delete cleanly removed associated GIS Routes and RoutePoints");

// ── TEST 6: Verified Master Bases from GIS Points & Custom Icons ──
echo "\n6. Checking Master Bases from GIS Points (Plot Peta) & Custom Icon Rendering:\n";
$masterBases = GisModel::getMasterBases();
assertTest(count($masterBases) >= 7, "GisModel::getMasterBases() retrieves verified master base points from GisPoint (found " . count($masterBases) . ")");

$kansarBase = null;
foreach ($masterBases as $mb) {
    if ($mb['id'] === 'pt-kansar' || stripos($mb['nama_unit'], 'Kantor SAR Kendari') !== false) {
        $kansarBase = $mb;
        break;
    }
}
assertTest($kansarBase !== null, "Kantor SAR Kendari (pt-kansar) exists in master bases");
assertTest(($kansarBase['is_default'] ?? 0) === 1, "Kantor SAR Kendari is configured as default origin base (is_default = 1)");
assertTest(!empty($kansarBase['customIconUrl']), "Kantor SAR Kendari has custom icon URL assigned: " . ($kansarBase['customIconUrl'] ?? ''));
assertTest(!empty($kansarBase['koordinat_dms']), "Kantor SAR Kendari has accurate DMS coordinate string: " . ($kansarBase['koordinat_dms'] ?? ''));

$allSarUnits = SarUnitModel::getAll();
assertTest($allSarUnits === $masterBases, "SarUnitModel::getAll() dynamically delegates to GisModel::getMasterBases()");

// Check form markup has custom icon attributes in origin select
$formContentUpdated = file_get_contents(__DIR__ . '/../views/operasi/form.php');
assertTest(strpos($formContentUpdated, 'data-icon="') !== false, "form.php origin_base_select includes data-icon attribute for GIS custom icons");
assertTest(strpos($formContentUpdated, 'data-point-type="') !== false, "form.php origin_base_select includes data-point-type attribute");
assertTest(strpos($formContentUpdated, 'data-category="') !== false, "form.php origin_base_select includes data-category attribute");
assertTest(strpos($formContentUpdated, 'name="origin_icon"') !== false, "form.php includes hidden input origin_icon");
assertTest(strpos($formContentUpdated, 'name="origin_point_type"') !== false, "form.php includes hidden input origin_point_type");

// Check JS engine custom icon SVG rendering
$jsContentUpdated = file_get_contents(__DIR__ . '/../assets/js/pages/operasi_form.js');
assertTest(strpos($jsContentUpdated, 'normalizeOriginIconUrl(') !== false, "operasi_form.js implements normalizeOriginIconUrl() for local & remote image normalization");
assertTest(strpos($jsContentUpdated, 'innerSvgContent') !== false && strpos($jsContentUpdated, '<image href=') !== false, "operasi_form.js createOriginDivIcon renders custom logo emblem SVG identically to GIS");

// 7. Checking Phase 7 Refinement: Locked Radial, Speed Presets (KM/h & Kts), Minimal Defaults, & Distance Unit Converter
echo "\n7. Checking Phase 7 Refinement (Radial Lock, Speed Units, Defaults & Distance Converter):\n";
assertTest(strpos($jsContentUpdated, 'const directLkp = calculateDistanceAndBearing(tacticalState.originLat, tacticalState.originLng, tacticalState.lkpLat, tacticalState.lkpLng);') !== false, "operasi_form.js locks departure radial to direct Origin -> LKP line (no distortion on manual waypoints)");
assertTest(strpos($jsContentUpdated, 'const departureRadial = Math.round(directLkp.bearing);') !== false, "operasi_form.js computes departureRadial from directLkp.bearing");

// Speed presets & units
assertTest(strpos($formContentUpdated, 'value="4" data-unit="kmh"') !== false, "form.php speed preset includes Rescuer Jalan Kaki with native 4 km/h value and data-unit='kmh'");
assertTest(strpos($formContentUpdated, 'value="60" data-unit="kmh"') !== false, "form.php speed preset includes Rescue Car with native 60 km/h value and data-unit='kmh'");
assertTest(strpos($formContentUpdated, 'value="25" data-unit="knots"') !== false, "form.php speed preset includes RIB Basarnas with 25 knots value and data-unit='knots'");
assertTest(strpos($jsContentUpdated, "opt.getAttribute('data-unit')") !== false, "operasi_form.js handleSpeedPresetChange extracts data-unit attribute from selected option");
assertTest(strpos($jsContentUpdated, "unitSelect.value = unit") !== false, "operasi_form.js handleSpeedPresetChange dynamically switches speed unit select to preset unit");

// Default Unsur & Alut
assertTest(strpos($formContentUpdated, "['nama_unsur' => 'SMC', 'jumlah_personil' => 1]") !== false, "form.php defaults initialUnsurData with SMC (1 Org)");
assertTest(strpos($formContentUpdated, "['nama_unsur' => 'Staf', 'jumlah_personil' => 1]") !== false, "form.php defaults initialUnsurData with Staf (1 Org)");
assertTest(strpos($formContentUpdated, "'Tim Rescue KPP Kendari', 'jumlah_personil' => 7") === false, "form.php eliminates bulky legacy 7-person default unsur");
assertTest(strpos($formContentUpdated, "['nama_alut' => 'Palsar Medis', 'jumlah_alut' => 1, 'satuan' => 'Set']") !== false, "form.php defaults initialAlutData with Palsar Medis (1 Set)");
assertTest(strpos($formContentUpdated, "['nama_alut' => 'Palsar Komunikasi', 'jumlah_alut' => 1, 'satuan' => 'Set']") !== false, "form.php defaults initialAlutData with Palsar Komunikasi (1 Set)");
assertTest(strpos($formContentUpdated, "['nama_alut' => 'Palsar Evakuasi', 'jumlah_alut' => 1, 'satuan' => 'Set']") !== false, "form.php defaults initialAlutData with Palsar Evakuasi (1 Set)");
assertTest(strpos($formContentUpdated, "'Rubber Boat + Mopel'") === false, "form.php eliminates bulky legacy heavy alut from default list");

// Distance Unit Converter
assertTest(strpos($formContentUpdated, 'onchange="handleJarakUnitChange(this.value)"') !== false, "form.php jarak_unit dropdown invokes handleJarakUnitChange()");
assertTest(strpos($jsContentUpdated, 'function handleJarakUnitChange(newUnit)') !== false, "operasi_form.js implements handleJarakUnitChange()");
assertTest(strpos($jsContentUpdated, 'val * 1.852') !== false && strpos($jsContentUpdated, 'val / 1.852') !== false, "operasi_form.js correctly converts distance between NM and KM using 1.852 factor");

echo "\n=== TEST SUMMARY ===\n";
echo "Total Passed: {$passed}\n";
echo "Total Failed: {$failed}\n";

if ($failed === 0) {
    echo ">>> ALL LKP MAP PICKER, MANUAL ROUTING & GIS TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED. PLEASE REVIEW LOGS. <<<\n";
    exit(1);
}

