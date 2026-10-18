<?php
/**
 * QR-SAR - Dedicated Rencana Operasi (RENOPS) Form Controller (Thin Dispatcher)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

(new OperasiController())->renopsForm();
