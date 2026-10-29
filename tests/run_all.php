<?php
/**
 * QR-SAR - Unified Automated Test Suite Runner (CLI)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Runs all test suites in the tests/ directory with aggregated reporting.
 * Usage: php tests/run_all.php [--filter=keyword]
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Akses Ditolak: Skrip ini hanya dapat dijalankan melalui terminal (CLI).\n");
}

// Batalkan seluruh suite bila bukan database test
require_once __DIR__ . '/bootstrap.php';

// Terminal color helpers
function color(string $text, string $code): string {
    return "\033[{$code}m{$text}\033[0m";
}

$phpBinary = PHP_BINARY ?: '/opt/homebrew/bin/php';
$testsDir = __DIR__;
$startTime = microtime(true);

echo "\n" . color("====================================================================", "1;33") . "\n";
echo color("   QR-SAR BASARNAS KENDARI - UNIFIED AUTOMATED TEST RUNNER CLI      ", "1;37;41") . "\n";
echo color("====================================================================", "1;33") . "\n\n";

// Parse CLI flags
$filter = null;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--filter=')) {
        $filter = substr($arg, 9);
    }
}

// Discovered test files (ordered logically from unit/assets to integration)
$preferredOrder = [
    'test_phase2_architecture.php',
    'test_phase3_frontend_assets.php',
    'test_phase4_standardization.php',
    'test_5_optimizations.php',
    'test_lkp_routing.php',
    'test_custom_icon_flow.php',
    'test_bra_perfection.php',
    'test_bra_mapel.php',
    'test_bra_audit_perfection.php',
    'test_tahap3_bra_fixes.php',
    'test_renops_lapbang_phase1.php',
    'test_renops_lapbang_phase2_3.php',
    'test_audit_optimalisasi_menyeluruh.php',
    'test_qrsar_utils.php',
    'test_gis_detail_perfection.php',
    'test_phase3_sql_optimizations.php',
    'test_gis_page_perfection.php',
    'test_phase4_refactoring_architecture.php',
    'test_deep_verification_phase1_3.php',
    'test_phase5_gis_modular.php',
    'test_phase6_decoupled_services.php',
    'test_phase7_migrations_cleanup.php',
    'test_phase8_relations_logger.php',
    'test_phase9_view_partials.php',
    'test_phase10_js_modular.php',
    'test_phase11_sql_and_router.php',
    'test_modal_scroll_integrity.php',
    'test_master_data_integrity.php',
    'test_gis_advanced_and_bra_dtg.php',
    'test_gis_units_esar_and_renops_sync.php',
    'test_gis_consistency_and_esar_arrows.php',
    'test_gis_dynamic_units_and_badges.php',
    'test_document_map_snapshot.php'
];

$allFiles = glob($testsDir . '/test_*.php') ?: [];
$testFiles = [];

foreach ($preferredOrder as $name) {
    $path = $testsDir . '/' . $name;
    if (file_exists($path)) {
        $testFiles[] = $path;
    }
}

// Add any test files not in preferred list
foreach ($allFiles as $path) {
    if (!in_array($path, $testFiles, true)) {
        $testFiles[] = $path;
    }
}

if ($filter) {
    $testFiles = array_filter($testFiles, fn($f) => stripos(basename($f), $filter) !== false);
    echo color("[INFO] Filter aktif: '{$filter}' (" . count($testFiles) . " berkas uji)\n\n", "1;36");
}

$totalSuites = count($testFiles);
$passedSuites = 0;
$failedSuites = 0;
$suiteResults = [];

echo color("[MENJALANKAN SELURUH SUITE PENGUJIAN OTOMATIS...]\n\n", "1;37");

foreach ($testFiles as $index => $testFile) {
    $baseName = basename($testFile);
    $num = str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT);
    $suiteStart = microtime(true);

    $cmd = escapeshellarg($phpBinary) . ' ' . escapeshellarg($testFile);
    $descriptorSpec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];

    $process = proc_open($cmd, $descriptorSpec, $pipes, dirname($testsDir));
    $stdout = '';
    $stderr = '';

    if (is_resource($process)) {
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    } else {
        $exitCode = -1;
        $stderr = "Gagal menjalankan sub-proses PHP.";
    }

    $elapsed = round(microtime(true) - $suiteStart, 2);
    $isOk = ($exitCode === 0);

    // Extract assertion counts if present
    $passCount = 0;
    $failCount = 0;
    if (preg_match('/(\d+)\s*\/\s*(\d+)\s*(?:Passed|test|berhasil)/i', $stdout, $m)) {
        $passCount = (int)$m[1];
    } elseif (preg_match_all('/\[PASS\]/i', $stdout, $mPass)) {
        $passCount = count($mPass[0]);
    }
    if (preg_match_all('/\[FAIL\]/i', $stdout, $mFail)) {
        $failCount = count($mFail[0]);
    }

    if ($isOk && $failCount === 0) {
        $passedSuites++;
        $statusStr = color("PASS", "1;32");
        $detail = color("({$passCount} assertions, {$elapsed}s)", "0;37");
    } else {
        $failedSuites++;
        $statusStr = color("FAIL", "1;31");
        $detail = color("(exit code {$exitCode}, {$elapsed}s)", "1;31");
    }

    printf(" [%s/%s] %-42s [%s] %s\n", $num, str_pad((string)$totalSuites, 2, '0', STR_PAD_LEFT), $baseName, $statusStr, $detail);

    $suiteResults[] = [
        'file' => $baseName,
        'ok' => $isOk && $failCount === 0,
        'exitCode' => $exitCode,
        'elapsed' => $elapsed,
        'passCount' => $passCount,
        'failCount' => $failCount,
        'output' => $stdout,
        'error' => $stderr
    ];
}

$totalTime = round(microtime(true) - $startTime, 2);

echo "\n" . color("====================================================================", "1;33") . "\n";
echo color("                      RINGKASAN HASIL PENGUJIAN                     ", "1;37") . "\n";
echo color("====================================================================", "1;33") . "\n";
printf("  Total Suite Pengujian : %d\n", $totalSuites);
printf("  Lulus (PASS)          : %s\n", color((string)$passedSuites, $passedSuites === $totalSuites ? "1;32" : "1;33"));
printf("  Gagal (FAIL)          : %s\n", color((string)$failedSuites, $failedSuites === 0 ? "1;32" : "1;31"));
printf("  Waktu Total Eksekusi  : %s detik\n", $totalTime);
echo color("====================================================================", "1;33") . "\n";

if ($failedSuites > 0) {
    echo "\n" . color("DETAIL KEGAGALAN:", "1;31") . "\n";
    foreach ($suiteResults as $res) {
        if (!$res['ok']) {
            echo color("• {$res['file']}:\n", "1;31");
            echo trim($res['output'] ?: $res['error']) . "\n\n";
        }
    }
    echo color("KESIMPULAN: Terdapat test suite yang belum lulus. Harap periksa detail di atas.\n\n", "1;31");
    exit(1);
} else {
    echo "\n" . color("KESIMPULAN: SELURUH {$totalSuites} TEST SUITE LULUS 100%! SISTEM BERFUNGSI SEMPURNA.\n\n", "1;32");
    exit(0);
}
