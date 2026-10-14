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

        $allowedTables = [
            'MasterAlut', 'MasterTim', 'SarUnit', 'Pejabat', 'Operasi',
            'Korban', 'DetailUnsur', 'DetailAlut', 'DetailTimBerangkat',
            'Renops', 'RenopsSRU', 'LaporanHarian', 'BraSar', 'BraSection',
            'GisPoint', 'GisPolygon', 'GisPolygonPoint', 'GisRoute', 'GisRoutePoint',
            'GisSearchPattern', 'GisSearchPatternWaypoint', 'GisCustomIcon',
            'Users', 'AuditLog'
        ];

        $db->beginTransaction();
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // 1. AppSettings (sanitized update)
            if (!empty($dbData['app_settings']) && is_array($dbData['app_settings'])) {
                $cleanSettings = $dbData['app_settings'];
                unset($cleanSettings['admin_password'], $cleanSettings['user_password']);
                self::updateSettings($cleanSettings);
            }

            // Helper to import array table safely
            $importTable = function(string $table, array $rows) use ($db, $allowedTables) {
                if (empty($rows) || !in_array($table, $allowedTables, true)) return;
                $db->exec("DELETE FROM `{$table}`");
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;
                    $cols = array_keys($row);
                    // Sanitasi nama kolom
                    $safeCols = [];
                    foreach ($cols as $c) {
                        if (preg_match('/^[a-zA-Z0-9_]+$/', (string)$c)) {
                            $safeCols[] = $c;
                        }
                    }
                    if (empty($safeCols)) continue;

                    $colList = implode('`, `', $safeCols);
                    $paramList = ':' . implode(', :', $safeCols);
                    $stmt = $db->prepare("INSERT INTO `{$table}` (`{$colList}`) VALUES ({$paramList})");
                    $binds = [];
                    foreach ($safeCols as $k) {
                        $v = $row[$k] ?? null;
                        $binds[":{$k}"] = is_array($v) ? json_encode($v) : $v;
                    }
                    $stmt->execute($binds);
                }
            };

            $tableMap = [
                'master_alut' => 'MasterAlut',
                'master_tim' => 'MasterTim',
                'sar_units' => 'SarUnit',
                'pejabat' => 'Pejabat',
                'operasi' => 'Operasi',
                'korban' => 'Korban',
                'detail_unsur' => 'DetailUnsur',
                'detail_alut' => 'DetailAlut',
                'detail_tim_berangkat' => 'DetailTimBerangkat',
                'renops' => 'Renops',
                'renops_sru' => 'RenopsSRU',
                'laporan_harian' => 'LaporanHarian',
                'bra_sar' => 'BraSar',
                'gis_points' => 'GisPoint',
                'gis_polygons' => 'GisPolygon',
                'gis_routes' => 'GisRoute',
                'gis_search_patterns' => 'GisSearchPattern',
                'users' => 'Users',
                'audit_log' => 'AuditLog'
            ];

            foreach ($tableMap as $key => $table) {
                if (!empty($dbData[$key]) && is_array($dbData[$key])) {
                    $importTable($table, $dbData[$key]);
                }
            }

            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            $db->commit();
            AuditHelper::log('DATABASE_RESTORE', "Memulihkan basis data dari berkas cadangan JSON", 'Database', 'SYSTEM');
            return true;
        } catch (Throwable $e) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
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
        $res = $stmt->execute($params);
        if ($res) {
            AuditHelper::log('SETTINGS_UPDATE', "Memperbarui konfigurasi sistem & profil satker", 'AppSettings', '1');
        }
        return $res;
    }

    public static function updateAdminProfile(array $data): bool {
        $db = getDB();
        $adminUser = !empty($data['username']) ? trim($data['username']) : 'admin';
        $stmt = $db->prepare("UPDATE AppSettings SET
            admin_username = :username,
            admin_nama = :nama,
            admin_nip = :nip,
            admin_pangkat = :pangkat,
            admin_jabatan = :jabatan,
            admin_email = :email,
            admin_no_hp = :no_hp
            WHERE id = 1");

        $res = $stmt->execute([
            ':username' => $adminUser,
            ':nama' => $data['nama'] ?? '',
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':email' => $data['email'] ?? '',
            ':no_hp' => $data['no_hp'] ?? ''
        ]);

        if ($res) {
            try {
                UserModel::ensureTableExists();
                $db->prepare("UPDATE `Users` SET 
                    username = :username, 
                    nama = :nama, 
                    nip = :nip, 
                    pangkat = :pangkat, 
                    jabatan = :jabatan, 
                    email = :email, 
                    no_hp = :no_hp, 
                    updated_at = :now 
                    WHERE id = 'USR-ADMIN' OR username = 'admin'")->execute([
                    ':username' => $adminUser,
                    ':nama' => $data['nama'] ?? '',
                    ':nip' => $data['nip'] ?? '',
                    ':pangkat' => $data['pangkat'] ?? '',
                    ':jabatan' => $data['jabatan'] ?? '',
                    ':email' => $data['email'] ?? '',
                    ':no_hp' => $data['no_hp'] ?? '',
                    ':now' => date('Y-m-d H:i:s')
                ]);
            } catch (Throwable $e) {}

            AuditHelper::log('PROFILE_UPDATE', "Memperbarui profil administrator (" . ($data['nama'] ?? $adminUser) . ")", 'Users', 'USR-ADMIN');
        }

        return $res;
    }

    public static function updateUserProfile(array $data): bool {
        $db = getDB();
        $userUser = !empty($data['username']) ? trim($data['username']) : 'user';
        $sql = "UPDATE AppSettings SET
            user_username = :username,
            user_nama = :nama,
            user_nip = :nip,
            user_pangkat = :pangkat,
            user_jabatan = :jabatan,
            user_email = :email,
            user_no_hp = :no_hp";

        $params = [
            ':username' => $userUser,
            ':nama' => $data['nama'] ?? '',
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':email' => $data['email'] ?? '',
            ':no_hp' => $data['no_hp'] ?? ''
        ];

        $hasPass = !empty($data['password']);
        if ($hasPass) {
            $sql .= ", user_password = :password";
            $params[':password'] = password_hash(trim($data['password']), PASSWORD_DEFAULT);
        }

        $sql .= " WHERE id = 1";
        $stmt = $db->prepare($sql);
        $res = $stmt->execute($params);

        if ($res) {
            try {
                UserModel::ensureTableExists();
                $uSql = "UPDATE `Users` SET 
                    username = :username, 
                    nama = :nama, 
                    nip = :nip, 
                    pangkat = :pangkat, 
                    jabatan = :jabatan, 
                    email = :email, 
                    no_hp = :no_hp";
                $uParams = [
                    ':username' => $userUser,
                    ':nama' => $data['nama'] ?? '',
                    ':nip' => $data['nip'] ?? '',
                    ':pangkat' => $data['pangkat'] ?? '',
                    ':jabatan' => $data['jabatan'] ?? '',
                    ':email' => $data['email'] ?? '',
                    ':no_hp' => $data['no_hp'] ?? ''
                ];
                if ($hasPass) {
                    $uSql .= ", password_hash = :password";
                    $uParams[':password'] = $params[':password'];
                }
                $uSql .= ", updated_at = :now WHERE id = 'USR-USER' OR username = 'user'";
                $uParams[':now'] = date('Y-m-d H:i:s');
                $db->prepare($uSql)->execute($uParams);
            } catch (Throwable $e) {}

            AuditHelper::log('PROFILE_UPDATE', "Memperbarui profil petugas siaga (" . ($data['nama'] ?? $userUser) . ")", 'Users', 'USR-USER');
        }

        return $res;
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
        $res = $stmt->execute([':p' => $defaultHash]);

        if ($res) {
            try {
                UserModel::ensureTableExists();
                $db->prepare("UPDATE `Users` SET 
                    username = 'user', 
                    password_hash = :p, 
                    nama = 'Petugas Siaga SAR', 
                    nip = '199501012020121001', 
                    pangkat = 'PENGATUR (II/c)', 
                    jabatan = 'Operator Komunikasi & Petugas Siaga SAR', 
                    email = 'operator.kendari@basarnas.go.id', 
                    no_hp = '0811-4091-115', 
                    updated_at = :now 
                    WHERE id = 'USR-USER' OR username = 'user'")->execute([
                    ':p' => $defaultHash, 
                    ':now' => date('Y-m-d H:i:s')
                ]);
            } catch (Throwable $e) {}

            AuditHelper::log('USER_RESET', "Mereset akun Petugas Siaga SAR ke pengaturan awal sistem", 'Users', 'USR-USER');
        }

        return $res;
    }

    public static function changePassword(string $role, string $oldPass, string $newPass): array {
        $db = getDB();
        $settings = self::getSettings();

        $roleUpper = strtoupper($role);
        $currPass = $roleUpper === 'ADMIN' ? ($settings['admin_password'] ?? 'Kendari304') : ($settings['user_password'] ?? 'Kendari304');

        if (!AuthHelper::verifyPassword($oldPass, (string)$currPass)) {
            return ['success' => false, 'error' => 'Kata sandi lama tidak cocok.'];
        }

        if (strlen($newPass) < 8) {
            return ['success' => false, 'error' => 'Kata sandi baru minimal 8 karakter demi standar keamanan akun.'];
        }

        $newHash = password_hash($newPass, PASSWORD_DEFAULT);

        if ($roleUpper === 'ADMIN') {
            $stmt = $db->prepare("UPDATE AppSettings SET admin_password = :p WHERE id = 1");
        } else {
            $stmt = $db->prepare("UPDATE AppSettings SET user_password = :p WHERE id = 1");
        }

        $res = $stmt->execute([':p' => $newHash]);

        if ($res) {
            try {
                UserModel::ensureTableExists();
                $usrId = ($roleUpper === 'ADMIN') ? 'USR-ADMIN' : 'USR-USER';
                $db->prepare("UPDATE `Users` SET password_hash = :p, updated_at = :now WHERE id = :id OR role = :role")->execute([
                    ':p' => $newHash,
                    ':now' => date('Y-m-d H:i:s'),
                    ':id' => $usrId,
                    ':role' => $roleUpper
                ]);
            } catch (Throwable $e) {}

            AuditHelper::log('PASSWORD_CHANGE', "Mengubah kata sandi untuk otoritas {$roleUpper}", 'Users', $roleUpper);
        }

        return ['success' => true, 'message' => 'Kata sandi berhasil diperbarui dengan aman.'];
    }

    public static function resetToDefaultPassword(): bool {
        $db = getDB();
        $defaultHash = password_hash('Kendari304', PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE AppSettings SET admin_password = :pa, user_password = :pu WHERE id = 1");
        $res = $stmt->execute([':pa' => $defaultHash, ':pu' => $defaultHash]);

        if ($res) {
            try {
                UserModel::ensureTableExists();
                $db->prepare("UPDATE `Users` SET password_hash = :p, updated_at = :now")->execute([
                    ':p' => $defaultHash,
                    ':now' => date('Y-m-d H:i:s')
                ]);
            } catch (Throwable $e) {}

            AuditHelper::log('PASSWORD_RESET', "Mereset semua kata sandi pengguna ke bawaan sistem (Kendari304)", 'Users', 'ALL');
        }

        return $res;
    }

    public static function exportJsonBackup(): array {
        $db = getDB();
        $cleanSettings = self::getSettings();
        // Sensor data sensitif kredensial dari file export cadangan
        unset($cleanSettings['admin_password'], $cleanSettings['user_password']);
        if (!empty($cleanSettings['gemini_api_key'])) {
            $cleanSettings['gemini_api_key'] = '********';
        }

        $backup = [
            'version' => APP_VERSION,
            'appName' => 'SISTEM QR SAR - KPP KENDARI (PHP NATIVE)',
            'exported_at' => date('Y-m-d H:i:s'),
            'app_settings' => $cleanSettings,
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
            'gis_search_patterns' => GisModel::getSearchPatterns(),
            'users' => class_exists('UserModel') ? UserModel::getAll() : [],
            'audit_log' => class_exists('AuditHelper') ? AuditHelper::getLogs(200) : []
        ];

        AuditHelper::log('DATABASE_BACKUP', "Mengekspor cadangan basis data format JSON", 'Database', 'SYSTEM');
        return $backup;
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
            'GisSearchPattern', 'GisSearchPatternWaypoint', 'GisCustomIcon',
            'Users', 'AuditLog'
        ];

        foreach ($tables as $table) {
            try {
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
            } catch (Throwable $e) {
                // Table might not exist yet, skip gracefully
            }
        }

        $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        AuditHelper::log('DATABASE_BACKUP', "Mengekspor cadangan basis data format SQL Dump", 'Database', 'SYSTEM');
        return $out;
    }

    /**
     * Parse and split SQL file content into individual executable statements.
     * Accurately handles semicolons inside quotes, backticks, and comments.
     *
     * @param string $sql
     * @return array<string>
     */
    public static function splitSqlStatements(string $sql): array {
        $statements = [];
        $len = strlen($sql);
        $buffer = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $inLineComment = false;
        $inBlockComment = false;

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];
            $nextChar = $i + 1 < $len ? $sql[$i + 1] : '';

            // Handle line comments: -- or #
            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick && !$inBlockComment) {
                if (($char === '-' && $nextChar === '-') || $char === '#') {
                    $inLineComment = true;
                }
            }

            if ($inLineComment) {
                if ($char === "\n") {
                    $inLineComment = false;
                }
                // Skip line comments
                continue;
            }

            // Handle block comments: /* ... */ (preserve MySQL conditional comments /*! ... */)
            if (!$inSingleQuote && !$inDoubleQuote && !$inBacktick && !$inLineComment) {
                if ($char === '/' && $nextChar === '*' && ($i + 2 >= $len || $sql[$i + 2] !== '!')) {
                    $inBlockComment = true;
                    $i++; // skip '*'
                    continue;
                }
            }

            if ($inBlockComment) {
                if ($char === '*' && $nextChar === '/') {
                    $inBlockComment = false;
                    $i++; // skip '/'
                }
                continue;
            }

            // Handle quotes & escaping
            if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
                $numBackslashes = 0;
                $k = $i - 1;
                while ($k >= 0 && $sql[$k] === '\\') {
                    $numBackslashes++;
                    $k--;
                }
                if ($numBackslashes % 2 === 0) {
                    $inSingleQuote = !$inSingleQuote;
                }
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick) {
                $numBackslashes = 0;
                $k = $i - 1;
                while ($k >= 0 && $sql[$k] === '\\') {
                    $numBackslashes++;
                    $k--;
                }
                if ($numBackslashes % 2 === 0) {
                    $inDoubleQuote = !$inDoubleQuote;
                }
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
            }

            // Detect statement terminator ';' outside quotes & comments
            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $stmt = trim($buffer);
                if ($stmt !== '') {
                    $statements[] = $stmt;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trailing = trim($buffer);
        if ($trailing !== '') {
            $statements[] = $trailing;
        }

        return $statements;
    }

    public static function restoreFromSQL(string $sql): bool {
        $db = getDB();

        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            $statements = self::splitSqlStatements($sql);

            foreach ($statements as $stmtText) {
                if ($stmtText === '' || $stmtText === ';') {
                    continue;
                }
                // Skip GTID / SET @@GLOBAL lines that require SUPER privilege in managed MySQL
                if (stripos($stmtText, 'SET @@GLOBAL.GTID_PURGED') !== false) {
                    continue;
                }
                $db->exec($stmtText);
            }

            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            AuditHelper::log('DATABASE_RESTORE', "Memulihkan basis data dari berkas cadangan SQL Dump", 'Database', 'SYSTEM');
            return true;
        } catch (Throwable $e) {
            try {
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (Throwable) {}
            error_log("[QR-SAR Restore Error] " . $e->getMessage());
            throw $e;
        }
    }
}
