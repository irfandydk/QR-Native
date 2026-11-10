<?php
/**
 * Test: optimasi form Laporan Awal — Jenis Kejadian dropdown (+Lainnya, nilai lama tetap), Unsur bawaan
 * "SMC dan Staf", pangkalan asal terpilih dari asal_berangkat, drag-reorder Unsur & Alut (SortableJS).
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

echo "=== TEST FORM LAPORAN AWAL (OPTIMASI) ===\n\n";

assertCondition(defined('OperasiModel::JENIS_KEJADIAN_OPSI') && OperasiModel::JENIS_KEJADIAN_OPSI === [
    'Kecelakaan Kapal', 'Kecelakaan Pesawat Udara', 'Kondisi Membahayakan Manusia', 'Bencana', 'Kecelakaan dengan Penanganan Khusus',
], 'Daftar Jenis Kejadian');
$pilih = fn($v) => method_exists('OperasiModel', 'jenisKejadianPilihan') ? OperasiModel::jenisKejadianPilihan($v) : null;
assertCondition($pilih('Bencana') === ['pilihan' => 'Bencana', 'lainnya' => ''], 'Nilai dalam daftar → terpilih');
assertCondition($pilih('Orang Tenggelam') === ['pilihan' => '__LAINNYA__', 'lainnya' => 'Orang Tenggelam'], 'Nilai lama di luar daftar → Lainnya + teks utuh (Review Focus 1)');
assertCondition($pilih(null) === ['pilihan' => '', 'lainnya' => ''] && $pilih('  ') === ['pilihan' => '', 'lainnya' => ''], 'Kosong → belum dipilih');

$view = (string)file_get_contents(ROOT_DIR . '/views/operasi/form.php');
$js = (string)file_get_contents(ROOT_DIR . '/assets/js/pages/operasi_form.js');
assertCondition(str_contains($view, '<select id="jenis_kejadian_select"') && str_contains($view, 'value="__LAINNYA__"')
    && str_contains($view, 'name="jenis_kejadian"') && !str_contains($view, 'list="jenis_kejadian_opsi"')
    && str_contains($view, 'OperasiModel::JENIS_KEJADIAN_OPSI') && str_contains($view, 'OperasiModel::jenisKejadianPilihan('), 'Jenis Kejadian: dropdown + Lainnya, satu field terkirim');
assertCondition(str_contains($js, "getElementById('jenis_kejadian_select')"), 'JS menyinkronkan dropdown ke field jenis_kejadian');
assertCondition(str_contains($view, "['nama_unsur' => 'SMC dan Staf', 'jumlah_personil' => 2]") && !str_contains($view, "'nama_unsur' => 'Staf'"), 'Unsur bawaan: 1 baris "SMC dan Staf" (2 personil)');
$posSelect = (int)strpos($view, 'id="origin_base_select"');
$blokOrigin = substr($view, max(0, $posSelect - 800), 3300);
assertCondition(str_contains($blokOrigin, "\$operasi['asal_berangkat']") && str_contains($blokOrigin, '$namaUnitAsal($unit) === $asalTersimpan'), 'Pangkalan asal terpilih mengikuti asal_berangkat tersimpan');

assertCondition(str_contains($view, "assetUrl('assets/vendor/sortablejs/Sortable.min.js')")
    && strpos($view, 'sortablejs/Sortable.min.js') < strpos($view, 'pages/operasi_form.js'), 'SortableJS lokal dimuat sebelum operasi_form.js');
assertCondition(substr_count($js, 'new Sortable(') >= 2 && str_contains($js, "handle: '.drag-handle'")
    && !preg_match('/\bmove(Alut|Unsur)Row\(/', $js) && str_contains($js, 'AlutKategoriUI.groups('),
    'Drag-reorder Unsur & Alut (alut per kelompok kategori), tombol naik/turun dihapus');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
