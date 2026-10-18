<?php
/**
 * QR-SAR - Automated Test Suite: Phase 3 SQL Optimizations & Composite Indexes
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

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

echo "\n--- PENGUJIAN FASE 3: OPTIMALISASI QUERY SQL & DATABASE INDEXING ---\n";

$db = getDB();

// 1. Pengujian Refactoring OperasiModel::getAll()
echo "\n1. Memeriksa Struktur Kode & Query OperasiModel::getAll():\n";
$opsModelFile = file_get_contents(__DIR__ . '/../models/OperasiModel.php');
assertCondition("OperasiModel::getAll() tidak menggunakan correlated scalar subquery untuk Korban", !str_contains($opsModelFile, "(SELECT COUNT(*) FROM Korban k WHERE k.id_operasi = o.id_operasi)"));
assertCondition("OperasiModel::getAll() tidak menggunakan correlated scalar subquery untuk Renops", !str_contains($opsModelFile, "(SELECT COUNT(*) FROM Renops r WHERE r.id_operasi = o.id_operasi)"));
assertCondition("OperasiModel::getAll() tidak menggunakan correlated scalar subquery untuk LaporanHarian", !str_contains($opsModelFile, "(SELECT COUNT(*) FROM LaporanHarian l WHERE l.id_operasi = o.id_operasi)"));
assertCondition("OperasiModel::getAll() tidak menggunakan correlated scalar subquery untuk BraSar", !str_contains($opsModelFile, "(SELECT COUNT(*) FROM BraSar b WHERE b.id_operasi = o.id_operasi)"));
assertCondition("OperasiModel::getAll() menggunakan LEFT JOIN derived aggregation untuk Korban", str_contains($opsModelFile, "LEFT JOIN (\n                SELECT id_operasi,\n                       COUNT(*) AS total_korban_count"));
assertCondition("OperasiModel::getAll() menggunakan LEFT JOIN derived aggregation untuk Renops", str_contains($opsModelFile, "LEFT JOIN (\n                SELECT id_operasi, COUNT(*) AS total_renops \n                FROM Renops"));
assertCondition("OperasiModel::getAll() menggunakan LEFT JOIN derived aggregation untuk LaporanHarian", str_contains($opsModelFile, "LEFT JOIN (\n                SELECT id_operasi, COUNT(*) AS total_laporan \n                FROM LaporanHarian"));
assertCondition("OperasiModel::getAll() menggunakan LEFT JOIN derived aggregation untuk BraSar", str_contains($opsModelFile, "LEFT JOIN (\n                SELECT id_operasi, COUNT(*) AS total_bra \n                FROM BraSar"));

// 2. Pengujian Runtime Execution OperasiModel::getAll()
echo "\n2. Menguji Eksekusi Runtime OperasiModel::getAll():\n";
$allOps = OperasiModel::getAll();
assertCondition("OperasiModel::getAll() berhasil dieksekusi tanpa error", is_array($allOps));
if (!empty($allOps)) {
    $first = $allOps[0];
    assertCondition("Hasil memiliki field total_korban_count", array_key_exists('total_korban_count', $first));
    assertCondition("Hasil memiliki field korban_s", array_key_exists('korban_s', $first));
    assertCondition("Hasil memiliki field korban_md", array_key_exists('korban_md', $first));
    assertCondition("Hasil memiliki field korban_dp", array_key_exists('korban_dp', $first));
    assertCondition("Hasil memiliki field korban_h", array_key_exists('korban_h', $first));
    assertCondition("Hasil memiliki field total_renops", array_key_exists('total_renops', $first));
    assertCondition("Hasil memiliki field total_laporan", array_key_exists('total_laporan', $first));
    assertCondition("Hasil memiliki field total_bra", array_key_exists('total_bra', $first));
}

// 3. Pengujian Filter Status & Pagination
echo "\n3. Menguji Filter Status, Search, & Pagination pada getAll():\n";
$openOps = OperasiModel::getAll('OPEN');
assertCondition("Filter status 'OPEN' berhasil", is_array($openOps));
$closedOps = OperasiModel::getAll('CLOSED');
assertCondition("Filter status 'CLOSED' berhasil", is_array($closedOps));
$pagedOps = OperasiModel::getAll(null, null, 1, 0);
assertCondition("Limit pagination berhasil mengembalikan maksimal 1 baris", count($pagedOps) <= 1);

// 4. Pengujian Keberadaan Indeks Komposit di MySQL Database
echo "\n4. Memeriksa Indeks Komposit pada Database MySQL:\n";
function checkIndexExists(PDO $db, string $table, string $indexName): bool {
    $stmt = $db->query("SHOW INDEX FROM {$table} WHERE Key_name = '{$indexName}'");
    return !empty($stmt->fetchAll());
}

assertCondition("Indeks komposit idx_korban_ops_status ada pada tabel Korban", checkIndexExists($db, 'Korban', 'idx_korban_ops_status'));
assertCondition("Indeks komposit idx_ops_status_selesai ada pada tabel Operasi", checkIndexExists($db, 'Operasi', 'idx_ops_status_selesai'));
assertCondition("Indeks komposit idx_renops_ops_hari ada pada tabel Renops", checkIndexExists($db, 'Renops', 'idx_renops_ops_hari'));
assertCondition("Indeks komposit idx_lap_ops_hari ada pada tabel LaporanHarian", checkIndexExists($db, 'LaporanHarian', 'idx_lap_ops_hari'));

// 5. Pengujian Pembersihan DDL Runtime pada Model
echo "\n5. Memeriksa Ketiadaan Panggilan DDL Runtime pada Model:\n";
$renopsContent = file_get_contents(__DIR__ . '/../models/RenopsModel.php');
$gisContent = file_get_contents(__DIR__ . '/../models/GisModel.php');

assertCondition("RenopsModel::getById() bebas dari self::ensureSchemaUpdated()", !str_contains($renopsContent, "getById(int \$id): ?array {\n        self::ensureSchemaUpdated();"));
assertCondition("RenopsModel::create() bebas dari self::ensureSchemaUpdated()", !str_contains($renopsContent, "create(array \$data): int {\n        self::ensureSchemaUpdated();"));
assertCondition("GisModel::getPoints() bebas dari self::ensureSchemaUpdated()", !str_contains($gisContent, "getPoints(?int \$idOperasi = null, bool \$includeGlobal = true): array {\n        self::ensureSchemaUpdated();"));
assertCondition("GisModel::savePoint() bebas dari self::ensureSchemaUpdated()", !str_contains($gisContent, "savePoint(array \$data): string {\n        self::ensureSchemaUpdated();"));
assertCondition("GisModel::savePolygon() bebas dari self::ensureSchemaUpdated()", !str_contains($gisContent, "savePolygon(array \$data): string {\n        self::ensureSchemaUpdated();"));
assertCondition("GisModel::getRoutes() bebas dari self::ensureSchemaUpdated()", !str_contains($gisContent, "getRoutes(?int \$idOperasi = null, bool \$includeGlobal = true): array {\n        self::ensureSchemaUpdated();"));

// 6. Pengujian File Migrasi & Runner
echo "\n6. Memeriksa Berkas Migrasi SQL & Runner migrate.php:\n";
$migrationFile = __DIR__ . '/../database/migrations/2026_10_18_add_composite_indexes.sql';
assertCondition("Berkas migrasi 2026_10_18_add_composite_indexes.sql tersedia", file_exists($migrationFile));
$migrateContent = file_get_contents(__DIR__ . '/../database/migrate.php');
assertCondition("database/migrate.php memuat validasi composite indexes", str_contains($migrateContent, 'Checking Composite Indexes'));

echo "\n--- HASIL: {$passedAssertions} / {$totalAssertions} Pengujian Berhasil Lulus ---\n";
if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
