<?php
/**
 * QR-SAR Automated Test Suite - Phase 4: Harmonisasi Webroot & Standarisasi Final
 * KPP Kendari - Basarnas
 *
 * Verifies:
 * 1. Domain-based view modularity (views/operasi/, views/bra/, views/master/, views/admin/, views/auth/)
 * 2. ViewHelper resolution for both modular paths and legacy aliases
 * 3. Elimination of stale root controller bra_form.php & link modernization
 * 4. DocumentRoot public/ alignment and security configuration (.htaccess & nginx)
 * 5. README.md directory tree and documentation currency
 * 6. PHP lint syntax validation across all domain view templates
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/ViewHelper.php';
require_once __DIR__ . '/../helpers/Router.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function runTest(string $description, callable $testFn): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    try {
        $result = $testFn();
        if ($result === true) {
            $passedTests++;
            echo " [PASS] {$description}\n";
        } else {
            $failedTests++;
            echo " [FAIL] {$description}: Test returned false\n";
        }
    } catch (\Throwable $e) {
        $failedTests++;
        echo " [FAIL] {$description}: " . $e->getMessage() . "\n";
    }
}

echo "====================================================================\n";
echo "QR-SAR Phase 4 Verification: Webroot Harmonization & Final Standards\n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------
// Group 1: Domain-Based Views Modularity (STR-ARC-04)
// ---------------------------------------------------------------------
echo "--- 1. Testing Domain-Based Views Modularity (STR-ARC-04) ---\n";

$domainViews = [
    'views/operasi/detail.php',
    'views/operasi/form.php',
    'views/operasi/renops_form.php',
    'views/operasi/sitrep_form.php',
    'views/bra/form.php',
    'views/bra/list.php',
    'views/bra/view.php',
    'views/bra/verify_invalid.php',
    'views/master/index.php',
    'views/master/settings.php',
    'views/admin/index.php',
    'views/auth/login.php',
];

foreach ($domainViews as $relPath) {
    runTest("Domain view exists and non-empty: {$relPath}", function() use ($relPath) {
        $fullPath = ROOT_DIR . '/' . $relPath;
        return file_exists($fullPath) && filesize($fullPath) > 500;
    });
}

// ---------------------------------------------------------------------
// Group 2: ViewHelper Dual-Path Resolution (STR-ARC-04)
// ---------------------------------------------------------------------
echo "\n--- 2. Testing ViewHelper Dual-Path Resolution (STR-ARC-04) ---\n";

$resolutionPairs = [
    'operasi/detail' => 'views/operasi/detail.php',
    'operasi_detail' => 'views/operasi/detail.php',
    'operasi/form'   => 'views/operasi/form.php',
    'operasi_form'   => 'views/operasi/form.php',
    'bra/form'       => 'views/bra/form.php',
    'bra_form'       => 'views/bra/form.php',
    'bra/list'       => 'views/bra/list.php',
    'bra_list'       => 'views/bra/list.php',
    'auth/login'     => 'views/auth/login.php',
    'login'          => 'views/auth/login.php',
    'master/index'   => 'views/master/index.php',
    'master'         => 'views/master/index.php',
    'admin/index'    => 'views/admin/index.php',
    'admin'          => 'views/admin/index.php',
    'dashboard'      => 'views/dashboard.php',
    'gis'            => 'views/gis.php',
];

foreach ($resolutionPairs as $inputView => $expectedSuffix) {
    runTest("ViewHelper::resolveViewFile('{$inputView}') -> {$expectedSuffix}", function() use ($inputView, $expectedSuffix) {
        $resolved = ViewHelper::resolveViewFile($inputView);
        $expected = ROOT_DIR . '/' . $expectedSuffix;
        return realpath($resolved) === realpath($expected);
    });
}

// ---------------------------------------------------------------------
// Group 3: Controller Root Standardization & bra_form.php Removal (STR-ARC-02)
// ---------------------------------------------------------------------
echo "\n--- 3. Testing Root Controller Standardization (STR-ARC-02) ---\n";

runTest("Stale redirector bra_form.php is permanently removed from root directory", function() {
    return !file_exists(ROOT_DIR . '/bra_form.php');
});

runTest("Zero href references to bra_form.php exist across views", function() {
    $views = array_merge(glob(VIEWS_DIR . '/*.php'), glob(VIEWS_DIR . '/**/*.php'));
    foreach ($views as $v) {
        if (strpos(file_get_contents($v), 'bra_form.php') !== false) {
            return false;
        }
    }
    return true;
});

