<?php
/**
 * QR-SAR - User Management Model (Multi-Account CRUD & Profile Avatar Management)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class UserModel {
    private static bool $tableChecked = false;
    private static bool $schemaChecked = false;
    public const AVATAR_REL_DIR = 'uploads/avatars';
    public const MAX_AVATAR_BYTES = 3145728; // 3 MB

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
                `avatar_url` VARCHAR(255) NOT NULL DEFAULT '',
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
     * Pastikan kolom avatar_url tersedia di tabel Users secara idempoten.
     */
    public static function ensureSchemaUpdated(bool $force = false): void {
        if (!$force && self::$schemaChecked) {
            return;
        }
        try {
            $db = getDB();
            $cols = $db->query("SHOW COLUMNS FROM `Users` LIKE 'avatar_url'")->fetchAll();
            if (empty($cols)) {
                if ($db->inTransaction()) {
                    return;
                }
                $db->exec("ALTER TABLE `Users` ADD COLUMN `avatar_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `no_hp`");
            }
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            error_log("[QR-SAR UserModel ensureSchemaUpdated] " . $e->getMessage());
        }
    }

    public static function hasAvatarColumn(): bool {
        try {
            $db = getDB();
            $cols = $db->query("SHOW COLUMNS FROM `Users` LIKE 'avatar_url'")->fetchAll();
            return !empty($cols);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Syncs users from legacy AppSettings row if Users table is empty or missing them.
     */
    public static function syncFromAppSettings(bool $force = false): void {
        if (!$force) return;
        self::ensureTableExists(true);

        try {
            $db = getDB();
            $count = (int)$db->query("SELECT COUNT(*) FROM `Users`")->fetchColumn();
            if ($count > 0) return;

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
        $avatarSelect = self::hasAvatarColumn() ? 'avatar_url, ' : "'' AS avatar_url, ";
        $stmt = $db->query("SELECT id, username, nama, nip, pangkat, jabatan, email, no_hp, {$avatarSelect}role, is_active, last_login_at, created_at, updated_at FROM `Users` ORDER BY role ASC, nama ASC");
        return $stmt->fetchAll();
    }

    public static function getById(string $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM `Users` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (!array_key_exists('avatar_url', $row)) {
            $row['avatar_url'] = '';
        }
        return $row;
    }

    public static function getByUsername(string $username): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM `Users` WHERE LOWER(username) = LOWER(:u) LIMIT 1");
        $stmt->execute([':u' => trim($username)]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (!array_key_exists('avatar_url', $row)) {
            $row['avatar_url'] = '';
        }
        return $row;
    }

    public static function countActiveAdmins(?string $excludeId = null): int {
        $db = getDB();
        if ($excludeId !== null && trim($excludeId) !== '') {
            $stmt = $db->prepare("SELECT COUNT(*) FROM `Users` WHERE role = 'ADMIN' AND is_active = 1 AND id <> :id");
            $stmt->execute([':id' => trim($excludeId)]);
            return (int)$stmt->fetchColumn();
        }
        return (int)$db->query("SELECT COUNT(*) FROM `Users` WHERE role = 'ADMIN' AND is_active = 1")->fetchColumn();
    }

    /**
     * Buat akun Admin atau User baru.
     *
     * @return array{success:bool, id?:string, message?:string, error?:string}
     */
    public static function create(array $data): array {
        self::ensureSchemaUpdated(false);
        $db = getDB();

        $username = trim((string)($data['username'] ?? ''));
        $nama = trim((string)($data['nama'] ?? ''));
        $password = trim((string)($data['password'] ?? ''));
        $role = strtoupper(trim((string)($data['role'] ?? 'USER')));
        if (!in_array($role, ['ADMIN', 'USER'], true)) {
            $role = 'USER';
        }

        if ($nama === '' || $username === '') {
            return ['success' => false, 'error' => 'Nama lengkap dan Username wajib diisi.'];
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            return ['success' => false, 'error' => 'Username harus 3-50 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Kata sandi akun baru minimal 8 karakter.'];
        }
        if (self::getByUsername($username) !== null) {
            return ['success' => false, 'error' => "Username '{$username}' sudah digunakan oleh akun lain."];
        }

        $id = 'USR-' . strtoupper(bin2hex(random_bytes(5)));
        $now = date('Y-m-d H:i:s');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $isActive = array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : 1;
        $avatarUrl = self::sanitizeAvatarPath((string)($data['avatar_url'] ?? ''));

        $hasAvatar = self::hasAvatarColumn();
        try {
            if ($hasAvatar) {
                $stmt = $db->prepare("INSERT INTO `Users`
                    (id, username, password_hash, nama, nip, pangkat, jabatan, email, no_hp, avatar_url, role, is_active, created_at, updated_at)
                    VALUES (:id, :u, :p, :nama, :nip, :pangkat, :jabatan, :email, :no_hp, :avatar, :role, :active, :c, :u_at)");
                $stmt->execute([
                    ':id' => $id,
                    ':u' => $username,
                    ':p' => $hash,
                    ':nama' => $nama,
                    ':nip' => trim((string)($data['nip'] ?? '')),
                    ':pangkat' => trim((string)($data['pangkat'] ?? '')),
                    ':jabatan' => trim((string)($data['jabatan'] ?? '')),
                    ':email' => trim((string)($data['email'] ?? '')),
                    ':no_hp' => trim((string)($data['no_hp'] ?? '')),
                    ':avatar' => $avatarUrl,
                    ':role' => $role,
                    ':active' => $isActive,
                    ':c' => $now,
                    ':u_at' => $now,
                ]);
            } else {
                $stmt = $db->prepare("INSERT INTO `Users`
                    (id, username, password_hash, nama, nip, pangkat, jabatan, email, no_hp, role, is_active, created_at, updated_at)
                    VALUES (:id, :u, :p, :nama, :nip, :pangkat, :jabatan, :email, :no_hp, :role, :active, :c, :u_at)");
                $stmt->execute([
                    ':id' => $id,
                    ':u' => $username,
                    ':p' => $hash,
                    ':nama' => $nama,
                    ':nip' => trim((string)($data['nip'] ?? '')),
                    ':pangkat' => trim((string)($data['pangkat'] ?? '')),
                    ':jabatan' => trim((string)($data['jabatan'] ?? '')),
                    ':email' => trim((string)($data['email'] ?? '')),
                    ':no_hp' => trim((string)($data['no_hp'] ?? '')),
                    ':role' => $role,
                    ':active' => $isActive,
                    ':c' => $now,
                    ':u_at' => $now,
                ]);
            }

            if (class_exists('AuditHelper')) {
                AuditHelper::log('USER_CREATE', "Menambahkan akun {$role} baru: {$nama} (@{$username})", 'Users', $id);
            }
            return ['success' => true, 'id' => $id, 'message' => "Akun {$role} '{$nama}' (@{$username}) berhasil ditambahkan."];
        } catch (Throwable $e) {
            error_log('[QR-SAR UserModel create] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Gagal menyimpan akun baru: username mungkin sudah dipakai.'];
        }
    }

    /**
     * Perbarui akun Admin atau User berdasarkan ID.
     *
     * @return array{success:bool, message?:string, error?:string}
     */
    public static function update(string $id, array $data, ?string $actorId = null): array {
        self::ensureSchemaUpdated(false);
        $db = getDB();

        $existing = self::getById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Akun pengguna tidak ditemukan.'];
        }

        $username = trim((string)($data['username'] ?? $existing['username']));
        $nama = trim((string)($data['nama'] ?? $existing['nama']));
        $role = strtoupper(trim((string)($data['role'] ?? $existing['role'])));
        if (!in_array($role, ['ADMIN', 'USER'], true)) {
            $role = (string)$existing['role'];
        }
        $isActive = array_key_exists('is_active', $data) ? (!empty($data['is_active']) ? 1 : 0) : (int)$existing['is_active'];

        if ($nama === '' || $username === '') {
            return ['success' => false, 'error' => 'Nama lengkap dan Username wajib diisi.'];
        }
        if (!preg_match('/^[a-zA-Z0-9._-]{3,50}$/', $username)) {
            return ['success' => false, 'error' => 'Username harus 3-50 karakter (huruf, angka, titik, garis bawah, atau tanda hubung).'];
        }

        $dup = self::getByUsername($username);
        if ($dup !== null && (string)$dup['id'] !== $id) {
            return ['success' => false, 'error' => "Username '{$username}' sudah digunakan oleh akun lain."];
        }

        // Proteksi: jangan sampai admin yang sedang login menurunkan role atau menonaktifkan dirinya sendiri
        if ($actorId !== null && $actorId === $id) {
            if ($role !== 'ADMIN') {
                return ['success' => false, 'error' => 'Anda tidak dapat menurunkan role akun Administrator yang sedang Anda gunakan.'];
            }
            if ($isActive === 0) {
                return ['success' => false, 'error' => 'Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan.'];
            }
        }

        // Proteksi: wajib selalu ada minimal 1 ADMIN aktif
        if (strtoupper((string)$existing['role']) === 'ADMIN' && ($role !== 'ADMIN' || $isActive === 0)) {
            if (self::countActiveAdmins($id) < 1) {
                return ['success' => false, 'error' => 'Tidak dapat mengubah role atau menonaktifkan Administrator aktif terakhir.'];
            }
        }

        $newPass = trim((string)($data['password'] ?? ''));
        if ($newPass !== '' && strlen($newPass) < 8) {
            return ['success' => false, 'error' => 'Kata sandi baru minimal 8 karakter.'];
        }

        $oldAvatar = (string)($existing['avatar_url'] ?? '');
        $nextAvatar = $oldAvatar;
        if (!empty($data['clear_avatar'])) {
            $nextAvatar = '';
        } elseif (array_key_exists('avatar_url', $data) && trim((string)$data['avatar_url']) !== '') {
            $nextAvatar = self::sanitizeAvatarPath((string)$data['avatar_url']);
        }

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $sets = [
                'username = :u',
                'nama = :nama',
                'nip = :nip',
                'pangkat = :pangkat',
                'jabatan = :jabatan',
                'email = :email',
                'no_hp = :no_hp',
                'role = :role',
                'is_active = :active',
                'updated_at = :now',
            ];
            $params = [
                ':id' => $id,
                ':u' => $username,
                ':nama' => $nama,
                ':nip' => trim((string)($data['nip'] ?? $existing['nip'] ?? '')),
                ':pangkat' => trim((string)($data['pangkat'] ?? $existing['pangkat'] ?? '')),
                ':jabatan' => trim((string)($data['jabatan'] ?? $existing['jabatan'] ?? '')),
                ':email' => trim((string)($data['email'] ?? $existing['email'] ?? '')),
                ':no_hp' => trim((string)($data['no_hp'] ?? $existing['no_hp'] ?? '')),
                ':role' => $role,
                ':active' => $isActive,
                ':now' => date('Y-m-d H:i:s'),
            ];

            if (self::hasAvatarColumn()) {
                $sets[] = 'avatar_url = :avatar';
                $params[':avatar'] = $nextAvatar;
            }

            $newHash = null;
            if ($newPass !== '') {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                $sets[] = 'password_hash = :p';
                $params[':p'] = $newHash;
            }

            $db->prepare("UPDATE `Users` SET " . implode(', ', $sets) . " WHERE id = :id")->execute($params);

            // Sinkronisasi legacy AppSettings untuk USR-ADMIN / USR-USER agar backward-compatible
            if ($id === 'USR-ADMIN') {
                $admSets = "admin_username = :u, admin_nama = :nama, admin_nip = :nip, admin_pangkat = :pangkat, admin_jabatan = :jabatan, admin_email = :email, admin_no_hp = :no_hp";
                $admParams = [
                    ':u' => $username, ':nama' => $nama, ':nip' => $params[':nip'],
                    ':pangkat' => $params[':pangkat'], ':jabatan' => $params[':jabatan'],
                    ':email' => $params[':email'], ':no_hp' => $params[':no_hp'],
                ];
                if ($newHash !== null) {
                    $admSets .= ", admin_password = :p";
                    $admParams[':p'] = $newHash;
                }
                $db->prepare("UPDATE AppSettings SET {$admSets} WHERE id = 1")->execute($admParams);
                SettingsModel::clearCache();
            } elseif ($id === 'USR-USER') {
                $usrSets = "user_username = :u, user_nama = :nama, user_nip = :nip, user_pangkat = :pangkat, user_jabatan = :jabatan, user_email = :email, user_no_hp = :no_hp";
                $usrParams = [
                    ':u' => $username, ':nama' => $nama, ':nip' => $params[':nip'],
                    ':pangkat' => $params[':pangkat'], ':jabatan' => $params[':jabatan'],
                    ':email' => $params[':email'], ':no_hp' => $params[':no_hp'],
                ];
                if ($newHash !== null) {
                    $usrSets .= ", user_password = :p";
                    $usrParams[':p'] = $newHash;
                }
                $db->prepare("UPDATE AppSettings SET {$usrSets} WHERE id = 1")->execute($usrParams);
                SettingsModel::clearCache();
            }

            if ($newHash !== null && $actorId === $id) {
                AuthHelper::refreshCredentialFingerprint($id, $newHash);
            }

            // Sinkronkan sesi aktif jika Admin menyunting akunnya sendiri
            if ($actorId === $id && isset($_SESSION['user']) && ($_SESSION['user_id'] ?? '') === $id) {
                $_SESSION['user']['username'] = $username;
                $_SESSION['user']['nama'] = $nama;
                $_SESSION['user']['nip'] = $params[':nip'] ?: '-';
                $_SESSION['user']['pangkat'] = $params[':pangkat'] ?: '-';
                $_SESSION['user']['jabatan'] = $params[':jabatan'] ?: 'Administrator SAR';
                $_SESSION['user']['email'] = $params[':email'];
                $_SESSION['user']['no_hp'] = $params[':no_hp'];
                $_SESSION['user']['avatar_url'] = $nextAvatar;
                $_SESSION['username'] = $username;
                $_SESSION['nama'] = $nama;
                $_SESSION['avatar_url'] = $nextAvatar;
            }

            if (class_exists('AuditHelper')) {
                AuditHelper::log('USER_UPDATE', "Memperbarui akun {$role}: {$nama} (@{$username})", 'Users', $id);
            }

            if (!$inTx) $db->commit();

            // Hapus berkas foto lama bila diganti/dihapus pasca-commit
            if ($oldAvatar !== '' && $oldAvatar !== $nextAvatar) {
                self::deleteAvatarFile($oldAvatar);
            }

            return ['success' => true, 'message' => "Akun '{$nama}' (@{$username}) berhasil diperbarui."];
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            error_log('[QR-SAR UserModel update] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Gagal memperbarui data akun pengguna.'];
        }
    }

    /**
     * Hapus akun Admin atau User secara aman.
     *
     * @return array{success:bool, message?:string, error?:string}
     */
    public static function delete(string $id, ?string $actorId = null): array {
        $db = getDB();
        $existing = self::getById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Akun pengguna tidak ditemukan.'];
        }
        if ($actorId !== null && $actorId === $id) {
            return ['success' => false, 'error' => 'Anda tidak dapat menghapus akun yang sedang Anda gunakan untuk login.'];
        }
        if (strtoupper((string)$existing['role']) === 'ADMIN' && self::countActiveAdmins($id) < 1) {
            return ['success' => false, 'error' => 'Tidak dapat menghapus Administrator aktif terakhir di sistem.'];
        }

        $oldAvatar = (string)($existing['avatar_url'] ?? '');
        try {
            $db->prepare("DELETE FROM `Users` WHERE id = :id")->execute([':id' => $id]);
            if ($oldAvatar !== '') {
                self::deleteAvatarFile($oldAvatar);
            }
            if (class_exists('AuditHelper')) {
                AuditHelper::log('USER_DELETE', "Menghapus akun {$existing['role']}: {$existing['nama']} (@{$existing['username']})", 'Users', $id);
            }
            return ['success' => true, 'message' => "Akun '{$existing['nama']}' (@{$existing['username']}) berhasil dihapus."];
        } catch (Throwable $e) {
            error_log('[QR-SAR UserModel delete] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Gagal menghapus akun pengguna.'];
        }
    }

    /**
     * Perbarui foto profil milik sendiri (Admin maupun User di halaman profile.php).
     *
     * @return array{success:bool, avatar_url?:string, message?:string, error?:string}
     */
    public static function updateAvatar(string $id, string $newAvatarUrl): array {
        self::ensureSchemaUpdated(false);
        $existing = self::getById($id);
        if (!$existing) {
            return ['success' => false, 'error' => 'Akun pengguna tidak ditemukan.'];
        }
        if (!self::hasAvatarColumn()) {
            return ['success' => false, 'error' => 'Kolom foto profil belum tersedia. Jalankan migrasi database terlebih dahulu.'];
        }

        $oldAvatar = (string)($existing['avatar_url'] ?? '');
        $cleanNew = self::sanitizeAvatarPath($newAvatarUrl);

        try {
            $db = getDB();
            $db->prepare("UPDATE `Users` SET avatar_url = :a, updated_at = :now WHERE id = :id")->execute([
                ':a' => $cleanNew,
                ':now' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);

            if ($oldAvatar !== '' && $oldAvatar !== $cleanNew) {
                self::deleteAvatarFile($oldAvatar);
            }

            if (($_SESSION['user_id'] ?? '') === $id) {
                if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
                    $_SESSION['user']['avatar_url'] = $cleanNew;
                }
                $_SESSION['avatar_url'] = $cleanNew;
            }

            if (class_exists('AuditHelper')) {
                $desc = $cleanNew !== '' ? "Memperbarui foto profil akun {$existing['username']}" : "Menghapus foto profil akun {$existing['username']}";
                AuditHelper::log('PROFILE_AVATAR_UPDATE', $desc, 'Users', $id);
            }

            return [
                'success' => true,
                'avatar_url' => $cleanNew,
                'message' => $cleanNew !== '' ? 'Foto profil berhasil diperbarui.' : 'Foto profil berhasil dihapus.',
            ];
        } catch (Throwable $e) {
            error_log('[QR-SAR UserModel updateAvatar] ' . $e->getMessage());
            return ['success' => false, 'error' => 'Gagal memperbarui foto profil.'];
        }
    }

    /**
     * Reset sandi akun ke sandi default (dipakai khusus alur reset rahasia Admin).
     */
    public static function resetPasswordToDefault(string $id): bool {
        $existing = self::getById($id);
        if (!$existing) {
            return false;
        }
        $defaultPlain = defined('DEFAULT_RESET_PASSWORD') ? (string)DEFAULT_RESET_PASSWORD : 'Kendari2026#';
        $newHash = password_hash($defaultPlain, PASSWORD_DEFAULT);

        $db = getDB();
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $db->prepare("UPDATE `Users` SET password_hash = :p, updated_at = :now WHERE id = :id")->execute([
                ':p' => $newHash,
                ':now' => date('Y-m-d H:i:s'),
                ':id' => $id,
            ]);
            if ($id === 'USR-ADMIN') {
                $db->prepare("UPDATE AppSettings SET admin_password = :p WHERE id = 1")->execute([':p' => $newHash]);
                SettingsModel::clearCache();
            }
            if (!$inTx) $db->commit();
            return true;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            error_log('[QR-SAR UserModel resetPasswordToDefault] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validasi dan optimasi berkas unggahan foto profil (JPG/PNG/WebP, crop persegi 320x320 ke uploads/avatars/).
     *
     * @return array{success:bool, path?:string, error?:string}
     */
    public static function processAvatarUpload(array $file): array {
        if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Berkas foto profil tidak valid atau gagal diunggah.'];
        }
        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_AVATAR_BYTES) {
            return ['success' => false, 'error' => 'Ukuran foto profil maksimal 3 MB.'];
        }

        $tmpPath = (string)$file['tmp_name'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmpPath);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            return ['success' => false, 'error' => 'Format foto profil harus JPG, PNG, atau WebP.'];
        }

        $uploadDir = ROOT_DIR . '/' . self::AVATAR_REL_DIR;
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }
        $guardFile = $uploadDir . '/index.php';
        if (!is_file($guardFile)) {
            @file_put_contents($guardFile, "<?php\nhttp_response_code(403);\nexit('Akses Ditolak.');\n");
        }

        $srcImg = null;
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $srcImg = @imagecreatefromjpeg($tmpPath);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $srcImg = @imagecreatefrompng($tmpPath);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $srcImg = @imagecreatefromwebp($tmpPath);
        }

        $token = 'avatar_' . time() . '_' . bin2hex(random_bytes(5));
        if ($srcImg) {
            $origW = imagesx($srcImg);
            $origH = imagesy($srcImg);
            $side = min($origW, $origH);
            $srcX = (int)floor(($origW - $side) / 2);
            $srcY = (int)floor(($origH - $side) / 2);
            $target = 320;

            $dstImg = imagecreatetruecolor($target, $target);
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $trans = imagecolorallocatealpha($dstImg, 0, 0, 0, 127);
            imagefilledrectangle($dstImg, 0, 0, $target, $target, $trans);
            imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $target, $target, $side, $side);

            $saved = false;
            $relPath = '';
            if (function_exists('imagewebp')) {
                $relPath = self::AVATAR_REL_DIR . '/' . $token . '.webp';
                $saved = @imagewebp($dstImg, ROOT_DIR . '/' . $relPath, 88);
            }
            if (!$saved && function_exists('imagepng')) {
                $relPath = self::AVATAR_REL_DIR . '/' . $token . '.png';
                $saved = @imagepng($dstImg, ROOT_DIR . '/' . $relPath, 7);
            }
            unset($dstImg, $srcImg);

            if ($saved && $relPath !== '') {
                @chmod(ROOT_DIR . '/' . $relPath, 0664);
                return ['success' => true, 'path' => $relPath];
            }
        }

        // Fallback tanpa GD
        $ext = $allowed[$mime];
        $relPath = self::AVATAR_REL_DIR . '/' . $token . '.' . $ext;
        $dest = ROOT_DIR . '/' . $relPath;
        $moved = is_uploaded_file($tmpPath) ? @move_uploaded_file($tmpPath, $dest) : @copy($tmpPath, $dest);
        if (!$moved) {
            return ['success' => false, 'error' => 'Gagal menyimpan berkas foto profil ke server.'];
        }
        @chmod($dest, 0664);
        return ['success' => true, 'path' => $relPath];
    }

    public static function sanitizeAvatarPath(string $path): string {
        $clean = ltrim(str_replace('\\', '/', trim($path)), '/');
        if ($clean === '') {
            return '';
        }
        if (str_contains($clean, '..')) {
            return '';
        }
        if (!str_starts_with($clean, self::AVATAR_REL_DIR . '/')) {
            return '';
        }
        return $clean;
    }

    public static function deleteAvatarFile(string $relPath): void {
        $clean = self::sanitizeAvatarPath($relPath);
        if ($clean === '') {
            return;
        }
        $abs = ROOT_DIR . '/' . $clean;
        if (is_file($abs)) {
            @unlink($abs);
        }
    }

    public static function resolveAvatarDisplayUrl(?string $avatarUrl): string {
        $clean = self::sanitizeAvatarPath((string)$avatarUrl);
        if ($clean === '') {
            return '';
        }
        return BASE_URL . '/' . $clean;
    }

    public static function updateLastLogin(string $id): void {
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE `Users` SET last_login_at = :now WHERE id = :id");
            $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id]);
        } catch (Throwable) {}
    }
}
