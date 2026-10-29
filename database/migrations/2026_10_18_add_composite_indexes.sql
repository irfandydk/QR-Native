-- ==============================================================================
-- QR-SAR Database Migration: Add Composite Indexes for High Performance
-- Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
-- ==============================================================================

-- 1. Index for Korban table by operation and victim status
ALTER TABLE `Korban` ADD INDEX `idx_korban_ops_status` (`id_operasi`, `status_korban`);

-- 2. Index for Operasi table by operation status and finish time
ALTER TABLE `Operasi` ADD INDEX `idx_ops_status_selesai` (`status`, `waktu_selesai`);

-- 3. Index for Renops table by operation and day
ALTER TABLE `Renops` ADD INDEX `idx_renops_ops_hari` (`id_operasi`, `hari_ke`);

-- 4. Index for LaporanHarian (Sitrep) table by operation and day
ALTER TABLE `LaporanHarian` ADD INDEX `idx_lap_ops_hari` (`id_operasi`, `hari_ke`);
