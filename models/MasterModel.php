<?php
/**
 * QR-SAR - Master Data Model (Alut, Tim, SarUnit, Pejabat)
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

    // --- Satuan SAR (Kansar, Pos SAR, Unit Siaga, KN SAR) ---
    public static function getSarUnits(): array {
        $db = getDB();
        return $db->query("SELECT * FROM SarUnit ORDER BY is_default DESC, id_unit ASC")->fetchAll();
    }

    public static function createSarUnit(array $data): int {
        $db = getDB();
        $lat = (float)($data['latitude'] ?? 0);
        $lng = (float)($data['longitude'] ?? 0);
        $dms = !empty($data['koordinat_dms']) ? $data['koordinat_dms'] : GeoHelper::decimalToDms($lat, $lng);

        $stmt = $db->prepare("INSERT INTO SarUnit (
            nama_unit, jenis_unit, wilayah, latitude, longitude, koordinat_dms, alamat, no_telp, is_default
        ) VALUES (
            :nama, :jenis, :wilayah, :lat, :lng, :dms, :alamat, :no_telp, :is_default
        )");

        $stmt->execute([
            ':nama' => $data['nama_unit'],
            ':jenis' => $data['jenis_unit'] ?? 'POS_SAR',
            ':wilayah' => $data['wilayah'] ?? '',
            ':lat' => $lat,
            ':lng' => $lng,
            ':dms' => $dms,
            ':alamat' => $data['alamat'] ?? '',
            ':no_telp' => $data['no_telp'] ?? '',
            ':is_default' => !empty($data['is_default']) ? 1 : 0
        ]);

        return (int)$db->lastInsertId();
    }

    public static function updateSarUnit(int $id, array $data): bool {
        $db = getDB();
        $lat = (float)($data['latitude'] ?? 0);
        $lng = (float)($data['longitude'] ?? 0);
        $dms = !empty($data['koordinat_dms']) ? $data['koordinat_dms'] : GeoHelper::decimalToDms($lat, $lng);

        $stmt = $db->prepare("UPDATE SarUnit SET
            nama_unit = :nama,
            jenis_unit = :jenis,
            wilayah = :wilayah,
            latitude = :lat,
            longitude = :lng,
            koordinat_dms = :dms,
            alamat = :alamat,
            no_telp = :no_telp
            WHERE id_unit = :id");

        return $stmt->execute([
            ':nama' => $data['nama_unit'],
            ':jenis' => $data['jenis_unit'] ?? 'POS_SAR',
            ':wilayah' => $data['wilayah'] ?? '',
            ':lat' => $lat,
            ':lng' => $lng,
            ':dms' => $dms,
            ':alamat' => $data['alamat'] ?? '',
            ':no_telp' => $data['no_telp'] ?? '',
            ':id' => $id
        ]);
    }

    public static function deleteSarUnit(int $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM SarUnit WHERE id_unit = :id");
        return $stmt->execute([':id' => $id]);
    }

    public static function setDefaultSarUnit(int $id): bool {
        $db = getDB();
        $db->query("UPDATE SarUnit SET is_default = 0");
        $stmt = $db->prepare("UPDATE SarUnit SET is_default = 1 WHERE id_unit = :id");
        return $stmt->execute([':id' => $id]);
    }

    // --- Pejabat / SMC ---
    public static function getPejabat(): array {
        $db = getDB();
        return $db->query("SELECT * FROM Pejabat ORDER BY is_default_smc DESC, nama ASC")->fetchAll();
    }

    public static function getDefaultSmc(): ?array {
        $db = getDB();
        $stmt = $db->query("SELECT * FROM Pejabat WHERE is_default_smc = 1 LIMIT 1");
        $row = $stmt->fetch();
        if ($row) return $row;

        // Fallback to first
        $stmt2 = $db->query("SELECT * FROM Pejabat LIMIT 1");
        return $stmt2->fetch() ?: null;
    }

    public static function createPejabat(array $data): string {
        $db = getDB();
        $id = $data['id'] ?? ('pej-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));

        $stmt = $db->prepare("INSERT INTO Pejabat (id, nama, nip, pangkat, jabatan, is_default_smc) VALUES (:id, :nama, :nip, :pangkat, :jabatan, :def)");
        $stmt->execute([
            ':id' => $id,
            ':nama' => $data['nama'],
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':def' => !empty($data['is_default_smc']) ? 1 : 0
        ]);

        return $id;
    }

    public static function updatePejabat(string $id, array $data): bool {
        $db = getDB();
        $isDef = !empty($data['is_default_smc']) ? 1 : 0;
        if ($isDef === 1) {
            $db->query("UPDATE Pejabat SET is_default_smc = 0");
        }
        $stmt = $db->prepare("UPDATE Pejabat SET nama = :nama, nip = :nip, pangkat = :pangkat, jabatan = :jabatan, is_default_smc = :def WHERE id = :id");
        return $stmt->execute([
            ':nama' => $data['nama'],
            ':nip' => $data['nip'] ?? '',
            ':pangkat' => $data['pangkat'] ?? '',
            ':jabatan' => $data['jabatan'] ?? '',
            ':def' => $isDef,
            ':id' => $id
        ]);
    }

    public static function deletePejabat(string $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM Pejabat WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public static function setDefaultSmc(string $id): bool {
        $db = getDB();
        $db->query("UPDATE Pejabat SET is_default_smc = 0");
        $stmt = $db->prepare("UPDATE Pejabat SET is_default_smc = 1 WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }
}
