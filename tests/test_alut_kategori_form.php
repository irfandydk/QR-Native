<?php
/**
 * Test (statis): pilihan kategori alut di form Laporan Awal, RENOPS & LAPBANG.
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

echo "=== TEST KATEGORI ALUT DI FORM ===\n\n";

$shared = (string)@file_get_contents(ROOT_DIR . '/assets/js/alut_kategori.js');
assertCondition(str_contains($shared, 'window.AlutKategoriUI') && str_contains($shared, 'window.ALUT_KATEGORI')
    && str_contains($shared, 'tebak') && str_contains($shared, 'sort'), 'Modul bersama alut_kategori.js (tebak, sort, opsi) membaca window.ALUT_KATEGORI');

$pairs = [
    'views/operasi/form.php' => 'assets/js/pages/operasi_form.js',
    'views/operasi/renops_form.php' => 'assets/js/pages/renops_form.js',
    'views/operasi/sitrep_form.php' => 'assets/js/pages/sitrep_form.js',
];
foreach ($pairs as $view => $jsPath) {
    $v = (string)file_get_contents(ROOT_DIR . '/' . $view);
    $js = (string)file_get_contents(ROOT_DIR . '/' . $jsPath);
    $n = basename($view);
    assertCondition(str_contains($v, 'id="alut-input-kategori"') && str_contains($v, 'window.ALUT_KATEGORI = <?= jsonSafe(AlutKategori::configJs()) ?>')
        && str_contains($v, "assetUrl('assets/js/alut_kategori.js')")
        && strpos($v, "assetUrl('assets/js/alut_kategori.js')") < strpos($v, "assetUrl('{$jsPath}')"), "{$n}: select kategori, konfigurasi & modul bersama dimuat sebelum JS halaman");
    assertCondition(str_contains($js, "updateAlutItem(\${idx}, 'kategori', this.value)"), "{$n}: kategori tiap item bisa diubah");
    assertCondition(str_contains($js, "document.getElementById('alut-input-kategori').value = AlutKategoriUI.normalize(item.kategori)"), "{$n}: pilih master mengisi kategori");
    assertCondition(str_contains($js, "kategori: AlutKategoriUI.normalize(document.getElementById('alut-input-kategori').value)"), "{$n}: item baru membawa kategori dari input");
    assertCondition(str_contains($js, 'AlutKategoriUI.sort(currentAlutList).map('), "{$n}: teks ringkas diurutkan per kategori");
}
assertCondition(method_exists('AlutKategori', 'configJs') && AlutKategori::configJs()['urutan'][0] === ['kode' => 'ALUT_DARAT', 'label' => 'ALUT Darat']
    && AlutKategori::configJs()['kata']['ALUT_UDARA'] === AlutKategori::KATA_KUNCI['ALUT_UDARA'], 'configJs: urutan, label & kata kunci dari satu sumber PHP');

$db = getDB();
$db->prepare("INSERT INTO MasterAlut (nama_alut, satuan, kategori) VALUES ('  Uji KN  Cfg ', 'Unit', 'ALUT_LAUT')")->execute();
$idCfg = (int)$db->lastInsertId();
AlutKategori::resetCache();
$cfgM = AlutKategori::configJs()['master'] ?? [];
assertCondition(($cfgM['nama']['uji kn cfg'] ?? null) === 'ALUT_LAUT' && ($cfgM['id'][$idCfg] ?? null) === 'ALUT_LAUT', 'configJs: peta master (id & nama ternormalisasi) untuk resolver JS');
$db->prepare('DELETE FROM MasterAlut WHERE id_alut = ?')->execute([$idCfg]);
AlutKategori::resetCache();

foreach (['views/operasi/form.php', 'views/operasi/renops_form.php', 'views/operasi/sitrep_form.php'] as $v) {
    $src = (string)file_get_contents(ROOT_DIR . '/' . $v);
    assertCondition(!str_contains($src, "\$ma['kategori'] ?? 'ALUT'") && substr_count($src, 'AlutKategori::resolve($ma)') === 2,
        basename($v) . ': opsi master alut memakai kategori ter-resolve + label (bukan kode mentah)');
}

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
