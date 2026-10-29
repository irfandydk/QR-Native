<?php
/**
 * QR-SAR - Operasi Relation Service (Domain Service Relasi Data SAR)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dedicated domain service managing child relations and differential synchronizations
 * for operational entities: DetailUnsur (Potensi/Personil), DetailAlut (Armada/Palsar),
 * and DetailTimBerangkat (Tim Lapangan).
 */

declare(strict_types=1);

class OperasiRelationService {
    /**
     * Apply differential synchronization for DetailUnsur.
     * Updates existing items in-place, creates new ones, and deletes removed items.
     */
    public static function applyDifferentialUnsur(PDO $db, int $idOperasi, array $unsurData): bool {
        $stmtExisting = $db->prepare("SELECT id_detail_unsur, nama_unsur FROM DetailUnsur WHERE id_operasi = :id ORDER BY id_detail_unsur ASC");
        $stmtExisting->execute([':id' => $idOperasi]);
        $existingRows = $stmtExisting->fetchAll();
        $existingIds = array_column($existingRows, 'id_detail_unsur');

        $stmtUpdate = $db->prepare("UPDATE DetailUnsur SET nama_unsur = :nama, jumlah_personil = :jml WHERE id_detail_unsur = :uid AND id_operasi = :id_ops");
        $stmtInsert = $db->prepare("INSERT INTO DetailUnsur (id_operasi, nama_unsur, jumlah_personil) VALUES (:id_ops, :nama, :jml)");

        $keptIds = [];
        $pool = $existingRows;

        foreach ($unsurData as $u) {
            if (empty($u['nama_unsur'])) continue;
            $uId = !empty($u['id_detail_unsur']) ? (int)$u['id_detail_unsur'] : null;
            $nama = trim((string)$u['nama_unsur']);
            $jml = (int)($u['jumlah_personil'] ?? 1);

            if ($uId && in_array($uId, $existingIds, true) && !in_array($uId, $keptIds, true)) {
                $stmtUpdate->execute([':uid' => $uId, ':id_ops' => $idOperasi, ':nama' => $nama, ':jml' => $jml]);
                $keptIds[] = $uId;
                foreach ($pool as $pIdx => $pItem) {
                    if ((int)$pItem['id_detail_unsur'] === $uId) {
                        unset($pool[$pIdx]);
                        break;
                    }
                }
            } elseif (!empty($pool)) {
                $matchKey = null;
                foreach ($pool as $pIdx => $pItem) {
                    if (strcasecmp($pItem['nama_unsur'], $nama) === 0) {
                        $matchKey = $pIdx;
                        break;
                    }
                }
                if ($matchKey === null) {
                    $matchKey = array_key_first($pool);
                }
                $reusedId = (int)$pool[$matchKey]['id_detail_unsur'];
                unset($pool[$matchKey]);

                $stmtUpdate->execute([':uid' => $reusedId, ':id_ops' => $idOperasi, ':nama' => $nama, ':jml' => $jml]);
                $keptIds[] = $reusedId;
            } else {
                $stmtInsert->execute([':id_ops' => $idOperasi, ':nama' => $nama, ':jml' => $jml]);
            }
        }

        $toDelete = array_diff($existingIds, $keptIds);
        if (!empty($toDelete)) {
            $delPlaceholders = implode(',', array_fill(0, count($toDelete), '?'));
            $stmtDel = $db->prepare("DELETE FROM DetailUnsur WHERE id_detail_unsur IN ({$delPlaceholders}) AND id_operasi = ?");
            $stmtDel->execute([...$toDelete, $idOperasi]);
            return true;
        }
        return false;
    }

