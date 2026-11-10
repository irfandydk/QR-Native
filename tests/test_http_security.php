<?php
/**
 * Test: keamanan lapisan HTTP.
 * - Pencocokan origin CORS harus penuh (skema + host + port), bukan hanya host. (selalu jalan)
 * - Bagian HTTP hanya jalan bila QRSAR_TEST_URL diisi, mis.:
 *   QRSAR_TEST_URL=http://localhost:8091 DB_DATABASE=qrsar_test php tests/test_http_security.php
 *   Server dan test harus memakai database yang sama.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST KEAMANAN HTTP ===\n\n";

$testPassed = 0;
$testTotal = 0;

function assertCondition(bool $cond, string $message): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($cond) {
        $testPassed++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
    }
}

function finish(): void {
    global $testPassed, $testTotal;
    echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
    exit($testPassed === $testTotal ? 0 : 1);
}

// 1. CORS: origin penuh
$has = method_exists('BaseApiController', 'isAllowedOrigin');
assertCondition($has, 'BaseApiController menyediakan isAllowedOrigin');
if ($has) {
    $app = 'https://sar.example.id';
    assertCondition(BaseApiController::isAllowedOrigin('https://sar.example.id', $app, false), 'Origin identik diizinkan');
    assertCondition(BaseApiController::isAllowedOrigin('https://sar.example.id', $app . '/qrsar', false), 'Origin identik diizinkan walau BASE_URL memakai subfolder');
    assertCondition(!BaseApiController::isAllowedOrigin('http://sar.example.id', $app, false), 'Skema berbeda (http vs https) ditolak');
    assertCondition(!BaseApiController::isAllowedOrigin('https://sar.example.id:8443', $app, false), 'Port berbeda ditolak');
    assertCondition(!BaseApiController::isAllowedOrigin('https://sar.example.id.evil.test', $app, false), 'Host berawalan sama ditolak');
    assertCondition(!BaseApiController::isAllowedOrigin('null', $app, false), 'Origin "null" ditolak');
    assertCondition(!BaseApiController::isAllowedOrigin('http://localhost:5173', $app, false), 'localhost ditolak di produksi');
    assertCondition(BaseApiController::isAllowedOrigin('http://localhost:5173', 'http://localhost:8091', true), 'localhost port lain diizinkan saat pengembangan');
}

$base = rtrim((string)getenv('QRSAR_TEST_URL'), '/');
if ($base === '') {
    echo "\n [SKIP] Bagian HTTP dilewati: QRSAR_TEST_URL tidak diisi.\n";
    finish();
}

$cookieJar = tempnam(sys_get_temp_dir(), 'qrsar_http_');

/** @return array{0:int,1:string,2:string} [status, header, body] */
function http(string $method, string $path, array $opt = []): array {
    global $base, $cookieJar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_PATH_AS_IS => true,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_HTTPHEADER => $opt['headers'] ?? [],
        CURLOPT_TIMEOUT => 20,
    ]);
    if (isset($opt['post'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opt['post']));
    }
    $raw = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$code, substr($raw, 0, $hs), substr($raw, $hs)];
}

// 2. Jalur berkas
[$c, , $b] = http('GET', '/assets/../config/app.php');
assertCondition($c === 400 && !str_contains($b, '<?php'), "Dot segment lewat /assets/ ditolak 400 (dapat {$c})");
[$c] = http('GET', '/./config/app.php');
assertCondition($c === 400, "Dot segment tunggal ditolak 400 (dapat {$c})");
[$c] = http('GET', '/api/../config/app.php');
assertCondition($c === 400, "Dot segment lewat /api ditolak 400 (dapat {$c})");
[$c] = http('GET', '/assets%5c..%5cconfig%5capp.php');
assertCondition($c === 400, "Backslash ditolak 400 (dapat {$c})");
[$c] = http('GET', '/index.php%00.css');
assertCondition($c === 400, "Byte NUL ditolak 400 (dapat {$c})");
[$c] = http('GET', '/.gitignore');
assertCondition($c === 404, "Berkas di luar assets/uploads/favicon tidak disajikan (dapat {$c})");
[$c] = http('GET', '/public/index.php');
assertCondition($c === 404, "Skrip PHP di subfolder tidak di-include router (dapat {$c})");
[$c] = http('GET', '/assets/css/style.css');
assertCondition($c === 200, "Aset CSS tetap tersaji (dapat {$c})");
[$c] = http('GET', '/favicon.ico');
assertCondition($c === 200, "Favicon tetap tersaji (dapat {$c})");
[$c] = http('GET', '/login.php');
assertCondition($c === 200, "Halaman login tetap terbuka (dapat {$c})");

// 3. operasi.php tanpa aksi wajib login
[$c, $h] = http('GET', '/operasi.php');
assertCondition($c === 302 && str_contains($h, '/login.php'), "operasi.php tanpa login dialihkan ke login (dapat {$c})");

// 4. CORS lewat HTTP
[, $h] = http('GET', '/api/auth/ping', ['headers' => ['Origin: http://evil.example']]);
assertCondition(stripos($h, 'Access-Control-Allow-Origin') === false, 'Origin asing tidak mendapat header CORS');

