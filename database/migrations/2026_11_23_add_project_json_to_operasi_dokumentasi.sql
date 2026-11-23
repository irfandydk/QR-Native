-- ==============================================================================
-- QR-SAR Database Migration: Tambah kolom project_json pada OperasiDokumentasi
-- Mendukung fungsi Edit penuh pada arsip kolase dokumentasi yang telah tersimpan
-- ==============================================================================

ALTER TABLE `OperasiDokumentasi`
    ADD COLUMN `project_json` LONGTEXT NULL DEFAULT NULL AFTER `file_size`;
