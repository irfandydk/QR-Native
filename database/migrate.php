<?php
/**
 * QR-SAR - Database Schema Migration Script
 * Basarnas KPP Kendari
 * 
 * Usage: php database/migrate.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Akses Ditolak: Skrip migrasi pangkalan data hanya dapat dijalankan melalui antarmuka konsol/terminal (CLI).' . PHP_EOL);
}

require_once dirname(__DIR__) . '/config/app.php';

/**
 * Safely parse and execute a multi-statement SQL migration file.
 */
function executeSqlMigrationFile(PDO $db, string $filePath): void {
    $sqlContent = file_get_contents($filePath);
    if ($sqlContent === false || trim($sqlContent) === '') {
        return;
    }

    $statements = BackupService::splitSqlStatements($sqlContent);

    foreach ($statements as $stmt) {
        if ($stmt === '') continue;
        try {
            $db->exec($stmt);
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            // Ignore benign duplicate indexes, columns, or non-existent index drops
            if (
                str_contains($msg, '1061 Duplicate key name') ||
                str_contains($msg, '1060 Duplicate column name') ||
                str_contains($msg, "1091 Can't DROP")
            ) {
                continue;
            }
            throw $e;
        }
    }
}

echo "=== [QR-SAR] Database Migration Runner ===\n";

