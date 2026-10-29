<?php
/**
 * QR-SAR - Official HTTP 403 Forbidden Error View
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Akses Terbatas | QR-SAR Basarnas Kendari</title>
    <link rel="icon" type="image/png" href="<?= BASE_URL ?>/favicon.png">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/tailwind.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 antialiased">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden text-center">
        <div class="w-16 h-16 rounded-2xl bg-rose-500/15 border border-rose-500/30 flex items-center justify-center text-rose-500 mx-auto mb-4">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        </div>
        <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-rose-500/20 text-rose-400 border border-rose-500/30">
            HTTP 403 FORBIDDEN
        </span>
        <h1 class="text-xl font-bold text-white tracking-tight mt-2">
            Akses Dibatasi
        </h1>
        <p class="text-xs text-slate-400 mt-2 leading-relaxed">
            Anda tidak memiliki izin otorisasi yang memadai untuk mengakses halaman atau menjalankan fungsi operasional ini.
        </p>
        <div class="mt-6 flex justify-center">
            <a href="<?= BASE_URL ?>/index.php" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-lg transition-all cursor-pointer">
                <span>Kembali ke Beranda</span>
            </a>
        </div>
    </div>
</body>
</html>
