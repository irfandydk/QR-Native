<?php
/**
 * QR-SAR - Application Bootstrap & Settings
 * Quick Report SAR System - Basarnas KPP Kendari
 */

declare(strict_types=1);

// 1. Global .env Loader (Sets getenv, $_ENV, and $_SERVER)
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath) && is_readable($envPath)) {
    $envLines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($envLines !== false) {
        foreach ($envLines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) continue;
            [$key, $val] = explode('=', $trimmed, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 2. Application Environment & Error Suppression Mode
$appEnv = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production');
define('APP_ENV', $appEnv);
$appDebug = filter_var(getenv('APP_DEBUG') ?: ($_ENV['APP_DEBUG'] ?? false), FILTER_VALIDATE_BOOLEAN);
define('APP_DEBUG', $appDebug);

if ($appDebug) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// 2b. Global Centralized Exception & Fatal Error Handler
if (!function_exists('qr_sar_exception_handler')) {
    function qr_sar_exception_handler(Throwable $e): void {
        error_log("[QR-SAR EXCEPTION 500] " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());

        try {
            if (class_exists('LogService')) {
                LogService::logException($e, 'UNCAUGHT_EXCEPTION_500');
            }
            if (class_exists('AuditHelper')) {
                AuditHelper::log('SYSTEM_ERROR_500', substr($e->getMessage(), 0, 200), 'EXCEPTION', basename($e->getFile()) . ':' . $e->getLine());
            }
        } catch (Throwable) {
            // Ignore audit log failure if database connection itself is broken
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $isApi = str_contains($uri, '/api/') || str_starts_with($uri, '/api') || str_contains($accept, 'application/json');

        if ($isApi) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error' => 'INTERNAL_SERVER_ERROR',
                'message' => (defined('APP_DEBUG') && APP_DEBUG) ? $e->getMessage() : 'Terjadi kendala pada pangkalan data atau sistem internal QR-SAR.'
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $err500View = dirname(__DIR__) . '/views/errors/500.php';
        if (file_exists($err500View)) {
            $exception = $e;
            include $err500View;
        } else {
            echo "<!DOCTYPE html><html><body style='font-family:sans-serif;padding:2rem;background:#0f172a;color:#fff;'><h1>HTTP 500 - Gangguan Sistem Internal</h1><p>" . htmlspecialchars($e->getMessage()) . "</p></body></html>";
        }
        exit;
    }
    set_exception_handler('qr_sar_exception_handler');
}

// 3. Baseline HTTP Security Headers
if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// 4. Session Setup
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Timezone standard Basarnas Kendari (WITA / UTC+8)
date_default_timezone_set('Asia/Makassar');

// App Constants
define('APP_NAME', 'QR SAR');
define('APP_TITLE', 'Sistem Pelaporan Operasi SAR & Berita Radio SAR');
define('APP_VERSION', '2.5.0');
define('DEFAULT_SATKER_KODE', 'SAR-304');
define('DEFAULT_SATKER_NAMA', 'Kantor Pencarian dan Pertolongan Kelas A Kendari');

// Cryptographic Key for HMAC & Signatures (Instance-specific)
$appKey = getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '');
if (empty($appKey)) {
    $appKey = 'BASARNAS_KENDARI_SECURE_HMAC_KEY_V2_' . sha1(__DIR__);
}
define('APP_KEY', (string)$appKey);

// Base URL detection with Host Header sanitization & optional APP_URL override
$appUrlEnv = getenv('APP_URL') ?: (getenv('BASE_URL') ?: null);
$rawHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '');
$isLocalDev = (php_sapi_name() === 'cli-server') ||
              str_starts_with($rawHost, 'localhost') ||
              str_starts_with($rawHost, '127.0.0.1') ||
              str_starts_with($rawHost, '[::1]');

if ($appUrlEnv && !$isLocalDev) {
    $baseUrl = rtrim($appUrlEnv, '/');
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                ? 'https://' : 'http://';

    $rawHost = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? '127.0.0.1:8000');
    // Strictly allow valid hostnames, domains, IPs, and optional port
    if (preg_match('/^[a-zA-Z0-9.\-]+(:[0-9]{1,5})?$/', $rawHost)) {
        $host = $rawHost;
    } else {
        $host = $_SERVER['SERVER_NAME'] ?? '127.0.0.1:8000';
    }

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $scriptDir = preg_replace('#/api$#', '', (string)$scriptDir);
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }
    $baseUrl = rtrim($protocol . $host . ($scriptDir ? '/' . ltrim($scriptDir, '/') : ''), '/');
}
define('BASE_URL', $baseUrl);

