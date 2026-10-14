<?php
/**
 * QR-SAR - Application Router & Dispatcher
 * Quick Report SAR System - Basarnas KPP Kendari
 * Supports both Modern Clean URLs and Legacy Page Controller URLs with Zero Regression
 */

declare(strict_types=1);

class Router {
    /**
     * Map of clean paths to physical controller files
     * @var array<string, string>
     */
    private static array $routes = [
        '/' => 'index.php',
        '' => 'index.php',
        '/index' => 'index.php',
        '/dashboard' => 'index.php',
        '/operasi' => 'operasi.php',
        '/operasi/create' => 'operasi_form.php',
        '/operasi/detail' => 'operasi_detail.php',
        '/renops/create' => 'renops_form.php',
        '/sitrep/create' => 'sitrep_form.php',
        '/bra' => 'bra.php',
        '/bra/create' => 'bra.php',
        '/bra/view' => 'bra.php',
        '/gis' => 'gis.php',
        '/master' => 'master.php',
        '/admin' => 'admin.php',
        '/login' => 'login.php',
        '/logout' => 'logout.php',
        '/profile' => 'profile.php',
        '/settings' => 'settings.php'
    ];

    /**
     * Dispatches current HTTP request to appropriate controller
     */
    public static function dispatch(?string $uri = null): bool {
        $rawUri = $uri ?? ($_SERVER['REQUEST_URI'] ?? '/');
        $parsedPath = parse_url($rawUri, PHP_URL_PATH);
        $path = urldecode($parsedPath ?: '/');

        // Normalize base path relative to configured or detected BASE_URL
        if (defined('BASE_URL')) {
            $basePath = parse_url(BASE_URL, PHP_URL_PATH) ?: '';
            if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
                $path = substr($path, strlen($basePath));
            }
        }
        $path = '/' . ltrim($path, '/');
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
        }

        // 1. Block access to protected directories & sensitive file extensions
        if (
            preg_match('#^/(database|config|models|helpers|views|storage)/#i', $path) ||
            preg_match('#\.(sql|sqlite|db|env|log|md|htaccess|sample)$#i', $path)
        ) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Akses Ditolak: Berkas atau direktori sistem dilindungi.';
            exit;
        }

        // 2. Static Asset Delivery Fallback (Guarantees CSS/JS/images load even if symlinks fail or on Windows)
        if (
            str_starts_with($path, '/assets/') ||
            str_starts_with($path, '/uploads/') ||
            in_array($path, ['/favicon.ico', '/favicon.png'], true)
        ) {
            $staticTarget = ROOT_DIR . $path;
            if (file_exists($staticTarget) && !is_dir($staticTarget)) {
                return self::serveStaticFile($staticTarget);
            }
        }

        // 3. Handle REST API prefix
        if (str_starts_with($path, '/api')) {
            $apiIndex = ROOT_DIR . '/api/index.php';
            if (file_exists($apiIndex)) {
                require_once $apiIndex;
                return true;
            }
        }

        // 4. Direct route matching (Clean URLs)
        if (isset(self::$routes[$path])) {
            $controller = self::$routes[$path];
            // Special sub-path query handling
            if ($path === '/bra/create') {
                $_GET['action'] = 'create';
            }
            $target = ROOT_DIR . '/' . $controller;
            if (file_exists($target)) {
                require_once $target;
                return true;
            }
        }

        // 4. Exact physical PHP script matching (e.g. /operasi.php)
        $cleanRelPath = ltrim($path, '/');
        $physicalFile = ROOT_DIR . '/' . $cleanRelPath;
        if (str_ends_with($cleanRelPath, '.php') && file_exists($physicalFile) && !is_dir($physicalFile)) {
            require_once $physicalFile;
            return true;
        }

        // 5. Check if clean path with .php exists (e.g. /operasi -> /operasi.php)
        $phpCandidate = ROOT_DIR . '/' . $cleanRelPath . '.php';
        if (file_exists($phpCandidate) && !is_dir($phpCandidate)) {
            require_once $phpCandidate;
            return true;
        }

        // 404 Not Found
        http_response_code(404);
        if (file_exists(VIEWS_DIR . '/layout/header.php')) {
            $pageTitle = 'Halaman Tidak Ditemukan - 404';
            require_once VIEWS_DIR . '/layout/header.php';
            echo '<div class="min-h-screen flex items-center justify-center bg-slate-50 dark:bg-slate-950 p-6 text-center">
                <div class="max-w-md bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl p-8 shadow-sm">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-orange-500/10 text-orange-600 flex items-center justify-center font-black text-2xl">404</div>
                    <h1 class="text-lg font-bold text-slate-900 dark:text-white mb-2">Halaman Tidak Ditemukan</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">Alamat halaman atau berkas yang Anda tuju tidak tersedia atau telah dipindahkan.</p>
                    <a href="' . BASE_URL . '/" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-700 text-white font-bold text-xs shadow-md shadow-orange-600/20 transition-all">Kembali ke Dashboard</a>
                </div>
            </div>';
            require_once VIEWS_DIR . '/layout/footer.php';
        } else {
            echo '404 - Halaman Tidak Ditemukan';
        }
        return false;
    }

    /**
     * Generates a clean URL with BASE_URL prefix
     */
    public static function url(string $path = ''): string {
        return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Serves static assets securely with appropriate MIME types, caching headers, and ETag/304 support
     */
    public static function serveStaticFile(string $filePath): bool {
        $realPath = realpath($filePath);
        $realRoot = realpath(ROOT_DIR);
        if (!$realPath || !$realRoot || !str_starts_with($realPath, $realRoot)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Akses Ditolak';
            exit;
        }

        // Never serve executable php or protected configuration files via static asset server
        if (
            str_ends_with($realPath, '.php') ||
            preg_match('#\.(sql|sqlite|db|env|log|md|htaccess|sample)$#i', $realPath)
        ) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Akses Ditolak';
            exit;
        }

        $ext = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
        $mimes = [
            'css'   => 'text/css; charset=UTF-8',
            'js'    => 'application/javascript; charset=UTF-8',
            'json'  => 'application/json; charset=UTF-8',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'ico'   => 'image/x-icon',
            'webp'  => 'image/webp',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'eot'   => 'application/vnd.ms-fontobject',
            'pdf'   => 'application/pdf',
            'map'   => 'application/json'
        ];

        $mimeType = $mimes[$ext] ?? null;
        if (!$mimeType) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $realPath) ?: 'application/octet-stream';
            finfo_close($finfo);
        }

        $lastModified = filemtime($realPath) ?: time();
        $etag = '"' . md5($lastModified . filesize($realPath)) . '"';

        header('Content-Type: ' . $mimeType);
        header('Cache-Control: public, max-age=604800, immutable');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
        header('ETag: ' . $etag);

        $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
        if ($ifNoneMatch === $etag || ($ifModifiedSince && strtotime($ifModifiedSince) >= $lastModified)) {
            http_response_code(304);
            return true;
        }

        header('Content-Length: ' . (string)filesize($realPath));
        readfile($realPath);
        return true;
    }
}
