<?php
/**
 * QR-SAR - Dokumen Peta Snapshot Model
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Mengelola penyimpanan, pengambilan, dan penghapusan cuplikan peta 16:9 untuk Dokumen Rencana Operasi SAR:
 * - RENOPS         : Peta Lokasi (LKP), 1x per operasi (id_referensi NULL), dipakai semua hari RENOPS.
 * - RENOPS_RENCANA : Peta Rencana Operasi, per hari RENOPS (id_referensi = id_renops).
 */

declare(strict_types=1);

class DokumenPetaSnapshotModel {
    private static bool $schemaChecked = false;

    public const ALLOWED_TYPES = ['RENOPS', 'RENOPS_RENCANA'];

    /**
     * Memastikan tabel DokumenPetaSnapshot tersedia di database.
     */
    public static function ensureSchemaUpdated(bool $force = false): void {
        if (!$force) {
            return;
        }
        if (self::$schemaChecked) {
            return;
        }
        self::$schemaChecked = true;
        try {
            $db = getDB();
            if ($db->inTransaction()) {
                return;
            }
            $db->exec("CREATE TABLE IF NOT EXISTS `DokumenPetaSnapshot` (
                `id_snapshot` INT AUTO_INCREMENT PRIMARY KEY,
                `id_operasi` INT NOT NULL,
                `jenis_dokumen` VARCHAR(30) NOT NULL DEFAULT 'RENOPS',
                `id_referensi` INT NULL DEFAULT NULL,
                `image_path` VARCHAR(255) NOT NULL,
                `bounds_json` TEXT NULL,
                `komponen_json` LONGTEXT NULL,
                `keterangan_tambahan` TEXT NULL,
                `captured_by` INT NULL DEFAULT NULL,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX `idx_ops_dok_ref` (`id_operasi`, `jenis_dokumen`, `id_referensi`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (Throwable $e) {
            error_log("[DokumenPetaSnapshotModel Schema Check] " . $e->getMessage());
        }
    }

    /**
     * Memastikan direktori penyimpanan uploads/map_snapshots siap dan aman.
     */
    public static function ensureUploadDir(): string {
        $dir = (defined('UPLOADS_DIR') ? UPLOADS_DIR : dirname(__DIR__) . '/uploads') . '/map_snapshots';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $htaccess = $dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|sh|cgi|pl)$\">\n    Require all denied\n</FilesMatch>\n");
        }
        return $dir;
    }

    /**
     * Normalisasi jenis dokumen; null bila bukan jenis yang diizinkan.
     */
    public static function normalizeJenisDokumen(string $jenis): ?string {
        $upper = strtoupper(trim($jenis));
        return in_array($upper, self::ALLOWED_TYPES, true) ? $upper : null;
    }

    /**
     * Menyimpan atau memperbarui snapshot peta untuk dokumen tertentu.
     */
    public static function saveSnapshot(array $data): array {
        self::ensureSchemaUpdated();
        $db = getDB();

        $idOperasi = (int)($data['id_operasi'] ?? 0);
        if ($idOperasi <= 0) {
            throw new InvalidArgumentException('ID Operasi tidak valid untuk menyimpan cuplikan peta.');
        }

        $jenisDokumen = self::normalizeJenisDokumen((string)($data['jenis_dokumen'] ?? ''));
        if ($jenisDokumen === null) {
            throw new InvalidArgumentException('Sasaran capture peta tidak dikenal (hanya Peta Lokasi LKP atau Peta Rencana Operasi RENOPS).');
        }
        // Peta LKP: 1x per operasi. Peta Rencana Operasi: wajib hari RENOPS.
        $idReferensi = null;
        if ($jenisDokumen === 'RENOPS_RENCANA') {
            $idReferensi = (int)($data['id_referensi'] ?? 0);
            if ($idReferensi <= 0) {
                throw new InvalidArgumentException('Pilih hari RENOPS untuk Peta Rencana Operasi.');
            }
        }

        // Proses data gambar base64 (maks 5 MB setelah decode)
        $rawImage = trim((string)($data['image_data'] ?? $data['image_base64'] ?? ''));
        if ($rawImage === '') {
            throw new InvalidArgumentException('Data gambar cuplikan peta (image_data) tidak boleh kosong.');
        }

        if (preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $rawImage)) {
            $rawImage = substr($rawImage, strpos($rawImage, ',') + 1);
        } elseif (str_starts_with(strtolower($rawImage), 'data:')) {
            throw new InvalidArgumentException('Format gambar tidak didukung (gunakan PNG, JPEG, atau WebP).');
        }

