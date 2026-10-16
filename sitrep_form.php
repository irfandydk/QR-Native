<?php
/**
 * QR-SAR - Dedicated Laporan Perkembangan (SITREP / LAPBANG) Form Controller
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

$idLaporan = isset($_GET['id_laporan']) ? (int)$_GET['id_laporan'] : (int)($_POST['id_laporan'] ?? 0);
$editMode = ($idLaporan > 0);
$laporan = $editMode ? LaporanModel::getById($idLaporan) : null;

if ($editMode && !$laporan) {
    setFlash('error', 'Data Laporan Perkembangan tidak ditemukan.');
    header("Location: " . BASE_URL . "/operasi_detail.php?id={$idOperasi}&tab=sitrep");
    exit;
}

// Check if operation is CLOSED
if ($operasi['status'] === 'CLOSED') {
    setFlash('error', 'Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan tidak dapat ditambahkan atau diubah kecuali operasi dibuka kembali (Rollback).');
    header("Location: " . BASE_URL . "/operasi_detail.php?id={$idOperasi}&tab=sitrep");
    exit;
}

// Handle Form POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Token keamanan CSRF tidak valid. Silakan coba lagi.');
        header("Location: " . BASE_URL . "/sitrep_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_laporan={$idLaporan}" : ""));
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

    $baseDateOps = TemplateHelper::getWaktuMulaiAktual($operasi);
    $waktuLap = $_POST['waktu_laporan'] ?? date('Y-m-d H:i:s');
    $tglLap = date('Y-m-d', strtotime($waktuLap));
    $_POST['hari_ke'] = TemplateHelper::hitungHariKe($baseDateOps, $tglLap);

    try {
        if ($editMode) {
            LaporanModel::update($idLaporan, $_POST);
            setFlash('success', 'Laporan Perkembangan SAR (SITREP) berhasil diperbarui!');
        } else {
            $_POST['id_operasi'] = $idOperasi;
            LaporanModel::create($_POST);
            setFlash('success', 'Laporan Perkembangan SAR (SITREP) berhasil diterbitkan!');
        }
        header("Location: " . BASE_URL . "/operasi_detail.php?id={$idOperasi}&tab=sitrep");
        exit;
    } catch (Throwable $e) {
        setFlash('error', 'Gagal menyimpan SITREP: ' . $e->getMessage());
        header("Location: " . BASE_URL . "/sitrep_form.php?id_operasi={$idOperasi}" . ($editMode ? "&id_laporan={$idLaporan}" : ""));
        exit;
    }
}

// Master data
$masterTim = MasterModel::getTim();
$masterAlut = MasterModel::getAlut();

// Default values & data state based on actual calendar date (restarts if reopened)
$baseDateOps = TemplateHelper::getWaktuMulaiAktual($operasi);

if ($editMode) {
    $waktuLaporan = !empty($laporan['waktu_laporan']) ? date('Y-m-d\TH:i', strtotime($laporan['waktu_laporan'])) : date('Y-m-d\TH:i');
    $tglLap = date('Y-m-d', strtotime($waktuLaporan));
    $hariKe = TemplateHelper::hitungHariKe($baseDateOps, $tglLap);
    $jenisLaporan = $laporan['jenis_laporan'] ?? 'PERKEMBANGAN';
    $waktuDebrief = !empty($laporan['waktu_debrief']) ? date('Y-m-d\TH:i', strtotime($laporan['waktu_debrief'])) : date('Y-m-d\TH:i');
    $cuaca = $laporan['cuaca'] ?? ($operasi['cuaca'] ?: 'Cerah Berawan');
    $isiLaporan = $laporan['isi_laporan'] ?? '';
    $initialUnsurData = !empty($laporan['detail_unsur']) ? $laporan['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
    $initialAlutData = !empty($laporan['detail_alut']) ? $laporan['detail_alut'] : ($operasi['detail_alut'] ?? []);
} else {
    $waktuLaporan = date('Y-m-d\TH:i');
    $tglLap = date('Y-m-d', strtotime($waktuLaporan));
    $hariKe = TemplateHelper::hitungHariKe($baseDateOps, $tglLap);
    $jenisLaporan = 'PERKEMBANGAN';
    $waktuDebrief = date('Y-m-d\TH:i');
    $cuaca = $operasi['cuaca'] ?: 'Cerah Berawan';
    $isiLaporan = "Pada pelaksanaan operasi SAR hari ini, tim gabungan telah melaksanakan pencarian sesuai rencana operasi (RENOPS). Hingga saat laporan ini disusun, hasil pencarian masih nihil / korban belum ditemukan. Operasi SAR dihentikan sementara dan akan dilanjutkan esok hari.";
    $initialUnsurData = !empty($operasi['detail_unsur']) ? $operasi['detail_unsur'] : [];
    $initialAlutData = !empty($operasi['detail_alut']) ? $operasi['detail_alut'] : [];
}

$pageTitle = ($editMode ? "Edit SITREP {$hariKe}" : "Penerbitan SITREP {$hariKe}") . " - {$operasi['nama_operasi']}";
$currentRoute = 'operasi_detail';
$navbarTitle = $editMode ? "Edit Laporan Perkembangan ({$hariKe})" : "Penerbitan Laporan Perkembangan ({$hariKe})";

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/sitrep_form.php';
require_once VIEWS_DIR . '/layout/footer.php';
