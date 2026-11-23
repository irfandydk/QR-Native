<?php
/**
 * QR-SAR - Authentication & User Profile API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class AuthApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // Ping / Session Heartbeat
        if ($path === '/auth/ping' && $method === 'GET') {
            self::json([
                'success' => true,
                'logged_in' => AuthHelper::isLoggedIn(),
                'user' => AuthHelper::isLoggedIn() ? (AuthHelper::getCurrentUser()['username'] ?? 'user') : null,
                'time' => time()
            ]);
            return true;
        }

        return false;
    }
}
