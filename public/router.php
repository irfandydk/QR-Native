<?php
/**
 * QR-SAR - Local Development Router for PHP Built-in Server with Public Docroot
 * Usage: php -S 127.0.0.1:8000 -t public public/router.php
 * Quick Report SAR System - Basarnas KPP Kendari
 */

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));

// Block sensitive directories, schemas, dumps, and environment files
if (
    preg_match('#^/(database|config|models|controllers|services|helpers|views|storage|tests|cli|scratch)/#i', $uri) ||
    preg_match('#\.(sql|sqlite|db|env|log|md|htaccess|sample)$#i', $uri)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Akses Ditolak: Direktori atau berkas sistem dilindungi.');
}

require_once dirname(__DIR__) . '/config/app.php';

// 1. Direct file check inside public/ directory
$publicFile = __DIR__ . $uri;
// /uploads/ lewat Router::serveStaticFile agar header CSP anti-XSS SVG ikut terkirim
if ($uri !== '/' && !str_starts_with($uri, '/uploads/') && file_exists($publicFile) && !is_dir($publicFile) && !str_ends_with($publicFile, '.php')) {
    return false; // Built-in web server serves static asset directly
}

// 2. Fallback check for assets, uploads, and favicons from parent project root
$rootDir = dirname(__DIR__);
$fallbackFile = $rootDir . $uri;

if (
    (str_starts_with($uri, '/assets/') || str_starts_with($uri, '/uploads/') || in_array($uri, ['/favicon.ico', '/favicon.png'], true)) &&
    file_exists($fallbackFile) && !is_dir($fallbackFile)
) {
    return Router::serveStaticFile($fallbackFile);
}

// 3. Forward all other requests to Front Controller
require_once __DIR__ . '/index.php';
return true;
