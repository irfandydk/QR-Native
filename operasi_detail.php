<?php
/**
 * QR-SAR - Detail Operasi SAR Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    setFlash('error', 'ID Operasi tidak valid.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$operasi = OperasiModel::getById($id);

if (!$operasi) {
    setFlash('error', 'Operasi SAR tidak ditemukan atau telah dihapus.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$gisBundle = GisModel::getOperationBundle($id, true);

$pageTitle = 'Operasi: ' . htmlspecialchars($operasi['nama_operasi']);
$currentRoute = 'operasi';
$navbarTitle = 'PUSAT KENDALI OPERASI SAR';
$navbarSubtitle = 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS';

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/operasi_detail.php';
require_once VIEWS_DIR . '/layout/footer.php';
