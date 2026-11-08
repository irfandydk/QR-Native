<?php
/**
 * Test: renderer dokumen RENOPS SMEAC (partial renops_smeac_doc.php + halaman renops_smeac_print.php).
 * Memakai fixture Kontrak Data Dokumen (tanpa data DB), mode draft & final, escaping, penomoran, fixture kosong.
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

echo "=== TEST RENOPS SMEAC RENDER ===\n\n";

$partial = ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php';
$page = ROOT_DIR . '/views/operasi/renops_smeac_print.php';

/** Render partial; warning/notice PHP dianggap gagal (dilempar). */
$render = function (array $doc, string $mode, ?string $qrUrl = null, string $versiLabel = 'v1') use ($partial): string {
    set_error_handler(function (int $no, string $str, string $file, int $line): bool {
        throw new ErrorException($str, 0, $no, $file, $line);
    });
    ob_start();
    try {
        include $partial;
    } finally {
        $html = (string)ob_get_clean();
        restore_error_handler();
    }
    return $html;
};

$doc = [
    'meta' => [
        'id_renops' => 11, 'id_operasi' => 7, 'hari_num' => 1, 'hari_label' => '1', 'tanggal' => '2025-09-28',
        'nomor' => 'RENOPS/01/IX/SAR-304/2025', 'satker_nama' => 'Kantor Pencarian dan Pertolongan Kendari', 'kota' => 'Kendari',
        'jenis_kejadian' => 'Kondisi Membahayakan Manusia', 'nama_operasi' => '1 (Satu) Orang Hilang di Kebun Desa Manuru',
        'closed' => false, 'source_hash' => str_repeat('a', 64),
    ],
    'sections' => [
        'awareness' => 'Pada tanggal 28 September 2025 pukul 20.00 WITA, Kantor Pencarian dan Pertolongan Kendari menerima informasi.',
        'kronologi' => "Pada hari Kamis, 25 September 2025, sekitar pukul 16.00 WITA, Sdr. Nurdin berangkat.\n\nKeluarga dan warga setempat telah melakukan pencarian.",
        'jenis_operasi' => 'Kondisi Membahayakan Manusia (KMM)',
        'data_kejadian' => [
            ['label' => 'Jenis Musibah', 'value' => 'Kondisi Membahayakan Manusia'],
            ['label' => 'Catatan', 'value' => "Identitas Korban:\nNama: Nurdin\nUmur: 31 tahun"],
        ],
        'upaya_sebelumnya' => 'Kantor Pencarian dan Pertolongan Kendari telah berkoordinasi dengan BCC.',
        'koordinasi' => ['Menyampaikan laporan musibah kepada BCC.', 'Berkoordinasi dengan:'],
        'koordinasi_instansi' => ['BCC', 'Polres Buton', 'Polsek Sampabalo'],
        'persiapan_personel' => ['Menyiapkan rescuer yang sedang siaga;', 'Menyiapkan peralatan.'],
        'sarana' => ['Rescue Car.'],
        'peralatan' => ['Peralatan evakuasi;', 'Drone.'],
        'mission' => 'Melaksanakan Operasi SAR pada Kondisi Membahayakan Manusia.',
        'waktu' => 'Operasi SAR Hari ke-1 dilaksanakan pada tanggal 28 September 2025, pukul 20.10 WITA sampai dengan selesai.',
        'area_pencarian' => 'Melaksanakan pencarian di sekitar LKP duga dengan radius sekitar 500 meter.',
        'aset' => [['grup' => 'Darat', 'aset' => 'Rescue Car', 'jumlah' => '2 (dua) unit']],
        'sru' => [['unsur' => 'Keluarga dan Warga Setempat', 'tugas' => 'Pencarian, pertolongan, dan evakuasi', 'area' => 'LKP', 'pola' => 'Circle', 'track_spacing' => '-']],
        'rescue_plan' => 'Korban yang ditemukan dievakuasi ke puskesmas terdekat.',
        'briefing' => 'Briefing dan debriefing disampaikan secara langsung.',
        'operation_stage' => 'Pada tanggal 28 September 2025 pukul 20.10 WITA, diberangkatkan 8 (delapan) orang personel.',
        'administrasi' => ['Buku jurnal catatan pergerakan unsur;', 'Dokumen pendukung lainnya.'],
        'logistik' => ['Makanan darurat;', 'Kebutuhan logistik lainnya.'],
        'struktur' => [
            'sc' => "KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN\nMARSEKAL MADYA TNI MOHAMMAD SYAFII", 'smc' => 'Amiruddin A.S, S.Sos', 'osc' => 'Haeruddin, S.H.',
            'staf_operasi' => 'Syaiful Wijanarta, S.E.', 'staf_admin' => 'Irfandy Dwi Kusuma', 'staf_logistik' => 'Finshar Simuru',
            'staf_intelijen' => 'I Kadek Wiryawan, S.Sos.', 'staf_humas' => 'Wahyudi, A.Md', 'staf_komunikasi' => 'Nurhayati',
            'sru' => [['nama' => 'Keluarga dan Warga Setempat', 'keterangan' => 'Potensi SAR dan Masyarakat Setempat']],
        ],
    ],
    'edited' => ['mission'],
    'peta' => ['path' => 'uploads/peta_snapshot/peta_uji.png'],
    'foto' => [
        ['id_foto' => 3, 'path' => 'uploads/renops_foto/foto_a.jpg', 'keterangan' => 'Dokumentasi Persiapan dan Pemberangkatan Tim Rescue'],
        ['id_foto' => 4, 'path' => 'uploads/renops_foto/foto_b.jpg', 'keterangan' => ''],
    ],
    'kontak' => ['telp' => '(0401) 3196557', 'email' => 'sar304kendari@gmail.com', 'alamat' => 'Jl. Uji'],
    'pengesahan' => ['kota' => 'Kendari', 'tanggal' => '2026-05-08', 'smc_nama' => 'Amiruddin A.S, S.Sos', 'smc_pangkat' => 'Pembina IV/a', 'smc_nip' => '197001012000031001'],
];
$qrUrl = BASE_URL . '/operasi.php?action=verify&type=renops&id=7&ref=11&token=ABCDEF0123456789';

