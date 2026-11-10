<?php
/**
 * Test: naskah WA — jarak tempuh disertai radial & asal pemberangkatan (Kantor/POS/USS),
 * jumlah korban 0 ditulis "-" pada Laporan Awal, LAPBANG, dan Penutupan.
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

echo "=== TEST NASKAH WA: JARAK + ASAL, KORBAN 0 → '-' ===\n\n";

$jarak = fn(array $o) => method_exists('WhatsAppTemplateService', 'formatJarakTempuh') ? WhatsAppTemplateService::formatJarakTempuh($o) : null;
assertCondition($jarak(['jarak' => '12 NM', 'radial' => '277°', 'asal_berangkat' => 'Kantor SAR Kendari']) === '12 NM (Radial 277° dari Kantor SAR Kendari)', 'Jarak + radial + asal');
assertCondition($jarak(['jarak' => '12 NM', 'asal_berangkat' => 'Kantor SAR Kendari']) === '12 NM (dari Kantor SAR Kendari)', 'Jarak + asal tanpa radial');
assertCondition($jarak(['jarak' => '12 NM', 'radial' => '277°']) === '12 NM (Radial 277°)', 'Jarak + radial tanpa asal (format lama)');
assertCondition($jarak(['jarak' => '', 'radial' => '277°', 'asal_berangkat' => 'X']) === '-' && $jarak([]) === '-', 'Tanpa jarak → "-"');
$jml = fn(int $n, string $s) => method_exists('WhatsAppTemplateService', 'jumlahOrang') ? WhatsAppTemplateService::jumlahOrang($n, $s) : null;
assertCondition($jml(0, 'orang') === '-' && $jml(2, 'orang') === '2 orang' && $jml(1, 'ORANG') === '1 ORANG', 'jumlahOrang: 0 → "-"');

$ops = ['nama_operasi' => 'Uji', 'jumlah_pob' => 0, 'jarak' => '12 NM', 'radial' => '277°', 'asal_berangkat' => 'Kantor SAR Kendari',
    'waktu_kejadian' => '2026-11-08 06:00:00', 'waktu_lapor' => '2026-11-08 06:30:00', 'waktu_berangkat' => '2026-11-08 07:00:00'];
$wa = WhatsAppTemplateService::generateLaporanAwalWA($ops, [], [], []);
assertCondition(str_contains($wa, 'jarak tempuh : 12 NM (Radial 277° dari Kantor SAR Kendari)'), 'Laporan Awal: jarak tempuh menyebut asal');
assertCondition(str_contains($wa, 'Data Korban: -') && str_contains($wa, '• S : -') && str_contains($wa, '• H : -') && !str_contains($wa, '0 orang'), 'Laporan Awal tanpa korban: "-"');
$wa1 = WhatsAppTemplateService::generateLaporanAwalWA(['jumlah_pob' => 1] + $ops, [['nama_korban' => 'A', 'status_korban' => 'DP']], [], []);
assertCondition(str_contains($wa1, '• DP : 1 orang') && str_contains($wa1, '• S : -') && str_contains($wa1, 'Data Korban: 1 orang'), 'Laporan Awal 1 korban DP: angka & "-"');
foreach (['LAPBANG' => false, 'Penutupan' => true] as $nama => $tutup) {
    $w = WhatsAppTemplateService::generateLapbangWA($ops, ['hari_ke' => 'H.1'], [], [], [], $tutup);
    assertCondition(str_contains($w, 'Data Korban: -') && str_contains($w, '• MD : -') && !str_contains($w, '0 ORANG') && !str_contains($w, '0 Orang'), "{$nama} tanpa korban: \"-\"");
}

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
