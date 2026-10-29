<?php
/**
 * Test Suite: Audit Menyeluruh Pasca-Optimalisasi 3 Modul Utama
 * Modul 1: Laporan Awal Operasi SAR
 * Modul 2: Rencana Operasi (RENOPS) & Laporan Perkembangan (LAPBANG/SITREP & Penutupan)
 * Modul 3: Berita Radio SAR (BRA SAR: Mapel & Operasi SAR)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once dirname(__DIR__) . '/config/app.php';

$passed = 0;
$failed = 0;
$warnings = [];

function assertAudit(string $desc, bool $condition, string $detail = ''): void {
    global $passed, $failed, $warnings;
    if ($condition) {
        $passed++;
        echo "  [PASS] {$desc}\n";
    } else {
        $failed++;
        $warnings[] = "{$desc} -> {$detail}";
        echo "  [FAIL] {$desc} -> {$detail}\n";
    }
}

echo "====================================================================\n";
echo " AUDIT FORENSIK INTEGRASI AKHIR 3 MODUL UTAMA QR-SAR KPP KENDARI\n";
echo "====================================================================\n\n";

$db = getDB();

// ---------------------------------------------------------------------
// 1. AUDIT MODUL 1: LAPORAN AWAL OPERASI SAR
// ---------------------------------------------------------------------
echo "--- 1. AUDIT MODUL 1: LAPORAN AWAL OPERASI SAR ---\n";

// A. Uji Persistensi & Snapshot Relasional
$testOpData = [
    'nama_operasi' => 'KMM 1 ORANG TENGGELAM DI TELUK KENDARI TTK',
    'jenis_kejadian' => 'KMM',
    'lokasi_kejadian' => 'Teluk Kendari, Kendari Barat, Kota Kendari',
    'lintang' => "03° 58' 30\" S",
    'bujur' => "122° 35' 15\" E",
    'koordinat' => "03° 58' 30\" S - 122° 35' 15\" E",
    'waktu_kejadian' => '2026-10-18 07:30:00',
    'waktu_lapor' => '2026-10-18 08:00:00',
    'waktu_berangkat' => '2026-10-18 08:15:00',
    'nama_pelapor' => 'H. JUMARDI',
    'hubungan_pelapor' => 'Keluarga Korban',
    'no_telp_pelapor' => '081234567890',
    'status' => 'OPEN',
    'jarak' => '12.5 NM',
    'radial' => '045°',
    'jumlah_pob' => 2,
    'korban' => [
        ['nama_korban' => 'KORBAN SATU', 'umur' => '30', 'jk' => 'L', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
        ['nama_korban' => 'KORBAN DUA', 'umur' => '25', 'jk' => 'P', 'alamat' => 'Kendari', 'status_korban' => 'DP']
    ],
    'detail_unsur' => [
        ['nama_unsur' => 'SMC', 'jumlah_personil' => 1],
        ['nama_unsur' => 'Staf', 'jumlah_personil' => 1],
        ['nama_unsur' => 'Rescuer KPP Kendari', 'jumlah_personil' => 6]
    ],
    'detail_alut' => [
        ['nama_alut' => 'Palsar Medis', 'jumlah_alut' => 1, 'satuan' => 'Set'],
        ['nama_alut' => 'Palsar Komunikasi', 'jumlah_alut' => 1, 'satuan' => 'Set'],
        ['nama_alut' => 'Palsar Evakuasi', 'jumlah_alut' => 1, 'satuan' => 'Set'],
        ['nama_alut' => 'Rubber Boat 30 PK', 'jumlah_alut' => 1, 'satuan' => 'Unit']
    ]
];

$opId = OperasiModel::create($testOpData);
assertAudit("Laporan Awal berhasil diterbitkan dengan ID Operasi #{$opId}", $opId > 0);

$fetchedOp = OperasiModel::getById($opId);
assertAudit("Data relasi Korban lengkap (2 orang)", count($fetchedOp['korban'] ?? []) === 2);
assertAudit("Data relasi DetailUnsur lengkap (3 unsur)", count($fetchedOp['detail_unsur'] ?? []) === 3);
assertAudit("Data relasi DetailAlut lengkap (4 alut)", count($fetchedOp['detail_alut'] ?? []) === 4);
assertAudit("SSOT Unsur Terlibat terformat bullet list", str_contains($fetchedOp['unsur_terlibat'] ?? '', '• SMC'));
assertAudit("SSOT Alut/Palsar terformat bullet list", str_contains($fetchedOp['alut_palsar'] ?? '', '• 1 Set Palsar Medis'));

// B. Uji Render Lembar Cetak Laporan Awal (No PHP Notice/Warning/Error)
$_GET['id'] = $opId;
ob_start();
try {
    include dirname(__DIR__) . '/views/operasi/laporan_awal_print.php';
    $printLaporanAwalHtml = ob_get_clean();
    $laporanAwalRenderOk = str_contains($printLaporanAwalHtml, 'LAPORAN AWAL OPERASI SAR') && str_contains($printLaporanAwalHtml, 'KORBAN SATU');
} catch (Throwable $e) {
    ob_end_clean();
    $printLaporanAwalHtml = '';
    $laporanAwalRenderOk = false;
}
assertAudit("Lembar Cetak Laporan Awal A4 berhasil di-render tanpa error", $laporanAwalRenderOk);


// ---------------------------------------------------------------------
// 2. AUDIT MODUL 2: RENCANA OPERASI (RENOPS) H.1 & H.2
// ---------------------------------------------------------------------
echo "\n--- 2. AUDIT MODUL 2: RENCANA OPERASI (RENOPS) ---\n";

// A. RENOPS Hari ke-1 (H.1)
$renops1Data = [
    'id_operasi' => $opId,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => '2026-10-18',
    'waktu_briefing' => '2026-10-18 08:30:00',
    'instruksi_koordinasi' => 'SRU 1 melakukan penyisiran permukaan laut di sektor timur LKP.',
    'srus' => [
        [
            'nama_sru' => 'SRU 1 (Rubber Boat)',
            'koordinat_area' => "03° 58' 00\" S - 122° 35' 00\" E",
            'luas_area' => '5 Nm²',
            'pola_pencarian' => 'Parallel Track Search'
        ]
    ]
];

$renops1Id = RenopsModel::create($renops1Data);
assertAudit("RENOPS H.1 berhasil diterbitkan dengan ID #{$renops1Id}", $renops1Id > 0);

$fetchedRenops1 = RenopsModel::getById($renops1Id);
assertAudit(
    "Nomor RENOPS terformat baku resmi Basarnas: {$fetchedRenops1['nomor_renops']}",
    str_starts_with($fetchedRenops1['nomor_renops'] ?? '', 'RENOPS-') && str_contains($fetchedRenops1['nomor_renops'] ?? '', '-H1/')
);
assertAudit("Snapshot unsur RENOPS H.1 otomatis mewarisi unsur operasi", !empty($fetchedRenops1['unsur_text']));
assertAudit("Snapshot alut RENOPS H.1 otomatis mewarisi alut operasi", !empty($fetchedRenops1['alut_text']));

// B. Uji Render Lembar Cetak RENOPS
$_GET['id'] = $opId;
$_GET['id_renops'] = $renops1Id;
ob_start();
try {
    include dirname(__DIR__) . '/views/operasi/renops_print.php';
    $printRenopsHtml = ob_get_clean();
    $renopsRenderOk = str_contains($printRenopsHtml, 'RENCANA OPERASI PENCARIAN DAN PERTOLONGAN (RENOPS)') && str_contains($printRenopsHtml, 'SRU 1');
} catch (Throwable $e) {
    ob_end_clean();
    $printRenopsHtml = '';
    $renopsRenderOk = false;
}
assertAudit("Lembar Cetak RENOPS A4 berhasil di-render tanpa error", $renopsRenderOk);


// ---------------------------------------------------------------------
// 3. AUDIT MODUL 2: LAPBANG / SITREP & PEMBARUAN KORBAN
// ---------------------------------------------------------------------
echo "\n--- 3. AUDIT MODUL 2: SITREP H.1 & PERKEMBANGAN KORBAN ---\n";

// A. SITREP Hari ke-1 (1 Korban Ditemukan Selamat, 1 Masih DP)
$korbanId1 = (int)$fetchedOp['korban'][0]['id_korban'];
$korbanId2 = (int)$fetchedOp['korban'][1]['id_korban'];

$sitrep1Data = [
    'id_operasi' => $opId,
    'hari_ke' => 'H.1',
    'jenis_laporan' => 'PERKEMBANGAN',
    'isi_laporan' => 'Tim SAR Gabungan menemukan 1 korban dalam keadaan SELAMAT pada koordinat 03° 59\' 10" S - 122° 36\' 00" E.',
    'waktu_debrief' => '2026-10-18 17:30:00',
    'waktu_laporan' => '2026-10-18 18:00:00',
    'update_korban' => [
        $korbanId1 => 'S' // Korban 1 SELAMAT, Korban 2 tetap DP
    ]
];

$sitrep1Id = LaporanModel::create($sitrep1Data);
assertAudit("SITREP H.1 berhasil diterbitkan dengan ID #{$sitrep1Id}", $sitrep1Id > 0);

$fetchedSitrep1 = LaporanModel::getById($sitrep1Id);
assertAudit(
    "Nomor SITREP terformat baku resmi: {$fetchedSitrep1['nomor_laporan']}",
    str_starts_with($fetchedSitrep1['nomor_laporan'] ?? '', 'SITREP-') && str_contains($fetchedSitrep1['nomor_laporan'] ?? '', '-H1/')
);

// Verifikasi status korban di basis data diperbarui sebelum draft WA digenerate
$checkKorban1 = $db->query("SELECT status_korban FROM Korban WHERE id_korban = {$korbanId1}")->fetchColumn();
$checkKorban2 = $db->query("SELECT status_korban FROM Korban WHERE id_korban = {$korbanId2}")->fetchColumn();
assertAudit("Status Korban #{$korbanId1} di database telah terbarui menjadi 'S' (Selamat)", $checkKorban1 === 'S');
assertAudit("Status Korban #{$korbanId2} di database tetap 'DP' (Dalam Pencarian)", $checkKorban2 === 'DP');
assertAudit("Teks WA SITREP memuat angka 'S : 1 ORANG'", str_contains($fetchedSitrep1['full_text_wa'] ?? '', 'S : 1 ORANG'));

// B. Uji Render Lembar Cetak SITREP
$_GET['id'] = $opId;
$_GET['id_laporan'] = $sitrep1Id;
ob_start();
try {
    include dirname(__DIR__) . '/views/operasi/sitrep_print.php';
    $printSitrepHtml = ob_get_clean();
    $sitrepRenderOk = str_contains($printSitrepHtml, 'LAPORAN PERKEMBANGAN (SITREP)') && str_contains($printSitrepHtml, 'KORBAN SATU');
} catch (Throwable $e) {
    ob_end_clean();
    $printSitrepHtml = '';
    $sitrepRenderOk = false;
}
assertAudit("Lembar Cetak SITREP A4 berhasil di-render tanpa error", $sitrepRenderOk);


// ---------------------------------------------------------------------
// 4. AUDIT MODUL 2: PENUTUPAN OPERASI & BERITA ACARA
// ---------------------------------------------------------------------
echo "\n--- 4. AUDIT MODUL 2: PENUTUPAN OPERASI & BERITA ACARA ---\n";

// A. SITREP Hari ke-2 jenis PENUTUPAN (Korban kedua ditemukan selamat)
$sitrep2Data = [
    'id_operasi' => $opId,
    'hari_ke' => 'H.2',
    'jenis_laporan' => 'PENUTUPAN',
    'isi_laporan' => 'Seluruh korban telah ditemukan dalam keadaan SELAMAT. Operasi SAR diusulkan ditutup.',
    'waktu_debrief' => '2026-10-19 11:00:00',
    'waktu_laporan' => '2026-10-19 11:30:00',
    'update_korban' => [
        $korbanId2 => 'S' // Korban 2 SELAMAT
    ]
];

$sitrep2Id = LaporanModel::create($sitrep2Data);
assertAudit("SITREP H.2 (Penutupan) berhasil diterbitkan dengan ID #{$sitrep2Id}", $sitrep2Id > 0);

// Eksekusi penutupan operasi
$closedOk = OperasiModel::closeOperation($opId, '2026-10-19 12:00:00');
assertAudit("Operasi SAR berhasil ditutup (Status: CLOSED)", $closedOk === true);

$closedOp = OperasiModel::getById($opId);
assertAudit("Status Operasi di database = 'CLOSED'", $closedOp['status'] === 'CLOSED');
assertAudit("Waktu selesai tercatat", !empty($closedOp['waktu_selesai']));

// B. Uji Idempotensi closeOperation & Editing Laporan saat CLOSED
$closeAgainOk = OperasiModel::closeOperation($opId, '2026-10-19 12:00:00');
assertAudit("closeOperation() bersifat idempoten saat operasi sudah CLOSED", $closeAgainOk === true);

$updateLapOk = LaporanModel::update($sitrep2Id, [
    'isi_laporan' => 'Koreksi isi laporan penutupan: seluruh korban selamat dan diserahkan ke keluarga.',
    'waktu_debrief' => '2026-10-19 11:15:00',
    'hari_ke' => 'H.2',
    'jenis_laporan' => 'PENUTUPAN'
]);
assertAudit("LaporanModel::update() mengizinkan penyuntingan laporan penutupan pada operasi CLOSED", $updateLapOk === true);

// C. Uji Render Lembar Cetak Berita Acara Penutupan
$_GET['id'] = $opId;
ob_start();
try {
    include dirname(__DIR__) . '/views/operasi/penutupan_print.php';
    $printPenutupanHtml = ob_get_clean();
    $penutupanRenderOk = str_contains($printPenutupanHtml, 'BERITA ACARA PENGUSULAN PENUTUPAN') && (str_contains($printPenutupanHtml, 'AMIRUDDIN A.S., S.SOS.') || str_contains($printPenutupanHtml, 'AMIRUDDIN A.S., S.Sos.'));
} catch (Throwable $e) {
    ob_end_clean();
    $printPenutupanHtml = '';
    $penutupanRenderOk = false;
}
assertAudit("Lembar Cetak Berita Acara Penutupan A4 berhasil di-render tanpa error", $penutupanRenderOk);


// ---------------------------------------------------------------------
// 5. AUDIT MODUL 3: BERITA RADIO SAR (BRA SAR) TERINTEGRASI OPERASI
// ---------------------------------------------------------------------
echo "\n--- 5. AUDIT MODUL 3: BERITA RADIO SAR (BRA SAR) ---\n";

// A. Pembuatan BRA Operasi SAR otomatis dari Laporan Awal
$tplLaporanAwal = BraHelper::buildSectionsFromLaporanAwal($closedOp);
assertAudit("buildSectionsFromLaporanAwal() menghasilkan seksi berita", !empty($tplLaporanAwal['sections']));

$braOpsData = [
    'kategori' => 'OPERASI_SAR',
    'id_operasi' => $opId,
    'nama_operasi' => $closedOp['nama_operasi'],
    'dari' => 'KANSAR KENDARI',
    'aksi' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    'info' => 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
    'pengirim' => 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
    'nama_smc' => 'AMIRUDDIN A.S., S.Sos.',
    'pangkat_smc' => 'PEMBINA (IV/a)',
    'jabatan_smc' => 'SMC',
    'twu' => '1018 0815 H',
    'twb' => '1018 0800 H',
    'perihal' => $tplLaporanAwal['perihal'],
    'sections' => $tplLaporanAwal['sections']
];

$braOpsId = BraModel::create($braOpsData);
assertAudit("BRA OPERASI SAR berhasil diterbitkan dengan ID #{$braOpsId}", $braOpsId > 0);

$savedBraOps = BraModel::getById($braOpsId);
assertAudit("Kategori BRA SAR baku = 'OPERASI_SAR'", $savedBraOps['kategori'] === 'OPERASI_SAR');
assertAudit("Perihal BRA berakhiran 'TTK'", str_ends_with($savedBraOps['perihal'] ?? '', 'TTK'));
assertAudit("Token QR Code digital terisi", !empty($savedBraOps['barcode_data']));

// B. Pembuatan BRA Mapel dari Operasi SAR
$tplMapel = BraHelper::buildSectionsFromMapel($closedOp);
assertAudit("buildSectionsFromMapel() menghasilkan tepat 3 seksi (AAA, BBB, CCC)", count($tplMapel['sections'] ?? []) === 3);

$braMapelData = [
    'kategori' => 'MAPEL',
    'id_operasi' => $opId,
    'nama_operasi' => $closedOp['nama_operasi'],
    'dari' => 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
    'aksi' => 'KA KSOP KENDARI KMA KA SROP KENDARI',
    'info' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
    'pengirim' => 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
    'nama_smc' => 'AMIRUDDIN A.S., S.Sos.',
    'pangkat_smc' => 'PEMBINA (IV/a)',
    'jabatan_smc' => 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
    'twu' => '1018 0815 H',
    'twb' => '1018 0800 H',
    'perihal' => $tplMapel['perihal'],
    'sections' => $tplMapel['sections']
];

$braMapelId = BraModel::create($braMapelData);
assertAudit("BRA MAPEL berhasil diterbitkan dengan ID #{$braMapelId}", $braMapelId > 0);

$savedBraMapel = BraModel::getById($braMapelId);
assertAudit("Kategori BRA MAPEL baku = 'MAPEL'", $savedBraMapel['kategori'] === 'MAPEL');
assertAudit("Jabatan SMC untuk MAPEL = 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI'", str_contains($savedBraMapel['jabatan_smc'], 'KEPALA KANTOR'));

// C. Uji Tautan BRA SAR di Operasi Detail
$updatedOp = OperasiModel::getById($opId);
assertAudit("Operasi SAR memuat 2 dokumen BRA tertaut", count($updatedOp['bra_sar'] ?? []) === 2);

// D. Uji Render Lembar Resmi BRA SAR A4 (views/bra/view.php)
$_GET['view'] = $braOpsId;
$bra = $savedBraOps;
ob_start();
try {
    include dirname(__DIR__) . '/views/bra/view.php';
    $printBraHtml = ob_get_clean();
    $braRenderOk = str_contains($printBraHtml, 'BADAN NASIONAL PENCARIAN DAN PERTOLONGAN') && str_contains($printBraHtml, 'BRA OPERASI SAR');
} catch (Throwable $e) {
    ob_end_clean();
    $printBraHtml = '';
    $braRenderOk = false;
}
assertAudit("Lembar Resmi Naskah Dinas BRA SAR A4 berhasil di-render tanpa error", $braRenderOk);


// ---------------------------------------------------------------------
// 6. CLEANUP UJI COBA
// ---------------------------------------------------------------------
echo "\n--- 6. DATA CLEANUP ---\n";
// Hapus BRA
BraModel::delete($braOpsId);
BraModel::delete($braMapelId);
// Hapus Operasi (Cascade hapus Renops, Sitrep, Korban, Unsur, Alut)
OperasiModel::delete($opId);

$opCheck = OperasiModel::getById($opId);
assertAudit("Data uji Laporan Awal, Renops, Sitrep, dan BRA SAR berhasil dibersihkan", $opCheck === null);

echo "\n====================================================================\n";
echo " HASIL AKHIR AUDIT: {$passed} BERHASIL (PASS), {$failed} GAGAL (FAIL)\n";
echo "====================================================================\n";

if ($failed === 0) {
    echo " KESIMPULAN: SELURUH HASIL OPTIMALISASI 3 MODUL BERJALAN DENGAN SEMPURNA!\n\n";
    exit(0);
} else {
    echo " KESIMPULAN: DITEMUKAN CATATAN KEKURANGAN:\n";
    foreach ($warnings as $w) {
        echo " - {$w}\n";
    }
    echo "\n";
    exit(1);
}
