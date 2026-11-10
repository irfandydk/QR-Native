<?php
/**
 * Test: API dokumen & foto RENOPS.
 * Bagian HTTP hanya jalan bila QRSAR_TEST_URL diisi (server & test memakai DB yang sama):
 *   QRSAR_TEST_URL=http://localhost:8091 DB_DATABASE=qrsar_test php tests/test_renops_dokumen_api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Akses Ditolak: Skrip pengujian hanya dapat dijalankan melalui CLI.' . PHP_EOL);
}

require_once __DIR__ . '/../config/app.php';

echo "=== TEST API DOKUMEN RENOPS ===\n\n";

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

function rejects(array $s): bool {
    try {
        RenopsDokumenApiController::sanitizeSections($s);
        return false;
    } catch (InvalidArgumentException) {
        return true;
    }
}

// 1. sanitizeSections
$ok = class_exists('RenopsDokumenApiController');
assertCondition($ok, 'RenopsDokumenApiController tersedia');
if ($ok) {
    assertCondition(rejects(['mission' => 'ok', 'hacker' => 'x']), 'Kunci tak dikenal ditolak');
    $r = RenopsDokumenApiController::sanitizeSections(['sru' => [['unsur' => 'A', 'tugas' => 'B', 'area' => '', 'pola' => '', 'track_spacing' => '', 'x' => 'buang']]]);
    assertCondition($r['sru'][0]['unsur'] === 'A' && !isset($r['sru'][0]['x']), 'Baris SRU lolos, field asing dibuang');
    assertCondition(rejects(['mission' => ['a']]), 'Kunci teks bernilai array ditolak');
    assertCondition(rejects(['koordinasi' => 'teks']), 'Kunci list bernilai string ditolak');
    assertCondition(rejects(['koordinasi' => [['a']]]), 'Item list non-string ditolak');
    assertCondition(rejects(['aset' => 'x']), 'Kunci baris bernilai string ditolak');
    $r = RenopsDokumenApiController::sanitizeSections(['mission' => null, 'waktu' => str_repeat('a', 25000)]);
    assertCondition($r['mission'] === null && mb_strlen($r['waktu']) === 20000, 'null = reset, string dipotong 20000');
    $r = RenopsDokumenApiController::sanitizeSections(['struktur' => ['sc' => 'A', 'zzz' => 'b', 'sru' => [['nama' => 'N', 'keterangan' => 'K']]]]);
    assertCondition($r['struktur']['sc'] === 'A' && !isset($r['struktur']['zzz']) && $r['struktur']['sru'][0]['nama'] === 'N', 'Struktur disaring');
    assertCondition(rejects(['struktur' => 'x']), 'Struktur non-objek ditolak');
}

// 1b. Logika unggah & validasi tanpa HTTP
$opsX = null;
try {
    $opsX = OperasiModel::create([
        'nama_operasi' => 'Operasi Uji Unggah', 'nama_pelapor' => 'Uji', 'lokasi_kejadian' => 'Teluk Kendari',
        'waktu_kejadian' => '2026-11-06 07:00:00', 'waktu_lapor' => '2026-11-06 07:30:00', 'waktu_berangkat' => '2026-11-06 07:45:00',
        'kronologi' => 'Uji', 'jumlah_pob' => 1,
    ]);
    $rX = RenopsModel::create(['id_operasi' => $opsX]);
    ob_start();
    $im = imagecreatetruecolor(20, 10);
    imagepng($im);
    $png = (string)ob_get_clean();
    $t1 = tempnam(sys_get_temp_dir(), 'rda');
    $t2 = tempnam(sys_get_temp_dir(), 'rda');
    file_put_contents($t1, $png);
    file_put_contents($t2, $png);
    $files = ['name' => ['a.png', 'b.png'], 'tmp_name' => [$t1, $t2], 'error' => [0, 0], 'size' => [strlen($png), strlen($png)]];
    $fail = RenopsDokumenApiController::storeUploads($rX, $files, ['Caption A', 'Caption B'], null, false);
    $list = RenopsFotoModel::listByRenops($rX);
    assertCondition($fail === null && count($list) === 2 && $list[0]['keterangan'] === 'Caption A' && $list[1]['keterangan'] === 'Caption B', 'Keterangan[] sampai ke RenopsFotoModel::store');
    assertCondition(RenopsDokumenApiController::storeUploads($rX, $files, [], null, true) !== null && count(RenopsFotoModel::listByRenops($rX)) === 2, 'Tanpa is_uploaded_file ditolak, tidak ada yang tersimpan');
    $n = RenopsFotoModel::MAX_PER_RENOPS + 1;
    $many = ['name' => array_fill(0, $n, 'x.png'), 'tmp_name' => array_fill(0, $n, $t1), 'error' => array_fill(0, $n, 0), 'size' => array_fill(0, $n, strlen($png))];
    $fail = RenopsDokumenApiController::storeUploads($rX, $many, [], null, false);
    assertCondition($fail !== null && count(RenopsFotoModel::listByRenops($rX)) === 2, 'Lebih dari batas per unggahan ditolak sebelum menyimpan');
} finally {
    if ($opsX) {
        OperasiModel::delete($opsX);
    }
}

// 1c. Handler API di proses terpisah (json() memanggil exit), sesi login tanpa HTTP
/** @return array{0:int,1:array} [status, body JSON] */
function runApi(string $path, string $method, array $input, array $session): array {
    $script = 'require ' . var_export(dirname(__DIR__) . '/config/app.php', true) . ';'
        . ' $_SESSION = array_merge($_SESSION ?? [], ' . var_export($session, true) . ');'
        . ' register_shutdown_function(function () { echo "\nSTATUS:" . (http_response_code() ?: 200); });'
        . ' RenopsDokumenApiController::handle(' . var_export($path, true) . ', ' . var_export($method, true) . ', ' . var_export($input, true) . ');';
    $out = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>&1');
    $pos = strrpos($out, "\nSTATUS:");
    return $pos === false ? [500, ['raw' => $out]] : [(int)substr($out, $pos + 8), json_decode(substr($out, 0, $pos), true) ?: ['raw' => $out]];
}

