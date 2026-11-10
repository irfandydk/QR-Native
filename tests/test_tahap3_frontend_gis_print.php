<?php
/**
 * Test Suite: Perbaikan Audit Tahap 3 (Frontend, Peta GIS, Cetak & Kerampingan Kode)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: hanya CLI.' . PHP_EOL);
}

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$total = 0;

function assertT3(bool $cond, string $msg): void {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] {$msg}\n";
    } else {
        echo " [FAIL] {$msg}\n";
    }
}

echo "=== TEST TAHAP 3: FRONTEND, PETA GIS, CETAK & KERAMPINGAN KODE ===\n\n";

$rootDir = ROOT_DIR;

// 1. Uji Finding #3: window.BASE_URL di gis_master.js dan normalisasi URL ikon
$gisMasterContent = file_get_contents($rootDir . '/assets/js/gis_master.js');
assertT3(
    str_contains($gisMasterContent, 'const BASE_URL = window.BASE_URL || window.location.origin;'),
    'gis_master.js memprioritaskan window.BASE_URL untuk instalasi subfolder Laragon'
);

$uiPointsContent = file_get_contents($rootDir . '/assets/js/gis/ui_points.js');
assertT3(
    str_contains($uiPointsContent, 'const base = (window.BASE_URL || window.location.origin || \'\')'),
    'ui_points.js normalizeIconUrl menyertakan window.BASE_URL'
);

// 2. Uji Finding #6: Anti XSS & penghapusan addslashes rawan newline
$renopsForm = file_get_contents($rootDir . '/views/operasi/renops_form.php');
assertT3(
    str_contains($renopsForm, 'e($hariKe)'),
    'renops_form.php membungkus $hariKe dengan helper e() untuk mencegah XSS'
);

$sitrepForm = file_get_contents($rootDir . '/views/operasi/sitrep_form.php');
assertT3(
    str_contains($sitrepForm, 'e($hariKe)'),
    'sitrep_form.php membungkus $hariKe dengan helper e() untuk mencegah XSS'
);

$braList = file_get_contents($rootDir . '/views/bra/list.php');
assertT3(
    str_contains($braList, 'data-no-bra="<?= e($bra[\'no_bra\'] ?? \'\') ?>"') && str_contains($braList, 'copyBraNo(this.dataset.noBra, this)'),
    'bra/list.php memindahkan nomor BRA dari inline onclick ke data-no-bra (anti quote breakout)'
);

$dashboard = file_get_contents($rootDir . '/views/dashboard.php');
assertT3(
    !str_contains($dashboard, 'onclick="openOpsWaModal(<?= $op[\'id_operasi\'] ?>, \'<?= e(addslashes') &&
    str_contains($dashboard, 'data-nama="<?= e($op[\'nama_operasi\']) ?>"') &&
    str_contains($dashboard, 'openOpsWaModal(this.dataset.id, this.dataset.nama)'),
    'dashboard.php tombol modal WA dan Hapus memakai data-nama sehingga aman dari newline/kutip'
);

// 3. Uji Finding #9: Offline Google Fonts, print rules, copy fallback
$header = file_get_contents($rootDir . '/views/layout/header.php');
assertT3(
    !str_contains($header, 'fonts.googleapis.com'),
    'header.php tidak lagi memuat Google Fonts CDN yang memblokir rendering saat offline'
);

$braForm = file_get_contents($rootDir . '/views/bra/form.php');
assertT3(
    str_contains($braForm, '#braMasterForm, #formBraSar {'),
    'bra/form.php menyembunyikan form input aktual #braMasterForm pada @media print'
);

$braView = file_get_contents($rootDir . '/views/bra/view.php');
assertT3(
    str_contains($braView, 'copyToClipboard(txt') && str_contains($braView, 'document.execCommand(\'copy\')'),
    'bra/view.php menyediakan fallback copy teks telegram pada koneksi LAN non-HTTPS'
);

// 4. Uji Finding #14: Optimalisasi pemuatan modul GIS dan urutan skrip
assertT3(
    !str_contains($header, '\'operasi\', \'operasi_detail\''),
    'header.php tidak memuat Leaflet CSS pada rute tabel daftar operasi tanpa peta'
);

$footer = file_get_contents($rootDir . '/views/layout/footer.php');
assertT3(
    str_contains($footer, '($currentRoute ?? \'\') !== \'gis\''),
    'footer.php tidak memuat gis.js legacy saat halaman GIS taktis aktif (mencegah double init pada #sarMap)'
);

assertT3(
    !str_contains($footer, '\'operasi\', \'operasi_detail\''),
    'footer.php tidak memuat Leaflet JS pada rute tabel daftar operasi tanpa peta'
);

$gisView = file_get_contents($rootDir . '/views/gis.php');
assertT3(
    str_contains($gisView, '<script src="<?= assetUrl(\'assets/js/gis_master.js\') ?>"></script>'),
    'views/gis.php memuat gis_master.js sebagai master controller taktis'
);

// 5. Uji Finding #15: CSS build, perbaikan class typo, & standalone HTML
$packageJson = file_get_contents($rootDir . '/package.json');
assertT3(
    str_contains($packageJson, '"build:css"') && str_contains($packageJson, 'tailwindcss'),
    'package.json tersedia dengan skrip build:css'
);

$tabSitrep = file_get_contents($rootDir . '/views/operasi/partials/tab_sitrep.php');
assertT3(
    str_contains($tabSitrep, 'dark:text-white') && !str_contains($tabSitrep, 'dark:white mb-1'),
    'tab_sitrep.php memperbaiki typo dark:white menjadi dark:text-white'
);

assertT3(
    !str_contains($braView, 'bg-emerald-6000/50'),
    'bra/view.php memperbaiki typo bg-emerald-6000/50'
);

$tailwindConfig = file_get_contents($rootDir . '/tailwind.config.js');
assertT3(
    str_contains($tailwindConfig, '\'xs\': \'0 1px 2px 0 rgba(0, 0, 0, 0.05)\''),
    'tailwind.config.js mendefinisikan utilitas shadow-xs'
);

// 6. Uji Finding #18: Firefox ESR plaintext-only & Login dead clock timer
$renopsDokumen = file_get_contents($rootDir . '/assets/js/pages/renops_dokumen.js');
assertT3(
    str_contains($renopsDokumen, 'supportsPlaintextOnly') && str_contains($renopsDokumen, 'document.execCommand(\'insertText\''),
    'renops_dokumen.js memiliki fallback paste plain-text untuk browser Firefox ESR'
);

$login = file_get_contents($rootDir . '/views/auth/login.php');
assertT3(
    !str_contains($login, 'updateLiveClock') && !str_contains($login, 'liveClockWita'),
    'views/auth/login.php bersih dari dead timer setInterval untuk elemen yang sudah dihapus'
);

echo "\nTotal: {$total}, Lolos: {$passed}\n";
exit($passed === $total ? 0 : 1);

