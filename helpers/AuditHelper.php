<?php
/**
 * QR-SAR - Audit Trail Helper
 * KPP Kendari - Basarnas
 * Records mission-critical operations, authentication events, and administrative activities
 */

declare(strict_types=1);

class AuditHelper {
    private static bool $tableChecked = false;

    /**
     * Ensures AuditLog table exists with appropriate indexes
     */
    public static function ensureTableExists(): void {
        if (self::$tableChecked) return;

        try {
            $db = getDB();
            // DDL memicu implicit commit MySQL: di dalam transaksi model, commit() pemanggil akan gagal walau data tersimpan
            if ($db->inTransaction()) {
                return;
            }
            $db->exec("CREATE TABLE IF NOT EXISTS `AuditLog` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `user_id` VARCHAR(50) DEFAULT NULL,
                `username` VARCHAR(100) DEFAULT 'system',
                `role` VARCHAR(50) DEFAULT 'GUEST',
                `action` VARCHAR(100) NOT NULL,
                `target_type` VARCHAR(50) DEFAULT NULL,
                `target_id` VARCHAR(100) DEFAULT NULL,
                `description` TEXT NOT NULL,
                `ip_address` VARCHAR(45) NOT NULL,
                `user_agent` VARCHAR(255) DEFAULT '',
                `created_at` DATETIME NOT NULL,
                KEY `idx_audit_user` (`user_id`),
                KEY `idx_audit_action` (`action`),
                KEY `idx_audit_target` (`target_type`, `target_id`),
                KEY `idx_audit_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            self::$tableChecked = true;
        } catch (Throwable $e) {
            error_log("[QR-SAR AuditHelper] Error checking AuditLog table: " . $e->getMessage());
        }
    }

    /**
     * Safely logs an activity without throwing exceptions to ensure primary flow never breaks
     */
    public static function log(string $action, string $description, ?string $targetType = null, ?string $targetId = null): void {
        try {
            self::ensureTableExists();
            $db = getDB();

            $currentUser = class_exists('AuthHelper') ? AuthHelper::getCurrentUser() : [];
            $userId = $currentUser['id'] ?? ($_SESSION['user_id'] ?? null);
            $username = $currentUser['username'] ?? ($_SESSION['username'] ?? 'system');
            $role = $currentUser['role'] ?? ($_SESSION['role'] ?? 'GUEST');

            $ip = class_exists('AuthHelper') ? AuthHelper::getClientIp() : ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
            $now = date('Y-m-d H:i:s');

            $stmt = $db->prepare("INSERT INTO `AuditLog` (
                user_id, username, role, action, target_type, target_id, description, ip_address, user_agent, created_at
            ) VALUES (
                :uid, :uname, :role, :act, :ttype, :tid, :descr, :ip, :ua, :now
            )");

            $stmt->execute([
                ':uid' => $userId,
                ':uname' => substr($username, 0, 100),
                ':role' => substr($role, 0, 50),
                ':act' => strtoupper($action),
                ':ttype' => $targetType ? strtoupper($targetType) : null,
                ':tid' => $targetId,
                ':descr' => $description,
                ':ip' => $ip,
                ':ua' => $ua,
                ':now' => $now
            ]);
        } catch (Throwable $e) {
            error_log("[QR-SAR AuditLog Failure] Action: {$action} | " . $e->getMessage());
        }
    }

    /**
     * Fetches recent audit logs for administration panel
     */
    public static function getLogs(int $limit = 50, int $offset = 0, ?string $action = null, ?string $search = null): array {
        self::ensureTableExists();
        $db = getDB();

        $sql = "SELECT * FROM `AuditLog` WHERE 1=1";
        $params = [];

        if (!empty($action)) {
            $sql .= " AND action = :act";
            $params[':act'] = strtoupper($action);
        }

        if (!empty($search)) {
            $sql .= " AND (description LIKE :q1 OR username LIKE :q2 OR ip_address LIKE :q3 OR target_id LIKE :q4)";
            $q = "%{$search}%";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }

        $sql .= " ORDER BY id DESC LIMIT " . max(1, min(200, $limit)) . " OFFSET " . max(0, $offset);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countLogs(?string $action = null, ?string $search = null): int {
        self::ensureTableExists();
        $db = getDB();

        $sql = "SELECT COUNT(*) FROM `AuditLog` WHERE 1=1";
        $params = [];

        if (!empty($action)) {
            $sql .= " AND action = :act";
            $params[':act'] = strtoupper($action);
        }

        if (!empty($search)) {
            $sql .= " AND (description LIKE :q1 OR username LIKE :q2 OR ip_address LIKE :q3 OR target_id LIKE :q4)";
            $q = "%{$search}%";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Prunes audit logs older than specified days (default 180 days)
     * Retains minimum 7 days as emergency security guardrail
     */
    public static function pruneLogs(int $olderThanDays = 180): array {
        self::ensureTableExists();
        $db = getDB();
        $days = max(7, $olderThanDays);

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM `AuditLog` WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
        $stmtCount->execute([':days' => $days]);
        $count = (int)$stmtCount->fetchColumn();

        if ($count > 0) {
            $stmtDel = $db->prepare("DELETE FROM `AuditLog` WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $stmtDel->execute([':days' => $days]);

            self::log('PRUNE_AUDIT_LOG', "Pembersihan {$count} catatan rekam jejak audit log yang berumur lebih dari {$days} hari.", 'AUDIT', 'RETENTION');
        }

        return [
            'deleted' => $count,
            'days' => $days
        ];
    }

    /**
     * Streams complete audit logs as standard CSV with Excel-compatible UTF-8 BOM
     */
    public static function exportCsv(?string $action = null, ?string $search = null): void {
        self::ensureTableExists();
        $db = getDB();

        $sql = "SELECT id, created_at, action, username, role, target_type, target_id, ip_address, description FROM `AuditLog` WHERE 1=1";
        $params = [];
        if (!empty($action)) {
            $sql .= " AND action = :act";
            $params[':act'] = strtoupper($action);
        }
        if (!empty($search)) {
            $sql .= " AND (description LIKE :q1 OR username LIKE :q2 OR ip_address LIKE :q3 OR target_id LIKE :q4)";
            $q = "%{$search}%";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }
        $sql .= " ORDER BY id DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        $filename = 'audit_log_sar304_' . date('Ymd_His') . '.csv';
        if (!headers_sent()) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        $output = fopen('php://output', 'w');
        if ($output) {
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($output, ['ID', 'Waktu (WITA)', 'Tindakan (Action)', 'Pengguna', 'Role', 'Tipe Target', 'ID Target', 'Alamat IP', 'Deskripsi'], ',', '"', "\\");

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Sel berawalan = + - @ tab/CR diberi ' agar tidak dieksekusi sebagai rumus spreadsheet
                fputcsv($output, array_map(static fn($v) => is_string($v) && $v !== '-' && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, [
                    $row['id'],
                    $row['created_at'],
                    $row['action'],
                    $row['username'],
                    $row['role'],
                    $row['target_type'] ?? '-',
                    $row['target_id'] ?? '-',
                    $row['ip_address'],
                    $row['description']
                ]), ',', '"', "\\");
            }
            fclose($output);
        }
        exit;
    }
}
