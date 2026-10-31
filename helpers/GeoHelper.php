<?php
/**
 * QR-SAR - Geospatial & Geodetic Helper
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dedicated domain helper for earth geometry, Haversine distance, radial bearings,
 * spherical polygon area in Nautical Miles, and DMS coordinate conversions.
 */

declare(strict_types=1);

class GeoHelper {
    /** Koordinat Pangkalan Utama Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari */
    public const KANSAR_KENDARI_LAT = -4.084194;
    public const KANSAR_KENDARI_LNG = 122.428389;

    /**
     * Format a single decimal degree coordinate into DMS (Degrees, Minutes, Seconds).
     */
    public static function formatDMS(float|string|null $val, bool $isLat, bool $useIndoDir = false): string {
        if ($val === null || $val === '') return '-';
        $v = (float)$val;
        $dir = $isLat ? ($v < 0 ? 'S' : ($useIndoDir ? 'U' : 'N')) : ($v < 0 ? ($useIndoDir ? 'B' : 'W') : ($useIndoDir ? 'T' : 'E'));
        $abs = abs($v);
        $d = (int)floor($abs);
        $mFloat = ($abs - $d) * 60;
        $m = (int)floor($mFloat);
        $sFloat = ($mFloat - $m) * 60;
        $s = round($sFloat, 1);
        if ($s >= 60.0) {
            $s = 0.0;
            $m++;
            if ($m >= 60) {
                $m = 0;
                $d++;
            }
        }
        $dPad = $isLat ? str_pad((string)$d, 2, '0', STR_PAD_LEFT) : str_pad((string)$d, 3, '0', STR_PAD_LEFT);
        $mPad = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        $sPad = str_pad(number_format($s, 1, '.', ''), 4, '0', STR_PAD_LEFT);
        return "{$dPad}° {$mPad}' {$sPad}\" {$dir}";
    }

    /**
     * Format coordinate pair (Lat, Lng) into DMS string: e.g. "03° 58' 30.0" S - 122° 35' 12.0" E".
     */
    public static function formatDmsPair(float|string|null $lat, float|string|null $lng, bool $useIndoDir = false): string {
        return self::formatDMS($lat, true, $useIndoDir) . " - " . self::formatDMS($lng, false, $useIndoDir);
    }

    /**
     * Alias for formatDmsPair() to maintain backward compatibility with decimalToDms callers.
     */
    public static function decimalToDms(float|string|null $lat, float|string|null $lng, bool $useIndoDir = false): string {
        return self::formatDmsPair($lat, $lng, $useIndoDir);
    }

    /**
     * Calculate Haversine distance and bearing between two coordinates on Earth sphere.
     * Returns ['km' => float, 'nm' => float, 'bearing' => int, 'headingName' => string]
     */
    public static function calculateDistanceHaversine(float $lat1, float $lon1, float $lat2, float $lon2): array {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $earthRadiusKm * $c;
        $nm = $km * 0.539957;

        $y = sin($dLon) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) -
             sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos($dLon);
        $brng = fmod(rad2deg(atan2($y, $x)) + 360, 360);

        $headings = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
        $headingIdx = (int)round($brng / 45) % 8;

