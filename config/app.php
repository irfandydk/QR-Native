<?php
/**
 * QR-SAR - Application Bootstrap & Settings
 * Quick Report SAR System - Basarnas KPP Kendari
 */

declare(strict_types=1);

error_reporting(E_ALL & ~E_DEPRECATED);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
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

// Base URL detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
if ($scriptDir === '/' || $scriptDir === '.') {
    $scriptDir = '';
}
$baseUrl = rtrim($protocol . $host . ($scriptDir ? '/' . ltrim($scriptDir, '/') : ''), '/');
define('BASE_URL', $baseUrl);

// Directory Constants
define('ROOT_DIR', dirname(__DIR__));
define('CONFIG_DIR', ROOT_DIR . '/config');
define('MODELS_DIR', ROOT_DIR . '/models');
define('HELPERS_DIR', ROOT_DIR . '/helpers');
define('VIEWS_DIR', ROOT_DIR . '/views');
define('UPLOADS_DIR', ROOT_DIR . '/uploads');

// Autoload Models & Helpers
require_once CONFIG_DIR . '/database.php';

spl_autoload_register(function ($class) {
    $modelFile = MODELS_DIR . '/' . $class . '.php';
    if (file_exists($modelFile)) {
        require_once $modelFile;
        return;
    }
    $helperFile = HELPERS_DIR . '/' . $class . '.php';
    if (file_exists($helperFile)) {
        require_once $helperFile;
        return;
    }
});

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
    return AuthHelper::validateCsrf($t);
}

// Global sanitization helper
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

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
