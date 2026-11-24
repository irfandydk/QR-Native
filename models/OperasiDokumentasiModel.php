<?php
/**
 * QR-SAR - Model Arsip Dokumentasi Kolase Umum & Operasi SAR (Foto & Video)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Menyimpan hasil kolase dokumentasi umum (id_operasi = NULL) maupun operasi SAR (id_operasi > 0)
 * berformat gambar (JPEG/PNG/WebP) atau video (WebM/MP4) beserta metadata tata letak (4-12 slot),
 * thumbnail video, serta project_json + aset sumber per-slot agar arsip dapat diedit ulang penuh.
 */

declare(strict_types=1);

class OperasiDokumentasiModel {
    public const MIN_SLOTS = 4;
    public const MAX_SLOTS = 12;
    public const MAX_IMAGE_BYTES = 20971520; // 20 MB (mendukung ekspor 4K PNG/JPG tajam)
    public const MAX_VIDEO_BYTES = 20971520; // 20 MB (selaras .user.ini upload_max_filesize)
    public const MAX_THUMB_BYTES = 3145728;  // 3 MB
    public const MAX_PIXELS = 40000000;
    public const MAX_PROJECT_JSON_BYTES = 65535;
    public const REL_DIR = 'uploads/operasi_dokumentasi';

    private const ALLOWED_IMAGE_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const ALLOWED_VIDEO_MIME = [
        'video/webm' => 'webm',
        'video/mp4' => 'mp4',
    ];

    private static bool $schemaChecked = false;

    /**
     * Pastikan direktori unggahan ada dan terlindungi index.php 403.
     */
    public static function ensureUploadDir(): string {
        $dir = ROOT_DIR . '/' . self::REL_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $index = $dir . '/index.php';
        if (is_dir($dir) && !is_file($index)) {
            @file_put_contents($index, "<?php\nhttp_response_code(403);\nexit('Akses Ditolak.');\n");
        }
        return $dir;
    }

