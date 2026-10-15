<?php
/**
 * QR-SAR - Database Schema Migration Script
 * Basarnas KPP Kendari
 * 
 * Usage: php database/migrate.php
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

echo "=== [QR-SAR] Database Migration Runner ===\n";

try {
    $db = Database::getConnection();
    echo "[OK] Connected to database.\n";

    // 1. Run GisModel forced migration
    if (class_exists('GisModel')) {
        echo "[RUN] Checking GIS tables...\n";
        GisModel::ensureSchemaUpdated(true);
        echo "[OK] GIS schema validated.\n";
    }

    // 2. Run UserModel table migration
    if (class_exists('UserModel')) {
        echo "[RUN] Checking Users table...\n";
        UserModel::ensureTableExists();
        UserModel::syncFromAppSettings();
        echo "[OK] Users table validated.\n";
    }

    // 3. Sync AUTO_INCREMENT across all operational and child tables
    if (class_exists('OperasiModel')) {
        echo "[RUN] Syncing Operasi & Child Tables AUTO_INCREMENT...\n";
        OperasiModel::syncAutoIncrement();
        OperasiModel::syncChildAutoIncrements();
        echo "[OK] Operasi & Child AUTO_INCREMENT synchronized.\n";
    }

    if (class_exists('BraModel')) {
        echo "[RUN] Syncing BraSar & BraSection AUTO_INCREMENT...\n";
        BraModel::syncAutoIncrement();
        echo "[OK] BraSar & BraSection AUTO_INCREMENT synchronized.\n";
    }

    if (class_exists('RenopsModel')) {
        echo "[RUN] Syncing Renops & RenopsSRU AUTO_INCREMENT...\n";
        RenopsModel::syncAutoIncrement();
        echo "[OK] Renops & RenopsSRU AUTO_INCREMENT synchronized.\n";
    }

    if (class_exists('GisModel')) {
        echo "[RUN] Syncing GIS AUTO_INCREMENT...\n";
        GisModel::syncAutoIncrement();
        echo "[OK] GIS AUTO_INCREMENT synchronized.\n";
    }

    echo "\n=== All database migrations completed successfully! ===\n";
    exit(0);
} catch (Throwable $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
