<?php
/**
 * QR-SAR - Database Backup & Restoration Service
 * KPP Kendari - Basarnas
 * 
 * Provides decoupled, enterprise-grade utilities for:
 * 1. Database table record statistics
 * 2. JSON structured database backup and restoration
 * 3. SQL full schema & data dump generation
 * 4. Robust parsing and execution of multi-statement SQL scripts
 */

declare(strict_types=1);

class BackupService {
    /**
     * Whitelist of tables allowed to be backed up and restored.
     */
    public const MANAGED_TABLES = [
        'AppSettings', 'Pejabat', 'MasterAlut', 'MasterTim', 'SarUnit',
        'Operasi', 'Korban', 'DetailUnsur', 'DetailAlut', 'DetailTimBerangkat',
        'Renops', 'RenopsSRU', 'LaporanHarian', 'BraSar', 'BraSection',
        'GisPoint', 'GisPolygon', 'GisPolygonPoint', 'GisRoute', 'GisRoutePoint',
        'GisSearchPattern', 'GisSearchPatternWaypoint', 'GisCustomIcon',
        'Users', 'AuditLog'
    ];

    /**
     * Mapping for JSON backup key to physical database table name.
     */
    public const JSON_TABLE_MAP = [
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

    /**
     * Retrieve count statistics across all managed tables.
     *
     * @return array<string, int>
     */
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

    /**
     * Export complete system state as structured JSON array.
     *
     * @return array
     */
    public static function exportJsonBackup(): array {
        $db = getDB();
        $cleanSettings = class_exists('SettingsModel') ? SettingsModel::getSettings() : [];
        
        // Sensor sensitive security fields
        unset($cleanSettings['admin_password'], $cleanSettings['user_password']);
        if (!empty($cleanSettings['gemini_api_key'])) {
            $cleanSettings['gemini_api_key'] = '********';
        }

        $backup = [
            'version' => defined('APP_VERSION') ? APP_VERSION : '2.5.0',
            'appName' => 'SISTEM QR SAR - KPP KENDARI (PHP NATIVE)',
            'exported_at' => date('Y-m-d H:i:s'),
            'app_settings' => $cleanSettings,
            'master_alut' => class_exists('MasterModel') ? MasterModel::getAlut() : [],
            'master_tim' => class_exists('MasterModel') ? MasterModel::getTim() : [],
            'sar_units' => class_exists('MasterModel') ? MasterModel::getSarUnits() : [],
            'operasi' => $db->query("SELECT * FROM Operasi ORDER BY id_operasi ASC")->fetchAll(),
            'korban' => $db->query("SELECT * FROM Korban ORDER BY id_korban ASC")->fetchAll(),
            'detail_unsur' => $db->query("SELECT * FROM DetailUnsur ORDER BY id_detail_unsur ASC")->fetchAll(),
            'detail_alut' => $db->query("SELECT * FROM DetailAlut ORDER BY id_detail_alut ASC")->fetchAll(),
            'detail_tim_berangkat' => $db->query("SELECT * FROM DetailTimBerangkat ORDER BY id_detail_tim ASC")->fetchAll(),
            'renops' => $db->query("SELECT * FROM Renops ORDER BY id_renops ASC")->fetchAll(),
            'renops_sru' => $db->query("SELECT * FROM RenopsSRU ORDER BY id_sru ASC")->fetchAll(),
            'laporan_harian' => $db->query("SELECT * FROM LaporanHarian ORDER BY id_laporan ASC")->fetchAll(),
            'bra_sar' => class_exists('BraModel') ? BraModel::getAll() : [],
            'gis_points' => class_exists('GisModel') ? GisModel::getPoints() : [],
            'gis_polygons' => class_exists('GisModel') ? GisModel::getPolygons() : [],
            'gis_routes' => class_exists('GisModel') ? GisModel::getRoutes() : [],
            'gis_search_patterns' => class_exists('GisModel') ? GisModel::getSearchPatterns() : [],
            'users' => class_exists('UserModel') ? UserModel::getAll() : [],
            'audit_log' => class_exists('AuditHelper') ? AuditHelper::getLogs(200) : []
        ];

        if (class_exists('AuditHelper')) {
            AuditHelper::log('DATABASE_BACKUP', "Mengekspor cadangan basis data format JSON", 'Database', 'SYSTEM');
        }

        return $backup;
    }

    /**
     * Restore database records from structured JSON backup data.
     *
     * @param array $data
     * @return bool
     * @throws Throwable
     */
    public static function restoreFromJson(array $data): bool {
        $db = getDB();
        $dbData = $data['data'] ?? $data['database'] ?? $data['backup'] ?? $data;

        $db->beginTransaction();
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // 1. AppSettings (sanitized update)
            if (!empty($dbData['app_settings']) && is_array($dbData['app_settings']) && class_exists('SettingsModel')) {
                $cleanSettings = $dbData['app_settings'];
                unset($cleanSettings['admin_password'], $cleanSettings['user_password']);
                SettingsModel::updateSettings($cleanSettings);
            }

            // Helper to import array table safely
            $importTable = function(string $table, array $rows) use ($db) {
                if (empty($rows) || !in_array($table, self::MANAGED_TABLES, true)) return;
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

            foreach (self::JSON_TABLE_MAP as $key => $table) {
                if (!empty($dbData[$key]) && is_array($dbData[$key])) {
                    $importTable($table, $dbData[$key]);
                }
            }

            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            $db->commit();
            if (class_exists('AuditHelper')) {
                AuditHelper::log('DATABASE_RESTORE', "Memulihkan basis data dari berkas cadangan JSON", 'Database', 'SYSTEM');
            }
            return true;
        } catch (Throwable $e) {
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Generate complete MySQL dump string containing DROP, CREATE TABLE, and INSERT statements.
     *
     * @return string
     */
    public static function generateSQLDump(): string {
        $db = getDB();
        $date = date('Y-m-d H:i:s');

        $out = "-- ========================================================\n";
        $out .= "-- DUMP BASIS DATA QR SAR KANTOR PENCARIAN DAN PERTOLONGAN KENDARI\n";
        $out .= "-- Tanggal Ekspor: {$date}\n";
        $out .= "-- ========================================================\n\n";
        $out .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach (self::MANAGED_TABLES as $table) {
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
        if (class_exists('AuditHelper')) {
            AuditHelper::log('DATABASE_BACKUP', "Mengekspor cadangan basis data format SQL Dump", 'Database', 'SYSTEM');
        }
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

    /**
     * Restore database state by executing SQL dump statements.
     *
     * @param string $sql
     * @return bool
     * @throws Throwable
     */
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
            if (class_exists('AuditHelper')) {
                AuditHelper::log('DATABASE_RESTORE', "Memulihkan basis data dari berkas cadangan SQL Dump", 'Database', 'SYSTEM');
            }
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
