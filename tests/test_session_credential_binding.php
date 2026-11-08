<?php
/**
 * Test: sesi login terikat pada kredensial akun.
 * Setelah sandi diganti/direset atau akun dinonaktifkan, sesi lama tidak boleh lagi dianggap login,
 * dan sesi punya batas umur mutlak (12 jam) walau terus aktif.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST SESI TERIKAT KREDENSIAL ===\n\n";

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

$setHash('USR-USER', DEFAULT_RESET_PASSWORD);
$setHash('USR-ADMIN', DEFAULT_RESET_PASSWORD);

// 1. Login normal tetap berlaku
$_SESSION = [];
$r = AuthHelper::login('user', DEFAULT_RESET_PASSWORD);
assertCondition(($r['success'] ?? false) && AuthHelper::isLoggedIn(), 'Sesi baru setelah login dianggap login');

// 2. Sandi diganti di tempat lain: sesi lama ditolak dan dibersihkan
$setHash('USR-USER', 'SandiBaruRotasi26');
assertCondition(!AuthHelper::isLoggedIn(), 'Sesi lama ditolak setelah sandi akun diganti');
assertCondition(empty($_SESSION['user_id']), 'Data sesi lama dibersihkan');

// 3. Akun dinonaktifkan: sesi ditolak
$setHash('USR-USER', DEFAULT_RESET_PASSWORD);
$_SESSION = [];
AuthHelper::login('user', DEFAULT_RESET_PASSWORD);
$db->exec("UPDATE `Users` SET is_active = 0 WHERE id = 'USR-USER'");
assertCondition(!AuthHelper::isLoggedIn(), 'Sesi ditolak setelah akun dinonaktifkan');
$db->exec("UPDATE `Users` SET is_active = 1 WHERE id = 'USR-USER'");

// 4. Batas umur mutlak 12 jam
$_SESSION = [];
AuthHelper::login('user', DEFAULT_RESET_PASSWORD);
$_SESSION['auth_at'] = time() - 13 * 3600;
assertCondition(!AuthHelper::isLoggedIn(), 'Sesi lebih dari 12 jam sejak login ditolak');

// 5. Sesi lama (sebelum fitur ini) tanpa sidik kredensial: diadopsi, lalu ikut terikat
$_SESSION = ['user_id' => 'USR-USER', 'role' => 'USER', 'user' => ['id' => 'USR-USER', 'role' => 'USER']];
assertCondition(AuthHelper::isLoggedIn(), 'Sesi lama tanpa sidik kredensial tetap login (diadopsi)');
$setHash('USR-USER', 'SandiBaruRotasi26');
assertCondition(!AuthHelper::isLoggedIn(), 'Sesi lama yang diadopsi ikut ditolak setelah rotasi sandi');

// 6. Hak admin ikut gugur bila sesi admin tidak lagi sah
$setHash('USR-ADMIN', DEFAULT_RESET_PASSWORD);
$_SESSION = [];
AuthHelper::login('admin', DEFAULT_RESET_PASSWORD);
assertCondition(AuthHelper::isAdmin(), 'Admin yang sah dikenali sebagai admin');
$setHash('USR-ADMIN', 'SandiAdminRotasi26');
assertCondition(!AuthHelper::isAdmin(), 'Hak admin gugur setelah sandi admin diganti');

// Pulihkan sandi uji bawaan
$setHash('USR-USER', DEFAULT_RESET_PASSWORD);
$setHash('USR-ADMIN', DEFAULT_RESET_PASSWORD);

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
