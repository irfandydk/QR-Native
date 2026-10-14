<?php
/**
 * QR-SAR - Operasi SAR Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class OperasiModel {
    public static function getAll(?string $status = null, ?string $search = null, ?int $limit = null, int $offset = 0): array {
        $db = getDB();
        $sql = "SELECT o.*, 
            (SELECT COUNT(*) FROM Korban k WHERE k.id_operasi = o.id_operasi) as total_korban_count,
            (SELECT COUNT(*) FROM Korban k WHERE k.id_operasi = o.id_operasi AND k.status_korban = 'S') as korban_s,
            (SELECT COUNT(*) FROM Korban k WHERE k.id_operasi = o.id_operasi AND k.status_korban = 'MD') as korban_md,
            (SELECT COUNT(*) FROM Korban k WHERE k.id_operasi = o.id_operasi AND k.status_korban = 'DP') as korban_dp,
            (SELECT COUNT(*) FROM Korban k WHERE k.id_operasi = o.id_operasi AND k.status_korban = 'H') as korban_h,
            (SELECT COUNT(*) FROM Renops r WHERE r.id_operasi = o.id_operasi) as total_renops,
            (SELECT COUNT(*) FROM LaporanHarian l WHERE l.id_operasi = o.id_operasi) as total_laporan,
            (SELECT COUNT(*) FROM BraSar b WHERE b.id_operasi = o.id_operasi) as total_bra
            FROM Operasi o WHERE 1=1";
        
        $params = [];
        if ($status && in_array(strtoupper($status), ['OPEN', 'CLOSED'])) {
            $sql .= " AND o.status = :status";
            $params[':status'] = strtoupper($status);
        }

        if (!empty($search)) {
            $sql .= " AND (o.nama_operasi LIKE :q1 OR o.lokasi_kejadian LIKE :q2 OR o.nama_pelapor LIKE :q3 OR o.koordinat LIKE :q4)";
            $q = "%{$search}%";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }

        $sql .= " ORDER BY o.id_operasi DESC";

        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit . " OFFSET " . max(0, (int)$offset);
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function getListSimple(?string $status = null): array {
        $db = getDB();
        $sql = "SELECT id_operasi, nama_operasi, status, lokasi_kejadian, koordinat, waktu_kejadian, created_at FROM Operasi";
        $params = [];
        if ($status && in_array(strtoupper($status), ['OPEN', 'CLOSED'], true)) {
            $sql .= " WHERE status = :status";
            $params[':status'] = strtoupper($status);
        }
        $sql .= " ORDER BY id_operasi DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function countAll(?string $status = null, ?string $search = null): int {
        $db = getDB();
        $sql = "SELECT COUNT(*) FROM Operasi o WHERE 1=1";
        $params = [];
        if ($status && in_array(strtoupper($status), ['OPEN', 'CLOSED'])) {
            $sql .= " AND o.status = :status";
            $params[':status'] = strtoupper($status);
        }
        if (!empty($search)) {
            $sql .= " AND (o.nama_operasi LIKE :q1 OR o.lokasi_kejadian LIKE :q2 OR o.nama_pelapor LIKE :q3 OR o.koordinat LIKE :q4)";
            $q = "%{$search}%";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public static function getById(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM Operasi WHERE id_operasi = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $ops = $stmt->fetch();
        if (!$ops) return null;

        // Fetch children
        $stmtK = $db->prepare("SELECT * FROM Korban WHERE id_operasi = :id ORDER BY id_korban ASC");
        $stmtK->execute([':id' => $id]);
        $ops['korban'] = $stmtK->fetchAll();

        $stmtU = $db->prepare("SELECT * FROM DetailUnsur WHERE id_operasi = :id ORDER BY id_detail_unsur ASC");
        $stmtU->execute([':id' => $id]);
        $ops['detail_unsur'] = $stmtU->fetchAll();

        $stmtA = $db->prepare("SELECT * FROM DetailAlut WHERE id_operasi = :id ORDER BY id_detail_alut ASC");
        $stmtA->execute([':id' => $id]);
        $ops['detail_alut'] = $stmtA->fetchAll();

        $stmtT = $db->prepare("SELECT * FROM DetailTimBerangkat WHERE id_operasi = :id ORDER BY id_detail_tim ASC");
        $stmtT->execute([':id' => $id]);
        $ops['detail_tim_berangkat'] = $stmtT->fetchAll();

        // Renops with SRUs & snapshot decoding
        $ops['renops'] = RenopsModel::getByOperasiId($id);

        // Laporan harian with snapshot decoding
        $ops['laporan_harian'] = LaporanModel::getByOperasiId($id);

        // Linked BRA SAR (Optimized batched section loading)
        $stmtB = $db->prepare("SELECT * FROM BraSar WHERE id_operasi = :id ORDER BY id_bra DESC");
        $stmtB->execute([':id' => $id]);
        $bras = $stmtB->fetchAll();
        if (!empty($bras)) {
            $braIds = array_column($bras, 'id_bra');
            $placeholders = implode(',', array_fill(0, count($braIds), '?'));
            $stmtSec = $db->prepare("SELECT id_bra, kode, isi FROM BraSection WHERE id_bra IN ({$placeholders}) ORDER BY id ASC");
            $stmtSec->execute($braIds);
            $allSecs = $stmtSec->fetchAll();

            $secsByBra = [];
            foreach ($allSecs as $s) {
                $secsByBra[$s['id_bra']][] = [
                    'kode' => $s['kode'],
                    'isi' => $s['isi']
                ];
            }

            foreach ($bras as &$braItem) {
                $braItem['sections'] = $secsByBra[$braItem['id_bra']] ?? [];
            }
        }
        $ops['bra_sar'] = $bras;

        // Calculate stats
        $ops['stats_korban'] = TemplateHelper::hitungStatistikKorban($ops['korban']);

        return $ops;
    }

    public static function getStats(): array {
        $db = getDB();
        $rowOps = $db->query("
            SELECT 
                COUNT(*) as total_operasi,
                COALESCE(SUM(CASE WHEN status = 'OPEN' THEN 1 ELSE 0 END), 0) as operasi_aktif,
                COALESCE(SUM(CASE WHEN status = 'CLOSED' THEN 1 ELSE 0 END), 0) as operasi_selesai,
                (SELECT COUNT(*) FROM BraSar) as total_bra
            FROM Operasi
        ")->fetch() ?: [];

        $rowKorban = $db->query("
            SELECT 
                COUNT(*) as total_korban,
                COALESCE(SUM(CASE WHEN status_korban = 'S' THEN 1 ELSE 0 END), 0) as korban_selamat,
                COALESCE(SUM(CASE WHEN status_korban = 'MD' THEN 1 ELSE 0 END), 0) as korban_meninggal,
                COALESCE(SUM(CASE WHEN status_korban = 'DP' THEN 1 ELSE 0 END), 0) as korban_dalam_pencarian,
                COALESCE(SUM(CASE WHEN status_korban = 'H' THEN 1 ELSE 0 END), 0) as korban_hilang
            FROM Korban
        ")->fetch() ?: [];

        return [
            'total_operasi' => (int)($rowOps['total_operasi'] ?? 0),
            'operasi_aktif' => (int)($rowOps['operasi_aktif'] ?? 0),
            'operasi_selesai' => (int)($rowOps['operasi_selesai'] ?? 0),
            'total_korban' => (int)($rowKorban['total_korban'] ?? 0),
            'korban_selamat' => (int)($rowKorban['korban_selamat'] ?? 0),
            'korban_meninggal' => (int)($rowKorban['korban_meninggal'] ?? 0),
            'korban_dalam_pencarian' => (int)($rowKorban['korban_dalam_pencarian'] ?? 0),
            'korban_hilang' => (int)($rowKorban['korban_hilang'] ?? 0),
            'total_bra' => (int)($rowOps['total_bra'] ?? 0)
        ];
    }

    public static function create(array $data): int {
        $db = getDB();
        $db->beginTransaction();

        try {
            // Build full_text_wa
            $korbanData = $data['korban'] ?? [];
            $unsurData = $data['detail_unsur'] ?? [];
            $alutData = $data['detail_alut'] ?? [];

            $fullTextWa = TemplateHelper::generateLaporanAwalWA($data, $korbanData, $unsurData, $alutData);

            $stmt = $db->prepare("INSERT INTO Operasi (
                nama_operasi, nama_pelapor, hubungan_pelapor, no_telp_pelapor,
                waktu_kejadian, waktu_lapor, waktu_berangkat, tim_berangkat, eta_tiba,
                lokasi_kejadian, koordinat, jarak, radial, eta_lokasi, kronologi, jumlah_pob,
                unsur_terlibat, alut_palsar, faktor_penghambat, cuaca, gelombang, angin,
                status, full_text_wa, created_at
            ) VALUES (
                :nama_operasi, :nama_pelapor, :hubungan_pelapor, :no_telp_pelapor,
                :waktu_kejadian, :waktu_lapor, :waktu_berangkat, :tim_berangkat, :eta_tiba,
                :lokasi_kejadian, :koordinat, :jarak, :radial, :eta_lokasi, :kronologi, :jumlah_pob,
                :unsur_terlibat, :alut_palsar, :faktor_penghambat, :cuaca, :gelombang, :angin,
                'OPEN', :full_text_wa, :created_at
            )");

            $now = date('Y-m-d H:i:s');
            $stmt->execute([
                ':nama_operasi' => $data['nama_operasi'],
                ':nama_pelapor' => $data['nama_pelapor'] ?? '',
                ':hubungan_pelapor' => $data['hubungan_pelapor'] ?? '',
                ':no_telp_pelapor' => $data['no_telp_pelapor'] ?? '',
                ':waktu_kejadian' => $data['waktu_kejadian'] ?? $now,
                ':waktu_lapor' => $data['waktu_lapor'] ?? $now,
                ':waktu_berangkat' => $data['waktu_berangkat'] ?? $now,
                ':tim_berangkat' => $data['tim_berangkat'] ?? 'Tim Rescue Kansar Kendari',
                ':eta_tiba' => $data['eta_tiba'] ?? $now,
                ':lokasi_kejadian' => $data['lokasi_kejadian'] ?? '',
                ':koordinat' => $data['koordinat'] ?? '',
                ':jarak' => $data['jarak'] ?? '',
                ':radial' => $data['radial'] ?? '',
                ':eta_lokasi' => $data['eta_lokasi'] ?? '',
                ':kronologi' => $data['kronologi'] ?? '',
                ':jumlah_pob' => (int)($data['jumlah_pob'] ?? count($korbanData)),
                ':unsur_terlibat' => $data['unsur_terlibat'] ?? '',
                ':alut_palsar' => $data['alut_palsar'] ?? '',
                ':faktor_penghambat' => $data['faktor_penghambat'] ?? 'Nihil',
                ':cuaca' => $data['cuaca'] ?? 'Cerah Berawan',
                ':gelombang' => $data['gelombang'] ?? '0.5 - 1.25 M',
                ':angin' => $data['angin'] ?? 'Barat Daya - Barat Laut, 5 - 15 Knots',
                ':full_text_wa' => $fullTextWa,
                ':created_at' => $now
            ]);

            $idOperasi = (int)$db->lastInsertId();

            // Insert Korban
            if (!empty($korbanData)) {
                $stmtK = $db->prepare("INSERT INTO Korban (id_operasi, nama_korban, umur, jk, alamat, status_korban) VALUES (:id_ops, :nama, :umur, :jk, :alamat, :status)");
                foreach ($korbanData as $k) {
                    if (empty($k['nama_korban'])) continue;
                    $stmtK->execute([
                        ':id_ops' => $idOperasi,
                        ':nama' => $k['nama_korban'],
                        ':umur' => $k['umur'] ?? '',
                        ':jk' => $k['jk'] ?? 'Laki-laki',
                        ':alamat' => $k['alamat'] ?? '',
                        ':status' => $k['status_korban'] ?? 'DP'
                    ]);
                }
            }

            // Insert Detail Unsur
            if (!empty($unsurData)) {
                $stmtU = $db->prepare("INSERT INTO DetailUnsur (id_operasi, nama_unsur, jumlah_personil) VALUES (:id_ops, :nama, :jml)");
                foreach ($unsurData as $u) {
                    if (empty($u['nama_unsur'])) continue;
                    $stmtU->execute([
                        ':id_ops' => $idOperasi,
                        ':nama' => $u['nama_unsur'],
                        ':jml' => (int)($u['jumlah_personil'] ?? 1)
                    ]);
                }
            }

            // Insert Detail Alut
            if (!empty($alutData)) {
                $stmtA = $db->prepare("INSERT INTO DetailAlut (id_operasi, id_alut, nama_alut, satuan, jumlah_alut) VALUES (:id_ops, :id_alut, :nama, :sat, :jml)");
                foreach ($alutData as $a) {
                    if (empty($a['nama_alut'])) continue;
                    $stmtA->execute([
                        ':id_ops' => $idOperasi,
                        ':id_alut' => (int)($a['id_alut'] ?? 0),
                        ':nama' => $a['nama_alut'],
                        ':sat' => $a['satuan'] ?? 'Unit',
                        ':jml' => (int)($a['jumlah_alut'] ?? 1)
                    ]);
                }
            }

            // Insert Tim Berangkat
            if (!empty($data['detail_tim_berangkat'])) {
                $stmtT = $db->prepare("INSERT INTO DetailTimBerangkat (id_operasi, id_tim, nama_tim) VALUES (:id_ops, :id_tim, :nama)");
                foreach ($data['detail_tim_berangkat'] as $t) {
                    if (empty($t['nama_tim'])) continue;
                    $stmtT->execute([
                        ':id_ops' => $idOperasi,
                        ':id_tim' => (int)($t['id_tim'] ?? 0),
                        ':nama' => $t['nama_tim']
                    ]);
                }
            }

            $db->commit();

            if (class_exists('AuditHelper')) {
                AuditHelper::log('CREATE_OPERASI', "Operasi SAR dibuka: {$data['nama_operasi']}", 'OPERASI', "OPS-{$idOperasi}");
            }

            return $idOperasi;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool {
        $db = getDB();
        $db->beginTransaction();

        try {
            $ops = self::getById($id);
            if (!$ops) return false;

            $korbanData = $data['korban'] ?? $ops['korban'];
            $unsurData = $data['detail_unsur'] ?? $ops['detail_unsur'];
            $alutData = $data['detail_alut'] ?? $ops['detail_alut'];

            $mergedOps = array_merge($ops, $data);
            $fullTextWa = TemplateHelper::generateLaporanAwalWA($mergedOps, $korbanData, $unsurData, $alutData);

            $stmt = $db->prepare("UPDATE Operasi SET
                nama_operasi = :nama_operasi,
                nama_pelapor = :nama_pelapor,
                hubungan_pelapor = :hubungan_pelapor,
                no_telp_pelapor = :no_telp_pelapor,
                waktu_kejadian = :waktu_kejadian,
                waktu_lapor = :waktu_lapor,
                waktu_berangkat = :waktu_berangkat,
                tim_berangkat = :tim_berangkat,
                eta_tiba = :eta_tiba,
                lokasi_kejadian = :lokasi_kejadian,
                koordinat = :koordinat,
                jarak = :jarak,
                radial = :radial,
                eta_lokasi = :eta_lokasi,
                kronologi = :kronologi,
                jumlah_pob = :jumlah_pob,
                unsur_terlibat = :unsur_terlibat,
                alut_palsar = :alut_palsar,
                faktor_penghambat = :faktor_penghambat,
                cuaca = :cuaca,
                gelombang = :gelombang,
                angin = :angin,
                full_text_wa = :full_text_wa
                WHERE id_operasi = :id");

            $stmt->execute([
                ':id' => $id,
                ':nama_operasi' => $data['nama_operasi'] ?? $ops['nama_operasi'],
                ':nama_pelapor' => $data['nama_pelapor'] ?? $ops['nama_pelapor'],
                ':hubungan_pelapor' => $data['hubungan_pelapor'] ?? $ops['hubungan_pelapor'],
                ':no_telp_pelapor' => $data['no_telp_pelapor'] ?? $ops['no_telp_pelapor'],
                ':waktu_kejadian' => $data['waktu_kejadian'] ?? $ops['waktu_kejadian'],
                ':waktu_lapor' => $data['waktu_lapor'] ?? $ops['waktu_lapor'],
                ':waktu_berangkat' => $data['waktu_berangkat'] ?? $ops['waktu_berangkat'],
                ':tim_berangkat' => $data['tim_berangkat'] ?? $ops['tim_berangkat'],
                ':eta_tiba' => $data['eta_tiba'] ?? $ops['eta_tiba'],
                ':lokasi_kejadian' => $data['lokasi_kejadian'] ?? $ops['lokasi_kejadian'],
                ':koordinat' => $data['koordinat'] ?? $ops['koordinat'],
                ':jarak' => $data['jarak'] ?? $ops['jarak'],
                ':radial' => $data['radial'] ?? ($ops['radial'] ?? ''),
                ':eta_lokasi' => $data['eta_lokasi'] ?? $ops['eta_lokasi'],
                ':kronologi' => $data['kronologi'] ?? $ops['kronologi'],
                ':jumlah_pob' => isset($data['jumlah_pob']) ? (int)$data['jumlah_pob'] : (int)$ops['jumlah_pob'],
                ':unsur_terlibat' => $data['unsur_terlibat'] ?? $ops['unsur_terlibat'],
                ':alut_palsar' => $data['alut_palsar'] ?? $ops['alut_palsar'],
                ':faktor_penghambat' => $data['faktor_penghambat'] ?? $ops['faktor_penghambat'],
                ':cuaca' => $data['cuaca'] ?? $ops['cuaca'],
                ':gelombang' => $data['gelombang'] ?? $ops['gelombang'],
                ':angin' => $data['angin'] ?? $ops['angin'],
                ':full_text_wa' => $fullTextWa
            ]);

            // Replace korban if provided
            if (isset($data['korban'])) {
                $db->prepare("DELETE FROM Korban WHERE id_operasi = :id")->execute([':id' => $id]);
                $stmtK = $db->prepare("INSERT INTO Korban (id_operasi, nama_korban, umur, jk, alamat, status_korban) VALUES (:id_ops, :nama, :umur, :jk, :alamat, :status)");
                foreach ($data['korban'] as $k) {
                    if (empty($k['nama_korban'])) continue;
                    $stmtK->execute([
                        ':id_ops' => $id,
                        ':nama' => $k['nama_korban'],
                        ':umur' => $k['umur'] ?? '',
                        ':jk' => $k['jk'] ?? 'Laki-laki',
                        ':alamat' => $k['alamat'] ?? '',
                        ':status' => $k['status_korban'] ?? 'DP'
                    ]);
                }
            }

            // Replace unsur if provided
            if (isset($data['detail_unsur'])) {
                $db->prepare("DELETE FROM DetailUnsur WHERE id_operasi = :id")->execute([':id' => $id]);
                $stmtU = $db->prepare("INSERT INTO DetailUnsur (id_operasi, nama_unsur, jumlah_personil) VALUES (:id_ops, :nama, :jml)");
                foreach ($data['detail_unsur'] as $u) {
                    if (empty($u['nama_unsur'])) continue;
                    $stmtU->execute([
                        ':id_ops' => $id,
                        ':nama' => $u['nama_unsur'],
                        ':jml' => (int)($u['jumlah_personil'] ?? 1)
                    ]);
                }
            }

            // Replace alut if provided
            if (isset($data['detail_alut'])) {
                $db->prepare("DELETE FROM DetailAlut WHERE id_operasi = :id")->execute([':id' => $id]);
                $stmtA = $db->prepare("INSERT INTO DetailAlut (id_operasi, id_alut, nama_alut, satuan, jumlah_alut) VALUES (:id_ops, :id_alut, :nama, :sat, :jml)");
                foreach ($data['detail_alut'] as $a) {
                    if (empty($a['nama_alut'])) continue;
                    $stmtA->execute([
                        ':id_ops' => $id,
                        ':id_alut' => (int)($a['id_alut'] ?? 0),
                        ':nama' => $a['nama_alut'],
                        ':sat' => $a['satuan'] ?? 'Unit',
                        ':jml' => (int)($a['jumlah_alut'] ?? 1)
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
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            // Retrieve operation metadata for descriptive audit trail before deletion
            $stmtOps = $db->prepare("SELECT nama_operasi, waktu_kejadian, status FROM Operasi WHERE id_operasi = :id");
            $stmtOps->execute([':id' => $id]);
            $ops = $stmtOps->fetch();
            $namaOps = $ops['nama_operasi'] ?? "ID #{$id}";
            $statusOps = $ops['status'] ?? 'OPEN';

            // 1. Delete SRU from Renops belonging to this operation
            $stmtRenopsIds = $db->prepare("SELECT id_renops FROM Renops WHERE id_operasi = :id");
            $stmtRenopsIds->execute([':id' => $id]);
            $renopsIds = $stmtRenopsIds->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($renopsIds)) {
                $placeholders = implode(',', array_fill(0, count($renopsIds), '?'));
                $stmtDelSru = $db->prepare("DELETE FROM RenopsSRU WHERE id_renops IN ({$placeholders})");
                $stmtDelSru->execute($renopsIds);
            }

            // 2. Delete Renops
            $stmtDelRen = $db->prepare("DELETE FROM Renops WHERE id_operasi = :id");
            $stmtDelRen->execute([':id' => $id]);

            // 3. Delete Laporan Harian (SITREP)
            $stmtDelLap = $db->prepare("DELETE FROM LaporanHarian WHERE id_operasi = :id");
            $stmtDelLap->execute([':id' => $id]);

            // 4. Detach linked BRA SAR (set id_operasi = NULL to preserve archive telegram)
            $stmtDetachBra = $db->prepare("UPDATE BraSar SET id_operasi = NULL WHERE id_operasi = :id");
            $stmtDetachBra->execute([':id' => $id]);

            // 5. Delete DetailAlut, DetailUnsur, DetailTimBerangkat
            $db->prepare("DELETE FROM DetailAlut WHERE id_operasi = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM DetailUnsur WHERE id_operasi = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM DetailTimBerangkat WHERE id_operasi = :id")->execute([':id' => $id]);

            // 6. Delete Korban
            $db->prepare("DELETE FROM Korban WHERE id_operasi = :id")->execute([':id' => $id]);

            // 7. Finally delete the Operasi record
            $stmtDelOps = $db->prepare("DELETE FROM Operasi WHERE id_operasi = :id");
            $result = $stmtDelOps->execute([':id' => $id]);

            if (!$inTx) $db->commit();

            if ($result && class_exists('AuditHelper')) {
                AuditHelper::log('DELETE_OPERASI', "Operasi SAR dihapus permanen: {$namaOps} [Status: {$statusOps}] beserta seluruh data turunan.", 'OPERASI', "OPS-{$id}");
            }

            return $result;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public static function closeOperation(int $id, ?string $waktuSelesai = null): bool {
        $db = getDB();
        $waktu = $waktuSelesai ?: date('Y-m-d H:i:s');
        $stmt = $db->prepare("UPDATE Operasi SET status = 'CLOSED', waktu_selesai = :waktu WHERE id_operasi = :id");
        $res = $stmt->execute([':waktu' => $waktu, ':id' => $id]);
        if ($res && class_exists('AuditHelper')) {
            AuditHelper::log('CLOSE_OPERASI', "Operasi SAR resmi ditutup (CLOSED): ID {$id}", 'OPERASI', "OPS-{$id}");
        }
        return $res;
    }

    public static function rollbackPenutupan(int $id): bool {
        $db = getDB();
        $stmt = $db->prepare("UPDATE Operasi SET status = 'OPEN', waktu_selesai = NULL WHERE id_operasi = :id");
        $res = $stmt->execute([':id' => $id]);
        if ($res && class_exists('AuditHelper')) {
            AuditHelper::log('REOPEN_OPERASI', "Operasi SAR dibuka kembali (RE-OPEN): ID {$id}", 'OPERASI', "OPS-{$id}");
        }
        return $res;
    }

    public static function syncUnsurAlut(int $idOperasi, array $unsurData, array $alutData): void {
        $db = getDB();
        $inTx = $db->inTransaction();
        if (!$inTx) $db->beginTransaction();

        try {
            $db->prepare("DELETE FROM DetailUnsur WHERE id_operasi = :id")->execute([':id' => $idOperasi]);
            if (!empty($unsurData)) {
                $stmtU = $db->prepare("INSERT INTO DetailUnsur (id_operasi, nama_unsur, jumlah_personil) VALUES (:id_ops, :nama, :jml)");
                foreach ($unsurData as $u) {
                    if (empty($u['nama_unsur'])) continue;
                    $stmtU->execute([
                        ':id_ops' => $idOperasi,
                        ':nama' => $u['nama_unsur'],
                        ':jml' => (int)($u['jumlah_personil'] ?? 1)
                    ]);
                }
            }

            $db->prepare("DELETE FROM DetailAlut WHERE id_operasi = :id")->execute([':id' => $idOperasi]);
            if (!empty($alutData)) {
                $stmtA = $db->prepare("INSERT INTO DetailAlut (id_operasi, id_alut, nama_alut, satuan, jumlah_alut) VALUES (:id_ops, :id_alut, :nama, :sat, :jml)");
                foreach ($alutData as $a) {
                    if (empty($a['nama_alut'])) continue;
                    $stmtA->execute([
                        ':id_ops' => $idOperasi,
                        ':id_alut' => (int)($a['id_alut'] ?? 0),
                        ':nama' => $a['nama_alut'],
                        ':sat' => $a['satuan'] ?? 'Unit',
                        ':jml' => (int)($a['jumlah_alut'] ?? 1)
                    ]);
                }
            }

            if (!$inTx) $db->commit();
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }
}
