<?php
/**
 * Test: APP_KEY tidak pernah jatuh ke kunci yang bisa ditebak.
 * Bila APP_KEY kosong, kunci acak dibuat sekali, disimpan (izin 0600), lalu dipakai ulang.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST RESOLUSI APP_KEY ===\n\n";

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

assertCondition(function_exists('qrsar_resolve_app_key'), 'Fungsi qrsar_resolve_app_key tersedia');

$file = sys_get_temp_dir() . '/qrsar_app_key_test_' . bin2hex(random_bytes(4));
@unlink($file);

if (function_exists('qrsar_resolve_app_key')) {
    $k1 = qrsar_resolve_app_key('', $file);
    assertCondition((bool)preg_match('/^[0-9a-f]{64}$/', $k1), 'APP_KEY kosong menghasilkan kunci acak 64 heksa');
    assertCondition(is_file($file) && (fileperms($file) & 0777) === 0600, 'Kunci disimpan ke berkas dengan izin 0600');
    assertCondition(qrsar_resolve_app_key('', $file) === $k1, 'Pemanggilan berikutnya memakai kunci yang sama');
    assertCondition(qrsar_resolve_app_key('kunci-dari-env-yang-panjang', $file) === 'kunci-dari-env-yang-panjang', 'APP_KEY dari env selalu diutamakan');
    assertCondition($k1 !== 'BASARNAS_KENDARI_SECURE_HMAC_KEY_V2_' . sha1(dirname(__DIR__) . '/config'), 'Bukan kunci cadangan lama yang bisa ditebak');
}
assertCondition(!str_contains((string)file_get_contents(__DIR__ . '/../config/app.php'), "sha1(__DIR__)"), 'config/app.php tidak lagi memakai sha1(__DIR__) sebagai kunci');

@unlink($file);
echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
