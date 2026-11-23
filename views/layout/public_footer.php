<?php
/**
 * QR-SAR - Layout Footer Portal Verifikasi Publik
 * Basarnas KPP Kendari
 */

declare(strict_types=1);
?>
    </main>

    <!-- Public Verification Footer Bar (Minimal: Copyright & App Version Only) -->
    <footer class="print:hidden border-t border-slate-200 dark:border-slate-800 bg-white/90 dark:bg-slate-900/90 backdrop-blur-md mt-auto py-4 text-slate-600 dark:text-slate-400 transition-colors">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs">
            <span>&copy; <?= date('Y') ?> Basarnas KPP Kelas A Kendari</span>
            <span class="font-mono text-[11px] font-semibold text-slate-600 dark:text-slate-400 tabular-nums">
                QR-SAR v<?= e(defined('APP_VERSION') ? APP_VERSION : '2.5.0') ?>
            </span>
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
