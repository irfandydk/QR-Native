-- ==============================================================================
-- QR-SAR Database Migration: Pilihan Tema Tampilan Global
-- 'klasik' = tampilan standar, 'minimal' = tema Minimal Glass. Dipilih admin di Pengaturan Satker.
-- ==============================================================================

ALTER TABLE `AppSettings` ADD COLUMN `ui_theme` VARCHAR(20) NOT NULL DEFAULT 'klasik';
