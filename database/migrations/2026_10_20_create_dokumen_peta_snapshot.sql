-- ==========================================
-- MIGRATION: Create DokumenPetaSnapshot Table
-- Date: 2026-10-20
-- Purpose: Menyimpan tangkapan layar (snapshot) peta taktis 16:9 beserta metadata komponen
--          (LKP, Search Area, Pola Pencarian, Jalur, dan Titik Penemuan Korban)
--          untuk disematkan pada cetakan dokumen resmi (Laporan Awal, RENOPS, SITREP, Penutupan).
-- ==========================================

CREATE TABLE IF NOT EXISTS `DokumenPetaSnapshot` (
    `id_snapshot` INT AUTO_INCREMENT PRIMARY KEY,
    `id_operasi` INT NOT NULL,
    `jenis_dokumen` VARCHAR(30) NOT NULL DEFAULT 'LAPORAN_AWAL',
    `id_referensi` INT NULL DEFAULT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `bounds_json` TEXT NULL,
    `komponen_json` LONGTEXT NULL,
    `keterangan_tambahan` TEXT NULL,
    `captured_by` INT NULL DEFAULT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_ops_dok_ref` (`id_operasi`, `jenis_dokumen`, `id_referensi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
