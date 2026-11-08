<?php
/**
 * QR-SAR - Document Numbering Service (Standar Penomoran Resmi Naskah Dinas Basarnas)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Nomor naskah dinas Rencana Operasi SAR (RENOPS).
 */

declare(strict_types=1);

class DocumentNumberService {
    /**
     * Resolve Satker Code with fallback to SettingsModel or default Kendari SAR-304.
     */
    protected static function resolveSatkerKode(?string $satkerKode): string {
        if ($satkerKode && trim($satkerKode) !== '') {
            return strtoupper(trim($satkerKode));
        }
        if (class_exists('SettingsModel')) {
            try {
                $st = SettingsModel::get();
                $code = $st['satker_kode'] ?? 'SAR-304';
                return strtoupper(trim((string)$code)) ?: 'SAR-304';
            } catch (Throwable $e) {
                // Fallback
            }
        }
        return 'SAR-304';
    }

    /**
     * Penomoran dinas resmi Rencana Operasi SAR (RENOPS):
     * Format: RENOPS-[TANGGAL]-[HARI_KE]/OPS-SAR/[KODE_SATKER]/[BULAN][TAHUN]
     * Contoh: RENOPS-220926-H1/OPS-SAR/SAR-304/0926
     */
    public static function generateNomorRenops(array $ops, array $renops, ?string $satkerKode = null): string {
        $waktu = $renops['waktu_briefing'] ?? ($renops['tanggal_laporan'] ?? ($ops['waktu_berangkat'] ?? null));
        $ts = SarCalculationService::parseToTimestamp($waktu) ?? time();

        $tgl = date('dmy', $ts);
        $blnthn = date('my', $ts);
        $hariKe = strtoupper(str_replace(['.', ' ', '-'], '', (string)($renops['hari_ke'] ?? 'H1')));
        if ($hariKe === '') $hariKe = 'H1';
        $kode = self::resolveSatkerKode($satkerKode);

        return "RENOPS-{$tgl}-{$hariKe}/OPS-SAR/{$kode}/{$blnthn}";
    }
}
