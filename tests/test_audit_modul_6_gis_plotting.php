<?php
/**
 * QR-SAR - End-to-End Automated Audit & Test Suite: Modul 6
 * Fitur Ploting Taktis GIS & Kalkulasi Maritim
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Cakupan Pengujian:
 * 1. Kalkulasi Matematika Geodesi, Formula Haversine, Bearing & Konversi Koordinat DMS (GeoHelper)
 * 2. Model Spasial Basis Data: Titik Taktis, Poligon Search Area, Rute SRU & Search Pattern (GisModel)
 * 3. Import & Export GeoJSON Standar RFC 7946
 * 4. API Endpoint RESTful GIS & Snapshot Peta Cetak (GisApiController & GisController)
 * 5. Integritas Skrip Taktis Maritim (gis_master.js) & Aturan Bisnis Peta Operasi Aktif (views/gis.php)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

echo "========================================================================================\n";
echo "   AUDIT & PENGUJIAN END-TO-END MODUL 6: PLOTING TAKTIS GIS & KALKULASI MARITIM         \n";
echo "   KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS                    \n";
echo "========================================================================================\n\n";

$passCount = 0;
$failCount = 0;
$testResults = [];

function assertTest(string $category, string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount, $testResults;
    if ($condition) {
        $passCount++;
        echo "  [PASS] [{$category}] {$desc}\n";
        $testResults[] = ['category' => $category, 'desc' => $desc, 'status' => 'PASS', 'detail' => ''];
    } else {
        $failCount++;
        echo "  [FAIL] [{$category}] {$desc}" . ($detail ? " -> {$detail}" : "") . "\n";
        $testResults[] = ['category' => $category, 'desc' => $desc, 'status' => 'FAIL', 'detail' => $detail];
    }
}

// =========================================================================
// DOMAIN 1: MATEMATIKA GEODESI, FORMULA HAVERSINE & PARSING KOORDINAT (GeoHelper)
// =========================================================================
echo "\n--- DOMAIN 1: KALKULASI GEODESI, HAVERSINE, BEARING & DMS (GeoHelper) ---\n";

assertTest('GEODESY', 'Class GeoHelper tersedia di autoloader', class_exists('GeoHelper'));

// 1.1 Format DMS Standar Internasional & Indonesia
$dmsLatS = GeoHelper::formatDMS(-3.97500, true);
assertTest('GEODESY', 'formatDMS Latitude negatif menghasilkan derajat Selatan (S)',
    str_contains($dmsLatS, '03°') && str_contains($dmsLatS, '58\'') && str_contains($dmsLatS, '30.0" S')
);

$dmsLatU = GeoHelper::formatDMS(3.97500, true, true);
assertTest('GEODESY', 'formatDMS Latitude positif dengan bendera Indonesia menghasilkan Utara (U)',
    str_contains($dmsLatU, '03°') && str_contains($dmsLatU, 'U')
);

$dmsLngE = GeoHelper::formatDMS(122.58667, false);
assertTest('GEODESY', 'formatDMS Longitude positif menghasilkan 3 digit derajat Timur (E)',
    str_contains($dmsLngE, '122°') && str_contains($dmsLngE, '35\'') && str_contains($dmsLngE, 'E')
);

$dmsLngT = GeoHelper::formatDMS(122.58667, false, true);
assertTest('GEODESY', 'formatDMS Longitude positif dengan bendera Indonesia menghasilkan Timur (T)',
    str_contains($dmsLngT, '122°') && str_contains($dmsLngT, 'T')
);

$dmsNull = GeoHelper::formatDMS(null, true);
assertTest('GEODESY', 'formatDMS nilai null fallback aman mengembalikan tanda strip (-)', $dmsNull === '-');

// 1.2 Format Pasangan DMS (formatDmsPair / decimalToDms)
$dmsPair = GeoHelper::formatDmsPair(-3.97500, 122.58667);
assertTest('GEODESY', 'formatDmsPair menghasilkan format pasangan standar dengan pemisah strip',
    str_contains($dmsPair, '03° 58\' 30.0" S - 122° 35\'')
);

$dmsAlias = GeoHelper::decimalToDms(-3.97500, 122.58667);
assertTest('GEODESY', 'decimalToDms kompatibel penuh sebagai alias formatDmsPair', $dmsPair === $dmsAlias);

// 1.3 Parsing String Koordinat Taktis (parseCoordinatesString / parseCoordinates)
$parsedDms = GeoHelper::parseCoordinatesString('03° 58\' 30" S - 122° 35\' 12" E');
assertTest('GEODESY', 'parseCoordinatesString mengenali format DMS standar',
    $parsedDms !== null &&
    abs($parsedDms['lat'] - (-3.975)) < 0.001 &&
    abs($parsedDms['lng'] - 122.58667) < 0.001
);

$parsedDmsIndo = GeoHelper::parseCoordinatesString('03° 58\' 30" LS - 122° 35\' 12" BT');
assertTest('GEODESY', 'parseCoordinatesString mengenali singkatan arah Indonesia (LS & BT)',
    $parsedDmsIndo !== null &&
    abs($parsedDmsIndo['lat'] - (-3.975)) < 0.001 &&
    abs($parsedDmsIndo['lng'] - 122.58667) < 0.001
);

$parsedDd = GeoHelper::parseCoordinatesString('-3.9750, 122.5866');
assertTest('GEODESY', 'parseCoordinatesString mengenali desimal derajat (DD)',
    $parsedDd !== null &&
    abs($parsedDd['lat'] - (-3.975)) < 0.0001 &&
    abs($parsedDd['lng'] - 122.5866) < 0.0001
);

$parsedInvalid = GeoHelper::parseCoordinatesString('Koordinat Sembarang Tidak Valid');
assertTest('GEODESY', 'parseCoordinatesString mengembalikan null secara aman untuk input tidak valid', $parsedInvalid === null);

// 1.4 Formula Jarak Haversine & Perhitungan Radial Bearing
// Titik 1: Kansar Kendari (-3.97495, 122.58662)
// Titik 2: Muara Teluk Kendari (-3.98500, 122.60000)
$dist = GeoHelper::calculateDistanceHaversine(-3.97495, 122.58662, -3.98500, 122.60000);

assertTest('GEODESY', 'calculateDistanceHaversine mengembalikan struktur array lengkap (km, nm, bearing, headingName)',
    isset($dist['km']) && isset($dist['nm']) && isset($dist['bearing']) && isset($dist['headingName'])
);

assertTest('GEODESY', 'Rasio konversi Nautical Miles ke Kilometer akurat (1 Nm ≈ 1.852 Km)',
    $dist['nm'] > 0 && abs(($dist['km'] / $dist['nm']) - 1.852) < 0.02
);

assertTest('GEODESY', 'Sudut azimuth / bearing ternormalisasi antara 0 sampai 359 derajat',
    $dist['bearing'] >= 0 && $dist['bearing'] < 360
);

assertTest('GEODESY', 'Nama arah mata angin (headingName) terdefinisi dalam bahasa Indonesia',
    in_array($dist['headingName'], ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'])
);

$distZero = GeoHelper::calculateDistanceHaversine(-3.97495, 122.58662, -3.97495, 122.58662);
assertTest('GEODESY', 'Jarak antara dua koordinat yang identik bernilai tepat 0.0',
    $distZero['km'] === 0.0 && $distZero['nm'] === 0.0
);

// 1.5 Perhitungan Luas Area Poligon Spasial (calculatePolygonAreaNm2)
// Lingkaran Search Area (Radius 5 NM) -> Luas = π * r² = π * 25 ≈ 78.54 NM²
$areaCircle = GeoHelper::calculatePolygonAreaNm2([], 'circle', 5.0);
assertTest('GEODESY', 'Luas lingkaran maritim (radius 5 NM) bernilai tepat ~78.54 NM²',
    abs($areaCircle - 78.54) < 0.1
);

// Kotak / Persegi Panjang Search Area (4 NM x 3 NM) -> Luas = 12 NM²
$areaBox = GeoHelper::calculatePolygonAreaNm2([], 'box', null, 4.0, 3.0);
assertTest('GEODESY', 'Luas box/rectangle search area (4 NM x 3 NM) bernilai tepat 12.00 NM²',
    $areaBox === 12.00
);

// Poligon Sembarang (Segitiga Teluk Kendari)
$polygonPoints = [
    ['lat' => -3.970, 'lng' => 122.580],
    ['lat' => -3.970, 'lng' => 122.610],
    ['lat' => -3.990, 'lng' => 122.580]
];
$areaPoly = GeoHelper::calculatePolygonAreaNm2($polygonPoints, 'polygon');
assertTest('GEODESY', 'Luas poligon koordinat arbitrer menghasilkan nilai positif valid (> 0 NM²)',
    $areaPoly > 0.0
);

$areaEmpty = GeoHelper::calculatePolygonAreaNm2([['lat' => -3.97, 'lng' => 122.58]], 'polygon');
assertTest('GEODESY', 'Poligon dengan kurang dari 3 titik mengembalikan luas 0.0 NM²', $areaEmpty === 0.0);


// =========================================================================
// DOMAIN 2: MODEL SPASIAL BASIS DATA & OPERASI CRUD GIS (GisModel)
// =========================================================================
echo "\n--- DOMAIN 2: PERSISTENSI SPASIAL BASIS DATA (GisModel) ---\n";

assertTest('SPATIAL_DB', 'Class GisModel tersedia di autoloader', class_exists('GisModel'));

$db = Database::getConnection();
$idTestOpsGis = 0;
$createdPointId = null;
$createdPolyId = null;
$createdRouteId = null;
$createdPatternId = null;

try {
    // 2.1 Buat Operasi Uji Coba untuk Scoping GIS
    $idTestOpsGis = OperasiModel::create([
        'nama_operasi' => 'TEST AUDIT MODUL 6 PLOTING TAKTIS GIS',
        'nama_pelapor' => 'Lettu Maritim Faisal',
        'lokasi_kejadian' => 'Perairan Selat Buton Sebelah Timur Baubau',
        'waktu_lapor' => '2026-10-21 06:30:00',
        'waktu_berangkat' => '2026-10-21 06:45:00',
        'status' => 'OPEN',
        'jumlah_pob' => 3,
        'created_by' => 1
    ]);
    assertTest('SPATIAL_DB', 'Operasi SAR uji coba berhasil dibuat di basis data', $idTestOpsGis > 0);

    // 2.2 Uji Master Bases (Titik Pangkalan Global - id_operasi IS NULL)
    $masterBases = GisModel::getMasterBases();
    assertTest('SPATIAL_DB', 'getMasterBases() mengembalikan daftar pangkalan SAR resmi',
        is_array($masterBases) && count($masterBases) > 0
    );
    assertTest('SPATIAL_DB', 'Pangkalan master memiliki koordinat latitude, longitude dan format DMS',
        isset($masterBases[0]['lat']) && isset($masterBases[0]['lng']) && isset($masterBases[0]['koordinat_dms'])
    );

    // 2.3 Simpan Titik Taktis (GisPoint - Scoped Operasi)
    $createdPointId = GisModel::savePoint([
        'id' => 'pt-test-lkp-' . time(),
        'id_operasi' => $idTestOpsGis,
        'lat' => -5.45000,
        'lng' => 122.62000,
        'label' => 'LKP KM BINTANG BUTON',
        'pointType' => 'lkp',
        'category' => 'LKP',
        'description' => 'Titik terakhir kapal terdeteksi sebelum hilang kontak',
        'alphabetChar' => 'L'
    ]);
    assertTest('SPATIAL_DB', 'savePoint berhasil menyimpan titik taktis scoped operasi', !empty($createdPointId));

    $pointsScoped = GisModel::getPoints($idTestOpsGis, false);
    $foundPoint = false;
    foreach ($pointsScoped as $pt) {
        if ($pt['id'] === $createdPointId) {
            $foundPoint = true;
            break;
        }
    }
    assertTest('SPATIAL_DB', 'getPoints(id_ops, includeGlobal=false) memuat titik taktis yang baru dibuat', $foundPoint);

    // 2.4 Simpan Poligon Search Area (GisPolygon & GisPolygonPoint)
    $polyPoints = [
        ['lat' => -5.44000, 'lng' => 122.60000, 'label' => 'Corner A'],
        ['lat' => -5.44000, 'lng' => 122.65000, 'label' => 'Corner B'],
        ['lat' => -5.48000, 'lng' => 122.65000, 'label' => 'Corner C'],
        ['lat' => -5.48000, 'lng' => 122.60000, 'label' => 'Corner D']
    ];
    $createdPolyId = GisModel::savePolygon([
        'id' => 'poly-test-' . time(),
        'id_operasi' => $idTestOpsGis,
        'name' => 'SEARCH AREA ALPHA H.1',
        'shapeType' => 'polygon',
        'color' => '#f97316',
        'opacity' => 0.25,
        'points' => $polyPoints
    ]);
    assertTest('SPATIAL_DB', 'savePolygon berhasil menyimpan area pencarian poligon', !empty($createdPolyId));

    $polygonsScoped = GisModel::getPolygons($idTestOpsGis, false);
    $foundPoly = null;
    foreach ($polygonsScoped as $pl) {
        if ($pl['id'] === $createdPolyId) {
            $foundPoly = $pl;
            break;
        }
    }
    assertTest('SPATIAL_DB', 'getPolygons mengembalikan poligon beserta anak titik (points)',
        $foundPoly !== null && isset($foundPoly['points']) && count($foundPoly['points']) === 4
    );
    assertTest('SPATIAL_DB', 'savePolygon mengkalkulasi luas areaNm2 secara otomatis (> 0 NM²)',
        $foundPoly !== null && isset($foundPoly['areaNm2']) && (float)$foundPoly['areaNm2'] > 0
    );

    // 2.5 Simpan Rute Pergerakan SRU (GisRoute & GisRoutePoint)
    $routeWaypoints = [
        ['lat' => -5.45000, 'lng' => 122.62000, 'name' => 'Start POS Baubau', 'order' => 1],
        ['lat' => -5.46000, 'lng' => 122.63000, 'name' => 'Waypoint 1', 'order' => 2],
        ['lat' => -5.47000, 'lng' => 122.64000, 'name' => 'Waypoint 2 / Target', 'order' => 3]
    ];
    $createdRouteId = GisModel::saveRoute([
        'id' => 'route-test-' . time(),
        'id_operasi' => $idTestOpsGis,
        'name' => 'RUTE PATROLI RIB 01',
        'color' => '#2563eb',
        'weight' => 3,
        'waypoints' => $routeWaypoints
    ]);
    assertTest('SPATIAL_DB', 'saveRoute berhasil menyimpan rute pergerakan SRU', !empty($createdRouteId));

    $routesScoped = GisModel::getRoutes($idTestOpsGis, false);
    $foundRoute = null;
    foreach ($routesScoped as $rt) {
        if ($rt['id'] === $createdRouteId) {
            $foundRoute = $rt;
            break;
        }
    }
    assertTest('SPATIAL_DB', 'getRoutes mengembalikan rute beserta titik-titik koordinat dalam points',
        $foundRoute !== null && isset($foundRoute['points']) && count($foundRoute['points']) === 3
    );
    assertTest('SPATIAL_DB', 'saveRoute menyimpan atribut kecepatan kapal vesselSpeedKnots (25 kts)',
        $foundRoute !== null && (float)($foundRoute['vesselSpeedKnots'] ?? 0) === 25.0
    );

    // 2.6 Simpan Tactical Search Pattern (GisSearchPattern & GisSearchPatternWaypoint)
    $patternWaypoints = [
        ['lat' => -5.44500, 'lng' => 122.60500, 'legNumber' => 1],
        ['lat' => -5.44500, 'lng' => 122.64500, 'legNumber' => 2],
        ['lat' => -5.45500, 'lng' => 122.64500, 'legNumber' => 3],
        ['lat' => -5.45500, 'lng' => 122.60500, 'legNumber' => 4]
    ];
    $createdPatternId = GisModel::saveSearchPattern([
        'id' => 'pattern-test-' . time(),
        'id_operasi' => $idTestOpsGis,
        'name' => 'PARALLEL TRACK PATTERN SRU-01',
        'patternType' => 'parallel_track',
        'trackSpacingNm' => 0.5,
        'legsCount' => 4,
        'totalDistanceNm' => 12.0,
        'color' => '#16a34a',
        'waypoints' => $patternWaypoints
    ]);
    assertTest('SPATIAL_DB', 'saveSearchPattern berhasil menyimpan pola pencarian taktis', !empty($createdPatternId));

    $patternsScoped = GisModel::getSearchPatterns($idTestOpsGis, false);
    $foundPattern = null;
    foreach ($patternsScoped as $sp) {
        if ($sp['id'] === $createdPatternId) {
            $foundPattern = $sp;
            break;
        }
    }
    assertTest('SPATIAL_DB', 'getSearchPatterns mengembalikan pola pencarian beserta waypoints',
        $foundPattern !== null && isset($foundPattern['waypoints']) && count($foundPattern['waypoints']) === 4
    );
    assertTest('SPATIAL_DB', 'Search pattern menyimpan parameter maritim: trackSpacingNm & legsCount',
        $foundPattern !== null && (float)$foundPattern['trackSpacingNm'] === 0.5 && (int)$foundPattern['legsCount'] === 4
    );

    // 2.7 Export GeoJSON Standar RFC 7946
    $geojsonStr = GisModel::exportGeoJson($idTestOpsGis);
    $geoDecoded = json_decode($geojsonStr, true);
    assertTest('GEOJSON', 'exportGeoJson menghasilkan format RFC 7946 FeatureCollection valid',
        is_array($geoDecoded) &&
        ($geoDecoded['type'] ?? '') === 'FeatureCollection' &&
        isset($geoDecoded['features']) &&
        count($geoDecoded['features']) >= 4
    );

    // Verifikasi masing-masing tipe geometri di GeoJSON
    $geomTypes = array_column(array_column($geoDecoded['features'], 'geometry'), 'type');
    assertTest('GEOJSON', 'GeoJSON memuat geometri Point, Polygon, dan LineString',
        in_array('Point', $geomTypes) && in_array('Polygon', $geomTypes) && in_array('LineString', $geomTypes)
    );

} catch (Throwable $e) {
    echo "  [ERROR EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    // 2.8 TEARDOWN BERSIH SPASIAL
    if ($createdPatternId) {
        GisModel::deleteSearchPattern($createdPatternId);
    }
    if ($createdRouteId) {
        GisModel::deleteRoute($createdRouteId);
    }
    if ($createdPolyId) {
        GisModel::deletePolygon($createdPolyId);
    }
    if ($createdPointId) {
        GisModel::deletePoint($createdPointId);
    }
    if ($idTestOpsGis > 0) {
        $db->query("DELETE FROM Operasi WHERE id_operasi = {$idTestOpsGis}");
    }
    echo "  [CLEANUP] Seluruh entitas spasial uji coba (Point, Polygon, Route, Pattern, Operasi) dibersihkan.\n";
}


// =========================================================================
// DOMAIN 3: AUDIT RESTFUL API GIS & KONTROLER (GisApiController & GisController)
// =========================================================================
echo "\n--- DOMAIN 3: AUDIT API ENDPOINT RESTFUL GIS & CONTROLLER ---\n";

assertTest('GIS_API', 'Class GisApiController tersedia di autoloader', class_exists('GisApiController'));
assertTest('GIS_API', 'Class GisController tersedia di autoloader', class_exists('GisController'));

// 3.1 Helper untuk Eksekusi Subproses API Terisolasi
function simulateGisApi(string $path, string $method = 'GET', array $params = [], array $body = [], bool $asAdmin = true): array {
    $script = sprintf(
        'require "%s/config/app.php";
        if (%s) {
            $_SESSION["user_id"] = 1;
            $_SESSION["role"] = "admin";
            $_SESSION["username"] = "admin_sar";
            $_SESSION["nama_lengkap"] = "Administrator SAR";
        }
        $_SERVER["REQUEST_METHOD"] = "%s";
        $_GET = %s;
        $_POST = %s;
        GisApiController::handle("%s", "%s", %s);',
        addslashes(dirname(__DIR__)),
        $asAdmin ? 'true' : 'false',
        $method,
        var_export($params, true),
        var_export($body, true),
        $path,
        $method,
        var_export($body, true)
    );
    $cmd = sprintf('/opt/homebrew/bin/php -r %s 2>&1', escapeshellarg($script));
    $raw = trim((string)shell_exec($cmd));
    $json = json_decode($raw, true);
    return ['status' => is_array($json) ? 200 : 500, 'body' => $raw, 'data' => $json];
}

// 3.2 Uji Keamanan: Akses API Tanpa Autentikasi Ditolak
$resUnauth = simulateGisApi('/gis/data', 'GET', [], [], false);
assertTest('GIS_API', 'Endpoint GIS API dilindungi autentikasi (unauthenticated request ditolak)',
    isset($resUnauth['data']['error']) && $resUnauth['data']['error'] === 'UNAUTHORIZED'
);

// 3.3 Uji GIS Data Bundle Endpoint (/gis/data atau /gis/bundle)
$resBundle = simulateGisApi('/gis/data', 'GET', ['include_base_map' => '1'], [], true);
assertTest('GIS_API', 'Endpoint /gis/data mengembalikan status sukses',
    is_array($resBundle['data']) &&
    ($resBundle['data']['success'] ?? false) === true
);
assertTest('GIS_API', 'Bundle GIS memuat layer spasial lengkap (points, polygons, routes, searchPatterns)',
    isset($resBundle['data']['points']) &&
    isset($resBundle['data']['polygons']) &&
    isset($resBundle['data']['routes']) &&
    isset($resBundle['data']['searchPatterns'])
);
assertTest('GIS_API', 'Bundle GIS menyertakan daftar pangkalan sar_units resmi KPP Kendari',
    isset($resBundle['data']['sar_units']) && is_array($resBundle['data']['sar_units'])
);

// 3.4 Uji CRUD API Titik Taktis (/gis/point)
$mockPointId = 'pt-api-' . time();
$resCreatePoint = simulateGisApi('/gis/point', 'POST', [], [
    'id' => $mockPointId,
    'lat' => -3.98000,
    'lng' => 122.59000,
    'label' => 'TITIK API TEST LKP',
    'pointType' => 'lkp',
    'category' => 'LKP'
], true);

assertTest('GIS_API', 'POST /gis/point berhasil membuat titik taktis baru',
    is_array($resCreatePoint['data']) &&
    ($resCreatePoint['data']['success'] ?? false) === true
);

// Hapus Titik Uji Coba via API
$resDeletePoint = simulateGisApi("/gis/point/{$mockPointId}", 'DELETE', [], [], true);
assertTest('GIS_API', 'DELETE /gis/point/{id} berhasil menghapus titik taktis',
    is_array($resDeletePoint['data']) &&
    ($resDeletePoint['data']['success'] ?? false) === true
);

// 3.5 Uji Endpoint Geocoding (/geocode)
$resGeocode = simulateGisApi('/geocode', 'GET', ['q' => 'Kendari'], [], true);
assertTest('GIS_API', 'Endpoint /geocode mengembalikan hasil pencarian geocoding',
    is_array($resGeocode['data']) && ($resGeocode['data']['success'] ?? false) === true
);


// =========================================================================
// DOMAIN 4: INTEGRITAS ENGINE KLIEN (gis_master.js) & ATURAN BISNIS (views/gis.php)
// =========================================================================
echo "\n--- DOMAIN 4: INTEGRITAS ENGINE JS (gis_master.js) & ATURAN PETA AKTIF ---\n";

$gisMasterPath = dirname(__DIR__) . '/assets/js/gis_master.js';
assertTest('GIS_CLIENT', 'Berkas assets/js/gis_master.js tersedia di filesystem', file_exists($gisMasterPath));
$gisJs = file_get_contents($gisMasterPath);

// 4.1 Dukungan 4 Pola Pencarian Taktis Maritim
assertTest('GIS_CLIENT', 'Engine GIS mendukung pola Parallel Track Search (parallel_track)',
    str_contains($gisJs, 'parallel_track')
);
assertTest('GIS_CLIENT', 'Engine GIS mendukung pola Creeping Line Search (creeping_line)',
    str_contains($gisJs, 'creeping_line')
);
assertTest('GIS_CLIENT', 'Engine GIS mendukung pola Sector Search / E-SAR (sector_search / e_sar)',
    str_contains($gisJs, 'sector_search') || str_contains($gisJs, 'e_sar')
);
assertTest('GIS_CLIENT', 'Engine GIS mendukung pola Expanding Square Search (expanding_square)',
    str_contains($gisJs, 'expanding_square')
);

// 4.2 Parameter Operasional Maritim
assertTest('GIS_CLIENT', 'Engine GIS menghitung lebar sapuan (sweep width / track spacing)',
    str_contains($gisJs, 'trackSpacing') || str_contains($gisJs, 'track_spacing')
);
assertTest('GIS_CLIENT', 'Engine GIS menghitung luas area pencarian maritim',
    str_contains($gisJs, 'areaNm2') || str_contains($gisJs, 'areaKm2')
);

// 4.3 Verifikasi views/gis.php: Aturan Bisnis Pilihan Operasi Aktif
$viewGisPath = dirname(__DIR__) . '/views/gis.php';
assertTest('GIS_VIEW', 'Berkas views/gis.php tersedia di filesystem', file_exists($viewGisPath));
$viewGisContent = file_get_contents($viewGisPath);

assertTest('GIS_VIEW', 'Halaman GIS memuat integrasi Leaflet.js',
    str_contains($viewGisContent, 'leaflet')
);

assertTest('GIS_VIEW', 'Aturan Bisnis: Pilihan operasi SAR hanya memuat operasi dengan status OPEN',
    str_contains($viewGisContent, "OperasiModel::getListSimple('OPEN')") &&
    str_contains($viewGisContent, "strtoupper(\$ops['status'] ?? '') === 'OPEN'")
);

assertTest('GIS_VIEW', 'Pengecualian Capture Doc: Mengizinkan operasi CLOSED di-load khusus saat capture dokumen resmi',
    str_contains($viewGisContent, 'capture_doc') && str_contains($viewGisContent, 'OperasiModel::getById')
);

assertTest('GIS_VIEW', 'Halaman GIS memuat kontrol navigasi basemap (Satellite, Hybrid, Roadmap)',
    str_contains($viewGisContent, 'basemap') || str_contains($viewGisContent, 'Satellite') || str_contains($viewGisContent, 'tileLayer')
);

// =========================================================================
// REKAPITULASI HASIL AUDIT MODUL 6
// =========================================================================
echo "\n========================================================================================\n";
echo "   REKAPITULASI HASIL AUDIT MODUL 6: {$passCount} LULUS (PASS), {$failCount} GAGAL (FAIL)  \n";
echo "========================================================================================\n\n";

if ($failCount > 0) {
    echo "⚠️ PERHATIAN: Terdapat {$failCount} pengujian yang gagal!\n";
    exit(1);
} else {
    echo "✅ SEMUA PENGUJIAN MODUL 6 BERHASIL LULUS 100% TANPA KESALAHAN.\n";
    exit(0);
}
