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

renderView('operasi_detail', [
    'id' => $id,
    'operasi' => $operasi,
    'gisBundle' => $gisBundle
], [
    'title' => 'Operasi: ' . htmlspecialchars($operasi['nama_operasi']),
    'route' => 'operasi',
    'navbarTitle' => 'PUSAT KENDALI OPERASI SAR',
    'navbarSubtitle' => 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS'
]);

