<?php
/**
 * QR-SAR - Authentication & User Profile API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class AuthApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Login
        if ($path === '/auth/login' && $method === 'POST') {
            $username = $input['username'] ?? '';
            $password = $input['password'] ?? '';
            $result = AuthHelper::login($username, $password);
            if (!$result['success']) {
                self::json($result, 401);
            }
            self::json($result);
            return true;
        }

        // 2. Profile
        if ($path === '/auth/profile' && $method === 'GET') {
            self::json(['success' => true, 'data' => AuthHelper::getCurrentUser()]);
            return true;
        }

        // 3. Ping / Session Heartbeat
        if ($path === '/auth/ping' && $method === 'GET') {
            self::json([
                'success' => true,
                'logged_in' => AuthHelper::isLoggedIn(),
                'user' => AuthHelper::isLoggedIn() ? (AuthHelper::getCurrentUser()['username'] ?? 'user') : null,
                'time' => time()
            ]);
            return true;
        }

        // 4. Change Password
        if ($path === '/auth/change-password' && $method === 'POST') {
            self::requireAuth(true);
            $currentUser = AuthHelper::getCurrentUser();
            $role = $currentUser['role'] ?? 'USER';
            $old = $input['oldPassword'] ?? '';
            $new = $input['newPassword'] ?? '';
            $userId = $currentUser['id'] ?? null;
            $res = SettingsModel::changePassword($role, $old, $new, $userId);
            if (!$res['success']) {
                self::json($res, 400);
            }
            self::json($res);
            return true;
        }

        return false;
    }
}
