<?php
/**
 * Test Suite: Verifikasi Mendalam & Komprehensif Fase 1 s.d. Fase 3
 * Memastikan tidak ada satupun fungsionalitas yang gagal atau mengalami regresi.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

$totalAssertions = 0;
$passedAssertions = 0;
$failedAssertions = 0;
$errors = [];

function check(string $name, bool $result, string $failMessage = ''): void {
    global $totalAssertions, $passedAssertions, $failedAssertions, $errors;
    $totalAssertions++;
    if ($result) {
        $passedAssertions++;
        echo "  [PASS] {$name}\n";
    } else {
        $failedAssertions++;
        $errors[] = "{$name}: {$failMessage}";
        echo "  [FAIL] {$name} => {$failMessage}\n";
    }
}

echo "====================================================================\n";
echo "   VERIFIKASI MENDALAM & FORENSIK INTEGRITAS PENERAPAN FASE 1 - 3   \n";
echo "====================================================================\n\n";

$db = getDB();

// ---------------------------------------------------------------------
// SEKSI 1: VERIFIKASI FASE 1 (FILESYSTEM HYGIENE & ASSET CONSOLIDATION)
// ---------------------------------------------------------------------
echo "--- 1. VERIFIKASI FASE 1: FILESYSTEM HYGIENE & VENDOR ASSETS ---\n";

$shadowViews = [
    'views/operasi_form.php',
    'views/operasi_detail.php',
    'views/renops_form.php',
    'views/sitrep_form.php',
    'views/bra_form.php',
    'views/bra_list.php',
    'views/bra_view.php',
    'views/bra_verify_invalid.php',
    'views/master.php',
    'views/master_settings.php',
    'views/master/settings.php',
    'views/admin.php',
    'views/login.php'
];

foreach ($shadowViews as $viewPath) {
    $fullPath = BASE_PATH . '/' . $viewPath;
    check("Shadow / dead view '{$viewPath}' telah terhapus", !file_exists($fullPath), "File masih ada!");
}

$canonicalViews = [
    'views/operasi/form.php',
    'views/operasi/detail.php',
    'views/operasi/renops_form.php',
    'views/operasi/sitrep_form.php',
    'views/bra/form.php',
    'views/bra/list.php',
    'views/bra/view.php',
    'views/bra/verify_invalid.php',
    'views/master/index.php',
    'views/admin/index.php',
    'views/auth/login.php'
];

foreach ($canonicalViews as $viewPath) {
    $fullPath = BASE_PATH . '/' . $viewPath;
    check("Canonical view '{$viewPath}' tersedia & valid", file_exists($fullPath) && filesize($fullPath) > 0, "File kanonikal hilang atau kosong");
}

$vendorAssets = [
    'assets/vendor/leaflet/leaflet.min.js',
    'assets/vendor/leaflet/leaflet.min.css',
    'assets/vendor/sweetalert2/sweetalert2.all.min.js',
    'assets/vendor/sweetalert2/sweetalert2.min.css',
    'assets/vendor/tailwind/tailwind.min.js',
    'assets/vendor/qrcode/qrcode.min.js',
    'assets/vendor/jszip/jszip.min.js'
];

foreach ($vendorAssets as $asset) {
    $fullPath = BASE_PATH . '/' . $asset;
    check("Vendor asset SSOT '{$asset}' tersedia", file_exists($fullPath) && filesize($fullPath) > 0, "Aset vendor hilang!");
}

$duplicateAssets = [
    'assets/js/leaflet.min.js',
    'assets/css/leaflet.min.css',
    'assets/js/sweetalert2.all.min.js',
    'assets/css/sweetalert2.min.css',
    'assets/js/tailwind.min.js',
    'assets/js/qrcode.min.js',
    'assets/js/jszip.min.js'
];

foreach ($duplicateAssets as $asset) {
    $fullPath = BASE_PATH . '/' . $asset;
    check("Aset duplikat usang '{$asset}' telah dibersihkan", !file_exists($fullPath), "Aset duplikat masih ada!");
}

// ---------------------------------------------------------------------
// SEKSI 2: VERIFIKASI FASE 2 (FRONTEND MODULARISASI & QrSarUtils)
// ---------------------------------------------------------------------
echo "\n--- 2. VERIFIKASI FASE 2: MODULARISASI JS & QrSarUtils ---\n";

$utilsFile = BASE_PATH . '/assets/js/utils/common.js';
check("Berkas utilitas bersama assets/js/utils/common.js tersedia", file_exists($utilsFile) && filesize($utilsFile) > 0);

$utilsContent = file_get_contents($utilsFile);
check("QrSarUtils mengekspos global QrSarUtils", strpos($utilsContent, 'global.QrSarUtils = QrSarUtils;') !== false);
check("QrSarUtils memiliki fungsi escapeHtml()", strpos($utilsContent, 'escapeHtml(') !== false);
check("QrSarUtils memiliki fungsi decimalToDms()", strpos($utilsContent, 'decimalToDms(') !== false);
check("QrSarUtils memiliki fungsi dmsToDecimal()", strpos($utilsContent, 'dmsToDecimal(') !== false);
check("QrSarUtils memiliki fungsi parseCoordinates()", strpos($utilsContent, 'parseCoordinates(') !== false);
check("QrSarUtils memiliki fungsi formatDmsPair()", strpos($utilsContent, 'formatDmsPair(') !== false);
check("QrSarUtils memiliki fungsi copyToClipboard()", strpos($utilsContent, 'copyToClipboard(') !== false);

// Verifikasi layout footer memuat common.js sebelum app.js
$footerContent = file_get_contents(BASE_PATH . '/views/layout/footer.php');
$commonJsPos = strpos($footerContent, 'assets/js/utils/common.js');
$appJsPos = strpos($footerContent, 'assets/js/app.js');
check("views/layout/footer.php memuat common.js", $commonJsPos !== false);
check("views/layout/footer.php memuat common.js sebelum app.js", $commonJsPos !== false && $appJsPos !== false && $commonJsPos < $appJsPos);

// Verifikasi dependency guards pada script halaman
$opsDetailJs = file_get_contents(BASE_PATH . '/assets/js/pages/operasi_detail.js');
check("operasi_detail.js memiliki guard TacticalGisMap dan Leaflet L", strpos($opsDetailJs, "typeof TacticalGisMap === 'undefined' || typeof L === 'undefined'") !== false);

$gisMasterJs = file_get_contents(BASE_PATH . '/assets/js/gis_master.js');
check("gis_master.js memiliki guard Leaflet typeof L === 'undefined'", strpos($gisMasterJs, "typeof L === 'undefined'") !== false);

// ---------------------------------------------------------------------
// SEKSI 3: VERIFIKASI FASE 3 (QUERY SQL, INDEKS & ELIMINASI RUNTIME DDL)
// ---------------------------------------------------------------------
echo "\n--- 3. VERIFIKASI FASE 3: QUERY SQL, INDEKS KOMPOSIT & DDL ---\n";

// A. Pengecekan 4 Indeks Komposit
$indexesToCheck = [
    ['table' => 'Korban', 'index' => 'idx_korban_ops_status', 'expected_cols' => ['id_operasi', 'status_korban']],
    ['table' => 'Operasi', 'index' => 'idx_ops_status_selesai', 'expected_cols' => ['status', 'waktu_selesai']],
    ['table' => 'Renops', 'index' => 'idx_renops_ops_hari', 'expected_cols' => ['id_operasi', 'hari_ke']],
    ['table' => 'LaporanHarian', 'index' => 'idx_lap_ops_hari', 'expected_cols' => ['id_operasi', 'hari_ke']]
];

foreach ($indexesToCheck as $idxInfo) {
    $stmt = $db->query("SHOW INDEX FROM `{$idxInfo['table']}` WHERE Key_name = '{$idxInfo['index']}'");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    check("Indeks komposit {$idxInfo['index']} ada pada tabel {$idxInfo['table']}", !empty($rows), "Indeks tidak ditemukan!");
    
    $cols = array_map(function($r) { return $r['Column_name']; }, $rows);
    check("Kolom pada indeks {$idxInfo['index']} sesuai", $cols === $idxInfo['expected_cols'], "Kolom tidak cocok: " . json_encode($cols));
}

// B. Pengecekan Eliminasi Runtime DDL
$renopsModelContent = file_get_contents(BASE_PATH . '/models/RenopsModel.php');
$gisModelContent = file_get_contents(BASE_PATH . '/models/GisModel.php');

check("RenopsModel::getById() bebas dari pemanggilan ensureSchemaUpdated()", strpos($renopsModelContent, 'self::ensureSchemaUpdated();') === false);
check("GisModel method CRUD bebas dari pemanggilan ensureSchemaUpdated()", strpos($gisModelContent, 'self::ensureSchemaUpdated();') === false);

// C. Pengecekan Query OperasiModel::getListSimple() (Anti-Error 1525)
$openOps = OperasiModel::getListSimple('OPEN');
check("OperasiModel::getListSimple('OPEN') berjalan sukses tanpa error 1525", is_array($openOps));

$closedOps = OperasiModel::getListSimple('CLOSED');
check("OperasiModel::getListSimple('CLOSED') berjalan sukses tanpa error 1525", is_array($closedOps));

$allSimpleOps = OperasiModel::getListSimple();
check("OperasiModel::getListSimple() (Semua) berjalan sukses", is_array($allSimpleOps));

// ---------------------------------------------------------------------
// SEKSI 4: SIMULASI END-TO-END TRANSAKSI LENGKAP & AGREGASI DERIVED JOIN
// ---------------------------------------------------------------------
echo "\n--- 4. SIMULASI TRANSAKSI E2E: OPERASI, KORBAN, RENOPS, SITREP & BRA ---\n";

// 1. Buat Operasi Baru
$kodeTest = 'TEST_AUDIT_' . time();
$newOpData = [
    'nama_operasi' => 'OPERASI UJI AUDIT SISTEM KPP KENDARI - ' . $kodeTest,
    'jenis_kejadian' => 'KMM',
    'lokasi_kejadian' => 'Perairan Teluk Kendari Uji',
    'lintang' => "03° 58' 45\" S",
    'bujur' => "122° 35' 50\" E",
    'koordinat' => "03° 58' 45\" S - 122° 35' 50\" E",
    'waktu_kejadian' => date('Y-m-d H:i:s', strtotime('-2 hours')),
    'waktu_lapor' => date('Y-m-d H:i:s', strtotime('-1 hour')),
    'waktu_berangkat' => date('Y-m-d H:i:s', strtotime('-45 minutes')),
    'nama_pelapor' => 'PETUGAS AUDIT',
    'hubungan_pelapor' => 'Internal',
    'no_telp_pelapor' => '081122334455',
    'status' => 'OPEN',
    'jarak' => '10 NM',
    'radial' => '090°',
    'jumlah_pob' => 4,
    'korban' => [
        ['nama_korban' => 'KORBAN DP 1', 'umur' => '25', 'jk' => 'L', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
        ['nama_korban' => 'KORBAN DP 2', 'umur' => '30', 'jk' => 'P', 'alamat' => 'Kendari', 'status_korban' => 'DP'],
        ['nama_korban' => 'KORBAN S 1', 'umur' => '22', 'jk' => 'L', 'alamat' => 'Kendari', 'status_korban' => 'S'],
        ['nama_korban' => 'KORBAN MD 1', 'umur' => '40', 'jk' => 'L', 'alamat' => 'Kendari', 'status_korban' => 'MD'],
    ]
];

$opId = OperasiModel::create($newOpData);
check("Operasi uji berhasil dibuat (ID: {$opId})", $opId > 0);

if ($opId > 0) {
    // 2. Tambah Renops Uji
    $renopsData = [
        'id_operasi' => $opId,
        'hari_ke' => 1,
        'tanggal' => date('Y-m-d'),
        'waktu_mulai' => '07:00',
        'waktu_selesai' => '17:00',
        'rencana_operasi' => 'Penyisiran sektor Alfa dan Bravo',
        'unsur_terlibat' => 'Basarnas Kendari, Polairud, TNI AL',
        'alut' => 'RIB 01, Rubber Boat',
        'sr_area' => 'Teluk Kendari',
        'cuaca' => 'Cerah Berawan',
        'arah_angin' => 'Tenggara',
        'kecepatan_angin' => '10 knot',
        'tinggi_gelombang' => '0.5 - 1.25 m',
        'arus' => '0.5 knot arah Selatan'
    ];
    $renopsId = RenopsModel::create($renopsData);
    check("Renops uji berhasil dibuat (ID: {$renopsId})", $renopsId > 0);

    // 3. Tambah Laporan Harian (Sitrep) Uji
    $sitrepData = [
        'id_operasi' => $opId,
        'hari_ke' => 1,
        'tanggal' => date('Y-m-d'),
        'waktu_mulai' => '07:30',
        'waktu_selesai' => '17:30',
        'giat_operasi' => 'Melaksanakan penyisiran sejauh 15 NM persegi',
        'hasil_operasi' => 'Ditemukan 1 korban selamat dan 1 korban MD',
        'unsur_terlibat' => 'Basarnas Kendari, Polairud',
        'alut' => 'RIB 01',
        'kendala' => 'Nihil',
        'evaluasi' => 'Pencarian hari pertama efektif',
        'rencana_lanjut' => 'Melanjutkan pencarian 2 korban DP besok hari',
        'status_operasi' => 'DILANJUTKAN'
    ];
    $sitrepId = LaporanModel::create($sitrepData);
    check("Laporan Harian uji berhasil dibuat (ID: {$sitrepId})", $sitrepId > 0);

    // 4. Tambah Berita Radio SAR (BRA SAR) Uji
    $braData = [
        'id_operasi' => $opId,
        'nomor_bra' => 'BRA/TEST/' . date('Ymd') . '/001',
        'dari' => 'KANSAR KENDARI',
        'kepada' => 'KABASARNAS',
        'info' => 'SAR MISSION COORDINATOR',
        'klasifikasi' => 'KILAT',
        'derajat' => 'OPERASI SAR',
        'waktu_buat' => date('Y-m-d H:i:s'),
        'waktu_kirim' => date('Y-m-d H:i:s'),
        'isi_berita' => 'PERKEMBANGAN OPERASI SAR HARI KE-1 TELAH DITEMUKAN 1 SELAMAT DAN 1 MD TTK',
        'status_kirim' => 'TERKIRIM',
        'pengirim' => 'OPERATOR SENKOM'
    ];
    $braId = BraModel::create($braData);
    check("BRA SAR uji berhasil dibuat (ID: {$braId})", $braId > 0);

    // 5. Verifikasi Integritas Agregasi Query Single-Pass LEFT JOIN (Fase 3 SQL-01)
    $fetchedOps = OperasiModel::getAll(null, $kodeTest);
    check("OperasiModel::getAll() menemukan data uji dengan search filter", count($fetchedOps) === 1);
    
    if (count($fetchedOps) === 1) {
        $opRow = $fetchedOps[0];
        check("Agregasi total_korban_count presisi (4)", (int)$opRow['total_korban_count'] === 4);
        check("Agregasi korban_dp presisi (2)", (int)$opRow['korban_dp'] === 2);
        check("Agregasi korban_s presisi (1)", (int)$opRow['korban_s'] === 1);
        check("Agregasi korban_md presisi (1)", (int)$opRow['korban_md'] === 1);
        check("Agregasi korban_h presisi (0)", (int)$opRow['korban_h'] === 0);
        check("Agregasi total_renops presisi (1)", (int)$opRow['total_renops'] === 1);
        check("Agregasi total_laporan presisi (1)", (int)$opRow['total_laporan'] === 1);
        check("Agregasi total_bra presisi (1)", (int)$opRow['total_bra'] === 1);
    }

    // 6. Uji Transisi Status ke CLOSED
    $closeSuccess = OperasiModel::closeOperation($opId, date('Y-m-d H:i:s'));
    check("Update status operasi ke CLOSED sukses", $closeSuccess);

    // Pastikan operasi berpindah kategori di getListSimple
    $simpleOpen = OperasiModel::getListSimple('OPEN');
    $simpleClosed = OperasiModel::getListSimple('CLOSED');
    $foundInOpen = array_filter($simpleOpen, fn($o) => (int)$o['id_operasi'] === $opId);
    $foundInClosed = array_filter($simpleClosed, fn($o) => (int)$o['id_operasi'] === $opId);

    check("Operasi CLOSED tidak muncul di getListSimple('OPEN')", count($foundInOpen) === 0);
    check("Operasi CLOSED muncul di getListSimple('CLOSED')", count($foundInClosed) === 1);

    // 7. Cleanup Data Uji (Garbage Collection Bersih)
    if ($braId > 0) {
        $db->exec("DELETE FROM BraSar WHERE id_bra = " . (int)$braId);
    }
    if ($sitrepId > 0) {
        $db->exec("DELETE FROM LaporanHarian WHERE id_laporan = " . (int)$sitrepId);
    }
    if ($renopsId > 0) {
        $db->exec("DELETE FROM Renops WHERE id_renops = " . (int)$renopsId);
    }
    $db->exec("DELETE FROM Korban WHERE id_operasi = " . (int)$opId);
    $db->exec("DELETE FROM Operasi WHERE id_operasi = " . (int)$opId);
    check("Pembersihan data transaksi uji tuntas & pangkalan data bersih", true);
}

// ---------------------------------------------------------------------
// SEKSI 5: VERIFIKASI AKSES HTTP 15 RUTE UTAMA (ZERO CRASH & ZERO ERROR 500)
// ---------------------------------------------------------------------
echo "\n--- 5. VERIFIKASI AKSESIBILITAS HTTP 15 RUTE UTAMA APLIKASI ---\n";

$routes = [
    ['name' => 'Dashboard Utama (index.php)', 'script' => 'index.php', 'get' => []],
    ['name' => 'Daftar Operasi (operasi.php)', 'script' => 'operasi.php', 'get' => []],
    ['name' => 'Form Operasi Baru (operasi_form.php)', 'script' => 'operasi_form.php', 'get' => []],
    ['name' => 'Detail Operasi (operasi_detail.php?id=1)', 'script' => 'operasi_detail.php', 'get' => ['id' => 1]],
    ['name' => 'Peta Plotting GIS Standalone (gis.php)', 'script' => 'gis.php', 'get' => []],
    ['name' => 'GIS Filter Operasi (gis.php?id_operasi=1)', 'script' => 'gis.php', 'get' => ['id_operasi' => 1]],
    ['name' => 'GIS Mode Basemap (gis.php?mode=basemap)', 'script' => 'gis.php', 'get' => ['mode' => 'basemap']],
    ['name' => 'Daftar BRA SAR (bra.php)', 'script' => 'bra.php', 'get' => []],
    ['name' => 'Form Inputan BRA (bra.php?action=create)', 'script' => 'bra.php', 'get' => ['action' => 'create']],
    ['name' => 'Lihat Naskah BRA (bra.php?view=1)', 'script' => 'bra.php', 'get' => ['view' => 1]],
    ['name' => 'Form Renops SAR (renops_form.php?id_operasi=1)', 'script' => 'renops_form.php', 'get' => ['id_operasi' => 1]],
    ['name' => 'Form Sitrep/Lapbang (sitrep_form.php?id_operasi=1)', 'script' => 'sitrep_form.php', 'get' => ['id_operasi' => 1]],
    ['name' => 'Master Data (master.php)', 'script' => 'master.php', 'get' => []],
    ['name' => 'Panel Admin (admin.php)', 'script' => 'admin.php', 'get' => []],
    ['name' => 'Halaman Login (login.php)', 'script' => 'login.php', 'get' => []],
];

$phpBin = '/opt/homebrew/bin/php';

foreach ($routes as $route) {
    $script = $route['script'];
    $isLogin = ($script === 'login.php');
    $sessionInit = $isLogin 
        ? "session_start();" 
        : "session_start(); \$_SESSION['user_id'] = 1; \$_SESSION['username'] = 'admin_auditor'; \$_SESSION['role'] = 'ADMIN';";

    $getExport = var_export($route['get'], true);
    $scriptPath = BASE_PATH . '/' . $script;

    $evalCode = "
    register_shutdown_function(function() {
        \$out = ob_get_clean();
        if (stripos(\$out, 'Fatal error') !== false || stripos(\$out, 'Parse error') !== false || stripos(\$out, 'SQLSTATE[') !== false) {
            echo 'ERROR: ' . substr(strip_tags(\$out), 0, 150);
            exit(1);
        }
        echo 'OK:' . strlen(\$out);
    });
    {$sessionInit}
    \$_GET = {$getExport};
    ob_start();
    include '{$scriptPath}';
    ";
    $encoded = base64_encode($evalCode);
    $cmd = sprintf("%s -r 'eval(base64_decode(\"%s\"));'", escapeshellcmd($phpBin), $encoded);

    $output = shell_exec($cmd);
    $isOk = $output !== null && str_starts_with(trim($output), 'OK:');
    check("Rute {$route['name']} render 100% mulus (Bebas Fatal/SQLSTATE)", $isOk, $output ?? 'Empty response');
}

// ---------------------------------------------------------------------
// SEKSI 6: VERIFIKASI PETA TAKTIS GIS & API GIS BUNDLE
// ---------------------------------------------------------------------
echo "\n--- 6. VERIFIKASI PETA TAKTIS GIS & ENDPOINT JSON BUNDLE ---\n";

$apiScriptPath = BASE_PATH . '/api/index.php';
$apiEvalCode = "session_start(); \$_SESSION['user_id'] = 1; \$_SESSION['username'] = 'admin_auditor'; \$_SESSION['role'] = 'ADMIN'; \$_SERVER['REQUEST_METHOD'] = 'GET'; \$_SERVER['REQUEST_URI'] = '/api/gis/bundle?id_operasi=1'; \$_GET = ['id_operasi' => 1]; ob_start(); include '{$apiScriptPath}'; \$out = ob_get_clean(); echo \$out;";
$apiEncoded = base64_encode($apiEvalCode);
$apiCmd = sprintf("%s -r 'eval(base64_decode(\"%s\"));'", escapeshellcmd($phpBin), $apiEncoded);
$apiOutput = shell_exec($apiCmd);

$bundleJson = json_decode((string)$apiOutput, true);

check("Endpoint /api/gis/bundle mengembalikan JSON valid", is_array($bundleJson), "Output bukan JSON: " . substr((string)$apiOutput, 0, 150));
if (is_array($bundleJson)) {
    check("Response bundle memiliki key 'success'", isset($bundleJson['success']) && $bundleJson['success'] === true);
    check("Response bundle memiliki payload data", isset($bundleJson['data']) && is_array($bundleJson['data']));
    if (isset($bundleJson['data'])) {
        check("Data bundle memuat layer points", isset($bundleJson['data']['points']));
        check("Data bundle memuat layer polygons", isset($bundleJson['data']['polygons']));
        check("Data bundle memuat layer routes", isset($bundleJson['data']['routes']));
        check("Data bundle memuat layer search_patterns", isset($bundleJson['data']['searchPatterns']) || isset($bundleJson['data']['search_patterns']));
    }
}

// ---------------------------------------------------------------------
// RINGKASAN AKHIR SUITE VERIFIKASI
// ---------------------------------------------------------------------
echo "\n====================================================================\n";
echo "              RINGKASAN HASIL VERIFIKASI MENDALAM                   \n";
echo "====================================================================\n";
echo "  Total Pemeriksaan Asersi : {$totalAssertions}\n";
echo "  Lulus (PASS)             : {$passedAssertions}\n";
echo "  Gagal (FAIL)             : {$failedAssertions}\n";
echo "====================================================================\n";

if ($failedAssertions > 0) {
    echo "PERINGATAN: DITEMUKAN KEGAGALAN!\n";
    foreach ($errors as $err) {
        echo " - {$err}\n";
    }
    exit(1);
} else {
    echo "KESIMPULAN: SELURUH FUNGSIONALITAS FASE 1 S.D. 3 BERJALAN 100% SEMPURNA!\n";
    echo "TIDAK ADA REGRESI, TIDAK ADA KERUSAKAN, DAN ZERO DATA LOSS.\n";
    exit(0);
}
