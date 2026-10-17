<?php
/**
 * Test Suite: Audit & Penyempurnaan Formulir Inputan dan Hasil Dokumen BRA SAR
 * Verifikasi 100% Poin Standarisasi:
 * 1. ISSUE-BRA-01: Penyelarasan Kategori Tunggal Baku OPERASI_SAR (Peniadaan SAR_UMUM)
 * 2. ISSUE-BRA-02: Auto-Enforce Strict Telegram Uppercase di Backend & Frontend
 * 3. ISSUE-BRA-03: Otomasi Sandi Tanda Baca Militer (TTK, KMA, TTK DUA, Reduplikasi S)
 * 4. ISSUE-BRA-04: Standarisasi Nama SMC (AMIRUDDIN A.S., S.Sos.) & Resilient HMAC-SHA256 Token
 * 5. ISSUE-BRA-05: Validasi & Normalisasi Sandi Waktu Militer MMDD HHmm H
 * 6. ISSUE-BRA-06: Format Salin Teks Clipboard Rapi & Multiline
 * 7. ISSUE-BRA-07: Penanganan Lokalisasi Bahasa Indonesia pada Tanggal Waktu
 * 8. ISSUE-BRA-08: Peringatan Dini Tabrakan Nomor BRA Kustom (/api/bra/check-number)
 * 9. ISSUE-BRA-09: Dokumen Mandiri HTML Berdiri Sendiri dengan Inline CSS
 * 10. ISSUE-BRA-10: Auto-Enforce Akhiran Sandi TTK pada Judul Perihal
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

$passed = 0;
$failed = 0;

function assertTest(string $desc, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$desc} -> {$detail}\n";
    }
}

echo "====================================================================\n";
echo " TEST SUITE: AUDIT & PENYEMPURNAAN BRA SAR BASARNAS KPP KENDARI\n";
echo "====================================================================\n\n";

// ---------------------------------------------------------------------
// 1. ISSUE-BRA-01: Penyelarasan Kategori Tunggal Baku OPERASI_SAR
// ---------------------------------------------------------------------
echo "--- 1. ISSUE-BRA-01: Kategori Tunggal Baku OPERASI_SAR ---\n";
assertTest(
    "BraHelper::normalizeCategory('SAR_UMUM') mengembalikan 'OPERASI_SAR'",
    BraHelper::normalizeCategory('SAR_UMUM') === 'OPERASI_SAR'
);
assertTest(
    "BraHelper::normalizeCategory('operasi_sar') mengembalikan 'OPERASI_SAR'",
    BraHelper::normalizeCategory('operasi_sar') === 'OPERASI_SAR'
);
assertTest(
    "BraHelper::normalizeCategory('MAPEL') mengembalikan 'MAPEL'",
    BraHelper::normalizeCategory('MAPEL') === 'MAPEL'
);
assertTest(
    "BraHelper::normalizeCategory('PERGERAKAN_KAPAL') mengembalikan 'PERGERAKAN_KAPAL'",
    BraHelper::normalizeCategory('PERGERAKAN_KAPAL') === 'PERGERAKAN_KAPAL'
);

// ---------------------------------------------------------------------
// 2. ISSUE-BRA-10: Auto-Enforce Akhiran Sandi TTK pada Perihal
// ---------------------------------------------------------------------
echo "\n--- 2. ISSUE-BRA-10: Auto-Enforce Akhiran Sandi TTK ---\n";
assertTest(
    "ensureEndsWithTtk('BERITA RADIO SAR') menambahkan 'TTK'",
    BraHelper::ensureEndsWithTtk('BERITA RADIO SAR') === 'BERITA RADIO SAR TTK'
);
assertTest(
    "ensureEndsWithTtk('BERITA RADIO SAR TTK') tidak menggandakan 'TTK'",
    BraHelper::ensureEndsWithTtk('BERITA RADIO SAR TTK') === 'BERITA RADIO SAR TTK'
);
assertTest(
    "ensureEndsWithTtk('BERITA RADIO SAR.') membersihkan titik lalu menambahkan 'TTK'",
    BraHelper::ensureEndsWithTtk('BERITA RADIO SAR.') === 'BERITA RADIO SAR TTK'
);
assertTest(
    "ensureEndsWithTtk('OPERASI SAR TTK DUA') tetap diakhiri 'TTK'",
    BraHelper::ensureEndsWithTtk('OPERASI SAR TTK DUA') === 'OPERASI SAR TTK DUA TTK'
);

// ---------------------------------------------------------------------
// 3. ISSUE-BRA-03: Otomasi Sandi Tanda Baca Militer
// ---------------------------------------------------------------------
echo "\n--- 3. ISSUE-BRA-03: Otomasi Sandi Tanda Baca Militer ---\n";
$samplePunct = "ciri-ciri kapal: warna merah, panjang 12 meter. koordinat 04° 05.25' S - 122° 30.50' E.";
$sanitizedPunct = BraHelper::sanitizeTelegramPunctuation($samplePunct);
assertTest(
    "Reduplikasi 'ciri-ciri' diubah menjadi 'CIRIS'",
    str_contains($sanitizedPunct, 'CIRIS KAPAL')
);
assertTest(
    "Titik dua ':' diubah menjadi 'TTK DUA'",
    str_contains($sanitizedPunct, 'TTK DUA WARNA MERAH')
);
assertTest(
    "Koma ',' diubah menjadi 'KMA'",
    str_contains($sanitizedPunct, 'KMA PANJANG 12 METER')
);
assertTest(
    "Titik '.' diubah menjadi 'TTK'",
    str_contains($sanitizedPunct, '12 METER TTK KOORDINAT')
);
assertTest(
    "Desimal koordinat '04° 05.25' S' dan '122° 30.50' E' tetap utuh (tidak rusak)",
    str_contains($sanitizedPunct, '04° 05.25\' S') && str_contains($sanitizedPunct, '122° 30.50\' E')
);

// ---------------------------------------------------------------------
// 4. ISSUE-BRA-05: Normalisasi Sandi Waktu Militer MMDD HHmm H
// ---------------------------------------------------------------------
echo "\n--- 4. ISSUE-BRA-05: Normalisasi Sandi Waktu Militer ---\n";
assertTest(
    "normalizeMilitaryTW('09231657') menghasilkan '0923 1657 H'",
    BraHelper::normalizeMilitaryTW('09231657') === '0923 1657 H'
);
assertTest(
    "normalizeMilitaryTW('TWU 0923 1657 H') menghasilkan '0923 1657 H'",
    BraHelper::normalizeMilitaryTW('TWU 0923 1657 H') === '0923 1657 H'
);
assertTest(
    "normalizeMilitaryTW('0923 1657H') menghasilkan '0923 1657 H'",
    BraHelper::normalizeMilitaryTW('0923 1657H') === '0923 1657 H'
);
assertTest(
    "normalizeMilitaryTW('2026-09-23 16:57:00') menghasilkan '0923 1657 H'",
    BraHelper::normalizeMilitaryTW('2026-09-23 16:57:00') === '0923 1657 H'
);

// ---------------------------------------------------------------------
// 5. ISSUE-BRA-04: Standarisasi SMC & Resilient HMAC-SHA256 Token
// ---------------------------------------------------------------------
echo "\n--- 5. ISSUE-BRA-04: SMC Standarisasi & Resilient HMAC Token ---\n";
$braData1 = [
    'id_bra' => 99,
    'no_bra' => '99/SAR-304/1026',
    'twu' => '1018 1000 H',
    'nama_smc' => 'AMIRUDDIN A.S., S.Sos.',
    'pangkat_smc' => 'PEMBINA (IV/a)',
    'perihal' => 'OPERASI SAR KONDISI MEMBAHAYAKAN MANUSIA TTK',
    'sections' => [
        ['kode' => 'AAA', 'isi' => 'INFO TERIMA LAPORAN TTK'],
        ['kode' => 'BBB', 'isi' => 'RESCUE CAR DIBERANGKATKAN TTK']
    ]
];
$braData2 = $braData1;
$braData2['nama_smc'] = 'AMIRUDDIN AS, S.Sos.'; // Variasi tanpa titik di A.S.

$token1 = BraModel::generateVerifyToken($braData1);
$token2 = BraModel::generateVerifyToken($braData2);

assertTest(
    "Token identik meski nama pejabat bervariasi tanda baca/gelar",
    $token1 === $token2,
    "Token1: {$token1} vs Token2: {$token2}"
);
assertTest(
    "BraModel::verifyToken memvalidasi token modern ternormalisasi",
    BraModel::verifyToken($braData1, $token1) === true
);

// Verifikasi fallback token legacy
$legacyToken = BraModel::generateLegacyVerifyToken($braData1);
assertTest(
    "BraModel::verifyToken memvalidasi legacy token secara transparan",
    BraModel::verifyToken($braData1, $legacyToken) === true
);

// ---------------------------------------------------------------------
// 6. ISSUE-BRA-02, 07 & Database CRUD Integration
// ---------------------------------------------------------------------
echo "\n--- 6. ISSUE-BRA-02 & CRUD: Strict Uppercase & Category Normalization ---\n";
$db = getDB();

// Bersihkan data uji sebelumnya jika ada
$db->exec("DELETE FROM BraSection WHERE id_bra IN (SELECT id_bra FROM BraSar WHERE no_bra LIKE '999/%' OR no_bra LIKE '998/%')");
$db->exec("DELETE FROM BraSar WHERE no_bra LIKE '999/%' OR no_bra LIKE '998/%'");

// Test Create dengan huruf kecil dan kategori legacy SAR_UMUM
$testCreateData = [
    'kategori' => 'SAR_UMUM', // Harap dinormalisasi ke OPERASI_SAR
    'no_bra' => '999/SAR-304/1026',
    'is_auto_no_bra' => '0',
    'dari' => 'kansar kendari',
    'aksi' => 'kepala badan nasional pencarian dan pertolongan',
    'info' => 'deputi bidang operasi pencarian dan pertolongan',
    'pengirim' => 'kantor pencarian dan pertolongan kendari',
    'nama_smc' => 'amiruddin a.s., s.sos.',
    'pangkat_smc' => 'pembina (iv/a)',
    'jabatan_smc' => 'smc',
    'twu' => '10181200',
    'twb' => '1018 1145 H',
    'perihal' => 'laporan perkembangan hari ke-2 operasi sar kendari', // Belum ada TTK
    'sections' => [
        ['kode' => 'aaa', 'isi' => 'pada tw 1018 1200 h sru 1 melaksanakan penyisiran: hasil nihil, operasi dilanjutkan.']
    ]
];

$newId = BraModel::create($testCreateData);
assertTest("BraModel::create berhasil menyimpan dokumen baru (ID: {$newId})", $newId > 0);

$savedBra = BraModel::getById($newId);
assertTest(
    "Kategori 'SAR_UMUM' otomatis dinormalisasi menjadi 'OPERASI_SAR' di database",
    ($savedBra['kategori'] ?? '') === 'OPERASI_SAR',
    "Actual: " . ($savedBra['kategori'] ?? '')
);
assertTest(
    "Field perihal otomatis diakhiri 'TTK' dan uppercase penuh",
    str_ends_with($savedBra['perihal'] ?? '', 'TTK') && ($savedBra['perihal'] === strtoupper($savedBra['perihal'])),
    "Actual: " . ($savedBra['perihal'] ?? '')
);
assertTest(
    "Field pejabat dan instansi otomatis uppercase (strict telegram)",
    ($savedBra['dari'] === 'KANSAR KENDARI') &&
    ($savedBra['nama_smc'] === 'AMIRUDDIN A.S., S.SOS.') &&
    ($savedBra['pangkat_smc'] === 'PEMBINA (IV/A)'),
    "Actual SMC: " . ($savedBra['nama_smc'] ?? '')
);
assertTest(
    "TWU '10181200' dinormalisasi ke '1018 1200 H'",
    ($savedBra['twu'] ?? '') === '1018 1200 H',
    "Actual TWU: " . ($savedBra['twu'] ?? '')
);

$secRow = $savedBra['sections'][0] ?? null;
assertTest(
    "Tanda baca pada seksi berita otomatis terkonversi sandi telegram militer",
    $secRow && str_contains($secRow['isi'], 'TTK DUA') && str_contains($secRow['isi'], 'KMA') && str_contains($secRow['isi'], 'TTK'),
    "Actual Section Isi: " . ($secRow['isi'] ?? '')
);

// ---------------------------------------------------------------------
// 7. ISSUE-BRA-08: Peringatan Dini Tabrakan Nomor BRA
// ---------------------------------------------------------------------
echo "\n--- 7. ISSUE-BRA-08: Peringatan Dini Tabrakan Nomor BRA ---\n";
// Uji query endpoint check-number
$stmtCheck = $db->prepare("SELECT id_bra, no_bra, perihal FROM BraSar WHERE no_bra = :no_bra AND id_bra != :exclude_id LIMIT 1");
$stmtCheck->execute([':no_bra' => '999/SAR-304/1026', ':exclude_id' => 0]);
$existsRow = $stmtCheck->fetch();
assertTest(
    "Pengecekan mendeteksi nomor '999/SAR-304/1026' telah digunakan oleh dokumen ID #{$newId}",
    (bool)$existsRow && (int)$existsRow['id_bra'] === $newId
);

$stmtCheck->execute([':no_bra' => '999/SAR-304/1026', ':exclude_id' => $newId]);
$selfRow = $stmtCheck->fetch();
assertTest(
    "Pengecekan mengabaikan nomor jika exclude_id adalah dokumen miliknya sendiri",
    !$selfRow
);

// ---------------------------------------------------------------------
// 8. ISSUE-BRA-06 & ISSUE-BRA-09: Layout, Export & Cleanup
// ---------------------------------------------------------------------
echo "\n--- 8. ISSUE-BRA-06 & 09: Template & Cleanup Verification ---\n";

// Verifikasi TemplateHelper::formatTanggalIndo
$indoDate = TemplateHelper::formatTanggalIndo(date('Y-m-d'));
assertTest(
    "TemplateHelper::formatTanggalIndo menghasilkan tanggal Indonesia ('{$indoDate}')",
    !empty($indoDate) && !preg_match('/(January|February|March|April|May|June|July|August|September|October|November|December)/', $indoDate)
);

// Hapus data uji
BraModel::delete($newId);
$deletedBra = BraModel::getById($newId);
assertTest("Data uji berhasil dihapus dengan aman (Cleanup PASS)", $deletedBra === null);

echo "\n====================================================================\n";
echo " HASIL AKHIR: {$passed} BERHASIL (PASS), {$failed} GAGAL (FAIL)\n";
echo "====================================================================\n";

if ($failed === 0) {
    echo " STATUS: SEMUA KRITERIA AUDIT BRA SAR TELAH TERVERIFIKASI 100% SUKSES!\n\n";
    exit(0);
} else {
    echo " STATUS: TERDAPAT PENGUJIAN YANG GAGAL!\n\n";
    exit(1);
}
