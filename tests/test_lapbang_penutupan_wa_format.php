<?php
/**
 * QR-SAR - Test Format WhatsApp LAPBANG & Penutupan + Pemisahan Input Cuaca (Opsi A)
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$passed = 0;
$failed = 0;

function assertCase(bool $cond, string $message, string $detail = ''): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}" . ($detail !== '' ? " => {$detail}" : "") . "\n";
    }
}

echo "=== TEST FORMAT WA LAPBANG & PENUTUPAN (OPSI A) ===\n\n";

$db = Database::getConnection();

// -----------------------------------------------------------------------------
// 1. Skema DB & Persistensi Cuaca Terpisah (LaporanHarian + HierarchyHelper)
// -----------------------------------------------------------------------------
echo "--- 1. Skema Kolom & Persistensi Cuaca Terpisah ---\n";

$hasGelombang = !empty($db->query("SHOW COLUMNS FROM LaporanHarian LIKE 'gelombang'")->fetchAll());
$hasAngin = !empty($db->query("SHOW COLUMNS FROM LaporanHarian LIKE 'angin'")->fetchAll());
assertCase($hasGelombang, "Tabel LaporanHarian memiliki kolom 'gelombang'");
assertCase($hasAngin, "Tabel LaporanHarian memiliki kolom 'angin'");

$testOpId = null;
try {
    $testOpId = OperasiModel::create([
        'nama_operasi' => 'Kecelakaan Kapal, 1 Orang ABK hilang saat mencari ikan di Karang Koromaho, Perairan Tomia, Kab. Wakatobi',
        'jenis_kejadian' => 'KMM',
        'lokasi_kejadian' => 'Karang Koromaho, Perairan Tomia, Kab. Wakatobi',
        'waktu_kejadian' => '2026-11-06 06:00:00',
        'waktu_lapor' => '2026-11-06 07:00:00',
        'waktu_berangkat' => '2026-11-06 07:30:00',
        'eta_tiba' => '2026-11-06 09:00:00',
        'jumlah_pob' => 1,
        'cuaca' => 'Berawan',
        'gelombang' => '0,5 Mtr',
        'angin' => '5 km/jam dari Timur',
        'korban' => [
            [
                'nama_korban' => 'Rendi',
                'umur' => '24 Tahun',
                'jk' => 'Laki-laki',
                'alamat' => 'Dusun Pasilohe, Desa Bira, Kec. Bontobahari, Kab. Bulukumba, Sulawesi Selatan',
                'status_korban' => 'DP',
            ],
        ],
        'detail_unsur' => [
            ['nama_unsur' => 'SMC dan Staf', 'jumlah_personil' => 7],
            ['nama_unsur' => 'Tim Rescue Pos SAR Wakatobi', 'jumlah_personil' => 5],
            ['nama_unsur' => 'Polairud Wakatobi', 'jumlah_personil' => 1],
            ['nama_unsur' => 'Crew KMN Dila Akbar 01', 'jumlah_personil' => 8],
            ['nama_unsur' => 'Crew KMN Andira Abadi', 'jumlah_personil' => 8],
        ],
        'detail_alut' => [
            ['nama_alut' => 'Rescue Truck Personil', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'ALUT_DARAT'],
            ['nama_alut' => 'Rigid Inflatable Boat (RIB) Wakatobi', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Kapal Ikan (KMN Dila Akbar 01 & KMN Andira Abadi)', 'satuan' => 'Unit', 'jumlah_alut' => 2, 'kategori' => 'ALUT_LAUT'],
            ['nama_alut' => 'Palsar Selam', 'satuan' => 'Set', 'jumlah_alut' => 2, 'kategori' => 'PALSAR'],
            ['nama_alut' => 'Aqua Eye', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
            ['nama_alut' => 'Palsar Medis', 'satuan' => 'Set', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
            ['nama_alut' => 'Palsar Komunikasi', 'satuan' => 'Set', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
            ['nama_alut' => 'Palsar Evakuasi', 'satuan' => 'Set', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
        ],
    ]);

    $opsLoaded = OperasiModel::getById($testOpId);
    $defaultsInit = HierarchyHelper::getNextLapbangDefaults($opsLoaded);
    assertCase(
        array_key_exists('inherited_gelombang', $defaultsInit) && array_key_exists('inherited_angin', $defaultsInit),
        "HierarchyHelper::getNextLapbangDefaults mengembalikan key 'inherited_gelombang' & 'inherited_angin'"
    );
    assertCase(
        ($defaultsInit['inherited_cuaca'] ?? '') === 'Berawan'
        && ($defaultsInit['inherited_gelombang'] ?? '') === '0,5 Mtr'
        && ($defaultsInit['inherited_angin'] ?? '') === '5 km/jam dari Timur',
        "Defaults awal mewarisi cuaca, gelombang, angin dari Laporan Awal"
    );

    $lapId = LaporanModel::create([
        'id_operasi' => $testOpId,
        'hari_ke' => 'H.6',
        'jenis_laporan' => 'PERKEMBANGAN',
        'waktu_laporan' => '2026-11-11 17:00:00',
        'isi_laporan' => "- Pukul 08.00 WITA, Tim melanjutkan pencarian.\n- Pukul 17.00 WITA, hasil masih nihil.",
        'cuaca' => 'Cerah',
        'gelombang' => '0,2 Mtr',
        'angin' => '3,1km/jam dari Utara',
    ]);
    $lapSaved = LaporanModel::getById($lapId);
    assertCase(
        ($lapSaved['cuaca'] ?? '') === 'Cerah'
        && ($lapSaved['gelombang'] ?? null) === '0,2 Mtr'
        && ($lapSaved['angin'] ?? null) === '3,1km/jam dari Utara',
        "LaporanModel::create menyimpan cuaca, gelombang, angin secara terpisah"
    );

    $opsAfterH6 = OperasiModel::getById($testOpId);
    $defaultsNext = HierarchyHelper::getNextLapbangDefaults($opsAfterH6);
    assertCase(
        ($defaultsNext['inherited_cuaca'] ?? '') === 'Cerah'
        && ($defaultsNext['inherited_gelombang'] ?? '') === '0,2 Mtr'
        && ($defaultsNext['inherited_angin'] ?? '') === '3,1km/jam dari Utara',
        "HierarchyHelper::getNextLapbangDefaults mewarisi cuaca, gelombang, angin dari LAPBANG terbaru"
    );

    // Partial update tanpa key gelombang & angin tidak boleh menghapus data lama
    LaporanModel::update($lapId, [
        'hari_ke' => 'H.6',
        'jenis_laporan' => 'PERKEMBANGAN',
        'waktu_laporan' => '2026-11-11 17:30:00',
        'isi_laporan' => '- Pukul 17.30 WITA, koreksi tanpa field cuaca.',
    ]);
    $lapAfterPartial = LaporanModel::getById($lapId);
    assertCase(
        ($lapAfterPartial['cuaca'] ?? '') === 'Cerah'
        && ($lapAfterPartial['gelombang'] ?? '') === '0,2 Mtr'
        && ($lapAfterPartial['angin'] ?? '') === '3,1km/jam dari Utara',
        "LaporanModel::update parsial menjaga nilai cuaca, gelombang, angin tersimpan"
    );

    // Update eksplisit cuaca, gelombang, angin
    LaporanModel::update($lapId, [
        'hari_ke' => 'H.6',
        'jenis_laporan' => 'PERKEMBANGAN',
        'waktu_laporan' => '2026-11-11 17:30:00',
        'isi_laporan' => '- Pukul 17.30 WITA, pembaruan cuaca.',
        'cuaca' => 'Hujan Ringan',
        'gelombang' => '1,0 Mtr',
        'angin' => '10 Knots dari Selatan',
    ]);
    $lapAfterExplicit = LaporanModel::getById($lapId);
    assertCase(
        ($lapAfterExplicit['cuaca'] ?? '') === 'Hujan Ringan'
        && ($lapAfterExplicit['gelombang'] ?? '') === '1,0 Mtr'
        && ($lapAfterExplicit['angin'] ?? '') === '10 Knots dari Selatan',
        "LaporanModel::update memperbarui cuaca, gelombang, angin saat dikirim eksplisit"
    );
} finally {
    if ($testOpId) {
        OperasiModel::delete($testOpId);
    }
}

// -----------------------------------------------------------------------------
// 2. Format Naskah WA LAPBANG & Penutupan (Target Persis + Edge Cases)
// -----------------------------------------------------------------------------
echo "\n--- 2. Format Naskah WA LAPBANG & Penutupan ---\n";

$opsSample = [
    'nama_operasi' => 'Kecelakaan Kapal, 1 Orang ABK hilang saat mencari ikan di Karang Koromaho, Perairan Tomia, Kab. Wakatobi',
    'jumlah_pob' => 1,
    'cuaca' => 'Berawan',
    'gelombang' => '0,5 Mtr',
    'angin' => '5 km/jam dari Timur',
];
$korbanSample = [[
    'nama_korban' => 'Rendi',
    'umur' => '24 Tahun',
    'jk' => 'Laki-laki',
    'alamat' => 'Dusun Pasilohe, Desa Bira, Kec. Bontobahari, Kab. Bulukumba, Sulawesi Selatan',
    'status_korban' => 'MD',
]];
$unsurSample = [
    ['nama_unsur' => 'SMC dan Staf', 'jumlah_personil' => 7],
    ['nama_unsur' => 'Tim Rescue Pos SAR Wakatobi', 'jumlah_personil' => 5],
    ['nama_unsur' => 'Polairud Wakatobi', 'jumlah_personil' => 1],
    ['nama_unsur' => 'Crew KMN Dila Akbar 01', 'jumlah_personil' => 8],
    ['nama_unsur' => 'Crew KMN Andira Abadi', 'jumlah_personil' => 8],
];
$alutSample = [
    ['nama_alut' => 'Rescue Truck Personil', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'ALUT_DARAT'],
    ['nama_alut' => 'Rigid Inflatable Boat (RIB) Wakatobi', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'ALUT_LAUT'],
    ['nama_alut' => 'Kapal Ikan (KMN Dila Akbar 01 & KMN Andira Abadi)', 'satuan' => 'Unit', 'jumlah_alut' => 2, 'kategori' => 'ALUT_LAUT'],
    ['nama_alut' => 'Palsar Selam', 'satuan' => 'Set', 'jumlah_alut' => 2, 'kategori' => 'PALSAR'],
    ['nama_alut' => 'Aqua Eye', 'satuan' => 'Unit', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
    ['nama_alut' => 'Palsar Medis', 'satuan' => 'Set', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
    ['nama_alut' => 'Palsar Komunikasi', 'satuan' => 'Set', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
    ['nama_alut' => 'Palsar Evakuasi', 'satuan' => 'Set', 'jumlah_alut' => 1, 'kategori' => 'PALSAR'],
];
$lapPenutupanInput = [
    'hari_ke' => 'H.7',
    'waktu_laporan' => '2026-11-12 12:30:00',
    'waktu_debrief' => '2026-11-12 12:10:00',
    'isi_laporan' => implode("\n", [
        '- Pukul 08.10 WITA, RIB tiba di LKP dan segera melaksanakan pencarian dengan membagi tugas bersama rekan korban.',
        '- Pukul 08.30 WITA, korban ditemukan oleh Tim SAR Gabungan dalam keadaan meninggal dunia (MD), dengan cara diselami pada kedalaman 45 meter dan jarak 340 meter arah Barat Laut dari LKP.',
        '- Pukul 08.45 WITA, Tim SAR melaksanakan evakuasi dan pengemasan jenazah korban.',
        '- Pukul 09.45 WITA, Tim SAR Gabungan menyerahkan korban kepada pihak keluarga dan Kapten Kapal KMN Andira Abadi, yang selanjutnya akan membawa korban menuju Bira, Bulukumba.',
        '- Pukul 10.15 WITA, Tim SAR Gabungan kembali menuju posko.',
    ]),
    'cuaca' => 'Cerah',
    'gelombang' => '0,2 Mtr',
    'angin' => '3,1km/jam dari Utara',
];

$waPenutupan = WhatsAppTemplateService::generateLapbangWA(
    $opsSample,
    $lapPenutupanInput,
    $korbanSample,
    $unsurSample,
    $alutSample,
    true
);

$expectedPenutupan = <<<'WA'
————————-
FILE AKTIF
————————-

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI

LAPBANG OPS SAR H.7 DAN PENUTUPAN
KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
Kamis, 12 November 2026

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon izin melaporkan Perkembangan dan Penutupan Operasi SAR Kecelakaan Kapal, 1 Orang ABK hilang saat mencari ikan di Karang Koromaho, Perairan Tomia, Kab. Wakatobi.
====================================

Hasil Ops SAR H.7:

- Pukul 08.10 WITA, RIB tiba di LKP dan segera melaksanakan pencarian dengan membagi tugas bersama rekan korban.
- Pukul 08.30 WITA, korban ditemukan oleh Tim SAR Gabungan dalam keadaan meninggal dunia (MD), dengan cara diselami pada kedalaman 45 meter dan jarak 340 meter arah Barat Laut dari LKP.
- Pukul 08.45 WITA, Tim SAR melaksanakan evakuasi dan pengemasan jenazah korban.
- Pukul 09.45 WITA, Tim SAR Gabungan menyerahkan korban kepada pihak keluarga dan Kapten Kapal KMN Andira Abadi, yang selanjutnya akan membawa korban menuju Bira, Bulukumba.
- Pukul 10.15 WITA, Tim SAR Gabungan kembali menuju posko.
- Pada tanggal 12 November 2026 pukul 12.10 WITA, Tim SAR Gabungan tiba di posko dan melaksanakan debriefing penutupan terhadap Operasi SAR Kecelakaan Kapal, 1 Orang ABK hilang saat mencari ikan di Karang Koromaho, Perairan Tomia, Kab. Wakatobi. Dengan ditemukannya korban serta selesainya proses evakuasi dan penyerahan, Operasi SAR dinyatakan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih.

Data Korban: 1 Orang
- S : -
- MD : 1 Orang
- DP : -
- H : -

Identitas Korban:
1. Nama : Rendi [Status: Meninggal Dunia (MD)]
   Umur : 24 Tahun
   Jenis Kelamin : Laki-laki
   Alamat : Dusun Pasilohe, Desa Bira, Kec. Bontobahari, Kab. Bulukumba, Sulawesi Selatan

Unsur SAR Terlibat:
- SMC dan Staf : 7 Orang
- Tim Rescue Pos SAR Wakatobi : 5 Orang
- Polairud Wakatobi : 1 Orang
- Crew KMN Dila Akbar 01 : 8 Orang
- Crew KMN Andira Abadi : 8 Orang

Alut dan Palsar:
- 1 Unit Rescue Truck Personil
- 1 Unit Rigid Inflatable Boat (RIB) Wakatobi
- 2 Unit Kapal Ikan (KMN Dila Akbar 01 & KMN Andira Abadi)
- 2 Set Palsar Selam
- 1 Unit Aqua Eye
- 1 Set Palsar Medis
- 1 Set Palsar Komunikasi
- 1 Set Palsar Evakuasi

Kondisi Cuaca:
• cuaca : Cerah
• Gelombang : 0,2 Mtr
• Angin: 3,1km/jam dari Utara (Sumber BMKG)

Demikian kami laporkan, mohon petunjuk dan arahan.

Ttd

KKS Kendari
==================================================
🏢 Kantor Pencarian dan Pertolongan Kendari
📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116
☎️ Telp. : (0401) 3196557
📠 Fax. : (0401) 3196558
📱 WhatsApp : +62811-4034-115
🚨 Emergency Call : 115
🌐 Website : www.kendari.basarnas.go.id
📧 E-Mail : sar304kendari@gmail.com / pusdatinsarkendari@yahoo.co.id
📲 Medsos : @Basarnaskendari
WA;

assertCase(
    $waPenutupan === $expectedPenutupan,
    "Naskah WA Penutupan H.7 persis sama karakter-per-karakter dengan target format",
    "Len actual=" . strlen($waPenutupan) . ", expected=" . strlen($expectedPenutupan)
);

// Review Focus 1: nama_operasi yang sudah diawali 'Operasi SAR' tidak boleh menggandakan kata 'Operasi SAR'
$opsPrefixed = ['nama_operasi' => 'Operasi SAR KMM di Teluk Kendari', 'jumlah_pob' => 1];
$waPrefixed = WhatsAppTemplateService::generateLapbangWA(
    $opsPrefixed,
    ['hari_ke' => 'H.2', 'waktu_laporan' => '2026-11-12 10:00:00', 'waktu_debrief' => '2026-11-12 10:00:00', 'isi_laporan' => '- Korban ditemukan selamat.'],
    [['nama_korban' => 'Budi', 'status_korban' => 'S']],
    [],
    [],
    true
);
assertCase(
    str_contains($waPrefixed, 'Perkembangan dan Penutupan Operasi SAR KMM di Teluk Kendari.')
    && str_contains($waPrefixed, 'debriefing penutupan terhadap Operasi SAR KMM di Teluk Kendari.')
    && !str_contains($waPrefixed, 'Operasi SAR Operasi SAR'),
    "Prefix ganda 'Operasi SAR Operasi SAR' dicegah pada header dan klausul debriefing"
);

// Review Focus 2: Normalisasi bullet & tidak menduplikasi debriefing jika sudah ditulis user di isi_laporan
$waNormalized = WhatsAppTemplateService::generateLapbangWA(
    $opsSample,
    [
        'hari_ke' => 'H.3',
        'waktu_laporan' => '2026-11-12 16:00:00',
        'waktu_debrief' => '2026-11-12 16:30:00',
        'isi_laporan' => "Pukul 08.00 WITA, penyisiran hari ke-3.\n• Pukul 14.00 WITA, korban ditemukan.\n- Pada tanggal 12 November 2026 pukul 16.30 WITA, debriefing penutupan khusus oleh SMC.",
    ],
    $korbanSample,
    $unsurSample,
    $alutSample,
    true
);
assertCase(
    str_contains($waNormalized, "- Pukul 08.00 WITA, penyisiran hari ke-3.\n- Pukul 14.00 WITA, korban ditemukan.\n- Pada tanggal 12 November 2026 pukul 16.30 WITA, debriefing penutupan khusus oleh SMC."),
    "Baris polos & bullet dot dinormalisasi ke '- '"
);
assertCase(
    substr_count(strtolower($waNormalized), 'debriefing penutupan') === 1,
    "Klausul debriefing tidak diduplikasi saat pengguna sudah menuliskannya di isi_laporan"
);

// Review Focus 3: LAPBANG Harian (non-penutupan) & Penutupan H.7 Nihil (DP > 0)
$waHarian = WhatsAppTemplateService::generateLapbangWA(
    $opsSample,
    ['hari_ke' => 'H.5', 'waktu_laporan' => '2026-11-10 17:00:00', 'isi_laporan' => 'Pencarian hari ke-5 masih nihil.'],
    [['nama_korban' => 'Rendi', 'status_korban' => 'DP']],
    $unsurSample,
    $alutSample,
    false
);
assertCase(
    str_contains($waHarian, 'LAPBANG OPS SAR H.5')
    && !str_contains($waHarian, 'DAN PENUTUPAN')
    && str_contains($waHarian, 'Dengan hormat, mohon izin melaporkan Perkembangan Operasi SAR Kecelakaan Kapal')
    && !str_contains($waHarian, 'debriefing penutupan')
    && str_contains($waHarian, '- DP : 1 Orang'),
    "LAPBANG Harian memakai judul & pembuka Perkembangan serta tanpa klausul penutupan"
);

$waNihilTutup = WhatsAppTemplateService::generateLapbangWA(
    $opsSample,
    ['hari_ke' => 'H.7', 'waktu_laporan' => '2026-11-12 17:00:00', 'waktu_debrief' => '2026-11-12 17:00:00', 'isi_laporan' => '- Hasil pencarian H.7 nihil.'],
    [['nama_korban' => 'Rendi', 'status_korban' => 'H']],
    [],
    [],
    true
);
assertCase(
    str_contains($waNihilTutup, 'Dengan selesainya seluruh rangkaian pencarian, Operasi SAR dinyatakan ditutup'),
    "Penutupan Nihil (H/DP) memakai redaksi 'Dengan selesainya seluruh rangkaian pencarian'"
);

// Review Focus 4: Fallback cuaca ke Operasi / '-', serta menghindari ganda '(Sumber BMKG)'
$waWeatherFallback = WhatsAppTemplateService::generateLapbangWA(
    ['nama_operasi' => 'Uji Cuaca', 'cuaca' => 'Berawan', 'gelombang' => '1 Mtr', 'angin' => '8 Knots (Sumber BMKG)'],
    ['hari_ke' => 'H.1', 'waktu_laporan' => '2026-11-12 10:00:00', 'isi_laporan' => 'Uji'],
    [],
    [],
    [],
    false
);
assertCase(
    str_contains($waWeatherFallback, "• cuaca : Berawan\n• Gelombang : 1 Mtr\n• Angin: 8 Knots (Sumber BMKG)")
    && substr_count($waWeatherFallback, 'Sumber BMKG') === 1,
    "Fallback cuaca ke Operasi berfungsi dan '(Sumber BMKG)' tidak terganda"
);

$waWeatherEmpty = WhatsAppTemplateService::generateLapbangWA(
    ['nama_operasi' => 'Uji Cuaca Kosong'],
    ['hari_ke' => 'H.1', 'waktu_laporan' => '2026-11-12 10:00:00', 'isi_laporan' => 'Uji'],
    [],
    [],
    [],
    false
);
assertCase(
    str_contains($waWeatherEmpty, "• cuaca : -\n• Gelombang : -\n• Angin: -"),
    "Cuaca kosong seluruhnya menampilkan '-' tanpa suffix BMKG"
);

echo "\nRingkasan: Lulus {$passed}, Gagal {$failed}\n";
exit($failed > 0 ? 1 : 0);
