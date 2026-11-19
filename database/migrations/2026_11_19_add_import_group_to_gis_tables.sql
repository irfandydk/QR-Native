-- ==============================================================================
-- QR-SAR Database Migration: Tambah Kolom import_group pada Tabel Spasial GIS
-- Mengelompokkan entitas hasil impor KML/KMZ per batch (Import 1, Import 2, dst.)
-- serta mendukung fitur Rename Kategori Import di Titik, Search Area, Pola SAR & Rute.
-- ==============================================================================

ALTER TABLE `GisPoint`
  ADD COLUMN `import_group` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

ALTER TABLE `GisPolygon`
  ADD COLUMN `import_group` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

ALTER TABLE `GisSearchPattern`
  ADD COLUMN `import_group` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;

ALTER TABLE `GisRoute`
  ADD COLUMN `import_group` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL;
