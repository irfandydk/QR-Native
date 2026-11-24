<?php
/**
 * QR-SAR Automated TDD Suite - Pusat Dokumentasi Umum & Operasi SAR (/dokumentasi.php)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Menguji:
 * 1. Kolom OperasiDokumentasi.id_operasi nullable (mendukung Dokumentasi Umum tanpa Operasi SAR)
 * 2. Penyimpanan aset sumber (src_0_*) dan kolase Dokumentasi Umum (id_operasi = NULL) + project_json
 * 3. Arsip kronologis terpadu getAllWithOperasi() (Umum + Operasi SAR), filter scope/media/bulan/pencarian, dan statistik
 * 4. Edit ulang penuh (update) Dokumentasi Umum serta perpindahan kaitan NULL <-> Operasi SAR Aktif
 * 5. Isolasi penghapusan Operasi SAR: OperasiModel::delete() tidak menghapus Dokumentasi Umum
 * 6. RBAC Opsi 1: USER tidak dapat menghapus dokumentasi (AuthHelper::canDelete, API 403, dan guard tombol di View)
 * 7. Kontrak navigasi Sidebar, Router, API Router, DokumentasiController, Studio selector, & penamaan berkas kolase
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/app.php';

$passed = 0;
$failed = 0;

function assertTrueDokUmum(bool $cond, string $label): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo " [PASS] {$label}\n";
    } else {
        $failed++;
        echo " [FAIL] {$label}\n";
    }
}

function makeTempJpegDokUmum(int $w = 640, int $h = 480, array $rgb = [234, 88, 12]): string {
    $tmp = tempnam(sys_get_temp_dir(), 'qrsar_dok_umum_');
    $im = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    imagefilledrectangle($im, 0, 0, $w, $h, $bg);
    imagejpeg($im, $tmp, 90);
    unset($im);
    return $tmp;
}

echo "====================================================================\n";
echo "QR-SAR Test: Pusat Dokumentasi Umum & Operasi SAR (/dokumentasi.php)\n";
echo "====================================================================\n\n";

OperasiDokumentasiModel::ensureSchemaUpdated(true);
$db = getDB();

// 1. Cek kolom id_operasi mengizinkan NULL
$colInfo = $db->query("SHOW COLUMNS FROM OperasiDokumentasi LIKE 'id_operasi'")->fetch(PDO::FETCH_ASSOC);
assertTrueDokUmum(
    is_array($colInfo) && strtoupper((string)($colInfo['Null'] ?? '')) === 'YES',
    'Kolom OperasiDokumentasi.id_operasi mengizinkan nilai NULL untuk Dokumentasi Umum'
);

$tempFiles = [];
$cleanupRelPaths = [];
$generalDocId = 0;
$opsDocId = 0;
$testOpsId = 0;

try {
    // Buat 1 Operasi SAR Aktif sementara untuk pengujian lintas-mode
    $testOpsId = OperasiModel::create([
        'nama_operasi' => 'Operasi SAR Uji Arsip Dokumentasi Terpadu',
        'jenis_musibah' => 'Kecelakaan Kapal',
        'kategori_musibah' => 'Kecelakaan Kapal',
        'sub_jenis_musibah' => 'Mati Mesin',
        'lokasi_kejadian' => 'Perairan Pulau Bokori',
        'koord_derajat1' => '03°56\'10" S',
        'koord_derajat2' => '122°40\'15" E',
        'koord_desimal' => '-3.9361, 122.6708',
        'waktu_kejadian' => date('Y-m-d H:i:s', strtotime('-1 day')),
        'waktu_lapor' => date('Y-m-d H:i:s', strtotime('-20 hours')),
        'waktu_berangkat' => date('Y-m-d H:i:s', strtotime('-19 hours')),
        'pelapor' => 'Uji Otomatis',
        'info_awal' => 'Pengujian dokumentasi umum dan operasi aktif.',
        'status' => 'OPEN',
        'korban' => [],
        'detail_unsur' => [],
        'detail_alut' => [],
    ]);
    assertTrueDokUmum($testOpsId > 0, 'Operasi SAR aktif uji berhasil dibuat');

    // 2. Unggah aset sumber per-slot untuk Dokumentasi Umum (id_operasi = null -> src_0_...)
    $src1Tmp = makeTempJpegDokUmum(800, 600, [14, 165, 233]);
    $src2Tmp = makeTempJpegDokUmum(800, 600, [16, 185, 129]);
    $tempFiles[] = $src1Tmp;
    $tempFiles[] = $src2Tmp;

    $srcAsset1 = OperasiDokumentasiModel::storeSourceAsset(null, [
        'tmp_name' => $src1Tmp,
        'size' => (int)filesize($src1Tmp),
        'name' => 'latihan_1.jpg',
    ]);
    $srcAsset2 = OperasiDokumentasiModel::storeSourceAsset(0, [
        'tmp_name' => $src2Tmp,
        'size' => (int)filesize($src2Tmp),
        'name' => 'latihan_2.jpg',
    ]);
    $cleanupRelPaths[] = $srcAsset1['path'];
    $cleanupRelPaths[] = $srcAsset2['path'];

    assertTrueDokUmum(
        str_starts_with($srcAsset1['path'], 'uploads/operasi_dokumentasi/src_0_') && is_file(ROOT_DIR . '/' . $srcAsset1['path']),
        'storeSourceAsset(null) menyimpan berkas sumber dengan prefix src_0_ di disk'
    );

    // 3. Simpan Kolase Dokumentasi Umum (id_operasi = null) lengkap dengan project_json
    $renderUmumTmp = makeTempJpegDokUmum(1080, 1620, [249, 115, 22]);
    $tempFiles[] = $renderUmumTmp;
    $projectUmumRaw = json_encode([
        'version' => 1,
        'styleId' => 'editorial_modern',
        'orientation' => 'vertical',
        'canvasPresetId' => 'portrait_2_3',
        'qualityId' => 'qhd',
        'imageFormat' => 'image/jpeg',
        'slotCount' => 4,
        'layoutId' => 'v_4_2x2',
        'slots' => [
            ['type' => 'image', 'path' => $srcAsset1['path'], 'name' => 'latihan_1.jpg', 'zoom' => 1.15, 'offsetX' => 10, 'offsetY' => -5],
            ['type' => 'image', 'path' => $srcAsset2['path'], 'name' => 'latihan_2.jpg', 'zoom' => 1.0, 'offsetX' => 0, 'offsetY' => 0],
            null,
            null,
        ],
    ]);

    $savedUmum = OperasiDokumentasiModel::store(null, [
        'judul' => 'Dokumentasi Latihan SAR Gabungan KPP Kendari Unik991',
        'hari_ke' => 'LATIHAN',
        'tanggal_label' => 'Selasa, 24 November 2026',
        'aspect_ratio' => '2:3',
        'orientation' => 'vertical',
        'slot_count' => 4,
        'layout_preset' => 'editorial_modern:v_4_2x2',
        'width_px' => 1080,
        'height_px' => 1620,
        'project_json' => $projectUmumRaw,
    ], [
        'tmp_name' => $renderUmumTmp,
        'size' => (int)filesize($renderUmumTmp),
        'name' => 'DOK_KEGIATAN_LATIHAN.jpg',
    ], null, 'admin');

    $generalDocId = (int)($savedUmum['id_dokumentasi'] ?? 0);
    $cleanupRelPaths[] = (string)($savedUmum['file_path'] ?? '');

    assertTrueDokUmum(
        $generalDocId > 0 && $savedUmum['id_operasi'] === null && str_starts_with((string)$savedUmum['file_path'], 'uploads/operasi_dokumentasi/dok_0_'),
        'OperasiDokumentasiModel::store(null) berhasil menyimpan Dokumentasi Umum dengan id_operasi NULL'
    );

    $decodedUmum = OperasiDokumentasiModel::decodeProjectForClient($savedUmum['project_json'] ?? null, 'http://127.0.0.1:8000');
    assertTrueDokUmum(
        is_array($decodedUmum) && ($decodedUmum['slots'][0]['path'] ?? '') === $srcAsset1['path'],
        'project_json Dokumentasi Umum mempertahankan referensi aset sumber src_0_* untuk mode Edit'
    );

    // 4. Simpan Kolase Dokumentasi Operasi SAR Aktif
    $renderOpsTmp = makeTempJpegDokUmum(1620, 1080, [15, 23, 42]);
    $tempFiles[] = $renderOpsTmp;
    $savedOps = OperasiDokumentasiModel::store($testOpsId, [
        'judul' => 'Dok Operasi SAR H1 Perairan Pulau Bokori Unik991',
        'hari_ke' => 'H1',
        'tanggal_label' => 'Selasa, 24 November 2026',
        'aspect_ratio' => '3:2',
        'orientation' => 'horizontal',
        'slot_count' => 6,
        'layout_preset' => 'klasik_kpp:h_6_3x2',
        'width_px' => 1620,
        'height_px' => 1080,
    ], [
        'tmp_name' => $renderOpsTmp,
        'size' => (int)filesize($renderOpsTmp),
        'name' => 'DOK_SAR_H1_BOKORI.jpg',
    ], null, 'admin');
    $opsDocId = (int)($savedOps['id_dokumentasi'] ?? 0);
    $cleanupRelPaths[] = (string)($savedOps['file_path'] ?? '');

    assertTrueDokUmum(
        $opsDocId > 0 && (int)$savedOps['id_operasi'] === $testOpsId,
        'OperasiDokumentasiModel::store($testOpsId) tetap menyimpan Dokumentasi Operasi SAR dengan benar'
    );

    // 5. Uji getAllWithOperasi() & Filter Arsip Terpadu
    $allFiltered = OperasiDokumentasiModel::getAllWithOperasi(['q' => 'Unik991']);
    $foundIds = array_map(static fn(array $r): int => (int)$r['id_dokumentasi'], $allFiltered);
    assertTrueDokUmum(
        in_array($generalDocId, $foundIds, true) && in_array($opsDocId, $foundIds, true),
        'getAllWithOperasi() mengembalikan gabungan Dokumentasi Umum dan Dokumentasi Operasi SAR'
    );

    $onlyUmum = OperasiDokumentasiModel::getAllWithOperasi(['scope' => 'umum', 'q' => 'Unik991']);
    assertTrueDokUmum(
        count($onlyUmum) === 1 && (int)$onlyUmum[0]['id_dokumentasi'] === $generalDocId && $onlyUmum[0]['id_operasi'] === null,
        'getAllWithOperasi(scope=umum) hanya mengembalikan Dokumentasi Umum'
    );

    $onlyOps = OperasiDokumentasiModel::getAllWithOperasi(['scope' => 'operasi', 'q' => 'Unik991']);
    assertTrueDokUmum(
        count($onlyOps) === 1
        && (int)$onlyOps[0]['id_dokumentasi'] === $opsDocId
        && (string)($onlyOps[0]['nama_operasi'] ?? '') === 'Operasi SAR Uji Arsip Dokumentasi Terpadu',
        'getAllWithOperasi(scope=operasi) menyertakan nama_operasi dari tabel Operasi'
    );

    $byMonth = OperasiDokumentasiModel::getAllWithOperasi(['month' => date('Y-m'), 'media_type' => 'image', 'q' => 'Unik991']);
    assertTrueDokUmum(count($byMonth) === 2, 'getAllWithOperasi() mendukung filter bulan (YYYY-MM) dan media_type');

    $stats = OperasiDokumentasiModel::getArchiveStats();
    $months = OperasiDokumentasiModel::getAvailableMonths();
    assertTrueDokUmum(
        ($stats['total'] ?? 0) >= 2 && ($stats['umum'] ?? 0) >= 1 && ($stats['operasi'] ?? 0) >= 1 && in_array(date('Y-m'), $months, true),
        'getArchiveStats() dan getAvailableMonths() mengembalikan metrik arsip kronologis yang akurat'
    );

    // 6. Uji Edit Ulang & Perpindahan Kaitan: Umum (NULL) -> Operasi Aktif ($testOpsId) -> Umum (NULL)
    $renderUpdate1 = makeTempJpegDokUmum(1080, 1620, [37, 99, 235]);
    $tempFiles[] = $renderUpdate1;
    $reassignedToOps = OperasiDokumentasiModel::update($generalDocId, $testOpsId, [
        'judul' => 'Dok Operasi SAR H1 Dialihkan dari Studio Umum Unik991',
        'hari_ke' => 'H1',
        'tanggal_label' => 'Selasa, 24 November 2026',
        'aspect_ratio' => '2:3',
        'orientation' => 'vertical',
        'slot_count' => 4,
        'layout_preset' => 'editorial_modern:v_4_2x2',
        'project_json' => $projectUmumRaw,
    ], [
        'tmp_name' => $renderUpdate1,
        'size' => (int)filesize($renderUpdate1),
    ], null, 'admin');
    $cleanupRelPaths[] = (string)($reassignedToOps['file_path'] ?? '');

    assertTrueDokUmum(
        (int)($reassignedToOps['id_operasi'] ?? 0) === $testOpsId && is_file(ROOT_DIR . '/' . $srcAsset1['path']),
        'update() mengizinkan pengaitan arsip ke Operasi SAR Aktif sambil mempertahankan aset sumber src_0_*'
    );

    // Kembalikan ke Dokumentasi Umum (NULL) sekaligus melepas srcAsset2 (uji pembersihan yatim)
    $renderUpdate2 = makeTempJpegDokUmum(1080, 1620, [5, 150, 105]);
    $tempFiles[] = $renderUpdate2;
    $projectKeepOneOnly = json_encode([
        'version' => 1,
        'styleId' => 'editorial_modern',
        'orientation' => 'vertical',
        'canvasPresetId' => 'portrait_2_3',
        'qualityId' => 'qhd',
        'slotCount' => 4,
        'layoutId' => 'v_4_2x2',
        'slots' => [
            ['type' => 'image', 'path' => $srcAsset1['path'], 'name' => 'latihan_1.jpg', 'zoom' => 1.2, 'offsetX' => 0, 'offsetY' => 0],
            null,
            null,
            null,
        ],
    ]);
    $reassignedBackUmum = OperasiDokumentasiModel::update($generalDocId, null, [
        'judul' => 'Dokumentasi Kegiatan Apel Siaga KPP Kendari Unik991',
        'hari_ke' => 'KEGIATAN',
        'tanggal_label' => 'Selasa, 24 November 2026',
        'aspect_ratio' => '2:3',
        'orientation' => 'vertical',
        'slot_count' => 4,
        'layout_preset' => 'editorial_modern:v_4_2x2',
        'project_json' => $projectKeepOneOnly,
    ], [
        'tmp_name' => $renderUpdate2,
        'size' => (int)filesize($renderUpdate2),
    ], null, 'admin');
    $cleanupRelPaths[] = (string)($reassignedBackUmum['file_path'] ?? '');

    assertTrueDokUmum(
        $reassignedBackUmum['id_operasi'] === null
        && is_file(ROOT_DIR . '/' . $srcAsset1['path'])
        && !is_file(ROOT_DIR . '/' . $srcAsset2['path']),
        'update() kembali ke Dokumentasi Umum (NULL) berhasil, mempertahankan aset aktif dan membersihkan aset yatim'
    );

    // 7. Isolasi Penghapusan Operasi SAR: Hapus $testOpsId tidak boleh menyentuh Dokumentasi Umum!
    $opsFileBeforeDelete = ROOT_DIR . '/' . ltrim((string)$savedOps['file_path'], '/');
    $umumFileBeforeDelete = ROOT_DIR . '/' . ltrim((string)$reassignedBackUmum['file_path'], '/');
    OperasiModel::delete($testOpsId);
    $testOpsId = 0;

    $checkUmumAfterOpsDel = OperasiDokumentasiModel::getById($generalDocId);
    $checkOpsAfterOpsDel = OperasiDokumentasiModel::getById($opsDocId);
    assertTrueDokUmum(
        $checkOpsAfterOpsDel === null && !is_file($opsFileBeforeDelete),
        'Menghapus Operasi SAR membersihkan arsip dokumentasi milik operasi tersebut'
    );
    assertTrueDokUmum(
        is_array($checkUmumAfterOpsDel) && is_file($umumFileBeforeDelete) && is_file(ROOT_DIR . '/' . $srcAsset1['path']),
        'Menghapus Operasi SAR SAMA SEKALI TIDAK menghapus Dokumentasi Umum maupun berkas fisiknya'
    );

    // 8. Uji RBAC Opsi 1: USER tidak dapat menghapus dokumentasi, ADMIN dapat menghapus
    UserModel::ensureTableExists(true);
    UserModel::syncFromAppSettings(true);
    $userRow = $db->query("SELECT * FROM `Users` WHERE UPPER(role) = 'USER' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $adminRow = $db->query("SELECT * FROM `Users` WHERE UPPER(role) = 'ADMIN' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

    $origSession = $_SESSION;
    $_SESSION = [
        'user_id' => (string)($userRow['id'] ?? 'USR-USER'),
        'username' => (string)($userRow['username'] ?? 'user'),
        'role' => 'USER',
        'auth_at' => time(),
        'cred_fp' => hash('sha256', (string)($userRow['password_hash'] ?? '')),
    ];
    assertTrueDokUmum(
        AuthHelper::isLoggedIn() === true && AuthHelper::canDelete('dokumentasi') === false && AuthHelper::canDelete('gis') === true,
        'AuthHelper::canDelete("dokumentasi") mengembalikan FALSE untuk sesi login role USER yang sah'
    );

    $_SESSION = [
        'user_id' => (string)($adminRow['id'] ?? 'USR-ADMIN'),
        'username' => (string)($adminRow['username'] ?? 'admin'),
        'role' => 'ADMIN',
        'auth_at' => time(),
        'cred_fp' => hash('sha256', (string)($adminRow['password_hash'] ?? '')),
    ];
    assertTrueDokUmum(
        AuthHelper::isLoggedIn() === true && AuthHelper::canDelete('dokumentasi') === true,
        'AuthHelper::canDelete("dokumentasi") mengembalikan TRUE untuk sesi login role ADMIN yang sah'
    );
    $_SESSION = $origSession;

    // 9. Kontrak Keamanan & Arsitektur Kode (API, View, Sidebar, Router, Studio)
    $apiCtrlSrc = (string)@file_get_contents(ROOT_DIR . '/api/controllers/OperasiDokumentasiApiController.php');
    assertTrueDokUmum(
        str_contains($apiCtrlSrc, "AuthHelper::canDelete('dokumentasi')")
        && str_contains($apiCtrlSrc, '403')
        && str_contains($apiCtrlSrc, '#^/dokumentasi'),
        'OperasiDokumentasiApiController menegakkan AuthHelper::canDelete("dokumentasi") -> 403 dan rute /dokumentasi'
    );

    $apiIndexSrc = (string)@file_get_contents(ROOT_DIR . '/api/index.php');
    assertTrueDokUmum(
        str_contains($apiIndexSrc, "str_starts_with(\$path, '/dokumentasi')"),
        'api/index.php meneruskan prefix /dokumentasi ke OperasiDokumentasiApiController'
    );

    $tabDokSrc = (string)@file_get_contents(ROOT_DIR . '/views/operasi/partials/tab_dokumentasi.php');
    $archiveViewSrc = (string)@file_get_contents(ROOT_DIR . '/views/dokumentasi/index.php');
    assertTrueDokUmum(
        str_contains($tabDokSrc, "AuthHelper::canDelete('dokumentasi')")
        && str_contains($archiveViewSrc, "AuthHelper::canDelete('dokumentasi')"),
        'Tombol Hapus dikunci dengan AuthHelper::canDelete("dokumentasi") baik di halaman Dokumentasi maupun tab Detail Operasi'
    );

    $sidebarSrc = (string)@file_get_contents(ROOT_DIR . '/views/layout/sidebar.php');
    $routerSrc = (string)@file_get_contents(ROOT_DIR . '/helpers/Router.php');
    assertTrueDokUmum(
        str_contains($sidebarSrc, '/dokumentasi.php') && str_contains($sidebarSrc, "currentRoute === 'dokumentasi'"),
        'Sidebar memiliki menu navigasi Dokumentasi (/dokumentasi.php)'
    );
    assertTrueDokUmum(
        str_contains($routerSrc, "'/dokumentasi' => 'dokumentasi.php'")
        && str_contains($routerSrc, "'/dokumentasi/create' => 'dokumentasi.php'")
        && str_contains($routerSrc, "'/dokumentasi/edit' => 'dokumentasi.php'"),
        'Router mendefinisikan clean URL /dokumentasi, /dokumentasi/create, dan /dokumentasi/edit'
    );

    $studioViewSrc = (string)@file_get_contents(ROOT_DIR . '/views/operasi/dokumentasi_studio.php');
    $studioJsSrc = (string)@file_get_contents(ROOT_DIR . '/assets/js/pages/operasi_dokumentasi.js');
    assertTrueDokUmum(
        is_file(ROOT_DIR . '/dokumentasi.php')
        && is_file(ROOT_DIR . '/controllers/DokumentasiController.php')
        && str_contains($studioViewSrc, 'selectDokOperasi')
        && str_contains($studioJsSrc, 'selectDokOperasi'),
        'DokumentasiController dan dropdown pilihan Operasi SAR Aktif (selectDokOperasi) tersedia di Studio'
    );
} finally {
    if ($generalDocId > 0) {
        OperasiDokumentasiModel::delete($generalDocId);
    }
    if ($opsDocId > 0) {
        OperasiDokumentasiModel::delete($opsDocId);
    }
    if ($testOpsId > 0) {
        try {
            OperasiModel::delete($testOpsId);
        } catch (Throwable) {
        }
    }
    if (!empty($cleanupRelPaths)) {
        OperasiDokumentasiModel::deleteFiles($cleanupRelPaths);
    }
    foreach ($tempFiles as $tf) {
        if (is_file($tf)) {
            @unlink($tf);
        }
    }
}

echo "\n====================================================================\n";
echo "Hasil Akhir: {$passed} PASS, {$failed} FAIL\n";
echo "====================================================================\n";
exit($failed > 0 ? 1 : 0);
