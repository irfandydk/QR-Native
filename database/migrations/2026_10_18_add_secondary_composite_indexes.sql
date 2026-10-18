-- ====================================================================
-- QR-SAR Database Migration: Secondary Composite Indexes
-- Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
-- ====================================================================

-- 1. Indeks Komposit BraSar: Mempercepat filter kategori & pengurutan id_bra
ALTER TABLE BraSar ADD INDEX idx_bra_kategori_id (kategori, id_bra);

-- 2. Indeks Komposit BraSection: Mempercepat lookup seksi per naskah BRA & kode
ALTER TABLE BraSection ADD INDEX idx_brasec_bra_kode (id_bra, kode);

-- 3. Indeks Komposit GisPoint: Mempercepat filtering titik per operasi dan kategori pangkalan
ALTER TABLE GisPoint ADD INDEX idx_point_ops_cat (id_operasi, category);