// 5. SITREP/RENOPS terikat ke operasinya sendiri
$db = getDB();
$oldHash = (string)$db->query("SELECT password_hash FROM `Users` WHERE id = 'USR-USER'")->fetchColumn();
$pass = 'Uji' . bin2hex(random_bytes(6));
$db->prepare("UPDATE `Users` SET password_hash = :p, is_active = 1 WHERE id = 'USR-USER'")->execute([':p' => password_hash($pass, PASSWORD_DEFAULT)]);
$username = (string)$db->query("SELECT username FROM `Users` WHERE id = 'USR-USER'")->fetchColumn();

$ops = [];
try {
    foreach (['A', 'B'] as $k) {
        $ops[$k] = OperasiModel::create([
            'nama_operasi' => "Operasi Uji HTTP {$k}", 'nama_pelapor' => 'Uji', 'lokasi_kejadian' => 'Teluk Kendari',
            'waktu_kejadian' => '2026-11-06 07:00:00', 'waktu_lapor' => '2026-11-06 07:30:00', 'waktu_berangkat' => '2026-11-06 07:45:00',
            'kronologi' => 'Uji', 'jumlah_pob' => 1,
        ]);
    }
    $lapB = LaporanModel::create(['id_operasi' => $ops['B'], 'jenis_laporan' => 'PERKEMBANGAN']);
    $renB = RenopsModel::create(['id_operasi' => $ops['B']]);

    [, , $b] = http('GET', '/login.php');
    preg_match('/name="csrf_token" value="([^"]+)"/', $b, $m);
    [$c] = http('POST', '/login.php', ['post' => ['csrf_token' => $m[1] ?? '', 'username' => $username, 'password' => $pass]]);
    assertCondition($c === 302, "Login uji berhasil (dapat {$c})");

    [$c] = http('GET', "/sitrep_form.php?id_operasi={$ops['A']}&id_laporan={$lapB}");
    assertCondition($c === 302, "Laporan operasi B tidak bisa dibuka lewat operasi A (dapat {$c})");
    [$c] = http('GET', "/sitrep_form.php?id_operasi={$ops['B']}&id_laporan={$lapB}");
    assertCondition($c === 200, "Laporan dibuka lewat operasinya sendiri (dapat {$c})");
    [$c] = http('GET', "/renops_form.php?id_operasi={$ops['A']}&id_renops={$renB}");
    assertCondition($c === 302, "RENOPS operasi B tidak bisa dibuka lewat operasi A (dapat {$c})");
    [$c] = http('GET', "/renops_form.php?id_operasi={$ops['B']}&id_renops={$renB}");
    assertCondition($c === 200, "RENOPS dibuka lewat operasinya sendiri (dapat {$c})");

    // 6. Akun bersandi bawaan: hanya boleh ganti sandi (halaman & API, termasuk API admin)
    $oldAdminHash = (string)$db->query("SELECT password_hash FROM `Users` WHERE id = 'USR-ADMIN'")->fetchColumn();
    $adminName = (string)$db->query("SELECT username FROM `Users` WHERE id = 'USR-ADMIN'")->fetchColumn();
    try {
        $loginAs = function (string $user, string $pw) use ($cookieJar): int {
            file_put_contents($cookieJar, '');
            [, , $b] = http('GET', '/login.php');
            preg_match('/name="csrf_token" value="([^"]+)"/', $b, $m);
            [$c] = http('POST', '/login.php', ['post' => ['csrf_token' => $m[1] ?? '', 'username' => $user, 'password' => $pw]]);
            return $c;
        };
        $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = 'USR-USER'")->execute([':p' => password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT)]);
        $loginAs($username, DEFAULT_RESET_PASSWORD);
        [$c] = http('GET', '/profile.php');
        assertCondition($c === 200, "USER bersandi bawaan tetap bisa membuka profile.php (dapat {$c})");
        [$c, $h] = http('GET', '/operasi.php');
        assertCondition($c === 302 && str_contains($h, '/profile.php'), "USER bersandi bawaan diarahkan ke profile.php (dapat {$c})");

        $db->prepare("UPDATE `Users` SET password_hash = :p, is_active = 1 WHERE id = 'USR-ADMIN'")->execute([':p' => password_hash(DEFAULT_RESET_PASSWORD, PASSWORD_DEFAULT)]);
        $loginAs($adminName, DEFAULT_RESET_PASSWORD);
        [$c, , $b] = http('PUT', '/api/settings', ['headers' => ['X-Requested-With: XMLHttpRequest', 'Content-Type: application/json']]);
        assertCondition($c === 403 && str_contains($b, 'PASSWORD_CHANGE_REQUIRED'), "ADMIN bersandi bawaan tidak bisa ubah pengaturan lewat API (dapat {$c})");
        [$c, , $b] = http('GET', '/api/settings', ['headers' => ['X-Requested-With: XMLHttpRequest']]);
        assertCondition(!str_contains($b, 'gemini_api_key'), "ADMIN bersandi bawaan tidak menerima pengaturan rahasia (dapat {$c})");
    } finally {
        $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = 'USR-ADMIN'")->execute([':p' => $oldAdminHash]);
    }
} catch (Throwable $e) {
    assertCondition(false, 'Persiapan data uji: ' . $e->getMessage());
} finally {
    foreach ($ops as $id) {
        OperasiModel::delete($id);
    }
    $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = 'USR-USER'")->execute([':p' => $oldHash]);
    @unlink($cookieJar);
}

finish();
