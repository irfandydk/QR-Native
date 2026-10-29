<?php
/**
 * QR-SAR - Document Numbering Service (Standar Penomoran Resmi Naskah Dinas Basarnas)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dedicated domain service for generating standardized administrative document numbers
 * for Laporan Awal (LA), Rencana Operasi (RENOPS), Situational Report (SITREP),
 * and Berita Acara Penutupan Operasi (BA-TUTUP).
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
     * Penomoran dinas resmi Laporan Awal Operasi SAR Basarnas:
     * Format: NO: LA-[TANGGAL]-[URUT]/OPS-SAR/[KODE_SATKER]/[BULAN][TAHUN]
     * Contoh: LA-210926-001/OPS-SAR/SAR-304/0926
     */
    public static function generateNomorLaporanAwal(array $ops, ?string $satkerKode = null): string {
        $waktu = null;
        if (!empty($ops['tanggal_lapor'])) {
            $waktu = trim($ops['tanggal_lapor'] . ' ' . ($ops['waktu_lapor'] ?? '00:00:00'));
        } elseif (!empty($ops['waktu_lapor'])) {
            $waktu = $ops['waktu_lapor'];
        } elseif (!empty($ops['waktu_berangkat'])) {
            $waktu = $ops['waktu_berangkat'];
        } elseif (!empty($ops['waktu_kejadian'])) {
            $waktu = $ops['waktu_kejadian'];
        } elseif (!empty($ops['created_at'])) {
            $waktu = $ops['created_at'];
        }

        $ts = SarCalculationService::parseToTimestamp($waktu);
        if ($ts === null) $ts = time();

        $tgl = date('dmy', $ts);
        $blnthn = date('my', $ts);
        $urutNum = (int)($ops['id_operasi'] ?? 1);
        if ($urutNum <= 0) $urutNum = 1;
        $urut = str_pad((string)$urutNum, 3, '0', STR_PAD_LEFT);
        $kode = self::resolveSatkerKode($satkerKode);

        return "LA-{$tgl}-{$urut}/OPS-SAR/{$kode}/{$blnthn}";
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

    /**
     * Penomoran dinas resmi Laporan Perkembangan Operasi SAR (SITREP):
     * Format: SITREP-[TANGGAL]-[HARI_KE]/OPS-SAR/[KODE_SATKER]/[BULAN][TAHUN]
     * Contoh: SITREP-220926-H1/OPS-SAR/SAR-304/0926
     */
    public static function generateNomorSitrep(array $ops, array $laporan, ?string $satkerKode = null): string {
        $waktu = $laporan['waktu_debrief'] ?? ($laporan['waktu_laporan'] ?? null);
        $ts = SarCalculationService::parseToTimestamp($waktu) ?? time();

        $tgl = date('dmy', $ts);
        $blnthn = date('my', $ts);
        $hariKe = strtoupper(str_replace(['.', ' ', '-'], '', (string)($laporan['hari_ke'] ?? 'H1')));
        if ($hariKe === '') $hariKe = 'H1';
        $kode = self::resolveSatkerKode($satkerKode);

        return "SITREP-{$tgl}-{$hariKe}/OPS-SAR/{$kode}/{$blnthn}";
    }

    /**
     * Penomoran Berita Acara Penutupan Operasi SAR:
     * Format: BA-TUTUP-[TANGGAL]-[URUT]/OPS-SAR/[KODE_SATKER]/[BULAN][TAHUN]
     */
    public static function generateNomorBeritaAcara(array $ops, array $laporan = [], ?string $satkerKode = null): string {
        $waktu = $laporan['waktu_debrief'] ?? ($ops['waktu_selesai'] ?? null);
        $ts = SarCalculationService::parseToTimestamp($waktu) ?? time();

        $tgl = date('dmy', $ts);
        $blnthn = date('my', $ts);
        $urutNum = (int)($ops['id_operasi'] ?? 1);
        if ($urutNum <= 0) $urutNum = 1;
        $urut = str_pad((string)$urutNum, 3, '0', STR_PAD_LEFT);
        $kode = self::resolveSatkerKode($satkerKode);

        return "BA-TUTUP-{$tgl}-{$urut}/OPS-SAR/{$kode}/{$blnthn}";
    }
}
