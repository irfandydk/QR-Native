<?php
/**
 * Test: ekspor CSV audit log menetralkan sel yang diawali karakter formula (= + - @ tab CR)
 * agar tidak dieksekusi sebagai rumus saat dibuka di Excel/LibreOffice (CSV injection).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST NETRALISASI FORMULA CSV AUDIT ===\n\n";

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

AuditHelper::ensureTableExists();
$db = getDB();
$marker = 'CSVFORMULA' . bin2hex(random_bytes(4));
$cases = [
    "=HYPERLINK(\"http://contoh.invalid\",\"{$marker}\")" => true,
    "+1+1 {$marker}" => true,
    "-1+1 {$marker}" => true,
    "@SUM(A1) {$marker}" => true,
    "\t=1+1 {$marker}" => true,
    "Catatan biasa {$marker}" => false,
];
$ins = $db->prepare("INSERT INTO `AuditLog` (user_id, username, role, action, target_type, target_id, description, ip_address, user_agent, created_at)
    VALUES ('USR-ADMIN', :u, 'ADMIN', 'TEST_CSV', NULL, NULL, :d, '127.0.0.1', 'cli', NOW())");
foreach (array_keys($cases) as $d) {
    $ins->execute([':u' => '=cmd|' . $marker, ':d' => $d]);
}

// exportCsv() memanggil exit, jadi dijalankan di proses PHP terpisah
$code = 'require_once ' . var_export(__DIR__ . '/bootstrap.php', true) . '; require_once '. var_export(__DIR__ . '/../config/app.php', true) . '; AuditHelper::exportCsv(null, ' . var_export($marker, true) . ');';
$csv = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code));
$db->prepare("DELETE FROM `AuditLog` WHERE description LIKE :m")->execute([':m' => "%{$marker}%"]);

$rows = [];
$fh = fopen('php://memory', 'r+');
fwrite($fh, preg_replace('/^\xEF\xBB\xBF/', '', $csv));
rewind($fh);
fgetcsv($fh, null, ',', '"', '\\');
while (($r = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
    $rows[$r[8] ?? ''] = $r;
}
fclose($fh);

assertCondition(count($rows) === count($cases), 'Seluruh baris uji ikut terekspor (' . count($rows) . '/' . count($cases) . ')');
foreach ($cases as $d => $dangerous) {
    $expected = $dangerous ? "'" . $d : $d;
    assertCondition(isset($rows[$expected]), ($dangerous ? 'Sel berbahaya dinetralkan: ' : 'Sel biasa tidak diubah: ') . json_encode(substr($d, 0, 12)));
}
$any = $rows ? reset($rows) : [];
assertCondition(($any[3] ?? '') === "'=cmd|{$marker}", 'Kolom pengguna ikut dinetralkan');
assertCondition(($any[5] ?? '') === '-' && ($any[6] ?? '') === '-', 'Penanda kosong "-" tetap utuh');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