$opsY = null;
try {
    $opsY = OperasiModel::create([
        'nama_operasi' => 'Operasi Uji Handler', 'nama_pelapor' => 'Uji', 'lokasi_kejadian' => 'Teluk Kendari',
        'waktu_kejadian' => '2026-11-06 07:00:00', 'waktu_lapor' => '2026-11-06 07:30:00', 'waktu_berangkat' => '2026-11-06 07:45:00',
        'kronologi' => 'Uji', 'jumlah_pob' => 1,
    ]);
    $rY = RenopsModel::create(['id_operasi' => $opsY]);
    $sess = ['user_id' => (string)getDB()->query("SELECT id FROM `Users` WHERE is_active = 1 LIMIT 1")->fetchColumn(), 'auth_at' => time()];

    [$c, $j] = runApi("/renops/{$rY}/dokumen/terbit", 'POST', ['rev' => 0], $sess);
    assertCondition($c === 200 && ($j['baru'] ?? null) === true && ($j['versi'] ?? null) === 1, "Terbit pertama: respons baru=true (dapat {$c})");
    [$c, $j] = runApi("/renops/{$rY}/dokumen/terbit", 'POST', ['rev' => 0], $sess);
    assertCondition($c === 200 && ($j['baru'] ?? null) === false && ($j['versi'] ?? null) === 1, "Terbit tanpa perubahan: respons baru=false (dapat {$c})");

    $t = tempnam(sys_get_temp_dir(), 'rdy');
    ob_start();
    imagepng(imagecreatetruecolor(8, 8));
    file_put_contents($t, ob_get_clean());
    $fy = RenopsFotoModel::store($rY, $t, (int)filesize($t), null, 'Awal');
    @unlink($t);
    $fp = "/renops/{$rY}/foto/{$fy['id_foto']}";
    [$c] = runApi($fp, 'PUT', ['keterangan' => ['a']], $sess);
    assertCondition($c === 422, "PUT foto keterangan array 422 (dapat {$c})");
    [$c] = runApi($fp, 'PUT', ['urutan' => 'dua'], $sess);
    assertCondition($c === 422, "PUT foto urutan non-integer 422 (dapat {$c})");
    [$c] = runApi($fp, 'PUT', ['urutan' => 1.5], $sess);
    assertCondition($c === 422, "PUT foto urutan pecahan 422 (dapat {$c})");
    [$c] = runApi($fp, 'PUT', ['urutan' => 7], $sess);
    $row = RenopsFotoModel::listByRenops($rY)[0] ?? [];
    assertCondition($c === 200 && (int)$row['urutan'] === 7 && $row['keterangan'] === 'Awal', "PUT foto urutan saja: tersimpan, keterangan tetap (dapat {$c})");
    [$cStr] = runApi($fp, 'PUT', ['urutan' => '8'], $sess);
    $rowStr = RenopsFotoModel::listByRenops($rY)[0] ?? [];
    assertCondition($cStr === 200 && (int)$rowStr['urutan'] === 8, "PUT foto urutan string integer '8' diterima (dapat {$cStr})");

    // Uji endpoint reorder & bulk-delete
    $t2 = tempnam(sys_get_temp_dir(), 'rdy2');
    ob_start();
    imagepng(imagecreatetruecolor(8, 8));
    file_put_contents($t2, ob_get_clean());
    $fy2 = RenopsFotoModel::store($rY, $t2, (int)filesize($t2), null, 'Kedua');
    @unlink($t2);
    [$c, $j] = runApi("/renops/{$rY}/foto/reorder", 'PUT', ['ids' => [(int)$fy2['id_foto'], (int)$fy['id_foto']]], $sess);
    assertCondition($c === 200 && ($j['success'] ?? false) === true && (int)($j['foto'][0]['id_foto'] ?? 0) === (int)$fy2['id_foto'],
        "PUT /foto/reorder mengubah urutan secara atomik (dapat {$c})");
    [$c, $j] = runApi("/renops/{$rY}/foto/bulk-delete", 'POST', ['ids' => [(int)$fy2['id_foto'], (int)$fy['id_foto']]], $sess);
    assertCondition($c === 200 && ($j['deleted'] ?? 0) === 2 && count(RenopsFotoModel::listByRenops($rY)) === 0,
        "POST /foto/bulk-delete menghapus banyak foto sekaligus (dapat {$c})");
} catch (Throwable $e) {
    assertCondition(false, 'Handler API: ' . $e->getMessage());
} finally {
    if ($opsY) {
        OperasiModel::delete($opsY);
    }
}

