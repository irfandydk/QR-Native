<?php
/**
 * Test Suite: Perbaikan Audit Tahap 1 (Backend, Keamanan & Deployment Lintas OS)
 */

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: hanya CLI.' . PHP_EOL);
}

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$total = 0;

function assertT1(bool $cond, string $msg): void {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] {$msg}\n";
    } else {
        echo " [FAIL] {$msg}\n";
    }
}

echo "=== TEST TAHAP 1: BACKEND, KEAMANAN & DEPLOYMENT LINTAS OS ===\n\n";

$db = getDB();
$phpBin = PHP_BINARY ?: 'php';
$rootDir = ROOT_DIR;

// 1. Open Redirect Boundary Check (sanitizeRedirectUrl)
$fallback = BASE_URL . '/index.php';
$evilPrefix = rtrim(BASE_URL, '/') . '.evil.com/phish';
$evilCred = preg_replace('#^(https?://)#i', '$1' . parse_url(BASE_URL, PHP_URL_HOST) . '@evil.com/', BASE_URL);
assertT1(AuthHelper::sanitizeRedirectUrl($evilPrefix, $fallback) === $fallback, 'sanitizeRedirectUrl menolak subdomain jahat berawalan BASE_URL (.evil.com)');
assertT1(AuthHelper::sanitizeRedirectUrl($evilCred, $fallback) === $fallback, 'sanitizeRedirectUrl menolak userinfo spoofing (@evil.com)');
assertT1(AuthHelper::sanitizeRedirectUrl("/operasi.php\r\nSet-Cookie: a=b", $fallback) === $fallback, 'sanitizeRedirectUrl menolak CRLF header injection');
assertT1(AuthHelper::sanitizeRedirectUrl(rtrim(BASE_URL, '/') . '/operasi.php?id=1', $fallback) === rtrim(BASE_URL, '/') . '/operasi.php?id=1', 'sanitizeRedirectUrl menerima URL internal yang sah');

// 2. Isolasi LoginThrottle per (IP, username)
$_SERVER['REMOTE_ADDR'] = '127.0.0.99';
AuthHelper::resetLoginThrottle();
$oldUserHash = (string)$db->query("SELECT password_hash FROM `Users` WHERE id = 'USR-USER'")->fetchColumn();
$userPass = 'SandiUjiOperator99!';
$db->prepare("UPDATE `Users` SET password_hash = :p, is_active = 1 WHERE id = 'USR-USER'")
    ->execute([':p' => password_hash($userPass, PASSWORD_DEFAULT)]);
try {
    for ($i = 0; $i < 4; $i++) {
        AuthHelper::login('admin', 'SandiSalah123');
    }
    // Login sah ke akun 'user' tidak boleh menghapus hitungan gagal milik 'admin'
    $okUser = AuthHelper::login('user', $userPass);
    assertT1(($okUser['success'] ?? false) === true, 'Operator tetap dapat login ke akun user');
    AuthHelper::login('admin', 'SandiSalah123'); // gagal ke-5 pada admin
    assertT1(AuthHelper::checkLoginThrottle('admin') !== null, 'Percobaan gagal ke-5 pada admin tetap mengunci admin walau diselingi login user');

    // Sebaliknya: 5 gagal pada user mengunci user tanpa mengunci admin di IP Wi-Fi Posko yang sama
    AuthHelper::resetLoginThrottle();
    for ($i = 0; $i < 5; $i++) {
        AuthHelper::login('user', 'SalahTerus123');
    }
    assertT1(AuthHelper::checkLoginThrottle('user') !== null, 'Akun user terkunci setelah 5 kali gagal');
    assertT1(AuthHelper::checkLoginThrottle('admin') === null, 'Akun admin di IP yang sama tidak ikut terkunci oleh salah ketik akun user');
} finally {
    AuthHelper::resetLoginThrottle();
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = 'USR-USER'")->execute([':p' => $oldUserHash]);
}

// 3. Validasi ukuran & magic bytes citra pada DokumenPetaSnapshotModel
$fakeImageB64 = 'data:image/png;base64,' . base64_encode(str_repeat('BUKAN_GAMBAR_PNG_VALID_', 10));
$rejectedFake = false;
try {
    DokumenPetaSnapshotModel::saveSnapshot([
        'id_operasi' => 999992,
        'jenis_dokumen' => 'RENOPS',
        'image_data' => $fakeImageB64,
    ]);
} catch (InvalidArgumentException $e) {
    $rejectedFake = true;
}
assertT1($rejectedFake, 'DokumenPetaSnapshotModel menolak payload non-citra yang menyamar sebagai data:image/png');

$oversizeRaw = 'data:image/png;base64,' . str_repeat('A', 7 * 1024 * 1024 + 100);
$rejectedOversize = false;
try {
    DokumenPetaSnapshotModel::saveSnapshot([
        'id_operasi' => 999992,
        'jenis_dokumen' => 'RENOPS',
        'image_data' => $oversizeRaw,
    ]);
} catch (InvalidArgumentException $e) {
    $rejectedOversize = true;
}
assertT1($rejectedOversize, 'DokumenPetaSnapshotModel menolak payload base64 > 5 MB sebelum menulis disk');

