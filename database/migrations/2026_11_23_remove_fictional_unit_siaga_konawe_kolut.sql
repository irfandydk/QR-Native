-- ==============================================================================
-- QR-SAR Database Migration: Hapus permanen 2 titik fiktif yang tidak ada di
-- wilayah kerja KPP Kelas A Kendari:
-- 1. Unit Siaga SAR Konawe (pt-unit-konawe / SarUnit id 7)
-- 2. Unit Siaga SAR Kolaka Utara (pt-unit-kolut / SarUnit id 8)
-- Pos SAR Konawe Utara (pt-pos-konut / SarUnit id 5) tetap dipertahankan.
-- ==============================================================================

DELETE FROM `GisPoint`
WHERE `id` IN ('pt-unit-konawe', 'pt-unit-kolut')
   OR (`id_operasi` IS NULL AND UPPER(TRIM(`label`)) IN ('UNIT SIAGA SAR KONAWE', 'UNIT SIAGA SAR KOLAKA UTARA'));

DELETE FROM `SarUnit`
WHERE `id_unit` IN (7, 8)
   OR UPPER(TRIM(`nama_unit`)) IN ('UNIT SIAGA SAR KONAWE', 'UNIT SIAGA SAR KOLAKA UTARA');
