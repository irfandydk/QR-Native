<?php
/**
 * QR-SAR - Layanan Unggah Chunked Resumable untuk Dokumentasi Kolase
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Memecah unggahan berkas besar (sumber video/foto tanpa batas per file maupun hasil kolase hingga 150 MB)
 * menjadi potongan kecil (~4 MB/request) agar 100% lolos batas HTTP body Cloudflare (100 MB),
 * Nginx, dan PHP lokal, serta mendukung retry otomatis dan perakitan streaming hemat RAM.
 */

declare(strict_types=1);

class DokumentasiChunkUploadService {
    public const DEFAULT_CHUNK_BYTES = 4194304; // 4 MB (aman di bawah batas 100 MB Cloudflare & 20 MB PHP lokal)
    public const MAX_CHUNK_BYTES = 16777216;    // 16 MB per potongan
    public const MAX_CHUNKS = 4096;             // Mendukung berkas sumber multi-GB via chunk 4 MB
    public const SESSION_TTL_SECONDS = 21600;   // 6 jam
    public const MIN_DISK_SAFETY_BYTES = 209715200; // 200 MB cadangan ruang disk

    private const ALLOWED_PURPOSES = [
        'source',
        'final_media',
        'final_thumb',
        'overlay_png',
    ];

    public static function getBaseDir(): string {
        $dir = STORAGE_DIR . '/tmp_dok_uploads';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        $index = $dir . '/index.php';
        if (is_dir($dir) && !is_file($index)) {
            @file_put_contents($index, "<?php\nhttp_response_code(403);\nexit('Akses Ditolak.');\n");
        }
        return $dir;
    }

