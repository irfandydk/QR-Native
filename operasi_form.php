<?php
/**
 * QR-SAR - Operasi Form Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$operasi = $id ? OperasiModel::getById($id) : null;

$pageTitle = $operasi ? "Edit Operasi - {$operasi['nama_operasi']}" : "Inisiasi Laporan Awal Operasi SAR";
$currentRoute = 'operasi_form';
$navbarTitle = $operasi ? "Edit Operasi SAR" : "Inisiasi Laporan Awal";

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/operasi_form.php';
require_once VIEWS_DIR . '/layout/footer.php';
