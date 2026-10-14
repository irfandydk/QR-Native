<?php
/**
 * QR-SAR - Logout Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? null);

if (!validateCsrf($token)) {
    setFlash('error', 'Permintaan keluar tidak valid atau token keamanan kedaluwarsa. Silakan gunakan tombol keluar resmi.');
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

AuthHelper::logout();
setFlash('success', 'Anda telah berhasil keluar dari sesi sistem.');
header("Location: " . BASE_URL . "/login.php");
exit;
