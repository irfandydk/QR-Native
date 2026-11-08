<?php
/**
 * Test: AuditHelper::log pertama dalam satu request yang terjadi DI DALAM transaksi model
 * tidak boleh memicu implicit commit (DDL CREATE TABLE) sehingga commit() gagal
 * ("There is no active transaction") padahal data sudah tersimpan.
 * Skenario nyata: Admin > Ganti Kata Sandi melaporkan gagal tetapi sandi sebenarnya berubah.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST AUDIT LOG DI DALAM TRANSAKSI ===\n\n";

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
$setAdminPassword = function (string $plain) use ($db): void {
    $hash = password_hash($plain, PASSWORD_DEFAULT);
    $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = 'USR-ADMIN'")->execute([':p' => $hash]);
    $db->prepare("UPDATE AppSettings SET admin_password = :p WHERE id = 1")->execute([':p' => $hash]);
};

$setAdminPassword('Kendari304');

// Proses PHP baru: AuditHelper belum pernah memeriksa tabel, jadi log pertama terjadi di dalam transaksi changePassword
$r = SettingsModel::changePassword('ADMIN', 'Kendari304', 'SandiUjiTx2026');
assertCondition(($r['success'] ?? false) === true, 'changePassword melaporkan sukses (bukan "There is no active transaction")');
assertCondition(!$db->inTransaction(), 'Tidak ada transaksi menggantung setelah changePassword');

$hash = (string)$db->query("SELECT password_hash FROM `Users` WHERE id = 'USR-ADMIN'")->fetchColumn();
assertCondition(password_verify('SandiUjiTx2026', $hash), 'Sandi baru benar-benar tersimpan');

$logged = (int)$db->query("SELECT COUNT(*) FROM AuditLog WHERE action = 'PASSWORD_CHANGE' AND target_id = 'USR-ADMIN' AND created_at >= NOW() - INTERVAL 1 MINUTE")->fetchColumn();
assertCondition($logged > 0, 'Jejak audit PASSWORD_CHANGE tercatat');

// Pulihkan sandi uji bawaan
$setAdminPassword('Kendari304');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
