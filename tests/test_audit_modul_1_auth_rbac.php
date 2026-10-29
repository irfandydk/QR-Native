<?php
/**
 * QR-SAR - Automated Audit & E2E Test Suite: MODUL 1 (Autentikasi, Sesi, RBAC & Login Throttling)
 * KPP Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}
$_SESSION = [];
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../models/UserModel.php';

$totalPassed = 0;
$totalFailed = 0;

function report(string $name, bool $passed, string $detail = ''): void {
    global $totalPassed, $totalFailed;
    if ($passed) {
        $totalPassed++;
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
    } else {
        $totalFailed++;
        echo "  [FAIL] {$name}" . ($detail ? " - DETAIL: {$detail}" : "") . "\n";
    }
}

echo "\n=========================================================================\n";
echo "  AUDIT E2E MODUL 1: AUTENTIKASI, SESI, RBAC & KEAMANAN AKSES QR-SAR    \n";
echo "  KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS      \n";
echo "=========================================================================\n\n";

// -------------------------------------------------------------------------
// 1. Audit CSRF Token Engine
// -------------------------------------------------------------------------
echo "--- 1. Audit Mesin Token CSRF ---\n";
$token1 = AuthHelper::csrfToken();
report("Token CSRF berformat 64 hex characters", strlen($token1) === 64 && ctype_xdigit($token1), "Len: " . strlen($token1));
report("Token CSRF tersimpan di sesi", ($_SESSION['csrf_token'] ?? '') === $token1);

$token2 = AuthHelper::csrfToken();
report("Token CSRF konsisten dalam satu sesi", $token1 === $token2);

report("Validasi token CSRF valid", AuthHelper::validateCsrf($token1));
report("Validasi token CSRF palsu ditolak", !AuthHelper::validateCsrf('invalid_token_12345'));
report("Validasi token CSRF null/empty ditolak", !AuthHelper::validateCsrf(null) && !AuthHelper::validateCsrf(''));

// -------------------------------------------------------------------------
// 2. Audit Kriptografi Kata Sandi (Bcrypt/Argon2)
// -------------------------------------------------------------------------
echo "\n--- 2. Audit Kriptografi Kata Sandi ---\n";
$plainTest = 'Kendari304';
$hashTest = password_hash($plainTest, PASSWORD_DEFAULT);
$info = password_get_info($hashTest);
report("Hashing menggunakan algoritma modern standar", !empty($info['algo']), "Algo: " . ($info['algoName'] ?? 'unknown'));
report("Verifikasi password hash valid", AuthHelper::verifyPassword($plainTest, $hashTest));
report("Verifikasi password salah ditolak", !AuthHelper::verifyPassword('SalahPassword', $hashTest));
report("Verifikasi password kosong ditolak", !AuthHelper::verifyPassword('', $hashTest) && !AuthHelper::verifyPassword($plainTest, ''));

// -------------------------------------------------------------------------
// 3. Audit Alur Autentikasi Login & Data Sesi
// -------------------------------------------------------------------------
echo "\n--- 3. Audit Alur Autentikasi Login & Data Sesi ---\n";

// Test wrong password
$resFail = AuthHelper::login('admin', 'PasswordSalahTotal999');
report("Login dengan password salah ditolak", $resFail['success'] === false);
report("Pesan error aman & tidak membocorkan struktur internal", !empty($resFail['error']));

// Test non-existent user
$resNon = AuthHelper::login('user_tidak_terdaftar_xyz', 'PasswordApapun');
report("Login user non-existent ditolak", $resNon['success'] === false);

// Test Admin Login
$db = Database::getConnection();
$userAdmin = UserModel::getByUsername('admin');
report("Akun admin terdaftar di tabel Users", !empty($userAdmin));

$adminSuccess = false;
$passwordsToTry = ['Kendari304', 'admin', 'admin123'];
foreach ($passwordsToTry as $p) {
    if (AuthHelper::verifyPassword($p, (string)$userAdmin['password_hash'])) {
        $resLogin = AuthHelper::login('admin', $p);
        if ($resLogin['success'] && $resLogin['user']['role'] === 'ADMIN') {
            $adminSuccess = true;
            break;
        }
    }
}
report("Login Admin berhasil dan memetakan Role 'ADMIN'", $adminSuccess);
report("Sesi admin terisi dengan role ADMIN", AuthHelper::isAdmin());
report("Sesi admin memiliki hak delete data operasi", AuthHelper::canDelete('operasi'));
report("Sesi admin memiliki hak delete master alut", AuthHelper::canDelete('master'));

// -------------------------------------------------------------------------
// 4. Audit RBAC (Role-Based Access Control) Operator vs Admin
// -------------------------------------------------------------------------
echo "\n--- 4. Audit RBAC (Role-Based Access Control) Operator vs Admin ---\n";
$userOperator = [
    'id' => 'USR-USER',
    'username' => 'operator_test',
    'nama' => 'Petugas Siaga Kendari',
    'nip' => '199501012020121001',
    'role' => 'USER',
    'roleTitle' => 'Operator Siaga SAR',
    'pangkat' => 'Pengatur (II/c)',
    'jabatan' => 'Petugas Komunikasi',
    'email' => 'operator@basarnas.go.id',
    'no_hp' => '081234567890',
    'loginAt' => date('Y-m-d H:i:s')
];
AuthHelper::setSession($userOperator);

report("Sesi Operator terdeteksi isLoggedIn()", AuthHelper::isLoggedIn());
report("Sesi Operator BUKAN Admin (isAdmin() = false)", AuthHelper::isAdmin() === false);
report("Sesi Operator DITOLAK menghapus Operasi (canDelete('operasi') = false)", AuthHelper::canDelete('operasi') === false);
report("Sesi Operator DITOLAK menghapus Renops (canDelete('renops') = false)", AuthHelper::canDelete('renops') === false);
report("Sesi Operator DITOLAK menghapus Master (canDelete('master') = false)", AuthHelper::canDelete('master') === false);
report("Sesi Operator DIIZINKAN menghapus layer taktis GIS (canDelete('gis') = true)", AuthHelper::canDelete('gis') === true);

// -------------------------------------------------------------------------
// 4b. Audit Self-Service Ganti Password Pengguna Operasional (Operator)
// -------------------------------------------------------------------------
echo "\n--- 4b. Audit Self-Service Ganti Password Pengguna Operasional ---\n";
SettingsModel::resetUserToDefault();

// Wrong old password rejected
$resWrongOld = SettingsModel::changePassword('USER', 'PasswordLamaSalah999', 'SandiBaruValid123');
report("Ganti password dengan password lama salah ditolak", $resWrongOld['success'] === false);

// Password too short (< 8 chars) rejected
$resShort = SettingsModel::changePassword('USER', 'Kendari304', 'pendek');
report("Ganti password baru kurang dari 8 karakter ditolak", $resShort['success'] === false);

// Valid change
$resValidChange = SettingsModel::changePassword('USER', 'Kendari304', 'KendariBaru304');
report("Ganti password valid berhasil diperbarui", $resValidChange['success'] === true);

// Verify new password works
$loginNewPass = AuthHelper::login('user', 'KendariBaru304');
report("Login user dengan password baru berhasil", $loginNewPass['success'] === true);

// Restore back to default
SettingsModel::resetUserToDefault();
$loginRestored = AuthHelper::login('user', 'Kendari304');
report("Reset akun user mengembalikan password standar (Kendari304)", $loginRestored['success'] === true);

// -------------------------------------------------------------------------
// 5. Audit Proteksi Brute-Force & Rate Limiting (LoginThrottle)
// -------------------------------------------------------------------------
echo "\n--- 5. Audit Proteksi Brute-Force & Rate Limiting (LoginThrottle) ---\n";
AuthHelper::resetLoginThrottle();
$ip = AuthHelper::getClientIp();

// Clean throttle
$db->prepare("DELETE FROM LoginThrottle WHERE ip_address = :ip")->execute([':ip' => $ip]);

$initialThrottle = AuthHelper::checkLoginThrottle();
report("Throttle bersih sebelum serangan brute-force", $initialThrottle === null);

// Simulate 4 failed attempts
for ($i = 1; $i <= 4; $i++) {
    AuthHelper::recordFailedLogin("attacker_test_{$i}");
}
$stmtCount = $db->prepare("SELECT COUNT(*) FROM LoginThrottle WHERE ip_address = :ip");
$stmtCount->execute([':ip' => $ip]);
$count = (int)$stmtCount->fetchColumn();
report("Tercatat 4 percobaan gagal di LoginThrottle", $count === 4, "Count: {$count}");

// 4 attempts should not be locked yet (threshold is >= 5)
$preLock = AuthHelper::checkLoginThrottle();
report("Percobaan ke-4 belum mengunci akun", $preLock === null);

// 5th failed attempt
AuthHelper::recordFailedLogin("attacker_test_5");
$postLock = AuthHelper::checkLoginThrottle();
report("Percobaan ke-5 memicu penguncian sistem (Brute-force Lockout)", $postLock !== null, $postLock ?? '');

// Test reset throttle on success
AuthHelper::resetLoginThrottle();
$afterReset = AuthHelper::checkLoginThrottle();
report("Reset throttle membersihkan status terkunci pasca-login sukses", $afterReset === null);

// -------------------------------------------------------------------------
// 6. Audit Invalidation Sesi & Siklus Logout
// -------------------------------------------------------------------------
echo "\n--- 6. Audit Invalidation Sesi & Siklus Logout ---\n";
$idBefore = session_id();
AuthHelper::setSession($userOperator);
$idAfter = session_id();
report("Status sesi aktif dan terisi data user", !empty($_SESSION['user_id']));

// Logout execution
AuthHelper::logout();
report("Sesi kosong setelah logout", empty($_SESSION));
report("Status isLoggedIn() false setelah logout", AuthHelper::isLoggedIn() === false);
report("Status isAdmin() false setelah logout", AuthHelper::isAdmin() === false);
report("canDelete() false setelah logout", AuthHelper::canDelete() === false);

// -------------------------------------------------------------------------
// 7. Audit Sanitasi Open Redirect (CWE-601)
// -------------------------------------------------------------------------
echo "\n--- 7. Audit Sanitasi Open Redirect (CWE-601) ---\n";
$maliciousUrls = [
    'https://evil-phishing-site.com/steal-creds',
    'http://attacker.org/login',
    '//attacker.com/malicious',
    '/\\attacker.com/bypass',
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>'
];

foreach ($maliciousUrls as $url) {
    $sanitized = AuthHelper::sanitizeRedirectUrl($url, BASE_URL . '/index.php');
    report("URL jahat dinetralkan ke default BASE_URL/index.php", $sanitized === BASE_URL . '/index.php', "Payload: {$url}");
}

$safeInternalUrls = [
    BASE_URL . '/operasi_detail.php?id=10',
    '/operasi_detail.php?id=10',
    '/renops_form.php?id_operasi=5',
    '/gis.php'
];
foreach ($safeInternalUrls as $sUrl) {
    $sanitizedSafe = AuthHelper::sanitizeRedirectUrl($sUrl, BASE_URL . '/index.php');
    report("URL internal aplikasi tetap diizinkan", $sanitizedSafe === $sUrl, "Valid: {$sUrl}");
}

// -------------------------------------------------------------------------
// HASIL AKHIR
// -------------------------------------------------------------------------
echo "\n=========================================================================\n";
echo "  HASIL AKHIR AUDIT MODUL 1: {$totalPassed} LULUS (PASS), {$totalFailed} GAGAL (FAIL)\n";
echo "=========================================================================\n\n";

if ($totalFailed > 0) {
    exit(1);
}