// 4. GisApiController: proteksi spoofing id_operasi pada rekaman operasi CLOSED & id_operasi fiktif
$idOpClosed = OperasiModel::create([
    'nama_operasi' => 'Uji GIS Closed Lock',
    'nama_pelapor' => 'Pelapor Uji',
    'lokasi_kejadian' => 'Teluk Kendari',
    'waktu_lapor' => '2026-11-01 08:00:00',
    'waktu_berangkat' => '2026-11-01 08:15:00',
]);
$idOpOpen = OperasiModel::create([
    'nama_operasi' => 'Uji GIS Open Target',
    'nama_pelapor' => 'Pelapor Uji',
    'lokasi_kejadian' => 'Selat Wawonii',
    'waktu_lapor' => '2026-11-01 09:00:00',
    'waktu_berangkat' => '2026-11-01 09:15:00',
]);
$ptClosedId = 'pt-closed-t1-' . bin2hex(random_bytes(3));
try {
    GisModel::savePoint([
        'id' => $ptClosedId,
        'id_operasi' => $idOpClosed,
        'lat' => -3.97,
        'lng' => 122.58,
        'label' => 'Titik Terkunci',
        'pointType' => 'lkp',
        'category' => 'LKP',
    ]);
    $db->prepare("UPDATE Operasi SET status = 'CLOSED' WHERE id_operasi = ?")->execute([$idOpClosed]);

    $runGisSub = function (string $path, string $method, array $body) use ($phpBin, $rootDir): array {
        $code = sprintf(
            'require %s; $_SESSION["user_id"] = "USR-USER"; $_SESSION["role"] = "USER"; $_SESSION["user"] = ["id" => "USR-USER", "role" => "USER"]; GisApiController::handle(%s, %s, %s);',
            var_export($rootDir . '/config/app.php', true),
            var_export($path, true),
            var_export($method, true),
            var_export($body, true)
        );
        $out = [];
        exec(escapeshellarg($phpBin) . ' -r ' . escapeshellarg($code) . ' 2>&1', $out);
        $raw = trim(implode("\n", $out));
        return json_decode($raw, true) ?: ['raw' => $raw];
    };

    // Upaya membajak titik milik operasi CLOSED dengan menyisipkan id_operasi milik operasi OPEN
    $spoofRes = $runGisSub("/gis/point/{$ptClosedId}", 'PUT', [
        'id' => $ptClosedId,
        'id_operasi' => $idOpOpen,
        'lat' => -4.01,
        'lng' => 122.61,
        'label' => 'Dibajak',
    ]);
    assertT1(($spoofRes['success'] ?? true) === false, 'GisApiController menolak modifikasi titik operasi CLOSED walau id_operasi di-spoof ke operasi OPEN');

    // Upaya membuat titik dengan id_operasi fiktif oleh USER non-admin
    $fakeOpRes = $runGisSub('/gis/point', 'POST', [
        'id' => 'pt-fakeop-' . bin2hex(random_bytes(3)),
        'id_operasi' => 987654321,
        'lat' => -4.00,
        'lng' => 122.60,
        'label' => 'Titik Fiktif',
    ]);
    assertT1(($fakeOpRes['success'] ?? true) === false, 'GisApiController menolak pembuatan titik dengan id_operasi fiktif');
} finally {
    GisModel::deletePoint($ptClosedId);
    OperasiModel::delete($idOpClosed);
    OperasiModel::delete($idOpOpen);
}

// 5. BackupService: RenopsDokumen (GENERATED columns), penolakan SQL berbahaya, & normalisasi nama tabel lowercase Windows
assertT1(in_array('RenopsDokumen', BackupService::MANAGED_TABLES, true) && in_array('RenopsFoto', BackupService::MANAGED_TABLES, true),
    'BackupService::MANAGED_TABLES mencakup RenopsDokumen & RenopsFoto');

$rejectedDanger = false;
try {
    BackupService::restoreFromSQL("DROP DATABASE qrsar_test;");
} catch (InvalidArgumentException $e) {
    $rejectedDanger = true;
}
assertT1($rejectedDanger, 'BackupService::restoreFromSQL menolak pernyataan berbahaya (DROP DATABASE) sebelum menyentuh tabel');

$rejectedUnmanaged = false;
try {
    BackupService::restoreFromSQL("DELETE FROM `migrations`;");
} catch (InvalidArgumentException $e) {
    $rejectedUnmanaged = true;
}
assertT1($rejectedUnmanaged, 'BackupService::restoreFromSQL menolak manipulasi tabel di luar MANAGED_TABLES (migrations)');

