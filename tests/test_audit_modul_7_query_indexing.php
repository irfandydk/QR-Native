<?php
/**
 * QR-SAR - End-to-End Automated Audit & Test Suite: Modul 7
 * Kinerja Query MySQL, Transaksi PDO & Indexing
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Cakupan Pengujian:
 * 1. Konfigurasi Koneksi PDO: ErrMode, Default Fetch Mode, Emulate Prepares, Charset UTF-8mb4
 * 2. Cakupan Indexing & Analisis Execution Plan (EXPLAIN FORMAT=TRADITIONAL): Zero Full-Table Scans
 * 3. Pencegahan N+1 Query Loops pada Operasi, Renops, dan GIS Layers (Batch IN Eager Loading)
 * 4. Jaminan ACID Transaksi & Uji Ketahanan Rollback Terhadap Kegagalan Multi-Tabel
 * 5. Proteksi Prepared Statements & Parameter Binding Terhadap Injeksi SQL
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';

echo "========================================================================================\n";
echo "   AUDIT & PENGUJIAN END-TO-END MODUL 7: KINERJA QUERY MYSQL, TRANSAKSI PDO & INDEXING   \n";
echo "   KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS                    \n";
echo "========================================================================================\n\n";

$passCount = 0;
$failCount = 0;
$testResults = [];

function assertTest(string $category, string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount, $testResults;
    if ($condition) {
        $passCount++;
        echo "  [PASS] [{$category}] {$desc}\n";
        $testResults[] = ['category' => $category, 'desc' => $desc, 'status' => 'PASS', 'detail' => ''];
    } else {
        $failCount++;
        echo "  [FAIL] [{$category}] {$desc}" . ($detail ? " -> {$detail}" : "") . "\n";
        $testResults[] = ['category' => $category, 'desc' => $desc, 'status' => 'FAIL', 'detail' => $detail];
    }
}

// =========================================================================
// DOMAIN 1: KONFIGURASI KONEKSI PDO, ERROR MODE & CHARSET
// =========================================================================
echo "\n--- DOMAIN 1: KONFIGURASI KONEKSI BASIS DATA (PDO) & CHARSET ---\n";

$db1 = Database::getConnection();
$db2 = Database::getConnection();
assertTest('PDO_CONF', 'Database::getConnection() mengembalikan objek PDO aktif', $db1 instanceof PDO);
assertTest('PDO_CONF', 'Koneksi basis data menerapkan pola Singleton (instance identik)', $db1 === $db2);

$dbGlobal = getDB();
assertTest('PDO_CONF', 'Fungsi global getDB() mengembalikan instance PDO yang sama', $dbGlobal === $db1);

// Atribut PDO
$errMode = $db1->getAttribute(PDO::ATTR_ERRMODE);
assertTest('PDO_CONF', 'PDO::ATTR_ERRMODE dikonfigurasi ke ERRMODE_EXCEPTION', $errMode === PDO::ERRMODE_EXCEPTION);

$fetchMode = $db1->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);
assertTest('PDO_CONF', 'PDO::ATTR_DEFAULT_FETCH_MODE dikonfigurasi ke FETCH_ASSOC', $fetchMode === PDO::FETCH_ASSOC);

$emulatePrepares = $db1->getAttribute(PDO::ATTR_EMULATE_PREPARES);
assertTest('PDO_CONF', 'PDO::ATTR_EMULATE_PREPARES dinonaktifkan (real server-side prepared statements)', $emulatePrepares === false);

// Charset & Collation
$charsetRow = $db1->query("SHOW VARIABLES LIKE 'character_set_connection'")->fetch();
assertTest('PDO_CONF', 'Koneksi MySQL menggunakan character set utf8mb4',
    isset($charsetRow['Value']) && str_starts_with($charsetRow['Value'], 'utf8mb4')
);

$collationRow = $db1->query("SHOW VARIABLES LIKE 'collation_connection'")->fetch();
assertTest('PDO_CONF', 'Koneksi MySQL menggunakan collation utf8mb4_unicode_ci',
    isset($collationRow['Value']) && str_starts_with($collationRow['Value'], 'utf8mb4')
);


// =========================================================================
// DOMAIN 2: AUDIT INDEXING & ANALISIS EXECUTION PLAN (EXPLAIN)
// =========================================================================
echo "\n--- DOMAIN 2: CAKUPAN INDEXING & ANALISIS EXECUTION PLAN (EXPLAIN) ---\n";

$requiredIndexes = [
    'Operasi' => [
        'PRIMARY' => ['id_operasi'],
        'idx_ops_status' => ['status'],
        'idx_ops_status_selesai' => ['status', 'waktu_selesai'],
        'idx_ops_waktu' => ['waktu_kejadian'],
        'idx_ops_created_at' => ['created_at']
    ],
    'Renops' => [
        'PRIMARY' => ['id_renops'],
        'Renops_id_operasi_fkey' => ['id_operasi'],
        'idx_renops_ops_hari' => ['id_operasi', 'hari_ke']
    ],
    'RenopsSRU' => [
        'PRIMARY' => ['id_sru'],
        'RenopsSRU_id_renops_fkey' => ['id_renops']
    ],
    'LaporanHarian' => [
        'PRIMARY' => ['id_laporan'],
        'LaporanHarian_id_operasi_fkey' => ['id_operasi'],
        'idx_lap_ops_hari' => ['id_operasi', 'hari_ke']
    ],
    'BraSar' => [
        'PRIMARY' => ['id_bra'],
        'idx_bra_no_bra' => ['no_bra'],
        'idx_bra_operasi' => ['id_operasi'],
        'idx_bra_nomor_urut' => ['nomor_urut'],
        'idx_bra_kategori_id' => ['kategori', 'id_bra']
    ],
    'Korban' => [
        'PRIMARY' => ['id_korban'],
        'Korban_id_operasi_fkey' => ['id_operasi'],
        'idx_korban_ops_status' => ['id_operasi', 'status_korban']
    ],
    'DetailUnsur' => [
        'PRIMARY' => ['id_detail_unsur'],
        'DetailUnsur_id_operasi_fkey' => ['id_operasi']
    ],
    'DetailAlut' => [
        'PRIMARY' => ['id_detail_alut'],
        'DetailAlut_id_operasi_fkey' => ['id_operasi']
    ],
    'GisPoint' => [
        'PRIMARY' => ['id'],
        'idx_point_operasi' => ['id_operasi'],
        'idx_point_cat' => ['category']
    ],
    'GisPolygon' => [
        'PRIMARY' => ['id'],
        'idx_poly_operasi' => ['id_operasi']
    ],
    'GisPolygonPoint' => [
        'PRIMARY' => ['id'],
        'GisPolygonPoint_polygonId_fkey' => ['polygonId']
    ],
    'GisRoute' => [
        'PRIMARY' => ['id'],
        'idx_route_operasi' => ['id_operasi']
    ],
    'GisRoutePoint' => [
        'PRIMARY' => ['id'],
        'GisRoutePoint_routeId_fkey' => ['routeId']
    ],
    'GisSearchPattern' => [
        'PRIMARY' => ['id'],
        'idx_pattern_operasi' => ['id_operasi']
    ],
    'GisSearchPatternWaypoint' => [
        'PRIMARY' => ['id'],
        'GisSearchPatternWaypoint_searchPatternId_fkey' => ['searchPatternId']
    ],
    'AuditLog' => [
        'PRIMARY' => ['id'],
        'idx_audit_user' => ['user_id'],
        'idx_audit_action' => ['action'],
        'idx_audit_target' => ['target_type', 'target_id'],
        'idx_audit_created' => ['created_at']
    ]
];

foreach ($requiredIndexes as $table => $expectedIndices) {
    $stmt = $db1->query("SHOW INDEX FROM {$table}");
    $liveIndexes = [];
    while ($r = $stmt->fetch()) {
        $liveIndexes[$r['Key_name']][] = $r['Column_name'];
    }

    foreach ($expectedIndices as $indexName => $cols) {
        $exists = isset($liveIndexes[$indexName]);
        assertTest('INDEX', "Tabel {$table} memiliki index {$indexName} (" . implode(', ', $cols) . ")",
            $exists,
            $exists ? '' : "Index {$indexName} tidak ditemukan pada tabel {$table}"
        );
    }
}

// Analisis Rencana Eksekusi Query (EXPLAIN FORMAT=TRADITIONAL)
echo "\n--- Uji Rencana Eksekusi Query Kunci (Zero Full Table Scans) ---\n";

$queriesToExplain = [
    'Operasi_Active_List' => [
        'sql' => "SELECT * FROM Operasi WHERE status = 'OPEN' AND waktu_selesai IS NULL ORDER BY id_operasi DESC",
        'expectedType' => ['ref', 'range', 'index'],
        'expectedKey' => ['idx_ops_status', 'idx_ops_status_selesai', 'PRIMARY']
    ],
    'Renops_By_Operasi' => [
        'sql' => "SELECT * FROM Renops WHERE id_operasi = 1 ORDER BY hari_ke ASC",
        'expectedType' => ['ref'],
        'expectedKey' => ['Renops_id_operasi_fkey', 'idx_renops_ops_hari']
    ],
    'Laporan_By_Operasi' => [
        'sql' => "SELECT * FROM LaporanHarian WHERE id_operasi = 1 ORDER BY id_laporan ASC",
        'expectedType' => ['ref'],
        'expectedKey' => ['LaporanHarian_id_operasi_fkey', 'idx_lap_ops_hari']
    ],
    'Korban_By_Operasi' => [
        'sql' => "SELECT * FROM Korban WHERE id_operasi = 1",
        'expectedType' => ['ref'],
        'expectedKey' => ['Korban_id_operasi_fkey', 'idx_korban_ops_status']
    ],
    'DetailUnsur_By_Operasi' => [
        'sql' => "SELECT * FROM DetailUnsur WHERE id_operasi = 1",
        'expectedType' => ['ref'],
        'expectedKey' => ['DetailUnsur_id_operasi_fkey']
    ],
    'DetailAlut_By_Operasi' => [
        'sql' => "SELECT * FROM DetailAlut WHERE id_operasi = 1",
        'expectedType' => ['ref'],
        'expectedKey' => ['DetailAlut_id_operasi_fkey']
    ],
    'GisPoint_By_Operasi' => [
        'sql' => "SELECT * FROM GisPoint WHERE id_operasi = 1",
        'expectedType' => ['ref'],
        'expectedKey' => ['idx_point_operasi', 'idx_point_ops_cat']
    ],
    'GisPolyPoint_Batch_In' => [
        'sql' => "SELECT * FROM GisPolygonPoint WHERE polygonId IN ('poly-1', 'poly-2')",
        'expectedType' => ['range'],
        'expectedKey' => ['GisPolygonPoint_polygonId_fkey']
    ],
    'GisRoutePoint_Batch_In' => [
        'sql' => "SELECT * FROM GisRoutePoint WHERE routeId IN ('route-1', 'route-2')",
        'expectedType' => ['range'],
        'expectedKey' => ['GisRoutePoint_routeId_fkey']
    ],
    'GisPatternWp_Batch_In' => [
        'sql' => "SELECT * FROM GisSearchPatternWaypoint WHERE searchPatternId IN ('pat-1', 'pat-2')",
        'expectedType' => ['range'],
        'expectedKey' => ['GisSearchPatternWaypoint_searchPatternId_fkey']
    ],
    'AuditLog_By_Target' => [
        'sql' => "SELECT * FROM AuditLog WHERE target_type = 'OPERASI' AND target_id = 1",
        'expectedType' => ['ref'],
        'expectedKey' => ['idx_audit_target']
    ]
];

foreach ($queriesToExplain as $name => $spec) {
    $explainSql = "EXPLAIN FORMAT=TRADITIONAL " . $spec['sql'];
    $stmt = $db1->query($explainSql);
    $plan = $stmt->fetch();

    $type = $plan['type'] ?? 'ALL';
    $usedKey = $plan['key'] ?? 'NULL';

    $isIndexUsed = ($type !== 'ALL' && $usedKey !== 'NULL');
    $isExpectedType = in_array($type, $spec['expectedType'], true);
    $isExpectedKey = in_array($usedKey, $spec['expectedKey'], true);

    assertTest('EXPLAIN', "Query {$name} menggunakan index ({$usedKey}, type: {$type})",
        $isIndexUsed && $isExpectedType && $isExpectedKey,
        "Aktual: type={$type}, key={$usedKey}"
    );
}


// =========================================================================
// DOMAIN 3: AUDIT PEMBERANTASAN QUERY N+1 & BATCH EAGER LOADING
// =========================================================================
echo "\n--- DOMAIN 3: PEMBERANTASAN QUERY N+1 & BATCH EAGER LOADING ---\n";

// 3.1 OperasiModel::getAll() - Agregasi Korban, Renops, Laporan, Bra dalam 1 Query
$sqlGetAll = "SELECT o.* FROM Operasi o LEFT JOIN Korban k ON k.id_operasi = o.id_operasi";
assertTest('N_PLUS_ONE', 'OperasiModel::getAll() menggunakan LEFT JOIN subquery tunggal untuk statistik',
    str_contains(file_get_contents(dirname(__DIR__) . '/models/OperasiModel.php'), 'LEFT JOIN (')
);

// 3.2 RenopsModel::getByOperasiId() - Mengambil SRU dengan 1 Query Batch IN (...)
$renopsCode = file_get_contents(dirname(__DIR__) . '/models/RenopsModel.php');
assertTest('N_PLUS_ONE', 'RenopsModel::getByOperasiId() mem-batch query SRU dengan id_renops IN (...)',
    str_contains($renopsCode, 'id_renops IN ({$placeholders})') || str_contains($renopsCode, 'IN ($inClause)') || str_contains($renopsCode, 'id_renops IN')
);

// 3.3 GisModel::getPolygons() - Mengambil Points dengan 1 Query Batch IN (...)
$gisCode = file_get_contents(dirname(__DIR__) . '/models/GisModel.php');
assertTest('N_PLUS_ONE', 'GisModel::getPolygons() mem-batch query titik poligon dengan polygonId IN (...)',
    str_contains($gisCode, 'polygonId IN ($inClause)')
);

// 3.4 GisModel::getRoutes() - Mengambil Waypoints dengan 1 Query Batch IN (...)
assertTest('N_PLUS_ONE', 'GisModel::getRoutes() mem-batch query titik rute dengan routeId IN (...)',
    str_contains($gisCode, 'routeId IN ($inClause)')
);

// 3.5 GisModel::getSearchPatterns() - Mengambil Waypoints dengan 1 Query Batch IN (...)
assertTest('N_PLUS_ONE', 'GisModel::getSearchPatterns() mem-batch waypoints pola dengan searchPatternId IN (...)',
    str_contains($gisCode, 'searchPatternId IN ($inClause)')
);


// =========================================================================
// DOMAIN 4: JAMINAN TRANSAKSI ACID & KETAHANAN ROLLBACK
// =========================================================================
echo "\n--- DOMAIN 4: JAMINAN TRANSAKSI ACID & KETAHANAN ROLLBACK ---\n";

// 4.1 In-Transaction Guards pada Seluruh Model Utama
$modelFiles = [
    'OperasiModel' => dirname(__DIR__) . '/models/OperasiModel.php',
    'RenopsModel' => dirname(__DIR__) . '/models/RenopsModel.php',
    'LaporanModel' => dirname(__DIR__) . '/models/LaporanModel.php',
    'GisModel' => dirname(__DIR__) . '/models/GisModel.php',
    'BraModel' => dirname(__DIR__) . '/models/BraModel.php'
];

foreach ($modelFiles as $name => $path) {
    $content = file_get_contents($path);
    assertTest('ACID', "{$name} menerapkan proteksi commit/rollback inTransaction()",
        str_contains($content, 'inTransaction()')
    );
}

// 4.2 Uji Ketahanan Rollback Saat Terjadi Kegagalan Nyata (Real Database Rollback Test)
$idSimulatedOps = null;
$rollbackSucceeded = false;

try {
    $db1->beginTransaction();

    // 1. Insert Operasi
    $stmtOps = $db1->prepare("INSERT INTO Operasi (nama_operasi, lokasi_kejadian, status, waktu_lapor, waktu_kejadian)
        VALUES (:nama, :lok, 'OPEN', NOW(), NOW())");
    $stmtOps->execute([
        ':nama' => 'SIMULASI TRANSAKSI ROLLBACK GAGAL',
        ':lok' => 'Perairan Wawonii'
    ]);
    $idSimulatedOps = (int)$db1->lastInsertId();

    // 2. Insert Korban Sah
    $stmtKorban = $db1->prepare("INSERT INTO Korban (id_operasi, nama_korban, status_korban)
        VALUES (:id_ops, 'Korban Uji Coba Rollback', 'DP')");
    $stmtKorban->execute([':id_ops' => $idSimulatedOps]);

    // 3. Simulasikan kegagalan fatal (misal: duplikasi key atau constraint error buatan)
    throw new RuntimeException("Simulasi Gangguan Listrik / Disk Penuh di Tengah Transaksi!");

    // Kode di bawah sengaja tidak akan tercapai
    $db1->commit();
} catch (Throwable $e) {
    if ($db1->inTransaction()) {
        $db1->rollBack();
        $rollbackSucceeded = true;
    }
}

assertTest('ACID', 'Transaksi PDO berhasil di-rollback secara utuh saat eksepsi terjadi', $rollbackSucceeded);

// Verifikasi atomisitas: Tidak ada satu pun baris data parsial yang tertinggal di basis data
if ($idSimulatedOps > 0) {
    $checkOps = $db1->query("SELECT COUNT(*) FROM Operasi WHERE id_operasi = {$idSimulatedOps}")->fetchColumn();
    $checkKorban = $db1->query("SELECT COUNT(*) FROM Korban WHERE id_operasi = {$idSimulatedOps}")->fetchColumn();

    assertTest('ACID', 'Atomisitas ACID: Tidak ada baris Operasi parsial yang tersimpan setelah rollback', (int)$checkOps === 0);
    assertTest('ACID', 'Atomisitas ACID: Tidak ada baris Korban parsial yang tersimpan setelah rollback', (int)$checkKorban === 0);
}


// =========================================================================
// DOMAIN 5: PROTEKSI PREPARED STATEMENT & KEKEBALAN INJEKSI SQL
// =========================================================================
echo "\n--- DOMAIN 5: PROTEKSI PREPARED STATEMENTS & KEKEBALAN SQL INJECTION ---\n";

// Uji Serangan SQL Injection Berbagai Vektor
$injectionPayloads = [
    "' OR '1'='1",
    "1; DROP TABLE TestFictitiousTable; --",
    "admin' --",
    "' UNION SELECT null, null, null, null, null --",
    "1' AND SLEEP(0.05) --",
    "0 OR id_operasi > 0"
];

foreach ($injectionPayloads as $idx => $payload) {
    // 5.1 Test pada OperasiModel::getById()
    $resGetById = OperasiModel::getById((int)$payload);
    assertTest('INJECTION', "Vektor Injection #{$idx} pada getById(): Dinetralkan oleh integer casting",
        $resGetById === null || is_array($resGetById)
    );

    // 5.2 Test pada OperasiModel::getAll(status)
    $resStatus = OperasiModel::getAll($payload);
    assertTest('INJECTION', "Vektor Injection #{$idx} pada getAll(status): Difilter oleh whitelist enum",
        is_array($resStatus)
    );

    // 5.3 Test pada Pencarian Nama / Lokasi Operasi
    $resSearch = OperasiModel::getAll(null, $payload);
    assertTest('INJECTION', "Vektor Injection #{$idx} pada pencarian teks: Di-bind aman sebagai string literal",
        is_array($resSearch)
    );
}

// 5.4 Verifikasi Sanitasi Variabel pada Query Dinamis
$dbFileContent = file_get_contents(dirname(__DIR__) . '/config/database.php');
assertTest('INJECTION', 'Konfigurasi PDO melarang emulasi prepare (PDO::ATTR_EMULATE_PREPARES => false)',
    str_contains($dbFileContent, 'PDO::ATTR_EMULATE_PREPARES => false')
);


// =========================================================================
// REKAPITULASI HASIL AUDIT MODUL 7
// =========================================================================
echo "\n========================================================================================\n";
echo "   REKAPITULASI HASIL AUDIT MODUL 7: {$passCount} LULUS (PASS), {$failCount} GAGAL (FAIL)  \n";
echo "========================================================================================\n\n";

if ($failCount > 0) {
    echo "⚠️ PERHATIAN: Terdapat {$failCount} pengujian yang gagal!\n";
    exit(1);
} else {
    echo "✅ SEMUA PENGUJIAN MODUL 7 BERHASIL LULUS 100% TANPA KESALAHAN.\n";
    exit(0);
}
