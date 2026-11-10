<?php
/**
 * Test (cek sumber): GIS — search area persegi bisa digeser utuh, warna & transparansi garis/isi terpisah,
 * jarak lurus terkunci setelah A–B + tombol Reset Garis, tile tidak blank saat zoom.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testPassed = 0;
$testTotal = 0;

function assertCondition(bool $cond, string $message): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($cond) {
        $testPassed++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
    }
}

echo "=== TEST GIS: AREA, WARNA, JARAK LURUS, ZOOM ===\n\n";

$view = (string)file_get_contents(ROOT_DIR . '/views/gis.php');
$js = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');

// Task 6: warna & transparansi garis/isi terpisah; persegi digeser utuh
assertCondition(str_contains($view, 'id="polyFillColorSwatches"') && str_contains($view, 'id="polyStrokeOpacitySlider"'), 'Editor area: swatch Warna Isi & slider Transparansi Garis');
assertCondition(!str_contains($js, 'polyFillColor = polyStrokeColor;'), 'Warna garis tidak lagi menimpa warna isi');
assertCondition(str_contains($js, 'opacity: polyStrokeOpacity') && str_contains($js, 'strokeOpacity: polyStrokeOpacity'), 'Transparansi garis dirender & disimpan');
assertCondition(str_contains($js, 'opacity: poly.strokeOpacity'), 'Area tersimpan dirender dengan transparansi garisnya');
assertCondition(str_contains($js, 'sar-rect-center-pin'), 'Persegi punya pin tengah untuk digeser utuh');

// Task 7: jarak lurus terkunci setelah A–B, tombol Reset Garis
$fnAdd = substr($js, (int)strpos($js, 'function addMeasurePoint('), 1600);
assertCondition(str_contains($view, 'id="btnResetMeasureLine"') && str_contains($js, 'resetMeasureLine: () => resetMeasureLine()'), 'Tombol Reset Garis');
assertCondition(!str_contains($fnAdd, 'measurePoints = [{ lat, lng }]') && str_contains($js, 'Garis lengkap — tekan Reset untuk membuat ulang'), 'Klik setelah A–B tidak me-reset garis lurus');

// Task 8: tombol flip pola PS/CS tersambung & tersimpan
assertCondition(str_contains($view, 'id="btnPatternFlipH"') && str_contains($view, 'id="btnPatternFlipV"') && str_contains($view, 'id="patternFlipWrapper"'), 'Tombol Flip ⇆ / ⇅ di form pola');
assertCondition(str_contains($js, 'flipH: activePatternFlipH') && str_contains($js, 'flipV: activePatternFlipV') && str_contains($js, 'activePatternFlipH = !!pat.flipH'), 'Flip diteruskan ke generator, disimpan & dipulihkan saat edit');

// Task 9: tile citra kosong (laut lepas z>=17) diganti tile induk, bukan blank
assertCondition(str_contains($view, "assetUrl('assets/js/gis/tile_fallback.js')"), 'Modul fallback tile dimuat');
assertCondition(substr_count($js, '= tileLayerWithFallback(') === 2, 'Layer satelit & hybrid memakai fallback tile induk');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
