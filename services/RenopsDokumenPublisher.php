<?php
/**
 * QR-SAR - Penerbitan Dokumen Rencana Operasi SAR (SMEAC)
 * Membekukan hasil resolve() menjadi versi TERBIT berisi salinan peta/foto + token QR,
 * dan menandai versi TERBIT sebelumnya sebagai DIGANTIKAN.
 */

declare(strict_types=1);

class RenopsDokumenPublisher {
    private const FROZEN_DIR = 'uploads/renops_dokumen';
    private const FROZEN_EXT = ['png', 'jpg', 'jpeg', 'webp'];

    /**
     * Terbitkan draf RENOPS. null bila rev draf bukan $expectedRev (atau bentrok dengan terbit serentak).
     * baru=false: sumber tidak berubah sejak versi TERBIT terakhir, versi itu yang dikembalikan.
     * @return array{versi:int, token:string, verify_url:string, public_url:string, baru:bool}|null
     */
    public static function publish(int $idRenops, ?string $userId, int $expectedRev): ?array {
        $db = getDB();
        $copies = [];
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT rev FROM RenopsDokumen WHERE id_renops = :r AND status = 'DRAF' FOR UPDATE");
            $stmt->execute([':r' => $idRenops]);
            $rev = $stmt->fetchColumn();
            if (($rev === false ? 0 : (int)$rev) !== $expectedRev) {
                $db->rollBack();
                return null;
            }
            $doc = RenopsDokumenService::resolve($idRenops);
            if ($doc === null) {
                throw new InvalidArgumentException('RENOPS tidak ditemukan.');
            }
            $idOps = (int)$doc['meta']['id_operasi'];
            if ($rev === false) {
                $db->prepare("INSERT INTO RenopsDokumen (id_renops, id_operasi, versi, status, rev, konten_json)
                    VALUES (:r, :o, 0, 'DRAF', 0, '{\"sections\":{}}')")->execute([':r' => $idRenops, ':o' => $idOps]);
            }
            // Idempoten (R13): tanpa perubahan sumber, terbit ulang (mis. klik ganda) mengembalikan versi yang ada.
            $terbit = RenopsDokumenModel::getLatestTerbit($idRenops);
            if ($terbit !== null && ($terbit['konten']['meta']['source_hash'] ?? null) === $doc['meta']['source_hash']) {
                $db->commit();
                return self::hasil($terbit, false);
            }

            // Salinan beku: capture ulang peta / hapus foto tidak boleh mengubah versi terbit.
            $doc['peta']['path'] = self::freeze($doc['peta']['path'], $copies);
            if (isset($doc['peta_rencana'])) {
                $doc['peta_rencana']['path'] = self::freeze($doc['peta_rencana']['path'], $copies);
            }
            foreach ($doc['foto'] as $i => $f) {
                $doc['foto'][$i]['path'] = self::freeze($f['path'], $copies);
            }

            $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $hash = hash('sha256', $json);
            $stmt = $db->prepare("SELECT COALESCE(MAX(versi), 0) + 1 FROM RenopsDokumen WHERE id_renops = :r");
            $stmt->execute([':r' => $idRenops]);
            $versi = (int)$stmt->fetchColumn();
            $token = strtoupper(substr(hash_hmac('sha256', "RENOPS_DOC:{$idRenops}:{$versi}:{$hash}", APP_KEY), 0, 16));

            $db->prepare("UPDATE RenopsDokumen SET status = 'DIGANTIKAN' WHERE id_renops = :r AND status = 'TERBIT'")
                ->execute([':r' => $idRenops]);
            $db->prepare("INSERT INTO RenopsDokumen (id_renops, id_operasi, versi, status, rev, konten_json, content_hash, token, issued_at, issued_by)
                VALUES (:r, :o, :v, 'TERBIT', 0, :k, :h, :t, NOW(), :by)")->execute([
                ':r' => $idRenops, ':o' => $idOps, ':v' => $versi, ':k' => $json, ':h' => $hash, ':t' => $token, ':by' => $userId,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            RenopsDokumenModel::deleteFiles($copies);
            // Draf pertama dibuat serentak (1062) / deadlock (1213) = bentrok, bukan error.
            if ($e instanceof PDOException && in_array($e->errorInfo[1] ?? 0, [1062, 1213], true)) {
                return null;
            }
            throw $e;
        }

        return self::hasil(['id_operasi' => $idOps, 'id_renops' => $idRenops, 'versi' => $versi, 'token' => $token], true);
    }

    private static function hasil(array $row, bool $baru): array {
        return [
            'versi' => (int)$row['versi'], 'token' => (string)$row['token'],
            'verify_url' => self::verifyUrl($row), 'public_url' => self::publicUrl((string)$row['token']), 'baru' => $baru,
        ];
    }

    /** @param array $versi baris versi (id_operasi, id_renops, token) */
    public static function verifyUrl(array $versi): string {
        return BASE_URL . '/operasi.php?' . http_build_query([
            'action' => 'verify', 'type' => 'renops', 'id' => (int)$versi['id_operasi'],
            'ref' => (int)$versi['id_renops'], 'token' => (string)$versi['token'],
        ]);
    }

    public static function publicUrl(string $token): string {
        return BASE_URL . '/operasi.php?' . http_build_query(['action' => 'renops_publik', 'token' => $token]);
    }

    /** true bila belum pernah terbit, atau data sumber berubah sejak versi TERBIT terakhir. */
    public static function hasUnpublishedChanges(int $idRenops): bool {
        $terbit = RenopsDokumenModel::getLatestTerbit($idRenops);
        // source_hash, bukan content_hash: path di versi beku sudah diganti salinan.
        return $terbit === null
            || (RenopsDokumenService::resolve($idRenops)['meta']['source_hash'] ?? null) !== ($terbit['konten']['meta']['source_hash'] ?? null);
    }

    /** Salin berkas ke folder beku dengan nama acak (uploads/ disajikan statis); null tetap null. */
    private static function freeze(?string $rel, array &$copies): ?string {
        if ($rel === null || $rel === '') {
            return $rel;
        }
        $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
        if (!str_starts_with($rel, 'uploads/') || str_contains($rel, '..') || !in_array($ext, self::FROZEN_EXT, true)) {
            throw new RuntimeException('Berkas dokumen tidak sah: ' . $rel);
        }
        $new = self::FROZEN_DIR . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        self::ensureDir();
        $copies[] = $new; // dicatat sebelum copy: salinan parsial ikut dihapus saat gagal
        if (!@copy(ROOT_DIR . '/' . $rel, ROOT_DIR . '/' . $new)) {
            throw new RuntimeException('Gagal membekukan berkas dokumen: ' . $rel);
        }
        return $new;
    }

    /** Folder dibuat saat runtime (uploads/* di-gitignore), lengkap dengan index.php 403. */
    private static function ensureDir(): void {
        $dir = ROOT_DIR . '/' . self::FROZEN_DIR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && !is_file($dir . '/index.php')) {
            @file_put_contents($dir . '/index.php', "<?php\nhttp_response_code(403);\nexit('Akses Ditolak.');\n");
        }
    }
}
