<?php
/**
 * QR-SAR - Operasi SAR Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/OperasiRelationService.php';

class OperasiModel {
    public static function getAll(?string $status = null, ?string $search = null, ?int $limit = null, int $offset = 0): array {
        $db = getDB();
        $sql = "SELECT o.*, 
            COALESCE(k.total_korban_count, 0) AS total_korban_count,
            COALESCE(k.korban_s, 0) AS korban_s,
            COALESCE(k.korban_md, 0) AS korban_md,
            COALESCE(k.korban_dp, 0) AS korban_dp,
            COALESCE(k.korban_h, 0) AS korban_h,
            COALESCE(r.total_renops, 0) AS total_renops,
            COALESCE(l.total_laporan, 0) AS total_laporan,
            COALESCE(b.total_bra, 0) AS total_bra
            FROM Operasi o
            LEFT JOIN (
                SELECT id_operasi,
                       COUNT(*) AS total_korban_count,
                       SUM(status_korban = 'S') AS korban_s,
                       SUM(status_korban = 'MD') AS korban_md,
                       SUM(status_korban = 'DP') AS korban_dp,
                       SUM(status_korban = 'H') AS korban_h
                FROM Korban
                GROUP BY id_operasi
            ) k ON k.id_operasi = o.id_operasi
            LEFT JOIN (
                SELECT id_operasi, COUNT(*) AS total_renops 
                FROM Renops 
                GROUP BY id_operasi
            ) r ON r.id_operasi = o.id_operasi
            LEFT JOIN (
                SELECT id_operasi, COUNT(*) AS total_laporan 
                FROM LaporanHarian 
                GROUP BY id_operasi
            ) l ON l.id_operasi = o.id_operasi
            LEFT JOIN (
                SELECT id_operasi, COUNT(*) AS total_bra 
                FROM BraSar 
                GROUP BY id_operasi
            ) b ON b.id_operasi = o.id_operasi
            WHERE 1=1";
        
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
                          AND waktu_selesai IS NULL";
            } else {
                $sql .= " WHERE status = 'CLOSED' 
                          OR waktu_selesai IS NOT NULL";
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

    /** Status korban yang sah (DP|S|MD|H) dalam huruf besar, null bila tidak dikenal */
    public static function normalizeStatusKorban(mixed $status): ?string {
        $status = is_string($status) ? strtoupper(trim($status)) : '';
        return in_array($status, ['DP', 'S', 'MD', 'H'], true) ? $status : null;
    }

    public static function create(array $data): int {
        $db = getDB();
        $db->beginTransaction();

        try {

            // Build full_text_wa & SSOT plain text representations (sumber sama dengan pratinjau WA di form)
            $now = date('Y-m-d H:i:s');
            $prep = self::prepareLaporanAwal($data);
            $opsDataForWa = $prep['ops'];
            [$korbanData, $unsurData, $alutData] = [$prep['korban'], $prep['unsur'], $prep['alut']];
            $unsurText = $opsDataForWa['unsur_terlibat'];
            $alutText = $opsDataForWa['alut_palsar'];
            $effectivePob = $opsDataForWa['jumlah_pob'];
            $wKejadian = $opsDataForWa['waktu_kejadian'];
            $wLapor = $opsDataForWa['waktu_lapor'];
            $wBerangkat = $opsDataForWa['waktu_berangkat'];
            $wEta = $opsDataForWa['eta_tiba'];
            $fullTextWa = TemplateHelper::generateLaporanAwalWA($opsDataForWa, $korbanData, $unsurData, $alutData);

            // ID dari AUTO_INCREMENT: ID operasi yang dihapus tidak dipakai ulang (link, QR & nomor dokumen lama tetap unik)
            $stmt = $db->prepare("INSERT INTO Operasi (
                nama_operasi, jenis_kejadian, nama_pelapor, hubungan_pelapor, no_telp_pelapor,
                waktu_kejadian, waktu_lapor, waktu_berangkat, tim_berangkat, eta_tiba,
                lokasi_kejadian, koordinat, jarak, radial, asal_berangkat, eta_lokasi, kronologi, jumlah_pob,
                unsur_terlibat, alut_palsar, faktor_penghambat, cuaca, gelombang, angin,
                status, full_text_wa, created_at
            ) VALUES (
                :nama_operasi, :jenis_kejadian, :nama_pelapor, :hubungan_pelapor, :no_telp_pelapor,
                :waktu_kejadian, :waktu_lapor, :waktu_berangkat, :tim_berangkat, :eta_tiba,
                :lokasi_kejadian, :koordinat, :jarak, :radial, :asal_berangkat, :eta_lokasi, :kronologi, :jumlah_pob,
                :unsur_terlibat, :alut_palsar, :faktor_penghambat, :cuaca, :gelombang, :angin,
                'OPEN', :full_text_wa, :created_at
            )");

            $stmt->execute([
                ':nama_operasi' => $data['nama_operasi'],
                ':jenis_kejadian' => self::normalizeJenisKejadian($data['jenis_kejadian'] ?? null),
                ':asal_berangkat' => $opsDataForWa['asal_berangkat'],
                ':nama_pelapor' => $data['nama_pelapor'] ?? '',
                ':hubungan_pelapor' => $data['hubungan_pelapor'] ?? '',
                ':no_telp_pelapor' => $data['no_telp_pelapor'] ?? '',
                ':waktu_kejadian' => $wKejadian,
                ':waktu_lapor' => $wLapor,
                ':waktu_berangkat' => $wBerangkat,
                ':tim_berangkat' => $data['tim_berangkat'] ?? '',
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
                ':cuaca' => $data['cuaca'] ?? '',
                ':gelombang' => $data['gelombang'] ?? '',
                ':angin' => $data['angin'] ?? '',
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
                        ':status' => self::normalizeStatusKorban($k['status_korban'] ?? null) ?? 'DP'
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
                $stmtA = $db->prepare("INSERT INTO DetailAlut (id_operasi, id_alut, nama_alut, satuan, kategori, jumlah_alut) VALUES (:id_ops, :id_alut, :nama, :sat, :kat, :jml)");
                foreach ($alutData as $a) {
                    if (empty($a['nama_alut'])) continue;
                    $stmtA->execute([
                        ':id_ops' => $idOperasi,
                        ':id_alut' => (int)($a['id_alut'] ?? 0),
                        ':nama' => $a['nama_alut'],
                        ':sat' => $a['satuan'] ?? 'Unit',
                        ':kat' => AlutKategori::resolve($a),
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

            // Sumber sama dengan pratinjau WA di form
            $prep = self::prepareLaporanAwal($data, $ops);
            $mergedOps = $prep['ops'];
            [$korbanData, $unsurData, $alutData] = [$prep['korban'], $prep['unsur'], $prep['alut']];
            $unsurText = $mergedOps['unsur_terlibat'];
            $alutText = $mergedOps['alut_palsar'];
            $effectivePob = $mergedOps['jumlah_pob'];
            $wKejadian = $mergedOps['waktu_kejadian'];
            $wLapor = $mergedOps['waktu_lapor'];
            $wBerangkat = $mergedOps['waktu_berangkat'];
            $wEta = $mergedOps['eta_tiba'];
            $fullTextWa = TemplateHelper::generateLaporanAwalWA($mergedOps, $korbanData, $unsurData, $alutData);

            $stmt = $db->prepare("UPDATE Operasi SET
                nama_operasi = :nama_operasi,
                jenis_kejadian = :jenis_kejadian,
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
                asal_berangkat = :asal_berangkat,
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
                ':jenis_kejadian' => array_key_exists('jenis_kejadian', $data)
                    ? self::normalizeJenisKejadian($data['jenis_kejadian'])
                    : ($ops['jenis_kejadian'] ?? null),
                ':asal_berangkat' => $mergedOps['asal_berangkat'],
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
                // Pre-claim explicit IDs so newly added unnumbered items cannot steal an ID belonging to a later existing item
                $explicitClaimedK = [];
                foreach ($data['korban'] as $k) {
                    $kId = !empty($k['id_korban']) ? (int)$k['id_korban'] : null;
                    if ($kId && in_array($kId, $existingKIds, true)) {
                        $explicitClaimedK[$kId] = true;
                    }
                }
                $kPool = array_values(array_filter($existingKIds, fn($id) => !isset($explicitClaimedK[$id])));

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
                            ':status' => self::normalizeStatusKorban($k['status_korban'] ?? null) ?? 'DP'
                        ]);
                        $keptKIds[] = $kId;
                    } elseif (!empty($kPool)) {
                        $reusedKId = array_shift($kPool);
                        $stmtUpdateK->execute([
                            ':kid' => $reusedKId,
                            ':id_ops' => $id,
                            ':nama' => $k['nama_korban'],
                            ':umur' => $k['umur'] ?? '',
                            ':jk' => $k['jk'] ?? 'Laki-laki',
                            ':alamat' => $k['alamat'] ?? '',
                            ':status' => self::normalizeStatusKorban($k['status_korban'] ?? null) ?? 'DP'
                        ]);
                        $keptKIds[] = $reusedKId;
                    } else {
                        $stmtInsertK->execute([
                            ':id_ops' => $id,
                            ':nama' => $k['nama_korban'],
                            ':umur' => $k['umur'] ?? '',
                            ':jk' => $k['jk'] ?? 'Laki-laki',
                            ':alamat' => $k['alamat'] ?? '',
                            ':status' => self::normalizeStatusKorban($k['status_korban'] ?? null) ?? 'DP'
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

            // 0. Kumpulkan path berkas dokumen RENOPS (dihapus dari disk setelah commit)
            $filePaths = class_exists('RenopsDokumenModel') ? RenopsDokumenModel::collectFilePathsForOperasi($id) : [];

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
            DocVerifyHelper::forgetOperasiTokens($id);

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

            // 7b. Delete map snapshots and collect physical image files to unlink AFTER commit
            $mapSnapFiles = [];
            if (class_exists('DokumenPetaSnapshotModel')) {
                $mapSnapFiles = DokumenPetaSnapshotModel::deleteByOperasi($id, false);
            }

            // 8. Finally delete the Operasi record
            $stmtDelOps = $db->prepare("DELETE FROM Operasi WHERE id_operasi = :id");
            $result = $stmtDelOps->execute([':id' => $id]);

            if (!$inTx && $db->inTransaction()) {
                $db->commit();
                if ($result) {
                    if ($filePaths && class_exists('RenopsDokumenModel')) {
                        RenopsDokumenModel::deleteFiles($filePaths);
                    }
                    if ($mapSnapFiles && class_exists('DokumenPetaSnapshotModel')) {
                        foreach ($mapSnapFiles as $mf) {
                            DokumenPetaSnapshotModel::removeImageFile($mf);
                        }
                    }
                }
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
        foreach (AlutKategori::sort($alutList) as $a) {
            $namaA = trim((string)($a['nama_alut'] ?? ''));
            if ($namaA === '') continue;
            $jmlA = (int)($a['jumlah_alut'] ?? 1);
            $satA = trim((string)($a['satuan'] ?? 'Unit')) ?: 'Unit';
            $aLines[] = "• {$jmlA} {$satA} {$namaA}";
        }
        return !empty($aLines) ? implode("\n", $aLines) : trim($fallback);
    }

    /**
     * Satu sumber data Laporan Awal untuk simpan (create/update) dan pratinjau WA di form.
     * $ops = data operasi tersimpan (mode edit) atau null (operasi baru).
     *
     * @return array{ops: array, korban: array, unsur: array, alut: array}
     */
    public static function prepareLaporanAwal(array $data, ?array $ops = null): array {
        $base = $ops ?? [];
        $now = date('Y-m-d H:i:s');
        // ISSUE-LAPAWAL-01: SSOT Generator for plain text columns from relational detail arrays
        $korban = array_values($data['korban'] ?? ($base['korban'] ?? []));
        $unsur = $data['detail_unsur'] ?? ($base['detail_unsur'] ?? []);
        $alut = $data['detail_alut'] ?? ($base['detail_alut'] ?? []);
        $pob = isset($data['jumlah_pob']) ? (int)$data['jumlah_pob'] : (int)($base['jumlah_pob'] ?? 0);

        // ISSUE-LAPAWAL-02/03: POB efektif & normalisasi DATETIME (Y-m-d H:i:s)
        $merged = array_merge($base, $data, [
            'waktu_kejadian' => self::normalizeDateTime($data['waktu_kejadian'] ?? ($ops ? $ops['waktu_kejadian'] : $now)),
            'waktu_lapor' => self::normalizeDateTime($data['waktu_lapor'] ?? ($ops ? $ops['waktu_lapor'] : $now)),
            'waktu_berangkat' => self::normalizeDateTime($data['waktu_berangkat'] ?? ($ops ? $ops['waktu_berangkat'] : $now)),
            'eta_tiba' => self::normalizeDateTime($data['eta_tiba'] ?? ($ops ? $ops['eta_tiba'] : $now)),
            'unsur_terlibat' => self::formatUnsurText($unsur, $data['unsur_terlibat'] ?? ($base['unsur_terlibat'] ?? '')),
            'alut_palsar' => self::formatAlutText($alut, $data['alut_palsar'] ?? ($base['alut_palsar'] ?? '')),
            'jumlah_pob' => max($pob, count($korban)),
            'asal_berangkat' => self::resolveAsalBerangkat($data, $base),
        ]);

        return ['ops' => $merged, 'korban' => $korban, 'unsur' => $unsur, 'alut' => $alut];
    }

    /** Pilihan baku Jenis Kejadian (dropdown form Laporan Awal); selain ini lewat "Lainnya…". */
    public const JENIS_KEJADIAN_OPSI = [
        'Kecelakaan Kapal', 'Kecelakaan Pesawat Udara', 'Kondisi Membahayakan Manusia', 'Bencana', 'Kecelakaan dengan Penanganan Khusus',
    ];

    /**
     * Status awal dropdown Jenis Kejadian dari nilai tersimpan. Nilai di luar daftar → "Lainnya…" + teks utuh,
     * agar data lama tidak hilang saat form disimpan ulang.
     *
     * @return array{pilihan: string, lainnya: string}
     */
    public static function jenisKejadianPilihan(?string $nilai): array {
        $v = trim((string)$nilai);
        if ($v === '') {
            return ['pilihan' => '', 'lainnya' => ''];
        }
        return in_array($v, self::JENIS_KEJADIAN_OPSI, true) ? ['pilihan' => $v, 'lainnya' => ''] : ['pilihan' => '__LAINNYA__', 'lainnya' => $v];
    }

    /**
     * Asal pemberangkatan (Kantor/POS/USS) untuk naskah "Radial … dari …": kunci asal_berangkat, atau origin_name
     * dari pilihan Pangkalan di form; tanpa keduanya nilai tersimpan tetap. Trim, kosong → NULL, maks 191.
     */
    private static function resolveAsalBerangkat(array $data, array $base): ?string {
        $raw = array_key_exists('asal_berangkat', $data) ? $data['asal_berangkat']
            : (array_key_exists('origin_name', $data) ? $data['origin_name'] : ($base['asal_berangkat'] ?? null));
        $val = is_string($raw) ? trim($raw) : '';
        return $val === '' ? null : mb_substr($val, 0, 191);
    }

    /**
     * Jenis kejadian (opsional, kolom VARCHAR(191)): trim, kosong → NULL, dipotong 191 karakter.
     */
    public static function normalizeJenisKejadian(mixed $raw): ?string {
        $val = is_string($raw) ? trim($raw) : '';
        return $val === '' ? null : mb_substr($val, 0, 191);
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
                'asal_berangkat' => "ALTER TABLE Operasi ADD COLUMN asal_berangkat VARCHAR(191) NULL DEFAULT NULL AFTER radial",
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

    public static function rollbackPenutupan(int $id, ?string $waktuReopen = null, ?string $catatan = null): bool {
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
            $alasan = !empty($catatan) ? " (Alasan: " . trim((string)$catatan) . ")" : "";
            AuditHelper::log('REOPEN_OPERASI', "Operasi SAR dibuka kembali (RE-OPEN): {$namaOps} (ID #{$id}) pada {$waktu} (melanjutkan dari H.{$hariTutup} -> H{$hariTutup} + 1){$alasan}", 'OPERASI', "OPS-{$id}");
        }
        return $res;
    }

    public static function applyDifferentialUnsur(PDO $db, int $idOperasi, array $unsurData): bool {
        return OperasiRelationService::applyDifferentialUnsur($db, $idOperasi, $unsurData);
    }

    public static function applyDifferentialAlut(PDO $db, int $idOperasi, array $alutData): bool {
        return OperasiRelationService::applyDifferentialAlut($db, $idOperasi, $alutData);
    }

    public static function applyDifferentialTimBerangkat(PDO $db, int $idOperasi, array $data): bool {
        return OperasiRelationService::applyDifferentialTimBerangkat($db, $idOperasi, $data);
    }

    public static function syncUnsurAlut(int $idOperasi, array $unsurData, array $alutData): void {
        OperasiRelationService::syncUnsurAlut($idOperasi, $unsurData, $alutData);
    }

}

