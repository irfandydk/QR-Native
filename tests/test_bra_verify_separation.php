<?php
/**
 * QR-SAR Basarnas Kendari - Pengujian Pemisahan Halaman Verifikasi & Lihat Dokumen BRA SAR
 * Memastikan pola pemisahan identik dengan RENOPS:
 * 1. Halaman Verifikasi Khusus (?action=verify&id=&token= + kompatibilitas QR lama ?view=&token=)
 * 2. Halaman Dokumen Publik Read-Only (?action=publik&id=&token= + ?format=pdf)
 * 3. Halaman Internal Petugas (?view= login tanpa kartu verifikasi publik)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

function assertBraSep(bool $cond, string $message): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
    }
}

function invokeBraController(array $get, array $session = []): array {
    $script = 'putenv("DB_DATABASE=" . ' . var_export(getenv('DB_DATABASE') ?: 'qrsar_test', true) . ');'
        . ' require ' . var_export(dirname(__DIR__) . '/config/app.php', true) . ';'
        . ' $_SESSION = array_merge($_SESSION ?? [], ' . var_export($session, true) . ');'
        . ' $_GET = ' . var_export(array_map('strval', $get), true) . '; $_SERVER["REQUEST_METHOD"] = "GET"; ob_start();'
        . ' register_shutdown_function(function () {'
        . '     $b = ""; while (ob_get_level() > 0) { $b = (string)ob_get_clean() . $b; }'
        . '     $headers = headers_list();'
        . '     echo "STATUS:" . (http_response_code() ?: 200) . "\nHEADERS:" . json_encode($headers) . "\n---BODY---\n" . $b;'
        . ' });'
        . ' (new BraController())->handle();';
    $cmd = 'DB_DATABASE=' . escapeshellarg(getenv('DB_DATABASE') ?: 'qrsar_test') . ' ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1';
    $out = (string)shell_exec($cmd);
    if (!preg_match('/STATUS:(\d+)\nHEADERS:([^\n]*)\n---BODY---\n/', $out, $m, PREG_OFFSET_CAPTURE)) {
        return [500, [], $out];
    }
    $status = (int)$m[1][0];
    $headers = json_decode($m[2][0], true);
    if (!is_array($headers)) {
        $headers = [];
    }
    $body = substr($out, $m[0][1] + strlen($m[0][0]));
    return [$status, $headers, $body];
}

echo "=== TEST PEMISAHAN HALAMAN VERIFIKASI & LIHAT DOKUMEN BRA SAR ===\n";

$testBraId = BraModel::create([
    'kategori' => 'OPERASI_SAR',
    'derajat' => 'SGR',
    'panggilan' => 'YCSB',
    'no_bra' => 'BRA/88/KPP-KDI/XI/2026',
    'twu' => '2311 0900 H',
    'perihal' => 'UJI PEMISAHAN VERIFIKASI BRA SAR TTK',
    'aksi' => 'STASIUN RADIO PANTAI KENDARI',
    'info' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    'nama_smc' => 'AMIRUDDIN A.S., S.SOS.',
    'pangkat_smc' => 'PEMBINA (IV/A)',
    'jabatan_smc' => 'KEPALA KANSAR KENDARI',
    'sections' => [
        ['kode' => 'AAA', 'isi' => 'NASKAH RAHASIA INTERNAL OPERASI SAR UJI PEMISAHAN TTK'],
        ['kode' => 'BBB', 'isi' => 'TIM RESCUE DIBERANGKATKAN MENUJU LOKASI TTK HBS'],
    ],
]);

assertBraSep($testBraId > 0, "Data uji BRA berhasil dibuat (ID: {$testBraId})");

try {
    $bra = BraModel::getById($testBraId);
    $token = BraModel::generateVerifyToken($bra);

    $adminUser = UserModel::getById('USR-ADMIN');
    $mockLoginSession = [
        'user_id' => 'USR-ADMIN',
        'role' => 'admin',
        'auth_at' => time(),
        'cred_fp' => hash('sha256', (string)($adminUser['password_hash'] ?? '')),
        'user' => [
            'id_user' => 'USR-ADMIN',
            'nama_lengkap' => $adminUser['nama_lengkap'] ?? 'Administrator',
            'username' => 'admin',
            'role' => 'admin',
        ],
    ];

    // 1. URL Generators
    $verifyUrl = BraModel::generateVerifyUrl($bra);
    assertBraSep(
        str_contains($verifyUrl, '/bra.php?action=verify&id=' . $testBraId . '&token=' . $token),
        "BraModel::generateVerifyUrl() mengarah ke /bra.php?action=verify&id=...&token=..."
    );

    assertBraSep(
        method_exists('BraModel', 'generatePublicDocUrl'),
        'BraModel::generatePublicDocUrl() tersedia'
    );

    if (method_exists('BraModel', 'generatePublicDocUrl')) {
        $publicDocUrl = BraModel::generatePublicDocUrl($bra);
        assertBraSep(
            str_contains($publicDocUrl, '/bra.php?action=publik&id=' . $testBraId . '&token=' . $token),
            "BraModel::generatePublicDocUrl() mengarah ke /bra.php?action=publik&id=...&token=..."
        );
    }

    // 2. Dedicated Verification Page (?action=verify&id=&token=)
    [$stVerify, , $bodyVerify] = invokeBraController([
        'action' => 'verify',
        'id' => (string)$testBraId,
        'token' => $token,
    ]);
    assertBraSep($stVerify === 200, "Halaman verifikasi BRA (?action=verify) mengembalikan HTTP 200 (dapat {$stVerify})");
    assertBraSep(
        str_contains($bodyVerify, 'SAH SECARA ELEKTRONIK')
            && str_contains($bodyVerify, $token)
            && str_contains($bodyVerify, 'AMIRUDDIN A.S., S.SOS.')
            && str_contains($bodyVerify, 'PEMBINA (IV/A)')
            && str_contains($bodyVerify, 'KEPALA KANSAR KENDARI'),
        'Halaman verifikasi BRA menampilkan status sah, token, dan rincian SMC terstruktur'
    );
    assertBraSep(
        str_contains($bodyVerify, 'Buka dokumen')
            && str_contains($bodyVerify, 'action=publik&amp;id=' . $testBraId . '&amp;token=' . $token),
        'Halaman verifikasi BRA menampilkan tombol Buka dokumen menuju ?action=publik'
    );
    assertBraSep(
        !str_contains($bodyVerify, 'id="official-bra-document"')
            && !str_contains($bodyVerify, 'Salin Naskah')
            && !str_contains($bodyVerify, 'Unduh QR')
            && !str_contains($bodyVerify, 'btnDownloadHtml')
            && !str_contains($bodyVerify, 'Edit Form'),
        'Halaman verifikasi BRA terpisah penuh (tanpa lembar telegram #official-bra-document & tanpa tombol internal)'
    );

    // 3. Backward-compatible Legacy QR URL (?view=&token=) renders Verification Page (not merged view)
    [$stLegacyQr, , $bodyLegacyQr] = invokeBraController([
        'view' => (string)$testBraId,
        'token' => $token,
    ]);
    assertBraSep(
        $stLegacyQr === 200
            && str_contains($bodyLegacyQr, 'SAH SECARA ELEKTRONIK')
            && str_contains($bodyLegacyQr, 'Buka dokumen')
            && !str_contains($bodyLegacyQr, 'id="official-bra-document"'),
        'QR lama (?view=ID&token=TOKEN) tetap kompatibel dan membuka halaman verifikasi khusus tanpa lembar telegram'
    );

    // 4. Dedicated Public BRA Document Viewer (?action=publik&id=&token=)
    [$stPub, $hdrPub, $bodyPub] = invokeBraController([
        'action' => 'publik',
        'id' => (string)$testBraId,
        'token' => $token,
    ]);
    assertBraSep($stPub === 200, "Halaman dokumen BRA publik (?action=publik) mengembalikan HTTP 200 (dapat {$stPub})");
    $braCtrlSrc = (string)file_get_contents(dirname(__DIR__) . '/controllers/BraController.php');
    assertBraSep(
        str_contains($bodyPub, '<meta name="robots" content="noindex, nofollow">')
            && str_contains($braCtrlSrc, "header('X-Robots-Tag: noindex, nofollow');")
            && str_contains($braCtrlSrc, "header('Cache-Control: no-store');"),
        'Halaman dokumen BRA publik menerapkan proteksi noindex (meta robots + header X-Robots-Tag & Cache-Control: no-store)'
    );
    assertBraSep(
        str_contains($bodyPub, 'id="official-bra-document"')
            && str_contains($bodyPub, 'id="btnDownloadPdf"')
            && str_contains($bodyPub, 'Unduh PDF')
            && str_contains($bodyPub, 'Cetak / Simpan PDF'),
        'Halaman dokumen BRA publik menampilkan lembar telegram resmi, tombol Unduh PDF, dan Cetak / Simpan PDF'
    );
    assertBraSep(
        !str_contains($bodyPub, 'SAH SECARA ELEKTRONIK')
            && !str_contains($bodyPub, 'Salin Naskah')
            && !str_contains($bodyPub, 'Unduh QR')
            && !str_contains($bodyPub, 'btnDownloadHtml')
            && !str_contains($bodyPub, 'Edit Form'),
        'Halaman dokumen BRA publik bebas dari kartu verifikasi, Salin Naskah, Unduh QR, HTML, dan Edit Form'
    );

    // 5. Public BRA PDF Download (?action=publik&id=&token=&format=pdf)
    [$stPubPdf, , $bodyPubPdf] = invokeBraController([
        'action' => 'publik',
        'id' => (string)$testBraId,
        'token' => $token,
        'format' => 'pdf',
    ]);
    assertBraSep(
        $stPubPdf === 200 && str_starts_with($bodyPubPdf, '%PDF-') && strlen($bodyPubPdf) > 5000,
        "Unduh PDF melalui ?action=publik&format=pdf menghasilkan biner %PDF- valid (status: {$stPubPdf}, size: " . strlen($bodyPubPdf) . " bytes)"
    );

    // 6. Security: Invalid / Missing Tokens rejected with HTTP 404 & zero leakage
    [$stBadVerify, , $bodyBadVerify] = invokeBraController([
        'action' => 'verify',
        'id' => (string)$testBraId,
        'token' => 'INVALID000000000',
    ]);
    assertBraSep(
        $stBadVerify === 404
            && str_contains($bodyBadVerify, 'VERIFIKASI GAGAL')
            && !str_contains($bodyBadVerify, 'NASKAH RAHASIA INTERNAL')
            && !str_contains($bodyBadVerify, 'Salin Laporan Audit'),
        'Token tidak valid pada ?action=verify ditolak HTTP 404 tanpa bocor isi naskah & tanpa tombol Salin Laporan Audit'
    );

    [$stNoTokVerify] = invokeBraController([
        'action' => 'verify',
        'id' => (string)$testBraId,
    ]);
    assertBraSep($stNoTokVerify === 404, 'Permintaan ?action=verify tanpa token ditolak HTTP 404');

    [$stBadPub, , $bodyBadPub] = invokeBraController([
        'action' => 'publik',
        'id' => (string)$testBraId,
        'token' => 'INVALID000000000',
    ]);
    assertBraSep(
        $stBadPub === 404 && !str_contains($bodyBadPub, 'id="official-bra-document"'),
        'Token tidak valid pada ?action=publik ditolak HTTP 404 tanpa merender naskah'
    );

    [$stNoTokPub] = invokeBraController([
        'action' => 'publik',
        'id' => (string)$testBraId,
    ], $mockLoginSession);
    assertBraSep($stNoTokPub === 404, 'Permintaan ?action=publik tanpa token ditolak HTTP 404');

    // 7. Internal Authenticated View (?view=ID without token)
    [$stInternal, , $bodyInternal] = invokeBraController([
        'view' => (string)$testBraId,
    ], $mockLoginSession);
    assertBraSep($stInternal === 200, "Halaman internal melihat BRA (?view=ID) mengembalikan HTTP 200 (dapat {$stInternal})");
    assertBraSep(
        str_contains($bodyInternal, 'id="official-bra-document"')
            && str_contains($bodyInternal, 'Edit Form')
            && str_contains($bodyInternal, 'id="paperSizeSelect"')
            && str_contains($bodyInternal, 'id="btnZoomIn"')
            && str_contains($bodyInternal, 'id="btnZoomOut"')
            && str_contains($bodyInternal, 'id="btnDownloadPdf"')
            && str_contains($bodyInternal, 'Cetak / Simpan PDF'),
        'Halaman internal melihat BRA menyediakan toolbar ringkas (Edit Form, Kertas & Zoom, Unduh PDF, Cetak PDF) & lembar resmi'
    );
    assertBraSep(
        !str_contains($bodyInternal, 'Salin Naskah')
            && !str_contains($bodyInternal, 'btnCopyTelegram')
            && !str_contains($bodyInternal, 'Unduh QR')
            && !str_contains($bodyInternal, 'btnDownloadQr')
            && !str_contains($bodyInternal, 'btnDownloadHtml'),
        'Halaman internal melihat BRA bersih dari tombol dan fungsi Salin Naskah, Unduh QR, dan HTML'
    );
    assertBraSep(
        !str_contains($bodyInternal, 'SAH SECARA ELEKTRONIK')
            && !str_contains($bodyInternal, 'TERVALIDASI RESMI'),
        'Halaman internal melihat BRA tidak lagi digabung dengan kartu verifikasi publik'
    );
} finally {
    if ($testBraId > 0) {
        BraModel::delete($testBraId);
    }
}

echo "\n--- HASIL PENGUJIAN PEMISAHAN BRA ---\n";
echo "Lulus (PASS): {$passed}\n";
echo "Gagal (FAIL): {$failed}\n";
exit($failed === 0 ? 0 : 1);
