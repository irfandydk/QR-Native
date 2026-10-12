<?php
/**
 * QR-SAR - Login Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $res = AuthHelper::login($username, $password);
    if ($res['success']) {
        setFlash('success', 'Selamat datang kembali, ' . ($res['user']['nama'] ?? 'Petugas SAR') . '!');
        header("Location: " . BASE_URL . "/index.php");
        exit;
    } else {
        setFlash('error', $res['message'] ?? 'Nama pengguna atau kata sandi tidak valid.');
        header("Location: " . BASE_URL . "/login.php");
        exit;
    }
}

// GET: render login view
require_once VIEWS_DIR . '/login.php';
