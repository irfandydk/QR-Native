<?php
/**
 * QR-SAR - Dedicated Rencana Operasi (RENOPS) Form Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

$idOperasi = isset($_GET['id_operasi']) ? (int)$_GET['id_operasi'] : (int)($_POST['id_operasi'] ?? 0);
if ($idOperasi <= 0) {
    setFlash('error', 'ID Operasi SAR tidak valid.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$operasi = OperasiModel::getById($idOperasi);
if (!$operasi) {
    setFlash('error', 'Data Operasi SAR tidak ditemukan.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$idRenops = isset($_GET['id_renops']) ? (int)$_GET['id_renops'] : (int)($_POST['id_renops'] ?? 0);
$editMode = ($idRenops > 0);
$renops = $editMode ? RenopsModel::getById($idRenops) : null;

if ($editMode && !$renops) {
    setFlash('error', 'Data Rencana Operasi (RENOPS) tidak ditemukan.');
    header("Location: " . BASE_URL . "/operasi_detail.php?id={$idOperasi}&tab=renops");
    exit;
}

// Check if operation is CLOSED
if ($operasi['status'] === 'CLOSED') {
    setFlash('error', 'Operasi SAR telah berstatus DITUTUP (CLOSED). Data RENOPS tidak dapat ditambahkan atau diubah kecuali operasi dibuka kembali (Rollback).');
    header("Location: " . BASE_URL . "/operasi_detail.php?id={$idOperasi}&tab=renops");
    exit;
}

// Handle Form POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
        header("Location: " . BASE_URL . "/renops_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_renops={$idRenops}" : ""));
        exit;
    }

    if (!empty($_POST['detail_alut_json']) && is_string($_POST['detail_alut_json'])) {
        $decodedA = json_decode($_POST['detail_alut_json'], true);
        if (is_array($decodedA)) {
            $_POST['detail_alut'] = $decodedA;
        }
    }
    if (!empty($_POST['detail_unsur_json']) && is_string($_POST['detail_unsur_json'])) {
        $decodedU = json_decode($_POST['detail_unsur_json'], true);
        if (is_array($decodedU)) {
            $_POST['detail_unsur'] = $decodedU;
        }
    }

    // Process SRUs from input arrays if submitted
    $srus = [];
    if (!empty($_POST['srus']) && is_array($_POST['srus'])) {
        foreach ($_POST['srus'] as $s) {
            if (!empty($s['nama_sru'])) {
                $srus[] = [
                    'nama_sru' => trim($s['nama_sru']),
                    'koordinat_area' => trim($s['koordinat_area'] ?? ''),
                    'luas_area' => trim($s['luas_area'] ?? ''),
                    'pola_pencarian' => trim($s['pola_pencarian'] ?? 'Parallel Track Search')
                ];
            }
        }
    }
    $_POST['srus'] = $srus;

    $baseDateOps = TemplateHelper::getWaktuMulaiAktual($operasi);
    $tglInput = $_POST['tanggal_laporan'] ?? date('Y-m-d');
    if (empty($_POST['hari_ke'])) {
        $_POST['hari_ke'] = TemplateHelper::hitungHariKe($baseDateOps, $tglInput);
    }

    try {
        if ($editMode) {
            RenopsModel::update($idRenops, $_POST);
            setFlash('success', 'Rencana Operasi SAR (RENOPS) berhasil diperbarui!');
        } else {
            $_POST['id_operasi'] = $idOperasi;
            RenopsModel::create($_POST);
            setFlash('success', 'Rencana Operasi SAR (RENOPS) berhasil diterbitkan!');
        }
        header("Location: " . BASE_URL . "/operasi_detail.php?id={$idOperasi}&tab=renops");
        exit;
    } catch (Throwable $e) {
        setFlash('error', 'Gagal menyimpan RENOPS: ' . $e->getMessage());
        header("Location: " . BASE_URL . "/renops_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_renops={$idRenops}" : ""));
        exit;
    }
}

// Master data
$masterTim = MasterModel::getTim();
$masterAlut = MasterModel::getAlut();

// Determine default day calculation based on actual operation calendar date (restarts if reopened)
$baseDateOps = TemplateHelper::getWaktuMulaiAktual($operasi);

if ($editMode) {
    $tanggalLaporan = $renops['tanggal_laporan'] ?? date('Y-m-d');
    $hariKe = $renops['hari_ke'] ?: TemplateHelper::hitungHariKe($baseDateOps, $tanggalLaporan);
    $waktuBriefing = !empty($renops['waktu_briefing']) ? date('Y-m-d\TH:i', strtotime($renops['waktu_briefing'])) : date('Y-m-d\T07:00');
    $waktuMulaiOps = !empty($renops['waktu_mulai_ops']) ? date('Y-m-d\TH:i', strtotime($renops['waktu_mulai_ops'])) : date('Y-m-d\T07:30');
    $cuaca = $renops['cuaca'] ?? ($operasi['cuaca'] ?: 'Cerah Berawan');
    $initialUnsurData = !empty($renops['detail_unsur']) ? $renops['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
    $initialAlutData = !empty($renops['detail_alut']) ? $renops['detail_alut'] : ($operasi['detail_alut'] ?? []);
    $initialSrus = $renops['srus'] ?? [];
} else {
    $tanggalLaporan = date('Y-m-d');
    $hariKe = TemplateHelper::hitungHariKe($baseDateOps, $tanggalLaporan);
    $waktuBriefing = date('Y-m-d\T07:00');
    $waktuMulaiOps = date('Y-m-d\T07:30');
    $cuaca = $operasi['cuaca'] ?: 'Cerah Berawan';
    $initialUnsurData = !empty($operasi['detail_unsur']) ? $operasi['detail_unsur'] : [];
    $initialAlutData = !empty($operasi['detail_alut']) ? $operasi['detail_alut'] : [];
    
    // Default initial SRU
    $lkpDesc = $operasi['lokasi_kejadian'] ?: 'LKP';
    $koorDesc = $operasi['koordinat'] ?: '';
    $initialSrus = [
        [
            'nama_sru' => 'SRU 1 Laut / Darat',
            'koordinat_area' => "Penyisiran di sekitar LKP ({$lkpDesc})\nKoordinat: {$koorDesc}",
            'luas_area' => '10 Nm²',
            'pola_pencarian' => 'Parallel Track Search'
        ]
    ];
}

$pageTitle = ($editMode ? "Edit RENOPS {$hariKe}" : "Buat RENOPS {$hariKe}") . " - {$operasi['nama_operasi']}";
$currentRoute = 'operasi_detail';
$navbarTitle = $editMode ? "Edit Rencana Operasi ({$hariKe})" : "Penerbitan Rencana Operasi ({$hariKe})";

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/renops_form.php';
require_once VIEWS_DIR . '/layout/footer.php';
