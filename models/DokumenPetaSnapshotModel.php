<?php
/**
 * QR-SAR - Dokumen Peta Snapshot Model
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Mengelola penyimpanan, pengambilan, dan penghapusan cuplikan layar (snapshot) peta taktis 16:9
 * beserta daftar komponen spasial yang berada di dalam area bidikan (Bounding Box)
 * untuk dicetak pada Laporan Awal, RENOPS, SITREP (Lapbang), dan Laporan Penutupan.
 */

declare(strict_types=1);

class DokumenPetaSnapshotModel {
    private static bool $schemaChecked = false;

    public const ALLOWED_TYPES = ['LAPORAN_AWAL', 'RENOPS', 'SITREP', 'PENUTUPAN'];

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
                `jenis_dokumen` VARCHAR(30) NOT NULL DEFAULT 'LAPORAN_AWAL',
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
     * Normalisasi jenis dokumen ke daftar konstan yang diizinkan.
     */
    public static function normalizeJenisDokumen(string $jenis): string {
        $upper = strtoupper(trim($jenis));
        if ($upper === 'LAPBANG' || $upper === 'LAPORAN_HARIAN') {
            return 'SITREP';
        }
        if (in_array($upper, self::ALLOWED_TYPES, true)) {
            return $upper;
        }
        return 'LAPORAN_AWAL';
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

        $jenisDokumen = self::normalizeJenisDokumen((string)($data['jenis_dokumen'] ?? 'LAPORAN_AWAL'));
        $idReferensi = isset($data['id_referensi']) && $data['id_referensi'] !== '' && (int)$data['id_referensi'] > 0
            ? (int)$data['id_referensi']
            : null;

        if ($jenisDokumen === 'LAPORAN_AWAL' || $jenisDokumen === 'PENUTUPAN') {
            $idReferensi = null;
        }

        // Proses data gambar base64
        $rawImage = trim((string)($data['image_data'] ?? $data['image_base64'] ?? ''));
        if ($rawImage === '') {
            throw new InvalidArgumentException('Data gambar cuplikan peta (image_data) tidak boleh kosong.');
        }

        $ext = 'png';
        if (preg_match('#^data:image/(png|jpeg|jpg|webp);base64,#i', $rawImage, $m)) {
            $fmt = strtolower($m[1]);
            $ext = ($fmt === 'jpeg' || $fmt === 'jpg') ? 'jpg' : ($fmt === 'webp' ? 'webp' : 'png');
            $rawImage = substr($rawImage, strpos($rawImage, ',') + 1);
        }

        $binaryImage = base64_decode(str_replace(' ', '+', $rawImage), true);
        if ($binaryImage === false || strlen($binaryImage) < 64) {
            throw new InvalidArgumentException('Format base64 gambar cuplikan peta tidak valid atau rusak.');
        }

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
     * Mengambil snapshot terbaik untuk suatu dokumen cetak operasi.
     * - RENOPS: Berbeda-beda setiap hari RENOPS (berdasarkan id_referensi = id_renops).
     * - LAPORAN_AWAL & PENUTUPAN: Hanya 1 kali per operasi (id_referensi IS NULL).
     */
    public static function getSnapshot(int $idOperasi, string $jenisDokumen, ?int $idReferensi = null): ?array {
        if ($idOperasi <= 0) {
            return null;
        }
        self::ensureSchemaUpdated();
        $db = getDB();
        $jenis = self::normalizeJenisDokumen($jenisDokumen);

        // 1. Untuk RENOPS: ambil spesifik sesuai id_renops hari tersebut (tidak mencampur hari RENOPS lain)
        if ($jenis === 'RENOPS' && $idReferensi !== null && $idReferensi > 0) {
            $stmt = $db->prepare("SELECT * FROM DokumenPetaSnapshot
                WHERE id_operasi = :op AND jenis_dokumen = 'RENOPS' AND id_referensi = :ref
                ORDER BY updated_at DESC, id_snapshot DESC LIMIT 1");
            $stmt->execute([':op' => $idOperasi, ':ref' => $idReferensi]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return self::hydrateRow($row);
            }
            // Fallback hanya ke snapshot umum jika ada (id_referensi IS NULL), jangan ambil milik id_renops hari lain
            $stmtNull = $db->prepare("SELECT * FROM DokumenPetaSnapshot
                WHERE id_operasi = :op AND jenis_dokumen = 'RENOPS' AND id_referensi IS NULL
                ORDER BY updated_at DESC, id_snapshot DESC LIMIT 1");
            $stmtNull->execute([':op' => $idOperasi]);
            $rowNull = $stmtNull->fetch(PDO::FETCH_ASSOC);
            return $rowNull ? self::hydrateRow($rowNull) : null;
        }

        // 2. Untuk LAPORAN_AWAL dan PENUTUPAN (1x capture per operasi)
        $stmt = $db->prepare("SELECT * FROM DokumenPetaSnapshot
            WHERE id_operasi = :op AND jenis_dokumen = :jenis
            ORDER BY (id_referensi IS NULL) DESC, updated_at DESC, id_snapshot DESC LIMIT 1");
        $stmt->execute([':op' => $idOperasi, ':jenis' => $jenis]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return self::hydrateRow($row);
        }

        // 3. Jika jenis adalah PENUTUPAN dan belum ada snapshot khusus PENUTUPAN, fallback ke SITREP jika pernah disimpan sebelumnya
        if ($jenis === 'PENUTUPAN') {
            $stmtSitrep = $db->prepare("SELECT * FROM DokumenPetaSnapshot
                WHERE id_operasi = :op AND jenis_dokumen = 'SITREP'
                ORDER BY updated_at DESC, id_snapshot DESC LIMIT 1");
            $stmtSitrep->execute([':op' => $idOperasi]);
            $rowSitrep = $stmtSitrep->fetch(PDO::FETCH_ASSOC);
            if ($rowSitrep) {
                return self::hydrateRow($rowSitrep);
            }
        }

        return null;
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
     * Menghapus seluruh snapshot peta beserta file fisik gambar milik operasi tertentu.
     */
    public static function deleteByOperasi(int $idOperasi): void {
        self::ensureSchemaUpdated();
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT image_path FROM DokumenPetaSnapshot WHERE id_operasi = :op");
            $stmt->execute([':op' => $idOperasi]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                self::removeImageFile((string)($r['image_path'] ?? ''));
            }
            $db->prepare("DELETE FROM DokumenPetaSnapshot WHERE id_operasi = :op")->execute([':op' => $idOperasi]);
        } catch (Throwable $e) {
            error_log("[DokumenPetaSnapshotModel deleteByOperasi] " . $e->getMessage());
        }
    }

    /**
     * Menghapus snapshot peta berdasarkan referensi dokumen (RENOPS/SITREP/PENUTUPAN).
     */
    public static function deleteByReferensi(string $tipeDokumen, int $idReferensi): void {
        self::ensureSchemaUpdated();
        try {
            $db = getDB();
            $jenisDokumen = self::normalizeJenisDokumen($tipeDokumen);
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
     * Menghapus file fisik gambar dari direktori uploads.
     */
    private static function removeImageFile(string $relativePath): void {
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

        // Data URI untuk penyematan langsung yang tahan terhadap kendala jaringan saat print
        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__);
        $fullPath = $rootDir . '/' . $relPath;
        $row['image_exists'] = ($relPath !== '' && file_exists($fullPath) && is_file($fullPath));
        $row['image_data_uri'] = '';
        if ($row['image_exists']) {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : ($ext === 'webp' ? 'image/webp' : 'image/png');
            $bytes = @file_get_contents($fullPath);
            if ($bytes !== false) {
                $row['image_data_uri'] = 'data:' . $mime . ';base64,' . base64_encode($bytes);
            }
        }

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
