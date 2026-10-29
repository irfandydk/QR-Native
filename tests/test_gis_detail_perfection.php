<?php
/**
 * QR-SAR - Automated Test Suite: GIS Tactical Map & Detail Operasi Perfection
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
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

echo "\n--- PENGUJIAN INTEGRITAS PETA TAKTIS GIS HALAMAN DETAIL OPERASI ---\n";

// 1. Uji Kontrak Controller operasi_detail.php
echo "\n1. Memeriksa Controller operasi_detail.php:\n";
$controllerContent = file_get_contents(__DIR__ . '/../operasi_detail.php');
assertCondition("Controller memuat useGis => true", str_contains($controllerContent, "'useGis' => true"));
assertCondition("Controller memuat route => 'operasi_detail'", str_contains($controllerContent, "'route' => 'operasi_detail'"));
assertCondition("Controller memanggil GisModel::getOperationBundle()", str_contains($controllerContent, 'GisModel::getOperationBundle'));

// 2. Uji Integritas View Detail Operasi (Bebas PHP Warning & Valid JSON Config)
$detailViewContent = file_get_contents(__DIR__ . '/../views/operasi/detail.php');
foreach (glob(__DIR__ . '/../views/operasi/partials/*.php') as $p) {
    $detailViewContent .= "\n" . file_get_contents($p);
}
assertCondition("Tidak ada referensi undefined \$renopsList", !str_contains($detailViewContent, '$renopsList'));
assertCondition("Tidak ada referensi undefined \$laporanList", !str_contains($detailViewContent, '$laporanList'));
assertCondition("Menggunakan \$operasi['renops'] ?? []", str_contains($detailViewContent, "'renops' => \$operasi['renops'] ?? []"));
assertCondition("Menggunakan \$operasi['laporan_harian'] ?? []", str_contains($detailViewContent, "'laporan' => \$operasi['laporan_harian'] ?? []"));
assertCondition("Kontainer peta #map-detail-ops tersedia", str_contains($detailViewContent, 'id="map-detail-ops"'));
assertCondition("Tab GIS #tab-content-gis tersedia", str_contains($detailViewContent, 'id="tab-content-gis"'));

// 3. Uji Rendering HTML Lengkap & Pemuatan Aset Leaflet
echo "\n3. Memeriksa Rendering HTML Lengkap Halaman Detail Operasi:\n";
$testOps = OperasiModel::getAll();
$testId = !empty($testOps) ? (int)$testOps[0]['id_operasi'] : 1;
$dummyOps = OperasiModel::getById($testId) ?: [
    'id_operasi' => $testId,
    'nama_operasi' => 'Operasi SAR Uji Coba KPP Kendari',
    'status' => 'OPEN',
    'waktu_kejadian' => date('Y-m-d H:i:s'),
    'waktu_lapor' => date('Y-m-d H:i:s'),
    'waktu_berangkat' => date('Y-m-d H:i:s'),
    'koordinat' => '04° 02\' 16" S - 122° 29\' 12" E',
    'lokasi_kejadian' => 'Perairan Kendari',
    'jarak' => '12.5 Nm',
    'radial' => '135',
    'renops' => [],
    'laporan_harian' => [],
    'korban' => [],
    'detail_unsur' => [],
    'detail_alut' => []
];
$dummyBundle = GisModel::getOperationBundle($testId, true);

ob_start();
renderView('operasi/detail', [
    'id' => $testId,
    'operasi' => $dummyOps,
    'gisBundle' => $dummyBundle
], [
    'title' => 'Operasi: ' . htmlspecialchars($dummyOps['nama_operasi']),
    'route' => 'operasi_detail',
    'useGis' => true,
    'navbarTitle' => 'PUSAT KENDALI OPERASI SAR',
    'navbarSubtitle' => 'KANTOR PENCARIAN DAN PERTOLONGAN (KPP) KELAS A KENDARI - BASARNAS'
]);
$renderedHtml = ob_get_clean();

assertCondition("Header memuat Leaflet CSS (leaflet.min.css)", str_contains($renderedHtml, 'assets/vendor/leaflet/leaflet.min.css'));
assertCondition("Footer memuat Leaflet JS (leaflet.min.js)", str_contains($renderedHtml, 'assets/vendor/leaflet/leaflet.min.js'));
assertCondition("Footer memuat Engine GIS (assets/js/gis.js)", str_contains($renderedHtml, 'assets/js/gis.js'));
assertCondition("Footer memuat QrSarUtils (assets/js/utils/common.js)", str_contains($renderedHtml, 'assets/js/utils/common.js'));
assertCondition("Blok config window.__OPS_DETAIL_CONFIG__ tidak tercemar warning", !str_contains($renderedHtml, 'Warning: Undefined'));
assertCondition("window.__OPS_DETAIL_CONFIG__ berisikan JSON yang valid", str_contains($renderedHtml, 'window.__OPS_DETAIL_CONFIG__ = {"idOperasi":' . $testId));

// 4. Uji Modul JavaScript assets/js/pages/operasi_detail.js
echo "\n4. Memeriksa Skrip assets/js/pages/operasi_detail.js:\n";
$jsContent = file_get_contents(__DIR__ . '/../assets/js/pages/operasi_detail.js');
assertCondition("Memiliki Dependency Guard untuk TacticalGisMap & Leaflet L", str_contains($jsContent, "typeof TacticalGisMap === 'undefined' || typeof L === 'undefined'"));
assertCondition("Memiliki Fallback Koordinat Kansar Kendari (-4.037694, 122.486694)", str_contains($jsContent, '-4.037694') && str_contains($jsContent, '122.486694'));
assertCondition("Memanggil invalidateSize() pada transisi tab", str_contains($jsContent, 'detailOpsMap.map.invalidateSize()'));
assertCondition("Memanggil fitAllComponents() untuk auto-fit", str_contains($jsContent, 'detailOpsMap.fitAllComponents()'));
assertCondition("switchTab('gis') memanggil initDetailMap()", str_contains($jsContent, "if (resolvedTab === 'gis')"));

// 5. Uji Engine GIS assets/js/gis.js
echo "\n5. Memeriksa Engine GIS assets/js/gis.js:\n";
$gisEngineContent = file_get_contents(__DIR__ . '/../assets/js/gis.js');
assertCondition("Class TacticalGisMap terdefinisi", str_contains($gisEngineContent, 'class TacticalGisMap'));
assertCondition("Mendukung switchBaseLayer (hybrid, satellite, roadmap, terrain)", str_contains($gisEngineContent, 'switchBaseLayer(layerKey)'));
assertCondition("Mendukung addLkpMarker", str_contains($gisEngineContent, 'addLkpMarker('));
assertCondition("Mendukung loadOperationBundle", str_contains($gisEngineContent, 'loadOperationBundle('));
assertCondition("Mendukung focusComponent", str_contains($gisEngineContent, 'focusComponent('));
assertCondition("Mendukung fitAllComponents", str_contains($gisEngineContent, 'fitAllComponents()'));

echo "\n--- HASIL: {$passedAssertions} / {$totalAssertions} Pengujian Berhasil Lulus ---\n";
if ($passedAssertions !== $totalAssertions) {
    exit(1);
}
