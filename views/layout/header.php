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
$defaultSystemTheme = $appSettings['default_theme'] ?? 'dark';
$logoUrl = getAppLogoUrl();
$pageTitle = isset($pageTitle) ? "{$pageTitle} - QR SAR Basarnas" : "QR SAR - Sistem Pelaporan Operasi SAR Kendari";
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= e($defaultSystemTheme) ?>" class="<?= $defaultSystemTheme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Immediate Anti-Flicker & Layout State Script -->
    <script>
        (function() {
            var sysDefault = <?= json_encode($defaultSystemTheme) ?>;
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
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/favicon.png?v=<?= APP_VERSION ?>">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico?v=<?= APP_VERSION ?>">
    <link rel="apple-touch-icon" href="<?= e($logoUrl) ?>">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- Leaflet CSS (Offline Local) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/leaflet.min.css?v=<?= APP_VERSION ?>">
    
    <!-- SweetAlert2 CSS (Offline Local) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sweetalert2.min.css?v=<?= APP_VERSION ?>">
    
    <!-- App Design System Stylesheet -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= APP_VERSION ?>">
    
    <!-- Tailwind CSS (Offline Local Engine) -->
    <script src="<?= BASE_URL ?>/assets/js/tailwind.min.js?v=<?= APP_VERSION ?>"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        basarnas: {
                            50: '#fff7ed',
                            100: '#ffedd5',
                            200: '#fed7aa',
                            300: '#fdba74',
                            400: '#fb923c',
                            500: '#f97316',
                            600: '#ea580c',
                            700: '#c2410c',
                            800: '#9a3412',
                            900: '#7c2d12',
                        }
                    }
                }
            }
        };
    </script>
    <!-- QRCode.js (Local Offline Engine) -->
    <script src="<?= BASE_URL ?>/assets/js/qrcode.min.js?v=<?= APP_VERSION ?>"></script>
</head>
<body class="<?= isset($currentRoute) ? 'route-' . e($currentRoute) : '' ?> bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen transition-colors">
<div class="app-container">
