<?php
/**
 * Test: akun yang masih memakai sandi bawaan (DEFAULT_RESET_PASSWORD) wajib mengganti sandi
 * sebelum memakai aplikasi. USER diarahkan ke profile.php, ADMIN ke admin.php?tab=admin.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST WAJIB GANTI SANDI BAWAAN ===\n\n";

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

assertCondition($has('mustChangePassword') && $has('passwordChangeRedirect'), 'AuthHelper menyediakan mustChangePassword dan passwordChangeRedirect');

if ($has('mustChangePassword') && $has('passwordChangeRedirect')) {
    // Sandi bawaan: wajib ganti
    $setHash('USR-USER', DEFAULT_RESET_PASSWORD);
    $_SESSION = [];
    AuthHelper::login('user', DEFAULT_RESET_PASSWORD);
    assertCondition(AuthHelper::mustChangePassword(), 'Login dengan sandi bawaan menandai wajib ganti sandi');

    // Setelah mengganti sandi sendiri: tidak lagi wajib, sesi tetap login
    $r = SettingsModel::changePassword('USER', DEFAULT_RESET_PASSWORD, 'SandiPetugasBaru26', 'USR-USER');
    assertCondition(($r['success'] ?? false) && AuthHelper::isLoggedIn() && !AuthHelper::mustChangePassword(), 'Setelah ganti sandi, sesi tetap login dan tidak wajib ganti lagi');

    // Sandi bukan bawaan: tidak wajib
    $_SESSION = [];
    AuthHelper::login('user', 'SandiPetugasBaru26');
    assertCondition(!AuthHelper::mustChangePassword(), 'Sandi non-bawaan tidak memicu wajib ganti');

    // Keputusan pengalihan
    $b = BASE_URL;
    assertCondition(AuthHelper::passwordChangeRedirect('USER', 'operasi', [], 'GET', []) === $b . '/profile.php', 'USER di halaman lain diarahkan ke profile.php');
    assertCondition(AuthHelper::passwordChangeRedirect('USER', 'profile', [], 'POST', []) === null, 'USER boleh membuka dan mengirim profile.php');
    assertCondition(AuthHelper::passwordChangeRedirect('USER', 'logout', [], 'POST', []) === null, 'USER boleh logout');
    assertCondition(AuthHelper::passwordChangeRedirect('ADMIN', 'admin', ['tab' => 'user'], 'GET', []) === $b . '/admin.php?tab=admin', 'ADMIN di tab lain diarahkan ke tab ganti sandi admin');
    assertCondition(AuthHelper::passwordChangeRedirect('ADMIN', 'admin', [], 'GET', []) === null, 'ADMIN boleh membuka admin.php (tab admin bawaan)');
    assertCondition(AuthHelper::passwordChangeRedirect('ADMIN', 'admin', ['action' => 'change_admin_pass'], 'POST', []) === null, 'ADMIN boleh mengirim ganti sandi admin');
    assertCondition(AuthHelper::passwordChangeRedirect('ADMIN', 'admin', ['action' => 'save_user'], 'POST', []) === $b . '/admin.php?tab=admin', 'ADMIN tidak boleh aksi admin lain sebelum ganti sandi');
    assertCondition(AuthHelper::passwordChangeRedirect('ADMIN', 'bra', [], 'GET', []) === $b . '/admin.php?tab=admin', 'ADMIN di halaman lain diarahkan ke tab ganti sandi');
}

// Nama halaman untuk gerbang: PATH_INFO (/operasi.php/profile) tidak boleh menyamar sebagai halaman pengecualian
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
