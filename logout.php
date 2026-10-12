<?php
/**
 * QR-SAR - Logout Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::logout();
setFlash('success', 'Anda telah berhasil keluar dari sesi sistem.');
header("Location: " . BASE_URL . "/login.php");
exit;
