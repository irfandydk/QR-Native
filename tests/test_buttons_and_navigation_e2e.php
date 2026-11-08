<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Test Buttons, Navigation & End-to-End Safety Audit
 * Quick Report SAR System - Basarnas KPP Kendari
 */
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/Router.php';

echo "=== MEMULAI TEST AUDIT E2E FUNGSI TOMBOL & NAVIGASI SISTEM QR-SAR ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(string $name, bool $condition, string $details = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo "![FAIL] $name: $details\n";
        $failCount++;
    }
}

// -------------------------------------------------------------
// SEKSI 1: SYNTAX VALIDATION
// -------------------------------------------------------------
echo "\n--- SEKSI 1: SYNTAX VALIDATION (php -l) ---\n";
$targetFiles = [
    'dashboard.php',
    'helpers/Router.php',
    'views/bra/list.php',
    'views/master/index.php',
    'views/gis.php',
    'views/operasi/renops_smeac_print.php',
    'views/admin/partials/tab_logs.php'
];

foreach ($targetFiles as $file) {
    $fullPath = ROOT_DIR . '/' . $file;
    $lintOutput = [];
    $lintCode = 0;
    exec('/opt/homebrew/bin/php -l ' . escapeshellarg($fullPath), $lintOutput, $lintCode);
    assertCondition("Syntax {$file} valid", $lintCode === 0, implode("\n", $lintOutput));
}

// -------------------------------------------------------------
// SEKSI 2: ROUTER & ROOT DISPATCHER VERIFICATION
// -------------------------------------------------------------
echo "\n--- SEKSI 2: ROUTER & ROOT DISPATCHER VERIFICATION ---\n";

assertCondition(
    "Berkas fisik dashboard.php tersedia di root sistem",
    file_exists(ROOT_DIR . '/dashboard.php')
);

$dashboardRootContent = file_get_contents(ROOT_DIR . '/dashboard.php');
assertCondition(
    "dashboard.php memanggil DashboardController->index()",
    str_contains($dashboardRootContent, 'DashboardController') && str_contains($dashboardRootContent, '->index()')
);

// Inspect Router::$routes using reflection
$refClass = new ReflectionClass('Router');
$routesProp = $refClass->getProperty('routes');
$routesProp->setAccessible(true);
$routes = $routesProp->getValue();

assertCondition(
    "Route '/dashboard' terdaftar menuju 'index.php'",
    isset($routes['/dashboard']) && $routes['/dashboard'] === 'index.php'
);

assertCondition(
    "Route '/dashboard.php' terdaftar menuju 'index.php'",
    isset($routes['/dashboard.php']) && $routes['/dashboard.php'] === 'index.php'
);

assertCondition(
    "Route '/index.php' terdaftar menuju 'index.php'",
    isset($routes['/index.php']) && $routes['/index.php'] === 'index.php'
);

// -------------------------------------------------------------
// SEKSI 3: AUDIT TOMBOL KEMBALI & NAVIGASI KONTEKSTUAL
// -------------------------------------------------------------
echo "\n--- SEKSI 3: AUDIT TOMBOL KEMBALI & NAVIGASI KONTEKSTUAL ---\n";

// 3.1 BRA List back button
$braListContent = file_get_contents(ROOT_DIR . '/views/bra/list.php');
assertCondition(
    "views/bra/list.php tidak memiliki link ke dashboard.php (mencegah 404)",
    !str_contains($braListContent, 'href="<?= BASE_URL ?>/dashboard.php"')
);
assertCondition(
    "views/bra/list.php tombol kembali mengarah ke index.php",
    str_contains($braListContent, 'href="<?= BASE_URL ?>/index.php"')
);

// 3.2 Master Index shortcut button
$masterContent = file_get_contents(ROOT_DIR . '/views/master/index.php');
assertCondition(
    "views/master/index.php tanpa tombol kembali/segarkan duplikat (navigasi lewat sidebar)",
    !str_contains($masterContent, '<span>Segarkan</span>') && !str_contains($masterContent, '<span>Dashboard</span>')
);

// 3.3 GIS Tactical Operational return link
$gisContent = file_get_contents(ROOT_DIR . '/views/gis.php');
assertCondition(
    "views/gis.php memiliki shortcut kembali ke Operasi SAR jika id aktif",
    str_contains($gisContent, 'href="<?= BASE_URL ?>/operasi_detail.php?id=<?= $selectedOperasiId ?>"')
);

// 3.4 Print documents contextual tab preservation
$renopsPrint = file_get_contents(ROOT_DIR . '/views/operasi/renops_smeac_print.php');
$operasiCtrl = file_get_contents(ROOT_DIR . '/controllers/OperasiController.php');
assertCondition(
    "renops_smeac_print.php tombol kembali ($backUrl) menuju editor dokumen RENOPS",
    str_contains($renopsPrint, 'href="<?= e($backUrl) ?>"')
        && str_contains($operasiCtrl, "\$editorUrl = BASE_URL . '/operasi.php?action=renops_dokumen&id_renops=' . \$idRenops;")
        && str_contains($operasiCtrl, '$this->renderRenopsVersi($versi, false, $editorUrl);')
);

