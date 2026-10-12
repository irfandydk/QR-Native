<?php
/**
 * QR-SAR - BRA Form Direct Router
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/config/app.php';

$query = $_SERVER['QUERY_STRING'] ?? '';
header("Location: " . BASE_URL . "/bra.php?action=create" . ($query ? '&' . $query : ''));
exit;
