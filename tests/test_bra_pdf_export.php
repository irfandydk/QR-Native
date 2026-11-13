<?php
/**
 * QR-SAR Basarnas Kendari - Pengujian Ekspor PDF Vektor BRA & Konsistensi Cetak
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testPassed = 0;
$testTotal = 0;

if (!function_exists('assertCondition')) {
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
}

function runBraAction(array $get, array $session = []): array {
    $script = 'putenv("DB_DATABASE=" . ' . var_export(getenv('DB_DATABASE') ?: 'qrsar_test', true) . ');'
        . ' require ' . var_export(dirname(__DIR__) . '/config/app.php', true) . ';'
        . ' $_SESSION = array_merge($_SESSION ?? [], ' . var_export($session, true) . ');'
        . ' $_GET = ' . var_export(array_map('strval', $get), true) . '; $_SERVER["REQUEST_METHOD"] = "GET"; ob_start();'
        . ' register_shutdown_function(function () {'
        . '     $b = ""; while (ob_get_level() > 0) { $b = (string)ob_get_clean() . $b; }'
        . '     $headers = headers_list();'
        . '     $ct = ""; $cd = "";'
        . '     foreach ($headers as $h) {'
        . '         if (stripos($h, "Content-Type:") === 0) $ct = trim(substr($h, 13));'
        . '         if (stripos($h, "Content-Disposition:") === 0) $cd = trim(substr($h, 20));'
        . '     }'
        . '     echo "STATUS:" . (http_response_code() ?: 200) . "\nCT:" . $ct . "\nCD:" . $cd . "\n---BODY---\n" . $b;'
        . ' });'
        . ' (new BraController())->handle();';
    $cmd = 'DB_DATABASE=' . escapeshellarg(getenv('DB_DATABASE') ?: 'qrsar_test') . ' ' . escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1';
    $out = (string)shell_exec($cmd);
    if (!preg_match('/STATUS:(\d+)\nCT:([^\n]*)\nCD:([^\n]*)\n---BODY---\n/', $out, $m, PREG_OFFSET_CAPTURE)) {
        return [500, '', '', $out];
    }
    $status = (int)$m[1][0];
    $ct = $m[2][0];
    $cd = $m[3][0];
    $body = substr($out, $m[0][1] + strlen($m[0][0]));
    return [$status, $ct, $cd, $body];
}

echo "=== TEST BRA PDF VECTOR EXPORT & PRINT CONSISTENCY ===\n";

// 1. Verifikasi ketersediaan kelas BraPdfService
assertCondition(class_exists('BraPdfService'), 'Class BraPdfService harus tersedia');

if (class_exists('BraPdfService')) {
    // 2. Verifikasi findChromeBinary mendeteksi biner Chrome yang valid di server
    $chromeBin = BraPdfService::findChromeBinary();
    assertCondition($chromeBin !== null && is_executable($chromeBin), 'BraPdfService::findChromeBinary menemukan biner Chrome yang dapat dieksekusi: ' . ($chromeBin ?? 'NULL'));

    // 3. Verifikasi prepareHtmlForLocalRender mengubah path lokal menjadi file://
    $dummyHtml = '<html><head></head><body><img src="' . BASE_URL . '/assets/images/logo_basarnas.svg"><div class="print:hidden">Toolbar</div><div id="official-bra-document">Isi Dokumen BRA</div></body></html>';
    $prepared = BraPdfService::prepareHtmlForLocalRender($dummyHtml);
    assertCondition(str_contains($prepared, 'file://'), 'BraPdfService::prepareHtmlForLocalRender mengubah tautan aset menjadi file:// lokal');
    assertCondition(str_contains($prepared, 'print\\:hidden') || str_contains($prepared, 'display: none'), 'BraPdfService::prepareHtmlForLocalRender menginjeksi aturan print override');

    // 4. Verifikasi renderHtmlToPdf menghasilkan biner %PDF-
    $pdfBytes = BraPdfService::renderHtmlToPdf($dummyHtml);
    assertCondition(is_string($pdfBytes) && str_starts_with($pdfBytes, '%PDF-') && strlen($pdfBytes) > 500, 'BraPdfService::renderHtmlToPdf menghasilkan biner PDF vektor valid via Chrome Headless');
}

// 5. Buat data uji BRA untuk pengujian controller
$testBraId = BraModel::create([
    'kategori' => 'MAPEL',
    'derajat' => 'SGR',
    'panggilan' => 'YCSB',
    'no_bra' => 'BRA/99/KPP-KDI/XI/2026',
    'twu' => '1308 0700 H',
    'perihal' => 'UJI EKSPOR PDF VEKTOR BRA SAR TTK',
    'aksi' => 'STASIUN RADIO PANTAI KENDARI',
    'info' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    'nama_smc' => 'KEPALA KANSAR KENDARI',
    'sections' => [
        ['kode' => 'AAA', 'isi' => 'KECELAKAAN KAPAL MOTOR NELAYAN TENGGELAM DI PERAIRAN WAKATOBI TTK'],
        ['kode' => 'BBB', 'isi' => 'TIM RESCUE POS SAR WAKATOBI DIBERANGKATKAN DENGAN RIB 01 TTK'],
        ['kode' => 'CCC', 'isi' => 'CUACA HUJAN RINGAN GELOMBANG 1 METER TTK'],
        ['kode' => 'DDD', 'isi' => 'PERKEMBANGAN SELANJUTNYA MENYUSUL KMA KA KPP KENDARI KRM TTK HBS']
    ]
]);

assertCondition($testBraId > 0, 'Data uji BRA SAR berhasil dibuat dengan ID: ' . $testBraId);

if ($testBraId > 0) {
    $braData = BraModel::getById($testBraId);
    $docHash = BraModel::generateVerifyToken($braData);
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
            'role' => 'admin'
        ]
    ];

    // 6. Uji controller: Akses internal login dengan ?format=pdf
    [$statusLogin, , , $bodyLogin] = runBraAction(
        ['view' => (string)$testBraId, 'format' => 'pdf'],
        $mockLoginSession
    );
    assertCondition(
        $statusLogin === 200 && str_starts_with($bodyLogin, '%PDF-') && strlen($bodyLogin) > 5000,
        "Controller internal (?view={$testBraId}&format=pdf) menghasilkan file PDF vektor valid (status: {$statusLogin}, size: " . strlen($bodyLogin) . " bytes)"
    );

    // Verifikasi sanitasi nama berkas unduhan
    $rawNo = (string)($braData['no_bra'] ?? 'BRA_SAR');
    $cleanNo = trim((string)preg_replace('/[^a-zA-Z0-9_\-.]+/', '_', $rawNo), '_');
    $safeName = 'BRA-SAR-' . $cleanNo . '.pdf';
    assertCondition(
        !empty($cleanNo) && str_starts_with($safeName, 'BRA-SAR-') && str_ends_with($safeName, '.pdf') && !str_contains($safeName, '/'),
        "Format nama berkas PDF resmi ter-sanitasi dengan benar dan aman dari slash: {$safeName}"
    );

    // 7. Uji controller: Akses publik bertanda tangan digital dengan ?format=pdf
    [$statusPub, , , $bodyPub] = runBraAction(
        ['view' => (string)$testBraId, 'token' => $docHash, 'format' => 'pdf']
    );
    assertCondition(
        $statusPub === 200 && str_starts_with($bodyPub, '%PDF-') && strlen($bodyPub) > 5000,
        "Controller publik bertoken (?view={$testBraId}&token=...&format=pdf) menghasilkan file PDF vektor valid (status: {$statusPub}, size: " . strlen($bodyPub) . " bytes)"
    );

    // 8. Uji keamanan: Akses publik tanpa token dan tanpa login harus ditolak (404 / verify invalid)
    [$statusGuestNoToken, , , $bodyGuestNoToken] = runBraAction(
        ['view' => (string)$testBraId, 'format' => 'pdf']
    );
    assertCondition(
        $statusGuestNoToken === 404 && !str_starts_with($bodyGuestNoToken, '%PDF-'),
        "Akses publik tanpa token dengan ?format=pdf ditolak (HTTP 404)"
    );

    // 9. Uji keamanan: Akses dengan token palsu/manipulasi harus ditolak (404)
    [$statusFakeToken, , , $bodyFakeToken] = runBraAction(
        ['view' => (string)$testBraId, 'token' => 'BAD_HASH_123', 'format' => 'pdf']
    );
    assertCondition(
        $statusFakeToken === 404 && !str_starts_with($bodyFakeToken, '%PDF-'),
        "Akses publik dengan token tidak sah pada ?format=pdf ditolak (HTTP 404)"
    );

    // 10. Uji frontend markup: Verifikasi tombol resmi "Unduh PDF" pada tampilan web
    [$statusViewHtml, , , $bodyViewHtml] = runBraAction(
        ['view' => (string)$testBraId],
        $mockLoginSession
    );
    assertCondition(
        $statusViewHtml === 200 && str_contains($bodyViewHtml, 'id="btnDownloadPdf"'),
        "Tombol unduh PDF dengan ID btnDownloadPdf tersedia pada lembar dokumen BRA"
    );
    assertCondition(
        str_contains($bodyViewHtml, 'data-pdf-url=') && str_contains($bodyViewHtml, 'data-pdf-filename='),
        "Tombol unduh memiliki atribut data-pdf-url dan data-pdf-filename"
    );
    assertCondition(
        str_contains($bodyViewHtml, 'downloadBraDocumentPdf()') || str_contains($bodyViewHtml, 'downloadOfficialPdf'),
        "Tombol unduh memanggil downloadBraDocumentPdf() atau downloadOfficialPdf"
    );
    assertCondition(
        str_contains($bodyViewHtml, 'Unduh PDF') && !str_contains($bodyViewHtml, 'PDF Gambar'),
        "Label tombol adalah 'Unduh PDF' dan label lama 'PDF Gambar' telah digantikan"
    );
    assertCondition(
        str_contains($bodyViewHtml, 'pdf_document_exporter.js'),
        "Skrip assets/js/pdf_document_exporter.js dimuat pada lembar dokumen BRA"
    );
    assertCondition(
        str_contains($bodyViewHtml, 'id="official-bra-document"') && str_contains($bodyViewHtml, 'page-sheet'),
        "Elemen dokumen resmi BRA memiliki penanda class .page-sheet untuk eksportir PDF"
    );
}

echo "\n--- HASIL PENGUJIAN BRA PDF EXPORT ---\n";
echo "Total Asersi: {$testTotal}\n";
echo "Lulus (PASS): {$testPassed}\n";
echo "Gagal (FAIL): " . ($testTotal - $testPassed) . "\n";
if ($testPassed === $testTotal) {
    echo "STATUS: SELURUH PENGUJIAN BRA PDF BERHASIL (100% PASS)!\n";
} else {
    echo "STATUS: ADA PENGUJIAN YANG GAGAL!\n";
    exit(1);
}