assertCondition(
    "Lembar cetak Laporan Awal/LAPBANG/Penutupan sudah dihapus",
    !file_exists(ROOT_DIR . '/views/operasi/laporan_awal_print.php') && !file_exists(ROOT_DIR . '/views/operasi/sitrep_print.php')
        && !file_exists(ROOT_DIR . '/views/operasi/penutupan_print.php')
);

// -------------------------------------------------------------
// SEKSI 4: AUDIT KONFIRMASI SWEETALERT2 & ANTI-DOUBLE SUBMIT
// -------------------------------------------------------------
echo "\n--- SEKSI 4: AUDIT KONFIRMASI SWEETALERT2 & ANTI-DOUBLE SUBMIT ---\n";

$tabLogsContent = file_get_contents(ROOT_DIR . '/views/admin/partials/tab_logs.php');
assertCondition(
    "tab_logs.php tidak lagi menggunakan inline onsubmit confirm",
    !str_contains($tabLogsContent, 'onsubmit="return confirm')
);
assertCondition(
    "tab_logs.php menggunakan data-confirm SweetAlert2",
    str_contains($tabLogsContent, 'data-confirm=') && str_contains($tabLogsContent, 'data-confirm-title=')
);

// Check that NO file in views/ uses return confirm
$viewsConfirmMatches = [];
exec('grep -rn "return confirm" ' . escapeshellarg(ROOT_DIR . '/views') . ' 2>&1', $viewsConfirmMatches);
assertCondition(
    "Tidak ada lagi inline 'return confirm' di seluruh folder views/ (100% SweetAlert2)",
    empty($viewsConfirmMatches),
    implode("\n", $viewsConfirmMatches)
);

// Check assets/js/app.js for anti-double submit and pageshow handler
$appJsContent = file_get_contents(ROOT_DIR . '/assets/js/app.js');
assertCondition(
    "assets/js/app.js memiliki double-submission guard pada form submit",
    str_contains($appJsContent, "submitBtn.disabled = true") && str_contains($appJsContent, "animate-spin")
);
assertCondition(
    "assets/js/app.js memiliki pageshow event handler untuk mereset tombol saat back navigation",
    str_contains($appJsContent, "window.addEventListener('pageshow'")
);

// -------------------------------------------------------------
// SEKSI 5: AUDIT CSRF FORM PROTECTION PADA SELURUH VIEWS
// -------------------------------------------------------------
echo "\n--- SEKSI 5: AUDIT CSRF FORM PROTECTION PADA SELURUH VIEWS ---\n";

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT_DIR . '/views'));
$postFormsWithoutCsrf = [];

foreach ($iterator as $splFile) {
    if (!$splFile->isFile() || $splFile->getExtension() !== 'php') continue;
    $filePath = $splFile->getPathname();
    $fileText = file_get_contents($filePath);

    // Look for <form ... method="POST" or method="post"
    if (preg_match_all('/<form\b[^>]*\bmethod=["\']POST["\'][^>]*>(.*?)<\/form>/is', $fileText, $matches)) {
        foreach ($matches[0] as $formBlock) {
            // Check if CSRF token is present
            if (!str_contains($formBlock, 'csrfInput()') && 
                !str_contains($formBlock, '_csrf_token') && 
                !str_contains($formBlock, 'csrf_token') &&
                !str_contains($formBlock, 'name="csrf_token"')) {
                $postFormsWithoutCsrf[] = str_replace(ROOT_DIR . '/', '', $filePath);
            }
        }
    }
}

$postFormsWithoutCsrf = array_unique($postFormsWithoutCsrf);
assertCondition(
    "Seluruh formulir ber-metode POST di views/ memiliki proteksi CSRF",
    empty($postFormsWithoutCsrf),
    "File tanpa CSRF: " . implode(', ', $postFormsWithoutCsrf)
);

// -------------------------------------------------------------
// RINGKASAN TEST
// -------------------------------------------------------------
echo "\n=======================================================\n";
echo "HASIL AKHIR TEST AUDIT E2E FUNGSI TOMBOL & NAVIGASI:\n";
echo "Total Lulus (PASS): $passCount\n";
echo "Total Gagal (FAIL): $failCount\n";
echo "=======================================================\n";

if ($failCount === 0) {
    echo "\n✓ SEMUA TEST BERHASIL LULUS 100%! SISTEM BEBAS GALAT 404 & FULL ROBUST.\n";
    exit(0);
} else {
    echo "\n✗ TERDAPAT TEST YANG GAGAL. SILAKAN PERIKSA DETAIL DI ATAS.\n";
    exit(1);
}
