<?php
/**
 * QR-SAR - Database Configuration & Connection (PDO)
 * Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas
 */

declare(strict_types=1);

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: 'localhost';
            $port = getenv('DB_PORT') ?: '3306';
            $dbname = getenv('DB_DATABASE') ?: 'REDACTED_qrsar';
            $username = getenv('DB_USERNAME') ?: 'REDACTED_root';
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'REDACTED';

            // Check if .env file exists and parse it if not already in getenv
            $envFile = dirname(__DIR__) . '/.env';
            if (file_exists($envFile)) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
                    [$key, $val] = explode('=', $line, 2);
                    $key = trim($key);
                    $val = trim($val, " \t\n\r\0\x0B\"'");
                    if ($key === 'DB_HOST') $host = $val;
                    if ($key === 'DB_PORT') $port = $val;
                    if ($key === 'DB_DATABASE' || $key === 'DB_NAME') $dbname = $val;
                    if ($key === 'DB_USERNAME' || $key === 'DB_USER') $username = $val;
                    if ($key === 'DB_PASSWORD' || $key === 'DB_PASS') $password = $val;
                }
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $bufferedAttr = defined('\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY')
                ? \Pdo\Mysql::ATTR_USE_BUFFERED_QUERY
                : (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY') ? PDO::MYSQL_ATTR_USE_BUFFERED_QUERY : 1000);
            $options[$bufferedAttr] = true;

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
                self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (PDOException $e) {
                // Smart fallback between 'localhost' (unix socket) and '127.0.0.1' (TCP port 3306)
                $alternateHost = ($host === 'localhost') ? '127.0.0.1' : (($host === '127.0.0.1') ? 'localhost' : null);
                if ($alternateHost !== null) {
                    try {
                        $altDsn = "mysql:host={$alternateHost};port={$port};dbname={$dbname};charset=utf8mb4";
                        self::$instance = new PDO($altDsn, $username, $password, $options);
                        self::$instance->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                        return self::$instance;
                    } catch (PDOException) {
                        // ignore and fall through to original error handler
                    }
                }

                // Attempt local development fallback if database does not exist or user fails
                if ($dbname === 'REDACTED_qrsar' || $dbname === 'qrsar') {
                    foreach (['qrsar', 'REDACTED_qrsar'] as $targetDb) {
                        try {
                            $dsnFallback = "mysql:host={$host};port={$port};dbname={$targetDb};charset=utf8mb4";
                            self::$instance = new PDO($dsnFallback, $username, $password, $options);
                            return self::$instance;
                        } catch (PDOException) {}
                        try {
                            $dsnFallback = "mysql:host=127.0.0.1;port={$port};dbname={$targetDb};charset=utf8mb4";
                            self::$instance = new PDO($dsnFallback, 'root', '', $options);
                            return self::$instance;
                        } catch (PDOException) {}
                    }
                }
                error_log("[QR-SAR DB Error] " . $e->getMessage());
                if (class_exists('LogService')) {
                    try {
                        LogService::critical("Gagal terhubung ke basis data MySQL: " . $e->getMessage(), [
                            'file' => $e->getFile(),
                            'line' => $e->getLine(),
                            'host' => $host,
                            'database' => $dbname
                        ]);
                    } catch (Throwable) {
                        // Safe fallback
                    }
                }
                if (defined('APP_DEBUG') && APP_DEBUG) {
                    throw new RuntimeException("Gagal terhubung ke basis data MySQL: " . $e->getMessage());
                }
                throw new RuntimeException("Gagal terhubung ke basis data MySQL. Pastikan konfigurasi dan server basis data aktif.");
            }
        }
        return self::$instance;
    }
}

function getDB(): PDO {
    return Database::getConnection();
}
