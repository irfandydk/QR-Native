<?php
/**
 * Test: Backend Arsip Dokumentasi Kolase Operasi SAR (Model, Validasi MIME/Slot, Cascade Delete, BackupService, API)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

function assertDok(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== TEST BACKEND DOKUMENTASI KOLASE OPERASI SAR ===\n\n";

assertDok(class_exists('OperasiDokumentasiModel'), 'Class OperasiDokumentasiModel tersedia via autoloader');
assertDok(class_exists('OperasiDokumentasiApiController'), 'Class OperasiDokumentasiApiController tersedia via autoloader');
assertDok(
    OperasiDokumentasiModel::MAX_VIDEO_BYTES === 157286400 && OperasiDokumentasiModel::MAX_FINAL_BYTES === 157286400,
    'OperasiDokumentasiModel::MAX_FINAL_BYTES & MAX_VIDEO_BYTES mendukung hasil kolase hingga 150 MB'
);
assertDok(
    in_array('OperasiDokumentasi', BackupService::MANAGED_TABLES, true),
    'BackupService::MANAGED_TABLES mencakup tabel OperasiDokumentasi'
);

if (!class_exists('OperasiDokumentasiModel')) {
    echo "\nLulus: {$passed}, Gagal: {$failed}\n";
    exit(1);
}

OperasiDokumentasiModel::ensureSchemaUpdated(true);

$testOpsId = OperasiModel::create([
    'nama_operasi' => 'UJI DOKUMENTASI KOLASE SAR KENDARI',
    'jenis_kejadian' => 'Kecelakaan Kapal',
    'waktu_kejadian' => date('Y-m-d H:i:s'),
    'lokasi_kejadian' => 'Perairan Teluk Kendari',
    'koordinat' => "03° 58' 30\" S - 122° 35' 15\" E",
    'kronologi' => 'Data uji kolase dokumentasi operasi SAR.',
]);
assertDok($testOpsId > 0, "Operasi uji berhasil dibuat (ID: {$testOpsId})");

$tmpFiles = [];
$makeTmp = function (string $bytes, string $suffix) use (&$tmpFiles): string {
    $path = sys_get_temp_dir() . '/qrsar_dok_test_' . bin2hex(random_bytes(6)) . $suffix;
    file_put_contents($path, $bytes);
    $tmpFiles[] = $path;
    return $path;
};

// Buat JPEG valid kecil via GD
$im = imagecreatetruecolor(120, 180);
imagefill($im, 0, 0, imagecolorallocate($im, 249, 99, 2));
ob_start();
imagejpeg($im, null, 85);
$jpegBytes = (string)ob_get_clean();
unset($im);

// Buat payload WebM EBML header valid minimal (untuk uji video MIME/magic)
$webmHeader = "\x1A\x45\xDF\xA3\x9F\x42\x86\x81\x01\x42\xF7\x81\x01\x42\xF2\x81\x04\x42\xF3\x81\x08\x42\x82\x84webm\x42\x87\x81\x04\x42\x85\x81\x02";
$webmBytes = $webmHeader . str_repeat("\x00", 256);

try {
    // 1. Simpan kolase gambar valid (6 slot vertikal 2:3)
    $tmpImg = $makeTmp($jpegBytes, '.jpg');
    $savedImg = OperasiDokumentasiModel::store($testOpsId, [
        'judul' => 'Dok Operasi SAR H1 Uji Kolase Foto',
        'hari_ke' => 'H1',
        'tanggal_label' => 'Senin, 23 November 2026',
        'aspect_ratio' => '2:3',
        'orientation' => 'vertical',
        'slot_count' => 6,
        'layout_preset' => 'v_6_2x3',
        'width_px' => 1080,
        'height_px' => 1620,
    ], ['tmp_name' => $tmpImg, 'size' => strlen($jpegBytes), 'name' => 'kolase.jpg'], null, 'USR-ADMIN');

    assertDok(
        !empty($savedImg['id_dokumentasi']) && ($savedImg['media_type'] ?? '') === 'image' && is_file(ROOT_DIR . '/' . $savedImg['file_path']),
        'Store gambar kolase valid berhasil tersimpan ke DB dan disk'
    );

    // 2. Simpan kolase video valid (WebM + thumbnail JPEG, 8 slot horizontal 16:9)
    $tmpVid = $makeTmp($webmBytes, '.webm');
    $tmpThumb = $makeTmp($jpegBytes, '.jpg');
    $savedVid = OperasiDokumentasiModel::store($testOpsId, [
        'judul' => 'Dok Operasi SAR H1 Uji Kolase Video',
        'hari_ke' => 'H1',
        'tanggal_label' => 'Senin, 23 November 2026',
        'aspect_ratio' => '16:9',
        'orientation' => 'horizontal',
        'slot_count' => 8,
        'layout_preset' => 'h_8_4x2',
        'width_px' => 1920,
        'height_px' => 1080,
    ], ['tmp_name' => $tmpVid, 'size' => strlen($webmBytes), 'name' => 'kolase.webm'], ['tmp_name' => $tmpThumb, 'size' => strlen($jpegBytes), 'name' => 'thumb.jpg'], 'USR-ADMIN');

    assertDok(
        !empty($savedVid['id_dokumentasi'])
            && ($savedVid['media_type'] ?? '') === 'video'
            && is_file(ROOT_DIR . '/' . $savedVid['file_path'])
            && !empty($savedVid['thumb_path'])
            && is_file(ROOT_DIR . '/' . $savedVid['thumb_path']),
        'Store video kolase WebM + thumbnail poster berhasil tersimpan ke DB dan disk'
    );

    // 3. Validasi jumlah slot di luar 4..12 ditolak
    $slotLowRejected = false;
    try {
        OperasiDokumentasiModel::store($testOpsId, ['judul' => 'Terlalu Sedikit', 'slot_count' => 3], ['tmp_name' => $tmpImg, 'size' => strlen($jpegBytes), 'name' => 'a.jpg'], null, 'USR-ADMIN');
    } catch (InvalidArgumentException) {
        $slotLowRejected = true;
    }
    assertDok($slotLowRejected, 'slot_count = 3 (kurang dari 4) ditolak dengan InvalidArgumentException');

    $slotHighRejected = false;
    try {
        OperasiDokumentasiModel::store($testOpsId, ['judul' => 'Terlalu Banyak', 'slot_count' => 13], ['tmp_name' => $tmpImg, 'size' => strlen($jpegBytes), 'name' => 'a.jpg'], null, 'USR-ADMIN');
    } catch (InvalidArgumentException) {
        $slotHighRejected = true;
    }
    assertDok($slotHighRejected, 'slot_count = 13 (lebih dari 12) ditolak dengan InvalidArgumentException');

    // 4. Keamanan: file PHP / payload jahat menyamar gambar/video ditolak
    $tmpEvil = $makeTmp("<?php echo 'pwned'; ?>", '.jpg');
    $evilRejected = false;
    try {
        OperasiDokumentasiModel::store($testOpsId, ['judul' => 'Evil', 'slot_count' => 4], ['tmp_name' => $tmpEvil, 'size' => 22, 'name' => 'evil.jpg'], null, 'USR-ADMIN');
    } catch (InvalidArgumentException) {
        $evilRejected = true;
    }
    assertDok($evilRejected, 'Payload PHP menyamar sebagai gambar ditolak oleh validasi MIME ketat');

    // 5. OperasiModel::getById menyertakan daftar dokumentasi
    $loadedOps = OperasiModel::getById($testOpsId);
    assertDok(
        isset($loadedOps['dokumentasi']) && count($loadedOps['dokumentasi']) === 2,
        'OperasiModel::getById() memuat relasi dokumentasi (2 item)'
    );

    // 5b. Verifikasi judul & tanggal bawaan Studio tidak menduplikasi nama hari atau frasa "Operasi SAR"
    $tglCheck = TemplateHelper::formatTanggalIndo('2026-11-23');
    assertDok(
        !preg_match('/^([A-Za-z]+),\s+\1,/i', $tglCheck),
        "Format tanggal bawaan Studio bebas duplikasi nama hari ({$tglCheck})"
    );
    $opsCtrlSrc = (string)file_get_contents(ROOT_DIR . '/controllers/OperasiController.php');
    assertDok(
        str_contains($opsCtrlSrc, "preg_replace('/^Operasi\\s+SAR\\s+/i'")
            && !str_contains($opsCtrlSrc, '$weekdayIndo . \', \' . TemplateHelper::formatTanggalIndo'),
        'OperasiController::dokumentasiStudio() menghindari duplikasi nama hari & frasa Operasi SAR pada judul bawaan'
    );

    // 5c. Verifikasi kolom AppSettings.no_wa, Pengaturan Satker WA, dan format EMERGENCY CALL (115 / telp / WA)
    $db = getDB();
    $waCols = $db->query("SHOW COLUMNS FROM AppSettings LIKE 'no_wa'")->fetchAll();
    assertDok(!empty($waCols), 'Kolom AppSettings.no_wa tersedia di skema database');

    $satkerView = (string)file_get_contents(ROOT_DIR . '/views/admin/partials/tab_satker.php');
    assertDok(
        str_contains($satkerView, 'name="no_wa"') && !str_contains($satkerView, 'name="frekuensi_default"'),
        'tab_satker.php mengganti input frekuensi_default menjadi input Nomor WhatsApp (no_wa)'
    );

    assertDok(
        method_exists('OperasiController', 'formatEmergencyCallLine'),
        'OperasiController::formatEmergencyCallLine() tersedia untuk menyusun format 115 / telp / WA'
    );
    if (method_exists('OperasiController', 'formatEmergencyCallLine')) {
        $lineWithWa = OperasiController::formatEmergencyCallLine('0401 3196 557', '08114034115');
        assertDok(
            $lineWithWa === '115 / 0401 3196 557 / WA: 08114034115',
            "Format emergency call dengan WA tepat: {$lineWithWa}"
        );
        $lineDup115 = OperasiController::formatEmergencyCallLine('(0401) 3122115 / Emergency 115', '08114034115');
        assertDok(
            $lineDup115 === '115 / (0401) 3122115 / WA: 08114034115',
            "Format emergency call tidak menduplikasi 115: {$lineDup115}"
        );
        $lineLeading115 = OperasiController::formatEmergencyCallLine('115 / 0401 3196 557', '08114034115');
        assertDok(
            $lineLeading115 === '115 / 0401 3196 557 / WA: 08114034115',
            "Format emergency call dengan prefix 115 awal tetap rapi: {$lineLeading115}"
        );
        $lineNoWa = OperasiController::formatEmergencyCallLine('0401 3196 557', '');
        assertDok(
            $lineNoWa === '115 / 0401 3196 557' && !str_contains($lineNoWa, 'WA:'),
            "Format emergency call tanpa WA bersih tanpa suffix WA: {$lineNoWa}"
        );
    }

    // 5d. Studio view bersih tanpa sub-teks penjelasan, fokus pratinjau >=75% di laptop, kontrol slot kontekstual, & modal trim 3 menit
    $studioView = (string)file_get_contents(ROOT_DIR . '/views/operasi/dokumentasi_studio.php');
    $posTeks = strpos($studioView, 'data-studio-panel="teks"');
    $posDesain = strpos($studioView, 'data-studio-panel="desain"');
    $posGrid = strpos($studioView, 'data-studio-panel="grid"');
    $posMedia = strpos($studioView, 'data-studio-panel="media"');
    assertDok(
        !str_contains($studioView, 'id="slotListGrid"')
            && !str_contains($studioView, 'id="slotInspectorPanel"')
            && str_contains($studioView, 'dok-studio-grid')
            && str_contains($studioView, 'id="collageInteractiveOverlay"')
            && str_contains($studioView, 'id="slotActionBar"')
            && str_contains($studioView, 'id="videoTrimModal"')
            && str_contains($studioView, 'id="stylePresetGrid"')
            && str_contains($studioView, 'id="selectExportQuality"')
            && str_contains($studioView, 'id="previewBulkBar"')
            && str_contains($studioView, 'value="180"')
            && str_contains($studioView, 'studio_draft_store.js')
            && str_contains($studioView, 'chunk_uploader.js')
            && $posTeks !== false && $posDesain !== false && $posGrid !== false && $posMedia !== false
            && $posTeks < $posDesain && $posDesain < $posGrid && $posGrid < $posMedia,
        'dokumentasi_studio.php memakai layout fokus pratinjau >=75%, tab terurut (Teks/Desain/Grid/Media), slotActionBar anti-overflow, modal Trim 3 menit, dan modul draft/chunk'
    );

    $tabDokView = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/tab_dokumentasi.php');
    assertDok(
        str_contains($tabDokView, 'doc_id=') && str_contains($tabDokView, '>Edit<'),
        'tab_dokumentasi.php menyediakan tombol Edit yang membuka Studio Dokumentasi pada arsip tersimpan'
    );

    // 5e. Verifikasi kolom project_json, simpan aset sumber per-slot, dan fungsi Edit/Update arsip tersimpan
    $pjCols = $db->query("SHOW COLUMNS FROM OperasiDokumentasi LIKE 'project_json'")->fetchAll();
    assertDok(!empty($pjCols), 'Kolom OperasiDokumentasi.project_json tersedia di skema database');

    assertDok(
        method_exists('OperasiDokumentasiModel', 'storeSourceAsset') && method_exists('OperasiDokumentasiModel', 'update'),
        'OperasiDokumentasiModel memiliki method storeSourceAsset() dan update() untuk fungsi edit arsip'
    );

    if (method_exists('OperasiDokumentasiModel', 'storeSourceAsset') && method_exists('OperasiDokumentasiModel', 'update')) {
        $tmpSrc1 = $makeTmp($jpegBytes, '.jpg');
        $tmpSrc2 = $makeTmp($jpegBytes, '.jpg');
        $asset1 = OperasiDokumentasiModel::storeSourceAsset($testOpsId, ['tmp_name' => $tmpSrc1, 'size' => strlen($jpegBytes), 'name' => 's1.jpg']);
        $asset2 = OperasiDokumentasiModel::storeSourceAsset($testOpsId, ['tmp_name' => $tmpSrc2, 'size' => strlen($jpegBytes), 'name' => 's2.jpg']);
        assertDok(
            !empty($asset1['path']) && str_starts_with($asset1['path'], 'uploads/operasi_dokumentasi/src_') && is_file(ROOT_DIR . '/' . $asset1['path']),
            'storeSourceAsset() menyimpan sumber media per-slot secara aman'
        );

        $tmpEditImg = $makeTmp($jpegBytes, '.jpg');
        $oldRenderPath = ROOT_DIR . '/' . $savedImg['file_path'];
        $projectV1 = json_encode([
            'version' => 1,
            'styleId' => 'editorial_modern',
            'slotCount' => 4,
            'slots' => [
                ['type' => 'image', 'path' => $asset1['path'], 'name' => 's1.jpg', 'zoom' => 1.2, 'offsetX' => 10, 'offsetY' => -5],
                ['type' => 'image', 'path' => $asset2['path'], 'name' => 's2.jpg', 'zoom' => 1.0, 'offsetX' => 0, 'offsetY' => 0],
            ],
        ]);
        $updated1 = OperasiDokumentasiModel::update(
            (int)$savedImg['id_dokumentasi'],
            $testOpsId,
            [
                'judul' => 'Dok Operasi SAR H2 Hasil Edit',
                'hari_ke' => 'H2',
                'tanggal_label' => 'Selasa, 24 November 2026',
                'aspect_ratio' => '16:9',
                'orientation' => 'horizontal',
                'slot_count' => 4,
                'layout_preset' => 'editorial_modern:grid_4_2x2',
                'width_px' => 3840,
                'height_px' => 2160,
                'project_json' => $projectV1,
            ],
            ['tmp_name' => $tmpEditImg, 'size' => strlen($jpegBytes), 'name' => 'kolase_v2.jpg'],
            null,
            'USR-ADMIN'
        );
        assertDok(
            ($updated1['judul'] ?? '') === 'Dok Operasi SAR H2 Hasil Edit'
                && ($updated1['hari_ke'] ?? '') === 'H2'
                && ($updated1['orientation'] ?? '') === 'horizontal'
                && !empty($updated1['project_json'])
                && !is_file($oldRenderPath)
                && is_file(ROOT_DIR . '/' . $updated1['file_path']),
            'OperasiDokumentasiModel::update() memperbarui arsip in-place, menyimpan project_json, dan menghapus render lama'
        );

        // Update kedua membuang asset2 -> pastikan asset2 ikut dibersihkan dan asset1 tetap ada
        $tmpEditImg2 = $makeTmp($jpegBytes, '.jpg');
        $projectV2 = json_encode([
            'version' => 1,
            'styleId' => 'klasik_kpp',
            'slotCount' => 4,
            'slots' => [
                ['type' => 'image', 'path' => $asset1['path'], 'name' => 's1.jpg', 'zoom' => 1.0, 'offsetX' => 0, 'offsetY' => 0],
            ],
        ]);
        $updated2 = OperasiDokumentasiModel::update(
            (int)$savedImg['id_dokumentasi'],
            $testOpsId,
            [
                'judul' => 'Dok Operasi SAR H2 Revisi Kedua',
                'hari_ke' => 'H2',
                'tanggal_label' => 'Selasa, 24 November 2026',
                'aspect_ratio' => '2:3',
                'orientation' => 'vertical',
                'slot_count' => 4,
                'layout_preset' => 'klasik_kpp:grid_4_2x2',
                'width_px' => 2160,
                'height_px' => 3240,
                'project_json' => $projectV2,
            ],
            ['tmp_name' => $tmpEditImg2, 'size' => strlen($jpegBytes), 'name' => 'kolase_v3.jpg'],
            null,
            'USR-ADMIN'
        );
        assertDok(
            is_file(ROOT_DIR . '/' . $asset1['path']) && !is_file(ROOT_DIR . '/' . $asset2['path']),
            'Update arsip membersihkan aset sumber yatim yang dihapus sambil mempertahankan aset aktif'
        );

        // Perbarui referensi $savedImg agar langkah delete di bawah memverifikasi pembersihan file baru & asset1
        $savedImg = $updated2;
        $retainedAssetPath = ROOT_DIR . '/' . $asset1['path'];
    } else {
        $retainedAssetPath = '';
    }

    // 6. Hapus 1 arsip menghapus baris DB, berkas gambar render, dan aset sumber per-slot di disk
    $imgDiskPath = ROOT_DIR . '/' . $savedImg['file_path'];
    $delOne = OperasiDokumentasiModel::delete((int)$savedImg['id_dokumentasi'], $testOpsId);
    assertDok(
        $delOne && !is_file($imgDiskPath) && ($retainedAssetPath === '' || !is_file($retainedAssetPath)),
        'OperasiDokumentasiModel::delete() menghapus arsip, berkas render, dan aset sumber per-slot di disk'
    );

    // 7. Hapus Operasi SAR menghapus sisa arsip + file video & thumbnail (cascade cleanup)
    $vidDiskPath = ROOT_DIR . '/' . $savedVid['file_path'];
    $thumbDiskPath = ROOT_DIR . '/' . $savedVid['thumb_path'];
    OperasiModel::delete($testOpsId);
    $testOpsId = 0;
    assertDok(
        !is_file($vidDiskPath) && !is_file($thumbDiskPath),
        'OperasiModel::delete() membersihkan file fisik video & thumbnail dokumentasi pasca-commit'
    );

    // 8. Uji validasi container video expanded (MP4 ftyp, MP4/MOV wide/moov, dan penolakan payload korup)
    $mp4FtypBytes = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00isommp42" . str_repeat("\x00", 256);
    $tmpMp4Ftyp = $makeTmp($mp4FtypBytes, '.mp4');
    $assetMp4Ftyp = OperasiDokumentasiModel::storeSourceAsset(null, [
        'tmp_name' => $tmpMp4Ftyp,
        'size' => strlen($mp4FtypBytes),
        'name' => 'clip_ftyp.mp4',
    ]);
    assertDok(
        $assetMp4Ftyp['media_type'] === 'video' && str_ends_with($assetMp4Ftyp['path'], '.mp4') && is_file(ROOT_DIR . '/' . $assetMp4Ftyp['path']),
        'storeSourceAsset() menerima video MP4 standar dengan ftyp box'
    );

    // MOV/MP4 dengan box wide/mdat di awal
    $movWideBytes = "\x00\x00\x00\x08wide\x00\x00\x00\x18mdat\x00\x00\x00\x00isom" . str_repeat("\x00", 256);
    $tmpMovWide = $makeTmp($movWideBytes, '.mov');
    $assetMovWide = OperasiDokumentasiModel::storeSourceAsset(null, [
        'tmp_name' => $tmpMovWide,
        'size' => strlen($movWideBytes),
        'name' => 'clip_wide.mov',
    ]);
    assertDok(
        $assetMovWide['media_type'] === 'video' && str_ends_with($assetMovWide['path'], '.mp4') && is_file(ROOT_DIR . '/' . $assetMovWide['path']),
        'storeSourceAsset() menerima container MOV/MP4 dengan atom wide/mdat di awal'
    );

    // Payload bukan video/gambar harus ditolak dengan InvalidArgumentException
    $invalidBytes = "Bukan berkas media melainkan skrip shell biasa\n";
    $tmpInvalid = $makeTmp($invalidBytes, '.txt');
    $rejectedOk = false;
    try {
        OperasiDokumentasiModel::storeSourceAsset(null, [
            'tmp_name' => $tmpInvalid,
            'size' => strlen($invalidBytes),
            'name' => 'fake.mp4',
        ]);
    } catch (InvalidArgumentException) {
        $rejectedOk = true;
    }
    assertDok($rejectedOk, 'storeSourceAsset() menolak berkas dengan format atau magic byte tidak valid');

    // Bersihkan file uji tambahan
    OperasiDokumentasiModel::deleteFiles([$assetMp4Ftyp['path'], $assetMovWide['path']]);

    // 9. Uji HTTP Range (RFC 7233) pada Router::serveStaticFile() untuk streaming video
    $rangeTestFile = ROOT_DIR . '/uploads/range_test_' . bin2hex(random_bytes(4)) . '.mp4';
    $rangeData = str_repeat('0123456789ABCDEF', 64); // 1024 bytes
    file_put_contents($rangeTestFile, $rangeData);
    $tmpFiles[] = $rangeTestFile;

    $runRangeSubproc = function (string $rangeHdr, string $filePath): array {
        $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg("
            \$_SERVER['HTTP_RANGE'] = '{$rangeHdr}';
            require_once '" . __DIR__ . "/../helpers/Router.php';
            ob_start();
            Router::serveStaticFile('{$filePath}');
            \$body = ob_get_clean();
            echo json_encode([
                'code' => http_response_code(),
                'len' => strlen(\$body),
                'body' => \$body,
            ]);
        ");
        $raw = (string)shell_exec($cmd);
        $res = json_decode($raw, true);
        return is_array($res) ? $res : ['code' => 0, 'len' => 0, 'body' => ''];
    };

    // Uji range bytes=0-99 (100 byte pertama) -> HTTP 206
    $r1 = $runRangeSubproc('bytes=0-99', $rangeTestFile);
    assertDok(
        $r1['code'] === 206 && $r1['len'] === 100 && $r1['body'] === substr($rangeData, 0, 100),
        "Router::serveStaticFile() melayani HTTP 206 Partial Content untuk Range: bytes=0-99 (panjang: {$r1['len']})"
    );

    // Uji suffix range bytes=-50 (50 byte terakhir) -> HTTP 206
    $r2 = $runRangeSubproc('bytes=-50', $rangeTestFile);
    assertDok(
        $r2['code'] === 206 && $r2['len'] === 50 && $r2['body'] === substr($rangeData, -50),
        "Router::serveStaticFile() melayani HTTP 206 Partial Content untuk suffix Range: bytes=-50"
    );

    // Uji out of bounds range bytes=5000-6000 -> HTTP 416
    $r3 = $runRangeSubproc('bytes=5000-6000', $rangeTestFile);
    assertDok(
        $r3['code'] === 416,
        "Router::serveStaticFile() mengembalikan HTTP 416 Range Not Satisfiable saat range di luar ukuran berkas (code: {$r3['code']})"
    );
} finally {
    if ($testOpsId > 0) {
        OperasiModel::delete($testOpsId);
    }
    foreach ($tmpFiles as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
}

echo "\n--- HASIL TEST BACKEND DOKUMENTASI ---\n";
echo "Lulus (PASS): {$passed}\n";
echo "Gagal (FAIL): {$failed}\n";
exit($failed === 0 ? 0 : 1);
