<?php
/**
 * QR-SAR - Application Settings & Backup API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class SettingsApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Get Settings (Public / Authenticated / Admin filtered)
        if ($path === '/settings' && $method === 'GET') {
            $settings = SettingsModel::getSettings();
            if (AuthHelper::isAdmin() && !AuthHelper::mustChangePassword()) {
                // Strip password hashes & raw API key even for admin API output
                $settings['has_ai_key'] = !empty($settings['gemini_api_key']);
                unset($settings['admin_password'], $settings['user_password'], $settings['gemini_api_key']);
                self::json(['success' => true, 'data' => $settings]);
            } else {
                // Public / Non-Admin: Public Satker metadata only; daftar pejabat hanya untuk sesi login aktif
                $publicData = [
                    'satker_kode' => $settings['satker_kode'] ?? 'SAR-304',
                    'satker_nama' => $settings['satker_nama'] ?? 'Kantor Pencarian dan Pertolongan Kelas A Kendari',
                    'alamat' => $settings['alamat'] ?? '',
                    'no_telp' => $settings['no_telp'] ?? '',
                    'email' => $settings['email'] ?? '',
                    'call_sign' => $settings['call_sign'] ?? '',
                    'frekuensi_default' => $settings['frekuensi_default'] ?? '',
                    'default_theme' => $settings['default_theme'] ?? 'dark',
                    'logo_url' => $settings['logo_url'] ?? ''
                ];
                if (AuthHelper::isLoggedIn() && !AuthHelper::mustChangePassword()) {
                    $publicData['pejabat_list'] = $settings['pejabat_list'] ?? [];
                }
                self::json(['success' => true, 'data' => $publicData]);
            }
            return true;
        }

        // 2. Update Settings
        if ($path === '/settings' && ($method === 'PUT' || $method === 'POST')) {
            self::requireAdmin(true);
            $ok = SettingsModel::updateSettings($input);
            self::json(['success' => $ok, 'message' => 'Pengaturan satker berhasil disimpan.']);
            return true;
        }

        return false;
    }
}
