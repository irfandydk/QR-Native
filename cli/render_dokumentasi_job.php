#!/usr/bin/env php
<?php
/**
 * QR-SAR - Background CLI Worker untuk Render Video Kolase Dokumentasi (FFmpeg)
 * Dipanggil secara asynchronous oleh DokumentasiVideoService::createRenderJob().
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only.');
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$jobId = isset($argv[1]) ? trim((string)$argv[1]) : '';
if ($jobId === '' || !preg_match('/^[a-f0-9]{32}\z/', $jobId)) {
    fwrite(STDERR, "Invalid job_id.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/config/app.php';

try {
    DokumentasiVideoService::executeJob($jobId);
    exit(0);
} catch (Throwable $e) {
    error_log('[render_dokumentasi_job] ' . $e->getMessage());
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
