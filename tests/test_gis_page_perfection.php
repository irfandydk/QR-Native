<?php
/**
 * QR-SAR - Automated Test Suite: GIS Tactical Page Perfection & Audit Verification
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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

echo "\n--- PENGUJIAN INTEGRITAS & AKSESIBILITAS HALAMAN PLOTTING GIS ---\n";

// 1. Uji OperasiModel::getListSimple() dengan filter OPEN dan CLOSED
echo "\n1. Memeriksa Query OperasiModel::getListSimple() (Bebas Error 1525 DATETIME):\n";
try {
    $openOps = OperasiModel::getListSimple('OPEN');
    assertCondition("getListSimple('OPEN') sukses dieksekusi tanpa error DATETIME", is_array($openOps));
} catch (Throwable $e) {
    assertCondition("getListSimple('OPEN') melempar error: " . $e->getMessage(), false);
}

try {
    $closedOps = OperasiModel::getListSimple('CLOSED');
    assertCondition("getListSimple('CLOSED') sukses dieksekusi tanpa error DATETIME", is_array($closedOps));
} catch (Throwable $e) {
    assertCondition("getListSimple('CLOSED') melempar error: " . $e->getMessage(), false);
}

try {
    $allSimple = OperasiModel::getListSimple();
    assertCondition("getListSimple() tanpa argumen sukses dieksekusi", is_array($allSimple));
} catch (Throwable $e) {
    assertCondition("getListSimple() melempar error: " . $e->getMessage(), false);
}

// 2. Uji Rendering Halaman gis.php (Mode Default, Mode Operasi, & Mode Basemap)
echo "\n2. Menguji Rendering Halaman gis.php pada Berbagai Mode Parameter:\n";
$_SESSION['user_id'] = 1;
$_SESSION['user'] = ['id' => 1, 'username' => 'admin', 'role' => 'superadmin', 'nama_lengkap' => 'Super Administrator'];

// Mode 1: Default gis.php
$_GET = [];
ob_start();
try {
    require __DIR__ . '/../gis.php';
    $htmlDefault = ob_get_clean();
    assertCondition("Halaman gis.php (Default) berhasil dirender", strlen($htmlDefault) > 50000);
    assertCondition("gis.php memuat Leaflet CSS di header", str_contains($htmlDefault, 'assets/vendor/leaflet/leaflet.min.css'));
    assertCondition("gis.php memuat Leaflet JS di footer", str_contains($htmlDefault, 'assets/vendor/leaflet/leaflet.min.js'));
    assertCondition("gis.php memuat gis_master.js", str_contains($htmlDefault, 'assets/js/gis_master.js'));
    assertCondition("gis.php memuat QrSarUtils common.js", str_contains($htmlDefault, 'assets/js/utils/common.js'));
    assertCondition("gis.php memuat window.SAR_INITIAL_GIS_DATA", str_contains($htmlDefault, 'window.SAR_INITIAL_GIS_DATA = {'));
} catch (Throwable $e) {
    ob_end_clean();
    assertCondition("Halaman gis.php gagal dirender: " . $e->getMessage(), false);
}

// Mode 2: Operasi Tertentu (gis.php?id_operasi=1)
$_GET = ['id_operasi' => '1'];
ob_start();
try {
    require __DIR__ . '/../gis.php';
    $htmlOps = ob_get_clean();
    assertCondition("Halaman gis.php?id_operasi=1 berhasil dirender", strlen($htmlOps) > 50000);
    assertCondition("SAR_INITIAL_GIS_DATA memuat filterMode", str_contains($htmlOps, 'filterMode:'));
} catch (Throwable $e) {
    ob_end_clean();
    assertCondition("gis.php?id_operasi=1 gagal: " . $e->getMessage(), false);
}

// Mode 3: Base Map (gis.php?mode=basemap)
$_GET = ['mode' => 'basemap'];
ob_start();
try {
    require __DIR__ . '/../gis.php';
    $htmlBase = ob_get_clean();
    assertCondition("Halaman gis.php?mode=basemap berhasil dirender", strlen($htmlBase) > 50000);
} catch (Throwable $e) {
    ob_end_clean();
    assertCondition("gis.php?mode=basemap gagal: " . $e->getMessage(), false);
}

// 3. Uji Dependency Guard pada assets/js/gis_master.js
echo "\n3. Memeriksa Skrip assets/js/gis_master.js:\n";
$gisMasterCode = file_get_contents(__DIR__ . '/../assets/js/gis_master.js');
assertCondition("gis_master.js memiliki dependency guard typeof L === 'undefined'", str_contains($gisMasterCode, "if (typeof L === 'undefined')"));
assertCondition("gis_master.js memuat fungsi bootstrapApp()", str_contains($gisMasterCode, 'function bootstrapApp()'));
assertCondition("gis_master.js menginisialisasi peta pada kontainer gisLeafletMap", str_contains($gisMasterCode, "map = L.map('gisLeafletMap'"));

// 4. Uji Respons Endpoint API GIS
echo "\n4. Memeriksa Endpoint API GIS:\n";
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/gis/bundle?id_operasi=1';
$_GET = ['id_operasi' => '1'];
ob_start();
try {
    require __DIR__ . '/../api/index.php';
    $apiResp = ob_get_clean();
    $apiJson = json_decode($apiResp, true);
    assertCondition("Endpoint /api/gis/bundle merespons dengan JSON valid", is_array($apiJson) && ($apiJson['success'] ?? false) === true);
    assertCondition("API bundle memuat field points, polygons, routes, searchPatterns", isset($apiJson['data']['points'], $apiJson['data']['polygons'], $apiJson['data']['routes'], $apiJson['data']['searchPatterns']));
} catch (Throwable $e) {
    ob_end_clean();
    assertCondition("API /api/gis/bundle gagal: " . $e->getMessage(), false);
}

echo "\n--- HASIL: {$passedAssertions} / {$totalAssertions} Pengujian Berhasil Lulus ---\n";
if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
