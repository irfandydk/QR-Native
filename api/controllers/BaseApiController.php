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
    public static function requireAuth(): void {
        if (!AuthHelper::isLoggedIn()) {
            self::error('Akses ditolak: Sesi login diperlukan.', 401, 'UNAUTHORIZED');
        }
    }

    /**
     * Administrator Privilege Middleware Check
     */
    public static function requireAdmin(): void {
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
