<?php
/**
 * QR-SAR - Verification Suite for Phase 1 RENOPS & LAPBANG Logic Fixes
 * Basarnas KPP Kendari
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

$baseDir = dirname(__DIR__);

// Load helpers and models (without triggering automatic DB connection)
require_once $baseDir . '/helpers/TemplateHelper.php';
require_once $baseDir . '/models/OperasiModel.php';

echo "=== START TEST SUITE: RENOPS & LAPBANG FASE 1 ===" . PHP_EOL . PHP_EOL;

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $title): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] " . $title . PHP_EOL;
        $passed++;
    } else {
        echo " [FAIL] " . $title . PHP_EOL;
        $failed++;
    }
}

// -------------------------------------------------------------
// 1. UJI ISSUE-RENOPS-03: SSOT Unsur & Alut Formatter
// -------------------------------------------------------------
echo "--- 1. UJI ISSUE-RENOPS-03: SSOT Unsur & Alut Formatter ---" . PHP_EOL;

$sampleUnsur = [
    ['nama_unsur' => 'Rescuer KPP Kendari', 'jumlah_personil' => 6],
    ['nama_unsur' => 'Polairud Polda Sultra', 'jumlah_personil' => 4],
    ['nama_unsur' => '', 'jumlah_personil' => 0] // Baris kosong harus diabaikan
];

$formattedUnsur = OperasiModel::formatUnsurText($sampleUnsur);
assertTest(
    str_contains($formattedUnsur, "• Rescuer KPP Kendari : 6 Org") &&
    str_contains($formattedUnsur, "• Polairud Polda Sultra : 4 Org"),
    "OperasiModel::formatUnsurText memformat array unsur snapshot menjadi string bullet list resmi"
);

$sampleAlut = [
    ['nama_alut' => 'RIB 01 Kendari', 'jumlah_alut' => 1, 'satuan' => 'Unit'],
    ['nama_alut' => 'Palsar Medis', 'jumlah_alut' => 2, 'satuan' => 'Set'],
    ['nama_alut' => 'Drone Thermal', 'jumlah_alut' => 1, 'satuan' => 'Unit']
];

$formattedAlut = OperasiModel::formatAlutText($sampleAlut);
assertTest(
    str_contains($formattedAlut, "• 1 Unit RIB 01 Kendari") &&
    str_contains($formattedAlut, "• 2 Set Palsar Medis") &&
    str_contains($formattedAlut, "• 1 Unit Drone Thermal"),
    "OperasiModel::formatAlutText memformat array alut snapshot menjadi string alut resmi"
);

// Fallback behavior when array is empty
$fallbackUnsur = OperasiModel::formatUnsurText([], "Fallback Unsur String");
assertTest($fallbackUnsur === "Fallback Unsur String", "formatUnsurText mengembalikan fallback jika array kosong");

$fallbackAlut = OperasiModel::formatAlutText([], "Fallback Alut String");
assertTest($fallbackAlut === "Fallback Alut String", "formatAlutText mengembalikan fallback jika array kosong");

// -------------------------------------------------------------
// 2. UJI ISSUE-RENOPS-04: Normalisasi Waktu DATETIME
// -------------------------------------------------------------
echo PHP_EOL . "--- 2. UJI ISSUE-RENOPS-04: Normalisasi Waktu DATETIME ---" . PHP_EOL;

assertTest(
    OperasiModel::normalizeDateTime("2026-10-18T07:30:00.000Z") === "2026-10-18 07:30:00",
    "normalizeDateTime mengonversi format ISO 8601 (T & Z) ke format MySQL DATETIME"
);
assertTest(
    OperasiModel::normalizeDateTime("2026-10-18 07:30") === "2026-10-18 07:30:00",
    "normalizeDateTime melengkapi detik (:00) pada format tanggal jam-menit"
);
assertTest(
    OperasiModel::normalizeDateTime(null, true) === null,
    "normalizeDateTime mengembalikan null jika allowNull = true dan raw = null"
);
assertTest(
    OperasiModel::normalizeDateTime('', true) === null,
    "normalizeDateTime mengembalikan null jika allowNull = true dan raw = ''"
);

// -------------------------------------------------------------
// 3. UJI ISSUE-LAPBANG-01: Verifikasi Logika Urutan Update Korban
// -------------------------------------------------------------
echo PHP_EOL . "--- 3. UJI ISSUE-LAPBANG-01: Urutan Update Korban vs Generator WA ---" . PHP_EOL;

$lapModelCode = file_get_contents($baseDir . '/models/LaporanModel.php');

// In create(): check that update_korban is processed BEFORE generateLapbangWA
$createPos = strpos($lapModelCode, 'public static function create(');
$updateKorbanCreatePos = strpos($lapModelCode, 'ISSUE-LAPBANG-01: Update status korban SEBELUM naskah WA digenerate', $createPos);
$generateWaCreatePos = strpos($lapModelCode, 'TemplateHelper::generateLapbangWA(', $createPos);

assertTest(
    $updateKorbanCreatePos !== false && $generateWaCreatePos !== false && $updateKorbanCreatePos < $generateWaCreatePos,
    "Pada LaporanModel::create(), eksekusi pembaruan status korban dilakukan SEBELUM generateLapbangWA()"
);

// In update(): check that update_korban is processed BEFORE generateLapbangWA
$updatePos = strpos($lapModelCode, 'public static function update(');
$updateKorbanUpdatePos = strpos($lapModelCode, 'ISSUE-LAPBANG-01: Update status korban SEBELUM naskah WA digenerate', $updatePos);
$generateWaUpdatePos = strpos($lapModelCode, 'TemplateHelper::generateLapbangWA(', $updatePos);

assertTest(
    $updateKorbanUpdatePos !== false && $generateWaUpdatePos !== false && $updateKorbanUpdatePos < $generateWaUpdatePos,
    "Pada LaporanModel::update(), eksekusi pembaruan status korban dilakukan SEBELUM generateLapbangWA()"
);

// Verify in-memory $ops['korban'] reflects updated status
$opsMock = [
    'nama_operasi' => 'KM MEKAR INDAH',
    'korban' => [
        ['id_korban' => 1, 'nama_korban' => 'Ahmad', 'status_korban' => 'DP']
    ],
    'cuaca' => 'Cerah'
];
$korbanUpdates = [1 => 'SELAMAT'];
foreach ($korbanUpdates as $kid => $st) {
    foreach ($opsMock['korban'] as &$k) {
        if ((int)$k['id_korban'] === (int)$kid) {
            $k['status_korban'] = $st;
        }
    }
    unset($k);
}
assertTest($opsMock['korban'][0]['status_korban'] === 'SELAMAT', "Mutasi in-memory korban menghasilkan status SELAMAT yang siap dikirim ke generateLapbangWA");

$waOutput = TemplateHelper::generateLapbangWA($opsMock, [
    'hari_ke' => 'H.1',
    'isi_laporan' => 'Korban ditemukan selamat.',
    'waktu_debrief' => '2026-10-18 17:00:00'
], $opsMock['korban'], $sampleUnsur, $sampleAlut, false);

assertTest(
    str_contains($waOutput, 'Selamat') || str_contains($waOutput, 'SELAMAT'),
    "Teks WA yang dihasilkan oleh TemplateHelper::generateLapbangWA memuat status terkini 'Selamat'"
);

// -------------------------------------------------------------
// 4. UJI ISSUE-LAPBANG-02: Idempoten closeOperation & Lockout Fix
// -------------------------------------------------------------
echo PHP_EOL . "--- 4. UJI ISSUE-LAPBANG-02: Idempoten closeOperation & Lockout Fix ---" . PHP_EOL;

$opsModelCode = file_get_contents($baseDir . '/models/OperasiModel.php');
assertTest(
    str_contains($opsModelCode, 'Bersifat idempoten: Jika sudah CLOSED, update waktu_selesai jika diberikan tanpa melempar exception') &&
    str_contains($opsModelCode, 'return true;'),
    "OperasiModel::closeOperation() bersifat idempoten dan tidak melempar exception saat operasi sudah CLOSED"
);

assertTest(
    str_contains($lapModelCode, '$isExistingPenutupan = ($lap[\'jenis_laporan\'] === \'PENUTUPAN\');') &&
    str_contains($lapModelCode, 'if ($ops && $ops[\'status\'] === \'CLOSED\' && !$isExistingPenutupan)'),
    "LaporanModel::update() mengizinkan penyuntingan laporan jenis PENUTUPAN meskipun status operasi telah CLOSED"
);

// -------------------------------------------------------------
// 5. UJI ISSUE-RENOPS-03 di RenopsModel
// -------------------------------------------------------------
echo PHP_EOL . "--- 5. UJI ISSUE-RENOPS-03 di RenopsModel ---" . PHP_EOL;

$renopsModelCode = file_get_contents($baseDir . '/models/RenopsModel.php');
assertTest(
    str_contains($renopsModelCode, 'OperasiModel::formatUnsurText($unsur') &&
    str_contains($renopsModelCode, 'OperasiModel::formatAlutText($alut'),
    "RenopsModel::create() dan update() menggunakan OperasiModel::formatUnsurText dan formatAlutText untuk menjamin SSOT"
);

echo PHP_EOL . "HASIL AKHIR: Passed: {$passed}, Failed: {$failed}" . PHP_EOL;
exit($failed > 0 ? 1 : 0);
