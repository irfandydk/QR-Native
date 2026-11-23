<?php
/**
 * QR-SAR - Regression & Verification Tests
 * Navigasi Capture RENOPS (same-tab), Navigasi Kembali BRA (context-aware),
 * dan UI Halaman Ploting Peta GIS (Unified Header, Floating Circular Controls, Compact Sidebar).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/controllers/BaseController.php';
require_once dirname(__DIR__) . '/controllers/BraController.php';

$rootDir = dirname(__DIR__);
$passCount = 0;
$failCount = 0;

function assertCheck(string $label, bool $condition, string $detail = ''): void {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo "  [PASS] {$label}\n";
    } else {
        $failCount++;
        echo "  [FAIL] {$label}" . ($detail !== '' ? " ({$detail})" : '') . "\n";
    }
}

echo "\n=== 1. NAVIGASI CAPTURE RENOPS (SAME-TAB RETURN) ===\n";

$renopsDocSrc = (string)file_get_contents($rootDir . '/views/operasi/partials/renops_smeac_doc.php');
$gisViewSrc   = (string)file_get_contents($rootDir . '/views/gis.php');
$gisJsSrc     = (string)file_get_contents($rootDir . '/assets/js/gis_master.js');

// 1a. LKP capture URL passes from_renops without adding capture_ref
$fromQPos = strpos($renopsDocSrc, '$fromRenopsQ =');
$lkpLinePos = strpos($renopsDocSrc, '$capturePetaUrl =');
$lkpLineEnd = $lkpLinePos !== false ? strpos($renopsDocSrc, ';', $lkpLinePos) : false;
$lkpLine = ($lkpLinePos !== false && $lkpLineEnd !== false) ? substr($renopsDocSrc, $lkpLinePos, $lkpLineEnd - $lkpLinePos + 1) : '';
assertCheck(
    'renops_smeac_doc.php menyertakan from_renops pada tautan Capture Peta LKP tanpa capture_ref',
    $fromQPos !== false
        && str_contains($renopsDocSrc, "'&from_renops='")
        && str_contains($lkpLine, 'capture_doc=RENOPS')
        && str_contains($lkpLine, '$fromRenopsQ')
        && !str_contains($lkpLine, 'capture_ref=')
);

// 1b. Peta Rencana capture URL also passes from_renops
$rencanaLinePos = strpos($renopsDocSrc, '$capturePetaRencanaUrl =');
$rencanaLineEnd = $rencanaLinePos !== false ? strpos($renopsDocSrc, ';', $rencanaLinePos) : false;
$rencanaLine = ($rencanaLinePos !== false && $rencanaLineEnd !== false) ? substr($renopsDocSrc, $rencanaLinePos, $rencanaLineEnd - $rencanaLinePos + 1) : '';
assertCheck(
    'renops_smeac_doc.php menyertakan from_renops pada tautan Capture Peta Rencana',
    str_contains($rencanaLine, 'capture_doc=RENOPS_RENCANA')
        && str_contains($rencanaLine, 'capture_ref=')
        && str_contains($rencanaLine, '$fromRenopsQ')
);

// 1c. views/gis.php parses from_renops and exposes fromRenopsParam
assertCheck(
    'views/gis.php mem-parsing parameter GET from_renops dan mengekspor fromRenopsParam ke SAR_INITIAL_GIS_DATA',
    str_contains($gisViewSrc, "\$_GET['from_renops']")
        && str_contains($gisViewSrc, 'fromRenopsParam:')
);

// 1d. gis_master.js does NOT use target="_blank" in capture status banner
$bannerFnPos = strpos($gisJsSrc, 'function updateSavedStatusBanner()');
$bannerFnSnippet = $bannerFnPos !== false ? substr($gisJsSrc, $bannerFnPos, 1700) : '';
assertCheck(
    'gis_master.js tidak memakai target="_blank" pada tombol kembali/cetakan hasil capture',
    $bannerFnSnippet !== '' && !str_contains($bannerFnSnippet, 'target="_blank"')
);

// 1e. getOriginRenopsForOperasi + getPrintUrlForSelection prioritize fromRenopsParam scoped to currentOperasiId
$originFnPos = strpos($gisJsSrc, 'function getOriginRenopsForOperasi(');
$originAndPrintSnippet = $originFnPos !== false ? substr($gisJsSrc, $originFnPos, 1100) : '';
assertCheck(
    'getOriginRenopsForOperasi & getPrintUrlForSelection memprioritaskan fromRenopsParam dengan guard kesesuaian currentOperasiId',
    $originFnPos !== false
        && str_contains($originAndPrintSnippet, 'initialData.fromRenopsParam')
        && str_contains($originAndPrintSnippet, 'initialData.currentOperasiId')
        && str_contains($originAndPrintSnippet, 'idRef || originRenops')
);

echo "\n=== 2. NAVIGASI KEMBALI BRA SAR (CONTEXT-AWARE) ===\n";

$tabBraSrc  = (string)file_get_contents($rootDir . '/views/operasi/partials/tab_bra.php');
$braViewSrc = (string)file_get_contents($rootDir . '/views/bra/view.php');
$braFormSrc = (string)file_get_contents($rootDir . '/views/bra/form.php');
$braCtrlSrc = (string)file_get_contents($rootDir . '/controllers/BraController.php');

// 2a. tab_bra.php passes return_to=operasi_bra&return_op= on View, Edit, and Create links
assertCheck(
    'tab_bra.php menyertakan return_to=operasi_bra&return_op= pada tautan Lihat, Edit, dan Buat BRA',
    str_contains($tabBraSrc, 'return_to=operasi_bra&return_op=')
        && substr_count($tabBraSrc, 'return_to=operasi_bra&return_op=') >= 4
);

// 2b. BraController::resolveReturnContext validates strict positive int and blocks open redirect
$hasResolver = method_exists('BraController', 'resolveReturnContext');
assertCheck('BraController::resolveReturnContext() tersedia', $hasResolver);

if ($hasResolver) {
    $validCtx = BraController::resolveReturnContext(['return_to' => 'operasi_bra', 'return_op' => '42']);
    assertCheck(
        'resolveReturnContext valid mengembalikan /operasi_detail.php?id=42&tab=bra',
        ($validCtx['backUrl'] ?? '') === BASE_URL . '/operasi_detail.php?id=42&tab=bra'
            && ($validCtx['returnQuery'] ?? '') === '&return_to=operasi_bra&return_op=42'
            && ($validCtx['returnOp'] ?? 0) === 42
    );

    $defaultCtx = BraController::resolveReturnContext([]);
    assertCheck(
        'resolveReturnContext tanpa parameter fallback aman ke /bra.php',
        ($defaultCtx['backUrl'] ?? '') === BASE_URL . '/bra.php'
            && ($defaultCtx['returnQuery'] ?? '') === ''
    );

    $malicious1 = BraController::resolveReturnContext(['return_to' => '//evil.com', 'return_op' => '42']);
    $malicious2 = BraController::resolveReturnContext(['return_to' => 'operasi_bra', 'return_op' => '0']);
    $malicious3 = BraController::resolveReturnContext(['return_to' => 'operasi_bra', 'return_op' => '42abc']);
    $malicious4 = BraController::resolveReturnContext(['return_to' => 'operasi_bra', 'return_op' => '-7']);
    assertCheck(
        'resolveReturnContext menolak input manipulatif/open-redirect dan fallback ke /bra.php',
        ($malicious1['backUrl'] ?? '') === BASE_URL . '/bra.php'
            && ($malicious2['backUrl'] ?? '') === BASE_URL . '/bra.php'
            && ($malicious3['backUrl'] ?? '') === BASE_URL . '/bra.php'
            && ($malicious4['backUrl'] ?? '') === BASE_URL . '/bra.php'
    );
}

// 2c. views/bra/view.php uses $backUrl and preserves $returnQuery on Edit Form link
assertCheck(
    'views/bra/view.php memakai $backUrl untuk tombol kembali dan meneruskan $returnQuery ke Edit Form',
    str_contains($braViewSrc, 'href="<?= e($braBackUrl) ?>"')
        && str_contains($braViewSrc, '$braReturnQuery')
);

// 2d. views/bra/form.php uses $backUrl on Batal and persists hidden return_to/return_op
assertCheck(
    'views/bra/form.php memakai $braBackUrl untuk tombol Batal dan menyimpan hidden input return_to/return_op',
    str_contains($braFormSrc, 'name="return_to"')
        && str_contains($braFormSrc, 'name="return_op"')
        && str_contains($braFormSrc, 'href="<?= e($braBackUrl) ?>"')
);

// 2e. BraController::save() preserves returnQuery on redirect
$saveFnPos = strpos($braCtrlSrc, 'protected function save(): void');
$saveFnSnippet = $saveFnPos !== false ? substr($braCtrlSrc, $saveFnPos, 1100) : '';
assertCheck(
    'BraController::save() mempertahankan returnQuery saat redirect pasca-simpan',
    str_contains($saveFnSnippet, 'resolveReturnContext')
        && str_contains($saveFnSnippet, 'returnQuery')
);

echo "\n=== 3. UI HALAMAN PLOTING PETA (UNIFIED HEADER, FLOATING CONTROLS, COMPACT SIDEBAR) ===\n";

$navbarSrc = (string)file_get_contents($rootDir . '/views/layout/navbar.php');
$styleCssSrc = (string)file_get_contents($rootDir . '/assets/css/style.css');

// 3a. navbar.php skips generic header on route 'gis' while keeping <main class="page-body">
assertCheck(
    'views/layout/navbar.php meniadakan header ganda pada rute gis dan tetap membuka <main class="page-body">',
    str_contains($navbarSrc, "(\$currentRoute ?? '') !== 'gis'")
        && str_contains($navbarSrc, '<main class="page-body">')
);

// 3b. style.css sets height: 100vh !important on body.route-gis .page-body
$routeGisCssPos = strpos($styleCssSrc, 'body.route-gis .page-body');
$routeGisCssSnippet = $routeGisCssPos !== false ? substr($styleCssSrc, $routeGisCssPos, 300) : '';
assertCheck(
    'assets/css/style.css mengubah body.route-gis .page-body menjadi height: 100vh !important',
    str_contains($routeGisCssSnippet, 'height: 100vh !important;')
);

// 3c. views/gis.php renders unified header with logo, GIS RADAR, mobile menu btn, theme toggle, Misi Ops
$headerPos = strpos($gisViewSrc, 'id="gisUnifiedHeader"');
$headerEnd = $headerPos !== false ? strpos($gisViewSrc, '</header>', $headerPos) : false;
$headerSnippet = ($headerPos !== false && $headerEnd !== false) ? substr($gisViewSrc, $headerPos, $headerEnd - $headerPos + 9) : '';
assertCheck(
    'views/gis.php menyediakan header tunggal #gisUnifiedHeader berisi logo, GIS RADAR, #mobile-menu-btn, dan #theme-toggle-btn',
    $headerPos !== false
        && str_contains($headerSnippet, 'GIS RADAR OPERASI SAR KENDARI')
        && str_contains($headerSnippet, 'id="mobile-menu-btn"')
        && str_contains($headerSnippet, 'id="theme-toggle-btn"')
        && str_contains($headerSnippet, 'id="theme-icon"')
        && str_contains($headerSnippet, 'id="selectGisOperasi"')
        && !str_contains($headerSnippet, 'id="locationSearchBarWrapper"')
        && !str_contains($headerSnippet, 'id="gisGlobalUnitSwitcher"')
);

// 3d. views/gis.php renders floating circular controls dock inside #gisMapWrapper with ARIA vertical toolbar
$mapPos = strpos($gisViewSrc, 'id="gisMapWrapper"');
$dockPos = strpos($gisViewSrc, 'id="gisFloatingMapDock"');
$dockEnd = $dockPos !== false ? strpos($gisViewSrc, 'id="gisOfflineIndicator"', $dockPos) : false;
$dockSnippet = ($dockPos !== false && $dockEnd !== false) ? substr($gisViewSrc, $dockPos, $dockEnd - $dockPos) : '';
assertCheck(
    'views/gis.php menempatkan dock 3 tombol bulat melayang #gisFloatingMapDock (Pencarian, Jenis Peta, Unit) di dalam #gisMapWrapper',
    $mapPos !== false
        && $dockPos !== false
        && $dockPos > $mapPos
        && str_contains($dockSnippet, 'role="toolbar"')
        && str_contains($dockSnippet, 'aria-orientation="vertical"')
        && str_contains($dockSnippet, 'focusgroup="toolbar wrap block"')
        && str_contains($dockSnippet, 'id="btnFloatingSearchToggle"')
        && str_contains($dockSnippet, 'id="btnFloatingBasemapToggle"')
        && str_contains($dockSnippet, 'id="btnFloatingUnitToggle"')
        && str_contains($dockSnippet, 'id="floatingUnitBadge"')
        && str_contains($dockSnippet, 'id="locationSearchBarWrapper"')
        && str_contains($dockSnippet, 'id="gisGlobalUnitSwitcher"')
        && str_contains($dockSnippet, 'basemap-pill-btn')
);

// 3e. Sidebar tools simplified (compact width 360px, compact tab action bars, legacy badge strings intact)
assertCheck(
    'Sidebar Tools GIS dirampingkan (.gis-sidebar-panel, tombol tambah ringkas) dan menjaga string badge tes lama',
    str_contains($gisViewSrc, 'gis-sidebar-panel')
        && str_contains($gisViewSrc, '--gis-sidebar-w: 360px')
        && str_contains($gisViewSrc, 'Search Area (<span id="polygonsBadgeCount">')
        && str_contains($gisViewSrc, 'Pola SAR (<span id="patternsBadgeCount"><?= $initialPatternsCount ?></span>)')
        && str_contains($gisViewSrc, 'Jarak & ETA (<span id="routesBadgeCount"><?= $initialRoutesCount ?></span>)')
        && !str_contains($gisViewSrc, 'id="btnShowPointCreator" class="w-full py-2.5')
        && !str_contains($gisViewSrc, 'id="btnStartNewPolygon" class="w-full py-2.5')
        && !str_contains($gisViewSrc, 'id="btnStartNewPattern" class="w-full py-2.5')
        && !str_contains($gisViewSrc, 'id="btnStartNewRoute" class="w-full py-2.5')
);

// 3f. gis_master.js manages floating controls, roving tabindex fallback, Capture Mode dock hiding, and centralized sidebar collapse
assertCheck(
    'gis_master.js mengimplementasikan initFloatingMapControls(), setSidebarVisibility(), sinkronisasi #floatingUnitBadge, dan menyembunyikan dock saat Capture Mode',
    str_contains($gisJsSrc, 'function initFloatingMapControls()')
        && str_contains($gisJsSrc, 'function setSidebarVisibility(')
        && str_contains($gisJsSrc, 'gis-sidebar-collapsed')
        && !str_contains($gisJsSrc, '-ml-[390px]')
        && str_contains($gisJsSrc, 'floatingUnitBadge')
        && str_contains($gisJsSrc, 'gisFloatingMapDock')
        && str_contains($gisJsSrc, "'focusGroup' in HTMLElement.prototype")
);

echo "\n=== RINGKASAN: {$passCount} PASS, {$failCount} FAIL ===\n";
exit($failCount > 0 ? 1 : 0);
