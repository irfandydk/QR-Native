<?php
/**
 * QR-SAR - GIS & Map Plotting Model
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class GisModel {
    public static function getPoints(): array {
        $db = getDB();
        return $db->query("SELECT * FROM GisPoint ORDER BY id DESC")->fetchAll();
    }

    public static function savePoint(array $data): string {
        $db = getDB();
        $id = $data['id'] ?? ('pt-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));

        $stmt = $db->prepare("REPLACE INTO GisPoint (id, lat, lng, label, pointType, customIconUrl, description, category, alphabetChar) VALUES (:id, :lat, :lng, :label, :type, :customIcon, :desc, :category, :alphabetChar)");
        $stmt->execute([
            ':id' => $id,
            ':lat' => (float)$data['lat'],
            ':lng' => (float)$data['lng'],
            ':label' => isset($data['label']) ? trim((string)$data['label']) : '',
            ':type' => $data['pointType'] ?? ($data['point_type'] ?? 'pin_umum'),
            ':customIcon' => $data['customIconUrl'] ?? ($data['custom_icon_url'] ?? null),
            ':desc' => $data['description'] ?? '',
            ':category' => $data['category'] ?? 'Tanpa Kategori',
            ':alphabetChar' => $data['alphabetChar'] ?? 'A'
        ]);

        return $id;
    }

    public static function deletePoint(string $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM GisPoint WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public static function getPolygons(): array {
        $db = getDB();
        $polys = $db->query("SELECT * FROM GisPolygon ORDER BY id DESC")->fetchAll();
        if (empty($polys)) return [];

        $allPoints = $db->query("SELECT polygonId, lat, lng, label FROM GisPolygonPoint ORDER BY id ASC")->fetchAll();
        $pointsByPoly = [];
        foreach ($allPoints as $pt) {
            $pointsByPoly[$pt['polygonId']][] = [
                'lat' => (float)$pt['lat'],
                'lng' => (float)$pt['lng'],
                'label' => $pt['label'] ?? ''
            ];
        }

        foreach ($polys as &$p) {
            $p['points'] = $pointsByPoly[$p['id']] ?? [];
        }
        return $polys;
    }

    public static function savePolygon(array $data): string {
        $db = getDB();
        $db->beginTransaction();
        try {
            $id = $data['id'] ?? ('poly-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));

            $stmt = $db->prepare("REPLACE INTO GisPolygon (id, name, strokeColor, strokeWeight, fillColor, fillOpacity) VALUES (:id, :name, :sc, :sw, :fc, :fo)");
            $stmt->execute([
                ':id' => $id,
                ':name' => $data['name'] ?? 'Area Pencarian',
                ':sc' => $data['strokeColor'] ?? '#ea580c',
                ':sw' => (float)($data['strokeWeight'] ?? 2.5),
                ':fc' => $data['fillColor'] ?? '#ea580c',
                ':fo' => (float)($data['fillOpacity'] ?? 0.2)
            ]);

            $db->prepare("DELETE FROM GisPolygonPoint WHERE polygonId = :id")->execute([':id' => $id]);
            if (!empty($data['points']) && is_array($data['points'])) {
                $stmtPt = $db->prepare("INSERT INTO GisPolygonPoint (polygonId, lat, lng, label) VALUES (:id, :lat, :lng, :label)");
                foreach ($data['points'] as $pt) {
                    $stmtPt->execute([
                        ':id' => $id,
                        ':lat' => (float)$pt['lat'],
                        ':lng' => (float)$pt['lng'],
                        ':label' => $pt['label'] ?? ''
                    ]);
                }
            }

            $db->commit();
            return $id;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function deletePolygon(string $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM GisPolygonPoint WHERE polygonId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisPolygon WHERE id = :id")->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Throwable) {
            $db->rollBack();
            return false;
        }
    }

    public static function getRoutes(): array {
        $db = getDB();
        $routes = $db->query("SELECT * FROM GisRoute ORDER BY id DESC")->fetchAll();
        if (empty($routes)) return [];

        $allPoints = $db->query("SELECT routeId, lat, lng, label FROM GisRoutePoint ORDER BY id ASC")->fetchAll();
        $pointsByRoute = [];
        foreach ($allPoints as $pt) {
            $pointsByRoute[$pt['routeId']][] = [
                'lat' => (float)$pt['lat'],
                'lng' => (float)$pt['lng'],
                'label' => $pt['label'] ?? ''
            ];
        }

        foreach ($routes as &$r) {
            $r['vesselSpeedKnots'] = (float)($r['vesselSpeedKnots'] ?? 25);
            $r['strokeWeight'] = (float)($r['strokeWeight'] ?? 3.5);
            $r['strokeOpacity'] = (float)($r['strokeOpacity'] ?? 0.9);
            $r['points'] = $pointsByRoute[$r['id']] ?? [];
        }
        return $routes;
    }

    public static function saveRoute(array $data): string {
        $db = getDB();
        $db->beginTransaction();
        try {
            $id = $data['id'] ?? ('route-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));

            $stmt = $db->prepare("INSERT INTO GisRoute (
                id, name, vesselSpeedKnots, strokeColor, strokeWeight, strokeOpacity, createdAt
            ) VALUES (
                :id, :name, :speed, :color, :weight, :opacity, :created
            ) ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                vesselSpeedKnots = VALUES(vesselSpeedKnots),
                strokeColor = VALUES(strokeColor),
                strokeWeight = VALUES(strokeWeight),
                strokeOpacity = VALUES(strokeOpacity)");

            $stmt->execute([
                ':id' => $id,
                ':name' => $data['name'] ?? 'Rute Operasi',
                ':speed' => (float)($data['vesselSpeedKnots'] ?? 25),
                ':color' => $data['strokeColor'] ?? '#059669',
                ':weight' => (float)($data['strokeWeight'] ?? 3.5),
                ':opacity' => (float)($data['strokeOpacity'] ?? 0.9),
                ':created' => $data['createdAt'] ?? (date('Y-m-d H:i') . ' WITA')
            ]);

            $db->prepare("DELETE FROM GisRoutePoint WHERE routeId = :id")->execute([':id' => $id]);
            if (!empty($data['points']) && is_array($data['points'])) {
                $stmtPt = $db->prepare("INSERT INTO GisRoutePoint (routeId, lat, lng, label) VALUES (:id, :lat, :lng, :label)");
                foreach ($data['points'] as $pt) {
                    $stmtPt->execute([
                        ':id' => $id,
                        ':lat' => (float)$pt['lat'],
                        ':lng' => (float)$pt['lng'],
                        ':label' => $pt['label'] ?? ''
                    ]);
                }
            }

            $db->commit();
            return $id;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function deleteRoute(string $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM GisRoutePoint WHERE routeId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisRoute WHERE id = :id")->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Throwable) {
            $db->rollBack();
            return false;
        }
    }

    public static function getSearchPatterns(): array {
        $db = getDB();
        $patterns = $db->query("SELECT * FROM GisSearchPattern ORDER BY id DESC")->fetchAll();
        if (empty($patterns)) return [];

        $allWp = $db->query("SELECT searchPatternId, lat, lng, label FROM GisSearchPatternWaypoint ORDER BY id ASC")->fetchAll();
        $wpByPattern = [];
        foreach ($allWp as $wp) {
            $wpByPattern[$wp['searchPatternId']][] = [
                'lat' => (float)$wp['lat'],
                'lng' => (float)$wp['lng'],
                'label' => $wp['label'] ?? ''
            ];
        }

        foreach ($patterns as &$p) {
            $p['waypoints'] = $wpByPattern[$p['id']] ?? [];
        }
        return $patterns;
    }

    public static function saveSearchPattern(array $data): string {
        $db = getDB();
        $db->beginTransaction();
        try {
            $id = $data['id'] ?? ('pattern-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));

            $stmt = $db->prepare("REPLACE INTO GisSearchPattern (
                id, name, polygonId, polygonName, patternType, trackSpacingNm,
                legsCount, poskoId, poskoName, color, totalDistanceNm, createdAt
            ) VALUES (
                :id, :name, :polyId, :polyName, :pt, :spacing,
                :legs, :poskoId, :poskoName, :color, :dist, :created
            )");

            $stmt->execute([
                ':id' => $id,
                ':name' => $data['name'] ?? 'Pola Pencarian SAR',
                ':polyId' => $data['polygonId'] ?? '',
                ':polyName' => $data['polygonName'] ?? '',
                ':pt' => $data['patternType'] ?? 'parallel_track',
                ':spacing' => (float)($data['trackSpacingNm'] ?? 1.0),
                ':legs' => (int)($data['legsCount'] ?? 5),
                ':poskoId' => $data['poskoId'] ?? '',
                ':poskoName' => $data['poskoName'] ?? '',
                ':color' => $data['color'] ?? '#0284c7',
                ':dist' => (float)($data['totalDistanceNm'] ?? 0),
                ':created' => $data['createdAt'] ?? (date('Y-m-d H:i') . ' WITA')
            ]);

            $db->prepare("DELETE FROM GisSearchPatternWaypoint WHERE searchPatternId = :id")->execute([':id' => $id]);
            if (!empty($data['waypoints']) && is_array($data['waypoints'])) {
                $stmtWp = $db->prepare("INSERT INTO GisSearchPatternWaypoint (searchPatternId, lat, lng, label) VALUES (:id, :lat, :lng, :label)");
                foreach ($data['waypoints'] as $wp) {
                    $stmtWp->execute([
                        ':id' => $id,
                        ':lat' => (float)$wp['lat'],
                        ':lng' => (float)$wp['lng'],
                        ':label' => $wp['label'] ?? ''
                    ]);
                }
            }

            $db->commit();
            return $id;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public static function deleteSearchPattern(string $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM GisSearchPatternWaypoint WHERE searchPatternId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisSearchPattern WHERE id = :id")->execute([':id' => $id]);
            $db->commit();
            return true;
        } catch (Throwable) {
            $db->rollBack();
            return false;
        }
    }
}
