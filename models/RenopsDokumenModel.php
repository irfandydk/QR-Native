<?php
/**
 * QR-SAR - Renops Dokumen Model
 * Dokumen Rencana Operasi SAR berversi: satu DRAF (versi 0, hanya suntingan) dan
 * versi TERBIT/DIGANTIKAN (salinan beku + token QR). Penerbitan ada di service terpisah.
 */

declare(strict_types=1);

class RenopsDokumenModel {
    private const SAFE_PREFIXES = ['uploads/renops_dokumen/', 'uploads/renops_foto/'];

    private static function hydrate($row): ?array {
        if (!$row) {
            return null;
        }
        $row['konten'] = json_decode((string)$row['konten_json'], true) ?: [];
        $row['rev'] = (int)$row['rev'];
        return $row;
    }

    private static function fetchOne(string $where, array $params): ?array {
        $stmt = getDB()->prepare("SELECT * FROM RenopsDokumen WHERE {$where} LIMIT 1");
        $stmt->execute($params);
        return self::hydrate($stmt->fetch(PDO::FETCH_ASSOC));
    }

    public static function getDraft(int $idRenops): ?array {
        return self::fetchOne("id_renops = :r AND status = 'DRAF'", [':r' => $idRenops]);
    }

    public static function getLatestTerbit(int $idRenops): ?array {
        return self::fetchOne("id_renops = :r AND status = 'TERBIT' ORDER BY versi DESC", [':r' => $idRenops]);
    }

    public static function findByToken(string $token): ?array {
        return self::fetchOne("token = :t", [':t' => $token]);
    }

