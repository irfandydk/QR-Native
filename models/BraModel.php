<?php
/**
 * QR-SAR - Berita Radio SAR (BRA) Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class BraModel {
    public static function getAll(?string $kategori = null, ?string $search = null, bool $withSections = false): array {
        $db = getDB();
        $sql = "SELECT b.*,
            (SELECT COUNT(*) FROM BraSection s WHERE s.id_bra = b.id_bra) as total_sections
            FROM BraSar b WHERE 1=1";
        
        $params = [];
        if ($kategori) {
            $cat = BraHelper::normalizeCategory($kategori);
            $sql .= " AND (b.kategori = :kategori" . ($cat === 'OPERASI_SAR' ? " OR b.kategori = 'SAR_UMUM'" : "") . ")";
            $params[':kategori'] = $cat;
        }

        if (!empty($search)) {
            $sql .= " AND (b.no_bra LIKE :q1 OR b.nama_operasi LIKE :q2 OR b.perihal LIKE :q3 OR b.menbra LIKE :q4 OR b.nama_smc LIKE :q5)";
            $q = "%{$search}%";
            $params[':q1'] = $q;
            $params[':q2'] = $q;
            $params[':q3'] = $q;
            $params[':q4'] = $q;
            $params[':q5'] = $q;
        }

        $sql .= " ORDER BY b.id_bra DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $bras = $stmt->fetchAll();

        // Efficient single batched query if sections are explicitly needed
        if ($withSections && !empty($bras)) {
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

            foreach ($bras as &$bra) {
                $bra['sections'] = $secsByBra[$bra['id_bra']] ?? [];
            }
        } else {
            foreach ($bras as &$bra) {
                $bra['sections'] = [];
            }
        }

        return $bras;
    }

    public static function getStats(): array {
        $db = getDB();
        $row = $db->query("
            SELECT 
                COUNT(*) as total,
                COALESCE(SUM(CASE WHEN kategori = 'MAPEL' THEN 1 ELSE 0 END), 0) as mapel,
                COALESCE(SUM(CASE WHEN kategori IN ('OPERASI_SAR', 'SAR_UMUM') THEN 1 ELSE 0 END), 0) as operasi_sar,
                COALESCE(SUM(CASE WHEN kategori = 'PERGERAKAN_KAPAL' THEN 1 ELSE 0 END), 0) as kapal
            FROM BraSar
        ")->fetch();

        return [
            'total' => (int)($row['total'] ?? 0),
            'mapel' => (int)($row['mapel'] ?? 0),
            'operasi_sar' => (int)($row['operasi_sar'] ?? 0),
            'sar_umum' => (int)($row['operasi_sar'] ?? 0), // kompatibilitas ke belakang
            'kapal' => (int)($row['kapal'] ?? 0)
        ];
    }

    public static function getLatest(int $limit = 1): array {
        $db = getDB();
        $limit = max(1, min(100, $limit));
        $stmt = $db->query("SELECT * FROM BraSar ORDER BY id_bra DESC LIMIT {$limit}");
        return $stmt->fetchAll();
    }

    public static function getById(int $id): ?array {
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM BraSar WHERE id_bra = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $bra = $stmt->fetch();
        if (!$bra) return null;

        $stmtSec = $db->prepare("SELECT kode, isi FROM BraSection WHERE id_bra = :id ORDER BY id ASC");
        $stmtSec->execute([':id' => $id]);
        $bra['sections'] = $stmtSec->fetchAll();

        return $bra;
    }

    public static function create(array $data): int {
        $db = getDB();
        $db->beginTransaction();

        try {
            $kategori = BraHelper::normalizeCategory($data['kategori'] ?? 'MAPEL');
            $satkerKode = $data['satker_kode'] ?? DEFAULT_SATKER_KODE;
            $isAuto = !isset($data['is_auto_no_bra']) || $data['is_auto_no_bra'] === '1' || $data['is_auto_no_bra'] === 1 || $data['is_auto_no_bra'] === true;

            // Generate sequence and no_bra with atomic row-level lock
            if ($isAuto || empty($data['no_bra'])) {
                $next = BraHelper::getNextNomorBra($kategori, $satkerKode, $db, true);
                $nomorUrut = $next['nomor_urut'];
                $noBra = $next['no_bra'];
                $bulanTahun = $next['bulan_tahun'];
            } else {
                $parsed = BraHelper::parseNoBra($data['no_bra'], $satkerKode);
                $nomorUrut = $parsed['nomor_urut'];
                $satkerKode = $parsed['satker_kode'];
                $bulanTahun = $parsed['bulan_tahun'];
                $noBra = $parsed['no_bra'];

                // Verifikasi ketersediaan nomor kustom dengan penguncian baris
                $stmtCheck = $db->prepare("SELECT id_bra FROM BraSar WHERE no_bra = :no_bra LIMIT 1 FOR UPDATE");
                $stmtCheck->execute([':no_bra' => $noBra]);
                if ($stmtCheck->fetch()) {
                    // Jika no_bra kustom ternyata sudah digunakan, otomatis alokasikan nomor urut berikutnya secara atomik
                    $next = BraHelper::getNextNomorBra($kategori, $satkerKode, $db, true);
                    $nomorUrut = $next['nomor_urut'];
                    $noBra = $next['no_bra'];
                    $bulanTahun = $next['bulan_tahun'];
                }
            }

            // Sanitasi ketat huruf kapital baku telegram dan tanda baca militer
            $rawPerihal = trim((string)($data['perihal'] ?? ''));
            $perihal = BraHelper::ensureEndsWithTtk(BraHelper::sanitizeTelegramPunctuation($rawPerihal));

            $rawSections = $data['sections'] ?? [];
            $sanitizedSections = [];
            foreach ($rawSections as $sec) {
                $isi = trim((string)($sec['isi'] ?? ''));
                if ($isi === '') continue;
                $sanitizedSections[] = [
                    'id' => !empty($sec['id']) ? (int)$sec['id'] : null,
                    'kode' => strtoupper(trim((string)($sec['kode'] ?? 'AAA'))),
                    'isi' => BraHelper::sanitizeTelegramPunctuation($isi)
                ];
            }
            $sections = $sanitizedSections;

            $wordCount = !empty($data['jumlah_kata']) ? $data['jumlah_kata'] : (BraHelper::countBraWords($sections, $perihal) . ' KATA');

            $idOps = !empty($data['id_operasi']) ? (int)$data['id_operasi'] : null;
            $namaOps = mb_strtoupper(trim((string)($data['nama_operasi'] ?? '')), 'UTF-8');
            if ($idOps && empty($namaOps)) {
                $stmtOps = $db->prepare("SELECT nama_operasi FROM Operasi WHERE id_operasi = :id LIMIT 1");
                $stmtOps->execute([':id' => $idOps]);
                $namaOps = mb_strtoupper((string)($stmtOps->fetchColumn() ?: ''), 'UTF-8');
            }

            $dari = mb_strtoupper(trim((string)($data['dari'] ?? 'KANSAR KENDARI')), 'UTF-8');
            $aksi = mb_strtoupper(trim((string)($data['aksi'] ?? ($kategori === 'MAPEL' ? 'KA KSOP KENDARI KMA KA SROP KENDARI' : 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN'))), 'UTF-8');
            $info = mb_strtoupper(trim((string)($data['info'] ?? ($kategori === 'MAPEL' ? 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN' : 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN'))), 'UTF-8');
            $pengirim = mb_strtoupper(trim((string)($data['pengirim'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI')), 'UTF-8');
            $namaSmc = mb_strtoupper(trim((string)($data['nama_smc'] ?? 'AMIRUDDIN A.S., S.Sos.')), 'UTF-8');
            $pangkatSmc = mb_strtoupper(trim((string)($data['pangkat_smc'] ?? 'PEMBINA (IV/a)')), 'UTF-8');
            $jabatanSmc = mb_strtoupper(trim((string)($data['jabatan_smc'] ?? ($kategori === 'MAPEL' ? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI' : 'SMC'))), 'UTF-8');

            $twu = BraHelper::normalizeMilitaryTW($data['twu'] ?? null);
            $twb = BraHelper::normalizeMilitaryTW($data['twb'] ?? null);

            $stmt = $db->prepare("INSERT INTO BraSar (
                kategori, no_bra, nomor_urut, satker_kode, bulan_tahun,
                id_operasi, nama_operasi, panggilan, jenis, nr_stn, derajat,
                instruksi_penerusan, dari, aksi, info, sifat, menbra, perihal,
                tanggal_waktu, twu, twb, jumlah_kata, pengirim, nama_smc,
                pangkat_smc, jabatan_smc, twt, twk, sistem, paraf,
                barcode_data, logo_url, created_at
            ) VALUES (
                :kategori, :no_bra, :nomor_urut, :satker_kode, :bulan_tahun,
                :id_operasi, :nama_operasi, :panggilan, :jenis, :nr_stn, :derajat,
                :instruksi_penerusan, :dari, :aksi, :info, :sifat, :menbra, :perihal,
                :tanggal_waktu, :twu, :twb, :jumlah_kata, :pengirim, :nama_smc,
                :pangkat_smc, :jabatan_smc, :twt, :twk, :sistem, :paraf,
                :barcode_data, :logo_url, :created_at
            )");

            $now = date('Y-m-d H:i:s');
            $tanggalWaktu = !empty($data['tanggal_waktu']) ? trim((string)$data['tanggal_waktu']) : TemplateHelper::formatTanggalIndo(date('Y-m-d'));

            $stmt->execute([
                ':kategori' => $kategori,
                ':no_bra' => $noBra,
                ':nomor_urut' => $nomorUrut,
                ':satker_kode' => $satkerKode,
                ':bulan_tahun' => $bulanTahun,
                ':id_operasi' => $idOps,
                ':nama_operasi' => $namaOps,
                ':panggilan' => strtoupper(trim((string)($data['panggilan'] ?? ''))),
                ':jenis' => strtoupper(trim((string)($data['jenis'] ?? 'SAR'))),
                ':nr_stn' => strtoupper(trim((string)($data['nr_stn'] ?? ''))),
                ':derajat' => strtoupper(trim((string)($data['derajat'] ?? 'SGR'))),
                ':instruksi_penerusan' => strtoupper(trim((string)($data['instruksi_penerusan'] ?? ''))),
                ':dari' => $dari,
                ':aksi' => $aksi,
                ':info' => $info,
                ':sifat' => strtoupper(trim((string)($data['sifat'] ?? ''))),
                ':menbra' => (strtoupper(trim((string)($data['menbra'] ?? ''))) === 'SEGERA' || trim((string)($data['menbra'] ?? '')) === '-') ? '' : strtoupper(trim((string)($data['menbra'] ?? ''))),
                ':perihal' => $perihal,
                ':tanggal_waktu' => $tanggalWaktu,
                ':twu' => $twu,
                ':twb' => $twb,
                ':jumlah_kata' => $wordCount,
                ':pengirim' => $pengirim,
                ':nama_smc' => $namaSmc,
                ':pangkat_smc' => $pangkatSmc,
                ':jabatan_smc' => $jabatanSmc,
                ':twt' => strtoupper(trim((string)($data['twt'] ?? ''))),
                ':twk' => strtoupper(trim((string)($data['twk'] ?? ''))),
                ':sistem' => strtoupper(trim((string)($data['sistem'] ?? ''))),
                ':paraf' => strtoupper(trim((string)($data['paraf'] ?? ''))),
                ':barcode_data' => $data['barcode_data'] ?? '',
                ':logo_url' => $data['logo_url'] ?? '',
                ':created_at' => $now
            ]);

            $idBra = (int)$db->lastInsertId();

            // Insert Sections terlebih dahulu agar tersedia saat token QR dihitung
            if (!empty($sections)) {
                $stmtSec = $db->prepare("INSERT INTO BraSection (id_bra, kode, isi) VALUES (:id_bra, :kode, :isi)");
                foreach ($sections as $sec) {
                    if (empty($sec['isi'])) continue;
                    $stmtSec->execute([
                        ':id_bra' => $idBra,
                        ':kode' => $sec['kode'],
                        ':isi' => $sec['isi']
                    ]);
                }
            }

            // Set barcode_data dengan token verifikasi kriptografis modern
            if (empty($data['barcode_data'])) {
                $verifyUrl = self::generateVerifyUrl([
                    'id_bra' => $idBra,
                    'no_bra' => $noBra,
                    'twu' => $twu,
                    'nama_smc' => $namaSmc,
                    'pangkat_smc' => $pangkatSmc,
                    'perihal' => $perihal,
                    'sections' => $sections
                ]);
                $db->prepare("UPDATE BraSar SET barcode_data = :bc WHERE id_bra = :id")->execute([
                    ':bc' => $verifyUrl,
                    ':id' => $idBra
                ]);
            }

            if ($db->inTransaction()) {
                $db->commit();
            }
            AuditHelper::log('BRA_CREATE', "Menerbitkan Naskah BRA {$kategori} No. {$noBra} (ID: {$idBra})", 'BraSar', (string)$idBra);
            return $idBra;
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
            $kategori = BraHelper::normalizeCategory($data['kategori'] ?? 'OPERASI_SAR');

            // Sanitasi ketat huruf kapital baku telegram dan tanda baca militer
            $rawPerihal = trim((string)($data['perihal'] ?? ''));
            $perihal = BraHelper::ensureEndsWithTtk(BraHelper::sanitizeTelegramPunctuation($rawPerihal));

            $rawSections = $data['sections'] ?? [];
            $sanitizedSections = [];
            foreach ($rawSections as $sec) {
                $isi = trim((string)($sec['isi'] ?? ''));
                if ($isi === '') continue;
                $sanitizedSections[] = [
                    'id' => !empty($sec['id']) ? (int)$sec['id'] : null,
                    'kode' => strtoupper(trim((string)($sec['kode'] ?? 'AAA'))),
                    'isi' => BraHelper::sanitizeTelegramPunctuation($isi)
                ];
            }
            $sections = $sanitizedSections;

            $wordCount = !empty($data['jumlah_kata']) ? $data['jumlah_kata'] : (BraHelper::countBraWords($sections, $perihal) . ' KATA');

            $idOps = !empty($data['id_operasi']) ? (int)$data['id_operasi'] : null;
            $namaOps = mb_strtoupper(trim((string)($data['nama_operasi'] ?? '')), 'UTF-8');
            if ($idOps && empty($namaOps)) {
                $stmtOps = $db->prepare("SELECT nama_operasi FROM Operasi WHERE id_operasi = :id LIMIT 1");
                $stmtOps->execute([':id' => $idOps]);
                $namaOps = mb_strtoupper((string)($stmtOps->fetchColumn() ?: ''), 'UTF-8');
            }

            $parsed = BraHelper::parseNoBra($data['no_bra'] ?? '', $data['satker_kode'] ?? DEFAULT_SATKER_KODE);
            $nomorUrut = $parsed['nomor_urut'];
            $satkerKode = $parsed['satker_kode'];
            $bulanTahun = $parsed['bulan_tahun'];
            $noBra = $parsed['no_bra'];

            $dari = mb_strtoupper(trim((string)($data['dari'] ?? 'KANSAR KENDARI')), 'UTF-8');
            $aksi = mb_strtoupper(trim((string)($data['aksi'] ?? ($kategori === 'MAPEL' ? 'KA KSOP KENDARI KMA KA SROP KENDARI' : 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN'))), 'UTF-8');
            $info = mb_strtoupper(trim((string)($data['info'] ?? ($kategori === 'MAPEL' ? 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN' : 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN'))), 'UTF-8');
            $pengirim = mb_strtoupper(trim((string)($data['pengirim'] ?? 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI')), 'UTF-8');
            $namaSmc = mb_strtoupper(trim((string)($data['nama_smc'] ?? 'AMIRUDDIN A.S., S.Sos.')), 'UTF-8');
            $pangkatSmc = mb_strtoupper(trim((string)($data['pangkat_smc'] ?? 'PEMBINA (IV/a)')), 'UTF-8');
            $jabatanSmc = mb_strtoupper(trim((string)($data['jabatan_smc'] ?? ($kategori === 'MAPEL' ? 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI' : 'SMC'))), 'UTF-8');

            $twu = BraHelper::normalizeMilitaryTW($data['twu'] ?? null);
            $twb = BraHelper::normalizeMilitaryTW($data['twb'] ?? null);

            $stmt = $db->prepare("UPDATE BraSar SET
                kategori = :kategori,
                no_bra = :no_bra,
                nomor_urut = :nomor_urut,
                satker_kode = :satker_kode,
                bulan_tahun = :bulan_tahun,
                id_operasi = :id_operasi,
                nama_operasi = :nama_operasi,
                panggilan = :panggilan,
                jenis = :jenis,
                nr_stn = :nr_stn,
                derajat = :derajat,
                instruksi_penerusan = :instruksi_penerusan,
                dari = :dari,
                aksi = :aksi,
                info = :info,
                sifat = :sifat,
                menbra = :menbra,
                perihal = :perihal,
                tanggal_waktu = :tanggal_waktu,
                twu = :twu,
                twb = :twb,
                jumlah_kata = :jumlah_kata,
                pengirim = :pengirim,
                nama_smc = :nama_smc,
                pangkat_smc = :pangkat_smc,
                jabatan_smc = :jabatan_smc,
                twt = :twt,
                twk = :twk,
                sistem = :sistem,
                paraf = :paraf
                WHERE id_bra = :id");

            $stmt->execute([
                ':id' => $id,
                ':kategori' => $kategori,
                ':no_bra' => $noBra,
                ':nomor_urut' => $nomorUrut,
                ':satker_kode' => $satkerKode,
                ':bulan_tahun' => $bulanTahun,
                ':id_operasi' => $idOps,
                ':nama_operasi' => $namaOps ?: ($perihal ?: ''),
                ':panggilan' => strtoupper(trim((string)($data['panggilan'] ?? ''))),
                ':jenis' => strtoupper(trim((string)($data['jenis'] ?? 'SAR'))),
                ':nr_stn' => strtoupper(trim((string)($data['nr_stn'] ?? ''))),
                ':derajat' => strtoupper(trim((string)($data['derajat'] ?? 'SGR'))),
                ':instruksi_penerusan' => strtoupper(trim((string)($data['instruksi_penerusan'] ?? ''))),
                ':dari' => $dari,
                ':aksi' => $aksi,
                ':info' => $info,
                ':sifat' => strtoupper(trim((string)($data['sifat'] ?? ''))),
                ':menbra' => (strtoupper(trim((string)($data['menbra'] ?? ''))) === 'SEGERA' || trim((string)($data['menbra'] ?? '')) === '-') ? '' : strtoupper(trim((string)($data['menbra'] ?? ''))),
                ':perihal' => $perihal,
                ':tanggal_waktu' => !empty($data['tanggal_waktu']) ? trim((string)$data['tanggal_waktu']) : TemplateHelper::formatTanggalIndo(date('Y-m-d')),
                ':twu' => $twu,
                ':twb' => $twb,
                ':jumlah_kata' => $wordCount,
                ':pengirim' => $pengirim,
                ':nama_smc' => $namaSmc,
                ':pangkat_smc' => $pangkatSmc,
                ':jabatan_smc' => $jabatanSmc,
                ':twt' => strtoupper(trim((string)($data['twt'] ?? ''))),
                ':twk' => strtoupper(trim((string)($data['twk'] ?? ''))),
                ':sistem' => strtoupper(trim((string)($data['sistem'] ?? ''))),
                ':paraf' => strtoupper(trim((string)($data['paraf'] ?? '')))
            ]);

            // Differential update for sections to prevent ID escalation in BraSection
            if (isset($data['sections'])) {
                $stmtExisting = $db->prepare("SELECT id, kode, isi FROM BraSection WHERE id_bra = :id ORDER BY id ASC");
                $stmtExisting->execute([':id' => $id]);
                $existingRows = $stmtExisting->fetchAll();
                $existingIds = array_column($existingRows, 'id');

                $stmtUpdate = $db->prepare("UPDATE BraSection SET kode = :kode, isi = :isi WHERE id = :sec_id AND id_bra = :id_bra");
                $stmtInsert = $db->prepare("INSERT INTO BraSection (id_bra, kode, isi) VALUES (:id_bra, :kode, :isi)");

                $keptIds = [];
                $pool = $existingRows;

                foreach ($sections as $sec) {
                    if (empty($sec['isi'])) continue;
                    $kode = strtoupper($sec['kode'] ?? 'AAA');
                    $isi = $sec['isi'];
                    $secId = !empty($sec['id']) ? (int)$sec['id'] : null;

                    if ($secId && in_array($secId, $existingIds, true) && !in_array($secId, $keptIds, true)) {
                        $stmtUpdate->execute([':sec_id' => $secId, ':id_bra' => $id, ':kode' => $kode, ':isi' => $isi]);
                        $keptIds[] = $secId;
                        foreach ($pool as $pIdx => $pItem) {
                            if ((int)$pItem['id'] === $secId) {
                                unset($pool[$pIdx]);
                                break;
                            }
                        }
                    } elseif (!empty($pool)) {
                        $matchKey = null;
                        foreach ($pool as $pIdx => $pItem) {
                            if ($pItem['kode'] === $kode) {
                                $matchKey = $pIdx;
                                break;
                            }
                        }
                        if ($matchKey === null) {
                            $matchKey = array_key_first($pool);
                        }
                        $reusedId = (int)$pool[$matchKey]['id'];
                        unset($pool[$matchKey]);

                        $stmtUpdate->execute([':sec_id' => $reusedId, ':id_bra' => $id, ':kode' => $kode, ':isi' => $isi]);
                        $keptIds[] = $reusedId;
                    } else {
                        $stmtInsert->execute([':id_bra' => $id, ':kode' => $kode, ':isi' => $isi]);
                    }
                }

                $toDelete = array_diff($existingIds, $keptIds);
                if (!empty($toDelete)) {
                    $delPlaceholders = implode(',', array_fill(0, count($toDelete), '?'));
                    $stmtDel = $db->prepare("DELETE FROM BraSection WHERE id IN ({$delPlaceholders}) AND id_bra = ?");
                    $stmtDel->execute([...$toDelete, $id]);
                }
            }

            // Sync updated barcode_data verify token dengan checksum seksi dan data terkini
            $verifyUrl = self::generateVerifyUrl([
                'id_bra' => $id,
                'no_bra' => $noBra,
                'twu' => $twu,
                'nama_smc' => $namaSmc,
                'pangkat_smc' => $pangkatSmc,
                'perihal' => $perihal,
                'sections' => $sections
            ]);
            $db->prepare("UPDATE BraSar SET barcode_data = :bc WHERE id_bra = :id")->execute([
                ':bc' => $verifyUrl,
                ':id' => $id
            ]);

            if ($db->inTransaction()) {
                $db->commit();
            }
            if (!empty($toDelete)) {
                self::syncAutoIncrement('BraSection');
            }
            AuditHelper::log('BRA_UPDATE', "Memperbarui Naskah BRA No. {$noBra} (ID: {$id})", 'BraSar', (string)$id);
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
            $db->prepare("DELETE FROM BraSection WHERE id_bra = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM BraSar WHERE id_bra = :id")->execute([':id' => $id]);
            if ($db->inTransaction()) {
                $db->commit();
            }
            self::syncAutoIncrement();
            AuditHelper::log('BRA_DELETE', "Menghapus Naskah BRA (ID: {$id})", 'BraSar', (string)$id);
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Synchronize and reset BraSar & BraSection AUTO_INCREMENT to strictly MAX(id) + 1.
     * If $table is specified ('BraSar' or 'BraSection'), only that table will be checked and updated.
     */
    public static function syncAutoIncrement(?string $table = null): void {
        $db = getDB();
        if ($db->inTransaction()) {
            return;
        }
        try {
            if ($table === null || $table === 'BraSar') {
                $stmtBra = $db->query("SELECT COALESCE(MAX(id_bra), 0) FROM BraSar");
                $maxBra = (int)$stmtBra->fetchColumn();
                $stmtBra->closeCursor();
                $nextBra = max(1, $maxBra + 1);
                $db->exec("ALTER TABLE BraSar AUTO_INCREMENT = {$nextBra}");
            }

            if ($table === null || $table === 'BraSection') {
                $stmtSec = $db->query("SELECT COALESCE(MAX(id), 0) FROM BraSection");
                $maxSec = (int)$stmtSec->fetchColumn();
                $stmtSec->closeCursor();
                $nextSec = max(1, $maxSec + 1);
                $db->exec("ALTER TABLE BraSection AUTO_INCREMENT = {$nextSec}");
            }
        } catch (Throwable) {
            // Silently continue if DDL is restricted
        }
    }

    public static function getNextSequenceNumber(string $satkerKode = 'SAR-304', string $kategori = 'MAPEL'): array {
        return BraHelper::getNextNomorBra($kategori, $satkerKode);
    }

    /**
     * Menghasilkan tanda tangan digital / token verifikasi kriptografis resmi BRA SAR
     * Memakai HMAC-SHA256 yang memvalidasi integritas penuh: ID, No BRA, TWU, SMC ter-standarisasi, Perihal, dan Checksum Seksi (AAA-ZZZ)
     */
    public static function generateVerifyToken(array $bra): string {
        $id = (string)($bra['id_bra'] ?? '');
        $noBra = strtoupper(trim((string)($bra['no_bra'] ?? '')));
        $twu = strtoupper(trim((string)($bra['twu'] ?? '')));
        // Normalisasi nama dan pangkat SMC agar tahan terhadap variasi tanda titik/spasi gelar
        $smc = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)($bra['nama_smc'] ?? ''))));
        $pangkat = preg_replace('/[^A-Z0-9]/', '', strtoupper(trim((string)($bra['pangkat_smc'] ?? ''))));
        $perihal = strtoupper(trim((string)($bra['perihal'] ?? '')));

        // Checksum isi seluruh seksi berita (AAA-ZZZ)
        $sections = $bra['sections'] ?? [];
        if (empty($sections) && !empty($id) && is_numeric($id)) {
            try {
                $db = getDB();
                $stmtSec = $db->prepare("SELECT kode, isi FROM BraSection WHERE id_bra = :id ORDER BY id ASC");
                $stmtSec->execute([':id' => (int)$id]);
                $sections = $stmtSec->fetchAll();
            } catch (Throwable) {
                $sections = [];
            }
        }

        $secSignatures = [];
        if (is_array($sections)) {
            foreach ($sections as $s) {
                $k = strtoupper(trim((string)($s['kode'] ?? '')));
                $i = trim((string)($s['isi'] ?? ''));
                if ($k !== '' || $i !== '') {
                    $normalizedIsi = preg_replace('/\s+/', ' ', $i);
                    $secSignatures[] = "{$k}=" . md5($normalizedIsi);
                }
            }
        }
        $sectionsHash = implode(';', $secSignatures);

        $salt = defined('APP_KEY') ? APP_KEY : 'BASARNAS_KENDARI_BRA_SECRET_SALT_2026';
        $payload = "BASARNAS_BRA:{$id}:{$noBra}:{$twu}:{$smc}:{$pangkat}:" . md5($perihal) . ":{$sectionsHash}";
        return strtoupper(substr(hash_hmac('sha256', $payload, $salt), 0, 16));
    }

    /**
     * Menghasilkan token dengan payload mentah (unnormalized SMC) untuk backward compatibility
     */
    public static function generateVerifyTokenRaw(array $bra): string {
        $id = (string)($bra['id_bra'] ?? '');
        $noBra = strtoupper(trim((string)($bra['no_bra'] ?? '')));
        $twu = strtoupper(trim((string)($bra['twu'] ?? '')));
        $smc = strtoupper(trim((string)($bra['nama_smc'] ?? '')));
        $pangkat = strtoupper(trim((string)($bra['pangkat_smc'] ?? '')));
        $perihal = strtoupper(trim((string)($bra['perihal'] ?? '')));

        $sections = $bra['sections'] ?? [];
        if (empty($sections) && !empty($id) && is_numeric($id)) {
            try {
                $db = getDB();
                $stmtSec = $db->prepare("SELECT kode, isi FROM BraSection WHERE id_bra = :id ORDER BY id ASC");
                $stmtSec->execute([':id' => (int)$id]);
                $sections = $stmtSec->fetchAll();
            } catch (Throwable) {
                $sections = [];
            }
        }

        $secSignatures = [];
        if (is_array($sections)) {
            foreach ($sections as $s) {
                $k = strtoupper(trim((string)($s['kode'] ?? '')));
                $i = trim((string)($s['isi'] ?? ''));
                if ($k !== '' || $i !== '') {
                    $normalizedIsi = preg_replace('/\s+/', ' ', $i);
                    $secSignatures[] = "{$k}=" . md5($normalizedIsi);
                }
            }
        }
        $sectionsHash = implode(';', $secSignatures);

        $salt = defined('APP_KEY') ? APP_KEY : 'BASARNAS_KENDARI_BRA_SECRET_SALT_2026';
        $payload = "BASARNAS_BRA:{$id}:{$noBra}:{$twu}:{$smc}:{$pangkat}:" . md5($perihal) . ":{$sectionsHash}";
        return strtoupper(substr(hash_hmac('sha256', $payload, $salt), 0, 16));
    }

    /**
     * Menghasilkan token versi lama (legacy) untuk backward compatibility
     */
    public static function generateLegacyVerifyToken(array $bra): string {
        $id = (string)($bra['id_bra'] ?? '');
        $noBra = (string)($bra['no_bra'] ?? '');
        $twu = (string)($bra['twu'] ?? '');
        $salt = defined('APP_KEY') ? APP_KEY : 'BASARNAS_KENDARI_BRA_SECRET_SALT_2026';
        $payload = "BASARNAS_BRA:{$id}:{$noBra}:{$twu}";
        return strtoupper(substr(hash_hmac('sha256', $payload, $salt), 0, 16));
    }

    /**
     * Validasi tanda tangan digital / token verifikasi BRA SAR (Multi-tier resilient check)
     */
    public static function verifyToken(array $bra, ?string $token): bool {
        if ($token === null || trim($token) === '') {
            return false;
        }
        $cleanToken = strtoupper(trim($token));

        // 1. Cek kecocokan token modern ternormalisasi (resilient terhadap format nama/gelar)
        $expectedModern = self::generateVerifyToken($bra);
        if (hash_equals($expectedModern, $cleanToken)) {
            return true;
        }

        // 2. Fallback verifikasi token modern unnormalized (dibuat sebelum standardisasi gelar)
        $expectedRaw = self::generateVerifyTokenRaw($bra);
        if (hash_equals($expectedRaw, $cleanToken)) {
            return true;
        }

        // 3. Fallback verifikasi token legacy (untuk dokumen yang telah dicetak paling awal)
        $expectedLegacy = self::generateLegacyVerifyToken($bra);
        return hash_equals($expectedLegacy, $cleanToken);
    }

    /**
     * Menghasilkan URL verifikasi publik resmi BRA SAR dengan token tanda tangan
     */
    public static function generateVerifyUrl(array $bra): string {
        $id = (int)($bra['id_bra'] ?? 0);
        $token = self::generateVerifyToken($bra);
        return BASE_URL . "/bra.php?view={$id}&token={$token}";
    }
}
