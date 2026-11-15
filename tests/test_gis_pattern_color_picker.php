<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$testTotal = 0; $testPassed = 0;
function assertCondition(bool $cond, string $msg): void {
    global $testTotal, $testPassed;
    $testTotal++;
    if ($cond) { $testPassed++; echo " [PASS] {$msg}\n"; }
    else { echo " [FAIL] {$msg}\n"; }
}

echo "=== TEST GIS PATTERN COLOR PICKER ===\n";
$view = (string)file_get_contents(ROOT_DIR . '/views/gis.php');
$js = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');

assertCondition(str_contains($view, 'id="labelPatternLineColor"'), 'View memiliki label active HEX untuk garis Pola SAR');
assertCondition(str_contains($view, 'id="patternColorSwatches"'), 'View memiliki kontainer swatches 8 warna Pola SAR');
assertCondition(str_contains($view, 'id="patternCustomColorHex"'), 'View memiliki label kode HEX kustom Pola SAR');
assertCondition(str_contains($js, 'patternColorSwatches') || str_contains($js, 'syncPatternStyleControls'), 'JS menginisialisasi atau menyinkronkan swatch Pola SAR');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);

