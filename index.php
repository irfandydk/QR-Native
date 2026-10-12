<?php
/**
 * QR-SAR - Main Entrypoint & Dashboard Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$pageTitle = 'Dashboard Operasi SAR';
$currentRoute = 'dashboard';
$navbarTitle = 'Dashboard Operasi SAR';

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/dashboard.php';
require_once VIEWS_DIR . '/layout/footer.php';
