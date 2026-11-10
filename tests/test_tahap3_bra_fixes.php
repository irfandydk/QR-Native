<?php
/**
 * Test Suite Tahap 3: ISSUE-08 dan ISSUE-10
 * Verifikasi Pemisahan MENBRA & Integrasi 3 Kapal Resmi Basarnas Kendari
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../helpers/BraHelper.php';
require_once __DIR__ . '/../models/BraModel.php';

echo "=== START TEST SUITE TAHAP 3 (ISSUE-08 & ISSUE-10) ===\n\n";

$db = getDB();
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

// -------------------------------------------------------------
// BAGIAN 1: UJI ISSUE-08 (Pemisahan MENBRA dari SIFAT/SEGERA)
// -------------------------------------------------------------
echo "--- 1. UJI ISSUE-08: Sanitasi & Pemisahan MENBRA ---\n";

// 1.1 BraModel::create() sanitasi 'SEGERA'
$testBraId = BraModel::create([
    'kategori' => 'MAPEL',
    'derajat' => 'SGR',
    'menbra' => 'SEGERA', // Nilai lama yang salah
    'perihal' => 'MAPEL RADIO SIARAN INFORMASI CUACA BURUK TTK',
    'aksi' => 'STASIUN RADIO PANTAI KENDARI',
    'sections' => [
        ['kode' => 'AAA', 'isi' => 'INFO CUACA BURUK DARI BMKG MARITIM KENDARI TTK'],
        ['kode' => 'BBB', 'isi' => 'KAPAL DIHIMBAU WASPADA TINGGI GELOMBANG MENCAPAI 2 METER TTK'],
        ['kode' => 'CCC', 'isi' => 'DMKN SBG LAPORAN KMA KA KPP KENDARI KRM TTK HBS']
    ]
]);

$savedBra = BraModel::getById($testBraId);
assertCondition(
    $savedBra !== null && empty($savedBra['menbra']),
    "BraModel::create() otomatis membersihkan 'SEGERA' dari kolom menbra sehingga tidak tersimpan sebagai 'MENBRA : SEGERA'"
);

// 1.2 BraModel::update() sanitasi '-'
BraModel::update($testBraId, [
    'kategori' => 'MAPEL',
    'menbra' => '-',
    'perihal' => 'MAPEL RADIO SIARAN INFORMASI CUACA BURUK TERUPDATE TTK'
]);
$updatedBra = BraModel::getById($testBraId);
assertCondition(
    $updatedBra !== null && empty($updatedBra['menbra']),
    "BraModel::update() otomatis membersihkan '-' dari kolom menbra"
);

// 1.3 Nilai rujukan asli harus tetap disimpan dengan benar
$realReference = '188/SAR-304/0926';
BraModel::update($testBraId, [
    'kategori' => 'MAPEL',
    'menbra' => $realReference
]);
$refBra = BraModel::getById($testBraId);
assertCondition(
    $refBra !== null && $refBra['menbra'] === $realReference,
    "Nomor rujukan telegram asli '{$realReference}' tersimpan dengan utuh dan tepat"
);

// 1.4 Verifikasi proteksi pada views/bra/view.php
$viewFile = file_get_contents(ViewHelper::resolveViewFile('bra/view'));
assertCondition(
    str_contains($viewFile, "strtoupper(trim((string)\$bra['menbra'])) !== 'SEGERA'"),
    "views/bra/view.php memiliki proteksi ketat agar teks MENBRA tidak pernah merender nilai 'SEGERA'"
);

// 1.5 Verifikasi proteksi pada views/bra/form.php
$formFile = file_get_contents(ViewHelper::resolveViewFile('bra/form'));
assertCondition(
    !str_contains($formFile, "menbraInput.value = 'SEGERA'"),
    "views/bra_form.php tidak lagi mengisikan string 'SEGERA' ke dalam menbraInput saat memilih MAPEL"
);
assertCondition(
    !str_contains($formFile, "menbraTitle.textContent = 'SIFAT BERITA'"),
    "views/bra_form.php tidak lagi mengubah label MENBRA menjadi 'SIFAT BERITA'"
);

// Cleanup test Bra
BraModel::delete($testBraId);

// 2.8 Verifikasi kontrol form UI bra_form.php
assertCondition(
    !str_contains($formFile, 'id="selectVesselChoice"') &&
    !str_contains($formFile, 'id="btnPresetPacitan"') &&
    !str_contains($formFile, 'id="btnVesselTolak"'),
    "UI views/bra_form.php tidak lagi menampilkan preset cepat Pergerakan Kapal (dihapus atas permintaan pengguna)"
);

// 2.9 Verifikasi Simpan Naskah Pergerakan Kapal ke DB & Word Count
$kapalBraId = BraModel::create([
    'kategori' => 'PERGERAKAN_KAPAL',
    'derajat' => 'SGR',
    'panggilan' => 'YCSB',
    'perihal' => 'PELAYARAN KN SAR PACITAN 102 DALAM RANGKA OPERASI SAR TTK',
    'aksi' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    'info' => 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN',
    'dari' => 'KANSAR KENDARI',
    'sections' => [
        ['kode' => 'AAA', 'isi' => 'PADA TW 1110 0800 H PERINTAH PELAYARAN KN SAR PACITAN 102 TTK'],
        ['kode' => 'BBB', 'isi' => 'DATA KAPAL KN SAR PACITAN 102 CALL SIGN YCSB TTK'],
        ['kode' => 'CCC', 'isi' => 'LAPORAN PERKEMBANGAN MENYUSUL KMA NAKHODA KN SAR PACITAN 102 KRM TTK HBS']
    ]
]);

$savedKapalBra = BraModel::getById($kapalBraId);
assertCondition(
    $savedKapalBra !== null &&
    $savedKapalBra['kategori'] === 'PERGERAKAN_KAPAL' &&
    $savedKapalBra['panggilan'] === 'YCSB' &&
    count($savedKapalBra['sections']) === 3 &&
    str_ends_with($savedKapalBra['jumlah_kata'], 'KATA'),
    "Dokumen BRA Pergerakan Kapal berhasil disimpan ke DB dengan metadata panggilan YCSB dan live word counter baku"
);

// Cleanup
BraModel::delete($kapalBraId);

// -------------------------------------------------------------
// RINGKASAN AKHIR
// -------------------------------------------------------------
echo "\n=== RINGKASAN HASIL TEST SUITE TAHAP 3 ===\n";
echo "Total Pengujian : {$testTotal}\n";
echo "Lolos (Passed)  : {$testPassed}\n";
echo "Gagal (Failed)  : " . ($testTotal - $testPassed) . "\n";

if ($testPassed === $testTotal) {
    echo "STATUS: SEMUA PENGUJIAN ISSUE-08 & ISSUE-10 BERHASIL 100%!\n";
    exit(0);
} else {
    echo "STATUS: ADA PENGUJIAN YANG GAGAL!\n";
    exit(1);
}
