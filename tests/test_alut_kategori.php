<?php
/**
 * Test: kategori Alut & Palsar (ALUT_DARAT/ALUT_LAUT/ALUT_UDARA/PALSAR):
 * normalisasi, tebakan kata kunci, resolver (item → master id → master nama → tebakan), urutan, dan backfill data lama.
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

echo "=== TEST KATEGORI ALUT & PALSAR ===\n\n";

$db = getDB();
$idOps = $idMaster = 0;
try {
    assertCondition(AlutKategori::URUTAN === ['ALUT_DARAT', 'ALUT_LAUT', 'ALUT_UDARA', 'PALSAR']
        && AlutKategori::label('ALUT_LAUT') === 'ALUT Laut' && AlutKategori::label('PALSAR') === 'PALSAR', 'kode, urutan & label');
    assertCondition(AlutKategori::normalize(' alut_laut ') === 'ALUT_LAUT' && AlutKategori::normalize('ALUT') === null
        && AlutKategori::normalize('') === null && AlutKategori::normalize(null) === null && AlutKategori::normalize(['x']) === null, 'normalize');
    assertCondition(AlutKategori::tebak('Drone Thermal') === 'ALUT_UDARA' && AlutKategori::tebak('KN SAR Pacitan') === 'PALSAR'
        && AlutKategori::tebak('Longboat Warga') === 'ALUT_LAUT' && AlutKategori::tebak('Carabiner') === 'PALSAR'
        && AlutKategori::tebak('Mobil Ambulans') === 'ALUT_DARAT', 'tebak kata utuh');
    assertCondition(AlutKategori::isAlut('ALUT_UDARA') && !AlutKategori::isAlut('PALSAR'), 'isAlut');

    $db->prepare("INSERT INTO MasterAlut (nama_alut, satuan, kategori) VALUES ('Uji KN Pelangi', 'Unit', 'ALUT_LAUT')")->execute();
    $idMaster = (int)$db->lastInsertId();
    AlutKategori::resetCache();
    assertCondition(AlutKategori::resolve(['nama_alut' => '  uji kn pelangi ']) === 'ALUT_LAUT', 'nama ketik-bebas cocok master (Review Focus 2)');
    assertCondition(AlutKategori::resolve(['id_alut' => $idMaster, 'nama_alut' => 'Nama Lain']) === 'ALUT_LAUT', 'master lewat id_alut');
    assertCondition(AlutKategori::resolve(['nama_alut' => 'Uji KN Pelangi', 'kategori' => 'PALSAR']) === 'PALSAR', 'kategori item menang atas master');
    assertCondition(AlutKategori::resolve(['nama_alut' => 'X', 'kategori' => 'ngawur']) === 'PALSAR', 'kategori tak dikenal → resolver (Review Focus 3)');
    assertCondition(AlutKategori::resolve([]) === 'PALSAR', 'item kosong tidak error (Review Focus 1)');
    assertCondition(array_column(AlutKategori::sort([['nama_alut' => 'Palsar Medis'], ['nama_alut' => 'Drone Thermal'], ['nama_alut' => 'Rescue Car'], ['nama_alut' => 'Rubber Boat']]), 'nama_alut')
        === ['Rescue Car', 'Rubber Boat', 'Drone Thermal', 'Palsar Medis'], 'urutan Darat, Laut, Udara, PALSAR');
    $nl = AlutKategori::normalizeList([['nama_alut' => 'Rescue Car'], ['nama_alut' => 'Senter', 'kategori' => 'alut_darat']]);
    assertCondition($nl[0]['kategori'] === 'ALUT_DARAT' && $nl[1]['kategori'] === 'ALUT_DARAT', 'normalizeList mengisi kategori');

    // Data lama (sebelum fitur): kategori NULL / tidak ada di JSON; satu baris sudah dikoreksi manual.
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Uji Backfill Alut', 'nama_pelapor' => 'P', 'lokasi_kejadian' => 'L',
        'waktu_lapor' => '2026-11-01 08:00:00', 'waktu_berangkat' => '2026-11-01 09:00:00',
        'detail_alut' => [['id_alut' => 0, 'nama_alut' => 'Rescue Car', 'satuan' => 'Unit', 'jumlah_alut' => 1],
                          ['id_alut' => 0, 'nama_alut' => 'Rubber Boat', 'satuan' => 'Unit', 'jumlah_alut' => 1]],
    ]);
    $idR = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-01', 'waktu_mulai_ops' => '2026-11-01 09:00:00']);
    $idL = LaporanModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'jenis_laporan' => 'LAPBANG', 'isi_laporan' => 'uji']);
    $db->prepare("UPDATE DetailAlut SET kategori = NULL WHERE id_operasi = ? AND nama_alut = 'Rescue Car'")->execute([$idOps]);
    $db->prepare("UPDATE DetailAlut SET kategori = 'PALSAR' WHERE id_operasi = ? AND nama_alut = 'Rubber Boat'")->execute([$idOps]);
    $db->prepare("UPDATE Renops SET detail_alut = ? WHERE id_renops = ?")->execute(['[{"nama_alut":"Drone Thermal","satuan":"Unit","jumlah_alut":1}]', $idR]);
    $db->prepare("UPDATE LaporanHarian SET detail_alut = ? WHERE id_laporan = ?")->execute(['[{"nama_alut":"Senter","satuan":"Buah","jumlah_alut":2}]', $idL]);

    $r1 = AlutKategori::backfill($db);
    $detail = $db->query("SELECT nama_alut, kategori FROM DetailAlut WHERE id_operasi = {$idOps} ORDER BY id_detail_alut")->fetchAll(PDO::FETCH_KEY_PAIR);
    $jRen = json_decode((string)$db->query("SELECT detail_alut FROM Renops WHERE id_renops = {$idR}")->fetchColumn(), true);
    $jLap = json_decode((string)$db->query("SELECT detail_alut FROM LaporanHarian WHERE id_laporan = {$idL}")->fetchColumn(), true);
    assertCondition($detail === ['Rescue Car' => 'ALUT_DARAT', 'Rubber Boat' => 'PALSAR'] && ($jRen[0]['kategori'] ?? null) === 'ALUT_UDARA'
        && ($jLap[0]['kategori'] ?? null) === 'PALSAR' && $r1['detail_alut'] >= 1 && $r1['renops'] >= 1 && $r1['laporan'] >= 1,
        'backfill mengisi data lama, kategori manual dipertahankan');
    $r2 = AlutKategori::backfill($db);
    assertCondition($r2 === ['detail_alut' => 0, 'renops' => 0, 'laporan' => 0], 'backfill idempoten (Review Focus 4)');

    $master = $db->query("SELECT LOWER(nama_alut), kategori FROM MasterAlut WHERE LOWER(nama_alut) IN ('rescue car','kn sar pacitan','aqua eye')")->fetchAll(PDO::FETCH_KEY_PAIR);
    assertCondition(array_diff_assoc($master, ['rescue car' => 'ALUT_DARAT', 'kn sar pacitan' => 'ALUT_LAUT', 'aqua eye' => 'PALSAR']) === [], 'isian master awal dari migrasi (baris yang ada di DB test)');
} catch (Throwable $e) {
    assertCondition(false, 'Tanpa exception: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    if ($idOps > 0) {
        OperasiModel::delete($idOps);
    }
    if ($idMaster > 0) {
        $db->prepare('DELETE FROM MasterAlut WHERE id_alut = ?')->execute([$idMaster]);
    }
}

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
