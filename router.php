<?php
/**
 * QR-SAR - Local Development Router for PHP Built-in Server
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
require_once __DIR__ . '/helpers/Router.php';
Router::rejectUnsafePath($uri);

// Block sensitive directories, schemas, dumps, and environment files
if (
    preg_match('#^/(database|config|models|controllers|services|helpers|views|storage|tests|cli|scratch)/#i', $uri) ||
    preg_match('#\.(sql|sqlite|db|env|log|md|htaccess|sample)$#i', $uri)
) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Akses Ditolak: Direktori atau berkas sistem dilindungi.');
}

// Serve existing static assets (css, js, images, icons, uploads)
$filePath = __DIR__ . $uri;
// /uploads/ lewat Router::serveStaticFile agar header CSP anti-XSS SVG ikut terkirim
// Hanya assets/ dan favicon yang disajikan langsung; berkas lain di root tidak pernah disajikan
$isPublicStatic = str_starts_with($uri, '/assets/') || in_array($uri, ['/favicon.ico', '/favicon.png'], true);
if ($isPublicStatic && file_exists($filePath) && !is_dir($filePath) && !str_ends_with($filePath, '.php')) {
    return false; // Built-in server serves static asset directly
}

if (str_starts_with($uri, '/api')) {
    require_once __DIR__ . '/api/index.php';
    return true;
}

require_once __DIR__ . '/config/app.php';
return Router::dispatch();
