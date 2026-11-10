<?php
/**
 * QR-SAR - Layout Header
 * KPP Kendari - Basarnas
 */

if (!defined('APP_NAME')) {
    require_once dirname(__DIR__, 2) . '/config/app.php';
}

$currentUser = AuthHelper::getCurrentUser();
$appSettings = SettingsModel::getSettings();
$uiTheme = (($appSettings['ui_theme'] ?? 'klasik') === 'minimal') ? 'minimal' : 'klasik';
$defaultSystemTheme = $appSettings['default_theme'] ?? 'dark';
$logoUrl = getAppLogoUrl();
$pageTitle = isset($pageTitle) ? "{$pageTitle} - QR SAR Basarnas" : "QR SAR - Sistem Pelaporan Operasi SAR Kendari";
?>
<!DOCTYPE html>
<html lang="id" data-ui="<?= $uiTheme ?>" data-theme="<?= e($defaultSystemTheme) ?>" class="<?= $defaultSystemTheme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="description" content="QR-SAR KPP Kendari - Sistem Pelaporan Operasi SAR & Berita Radio SAR Resmi Basarnas">
    <meta name="theme-color" content="#f97316">
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <!-- Immediate Anti-Flicker & Layout State Script -->
    <script>
        window.BASE_URL = <?= jsonSafe(BASE_URL) ?>;
        window.CSRF_TOKEN = <?= jsonSafe(csrfToken()) ?>;

        // Global Fetch Interceptor to automatically attach CSRF Token to state-changing requests
        (function() {
            var origFetch = window.fetch;
            if (typeof origFetch === 'function') {
                window.fetch = function(resource, config) {
                    config = config || {};
                    var method = (config.method || 'GET').toUpperCase();
                    if (['POST', 'PUT', 'DELETE', 'PATCH'].indexOf(method) !== -1) {
                        config.headers = config.headers || {};
                        var token = window.CSRF_TOKEN;
                        if (token) {
                            if (typeof Headers !== 'undefined' && config.headers instanceof Headers) {
                                if (!config.headers.has('X-CSRF-Token')) {
                                    config.headers.append('X-CSRF-Token', token);
                                }
                            } else if (Array.isArray(config.headers)) {
                                var found = false;
                                for (var i = 0; i < config.headers.length; i++) {
                                    if (config.headers[i][0] && config.headers[i][0].toLowerCase() === 'x-csrf-token') {
                                        found = true; break;
                                    }
                                }
                                if (!found) config.headers.push(['X-CSRF-Token', token]);
                            } else {
                                if (!config.headers['X-CSRF-Token'] && !config.headers['x-csrf-token']) {
                                    config.headers['X-CSRF-Token'] = token;
                                }
                            }
                        }
                    }
                    return origFetch.call(this, resource, config);
                };
            }
        })();

        (function() {
            var sysDefault = <?= jsonSafe($defaultSystemTheme) ?>;
            var savedTheme = localStorage.getItem('qr_sar_theme') || sysDefault || 'dark';
            document.documentElement.setAttribute('data-theme', savedTheme);
            if (savedTheme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
            var savedPalette = localStorage.getItem('qr_sar_palette') || 'orange';
            document.documentElement.setAttribute('data-palette', savedPalette);
            if (localStorage.getItem('qr_sar_sidebar_collapsed') === 'true') {
                document.documentElement.classList.add('sidebar-is-collapsed');
            }
        })();
    </script>
    <script>
    // Transisi antar halaman yang dibatalkan (klik cepat) bukan error: tangkap promise-nya
    ['pageswap', 'pagereveal'].forEach(function (t) {
        window.addEventListener(t, function (e) {
            if (e.viewTransition) { ["ready", "finished", "updateCallbackDone"].forEach(function (k) { if (e.viewTransition[k]) e.viewTransition[k].catch(function () {}); }); }
        });
    });
    </script>
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= assetUrl('favicon.png') ?>">
    <link rel="shortcut icon" href="<?= assetUrl('favicon.ico') ?>">
    <link rel="apple-touch-icon" href="<?= e($logoUrl) ?>">
    
    <!-- Conditional Leaflet CSS (Only on GIS / Map-enabled views) -->
    <?php if (!empty($useGis) || in_array($currentRoute ?? '', ['gis', 'operasi_detail', 'operasi_form'], true)): ?>
    <link rel="stylesheet" href="<?= assetUrl('assets/vendor/leaflet/leaflet.min.css') ?>">
    <?php endif; ?>
    
    <!-- SweetAlert2 CSS (Offline Local Vendor) -->
    <link rel="stylesheet" href="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">
    
    <!-- App Design System Stylesheet -->
    <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">
    
    <!-- Tailwind CSS (Offline Local Engine) -->
    <link rel="stylesheet" href="<?= assetUrl('assets/css/tailwind.css') ?>">
    <!-- Tema tampilan global (Pengaturan Satker) -->
    <?php if ($uiTheme === 'minimal'): ?><link rel="stylesheet" href="<?= assetUrl('assets/css/theme-minimal.css') ?>"><?php endif; ?>
    <?php if (($currentRoute ?? '') === 'gis'): ?>
    <!-- Halaman GIS: tanpa transisi antar halaman -->
    <style>@view-transition { navigation: none; }</style>
    <?php endif; ?>
</head>
<body class="<?= isset($currentRoute) ? 'route-' . e($currentRoute) : '' ?> bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen transition-colors">
<div class="app-container">
