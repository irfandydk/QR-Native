<?php
/**
 * QR-SAR - Layanan Render Video Kolase Server-Side (FFmpeg)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Merender kolase video resmi KPP Kendari secara asynchronous (background CLI worker)
 * dengan overlay PNG transparan + pemotongan klip (Trim maks 3 menit) + crop presisi
 * sesuai zoom/pan + pencampuran audio + kalkulasi bitrate otomatis agar hasil MP4 (H.264/AAC)
 * selalu kompatibel (iOS/Android/WhatsApp) dan berukuran <= 150 MB.
 */

declare(strict_types=1);

class DokumentasiVideoService {
    public const MAX_CONCURRENT_JOBS = 2;
    public const JOB_TTL_SECONDS = 21600; // 6 jam
    public const SAFE_TARGET_MAX_BYTES = 146800640; // 140 MB target (margin 10 MB di bawah batas 150 MB)

    private static ?array $cachedCaps = null;

    public static function getJobsDir(): string {
        $dir = STORAGE_DIR . '/tmp_dok_jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $index = $dir . '/index.php';
        if (is_dir($dir) && !is_file($index)) {
            @file_put_contents($index, "<?php\nhttp_response_code(403);\nexit('Akses Ditolak.');\n");
        }
        return $dir;
    }

