<?php
/**
 * QR-SAR - Official HTTP 500 Internal Server Error View
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}
$isAppDebug = defined('APP_DEBUG') && APP_DEBUG;
$errMsg = isset($exception) && $exception instanceof Throwable ? $exception->getMessage() : 'Terjadi kendala sistem internal.';
$errFile = isset($exception) && $exception instanceof Throwable ? $exception->getFile() : '';
$errLine = isset($exception) && $exception instanceof Throwable ? $exception->getLine() : 0;
$errTrace = isset($exception) && $exception instanceof Throwable ? $exception->getTraceAsString() : '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 - Gangguan Sistem Internal | QR-SAR Basarnas Kendari</title>
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
    <div class="max-w-2xl w-full bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl relative overflow-hidden">
        <!-- Glowing background accent -->
        <div class="absolute -top-24 -right-24 w-64 h-64 bg-orange-600/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-rose-600/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Header / Logo -->
        <div class="flex items-center gap-3.5 pb-6 border-b border-slate-800/80">
            <div class="w-12 h-12 rounded-2xl bg-orange-500/15 border border-orange-500/30 flex items-center justify-center text-orange-500 shrink-0 shadow-inner">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                    <line x1="12" x2="12" y1="9" y2="13"/>
                    <line x1="12" x2="12.01" y1="17" y2="17"/>
                </svg>
            </div>
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md bg-orange-500/20 text-orange-400 border border-orange-500/30">
                    SISTEM OPERASI SAR
                </span>
                <h1 class="text-lg font-bold text-white tracking-tight mt-0.5">
                    Kantor SAR Kelas A Kendari
                </h1>
            </div>
        </div>

        <!-- Main Content -->
        <div class="py-8">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 text-rose-400 text-xs font-bold mb-3">
                <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span>
                <span>STATUS KODE: HTTP 500</span>
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">
                Gangguan Sistem Internal
            </h2>
            <p class="text-sm text-slate-400 mt-2 leading-relaxed">
                Terjadi kendala saat sistem memproses permintaan atau menghubungi pangkalan data. Seluruh parameter galat telah dicatat secara otomatis ke dalam berkas log sistem untuk investigasi teknis.
            </p>

            <!-- Diagnostic Technical Panel (Debug or Admin) -->
            <?php if ($isAppDebug && !empty($errMsg)): ?>
                <div class="mt-6 rounded-2xl bg-slate-950 border border-slate-800 p-4 text-xs font-mono">
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-800 text-slate-400">
                        <span class="font-bold text-amber-400">Informasi Teknis (Mode Pengembang):</span>
                        <span><?= htmlspecialchars(basename($errFile) . ':' . $errLine) ?></span>
                    </div>
                    <div class="text-rose-400 font-semibold break-all">
                        <?= htmlspecialchars($errMsg) ?>
                    </div>
                    <?php if (!empty($errTrace)): ?>
                        <details class="mt-3 text-slate-500 text-[11px]">
                            <summary class="cursor-pointer hover:text-slate-300 font-sans font-bold">Tampilkan Pelacakan Eksekusi (Stack Trace)</summary>
                            <pre class="mt-2 whitespace-pre-wrap overflow-x-auto text-slate-400 leading-tight"><?= htmlspecialchars($errTrace) ?></pre>
                        </details>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="pt-6 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
            <button onclick="window.location.reload()" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs transition-all cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                <span>Muat Ulang Halaman</span>
            </button>

            <a href="<?= BASE_URL ?>/index.php" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-bold text-xs border border-slate-700 transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span>Kembali ke Beranda</span>
            </a>
        </div>
    </div>
</body>
</html>
