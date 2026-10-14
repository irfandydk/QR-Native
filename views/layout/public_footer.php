<?php
/**
 * QR-SAR - Layout Footer Portal Verifikasi Publik
 * Basarnas KPP Kendari
 */

declare(strict_types=1);
?>
    </main>

    <!-- Public Verification Footer Bar -->
    <footer class="print:hidden border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 mt-auto py-6 text-slate-500 dark:text-slate-400 transition-colors">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs">
            <div class="flex items-center gap-3 text-center sm:text-left">
                <div>
                    <span class="font-bold text-slate-800 dark:text-slate-200 block">BADAN NASIONAL PENCARIAN DAN PERTOLONGAN</span>
                    <span class="text-[11px] text-slate-400">Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari &bull; Layanan Verifikasi Berita Radio SAR</span>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-4 text-[11px]">
                <div class="flex items-center gap-1.5 font-bold text-orange-600 dark:text-orange-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>Emergency Call: 115 (Bebas Pulsa)</span>
                </div>
                <span class="text-slate-300 dark:text-slate-700 hidden sm:inline">&bull;</span>
                <span class="text-slate-400">SENKOM: (0401) 3122115</span>
            </div>
        </div>
    </footer>

    <script>
        function togglePublicTheme() {
            var html = document.documentElement;
            var isDark = html.classList.contains('dark');
            if (isDark) {
                html.classList.remove('dark');
                html.setAttribute('data-theme', 'light');
                localStorage.setItem('qr_sar_theme', 'light');
            } else {
                html.classList.add('dark');
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('qr_sar_theme', 'dark');
            }
        }
        (function() {
            var saved = localStorage.getItem('qr_sar_theme');
            if (saved === 'dark') {
                document.documentElement.classList.add('dark');
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (saved === 'light') {
                document.documentElement.classList.remove('dark');
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>
</body>
</html>
