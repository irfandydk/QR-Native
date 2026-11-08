<?php
/**
 * Test: TemplateHelper::terbilang + HierarchyHelper::resolveRenopsSources/absoluteDayNumber.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/RenopsModel.php';
require_once __DIR__ . '/../models/LaporanModel.php';

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

echo "=== TEST RENOPS HIERARKI SUMBER ===\n\n";

$db = getDB();
$idOps = $idOps2 = 0;
try {
    $u = fn(int $n) => array_map(fn($i) => ['nama_unsur' => "Unsur {$i}", 'jumlah_personil' => 2], range(1, $n));
    $mkOps = fn(string $nama) => OperasiModel::create([
        'nama_operasi' => $nama,
        'nama_pelapor' => 'Uji',
        'lokasi_kejadian' => 'Perairan Uji',
        'waktu_kejadian' => '2026-10-21 06:00:00',
        'waktu_lapor' => '2026-10-21 06:30:00',
        'waktu_berangkat' => '2026-10-21 07:00:00',
        'detail_unsur' => $u(2),
    ]);
    $mkRenops = function (int $id, string $tgl, array $unsur = []) use ($db) {
        $rid = RenopsModel::create(['id_operasi' => $id, 'tanggal_laporan' => $tgl, 'srus' => [], 'detail_unsur' => $unsur]);
        if (!$unsur) { // R2: create menyalin unsur Operasi saat input kosong
            $db->prepare("UPDATE Renops SET detail_unsur='[]', detail_alut='[]' WHERE id_renops=?")->execute([$rid]);
        }
        return $rid;
    };
    $mkLap = fn(int $id, string $waktu, array $unsur) => LaporanModel::create([
        'id_operasi' => $id, 'waktu_laporan' => $waktu, 'detail_unsur' => $unsur,
    ]);

    $idOps = $mkOps('Uji Hierarki');
    $idLapH1 = $mkLap($idOps, '2026-10-21 20:00:00', $u(3));
    $r1 = $mkRenops($idOps, '2026-10-21');
    $r2 = $mkRenops($idOps, '2026-10-22', $u(4));
    $r3 = $mkRenops($idOps, '2026-10-23');

    // Create LAPBANG/RENOPS menyinkronkan unsur kembali ke Operasi; kembalikan ke 2 unsur Laporan Awal.
    $db->prepare("DELETE FROM DetailUnsur WHERE id_operasi=? AND nama_unsur NOT IN ('Unsur 1','Unsur 2')")->execute([$idOps]);
    $ops = OperasiModel::getById($idOps);
    assertCondition(count($ops['detail_unsur']) === 2, 'Fixture: Laporan Awal 2 unsur');
    $load = fn(int $id) => RenopsModel::getById($id);

    assertCondition(TemplateHelper::terbilang(8) === 'delapan' && TemplateHelper::terbilang(21) === 'dua puluh satu' && TemplateHelper::terbilang(1250) === 'seribu dua ratus lima puluh', 'terbilang');
    assertCondition(TemplateHelper::terbilang(0) === 'nol' && TemplateHelper::terbilang(12) === 'dua belas' && TemplateHelper::terbilang(100) === 'seratus' && TemplateHelper::terbilang(111) === 'seratus sebelas', 'terbilang tepi');
    assertCondition(HierarchyHelper::absoluteDayNumber('H7 + 1') === 8 && HierarchyHelper::absoluteDayNumber('H.3') === 3, 'Hari absolut');

    $s1 = HierarchyHelper::resolveRenopsSources($ops, $load($r1));
    assertCondition($s1['hari_num'] === 1 && $s1['hari_label'] === '1' && $s1['sumber'] === 'LAPORAN_AWAL' && count($s1['unsur']) === 2, 'H1: Laporan Awal, RENOPS H1 tanpa unsur tidak menimpa');
    $s2 = HierarchyHelper::resolveRenopsSources($ops, $load($r2));
    assertCondition($s2['sumber'] === 'LAPBANG' && (int)$s2['lapbang']['id_laporan'] === $idLapH1, 'H2: sumber LAPBANG H1');
    assertCondition(count($s2['unsur']) === 4, 'H2: RENOPS H2 (4 unsur) menang atas LAPBANG H1 (3 unsur)');
    $s3 = HierarchyHelper::resolveRenopsSources($ops, $load($r3));
    assertCondition($s3['sumber'] === 'LAPBANG' && (int)$s3['lapbang']['id_laporan'] === $idLapH1, 'H3 tanpa LAPBANG H2: pakai LAPBANG terdekat sebelumnya');
    assertCondition(count($s3['unsur']) === 3, 'H3 (RENOPS tanpa unsur) mewarisi 3 unsur LAPBANG terdekat');

    // Reopen: H7 + 1
    $idOps2 = $mkOps('Uji Hierarki Reopen');
    $lapH7 = $mkLap($idOps2, '2026-10-27 20:00:00', $u(3));
    $lapH6 = $mkLap($idOps2, '2026-10-26 20:00:00', $u(1));
    $db->prepare("UPDATE Operasi SET waktu_reopen='2026-11-01 08:00:00', hari_sebelum_reopen=7, waktu_selesai=NULL WHERE id_operasi=?")->execute([$idOps2]);
    $rr = $mkRenops($idOps2, '2026-11-01');
    $ops2 = OperasiModel::getById($idOps2);
    $rrData = $load($rr);
    assertCondition(TemplateHelper::hitungHariKe($ops2, $rrData['tanggal_laporan'], $rrData['hari_ke']) === 'H7 + 1', 'Fixture reopen menghasilkan H7 + 1');
    $sr = HierarchyHelper::resolveRenopsSources($ops2, $rrData);
    assertCondition($sr['hari_label'] === '7 + 1' && $sr['hari_num'] === 8, 'Reopen: label 7 + 1, hari 8');
    assertCondition($sr['sumber'] === 'LAPBANG' && (int)$sr['lapbang']['id_laporan'] === $lapH7, 'Reopen: LAPBANG terakhir sebelum reopen');
} finally {
    foreach ([$idOps, $idOps2] as $id) {
        if ($id) OperasiModel::delete($id);
    }
}

echo "\nHasil: {$testPassed}/{$testTotal} lulus\n";
exit($testPassed === $testTotal ? 0 : 1);
