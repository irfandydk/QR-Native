<?php
/**
 * Test: isian Jenis Kejadian pada form Laporan Awal tersimpan ke Operasi.jenis_kejadian
 * (dipakai kop dokumen RENOPS SMEAC, Tabel 1 "Jenis Musibah" dan kalimat Mission).
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

echo "=== TEST JENIS KEJADIAN LAPORAN AWAL ===\n\n";

$jenisDb = static fn(int $id): ?string => getDB()->query("SELECT jenis_kejadian FROM Operasi WHERE id_operasi = {$id}")->fetchColumn() ?: null;

$base = [
    'nama_operasi' => 'Uji Jenis Kejadian',
    'nama_pelapor' => 'Pelapor Uji',
    'lokasi_kejadian' => 'Perairan Uji',
    'waktu_lapor' => '2026-11-01 08:00:00',
    'waktu_berangkat' => '2026-11-01 09:00:00',
];
$idOps = 0;
try {
    $idOps = OperasiModel::create($base + ['jenis_kejadian' => '  Kondisi Membahayakan Manusia  ']);
    assertCondition($jenisDb($idOps) === 'Kondisi Membahayakan Manusia', 'Create menyimpan jenis kejadian (di-trim)');
    assertCondition(OperasiModel::getById($idOps)['jenis_kejadian'] === 'Kondisi Membahayakan Manusia', 'getById mengembalikan jenis kejadian');

    OperasiModel::update($idOps, ['nama_operasi' => 'Uji Jenis Kejadian (edit)']);
    assertCondition($jenisDb($idOps) === 'Kondisi Membahayakan Manusia', 'Update tanpa isian jenis tidak menghapus nilai lama');

    OperasiModel::update($idOps, ['jenis_kejadian' => 'Kecelakaan Kapal']);
    assertCondition($jenisDb($idOps) === 'Kecelakaan Kapal', 'Update mengganti jenis kejadian');

    OperasiModel::update($idOps, ['jenis_kejadian' => '   ']);
    assertCondition($jenisDb($idOps) === null, 'Isian dikosongkan disimpan sebagai NULL');

    OperasiModel::update($idOps, ['jenis_kejadian' => str_repeat('é', 250)]);
    assertCondition(mb_strlen((string)$jenisDb($idOps)) === 191, 'Lebih dari 191 karakter dipotong, aman untuk multibyte');

    OperasiModel::update($idOps, ['jenis_kejadian' => 'Kondisi Membahayakan Manusia']);
    $doc = RenopsDokumenService::resolve(RenopsModel::create([
        'id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-01',
        'waktu_mulai_ops' => '2026-11-01 09:00:00',
    ]));
    $rows = array_column($doc['sections']['data_kejadian'], 'value', 'label');
    assertCondition($rows['Jenis Musibah'] === 'Kondisi Membahayakan Manusia' && str_contains($doc['sections']['mission'], 'pada Kondisi Membahayakan Manusia'), 'Dokumen RENOPS memakai jenis kejadian dari Laporan Awal');

    $form = (string)file_get_contents(dirname(__DIR__) . '/views/operasi/form.php');
    assertCondition((bool)preg_match('/name="jenis_kejadian"[^>]*maxlength="191"/', $form) && str_contains($form, "e(\$operasi['jenis_kejadian'] ?? '')"), 'Form Laporan Awal punya isian jenis_kejadian (maxlength 191, nilai di-escape)');
    assertCondition(str_contains($form, '<select id="jenis_kejadian_select"') && str_contains($form, 'OperasiModel::JENIS_KEJADIAN_OPSI'), 'Form menyediakan pilihan jenis kejadian umum (dropdown)');
    $js = (string)file_get_contents(dirname(__DIR__) . '/assets/js/pages/operasi_form.js');
    assertCondition(substr_count($js, "jenis_kejadian") >= 2, 'Draf otomatis (localStorage) menyimpan & memulihkan jenis kejadian');
} finally {
    if ($idOps > 0) {
        OperasiModel::delete($idOps);
    }
}

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
