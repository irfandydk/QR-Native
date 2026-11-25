-- QR-SAR Migration: Add avatar_url column to Users table for Admin & User profile photos

ALTER TABLE `Users`
  ADD COLUMN `avatar_url` VARCHAR(255) NOT NULL DEFAULT '' AFTER `no_hp`;
