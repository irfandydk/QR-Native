-- QR-SAR Migration: Allow nullable id_operasi in OperasiDokumentasi for General Documentation (/dokumentasi.php)
-- Date: 2026-11-24
-- Purpose: Support unified chronological archiving of both General Documentation (id_operasi IS NULL)
--          and SAR Operation Documentation (id_operasi > 0) within OperasiDokumentasi.

ALTER TABLE `OperasiDokumentasi`
    MODIFY COLUMN `id_operasi` INT NULL DEFAULT NULL;
