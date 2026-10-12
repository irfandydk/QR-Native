<?php
/**
 * QR-SAR - Laporan Perkembangan & Sitrep Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class LaporanModel {
    public static function getByOperasiId(int $idOperasi): array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM LaporanHarian WHERE id_operasi = :id ORDER BY id_laporan ASC");
        $stmt->execute([':id' => $idOperasi]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            if (!empty($row['detail_unsur']) && is_string($row['detail_unsur'])) {
                $decoded = json_decode($row['detail_unsur'], true);
                $row['detail_unsur'] = is_array($decoded) ? $decoded : [];
            } else {
                $row['detail_unsur'] = [];
            }

            if (!empty($row['detail_alut']) && is_string($row['detail_alut'])) {
                $decoded = json_decode($row['detail_alut'], true);
                $row['detail_alut'] = is_array($decoded) ? $decoded : [];
            } else {
                $row['detail_alut'] = [];
            }
        }

        return $rows;
    }

    public static function getById(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM LaporanHarian WHERE id_laporan = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        if (!empty($row['detail_unsur']) && is_string($row['detail_unsur'])) {
            $decoded = json_decode($row['detail_unsur'], true);
            $row['detail_unsur'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['detail_unsur'] = [];
        }

        if (!empty($row['detail_alut']) && is_string($row['detail_alut'])) {
            $decoded = json_decode($row['detail_alut'], true);
            $row['detail_alut'] = is_array($decoded) ? $decoded : [];
        } else {
            $row['detail_alut'] = [];
        }

        return $row;
    }

    public static function create(array $data): int {
        $db = getDB();
        $db->beginTransaction();

        try {
            $ops = OperasiModel::getById((int)$data['id_operasi']);
            if (!$ops) throw new RuntimeException("Operasi tidak ditemukan");
            if ($ops['status'] === 'CLOSED') {
                throw new RuntimeException("Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan baru tidak dapat ditambahkan kecuali operasi dibuka kembali.");
            }

            $isPenutupan = strtoupper($data['jenis_laporan'] ?? '') === 'PENUTUPAN';
            $unsur = !empty($data['detail_unsur']) && is_array($data['detail_unsur']) ? $data['detail_unsur'] : ($ops['detail_unsur'] ?? []);
            $alut = !empty($data['detail_alut']) && is_array($data['detail_alut']) ? $data['detail_alut'] : ($ops['detail_alut'] ?? []);
            $fullTextWa = TemplateHelper::generateLapbangWA($ops, $data, $ops['korban'], $unsur, $alut, $isPenutupan);

            $stmt = $db->prepare("INSERT INTO LaporanHarian (
                id_operasi, hari_ke, jenis_laporan, isi_laporan,
                waktu_debrief, cuaca, unsur_text, alut_text,
                detail_unsur, detail_alut,
                full_text_wa, waktu_laporan
            ) VALUES (
                :id_operasi, :hari_ke, :jenis_laporan, :isi_laporan,
                :waktu_debrief, :cuaca, :unsur_text, :alut_text,
                :detail_unsur, :detail_alut,
                :full_text_wa, :waktu_laporan
            )");

            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                ':id_operasi' => (int)$data['id_operasi'],
                ':hari_ke' => strtoupper($data['hari_ke'] ?? 'H.1'),
                ':jenis_laporan' => $isPenutupan ? 'PENUTUPAN' : 'PERKEMBANGAN',
                ':isi_laporan' => $data['isi_laporan'] ?? '',
                ':waktu_debrief' => $data['waktu_debrief'] ?? ($isPenutupan ? $now : null),
                ':cuaca' => $data['cuaca'] ?? ($ops['cuaca'] ?: 'Cerah Berawan'),
                ':unsur_text' => $data['unsur_text'] ?? ($ops['unsur_terlibat'] ?? ''),
                ':alut_text' => $data['alut_text'] ?? ($ops['alut_palsar'] ?? ''),
                ':detail_unsur' => json_encode($unsur, JSON_UNESCAPED_UNICODE),
                ':detail_alut' => json_encode($alut, JSON_UNESCAPED_UNICODE),
                ':full_text_wa' => $fullTextWa,
                ':waktu_laporan' => $data['waktu_laporan'] ?? $now
            ]);

            $idLaporan = (int)$db->lastInsertId();

            // Sync latest operational Unsur & Alut state to master tables
            OperasiModel::syncUnsurAlut((int)$data['id_operasi'], $unsur, $alut);

            // If this is a closing report, automatically close the operation
            if ($isPenutupan) {
                $waktuSelesai = $data['waktu_debrief'] ?? $now;
                OperasiModel::closeOperation((int)$data['id_operasi'], $waktuSelesai);
            }

            // If victim updates were submitted, update victim records
            if (!empty($data['update_korban']) && is_array($data['update_korban'])) {
                $stmtK = $db->prepare("UPDATE Korban SET status_korban = :st WHERE id_korban = :kid AND id_operasi = :oid");
                foreach ($data['update_korban'] as $kid => $st) {
                    $stmtK->execute([
                        ':st' => strtoupper($st),
                        ':kid' => (int)$kid,
                        ':oid' => (int)$data['id_operasi']
                    ]);
                }
            }

            $db->commit();
            return $idLaporan;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool {
        $db = getDB();
        $db->beginTransaction();

        try {
            $lap = self::getById($id);
            if (!$lap) return false;

            $ops = OperasiModel::getById((int)$lap['id_operasi']);
            if ($ops && $ops['status'] === 'CLOSED') {
                throw new RuntimeException("Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan tidak dapat diubah kecuali operasi dibuka kembali.");
            }
            $isPenutupan = strtoupper($data['jenis_laporan'] ?? $lap['jenis_laporan']) === 'PENUTUPAN';
            $unsur = !empty($data['detail_unsur']) && is_array($data['detail_unsur']) ? $data['detail_unsur'] : ($lap['detail_unsur'] ?: ($ops['detail_unsur'] ?? []));
            $alut = !empty($data['detail_alut']) && is_array($data['detail_alut']) ? $data['detail_alut'] : ($lap['detail_alut'] ?: ($ops['detail_alut'] ?? []));
            $fullTextWa = TemplateHelper::generateLapbangWA($ops, $data, $ops['korban'], $unsur, $alut, $isPenutupan);

            $stmt = $db->prepare("UPDATE LaporanHarian SET
                hari_ke = :hari_ke,
                jenis_laporan = :jenis_laporan,
                isi_laporan = :isi_laporan,
                waktu_debrief = :waktu_debrief,
                cuaca = :cuaca,
                unsur_text = :unsur_text,
                alut_text = :alut_text,
                detail_unsur = :detail_unsur,
                detail_alut = :detail_alut,
                full_text_wa = :full_text_wa,
                waktu_laporan = :waktu_laporan
                WHERE id_laporan = :id");

            $stmt->execute([
                ':id' => $id,
                ':hari_ke' => strtoupper($data['hari_ke'] ?? $lap['hari_ke']),
                ':jenis_laporan' => $isPenutupan ? 'PENUTUPAN' : 'PERKEMBANGAN',
                ':isi_laporan' => $data['isi_laporan'] ?? $lap['isi_laporan'],
                ':waktu_debrief' => $data['waktu_debrief'] ?? $lap['waktu_debrief'],
                ':cuaca' => $data['cuaca'] ?? ($lap['cuaca'] ?: ($ops['cuaca'] ?: 'Cerah Berawan')),
                ':unsur_text' => $data['unsur_text'] ?? $lap['unsur_text'],
                ':alut_text' => $data['alut_text'] ?? $lap['alut_text'],
                ':detail_unsur' => json_encode($unsur, JSON_UNESCAPED_UNICODE),
                ':detail_alut' => json_encode($alut, JSON_UNESCAPED_UNICODE),
                ':full_text_wa' => $fullTextWa,
                ':waktu_laporan' => $data['waktu_laporan'] ?? $lap['waktu_laporan']
            ]);

            // Sync operational state
            OperasiModel::syncUnsurAlut((int)$lap['id_operasi'], $unsur, $alut);

            if ($isPenutupan) {
                OperasiModel::closeOperation((int)$lap['id_operasi'], $data['waktu_debrief'] ?? date('Y-m-d H:i:s'));
            }

            // Update victim records if present
            if (!empty($data['update_korban']) && is_array($data['update_korban'])) {
                $stmtK = $db->prepare("UPDATE Korban SET status_korban = :st WHERE id_korban = :kid AND id_operasi = :oid");
                foreach ($data['update_korban'] as $kid => $st) {
                    $stmtK->execute([
                        ':st' => strtoupper($st),
                        ':kid' => (int)$kid,
                        ':oid' => (int)$lap['id_operasi']
                    ]);
                }
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function delete(int $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM LaporanHarian WHERE id_laporan = :id");
        return $stmt->execute([':id' => $id]);
    }
}
