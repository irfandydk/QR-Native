<?php
/**
 * QR-SAR - Automated Test Suite for Phase 7 (Orphan Views Cleanup & Migration Tracker)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Verifies:
 * 1. Dead code elimination: views/master/settings.php removed.
 * 2. ViewHelper fallback for master_settings.php mapped cleanly to admin/index.php.
 * 3. Router legacy /settings and /settings.php HTTP 301 redirection rules.
 * 4. Database automated migrations table existence and applied files integrity.
 * 5. Idempotent execution of database/migrate.php.
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/app.php';

echo "====================================================================" . PHP_EOL;
echo "   UJI OTOMATIS FASE 7: BERKAS YATIM, ROUTER & MIGRATION TRACKER   " . PHP_EOL;
echo "====================================================================" . PHP_EOL;

$passed = 0;
$failed = 0;

function assertCondition(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$name}" . PHP_EOL;
        $passed++;
    } else {
        echo "  [FAIL] {$name}" . ($detail ? " => {$detail}" : '') . PHP_EOL;
        $failed++;
    }
}

// ---------------------------------------------------------------------
// 1. VERIFIKASI PEMBERSIHAN BERKAS YATIM (DEAD VIEW CLEANUP)
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 1. VERIFIKASI PEMBERSIHAN BERKAS YATIM (DEAD VIEW) ---" . PHP_EOL;

$deadViewPath = $rootDir . '/views/master/settings.php';
assertCondition(
    "Berkas mati views/master/settings.php (41 KB) telah dimusnahkan secara tuntas",
    !file_exists($deadViewPath)
);

$viewHelperContent = file_get_contents($rootDir . '/helpers/ViewHelper.php');
assertCondition(
    "ViewHelper aliasMap tidak lagi merujuk ke views/master/settings.php",
    !str_contains($viewHelperContent, "'master/settings.php'")
);

$resolvedView = ViewHelper::resolve('master_settings.php');
assertCondition(
    "ViewHelper::resolve('master_settings.php') dialihkan dengan aman ke admin/index.php",
    str_ends_with(str_replace('\\', '/', $resolvedView), 'views/admin/index.php')
);

// ---------------------------------------------------------------------
// 2. VERIFIKASI ROUTER & REDIRECT /settings
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 2. VERIFIKASI ROUTER REDIRECT UNTUK /settings ---" . PHP_EOL;

$routerContent = file_get_contents($rootDir . '/helpers/Router.php');
assertCondition(
    "Router memiliki blok redirect khusus untuk /settings dan /settings.php",
    str_contains($routerContent, '$path === \'/settings\'') && str_contains($routerContent, 'header("Location:')
);

$rootSettingsContent = file_get_contents($rootDir . '/settings.php');
assertCondition(
    "Berkas root settings.php menggunakan HTTP 301 permanent redirect",
    str_contains($rootSettingsContent, '301') && str_contains($rootSettingsContent, 'admin.php')
);

// ---------------------------------------------------------------------
// 3. VERIFIKASI TABEL MIGRATIONS & TRACKER DATABASE
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 3. VERIFIKASI BASIS DATA: TABEL MIGRATIONS ---" . PHP_EOL;

try {
    $db = Database::getConnection();

    // Pastikan tabel migrations ada
    $checkTable = $db->query("SHOW TABLES LIKE 'migrations'")->fetchColumn();
    assertCondition(
        "Tabel 'migrations' berhasil dibuat di basis data MySQL",
        !empty($checkTable)
    );

    // Pastikan skema kolom tabel migrations sesuai spesifikasi
    $columns = $db->query("SHOW COLUMNS FROM `migrations`")->fetchAll(PDO::FETCH_COLUMN);
    $expectedCols = ['id', 'migration', 'batch', 'applied_at'];
    $colsDiff = array_diff($expectedCols, $columns);
    assertCondition(
        "Tabel 'migrations' memiliki kolom standar: id, migration, batch, applied_at",
        empty($colsDiff)
    );

    // Periksa migrasi yang terdaftar
    $applied = $db->query("SELECT `migration`, `batch` FROM `migrations` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
    $appliedMap = [];
    foreach ($applied as $row) {
        $appliedMap[$row['migration']] = (int)$row['batch'];
    }

    assertCondition(
        "Migrasi '2026_10_18_add_composite_indexes.sql' tercatat di tabel migrations",
        isset($appliedMap['2026_10_18_add_composite_indexes.sql'])
    );

    assertCondition(
        "Migrasi '2026_10_18_normalize_datetime.sql' tercatat di tabel migrations",
        isset($appliedMap['2026_10_18_normalize_datetime.sql'])
    );

    assertCondition(
        "Migrasi '2026_10_18_standardize_bra_kategori.sql' tercatat di tabel migrations",
        isset($appliedMap['2026_10_18_standardize_bra_kategori.sql'])
    );

} catch (Throwable $e) {
    assertCondition("Koneksi & inspeksi tabel migrations", false, $e->getMessage());
}

// ---------------------------------------------------------------------
// 4. VERIFIKASI IDEMPOTENSI DATABASE RUNNER
// ---------------------------------------------------------------------
echo PHP_EOL . "--- 4. VERIFIKASI IDEMPOTENSI DATABASE RUNNER ---" . PHP_EOL;

$output = [];
$returnCode = 0;
exec('/opt/homebrew/bin/php ' . escapeshellarg($rootDir . '/database/migrate.php') . ' 2>&1', $output, $returnCode);
$outputText = implode("\n", $output);

assertCondition(
    "database/migrate.php dieksekusi dengan kode keluar 0 (Sukses)",
    $returnCode === 0
);

assertCondition(
    "database/migrate.php mendeteksi tidak ada migrasi tertunda (Schema up-to-date)",
    str_contains($outputText, 'No pending SQL migration files found')
);

// ---------------------------------------------------------------------
// RINGKASAN
// ---------------------------------------------------------------------
echo PHP_EOL . "====================================================================" . PHP_EOL;
echo "  Total Pemeriksaan Asersi : " . ($passed + $failed) . PHP_EOL;
echo "  Lulus (PASS)             : {$passed}" . PHP_EOL;
echo "  Gagal (FAIL)             : {$failed}" . PHP_EOL;
echo "====================================================================" . PHP_EOL;

if ($failed > 0) {
    exit(1);
}
exit(0);
