<?php
/**
 * QR-SAR - Automated Test Suite: Fase 9 (Dekomposisi View Monolitik & Partials Architecture)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

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

echo "\n====================================================================\n";
echo "   UJI OTOMATIS FASE 9: VIEW PARTIALS ARCHITECTURE & MODULARITY     \n";
echo "====================================================================\n";

$rootDir = dirname(__DIR__);

// --- 1. UJI INTEGRITAS VIEW PARTIALS OPERASI DETAIL ---
echo "\n--- 1. MEMERIKSA VIEW PARTIALS DETAIL OPERASI (views/operasi/partials/) ---\n";
$operasiPartials = [
    'tab_hierarchy.php',
    'tab_renops.php',
    'tab_sitrep.php',
    'tab_korban.php',
    'tab_kekuatan.php',
    'tab_bra.php',
    'tab_gis.php',
    'tab_cetak.php',
    'modals.php'
];

foreach ($operasiPartials as $partial) {
    $path = $rootDir . "/views/operasi/partials/{$partial}";
    assertCondition("Partial views/operasi/partials/{$partial} fisik tersedia", file_exists($path));
    if (file_exists($path)) {
        $lintOutput = [];
        $lintCode = 0;
        exec("/opt/homebrew/bin/php -l " . escapeshellarg($path), $lintOutput, $lintCode);
        assertCondition("Sintaks PHP valid tanpa lint error: {$partial}", $lintCode === 0);
    }
}

$detailOpsPath = $rootDir . '/views/operasi/detail.php';
assertCondition("views/operasi/detail.php ramping (< 350 baris)", count(file($detailOpsPath)) < 350);

// --- 2. UJI INTEGRITAS VIEW PARTIALS ADMIN SETTINGS ---
echo "\n--- 2. MEMERIKSA VIEW PARTIALS PENGATURAN ADMIN (views/admin/partials/) ---\n";
$adminPartials = [
    'tab_admin.php',
    'tab_user.php',
    'tab_satker.php',
    'tab_backup.php',
    'tab_logs.php'
];

foreach ($adminPartials as $partial) {
    $path = $rootDir . "/views/admin/partials/{$partial}";
    assertCondition("Partial views/admin/partials/{$partial} fisik tersedia", file_exists($path));
    if (file_exists($path)) {
        $lintOutput = [];
        $lintCode = 0;
        exec("/opt/homebrew/bin/php -l " . escapeshellarg($path), $lintOutput, $lintCode);
        assertCondition("Sintaks PHP valid tanpa lint error: {$partial}", $lintCode === 0);
    }
}

$adminIndexPath = $rootDir . '/views/admin/index.php';
assertCondition("views/admin/index.php ramping (< 200 baris)", count(file($adminIndexPath)) < 200);

// --- 3. UJI INTEGRITAS VIEW PARTIALS MASTER DATA ---
echo "\n--- 3. MEMERIKSA VIEW PARTIALS MASTER DATA (views/master/partials/) ---\n";
$masterPartials = [
    'tab_alut.php',
    'tab_potensi.php',
    'tab_pejabat.php',
    'modals.php'
];

foreach ($masterPartials as $partial) {
    $path = $rootDir . "/views/master/partials/{$partial}";
    assertCondition("Partial views/master/partials/{$partial} fisik tersedia", file_exists($path));
    if (file_exists($path)) {
        $lintOutput = [];
        $lintCode = 0;
        exec("/opt/homebrew/bin/php -l " . escapeshellarg($path), $lintOutput, $lintCode);
        assertCondition("Sintaks PHP valid tanpa lint error: {$partial}", $lintCode === 0);
    }
}

$masterIndexPath = $rootDir . '/views/master/index.php';
assertCondition("views/master/index.php ramping (< 150 baris)", count(file($masterIndexPath)) < 150);

// --- 4. UJI FUNGSIONALITAS RENDERPARTIAL & ISOLATED SCOPE ---
echo "\n--- 4. MENGUJI METODE RENDERPARTIAL() & VIEWHELPER::PARTIAL() ---\n";
assertCondition("Helper renderPartial() tersedia secara global", function_exists('renderPartial'));
assertCondition("ViewHelper::resolvePartialFile() menyelesaikan path tanpa ekstensi", method_exists('ViewHelper', 'resolvePartialFile'));
assertCondition("ViewHelper::partial() dapat dieksekusi", method_exists('ViewHelper', 'partial'));

// Uji rendering partial terisolasi
ob_start();
renderPartial('master/partials/modals', ['testVar' => 'qrsar_test']);
$renderedModal = ob_get_clean();
assertCondition("renderPartial('master/partials/modals') menghasilkan HTML modal", str_contains($renderedModal, 'modal-edit-alut'));

echo "\n====================================================================\n";
echo "  Total Pemeriksaan Asersi : {$totalAssertions}\n";
echo "  Lulus (PASS)             : {$passedAssertions}\n";
echo "  Gagal (FAIL)             : " . ($totalAssertions - $passedAssertions) . "\n";
echo "====================================================================\n";

if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
