<?php
/**
 * QR-SAR - Berita Radio SAR (BRA) Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$action = $_GET['action'] ?? null;
$viewId = $_GET['view'] ?? ($action === 'view' ? ($_GET['id'] ?? null) : null);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// If 'id' is supplied without explicit 'action' and not in view mode, default to edit form
if (!$action && isset($_GET['id']) && $viewId === null && $method === 'GET') {
    $action = 'edit';
}

// View single BRA document sheet / Public QR Verification Portal
if ($viewId !== null) {
    $pageTitle = 'Verifikasi Dokumen Resmi Berita Radio SAR';
    $currentRoute = 'bra';
    $navbarTitle = 'Naskah Resmi BRA SAR Terverifikasi';
    $isGuest = !AuthHelper::isLoggedIn();

    $rawView = trim((string)$viewId);
    $rawToken = isset($_GET['token']) ? trim((string)$_GET['token']) : null;

    // 1. Sanitasi Ketat Parameter URL: Hanya izinkan integer positif murni (1-9999999999)
    $isValidParam = (bool)preg_match('/^[1-9][0-9]{0,9}$/', $rawView);
    $docId = $isValidParam ? (int)$rawView : 0;

    // 2. Query Record Dokumen secara aman via prepared statement
    $bra = $docId > 0 ? BraModel::getById($docId) : null;

    // 3. Evaluasi Status Keabsahan Dokumen & Token Tanda Tangan Digital
    $verificationStatus = 'VALID';
    $verificationMessage = '';

    if (!$bra) {
        $verificationStatus = 'NOT_FOUND';
        $verificationMessage = 'Dokumen Berita Radio SAR dengan ID ini tidak terdaftar dalam pangkalan data resmi Basarnas KPP Kendari.';
    } else {
        if ($rawToken !== null) {
            // Jika token disertakan di URL, verifikasi kecocokan tanda tangan digital
            if (!BraModel::verifyToken($bra, $rawToken)) {
                $verificationStatus = 'INVALID_TOKEN';
                $verificationMessage = 'Tanda tangan digital atau token verifikasi tidak valid. Dokumen diduga mengalami manipulasi URL atau tidak sah.';
            } else {
                $verificationStatus = 'VERIFIED_SIGNATURE';
            }
        } else {
            // Dokumen terdaftar tanpa token di URL (pencarian internal atau QR generasi lama)
            $verificationStatus = 'LEGACY_VERIFIED';
        }
    }

    // 4. Tangani Dokumen Tidak Sah / Parameter URL Manipulasi (Zero-Leakage Policy)
    if ($verificationStatus === 'NOT_FOUND' || $verificationStatus === 'INVALID_TOKEN') {
        // Penting: JANGAN PERNAH redirect tamu ke login.php atau bra.php!
        http_response_code(404);
        $invalidRef = htmlspecialchars($rawView, ENT_QUOTES, 'UTF-8');
        $invalidToken = $rawToken !== null ? htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8') : null;
        
        renderView('bra_verify_invalid', [
            'invalidRef' => $invalidRef,
            'invalidToken' => $invalidToken,
            'verificationStatus' => $verificationStatus
        ], [
            'layout' => 'public',
            'title' => 'Verifikasi Dokumen Tidak Sah'
        ]);
        exit;
    }

    // 5. Dokumen Sah: Render Portal Khusus Publik jika Guest
    if ($isGuest) {
        // Portal Verifikasi Publik Terisolasi (Bebas dari sidebar/navbar internal & DOM mismatch)
        renderView('bra_view', get_defined_vars(), [
            'layout' => 'public',
            'title' => 'Verifikasi Berita Radio SAR'
        ]);
        exit;
    }

    // 6. Dokumen Sah: Render Layout Lengkap Internal jika Pengguna Sudah Login
    renderView('bra_view', get_defined_vars(), [
        'layout' => 'default',
        'route' => 'bra',
        'title' => 'Naskah Berita Radio SAR',
        'navbarTitle' => 'LEMBAR DOKUMEN BERITA RADIO SAR (BRA)'
    ]);
    exit;
}

// Action: Save (Create or Update)
if ($method === 'POST' && $action === 'save') {
    AuthHelper::requireLogin();

    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa.');
        header("Location: " . BASE_URL . "/bra.php");
        exit;
    }

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

// Action: Delete (POST Only with CSRF)
if ($action === 'delete') {
    AuthHelper::requireLogin();

    if ($method !== 'POST') {
        setFlash('error', 'Metode tidak diizinkan: Penghapusan naskah BRA harus menggunakan metode POST.');
        header("Location: " . BASE_URL . "/bra.php");
        exit;
    }

    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa.');
        header("Location: " . BASE_URL . "/bra.php");
        exit;
    }

    if (!AuthHelper::canDelete()) {
        setFlash('error', 'Akses ditolak: Hanya administrator yang berwenang menghapus arsip BRA.');
        header("Location: " . BASE_URL . "/bra.php");
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        BraModel::delete($id);
        setFlash('success', 'Dokumen Berita Radio SAR berhasil dihapus dari arsip.');
    }
    header("Location: " . BASE_URL . "/bra.php");
    exit;
}

// Action: Create or Edit Form
if ($action === 'create' || $action === 'edit') {
    AuthHelper::requireLogin();

    $pageTitle = $action === 'edit' ? 'Edit Naskah BRA' : 'Buat Naskah BRA Baru';
    $navbarTitle = $pageTitle;

    renderView('bra_form', get_defined_vars(), [
        'title' => $pageTitle,
        'route' => 'bra',
        'navbarTitle' => $navbarTitle
    ]);
    exit;
}

// Default: List Archive
AuthHelper::requireLogin();

renderView('bra_list', get_defined_vars(), [
    'title' => 'Arsip Berita Radio SAR (BRA)',
    'route' => 'bra',
    'navbarTitle' => 'Arsip Berita Radio SAR KPP Kendari'
]);

