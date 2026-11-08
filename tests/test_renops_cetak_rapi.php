<?php
/**
 * Test: Dokumen RENOPS — peta Rencana Operasi (capture per hari, di bawah Peta Lokasi), cetak/unduh draf
 * sebelum QR terbit (pratinjau DRAF tanpa QR), QR bermodel BRA SAR, serta pembersihan kartu RENOPS/LAPBANG
 * dan tab Cetak (hanya Rencana Operasi SAR & BRA SAR).
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

function renderDoc(array $doc, string $mode, ?string $qrUrl = null, string $versiLabel = ''): string {
    ob_start();
    include ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php';
    return (string)ob_get_clean();
}

echo "=== TEST RENOPS: PETA RENCANA, CETAK DRAF, QR, PEMBERSIHAN ===\n\n";

$db = getDB();
$idOps = 0;
try {
    $idOps = OperasiModel::create([
        'nama_operasi' => 'Operasi Uji Cetak Rapi', 'nama_pelapor' => 'Uji', 'lokasi_kejadian' => 'Perairan Uji',
        'waktu_kejadian' => '2026-10-21 06:00:00', 'waktu_lapor' => '2026-10-21 06:30:00',
    ]);
    $idR = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-10-21', 'srus' => []]);
    $idR2 = RenopsModel::create(['id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-10-22', 'srus' => []]);
    ob_start();
    imagepng(imagecreatetruecolor(32, 18));
    $png = base64_encode((string)ob_get_clean());

    // 1. Jenis snapshot baru per hari, terpisah dari peta lokasi
    assertCondition(in_array('RENOPS_RENCANA', DokumenPetaSnapshotModel::ALLOWED_TYPES, true)
        && DokumenPetaSnapshotModel::normalizeJenisDokumen('renops_rencana') === 'RENOPS_RENCANA', 'Jenis snapshot RENOPS_RENCANA dikenal');
    $d0 = RenopsDokumenService::resolve($idR);
    assertCondition(($d0['peta_rencana'] ?? 'x') === ['path' => null], 'Tanpa capture: peta_rencana kosong');
    $snap = DokumenPetaSnapshotModel::saveSnapshot(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS_RENCANA', 'id_referensi' => $idR, 'image_data' => $png]);
    assertCondition((int)($snap['id_referensi'] ?? 0) === $idR, 'Snapshot rencana menyimpan id_renops');
    $d1 = RenopsDokumenService::resolve($idR);
    assertCondition(($d1['peta_rencana']['path'] ?? null) === $snap['image_path'] && $d1['peta']['path'] === null
        && $d1['meta']['source_hash'] !== $d0['meta']['source_hash'], 'resolve: peta_rencana terisi, peta lokasi tidak tercampur, hash berubah');
    assertCondition((RenopsDokumenService::resolve($idR2)['peta_rencana'] ?? 'x') === ['path' => null], 'Peta rencana per hari (H.2 tidak mewarisi H.1)');

    // 2. Render: Peta Rencana di bawah Peta Lokasi; tombol capture di draf
    $draftHtml = renderDoc($d0, 'draft');
    assertCondition(str_contains($draftHtml, 'id="btnCapturePetaRencana"') && str_contains($draftHtml, 'capture_doc=RENOPS_RENCANA&amp;capture_ref=' . $idR),
        'Draf: tombol capture peta rencana di tempat');
    $snapL = DokumenPetaSnapshotModel::saveSnapshot(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS', 'image_data' => $png]);
    $d2 = RenopsDokumenService::resolve($idR);
    $fin = renderDoc($d2, 'final', 'https://contoh/verify', 'v1');
    $pLok = strpos($fin, 'Peta Lokasi');
    $pRen = strpos($fin, 'Peta Rencana Operasi');
    assertCondition($pLok !== false && $pRen !== false && $pRen > $pLok && str_contains($fin, e(BASE_URL . '/' . $snap['image_path']))
        && $pRen < strpos($fin, 'Initial Action Stage'), 'Final: Peta Rencana Operasi tepat di bawah Peta Lokasi');
    assertCondition(!str_contains(renderDoc($d0, 'final', 'https://contoh/verify', 'v1'), 'Peta Rencana Operasi'), 'Final tanpa capture: bagian peta rencana tidak dicetak');

    // 3. Terbit: peta rencana ikut dibekukan
    $p = RenopsDokumenPublisher::publish($idR, null, 0);
    $v = RenopsDokumenModel::findByToken($p['token']);
    $beku = $v['konten']['peta_rencana']['path'] ?? '';
    assertCondition(str_starts_with($beku, 'uploads/renops_dokumen/') && is_file(ROOT_DIR . '/' . $beku)
        && in_array($beku, RenopsDokumenModel::collectFilePaths($idR), true), 'Terbit: peta rencana dibekukan & ikut dibersihkan');

    // 4. QR model BRA SAR; pratinjau draf tanpa QR
    assertCondition(str_contains($fin, 'class="qr-frame"') && str_contains($fin, 'SCAN UNTUK VERIFIKASI') && str_contains($fin, 'id="qrCanvas"'),
        'QR final: bingkai & keterangan seperti BRA SAR');
    $prev = renderDoc($d2, 'pratinjau');
    assertCondition(str_contains($prev, 'renops-watermark') && !str_contains($prev, 'id="qrCanvas"') && !str_contains($prev, 'data-sec=')
        && !str_contains($prev, 'btnCapturePeta') && str_contains($prev, 'Peta Rencana Operasi'), 'Pratinjau draf: watermark DRAF, tanpa QR & tanpa kontrol sunting');
    $printSrc = (string)file_get_contents(ROOT_DIR . '/views/operasi/renops_smeac_print.php');
    assertCondition(str_contains($printSrc, "'#0a0f1d'"), 'Warna QR sama dengan BRA SAR');

    // 5. Snapshot rencana ikut terhapus bersama RENOPS
    RenopsModel::delete($idR2);
    DokumenPetaSnapshotModel::saveSnapshot(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS_RENCANA', 'id_referensi' => $idR, 'image_data' => $png]);
    RenopsModel::delete($idR);
    assertCondition((int)$db->query("SELECT COUNT(*) FROM DokumenPetaSnapshot WHERE jenis_dokumen = 'RENOPS_RENCANA' AND id_referensi = {$idR}")->fetchColumn() === 0,
        'Hapus RENOPS: snapshot peta rencana ikut terhapus');
} catch (Throwable $e) {
    assertCondition(false, 'Tanpa exception: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    if ($idOps) {
        OperasiModel::delete($idOps);
    }
}

// 6. GIS capture mengenal sasaran peta rencana
$gisView = (string)file_get_contents(ROOT_DIR . '/views/gis.php');
$gisJs = (string)file_get_contents(ROOT_DIR . '/assets/js/gis_master.js');
assertCondition(str_contains($gisView, 'value="RENOPS_RENCANA"') && str_contains($gisJs, "'RENOPS_RENCANA'"), 'Studio capture: pilihan Peta Rencana Operasi (per hari)');

// 7. Editor: cetak draf selalu tersedia
$editor = (string)file_get_contents(ROOT_DIR . '/views/operasi/renops_dokumen.php');
$editorJs = (string)file_get_contents(ROOT_DIR . '/assets/js/pages/renops_dokumen.js');
assertCondition(str_contains($editor, 'id="btnCetakDraf"') && str_contains($editor, 'print_renops&amp;draf=1') && !str_contains($editor, 'Terbitkan dulu')
    && str_contains($editorJs, '#btnCetakDraf'), 'Editor: tombol Cetak Draf / PDF aktif sebelum terbit (simpan dulu)');

// 8. Kartu RENOPS & LAPBANG: hanya Naskah WA, Edit, BRA
$tabR = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/tab_renops.php');
$tabS = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/tab_sitrep.php');
foreach (['<span>Salin WA</span>', '<span>Edit Draf</span>', '<span>Cetak Berkas</span>', '<span>Unduh PDF</span>'] as $x) {
    assertCondition(!str_contains($tabR, $x), "Kartu RENOPS tanpa {$x}");
}
assertCondition(str_contains($tabR, '<span>Naskah WA</span>') && str_contains($tabR, '<span>Edit</span>') && str_contains($tabR, '<span>BRA Renops</span>'), 'Kartu RENOPS: Naskah WA, Edit, BRA Renops');
foreach (['<span>Salin WA</span>', '<span>Cetak LAPBANG</span>', '<span>Unduh PDF</span>', '<span>Cetak Berita Acara</span>'] as $x) {
    assertCondition(!str_contains($tabS, $x), "Kartu LAPBANG tanpa {$x}");
}
assertCondition(str_contains($tabS, '<span>Naskah WA</span>') && str_contains($tabS, '<span>Edit</span>') && str_contains($tabS, '<span>BRA LAPBANG</span>'), 'Kartu LAPBANG: Naskah WA, Edit, BRA LAPBANG');

// 9. Tab Cetak: hanya Rencana Operasi SAR & BRA SAR
$tabC = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/tab_cetak.php');
foreach (['print_laporan_awal', 'print_penutupan', 'print_sitrep', 'capture_doc=LAPORAN_AWAL', 'capture_doc=PENUTUPAN', 'select-sitrep-cetak'] as $x) {
    assertCondition(!str_contains($tabC, $x), "Tab Cetak tanpa {$x}");
}
assertCondition(str_contains($tabC, 'Rencana Operasi SAR') && str_contains($tabC, 'Berita Radio SAR') && str_contains($tabC, 'print_renops')
    && str_contains($tabC, 'capture_doc=RENOPS_RENCANA') && str_contains($tabC, 'bra.php?view='), 'Tab Cetak: Rencana Operasi SAR (lokasi + rencana) & BRA SAR');

echo "\nTotal: {$testTotal}, Lolos: {$testPassed}\n";
exit($testPassed === $testTotal ? 0 : 1);
