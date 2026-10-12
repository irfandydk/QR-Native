<?php
/**
 * QR-SAR - Layout Top Header
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference src/components/Header.tsx
 */

declare(strict_types=1);

$headerTitle = $navbarTitle ?? $pageTitle ?? 'DASHBOARD OPERASI SAR';
// Clean any suffix like " - QR SAR Basarnas"
$headerTitle = preg_replace('/\s*-\s*QR SAR.*$/i', '', (string)$headerTitle);
$headerSubtitle = $navbarSubtitle ?? 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI • BASARNAS';
?>
<header class="sticky top-0 z-30 backdrop-blur-md border-b transition-colors duration-200 bg-white/95 dark:bg-slate-900/90 border-slate-200 dark:border-slate-800 text-slate-900 dark:text-slate-100 shadow-xs">
    <div class="w-full px-4 sm:px-6">
        <div class="flex items-center justify-between h-14 gap-3">
            <!-- Left: Mobile Drawer Trigger & View Title -->
            <div class="flex items-center gap-3 min-w-0">
                <button
                    type="button"
                    id="mobile-menu-btn"
                    class="lg:hidden p-2 rounded-xl border text-slate-500 hover:text-slate-900 dark:hover:text-white transition-colors cursor-pointer shrink-0 border-slate-200 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800"
                    title="Buka Navigasi Sistem"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                </button>

                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-extrabold text-sm sm:text-base tracking-wide uppercase truncate text-slate-900 dark:text-slate-100">
                            <?= e($headerTitle) ?>
                        </span>
                        <span class="hidden sm:inline-flex items-center gap-1 text-[9px] font-bold px-2 py-0.5 rounded-full bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30 shrink-0">
                            FILE AKTIF
                        </span>
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium tracking-tight truncate hidden sm:block">
                        <?= e($headerSubtitle) ?>
                    </p>
                </div>
            </div>

            <!-- Right: Official Satker Indicator & Global Theme Toggle -->
            <div class="flex items-center gap-2.5 shrink-0">
                <div class="hidden md:flex items-center gap-2 px-3 py-1 rounded-xl border text-[11px] font-semibold bg-slate-100 dark:bg-slate-800/80 border-slate-200 dark:border-slate-700/60 text-slate-700 dark:text-slate-300">
                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                    <span>SISTEM SIAGA SAR KENDARI</span>
                </div>

                <!-- Theme Toggle Button -->
                <button
                    type="button"
                    id="theme-toggle-btn"
                    class="p-2 rounded-xl border border-slate-200 dark:border-slate-700/80 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 transition-colors cursor-pointer shadow-xs"
                    title="Ganti Mode Gelap / Terang"
                >
                    <span id="theme-icon" class="flex items-center justify-center">
                        <svg class="w-4 h-4 hidden dark:block text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                        <svg class="w-4 h-4 block dark:hidden text-slate-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                    </span>
                </button>
            </div>
        </div>
    </div>
</header>

<main class="page-body">

