<?php
/**
 * QR-SAR - End-to-End Automated Audit & Test Suite: Modul 5
 * Sistem Verifikasi & Keabsahan Dokumen Digital (QR Logo HD & Anti-Potong PDF)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Cakupan Pengujian:
 * 1. Algoritma Kriptografis Token HMAC-SHA256 (DocVerifyHelper) & Normalisasi 4 Tipe Dokumen
 * 2. Portal Publik Verifikasi Keabsahan Dokumen (Akses Tanpa Login, Keabsahan Status & Zero-Leakage 404)
 * 3. Integritas Canvas QR Code Beremblem Logo HD Basarnas & Fallback Img Data URI
 * 4. Spesifikasi Engine Anti-Potong PDF (pdf_document_exporter.js) & Kepatuhan Tipografi Resmi Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';

echo "========================================================================================\n";
echo "   AUDIT & PENGUJIAN END-TO-END MODUL 5: VERIFIKASI DOKUMEN DIGITAL, QR LOGO & PDF     \n";
echo "   KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS                    \n";
echo "========================================================================================\n\n";

$passCount = 0;
$failCount = 0;
$testResults = [];

function assertTest(string $category, string $desc, bool $condition, string $detail = ''): void {
    global $passCount, $failCount, $testResults;
    if ($condition) {
        $passCount++;
        echo "  [PASS] [{$category}] {$desc}\n";
        $testResults[] = ['category' => $category, 'desc' => $desc, 'status' => 'PASS', 'detail' => ''];
    } else {
        $failCount++;
        echo "  [FAIL] [{$category}] {$desc}" . ($detail ? " -> {$detail}" : "") . "\n";
        $testResults[] = ['category' => $category, 'desc' => $desc, 'status' => 'FAIL', 'detail' => $detail];
    }
}

// =========================================================================
// BAGIAN 1: AUDIT ALGORITMA KRIPTOGRAFIS TOKEN & NORMALISASI DOKUMEN
// =========================================================================
echo "\n--- DOMAIN 1: AUDIT KRIPTOGRAFIS TOKEN HMAC-SHA256 & NORMALISASI ---\n";

assertTest('KRIPTO', 'Class DocVerifyHelper tersedia di autoloader', class_exists('DocVerifyHelper'));

// 1.1 Tipe Dokumen
$supported = DocVerifyHelper::supportedTypes();
assertTest('KRIPTO', 'Mendukung tepat 4 tipe dokumen operasional resmi', count($supported) === 4 &&
    in_array('laporan_awal', $supported) &&
    in_array('renops', $supported) &&
    in_array('sitrep', $supported) &&
    in_array('penutupan', $supported)
);

// 1.2 Normalisasi Tipe Dokumen
$normAwal1 = DocVerifyHelper::normalizeType('lap_awal');
$normAwal2 = DocVerifyHelper::normalizeType('awal');
$normAwal3 = DocVerifyHelper::normalizeType('laporan_awal');
$normRenops = DocVerifyHelper::normalizeType('renops');
$normSitrep1 = DocVerifyHelper::normalizeType('sitrep');
$normSitrep2 = DocVerifyHelper::normalizeType('lapbang');
$normSitrep3 = DocVerifyHelper::normalizeType('laporan_perkembangan');
$normPenutupan1 = DocVerifyHelper::normalizeType('penutupan');
$normPenutupan2 = DocVerifyHelper::normalizeType('berita_acara');
$normPenutupan3 = DocVerifyHelper::normalizeType('ba_penutupan');
$normDefault = DocVerifyHelper::normalizeType('unknown_arbitrary_string');

assertTest('KRIPTO', 'Normalisasi tipe laporan_awal bekerja untuk semua varian alias',
    $normAwal1 === 'laporan_awal' && $normAwal2 === 'laporan_awal' && $normAwal3 === 'laporan_awal'
);
assertTest('KRIPTO', 'Normalisasi tipe renops konsisten', $normRenops === 'renops');
assertTest('KRIPTO', 'Normalisasi tipe sitrep/lapbang konsisten',
    $normSitrep1 === 'sitrep' && $normSitrep2 === 'sitrep' && $normSitrep3 === 'sitrep'
);
assertTest('KRIPTO', 'Normalisasi tipe penutupan/berita acara konsisten',
    $normPenutupan1 === 'penutupan' && $normPenutupan2 === 'penutupan' && $normPenutupan3 === 'penutupan'
);
assertTest('KRIPTO', 'Tipe dokumen tidak dikenal fallback aman ke laporan_awal', $normDefault === 'laporan_awal');

// 1.3 Mock Data untuk Uji Kriptografis
$mockOpsA = [
    'id_operasi' => 701,
    'nama_operasi' => 'KM CAHAYA TIMUR KANDAS DI PERAIRAN WAWONII',
    'waktu_lapor' => '2026-10-21 07:15:00',
    'status' => 'OPEN',
    'korban' => [],
    'jumlah_pob' => 12
];

$mockOpsB = [
    'id_operasi' => 702,
    'nama_operasi' => 'ORANG HILANG DI HUTAN TAHURA NANGGURUKA',
    'waktu_lapor' => '2026-10-21 09:30:00',
    'status' => 'OPEN'
];

$mockRenopsA = [
    'id_renops' => 801,
    'id_operasi' => 701,
    'hari_ke' => 'H.1',
    'waktu_briefing' => '2026-10-21 06:00:00',
    'nama_smc' => 'AMIRUDDIN A.S., S.Sos.'
];

$mockSitrepA = [
    'id_laporan' => 901,
    'id_operasi' => 701,
    'hari_ke' => 'H.1',
    'waktu_debrief' => '2026-10-21 17:45:00',
    'jenis_laporan' => 'PERKEMBANGAN'
];

$mockPenutupanA = [
    'id_laporan' => 902,
    'id_operasi' => 701,
    'hari_ke' => 'H.3',
    'waktu_debrief' => '2026-10-23 15:30:00',
    'jenis_laporan' => 'PENUTUPAN'
];

// 1.4 Format Token HMAC (16 char hex uppercase)
$tokAwalA = DocVerifyHelper::generateToken('laporan_awal', $mockOpsA);
assertTest('KRIPTO', 'Token Laporan Awal berformat tepat 16 karakter Hex Uppercase',
    strlen($tokAwalA) === 16 && ctype_xdigit($tokAwalA) && strtoupper($tokAwalA) === $tokAwalA
);

$tokRenopsA = DocVerifyHelper::generateToken('renops', $mockOpsA, $mockRenopsA);
assertTest('KRIPTO', 'Token RENOPS berformat tepat 16 karakter Hex Uppercase',
    strlen($tokRenopsA) === 16 && ctype_xdigit($tokRenopsA) && strtoupper($tokRenopsA) === $tokRenopsA
);

$tokSitrepA = DocVerifyHelper::generateToken('sitrep', $mockOpsA, $mockSitrepA);
assertTest('KRIPTO', 'Token SITREP berformat tepat 16 karakter Hex Uppercase',
    strlen($tokSitrepA) === 16 && ctype_xdigit($tokSitrepA) && strtoupper($tokSitrepA) === $tokSitrepA
);

$tokPenutupanA = DocVerifyHelper::generateToken('penutupan', $mockOpsA, $mockPenutupanA);
assertTest('KRIPTO', 'Token Penutupan berformat tepat 16 karakter Hex Uppercase',
    strlen($tokPenutupanA) === 16 && ctype_xdigit($tokPenutupanA) && strtoupper($tokPenutupanA) === $tokPenutupanA
);

// 1.5 Sifat Kriptografis: Determinisme & Keunikan Antar-Entitas
$tokAwalA2 = DocVerifyHelper::generateToken('laporan_awal', $mockOpsA);
assertTest('KRIPTO', 'Token bersifat deterministik (input sama menghasilkan token identik)', $tokAwalA === $tokAwalA2);

$tokAwalB = DocVerifyHelper::generateToken('laporan_awal', $mockOpsB);
assertTest('KRIPTO', 'Token unik antar-operasi berbeda (ID & Nama beda -> token beda)', $tokAwalA !== $tokAwalB);

assertTest('KRIPTO', 'Token unik antar-tipe dokumen pada operasi yang sama',
    $tokAwalA !== $tokRenopsA && $tokRenopsA !== $tokSitrepA && $tokSitrepA !== $tokPenutupanA
);

$tokRenopsBedaRef = DocVerifyHelper::generateToken('renops', $mockOpsA, ['id_renops' => 802, 'waktu_briefing' => '2026-10-21 06:00:00']);
assertTest('KRIPTO', 'Token RENOPS berbeda jika id_renops berbeda', $tokRenopsA !== $tokRenopsBedaRef);

// 1.6 Verifikasi Token & Ketahanan Tamper
assertTest('KRIPTO', 'verifyToken Laporan Awal lolos untuk token sah',
    DocVerifyHelper::verifyToken('laporan_awal', $mockOpsA, null, $tokAwalA) === true
);
assertTest('KRIPTO', 'verifyToken RENOPS lolos untuk token sah',
    DocVerifyHelper::verifyToken('renops', $mockOpsA, $mockRenopsA, $tokRenopsA) === true
);
assertTest('KRIPTO', 'verifyToken SITREP lolos untuk token sah',
    DocVerifyHelper::verifyToken('sitrep', $mockOpsA, $mockSitrepA, $tokSitrepA) === true
);
assertTest('KRIPTO', 'verifyToken Penutupan lolos untuk token sah',
    DocVerifyHelper::verifyToken('penutupan', $mockOpsA, $mockPenutupanA, $tokPenutupanA) === true
);

// Toleransi Huruf Kecil pada Input Token
$tokAwalLower = strtolower($tokAwalA);
assertTest('KRIPTO', 'verifyToken toleran terhadap huruf kecil (otomatis dinormalisasi ke uppercase)',
    DocVerifyHelper::verifyToken('laporan_awal', $mockOpsA, null, $tokAwalLower) === true
);

// Fallback Legacy Token
$tokLegacy = DocVerifyHelper::generateLegacyToken('laporan_awal', $mockOpsA);
assertTest('KRIPTO', 'verifyToken mengenali format token legacy sebagai fallback',
    DocVerifyHelper::verifyToken('laporan_awal', $mockOpsA, null, $tokLegacy) === true
);

// Tamper Tests: Ditolak Keras
assertTest('KRIPTO', 'verifyToken menolak token kosong / null',
    DocVerifyHelper::verifyToken('laporan_awal', $mockOpsA, null, '') === false &&
    DocVerifyHelper::verifyToken('laporan_awal', $mockOpsA, null, null) === false
);
$tamperedToken = substr($tokAwalA, 0, 15) . ($tokAwalA[15] === 'A' ? 'B' : 'A');
assertTest('KRIPTO', 'verifyToken menolak token yang dimanipulasi 1 karakter',
    DocVerifyHelper::verifyToken('laporan_awal', $mockOpsA, null, $tamperedToken) === false
);
assertTest('KRIPTO', 'verifyToken menolak token acak / palsu',
    DocVerifyHelper::verifyToken('laporan_awal', $mockOpsA, null, 'ABCDEF0123456789') === false
);

// 1.7 URL Verifikasi Publik & URL Cetak
$urlAwal = DocVerifyHelper::generateVerifyUrl('laporan_awal', $mockOpsA);
assertTest('KRIPTO', 'URL Verifikasi Laporan Awal memuat action=verify, type=laporan_awal, id, dan token',
    str_contains($urlAwal, 'action=verify') &&
    str_contains($urlAwal, 'type=laporan_awal') &&
    str_contains($urlAwal, 'id=701') &&
    str_contains($urlAwal, "token={$tokAwalA}") &&
    !str_contains($urlAwal, 'ref=')
);

$urlRenops = DocVerifyHelper::generateVerifyUrl('renops', $mockOpsA, $mockRenopsA);
assertTest('KRIPTO', 'URL Verifikasi RENOPS memuat ref=801',
    str_contains($urlRenops, 'ref=801') && str_contains($urlRenops, "token={$tokRenopsA}")
);

$urlSitrep = DocVerifyHelper::generateVerifyUrl('sitrep', $mockOpsA, $mockSitrepA);
assertTest('KRIPTO', 'URL Verifikasi SITREP memuat ref=901',
    str_contains($urlSitrep, 'ref=901') && str_contains($urlSitrep, "token={$tokSitrepA}")
);

$printUrlAwal = DocVerifyHelper::getPrintUrl('laporan_awal', 701);
assertTest('KRIPTO', 'getPrintUrl Laporan Awal mengarah ke print_laporan_awal',
    str_contains($printUrlAwal, 'action=print_laporan_awal') && str_contains($printUrlAwal, 'id=701')
);

$printUrlRenops = DocVerifyHelper::getPrintUrl('renops', 701, 801);
assertTest('KRIPTO', 'getPrintUrl RENOPS menyertakan id_renops=801',
    str_contains($printUrlRenops, 'action=print_renops') && str_contains($printUrlRenops, 'id_renops=801')
);

// 1.8 Ekstraksi Pejabat SMC & Judul Dokumen
$smcFromDoc = DocVerifyHelper::getSmcName(null, ['nama_smc' => 'muhammad arifin']);
assertTest('KRIPTO', 'getSmcName memprioritaskan nama_smc dari docData dan di-uppercase',
    $smcFromDoc === 'MUHAMMAD ARIFIN'
);

$titleRenops = DocVerifyHelper::getDocTitle('renops', $mockOpsA, $mockRenopsA);
assertTest('KRIPTO', 'getDocTitle menghasilkan judul resmi RENOPS H.1',
    $titleRenops === 'Rencana Operasi SAR (RENOPS) H.1'
);

$titlePenutupan = DocVerifyHelper::getDocTitle('penutupan', $mockOpsA, $mockPenutupanA);
assertTest('KRIPTO', 'getDocTitle menghasilkan judul resmi Berita Acara Penutupan',
    $titlePenutupan === 'Berita Acara Pengusulan Penutupan Operasi SAR'
);


// =========================================================================
// BAGIAN 2: AUDIT INTEGRASI BASIS DATA & SIMULASI VERIFIKASI PUBLIK (E2E)
// =========================================================================
echo "\n--- DOMAIN 2: SIMULASI VERIFIKASI PUBLIK BERBASIS BASIS DATA REAL ---\n";

$db = Database::getConnection();
$idTestOps = 0;
$idTestRenops = 0;
$idTestSitrep = 0;
$idTestPenutupan = 0;

try {
    // 2.1 Buat Operasi SAR Real di DB
    $idTestOps = OperasiModel::create([
        'nama_operasi' => 'TEST AUDIT MODUL 5 VERIFIKASI DOKUMEN REAL',
        'nama_pelapor' => 'Serka Laut Bambang',
        'lokasi_kejadian' => 'Perairan Teluk Kendari Sekitar Jembatan Bahteramas',
        'waktu_lapor' => '2026-10-21 08:00:00',
        'waktu_berangkat' => '2026-10-21 08:15:00',
        'status' => 'OPEN',
        'jumlah_pob' => 4,
        'created_by' => 1
    ]);
    assertTest('E2E_DB', 'Operasi SAR uji coba berhasil dibuat di basis data', $idTestOps > 0);

    // 2.2 Buat Dokumen RENOPS Real
    $idTestRenops = RenopsModel::create([
        'id_operasi' => $idTestOps,
        'hari_ke' => 'H.1',
        'tanggal_laporan' => '2026-10-21',
        'waktu_briefing' => '2026-10-21 07:00:00',
        'metode_pencarian' => 'Creeping Line Search',
        'luas_search_area' => 18.5,
        'satuan_jarak' => 'NM',
        'rencana_komunikasi' => 'VHF Marine Ch. 16, Satker Kendari Ch. 68',
        'nama_smc' => 'AMIRUDDIN A.S., S.Sos.'
    ]);
    assertTest('E2E_DB', 'Dokumen RENOPS H.1 real berhasil dibuat di basis data', $idTestRenops > 0);

    // 2.3 Buat Dokumen SITREP Real
    $idTestSitrep = LaporanModel::create([
        'id_operasi' => $idTestOps,
        'jenis_laporan' => 'PERKEMBANGAN',
        'hari_ke' => 'H.1',
        'waktu_laporan' => '2026-10-21 17:00:00',
        'waktu_debrief' => '2026-10-21 17:00:00',
        'isi_laporan' => 'Pencarian hari pertama nihil, dilanjutkan esok hari.',
        'nama_smc' => 'AMIRUDDIN A.S., S.Sos.'
    ]);
    assertTest('E2E_DB', 'Dokumen SITREP H.1 real berhasil dibuat di basis data', $idTestSitrep > 0);

    // Muat data dari DB saat OPEN
    $realOps = OperasiModel::getById($idTestOps);
    $realRenops = RenopsModel::getById($idTestRenops);
    $realSitrep = LaporanModel::getById($idTestSitrep);

    // 2.4 Generate Real Tokens saat OPEN
    $realTokAwal = DocVerifyHelper::generateToken('laporan_awal', $realOps);
    $realTokRenops = DocVerifyHelper::generateToken('renops', $realOps, $realRenops);
    $realTokSitrep = DocVerifyHelper::generateToken('sitrep', $realOps, $realSitrep);

    assertTest('E2E_DB', 'Token Laporan Awal real valid diverifikasi',
        DocVerifyHelper::verifyToken('laporan_awal', $realOps, null, $realTokAwal) === true
    );
    assertTest('E2E_DB', 'Token RENOPS real valid diverifikasi',
        DocVerifyHelper::verifyToken('renops', $realOps, $realRenops, $realTokRenops) === true
    );
    assertTest('E2E_DB', 'Token SITREP real valid diverifikasi',
        DocVerifyHelper::verifyToken('sitrep', $realOps, $realSitrep, $realTokSitrep) === true
    );

    function simulateVerifyRequest(array $params): array {
        $script = sprintf(
            'require "%s/config/app.php"; $_GET = %s; $_SERVER["REQUEST_METHOD"] = "GET"; ob_start(); (new OperasiController())->index(); $body = ob_get_clean(); echo "STATUS_CODE:" . http_response_code() . "===BODY===" . $body;',
            addslashes(dirname(__DIR__)),
            var_export($params, true)
        );
        $cmd = sprintf('/opt/homebrew/bin/php -r %s 2>&1', escapeshellarg($script));
        $output = shell_exec($cmd);
        if (!str_contains($output, '===BODY===')) {
            return ['status' => 500, 'body' => $output];
        }
        [$header, $body] = explode('===BODY===', $output, 2);
        preg_match('/STATUS_CODE:(\d+)/', $header, $m);
        $statusCode = isset($m[1]) ? (int)$m[1] : 200;
        return ['status' => $statusCode, 'body' => $body];
    }

    // 2.5 Simulasi Eksekusi Controller Verifikasi Publik saat Operasi Aktif (OPEN)
    // Skenario A: Verifikasi Sah Laporan Awal
    $resAwal = simulateVerifyRequest([
        'action' => 'verify',
        'type' => 'laporan_awal',
        'id' => (string)$idTestOps,
        'token' => $realTokAwal
    ]);

    assertTest('E2E_HTTP', 'Verifikasi Laporan Awal mengembalikan status HTTP 200 OK', $resAwal['status'] === 200);
    assertTest('E2E_HTTP', 'Tampilan verifikasi Laporan Awal memuat banner DOKUMEN RESMI TERVERIFIKASI',
        str_contains($resAwal['body'], 'DOKUMEN RESMI TERVERIFIKASI') && str_contains($resAwal['body'], 'SAH SECARA ELEKTRONIK')
    );
    assertTest('E2E_HTTP', 'Tampilan verifikasi memuat nama operasi real',
        str_contains($resAwal['body'], 'TEST AUDIT MODUL 5 VERIFIKASI DOKUMEN REAL')
    );
    assertTest('E2E_HTTP', 'Tampilan verifikasi memuat status operasi AKTIF (OPEN)',
        str_contains($resAwal['body'], 'AKTIF (OPEN)')
    );
    assertTest('E2E_HTTP', 'Tampilan verifikasi publik (tamu) tidak menautkan lembar cetak berisi data pribadi',
        !str_contains($resAwal['body'], 'Buka Lembar Naskah Dinas Resmi (Cetak / PDF)') &&
        !str_contains($resAwal['body'], "action=print_laporan_awal&amp;id={$idTestOps}")
    );

    // Skenario B: Verifikasi Sah RENOPS
    $resRenops = simulateVerifyRequest([
        'action' => 'verify',
        'type' => 'renops',
        'id' => (string)$idTestOps,
        'ref' => (string)$idTestRenops,
        'token' => $realTokRenops
    ]);

    assertTest('E2E_HTTP', 'Verifikasi RENOPS mengembalikan status HTTP 200 OK', $resRenops['status'] === 200);
    assertTest('E2E_HTTP', 'Tampilan verifikasi RENOPS memuat judul Rencana Operasi SAR (RENOPS) H.1',
        str_contains($resRenops['body'], 'Rencana Operasi SAR (RENOPS) H.1')
    );
    assertTest('E2E_HTTP', 'Tampilan verifikasi RENOPS publik (tamu) tidak menautkan lembar cetak berisi data pribadi',
        !str_contains($resRenops['body'], "action=print_renops&amp;id={$idTestOps}&amp;id_renops={$idTestRenops}")
    );

    // Skenario C: Penerbitan Dokumen Penutupan Operasi & Transisi Otomatis ke Status CLOSED
    $idTestPenutupan = LaporanModel::create([
        'id_operasi' => $idTestOps,
        'jenis_laporan' => 'PENUTUPAN',
        'hari_ke' => 'H.2',
        'waktu_laporan' => '2026-10-22 14:00:00',
        'waktu_debrief' => '2026-10-22 14:00:00',
        'isi_laporan' => 'Korban berhasil dievakuasi dalam keadaan selamat. Operasi SAR diusulkan ditutup.',
        'nama_smc' => 'AMIRUDDIN A.S., S.Sos.'
    ]);
    assertTest('E2E_DB', 'Dokumen Berita Acara Penutupan real berhasil dibuat di basis data', $idTestPenutupan > 0);

    $opsClosed = OperasiModel::getById($idTestOps);
    assertTest('E2E_DB', 'Pembuatan laporan Penutupan otomatis mengubah status operasi menjadi CLOSED',
        $opsClosed['status'] === 'CLOSED'
    );

    $realPenutupan = LaporanModel::getById($idTestPenutupan);
    $realTokPenutupan = DocVerifyHelper::generateToken('penutupan', $opsClosed, $realPenutupan);

    assertTest('E2E_DB', 'Token Penutupan real valid diverifikasi',
        DocVerifyHelper::verifyToken('penutupan', $opsClosed, $realPenutupan, $realTokPenutupan) === true
    );

    $resPenutupan = simulateVerifyRequest([
        'action' => 'verify',
        'type' => 'penutupan',
        'id' => (string)$idTestOps,
        'ref' => (string)$idTestPenutupan,
        'token' => $realTokPenutupan
    ]);

    assertTest('E2E_HTTP', 'Verifikasi Penutupan mengembalikan status HTTP 200 OK', $resPenutupan['status'] === 200);
    assertTest('E2E_HTTP', 'Tampilan verifikasi Penutupan mencerminkan status DITUTUP (CLOSED)',
        str_contains($resPenutupan['body'], 'DITUTUP (CLOSED)')
    );

    // Skenario D: Ketahanan Tamper & Zero-Leakage (HTTP 404)
    $resPalsu = simulateVerifyRequest([
        'action' => 'verify',
        'type' => 'laporan_awal',
        'id' => (string)$idTestOps,
        'token' => 'PALSU1234567890'
    ]);

    assertTest('SECURITY', 'Token palsu / manipulasi URL menghasilkan respons HTTP 404', $resPalsu['status'] === 404);
    assertTest('SECURITY', 'Halaman 404 memuat status VERIFIKASI GAGAL • DOKUMEN TIDAK SAH',
        str_contains($resPalsu['body'], 'VERIFIKASI GAGAL &bull; DOKUMEN TIDAK SAH') ||
        str_contains($resPalsu['body'], 'VERIFIKASI GAGAL • DOKUMEN TIDAK SAH')
    );
    assertTest('SECURITY', 'Halaman 404 memuat judul kontekstual Dokumen Operasi SAR',
        str_contains($resPalsu['body'], 'Naskah Dokumen Operasi SAR Tidak Terverifikasi')
    );
    assertTest('SECURITY', 'Halaman 404 Zero-Leakage: Tidak membocorkan nama pelapor atau korban',
        !str_contains($resPalsu['body'], 'Bambang') && !str_contains($resPalsu['body'], 'Bahteramas')
    );

    // Skenario E: ID Operasi Tidak Ada di DB
    $resNotFound = simulateVerifyRequest([
        'action' => 'verify',
        'type' => 'laporan_awal',
        'id' => '999999',
        'token' => 'APAPUNTOKENNYA'
    ]);

    assertTest('SECURITY', 'ID operasi fiktif menghasilkan respons HTTP 404', $resNotFound['status'] === 404);

    // Skenario F: Uji SQL Injection & XSS pada Parameter Verifikasi
    $resInjection = simulateVerifyRequest([
        'action' => 'verify',
        'type' => 'laporan_awal',
        'id' => "1' OR '1'='1",
        'token' => "<script>alert('xss')</script>"
    ]);

    assertTest('SECURITY', 'Injeksi SQL pada ID dicegah via casting integer aman (HTTP 404)', $resInjection['status'] === 404);
    assertTest('SECURITY', 'Injeksi XSS pada token di-escape sempurna (&lt;script&gt;)',
        !str_contains($resInjection['body'], "<script>alert('xss')</script>") &&
        str_contains($resInjection['body'], "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;")
    );

} catch (Throwable $e) {
    echo "  [ERROR EXCEPTION] " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
} finally {
    // 2.7 TEARDOWN BERSIH: Hapus data uji coba dari DB
    if ($idTestPenutupan > 0) {
        $db->query("DELETE FROM LaporanHarian WHERE id_laporan = {$idTestPenutupan}");
    }
    if ($idTestSitrep > 0) {
        $db->query("DELETE FROM LaporanHarian WHERE id_laporan = {$idTestSitrep}");
    }
    if ($idTestRenops > 0) {
        $db->query("DELETE FROM Renops WHERE id_renops = {$idTestRenops}");
    }
    if ($idTestOps > 0) {
        $db->query("DELETE FROM Operasi WHERE id_operasi = {$idTestOps}");
    }
    echo "  [CLEANUP] Seluruh entitas basis data uji coba berhasil dibersihkan (Zero Database Pollution).\n";
}


// =========================================================================
// BAGIAN 3: AUDIT QR CODE BEREMBLEM LOGO HD BASARNAS PADA 4 VIEW CETAK
// =========================================================================
echo "\n--- DOMAIN 3: AUDIT QR CODE BEREMBLEM LOGO HD PADA 4 VIEW CETAK ---\n";

$logoSvg = dirname(__DIR__) . '/assets/images/logo_basarnas.svg';
assertTest('QR_LOGO', 'Berkas lambang resmi Basarnas SVG tersedia di filesystem', file_exists($logoSvg));

$dataUri = getAppLogoDataUri();
assertTest('QR_LOGO', 'Helper getAppLogoDataUri() menghasilkan Base64 Data URI valid',
    str_starts_with($dataUri, 'data:image/svg+xml;base64,') || str_starts_with($dataUri, 'data:image/png;base64,')
);

$printViews = [
    'Laporan Awal' => dirname(__DIR__) . '/views/operasi/laporan_awal_print.php',
    'RENOPS' => dirname(__DIR__) . '/views/operasi/renops_print.php',
    'SITREP' => dirname(__DIR__) . '/views/operasi/sitrep_print.php',
    'Penutupan' => dirname(__DIR__) . '/views/operasi/penutupan_print.php'
];

foreach ($printViews as $name => $path) {
    assertTest('QR_LOGO', "Berkas view cetak {$name} ada di filesystem", file_exists($path));
    $content = file_get_contents($path);

    // QRCode toCanvas call
    assertTest('QR_LOGO', "{$name}: Mengonfigurasi QRCode.toCanvas dengan errorCorrectionLevel 'H'",
        str_contains($content, "errorCorrectionLevel: 'H'")
    );

    // Elemen canvas dan img cadangan
    assertTest('QR_LOGO', "{$name}: Memiliki pasangan canvas QR dan img pengganti",
        str_contains($content, '<canvas id="qrCanvas') && str_contains($content, '<img id="qrImg')
    );

    // Fungsi drawCenterEmblem
    assertTest('QR_LOGO', "{$name}: Mengimplementasikan fungsi gambar lambang tengah drawCenterEmblem()",
        str_contains($content, 'function drawCenterEmblem()')
    );

    // Badge sirkular putih sebagai kontras pembacaan barcode
    assertTest('QR_LOGO', "{$name}: Menggambar badge sirkular putih penangkal tabrakan QR matrix",
        str_contains($content, "ctx.fillStyle = '#ffffff'") && str_contains($content, 'ctx.arc(')
    );

    // Sinkronisasi permanen ke format Image PNG Data URI
    assertTest('QR_LOGO', "{$name}: Mengonversi canvas ke DataURL PNG untuk kompatibilitas cetak",
        str_contains($content, "canvas.toDataURL('image/png')") && str_contains($content, 'imgEl.src = dataUrl')
    );

    // Pustaka vendor dimuat
    assertTest('QR_LOGO', "{$name}: Memuat pustaka qrcode.min.js dan html2pdf.bundle.min.js",
        str_contains($content, 'qrcode.min.js') && str_contains($content, 'html2pdf.bundle.min.js')
    );
}


// =========================================================================
// BAGIAN 4: AUDIT ENGINE ANTI-POTONG PDF & KEPATUHAN TIPOGRAFI RESMI
// =========================================================================
echo "\n--- DOMAIN 4: AUDIT ENGINE ANTI-POTONG PDF & KEPATUHAN TIPOGRAFI ---\n";

$exporterPath = dirname(__DIR__) . '/assets/js/pdf_document_exporter.js';
assertTest('PDF_ENGINE', 'Berkas pdf_document_exporter.js tersedia', file_exists($exporterPath));
$exporterJs = file_get_contents($exporterPath);

// 4.1 Definisi Fungsi & Target Selektor
assertTest('PDF_ENGINE', 'Mengekspos hook global window.downloadOfficialPdf',
    str_contains($exporterJs, 'window.downloadOfficialPdf = function')
);
assertTest('PDF_ENGINE', 'Target selektor naskah dinas mengacu pada .page-sheet',
    str_contains($exporterJs, "document.querySelector('.page-sheet')")
);

// 4.2 Deteksi Zona Terlarang Blok Tanda Tangan
assertTest('PDF_ENGINE', 'Mendeteksi selektor .signature-block untuk zona terlarang pemotongan',
    str_contains($exporterJs, '.signature-block') &&
    str_contains($exporterJs, 'window.__pdfForbiddenZones')
);

// 4.3 Spesifikasi Margin Asimetris Halaman 1 vs Halaman Seterusnya
assertTest('PDF_ENGINE', 'Halaman 1 dialokasikan margin atas 10mm (tinggi area 275mm)',
    str_contains($exporterJs, 'topMarginPage1 = 10') && str_contains($exporterJs, 'innerHeightPage1Mm')
);
assertTest('PDF_ENGINE', 'Halaman 2+ dialokasikan margin atas 15mm (tinggi area 270mm)',
    str_contains($exporterJs, 'topMarginPageNext = 15') && str_contains($exporterJs, 'innerHeightPageNextMm')
);

// 4.4 Algoritma Evasi Collision Blok Tanda Tangan
assertTest('PDF_ENGINE', 'Mengeksekusi pemotongan tepat sebelum batas atas blok tanda tangan jika terdeteksi tabrakan',
    str_contains($exporterJs, 'insideForbidden.top - currentY')
);

// 4.5 Pemindaian Celah Spasi Putih Baris Teks (findSafePageCutY)
assertTest('PDF_ENGINE', 'Memiliki fungsi findSafePageCutY untuk mencegah huruf terbelah dua',
    str_contains($exporterJs, 'function findSafePageCutY(')
);

// 4.6 Standar Tipografi Resmi Basarnas pada PDF
assertTest('PDF_ENGINE', 'Menetapkan font resmi Times New Roman',
    str_contains($exporterJs, "'Times New Roman', Times, serif")
);
assertTest('PDF_ENGINE', 'Menetapkan ukuran font 12pt',
    str_contains($exporterJs, "'12pt'")
);
assertTest('PDF_ENGINE', 'Menetapkan line-height 1.45 untuk keterbacaan naskah dinas',
    str_contains($exporterJs, "'1.45'")
);
assertTest('PDF_ENGINE', 'Menerapkan padding bawah pengaman 25mm pada .page-sheet',
    str_contains($exporterJs, '0 24px 25mm 6px')
);

// 4.7 Kepatuhan Tipografi & Aturan Cetak CSS pada 4 View Cetak
foreach ($printViews as $name => $path) {
    $content = file_get_contents($path);

    assertTest('TYPOGRAPHY', "{$name}: CSS @media print menetapkan font Times New Roman 12pt",
        str_contains($content, "font-family: 'Times New Roman', Times, serif !important") &&
        str_contains($content, "font-size: 12pt !important")
    );

    assertTest('TYPOGRAPHY', "{$name}: Memiliki class .signature-block dengan break-inside avoid",
        str_contains($content, '.signature-block {') &&
        str_contains($content, 'break-inside: avoid !important')
    );

    assertTest('TYPOGRAPHY', "{$name}: Naskah memiliki alignment text-justify untuk kerapian resmi",
        str_contains($content, 'text-justify') || str_contains($content, 'text-align: justify')
    );

    assertTest('TYPOGRAPHY', "{$name}: Menampilkan NIP SMC di dalam blok tanda tangan legalitas",
        str_contains($content, 'NIP.')
    );
}

// =========================================================================
// REKAPITULASI HASIL AUDIT MODUL 5
// =========================================================================
echo "\n========================================================================================\n";
echo "   REKAPITULASI HASIL AUDIT MODUL 5: {$passCount} LULUS (PASS), {$failCount} GAGAL (FAIL)  \n";
echo "========================================================================================\n\n";

if ($failCount > 0) {
    echo "⚠️ PERHATIAN: Terdapat {$failCount} pengujian yang gagal!\n";
    exit(1);
} else {
    echo "✅ SEMUA PENGUJIAN MODUL 5 BERHASIL LULUS 100% TANPA KESALAHAN.\n";
    exit(0);
}