try {
    $db = Database::getConnection();
    echo "[OK] Connected to database.\n";

    // 0. Ensure Automated Migrations Tracker Table Exists
    echo "[RUN] Checking migrations table...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS `migrations` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `migration` VARCHAR(255) NOT NULL UNIQUE,
        `batch` INT NOT NULL DEFAULT 1,
        `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $appliedRows = $db->query("SELECT `migration` FROM `migrations`")->fetchAll(PDO::FETCH_COLUMN);
    $appliedSet = array_flip($appliedRows);

    $currentBatch = (int)$db->query("SELECT COALESCE(MAX(batch), 0) FROM `migrations`")->fetchColumn();
    $nextBatch = $currentBatch + 1;

    // Scan database/migrations/ for pending .sql files
    $migrationsDir = __DIR__ . '/migrations';
    $sqlFiles = glob($migrationsDir . '/*.sql') ?: [];
    sort($sqlFiles);

    $appliedCount = 0;
    foreach ($sqlFiles as $filePath) {
        $fileName = basename($filePath);
        if (isset($appliedSet[$fileName])) {
            continue; // Already tracked and applied
        }

        echo "[RUN] Applying SQL migration: {$fileName}...\n";
        executeSqlMigrationFile($db, $filePath);

        $stmtIns = $db->prepare("INSERT INTO `migrations` (`migration`, `batch`, `applied_at`) VALUES (:mig, :batch, NOW())");
        $stmtIns->execute([':mig' => $fileName, ':batch' => $nextBatch]);
        echo "[OK] Applied: {$fileName} (Batch {$nextBatch})\n";
        $appliedCount++;
    }

    if ($appliedCount === 0) {
        echo "[INFO] No pending SQL migration files found (schema up-to-date).\n";
    } else {
        echo "[OK] {$appliedCount} SQL migration(s) successfully executed.\n";
    }

    // 1. Run GisModel forced migration
    if (class_exists('GisModel')) {
        echo "[RUN] Checking GIS tables...\n";
        GisModel::ensureSchemaUpdated(true);
        echo "[OK] GIS schema validated.\n";
    }

    // 1b. Run OperasiModel forced migration
    if (class_exists('OperasiModel')) {
        echo "[RUN] Checking Operasi tables...\n";
        OperasiModel::ensureSchemaUpdated(true);
        echo "[OK] Operasi schema validated.\n";
    }

    // 1c. Run RenopsModel forced migration
    if (class_exists('RenopsModel')) {
        echo "[RUN] Checking Renops tables...\n";
        RenopsModel::ensureSchemaUpdated(true);
        echo "[OK] Renops schema validated.\n";
    }

    // 1c-2. Run DokumenPetaSnapshotModel forced migration
    if (class_exists('DokumenPetaSnapshotModel')) {
        echo "[RUN] Checking DokumenPetaSnapshot table...\n";
        DokumenPetaSnapshotModel::ensureSchemaUpdated(true);
        echo "[OK] DokumenPetaSnapshot schema validated.\n";
    }

    // 1d. Validate and Apply Composite Indexes for High Performance
    echo "[RUN] Checking Composite Indexes (Korban, Operasi, Renops, LaporanHarian)...\n";
    $compositeIndexes = [
        ['table' => 'Korban', 'name' => 'idx_korban_ops_status', 'sql' => 'ALTER TABLE Korban ADD INDEX idx_korban_ops_status (id_operasi, status_korban)'],
        ['table' => 'Operasi', 'name' => 'idx_ops_status_selesai', 'sql' => 'ALTER TABLE Operasi ADD INDEX idx_ops_status_selesai (status, waktu_selesai)'],
        ['table' => 'Renops', 'name' => 'idx_renops_ops_hari', 'sql' => 'ALTER TABLE Renops ADD INDEX idx_renops_ops_hari (id_operasi, hari_ke)'],
        ['table' => 'LaporanHarian', 'name' => 'idx_lap_ops_hari', 'sql' => 'ALTER TABLE LaporanHarian ADD INDEX idx_lap_ops_hari (id_operasi, hari_ke)']
    ];
    foreach ($compositeIndexes as $cIdx) {
        $check = $db->query("SHOW INDEX FROM {$cIdx['table']} WHERE Key_name = '{$cIdx['name']}'")->fetchAll();
        if (empty($check)) {
            $db->exec($cIdx['sql']);
            echo "[OK] Index {$cIdx['name']} created on {$cIdx['table']}.\n";
        }
    }
    echo "[OK] Composite indexes validated.\n";

    // 2. Run UserModel table migration
    if (class_exists('UserModel')) {
        echo "[RUN] Checking Users table...\n";
        UserModel::ensureTableExists(true);
        SettingsModel::getSettings(); // Instalasi baru: buat baris AppSettings default agar akun admin/user ikut tersinkron
        UserModel::syncFromAppSettings(true);
        echo "[OK] Users table validated.\n";
    }

    // 2b. Ensure AppSettings default passwords exist
    $checkSet = $db->query("SELECT admin_password, user_password FROM AppSettings WHERE id = 1 LIMIT 1")->fetch();
    if ($checkSet && (empty($checkSet['admin_password']) || empty($checkSet['user_password']))) {
        echo "[RUN] Seeding default passwords into AppSettings...\n";
        $defaultHash = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT);
        $db->prepare("UPDATE AppSettings SET admin_password = COALESCE(NULLIF(admin_password, ''), :pa), user_password = COALESCE(NULLIF(user_password, ''), :pu) WHERE id = 1")
           ->execute([':pa' => $defaultHash, ':pu' => $defaultHash]);
        echo "[OK] AppSettings default passwords secured.\n";
    }

    // 2b-2. Hash sisa sandi plaintext lama (login hanya menerima password_hash sejak audit keamanan)
    $plainUsers = $db->query("SELECT id, password_hash FROM `Users`")->fetchAll();
    $stmtHashUser = $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = :id");
    foreach ($plainUsers as $pu) {
        if ($pu['password_hash'] !== '' && empty(password_get_info($pu['password_hash'])['algo'])) {
            $stmtHashUser->execute([':p' => password_hash(trim($pu['password_hash']), PASSWORD_DEFAULT), ':id' => $pu['id']]);
            echo "[OK] Plaintext password hashed for user {$pu['id']}.\n";
        }
    }
    $plainSet = $db->query("SELECT admin_password, user_password FROM AppSettings WHERE id = 1 LIMIT 1")->fetch() ?: [];
    foreach (['admin_password', 'user_password'] as $col) {
        $val = (string)($plainSet[$col] ?? '');
        if ($val !== '' && empty(password_get_info($val)['algo'])) {
            $db->prepare("UPDATE AppSettings SET {$col} = :p WHERE id = 1")->execute([':p' => password_hash(trim($val), PASSWORD_DEFAULT)]);
            echo "[OK] Plaintext AppSettings.{$col} hashed.\n";
        }
    }

    // 2c. Ensure all 9 official Basarnas Sultra SarUnit bases exist with verified true coordinates
    echo "[RUN] Checking SarUnit bases (Kansar, Pos SAR, Unit Siaga SAR)...\n";
    $sarUnitsSeed = [
        [1, 'Kansar Kendari (Kantor Utama)', 'KANTOR_SAR', 'Kota Kendari / Konawe Selatan', -4.0378573160673, 122.4866527319, '04° 02\' 16.3" S - 122° 29\' 11.9" E', 'Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan', '(0401) 3122115 / Emergency 115', 1],
        [2, 'Pos SAR Kolaka', 'POS_SAR', 'Kabupaten Kolaka', -4.0654939493596, 121.60832650959, '04° 03\' 55.8" S - 121° 36\' 30.0" E', 'Kompleks Pelabuhan Ferry Kolaka, Jl. Dermaga Ferry Kolaka, Kel. Kolakaasi, Kec. Latambaga, Kab. Kolaka', '0811-4091-115', 0],
        [3, 'Pos SAR Baubau', 'POS_SAR', 'Kota Baubau & Buton', -5.5119777777778, 122.560300, '05° 30\' 43.1" S - 122° 33\' 37.1" E', 'Kawasan Pelabuhan Murhum / Jembatan Batu, Kel. Batulo, Kec. Wolio, Kota Baubau, Sulawesi Tenggara', '0811-4092-115', 0],
        [4, 'Pos SAR Wakatobi', 'POS_SAR', 'Kabupaten Wakatobi', -5.3539166666667, 123.551500, '05° 21\' 14.1" S - 123° 33\' 05.4" E', 'Kawasan Pelabuhan Mandati / Pangulubelo, Wangi-Wangi Selatan, Kab. Wakatobi, Sulawesi Tenggara', '0811-4093-115', 0],
        [5, 'Pos SAR Konawe Utara', 'POS_SAR', 'Kabupaten Konawe Utara', -3.5290805555556, 122.13168333333, '03° 31\' 44.7" S - 122° 07\' 54.1" E', 'Jl. Poros Trans Sulawesi Kendari - Morowali, Wanggudu / Kec. Asera, Kab. Konawe Utara, Sulawesi Tenggara', '0811-4094-115', 0],
        [6, 'Unit Siaga SAR Muna', 'UNIT_SIAGA', 'Kabupaten Muna', -4.8441666666667, 122.72583333333, '04° 50\' 39.0" S - 122° 43\' 33.0" E', 'Kawasan Pelabuhan Nusantara Raha / Jl. By Pass, Kota Raha, Kab. Muna, Sulawesi Tenggara', '0811-4095-115', 0],
        [7, 'Unit Siaga SAR Konawe', 'UNIT_SIAGA', 'Kabupaten Konawe', -3.905556, 122.047222, '03° 54\' 20.0" S - 122° 02\' 50.0" E', 'Jl. Poros Kendari - Kolaka, Kota Unaaha, Kab. Konawe, Sulawesi Tenggara', '0811-4096-115', 0],
        [8, 'Unit Siaga SAR Kolaka Utara', 'UNIT_SIAGA', 'Kabupaten Kolaka Utara', -3.425000, 120.897000, '03° 25\' 30.0" S - 120° 53\' 49.2" E', 'Kawasan Pelabuhan Penyeberangan Tobaku / Jl. Poros Lasusua, Kec. Lasusua, Kab. Kolaka Utara', '0811-4097-115', 0],
        [9, 'Dermaga SAR Kendari (KN SAR / RB)', 'KN_SAR', 'Kota Kendari (Bungkutoko)', -3.9774826095434, 122.59606942534, '03° 58\' 38.9" S - 122° 35\' 45.8" E', 'Kawasan Pelabuhan Bungkutoko (Pangkalan KN SAR Pacitan / RB 210 / RB 307), Kota Kendari', '(0401) 3122115', 0]
    ];
    $stmtUnit = $db->prepare("INSERT INTO SarUnit (id_unit, nama_unit, jenis_unit, wilayah, latitude, longitude, koordinat_dms, alamat, no_telp, is_default)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            nama_unit = VALUES(nama_unit),
            jenis_unit = VALUES(jenis_unit),
            wilayah = VALUES(wilayah),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            koordinat_dms = VALUES(koordinat_dms),
            alamat = VALUES(alamat),
            no_telp = VALUES(no_telp)");
    foreach ($sarUnitsSeed as $u) {
        $stmtUnit->execute($u);
    }
    echo "[OK] All 9 SarUnit bases verified.\n";

    // 2d. Ensure master GIS base points exist in GisPoint for tactical map plotting & routing with true coordinates and custom icon
    echo "[RUN] Checking GisPoint master bases (pt-kansar, POS SAR, etc.)...\n";
    $iconBasarnas = 'uploads/icons/icon_1792053884_34b3a7b0.png';
    $masterBasesGis = [
        ['pt-kansar', -4.0378573160673, 122.4866527319, 'Kantor SAR Kendari', 'custom_upload', $iconBasarnas, 'Kantor Pencarian dan Pertolongan Kelas A Kendari', 'Kantor SAR', 'A', null],
        ['pt-pos-kolaka', -4.0654939493596, 121.60832650959, 'POS SAR KOLAKA', 'custom_upload', $iconBasarnas, 'Pos SAR Kolaka', 'POS SAR', 'A', null],
        ['pt-pos-baubau', -5.5119777777778, 122.560300, 'POS SAR BAU BAU', 'custom_upload', $iconBasarnas, 'Pos SAR Baubau', 'POS SAR', 'A', null],
        ['pt-pos-wakatobi', -5.3539166666667, 123.551500, 'POS SAR WAKATOBI', 'custom_upload', $iconBasarnas, 'Pos SAR Wakatobi', 'POS SAR', 'A', null],
        ['pt-pos-konut', -3.5290805555556, 122.13168333333, 'POS SAR KONAWE UTARA', 'custom_upload', $iconBasarnas, 'Pos SAR Konawe Utara', 'POS SAR', 'A', null],
        ['pt-unit-muna', -4.8441666666667, 122.72583333333, 'UNIT SIAGA SAR MUNA', 'custom_upload', $iconBasarnas, 'Unit Siaga SAR Muna', 'Unit Siaga SAR', 'A', null],
        ['pt-unit-konawe', -3.905556, 122.047222, 'UNIT SIAGA SAR KONAWE', 'custom_upload', $iconBasarnas, 'Unit Siaga SAR Konawe', 'Unit Siaga SAR', 'A', null],
        ['pt-unit-kolut', -3.425000, 120.897000, 'UNIT SIAGA SAR KOLAKA UTARA', 'custom_upload', $iconBasarnas, 'Unit Siaga SAR Kolaka Utara', 'Unit Siaga SAR', 'A', null],
        ['pt-dermaga-kdr', -3.9774826095434, 122.59606942534, 'Dermaga Utama Basarnas Kawasan Timur', 'custom_upload', $iconBasarnas, 'Pangkalan Armada KN SAR Kendari (Bungkutoko)', 'Dermaga SAR', 'A', null]
    ];
    $stmtGis = $db->prepare("INSERT INTO GisPoint (id, lat, lng, label, pointType, customIconUrl, description, category, alphabetChar, id_operasi)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            lat = VALUES(lat),
            lng = VALUES(lng),
            label = VALUES(label),
            customIconUrl = VALUES(customIconUrl),
            description = VALUES(description),
            category = VALUES(category)");
    foreach ($masterBasesGis as $bg) {
        $stmtGis->execute($bg);
    }
    echo "[OK] All 9 GisPoint master bases verified with verified true coordinates.\n";

    // 2e. Clean up duplicate manual GisPoint entries and orphaned test artifacts
    echo "[RUN] Cleaning up duplicate manual points & test artifacts in GisPoint...\n";
    $db->exec("DELETE FROM GisPoint WHERE id IN (
        'pt-1792041945789', 'pt-1792046759003', 'pt-1792046854270',
        'pt-1792046969319', 'pt-1792047663139', 'pt-1792149545168'
    )");
    $db->exec("DELETE FROM GisRoutePoint WHERE routeId IN ('route-op-8', 'route-op-88888') OR routeId NOT IN (SELECT id FROM GisRoute)");
    $db->exec("DELETE FROM GisRoute WHERE id_operasi IN (8, 88888) OR (id_operasi IS NOT NULL AND id_operasi NOT IN (SELECT id_operasi FROM Operasi))");
    $db->exec("DELETE FROM GisPoint WHERE id IN ('pt-lkp-op-8', 'pt-lkp-op-88888') OR (id_operasi IS NOT NULL AND id_operasi NOT IN (SELECT id_operasi FROM Operasi))");
    echo "[OK] Obsolete duplicates and orphaned test points cleaned up.\n";

    // 3. Ensure DetailTimBerangkat is populated for any existing operations
    if (class_exists('OperasiModel')) {
        echo "[RUN] Checking DetailTimBerangkat for existing operations...\n";
        $opsList = $db->query("SELECT id_operasi, tim_berangkat FROM Operasi")->fetchAll();
        foreach ($opsList as $o) {
            $idOps = (int)$o['id_operasi'];
            $timStr = trim((string)($o['tim_berangkat'] ?? ''));
            if ($timStr === '') continue;

            $check = $db->prepare("SELECT id_detail_tim FROM DetailTimBerangkat WHERE id_operasi = :id LIMIT 1");
            $check->execute([':id' => $idOps]);
            if (!$check->fetchColumn()) {
                $stmtMt = $db->prepare("SELECT id_tim FROM MasterTim WHERE LOWER(nama_tim) = LOWER(:nama) LIMIT 1");
                $stmtMt->execute([':nama' => $timStr]);
                $idTim = (int)($stmtMt->fetchColumn() ?: 0);

                $ins = $db->prepare("INSERT INTO DetailTimBerangkat (id_operasi, id_tim, nama_tim) VALUES (:id_ops, :id_tim, :nama)");
                $ins->execute([':id_ops' => $idOps, ':id_tim' => $idTim, ':nama' => $timStr]);
            }
        }

        echo "[OK] DetailTimBerangkat validated.\n";
    }

    echo "\n=== All database migrations completed successfully! ===\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
