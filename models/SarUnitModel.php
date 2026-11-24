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
}
