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

            if (class_exists('TemplateHelper')) {
                $row['nomor_laporan'] = TemplateHelper::generateNomorSitrep(['id_operasi' => $row['id_operasi']], $row);
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

        if (class_exists('TemplateHelper')) {
            $db = getDB();
            $stmtOps = $db->prepare("SELECT id_operasi, waktu_berangkat, created_at FROM Operasi WHERE id_operasi = :id LIMIT 1");
            $stmtOps->execute([':id' => (int)$row['id_operasi']]);
            $ops = $stmtOps->fetch(PDO::FETCH_ASSOC) ?: ['id_operasi' => $row['id_operasi']];
            $row['nomor_laporan'] = TemplateHelper::generateNomorSitrep($ops, $row);
        }

        return $row;
    }

    /**
     * Snapshot unsur & alut SITREP: input form, lalu SITREP tersimpan (mode edit), lalu data operasi.
     */
    public static function resolveUnsurAlut(array $ops, array $data, ?array $existing = null): array {
        $unsur = !empty($data['detail_unsur']) && is_array($data['detail_unsur']) ? $data['detail_unsur'] : (($existing['detail_unsur'] ?? null) ?: ($ops['detail_unsur'] ?? []));
        $alut = !empty($data['detail_alut']) && is_array($data['detail_alut']) ? $data['detail_alut'] : (($existing['detail_alut'] ?? null) ?: ($ops['detail_alut'] ?? []));
        return [$unsur, $alut];
    }

    /**
     * Pratinjau naskah WA SITREP/Penutupan dari isi form (tanpa menyimpan), generator sama dengan create/update.
     * Status korban dari form (update_korban) diterapkan di memori saja, sama seperti sebelum WA dibuat saat simpan.
     */
    public static function buildPreviewWa(array $ops, array $data, ?array $existing = null): string {
        $statusBaru = is_array($data['update_korban'] ?? null) ? $data['update_korban'] : [];
        foreach ($ops['korban'] ?? [] as $i => $k) {
            if (isset($statusBaru[$k['id_korban']])) {
                $ops['korban'][$i]['status_korban'] = strtoupper(trim((string)$statusBaru[$k['id_korban']]));
            }
        }
        [$unsur, $alut] = self::resolveUnsurAlut($ops, $data, $existing);
        $isPenutupan = strtoupper($data['jenis_laporan'] ?? ($existing['jenis_laporan'] ?? '')) === 'PENUTUPAN';
        return TemplateHelper::generateLapbangWA($ops, $data, $ops['korban'] ?? [], $unsur, $alut, $isPenutupan);
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

            // ISSUE-LAPBANG-01: Update status korban SEBELUM naskah WA digenerate agar WA memuat status terbaru
            if (!empty($data['update_korban']) && is_array($data['update_korban'])) {
                $stmtK = $db->prepare("UPDATE Korban SET status_korban = :st WHERE id_korban = :kid AND id_operasi = :oid");
                foreach ($data['update_korban'] as $kid => $st) {
                    $stUpper = strtoupper(trim((string)$st));
                    $stmtK->execute([
                        ':st' => $stUpper,
                        ':kid' => (int)$kid,
                        ':oid' => (int)$data['id_operasi']
                    ]);
                    // Update array memori $ops['korban']
                    if (!empty($ops['korban']) && is_array($ops['korban'])) {
                        foreach ($ops['korban'] as &$k) {
                            if ((int)$k['id_korban'] === (int)$kid) {
                                $k['status_korban'] = $stUpper;
                            }
                        }
                        unset($k);
                    }
                }
            }

            $isPenutupan = strtoupper($data['jenis_laporan'] ?? '') === 'PENUTUPAN';
            [$unsur, $alut] = self::resolveUnsurAlut($ops, $data);
            
            // ISSUE-RENOPS-03: Format teks Unsur & Alut secara SSOT dari array snapshot
            $unsurText = OperasiModel::formatUnsurText($unsur, $data['unsur_text'] ?? ($ops['unsur_terlibat'] ?? ''));
            $alutText = OperasiModel::formatAlutText($alut, $data['alut_text'] ?? ($ops['alut_palsar'] ?? ''));

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
            // ISSUE-RENOPS-04: Normalisasi format waktu DATETIME
            $waktuDebrief = !empty($data['waktu_debrief'])
                ? OperasiModel::normalizeDateTime($data['waktu_debrief'], true)
                : ($isPenutupan ? $now : null);
            $waktuLaporan = OperasiModel::normalizeDateTime($data['waktu_laporan'] ?? $now);

            $rawHari = trim((string)($data['hari_ke'] ?? 'H.1'));
            $hariKe = is_numeric($rawHari) ? "H.{$rawHari}" : strtoupper($rawHari);

            $stmt->execute([
                ':id_operasi' => (int)$data['id_operasi'],
                ':hari_ke' => $hariKe,
                ':jenis_laporan' => $isPenutupan ? 'PENUTUPAN' : 'PERKEMBANGAN',
                ':isi_laporan' => $data['isi_laporan'] ?? '',
                ':waktu_debrief' => $waktuDebrief,
                ':cuaca' => $data['cuaca'] ?? ($ops['cuaca'] ?? ''),
                ':unsur_text' => $unsurText,
                ':alut_text' => $alutText,
                ':detail_unsur' => json_encode($unsur, JSON_UNESCAPED_UNICODE),
                ':detail_alut' => json_encode($alut, JSON_UNESCAPED_UNICODE),
                ':full_text_wa' => $fullTextWa,
                ':waktu_laporan' => $waktuLaporan
            ]);

            $idLaporan = (int)$db->lastInsertId();

            // Sync latest operational Unsur & Alut state to master tables
            OperasiModel::syncUnsurAlut((int)$data['id_operasi'], $unsur, $alut);

            // If this is a closing report, automatically close the operation
            if ($isPenutupan) {
                $waktuSelesai = $waktuDebrief ?? $now;
                OperasiModel::closeOperation((int)$data['id_operasi'], $waktuSelesai);
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
            $isExistingPenutupan = ($lap['jenis_laporan'] === 'PENUTUPAN');
            $isPenutupan = strtoupper($data['jenis_laporan'] ?? $lap['jenis_laporan']) === 'PENUTUPAN';

            // ISSUE-LAPBANG-02: Jika operasi sudah CLOSED, izinkan edit hanya jika yang diedit adalah laporan PENUTUPAN itu sendiri
            if ($ops && $ops['status'] === 'CLOSED' && !$isExistingPenutupan) {
                throw new RuntimeException("Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan tidak dapat diubah kecuali operasi dibuka kembali.");
            }

            // ISSUE-LAPBANG-01: Update status korban SEBELUM naskah WA digenerate
            if (!empty($data['update_korban']) && is_array($data['update_korban'])) {
                $stmtK = $db->prepare("UPDATE Korban SET status_korban = :st WHERE id_korban = :kid AND id_operasi = :oid");
                foreach ($data['update_korban'] as $kid => $st) {
                    $stUpper = strtoupper(trim((string)$st));
                    $stmtK->execute([
                        ':st' => $stUpper,
                        ':kid' => (int)$kid,
                        ':oid' => (int)$lap['id_operasi']
                    ]);
                    // Update array memori $ops['korban']
                    if (!empty($ops['korban']) && is_array($ops['korban'])) {
                        foreach ($ops['korban'] as &$k) {
                            if ((int)$k['id_korban'] === (int)$kid) {
                                $k['status_korban'] = $stUpper;
                            }
                        }
                        unset($k);
                    }
                }
            }

            [$unsur, $alut] = self::resolveUnsurAlut($ops, $data, $lap);

            // ISSUE-RENOPS-03: Format teks Unsur & Alut secara SSOT dari array snapshot
            $unsurText = OperasiModel::formatUnsurText($unsur, $data['unsur_text'] ?? ($lap['unsur_text'] ?: ($ops['unsur_terlibat'] ?? '')));
            $alutText = OperasiModel::formatAlutText($alut, $data['alut_text'] ?? ($lap['alut_text'] ?: ($ops['alut_palsar'] ?? '')));

            $fullTextWa = TemplateHelper::generateLapbangWA($ops, $data, $ops['korban'], $unsur, $alut, $isPenutupan);

            // ISSUE-RENOPS-04: Normalisasi format waktu DATETIME
            $waktuDebrief = !empty($data['waktu_debrief'])
                ? OperasiModel::normalizeDateTime($data['waktu_debrief'], true)
                : (!empty($lap['waktu_debrief']) ? OperasiModel::normalizeDateTime($lap['waktu_debrief'], true) : ($isPenutupan ? date('Y-m-d H:i:s') : null));
            $waktuLaporan = OperasiModel::normalizeDateTime($data['waktu_laporan'] ?? $lap['waktu_laporan']);

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

            $rawHari = trim((string)($data['hari_ke'] ?? $lap['hari_ke']));
            $hariKe = is_numeric($rawHari) ? "H.{$rawHari}" : strtoupper($rawHari);

            $stmt->execute([
                ':id' => $id,
                ':hari_ke' => $hariKe,
                ':jenis_laporan' => $isPenutupan ? 'PENUTUPAN' : 'PERKEMBANGAN',
                ':isi_laporan' => $data['isi_laporan'] ?? $lap['isi_laporan'],
                ':waktu_debrief' => $waktuDebrief,
                ':cuaca' => $data['cuaca'] ?? ($lap['cuaca'] ?: ($ops['cuaca'] ?? '')),
                ':unsur_text' => $unsurText,
                ':alut_text' => $alutText,
                ':detail_unsur' => json_encode($unsur, JSON_UNESCAPED_UNICODE),
                ':detail_alut' => json_encode($alut, JSON_UNESCAPED_UNICODE),
                ':full_text_wa' => $fullTextWa,
                ':waktu_laporan' => $waktuLaporan
            ]);

            // Sync operational state
            OperasiModel::syncUnsurAlut((int)$lap['id_operasi'], $unsur, $alut);

            if ($isPenutupan) {
                OperasiModel::closeOperation((int)$lap['id_operasi'], $waktuDebrief ?? date('Y-m-d H:i:s'));
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
        $ok = $stmt->execute([':id' => $id]);
        DocVerifyHelper::forgetDocTokens([DocVerifyHelper::TYPE_SITREP, DocVerifyHelper::TYPE_PENUTUPAN], $id);
        if (class_exists('DokumenPetaSnapshotModel')) {
            DokumenPetaSnapshotModel::deleteByReferensi('SITREP', $id);
            DokumenPetaSnapshotModel::deleteByReferensi('PENUTUPAN', $id);
        }
        return $ok;
    }
}
