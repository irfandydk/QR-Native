<?php
/**
 * QR-SAR - Main Entrypoint & Dashboard Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

renderView('dashboard', [], [
    'title' => 'Dashboard Operasi SAR',
    'route' => 'dashboard',
    'navbarTitle' => 'Dashboard Operasi SAR'
]);

