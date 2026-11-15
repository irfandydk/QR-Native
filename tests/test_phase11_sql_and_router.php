<?php
/**
 * QR-SAR - Automated Test Suite: Fase 11 (SQL Query Performance Tuning & Router Unification)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertCondition(string $description, bool $condition): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "\n====================================================================\n";
echo "   UJI OTOMATIS FASE 11: SQL QUERY TUNING & ROUTER UNIFICATION      \n";
echo "====================================================================\n";

$rootDir = dirname(__DIR__);

// --- 1. UJI QUERY TUNING BRAMODEL::GETALL() DENGAN DERIVED TABLE JOIN ---
echo "\n--- 1. EVALUASI QUERY TUNING BRAMODEL::GETALL() ---\n";

$t0 = microtime(true);
$allBras = BraModel::getAll();
$elapsed = (microtime(true) - $t0) * 1000;

assertCondition("BraModel::getAll() berhasil dieksekusi tanpa error", is_array($allBras));
assertCondition("Waktu eksekusi query BraModel::getAll() sangat cepat (< 50ms) [Real: " . round($elapsed, 2) . "ms]", $elapsed < 50.0);

if (!empty($allBras)) {
    $firstBra = $allBras[0];
    assertCondition("Hasil query memuat atribut total_sections", array_key_exists('total_sections', $firstBra));
    assertCondition("total_sections bertipe numerik / integer >= 0", is_numeric($firstBra['total_sections']) && (int)$firstBra['total_sections'] >= 0);
}

// Uji filter kategori
$opsBras = BraModel::getAll('OPERASI_SAR');
assertCondition("BraModel::getAll('OPERASI_SAR') mengembalikan array hasil", is_array($opsBras));

// Uji filter pencarian
$searchBras = BraModel::getAll(null, 'SAR');
assertCondition("BraModel::getAll(null, 'SAR') mengembalikan array hasil", is_array($searchBras));


// --- 2. UJI INTEGRITAS INDEKS KOMPOSIT DATABASE ---
echo "\n--- 2. MEMERIKSA KEBERADAAN INDEKS KOMPOSIT DATABASE ---\n";
$db = getDB();

function checkTableIndexExists(PDO $db, string $table, string $indexName): bool {
    $stmt = $db->prepare("SHOW INDEX FROM `{$table}` WHERE Key_name = :idx");
    $stmt->execute([':idx' => $indexName]);
    return !empty($stmt->fetchAll());
}

$hasIdxBra = checkTableIndexExists($db, 'BraSar', 'idx_bra_kategori_id');
assertCondition("Indeks komposit idx_bra_kategori_id ada pada tabel BraSar", $hasIdxBra);

$hasIdxBraSec = checkTableIndexExists($db, 'BraSection', 'idx_brasec_bra_kode');
assertCondition("Indeks komposit idx_brasec_bra_kode ada pada tabel BraSection", $hasIdxBraSec);

$hasIdxPoint = checkTableIndexExists($db, 'GisPoint', 'idx_point_ops_cat');
assertCondition("Indeks komposit idx_point_ops_cat ada pada tabel GisPoint", $hasIdxPoint);


// --- 3. UJI KEAMANAN & UNIFIKASI ROUTER DEV SERVER ---
echo "\n--- 3. MEMERIKSA ATURAN PROTEKSI ROUTER.PHP & PUBLIC/ROUTER.PHP ---\n";

$routerRoot = file_get_contents($rootDir . '/router.php');
$routerPublic = file_get_contents($rootDir . '/public/router.php');

$securityPatternDir = '#^/(database|config|models|controllers|services|helpers|views|storage|tests|cli|scratch)/#i';
$securityPatternExt = '#\.(sql|sqlite|db|env|log|md|htaccess|sample)$#i';

assertCondition("router.php memuat pola blokade direktori sensitif", str_contains($routerRoot, 'database|config|models|controllers|services|helpers|views|storage|tests'));
assertCondition("public/router.php memuat pola blokade direktori sensitif", str_contains($routerPublic, 'database|config|models|controllers|services|helpers|views|storage|tests'));
assertCondition("router.php memuat pola blokade ekstensi berbahaya (.env, .sql, dll)", str_contains($routerRoot, 'sql|sqlite|db|env|log|md'));
assertCondition("public/router.php memuat pola blokade ekstensi berbahaya (.env, .sql, dll)", str_contains($routerPublic, 'sql|sqlite|db|env|log|md'));

$isBlocked = function(string $uri) use ($securityPatternDir, $securityPatternExt): bool {
    return (bool)(preg_match($securityPatternDir, $uri) || preg_match($securityPatternExt, $uri));
};

assertCondition("Pola keamanan memblokir direktori /storage/", $isBlocked('/storage/logs/daily.log'));
assertCondition("Pola keamanan memblokir direktori /database/", $isBlocked('/database/migrate.php'));
assertCondition("Pola keamanan memblokir berkas kredensial .env", $isBlocked('/.env'));
assertCondition("Pola keamanan memblokir dump database .sql", $isBlocked('/database_dump.sql'));
assertCondition("Pola keamanan memblokir direktori /controllers/", $isBlocked('/controllers/BraController.php'));
assertCondition("Pola keamanan memblokir direktori /services/", $isBlocked('/services/GisExportService.php'));
assertCondition("Pola keamanan mengizinkan aset statis /assets/css/app.css", !$isBlocked('/assets/css/app.css'));
assertCondition("Pola keamanan mengizinkan aset upload /uploads/icons/marker.svg", !$isBlocked('/uploads/icons/marker.svg'));

assertCondition("Router::serveStaticFile() terdefinisi di helpers/Router.php", method_exists('Router', 'serveStaticFile'));


// --- 4. UJI CLEAN URL HELPER ROUTER::URL() & GLOBAL URL() ---
echo "\n--- 4. MENGUJI GENERASI URL DENGAN ROUTER::URL() & GLOBAL URL() ---\n";

assertCondition("Fungsi global url() tersedia", function_exists('url'));
assertCondition("Metode statis Router::url() tersedia", method_exists('Router', 'url'));

$url1 = Router::url('operasi/detail', ['id' => 42]);
assertCondition("Router::url('operasi/detail', ['id' => 42]) menghasilkan tautan valid", str_contains($url1, '/operasi/detail?id=42'));

$url2 = url('master', ['tab' => 'alut', 'q' => 'rib']);
assertCondition("url('master', ['tab' => 'alut', 'q' => 'rib']) menghasilkan tautan multi-param", str_contains($url2, '/master?tab=alut&q=rib'));

$url3 = url('/bra');
assertCondition("url('/bra') menangani leading slash dengan bersih tanpa double slash", !str_contains($url3, '//bra'));

// --- 5. REGRESI DETEKSI BASE PATH & ROUTING STATIS /uploads/ ---
echo "\n--- 5. MENGUJI DETEKSI BASE PATH (CLI-SERVER & APACHE SUBFOLDER) ---\n";

assertCondition(
    "Fungsi qrsar_detect_base_path() tersedia",
    function_exists('qrsar_detect_base_path')
);
assertCondition(
    "cli-server di /uploads/logo.png tidak menghasilkan prefix '/uploads'",
    qrsar_detect_base_path([
        'DOCUMENT_ROOT' => $rootDir,
        'SCRIPT_NAME' => '/uploads/satker_logo_1791763778.png',
        'SCRIPT_FILENAME' => $rootDir . '/router.php',
    ], $rootDir, 'cli-server') === ''
);
assertCondition(
    "cli-server di clean route bertingkat (/operasi/detail) mengembalikan prefix kosong",
    qrsar_detect_base_path([
        'DOCUMENT_ROOT' => $rootDir,
        'SCRIPT_NAME' => '/operasi/detail',
        'SCRIPT_FILENAME' => $rootDir . '/router.php',
    ], $rootDir, 'cli-server') === ''
);
assertCondition(
    "cli-server dengan docroot -t public mengembalikan prefix kosong",
    qrsar_detect_base_path([
        'DOCUMENT_ROOT' => $rootDir . '/public',
        'SCRIPT_NAME' => '/uploads/icons/marker.png',
        'SCRIPT_FILENAME' => $rootDir . '/public/router.php',
    ], $rootDir, 'cli-server') === ''
);
assertCondition(
    "cli-server dari direktori induk mendeteksi subfolder proyek",
    qrsar_detect_base_path([
        'DOCUMENT_ROOT' => dirname($rootDir),
        'SCRIPT_NAME' => '/' . basename($rootDir) . '/uploads/logo.png',
    ], $rootDir, 'cli-server') === '/' . basename($rootDir)
);
assertCondition(
    "Apache/Laragon subfolder lewat router.php mendeteksi '/QR-SAR'",
    qrsar_detect_base_path([
        'SCRIPT_NAME' => '/QR-SAR/router.php',
        'SCRIPT_FILENAME' => $rootDir . '/router.php',
    ], $rootDir, 'apache2handler') === '/QR-SAR'
);
assertCondition(
    "Apache/Laragon subfolder lewat api/index.php mendeteksi '/QR-SAR'",
    qrsar_detect_base_path([
        'SCRIPT_NAME' => '/QR-SAR/api/index.php',
        'SCRIPT_FILENAME' => $rootDir . '/api/index.php',
    ], $rootDir, 'apache2handler') === '/QR-SAR'
);
assertCondition(
    "Apache VirtualHost dengan DocumentRoot=public mendeteksi prefix kosong",
    qrsar_detect_base_path([
        'SCRIPT_NAME' => '/index.php',
        'SCRIPT_FILENAME' => $rootDir . '/public/index.php',
    ], $rootDir, 'apache2handler') === ''
);
assertCondition(
    "router.php menyajikan /uploads/ lewat Router::serveStaticFile sebelum bootstrap app",
    str_contains($routerRoot, "str_starts_with(\$uri, '/uploads/')") && str_contains($routerRoot, 'Router::serveStaticFile($filePath)')
);

echo "\n====================================================================\n";
echo "  Total Pemeriksaan Asersi : {$totalAssertions}\n";
echo "  Lulus (PASS)             : {$passedAssertions}\n";
echo "  Gagal (FAIL)             : " . ($totalAssertions - $passedAssertions) . "\n";
echo "====================================================================\n";

if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
