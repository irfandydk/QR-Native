<?php
/**
 * QR-SAR - QrSarUtils Frontend Utility Verification Test
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertUtil(string $name, bool $condition, string $detail = ''): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$name}\n";
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " -> {$detail}" : "") . "\n";
    }
}

echo "====================================================================\n";
echo " TEST SUITE: QrSarUtils FRONTEND JAVASCRIPT UTILITIES (FASE 2)\n";
echo "====================================================================\n\n";

// 1. File Existence & Layout Inclusions
echo "--- 1. Verification of common.js & Layout Inclusions ---\n";
$commonJsPath = ROOT_DIR . '/assets/js/utils/common.js';
assertUtil("File assets/js/utils/common.js exists and is non-empty", file_exists($commonJsPath) && filesize($commonJsPath) > 500);

$footerHtml = file_get_contents(VIEWS_DIR . '/layout/footer.php');
assertUtil("views/layout/footer.php includes assets/js/utils/common.js", str_contains($footerHtml, 'assets/js/utils/common.js'));

// 2. Node.js Functional Verification of QrSarUtils
echo "\n--- 2. Functional Node.js Runtime Execution of QrSarUtils ---\n";
$jsTestScript = <<<JS
const fs = require('fs');
const code = fs.readFileSync('{$commonJsPath}', 'utf8');

// Mock window/global
const window = {};
eval(code);

const u = window.QrSarUtils;
if (!u) {
    console.log(JSON.stringify({ error: 'QrSarUtils not mounted' }));
    process.exit(1);
}

const results = {};

// Test escapeHtml
results.escapeHtml_null = u.escapeHtml(null) === '';
results.escapeHtml_special = u.escapeHtml('<b>"Test" & \'Sandi\'</b>') === '&lt;b&gt;&quot;Test&quot; &amp; &#039;Sandi&#039;&lt;/b&gt;';

// Test padZero
results.padZero_2 = u.padZero(5, 2) === '05';
results.padZero_3 = u.padZero(7, 3) === '007';

// Test decimalToDms
const dmsLat = u.decimalToDms(-4.084194, true);
results.dmsLat_deg = dmsLat.deg === 4;
results.dmsLat_min = dmsLat.min === 5;
results.dmsLat_dir = dmsLat.dir === 'S';

const dmsLng = u.decimalToDms(122.428389, false);
results.dmsLng_deg = dmsLng.deg === 122;
results.dmsLng_min = dmsLng.min === 25;
results.dmsLng_dir = dmsLng.dir === 'T';

// Test formatDmsPair
const pairStr = u.formatDmsPair(-4.084194, 122.428389);
results.formatDmsPair = pairStr.includes('04° 05\'') && pairStr.includes('S') && pairStr.includes('122° 25\'') && pairStr.includes('T');

// Test dmsToDecimal
const roundTripLat = u.dmsToDecimal(4, 5, 3.1, 'S');
results.dmsToDecimal = Math.abs(roundTripLat - (-4.084194)) < 0.001;

// Test parseCoordinates
const parsed1 = u.parseCoordinates('04° 05\' 03" S - 122° 25\' 42" T');
results.parseDms = parsed1 !== null && Math.abs(parsed1.lat - (-4.084166)) < 0.001 && Math.abs(parsed1.lng - 122.428333) < 0.001;

const parsed2 = u.parseCoordinates('-4.084194, 122.428389');
results.parseDd = parsed2 !== null && parsed2.lat === -4.084194 && parsed2.lng === 122.428389;

console.log(JSON.stringify(results));
JS;

$tmpScript = ROOT_DIR . '/storage/test_node_utils.js';
file_put_contents($tmpScript, $jsTestScript);

$output = [];
$returnCode = 0;
exec("node " . escapeshellarg($tmpScript) . " 2>&1", $output, $returnCode);
@unlink($tmpScript);

$rawJson = implode("\n", $output);
$results = json_decode($rawJson, true);

if (!is_array($results)) {
    assertUtil("Eksekusi Node.js QrSarUtils", false, "Output error: " . $rawJson);
} else {
    foreach ($results as $testName => $passed) {
        assertUtil("QrSarUtils.{$testName}", $passed === true);
    }
}

// 3. Verification of Page Script Integration
echo "\n--- 3. Page Script Integration with QrSarUtils ---\n";
$opsFormContent = file_get_contents(ROOT_DIR . '/assets/js/pages/operasi_form.js');
assertUtil("operasi_form.js utilizes QrSarUtils for escapeHtml", str_contains($opsFormContent, 'window.QrSarUtils'));

$renopsFormContent = file_get_contents(ROOT_DIR . '/assets/js/pages/renops_form.js');
assertUtil("renops_form.js utilizes QrSarUtils for DMS & clipboard", str_contains($renopsFormContent, 'window.QrSarUtils.formatDmsPair'));

$sitrepFormContent = file_get_contents(ROOT_DIR . '/assets/js/pages/sitrep_form.js');
assertUtil("sitrep_form.js utilizes QrSarUtils for escapeHtml", str_contains($sitrepFormContent, 'window.QrSarUtils'));

$braFormContent = file_get_contents(ROOT_DIR . '/assets/js/pages/bra_form.js');
assertUtil("bra_form.js utilizes QrSarUtils for escapeHtml", str_contains($braFormContent, 'window.QrSarUtils'));

$opsDetailContent = file_get_contents(ROOT_DIR . '/assets/js/pages/operasi_detail.js');
assertUtil("operasi_detail.js utilizes QrSarUtils for DMS", str_contains($opsDetailContent, 'window.QrSarUtils.formatDmsPair'));

echo "\n====================================================================\n";
echo " HASIL AKHIR: {$passedAssertions} / {$totalAssertions} Assertions Lulus (" . round(($passedAssertions / max(1, $totalAssertions)) * 100) . "%)\n";
echo "====================================================================\n";

if ($passedAssertions === $totalAssertions) {
    echo " KESIMPULAN: FASE 2 MODULARISASI JS & QrSarUtils BERHASIL 100%!\n\n";
    exit(0);
} else {
    echo " KESIMPULAN: Terdapat asersi yang gagal!\n\n";
    exit(1);
}
