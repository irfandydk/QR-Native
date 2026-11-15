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

echo "=== TEST GIS AREA COLOR PICKER ===\n";
$view = (string)file_get_contents(ROOT_DIR . '/views/gis.php');
$js = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');

assertCondition(str_contains($view, 'id="polyCustomStrokeColorInput"') && str_contains($view, 'id="polyCustomStrokeColorHex"'), 'View memiliki custom stroke color picker & HEX label');
assertCondition(str_contains($view, 'id="polyCustomFillColorInput"') && str_contains($view, 'id="polyCustomFillColorHex"'), 'View memiliki custom fill color picker & HEX label');
assertCondition(str_contains($js, 'polyCustomStrokeColorInput') && str_contains($js, 'polyCustomFillColorInput'), 'JS mengikat event listener pada custom color pickers Search Area');
assertCondition(str_contains($js, 'polyCustomStrokeColorHex') && str_contains($js, 'polyCustomFillColorHex'), 'JS menyinkronkan label HEX stroke & fill');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);

