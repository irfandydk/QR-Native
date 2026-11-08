<?php
require_once __DIR__ . '/bootstrap.php';
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

// --- 2. Syntax & Template File Existence ---
$printViews = [
    'views/operasi/partials/renops_smeac_doc.php' => [
        'RENCANA OPERASI SAR HARI KE-',
        'MISSION (TUGAS)',
        'qrCanvas'
    ],
    'views/operasi/renops_smeac_print.php' => [
        'renops_smeac_doc.php',
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
$ctlPhp = file_get_contents(__DIR__ . '/../controllers/OperasiController.php');
assertTest(strpos($ctlPhp, "print_penutupan") === false && strpos($ctlPhp, "print_sitrep") === false && strpos($ctlPhp, "print_laporan_awal") === false,
    "Route cetak Laporan Awal/LAPBANG/Penutupan sudah dihapus");

// --- 4. Tombol & Trigger di views/operasi/detail.php & Partials ---
$detailPhp = file_get_contents(__DIR__ . '/../views/operasi/detail.php');
foreach (glob(__DIR__ . '/../views/operasi/partials/*.php') as $partialFile) {
    $detailPhp .= "\n" . file_get_contents($partialFile);
}
assertTest(strpos($detailPhp, "operasi.php?action=print_renops") !== false, "detail.php memiliki link/aksi cetak RENOPS");
// Tab Cetak & kartu hanya untuk Rencana Operasi SAR & BRA SAR: cetak LAPBANG/Penutupan dihapus dari halaman detail.
assertTest(strpos($detailPhp, "operasi.php?action=print_sitrep") === false, "detail.php tidak lagi menampilkan cetak SITREP (tab Cetak: RENOPS & BRA saja)");
assertTest(strpos($detailPhp, "operasi.php?action=print_penutupan") === false, "detail.php tidak lagi menampilkan cetak Berita Acara Penutupan");
assertTest(strpos($detailPhp, "operasi.php?action=renops_dokumen&id_renops=") !== false, "detail.php memiliki tombol Edit Draf dokumen RENOPS per hari di Tab Berkas");
assertTest(strpos($detailPhp, "select-sitrep-cetak") === false, "Tab Berkas tanpa dropdown pemilih hari SITREP");

// --- 5. JS Presisi GIS & Reaktivitas Korban ---
$renopsJs = file_get_contents(__DIR__ . '/../assets/js/pages/renops_form.js');
assertTest(strpos($renopsJs, "formatDmsPair") !== false, "renops_form.js menyertakan fungsi formatDmsPair untuk format koordinat Basarnas");
assertTest(strpos($renopsJs, "track_spacing") !== false, "renops_form.js menyinkronkan track_spacing ke SRU form");

$sitrepJs = file_get_contents(__DIR__ . '/../assets/js/pages/sitrep_form.js');
assertTest(strpos($sitrepJs, "update_korban") !== false, "sitrep_form.js memproses selector update_korban");
assertTest(strpos($sitrepJs, "victim-status-badges") !== false, "sitrep_form.js memutakhirkan widget #victim-status-badges");

$detailJs = file_get_contents(__DIR__ . '/../assets/js/pages/operasi_detail.js');
assertTest(strpos($detailJs, "printBerkas") === false && strpos($detailJs, "print_penutupan") === false, "operasi_detail.js tanpa fungsi cetak mati (printBerkas)");

echo "\n========================================================\n";
echo "HASIL AKHIR: $passed / $total Test Berhasil\n";
echo "========================================================\n";

if ($passed === $total) {
    exit(0);
} else {
    exit(1);
}
