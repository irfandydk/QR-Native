<?php
/**
 * QR-SAR - Unified REST API Router & Dispatcher
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dispatches API requests cleanly to modular domain controllers under api/controllers/
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Secure Origin Matching (Strict in production, relaxed only in local dev)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$appHost = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
if ($origin) {
    $isDev = (defined('APP_ENV') && APP_ENV === 'development') || in_array($appHost, ['localhost', '127.0.0.1'], true);

    if (BaseApiController::isAllowedOrigin($origin, BASE_URL, $isDev)) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Determine request path
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';

// Strip script directory and /api prefix
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
$path = preg_replace('#^/api#', '', $path);
$path = '/' . trim($path, '/');

// Support explicit path parameter: e.g. /api/index.php?path=/ai/refine-sitrep
if (!empty($_GET['path'])) {
    $path = '/' . ltrim((string)$_GET['path'], '/');
} elseif ($path === '/index.php' && !empty($_SERVER['QUERY_STRING']) && preg_match('#^/([a-zA-Z0-9_\-/]+)#', $_SERVER['QUERY_STRING'], $qm)) {
    $path = '/' . ltrim($qm[1], '/');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawInput = file_get_contents('php://input');
$input = !empty($rawInput) ? json_decode($rawInput, true) : [];
if (!is_array($input)) {
    $input = $_POST;
}

// CSRF Guard Middleware for State-Changing Requests (POST, PUT, DELETE)
if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && $path !== '/auth/login') {
    BaseApiController::requireCsrf($input);
}

try {
    // 1. Health check
    if ($path === '/' || $path === '/health') {
        BaseApiController::json(['status' => 'ok', 'time' => date('Y-m-d H:i:s'), 'version' => APP_VERSION]);
    }

    // 2. Authentication & User Profile
    if (str_starts_with($path, '/auth/')) {
        if (AuthApiController::handle($path, $method, $input)) exit;
    }

    // 3. Operasi SAR & Nested Sub-routes (RENOPS & SITREP under Operasi)
    if ($path === '/laporan/stats' || $path === '/monitoring/stats' || str_starts_with($path, '/operasi')) {
        if (preg_match('#^/operasi/(\d+)/renops#', $path)) {
            if (RenopsApiController::handle($path, $method, $input)) exit;
        } elseif (preg_match('#^/operasi/(\d+)/laporan#', $path)) {
            if (SitrepApiController::handle($path, $method, $input)) exit;
        } else {
            if (OperasiApiController::handle($path, $method, $input)) exit;
        }
    }

    // 4. Standalone RENOPS routes
    if (str_starts_with($path, '/renops/')) {
        if (preg_match('#^/renops/\d+/(dokumen|foto)#', $path)) {
            if (RenopsDokumenApiController::handle($path, $method, $input)) exit;
        }
        if (RenopsApiController::handle($path, $method, $input)) exit;
    }

    // 5. Standalone SITREP / Laporan routes
    if (str_starts_with($path, '/laporan/')) {
        if (SitrepApiController::handle($path, $method, $input)) exit;
    }

    // 6. Berita Radio SAR (BRA)
    if (str_starts_with($path, '/bra')) {
        if (BraApiController::handle($path, $method, $input)) exit;
    }

    // 7. Master Data (Alut, Tim, SarUnits, Pejabat)
    if (str_starts_with($path, '/settings/pejabat') || str_starts_with($path, '/master/') || $path === '/sar-units') {
        if (MasterApiController::handle($path, $method, $input)) exit;
    }

    // 8. Application Settings & Database Backups
    if (str_starts_with($path, '/settings') || str_starts_with($path, '/database/') || str_starts_with($path, '/backup/') || str_starts_with($path, '/export/')) {
        if (SettingsApiController::handle($path, $method, $input)) exit;
    }

    // 9. Gemini AI Assistant
    if (str_starts_with($path, '/ai/')) {
        if (AiApiController::handle($path, $method, $input)) exit;
    }

    // 10. Tactical Maritime GIS & Geocoding
    if (str_starts_with($path, '/gis') || $path === '/geocode' || $path === '/maps/calculate-route') {
        if (GisApiController::handle($path, $method, $input)) exit;
    }

    // 404 Route Not Found
    BaseApiController::error("Endpoint API tidak ditemukan: {$path}", 404, 'NOT_FOUND');
} catch (Throwable $e) {
    error_log('QR-SAR API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    BaseApiController::error('Terjadi kesalahan sistem internal. Permintaan tidak dapat diproses saat ini.', 500, 'INTERNAL_SERVER_ERROR');
}
