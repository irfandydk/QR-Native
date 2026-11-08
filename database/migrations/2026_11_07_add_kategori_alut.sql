-- ==============================================================================
-- QR-SAR Database Migration: Kategori Alut & Palsar
-- ALUT_DARAT | ALUT_LAUT | ALUT_UDARA | PALSAR. Master wajib berkategori; item laporan menyimpan
-- salinan kategori (DetailAlut.kategori; kunci "kategori" di JSON Renops/LaporanHarian.detail_alut).
-- Item laporan lama diisi sekali oleh AlutKategori::backfill() (database/migrate.php).
-- ==============================================================================

ALTER TABLE `MasterAlut` ADD COLUMN `kategori` VARCHAR(20) NOT NULL DEFAULT 'PALSAR' AFTER `satuan`;
ALTER TABLE `DetailAlut` ADD COLUMN `kategori` VARCHAR(20) NULL DEFAULT NULL AFTER `satuan`;

UPDATE `MasterAlut` SET `kategori` = 'ALUT_DARAT' WHERE LOWER(TRIM(`nama_alut`)) IN ('rescue car', 'rescue carrier');
UPDATE `MasterAlut` SET `kategori` = 'ALUT_LAUT' WHERE LOWER(TRIM(`nama_alut`)) IN ('rescue boat 210', 'rescue boat 307', 'rigid inflatable boat (rib)', 'rubber boat', 'longboat masyarakat', 'kn sar pacitan');
UPDATE `MasterAlut` SET `kategori` = 'ALUT_UDARA' WHERE LOWER(TRIM(`nama_alut`)) IN ('drone thermal');
UPDATE `MasterAlut` SET `kategori` = 'PALSAR' WHERE LOWER(TRIM(`nama_alut`)) IN ('aqua eye', 'palsar medis', 'palsar evakuasi', 'palsar komunikasi', 'palsar selam', 'palsar mounteneering', 'kantong jenazah');
