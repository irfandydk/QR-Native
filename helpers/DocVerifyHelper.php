<?php
/**
 * QR-SAR - Verifikasi QR lama Dokumen RENOPS (token HMAC sebelum format SMEAC bertoken per versi)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dokumen RENOPS format baru diverifikasi lewat RenopsDokumenPublisher/RenopsDokumenModel (token per versi).
 * Helper ini hanya menjaga QR RENOPS lama tetap dapat dipindai: rumus token tidak boleh diubah.
 */

declare(strict_types=1);

class DocVerifyHelper {
    public const TYPE_RENOPS = 'renops';

    /** Tipe dokumen yang masih dapat diverifikasi lewat QR lama; selain RENOPS = null (tidak valid). */
    public static function normalizeType(?string $rawType): ?string {
        return strtolower(trim((string)$rawType)) === self::TYPE_RENOPS ? self::TYPE_RENOPS : null;
    }

    public static function getDocTitle(?array $docData = null): string {
        return 'Rencana Operasi SAR (RENOPS) ' . strtoupper(trim((string)($docData['hari_ke'] ?? 'H.1')));
    }

    public static function getDocNumber(array $operasi, ?array $docData = null, string $satkerKode = 'SAR-304'): string {
        try {
            return TemplateHelper::generateNomorRenops($operasi, $docData ?? [], $satkerKode);
        } catch (Throwable) {
            $id = (int)($operasi['id_operasi'] ?? 0);
            return "OPS-{$id}/{$satkerKode}/" . date('Y');
        }
    }

    public static function getDocTimestamp(?array $docData = null): string {
        return (string)($docData['waktu_briefing'] ?? ($docData['tanggal_laporan'] ?? date('Y-m-d H:i:s')));
    }

    /**
     * Ekstraksi Nama SMC Resmi
     */
    public static function getSmcName(?array $defaultSmc = null, ?array $docData = null): string {
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
     * Token HMAC-SHA256 QR RENOPS lama (16 karakter hex uppercase).
     */
    public static function generateToken(array $operasi, ?array $docData = null, ?string $smcName = null): string {
        $idOps = (int)($operasi['id_operasi'] ?? 0);
        $refId = (int)($docData['id_renops'] ?? 0);
        $cleanWaktu = date('Y-m-d', strtotime(self::getDocTimestamp($docData)) ?: time());
        $namaOps = mb_strtoupper(trim((string)($operasi['nama_operasi'] ?? '')), 'UTF-8');
        $smc = mb_strtoupper(trim((string)($smcName ?: self::getSmcName(null, $docData))), 'UTF-8');

        $salt = defined('APP_KEY') ? APP_KEY : 'BASARNAS_KENDARI_SECURE_HMAC_KEY_V2';
        $payload = 'BASARNAS_OPS_DOC:' . self::TYPE_RENOPS . ":{$idOps}:{$refId}:{$cleanWaktu}:{$smc}:{$namaOps}";

        return strtoupper(substr(hash_hmac('sha256', $payload, $salt), 0, 16));
    }

    /**
     * Token fallback yang lebih fleksibel (tanpa tanggal spesifik jika format tanggal berubah)
     */
    public static function generateLegacyToken(array $operasi, ?array $docData = null): string {
        $idOps = (int)($operasi['id_operasi'] ?? 0);
        $refId = (int)($docData['id_renops'] ?? 0);
        $salt = defined('APP_KEY') ? APP_KEY : 'BASARNAS_KENDARI_SECURE_HMAC_KEY_V2';
        $payload = 'BASARNAS_OPS_DOC:' . self::TYPE_RENOPS . ":{$idOps}:{$refId}";
        return strtoupper(substr(hash_hmac('sha256', $payload, $salt), 0, 16));
    }

    /**
     * Memvalidasi token QR RENOPS lama.
     */
    public static function verifyToken(array $operasi, ?array $docData, ?string $token): bool {
        if ($token === null || trim($token) === '') {
            return false;
        }
        $cleanToken = strtoupper(trim($token));

        // 1. Token yang tersimpan saat dokumen pertama kali dicetak (tidak berubah walau SMC / nama operasi diganti)
        $stored = self::getStoredToken($operasi, $docData);
        if ($stored !== null && hash_equals($stored, $cleanToken)) {
            return true;
        }

        // 2. Token dihitung dari data terkini (SMC default saat ini)
        if (hash_equals(self::generateToken($operasi, $docData), $cleanToken)) {
            return true;
        }

        // 3. Dokumen cetakan lama: token dibuat dengan SMC default saat itu, coba semua pejabat terdaftar
        try {
            foreach (PejabatModel::getAll() as $pejabat) {
                if (!empty($pejabat['nama']) && hash_equals(self::generateToken($operasi, $docData, (string)$pejabat['nama']), $cleanToken)) {
                    return true;
                }
            }
        } catch (Throwable) {
            // Tabel Pejabat tidak tersedia: lanjut ke fallback berikutnya
        }

        // 4. Fallback token legacy
        return hash_equals(self::generateLegacyToken($operasi, $docData), $cleanToken);
    }

    /**
     * Token tersimpan untuk RENOPS ini, atau null bila belum pernah dicetak / tabel belum dimigrasi.
     */
    public static function getStoredToken(array $operasi, ?array $docData = null): ?string {
        $refId = (int)($docData['id_renops'] ?? 0);
        if ($refId <= 0) return null;
        try {
            $stmt = getDB()->prepare("SELECT token FROM DokumenToken WHERE doc_type = :t AND ref_id = :r AND id_operasi = :o LIMIT 1");
            $stmt->execute([':t' => self::TYPE_RENOPS, ':r' => $refId, ':o' => (int)($operasi['id_operasi'] ?? 0)]);
            $token = $stmt->fetchColumn();
            return $token !== false ? (string)$token : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Hapus token tersimpan saat dokumen dihapus, agar ID yang dipakai ulang tidak mewarisi token lama.
     */
    public static function forgetOperasiTokens(int $idOperasi): void {
        self::deleteTokens("DELETE FROM DokumenToken WHERE id_operasi = ?", [$idOperasi]);
    }

    public static function forgetRenopsTokens(int $idRenops): void {
        self::deleteTokens("DELETE FROM DokumenToken WHERE doc_type = ? AND ref_id = ?", [self::TYPE_RENOPS, $idRenops]);
    }

    private static function deleteTokens(string $sql, array $params): void {
        try {
            getDB()->prepare($sql)->execute($params);
        } catch (Throwable) {
            // Tabel belum dimigrasi: tidak ada token tersimpan
        }
    }

    /**
     * URL cetak internal RENOPS dari halaman verifikasi.
     */
    public static function getPrintUrl(int $idOps, int $refId = 0): string {
        return BASE_URL . "/operasi.php?action=print_renops&id={$idOps}" . ($refId > 0 ? "&id_renops={$refId}" : '');
    }
}
