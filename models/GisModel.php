<?php
/**
 * QR-SAR - GIS & Map Plotting Model
 * KPP Kendari - Basarnas
 * Multi-Operation SAR Supported with Nullable Operation Scoping
 */

declare(strict_types=1);

class GisModel {
    private static bool $schemaChecked = false;

    /**
     * Ensure database tables have required columns for multi-operation SAR
     */
    public static function ensureSchemaUpdated(): void {
        if (self::$schemaChecked) return;
        self::$schemaChecked = true;
        try {
            $db = getDB();
            $cols = $db->query("SHOW COLUMNS FROM GisPoint LIKE 'id_operasi'")->fetchAll();
            if (empty($cols)) {
                $db->exec("ALTER TABLE GisPoint ADD COLUMN id_operasi INT NULL DEFAULT NULL");
                $db->exec("ALTER TABLE GisPoint ADD INDEX idx_point_operasi (id_operasi)");
                $db->exec("ALTER TABLE GisPolygon ADD COLUMN id_operasi INT NULL DEFAULT NULL");
                $db->exec("ALTER TABLE GisPolygon ADD INDEX idx_poly_operasi (id_operasi)");
                $db->exec("ALTER TABLE GisRoute ADD COLUMN id_operasi INT NULL DEFAULT NULL");
                $db->exec("ALTER TABLE GisRoute ADD INDEX idx_route_operasi (id_operasi)");
                $db->exec("ALTER TABLE GisSearchPattern ADD COLUMN id_operasi INT NULL DEFAULT NULL");
                $db->exec("ALTER TABLE GisSearchPattern ADD INDEX idx_pattern_operasi (id_operasi)");
            }
            // Ensure customIconUrl is MEDIUMTEXT to prevent truncation on large DataURLs
            $db->exec("ALTER TABLE GisPoint MODIFY COLUMN customIconUrl MEDIUMTEXT NULL");
        } catch (Throwable $e) {
            error_log("[GisModel Schema Check] " . $e->getMessage());
        }
    }

    public static function getPoints(?int $idOperasi = null, bool $includeGlobal = true): array {
        self::ensureSchemaUpdated();
        $db = getDB();
        if ($idOperasi !== null && $idOperasi > 0 && !$includeGlobal) {
            $stmt = $db->prepare("SELECT * FROM GisPoint WHERE id_operasi = :id_ops ORDER BY id DESC");
            $stmt->execute([':id_ops' => $idOperasi]);
            return $stmt->fetchAll();
        }
        return $db->query("SELECT * FROM GisPoint ORDER BY id DESC")->fetchAll();
    }