try {
    $html = $render($doc, 'final', $qrUrl, 'v2');
    $htmlFinal = $html;
    $htmlDraft = $render($doc, 'draft', null, 'v2');

    foreach (['SITUATION (SITUASI)', 'Awareness Stage (Tahap Menyadari)', 'Initial Action Stage (Tahap Tindakan Awal)', 'MISSION (TUGAS)', 'IMPLEMENTATION (PELAKSANAAN)', 'Planning Stage (Tahap Perencanaan)', 'OPERATION STAGE (TAHAP OPERASI)', 'ADMINISTRATION AND LOGISTICS (ADMINISTRASI DAN LOGISTIK)', 'KOORDINASI, PENGENDALIAN, DAN KOMUNIKASI', 'Jaring Komunikasi', 'Struktur Organisasi Operasi SAR'] as $h) {
        assertCondition(str_contains($html, $h), "Judul bab: {$h}");
    }
    assertCondition(str_contains($html, 'RENCANA OPERASI SAR HARI KE-1') && str_contains($html, e(getAppLogo2DataUri())) && getAppLogo2DataUri() !== getAppLogoDataUri(), 'Kop + logo kanan (logo kedua pengaturan, berbeda dari logo kiri)');
    assertCondition(str_contains($html, 'KANTOR PENCARIAN DAN PERTOLONGAN KENDARI'), 'Kop baris 2: satker huruf besar');
    assertCondition(str_contains($html, 'Operasi SAR Kondisi Membahayakan Manusia: 1 (Satu) Orang Hilang di Kebun Desa Manuru'), 'Kop baris 3 dengan jenis kejadian');
    assertCondition(is_file(ROOT_DIR . '/assets/images/logo_sar_nasional.jpg'), 'Aset logo_sar_nasional.jpg tersedia');
    assertCondition(!str_contains($html, 'Total Endurance'), 'Tanpa kolom Total Endurance');
    assertCondition(str_contains($html, 'Tabel 1. Data Kejadian') && str_contains($html, 'Gambar 1. Peta Lokasi'), 'Penomoran tabel/gambar');
    assertCondition(str_contains($html, 'Tabel 2. Alokasi Aset Darat') && str_contains($html, 'Tabel 3. Penugasan Search and Rescue Unit (SRU)'), 'Tabel aset & SRU bernomor berurutan');
    assertCondition(str_contains($html, 'Gambar 2. Dokumentasi Persiapan dan Pemberangkatan Tim Rescue') && str_contains($html, 'Gambar 3. ')
        && str_contains($html, 'Gambar 4. Jaring Komunikasi dan Koordinasi Operasi SAR') && str_contains($html, 'Gambar 5. Struktur Organisasi Operasi SAR'), 'Gambar foto/jaring/struktur bernomor berurutan');
    assertCondition(str_contains($html, 'ditunjukkan pada Gambar 4.'), 'Paragraf jaring merujuk nomor gambar yang benar');
    assertCondition(str_contains($html, 'class="page-sheet renops-smeac" data-pdf-orientation="portrait"'), 'Lembar utama portrait untuk exporter');
    $posLanskap = strpos($html, 'class="page-sheet renops-smeac lanskap" data-pdf-orientation="landscape"');
    assertCondition($posLanskap !== false && strpos($html, 'Struktur Organisasi Operasi SAR</h3>') > $posLanskap
        && strpos($html, 'class="pengesahan signature-block"') > $posLanskap && strpos($html, 'Jaring Komunikasi</h3>') < $posLanskap,
        'Lembar terakhir landscape: struktur organisasi + pengesahan');
    $posJaring = strpos($html, 'class="page-sheet renops-smeac hal-jaring" data-pdf-orientation="portrait"');
    assertCondition($posJaring !== false && $posJaring < $posLanskap && strpos($html, 'KOORDINASI, PENGENDALIAN, DAN KOMUNIKASI') > $posJaring
        && strpos($html, 'Jaring Komunikasi</h3>') > $posJaring && strpos($html, 'ADMINISTRATION AND LOGISTICS') < $posJaring
        && str_contains($html, '.page-sheet.renops-smeac.hal-jaring { break-before: page;'), 'Jaring komunikasi di halaman sendiri (lembar portrait terpisah)');
    assertCondition(str_contains($html, 'figure.jaring .jaring-svg { width: auto !important; height: 205mm !important;'), 'Diagram jaring dibatasi tinggi agar utuh satu halaman');
    assertCondition(str_contains($html, 'size: A4 portrait') && str_contains($html, '@page lanskap {') && str_contains($html, 'size: A4 landscape')
        && str_contains($html, 'page: lanskap; break-before: page;') && str_contains($html, 'margin: 20mm 20mm 18mm 20mm')
        && str_contains($html, '"Rencana Operasi SAR Hari ke-1 | Kantor Pencarian dan Pertolongan Kendari"')
        && str_contains($html, '"Halaman " counter(page) " dari " counter(pages)'), '@page portrait + @page lanskap, header/footer berjalan');
    assertCondition(!preg_match('/#1f3a5f|#e8731a|#eef3f9/i', $html) && str_contains($html, 'th { background: #ffffff !important; color: #000000 !important;'), 'Teks, tabel & struktur hitam/tanpa warna');
    assertCondition(str_contains($html, '<col style="width:18%"><col style="width:29.7%"><col style="width:14.5%"><col></colgroup>'), 'Kolom SRU: Area lebar, Pola Pencarian sempit (ditukar)');
    assertCondition(str_contains($html, e(BASE_URL . '/uploads/peta_snapshot/peta_uji.png')) && str_contains($html, e(BASE_URL . '/uploads/renops_foto/foto_a.jpg')), 'Gambar peta/foto memakai BASE_URL + path');
    assertCondition(str_contains($html, '(0401) 3196557') && str_contains($html, 'sar304kendari@gmail.com') && str_contains($html, '021-65867510')
        && str_contains($html, 'Com Center : +62811-4034-115') && str_contains($html, 'Faksimile : (0401) 3196558'), 'Jaring komunikasi: kontak kantor + teks statis');
    assertCondition(str_contains($html, 'Kendari, 8 Mei 2026') && str_contains($html, 'Amiruddin A.S, S.Sos') && str_contains($html, 'Pembina IV/a'), 'Blok pengesahan: kota, tanggal Indo, SMC');
    assertCondition(str_contains($html, 'Hari Pertama (28 September 2025)'), 'Operation stage: Hari Pertama (tanggal)');
    assertCondition(str_contains($html, 'STAF KOMUNIKASI') && str_contains($html, 'Nurhayati') && str_contains($html, 'Potensi SAR dan Masyarakat Setempat'), 'Bagan struktur organisasi berisi staf & SRU');

    // Review Focus 1: suntingan berisi HTML tampil sebagai teks.
    $docX = $doc;
    $docX['sections']['mission'] = '<script>alert(1)</script>';
    $docX['sections']['sru'][0]['unsur'] = '<img src=x onerror=alert(3)>';
    $docX['foto'][0]['path'] = 'uploads/x".png onerror="alert(4)';
    $docX['meta']['satker_nama'] = 'Kantor </style><script>alert(2)</script> "Uji"';
    $html2 = $render($docX, 'final', $qrUrl);
    assertCondition(!str_contains($html2, '<script>alert(1)</script>') && str_contains($html2, '&lt;script&gt;'), 'Suntingan di-escape (Review Focus 1)');
    assertCondition(!str_contains($html2, '<img src=x') && !str_contains($html2, '<script>alert(2)') && !str_contains($html2, 'onerror="alert(4)'), 'Sel tabel, path gambar, dan header CSS tidak bisa menyisipkan HTML');

    assertCondition(str_contains($htmlDraft, 'data-sec="mission"') && str_contains($htmlDraft, 'DRAF') && !str_contains($htmlDraft, 'qrCanvas'), 'Mode draf: atribut sunting, watermark, tanpa QR');
    assertCondition(str_contains($htmlDraft, 'QR terbit setelah diterbitkan'), 'Mode draf: kotak pengganti QR');
    assertCondition((bool)preg_match('/data-sec="mission" data-kind="text"[^>]*>\s*<span class="sec-badge[^"]*">Disunting</', $htmlDraft)
        && (bool)preg_match('/data-sec="awareness" data-kind="text"[^>]*>\s*<span class="sec-badge[^"]*">Otomatis</', $htmlDraft), 'Mode draf: lencana Disunting/Otomatis dari edited');
    foreach (['koordinasi' => 'list', 'data_kejadian' => 'table', 'aset' => 'table', 'sru' => 'table', 'struktur' => 'struktur'] as $k => $kind) {
        assertCondition(str_contains($htmlDraft, "data-sec=\"{$k}\" data-kind=\"{$kind}\""), "Mode draf: bagian {$k} data-kind={$kind}");
    }
    assertCondition(str_contains($htmlDraft, 'data-col="unsur"') && str_contains($htmlDraft, 'data-col="track_spacing"') && str_contains($htmlDraft, 'data-col="grup"')
        && str_contains($htmlDraft, 'data-col="value"') && str_contains($htmlDraft, 'data-col="staf_humas"') && str_contains($htmlDraft, 'data-col="keterangan"'), 'Mode draf: sel tabel/struktur ber-data-col');
    assertCondition(str_contains($htmlFinal, 'qrCanvas') && str_contains($htmlFinal, 'Koordinator Misi Pencarian dan Pertolongan (SMC)'), 'Mode final: blok QR SMC');
    assertCondition(str_contains($htmlFinal, 'data-qr-url="' . e($qrUrl) . '"'), 'Mode final: URL verifikasi di canvas QR');
    // Kelas lencana/watermark tetap ada di CSS statis; yang dicek adalah markup.
    assertCondition(!str_contains($htmlFinal, 'data-sec=') && !str_contains($htmlFinal, 'data-col=') && !str_contains($htmlFinal, 'class="sec-badge')
        && !str_contains($htmlFinal, 'class="renops-watermark') && !str_contains($htmlFinal, 'QR terbit setelah'), 'Mode final: tanpa atribut sunting, lencana, watermark');

    // R7: jenis_kejadian kosong.
    $docR7 = $doc;
    $docR7['meta']['jenis_kejadian'] = '';
    $htmlR7 = $render($docR7, 'final', $qrUrl);
    assertCondition(str_contains($htmlR7, 'Operasi SAR: 1 (Satu) Orang Hilang di Kebun Desa Manuru') && !str_contains($htmlR7, 'Operasi SAR :'), 'Kop baris 3 tanpa jenis kejadian (R7)');

    // Hari setelah reopen ("7 + 1").
    $docRe = $doc;
    $docRe['meta']['hari_num'] = 8;
    $docRe['meta']['hari_label'] = '7 + 1';
    $htmlRe = $render($docRe, 'final', $qrUrl);
    assertCondition(str_contains($htmlRe, 'RENCANA OPERASI SAR HARI KE-7 + 1') && str_contains($htmlRe, 'Hari ke-7 + 1 (28 September 2025)'), 'Label hari setelah reopen');

    // Review Focus 2: fixture kosong (tanpa peta/foto/sru/aset/korban/struktur).
    $kosong = $doc;
    $kosong['peta'] = ['path' => null];
    $kosong['foto'] = [];
    $kosong['edited'] = [];
    foreach (['koordinasi', 'koordinasi_instansi', 'persiapan_personel', 'sarana', 'peralatan', 'administrasi', 'logistik', 'data_kejadian', 'aset', 'sru'] as $k) {
        $kosong['sections'][$k] = [];
    }
    $kosong['sections']['struktur'] = array_fill_keys(['sc', 'smc', 'osc', 'staf_operasi', 'staf_admin', 'staf_logistik', 'staf_intelijen', 'staf_humas', 'staf_komunikasi'], '') + ['sru' => []];
    $kosong['kontak'] = ['telp' => '', 'email' => '', 'alamat' => ''];
    $kosong['pengesahan']['smc_nama'] = '';
    $kosong['pengesahan']['smc_pangkat'] = '';
    $okFinal = $render($kosong, 'final', $qrUrl);
    $okDraft = $render($kosong, 'draft');
    assertCondition(!str_contains($okFinal, 'Peta Lokasi') && str_contains($okFinal, 'Gambar 1. Jaring Komunikasi dan Koordinasi Operasi SAR')
        && str_contains($okFinal, 'Gambar 2. Struktur Organisasi Operasi SAR'), 'Fixture kosong: tanpa gambar peta, penomoran gambar mulai dari 1');
    assertCondition(str_contains($okFinal, 'Tabel 1. Data Kejadian') && str_contains($okFinal, 'Tabel 2. Alokasi Aset') && str_contains($okFinal, 'Tabel 3. Penugasan'), 'Fixture kosong: tabel tetap tampil');
    assertCondition(str_contains($okDraft, 'data-sec="sru" data-kind="table"') && str_contains($okDraft, 'data-sec="sarana" data-kind="list"'), 'Fixture kosong (draf): bagian tetap bisa disunting');
    assertCondition(str_contains($okFinal, 'Com Center : +62811-4034-115') && str_contains($okFinal, 'Faksimile : (0401) 3196558'), 'Fixture kosong: baris statis Kantor tetap tampil walau telepon kosong');

    // Variabel/helper partial tidak bocor ke lingkup pemanggil (editor Task 8 / halaman cetak).
    $leak = (function (array $doc) use ($partial): array {
        $row = 'SENTINEL';
        $col = 'SENTINEL';
        $mode = 'draft';
        $qrUrl = null;
        $versiLabel = 'v1';
        ob_start();
        include $partial;
        ob_end_clean();
        return [$row, $col, $mode, $versiLabel, isset($meta) || isset($S) || isset($text) || isset($draft) || isset($badge)];
    })($doc);
    assertCondition($leak === ['SENTINEL', 'SENTINEL', 'draft', 'v1', false], 'Partial tidak membocorkan/menimpa variabel pemanggil');

    // Halaman cetak/publik mandiri.
    $renderPage = function (array $doc, ?string $qrUrl, string $versiLabel, bool $isPublic, ?string $backUrl) use ($page): string {
        set_error_handler(function (int $no, string $str, string $file, int $line): bool {
            throw new ErrorException($str, 0, $no, $file, $line);
        });
        ob_start();
        try {
            include $page;
        } finally {
            $out = (string)ob_get_clean();
            restore_error_handler();
        }
        return $out;
    };
    $pub = $renderPage($doc, $qrUrl, 'v2', true, null);
    $int = $renderPage($doc, $qrUrl, 'v2', false, BASE_URL . '/operasi.php?action=renops_dokumen&id_renops=11');
    assertCondition(str_contains($pub, '<!DOCTYPE html>') && str_contains($pub, 'MISSION (TUGAS)') && str_contains($pub, 'id="btnDownloadPdf"')
        && str_contains($pub, 'window.print()'), 'Halaman cetak: dokumen + tombol Cetak & PDF Gambar');
    assertCondition(str_contains($pub, 'qrcode.min.js') && str_contains($pub, 'html2pdf.bundle.min.js') && str_contains($pub, 'pdf_document_exporter.js')
        && str_contains($pub, "getElementById('qrCanvas')"), 'Halaman cetak: skrip QR berlogo + exporter');
    assertCondition(str_contains($pub, 'name="robots" content="noindex, nofollow"') && !str_contains($pub, 'renops_dokumen&amp;id_renops'), 'Halaman publik: noindex, tanpa tautan kembali');
    assertCondition(str_contains($int, 'renops_dokumen&amp;id_renops=11') && !str_contains($int, 'noindex'), 'Halaman internal: tautan kembali');

    // Exporter: landscape hanya bila data-pdf-orientation="landscape"; konstanta portrait tetap.
    $js = (string)file_get_contents(ROOT_DIR . '/assets/js/pdf_document_exporter.js');
    assertCondition(str_contains($js, "return el.dataset.pdfOrientation === 'landscape';") && str_contains($js, "orientation: isLandscape(el) ? 'landscape' : 'portrait'"), 'Exporter: orientasi per lembar dari data-pdf-orientation');
    assertCondition(str_contains($js, "document.querySelectorAll('.page-sheet')") && str_contains($js, "pdf.addPage('a4', landscape ? 'landscape' : 'portrait')")
        && str_contains($js, 'window.__pdfAppend = true;'), 'Exporter: lembar portrait + landscape digabung dalam satu PDF');
    assertCondition(str_contains($js, "cSheet.querySelectorAll('svg')") && str_contains($js, "svg.setAttribute('width', String(Math.round(r.width)))"), 'Exporter: SVG jaring diberi ukuran tampil (tidak terpotong di PDF)');
    assertCondition(str_contains($js, 'var pageWidthMm = landscape ? 297 : 210;') && str_contains($js, 'var pageHeightMm = landscape ? 210 : 297;')
        && str_contains($js, 'margin: [20, 18, 18, 14],'), 'Exporter: ukuran per orientasi, margin seragam SMEAC');
} catch (Throwable $e) {
    assertCondition(false, 'Render tanpa exception/warning: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
}

echo "\nHasil: {$testPassed}/{$testTotal} lulus.\n";
exit($testPassed === $testTotal ? 0 : 1);
