<?php
/**
 * QR-SAR - Admin Tab Partial (Legacy Alias)
 * Daftar akun Admin & User kini digabung penuh pada tab_admin.php.
 */
declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

require __DIR__ . '/tab_admin.php';