// 5. Server-Side Session Inactivity Timeout (Idle Timeout: 2 jam / 7200 detik)
$idleTimeout = 7200;
if (!empty($_SESSION['user_id'])) {
    $now = time();
    $lastActivity = (int)($_SESSION['last_activity'] ?? $now);
    if ($now - $lastActivity > $idleTimeout) {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        session_start();
        $_SESSION['flash'] = [
            'type' => 'error',
            'message' => 'Sesi Anda telah kedaluwarsa karena tidak ada aktivitas selama 2 jam demi keamanan sistem.'
        ];
        $_SESSION['flash_messages']['error'] = $_SESSION['flash']['message'];
        
        $reqUri = $_SERVER['REQUEST_URI'] ?? '';
        if (php_sapi_name() !== 'cli') {
            if (str_contains($reqUri, '/api/')) {
                http_response_code(401);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'error' => 'Sesi Anda telah berakhir otomatis karena tidak ada aktivitas selama 2 jam.'
                ]);
                exit;
            }
            header("Location: " . BASE_URL . "/login.php");
            exit;
        }
    } else {
        $_SESSION['last_activity'] = $now;
    }
}

define('ROOT_DIR', dirname(__DIR__));
define('CONFIG_DIR', ROOT_DIR . '/config');
define('MODELS_DIR', ROOT_DIR . '/models');
define('SERVICES_DIR', ROOT_DIR . '/services');
define('CONTROLLERS_DIR', ROOT_DIR . '/controllers');
define('HELPERS_DIR', ROOT_DIR . '/helpers');
define('VIEWS_DIR', ROOT_DIR . '/views');
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('STORAGE_DIR', ROOT_DIR . '/storage');
define('ASSETS_DIR', ROOT_DIR . '/assets');
define('PUBLIC_DIR', ROOT_DIR . '/public');
define('API_CONTROLLERS_DIR', ROOT_DIR . '/api/controllers');

// Autoload Models, Services, Controllers, Helpers & API Controllers
require_once CONFIG_DIR . '/database.php';

spl_autoload_register(function ($class) {
    $modelFile = MODELS_DIR . '/' . $class . '.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        return;
    }
    $serviceFile = SERVICES_DIR . '/' . $class . '.php';
    if (file_exists($serviceFile)) {
        require_once $serviceFile;
        return;
    }
    $ctrlFile = CONTROLLERS_DIR . '/' . $class . '.php';
    if (file_exists($ctrlFile)) {
        require_once $ctrlFile;
        return;
    }
    $helperFile = HELPERS_DIR . '/' . $class . '.php';
    if (file_exists($helperFile)) {
        require_once $helperFile;
        return;
    }
    $apiCtrlFile = API_CONTROLLERS_DIR . '/' . $class . '.php';
    if (file_exists($apiCtrlFile)) {
        require_once $apiCtrlFile;
        return;
    }
});

require_once HELPERS_DIR . '/ViewHelper.php';

// Flash messages helpers
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    $_SESSION['flash_messages'][$type] = $message;
}

function getFlash(?string $type = null) {
    if ($type !== null) {
        $msg = $_SESSION['flash_messages'][$type] ?? null;
        unset($_SESSION['flash_messages'][$type]);
        if (isset($_SESSION['flash']) && ($_SESSION['flash']['type'] ?? '') === $type) {
            unset($_SESSION['flash']);
        }
        return $msg;
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        unset($_SESSION['flash_messages']);
        return $flash;
    }
    return null;
}

// CSRF Global Helpers
function csrfToken(): string {
    return AuthHelper::csrfToken();
}

