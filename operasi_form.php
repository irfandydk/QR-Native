<?php
/**
 * QR-SAR - Operasi Form Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$operasi = $id ? OperasiModel::getById($id) : null;

$oldInput = $_SESSION['old_input'] ?? null;
unset($_SESSION['old_input']);

if ($oldInput && is_array($oldInput)) {
    if (!$operasi) {
        $operasi = $oldInput;
    } else {
        $operasi = array_merge($operasi, $oldInput);
    }
}

$pageTitle = $operasi ? "Edit Operasi - {$operasi['nama_operasi']}" : "Inisiasi Laporan Awal Operasi SAR";
$currentRoute = 'operasi_form';
$navbarTitle = $operasi ? "Edit Operasi SAR" : "Inisiasi Laporan Awal";

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/operasi_form.php';
require_once VIEWS_DIR . '/layout/footer.php';
