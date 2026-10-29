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
            $defaultHash = password_hash(defined('DEFAULT_RESET_PASSWORD') ? DEFAULT_RESET_PASSWORD : 'Kendari304', PASSWORD_DEFAULT);
            $stmtInit = $db->prepare("INSERT INTO AppSettings (
                id, satker_kode, satker_nama, 
                admin_username, admin_password, admin_nama,
                user_username, user_password, user_nama
            ) VALUES (
                1, 'SAR-304', 'Kantor Pencarian dan Pertolongan Kelas A Kendari',
                'admin', :pa, 'AMIRUDDIN AS, S.Sos.',
                'user', :pu, 'Petugas Siaga SAR'
            )");
            $stmtInit->execute([':pa' => $defaultHash, ':pu' => $defaultHash]);
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
        return BackupService::getDbStats();
    }

    public static function restoreFromJson(array $data): bool {
        return BackupService::restoreFromJson($data);
    }

    public static function updateSettings(array $data): bool {
        $db = getDB();
        $fields = [
            'satker_kode', 'satker_nama', 'alamat', 'no_telp', 'email',
            'call_sign', 'frekuensi_default', 'default_theme', 'ui_theme', 'logo_url', 'gemini_api_key'
        ];

        $sets = [];
        $params = [':id' => 1];
        // Kolom yang ditambah lewat migrasi (mis. ui_theme) dilewati bila migrasi belum dijalankan, agar simpan tidak error 500
        $existing = array_flip($db->query("SHOW COLUMNS FROM AppSettings")->fetchAll(PDO::FETCH_COLUMN));

        foreach ($fields as $f) {
            if (array_key_exists($f, $data) && isset($existing[$f])) {
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
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
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

                AuditHelper::log('PROFILE_UPDATE', "Memperbarui profil administrator (" . ($data['nama'] ?? $adminUser) . ")", 'Users', 'USR-ADMIN');
            }

            if (!$inTx) $db->commit();
            return $res;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            error_log("[SettingsModel updateAdminProfile] " . $e->getMessage());
            return false;
        }
    }

    public static function updateUserProfile(array $data): bool {
        $db = getDB();
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
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

                AuditHelper::log('PROFILE_UPDATE', "Memperbarui profil petugas siaga (" . ($data['nama'] ?? $userUser) . ")", 'Users', 'USR-USER');
            }

            if (!$inTx) $db->commit();
            return $res;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            error_log("[SettingsModel updateUserProfile] " . $e->getMessage());
            return false;
        }
    }

    public static function resetUserToDefault(): bool {
        $db = getDB();
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $defaultHash = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT);
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

                AuditHelper::log('USER_RESET', "Mereset akun Petugas Siaga SAR ke pengaturan awal sistem", 'Users', 'USR-USER');
            }

            if (!$inTx) $db->commit();
            return $res;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            error_log("[SettingsModel resetUserToDefault] " . $e->getMessage());
            return false;
        }
    }

    public static function changePassword(string $role, string $oldPass, string $newPass): array {
        $db = getDB();
        $settings = self::getSettings();

        $roleUpper = strtoupper($role);
        $currPass = $roleUpper === 'ADMIN' ? ($settings['admin_password'] ?? '') : ($settings['user_password'] ?? '');

        if ($currPass === '' || !AuthHelper::verifyPassword($oldPass, (string)$currPass)) {
            return ['success' => false, 'error' => 'Kata sandi lama tidak cocok.'];
        }

        if (strlen($newPass) < 8) {
            return ['success' => false, 'error' => 'Kata sandi baru minimal 8 karakter demi standar keamanan akun.'];
        }

        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);

            if ($roleUpper === 'ADMIN') {
                $stmt = $db->prepare("UPDATE AppSettings SET admin_password = :p WHERE id = 1");
            } else {
                $stmt = $db->prepare("UPDATE AppSettings SET user_password = :p WHERE id = 1");
            }

            $res = $stmt->execute([':p' => $newHash]);

            if ($res) {
                UserModel::ensureTableExists();
                $usrId = ($roleUpper === 'ADMIN') ? 'USR-ADMIN' : 'USR-USER';
                $db->prepare("UPDATE `Users` SET password_hash = :p, updated_at = :now WHERE id = :id OR role = :role")->execute([
                    ':p' => $newHash,
                    ':now' => date('Y-m-d H:i:s'),
                    ':id' => $usrId,
                    ':role' => $roleUpper
                ]);

                AuditHelper::log('PASSWORD_CHANGE', "Mengubah kata sandi untuk otoritas {$roleUpper}", 'Users', $roleUpper);
            }

            if (!$inTx) $db->commit();
            return ['success' => true, 'message' => 'Kata sandi berhasil diperbarui dengan aman.'];
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            error_log("[SettingsModel changePassword] " . $e->getMessage());
            return ['success' => false, 'error' => 'Gagal memperbarui kata sandi: ' . $e->getMessage()];
        }
    }

    public static function resetToDefaultPassword(): bool {
        $db = getDB();
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $defaultHash = password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE AppSettings SET admin_password = :pa, user_password = :pu WHERE id = 1");
            $res = $stmt->execute([':pa' => $defaultHash, ':pu' => $defaultHash]);

            if ($res) {
                UserModel::ensureTableExists();
                $db->prepare("UPDATE `Users` SET password_hash = :p, updated_at = :now")->execute([
                    ':p' => $defaultHash,
                    ':now' => date('Y-m-d H:i:s')
                ]);

                AuditHelper::log('PASSWORD_RESET', "Mereset semua kata sandi pengguna ke bawaan sistem", 'Users', 'ALL');
            }

            if (!$inTx) $db->commit();
            return $res;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            error_log("[SettingsModel resetToDefaultPassword] " . $e->getMessage());
            return false;
        }
    }

    public static function exportJsonBackup(): array {
        return BackupService::exportJsonBackup();
    }

    public static function generateSQLDump(): string {
        return BackupService::generateSQLDump();
    }

    public static function splitSqlStatements(string $sql): array {
        return BackupService::splitSqlStatements($sql);
    }

    public static function restoreFromSQL(string $sql): bool {
        return BackupService::restoreFromSQL($sql);
    }
}
