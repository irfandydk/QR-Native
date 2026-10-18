-- ====================================================================
-- QR-SAR Database Migration: Composite Indexes for High Performance
-- Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
-- ====================================================================

-- 1. Indeks Komposit Korban: Mempercepat agregasi status korban per operasi
ALTER TABLE Korban ADD INDEX idx_korban_ops_status (id_operasi, status_korban);

-- 2. Indeks Komposit Operasi: Mempercepat filter timeline status operasi terbuka/tertutup
ALTER TABLE Operasi ADD INDEX idx_ops_status_selesai (status, waktu_selesai);

-- 3. Indeks Komposit Renops: Mempercepat sorting & lookup Rencana Operasi per hari
ALTER TABLE Renops ADD INDEX idx_renops_ops_hari (id_operasi, hari_ke);

-- 4. Indeks Komposit LaporanHarian: Mempercepat lookup Laporan Perkembangan per hari
ALTER TABLE LaporanHarian ADD INDEX idx_lap_ops_hari (id_operasi, hari_ke);
