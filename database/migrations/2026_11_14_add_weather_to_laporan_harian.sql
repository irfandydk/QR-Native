-- ==============================================================================
-- QR-SAR Database Migration: Tambah Kolom Gelombang & Angin pada LaporanHarian
-- Menyelaraskan struktur kondisi cuaca LAPBANG / Penutupan dengan Laporan Awal.
-- ==============================================================================

ALTER TABLE `LaporanHarian`
  ADD COLUMN `gelombang` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `cuaca`,
  ADD COLUMN `angin` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL AFTER `gelombang`;
