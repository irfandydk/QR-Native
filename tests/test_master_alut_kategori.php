<?php
/**
 * Test: Master Data Alut wajib berkategori (ALUT_DARAT/ALUT_LAUT/ALUT_UDARA/PALSAR).
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

echo "=== TEST MASTER ALUT KATEGORI ===\n\n";

$db = getDB();
$kat = fn(int $id): string => (string)$db->query("SELECT kategori FROM MasterAlut WHERE id_alut = {$id}")->fetchColumn();
$ids = [];
try {
    $ids[] = $id = MasterModel::createAlut('Uji Helikopter Basarnas', 'Unit', 'ALUT_UDARA');
    assertCondition($kat($id) === 'ALUT_UDARA', 'createAlut menyimpan kategori');
    MasterModel::updateAlut($id, 'Uji Helikopter Basarnas', 'Unit', 'palsar');
    assertCondition($kat($id) === 'PALSAR', 'updateAlut menyimpan kategori (dinormalkan)');
    $ids[] = $id2 = MasterModel::createAlut(['nama_alut' => 'Uji Rescue Truck', 'satuan' => 'Unit', 'kategori' => 'ALUT_DARAT']);
    assertCondition($kat($id2) === 'ALUT_DARAT', 'createAlut bentuk array membaca kategori');
    $tolak = false;
    try { MasterModel::createAlut('Uji Salah', 'Unit', 'ngawur'); } catch (InvalidArgumentException) { $tolak = true; }
    assertCondition($tolak, 'kategori tidak valid ditolak');
    assertCondition(isset(MasterModel::getAlut()[0]['kategori']), 'getAlut mengembalikan kategori');
} finally {
    foreach ($ids as $i) {
        $db->prepare('DELETE FROM MasterAlut WHERE id_alut = ?')->execute([$i]);
    }
    $db->exec("DELETE FROM MasterAlut WHERE nama_alut = 'Uji Salah'");
}

$tab = (string)file_get_contents(ROOT_DIR . '/views/master/partials/tab_alut.php');
$modals = (string)file_get_contents(ROOT_DIR . '/views/master/partials/modals.php');
$ctl = (string)file_get_contents(ROOT_DIR . '/controllers/MasterController.php');
$api = (string)file_get_contents(ROOT_DIR . '/api/controllers/MasterApiController.php');
assertCondition(str_contains($tab, 'name="kategori"') && str_contains($tab, 'AlutKategori::LABEL') && str_contains($tab, 'AlutKategori::label('), 'Form tambah: select kategori; tabel: label kategori');
assertCondition(str_contains($modals, 'id="edit_kategori_alut"') && str_contains($modals, 'function openEditAlutModal(id, nama, satuan, kategori)'), 'Modal edit: select kategori');
assertCondition(!str_contains($ctl, "'Alut'") && substr_count($ctl, "\$_POST['kategori']") >= 2, 'Controller meneruskan kategori (tambah & ubah)');
assertCondition(substr_count($api, "\$input['kategori']") >= 2, 'API master meneruskan kategori');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
