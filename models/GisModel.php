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

            // Ensure areaNm2 exists on GisPolygon
            $polyCols = $db->query("SHOW COLUMNS FROM GisPolygon LIKE 'areaNm2'")->fetchAll();
            if (empty($polyCols)) {
                $db->exec("ALTER TABLE GisPolygon ADD COLUMN areaNm2 DOUBLE NULL DEFAULT NULL");
            }
        } catch (Throwable $e) {
            error_log("[GisModel Schema Check] " . $e->getMessage());
        }
    }

    /**
     * Compute exact geodesic / spherical polygon area in Nautical Miles squared (Nm²)
     */
    public static function calculatePolygonAreaNm2(array $points, ?string $shapeType = null, ?float $radiusNm = null, ?float $boxWidthNm = null, ?float $boxHeightNm = null): float {
        if ($shapeType === 'circle' && $radiusNm !== null && $radiusNm > 0) {
            return round(M_PI * $radiusNm * $radiusNm, 2);
        }
        if ($shapeType === 'box' && $boxWidthNm !== null && $boxHeightNm !== null && $boxWidthNm > 0 && $boxHeightNm > 0) {
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

    public static function getPoints(?int $idOperasi = null, bool $includeGlobal = true): array {
        self::ensureSchemaUpdated();
        $db = getDB();
        if ($idOperasi !== null && $idOperasi > 0) {
            if ($includeGlobal) {
                $stmt = $db->prepare("SELECT * FROM GisPoint WHERE id_operasi = :id_ops OR id_operasi IS NULL ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                return $stmt->fetchAll();
            } else {
                $stmt = $db->prepare("SELECT * FROM GisPoint WHERE id_operasi = :id_ops ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                return $stmt->fetchAll();
            }
        } elseif ($idOperasi === 0) {
            return $db->query("SELECT * FROM GisPoint WHERE id_operasi IS NULL ORDER BY id DESC")->fetchAll();
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
        if ($idOperasi !== null && $idOperasi > 0) {
            if ($includeGlobal) {
                $stmt = $db->prepare("SELECT * FROM GisPolygon WHERE id_operasi = :id_ops OR id_operasi IS NULL ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                $polys = $stmt->fetchAll();
            } else {
                $stmt = $db->prepare("SELECT * FROM GisPolygon WHERE id_operasi = :id_ops ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                $polys = $stmt->fetchAll();
            }
        } elseif ($idOperasi === 0) {
            $polys = $db->query("SELECT * FROM GisPolygon WHERE id_operasi IS NULL ORDER BY id DESC")->fetchAll();
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
            $calcArea = self::calculatePolygonAreaNm2(
                $p['points'],
                $p['shapeType'] ?? null,
                isset($p['radiusNm']) ? (float)$p['radiusNm'] : null,
                isset($p['boxWidthNm']) ? (float)$p['boxWidthNm'] : null,
                isset($p['boxHeightNm']) ? (float)$p['boxHeightNm'] : null
            );
            $p['areaNm2'] = (!empty($p['areaNm2']) && (float)$p['areaNm2'] > 0) ? (float)$p['areaNm2'] : $calcArea;
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

            $calcArea = self::calculatePolygonAreaNm2(
                $data['points'] ?? [],
                $data['shapeType'] ?? null,
                isset($data['radiusNm']) ? (float)$data['radiusNm'] : null,
                isset($data['boxWidthNm']) ? (float)$data['boxWidthNm'] : null,
                isset($data['boxHeightNm']) ? (float)$data['boxHeightNm'] : null
            );
            $areaNm2 = isset($data['areaNm2']) && (float)$data['areaNm2'] > 0 ? (float)$data['areaNm2'] : $calcArea;

            $stmt = $db->prepare("REPLACE INTO GisPolygon (
                id, id_operasi, name, strokeColor, strokeWeight, fillColor, fillOpacity,
                shapeType, centerLat, centerLng, radiusNm, boxWidthNm, boxHeightNm, areaNm2
            ) VALUES (
                :id, :id_ops, :name, :sc, :sw, :fc, :fo,
                :st, :clat, :clng, :r, :bw, :bh, :area
            )");
            $stmt->execute([
                ':id' => $id,
                ':id_ops' => $idOperasi,
                ':name' => $data['name'] ?? 'Area Pencarian',
                ':sc' => $data['strokeColor'] ?? '#ea580c',
                ':sw' => (float)($data['strokeWeight'] ?? 2.5),
                ':fc' => $data['fillColor'] ?? '#ea580c',
                ':fo' => (float)($data['fillOpacity'] ?? 0.2),
                ':st' => $data['shapeType'] ?? 'polygon',
                ':clat' => isset($data['centerLat']) ? (float)$data['centerLat'] : null,
                ':clng' => isset($data['centerLng']) ? (float)$data['centerLng'] : null,
                ':r' => isset($data['radiusNm']) ? (float)$data['radiusNm'] : null,
                ':bw' => isset($data['boxWidthNm']) ? (float)$data['boxWidthNm'] : null,
                ':bh' => isset($data['boxHeightNm']) ? (float)$data['boxHeightNm'] : null,
                ':area' => $areaNm2
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
        if ($idOperasi !== null && $idOperasi > 0) {
            if ($includeGlobal) {
                $stmt = $db->prepare("SELECT * FROM GisRoute WHERE id_operasi = :id_ops OR id_operasi IS NULL ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                $routes = $stmt->fetchAll();
            } else {
                $stmt = $db->prepare("SELECT * FROM GisRoute WHERE id_operasi = :id_ops ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                $routes = $stmt->fetchAll();
            }
        } elseif ($idOperasi === 0) {
            $routes = $db->query("SELECT * FROM GisRoute WHERE id_operasi IS NULL ORDER BY id DESC")->fetchAll();
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
        if ($idOperasi !== null && $idOperasi > 0) {
            if ($includeGlobal) {
                $stmt = $db->prepare("SELECT * FROM GisSearchPattern WHERE id_operasi = :id_ops OR id_operasi IS NULL ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                $patterns = $stmt->fetchAll();
            } else {
                $stmt = $db->prepare("SELECT * FROM GisSearchPattern WHERE id_operasi = :id_ops ORDER BY id DESC");
                $stmt->execute([':id_ops' => $idOperasi]);
                $patterns = $stmt->fetchAll();
            }
        } elseif ($idOperasi === 0) {
            $patterns = $db->query("SELECT * FROM GisSearchPattern WHERE id_operasi IS NULL ORDER BY id DESC")->fetchAll();
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
                legsCount, poskoId, poskoName, color, opacity, strokeWeight, totalDistanceNm, createdAt
            ) VALUES (
                :id, :id_ops, :name, :polyId, :polyName, :pt, :spacing,
                :legs, :poskoId, :poskoName, :color, :opacity, :weight, :dist, :created
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
                ':opacity' => isset($data['opacity']) ? (float)$data['opacity'] : 0.9,
                ':weight' => isset($data['strokeWeight']) ? (float)$data['strokeWeight'] : (isset($data['weight']) ? (float)$data['weight'] : 3.0),
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

    public static function getOperationBundle(int $idOperasi, bool $includeBaseMap = true): array {
        return [
            'id_operasi' => $idOperasi,
            'include_base_map' => $includeBaseMap,
            'points' => self::getPoints($idOperasi, $includeBaseMap),
            'polygons' => self::getPolygons($idOperasi, $includeBaseMap),
            'routes' => self::getRoutes($idOperasi, $includeBaseMap),
            'searchPatterns' => self::getSearchPatterns($idOperasi, $includeBaseMap)
        ];
    }
}
