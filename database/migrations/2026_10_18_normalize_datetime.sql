-- ==============================================================================
-- QR-SAR Migration: Normalisasi Kolom Waktu & Tanggal ke Format Standar DATETIME
-- KPP Kendari - Basarnas
-- Issue ID: ISSUE-LAPAWAL-03
-- ==============================================================================

-- 1. Normalisasi Data String ISO / T-delimited pada Tabel Operasi
UPDATE `Operasi`
SET `waktu_kejadian` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_kejadian` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_kejadian` IS NOT NULL AND CAST(`waktu_kejadian` AS CHAR) != '';

UPDATE `Operasi`
SET `waktu_lapor` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_lapor` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_lapor` IS NOT NULL AND CAST(`waktu_lapor` AS CHAR) != '';

UPDATE `Operasi`
SET `waktu_berangkat` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_berangkat` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_berangkat` IS NOT NULL AND CAST(`waktu_berangkat` AS CHAR) != '';

UPDATE `Operasi`
SET `eta_tiba` = STR_TO_DATE(LEFT(REPLACE(CAST(`eta_tiba` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `eta_tiba` IS NOT NULL AND CAST(`eta_tiba` AS CHAR) != '';

UPDATE `Operasi`
SET `waktu_selesai` = NULL
WHERE `waktu_selesai` IS NOT NULL AND TRIM(CAST(`waktu_selesai` AS CHAR)) IN ('', '0000-00-00 00:00:00', 'null', 'NULL');

UPDATE `Operasi`
SET `waktu_selesai` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_selesai` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_selesai` IS NOT NULL AND CAST(`waktu_selesai` AS CHAR) != '';

UPDATE `Operasi`
SET `created_at` = STR_TO_DATE(LEFT(REPLACE(CAST(`created_at` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `created_at` IS NOT NULL AND CAST(`created_at` AS CHAR) != '';

-- 2. Modifikasi Tipe Kolom Menjadi DATETIME
ALTER TABLE `Operasi`
  MODIFY COLUMN `waktu_kejadian` DATETIME NOT NULL,
  MODIFY COLUMN `waktu_lapor` DATETIME NOT NULL,
  MODIFY COLUMN `waktu_berangkat` DATETIME NOT NULL,
  MODIFY COLUMN `eta_tiba` DATETIME NOT NULL,
  MODIFY COLUMN `waktu_selesai` DATETIME NULL DEFAULT NULL,
  MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 3. Optimasi Indeks Temporal
-- Pastikan indeks idx_ops_waktu menggunakan kolom DATETIME yang baru
DROP INDEX `idx_ops_waktu` ON `Operasi`;
CREATE INDEX `idx_ops_waktu` ON `Operasi` (`waktu_kejadian`);
CREATE INDEX `idx_ops_created_at` ON `Operasi` (`created_at`);

-- 4. Normalisasi dan Modifikasi Tipe Kolom Tabel Renops (ISSUE-RENOPS-04)
UPDATE `Renops`
SET `tanggal_laporan` = LEFT(TRIM(CAST(`tanggal_laporan` AS CHAR)), 10)
WHERE `tanggal_laporan` IS NOT NULL AND CAST(`tanggal_laporan` AS CHAR) != '';

UPDATE `Renops`
SET `waktu_briefing` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_briefing` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_briefing` IS NOT NULL AND CAST(`waktu_briefing` AS CHAR) != '';

UPDATE `Renops`
SET `waktu_mulai_ops` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_mulai_ops` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_mulai_ops` IS NOT NULL AND CAST(`waktu_mulai_ops` AS CHAR) != '';

UPDATE `Renops`
SET `created_at` = STR_TO_DATE(LEFT(REPLACE(CAST(`created_at` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `created_at` IS NOT NULL AND CAST(`created_at` AS CHAR) != '';

ALTER TABLE `Renops`
  MODIFY COLUMN `tanggal_laporan` DATE NOT NULL,
  MODIFY COLUMN `waktu_briefing` DATETIME NOT NULL,
  MODIFY COLUMN `waktu_mulai_ops` DATETIME NOT NULL,
  MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- 5. Normalisasi dan Modifikasi Tipe Kolom Tabel LaporanHarian (ISSUE-RENOPS-04)
UPDATE `LaporanHarian`
SET `waktu_debrief` = NULL
WHERE `waktu_debrief` IS NOT NULL AND TRIM(CAST(`waktu_debrief` AS CHAR)) IN ('', '0000-00-00 00:00:00', 'null', 'NULL');

UPDATE `LaporanHarian`
SET `waktu_debrief` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_debrief` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_debrief` IS NOT NULL AND CAST(`waktu_debrief` AS CHAR) != '';

UPDATE `LaporanHarian`
SET `waktu_laporan` = STR_TO_DATE(LEFT(REPLACE(CAST(`waktu_laporan` AS CHAR), 'T', ' '), 19), '%Y-%m-%d %H:%i:%s')
WHERE `waktu_laporan` IS NOT NULL AND CAST(`waktu_laporan` AS CHAR) != '';

ALTER TABLE `LaporanHarian`
  MODIFY COLUMN `waktu_debrief` DATETIME NULL DEFAULT NULL,
  MODIFY COLUMN `waktu_laporan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;
