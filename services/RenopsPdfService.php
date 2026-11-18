<?php
/**
 * QR-SAR - Service Generator PDF Vektor Dokumen Rencana Operasi SAR (RENOPS)
 * Menggunakan engine cetak Google Chrome / Chromium Headless (--print-to-pdf)
 * sehingga hasil unduh PDF 100% identik dengan Cetak PDF (teks vektor & dapat dicari/find,
 * mendukung @page portrait + @page lanskap, header/footer berjalan, dan diagram SVG).
 */

declare(strict_types=1);

class RenopsPdfService {
    /**
     * Temukan path biner Google Chrome / Chromium yang dapat dieksekusi di server.
     */
    public static function findChromeBinary(): ?string {
        $envBin = trim((string)(getenv('CHROME_BIN') ?: ($_ENV['CHROME_BIN'] ?? '')));
        $candidates = array_filter([
            $envBin !== '' ? $envBin : null,
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Chromium.app/Contents/MacOS/Chromium',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/google-chrome',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/snap/bin/chromium',
        ]);

        foreach ($candidates as $bin) {
            if (is_file($bin) && is_executable($bin)) {
                return $bin;
            }
        }
        return null;
    }

    /**
     * Konversi URL aset lokal (/assets/... dan /uploads/...) menjadi URI file:// absolut di dalam ROOT_DIR
     * agar Chrome Headless dapat memuat stylesheet, skrip QR, logo, peta, dan foto dokumentasi langsung
     * dari disk lokal tanpa bergantung pada sesi HTTP.
     */
    public static function prepareHtmlForLocalRender(string $html): string {
        $rootDir = rtrim(str_replace('\\', '/', ROOT_DIR), '/');
        $baseQuoted = preg_quote(rtrim(BASE_URL, '/'), '#');

        $rewritten = (string)preg_replace_callback(
            '#\b(src|href)="(?:' . $baseQuoted . '|https?://[^"/]+)?/(assets|uploads)/([^"?\#]+)(?:\?[^"]*)?"#i',
            static function (array $m) use ($rootDir): string {
                $attr = $m[1];
                $folder = $m[2];
                $relPath = rawurldecode($m[3]);
                if (str_contains($relPath, '..') || str_contains($relPath, "\0")) {
                    return $m[0];
                }
                $encodedParts = array_map('rawurlencode', explode('/', ltrim($relPath, '/')));
                $fileUri = 'file://' . $rootDir . '/' . $folder . '/' . implode('/', $encodedParts);
                return $attr . '="' . htmlspecialchars($fileUri, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );

        $printOverrideStyle = '<style>'
            . 'html, body { background: #ffffff !important; }'
            . '.no-print, .no-print * { display: none !important; }'
            . '.doc-scroll { overflow: visible !important; padding: 0 !important; }'
            . '.page-sheet.renops-smeac { zoom: 1 !important; box-shadow: none !important; }'
            . '</style>';

        if (stripos($rewritten, '</head>') !== false) {
            $rewritten = (string)preg_replace('#</head>#i', $printOverrideStyle . '</head>', $rewritten, 1);
        } else {
            $rewritten = $printOverrideStyle . $rewritten;
        }

        return $rewritten;
    }

    /**
     * Render HTML dokumen RENOPS menjadi biner PDF vektor (%PDF-...) menggunakan Chrome Headless.
     * Mengembalikan null apabila biner Chrome tidak tersedia atau proses gagal.
     */
    public static function renderHtmlToPdf(string $html): ?string {
        $chrome = self::findChromeBinary();
        if ($chrome === null) {
            return null;
        }

        $preparedHtml = self::prepareHtmlForLocalRender($html);
        $token = bin2hex(random_bytes(8));
        $tmpDir = rtrim(sys_get_temp_dir(), '/\\');
        $tmpHtml = $tmpDir . '/qrsar_renops_' . $token . '.html';
        $tmpPdf = $tmpDir . '/qrsar_renops_' . $token . '.pdf';

        try {
            if (@file_put_contents($tmpHtml, $preparedHtml) === false) {
                return null;
            }
            @chmod($tmpHtml, 0600);

            $fileUrl = 'file://' . str_replace('\\', '/', $tmpHtml);
            $cmd = escapeshellarg($chrome)
                . ' --headless'
                . ' --disable-gpu'
                . ' --no-sandbox'
                . ' --disable-dev-shm-usage'
                . ' --no-pdf-header-footer'
                . ' --virtual-time-budget=1200'
                . ' --print-to-pdf=' . escapeshellarg($tmpPdf)
                . ' ' . escapeshellarg($fileUrl)
                . ' 2>/dev/null';

            self::runWithTimeout($cmd, 15);

            if (!is_file($tmpPdf) || filesize($tmpPdf) < 500) {
                return null;
            }
            $pdfBytes = @file_get_contents($tmpPdf);
            if (!is_string($pdfBytes) || !str_starts_with($pdfBytes, '%PDF-')) {
                return null;
            }
            return $pdfBytes;
        } finally {
            if (is_file($tmpHtml)) {
                @unlink($tmpHtml);
            }
            if (is_file($tmpPdf)) {
                @unlink($tmpPdf);
            }
        }
    }

    private static function runWithTimeout(string $cmd, int $timeoutSeconds = 15): void {
        if (!function_exists('proc_open')) {
            $out = [];
            $exitCode = 1;
            @exec($cmd, $out, $exitCode);
            return;
        }
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return;
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            @fclose($pipes[0]);
        }
        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            $status = @proc_get_status($proc);
            if (!is_array($status) || empty($status['running'])) {
                break;
            }
            if (microtime(true) >= $deadline) {
                @proc_terminate($proc, 9);
                break;
            }
            usleep(50000);
        }
        @proc_close($proc);
    }
}

