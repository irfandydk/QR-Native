<?php
/**
 * QR-SAR - REST API Controller Arsip Dokumentasi Kolase Umum & Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Endpoints Terpadu (Pusat Dokumentasi Umum & Operasi SAR):
 *   GET    /api/dokumentasi/capabilities        (cek dukungan FFmpeg server, ukuran chunk, & batas 150 MB)
 *   POST   /api/dokumentasi/upload/init         (mulai sesi unggah chunked 4 MB/potongan)
 *   POST   /api/dokumentasi/upload/chunk        (kirim satu potongan berkas)
 *   POST   /api/dokumentasi/upload/complete     (rakit berkas & opsional langsung simpan sumber slot)
 *   POST   /api/dokumentasi/render              (mulai background render video kolase FFmpeg)
 *   GET    /api/dokumentasi/jobs/{jobId}        (pantau progres real-time & hasil render video)
 *   GET    /api/dokumentasi/jobs/{jobId}/download (unduh hasil MP4 mode download)
 *   DELETE /api/dokumentasi/jobs/{jobId}        (batalkan job render berjalan)
 *   GET    /api/dokumentasi
 *   GET    /api/dokumentasi/{docId}
 *   POST   /api/dokumentasi/source              (unggah sumber per-slot langsung atau via chunk)
 *   POST   /api/dokumentasi                     (simpan kolase langsung atau via media_upload_id chunk)
 *   POST   /api/dokumentasi/{docId}             (perbarui arsip tersimpan in-place)
 *   DELETE /api/dokumentasi/{docId}             (khusus ADMIN — Opsi 1 RBAC)
 *
 * Endpoints Spesifik Operasi SAR:
 *   GET    /api/operasi/{id}/dokumentasi
 *   GET    /api/operasi/{id}/dokumentasi/{docId}
 *   POST   /api/operasi/{id}/dokumentasi/source
 *   POST   /api/operasi/{id}/dokumentasi
 *   POST   /api/operasi/{id}/dokumentasi/{docId}
 *   DELETE /api/operasi/{id}/dokumentasi/{docId} (khusus ADMIN — Opsi 1 RBAC)
 */

declare(strict_types=1);

class OperasiDokumentasiApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 0A. Kapabilitas Studio Dokumentasi (/dokumentasi/capabilities)
        if ($path === '/dokumentasi/capabilities') {
            self::requireAuth();
            if ($method !== 'GET') {
                self::error('Metode HTTP tidak didukung.', 405);
            }
            self::json([
                'success' => true,
                'capabilities' => DokumentasiVideoService::getCapabilities(),
            ]);
            return true;
        }

        // 0B. Unggah Chunked Resumable (/dokumentasi/upload/init|chunk|complete)
        if ($path === '/dokumentasi/upload/init') {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung.', 405);
            }
            return self::handleChunkInit($input);
        }
        if ($path === '/dokumentasi/upload/chunk') {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung.', 405);
            }
            return self::handleChunkPart();
        }
        if ($path === '/dokumentasi/upload/complete') {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung.', 405);
            }
            return self::handleChunkComplete($input);
        }

        // 0C. Background Server-Side FFmpeg Render & Status Job
        if ($path === '/dokumentasi/render') {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung.', 405);
            }
            return self::handleStartRenderJob($input);
        }
        if (preg_match('#^/dokumentasi/jobs/([a-f0-9]{32})/download\z#', $path, $mjd)) {
            self::requireAuth();
            if ($method !== 'GET') {
                self::error('Metode HTTP tidak didukung.', 405);
            }
            return self::handleDownloadRenderJob($mjd[1]);
        }
        if (preg_match('#^/dokumentasi/jobs/([a-f0-9]{32})\z#', $path, $mj)) {
            self::requireAuth();
            $jobId = $mj[1];
            $uid = self::currentUserIdString();
            if ($method === 'GET') {
                $status = DokumentasiVideoService::getJobStatus($jobId, $uid);
                if ($status === null) {
                    self::error('Job pemrosesan video tidak ditemukan.', 404);
                }
                if (is_array($status['item'] ?? null)) {
                    $status['item'] = self::serializeItem($status['item'], true);
                }
                if (!empty($status['download_ready'])) {
                    $status['download_url'] = BASE_URL . '/api/index.php?path=/dokumentasi/jobs/' . $jobId . '/download';
                }
                self::json(array_merge(['success' => true], $status));
                return true;
            }
            if ($method === 'DELETE') {
                $ok = DokumentasiVideoService::cancelJob($jobId, $uid);
                if (!$ok) {
                    self::error('Job pemrosesan video tidak ditemukan.', 404);
                }
                self::json(['success' => true, 'message' => 'Pemrosesan video dibatalkan.']);
                return true;
            }
            self::error('Metode HTTP tidak didukung.', 405);
            return true;
        }

        // 1A. Unggah aset sumber per-slot dari Pusat Dokumentasi Umum (/dokumentasi/source)
        if ($path === '/dokumentasi/source') {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung untuk unggah aset sumber dokumentasi.', 405);
            }
            $rawOps = isset($_POST['id_operasi']) ? (int)$_POST['id_operasi'] : 0;
            $targetOps = $rawOps > 0 ? $rawOps : null;
            if ($targetOps !== null && OperasiModel::getStatusById($targetOps) === null) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }
            return self::handleSourceUpload($targetOps);
        }

        // 1B. Unggah aset sumber per-slot dari Studio Operasi SAR (/operasi/{id}/dokumentasi/source)
        if (preg_match('#^/operasi/(\d+)/dokumentasi/source\z#', $path, $ms)) {
            self::requireAuth();
            if ($method !== 'POST') {
                self::error('Metode HTTP tidak didukung untuk unggah aset sumber dokumentasi.', 405);
            }
            $idOperasi = (int)$ms[1];
            if (OperasiModel::getStatusById($idOperasi) === null) {
                self::error('Operasi SAR tidak ditemukan.', 404);
            }
            return self::handleSourceUpload($idOperasi);
        }

        // 2A. Endpoint Pusat Dokumentasi Terpadu (/dokumentasi dan /dokumentasi/{docId})
        if (preg_match('#^/dokumentasi(?:/(\d+))?\z#', $path, $mg)) {
            self::requireAuth();
            $docId = isset($mg[1]) ? (int)$mg[1] : null;

            if ($docId === null && $method === 'GET') {
                $items = OperasiDokumentasiModel::getAllWithOperasi($_GET);
                self::json([
                    'success' => true,
                    'stats' => OperasiDokumentasiModel::getArchiveStats(),
                    'items' => array_map([self::class, 'serializeItem'], $items),
                ]);
                return true;
            }

            if ($docId !== null && $method === 'GET') {
                $row = OperasiDokumentasiModel::getById($docId);
                if (!$row) {
                    self::error('Arsip dokumentasi tidak ditemukan.', 404);
                }
                self::json([
                    'success' => true,
                    'item' => self::serializeItem($row, true),
                ]);
                return true;
            }

            if ($method === 'POST') {
                $postData = !empty($_POST) ? $_POST : $input;
                $rawOps = isset($postData['id_operasi']) ? (int)$postData['id_operasi'] : 0;
                $targetOps = $rawOps > 0 ? $rawOps : null;
                if ($targetOps !== null && OperasiModel::getStatusById($targetOps) === null) {
                    self::error('Operasi SAR yang dipilih tidak ditemukan.', 404);
                }
                return self::handleStoreOrUpdate($docId, $targetOps, null, $postData);
            }

            if ($docId !== null && $method === 'DELETE') {
                if (!AuthHelper::canDelete('dokumentasi')) {
                    self::error('Akses ditolak: Hanya Administrator yang dapat menghapus arsip dokumentasi.', 403, 'FORBIDDEN');
                }
                $deleted = OperasiDokumentasiModel::delete($docId);
                if (!$deleted) {
                    self::error('Arsip dokumentasi tidak ditemukan.', 404);
                }
                self::json(['success' => true, 'message' => 'Arsip dokumentasi berhasil dihapus.']);
                return true;
            }

            self::error('Metode HTTP tidak didukung untuk endpoint dokumentasi.', 405);
            return true;
        }

        // 2B. Endpoint Spesifik Operasi SAR (/operasi/{id}/dokumentasi dan /operasi/{id}/dokumentasi/{docId})
        if (!preg_match('#^/operasi/(\d+)/dokumentasi(?:/(\d+))?\z#', $path, $m)) {
            return false;
        }

        self::requireAuth();
        $idOperasi = (int)$m[1];
        $docId = isset($m[2]) ? (int)$m[2] : null;

        $ops = OperasiModel::getStatusById($idOperasi);
        if ($ops === null) {
            self::error('Operasi SAR tidak ditemukan.', 404);
        }

        if ($docId === null && $method === 'GET') {
            $items = OperasiDokumentasiModel::getByOperasiId($idOperasi);
            self::json([
                'success' => true,
                'items' => array_map([self::class, 'serializeItem'], $items),
            ]);
            return true;
        }

        if ($docId !== null && $method === 'GET') {
            $row = OperasiDokumentasiModel::getById($docId);
            if (!$row || (int)($row['id_operasi'] ?? 0) !== $idOperasi) {
                self::error('Arsip dokumentasi tidak ditemukan.', 404);
            }
            self::json([
                'success' => true,
                'item' => self::serializeItem($row, true),
            ]);
            return true;
        }

        if ($method === 'POST') {
            $postData = !empty($_POST) ? $_POST : $input;
            return self::handleStoreOrUpdate($docId, $idOperasi, $idOperasi, $postData);
        }

        if ($docId !== null && $method === 'DELETE') {
            if (!AuthHelper::canDelete('dokumentasi')) {
                self::error('Akses ditolak: Hanya Administrator yang dapat menghapus arsip dokumentasi.', 403, 'FORBIDDEN');
            }
            $deleted = OperasiDokumentasiModel::delete($docId, $idOperasi);
            if (!$deleted) {
                self::error('Arsip dokumentasi tidak ditemukan.', 404);
            }
            self::json(['success' => true, 'message' => 'Arsip dokumentasi berhasil dihapus.']);
            return true;
        }

        self::error('Metode HTTP tidak didukung untuk endpoint dokumentasi.', 405);
        return true;
    }

    private static function currentUserIdString(): string {
        $uid = AuthHelper::getCurrentUser()['id'] ?? null;
        return $uid !== null ? (string)$uid : 'guest';
    }

    private static function handleChunkInit(array $input): bool {
        $data = !empty($input) ? $input : $_POST;
        $purpose = (string)($data['purpose'] ?? 'source');
        $rawOps = isset($data['id_operasi']) ? (int)$data['id_operasi'] : 0;
        $targetOps = $rawOps > 0 ? $rawOps : null;
        if ($targetOps !== null && OperasiModel::getStatusById($targetOps) === null) {
            self::error('Operasi SAR tidak ditemukan.', 404);
        }
        $fileName = (string)($data['file_name'] ?? 'media.bin');
        $totalSize = (int)($data['total_size'] ?? 0);
        $totalChunks = (int)($data['total_chunks'] ?? 0);

        try {
            $res = DokumentasiChunkUploadService::initSession(
                $purpose,
                $targetOps,
                $fileName,
                $totalSize,
                $totalChunks,
                self::currentUserIdString()
            );
            self::json(array_merge(['success' => true], $res), 201);
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiApiController /upload/init] ' . $e->getMessage());
            self::error($e->getMessage() ?: 'Gagal memulai sesi unggahan.', 500);
        }
        return true;
    }

    private static function handleChunkPart(): bool {
        $uploadId = trim((string)($_POST['upload_id'] ?? ''));
        $chunkIndex = isset($_POST['chunk_index']) ? (int)$_POST['chunk_index'] : -1;
        $chunkFile = $_FILES['chunk'] ?? null;
        if (!is_array($chunkFile) || ($chunkFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            self::error('Potongan berkas tidak diterima dengan lengkap.', 422);
        }

        try {
            $res = DokumentasiChunkUploadService::storeChunk(
                $uploadId,
                $chunkIndex,
                $chunkFile,
                self::currentUserIdString()
            );
            self::json(array_merge(['success' => true], $res));
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiApiController /upload/chunk] ' . $e->getMessage());
            self::error('Gagal menyimpan potongan unggahan.', 500);
        }
        return true;
    }

    private static function handleChunkComplete(array $input): bool {
        $data = !empty($input) ? $input : $_POST;
        $uploadId = trim((string)($data['upload_id'] ?? ''));
        $uid = self::currentUserIdString();

        try {
            $meta = DokumentasiChunkUploadService::assembleSession($uploadId, $uid);
            $purpose = (string)($meta['purpose'] ?? '');

            // Jika tujuan = 'source', langsung validasi & simpan sebagai aset sumber per-slot
            if ($purpose === 'source') {
                $fileInfo = DokumentasiChunkUploadService::getAssembledFileInfo($uploadId, $uid, 'source');
                try {
                    $asset = OperasiDokumentasiModel::storeSourceAsset($fileInfo['id_operasi'], $fileInfo);
                } finally {
                    DokumentasiChunkUploadService::cleanupSession($uploadId);
                }
                self::json([
                    'success' => true,
                    'purpose' => 'source',
                    'path' => $asset['path'],
                    'url' => BASE_URL . '/' . ltrim($asset['path'], '/'),
                    'media_type' => $asset['media_type'],
                    'size' => $asset['size'],
                ], 201);
                return true;
            }

            self::json([
                'success' => true,
                'upload_id' => $uploadId,
                'purpose' => $purpose,
                'size' => (int)($meta['assembled_size'] ?? 0),
            ], 200);
        } catch (InvalidArgumentException $e) {
            DokumentasiChunkUploadService::cleanupSession($uploadId);
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiApiController /upload/complete] ' . $e->getMessage());
            self::error('Gagal menyelesaikan perakitan berkas unggahan.', 500);
        }
        return true;
    }

    private static function handleStartRenderJob(array $input): bool {
        $postData = !empty($_POST) ? $_POST : $input;
        $uid = self::currentUserIdString();
        $cleanupUploadIds = [];

        try {
            $overlayTmpPath = '';
            $overlayUploadId = trim((string)($postData['overlay_upload_id'] ?? ''));
            if ($overlayUploadId !== '') {
                $ovInfo = DokumentasiChunkUploadService::getAssembledFileInfo($overlayUploadId, $uid, 'overlay_png');
                $overlayTmpPath = $ovInfo['tmp_name'];
                $cleanupUploadIds[] = $overlayUploadId;
            } elseif (isset($_FILES['overlay_file']) && is_array($_FILES['overlay_file']) && ($_FILES['overlay_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $overlayTmpPath = (string)$_FILES['overlay_file']['tmp_name'];
            }

            $thumbTmpPath = '';
            $thumbUploadId = trim((string)($postData['thumb_upload_id'] ?? ''));
            if ($thumbUploadId !== '') {
                $tInfo = DokumentasiChunkUploadService::getAssembledFileInfo($thumbUploadId, $uid, 'final_thumb');
                $thumbTmpPath = $tInfo['tmp_name'];
                $cleanupUploadIds[] = $thumbUploadId;
            } elseif (isset($_FILES['thumb_file']) && is_array($_FILES['thumb_file']) && ($_FILES['thumb_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $thumbTmpPath = (string)$_FILES['thumb_file']['tmp_name'];
            }

            $videoSlots = $postData['video_slots'] ?? [];
            if (is_string($videoSlots)) {
                $videoSlots = json_decode($videoSlots, true) ?: [];
            }

            $payload = [
                'mode' => (string)($postData['mode'] ?? 'archive'),
                'edit_doc_id' => isset($postData['edit_doc_id']) ? (int)$postData['edit_doc_id'] : 0,
                'id_operasi' => isset($postData['id_operasi']) ? (int)$postData['id_operasi'] : 0,
                'expected_operasi_id' => isset($postData['expected_operasi_id']) ? (int)$postData['expected_operasi_id'] : 0,
                'judul' => (string)($postData['judul'] ?? ''),
                'hari_ke' => (string)($postData['hari_ke'] ?? ''),
                'tanggal_label' => (string)($postData['tanggal_label'] ?? ''),
                'aspect_ratio' => (string)($postData['aspect_ratio'] ?? '2:3'),
                'orientation' => (string)($postData['orientation'] ?? 'vertical'),
                'slot_count' => (int)($postData['slot_count'] ?? 6),
                'layout_preset' => (string)($postData['layout_preset'] ?? ''),
                'width_px' => (int)($postData['width_px'] ?? 1080),
                'height_px' => (int)($postData['height_px'] ?? 1620),
                'duration' => (float)($postData['duration'] ?? 15.0),
                'project_json' => isset($postData['project_json']) ? (string)$postData['project_json'] : null,
                'download_filename' => (string)($postData['download_filename'] ?? 'DOK_SAR.mp4'),
                'overlay_tmp_path' => $overlayTmpPath,
                'thumb_tmp_path' => $thumbTmpPath,
                'video_slots' => $videoSlots,
            ];

            $job = DokumentasiVideoService::createRenderJob($payload, $uid, true);
            foreach ($cleanupUploadIds as $cid) {
                DokumentasiChunkUploadService::cleanupSession($cid);
            }

            self::json(array_merge(['success' => true], $job), 202);
        } catch (InvalidArgumentException $e) {
            foreach ($cleanupUploadIds as $cid) {
                DokumentasiChunkUploadService::cleanupSession($cid);
            }
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            foreach ($cleanupUploadIds as $cid) {
                DokumentasiChunkUploadService::cleanupSession($cid);
            }
            error_log('[OperasiDokumentasiApiController /render] ' . $e->getMessage());
            self::error($e->getMessage() ?: 'Gagal memulai pemrosesan video kolase.', 500);
        }
        return true;
    }

    private static function handleDownloadRenderJob(string $jobId): bool {
        $uid = self::currentUserIdString();
        $dl = DokumentasiVideoService::getDownloadFile($jobId, $uid);
        if ($dl === null || !is_file($dl['path'])) {
            self::error('Berkas video hasil render tidak ditemukan atau sudah kedaluwarsa.', 404);
        }
        $size = (int)filesize($dl['path']);
        if (!headers_sent()) {
            header('Content-Type: video/mp4');
            header('Content-Length: ' . $size);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $dl['filename']) . '"');
            header('Cache-Control: private, no-store');
        }
        $fp = fopen($dl['path'], 'rb');
        if ($fp !== false) {
            while (!feof($fp)) {
                echo fread($fp, 1048576);
                @ob_flush();
                @flush();
            }
            fclose($fp);
        }
        exit;
    }

    private static function handleSourceUpload(?int $idOperasi): bool {
        $file = $_FILES['source_file'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $errCode = is_array($file) ? (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
            $msg = ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE)
                ? 'Ukuran unggahan langsung melebihi konfigurasi PHP. Gunakan unggah bertahap otomatis.'
                : 'Berkas sumber media slot tidak diterima dengan lengkap.';
            self::error($msg, 422);
        }
        try {
            $asset = OperasiDokumentasiModel::storeSourceAsset($idOperasi, $file);
            self::json([
                'success' => true,
                'path' => $asset['path'],
                'url' => BASE_URL . '/' . ltrim($asset['path'], '/'),
                'media_type' => $asset['media_type'],
                'size' => $asset['size'],
            ], 201);
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiApiController POST /source] ' . $e->getMessage());
            self::error('Gagal menyimpan aset sumber media.', 500);
        }
        return true;
    }

    private static function handleStoreOrUpdate(?int $docId, ?int $targetOperasiId, ?int $expectedOperasiId, array $postData = []): bool {
        $uidStr = self::currentUserIdString();
        $cleanupUploadIds = [];
        $file = null;

        $mediaUploadId = trim((string)($postData['media_upload_id'] ?? ''));
        if ($mediaUploadId !== '') {
            try {
                $file = DokumentasiChunkUploadService::getAssembledFileInfo($mediaUploadId, $uidStr, 'final_media');
                $cleanupUploadIds[] = $mediaUploadId;
            } catch (InvalidArgumentException $e) {
                self::error($e->getMessage(), 422);
            }
        } else {
            $uploaded = $_FILES['media_file'] ?? null;
            if (!is_array($uploaded) || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $errCode = is_array($uploaded) ? (int)($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
                $msg = ($errCode === UPLOAD_ERR_INI_SIZE || $errCode === UPLOAD_ERR_FORM_SIZE)
                    ? 'Ukuran berkas melebihi batas unggahan maksimal 150 MB.'
                    : 'Berkas kolase tidak diterima dengan lengkap.';
                self::error($msg, 422);
            }
            $file = $uploaded;
        }

        $thumb = null;
        $thumbUploadId = trim((string)($postData['thumb_upload_id'] ?? ''));
        if ($thumbUploadId !== '') {
            try {
                $thumb = DokumentasiChunkUploadService::getAssembledFileInfo($thumbUploadId, $uidStr, 'final_thumb');
                $cleanupUploadIds[] = $thumbUploadId;
            } catch (InvalidArgumentException) {
                $thumb = null;
            }
        } elseif (isset($_FILES['thumb_file']) && is_array($_FILES['thumb_file']) && ($_FILES['thumb_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $thumb = $_FILES['thumb_file'];
        }

        $userId = AuthHelper::getCurrentUser()['id'] ?? null;
        $auditUid = $userId === null ? null : (string)$userId;
        try {
            if ($docId !== null) {
                $updated = OperasiDokumentasiModel::update($docId, $targetOperasiId, $postData, $file, $thumb, $auditUid, $expectedOperasiId);
                self::json([
                    'success' => true,
                    'message' => 'Perubahan dokumentasi kolase berhasil disimpan.',
                    'item' => self::serializeItem($updated, true),
                ], 200);
            } else {
                $saved = OperasiDokumentasiModel::store($targetOperasiId, $postData, $file, $thumb, $auditUid);
                self::json([
                    'success' => true,
                    'message' => 'Dokumentasi kolase berhasil disimpan ke arsip.',
                    'item' => self::serializeItem($saved, true),
                ], 201);
            }
        } catch (InvalidArgumentException $e) {
            self::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiApiController POST] ' . $e->getMessage());
            self::error('Gagal menyimpan dokumentasi kolase.', 500);
        } finally {
            foreach ($cleanupUploadIds as $cid) {
                DokumentasiChunkUploadService::cleanupSession($cid);
            }
        }
        return true;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function serializeItem(array $row, bool $includeProject = false): array {
        $filePath = ltrim((string)($row['file_path'] ?? ''), '/');
        $thumbPath = ltrim((string)($row['thumb_path'] ?? ''), '/');
        $projectRaw = isset($row['project_json']) ? (string)$row['project_json'] : '';
        $idOps = ($row['id_operasi'] ?? null) !== null && (int)$row['id_operasi'] > 0 ? (int)$row['id_operasi'] : null;
        $item = [
            'id_dokumentasi' => (int)($row['id_dokumentasi'] ?? 0),
            'id_operasi' => $idOps,
            'is_umum' => $idOps === null,
            'nama_operasi' => isset($row['nama_operasi']) ? (string)$row['nama_operasi'] : '',
            'judul' => (string)($row['judul'] ?? ''),
            'hari_ke' => (string)($row['hari_ke'] ?? ($idOps !== null ? 'H1' : 'KEGIATAN')),
            'tanggal_label' => (string)($row['tanggal_label'] ?? ''),
            'media_type' => (string)($row['media_type'] ?? 'image'),
            'mime_type' => (string)($row['mime_type'] ?? 'image/jpeg'),
            'aspect_ratio' => (string)($row['aspect_ratio'] ?? '2:3'),
            'orientation' => (string)($row['orientation'] ?? 'vertical'),
            'slot_count' => (int)($row['slot_count'] ?? 6),
            'layout_preset' => (string)($row['layout_preset'] ?? ''),
            'width_px' => (int)($row['width_px'] ?? 1080),
            'height_px' => (int)($row['height_px'] ?? 1620),
            'file_url' => $filePath !== '' ? BASE_URL . '/' . $filePath : '',
            'thumb_url' => $thumbPath !== '' ? BASE_URL . '/' . $thumbPath : ($filePath !== '' && ($row['media_type'] ?? '') === 'image' ? BASE_URL . '/' . $filePath : ''),
            'file_size' => (int)($row['file_size'] ?? 0),
            'has_project' => trim($projectRaw) !== '',
            'created_at' => (string)($row['created_at'] ?? ''),
        ];
        if ($includeProject) {
            $item['project'] = OperasiDokumentasiModel::decodeProjectForClient($projectRaw, BASE_URL);
        }
        return $item;
    }
}
