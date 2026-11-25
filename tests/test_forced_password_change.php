<?php
/**
 * Test: Kebijakan nonaktif wajib ganti sandi bawaan & validasi nama halaman aman.
 * Fitur wajib ganti sandi bawaan telah dinonaktifkan sesuai permintaan pengguna,
 * sehingga akun dengan DEFAULT_RESET_PASSWORD tidak dipaksa redirect.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST NONAKTIF WAJIB GANTI SANDI BAWAAN ===\n\n";

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

$db = getDB();
$setHash = fn(string $id, string $plain) => $db->prepare("UPDATE `Users` SET password_hash = :p, is_active = 1 WHERE id = :id")
    ->execute([':p' => password_hash($plain, PASSWORD_DEFAULT), ':id' => $id]);
$has = fn(string $m) => method_exists('AuthHelper', $m);

assertCondition($has('mustChangePassword') && $has('passwordChangeRedirect'), 'AuthHelper tetap menyediakan method mustChangePassword dan passwordChangeRedirect untuk kompatibilitas');

if ($has('mustChangePassword') && $has('passwordChangeRedirect')) {
    AuthHelper::resetLoginThrottle();
    $setHash('USR-USER', DEFAULT_RESET_PASSWORD);
    $_SESSION = [];
    $loginRes = AuthHelper::login('user', DEFAULT_RESET_PASSWORD);
    assertCondition(($loginRes['success'] ?? false) === true, 'Login USER dengan DEFAULT_RESET_PASSWORD berhasil');
    assertCondition(AuthHelper::mustChangePassword() === false, 'Login dengan sandi bawaan TIDAK memicu wajib ganti sandi');

    assertCondition(AuthHelper::passwordChangeRedirect('USER', 'operasi', [], 'GET', []) === null, 'USER di halaman operasi tidak dipaksa redirect');
    assertCondition(AuthHelper::passwordChangeRedirect('USER', 'profile', [], 'POST', []) === null, 'USER di profile.php tidak dipaksa redirect');
    assertCondition(AuthHelper::passwordChangeRedirect('ADMIN', 'admin', ['tab' => 'satker'], 'GET', []) === null, 'ADMIN di tab lain tidak dipaksa redirect');
    assertCondition(AuthHelper::passwordChangeRedirect('ADMIN', 'bra', [], 'GET', []) === null, 'ADMIN di halaman lain tidak dipaksa redirect');
}

// Nama halaman tetap divalidasi aman terhadap PATH_INFO spoofing
$hasPage = method_exists('AuthHelper', 'currentPageName');
assertCondition($hasPage, 'AuthHelper menyediakan currentPageName');
if ($hasPage) {
    assertCondition(AuthHelper::currentPageName(['REQUEST_URI' => '/profile.php?x=1']) === 'profile', 'profile.php dikenali sebagai profile');
    assertCondition(AuthHelper::currentPageName(['REQUEST_URI' => '/profile']) === 'profile', 'URL bersih /profile dikenali sebagai profile');
    assertCondition(AuthHelper::currentPageName(['REQUEST_URI' => '/operasi.php/profile', 'PATH_INFO' => '/profile']) === '', 'PATH_INFO tidak bisa menyamar sebagai profile');
    assertCondition(AuthHelper::currentPageName(['REQUEST_URI' => '/admin.php/', 'PATH_INFO' => '/']) === '', 'PATH_INFO kosong-garis tetap tidak dikecualikan');
}

$setHash('USR-USER', DEFAULT_RESET_PASSWORD);
echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
