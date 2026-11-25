<?php
/**
 * QR-SAR - Regression Suite: Gelombang 1 Audit Security & Reliability Hardening
 * Menguji seluruh perbaikan audit terverifikasi (#1, #4, #5, #9, #11, #12, #19, #20, #21).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$total = 0;

function assertCond(bool $cond, string $label): void {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo " [PASS] {$label}\n";
    } else {
        echo " [FAIL] {$label}\n";
    }
}

echo "=== TEST GELOMBANG 1 SECURITY & RELIABILITY HARDENING ===\n\n";

$db = getDB();
$origUserHash = (string)$db->query("SELECT password_hash FROM `Users` WHERE id = 'USR-USER'")->fetchColumn();
$origLogo2 = $db->query("SELECT logo_url_2 FROM AppSettings WHERE id = 1")->fetchColumn();
$sentinelFile = STORAGE_DIR . '/sentinel_map_snap_test_' . time() . '.txt';
$validSnapFile = ROOT_DIR . '/uploads/map_snapshots/test_gelombang1_del_' . time() . '.png';
$testPtBadId = 'pt-g1-bad-' . time();
$testPtDelA = 'pt-g1-dela-' . time();
$testPtDelB = 'pt-g1-delb-' . time();

try {
    // ------------------------------------------------------------------
    // 1. (#1) SettingsModel::changePassword trims new & old passwords
    // ------------------------------------------------------------------
    $db->prepare("UPDATE `Users` SET password_hash = :p, is_active = 1 WHERE id = 'USR-USER'")
        ->execute([':p' => password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT)]);

    $spaceOnly = SettingsModel::changePassword('USER', DEFAULT_RESET_PASSWORD, '        ', 'USR-USER');
    assertCond(($spaceOnly['success'] ?? true) === false, '#1 changePassword menolak sandi baru berisi spasi saja');

    $paddedRes = SettingsModel::changePassword('USER', DEFAULT_RESET_PASSWORD, '  SandiRapih2026  ', 'USR-USER');
    assertCond(($paddedRes['success'] ?? false) === true, '#1 changePassword menerima & men-trim sandi baru dengan spasi tepi');

    $_SESSION = [];
    $loginRes = AuthHelper::login('user', 'SandiRapih2026');
    assertCond(($loginRes['success'] ?? false) === true, '#1 Login berhasil memakai sandi ter-trim');

    // ------------------------------------------------------------------
    // 2. (#21) Transaction rollBack on missing ID / errors
    // ------------------------------------------------------------------
    $renopsMissing = RenopsModel::update(99999999, []);
    assertCond($renopsMissing === false && !$db->inTransaction(), '#21 RenopsModel::update ID tidak ada me-rollBack transaksi');

    $lapMissing = LaporanModel::update(99999999, []);
    assertCond($lapMissing === false && !$db->inTransaction(), '#21 LaporanModel::update ID tidak ada me-rollBack transaksi');

    // ------------------------------------------------------------------
    // 3. (#9) AuthHelper::setSession clears old CSRF token & no GET CSRF
    // ------------------------------------------------------------------
    $_SESSION = ['csrf_token' => 'pre-auth-fixed-token'];
    AuthHelper::setSession(['id' => 'USR-USER', 'username' => 'user', 'role' => 'USER']);
    assertCond(empty($_SESSION['csrf_token']), '#9 AuthHelper::setSession menghapus token CSRF pra-login');
    $newToken = AuthHelper::csrfToken();
    assertCond($newToken !== '' && $newToken !== 'pre-auth-fixed-token', '#9 Token CSRF baru dibuat setelah login');

    $baseApiSrc = (string)file_get_contents(API_CONTROLLERS_DIR . '/BaseApiController.php');
    $authCtrlSrc = (string)file_get_contents(CONTROLLERS_DIR . '/AuthController.php');
    assertCond(!str_contains($baseApiSrc, "\$_GET['csrf_token']"), '#9 BaseApiController::requireCsrf tidak menerima $_GET[csrf_token]');
    assertCond(!str_contains($authCtrlSrc, "\$_GET['csrf_token']"), '#9 AuthController::logout tidak menerima $_GET[csrf_token]');

    // Runtime check via isolated PHP subprocess for BaseApiController::requireCsrf GET rejection
    $probeCmd = escapeshellarg(PHP_BINARY ?: 'php') . ' -r ' . escapeshellarg(
        'require ' . var_export(ROOT_DIR . '/config/app.php', true) . ';'
        . 'AuthHelper::setSession(["id" => "USR-USER", "username" => "user", "role" => "USER"]);'
        . '$tok = AuthHelper::csrfToken();'
        . '$_GET["csrf_token"] = $tok;'
        . 'BaseApiController::requireCsrf([]);'
    );
    $probeOut = [];
    $probeCode = 0;
    @exec($probeCmd, $probeOut, $probeCode);
    $probeJson = implode("\n", $probeOut);
    assertCond(str_contains($probeJson, 'CSRF_INVALID'), '#9 BaseApiController::requireCsrf runtime menolak token CSRF via $_GET');

    // ------------------------------------------------------------------
    // 4. (#20) DokumenPetaSnapshotModel::removeImageFile traversal guard
    // ------------------------------------------------------------------
    file_put_contents($sentinelFile, 'do-not-delete');
    DokumenPetaSnapshotModel::removeImageFile('uploads/map_snapshots/../../storage/' . basename($sentinelFile));
    DokumenPetaSnapshotModel::removeImageFile("uploads/map_snapshots/x.png\0.png");
    assertCond(is_file($sentinelFile), '#20 removeImageFile memblokir path traversal (..) & null byte');

    @mkdir(dirname($validSnapFile), 0755, true);
    file_put_contents($validSnapFile, 'dummy-png');
    DokumenPetaSnapshotModel::removeImageFile('uploads/map_snapshots/' . basename($validSnapFile));
    assertCond(!is_file($validSnapFile), '#20 removeImageFile tetap menghapus berkas sah dalam uploads/map_snapshots');

    // ------------------------------------------------------------------
    // 5. (#11) No double-escaping on Operasi detail title
    // ------------------------------------------------------------------
    $opCtrlSrc = (string)file_get_contents(CONTROLLERS_DIR . '/OperasiController.php');
    assertCond(
        str_contains($opCtrlSrc, "'title' => 'Operasi: ' . (\$operasi['nama_operasi'] ?? '')")
        && !str_contains($opCtrlSrc, "'title' => 'Operasi: ' . htmlspecialchars("),
        '#11 OperasiController tidak melakukan htmlspecialchars ganda pada title'
    );

    // ------------------------------------------------------------------
    // 6. (#4) Asset URL sanitization, icon deletion & server templates
    // ------------------------------------------------------------------
    assertCond(qrsar_sanitize_asset_url('javascript:alert(1)', '') === '', '#4 Tolak skema javascript: pada URL aset');
    assertCond(qrsar_sanitize_asset_url('data:text/html,<script>alert(1)</script>', '') === '', '#4 Tolak skema data:text/html pada URL aset');
    assertCond(qrsar_sanitize_asset_url('data:image/svg+xml;base64,PHN2Zz4=', '') === '', '#4 Tolak skema data:image/svg+xml pada URL aset');
    assertCond(qrsar_sanitize_asset_url('/uploads/../config/app.php', '') === '', '#4 Tolak traversal .. pada jalur aset');
    assertCond(qrsar_sanitize_asset_url('/uploads/satker_logo_1.webp', '') === '/uploads/satker_logo_1.webp', '#4 Terima jalur relatif /uploads/ sah');
    assertCond(qrsar_sanitize_asset_url('https://app.qrsar.my.id/uploads/logo.png', '') === 'https://app.qrsar.my.id/uploads/logo.png', '#4 Terima URL HTTPS sah');
    assertCond(qrsar_sanitize_asset_url('data:image/png;base64,aGVsbG8=', '') === 'data:image/png;base64,aGVsbG8=', '#4 Terima data:image/png;base64 sah');

    SettingsModel::updateSettings(['logo_url_2' => 'javascript:alert(1)']);
    assertCond((string)$db->query("SELECT logo_url_2 FROM AppSettings WHERE id = 1")->fetchColumn() === '', '#4 SettingsModel::updateSettings membersihkan URL logo_url_2 berbahaya');

    GisModel::savePoint([
        'id' => $testPtBadId,
        'id_operasi' => null,
        'lat' => -4.01,
        'lng' => 122.58,
        'label' => 'Uji XSS Icon',
        'pointType' => 'custom_upload',
        'customIconUrl' => 'javascript:alert(document.cookie)'
    ]);
    $rowBad = $db->query("SELECT pointType, customIconUrl FROM GisPoint WHERE id = " . $db->quote($testPtBadId))->fetch(PDO::FETCH_ASSOC);
    assertCond(($rowBad['pointType'] ?? '') === 'pin_umum' && ($rowBad['customIconUrl'] ?? null) === null, '#4 GisModel::savePoint menolak customIconUrl javascript: dan menurunkan ke pin_umum');

    // Test handleDeleteCustomIcon SQL exactness + invalid filename rejection
    $iconDelA = 'icon_1799999901_a1b2c3d4.png';
    $iconKeepB = 'icon_1799999901_a1b2c3d4_extra.png';
    GisModel::savePoint([
        'id' => $testPtDelA,
        'id_operasi' => null,
        'lat' => -4.02,
        'lng' => 122.58,
        'label' => 'Uji Del A',
        'pointType' => 'custom_upload',
        'customIconUrl' => '/uploads/icons/' . $iconDelA
    ]);
    GisModel::savePoint([
        'id' => $testPtDelB,
        'id_operasi' => null,
        'lat' => -4.03,
        'lng' => 122.58,
        'label' => 'Uji Keep B',
        'pointType' => 'custom_upload',
        'customIconUrl' => '/uploads/icons/' . $iconKeepB
    ]);

    $delInvalidCmd = escapeshellarg(PHP_BINARY ?: 'php') . ' -r ' . escapeshellarg(
        'require ' . var_export(ROOT_DIR . '/config/app.php', true) . ';'
        . 'AuthHelper::setSession(["id" => "USR-ADMIN", "username" => "admin", "role" => "ADMIN"]);'
        . 'GisApiController::handle("/gis/custom-icons", "DELETE", ["filename" => "../evil.png"]);'
    );
    $delInvalidOut = [];
    @exec($delInvalidCmd, $delInvalidOut);
    assertCond(str_contains(implode("\n", $delInvalidOut), '"success":false'), '#4 DELETE /gis/custom-icons menolak nama berkas non-pola/traversal');

    $delValidCmd = escapeshellarg(PHP_BINARY ?: 'php') . ' -r ' . escapeshellarg(
        'require ' . var_export(ROOT_DIR . '/config/app.php', true) . ';'
        . 'AuthHelper::setSession(["id" => "USR-ADMIN", "username" => "admin", "role" => "ADMIN"]);'
        . 'GisApiController::handle("/gis/custom-icons", "DELETE", ["filename" => ' . var_export($iconDelA, true) . ']);'
    );
    $delValidOut = [];
    @exec($delValidCmd, $delValidOut);
    $rowDelA = $db->query("SELECT pointType, customIconUrl FROM GisPoint WHERE id = " . $db->quote($testPtDelA))->fetch(PDO::FETCH_ASSOC);
    $rowKeepB = $db->query("SELECT pointType, customIconUrl FROM GisPoint WHERE id = " . $db->quote($testPtDelB))->fetch(PDO::FETCH_ASSOC);
    assertCond(
        str_contains(implode("\n", $delValidOut), '"success":true')
        && ($rowDelA['customIconUrl'] ?? null) === null
        && ($rowKeepB['customIconUrl'] ?? '') === '/uploads/icons/' . $iconKeepB,
        '#4 DELETE /gis/custom-icons hanya melepas ikon sasaran tanpa menimpa ikon mirip'
    );

    $apacheConf = (string)file_get_contents(ROOT_DIR . '/apache.conf.sample');
    $nginxConf = (string)file_get_contents(ROOT_DIR . '/nginx.conf.sample');
    assertCond(str_contains($apacheConf, 'Content-Security-Policy') && str_contains($apacheConf, 'sandbox'), '#4 apache.conf.sample menyertakan header CSP sandbox untuk uploads/');
    assertCond(str_contains($nginxConf, 'location = /router.php'), '#4 nginx.conf.sample memblokir akses ke /router.php');

    // ------------------------------------------------------------------
    // 7. (#5) /gis/tile-proxy URL & MIME validation
    // ------------------------------------------------------------------
    assertCond(GisApiController::isAllowedTileUrl('https://a.tile.openstreetmap.org/12/3441/2091.png') === true, '#5 tile-proxy mengizinkan tile HTTPS dari host whitelist');
    assertCond(GisApiController::isAllowedTileUrl('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/10/500/800') === true, '#5 tile-proxy mengizinkan Esri World Imagery HTTPS');
    assertCond(GisApiController::isAllowedTileUrl('http://a.tile.openstreetmap.org/12/3441/2091.png') === false, '#5 tile-proxy menolak skema HTTP tidak terenkripsi');
    assertCond(GisApiController::isAllowedTileUrl('https://a.tile.openstreetmap.org@127.0.0.1/1.png') === false, '#5 tile-proxy menolak URL dengan userinfo');
    assertCond(GisApiController::isAllowedTileUrl('https://a.tile.openstreetmap.org:8443/1.png') === false, '#5 tile-proxy menolak port non-443');
    assertCond(GisApiController::isAllowedTileUrl('https://169.254.169.254/latest/meta-data/') === false, '#5 tile-proxy menolak host internal/non-whitelist');
    assertCond(GisApiController::detectValidTileMime('<html><title>404 Not Found</title></html>') === null, '#5 detectValidTileMime menolak respons non-gambar');

    $gdOk = false;
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor(8, 8);
        ob_start();
        imagepng($im);
        $pngBytes = (string)ob_get_clean();
        unset($im);
        $gdOk = GisApiController::detectValidTileMime($pngBytes) === 'image/png';
    } else {
        $gdOk = true;
    }
    assertCond($gdOk, '#5 detectValidTileMime memverifikasi byte gambar PNG asli');

    // ------------------------------------------------------------------
    // 8. (#12 & #19) ALLOWED_HOSTS resolution & README PHP 8.2+
    // ------------------------------------------------------------------
    assertCond(qrsar_resolve_host(['HTTP_HOST' => 'app.qrsar.my.id'], 'app.qrsar.my.id,192.168.100.58:8085') === 'app.qrsar.my.id', '#12 ALLOWED_HOSTS mengizinkan host publik terdaftar');
    assertCond(qrsar_resolve_host(['HTTP_HOST' => '192.168.100.58:8085'], 'app.qrsar.my.id,192.168.100.58:8085') === '192.168.100.58:8085', '#12 ALLOWED_HOSTS mengizinkan host+port LAN terdaftar');
    assertCond(qrsar_resolve_host(['HTTP_HOST' => 'attacker.example.com'], 'app.qrsar.my.id,192.168.100.58:8085') === 'app.qrsar.my.id', '#12 Host tidak terdaftar jatuh ke entri pertama ALLOWED_HOSTS');
    assertCond(qrsar_resolve_host(['HTTP_HOST' => 'evil.com<script>'], '') === '127.0.0.1:8000', '#12 Host dengan karakter ilegal selalu ditolak');
    assertCond(qrsar_resolve_host(['HTTP_HOST' => '127.0.0.1:8000'], '') === '127.0.0.1:8000', '#12 Host lokal sah tetap bekerja bila ALLOWED_HOSTS kosong');

    $envExample = (string)file_get_contents(ROOT_DIR . '/.env.example');
    $readme = (string)file_get_contents(ROOT_DIR . '/README.md');
    assertCond(str_contains($envExample, 'ALLOWED_HOSTS='), '#12 .env.example mendokumentasikan ALLOWED_HOSTS');
    assertCond(str_contains($readme, 'PHP 8.2+') && !str_contains($readme, 'PHP 8.0+'), '#19 README.md memperbarui syarat minimum ke PHP 8.2+');

    // ------------------------------------------------------------------
    // 9. Gelombang 2 Safe Hardening (Cookie Secure, SVG regex, PDF timeout,
    //    BackupService DROP/TRUNCATE & flock, LogService IP, Offline Fonts)
    // ------------------------------------------------------------------
    $appConfigSrc = (string)file_get_contents(ROOT_DIR . '/config/app.php');
    assertCond(
        (bool)preg_match('/\$isSecure\s*=[^;]+HTTP_X_FORWARDED_PROTO/s', $appConfigSrc),
        'G2-1 config/app.php menyertakan HTTP_X_FORWARDED_PROTO pada $isSecure session cookie'
    );

    $adminCtrlSrc = (string)file_get_contents(CONTROLLERS_DIR . '/AdminController.php');
    $gisApiSrc = (string)file_get_contents(API_CONTROLLERS_DIR . '/GisApiController.php');
    assertCond(
        str_contains($adminCtrlSrc, '\bon[a-z]+\s*=') && str_contains($gisApiSrc, '\bon[a-z]+\s*='),
        'G2-2 AdminController & GisApiController menolak seluruh event handler SVG (\bon[a-z]+\s*=)'
    );
    assertCond(
        str_contains($adminCtrlSrc, "\$tab === 'backup' ? BackupService::getDbStats() : []")
        && str_contains($adminCtrlSrc, 'LogService::cleanOldLogs('),
        'G2-6 AdminController memuat getDbStats() hanya di tab backup & memanggil cleanOldLogs saat prune_audit'
    );

    $chromePdfSrc = (string)file_get_contents(ROOT_DIR . '/services/ChromePdfService.php');
    $renopsPdfSrc = (string)file_get_contents(ROOT_DIR . '/services/RenopsPdfService.php');
    $braPdfSrc = (string)file_get_contents(ROOT_DIR . '/services/BraPdfService.php');
    assertCond(
        str_contains($chromePdfSrc, 'proc_open') && str_contains($chromePdfSrc, 'proc_terminate')
        && str_contains($chromePdfSrc, 'LOCK_EX | LOCK_NB')
        && str_contains($renopsPdfSrc, 'ChromePdfService::renderHtmlToPdf')
        && str_contains($braPdfSrc, 'ChromePdfService::renderHtmlToPdf'),
        'G2-3 ChromePdfService bersama memakai proc_open berbatas waktu + flock(LOCK_EX|LOCK_NB) dan didelegasikan oleh BraPdfService & RenopsPdfService'
    );

    // G3-3 Uji perilaku kunci konkurensi PDF: saat lock dipegang proses lain, renderHtmlToPdf() langsung mengembalikan null
    $lockHeld = @fopen(ChromePdfService::getLockFilePath(), 'c');
    $lockedOk = $lockHeld !== false && @flock($lockHeld, LOCK_EX | LOCK_NB);
    $nullWhenBusy = null;
    if ($lockedOk) {
        $probeCmd = 'DB_DATABASE=' . escapeshellarg(getenv('DB_DATABASE') ?: 'qrsar_test') . ' '
            . escapeshellarg(PHP_BINARY) . ' -r '
            . escapeshellarg('require ' . var_export(ROOT_DIR . '/config/app.php', true) . '; var_export(BraPdfService::renderHtmlToPdf("<html><body>x</body></html>") === null);');
        $nullWhenBusy = trim((string)shell_exec($probeCmd));
        @flock($lockHeld, LOCK_UN);
        @fclose($lockHeld);
    }
    assertCond(
        $lockedOk && $nullWhenBusy === 'true',
        'G3-3 Saat kunci ChromePdfService dipegang proses lain, renderHtmlToPdf() langsung mengembalikan null tanpa menyalakan Chrome'
    );

    $rejectedMultiDrop = false;
    try {
        $refNorm = new ReflectionMethod(BackupService::class, 'normalizeAndValidateSqlStatement');
        $refNorm->invoke(null, "DROP TABLE IF EXISTS `Users`, `Operasi`");
    } catch (InvalidArgumentException) {
        $rejectedMultiDrop = true;
    }
    $backupSrc = (string)file_get_contents(ROOT_DIR . '/services/BackupService.php');
    assertCond(
        $rejectedMultiDrop && str_contains($backupSrc, 'flock('),
        'G2-4 BackupService menolak ekor tambahan pada DROP/TRUNCATE dan memasang flock() saat restoreFromSQL'
    );

    $logSvcSrc = (string)file_get_contents(ROOT_DIR . '/services/LogService.php');
    $gisMasterSrc = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');
    assertCond(
        !str_contains($logSvcSrc, 'HTTP_X_FORWARDED_FOR') && str_contains($logSvcSrc, 'AuthHelper::getClientIp()')
        && str_contains($gisMasterSrc, '${escapeHtml(msg)}')
        && str_contains($gisMasterSrc, '${escapeHtml(pt.label)}')
        && !str_contains($gisMasterSrc, '<span class="font-bold text-white">${pt.label}</span>'),
        'G2-5 LogService memakai AuthHelper::getClientIp(), showRouteSaveFeedback meng-escape msg, & tabel vertex poligon meng-escape pt.label'
    );

    // G3-2 Uji kegagalan unik username: updateUserProfile memakai username milik ADMIN harus mengembalikan false & me-rollback sandi
    $adminRowNow = UserModel::getById('USR-ADMIN');
    $userRowBefore = UserModel::getById('USR-USER');
    $dupFail = SettingsModel::updateUserProfile([
        'username' => (string)($adminRowNow['username'] ?? 'admin'),
        'nama' => 'Collision Test',
        'password' => 'SandiHarusRollback99',
    ]);
    $userRowAfter = UserModel::getById('USR-USER');
    assertCond(
        $dupFail === false && ($userRowAfter['password_hash'] ?? '') === ($userRowBefore['password_hash'] ?? ''),
        'G3-2 SettingsModel::updateUserProfile menolak duplikat username ADMIN (return false) dan me-rollback sandi'
    );
    assertCond(
        str_contains($adminCtrlSrc, '!SettingsModel::updateAdminProfile(')
        && str_contains($adminCtrlSrc, '!SettingsModel::updateUserProfile(')
        && str_contains($adminCtrlSrc, '!SettingsModel::resetUserToDefault(')
        && str_contains($adminCtrlSrc, '!SettingsModel::updateSettings('),
        'G3-2 AdminController memeriksa return bool untuk save_admin, save_user, reset_user, dan save_satker'
    );

    // G3-4 Zona waktu koneksi MySQL dikunci ke +08:00 (WITA)
    $mysqlTz = (string)$db->query('SELECT @@session.time_zone')->fetchColumn();
    assertCond($mysqlTz === '+08:00', 'G3-4 Koneksi MySQL mengunci @@session.time_zone ke +08:00 (WITA), didapat: ' . $mysqlTz);

    // G3-5 Session GC maxlifetime 12 jam + guard is_writable untuk storage/sessions
    assertCond(
        str_contains($appConfigSrc, "ini_set('session.gc_maxlifetime', '43200')")
        && str_contains($appConfigSrc, 'is_writable($sessionDir)'),
        'G3-5 config/app.php mengatur session.gc_maxlifetime=43200 dan guard is_writable untuk storage/sessions'
    );

    // G3-6 Tidak ada bocoran sandi literal Kendari304 di UI admin & reset_user membuat sandi acak
    $tabAdminSrc = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_admin.php');
    $tabUserSrc = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_user.php');
    assertCond(
        !str_contains($adminCtrlSrc, 'Kendari304')
        && !str_contains($tabAdminSrc, 'Kendari304')
        && !str_contains($tabUserSrc, 'Kendari304')
        && str_contains($adminCtrlSrc, 'random_bytes('),
        'G3-6 AdminController & views/admin bebas dari sandi literal Kendari304 dan reset_user memakai random_bytes()'
    );

    // G3-7 Pembersihan berkas sumber kolase yatim (src_*) > 24 jam tanpa menyentuh berkas baru
    $dokDir = UPLOADS_DIR . '/operasi_dokumentasi';
    if (!is_dir($dokDir)) {
        @mkdir($dokDir, 0775, true);
    }
    $oldOrphan = $dokDir . '/src_g3_orphan_old_' . bin2hex(random_bytes(4)) . '.jpg';
    $freshOrphan = $dokDir . '/src_g3_orphan_fresh_' . bin2hex(random_bytes(4)) . '.jpg';
    @file_put_contents($oldOrphan, 'dummy');
    @file_put_contents($freshOrphan, 'dummy');
    @touch($oldOrphan, time() - 90000);
    $prunedCount = OperasiDokumentasiModel::pruneOrphanSourceFiles(86400);
    $oldRemoved = !is_file($oldOrphan);
    $freshKept = is_file($freshOrphan);
    @unlink($oldOrphan);
    @unlink($freshOrphan);
    assertCond(
        $prunedCount >= 1 && $oldRemoved && $freshKept,
        'G3-7 OperasiDokumentasiModel::pruneOrphanSourceFiles menghapus src_* yatim > 24 jam dan mempertahankan berkas baru'
    );

    // G3-8 Keepalive ping operasi_form.js terikat aktivitas pengguna
    $opsFormJsSrc = (string)file_get_contents(ROOT_DIR . '/assets/js/pages/operasi_form.js');
    assertCond(
        str_contains($opsFormJsSrc, 'lastUserActivityAt') && str_contains($opsFormJsSrc, 'Date.now() - lastUserActivityAt < 300000'),
        'G3-8 Ping keepalive operasi_form.js hanya dikirim saat ada interaksi pengguna dalam 5 menit terakhir'
    );

    // G3-9 DokumenPetaSnapshotModel tidak menulis angka 0 palsu ke captured_by saat user_id berbentuk string (USR-ADMIN)
    $snapModelSrc = (string)file_get_contents(ROOT_DIR . '/models/DokumenPetaSnapshotModel.php');
    assertCond(
        str_contains($snapModelSrc, 'ctype_digit(') && !str_contains($snapModelSrc, "\$capturedBy = isset(\$_SESSION['user_id']) ? (int)\$_SESSION['user_id']"),
        'G3-9 DokumenPetaSnapshotModel menyimpan NULL (bukan 0 palsu) pada captured_by untuk user_id teks'
    );

    $loginViewSrc = (string)file_get_contents(ROOT_DIR . '/views/auth/login.php');
    $pubHeaderSrc = (string)file_get_contents(ROOT_DIR . '/views/layout/public_header.php');
    assertCond(
        !str_contains($loginViewSrc, 'fonts.googleapis.com') && !str_contains($pubHeaderSrc, 'fonts.googleapis.com'),
        'G2-7 views/auth/login.php & views/layout/public_header.php bebas dari CDN Google Fonts'
    );
} catch (Throwable $e) {
    assertCond(false, 'Pengecualian uji: ' . $e->getMessage());
} finally {
    $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = 'USR-USER'")->execute([':p' => $origUserHash]);
    $db->prepare("UPDATE AppSettings SET logo_url_2 = ? WHERE id = 1")->execute([$origLogo2 === false ? null : $origLogo2]);
    SettingsModel::clearCache();
    $db->exec("DELETE FROM GisPoint WHERE id LIKE 'pt-g1-%'");
    @unlink($sentinelFile);
    @unlink($validSnapFile);
    $_SESSION = [];
}

echo "\nTotal: {$total}, Lolos: {$passed}\n";
exit($passed === $total ? 0 : 1);
