<?php
/**
 * Test: Sanitasi sandi telegram BRA di server mempertahankan daftar bernomor/berhuruf dan baris baru
 * (sejalan dengan BraTemplates.formatSectionText di assets/js/bra/bra_templates.js).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../helpers/BraHelper.php';

echo "=== TEST SANITASI BRA: DAFTAR & BARIS BARU ===\n\n";

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

$ccc = "UNSUR SAR TERLIBAT TTK DUA\n1. RESCUER KENDARI : 6 ORANG\n\nALUT SAR TTK DUA\n1. RESCUE CAR : 1 UNIT\n2. RIB : 1 UNIT";
assertCondition(BraHelper::sanitizeTelegramPunctuation($ccc) === $ccc, 'Daftar bernomor dan baris kosong pemisah tetap utuh');

$sru = "PEMBAGIAN SRU TTK DUA\n1. SEARCH AREA SRU 1 TTK DUA\nA. 03° 58' 30.5\" S - 122° 35' 12\" E\nB. 03° 59' 00\" S - 122° 36' 00\" E\nPOLA E-SAR TS 0.5 NM LUAS 4 NM²";
$out = BraHelper::sanitizeTelegramPunctuation($sru);
assertCondition(str_contains($out, "\nA. 03° 58' 30.5\" S - 122° 35' 12\" E\n"), 'Baris koordinat berhuruf A. tidak diubah');
assertCondition(str_contains($out, "\nPOLA E-SAR TS 0.5 NM LUAS 4 NM²"), 'Baris pola tetap di baris sendiri dengan desimal utuh');

$naratif = "Tim bergerak, cepat.\n1. Rescuer : 6 orang";
assertCondition(BraHelper::sanitizeTelegramPunctuation($naratif) === "TIM BERGERAK KMA CEPAT TTK\n1. RESCUER : 6 ORANG", 'Baris naratif tetap disandikan, baris daftar hanya kapital');

$kurung = 'PD TW 1028 1705 H TERIMA INFO DARI ANDI (KELUARGA KORBAN/ HP. 0812) BAHWA';
assertCondition(str_contains(BraHelper::sanitizeTelegramPunctuation($kurung), '(KELUARGA KORBAN/ HP. 0812)'), 'Isi dalam kurung (keterangan pelapor) tidak disandikan');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
