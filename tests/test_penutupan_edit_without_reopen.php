<?php
/**
 * QR-SAR - Verification Suite: Izinkan Edit Laporan Penutupan Tanpa Reopen Operasi
 * Basarnas KPP Kendari
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../helpers/TemplateHelper.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/LaporanModel.php';

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
echo "  TEST SUITE: EDIT LAPORAN PENUTUPAN TANPA REOPEN OPERASI (CLOSED)\n";
echo "  KPP KELAS A KENDARI - BASARNAS\n";
echo "=========================================================================\n\n";

$db = Database::getConnection();

// -------------------------------------------------------------------------
// 1. Static View & Controller Inspection (TDD Checks)
// -------------------------------------------------------------------------
echo "--- 1. Static Code Analysis (Views & Controller) ---\n";

$tabSitrepPath = ROOT_DIR . '/views/operasi/partials/tab_sitrep.php';
$tabSitrepContent = file_get_contents($tabSitrepPath) ?: '';

// Cek bahwa tombol Edit di tab_sitrep.php dapat diakses oleh laporan penutupan saat CLOSED
$hasPenutupanEditAccess = (
    str_contains($tabSitrepContent, '$isOpen || $isPenutupan') ||
    str_contains($tabSitrepContent, '$isPenutupan || $isOpen')
);
report("Tombol Edit di tab_sitrep.php aktif untuk Laporan Penutupan saat CLOSED (\$isOpen || \$isPenutupan)", $hasPenutupanEditAccess);

// Cek bahwa tombol Buka Kembali diubah/dipertegas untuk Perpanjangan Operasi SAR
$hasPerpanjangOpsLabel = (
    str_contains($tabSitrepContent, 'Perpanjang Operasi') ||
    str_contains($tabSitrepContent, 'Perpanjangan Operasi')
);
report("Banner/Tombol di tab_sitrep.php menegaskan fungsi Perpanjangan Operasi SAR", $hasPerpanjangOpsLabel);

// Cek OperasiController menjaga hari_ke asli saat edit
$controllerPath = ROOT_DIR . '/controllers/OperasiController.php';
$controllerContent = file_get_contents($controllerPath) ?: '';

$hasHariKePreservation = (
    str_contains($controllerContent, "origTgl === \$tglLap") ||
    str_contains($controllerContent, "\$laporan['hari_ke']") && str_contains($controllerContent, "\$editMode")
);
report("OperasiController memelihara hari_ke dokumen saat edit laporan penutupan", $hasHariKePreservation);

// Cek detail.php mempertegas tombol Perpanjang Operasi di header
$detailPath = ROOT_DIR . '/views/operasi/detail.php';
$detailContent = file_get_contents($detailPath) ?: '';
$hasDetailPerpanjang = (
    str_contains($detailContent, 'Perpanjang Operasi') ||
    str_contains($detailContent, 'Perpanjang')
);
report("Header detail.php menggunakan label 'Perpanjang Operasi'", $hasDetailPerpanjang);

// -------------------------------------------------------------------------
// 2. Dynamic DB & Model End-to-End Lifecycle Test
// -------------------------------------------------------------------------
echo "\n--- 2. Dynamic E2E Lifecycle: Create -> Close -> Edit Penutupan without Reopen ---\n";

$testNomorOps = 'TEST-OPS-EDIT-PENUTUPAN-' . time();
$dummyOpId = null;
$dummyLapbang1Id = null;
$dummyPenutupanId = null;

try {
    // 2.1 Buat Operasi SAR Baru
    $opData = [
        'nama_operasi' => 'Operasi SAR Uji Edit Penutupan Tanpa Reopen',
        'nomor_operasi' => $testNomorOps,
        'nomor_registrasi' => $testNomorOps,
        'jenis_kejadian' => 'KMM',
        'lokasi' => 'Perairan Wakatobi',
        'latitude' => -5.3210,
        'longitude' => 123.5678,
        'waktu_lapor' => '2026-11-10 08:00:00',
        'waktu_berangkat' => '2026-11-10 08:30:00',
        'status' => 'OPEN',
        'smc' => 'SAR Mission Coordinator Test',
        'cuaca' => 'Cerah Berawan'
    ];
    $dummyOpId = OperasiModel::create($opData);
    report("Operasi dummy berhasil dibuat", $dummyOpId > 0, "ID: {$dummyOpId}");

    // 2.2 Terbitkan LAPBANG H.1
    $lapbang1Data = [
        'id_operasi' => $dummyOpId,
        'hari_ke' => 'H.1',
        'jenis_laporan' => 'PERKEMBANGAN',
        'waktu_laporan' => '2026-11-10 18:00:00',
        'isi_laporan' => 'Pencarian H.1 dilaksanakan dengan hasil sementara nihil.',
        'cuaca' => 'Cerah Berawan'
    ];
    $dummyLapbang1Id = LaporanModel::create($lapbang1Data);
    $opAfterH1 = OperasiModel::getById($dummyOpId);
    report("LAPBANG H.1 terbit & status operasi tetap OPEN", ($dummyLapbang1Id > 0 && ($opAfterH1['status'] ?? '') === 'OPEN'));

    // 2.3 Terbitkan Laporan Penutupan H.2 (Operasi otomatis CLOSED)
    $penutupanData = [
        'id_operasi' => $dummyOpId,
        'hari_ke' => 'H.2',
        'jenis_laporan' => 'PENUTUPAN',
        'waktu_laporan' => '2026-11-11 17:00:00',
        'waktu_debrief' => '2026-11-11 17:30:00',
        'isi_laporan' => 'Korban berhasil ditemukan dalam keadaan selamat. Operasi SAR diusulkan untuk ditutup.',
        'cuaca' => 'Tenang'
    ];
    $dummyPenutupanId = LaporanModel::create($penutupanData);
    $opAfterClose = OperasiModel::getById($dummyOpId);
    report("Laporan Penutupan H.2 terbit", $dummyPenutupanId > 0, "ID Penutupan: {$dummyPenutupanId}");
    report("Status operasi otomatis CLOSED", ($opAfterClose['status'] ?? '') === 'CLOSED');
    report("Waktu selesai operasi tercatat", !empty($opAfterClose['waktu_selesai']));
    report("Waktu reopen operasi tetap NULL", empty($opAfterClose['waktu_reopen']));

    // 2.4 Edit Laporan Penutupan saat Operasi masih CLOSED (Tanpa Reopen!)
    // Mensimulasikan koreksi naskah evaluasi/penutupan
    $updatedIsi = 'KOREKSI RESMI: Seluruh 2 korban berhasil dievakuasi selamat oleh Tim SAR Gabungan. Operasi SAR resmi ditutup.';
    $updatedDebrief = '2026-11-11 17:45:00';
    $editPayload = [
        'hari_ke' => 'H.2', // Nilai hari_ke asli yang harus dipertahankan
        'jenis_laporan' => 'PENUTUPAN',
        'waktu_laporan' => '2026-11-11 17:00:00',
        'waktu_debrief' => $updatedDebrief,
        'isi_laporan' => $updatedIsi,
        'cuaca' => 'Cerah'
    ];
    $updateSuccess = LaporanModel::update($dummyPenutupanId, $editPayload);
    report("LaporanModel::update berhasil dieksekusi saat operasi CLOSED", $updateSuccess === true);

    // Verifikasi data laporan penutupan setelah diedit
    $laporanAfterEdit = LaporanModel::getById($dummyPenutupanId);
    report("Isi laporan terbarui dengan teks koreksi", ($laporanAfterEdit['isi_laporan'] ?? '') === $updatedIsi);
    report("Hari ke tetap H.2 (TIDAK bermutasi menjadi H + X)", ($laporanAfterEdit['hari_ke'] ?? '') === 'H.2', "Tercatat: " . ($laporanAfterEdit['hari_ke'] ?? ''));
    report("Jenis laporan tetap PENUTUPAN", ($laporanAfterEdit['jenis_laporan'] ?? '') === 'PENUTUPAN');

    // Verifikasi status operasi setelah Laporan Penutupan diedit
    $opAfterEdit = OperasiModel::getById($dummyOpId);
    report("Status operasi SAR TETAP 'CLOSED'", ($opAfterEdit['status'] ?? '') === 'CLOSED');
    report("Waktu reopen operasi TETAP NULL (tidak terpicu rollback)", empty($opAfterEdit['waktu_reopen']));
    report("Waktu selesai tersinkronisasi dengan debrief terkoreksi", ($opAfterEdit['waktu_selesai'] ?? '') === $updatedDebrief);

} finally {
    // Cleanup data dummy test
    if ($dummyOpId) {
        $db->prepare("DELETE FROM LaporanHarian WHERE id_operasi = :op")->execute([':op' => $dummyOpId]);
        $db->prepare("DELETE FROM Operasi WHERE id_operasi = :op")->execute([':op' => $dummyOpId]);
    }
}

// -------------------------------------------------------------------------
// Summary
// -------------------------------------------------------------------------
echo "\n=========================================================================\n";
echo "  HASIL PENGUJIAN: {$totalPassed} PASS, {$totalFailed} FAIL\n";
echo "=========================================================================\n\n";

if ($totalFailed > 0) {
    exit(1);
}
exit(0);
