<?php
/**
 * Test: RenopsDokumenService::resolve (kontrak data dokumen RENOPS SMEAC).
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

echo "=== TEST RENOPS DOKUMEN RESOLVE ===\n\n";

$db = getDB();
$idOps = $idOpsKosong = 0;
$pejabatUji = null;
try {
    if (!PejabatModel::getDefaultSmc()) {
        $pejabatUji = 'uji-smc-' . bin2hex(random_bytes(4));
        $db->prepare("INSERT INTO Pejabat (id, nama, nip, pangkat, jabatan, is_default_smc) VALUES (?, 'Amiruddin A.S, S.Sos', '197001012000031001', 'Pembina IV/a', 'Kepala Kantor', 1)")
            ->execute([$pejabatUji]);
    }
    $defaultSmcNama = PejabatModel::getDefaultSmc()['nama'];
    $satker = trim((string)(SettingsModel::get()['satker_nama'] ?? '')) ?: 'Kantor Pencarian dan Pertolongan Kelas A Kendari';

    // Kategori Alut/Palsar: kategori item menang atas nama; tanpa kategori → resolver (master, lalu kata kunci utuh)
    $split = RenopsDokumenService::splitAlut([...array_map(fn($n) => ['nama_alut' => $n], ['Rescue Car', 'Carabiner', 'HT Motorola', 'Kapal Motor', 'Speedboat', 'Speed Boat', 'Helikopter', 'Drone']),
        ['nama_alut' => 'Rescue Car Cadangan', 'kategori' => 'PALSAR'], ['nama_alut' => 'Tandu', 'kategori' => 'ALUT_DARAT']]);
    assertCondition($split === ['sarana' => ['Rescue Car', 'Kapal Motor', 'Speedboat', 'Speed Boat', 'Helikopter', 'Drone', 'Tandu'], 'peralatan' => ['Carabiner', 'HT Motorola', 'Rescue Car Cadangan']],
        'Sarana = kategori ALUT_*, Peralatan = PALSAR (kategori menang atas nama)');
    assertCondition(!method_exists(RenopsDokumenService::class, 'grupAset'), 'Grup aset dari kategori (tebakan nama di service dihapus)');
    $unsur = [['nama_unsur' => 'Polres Buton', 'jumlah_personil' => 5], ['nama_unsur' => 'Polsek Sampabalo', 'jumlah_personil' => 3]];
    $alut = [
        ['id_alut' => 0, 'nama_alut' => 'Rescue Car', 'satuan' => 'Unit', 'jumlah_alut' => 2],
        ['id_alut' => 0, 'nama_alut' => 'Drone', 'satuan' => 'Unit', 'jumlah_alut' => 1],
    ];

    $idOps = OperasiModel::create([
        'nama_operasi' => '1 (Satu) Orang Hilang di Kebun Desa Uji',
        'nama_pelapor' => 'Bapak Syahrir',
        'hubungan_pelapor' => 'Kepala Kepolisian Sektor Sampabalo',
        'no_telp_pelapor' => '085240060223',
        'lokasi_kejadian' => 'Kebun Desa Manuru',
        'koordinat' => "5°24'33\" S - 123°4'19\" E",
        'jarak' => '75 km',
        'kronologi' => "Paragraf satu.\r\nParagraf dua.",
        'waktu_kejadian' => '2026-10-21 06:00:00',
        'waktu_lapor' => '2026-10-21 06:30:00',
        'waktu_berangkat' => '2026-10-21 07:00:00',
        'tim_berangkat' => 'Tim Rescue Pos SAR Bau-Bau',
        'jumlah_pob' => 1,
        'korban' => [['nama_korban' => 'Nurdin', 'jk' => 'L', 'umur' => '31', 'alamat' => 'Dusun Wakaroli', 'status_korban' => 'DP']],
        'detail_unsur' => $unsur,
        'detail_alut' => $alut,
    ]);
    $db->prepare("UPDATE Operasi SET jenis_kejadian = 'Kondisi Membahayakan Manusia' WHERE id_operasi = ?")->execute([$idOps]);
    $idR = RenopsModel::create([
        'id_operasi' => $idOps, 'hari_ke' => 'H.1', 'tanggal_laporan' => '2026-10-21',
        'waktu_mulai_ops' => '2026-10-21 20:10:00',
        'srus' => [['nama_sru' => 'Keluarga dan Warga Setempat', 'pola_pencarian' => 'Circle']],
        'detail_unsur' => $unsur, 'detail_alut' => $alut, // R2: alut H1 eksplisit
    ]);

    $doc = RenopsDokumenService::resolve($idR);
    $s = $doc['sections'];
    assertCondition(str_contains($s['awareness'], 'Bapak Syahrir') && str_contains($s['awareness'], 'Kondisi Membahayakan Manusia'), 'Awareness dirangkai dari Laporan Awal');
    assertCondition(str_contains($s['awareness'], 'Pada tanggal 21 Oktober 2026 pukul 06.30 WITA, ' . $satker) && str_contains($s['awareness'], 'Kebun Desa Manuru') && str_contains($s['awareness'], '75 km dari '), 'Awareness: waktu lapor, satker, lokasi, jarak');
    $rows = array_column($s['data_kejadian'], 'value', 'label');
    assertCondition(array_keys($rows) === ['Jenis Musibah','Lokasi Duga Kejadian','Waktu Kejadian','Penyebab','Objek yang Dicari','Upaya yang Telah Dilakukan','Jumlah Korban dalam Pencarian','Catatan'], 'Label Tabel 1 sesuai lampiran');
    assertCondition($rows['Penyebab'] === 'Hilang' && $rows['Objek yang Dicari'] === '1 (satu) orang' && str_contains($rows['Catatan'], 'Nurdin'), 'Penyebab/objek/identitas korban');
    assertCondition($rows['Waktu Kejadian'] === '21 Oktober 2026, pukul 06.00 WITA' && $rows['Jumlah Korban dalam Pencarian'] === '1 (satu) orang' && str_contains($rows['Catatan'], 'Laki-laki') && str_contains($rows['Catatan'], '31 tahun'), 'Tabel 1: waktu, jumlah DP, identitas');
    assertCondition($s['sarana'] === ['Rescue Car', 'Drone'] && $s['peralatan'] === [], 'Sarana vs Peralatan dari kategori (Drone = ALUT Udara)');
    assertCondition($s['aset'] === [['grup' => 'Darat', 'aset' => 'Rescue Car', 'jumlah' => '2 (dua) unit'], ['grup' => 'Udara', 'aset' => 'Drone', 'jumlah' => '1 (satu) unit']],
        'Tabel aset hanya Sarana, dikelompokkan per matra kategori, kolom Aset|Jumlah');
    assertCondition($s['sru'][0]['tugas'] === 'Pencarian, pertolongan, dan evakuasi' && $s['sru'][0]['unsur'] === 'Keluarga dan Warga Setempat', 'Tugas SRU default');
    assertCondition($s['struktur']['smc'] === $defaultSmcNama && $s['struktur']['sru'][0]['nama'] === 'Keluarga dan Warga Setempat', 'Struktur: SMC default + SRU otomatis');
    assertCondition($s['koordinasi_instansi'] === ['BCC', 'Polres Buton', 'Polsek Sampabalo'] && $s['upaya_sebelumnya'] === "{$satker} telah berkoordinasi dengan BCC, Polres Buton, Polsek Sampabalo, dan keluarga korban.", 'H1: koordinasi & upaya sebelumnya');
    assertCondition($s['waktu'] === 'Operasi SAR Hari ke-1 dilaksanakan pada tanggal 21 Oktober 2026, pukul 20.10 WITA sampai dengan selesai.', 'Waktu sesuai format lampiran');
    assertCondition($s['operation_stage'] === 'Pada tanggal 21 Oktober 2026 pukul 07.00 WITA, diberangkatkan 8 (delapan) orang personel Tim Rescue Pos SAR Bau-Bau.', 'Operation stage H1: jumlah personel terbilang');
    assertCondition($s['mission'] === 'Melaksanakan Operasi SAR pada Kondisi Membahayakan Manusia, yaitu 1 (Satu) Orang Hilang di Kebun Desa Uji.' && $s['kronologi'] === "Paragraf satu.\nParagraf dua.", 'Mission & kronologi');
    assertCondition(str_starts_with($s['area_pencarian'], 'Keluarga dan Warga Setempat melaksanakan pencarian dengan pola Circle'), 'Area pencarian dari SRU');
    $m = $doc['meta'];
    assertCondition($m['hari_num'] === 1 && $m['hari_label'] === '1' && $m['tanggal'] === '2026-10-21' && $m['closed'] === false && $m['kota'] === 'Kendari' && $m['nomor'] !== '' && (bool)preg_match('/^[0-9a-f]{64}$/', $m['source_hash']), 'Meta dokumen');
    assertCondition($doc['peta'] === ['path' => null] && $doc['foto'] === [] && $doc['pengesahan']['smc_nama'] === $defaultSmcNama && $doc['pengesahan']['tanggal'] === '2026-10-21', 'Peta/foto kosong, pengesahan SMC');
    assertCondition($doc['edited'] === [], 'Tanpa draf: semua Otomatis');
    assertCondition(RenopsDokumenService::SC_DEFAULT === 'MARSEKAL MADYA TNI Dr.Mohammad Syafii, S.I.P., M.M., CHRMP.'
        && $s['struktur']['sc'] === RenopsDokumenService::SC_DEFAULT, 'SC: bawaan Kepala Basarnas bila belum ada dokumen sebelumnya');

    RenopsDokumenModel::saveDraft($idR, $idOps, ['mission' => 'Misi sunting'], 0);
    $doc2 = RenopsDokumenService::resolve($idR);
    assertCondition($doc2['sections']['mission'] === 'Misi sunting' && $doc2['edited'] === ['mission'], 'Suntingan menimpa & tercatat di edited');
    assertCondition($doc2['meta']['source_hash'] !== $m['source_hash'], 'Suntingan mengubah source_hash');
    $rev = fn() => (int)RenopsDokumenModel::getDraft($idR)['rev'];

    // R9: struktur disunting (lengkap dengan daftar SRU lama), lalu SRU ditambah di RENOPS.
    $st = $doc2['sections']['struktur'];
    $st['osc'] = 'Haeruddin, S.H.';
    $st['sru'][0]['keterangan'] = 'Potensi SAR dan Masyarakat Setempat';
    RenopsDokumenModel::saveDraft($idR, $idOps, ['struktur' => $st], $rev());
    $db->prepare("INSERT INTO RenopsSRU (id_renops, nama_sru, koordinat_area, luas_area, pola_pencarian) VALUES (?, 'Rescuer Pos SAR Kolaka', '', '', 'Parallel Track Search')")->execute([$idR]);
    $d = RenopsDokumenService::resolve($idR);
    assertCondition($d['sections']['struktur']['osc'] === 'Haeruddin, S.H.' && $d['sections']['struktur']['sru'] === [
        ['nama' => 'Keluarga dan Warga Setempat', 'keterangan' => 'Potensi SAR dan Masyarakat Setempat'],
        ['nama' => 'Rescuer Pos SAR Kolaka', 'keterangan' => ''],
    ], 'Struktur disunting tetap mengikuti SRU hari N, keterangan suntingan terbawa');

    // Bentuk suntingan diperiksa; baris tabel hanya membawa kolom kontrak.
    RenopsDokumenModel::saveDraft($idR, $idOps, ['aset' => 'bukan daftar', 'sru' => [['unsur' => 'SRU Sunting', 'tugas' => 'Penyisiran', 'liar' => '<x>']]], $rev());
    $d = RenopsDokumenService::resolve($idR);
    assertCondition($d['sections']['aset'] === $s['aset'] && !in_array('aset', $d['edited'], true), 'Suntingan berbentuk salah diabaikan (aset string)');
    assertCondition($d['sections']['sru'] === [['unsur' => 'SRU Sunting', 'tugas' => 'Penyisiran', 'area' => '', 'pola' => '', 'track_spacing' => '']]
        && $d['sections']['struktur']['sru'] === [['nama' => 'SRU Sunting', 'keterangan' => '']] && $d['edited'] === ['mission', 'sru', 'struktur'], 'Baris SRU disaring ke kolom kontrak; struktur mengikuti tabel SRU final');
    RenopsDokumenModel::saveDraft($idR, $idOps, ['aset' => null, 'sru' => null], $rev());

    // Peta Lokasi (LKP): 1x per operasi, dipakai RENOPS hari ini.
    ob_start();
    $im = imagecreatetruecolor(32, 18);
    imagepng($im);
    $pngB64 = base64_encode((string)ob_get_clean());
    $snapR = DokumenPetaSnapshotModel::saveSnapshot(['id_operasi' => $idOps, 'jenis_dokumen' => 'RENOPS', 'image_data' => $pngB64]);
    $d = RenopsDokumenService::resolve($idR);
    assertCondition($d['peta'] === ['path' => $snapR['image_path']] && is_file(ROOT_DIR . '/' . $d['peta']['path']), 'Peta: snapshot LKP operasi');

    // Foto dokumentasi
    $tmpPng = tempnam(sys_get_temp_dir(), 'rdr');
    imagepng($im, $tmpPng);
    $hashSebelum = $d['meta']['source_hash'];
    $foto = RenopsFotoModel::store($idR, $tmpPng, (int)filesize($tmpPng), null, 'Pemberangkatan tim');
    @unlink($tmpPng);
    $d = RenopsDokumenService::resolve($idR);
    assertCondition($d['foto'] === [['id_foto' => (int)$foto['id_foto'], 'path' => $foto['path'], 'keterangan' => 'Pemberangkatan tim']] && $d['meta']['source_hash'] !== $hashSebelum, 'Foto masuk dokumen & mengubah source_hash');

    // HN: LAPBANG H1 menjadi sumber upaya sebelumnya & hasil hari sebelumnya.
    LaporanModel::create(['id_operasi' => $idOps, 'waktu_laporan' => '2026-10-21 21:00:00', 'isi_laporan' => 'Pencarian hari pertama belum menemukan korban.']);
    $idR2 = RenopsModel::create([
        'id_operasi' => $idOps, 'hari_ke' => 'H.2', 'tanggal_laporan' => '2026-10-22',
        'waktu_mulai_ops' => '2026-10-22 07:30:00', 'srus' => [],
    ]);
    $doc3 = RenopsDokumenService::resolve($idR2);
    $s3 = $doc3['sections'];
    assertCondition($s3['struktur']['osc'] === 'Haeruddin, S.H.' && $s3['struktur']['smc'] === $defaultSmcNama && $s3['struktur']['sru'] === [], 'Struktur H2 disalin dari draf H1, SRU dari hari N');
    assertCondition($doc3['meta']['hari_label'] === '2' && $s3['upaya_sebelumnya'] === 'Pencarian hari pertama belum menemukan korban.', 'H2: upaya sebelumnya dari LAPBANG pendahulu');
    assertCondition(str_starts_with($s3['operation_stage'], "Pencarian hari pertama belum menemukan korban.\n\n") && str_contains($s3['operation_stage'], 'Operasi SAR Hari ke-2 dilanjutkan dengan 8 (delapan) orang personel.'), 'H2: operation stage = hasil LAPBANG + pelaksanaan hari N');
    $r3 = array_column($s3['data_kejadian'], 'value', 'label');
    assertCondition($r3['Upaya yang Telah Dilakukan'] === 'Berkoordinasi dengan Basarnas Command Center (BCC), Polres Buton, dan Polsek Sampabalo', 'H2: Tabel 1 upaya tetap kalimat koordinasi');

    // TERBIT hari sebelumnya diutamakan atas DRAF-nya.
    $terbit = function (int $rid, string $osc, string $issuedAt) use ($db, $idOps) {
        $db->prepare("INSERT INTO RenopsDokumen (id_renops, id_operasi, versi, status, rev, konten_json, token, issued_at) VALUES (?, ?, 1, 'TERBIT', 0, ?, ?, ?)")
            ->execute([$rid, $idOps, json_encode(['sections' => ['struktur' => ['sc' => 'SC Uji', 'osc' => $osc]]]), strtoupper(bin2hex(random_bytes(8))), $issuedAt]);
    };
    $terbit($idR, 'OSC Terbit', '2001-01-01 08:00:00');
    assertCondition(RenopsDokumenService::resolve($idR2)['sections']['struktur']['osc'] === 'OSC Terbit', 'Struktur H2: TERBIT H1 diutamakan atas DRAF H1');

    // Review Focus 2: operasi tanpa korban, SRU, alut, maupun snapshot.
    $idOpsKosong = OperasiModel::create(['nama_operasi' => 'Operasi Uji Kosong']);
    $idRk = RenopsModel::create(['id_operasi' => $idOpsKosong, 'srus' => []]);
    // R11: hanya versi terbit yang diterbitkan sebelum RENOPS ini dibuat (09:00) yang dipakai.
    $db->prepare("UPDATE Renops SET created_at = '2001-01-01 09:00:00' WHERE id_renops = ?")->execute([$idRk]);
    $warnings = [];
    set_error_handler(function (int $no, string $msg, string $file, int $line) use (&$warnings) {
        $warnings[] = "{$msg} @ {$file}:{$line}";
        return true;
    });
    try {
        $dk = RenopsDokumenService::resolve($idRk);
    } finally {
        restore_error_handler();
    }
    assertCondition($warnings === [] && is_array($dk), 'Data kosong: tanpa error/warning' . ($warnings ? ' — ' . implode('; ', $warnings) : ''));
    $sk = $dk['sections'];
    assertCondition($dk['peta']['path'] === null && $sk['area_pencarian'] === 'Melaksanakan koordinasi bersama potensi SAR dan melaksanakan pencarian di sekitar LKP duga.', 'Data kosong: peta null, area pencarian default');
    $rk = array_column($sk['data_kejadian'], 'value', 'label');
    assertCondition($sk['aset'] === [] && $sk['sru'] === [] && $sk['sarana'] === [] && $rk['Objek yang Dicari'] === '-' && $rk['Catatan'] === '-' && $rk['Penyebab'] === '-', 'Data kosong: tabel kosong & "-"');
    assertCondition(!str_contains($sk['awareness'] . $sk['mission'] . $sk['operation_stage'], ' - '), 'Data kosong: kalimat tanpa sisipan "-"');
    assertCondition($sk['struktur']['osc'] === 'OSC Terbit' && $sk['struktur']['sc'] === 'SC Uji', 'H1 tanpa pendahulu: struktur dari versi terbit terakhir sebelum RENOPS dibuat (bukan DRAF, bukan terbitan sesudahnya)');
    $terbit($idR2, 'OSC Nanti', '2001-01-01 10:00:00'); // terbit di tempat lain SESUDAH RENOPS kosong dibuat
    $dk2 = RenopsDokumenService::resolve($idRk);
    assertCondition($dk2['sections']['struktur']['osc'] === 'OSC Terbit' && $dk2['meta']['source_hash'] === $dk['meta']['source_hash'], 'Terbitan sesudahnya tidak mengubah struktur/source_hash dokumen lama');

} finally {
    foreach ([$idOps, $idOpsKosong] as $id) {
        if ($id) OperasiModel::delete($id);
    }
    if ($pejabatUji) {
        $db->prepare("DELETE FROM Pejabat WHERE id = ?")->execute([$pejabatUji]);
    }
}

echo "\nHasil: {$testPassed}/{$testTotal} lulus\n";
exit($testPassed === $testTotal ? 0 : 1);
