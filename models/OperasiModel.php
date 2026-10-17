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
        $sql = "SELECT id_operasi, nama_operasi, status, lokasi_kejadian, koordinat, waktu_kejadian, waktu_berangkat, waktu_lapor, waktu_selesai, waktu_reopen, created_at FROM Operasi";
        $params = [];
        if ($status && in_array(strtoupper($status), ['OPEN', 'CLOSED'], true)) {
            $target = strtoupper($status);
            if ($target === 'OPEN') {
                $sql .= " WHERE status = 'OPEN' 
                          AND (waktu_selesai IS NULL OR waktu_selesai = '')";
            } else {
                $sql .= " WHERE status = 'CLOSED' 
                          OR (waktu_selesai IS NOT NULL AND waktu_selesai != '')";
            }
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
        $ops['stats_korban'] = TemplateHelper::hitungStatistikKorban($ops['korban'], (int)($ops['jumlah_pob'] ?? 0));

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
            // Strict Sequential ID Calculation:
            // Calculate the next ID strictly as MAX(id_operasi) + 1 with pessimistic row lock to prevent irregular jumping
            $stmtMax = $db->query("SELECT COALESCE(MAX(id_operasi), 0) + 1 AS next_id FROM Operasi FOR UPDATE");
            $nextId = (int)$stmtMax->fetchColumn();
            if ($nextId < 1) $nextId = 1;

            // Build full_text_wa & SSOT plain text representations
            $korbanData = $data['korban'] ?? [];
            $unsurData = $data['detail_unsur'] ?? [];
            $alutData = $data['detail_alut'] ?? [];

            // ISSUE-LAPAWAL-01: SSOT Generator for plain text columns from relational detail arrays
            $unsurText = self::formatUnsurText($unsurData, $data['unsur_terlibat'] ?? '');
            $alutText = self::formatAlutText($alutData, $data['alut_palsar'] ?? '');

            // ISSUE-LAPAWAL-02: Ensure jumlah_pob is at least count(korbanData)
            $effectivePob = max((int)($data['jumlah_pob'] ?? 0), count($korbanData));

            $now = date('Y-m-d H:i:s');
            // ISSUE-LAPAWAL-03: Temporal Normalization (DATETIME standard Y-m-d H:i:s)
            $wKejadian = self::normalizeDateTime($data['waktu_kejadian'] ?? $now);
            $wLapor = self::normalizeDateTime($data['waktu_lapor'] ?? $now);
            $wBerangkat = self::normalizeDateTime($data['waktu_berangkat'] ?? $now);
            $wEta = self::normalizeDateTime($data['eta_tiba'] ?? $now);

            $opsDataForWa = array_merge($data, [
                'waktu_kejadian' => $wKejadian,
                'waktu_lapor' => $wLapor,
                'waktu_berangkat' => $wBerangkat,
                'eta_tiba' => $wEta,
                'unsur_terlibat' => $unsurText,
                'alut_palsar' => $alutText,
                'jumlah_pob' => $effectivePob
            ]);
            $fullTextWa = TemplateHelper::generateLaporanAwalWA($opsDataForWa, $korbanData, $unsurData, $alutData);

            $stmt = $db->prepare("INSERT INTO Operasi (
                id_operasi,
                nama_operasi, nama_pelapor, hubungan_pelapor, no_telp_pelapor,
                waktu_kejadian, waktu_lapor, waktu_berangkat, tim_berangkat, eta_tiba,
                lokasi_kejadian, koordinat, jarak, radial, eta_lokasi, kronologi, jumlah_pob,
                unsur_terlibat, alut_palsar, faktor_penghambat, cuaca, gelombang, angin,
                status, full_text_wa, created_at
            ) VALUES (
                :id_operasi,
                :nama_operasi, :nama_pelapor, :hubungan_pelapor, :no_telp_pelapor,
                :waktu_kejadian, :waktu_lapor, :waktu_berangkat, :tim_berangkat, :eta_tiba,
                :lokasi_kejadian, :koordinat, :jarak, :radial, :eta_lokasi, :kronologi, :jumlah_pob,
                :unsur_terlibat, :alut_palsar, :faktor_penghambat, :cuaca, :gelombang, :angin,
                'OPEN', :full_text_wa, :created_at
            )");

            $stmt->execute([
                ':id_operasi' => $nextId,
                ':nama_operasi' => $data['nama_operasi'],
                ':nama_pelapor' => $data['nama_pelapor'] ?? '',
                ':hubungan_pelapor' => $data['hubungan_pelapor'] ?? '',
                ':no_telp_pelapor' => $data['no_telp_pelapor'] ?? '',
                ':waktu_kejadian' => $wKejadian,
                ':waktu_lapor' => $wLapor,
                ':waktu_berangkat' => $wBerangkat,
                ':tim_berangkat' => $data['tim_berangkat'] ?? 'Tim Rescue Kansar Kendari',
                ':eta_tiba' => $wEta,
                ':lokasi_kejadian' => $data['lokasi_kejadian'] ?? '',
                ':koordinat' => $data['koordinat'] ?? '',
                ':jarak' => $data['jarak'] ?? '',
                ':radial' => $data['radial'] ?? '',
                ':eta_lokasi' => $data['eta_lokasi'] ?? '',
                ':kronologi' => $data['kronologi'] ?? '',
                ':jumlah_pob' => $effectivePob,
                ':unsur_terlibat' => $unsurText,
                ':alut_palsar' => $alutText,
                ':faktor_penghambat' => $data['faktor_penghambat'] ?? 'Nihil',
                ':cuaca' => $data['cuaca'] ?? 'Cerah Berawan',
                ':gelombang' => $data['gelombang'] ?? '0.5 - 1.25 M',
                ':angin' => $data['angin'] ?? 'Barat Daya - Barat Laut, 5 - 15 Knots',
                ':full_text_wa' => $fullTextWa,
                ':created_at' => $now
            ]);

            $idOperasi = $nextId;

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
            $timBerangkat = trim((string)($data['tim_berangkat'] ?? ''));
            if (!empty($data['detail_tim_berangkat']) && is_array($data['detail_tim_berangkat'])) {
                $stmtT = $db->prepare("INSERT INTO DetailTimBerangkat (id_operasi, id_tim, nama_tim) VALUES (:id_ops, :id_tim, :nama)");
                foreach ($data['detail_tim_berangkat'] as $t) {
                    if (empty($t['nama_tim'])) continue;
                    $stmtT->execute([
                        ':id_ops' => $idOperasi,
                        ':id_tim' => (int)($t['id_tim'] ?? 0),
                        ':nama' => $t['nama_tim']
                    ]);
                }
            } elseif ($timBerangkat !== '') {
                $idTim = (int)($data['id_tim_berangkat'] ?? 0);
                if ($idTim <= 0) {
                    $stmtMt = $db->prepare("SELECT id_tim FROM MasterTim WHERE LOWER(nama_tim) = LOWER(:nama) LIMIT 1");
                    $stmtMt->execute([':nama' => $timBerangkat]);
                    $idTim = (int)($stmtMt->fetchColumn() ?: 0);
                }
                $stmtT = $db->prepare("INSERT INTO DetailTimBerangkat (id_operasi, id_tim, nama_tim) VALUES (:id_ops, :id_tim, :nama)");
                $stmtT->execute([
                    ':id_ops' => $idOperasi,
                    ':id_tim' => $idTim,
                    ':nama' => $timBerangkat
                ]);
            }

            $db->commit();

            // Synchronize GIS LKP Point & Tactical Nautical Route
            try {
                require_once __DIR__ . '/GisModel.php';
                GisModel::syncOperasiLkpPoint($idOperasi, $data);
                GisModel::syncOperasiRoute($idOperasi, $data);
            } catch (Throwable $gisEx) {
                error_log("GIS sync error on create Operasi {$idOperasi}: " . $gisEx->getMessage());
            }

            if (class_exists('AuditHelper')) {
                AuditHelper::log('CREATE_OPERASI', "Operasi SAR dibuka: {$data['nama_operasi']}", 'OPERASI', "OPS-{$idOperasi}");
            }

            return $idOperasi;
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
            $ops = self::getById($id);
            if (!$ops) {
                throw new RuntimeException('Operasi SAR tidak ditemukan.');
            }
            if (($ops['status'] ?? '') === 'CLOSED') {
                throw new RuntimeException('Operasi SAR telah berstatus DITUTUP (CLOSED). Data Laporan Awal terkunci dan tidak dapat diubah kecuali status operasi dibuka kembali.');
            }

            $korbanData = $data['korban'] ?? $ops['korban'];
            $unsurData = $data['detail_unsur'] ?? $ops['detail_unsur'];
            $alutData = $data['detail_alut'] ?? $ops['detail_alut'];

            // ISSUE-LAPAWAL-01: SSOT Generator for plain text columns from relational detail arrays
            $unsurText = self::formatUnsurText($unsurData, $data['unsur_terlibat'] ?? $ops['unsur_terlibat']);
            $alutText = self::formatAlutText($alutData, $data['alut_palsar'] ?? $ops['alut_palsar']);

            // ISSUE-LAPAWAL-02: Effective POB
            $effectivePob = max(isset($data['jumlah_pob']) ? (int)$data['jumlah_pob'] : (int)$ops['jumlah_pob'], count($korbanData));

            // ISSUE-LAPAWAL-03: Temporal Normalization (DATETIME standard Y-m-d H:i:s)
            $wKejadian = self::normalizeDateTime($data['waktu_kejadian'] ?? $ops['waktu_kejadian']);
            $wLapor = self::normalizeDateTime($data['waktu_lapor'] ?? $ops['waktu_lapor']);
            $wBerangkat = self::normalizeDateTime($data['waktu_berangkat'] ?? $ops['waktu_berangkat']);
            $wEta = self::normalizeDateTime($data['eta_tiba'] ?? $ops['eta_tiba']);

            $mergedOps = array_merge($ops, $data, [
                'waktu_kejadian' => $wKejadian,
                'waktu_lapor' => $wLapor,
                'waktu_berangkat' => $wBerangkat,
                'eta_tiba' => $wEta,
                'unsur_terlibat' => $unsurText,
                'alut_palsar' => $alutText,
                'jumlah_pob' => $effectivePob
            ]);
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
                ':waktu_kejadian' => $wKejadian,
                ':waktu_lapor' => $wLapor,
                ':waktu_berangkat' => $wBerangkat,
                ':tim_berangkat' => $data['tim_berangkat'] ?? $ops['tim_berangkat'],
                ':eta_tiba' => $wEta,
                ':lokasi_kejadian' => $data['lokasi_kejadian'] ?? $ops['lokasi_kejadian'],
                ':koordinat' => $data['koordinat'] ?? $ops['koordinat'],
                ':jarak' => $data['jarak'] ?? $ops['jarak'],
                ':radial' => $data['radial'] ?? ($ops['radial'] ?? ''),
                ':eta_lokasi' => $data['eta_lokasi'] ?? $ops['eta_lokasi'],
                ':kronologi' => $data['kronologi'] ?? $ops['kronologi'],
                ':jumlah_pob' => $effectivePob,
                ':unsur_terlibat' => $unsurText,
                ':alut_palsar' => $alutText,
                ':faktor_penghambat' => $data['faktor_penghambat'] ?? $ops['faktor_penghambat'],
                ':cuaca' => $data['cuaca'] ?? $ops['cuaca'],
                ':gelombang' => $data['gelombang'] ?? $ops['gelombang'],
                ':angin' => $data['angin'] ?? $ops['angin'],
                ':full_text_wa' => $fullTextWa
            ]);

            // Differential update for Korban
            if (isset($data['korban'])) {
                $stmtExistingK = $db->prepare("SELECT id_korban FROM Korban WHERE id_operasi = :id ORDER BY id_korban ASC");
                $stmtExistingK->execute([':id' => $id]);
                $existingKRows = $stmtExistingK->fetchAll();
                $existingKIds = array_column($existingKRows, 'id_korban');

                $stmtUpdateK = $db->prepare("UPDATE Korban SET nama_korban = :nama, umur = :umur, jk = :jk, alamat = :alamat, status_korban = :status WHERE id_korban = :kid AND id_operasi = :id_ops");
                $stmtInsertK = $db->prepare("INSERT INTO Korban (id_operasi, nama_korban, umur, jk, alamat, status_korban) VALUES (:id_ops, :nama, :umur, :jk, :alamat, :status)");

                $keptKIds = [];
                $kPool = $existingKIds;

                foreach ($data['korban'] as $k) {
                    if (empty($k['nama_korban'])) continue;
                    $kId = !empty($k['id_korban']) ? (int)$k['id_korban'] : null;

                    if ($kId && in_array($kId, $existingKIds, true) && !in_array($kId, $keptKIds, true)) {
                        $stmtUpdateK->execute([
                            ':kid' => $kId,
                            ':id_ops' => $id,
                            ':nama' => $k['nama_korban'],
                            ':umur' => $k['umur'] ?? '',
                            ':jk' => $k['jk'] ?? 'Laki-laki',
                            ':alamat' => $k['alamat'] ?? '',
                            ':status' => $k['status_korban'] ?? 'DP'
                        ]);
                        $keptKIds[] = $kId;
                        $kPool = array_values(array_diff($kPool, [$kId]));
                    } elseif (!empty($kPool)) {
                        $reusedKId = array_shift($kPool);
                        $stmtUpdateK->execute([
                            ':kid' => $reusedKId,
                            ':id_ops' => $id,
                            ':nama' => $k['nama_korban'],
                            ':umur' => $k['umur'] ?? '',
                            ':jk' => $k['jk'] ?? 'Laki-laki',
                            ':alamat' => $k['alamat'] ?? '',
                            ':status' => $k['status_korban'] ?? 'DP'
                        ]);
                        $keptKIds[] = $reusedKId;
                    } else {
                        $stmtInsertK->execute([
                            ':id_ops' => $id,
                            ':nama' => $k['nama_korban'],
                            ':umur' => $k['umur'] ?? '',
                            ':jk' => $k['jk'] ?? 'Laki-laki',
                            ':alamat' => $k['alamat'] ?? '',
                            ':status' => $k['status_korban'] ?? 'DP'
                        ]);
                    }
                }

                $toDeleteK = array_diff($existingKIds, $keptKIds);
                if (!empty($toDeleteK)) {
                    $delPlaceholders = implode(',', array_fill(0, count($toDeleteK), '?'));
                    $stmtDelK = $db->prepare("DELETE FROM Korban WHERE id_korban IN ({$delPlaceholders}) AND id_operasi = ?");
                    $stmtDelK->execute([...$toDeleteK, $id]);
                    $delKorban = true;
                }
            }

            // Differential update for DetailUnsur
            $delUnsur = false;
            if (isset($data['detail_unsur'])) {
                $delUnsur = self::applyDifferentialUnsur($db, $id, $data['detail_unsur']);
            }

            // Differential update for DetailAlut
            $delAlut = false;
            if (isset($data['detail_alut'])) {
                $delAlut = self::applyDifferentialAlut($db, $id, $data['detail_alut']);
            }

            // Differential update for DetailTimBerangkat
            $delTim = false;
            if (isset($data['tim_berangkat']) || isset($data['detail_tim_berangkat'])) {
                $delTim = self::applyDifferentialTimBerangkat($db, $id, $data);
            }

            $db->commit();
            if (!empty($delKorban)) self::syncChildAutoIncrements('Korban');
            if ($delUnsur) self::syncChildAutoIncrements('DetailUnsur');
            if ($delAlut) self::syncChildAutoIncrements('DetailAlut');
            if ($delTim) self::syncChildAutoIncrements('DetailTimBerangkat');

            // Synchronize GIS LKP Point & Tactical Nautical Route
            try {
                require_once __DIR__ . '/GisModel.php';
                $syncData = array_merge($ops, $data);
                GisModel::syncOperasiLkpPoint($id, $syncData);
                GisModel::syncOperasiRoute($id, $syncData);
            } catch (Throwable $gisEx) {
                error_log("GIS sync error on update Operasi {$id}: " . $gisEx->getMessage());
            }

            if (class_exists('AuditHelper')) {
                $namaUpdated = $data['nama_operasi'] ?? $ops['nama_operasi'];
                AuditHelper::log('UPDATE_OPERASI', "Data Laporan Awal Operasi SAR diperbarui: {$namaUpdated}", 'OPERASI', "OPS-{$id}");
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

            // 7. Clean up GIS components linked to this operation
            // Child points/waypoints must be deleted first due to RESTRICT FKs
            $db->prepare("DELETE FROM GisPolygonPoint WHERE polygonId IN (SELECT id FROM GisPolygon WHERE id_operasi = :id)")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisRoutePoint WHERE routeId IN (SELECT id FROM GisRoute WHERE id_operasi = :id)")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisSearchPatternWaypoint WHERE searchPatternId IN (SELECT id FROM GisSearchPattern WHERE id_operasi = :id)")->execute([':id' => $id]);

            $db->prepare("DELETE FROM GisPoint WHERE id_operasi = :id OR id = :pt_id")->execute([':id' => $id, ':pt_id' => "pt-lkp-op-{$id}"]);
            $db->prepare("DELETE FROM GisPolygon WHERE id_operasi = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisRoute WHERE id_operasi = :id OR id = :rt_id")->execute([':id' => $id, ':rt_id' => "route-op-{$id}"]);
            $db->prepare("DELETE FROM GisSearchPattern WHERE id_operasi = :id")->execute([':id' => $id]);

            // 8. Finally delete the Operasi record
            $stmtDelOps = $db->prepare("DELETE FROM Operasi WHERE id_operasi = :id");
            $result = $stmtDelOps->execute([':id' => $id]);

            if (!$inTx) {
                $db->commit();
                self::syncAutoIncrement();
                self::syncChildAutoIncrements();
            }

            if ($result && class_exists('AuditHelper')) {
                AuditHelper::log('DELETE_OPERASI', "Operasi SAR dihapus permanen: {$namaOps} [Status: {$statusOps}] beserta seluruh data turunan.", 'OPERASI', "OPS-{$id}");
            }

            return $result;
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function formatUnsurText(array $unsurList, string $fallback = ''): string {
        $uLines = [];
        foreach ($unsurList as $u) {
            $namaU = trim((string)($u['nama_unsur'] ?? ''));
            if ($namaU === '') continue;
            $jmlU = (int)($u['jumlah_personil'] ?? 1);
            $uLines[] = "• {$namaU} : {$jmlU} Org";
        }
        return !empty($uLines) ? implode("\n", $uLines) : trim($fallback);
    }

    public static function formatAlutText(array $alutList, string $fallback = ''): string {
        $aLines = [];
        foreach ($alutList as $a) {
            $namaA = trim((string)($a['nama_alut'] ?? ''));
            if ($namaA === '') continue;
            $jmlA = (int)($a['jumlah_alut'] ?? 1);
            $satA = trim((string)($a['satuan'] ?? 'Unit')) ?: 'Unit';
            $aLines[] = "• {$jmlA} {$satA} {$namaA}";
        }
        return !empty($aLines) ? implode("\n", $aLines) : trim($fallback);
    }

    public static function normalizeDateTime(?string $raw, bool $allowNull = false): ?string {
        if ($raw === null || trim($raw) === '') {
            return $allowNull ? null : date('Y-m-d H:i:s');
        }
        $clean = trim($raw);
        $clean = str_replace('T', ' ', $clean);
        if (str_ends_with($clean, 'Z')) {
            $clean = substr($clean, 0, -1);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $clean)) {
            $clean .= ':00';
        }
        $ts = class_exists('TemplateHelper')
            ? TemplateHelper::parseToTimestamp($clean)
            : strtotime($clean);
        if ($ts === false || $ts <= 0) {
            return $allowNull ? null : date('Y-m-d H:i:s');
        }
        return date('Y-m-d H:i:s', $ts);
    }

    public static function closeOperation(int $id, ?string $waktuSelesai = null): bool {
        $db = getDB();
        $stmtCheck = $db->prepare("SELECT id_operasi, nama_operasi, status FROM Operasi WHERE id_operasi = :id");
        $stmtCheck->execute([':id' => $id]);
        $ops = $stmtCheck->fetch();
        if (!$ops) {
            throw new RuntimeException('Operasi SAR tidak ditemukan.');
        }
        $waktu = self::normalizeDateTime($waktuSelesai);
        if (($ops['status'] ?? '') === 'CLOSED') {
            // Bersifat idempoten: Jika sudah CLOSED, update waktu_selesai jika diberikan tanpa melempar exception
            if ($waktu) {
                $stmt = $db->prepare("UPDATE Operasi SET waktu_selesai = :waktu WHERE id_operasi = :id");
                $stmt->execute([':waktu' => $waktu, ':id' => $id]);
            }
            return true;
        }

        $stmt = $db->prepare("UPDATE Operasi SET status = 'CLOSED', waktu_selesai = :waktu WHERE id_operasi = :id");
        $res = $stmt->execute([':waktu' => $waktu, ':id' => $id]);
        if ($res && class_exists('AuditHelper')) {
            $namaOps = $ops['nama_operasi'] ?? "ID #{$id}";
            AuditHelper::log('CLOSE_OPERASI', "Operasi SAR resmi ditutup (CLOSED): {$namaOps} (ID #{$id}) pada {$waktu}", 'OPERASI', "OPS-{$id}");
        }
        return $res;
    }

    private static bool $schemaChecked = false;

    public static function ensureSchemaUpdated(bool $force = false): void {
        if (!$force) return;
        if (self::$schemaChecked) return;
        self::$schemaChecked = true;
        try {
            $db = getDB();
            if ($db->inTransaction()) return;

            $opsCols = [
                'jenis_kejadian' => "ALTER TABLE Operasi ADD COLUMN jenis_kejadian VARCHAR(191) NULL DEFAULT NULL AFTER nama_operasi",
                'radial' => "ALTER TABLE Operasi ADD COLUMN radial VARCHAR(191) NULL DEFAULT NULL AFTER jarak",
                'waktu_reopen' => "ALTER TABLE Operasi ADD COLUMN waktu_reopen DATETIME NULL DEFAULT NULL AFTER waktu_selesai",
                'catatan_reopen' => "ALTER TABLE Operasi ADD COLUMN catatan_reopen TEXT NULL DEFAULT NULL AFTER waktu_reopen",
                'hari_sebelum_reopen' => "ALTER TABLE Operasi ADD COLUMN hari_sebelum_reopen INT NULL DEFAULT NULL AFTER catatan_reopen"
            ];
            foreach ($opsCols as $colName => $alterSql) {
                $cols = $db->query("SHOW COLUMNS FROM Operasi LIKE '{$colName}'")->fetchAll();
                if (empty($cols)) {
                    $db->exec($alterSql);
                }
            }

            // ISSUE-LAPAWAL-03: Automate temporal column conversion from VARCHAR to DATETIME
            $colWaktu = $db->query("SHOW COLUMNS FROM Operasi LIKE 'waktu_kejadian'")->fetch();
            if ($colWaktu && stripos((string)($colWaktu['Type'] ?? ''), 'varchar') !== false) {
                $db->exec("UPDATE Operasi SET waktu_kejadian = STR_TO_DATE(LEFT(REPLACE(waktu_kejadian, 'T', ' '), 19), '%Y-%m-%d %H:%i:%s') WHERE waktu_kejadian IS NOT NULL AND waktu_kejadian != ''");
                $db->exec("UPDATE Operasi SET waktu_lapor = STR_TO_DATE(LEFT(REPLACE(waktu_lapor, 'T', ' '), 19), '%Y-%m-%d %H:%i:%s') WHERE waktu_lapor IS NOT NULL AND waktu_lapor != ''");
                $db->exec("UPDATE Operasi SET waktu_berangkat = STR_TO_DATE(LEFT(REPLACE(waktu_berangkat, 'T', ' '), 19), '%Y-%m-%d %H:%i:%s') WHERE waktu_berangkat IS NOT NULL AND waktu_berangkat != ''");
                $db->exec("UPDATE Operasi SET eta_tiba = STR_TO_DATE(LEFT(REPLACE(eta_tiba, 'T', ' '), 19), '%Y-%m-%d %H:%i:%s') WHERE eta_tiba IS NOT NULL AND eta_tiba != ''");
                $db->exec("UPDATE Operasi SET waktu_selesai = NULL WHERE waktu_selesai IS NOT NULL AND TRIM(waktu_selesai) IN ('', '0000-00-00 00:00:00', 'null', 'NULL')");
                $db->exec("UPDATE Operasi SET waktu_selesai = STR_TO_DATE(LEFT(REPLACE(waktu_selesai, 'T', ' '), 19), '%Y-%m-%d %H:%i:%s') WHERE waktu_selesai IS NOT NULL AND waktu_selesai != ''");
                $db->exec("ALTER TABLE Operasi 
                    MODIFY COLUMN waktu_kejadian DATETIME NOT NULL,
                    MODIFY COLUMN waktu_lapor DATETIME NOT NULL,
                    MODIFY COLUMN waktu_berangkat DATETIME NOT NULL,
                    MODIFY COLUMN eta_tiba DATETIME NOT NULL,
                    MODIFY COLUMN waktu_selesai DATETIME NULL DEFAULT NULL");
            }
        } catch (Throwable $e) {
            error_log("[OperasiModel Schema Check] " . $e->getMessage());
        }
    }

    public static function rollbackPenutupan(int $id, ?string $waktuReopen = null): bool {
        $db = getDB();
        $ops = self::getById($id);
        if (!$ops) {
            throw new RuntimeException('Operasi SAR tidak ditemukan.');
        }
        if (($ops['status'] ?? '') !== 'CLOSED') {
            throw new RuntimeException('Operasi SAR sudah berstatus AKTIF (OPEN) dan tidak memerlukan pembukaan kembali.');
        }

        // Hitung hari ke-N saat operasi ditutup sebelum waktu_selesai direset ke NULL
        $opsForBase = $ops;
        unset($opsForBase['waktu_reopen']);
        $hariTutup = class_exists('TemplateHelper') ? TemplateHelper::getBaseHariSebelumReopen($opsForBase) : 7;

        $waktu = self::normalizeDateTime($waktuReopen);
        try {
            $stmt = $db->prepare("UPDATE Operasi SET status = 'OPEN', waktu_selesai = NULL, waktu_reopen = :waktu, hari_sebelum_reopen = :hari_tutup WHERE id_operasi = :id");
            $res = $stmt->execute([':waktu' => $waktu, ':hari_tutup' => $hariTutup, ':id' => $id]);
        } catch (Throwable) {
            $stmt = $db->prepare("UPDATE Operasi SET status = 'OPEN', waktu_selesai = NULL, waktu_reopen = :waktu WHERE id_operasi = :id");
            $res = $stmt->execute([':waktu' => $waktu, ':id' => $id]);
        }
        if ($res && class_exists('AuditHelper')) {
            $namaOps = $ops['nama_operasi'] ?? "ID #{$id}";
            AuditHelper::log('REOPEN_OPERASI', "Operasi SAR dibuka kembali (RE-OPEN): {$namaOps} (ID #{$id}) pada {$waktu} (melanjutkan dari H.{$hariTutup} -> H{$hariTutup} + 1)", 'OPERASI', "OPS-{$id}");
        }
        return $res;
    }

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
                if ($delUnsur) self::syncChildAutoIncrements('DetailUnsur');
                if ($delAlut) self::syncChildAutoIncrements('DetailAlut');
            }
        } catch (Throwable $e) {
            if (!$inTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Synchronize and reset Operasi table AUTO_INCREMENT to strictly MAX(id_operasi) + 1.
     * Prevents any irregular jumping or stale high-water marks in MySQL InnoDB.
     */
    public static function syncAutoIncrement(): void {
        try {
            $db = getDB();
            if ($db->inTransaction()) {
                return; // DDL causes implicit commit; never run inside an active transaction
            }
            $stmtMax = $db->query("SELECT COALESCE(MAX(id_operasi), 0) + 1 FROM Operasi");
            $nextAuto = max(1, (int)$stmtMax->fetchColumn());
            $stmtMax->closeCursor();
            $db->exec("ALTER TABLE Operasi AUTO_INCREMENT = {$nextAuto}");
        } catch (Throwable $e) {
            // Silently ignore if insufficient DB DDL privileges
        }
    }

    /**
     * Synchronize and reset child table AUTO_INCREMENT values to MAX(id) + 1.
     * If $onlyTable is specified, only that table will be checked and updated.
     * Keeps primary keys compact and prevents auto-increment high-water mark drift.
     */
    public static function syncChildAutoIncrements(?string $onlyTable = null): void {
        $db = getDB();
        if ($db->inTransaction()) {
            return; // DDL causes implicit commit; never run inside an active transaction
        }
        $childTables = [
            'DetailAlut' => 'id_detail_alut',
            'DetailUnsur' => 'id_detail_unsur',
            'DetailTimBerangkat' => 'id_detail_tim',
            'MasterTim' => 'id_tim',
            'Korban' => 'id_korban',
            'RenopsSRU' => 'id_sru',
            'BraSection' => 'id',
            'GisPolygonPoint' => 'id',
            'GisRoutePoint' => 'id',
            'GisSearchPatternWaypoint' => 'id',
            'LoginThrottle' => 'id'
        ];
        $targets = $onlyTable !== null ? (isset($childTables[$onlyTable]) ? [$onlyTable => $childTables[$onlyTable]] : []) : $childTables;
        if (empty($targets)) {
            return;
        }
        foreach ($targets as $table => $col) {
            try {
                $stmt = $db->query("SELECT COALESCE(MAX({$col}), 0) FROM {$table}");
                $max = (int)$stmt->fetchColumn();
                $stmt->closeCursor();
                $next = max(1, $max + 1);
                $db->exec("ALTER TABLE {$table} AUTO_INCREMENT = {$next}");
            } catch (Throwable) {
                // Silently continue
            }
        }
    }
}

