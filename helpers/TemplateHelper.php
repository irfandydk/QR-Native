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

    public static function getNamaHariIndo(string|int|null $input): string {
        return SarCalculationService::getNamaHariIndo($input);
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

    public static function decimalToDms(float|string|null $lat, float|string|null $lng, bool $useIndoDir = false): string {
        return GeoHelper::decimalToDms($lat, $lng, $useIndoDir);
    }

    public static function parseCoordinates(?string $text): ?array {
        return GeoHelper::parseCoordinates($text);
    }

    public static function parseCoordinatesString(?string $text): ?array {
        return GeoHelper::parseCoordinatesString($text);
    }

    // --- DOCUMENT NUMBERING DELEGATES ---

    public static function generateNomorLaporanAwal(array $ops, ?string $satkerKode = null): string {
        return DocumentNumberService::generateNomorLaporanAwal($ops, $satkerKode);
    }

    public static function generateNomorRenops(array $ops, array $renops, ?string $satkerKode = null): string {
        return DocumentNumberService::generateNomorRenops($ops, $renops, $satkerKode);
    }

    public static function generateNomorSitrep(array $ops, array $laporan, ?string $satkerKode = null): string {
        return DocumentNumberService::generateNomorSitrep($ops, $laporan, $satkerKode);
    }

    public static function formatNomorRenops(array $renops, array $ops = [], ?string $satkerKode = null): string {
        return DocumentNumberService::formatNomorRenops($renops, $ops, $satkerKode);
    }

    public static function formatNomorSitrep(array $laporan, array $ops = [], ?string $satkerKode = null): string {
        return DocumentNumberService::formatNomorSitrep($laporan, $ops, $satkerKode);
    }

    public static function generateNomorBeritaAcara(array $ops, array $laporan = [], ?string $satkerKode = null): string {
        return DocumentNumberService::generateNomorBeritaAcara($ops, $laporan, $satkerKode);
    }

    // --- WHATSAPP & TELEGRAM TEMPLATE DELEGATES ---

    public static function getHeaderWA(string $judulLaporan, string $namaOperasi, ?string $waktu = null, ?string $nomor = null): string {
        return WhatsAppTemplateService::getHeaderWA($judulLaporan, $namaOperasi, $waktu, $nomor);
    }

    public static function getFooterWA(): string {
        return WhatsAppTemplateService::getFooterWA();
    }

    public static function cleanSruCoordinates(?string $koor): string {
        return WhatsAppTemplateService::cleanSruCoordinates($koor);
    }

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
