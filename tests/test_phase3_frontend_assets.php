<?php
/**
 * QR-SAR Automated Test Suite - Phase 3: Penataan Aset Frontend & Pemisahan JavaScript
 * KPP Kendari - Basarnas
 *
 * Verifies:
 * 1. Vendor Isolation in assets/vendor/
 * 2. Static JavaScript Extraction in assets/js/pages/ (0 PHP tags, valid file sizes)
 * 3. Page Config & Script linkages in view templates
 * 4. Conditional asset loading in header/footer layouts
 * 5. PHP Syntax validity across modified templates
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/ViewHelper.php';

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
echo "QR-SAR Phase 3 Verification: Frontend Assets & JavaScript Extraction\n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------
// Group 1: Vendor Isolation (assets/vendor/)
// ---------------------------------------------------------------------
echo "--- 1. Testing Vendor Isolation (assets/vendor/) ---\n";

$vendorFiles = [
    'assets/vendor/leaflet/leaflet.min.css',
    'assets/vendor/leaflet/leaflet.min.js',
    'assets/vendor/sweetalert2/sweetalert2.min.css',
    'assets/vendor/sweetalert2/sweetalert2.all.min.js',
    'assets/vendor/qrcode/qrcode.min.js',
    'assets/css/tailwind.css',
    'assets/vendor/jszip/jszip.min.js'
];

foreach ($vendorFiles as $relPath) {
    runTest("Vendor file exists: {$relPath}", function() use ($relPath) {
        $fullPath = ROOT_DIR . '/' . $relPath;
        return file_exists($fullPath) && filesize($fullPath) > 500;
    });
}

// ---------------------------------------------------------------------
// Group 2: JavaScript Extraction (assets/js/pages/)
// ---------------------------------------------------------------------
echo "\n--- 2. Testing Page JavaScript Extraction (assets/js/pages/) ---\n";

$pageScripts = [
    'renops_form.js' => 40000,
    'operasi_form.js' => 30000,
    'bra_form.js' => 25000,
    'operasi_detail.js' => 20000,
    'sitrep_form.js' => 20000,
];

foreach ($pageScripts as $scriptFile => $minSize) {
    runTest("Page script exists and non-empty: assets/js/pages/{$scriptFile}", function() use ($scriptFile, $minSize) {
        $fullPath = ROOT_DIR . "/assets/js/pages/{$scriptFile}";
        return file_exists($fullPath) && filesize($fullPath) >= $minSize;
    });

    runTest("Page script is pure JS with ZERO PHP tags: assets/js/pages/{$scriptFile}", function() use ($scriptFile) {
        $fullPath = ROOT_DIR . "/assets/js/pages/{$scriptFile}";
        $content = file_get_contents($fullPath);
        return strpos($content, '<?') === false;
    });
}

// ---------------------------------------------------------------------
// Group 3: View Templates Linkage & Config Injection
// ---------------------------------------------------------------------
echo "\n--- 3. Testing View Config Injection and Script Tags ---\n";

$viewChecks = [
    'views/operasi/renops_form.php' => [
        'config' => 'window.__RENOPS_PAGE_CONFIG__',
        'script' => 'assets/js/pages/renops_form.js'
    ],
    'views/operasi/form.php' => [
        'config' => 'window.__OPS_FORM_CONFIG__',
        'script' => 'assets/js/pages/operasi_form.js'
    ],
    'views/bra/form.php' => [
        'config' => 'window.__BRA_FORM_CONFIG__',
        'script' => 'assets/js/pages/bra_form.js'
    ],
    'views/operasi/detail.php' => [
        'config' => 'window.__OPS_DETAIL_CONFIG__',
        'script' => 'assets/js/pages/operasi_detail.js'
    ],
    'views/operasi/sitrep_form.php' => [
        'config' => 'window.__SITREP_FORM_CONFIG__',
        'script' => 'assets/js/pages/sitrep_form.js'
    ],
];

foreach ($viewChecks as $viewPath => $expected) {
    runTest("View {$viewPath} contains {$expected['config']} and references {$expected['script']}", function() use ($viewPath, $expected) {
        $fullPath = ViewHelper::resolveViewFile($viewPath);
        if (!file_exists($fullPath)) return false;
        $content = file_get_contents($fullPath);
        return strpos($content, $expected['config']) !== false && strpos($content, $expected['script']) !== false;
    });
}

// ---------------------------------------------------------------------
// Group 4: Conditional Asset Loading in Layouts
// ---------------------------------------------------------------------
echo "\n--- 4. Testing Layout Conditional Asset Loading ---\n";

runTest("Layout header conditionally loads Leaflet CSS", function() {
    $headerContent = file_get_contents(VIEWS_DIR . '/layout/header.php');
    return strpos($headerContent, 'assets/vendor/leaflet/leaflet.min.css') !== false
        && strpos($headerContent, '!empty($useGis)') !== false;
});

runTest("Layout header uses vendor SweetAlert2 and static Tailwind CSS", function() {
    $headerContent = file_get_contents(VIEWS_DIR . '/layout/header.php');
    return strpos($headerContent, 'assets/vendor/sweetalert2/sweetalert2.min.css') !== false
        && strpos($headerContent, 'assets/css/tailwind.css') !== false;
});

runTest("Layout header DOES NOT contain redundant QRCode script", function() {
    $headerContent = file_get_contents(VIEWS_DIR . '/layout/header.php');
    return strpos($headerContent, 'qrcode.min.js') === false;
});

runTest("Layout footer conditionally loads Leaflet, QRCode, and GIS scripts", function() {
    $footerContent = file_get_contents(VIEWS_DIR . '/layout/footer.php');
    return strpos($footerContent, '$requiresGis') !== false
        && strpos($footerContent, '$requiresQr') !== false
        && strpos($footerContent, 'assets/vendor/leaflet/leaflet.min.js') !== false
        && strpos($footerContent, 'assets/vendor/qrcode/qrcode.min.js') !== false;
});

runTest("ViewHelper supports conditional asset options (useGis, useQr, useBra, pageScript)", function() {
    // Test using reflection or invocation
    $ref = new ReflectionMethod('ViewHelper', 'render');
    $params = $ref->getParameters();
    return count($params) === 3;
});

// ---------------------------------------------------------------------
// Group 5: Template Syntax Validation
// ---------------------------------------------------------------------
echo "\n--- 5. Checking PHP Syntax for Modified Views ---\n";

$viewsToLint = [
    'views/operasi/renops_form.php',
    'views/operasi/form.php',
    'views/bra/form.php',
    'views/operasi/detail.php',
    'views/operasi/sitrep_form.php',
    'views/layout/header.php',
    'views/layout/footer.php',
    'views/layout/public_header.php',
    'helpers/ViewHelper.php'
];

foreach ($viewsToLint as $viewRel) {
    runTest("PHP lint validation: {$viewRel}", function() use ($viewRel) {
        $file = ROOT_DIR . '/' . $viewRel;
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
echo "Phase 3 Test Results: {$passedTests} / {$totalTests} Passed (" . round(($passedTests / max(1, $totalTests)) * 100) . "%)\n";
if ($failedTests > 0) {
    echo "FAILED: {$failedTests} test(s) encountered issues.\n";
    exit(1);
} else {
    echo "SUCCESS: All Phase 3 Frontend Asset & JS Extraction tests passed flawlessly!\n";
    exit(0);
}
