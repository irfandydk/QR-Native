<?php
/**
 * Test: pembersihan dokumen cetak Laporan Awal, LAPBANG (SITREP) & Penutupan — tanpa file/kode mati — serta
 * aturan peta dokumen RENOPS: Peta Lokasi (LKP) 1x per operasi dipakai semua hari, Peta Rencana Operasi per hari.
 * Termasuk perapian data lama (snapshot & berkasnya, snapshot milik operasi terhapus, token QR lama) yang idempoten.
 * Tidak menyapu folder uploads: DB utama & DB test berbagi folder yang sama.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$testPassed = 0;
$testTotal = 0;

function assertCondition(bool $cond, string $message): void {
    global $testPassed, $testTotal;
    $testTotal++;
    if ($cond) {
        $testPassed++;
        echo " [PASS] {$message}\n";
    } else {
        echo " [FAIL] {$message}\n";
    }
}

$src = fn(string $rel): string => (string)@file_get_contents(ROOT_DIR . '/' . $rel);

echo "=== TEST PEMBERSIHAN DOKUMEN LAMA & PETA LKP RENOPS ===\n\n";

// 1. File mati terhapus
foreach (['views/operasi/laporan_awal_print.php', 'views/operasi/sitrep_print.php', 'views/operasi/penutupan_print.php',
    'views/operasi/partials/print_kop.php', 'views/operasi/partials/print_map_snapshot_block.php'] as $f) {
    assertCondition(!file_exists(ROOT_DIR . '/' . $f), "Terhapus: {$f}");
}

// 2. Kode mati terhapus
$mati = [
    'controllers/OperasiController.php' => ['print_laporan_awal', 'print_sitrep', 'print_penutupan', 'print_berita_acara', 'TYPE_SITREP', 'TYPE_PENUTUPAN'],
    'operasi.php' => ['print_laporan_awal', 'print_sitrep', 'print_penutupan'],
    'assets/js/pages/operasi_detail.js' => ['printBerkas', 'print_laporan_awal', 'print_sitrep', 'print_penutupan'],
    'assets/js/gis_master.js' => ["'LAPORAN_AWAL'", "'PENUTUPAN'", "'SITREP'", 'sitrep_list', 'cachedOperasiSitrepList', 'print_laporan_awal', 'print_penutupan'],
    'views/gis.php' => ['value="LAPORAN_AWAL"', 'value="PENUTUPAN"'],
    'views/operasi/partials/tab_gis.php' => ['capture_doc=LAPORAN_AWAL'],
    'api/controllers/GisApiController.php' => ['sitrep_list'],
    // 'LAPORAN_AWAL' tetap disebut di rapikanDataLama() untuk memigrasikan peta LKP lama.
    'models/DokumenPetaSnapshotModel.php' => ["'SITREP'", "'PENUTUPAN'"],
    'models/LaporanModel.php' => ['DokumenPetaSnapshotModel', 'nomor_laporan', 'TYPE_SITREP'],
    'models/RenopsModel.php' => ["deleteByReferensi('RENOPS',"],
    'services/RenopsDokumenService.php' => ["'LAPORAN_AWAL'"],
    'helpers/TemplateHelper.php' => ['generateNomorLaporanAwal', 'generateNomorSitrep', 'generateNomorBeritaAcara'],
    'services/DocumentNumberService.php' => ['generateNomorLaporanAwal', 'generateNomorSitrep', 'generateNomorBeritaAcara'],
    'helpers/DocVerifyHelper.php' => ['TYPE_LAPORAN_AWAL', 'TYPE_SITREP', 'TYPE_PENUTUPAN', 'function issueToken', 'function generateVerifyUrl', 'print_laporan_awal'],
    'views/operasi/verify_public.php' => ['Laporan Awal', 'SITREP', 'Penutupan', 'id_laporan'],
    'assets/css/tailwind.src.css' => ['.doc-title', '.doc-sub', '.doc-number', '.doc-section'],
    'assets/css/tailwind.css' => ['.doc-title', '.doc-section'],
    'assets/js/pdf_document_exporter.js' => ['Dokumen lain: satu lembar'],
    'README.md' => ['laporan_awal_print', 'sitrep_print', 'penutupan_print', 'print_map_snapshot_block'],
];
foreach ($mati as $f => $kata) {
    $isi = $src($f);
    $sisa = array_values(array_filter($kata, fn($k) => str_contains($isi, $k)));
    assertCondition($isi !== '' && $sisa === [], "{$f} bersih" . ($sisa ? ' (sisa: ' . implode(', ', $sisa) . ')' : ''));
}
assertCondition(DocVerifyHelper::normalizeType('renops') === 'renops' && DocVerifyHelper::normalizeType('laporan_awal') === null
    && DocVerifyHelper::normalizeType('sitrep') === null, 'Verifikasi QR lama: hanya RENOPS');

// 3. Peta Lokasi (LKP) 1x per operasi, Peta Rencana per hari
assertCondition(DokumenPetaSnapshotModel::ALLOWED_TYPES === ['RENOPS', 'RENOPS_RENCANA'], 'Jenis snapshot: RENOPS (LKP) & RENOPS_RENCANA');
$db = getDB();
$idOps = $idOps2 = $idOps3 = 0;
$refToken = random_int(900000, 999999);
try {
    $idOps = OperasiModel::create(['nama_operasi' => 'Uji LKP Sekali', 'nama_pelapor' => 'U', 'lokasi_kejadian' => 'L', 'waktu_lapor' => '2026-11-07 06:30:00']);
    $idR1 = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-11-07', 'srus' => []]);
    $idR2 = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-11-08', 'srus' => []]);
    ob_start();
    imagepng(imagecreatetruecolor(32, 18));
    $png = base64_encode((string)ob_get_clean());

    $tolak = function (array $data): bool {
        try {
            DokumenPetaSnapshotModel::saveSnapshot($data);
            return false;
        } catch (InvalidArgumentException) {
            return true;
        }
    };
    assertCondition($tolak(['id_operasi' => $idOps, 'jenis_dokumen' => 'LAPORAN_AWAL', 'image_data' => $png])
        && $tolak(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS_RENCANA', 'image_data' => $png]), 'Jenis tak dikenal & peta rencana tanpa hari ditolak');
    $lkp = DokumenPetaSnapshotModel::saveSnapshot(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS', 'id_referensi' => $idR2, 'image_data' => $png]);
    assertCondition($lkp['id_referensi'] === null, 'Peta LKP disimpan per operasi (id_referensi NULL)');
    $d1 = RenopsDokumenService::resolve($idR1);
    $d2 = RenopsDokumenService::resolve($idR2);
    assertCondition($d1['peta']['path'] === $lkp['image_path'] && $d2['peta']['path'] === $lkp['image_path'], 'Semua hari RENOPS memakai Peta LKP yang sama');
    $lkp2 = DokumenPetaSnapshotModel::saveSnapshot(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS', 'image_data' => $png]);
    assertCondition((int)$lkp2['id_snapshot'] === (int)$lkp['id_snapshot']
        && (int)$db->query("SELECT COUNT(*) FROM DokumenPetaSnapshot WHERE id_operasi = {$idOps} AND jenis_dokumen = 'RENOPS'")->fetchColumn() === 1, 'Capture ulang LKP memperbarui baris yang sama');

    ob_start();
    $doc = $d2;
    $mode = 'draft';
    include ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php';
    $html = (string)ob_get_clean();
    assertCondition(str_contains($html, 'capture_doc=RENOPS"') && !str_contains($html, 'capture_doc=RENOPS&amp;capture_ref'),
        'Tombol capture Peta LKP tanpa hari (1x per operasi)');
    $cetak = $src('views/operasi/partials/tab_cetak.php');
    assertCondition(substr_count($cetak, "capture_doc=RENOPS'") === 1 && !str_contains($cetak, 'capture_doc=RENOPS&capture_ref'), 'Tab Cetak: satu tombol Peta LKP per operasi');

    RenopsModel::delete($idR1);
    assertCondition((int)$db->query("SELECT COUNT(*) FROM DokumenPetaSnapshot WHERE id_operasi = {$idOps} AND jenis_dokumen = 'RENOPS'")->fetchColumn() === 1,
        'Hapus RENOPS H.1 tidak menghapus Peta LKP operasi');

    // 4. Perapian data lama (idempoten)
    $dir = DokumenPetaSnapshotModel::ensureUploadDir();
    $buat = function (string $jenis, ?int $ref) use ($db, &$idOps, $dir): string {
        $nama = 'uji_rapi_' . strtolower($jenis) . '_' . bin2hex(random_bytes(4)) . '.png';
        copy(ROOT_DIR . '/' . ltrim($GLOBALS['lkpPath'], '/'), $dir . '/' . $nama);
        $db->prepare('INSERT INTO DokumenPetaSnapshot (id_operasi, jenis_dokumen, id_referensi, image_path) VALUES (?, ?, ?, ?)')
            ->execute([$idOps, $jenis, $ref, 'uploads/map_snapshots/' . $nama]);
        return $dir . '/' . $nama;
    };
    $GLOBALS['lkpPath'] = $lkp2['image_path'];
    $idOps2 = OperasiModel::create(['nama_operasi' => 'Uji Rapi Lama', 'nama_pelapor' => 'U', 'lokasi_kejadian' => 'L', 'waktu_lapor' => '2026-11-07 06:30:00']);
    $idOpsAsli = $idOps;
    $idOps = $idOps2;
    $fLa = $buat('LAPORAN_AWAL', null);
    $fH1 = $buat('RENOPS', 501);
    $fH2 = $buat('RENOPS', 502);
    $fSit = $buat('SITREP', 7);
    $fTut = $buat('PENUTUPAN', null);
    $idOps3 = OperasiModel::create(['nama_operasi' => 'Uji Rapi LA Saja', 'nama_pelapor' => 'U', 'lokasi_kejadian' => 'L', 'waktu_lapor' => '2026-11-07 06:30:00']);
    $idOps = $idOps3;
    $fLaSaja = $buat('LAPORAN_AWAL', null);
    $idOps = 2147480000; // operasi yang sudah tidak ada
    $fTanpaOps = $buat('RENOPS', 9);
    $idOps = $idOpsAsli;
    $lain = $dir . '/uji_rapi_lain_' . bin2hex(random_bytes(4)) . '.png'; // berkas milik DB lain: tidak boleh disentuh
    copy(ROOT_DIR . '/' . $lkp2['image_path'], $lain);
    $db->prepare("INSERT INTO DokumenToken (doc_type, ref_id, id_operasi, token, issued_at) VALUES ('sitrep', ?, ?, 'ABCDEF0123456789', NOW())")->execute([$refToken, $idOps2]);

    $r = DokumenPetaSnapshotModel::rapikanDataLama($db);
    $baris = fn(int $op) => $db->query("SELECT jenis_dokumen, id_referensi, image_path FROM DokumenPetaSnapshot WHERE id_operasi = {$op}")->fetchAll(PDO::FETCH_ASSOC);
    $b2 = $baris($idOps2);
    $b3 = $baris($idOps3);
    assertCondition(count($b2) === 1 && $b2[0]['jenis_dokumen'] === 'RENOPS' && $b2[0]['id_referensi'] === null && str_ends_with($fH1, basename($b2[0]['image_path']))
        && !is_file($fH2) && !is_file($fLa) && !is_file($fSit) && !is_file($fTut) && is_file($fH1), 'Data lama: LKP hari pertama jadi LKP operasi, sisanya & berkasnya dihapus');
    assertCondition(count($b3) === 1 && $b3[0]['jenis_dokumen'] === 'RENOPS' && $b3[0]['id_referensi'] === null && is_file($fLaSaja), 'Data lama: hanya Laporan Awal → dipakai sebagai LKP operasi');
    assertCondition(!is_file($fTanpaOps) && (int)$db->query('SELECT COUNT(*) FROM DokumenPetaSnapshot WHERE id_operasi = 2147480000')->fetchColumn() === 0,
        'Snapshot milik operasi yang sudah terhapus ikut dibersihkan');
    assertCondition(is_file($lain) && is_file(ROOT_DIR . '/' . $lkp2['image_path']) && is_file($dir . '/.htaccess'), 'Berkas tanpa baris (milik DB lain) & berkas terpakai tidak disentuh');
    assertCondition((int)$db->query("SELECT COUNT(*) FROM DokumenToken WHERE doc_type <> 'renops'")->fetchColumn() === 0, 'Token QR dokumen lama dihapus');
    assertCondition(($r['snapshot'] ?? 0) >= 5 && ($r['lkp'] ?? 0) >= 2 && ($r['token'] ?? 0) >= 1, 'Ringkasan jumlah perapian');
    $r2 = DokumenPetaSnapshotModel::rapikanDataLama($db);
    assertCondition($r2 === ['snapshot' => 0, 'lkp' => 0, 'token' => 0], 'Perapian idempoten');
} catch (Throwable $e) {
    assertCondition(false, 'Tanpa exception: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    foreach ([$idOps, $idOps2, $idOps3] as $op) {
        if ($op) {
            OperasiModel::delete($op);
        }
    }
    $db->prepare("DELETE FROM DokumenToken WHERE doc_type = 'sitrep' AND ref_id = ?")->execute([$refToken]);
    foreach (glob(ROOT_DIR . '/uploads/map_snapshots/uji_rapi_*') ?: [] as $f) {
        @unlink($f);
    }
}
$mig = $src('database/migrate.php');
assertCondition(str_contains($mig, 'DokumenPetaSnapshotModel::rapikanDataLama('), 'migrate.php menjalankan perapian data lama');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
