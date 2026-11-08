-- ==============================================================================
-- QR-SAR Database Migration: Logo kedua kop dokumen (kanan), mis. logo SAR Nasional.
-- Dipakai kop Dokumen Rencana Operasi SAR; kosong = logo bawaan assets/images/logo_sar_nasional.jpg.
-- ==============================================================================

ALTER TABLE `AppSettings` ADD COLUMN `logo_url_2` LONGTEXT NULL DEFAULT NULL AFTER `logo_url`;
