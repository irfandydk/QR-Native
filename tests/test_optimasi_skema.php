<?php
/**
 * Test: kolom baru optimasi Laporan Awal & GIS — Operasi.asal_berangkat, GisPolygon.strokeOpacity,
 * GisSearchPattern.flipH/flipV, GisRoute.polygonId — tersimpan & terbaca, dan pulih setelah restore backup lama.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testPassed = 0;
$testTotal = 0;

function assertCondition(bool $cond, string $message): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($cond) {
        $testPassed++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
    }
}

echo "=== TEST SKEMA OPTIMASI LAPORAN AWAL & GIS ===\n\n";

$db = getDB();
$ada = fn(string $t, string $c) => !empty($db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'")->fetchAll());
$kolom = [
    ['Operasi', 'asal_berangkat'],
    ['Renops', 'gelombang'],
    ['Renops', 'angin'],
    ['GisPolygon', 'strokeOpacity'],
    ['GisSearchPattern', 'flipH'],
    ['GisSearchPattern', 'flipV'],
    ['GisRoute', 'polygonId'],
];
foreach ($kolom as [$t, $c]) {
    assertCondition($ada($t, $c), "Kolom {$t}.{$c} ada");
}

$idOps = 0;
$ids = ['poly' => 'poly-uji-skema', 'pat' => 'pattern-uji-skema', 'route' => 'route-uji-skema'];
try {
    $idOps = OperasiModel::create(['nama_operasi' => 'Uji Skema Optimasi', 'nama_pelapor' => 'U', 'lokasi_kejadian' => 'L',
        'waktu_lapor' => '2026-11-08 06:30:00', 'origin_name' => '  Kantor SAR Kendari ']);
    assertCondition((OperasiModel::getById($idOps)['asal_berangkat'] ?? null) === 'Kantor SAR Kendari', 'asal_berangkat disimpan dari origin_name (di-trim)');
    OperasiModel::update($idOps, ['nama_operasi' => 'Uji Skema Optimasi 2']);
    assertCondition((OperasiModel::getById($idOps)['asal_berangkat'] ?? null) === 'Kantor SAR Kendari', 'update tanpa kunci asal: nilai lama tetap');
    OperasiModel::update($idOps, ['nama_operasi' => 'Uji Skema Optimasi 2', 'asal_berangkat' => 'POS SAR Kolaka']);
    assertCondition((OperasiModel::getById($idOps)['asal_berangkat'] ?? null) === 'POS SAR Kolaka', 'update dengan asal_berangkat baru');

    $pts = [['lat' => -4.0, 'lng' => 122.0], ['lat' => -4.0, 'lng' => 122.1], ['lat' => -4.1, 'lng' => 122.1]];
    GisModel::savePolygon(['id' => $ids['poly'], 'id_operasi' => $idOps, 'name' => 'SRU 1', 'points' => $pts, 'strokeOpacity' => 0.4]);
    $poly = array_values(array_filter(GisModel::getPolygons($idOps, false), fn($p) => $p['id'] === $ids['poly']))[0] ?? [];
    assertCondition(($poly['strokeOpacity'] ?? null) === 0.4, 'GisPolygon.strokeOpacity tersimpan (float 0.4)');

    GisModel::saveSearchPattern(['id' => $ids['pat'], 'id_operasi' => $idOps, 'name' => 'Pola', 'polygonId' => $ids['poly'], 'flipH' => true, 'flipV' => false,
        'waypoints' => [['lat' => -4.0, 'lng' => 122.0], ['lat' => -4.1, 'lng' => 122.1]]]);
    $pat = array_values(array_filter(GisModel::getSearchPatterns($idOps, false), fn($p) => $p['id'] === $ids['pat']))[0] ?? [];
    assertCondition(($pat['flipH'] ?? null) === true && ($pat['flipV'] ?? null) === false, 'GisSearchPattern.flipH/flipV tersimpan (bool)');

    GisModel::saveRoute(['id' => $ids['route'], 'id_operasi' => $idOps, 'name' => 'Rute ke SRU 1', 'polygonId' => $ids['poly'],
        'points' => [['lat' => -4.0, 'lng' => 122.0], ['lat' => -4.1, 'lng' => 122.1]]]);
    $route = array_values(array_filter(GisModel::getRoutes($idOps, false), fn($r) => $r['id'] === $ids['route']))[0] ?? [];
    assertCondition(($route['polygonId'] ?? null) === $ids['poly'], 'GisRoute.polygonId tersimpan');
    GisModel::saveRoute(['id' => $ids['route'], 'id_operasi' => $idOps, 'name' => 'Rute 1', 'points' => [['lat' => -4.0, 'lng' => 122.0], ['lat' => -4.1, 'lng' => 122.1]]]);
    $route = array_values(array_filter(GisModel::getRoutes($idOps, false), fn($r) => $r['id'] === $ids['route']))[0] ?? [];
    assertCondition(array_key_exists('polygonId', $route) && $route['polygonId'] === null, 'Rute tanpa area: polygonId null');

    // Renops cuaca 3 field + pewarisan HierarchyHelper + fallback parsial
    OperasiModel::update($idOps, ['cuaca' => 'Cerah', 'gelombang' => '0,4 Mtr', 'angin' => '5 km/jam Timur']);
    $opsFresh = OperasiModel::getById($idOps);
    $nextRen = HierarchyHelper::getNextRenopsDefaults($opsFresh);
    assertCondition(
        ($nextRen['inherited_cuaca'] ?? '') === 'Cerah' && ($nextRen['inherited_gelombang'] ?? '') === '0,4 Mtr' && ($nextRen['inherited_angin'] ?? '') === '5 km/jam Timur',
        'HierarchyHelper::getNextRenopsDefaults mewarisi cuaca, gelombang, angin'
    );
    $idRen = RenopsModel::create([
        'id_operasi' => $idOps,
        'hari_ke' => $nextRen['hari_ke'] ?? 'H.2',
        'tanggal_laporan' => $nextRen['target_date_str'] ?? date('Y-m-d'),
        'cuaca' => 'Berawan', 'gelombang' => '1,1 Mtr', 'angin' => '9 km/jam Selatan',
    ]);
    $renRow = RenopsModel::getById($idRen);
    assertCondition(
        ($renRow['cuaca'] ?? '') === 'Berawan' && ($renRow['gelombang'] ?? '') === '1,1 Mtr' && ($renRow['angin'] ?? '') === '9 km/jam Selatan',
        'RenopsModel::create menyimpan cuaca, gelombang, angin terpisah'
    );
    RenopsModel::update($idRen, ['cuaca' => 'Hujan Ringan']);
    $renUpdated = RenopsModel::getById($idRen);
    assertCondition(
        ($renUpdated['cuaca'] ?? '') === 'Hujan Ringan' && ($renUpdated['gelombang'] ?? '') === '1,1 Mtr' && ($renUpdated['angin'] ?? '') === '9 km/jam Selatan',
        'RenopsModel::update parsial mempertahankan gelombang dan angin tersimpan'
    );
    $opsWithRen = OperasiModel::getById($idOps);
    $nextLap = HierarchyHelper::getNextLapbangDefaults($opsWithRen);
    assertCondition(
        ($nextLap['inherited_cuaca'] ?? '') === 'Hujan Ringan' && ($nextLap['inherited_gelombang'] ?? '') === '1,1 Mtr' && ($nextLap['inherited_angin'] ?? '') === '9 km/jam Selatan',
        'HierarchyHelper::getNextLapbangDefaults mewarisi cuaca, gelombang, angin dari RENOPS hari sama'
    );
    $renSrc = HierarchyHelper::resolveRenopsSources($opsWithRen, $renUpdated);
    assertCondition(
        ($renSrc['cuaca'] ?? '') === 'Hujan Ringan' && ($renSrc['gelombang'] ?? '') === '1,1 Mtr' && ($renSrc['angin'] ?? '') === '9 km/jam Selatan',
        'HierarchyHelper::resolveRenopsSources meneruskan cuaca, gelombang, angin'
    );

    // Review Focus 2: restore backup lama menghapus kolom → ensureSchemaUpdated(true) memulihkan.
    $db->exec('ALTER TABLE GisPolygon DROP COLUMN strokeOpacity');
    $db->exec('ALTER TABLE Renops DROP COLUMN gelombang');
    $script = 'require ' . var_export(__DIR__ . '/bootstrap.php', true) . '; GisModel::ensureSchemaUpdated(true); OperasiModel::ensureSchemaUpdated(true); RenopsModel::ensureSchemaUpdated(true);';
    shell_exec('DB_DATABASE=' . escapeshellarg((string)getenv('DB_DATABASE')) . ' ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');
    assertCondition($ada('GisPolygon', 'strokeOpacity') && $ada('Renops', 'gelombang'), 'Kolom yang hilang dibuat ulang oleh ensureSchemaUpdated(true)');
} catch (Throwable $e) {
    assertCondition(false, 'Tanpa exception: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    if (!$ada('GisPolygon', 'strokeOpacity')) {
        $db->exec('ALTER TABLE GisPolygon ADD COLUMN strokeOpacity DOUBLE NOT NULL DEFAULT 1');
    }
    if (!$ada('Renops', 'gelombang')) {
        $db->exec('ALTER TABLE Renops ADD COLUMN gelombang VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER cuaca');
    }
    foreach (['GisSearchPatternWaypoint' => 'searchPatternId', 'GisSearchPattern' => 'id'] as $t => $c) {
        $db->prepare("DELETE FROM {$t} WHERE {$c} = ?")->execute([$ids['pat']]);
    }
    foreach (['GisRoutePoint' => 'routeId', 'GisRoute' => 'id'] as $t => $c) {
        $db->prepare("DELETE FROM {$t} WHERE {$c} = ?")->execute([$ids['route']]);
    }
    foreach (['GisPolygonPoint' => 'polygonId', 'GisPolygon' => 'id'] as $t => $c) {
        $db->prepare("DELETE FROM {$t} WHERE {$c} = ?")->execute([$ids['poly']]);
    }
    if ($idOps) {
        OperasiModel::delete($idOps);
    }
}

$bak = (string)file_get_contents(ROOT_DIR . '/services/BackupService.php');
assertCondition(
    substr_count($bak, 'GisModel::ensureSchemaUpdated(true)') === 1
    && substr_count($bak, 'OperasiModel::ensureSchemaUpdated(true)') === 1
    && substr_count($bak, 'RenopsModel::ensureSchemaUpdated(true)') === 1,
    'Restore SQL memulihkan skema (GisModel, OperasiModel, RenopsModel)'
);

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
