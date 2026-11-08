<?php
/**
 * QR-SAR - Automated Test Suite: Fase 6 (Dekonstruksi God Helper & Standarisasi Geodesi Spasial)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertCondition(string $description, bool $condition): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "\n--- PENGUJIAN FASE 6: DEKONSTRUKSI GOD HELPER & STANDARISASI GEODESI ---\n";

// 1. Memeriksa Eksistensi Berkas Services dan Helper Baru
echo "\n1. Memeriksa Eksistensi Berkas Layanan Domain:\n";
$rootDir = dirname(__DIR__);
$files = [
    'SarCalculationService.php' => $rootDir . '/services/SarCalculationService.php',
    'DocumentNumberService.php' => $rootDir . '/services/DocumentNumberService.php',
    'WhatsAppTemplateService.php' => $rootDir . '/services/WhatsAppTemplateService.php',
    'GeoHelper.php' => $rootDir . '/helpers/GeoHelper.php',
    'TemplateHelper.php' => $rootDir . '/helpers/TemplateHelper.php',
];

foreach ($files as $name => $path) {
    assertCondition("Berkas {$name} tersedia", file_exists($path));
}

// 2. Pengujian SarCalculationService
echo "\n2. Menguji Fungsionalitas SarCalculationService:\n";
assertCondition("Class SarCalculationService dapat di-autoload", class_exists('SarCalculationService'));

$ts = SarCalculationService::parseToTimestamp('2026-10-18 09:30:00');
assertCondition("parseToTimestamp menghasilkan integer timestamp valid", is_int($ts) && $ts > 0);

$tglIndo = SarCalculationService::formatTanggalIndo('2026-10-18 09:30:00');
assertCondition("formatTanggalIndo memuat nama hari dan bulan Indonesia", str_contains($tglIndo, 'Minggu') && str_contains($tglIndo, 'Oktober'));

$tglSimple = SarCalculationService::formatTanggalSimple('2026-10-18 09:30:00');
assertCondition("formatTanggalSimple menghasilkan format d B Y", str_contains($tglSimple, '18 Oktober 2026'));

$jamWita = SarCalculationService::formatJamWITA('2026-10-18 09:30:00');
assertCondition("formatJamWITA menghasilkan H:i", $jamWita === '09:30');

$tw = SarCalculationService::formatTW('2026-10-18 09:30:00');
assertCondition("formatTW menghasilkan format militer H", $tw === 'TW 1018 0930 H');

// Uji hitungHariKe standar & reopen
$hariStandard = SarCalculationService::hitungHariKe('2026-10-15 08:00:00', '2026-10-18 10:00:00');
assertCondition("hitungHariKe standar (15 Okt s/d 18 Okt) menghasilkan H.4", $hariStandard === 'H.4');

$opReopen = [
    'waktu_berangkat' => '2026-10-01 08:00:00',
    'waktu_selesai' => '2026-10-07 18:00:00',
    'waktu_reopen' => '2026-10-10 08:00:00',
    'hari_sebelum_reopen' => 7,
    'status' => 'OPEN'
];
$hariReopen = SarCalculationService::hitungHariKe($opReopen, '2026-10-12 09:00:00');
assertCondition("hitungHariKe pada operasi reopen menghasilkan format H7 + 3", $hariReopen === 'H7 + 3');

// Uji hitungResponseTime
$rtQuick = SarCalculationService::hitungResponseTime('2026-10-18 08:00:00', '2026-10-18 08:12:00');
assertCondition("hitungResponseTime 12 menit terdeteksi quick response", $rtQuick['is_quick'] === true && $rtQuick['minutes'] === 12);

// Uji hitungPelaksanaanOperasi
$pelaksanaan = SarCalculationService::hitungPelaksanaanOperasi(['status' => 'OPEN', 'waktu_berangkat' => '2026-10-15 08:00:00'], '2026-10-18 12:00:00');
assertCondition("hitungPelaksanaanOperasi OPEN terhitung benar", $pelaksanaan['status'] === 'OPEN' && $pelaksanaan['total_hari'] === 4);

// Uji hitungStatistikKorban
$korbanList = [
    ['status_korban' => 'S'],
    ['status_korban' => 'MD'],
    ['status_korban' => 'DP'],
];
$stats = SarCalculationService::hitungStatistikKorban($korbanList, 5);
assertCondition("hitungStatistikKorban menghitung S, MD, DP, dan POB unrecorded", $stats['total'] === 5 && $stats['s'] === 1 && $stats['md'] === 1 && $stats['dp'] === 3);

// 3. Pengujian DocumentNumberService
echo "\n3. Menguji Fungsionalitas DocumentNumberService:\n";
assertCondition("Class DocumentNumberService dapat di-autoload", class_exists('DocumentNumberService'));

$mockOps = ['id_operasi' => 12, 'waktu_lapor' => '2026-10-18 08:00:00', 'nama_operasi' => 'KMM Nelayan Hilang'];
$mockRenops = ['waktu_briefing' => '2026-10-18 06:00:00', 'hari_ke' => 'H.2'];
$noRenops = DocumentNumberService::generateNomorRenops($mockOps, $mockRenops, 'SAR-304');
assertCondition("generateNomorRenops menghasilkan format RENOPS-181026-H2/OPS-SAR/SAR-304/1026", str_contains($noRenops, 'RENOPS-181026-H2/OPS-SAR/SAR-304/1026'));

// Dokumen cetak Laporan Awal, LAPBANG & Penutupan dihapus: hanya nomor RENOPS yang tersisa.
assertCondition("Nomor dokumen lama (LA/SITREP/BA) tidak ada lagi",
    !method_exists('DocumentNumberService', 'generateNomorLaporanAwal') && !method_exists('DocumentNumberService', 'generateNomorSitrep')
    && !method_exists('DocumentNumberService', 'generateNomorBeritaAcara'));

// 4. Pengujian WhatsAppTemplateService
echo "\n4. Menguji Fungsionalitas WhatsAppTemplateService:\n";
assertCondition("Class WhatsAppTemplateService dapat di-autoload", class_exists('WhatsAppTemplateService'));

$hdrWA = WhatsAppTemplateService::getHeaderWA('LAPORAN AWAL OPS SAR', 'Ops SAR Nelayan Wawonii', '2026-10-18 08:00:00');
assertCondition("getHeaderWA memuat FILE AKTIF dan moto Basarnas", str_contains($hdrWA, 'FILE AKTIF') && str_contains($hdrWA, 'SEMANGAT QUICK ACTION SEARCH AND RESCUE'));

$ftrWA = WhatsAppTemplateService::getFooterWA();
assertCondition("getFooterWA memuat kontak emergency call 115", str_contains($ftrWA, 'Emergency Call : 115'));

$cleanKoor = WhatsAppTemplateService::cleanSruCoordinates("A: 03° 58' S - 122° 35' E\nPola pencarian: Parallel Track");
assertCondition("cleanSruCoordinates membersihkan baris pola metadata", !str_contains($cleanKoor, 'Pola pencarian') && str_contains($cleanKoor, '03° 58\''));

$laWA = WhatsAppTemplateService::generateLaporanAwalWA($mockOps, $korbanList);
assertCondition("generateLaporanAwalWA memuat seluruh seksi laporan awal (1 s/d 9)", str_contains($laWA, '1. Sumber Info kejadian') && str_contains($laWA, '9. Kondisi Cuaca'));

// 5. Pengujian GeoHelper
echo "\n5. Menguji Fungsionalitas GeoHelper:\n";
assertCondition("Class GeoHelper dapat di-autoload", class_exists('GeoHelper'));

$dmsLat = GeoHelper::formatDMS(-4.037664, true);
assertCondition("GeoHelper::formatDMS memformat lintang Selatan dengan simbol derajat dan detik", str_contains($dmsLat, '04°') && str_contains($dmsLat, 'S'));

$dmsPair = GeoHelper::formatDmsPair(-4.037664, 122.486662);
assertCondition("GeoHelper::formatDmsPair menghasilkan pasangan koordinat", str_contains($dmsPair, 'S -') && str_contains($dmsPair, 'E'));

$dist = GeoHelper::calculateDistanceHaversine(-4.037664, 122.486662, -3.975000, 122.586600);
assertCondition("GeoHelper::calculateDistanceHaversine menghasilkan array km, nm, bearing, dan headingName", isset($dist['km'], $dist['nm'], $dist['bearing'], $dist['headingName']) && $dist['km'] > 0);

$polyPoints = [
    ['lat' => -4.0, 'lng' => 122.4],
    ['lat' => -4.0, 'lng' => 122.6],
    ['lat' => -4.2, 'lng' => 122.6],
    ['lat' => -4.2, 'lng' => 122.4]
];
$areaNm2 = GeoHelper::calculatePolygonAreaNm2($polyPoints);
assertCondition("GeoHelper::calculatePolygonAreaNm2 menghitung luas area poligon dalam Nm²", is_float($areaNm2) && $areaNm2 > 10.0);

$parsedCoord = GeoHelper::parseCoordinatesString('04° 02\' 16" S - 122° 29\' 12" E');
assertCondition("GeoHelper::parseCoordinatesString mengenali koordinat DMS", $parsedCoord !== null && $parsedCoord['lat'] < 0 && $parsedCoord['lng'] > 0);

// 6. Pengujian Ekuivalensi Mutlak Facade TemplateHelper & Delegasi GisModel
echo "\n6. Menguji Ekuivalensi Facade TemplateHelper & GisModel:\n";
assertCondition("TemplateHelper::hitungHariKe identik dengan SarCalculationService::hitungHariKe",
    TemplateHelper::hitungHariKe('2026-10-15 08:00:00', '2026-10-18 10:00:00') === SarCalculationService::hitungHariKe('2026-10-15 08:00:00', '2026-10-18 10:00:00')
);

assertCondition("TemplateHelper::generateNomorRenops identik dengan DocumentNumberService::generateNomorRenops",
    TemplateHelper::generateNomorRenops($mockOps, $mockRenops, 'SAR-304') === DocumentNumberService::generateNomorRenops($mockOps, $mockRenops, 'SAR-304')
);

assertCondition("TemplateHelper::generateLaporanAwalWA identik dengan WhatsAppTemplateService::generateLaporanAwalWA",
    TemplateHelper::generateLaporanAwalWA($mockOps, $korbanList) === WhatsAppTemplateService::generateLaporanAwalWA($mockOps, $korbanList)
);

assertCondition("TemplateHelper::formatDMS identik dengan GeoHelper::formatDMS",
    TemplateHelper::formatDMS(-4.037664, true) === GeoHelper::formatDMS(-4.037664, true)
);

assertCondition("GisModel::calculateDistanceHaversine mendelegasikan ke GeoHelper::calculateDistanceHaversine",
    GisModel::calculateDistanceHaversine(-4.0, 122.4, -4.1, 122.5) === GeoHelper::calculateDistanceHaversine(-4.0, 122.4, -4.1, 122.5)
);

assertCondition("GisModel::calculatePolygonAreaNm2 mendelegasikan ke GeoHelper::calculatePolygonAreaNm2",
    GisModel::calculatePolygonAreaNm2($polyPoints) === GeoHelper::calculatePolygonAreaNm2($polyPoints)
);

echo "\n--- HASIL: {$passedAssertions} / {$totalAssertions} Pengujian Berhasil Lulus ---\n";
if ($passedAssertions === $totalAssertions) {
    echo "KESIMPULAN: Seluruh pengujian Fase 6 berhasil lulus 100%!\n";
    exit(0);
} else {
    echo "KESIMPULAN: Terdapat pengujian Fase 6 yang gagal.\n";
    exit(1);
}
