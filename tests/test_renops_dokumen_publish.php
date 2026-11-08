<?php
/**
 * Test: RenopsDokumenPublisher (terbitkan, bekukan salinan, token, cari token, revisi).
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

echo "=== TEST RENOPS DOKUMEN PUBLISH ===\n\n";

$db = getDB();
$idOps = 0;
$tmpPng = '';
$frozenDir = ROOT_DIR . '/uploads/renops_dokumen';
$frozenCount = fn() => count(glob($frozenDir . '/*.{png,jpg,jpeg,webp}', GLOB_BRACE) ?: []);
$pejabatUji = null;
$smc = null;
try {
    // Pejabat SMC default (tercetak di pengesahan): pakai yang ada (NIP dipulihkan) atau buat sementara.
    if (!PejabatModel::getDefaultSmc()) {
        $pejabatUji = 'uji-smc-' . bin2hex(random_bytes(4));
        $db->prepare("INSERT INTO Pejabat (id, nama, nip, pangkat, jabatan, is_default_smc) VALUES (?, 'SMC Uji', '197001012000031001', 'Pembina IV/a', 'Kepala Kantor', 1)")
            ->execute([$pejabatUji]);
    }
    $smc = PejabatModel::getDefaultSmc();

    $idOps = OperasiModel::create([
        'nama_operasi' => 'Operasi Uji Terbit',
        'nama_pelapor' => 'Uji',
        'lokasi_kejadian' => 'Perairan Uji',
        'waktu_kejadian' => '2026-10-21 06:00:00',
        'waktu_lapor' => '2026-10-21 06:30:00',
        'jumlah_pob' => 1,
        'korban' => [['nama_korban' => 'Nurdin', 'jk' => 'L', 'umur' => '31', 'alamat' => 'Dusun Uji', 'status_korban' => 'DP']],
    ]);
    $idR = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-10-21', 'srus' => []]);
    $idRBaru = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-10-22', 'srus' => []]);

    ob_start();
    $im = imagecreatetruecolor(32, 18);
    imagepng($im);
    $pngB64 = base64_encode((string)ob_get_clean());
    $capture = fn() => DokumenPetaSnapshotModel::saveSnapshot(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS', 'image_data' => $pngB64]);
    $capture();
    $tmpPng = tempnam(sys_get_temp_dir(), 'rdp');
    imagepng($im, $tmpPng);
    RenopsFotoModel::store($idR, $tmpPng, (int)filesize($tmpPng), null, 'Pemberangkatan tim');

    assertCondition(RenopsDokumenPublisher::hasUnpublishedChanges($idR) === true, 'Belum pernah terbit: ada perubahan untuk diterbitkan');
    assertCondition(RenopsDokumenPublisher::publish($idRBaru, null, 3) === null && RenopsDokumenModel::getDraft($idRBaru) === null, 'Tanpa draf & rev bukan 0: tidak terbit, draf tidak dibuat');

    $p1 = RenopsDokumenPublisher::publish($idR, 'uji-user', 0);
    assertCondition($p1['versi'] === 1 && (bool)preg_match('/^[0-9A-F]{16}$/', $p1['token']), 'Terbit v1 dengan token 16 hex');
    $v1 = RenopsDokumenModel::findByToken($p1['token']);
    assertCondition($v1['status'] === 'TERBIT' && $v1['content_hash'] === hash('sha256', $v1['konten_json']), 'Versi TERBIT + hash isi');
    assertCondition($p1['token'] === strtoupper(substr(hash_hmac('sha256', "RENOPS_DOC:{$idR}:1:{$v1['content_hash']}", APP_KEY), 0, 16)), 'Token sesuai rumus HMAC');
    assertCondition($v1['issued_by'] === 'uji-user' && $v1['issued_at'] !== null && (int)$v1['id_operasi'] === $idOps, 'issued_by/issued_at/id_operasi tercatat');
    $draf = RenopsDokumenModel::getDraft($idR);
    assertCondition($draf !== null && $draf['rev'] === 0 && $draf['konten_json'] === '{"sections":{}}', 'Draf kosong rev 0 dibuat saat terbit pertama');
    assertCondition($p1['verify_url'] === BASE_URL . "/operasi.php?action=verify&type=renops&id={$idOps}&ref={$idR}&token={$p1['token']}"
        && $p1['public_url'] === BASE_URL . "/operasi.php?action=renops_publik&token={$p1['token']}", 'URL verifikasi & publik');
    assertCondition(!str_contains($v1['konten_json'], "\n") && str_contains($v1['konten_json'], '"struktur":{'), 'konten_json ringkas (tanpa pretty print)');
    assertCondition(RenopsDokumenPublisher::hasUnpublishedChanges($idR) === false, 'Sesudah terbit: tidak ada perubahan');

    // R13: terbit ulang tanpa perubahan (klik ganda) = versi yang sama, tanpa salinan baru.
    $digantikan = function () use ($db, &$idR): int {
        $st = $db->prepare("SELECT COUNT(*) FROM RenopsDokumen WHERE id_renops = ? AND status = 'DIGANTIKAN'");
        $st->execute([$idR]);
        return (int)$st->fetchColumn();
    };
    $nBeku = $frozenCount();
    $p1b = RenopsDokumenPublisher::publish($idR, null, 0);
    assertCondition($p1['baru'] === true && $p1b === array_merge($p1, ['baru' => false]), 'Terbit ulang tanpa perubahan: versi/token sama, baru=false');
    assertCondition($digantikan() === 0 && $frozenCount() === $nBeku && RenopsDokumenModel::findByToken($p1['token'])['status'] === 'TERBIT', 'Terbit ulang tanpa perubahan: tanpa DIGANTIKAN, tanpa salinan baru');

    $fotoV1 = $v1['konten']['foto'][0]['path'] ?? null;
    assertCondition($fotoV1 !== null && str_starts_with($fotoV1, 'uploads/renops_dokumen/') && is_file(ROOT_DIR . '/' . $fotoV1)
        && $v1['konten']['foto'][0]['keterangan'] === 'Pemberangkatan tim', 'Foto dibekukan ke uploads/renops_dokumen');
    assertCondition(is_file($frozenDir . '/index.php'), 'index.php 403 dibuat di folder salinan beku');

    $db->prepare("UPDATE Korban SET status_korban='S' WHERE id_operasi=?")->execute([$idOps]);
    assertCondition(str_contains(json_encode(RenopsDokumenModel::findByToken($p1['token'])['konten']), 'Hilang'), 'Beku: status korban baru tidak mengubah versi terbit');
    $petaV1 = $v1['konten']['peta']['path'];
    $capture();
    assertCondition($petaV1 !== null && is_file(ROOT_DIR . '/' . $petaV1) && str_starts_with($petaV1, 'uploads/renops_dokumen/')
        && (bool)preg_match('#^uploads/renops_dokumen/[0-9a-f]{32}\.png$#', $petaV1), 'Peta beku tetap ada setelah capture ulang');
    assertCondition(RenopsDokumenPublisher::hasUnpublishedChanges($idR) === true, 'Data sumber berubah: ada perubahan belum terbit');

    $p2 = RenopsDokumenPublisher::publish($idR, null, (int)RenopsDokumenModel::getDraft($idR)['rev']);
    assertCondition(RenopsDokumenModel::findByToken($p1['token'])['status'] === 'DIGANTIKAN' && RenopsDokumenModel::findByToken($p2['token'])['status'] === 'TERBIT' && $p2['versi'] === 2, 'Revisi: v1 DIGANTIKAN, v2 TERBIT');
    assertCondition(RenopsDokumenPublisher::publish($idR, null, 999) === null, 'Rev draf usang tidak bisa terbit');

    // R13: suntingan draf = perubahan, terbit lagi menghasilkan versi baru.
    $revBaru = RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Misi revisi'], 0);
    $p3 = RenopsDokumenPublisher::publish($idR, null, (int)$revBaru);
    $v3 = RenopsDokumenModel::findByToken($p3['token']);
    assertCondition($p3['baru'] === true && $p3['versi'] === 3 && $v3['konten']['sections']['mission'] === 'Misi revisi'
        && RenopsDokumenModel::findByToken($p2['token'])['status'] === 'DIGANTIKAN' && $digantikan() === 2, 'Sesudah suntingan draf: versi baru v3, v2 DIGANTIKAN');

    // R14: koreksi NIP SMC (hanya tercetak di pengesahan) = perubahan isi.
    $nipUji = $smc['nip'] === '199912312020121999' ? '199912312020121998' : '199912312020121999';
    $db->prepare("UPDATE Pejabat SET nip = ? WHERE id = ?")->execute([$nipUji, $smc['id']]);
    $adaUbahNip = RenopsDokumenPublisher::hasUnpublishedChanges($idR);
    $p4 = RenopsDokumenPublisher::publish($idR, null, (int)$revBaru);
    assertCondition($adaUbahNip === true && $p4['baru'] === true && $p4['versi'] === 4
        && RenopsDokumenModel::findByToken($p4['token'])['konten']['pengesahan']['smc_nip'] === $nipUji, 'Koreksi NIP SMC: ada perubahan, terbit v4 baru');

    // R14: menutup operasi bukan perubahan isi (K16: terbit tetap boleh).
    $db->prepare("UPDATE Operasi SET status = 'CLOSED' WHERE id_operasi = ?")->execute([$idOps]);
    $p4b = RenopsDokumenPublisher::publish($idR, null, (int)$revBaru);
    assertCondition(RenopsDokumenPublisher::hasUnpublishedChanges($idR) === false && $p4b['baru'] === false && $p4b['token'] === $p4['token'], 'Operasi CLOSED: tidak dihitung perubahan');

    // Gagal di tengah (foto hilang dari disk): rollback, salinan yang sempat dibuat dihapus.
    $db->prepare("INSERT INTO RenopsFoto (id_renops, path, keterangan, urutan) VALUES (?, 'uploads/renops_foto/hilang.jpg', '', 99)")->execute([$idR]);
    $sebelum = $frozenCount();
    $gagal = false;
    try {
        RenopsDokumenPublisher::publish($idR, null, (int)$revBaru);
    } catch (RuntimeException) {
        $gagal = true;
    }
    assertCondition($gagal && $frozenCount() === $sebelum && RenopsDokumenModel::getLatestTerbit($idR)['token'] === $p4['token'] && !$db->inTransaction(), 'Gagal salin: rollback, tanpa sisa salinan, v4 tetap TERBIT');
    $db->prepare("DELETE FROM RenopsFoto WHERE id_renops = ? AND urutan = 99")->execute([$idR]);

    $files = RenopsDokumenModel::collectFilePaths($idR);
    assertCondition(in_array($petaV1, $files, true) && in_array($fotoV1, $files, true), 'collectFilePaths memuat salinan beku v1 (peta & foto)');
    RenopsModel::delete($idR);
    assertCondition(RenopsDokumenModel::findByToken($p2['token']) === null && RenopsDokumenModel::findByToken($p4['token']) === null
        && !array_filter($files, fn($f) => is_file(ROOT_DIR . '/' . $f)), 'Hapus RENOPS: token mati & berkas beku terhapus');
} finally {
    if ($idOps) {
        OperasiModel::delete($idOps);
    }
    if ($pejabatUji) {
        $db->prepare("DELETE FROM Pejabat WHERE id = ?")->execute([$pejabatUji]);
    } elseif ($smc) {
        $db->prepare("UPDATE Pejabat SET nip = ? WHERE id = ?")->execute([$smc['nip'], $smc['id']]);
    }
    if ($tmpPng !== '') {
        @unlink($tmpPng);
    }
}

echo "\nHasil: {$testPassed}/{$testTotal} lulus\n";
exit($testPassed === $testTotal ? 0 : 1);
