<?php
/**
 * QR-SAR - Main Entrypoint & Dashboard Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

(new DashboardController())->index();

