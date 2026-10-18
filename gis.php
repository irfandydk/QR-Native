<?php
/**
 * QR-SAR - Tactical GIS Controller (Thin Dispatcher)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

(new GisController())->index();
