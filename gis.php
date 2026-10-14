<?php
/**
 * QR-SAR - Tactical GIS Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

$pageTitle = 'Tactical Maritime GIS & Map Plotting';
$currentRoute = 'gis';
$navbarTitle = 'Plotting Taktis Operasi & Wilayah Kerja SAR Kendari';

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/gis.php';
require_once VIEWS_DIR . '/layout/footer.php';
