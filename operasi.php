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
    AuthHelper::requireLogin();

    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
        header("Location: " . BASE_URL . "/index.php");
        exit;
    }

    if (!empty($_POST['detail_alut_json']) && is_string($_POST['detail_alut_json'])) {
        $decodedA = json_decode($_POST['detail_alut_json'], true);
        if (is_array($decodedA)) $_POST['detail_alut'] = $decodedA;
    }
    if (!empty($_POST['detail_unsur_json']) && is_string($_POST['detail_unsur_json'])) {
        $decodedU = json_decode($_POST['detail_unsur_json'], true);
        if (is_array($decodedU)) $_POST['detail_unsur'] = $decodedU;
    }

    if ($action === 'create' || $action === 'update') {
        $errors = [];
        if (empty(trim($_POST['nama_operasi'] ?? ''))) {
            $errors[] = 'Nama Operasi SAR wajib diisi.';
        }
        if (empty(trim($_POST['nama_pelapor'] ?? ''))) {
            $errors[] = 'Nama Pelapor wajib diisi.';
        }
        if (empty(trim($_POST['lokasi_kejadian'] ?? ''))) {
            $errors[] = 'Lokasi Kejadian wajib diisi.';
        }
        if (empty(trim($_POST['waktu_lapor'] ?? ''))) {
            $errors[] = 'Waktu Lapor Kejadian wajib diisi.';
        }
        if (empty(trim($_POST['waktu_berangkat'] ?? ''))) {
            $errors[] = 'Waktu Tim Berangkat wajib diisi.';
        }

        if (!empty($errors)) {
            $_SESSION['old_input'] = $_POST;
            setFlash('error', implode(' ', $errors));
            if ($action === 'update') {
                $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
                header("Location: " . BASE_URL . "/operasi_form.php?id={$id}");
            } else {
                header("Location: " . BASE_URL . "/operasi_form.php");
            }
            exit;
        }
    }

    if ($action === 'create') {
        try {
            $newId = OperasiModel::create($_POST);
            unset($_SESSION['old_input']);
            setFlash('success', 'Laporan Awal Operasi SAR berhasil diterbitkan!');
            header("Location: " . BASE_URL . "/operasi_detail.php?id={$newId}");
            exit;
        } catch (Throwable $e) {
            $_SESSION['old_input'] = $_POST;
            setFlash('error', 'Gagal membuat operasi SAR: ' . $e->getMessage());
            header("Location: " . BASE_URL . "/operasi_form.php");
            exit;
        }
    }

    if ($action === 'update') {
        $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
        try {
            OperasiModel::update($id, $_POST);
            unset($_SESSION['old_input']);
            setFlash('success', 'Data Operasi SAR berhasil diperbarui!');
            header("Location: " . BASE_URL . "/operasi_detail.php?id={$id}");
            exit;
        } catch (Throwable $e) {
            $_SESSION['old_input'] = $_POST;
            setFlash('error', 'Gagal memperbarui operasi SAR: ' . $e->getMessage());
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

        $waktuReopen = $_POST['waktu_reopen'] ?? date('Y-m-d H:i:s');
        try {
            $ok = OperasiModel::rollbackPenutupan($id, $waktuReopen);
            if ($ok) {
                setFlash('success', 'Operasi SAR berhasil dibuka kembali (Status: OPEN). Perhitungan hari operasional dimulai lagi sejak tanggal pembukaan.');
            } else {
                setFlash('error', 'Gagal membuka kembali operasi SAR: Sistem basis data gagal memperbarui status.');
            }
        } catch (Throwable $e) {
            setFlash('error', 'Gagal membuka kembali operasi: ' . $e->getMessage());
        }

        header("Location: " . BASE_URL . "/operasi_detail.php?id={$id}");
        exit;
    }

    // Delete action (POST Only with CSRF)
    if ($action === 'delete') {
        if (!AuthHelper::canDelete()) {
            setFlash('error', 'Akses ditolak: Hanya administrator yang dapat menghapus data operasi.');
            header("Location: " . BASE_URL . "/index.php");
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
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
}

// Reject any unauthenticated or GET-based delete attempt
if ($action === 'delete' && $method !== 'POST') {
    setFlash('error', 'Metode tidak diizinkan: Penghapusan data harus melalui metode POST dengan token keamanan.');
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
