<?php
/**
 * QR-SAR - Geographic & Coordinate Conversion Helper
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class GeoHelper {
    public const KANSAR_KENDARI_LAT = -4.037694;
    public const KANSAR_KENDARI_LNG = 122.486694;

    /**
     * Convert Decimal Degrees to Basarnas standard DMS formatted string
     * e.g. -4.017778, 122.569444 -> 04° 01' 04" S - 122° 34' 10" E
     */
    public static function decimalToDms(float $lat, float $lng): string {
        $latDir = $lat < 0 ? 'S' : 'N';
        $absLat = abs($lat);
        $latDeg = floor($absLat);
        $latMinFloat = ($absLat - $latDeg) * 60;
        $latMin = floor($latMinFloat);
        $latSec = round(($latMinFloat - $latMin) * 60);

        $lngDir = $lng < 0 ? 'W' : 'E';
        $absLng = abs($lng);
        $lngDeg = floor($absLng);
        $lngMinFloat = ($absLng - $lngDeg) * 60;
        $lngMin = floor($lngMinFloat);
        $lngSec = round(($lngMinFloat - $lngMin) * 60);

        $latStr = sprintf("%02d° %02d' %02d\" %s", $latDeg, $latMin, $latSec, $latDir);
        $lngStr = sprintf("%03d° %02d' %02d\" %s", $lngDeg, $lngMin, $lngSec, $lngDir);

        return "{$latStr} - {$lngStr}";
    }

    /**
     * Parse DMS string to Decimal latitude and longitude array [lat, lng]
     */
    public static function parseCoordinates(?string $coordString): ?array {
        if (!$coordString) return null;
        $parts = preg_split('/[-–—]/', $coordString);
        if (count($parts) < 2) return null;

        $lat = self::dmsPartToDecimal(trim($parts[0]));
        $lng = self::dmsPartToDecimal(trim($parts[1]));

        if ($lat === null || $lng === null) return null;
        return ['lat' => $lat, 'lng' => $lng];
    }

    public static function dmsPartToDecimal(string $str): ?float {
        if (preg_match('/(\d{1,3})[°\s]+(\d{1,2})[\'\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NSEWnsew])?/i', trim($str), $m)) {
            $deg = (float)$m[1];
            $min = (float)$m[2];
            $sec = (float)$m[3];
            $dir = isset($m[4]) ? strtoupper($m[4]) : '';

            $dec = $deg + ($min / 60) + ($sec / 3600);
            if ($dir === 'S' || $dir === 'W') {
                $dec = -$dec;
            }
            return $dec;
        }

        // Check if already decimal number
        if (is_numeric(trim($str))) {
            return (float)trim($str);
        }

        return null;
    }

    /**
     * Calculate Nautical Miles distance using Haversine formula
     */
    public static function calculateDistanceNm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $km = self::calculateDistanceKm($lat1, $lon1, $lat2, $lon2);
        return round($km * 0.539957, 1); // 1 km = 0.539957 NM
    }

    /**
     * Calculate Kilometers distance using Haversine formula
     */
    public static function calculateDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadiusKm * $c, 2);
    }

    /**
     * Calculate initial bearing (heading) in degrees (0 - 360) and cardinal direction
     */
    public static function calculateBearing(float $lat1, float $lon1, float $lat2, float $lon2): array {
        $y = sin(deg2rad($lon2 - $lon1)) * cos(deg2rad($lat2));
        $x = cos(deg2rad($lat1)) * sin(deg2rad($lat2)) -
             sin(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($lon2 - $lon1));

        $bearing = fmod(rad2deg(atan2($y, $x)) + 360, 360);
        $bearingRound = round($bearing);

        $cardinals = [
            'Utara', 'Timur Laut', 'Timur', 'Tenggara',
            'Selatan', 'Barat Daya', 'Barat', 'Barat Laut', 'Utara'
        ];
        $cardinalIndex = (int)round($bearing / 45);
        $cardinal = $cardinals[$cardinalIndex] ?? 'Utara';

        return [
            'degrees' => $bearingRound,
            'cardinal' => $cardinal,
            'label' => "{$bearingRound}° {$cardinal}"
        ];
    }

    /**
     * Generate search pattern waypoints
     */
    public static function generatePatternWaypoints(
        string $patternType,
        float $centerLat,
        float $centerLng,
        float $trackSpacingNm = 1.0,
        int $legsCount = 5
    ): array {
        $nmToDegLat = 1 / 60; // 1 NM in latitude degrees
        $nmToDegLng = 1 / (60 * cos(deg2rad($centerLat))); // 1 NM in longitude degrees
        $spacingLat = $trackSpacingNm * $nmToDegLat;
        $spacingLng = $trackSpacingNm * $nmToDegLng;

        $waypoints = [];

        switch ($patternType) {
            case 'creeping_line':
                for ($i = 0; $i < $legsCount; $i++) {
                    $offsetLat = ($i - floor($legsCount / 2)) * $spacingLat;
                    $lat = $centerLat + $offsetLat;
                    $lngA = $centerLng - (2.5 * $spacingLng);
                    $lngB = $centerLng + (2.5 * $spacingLng);

                    if ($i % 2 === 0) {
                        $waypoints[] = ['lat' => $lat, 'lng' => $lngA, 'label' => "Leg " . ($i + 1) . "A"];
                        $waypoints[] = ['lat' => $lat, 'lng' => $lngB, 'label' => "Leg " . ($i + 1) . "B"];
                    } else {
                        $waypoints[] = ['lat' => $lat, 'lng' => $lngB, 'label' => "Leg " . ($i + 1) . "A"];
                        $waypoints[] = ['lat' => $lat, 'lng' => $lngA, 'label' => "Leg " . ($i + 1) . "B"];
                    }
                }
                break;

            case 'expanding_square':
                $currLat = $centerLat;
                $currLng = $centerLng;
                $waypoints[] = ['lat' => $currLat, 'lng' => $currLng, 'label' => 'Datum (LKP)'];

                $dirs = [
                    [0, 1],   // East
                    [-1, 0],  // South
                    [0, -1],  // West
                    [1, 0]    // North
                ];

                $step = 1;
                $legIdx = 1;
                while ($legIdx <= $legsCount) {
                    for ($d = 0; $d < 4 && $legIdx <= $legsCount; $d++) {
                        $dist = ceil($step / 2) * $spacingLng;
                        $currLat += $dirs[$d][0] * ceil($step / 2) * $spacingLat;
                        $currLng += $dirs[$d][1] * $dist;
                        $waypoints[] = ['lat' => $currLat, 'lng' => $currLng, 'label' => "WP-{$legIdx}"];
                        $step++;
                        $legIdx++;
                    }
                }
                break;

            case 'sector_search':
                $radiusNm = $trackSpacingNm * 3;
                $radiusLat = $radiusNm * $nmToDegLat;
                $radiusLng = $radiusNm * $nmToDegLng;

                $waypoints[] = ['lat' => $centerLat, 'lng' => $centerLng, 'label' => 'Datum (Center)'];
                for ($angle = 0; $angle < 360; $angle += 120) {
                    $rad = deg2rad($angle);
                    $wpLat = $centerLat + sin($rad) * $radiusLat;
                    $wpLng = $centerLng + cos($rad) * $radiusLng;
                    $waypoints[] = ['lat' => $wpLat, 'lng' => $wpLng, 'label' => "Out {$angle}°"];

                    $radNext = deg2rad($angle + 120);
                    $nextLat = $centerLat + sin($radNext) * $radiusLat;
                    $nextLng = $centerLng + cos($radNext) * $radiusLng;
                    $waypoints[] = ['lat' => $nextLat, 'lng' => $nextLng, 'label' => "Cross " . ($angle + 120) . "°"];
                    $waypoints[] = ['lat' => $centerLat, 'lng' => $centerLng, 'label' => "Return Datum"];
                }
                break;

            case 'parallel_track':
            default:
                for ($i = 0; $i < $legsCount; $i++) {
                    $offsetLng = ($i - floor($legsCount / 2)) * $spacingLng;
                    $lng = $centerLng + $offsetLng;
                    $latTop = $centerLat + (2.5 * $spacingLat);
                    $latBottom = $centerLat - (2.5 * $spacingLat);

                    if ($i % 2 === 0) {
                        $waypoints[] = ['lat' => $latTop, 'lng' => $lng, 'label' => "Leg " . ($i + 1) . " Start"];
                        $waypoints[] = ['lat' => $latBottom, 'lng' => $lng, 'label' => "Leg " . ($i + 1) . " End"];
                    } else {
                        $waypoints[] = ['lat' => $latBottom, 'lng' => $lng, 'label' => "Leg " . ($i + 1) . " Start"];
                        $waypoints[] = ['lat' => $latTop, 'lng' => $lng, 'label' => "Leg " . ($i + 1) . " End"];
                    }
                }
                break;
        }

        return $waypoints;
    }
}
