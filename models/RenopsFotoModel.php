<?php
/**
 * RenopsFotoModel: foto dokumentasi per RENOPS (tabel RenopsFoto).
 * Unggahan adalah batas kepercayaan: ukuran -> MIME (finfo) -> decode GD -> encode ulang JPEG.
 */

declare(strict_types=1);

class RenopsFotoModel {
    public const MAX_BYTES = 5242880; // 5 MB per foto
    public const MAX_PER_RENOPS = 12;
    public const MAX_SIDE = 1600;
    public const MAX_PIXELS = 40000000;
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const REL_DIR = 'uploads/renops_foto';

    /** Folder dibuat saat runtime (uploads/* di-gitignore), lengkap dengan index.php 403. */
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

    public static function store(int $idRenops, string $tmpPath, int $size, ?string $userId, string $keterangan = ''): array {
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Ukuran foto maksimal 5 MB.');
        }
        $data = is_file($tmpPath) ? @file_get_contents($tmpPath, false, null, 0, self::MAX_BYTES + 1) : false;
        if ($data === false || $data === '' || strlen($data) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Berkas foto tidak dapat dibaca.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($data);
        if (!in_array($mime, self::ALLOWED_MIME, true)) {
            throw new InvalidArgumentException('Format foto harus JPEG, PNG, atau WebP.');
        }
        if ($mime === 'image/webp' && !(imagetypes() & IMG_WEBP)) {
            throw new InvalidArgumentException('Format WebP tidak didukung server ini.');
        }
        // Header dibaca tanpa decode: cegah decompression bomb (byte kecil, piksel raksasa).
        $info = @getimagesizefromstring($data);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new InvalidArgumentException('Berkas bukan gambar yang valid.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Dimensi foto terlalu besar (maksimal 40 megapiksel).');
        }
        // 40 MP truecolor ~160 MB di GD: naikkan batas memori untuk request ini bila lebih rendah.
        $limit = (string)ini_get('memory_limit');
        if ($limit !== '-1' && ini_parse_quantity($limit) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
        $img = @imagecreatefromstring($data);
        if ($img === false) {
            throw new InvalidArgumentException('Berkas bukan gambar yang valid.');
        }
        unset($data);

        $w = imagesx($img);
        $h = imagesy($img);
        if (max($w, $h) > self::MAX_SIDE) {
            $scaled = $w >= $h ? imagescale($img, self::MAX_SIDE) : imagescale($img, (int)max(1, round($w * self::MAX_SIDE / $h)), self::MAX_SIDE);
            if ($scaled === false) {
                throw new RuntimeException('Gagal memperkecil foto.');
            }
            unset($img);
            $img = $scaled;
        }
        // PNG/WebP transparan: ratakan di atas putih agar JPEG tidak hitam.
        $flat = imagecreatetruecolor(imagesx($img), imagesy($img));
        if ($flat === false) {
            throw new RuntimeException('Gagal memproses foto.');
        }
        imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
        imagecopy($flat, $img, 0, 0, 0, 0, imagesx($img), imagesy($img));

        $dir = self::ensureUploadDir();
        $rel = self::REL_DIR . '/' . bin2hex(random_bytes(16)) . '.jpg';
        $abs = ROOT_DIR . '/' . $rel;
        if (!is_dir($dir) || !@imagejpeg($flat, $abs, 82)) {
            @unlink($abs);
            throw new RuntimeException('Gagal menyimpan foto ke server.');
        }

        $db = getDB();
        try {
            $db->beginTransaction();
            // Kunci baris RENOPS agar batas 12 dan urutan aman dari unggahan serentak.
            $lock = $db->prepare("SELECT id_renops FROM Renops WHERE id_renops = :r FOR UPDATE");
            $lock->execute([':r' => $idRenops]);
            if ($lock->fetchColumn() === false) {
                throw new InvalidArgumentException('RENOPS tidak ditemukan.');
            }
            $st = $db->prepare("SELECT COUNT(*), COALESCE(MAX(urutan), 0) FROM RenopsFoto WHERE id_renops = :r");
            $st->execute([':r' => $idRenops]);
            [$count, $maxUrut] = $st->fetch(PDO::FETCH_NUM);
            if ((int)$count >= self::MAX_PER_RENOPS) {
                throw new InvalidArgumentException('Maksimal ' . self::MAX_PER_RENOPS . ' foto per RENOPS.');
            }
            $ins = $db->prepare("INSERT INTO RenopsFoto (id_renops, path, keterangan, urutan, uploaded_by) VALUES (:r, :p, :k, :u, :b)");
            $ins->execute([
                ':r' => $idRenops, ':p' => $rel, ':k' => mb_substr(trim($keterangan), 0, 255),
                ':u' => (int)$maxUrut + 1, ':b' => $userId,
            ]);
            $id = (int)$db->lastInsertId();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            @unlink($abs);
            throw $e;
        }
        $st = $db->prepare("SELECT * FROM RenopsFoto WHERE id_foto = :i");
        $st->execute([':i' => $id]);
        return $st->fetch(PDO::FETCH_ASSOC);
    }

    /** @return list<array> */
    public static function listByRenops(int $idRenops): array {
        $st = getDB()->prepare("SELECT * FROM RenopsFoto WHERE id_renops = :r ORDER BY urutan, id_foto");
        $st->execute([':r' => $idRenops]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function update(int $idFoto, int $idRenops, string $keterangan, int $urutan): bool {
        $st = getDB()->prepare("UPDATE RenopsFoto SET keterangan = :k, urutan = :u WHERE id_foto = :i AND id_renops = :r");
        $st->execute([':k' => mb_substr(trim($keterangan), 0, 255), ':u' => $urutan, ':i' => $idFoto, ':r' => $idRenops]);
        // rowCount 0 bila nilai sama; cek keberadaan baris.
        if ($st->rowCount() > 0) {
            return true;
        }
        $chk = getDB()->prepare("SELECT 1 FROM RenopsFoto WHERE id_foto = :i AND id_renops = :r");
        $chk->execute([':i' => $idFoto, ':r' => $idRenops]);
        return $chk->fetchColumn() !== false;
    }

    public static function delete(int $idFoto, int $idRenops): bool {
        $db = getDB();
        $st = $db->prepare("SELECT path FROM RenopsFoto WHERE id_foto = :i AND id_renops = :r");
        $st->execute([':i' => $idFoto, ':r' => $idRenops]);
        $path = $st->fetchColumn();
        if ($path === false) {
            return false;
        }
        $del = $db->prepare("DELETE FROM RenopsFoto WHERE id_foto = :i AND id_renops = :r");
        $del->execute([':i' => $idFoto, ':r' => $idRenops]);
        if ($del->rowCount() < 1) {
            return false;
        }
        // Hanya setelah DB berhasil; versi TERBIT memakai salinan beku, bukan berkas ini.
        RenopsDokumenModel::deleteFiles([$path]);
        return true;
    }
}
