<?php
/**
 * Test: pulih dari cadangan sebelum fitur kategori (temuan review akhir #1).
 * Restore SQL lama menghapus kolom kategori (tabel migrations tetap mencatat migrasi sudah jalan),
 * restore JSON lama mengisi master dengan bawaan PALSAR. AlutKategori::pulihkan() harus memulihkan
 * kolom, isian master (daftar baku + tebakan) dan item laporan, tanpa menimpa kategori yang sudah ada.
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

echo "=== TEST PULIH KATEGORI ALUT DARI CADANGAN LAMA ===\n\n";

$db = getDB();
$kolom = fn(string $t) => in_array('kategori', $db->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN), true);
$idOps = 0;
$idMaster = [];
try {
    foreach (['  KN SAR Pacitan Uji ', 'Mobil Uji Pulih', 'Senter Uji Pulih', 'Drone Uji Pulih'] as $n) {
        $db->prepare("INSERT INTO MasterAlut (nama_alut, satuan) VALUES (?, 'Unit')")->execute([$n]);
        $idMaster[trim($n)] = (int)$db->lastInsertId();
    }
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Uji Pulih Alut', 'nama_pelapor' => 'P', 'lokasi_kejadian' => 'L',
        'waktu_lapor' => '2026-11-01 08:00:00', 'waktu_berangkat' => '2026-11-01 09:00:00',
        'detail_alut' => [['id_alut' => $idMaster['KN SAR Pacitan Uji'], 'nama_alut' => 'KN SAR Pacitan Uji', 'satuan' => 'Unit', 'jumlah_alut' => 1]],
    ]);

    // Restore SQL lama: kolom hilang.
    $db->exec('ALTER TABLE MasterAlut DROP COLUMN kategori');
    $db->exec('ALTER TABLE DetailAlut DROP COLUMN kategori');
    AlutKategori::resetCache();

    $hasil = AlutKategori::pulihkan($db);
    assertCondition($kolom('MasterAlut') && $kolom('DetailAlut'), 'kolom kategori dibuat ulang');
    $m = $db->query('SELECT id_alut, kategori FROM MasterAlut WHERE id_alut IN (' . implode(',', $idMaster) . ')')->fetchAll(PDO::FETCH_KEY_PAIR);
    assertCondition($m[$idMaster['Mobil Uji Pulih']] === 'ALUT_DARAT' && $m[$idMaster['Senter Uji Pulih']] === 'PALSAR' && $m[$idMaster['Drone Uji Pulih']] === 'ALUT_UDARA',
        'master diisi ulang dari tebakan kata kunci');
    assertCondition(AlutKategori::BAKU['kn sar pacitan'] === 'ALUT_LAUT' && AlutKategori::BAKU['rescue carrier'] === 'ALUT_DARAT', 'daftar baku master tersedia di PHP');
    $det = $db->query("SELECT kategori FROM DetailAlut WHERE id_operasi = {$idOps}")->fetchColumn();
    assertCondition($det === 'PALSAR' && ($hasil['master'] ?? -1) >= 2 && ($hasil['detail_alut'] ?? -1) >= 1, 'item laporan lama diisi (resolver lewat master)');

    // Kategori yang sudah ada / koreksi manual tidak ditimpa pemulihan berikutnya (kolom sudah ada → master tidak ditebak ulang).
    $db->prepare("UPDATE MasterAlut SET kategori = 'PALSAR' WHERE id_alut = ?")->execute([$idMaster['Drone Uji Pulih']]);
    AlutKategori::pulihkan($db);
    assertCondition($db->query('SELECT kategori FROM MasterAlut WHERE id_alut = ' . $idMaster['Drone Uji Pulih'])->fetchColumn() === 'PALSAR', 'koreksi manual master dipertahankan');

    // Restore JSON lama: master tanpa kunci kategori → paksa isi ulang master.
    assertCondition(AlutKategori::cadanganTanpaKategori(['master_alut' => [['id_alut' => 1, 'nama_alut' => 'Rescue Car']]])
        && !AlutKategori::cadanganTanpaKategori(['master_alut' => [['id_alut' => 1, 'nama_alut' => 'Rescue Car', 'kategori' => 'PALSAR']]])
        && !AlutKategori::cadanganTanpaKategori(['data' => []]), 'deteksi cadangan JSON sebelum fitur');
    $db->exec("UPDATE MasterAlut SET kategori = 'PALSAR' WHERE id_alut IN (" . implode(',', $idMaster) . ')');
    AlutKategori::pulihkan($db, true);
    assertCondition($db->query('SELECT kategori FROM MasterAlut WHERE id_alut = ' . $idMaster['Mobil Uji Pulih'])->fetchColumn() === 'ALUT_DARAT', 'pulihkan(paksa) mengisi ulang master bawaan PALSAR');

    // Setelah pulih, simpan Laporan Awal kembali berjalan.
    OperasiModel::update($idOps, ['nama_operasi' => 'Uji Pulih Alut', 'nama_pelapor' => 'P', 'lokasi_kejadian' => 'L',
        'waktu_lapor' => '2026-11-01 08:00:00', 'waktu_berangkat' => '2026-11-01 09:00:00',
        'detail_alut' => [['id_alut' => 0, 'nama_alut' => 'Rescue Car', 'satuan' => 'Unit', 'jumlah_alut' => 1]]]);
    assertCondition($db->query("SELECT kategori FROM DetailAlut WHERE id_operasi = {$idOps}")->fetchColumn() === 'ALUT_DARAT', 'simpan Laporan Awal setelah pulih');
} catch (Throwable $e) {
    assertCondition(false, 'Tanpa exception: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    // Jangan tinggalkan DB test tanpa kolom.
    if (!$kolom('MasterAlut')) {
        $db->exec("ALTER TABLE `MasterAlut` ADD COLUMN `kategori` VARCHAR(20) NOT NULL DEFAULT 'PALSAR' AFTER `satuan`");
    }
    if (!$kolom('DetailAlut')) {
        $db->exec("ALTER TABLE `DetailAlut` ADD COLUMN `kategori` VARCHAR(20) NULL DEFAULT NULL AFTER `satuan`");
    }
    if ($idOps > 0) {
        OperasiModel::delete($idOps);
    }
    foreach ($idMaster as $id) {
        $db->prepare('DELETE FROM MasterAlut WHERE id_alut = ?')->execute([$id]);
    }
}

$mig = (string)file_get_contents(ROOT_DIR . '/database/migrate.php');
$bak = (string)file_get_contents(ROOT_DIR . '/services/BackupService.php');
assertCondition(str_contains($mig, 'AlutKategori::pulihkan(') && !str_contains($mig, 'AlutKategori::backfill('), 'migrate.php memakai pulihkan (cek kolom sebelum backfill)');
assertCondition(substr_count($bak, 'AlutKategori::pulihkan(') === 2 && str_contains($bak, 'AlutKategori::cadanganTanpaKategori('), 'restore SQL & JSON memanggil pulihkan');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
