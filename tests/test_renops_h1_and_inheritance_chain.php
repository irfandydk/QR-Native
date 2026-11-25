<?php
/**
 * Test: Perbaikan Crash Base Map >63 Titik (jsonSafe + OperasiController),
 * Aturan RENOPS H.1 (tanpa waktu_briefing + auto waktu_berangkat),
 * dan Rantai Pewarisan Kronologis Laporan Awal -> RENOPS H.1 -> LAPBANG H.1 -> RENOPS H.2 -> LAPBANG H.2 -> RENOPS H.3.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$total = 0;

function assertCheck(bool $cond, string $msg): void {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] {$msg}\n";
    } else {
        echo " [FAIL] {$msg}\n";
    }
}

echo "=== TEST RENOPS H.1, BASE MAP UTF-8 CRASH & INHERITANCE CHAIN ===\n\n";

$db = getDB();

// 1. Migrasi kolom waktu_briefing nullable
$colInfo = $db->query("SHOW COLUMNS FROM Renops LIKE 'waktu_briefing'")->fetch(PDO::FETCH_ASSOC);
assertCheck($colInfo && strtoupper((string)($colInfo['Null'] ?? 'NO')) === 'YES', 'Kolom Renops.waktu_briefing mengizinkan NULL');

// 2. jsonSafe tidak crash pada byte UTF-8 rusak (mis. chr(128))
$badStr = "Titik \x80 rusak";
$safeJson = jsonSafe(['text' => $badStr]);
assertCheck(is_string($safeJson) && $safeJson !== '' && json_decode($safeJson, true) !== null, 'jsonSafe() menangani byte non-UTF-8 secara aman tanpa TypeError');

// 3. Uji helper RenopsModel::isHariPertama
assertCheck(RenopsModel::isHariPertama('H.1') === true, 'isHariPertama(H.1) = true');
assertCheck(RenopsModel::isHariPertama('H1') === true, 'isHariPertama(H1) = true');
assertCheck(RenopsModel::isHariPertama('1') === true, 'isHariPertama(1) = true');
assertCheck(RenopsModel::isHariPertama('H.2') === false, 'isHariPertama(H.2) = false');
assertCheck(RenopsModel::isHariPertama('H7 + 1') === false, 'isHariPertama(H7 + 1) = false (hari perpanjangan tetap memiliki briefing)');

$idOps = 0;
$basePolyId = 'poly-test-basemap-186-' . time();
$opPolyId = 'poly-test-op-80-' . time();

try {
    $today = date('Y-m-d');
    $day2 = date('Y-m-d', strtotime('+1 day'));
    $day3 = date('Y-m-d', strtotime('+2 days'));
    $waktuBerangkat = "{$today} 14:25:00";

    $idOps = OperasiModel::create([
        'nama_operasi' => 'Operasi SAR Uji Pewarisan H1 H2 H3',
        'nama_pelapor' => 'Pelapor Uji',
        'lokasi_kejadian' => 'Perairan Teluk Kendari',
        'koordinat' => '03° 58\' 30.0" S - 122° 35\' 12.0" E',
        'waktu_kejadian' => "{$today} 13:00:00",
        'waktu_lapor' => "{$today} 13:30:00",
        'waktu_berangkat' => $waktuBerangkat,
        'cuaca' => 'Cerah',
        'gelombang' => '0,3 Mtr',
        'angin' => '5 km/jam Timur',
        'detail_unsur' => [
            ['nama_unsur' => 'Rescuer KPP Kendari', 'jumlah_personil' => 6],
        ],
        'detail_alut' => [
            ['nama_alut' => 'RIB 01', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
        ],
    ]);
    assertCheck($idOps > 0, "Fixture Operasi dibuat (ID={$idOps})");

    // Tambahkan Base Map polygon 186 titik (seperti di homeserver) + Operation polygon 80 titik (tanpa label)
    $pts186 = [];
    for ($i = 0; $i < 186; $i++) {
        $pts186[] = ['lat' => -4.0 + ($i * 0.0001), 'lng' => 122.5 + ($i * 0.0001)];
    }
    $pts80 = [];
    for ($i = 0; $i < 80; $i++) {
        $pts80[] = ['lat' => -3.9 + ($i * 0.0001), 'lng' => 122.6 + ($i * 0.0001)];
    }
    GisModel::savePolygon(['id' => $basePolyId, 'id_operasi' => null, 'name' => 'Biak BaseMap', 'points' => $pts186]);
    GisModel::savePolygon(['id' => $opPolyId, 'id_operasi' => $idOps, 'name' => 'Sektor Operasi 1', 'points' => $pts80]);

    // 4. Render penuh OperasiController::renopsForm() untuk memastikan TIDAK crash & menyaring Base Map
    $admin = $db->query("SELECT * FROM Users WHERE role = 'ADMIN' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($admin) {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = "/renops_form.php?id_operasi={$idOps}";
        $_GET = ['id_operasi' => (string)$idOps];
        AuthHelper::setSession([
            'id' => $admin['id'],
            'username' => $admin['username'],
            'nama' => $admin['nama'],
            'role' => $admin['role'],
        ]);
        ob_start();
        (new OperasiController())->renopsForm();
        $html = (string)ob_get_clean();

        assertCheck(str_contains($html, 'window.__RENOPS_PAGE_CONFIG__ = {'), 'renops_form.php merender window.__RENOPS_PAGE_CONFIG__ secara utuh');
        assertCheck(str_contains($html, 'assets/js/pages/renops_form.js'), 'renops_form.php merender tag script pages/renops_form.js sampai selesai');
        assertCheck(str_contains($html, 'Rescuer KPP Kendari') && str_contains($html, 'RIB 01'), 'renops_form.php memuat Unsur & Alut warisan dari Laporan Awal');
        assertCheck(str_contains($html, 'id="field-renops-waktu-briefing" hidden'), 'renops_form.php menyembunyikan field Waktu Briefing pada H.1');
        assertCheck(str_contains($html, 'value="' . $today . 'T14:25"'), 'renops_form.php otomatis mengisi Waktu Mulai Pergerakan dari waktu_berangkat Laporan Awal');
        assertCheck(str_contains($html, 'SRU 1 - Sektor Operasi 1') && !str_contains($html, 'SRU 1 - Biak BaseMap'), 'Auto-SRU hanya memakai poligon milik operasi dan mengecualikan poligon Base Map');
        assertCheck(str_contains($html, 'Titik L3:'), 'Poligon 80 titik menghasilkan label UTF-8 aman (A..Z, A2..Z2, A3..L3)');
    } else {
        assertCheck(false, 'Akun admin tidak ditemukan untuk pengujian render renopsForm');
    }

    // 5. Langkah 1 Rantai: Laporan Awal -> RENOPS H.1
    $ops0 = OperasiModel::getById($idOps);
    $defRen1 = HierarchyHelper::getNextRenopsDefaults($ops0);
    assertCheck($defRen1['hari_ke'] === 'H.1' && $defRen1['is_hari_pertama'] === true, 'Step 1: Next RENOPS adalah H.1');
    assertCheck($defRen1['default_waktu_briefing'] === null, 'Step 1: Default waktu_briefing H.1 adalah null');
    assertCheck($defRen1['default_waktu_mulai_ops'] === "{$today}T14:25", 'Step 1: Default waktu_mulai_ops H.1 mengikuti waktu_berangkat Laporan Awal');
    assertCheck(count($defRen1['inherited_unsur']) === 1 && count($defRen1['inherited_alut']) === 1 && $defRen1['inherited_cuaca'] === 'Cerah', 'Step 1: RENOPS H.1 mewarisi Unsur, Alut, dan Cuaca dari Laporan Awal');

    $idRen1 = RenopsModel::create([
        'id_operasi' => $idOps,
        'hari_ke' => 'H.1',
        'tanggal_laporan' => $today,
        'waktu_briefing' => "{$today} 07:00:00", // tetap diabaikan khusus H.1
        'waktu_mulai_ops' => '', // otomatis fallback ke waktu_berangkat Laporan Awal
        'cuaca' => 'Berawan',
        'gelombang' => '0,6 Mtr',
        'angin' => '8 km/jam Tenggara',
        'detail_unsur' => [
            ['nama_unsur' => 'Rescuer KPP Kendari', 'jumlah_personil' => 6],
            ['nama_unsur' => 'Polairud', 'jumlah_personil' => 4],
        ],
        'detail_alut' => [
            ['nama_alut' => 'RIB 01', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Rubber Boat', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
        ],
        'srus' => [
            ['nama_sru' => 'SRU 1', 'koordinat_area' => 'Sektor 1', 'luas_area' => '5 NM²', 'pola_pencarian' => 'Parallel Track Search'],
        ],
    ]);
    $ren1 = RenopsModel::getById($idRen1);
    assertCheck($ren1 && $ren1['waktu_briefing'] === null, 'RENOPS H.1 tersimpan di DB dengan waktu_briefing = NULL');
    assertCheck($ren1 && $ren1['waktu_mulai_ops'] === $waktuBerangkat, 'RENOPS H.1 tersimpan dengan waktu_mulai_ops = waktu_berangkat Laporan Awal');
    assertCheck($ren1 && stripos((string)$ren1['full_text_wa'], 'Brifing Operasi') === false, 'Naskah WA RENOPS H.1 tidak memuat baris Briefing Operasi');
    assertCheck($ren1 && str_contains((string)$ren1['full_text_wa'], 'Melaksanakan Operasi Pencarian H.1'), 'Naskah WA RENOPS H.1 memuat redaksi pelaksanaan H.1');

    // 6. Langkah 2 Rantai: RENOPS H.1 -> LAPBANG H.1
    $ops1 = OperasiModel::getById($idOps);
    $defLap1 = HierarchyHelper::getNextLapbangDefaults($ops1);
    assertCheck($defLap1['hari_ke'] === 'H.1' && str_contains($defLap1['predecessor_label'], 'Renops H.1'), 'Step 2: LAPBANG H.1 mewarisi dari RENOPS H.1');
    assertCheck(count($defLap1['inherited_unsur']) === 2 && count($defLap1['inherited_alut']) === 2 && $defLap1['inherited_cuaca'] === 'Berawan' && $defLap1['inherited_gelombang'] === '0,6 Mtr' && $defLap1['inherited_angin'] === '8 km/jam Tenggara', 'Step 2: Unsur/Alut/Cuaca/Gelombang/Angin LAPBANG H.1 tepat dari RENOPS H.1');

    $idLap1 = LaporanModel::create([
        'id_operasi' => $idOps,
        'hari_ke' => 'H.1',
        'jenis_laporan' => 'PERKEMBANGAN',
        'waktu_laporan' => "{$today} 18:00:00",
        'isi_laporan' => 'Pencarian H.1 nihil.',
        'cuaca' => 'Hujan Ringan',
        'gelombang' => '1,0 Mtr',
        'angin' => '10 km/jam Barat',
        'detail_unsur' => [
            ['nama_unsur' => 'Rescuer KPP Kendari', 'jumlah_personil' => 6],
            ['nama_unsur' => 'Polairud', 'jumlah_personil' => 4],
            ['nama_unsur' => 'BPBD', 'jumlah_personil' => 3],
        ],
        'detail_alut' => [
            ['nama_alut' => 'RIB 01', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Rubber Boat', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Drone Thermal', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_UDARA'],
        ],
    ]);
    assertCheck($idLap1 > 0, "LAPBANG H.1 dibuat (ID={$idLap1})");

    // 7. Langkah 3 Rantai: LAPBANG H.1 -> RENOPS H.2
    $ops2 = OperasiModel::getById($idOps);
    $defRen2 = HierarchyHelper::getNextRenopsDefaults($ops2);
    assertCheck($defRen2['hari_ke'] === 'H.2' && $defRen2['is_hari_pertama'] === false, 'Step 3: Next RENOPS adalah H.2');
    assertCheck($defRen2['default_waktu_briefing'] === "{$day2}T07:00" && $defRen2['default_waktu_mulai_ops'] === "{$day2}T07:30", 'Step 3: RENOPS H.2 tetap memiliki default briefing 07:00 & mulai 07:30');
    assertCheck(str_contains($defRen2['predecessor_label'], 'Lapbang H.1') && count($defRen2['inherited_unsur']) === 3 && count($defRen2['inherited_alut']) === 3 && $defRen2['inherited_cuaca'] === 'Hujan Ringan', 'Step 3: RENOPS H.2 mewarisi penuh dari LAPBANG H.1');

    $idRen2 = RenopsModel::create([
        'id_operasi' => $idOps,
        'hari_ke' => 'H.2',
        'tanggal_laporan' => $day2,
        'waktu_briefing' => "{$day2} 07:00:00",
        'waktu_mulai_ops' => "{$day2} 07:30:00",
        'cuaca' => 'Mendung',
        'gelombang' => '1,2 Mtr',
        'angin' => '12 km/jam Selatan',
        'detail_unsur' => [
            ['nama_unsur' => 'Rescuer KPP Kendari', 'jumlah_personil' => 6],
            ['nama_unsur' => 'Polairud', 'jumlah_personil' => 4],
            ['nama_unsur' => 'BPBD', 'jumlah_personil' => 3],
            ['nama_unsur' => 'TNI AL', 'jumlah_personil' => 5],
        ],
        'detail_alut' => [
            ['nama_alut' => 'RIB 01', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Rubber Boat', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Drone Thermal', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_UDARA'],
            ['nama_alut' => 'KN SAR Pacitan', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
        ],
        'srus' => [
            ['nama_sru' => 'SRU 1', 'koordinat_area' => 'Sektor 2', 'luas_area' => '10 NM²', 'pola_pencarian' => 'Creeping Line Search'],
        ],
    ]);
    $ren2 = RenopsModel::getById($idRen2);
    assertCheck($ren2 && $ren2['waktu_briefing'] === "{$day2} 07:00:00", 'RENOPS H.2 menyimpan waktu_briefing dengan benar');
    assertCheck($ren2 && str_contains((string)$ren2['full_text_wa'], 'Melaksanakan Brifing Operasi'), 'Naskah WA RENOPS H.2 tetap memuat baris Briefing Operasi');

    // 8. Langkah 4 Rantai: RENOPS H.2 -> LAPBANG H.2, plus uji bila LAPBANG H.2 dilewati
    $ops3 = OperasiModel::getById($idOps);
    $defLap2 = HierarchyHelper::getNextLapbangDefaults($ops3);
    assertCheck($defLap2['hari_ke'] === 'H.2' && str_contains($defLap2['predecessor_label'], 'Renops H.2') && count($defLap2['inherited_unsur']) === 4 && $defLap2['inherited_cuaca'] === 'Mendung', 'Step 4: LAPBANG H.2 mewarisi dari RENOPS H.2 (4 unsur, Mendung)');

    $defRen3SkipLap2 = HierarchyHelper::getNextRenopsDefaults($ops3);
    assertCheck($defRen3SkipLap2['hari_ke'] === 'H.3' && str_contains($defRen3SkipLap2['predecessor_label'], 'Renops H.2') && count($defRen3SkipLap2['inherited_unsur']) === 4, 'Edge Case: RENOPS H.3 tanpa LAPBANG H.2 tetap mewarisi RENOPS H.2 (4 unsur), tidak mundur ke LAPBANG H.1');

    // 9. Langkah 5 Rantai: LAPBANG H.2 -> RENOPS H.3
    $idLap2 = LaporanModel::create([
        'id_operasi' => $idOps,
        'hari_ke' => 'H.2',
        'jenis_laporan' => 'PERKEMBANGAN',
        'waktu_laporan' => "{$day2} 18:00:00",
        'isi_laporan' => 'Pencarian H.2 nihil.',
        'cuaca' => 'Cerah Berawan',
        'gelombang' => '0,8 Mtr',
        'angin' => '6 km/jam Utara',
        'detail_unsur' => [
            ['nama_unsur' => 'Rescuer KPP Kendari', 'jumlah_personil' => 6],
            ['nama_unsur' => 'Polairud', 'jumlah_personil' => 4],
            ['nama_unsur' => 'BPBD', 'jumlah_personil' => 3],
            ['nama_unsur' => 'TNI AL', 'jumlah_personil' => 5],
            ['nama_unsur' => 'Nelayan Lokal', 'jumlah_personil' => 10],
        ],
        'detail_alut' => [
            ['nama_alut' => 'RIB 01', 'jumlah_alut' => 1, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Longboat Masyarakat', 'jumlah_alut' => 2, 'satuan' => 'Unit', 'kategori' => 'ALUT_LAUT'],
        ],
    ]);
    assertCheck($idLap2 > 0, "LAPBANG H.2 dibuat (ID={$idLap2})");

    $ops4 = OperasiModel::getById($idOps);
    $defRen3 = HierarchyHelper::getNextRenopsDefaults($ops4);
    assertCheck($defRen3['hari_ke'] === 'H.3' && str_contains($defRen3['predecessor_label'], 'Lapbang H.2') && count($defRen3['inherited_unsur']) === 5 && count($defRen3['inherited_alut']) === 2 && $defRen3['inherited_cuaca'] === 'Cerah Berawan', 'Step 5: RENOPS H.3 mewarisi dari LAPBANG H.2 (5 unsur, 2 alut, Cerah Berawan)');
} finally {
    GisModel::deletePolygon($basePolyId);
    GisModel::deletePolygon($opPolyId);
    if ($idOps > 0) {
        OperasiModel::delete($idOps);
    }
}

echo "\nTotal: {$total}, Lulus: {$passed}\n";
exit($passed === $total ? 0 : 1);