// 2. HTTP
$base = rtrim((string)getenv('QRSAR_TEST_URL'), '/');
if ($base === '') {
    echo "\n [SKIP] Bagian HTTP dilewati: QRSAR_TEST_URL tidak diisi.\n";
    finish();
}

$cookieJar = tempnam(sys_get_temp_dir(), 'qrsar_dok_');
function http(string $method, string $path, array $opt = []): array {
    global $base, $cookieJar;
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => $cookieJar, CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_HTTPHEADER => $opt['headers'] ?? [], CURLOPT_TIMEOUT => 20,
    ]);
    if (isset($opt['post'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($opt['post']));
    }
    if (isset($opt['body'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['body']);
    }
    $raw = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hs = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    return [$code, substr($raw, 0, $hs), substr($raw, $hs)];
}

$db = getDB();
$oldHash = (string)$db->query("SELECT password_hash FROM `Users` WHERE id = 'USR-USER'")->fetchColumn();
$pass = 'Uji' . bin2hex(random_bytes(6));
$username = (string)$db->query("SELECT username FROM `Users` WHERE id = 'USR-USER'")->fetchColumn();
$idOps = null;
try {
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Operasi Uji API Dokumen', 'nama_pelapor' => 'Uji', 'lokasi_kejadian' => 'Teluk Kendari',
        'waktu_kejadian' => '2026-11-06 07:00:00', 'waktu_lapor' => '2026-11-06 07:30:00', 'waktu_berangkat' => '2026-11-06 07:45:00',
        'kronologi' => 'Uji', 'jumlah_pob' => 1,
    ]);
    $idRen = RenopsModel::create(['id_operasi' => $idOps]);
    $xhr = ['X-Requested-With: XMLHttpRequest'];

    [$c] = http('GET', "/api/renops/{$idRen}/dokumen", ['headers' => $xhr]);
    assertCondition($c === 401, "GET dokumen tanpa login 401 (dapat {$c})");

    $db->prepare("UPDATE `Users` SET password_hash = :p, is_active = 1 WHERE id = 'USR-USER'")->execute([':p' => password_hash($pass, PASSWORD_DEFAULT)]);
    [, , $b] = http('GET', '/login.php');
    preg_match('/name="csrf_token" value="([^"]+)"/', $b, $m);
    http('POST', '/login.php', ['post' => ['csrf_token' => $m[1] ?? '', 'username' => $username, 'password' => $pass]]);

    [$c] = http('PUT', "/api/renops/{$idRen}/dokumen", ['headers' => array_merge($xhr, ['Content-Type: application/json']), 'body' => '{"sections":{},"rev":0}']);
    assertCondition($c === 403, "PUT dokumen tanpa CSRF 403 (dapat {$c})");

    [$c, , $b] = http('GET', "/api/renops/{$idRen}/dokumen", ['headers' => $xhr]);
    $j = json_decode($b, true);
    assertCondition($c === 200 && isset($j['rev'], $j['doc']) && $j['has_unpublished'] === true, "GET dokumen login 200 (dapat {$c})");
    [$c] = http('GET', '/api/renops/99999999/dokumen', ['headers' => $xhr]);
    assertCondition($c === 404, "RENOPS tak ada 404 (dapat {$c})");

    [, , $b] = http('GET', "/api/renops/{$idRen}/dokumen", ['headers' => $xhr]);
    [, , $pg] = http('GET', "/renops_form.php?id_operasi={$idOps}&id_renops={$idRen}");
    preg_match('/name="csrf-token" content="([^"]+)"/', $pg, $cm);
    $hdr = array_merge($xhr, ['Content-Type: application/json', 'X-CSRF-Token: ' . ($cm[1] ?? '')]);
    [$c, , $b] = http('PUT', "/api/renops/{$idRen}/dokumen", ['headers' => $hdr, 'body' => '{"sections":{"mission":"x"},"rev":0}']);
    assertCondition($c === 200 && ($j = json_decode($b, true)) && $j['rev'] === 1, "PUT rev 0 sukses (dapat {$c})");
    [$c] = http('PUT', "/api/renops/{$idRen}/dokumen", ['headers' => $hdr, 'body' => '{"sections":{"mission":"y"},"rev":0}']);
    assertCondition($c === 409, "PUT rev usang 409 (dapat {$c})");
    [$c] = http('PUT', "/api/renops/{$idRen}/dokumen", ['headers' => $hdr, 'body' => '{"sections":{"mission":"y"}}']);
    assertCondition($c === 422, "PUT tanpa rev 422 (dapat {$c})");
    [$c] = http('PUT', "/api/renops/{$idRen}/dokumen", ['headers' => $hdr, 'body' => '{"sections":"x","rev":1}']);
    assertCondition($c === 422, "PUT sections non-array 422 (dapat {$c})");
    $idRen2 = RenopsModel::create(['id_operasi' => $idOps]);
    $t = tempnam(sys_get_temp_dir(), 'rdh');
    ob_start();
    imagepng(imagecreatetruecolor(8, 8));
    file_put_contents($t, ob_get_clean());
    $foto2 = RenopsFotoModel::store($idRen2, $t, (int)filesize($t), null, 'lain');
    [$c] = http('DELETE', "/api/renops/{$idRen}/foto/{$foto2['id_foto']}", ['headers' => $hdr]);
    assertCondition($c === 404, "DELETE foto RENOPS lain 404 (dapat {$c})");
} catch (Throwable $e) {
    assertCondition(false, 'Persiapan data uji: ' . $e->getMessage());
} finally {
    if ($idOps) {
        OperasiModel::delete($idOps);
    }
    $db->prepare("UPDATE `Users` SET password_hash = :p WHERE id = 'USR-USER'")->execute([':p' => $oldHash]);
    @unlink($cookieJar);
}

finish();
