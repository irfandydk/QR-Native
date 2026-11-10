<?php
/**
 * Test: verifikasi QR Dokumen RENOPS (SAH / DOKUMEN TELAH DIUBAH / tidak valid), halaman publik bertoken,
 * dan kompatibilitas QR lama (DokumenToken).
 * Bagian HTTP hanya jalan bila QRSAR_TEST_URL diisi, mis.:
 *   QRSAR_TEST_URL=http://localhost:8091 DB_DATABASE=qrsar_test php tests/test_renops_dokumen_verify.php
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

/**
 * Jalankan OperasiController::index() di proses terpisah (halaman cetak memanggil exit).
 * @return array{0:int,1:string,2:string} [status, body, pesan flash]
 */
function runAction(array $get, array $session = []): array {
    $script = 'require ' . var_export(dirname(__DIR__) . '/config/app.php', true) . ';'
        . ' $_SESSION = array_merge($_SESSION ?? [], ' . var_export($session, true) . ');'
        . ' $_GET = ' . var_export(array_map('strval', $get), true) . '; $_SERVER["REQUEST_METHOD"] = "GET"; ob_start();'
        . ' register_shutdown_function(function () { $b = ""; while (ob_get_level() > 0) { $b = (string)ob_get_clean() . $b; }'
        . ' echo "STATUS:" . (http_response_code() ?: 200) . "\nFLASH:" . ($_SESSION["flash"]["message"] ?? "") . "\n" . $b; });'
        . ' (new OperasiController())->index();';
    $out = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');
    if (!preg_match('/STATUS:(\d+)\nFLASH:([^\n]*)\n/', $out, $m, PREG_OFFSET_CAPTURE)) {
        return [500, $out, ''];
    }
    return [(int)$m[1][0], substr($out, $m[0][1] + strlen($m[0][0])), $m[2][0]];
}

echo "=== TEST RENOPS DOKUMEN VERIFIKASI & PUBLIK ===\n\n";

