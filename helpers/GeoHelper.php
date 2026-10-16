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
        $latDeg = (int)floor($absLat);
        $latMinFloat = ($absLat - $latDeg) * 60;
        $latMin = (int)floor($latMinFloat);
        $latSec = (int)round(($latMinFloat - $latMin) * 60);
        if ($latSec >= 60) {
            $latSec = 0;
            $latMin++;
            if ($latMin >= 60) {
                $latMin = 0;
                $latDeg++;
            }
        }

        $lngDir = $lng < 0 ? 'W' : 'E';
        $absLng = abs($lng);
        $lngDeg = (int)floor($absLng);
        $lngMinFloat = ($absLng - $lngDeg) * 60;
        $lngMin = (int)floor($lngMinFloat);
        $lngSec = (int)round(($lngMinFloat - $lngMin) * 60);
        if ($lngSec >= 60) {
            $lngSec = 0;
            $lngMin++;
            if ($lngMin >= 60) {
                $lngMin = 0;
                $lngDeg++;
            }
        }

        $latStr = sprintf("%02d° %02d' %02d\" %s", $latDeg, $latMin, $latSec, $latDir);
        $lngStr = sprintf("%03d° %02d' %02d\" %s", $lngDeg, $lngMin, $lngSec, $lngDir);

        return "{$latStr} - {$lngStr}";
    }

    /**
     * Parse DMS or Decimal coordinate string to Decimal latitude and longitude array ['lat' => float, 'lng' => float]
     * Supports:
     * - Standard DMS: "04° 01' 04\" S - 122° 34' 10\" E"
     * - Indonesian DMS: "04° 01' 04.5\" S - 122° 34' 10.2\" T" (U/S/T/B)
     * - Comma-separated DMS: "04° 01' 04\" S, 122° 34' 10\" E"
     * - Negative/Positive Decimal Degrees: "-4.037694, 122.486694" or "-4.037694 - 122.486694"
     */
    public static function parseCoordinates(?string $coordString): ?array {
        if (!$coordString) return null;
        $clean = trim($coordString);
        if ($clean === '' || $clean === '-') return null;

        // 1. Direct Decimal Degrees pair: e.g. "-4.037694, 122.486694" or "-4.037694 - 122.486694"
        if (preg_match('/^\s*(-?\d{1,2}(?:\.\d+)?)\s*(LU|LS|[NSU])?\s*(?:[,;\/]|\s+[-–—]\s+|\s+)\s*(-?\d{1,3}(?:\.\d+)?)\s*(BT|BB|[EWTB])?\s*$/i', $clean, $mDec)) {
            $lat = (float)$mDec[1];
            $latDir = strtoupper($mDec[2] ?? '');
            if (($latDir === 'S' || $latDir === 'LS') && $lat > 0) $lat = -$lat;

            $lng = (float)$mDec[3];
            $lngDir = strtoupper($mDec[4] ?? '');
            if (($lngDir === 'W' || $lngDir === 'B' || $lngDir === 'BB') && $lng > 0) $lng = -$lng;

            if ($lat >= -90.0 && $lat <= 90.0 && $lng >= -180.0 && $lng <= 180.0) {
                return ['lat' => $lat, 'lng' => $lng];
            }
        }

        // 2. Split DMS / mixed coordinate pair safely without breaking leading negative sign
        $parts = preg_split('/(?:\s+[-–—]\s+|[,;\/]|(?<=[NSEWUSTBnsewustb"\'”″])\s*[-–—]\s*)/u', $clean);
        if (!$parts || count($parts) < 2) {
            // Fallback: split on hyphen not at index 0
            $parts = preg_split('/(?<!^)\s*[-–—]\s*/u', $clean, 2);
        }
        if (!$parts || count($parts) < 2) return null;

        $lat = self::dmsPartToDecimal(trim($parts[0]));
        $lng = self::dmsPartToDecimal(trim($parts[1]));

        if ($lat === null || $lng === null) return null;
        if ($lat < -90.0 || $lat > 90.0 || $lng < -180.0 || $lng > 180.0) return null;

        return ['lat' => $lat, 'lng' => $lng];
    }

    public static function dmsPartToDecimal(string $str): ?float {
        $trimmed = trim($str);
        if ($trimmed === '') return null;

        // Match DMS: deg° min' sec" [N/S/E/W or U/S/T/B or LU/LS/BT/BB]
        if (preg_match('/^(-?\d{1,3})\s*(?:°|deg|\s)\s*(\d{1,2})\s*(?:[\'’′]|min|\s)\s*(\d{1,2}(?:\.\d+)?)\s*(?:["”″]|sec|s)?\s*(LU|LS|BT|BB|[NSEWUSTB])?$/iu', $trimmed, $m)) {
            $deg = (float)$m[1];
            $min = (float)$m[2];
            $sec = (float)$m[3];
            $dir = isset($m[4]) ? strtoupper($m[4]) : '';

            $dec = abs($deg) + ($min / 60.0) + ($sec / 3600.0);
            if ($deg < 0 || in_array($dir, ['S', 'LS', 'W', 'B', 'BB'], true)) {
                $dec = -$dec;
            }
            return $dec;
        }

        // Match DM (Degrees + Decimal Minutes): deg° min' [N/S/E/W or U/S/T/B or LU/LS/BT/BB]
        if (preg_match('/^(-?\d{1,3})\s*(?:°|deg|\s)\s*(\d{1,2}(?:\.\d+)?)\s*(?:[\'’′]|min)?\s*(LU|LS|BT|BB|[NSEWUSTB])?$/iu', $trimmed, $mDm)) {
            $deg = (float)$mDm[1];
            $min = (float)$mDm[2];
            $dir = isset($mDm[3]) ? strtoupper($mDm[3]) : '';

            $dec = abs($deg) + ($min / 60.0);
            if ($deg < 0 || in_array($dir, ['S', 'LS', 'W', 'B', 'BB'], true)) {
                $dec = -$dec;
            }
            return $dec;
        }

        // Match Decimal with optional compass suffix/prefix: e.g. "-4.037694" or "4.037694 S"
        if (preg_match('/^(LU|LS|BT|BB|[NSEWUSTB])?\s*(-?\d+(?:\.\d+)?)\s*°?\s*(LU|LS|BT|BB|[NSEWUSTB])?$/iu', $trimmed, $mDec)) {
            $dir = strtoupper(($mDec[1] ?? '') ?: ($mDec[3] ?? ''));
            $val = (float)$mDec[2];
            if ($val < 0 || in_array($dir, ['S', 'LS', 'W', 'B', 'BB'], true)) {
                $val = -abs($val);
            }
            return $val;
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

    /**
     * Decodes a Google Maps encoded polyline string into an array of [lat, lng] coordinates
     */
    public static function decodeGooglePolyline(string $encoded): array {
        $length = strlen($encoded);
        $index = 0;
        $points = [];
        $lat = 0;
        $lng = 0;

        while ($index < $length) {
            $shift = 0;
            $result = 0;
            do {
                if ($index >= $length) break;
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            $shift = 0;
            $result = 0;
            do {
                if ($index >= $length) break;
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = [
                'lat' => round($lat * 1e-5, 6),
                'lng' => round($lng * 1e-5, 6)
            ];
        }
        return $points;
    }
}
