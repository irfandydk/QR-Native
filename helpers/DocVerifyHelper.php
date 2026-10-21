<?php
/**
 * QR-SAR - Document Verification & Cryptographic HMAC Token Helper
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Mengelola pembuatan token keabsahan digital, URL verifikasi publik,
 * dan audit tanda tangan elektronik pada dokumen resmi operasional SAR:
 * - Laporan Awal Operasi SAR
 * - Rencana Operasi SAR (RENOPS)
 * - Laporan Perkembangan Operasi SAR (SITREP / Lapbang)
 * - Berita Acara Pengusulan Penutupan Operasi SAR
 */

declare(strict_types=1);

class DocVerifyHelper {
    public const TYPE_LAPORAN_AWAL = 'laporan_awal';
    public const TYPE_RENOPS = 'renops';
    public const TYPE_SITREP = 'sitrep';
    public const TYPE_PENUTUPAN = 'penutupan';

    /**
     * Daftar tipe dokumen resmi yang didukung
     */
    public static function supportedTypes(): array {
        return [
            self::TYPE_LAPORAN_AWAL,
            self::TYPE_RENOPS,
            self::TYPE_SITREP,
            self::TYPE_PENUTUPAN
        ];
    }

    /**
     * Normalisasi tipe dokumen
     */
    public static function normalizeType(?string $rawType): string {
        $t = strtolower(trim((string)$rawType));
        return match ($t) {
            'lap_awal', 'laporan_awal', 'awal' => self::TYPE_LAPORAN_AWAL,
            'renops' => self::TYPE_RENOPS,
            'sitrep', 'lapbang', 'laporan_perkembangan' => self::TYPE_SITREP,
            'penutupan', 'berita_acara', 'ba_penutupan' => self::TYPE_PENUTUPAN,
            default => self::TYPE_LAPORAN_AWAL
        };
    }

    /**
     * Menghasilkan Judul Resmi Dokumen berdasarkan tipenya
     */
    public static function getDocTitle(string $type, array $operasi, ?array $docData = null): string {
        $normType = self::normalizeType($type);
        return match ($normType) {
            self::TYPE_LAPORAN_AWAL => 'Laporan Awal Operasi SAR',
            self::TYPE_RENOPS => 'Rencana Operasi SAR (RENOPS) ' . strtoupper(trim((string)($docData['hari_ke'] ?? 'H.1'))),
            self::TYPE_SITREP => 'Laporan Perkembangan Operasi SAR (SITREP) ' . strtoupper(trim((string)($docData['hari_ke'] ?? 'H.1'))),
            self::TYPE_PENUTUPAN => 'Berita Acara Pengusulan Penutupan Operasi SAR',
        };
    }

    /**
     * Menghasilkan Nomor Dokumen Resmi
     */
    public static function getDocNumber(string $type, array $operasi, ?array $docData = null, string $satkerKode = 'SAR-304'): string {
        $normType = self::normalizeType($type);
        try {
            return match ($normType) {
                self::TYPE_LAPORAN_AWAL => TemplateHelper::generateNomorLaporanAwal($operasi, $satkerKode),
                self::TYPE_RENOPS => TemplateHelper::generateNomorRenops($operasi, $docData ?? [], $satkerKode),
                self::TYPE_SITREP => TemplateHelper::generateNomorSitrep($operasi, $docData ?? [], $satkerKode),
                self::TYPE_PENUTUPAN => TemplateHelper::generateNomorBeritaAcara($operasi, $docData ?? [], $satkerKode),
            };
        } catch (Throwable) {
            $id = (int)($operasi['id_operasi'] ?? 0);
            return "OPS-{$id}/{$satkerKode}/" . date('Y');
        }
    }

    /**
     * Menghasilkan Waktu Utama Pengesahan Dokumen
     */
    public static function getDocTimestamp(string $type, array $operasi, ?array $docData = null): string {
        $normType = self::normalizeType($type);
        return match ($normType) {
            self::TYPE_LAPORAN_AWAL => (string)($operasi['waktu_lapor'] ?? ($operasi['created_at'] ?? date('Y-m-d H:i:s'))),
            self::TYPE_RENOPS => (string)($docData['waktu_briefing'] ?? ($docData['tanggal_laporan'] ?? date('Y-m-d H:i:s'))),
            self::TYPE_SITREP => (string)($docData['waktu_debrief'] ?? ($docData['waktu_laporan'] ?? date('Y-m-d H:i:s'))),
            self::TYPE_PENUTUPAN => (string)($docData['waktu_debrief'] ?? ($operasi['waktu_selesai'] ?? date('Y-m-d H:i:s'))),
        };
    }

    /**
     * Ekstraksi Nama SMC Resmi
     */
    public static function getSmcName(?array $defaultSmc = null, ?array $docData = null, ?array $operasi = null): string {
        if (!empty($docData['nama_smc'])) {
            return mb_strtoupper(trim((string)$docData['nama_smc']), 'UTF-8');
        }
        if (!empty($defaultSmc['nama'])) {
            return mb_strtoupper(trim((string)$defaultSmc['nama']), 'UTF-8');
        }
        try {
            if (class_exists('MasterModel')) {
                $masterSmc = MasterModel::getDefaultSmc();
                if (!empty($masterSmc['nama'])) {
                    return mb_strtoupper(trim((string)$masterSmc['nama']), 'UTF-8');
                }
            }
        } catch (Throwable) {
            // fallback
        }
        return 'AMIRUDDIN A.S., S.Sos.';
    }