    /**
     * Pastikan tabel OperasiDokumentasi, kolom project_json, id_operasi nullable, dan indeks kronologis tersedia idempoten.
     * Aman terhadap transaksi aktif MySQL: cek SHOW TABLES/COLUMNS terlebih dulu agar tidak memicu DDL implicit commit.
     */
    public static function ensureSchemaUpdated(bool $force = false): void {
        if (self::$schemaChecked && !$force) {
            return;
        }

        try {
            $db = getDB();
            $exists = $db->query("SHOW TABLES LIKE 'OperasiDokumentasi'")->fetchColumn();
            if ($exists === false) {
                if ($db->inTransaction()) {
                    return;
                }
                $db->exec("CREATE TABLE IF NOT EXISTS `OperasiDokumentasi` (
                    `id_dokumentasi` INT AUTO_INCREMENT PRIMARY KEY,
                    `id_operasi` INT NULL DEFAULT NULL,
                    `judul` VARCHAR(255) NOT NULL,
                    `hari_ke` VARCHAR(32) NOT NULL DEFAULT 'H1',
                    `tanggal_label` VARCHAR(100) NOT NULL DEFAULT '',
                    `media_type` ENUM('image', 'video') NOT NULL DEFAULT 'image',
                    `mime_type` VARCHAR(64) NOT NULL DEFAULT 'image/jpeg',
                    `aspect_ratio` VARCHAR(24) NOT NULL DEFAULT '2:3',
                    `orientation` ENUM('vertical', 'horizontal', 'square') NOT NULL DEFAULT 'vertical',
                    `slot_count` TINYINT UNSIGNED NOT NULL DEFAULT 6,
                    `layout_preset` VARCHAR(64) NOT NULL DEFAULT 'v_6_2x3',
                    `width_px` SMALLINT UNSIGNED NOT NULL DEFAULT 1080,
                    `height_px` SMALLINT UNSIGNED NOT NULL DEFAULT 1620,
                    `file_path` VARCHAR(255) NOT NULL,
                    `thumb_path` VARCHAR(255) NULL DEFAULT NULL,
                    `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
                    `project_json` LONGTEXT NULL DEFAULT NULL,
                    `created_by` VARCHAR(64) NULL DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_ops_dok_operasi` (`id_operasi`, `created_at`),
                    KEY `idx_ops_dok_created_at` (`created_at`, `id_dokumentasi`),
                    CONSTRAINT `fk_ops_dok_operasi` FOREIGN KEY (`id_operasi`) REFERENCES `Operasi`(`id_operasi`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                self::$schemaChecked = true;
                return;
            }

            if (!$db->inTransaction()) {
                $colExists = $db->query("SHOW COLUMNS FROM `OperasiDokumentasi` LIKE 'project_json'")->fetchColumn();
                if ($colExists === false) {
                    $db->exec("ALTER TABLE `OperasiDokumentasi` ADD COLUMN `project_json` LONGTEXT NULL DEFAULT NULL AFTER `file_size`");
                }

                $idOpsCol = $db->query("SHOW COLUMNS FROM `OperasiDokumentasi` LIKE 'id_operasi'")->fetch(PDO::FETCH_ASSOC);
                if (is_array($idOpsCol) && strtoupper((string)($idOpsCol['Null'] ?? '')) !== 'YES') {
                    $db->exec("ALTER TABLE `OperasiDokumentasi` MODIFY COLUMN `id_operasi` INT NULL DEFAULT NULL");
                }

                $idxCreated = $db->query("SHOW INDEX FROM `OperasiDokumentasi` WHERE Key_name = 'idx_ops_dok_created_at'")->fetchAll();
                if (empty($idxCreated)) {
                    $db->exec("ALTER TABLE `OperasiDokumentasi` ADD INDEX `idx_ops_dok_created_at` (`created_at`, `id_dokumentasi`)");
                }
            }
            self::$schemaChecked = true;
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiModel ensureSchemaUpdated] ' . $e->getMessage());
        }
    }

    /**
     * Normalisasi input id_operasi: NULL atau 0 berarti Dokumentasi Umum (NULL), negatif ditolak.
     */
    private static function normalizeOperasiId(?int $idOperasi): ?int {
        if ($idOperasi === null || $idOperasi === 0) {
            return null;
        }
        if ($idOperasi < 0) {
            throw new InvalidArgumentException('ID Operasi SAR tidak valid.');
        }
        return $idOperasi;
    }

    /**
     * Ambil seluruh arsip dokumentasi milik suatu operasi (terbaru di atas).
     * Murni read-only tanpa DDL agar aman dipanggil di dalam transaksi aktif.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getByOperasiId(int $idOperasi): array {
        if ($idOperasi <= 0) {
            return [];
        }
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM OperasiDokumentasi WHERE id_operasi = :id ORDER BY id_dokumentasi DESC");
            $stmt->execute([':id' => $idOperasi]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Ambil seluruh arsip dokumentasi terpadu (Dokumentasi Umum + Operasi SAR) diurutkan berdasarkan waktu pembuatan terbaru.
     * Mendukung filter: scope ('all'|'umum'|'operasi'), id_operasi, media_type ('image'|'video'), month ('YYYY-MM'), dan q (kata kunci).
     *
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function getAllWithOperasi(array $filters = []): array {
        try {
            $db = getDB();
            $where = [];
            $params = [];

            $scope = strtolower(trim((string)($filters['scope'] ?? 'all')));
            if ($scope === 'umum') {
                $where[] = 'd.id_operasi IS NULL';
            } elseif ($scope === 'operasi') {
                $where[] = 'd.id_operasi IS NOT NULL';
            }

            if (isset($filters['id_operasi']) && (int)$filters['id_operasi'] > 0) {
                $where[] = 'd.id_operasi = :id_ops';
                $params[':id_ops'] = (int)$filters['id_operasi'];
            }

            $mediaType = strtolower(trim((string)($filters['media_type'] ?? '')));
            if (in_array($mediaType, ['image', 'video'], true)) {
                $where[] = 'd.media_type = :mtype';
                $params[':mtype'] = $mediaType;
            }

            $month = trim((string)($filters['month'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}$/', $month)) {
                $where[] = "DATE_FORMAT(d.created_at, '%Y-%m') = :ym";
                $params[':ym'] = $month;
            }

            $q = trim((string)($filters['q'] ?? ''));
            if ($q !== '') {
                $like = '%' . $q . '%';
                $where[] = "(d.judul LIKE :q1 OR d.hari_ke LIKE :q2 OR d.tanggal_label LIKE :q3 OR COALESCE(o.nama_operasi, '') LIKE :q4)";
                $params[':q1'] = $like;
                $params[':q2'] = $like;
                $params[':q3'] = $like;
                $params[':q4'] = $like;
            }

            $sql = "SELECT d.*, o.nama_operasi, o.status AS status_operasi
                    FROM OperasiDokumentasi d
                    LEFT JOIN Operasi o ON o.id_operasi = d.id_operasi";
            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY d.created_at DESC, d.id_dokumentasi DESC';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            error_log('[OperasiDokumentasiModel getAllWithOperasi] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Ringkasan statistik arsip dokumentasi terpadu dalam 1 kueri agregasi.
     *
     * @return array{total:int, umum:int, operasi:int, image:int, video:int}
     */
    public static function getArchiveStats(): array {
        $defaults = ['total' => 0, 'umum' => 0, 'operasi' => 0, 'image' => 0, 'video' => 0];
        try {
            $db = getDB();
            $row = $db->query("SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN id_operasi IS NULL THEN 1 ELSE 0 END) AS umum,
                SUM(CASE WHEN id_operasi IS NOT NULL THEN 1 ELSE 0 END) AS operasi,
                SUM(CASE WHEN media_type = 'image' THEN 1 ELSE 0 END) AS image,
                SUM(CASE WHEN media_type = 'video' THEN 1 ELSE 0 END) AS video
            FROM OperasiDokumentasi")->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return $defaults;
            }
            return [
                'total' => (int)($row['total'] ?? 0),
                'umum' => (int)($row['umum'] ?? 0),
                'operasi' => (int)($row['operasi'] ?? 0),
                'image' => (int)($row['image'] ?? 0),
                'video' => (int)($row['video'] ?? 0),
            ];
        } catch (Throwable) {
            return $defaults;
        }
    }

    /**
     * Daftar bulan pembuatan arsip yang tersedia (format YYYY-MM, terbaru di atas).
     *
     * @return string[]
     */
    public static function getAvailableMonths(): array {
        try {
            $db = getDB();
            $rows = $db->query("SELECT DISTINCT DATE_FORMAT(created_at, '%Y-%m') AS ym
                                FROM OperasiDokumentasi
                                WHERE created_at IS NOT NULL
                                ORDER BY ym DESC")->fetchAll(PDO::FETCH_COLUMN);
            return array_values(array_filter(array_map('strval', $rows ?: [])));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Ambil satu arsip dokumentasi berdasarkan ID beserta info operasi opsional (murni read-only tanpa DDL).
     */
    public static function getById(int $idDokumentasi): ?array {
        if ($idDokumentasi <= 0) {
            return null;
        }
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT d.*, o.nama_operasi, o.status AS status_operasi
                                  FROM OperasiDokumentasi d
                                  LEFT JOIN Operasi o ON o.id_operasi = d.id_operasi
                                  WHERE d.id_dokumentasi = :id
                                  LIMIT 1");
            $stmt->execute([':id' => $idDokumentasi]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Simpan berkas sumber foto/video per-slot ke disk agar arsip dokumentasi dapat dibuka dan diedit ulang.
     * Mendukung Dokumentasi Umum ($idOperasi === null atau 0 -> src_0_...) maupun Operasi SAR ($idOperasi > 0).
     *
     * @param array{tmp_name:string, size:int, name?:string} $fileInfo
     * @return array{path:string, media_type:string, mime_type:string, width_px:int, height_px:int, size:int}
     */
    public static function storeSourceAsset(?int $idOperasi, array $fileInfo): array {
        $normOps = self::normalizeOperasiId($idOperasi);
        if ($normOps !== null) {
            $db = getDB();
            $chkOps = $db->prepare("SELECT id_operasi FROM Operasi WHERE id_operasi = :id LIMIT 1");
            $chkOps->execute([':id' => $normOps]);
            if ($chkOps->fetchColumn() === false) {
                throw new InvalidArgumentException('Operasi SAR tidak ditemukan.');
            }
        }

        $saved = self::validateAndWriteMediaPayload($normOps ?? 0, $fileInfo, 'src');
        return [
            'path' => $saved['rel_path'],
            'media_type' => $saved['media_type'],
            'mime_type' => $saved['mime_type'],
            'width_px' => $saved['width_px'],
            'height_px' => $saved['height_px'],
            'size' => $saved['size'],
        ];
    }

    /**
     * Simpan berkas kolase hasil render (gambar/video) beserta metadata, opsional thumbnail & project_json.
     * $idOperasi === null atau 0 akan disimpan sebagai Dokumentasi Umum (id_operasi = NULL).
     *
     * @param array<string, mixed> $meta
     * @param array{tmp_name:string, size:int, name?:string} $fileInfo
     * @param array{tmp_name:string, size:int, name?:string}|null $thumbFileInfo
     * @return array<string, mixed>
     */
    public static function store(
        ?int $idOperasi,
        array $meta,
        array $fileInfo,
        ?array $thumbFileInfo = null,
        ?string $userId = null
    ): array {
        self::ensureSchemaUpdated();
        $normOps = self::normalizeOperasiId($idOperasi);
        $fileSeg = $normOps ?? 0;

        $slotCount = (int)($meta['slot_count'] ?? 0);
        if ($slotCount < self::MIN_SLOTS || $slotCount > self::MAX_SLOTS) {
            throw new InvalidArgumentException('Jumlah foto/video kolase harus antara ' . self::MIN_SLOTS . ' hingga ' . self::MAX_SLOTS . ' slot.');
        }

        $media = self::validateAndWriteMediaPayload($fileSeg, $fileInfo, 'dok', $meta);
        $thumb = $media['media_type'] === 'video' ? self::storeOptionalThumb($fileSeg, $thumbFileInfo) : ['rel_path' => null, 'abs_path' => null];
        $norm = self::normalizeMeta($meta, $slotCount, $normOps !== null);
        $projectJson = self::sanitizeProjectJson(isset($meta['project_json']) ? (string)$meta['project_json'] : null);

        $db = getDB();
        try {
            $db->beginTransaction();
            if ($normOps !== null) {
                $chkOps = $db->prepare("SELECT id_operasi FROM Operasi WHERE id_operasi = :id LIMIT 1");
                $chkOps->execute([':id' => $normOps]);
                if ($chkOps->fetchColumn() === false) {
                    throw new InvalidArgumentException('Operasi SAR tidak ditemukan.');
                }
            }

            $stmt = $db->prepare("INSERT INTO OperasiDokumentasi (
                id_operasi, judul, hari_ke, tanggal_label, media_type, mime_type,
                aspect_ratio, orientation, slot_count, layout_preset,
                width_px, height_px, file_path, thumb_path, file_size, project_json, created_by
            ) VALUES (
                :id_ops, :judul, :hari_ke, :tgl, :mtype, :mime,
                :ratio, :orient, :slots, :preset,
                :w, :h, :fpath, :tpath, :fsize, :pjson, :uid
            )");
            $stmt->execute([
                ':id_ops' => $normOps,
                ':judul' => $norm['judul'],
                ':hari_ke' => $norm['hari_ke'],
                ':tgl' => $norm['tanggal_label'],
                ':mtype' => $media['media_type'],
                ':mime' => $media['mime_type'],
                ':ratio' => $norm['aspect_ratio'],
                ':orient' => $norm['orientation'],
                ':slots' => $slotCount,
                ':preset' => $norm['layout_preset'],
                ':w' => $media['width_px'] > 0 ? $media['width_px'] : 1080,
                ':h' => $media['height_px'] > 0 ? $media['height_px'] : 1620,
                ':fpath' => $media['rel_path'],
                ':tpath' => $thumb['rel_path'],
                ':fsize' => $media['size'],
                ':pjson' => $projectJson,
                ':uid' => $userId,
            ]);
            $newId = (int)$db->lastInsertId();
            $db->commit();

            if (class_exists('AuditHelper')) {
                $scopeLabel = $normOps !== null ? "Operasi #{$normOps}" : 'Dokumentasi Umum';
                AuditHelper::log('DOKUMENTASI_CREATE', "Menyimpan dokumentasi kolase [{$scopeLabel}] ({$media['media_type']}, {$slotCount} slot): {$norm['judul']}", 'OperasiDokumentasi', (string)$newId);
            }

            return self::getById($newId) ?: [
                'id_dokumentasi' => $newId,
                'id_operasi' => $normOps,
                'judul' => $norm['judul'],
                'media_type' => $media['media_type'],
                'file_path' => $media['rel_path'],
                'thumb_path' => $thumb['rel_path'],
                'project_json' => $projectJson,
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            @unlink($media['abs_path']);
            if ($thumb['abs_path'] !== null) {
                @unlink($thumb['abs_path']);
            }
            throw $e;
        }
    }

    /**
     * Perbarui arsip dokumentasi yang sudah tersimpan secara in-place (mengganti hasil render, metadata,
     * kaitan id_operasi opsional, dan project_json, serta membersihkan berkas render lama & aset sumber yatim pasca-commit).
     *
     * @param array<string, mixed> $meta
     * @param array{tmp_name:string, size:int, name?:string} $fileInfo
     * @param array{tmp_name:string, size:int, name?:string}|null $thumbFileInfo
     * @return array<string, mixed>
     */
    public static function update(
        int $idDokumentasi,
        ?int $idOperasi,
        array $meta,
        array $fileInfo,
        ?array $thumbFileInfo = null,
        ?string $userId = null,
        ?int $expectedOperasiId = null
    ): array {
        self::ensureSchemaUpdated();

        if ($idDokumentasi <= 0) {
            throw new InvalidArgumentException('ID dokumentasi tidak valid.');
        }

        $normOps = self::normalizeOperasiId($idOperasi);
        $fileSeg = $normOps ?? 0;

        $existing = self::getById($idDokumentasi);
        if (!$existing) {
            throw new InvalidArgumentException('Arsip dokumentasi tidak ditemukan.');
        }
        $existingOps = $existing['id_operasi'] !== null ? (int)$existing['id_operasi'] : null;
        if ($expectedOperasiId !== null && $existingOps !== $expectedOperasiId) {
            throw new InvalidArgumentException('Arsip dokumentasi tidak ditemukan pada operasi ini.');
        }

        $slotCount = (int)($meta['slot_count'] ?? 0);
        if ($slotCount < self::MIN_SLOTS || $slotCount > self::MAX_SLOTS) {
            throw new InvalidArgumentException('Jumlah foto/video kolase harus antara ' . self::MIN_SLOTS . ' hingga ' . self::MAX_SLOTS . ' slot.');
        }

        $media = self::validateAndWriteMediaPayload($fileSeg, $fileInfo, 'dok', $meta);
        $thumb = $media['media_type'] === 'video' ? self::storeOptionalThumb($fileSeg, $thumbFileInfo) : ['rel_path' => null, 'abs_path' => null];
        $norm = self::normalizeMeta($meta, $slotCount, $normOps !== null);
        $projectJson = self::sanitizeProjectJson(isset($meta['project_json']) ? (string)$meta['project_json'] : null);

        $oldFilePath = (string)($existing['file_path'] ?? '');
        $oldThumbPath = (string)($existing['thumb_path'] ?? '');
        $oldSourcePaths = self::extractSourcePaths(isset($existing['project_json']) ? (string)$existing['project_json'] : null);
        $newSourcePaths = self::extractSourcePaths($projectJson);

        $db = getDB();
        try {
            $db->beginTransaction();
            if ($normOps !== null) {
                $chkOps = $db->prepare("SELECT id_operasi FROM Operasi WHERE id_operasi = :id LIMIT 1");
                $chkOps->execute([':id' => $normOps]);
                if ($chkOps->fetchColumn() === false) {
                    throw new InvalidArgumentException('Operasi SAR tidak ditemukan.');
                }
            }

            $stmt = $db->prepare("UPDATE OperasiDokumentasi SET
                id_operasi = :id_ops,
                judul = :judul,
                hari_ke = :hari_ke,
                tanggal_label = :tgl,
                media_type = :mtype,
                mime_type = :mime,
                aspect_ratio = :ratio,
                orientation = :orient,
                slot_count = :slots,
                layout_preset = :preset,
                width_px = :w,
                height_px = :h,
                file_path = :fpath,
                thumb_path = :tpath,
                file_size = :fsize,
                project_json = :pjson
            WHERE id_dokumentasi = :id");
            $stmt->execute([
                ':id_ops' => $normOps,
                ':judul' => $norm['judul'],
                ':hari_ke' => $norm['hari_ke'],
                ':tgl' => $norm['tanggal_label'],
                ':mtype' => $media['media_type'],
                ':mime' => $media['mime_type'],
                ':ratio' => $norm['aspect_ratio'],
                ':orient' => $norm['orientation'],
                ':slots' => $slotCount,
                ':preset' => $norm['layout_preset'],
                ':w' => $media['width_px'] > 0 ? $media['width_px'] : 1080,
                ':h' => $media['height_px'] > 0 ? $media['height_px'] : 1620,
                ':fpath' => $media['rel_path'],
                ':tpath' => $thumb['rel_path'],
                ':fsize' => $media['size'],
                ':pjson' => $projectJson,
                ':id' => $idDokumentasi,
            ]);
            $db->commit();

            // Pasca-commit: bersihkan berkas render lama & aset sumber per-slot yang tidak lagi dipakai arsip mana pun
            $toDelete = [];
            if ($oldFilePath !== '' && $oldFilePath !== $media['rel_path']) {
                $toDelete[] = $oldFilePath;
            }
            if ($oldThumbPath !== '' && $oldThumbPath !== (string)($thumb['rel_path'] ?? '')) {
                $toDelete[] = $oldThumbPath;
            }
            $droppedSources = array_values(array_diff($oldSourcePaths, $newSourcePaths));
            if (!empty($droppedSources)) {
                $toDelete = array_merge($toDelete, self::filterUnreferencedSourcePaths($droppedSources, $idDokumentasi));
            }
            if (!empty($toDelete)) {
                self::deleteFiles($toDelete);
            }

            if (class_exists('AuditHelper')) {
                $scopeLabel = $normOps !== null ? "Operasi #{$normOps}" : 'Dokumentasi Umum';
                AuditHelper::log('DOKUMENTASI_UPDATE', "Memperbarui dokumentasi kolase [{$scopeLabel}] ({$media['media_type']}, {$slotCount} slot): {$norm['judul']}", 'OperasiDokumentasi', (string)$idDokumentasi);
            }

            return self::getById($idDokumentasi) ?: $existing;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            @unlink($media['abs_path']);
            if ($thumb['abs_path'] !== null) {
                @unlink($thumb['abs_path']);
            }
            throw $e;
        }
    }

    /**
     * Hapus satu arsip dokumentasi beserta berkas render, thumbnail, dan aset sumber per-slot yang tidak dipakai arsip lain.
     */
    public static function delete(int $idDokumentasi, ?int $expectedOperasiId = null): bool {
        if ($idDokumentasi <= 0) {
            return false;
        }
        self::ensureSchemaUpdated();
        $row = self::getById($idDokumentasi);
        if (!$row) {
            return false;
        }
        $rowOps = $row['id_operasi'] !== null ? (int)$row['id_operasi'] : null;
        if ($expectedOperasiId !== null && $rowOps !== $expectedOperasiId) {
            return false;
        }

        $sourcePaths = self::extractSourcePaths(isset($row['project_json']) ? (string)$row['project_json'] : null);

        $db = getDB();
        $stmt = $db->prepare("DELETE FROM OperasiDokumentasi WHERE id_dokumentasi = :id");
        $ok = $stmt->execute([':id' => $idDokumentasi]);
        if ($ok) {
            $paths = array_filter([(string)($row['file_path'] ?? ''), (string)($row['thumb_path'] ?? '')]);
            if (!empty($sourcePaths)) {
                $paths = array_merge($paths, self::filterUnreferencedSourcePaths($sourcePaths, $idDokumentasi));
            }
            self::deleteFiles($paths);
            if (class_exists('AuditHelper')) {
                AuditHelper::log('DOKUMENTASI_DELETE', 'Menghapus arsip dokumentasi kolase: ' . ($row['judul'] ?? "#{$idDokumentasi}"), 'OperasiDokumentasi', (string)$idDokumentasi);
            }
        }
        return $ok;
    }

    /**
     * Kumpulkan semua path fisik milik suatu operasi (hasil render, thumbnail, dan aset sumber per-slot yang tidak dipakai di luar operasi ini)
     * untuk dibersihkan pasca-commit OperasiModel::delete(). Dokumentasi Umum (id_operasi IS NULL) tidak pernah tersentuh.
     *
     * @return string[]
     */
    public static function collectFilePathsForOperasi(int $idOperasi): array {
        if ($idOperasi <= 0) {
            return [];
        }
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT file_path, thumb_path, project_json FROM OperasiDokumentasi WHERE id_operasi = :id");
            $stmt->execute([':id' => $idOperasi]);
            $paths = [];
            $candidateSources = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (!empty($r['file_path'])) {
                    $paths[] = (string)$r['file_path'];
                }
                if (!empty($r['thumb_path'])) {
                    $paths[] = (string)$r['thumb_path'];
                }
                if (!empty($r['project_json'])) {
                    $candidateSources = array_merge($candidateSources, self::extractSourcePaths((string)$r['project_json']));
                }
            }
            if (!empty($candidateSources)) {
                $stmtOther = $db->prepare("SELECT project_json FROM OperasiDokumentasi WHERE (id_operasi IS NULL OR id_operasi <> :id) AND project_json IS NOT NULL");
                $stmtOther->execute([':id' => $idOperasi]);
                $usedOutside = [];
                foreach ($stmtOther->fetchAll(PDO::FETCH_COLUMN) as $pj) {
                    foreach (self::extractSourcePaths((string)$pj) as $sp) {
                        $usedOutside[$sp] = true;
                    }
                }
                foreach ($candidateSources as $cs) {
                    if (!isset($usedOutside[$cs])) {
                        $paths[] = $cs;
                    }
                }
            }
            return array_values(array_unique($paths));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Parse & format project_json dari baris arsip untuk kebutuhan frontend Studio (dilengkapi URL publik).
     *
     * @return array<string, mixed>|null
     */
    public static function decodeProjectForClient(?string $projectJson, string $baseUrl = ''): ?array {
        if ($projectJson === null || trim($projectJson) === '') {
            return null;
        }
        $decoded = json_decode($projectJson, true);
        if (!is_array($decoded)) {
            return null;
        }
        $prefix = rtrim($baseUrl, '/');
        if (isset($decoded['slots']) && is_array($decoded['slots'])) {
            foreach ($decoded['slots'] as $idx => $slot) {
                if (!is_array($slot) || empty($slot['path'])) {
                    continue;
                }
                $rel = ltrim(str_replace('\\', '/', (string)$slot['path']), '/');
                if (!str_starts_with($rel, self::REL_DIR . '/') || !is_file(ROOT_DIR . '/' . $rel)) {
                    $decoded['slots'][$idx] = null;
                    continue;
                }
                $decoded['slots'][$idx]['path'] = $rel;
                $decoded['slots'][$idx]['url'] = ($prefix !== '' ? $prefix . '/' : '/') . $rel;
            }
        }
        return $decoded;
    }

    /**
     * Hapus daftar berkas fisik secara aman (terbatas di dalam uploads/operasi_dokumentasi/).
     *
     * @param string[] $relPaths
     */
    public static function deleteFiles(array $relPaths): void {
        $baseDir = ROOT_DIR . '/' . self::REL_DIR;
        foreach (array_unique($relPaths) as $rel) {
            $clean = ltrim(str_replace('\\', '/', trim((string)$rel)), '/');
            if ($clean === '' || !str_starts_with($clean, self::REL_DIR . '/')) {
                continue;
            }
            $base = basename($clean);
            if ($base === '' || $base === 'index.php' || str_contains($base, '..')) {
                continue;
            }
            $full = $baseDir . '/' . $base;
            if (is_file($full)) {
                @unlink($full);
            }
        }
    }

    /**
     * Validasi & tulis berkas gambar/video terverifikasi MIME + struktur biner ke direktori unggahan.
     *
     * @param array{tmp_name:string, size:int, name?:string} $fileInfo
     * @param array<string, mixed> $meta
     * @return array{media_type:string, mime_type:string, ext:string, width_px:int, height_px:int, rel_path:string, abs_path:string, size:int}
     */
    private static function validateAndWriteMediaPayload(int $fileSeg, array $fileInfo, string $prefix, array $meta = []): array {
        $tmpPath = (string)($fileInfo['tmp_name'] ?? '');
        $size = (int)($fileInfo['size'] ?? 0);
        if ($tmpPath === '' || !is_file($tmpPath) || $size <= 0) {
            throw new InvalidArgumentException('Berkas media tidak ditemukan atau kosong.');
        }
        if ($size > self::MAX_VIDEO_BYTES) {
            throw new InvalidArgumentException('Ukuran berkas melebihi batas maksimal 20 MB.');
        }

        $rawBytes = @file_get_contents($tmpPath, false, null, 0, self::MAX_VIDEO_BYTES + 1);
        if ($rawBytes === false || $rawBytes === '' || strlen($rawBytes) > self::MAX_VIDEO_BYTES) {
            throw new InvalidArgumentException('Berkas media tidak dapat dibaca atau melebihi batas ukuran.');
        }

        $actualSize = strlen($rawBytes);
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string)$finfo->buffer($rawBytes));

        $mediaType = '';
        $ext = '';
        $finalMime = '';
        $widthPx = max(0, min(65535, (int)($meta['width_px'] ?? 0)));
        $heightPx = max(0, min(65535, (int)($meta['height_px'] ?? 0)));

        if (isset(self::ALLOWED_IMAGE_MIME[$detectedMime])) {
            if ($actualSize > self::MAX_IMAGE_BYTES) {
                throw new InvalidArgumentException('Ukuran berkas gambar maksimal 20 MB.');
            }
            $imgInfo = @getimagesizefromstring($rawBytes);
            if ($imgInfo === false || $imgInfo[0] < 1 || $imgInfo[1] < 1) {
                throw new InvalidArgumentException('Berkas gambar tidak valid atau rusak.');
            }
            if ((int)$imgInfo[0] * (int)$imgInfo[1] > self::MAX_PIXELS) {
                throw new InvalidArgumentException('Resolusi gambar terlalu besar (maksimal 40 megapiksel).');
            }
            $mediaType = 'image';
            $finalMime = $detectedMime;
            $ext = self::ALLOWED_IMAGE_MIME[$detectedMime];
            $widthPx = min(65535, (int)$imgInfo[0]);
            $heightPx = min(65535, (int)$imgInfo[1]);
        } elseif (self::isValidVideoPayload($rawBytes, $detectedMime, $finalMime, $ext)) {
            $mediaType = 'video';
        } else {
            throw new InvalidArgumentException('Format berkas tidak diizinkan. Gunakan gambar (JPG, PNG, WebP) atau video (WebM, MP4) yang valid.');
        }

        $dir = self::ensureUploadDir();
        $fileName = $prefix . '_' . max(0, $fileSeg) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $relPath = self::REL_DIR . '/' . $fileName;
        $absPath = ROOT_DIR . '/' . $relPath;

        if (!is_dir($dir) || @file_put_contents($absPath, $rawBytes) === false) {
            @unlink($absPath);
            throw new RuntimeException('Gagal menyimpan berkas media ke penyimpanan server.');
        }

        return [
            'media_type' => $mediaType,
            'mime_type' => $finalMime,
            'ext' => $ext,
            'width_px' => $widthPx,
            'height_px' => $heightPx,
            'rel_path' => $relPath,
            'abs_path' => $absPath,
            'size' => $actualSize,
        ];
    }

    /**
     * @param array{tmp_name:string, size:int, name?:string}|null $thumbFileInfo
     * @return array{rel_path:?string, abs_path:?string}
     */
    private static function storeOptionalThumb(int $fileSeg, ?array $thumbFileInfo): array {
        $result = ['rel_path' => null, 'abs_path' => null];
        if (!is_array($thumbFileInfo) || empty($thumbFileInfo['tmp_name']) || !is_file((string)$thumbFileInfo['tmp_name'])) {
            return $result;
        }
        $tSize = (int)($thumbFileInfo['size'] ?? 0);
        if ($tSize <= 0 || $tSize > self::MAX_THUMB_BYTES) {
            return $result;
        }
        $tBytes = @file_get_contents((string)$thumbFileInfo['tmp_name'], false, null, 0, self::MAX_THUMB_BYTES + 1);
        if ($tBytes === false || strlen($tBytes) > self::MAX_THUMB_BYTES) {
            return $result;
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tMime = strtolower((string)$finfo->buffer($tBytes));
        $tInfo = isset(self::ALLOWED_IMAGE_MIME[$tMime]) ? @getimagesizefromstring($tBytes) : false;
        if ($tInfo === false || $tInfo[0] <= 0 || $tInfo[1] <= 0 || ($tInfo[0] * $tInfo[1] > self::MAX_PIXELS)) {
            return $result;
        }
        $tExt = self::ALLOWED_IMAGE_MIME[$tMime];
        $rel = self::REL_DIR . '/thumb_' . max(0, $fileSeg) . '_' . bin2hex(random_bytes(8)) . '.' . $tExt;
        $abs = ROOT_DIR . '/' . $rel;
        if (@file_put_contents($abs, $tBytes) === false) {
            @unlink($abs);
            return $result;
        }
        return ['rel_path' => $rel, 'abs_path' => $abs];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{judul:string, hari_ke:string, tanggal_label:string, aspect_ratio:string, orientation:string, layout_preset:string}
     */
    private static function normalizeMeta(array $meta, int $slotCount, bool $hasOperasi = true): array {
        $judul = trim((string)($meta['judul'] ?? ''));
        if ($judul === '') {
            $judul = $hasOperasi ? 'Dokumentasi Operasi SAR' : 'Dokumentasi Kegiatan KPP Kelas A Kendari';
        }
        $defaultHari = $hasOperasi ? 'H1' : 'KEGIATAN';
        $orientationRaw = strtolower(trim((string)($meta['orientation'] ?? 'vertical')));
        return [
            'judul' => mb_substr($judul, 0, 255),
            'hari_ke' => mb_substr(trim((string)($meta['hari_ke'] ?? $defaultHari)) ?: $defaultHari, 0, 32),
            'tanggal_label' => mb_substr(trim((string)($meta['tanggal_label'] ?? '')), 0, 100),
            'aspect_ratio' => mb_substr(trim((string)($meta['aspect_ratio'] ?? '2:3')) ?: '2:3', 0, 24),
            'orientation' => in_array($orientationRaw, ['vertical', 'horizontal', 'square'], true) ? $orientationRaw : 'vertical',
            'layout_preset' => mb_substr(trim((string)($meta['layout_preset'] ?? 'v_' . $slotCount . '_default')) ?: 'v_6_2x3', 0, 64),
        ];
    }

    /**
     * Sanitasi payload JSON proyek kolase agar aman dan hanya mereferensikan berkas sumber src_* valid di uploads/operasi_dokumentasi/.
     */
    private static function sanitizeProjectJson(?string $rawJson): ?string {
        if ($rawJson === null) {
            return null;
        }
        $trimmed = trim($rawJson);
        if ($trimmed === '' || strlen($trimmed) > self::MAX_PROJECT_JSON_BYTES) {
            return null;
        }
        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return null;
        }

        $safeSlots = [];
        $pathRegex = '#^' . preg_quote(self::REL_DIR, '#') . '/src_\d+_[A-Za-z0-9_\-]+\.(?:jpg|jpeg|png|webp|webm|mp4)\z#i';
        if (isset($decoded['slots']) && is_array($decoded['slots'])) {
            foreach (array_slice($decoded['slots'], 0, self::MAX_SLOTS) as $slot) {
                if (!is_array($slot) || empty($slot['path'])) {
                    $safeSlots[] = null;
                    continue;
                }
                $rel = ltrim(str_replace('\\', '/', trim((string)$slot['path'])), '/');
                if (!preg_match($pathRegex, $rel) || !is_file(ROOT_DIR . '/' . $rel)) {
                    $safeSlots[] = null;
                    continue;
                }
                $type = (($slot['type'] ?? 'image') === 'video') ? 'video' : 'image';
                $safeSlots[] = [
                    'type' => $type,
                    'path' => $rel,
                    'name' => mb_substr(trim((string)($slot['name'] ?? '')), 0, 120),
                    'zoom' => max(1.0, min(3.0, round((float)($slot['zoom'] ?? 1.0), 3))),
                    'offsetX' => max(-100, min(100, (int)round((float)($slot['offsetX'] ?? 0)))),
                    'offsetY' => max(-100, min(100, (int)round((float)($slot['offsetY'] ?? 0)))),
                    'muted' => !isset($slot['muted']) || (bool)$slot['muted'],
                    'videoTime' => max(0.0, round((float)($slot['videoTime'] ?? 0.0), 2)),
                ];
            }
        }

        $cleanStr = static function ($v, int $max = 255): string {
            return mb_substr(trim((string)$v), 0, $max);
        };

        $clean = [
            'version' => 1,
            'styleId' => in_array((string)($decoded['styleId'] ?? ''), ['klasik_kpp', 'editorial_modern'], true) ? (string)$decoded['styleId'] : 'klasik_kpp',
            'orientation' => in_array((string)($decoded['orientation'] ?? ''), ['vertical', 'horizontal', 'square'], true) ? (string)$decoded['orientation'] : 'vertical',
            'canvasPresetId' => $cleanStr($decoded['canvasPresetId'] ?? 'portrait_2_3', 48),
            'qualityId' => in_array((string)($decoded['qualityId'] ?? ''), ['fhd', 'qhd', 'uhd_4k'], true) ? (string)$decoded['qualityId'] : 'qhd',
            'imageFormat' => (($decoded['imageFormat'] ?? '') === 'image/png') ? 'image/png' : 'image/jpeg',
            'slotCount' => max(self::MIN_SLOTS, min(self::MAX_SLOTS, (int)($decoded['slotCount'] ?? 6))),
            'layoutId' => $cleanStr($decoded['layoutId'] ?? 'v_6_2x3', 64),
            'videoDuration' => $cleanStr($decoded['videoDuration'] ?? 'auto', 16),
            'logos' => [
                'logo1' => !isset($decoded['logos']['logo1']) || (bool)$decoded['logos']['logo1'],
                'logo2' => !isset($decoded['logos']['logo2']) || (bool)$decoded['logos']['logo2'],
                'logo3' => !isset($decoded['logos']['logo3']) || (bool)$decoded['logos']['logo3'],
            ],
            'header' => [
                'nama' => $cleanStr($decoded['header']['nama'] ?? '', 180),
                'alamat' => $cleanStr($decoded['header']['alamat'] ?? '', 240),
                'telp' => $cleanStr($decoded['header']['telp'] ?? '', 80),
                'wa' => $cleanStr($decoded['header']['wa'] ?? '', 64),
                'email' => $cleanStr($decoded['header']['email'] ?? '', 120),
            ],
            'footer' => [
                'web' => $cleanStr($decoded['footer']['web'] ?? '', 120),
                'ig' => $cleanStr($decoded['footer']['ig'] ?? '', 80),
                'fb' => $cleanStr($decoded['footer']['fb'] ?? '', 80),
                'x' => $cleanStr($decoded['footer']['x'] ?? '', 80),
            ],
            'slots' => $safeSlots,
        ];

        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return ($encoded !== false && strlen($encoded) <= self::MAX_PROJECT_JSON_BYTES) ? $encoded : null;
    }

    /**
     * Ekstrak seluruh path aset sumber per-slot (src_*) yang direferensikan oleh project_json.
     *
     * @return string[]
     */
    private static function extractSourcePaths(?string $projectJson): array {
        if ($projectJson === null || trim($projectJson) === '') {
            return [];
        }
        $decoded = json_decode($projectJson, true);
        if (!is_array($decoded) || !isset($decoded['slots']) || !is_array($decoded['slots'])) {
            return [];
        }
        $paths = [];
        foreach ($decoded['slots'] as $slot) {
            if (!is_array($slot) || empty($slot['path'])) {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', trim((string)$slot['path'])), '/');
            if (str_starts_with($rel, self::REL_DIR . '/src_')) {
                $paths[] = $rel;
            }
        }
        return array_values(array_unique($paths));
    }

    /**
     * Saring daftar aset sumber agar hanya berkas yang benar-benar tidak lagi dipakai oleh arsip mana pun yang dihapus.
     *
     * @param string[] $candidatePaths
     * @return string[]
     */
    private static function filterUnreferencedSourcePaths(array $candidatePaths, ?int $excludeDocId = null): array {
        if (empty($candidatePaths)) {
            return [];
        }
        try {
            $db = getDB();
            if ($excludeDocId !== null) {
                $stmt = $db->prepare("SELECT project_json FROM OperasiDokumentasi WHERE id_dokumentasi <> :ex AND project_json IS NOT NULL");
                $stmt->execute([':ex' => $excludeDocId]);
            } else {
                $stmt = $db->query("SELECT project_json FROM OperasiDokumentasi WHERE project_json IS NOT NULL");
            }
            $stillUsed = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $pj) {
                foreach (self::extractSourcePaths((string)$pj) as $p) {
                    $stillUsed[$p] = true;
                }
            }
            return array_values(array_filter($candidatePaths, static fn (string $p): bool => !isset($stillUsed[$p])));
        } catch (Throwable) {
            return $candidatePaths;
        }
    }

    /**
     * Validasi ketat container video WebM (EBML header + doctype webm) atau MP4 (ISO BMFF ftyp box).
     */
    private static function isValidVideoPayload(string $bytes, string $detectedMime, string &$outMime, string &$outExt): bool {
        if (strlen($bytes) < 16) {
            return false;
        }
        // 1. WebM (EBML magic bytes 0x1A45DFA3)
        if (str_starts_with($bytes, "\x1A\x45\xDF\xA3")) {
            $head = substr($bytes, 0, 128);
            if (
                in_array($detectedMime, ['video/webm', 'video/x-matroska', 'application/octet-stream'], true)
                && (stripos($head, 'webm') !== false || $detectedMime === 'video/webm')
            ) {
                $outMime = 'video/webm';
                $outExt = 'webm';
                return true;
            }
        }
        // 2. MP4 / ISO BMFF ('ftyp' at byte offset 4)
        if (substr($bytes, 4, 4) === 'ftyp') {
            if (in_array($detectedMime, ['video/mp4', 'video/quicktime', 'application/octet-stream'], true)) {
                $outMime = 'video/mp4';
                $outExt = 'mp4';
                return true;
            }
        }
        return false;
    }
}