    public static function findBinary(string $name): ?string {
        $envKey = strtoupper($name) . '_BIN';
        $envVal = getenv($envKey) ?: ($_ENV[$envKey] ?? '');
        if (is_string($envVal) && trim($envVal) !== '' && is_file($envVal) && is_executable($envVal)) {
            return $envVal;
        }

        $candidates = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/opt/homebrew/bin/' . $name,
            '/bin/' . $name,
        ];
        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        $which = trim((string)@shell_exec('command -v ' . escapeshellarg($name) . ' 2>/dev/null'));
        if ($which !== '' && is_file($which) && is_executable($which)) {
            return $which;
        }
        return null;
    }

    public static function findPhpCliBinary(): ?string {
        if (PHP_SAPI === 'cli' && is_file(PHP_BINARY) && is_executable(PHP_BINARY)) {
            return PHP_BINARY;
        }
        $candidates = [
            '/usr/local/bin/php',
            '/opt/homebrew/bin/php',
            '/usr/bin/php',
            dirname(PHP_BINARY) . '/php',
            dirname(dirname(PHP_BINARY)) . '/bin/php',
        ];
        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path) && !str_contains(basename($path), 'fpm')) {
                return $path;
            }
        }
        $which = trim((string)@shell_exec('command -v php 2>/dev/null'));
        if ($which !== '' && is_file($which) && is_executable($which) && !str_contains(basename($which), 'fpm')) {
            return $which;
        }
        return null;
    }

    /**
     * Kembalikan kapabilitas server untuk Studio Dokumentasi.
     *
     * @return array<string, mixed>
     */
    public static function getCapabilities(bool $refresh = false): array {
        if (self::$cachedCaps !== null && !$refresh) {
            return self::$cachedCaps;
        }
        $ffmpeg = self::findBinary('ffmpeg');
        $ffprobe = self::findBinary('ffprobe');
        $phpCli = self::findPhpCliBinary();
        $serverRender = ($ffmpeg !== null && $ffprobe !== null && $phpCli !== null && function_exists('proc_open'));

        self::$cachedCaps = [
            'server_render' => $serverRender,
            'has_ffmpeg' => $ffmpeg !== null,
            'has_ffprobe' => $ffprobe !== null,
            'chunk_size_bytes' => DokumentasiChunkUploadService::DEFAULT_CHUNK_BYTES,
            'max_final_bytes' => OperasiDokumentasiModel::MAX_FINAL_BYTES,
            'max_video_duration_seconds' => OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS,
            'source_size_limit_bytes' => null,
        ];
        return self::$cachedCaps;
    }

    /**
     * Inspeksi metadata video dengan ffprobe (dimensi efektif pasca-rotasi, durasi, keberadaan audio).
     *
     * @return array{width:int, height:int, duration:float, has_audio:bool}
     */
    public static function probeVideo(string $absPath): array {
        $ffprobe = self::findBinary('ffprobe');
        if ($ffprobe === null) {
            throw new RuntimeException('ffprobe tidak tersedia di server.');
        }
        if (!is_file($absPath)) {
            throw new InvalidArgumentException('Berkas video sumber tidak ditemukan di server.');
        }

        $cmd = [
            $ffprobe,
            '-v', 'error',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $absPath,
        ];

        $desc = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $proc = @proc_open($cmd, $desc, $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('Gagal menjalankan ffprobe untuk membaca metadata video.');
        }
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        if ($exitCode !== 0 || !is_string($stdout) || trim($stdout) === '') {
            throw new RuntimeException('Gagal membaca metadata video: ' . trim((string)$stderr));
        }

        $json = json_decode($stdout, true);
        if (!is_array($json)) {
            throw new RuntimeException('Keluaran metadata ffprobe tidak valid.');
        }

        $width = 0;
        $height = 0;
        $rotation = 0;
        $duration = 0.0;
        $hasAudio = false;

        foreach (($json['streams'] ?? []) as $stream) {
            if (!is_array($stream)) {
                continue;
            }
            $type = strtolower((string)($stream['codec_type'] ?? ''));
            if ($type === 'video' && $width === 0) {
                $width = (int)($stream['width'] ?? 0);
                $height = (int)($stream['height'] ?? 0);
                if (isset($stream['duration']) && is_numeric($stream['duration'])) {
                    $duration = max($duration, (float)$stream['duration']);
                }
                if (isset($stream['tags']['rotate']) && is_numeric($stream['tags']['rotate'])) {
                    $rotation = (int)$stream['tags']['rotate'];
                }
                if (isset($stream['side_data_list']) && is_array($stream['side_data_list'])) {
                    foreach ($stream['side_data_list'] as $sd) {
                        if (is_array($sd) && isset($sd['rotation']) && is_numeric($sd['rotation'])) {
                            $rotation = (int)$sd['rotation'];
                        }
                    }
                }
            } elseif ($type === 'audio') {
                $hasAudio = true;
            }
        }

        if (isset($json['format']['duration']) && is_numeric($json['format']['duration'])) {
            $fmtDur = (float)$json['format']['duration'];
            if ($fmtDur > 0 && ($duration <= 0 || $fmtDur < $duration + 2.0)) {
                $duration = max($duration, $fmtDur);
            }
        }

        // FFmpeg auto-rotates displaymatrix sebelum filter_complex -> dimensi efektif bertukar pada rotasi +-90/270
        $absRot = abs($rotation) % 360;
        if ($absRot === 90 || $absRot === 270) {
            [$width, $height] = [$height, $width];
        }

        if ($width < 2 || $height < 2) {
            throw new RuntimeException('Stream video tidak memiliki dimensi piksel yang valid.');
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration' => $duration > 0 ? $duration : 5.0,
            'has_audio' => $hasAudio,
        ];
    }

    /**
     * Hitung kotak crop sumber (identik dengan collage_layouts.js::computeCoverCrop).
     *
     * @return array{sx:int, sy:int, sw:int, sh:int}
     */
    public static function computeCoverCrop(
        int $mediaW,
        int $mediaH,
        int $slotW,
        int $slotH,
        float $zoom = 1.0,
        float $offsetX = 0.0,
        float $offsetY = 0.0
    ): array {
        $mw = max(2, $mediaW);
        $mh = max(2, $mediaH);
        $swRatio = max(0.01, max(1, $slotW) / max(1, $slotH));
        $mRatio = $mw / $mh;
        $z = max(1.0, min(3.0, $zoom));

        if ($mRatio > $swRatio) {
            $baseSw = $mh * $swRatio;
            $baseSh = (float)$mh;
        } else {
            $baseSw = (float)$mw;
            $baseSh = $mw / $swRatio;
        }

        $sw = min((float)$mw, $baseSw / $z);
        $sh = min((float)$mh, $baseSh / $z);
        $maxPanX = max(0.0, ($mw - $sw) / 2.0);
        $maxPanY = max(0.0, ($mh - $sh) / 2.0);
        $normX = max(-100.0, min(100.0, $offsetX)) / 100.0;
        $normY = max(-100.0, min(100.0, $offsetY)) / 100.0;

        $sx = max(0.0, min($mw - $sw, ($mw - $sw) / 2.0 + $normX * $maxPanX));
        $sy = max(0.0, min($mh - $sh, ($mh - $sh) / 2.0 + $normY * $maxPanY));

        $cropW = max(2, min($mw, (int)round($sw)));
        $cropH = max(2, min($mh, (int)round($sh)));
        $cropX = max(0, min($mw - $cropW, (int)round($sx)));
        $cropY = max(0, min($mh - $cropH, (int)round($sy)));

        return ['sx' => $cropX, 'sy' => $cropY, 'sw' => $cropW, 'sh' => $cropH];
    }

    /**
     * Hitung bitrate video & audio optimal agar ukuran MP4 akhir selalu <= 150 MB.
     *
     * @return array{video_bps:int, maxrate_bps:int, bufsize_bps:int, audio_bps:int}
     */
    public static function computeTargetBitrates(float $durationSec, int $width, int $height, bool $hasAudio): array {
        $dur = max(1.0, min((float)OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS, $durationSec));
        $maxTotalBps = (int)floor((self::SAFE_TARGET_MAX_BYTES * 8.0) / $dur);
        $audioBps = $hasAudio ? ($maxTotalBps < 1800000 ? 96000 : 128000) : 0;

        $pixels = max(1, $width) * max(1, $height);
        if ($pixels <= 2300000) {
            $desiredVideoBps = 5500000;  // 1080p tajam
        } elseif ($pixels <= 5500000) {
            $desiredVideoBps = 8500000;  // 2K QHD
        } else {
            $desiredVideoBps = 13000000; // 4K UHD
        }

        $budgetVideoBps = max(500000, $maxTotalBps - $audioBps);
        $videoBps = max(500000, min($desiredVideoBps, $budgetVideoBps));
        $maxrateBps = (int)min($budgetVideoBps + 250000, round($videoBps * 1.15));
        $bufsizeBps = (int)round($maxrateBps * 1.5);

        return [
            'video_bps' => $videoBps,
            'maxrate_bps' => max($videoBps, $maxrateBps),
            'bufsize_bps' => max($videoBps, $bufsizeBps),
            'audio_bps' => $audioBps,
        ];
    }

    /**
     * Bangun daftar argumen perintah FFmpeg yang aman (array-based untuk proc_open, tanpa injeksi shell).
     *
     * @param array<string, mixed> $spec
     * @param array<int, array<string, mixed>> $probedSlots
     * @return array<int, string>
     */
    public static function buildFfmpegCommand(
        string $ffmpegBin,
        string $overlayPngPath,
        array $probedSlots,
        array $spec,
        string $outputMp4Path,
        string $progressFilePath
    ): array {
        $outW = max(320, min(4096, ((int)($spec['width_px'] ?? 1080)) & ~1));
        $outH = max(320, min(4096, ((int)($spec['height_px'] ?? 1620)) & ~1));
        $totalDur = max(1.0, min((float)OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS, (float)($spec['duration'] ?? 12.0)));
        $durStr = sprintf('%.3f', $totalDur);
        $fps = 30;

        $cmd = [$ffmpegBin, '-hide_banner', '-loglevel', 'error', '-y'];

        // Input 0: Static overlay PNG berlubang transparan (didekode sekali lalu dipakai ulang via eof_action=repeat)
        $cmd[] = '-i';
        $cmd[] = $overlayPngPath;

        $filterParts = [];
        $filterParts[] = "color=c=#0f172a:s={$outW}x{$outH}:r={$fps}:d={$durStr},format=rgba[bg0]";

        $audioLabels = [];
        $hasAnyAudio = false;
        $slotIdx = 0;

        foreach ($probedSlots as $slot) {
            $inputIndex = $slotIdx + 1;
            $absPath = (string)$slot['abs_path'];
            $srcDur = max(0.2, (float)$slot['probe']['duration']);
            $trimStart = max(0.0, min(max(0.0, $srcDur - 0.1), (float)($slot['trimStart'] ?? 0.0)));
            $rawTrimEnd = isset($slot['trimEnd']) && is_numeric($slot['trimEnd']) ? (float)$slot['trimEnd'] : null;

            if ($rawTrimEnd !== null && $rawTrimEnd > $trimStart + 0.05) {
                $clipDur = min($srcDur - $trimStart, $rawTrimEnd - $trimStart, $totalDur);
            } else {
                $clipDur = min($srcDur - $trimStart, $totalDur);
            }
            $clipDur = max(0.15, $clipDur);

            $shouldLoopWhole = ($trimStart <= 0.02 && $clipDur < ($totalDur - 0.15) && ($rawTrimEnd === null || $rawTrimEnd >= $srcDur - 0.15));
            if ($shouldLoopWhole) {
                $cmd[] = '-stream_loop';
                $cmd[] = '-1';
            } elseif ($trimStart > 0.01) {
                $cmd[] = '-ss';
                $cmd[] = sprintf('%.3f', $trimStart);
            }
            if (!$shouldLoopWhole) {
                $cmd[] = '-t';
                $cmd[] = sprintf('%.3f', $clipDur);
            }
            $cmd[] = '-i';
            $cmd[] = $absPath;

            $slotX = max(0, min($outW - 2, (int)round((float)($slot['rect']['x'] ?? 0))));
            $slotY = max(0, min($outH - 2, (int)round((float)($slot['rect']['y'] ?? 0))));
            $slotW = max(2, min($outW - $slotX, (int)round((float)($slot['rect']['w'] ?? 100))));
            $slotH = max(2, min($outH - $slotY, (int)round((float)($slot['rect']['h'] ?? 100))));

            $crop = self::computeCoverCrop(
                (int)$slot['probe']['width'],
                (int)$slot['probe']['height'],
                $slotW,
                $slotH,
                (float)($slot['zoom'] ?? 1.0),
                (float)($slot['offsetX'] ?? 0.0),
                (float)($slot['offsetY'] ?? 0.0)
            );

            $filterParts[] = sprintf(
                '[%d:v]crop=%d:%d:%d:%d,scale=%d:%d:flags=lanczos,fps=%d,format=rgba,setpts=PTS-STARTPTS[vs%d]',
                $inputIndex,
                $crop['sw'],
                $crop['sh'],
                $crop['sx'],
                $crop['sy'],
                $slotW,
                $slotH,
                $fps,
                $slotIdx
            );
            $nextIdx = $slotIdx + 1;
            $filterParts[] = sprintf(
                '[bg%d][vs%d]overlay=x=%d:y=%d:shortest=0:eof_action=repeat[bg%d]',
                $slotIdx,
                $slotIdx,
                $slotX,
                $slotY,
                $nextIdx
            );

            $isMuted = !empty($slot['muted']);
            if (!$isMuted && !empty($slot['probe']['has_audio'])) {
                $aLbl = "as{$slotIdx}";
                $filterParts[] = sprintf(
                    '[%d:a]asetpts=PTS-STARTPTS,atrim=0:%s,aresample=44100:async=1:first_pts=0,aformat=channel_layouts=stereo[%s]',
                    $inputIndex,
                    $durStr,
                    $aLbl
                );
                $audioLabels[] = "[{$aLbl}]";
                $hasAnyAudio = true;
            }

            $slotIdx++;
        }

        // Overlay PNG kolase statis (kop, logo, judul, footer, foto statis, dan sudut membulat) di atas seluruh video
        $filterParts[] = sprintf(
            '[0:v]scale=%d:%d:flags=lanczos,format=rgba[ov];[bg%d][ov]overlay=x=0:y=0:format=auto:eof_action=repeat:shortest=0,format=yuv420p[vout]',
            $outW,
            $outH,
            $slotIdx
        );

        if ($hasAnyAudio) {
            if (count($audioLabels) === 1) {
                $filterParts[] = $audioLabels[0] . "atrim=0:{$durStr}[aout]";
            } else {
                $filterParts[] = implode('', $audioLabels)
                    . sprintf('amix=inputs=%d:duration=longest:dropout_transition=0:normalize=1,atrim=0:%s[aout]', count($audioLabels), $durStr);
            }
        }

        $rates = self::computeTargetBitrates($totalDur, $outW, $outH, $hasAnyAudio);

        $cmd[] = '-filter_complex';
        $cmd[] = implode(';', $filterParts);
        $cmd[] = '-map';
        $cmd[] = '[vout]';
        if ($hasAnyAudio) {
            $cmd[] = '-map';
            $cmd[] = '[aout]';
        } else {
            $cmd[] = '-an';
        }
        $cmd[] = '-t';
        $cmd[] = $durStr;
        $cmd[] = '-r';
        $cmd[] = (string)$fps;
        $cmd[] = '-c:v';
        $cmd[] = 'libx264';
        $cmd[] = '-preset';
        $cmd[] = 'veryfast';
        $cmd[] = '-profile:v';
        $cmd[] = 'high';
        $cmd[] = '-level';
        $cmd[] = '4.1';
        $cmd[] = '-pix_fmt';
        $cmd[] = 'yuv420p';
        $cmd[] = '-b:v';
        $cmd[] = (string)$rates['video_bps'];
        $cmd[] = '-maxrate';
        $cmd[] = (string)$rates['maxrate_bps'];
        $cmd[] = '-bufsize';
        $cmd[] = (string)$rates['bufsize_bps'];
        $cmd[] = '-threads';
        $cmd[] = '2';

        if ($hasAnyAudio) {
            $cmd[] = '-c:a';
            $cmd[] = 'aac';
            $cmd[] = '-b:a';
            $cmd[] = (string)$rates['audio_bps'];
            $cmd[] = '-ar';
            $cmd[] = '44100';
            $cmd[] = '-ac';
            $cmd[] = '2';
        }

        $cmd[] = '-movflags';
        $cmd[] = '+faststart';
        $cmd[] = '-progress';
        $cmd[] = $progressFilePath;
        $cmd[] = '-nostats';
        $cmd[] = $outputMp4Path;

        return $cmd;
    }

    /**
     * Buat job render video kolase dan luncurkan CLI worker di latar belakang (non-blocking HTTP).
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function createRenderJob(array $payload, string $userId, bool $spawnWorker = true): array {
        $caps = self::getCapabilities();
        if (empty($caps['server_render'])) {
            throw new RuntimeException('Mesin render video server (FFmpeg) belum tersedia di server ini.');
        }

        self::pruneExpiredJobs();

        $mode = (($payload['mode'] ?? 'archive') === 'download') ? 'download' : 'archive';
        $editDocId = isset($payload['edit_doc_id']) ? (int)$payload['edit_doc_id'] : 0;
        $rawOps = isset($payload['id_operasi']) ? (int)$payload['id_operasi'] : 0;
        $targetOps = $rawOps > 0 ? $rawOps : null;
        $expectedOps = isset($payload['expected_operasi_id']) && (int)$payload['expected_operasi_id'] > 0
            ? (int)$payload['expected_operasi_id']
            : null;

        if ($targetOps !== null && OperasiModel::getStatusById($targetOps) === null) {
            throw new InvalidArgumentException('Operasi SAR yang dipilih tidak ditemukan.');
        }

        $slotCount = (int)($payload['slot_count'] ?? 6);
        if ($slotCount < OperasiDokumentasiModel::MIN_SLOTS || $slotCount > OperasiDokumentasiModel::MAX_SLOTS) {
            throw new InvalidArgumentException('Jumlah slot kolase harus antara 4 hingga 12.');
        }

        $videoSlotsInput = $payload['video_slots'] ?? [];
        if (!is_array($videoSlotsInput) || empty($videoSlotsInput)) {
            throw new InvalidArgumentException('Minimal satu slot video diperlukan untuk render video kolase.');
        }

        $srcRegex = '#^' . preg_quote(OperasiDokumentasiModel::REL_DIR, '#') . '/src_\d+_[A-Za-z0-9_\-]+\.(?:webm|mp4|mov|m4v)\z#i';
        $cleanVideoSlots = [];
        foreach (array_slice($videoSlotsInput, 0, OperasiDokumentasiModel::MAX_SLOTS) as $vs) {
            if (!is_array($vs) || empty($vs['path']) || !isset($vs['rect']) || !is_array($vs['rect'])) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', trim((string)$vs['path'])), '/');
            if (!preg_match($srcRegex, $rel)) {
                throw new InvalidArgumentException('Path sumber video tidak valid.');
            }
            $abs = ROOT_DIR . '/' . $rel;
            if (!is_file($abs)) {
                throw new InvalidArgumentException('Berkas video sumber tidak ditemukan di server: ' . basename($rel));
            }
            $trimStart = max(0.0, round((float)($vs['trimStart'] ?? 0.0), 2));
            $trimEnd = (isset($vs['trimEnd']) && is_numeric($vs['trimEnd'])) ? round((float)$vs['trimEnd'], 2) : null;
            if ($trimEnd !== null && $trimEnd <= $trimStart) {
                $trimEnd = null;
            }
            if ($trimEnd !== null && ($trimEnd - $trimStart) > OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS) {
                $trimEnd = round($trimStart + OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS, 2);
            }
            $cleanVideoSlots[] = [
                'path' => $rel,
                'abs_path' => $abs,
                'rect' => [
                    'x' => (float)($vs['rect']['x'] ?? 0),
                    'y' => (float)($vs['rect']['y'] ?? 0),
                    'w' => max(2.0, (float)($vs['rect']['w'] ?? 100)),
                    'h' => max(2.0, (float)($vs['rect']['h'] ?? 100)),
                ],
                'zoom' => max(1.0, min(3.0, (float)($vs['zoom'] ?? 1.0))),
                'offsetX' => max(-100.0, min(100.0, (float)($vs['offsetX'] ?? 0.0))),
                'offsetY' => max(-100.0, min(100.0, (float)($vs['offsetY'] ?? 0.0))),
                'muted' => !isset($vs['muted']) || (bool)$vs['muted'],
                'trimStart' => $trimStart,
                'trimEnd' => $trimEnd,
            ];
        }

        if (empty($cleanVideoSlots)) {
            throw new InvalidArgumentException('Tidak ada slot video valid yang dapat dirender.');
        }

        $jobId = bin2hex(random_bytes(16));
        $jobDir = self::getJobsDir() . '/' . $jobId;
        if (!@mkdir($jobDir, 0770, true)) {
            throw new RuntimeException('Gagal membuat direktori kerja render video.');
        }

        // Ambil overlay PNG (dari upload langsung atau sesi chunk)
        $overlayPath = $jobDir . '/overlay.png';
        $overlaySrc = (string)($payload['overlay_tmp_path'] ?? '');
        if ($overlaySrc === '' || !is_file($overlaySrc)) {
            self::removeDirectory($jobDir);
            throw new InvalidArgumentException('Frame overlay PNG kolase tidak ditemukan.');
        }
        $imgInfo = @getimagesize($overlaySrc);
        if ($imgInfo === false || ($imgInfo[2] ?? 0) !== IMAGETYPE_PNG) {
            self::removeDirectory($jobDir);
            throw new InvalidArgumentException('Frame overlay kolase harus berupa berkas PNG yang valid.');
        }
        $movedOverlay = is_uploaded_file($overlaySrc)
            ? @move_uploaded_file($overlaySrc, $overlayPath)
            : (@rename($overlaySrc, $overlayPath) || @copy($overlaySrc, $overlayPath));
        if (!$movedOverlay || !is_file($overlayPath)) {
            self::removeDirectory($jobDir);
            throw new RuntimeException('Gagal menyimpan frame overlay PNG.');
        }

        // Opsional poster thumbnail JPG
        $thumbPath = null;
        $thumbSrc = (string)($payload['thumb_tmp_path'] ?? '');
        if ($thumbSrc !== '' && is_file($thumbSrc)) {
            $thumbTarget = $jobDir . '/poster.jpg';
            $movedThumb = is_uploaded_file($thumbSrc)
                ? @move_uploaded_file($thumbSrc, $thumbTarget)
                : (@rename($thumbSrc, $thumbTarget) || @copy($thumbSrc, $thumbTarget));
            if ($movedThumb && is_file($thumbTarget)) {
                $thumbPath = $thumbTarget;
            }
        }

        $outW = max(320, min(4096, ((int)($payload['width_px'] ?? $imgInfo[0])) & ~1));
        $outH = max(320, min(4096, ((int)($payload['height_px'] ?? $imgInfo[1])) & ~1));
        $reqDur = max(1.0, min((float)OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS, (float)($payload['duration'] ?? 15.0)));

        $jobMeta = [
            'job_id' => $jobId,
            'user_id' => $userId,
            'mode' => $mode,
            'status' => 'queued',
            'progress' => 0,
            'message' => 'Menyiapkan pemrosesan video...',
            'error' => null,
            'worker_pid' => null,
            'ffmpeg_pid' => null,
            'created_at' => time(),
            'updated_at' => time(),
            'finished_at' => null,
            'item' => null,
            'download_file' => null,
            'download_filename' => self::sanitizeDownloadName((string)($payload['download_filename'] ?? 'DOK_SAR.mp4')),
            'spec' => [
                'edit_doc_id' => $editDocId > 0 ? $editDocId : null,
                'id_operasi' => $targetOps,
                'expected_operasi_id' => $expectedOps,
                'judul' => (string)($payload['judul'] ?? ''),
                'hari_ke' => (string)($payload['hari_ke'] ?? ''),
                'tanggal_label' => (string)($payload['tanggal_label'] ?? ''),
                'aspect_ratio' => (string)($payload['aspect_ratio'] ?? '2:3'),
                'orientation' => (string)($payload['orientation'] ?? 'vertical'),
                'slot_count' => $slotCount,
                'layout_preset' => (string)($payload['layout_preset'] ?? ''),
                'project_json' => isset($payload['project_json']) ? (string)$payload['project_json'] : null,
                'width_px' => $outW,
                'height_px' => $outH,
                'duration' => $reqDur,
                'overlay_path' => $overlayPath,
                'thumb_path' => $thumbPath,
                'video_slots' => $cleanVideoSlots,
            ],
        ];

        self::writeJobMeta($jobDir, $jobMeta);

        if ($spawnWorker) {
            self::spawnWorkerProcess($jobId);
        }

        return [
            'job_id' => $jobId,
            'status' => 'queued',
            'progress' => 0,
        ];
    }

    private static function spawnWorkerProcess(string $jobId): void {
        $phpCli = self::findPhpCliBinary();
        $workerScript = ROOT_DIR . '/cli/render_dokumentasi_job.php';
        if ($phpCli === null || !is_file($workerScript)) {
            throw new RuntimeException('Worker CLI render video tidak ditemukan.');
        }

        // Teruskan env DB penting agar worker menyambung ke database yang sama (termasuk saat testing)
        $envPrefix = '';
        foreach (['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'APP_URL', 'APP_ENV'] as $k) {
            $v = getenv($k);
            if ($v !== false && $v !== '') {
                $envPrefix .= $k . '=' . escapeshellarg($v) . ' ';
            }
        }

        $logFile = self::getJobsDir() . '/' . $jobId . '/worker.log';
        $niceBin = self::findBinary('nice');
        $nicePrefix = $niceBin !== null ? escapeshellarg($niceBin) . ' -n 10 ' : '';
        $cmd = $envPrefix . $nicePrefix . escapeshellarg($phpCli) . ' ' . escapeshellarg($workerScript) . ' ' . escapeshellarg($jobId)
            . ' > ' . escapeshellarg($logFile) . ' 2>&1 < /dev/null &';

        @exec($cmd);
    }

    /**
     * Eksekusi job render secara sinkron (dipanggil oleh cli/render_dokumentasi_job.php atau automated test).
     *
     * @return array<string, mixed>
     */
    public static function executeJob(string $jobId): array {
        if (!self::isValidJobId($jobId)) {
            throw new InvalidArgumentException('Job ID tidak valid.');
        }
        $jobDir = self::getJobsDir() . '/' . $jobId;
        $meta = self::readJobMeta($jobDir);
        if ($meta === null) {
            throw new RuntimeException('Metadata job render tidak ditemukan.');
        }
        if (($meta['status'] ?? '') === 'cancelled') {
            return $meta;
        }

        $meta['worker_pid'] = getmypid() ?: null;
        $meta['updated_at'] = time();
        self::writeJobMeta($jobDir, $meta);

        // Akuisisi 1 dari MAX_CONCURRENT_JOBS slot kunci agar CPU 2-core server tidak kewalahan
        $lockHandle = self::acquireWorkerSlotLock($jobDir, $meta);
        if ($lockHandle === null) {
            return self::readJobMeta($jobDir) ?? $meta;
        }

        try {
            $meta = self::readJobMeta($jobDir) ?? $meta;
            if (($meta['status'] ?? '') === 'cancelled') {
                return $meta;
            }

            $ffmpegBin = self::findBinary('ffmpeg');
            if ($ffmpegBin === null) {
                throw new RuntimeException('FFmpeg tidak tersedia di server.');
            }

            $spec = $meta['spec'];
            $meta['status'] = 'rendering';
            $meta['progress'] = 2;
            $meta['message'] = 'Memproses video kolase...';
            $meta['updated_at'] = time();
            self::writeJobMeta($jobDir, $meta);

            // Probe setiap slot video & tentukan durasi akhir akurat (maks 180 detik)
            $probedSlots = [];
            $maxClipDur = 0.0;
            foreach ($spec['video_slots'] as $vs) {
                $probe = self::probeVideo((string)$vs['abs_path']);
                $tStart = max(0.0, min(max(0.0, $probe['duration'] - 0.1), (float)($vs['trimStart'] ?? 0.0)));
                $tEnd = (isset($vs['trimEnd']) && is_numeric($vs['trimEnd'])) ? (float)$vs['trimEnd'] : null;
                $cDur = ($tEnd !== null && $tEnd > $tStart + 0.05)
                    ? min($probe['duration'] - $tStart, $tEnd - $tStart)
                    : max(0.2, $probe['duration'] - $tStart);
                $maxClipDur = max($maxClipDur, $cDur);
                $vs['probe'] = $probe;
                $probedSlots[] = $vs;
            }

            $totalDur = (float)($spec['duration'] ?? 0);
            if ($totalDur <= 0) {
                $totalDur = $maxClipDur > 0 ? $maxClipDur : 10.0;
            }
            $totalDur = max(1.0, min((float)OperasiDokumentasiModel::MAX_VIDEO_DURATION_SECONDS, $totalDur));
            $spec['duration'] = $totalDur;
            $meta['spec']['duration'] = $totalDur;

            $progressFile = $jobDir . '/ffmpeg_progress.txt';
            $outputMp4 = $jobDir . '/output.mp4';
            @unlink($progressFile);
            @unlink($outputMp4);

            $cmd = self::buildFfmpegCommand(
                $ffmpegBin,
                (string)$spec['overlay_path'],
                $probedSlots,
                $spec,
                $outputMp4,
                $progressFile
            );

            $desc = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($cmd, $desc, $pipes);
            if (!is_resource($proc)) {
                throw new RuntimeException('Gagal menjalankan proses FFmpeg.');
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);

            $procStatus = proc_get_status($proc);
            $meta['ffmpeg_pid'] = is_array($procStatus) ? ($procStatus['pid'] ?? null) : null;
            $meta['updated_at'] = time();
            self::writeJobMeta($jobDir, $meta);

            $stderrBuf = '';
            $cancelled = false;

            while (true) {
                $st = proc_get_status($proc);
                $errChunk = stream_get_contents($pipes[2]);
                if (is_string($errChunk) && $errChunk !== '') {
                    $stderrBuf .= $errChunk;
                    if (strlen($stderrBuf) > 8192) {
                        $stderrBuf = substr($stderrBuf, -4096);
                    }
                }
                // Buang stdout agar buffer pipe tidak pernah penuh
                @stream_get_contents($pipes[1]);

                // Cek sinyal batal
                if (is_file($jobDir . '/cancel.flag')) {
                    $cancelled = true;
                    @proc_terminate($proc, 15);
                    break;
                }

                // Perbarui progress periodik dari berkas -progress FFmpeg
                $pct = self::parseFfmpegProgressPct($progressFile, $totalDur);
                if ($pct !== null && $pct > (int)$meta['progress']) {
                    $meta['progress'] = min(96, $pct);
                    $meta['updated_at'] = time();
                    self::writeJobMeta($jobDir, $meta);
                }

                if (!is_array($st) || empty($st['running'])) {
                    break;
                }
                usleep(250000); // 250ms
            }

            $errTail = stream_get_contents($pipes[2]);
            if (is_string($errTail)) {
                $stderrBuf .= $errTail;
            }
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($proc);

            if ($cancelled) {
                @unlink($outputMp4);
                $meta['status'] = 'cancelled';
                $meta['message'] = 'Proses dibatalkan oleh pengguna.';
                $meta['finished_at'] = time();
                $meta['updated_at'] = time();
                self::writeJobMeta($jobDir, $meta);
                return $meta;
            }

            if ($exitCode !== 0 || !is_file($outputMp4) || (int)filesize($outputMp4) <= 0) {
                $cleanErr = trim(preg_replace('/\s+/', ' ', $stderrBuf));
                throw new RuntimeException('FFmpeg gagal merender kolase video' . ($cleanErr !== '' ? ': ' . mb_substr($cleanErr, 0, 260) : '.'));
            }

            $outSize = (int)filesize($outputMp4);
            if ($outSize > OperasiDokumentasiModel::MAX_FINAL_BYTES) {
                @unlink($outputMp4);
                throw new RuntimeException('Ukuran hasil video melebihi batas maksimal 150 MB. Coba turunkan kualitas atau potong durasi.');
            }

            $meta['progress'] = 98;
            $meta['updated_at'] = time();
            self::writeJobMeta($jobDir, $meta);

            if (($meta['mode'] ?? 'archive') === 'download') {
                $meta['status'] = 'completed';
                $meta['progress'] = 100;
                $meta['message'] = 'Video kolase siap diunduh.';
                $meta['download_file'] = $outputMp4;
                $meta['file_size'] = $outSize;
                $meta['finished_at'] = time();
                $meta['updated_at'] = time();
                self::writeJobMeta($jobDir, $meta);
                // Hapus overlay sementara agar hemat disk
                if (is_file((string)$spec['overlay_path'])) {
                    @unlink((string)$spec['overlay_path']);
                }
                return $meta;
            }

            // Simpan ke arsip DB + pindahkan MP4 ke uploads/operasi_dokumentasi/
            $fileInfo = [
                'tmp_name' => $outputMp4,
                'size' => $outSize,
                'name' => $meta['download_filename'] ?? 'kolase.mp4',
                'move_local' => true,
            ];
            $thumbInfo = null;
            if (!empty($spec['thumb_path']) && is_file((string)$spec['thumb_path'])) {
                $thumbInfo = [
                    'tmp_name' => (string)$spec['thumb_path'],
                    'size' => (int)filesize((string)$spec['thumb_path']),
                    'name' => 'poster.jpg',
                ];
            }

            $saveMeta = [
                'judul' => $spec['judul'],
                'hari_ke' => $spec['hari_ke'],
                'tanggal_label' => $spec['tanggal_label'],
                'aspect_ratio' => $spec['aspect_ratio'],
                'orientation' => $spec['orientation'],
                'slot_count' => $spec['slot_count'],
                'layout_preset' => $spec['layout_preset'],
                'width_px' => $spec['width_px'],
                'height_px' => $spec['height_px'],
                'project_json' => $spec['project_json'],
            ];

            $editDocId = $spec['edit_doc_id'] ?? null;
            if ($editDocId !== null && (int)$editDocId > 0) {
                $savedRow = OperasiDokumentasiModel::update(
                    (int)$editDocId,
                    $spec['id_operasi'],
                    $saveMeta,
                    $fileInfo,
                    $thumbInfo,
                    (string)$meta['user_id'],
                    $spec['expected_operasi_id']
                );
            } else {
                $savedRow = OperasiDokumentasiModel::store(
                    $spec['id_operasi'],
                    $saveMeta,
                    $fileInfo,
                    $thumbInfo,
                    (string)$meta['user_id']
                );
            }

            // Bersihkan overlay & thumb temp
            if (is_file((string)$spec['overlay_path'])) {
                @unlink((string)$spec['overlay_path']);
            }
            if (!empty($spec['thumb_path']) && is_file((string)$spec['thumb_path'])) {
                @unlink((string)$spec['thumb_path']);
            }

            $meta['status'] = 'completed';
            $meta['progress'] = 100;
            $meta['message'] = 'Dokumentasi kolase video berhasil disimpan.';
            $meta['item'] = $savedRow;
            $meta['finished_at'] = time();
            $meta['updated_at'] = time();
            self::writeJobMeta($jobDir, $meta);

            return $meta;
        } catch (Throwable $e) {
            error_log('[DokumentasiVideoService job ' . $jobId . '] ' . $e->getMessage());
            $meta['status'] = 'failed';
            $meta['error'] = $e->getMessage();
            $meta['message'] = $e->getMessage();
            $meta['finished_at'] = time();
            $meta['updated_at'] = time();
            self::writeJobMeta($jobDir, $meta);
            return $meta;
        } finally {
            @flock($lockHandle, LOCK_UN);
            @fclose($lockHandle);
        }
    }

    /**
     * Baca status live job render milik user.
     *
     * @return array<string, mixed>|null
     */
    public static function getJobStatus(string $jobId, string $userId): ?array {
        if (!self::isValidJobId($jobId)) {
            return null;
        }
        $jobDir = self::getJobsDir() . '/' . $jobId;
        $meta = self::readJobMeta($jobDir);
        if ($meta === null || (string)($meta['user_id'] ?? '') !== $userId) {
            return null;
        }

        $status = (string)($meta['status'] ?? 'queued');
        $progress = (int)($meta['progress'] ?? 0);

        if ($status === 'rendering') {
            $livePct = self::parseFfmpegProgressPct($jobDir . '/ffmpeg_progress.txt', (float)($meta['spec']['duration'] ?? 15.0));
            if ($livePct !== null && $livePct > $progress) {
                $progress = min(96, $livePct);
            }
        }

        // Deteksi worker mati tak terduga agar UI tidak pernah stuck selamanya
        if (in_array($status, ['queued', 'rendering'], true)) {
            $age = time() - (int)($meta['updated_at'] ?? $meta['created_at'] ?? time());
            $pid = (int)($meta['worker_pid'] ?? 0);
            $pidDead = ($pid > 0 && function_exists('posix_kill') && !@posix_kill($pid, 0));
            if (($pidDead && $age > 4) || ($pid === 0 && $age > 35)) {
                $meta['status'] = 'failed';
                $meta['error'] = 'Proses render terhenti tak terduga di server. Silakan coba simpan kembali.';
                $meta['message'] = $meta['error'];
                $meta['finished_at'] = time();
                self::writeJobMeta($jobDir, $meta);
                $status = 'failed';
            }
        }

        return [
            'job_id' => $jobId,
            'mode' => (string)($meta['mode'] ?? 'archive'),
            'status' => $status,
            'progress' => $status === 'completed' ? 100 : max(0, min(99, $progress)),
            'message' => (string)($meta['message'] ?? ''),
            'error' => $meta['error'] ?? null,
            'item' => $meta['item'] ?? null,
            'download_ready' => ($status === 'completed' && ($meta['mode'] ?? '') === 'download' && !empty($meta['download_file']) && is_file((string)$meta['download_file'])),
            'download_filename' => (string)($meta['download_filename'] ?? 'DOK_SAR.mp4'),
            'file_size' => (int)($meta['file_size'] ?? 0),
        ];
    }

    /**
     * Ambil path berkas MP4 untuk mode download (khusus pemilik job).
     *
     * @return array{path:string, filename:string}|null
     */
    public static function getDownloadFile(string $jobId, string $userId): ?array {
        if (!self::isValidJobId($jobId)) {
            return null;
        }
        $jobDir = self::getJobsDir() . '/' . $jobId;
        $meta = self::readJobMeta($jobDir);
        if ($meta === null || (string)($meta['user_id'] ?? '') !== $userId) {
            return null;
        }
        if (($meta['status'] ?? '') !== 'completed' || ($meta['mode'] ?? '') !== 'download') {
            return null;
        }
        $file = (string)($meta['download_file'] ?? '');
        if ($file === '' || !is_file($file)) {
            return null;
        }
        return [
            'path' => $file,
            'filename' => (string)($meta['download_filename'] ?? 'DOK_SAR.mp4'),
        ];
    }

    /**
     * Batalkan job render milik user.
     */
    public static function cancelJob(string $jobId, string $userId): bool {
        if (!self::isValidJobId($jobId)) {
            return false;
        }
        $jobDir = self::getJobsDir() . '/' . $jobId;
        $meta = self::readJobMeta($jobDir);
        if ($meta === null || (string)($meta['user_id'] ?? '') !== $userId) {
            return false;
        }

        @file_put_contents($jobDir . '/cancel.flag', (string)time());
        if (function_exists('posix_kill')) {
            $ffPid = (int)($meta['ffmpeg_pid'] ?? 0);
            if ($ffPid > 0) {
                @posix_kill($ffPid, 15);
            }
        }
        if (in_array((string)($meta['status'] ?? ''), ['queued', 'rendering'], true)) {
            $meta['status'] = 'cancelled';
            $meta['message'] = 'Proses dibatalkan.';
            $meta['finished_at'] = time();
            $meta['updated_at'] = time();
            self::writeJobMeta($jobDir, $meta);
        }
        return true;
    }

    public static function pruneExpiredJobs(int $ttlSeconds = self::JOB_TTL_SECONDS): int {
        $base = self::getJobsDir();
        if (!is_dir($base)) {
            return 0;
        }
        $cutoff = time() - max(120, $ttlSeconds);
        $removed = 0;
        $entries = @scandir($base) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php' || str_ends_with($entry, '.lock')) {
                continue;
            }
            $dir = $base . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $meta = self::readJobMeta($dir);
            $ts = is_array($meta) ? (int)($meta['updated_at'] ?? $meta['created_at'] ?? 0) : (int)(@filemtime($dir) ?: 0);
            if ($ts > 0 && $ts < $cutoff) {
                self::removeDirectory($dir);
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * @param array<string, mixed> $meta
     * @return resource|null
     */
    private static function acquireWorkerSlotLock(string $jobDir, array &$meta) {
        $jobsBase = self::getJobsDir();
        $deadline = time() + 600; // tunggu maksimal 10 menit di antrean
        while (time() < $deadline) {
            if (is_file($jobDir . '/cancel.flag')) {
                $meta['status'] = 'cancelled';
                $meta['finished_at'] = time();
                self::writeJobMeta($jobDir, $meta);
                return null;
            }
            for ($slot = 0; $slot < self::MAX_CONCURRENT_JOBS; $slot++) {
                $lockFile = $jobsBase . '/worker_slot_' . $slot . '.lock';
                $fh = @fopen($lockFile, 'c');
                if ($fh !== false && @flock($fh, LOCK_EX | LOCK_NB)) {
                    return $fh;
                }
                if ($fh !== false) {
                    fclose($fh);
                }
            }
            $meta['updated_at'] = time();
            self::writeJobMeta($jobDir, $meta);
            usleep(400000);
        }
        throw new RuntimeException('Antrean pemrosesan video server sedang penuh. Silakan coba beberapa saat lagi.');
    }

    private static function parseFfmpegProgressPct(string $progressFile, float $totalDuration): ?int {
        if (!is_file($progressFile) || $totalDuration <= 0) {
            return null;
        }
        $raw = @file_get_contents($progressFile);
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $outUs = null;
        if (preg_match_all('/out_time_us=(\d+)/', $raw, $m) && !empty($m[1])) {
            $outUs = (float)end($m[1]);
        } elseif (preg_match_all('/out_time_ms=(\d+)/', $raw, $m2) && !empty($m2[1])) {
            $outUs = (float)end($m2[1]);
        }
        if ($outUs === null) {
            return null;
        }
        $sec = $outUs / 1000000.0;
        return max(1, min(98, (int)round(($sec / $totalDuration) * 96.0)));
    }

    private static function isValidJobId(string $jobId): bool {
        return (bool)preg_match('/^[a-f0-9]{32}\z/', $jobId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function readJobMeta(string $jobDir): ?array {
        $path = $jobDir . '/job.json';
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function writeJobMeta(string $jobDir, array $meta): void {
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json !== false) {
            $tmp = $jobDir . '/job.json.tmp';
            if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
                @rename($tmp, $jobDir . '/job.json');
            }
        }
    }

    private static function sanitizeDownloadName(string $name): string {
        $base = basename(str_replace('\\', '/', trim($name)));
        $clean = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $base) ?: 'DOK_SAR.mp4';
        if (!str_ends_with(strtolower($clean), '.mp4')) {
            $clean = preg_replace('/\.[A-Za-z0-9]+$/', '', $clean) . '.mp4';
        }
        return mb_substr($clean, 0, 120);
    }

    private static function removeDirectory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            if (is_dir($p)) {
                self::removeDirectory($p);
            } else {
                @unlink($p);
            }
        }
        @rmdir($dir);
    }
}
