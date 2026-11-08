<?php
/**
 * QR-SAR - Layout Header Portal Verifikasi Publik
 * Basarnas KPP Kendari
 * Khusus untuk Akses Eksternal Tamu / Verifikasi QR Code Naskah
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    require_once dirname(__DIR__, 2) . '/config/app.php';
}

$appSettings = SettingsModel::getSettings();
$uiTheme = (($appSettings['ui_theme'] ?? 'klasik') === 'minimal') ? 'minimal' : 'klasik';
$defaultSystemTheme = $appSettings['default_theme'] ?? 'dark';
$logoUrl = getAppLogoUrl();
$pageTitle = isset($pageTitle) ? "{$pageTitle} - Verifikasi Resmi Basarnas" : "Verifikasi Naskah Berita Radio SAR - Basarnas Kendari";
?>
<!DOCTYPE html>
<html lang="id" data-ui="<?= $uiTheme ?>" data-theme="<?= e($defaultSystemTheme) ?>" class="<?= $defaultSystemTheme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="description" content="Portal Verifikasi Digital Resmi Dokumen Berita Radio SAR - Badan Nasional Pencarian dan Pertolongan (Basarnas) KPP Kendari">
    <meta name="theme-color" content="#f97316">
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
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <!-- App Design System Stylesheet -->
    <link rel="stylesheet" href="<?= assetUrl('assets/css/style.css') ?>">

    <!-- Tailwind CSS (Offline Local Vendor) -->
    <link rel="stylesheet" href="<?= assetUrl('assets/css/tailwind.css') ?>">
    <!-- Tema tampilan global (Pengaturan Satker) -->
    <?php if ($uiTheme === 'minimal'): ?><link rel="stylesheet" href="<?= assetUrl('assets/css/theme-minimal.css') ?>"><?php endif; ?>
    
    <!-- QRCode.js (Offline Local Vendor) -->
    <script src="<?= assetUrl('assets/vendor/qrcode/qrcode.min.js') ?>"></script>

    <!-- SweetAlert2 (Offline Local Vendor) -->
    <link rel="stylesheet" href="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.min.css') ?>">
    <script src="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>

    <script>
        window.BASE_URL = <?= jsonSafe(BASE_URL) ?>;
        function showToast(message, type = 'info') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: type,
                    titleText: message,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            } else {
                alert(message);
            }
        }
    </script>

    <style>
        /* Standalone Print & Typography Rules */
        @media print {
            body {
                background: white !important;
                color: black !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            .print\:hidden {
                display: none !important;
            }
            @page {
                size: auto;
                margin: 8mm 10mm 8mm 10mm;
            }
        }
    </style>
</head>
<body class="bg-slate-100 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col font-sans transition-colors antialiased">
    <!-- Public Verification Header Bar -->
    <header class="print:hidden sticky top-0 z-40 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-xs">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="w-10 h-10 object-contain drop-shadow-xs">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs sm:text-sm font-bold tracking-tight text-slate-900 dark:text-white uppercase">BASARNAS</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-bold bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20">KPP KENDARI</span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Portal Verifikasi Dokumen Berita Radio SAR</div>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <div class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-400 text-xs font-semibold">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Server Verifikasi Aktif</span>
                </div>
                <!-- Theme Toggle Button -->
                <button type="button" onclick="togglePublicTheme()" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Ganti Mode Gelap / Terang">
                    <svg class="w-5 h-5 dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </button>
            </div>
        </div>
    </header>

    <!-- Main Public Container -->
    <main class="flex-1 w-full max-w-6xl mx-auto px-3 sm:px-6 py-6">
