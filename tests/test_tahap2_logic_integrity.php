<?php
/**
 * Test Suite: Perbaikan Audit Tahap 2 (Business Logic, Integritas Relasi & Konkurensi)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: hanya CLI.' . PHP_EOL);
}

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$total = 0;

function assertT2(bool $cond, string $msg): void {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] {$msg}\n";
    } else {
        echo " [FAIL] {$msg}\n";
    }
}

echo "=== TEST TAHAP 2: BUSINESS LOGIC, INTEGRITAS DATA & PENGUJIAN ===\n\n";

$db = getDB();
$phpBin = PHP_BINARY ?: 'php';
$rootDir = ROOT_DIR;

// ---------------------------------------------------------
// 1. Uji Finding #10: Global Exception Handler di CLI keluar dengan exit code 1
// ---------------------------------------------------------
$tmpCrashFile = tempnam(sys_get_temp_dir(), 'qrsar_cli_crash_');
file_put_contents($tmpCrashFile, sprintf(
    '<?php require %s; throw new Exception("Simulasi Crash Fatal CLI");',
    var_export($rootDir . '/config/app.php', true)
));
$out = [];
$code = 0;
exec(escapeshellarg($phpBin) . ' ' . escapeshellarg($tmpCrashFile) . ' 2>&1', $out, $code);
@unlink($tmpCrashFile);
assertT2($code === 1, 'qr_sar_exception_handler keluar dengan exit code 1 pada CLI saat terjadi exception (bukan 0)');
assertT2(str_contains(implode("\n", $out), '[QR-SAR CLI ERROR] Simulasi Crash Fatal CLI'), 'qr_sar_exception_handler mencetak pesan galat ke STDERR di CLI');

// Verifikasi test_gis_ssot_and_map_rendering.php tidak lagi crash diam-diam (menghasilkan 25+ assertions)
$ssotOut = [];
$ssotCode = 0;
exec('DB_DATABASE=qrsar_test ' . escapeshellarg($phpBin) . ' ' . escapeshellarg(__DIR__ . '/test_gis_ssot_and_map_rendering.php') . ' 2>&1', $ssotOut, $ssotCode);
$ssotRaw = implode("\n", $ssotOut);
assertT2($ssotCode === 0 && (str_contains($ssotRaw, 'Success Rate : 100%') || str_contains($ssotRaw, 'Total Assertions : 28') || str_contains($ssotRaw, 'Total Assertions : 25')), 'test_gis_ssot_and_map_rendering.php mengeksekusi asersi tanpa crash');

// ---------------------------------------------------------
// 2. Uji Finding #5: Siklus Tutup-Buka Operasi & Status Saat Hapus/Edit Laporan
// ---------------------------------------------------------
$idOpCycle = OperasiModel::create([
    'nama_operasi' => 'Uji Siklus Penutupan T2',
    'nama_pelapor' => 'Samsul',
    'lokasi_kejadian' => 'Laut Banda',
    'waktu_lapor' => '2026-11-01 07:00:00',
    'waktu_berangkat' => '2026-11-01 07:30:00',
]);
try {
    $idLapTutup = LaporanModel::create([
        'id_operasi' => $idOpCycle,
        'hari_ke' => 'H.1',
        'jenis_laporan' => 'PENUTUPAN',
        'isi_laporan' => 'Operasi selesai dan seluruh korban dievakuasi.',
        'waktu_laporan' => '2026-11-01 17:00:00',
        'waktu_debrief' => '2026-11-01 17:30:00',
    ]);
    $st1 = (string)$db->query("SELECT status FROM Operasi WHERE id_operasi = {$idOpCycle}")->fetchColumn();
    assertT2($st1 === 'CLOSED', 'Membuat laporan berjenis PENUTUPAN otomatis mengunci Operasi menjadi CLOSED');

    // Ubah jenis laporan penutupan kembali ke PERKEMBANGAN
    LaporanModel::update($idLapTutup, [
        'hari_ke' => 'H.1',
        'jenis_laporan' => 'PERKEMBANGAN',
        'isi_laporan' => 'Pencarian masih berlanjut (salah input jenis laporan).',
    ]);
    $st2 = (string)$db->query("SELECT status FROM Operasi WHERE id_operasi = {$idOpCycle}")->fetchColumn();
    assertT2($st2 === 'OPEN', 'Mengoreksi jenis laporan PENUTUPAN ke PERKEMBANGAN otomatis membuka status Operasi kembali ke OPEN');

    // Ubah lagi ke PENUTUPAN
    LaporanModel::update($idLapTutup, [
        'hari_ke' => 'H.1',
        'jenis_laporan' => 'PENUTUPAN',
        'isi_laporan' => 'Penutupan resmi operasi.',
    ]);
    $st3 = (string)$db->query("SELECT status FROM Operasi WHERE id_operasi = {$idOpCycle}")->fetchColumn();
    assertT2($st3 === 'CLOSED', 'Mengubah kembali ke PENUTUPAN mengunci kembali Operasi ke CLOSED');

    // Hapus laporan penutupan: operasi harus otomatis kembali ke OPEN
    LaporanModel::delete($idLapTutup);
    $st4 = (string)$db->query("SELECT status FROM Operasi WHERE id_operasi = {$idOpCycle}")->fetchColumn();
    assertT2($st4 === 'OPEN', 'Menghapus satu-satunya laporan PENUTUPAN otomatis membuka status Operasi kembali ke OPEN');

    // Tutup manual dan uji rollbackPenutupan dengan catatan alasan
    OperasiModel::closeOperation($idOpCycle, '2026-11-01 18:00:00');
    $reopenSuccess = OperasiModel::rollbackPenutupan($idOpCycle, '2026-11-02 08:00:00', 'Ditemukan serpihan kapal baru di pesisir');
    $st5 = (string)$db->query("SELECT status FROM Operasi WHERE id_operasi = {$idOpCycle}")->fetchColumn();
    assertT2($reopenSuccess && $st5 === 'OPEN', 'rollbackPenutupan berhasil membuka kembali operasi SAR dengan catatan alasan');
} finally {
    OperasiModel::delete($idOpCycle);
}

// ---------------------------------------------------------
// 3. Uji Finding #8: POB Tanpa Identitas di Naskah BRA & Rekapitulasi Korban
// ---------------------------------------------------------
$idOpPob = OperasiModel::create([
    'nama_operasi' => 'KM Surya Indah Tenggelam',
    'nama_pelapor' => 'Nahkoda',
    'lokasi_kejadian' => 'Selat Buton',
    'jumlah_pob' => 15,
    'waktu_lapor' => '2026-11-02 06:00:00',
    'waktu_berangkat' => '2026-11-02 06:30:00',
    'korban' => [
        ['nama_korban' => 'Ali', 'umur' => '30', 'jk' => 'Laki-laki', 'status_korban' => 'S'],
        ['nama_korban' => 'Budi', 'umur' => '25', 'jk' => 'Laki-laki', 'status_korban' => 'MD'],
    ]
]);
try {
    $opsPob = OperasiModel::getById($idOpPob);
    $braMapel = BraHelper::buildSectionsFromMapel($opsPob);
    $isiAaa = '';
    foreach ($braMapel['sections'] as $sec) {
        if (($sec['kode'] ?? '') === 'AAA') $isiAaa = $sec['isi'];
    }
    assertT2(str_contains($isiAaa, 'POB 15 ORANG'), 'buildSectionsFromMapel menyertakan jumlah_pob (15 ORANG) walau korban bernama baru 2 orang');
} finally {
    OperasiModel::delete($idOpPob);
}

// ---------------------------------------------------------
// 4. Uji Finding #13: Penomoran BRA Per-Tahun, Anti-Duplikasi & Pre-Claim ID Korban
// ---------------------------------------------------------
// 4.1 Filter per-tahun getNextNomorBra
$nextBraNow = BraHelper::getNextNomorBra('MAPEL');
assertT2(isset($nextBraNow['nomor_urut']) && $nextBraNow['nomor_urut'] >= 1, 'getNextNomorBra menghasilkan nomor urut valid tahun berjalan');

// 4.2 Validasi keunikan no_bra pada BraModel::update
$idBra1 = BraModel::create([
    'kategori' => 'MAPEL',
    'no_bra' => '777/SAR-304/1126',
    'perihal' => 'UJI KEUNIKAN BRA 1 TTK',
    'sections' => [['kode' => 'AAA', 'isi' => 'KONTEN SATU']]
]);
$idBra2 = BraModel::create([
    'kategori' => 'MAPEL',
    'no_bra' => '778/SAR-304/1126',
    'perihal' => 'UJI KEUNIKAN BRA 2 TTK',
    'sections' => [['kode' => 'AAA', 'isi' => 'KONTEN DUA']]
]);
try {
    $dupRejected = false;
    try {
        BraModel::update($idBra2, [
            'kategori' => 'MAPEL',
            'no_bra' => '777/SAR-304/1126', // sengaja samakan dengan idBra1
            'perihal' => 'UJI COBA TABRAKAN NO BRA TTK',
            'sections' => [['kode' => 'AAA', 'isi' => 'KONTEN DUPLIKAT']]
        ]);
    } catch (InvalidArgumentException $e) {
        $dupRejected = true;
    }
    assertT2($dupRejected, 'BraModel::update menolak nomor no_bra yang sudah digunakan dokumen lain');
} finally {
    BraModel::delete($idBra1);
    BraModel::delete($idBra2);
}

// 4.3 Pre-claim explicit ID korban pada OperasiModel::update
$idOpHijack = OperasiModel::create([
    'nama_operasi' => 'Uji Anti ID Hijacking Korban',
    'nama_pelapor' => 'Samsir',
    'lokasi_kejadian' => 'Teluk Kendari',
    'waktu_lapor' => '2026-11-03 08:00:00',
    'waktu_berangkat' => '2026-11-03 08:30:00',
    'korban' => [
        ['nama_korban' => 'Korban Asli A', 'status_korban' => 'DP'],
        ['nama_korban' => 'Korban Asli B', 'status_korban' => 'DP']
    ]
]);
try {
    $rowsAwal = $db->query("SELECT id_korban, nama_korban FROM Korban WHERE id_operasi = {$idOpHijack} ORDER BY id_korban ASC")->fetchAll(PDO::FETCH_ASSOC);
    $idA = (int)$rowsAwal[0]['id_korban'];
    $idB = (int)$rowsAwal[1]['id_korban'];

    // Update dengan menyisipkan korban baru di urutan PALING ATAS tanpa id_korban
    OperasiModel::update($idOpHijack, [
        'korban' => [
            ['nama_korban' => 'Korban Baru Disisipkan', 'status_korban' => 'DP'], // tidak punya id_korban
            ['id_korban' => $idA, 'nama_korban' => 'Korban Asli A (Updated)', 'status_korban' => 'S'],
            ['id_korban' => $idB, 'nama_korban' => 'Korban Asli B (Updated)', 'status_korban' => 'MD']
        ]
    ]);

    $namaAAfter = (string)$db->query("SELECT nama_korban FROM Korban WHERE id_korban = {$idA}")->fetchColumn();
    $statusAAfter = (string)$db->query("SELECT status_korban FROM Korban WHERE id_korban = {$idA}")->fetchColumn();
    assertT2($namaAAfter === 'Korban Asli A (Updated)' && $statusAAfter === 'S',
        'Pre-claim ID korban mencegah korban baru di posisi atas membajak ID milik korban lama');
} finally {
    OperasiModel::delete($idOpHijack);
}

// 4.4 Penghapusan file fisik peta hanya setelah DB commit
$idOpFileDel = OperasiModel::create([
    'nama_operasi' => 'Uji Hapus File Snapshot Setelah Commit',
    'nama_pelapor' => 'Pelapor File',
    'lokasi_kejadian' => 'Kendari',
    'waktu_lapor' => '2026-11-04 08:00:00',
    'waktu_berangkat' => '2026-11-04 08:30:00',
]);
$pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$dummyPngBase64 = 'data:image/png;base64,' . base64_encode($pngBytes);
$savedSnap = DokumenPetaSnapshotModel::saveSnapshot([
    'id_operasi' => $idOpFileDel,
    'jenis_dokumen' => 'RENOPS',
    'image_data' => $dummyPngBase64
]);
$filePathPhys = ROOT_DIR . '/' . $savedSnap['image_path'];
assertT2(is_file($filePathPhys), 'Berkas cuplikan peta fisik tersimpan di disk sebelum operasi dihapus');

OperasiModel::delete($idOpFileDel);
assertT2(!is_file($filePathPhys), 'Berkas fisik cuplikan peta berhasil dihapus dari disk setelah DB commit penghapusan Operasi');

echo "\nTotal: {$total}, Lolos: {$passed}\n";
exit($passed === $total ? 0 : 1);
