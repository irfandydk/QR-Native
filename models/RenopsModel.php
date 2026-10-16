<?php
/**
 * QR-SAR - Rencana Operasi (RENOPS) Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class RenopsModel {
    private static bool $schemaChecked = false;

    public static function ensureSchemaUpdated(bool $force = false): void {
        if (!$force) return;
        if (self::$schemaChecked) return;
        self::$schemaChecked = true;
        try {
            $db = getDB();
            $cols = $db->query("SHOW COLUMNS FROM RenopsSRU LIKE 'total_sapuan'")->fetchAll();
            if (empty($cols)) {
                $db->exec("ALTER TABLE RenopsSRU ADD COLUMN total_sapuan VARCHAR(191) NULL DEFAULT NULL");
            }
            $colsTs = $db->query("SHOW COLUMNS FROM RenopsSRU LIKE 'track_spacing'")->fetchAll();
            if (empty($colsTs)) {
                $db->exec("ALTER TABLE RenopsSRU ADD COLUMN track_spacing VARCHAR(191) NULL DEFAULT NULL");
            }
        } catch (Throwable $e) {
            error_log("[RenopsModel Schema Check] " . $e->getMessage());
        }
    }

    public static function getByOperasiId(int $idOperasi): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM Renops WHERE id_operasi = :id ORDER BY id_renops ASC");
        $stmt->execute([':id' => $idOperasi]);
        $list = $stmt->fetchAll();
        if (empty($list)) {
            return [];
        }

        $renopsIds = array_column($list, 'id_renops');
        $placeholders = implode(',', array_fill(0, count($renopsIds), '?'));
        $stmtS = $db->prepare("SELECT * FROM RenopsSRU WHERE id_renops IN ({$placeholders}) ORDER BY id_sru ASC");
        $stmtS->execute($renopsIds);
        $allSrus = $stmtS->fetchAll();

        $srusByRenops = [];
        foreach ($allSrus as $sru) {
            $srusByRenops[$sru['id_renops']][] = $sru;
        }

        foreach ($list as &$r) {
            $r['srus'] = $srusByRenops[$r['id_renops']] ?? [];

            // Decode snapshots if present
            if (!empty($r['detail_unsur']) && is_string($r['detail_unsur'])) {
                $decoded = json_decode($r['detail_unsur'], true);
                $r['detail_unsur'] = is_array($decoded) ? $decoded : [];
            } else {
                $r['detail_unsur'] = [];
            }

            if (!empty($r['detail_alut']) && is_string($r['detail_alut'])) {
                $decoded = json_decode($r['detail_alut'], true);
                $r['detail_alut'] = is_array($decoded) ? $decoded : [];
            } else {
                $r['detail_alut'] = [];
            }
        }

        return $list;
    }

    public static function getById(int $id): ?array {
        self::ensureSchemaUpdated();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM Renops WHERE id_renops = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $renops = $stmt->fetch();
        if (!$renops) return null;

        $stmtS = $db->prepare("SELECT * FROM RenopsSRU WHERE id_renops = :id ORDER BY id_sru ASC");
        $stmtS->execute([':id' => $id]);
        $renops['srus'] = $stmtS->fetchAll();

        if (!empty($renops['detail_unsur']) && is_string($renops['detail_unsur'])) {
            $decoded = json_decode($renops['detail_unsur'], true);
            $renops['detail_unsur'] = is_array($decoded) ? $decoded : [];
        } else {
            $renops['detail_unsur'] = [];
        }

        if (!empty($renops['detail_alut']) && is_string($renops['detail_alut'])) {
            $decoded = json_decode($renops['detail_alut'], true);
            $renops['detail_alut'] = is_array($decoded) ? $decoded : [];
        } else {
            $renops['detail_alut'] = [];
        }

        return $renops;
    }

    public static function create(array $data): int {
        self::ensureSchemaUpdated();
        $db = getDB();
        $db->beginTransaction();

        try {
            $ops = OperasiModel::getById((int)$data['id_operasi']);
            if (!$ops) throw new RuntimeException("Operasi tidak ditemukan");
            if ($ops['status'] === 'CLOSED') {
                throw new RuntimeException("Operasi SAR telah berstatus DITUTUP (CLOSED). Rencana Operasi baru tidak dapat ditambahkan kecuali operasi dibuka kembali.");
            }

            $srus = $data['srus'] ?? [];
            $unsur = !empty($data['detail_unsur']) && is_array($data['detail_unsur']) ? $data['detail_unsur'] : ($ops['detail_unsur'] ?? []);
            $alut = !empty($data['detail_alut']) && is_array($data['detail_alut']) ? $data['detail_alut'] : ($ops['detail_alut'] ?? []);
            $fullTextWa = TemplateHelper::generateRenopsWA($ops, $data, $srus, $ops['korban'], $unsur, $alut);

            $stmt = $db->prepare("INSERT INTO Renops (
                id_operasi, hari_ke, tanggal_laporan, waktu_briefing,
                waktu_mulai_ops, cuaca, unsur_text, alut_text,
                detail_unsur, detail_alut,
                full_text_wa, created_at
            ) VALUES (
                :id_operasi, :hari_ke, :tanggal_laporan, :waktu_briefing,
                :waktu_mulai_ops, :cuaca, :unsur_text, :alut_text,
                :detail_unsur, :detail_alut,
                :full_text_wa, :created_at
            )");

            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                ':id_operasi' => (int)$data['id_operasi'],
                ':hari_ke' => strtoupper($data['hari_ke'] ?? 'H.1'),
                ':tanggal_laporan' => $data['tanggal_laporan'] ?? date('Y-m-d'),
                ':waktu_briefing' => $data['waktu_briefing'] ?? $now,
                ':waktu_mulai_ops' => $data['waktu_mulai_ops'] ?? $now,
                ':cuaca' => $data['cuaca'] ?? ($ops['cuaca'] ?: 'Cerah Berawan'),
                ':unsur_text' => $data['unsur_text'] ?? ($ops['unsur_terlibat'] ?? ''),
                ':alut_text' => $data['alut_text'] ?? ($ops['alut_palsar'] ?? ''),
                ':detail_unsur' => json_encode($unsur, JSON_UNESCAPED_UNICODE),
                ':detail_alut' => json_encode($alut, JSON_UNESCAPED_UNICODE),
                ':full_text_wa' => $fullTextWa,
                ':created_at' => $now
            ]);

            $idRenops = (int)$db->lastInsertId();

            if (!empty($srus)) {
                $stmtSru = $db->prepare("INSERT INTO RenopsSRU (id_renops, nama_sru, koordinat_area, luas_area, pola_pencarian, total_sapuan, track_spacing) VALUES (:rid, :nama, :koor, :luas, :pola, :sapuan, :track)");
                foreach ($srus as $s) {
                    if (empty($s['nama_sru'])) continue;
                    $stmtSru->execute([
                        ':rid' => $idRenops,
                        ':nama' => $s['nama_sru'],
                        ':koor' => $s['koordinat_area'] ?? '',
                        ':luas' => $s['luas_area'] ?? '',
                        ':pola' => $s['pola_pencarian'] ?? 'Parallel Track Search',
                        ':sapuan' => $s['total_sapuan'] ?? '',
                        ':track' => !empty($s['track_spacing']) ? $s['track_spacing'] : null
                    ]);
                }
            }

            // Sync latest operational Unsur & Alut state to master tables
            OperasiModel::syncUnsurAlut((int)$data['id_operasi'], $unsur, $alut);

            $db->commit();
            return $idRenops;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool {
        $db = getDB();
        $db->beginTransaction();

        try {
            $renops = self::getById($id);
            if (!$renops) return false;

            $ops = OperasiModel::getById((int)$renops['id_operasi']);
            if ($ops && $ops['status'] === 'CLOSED') {
                throw new RuntimeException("Operasi SAR telah berstatus DITUTUP (CLOSED). Rencana Operasi tidak dapat diubah kecuali operasi dibuka kembali.");
            }
            $srus = $data['srus'] ?? $renops['srus'];
            $unsur = !empty($data['detail_unsur']) && is_array($data['detail_unsur']) ? $data['detail_unsur'] : ($renops['detail_unsur'] ?: ($ops['detail_unsur'] ?? []));
            $alut = !empty($data['detail_alut']) && is_array($data['detail_alut']) ? $data['detail_alut'] : ($renops['detail_alut'] ?: ($ops['detail_alut'] ?? []));
            $fullTextWa = TemplateHelper::generateRenopsWA($ops, $data, $srus, $ops['korban'], $unsur, $alut);

            $stmt = $db->prepare("UPDATE Renops SET
                hari_ke = :hari_ke,
                tanggal_laporan = :tanggal_laporan,
                waktu_briefing = :waktu_briefing,
                waktu_mulai_ops = :waktu_mulai_ops,
                cuaca = :cuaca,
                unsur_text = :unsur_text,
                alut_text = :alut_text,
                detail_unsur = :detail_unsur,
                detail_alut = :detail_alut,
                full_text_wa = :full_text_wa
                WHERE id_renops = :id");

            $stmt->execute([
                ':id' => $id,
                ':hari_ke' => strtoupper($data['hari_ke'] ?? $renops['hari_ke']),
                ':tanggal_laporan' => $data['tanggal_laporan'] ?? $renops['tanggal_laporan'],
                ':waktu_briefing' => $data['waktu_briefing'] ?? $renops['waktu_briefing'],
                ':waktu_mulai_ops' => $data['waktu_mulai_ops'] ?? $renops['waktu_mulai_ops'],
                ':cuaca' => $data['cuaca'] ?? $renops['cuaca'],
                ':unsur_text' => $data['unsur_text'] ?? $renops['unsur_text'],
                ':alut_text' => $data['alut_text'] ?? $renops['alut_text'],
                ':detail_unsur' => json_encode($unsur, JSON_UNESCAPED_UNICODE),
                ':detail_alut' => json_encode($alut, JSON_UNESCAPED_UNICODE),
                ':full_text_wa' => $fullTextWa
            ]);

            if (isset($data['srus'])) {
                $stmtExisting = $db->prepare("SELECT id_sru, nama_sru FROM RenopsSRU WHERE id_renops = :id ORDER BY id_sru ASC");
                $stmtExisting->execute([':id' => $id]);
                $existingRows = $stmtExisting->fetchAll();
                $existingIds = array_column($existingRows, 'id_sru');

                $stmtUpdate = $db->prepare("UPDATE RenopsSRU SET nama_sru = :nama, koordinat_area = :koor, luas_area = :luas, pola_pencarian = :pola, total_sapuan = :sapuan, track_spacing = :track WHERE id_sru = :sid AND id_renops = :rid");
                $stmtInsert = $db->prepare("INSERT INTO RenopsSRU (id_renops, nama_sru, koordinat_area, luas_area, pola_pencarian, total_sapuan, track_spacing) VALUES (:rid, :nama, :koor, :luas, :pola, :sapuan, :track)");

                $keptIds = [];
                $pool = $existingRows;

                foreach ($srus as $s) {
                    if (empty($s['nama_sru'])) continue;
                    $sId = !empty($s['id_sru']) ? (int)$s['id_sru'] : null;
                    $nama = trim((string)$s['nama_sru']);
                    $koor = $s['koordinat_area'] ?? '';
                    $luas = $s['luas_area'] ?? '';
                    $pola = $s['pola_pencarian'] ?? 'Parallel Track Search';
                    $sapuan = $s['total_sapuan'] ?? '';
                    $track = !empty($s['track_spacing']) ? $s['track_spacing'] : null;

                    if ($sId && in_array($sId, $existingIds, true) && !in_array($sId, $keptIds, true)) {
                        $stmtUpdate->execute([':sid' => $sId, ':rid' => $id, ':nama' => $nama, ':koor' => $koor, ':luas' => $luas, ':pola' => $pola, ':sapuan' => $sapuan, ':track' => $track]);
                        $keptIds[] = $sId;
                        foreach ($pool as $pIdx => $pItem) {
                            if ((int)$pItem['id_sru'] === $sId) {
                                unset($pool[$pIdx]);
                                break;
                            }
                        }
                    } elseif (!empty($pool)) {
                        $matchKey = null;
                        foreach ($pool as $pIdx => $pItem) {
                            if (strcasecmp($pItem['nama_sru'], $nama) === 0) {
                                $matchKey = $pIdx;
                                break;
                            }
                        }
                        if ($matchKey === null) {
                            $matchKey = array_key_first($pool);
                        }
                        $reusedId = (int)$pool[$matchKey]['id_sru'];
                        unset($pool[$matchKey]);

                        $stmtUpdate->execute([':sid' => $reusedId, ':rid' => $id, ':nama' => $nama, ':koor' => $koor, ':luas' => $luas, ':pola' => $pola, ':sapuan' => $sapuan, ':track' => $track]);
                        $keptIds[] = $reusedId;
                    } else {
                        $stmtInsert->execute([':rid' => $id, ':nama' => $nama, ':koor' => $koor, ':luas' => $luas, ':pola' => $pola, ':sapuan' => $sapuan, ':track' => $track]);
                    }
                }

                $toDelete = array_diff($existingIds, $keptIds);
                if (!empty($toDelete)) {
                    $delPlaceholders = implode(',', array_fill(0, count($toDelete), '?'));
                    $stmtDel = $db->prepare("DELETE FROM RenopsSRU WHERE id_sru IN ({$delPlaceholders}) AND id_renops = ?");
                    $stmtDel->execute([...$toDelete, $id]);
                }
            }

            // Sync operational state
            OperasiModel::syncUnsurAlut((int)$renops['id_operasi'], $unsur, $alut);

            if ($db->inTransaction()) {
                $db->commit();
            }
            if (!empty($toDelete)) {
                self::syncAutoIncrement('RenopsSRU');
            }
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function delete(int $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM RenopsSRU WHERE id_renops = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM Renops WHERE id_renops = :id")->execute([':id' => $id]);
            if ($db->inTransaction()) {
                $db->commit();
            }
            self::syncAutoIncrement();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Synchronize and reset Renops & RenopsSRU AUTO_INCREMENT to strictly MAX(id) + 1.
     * If $table is specified ('Renops' or 'RenopsSRU'), only that table will be checked and updated.
     */
    public static function syncAutoIncrement(?string $table = null): void {
        $db = getDB();
        if ($db->inTransaction()) {
            return;
        }
        try {
            if ($table === null || $table === 'Renops') {
                $stmtRen = $db->query("SELECT COALESCE(MAX(id_renops), 0) FROM Renops");
                $maxRenops = (int)$stmtRen->fetchColumn();
                $stmtRen->closeCursor();
                $nextRenops = max(1, $maxRenops + 1);
                $db->exec("ALTER TABLE Renops AUTO_INCREMENT = {$nextRenops}");
            }

            if ($table === null || $table === 'RenopsSRU') {
                $stmtSru = $db->query("SELECT COALESCE(MAX(id_sru), 0) FROM RenopsSRU");
                $maxSru = (int)$stmtSru->fetchColumn();
                $stmtSru->closeCursor();
                $nextSru = max(1, $maxSru + 1);
                $db->exec("ALTER TABLE RenopsSRU AUTO_INCREMENT = {$nextSru}");
            }
        } catch (Throwable) {
            // Silently continue
        }
    }
}
