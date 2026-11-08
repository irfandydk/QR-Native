-- ==============================================================================
-- QR-SAR Database Migration: Dokumen Rencana Operasi SAR berversi (DRAF/TERBIT/DIGANTIKAN)
-- DRAF (versi 0) hanya menyimpan suntingan; TERBIT menyimpan salinan beku + token QR.
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `RenopsDokumen` (
  `id_dokumen` INT NOT NULL AUTO_INCREMENT,
  `id_renops` INT NOT NULL,
  `id_operasi` INT NOT NULL,
  `versi` INT NOT NULL DEFAULT 0,
  `status` VARCHAR(12) NOT NULL,
  `rev` INT NOT NULL DEFAULT 0,
  `konten_json` LONGTEXT NOT NULL,
  `content_hash` CHAR(64) NULL,
  `token` CHAR(16) NULL,
  `issued_at` DATETIME NULL,
  `issued_by` VARCHAR(50) NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `draf_lock` TINYINT GENERATED ALWAYS AS (IF(`status` = 'DRAF', 1, NULL)) STORED,
  `terbit_lock` TINYINT GENERATED ALWAYS AS (IF(`status` = 'TERBIT', 1, NULL)) STORED,
  PRIMARY KEY (`id_dokumen`),
  UNIQUE KEY `uq_renopsdok_token` (`token`),
  KEY `idx_renopsdok_status` (`id_renops`, `status`),
  KEY `idx_renopsdok_ops` (`id_operasi`),
  UNIQUE KEY `uq_renopsdok_draf` (`id_renops`, `draf_lock`),
  UNIQUE KEY `uq_renopsdok_terbit` (`id_renops`, `terbit_lock`),
  CONSTRAINT `fk_renopsdok_renops` FOREIGN KEY (`id_renops`) REFERENCES `Renops` (`id_renops`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `RenopsFoto` (
  `id_foto` INT NOT NULL AUTO_INCREMENT,
  `id_renops` INT NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `keterangan` VARCHAR(255) NOT NULL DEFAULT '',
  `urutan` INT NOT NULL DEFAULT 0,
  `uploaded_by` VARCHAR(50) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_foto`),
  KEY `idx_renopsfoto_renops` (`id_renops`),
  CONSTRAINT `fk_renopsfoto_renops` FOREIGN KEY (`id_renops`) REFERENCES `Renops` (`id_renops`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