function csrfInput(): string {
    $token = csrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function validateCsrf(?string $token = null): bool {
    $t = $token ?? ($_POST['csrf_token'] ?? '');
    return AuthHelper::validateCsrf((string)$t);
}

function verifyCsrfToken(?string $token = null): bool {
    return validateCsrf($token);
}

// Global sanitization helper
function e(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Safe JSON encoder for inline HTML <script> tags
 * Automatically escapes HTML tags, quotes, and ampersands to prevent XSS breakout.
 */
function jsonSafe(mixed $value, int $flags = 0): string {
    return json_encode($value, $flags | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

// Default initial password fallback for administrative reset routines
defined('DEFAULT_RESET_PASSWORD') || define('DEFAULT_RESET_PASSWORD', getenv('DEFAULT_RESET_PASSWORD') ?: 'Kendari304');

/**
 * Global App Logo Helper (Resolves from AppSettings.logo_url)
 * Normalized to current domain/origin to prevent CORS & broken links
 */
function getAppLogoUrl(): string {
    static $cachedUrl = null;
    if ($cachedUrl !== null) {
        return $cachedUrl;
    }

    try {
        $settings = SettingsModel::getSettings();
        $rawLogo = trim((string)($settings['logo_url'] ?? ''));

        if (!empty($rawLogo)) {
            if (str_starts_with($rawLogo, 'data:image/')) {
                $cachedUrl = $rawLogo;
                return $cachedUrl;
            }
            if (str_starts_with($rawLogo, '/uploads/') || str_starts_with($rawLogo, 'uploads/')) {
                $cachedUrl = BASE_URL . '/' . ltrim($rawLogo, '/');
                return $cachedUrl;
            }
            if (strpos($rawLogo, '/uploads/') !== false) {
                $rel = strstr($rawLogo, '/uploads/');
                $cachedUrl = BASE_URL . $rel;
                return $cachedUrl;
            }
            $cachedUrl = $rawLogo;
            return $cachedUrl;
        }
    } catch (Throwable $e) {
        // Fallback
    }

    $cachedUrl = BASE_URL . '/assets/images/logo_basarnas.svg';
    return $cachedUrl;
}

/**
 * Returns Base64 Data URI of current App Logo
 * Perfect for embedding in HTML, Favicons, and HTML5 Canvas (Zero CORS issues)
 */
function getAppLogoDataUri(): string {
    static $cachedDataUri = null;
    if ($cachedDataUri !== null) {
        return $cachedDataUri;
    }

    try {
        $settings = SettingsModel::getSettings();
        $rawLogo = trim((string)($settings['logo_url'] ?? ''));

        if (!empty($rawLogo)) {
            if (str_starts_with($rawLogo, 'data:image/')) {
                $cachedDataUri = $rawLogo;
                return $cachedDataUri;
            }
            if (strpos($rawLogo, '/uploads/') !== false || str_starts_with($rawLogo, 'uploads/')) {
                $parsedPath = parse_url($rawLogo, PHP_URL_PATH) ?? $rawLogo;
                $filename = basename($parsedPath);
                $filePath = UPLOADS_DIR . '/' . $filename;
                if (file_exists($filePath)) {
                    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                    $mime = match ($ext) {
                        'png' => 'image/png',
                        'jpg', 'jpeg' => 'image/jpeg',
                        'svg' => 'image/svg+xml',
                        'webp' => 'image/webp',
                        'gif' => 'image/gif',
                        default => 'image/png'
                    };
                    $data = file_get_contents($filePath);
                    if ($data !== false) {
                        $cachedDataUri = 'data:' . $mime . ';base64,' . base64_encode($data);
                        return $cachedDataUri;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        // Fallback
    }

    // Default Basarnas logo SVG Data URI
    $svgPath = ROOT_DIR . '/assets/images/logo_basarnas.svg';
    if (file_exists($svgPath)) {
        $data = file_get_contents($svgPath);
        if ($data !== false) {
            $cachedDataUri = 'data:image/svg+xml;base64,' . base64_encode($data);
            return $cachedDataUri;
        }
    }

    $cachedDataUri = '';
    return $cachedDataUri;
}