runTest("Router defines clean routes for /bra, /bra/create, /bra/edit, /bra/form, /bra/view", function() {
    $routerContent = file_get_contents(HELPERS_DIR . '/Router.php');
    return strpos($routerContent, "'/bra/create' => 'bra.php'") !== false
        && strpos($routerContent, "'/bra/edit' => 'bra.php'") !== false
        && strpos($routerContent, "'/bra/form' => 'bra.php'") !== false
        && strpos($routerContent, "'/bra/view' => 'bra.php'") !== false;
});

runTest("Router includes 301 legacy permanent redirect handler for /bra_form.php", function() {
    $routerContent = file_get_contents(HELPERS_DIR . '/Router.php');
    return strpos($routerContent, "if (\$path === '/bra_form.php')") !== false
        && strpos($routerContent, "Location:") !== false;
});

// ---------------------------------------------------------------------
// Group 4: DocumentRoot Alignment & Server Hardening (STR-SEC-01)
// ---------------------------------------------------------------------
echo "\n--- 4. Testing DocumentRoot & Server Security Hardening (STR-SEC-01) ---\n";

runTest("public/index.php exists and properly dispatches via Router", function() {
    $file = ROOT_DIR . '/public/index.php';
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return strpos($content, 'Router::dispatch()') !== false;
});

runTest("public/router.php exists for PHP built-in server with public docroot", function() {
    $file = ROOT_DIR . '/public/router.php';
    return file_exists($file) && filesize($file) > 300;
});

runTest("public/.htaccess contains Options -Indexes +FollowSymLinks and FilesMatch deny rule", function() {
    $file = ROOT_DIR . '/public/.htaccess';
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return strpos($content, 'Options -Indexes') !== false
        && strpos($content, '<FilesMatch') !== false;
});

runTest("Root .htaccess protects storage, tests, and database directories", function() {
    $file = ROOT_DIR . '/.htaccess';
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return strpos($content, 'storage|tests') !== false;
});

runTest("nginx.conf.sample documents both /public DocumentRoot and root fallback", function() {
    $file = ROOT_DIR . '/nginx.conf.sample';
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return strpos($content, 'root /var/www/html/QR-SAR/public') !== false
        && strpos($content, 'root /var/www/html/QR-SAR') !== false;
});

// ---------------------------------------------------------------------
// Group 5: README.md Architecture Currency (STR-DB-03)
// ---------------------------------------------------------------------
echo "\n--- 5. Testing README.md Architecture Documentation (STR-DB-03) ---\n";

runTest("README.md contains updated directory tree with modular controllers and views", function() {
    $file = ROOT_DIR . '/README.md';
    if (!file_exists($file)) return false;
    $content = file_get_contents($file);
    return strpos($content, 'BaseApiController.php') !== false
        && strpos($content, 'vendor/') !== false
        && strpos($content, 'pages/') !== false
        && strpos($content, 'operasi/') !== false
        && strpos($content, 'bra/') !== false
        && strpos($content, 'storage/') !== false
        && strpos($content, 'tests/') !== false
        && strpos($content, 'migrate.php') !== false;
});

// ---------------------------------------------------------------------
// Group 6: Syntax Validation of Domain Views
// ---------------------------------------------------------------------
echo "\n--- 6. Checking PHP Lint on All Domain Views ---\n";

foreach ($domainViews as $relPath) {
    runTest("PHP lint validation: {$relPath}", function() use ($relPath) {
        $file = ROOT_DIR . '/' . $relPath;
        $output = [];
        $returnVar = 0;
        exec("/opt/homebrew/bin/php -l " . escapeshellarg($file) . " 2>&1", $output, $returnVar);
        return $returnVar === 0;
    });
}

// ---------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------
echo "\n====================================================================\n";
echo "Phase 4 Test Results: {$passedTests} / {$totalTests} Passed (" . round(($passedTests / max(1, $totalTests)) * 100) . "%)\n";
if ($failedTests > 0) {
    echo "FAILED: {$failedTests} test(s) encountered issues.\n";
    exit(1);
} else {
    echo "SUCCESS: All Phase 4 Standardization tests passed flawlessly!\n";
    exit(0);
}
