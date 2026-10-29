-- ==============================================================================
-- QR-SAR Database Migration: Token QR Dokumen Operasi Tersimpan
-- Token disimpan saat dokumen pertama kali dicetak, sehingga Barcode QR tetap sah
-- walau pejabat SMC default atau nama operasi berubah di kemudian hari.
-- ==============================================================================

CREATE TABLE IF NOT EXISTS `DokumenToken` (
  `doc_type` VARCHAR(20) NOT NULL,
  `ref_id` INT NOT NULL,
  `id_operasi` INT NOT NULL,
  `token` CHAR(16) NOT NULL,
  `issued_at` DATETIME NOT NULL,
  PRIMARY KEY (`doc_type`, `ref_id`),
  KEY `idx_doktoken_ops` (`id_operasi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
