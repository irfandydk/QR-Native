-- ==============================================================================
-- QR-SAR Database Migration: Tambah Kolom Gelombang & Angin pada Renops
-- Menyelaraskan struktur kondisi cuaca RENOPS dengan Laporan Awal & LAPBANG.
-- ==============================================================================

ALTER TABLE `Renops`
  ADD COLUMN `gelombang` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `cuaca`,
  ADD COLUMN `angin` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `gelombang`;
