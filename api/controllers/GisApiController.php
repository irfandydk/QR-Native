<?php
/**
 * QR-SAR - Tactical Maritime GIS & Geocoding API Controller
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

class GisApiController extends BaseApiController {
    public static function handle(string $path, string $method, array $input): bool {
        // 1. Geocode endpoint (Global & Indonesian Geocoding)
        if ($path === '/geocode') {
            self::handleGeocode();
            return true;
        }

        // 2. GIS Bundle / All Layers
        if (self::handleBundleRoute($path, $method)) {
            return true;
        }

        // 3. GIS Points
        if (self::handlePointsRoute($path, $method, $input)) {
            return true;
        }

        // 4. Custom Icon Upload & Gallery
        if (self::handleCustomIconsRoute($path, $method, $input)) {
            return true;
        }

        // 5. GIS Polygons
        if (self::handlePolygonsRoute($path, $method, $input)) {
            return true;
        }

        // 6. GIS Search Patterns
        if (self::handlePatternsRoute($path, $method, $input)) {
            return true;
        }

        // 7. GIS Routes
        if (self::handleRoutesRoute($path, $method, $input)) {
            return true;
        }

        // 8. OSRM Directions & Route Calculation
        if (self::handleRoutingDirectionsRoute($path, $method, $input)) {
            return true;
        }

        // 9. Document Map Snapshots (Capture 16:9 for Official Printouts)
        if (self::handleSnapshotsRoute($path, $method, $input)) {
            return true;
        }

        return false;
    }

    private static function handleBundleRoute(string $path, string $method): bool {
        if (($path === '/gis/data' || $path === '/gis/bundle') && $method === 'GET') {
            self::requireAuth();
            $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
            $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
                ? true
                : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');

            $points = GisModel::getPoints($idOperasi, $includeGlobal);
            $polygons = GisModel::getPolygons($idOperasi, $includeGlobal);
            $routes = GisModel::getRoutes($idOperasi, $includeGlobal);
            $searchPatterns = GisModel::getSearchPatterns($idOperasi, $includeGlobal);
            $bundle = [
                'id_operasi' => $idOperasi,
                'include_base_map' => $includeGlobal,
                'points' => $points,
                'polygons' => $polygons,
                'routes' => $routes,
                'searchPatterns' => $searchPatterns
            ];

            self::json([
                'success' => true,
                'data' => $bundle,
                'id_operasi' => $idOperasi,
                'include_base_map' => $includeGlobal,
                'sar_units' => SarUnitModel::getAll(),
                'points' => $points,
                'polygons' => $polygons,
                'routes' => $routes,
                'search_patterns' => $searchPatterns,
                'searchPatterns' => $searchPatterns,
                'operasi' => (!$idOperasi || isset($_GET['include_operations'])) ? OperasiModel::getAll('OPEN') : []
            ]);
            return true;
        }
        return false;
    }

    private static function handlePointsRoute(string $path, string $method, array $input): bool {
        if (($path === '/gis/point' || $path === '/gis/points') && $method === 'GET') {
            self::requireAuth();
            $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
            $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
                ? true
                : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
            self::json(['success' => true, 'data' => GisModel::getPoints($idOperasi, $includeGlobal)]);
            return true;
        }

        if (($path === '/gis/point' || $path === '/gis/points') && in_array($method, ['POST', 'PUT'])) {
            self::requireAuth();
            $id = GisModel::savePoint($input);
            self::json(['success' => true, 'id' => $id, 'data' => $input]);
            return true;
        }

        if (preg_match('#^/gis/point(?:s)?/(.+)$#', $path, $m) && in_array($method, ['POST', 'PUT'])) {
            self::requireAuth();
            $input['id'] = $m[1];
            $id = GisModel::savePoint($input);
            self::json(['success' => true, 'id' => $id, 'data' => $input]);
            return true;
        }

        if (preg_match('#^/gis/point(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
            self::requireAuth();
            $ok = GisModel::deletePoint($m[1]);
            self::json(['success' => $ok]);
            return true;
        }

        return false;
    }

    private static function handleCustomIconsRoute(string $path, string $method, array $input): bool {
        if ($path === '/gis/upload-icon' && $method === 'POST') {
            self::requireAuth();
            self::handleUploadIcon();
            return true;
        }

        if ($path === '/gis/custom-icons' && $method === 'GET') {
            self::requireAuth();
            self::handleGetCustomIcons();
            return true;
        }

        if ($path === '/gis/custom-icons' && $method === 'DELETE') {
            self::requireAuth();
            self::handleDeleteCustomIcon($input);
            return true;
        }

        return false;
    }

    private static function handlePolygonsRoute(string $path, string $method, array $input): bool {
        if (($path === '/gis/polygon' || $path === '/gis/polygons') && $method === 'GET') {
            self::requireAuth();
            $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
            $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
                ? true
                : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
            self::json(['success' => true, 'data' => GisModel::getPolygons($idOperasi, $includeGlobal)]);
            return true;
        }

        if (($path === '/gis/polygon' || $path === '/gis/polygons') && in_array($method, ['POST', 'PUT'])) {
            self::requireAuth();
            $id = GisModel::savePolygon($input);
            self::json(['success' => true, 'id' => $id, 'data' => $input]);
            return true;
        }

        if (preg_match('#^/gis/polygon(?:s)?/(.+)$#', $path, $m) && in_array($method, ['POST', 'PUT'])) {
            self::requireAuth();
            $input['id'] = $m[1];
            $id = GisModel::savePolygon($input);
            self::json(['success' => true, 'id' => $id, 'data' => $input]);
            return true;
        }

        if (preg_match('#^/gis/polygon(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
            self::requireAuth();
            $ok = GisModel::deletePolygon($m[1]);
            self::json(['success' => $ok]);
            return true;
        }

        return false;
    }

    private static function handlePatternsRoute(string $path, string $method, array $input): bool {
        if (($path === '/gis/pattern' || $path === '/gis/patterns' || $path === '/gis/search-patterns') && $method === 'GET') {
            self::requireAuth();
            $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
            $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
                ? true
                : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
            self::json(['success' => true, 'data' => GisModel::getSearchPatterns($idOperasi, $includeGlobal)]);
            return true;
        }

        if (($path === '/gis/pattern' || $path === '/gis/patterns' || $path === '/gis/search-patterns') && in_array($method, ['POST', 'PUT'])) {
            self::requireAuth();
            $id = GisModel::saveSearchPattern($input);
            self::json(['success' => true, 'id' => $id, 'data' => $input]);
            return true;
        }

        if (preg_match('#^/gis/(?:pattern|search-pattern)(?:s)?/(.+)$#', $path, $m) && in_array($method, ['POST', 'PUT'])) {
            self::requireAuth();
            $input['id'] = $m[1];
            $id = GisModel::saveSearchPattern($input);
            self::json(['success' => true, 'id' => $id, 'data' => $input]);
            return true;
        }

        if (preg_match('#^/gis/(?:pattern|search-pattern)(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
            self::requireAuth();
            $ok = GisModel::deleteSearchPattern($m[1]);
            self::json(['success' => $ok]);
            return true;
        }

        return false;
    }

    private static function handleRoutesRoute(string $path, string $method, array $input): bool {
        if (($path === '/gis/route' || $path === '/gis/routes') && $method === 'GET') {
            self::requireAuth();
            $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
            $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
                ? true
                : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
            self::json(['success' => true, 'data' => GisModel::getRoutes($idOperasi, $includeGlobal)]);
            return true;
        }

        if (($path === '/gis/route' || $path === '/gis/routes') && in_array($method, ['POST', 'PUT'])) {
            self::requireAuth();
            $id = GisModel::saveRoute($input);
            self::json(['success' => true, 'id' => $id]);
            return true;
        }

        if (preg_match('#^/gis/route(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
            self::requireAuth();
            $ok = GisModel::deleteRoute($m[1]);
            self::json(['success' => $ok]);
            return true;
        }

        return false;
    }

    private static function handleRoutingDirectionsRoute(string $path, string $method, array $input): bool {
        if ($path === '/gis/directions' && $method === 'POST') {
            self::requireAuth();
            self::handleDirections($input);
            return true;
        }

        if ($path === '/maps/calculate-route' && $method === 'POST') {
            self::requireAuth();
            self::handleCalculateRoute($input);
            return true;
        }

        return false;
    }

    private static function handleGeocode(): void {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            self::json(['success' => true, 'data' => []]);
        }

        $results = [];

        $toDms = function(float $lat, float $lng): string {
            $latDir = $lat < 0 ? 'S' : 'N';
            $latVal = abs($lat);
            $latDeg = (int)floor($latVal);
            $latMin = (int)floor(($latVal - $latDeg) * 60);
            $latSec = round((($latVal - $latDeg) * 60 - $latMin) * 60, 1);

            $lngDir = $lng < 0 ? 'W' : 'E';
            $lngVal = abs($lng);
            $lngDeg = (int)floor($lngVal);
            $lngMin = (int)floor(($lngVal - $lngDeg) * 60);
            $lngSec = round((($lngVal - $lngDeg) * 60 - $lngMin) * 60, 1);

            return sprintf("%02d° %02d' %04.1f\" %s - %03d° %02d' %04.1f\" %s",
                $latDeg, $latMin, $latSec, $latDir,
                $lngDeg, $lngMin, $lngSec, $lngDir
            );
        };

        // 1. Raw Coordinates
        if (preg_match('/^\s*([+-]?\d+(?:\.\d+)?)[,\s]+([+-]?\d+(?:\.\d+)?)\s*$/', $q, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                self::json([
                    'success' => true,
                    'query' => $q,
                    'count' => 1,
                    'data' => [[
                        'name' => sprintf("Titik Koordinat: %.5f, %.5f", $lat, $lng),
                        'address' => $toDms($lat, $lng),
                        'lat' => $lat,
                        'lng' => $lng,
                        'type' => 'Koordinat GPS',
                        'badge' => 'GPS DD',
                        'icon' => 'crosshair',
                        'dms' => $toDms($lat, $lng)
                    ]]
                ]);
            }
        }

        // DMS parsing
        if (preg_match('#(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["]*\s*([NSEWnsew])[,\s]+(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["]*\s*([NSEWnsew])#u', $q, $dmsM)) {
            $latDeg = (float)$dmsM[1]; $latMin = (float)$dmsM[2]; $latSec = (float)$dmsM[3]; $latDir = strtoupper($dmsM[4]);
            $lngDeg = (float)$dmsM[5]; $lngMin = (float)$dmsM[6]; $lngSec = (float)$dmsM[7]; $lngDir = strtoupper($dmsM[8]);

            $lat = $latDeg + ($latMin / 60) + ($latSec / 3600);
            if ($latDir === 'S') $lat = -$lat;
            $lng = $lngDeg + ($lngMin / 60) + ($lngSec / 3600);
            if ($lngDir === 'W') $lng = -$lng;

            self::json([
                'success' => true,
                'query' => $q,
                'count' => 1,
                'data' => [[
                    'name' => sprintf("Titik Koordinat DMS: %.5f, %.5f", $lat, $lng),
                    'address' => $toDms($lat, $lng),
                    'lat' => $lat,
                    'lng' => $lng,
                    'type' => 'Koordinat GPS',
                    'badge' => 'GPS DMS',
                    'icon' => 'crosshair',
                    'dms' => $toDms($lat, $lng)
                ]]
            ]);
        }

        // 2. MySQL GisPoint for saved plotting points
        try {
            $db = getDB();
            $likeParam = '%' . $q . '%';
            $stmtGis = $db->prepare("
                SELECT COALESCE(NULLIF(label, ''), NULLIF(category, 'Tanpa Kategori'), 'Titik Plotting SAR') as name, COALESCE(NULLIF(description, ''), 'Titik Plotting SAR') as address, lat, lng, pointType as type
                FROM GisPoint
                WHERE (label LIKE :q1 OR description LIKE :q2 OR category LIKE :q3)
                LIMIT 8
            ");
            $stmtGis->execute([':q1' => $likeParam, ':q2' => $likeParam, ':q3' => $likeParam]);
            $gisRows = $stmtGis->fetchAll();

            foreach ($gisRows as $row) {
                if (!empty($row['lat']) && !empty($row['lng'])) {
                    $lat = (float)$row['lat'];
                    $lng = (float)$row['lng'];
                    $results[] = [
                        'name' => $row['name'],
                        'address' => $row['address'] ?: 'Titik Plotting SAR Database',
                        'lat' => $lat,
                        'lng' => $lng,
                        'type' => 'Titik Plotting SAR',
                        'badge' => 'PLOT SAR',
                        'icon' => 'map-pin',
                        'dms' => $toDms($lat, $lng)
                    ];
                }
            }
        } catch (Throwable $e) {}

        // 3. ArcGIS World Geocoding
        try {
            $url = "https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?" . http_build_query([
                'f' => 'json',
                'singleLine' => $q,
                'countryCode' => 'IDN',
                'maxLocations' => 8,
                'outFields' => 'Match_addr,Addr_type,Type,City,Subregion,Region'
            ]);
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'header' => "User-Agent: QRSAR-Basarnas/2.0\r\nAccept: application/json\r\n"
                ]
            ]);
            $json = @file_get_contents($url, false, $ctx);
            if ($json) {
                $res = json_decode($json, true);
                if (!empty($res['candidates'])) {
                    foreach ($res['candidates'] as $c) {
                        $lat = (float)($c['location']['y'] ?? 0);
                        $lng = (float)($c['location']['x'] ?? 0);
                        if ($lat == 0 && $lng == 0) continue;

                        $addr = $c['address'] ?? '';
                        $parts = explode(',', $addr);
                        $name = trim($parts[0]);
                        $sub = trim(implode(', ', array_slice($parts, 1))) ?: $addr;

                        $isDup = false;
                        foreach ($results as $r) {
                            if (abs($r['lat'] - $lat) < 0.0005 && abs($r['lng'] - $lng) < 0.0005) {
                                $isDup = true;
                                break;
                            }
                        }
                        if ($isDup) continue;

                        $type = $c['attributes']['Type'] ?? $c['attributes']['Addr_type'] ?? 'Lokasi';
                        $results[] = [
                            'name' => $name,
                            'address' => $sub,
                            'lat' => $lat,
                            'lng' => $lng,
                            'type' => $type,
                            'badge' => 'MAPS',
                            'icon' => 'map-pin',
                            'dms' => $toDms($lat, $lng)
                        ];
                    }
                }
            }
        } catch (Throwable $e) {}

        // 4. Photon OSM API fallback
        if (count($results) < 5) {
            try {
                $url = "https://photon.komoot.io/api/?" . http_build_query([
                    'q' => $q,
                    'limit' => 6
                ]);
                $ctx = stream_context_create([
                    'http' => [
                        'timeout' => 3,
                        'header' => "User-Agent: QRSAR-Basarnas/2.0\r\nAccept: application/json\r\n"
                    ]
                ]);
                $json = @file_get_contents($url, false, $ctx);
                if ($json) {
                    $res = json_decode($json, true);
                    if (!empty($res['features'])) {
                        foreach ($res['features'] as $f) {
                            $props = $f['properties'] ?? [];
                            $coords = $f['geometry']['coordinates'] ?? [];
                            if (empty($coords) || count($coords) < 2) continue;
                            $lng = (float)$coords[0];
                            $lat = (float)$coords[1];

                            $name = $props['name'] ?? '';
                            if (!$name) continue;

                            $isDup = false;
                            foreach ($results as $r) {
                                if (abs($r['lat'] - $lat) < 0.0005 && abs($r['lng'] - $lng) < 0.0005) {
                                    $isDup = true;
                                    break;
                                }
                            }
                            if ($isDup) continue;

                            $addrParts = array_filter([
                                $props['district'] ?? null,
                                $props['city'] ?? $props['county'] ?? null,
                                $props['state'] ?? null,
                                $props['country'] ?? null
                            ]);
                            $sub = implode(', ', $addrParts) ?: 'Indonesia';

                            $results[] = [
                                'name' => $name,
                                'address' => $sub,
                                'lat' => $lat,
                                'lng' => $lng,
                                'type' => $props['type'] ?? 'Wilayah',
                                'badge' => 'GEODATA',
                                'icon' => 'navigation',
                                'dms' => $toDms($lat, $lng)
                            ];
                        }
                    }
                }
            } catch (Throwable $e) {}
        }

        self::json(['success' => true, 'query' => $q, 'count' => count($results), 'data' => array_slice($results, 0, 10)]);
    }

    private static function handleUploadIcon(): void {
        if (!empty($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['icon'];

            if ($file['size'] > 2 * 1024 * 1024) {
                self::error('Ukuran file ikon terlalu besar (Maksimal 2 MB).', 400);
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                self::error('Format file tidak didukung (Gunakan PNG, JPG, JPEG, SVG, WebP).', 400);
            }

            if ($ext === 'svg') {
                $svgContent = file_get_contents($file['tmp_name']);
                if ($svgContent !== false && preg_match('/<script|javascript:|onload|onerror|onclick|onmouseover|<iframe|<embed|<object|<foreignobject/i', $svgContent)) {
                    self::error('Berkas SVG ditolak: Terdeteksi skrip atau tag tidak aman.', 400);
                }
            } else {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $validMimes = ['image/png', 'image/jpeg', 'image/webp'];
                if (!in_array($mime, $validMimes, true)) {
                    self::error('MIME type gambar tidak valid atau berkas rusak.', 400);
                }
            }

            $targetDir = ROOT_DIR . '/uploads/icons';
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            $filename = 'icon_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $targetDir . '/' . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $appRootUrl = preg_replace('#/api/?$#i', '', BASE_URL);
                $url = $appRootUrl . '/uploads/icons/' . $filename;
                self::json(['success' => true, 'url' => $url, 'filename' => $filename]);
            }
        }
        self::error('Gagal mengunggah file ikon.', 400);
    }

    private static function handleGetCustomIcons(): void {
        $targetDir = ROOT_DIR . '/uploads/icons';
        $icons = [];
        $appRootUrl = preg_replace('#/api/?$#i', '', BASE_URL);

        if (is_dir($targetDir)) {
            $files = scandir($targetDir);
            foreach ($files as $f) {
                if ($f === '.' || $f === '..' || $f === 'index.php') continue;
                $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
                if (!in_array($ext, ['png', 'jpg', 'jpeg', 'svg', 'webp'], true)) continue;
                $filePath = $targetDir . '/' . $f;
                $icons[] = [
                    'name' => $f,
                    'filename' => $f,
                    'url' => $appRootUrl . '/uploads/icons/' . $f,
                    'time' => filemtime($filePath) ?: 0
                ];
            }
        }

        usort($icons, fn($a, $b) => ($b['time'] ?? 0) <=> ($a['time'] ?? 0));
        self::json([
            'success' => true,
            'icons' => $icons,
            'data' => $icons
        ]);
    }

    private static function handleDeleteCustomIcon(array $input): void {
        $filename = trim((string)($input['filename'] ?? ''));
        if (empty($filename) || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            self::error('Nama berkas ikon tidak valid.', 400);
        }
        $targetDir = ROOT_DIR . '/uploads/icons';
        $filePath = $targetDir . '/' . $filename;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE GisPoint SET customIconUrl = NULL, pointType = 'pin_umum' WHERE customIconUrl LIKE :pattern");
            $stmt->execute([':pattern' => '%' . $filename . '%']);
        } catch (Throwable) {}

        self::json(['success' => true, 'message' => 'Logo kustom berhasil dihapus dari galeri dan dilepas dari titik peta.']);
    }

    private static function handleDirections(array $input): void {
        $startLat = (float)($input['startLat'] ?? 0);
        $startLng = (float)($input['startLng'] ?? 0);
        $destLat = (float)($input['destLat'] ?? 0);
        $destLng = (float)($input['destLng'] ?? 0);
        $mode = (string)($input['mode'] ?? 'road');

        if (!$startLat || !$startLng || !$destLat || !$destLng) {
            self::error('Koordinat asal dan tujuan tidak valid', 400);
        }

        $points = [];
        $engine = 'Jalur Navigasi Standar';
        $distanceMeters = 0;
        $durationSeconds = 0;

        if (empty($points) && $mode === 'road') {
            $osrmUrl = "https://router.project-osrm.org/route/v1/driving/{$startLng},{$startLat};{$destLng},{$destLat}?overview=full&geometries=geojson";
            $ch = curl_init($osrmUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 7,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'QR-SAR-Kendari/2.5.0 (basarnas-kendari; contact: admin@basarnas.go.id)'
            ]);
            $osrmRes = curl_exec($ch);
            if (!empty($osrmRes)) {
                $osrmData = json_decode($osrmRes, true);
                if (!empty($osrmData['routes'][0]['geometry']['coordinates'])) {
                    $allCoords = $osrmData['routes'][0]['geometry']['coordinates'];
                    $totalPts = count($allCoords);
                    if ($totalPts > 250) {
                        $step = ($totalPts - 1) / 249;
                        for ($idx = 0; $idx < 250; $idx++) {
                            $coordIndex = (int)round($idx * $step);
                            if ($coordIndex >= $totalPts) $coordIndex = $totalPts - 1;
                            $c = $allCoords[$coordIndex];
                            $points[] = ['lat' => (float)$c[1], 'lng' => (float)$c[0]];
                        }
                    } else {
                        foreach ($allCoords as $c) {
                            $points[] = ['lat' => (float)$c[1], 'lng' => (float)$c[0]];
                        }
                    }
                    $distanceMeters = (float)($osrmData['routes'][0]['distance'] ?? 0);
                    $durationSeconds = (float)($osrmData['routes'][0]['duration'] ?? 0);
                    $engine = 'OSRM Server Live Routing';
                }
            }
        }

        self::json([
            'success' => true,
            'engine' => $engine,
            'hasRoadApi' => !empty($points),
            'points' => $points,
            'distanceMeters' => $distanceMeters,
            'durationSeconds' => $durationSeconds
        ]);
    }

    private static function handleCalculateRoute(array $input): void {
        $startLat = (float)($input['startLat'] ?? GeoHelper::KANSAR_KENDARI_LAT);
        $startLng = (float)($input['startLng'] ?? GeoHelper::KANSAR_KENDARI_LNG);
        $destLat = (float)($input['destLat'] ?? 0);
        $destLng = (float)($input['destLng'] ?? 0);
        $speed = (float)($input['speedKnots'] ?? 20);

        $distNm = GeoHelper::calculateDistanceNm($startLat, $startLng, $destLat, $destLng);
        $distKm = GeoHelper::calculateDistanceKm($startLat, $startLng, $destLat, $destLng);
        $bearing = GeoHelper::calculateBearing($startLat, $startLng, $destLat, $destLng);

        $hours = $speed > 0 ? ($distNm / $speed) : 0;
        $hrs = floor($hours);
        $mins = round(($hours - $hrs) * 60);
        $etaText = "{$hrs} Jam {$mins} Menit";

        self::json([
            'success' => true,
            'distance_nm' => $distNm,
            'distance_km' => $distKm,
            'bearing' => $bearing,
            'eta_text' => $etaText,
            'dms' => GeoHelper::decimalToDms($destLat, $destLng)
        ]);
    }

    private static function handleSnapshotsRoute(string $path, string $method, array $input): bool {
        if (($path === '/gis/snapshot' || $path === '/gis/snapshots') && $method === 'GET') {
            self::requireAuth();
            $idOperasi = isset($_GET['id_operasi']) ? (int)$_GET['id_operasi'] : 0;
            $jenisDokumen = isset($_GET['jenis_dokumen']) ? trim((string)$_GET['jenis_dokumen']) : '';
            $idReferensi = isset($_GET['id_referensi']) && $_GET['id_referensi'] !== '' ? (int)$_GET['id_referensi'] : null;

            if ($idOperasi <= 0) {
                self::json(['success' => true, 'data' => [], 'renops_list' => [], 'sitrep_list' => []]);
                return true;
            }

            if ($jenisDokumen !== '') {
                $snap = DokumenPetaSnapshotModel::getSnapshot($idOperasi, $jenisDokumen, $idReferensi);
                self::json(['success' => true, 'data' => $snap]);
                return true;
            }

            $all = DokumenPetaSnapshotModel::getAllByOperasi($idOperasi);
            // Strip heavy base64 data URI from bulk list for fast network transfer
            foreach ($all as &$item) {
                unset($item['image_data_uri']);
            }
            unset($item);

            $renopsList = [];
            foreach (RenopsModel::getByOperasiId($idOperasi) as $r) {
                $renopsList[] = [
                    'id_renops' => (int)$r['id_renops'],
                    'hari_ke' => $r['hari_ke'] ?? 'H.1',
                    'tanggal_laporan' => $r['tanggal_laporan'] ?? ''
                ];
            }

            $sitrepList = [];
            foreach (LaporanModel::getByOperasiId($idOperasi) as $l) {
                $sitrepList[] = [
                    'id_laporan' => (int)$l['id_laporan'],
                    'hari_ke' => $l['hari_ke'] ?? 'H.1',
                    'jenis_laporan' => $l['jenis_laporan'] ?? 'Harian',
                    'waktu_laporan' => $l['waktu_laporan'] ?? ''
                ];
            }

            self::json([
                'success' => true,
                'data' => [
                    'snapshots' => $all,
                    'renops_list' => $renopsList,
                    'sitrep_list' => $sitrepList
                ],
                'snapshots' => $all,
                'renops_list' => $renopsList,
                'sitrep_list' => $sitrepList
            ]);
            return true;
        }

        if (($path === '/gis/snapshot' || $path === '/gis/snapshots') && in_array($method, ['POST', 'PUT'], true)) {
            self::requireAuth();
            try {
                $saved = DokumenPetaSnapshotModel::saveSnapshot($input);
                unset($saved['image_data_uri']);
                self::json([
                    'success' => true,
                    'message' => 'Cuplikan peta 16:9 beserta keterangan komponen berhasil disimpan untuk dokumen cetak.',
                    'data' => $saved
                ]);
            } catch (InvalidArgumentException $e) {
                self::error($e->getMessage(), 422, 'VALIDATION_ERROR');
            } catch (Throwable $e) {
                self::error('Gagal menyimpan cuplikan peta: ' . $e->getMessage(), 500, 'SNAPSHOT_SAVE_FAILED');
            }
            return true;
        }

        if (preg_match('#^/gis/snapshot(?:s)?/(\d+)$#', $path, $m) && $method === 'DELETE') {
            self::requireAuth();
            $idSnap = (int)$m[1];
            $ok = DokumenPetaSnapshotModel::deleteSnapshot($idSnap);
            self::json(['success' => $ok]);
            return true;
        }

        if ($path === '/gis/tile-proxy' && $method === 'GET') {
            self::requireAuth();
            $tileUrl = trim((string)($_GET['url'] ?? ''));
            $parsed = parse_url($tileUrl);
            $host = strtolower((string)($parsed['host'] ?? ''));
            $allowedHosts = [
                'mt0.google.com', 'mt1.google.com', 'mt2.google.com', 'mt3.google.com',
                'tile.openstreetmap.org', 'a.tile.openstreetmap.org', 'b.tile.openstreetmap.org', 'c.tile.openstreetmap.org',
                'server.arcgisonline.com'
            ];
            if (!in_array($host, $allowedHosts, true) || !in_array(strtolower((string)($parsed['scheme'] ?? '')), ['http', 'https'], true)) {
                http_response_code(400);
                exit;
            }
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 4,
                    'header' => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) QR-SAR-GIS/2.5\r\n"
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ]);
            $imgBytes = @file_get_contents($tileUrl, false, $ctx);
            if ($imgBytes === false || strlen($imgBytes) < 32) {
                http_response_code(404);
                exit;
            }
            header('Content-Type: image/png');
            header('Cache-Control: public, max-age=86400');
            header('Access-Control-Allow-Origin: *');
            echo $imgBytes;
            exit;
        }

        return false;
    }
}
