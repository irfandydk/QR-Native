<?php
/**
 * QR-SAR Automated TDD Suite - Dokumentasi Chunked Upload, Batas 150 MB, Trim 3 Menit, & Server-Side FFmpeg Render
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

function assertDokOpt(bool $cond, string $msg): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$msg}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$msg}\n";
    }
}

echo "=== TEST DOKUMENTASI CHUNKED UPLOAD, 150 MB, TRIM 3 MENIT & FFMPEG RENDER ===\n\n";

assertDokOpt(class_exists('DokumentasiChunkUploadService'), 'Class DokumentasiChunkUploadService tersedia via autoloader');
assertDokOpt(class_exists('DokumentasiVideoService'), 'Class DokumentasiVideoService tersedia via autoloader');
assertDokOpt(
    OperasiDokumentasiModel::MAX_FINAL_BYTES === 157286400
        && OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS === 180,
    'Konstanta batas hasil akhir = 150 MB dan durasi maksimal video = 180 detik (3 menit)'
);

$tmpFiles = [];
$cleanupRelPaths = [];
$createdDocIds = [];

try {
    // 1. Uji Chunked Upload: init -> storeChunk (3 potongan) -> assemble -> validasi & simpan sumber
    $im = imagecreatetruecolor(320, 240);
    imagefill($im, 0, 0, imagecolorallocate($im, 234, 88, 12));
    ob_start();
    imagejpeg($im, null, 92);
    $jpegData = (string)ob_get_clean();
    unset($im);

    $c1 = substr($jpegData, 0, (int)floor(strlen($jpegData) / 3));
    $c2 = substr($jpegData, strlen($c1), (int)floor(strlen($jpegData) / 3));
    $c3 = substr($jpegData, strlen($c1) + strlen($c2));

    $session = DokumentasiChunkUploadService::initSession('source', null, 'foto_besar_drone.jpg', strlen($jpegData), 3, 'USR-TEST');
    assertDokOpt(!empty($session['upload_id']), 'Chunked upload initSession() menghasilkan upload_id valid');

    foreach ([$c1, $c2, $c3] as $idx => $partBytes) {
        $pFile = tempnam(sys_get_temp_dir(), 'qrsar_chunk_');
        file_put_contents($pFile, $partBytes);
        $tmpFiles[] = $pFile;
        DokumentasiChunkUploadService::storeChunk($session['upload_id'], $idx, ['tmp_name' => $pFile, 'size' => strlen($partBytes)], 'USR-TEST');
    }

    // Uji isolasi kepemilikan sesi: user lain tidak boleh mengakses/merakit sesi milik USR-TEST
    $otherUserBlocked = false;
    try {
        DokumentasiChunkUploadService::assembleSession($session['upload_id'], 'USR-OTHER');
    } catch (InvalidArgumentException) {
        $otherUserBlocked = true;
    }
    assertDokOpt($otherUserBlocked, 'Chunked upload menolak akses lintas pengguna (ownership isolation)');

    $assembled = DokumentasiChunkUploadService::assembleSession($session['upload_id'], 'USR-TEST');
    assertDokOpt(
        !empty($assembled['assembled']) && (int)$assembled['assembled_size'] === strlen($jpegData),
        'assembleSession() merakit 3 potongan tepat byte-per-byte secara streaming'
    );

    $fileInfo = DokumentasiChunkUploadService::getAssembledFileInfo($session['upload_id'], 'USR-TEST', 'source');
    $srcAsset = OperasiDokumentasiModel::storeSourceAsset(null, $fileInfo);
    DokumentasiChunkUploadService::cleanupSession($session['upload_id']);
    $cleanupRelPaths[] = $srcAsset['path'];

    assertDokOpt(
        $srcAsset['media_type'] === 'image' && is_file(ROOT_DIR . '/' . $srcAsset['path']),
        'storeSourceAsset() menerima hasil rakitan chunk dan memindahkan file secara instan (move_local)'
    );

    // 2. Uji Batas 150 MB untuk final_media ditolak di initSession, sementara purpose=source > 150 MB diizinkan (tanpa batas per file)
    $finalOver150Rejected = false;
    try {
        DokumentasiChunkUploadService::initSession('final_media', null, 'hasil_160mb.mp4', 160 * 1024 * 1024, 40, 'USR-TEST');
    } catch (InvalidArgumentException) {
        $finalOver150Rejected = true;
    }
    assertDokOpt($finalOver150Rejected, 'initSession(final_media) menolak hasil kolase > 150 MB sebelum upload dimulai');

    $sourceOver150 = DokumentasiChunkUploadService::initSession('source', null, 'raw_drone_220mb.mp4', 220 * 1024 * 1024, 55, 'USR-TEST');
    assertDokOpt(!empty($sourceOver150['upload_id']), 'initSession(source) mengizinkan sumber video mentah > 150 MB (tanpa batas per foto/video)');
    DokumentasiChunkUploadService::cleanupSession($sourceOver150['upload_id']);

    // 3. Uji sanitasi trimStart/trimEnd (maks 180 dtk), titleAlign/titleScale, dan default fhd + image/png di project_json
    $pjInput = json_encode([
        'version' => 1,
        'styleId' => 'klasik_kpp',
        'slotCount' => 4,
        'titleAlign' => 'right',
        'titleScale' => 1.3,
        'tanggalIso' => '2026-11-25',
        'slots' => [
            [
                'type' => 'image',
                'path' => $srcAsset['path'],
                'name' => 'foto_besar_drone.jpg',
                'zoom' => 1.25,
                'offsetX' => 5,
                'offsetY' => -5,
                'trimStart' => 12.5,
                'trimEnd' => 400.0, // Melebihi 180 dtk dari 12.5 -> harus dijepit ke 192.5
                'duration' => 420.0,
            ],
        ],
    ]);

    $renderTmp = tempnam(sys_get_temp_dir(), 'qrsar_render_');
    file_put_contents($renderTmp, $jpegData);
    $tmpFiles[] = $renderTmp;
    $savedDoc = OperasiDokumentasiModel::store(null, [
        'judul' => 'Uji Trim Metadata & Batas 150 MB',
        'slot_count' => 4,
        'project_json' => $pjInput,
    ], ['tmp_name' => $renderTmp, 'size' => strlen($jpegData), 'name' => 'uji.jpg'], null, 'USR-TEST');
    $createdDocIds[] = (int)$savedDoc['id_dokumentasi'];
    $decodedPj = json_decode((string)($savedDoc['project_json'] ?? ''), true);
    $slot0 = $decodedPj['slots'][0] ?? [];
    assertDokOpt(
        abs(((float)($slot0['trimStart'] ?? -1)) - 12.5) < 0.01
            && abs(((float)($slot0['trimEnd'] ?? -1)) - 192.5) < 0.01
            && ($decodedPj['titleAlign'] ?? '') === 'right'
            && abs(((float)($decodedPj['titleScale'] ?? 0)) - 1.3) < 0.01
            && ($decodedPj['qualityId'] ?? '') === 'fhd'
            && ($decodedPj['imageFormat'] ?? '') === 'image/png',
        'sanitizeProjectJson() menjaga trim maks 180 dtk, titleAlign/titleScale, serta default kualitas FHD & format PNG'
    );

    // 4. Uji Kalkulasi Bitrate Otomatis (<= 150 MB untuk 180 detik) & Cover Crop
    $rates180 = DokumentasiVideoService::computeTargetBitrates(180.0, 1080, 1620, true);
    $estBytes180 = (($rates180['video_bps'] + $rates180['audio_bps']) * 180.0) / 8.0;
    assertDokOpt(
        $estBytes180 <= OperasiDokumentasiModel::MAX_FINAL_BYTES && $rates180['video_bps'] >= 4000000,
        'computeTargetBitrates(180s, 1080p) menjaga estimasi ukuran <= 150 MB (' . round($estBytes180 / 1048576, 1) . ' MB) dengan kualitas tajam'
    );

    $rates4k180 = DokumentasiVideoService::computeTargetBitrates(180.0, 2160, 3240, true);
    $estBytes4k180 = (($rates4k180['video_bps'] + $rates4k180['audio_bps']) * 180.0) / 8.0;
    assertDokOpt(
        $estBytes4k180 <= OperasiDokumentasiModel::MAX_FINAL_BYTES,
        'computeTargetBitrates(180s, 4K) otomatis menurunkan bitrate agar tidak pernah melebihi 150 MB (' . round($estBytes4k180 / 1048576, 1) . ' MB)'
    );

    $crop = DokumentasiVideoService::computeCoverCrop(1920, 1080, 400, 400, 1.5, 50.0, -50.0);
    assertDokOpt(
        $crop['sw'] === 720 && $crop['sh'] === 720 && $crop['sx'] >= 0 && $crop['sy'] >= 0 && ($crop['sx'] + $crop['sw'] <= 1920),
        'computeCoverCrop() menghitung kotak crop presisi selaras collage_layouts.js'
    );

    // 5. Uji End-to-End Server-Side FFmpeg Render (bila ffmpeg terpasang di mesin ini)
    $caps = DokumentasiVideoService::getCapabilities(true);
    if (!empty($caps['server_render'])) {
        $ffmpeg = DokumentasiVideoService::findBinary('ffmpeg');
        // Buat 1 video sumber MP4 uji (durasi 4 detik dengan audio sine) via ffmpeg
        $sampleVidTmp = sys_get_temp_dir() . '/qrsar_test_src_' . bin2hex(random_bytes(4)) . '.mp4';
        $tmpFiles[] = $sampleVidTmp;
        $genCmd = escapeshellarg((string)$ffmpeg)
            . ' -y -f lavfi -i testsrc=duration=4:size=640x360:rate=25'
            . ' -f lavfi -i sine=frequency=440:duration=4'
            . ' -c:v libx264 -preset ultrafast -pix_fmt yuv420p -c:a aac '
            . escapeshellarg($sampleVidTmp) . ' 2>/dev/null';
        exec($genCmd, $outLines, $genCode);
        assertDokOpt($genCode === 0 && is_file($sampleVidTmp), 'Video uji MP4 (4 dtk + audio) berhasil dibuat dengan FFmpeg lokal');

        $vidSrcAsset = OperasiDokumentasiModel::storeSourceAsset(null, [
            'tmp_name' => $sampleVidTmp,
            'size' => (int)filesize($sampleVidTmp),
            'name' => 'klip_uji.mp4',
        ]);
        $cleanupRelPaths[] = $vidSrcAsset['path'];

        // Buat frame overlay PNG transparan (lubang transparan pada kotak video 40..440 x 80..360)
        $ovTmp = sys_get_temp_dir() . '/qrsar_test_ov_' . bin2hex(random_bytes(4)) . '.png';
        $tmpFiles[] = $ovTmp;
        $ov = imagecreatetruecolor(480, 640);
        imagealphablending($ov, false);
        imagesavealpha($ov, true);
        $bgOrange = imagecolorallocatealpha($ov, 249, 115, 22, 0);
        $holeTransparent = imagecolorallocatealpha($ov, 0, 0, 0, 127);
        imagefilledrectangle($ov, 0, 0, 480, 640, $bgOrange);
        imagefilledrectangle($ov, 40, 80, 440, 360, $holeTransparent);
        imagepng($ov, $ovTmp);
        unset($ov);

        $job = DokumentasiVideoService::createRenderJob([
            'mode' => 'archive',
            'id_operasi' => 0,
            'judul' => 'Uji End-to-End FFmpeg Kolase Video Trim',
            'hari_ke' => 'KEGIATAN',
            'tanggal_label' => 'Rabu, 25 November 2026',
            'aspect_ratio' => '3:4',
            'orientation' => 'vertical',
            'slot_count' => 4,
            'layout_preset' => 'klasik_kpp:grid_4_2x2',
            'width_px' => 480,
            'height_px' => 640,
            'duration' => 2.0,
            'overlay_tmp_path' => $ovTmp,
            'thumb_tmp_path' => $renderTmp,
            'video_slots' => [
                [
                    'path' => $vidSrcAsset['path'],
                    'rect' => ['x' => 40, 'y' => 80, 'w' => 400, 'h' => 280],
                    'zoom' => 1.2,
                    'offsetX' => 0,
                    'offsetY' => 0,
                    'muted' => false,
                    'trimStart' => 1.0,
                    'trimEnd' => 3.0,
                ],
            ],
        ], 'USR-TEST', false);

        $done = DokumentasiVideoService::executeJob($job['job_id']);
        $savedItem = $done['item'] ?? null;
        if (is_array($savedItem) && !empty($savedItem['id_dokumentasi'])) {
            $createdDocIds[] = (int)$savedItem['id_dokumentasi'];
        }

        assertDokOpt(
            ($done['status'] ?? '') === 'completed'
                && is_array($savedItem)
                && ($savedItem['media_type'] ?? '') === 'video'
                && ($savedItem['mime_type'] ?? '') === 'video/mp4'
                && is_file(ROOT_DIR . '/' . (string)$savedItem['file_path']),
            'End-to-End FFmpeg render menghasilkan MP4 H.264/AAC valid dengan trim 1.0–3.0 dtk dan tersimpan otomatis ke DB'
        );

        if (is_array($savedItem) && !empty($savedItem['file_path'])) {
            $outProbe = DokumentasiVideoService::probeVideo(ROOT_DIR . '/' . (string)$savedItem['file_path']);
            assertDokOpt(
                $outProbe['width'] === 480 && $outProbe['height'] === 640 && $outProbe['has_audio'] === true && $outProbe['duration'] >= 1.7 && $outProbe['duration'] <= 2.5,
                'Verifikasi ffprobe hasil render: 480x640, audio aktif, durasi trim presisi (' . round($outProbe['duration'], 2) . ' dtk)'
            );
        }
    } else {
        echo "  [SKIP] FFmpeg tidak terpasang di lingkungan ini — uji render end-to-end dilewati.\n";
    }

    // 6. Kontrak Frontend & API: bebas batas 20 MB hardcoded, mendukung 150 MB & 180 detik, 1 loading terpadu
    $jsSrc = (string)file_get_contents(ROOT_DIR . '/assets/js/pages/operasi_dokumentasi.js');
    assertDokOpt(
        !str_contains($jsSrc, '20 * 1024 * 1024')
            && str_contains($jsSrc, 'QrSarDokDraftStore')
            && str_contains($jsSrc, 'QrSarDokChunkUploader')
            && str_contains($jsSrc, 'setUnifiedProgress')
            && str_contains($jsSrc, 'openVideoTrimModal'),
        'operasi_dokumentasi.js bebas batas 20 MB lama dan mengintegrasikan IndexedDB draft, chunk uploader, unified progress, & video trim'
    );
} finally {
    foreach ($createdDocIds as $cid) {
        if ($cid > 0) {
            try {
                OperasiDokumentasiModel::delete($cid);
            } catch (Throwable) {
            }
        }
    }
    if (!empty($cleanupRelPaths)) {
        OperasiDokumentasiModel::deleteFiles($cleanupRelPaths);
    }
    foreach ($tmpFiles as $tf) {
        if (is_file($tf)) {
            @unlink($tf);
        }
    }
}

echo "\n--- HASIL TEST DOKUMENTASI CHUNK + FFMPEG + TRIM ---\n";
echo "Lulus (PASS): {$passed}\n";
echo "Gagal (FAIL): {$failed}\n";
exit($failed === 0 ? 0 : 1);
