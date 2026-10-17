<?php
/**
 * QR-SAR - Master Data Model (Alut & Tim + Backward-compatible Facade for SarUnit & Pejabat)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class MasterModel {
    // --- Master Alut ---
    public static function getAlut(): array {
        $db = getDB();
        return $db->query("SELECT * FROM MasterAlut ORDER BY id_alut ASC")->fetchAll();
    }

    public static function createAlut(string|array $nama, string $satuan = 'Unit'): int {
        $db = getDB();
        if (is_array($nama)) {
            $satuan = (string)($nama['satuan'] ?? 'Unit');
            $nama = (string)($nama['nama_alut'] ?? '');
        }
        $stmt = $db->prepare("INSERT INTO MasterAlut (nama_alut, satuan) VALUES (:nama, :satuan)");
        $stmt->execute([':nama' => trim($nama), ':satuan' => trim($satuan) ?: 'Unit']);
        return (int)$db->lastInsertId();
    }

    public static function updateAlut(int $id, string|array $nama, string $satuan = 'Unit'): bool {
        $db = getDB();
        if (is_array($nama)) {
            $satuan = (string)($nama['satuan'] ?? 'Unit');
            $nama = (string)($nama['nama_alut'] ?? '');
        }
        $stmt = $db->prepare("UPDATE MasterAlut SET nama_alut = :nama, satuan = :satuan WHERE id_alut = :id");
        return $stmt->execute([':nama' => trim($nama), ':satuan' => trim($satuan) ?: 'Unit', ':id' => $id]);
    }

    public static function deleteAlut(int $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM MasterAlut WHERE id_alut = :id");
        return $stmt->execute([':id' => $id]);
    }

    // --- Master Tim ---
    public static function getTim(): array {
        $db = getDB();
        return $db->query("SELECT * FROM MasterTim ORDER BY id_tim ASC")->fetchAll();
    }

    public static function createTim(string|array $nama): int {
        $db = getDB();
        if (is_array($nama)) {
            $nama = (string)($nama['nama_tim'] ?? '');
        }
        $stmt = $db->prepare("INSERT INTO MasterTim (nama_tim) VALUES (:nama)");
        $stmt->execute([':nama' => trim($nama)]);
        return (int)$db->lastInsertId();
    }

    public static function updateTim(int $id, string|array $nama): bool {
        $db = getDB();
        if (is_array($nama)) {
            $nama = (string)($nama['nama_tim'] ?? '');
        }
        $stmt = $db->prepare("UPDATE MasterTim SET nama_tim = :nama WHERE id_tim = :id");
        return $stmt->execute([':nama' => trim($nama), ':id' => $id]);
    }

    public static function deleteTim(int $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM MasterTim WHERE id_tim = :id");
        return $stmt->execute([':id' => $id]);
    }

    // --- Satuan SAR (Delegated to SarUnitModel) ---
    public static function getSarUnits(): array {
        return SarUnitModel::getAll();
    }

    public static function createSarUnit(array $data): int {
        return SarUnitModel::create($data);
    }

    public static function updateSarUnit(int $id, array $data): bool {
        return SarUnitModel::update($id, $data);
    }

    public static function deleteSarUnit(int $id): bool {
        return SarUnitModel::delete($id);
    }

    public static function setDefaultSarUnit(int $id): bool {
        return SarUnitModel::setDefault($id);
    }

    // --- Pejabat / SMC (Delegated to PejabatModel) ---
    public static function getPejabat(): array {
        return PejabatModel::getAll();
    }

    public static function getDefaultSmc(): ?array {
        return PejabatModel::getDefaultSmc();
    }

    public static function createPejabat(array $data): string {
        return PejabatModel::create($data);
    }

    public static function updatePejabat(string $id, array $data): bool {
        return PejabatModel::update($id, $data);
    }

    public static function deletePejabat(string $id): bool {
        return PejabatModel::delete($id);
    }

    public static function setDefaultSmc(string $id): bool {
        return PejabatModel::setDefaultSmc($id);
    }
}
