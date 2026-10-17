<?php
/**
 * Test Suite Fase 2 & 3: Standardisasi Naskah Dinas Cetak Resmi Basarnas A4 & Presisi GIS/UI
 * Verifikasi:
 * 1. Helper Nomor Registrasi Dinas (Nomor RENOPS, SITREP, Berita Acara Penutupan)
 * 2. Integritas File Template Naskah Dinas Cetak (Kop, TTD, Tabel, QR Code)
 * 3. Routing Cetak di operasi.php
 * 4. UI Trigger (Tombol Cetak & Dropdown di views/operasi/detail.php)
 * 5. Reaktivitas & Selector di assets/js/pages/
 */

require_once __DIR__ . '/../helpers/TemplateHelper.php';

$passed = 0;
$total = 0;

function assertTest($condition, $message) {
    global $passed, $total;
    $total++;
    if ($condition) {
        $passed++;
        echo "[\033[32mPASS\033[0m] $message\n";
    } else {
        echo "[\033[31mFAIL\033[0m] $message\n";
    }
}

echo "========================================================\n";
echo "TEST SUITE FASE 2 & 3: RENOPS, SITREP, BERITA ACARA CETAK\n";
echo "========================================================\n\n";

// --- 1. Helper Nomor Registrasi Dinas ---
$sampleOps = [
    'id_operasi' => 12,
    'waktu_berangkat' => '2026-10-18 07:00:00',
    'waktu_selesai' => '2026-10-18 17:30:00'
];
$sampleRenops = [
    'hari_ke' => 'H1',
    'waktu_briefing' => '2026-10-18 06:30:00'
];
$sampleSitrep = [
    'hari_ke' => 'H2',
    'waktu_debrief' => '2026-10-19 18:00:00'
];

$nomorRenops = TemplateHelper::generateNomorRenops($sampleOps, $sampleRenops, 'SAR-KDI');
assertTest(
    strpos($nomorRenops, 'RENOPS-181026-H1/OPS-SAR/SAR-KDI/1026') !== false,
    "Nomor RENOPS Hari ke-1 terformat dengan benar: $nomorRenops"
);

$nomorSitrep = TemplateHelper::generateNomorSitrep($sampleOps, $sampleSitrep, 'SAR-KDI');
assertTest(
    strpos($nomorSitrep, 'SITREP-191026-H2/OPS-SAR/SAR-KDI/1026') !== false,
    "Nomor SITREP Hari ke-2 terformat dengan benar: $nomorSitrep"
);

$nomorBA = TemplateHelper::generateNomorBeritaAcara($sampleOps, $sampleSitrep, 'SAR-KDI');
assertTest(
    strpos($nomorBA, 'BA-TUTUP-191026-012/OPS-SAR/SAR-KDI/1026') !== false,
    "Nomor Berita Acara terformat dengan benar: $nomorBA"
);

// --- 2. Syntax & Template File Existence ---
$printViews = [
    'views/operasi/renops_print.php' => [
        'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI',
        'RENCANA OPERASI SAR (RENOPS)',
        'SAR Mission Coordinator (SMC)',
        'qrcode'
    ],
    'views/operasi/penutupan_print.php' => [
        'BERITA ACARA PENGUSULAN PENUTUPAN OPERASI PENCARIAN DAN PERTOLONGAN',
        'musyawarah mufakat bersama unsur Potensi SAR',
        'Perwakilan Keluarga Korban',
        'qrcode'
    ],
    'views/operasi/sitrep_print.php' => [
        'LAPORAN PERKEMBANGAN (SITREP) OPERASI SAR',
        'II. DATA KORBAN',
        'III. HASIL PELAKSANAAN OPERASI HARI INI',
        'qrcode'
    ]
];

foreach ($printViews as $filePath => $requiredStrings) {
    $fullPath = __DIR__ . '/../' . $filePath;
    assertTest(file_exists($fullPath), "File template cetak ada: $filePath");
    
    $content = file_get_contents($fullPath);
    foreach ($requiredStrings as $needle) {
        assertTest(stripos($content, $needle) !== false, "[$filePath] Memuat teks wajib: '$needle'");
    }
}

// --- 3. Routing di operasi.php ---
$operasiPhp = file_get_contents(__DIR__ . '/../operasi.php');
assertTest(strpos($operasiPhp, "print_renops") !== false, "Route 'print_renops' terdaftar di operasi.php");
assertTest(strpos($operasiPhp, "print_penutupan") !== false, "Route 'print_penutupan' terdaftar di operasi.php");
assertTest(strpos($operasiPhp, "print_sitrep") !== false, "Route 'print_sitrep' terdaftar di operasi.php");

// --- 4. Tombol & Trigger di views/operasi/detail.php ---
$detailPhp = file_get_contents(__DIR__ . '/../views/operasi/detail.php');
assertTest(strpos($detailPhp, "operasi.php?action=print_renops") !== false, "detail.php memiliki link/aksi cetak RENOPS");
assertTest(strpos($detailPhp, "operasi.php?action=print_sitrep") !== false, "detail.php memiliki link/aksi cetak SITREP");
assertTest(strpos($detailPhp, "operasi.php?action=print_penutupan") !== false, "detail.php memiliki link/aksi cetak Berita Acara Penutupan");
assertTest(strpos($detailPhp, "select-renops-cetak") !== false, "detail.php memiliki dropdown pemilih hari RENOPS di Tab Berkas");
assertTest(strpos($detailPhp, "select-sitrep-cetak") !== false, "detail.php memiliki dropdown pemilih hari SITREP di Tab Berkas");

// --- 5. JS Presisi GIS & Reaktivitas Korban ---
$renopsJs = file_get_contents(__DIR__ . '/../assets/js/pages/renops_form.js');
assertTest(strpos($renopsJs, "formatDmsPair") !== false, "renops_form.js menyertakan fungsi formatDmsPair untuk format koordinat Basarnas");
assertTest(strpos($renopsJs, "track_spacing") !== false, "renops_form.js menyinkronkan track_spacing ke SRU form");

$sitrepJs = file_get_contents(__DIR__ . '/../assets/js/pages/sitrep_form.js');
assertTest(strpos($sitrepJs, "update_korban") !== false, "sitrep_form.js memproses selector update_korban");
assertTest(strpos($sitrepJs, "victim-status-badges") !== false, "sitrep_form.js memutakhirkan widget #victim-status-badges");

$detailJs = file_get_contents(__DIR__ . '/../assets/js/pages/operasi_detail.js');
assertTest(strpos($detailJs, "print_renops") !== false, "operasi_detail.js mendukung cetak naskah dinas RENOPS");
assertTest(strpos($detailJs, "print_penutupan") !== false, "operasi_detail.js mendukung cetak naskah dinas Berita Acara");

echo "\n========================================================\n";
echo "HASIL AKHIR: $passed / $total Test Berhasil\n";
echo "========================================================\n";

if ($passed === $total) {
    exit(0);
} else {
    exit(1);
}
