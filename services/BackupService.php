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
        'Renops', 'RenopsSRU', 'RenopsDokumen', 'RenopsFoto', 'LaporanHarian', 'BraSar', 'BraSection',
        'GisPoint', 'GisPolygon', 'GisPolygonPoint', 'GisRoute', 'GisRoutePoint',
        'GisSearchPattern', 'GisSearchPatternWaypoint', 'GisCustomIcon',
        'DokumenPetaSnapshot', 'DokumenToken',
        'Users', 'AuditLog'
    ];

    /**
     * Mapping for JSON backup key to physical database table name.
     */
    public const JSON_TABLE_MAP = [
        'pejabat' => 'Pejabat',
        'master_alut' => 'MasterAlut',
        'master_tim' => 'MasterTim',
        'sar_units' => 'SarUnit',
        'operasi' => 'Operasi',
        'korban' => 'Korban',
        'detail_unsur' => 'DetailUnsur',
        'detail_alut' => 'DetailAlut',
        'detail_tim_berangkat' => 'DetailTimBerangkat',
        'renops' => 'Renops',
        'renops_sru' => 'RenopsSRU',
        'renops_dokumen' => 'RenopsDokumen',
        'renops_foto' => 'RenopsFoto',
        'laporan_harian' => 'LaporanHarian',
        'bra_sar' => 'BraSar',
        'bra_sections' => 'BraSection',
        'gis_points' => 'GisPoint',
        'gis_polygons' => 'GisPolygon',
        'gis_polygon_points' => 'GisPolygonPoint',
        'gis_routes' => 'GisRoute',
        'gis_route_points' => 'GisRoutePoint',
        'gis_search_patterns' => 'GisSearchPattern',
        'gis_search_pattern_waypoints' => 'GisSearchPatternWaypoint',
        'gis_custom_icons' => 'GisCustomIcon',
        'dokumen_peta_snapshots' => 'DokumenPetaSnapshot',
        'dokumen_tokens' => 'DokumenToken',
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

        $safeQuery = function(string $table, string $orderCol) use ($db): array {
            try {
                $cols = self::getInsertableColumns($db, $table);
                if (empty($cols)) {
                    return [];
                }
                $colSelect = '`' . implode('`, `', $cols) . '`';
                return $db->query("SELECT {$colSelect} FROM `{$table}` ORDER BY `{$orderCol}` ASC")->fetchAll();
            } catch (Throwable) {
                return [];
            }
        };

        $backup = [
            'version' => defined('APP_VERSION') ? APP_VERSION : '2.5.0',
            'appName' => 'SISTEM QR SAR - KPP KENDARI (PHP NATIVE)',
            'exported_at' => date('Y-m-d H:i:s'),
            'app_settings' => $cleanSettings,
            'pejabat' => $safeQuery('Pejabat', 'id'),
            'master_alut' => $safeQuery('MasterAlut', 'id_alut'),
            'master_tim' => $safeQuery('MasterTim', 'id_tim'),
            'sar_units' => $safeQuery('SarUnit', 'id_unit'),
            'operasi' => $safeQuery('Operasi', 'id_operasi'),
            'korban' => $safeQuery('Korban', 'id_korban'),
            'detail_unsur' => $safeQuery('DetailUnsur', 'id_detail_unsur'),
            'detail_alut' => $safeQuery('DetailAlut', 'id_detail_alut'),
            'detail_tim_berangkat' => $safeQuery('DetailTimBerangkat', 'id_detail_tim'),
            'renops' => $safeQuery('Renops', 'id_renops'),
            'renops_sru' => $safeQuery('RenopsSRU', 'id_sru'),
            'renops_dokumen' => $safeQuery('RenopsDokumen', 'id_dokumen'),
            'renops_foto' => $safeQuery('RenopsFoto', 'id_foto'),
            'laporan_harian' => $safeQuery('LaporanHarian', 'id_laporan'),
            // Query raw BraSar directly to prevent virtual 'sections' & 'total_sections' array pollution
            'bra_sar' => $safeQuery('BraSar', 'id_bra'),
            'bra_sections' => $safeQuery('BraSection', 'id'),
            // Query raw GIS tables directly to prevent virtual 'points' / 'waypoints' array pollution
            'gis_points' => $safeQuery('GisPoint', 'id'),
            'gis_polygons' => $safeQuery('GisPolygon', 'id'),
            'gis_polygon_points' => $safeQuery('GisPolygonPoint', 'id'),
            'gis_routes' => $safeQuery('GisRoute', 'id'),
            'gis_route_points' => $safeQuery('GisRoutePoint', 'id'),
            'gis_search_patterns' => $safeQuery('GisSearchPattern', 'id'),
            'gis_search_pattern_waypoints' => $safeQuery('GisSearchPatternWaypoint', 'id'),
            'gis_custom_icons' => $safeQuery('GisCustomIcon', 'id'),
            'dokumen_peta_snapshots' => $safeQuery('DokumenPetaSnapshot', 'id_snapshot'),
            'dokumen_tokens' => $safeQuery('DokumenToken', 'issued_at'),
            // Export users WITH password_hash so credentials survive restore
            'users' => $safeQuery('Users', 'id'),
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
        self::snapshotBeforeRestore();
        $db = getDB();
        $dbData = $data['data'] ?? $data['database'] ?? $data['backup'] ?? $data;

        $db->beginTransaction();
        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            // 1. AppSettings (sanitized update)
            if (!empty($dbData['app_settings']) && is_array($dbData['app_settings']) && class_exists('SettingsModel')) {
                $cleanSettings = $dbData['app_settings'];
                unset($cleanSettings['admin_password'], $cleanSettings['user_password']);
                if (($cleanSettings['gemini_api_key'] ?? '') === '********') {
                    unset($cleanSettings['gemini_api_key']);
                }
                SettingsModel::updateSettings($cleanSettings);
            }

            // Cache current users' password hashes in case imported JSON lacked password_hash
            $existingUsers = [];
            try {
                $uStmt = $db->query("SELECT id, username, password_hash FROM `Users`");
                while ($uRow = $uStmt->fetch()) {
                    $existingUsers[$uRow['username']] = $uRow;
                    $existingUsers[$uRow['id']] = $uRow;
                }
            } catch (Throwable) {}

            // Helper to import array table safely
            $importTable = function(string $table, array $rows) use ($db, $existingUsers) {
                if (empty($rows) || !in_array($table, self::MANAGED_TABLES, true)) return;

                // Discover existing physical non-generated columns to filter out virtual & STORED GENERATED fields
                $realCols = self::getInsertableColumns($db, $table);
                if (empty($realCols)) {
                    return; // Table might not exist yet
                }
                $allowedColsMap = array_flip($realCols);

                $db->exec("DELETE FROM `{$table}`");
                foreach ($rows as $row) {
                    if (!is_array($row)) continue;

                    // If restoring Users table and row has empty password_hash, preserve existing password
                    if ($table === 'Users') {
                        if (empty($row['password_hash'])) {
                            $uKey = $row['username'] ?? ($row['id'] ?? '');
                            if (isset($existingUsers[$uKey]) && !empty($existingUsers[$uKey]['password_hash'])) {
                                $row['password_hash'] = $existingUsers[$uKey]['password_hash'];
                            } else {
                                $row['password_hash'] = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT);
                            }
                        }
                    }

                    $cols = array_keys($row);
                    $safeCols = [];
                    foreach ($cols as $c) {
                        $cStr = (string)$c;
                        // Column must match valid regex AND exist physically in MySQL table schema
                        if (preg_match('/^[a-zA-Z0-9_]+$/', $cStr) && isset($allowedColsMap[$cStr])) {
                            $safeCols[] = $cStr;
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
            // Cadangan lama mungkin tanpa kolom terbaru: pulihkan skema (DDL di luar transaksi).
            GisModel::ensureSchemaUpdated(true);
            OperasiModel::ensureSchemaUpdated(true);
            // Cadangan sebelum fitur kategori alut: isi ulang master & item laporan.
            AlutKategori::pulihkan($db, AlutKategori::cadanganTanpaKategori($data));
            if (class_exists('SettingsModel')) {
                SettingsModel::clearCache();
            }
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
     * Simpan SQL dump kondisi terkini ke storage/backups sebelum restore.
     * Restore dibatalkan (exception) bila snapshot gagal ditulis, agar data lama tidak hilang tanpa cadangan.
     *
     * @return string Path berkas snapshot
     */
    public static function snapshotBeforeRestore(): string {
        $dir = STORAGE_DIR . '/backups';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            throw new RuntimeException('Restore dibatalkan: folder storage/backups tidak dapat dibuat untuk cadangan otomatis.');
        }
        $path = $dir . '/pre-restore-' . date('Ymd-His') . '.sql';
        $fp = @fopen($path, 'wb');
        if (!$fp) {
            throw new RuntimeException('Restore dibatalkan: cadangan otomatis sebelum restore gagal ditulis ke storage/backups.');
        }
        self::writeSQLDump($fp);
        fclose($fp);
        return $path;
    }

    /**
     * Tulis seluruh SQL dump langsung ke stream/resource (misal berkas atau php://temp).
     * Mencegah pemborosan memori (OOM) dengan membaca baris secara bertahap (streaming/chunking)
     * dan menggunakan PDO::quote() untuk mencegah malformasi sintaks SQL pada karakter khusus.
     *
     * @param resource $resource
     */
    public static function writeSQLDump($resource): void {
        $db = getDB();
        $date = date('Y-m-d H:i:s');

        fwrite($resource, "-- ========================================================\n");
        fwrite($resource, "-- DUMP BASIS DATA QR SAR KANTOR PENCARIAN DAN PERTOLONGAN KENDARI\n");
        fwrite($resource, "-- Tanggal Ekspor: {$date}\n");
        fwrite($resource, "-- ========================================================\n\n");
        fwrite($resource, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        foreach (self::MANAGED_TABLES as $table) {
            try {
                $createRow = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
                if ($createRow && isset($createRow['Create Table'])) {
                    fwrite($resource, "DROP TABLE IF EXISTS `{$table}`;\n");
                    fwrite($resource, $createRow['Create Table'] . ";\n\n");

                    $insertCols = self::getInsertableColumns($db, $table);
                    if (empty($insertCols)) {
                        continue;
                    }
                    $colList = implode('`, `', $insertCols);
                    $stmt = $db->query("SELECT `{$colList}` FROM `{$table}`");
                    $batchSize = 100;
                    $batch = [];

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $valList = [];
                        foreach ($row as $v) {
                            if ($v === null) {
                                $valList[] = "NULL";
                            } else {
                                $valList[] = $db->quote((string)$v);
                            }
                        }
                        $batch[] = "(" . implode(', ', $valList) . ")";

                        if (count($batch) >= $batchSize) {
                            fwrite($resource, "INSERT INTO `{$table}` (`{$colList}`) VALUES\n" . implode(",\n", $batch) . ";\n\n");
                            $batch = [];
                        }
                    }

                    if (!empty($batch)) {
                        fwrite($resource, "INSERT INTO `{$table}` (`{$colList}`) VALUES\n" . implode(",\n", $batch) . ";\n\n");
                    }
                }
            } catch (Throwable $e) {
                // Table might not exist yet, skip gracefully
            }
        }

        fwrite($resource, "SET FOREIGN_KEY_CHECKS = 1;\n");
    }

    /**
     * Generate complete MySQL dump string containing DROP, CREATE TABLE, and INSERT statements.
     * Menggunakan memory-backed stream dengan fallback disk temporary jika ukuran melebihi limit.
     *
     * @return string
     */
    public static function generateSQLDump(): string {
        $stream = fopen('php://temp', 'r+');
        self::writeSQLDump($stream);
        rewind($stream);
        $out = (string)stream_get_contents($stream);
        fclose($stream);

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
     * Ambil daftar kolom fisik tabel yang dapat di-INSERT (mengecualikan STORED/VIRTUAL GENERATED columns).
     *
     * @return array<string>
     */
    private static function getInsertableColumns(PDO $db, string $table): array {
        try {
            $cols = [];
            $rows = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $field = (string)($r['Field'] ?? '');
                $extra = (string)($r['Extra'] ?? '');
                if ($field !== '' && stripos($extra, 'GENERATED') === false) {
                    $cols[] = $field;
                }
            }
            return $cols;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Validasi pernyataan SQL pemulihan terhadap daftar tabel terkelola (MANAGED_TABLES)
     * sekaligus menormalkan penulisan besar/kecil nama tabel (mis. dump Windows lowercase -> Linux PascalCase).
     * Mengembalikan null bila pernyataan aman dilewati, atau melempar InvalidArgumentException bila dilarang.
     */
    private static function normalizeAndValidateSqlStatement(string $stmt): ?string {
        $trimmed = trim($stmt);
        if ($trimmed === '' || $trimmed === ';') {
            return null;
        }
        if (stripos($trimmed, 'SET @@GLOBAL.GTID_PURGED') !== false) {
            return null;
        }
        if (preg_match('/^(LOCK\s+TABLES\b|UNLOCK\s+TABLES\b|START\s+TRANSACTION\b|BEGIN\b|COMMIT\b)/i', $trimmed)) {
            return null;
        }

        // Tolak konstruksi berbahaya secara eksplisit (termasuk di dalam sub-ekspresi)
        if (preg_match('/\b(INTO\s+(?:OUTFILE|DUMPFILE)|LOAD\s+DATA|LOAD_FILE\s*\(|GRANT\b|REVOKE\b|CREATE\s+USER\b|ALTER\s+USER\b|DROP\s+DATABASE\b|CREATE\s+DATABASE\b|CREATE\s+TRIGGER\b|CREATE\s+PROCEDURE\b|CREATE\s+FUNCTION\b|SET\s+(?:GLOBAL|PERSIST)\b)/i', $trimmed)) {
            throw new InvalidArgumentException('Pernyataan SQL berbahaya atau tidak diizinkan terdeteksi dalam berkas cadangan.');
        }

        static $tableMap = null;
        if ($tableMap === null) {
            $tableMap = [];
            foreach (self::MANAGED_TABLES as $t) {
                $tableMap[strtolower($t)] = $t;
            }
        }

        // Tangani komentar kondisional MySQL: /*!40101 SET ... */ atau /*!40000 ALTER TABLE `x` DISABLE KEYS */
        if (preg_match('#^/\*!(\d*)\s*(.*?)\s*\*/$#s', $trimmed, $cm)) {
            $ver = $cm[1];
            $inner = trim($cm[2]);
            if ($inner === '') {
                return null;
            }
            $validatedInner = self::normalizeAndValidateSqlStatement($inner);
            if ($validatedInner === null) {
                return null;
            }
            return "/*!{$ver} {$validatedInner} */";
        }

        // Izinkan pengaturan sesi standar dump MySQL
        if (preg_match('/^SET\s+(?:SESSION\s+)?(?:FOREIGN_KEY_CHECKS|NAMES|SQL_MODE|TIME_ZONE|UNIQUE_CHECKS|AUTOCOMMIT|CHARACTER_SET_CLIENT|CHARACTER_SET_RESULTS|COLLATION_CONNECTION|@[A-Za-z0-9_]+)\b/i', $trimmed)) {
            return $trimmed;
        }

        // Izinkan DDL/DML hanya pada tabel dalam MANAGED_TABLES dan normalkan nama tabel ke PascalCase kanonik
        $patterns = [
            '/^(DROP\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+EXISTS\s+)?)`?([A-Za-z0-9_]+)`?(.*)$/is',
            '/^(CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?)`?([A-Za-z0-9_]+)`?(.*)$/is',
            '/^(INSERT\s+(?:IGNORE\s+)?INTO\s+)`?([A-Za-z0-9_]+)`?(.*)$/is',
            '/^(REPLACE\s+INTO\s+)`?([A-Za-z0-9_]+)`?(.*)$/is',
            '/^(DELETE\s+FROM\s+)`?([A-Za-z0-9_]+)`?(.*)$/is',
            '/^(TRUNCATE\s+(?:TABLE\s+)?)`?([A-Za-z0-9_]+)`?(.*)$/is',
            '/^(ALTER\s+TABLE\s+)`?([A-Za-z0-9_]+)`?(.*)$/is',
        ];

        foreach ($patterns as $pat) {
            if (preg_match($pat, $trimmed, $m)) {
                $prefix = $m[1];
                $rawTable = $m[2];
                $rest = $m[3];
                $lower = strtolower($rawTable);
                if (!isset($tableMap[$lower])) {
                    throw new InvalidArgumentException("Pemulihan ditolak: tabel '{$rawTable}' bukan bagian dari skema terkelola QR-SAR.");
                }
                $canonical = $tableMap[$lower];
                if (stripos($prefix, 'CREATE') === 0 || stripos($prefix, 'ALTER') === 0) {
                    $rest = (string)preg_replace_callback(
                        '/\b(REFERENCES\s+)`?([A-Za-z0-9_]+)`?/i',
                        static function (array $rm) use ($tableMap): string {
                            $refLower = strtolower($rm[2]);
                            $refName = $tableMap[$refLower] ?? $rm[2];
                            return $rm[1] . '`' . $refName . '`';
                        },
                        $rest
                    );
                }
                return $prefix . '`' . $canonical . '`' . $rest;
            }
        }

        throw new InvalidArgumentException('Pemulihan ditolak: ditemukan pernyataan SQL di luar format cadangan resmi QR-SAR.');
    }

    /**
     * Restore database state by executing SQL dump statements.
     *
     * @param string $sql
     * @return bool
     * @throws Throwable
     */
    public static function restoreFromSQL(string $sql): bool {
        $rawStatements = self::splitSqlStatements($sql);
        $validatedStatements = [];
        foreach ($rawStatements as $stmtText) {
            $norm = self::normalizeAndValidateSqlStatement($stmtText);
            if ($norm !== null) {
                $validatedStatements[] = $norm;
            }
        }
        if (empty($validatedStatements)) {
            throw new InvalidArgumentException('Berkas SQL tidak memuat pernyataan pemulihan tabel yang valid.');
        }

        $snapshotPath = self::snapshotBeforeRestore();
        $db = getDB();
        $ddlStarted = false;

        try {
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($validatedStatements as $stmtText) {
                $ddlStarted = true;
                $db->exec($stmtText);
            }

            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            // Dump lama menghapus kolom terbaru (DROP/CREATE TABLE); migrations tetap mencatatnya sudah jalan.
            GisModel::ensureSchemaUpdated(true);
            OperasiModel::ensureSchemaUpdated(true);
            AlutKategori::pulihkan($db);
            if (class_exists('SettingsModel')) {
                SettingsModel::clearCache();
            }
            if (class_exists('AuditHelper')) {
                AuditHelper::log('DATABASE_RESTORE', "Memulihkan basis data dari berkas cadangan SQL Dump", 'Database', 'SYSTEM');
            }
            return true;
        } catch (Throwable $e) {
            if ($ddlStarted && is_file($snapshotPath)) {
                try {
                    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                    $rollbackSql = (string)@file_get_contents($snapshotPath);
                    foreach (self::splitSqlStatements($rollbackSql) as $rbStmt) {
                        $rbNorm = self::normalizeAndValidateSqlStatement($rbStmt);
                        if ($rbNorm !== null) {
                            $db->exec($rbNorm);
                        }
                    }
                } catch (Throwable $rbErr) {
                    error_log("[QR-SAR Restore Rollback Error] " . $rbErr->getMessage());
                }
            }
            try {
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (Throwable) {}
            if (class_exists('SettingsModel')) {
                SettingsModel::clearCache();
            }
            error_log("[QR-SAR Restore Error] " . $e->getMessage());
            throw $e;
        }
    }
}
