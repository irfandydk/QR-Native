<?php
/**
 * QR-SAR - Login Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (AuthHelper::isLoggedIn() && $method === 'GET') {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

if ($method === 'POST') {
    if (!validateCsrf($_POST['csrf_token'] ?? null)) {
        setFlash('error', 'Token keamanan CSRF tidak valid atau sesi Anda telah kedaluwarsa. Silakan muat ulang halaman.');
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $res = AuthHelper::login($username, $password);
    if ($res['success']) {
        setFlash('success', 'Selamat datang kembali, ' . ($res['user']['nama'] ?? 'Petugas SAR') . '!');
        $redirect = $_SESSION['redirect_url'] ?? (BASE_URL . '/index.php');
        unset($_SESSION['redirect_url']);
        // Sanity check against open redirect vulnerabilities
        if (str_starts_with($redirect, 'http') && !str_starts_with($redirect, BASE_URL)) {
            $redirect = BASE_URL . '/index.php';
        }
        header("Location: " . $redirect);
        exit;
    } else {
        setFlash('error', $res['error'] ?? $res['message'] ?? 'Nama pengguna atau kata sandi tidak valid.');
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

// GET: render login view
require_once VIEWS_DIR . '/login.php';
