<?php
/**
 * QR-SAR - Centralized Daily Rotating Logger Service
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Provides thread-safe, daily rotating file logging for system exceptions,
 * security alerts, database failures, and operational audit checkpoints.
 * Log destination: storage/logs/qrsar-YYYY-MM-DD.log
 */

declare(strict_types=1);

class LogService {
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';

    /**
     * Resolves the storage log directory path.
     */
    public static function getLogsDir(): string {
        if (defined('STORAGE_DIR')) {
            $dir = STORAGE_DIR . '/logs';
        } else {
            $dir = dirname(__DIR__) . '/storage/logs';
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    /**
     * Get the absolute path for today's log file or a specific date's log file.
     */
    public static function getLogPath(?string $date = null): string {
        $d = $date ?: date('Y-m-d');
        // Validate date format YYYY-MM-DD
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $d = date('Y-m-d');
        }
        return self::getLogsDir() . "/qrsar-{$d}.log";
    }

    /**
     * Log a message with a specific severity level.
     */
    public static function log(string $level, string $message, array $context = []): void {
        try {
            $timestamp = date('Y-m-d H:i:s');
            $levelUpper = strtoupper($level);
            
            // Client IP
            $ip = $_SERVER['REMOTE_ADDR'] ?? (php_sapi_name() === 'cli' ? 'CLI' : '127.0.0.1');
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $ip = trim($forwarded[0]);
            }

            // Authenticated user resolution
            $user = 'GUEST';
            if (isset($_SESSION['user']['username'])) {
                $user = (string)$_SESSION['user']['username'];
            } elseif (isset($_SESSION['user']['nama_lengkap'])) {
                $user = (string)$_SESSION['user']['nama_lengkap'];
            } elseif (php_sapi_name() === 'cli') {
                $user = 'CONSOLE';
            }

            // Location/File context
            $location = '';
            if (isset($context['file']) && isset($context['line'])) {
                $relFile = str_replace(dirname(__DIR__) . '/', '', (string)$context['file']);
                $location = " [{$relFile}:{$context['line']}]";
                unset($context['file'], $context['line']);
            }

            // JSON Context payload if any extra data exists
            $contextStr = '';
            if (!empty($context)) {
                $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($contextJson !== false && $contextJson !== '{}') {
                    $contextStr = " " . $contextJson;
                }
            }

            // Clean message from excess line breaks in single log line
            $cleanMsg = str_replace(["\r\n", "\r", "\n"], ' ', trim($message));
            $logLine = "[{$timestamp}] [{$levelUpper}] [{$ip}] [{$user}] {$cleanMsg}{$location}{$contextStr}" . PHP_EOL;

            $logFile = self::getLogPath();
            @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Silently fallback to prevent recursion or breaking system execution
        }
    }

    /**
     * Convenience helpers for different severity levels.
     */
    public static function info(string $message, array $context = []): void {
        self::log(self::INFO, $message, $context);
    }

    public static function warning(string $message, array $context = []): void {
        self::log(self::WARNING, $message, $context);
    }

    public static function error(string $message, array $context = []): void {
        self::log(self::ERROR, $message, $context);
    }

    public static function critical(string $message, array $context = []): void {
        self::log(self::CRITICAL, $message, $context);
    }

    public static function debug(string $message, array $context = []): void {
        self::log(self::DEBUG, $message, $context);
    }

    /**
     * Log an uncaught Throwable exception with complete stack trace context.
     */
    public static function logException(Throwable $e, ?string $contextInfo = null): void {
        $msg = $e->getMessage();
        if ($contextInfo) {
            $msg = "{$contextInfo} | {$msg}";
        }

        self::error($msg, [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'code' => $e->getCode(),
            'class' => get_class($e),
            'trace' => array_slice(explode("\n", $e->getTraceAsString()), 0, 5)
        ]);
    }

    /**
     * List all available daily log files sorted descending (newest first).
     * Returns array of ['date' => 'YYYY-MM-DD', 'filename' => '...', 'size' => bytes, 'modified' => timestamp]
     */
    public static function getLogFiles(): array {
        $dir = self::getLogsDir();
        $files = glob($dir . '/qrsar-*.log') ?: [];
        $result = [];

        foreach ($files as $file) {
            $base = basename($file);
            if (preg_match('/^qrsar-(\d{4}-\d{2}-\d{2})\.log$/', $base, $m)) {
                $result[] = [
                    'date' => $m[1],
                    'filename' => $base,
                    'path' => $file,
                    'size' => filesize($file),
                    'modified' => filemtime($file)
                ];
            }
        }

        usort($result, fn($a, $b) => strcmp($b['date'], $a['date']));
        return $result;
    }

    /**
     * Read content of a specific date's log file (safe bounded read).
     */
    public static function getLogContent(string $date, int $maxBytes = 2097152): ?string {
        $path = self::getLogPath($date);
        if (!file_exists($path)) {
            return null;
        }
        $size = filesize($path);
        if ($size > $maxBytes) {
            // Read last $maxBytes bytes
            $fp = @fopen($path, 'r');
            if (!$fp) return null;
            fseek($fp, -$maxBytes, SEEK_END);
            $content = fread($fp, $maxBytes);
            fclose($fp);
            return "[... Log truncated due to size limit ...]\n" . ($content ?: '');
        }
        return @file_get_contents($path) ?: '';
    }

    /**
     * Prune old log files older than $keepDays.
     * Returns count of deleted files.
     */
    public static function cleanOldLogs(int $keepDays = 30): int {
        $cutoffTime = time() - ($keepDays * 86400);
        $files = self::getLogFiles();
        $deleted = 0;

        foreach ($files as $f) {
            $fileDate = strtotime($f['date']);
            if ($fileDate !== false && $fileDate < $cutoffTime) {
                if (@unlink($f['path'])) {
                    $deleted++;
                }
            }
        }
        return $deleted;
    }
}
