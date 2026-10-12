<?php
/**
 * QR-SAR - Berita Radio SAR (BRA) Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class BraModel {
    public static function getAll(?string $kategori = null, ?string $search = null): array {
        $db = getDB();
        $sql = "SELECT b.*,
            (SELECT COUNT(*) FROM BraSection s WHERE s.id_bra = b.id_bra) as total_sections
            FROM BraSar b WHERE 1=1";
        
        $params = [];
        if ($kategori && in_array(strtoupper($kategori), ['MAPEL', 'SAR_UMUM', 'PERGERAKAN_KAPAL'])) {
            $sql .= " AND b.kategori = :kategori";
            $params[':kategori'] = strtoupper($kategori);
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

        // Attach sections for each BRA
        foreach ($bras as &$bra) {
            $stmtSec = $db->prepare("SELECT kode, isi FROM BraSection WHERE id_bra = :id ORDER BY id ASC");
            $stmtSec->execute([':id' => $bra['id_bra']]);
            $bra['sections'] = $stmtSec->fetchAll();
        }

        return $bras;
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
            $kategori = strtoupper($data['kategori'] ?? 'MAPEL');
            $satkerKode = $data['satker_kode'] ?? DEFAULT_SATKER_KODE;

            // Generate sequence and no_bra if not provided
            if (empty($data['no_bra'])) {
                $next = BraHelper::getNextNomorBra($kategori, $satkerKode);
                $nomorUrut = $next['nomor_urut'];
                $noBra = $next['no_bra'];
                $bulanTahun = $next['bulan_tahun'];
            } else {
                $noBra = $data['no_bra'];
                $nomorUrut = (int)($data['nomor_urut'] ?? 1);
                $bulanTahun = $data['bulan_tahun'] ?? BraHelper::getBulanTahunSuffix();
            }

            $sections = $data['sections'] ?? [];
            $wordCount = $data['jumlah_kata'] ?? (BraHelper::countBraWords($sections) . ' KATA');

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
            $idOps = !empty($data['id_operasi']) ? (int)$data['id_operasi'] : null;

            $stmt->execute([
                ':kategori' => $kategori,
                ':no_bra' => $noBra,
                ':nomor_urut' => $nomorUrut,
                ':satker_kode' => $satkerKode,
                ':bulan_tahun' => $bulanTahun,
                ':id_operasi' => $idOps,
                ':nama_operasi' => $data['nama_operasi'] ?? '',
                ':panggilan' => $data['panggilan'] ?? '',
                ':jenis' => $data['jenis'] ?? '',
                ':nr_stn' => $data['nr_stn'] ?? '',
                ':derajat' => $data['derajat'] ?? 'SGR',
                ':instruksi_penerusan' => $data['instruksi_penerusan'] ?? '',
                ':dari' => $data['dari'] ?? 'KANSAR KENDARI',
                ':aksi' => $data['aksi'] ?? 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
                ':info' => $data['info'] ?? 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
                ':sifat' => $data['sifat'] ?? '',
                ':menbra' => $data['menbra'] ?? '',
                ':perihal' => $data['perihal'] ?? '',
                ':tanggal_waktu' => $data['tanggal_waktu'] ?? '',
                ':twu' => $data['twu'] ?? BraHelper::formatMilitaryTW(),
                ':twb' => $data['twb'] ?? BraHelper::formatMilitaryTW(),
                ':jumlah_kata' => $wordCount,
                ':pengirim' => $data['pengirim'] ?? 'KANSAR KENDARI',
                ':nama_smc' => $data['nama_smc'] ?? 'AMIRUDDIN A.S., S.Sos.',
                ':pangkat_smc' => $data['pangkat_smc'] ?? 'PEMBINA (IV/a)',
                ':jabatan_smc' => $data['jabatan_smc'] ?? 'KEPALA KANTOR / SMC',
                ':twt' => $data['twt'] ?? '',
                ':twk' => $data['twk'] ?? '',
                ':sistem' => $data['sistem'] ?? '',
                ':paraf' => $data['paraf'] ?? '',
                ':barcode_data' => $data['barcode_data'] ?? '',
                ':logo_url' => $data['logo_url'] ?? '',
                ':created_at' => $now
            ]);

            $idBra = (int)$db->lastInsertId();

            // Set default barcode_data if empty
            if (empty($data['barcode_data'])) {
                $verifyUrl = BASE_URL . "/bra.php?view={$idBra}";
                $db->prepare("UPDATE BraSar SET barcode_data = :bc WHERE id_bra = :id")->execute([
                    ':bc' => $verifyUrl,
                    ':id' => $idBra
                ]);
            }

            // Insert Sections
            if (!empty($sections)) {
                $stmtSec = $db->prepare("INSERT INTO BraSection (id_bra, kode, isi) VALUES (:id_bra, :kode, :isi)");
                foreach ($sections as $sec) {
                    if (empty($sec['isi'])) continue;
                    $stmtSec->execute([
                        ':id_bra' => $idBra,
                        ':kode' => strtoupper($sec['kode'] ?? 'AAA'),
                        ':isi' => $sec['isi']
                    ]);
                }
            }

            $db->commit();
            return $idBra;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function update(int $id, array $data): bool {
        $db = getDB();
        $db->beginTransaction();

        try {
            $sections = $data['sections'] ?? [];
            $wordCount = $data['jumlah_kata'] ?? (BraHelper::countBraWords($sections) . ' KATA');

            $idOps = !empty($data['id_operasi']) ? (int)$data['id_operasi'] : null;

            $stmt = $db->prepare("UPDATE BraSar SET
                kategori = :kategori,
                no_bra = :no_bra,
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
                ':kategori' => strtoupper($data['kategori'] ?? 'MAPEL'),
                ':no_bra' => $data['no_bra'],
                ':id_operasi' => $idOps,
                ':nama_operasi' => $data['nama_operasi'] ?? ($data['perihal'] ?? ''),
                ':panggilan' => $data['panggilan'] ?? '',
                ':jenis' => $data['jenis'] ?? '',
                ':nr_stn' => $data['nr_stn'] ?? '',
                ':derajat' => $data['derajat'] ?? 'SGR',
                ':instruksi_penerusan' => $data['instruksi_penerusan'] ?? '',
                ':dari' => $data['dari'] ?? 'KANSAR KENDARI',
                ':aksi' => $data['aksi'] ?? '',
                ':info' => $data['info'] ?? '',
                ':sifat' => $data['sifat'] ?? '',
                ':menbra' => $data['menbra'] ?? '',
                ':perihal' => $data['perihal'] ?? '',
                ':tanggal_waktu' => $data['tanggal_waktu'] ?? '',
                ':twu' => $data['twu'] ?? '',
                ':twb' => $data['twb'] ?? '',
                ':jumlah_kata' => $wordCount,
                ':pengirim' => $data['pengirim'] ?? '',
                ':nama_smc' => $data['nama_smc'] ?? '',
                ':pangkat_smc' => $data['pangkat_smc'] ?? '',
                ':jabatan_smc' => $data['jabatan_smc'] ?? '',
                ':twt' => $data['twt'] ?? '',
                ':twk' => $data['twk'] ?? '',
                ':sistem' => $data['sistem'] ?? '',
                ':paraf' => $data['paraf'] ?? ''
            ]);

            // Replace sections
            if (isset($data['sections'])) {
                $db->prepare("DELETE FROM BraSection WHERE id_bra = :id")->execute([':id' => $id]);
                $stmtSec = $db->prepare("INSERT INTO BraSection (id_bra, kode, isi) VALUES (:id_bra, :kode, :isi)");
                foreach ($sections as $sec) {
                    if (empty($sec['isi'])) continue;
                    $stmtSec->execute([
                        ':id_bra' => $id,
                        ':kode' => strtoupper($sec['kode'] ?? 'AAA'),
                        ':isi' => $sec['isi']
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
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM BraSection WHERE id_bra = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM BraSar WHERE id_bra = :id")->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            return false;
        }
    }

    public static function getNextSequenceNumber(string $satkerKode = 'SAR-304', string $kategori = 'MAPEL'): array {
        return BraHelper::getNextNomorBra($kategori, $satkerKode);
    }
}
