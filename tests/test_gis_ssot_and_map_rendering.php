<?php
/**
 * QR-SAR - Automated Test Suite: GIS SSOT Integration & Map Rendering Perfection
 * Memverifikasi integrasi Ploting Peta sebagai SSOT di 4 modul:
 * 1. Penginputan Laporan Awal
 * 2. Interkoneksi Rencana Operasi (RENOPS)
 * 3. History Ploting pada Detail Operasi SAR
 * 4. Pembersihan Tuntas Fitur Cetak Peta (Zero Modal, Zero Button, Zero Dead Code)
 * Serta kualitas visual peta (Zoom In 22, Zoom Out 4, Bebas API Key, Zero Crash JS)
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    define('APP_NAME', 'QR-SAR Test');
    define('BASE_PATH', dirname(__DIR__));
    define('APP_VERSION', '3.8.0');
}

echo "\n====================================================================\n";
echo " TEST SUITE: GIS SSOT INTEGRASI MENYELURUH & MAP RENDERING PERFECTION\n";
echo "====================================================================\n\n";

$assertionsPassed = 0;
$assertionsFailed = 0;

function assertCondition(bool $condition, string $label, string $details = ''): void {
    global $assertionsPassed, $assertionsFailed;
    if ($condition) {
        $assertionsPassed++;
        echo "  [PASS] {$label}\n";
    } else {
        $assertionsFailed++;
        echo "  [FAIL] {$label}" . ($details ? " -> {$details}" : "") . "\n";
    }
}

// ---------------------------------------------------------
// [1] Standardisasi Tile Layers Bebas API Key & Zoom
// ---------------------------------------------------------
echo "[1] Verifikasi Tile Layers Bebas API Key & Zoom Boundaries...\n";

$gisMasterContent = file_get_contents(BASE_PATH . '/assets/js/gis_master.js');
$gisTacticalContent = file_get_contents(BASE_PATH . '/assets/js/gis.js');
$layersContent = file_get_contents(BASE_PATH . '/assets/js/gis/layers.js');
$opsFormContent = file_get_contents(BASE_PATH . '/assets/js/pages/operasi_form.js');
$opsFormMapContent = file_get_contents(BASE_PATH . '/assets/js/pages/operasi_form_map.js');

// 1.1 Multi-subdomain mt{s}.google.com
assertCondition(
    strpos($gisMasterContent, 'https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}') !== false,
    "gis_master.js menggunakan URL multi-subdomain mt{s}.google.com untuk Google Hybrid"
);
assertCondition(
    strpos($gisMasterContent, "'0', '1', '2', '3'") !== false,
    "gis_master.js mendefinisikan subdomains ['0', '1', '2', '3'] untuk load balancing"
);
assertCondition(
    strpos($gisMasterContent, "maxNativeZoom: 20") !== false && strpos($gisMasterContent, "maxZoom: 22") !== false,
    "gis_master.js menetapkan maxNativeZoom: 20 dan maxZoom: 22 (mencegah grey tiles saat zoom in 21-22)"
);
assertCondition(
    strpos($gisMasterContent, "minZoom: 4") !== false,
    "gis_master.js menetapkan minZoom: 4 (mencegah looping/wrapping tak terkontrol saat zoom out)"
);

// 1.2 Tactical GIS (Detail Operasi)
assertCondition(
    strpos($gisTacticalContent, "minZoom: 4") !== false && strpos($gisTacticalContent, "maxZoom: 22") !== false,
    "assets/js/gis.js menetapkan minZoom: 4 dan maxZoom: 22 pada TacticalGisMap"
);
assertCondition(
    strpos($gisTacticalContent, "https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}") !== false,
    "assets/js/gis.js menggunakan multi-subdomain mt{s}.google.com"
);
assertCondition(
    strpos($gisTacticalContent, "esriSatellite") !== false && strpos($gisTacticalContent, "osm") !== false,
    "assets/js/gis.js menyediakan opsi Esri Satellite dan OpenStreetMap bebas API key"
);

// 1.3 Form Operasi Laporan Awal
assertCondition(
    strpos($opsFormContent, "https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}") !== false,
    "operasi_form.js menggunakan multi-subdomain mt{s}.google.com"
);
assertCondition(
    strpos($opsFormContent, "minZoom: 4") !== false && strpos($opsFormContent, "maxZoom: 22") !== false,
    "operasi_form.js menetapkan minZoom: 4 dan maxZoom: 22 pada inlineMap dan modalMap"
);
assertCondition(
    strpos($opsFormMapContent, "https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}") !== false,
    "operasi_form_map.js menggunakan multi-subdomain mt{s}.google.com"
);

// ---------------------------------------------------------
// [2] Integrasi Modul 1: Penginputan Laporan Awal
// ---------------------------------------------------------
echo "\n[2] Verifikasi Integrasi Penginputan Laporan Awal...\n";

assertCondition(
    strpos($opsFormContent, "manualRouteHidden.value = JSON.stringify(pts)") !== false,
    "operasi_form.js mengisi input manual_route_json dengan rute titik waypoints"
);
assertCondition(
    strpos($opsFormContent, "tacticalRouteHidden.value = JSON.stringify") !== false,
    "operasi_form.js mengisi input tactical_route_data dengan metadata lengkap"
);
assertCondition(
    strpos($opsFormContent, "oLatIn.value = tacticalState.originLat") !== false,
    "operasi_form.js menyinkronkan origin_lat, origin_lng, dan origin_name"
);

$gisModelContent = file_get_contents(BASE_PATH . '/models/GisModel.php');
assertCondition(
    strpos($gisModelContent, "function syncOperasiLkpPoint") !== false,
    "GisModel memiliki fungsi syncOperasiLkpPoint untuk otomatisasi pin LKP"
);
assertCondition(
    strpos($gisModelContent, "function syncOperasiRoute") !== false,
    "GisModel memiliki fungsi syncOperasiRoute untuk persistensi jalur laut ke GisRoute"
);

// ---------------------------------------------------------
// [3] Integrasi Modul 2 & 3: Rencana Operasi (RENOPS) & Detail Ops
// ---------------------------------------------------------
echo "\n[3] Verifikasi Interkoneksi RENOPS & Detail Operasi...\n";

$renopsFormContent = file_get_contents(BASE_PATH . '/assets/js/pages/renops_form.js');
assertCondition(
    strpos($renopsFormContent, "poly.shapeType === 'circle'") !== false,
    "renops_form.js getPolygonCoordsText memformat koordinat datum LKP & radius untuk Search Area Circle"
);
assertCondition(
    strpos($renopsFormContent, "poly.shapeType === 'rectangle'") !== false,
    "renops_form.js getPolygonCoordsText memiliki fallback kalkulasi 4 sudut untuk Search Area Rectangle"
);
assertCondition(
    strpos($renopsFormContent, "importAllPolygonsToSrus") !== false,
    "renops_form.js mengonversi seluruh Search Area ke tabel SRU RENOPS secara otomatis"
);

$tabGisContent = file_get_contents(BASE_PATH . '/views/operasi/partials/tab_gis.php');
assertCondition(
    strpos($tabGisContent, "Cetak Lembar Peta") === false,
    "views/operasi/partials/tab_gis.php telah bersih dari tombol Cetak Lembar Peta Taktis"
);

// ---------------------------------------------------------
// [4] Verifikasi Pembersihan Fitur Cetak Peta
// ---------------------------------------------------------
echo "\n[4] Verifikasi Pembersihan Fitur Cetak Peta (Frontend, Backend & Modal)...\n";

$tabCetakContent = file_get_contents(BASE_PATH . '/views/operasi/partials/tab_cetak.php');
assertCondition(
    strpos($tabCetakContent, "5. Peta Plotting Taktis Operasi") === false && strpos($tabCetakContent, "Cetak Peta Taktis") === false,
    "views/operasi/partials/tab_cetak.php telah menghapus Card 5 Cetak Peta Plotting Taktis"
);
assertCondition(
    strpos($tabCetakContent, "print=1") === false,
    "views/operasi/partials/tab_cetak.php tidak lagi merujuk ke parameter print=1"
);
assertCondition(
    strpos($tabCetakContent, "xl:grid-cols-4") !== false,
    "views/operasi/partials/tab_cetak.php menggunakan grid 4 kolom proporsional untuk 4 dokumen utama"
);

$gisViewContent = file_get_contents(BASE_PATH . '/views/gis.php');
assertCondition(
    strpos($gisViewContent, 'id="printMapModal"') === false && strpos($gisViewContent, 'id="btnOpenPrintModal"') === false,
    "views/gis.php bersih dari modal cetak (#printMapModal) dan tombol navbar (#btnOpenPrintModal)"
);

assertCondition(
    strpos($gisMasterContent, "initPrintModalUI") === false && strpos($gisMasterContent, "openPrintMapModal") === false,
    "gis_master.js bersih dari inisialisasi dan fungsi pemanggilan modal cetak peta"
);
assertCondition(
    strpos($gisMasterContent, "printPreviewMap") === false,
    "gis_master.js bersih dari instance dan objek Leaflet cetak snapshot (printPreviewMap)"
);

// ---------------------------------------------------------
// REKAPITULASI HASIL
// ---------------------------------------------------------
echo "\n====================================================================\n";
echo " TEST SUITE SUMMARY\n";
echo " Total Assertions : " . ($assertionsPassed + $assertionsFailed) . "\n";
echo " Passed           : {$assertionsPassed}\n";
echo " Failed           : {$assertionsFailed}\n";
$rate = round(($assertionsPassed / max(1, $assertionsPassed + $assertionsFailed)) * 100, 2);
echo " Success Rate     : {$rate}%\n";
echo "====================================================================\n\n";

if ($assertionsFailed > 0) {
    exit(1);
}
exit(0);
