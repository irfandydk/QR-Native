<?php
/**
 * QR-SAR - Pengaturan Admin & Konfigurasi Satker (Thin Dispatcher)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

(new AdminController())->index();
