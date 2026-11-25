-- QR-SAR Migration: Allow nullable waktu_briefing in Renops (RENOPS H.1 has no briefing time)
-- Date: 2026-11-25
-- Purpose: Support RENOPS Hari ke-1 without briefing time, using departure time from Laporan Awal as start time.

ALTER TABLE `Renops`
    MODIFY COLUMN `waktu_briefing` DATETIME NULL DEFAULT NULL;
