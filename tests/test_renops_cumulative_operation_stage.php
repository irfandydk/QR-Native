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

echo "=== TEST RENOPS CUMULATIVE OPERATION STAGE ===\n";

$idOps = OperasiModel::create([
    'nama_operasi' => 'Operasi SAR Uji Kumulatif',
    'waktu_berangkat' => '2026-11-10 07:00:00',
    'tim_berangkat' => 'Tim Alpha',
    'detail_unsur' => [['nama_unsur' => 'Basarnas', 'jumlah_personil' => 6]],
]);

// H1
$idR1 = RenopsModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-10', 'srus' => [],
]);
$doc1 = RenopsDokumenService::resolve($idR1);
$stage1 = $doc1['sections']['operation_stage'];
assertCondition(str_contains($stage1, 'diberangkatkan 6 (enam) orang personel Tim Alpha'), 'H1: memuat pengerahan awal');

// Lapbang H1
LaporanModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.1', 'waktu_laporan' => '2026-11-10 20:00:00',
    'isi_laporan' => 'Hasil H1: Pencarian seluas 10 NM2 nihil korban.',
]);

// H2
$idR2 = RenopsModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-11-11',
    'waktu_mulai_ops' => '2026-11-11 07:00:00', 'srus' => [],
]);
$doc2 = RenopsDokumenService::resolve($idR2);
$stage2 = $doc2['sections']['operation_stage'];
assertCondition(str_contains($stage2, 'Hari ke-1:') && str_contains($stage2, 'Hasil H1: Pencarian seluas 10 NM2 nihil korban.'), 'H2: memuat rekapitulasi Hari ke-1 beserta hasil lapbang H1');
assertCondition(str_contains($stage2, 'Hari ke-2 (Rencana Pelaksanaan):') && str_contains($stage2, 'Operasi SAR Hari ke-2 dilanjutkan'), 'H2: memuat pelaksanaan Hari ke-2');

// Lapbang H2
LaporanModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.2', 'waktu_laporan' => '2026-11-11 20:00:00',
    'isi_laporan' => 'Hasil H2: Ditemukan serpihan perahu.',
]);

// H3
$idR3 = RenopsModel::create([
    'id_operasi' => $idOps, 'hari_ke' => 'H.3', 'tanggal_laporan' => '2026-11-12',
    'waktu_mulai_ops' => '2026-11-12 07:00:00', 'srus' => [],
]);
$doc3 = RenopsDokumenService::resolve($idR3);
$stage3 = $doc3['sections']['operation_stage'];
assertCondition(str_contains($stage3, 'Hari ke-1:') && str_contains($stage3, 'Hari ke-2:') && str_contains($stage3, 'Hari ke-3 (Rencana Pelaksanaan):'), 'H3: memuat kumulatif Hari ke-1, Hari ke-2, dan Hari ke-3');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