    public static function savePoint(array $data): string {
        self::ensureSchemaUpdated();
        $db = getDB();
        $id = $data['id'] ?? ('pt-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
        $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
            ? (int)$data['id_operasi']
            : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
        if ($idOperasi !== null && $idOperasi <= 0) {
            $idOperasi = null;
        }

        $stmt = $db->prepare("REPLACE INTO GisPoint (id, id_operasi, lat, lng, label, pointType, customIconUrl, description, category, alphabetChar) VALUES (:id, :id_ops, :lat, :lng, :label, :type, :customIcon, :desc, :category, :alphabetChar)");
        $stmt->execute([
            ':id' => $id,
            ':id_ops' => $idOperasi,
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
        self::ensureSchemaUpdated();
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM GisPoint WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public static function getPolygons(?int $idOperasi = null, bool $includeGlobal = true): array {
        self::ensureSchemaUpdated();
        $db = getDB();
        if ($idOperasi !== null && $idOperasi > 0 && !$includeGlobal) {
            $stmt = $db->prepare("SELECT * FROM GisPolygon WHERE id_operasi = :id_ops ORDER BY id DESC");
            $stmt->execute([':id_ops' => $idOperasi]);
            $polys = $stmt->fetchAll();
        } else {
            $polys = $db->query("SELECT * FROM GisPolygon ORDER BY id DESC")->fetchAll();
        }

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
        self::ensureSchemaUpdated();
        $db = getDB();
        $db->beginTransaction();
        try {
            $id = $data['id'] ?? ('poly-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
            $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
                ? (int)$data['id_operasi']
                : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
            if ($idOperasi !== null && $idOperasi <= 0) {
                $idOperasi = null;
            }

            $stmt = $db->prepare("REPLACE INTO GisPolygon (id, id_operasi, name, strokeColor, strokeWeight, fillColor, fillOpacity) VALUES (:id, :id_ops, :name, :sc, :sw, :fc, :fo)");
            $stmt->execute([
                ':id' => $id,
                ':id_ops' => $idOperasi,
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
        self::ensureSchemaUpdated();
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

    public static function getRoutes(?int $idOperasi = null, bool $includeGlobal = true): array {
        self::ensureSchemaUpdated();
        $db = getDB();
        if ($idOperasi !== null && $idOperasi > 0 && !$includeGlobal) {
            $stmt = $db->prepare("SELECT * FROM GisRoute WHERE id_operasi = :id_ops ORDER BY id DESC");
            $stmt->execute([':id_ops' => $idOperasi]);
            $routes = $stmt->fetchAll();
        } else {
            $routes = $db->query("SELECT * FROM GisRoute ORDER BY id DESC")->fetchAll();
        }

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
        self::ensureSchemaUpdated();
        $db = getDB();
        $db->beginTransaction();
        try {
            $id = $data['id'] ?? ('route-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
            $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
                ? (int)$data['id_operasi']
                : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
            if ($idOperasi !== null && $idOperasi <= 0) {
                $idOperasi = null;
            }

            $stmt = $db->prepare("INSERT INTO GisRoute (
                id, id_operasi, name, vesselSpeedKnots, strokeColor, strokeWeight, strokeOpacity, createdAt
            ) VALUES (
                :id, :id_ops, :name, :speed, :color, :weight, :opacity, :created
            ) ON DUPLICATE KEY UPDATE 
                id_operasi = VALUES(id_operasi),
                name = VALUES(name),
                vesselSpeedKnots = VALUES(vesselSpeedKnots),
                strokeColor = VALUES(strokeColor),
                strokeWeight = VALUES(strokeWeight),
                strokeOpacity = VALUES(strokeOpacity)");

            $stmt->execute([
                ':id' => $id,
                ':id_ops' => $idOperasi,
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
        self::ensureSchemaUpdated();
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

    public static function getSearchPatterns(?int $idOperasi = null, bool $includeGlobal = true): array {
        self::ensureSchemaUpdated();
        $db = getDB();
        if ($idOperasi !== null && $idOperasi > 0 && !$includeGlobal) {
            $stmt = $db->prepare("SELECT * FROM GisSearchPattern WHERE id_operasi = :id_ops ORDER BY id DESC");
            $stmt->execute([':id_ops' => $idOperasi]);
            $patterns = $stmt->fetchAll();
        } else {
            $patterns = $db->query("SELECT * FROM GisSearchPattern ORDER BY id DESC")->fetchAll();
        }

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
        self::ensureSchemaUpdated();
        $db = getDB();
        $db->beginTransaction();
        try {
            $id = $data['id'] ?? ('pattern-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
            $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
                ? (int)$data['id_operasi']
                : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
            if ($idOperasi !== null && $idOperasi <= 0) {
                $idOperasi = null;
            }

            $stmt = $db->prepare("REPLACE INTO GisSearchPattern (
                id, id_operasi, name, polygonId, polygonName, patternType, trackSpacingNm,
                legsCount, poskoId, poskoName, color, totalDistanceNm, createdAt
            ) VALUES (
                :id, :id_ops, :name, :polyId, :polyName, :pt, :spacing,
                :legs, :poskoId, :poskoName, :color, :dist, :created
            )");

            $stmt->execute([
                ':id' => $id,
                ':id_ops' => $idOperasi,
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
        self::ensureSchemaUpdated();
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
