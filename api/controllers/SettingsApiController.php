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
            self::requireAdmin();
            $ok = SettingsModel::updateSettings($input);
            self::json(['success' => $ok, 'message' => 'Pengaturan satker berhasil disimpan.']);
            return true;
        }

        // 3. Export SQL Dump (Admin Only)
        if ($path === '/export/sql' && $method === 'GET') {
            self::requireAdmin();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            $sql = SettingsModel::generateSQLDump();
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="db_sar_qr.sql"');
            echo $sql;
            exit;
        }

        // 4. Backup JSON Export (Admin Only)
        if (($path === '/database/export' || $path === '/backup/export') && $method === 'GET') {
            self::requireAdmin();
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            $backup = SettingsModel::exportJsonBackup();
            $date = date('Y-m-d');
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="BACKUP_DATABASE_SAR_KENDARI_' . $date . '.json"');
            echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        return false;
    }
}
