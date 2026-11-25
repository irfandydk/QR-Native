<?php
/**
 * QR-SAR - Automated Test Suite
 * Proteksi Kategori Titik Pangkalan SAR (Kantor SAR, POS SAR, Unit Siaga SAR) & Dermaga SAR:
 * - Role USER tidak dapat menghapus (satuan maupun bulk-delete) titik kategori ini, baik di Base Map maupun Operasi aktif.
 * - Role USER tidak dapat mengganti kategori titik ini ke kategori lain (mencegah celah ubah kategori lalu hapus).
 * - Role USER tetap dapat menghapus titik kategori lain (LKP/Posko/dll) pada Operasi OPEN.
 * - Role ADMIN tetap memiliki akses penuh (hapus satuan & bulk-delete).
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
require_once $rootDir . '/api/controllers/BaseApiController.php';
require_once $rootDir . '/api/controllers/GisApiController.php';

$passCount = 0;
$failCount = 0;

function assertProtected(string $label, bool $cond, string $detail = ''): void {
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "  [PASS] {$label}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
}

echo "\n=== 1. HELPER KATEGORI TERLINDUNGI ===\n";

$hasHelper = method_exists('GisApiController', 'isProtectedPointCategory');
assertProtected('GisApiController::isProtectedPointCategory() tersedia', $hasHelper);

if ($hasHelper) {
    $protectedCats = ['Kantor SAR', 'POS SAR', 'Unit Siaga SAR', 'Dermaga SAR', 'Dermaga', ' kantor sar ', 'DERMAGA SAR'];
    $allProtectedTrue = true;
    foreach ($protectedCats as $c) {
        if (!GisApiController::isProtectedPointCategory($c)) {
            $allProtectedTrue = false;
        }
    }
    assertProtected('Semua kategori Pangkalan SAR & Dermaga SAR terdeteksi terlindungi', $allProtectedTrue);

    $normalCats = ['LKP', 'Posko', 'Penemuan Korban', 'Lokasi Penemuan Korban', 'Entry Poin', 'Exit Poin', 'Tanpa Kategori', 'Import 1', '', null];
    $allNormalFalse = true;
    foreach ($normalCats as $c) {
        if (GisApiController::isProtectedPointCategory($c)) {
            $allNormalFalse = false;
        }
    }
    assertProtected('Kategori non-Pangkalan/Dermaga tidak diblokir', $allNormalFalse);
}

echo "\n=== 2. INTEGRASI API RBAC (USER vs ADMIN PADA OPERASI OPEN) ===\n";

$db = getDB();
$phpBin = PHP_BINARY ?: '/opt/homebrew/bin/php';
$opId = null;
$createdPointIds = [];

$runGisController = function (string $path, string $method, array $body, string $role) use ($phpBin, $rootDir): array {
    $sess = $role === 'ADMIN'
        ? '$_SESSION["user_id"] = "USR-ADMIN"; $_SESSION["role"] = "ADMIN"; $_SESSION["user"] = ["id" => "USR-ADMIN", "role" => "ADMIN"];'
        : '$_SESSION["user_id"] = "USR-USER"; $_SESSION["role"] = "USER"; $_SESSION["user"] = ["id" => "USR-USER", "role" => "USER"];';
    $code = sprintf(
        'putenv("DB_DATABASE=" . %s); require %s; register_shutdown_function(function() { $c = http_response_code(); if ($c) echo "\n__HTTP_STATUS__:" . $c; }); %s GisApiController::handle(%s, %s, %s);',
        var_export(getenv('DB_DATABASE') ?: 'qrsar_test', true),
        var_export($rootDir . '/config/app.php', true),
        $sess,
        var_export($path, true),
        var_export($method, true),
        var_export($body, true)
    );
    $out = [];
    exec(escapeshellarg($phpBin) . ' -r ' . escapeshellarg($code) . ' 2>&1', $out);
    $raw = trim(implode("\n", $out));
    $httpStatus = 200;
    if (preg_match('/__HTTP_STATUS__:(\d+)/', $raw, $m)) {
        $httpStatus = (int)$m[1];
        $raw = trim(str_replace($m[0], '', $raw));
    }
    $res = json_decode($raw, true);
    if (!is_array($res)) {
        $res = ['success' => false, 'raw' => $raw];
    }
    $res['__http_status__'] = $httpStatus;
    return $res;
};

$pointExists = function (string $id) use ($db): bool {
    $stmt = $db->prepare('SELECT COUNT(*) FROM GisPoint WHERE id = ?');
    $stmt->execute([$id]);
    return (int)$stmt->fetchColumn() === 1;
};

$pointCategory = function (string $id) use ($db): string {
    $stmt = $db->prepare('SELECT category FROM GisPoint WHERE id = ?');
    $stmt->execute([$id]);
    return (string)$stmt->fetchColumn();
};

try {
    $opId = (int)OperasiModel::create([
        'nama_operasi' => 'TEST PROTECTED GIS POINTS - ' . time(),
        'jenis_kejadian' => 'KECELAKAAN_KAPAL',
        'lokasi_kejadian' => 'Teluk Kendari',
        'nama_pelapor' => 'Petugas Uji',
        'hubungan_pelapor' => 'Internal',
        'no_telp_pelapor' => '081100001122',
        'koordinat' => "04° 02' 16\" S - 122° 29' 12\" E",
        'waktu_kejadian' => date('Y-m-d H:i:s', strtotime('-2 hours')),
        'waktu_lapor' => date('Y-m-d H:i:s', strtotime('-1 hour')),
        'waktu_berangkat' => date('Y-m-d H:i:s'),
        'status' => 'OPEN',
    ]);

    $suffix = (string)time() . mt_rand(100, 999);
    $ptKantor  = "pt-prot-kantor-{$suffix}";
    $ptPos     = "pt-prot-pos-{$suffix}";
    $ptUss     = "pt-prot-uss-{$suffix}";
    $ptDermaga = "pt-prot-dermaga-{$suffix}";
    $ptLkp     = "pt-prot-lkp-{$suffix}";
    $createdPointIds = [$ptKantor, $ptPos, $ptUss, $ptDermaga, $ptLkp];

    $seed = [
        [$ptKantor,  'Kansar Test',  'Kantor SAR',     -4.037, 122.486],
        [$ptPos,     'Pos Test',     'POS SAR',        -4.041, 122.490],
        [$ptUss,     'USS Test',     'Unit Siaga SAR', -4.045, 122.495],
        [$ptDermaga, 'Dermaga Test', 'Dermaga SAR',    -3.980, 122.580],
        [$ptLkp,     'LKP Test',     'LKP',            -4.100, 122.600],
    ];
    foreach ($seed as [$id, $label, $cat, $lat, $lng]) {
        GisModel::savePoint([
            'id' => $id,
            'id_operasi' => $opId,
            'label' => $label,
            'category' => $cat,
            'pointType' => 'pin_umum',
            'lat' => $lat,
            'lng' => $lng,
        ]);
    }

    // 2a. USER single DELETE blocked on all 4 protected categories even in OPEN operation
    foreach ([
        'Kantor SAR' => $ptKantor,
        'POS SAR' => $ptPos,
        'Unit Siaga SAR' => $ptUss,
        'Dermaga SAR' => $ptDermaga,
    ] as $catName => $ptId) {
        $res = $runGisController("/gis/points/{$ptId}", 'DELETE', [], 'USER');
        assertProtected(
            "USER ditolak menghapus titik {$catName} (HTTP 403) dan data tetap utuh",
            ($res['__http_status__'] ?? 0) === 403 && $pointExists($ptId),
            'HTTP=' . ($res['__http_status__'] ?? 0) . ' msg=' . ($res['message'] ?? $res['error'] ?? '')
        );
    }

    // 2b. USER bulk-delete mixing LKP + Dermaga SAR rejected atomically (HTTP 403, neither deleted)
    $bulkUserRes = $runGisController('/gis/bulk-delete', 'POST', [
        'entity_type' => 'points',
        'ids' => [$ptLkp, $ptDermaga],
    ], 'USER');
    assertProtected(
        'USER ditolak bulk-delete jika mengandung titik Dermaga SAR (HTTP 403, atomik — LKP juga tidak terhapus)',
        ($bulkUserRes['__http_status__'] ?? 0) === 403 && $pointExists($ptDermaga) && $pointExists($ptLkp),
        'HTTP=' . ($bulkUserRes['__http_status__'] ?? 0) . ' msg=' . ($bulkUserRes['message'] ?? '')
    );

    // 2c. USER cannot change protected category to non-protected via PUT or POST (anti-bypass)
    $bypassPutRes = $runGisController("/gis/points/{$ptKantor}", 'PUT', [
        'id_operasi' => $opId,
        'label' => 'Kansar Test',
        'category' => 'Tanpa Kategori',
        'lat' => -4.037,
        'lng' => 122.486,
    ], 'USER');
    assertProtected(
        'USER ditolak mengubah kategori Kantor SAR -> Tanpa Kategori via PUT (HTTP 403)',
        ($bypassPutRes['__http_status__'] ?? 0) === 403 && $pointCategory($ptKantor) === 'Kantor SAR',
        'HTTP=' . ($bypassPutRes['__http_status__'] ?? 0) . ' cat=' . $pointCategory($ptKantor)
    );

    $bypassPostRes = $runGisController('/gis/points', 'POST', [
        'id' => $ptDermaga,
        'id_operasi' => $opId,
        'label' => 'Dermaga Test',
        'category' => 'Posko',
        'lat' => -3.980,
        'lng' => 122.580,
    ], 'USER');
    assertProtected(
        'USER ditolak mengubah kategori Dermaga SAR -> Posko via POST (HTTP 403)',
        ($bypassPostRes['__http_status__'] ?? 0) === 403 && $pointCategory($ptDermaga) === 'Dermaga SAR',
        'HTTP=' . ($bypassPostRes['__http_status__'] ?? 0) . ' cat=' . $pointCategory($ptDermaga)
    );

    // 2d. USER can still update label/coords of protected point in OPEN op while keeping its protected category
    $validEditRes = $runGisController("/gis/points/{$ptKantor}", 'PUT', [
        'id_operasi' => $opId,
        'label' => 'Kansar Test Updated',
        'category' => 'Kantor SAR',
        'lat' => -4.038,
        'lng' => 122.487,
    ], 'USER');
    assertProtected(
        'USER tetap dapat memperbarui nama/koordinat titik Kantor SAR di operasi OPEN tanpa mengubah kategori (HTTP 200)',
        ($validEditRes['__http_status__'] ?? 0) === 200 && ($validEditRes['success'] ?? false) === true,
        'HTTP=' . ($validEditRes['__http_status__'] ?? 0) . ' msg=' . ($validEditRes['message'] ?? '')
    );

    // 2e. USER cannot CREATE a new protected-category point (Kantor SAR / POS SAR / Unit Siaga SAR / Dermaga SAR)
    $newProtectedId = "pt-prot-new-user-{$suffix}";
    $createProtRes = $runGisController('/gis/points', 'POST', [
        'id' => $newProtectedId,
        'id_operasi' => $opId,
        'label' => 'Kantor SAR Palsu Oleh User',
        'category' => 'Kantor SAR',
        'lat' => -4.05,
        'lng' => 122.50,
    ], 'USER');
    assertProtected(
        'USER ditolak membuat titik baru berkategori Pangkalan SAR (HTTP 403) dan titik tidak tersimpan',
        ($createProtRes['__http_status__'] ?? 0) === 403 && !$pointExists($newProtectedId),
        'HTTP=' . ($createProtRes['__http_status__'] ?? 0) . ' msg=' . ($createProtRes['message'] ?? $createProtRes['error'] ?? '')
    );

    // 2f. USER CAN delete regular LKP point on OPEN operation
    $delLkpRes = $runGisController("/gis/points/{$ptLkp}", 'DELETE', [], 'USER');
    assertProtected(
        'USER tetap dapat menghapus titik kategori biasa (LKP) pada operasi OPEN (HTTP 200)',
        ($delLkpRes['__http_status__'] ?? 0) === 200 && !$pointExists($ptLkp),
        'HTTP=' . ($delLkpRes['__http_status__'] ?? 0)
    );

    // 2f. ADMIN CAN single-delete Kantor SAR and bulk-delete POS SAR + Unit Siaga SAR + Dermaga SAR
    $adminDelRes = $runGisController("/gis/points/{$ptKantor}", 'DELETE', [], 'ADMIN');
    assertProtected(
        'ADMIN tetap dapat menghapus titik Kantor SAR secara satuan (HTTP 200)',
        ($adminDelRes['__http_status__'] ?? 0) === 200 && !$pointExists($ptKantor),
        'HTTP=' . ($adminDelRes['__http_status__'] ?? 0)
    );

    $adminBulkRes = $runGisController('/gis/bulk-delete', 'POST', [
        'entity_type' => 'points',
        'ids' => [$ptPos, $ptUss, $ptDermaga],
    ], 'ADMIN');
    assertProtected(
        'ADMIN tetap dapat bulk-delete titik POS SAR, Unit Siaga SAR, dan Dermaga SAR (HTTP 200)',
        ($adminBulkRes['__http_status__'] ?? 0) === 200
            && !$pointExists($ptPos) && !$pointExists($ptUss) && !$pointExists($ptDermaga),
        'HTTP=' . ($adminBulkRes['__http_status__'] ?? 0)
    );
} finally {
    foreach ($createdPointIds as $pid) {
        try { GisModel::deletePoint($pid); } catch (Throwable $e) {}
    }
    if ($opId) {
        try { OperasiModel::delete($opId); } catch (Throwable $e) {}
    }
}

echo "\n=== 3. PROTEKSI UI FRONTEND (naming.js & gis_master.js) ===\n";

$namingJs = (string)file_get_contents($rootDir . '/assets/js/gis/naming.js');
$gisMasterJs = (string)file_get_contents($rootDir . '/assets/js/gis_master.js');

assertProtected(
    'assets/js/gis/naming.js mengekspor helper isProtectedPointCategory()',
    str_contains($namingJs, 'function isProtectedPointCategory(')
        && str_contains($namingJs, 'isProtectedPointCategory,')
);

assertProtected(
    'assets/js/gis_master.js menyediakan isPointDeleteLockedForUser(), menyembunyikan tombol hapus, mengunci kategori edit, dan memfilter bulk delete untuk USER',
    str_contains($gisMasterJs, 'function isPointDeleteLockedForUser(')
        && str_contains($gisMasterJs, 'isPointDeleteLockedForUser(pt)')
        && str_contains($gisMasterJs, 'catSelect.disabled = lockCat')
        && str_contains($gisMasterJs, 'isPointDeleteLockedForUser(it)')
);

echo "\n=== RINGKASAN: {$passCount} PASS, {$failCount} FAIL ===\n";
exit($failCount > 0 ? 1 : 0);
