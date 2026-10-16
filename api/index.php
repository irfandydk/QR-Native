<?php
/**
 * QR-SAR - Unified REST API Controller
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/app.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
// Secure Origin Matching (Strict in production, relaxed only in local dev)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$appHost = parse_url(BASE_URL, PHP_URL_HOST) ?: 'localhost';
if ($origin) {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $isDev = (defined('APP_ENV') && APP_ENV === 'development') || in_array($appHost, ['localhost', '127.0.0.1'], true);
    $allowLocal = $isDev && in_array($originHost, ['localhost', '127.0.0.1'], true);

    if ($originHost === $appHost || $allowLocal) {
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Credentials: true');
    }
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Determine path
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?? '/';

// Strip script directory and /api prefix
$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
if ($scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
    $path = substr($path, strlen($scriptDir));
}
$path = preg_replace('#^/api#', '', $path);
$path = '/' . trim($path, '/');

// Support explicit path parameter: e.g. /api/index.php?path=/ai/refine-sitrep
if (!empty($_GET['path'])) {
    $path = '/' . ltrim((string)$_GET['path'], '/');
} elseif ($path === '/index.php' && !empty($_SERVER['QUERY_STRING']) && preg_match('#^/([a-zA-Z0-9_\-/]+)#', $_SERVER['QUERY_STRING'], $qm)) {
    $path = '/' . ltrim($qm[1], '/');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$rawInput = file_get_contents('php://input');
$input = !empty($rawInput) ? json_decode($rawInput, true) : [];
if (!is_array($input)) $input = $_POST;

// Security Middleware Callbacks for API endpoints
$requireAuth = function(): void {
    if (!AuthHelper::isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Akses ditolak: Sesi login diperlukan.']);
        exit;
    }
};

$requireAdmin = function(): void {
    if (!AuthHelper::isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Akses ditolak: Hanya Administrator yang diizinkan melakukan tindakan ini.']);
        exit;
    }
};

// CSRF Guard Middleware for State-Changing Requests (POST, PUT, DELETE)
$requireCsrf = function() use ($input): void {
    if (AuthHelper::isLoggedIn()) {
        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ($_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? null)));
        if (!validateCsrf($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Validasi CSRF gagal: Token keamanan tidak valid atau sesi Anda telah kedaluwarsa.'
            ]);
            exit;
        }
    }
};

if (in_array($method, ['POST', 'PUT', 'DELETE'], true) && $path !== '/auth/login') {
    $requireCsrf();
}

$checkAiRateLimit = function(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $now = time();
    $history = $_SESSION['ai_req_history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }
    $history = array_filter($history, fn($t) => is_numeric($t) && ($now - (int)$t) < 60);
    if (count($history) >= 15) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Batas kuota panggilan AI terlampaui (Maksimal 15 permintaan per menit). Mohon tunggu sejenak.'
        ]);
        exit;
    }
    $history[] = $now;
    $_SESSION['ai_req_history'] = array_values($history);
};

try {
    // Health check
    if ($path === '/' || $path === '/health') {
        echo json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s'), 'version' => APP_VERSION]);
        exit;
    }

    // Auth
    if ($path === '/auth/login' && $method === 'POST') {
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        $result = AuthHelper::login($username, $password);
        if (!$result['success']) {
            http_response_code(401);
        }
        echo json_encode($result);
        exit;
    }

    if ($path === '/auth/profile' && $method === 'GET') {
        echo json_encode(['success' => true, 'data' => AuthHelper::getCurrentUser()]);
        exit;
    }

    if ($path === '/auth/ping' && $method === 'GET') {
        echo json_encode([
            'success' => true,
            'logged_in' => AuthHelper::isLoggedIn(),
            'user' => AuthHelper::isLoggedIn() ? (AuthHelper::getCurrentUser()['username'] ?? 'user') : null,
            'time' => time()
        ]);
        exit;
    }

    if ($path === '/auth/change-password' && $method === 'POST') {
        $requireAuth();
        $currentUser = AuthHelper::getCurrentUser();
        $role = $currentUser['role'] ?? 'USER';
        $old = $input['oldPassword'] ?? '';
        $new = $input['newPassword'] ?? '';
        $res = SettingsModel::changePassword($role, $old, $new);
        if (!$res['success']) http_response_code(400);
        echo json_encode($res);
        exit;
    }

    if ($path === '/auth/reset-default' && $method === 'POST') {
        $requireAdmin();
        SettingsModel::resetToDefaultPassword();
        echo json_encode(['success' => true, 'message' => 'Kata sandi berhasil direset ke standar sistem (Kendari304).']);
        exit;
    }

    // Stats
    if ($path === '/laporan/stats' || $path === '/monitoring/stats') {
        $requireAuth();
        echo json_encode(['success' => true, 'data' => OperasiModel::getStats()]);
        exit;
    }

    // Comprehensive Global & Indonesian Geocoding (Google Maps Experience)
    if ($path === '/geocode') {
        $q = trim($_GET['q'] ?? '');
        if (strlen($q) < 2) {
            echo json_encode(['success' => true, 'data' => []]);
            exit;
        }

        $results = [];

        // Helper: Convert decimal to DMS
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

        // 1. Check if raw coordinates entered (Decimal or DMS)
        if (preg_match('/^\s*([+-]?\d+(?:\.\d+)?)[,\s]+([+-]?\d+(?:\.\d+)?)\s*$/', $q, $m)) {
            $lat = (float)$m[1];
            $lng = (float)$m[2];
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                echo json_encode([
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
                exit;
            }
        }

        // DMS parsing: e.g. 04° 03' 27" S, 121° 35' 21" E
        if (preg_match('#(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["]*\s*([NSEWnsew])[,\s]+(\d+)[°\s]+(\d+)[\'\s]+(\d+(?:\.\d+)?)["]*\s*([NSEWnsew])#u', $q, $dmsM)) {
            $latDeg = (float)$dmsM[1]; $latMin = (float)$dmsM[2]; $latSec = (float)$dmsM[3]; $latDir = strtoupper($dmsM[4]);
            $lngDeg = (float)$dmsM[5]; $lngMin = (float)$dmsM[6]; $lngSec = (float)$dmsM[7]; $lngDir = strtoupper($dmsM[8]);

            $lat = $latDeg + ($latMin / 60) + ($latSec / 3600);
            if ($latDir === 'S') $lat = -$lat;
            $lng = $lngDeg + ($lngMin / 60) + ($lngSec / 3600);
            if ($lngDir === 'W') $lng = -$lng;

            echo json_encode([
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
            exit;
        }

        // 2. Query MySQL GisPoint for saved SAR plotting points
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

        // 3. Query ArcGIS World Geocoding (Outstanding Indonesian & global coverage: Kota, Desa, Kecamatan, Pelabuhan, Gunung, dll)
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

                        // Deduplicate
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

        // 4. Query Photon OSM API if fewer than 5 results (Covers villages, hamlets, islands, mountains)
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

                            // Deduplicate
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

        echo json_encode(['success' => true, 'query' => $q, 'count' => count($results), 'data' => array_slice($results, 0, 10)]);
        exit;
    }

    // AI Endpoints (Protected: Requires Authenticated Operator & Rate Limited)
    if ($path === '/ai/refine-kronologi' && $method === 'POST') {
        $requireAuth();
        $checkAiRateLimit();
        $raw = $input['rawDraft'] ?? ($input['draftInput'] ?? '');
        echo json_encode(AiHelper::refineRawDraft($raw));
        exit;
    }

    if ($path === '/ai/draft-kronologi' && $method === 'POST') {
        $requireAuth();
        $checkAiRateLimit();
        $mode = $input['mode'] ?? 'quick_draft';
        if ($mode === 'refine_raw') {
            $raw = $input['rawDraft'] ?? ($input['draftInput'] ?? '');
            echo json_encode(AiHelper::refineRawDraft($raw));
            exit;
        }

        $draft = $input['draftInput'] ?? '';
        $nama = $input['namaOperasi'] ?? '';
        $lokasi = $input['lokasi'] ?? '';
        $waktu = $input['waktuKejadian'] ?? '';
        $pelapor = $input['pelapor'] ?? '';
        $waktuLapor = $input['waktuLapor'] ?? '';
        $korbanInfo = $input['korbanInfo'] ?? '';
        echo json_encode(AiHelper::draftKronologi($draft, $nama, $lokasi, $waktu, [
            'pelapor' => $pelapor,
            'waktuLapor' => $waktuLapor,
            'korbanInfo' => $korbanInfo,
        ]));
        exit;
    }

    if ($path === '/ai/refine-sitrep' && $method === 'POST') {
        $requireAuth();
        $checkAiRateLimit();
        $raw = $input['rawDraft'] ?? ($input['catatan'] ?? ($input['rawText'] ?? ($input['draftInput'] ?? ($input['isi_laporan'] ?? ''))));
        $hariKe = $input['hariKe'] ?? ($input['hari_ke'] ?? 'H.1');
        $nama = $input['namaOperasi'] ?? ($input['nama_operasi'] ?? '');
        echo json_encode(AiHelper::refineSitrepRawDraft($raw, $hariKe, $nama, [
            'unsur' => $input['unsur'] ?? '',
            'alut' => $input['alut'] ?? '',
            'hasil' => $input['hasil'] ?? '',
            'kendala' => $input['kendala'] ?? ''
        ]));
        exit;
    }

    if ($path === '/ai/draft-sitrep' && $method === 'POST') {
        $requireAuth();
        $checkAiRateLimit();
        $mode = $input['mode'] ?? 'quick_draft';
        $hariKe = $input['hariKe'] ?? ($input['hari_ke'] ?? 'H.1');
        $nama = $input['namaOperasi'] ?? ($input['nama_operasi'] ?? '');
        $unsur = $input['unsur'] ?? '';
        $alut = $input['alut'] ?? '';
        $hasil = $input['hasil'] ?? 'NIHIL';
        $kendala = $input['kendala'] ?? 'Nihil';

        if ($mode === 'refine_raw') {
            $raw = $input['rawDraft'] ?? ($input['catatan'] ?? ($input['rawText'] ?? ($input['draftInput'] ?? ($input['tindakanHariIni'] ?? ''))));
            echo json_encode(AiHelper::refineSitrepRawDraft($raw, $hariKe, $nama, [
                'unsur' => $unsur,
                'alut' => $alut,
                'hasil' => $hasil,
                'kendala' => $kendala
            ]));
            exit;
        }

        $tindakan = $input['tindakanHariIni'] ?? ($input['draftInput'] ?? ($input['catatan'] ?? ''));
        echo json_encode(AiHelper::draftSitrep($hariKe, $tindakan, $hasil, $kendala, $nama, [
            'unsur' => $unsur,
            'alut' => $alut
        ]));
        exit;
    }

    // Operasi CRUD
    if ($path === '/operasi' && $method === 'GET') {
        $requireAuth();
        $status = $_GET['status'] ?? null;
        $q = $_GET['q'] ?? null;
        echo json_encode(['success' => true, 'data' => OperasiModel::getAll($status, $q)]);
        exit;
    }

    if ($path === '/operasi' && $method === 'POST') {
        $requireAuth();
        $newId = OperasiModel::create($input);
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Laporan Awal Operasi SAR berhasil disimpan.']);
        exit;
    }

    if (preg_match('#^/operasi/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        if ($method === 'GET') {
            $requireAuth();
            $ops = OperasiModel::getById($id);
            if (!$ops) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Operasi tidak ditemukan']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $ops]);
            exit;
        }

        if ($method === 'PUT' || $method === 'POST') {
            $requireAuth();
            $ok = OperasiModel::update($id, $input);
            echo json_encode(['success' => $ok, 'message' => 'Data Operasi SAR berhasil diperbarui.']);
            exit;
        }

        if ($method === 'DELETE') {
            $requireAuth();
            if (!AuthHelper::canDelete()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Akses ditolak: Hanya administrator yang dapat menghapus data operasi.']);
                exit;
            }
            $ok = OperasiModel::delete($id);
            echo json_encode(['success' => $ok, 'message' => 'Operasi SAR berhasil dihapus.']);
            exit;
        }
    }

    if (preg_match('#^/operasi/(\d+)/close$#', $path, $m) && $method === 'POST') {
        $requireAuth();
        $id = (int)$m[1];
        $waktu = $input['waktu_selesai'] ?? date('Y-m-d H:i:s');
        $ok = OperasiModel::closeOperation($id, $waktu);
        echo json_encode(['success' => $ok, 'message' => 'Operasi SAR resmi ditutup.']);
        exit;
    }

    if (preg_match('#^/operasi/(\d+)/rollback-penutupan$#', $path, $m) && $method === 'POST') {
        $requireAuth();
        $id = (int)$m[1];
        $waktuReopen = $input['waktu_reopen'] ?? $input['waktu'] ?? date('Y-m-d H:i:s');
        $ok = OperasiModel::rollbackPenutupan($id, $waktuReopen);
        echo json_encode([
            'success' => $ok, 
            'message' => 'Status Operasi SAR berhasil dibuka kembali (OPEN) dan perhitungan hari operasional dimulai lagi.',
            'waktu_reopen' => $waktuReopen
        ]);
        exit;
    }

    // Renops
    if (preg_match('#^/operasi/(\d+)/renops$#', $path, $m) && $method === 'POST') {
        $requireAuth();
        $idOps = (int)$m[1];
        $ops = OperasiModel::getById($idOps);
        if (!$ops) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operasi SAR tidak ditemukan.']);
            exit;
        }
        if ($ops['status'] === 'CLOSED') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Operasi SAR telah berstatus DITUTUP (CLOSED). Rencana Operasi (RENOPS) baru tidak dapat ditambahkan kecuali operasi dibuka kembali (Rollback).'
            ]);
            exit;
        }
        $input['id_operasi'] = $idOps;
        try {
            $newId = RenopsModel::create($input);
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Rencana Operasi SAR berhasil ditambahkan.']);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if (preg_match('#^/renops/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        $existingRenops = RenopsModel::getById($id);
        if ($existingRenops) {
            $parentOps = OperasiModel::getById((int)$existingRenops['id_operasi']);
            if ($parentOps && $parentOps['status'] === 'CLOSED') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Operasi SAR telah berstatus DITUTUP (CLOSED). Data RENOPS terkunci dan tidak dapat diubah/dihapus kecuali status operasi dibuka kembali.'
                ]);
                exit;
            }
        }
        if ($method === 'PUT' || $method === 'POST') {
            $requireAuth();
            $ok = RenopsModel::update($id, $input);
            echo json_encode(['success' => $ok, 'message' => 'Rencana Operasi berhasil diperbarui.']);
            exit;
        }
        if ($method === 'DELETE') {
            $requireAuth();
            if (!AuthHelper::canDelete()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Akses ditolak.']);
                exit;
            }
            $ok = RenopsModel::delete($id);
            echo json_encode(['success' => $ok, 'message' => 'Rencana Operasi berhasil dihapus.']);
            exit;
        }
    }

    // Sitrep / Laporan Harian
    if (preg_match('#^/operasi/(\d+)/laporan$#', $path, $m) && $method === 'POST') {
        $requireAuth();
        $idOps = (int)$m[1];
        $ops = OperasiModel::getById($idOps);
        if (!$ops) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Operasi SAR tidak ditemukan.']);
            exit;
        }
        if ($ops['status'] === 'CLOSED') {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan baru tidak dapat ditambahkan kecuali operasi dibuka kembali (Rollback).'
            ]);
            exit;
        }
        $input['id_operasi'] = $idOps;
        try {
            $newId = LaporanModel::create($input);
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Laporan Perkembangan berhasil ditambahkan.']);
        } catch (\Throwable $e) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if (preg_match('#^/laporan/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        $existingLap = LaporanModel::getById($id);
        if ($existingLap) {
            $parentOps = OperasiModel::getById((int)$existingLap['id_operasi']);
            if ($parentOps && $parentOps['status'] === 'CLOSED') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Operasi SAR telah berstatus DITUTUP (CLOSED). Laporan Perkembangan terkunci dan tidak dapat diubah/dihapus kecuali status operasi dibuka kembali.'
                ]);
                exit;
            }
        }
        if ($method === 'PUT' || $method === 'POST') {
            $requireAuth();
            $ok = LaporanModel::update($id, $input);
            echo json_encode(['success' => $ok, 'message' => 'Laporan Perkembangan berhasil diperbarui.']);
            exit;
        }
        if ($method === 'DELETE') {
            $requireAuth();
            if (!AuthHelper::canDelete()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Akses ditolak.']);
                exit;
            }
            $ok = LaporanModel::delete($id);
            echo json_encode(['success' => $ok, 'message' => 'Laporan berhasil dihapus.']);
            exit;
        }
    }

    // BRA SAR
    if ($path === '/bra' && $method === 'GET') {
        $requireAuth();
        $kat = $_GET['kategori'] ?? null;
        $q = $_GET['q'] ?? null;
        echo json_encode(['success' => true, 'data' => BraModel::getAll($kat, $q)]);
        exit;
    }

    if ($path === '/bra/next-number' && $method === 'GET') {
        $requireAuth();
        $kat = $_GET['kategori'] ?? 'MAPEL';
        $satker = $_GET['satker'] ?? DEFAULT_SATKER_KODE;
        echo json_encode(['success' => true, 'data' => BraHelper::getNextNomorBra($kat, $satker)]);
        exit;
    }

    if ($path === '/bra/vessels' && $method === 'GET') {
        $requireAuth();
        echo json_encode(['success' => true, 'data' => BraHelper::getAvailableVessels()]);
        exit;
    }

    if ($path === '/bra/vessel-template' && $method === 'GET') {
        $requireAuth();
        $vessel = $_GET['vessel'] ?? 'KN_SAR_PACITAN';
        $movement = $_GET['movement'] ?? 'TOLAK';
        echo json_encode(['success' => true, 'data' => BraHelper::buildSectionsFromVesselMovement($vessel, $movement)]);
        exit;
    }

    if ($path === '/bra' && $method === 'POST') {
        $requireAuth();
        $newId = BraModel::create($input);
        echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Berita Radio SAR (BRA) berhasil disimpan.']);
        exit;
    }

    if (preg_match('#^/bra/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        if ($method === 'GET') {
            $requireAuth();
            $bra = BraModel::getById($id);
            if (!$bra) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Dokumen BRA tidak ditemukan']);
                exit;
            }
            echo json_encode(['success' => true, 'data' => $bra]);
            exit;
        }
        if ($method === 'PUT' || $method === 'POST') {
            $requireAuth();
            $ok = BraModel::update($id, $input);
            echo json_encode(['success' => $ok, 'message' => 'Dokumen BRA SAR berhasil diperbarui.']);
            exit;
        }
        if ($method === 'DELETE') {
            $requireAuth();
            if (!AuthHelper::canDelete()) {
                http_response_code(403);
                echo json_encode(['success' => false, 'error' => 'Akses ditolak.']);
                exit;
            }
            $ok = BraModel::delete($id);
            echo json_encode(['success' => $ok, 'message' => 'Dokumen BRA SAR berhasil dihapus.']);
            exit;
        }
    }

    // Master Data
    if ($path === '/master/alut') {
        if ($method === 'GET') {
            $requireAuth();
            echo json_encode(['success' => true, 'data' => MasterModel::getAlut()]);
            exit;
        }
        if ($method === 'POST') {
            $requireAdmin();
            $id = MasterModel::createAlut($input['nama_alut'] ?? '', $input['satuan'] ?? 'Unit');
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
    }

    if (preg_match('#^/master/alut/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        if ($method === 'PUT' || $method === 'POST') {
            $requireAdmin();
            $ok = MasterModel::updateAlut($id, $input['nama_alut'] ?? '', $input['satuan'] ?? 'Unit');
            echo json_encode(['success' => $ok]);
            exit;
        }
        if ($method === 'DELETE') {
            $requireAdmin();
            $ok = MasterModel::deleteAlut($id);
            echo json_encode(['success' => $ok]);
            exit;
        }
    }

    if ($path === '/master/tim') {
        if ($method === 'GET') {
            $requireAuth();
            echo json_encode(['success' => true, 'data' => MasterModel::getTim()]);
            exit;
        }
        if ($method === 'POST') {
            $requireAdmin();
            $id = MasterModel::createTim($input['nama_tim'] ?? '');
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
    }

    if (preg_match('#^/master/tim/(\d+)$#', $path, $m)) {
        $id = (int)$m[1];
        if ($method === 'PUT' || $method === 'POST') {
            $requireAdmin();
            $ok = MasterModel::updateTim($id, $input['nama_tim'] ?? '');
            echo json_encode(['success' => $ok]);
            exit;
        }
        if ($method === 'DELETE') {
            $requireAdmin();
            $ok = MasterModel::deleteTim($id);
            echo json_encode(['success' => $ok]);
            exit;
        }
    }

    if ($path === '/sar-units') {
        if ($method === 'GET') {
            $requireAuth();
            echo json_encode(['success' => true, 'data' => MasterModel::getSarUnits()]);
            exit;
        }
        if ($method === 'POST') {
            $requireAdmin();
            $id = MasterModel::createSarUnit($input);
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
    }

    // Settings & Pejabat
    if ($path === '/settings' && $method === 'GET') {
        $settings = SettingsModel::getSettings();
        if (AuthHelper::isAdmin()) {
            // Strip password hashes even for admin API output
            unset($settings['admin_password'], $settings['user_password']);
            echo json_encode(['success' => true, 'data' => $settings]);
        } else {
            // Publik / Non-Admin: Hanya metadata publik kantor SAR
            $publicData = [
                'satker_kode' => $settings['satker_kode'] ?? 'SAR-304',
                'satker_nama' => $settings['satker_nama'] ?? 'Kantor Pencarian dan Pertolongan Kelas A Kendari',
                'alamat' => $settings['alamat'] ?? '',
                'no_telp' => $settings['no_telp'] ?? '',
                'email' => $settings['email'] ?? '',
                'call_sign' => $settings['call_sign'] ?? '',
                'frekuensi_default' => $settings['frekuensi_default'] ?? '',
                'default_theme' => $settings['default_theme'] ?? 'dark',
                'logo_url' => $settings['logo_url'] ?? '',
                'pejabat_list' => $settings['pejabat_list'] ?? []
            ];
            echo json_encode(['success' => true, 'data' => $publicData]);
        }
        exit;
    }

    if ($path === '/settings' && ($method === 'PUT' || $method === 'POST')) {
        $requireAdmin();
        $ok = SettingsModel::updateSettings($input);
        echo json_encode(['success' => $ok, 'message' => 'Pengaturan satker berhasil disimpan.']);
        exit;
    }

    if ($path === '/settings/pejabat') {
        if ($method === 'GET') {
            $requireAuth();
            echo json_encode(['success' => true, 'data' => MasterModel::getPejabat()]);
            exit;
        }
        if ($method === 'POST') {
            $requireAdmin();
            $id = MasterModel::createPejabat($input);
            echo json_encode(['success' => true, 'id' => $id]);
            exit;
        }
    }

    if (preg_match('#^/settings/pejabat/(.+)$#', $path, $m)) {
        $id = $m[1];
        if ($method === 'PUT' || $method === 'POST') {
            $requireAdmin();
            $ok = MasterModel::updatePejabat($id, $input);
            echo json_encode(['success' => $ok]);
            exit;
        }
        if ($method === 'DELETE') {
            $requireAdmin();
            $ok = MasterModel::deletePejabat($id);
            echo json_encode(['success' => $ok]);
            exit;
        }
    }

    // GIS endpoints
    if (($path === '/gis/data' || $path === '/gis/bundle') && $method === 'GET') {
        $requireAuth();
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

        echo json_encode([
            'success' => true,
            'data' => $bundle,
            'id_operasi' => $idOperasi,
            'include_base_map' => $includeGlobal,
            'sar_units' => MasterModel::getSarUnits(),
            'points' => $points,
            'polygons' => $polygons,
            'routes' => $routes,
            'search_patterns' => $searchPatterns,
            'searchPatterns' => $searchPatterns,
            'operasi' => OperasiModel::getAll('OPEN')
        ]);
        exit;
    }

    if (($path === '/gis/point' || $path === '/gis/points') && $method === 'GET') {
        $requireAuth();
        $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
        $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
            ? true
            : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
        echo json_encode(['success' => true, 'data' => GisModel::getPoints($idOperasi, $includeGlobal)]);
        exit;
    }

    if (($path === '/gis/point' || $path === '/gis/points') && in_array($method, ['POST', 'PUT'])) {
        $requireAuth();
        $id = GisModel::savePoint($input);
        echo json_encode(['success' => true, 'id' => $id, 'data' => $input]);
        exit;
    }

    if (preg_match('#^/gis/point(?:s)?/(.+)$#', $path, $m) && in_array($method, ['POST', 'PUT'])) {
        $requireAuth();
        $input['id'] = $m[1];
        $id = GisModel::savePoint($input);
        echo json_encode(['success' => true, 'id' => $id, 'data' => $input]);
        exit;
    }

    if (preg_match('#^/gis/point(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
        $requireAuth();
        $ok = GisModel::deletePoint($m[1]);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($path === '/gis/upload-icon' && $method === 'POST') {
        $requireAuth();
        if (!empty($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['icon'];

            // 1. Validasi Batas Ukuran Berkas (Maksimal 2MB)
            if ($file['size'] > 2 * 1024 * 1024) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Ukuran file ikon terlalu besar (Maksimal 2 MB).']);
                exit;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            if (!in_array($ext, $allowed, true)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Format file tidak didukung (Gunakan PNG, JPG, JPEG, SVG, WebP).']);
                exit;
            }

            // 2. Validasi Keamanan Khusus SVG terhadap XSS
            if ($ext === 'svg') {
                $svgContent = file_get_contents($file['tmp_name']);
                if ($svgContent !== false && preg_match('/<script|javascript:|onload|onerror|onclick|onmouseover|<iframe|<embed|<object|<foreignobject/i', $svgContent)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Berkas SVG ditolak: Terdeteksi skrip atau tag tidak aman.']);
                    exit;
                }
            } else {
                // 3. Validasi MIME Type Riil untuk Gambar Raster
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                $validMimes = ['image/png', 'image/jpeg', 'image/webp'];
                if (!in_array($mime, $validMimes, true)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'MIME type gambar tidak valid atau berkas rusak.']);
                    exit;
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
                echo json_encode(['success' => true, 'url' => $url, 'filename' => $filename]);
                exit;
            }
        }
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file ikon.']);
        exit;
    }

    if ($path === '/gis/custom-icons' && $method === 'GET') {
        $requireAuth();
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
        echo json_encode([
            'success' => true,
            'icons' => $icons,
            'data' => $icons
        ]);
        exit;
    }

    if ($path === '/gis/custom-icons' && $method === 'DELETE') {
        $requireAuth();
        $filename = trim((string)($input['filename'] ?? ''));
        if (empty($filename) || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Nama berkas ikon tidak valid.']);
            exit;
        }
        $targetDir = ROOT_DIR . '/uploads/icons';
        $filePath = $targetDir . '/' . $filename;
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Cleanly detach this deleted icon from all GisPoint rows so it is unlinked from map points
        try {
            $db = getDB();
            $stmt = $db->prepare("UPDATE GisPoint SET customIconUrl = NULL, pointType = 'pin_umum' WHERE customIconUrl LIKE :pattern");
            $stmt->execute([':pattern' => '%' . $filename . '%']);
        } catch (Throwable) {}

        echo json_encode(['success' => true, 'message' => 'Logo kustom berhasil dihapus dari galeri dan dilepas dari titik peta.']);
        exit;
    }

    if (($path === '/gis/polygon' || $path === '/gis/polygons') && $method === 'GET') {
        $requireAuth();
        $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
        $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
            ? true
            : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
        echo json_encode(['success' => true, 'data' => GisModel::getPolygons($idOperasi, $includeGlobal)]);
        exit;
    }

    if (($path === '/gis/polygon' || $path === '/gis/polygons') && in_array($method, ['POST', 'PUT'])) {
        $requireAuth();
        $id = GisModel::savePolygon($input);
        echo json_encode(['success' => true, 'id' => $id, 'data' => $input]);
        exit;
    }

    if (preg_match('#^/gis/polygon(?:s)?/(.+)$#', $path, $m) && in_array($method, ['POST', 'PUT'])) {
        $requireAuth();
        $input['id'] = $m[1];
        $id = GisModel::savePolygon($input);
        echo json_encode(['success' => true, 'id' => $id, 'data' => $input]);
        exit;
    }

    if (preg_match('#^/gis/polygon(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
        $requireAuth();
        $ok = GisModel::deletePolygon($m[1]);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if (($path === '/gis/pattern' || $path === '/gis/patterns' || $path === '/gis/search-patterns') && $method === 'GET') {
        $requireAuth();
        $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
        $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
            ? true
            : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
        echo json_encode(['success' => true, 'data' => GisModel::getSearchPatterns($idOperasi, $includeGlobal)]);
        exit;
    }

    if (($path === '/gis/pattern' || $path === '/gis/patterns' || $path === '/gis/search-patterns') && in_array($method, ['POST', 'PUT'])) {
        $requireAuth();
        $id = GisModel::saveSearchPattern($input);
        echo json_encode(['success' => true, 'id' => $id, 'data' => $input]);
        exit;
    }

    if (preg_match('#^/gis/(?:pattern|search-pattern)(?:s)?/(.+)$#', $path, $m) && in_array($method, ['POST', 'PUT'])) {
        $requireAuth();
        $input['id'] = $m[1];
        $id = GisModel::saveSearchPattern($input);
        echo json_encode(['success' => true, 'id' => $id, 'data' => $input]);
        exit;
    }

    if (preg_match('#^/gis/(?:pattern|search-pattern)(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
        $requireAuth();
        $ok = GisModel::deleteSearchPattern($m[1]);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if (($path === '/gis/route' || $path === '/gis/routes') && $method === 'GET') {
        $requireAuth();
        $idOperasi = isset($_GET['id_operasi']) && $_GET['id_operasi'] !== '' ? (int)$_GET['id_operasi'] : null;
        $includeGlobal = !isset($_GET['include_global']) && !isset($_GET['include_base_map'])
            ? true
            : (($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === '1' || ($_GET['include_global'] ?? $_GET['include_base_map'] ?? '1') === 'true');
        echo json_encode(['success' => true, 'data' => GisModel::getRoutes($idOperasi, $includeGlobal)]);
        exit;
    }

    if (($path === '/gis/route' || $path === '/gis/routes') && in_array($method, ['POST', 'PUT'])) {
        $requireAuth();
        $id = GisModel::saveRoute($input);
        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    if (preg_match('#^/gis/route(?:s)?/(.+)$#', $path, $m) && $method === 'DELETE') {
        $requireAuth();
        $ok = GisModel::deleteRoute($m[1]);
        echo json_encode(['success' => $ok]);
        exit;
    }

    if ($path === '/gis/directions' && $method === 'POST') {
        $requireAuth();
        $startLat = (float)($input['startLat'] ?? 0);
        $startLng = (float)($input['startLng'] ?? 0);
        $destLat = (float)($input['destLat'] ?? 0);
        $destLng = (float)($input['destLng'] ?? 0);
        $mode = (string)($input['mode'] ?? 'road');

        if (!$startLat || !$startLng || !$destLat || !$destLng) {
            echo json_encode(['success' => false, 'message' => 'Koordinat asal dan tujuan tidak valid']);
            exit;
        }

        $points = [];
        $engine = 'Jalur Navigasi Standar';
        $distanceMeters = 0;
        $durationSeconds = 0;

        // 4. Fallback OSRM if road mode and no custom API points
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

        echo json_encode([
            'success' => true,
            'engine' => $engine,
            'hasRoadApi' => !empty($points),
            'points' => $points,
            'distanceMeters' => $distanceMeters,
            'durationSeconds' => $durationSeconds
        ]);
        exit;
    }

    // Route calculation helper
    if ($path === '/maps/calculate-route' && $method === 'POST') {
        $requireAuth();
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

        echo json_encode([
            'success' => true,
            'distance_nm' => $distNm,
            'distance_km' => $distKm,
            'bearing' => $bearing,
            'eta_text' => $etaText,
            'dms' => GeoHelper::decimalToDms($destLat, $destLng)
        ]);
        exit;
    }

    // Export SQL Dump (Admin Only)
    if ($path === '/export/sql' && $method === 'GET') {
        $requireAdmin();
        $sql = SettingsModel::generateSQLDump();
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="db_sar_qr.sql"');
        echo $sql;
        exit;
    }

    // Backup JSON Export (Admin Only)
    if ($path === '/database/export' || $path === '/backup/export') {
        $requireAdmin();
        $backup = SettingsModel::exportJsonBackup();
        $date = date('Y-m-d');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="BACKUP_DATABASE_SAR_KENDARI_' . $date . '.json"');
        echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // 404
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => "Endpoint API tidak ditemukan: {$path}"]);
} catch (Throwable $e) {
    error_log('QR-SAR API Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Terjadi kesalahan sistem internal. Permintaan tidak dapat diproses saat ini.'
    ]);
}
