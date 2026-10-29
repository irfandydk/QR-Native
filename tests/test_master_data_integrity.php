<?php
/**
 * QR-SAR - Automated Test Suite: Master Data Integrity & Sub-Page Switching
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Menguji orkestrasi sub-halaman master data: Alut & Palsar, Potensi/Tim SAR,
 * dan Pejabat/SMC, integritas URL state, tab switching, dan keamanan CSRF.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';

$totalAssertions = 0;
$passedAssertions = 0;

function assertCondition(string $description, bool $condition): void {
    global $totalAssertions, $passedAssertions;
    $totalAssertions++;
    if ($condition) {
        $passedAssertions++;
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
    }
}

echo "\n====================================================================\n";
echo "   UJI OTOMATIS: INTEGRITAS MASTER DATA & SUB-PAGE SWITCHING        \n";
echo "====================================================================\n";

$rootDir = dirname(__DIR__);

// --- 1. AUDIT STATIC CODE & INTEGRITAS ARSITEKTUR ---
echo "\n--- 1. AUDIT STATIC CODE & INTEGRITAS ARSITEKTUR ---\n";

// 1.1 MasterController.php
$controllerPath = $rootDir . '/controllers/MasterController.php';
assertCondition("Berkas controllers/MasterController.php fisik tersedia", file_exists($controllerPath));
$controllerCode = file_get_contents($controllerPath);

assertCondition("MasterController memvalidasi whitelist tab (alut, tim, pejabat)", str_contains($controllerCode, "in_array(\$tab, ['alut', 'tim', 'pejabat']"));
assertCondition("MasterController mengoper 'tab' => \$tab ke render view", str_contains($controllerCode, "'tab' => \$tab"));
assertCondition("MasterController mengoper 'activeTab' => \$tab ke render view", str_contains($controllerCode, "'activeTab' => \$tab"));
assertCondition("MasterController menolak aksi GET delete/toggle secara defensif", str_contains($controllerCode, "in_array(\$action, ['delete_alut', 'delete_tim', 'delete_pejabat', 'toggle_smc']"));

// 1.2 views/master/index.php
$masterIndexPath = $rootDir . '/views/master/index.php';
assertCondition("Berkas views/master/index.php fisik tersedia", file_exists($masterIndexPath));
$masterIndexCode = file_get_contents($masterIndexPath);

assertCondition("views/master/index.php menggunakan defensive fallback multi-layer untuk activeTab", str_contains($masterIndexCode, "\$activeTab = \$tab ?? (\$activeTab ?? (\$_GET['tab'] ?? 'alut'))"));
assertCondition("views/master/index.php memvalidasi activeTab terhadap whitelist", str_contains($masterIndexCode, "if (!in_array(\$activeTab, ['alut', 'tim', 'pejabat']"));
assertCondition("views/master/index.php mendefinisikan masterTabMap lengkap", str_contains($masterIndexCode, "'alut'    => 'master/partials/tab_alut'") && str_contains($masterIndexCode, "'tim'     => 'master/partials/tab_potensi'") && str_contains($masterIndexCode, "'pejabat' => 'master/partials/tab_pejabat'"));

// 1.3 views/master/partials/ files
$partials = [
    'tab_alut.php',
    'tab_potensi.php',
    'tab_pejabat.php',
    'modals.php'
];

foreach ($partials as $p) {
    $pPath = $rootDir . '/views/master/partials/' . $p;
    assertCondition("Partial views/master/partials/{$p} fisik tersedia", file_exists($pPath));
}

// --- 2. RUNTIME DISPATCH & SUB-PAGE SWITCHING EVALUATION ---
echo "\n--- 2. RUNTIME DISPATCH & SUB-PAGE SWITCHING EVALUATION ---\n";

function renderMasterTab(string $tab, string $searchQuery = ''): string {
    $_GET['tab'] = $tab;
    if ($searchQuery !== '') {
        $_GET['q'] = $searchQuery;
    } else {
        unset($_GET['q']);
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'admin';
    $_SESSION['username'] = 'admin';

    ob_start();
    (new MasterController())->index();
    return (string)ob_get_clean();
}

// 2.1 Tab ALUT (Master Alut & Palsar)
$htmlAlut = renderMasterTab('alut');
preg_match_all('/<a href="[^"]*tab=alut"[^>]*class="([^"]*)"/', $htmlAlut, $mAlutClass);
$alutHighlighted = false;
foreach ($mAlutClass[1] as $c) {
    if (str_contains($c, 'text-white shadow-md')) {
        $alutHighlighted = true;
        break;
    }
}
assertCondition("Tab Alut ber-highlight aktif saat tab=alut", $alutHighlighted);
assertCondition("Tab Alut merender 'Daftar Master Alut & Palsar'", str_contains($htmlAlut, 'Daftar Master Alut & Palsar'));
assertCondition("Tab Alut TIDAK merender konten tab Tim", !str_contains($htmlAlut, 'Daftar Tim & Potensi SAR'));
assertCondition("Tab Alut TIDAK merender konten tab Pejabat", !str_contains($htmlAlut, 'Daftar Pejabat Berwenang & Penandatangan SMC'));

// 2.2 Tab TIM (Master Potensi SAR / Tim)
$htmlTim = renderMasterTab('tim');
preg_match_all('/<a href="[^"]*tab=tim"[^>]*class="([^"]*)"/', $htmlTim, $mTimClass);
$timHighlighted = false;
foreach ($mTimClass[1] as $c) {
    if (str_contains($c, 'text-white shadow-md')) {
        $timHighlighted = true;
        break;
    }
}
assertCondition("Tab Tim ber-highlight aktif saat tab=tim", $timHighlighted);
assertCondition("Tab Tim merender 'Daftar Tim & Potensi SAR'", str_contains($htmlTim, 'Daftar Tim & Potensi SAR'));
assertCondition("Tab Tim TIDAK merender konten tab Alut", !str_contains($htmlTim, 'Daftar Master Alut & Palsar'));
assertCondition("Tab Tim TIDAK merender konten tab Pejabat", !str_contains($htmlTim, 'Daftar Pejabat Berwenang & Penandatangan SMC'));

// 2.3 Tab PEJABAT (Pejabat Berwenang & SMC)
$htmlPejabat = renderMasterTab('pejabat');
preg_match_all('/<a href="[^"]*tab=pejabat"[^>]*class="([^"]*)"/', $htmlPejabat, $mPejabatClass);
$pejabatHighlighted = false;
foreach ($mPejabatClass[1] as $c) {
    if (str_contains($c, 'text-white shadow-md')) {
        $pejabatHighlighted = true;
        break;
    }
}
assertCondition("Tab Pejabat ber-highlight aktif saat tab=pejabat", $pejabatHighlighted);
assertCondition("Tab Pejabat merender 'Daftar Pejabat Berwenang & Penandatangan SMC'", str_contains($htmlPejabat, 'Daftar Pejabat Berwenang & Penandatangan SMC'));
assertCondition("Tab Pejabat TIDAK merender konten tab Alut", !str_contains($htmlPejabat, 'Daftar Master Alut & Palsar'));
assertCondition("Tab Pejabat TIDAK merender konten tab Tim", !str_contains($htmlPejabat, 'Daftar Tim & Potensi SAR'));

// 2.4 Invalid Tab Fallback
$htmlInvalid = renderMasterTab('halaman_tidak_dikenal');
assertCondition("Invalid tab fallback dengan aman ke Alut & Palsar", str_contains($htmlInvalid, 'Daftar Master Alut & Palsar') && !str_contains($htmlInvalid, 'Daftar Tim & Potensi SAR'));

// 2.5 Dynamic Refresh Link Verification
assertCondition("Tab tim aktif dirender", str_contains($htmlTim, 'master.php?tab=tim'));
assertCondition("Tab pejabat aktif dirender", str_contains($htmlPejabat, 'master.php?tab=pejabat'));

// --- 3. SEARCH PRE-FILL & MODAL DIALOGS VERIFICATION ---
echo "\n--- 3. SEARCH PRE-FILL & MODAL DIALOGS VERIFICATION ---\n";

// 3.1 Search Pre-fill Evaluation
$htmlSearchAlut = renderMasterTab('alut', 'KN SAR Pacitan');
assertCondition("Input filterMasterAlut terisi pre-fill search query 'KN SAR Pacitan'", str_contains($htmlSearchAlut, 'id="filterMasterAlut" value="KN SAR Pacitan"'));

$htmlSearchTim = renderMasterTab('tim', 'Polairud Sultra');
assertCondition("Input filterMasterTim terisi pre-fill search query 'Polairud Sultra'", str_contains($htmlSearchTim, 'id="filterMasterTim" value="Polairud Sultra"'));

$htmlSearchPejabat = renderMasterTab('pejabat', 'Amiruddin');
assertCondition("Input filterMasterPejabat terisi pre-fill search query 'Amiruddin'", str_contains($htmlSearchPejabat, 'id="filterMasterPejabat" value="Amiruddin"'));

// 3.2 Modal Dialogs Integration
assertCondition("Modal modal-edit-alut memiliki kelas modal-overlay", str_contains($htmlAlut, 'id="modal-edit-alut" class="modal-overlay'));
assertCondition("Modal modal-edit-tim memiliki kelas modal-overlay", str_contains($htmlTim, 'id="modal-edit-tim" class="modal-overlay'));
assertCondition("Modal modal-edit-pejabat memiliki kelas modal-overlay", str_contains($htmlPejabat, 'id="modal-edit-pejabat" class="modal-overlay'));

// 3.3 CSRF Form Presence
assertCondition("Form tambah Alut memiliki CSRF token", str_contains($htmlAlut, 'name="csrf_token"'));
assertCondition("Form tambah Tim memiliki CSRF token", str_contains($htmlTim, 'name="csrf_token"'));
assertCondition("Form tambah Pejabat memiliki CSRF token", str_contains($htmlPejabat, 'name="csrf_token"'));

// --- 4. REKAPITULASI HASIL UJI ---
echo "\n====================================================================\n";
echo "REKAPITULASI UJI: {$passedAssertions} / {$totalAssertions} PENGUJIAN LULUS (" . round(($passedAssertions / $totalAssertions) * 100, 2) . "%)\n";
echo "====================================================================\n";

if ($passedAssertions === $totalAssertions) {
    echo "\n>>> STATUS: MASTER DATA & SUB-PAGE SWITCHING BERFUNGSI SEMPURNA (100% PASS) <<<\n\n";
    exit(0);
} else {
    echo "\n>>> STATUS: TERDAPAT PENGUJIAN YANG GAGAL! <<<\n\n";
    exit(1);
}
