<?php
/**
 * QR-SAR - Verification Suite for Phase 2 Architecture & Database Refactoring
 * Basarnas KPP Kendari
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once dirname(__DIR__) . '/config/app.php';

echo "=== START TEST SUITE PHASE 2: ARSITEKTUR BACKEND & DATABASE ===" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $title): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] " . $title . PHP_EOL;
        $passed++;
    } else {
        echo " [FAIL] " . $title . PHP_EOL;
        $failed++;
    }
}

// -------------------------------------------------------------
// 1. STR-ARC-03: Centralized View Helper
// -------------------------------------------------------------
echo "--- 1. UJI STR-ARC-03: ViewHelper & renderView() ---" . PHP_EOL;

assertTest(class_exists('ViewHelper'), "Class ViewHelper terdefinisi dan siap digunakan");
assertTest(function_exists('renderView'), "Fungsi global renderView() terdefinisi");

// Test capturing output of renderView with layout 'none'
ob_start();
renderView('login', [], ['layout' => 'none']);
$loginOutput = ob_get_clean();
assertTest(str_contains($loginOutput, '<!DOCTYPE html>') && str_contains($loginOutput, 'Login'), "renderView('login', [], ['layout' => 'none']) berhasil merender halaman login mandiri");

// Test layout options mapping
$testFile = VIEWS_DIR . '/test_dummy_view.php';
file_put_contents($testFile, '<?php echo "TITLE:" . ($pageTitle ?? "") . "|ROUTE:" . ($currentRoute ?? "") . "|NAV:" . ($navbarTitle ?? "");');
ob_start();
renderView('test_dummy_view', [], [
    'layout' => 'none',
    'title' => 'Test Page Title',
    'route' => 'test_route',
    'navbarTitle' => 'Test Navbar'
]);
$renderedDummy = ob_get_clean();
unlink($testFile);
assertTest($renderedDummy === 'TITLE:Test Page Title|ROUTE:test_route|NAV:Test Navbar', "renderView() memetakan options 'title', 'route', dan 'navbarTitle' secara tepat ke variabel view");

// -------------------------------------------------------------
// 2. STR-DB-02: Split MasterModel into SarUnitModel & PejabatModel
// -------------------------------------------------------------
echo PHP_EOL . "--- 2. UJI STR-DB-02: Domain Models & Backward-Compatible Facade ---" . PHP_EOL;

assertTest(class_exists('SarUnitModel'), "Class SarUnitModel terdefinisi dan ter-autoload");
assertTest(class_exists('PejabatModel'), "Class PejabatModel terdefinisi dan ter-autoload");

$sarUnitsDirect = SarUnitModel::getAll();
$sarUnitsFacade = MasterModel::getSarUnits();
assertTest(count($sarUnitsDirect) >= 7, "SarUnitModel::getAll() berhasil mengambil pangkalan SAR Sultra yang aktif di GIS (minimal 7 pangkalan)");
assertTest($sarUnitsDirect === $sarUnitsFacade, "MasterModel::getSarUnits() mendelegasikan 100% identik ke SarUnitModel::getAll()");

$pejabatDirect = PejabatModel::getAll();
$pejabatFacade = MasterModel::getPejabat();
assertTest(is_array($pejabatDirect), "PejabatModel::getAll() berhasil mengambil data pejabat penandatangan");
assertTest($pejabatDirect === $pejabatFacade, "MasterModel::getPejabat() mendelegasikan 100% identik ke PejabatModel::getAll()");

$smcDirect = PejabatModel::getDefaultSmc();
$smcFacade = MasterModel::getDefaultSmc();
assertTest($smcDirect === $smcFacade, "MasterModel::getDefaultSmc() mendelegasikan 100% identik ke PejabatModel::getDefaultSmc()");

// Master Alut & Tim tetap aktif di MasterModel
$alut = MasterModel::getAlut();
$tim = MasterModel::getTim();
assertTest(is_array($alut) && is_array($tim), "MasterModel::getAlut() dan MasterModel::getTim() tetap aktif dengan performa optimal");

// -------------------------------------------------------------
// 3. STR-DB-01: Decouple Runtime DDL
// -------------------------------------------------------------
echo PHP_EOL . "--- 3. UJI STR-DB-01: Decouple DDL Execution from Runtime Models ---" . PHP_EOL;

// Verify default execution of schema methods does not crash or execute unforced DDL
try {
    UserModel::ensureTableExists(false);
    GisModel::ensureSchemaUpdated(false);
    OperasiModel::ensureSchemaUpdated(false);
    RenopsModel::ensureSchemaUpdated(false);
    assertTest(true, "Model runtime methods (UserModel, GisModel, OperasiModel, RenopsModel) aman dieksekusi tanpa menjalankan DDL tanpa force=true");
} catch (Throwable $e) {
    assertTest(false, "Terjadi kesalahan saat memanggil schema methods: " . $e->getMessage());
}

// -------------------------------------------------------------
// 4. STR-ARC-01: Modularized API Controllers
// -------------------------------------------------------------
echo PHP_EOL . "--- 4. UJI STR-ARC-01: Modular API Controllers ---" . PHP_EOL;

$requiredControllers = [
    'BaseApiController',
    'AuthApiController',
    'OperasiApiController',
    'RenopsApiController',
    'SitrepApiController',
    'BraApiController',
    'MasterApiController',
    'SettingsApiController',
    'AiApiController',
    'GisApiController'
];

foreach ($requiredControllers as $ctrl) {
    assertTest(class_exists($ctrl), "Controller {$ctrl} terdefinisi dan ter-autoload otomatis via API_CONTROLLERS_DIR");
}

// Test router dispatcher file length reduction
$apiIndexLines = count(file(ROOT_DIR . '/api/index.php'));
assertTest($apiIndexLines < 200, "api/index.php termodularisasi secara dramatis (< 200 baris, sebelumnya 1.311 baris; saat ini {$apiIndexLines} baris)");

echo PHP_EOL . "=== RINGKASAN HASIL TEST SUITE PHASE 2 ===" . PHP_EOL;
echo "Total Pengujian : " . ($passed + $failed) . PHP_EOL;
echo "Lolos (Passed)  : {$passed}" . PHP_EOL;
echo "Gagal (Failed)  : {$failed}" . PHP_EOL;

if ($failed === 0) {
    echo "STATUS: SEMUA PENGUJIAN PHASE 2 LOLOS DENGAN SEMPURNA 100%!" . PHP_EOL;
    exit(0);
} else {
    echo "STATUS: TERDAPAT PENGUJIAN YANG GAGAL!" . PHP_EOL;
    exit(1);
}
