<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/RenopsModel.php';
require_once __DIR__ . '/../models/MasterModel.php';
require_once __DIR__ . '/../models/GisModel.php';
require_once __DIR__ . '/../helpers/TemplateHelper.php';
require_once __DIR__ . '/../services/WhatsAppTemplateService.php';

echo "=== TEST RENOPS MANUAL SRU & POLA PENCARIAN FLOW ===\n\n";

$db = getDB();
$dbName = $db->query("SELECT DATABASE()")->fetchColumn();
if (!str_ends_with((string)$dbName, '_test')) {
    echo "Test dibatalkan: database aktif '{$dbName}' bukan database test (nama harus berakhiran _test).\n";
    exit(1);
}

$passCount = 0;
function assertTrue(bool $condition, string $msg): void {
    global $passCount;
    if (!$condition) {
        echo " [FAIL] {$msg}\n";
        exit(1);
    }
    echo " [PASS] {$msg}\n";
    $passCount++;
}

// 1. Verifikasi kode OperasiController.php tidak memaksakan 'Parallel Track Search'
$controllerCode = file_get_contents(__DIR__ . '/../controllers/OperasiController.php');
assertTrue(!str_contains($controllerCode, "'pola_pencarian' => 'Parallel Track Search'"), "OperasiController tidak lagi memuat hardcoded initialSrus pola_pencarian Parallel Track Search");
assertTrue(str_contains($controllerCode, "'pola_pencarian' => ''"), "OperasiController menginisialisasi initialSrus pola_pencarian dengan string kosong ('')");
assertTrue(str_contains($controllerCode, '$pola = \'\';'), "OperasiController menginisialisasi \$pola dengan string kosong ('') sebelum matching search pattern");

// 2. Verifikasi kode RenopsModel.php tidak fallback ke 'Parallel Track Search'
$modelCode = file_get_contents(__DIR__ . '/../models/RenopsModel.php');
assertTrue(!str_contains($modelCode, "?? 'Parallel Track Search'"), "RenopsModel::create() & update() tidak lagi memiliki fallback ke 'Parallel Track Search'");

// 3. Verifikasi pembuatan RENOPS dengan pola_pencarian kosong tersimpan persisten sebagai '' di database
$testNoOps = 'TEST-OPS-MANUAL-' . time();
$idOps = OperasiModel::create([
    'no_registrasi' => $testNoOps,
    'nama_operasi' => 'Operasi Uji SRU Manual',
    'status' => 'OPEN',
    'tanggal_mulai' => date('Y-m-d H:i:s'),
    'koordinat' => '03° 58\' 45.00" S - 122° 35\' 20.00" E',
    'lokasi_kejadian' => 'Perairan Toronipa'
]);
assertTrue($idOps > 0, "Operasi test berhasil dibuat dengan ID {$idOps}");

$idRenops = RenopsModel::create([
    'id_operasi' => $idOps,
    'hari_ke' => 'H.1',
    'tanggal_laporan' => date('Y-m-d'),
    'waktu_briefing' => date('Y-m-d H:i:s'),
    'waktu_mulai_ops' => date('Y-m-d H:i:s'),
    'cuaca' => 'Cerah',
    'satuan_jarak' => 'NM',
    'srus' => [
        [
            'nama_sru' => 'SRU 1 Darat / Pesisir',
            'koordinat_area' => 'Penyisiran visual garis pantai Toronipa ke arah Timur sejauh 5 KM',
            'luas_area' => '',
            'pola_pencarian' => '', // Murni manual, tanpa pola
            'total_sapuan' => ''
        ],
        [
            'nama_sru' => 'SRU 2 Perahu Karet',
            'koordinat_area' => 'Penyisiran alur laut dangkal',
            'luas_area' => '2 NM²',
            'pola_pencarian' => 'Creeping Line Search', // Pola dipilih manual
            'total_sapuan' => '4 Sapuan'
        ]
    ]
]);
assertTrue($idRenops > 0, "RENOPS berhasil dibuat dengan ID {$idRenops}");

// Periksa record di tabel RenopsSRU
$stmtCheck = $db->prepare("SELECT nama_sru, pola_pencarian, luas_area FROM RenopsSRU WHERE id_renops = :rid ORDER BY id_sru ASC");
$stmtCheck->execute([':rid' => $idRenops]);
$srusFromDb = $stmtCheck->fetchAll(PDO::FETCH_ASSOC);

assertTrue(count($srusFromDb) === 2, "Tersimpan tepat 2 SRU di tabel RenopsSRU");
assertTrue($srusFromDb[0]['pola_pencarian'] === '', "SRU 1 pola_pencarian tersimpan persisten sebagai string kosong (''), bukan Parallel Track Search");
assertTrue($srusFromDb[1]['pola_pencarian'] === 'Creeping Line Search', "SRU 2 pola_pencarian tersimpan sesuai pilihan ('Creeping Line Search')");

