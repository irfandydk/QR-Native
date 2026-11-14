<?php
/**
 * QR-SAR - Automated Test Suite for GIS KML/KMZ Import & Export Parity
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. GisModel methods existence (importBatch, resolveSafeLocalIconPath, generateCircleRing).
 * 2. resolveSafeLocalIconPath safe local icon path validation and normalization.
 * 3. GisModel::importBatch transactional execution, coordinate validation, safe icon downgrade,
 *    circle 36-point generation, 3-stage polygonRef resolution, and Base Map (null) scoping.
 * 4. Atomic transaction rollback when any batch item fails validation.
 * 5. Batch size boundaries (0 empty and > 500 entities rejected).
 * 6. GisModel::exportGeoJson circle 64-vertex ring synthesis when GisPolygonPoint rows are absent.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.\n");
}

require_once __DIR__ . '/bootstrap.php';

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/app.php';
require_once $rootDir . '/config/database.php';
require_once $rootDir . '/models/OperasiModel.php';
require_once $rootDir . '/models/GisModel.php';

echo "====================================================================" . PHP_EOL;
echo "   UJI OTOMATIS GIS KML/KMZ: IMPORT BATCH, ICON SECURITY & GEOJSON   " . PHP_EOL;
echo "====================================================================" . PHP_EOL;

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " => {$detail}" : '') . PHP_EOL;
        $failed++;
    }
}

$db = getDB();

// Test Operations & State
$opA = null;
$opB = null;
$createdBaseMapPointIds = [];
$createdBaseMapPolyIds = [];
$createdLogoFixture = false;
$logoFixturePath = ROOT_DIR . '/assets/img/basarnas-logo.png';

try {
    // -----------------------------------------------------------------
    // SETUP FIXTURES
    // -----------------------------------------------------------------
    if (!file_exists($logoFixturePath)) {
        @mkdir(dirname($logoFixturePath), 0755, true);
        file_put_contents($logoFixturePath, 'PNG_FIXTURE_FOR_TEST');
        $createdLogoFixture = true;
    }

    $opA = (int)OperasiModel::create([
        'nama_operasi' => 'TEST GIS KML IMPORT A - ' . time(),
        'jenis_kejadian' => 'KECELAKAAN_KAPAL',
        'lokasi_kejadian' => 'Teluk Kendari Sektor Alpha',
        'nama_pelapor' => 'Petugas Ops A',
        'hubungan_pelapor' => 'Internal',
        'no_telp_pelapor' => '081122334401',
        'koordinat' => "03° 58' 45\" S - 122° 35' 50\" E",
        'waktu_kejadian' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'waktu_lapor' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'waktu_berangkat' => date('Y-m-d H:i:s', strtotime('-45 minutes')),
        'status' => 'OPEN'
    ]);

    $opB = (int)OperasiModel::create([
        'nama_operasi' => 'TEST GIS KML IMPORT B - ' . time(),
        'jenis_kejadian' => 'KONDISI_MEMBAHAYAKAN_MANUSIA',
        'lokasi_kejadian' => 'Perairan Konawe Sektor Beta',
        'nama_pelapor' => 'Petugas Ops B',
        'hubungan_pelapor' => 'Internal',
        'no_telp_pelapor' => '081122334402',
        'koordinat' => "04° 02' 10\" S - 122° 40' 00\" E",
        'waktu_kejadian' => date('Y-m-d H:i:s', strtotime('-3 hours')),
        'waktu_lapor' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'waktu_berangkat' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'status' => 'OPEN'
    ]);

    assertCondition("Operasi uji A dan B berhasil dibuat", $opA > 0 && $opB > 0, "opA={$opA}, opB={$opB}");

    // -----------------------------------------------------------------
    // 1. VERIFIKASI METODE MODEL GIS
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- 1. VERIFIKASI METODE GisModel ---" . PHP_EOL;

    assertCondition(
        "Metode GisModel::importBatch tersedia",
        method_exists('GisModel', 'importBatch')
    );
    assertCondition(
        "Metode GisModel::resolveSafeLocalIconPath tersedia",
        method_exists('GisModel', 'resolveSafeLocalIconPath')
    );
    assertCondition(
        "Metode GisModel::generateCircleRing tersedia",
        method_exists('GisModel', 'generateCircleRing')
    );

    // -----------------------------------------------------------------
    // 2. VERIFIKASI resolveSafeLocalIconPath
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- 2. VERIFIKASI resolveSafeLocalIconPath ---" . PHP_EOL;

    if (method_exists('GisModel', 'resolveSafeLocalIconPath')) {
        $validRel = 'assets/img/basarnas-logo.png';
        assertCondition(
            "Menerima path relatif aset lokal yang ada di disk",
            GisModel::resolveSafeLocalIconPath($validRel) === $validRel,
            "Got: " . var_export(GisModel::resolveSafeLocalIconPath($validRel), true)
        );

        $validLeadingSlash = '/assets/img/basarnas-logo.png';
        assertCondition(
            "Menormalkan leading slash pada path lokal sah",
            GisModel::resolveSafeLocalIconPath($validLeadingSlash) === $validRel,
            "Got: " . var_export(GisModel::resolveSafeLocalIconPath($validLeadingSlash), true)
        );

        $validFullUrl = BASE_URL . '/assets/img/basarnas-logo.png';
        assertCondition(
            "Menormalkan URL lengkap berakar BASE_URL lokal",
            GisModel::resolveSafeLocalIconPath($validFullUrl) === $validRel,
            "Got: " . var_export(GisModel::resolveSafeLocalIconPath($validFullUrl), true)
        );

        assertCondition(
            "Menolak URL eksternal (https://evil.com/uploads/icons/x.png)",
            GisModel::resolveSafeLocalIconPath('https://evil.com/uploads/icons/x.png') === null
        );

        assertCondition(
            "Menolak traversal direktori (assets/img/../config/app.php)",
            GisModel::resolveSafeLocalIconPath('assets/img/../config/app.php') === null
        );

        assertCondition(
            "Menolak berkas uploads yang tidak ada di disk (uploads/icons/nonexistent_xyz.png)",
            GisModel::resolveSafeLocalIconPath('uploads/icons/nonexistent_xyz.png') === null
        );

        assertCondition(
            "Menolak DataURL (data:image/png;base64,abc)",
            GisModel::resolveSafeLocalIconPath('data:image/png;base64,abc') === null
        );

        assertCondition(
            "Menolak path kosong atau null",
            GisModel::resolveSafeLocalIconPath('') === null && GisModel::resolveSafeLocalIconPath(null) === null
        );
    }

    // -----------------------------------------------------------------
    // 3. VERIFIKASI importBatch DENGAN TRANSAKSI, RELASI & SAFE DOWNGRADE
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- 3. VERIFIKASI importBatch DENGAN TARGET OPERASI AKTIF ---" . PHP_EOL;

    // Persiapkan poligon existing di OpA dan poligon asing di OpB
    $existingPolyOpA = GisModel::savePolygon([
        'id_operasi' => $opA,
        'name' => 'Area Sektor Alpha Asli OpA',
        'shapeType' => 'polygon',
        'points' => [
            ['lat' => -4.01, 'lng' => 122.40, 'label' => 'A1'],
            ['lat' => -4.01, 'lng' => 122.45, 'label' => 'A2'],
            ['lat' => -4.05, 'lng' => 122.45, 'label' => 'A3'],
            ['lat' => -4.05, 'lng' => 122.40, 'label' => 'A4']
        ]
    ]);

    $foreignPolyOpB = GisModel::savePolygon([
        'id_operasi' => $opB,
        'name' => 'Area Sektor Beta Milik OpB',
        'shapeType' => 'polygon',
        'points' => [
            ['lat' => -4.10, 'lng' => 122.50, 'label' => 'B1'],
            ['lat' => -4.10, 'lng' => 122.55, 'label' => 'B2'],
            ['lat' => -4.15, 'lng' => 122.55, 'label' => 'B3']
        ]
    ]);

    assertCondition("Poligon existing OpA dan OpB berhasil disimpan", !empty($existingPolyOpA) && !empty($foreignPolyOpB));

    $batchPayloadOpA = [
        'points' => [
            [
                'label' => 'LKP Kapal Nelayan',
                'pointType' => 'lkp',
                'lat' => -4.0123,
                'lng' => 122.5123,
                'category' => 'kejadian',
                'alphabetChar' => 'L'
            ],
            [
                'label' => 'Posko SAR Utama Kendari',
                'pointType' => 'custom_upload',
                'customIconUrl' => 'assets/img/basarnas-logo.png',
                'lat' => -4.0200,
                'lng' => 122.5200,
                'category' => 'posko',
                'alphabetChar' => 'P'
            ],
            [
                'label' => 'Pos SAR Uji Tidak Sah',
                'pointType' => 'custom_upload',
                'customIconUrl' => 'http://evil.com/icon.png',
                'lat' => -4.0300,
                'lng' => 122.5300,
                'category' => 'pos_sar',
                'alphabetChar' => 'U'
            ]
        ],
        'polygons' => [
            [
                'clientRefId' => 'ref-poly-1',
                'name' => 'Area Lingkaran SAR Sektor 1',
                'shapeType' => 'circle',
                'centerLat' => -4.0500,
                'centerLng' => 122.5500,
                'radiusNm' => 2.0,
                'points' => [] // Sengaja kosong agar di-generate 36 vertex
            ]
        ],
        'patterns' => [
            [
                'name' => 'Pola Pencarian Sektor 1',
                'polygonRef' => 'ref-poly-1', // Harus ter-resolve ke ID poligon baru dalam batch
                'patternType' => 'parallel_track',
                'trackSpacingNm' => 1.0,
                'legsCount' => 4,
                'waypoints' => [
                    ['lat' => -4.0400, 'lng' => 122.5400],
                    ['lat' => -4.0400, 'lng' => 122.5600],
                    ['lat' => -4.0600, 'lng' => 122.5600]
                ]
            ],
            [
                'name' => 'Pola Datum Kansar',
                'polygonRef' => 'datum_kansar', // Harus mempertahankan 'datum_kansar'
                'patternType' => 'expanding_square',
                'points' => [ // Menguji toleransi input key 'points' pada pola
                    ['lat' => -4.0378, 'lng' => 122.4866],
                    ['lat' => -4.0380, 'lng' => 122.4870]
                ]
            ]
        ],
        'routes' => [
            [
                'name' => 'Jalur Masuk Sektor Asli OpA',
                'polygonRef' => $existingPolyOpA, // Scoped OpA valid, harus dipertahankan
                'vesselSpeedKnots' => 18.0,
                'waypoints' => [ // Menguji toleransi input key 'waypoints' pada rute
                    ['lat' => -4.0150, 'lng' => 122.4100],
                    ['lat' => -4.0350, 'lng' => 122.4300]
                ]
            ],
            [
                'name' => 'Jalur Lintas Operasi Asing OpB',
                'polygonRef' => $foreignPolyOpB, // Milik OpB! Harus dibersihkan menjadi null
                'vesselSpeedKnots' => 22.0,
                'points' => [
                    ['lat' => -4.0700, 'lng' => 122.5200],
                    ['lat' => -4.0800, 'lng' => 122.5300]
                ]
            ]
        ]
    ];

    $resultA = GisModel::importBatch($opA, $batchPayloadOpA);

    assertCondition("importBatch OpA mengembalikan struktur array", is_array($resultA));
    assertCondition("Counts points === 3", ($resultA['counts']['points'] ?? 0) === 3);
    assertCondition("Counts polygons === 1", ($resultA['counts']['polygons'] ?? 0) === 1);
    assertCondition("Counts patterns === 2", ($resultA['counts']['patterns'] ?? 0) === 2);
    assertCondition("Counts routes === 2", ($resultA['counts']['routes'] ?? 0) === 2);

    // Verifikasi Hydrated Points
    $pts = $resultA['points'] ?? [];
    assertCondition("Titik 1 tipe lkp dipertahankan", ($pts[0]['pointType'] ?? '') === 'lkp');
    assertCondition(
        "Titik 2 valid custom_upload mempertahankan path ikon lokal",
        ($pts[1]['pointType'] ?? '') === 'custom_upload' && ($pts[1]['customIconUrl'] ?? '') === 'assets/img/basarnas-logo.png'
    );
    assertCondition(
        "Titik 3 unsafe custom_upload diturunkan ke preset pos_sar & customIconUrl null",
        ($pts[2]['pointType'] ?? '') === 'pos_sar' && empty($pts[2]['customIconUrl']),
        "Got pointType: " . ($pts[2]['pointType'] ?? '') . ", icon: " . var_export($pts[2]['customIconUrl'] ?? '', true)
    );

    // Verifikasi Hydrated Polygon & Titik Geodesik Lingkaran
    $polys = $resultA['polygons'] ?? [];
    $newCircleId = $polys[0]['id'] ?? '';
    assertCondition("ID poligon baru berformat poly-imp-...", str_starts_with($newCircleId, 'poly-imp-'));
    assertCondition("Poligon lingkaran memiliki areaNm2 > 0", (float)($polys[0]['areaNm2'] ?? 0) > 0);
    assertCondition("Poligon lingkaran memiliki 36 vertex titik", count($polys[0]['points'] ?? []) === 36);

    // Verifikasi Fisik DB GisPolygonPoint
    $stmtPolyPts = $db->prepare("SELECT COUNT(*) FROM GisPolygonPoint WHERE polygonId = :pid");
    $stmtPolyPts->execute([':pid' => $newCircleId]);
    $dbPolyPtsCount = (int)$stmtPolyPts->fetchColumn();
    assertCondition("Tabel GisPolygonPoint benar-benar menyimpan 36 baris untuk lingkaran impor", $dbPolyPtsCount === 36);

    // Verifikasi Resolusi 3-Tahap polygonRef pada SearchPattern
    $patterns = $resultA['patterns'] ?? [];
    assertCondition(
        "Pola 1 me-remap polygonRef 'ref-poly-1' ke ID poligon baru {$newCircleId}",
        ($patterns[0]['polygonId'] ?? '') === $newCircleId
    );
    assertCondition(
        "Pola 2 mempertahankan 'datum_kansar'",
        ($patterns[1]['polygonId'] ?? '') === 'datum_kansar'
    );

    // Verifikasi Resolusi polygonRef pada Route
    $routes = $resultA['routes'] ?? [];
    assertCondition(
        "Rute 1 mempertahankan polygonRef in-scope ($existingPolyOpA)",
        ($routes[0]['polygonId'] ?? '') === $existingPolyOpA
    );
    assertCondition(
        "Rute 2 membersihkan polygonRef lintas-operasi asing ($foreignPolyOpB) menjadi null",
        ($routes[1]['polygonId'] ?? null) === null,
        "Got polygonId: " . var_export($routes[1]['polygonId'] ?? null, true)
    );

    // -----------------------------------------------------------------
    // 4. VERIFIKASI importBatch PADA BASE MAP (idOperasi === null)
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- 4. VERIFIKASI importBatch PADA BASE MAP (null) ---" . PHP_EOL;

    $basePayload = [
        'points' => [
            [
                'label' => 'Kantor SAR Kendari Base Master',
                'pointType' => 'kantor',
                'lat' => -4.0378,
                'lng' => 122.4866,
                'category' => 'Kantor SAR'
            ]
        ],
        'polygons' => [
            [
                'name' => 'Wilayah Kerja Basarnas Kendari',
                'shapeType' => 'polygon',
                'points' => [
                    ['lat' => -3.80, 'lng' => 122.20],
                    ['lat' => -3.80, 'lng' => 123.00],
                    ['lat' => -4.50, 'lng' => 123.00],
                    ['lat' => -4.50, 'lng' => 122.20]
                ]
            ]
        ]
    ];

    $resultBase = GisModel::importBatch(null, $basePayload);
    assertCondition("importBatch Base Map sukses 1 point, 1 polygon",
        ($resultBase['counts']['points'] ?? 0) === 1 && ($resultBase['counts']['polygons'] ?? 0) === 1
    );

    $basePtId = $resultBase['points'][0]['id'] ?? '';
    $basePolyId = $resultBase['polygons'][0]['id'] ?? '';
    if ($basePtId) $createdBaseMapPointIds[] = $basePtId;
    if ($basePolyId) $createdBaseMapPolyIds[] = $basePolyId;

    assertCondition("Point Base Map memiliki id_operasi === null", array_key_exists('id_operasi', $resultBase['points'][0]) && $resultBase['points'][0]['id_operasi'] === null);
    assertCondition("Polygon Base Map memiliki id_operasi === null", array_key_exists('id_operasi', $resultBase['polygons'][0]) && $resultBase['polygons'][0]['id_operasi'] === null);

    // -----------------------------------------------------------------
    // 5. VERIFIKASI ROLLBACK ATOMIK PENUH PADA KEGAGALAN VALIDASI
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- 5. VERIFIKASI ATOMIC ROLLBACK ---" . PHP_EOL;

    // Catat jumlah row awal pada opB
    $countPtsBBefore = (int)$db->query("SELECT COUNT(*) FROM GisPoint WHERE id_operasi = {$opB}")->fetchColumn();
    $countPolysBBefore = (int)$db->query("SELECT COUNT(*) FROM GisPolygon WHERE id_operasi = {$opB}")->fetchColumn();
    $countRoutesBBefore = (int)$db->query("SELECT COUNT(*) FROM GisRoute WHERE id_operasi = {$opB}")->fetchColumn();

    $poisonPayload = [
        'points' => [
            ['label' => 'Titik Sah Sebelum Gagal', 'lat' => -4.11, 'lng' => 122.51]
        ],
        'polygons' => [
            [
                'name' => 'Area Sah Sebelum Gagal',
                'shapeType' => 'polygon',
                'points' => [
                    ['lat' => -4.12, 'lng' => 122.52],
                    ['lat' => -4.12, 'lng' => 122.53],
                    ['lat' => -4.13, 'lng' => 122.53]
                ]
            ]
        ],
        'routes' => [
            [
                'name' => 'Rute Rusak Hanya 1 Titik (Wajib Gagal)',
                'points' => [
                    ['lat' => -4.14, 'lng' => 122.54] // GAGAL: Rute wajib min 2 titik!
                ]
            ]
        ]
    ];

    $exceptionThrown = false;
    try {
        GisModel::importBatch($opB, $poisonPayload);
    } catch (InvalidArgumentException $e) {
        $exceptionThrown = true;
    }

    assertCondition("importBatch melempar InvalidArgumentException saat rute kurang dari 2 titik", $exceptionThrown);

    $countPtsBAfter = (int)$db->query("SELECT COUNT(*) FROM GisPoint WHERE id_operasi = {$opB}")->fetchColumn();
    $countPolysBAfter = (int)$db->query("SELECT COUNT(*) FROM GisPolygon WHERE id_operasi = {$opB}")->fetchColumn();
    $countRoutesBAfter = (int)$db->query("SELECT COUNT(*) FROM GisRoute WHERE id_operasi = {$opB}")->fetchColumn();

    assertCondition(
        "Rollback atomik: Tidak ada titik yang tertinggal pada opB",
        $countPtsBAfter === $countPtsBBefore,
        "Before: {$countPtsBBefore}, After: {$countPtsBAfter}"
    );
    assertCondition(
        "Rollback atomik: Tidak ada poligon yang tertinggal pada opB",
        $countPolysBAfter === $countPolysBBefore,
        "Before: {$countPolysBBefore}, After: {$countPolysBAfter}"
    );
    assertCondition(
        "Rollback atomik: Tidak ada rute yang tertinggal pada opB",
        $countRoutesBAfter === $countRoutesBBefore,
        "Before: {$countRoutesBBefore}, After: {$countRoutesBAfter}"
    );

    // -----------------------------------------------------------------
    // 6. VERIFIKASI BATAS JUMLAH & VALIDASI KOORDINAT
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- 6. VERIFIKASI BATAS BATCH & KOORDINAT ---" . PHP_EOL;

    $emptyBatchFailed = false;
    try {
        GisModel::importBatch($opA, []);
    } catch (InvalidArgumentException) {
        $emptyBatchFailed = true;
    }
    assertCondition("Batch kosong (0 item) ditolak dengan InvalidArgumentException", $emptyBatchFailed);

    $oversizedFailed = false;
    $oversizedPoints = [];
    for ($i = 0; $i < 501; $i++) {
        $oversizedPoints[] = ['label' => "Pt-{$i}", 'lat' => -4.0, 'lng' => 122.0];
    }
    try {
        GisModel::importBatch($opA, ['points' => $oversizedPoints]);
    } catch (InvalidArgumentException) {
        $oversizedFailed = true;
    }
    assertCondition("Batch > 500 item ditolak dengan InvalidArgumentException", $oversizedFailed);

    $invalidCoordFailed = false;
    try {
        GisModel::importBatch($opA, [
            'points' => [
                ['label' => 'Titik Kutub Rusak', 'lat' => 99.99, 'lng' => 122.0]
            ]
        ]);
    } catch (InvalidArgumentException) {
        $invalidCoordFailed = true;
    }
    assertCondition("Koordinat di luar [-90, 90] ditolak dengan InvalidArgumentException", $invalidCoordFailed);

    // -----------------------------------------------------------------
    // 7. VERIFIKASI exportGeoJson DENGAN FALLBACK LINGKARAN TANPA GisPolygonPoint
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- 7. VERIFIKASI exportGeoJson CIRCLE FALLBACK ---" . PHP_EOL;

    $manualEmptyCircleId = 'poly-manual-circle-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    $stmtManual = $db->prepare("INSERT INTO GisPolygon (
        id, id_operasi, name, shapeType, centerLat, centerLng, radiusNm, strokeColor, strokeWeight, fillColor, fillOpacity, areaNm2
    ) VALUES (
        :id, :id_ops, 'Lingkaran Manual Tanpa Point Baris', 'circle', -4.0378, 122.4866, 3.5, '#ea580c', 2.5, '#f97316', 0.25, 38.48
    )");
    $stmtManual->execute([
        ':id' => $manualEmptyCircleId,
        ':id_ops' => $opA
    ]);

    $geoJsonRaw = GisModel::exportGeoJson($opA, false);
    $geoJson = is_string($geoJsonRaw) ? json_decode($geoJsonRaw, true) : $geoJsonRaw;
    assertCondition("exportGeoJson mengembalikan tipe FeatureCollection", ($geoJson['type'] ?? '') === 'FeatureCollection');

    $foundManualCircleFeature = null;
    foreach ($geoJson['features'] ?? [] as $feat) {
        if (($feat['properties']['id'] ?? '') === $manualEmptyCircleId) {
            $foundManualCircleFeature = $feat;
            break;
        }
    }

    assertCondition("exportGeoJson menemukan fitur untuk poligon lingkaran manual tanpa titik DB", $foundManualCircleFeature !== null);
    if ($foundManualCircleFeature) {
        $coordsRing = $foundManualCircleFeature['geometry']['coordinates'][0] ?? [];
        assertCondition(
            "Fitur lingkaran disintesis menjadi 64 vertex poligon tertutup (65 koordinat)",
            count($coordsRing) === 65 && ($coordsRing[0] === end($coordsRing)),
            "Vertex count: " . count($coordsRing)
        );
    }

} finally {
    // -----------------------------------------------------------------
    // PEMBERSIHAN DATA TEST (CLEANUP SPOTLESS)
    // -----------------------------------------------------------------
    echo PHP_EOL . "--- PEMBERSIHAN DATA UJI (CLEANUP) ---" . PHP_EOL;

    if ($opA) {
        // Hapus detail rute, pola, dan poligon terkait opA
        $db->exec("DELETE p FROM GisPolygonPoint p INNER JOIN GisPolygon g ON p.polygonId = g.id WHERE g.id_operasi = {$opA}");
        $db->exec("DELETE w FROM GisSearchPatternWaypoint w INNER JOIN GisSearchPattern s ON w.searchPatternId = s.id WHERE s.id_operasi = {$opA}");
        $db->exec("DELETE r FROM GisRoutePoint r INNER JOIN GisRoute g ON r.routeId = g.id WHERE g.id_operasi = {$opA}");
        $db->exec("DELETE FROM GisPoint WHERE id_operasi = {$opA}");
        $db->exec("DELETE FROM GisPolygon WHERE id_operasi = {$opA}");
        $db->exec("DELETE FROM GisSearchPattern WHERE id_operasi = {$opA}");
        $db->exec("DELETE FROM GisRoute WHERE id_operasi = {$opA}");
        $db->exec("DELETE FROM Operasi WHERE id_operasi = {$opA}");
    }

    if ($opB) {
        $db->exec("DELETE p FROM GisPolygonPoint p INNER JOIN GisPolygon g ON p.polygonId = g.id WHERE g.id_operasi = {$opB}");
        $db->exec("DELETE w FROM GisSearchPatternWaypoint w INNER JOIN GisSearchPattern s ON w.searchPatternId = s.id WHERE s.id_operasi = {$opB}");
        $db->exec("DELETE r FROM GisRoutePoint r INNER JOIN GisRoute g ON r.routeId = g.id WHERE g.id_operasi = {$opB}");
        $db->exec("DELETE FROM GisPoint WHERE id_operasi = {$opB}");
        $db->exec("DELETE FROM GisPolygon WHERE id_operasi = {$opB}");
        $db->exec("DELETE FROM GisSearchPattern WHERE id_operasi = {$opB}");
        $db->exec("DELETE FROM GisRoute WHERE id_operasi = {$opB}");
        $db->exec("DELETE FROM Operasi WHERE id_operasi = {$opB}");
    }

    if (!empty($createdBaseMapPointIds)) {
        $in = implode("','", array_map('addslashes', $createdBaseMapPointIds));
        $db->exec("DELETE FROM GisPoint WHERE id IN ('{$in}') AND id_operasi IS NULL");
    }

    if (!empty($createdBaseMapPolyIds)) {
        $in = implode("','", array_map('addslashes', $createdBaseMapPolyIds));
        $db->exec("DELETE FROM GisPolygonPoint WHERE polygonId IN ('{$in}')");
        $db->exec("DELETE FROM GisPolygon WHERE id IN ('{$in}') AND id_operasi IS NULL");
    }

    if ($createdLogoFixture && file_exists($logoFixturePath)) {
        @unlink($logoFixturePath);
    }

    echo "  [OK] Seluruh data dummy operasi A, B, Base Map & logo fixture berhasil dibersihkan." . PHP_EOL;
}

echo PHP_EOL . "====================================================================" . PHP_EOL;
echo "  Hasil Uji GIS KML/KMZ: {$passed} Lulus (PASS), {$failed} Gagal (FAIL)" . PHP_EOL;
echo "====================================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
