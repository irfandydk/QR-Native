<?php
/**
 * QR-SAR - Tactical GIS Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

renderView('gis', [], [
    'title' => 'Tactical Maritime GIS & Map Plotting',
    'route' => 'gis',
    'navbarTitle' => 'Plotting Taktis Operasi & Wilayah Kerja SAR Kendari'
]);

