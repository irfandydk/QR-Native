<?php
/**
 * QR-SAR - Operasi SAR Controller & Action Handler
 * KPP Kendari - Basarnas
 *
 * Routes and print actions delegated to OperasiController:
 * - print_renops
 * - print_penutupan
 * - print_sitrep
 * - print_laporan_awal
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

(new OperasiController())->index();
