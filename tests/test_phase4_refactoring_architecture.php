<?php
/**
 * QR-SAR - Automated Test Suite: Phase 4 Refactoring Architecture & Security
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. Autoloader resolution for Services and Controllers
 * 2. OOP Hierarchy and BaseController inheritance
 * 3. BackupService separation and delegation parity with SettingsModel
 * 4. Thin Dispatcher verification for all entrypoints
 * 5. Single Document Root (/public) structure and security boundary enforcement
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

echo "\n--- PENGUJIAN FASE 4: REFAKTORING ARSITEKTUR, CONTROLLER OOP & BACKUP SERVICE ---\n";

// 1. Uji Autoloader Service & Controller
echo "\n1. Memeriksa Resolusi Autoloader (Services & Controllers):\n";
assertCondition("Class BackupService dapat dimuat via autoloader", class_exists('BackupService'));
assertCondition("Class BaseController dapat dimuat via autoloader", class_exists('BaseController'));
assertCondition("Class DashboardController dapat dimuat via autoloader", class_exists('DashboardController'));
assertCondition("Class OperasiController dapat dimuat via autoloader", class_exists('OperasiController'));
assertCondition("Class BraController dapat dimuat via autoloader", class_exists('BraController'));
assertCondition("Class GisController dapat dimuat via autoloader", class_exists('GisController'));
assertCondition("Class MasterController dapat dimuat via autoloader", class_exists('MasterController'));
assertCondition("Class AdminController dapat dimuat via autoloader", class_exists('AdminController'));
assertCondition("Class AuthController dapat dimuat via autoloader", class_exists('AuthController'));

// 2. Uji Hirarki Pewarisan OOP BaseController
echo "\n2. Memeriksa Hirarki Pewarisan OOP BaseController:\n";
assertCondition("DashboardController adalah turunan dari BaseController", is_subclass_of(DashboardController::class, BaseController::class));
assertCondition("OperasiController adalah turunan dari BaseController", is_subclass_of(OperasiController::class, BaseController::class));
assertCondition("BraController adalah turunan dari BaseController", is_subclass_of(BraController::class, BaseController::class));
assertCondition("GisController adalah turunan dari BaseController", is_subclass_of(GisController::class, BaseController::class));
assertCondition("MasterController adalah turunan dari BaseController", is_subclass_of(MasterController::class, BaseController::class));
assertCondition("AdminController adalah turunan dari BaseController", is_subclass_of(AdminController::class, BaseController::class));
assertCondition("AuthController adalah turunan dari BaseController", is_subclass_of(AuthController::class, BaseController::class));

// 3. Uji Pemisahan BackupService & Kesetaraan Delegasi dengan SettingsModel
echo "\n3. Memeriksa Integritas Pemisahan BackupService:\n";
assertCondition("BackupService memiliki method getDbStats", method_exists('BackupService', 'getDbStats'));
assertCondition("BackupService memiliki method generateSQLDump", method_exists('BackupService', 'generateSQLDump'));
assertCondition("BackupService memiliki method exportJsonBackup", method_exists('BackupService', 'exportJsonBackup'));
assertCondition("BackupService memiliki method restoreFromSQL", method_exists('BackupService', 'restoreFromSQL'));
assertCondition("BackupService memiliki method restoreFromJson", method_exists('BackupService', 'restoreFromJson'));
assertCondition("BackupService memiliki method splitSqlStatements", method_exists('BackupService', 'splitSqlStatements'));

$backupStats = BackupService::getDbStats();
$settingsStats = SettingsModel::getDbStats();
assertCondition("BackupService::getDbStats() mengembalikan array statistik lengkap", isset($backupStats['total_operasi'], $backupStats['total_renops'], $backupStats['total_bra_sar']));
assertCondition("SettingsModel::getDbStats() identik 100% dengan BackupService::getDbStats()", $backupStats === $settingsStats);

// 4. Uji Thin Dispatcher pada File Entrypoint Root
echo "\n4. Memeriksa Kontrak Thin Dispatcher pada Seluruh Entrypoint Root:\n";
$dispatchers = [
    'index.php' => ['DashboardController', 'index'],
    'operasi.php' => ['OperasiController', 'index'],
    'operasi_form.php' => ['OperasiController', 'form'],
    'operasi_detail.php' => ['OperasiController', 'detail'],
    'renops_form.php' => ['OperasiController', 'renopsForm'],
    'sitrep_form.php' => ['OperasiController', 'sitrepForm'],
    'bra.php' => ['BraController', 'handle'],
    'gis.php' => ['GisController', 'index'],
    'master.php' => ['MasterController', 'index'],
    'admin.php' => ['AdminController', 'index'],
    'login.php' => ['AuthController', 'login'],
    'logout.php' => ['AuthController', 'logout'],
    'profile.php' => ['AuthController', 'profile']
];

foreach ($dispatchers as $file => [$ctrl, $method]) {
    $fullPath = ROOT_DIR . '/' . $file;
    assertCondition("Entrypoint {$file} tersedia di root", file_exists($fullPath));
    $content = (string)file_get_contents($fullPath);
    assertCondition("Entrypoint {$file} mendelegasikan ke {$ctrl}->{$method}()", str_contains($content, $ctrl) && str_contains($content, $method));
    $lines = count(file($fullPath));
    assertCondition("Entrypoint {$file} adalah thin dispatcher (<= 25 baris kode: {$lines} baris)", $lines <= 25);
}

// 5. Uji Single Document Root (/public) dan Batas Keamanan
echo "\n5. Memeriksa Standardisasi Single Document Root (/public) & Batas Keamanan:\n";
assertCondition("Berkas public/index.php tersedia", file_exists(ROOT_DIR . '/public/index.php'));
assertCondition("Berkas public/.htaccess tersedia", file_exists(ROOT_DIR . '/public/.htaccess'));
assertCondition("Berkas public/router.php tersedia", file_exists(ROOT_DIR . '/public/router.php'));
assertCondition("Symlink public/assets aktif", is_dir(ROOT_DIR . '/public/assets'));
assertCondition("Symlink public/uploads aktif", is_dir(ROOT_DIR . '/public/uploads'));
assertCondition("Panduan konfigurasi nginx.conf.sample tersedia", file_exists(ROOT_DIR . '/nginx.conf.sample'));
assertCondition("Panduan konfigurasi apache.conf.sample tersedia", file_exists(ROOT_DIR . '/apache.conf.sample'));

// Uji pencegahan akses langsung router ke direktori sistem baru
$routerFile = (string)file_get_contents(ROOT_DIR . '/helpers/Router.php');
assertCondition("Router memproteksi folder controllers", str_contains($routerFile, 'controllers'));
assertCondition("Router memproteksi folder services", str_contains($routerFile, 'services'));

$htaccessFile = (string)file_get_contents(ROOT_DIR . '/.htaccess');
assertCondition(".htaccess root memproteksi folder controllers", str_contains($htaccessFile, 'controllers'));
assertCondition(".htaccess root memproteksi folder services", str_contains($htaccessFile, 'services'));

echo "\n--- HASIL: {$passedAssertions} / {$totalAssertions} Pengujian Berhasil Lulus ---\n";
if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
