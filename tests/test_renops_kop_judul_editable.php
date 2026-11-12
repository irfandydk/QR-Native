<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

$testTotal = 0; $testPassed = 0;
function assertCondition(bool $cond, string $msg): void {
    global $testTotal, $testPassed;
    $testTotal++;
    if ($cond) { $testPassed++; echo " [PASS] {$msg}\n"; }
    else { echo " [FAIL] {$msg}\n"; }
}

echo "=== TEST RENOPS KOP JUDUL EDITABLE ===\n";

$idOps = OperasiModel::create([
    'nama_operasi' => 'KMM 1 ORANG TENGGELAM DI TELUK KENDARI',
    'jenis_kejadian' => 'Kecelakaan Kapal',
]);
$idRenops = RenopsModel::create([
    'id_operasi' => $idOps,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-11-13',
    'srus' => [],
]);

$doc = RenopsDokumenService::resolve($idRenops);
assertCondition(isset($doc['sections']['judul_operasi']), 'Kontrak data dokumen memuat seksi judul_operasi');
assertCondition($doc['sections']['judul_operasi'] === 'KMM 1 ORANG TENGGELAM DI TELUK KENDARI', 'Default judul_operasi langsung nama operasi murni tanpa jenis_kejadian');

// Simpan suntingan draf
RenopsDokumenModel::saveDraft($idRenops, $idOps, ['judul_operasi' => 'JUDUL HASIL SUNTINGAN MANUAL USER'], 0);
$docEdited = RenopsDokumenService::resolve($idRenops);
assertCondition($docEdited['sections']['judul_operasi'] === 'JUDUL HASIL SUNTINGAN MANUAL USER', 'Suntingan manual judul_operasi tersimpan pada draf');

$view = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php');
assertCondition(str_contains($view, 'data-sec="judul_operasi"') || str_contains($view, "\$sec('judul_operasi'"), 'KOP baris 3 memiliki penanda data-sec editable');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
