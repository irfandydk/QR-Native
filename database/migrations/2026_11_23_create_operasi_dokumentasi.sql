-- ==============================================================================
-- QR-SAR Database Migration: Arsip Kolase Dokumentasi Operasi SAR (Foto & Video)
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `OperasiDokumentasi` (
    `id_dokumentasi` INT AUTO_INCREMENT PRIMARY KEY,
    `id_operasi` INT NOT NULL,
    `judul` VARCHAR(255) NOT NULL,
    `hari_ke` VARCHAR(32) NOT NULL DEFAULT 'H1',
    `tanggal_label` VARCHAR(100) NOT NULL DEFAULT '',
    `media_type` ENUM('image', 'video') NOT NULL DEFAULT 'image',
    `mime_type` VARCHAR(64) NOT NULL DEFAULT 'image/jpeg',
    `aspect_ratio` VARCHAR(24) NOT NULL DEFAULT '2:3',
    `orientation` ENUM('vertical', 'horizontal', 'square') NOT NULL DEFAULT 'vertical',
    `slot_count` TINYINT UNSIGNED NOT NULL DEFAULT 6,
    `layout_preset` VARCHAR(64) NOT NULL DEFAULT 'v_6_2x3',
    `width_px` SMALLINT UNSIGNED NOT NULL DEFAULT 1080,
    `height_px` SMALLINT UNSIGNED NOT NULL DEFAULT 1620,
    `file_path` VARCHAR(255) NOT NULL,
    `thumb_path` VARCHAR(255) NULL DEFAULT NULL,
    `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_by` VARCHAR(64) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_ops_dok_operasi` (`id_operasi`, `created_at`),
    CONSTRAINT `fk_ops_dok_operasi` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi`(`id_operasi`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
