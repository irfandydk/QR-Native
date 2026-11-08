<?php
/**
 * QR-SAR - Base API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

abstract class BaseApiController {
    /**
     * Send JSON response and exit
     */
    public static function json(mixed $data, int $statusCode = 200): void {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send standard error response and exit
     */
    public static function error(string $message, int $statusCode = 400, ?string $errorCode = null): void {
        $payload = [
            'success' => false,
            'error' => $errorCode ?: $message,
            'message' => $message
        ];
        self::json($payload, $statusCode);
    }

    /**
     * Authentication Middleware Check
     */
    /**
     * Origin CORS sah bila skema, host dan port sama persis dengan BASE_URL.
     * Saat pengembangan, localhost/127.0.0.1 port mana pun ikut diizinkan.
     */
    public static function isAllowedOrigin(string $origin, string $baseUrl, bool $isDev): bool {
        $norm = static function (string $url): ?string {
            $p = parse_url($url);
            if (!is_array($p) || empty($p['scheme']) || empty($p['host'])) {
                return null;
            }
            $scheme = strtolower($p['scheme']);
            return $scheme . '://' . strtolower($p['host']) . ':' . ($p['port'] ?? ($scheme === 'https' ? 443 : 80));
        };
        $o = $norm($origin);
        if ($o === null) {
            return false;
        }
        return $o === $norm($baseUrl)
            || ($isDev && in_array(strtolower((string)parse_url($origin, PHP_URL_HOST)), ['localhost', '127.0.0.1'], true));
    }

    public static function requireAuth(bool $allowPendingPasswordChange = false): void {
        if (!AuthHelper::isLoggedIn()) {
            self::error('Akses ditolak: Sesi login diperlukan.', 401, 'UNAUTHORIZED');
        }
        if (!$allowPendingPasswordChange && PHP_SAPI !== 'cli' && AuthHelper::mustChangePassword()) {
            self::error('Akun masih memakai kata sandi bawaan. Ganti kata sandi terlebih dahulu.', 403, 'PASSWORD_CHANGE_REQUIRED');
        }
    }

    /**
     * Administrator Privilege Middleware Check
     */
    public static function requireAdmin(): void {
        // Sesi sah + gerbang wajib-ganti-sandi berlaku juga untuk endpoint admin
        self::requireAuth();
        if (!AuthHelper::isAdmin()) {
            self::error('Akses ditolak: Hanya Administrator yang diizinkan melakukan tindakan ini.', 403, 'FORBIDDEN');
        }
    }

    /**
     * CSRF Validation Middleware
     */
    public static function requireCsrf(array $input = []): void {
        if (AuthHelper::isLoggedIn()) {
            $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? null)));
            if (!validateCsrf($csrfToken)) {
                self::error('Validasi CSRF gagal: Token keamanan tidak valid atau sesi Anda telah kedaluwarsa.', 403, 'CSRF_INVALID');
            }
        }
    }

    /**
     * AI Rate Limiting Middleware (15 requests/min per session)
     */
    public static function checkAiRateLimit(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $now = time();
        $history = $_SESSION['ai_req_history'] ?? [];
        if (!is_array($history)) {
            $history = [];
        }
        $history = array_filter($history, fn($t) => is_numeric($t) && ($now - (int)$t) < 60);
        if (count($history) >= 15) {
            self::error('Batas kuota panggilan AI terlampaui (Maksimal 15 permintaan per menit). Mohon tunggu sejenak.', 429, 'RATE_LIMIT_EXCEEDED');
        }
        $history[] = $now;
        $_SESSION['ai_req_history'] = array_values($history);
    }
}
