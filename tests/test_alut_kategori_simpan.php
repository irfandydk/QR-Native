<?php
/**
 * Test: kategori alut ikut tersimpan di setiap laporan (DetailAlut, JSON RENOPS & LAPBANG) dan beku
 * terhadap perubahan master; teks ringkas alut_palsar diurutkan per kategori.
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

echo "=== TEST KATEGORI ALUT TERSIMPAN ===\n\n";

$db = getDB();
$idOps = 0;
$masterRc = $db->query("SELECT id_alut, kategori FROM MasterAlut WHERE LOWER(nama_alut) = 'rescue car' LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: null;
$idMasterUji = 0;
try {
    if (!$masterRc) {
        $db->exec("INSERT INTO MasterAlut (nama_alut, satuan, kategori) VALUES ('Rescue Car', 'Unit', 'ALUT_DARAT')");
        $idMasterUji = (int)$db->lastInsertId();
    }
    AlutKategori::resetCache();
    $alut = [
        ['id_alut' => 0, 'nama_alut' => 'Palsar Uji', 'satuan' => 'Set', 'jumlah_alut' => 1],
        ['id_alut' => 0, 'nama_alut' => 'Rescue Car', 'satuan' => 'Unit', 'jumlah_alut' => 1],
        ['id_alut' => 0, 'nama_alut' => 'Senter', 'satuan' => 'Buah', 'jumlah_alut' => 2, 'kategori' => 'alut_darat'],
        ['id_alut' => 0, 'nama_alut' => 'X', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'ngawur'],
        ['id_alut' => 0, 'nama_alut' => 'Rubber Boat', 'satuan' => 'Unit', 'jumlah_alut' => 1],
    ];
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Uji Simpan Kategori', 'nama_pelapor' => 'P', 'lokasi_kejadian' => 'L',
        'waktu_lapor' => '2026-11-01 08:00:00', 'waktu_berangkat' => '2026-11-01 09:00:00', 'detail_alut' => $alut,
    ]);
    $rows = $db->query("SELECT nama_alut, kategori FROM DetailAlut WHERE id_operasi = {$idOps} ORDER BY id_detail_alut")->fetchAll(PDO::FETCH_KEY_PAIR);
    assertCondition($rows === ['Palsar Uji' => 'PALSAR', 'Rescue Car' => 'ALUT_DARAT', 'Senter' => 'ALUT_DARAT', 'X' => 'PALSAR', 'Rubber Boat' => 'ALUT_LAUT'],
        'DetailAlut menyimpan kategori (master, pilihan form dinormalkan, nilai salah → resolver)');
    $teks = (string)$db->query("SELECT alut_palsar FROM Operasi WHERE id_operasi = {$idOps}")->fetchColumn();
    assertCondition($teks === "• 1 Unit Rescue Car\n• 2 Buah Senter\n• 1 Unit Rubber Boat\n• 1 Set Palsar Uji\n• 1 Unit X", 'alut_palsar diurutkan Darat → Laut → Udara → PALSAR');

    // Review Focus 5 (sebelum RENOPS/LAPBANG, yang menyinkronkan alut ke Operasi): master diubah setelah laporan disimpan → laporan lama tetap.
    $db->exec("UPDATE MasterAlut SET kategori = 'PALSAR' WHERE LOWER(nama_alut) = 'rescue car'");
    AlutKategori::resetCache();
    $rc = array_values(array_filter(OperasiModel::getById($idOps)['detail_alut'], fn($a) => $a['nama_alut'] === 'Rescue Car'))[0];
    assertCondition($rc['kategori'] === 'ALUT_DARAT', 'Laporan lama beku terhadap perubahan kategori master (Review Focus 5)');
    $db->exec("UPDATE MasterAlut SET kategori = 'ALUT_DARAT' WHERE LOWER(nama_alut) = 'rescue car'");
    AlutKategori::resetCache();

    $idR = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-01', 'waktu_mulai_ops' => '2026-11-01 09:00:00',
        'detail_alut' => [['nama_alut' => 'Drone Thermal', 'satuan' => 'Unit', 'jumlah_alut' => 1]]]);
    $jR = json_decode((string)$db->query("SELECT detail_alut FROM Renops WHERE id_renops = {$idR}")->fetchColumn(), true);
    assertCondition(($jR[0]['kategori'] ?? null) === 'ALUT_UDARA', 'RENOPS: JSON detail_alut menyimpan kategori');
    $idL = LaporanModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'jenis_laporan' => 'LAPBANG', 'isi_laporan' => 'uji',
        'detail_alut' => [['nama_alut' => 'Kantong Mayat', 'satuan' => 'Buah', 'jumlah_alut' => 3]]]);
    $jL = json_decode((string)$db->query("SELECT detail_alut FROM LaporanHarian WHERE id_laporan = {$idL}")->fetchColumn(), true);
    assertCondition(($jL[0]['kategori'] ?? null) === 'PALSAR', 'LAPBANG: JSON detail_alut menyimpan kategori');

} catch (Throwable $e) {
    assertCondition(false, 'Tanpa exception: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    if ($masterRc) {
        $db->prepare('UPDATE MasterAlut SET kategori = ? WHERE id_alut = ?')->execute([$masterRc['kategori'], $masterRc['id_alut']]);
    }
    if ($idMasterUji > 0) {
        $db->prepare('DELETE FROM MasterAlut WHERE id_alut = ?')->execute([$idMasterUji]);
    }
    if ($idOps > 0) {
        OperasiModel::delete($idOps);
    }
    AlutKategori::resetCache();
}

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
