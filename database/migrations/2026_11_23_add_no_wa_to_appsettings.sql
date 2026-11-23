-- ==============================================================================
-- QR-SAR Database Migration: Tambah Nomor WhatsApp (no_wa) pada AppSettings
-- Dipakai pada Pengaturan Satker dan Kop Kolase Dokumentasi (115 / Telp / WA).
-- ==============================================================================

ALTER TABLE `AppSettings` ADD COLUMN `no_wa` VARCHAR(64) NULL DEFAULT NULL AFTER `no_telp`;
