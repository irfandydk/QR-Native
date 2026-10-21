<?php
/**
 * QR-SAR - Dashboard Controller Dispatcher (Fail-Safe Entrypoint)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

(new DashboardController())->index();
