<?php
/**
 * QR-SAR - Database Backup & Restoration Service
 * KPP Kendari - Basarnas
 * 
 * Provides decoupled, enterprise-grade utilities for:
 * 1. Database table record statistics
 * 2. SQL full schema & data dump generation
 * 3. Robust parsing and execution of multi-statement SQL scripts
 */

declare(strict_types=1);

class BackupService {
    /**
     * Whitelist of tables allowed to be backed up and restored.
     */
    public const MANAGED_TABLES = [
        'AppSettings', 'Pejabat', 'MasterAlut', 'MasterTim', 'SarUnit',
        'Operasi', 'Korban', 'DetailUnsur', 'DetailAlut', 'DetailTimBerangkat',
        'Renops', 'RenopsSRU', 'RenopsDokumen', 'RenopsFoto', 'OperasiDokumentasi', 'LaporanHarian', 'BraSar', 'BraSection',
        'GisPoint', 'GisPolygon', 'GisPolygonPoint', 'GisRoute', 'GisRoutePoint',
        'GisSearchPattern', 'GisSearchPatternWaypoint', 'GisCustomIcon',
        'DokumenPetaSnapshot', 'DokumenToken',
        'Users', 'AuditLog'
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

        // Retensi maksimal 5 berkas snapshot pre-restore terbaru
        $snaps = glob($dir . '/pre-restore-*.sql') ?: [];
        if (count($snaps) > 5) {
            sort($snaps);
            foreach (array_slice($snaps, 0, count($snaps) - 5) as $oldSnap) {
                @unlink($oldSnap);
            }
        }

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
                if (str_starts_with(ltrim($rest), '.')) {
                    throw new InvalidArgumentException("Pemulihan ditolak: referensi lintas database tidak diizinkan.");
                }
                if ((stripos($prefix, 'DROP') === 0 || stripos($prefix, 'TRUNCATE') === 0) && trim($rest) !== '') {
                    throw new InvalidArgumentException("Pemulihan ditolak: pernyataan DROP/TRUNCATE tidak boleh memuat argumen atau tabel tambahan.");
                }
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

        $lockDir = STORAGE_DIR . '/backups';
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }
        $lockFp = @fopen($lockDir . '/restore.lock', 'cb');
        if ($lockFp === false || !@flock($lockFp, LOCK_EX | LOCK_NB)) {
            if (is_resource($lockFp)) {
                @fclose($lockFp);
            }
            throw new RuntimeException('Proses pemulihan basis data lain sedang berlangsung. Silakan tunggu beberapa saat.');
        }

        $snapshotPath = '';
        $db = getDB();
        $ddlStarted = false;

        try {
            $snapshotPath = self::snapshotBeforeRestore();
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");

            foreach ($validatedStatements as $stmtText) {
                $ddlStarted = true;
                $db->exec($stmtText);
            }

            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            // Dump lama menghapus kolom terbaru (DROP/CREATE TABLE); migrations tetap mencatatnya sudah jalan.
            GisModel::ensureSchemaUpdated(true);
            OperasiModel::ensureSchemaUpdated(true);
            if (class_exists('LaporanModel')) {
                LaporanModel::ensureSchemaUpdated(true);
            }
            if (class_exists('RenopsModel')) {
                RenopsModel::ensureSchemaUpdated(true);
            }
            if (class_exists('DokumenPetaSnapshotModel')) {
                DokumenPetaSnapshotModel::ensureSchemaUpdated(true);
            }
            if (class_exists('OperasiDokumentasiModel')) {
                OperasiDokumentasiModel::ensureSchemaUpdated(true);
            }
            AlutKategori::pulihkan($db);
            if (class_exists('SettingsModel')) {
                SettingsModel::clearCache();
            }
            if (class_exists('AuditHelper')) {
                AuditHelper::ensureTableExists(true);
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
        } finally {
            if (isset($lockFp) && is_resource($lockFp)) {
                @flock($lockFp, LOCK_UN);
                @fclose($lockFp);
            }
        }
    }
}