// Uji siklus nyata dump + restore pada RenopsDokumen (memiliki STORED GENERATED draf_lock & terbit_lock)
$db->exec("DELETE FROM Operasi WHERE nama_operasi = 'Uji Backup RenopsDokumen'");
$idOpBak = 0;
$snapBeforeTest = count(glob(STORAGE_DIR . '/backups/pre-restore-*.sql') ?: []);
try {
    $idOpBak = OperasiModel::create([
        'nama_operasi' => 'Uji Backup RenopsDokumen',
        'nama_pelapor' => 'Pelapor Backup',
        'lokasi_kejadian' => 'Kendari',
        'waktu_lapor' => '2026-11-02 08:00:00',
        'waktu_berangkat' => '2026-11-02 08:30:00',
    ]);
    $idRenBak = RenopsModel::create([
        'id_operasi' => $idOpBak,
        'nomor_renops' => 'RENOPS-BAK-T1',
        'hari_ke' => 'H.1',
        'tanggal' => '2026-11-02',
    ]);
    RenopsDokumenModel::saveDraft($idRenBak, $idOpBak, ['situasi' => 'Uji kolom generated draf_lock'], 0);
    $sqlDump = BackupService::generateSQLDump();
    assertT1(str_contains($sqlDump, 'CREATE TABLE `RenopsDokumen`') && !preg_match('/INSERT INTO `RenopsDokumen` \([^)]*`draf_lock`/i', $sqlDump),
        'SQL Dump menyertakan RenopsDokumen tanpa menyisipkan kolom STORED GENERATED draf_lock di INSERT');

    // Simulasikan dump dari Windows (lower_case_table_names=1) untuk sebagian pernyataan
    $winSql = str_replace(
        ['DROP TABLE IF EXISTS `RenopsDokumen`', 'INSERT INTO `RenopsDokumen`'],
        ['DROP TABLE IF EXISTS `renopsdokumen`', 'INSERT INTO `renopsdokumen`'],
        $sqlDump
    );
    $restoredOk = BackupService::restoreFromSQL($winSql);
    $draftAfter = RenopsDokumenModel::getDraft($idRenBak);
    assertT1($restoredOk && ($draftAfter['konten']['sections']['situasi'] ?? '') === 'Uji kolom generated draf_lock',
        'restoreFromSQL memulihkan RenopsDokumen berkolom GENERATED & menormalkan nama tabel lowercase Windows');
} finally {
    if ($idOpBak > 0) {
        OperasiModel::delete($idOpBak);
    }
    // Bersihkan file pre-restore baru yang dibuat selama pengujian ini agar storage/backups tetap rapi
    $snapsNow = glob(STORAGE_DIR . '/backups/pre-restore-*.sql') ?: [];
    if (count($snapsNow) > $snapBeforeTest) {
        foreach (array_slice($snapsNow, $snapBeforeTest) as $sp) {
            @unlink($sp);
        }
    }
}

// 6. SettingsApiController: tamu tidak menerima pejabat_list, admin tidak menerima plaintext gemini_api_key
$guestSettingsCode = sprintf(
    'require %s; $_SESSION = []; SettingsApiController::handle("/settings", "GET", []);',
    var_export($rootDir . '/config/app.php', true)
);
$guestOut = [];
exec(escapeshellarg($phpBin) . ' -r ' . escapeshellarg($guestSettingsCode) . ' 2>&1', $guestOut);
$guestJson = json_decode(trim(implode("\n", $guestOut)), true);
assertT1(is_array($guestJson) && !isset($guestJson['data']['pejabat_list']), 'GET /api/settings publik (tamu) tidak membocorkan daftar pejabat & NIP');

// 7. Pemeriksaan konfigurasi statis lintas OS (.htaccess, views/bra/view.php, migrate.php, config/database.php)
$htaccess = (string)file_get_contents($rootDir . '/.htaccess');
assertT1(str_contains($htaccess, '(?:^|/)') && str_contains($htaccess, 'views'), '.htaccess melindungi folder sistem baik di root maupun subfolder Apache/Laragon');

$braView = (string)file_get_contents($rootDir . '/views/bra/view.php');
assertT1(str_contains($braView, "!defined('APP_NAME')") && str_contains($braView, '/bra/verify_invalid.php'), 'views/bra/view.php memiliki guard akses langsung & path verify_invalid yang benar');

$migratePhp = (string)file_get_contents($rootDir . '/database/migrate.php');
assertT1(!str_contains($migratePhp, "'route-op-8'") && !str_contains($migratePhp, 'IN (8, 88888)'), 'database/migrate.php tidak lagi menghapus data operasi nyata ID 8');

$dbConfigPhp = (string)file_get_contents($rootDir . '/config/database.php');
assertT1(!str_contains($dbConfigPhp, 'dbname=db_sar_qr'), 'config/database.php tidak lagi diam-diam fallback ke db_sar_qr');

echo "\nTotal: {$total}, Lolos: {$passed}\n";
exit($passed === $total ? 0 : 1);