    /**
     * Menghasilkan Token Kriptografis HMAC-SHA256 untuk Dokumen Operasi SAR
     * (16 Karakter Hex Uppercase)
     */
    public static function generateToken(string $type, array $operasi, ?array $docData = null, ?string $smcName = null): string {
        $normType = self::normalizeType($type);
        $idOps = (int)($operasi['id_operasi'] ?? 0);
        $refId = 0;

        if ($normType === self::TYPE_RENOPS) {
            $refId = (int)($docData['id_renops'] ?? 0);
        } elseif ($normType === self::TYPE_SITREP || $normType === self::TYPE_PENUTUPAN) {
            $refId = (int)($docData['id_laporan'] ?? 0);
        }

        $waktu = self::getDocTimestamp($normType, $operasi, $docData);
        $cleanWaktu = date('Y-m-d', strtotime($waktu) ?: time());
        $namaOps = mb_strtoupper(trim((string)($operasi['nama_operasi'] ?? '')), 'UTF-8');
        $smc = mb_strtoupper(trim((string)($smcName ?: self::getSmcName(null, $docData, $operasi))), 'UTF-8');

        $salt = defined('APP_KEY') ? APP_KEY : 'BASARNAS_KENDARI_SECURE_HMAC_KEY_V2';
        $payload = "BASARNAS_OPS_DOC:{$normType}:{$idOps}:{$refId}:{$cleanWaktu}:{$smc}:{$namaOps}";

        return strtoupper(substr(hash_hmac('sha256', $payload, $salt), 0, 16));
    }

    /**
     * Token fallback yang lebih fleksibel (tanpa tanggal spesifik jika format tanggal berubah)
     */
    public static function generateLegacyToken(string $type, array $operasi, ?array $docData = null): string {
        $normType = self::normalizeType($type);
        $idOps = (int)($operasi['id_operasi'] ?? 0);
        $refId = (int)($docData['id_renops'] ?? ($docData['id_laporan'] ?? 0));
        $salt = defined('APP_KEY') ? APP_KEY : 'BASARNAS_KENDARI_SECURE_HMAC_KEY_V2';
        $payload = "BASARNAS_OPS_DOC:{$normType}:{$idOps}:{$refId}";
        return strtoupper(substr(hash_hmac('sha256', $payload, $salt), 0, 16));
    }

    /**
     * Memvalidasi Token Tanda Tangan Dokumen Operasi SAR
     */
    public static function verifyToken(string $type, array $operasi, ?array $docData, ?string $token): bool {
        if ($token === null || trim($token) === '') {
            return false;
        }
        $cleanToken = strtoupper(trim($token));

        // 1. Verifikasi token utama
        $expected = self::generateToken($type, $operasi, $docData);
        if (hash_equals($expected, $cleanToken)) {
            return true;
        }

        // 2. Fallback token legacy
        $expectedLegacy = self::generateLegacyToken($type, $operasi, $docData);
        if (hash_equals($expectedLegacy, $cleanToken)) {
            return true;
        }

        return false;
    }

    /**
     * Menghasilkan URL Verifikasi Publik Resmi
     */
    public static function generateVerifyUrl(string $type, array $operasi, ?array $docData = null): string {
        $normType = self::normalizeType($type);
        $idOps = (int)($operasi['id_operasi'] ?? 0);
        $token = self::generateToken($normType, $operasi, $docData);

        $params = [
            'action' => 'verify',
            'type' => $normType,
            'id' => $idOps
        ];

        if ($normType === self::TYPE_RENOPS && !empty($docData['id_renops'])) {
            $params['ref'] = (int)$docData['id_renops'];
        } elseif (($normType === self::TYPE_SITREP || $normType === self::TYPE_PENUTUPAN) && !empty($docData['id_laporan'])) {
            $params['ref'] = (int)$docData['id_laporan'];
        }

        $params['token'] = $token;

        return BASE_URL . '/operasi.php?' . http_build_query($params);
    }

    /**
     * URL tautan cetak langsung dari dokumen
     */
    public static function getPrintUrl(string $type, int $idOps, int $refId = 0): string {
        $normType = self::normalizeType($type);
        return match ($normType) {
            self::TYPE_LAPORAN_AWAL => BASE_URL . "/operasi.php?action=print_laporan_awal&id={$idOps}",
            self::TYPE_RENOPS => BASE_URL . "/operasi.php?action=print_renops&id={$idOps}" . ($refId > 0 ? "&id_renops={$refId}" : ""),
            self::TYPE_SITREP => BASE_URL . "/operasi.php?action=print_sitrep&id={$idOps}" . ($refId > 0 ? "&id_laporan={$refId}" : ""),
            self::TYPE_PENUTUPAN => BASE_URL . "/operasi.php?action=print_penutupan&id={$idOps}" . ($refId > 0 ? "&id_laporan={$refId}" : ""),
        };
    }
}