    /**
     * Bersihkan sesi unggah yatim yang lebih tua dari TTL.
     */
    public static function pruneExpired(int $ttlSeconds = self::SESSION_TTL_SECONDS): int {
        $base = self::getBaseDir();
        if (!is_dir($base)) {
            return 0;
        }
        $cutoff = time() - max(60, $ttlSeconds);
        $removed = 0;
        $entries = @scandir($base) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === 'index.php') {
                continue;
            }
            $dir = $base . '/' . $entry;
            if (!is_dir($dir)) {
                continue;
            }
            $metaFile = $dir . '/meta.json';
            $mtime = is_file($metaFile) ? @filemtime($metaFile) : @filemtime($dir);
            if ($mtime !== false && $mtime < $cutoff) {
                self::removeDirectory($dir);
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Mulai sesi unggah chunked baru.
     *
     * @return array<string, mixed>
     */
    public static function initSession(
        string $purpose,
        ?int $idOperasi,
        string $fileName,
        int $totalSize,
        int $totalChunks,
        string $userId
    ): array {
        self::pruneExpired();

        $purpose = strtolower(trim($purpose));
        if (!in_array($purpose, self::ALLOWED_PURPOSES, true)) {
            throw new InvalidArgumentException('Tujuan unggahan chunk tidak valid.');
        }
        if ($totalSize <= 0) {
            throw new InvalidArgumentException('Ukuran berkas tidak valid.');
        }
        if ($totalChunks < 1 || $totalChunks > self::MAX_CHUNKS) {
            throw new InvalidArgumentException('Jumlah potongan unggahan tidak valid.');
        }
        if ($purpose === 'final_media' && $totalSize > OperasiDokumentasiModel::MAX_FINAL_BYTES) {
            throw new InvalidArgumentException('Ukuran hasil kolase melebihi batas maksimal 150 MB.');
        }
        if ($purpose === 'final_thumb' && $totalSize > OperasiDokumentasiModel::MAX_THUMB_BYTES) {
            throw new InvalidArgumentException('Ukuran thumbnail melebihi batas maksimal 5 MB.');
        }
        if ($purpose === 'overlay_png' && $totalSize > 52428800) {
            throw new InvalidArgumentException('Ukuran frame overlay kolase terlalu besar.');
        }

        $base = self::getBaseDir();
        $freeBytes = @disk_free_space($base);
        if ($freeBytes !== false && ($totalSize + self::MIN_DISK_SAFETY_BYTES) > $freeBytes) {
            throw new RuntimeException('Ruang penyimpanan server tidak mencukupi untuk menerima berkas ini.');
        }

        $uploadId = bin2hex(random_bytes(16));
        $sessionDir = $base . '/' . $uploadId;
        if (!@mkdir($sessionDir, 0770, true)) {
            throw new RuntimeException('Gagal membuat ruang sementara unggahan di server.');
        }

        $safeName = self::sanitizeFilename($fileName);
        $meta = [
            'upload_id' => $uploadId,
            'purpose' => $purpose,
            'id_operasi' => ($idOperasi !== null && $idOperasi > 0) ? $idOperasi : null,
            'file_name' => $safeName,
            'total_size' => $totalSize,
            'total_chunks' => $totalChunks,
            'user_id' => $userId,
            'assembled' => false,
            'assembled_path' => null,
            'assembled_size' => 0,
            'created_at' => time(),
            'updated_at' => time(),
        ];

        self::writeMeta($sessionDir, $meta);

        return [
            'upload_id' => $uploadId,
            'chunk_size' => self::DEFAULT_CHUNK_BYTES,
            'total_chunks' => $totalChunks,
        ];
    }

    /**
     * Simpan satu potongan berkas ke direktori sesi.
     *
     * @param array{tmp_name:string, size:int, error?:int} $chunkFile
     * @return array<string, mixed>
     */
    public static function storeChunk(string $uploadId, int $chunkIndex, array $chunkFile, string $userId): array {
        [$sessionDir, $meta] = self::loadOwnedSession($uploadId, $userId);
        if (!empty($meta['assembled'])) {
            throw new InvalidArgumentException('Sesi unggah sudah diselesaikan.');
        }
        if ($chunkIndex < 0 || $chunkIndex >= (int)$meta['total_chunks']) {
            throw new InvalidArgumentException('Indeks potongan berada di luar jangkauan.');
        }

        $tmpPath = (string)($chunkFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw new InvalidArgumentException('Potongan berkas tidak diterima oleh server.');
        }
        $chunkSize = (int)(filesize($tmpPath) ?: ($chunkFile['size'] ?? 0));
        if ($chunkSize <= 0 || $chunkSize > self::MAX_CHUNK_BYTES) {
            throw new InvalidArgumentException('Ukuran potongan berkas tidak valid (maksimal 16 MB per potongan).');
        }

        $partPath = $sessionDir . '/part_' . str_pad((string)$chunkIndex, 5, '0', STR_PAD_LEFT);
        $ok = is_uploaded_file($tmpPath)
            ? @move_uploaded_file($tmpPath, $partPath)
            : @copy($tmpPath, $partPath);

        if (!$ok || !is_file($partPath)) {
            throw new RuntimeException('Gagal menyimpan potongan berkas ke disk sementara.');
        }

        $meta['updated_at'] = time();
        self::writeMeta($sessionDir, $meta);

        return [
            'upload_id' => $uploadId,
            'chunk_index' => $chunkIndex,
            'size' => $chunkSize,
        ];
    }

    /**
     * Rakit seluruh potongan secara streaming (tanpa lonjakan RAM).
     *
     * @return array<string, mixed>
     */
    public static function assembleSession(string $uploadId, string $userId): array {
        [$sessionDir, $meta] = self::loadOwnedSession($uploadId, $userId);
        if (!empty($meta['assembled']) && !empty($meta['assembled_path']) && is_file((string)$meta['assembled_path'])) {
            return $meta;
        }

        $totalChunks = (int)$meta['total_chunks'];
        $partPaths = [];
        for ($i = 0; $i < $totalChunks; $i++) {
            $p = $sessionDir . '/part_' . str_pad((string)$i, 5, '0', STR_PAD_LEFT);
            if (!is_file($p)) {
                throw new InvalidArgumentException("Potongan unggahan #{$i} belum lengkap.");
            }
            $partPaths[] = $p;
        }

        $assembledPath = $sessionDir . '/assembled.bin';
        $out = @fopen($assembledPath, 'wb');
        if ($out === false) {
            throw new RuntimeException('Gagal membuka berkas rakitan sementara.');
        }

        $writtenTotal = 0;
        try {
            foreach ($partPaths as $partPath) {
                $in = @fopen($partPath, 'rb');
                if ($in === false) {
                    throw new RuntimeException('Gagal membaca potongan berkas sementara.');
                }
                while (!feof($in)) {
                    $buf = fread($in, 1048576); // 1 MB stream buffer
                    if ($buf === false) {
                        fclose($in);
                        throw new RuntimeException('Gagal menyalin data potongan berkas.');
                    }
                    if ($buf !== '') {
                        $n = fwrite($out, $buf);
                        if ($n === false) {
                            fclose($in);
                            throw new RuntimeException('Gagal menulis berkas rakitan sementara.');
                        }
                        $writtenTotal += $n;
                    }
                }
                fclose($in);
            }
        } catch (Throwable $e) {
            fclose($out);
            @unlink($assembledPath);
            throw $e;
        }
        fclose($out);

        if ($writtenTotal <= 0) {
            @unlink($assembledPath);
            throw new InvalidArgumentException('Hasil rakitan berkas kosong.');
        }
        if ($meta['purpose'] === 'final_media' && $writtenTotal > OperasiDokumentasiModel::MAX_FINAL_BYTES) {
            @unlink($assembledPath);
            throw new InvalidArgumentException('Ukuran hasil kolase melebihi batas maksimal 150 MB.');
        }

        // Bersihkan potongan part_* segera untuk menghemat ruang disk
        foreach ($partPaths as $partPath) {
            @unlink($partPath);
        }

        $meta['assembled'] = true;
        $meta['assembled_path'] = $assembledPath;
        $meta['assembled_size'] = $writtenTotal;
        $meta['updated_at'] = time();
        self::writeMeta($sessionDir, $meta);

        return $meta;
    }

    /**
     * Ambil deskriptor berkas hasil rakitan agar dapat dipindahkan langsung (move_local) oleh Model/Service.
     *
     * @return array{tmp_name:string, size:int, name:string, move_local:bool, id_operasi:?int, purpose:string, upload_id:string}
     */
    public static function getAssembledFileInfo(string $uploadId, string $userId, ?string $expectedPurpose = null): array {
        [, $meta] = self::loadOwnedSession($uploadId, $userId);
        if ($expectedPurpose !== null && ($meta['purpose'] ?? '') !== $expectedPurpose) {
            throw new InvalidArgumentException('Tujuan sesi unggah tidak sesuai.');
        }
        $path = (string)($meta['assembled_path'] ?? '');
        if (empty($meta['assembled']) || $path === '' || !is_file($path)) {
            throw new InvalidArgumentException('Berkas unggahan belum selesai dirakit.');
        }
        return [
            'tmp_name' => $path,
            'size' => (int)filesize($path),
            'name' => (string)($meta['file_name'] ?? 'upload.bin'),
            'move_local' => true,
            'id_operasi' => $meta['id_operasi'] ?? null,
            'purpose' => (string)$meta['purpose'],
            'upload_id' => $uploadId,
        ];
    }

    public static function cleanupSession(string $uploadId): void {
        if (!self::isValidUploadId($uploadId)) {
            return;
        }
        $sessionDir = self::getBaseDir() . '/' . $uploadId;
        if (is_dir($sessionDir)) {
            self::removeDirectory($sessionDir);
        }
    }

    /**
     * @return array{0:string, 1:array<string, mixed>}
     */
    private static function loadOwnedSession(string $uploadId, string $userId): array {
        if (!self::isValidUploadId($uploadId)) {
            throw new InvalidArgumentException('ID sesi unggah tidak valid.');
        }
        $sessionDir = self::getBaseDir() . '/' . $uploadId;
        $metaPath = $sessionDir . '/meta.json';
        if (!is_dir($sessionDir) || !is_file($metaPath)) {
            throw new InvalidArgumentException('Sesi unggah tidak ditemukan atau sudah kedaluwarsa.');
        }
        $raw = @file_get_contents($metaPath);
        $meta = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($meta)) {
            throw new RuntimeException('Metadata sesi unggah rusak.');
        }
        if ((string)($meta['user_id'] ?? '') !== $userId) {
            throw new InvalidArgumentException('Akses ke sesi unggah ditolak.');
        }
        return [$sessionDir, $meta];
    }

    private static function isValidUploadId(string $uploadId): bool {
        return (bool)preg_match('/^[a-f0-9]{32}\z/', $uploadId);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private static function writeMeta(string $sessionDir, array $meta): void {
        $json = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($sessionDir . '/meta.json', $json, LOCK_EX) === false) {
            throw new RuntimeException('Gagal menyimpan metadata sesi unggah.');
        }
    }

    private static function sanitizeFilename(string $name): string {
        $base = basename(str_replace('\\', '/', trim($name)));
        $clean = preg_replace('/[^A-Za-z0-9._\-]+/', '_', $base) ?: 'media.bin';
        return mb_substr($clean, 0, 140);
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
