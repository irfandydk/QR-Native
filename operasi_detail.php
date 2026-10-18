<?php
/**
 * QR-SAR - Detail Operasi SAR Controller
 * KPP Kendari - Basarnas
 *
 * Operational Controller Contract:
 * - 'useGis' => true
 * - 'route' => 'operasi_detail'
 * - GisModel::getOperationBundle($id, true)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

(new OperasiController())->detail();