        return [
            'km' => round($km, 2),
            'nm' => round($nm, 2),
            'bearing' => (int)round($brng),
            'headingName' => $headings[$headingIdx]
        ];
    }

    /**
     * Hitung jarak Nautical Miles (NM) antara dua koordinat bola bumi.
     */
    public static function calculateDistanceNm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        return self::calculateDistanceHaversine($lat1, $lon1, $lat2, $lon2)['nm'];
    }

    /**
     * Hitung jarak Kilometer (KM) antara dua koordinat bola bumi.
     */
    public static function calculateDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        return self::calculateDistanceHaversine($lat1, $lon1, $lat2, $lon2)['km'];
    }

    /**
     * Hitung sudut azimuth / radial bearing (0-359 derajat) dari titik awal ke titik tujuan.
     */
    public static function calculateBearing(float $lat1, float $lon1, float $lat2, float $lon2): int {
        return self::calculateDistanceHaversine($lat1, $lon1, $lat2, $lon2)['bearing'];
    }

    /**
     * Compute exact geodesic / spherical polygon area in Nautical Miles squared (Nm²).
     */
    public static function calculatePolygonAreaNm2(array $points, ?string $shapeType = null, ?float $radiusNm = null, ?float $boxWidthNm = null, ?float $boxHeightNm = null): float {
        if ($shapeType === 'circle' && $radiusNm !== null && $radiusNm > 0) {
            return round(M_PI * $radiusNm * $radiusNm, 2);
        }
        if (($shapeType === 'box' || $shapeType === 'rectangle') && $boxWidthNm !== null && $boxHeightNm !== null && $boxWidthNm > 0 && $boxHeightNm > 0) {
            return round($boxWidthNm * $boxHeightNm, 2);
        }
        if (empty($points) || count($points) < 3) {
            return 0.0;
        }

        // Centroid / mean latitude for local nautical miles projection
        $sumLat = 0.0;
        foreach ($points as $p) {
            $sumLat += (float)($p['lat'] ?? 0);
        }
        $meanLatRad = deg2rad($sumLat / count($points));
        $cosMeanLat = cos($meanLatRad);

        // Convert lat/lng to local nautical miles (x, y)
        // 1 deg latitude = 60 Nm, 1 deg longitude = 60 * cos(lat) Nm
        $n = count($points);
        $areaSum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $x1 = (float)($points[$i]['lng'] ?? 0) * 60.0 * $cosMeanLat;
            $y1 = (float)($points[$i]['lat'] ?? 0) * 60.0;
            $x2 = (float)($points[$j]['lng'] ?? 0) * 60.0 * $cosMeanLat;
            $y2 = (float)($points[$j]['lat'] ?? 0) * 60.0;
            $areaSum += ($x1 * $y2) - ($x2 * $y1);
        }

        $areaNm2 = abs($areaSum) / 2.0;
        return round($areaNm2, 2);
    }

    /**
     * Parse text coordinate string in various formats (DMS, Decimal, Cardinal) into ['lat' => float, 'lng' => float].
     */
    public static function parseCoordinatesString(?string $text): ?array {
        if (!$text || trim($text) === '') return null;
        $clean = trim($text);

        // Replace Indonesian directions
        $norm = preg_replace('/\bLS\b/i', 'S', $clean);
        $norm = preg_replace('/\bLU\b/i', 'N', (string)$norm);
        $norm = preg_replace('/\bBT\b/i', 'E', (string)$norm);
        $norm = preg_replace('/\bBB\b/i', 'W', (string)$norm);

        // DMS format regex: 03° 58' 30" S - 122° 35' 12" E
        $dmsRegex = '/(\d{1,2})[°\s]+(\d{1,2})[\'\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NS])\s*[-–,\s\/]+\s*(\d{1,3})[°\s]+(\d{1,2})[\'\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([EW])/i';
        if (preg_match($dmsRegex, $norm, $m)) {
            $lat = (float)$m[1] + (float)$m[2] / 60 + (float)$m[3] / 3600;
            if (strtoupper($m[4]) === 'S') $lat = -$lat;
            $lng = (float)$m[5] + (float)$m[6] / 60 + (float)$m[7] / 3600;
            if (strtoupper($m[8]) === 'W') $lng = -$lng;
            return ['lat' => $lat, 'lng' => $lng];
        }

        // Decimal degrees regex: -3.9750, 122.5866
        $ddRegex = '/^([-+]?\d{1,2}(?:\.\d+)?)\s*[,;\s]\s*([-+]?\d{1,3}(?:\.\d+)?)$/';
        if (preg_match($ddRegex, $clean, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        return null;
    }

    /**
     * Alias for parseCoordinatesString() for backward compatibility.
     */
    public static function parseCoordinates(?string $text): ?array {
        return self::parseCoordinatesString($text);
    }
}
