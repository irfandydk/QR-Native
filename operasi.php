<?php
/**
 * QR-SAR - Operasi SAR Controller & Action Handler
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$action = $_GET['action'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    if (!empty($_POST['detail_alut_json']) && is_string($_POST['detail_alut_json'])) {
        $decodedA = json_decode($_POST['detail_alut_json'], true);
        if (is_array($decodedA)) $_POST['detail_alut'] = $decodedA;
    }
    if (!empty($_POST['detail_unsur_json']) && is_string($_POST['detail_unsur_json'])) {
        $decodedU = json_decode($_POST['detail_unsur_json'], true);
        if (is_array($decodedU)) $_POST['detail_unsur'] = $decodedU;
    }

    if ($action === 'create') {
        try {
            $newId = OperasiModel::create($_POST);
            setFlash('success', 'Laporan Awal Operasi SAR berhasil diterbitkan!');
            header("Location: " . BASE_URL . "/operasi_detail.php?id={$newId}");
            exit;
        } catch (Throwable $e) {
            setFlash('error', 'Gagal membuat operasi: ' . $e->getMessage());
            header("Location: " . BASE_URL . "/operasi_form.php");
            exit;
        }
    }

    if ($action === 'update') {
        $id = (int)($_GET['id'] ?? 0);
        try {
            OperasiModel::update($id, $_POST);
            setFlash('success', 'Data Operasi SAR berhasil diperbarui!');
            header("Location: " . BASE_URL . "/operasi_detail.php?id={$id}");
            exit;
        } catch (Throwable $e) {
            setFlash('error', 'Gagal memperbarui operasi: ' . $e->getMessage());
            header("Location: " . BASE_URL . "/operasi_form.php?id={$id}");
            exit;
        }
    }

    if ($action === 'close') {
        $id = (int)($_POST['id_operasi'] ?? 0);
        $waktu = $_POST['waktu_selesai'] ?? date('Y-m-d H:i:s');
        OperasiModel::closeOperation($id, $waktu);
        setFlash('success', 'Operasi SAR resmi dinyatakan DITUTUP.');
        header("Location: " . BASE_URL . "/operasi_detail.php?id={$id}");
        exit;
    }

    if ($action === 'rollback') {
        $id = (int)($_POST['id_operasi'] ?? $_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            setFlash('error', 'Gagal membuka kembali operasi: ID Operasi tidak ditemukan.');
            header("Location: " . BASE_URL . "/index.php");
            exit;
        }

        try {
            $ok = OperasiModel::rollbackPenutupan($id);
            if ($ok) {
                setFlash('success', 'Operasi SAR berhasil dibuka kembali (Status: OPEN). Seluruh dokumen dan data operasional kini dapat diperbarui kembali.');
            } else {
                setFlash('error', 'Gagal membuka kembali operasi SAR: Sistem basis data gagal memperbarui status.');
            }
        } catch (Throwable $e) {
            setFlash('error', 'Gagal membuka kembali operasi: ' . $e->getMessage());
        }

        header("Location: " . BASE_URL . "/operasi_detail.php?id={$id}");
        exit;
    }
}

// Delete action (GET or POST)
if ($action === 'delete') {
    if (!AuthHelper::canDelete()) {
        setFlash('error', 'Akses ditolak: Hanya administrator yang dapat menghapus data operasi.');
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }

    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id > 0) {
        try {
            OperasiModel::delete($id);
            setFlash('success', 'Laporan Awal Operasi SAR berhasil dihapus secara permanen.');
        } catch (Throwable $e) {
            setFlash('error', 'Gagal menghapus operasi: ' . $e->getMessage());
        }
    }
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Default: show list
$pageTitle = 'Daftar Operasi SAR';
$currentRoute = 'operasi';
$navbarTitle = 'Daftar Operasi SAR Kendari';

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/dashboard.php';
require_once VIEWS_DIR . '/layout/footer.php';
