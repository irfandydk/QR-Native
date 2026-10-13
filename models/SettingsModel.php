<?php
/**
 * QR-SAR - Settings & Backup/Restore Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class SettingsModel {
    public static function get(): array {
        return self::getSettings();
    }

    public static function getSettings(): array {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM AppSettings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch();

        if (!$settings) {
            $db->exec("INSERT INTO AppSettings (id, satker_kode, satker_nama) VALUES (1, 'SAR-304', 'Kantor Pencarian dan Pertolongan Kelas A Kendari')");
            $stmt = $db->query("SELECT * FROM AppSettings WHERE id = 1 LIMIT 1");
            $settings = $stmt->fetch();
        }

        $settings['pejabat_list'] = MasterModel::getPejabat();
        return $settings;
    }

    public static function updateSatker(array $data): bool {
        return self::updateSettings($data);
    }

    public static function updatePassword(string $newPass): array {
        return self::changePassword('ADMIN', '', $newPass);
    }

    public static function savePejabat(array $data): string {
        return MasterModel::createPejabat($data);
    }

    public static function updatePejabat(string $id, array $data): bool {
        return MasterModel::updatePejabat($id, $data);
    }

    public static function deletePejabat(string $id): bool {
        return MasterModel::deletePejabat($id);
    }

    public static function getDbStats(): array {
        $db = getDB();
        $safeCount = function(string $table) use ($db): int {
            try {
                $stmt = $db->query("SELECT COUNT(*) FROM `{$table}`");
                return (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                return 0;
            }
        };

        return [
            'total_operasi' => $safeCount('Operasi'),
            'total_korban' => $safeCount('Korban'),
            'total_renops' => $safeCount('Renops'),
            'total_laporan_harian' => $safeCount('LaporanHarian'),
            'total_bra_sar' => $safeCount('BraSar'),
            'total_master_alut' => $safeCount('MasterAlut'),
            'total_master_tim' => $safeCount('MasterTim'),
            'total_sar_units' => $safeCount('SarUnit'),
            'total_pejabat' => $safeCount('Pejabat'),
            'total_gis_points' => $safeCount('GisPoint'),
            'total_gis_polygons' => $safeCount('GisPolygon'),
            'total_gis_routes' => $safeCount('GisRoute')
        ];
    }

    public static function restoreFromJson(array $data): bool {
        $db = getDB();
        $dbData = $data['data'] ?? $data['database'] ?? $data['backup'] ?? $data;

        // 1. AppSettings
        if (!empty($dbData['app_settings']) && is_array($dbData['app_settings'])) {
            self::updateSettings($dbData['app_settings']);
        }

        // Helper to import array table
        $importTable = function(string $table, array $rows) use ($db) {
            if (empty($rows)) return;
            try {
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                $db->exec("TRUNCATE TABLE `{$table}`");
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $cols = array_keys($row);
                    $colList = implode('`, `', $cols);
                    $paramList = ':' . implode(', :', $cols);
                    $stmt = $db->prepare("INSERT INTO `{$table}` (`{$colList}`) VALUES ({$paramList})");
                    $binds = [];
                    foreach ($row as $k => $v) {
                        $binds[":{$k}"] = is_array($v) ? json_encode($v) : $v;
                    }
                    $stmt->execute($binds);
                }
            } catch (Throwable $e) {
                // Ignore single table restore failure
            } finally {
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            }
        };

        if (!empty($dbData['master_alut']) && is_array($dbData['master_alut'])) {
            $importTable('MasterAlut', $dbData['master_alut']);
        }
        if (!empty($dbData['master_tim']) && is_array($dbData['master_tim'])) {
            $importTable('MasterTim', $dbData['master_tim']);
        }
        if (!empty($dbData['sar_units']) && is_array($dbData['sar_units'])) {
            $importTable('SarUnit', $dbData['sar_units']);
        }
        if (!empty($dbData['pejabat']) && is_array($dbData['pejabat'])) {
            $importTable('Pejabat', $dbData['pejabat']);
        }
        if (!empty($dbData['operasi']) && is_array($dbData['operasi'])) {
            $importTable('Operasi', $dbData['operasi']);
        }
        if (!empty($dbData['korban']) && is_array($dbData['korban'])) {
            $importTable('Korban', $dbData['korban']);
        }
        if (!empty($dbData['detail_unsur']) && is_array($dbData['detail_unsur'])) {
            $importTable('DetailUnsur', $dbData['detail_unsur']);
        }
        if (!empty($dbData['detail_alut']) && is_array($dbData['detail_alut'])) {
            $importTable('DetailAlut', $dbData['detail_alut']);
        }
        if (!empty($dbData['detail_tim_berangkat']) && is_array($dbData['detail_tim_berangkat'])) {
            $importTable('DetailTimBerangkat', $dbData['detail_tim_berangkat']);
        }
        if (!empty($dbData['renops']) && is_array($dbData['renops'])) {
            $importTable('Renops', $dbData['renops']);
        }
        if (!empty($dbData['renops_sru']) && is_array($dbData['renops_sru'])) {
            $importTable('RenopsSRU', $dbData['renops_sru']);
        }
        if (!empty($dbData['laporan_harian']) && is_array($dbData['laporan_harian'])) {
            $importTable('LaporanHarian', $dbData['laporan_harian']);
        }
        if (!empty($dbData['bra_sar']) && is_array($dbData['bra_sar'])) {
            $importTable('BraSar', $dbData['bra_sar']);
        }
        if (!empty($dbData['gis_points']) && is_array($dbData['gis_points'])) {
            $importTable('GisPoint', $dbData['gis_points']);
        }
        if (!empty($dbData['gis_polygons']) && is_array($dbData['gis_polygons'])) {
            $importTable('GisPolygon', $dbData['gis_polygons']);
        }
        if (!empty($dbData['gis_routes']) && is_array($dbData['gis_routes'])) {
            $importTable('GisRoute', $dbData['gis_routes']);
        }

        return true;
    }

    public static function updateSettings(array $data): bool {
        $db = getDB();
        $fields = [
            'satker_kode', 'satker_nama', 'alamat', 'no_telp', 'email',
            'call_sign', 'frekuensi_default', 'default_theme', 'logo_url', 'gemini_api_key'
        ];

        $sets = [];
        $params = [':id' => 1];

        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($sets)) return true;

        $sql = "UPDATE AppSettings SET " . implode(', ', $sets) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function updateAdminProfile(array $data): bool {
        $db = getDB();
        $stmt = $db->prepare("UPDATE AppSettings SET
            admin_username = :username,
            admin_nama = :nama,
            admin_nip = :nip,
            admin_pangkat = :pangkat,
            admin_jabatan = :jabatan,
            admin_email = :email,
            admin_no_hp = :no_hp
            WHERE id = 1");

        return $stmt->execute([
            ':username' => !empty($data['username']) ? trim($data['username']) : 'admin',
            ':nama' => $data['nama'] ?? '',
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':email' => $data['email'] ?? '',
            ':no_hp' => $data['no_hp'] ?? ''
        ]);
    }

    public static function updateUserProfile(array $data): bool {
        $db = getDB();
        $sql = "UPDATE AppSettings SET
            user_username = :username,
            user_nama = :nama,
            user_nip = :nip,
            user_pangkat = :pangkat,
            user_jabatan = :jabatan,
            user_email = :email,
            user_no_hp = :no_hp";

        $params = [
            ':username' => !empty($data['username']) ? trim($data['username']) : 'user',
            ':nama' => $data['nama'] ?? '',
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':email' => $data['email'] ?? '',
            ':no_hp' => $data['no_hp'] ?? ''
        ];

        if (!empty($data['password'])) {
            $sql .= ", user_password = :password";
            $params[':password'] = password_hash(trim($data['password']), PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = 1";
        $stmt = $db->prepare($sql);
        return $stmt->execute($params);
    }

    public static function resetUserToDefault(): bool {
        $db = getDB();
        $defaultHash = password_hash('Kendari304', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE AppSettings SET
            user_username = 'user',
            user_password = :p,
            user_nama = 'Petugas Siaga SAR',
            user_nip = '199501012020121001',
            user_pangkat = 'PENGATUR (II/c)',
            user_jabatan = 'Operator Komunikasi & Petugas Siaga SAR',
            user_email = 'operator.kendari@basarnas.go.id',
            user_no_hp = '0811-4091-115'
            WHERE id = 1");
        return $stmt->execute([':p' => $defaultHash]);
    }

    public static function changePassword(string $role, string $oldPass, string $newPass): array {
        $db = getDB();
        $settings = self::getSettings();

        $roleUpper = strtoupper($role);
        $currPass = $roleUpper === 'ADMIN' ? ($settings['admin_password'] ?? 'Kendari304') : ($settings['user_password'] ?? 'Kendari304');

        if (!AuthHelper::verifyPassword($oldPass, (string)$currPass)) {
            return ['success' => false, 'error' => 'Kata sandi lama tidak cocok.'];
        }

        if (strlen($newPass) < 4) {
            return ['success' => false, 'error' => 'Kata sandi baru minimal 4 karakter.'];
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);

        if ($roleUpper === 'ADMIN') {
            $stmt = $db->prepare("UPDATE AppSettings SET admin_password = :p WHERE id = 1");
        } else {
            $stmt = $db->prepare("UPDATE AppSettings SET user_password = :p WHERE id = 1");
        }

        $stmt->execute([':p' => $newHash]);
        return ['success' => true, 'message' => 'Kata sandi berhasil diperbarui dengan aman.'];
    }

    public static function resetToDefaultPassword(): bool {
        $db = getDB();
        $defaultHash = password_hash('Kendari304', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE AppSettings SET admin_password = :p, user_password = :p WHERE id = 1");
        return $stmt->execute([':p' => $defaultHash]);
    }

    public static function exportJsonBackup(): array {
        $db = getDB();
        return [
            'version' => APP_VERSION,
            'appName' => 'SISTEM QR SAR - KPP KENDARI (PHP NATIVE)',
            'exported_at' => date('Y-m-d H:i:s'),
            'app_settings' => self::getSettings(),
            'master_alut' => MasterModel::getAlut(),
            'master_tim' => MasterModel::getTim(),
            'sar_units' => MasterModel::getSarUnits(),
            'operasi' => $db->query("SELECT * FROM Operasi ORDER BY id_operasi ASC")->fetchAll(),
            'korban' => $db->query("SELECT * FROM Korban ORDER BY id_korban ASC")->fetchAll(),
            'detail_unsur' => $db->query("SELECT * FROM DetailUnsur ORDER BY id_detail_unsur ASC")->fetchAll(),
            'detail_alut' => $db->query("SELECT * FROM DetailAlut ORDER BY id_detail_alut ASC")->fetchAll(),
            'detail_tim_berangkat' => $db->query("SELECT * FROM DetailTimBerangkat ORDER BY id_detail_tim ASC")->fetchAll(),
            'renops' => $db->query("SELECT * FROM Renops ORDER BY id_renops ASC")->fetchAll(),
            'renops_sru' => $db->query("SELECT * FROM RenopsSRU ORDER BY id_sru ASC")->fetchAll(),
            'laporan_harian' => $db->query("SELECT * FROM LaporanHarian ORDER BY id_laporan ASC")->fetchAll(),
            'bra_sar' => BraModel::getAll(),
            'gis_points' => GisModel::getPoints(),
            'gis_polygons' => GisModel::getPolygons(),
            'gis_routes' => GisModel::getRoutes(),
            'gis_search_patterns' => GisModel::getSearchPatterns()
        ];
    }

    public static function generateSQLDump(): string {
        $db = getDB();
        $date = date('Y-m-d H:i:s');

        $out = "-- ========================================================\n";
        $out .= "-- DUMP BASIS DATA QR SAR KANTOR PENCARIAN DAN PERTOLONGAN KENDARI\n";
        $out .= "-- Tanggal Ekspor: {$date}\n";
        $out .= "-- ========================================================\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        $tables = [
            'AppSettings', 'Pejabat', 'MasterAlut', 'MasterTim', 'SarUnit',
            'Operasi', 'Korban', 'DetailUnsur', 'DetailAlut', 'DetailTimBerangkat',
            'Renops', 'RenopsSRU', 'LaporanHarian', 'BraSar', 'BraSection',
            'GisPoint', 'GisPolygon', 'GisPolygonPoint', 'GisRoute', 'GisRoutePoint',
            'GisSearchPattern', 'GisSearchPatternWaypoint', 'GisCustomIcon'
        ];

        foreach ($tables as $table) {
            $createRow = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
            if ($createRow && isset($createRow['Create Table'])) {
                $out .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $out .= $createRow['Create Table'] . ";\n\n";

                $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll();
                if (!empty($rows)) {
                    $cols = array_keys($rows[0]);
                    $colList = implode('`, `', $cols);

                    $insertLines = [];
                    foreach ($rows as $row) {
                        $valList = [];
                        foreach ($row as $v) {
                            if ($v === null) {
                                $valList[] = "NULL";
                            } else {
                                $valList[] = "'" . addslashes((string)$v) . "'";
                            }
                        }
                        $insertLines[] = "(" . implode(', ', $valList) . ")";
                    }

                    $out .= "INSERT INTO `{$table}` (`{$colList}`) VALUES\n" . implode(",\n", $insertLines) . ";\n\n";
                }
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $out;
    }

    public static function restoreFromSQL(string $sql): bool {
        $db = getDB();

        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // Split statements by semicolon while respecting line breaks
            $lines = explode("\n", $sql);
            $currentStatement = '';

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                    continue;
                }
                $currentStatement .= $line . "\n";
                if (str_ends_with($trimmed, ';')) {
                    $stmtText = trim($currentStatement);
                    if ($stmtText !== '') {
                        $db->exec($stmtText);
                    }
                    $currentStatement = '';
                }
            }

            if (trim($currentStatement) !== '') {
                $db->exec(trim($currentStatement));
            }

            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            if ($db->inTransaction()) {
                $db->commit();
            }
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            throw $e;
        }
    }
}
