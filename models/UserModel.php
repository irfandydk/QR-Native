<?php
/**
 * QR-SAR - User Management Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class UserModel {
    private static bool $tableChecked = false;

    /**
     * Ensures Users table exists in database with strict indexes.
     * Controlled via $force = true to isolate DDL strictly to database/migrate.php.
     */
    public static function ensureTableExists(bool $force = false): void {
        if (!$force) return;
        if (self::$tableChecked) return;

        try {
            $db = getDB();
            $db->exec("CREATE TABLE IF NOT EXISTS `Users` (
                `id` VARCHAR(50) NOT NULL,
                `username` VARCHAR(100) NOT NULL,
                `password_hash` VARCHAR(255) NOT NULL,
                `nama` VARCHAR(191) NOT NULL,
                `nip` VARCHAR(50) DEFAULT '',
                `pangkat` VARCHAR(100) DEFAULT '',
                `jabatan` VARCHAR(191) DEFAULT '',
                `email` VARCHAR(191) DEFAULT '',
                `no_hp` VARCHAR(50) DEFAULT '',
                `role` ENUM('ADMIN', 'USER') NOT NULL DEFAULT 'USER',
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_login_at` DATETIME DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `idx_users_username` (`username`),
                KEY `idx_users_role` (`role`),
                KEY `idx_users_active` (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            self::$tableChecked = true;
        } catch (Throwable $e) {
            error_log("[QR-SAR UserModel] Error checking Users table: " . $e->getMessage());
        }
    }

    /**
     * Syncs users from legacy AppSettings row if Users table is empty or missing them
     */
    public static function syncFromAppSettings(bool $force = false): void {
        if (!$force) return;
        self::ensureTableExists(true);

        try {
            $db = getDB();
            $count = (int)$db->query("SELECT COUNT(*) FROM `Users`")->fetchColumn();
            if ($count > 0) return;

            // Load settings
            $settings = $db->query("SELECT * FROM AppSettings WHERE id = 1 LIMIT 1")->fetch() ?: [];
            if (empty($settings)) return;

            $now = date('Y-m-d H:i:s');

            // 1. Admin Account
            $adminUser = !empty($settings['admin_username']) ? trim($settings['admin_username']) : 'admin';
            $adminPass = (string)($settings['admin_password'] ?? (defined('DEFAULT_RESET_PASSWORD') ? DEFAULT_RESET_PASSWORD : ''));
            $adminHash = (str_starts_with($adminPass, '$2y$') || str_starts_with($adminPass, '$argon2'))
                ? $adminPass
                : password_hash($adminPass, PASSWORD_DEFAULT);

            $stmtAdmin = $db->prepare("INSERT INTO `Users` (
                id, username, password_hash, nama, nip, pangkat, jabatan, email, no_hp, role, is_active, created_at, updated_at
            ) VALUES (
                'USR-ADMIN', :u, :p, :nama, :nip, :pangkat, :jabatan, :email, :no_hp, 'ADMIN', 1, :c, :u_date
            ) ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)");

            $stmtAdmin->execute([
                ':u' => $adminUser,
                ':p' => $adminHash,
                ':nama' => $settings['admin_nama'] ?? 'AMIRUDDIN AS, S.Sos.',
                ':nip' => $settings['admin_nip'] ?? '197405101998031002',
                ':pangkat' => $settings['admin_pangkat'] ?? 'PEMBINA (IV/a)',
                ':jabatan' => $settings['admin_jabatan'] ?? 'Kepala Kantor KPP Kendari',
                ':email' => $settings['admin_email'] ?? 'irfandydk@gmail.com',
                ':no_hp' => $settings['admin_no_hp'] ?? '0811-4091-115',
                ':c' => $now,
                ':u_date' => $now
            ]);

            // 2. User Account
            $userUser = !empty($settings['user_username']) ? trim($settings['user_username']) : 'user';
            $userPass = (string)($settings['user_password'] ?? (defined('DEFAULT_RESET_PASSWORD') ? DEFAULT_RESET_PASSWORD : ''));
            $userHash = (str_starts_with($userPass, '$2y$') || str_starts_with($userPass, '$argon2'))
                ? $userPass
                : password_hash($userPass, PASSWORD_DEFAULT);

            $stmtUser = $db->prepare("INSERT INTO `Users` (
                id, username, password_hash, nama, nip, pangkat, jabatan, email, no_hp, role, is_active, created_at, updated_at
            ) VALUES (
                'USR-USER', :u, :p, :nama, :nip, :pangkat, :jabatan, :email, :no_hp, 'USER', 1, :c, :u_date
            ) ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at)");

            $stmtUser->execute([
                ':u' => $userUser,
                ':p' => $userHash,
                ':nama' => $settings['user_nama'] ?? 'Petugas Siaga SAR',
                ':nip' => $settings['user_nip'] ?? '199501012020121001',
                ':pangkat' => $settings['user_pangkat'] ?? 'PENGATUR (II/c)',
                ':jabatan' => $settings['user_jabatan'] ?? 'Operator Komunikasi & Petugas Siaga SAR',
                ':email' => $settings['user_email'] ?? 'operator.kendari@basarnas.go.id',
                ':no_hp' => $settings['user_no_hp'] ?? '0811-4091-115',
                ':c' => $now,
                ':u_date' => $now
            ]);
        } catch (Throwable $e) {
            error_log("[QR-SAR UserModel] Error syncing users: " . $e->getMessage());
        }
    }

    public static function getAll(): array {
        $db = getDB();
        $stmt = $db->query("SELECT id, username, nama, nip, pangkat, jabatan, email, no_hp, role, is_active, last_login_at, created_at, updated_at FROM `Users` ORDER BY role ASC, nama ASC");
        return $stmt->fetchAll();
    }

    public static function getById(string $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM `Users` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function getByUsername(string $username): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM `Users` WHERE LOWER(username) = LOWER(:u) LIMIT 1");
        $stmt->execute([':u' => trim($username)]);
        return $stmt->fetch() ?: null;
    }

    public static function updateLastLogin(string $id): void {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE `Users` SET last_login_at = :now WHERE id = :id");
            $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
        } catch (Throwable) {}
    }

    public static function updatePassword(string $id, string $newPassword): bool {
        $db = getDB();
        $hash = password_hash(trim($newPassword), PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE `Users` SET password_hash = :p, updated_at = :u WHERE id = :id");
        return $stmt->execute([
            ':p' => $hash,
            ':u' => date('Y-m-d H:i:s'),
            ':id' => $id
        ]);
    }
}
