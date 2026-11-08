<?php
/**
 * Test: database yang belum menjalankan migrasi 2026_11_06_create_renops_dokumen.sql
 * tidak membuat halaman detail operasi atau penghapusan RENOPS/operasi gagal (1146 table doesn't exist).
 * Tabel belum ada = belum ada dokumen/foto, jadi status & daftar berkas kosong.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testPassed = 0;
$testTotal = 0;

function assertCondition(bool $cond, string $message): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($cond) {
        $testPassed++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
    }
}

function tanpaError(callable $fn): array {
    try {
        return [true, $fn()];
    } catch (Throwable $e) {
        echo "   -> " . get_class($e) . ': ' . $e->getMessage() . "\n";
        return [false, null];
    }
}

echo "=== TEST RENOPS DOKUMEN TANPA MIGRASI ===\n\n";

$db = getDB();
$idOps = 0;
$renamed = false;
try {
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Uji Tanpa Migrasi', 'nama_pelapor' => 'Pelapor', 'lokasi_kejadian' => 'Lokasi',
        'waktu_lapor' => '2026-11-01 08:00:00', 'waktu_berangkat' => '2026-11-01 09:00:00',
    ]);
    $idR = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-01', 'waktu_mulai_ops' => '2026-11-01 09:00:00']);

    $db->exec('RENAME TABLE RenopsDokumen TO RenopsDokumen_uji_bak, RenopsFoto TO RenopsFoto_uji_bak');
    $renamed = true;

    [$ok, $status] = tanpaError(fn() => RenopsDokumenModel::statusByOperasi($idOps));
    assertCondition($ok && $status === [], 'statusByOperasi: tabel belum ada → [] (detail operasi tetap tampil)');
    [$ok, $paths] = tanpaError(fn() => RenopsDokumenModel::collectFilePaths($idR));
    assertCondition($ok && $paths === [], 'collectFilePaths: tabel belum ada → []');
    [$ok, $paths] = tanpaError(fn() => RenopsDokumenModel::collectFilePathsForOperasi($idOps));
    assertCondition($ok && $paths === [], 'collectFilePathsForOperasi: tabel belum ada → []');
    [$ok, $hasil] = tanpaError(fn() => RenopsModel::delete($idR));
    assertCondition($ok && $hasil === true, 'Hapus RENOPS tetap berhasil sebelum migrasi');
    [$ok, $hasil] = tanpaError(fn() => OperasiModel::delete($idOps));
    assertCondition($ok && $hasil === true, 'Hapus operasi tetap berhasil sebelum migrasi');
    if ($ok && $hasil === true) {
        $idOps = 0;
    }

    [$ok] = tanpaError(fn() => RenopsDokumenModel::getDraft($idR));
    assertCondition(!$ok, 'Error lain (mis. editor membaca draf) tidak disembunyikan: tetap melempar');
} finally {
    if ($renamed) {
        $db->exec('RENAME TABLE RenopsDokumen_uji_bak TO RenopsDokumen, RenopsFoto_uji_bak TO RenopsFoto');
    }
    if ($idOps > 0) {
        OperasiModel::delete($idOps);
    }
}

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
