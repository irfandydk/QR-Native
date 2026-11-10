<?php
/**
 * Test: RenopsFotoModel (validasi unggahan, re-encode, batas, hapus berkas).
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../models/OperasiModel.php';
require_once __DIR__ . '/../models/RenopsModel.php';
require_once __DIR__ . '/../models/RenopsFotoModel.php';
require_once __DIR__ . '/../api/controllers/BaseApiController.php';
require_once __DIR__ . '/../api/controllers/RenopsDokumenApiController.php';

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

function throwsInvalid(callable $fn): bool {
    try { $fn(); } catch (InvalidArgumentException) { return true; }
    return false;
}

echo "=== TEST RENOPS FOTO UPLOAD ===\n\n";

$tmp = sys_get_temp_dir() . '/renops_foto_' . bin2hex(random_bytes(4));
mkdir($tmp);
$idOps = 0;
$idR = 0;
$created = [];
try {
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Operasi Uji Foto',
        'nama_pelapor' => 'Uji',
        'lokasi_kejadian' => 'Perairan Uji',
        'waktu_kejadian' => '2026-10-21 06:00:00',
        'waktu_lapor' => '2026-10-21 06:30:00',
    ]);
    $idR = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-10-21', 'srus' => []]);

    $png = "{$tmp}/besar.png";
    $im = imagecreatetruecolor(3000, 2000);
    imagepng($im, $png);
    $phpFake = "{$tmp}/shell.jpg";
    file_put_contents($phpFake, '<?php echo 1;');
    // JPEG valid + payload PHP ditempel di belakang
    $tail = "{$tmp}/tail.jpg";
    $im2 = imagecreatetruecolor(50, 50);
    imagejpeg($im2, $tail);
    file_put_contents($tail, '<?php system($_GET["c"]); ?>', FILE_APPEND);
    // MIME benar (PNG header) tapi tidak bisa didecode GD
    $rusak = "{$tmp}/rusak.png";
    file_put_contents($rusak, "\x89PNG\r\n\x1a\n" . str_repeat("\0", 20));

    // PNG kecil dengan IHDR 20000x20000 (CRC valid): harus ditolak sebelum decode.
    $ihdr = pack('NNCCCCC', 20000, 20000, 8, 2, 0, 0, 0);
    $bomb = "{$tmp}/bomb.png";
    file_put_contents($bomb, "\x89PNG\r\n\x1a\n" . pack('N', 13) . 'IHDR' . $ihdr . pack('N', crc32('IHDR' . $ihdr)));
    assertCondition(throwsInvalid(fn() => RenopsFotoModel::store($idR, $bomb, filesize($bomb), null)), 'PNG berdimensi >40 MP ditolak sebelum decode');

    $memAwal = (string)ini_get('memory_limit');
    ini_set('memory_limit', '256M');
    $row = RenopsFotoModel::store($idR, $png, filesize($png), null, 'Persiapan tim');
    assertCondition(ini_parse_quantity((string)ini_get('memory_limit')) >= 512 * 1024 * 1024, 'memory_limit dinaikkan ke 512M sebelum decode');
    ini_set('memory_limit', $memAwal);
    assertCondition(str_contains((string)file_get_contents(ROOT_DIR . '/models/RenopsFotoModel.php'), 'declare(strict_types=1);'), 'RenopsFotoModel memakai strict_types');
    assertCondition(!str_contains((string)file_get_contents(ROOT_DIR . '/models/RenopsFotoModel.php'), 'imagedestroy'), 'RenopsFotoModel bebas dari imagedestroy yang deprecated di PHP 8.5');
    $created[] = ROOT_DIR . '/' . $row['path'];
    [$w, $h, $t] = getimagesize(ROOT_DIR . '/' . $row['path']);
    assertCondition($t === IMAGETYPE_JPEG && max($w, $h) === 1600, 'Di-encode ulang ke JPEG sisi terpanjang 1600');
    assertCondition((bool)preg_match('#^uploads/renops_foto/[0-9a-f]{32}\.jpg$#', $row['path']), 'Nama acak di uploads/renops_foto');
    assertCondition(is_file(ROOT_DIR . '/uploads/renops_foto/index.php'), 'index.php 403 dibuat di folder foto');
    assertCondition($row['keterangan'] === 'Persiapan tim' && (int)$row['urutan'] === 1, 'Keterangan dan urutan tersimpan');

    assertCondition(throwsInvalid(fn() => RenopsFotoModel::store($idR, $phpFake, filesize($phpFake), null)), 'Berkas bukan gambar ditolak walau berekstensi .jpg');
    assertCondition(throwsInvalid(fn() => RenopsFotoModel::store($idR, $rusak, filesize($rusak), null)), 'PNG rusak yang tak bisa didecode GD ditolak');
    assertCondition(throwsInvalid(fn() => RenopsFotoModel::store($idR, $png, RenopsFotoModel::MAX_BYTES + 1, null)), 'Lebih dari 5 MB ditolak');

    $row2 = RenopsFotoModel::store($idR, $tail, filesize($tail), 'tester');
    $created[] = ROOT_DIR . '/' . $row2['path'];
    assertCondition(!str_contains((string)file_get_contents(ROOT_DIR . '/' . $row2['path']), '<?php'), 'Payload tambahan hilang setelah re-encode');
    assertCondition((int)$row2['urutan'] === 2, 'urutan = max+1');

    $ids = array_column(RenopsFotoModel::listByRenops($idR), 'id_foto');
    assertCondition(array_map('intval', $ids) === [(int)$row['id_foto'], (int)$row2['id_foto']], 'listByRenops terurut urutan, id_foto');

    assertCondition(RenopsFotoModel::update((int)$row2['id_foto'], $idR, 'Baru', 0) === true
        && RenopsFotoModel::listByRenops($idR)[0]['keterangan'] === 'Baru', 'update mengubah keterangan dan urutan');
    assertCondition(RenopsFotoModel::update((int)$row2['id_foto'], $idR + 999999, 'X', 0) === false, 'update ditolak untuk RENOPS lain');

    for ($i = 2; $i < RenopsFotoModel::MAX_PER_RENOPS; $i++) {
        $r = RenopsFotoModel::store($idR, $tail, filesize($tail), null);
        $created[] = ROOT_DIR . '/' . $r['path'];
    }
    assertCondition(throwsInvalid(fn() => RenopsFotoModel::store($idR, $tail, filesize($tail), null)), 'Foto ke-13 ditolak');

    assertCondition(RenopsFotoModel::delete((int)$row['id_foto'], $idR + 999999) === false && is_file(ROOT_DIR . '/' . $row['path']), 'delete ditolak untuk RENOPS lain');
    assertCondition(RenopsFotoModel::delete((int)$row['id_foto'], $idR) && !is_file(ROOT_DIR . '/' . $row['path']), 'Hapus foto meng-unlink berkas');

    // --- Uji Format GIF ---
    $gif = "{$tmp}/sample.gif";
    $imGif = imagecreate(100, 80);
    imagecolorallocate($imGif, 0, 150, 255);
    imagegif($imGif, $gif);
    unset($imGif);
    $rowGif = RenopsFotoModel::store($idR, $gif, filesize($gif), null, 'Foto format GIF');
    $created[] = ROOT_DIR . '/' . $rowGif['path'];
    assertCondition(is_file(ROOT_DIR . '/' . $rowGif['path']), 'Format GIF berhasil disimpan');
    [$gw, $gh, $gt] = getimagesize(ROOT_DIR . '/' . $rowGif['path']);
    assertCondition($gt === IMAGETYPE_JPEG, 'GIF di-convert ke JPEG');

    // --- Uji Batch Upload 12 Foto Sekaligus via RenopsDokumenApiController ---
    $idR2 = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-10-22', 'srus' => []]);
    $batchNames = [];
    $batchTmps = [];
    $batchSizes = [];
    $batchErrors = [];
    $batchKets = [];
    for ($i = 1; $i <= 12; $i++) {
        $fPath = "{$tmp}/batch_{$i}.png";
        $imB = imagecreatetruecolor(100, 100);
        imagepng($imB, $fPath);
        unset($imB);
        $batchNames[] = "dokumentasi_{$i}.png";
        $batchTmps[] = $fPath;
        $batchSizes[] = filesize($fPath);
        $batchErrors[] = UPLOAD_ERR_OK;
        $batchKets[] = "Dokumentasi ke-{$i}";
    }
    $fBatch = [
        'name' => $batchNames,
        'tmp_name' => $batchTmps,
        'size' => $batchSizes,
        'error' => $batchErrors,
    ];
    $failBatch = RenopsDokumenApiController::storeUploads($idR2, $fBatch, $batchKets, null, false);
    assertCondition($failBatch === null, 'Batch upload 12 foto sekaligus berhasil tanpa error');
    $uploadedList = RenopsFotoModel::listByRenops($idR2);
    assertCondition(count($uploadedList) === 12, 'Tepat 12 foto tersimpan di database RenopsFoto');
    foreach ($uploadedList as $u) {
        $created[] = ROOT_DIR . '/' . $u['path'];
    }

    // --- Uji Batch Reorder & Bulk Delete ---
    $origIds = array_map(fn($x) => (int)$x['id_foto'], $uploadedList);
    $revIds = array_reverse($origIds);
    // Sertakan ID asing (milik $idR) untuk memastikan ID milik RENOPS lain diabaikan
    $foreignFotoId = (int)($rowGif['id_foto'] ?? 999999);
    RenopsFotoModel::reorder($idR2, array_merge([$foreignFotoId], $revIds));
    $reorderedList = RenopsFotoModel::listByRenops($idR2);
    $newIds = array_map(fn($x) => (int)$x['id_foto'], $reorderedList);
    assertCondition($newIds === $revIds && (int)$reorderedList[0]['urutan'] === 1 && (int)$reorderedList[11]['urutan'] === 12,
        'RenopsFotoModel::reorder membalik urutan 12 foto menjadi 1..12 dan mengabaikan ID foto milik RENOPS lain');

    $delIds = [$newIds[0], $newIds[1]];
    $delPaths = [ROOT_DIR . '/' . $reorderedList[0]['path'], ROOT_DIR . '/' . $reorderedList[1]['path']];
    $deletedCount = RenopsFotoModel::deleteMany($idR2, [$delIds[0], $delIds[1], $foreignFotoId]);
    assertCondition($deletedCount === 2 && count(RenopsFotoModel::listByRenops($idR2)) === 10
        && !is_file($delPaths[0]) && !is_file($delPaths[1]) && is_file(ROOT_DIR . '/' . $rowGif['path']),
        'RenopsFotoModel::deleteMany menghapus 2 foto beserta berkas fisiknya dan melindungi foto RENOPS lain');

    // Uji penolakan jika batch melebihi 12 file
    $batch13 = $fBatch;
    $batch13['name'][] = 'extra.png';
    $batch13['tmp_name'][] = $batchTmps[0];
    $batch13['size'][] = $batchSizes[0];
    $batch13['error'][] = UPLOAD_ERR_OK;
    $fail13 = RenopsDokumenApiController::storeUploads($idR2, $batch13, [], null, false);
    assertCondition($fail13 === 'Maksimal 12 foto per unggahan.', 'Batch > 12 foto ditolak dengan pesan formal');

    // Uji penolakan per-file > 5.5 MB (5,767,168 bytes) dengan nama file di pesan
    $fJumbo = [
        'name' => ['foto_jumbo.jpg'],
        'tmp_name' => [$batchTmps[0]],
        'size' => [RenopsFotoModel::MAX_BYTES + 1],
        'error' => [UPLOAD_ERR_OK],
    ];
    $failJumbo = RenopsDokumenApiController::storeUploads($idR2, $fJumbo, [], null, false);
    assertCondition(str_contains((string)$failJumbo, "'foto_jumbo.jpg'") && str_contains((string)$failJumbo, '5 MB'), 'File > 5.5 MB ditolak dengan menyertakan nama file');

    // Uji overflow post_max_size (CONTENT_LENGTH > 0 tapi $_POST dan $_FILES kosong)
    $userId = (string)getDB()->query("SELECT id FROM `Users` WHERE is_active = 1 LIMIT 1")->fetchColumn();
    $scriptOverflow = 'require ' . var_export(dirname(__DIR__) . '/config/app.php', true) . ';'
        . ' $_SESSION["user_id"] = ' . var_export($userId, true) . ';'
        . ' $_SESSION["auth_at"] = ' . time() . ';'
        . ' $_SERVER["REQUEST_METHOD"] = "POST";'
        . ' $_SERVER["CONTENT_LENGTH"] = "70000000";'
        . ' $_POST = []; $_FILES = [];'
        . ' register_shutdown_function(function () { echo "\nSTATUS:" . (http_response_code() ?: 200); });'
        . ' RenopsDokumenApiController::handle("/renops/' . $idR2 . '/foto", "POST", []);';
    $outOverflow = (string)shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($scriptOverflow) . ' 2>&1');
    assertCondition(str_contains($outOverflow, 'STATUS:413') && str_contains($outOverflow, 'post_max_size'), 'Overflow post_max_size mengembalikan HTTP 413 dengan pesan ramah');
} finally {
    if ($idOps) {
        OperasiModel::delete($idOps); // cascade + unlink foto
    }
    foreach ($created as $f) { if (is_file($f)) @unlink($f); }
    foreach (glob("{$tmp}/*") ?: [] as $f) { @unlink($f); }
    @rmdir($tmp);
}

echo "\nHasil: {$testPassed}/{$testTotal} lulus\n";
exit($testPassed === $testTotal ? 0 : 1);
