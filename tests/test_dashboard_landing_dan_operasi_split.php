<?php
/**
 * QR-SAR - Automated Test Suite
 * Verifikasi pemisahan Halaman Operasi SAR (/operasi.php -> views/operasi/list.php)
 * dan Dashboard Baru (/index.php -> views/dashboard.php bergaya Hybrid Command-Landing).
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.\n");
}

require_once __DIR__ . '/bootstrap.php';

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/app.php';
require_once $rootDir . '/config/database.php';

$passCount = 0;
$failCount = 0;

function assertDash(string $label, bool $cond, string $detail = ''): void {
    global $passCount, $failCount;
    if ($cond) {
        $passCount++;
        echo "  [PASS] {$label}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
}

$db = getDB();

echo "\n=== 1. HELPER STATISTIK RINGAN (OperasiModel::countOpen & GisModel::getStatsSummary) ===\n";

$hasCountOpen = method_exists('OperasiModel', 'countOpen');
assertDash('OperasiModel::countOpen() tersedia', $hasCountOpen);
if ($hasCountOpen) {
    $expectedOpen = (int)$db->query("SELECT COUNT(*) FROM Operasi WHERE status = 'OPEN'")->fetchColumn();
    $actualOpen = OperasiModel::countOpen();
    assertDash(
        'OperasiModel::countOpen() mengembalikan jumlah operasi OPEN yang akurat',
        $actualOpen === $expectedOpen,
        "expected={$expectedOpen}, actual={$actualOpen}"
    );
}

$hasGisSummary = method_exists('GisModel', 'getStatsSummary');
assertDash('GisModel::getStatsSummary() tersedia', $hasGisSummary);
if ($hasGisSummary) {
    $summary = GisModel::getStatsSummary();
    $expectedPts = (int)$db->query('SELECT COUNT(*) FROM GisPoint')->fetchColumn();
    $expectedPolys = (int)$db->query('SELECT COUNT(*) FROM GisPolygon')->fetchColumn();
    $expectedPats = (int)$db->query('SELECT COUNT(*) FROM GisSearchPattern')->fetchColumn();
    $expectedRts = (int)$db->query('SELECT COUNT(*) FROM GisRoute')->fetchColumn();
    $expectedTotal = $expectedPts + $expectedPolys + $expectedPats + $expectedRts;

    assertDash(
        'GisModel::getStatsSummary() mengembalikan struktur points/polygons/patterns/routes/total yang akurat',
        isset($summary['points'], $summary['polygons'], $summary['patterns'], $summary['routes'], $summary['total'])
            && $summary['points'] === $expectedPts
            && $summary['polygons'] === $expectedPolys
            && $summary['patterns'] === $expectedPats
            && $summary['routes'] === $expectedRts
            && $summary['total'] === $expectedTotal,
        json_encode($summary)
    );
}

echo "\n=== 2. PEMISAHAN HALAMAN OPERASI SAR & MENU SIDEBAR ===\n";

$opsListPath = $rootDir . '/views/operasi/list.php';
$opsCtrlSrc  = (string)file_get_contents($rootDir . '/controllers/OperasiController.php');
$sidebarSrc  = (string)file_get_contents($rootDir . '/views/layout/sidebar.php');

assertDash('Berkas kanonik views/operasi/list.php tersedia', file_exists($opsListPath));
if (file_exists($opsListPath)) {
    $opsListSrc = (string)file_get_contents($opsListPath);
    assertDash(
        'views/operasi/list.php memuat daftar operasi dengan default OPEN dan tombol WA aman',
        str_contains($opsListSrc, ": 'OPEN';")
            && str_contains($opsListSrc, 'openOpsWaModal(this.dataset.id, this.dataset.nama)')
    );
}

assertDash(
    'OperasiController::index() merender view operasi/list pada rute operasi',
    str_contains($opsCtrlSrc, "\$this->render('operasi/list'")
        && str_contains($opsCtrlSrc, "'route' => 'operasi'")
);

assertDash(
    'views/layout/sidebar.php memisahkan menu Dashboard (/index.php) dan Operasi SAR (/operasi.php) dengan badge OPEN',
    str_contains($sidebarSrc, 'href="<?= BASE_URL ?>/index.php"')
        && str_contains($sidebarSrc, 'href="<?= BASE_URL ?>/operasi.php"')
        && str_contains($sidebarSrc, "\$currentRoute === 'dashboard'")
        && str_contains($sidebarSrc, "in_array(\$currentRoute, ['operasi', 'operasi_detail', 'operasi_form']")
        && str_contains($sidebarSrc, 'OperasiModel::countOpen()')
);

echo "\n=== 3. DASHBOARD BARU (HYBRID COMMAND-LANDING & KOMPATIBILITAS TEMA) ===\n";

$dashCtrlSrc = (string)file_get_contents($rootDir . '/controllers/DashboardController.php');
$dashViewSrc = (string)file_get_contents($rootDir . '/views/dashboard.php');

assertDash(
    'DashboardController::index() mengirimkan stats, activeOperations, dan appSettings ke views/dashboard.php',
    str_contains($dashCtrlSrc, 'OperasiModel::getStats()')
        && str_contains($dashCtrlSrc, "OperasiModel::getListSimple('OPEN')")
        && str_contains($dashCtrlSrc, "SettingsModel::getSettings()")
        && str_contains($dashCtrlSrc, "'route' => 'dashboard'")
);

assertDash(
    'views/dashboard.php memuat blok inti (Hero, Metrik Relevan, Alur Kerja 5 Poin, Fitur Utama, Keunggulan & Manfaat)',
    str_contains($dashViewSrc, 'id="dashHeroTitle"')
        && str_contains($dashViewSrc, 'id="dashWorkflowTitle"')
        && str_contains($dashViewSrc, 'id="dashFeaturesTitle"')
        && str_contains($dashViewSrc, 'id="dashBenefitsTitle"')
        && str_contains($dashViewSrc, '/operasi.php')
        && str_contains($dashViewSrc, '/gis.php')
        && str_contains($dashViewSrc, '/bra.php')
        && str_contains($dashViewSrc, 'Keunggulan')
        && str_contains($dashViewSrc, 'Manfaat')
);

assertDash(
    'views/dashboard.php menggunakan token tema adaptif (var(--primary), bento-card-v1) dan kartu informatif hover-only tanpa tautan pada Alur Kerja, Fitur, dan Keunggulan/Manfaat',
    str_contains($dashViewSrc, 'var(--primary)')
        && str_contains($dashViewSrc, 'bento-card-v1')
        && str_contains($dashViewSrc, 'dash-hover-card')
        && !str_contains($dashViewSrc, 'dash-card-link')
        && str_contains($dashViewSrc, '<ol')
        && str_contains($dashViewSrc, '<article')
);

// SSR Render test for both USER (non-admin) and ADMIN with zero errors/warnings
$renderDashboard = function (bool $asAdmin) use ($rootDir): string {
    $stats = ['total_operasi' => 5, 'operasi_aktif' => 2, 'operasi_selesai' => 3, 'total_bra' => 4];
    $activeOperations = [];
    $appSettings = ['satker_nama' => 'Kantor Pencarian dan Pertolongan Kendari', 'satker_kode' => 'SAR-304', 'ui_theme' => 'minimal'];
    $currentUser = ['nama' => $asAdmin ? 'Admin Uji' : 'Petugas Uji', 'role' => $asAdmin ? 'ADMIN' : 'USER'];
    $isAdmin = $asAdmin;
    ob_start();
    include $rootDir . '/views/dashboard.php';
    return (string)ob_get_clean();
};

$htmlUser = $renderDashboard(false);
$htmlAdmin = $renderDashboard(true);
$wfPos = strpos($htmlUser, 'aria-labelledby="dashWorkflowTitle"');
$infoSectionsHtml = $wfPos !== false ? substr($htmlUser, $wfPos) : '';
assertDash(
    'Alur Kerja, Fitur Utama, serta Keunggulan & Manfaat murni hover-only (0 tautan <a>, tidak dapat diklik pindah halaman)',
    $infoSectionsHtml !== '' && !preg_match('/<a\b/i', $infoSectionsHtml)
);
assertDash(
    'SSR Dashboard (role USER) merender metrik relevan tanpa Komponen GIS, Master Data, maupun tombol tambah operasi baru',
    str_contains($htmlUser, 'Petugas Uji')
        && str_contains($htmlUser, 'Kantor Pencarian dan Pertolongan Kendari')
        && str_contains($htmlUser, '/operasi.php')
        && !str_contains($htmlUser, '/operasi_form.php')
        && !str_contains($htmlUser, '/master.php')
        && !str_contains($htmlUser, '/admin.php')
        && !str_contains($htmlUser, 'Komponen GIS')
);
assertDash(
    'SSR Dashboard (role ADMIN) merender bersih, ikon-first, dan tanpa tautan tidak relevan (/operasi_form.php, /master.php)',
    str_contains($htmlAdmin, 'Admin Uji')
        && str_contains($htmlAdmin, 'Administrator')
        && !str_contains($htmlAdmin, '/operasi_form.php')
        && !str_contains($htmlAdmin, '/master.php')
        && !str_contains($htmlAdmin, 'Komponen GIS')
);

assertDash(
    'Ketiga aset gambar generatif lokal Dashboard tersedia dan dimuat di views/dashboard.php',
    file_exists($rootDir . '/assets/images/dashboard/hero-sar-ops.jpg')
        && file_exists($rootDir . '/assets/images/dashboard/feature-gis-tactical.jpg')
        && file_exists($rootDir . '/assets/images/dashboard/feature-command-desk.jpg')
        && str_contains($htmlUser, 'assets/images/dashboard/hero-sar-ops.jpg')
        && str_contains($htmlUser, 'assets/images/dashboard/feature-gis-tactical.jpg')
        && str_contains($htmlUser, 'assets/images/dashboard/feature-command-desk.jpg')
);

assertDash(
    'Output Dashboard bebas em-dash/en-dash dan menjaga teks ringkas (anti-slop pre-flight)',
    !str_contains($htmlUser, '—')
        && !str_contains($htmlUser, '–')
        && !str_contains($htmlAdmin, '—')
        && !str_contains($htmlAdmin, '–')
);

echo "\n=== RINGKASAN: {$passCount} PASS, {$failCount} FAIL ===\n";
exit($failCount > 0 ? 1 : 0);
