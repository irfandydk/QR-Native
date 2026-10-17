-- Migrasi Standarisasi Kategori BRA SAR Basarnas KPP Kendari
-- Mengubah seluruh kategori 'SAR_UMUM' menjadi standar resmi tunggal 'OPERASI_SAR'

UPDATE `BraSar` 
SET `kategori` = 'OPERASI_SAR' 
WHERE `kategori` = 'SAR_UMUM';
