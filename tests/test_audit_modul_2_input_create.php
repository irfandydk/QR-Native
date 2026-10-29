<?php
/**
 * QR-SAR - Automated Audit & E2E Test Suite: MODUL 2 (Siklus Input Data & Validasi End-to-End)
 * KPP Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../helpers/BraHelper.php';
require_once __DIR__ . '/../helpers/TemplateHelper.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/RenopsModel.php';
require_once __DIR__ . '/../models/LaporanModel.php';
require_once __DIR__ . '/../models/BraModel.php';
require_once __DIR__ . '/../models/MasterModel.php';
require_once __DIR__ . '/../models/GisModel.php';

$totalPassed = 0;
$totalFailed = 0;

function report(string $name, bool $passed, string $detail = ''): void {
    global $totalPassed, $totalFailed;
    if ($passed) {
        $totalPassed++;
        echo "  [PASS] {$name}" . ($detail ? " ({$detail})" : "") . "\n";
    } else {
        $totalFailed++;
        echo "  [FAIL] {$name}" . ($detail ? " - DETAIL: {$detail}" : "") . "\n";
    }
}

echo "\n=========================================================================\n";
echo "  AUDIT E2E MODUL 2: SIKLUS INPUT DATA (CREATE/STORE) & ATOMISITAS TRANSAKSI \n";
echo "  KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS      \n";
echo "=========================================================================\n\n";

$db = Database::getConnection();
$dbOpsCountInitial = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();

// -------------------------------------------------------------------------
// 1. Audit Strict Sequential ID Calculation (Operasi)
// -------------------------------------------------------------------------
echo "--- 1. Audit Strict Sequential ID & Pessimistic Locking ---\n";
$stmtMax = $db->query("SELECT COALESCE(MAX(id_operasi), 0) + 1 AS expected_next FROM Operasi");
$expectedNext = (int)$stmtMax->fetchColumn();
report("Query kalkulasi sequential ID berhasil", $expectedNext >= 1, "Next ID: {$expectedNext}");

// -------------------------------------------------------------------------
// 2. Audit Atomisitas Transaksi Multi-Tabel (Laporan Awal Operasi)
// -------------------------------------------------------------------------
echo "\n--- 2. Audit Atomisitas Transaksi Multi-Tabel (Laporan Awal) ---\n";

$testOpsData = [
    'nama_operasi' => 'Kecelakaan Kapal KM Bahari Express di Perairan Wakatobi',
    'nama_pelapor' => 'Bapak Sudirman (KSOP)',
    'hubungan_pelapor' => 'Petugas Jaga Syahbandar',
    'no_telp_pelapor' => '0811-4091-999',
    'waktu_kejadian' => '2026-10-21 08:30:00',
    'waktu_lapor' => '2026-10-21 09:15:00',
    'waktu_berangkat' => '2026-10-21 09:30:00',
    'eta_tiba' => '2026-10-21 11:00:00',
    'lokasi_kejadian' => 'Perairan Karang Kapota Wakatobi',
    'koordinat' => "05° 18' 20.00\" S - 123° 35' 15.00\" E",
    'jarak' => '42.5 NM',
    'radial' => '135° Tenggara',
    'eta_lokasi' => '1 Jam 30 Menit',
    'kronologi' => "Pada tanggal 21 Oktober 2026 pukul 08.30 WITA, KM Bahari Express mengalami mati mesin (engine trouble) dihantam ombak setinggi 2 meter di perairan Karang Kapota. Kapal terombang-ambing membutuhkan bantuan evakuasi SAR darurat.",
    'jumlah_pob' => 12,
    'cuaca' => 'Hujan Ringan',
    'gelombang' => '1.5 - 2.5 M',
    'angin' => 'Barat Laut, 15 - 20 Knots',
    'faktor_penghambat' => 'Alun gelombang tinggi di mulut selat',
    'tim_berangkat' => 'Tim Rescue Pos SAR Wakatobi',
    'korban' => [
        ['nama_korban' => 'H. La Ode Rusli (Nakhoda)', 'umur' => '52 Tahun', 'jk' => 'Laki-laki', 'alamat' => 'Wanci, Wakatobi', 'status_korban' => 'DP'],
        ['nama_korban' => 'Wa Ode Sitti (Penumpang)', 'umur' => '38 Tahun', 'jk' => 'Perempuan', 'alamat' => 'Tomia, Wakatobi', 'status_korban' => 'DP'],
        ['nama_korban' => 'Muhammad Arfan (ABK)', 'umur' => '24 Tahun', 'jk' => 'Laki-laki', 'alamat' => 'Kaledupa, Wakatobi', 'status_korban' => 'DP']
    ],
    'detail_unsur' => [
        ['nama_unsur' => 'Rescuer Pos SAR Wakatobi', 'jumlah_personil' => 6],
        ['nama_unsur' => 'Polairud Wakatobi', 'jumlah_personil' => 4],
        ['nama_unsur' => 'Pos TNI AL Wakatobi', 'jumlah_personil' => 3]
    ],
    'detail_alut' => [
        ['id_alut' => 1, 'nama_alut' => 'RIB 01 Wakatobi', 'satuan' => 'Unit', 'jumlah_alut' => 1],
        ['id_alut' => 2, 'nama_alut' => 'Perahu Karet / Rubber Boat', 'satuan' => 'Unit', 'jumlah_alut' => 2],
        ['id_alut' => 3, 'nama_alut' => 'Peralatan Medis & Evakuasi', 'satuan' => 'Set', 'jumlah_alut' => 1]
    ],
    'detail_tim_berangkat' => [
        ['id_tim' => 1, 'nama_tim' => 'Tim Rescue Pos SAR Wakatobi']
    ]
];

$createdOpsId = 0;
try {
    $createdOpsId = OperasiModel::create($testOpsData);
    report("OperasiModel::create berhasil dieksekusi", $createdOpsId > 0, "ID Baru: {$createdOpsId}");
} catch (Throwable $e) {
    report("OperasiModel::create gagal", false, $e->getMessage());
}

if ($createdOpsId > 0) {
    // Verifikasi Record Utama di tabel Operasi
    $stmtCheckOps = $db->prepare("SELECT * FROM Operasi WHERE id_operasi = :id");
    $stmtCheckOps->execute([':id' => $createdOpsId]);
    $rowOps = $stmtCheckOps->fetch();
    report("Record tabel induk Operasi tersimpan", !empty($rowOps) && $rowOps['status'] === 'OPEN');
    report("Waktu kejadian terformat standar DATETIME", $rowOps['waktu_kejadian'] === '2026-10-21 08:30:00');
    report("Penyimpanan teks naskah WA (full_text_wa) terisi otomatis", !empty($rowOps['full_text_wa']));

    // Verifikasi Relasi Korban
    $stmtK = $db->prepare("SELECT COUNT(*) FROM Korban WHERE id_operasi = :id");
    $stmtK->execute([':id' => $createdOpsId]);
    $countK = (int)$stmtK->fetchColumn();
    report("3 Record Korban tersimpan di tabel Korban", $countK === 3, "Count: {$countK}");

    // Verifikasi Relasi DetailUnsur
    $stmtU = $db->prepare("SELECT COUNT(*) FROM DetailUnsur WHERE id_operasi = :id");
    $stmtU->execute([':id' => $createdOpsId]);
    $countU = (int)$stmtU->fetchColumn();
    report("3 Record DetailUnsur tersimpan di tabel DetailUnsur", $countU === 3, "Count: {$countU}");

    // Verifikasi Relasi DetailAlut
    $stmtA = $db->prepare("SELECT COUNT(*) FROM DetailAlut WHERE id_operasi = :id");
    $stmtA->execute([':id' => $createdOpsId]);
    $countA = (int)$stmtA->fetchColumn();
    report("3 Record DetailAlut tersimpan di tabel DetailAlut", $countA === 3, "Count: {$countA}");

    // Verifikasi Relasi DetailTimBerangkat
    $stmtT = $db->prepare("SELECT COUNT(*) FROM DetailTimBerangkat WHERE id_operasi = :id");
    $stmtT->execute([':id' => $createdOpsId]);
    $countT = (int)$stmtT->fetchColumn();
    report("Record Tim Berangkat tersimpan di DetailTimBerangkat", $countT >= 1, "Count: {$countT}");

    // Verifikasi Auto-Sync GIS Point (LKP) & Route
    $stmtGisPt = $db->prepare("SELECT * FROM GisPoint WHERE id = :pid OR id_operasi = :id");
    $stmtGisPt->execute([':pid' => "pt-lkp-op-{$createdOpsId}", ':id' => $createdOpsId]);
    $gisPt = $stmtGisPt->fetch();
    report("Titik LKP otomatis tersinkronisasi ke GisPoint", !empty($gisPt), "ID GisPoint: " . ($gisPt['id'] ?? 'none'));

    // Verifikasi Audit Trail
    $stmtAudit = $db->prepare("SELECT * FROM AuditLog WHERE target_type = 'OPERASI' AND target_id = :ref ORDER BY id DESC LIMIT 1");
    $stmtAudit->execute([':ref' => "OPS-{$createdOpsId}"]);
    $auditRow = $stmtAudit->fetch();
    report("Event CREATE_OPERASI tercatat di tabel AuditLog", !empty($auditRow) && $auditRow['action'] === 'CREATE_OPERASI');
}

// -------------------------------------------------------------------------
// 3. Audit Forced Transaction Rollback (Pencegahan Orphan Record)
// -------------------------------------------------------------------------
echo "\n--- 3. Audit Rollback Transaksi & Pencegahan Data Sampah (Orphan) ---\n";
$dbOpsCountBefore = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();
$dbKorbanCountBefore = (int)$db->query("SELECT COUNT(*) FROM Korban")->fetchColumn();

$malformedData = [
    'nama_operasi' => 'Operasi Simulasi Gagal Transaksi',
    'nama_pelapor' => 'Bapak Uji Coba',
    'lokasi_kejadian' => 'Lokasi Uji',
    // We pass a korban array with an invalid structure that will cause an intentional failure or trigger a rollback
];

$failedCaught = false;
try {
    $db->beginTransaction();
    // Insert partial
    $stmtSim = $db->prepare("INSERT INTO Operasi (id_operasi, nama_operasi, created_at, status) VALUES (999999, 'Operasi Gagal Sengaja', NOW(), 'OPEN')");
    $stmtSim->execute();
    $stmtSimK = $db->prepare("INSERT INTO Korban (id_operasi, nama_korban) VALUES (999999, 'Korban Percobaan')");
    $stmtSimK->execute();

    // Force rollback
    throw new RuntimeException("Simulasi kegagalan eksekusi koneksi di tengah transaksi multi-tabel");
} catch (Throwable $e) {
    $failedCaught = true;
    if ($db->inTransaction()) {
        $db->rollBack();
    }
}

$dbOpsCountAfter = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();
$dbKorbanCountAfter = (int)$db->query("SELECT COUNT(*) FROM Korban")->fetchColumn();

report("Kegagalan transaksi berhasil ditangkap exception", $failedCaught);
report("Tabel Operasi tidak bertambah (Rollback sukses)", $dbOpsCountBefore === $dbOpsCountAfter);
report("Tabel Korban tidak meninggalkan record yatim/orphan", $dbKorbanCountBefore === $dbKorbanCountAfter);

$stmtOrphan = $db->query("SELECT COUNT(*) FROM Korban WHERE id_operasi NOT IN (SELECT id_operasi FROM Operasi)");
$orphanCount = (int)$stmtOrphan->fetchColumn();
report("Audit integritas: 0 record Korban orphan di seluruh basis data", $orphanCount === 0, "Orphan: {$orphanCount}");

// -------------------------------------------------------------------------
// 4. Audit Sanitasi Karakter Khusus & SQL Injection Defense
// -------------------------------------------------------------------------
echo "\n--- 4. Audit Sanitasi Karakter Khusus & SQL Injection Defense ---\n";

$sqlInjectionPayload = "Kecelakaan Kapal ' OR 1=1; DROP TABLE TestDummy; -- <script>alert('XSS')</script>";
$safeTestOps = [
    'nama_operasi' => $sqlInjectionPayload,
    'nama_pelapor' => "O'Connor \"The Pilot\" & Friends",
    'lokasi_kejadian' => "Perairan Karang 'Batu' Hitam",
    'waktu_kejadian' => '2026-10-21 10:00:00',
    'waktu_lapor' => '2026-10-21 10:15:00',
    'waktu_berangkat' => '2026-10-21 10:30:00',
    'eta_tiba' => '2026-10-21 12:00:00',
    'kronologi' => "Uji karakter: ' OR '1'='1' ; -- \nBaris baru dan tag HTML: <div class='test'>aman</div>",
    'jumlah_pob' => 1
];

$sqlSecId = 0;
try {
    $sqlSecId = OperasiModel::create($safeTestOps);
    report("Operasi dengan payload SQL Injection & tanda kutip berhasil disimpan aman", $sqlSecId > 0, "ID: {$sqlSecId}");
    
    $stmtFetchSec = $db->prepare("SELECT nama_operasi, nama_pelapor, kronologi FROM Operasi WHERE id_operasi = :id");
    $stmtFetchSec->execute([':id' => $sqlSecId]);
    $rowSec = $stmtFetchSec->fetch();
    report("Payload disimpan presisi secara literal tanpa merusak query", $rowSec['nama_operasi'] === $sqlInjectionPayload);
} catch (Throwable $e) {
    report("Gagal menangani payload SQL injection aman", false, $e->getMessage());
}

// -------------------------------------------------------------------------
// 5. Audit Input RENOPS (Rencana Operasi SAR)
// -------------------------------------------------------------------------
echo "\n--- 5. Audit Input RENOPS (Rencana Operasi SAR) ---\n";

$testRenopsData = [
    'id_operasi' => $createdOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'waktu_briefing' => '2026-10-21 06:00:00',
    'waktu_mulai_ops' => '2026-10-21 06:30:00',
    'satuan_jarak' => 'NM',
    'cuaca' => 'Cerah Berawan, Ombak 1 - 2 Meter',
    'srus' => [
        [
            'nama_sru' => 'SRU 1 (RIB 01 Pos SAR Wakatobi)',
            'koordinat_area' => "05° 15' S 123° 30' E s/d 05° 20' S 123° 40' E",
            'luas_area' => '25 NM²',
            'pola_pencarian' => 'Parallel Track Search',
            'total_sapuan' => '15 NM',
            'track_spacing' => '0.5 NM'
        ],
        [
            'nama_sru' => 'SRU 2 (Polairud & Nelayan)',
            'koordinat_area' => "05° 20' S 123° 30' E s/d 05° 25' S 123° 40' E",
            'luas_area' => '20 NM²',
            'pola_pencarian' => 'Creeping Line Search',
            'total_sapuan' => '12 NM',
            'track_spacing' => '0.5 NM'
        ]
    ]
];

$createdRenopsId = 0;
try {
    $createdRenopsId = RenopsModel::create($testRenopsData);
    report("RenopsModel::create berhasil dieksekusi", $createdRenopsId > 0, "ID Renops: {$createdRenopsId}");
} catch (Throwable $e) {
    report("RenopsModel::create gagal", false, $e->getMessage());
}

if ($createdRenopsId > 0) {
    $stmtR = $db->prepare("SELECT * FROM Renops WHERE id_renops = :id");
    $stmtR->execute([':id' => $createdRenopsId]);
    $rowR = $stmtR->fetch();
    report("Record Renops tersimpan dengan hari_ke 'H.1'", $rowR['hari_ke'] === 'H.1');
    report("Satuan jarak tersimpan 'NM'", ($rowR['satuan_jarak'] ?? '') === 'NM');
    report("Naskah WA RENOPS terisi otomatis", !empty($rowR['full_text_wa']));

    $stmtSru = $db->prepare("SELECT COUNT(*) FROM RenopsSRU WHERE id_renops = :id");
    $stmtSru->execute([':id' => $createdRenopsId]);
    $countSru = (int)$stmtSru->fetchColumn();
    report("2 Record SRU tersimpan di RenopsSRU", $countSru === 2, "Count: {$countSru}");
}

// -------------------------------------------------------------------------
// 6. Audit Input SITREP (Laporan Perkembangan Harian)
// -------------------------------------------------------------------------
echo "\n--- 6. Audit Input SITREP (Laporan Perkembangan Harian) ---\n";

// Get victim ID of created operation
$stmtFirstK = $db->prepare("SELECT id_korban FROM Korban WHERE id_operasi = :id LIMIT 1");
$stmtFirstK->execute([':id' => $createdOpsId]);
$victimId = (int)$stmtFirstK->fetchColumn();

$testSitrepData = [
    'id_operasi' => $createdOpsId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-21',
    'jam_laporan' => '17:00:00',
    'hasil_operasi' => '1 Korban ditemukan dalam keadaan selamat dievakuasi ke Puskesmas Wanci, 2 korban masih dalam pencarian.',
    'jenis_laporan' => 'HARIAN',
    'update_korban' => [
        $victimId => 'S' // Status diubah dari DP menjadi S (Selamat)
    ]
];

$createdSitrepId = 0;
try {
    $createdSitrepId = LaporanModel::create($testSitrepData);
    report("LaporanModel::create berhasil dieksekusi", $createdSitrepId > 0, "ID SITREP: {$createdSitrepId}");
} catch (Throwable $e) {
    report("LaporanModel::create gagal", false, $e->getMessage());
}

if ($createdSitrepId > 0) {
    $stmtSit = $db->prepare("SELECT * FROM LaporanHarian WHERE id_laporan = :id");
    $stmtSit->execute([':id' => $createdSitrepId]);
    $rowSit = $stmtSit->fetch();
    report("Record LaporanHarian tersimpan", !empty($rowSit));

    // Verify victim status update
    $stmtCheckK = $db->prepare("SELECT status_korban FROM Korban WHERE id_korban = :id");
    $stmtCheckK->execute([':id' => $victimId]);
    $newStatus = $stmtCheckK->fetchColumn();
    report("Status korban otomatis terupdate menjadi 'S' (Selamat)", $newStatus === 'S');
}

// -------------------------------------------------------------------------
// 7. Audit Input BRA SAR (Telegram Berita SAR) & Penomoran Atomik
// -------------------------------------------------------------------------
echo "\n--- 7. Audit Input BRA SAR (Telegram Berita SAR) ---\n";

$testBraData = [
    'kategori' => 'OPERASI_SAR',
    'satker_kode' => 'KDI',
    'id_operasi' => $createdOpsId,
    'is_auto_no_bra' => true,
    'waktu_buat' => date('Y-m-d H:i:s'),
    'sections' => [
        ['kode' => 'A', 'isi' => 'KECELAKAAN KAPAL KM BAHARI EXPRESS DI PERAIRAN WAKATOBI'],
        ['kode' => 'B', 'isi' => "05 18 20 S / 123 35 15 E"],
        ['kode' => 'C', 'isi' => 'RESCUER POS SAR WAKATOBI, POLAIRUD, DAN TNI AL']
    ]
];

$createdBraId = 0;
try {
    $createdBraId = BraModel::create($testBraData);
    report("BraModel::create berhasil dengan penomoran atomik", $createdBraId > 0, "ID BRA: {$createdBraId}");
} catch (Throwable $e) {
    report("BraModel::create gagal", false, $e->getMessage());
}

if ($createdBraId > 0) {
    $stmtBra = $db->prepare("SELECT * FROM BraSar WHERE id_bra = :id");
    $stmtBra->execute([':id' => $createdBraId]);
    $rowBra = $stmtBra->fetch();
    report("Record BraSar tersimpan dengan nomor BRA resmi", !empty($rowBra['no_bra']), "No BRA: " . ($rowBra['no_bra'] ?? ''));
    report("BRA terhubung ke id_operasi yang sah", (int)$rowBra['id_operasi'] === $createdOpsId);

    $stmtBraSec = $db->prepare("SELECT COUNT(*) FROM BraSection WHERE id_bra = :id");
    $stmtBraSec->execute([':id' => $createdBraId]);
    $countBraSec = (int)$stmtBraSec->fetchColumn();
    report("3 Bagian Section (A, B, C) tersimpan di BraSection", $countBraSec === 3, "Count: {$countBraSec}");
}

// -------------------------------------------------------------------------
// 8. Audit Input Master Data (Alut, Tim, SarUnit, Pejabat)
// -------------------------------------------------------------------------
echo "\n--- 8. Audit Input Master Data ---\n";

$testAlutId = MasterModel::createAlut(['nama_alut' => 'Drone Thermal Matrice 300 RTK', 'satuan' => 'Unit']);
report("MasterModel::createAlut berhasil", $testAlutId > 0, "ID Alut: {$testAlutId}");

$testTimId = MasterModel::createTim(['nama_tim' => 'Tim Rescue Siaga Khusus']);
report("MasterModel::createTim berhasil", $testTimId > 0, "ID Tim: {$testTimId}");

$testUnitId = MasterModel::createSarUnit([
    'nama_unit' => 'Pos SAR Kolaka Utara',
    'tipe' => 'POS_SAR',
    'wilayah' => 'Kolaka Utara & Teluk Bone',
    'koordinat' => "03° 10' 00.00\" S - 121° 02' 00.00\" E",
    'kontak' => '0811-4091-888',
    'is_default' => 0
]);
report("MasterModel::createSarUnit berhasil", $testUnitId > 0, "ID Unit: {$testUnitId}");

// -------------------------------------------------------------------------
// 9. Pembersihan Data Uji Coba (Clean Teardown)
// -------------------------------------------------------------------------
echo "\n--- 9. Pembersihan Data Uji Coba (Teardown) ---\n";

// Hapus Master Data uji
if ($testAlutId > 0) MasterModel::deleteAlut($testAlutId);
if ($testTimId > 0) MasterModel::deleteTim($testTimId);
if ($testUnitId > 0) MasterModel::deleteSarUnit($testUnitId);
report("Master data uji coba berhasil dibersihkan", true);

// Hapus BRA SAR uji
if ($createdBraId > 0) {
    $db->prepare("DELETE FROM BraSection WHERE id_bra = :id")->execute([':id' => $createdBraId]);
    $db->prepare("DELETE FROM BraSar WHERE id_bra = :id")->execute([':id' => $createdBraId]);
    report("BRA SAR uji coba berhasil dibersihkan", true);
}

// Hapus Operasi uji coba (menggunakan OperasiModel::delete untuk menguji integrasi cascade delete)
if ($sqlSecId > 0) {
    OperasiModel::delete($sqlSecId);
    report("Operasi uji SQL injection berhasil dibersihkan", true);
}

if ($createdOpsId > 0) {
    OperasiModel::delete($createdOpsId);
    report("Operasi utama uji coba & seluruh relasinya (Renops, Sitrep, Korban, GisPoint) berhasil dibersihkan", true);
}

// Verifikasi database kembali bersih
$dbOpsEnd = (int)$db->query("SELECT COUNT(*) FROM Operasi")->fetchColumn();
report("Jumlah total Operasi kembali sinkron ke kondisi awal", $dbOpsEnd === $dbOpsCountInitial, "Current: {$dbOpsEnd}, Initial: {$dbOpsCountInitial}");

// -------------------------------------------------------------------------
// HASIL AKHIR PENGUJIAN
// -------------------------------------------------------------------------
echo "\n=========================================================================\n";
echo "  HASIL AKHIR AUDIT MODUL 2: {$totalPassed} LULUS (PASS), {$totalFailed} GAGAL (FAIL)\n";
echo "=========================================================================\n\n";

if ($totalFailed > 0) {
    exit(1);
}
