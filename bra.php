<?php
/**
 * QR-SAR - Berita Radio SAR (BRA) Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$action = $_GET['action'] ?? null;
$viewId = $_GET['view'] ?? null;
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// View single BRA document sheet
if ($viewId !== null) {
    $pageTitle = 'Dokumen Resmi Berita Radio SAR';
    $currentRoute = 'bra';
    $navbarTitle = 'Naskah Resmi BRA SAR Terverifikasi';

    $isGuest = !AuthHelper::isLoggedIn();

    if ($isGuest) {
        // Public / External Guest Verification Portal (No internal admin sidebar)
        require_once VIEWS_DIR . '/layout/header.php';
        echo '<div class="min-h-screen bg-slate-100 dark:bg-slate-950 py-6 px-3 sm:px-6">';
        require_once VIEWS_DIR . '/bra_view.php';
        echo '</div>';
        require_once VIEWS_DIR . '/layout/footer.php';
        exit;
    }

    require_once VIEWS_DIR . '/layout/header.php';
    require_once VIEWS_DIR . '/layout/sidebar.php';
    require_once VIEWS_DIR . '/layout/navbar.php';
    require_once VIEWS_DIR . '/bra_view.php';
    require_once VIEWS_DIR . '/layout/footer.php';
    exit;
}

// Action: Save (Create or Update)
if ($method === 'POST' && $action === 'save') {
    try {
        $id = (int)($_POST['id_bra'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            BraModel::update($id, $_POST);
            setFlash('success', 'Naskah Berita Radio SAR berhasil diperbarui!');
            header("Location: " . BASE_URL . "/bra.php?view={$id}");
            exit;
        } else {
            $newId = BraModel::create($_POST);
            setFlash('success', 'Naskah Berita Radio SAR berhasil diterbitkan!');
            header("Location: " . BASE_URL . "/bra.php?view={$newId}");
            exit;
        }
    } catch (Throwable $e) {
        setFlash('error', 'Gagal menyimpan naskah BRA: ' . $e->getMessage());
        header("Location: " . BASE_URL . "/bra.php" . ($id > 0 ? "?action=edit&id={$id}" : "?action=create"));
        exit;
    }
}

// Action: Delete
if ($action === 'delete') {
    if (!AuthHelper::canDelete()) {
        setFlash('error', 'Akses ditolak: Hanya administrator yang berwenang menghapus arsip BRA.');
        header("Location: " . BASE_URL . "/bra.php");
        exit;
    }
    $id = (int)($_GET['id'] ?? 0);
    if ($id > 0) {
        BraModel::delete($id);
        setFlash('success', 'Dokumen Berita Radio SAR berhasil dihapus dari arsip.');
    }
    header("Location: " . BASE_URL . "/bra.php");
    exit;
}

// Action: Create or Edit Form
if ($action === 'create' || $action === 'edit') {
    $pageTitle = $action === 'edit' ? 'Edit Naskah BRA' : 'Buat Naskah BRA Baru';
    $currentRoute = 'bra';
    $navbarTitle = $pageTitle;

    require_once VIEWS_DIR . '/layout/header.php';
    require_once VIEWS_DIR . '/layout/sidebar.php';
    require_once VIEWS_DIR . '/layout/navbar.php';
    require_once VIEWS_DIR . '/bra_form.php';
    require_once VIEWS_DIR . '/layout/footer.php';
    exit;
}

// Default: List Archive
$pageTitle = 'Arsip Berita Radio SAR (BRA)';
$currentRoute = 'bra';
$navbarTitle = 'Arsip Berita Radio SAR KPP Kendari';

require_once VIEWS_DIR . '/layout/header.php';
require_once VIEWS_DIR . '/layout/sidebar.php';
require_once VIEWS_DIR . '/layout/navbar.php';
require_once VIEWS_DIR . '/bra_list.php';
require_once VIEWS_DIR . '/layout/footer.php';
