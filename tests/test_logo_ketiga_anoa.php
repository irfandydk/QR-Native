<?php
/**
 * Test: Logo ketiga (ANOA Rescue / Dokumentasi, AppSettings.logo_url_3) bisa diatur di Pengaturan Satker
 * dan memiliki bawaan assets/images/logo_anoa_rescue.png.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testPassed = 0;
$testTotal = 0;

function assertLogo3(bool $cond, string $message): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($cond) {
        $testPassed++;
        echo "  [PASS] {$message}\n";
    } else {
        echo "  [FAIL] {$message}\n";
    }
}

echo "=== TEST LOGO KETIGA (ANOA RESCUE) PENGATURAN SATKER ===\n\n";

assertLogo3(
    is_file(ROOT_DIR . '/assets/images/logo_anoa_rescue.png'),
    'Berkas bawaan assets/images/logo_anoa_rescue.png tersedia'
);

assertLogo3(
    function_exists('getAppLogo3DataUri'),
    'Helper getAppLogo3DataUri() tersedia di config/app.php'
);

$db = getDB();
$cols = $db->query("SHOW COLUMNS FROM AppSettings LIKE 'logo_url_3'")->fetchAll();
assertLogo3(!empty($cols), 'Kolom AppSettings.logo_url_3 tersedia di skema database');

if (!empty($cols) && function_exists('getAppLogo3DataUri')) {
    $lama = $db->query("SELECT logo_url_3 FROM AppSettings WHERE id = 1")->fetchColumn();
    $logoUji = 'data:image/png;base64,' . base64_encode('logo-anoa-rescue-uji');
    try {
        SettingsModel::updateSettings(['logo_url_3' => '']);
        assertLogo3(
            str_starts_with(qrsar_logo_data_uri('logo_url_3', ROOT_DIR . '/assets/images/logo_anoa_rescue.png'), 'data:image/png;base64,'),
            'Saat logo_url_3 kosong, fallback memakai data URI PNG bawaan ANOA Rescue'
        );

        SettingsModel::updateSettings(['logo_url_3' => $logoUji]);
        assertLogo3(
            $db->query("SELECT logo_url_3 FROM AppSettings WHERE id = 1")->fetchColumn() === $logoUji,
            'SettingsModel::updateSettings() menyimpan logo_url_3'
        );
        assertLogo3(
            qrsar_logo_data_uri('logo_url_3', ROOT_DIR . '/assets/images/logo_anoa_rescue.png') === $logoUji,
            'Logo ketiga membaca data URI kustom dari AppSettings.logo_url_3'
        );
    } finally {
        $db->prepare("UPDATE AppSettings SET logo_url_3 = ? WHERE id = 1")->execute([$lama === false ? null : $lama]);
        SettingsModel::clearCache();
    }
}

$tab = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_satker.php');
assertLogo3(
    str_contains($tab, 'name="logo_file_3"')
        && str_contains($tab, 'name="logo_url_3"')
        && str_contains($tab, 'satkerLogo3Preview')
        && str_contains($tab, 'getAppLogo3DataUri()'),
    'views/admin/partials/tab_satker.php memiliki kartu isian Logo Ketiga (ANOA Rescue)'
);

$ctl = (string)file_get_contents(ROOT_DIR . '/controllers/AdminController.php');
assertLogo3(
    str_contains($ctl, "\$this->prosesLogoSatker('logo_file_3', 'logo_url_3', \$data);"),
    'AdminController::index() memproses unggahan logo_file_3 ke logo_url_3'
);

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
