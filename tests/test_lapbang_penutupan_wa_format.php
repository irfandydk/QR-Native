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

echo "\nRingkasan: Lulus {$passed}, Gagal {$failed}\n";
exit($failed > 0 ? 1 : 0);
