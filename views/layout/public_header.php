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
$pageTitle = isset($pageTitle) ? "{$pageTitle} - Verifikasi Resmi Basarnas" : "Verifikasi Dokumen Elektronik - Basarnas Kendari";
?>
<!DOCTYPE html>
<html lang="id" data-ui="<?= $uiTheme ?>" data-theme="<?= e($defaultSystemTheme) ?>" class="<?= $defaultSystemTheme === 'dark' ? 'dark' : '' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="description" content="Portal Verifikasi Digital Resmi Dokumen Operasi & Berita Radio SAR - Badan Nasional Pencarian dan Pertolongan (Basarnas) KPP Kendari">
    <meta name="theme-color" content="#f97316">
    <script>
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
        /* Public Verification Editorial Typographic Rules */
        .verify-heading {
            text-wrap: balance;
        }
        .verify-prose {
            text-wrap: pretty;
        }
        .verify-surface-bg {
            background-image:
                radial-gradient(48rem 26rem at 10% 0%, rgba(234, 88, 12, 0.06), transparent 62%),
                radial-gradient(42rem 24rem at 92% 100%, rgba(16, 185, 129, 0.05), transparent 60%);
        }
        .dark .verify-surface-bg,
        [data-theme="dark"] .verify-surface-bg {
            background-image:
                radial-gradient(48rem 26rem at 10% 0%, rgba(234, 88, 12, 0.09), transparent 62%),
                radial-gradient(42rem 24rem at 92% 100%, rgba(16, 185, 129, 0.07), transparent 60%);
        }
        /* Standalone Print & Typography Rules */
        @media print {
            body {
                background: white !important;
                background-image: none !important;
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
<body class="verify-surface-bg bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 min-h-screen flex flex-col font-sans transition-colors antialiased overflow-x-hidden">
    <!-- Public Verification Header Bar -->
    <header class="print:hidden top-navbar sticky top-0 z-40 bg-white/95 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 shadow-xs">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 min-h-[64px] flex items-center justify-between gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <img src="<?= e($logoUrl) ?>" alt="Logo Basarnas" class="w-8 h-8 object-contain shrink-0" onerror="this.src='<?= assetUrl('assets/images/logo_basarnas.svg') ?>'">
                <div class="min-w-0">
                    <span class="text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100 uppercase block truncate">BASARNAS KPP KENDARI</span>
                    <p class="text-[11px] text-slate-600 dark:text-slate-400 font-medium tracking-tight truncate">
                        Verifikasi Dokumen Elektronik
                    </p>
                </div>
            </div>
            
            <!-- Theme Toggle Button -->
            <button type="button" onclick="togglePublicTheme()" class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer shadow-xs shrink-0" title="Ganti Mode Gelap / Terang" aria-label="Ganti Mode Gelap / Terang">
                <svg class="w-4 h-4 block dark:hidden text-slate-700" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                <svg class="w-4 h-4 hidden dark:block text-amber-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            </button>
        </div>
    </header>

    <!-- Main Public Container -->
    <main class="flex-1 w-full max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-12 overflow-x-hidden">
