<?php
/**
 * QR-SAR - Pengaman Test Suite
 * Test membuat, mengubah dan menghapus data. Hanya boleh berjalan dari CLI
 * dan terhadap database khusus test (nama berakhiran _test), agar data operasi asli tidak tersentuh.
 *
 * Contoh:
 *   DB_DATABASE=qrsar_test php database/migrate.php
 *   DB_DATABASE=qrsar_test php tests/run_all.php
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Akses Ditolak: Test hanya dapat dijalankan melalui terminal (CLI).\n");
}

require_once dirname(__DIR__) . '/config/app.php';

$qrsarTestDb = (string)getDB()->query('SELECT DATABASE()')->fetchColumn();
if (!str_ends_with($qrsarTestDb, '_test')) {
    fwrite(STDERR, "Test dibatalkan: database aktif '{$qrsarTestDb}' bukan database test (nama harus berakhiran _test).\n"
        . "Jalankan dengan database test, misalnya:\n"
        . "  DB_DATABASE=qrsar_test php database/migrate.php\n"
        . "  DB_DATABASE=qrsar_test php tests/run_all.php\n");
    exit(1);
}
