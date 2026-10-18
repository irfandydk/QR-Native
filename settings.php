<?php
/**
 * QR-SAR - Legacy Settings Redirection Handler
 * KPP Kendari - Basarnas
 * Routes cleanly to master.php (Operasional) or admin.php (Administrator)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$tab = $_GET['tab'] ?? '';

if (in_array($tab, ['satker', 'keamanan', 'backup', 'admin', 'user', 'logs'], true)) {
    $targetTab = ($tab === 'keamanan') ? 'admin' : $tab;
    header("Location: " . BASE_URL . "/admin.php?tab=" . urlencode($targetTab), true, 301);
    exit;
}

header("Location: " . BASE_URL . "/master.php?tab=" . urlencode($tab ?: 'alut'), true, 301);
exit;
