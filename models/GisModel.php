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
                'areaNm2' => "ALTER TABLE GisPolygon ADD COLUMN areaNm2 DOUBLE NULL DEFAULT NULL",
                'strokeOpacity' => "ALTER TABLE GisPolygon ADD COLUMN strokeOpacity DOUBLE NOT NULL DEFAULT 1"
            ];
            foreach ($gisPolyCols as $colName => $alterSql) {
                $check = $db->query("SHOW COLUMNS FROM GisPolygon LIKE '{$colName}'")->fetchAll();
                if (empty($check)) {
                    $db->exec($alterSql);
                }
            }
            // Flip pola PS/CS (titik awal) & keterkaitan rute → search area (penamaan & impor per SRU)
            foreach ([
                ['GisSearchPattern', 'flipH', 'TINYINT(1) NOT NULL DEFAULT 0'],
                ['GisSearchPattern', 'flipV', 'TINYINT(1) NOT NULL DEFAULT 0'],
                ['GisRoute', 'polygonId', 'VARCHAR(191) NULL DEFAULT NULL'],
                ['GisPoint', 'import_group', 'VARCHAR(100) NULL DEFAULT NULL'],
                ['GisPolygon', 'import_group', 'VARCHAR(100) NULL DEFAULT NULL'],
                ['GisSearchPattern', 'import_group', 'VARCHAR(100) NULL DEFAULT NULL'],
                ['GisRoute', 'import_group', 'VARCHAR(100) NULL DEFAULT NULL'],
            ] as [$tbl, $colName, $def]) {
                if (empty($db->query("SHOW COLUMNS FROM {$tbl} LIKE '{$colName}'")->fetchAll())) {
                    $db->exec("ALTER TABLE {$tbl} ADD COLUMN {$colName} {$def}");
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
     * Generate geodesic circle ring coordinates (unclosed).
     * Used for circle polygon rendering, KMZ export, GeoJSON fallback, and import vertex generation.
     *
     * @param float $centerLat Latitude of circle center
     * @param float $centerLng Longitude of circle center
     * @param float $radiusNm Radius in Nautical Miles (> 0)
     * @param int $numPoints Number of vertices (min 3, default 64)
     * @return array<int, array{lat: float, lng: float}>
     */
    public static function generateCircleRing(float $centerLat, float $centerLng, float $radiusNm, int $numPoints = 64): array {
        if ($radiusNm <= 0 || !is_finite($centerLat) || !is_finite($centerLng) || $centerLat < -90.0 || $centerLat > 90.0 || $centerLng < -180.0 || $centerLng > 180.0) {
            return [];
        }
        $n = max(3, $numPoints);
        $radiusKm = $radiusNm * 1.852;
        $deltaLatDeg = $radiusKm / 110.574;
        $cosLat = cos($centerLat * M_PI / 180.0);
        $cosFactor = abs($cosLat) < 1e-6 ? 1e-6 : abs($cosLat);
        $deltaLngDeg = $radiusKm / (111.32 * $cosFactor);

        $ring = [];
        for ($i = 0; $i < $n; $i++) {
            $angle = (2.0 * M_PI * $i) / $n;
            $lat = $centerLat + $deltaLatDeg * sin($angle);
            $lng = $centerLng + $deltaLngDeg * cos($angle);
            $ring[] = ['lat' => $lat, 'lng' => $lng];
        }
        return $ring;
    }

    /**
     * Validates that a pair of coordinates are finite floats within standard WGS84 bounds.
     * Throws InvalidArgumentException on failure.
     *
     * @param mixed $lat
     * @param mixed $lng
     * @param string $context
     * @return array{lat: float, lng: float}
     * @throws InvalidArgumentException
     */
    private static function validateCoordPair(mixed $lat, mixed $lng, string $context = 'coordinate'): array {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            throw new InvalidArgumentException("Non-numeric coordinate in {$context}: lat=" . var_export($lat, true) . ", lng=" . var_export($lng, true));
        }
        $latF = (float)$lat;
        $lngF = (float)$lng;
        if (!is_finite($latF) || !is_finite($lngF)) {
            throw new InvalidArgumentException("Non-finite coordinate in {$context}");
        }
        if ($latF < -90.0 || $latF > 90.0) {
            throw new InvalidArgumentException("Latitude out of range [-90, 90] in {$context}: {$latF}");
        }
        if ($lngF < -180.0 || $lngF > 180.0) {
            throw new InvalidArgumentException("Longitude out of range [-180, 180] in {$context}: {$lngF}");
        }
        return ['lat' => $latF, 'lng' => $lngF];
    }

    /**
     * Validates and normalizes local custom icon paths.
     * Accepts only relative paths matching ^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$
     * that exist on disk (is_file(ROOT_DIR . '/' . $rel)). Rejects traversal (..), external origins, and missing files.
     *
     * @param mixed $path
     * @return string|null Normalized relative path or null if unsafe/invalid/missing
     */
    public static function resolveSafeLocalIconPath(mixed $path): ?string {
        if (!is_string($path)) {
            return null;
        }
        $trimmed = trim($path);
        if ($trimmed === '' || str_contains($trimmed, '..') || str_starts_with($trimmed, 'data:') || preg_match('/[?#\s]/', $trimmed)) {
            return null;
        }

        // Normalize full URL (if originating from local server / BASE_URL)
        if (preg_match('#^https?://#i', $trimmed)) {
            $parsed = parse_url($trimmed);
            if (!$parsed || empty($parsed['path'])) {
                return null;
            }
            $baseParsed = parse_url(defined('BASE_URL') ? BASE_URL : '');
            $baseHost = $baseParsed['host'] ?? null;
            $targetHost = $parsed['host'] ?? null;
            if ($baseHost !== null && $targetHost !== null && strcasecmp($baseHost, $targetHost) !== 0) {
                return null; // External host (e.g. evil.com)
            }
            $trimmed = $parsed['path'];
        }

        // Strip any leading slash or project subfolder up to assets/img or uploads/icons
        $rel = ltrim($trimmed, '/');
        if (preg_match('#(?:^|/)((?:assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+)$#', $rel, $m)) {
            $rel = $m[1];
        }

        if (!preg_match('#^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$#', $rel) || str_contains($rel, '..')) {
            return null;
        }

        $rootDir = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__);
        $fullPath = $rootDir . '/' . $rel;
        if (!is_file($fullPath)) {
            return null;
        }

        return $rel;
    }

    /**
     * Membangun klausa WHERE untuk penyaringan entitas GIS menurut satu id_operasi,
     * daftar id_operasi (array), base map saja (0 / array kosong + includeGlobal), atau seluruh data (null).
     *
     * @param int|array<int>|null $idOperasi
     * @return array{where: string, params: array<int>, empty: bool}
     */
    private static function buildOperasiScopeSql(int|array|null $idOperasi, bool $includeGlobal): array {
        if ($idOperasi === null) {
            return ['where' => '', 'params' => [], 'empty' => false];
        }
        if (is_array($idOperasi)) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $idOperasi), static fn(int $v): bool => $v > 0)));
            if (empty($ids)) {
                return $includeGlobal
                    ? ['where' => ' WHERE id_operasi IS NULL', 'params' => [], 'empty' => false]
                    : ['where' => '', 'params' => [], 'empty' => true];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $where = $includeGlobal
                ? " WHERE (id_operasi IN ({$placeholders}) OR id_operasi IS NULL)"
                : " WHERE id_operasi IN ({$placeholders})";
            return ['where' => $where, 'params' => $ids, 'empty' => false];
        }
        if ($idOperasi > 0) {
            return [
                'where' => $includeGlobal ? ' WHERE id_operasi = ? OR id_operasi IS NULL' : ' WHERE id_operasi = ?',
                'params' => [$idOperasi],
                'empty' => false,
            ];
        }
        if ($idOperasi === 0) {
            return ['where' => ' WHERE id_operasi IS NULL', 'params' => [], 'empty' => false];
        }
        return ['where' => '', 'params' => [], 'empty' => false];
    }

    public static function getPoints(int|array|null $idOperasi = null, bool $includeGlobal = true): array {
        $scope = self::buildOperasiScopeSql($idOperasi, $includeGlobal);
        if ($scope['empty']) return [];
        $db = getDB();
        $sql = "SELECT * FROM GisPoint{$scope['where']} ORDER BY id DESC";
        if (empty($scope['params'])) {
            return $db->query($sql)->fetchAll();
        }
        $stmt = $db->prepare($sql);
        $stmt->execute($scope['params']);
        return $stmt->fetchAll();
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

        if (empty($rows)) {
            // Fallback: baca dari SarUnit jika data GisPoint belum terisi
            $suRows = $db->query("SELECT * FROM SarUnit ORDER BY is_default DESC, id_unit ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($suRows as $su) {
                $lat = (float)$su['latitude'];
                $lng = (float)$su['longitude'];
                $dms = !empty($su['koordinat_dms']) ? $su['koordinat_dms'] : (class_exists('GeoHelper') ? GeoHelper::decimalToDms($lat, $lng) : "{$lat}, {$lng}");
                $isDefault = ((int)($su['is_default'] ?? 0) === 1 || stripos($su['nama_unit'], 'Kansar') !== false || stripos($su['nama_unit'], 'Kantor SAR') !== false);
                $id = $isDefault ? 'pt-kansar' : ('pt-sarunit-' . $su['id_unit']);
                $cat = match(strtoupper((string)($su['jenis_unit'] ?? ''))) {
                    'KANTOR_SAR' => 'Kantor SAR',
                    'POS_SAR' => 'POS SAR',
                    'UNIT_SIAGA' => 'Unit Siaga SAR',
                    'KN_SAR' => 'Dermaga',
                    default => 'POS SAR'
                };

                $bases[] = [
                    'id' => $id,
                    'id_unit' => $id,
                    'nama_unit' => $su['nama_unit'],
                    'label' => $su['nama_unit'],
                    'category' => $cat,
                    'jenis_unit' => strtoupper(str_replace(' ', '_', $cat)),
                    'wilayah' => $su['wilayah'] ?: $su['alamat'],
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'lat' => $lat,
                    'lng' => $lng,
                    'koordinat_dms' => $dms,
                    'pointType' => 'custom_upload',
                    'customIconUrl' => 'assets/img/basarnas-logo.png',
                    'alphabetChar' => 'A',
                    'is_default' => $isDefault ? 1 : 0
                ];
            }
            return $bases;
        }

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
                'customIconUrl' => $r['customIconUrl'] ?: 'assets/img/basarnas-logo.png',
                'alphabetChar' => $r['alphabetChar'] ?: 'A',
                'is_default' => $isDefault ? 1 : 0
            ];
        }
        
        return $bases;
    }

    /** ID objek GIS dirender ke atribut HTML/JS di klien, jadi karakternya dibatasi */
    private static function validId(string $id): string {
        if (!preg_match('/^[A-Za-z0-9_.:-]{1,100}$/', $id)) {
            throw new InvalidArgumentException('ID objek peta tidak valid.');
        }
        return $id;
    }

    /** Referensi ke objek GIS lain: kosongkan bila bukan ID yang sah */
    private static function refId(mixed $id): string {
        $id = (string)$id;
        return preg_match('/^[A-Za-z0-9_.:-]{0,100}$/', $id) ? $id : '';
    }

    /** Warna masuk ke style/atribut SVG: hanya hex, rgb(a), hsl(a) atau nama warna */
    private static function safeColor(mixed $color, string $default): string {
        $color = trim((string)$color);
        return preg_match('/^(#[0-9a-fA-F]{3,8}|(rgb|hsl)a?\([0-9.,%\/ ]+\)|[a-zA-Z]{3,20})$/', $color) ? $color : $default;
    }

    public static function savePoint(array $data): string {
        $db = getDB();
        $id = self::validId((string)($data['id'] ?? ('pt-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4))));
        $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
            ? (int)$data['id_operasi']
            : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
        if ($idOperasi !== null && $idOperasi <= 0) {
            $idOperasi = null;
        }

        $customIcon = $data['customIconUrl'] ?? ($data['custom_icon_url'] ?? null);
        if ($customIcon !== null) {
            $customIcon = qrsar_sanitize_asset_url((string)$customIcon, '');
            if ($customIcon === '') $customIcon = null;
        }
        $rawType = (string)($data['pointType'] ?? ($data['point_type'] ?? 'pin_umum'));
        $type = !empty($customIcon) ? 'custom_upload' : ($rawType === 'custom_upload' ? 'pin_umum' : $rawType);

        $rawImpGroup = $data['import_group'] ?? ($data['importGroup'] ?? null);
        $importGroup = ($rawImpGroup !== null && trim((string)$rawImpGroup) !== '') ? mb_substr(trim((string)$rawImpGroup), 0, 100) : null;

        $stmt = $db->prepare("INSERT INTO GisPoint (
            id, id_operasi, lat, lng, label, pointType, customIconUrl, description, category, alphabetChar, import_group
        ) VALUES (
            :id, :id_ops, :lat, :lng, :label, :type, :customIcon, :desc, :category, :alphabetChar, :import_group
        ) ON DUPLICATE KEY UPDATE
            id_operasi = VALUES(id_operasi),
            lat = VALUES(lat),
            lng = VALUES(lng),
            label = VALUES(label),
            pointType = VALUES(pointType),
            customIconUrl = VALUES(customIconUrl),
            description = VALUES(description),
            category = VALUES(category),
            alphabetChar = VALUES(alphabetChar),
            import_group = COALESCE(VALUES(import_group), import_group)");
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
            // Label huruf/emoji pendek yang dirender ke HTML: tolak karakter markup
            ':alphabetChar' => preg_match('/^[^<>&"\'`\s]{1,4}$/u', (string)($data['alphabetChar'] ?? '')) ? $data['alphabetChar'] : 'A',
            ':import_group' => $importGroup
        ]);

        return $id;
    }

    public static function deletePoint(string $id): bool {
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM GisPoint WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public static function getPolygons(int|array|null $idOperasi = null, bool $includeGlobal = true): array {
        $scope = self::buildOperasiScopeSql($idOperasi, $includeGlobal);
        if ($scope['empty']) return [];
        $db = getDB();
        $sql = "SELECT * FROM GisPolygon{$scope['where']} ORDER BY id DESC";
        if (empty($scope['params'])) {
            $polys = $db->query($sql)->fetchAll();
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute($scope['params']);
            $polys = $stmt->fetchAll();
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
            $p['strokeOpacity'] = isset($p['strokeOpacity']) ? (float)$p['strokeOpacity'] : 1.0;
        }
        return $polys;
    }

    public static function savePolygon(array $data): string {
        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $id = self::validId((string)($data['id'] ?? ('poly-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4))));
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
            $rawImpGroup = $data['import_group'] ?? ($data['importGroup'] ?? null);
            $importGroup = ($rawImpGroup !== null && trim((string)$rawImpGroup) !== '') ? mb_substr(trim((string)$rawImpGroup), 0, 100) : null;

            $stmt = $db->prepare("INSERT INTO GisPolygon (
                id, id_operasi, name, strokeColor, strokeWeight, strokeOpacity, fillColor, fillOpacity,
                shapeType, centerLat, centerLng, radiusNm, boxWidthNm, boxHeightNm, areaNm2, import_group
            ) VALUES (
                :id, :id_ops, :name, :sc, :sw, :so, :fc, :fo,
                :st, :clat, :clng, :r, :bw, :bh, :area, :import_group
            ) ON DUPLICATE KEY UPDATE
                id_operasi = VALUES(id_operasi),
                name = VALUES(name),
                strokeColor = VALUES(strokeColor),
                strokeWeight = VALUES(strokeWeight),
                strokeOpacity = VALUES(strokeOpacity),
                fillColor = VALUES(fillColor),
                fillOpacity = VALUES(fillOpacity),
                shapeType = VALUES(shapeType),
                centerLat = VALUES(centerLat),
                centerLng = VALUES(centerLng),
                radiusNm = VALUES(radiusNm),
                boxWidthNm = VALUES(boxWidthNm),
                boxHeightNm = VALUES(boxHeightNm),
                areaNm2 = VALUES(areaNm2),
                import_group = COALESCE(VALUES(import_group), import_group)");
            $stmt->execute([
                ':id' => $id,
                ':id_ops' => $idOperasi,
                ':name' => $data['name'] ?? 'Area Pencarian',
                ':sc' => self::safeColor($data['strokeColor'] ?? null, '#ea580c'),
                ':sw' => (float)($data['strokeWeight'] ?? 2.5),
                ':so' => max(0.0, min(1.0, (float)($data['strokeOpacity'] ?? 1))),
                ':fc' => self::safeColor($data['fillColor'] ?? null, '#ea580c'),
                ':fo' => (float)($data['fillOpacity'] ?? 0.2),
                ':st' => $data['shapeType'] ?? 'polygon',
                ':clat' => isset($data['centerLat']) ? (float)$data['centerLat'] : null,
                ':clng' => isset($data['centerLng']) ? (float)$data['centerLng'] : null,
                ':r' => isset($data['radiusNm']) ? (float)$data['radiusNm'] : null,
                ':bw' => isset($data['boxWidthNm']) ? (float)$data['boxWidthNm'] : null,
                ':bh' => isset($data['boxHeightNm']) ? (float)$data['boxHeightNm'] : null,
                ':area' => $areaNm2,
                ':import_group' => $importGroup
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

            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return $id;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deletePolygon(string $id): bool {
        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $db->prepare("DELETE FROM GisPolygonPoint WHERE polygonId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisPolygon WHERE id = :id")->execute([':id' => $id]);
            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return true;
        } catch (Throwable) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    public static function getRoutes(int|array|null $idOperasi = null, bool $includeGlobal = true): array {
        $scope = self::buildOperasiScopeSql($idOperasi, $includeGlobal);
        if ($scope['empty']) return [];
        $db = getDB();
        $sql = "SELECT * FROM GisRoute{$scope['where']} ORDER BY id DESC";
        if (empty($scope['params'])) {
            $routes = $db->query($sql)->fetchAll();
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute($scope['params']);
            $routes = $stmt->fetchAll();
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
            $r['polygonId'] = ($r['polygonId'] ?? '') !== '' ? (string)$r['polygonId'] : null;
            $r['points'] = $pointsByRoute[$r['id']] ?? [];
        }
        return $routes;
    }

    public static function saveRoute(array $data): string {
        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $id = self::validId((string)($data['id'] ?? ('route-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4))));
            $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
                ? (int)$data['id_operasi']
                : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
            if ($idOperasi !== null && $idOperasi <= 0) {
                $idOperasi = null;
            }
            $rawImpGroup = $data['import_group'] ?? ($data['importGroup'] ?? null);
            $importGroup = ($rawImpGroup !== null && trim((string)$rawImpGroup) !== '') ? mb_substr(trim((string)$rawImpGroup), 0, 100) : null;

            $stmt = $db->prepare("INSERT INTO GisRoute (
                id, id_operasi, name, polygonId, vesselSpeedKnots, strokeColor, strokeWeight, strokeOpacity, createdAt, import_group
            ) VALUES (
                :id, :id_ops, :name, :polyId, :speed, :color, :weight, :opacity, :created, :import_group
            ) ON DUPLICATE KEY UPDATE 
                id_operasi = VALUES(id_operasi),
                name = VALUES(name),
                polygonId = VALUES(polygonId),
                vesselSpeedKnots = VALUES(vesselSpeedKnots),
                strokeColor = VALUES(strokeColor),
                strokeWeight = VALUES(strokeWeight),
                strokeOpacity = VALUES(strokeOpacity),
                import_group = COALESCE(VALUES(import_group), import_group)");

            $stmt->execute([
                ':id' => $id,
                ':id_ops' => $idOperasi,
                ':name' => $data['name'] ?? 'Rute Operasi',
                ':polyId' => self::refId($data['polygonId'] ?? '') ?: null,
                ':speed' => (float)($data['vesselSpeedKnots'] ?? 25),
                ':color' => self::safeColor($data['strokeColor'] ?? null, '#059669'),
                ':weight' => (float)($data['strokeWeight'] ?? 3.5),
                ':opacity' => (float)($data['strokeOpacity'] ?? 0.9),
                ':created' => $data['createdAt'] ?? (date('Y-m-d H:i') . ' WITA'),
                ':import_group' => $importGroup
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

            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return $id;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deleteRoute(string $id): bool {
        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $db->prepare("DELETE FROM GisRoutePoint WHERE routeId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisRoute WHERE id = :id")->execute([':id' => $id]);
            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return true;
        } catch (Throwable) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            return false;
        }
    }

    public static function getSearchPatterns(int|array|null $idOperasi = null, bool $includeGlobal = true): array {
        $scope = self::buildOperasiScopeSql($idOperasi, $includeGlobal);
        if ($scope['empty']) return [];
        $db = getDB();
        $sql = "SELECT * FROM GisSearchPattern{$scope['where']} ORDER BY id DESC";
        if (empty($scope['params'])) {
            $patterns = $db->query($sql)->fetchAll();
        } else {
            $stmt = $db->prepare($sql);
            $stmt->execute($scope['params']);
            $patterns = $stmt->fetchAll();
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
            $p['flipH'] = !empty($p['flipH']);
            $p['flipV'] = !empty($p['flipV']);
        }
        return $patterns;
    }

    public static function saveSearchPattern(array $data): string {
        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $id = self::validId((string)($data['id'] ?? ('pattern-' . time() . '-' . substr(bin2hex(random_bytes(2)), 0, 4))));
            $idOperasi = isset($data['id_operasi']) && $data['id_operasi'] !== '' && $data['id_operasi'] !== null
                ? (int)$data['id_operasi']
                : (isset($data['idOperasi']) && $data['idOperasi'] !== '' && $data['idOperasi'] !== null ? (int)$data['idOperasi'] : null);
            if ($idOperasi !== null && $idOperasi <= 0) {
                $idOperasi = null;
            }

            $rawImpGroup = $data['import_group'] ?? ($data['importGroup'] ?? null);
            $importGroup = ($rawImpGroup !== null && trim((string)$rawImpGroup) !== '') ? mb_substr(trim((string)$rawImpGroup), 0, 100) : null;

            $stmt = $db->prepare("INSERT INTO GisSearchPattern (
                id, id_operasi, name, polygonId, polygonName, patternType, trackSpacingNm,
                legsCount, poskoId, poskoName, color, opacity, strokeWeight, totalDistanceNm, createdAt, flipH, flipV, import_group
            ) VALUES (
                :id, :id_ops, :name, :polyId, :polyName, :pt, :spacing,
                :legs, :poskoId, :poskoName, :color, :opacity, :weight, :dist, :created, :flipH, :flipV, :import_group
            ) ON DUPLICATE KEY UPDATE
                id_operasi = VALUES(id_operasi),
                name = VALUES(name),
                polygonId = VALUES(polygonId),
                polygonName = VALUES(polygonName),
                patternType = VALUES(patternType),
                trackSpacingNm = VALUES(trackSpacingNm),
                legsCount = VALUES(legsCount),
                poskoId = VALUES(poskoId),
                poskoName = VALUES(poskoName),
                color = VALUES(color),
                opacity = VALUES(opacity),
                strokeWeight = VALUES(strokeWeight),
                totalDistanceNm = VALUES(totalDistanceNm),
                createdAt = VALUES(createdAt),
                flipH = VALUES(flipH),
                flipV = VALUES(flipV),
                import_group = COALESCE(VALUES(import_group), import_group)");

            $stmt->execute([
                ':id' => $id,
                ':id_ops' => $idOperasi,
                ':name' => $data['name'] ?? 'Pola Pencarian SAR',
                ':polyId' => self::refId($data['polygonId'] ?? ''),
                ':polyName' => $data['polygonName'] ?? '',
                ':pt' => $data['patternType'] ?? 'parallel_track',
                ':spacing' => (float)($data['trackSpacingNm'] ?? 1.0),
                ':legs' => (int)($data['legsCount'] ?? 5),
                ':poskoId' => self::refId($data['poskoId'] ?? ''),
                ':poskoName' => $data['poskoName'] ?? '',
                ':color' => self::safeColor($data['color'] ?? null, '#0284c7'),
                ':opacity' => isset($data['opacity']) ? (float)$data['opacity'] : 0.9,
                ':weight' => isset($data['strokeWeight']) ? (float)$data['strokeWeight'] : (isset($data['weight']) ? (float)$data['weight'] : 3.0),
                ':dist' => (float)($data['totalDistanceNm'] ?? 0),
                ':created' => $data['createdAt'] ?? (date('Y-m-d H:i') . ' WITA'),
                ':flipH' => filter_var($data['flipH'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                ':flipV' => filter_var($data['flipV'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
                ':import_group' => $importGroup,
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

            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return $id;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public static function deleteSearchPattern(string $id): bool {
        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $db->prepare("DELETE FROM GisSearchPatternWaypoint WHERE searchPatternId = :id")->execute([':id' => $id]);
            $db->prepare("DELETE FROM GisSearchPattern WHERE id = :id")->execute([':id' => $id]);
            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return true;
        } catch (Throwable) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
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
            'label' => 'LKP',
            'pointType' => 'lkp',
            'customIconUrl' => null,
            'description' => trim($desc),
            'category' => 'LKP',
            'alphabetChar' => 'LKP'
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
            'name' => 'Rute ke LKP',
            'vesselSpeedKnots' => $speed,
            'strokeColor' => '#ea580c',
            'strokeWeight' => 3.5,
            'strokeOpacity' => 0.9,
            'createdAt' => date('Y-m-d H:i') . ' WITA',
            'points' => $points
        ]);
    }

    /**
     * Atomically import a batch of tactical GIS entities (Points, Polygons, SearchPatterns, Routes).
     *
     * Validates:
     * - Total entity count between 1 and 500.
     * - Geographic coordinate bounds on all points / waypoints.
     * - Minimum vertex counts (polygons >= 3, routes >= 2, patterns >= 2).
     * - Circles generate 36 geodesic vertices when raw vertices are absent.
     * - Safe local custom icon allowlist with graceful fallback to presets.
     * - 3-stage polygonRef resolution (batch remap -> datum_kansar -> scoped DB lookup -> null).
     * - Single atomic transaction with full rollback on any validation / DB error.
     *
     * @param int|null $idOperasi Target operation ID or null for Base Map
     * @param array $payload Batch payload containing points, polygons, patterns, routes
     * @return array{
     *     counts: array{points: int, polygons: int, patterns: int, routes: int},
     *     points: array,
     *     polygons: array,
     *     patterns: array,
     *     routes: array
     * }
     * @throws InvalidArgumentException
     * @throws Throwable
     */
    public static function importBatch(?int $idOperasi, array $payload): array {
        if ($idOperasi !== null && $idOperasi <= 0) {
            $idOperasi = null;
        }

        $points = isset($payload['points']) && is_array($payload['points']) ? array_values($payload['points']) : [];
        $polygons = isset($payload['polygons']) && is_array($payload['polygons']) ? array_values($payload['polygons']) : [];
        $patterns = isset($payload['patterns']) && is_array($payload['patterns']) ? array_values($payload['patterns']) : [];
        $routes = isset($payload['routes']) && is_array($payload['routes']) ? array_values($payload['routes']) : [];

        $totalCount = count($points) + count($polygons) + count($patterns) + count($routes);
        if ($totalCount === 0 || $totalCount > 500) {
            throw new InvalidArgumentException("Batch import must contain between 1 and 500 entities (received {$totalCount}).");
        }

        $db = getDB();
        $explicitGroup = trim((string)($payload['import_group'] ?? ($payload['importGroup'] ?? '')));
        $importGroup = $explicitGroup !== '' ? mb_substr($explicitGroup, 0, 100) : self::nextImportGroupName($idOperasi);

        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }

        try {
            $seq = 0;
            $newPointIds = [];
            $newPolyIds = [];
            $newPatternIds = [];
            $newRouteIds = [];
            $clientRefIdMap = [];

            // 1. Process Points
            foreach ($points as $idx => $pt) {
                $seq++;
                $newId = "pt-imp-" . bin2hex(random_bytes(5)) . "-{$seq}";
                $label = isset($pt['label']) && $pt['label'] !== '' ? trim((string)$pt['label']) : (isset($pt['name']) ? trim((string)$pt['name']) : 'Titik SAR');
                $coords = self::validateCoordPair($pt['lat'] ?? null, $pt['lng'] ?? null, "point #{$idx} '{$label}'");

                $rawType = trim((string)($pt['pointType'] ?? ($pt['point_type'] ?? '')));
                $rawIcon = $pt['customIconUrl'] ?? ($pt['custom_icon_url'] ?? ($pt['customIconPath'] ?? ($pt['iconUrl'] ?? null)));

                $customIconUrl = null;
                $pointType = $rawType !== '' ? $rawType : 'pin_umum';

                if ($pointType === 'custom_upload' || !empty($rawIcon)) {
                    $safeIcon = self::resolveSafeLocalIconPath($rawIcon);
                    if ($safeIcon !== null) {
                        $pointType = 'custom_upload';
                        $customIconUrl = $safeIcon;
                    } else {
                        // Safe downgrade to preset icon
                        $customIconUrl = null;
                        $fallbackPreset = trim((string)($pt['fallbackType'] ?? ($pt['preset'] ?? '')));
                        if ($fallbackPreset !== '' && preg_match('/^[a-z0-9_]{1,40}$/', $fallbackPreset)) {
                            $pointType = $fallbackPreset;
                        } else {
                            $cat = trim((string)($pt['category'] ?? ''));
                            if (stripos($label, 'kantor') !== false) {
                                $pointType = 'kantor';
                            } elseif (stripos($label, 'pos') !== false || stripos($cat, 'pos') !== false) {
                                $pointType = 'pos_sar';
                            } elseif (stripos($label, 'unit') !== false) {
                                $pointType = 'unit_siaga';
                            } elseif (stripos($label, 'dermaga') !== false || stripos($label, 'kapal') !== false) {
                                $pointType = 'dermaga';
                            } elseif (stripos($label, 'lkp') !== false) {
                                $pointType = 'lkp';
                            } else {
                                $pointType = 'pin_umum';
                            }
                        }
                    }
                } else {
                    if (!preg_match('/^[a-z0-9_]{1,40}$/', $pointType)) {
                        $pointType = 'pin_umum';
                    }
                }

                $pointData = [
                    'id' => $newId,
                    'id_operasi' => $idOperasi,
                    'lat' => $coords['lat'],
                    'lng' => $coords['lng'],
                    'label' => $label,
                    'pointType' => $pointType,
                    'customIconUrl' => $customIconUrl,
                    'description' => $pt['description'] ?? '',
                    'category' => $importGroup,
                    'import_group' => $importGroup,
                    'alphabetChar' => $pt['alphabetChar'] ?? 'A'
                ];
                self::savePoint($pointData);
                $newPointIds[] = $newId;
            }

            // 2. Process Polygons
            foreach ($polygons as $idx => $poly) {
                $seq++;
                $newId = "poly-imp-" . bin2hex(random_bytes(5)) . "-{$seq}";
                $polyName = isset($poly['name']) && $poly['name'] !== '' ? trim((string)$poly['name']) : 'Area Pencarian';
                $shapeType = trim((string)($poly['shapeType'] ?? 'polygon'));
                $cLat = isset($poly['centerLat']) && is_numeric($poly['centerLat']) ? (float)$poly['centerLat'] : null;
                $cLng = isset($poly['centerLng']) && is_numeric($poly['centerLng']) ? (float)$poly['centerLng'] : null;
                $radiusNm = isset($poly['radiusNm']) && is_numeric($poly['radiusNm']) ? (float)$poly['radiusNm'] : null;

                $pts = isset($poly['points']) && is_array($poly['points']) ? $poly['points'] : [];

                if ($shapeType === 'circle' && count($pts) < 3 && $cLat !== null && $cLng !== null && $radiusNm !== null && $radiusNm > 0) {
                    self::validateCoordPair($cLat, $cLng, "circle center '{$polyName}'");
                    $pts = self::generateCircleRing($cLat, $cLng, $radiusNm, 36);
                }

                if (count($pts) < 3) {
                    throw new InvalidArgumentException("Polygon '{$polyName}' requires at least 3 vertices (received " . count($pts) . ").");
                }

                $validatedPts = [];
                foreach ($pts as $vIdx => $pt) {
                    $c = self::validateCoordPair($pt['lat'] ?? null, $pt['lng'] ?? null, "polygon '{$polyName}' vertex #{$vIdx}");
                    $validatedPts[] = [
                        'lat' => $c['lat'],
                        'lng' => $c['lng'],
                        'label' => $pt['label'] ?? ''
                    ];
                }

                $calcArea = self::calculatePolygonAreaNm2(
                    $validatedPts,
                    $shapeType,
                    $radiusNm,
                    isset($poly['boxWidthNm']) && is_numeric($poly['boxWidthNm']) ? (float)$poly['boxWidthNm'] : null,
                    isset($poly['boxHeightNm']) && is_numeric($poly['boxHeightNm']) ? (float)$poly['boxHeightNm'] : null
                );
                $areaNm2 = isset($poly['areaNm2']) && (float)$poly['areaNm2'] > 0 ? (float)$poly['areaNm2'] : $calcArea;

                $polyData = [
                    'id' => $newId,
                    'id_operasi' => $idOperasi,
                    'name' => $polyName,
                    'shapeType' => $shapeType,
                    'strokeColor' => $poly['strokeColor'] ?? '#ea580c',
                    'strokeWeight' => $poly['strokeWeight'] ?? 2.5,
                    'strokeOpacity' => $poly['strokeOpacity'] ?? 1.0,
                    'fillColor' => $poly['fillColor'] ?? '#ea580c',
                    'fillOpacity' => $poly['fillOpacity'] ?? 0.2,
                    'centerLat' => $cLat,
                    'centerLng' => $cLng,
                    'radiusNm' => $radiusNm,
                    'boxWidthNm' => isset($poly['boxWidthNm']) && is_numeric($poly['boxWidthNm']) ? (float)$poly['boxWidthNm'] : null,
                    'boxHeightNm' => isset($poly['boxHeightNm']) && is_numeric($poly['boxHeightNm']) ? (float)$poly['boxHeightNm'] : null,
                    'areaNm2' => $areaNm2,
                    'import_group' => $importGroup,
                    'points' => $validatedPts
                ];

                self::savePolygon($polyData);
                $newPolyIds[] = $newId;

                // Index clientRefId mappings for pattern / route cross-reference
                $cRef = trim((string)($poly['clientRefId'] ?? ($poly['sourceId'] ?? ($poly['id'] ?? ''))));
                if ($cRef !== '') {
                    $clientRefIdMap[$cRef] = ['id' => $newId, 'name' => $polyName];
                }
                if (!empty($poly['id']) && (string)$poly['id'] !== $cRef) {
                    $clientRefIdMap[(string)$poly['id']] = ['id' => $newId, 'name' => $polyName];
                }
                $clientRefIdMap[$newId] = ['id' => $newId, 'name' => $polyName];
            }

            // 3-Stage polygonRef resolver closure
            $resolvePolyRef = function(mixed $ref) use ($db, $idOperasi, $clientRefIdMap): array {
                $refStr = trim((string)$ref);
                if ($refStr === '') {
                    return ['id' => '', 'name' => ''];
                }
                if ($refStr === 'datum_kansar') {
                    return ['id' => 'datum_kansar', 'name' => 'Datum / Kansar Kendari'];
                }
                if (isset($clientRefIdMap[$refStr])) {
                    return $clientRefIdMap[$refStr];
                }
                if ($idOperasi !== null) {
                    $stmt = $db->prepare("SELECT id, name FROM GisPolygon WHERE id = :ref AND (id_operasi = :id_ops OR id_operasi IS NULL) LIMIT 1");
                    $stmt->execute([':ref' => $refStr, ':id_ops' => $idOperasi]);
                } else {
                    $stmt = $db->prepare("SELECT id, name FROM GisPolygon WHERE id = :ref AND id_operasi IS NULL LIMIT 1");
                    $stmt->execute([':ref' => $refStr]);
                }
                $row = $stmt->fetch();
                if ($row) {
                    return ['id' => (string)$row['id'], 'name' => (string)$row['name']];
                }
                return ['id' => '', 'name' => ''];
            };

            // 3. Process Search Patterns
            foreach ($patterns as $idx => $pat) {
                $seq++;
                $newId = "pattern-imp-" . bin2hex(random_bytes(5)) . "-{$seq}";
                $patName = isset($pat['name']) && $pat['name'] !== '' ? trim((string)$pat['name']) : 'Pola Pencarian SAR';
                $wps = !empty($pat['waypoints']) && is_array($pat['waypoints'])
                    ? $pat['waypoints']
                    : (!empty($pat['points']) && is_array($pat['points']) ? $pat['points'] : []);

                if (count($wps) < 2) {
                    throw new InvalidArgumentException("Search pattern '{$patName}' requires at least 2 waypoints (received " . count($wps) . ").");
                }

                $validatedWps = [];
                foreach ($wps as $wIdx => $wp) {
                    $c = self::validateCoordPair($wp['lat'] ?? null, $wp['lng'] ?? null, "pattern '{$patName}' waypoint #{$wIdx}");
                    $validatedWps[] = [
                        'lat' => $c['lat'],
                        'lng' => $c['lng'],
                        'label' => $wp['label'] ?? ''
                    ];
                }

                $ref = $pat['polygonRef'] ?? ($pat['polygonId'] ?? '');
                $resolved = $resolvePolyRef($ref);

                $patternData = [
                    'id' => $newId,
                    'id_operasi' => $idOperasi,
                    'name' => $patName,
                    'polygonId' => $resolved['id'],
                    'polygonName' => $resolved['name'],
                    'patternType' => $pat['patternType'] ?? 'parallel_track',
                    'trackSpacingNm' => isset($pat['trackSpacingNm']) && is_numeric($pat['trackSpacingNm']) ? (float)$pat['trackSpacingNm'] : 1.0,
                    'legsCount' => isset($pat['legsCount']) && is_numeric($pat['legsCount']) ? (int)$pat['legsCount'] : 5,
                    'poskoId' => $pat['poskoId'] ?? '',
                    'poskoName' => $pat['poskoName'] ?? '',
                    'color' => $pat['color'] ?? '#0284c7',
                    'opacity' => isset($pat['opacity']) && is_numeric($pat['opacity']) ? (float)$pat['opacity'] : 0.9,
                    'strokeWeight' => isset($pat['strokeWeight']) && is_numeric($pat['strokeWeight']) ? (float)$pat['strokeWeight'] : (isset($pat['weight']) && is_numeric($pat['weight']) ? (float)$pat['weight'] : 3.0),
                    'totalDistanceNm' => isset($pat['totalDistanceNm']) && is_numeric($pat['totalDistanceNm']) ? (float)$pat['totalDistanceNm'] : 0.0,
                    'flipH' => !empty($pat['flipH']),
                    'flipV' => !empty($pat['flipV']),
                    'import_group' => $importGroup,
                    'waypoints' => $validatedWps
                ];

                self::saveSearchPattern($patternData);
                $newPatternIds[] = $newId;
            }

            // 4. Process Routes
            foreach ($routes as $idx => $rt) {
                $seq++;
                $newId = "route-imp-" . bin2hex(random_bytes(5)) . "-{$seq}";
                $rtName = isset($rt['name']) && $rt['name'] !== '' ? trim((string)$rt['name']) : 'Rute Operasi';
                $pts = !empty($rt['points']) && is_array($rt['points'])
                    ? $rt['points']
                    : (!empty($rt['waypoints']) && is_array($rt['waypoints']) ? $rt['waypoints'] : []);

                if (count($pts) < 2) {
                    throw new InvalidArgumentException("Route '{$rtName}' requires at least 2 points (received " . count($pts) . ").");
                }

                $validatedPts = [];
                foreach ($pts as $pIdx => $pt) {
                    $c = self::validateCoordPair($pt['lat'] ?? null, $pt['lng'] ?? null, "route '{$rtName}' point #{$pIdx}");
                    $validatedPts[] = [
                        'lat' => $c['lat'],
                        'lng' => $c['lng'],
                        'label' => $pt['label'] ?? ''
                    ];
                }

                $ref = $rt['polygonRef'] ?? ($rt['polygonId'] ?? '');
                $resolved = $resolvePolyRef($ref);

                $routeData = [
                    'id' => $newId,
                    'id_operasi' => $idOperasi,
                    'name' => $rtName,
                    'polygonId' => $resolved['id'] !== '' ? $resolved['id'] : null,
                    'vesselSpeedKnots' => isset($rt['vesselSpeedKnots']) && is_numeric($rt['vesselSpeedKnots']) ? (float)$rt['vesselSpeedKnots'] : 25.0,
                    'strokeColor' => $rt['strokeColor'] ?? '#059669',
                    'strokeWeight' => isset($rt['strokeWeight']) && is_numeric($rt['strokeWeight']) ? (float)$rt['strokeWeight'] : 3.5,
                    'strokeOpacity' => isset($rt['strokeOpacity']) && is_numeric($rt['strokeOpacity']) ? (float)$rt['strokeOpacity'] : 0.9,
                    'import_group' => $importGroup,
                    'points' => $validatedPts
                ];

                self::saveRoute($routeData);
                $newRouteIds[] = $newId;
            }

            // 5. Hydrate persisted shapes exactly matching model getters
            $hydratedPoints = [];
            if (!empty($newPointIds)) {
                $in = implode(',', array_fill(0, count($newPointIds), '?'));
                $stmt = $db->prepare("SELECT * FROM GisPoint WHERE id IN ($in)");
                $stmt->execute($newPointIds);
                $rows = $stmt->fetchAll();
                $map = [];
                foreach ($rows as $r) {
                    $r['lat'] = (float)$r['lat'];
                    $r['lng'] = (float)$r['lng'];
                    $map[$r['id']] = $r;
                }
                foreach ($newPointIds as $nid) {
                    if (isset($map[$nid])) $hydratedPoints[] = $map[$nid];
                }
            }

            $hydratedPolygons = [];
            if (!empty($newPolyIds)) {
                $in = implode(',', array_fill(0, count($newPolyIds), '?'));
                $stmt = $db->prepare("SELECT * FROM GisPolygon WHERE id IN ($in)");
                $stmt->execute($newPolyIds);
                $polys = $stmt->fetchAll();

                $stmtPts = $db->prepare("SELECT polygonId, lat, lng, label FROM GisPolygonPoint WHERE polygonId IN ($in) ORDER BY id ASC");
                $stmtPts->execute($newPolyIds);
                $allPts = $stmtPts->fetchAll();
                $ptsByPoly = [];
                foreach ($allPts as $pt) {
                    $ptsByPoly[$pt['polygonId']][] = [
                        'lat' => (float)$pt['lat'],
                        'lng' => (float)$pt['lng'],
                        'label' => $pt['label'] ?? ''
                    ];
                }

                $map = [];
                foreach ($polys as $p) {
                    $p['points'] = $ptsByPoly[$p['id']] ?? [];
                    $calcArea = self::calculatePolygonAreaNm2(
                        $p['points'],
                        $p['shapeType'] ?? null,
                        isset($p['radiusNm']) ? (float)$p['radiusNm'] : null,
                        isset($p['boxWidthNm']) ? (float)$p['boxWidthNm'] : null,
                        isset($p['boxHeightNm']) ? (float)$p['boxHeightNm'] : null
                    );
                    $p['areaNm2'] = (!empty($p['areaNm2']) && (float)$p['areaNm2'] > 0) ? (float)$p['areaNm2'] : $calcArea;
                    $p['strokeOpacity'] = isset($p['strokeOpacity']) ? (float)$p['strokeOpacity'] : 1.0;
                    $map[$p['id']] = $p;
                }
                foreach ($newPolyIds as $nid) {
                    if (isset($map[$nid])) $hydratedPolygons[] = $map[$nid];
                }
            }

            $hydratedPatterns = [];
            if (!empty($newPatternIds)) {
                $in = implode(',', array_fill(0, count($newPatternIds), '?'));
                $stmt = $db->prepare("SELECT * FROM GisSearchPattern WHERE id IN ($in)");
                $stmt->execute($newPatternIds);
                $pats = $stmt->fetchAll();

                $stmtWp = $db->prepare("SELECT searchPatternId, lat, lng, label FROM GisSearchPatternWaypoint WHERE searchPatternId IN ($in) ORDER BY id ASC");
                $stmtWp->execute($newPatternIds);
                $allWp = $stmtWp->fetchAll();
                $wpByPattern = [];
                foreach ($allWp as $wp) {
                    $wpByPattern[$wp['searchPatternId']][] = [
                        'lat' => (float)$wp['lat'],
                        'lng' => (float)$wp['lng'],
                        'label' => $wp['label'] ?? ''
                    ];
                }

                $map = [];
                foreach ($pats as $p) {
                    $p['waypoints'] = $wpByPattern[$p['id']] ?? [];
                    $p['flipH'] = !empty($p['flipH']);
                    $p['flipV'] = !empty($p['flipV']);
                    $map[$p['id']] = $p;
                }
                foreach ($newPatternIds as $nid) {
                    if (isset($map[$nid])) $hydratedPatterns[] = $map[$nid];
                }
            }

            $hydratedRoutes = [];
            if (!empty($newRouteIds)) {
                $in = implode(',', array_fill(0, count($newRouteIds), '?'));
                $stmt = $db->prepare("SELECT * FROM GisRoute WHERE id IN ($in)");
                $stmt->execute($newRouteIds);
                $rts = $stmt->fetchAll();

                $stmtPts = $db->prepare("SELECT routeId, lat, lng, label FROM GisRoutePoint WHERE routeId IN ($in) ORDER BY id ASC");
                $stmtPts->execute($newRouteIds);
                $allPts = $stmtPts->fetchAll();
                $ptsByRoute = [];
                foreach ($allPts as $pt) {
                    $ptsByRoute[$pt['routeId']][] = [
                        'lat' => (float)$pt['lat'],
                        'lng' => (float)$pt['lng'],
                        'label' => $pt['label'] ?? ''
                    ];
                }

                $map = [];
                foreach ($rts as $r) {
                    $r['vesselSpeedKnots'] = (float)($r['vesselSpeedKnots'] ?? 25);
                    $r['strokeWeight'] = (float)($r['strokeWeight'] ?? 3.5);
                    $r['strokeOpacity'] = (float)($r['strokeOpacity'] ?? 0.9);
                    $r['polygonId'] = ($r['polygonId'] ?? '') !== '' ? (string)$r['polygonId'] : null;
                    $r['points'] = $ptsByRoute[$r['id']] ?? [];
                    $map[$r['id']] = $r;
                }
                foreach ($newRouteIds as $nid) {
                    if (isset($map[$nid])) $hydratedRoutes[] = $map[$nid];
                }
            }

            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }

            return [
                'import_group' => $importGroup,
                'counts' => [
                    'points' => count($hydratedPoints),
                    'polygons' => count($hydratedPolygons),
                    'patterns' => count($hydratedPatterns),
                    'routes' => count($hydratedRoutes)
                ],
                'points' => $hydratedPoints,
                'polygons' => $hydratedPolygons,
                'patterns' => $hydratedPatterns,
                'routes' => $hydratedRoutes
            ];
        } catch (\Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Hitung nama grup import berikutnya ('Import 1', 'Import 2', dst.) pada cakupan $idOperasi.
     */
    public static function nextImportGroupName(?int $idOperasi): string {
        $db = getDB();
        $opVal = ($idOperasi !== null && $idOperasi > 0) ? $idOperasi : null;
        $uniqueGroups = [];
        $maxNum = 0;

        foreach (['GisPoint', 'GisPolygon', 'GisSearchPattern', 'GisRoute'] as $tbl) {
            if ($opVal !== null) {
                $stmt = $db->prepare("SELECT DISTINCT import_group FROM `{$tbl}` WHERE id_operasi = :op AND import_group IS NOT NULL AND import_group != ''");
                $stmt->execute([':op' => $opVal]);
            } else {
                $stmt = $db->query("SELECT DISTINCT import_group FROM `{$tbl}` WHERE id_operasi IS NULL AND import_group IS NOT NULL AND import_group != ''");
            }
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $grp) {
                $g = trim((string)$grp);
                if ($g === '') continue;
                $uniqueGroups[strtolower($g)] = $g;
                if (preg_match('/^Import\s+(\d+)$/i', $g, $m)) {
                    $maxNum = max($maxNum, (int)$m[1]);
                }
            }
        }

        $next = max($maxNum, count($uniqueGroups)) + 1;
        while (isset($uniqueGroups[strtolower('Import ' . $next)])) {
            $next++;
        }
        return 'Import ' . $next;
    }

    /**
     * Ubah nama grup import (import_group & GisPoint.category) pada cakupan operasi atau Base Map.
     */
    public static function renameImportGroup(?int $idOperasi, string $oldName, string $newName): int {
        $oldClean = trim($oldName);
        $newClean = trim($newName);
        if ($oldClean === '' || $newClean === '') {
            throw new InvalidArgumentException('Nama kategori import lama dan baru tidak boleh kosong.');
        }
        if (mb_strlen($newClean, 'UTF-8') > 100 || preg_match('/[\x00-\x1F\x7F<>]/', $newClean)) {
            throw new InvalidArgumentException('Nama kategori import baru tidak valid (maksimal 100 karakter, tanpa tag HTML).');
        }
        if ($oldClean === $newClean) {
            return 0;
        }

        $db = getDB();
        $opVal = ($idOperasi !== null && $idOperasi > 0) ? $idOperasi : null;
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }

        try {
            $updated = 0;
            if ($opVal !== null) {
                $stmtPt = $db->prepare("UPDATE GisPoint SET import_group = :new1, category = CASE WHEN category = :old1 THEN :new2 ELSE category END WHERE id_operasi = :op AND (import_group = :old2 OR category = :old3)");
                $stmtPt->execute([':new1' => $newClean, ':old1' => $oldClean, ':new2' => $newClean, ':op' => $opVal, ':old2' => $oldClean, ':old3' => $oldClean]);
                $updated += $stmtPt->rowCount();

                foreach (['GisPolygon', 'GisSearchPattern', 'GisRoute'] as $tbl) {
                    $stmt = $db->prepare("UPDATE `{$tbl}` SET import_group = :new WHERE id_operasi = :op AND import_group = :old");
                    $stmt->execute([':new' => $newClean, ':op' => $opVal, ':old' => $oldClean]);
                    $updated += $stmt->rowCount();
                }
            } else {
                $stmtPt = $db->prepare("UPDATE GisPoint SET import_group = :new1, category = CASE WHEN category = :old1 THEN :new2 ELSE category END WHERE id_operasi IS NULL AND (import_group = :old2 OR category = :old3)");
                $stmtPt->execute([':new1' => $newClean, ':old1' => $oldClean, ':new2' => $newClean, ':old2' => $oldClean, ':old3' => $oldClean]);
                $updated += $stmtPt->rowCount();

                foreach (['GisPolygon', 'GisSearchPattern', 'GisRoute'] as $tbl) {
                    $stmt = $db->prepare("UPDATE `{$tbl}` SET import_group = :new WHERE id_operasi IS NULL AND import_group = :old");
                    $stmt->execute([':new' => $newClean, ':old' => $oldClean]);
                    $updated += $stmt->rowCount();
                }
            }

            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return $updated;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Hapus massal entitas GIS (points, polygons, patterns, routes) dalam satu transaksi.
     */
    public static function bulkDelete(string $entityType, array $ids): int {
        $type = strtolower(trim($entityType));
        if (!in_array($type, ['points', 'polygons', 'patterns', 'routes'], true)) {
            throw new InvalidArgumentException("Tipe entitas bulk delete tidak valid: {$entityType}");
        }
        $cleanIds = array_values(array_unique(array_filter(array_map(fn($v) => trim((string)$v), $ids), fn($v) => $v !== '')));
        if (empty($cleanIds) || count($cleanIds) > 500) {
            throw new InvalidArgumentException('Jumlah ID bulk delete harus antara 1 hingga 500.');
        }

        $db = getDB();
        $ownTx = !$db->inTransaction();
        if ($ownTx) {
            $db->beginTransaction();
        }
        try {
            $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
            if ($type === 'polygons') {
                $db->prepare("DELETE FROM GisPolygonPoint WHERE polygonId IN ({$placeholders})")->execute($cleanIds);
                $stmtParent = $db->prepare("DELETE FROM GisPolygon WHERE id IN ({$placeholders})");
            } elseif ($type === 'patterns') {
                $db->prepare("DELETE FROM GisSearchPatternWaypoint WHERE searchPatternId IN ({$placeholders})")->execute($cleanIds);
                $stmtParent = $db->prepare("DELETE FROM GisSearchPattern WHERE id IN ({$placeholders})");
            } elseif ($type === 'routes') {
                $db->prepare("DELETE FROM GisRoutePoint WHERE routeId IN ({$placeholders})")->execute($cleanIds);
                $stmtParent = $db->prepare("DELETE FROM GisRoute WHERE id IN ({$placeholders})");
            } else {
                $stmtParent = $db->prepare("DELETE FROM GisPoint WHERE id IN ({$placeholders})");
            }
            $stmtParent->execute($cleanIds);
            $deleted = $stmtParent->rowCount();

            if ($ownTx && $db->inTransaction()) {
                $db->commit();
            }
            return $deleted;
        } catch (Throwable $e) {
            if ($ownTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