// 4. Verifikasi update RENOPS tetap mempertahankan string kosong saat diupdate
$okUpdate = RenopsModel::update($idRenops, [
    'hari_ke' => 'H.1',
    'tanggal_laporan' => date('Y-m-d'),
    'waktu_briefing' => date('Y-m-d H:i:s'),
    'waktu_mulai_ops' => date('Y-m-d H:i:s'),
    'cuaca' => 'Berawan',
    'srus' => [
        [
            'nama_sru' => 'SRU 1 Darat / Pesisir',
            'koordinat_area' => 'Penyisiran diperluas ke Tanjung Toronipa',
            'luas_area' => '',
            'pola_pencarian' => '', // Tetap kosong
            'total_sapuan' => ''
        ]
    ]
]);
assertTrue($okUpdate === true, "RenopsModel::update() berhasil dijalankan");

$stmtCheck2 = $db->prepare("SELECT pola_pencarian FROM RenopsSRU WHERE id_renops = :rid AND nama_sru LIKE '%SRU 1%'");
$stmtCheck2->execute([':rid' => $idRenops]);
$polaAfterUpdate = $stmtCheck2->fetchColumn();
assertTrue($polaAfterUpdate === '', "SRU 1 setelah update tetap memiliki pola_pencarian kosong ('')");

// 5. Verifikasi integrasi JavaScript di renops_form.js via Node.js
$nodeScript = <<< 'NODE'
const fs = require('fs');
const code = fs.readFileSync('assets/js/pages/renops_form.js', 'utf8');

// Mock browser environment
let renderedHtml = '';
global.window = {
  addEventListener: () => {},
  __RENOPS_PAGE_CONFIG__: {
    initialSrus: [
      { nama_sru: 'SRU 1', luas_area: '10 NM²', pola_pencarian: 'Parallel Track Search', total_sapuan: '6 Sapuan', id_polygon: 'poly1' }
    ]
  }
};
global.document = {
  getElementById: (id) => {
    if (id === 'sru-container') {
      return {
        set innerHTML(val) { renderedHtml = val; },
        get innerHTML() { return renderedHtml; },
        appendChild: (child) => { renderedHtml += child.innerHTML; }
      };
    }
    return null;
  },
  createElement: () => ({ innerHTML: '', className: '', appendChild: () => {} }),
  querySelector: () => null,
  querySelectorAll: () => [],
  addEventListener: () => {}
};

// Check syntax and execution
eval(code);

if (typeof applySektorToPlotting !== 'function') throw new Error('applySektorToPlotting not found');
if (typeof setSruManualPola !== 'function') throw new Error('setSruManualPola not found');
if (typeof setSruManualLuas !== 'function') throw new Error('setSruManualLuas not found');
if (typeof setSruManualSapuan !== 'function') throw new Error('setSruManualSapuan not found');

// Test unlinking GIS sector
applySektorToPlotting(0, '');

const srus = window.RENOPS_FORM.getCurrentSrus();
if (srus[0].id_polygon !== 'manual') throw new Error('id_polygon should be manual when polyId is empty');
if (srus[0].luas_area !== '') throw new Error('luas_area should be empty when polyId is empty');
if (srus[0].pola_pencarian !== '') throw new Error('pola_pencarian should be empty when polyId is empty');
if (srus[0].total_sapuan !== '') throw new Error('total_sapuan should be empty when polyId is empty');

// Test manual pattern selection
setSruManualPola(0, 'Creeping Line Search');
if (srus[0].pola_pencarian !== 'Creeping Line Search') throw new Error('Manual pola was not set');

console.log("NODE_OK");
NODE;

$nodeTmp = sys_get_temp_dir() . '/test_renops_manual_js.js';
file_put_contents($nodeTmp, $nodeScript);
$nodeOutput = trim((string)shell_exec("node {$nodeTmp}"));
@unlink($nodeTmp);

assertTrue($nodeOutput === 'NODE_OK', "Eksekusi JavaScript renops_form.js (applySektorToPlotting & setSruManualPola) lulus 100%");

// Cleanup database
$db->prepare("DELETE FROM RenopsSRU WHERE id_renops = :rid")->execute([':rid' => $idRenops]);
$db->prepare("DELETE FROM Renops WHERE id_renops = :rid")->execute([':rid' => $idRenops]);
$db->prepare("DELETE FROM Operasi WHERE id_operasi = :id")->execute([':id' => $idOps]);

echo "\nHasil: {$passCount}/{$passCount} lulus.\n";
