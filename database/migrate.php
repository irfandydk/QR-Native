<?php
/**
 * QR-SAR - Database Schema Migration Script
 * Basarnas KPP Kendari
 * 
 * Usage: php database/migrate.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

echo "=== [QR-SAR] Database Migration Runner ===\n";

try {
    $db = Database::getConnection();
    echo "[OK] Connected to database.\n";

    // 1. Run GisModel forced migration
    if (class_exists('GisModel')) {
        echo "[RUN] Checking GIS tables...\n";
        GisModel::ensureSchemaUpdated(true);
        echo "[OK] GIS schema validated.\n";
    }

    // 2. Run UserModel table migration
    if (class_exists('UserModel')) {
        echo "[RUN] Checking Users table...\n";
        UserModel::ensureTableExists();
        UserModel::syncFromAppSettings();
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

    // 2c. Ensure all 9 official Basarnas Sultra SarUnit bases exist
    echo "[RUN] Checking SarUnit bases (Kansar, Pos SAR, Unit Siaga SAR)...\n";
    $sarUnitsSeed = [
        [1, 'Kansar Kendari (Kantor Utama)', 'KANTOR_SAR', 'Kota Kendari / Konawe Selatan', -4.084194, 122.428389, '04° 05\' 03" S - 122° 25\' 42" E', 'Jl. Poros Bandara Haluoleo No. 1, Desa Ambaipua, Kec. Ranomeeto, Kab. Konawe Selatan', '(0401) 3122115 / Emergency 115', 1],
        [2, 'Pos SAR Kolaka', 'POS_SAR', 'Kabupaten Kolaka', -4.053889, 121.604167, '04° 03\' 14" S - 121° 36\' 15" E', 'Kompleks Pelabuhan Ferry Kolaka, Jl. Dermaga Ferry Kolaka, Kel. Kolakaasi, Kec. Latambaga, Kab. Kolaka', '0811-4091-115', 0],
        [3, 'Pos SAR Baubau', 'POS_SAR', 'Kota Baubau & Buton', -5.463889, 122.604167, '05° 27\' 50" S - 122° 36\' 15" E', 'Kawasan Pelabuhan Murhum / Jembatan Batu, Kel. Batulo, Kec. Wolio, Kota Baubau, Sulawesi Tenggara', '0811-4092-115', 0],
        [4, 'Pos SAR Wakatobi', 'POS_SAR', 'Kabupaten Wakatobi', -5.326389, 123.588889, '05° 19\' 35" S - 123° 35\' 20" E', 'Kawasan Pelabuhan Mandati / Pangulubelo, Wangi-Wangi Selatan, Kab. Wakatobi, Sulawesi Tenggara', '0811-4093-115', 0],
        [5, 'Pos SAR Konawe Utara', 'POS_SAR', 'Kabupaten Konawe Utara', -3.513889, 122.180556, '03° 30\' 50" S - 122° 10\' 50" E', 'Jl. Poros Trans Sulawesi Kendari - Morowali, Wanggudu / Kec. Asera, Kab. Konawe Utara, Sulawesi Tenggara', '0811-4094-115', 0],
        [6, 'Unit Siaga SAR Muna', 'UNIT_SIAGA', 'Kabupaten Muna', -4.836111, 122.719444, '04° 50\' 10" S - 122° 43\' 10" E', 'Kawasan Pelabuhan Nusantara Raha / Jl. By Pass, Kota Raha, Kab. Muna, Sulawesi Tenggara', '0811-4095-115', 0],
        [7, 'Unit Siaga SAR Konawe', 'UNIT_SIAGA', 'Kabupaten Konawe', -3.905556, 122.047222, '03° 54\' 20" S - 122° 02\' 50" E', 'Jl. Poros Kendari - Kolaka, Kota Unaaha, Kab. Konawe, Sulawesi Tenggara', '0811-4096-115', 0],
        [8, 'Unit Siaga SAR Kolaka Utara', 'UNIT_SIAGA', 'Kabupaten Kolaka Utara', -3.425000, 120.897000, '03° 25\' 30" S - 120° 53\' 49" E', 'Kawasan Pelabuhan Penyeberangan Tobaku / Jl. Poros Lasusua, Kec. Lasusua, Kab. Kolaka Utara', '0811-4097-115', 0],
        [9, 'Dermaga SAR Kendari (KN SAR / RB)', 'KN_SAR', 'Kota Kendari (Bungkutoko)', -3.974167, 122.585000, '03° 58\' 27" S - 122° 35\' 06" E', 'Kawasan Pelabuhan Bungkutoko (Pangkalan KN SAR Pacitan / RB 210 / RB 307), Kota Kendari', '(0401) 3122115', 0]
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

    // 3. Sync AUTO_INCREMENT across all operational and child tables
    if (class_exists('OperasiModel')) {
        echo "[RUN] Syncing Operasi & Child Tables AUTO_INCREMENT...\n";
        
        // Ensure DetailTimBerangkat is populated for any existing operations
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

        OperasiModel::syncAutoIncrement();
        OperasiModel::syncChildAutoIncrements();
        echo "[OK] Operasi & Child AUTO_INCREMENT synchronized.\n";
    }

    if (class_exists('BraModel')) {
        echo "[RUN] Syncing BraSar & BraSection AUTO_INCREMENT...\n";
        BraModel::syncAutoIncrement();
        echo "[OK] BraSar & BraSection AUTO_INCREMENT synchronized.\n";
    }

    if (class_exists('RenopsModel')) {
        echo "[RUN] Syncing Renops & RenopsSRU AUTO_INCREMENT...\n";
        RenopsModel::syncAutoIncrement();
        echo "[OK] Renops & RenopsSRU AUTO_INCREMENT synchronized.\n";
    }

    if (class_exists('GisModel')) {
        echo "[RUN] Syncing GIS AUTO_INCREMENT...\n";
        GisModel::syncAutoIncrement();
        echo "[OK] GIS AUTO_INCREMENT synchronized.\n";
    }

    echo "\n=== All database migrations completed successfully! ===\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
