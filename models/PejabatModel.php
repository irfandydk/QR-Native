<?php
/**
 * QR-SAR - Pejabat & SMC (Search Mission Coordinator) Model
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class PejabatModel {
    /**
     * Get all officials ordered by default SMC first, then name
     */
    public static function getAll(): array {
        $db = getDB();
        return $db->query("SELECT * FROM Pejabat ORDER BY is_default_smc DESC, nama ASC")->fetchAll();
    }

    /**
     * Alias for backward compatibility
     */
    public static function getPejabat(): array {
        return self::getAll();
    }

    /**
     * Get single official by ID
     */
    public static function getById(string $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM Pejabat WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Get default SMC (or fallback to first official)
     */
    public static function getDefaultSmc(): ?array {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM Pejabat WHERE is_default_smc = 1 LIMIT 1");
        $row = $stmt->fetch();
        if ($row) return $row;

        // Fallback to first row
        $stmt2 = $db->query("SELECT * FROM Pejabat LIMIT 1");
        return $stmt2->fetch() ?: null;
    }

    /**
     * Create new official
     */
    public static function create(array $data): string {
        $db = getDB();
        $id = $data['id'] ?? ('pej-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
        $isDef = !empty($data['is_default_smc']) ? 1 : 0;
        if ($isDef === 1) {
            $db->query("UPDATE Pejabat SET is_default_smc = 0");
        }

        $stmt = $db->prepare("INSERT INTO Pejabat (id, nama, nip, pangkat, jabatan, is_default_smc) VALUES (:id, :nama, :nip, :pangkat, :jabatan, :def)");
        $stmt->execute([
            ':id' => $id,
            ':nama' => $data['nama'],
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':def' => $isDef
        ]);
        if (class_exists('SettingsModel')) {
            SettingsModel::clearCache();
        }

        return $id;
    }

    /**
     * Update existing official
     */
    public static function update(string $id, array $data): bool {
        $db = getDB();
        $isDef = !empty($data['is_default_smc']) ? 1 : 0;
        if ($isDef === 1) {
            $db->query("UPDATE Pejabat SET is_default_smc = 0");
        }
        $stmt = $db->prepare("UPDATE Pejabat SET nama = :nama, nip = :nip, pangkat = :pangkat, jabatan = :jabatan, is_default_smc = :def WHERE id = :id");
        $ok = $stmt->execute([
            ':nama' => $data['nama'],
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':def' => $isDef,
            ':id' => $id
        ]);
        if (class_exists('SettingsModel')) {
            SettingsModel::clearCache();
        }
        return $ok;
    }

    /**
     * Delete official
     */
    public static function delete(string $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM Pejabat WHERE id = :id");
        $ok = $stmt->execute([':id' => $id]);
        if (class_exists('SettingsModel')) {
            SettingsModel::clearCache();
        }
        return $ok;
    }

    /**
     * Set official as default SMC
     */
    public static function setDefaultSmc(string $id): bool {
        $db = getDB();
        $db->query("UPDATE Pejabat SET is_default_smc = 0");
        $stmt = $db->prepare("UPDATE Pejabat SET is_default_smc = 1 WHERE id = :id");
        $ok = $stmt->execute([':id' => $id]);
        if (class_exists('SettingsModel')) {
            SettingsModel::clearCache();
        }
        return $ok;
    }
}
