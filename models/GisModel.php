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
     * Schema migration check for multi-operation SAR.
     * In production runtime, DDL checks are disabled by default for zero table locking overhead.
     * Pass $force = true or run database/migrate.php to perform schema migrations explicitly.
     */
    public static function ensureSchemaUpdated(bool $force = false): void {
        if (!$force) {
            return;
        }
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

            // Ensure shape & geometry attributes exist on GisPolygon
            $gisPolyCols = [
                'shapeType' => "ALTER TABLE GisPolygon ADD COLUMN shapeType VARCHAR(50) NULL DEFAULT 'polygon'",
                'centerLat' => "ALTER TABLE GisPolygon ADD COLUMN centerLat DOUBLE NULL DEFAULT NULL",
                'centerLng' => "ALTER TABLE GisPolygon ADD COLUMN centerLng DOUBLE NULL DEFAULT NULL",
                'radiusNm' => "ALTER TABLE GisPolygon ADD COLUMN radiusNm DOUBLE NULL DEFAULT NULL",
                'boxWidthNm' => "ALTER TABLE GisPolygon ADD COLUMN boxWidthNm DOUBLE NULL DEFAULT NULL",
                'boxHeightNm' => "ALTER TABLE GisPolygon ADD COLUMN boxHeightNm DOUBLE NULL DEFAULT NULL",
                'areaNm2' => "ALTER TABLE GisPolygon ADD COLUMN areaNm2 DOUBLE NULL DEFAULT NULL"
            ];
            foreach ($gisPolyCols as $colName => $alterSql) {
                $check = $db->query("SHOW COLUMNS FROM GisPolygon LIKE '{$colName}'")->fetchAll();
                if (empty($check)) {
                    $db->exec($alterSql);
                }
            }
        } catch (Throwable $e) {
            error_log("[GisModel Schema Check] " . $e->getMessage());
        }
    }

    /**
     * Compute exact geodesic / spherical polygon area in Nautical Miles squared (Nm²)
     * Delegates to GeoHelper::calculatePolygonAreaNm2 SSOT
     */
    public static function calculatePolygonAreaNm2(array $points, ?string $shapeType = null, ?float $radiusNm = null, ?float $boxWidthNm = null, ?float $boxHeightNm = null): float {
        return GeoHelper::calculatePolygonAreaNm2($points, $shapeType, $radiusNm, $boxWidthNm, $boxHeightNm);
    }

    /**
     * Calculate Haversine distance and bearing between two coordinates.
     * Returns ['km' => float, 'nm' => float, 'bearing' => int, 'headingName' => string]
     * Delegates to GeoHelper::calculateDistanceHaversine SSOT
     */
    public static function calculateDistanceHaversine(float $lat1, float $lon1, float $lat2, float $lon2): array {
        return GeoHelper::calculateDistanceHaversine($lat1, $lon1, $lat2, $lon2);
    }

    public static function getPoints(?int $idOperasi = null, bool $includeGlobal = true): array {
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

    /**
     * Mengambil Titik Pangkalan / Pos SAR Master (Kantor SAR, POS SAR, Unit Siaga SAR, Dermaga, dll)
     * yang diinput oleh user pada halaman Plot Peta (GisPoint dengan id_operasi IS NULL).
     * Titik ini menjadi master data default untuk titik awal pemberangkatan operasi SAR.
     */
    public static function getMasterBases(): array {
        $db = getDB();
        
        $sql = "
            SELECT * FROM GisPoint 
            WHERE id_operasi IS NULL 
              AND (
                  category IN ('Kantor SAR', 'POS SAR', 'Unit Siaga SAR', 'Posko', 'Dermaga')
                  OR (category != 'LKP' AND pointType != 'lkp' AND (label LIKE '%SAR%' OR label LIKE '%Kansar%' OR label LIKE '%Pos%' OR label LIKE '%Dermaga%'))
              )
            ORDER BY 
              CASE 
                WHEN (id = 'pt-kansar' OR label LIKE '%Kantor SAR Kendari%') THEN 1
                WHEN category = 'Kantor SAR' THEN 2
                WHEN category = 'POS SAR' THEN 3
                WHEN category = 'Unit Siaga SAR' THEN 4
                ELSE 5
              END ASC,
              label ASC
        ";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        $bases = [];
        foreach ($rows as $r) {
            $lat = (float)$r['lat'];
            $lng = (float)$r['lng'];
            $dms = class_exists('GeoHelper') ? GeoHelper::decimalToDms($lat, $lng) : "{$lat}, {$lng}";
            $isDefault = ($r['id'] === 'pt-kansar' || stripos($r['label'], 'Kantor SAR Kendari') !== false);
            
            $bases[] = [
                'id' => $r['id'],
                'id_unit' => $r['id'],
                'nama_unit' => $r['label'],
                'label' => $r['label'],
                'category' => $r['category'] ?: 'POS SAR',
                'jenis_unit' => strtoupper(str_replace(' ', '_', $r['category'] ?: 'POS_SAR')),
                'wilayah' => $r['description'] ?: ($r['category'] ?? ''),
                'latitude' => $lat,
                'longitude' => $lng,
                'lat' => $lat,
                'lng' => $lng,
                'koordinat_dms' => $dms,
                'pointType' => $r['pointType'] ?: 'custom_upload',
                'customIconUrl' => $r['customIconUrl'] ?: '',
                'alphabetChar' => $r['alphabetChar'] ?: 'A',
                'is_default' => $isDefault ? 1 : 0
            ];
        }
        
        return $bases;
    }

    public static function savePoint(array $data): string {
        $db = getDB();
        $id = $data['id'] ?? ('pt-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4));
        $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
            ? (int)$data['id_operasi']
            : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
        if ($idOperasi !== null && $idOperasi <= 0) {
            $idOperasi = null;
        }

        $customIcon = $data['customIconUrl'] ?? ($data['custom_icon_url'] ?? null);
        if ($customIcon !== null) {
            $customIcon = trim((string)$customIcon);
            if ($customIcon === '') $customIcon = null;
        }
        $type = !empty($customIcon) ? 'custom_upload' : ($data['pointType'] ?? ($data['point_type'] ?? 'pin_umum'));

        $stmt = $db->prepare("REPLACE INTO GisPoint (id, id_operasi, lat, lng, label, pointType, customIconUrl, description, category, alphabetChar) VALUES (:id, :id_ops, :lat, :lng, :label, :type, :customIcon, :desc, :category, :alphabetChar)");
        $stmt->execute([
            ':id' => $id,
            ':id_ops' => $idOperasi,
            ':lat' => (float)$data['lat'],
            ':lng' => (float)$data['lng'],
            ':label' => isset($data['label']) && $data['label'] !== '' ? trim((string)$data['label']) : (isset($data['name']) ? trim((string)$data['name']) : 'Titik SAR'),
            ':type' => $type,
            ':customIcon' => $customIcon,
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

    public static function getPolygons(?int $idOperasi = null, bool $includeGlobal = true): array {
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

        $polyIds = array_values(array_filter(array_map('strval', array_column($polys, 'id'))));
        $pointsByPoly = [];
        if (!empty($polyIds)) {
            $inClause = implode(',', array_fill(0, count($polyIds), '?'));
            $stmt = $db->prepare("SELECT polygonId, lat, lng, label FROM GisPolygonPoint WHERE polygonId IN ($inClause) ORDER BY id ASC");
            $stmt->execute($polyIds);
            $allPoints = $stmt->fetchAll();
            foreach ($allPoints as $pt) {
                $pointsByPoly[$pt['polygonId']][] = [
                    'lat' => (float)$pt['lat'],
                    'lng' => (float)$pt['lng'],
                    'label' => $pt['label'] ?? ''
                ];
            }
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

            $stmtExisting = $db->prepare("SELECT id FROM GisPolygonPoint WHERE polygonId = :id ORDER BY id ASC");
            $stmtExisting->execute([':id' => $id]);
            $existingPts = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);

            $stmtUpdatePt = $db->prepare("UPDATE GisPolygonPoint SET lat = :lat, lng = :lng, label = :label WHERE id = :pid AND polygonId = :id");
            $stmtInsertPt = $db->prepare("INSERT INTO GisPolygonPoint (polygonId, lat, lng, label) VALUES (:id, :lat, :lng, :label)");

            $keptPtIds = [];
            $ptPool = $existingPts;

            if (!empty($data['points']) && is_array($data['points'])) {
                foreach ($data['points'] as $pt) {
                    $lat = (float)$pt['lat'];
                    $lng = (float)$pt['lng'];
                    $label = $pt['label'] ?? '';

                    if (!empty($ptPool)) {
                        $reusedId = (int)array_shift($ptPool);
                        $stmtUpdatePt->execute([':pid' => $reusedId, ':id' => $id, ':lat' => $lat, ':lng' => $lng, ':label' => $label]);
                        $keptPtIds[] = $reusedId;
                    } else {
                        $stmtInsertPt->execute([':id' => $id, ':lat' => $lat, ':lng' => $lng, ':label' => $label]);
                    }
                }
            }

            $toDeletePts = array_diff($existingPts, $keptPtIds);
            if (!empty($toDeletePts)) {
                $delPlaceholders = implode(',', array_fill(0, count($toDeletePts), '?'));
                $stmtDel = $db->prepare("DELETE FROM GisPolygonPoint WHERE id IN ({$delPlaceholders}) AND polygonId = ?");
                $stmtDel->execute([...$toDeletePts, $id]);
            }

            if ($db->inTransaction()) {
                $db->commit();
            }
            if (!empty($toDeletePts)) {
                self::syncAutoIncrement('GisPolygonPoint');
            }
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deletePolygon(string $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM GisPolygonPoint WHERE polygonId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisPolygon WHERE id = :id")->execute([':id' => $id]);
            if ($db->inTransaction()) {
                $db->commit();
            }
            self::syncAutoIncrement('GisPolygonPoint');
            return true;
        } catch (Throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    public static function getRoutes(?int $idOperasi = null, bool $includeGlobal = true): array {
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

        $routeIds = array_values(array_filter(array_map('strval', array_column($routes, 'id'))));
        $pointsByRoute = [];
        if (!empty($routeIds)) {
            $inClause = implode(',', array_fill(0, count($routeIds), '?'));
            $stmt = $db->prepare("SELECT routeId, lat, lng, label FROM GisRoutePoint WHERE routeId IN ($inClause) ORDER BY id ASC");
            $stmt->execute($routeIds);
            $allPoints = $stmt->fetchAll();
            foreach ($allPoints as $pt) {
                $pointsByRoute[$pt['routeId']][] = [
                    'lat' => (float)$pt['lat'],
                    'lng' => (float)$pt['lng'],
                    'label' => $pt['label'] ?? ''
                ];
            }
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

            $stmtExisting = $db->prepare("SELECT id FROM GisRoutePoint WHERE routeId = :id ORDER BY id ASC");
            $stmtExisting->execute([':id' => $id]);
            $existingPts = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);

            $stmtUpdatePt = $db->prepare("UPDATE GisRoutePoint SET lat = :lat, lng = :lng, label = :label WHERE id = :pid AND routeId = :id");
            $stmtInsertPt = $db->prepare("INSERT INTO GisRoutePoint (routeId, lat, lng, label) VALUES (:id, :lat, :lng, :label)");

            $keptPtIds = [];
            $ptPool = $existingPts;

            $rawPts = !empty($data['points']) ? $data['points'] : (!empty($data['waypoints']) ? $data['waypoints'] : []);
            if (!empty($rawPts) && is_array($rawPts)) {
                foreach ($rawPts as $pt) {
                    $lat = (float)$pt['lat'];
                    $lng = (float)$pt['lng'];
                    $label = $pt['label'] ?? '';

                    if (!empty($ptPool)) {
                        $reusedId = (int)array_shift($ptPool);
                        $stmtUpdatePt->execute([':pid' => $reusedId, ':id' => $id, ':lat' => $lat, ':lng' => $lng, ':label' => $label]);
                        $keptPtIds[] = $reusedId;
                    } else {
                        $stmtInsertPt->execute([':id' => $id, ':lat' => $lat, ':lng' => $lng, ':label' => $label]);
                    }
                }
            }

            $toDeletePts = array_diff($existingPts, $keptPtIds);
            if (!empty($toDeletePts)) {
                $delPlaceholders = implode(',', array_fill(0, count($toDeletePts), '?'));
                $stmtDel = $db->prepare("DELETE FROM GisRoutePoint WHERE id IN ({$delPlaceholders}) AND routeId = ?");
                $stmtDel->execute([...$toDeletePts, $id]);
            }

            if ($db->inTransaction()) {
                $db->commit();
            }
            if (!empty($toDeletePts)) {
                self::syncAutoIncrement('GisRoutePoint');
            }
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deleteRoute(string $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM GisRoutePoint WHERE routeId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisRoute WHERE id = :id")->execute([':id' => $id]);
            if ($db->inTransaction()) {
                $db->commit();
            }
            self::syncAutoIncrement('GisRoutePoint');
            return true;
        } catch (Throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    public static function getSearchPatterns(?int $idOperasi = null, bool $includeGlobal = true): array {
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

        $patternIds = array_values(array_filter(array_map('strval', array_column($patterns, 'id'))));
        $wpByPattern = [];
        if (!empty($patternIds)) {
            $inClause = implode(',', array_fill(0, count($patternIds), '?'));
            $stmt = $db->prepare("SELECT searchPatternId, lat, lng, label FROM GisSearchPatternWaypoint WHERE searchPatternId IN ($inClause) ORDER BY id ASC");
            $stmt->execute($patternIds);
            $allWp = $stmt->fetchAll();
            foreach ($allWp as $wp) {
                $wpByPattern[$wp['searchPatternId']][] = [
                    'lat' => (float)$wp['lat'],
                    'lng' => (float)$wp['lng'],
                    'label' => $wp['label'] ?? ''
                ];
            }
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

            $stmtExisting = $db->prepare("SELECT id FROM GisSearchPatternWaypoint WHERE searchPatternId = :id ORDER BY id ASC");
            $stmtExisting->execute([':id' => $id]);
            $existingWps = $stmtExisting->fetchAll(PDO::FETCH_COLUMN);

            $stmtUpdateWp = $db->prepare("UPDATE GisSearchPatternWaypoint SET lat = :lat, lng = :lng, label = :label WHERE id = :wid AND searchPatternId = :id");
            $stmtInsertWp = $db->prepare("INSERT INTO GisSearchPatternWaypoint (searchPatternId, lat, lng, label) VALUES (:id, :lat, :lng, :label)");

            $keptWpIds = [];
            $wpPool = $existingWps;

            if (!empty($data['waypoints']) && is_array($data['waypoints'])) {
                foreach ($data['waypoints'] as $wp) {
                    $lat = (float)$wp['lat'];
                    $lng = (float)$wp['lng'];
                    $label = $wp['label'] ?? '';

                    if (!empty($wpPool)) {
                        $reusedId = (int)array_shift($wpPool);
                        $stmtUpdateWp->execute([':wid' => $reusedId, ':id' => $id, ':lat' => $lat, ':lng' => $lng, ':label' => $label]);
                        $keptWpIds[] = $reusedId;
                    } else {
                        $stmtInsertWp->execute([':id' => $id, ':lat' => $lat, ':lng' => $lng, ':label' => $label]);
                    }
                }
            }

            $toDeleteWps = array_diff($existingWps, $keptWpIds);
            if (!empty($toDeleteWps)) {
                $delPlaceholders = implode(',', array_fill(0, count($toDeleteWps), '?'));
                $stmtDel = $db->prepare("DELETE FROM GisSearchPatternWaypoint WHERE id IN ({$delPlaceholders}) AND searchPatternId = ?");
                $stmtDel->execute([...$toDeleteWps, $id]);
            }

            if ($db->inTransaction()) {
                $db->commit();
            }
            if (!empty($toDeleteWps)) {
                self::syncAutoIncrement('GisSearchPatternWaypoint');
            }
            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deleteSearchPattern(string $id): bool {
        $db = getDB();
        $db->beginTransaction();
        try {
            $db->prepare("DELETE FROM GisSearchPatternWaypoint WHERE searchPatternId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisSearchPattern WHERE id = :id")->execute([':id' => $id]);
            if ($db->inTransaction()) {
                $db->commit();
            }
            self::syncAutoIncrement('GisSearchPatternWaypoint');
            return true;
        } catch (Throwable) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    /**
     * Synchronize and reset GIS point/waypoint table AUTO_INCREMENT values to strictly MAX(id) + 1.
     * If $table is specified, only that table will be checked and updated.
     */
    public static function syncAutoIncrement(?string $table = null): void {
        $db = getDB();
        if ($db->inTransaction()) {
            return;
        }
        try {
            if ($table === null || $table === 'GisPolygonPoint') {
                $stmtPolyPt = $db->query("SELECT COALESCE(MAX(id), 0) FROM GisPolygonPoint");
                $maxPolyPt = (int)$stmtPolyPt->fetchColumn();
                $stmtPolyPt->closeCursor();
                $nextPolyPt = max(1, $maxPolyPt + 1);
                $db->exec("ALTER TABLE GisPolygonPoint AUTO_INCREMENT = {$nextPolyPt}");
            }

            if ($table === null || $table === 'GisRoutePoint') {
                $stmtRoutePt = $db->query("SELECT COALESCE(MAX(id), 0) FROM GisRoutePoint");
                $maxRoutePt = (int)$stmtRoutePt->fetchColumn();
                $stmtRoutePt->closeCursor();
                $nextRoutePt = max(1, $maxRoutePt + 1);
                $db->exec("ALTER TABLE GisRoutePoint AUTO_INCREMENT = {$nextRoutePt}");
            }

            if ($table === null || $table === 'GisSearchPatternWaypoint') {
                $stmtWp = $db->query("SELECT COALESCE(MAX(id), 0) FROM GisSearchPatternWaypoint");
                $maxWp = (int)$stmtWp->fetchColumn();
                $stmtWp->closeCursor();
                $nextWp = max(1, $maxWp + 1);
                $db->exec("ALTER TABLE GisSearchPatternWaypoint AUTO_INCREMENT = {$nextWp}");
            }
        } catch (Throwable) {
            // Silently continue
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

    /**
     * Synchronize and upsert LKP point for a given SAR operation into GisPoint.
     * Generates a persistent GisPoint with category 'LKP', type 'lkp', and formatted coordinates.
     */
    public static function syncOperasiLkpPoint(int $idOperasi, array $data): ?string {
        if ($idOperasi <= 0) return null;

        $lat = null;
        $lng = null;

        // 1. Direct decimal coordinates if provided
        if (isset($data['lkp_lat'], $data['lkp_lng']) && $data['lkp_lat'] !== '' && $data['lkp_lng'] !== '') {
            $cLat = (float)$data['lkp_lat'];
            $cLng = (float)$data['lkp_lng'];
            if ($cLat >= -90.0 && $cLat <= 90.0 && $cLng >= -180.0 && $cLng <= 180.0 && ($cLat != 0.0 || $cLng != 0.0)) {
                $lat = $cLat;
                $lng = $cLng;
            }
        }

        // 2. Fallback: Parse from DMS string in koordinat
        if (($lat === null || $lng === null) && !empty($data['koordinat'])) {
            require_once dirname(__DIR__) . '/helpers/GeoHelper.php';
            $parsed = GeoHelper::parseCoordinates((string)$data['koordinat']);
            if ($parsed && isset($parsed['lat'], $parsed['lng'])) {
                $lat = (float)$parsed['lat'];
                $lng = (float)$parsed['lng'];
            }
        }

        if ($lat === null || $lng === null) {
            return null;
        }

        require_once dirname(__DIR__) . '/helpers/GeoHelper.php';
        $dmsStr = GeoHelper::decimalToDms($lat, $lng);
        $namaOperasi = trim((string)($data['nama_operasi'] ?? 'Operasi SAR'));
        $lokasiKejadian = trim((string)($data['lokasi_kejadian'] ?? ''));
        $jarak = trim((string)($data['jarak'] ?? ''));
        $radial = trim((string)($data['radial'] ?? ''));

        $desc = "📍 LKP (Last Known Position)\n" .
                "Operasi: {$namaOperasi}\n" .
                "Lokasi: " . ($lokasiKejadian ?: 'Perairan/Wilayah Kejadian') . "\n" .
                "Koordinat: {$dmsStr}\n" .
                ($jarak ? "Jarak: {$jarak}\n" : '') .
                ($radial ? "Radial: {$radial}°" : '');

        $pointId = "pt-lkp-op-{$idOperasi}";

        return self::savePoint([
            'id' => $pointId,
            'id_operasi' => $idOperasi,
            'lat' => $lat,
            'lng' => $lng,
            'label' => "🚨 LKP - " . (mb_strlen($namaOperasi) > 30 ? mb_substr($namaOperasi, 0, 27) . '...' : $namaOperasi),
            'pointType' => 'lkp',
            'customIconUrl' => null,
            'description' => trim($desc),
            'category' => 'LKP',
            'alphabetChar' => '🚨'
        ]);
    }

    /**
     * Synchronize and upsert nautical route for a given SAR operation into GisRoute & GisRoutePoint.
     * Supports manual route waypoints (JSON) or direct departure route (Origin Base -> LKP).
     */
    public static function syncOperasiRoute(int $idOperasi, array $data): ?string {
        if ($idOperasi <= 0) return null;

        $points = [];

        // 1. Check if manual_route_json is provided (from the manual route plotter)
        if (!empty($data['manual_route_json'])) {
            $decoded = is_string($data['manual_route_json']) ? json_decode($data['manual_route_json'], true) : $data['manual_route_json'];
            if (is_array($decoded) && count($decoded) >= 2) {
                foreach ($decoded as $idx => $p) {
                    if (isset($p['lat'], $p['lng'])) {
                        $pLat = (float)$p['lat'];
                        $pLng = (float)$p['lng'];
                        if ($pLat >= -90.0 && $pLat <= 90.0 && $pLng >= -180.0 && $pLng <= 180.0) {
                            $label = !empty($p['label']) ? (string)$p['label'] : ($idx === 0 ? 'Pangkalan Asal' : ($idx === count($decoded) - 1 ? 'LKP' : "WP-{$idx}"));
                            $points[] = [
                                'lat' => $pLat,
                                'lng' => $pLng,
                                'label' => $label
                            ];
                        }
                    }
                }
            }
        }

        // 2. Fallback: If no manual route, but origin base and LKP exist, create direct route
        if (count($points) < 2) {
            $originLat = isset($data['origin_lat']) && $data['origin_lat'] !== '' ? (float)$data['origin_lat'] : null;
            $originLng = isset($data['origin_lng']) && $data['origin_lng'] !== '' ? (float)$data['origin_lng'] : null;
            $originName = !empty($data['origin_name']) ? (string)$data['origin_name'] : 'Pangkalan Asal';

            $lkpLat = isset($data['lkp_lat']) && $data['lkp_lat'] !== '' ? (float)$data['lkp_lat'] : null;
            $lkpLng = isset($data['lkp_lng']) && $data['lkp_lng'] !== '' ? (float)$data['lkp_lng'] : null;

            if (($lkpLat === null || $lkpLng === null) && !empty($data['koordinat'])) {
                require_once dirname(__DIR__) . '/helpers/GeoHelper.php';
                $parsed = GeoHelper::parseCoordinates((string)$data['koordinat']);
                if ($parsed) {
                    $lkpLat = (float)$parsed['lat'];
                    $lkpLng = (float)$parsed['lng'];
                }
            }

            if ($originLat !== null && $originLng !== null && $lkpLat !== null && $lkpLng !== null && ($originLat != 0.0 || $originLng != 0.0) && ($lkpLat != 0.0 || $lkpLng != 0.0)) {
                $points = [
                    ['lat' => $originLat, 'lng' => $originLng, 'label' => $originName],
                    ['lat' => $lkpLat, 'lng' => $lkpLng, 'label' => 'Titik LKP']
                ];
            }
        }

        if (count($points) < 2) {
            return null;
        }

        $namaOperasi = trim((string)($data['nama_operasi'] ?? 'Operasi SAR'));
        $speed = isset($data['vessel_speed_knots']) && (float)$data['vessel_speed_knots'] > 0
            ? (float)$data['vessel_speed_knots']
            : (isset($data['vesselSpeedKnots']) ? (float)$data['vesselSpeedKnots'] : 25.0);

        $routeId = "route-op-{$idOperasi}";

        return self::saveRoute([
            'id' => $routeId,
            'id_operasi' => $idOperasi,
            'name' => "Rute Pelayaran - " . (mb_strlen($namaOperasi) > 30 ? mb_substr($namaOperasi, 0, 27) . '...' : $namaOperasi),
            'vesselSpeedKnots' => $speed,
            'strokeColor' => '#ea580c',
            'strokeWeight' => 3.5,
            'strokeOpacity' => 0.9,
            'createdAt' => date('Y-m-d H:i') . ' WITA',
            'points' => $points
        ]);
    }

    /**
     * Export all GIS tactical layers as standard RFC 7946 GeoJSON FeatureCollection.
     */
    public static function exportGeoJson(?int $idOperasi = null, bool $includeGlobal = true): string {
        $features = [];

        // 1. Points
        $points = self::getPoints($idOperasi, $includeGlobal);
        foreach ($points as $pt) {
            $lat = (float)($pt['lat'] ?? 0);
            $lng = (float)($pt['lng'] ?? 0);
            if ($lat == 0.0 && $lng == 0.0) continue;
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lng, $lat]
                ],
                'properties' => [
                    'id' => $pt['id'],
                    'label' => $pt['label'] ?? '',
                    'pointType' => $pt['pointType'] ?? 'pin_umum',
                    'category' => $pt['category'] ?? '',
                    'description' => $pt['description'] ?? '',
                    'alphabetChar' => $pt['alphabetChar'] ?? '',
                    'id_operasi' => $pt['id_operasi'] ?? null
                ]
            ];
        }

        // 2. Polygons / Search Areas
        $polys = self::getPolygons($idOperasi, $includeGlobal);
        foreach ($polys as $pl) {
            $rawPts = $pl['points'] ?? [];
            if (empty($rawPts)) continue;
            $ring = [];
            foreach ($rawPts as $p) {
                $ring[] = [(float)$p['lng'], (float)$p['lat']];
            }
            if (!empty($ring) && ($ring[0] !== end($ring))) {
                $ring[] = $ring[0];
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [$ring]
                ],
                'properties' => [
                    'id' => $pl['id'],
                    'name' => $pl['name'] ?? 'Search Area',
                    'shapeType' => $pl['shapeType'] ?? 'polygon',
                    'areaNm2' => (float)($pl['areaNm2'] ?? 0),
                    'color' => $pl['color'] ?? '#f97316',
                    'opacity' => (float)($pl['opacity'] ?? 0.25),
                    'id_operasi' => $pl['id_operasi'] ?? null
                ]
            ];
        }

        // 3. Routes
        $routes = self::getRoutes($idOperasi, $includeGlobal);
        foreach ($routes as $rt) {
            $rawPts = $rt['points'] ?? [];
            if (count($rawPts) < 2) continue;
            $coords = [];
            foreach ($rawPts as $p) {
                $coords[] = [(float)$p['lng'], (float)$p['lat']];
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $coords
                ],
                'properties' => [
                    'id' => $rt['id'],
                    'name' => $rt['name'] ?? 'Rute Operasi',
                    'vesselSpeedKnots' => (float)($rt['vesselSpeedKnots'] ?? 25),
                    'strokeColor' => $rt['strokeColor'] ?? '#059669',
                    'id_operasi' => $rt['id_operasi'] ?? null
                ]
            ];
        }

        // 4. Search Patterns
        $patterns = self::getSearchPatterns($idOperasi, $includeGlobal);
        foreach ($patterns as $sp) {
            $rawWp = $sp['waypoints'] ?? [];
            if (count($rawWp) < 2) continue;
            $coords = [];
            foreach ($rawWp as $w) {
                $coords[] = [(float)$w['lng'], (float)$w['lat']];
            }
            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'LineString',
                    'coordinates' => $coords
                ],
                'properties' => [
                    'id' => $sp['id'],
                    'name' => $sp['name'] ?? 'Pola Pencarian',
                    'patternType' => $sp['patternType'] ?? 'parallel_track',
                    'trackSpacingNm' => (float)($sp['trackSpacingNm'] ?? 1.0),
                    'totalDistanceNm' => (float)($sp['totalDistanceNm'] ?? 0),
                    'color' => $sp['color'] ?? '#0284c7',
                    'id_operasi' => $sp['id_operasi'] ?? null
                ]
            ];
        }

        return (string)json_encode([
            'type' => 'FeatureCollection',
            'features' => $features
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
