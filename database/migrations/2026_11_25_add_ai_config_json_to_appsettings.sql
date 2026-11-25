-- QR-SAR Migration: Multi-Provider AI API Keys & Model Configuration
-- Adds ai_config_json LONGTEXT column to AppSettings for storing multiple AI API keys (Gemini, DeepSeek, etc.), default selection, and auto-fallback settings.

ALTER TABLE AppSettings
  ADD COLUMN ai_config_json LONGTEXT NULL DEFAULT NULL AFTER gemini_api_key;
