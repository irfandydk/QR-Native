-- Migration: Add satuan_jarak column to Renops table
-- KPP Kendari - Basarnas QR-SAR

ALTER TABLE `Renops` ADD COLUMN `satuan_jarak` VARCHAR(20) NULL DEFAULT 'NM' AFTER `cuaca`;
