<?php
/**
 * QR-SAR - User Profile Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

AuthHelper::requireLogin();

if (AuthHelper::isAdmin()) {
    header("Location: " . BASE_URL . "/admin.php?tab=admin");
    exit;
}

header("Location: " . BASE_URL . "/master.php?tab=alut");
exit;
