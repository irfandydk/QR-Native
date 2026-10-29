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


// -------------------------------------------------------------
// BAGIAN 2: UJI ISSUE-10 (Dukungan 3 Kapal Resmi Basarnas Kendari)
// -------------------------------------------------------------
echo "\n--- 2. UJI ISSUE-10: 3 Kapal Resmi (KN SAR PACITAN, RB 210, RB 307) ---\n";

// 2.1 Cek getAvailableVessels
$vessels = BraHelper::getAvailableVessels();
assertCondition(
    count($vessels) === 3 && isset($vessels['KN_SAR_PACITAN'], $vessels['RB_210'], $vessels['RB_307']),
    "BraHelper::getAvailableVessels() mengembalikan tepat 3 kapal: KN SAR PACITAN, RB 210, dan RB 307"
);

// 2.2 Spesifikasi KN SAR PACITAN
$pacitan = $vessels['KN_SAR_PACITAN'];
assertCondition(
    $pacitan['nama_resmi'] === 'KN SAR PACITAN 102' && $pacitan['panggilan'] === 'YCSB' && str_contains($pacitan['loa'], '59,80'),
    "Spesifikasi KN SAR Pacitan 102 akurat (Call sign YCSB, LOA 59.80 M)"
);

// 2.3 Spesifikasi RESCUE BOAT 210
$rb210 = $vessels['RB_210'];
assertCondition(
    $rb210['nama_resmi'] === 'RESCUE BOAT 210 KENDARI' && $rb210['panggilan'] === 'YCSB-210' && str_contains($rb210['loa'], '36 METER'),
    "Spesifikasi Rescue Boat 210 akurat (Call sign YCSB-210, LOA 36 M)"
);

// 2.4 Spesifikasi RESCUE BOAT 307
$rb307 = $vessels['RB_307'];
assertCondition(
    $rb307['nama_resmi'] === 'RESCUE BOAT 307 KENDARI' && $rb307['panggilan'] === 'YCSB-307' && str_contains($rb307['loa'], '28 METER'),
    "Spesifikasi Rescue Boat 307 akurat (Call sign YCSB-307, LOA 28 M)"
);

// 2.5 Naskah Gerak: Tolak / Keberangkatan KN SAR Pacitan
$pacitanTolak = BraHelper::buildSectionsFromVesselMovement('KN_SAR_PACITAN', 'TOLAK');
assertCondition(
    $pacitanTolak['derajat'] === 'SGR' &&
    $pacitanTolak['panggilan'] === 'YCSB' &&
    count($pacitanTolak['sections']) >= 3 &&
    str_contains($pacitanTolak['sections'][0]['isi'], 'PELAYARAN KN SAR PACITAN 102') &&
    str_contains($pacitanTolak['sections'][1]['isi'], 'NAKHODA : RUSLAN, S.T.'),
    "Naskah Gerak TOLAK KN SAR Pacitan 102 terbentuk lengkap dengan seksi AAA, BBB, CCC baku militer"
);

// 2.6 Naskah Gerak: Pantauan Posisi RB 210
$rb210Pantau = BraHelper::buildSectionsFromVesselMovement('RB_210', 'PANTAUAN');
assertCondition(
    $rb210Pantau['derajat'] === 'SGR' &&
    $rb210Pantau['panggilan'] === 'YCSB-210' &&
    str_contains($rb210Pantau['perihal'], 'LAPORAN PERKEMBANGAN POSISI RESCUE BOAT 210 KENDARI') &&
    str_contains($rb210Pantau['sections'][0]['isi'], 'KOORDINAT') &&
    str_contains($rb210Pantau['sections'][1]['isi'], 'KONDISI METEOROLOGI DAN LAUT'),
    "Naskah Gerak PANTAUAN POSISI RB 210 terbentuk dengan koordinat, cuaca, dan kondisi material/personil"
);

// 2.7 Naskah Gerak: Sandar / Tiba RB 307
$rb307Sandar = BraHelper::buildSectionsFromVesselMovement('RB_307', 'SANDAR');
assertCondition(
    $rb307Sandar['derajat'] === 'RUTIN' &&
    $rb307Sandar['panggilan'] === 'YCSB-307' &&
    str_contains($rb307Sandar['perihal'], 'SANDAR DI') &&
    str_contains($rb307Sandar['sections'][1]['isi'], 'STATUS ALUT PASCA PELAYARAN') &&
    str_contains($rb307Sandar['sections'][2]['isi'], 'NAKHODA RESCUE BOAT 307 KENDARI'),
    "Naskah Gerak SANDAR RB 307 memiliki derajat RUTIN dan laporan status alut & personil pasca pelayaran"
);

// 2.8 Verifikasi kontrol form UI bra_form.php
assertCondition(
    str_contains($formFile, 'id="selectVesselChoice"') &&
    str_contains($formFile, 'id="btnVesselTolak"') &&
    str_contains($formFile, 'id="btnVesselPantauan"') &&
    str_contains($formFile, 'id="btnVesselSandar"') &&
    str_contains($formFile, 'id="btnPresetPacitan"') &&
    str_contains($formFile, 'id="btnPresetRB210"') &&
    str_contains($formFile, 'id="btnPresetRB307"'),
    "UI views/bra_form.php menyediakan pemilih dropdown dan tombol aksi lengkap untuk ke-3 kapal resmi"
);

// 2.9 Verifikasi Simpan Naskah Pergerakan Kapal ke DB & Word Count
$kapalBraId = BraModel::create([
    'kategori' => 'PERGERAKAN_KAPAL',
    'derajat' => $pacitanTolak['derajat'],
    'panggilan' => $pacitanTolak['panggilan'],
    'perihal' => $pacitanTolak['perihal'],
    'aksi' => $pacitanTolak['aksi'],
    'info' => $pacitanTolak['info'],
    'dari' => $pacitanTolak['dari'],
    'sections' => $pacitanTolak['sections']
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