    /**
     * Apply differential synchronization for DetailAlut.
     */
    public static function applyDifferentialAlut(PDO $db, int $idOperasi, array $alutData): bool {
        $stmtExisting = $db->prepare("SELECT id_detail_alut, nama_alut FROM DetailAlut WHERE id_operasi = :id ORDER BY id_detail_alut ASC");
        $stmtExisting->execute([':id' => $idOperasi]);
        $existingRows = $stmtExisting->fetchAll();
        $existingIds = array_column($existingRows, 'id_detail_alut');

        $stmtUpdate = $db->prepare("UPDATE DetailAlut SET id_alut = :id_alut, nama_alut = :nama, satuan = :sat, jumlah_alut = :jml WHERE id_detail_alut = :aid AND id_operasi = :id_ops");
        $stmtInsert = $db->prepare("INSERT INTO DetailAlut (id_operasi, id_alut, nama_alut, satuan, jumlah_alut) VALUES (:id_ops, :id_alut, :nama, :sat, :jml)");

        $keptIds = [];
        $pool = $existingRows;

        foreach ($alutData as $a) {
            if (empty($a['nama_alut'])) continue;
            $aId = !empty($a['id_detail_alut']) ? (int)$a['id_detail_alut'] : null;
            $nama = trim((string)$a['nama_alut']);
            $idAlut = (int)($a['id_alut'] ?? 0);
            $satuan = $a['satuan'] ?? 'Unit';
            $jml = (int)($a['jumlah_alut'] ?? 1);

            if ($aId && in_array($aId, $existingIds, true) && !in_array($aId, $keptIds, true)) {
                $stmtUpdate->execute([':aid' => $aId, ':id_ops' => $idOperasi, ':id_alut' => $idAlut, ':nama' => $nama, ':sat' => $satuan, ':jml' => $jml]);
                $keptIds[] = $aId;
                foreach ($pool as $pIdx => $pItem) {
                    if ((int)$pItem['id_detail_alut'] === $aId) {
                        unset($pool[$pIdx]);
                        break;
                    }
                }
            } elseif (!empty($pool)) {
                $matchKey = null;
                foreach ($pool as $pIdx => $pItem) {
                    if (strcasecmp($pItem['nama_alut'], $nama) === 0) {
                        $matchKey = $pIdx;
                        break;
                    }
                }
                if ($matchKey === null) {
                    $matchKey = array_key_first($pool);
                }
                $reusedId = (int)$pool[$matchKey]['id_detail_alut'];
                unset($pool[$matchKey]);

                $stmtUpdate->execute([':aid' => $reusedId, ':id_ops' => $idOperasi, ':id_alut' => $idAlut, ':nama' => $nama, ':sat' => $satuan, ':jml' => $jml]);
                $keptIds[] = $reusedId;
            } else {
                $stmtInsert->execute([':id_ops' => $idOperasi, ':id_alut' => $idAlut, ':nama' => $nama, ':sat' => $satuan, ':jml' => $jml]);
            }
        }

        $toDelete = array_diff($existingIds, $keptIds);
        if (!empty($toDelete)) {
            $delPlaceholders = implode(',', array_fill(0, count($toDelete), '?'));
            $stmtDel = $db->prepare("DELETE FROM DetailAlut WHERE id_detail_alut IN ({$delPlaceholders}) AND id_operasi = ?");
            $stmtDel->execute([...$toDelete, $idOperasi]);
            return true;
        }
        return false;
    }

    /**
     * Apply differential synchronization for DetailTimBerangkat.
     */
    public static function applyDifferentialTimBerangkat(PDO $db, int $idOperasi, array $data): bool {
        $timBerangkat = trim((string)($data['tim_berangkat'] ?? ''));
        if ($timBerangkat === '' && empty($data['detail_tim_berangkat'])) {
            $stmtDel = $db->prepare("DELETE FROM DetailTimBerangkat WHERE id_operasi = :id");
            $stmtDel->execute([':id' => $idOperasi]);
            return $stmtDel->rowCount() > 0;
        }

        $idTim = (int)($data['id_tim_berangkat'] ?? 0);
        if ($idTim <= 0 && $timBerangkat !== '') {
            $stmtMt = $db->prepare("SELECT id_tim FROM MasterTim WHERE LOWER(nama_tim) = LOWER(:nama) LIMIT 1");
            $stmtMt->execute([':nama' => $timBerangkat]);
            $idTim = (int)($stmtMt->fetchColumn() ?: 0);
        }

        $stmtCheck = $db->prepare("SELECT id_detail_tim FROM DetailTimBerangkat WHERE id_operasi = :id ORDER BY id_detail_tim ASC LIMIT 1");
        $stmtCheck->execute([':id' => $idOperasi]);
        $existingId = $stmtCheck->fetchColumn();

        if ($existingId) {
            $db->prepare("UPDATE DetailTimBerangkat SET id_tim = :id_tim, nama_tim = :nama WHERE id_detail_tim = :dtid")
               ->execute([':id_tim' => $idTim, ':nama' => $timBerangkat, ':dtid' => $existingId]);
            $stmtDel = $db->prepare("DELETE FROM DetailTimBerangkat WHERE id_operasi = :id AND id_detail_tim != :dtid");
            $stmtDel->execute([':id' => $idOperasi, ':dtid' => $existingId]);
            return $stmtDel->rowCount() > 0;
        } else {
            $db->prepare("INSERT INTO DetailTimBerangkat (id_operasi, id_tim, nama_tim) VALUES (:id_ops, :id_tim, :nama)")
               ->execute([':id_ops' => $idOperasi, ':id_tim' => $idTim, ':nama' => $timBerangkat]);
            return false;
        }
    }

    /**
     * Synchronize Unsur and Alut snapshots within an isolated transaction.
     */
    public static function syncUnsurAlut(int $idOperasi, array $unsurData, array $alutData): void {
        $db = getDB();
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $delUnsur = false;
            $delAlut = false;
            if (!empty($unsurData)) {
                $delUnsur = self::applyDifferentialUnsur($db, $idOperasi, $unsurData);
            }
            if (!empty($alutData)) {
                $delAlut = self::applyDifferentialAlut($db, $idOperasi, $alutData);
            }

            if (!$inTx) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

}
