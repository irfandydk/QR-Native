<?php
/**
 * QR-SAR - SarUnit Model (Satuan SAR / Pos SAR / Unit Siaga SAR)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class SarUnitModel {
    /**
     * Get all SAR units ordered by default unit first, then ID
     * Mengutamakan data master pangkalan SAR (Kantor SAR, POS SAR, Unit Siaga SAR)
     * yang diinput pada GIS / Plot Peta sebagai sumber data terverifikasi dan akurat.
     */
    public static function getAll(): array {
        if (class_exists('GisModel')) {
            $gisBases = GisModel::getMasterBases();
            if (!empty($gisBases)) {
                return $gisBases;
            }
        }
        $db = getDB();
        return $db->query("SELECT * FROM SarUnit ORDER BY is_default DESC, id_unit ASC")->fetchAll();
    }

    /**
     * Create new SAR unit
     */
    public static function create(array $data): int {
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

    /**
     * Update existing SAR unit
     */
    public static function update(int $id, array $data): bool {
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

    /**
     * Delete SAR unit
     */
    public static function delete(int $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM SarUnit WHERE id_unit = :id");
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Set a unit as the default command base
     */
    public static function setDefault(int $id): bool {
        $db = getDB();
        $db->query("UPDATE SarUnit SET is_default = 0");
        $stmt = $db->prepare("UPDATE SarUnit SET is_default = 1 WHERE id_unit = :id");
        return $stmt->execute([':id' => $id]);
    }
}