    /**
     * Struktur organisasi versi terbit (TERBIT/DIGANTIKAN) terbaru di sistem yang diterbitkan paling lambat
     * $sebelum, selain milik $excludeRenops. Batas waktu membuat default tetap: terbitan baru di operasi lain
     * tidak mengubah isi (dan source_hash) dokumen yang lebih lama.
     */
    public static function latestStruktur(int $excludeRenops, string $sebelum): ?array {
        $stmt = getDB()->prepare("SELECT konten_json FROM RenopsDokumen
            WHERE id_renops <> :r AND status IN ('TERBIT','DIGANTIKAN') AND issued_at <= :t
            ORDER BY issued_at DESC, id_dokumen DESC LIMIT 1");
        $stmt->execute([':r' => $excludeRenops, ':t' => $sebelum]);
        $s = json_decode((string)$stmt->fetchColumn(), true)['sections']['struktur'] ?? null;
        return is_array($s) ? $s : null;
    }

    /** @return array<int, array{terbit_versi: ?int}> */
    public static function statusByOperasi(int $idOperasi): array {
        try {
            $stmt = getDB()->prepare("SELECT id_renops, MAX(versi) AS v FROM RenopsDokumen
                WHERE id_operasi = :o AND status = 'TERBIT' GROUP BY id_renops");
            $stmt->execute([':o' => $idOperasi]);
        } catch (PDOException $e) {
            if (self::tabelBelumAda($e)) {
                return [];
            }
            throw $e;
        }
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $out[(int)$r['id_renops']] = ['terbit_versi' => (int)$r['v']];
        }
        return $out;
    }

    /**
     * Simpan suntingan draf (digabung per kunci; nilai null = hapus suntingan kunci).
     * Mengembalikan rev baru, atau null bila rev usang (bentrok).
     */
    public static function saveDraft(int $idRenops, int $idOperasi, array $sections, int $expectedRev): ?int {
        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $stmt = $db->prepare("SELECT id_dokumen, rev, konten_json FROM RenopsDokumen
                WHERE id_renops = :r AND status = 'DRAF' FOR UPDATE");
            $stmt->execute([':r' => $idRenops]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $current = $row ? ((json_decode((string)$row['konten_json'], true)['sections'] ?? [])) : [];
            foreach ($sections as $key => $val) {
                if ($val === null) {
                    unset($current[$key]);
                } else {
                    $current[$key] = $val;
                }
            }
            $json = json_encode(['sections' => (object)$current], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

            if (!$row) {
                if ($expectedRev !== 0) {
                    $newRev = null;
                } else {
                    $db->prepare("INSERT INTO RenopsDokumen (id_renops, id_operasi, versi, status, rev, konten_json)
                        VALUES (:r, :o, 0, 'DRAF', 1, :k)")->execute([':r' => $idRenops, ':o' => $idOperasi, ':k' => $json]);
                    $newRev = 1;
                }
            } else {
                $upd = $db->prepare("UPDATE RenopsDokumen SET rev = rev + 1, konten_json = :k
                    WHERE id_dokumen = :id AND rev = :rev");
                $upd->execute([':k' => $json, ':id' => (int)$row['id_dokumen'], ':rev' => $expectedRev]);
                $newRev = $upd->rowCount() === 1 ? $expectedRev + 1 : null;
            }
            if ($ownTx) {
                $db->commit();
            }
            return $newRev;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            // Insert pertama bentrok dengan draf lain (uq_renopsdok_draf) atau deadlock (1213, transaksi sudah
            // di-rollback server; hanya bila transaksi milik sendiri) = konflik, bukan error.
            $code = $e instanceof PDOException ? ($e->errorInfo[1] ?? 0) : 0;
            if ($code === 1062 || ($code === 1213 && $ownTx)) {
                return null;
            }
            throw $e;
        }
    }

    /** @return list<string> */
    public static function collectFilePaths(int $idRenops): array {
        $db = getDB();
        $paths = [];
        try {
            $stmt = $db->prepare("SELECT konten_json FROM RenopsDokumen WHERE id_renops = :r AND status IN ('TERBIT','DIGANTIKAN')");
            $stmt->execute([':r' => $idRenops]);
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $json) {
                $k = json_decode((string)$json, true) ?: [];
                $paths[] = $k['peta']['path'] ?? null;
                $paths[] = $k['peta_rencana']['path'] ?? null;
                foreach ($k['foto'] ?? [] as $f) {
                    $paths[] = $f['path'] ?? null;
                }
            }
            $stmt = $db->prepare("SELECT path FROM RenopsFoto WHERE id_renops = :r");
            $stmt->execute([':r' => $idRenops]);
            $paths = array_merge($paths, $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (PDOException $e) {
            if (!self::tabelBelumAda($e)) {
                throw $e;
            }
        }
        return array_values(array_unique(array_filter($paths, fn($p) => is_string($p) && $p !== '')));
    }

    /**
     * Tabel RenopsDokumen/RenopsFoto belum dibuat (migrasi 2026_11_06 belum dijalankan) = belum ada dokumen/foto.
     * Hanya untuk jalur baca status & daftar berkas; editor/terbit tetap melempar agar migrasi yang terlewat terlihat.
     */
    private static function tabelBelumAda(PDOException $e): bool {
        return $e->getCode() === '42S02';
    }

    /** @return list<string> */
    public static function collectFilePathsForOperasi(int $idOperasi): array {
        $stmt = getDB()->prepare("SELECT id_renops FROM Renops WHERE id_operasi = :o");
        $stmt->execute([':o' => $idOperasi]);
        $paths = [];
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $idRenops) {
            $paths = array_merge($paths, self::collectFilePaths((int)$idRenops));
        }
        return array_values(array_unique($paths));
    }

    /** Hapus berkas di disk; panggil hanya setelah penghapusan DB ter-commit. */
    public static function deleteFiles(array $paths): void {
        foreach ($paths as $p) {
            if (!is_string($p) || str_contains($p, '..')) {
                continue;
            }
            foreach (self::SAFE_PREFIXES as $prefix) {
                if (str_starts_with($p, $prefix)) {
                    $abs = ROOT_DIR . '/' . $p;
                    if (is_file($abs)) {
                        @unlink($abs);
                    }
                    break;
                }
            }
        }
    }
}
