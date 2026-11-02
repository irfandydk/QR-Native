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
            $host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($_SERVER['DB_HOST'] ?? '127.0.0.1'));
            $port = getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? ($_SERVER['DB_PORT'] ?? '3306'));
            $dbname = getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: ($_ENV['DB_DATABASE'] ?? ($_SERVER['DB_DATABASE'] ?? 'qrsar')));
            $username = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: ($_ENV['DB_USERNAME'] ?? ($_SERVER['DB_USERNAME'] ?? 'root')));
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASSWORD'] ?? ($_SERVER['DB_PASSWORD'] ?? '')));

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
                // Attempt fallback to 'db_sar_qr' if 'qrsar' fails
                if ($dbname === 'qrsar') {
                    try {
                        $dsnFallback = "mysql:host={$host};port={$port};dbname=db_sar_qr;charset=utf8mb4";
                        self::$instance = new PDO($dsnFallback, $username, $password, $options);
                        return self::$instance;
                    } catch (PDOException $e2) {
                        // Throw original error
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
