<?php
/**
 * Test: logo kedua kop dokumen (AppSettings.logo_url_2) bisa diatur di Pengaturan Satker
 * dan dipakai sisi kanan kop Dokumen Rencana Operasi SAR; kosong = logo SAR Nasional bawaan.
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

echo "=== TEST LOGO KEDUA KOP DOKUMEN ===\n\n";

$db = getDB();
$lama = $db->query("SELECT logo_url_2 FROM AppSettings WHERE id = 1")->fetchColumn();
$logoUji = 'data:image/png;base64,' . base64_encode('logo-kedua-uji');
try {
    SettingsModel::updateSettings(['logo_url_2' => $logoUji]);
    assertCondition($db->query("SELECT logo_url_2 FROM AppSettings WHERE id = 1")->fetchColumn() === $logoUji, 'updateSettings menyimpan logo_url_2');
    assertCondition(getAppLogo2DataUri() === $logoUji, 'getAppLogo2DataUri memakai logo dari pengaturan');
    assertCondition(str_starts_with(qrsar_logo_data_uri('kolom_tidak_ada', ROOT_DIR . '/assets/images/logo_sar_nasional.jpg'), 'data:image/jpeg;base64,'), 'Kosong: logo SAR Nasional bawaan');

    $tab = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_satker.php');
    assertCondition(str_contains($tab, 'name="logo_file_2"') && str_contains($tab, 'name="logo_url_2"') && str_contains($tab, "e(\$settings['logo_url_2'] ?? '')"), 'Form Pengaturan Satker punya isian logo kedua');
    $ctl = (string)file_get_contents(ROOT_DIR . '/controllers/AdminController.php');
    assertCondition(str_contains($ctl, "\$this->prosesLogoSatker('logo_file', 'logo_url', \$data);") && str_contains($ctl, "\$this->prosesLogoSatker('logo_file_2', 'logo_url_2', \$data);"), 'Simpan satker memproses kedua logo dengan validasi yang sama');
    $partial = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php');
    assertCondition(str_contains($partial, 'e(getAppLogo2DataUri())'), 'Kop kanan dokumen RENOPS memakai logo kedua');
} finally {
    $db->prepare("UPDATE AppSettings SET logo_url_2 = ? WHERE id = 1")->execute([$lama === false ? null : $lama]);
}

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
