<?php
/**
 * Test: keluaran memakai kategori alut — naskah WA (judul tetap "ALUT DAN PALSAR", isi urut
 * Darat → Laut → Udara → PALSAR) & tab Kekuatan (urut + label kategori).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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

echo "=== TEST KATEGORI ALUT DI KELUARAN ===\n\n";

$alut = [
    ['nama_alut' => 'Palsar Uji', 'satuan' => 'Set', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
    ['nama_alut' => 'Drone Uji', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'ALUT_UDARA'],
    ['nama_alut' => 'Mobil Uji', 'satuan' => 'Unit', 'jumlah_alut' => 2, 'kategori' => 'ALUT_DARAT'],
    ['nama_alut' => 'Speedboat Lama', 'satuan' => 'Unit', 'jumlah_alut' => 1], // data lama tanpa kategori (Review Focus 1)
];
$urut = "• 2 Unit Mobil Uji\n• 1 Unit Speedboat Lama\n• 1 Unit Drone Uji\n• 1 Set Palsar Uji";
$ops = ['nama_operasi' => 'Uji', 'waktu_kejadian' => '2026-11-01 08:00:00', 'waktu_lapor' => '2026-11-01 08:00:00', 'waktu_berangkat' => '2026-11-01 09:00:00'];
try {
    $wa = TemplateHelper::generateLaporanAwalWA($ops, [], [], $alut);
    assertCondition(stripos($wa, 'ALUT DAN PALSAR') !== false && str_contains($wa, $urut), 'WA Laporan Awal: judul gabungan, isi terurut per kategori');
    $wa = WhatsAppTemplateService::generateRenopsWA($ops, ['hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-01'], [], [], [], $alut);
    assertCondition(str_contains($wa, $urut), 'WA RENOPS: isi terurut per kategori');
    $wa = WhatsAppTemplateService::generateLapbangWA($ops, ['hari_ke' => 'H.1'], [], [], $alut);
    assertCondition(str_contains($wa, $urut), 'WA LAPBANG: isi terurut per kategori');
    assertCondition(str_contains(WhatsAppTemplateService::generateLapbangWA($ops, ['hari_ke' => 'H.1'], [], [], []), '• Belum ada data alut'), 'WA tanpa alut tetap "Belum ada data alut"');

    $operasi = ['detail_alut' => $alut, 'alut_palsar' => '', 'detail_unsur' => [], 'korban' => []];
    $activeTab = 'kekuatan';
    ob_start();
    include ROOT_DIR . '/views/operasi/partials/tab_kekuatan.php';
    $html = (string)ob_get_clean();
    assertCondition(str_contains($html, 'ALUT Darat') && str_contains($html, 'ALUT Laut') && str_contains($html, 'PALSAR')
        && strpos($html, 'Mobil Uji') < strpos($html, 'Speedboat Lama') && strpos($html, 'Drone Uji') < strpos($html, 'Palsar Uji'), 'Tab Kekuatan: urut + label kategori');
} catch (Throwable $e) {
    assertCondition(false, 'Tanpa exception: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}
$waSrc = (string)file_get_contents(ROOT_DIR . '/services/WhatsAppTemplateService.php');
assertCondition(substr_count($waSrc, 'self::formatAlutLines($alut)') === 3 && substr_count($waSrc, '"• Belum ada data alut"') === 1, 'Satu formatter alut WA (tidak duplikat)');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
