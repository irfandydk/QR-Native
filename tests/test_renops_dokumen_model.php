<?php
/**
 * Test: RenopsDokumenModel (draf, optimistic locking, cascade, pembersihan berkas).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/RenopsModel.php';
require_once __DIR__ . '/../models/RenopsDokumenModel.php';

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

echo "=== TEST RENOPS DOKUMEN MODEL ===\n\n";

$db = getDB();
$idOps = OperasiModel::create([
    'nama_operasi' => 'Operasi Uji RenopsDokumen',
    'nama_pelapor' => 'Uji',
    'lokasi_kejadian' => 'Perairan Uji',
    'waktu_kejadian' => '2026-10-21 06:00:00',
    'waktu_lapor' => '2026-10-21 06:30:00',
]);
$idR = RenopsModel::create([
    'id_operasi' => $idOps,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'srus' => [],
]);

$rev1 = RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Teks A'], 0);
assertCondition($rev1 === 1, 'Draf pertama tersimpan dengan rev 1');
$d = RenopsDokumenModel::getDraft($idR);
assertCondition($d['konten']['sections']['mission'] === 'Teks A' && $d['status'] === 'DRAF' && (int)$d['versi'] === 0, 'Draf memuat suntingan');
assertCondition(RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Teks B'], 0) === null, 'Rev usang ditolak (bentrok)');
assertCondition(RenopsDokumenModel::getDraft($idR)['konten']['sections']['mission'] === 'Teks A', 'Suntingan pemenang tidak tertimpa');
$rev2 = RenopsDokumenModel::saveDraft($idR, $idOps, ['awareness' => 'X'], $rev1);
assertCondition($rev2 === 2 && isset(RenopsDokumenModel::getDraft($idR)['konten']['sections']['mission']), 'Simpan menggabungkan kunci');
RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => null], $rev2);
assertCondition(!array_key_exists('mission', RenopsDokumenModel::getDraft($idR)['konten']['sections']), 'Nilai null = Reset kunci');

// Versi TERBIT/DIGANTIKAN + foto: path dikumpulkan, hanya path aman yang di-unlink.
$root = ROOT_DIR;
@mkdir("{$root}/uploads/renops_dokumen", 0755, true);
@mkdir("{$root}/uploads/renops_foto", 0755, true);
$fPeta = 'uploads/renops_dokumen/' . bin2hex(random_bytes(8)) . '.png';
$fFoto = 'uploads/renops_foto/' . bin2hex(random_bytes(8)) . '.jpg';
$fLepas = 'uploads/renops_foto/' . bin2hex(random_bytes(8)) . '.jpg';
$fLuar = 'uploads/uji_luar_' . bin2hex(random_bytes(8)) . '.txt';
foreach ([$fPeta, $fFoto, $fLepas, $fLuar] as $f) {
    file_put_contents("{$root}/{$f}", 'x');
}
$ins = $db->prepare("INSERT INTO RenopsDokumen (id_renops, id_operasi, versi, status, konten_json) VALUES (?, ?, ?, ?, ?)");
$ins->execute([$idR, $idOps, 1, 'DIGANTIKAN', json_encode(['peta' => ['path' => $fPeta], 'foto' => [['path' => $fFoto]]])]);
$ins->execute([$idR, $idOps, 2, 'TERBIT', json_encode(['peta' => ['path' => null], 'foto' => [['path' => '../../etc/passwd'], ['path' => $fLuar]]])]);
$db->prepare("INSERT INTO RenopsFoto (id_renops, path) VALUES (?, ?)")->execute([$idR, $fLepas]);

$dupOk = true;
try { $ins->execute([$idR, $idOps, 0, 'DRAF', '{}']); $dupOk = false; } catch (PDOException $e) {}
assertCondition($dupOk, 'DRAF kedua untuk id_renops yang sama ditolak DB');
$dupOk = true;
try { $ins->execute([$idR, $idOps, 3, 'TERBIT', '{}']); $dupOk = false; } catch (PDOException $e) {}
assertCondition($dupOk, 'TERBIT kedua untuk id_renops yang sama ditolak DB');

$paths = RenopsDokumenModel::collectFilePaths($idR);
assertCondition(in_array($fPeta, $paths, true) && in_array($fFoto, $paths, true) && in_array($fLepas, $paths, true), 'collectFilePaths memuat peta, foto versi, dan RenopsFoto');
assertCondition((int)RenopsDokumenModel::getLatestTerbit($idR)['versi'] === 2, 'getLatestTerbit mengembalikan versi TERBIT');
assertCondition(RenopsDokumenModel::statusByOperasi($idOps)[$idR]['terbit_versi'] === 2, 'statusByOperasi melaporkan versi terbit');

// Rollback: hapus operasi gagal -> berkas harus utuh. Paksa gagal lewat trigger BEFORE DELETE pada Renops.
$db->exec("CREATE TRIGGER trg_uji_renops_blok BEFORE DELETE ON Renops FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'blok uji'");
$delOk = RenopsModel::delete($idR);
$db->exec("DROP TRIGGER trg_uji_renops_blok");
assertCondition($delOk === false && RenopsDokumenModel::getLatestTerbit($idR) !== null, 'Hapus RENOPS gagal -> baris tetap ada');
assertCondition(file_exists("{$root}/{$fPeta}") && file_exists("{$root}/{$fFoto}") && file_exists("{$root}/{$fLepas}"), 'Hapus RENOPS gagal (rollback) -> berkas tidak dihapus');

RenopsModel::delete($idR);
assertCondition(RenopsDokumenModel::getDraft($idR) === null, 'Hapus RENOPS ikut menghapus dokumen (cascade)');
assertCondition(!file_exists("{$root}/{$fPeta}") && !file_exists("{$root}/{$fFoto}") && !file_exists("{$root}/{$fLepas}"), 'Berkas peta/foto ikut terhapus');
assertCondition(file_exists("{$root}/{$fLuar}"), 'Path di luar uploads/renops_* tidak disentuh');
unlink("{$root}/{$fLuar}");

// deleteFilesForOperasi
$idR2 = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-10-22', 'srus' => []]);
$f2 = 'uploads/renops_foto/' . bin2hex(random_bytes(8)) . '.jpg';
file_put_contents("{$root}/{$f2}", 'x');
$db->prepare("INSERT INTO RenopsFoto (id_renops, path) VALUES (?, ?)")->execute([$idR2, $f2]);
OperasiModel::delete($idOps);
assertCondition(!file_exists("{$root}/{$f2}"), 'OperasiModel::delete menghapus berkas semua RENOPS operasi');

echo "\nHasil: {$testPassed}/{$testTotal} lulus\n";
exit($testPassed === $testTotal ? 0 : 1);
