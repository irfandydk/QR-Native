<?php
require_once __DIR__ . '/bootstrap.php';
/**
 * Test Dashboard Default Open & Korban Rekap Removal
 */
require_once __DIR__ . '/../config/app.php';

echo "=== MEMULAI TEST DASHBOARD DEFAULT OPEN & PEMBERSIHAN REKAP KORBAN ===\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition($name, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo "![FAIL] $name: $details\n";
        $failCount++;
    }
}

// 1. Test Static File Content & Architecture
$dashboardFile = __DIR__ . '/../views/dashboard.php';
$content = file_get_contents($dashboardFile);

// Check syntax
$lintOutput = [];
$lintCode = 0;
exec('/opt/homebrew/bin/php -l ' . escapeshellarg($dashboardFile), $lintOutput, $lintCode);
assertCondition("Sintaks views/dashboard.php valid tanpa error", $lintCode === 0);

assertCondition(
    "Fallback initialStatus adalah 'OPEN'",
    strpos($content, ": 'OPEN';") !== false
);

assertCondition(
    "Variabel totalSelamat, totalMeninggal, totalHilang, totalKorban di dashboard header telah dibersihkan",
    strpos($content, '$totalSelamat') === false &&
    strpos($content, '$totalMeninggal') === false &&
    strpos($content, '$totalHilang') === false &&
    strpos($content, '$totalKorban') === false
);

assertCondition(
    "Container Rekap Korban & POB pill bar telah dihilangkan",
    stripos($content, 'Rekap Korban') === false &&
    stripos($content, 'SITUATIONAL KORBAN SUMMARY PILL BAR') === false
);

assertCondition(
    "Tab status buttons dinamis berdasarkan initialStatus",
    strpos($content, '$initialStatus === \'OPEN\' ? \'font-bold bg-orange-600') !== false &&
    strpos($content, '$initialStatus === \'ALL\' ? \'font-bold bg-orange-600') !== false &&
    strpos($content, '$initialStatus === \'CLOSED\' ? \'font-bold bg-orange-600') !== false
);

assertCondition(
    "Badge tab status dinamis berdasarkan initialStatus",
    strpos($content, '$initialStatus === \'OPEN\' ? \'bg-white/20 text-white font-bold\' : \'bg-slate-200') !== false
);

assertCondition(
    "Empty state dinamis mendeteksi initialStatus === 'OPEN'",
    strpos($content, '$initialStatus === \'OPEN\' ? \'Tidak ada operasi SAR aktif\'') !== false
);

assertCondition(
    "Card operasi di SSR ter-filter sesuai initialStatus (isInitialMatch ? '' : 'hidden')",
    strpos($content, '$isInitialMatch ? \'\' : \'hidden\'') !== false
);

assertCondition(
    "resetAllFilters() mereset ke status 'OPEN'",
    strpos($content, "setStatusFilter('OPEN');") !== false
);

assertCondition(
    "chipStatus close button mereset ke 'OPEN'",
    strpos($content, "onclick=\"setStatusFilter('OPEN')\"") !== false
);

assertCondition(
    "JS applyDashFilters menangani status non-default ('OPEN') secara akurat",
    strpos($content, "const isNonDefaultStatus = currentStatusFilter !== 'OPEN';") !== false
);

assertCondition(
    "JS applyDashFilters menampilkan 'Status: Semua Operasi' saat tab ALL dipilih",
    strpos($content, "currentStatusFilter === 'ALL' ? 'Semua Operasi' : currentStatusFilter") !== false
);

assertCondition(
    "Initial filter activation memanggil setStatusFilter(currentStatusFilter) saat DOM siap",
    strpos($content, "setStatusFilter(currentStatusFilter);") !== false
);

// 2. Test isolated rendering with mock data
class MockOperasiModel {
    public static function getStats() {
        return ['operasi_aktif' => 2, 'operasi_selesai' => 5];
    }
    public static function getAll() {
        return [
            [
                'id_operasi' => 1,
                'nama_operasi' => 'KMM Nelayan Hilang',
                'status' => 'OPEN',
                'lokasi_kejadian' => 'Wakatobi',
                'koordinat' => '05° 12\' S',
                'waktu_kejadian' => '2026-10-18 07:30:00',
                'waktu_lapor' => '2026-10-18 08:00:00',
                'waktu_berangkat' => '2026-10-18 08:15:00',
                'created_at' => '2026-10-18 08:00:00',
                'waktu_reopen' => null,
                'korban_s' => 1,
                'korban_md' => 0,
                'korban_dp' => 0,
                'jumlah_pob' => 1,
                'total_renops' => 1,
                'total_laporan' => 1
            ],
            [
                'id_operasi' => 2,
                'nama_operasi' => 'KMP Tenggelam',
                'status' => 'CLOSED',
                'lokasi_kejadian' => 'Teluk Kendari',
                'koordinat' => '03° 58\' S',
                'waktu_kejadian' => '2026-10-10 08:30:00',
                'waktu_lapor' => '2026-10-10 09:00:00',
                'waktu_berangkat' => '2026-10-10 09:20:00',
                'created_at' => '2026-10-10 09:00:00',
                'waktu_reopen' => null,
                'korban_s' => 10,
                'korban_md' => 1,
                'korban_dp' => 0,
                'jumlah_pob' => 11,
                'total_renops' => 2,
                'total_laporan' => 2
            ]
        ];
    }
}

