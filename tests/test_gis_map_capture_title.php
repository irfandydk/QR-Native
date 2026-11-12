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

echo "=== TEST GIS MAP CAPTURE TITLE ===\n";

$js = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');

assertCondition(!str_contains($js, '`OPS #${opObj.id_operasi} - ${opObj.nama_operasi}`'), 'GIS HUD tidak lagi menyertakan prefix OPS #ID pada judul peta');
assertCondition(!str_contains($js, 'substring(0, 62)'), 'GIS HUD tidak memotong paksa judul pada 62 karakter');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
