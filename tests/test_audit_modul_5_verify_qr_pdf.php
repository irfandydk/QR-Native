<?php
/**
 * QR-SAR - Audit Modul 5: Verifikasi QR & PDF Dokumen Rencana Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Cakupan (dokumen cetak Laporan Awal, LAPBANG & Penutupan sudah dihapus; QR hanya RENOPS):
 * 1. Token HMAC-SHA256 QR RENOPS lama (DocVerifyHelper): format, determinisme, tamper, legacy
 * 2. Portal verifikasi publik: RENOPS sah, tipe lama tidak valid, zero-leakage 404, injeksi
 * 3. QR beremblem logo pada dokumen RENOPS SMEAC
 * 4. Engine PDF anti-potong (pdf_document_exporter.js) multi-lembar SMEAC
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

echo "=== AUDIT MODUL 5: VERIFIKASI QR & PDF DOKUMEN RENOPS ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $category, string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] [{$category}] {$desc}\n";
    } else {
        $failCount++;
        echo "  [FAIL] [{$category}] {$desc}" . ($detail ? " -> {$detail}" : '') . "\n";
    }
}

// =========================================================================
// 1. TOKEN HMAC QR RENOPS LAMA
// =========================================================================
echo "--- 1. TOKEN HMAC QR RENOPS LAMA ---\n";
assertTest('KRIPTO', 'Hanya tipe renops yang dikenali (tipe lama tidak valid)',
    DocVerifyHelper::normalizeType('RENOPS') === 'renops' && DocVerifyHelper::normalizeType('laporan_awal') === null
    && DocVerifyHelper::normalizeType('lapbang') === null && DocVerifyHelper::normalizeType('berita_acara') === null && DocVerifyHelper::normalizeType('') === null);

$mockOpsA = ['id_operasi' => 701, 'nama_operasi' => 'KM CAHAYA TIMUR KANDAS DI PERAIRAN WAWONII', 'status' => 'OPEN'];
$mockOpsB = ['id_operasi' => 702, 'nama_operasi' => 'ORANG HILANG DI HUTAN TAHURA NANGGURUKA', 'status' => 'OPEN'];
$mockRenopsA = ['id_renops' => 801, 'id_operasi' => 701, 'hari_ke' => 'H.1', 'waktu_briefing' => '2026-10-21 06:00:00', 'nama_smc' => 'AMIRUDDIN A.S., S.Sos.'];

$tokA = DocVerifyHelper::generateToken($mockOpsA, $mockRenopsA);
assertTest('KRIPTO', 'Token 16 karakter hex uppercase', strlen($tokA) === 16 && ctype_xdigit($tokA) && strtoupper($tokA) === $tokA);
assertTest('KRIPTO', 'Token deterministik', DocVerifyHelper::generateToken($mockOpsA, $mockRenopsA) === $tokA);
assertTest('KRIPTO', 'Token berbeda antar-operasi & antar-hari RENOPS',
    DocVerifyHelper::generateToken($mockOpsB, $mockRenopsA) !== $tokA
    && DocVerifyHelper::generateToken($mockOpsA, ['id_renops' => 802] + $mockRenopsA) !== $tokA);
assertTest('KRIPTO', 'Token sah & huruf kecil diterima',
    DocVerifyHelper::verifyToken($mockOpsA, $mockRenopsA, $tokA) && DocVerifyHelper::verifyToken($mockOpsA, $mockRenopsA, strtolower($tokA)));
assertTest('KRIPTO', 'Token legacy (tanpa tanggal) diterima',
    DocVerifyHelper::verifyToken($mockOpsA, $mockRenopsA, DocVerifyHelper::generateLegacyToken($mockOpsA, $mockRenopsA)));
$tamper = substr($tokA, 0, 15) . ($tokA[15] === 'A' ? 'B' : 'A');
assertTest('KRIPTO', 'Token kosong, null, diubah 1 karakter, atau acak ditolak',
    !DocVerifyHelper::verifyToken($mockOpsA, $mockRenopsA, '') && !DocVerifyHelper::verifyToken($mockOpsA, $mockRenopsA, null)
    && !DocVerifyHelper::verifyToken($mockOpsA, $mockRenopsA, $tamper) && !DocVerifyHelper::verifyToken($mockOpsA, $mockRenopsA, 'ABCDEF0123456789'));
assertTest('KRIPTO', 'getPrintUrl RENOPS menyertakan id_renops', DocVerifyHelper::getPrintUrl(701, 801) === BASE_URL . '/operasi.php?action=print_renops&id=701&id_renops=801');
assertTest('KRIPTO', 'getSmcName memprioritaskan nama_smc dokumen (uppercase)', DocVerifyHelper::getSmcName(null, ['nama_smc' => 'muhammad arifin']) === 'MUHAMMAD ARIFIN');
assertTest('KRIPTO', 'getDocTitle judul resmi RENOPS H.1', DocVerifyHelper::getDocTitle($mockRenopsA) === 'Rencana Operasi SAR (RENOPS) H.1');

// =========================================================================
// 2. PORTAL VERIFIKASI PUBLIK (DB nyata)
// =========================================================================
echo "\n--- 2. PORTAL VERIFIKASI PUBLIK ---\n";

function simulateVerifyRequest(array $params): array {
    $script = sprintf(
        'require "%s/config/app.php"; $_GET = %s; $_SERVER["REQUEST_METHOD"] = "GET"; ob_start(); (new OperasiController())->index(); $body = ob_get_clean(); echo "STATUS_CODE:" . http_response_code() . "===BODY===" . $body;',
        addslashes(dirname(__DIR__)),
        var_export($params, true)
    );
    $output = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');
    if (!str_contains($output, '===BODY===')) {
        return ['status' => 500, 'body' => $output];
    }
    [$header, $body] = explode('===BODY===', $output, 2);
    preg_match('/STATUS_CODE:(\d+)/', $header, $m);
    return ['status' => isset($m[1]) ? (int)$m[1] : 200, 'body' => $body];
}

$idTestOps = 0;
try {
    $idTestOps = OperasiModel::create([
        'nama_operasi' => 'TEST AUDIT MODUL 5 VERIFIKASI DOKUMEN REAL',
        'nama_pelapor' => 'Serka Laut Bambang',
        'lokasi_kejadian' => 'Perairan Teluk Kendari Sekitar Jembatan Bahteramas',
        'waktu_lapor' => '2026-10-21 08:00:00',
        'waktu_berangkat' => '2026-10-21 08:15:00',
    ]);
    $idTestRenops = RenopsModel::create([
        'id_operasi' => $idTestOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-10-21',
        'waktu_briefing' => '2026-10-21 07:00:00', 'nama_smc' => 'AMIRUDDIN A.S., S.Sos.',
    ]);
    assertTest('E2E_DB', 'Operasi & RENOPS uji dibuat', $idTestOps > 0 && $idTestRenops > 0);
    $realOps = OperasiModel::getById($idTestOps);
    $realRenops = RenopsModel::getById($idTestRenops);
    $tok = DocVerifyHelper::generateToken($realOps, $realRenops);
    assertTest('E2E_DB', 'Token RENOPS real valid', DocVerifyHelper::verifyToken($realOps, $realRenops, $tok));

    $res = simulateVerifyRequest(['action' => 'verify', 'type' => 'renops', 'id' => (string)$idTestOps, 'ref' => (string)$idTestRenops, 'token' => $tok]);
    assertTest('E2E_HTTP', 'Verifikasi RENOPS sah: HTTP 200, judul, nama operasi & status OPEN',
        $res['status'] === 200 && str_contains($res['body'], 'Rencana Operasi SAR (RENOPS) H.1')
        && str_contains($res['body'], 'TEST AUDIT MODUL 5 VERIFIKASI DOKUMEN REAL') && str_contains($res['body'], 'AKTIF (OPEN)'));
    assertTest('E2E_HTTP', 'Publik (tamu) tidak menautkan lembar cetak internal',
        !str_contains($res['body'], "action=print_renops&amp;id={$idTestOps}&amp;id_renops={$idTestRenops}"));

    $lama = simulateVerifyRequest(['action' => 'verify', 'type' => 'laporan_awal', 'id' => (string)$idTestOps, 'token' => $tok]);
    assertTest('SECURITY', 'QR tipe lama (laporan_awal) tidak valid: HTTP 404', $lama['status'] === 404);

    $palsu = simulateVerifyRequest(['action' => 'verify', 'type' => 'renops', 'id' => (string)$idTestOps, 'ref' => (string)$idTestRenops, 'token' => 'PALSU1234567890']);
    assertTest('SECURITY', 'Token palsu: HTTP 404 + pesan tidak sah, tanpa bocor data pelapor/lokasi',
        $palsu['status'] === 404 && str_contains($palsu['body'], 'Naskah Dokumen Operasi SAR Tidak Terverifikasi')
        && !str_contains($palsu['body'], 'Bambang') && !str_contains($palsu['body'], 'Bahteramas'));

    $fiktif = simulateVerifyRequest(['action' => 'verify', 'type' => 'renops', 'id' => '999999', 'token' => 'APAPUNTOKENNYA']);
    assertTest('SECURITY', 'ID operasi fiktif: HTTP 404', $fiktif['status'] === 404);

    $inj = simulateVerifyRequest(['action' => 'verify', 'type' => 'renops', 'id' => "1' OR '1'='1", 'token' => "<script>alert('xss')</script>"]);
    assertTest('SECURITY', 'Injeksi SQL/XSS: HTTP 404 & token ter-escape',
        $inj['status'] === 404 && !str_contains($inj['body'], "<script>alert('xss')</script>"));
} catch (Throwable $e) {
    assertTest('E2E', 'Tanpa exception', false, get_class($e) . ': ' . $e->getMessage());
} finally {
    if ($idTestOps > 0) {
        OperasiModel::delete($idTestOps);
    }
}

// =========================================================================
// 3. QR BEREMBLEM LOGO PADA DOKUMEN RENOPS
// =========================================================================
echo "\n--- 3. QR BEREMBLEM LOGO (RENOPS SMEAC) ---\n";
assertTest('QR_LOGO', 'Lambang Basarnas SVG tersedia', file_exists(dirname(__DIR__) . '/assets/images/logo_basarnas.svg'));
$dataUri = getAppLogoDataUri();
assertTest('QR_LOGO', 'getAppLogoDataUri() menghasilkan Data URI', str_starts_with($dataUri, 'data:image/svg+xml;base64,') || str_starts_with($dataUri, 'data:image/png;base64,'));
$renops = file_get_contents(dirname(__DIR__) . '/views/operasi/renops_smeac_print.php') . file_get_contents(dirname(__DIR__) . '/views/operasi/partials/renops_smeac_doc.php');
assertTest('QR_LOGO', "QRCode.toCanvas errorCorrectionLevel 'H'", str_contains($renops, "errorCorrectionLevel: 'H'"));
assertTest('QR_LOGO', 'Pasangan canvas QR & img pengganti', str_contains($renops, '<canvas id="qrCanvas') && str_contains($renops, '<img id="qrImg'));
assertTest('QR_LOGO', 'Emblem tengah (drawCenterEmblem) dengan badge putih', str_contains($renops, 'function drawCenterEmblem()') && str_contains($renops, "ctx.fillStyle = '#ffffff'"));
assertTest('QR_LOGO', 'Canvas disalin ke PNG untuk cetak', str_contains($renops, "canvas.toDataURL('image/png')") && str_contains($renops, 'imgEl.src = dataUrl'));
assertTest('QR_LOGO', 'Memuat qrcode.min.js & html2pdf.bundle.min.js', str_contains($renops, 'qrcode.min.js') && str_contains($renops, 'html2pdf.bundle.min.js'));
assertTest('QR_LOGO', 'Blok pengesahan ber-class signature-block', str_contains($renops, 'signature-block'));

// =========================================================================
// 4. ENGINE PDF ANTI-POTONG (multi-lembar SMEAC)
// =========================================================================
echo "\n--- 4. ENGINE PDF ANTI-POTONG ---\n";
$exporterJs = (string)file_get_contents(dirname(__DIR__) . '/assets/js/pdf_document_exporter.js');
assertTest('PDF_ENGINE', 'Hook window.downloadOfficialPdf', str_contains($exporterJs, 'window.downloadOfficialPdf = function'));
assertTest('PDF_ENGINE', 'Semua lembar .page-sheet digabung satu PDF', str_contains($exporterJs, "document.querySelectorAll('.page-sheet')"));
assertTest('PDF_ENGINE', 'Zona terlarang blok tanda tangan', str_contains($exporterJs, "'.signature-block'") && str_contains($exporterJs, 'window.__pdfForbiddenZones'));
assertTest('PDF_ENGINE', 'Margin atas seragam semua halaman', str_contains($exporterJs, 'topMarginPage1 = opt.margin[0]') && str_contains($exporterJs, 'topMarginPageNext = opt.margin[0]'));
assertTest('PDF_ENGINE', 'Potong sebelum blok tanda tangan bila bertabrakan', str_contains($exporterJs, 'insideForbidden.top - currentY'));
assertTest('PDF_ENGINE', 'findSafePageCutY mencegah huruf terbelah', str_contains($exporterJs, 'function findSafePageCutY('));
assertTest('PDF_ENGINE', 'Header berjalan & nomor halaman', str_contains($exporterJs, 'stampHeaderFooter(pdf, window.__pdfRunningHeader)'));
assertTest('PDF_ENGINE', 'Padding bawah pengaman 25mm', str_contains($exporterJs, '0 24px 25mm 6px'));

echo "\n=== REKAP MODUL 5: {$passCount} LULUS (PASS), {$failCount} GAGAL (FAIL) ===\n";
exit($failCount > 0 ? 1 : 0);