class MockBraModel {
    public static function getStats() { return ['total' => 3]; }
    public static function getLatest($limit = 1) { return []; }
}

class MockAuthHelper {
    public static function getCurrentUser() { return ['id' => 1, 'role' => 'ADMIN']; }
    public static function isAdmin() { return true; }
}

// Create an isolated sandbox renderer
$rendererCode = file_get_contents($dashboardFile);
$rendererCode = str_replace(
    ['OperasiModel::', 'BraModel::', 'AuthHelper::'],
    ['MockOperasiModel::', 'MockBraModel::', 'MockAuthHelper::'],
    $rendererCode
);

function renderMockDashboard($code, $statusParam = null) {
    if ($statusParam !== null) {
        $_GET['status'] = $statusParam;
    } else {
        unset($_GET['status']);
    }
    ob_start();
    eval('?>' . $code);
    return ob_get_clean();
}

require_once __DIR__ . '/../helpers/TemplateHelper.php';

// Render default
$renderedDefault = renderMockDashboard($rendererCode, null);

assertCondition(
    "Render Default: Tab OPEN memiliki class aktif (oranye solid)",
    preg_match('/id="tab-status-OPEN"[^>]*bg-orange-600 text-white/', $renderedDefault) === 1
);

assertCondition(
    "Render Default: Tab ALL memiliki class inactive (bukan oranye solid)",
    preg_match('/id="tab-status-ALL"[^>]*bg-orange-600 text-white/', $renderedDefault) === 0
);

assertCondition(
    "Render Default: Tab CLOSED memiliki class inactive",
    preg_match('/id="tab-status-CLOSED"[^>]*bg-orange-600 text-white/', $renderedDefault) === 0
);

assertCondition(
    "Render Default: Card status CLOSED memiliki class 'hidden' di SSR",
    preg_match('/class="[^"]*\bhidden\b[^"]*"[^>]*data-status="CLOSED"/', $renderedDefault) === 1
);

assertCondition(
    "Render Default: Card status OPEN TIDAK memiliki class 'hidden' di SSR",
    preg_match('/class="[^"]*\bhidden\b[^"]*"[^>]*data-status="OPEN"/', $renderedDefault) === 0
);

assertCondition(
    "Render Default: Naskah 'Rekap Korban' TIDAK muncul di output",
    stripos($renderedDefault, 'Rekap Korban') === false
);

// Render status=ALL
$renderedAll = renderMockDashboard($rendererCode, 'ALL');

assertCondition(
    "Render ?status=ALL: Tab ALL memiliki class aktif (oranye solid)",
    preg_match('/id="tab-status-ALL"[^>]*bg-orange-600 text-white/', $renderedAll) === 1
);

assertCondition(
    "Render ?status=ALL: Tab OPEN memiliki class inactive",
    preg_match('/id="tab-status-OPEN"[^>]*bg-orange-600 text-white/', $renderedAll) === 0
);

assertCondition(
    "Render ?status=ALL: Card status CLOSED TIDAK memiliki class 'hidden' di SSR",
    preg_match('/class="[^"]*\bhidden\b[^"]*"[^>]*data-status="CLOSED"/', $renderedAll) === 0
);

// Render status=CLOSED
$renderedClosed = renderMockDashboard($rendererCode, 'CLOSED');

assertCondition(
    "Render ?status=CLOSED: Tab CLOSED memiliki class aktif (oranye solid)",
    preg_match('/id="tab-status-CLOSED"[^>]*bg-orange-600 text-white/', $renderedClosed) === 1
);

assertCondition(
    "Render ?status=CLOSED: Card status OPEN memiliki class 'hidden' di SSR",
    preg_match('/class="[^"]*\bhidden\b[^"]*"[^>]*data-status="OPEN"/', $renderedClosed) === 1
);

assertCondition(
    "Render ?status=CLOSED: Card status CLOSED TIDAK memiliki class 'hidden' di SSR",
    preg_match('/class="[^"]*\bhidden\b[^"]*"[^>]*data-status="CLOSED"/', $renderedClosed) === 0
);

echo "\n=== RINGKASAN HASIL TEST ===\n";
echo "Total Berhasil : $passCount\n";
echo "Total Gagal    : $failCount\n";

if ($failCount === 0) {
    echo ">> SEMUA PENGUJIAN INTEGRITAS DASHBOARD BERHASIL DENGAN SEMPURNA! <<\n";
    exit(0);
} else {
    echo ">> TERDAPAT KEGAGALAN DALAM PENGUJIAN! <<\n";
    exit(1);
}
