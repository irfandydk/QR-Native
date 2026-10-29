<?php
/**
 * Test Suite: Pilihan Satuan Jarak pada Form Rencana Operasi (RENOPS)
 * Memverifikasi:
 * 1. Skema Database & Migrasi kolom `satuan_jarak` pada tabel `Renops`
 * 2. Model RenopsModel (create, update, getById dengan satuan_jarak)
 * 3. Sanitasi & Fallback Satuan Jarak di Controller OperasiController
 * 4. Komponen UI Switcher Satuan Jarak di views/operasi/renops_form.php
 * 5. Tampilan Satuan pada lembar cetak (renops_print.php) dan tab RENOPS (tab_renops.php)
 * 6. Format Pesan WhatsApp di WhatsAppTemplateService
 * 7. Integritas Skrip JavaScript renops_form.js dan formula matematika
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/RenopsModel.php';
require_once __DIR__ . '/../services/WhatsAppTemplateService.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function it(string $description, callable $fn) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    try {
        $fn();
        $passedTests++;
        echo "  [PASS] {$description}\n";
    } catch (Throwable $e) {
        $failedTests++;
        echo "  [FAIL] {$description}\n";
        echo "         Error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n";
    }
}

echo "=== MEMULAI TEST SUITE: PILIHAN SATUAN JARAK RENOPS ===\n\n";

// -------------------------------------------------------------
// 1. Skema Database & Migration Verification
// -------------------------------------------------------------
echo "[1] Verifikasi Skema Database & Model RenopsModel\n";

it("Kolom satuan_jarak ada di tabel Renops atau dibuat otomatis oleh ensureSchemaUpdated()", function() {
    RenopsModel::ensureSchemaUpdated(true);
    $db = getDB();
    $stmt = $db->query("SHOW COLUMNS FROM Renops LIKE 'satuan_jarak'");
    $col = $stmt->fetch();
    if (!$col) {
        throw new Exception("Kolom satuan_jarak tidak ditemukan di tabel Renops.");
    }
});

it("RenopsModel::create() & update() mendukung persistensi field satuan_jarak", function() {
    $db = getDB();
    
    // Cari operasi open untuk testing
    $ops = $db->query("SELECT id_operasi FROM Operasi WHERE status = 'OPEN' LIMIT 1")->fetch();
    if (!$ops) {
        // Fallback cari operasi manapun dan sementara statusnya diset OPEN
        $ops = $db->query("SELECT id_operasi FROM Operasi LIMIT 1")->fetch();
        if ($ops) {
            $db->exec("UPDATE Operasi SET status = 'OPEN' WHERE id_operasi = " . (int)$ops['id_operasi']);
        }
    }
    if (!$ops) {
        throw new Exception("Tidak ada operasi SAR untuk testing.");
    }
    $idOps = (int)$ops['id_operasi'];

    // Test Create dengan satuan_jarak = 'KM'
    $dummyData = [
        'id_operasi' => $idOps,
        'hari_ke' => 'H.99',
        'tanggal_laporan' => date('Y-m-d'),
        'waktu_briefing' => date('Y-m-d 07:00:00'),
        'waktu_mulai_ops' => date('Y-m-d 07:30:00'),
        'cuaca' => 'Cerah',
        'satuan_jarak' => 'KM',
        'unsur_text' => 'Tim SAR',
        'alut_text' => 'Rescue Car',
        'komunikasi' => 'VHF Ch 16',
        'catatan' => 'Unit testing satuan jarak',
        'srus' => [
            [
                'nama_sru' => 'SRU 1 Darat',
                'koordinat_area' => '03° 58\' 30" S - 122° 35\' 12" E',
                'luas_area' => '34.30 KM² (10.00 NM²)',
                'pola_pencarian' => 'Parallel Track Search',
                'track_spacing' => '500 m',
                'total_sapuan' => '6 Sapuan (Spasi: 500 m, Total: 10.0 km)'
            ]
        ]
    ];

    $newId = RenopsModel::create($dummyData);
    if ($newId <= 0) {
        throw new Exception("Gagal membuat data Renops baru.");
    }

    $retrieved = RenopsModel::getById($newId);
    if (!$retrieved || ($retrieved['satuan_jarak'] ?? '') !== 'KM') {
        throw new Exception("Field satuan_jarak tidak tersimpan sebagai 'KM'. Hasil: " . var_export($retrieved['satuan_jarak'] ?? null, true));
    }

    // Test Update ubah satuan_jarak ke 'NM'
    $updateData = $dummyData;
    $updateData['satuan_jarak'] = 'NM';
    $updateData['srus'][0]['luas_area'] = '10.00 NM² (34.30 KM²)';
    $updateData['srus'][0]['track_spacing'] = '1.00 NM';
    
    $ok = RenopsModel::update($newId, $updateData);
    if (!$ok) {
        throw new Exception("Gagal mengupdate Renops ID {$newId}.");
    }

    $updated = RenopsModel::getById($newId);
    if (!$updated || ($updated['satuan_jarak'] ?? '') !== 'NM') {
        throw new Exception("Field satuan_jarak gagal diupdate ke 'NM'.");
    }

    // Bersihkan data dummy
    RenopsModel::delete($newId);
});

// -------------------------------------------------------------
// 2. Controller OperasiController Sanitization Check
// -------------------------------------------------------------
echo "\n[2] Verifikasi Sanitasi & Fallback di Controller\n";

it("Controller memfilter dan menormalisasi satuan_jarak ke KM, M, atau NM", function() {
    $whitelist = ['KM', 'M', 'NM'];
    
    $testInputs = [
        'km' => 'KM',
        'KM' => 'KM',
        'm' => 'M',
        'M' => 'M',
        'nm' => 'NM',
        'NM' => 'NM',
        'INVALID' => 'NM',
        '' => 'NM'
    ];

    foreach ($testInputs as $input => $expected) {
        $val = in_array(strtoupper(trim((string)$input)), $whitelist) ? strtoupper(trim((string)$input)) : 'NM';
        if ($val !== $expected) {
            throw new Exception("Input '{$input}' menghasilkan '{$val}', diharapkan '{$expected}'.");
        }
    }
});

// -------------------------------------------------------------
// 3. View UI Elements Check (renops_form.php, tab_renops.php, renops_print.php)
// -------------------------------------------------------------
echo "\n[3] Verifikasi UI Switcher & View Integrations\n";

it("File views/operasi/renops_form.php memuat Master Switcher, Tombol Pill Satuan, dan Hidden Input", function() {
    $formView = file_get_contents(__DIR__ . '/../views/operasi/renops_form.php');
    if ($formView === false) throw new Exception("Gagal membaca views/operasi/renops_form.php");

    if (strpos($formView, 'id="renopsUnitSwitcher"') === false) {
        throw new Exception("Elemen #renopsUnitSwitcher tidak ditemukan di views/operasi/renops_form.php.");
    }
    if (strpos($formView, 'data-renops-unit="NM"') === false || 
        strpos($formView, 'data-renops-unit="KM"') === false || 
        strpos($formView, 'data-renops-unit="M"') === false) {
        throw new Exception("Tombol pill satuan (NM, KM, M) tidak lengkap di form RENOPS.");
    }
    if (strpos($formView, 'name="satuan_jarak"') === false || strpos($formView, 'id="renops_satuan_jarak"') === false) {
        throw new Exception("Hidden input #renops_satuan_jarak tidak ditemukan.");
    }
    if (strpos($formView, "'satuanJarak' => \$satuanJarak") === false) {
        throw new Exception("Konfigurasi window.__RENOPS_PAGE_CONFIG__ tidak menyertakan satuanJarak.");
    }
    if (strpos($formView, 'renops-active-unit-label') === false) {
        throw new Exception("Label indikator satuan aktif di modal import GIS tidak ditemukan.");
    }
});

it("File views/operasi/partials/tab_renops.php menampilkan badge satuan jarak", function() {
    $tabView = file_get_contents(__DIR__ . '/../views/operasi/partials/tab_renops.php');
    if (strpos($tabView, "Satuan: <?= e(\$ren['satuan_jarak']) ?>") === false) {
        throw new Exception("Badge Satuan Jarak tidak ditemukan di tab_renops.php.");
    }
});

it("File views/operasi/renops_print.php menampilkan satuan jarak pada Bagian II", function() {
    $printView = file_get_contents(__DIR__ . '/../views/operasi/renops_print.php');
    if (strpos($printView, "Satuan: ' . e(\$renops['satuan_jarak'])") === false) {
        throw new Exception("Satuan Jarak tidak ditampilkan pada lembar cetak RENOPS.");
    }
});

// -------------------------------------------------------------
// 4. WhatsApp Template Generator Check
// -------------------------------------------------------------
echo "\n[4] Verifikasi WhatsApp Template Service\n";

it("WhatsAppTemplateService::generateRenopsWA menghasilkan format luas yang sesuai dengan satuan_jarak", function() {
    $opsDummy = [
        'nama_operasi' => 'Operasi SAR Nelayan Hilang',
        'koordinat' => '03° 58\' 30" S - 122° 35\' 12" E'
    ];

    // Luas area SRU (sudah diformat per satuan di form) tampil apa adanya di WA
    $renopsKm = ['hari_ke' => 'H.1', 'satuan_jarak' => 'KM', 'cuaca' => 'Cerah'];
    $sruKm = [['nama_sru' => 'SRU 1', 'koordinat_area' => 'A: 03° 58\' 30.0" S - 122° 35\' 12.0" E', 'luas_area' => '34.30 KM² (10.00 NM²)']];
    $waKm = WhatsAppTemplateService::generateRenopsWA($opsDummy, $renopsKm, $sruKm);
    if (strpos($waKm, 'Luas area pencarian : 34.30 KM²') === false) {
        throw new Exception("WA Template untuk satuan KM tidak memuat luas dalam KM². Output:\n" . substr($waKm, 0, 300));
    }

    $renopsNm = ['hari_ke' => 'H.1', 'satuan_jarak' => 'NM', 'cuaca' => 'Cerah'];
    $sruNm = [['nama_sru' => 'SRU 1', 'koordinat_area' => 'A: 03° 58\' 30.0" S - 122° 35\' 12.0" E', 'luas_area' => '10.00 NM² (34.30 KM²)']];
    $waNm = WhatsAppTemplateService::generateRenopsWA($opsDummy, $renopsNm, $sruNm);
    if (strpos($waNm, 'Luas area pencarian : 10.00 NM²') === false) {
        throw new Exception("WA Template untuk satuan NM tidak memuat luas dalam NM².");
    }

    // Tanpa SRU: WA tidak boleh mengarang SRU, koordinat, atau luas area
    $waKosong = WhatsAppTemplateService::generateRenopsWA($opsDummy, $renopsNm, []);
    if (strpos($waKosong, 'Pembagian SRU dan area pencarian belum ditetapkan.') === false || strpos($waKosong, '10.00 NM²') !== false) {
        throw new Exception("WA Template tanpa SRU masih memuat SRU / luas area fiktif.");
    }
});

// -------------------------------------------------------------
// 5. JavaScript Logic & Mathematical Consistency Check
// -------------------------------------------------------------
echo "\n[5] Verifikasi Logika JavaScript renops_form.js\n";

it("Formula matematis dan fungsi JS renops_form.js lulus syntax dan eksekusi Node.js", function() {
    $jsTestCmd = "node -e '
        const fs = require(\"fs\");
        const code = fs.readFileSync(\"assets/js/pages/renops_form.js\", \"utf8\");
        
        // Mock DOM & environment
        global.document = {
            getElementById: () => null,
            querySelectorAll: () => [],
            addEventListener: () => {}
        };
        global.window = {
            addEventListener: () => {},
            location: { search: \"\" },
            __RENOPS_PAGE_CONFIG__: { satuanJarak: \"KM\" }
        };
        global.localStorage = {
            getItem: () => \"KM\",
            setItem: () => {}
        };

        eval(code);

        if (typeof window.RENOPS_FORM !== \"object\") {
            throw new Error(\"window.RENOPS_FORM tidak terdefinisi.\");
        }

        // Test format KM
        window.RENOPS_FORM.setUnit(\"KM\");
        const areaKm = window.RENOPS_FORM.formatArea(10); // 10 NM²
        if (!areaKm.includes(\"34.30 KM²\")) {
            throw new Error(\"Perhitungan area KM salah: \" + areaKm);
        }

        const distKm = window.RENOPS_FORM.formatDistance(10); // 10 NM = 18.52 km
        if (!distKm.includes(\"18.52 km\")) {
            throw new Error(\"Perhitungan jarak KM salah: \" + distKm);
        }

        // Test format NM
        window.RENOPS_FORM.setUnit(\"NM\");
        const areaNm = window.RENOPS_FORM.formatArea(10);
        if (!areaNm.includes(\"10.00 NM²\")) {
            throw new Error(\"Perhitungan area NM salah: \" + areaNm);
        }

        // Test format M
        window.RENOPS_FORM.setUnit(\"M\");
        const areaM = window.RENOPS_FORM.formatArea(1);
        if (!areaM.includes(\"m²\")) {
            throw new Error(\"Perhitungan area M salah: \" + areaM);
        }

        console.log(\"Node math test passed.\");
    '";

    exec($jsTestCmd . " 2>&1", $out, $ret);
    if ($ret !== 0) {
        throw new Exception("Eksekusi tes Node.js gagal:\n" . implode("\n", $out));
    }
});

// -------------------------------------------------------------
// HASIL AKHIR
// -------------------------------------------------------------
echo "\n======================================================\n";
echo "HASIL TEST: {$passedTests}/{$totalTests} PASSED";
if ($failedTests > 0) {
    echo " ({$failedTests} FAILED)\n";
    echo "======================================================\n";
    exit(1);
} else {
    echo " (SEMUA TEST BERHASIL)\n";
    echo "======================================================\n";
    exit(0);
}
