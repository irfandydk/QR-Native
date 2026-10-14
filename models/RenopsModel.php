<?php
/**
 * QR-SAR - Rencana Operasi (RENOPS) Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class RenopsModel {
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
                $stmtSru = $db->prepare("INSERT INTO RenopsSRU (id_renops, nama_sru, koordinat_area, luas_area, pola_pencarian) VALUES (:rid, :nama, :koor, :luas, :pola)");
                foreach ($srus as $s) {
                    if (empty($s['nama_sru'])) continue;
                    $stmtSru->execute([
                        ':rid' => $idRenops,
                        ':nama' => $s['nama_sru'],
                        ':koor' => $s['koordinat_area'] ?? '',
                        ':luas' => $s['luas_area'] ?? '',
                        ':pola' => $s['pola_pencarian'] ?? 'Parallel Track Search'
                    ]);
                }
            }

            // Sync latest operational Unsur & Alut state to master tables
            OperasiModel::syncUnsurAlut((int)$data['id_operasi'], $unsur, $alut);

            $db->commit();
            return $idRenops;
        } catch (Throwable $e) {
            $db->rollBack();
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
                $db->prepare("DELETE FROM RenopsSRU WHERE id_renops = :id")->execute([':id' => $id]);
                $stmtSru = $db->prepare("INSERT INTO RenopsSRU (id_renops, nama_sru, koordinat_area, luas_area, pola_pencarian) VALUES (:rid, :nama, :koor, :luas, :pola)");
                foreach ($srus as $s) {
                    if (empty($s['nama_sru'])) continue;
                    $stmtSru->execute([
                        ':rid' => $id,
                        ':nama' => $s['nama_sru'],
                        ':koor' => $s['koordinat_area'] ?? '',
                        ':luas' => $s['luas_area'] ?? '',
                        ':pola' => $s['pola_pencarian'] ?? 'Parallel Track Search'
                    ]);
                }
            }

            // Sync operational state
            OperasiModel::syncUnsurAlut((int)$renops['id_operasi'], $unsur, $alut);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM RenopsSRU WHERE id_renops = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM Renops WHERE id_renops = :id")->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            return false;
        }
    }
}