        $maxBytes = 5 * 1024 * 1024;
        if (strlen($rawImage) > (int)ceil($maxBytes * 1.4)) {
            throw new InvalidArgumentException('Ukuran gambar cuplikan peta melebihi batas maksimal 5 MB.');
        }

        $binaryImage = base64_decode(str_replace(' ', '+', $rawImage), true);
        if ($binaryImage === false || strlen($binaryImage) < 64) {
            throw new InvalidArgumentException('Format base64 gambar cuplikan peta tidak valid atau rusak.');
        }
        if (strlen($binaryImage) > $maxBytes) {
            throw new InvalidArgumentException('Ukuran gambar cuplikan peta melebihi batas maksimal 5 MB.');
        }

        $imgInfo = @getimagesizefromstring($binaryImage);
        $mime = is_array($imgInfo) ? strtolower((string)($imgInfo['mime'] ?? '')) : '';
        $mimeExtMap = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!is_array($imgInfo) || ($imgInfo[0] ?? 0) <= 0 || ($imgInfo[1] ?? 0) <= 0 || !isset($mimeExtMap[$mime])) {
            throw new InvalidArgumentException('Berkas cuplikan peta bukan citra PNG/JPEG/WebP yang valid.');
        }
        $ext = $mimeExtMap[$mime];

        $uploadDir = self::ensureUploadDir();
        $refSuffix = $idReferensi !== null ? "_ref{$idReferensi}" : '';
        $fileName = sprintf(
            'snap_op%d_%s%s_%d_%s.%s',
            $idOperasi,
            strtolower($jenisDokumen),
            $refSuffix,
            time(),
             bin2hex(random_bytes(3)),
            $ext
        );
        $fullPath = $uploadDir . '/' . $fileName;
        if (file_put_contents($fullPath, $binaryImage) === false) {
            throw new RuntimeException('Gagal menulis berkas gambar cuplikan peta ke direktori uploads/map_snapshots.');
        }

        $relativePath = 'uploads/map_snapshots/' . $fileName;

        // Siapkan JSON bounds & komponen
        $bounds = $data['bounds'] ?? $data['bounds_json'] ?? null;
        $boundsJson = is_string($bounds) ? $bounds : json_encode($bounds ?: [], JSON_UNESCAPED_UNICODE);

        $komponen = $data['komponen'] ?? $data['komponen_json'] ?? [];
        $komponenJson = is_string($komponen) ? $komponen : json_encode($komponen ?: [], JSON_UNESCAPED_UNICODE);

        $keterangan = isset($data['keterangan_tambahan']) ? trim((string)$data['keterangan_tambahan']) : null;
        $capturedBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : (isset($data['captured_by']) ? (int)$data['captured_by'] : null);

        // Cek apakah sudah ada snapshot sebelumnya untuk kombinasi (id_operasi, jenis_dokumen, id_referensi)
        if ($idReferensi !== null) {
            $stmtExist = $db->prepare("SELECT * FROM DokumenPetaSnapshot WHERE id_operasi = :op AND jenis_dokumen = :jenis AND id_referensi = :ref LIMIT 1");
            $stmtExist->execute([':op' => $idOperasi, ':jenis' => $jenisDokumen, ':ref' => $idReferensi]);
        } else {
            $stmtExist = $db->prepare("SELECT * FROM DokumenPetaSnapshot WHERE id_operasi = :op AND jenis_dokumen = :jenis AND id_referensi IS NULL LIMIT 1");
            $stmtExist->execute([':op' => $idOperasi, ':jenis' => $jenisDokumen]);
        }
        $existing = $stmtExist->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Hapus file gambar lama agar hemat ruang disk
            self::removeImageFile((string)($existing['image_path'] ?? ''));

            $stmtUpd = $db->prepare("UPDATE DokumenPetaSnapshot SET
                image_path = :img,
                bounds_json = :bounds,
                komponen_json = :komponen,
                keterangan_tambahan = :ket,
                captured_by = :by,
                updated_at = NOW()
                WHERE id_snapshot = :id");
            $stmtUpd->execute([
                ':img' => $relativePath,
                ':bounds' => $boundsJson,
                ':komponen' => $komponenJson,
                ':ket' => $keterangan,
                ':by' => $capturedBy,
                ':id' => (int)$existing['id_snapshot']
            ]);
            $idSnapshot = (int)$existing['id_snapshot'];
        } else {
            $stmtIns = $db->prepare("INSERT INTO DokumenPetaSnapshot
                (id_operasi, jenis_dokumen, id_referensi, image_path, bounds_json, komponen_json, keterangan_tambahan, captured_by, created_at, updated_at)
                VALUES (:op, :jenis, :ref, :img, :bounds, :komponen, :ket, :by, NOW(), NOW())");
            $stmtIns->execute([
                ':op' => $idOperasi,
                ':jenis' => $jenisDokumen,
                ':ref' => $idReferensi,
                ':img' => $relativePath,
                ':bounds' => $boundsJson,
                ':komponen' => $komponenJson,
                ':ket' => $keterangan,
                ':by' => $capturedBy
            ]);
            $idSnapshot = (int)$db->lastInsertId();
        }

        return self::getById($idSnapshot) ?? [];
    }

    /**
     * Mengambil snapshot berdasarkan ID primary key.
     */
    public static function getById(int $idSnapshot): ?array {
        self::ensureSchemaUpdated();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM DokumenPetaSnapshot WHERE id_snapshot = :id LIMIT 1");
        $stmt->execute([':id' => $idSnapshot]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::hydrateRow($row) : null;
    }

    /**
     * Snapshot dokumen RENOPS: Peta LKP operasi (RENOPS) atau Peta Rencana Operasi hari tertentu (RENOPS_RENCANA).
     */
    public static function getSnapshot(int $idOperasi, string $jenisDokumen, ?int $idReferensi = null): ?array {
        $jenis = self::normalizeJenisDokumen($jenisDokumen);
        if ($idOperasi <= 0 || $jenis === null || ($jenis === 'RENOPS_RENCANA' && (int)$idReferensi <= 0)) {
            return null;
        }
        self::ensureSchemaUpdated();
        $sql = "SELECT * FROM DokumenPetaSnapshot WHERE id_operasi = :op AND jenis_dokumen = :jenis AND "
            . ($jenis === 'RENOPS' ? 'id_referensi IS NULL' : 'id_referensi = :ref')
            . ' ORDER BY updated_at DESC, id_snapshot DESC LIMIT 1';
        $params = [':op' => $idOperasi, ':jenis' => $jenis] + ($jenis === 'RENOPS' ? [] : [':ref' => (int)$idReferensi]);
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? self::hydrateRow($row) : null;
    }

    /**
     * Mengambil seluruh daftar snapshot milik satu Operasi SAR.
     */
    public static function getAllByOperasi(int $idOperasi): array {
        if ($idOperasi <= 0) {
            return [];
        }
        self::ensureSchemaUpdated();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM DokumenPetaSnapshot WHERE id_operasi = :op ORDER BY updated_at DESC, id_snapshot DESC");
        $stmt->execute([':op' => $idOperasi]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map([self::class, 'hydrateRow'], $rows);
    }

    /**
     * Menghapus snapshot berdasarkan ID.
     */
    public static function deleteSnapshot(int $idSnapshot): bool {
        self::ensureSchemaUpdated();
        $db = getDB();
        $existing = self::getById($idSnapshot);
        if (!$existing) {
            return false;
        }
        self::removeImageFile((string)($existing['image_path'] ?? ''));
        $stmt = $db->prepare("DELETE FROM DokumenPetaSnapshot WHERE id_snapshot = :id");
        return $stmt->execute([':id' => $idSnapshot]);
    }

    /**
     * Menghapus seluruh snapshot peta milik operasi tertentu dari basis data,
     * dan mengembalikan daftar file gambar yang siap dihapus dari disk setelah transaksi selesai.
     *
     * @param int $idOperasi
     * @param bool $deleteFilesImmediately
     * @return array<string>
     */
    public static function deleteByOperasi(int $idOperasi, bool $deleteFilesImmediately = true): array {
        self::ensureSchemaUpdated();
        $filePaths = [];
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT image_path FROM DokumenPetaSnapshot WHERE id_operasi = :op");
            $stmt->execute([':op' => $idOperasi]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                $p = trim((string)($r['image_path'] ?? ''));
                if ($p !== '') {
                    $filePaths[] = $p;
                    if ($deleteFilesImmediately) {
                        self::removeImageFile($p);
                    }
                }
            }
            $db->prepare("DELETE FROM DokumenPetaSnapshot WHERE id_operasi = :op")->execute([':op' => $idOperasi]);
        } catch (Throwable $e) {
            error_log("[DokumenPetaSnapshotModel deleteByOperasi] " . $e->getMessage());
        }
        return $filePaths;
    }

    /**
     * Menghapus snapshot peta milik satu hari RENOPS (Peta Rencana Operasi) beserta berkasnya.
     */
    public static function deleteByReferensi(string $tipeDokumen, int $idReferensi): void {
        self::ensureSchemaUpdated();
        $jenisDokumen = self::normalizeJenisDokumen($tipeDokumen);
        if ($jenisDokumen === null) {
            return;
        }
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT image_path FROM DokumenPetaSnapshot WHERE jenis_dokumen = :jd AND id_referensi = :ref");
            $stmt->execute([':jd' => $jenisDokumen, ':ref' => $idReferensi]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                self::removeImageFile((string)($r['image_path'] ?? ''));
            }
            $db->prepare("DELETE FROM DokumenPetaSnapshot WHERE jenis_dokumen = :jd AND id_referensi = :ref")->execute([
                ':jd' => $jenisDokumen,
                ':ref' => $idReferensi
            ]);
        } catch (Throwable $e) {
            error_log("[DokumenPetaSnapshotModel deleteByReferensi] " . $e->getMessage());
        }
    }

    /**
     * Perapian data lama (idempoten, dijalankan database/migrate.php) setelah dokumen cetak Laporan Awal,
     * LAPBANG & Penutupan dihapus dan Peta LKP menjadi 1x per operasi:
     * - Peta LKP operasi = RENOPS tanpa hari; bila belum ada, RENOPS hari paling awal, lalu peta Laporan Awal lama.
     * - Snapshot lain selain Peta Rencana Operasi per hari (RENOPS hari lain, LAPORAN_AWAL, SITREP, PENUTUPAN)
     *   serta snapshot milik operasi yang sudah terhapus: baris & berkasnya dihapus.
     * - Token QR dokumen selain RENOPS dihapus.
     * Berkas tanpa baris tidak disapu: folder uploads dapat dipakai bersama oleh DB lain (mis. DB test).
     *
     * @return array{snapshot:int, lkp:int, token:int}
     */
    public static function rapikanDataLama(PDO $db): array {
        self::ensureSchemaUpdated(true);
        $hasil = ['snapshot' => 0, 'lkp' => 0, 'token' => 0];
        $opsAda = array_flip(array_map('intval', $db->query('SELECT id_operasi FROM Operasi')->fetchAll(PDO::FETCH_COLUMN)));
        $rows = $db->query("SELECT id_snapshot, id_operasi, jenis_dokumen, id_referensi, image_path FROM DokumenPetaSnapshot
            ORDER BY id_operasi, (id_referensi IS NULL) DESC, id_referensi ASC, updated_at DESC, id_snapshot DESC")->fetchAll(PDO::FETCH_ASSOC);
        $perOps = [];
        foreach ($rows as $r) {
            $perOps[(int)$r['id_operasi']][] = $r;
        }
        $hapus = $db->prepare('DELETE FROM DokumenPetaSnapshot WHERE id_snapshot = :id');
        $jadikanLkp = $db->prepare("UPDATE DokumenPetaSnapshot SET jenis_dokumen = 'RENOPS', id_referensi = NULL WHERE id_snapshot = :id");
        foreach ($perOps as $idOps => $list) {
            $lkp = null;
            if (isset($opsAda[$idOps])) {
                foreach (['RENOPS', 'LAPORAN_AWAL'] as $jenis) {
                    foreach ($list as $r) {
                        if ($lkp === null && $r['jenis_dokumen'] === $jenis) {
                            $lkp = $r;
                        }
                    }
                }
            }
            foreach ($list as $r) {
                $simpan = isset($opsAda[$idOps]) && $r['jenis_dokumen'] === 'RENOPS_RENCANA' && (int)$r['id_referensi'] > 0;
                if ($simpan) {
                    continue;
                }
                if ($lkp !== null && $r['id_snapshot'] === $lkp['id_snapshot']) {
                    if ($r['jenis_dokumen'] !== 'RENOPS' || $r['id_referensi'] !== null) {
                        $jadikanLkp->execute([':id' => $r['id_snapshot']]);
                        $hasil['lkp']++;
                    }
                    continue;
                }
                self::removeImageFile((string)$r['image_path']);
                $hapus->execute([':id' => $r['id_snapshot']]);
                $hasil['snapshot']++;
            }
        }
        try {
            $hasil['token'] = $db->exec("DELETE FROM DokumenToken WHERE doc_type <> 'renops'") ?: 0;
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') { // tabel DokumenToken belum ada
                throw $e;
            }
        }
        return $hasil;
    }

    /**
     * Menghapus file fisik gambar dari direktori uploads.
     */
    public static function removeImageFile(string $relativePath): void {
        if ($relativePath === '') {
            return;
        }
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__);
        $cleanRel = ltrim($relativePath, '/');
        if (!str_starts_with($cleanRel, 'uploads/map_snapshots/')) {
            return;
        }
        $fullPath = $rootDir . '/' . $cleanRel;
        if (file_exists($fullPath) && is_file($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Memperkaya baris database dengan URL gambar dan dekode JSON.
     */
    private static function hydrateRow(array $row): array {
        $relPath = ltrim((string)($row['image_path'] ?? ''), '/');
        $baseUrl = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
        $row['image_url'] = $relPath !== '' ? ($baseUrl . '/' . $relPath) : '';

        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__);
        $fullPath = $rootDir . '/' . $relPath;
        $row['image_exists'] = ($relPath !== '' && file_exists($fullPath) && is_file($fullPath));

        $bounds = !empty($row['bounds_json']) ? json_decode((string)$row['bounds_json'], true) : [];
        $row['bounds'] = is_array($bounds) ? $bounds : [];

        $komponen = !empty($row['komponen_json']) ? json_decode((string)$row['komponen_json'], true) : [];
        $row['komponen'] = is_array($komponen) ? $komponen : [
            'points' => [],
            'polygons' => [],
            'patterns' => [],
            'routes' => []
        ];

        return $row;
    }
}
