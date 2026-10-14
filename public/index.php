<?php
/**
 * QR-SAR - Modern Public Document Root Entrypoint (Front Controller)
 * Quick Report SAR System - Basarnas KPP Kendari
 */

declare(strict_types=1);

// Bootstrap application from parent directory
require_once dirname(__DIR__) . '/config/app.php';

// Dispatch request through unified router
Router::dispatch();