$db = getDB();
$idOps = 0;
$pejUji = [];
// SMC default milik test (dipulihkan di finally): hasil tidak bergantung isi tabel Pejabat.
$prevDefault = $db->query("SELECT id FROM Pejabat WHERE is_default_smc = 1")->fetchAll(PDO::FETCH_COLUMN);
try {
    $pejUji[] = PejabatModel::create(['nama' => 'Smc Awal Uji', 'nip' => '199900000000000000', 'pangkat' => 'Pangkat Awal', 'is_default_smc' => 1]);
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Operasi Uji Verifikasi Dokumen',
        'nama_pelapor' => 'Uji',
        'lokasi_kejadian' => 'Perairan Uji',
        'waktu_kejadian' => '2026-10-21 06:00:00',
        'waktu_lapor' => '2026-10-21 06:30:00',
        'jumlah_pob' => 1,
    ]);
    $idR = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-10-21', 'srus' => []]);
    $idRLama = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-10-22', 'srus' => []]);
    $operasi = OperasiModel::getById($idOps);
    // QR portrait lama (token DokumenToken / HMAC lama)
    $tokLama = DocVerifyHelper::generateToken($operasi, RenopsModel::getById($idR));
    $tokLamaTanpaTerbit = DocVerifyHelper::generateToken($operasi, RenopsModel::getById($idRLama));

    $p1 = RenopsDokumenPublisher::publish($idR, null, 0);
    $rev = RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Misi revisi uji verifikasi'], (int)RenopsDokumenModel::getDraft($idR)['rev']);
    $p2 = RenopsDokumenPublisher::publish($idR, null, (int)$rev);
    assertCondition($p1['versi'] === 1 && $p2['versi'] === 2 && $p2['baru'] === true, 'Persiapan: dua versi terbit (v1 DIGANTIKAN, v2 TERBIT)');

    // 1. resolveRenopsVerification
    $v = OperasiController::resolveRenopsVerification($p2['token'], $idOps, $idR);
    assertCondition(($v['status'] ?? null) === 'SAH' && str_contains($v['open_url'], $p2['token'])
        && $v['versi'] === 2 && $v['latest_versi'] === 2 && $v['issued_at'] !== '', 'Token versi terbaru: SAH');
    $v = OperasiController::resolveRenopsVerification($p1['token'], $idOps, $idR);
    assertCondition(($v['status'] ?? null) === 'DIUBAH' && $v['versi'] === 1 && $v['latest_versi'] === 2
        && str_contains($v['open_url'], $p2['token']), 'Token lama: DIUBAH + tautan versi terbaru');
    assertCondition(OperasiController::resolveRenopsVerification($p2['token'], $idOps, $idR + 999) === null, 'Token tidak cocok ref → null');
    assertCondition(OperasiController::resolveRenopsVerification($p2['token'], $idOps + 999, $idR) === null, 'Token tidak cocok operasi → null');
    assertCondition(OperasiController::resolveRenopsVerification($tokLama, $idOps, $idR) === null, 'Token QR lama tidak ada di RenopsDokumen → null (jalur lama)');

    // 2. Halaman verifikasi (tanpa login)
    [$c, $b] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idR, 'token' => $p2['token']]);
    assertCondition($c === 200 && str_contains($b, 'SAH SECARA ELEKTRONIK') && str_contains($b, 'Buka dokumen')
        && str_contains($b, e(RenopsDokumenPublisher::publicUrl($p2['token']))) && !str_contains($b, 'DOKUMEN TELAH DIUBAH'), "Verifikasi v2: SAH + Buka dokumen (dapat {$c})");
    assertCondition(str_contains($b, 'tercatat sah') && str_contains($b, 'data-verify-icon="sah"')
        && !str_contains($b, 'Versi ini telah digantikan'), 'Verifikasi v2: pernyataan sah + ikon hijau tetap');
    [$c, $b] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idR, 'token' => $p1['token']]);
    assertCondition($c === 200 && str_contains($b, 'DOKUMEN TELAH DIUBAH') && str_contains($b, 'Versi v1 telah digantikan oleh v2.')
        && str_contains($b, 'Klik di sini untuk membuka dokumen') && str_contains($b, e(RenopsDokumenPublisher::publicUrl($p2['token'])))
        && !str_contains($b, 'SAH SECARA ELEKTRONIK'), "Verifikasi v1: DOKUMEN TELAH DIUBAH + tautan v2 (dapat {$c})");
    assertCondition(!str_contains($b, 'tercatat sah') && str_contains($b, 'Versi ini telah digantikan; gunakan versi terbaru.')
        && str_contains($b, 'data-verify-icon="diubah"') && !str_contains($b, 'data-verify-icon="sah"'), 'Verifikasi v1: tanpa pernyataan sah, ikon amber');
    [$c, $b] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idR, 'token' => $tokLama]);
    assertCondition($c === 200 && str_contains($b, 'DOKUMEN TELAH DIUBAH') && str_contains($b, e(RenopsDokumenPublisher::publicUrl($p2['token']))),
        "QR lama sah + sudah ada versi TERBIT: DOKUMEN TELAH DIUBAH (dapat {$c})");
    [$c, $b] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idRLama, 'token' => $tokLamaTanpaTerbit]);
    assertCondition($c === 200 && str_contains($b, 'SAH SECARA ELEKTRONIK') && !str_contains($b, 'DOKUMEN TELAH DIUBAH')
        && !str_contains($b, 'renops_publik'), "QR lama sah tanpa versi TERBIT: halaman SAH lama (dapat {$c})");
    [$c, $b] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idR, 'token' => 'ABCDEF0123456789']);
    assertCondition($c === 404 && !str_contains($b, 'SAH SECARA ELEKTRONIK'), "Token palsu: tidak valid 404 (dapat {$c})");

    // 3. Halaman publik bertoken
    [$c, $b] = runAction(['action' => 'renops_publik', 'token' => $p2['token']]);
    assertCondition($c === 200 && str_contains($b, 'MISSION (TUGAS)') && str_contains($b, 'Misi revisi uji verifikasi')
        && str_contains($b, '<meta name="robots" content="noindex, nofollow">') && !str_contains($b, 'telah digantikan'), "Publik v2: dokumen beku, noindex (dapat {$c})");
    assertCondition(str_contains($b, 'data-qr-url="' . e(RenopsDokumenPublisher::verifyUrl(RenopsDokumenModel::findByToken($p2['token']))) . '"'), 'Publik v2: QR menunjuk URL verifikasi versi itu');
    [$c, $b] = runAction(['action' => 'renops_publik', 'token' => $p1['token']]);
    assertCondition($c === 200 && str_contains($b, 'Versi ini telah digantikan oleh v2') && str_contains($b, e(RenopsDokumenPublisher::publicUrl($p2['token'])))
        && !str_contains($b, 'Misi revisi uji verifikasi'), "Publik v1 (DIGANTIKAN): isi v1 + banner ke v2 (dapat {$c})");
    foreach (['acak' => 'ABCDEF0123456789', 'huruf kecil' => strtolower($p2['token']), 'format salah' => "' OR 1=1 --", 'kosong' => ''] as $label => $tok) {
        [$c, $b] = runAction(['action' => 'renops_publik', 'token' => $tok]);
        assertCondition($c === 404 && !str_contains($b, 'MISSION (TUGAS)'), "Publik token {$label}: tidak valid 404 (dapat {$c})");
    }

    // 4. Cetak internal (wajib login): versi TERBIT terakhir; belum terbit / ?draf=1 → pratinjau DRAF tanpa QR
    [, $b, $flash] = runAction(['action' => 'print_renops', 'id_renops' => $idR]);
    assertCondition(!str_contains($b, 'MISSION (TUGAS)') && $flash === 'Sesi login diperlukan untuk mengakses sistem ini.', 'Cetak tanpa login: ditolak ke login');
    $login = ['user_id' => (string)$db->query("SELECT id FROM `Users` WHERE is_active = 1 LIMIT 1")->fetchColumn(), 'auth_at' => time()];
    [$c, $b] = runAction(['action' => 'print_renops', 'id_renops' => $idRLama], $login);
    assertCondition($c === 200 && str_contains($b, 'MISSION (TUGAS)') && str_contains($b, 'renops-watermark') && !str_contains($b, 'id="qrCanvas"')
        && str_contains($b, 'id="btnDownloadPdf"') && str_contains($b, '_DRAF_'), "Cetak tanpa versi TERBIT: pratinjau DRAF tanpa QR + tombol PDF (dapat {$c})");
    [$c, $b] = runAction(['action' => 'print_renops', 'id_renops' => $idR, 'draf' => 1], $login);
    assertCondition($c === 200 && str_contains($b, 'renops-watermark') && !str_contains($b, 'id="qrCanvas"'), "Cetak draf (?draf=1) walau sudah terbit: tanpa QR (dapat {$c})");
    [, $b, $flash] = runAction(['action' => 'print_renops', 'id_renops' => $idRLama, 'draf' => 1]);
    assertCondition(!str_contains($b, 'MISSION (TUGAS)') && $flash === 'Sesi login diperlukan untuk mengakses sistem ini.', 'Cetak draf tanpa login: ditolak ke login');
    [$c, $b] = runAction(['action' => 'print_renops', 'id' => $idOps, 'id_renops' => $idR, 'download_pdf' => 1], $login);
    assertCondition($c === 200 && str_contains($b, 'Misi revisi uji verifikasi') && str_contains($b, 'id="btnDownloadPdf"')
        && str_contains($b, 'href="' . e(BASE_URL . '/operasi.php?action=renops_dokumen&id_renops=' . $idR) . '"') && !str_contains($b, 'noindex'),
        "Cetak internal: versi TERBIT terakhir + tombol PDF + kembali ke editor (dapat {$c})");
    if (class_exists('RenopsPdfService') && RenopsPdfService::findChromeBinary() !== null) {
        [$cPdf, $bPdf] = runAction(['action' => 'print_renops', 'id' => $idOps, 'id_renops' => $idR, 'format' => 'pdf'], $login);
        assertCondition($cPdf === 200 && str_starts_with($bPdf, '%PDF-') && strlen($bPdf) > 5000,
            "Unduh PDF Vektor internal (?format=pdf): menghasilkan biner %PDF- valid (dapat {$cPdf})");
    } else {
        assertCondition(class_exists('RenopsPdfService'), 'Class RenopsPdfService harus tersedia untuk ?format=pdf');
    }
    RenopsDokumenPublisher::publish($idRLama, null, 0);
    [$c, $b] = runAction(['action' => 'print_renops', 'id' => $idOps], $login);
    assertCondition($c === 200 && str_contains($b, 'Hari ke-2'), "Cetak tanpa id_renops: RENOPS terakhir operasi (dapat {$c})");

    // 4b. Penandatangan beku: SMC default diganti setelah terbit; verifikasi tetap menampilkan SMC versi itu
    $smcAwal = (string)(RenopsDokumenModel::findByToken($p2['token'])['konten']['pengesahan']['smc_nama'] ?? '');
    $pejUji[] = PejabatModel::create(['nama' => 'Smc Pengganti Uji', 'nip' => '199900000000000001', 'pangkat' => 'Pangkat Uji', 'is_default_smc' => 1]);
    $p3 = RenopsDokumenPublisher::publish($idR, null, (int)RenopsDokumenModel::getDraft($idR)['rev']);
    [$c, $b] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idR, 'token' => $p2['token']]);
    assertCondition($smcAwal === 'Smc Awal Uji' && $c === 200 && str_contains($b, 'DOKUMEN TELAH DIUBAH') && str_contains($b, e(mb_strtoupper($smcAwal)))
        && str_contains($b, 'Pangkat Awal') && !str_contains($b, 'SMC PENGGANTI UJI') && !str_contains($b, 'Pangkat Uji'), "Verifikasi v2 (DIUBAH): SMC beku versi lama (dapat {$c})");
    [$c, $b] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idR, 'token' => $p3['token']]);
    assertCondition($p3['baru'] === true && $c === 200 && str_contains($b, 'SAH SECARA ELEKTRONIK') && str_contains($b, 'SMC PENGGANTI UJI')
        && str_contains($b, 'Pangkat Uji') && str_contains($b, '199900000000000001'), "Verifikasi v3: SMC beku versi baru (dapat {$c})");

    // 5. HTTP (opsional)
    $base = rtrim((string)getenv('QRSAR_TEST_URL'), '/');
    if ($base === '') {
        echo "\n [SKIP] Bagian HTTP dilewati: QRSAR_TEST_URL tidak diisi.\n";
    } else {
        $http = function (string $path) use ($base): array {
            $ch = curl_init($base . $path);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20]);
            $raw = (string)curl_exec($ch);
            $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            return [(int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE), substr($raw, 0, $hs), substr($raw, $hs)];
        };
        [$c, $h, $b] = $http('/operasi.php?action=renops_publik&token=' . $p2['token']);
        assertCondition($c === 200 && (bool)preg_match('/^X-Robots-Tag: noindex, nofollow\r?$/mi', $h)
            && (bool)preg_match('/^Cache-Control: no-store/mi', $h) && str_contains($b, 'MISSION (TUGAS)'), "HTTP publik: 200 + X-Robots-Tag + no-store (dapat {$c})");
        [$c] = $http('/operasi.php?action=renops_publik&token=ABCDEF0123456789');
        assertCondition($c === 404, "HTTP publik token acak: 404 (dapat {$c})");
        [$c, $h] = $http('/operasi.php?action=print_renops&id_renops=' . $idR);
        assertCondition($c === 302 && str_contains($h, '/login.php'), "HTTP print_renops tanpa login: ke login (dapat {$c})");
    }

    // 6. RENOPS dihapus: semua token mati
    RenopsModel::delete($idR);
    assertCondition(OperasiController::resolveRenopsVerification($p2['token'], $idOps, $idR) === null, 'RENOPS dihapus → tidak valid');
    [$c] = runAction(['action' => 'verify', 'type' => 'renops', 'id' => $idOps, 'ref' => $idR, 'token' => $p2['token']]);
    assertCondition($c === 404, "RENOPS dihapus: halaman verifikasi 404 (dapat {$c})");
    [$c] = runAction(['action' => 'renops_publik', 'token' => $p2['token']]);
    assertCondition($c === 404, "RENOPS dihapus: halaman publik 404 (dapat {$c})");
} catch (Throwable $e) {
    assertCondition(false, 'Error tak terduga: ' . $e->getMessage());
} finally {
    if ($idOps) {
        OperasiModel::delete($idOps);
    }
    foreach ($pejUji as $pid) {
        $db->prepare("DELETE FROM Pejabat WHERE id = :id")->execute([':id' => $pid]);
    }
    $db->query("UPDATE Pejabat SET is_default_smc = 0");
    foreach ($prevDefault as $pid) {
        $db->prepare("UPDATE Pejabat SET is_default_smc = 1 WHERE id = :id")->execute([':id' => $pid]);
    }
}

echo "\nHasil: {$testPassed}/{$testTotal} lulus\n";
exit($testPassed === $testTotal ? 0 : 1);
