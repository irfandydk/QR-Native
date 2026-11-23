-- ==============================================================================
-- QR-SAR Database Migration: Logo ketiga (ANOA Rescue / Dokumentasi Operasi SAR)
-- Kosong = logo bawaan assets/images/logo_anoa_rescue.png.
-- ==============================================================================

ALTER TABLE `AppSettings` ADD COLUMN `logo_url_3` LONGTEXT NULL DEFAULT NULL AFTER `logo_url_2`;
