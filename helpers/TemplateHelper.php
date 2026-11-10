<?php
/**
 * QR-SAR - Template Helper (Facade & Compatibility Bridge)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Provides backward-compatible static API proxying calls to specialized services:
 * - SarCalculationService (Date formatting, operational calculations, victim stats)
 * - DocumentNumberService (Standard Basarnas official document numbering)
 * - WhatsAppTemplateService (Operational message templates for WA / Telegram)
 * - GeoHelper (DMS coordinates formatting)
 */

declare(strict_types=1);

require_once __DIR__ . '/../services/SarCalculationService.php';
require_once __DIR__ . '/../services/DocumentNumberService.php';
require_once __DIR__ . '/../services/WhatsAppTemplateService.php';
require_once __DIR__ . '/GeoHelper.php';

class TemplateHelper {
    public const HARI_INDO = SarCalculationService::HARI_INDO;
    public const BULAN_INDO = SarCalculationService::BULAN_INDO;

    // --- SAR CALCULATION DELEGATES ---

    public static function parseToTimestamp(?string $dateInput): ?int {
        return SarCalculationService::parseToTimestamp($dateInput);
    }

    public static function formatTanggalIndo(?string $dateInput): string {
        return SarCalculationService::formatTanggalIndo($dateInput);
    }

    public static function formatTanggalSimple(?string $dateInput): string {
        return SarCalculationService::formatTanggalSimple($dateInput);
    }

    public static function formatJamWITA(?string $dateInput): string {
        return SarCalculationService::formatJamWITA($dateInput);
    }

    public static function formatTW(?string $dateInput): string {
        return SarCalculationService::formatTW($dateInput);
    }

    public static function getWaktuMulaiAwal(?array $operasi): string {
        return SarCalculationService::getWaktuMulaiAwal($operasi);
    }

    public static function getWaktuMulaiAktual(?array $operasi, bool $ignoreReopen = false): string {
        return SarCalculationService::getWaktuMulaiAktual($operasi, $ignoreReopen);
    }

    public static function getBaseHariSebelumReopen(?array $operasi): int {
        return SarCalculationService::getBaseHariSebelumReopen($operasi);
    }

    public static function hitungHariKe(string|array|null $waktuOrOperasi, ?string $targetDate = null, ?string $fallbackHariKe = null): string {
        return SarCalculationService::hitungHariKe($waktuOrOperasi, $targetDate, $fallbackHariKe);
    }

    /** Bilangan bulat -> kata Indonesia ("21" -> "dua puluh satu"). Mendukung hingga triliunan. */
    public static function terbilang(int $n): string {
        if ($n < 0) return 'minus ' . self::terbilang(-$n);
        $s = ['nol', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
        if ($n < 12) return $s[$n];
        if ($n < 20) return $s[$n - 10] . ' belas';
        if ($n < 100) return trim($s[intdiv($n, 10)] . ' puluh ' . ($n % 10 ? $s[$n % 10] : ''));
        if ($n < 200) return trim('seratus ' . ($n % 100 ? self::terbilang($n % 100) : ''));
        if ($n < 1000) return trim($s[intdiv($n, 100)] . ' ratus ' . ($n % 100 ? self::terbilang($n % 100) : ''));
        if ($n < 2000) return trim('seribu ' . ($n % 1000 ? self::terbilang($n % 1000) : ''));
        foreach ([[1000000000000, 'triliun'], [1000000000, 'miliar'], [1000000, 'juta'], [1000, 'ribu']] as [$unit, $name]) {
            if ($n >= $unit) {
                return trim(self::terbilang(intdiv($n, $unit)) . " {$name} " . ($n % $unit ? self::terbilang($n % $unit) : ''));
            }
        }
        return (string)$n;
    }

    public static function hitungResponseTime(?string $waktuLapor, ?string $waktuBerangkat): array {
        return SarCalculationService::hitungResponseTime($waktuLapor, $waktuBerangkat);
    }

    public static function hitungPelaksanaanOperasi(array $op, ?string $nowDate = null): array {
        return SarCalculationService::hitungPelaksanaanOperasi($op, $nowDate);
    }

    public static function hitungStatistikKorban(array $korbanList, ?int $totalPob = null): array {
        return SarCalculationService::hitungStatistikKorban($korbanList, $totalPob);
    }

    public static function getLabelStatusKorban(string $status): string {
        return SarCalculationService::getLabelStatusKorban($status);
    }

    // --- GEOSPATIAL DELEGATES ---

    public static function formatDMS(float|string|null $val, bool $isLat, bool $useIndoDir = false): string {
        return GeoHelper::formatDMS($val, $isLat, $useIndoDir);
    }

    public static function formatDmsPair(float|string|null $lat, float|string|null $lng, bool $useIndoDir = false): string {
        return GeoHelper::formatDmsPair($lat, $lng, $useIndoDir);
    }

    // --- DOCUMENT NUMBERING DELEGATES ---

    public static function generateNomorRenops(array $ops, array $renops, ?string $satkerKode = null): string {
        return DocumentNumberService::generateNomorRenops($ops, $renops, $satkerKode);
    }

    // --- WHATSAPP & TELEGRAM TEMPLATE DELEGATES ---

    public static function generateLaporanAwalWA(array $ops, array $korban = [], array $unsur = [], array $alut = []): string {
        return WhatsAppTemplateService::generateLaporanAwalWA($ops, $korban, $unsur, $alut);
    }

    public static function generateRenopsWA(array $ops, array $renops, array $srus = [], array $korban = [], array $unsur = [], array $alut = []): string {
        return WhatsAppTemplateService::generateRenopsWA($ops, $renops, $srus, $korban, $unsur, $alut);
    }

    public static function generateLapbangWA(array $ops, array $laporan, array $korban = [], array $unsur = [], array $alut = [], bool $isPenutupan = false): string {
        return WhatsAppTemplateService::generateLapbangWA($ops, $laporan, $korban, $unsur, $alut, $isPenutupan);
    }
}
