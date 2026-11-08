<?php
/**
 * QR-SAR - Kategori Alut & Palsar: satu sumber kode, label, urutan, normalisasi dan resolver.
 *
 * Resolver: kategori item (bila valid) → master via id_alut → master via nama → tebakan kata kunci (kata utuh).
 * Item laporan menyimpan salinan kategorinya, jadi mengubah master tidak mengubah laporan yang sudah tersimpan.
 */

declare(strict_types=1);

class AlutKategori {
    public const DARAT = 'ALUT_DARAT';
    public const LAUT = 'ALUT_LAUT';
    public const UDARA = 'ALUT_UDARA';
    public const PALSAR = 'PALSAR';
    public const URUTAN = [self::DARAT, self::LAUT, self::UDARA, self::PALSAR];
    public const LABEL = [self::DARAT => 'ALUT Darat', self::LAUT => 'ALUT Laut', self::UDARA => 'ALUT Udara', self::PALSAR => 'PALSAR'];

    /** Cadangan tebakan (kata utuh), diperiksa berurutan Laut → Udara → Darat; selain itu PALSAR. */
    public const KATA_KUNCI = [
        self::LAUT => ['kapal', 'rib', 'perahu', 'boat', 'speed', 'speedboat', 'longboat'],
        self::UDARA => ['heli', 'helikopter', 'pesawat', 'drone'],
        self::DARAT => ['car', 'truck', 'truk', 'mobil', 'motor', 'ambulans'],
    ];

    /** Isian baku master (nama huruf kecil → kode); sama dengan daftar di migrasi 2026_11_07_add_kategori_alut.sql. */
    public const BAKU = [
        'rescue car' => self::DARAT, 'rescue carrier' => self::DARAT,
        'rescue boat 210' => self::LAUT, 'rescue boat 307' => self::LAUT, 'rigid inflatable boat (rib)' => self::LAUT,
        'rubber boat' => self::LAUT, 'longboat masyarakat' => self::LAUT, 'kn sar pacitan' => self::LAUT,
        'drone thermal' => self::UDARA,
    ];

    /** @var array{id: array<int,string>, nama: array<string,string>}|null */
    private static ?array $master = null;

    public static function normalize(mixed $v): ?string {
        if (!is_string($v)) {
            return null;
        }
        $k = strtoupper(trim($v));
        return in_array($k, self::URUTAN, true) ? $k : null;
    }

    public static function label(string $kode): string {
        return self::LABEL[$kode] ?? self::LABEL[self::PALSAR];
    }

    public static function isAlut(string $kode): bool {
        return str_starts_with($kode, 'ALUT_');
    }

    public static function tebak(string $nama): string {
        foreach (self::KATA_KUNCI as $kode => $kata) {
            if (preg_match('/\b(?:' . implode('|', array_map('preg_quote', $kata)) . ')\b/iu', $nama)) {
                return $kode;
            }
        }
        return self::PALSAR;
    }

    public static function resolve(array $item): string {
        $own = self::normalize($item['kategori'] ?? null);
        if ($own !== null) {
            return $own;
        }
        $m = self::master();
        $id = (int)($item['id_alut'] ?? 0);
        if ($id > 0 && isset($m['id'][$id])) {
            return $m['id'][$id];
        }
        $nama = (string)($item['nama_alut'] ?? '');
        $key = self::kunciNama($nama);
        if ($key !== '' && isset($m['nama'][$key])) {
            return $m['nama'][$key];
        }
        return self::tebak($nama);
    }

    /** @return list<array> setiap item mendapat 'kategori' hasil resolve(). */
    public static function normalizeList(array $items): array {
        $out = [];
        foreach ($items as $it) {
            if (is_array($it)) {
                $it['kategori'] = self::resolve($it);
                $out[] = $it;
            }
        }
        return $out;
    }

    /** Urutan stabil Darat → Laut → Udara → PALSAR. */
    public static function sort(array $items): array {
        $items = array_values(array_filter($items, 'is_array'));
        $rank = array_flip(self::URUTAN);
        $keyed = [];
        foreach ($items as $i => $it) {
            $keyed[] = [$rank[self::resolve($it)], $i, $it];
        }
        usort($keyed, fn($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        return array_column($keyed, 2);
    }

    /** Konfigurasi untuk JS form (window.ALUT_KATEGORI): urutan, label, kata kunci & peta master — resolver JS sama dengan PHP. */
    public static function configJs(): array {
        return [
            'urutan' => array_map(fn($k) => ['kode' => $k, 'label' => self::LABEL[$k]], self::URUTAN),
            'kata' => self::KATA_KUNCI,
            'master' => self::master(),
        ];
    }

    public static function resetCache(): void {
        self::$master = null;
    }

    /**
     * Isi kategori item laporan lama sekali (idempoten): DetailAlut.kategori NULL/tidak valid, serta item JSON
     * detail_alut di Renops & LaporanHarian tanpa kategori valid. Kategori yang sudah valid tidak disentuh.
     *
     * @return array{detail_alut:int, renops:int, laporan:int}
     */
    public static function backfill(PDO $db): array {
        self::resetCache();
        $hasil = ['detail_alut' => 0, 'renops' => 0, 'laporan' => 0];

        $upd = $db->prepare('UPDATE DetailAlut SET kategori = :k WHERE id_detail_alut = :id');
        $rows = $db->query("SELECT id_detail_alut, id_alut, nama_alut, kategori FROM DetailAlut
            WHERE kategori IS NULL OR kategori NOT IN ('ALUT_DARAT','ALUT_LAUT','ALUT_UDARA','PALSAR')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $upd->execute([':k' => self::resolve(['id_alut' => $r['id_alut'], 'nama_alut' => $r['nama_alut']]), ':id' => $r['id_detail_alut']]);
            $hasil['detail_alut']++;
        }

        foreach (['renops' => ['Renops', 'id_renops'], 'laporan' => ['LaporanHarian', 'id_laporan']] as $kunci => [$tabel, $pk]) {
            $upd = $db->prepare("UPDATE {$tabel} SET detail_alut = :j WHERE {$pk} = :id");
            foreach ($db->query("SELECT {$pk} AS id, detail_alut FROM {$tabel} WHERE detail_alut IS NOT NULL AND detail_alut <> ''")->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $items = json_decode((string)$r['detail_alut'], true);
                if (!is_array($items)) {
                    continue;
                }
                $ubah = false;
                foreach ($items as $i => $it) {
                    if (is_array($it) && self::normalize($it['kategori'] ?? null) === null) {
                        $items[$i]['kategori'] = self::resolve($it);
                        $ubah = true;
                    }
                }
                if ($ubah) {
                    $upd->execute([':j' => json_encode($items, JSON_UNESCAPED_UNICODE), ':id' => $r['id']]);
                    $hasil[$kunci]++;
                }
            }
        }
        return $hasil;
    }

    /**
     * Pastikan kolom kategori ada (restore SQL dari cadangan sebelum fitur menghapusnya, sementara tabel
     * migrations tetap mencatat migrasi sudah jalan). @return bool true bila kolom MasterAlut baru dibuat.
     */
    public static function ensureSchema(PDO $db): bool {
        $ada = fn(string $t) => in_array('kategori', $db->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_COLUMN), true);
        $baru = false;
        if (!$ada('MasterAlut')) {
            $db->exec("ALTER TABLE `MasterAlut` ADD COLUMN `kategori` VARCHAR(20) NOT NULL DEFAULT 'PALSAR' AFTER `satuan`");
            $baru = true;
        }
        if (!$ada('DetailAlut')) {
            $db->exec("ALTER TABLE `DetailAlut` ADD COLUMN `kategori` VARCHAR(20) NULL DEFAULT NULL AFTER `satuan`");
        }
        self::resetCache();
        return $baru;
    }

    /**
     * Pemulihan setelah migrate / restore: kolom, isian master (hanya bila kolom master baru dibuat atau $isiMaster,
     * agar koreksi manual tidak ditimpa), lalu backfill item laporan.
     *
     * @return array{master:int, detail_alut:int, renops:int, laporan:int}
     */
    public static function pulihkan(PDO $db, bool $isiMaster = false): array {
        $master = (self::ensureSchema($db) || $isiMaster) ? self::seedMaster($db) : 0;
        return ['master' => $master] + self::backfill($db);
    }

    /** Cadangan JSON dari sebelum fitur: baris master_alut tidak membawa kunci kategori. */
    public static function cadanganTanpaKategori(array $data): bool {
        $dbData = $data['data'] ?? $data['database'] ?? $data['backup'] ?? $data;
        $rows = is_array($dbData) ? ($dbData['master_alut'] ?? null) : null;
        $first = is_array($rows) ? reset($rows) : false;
        return is_array($first) && !array_key_exists('kategori', $first);
    }

    /**
     * Isian master yang masih bawaan PALSAR: daftar baku dulu, lalu tebakan kata kunci.
     * Dipanggil lewat pulihkan() hanya saat kolom/isian master baru, agar koreksi manual
     * (mis. drone diubah ke PALSAR) tidak ditimpa run berikutnya.
     */
    public static function seedMaster(PDO $db): int {
        $n = 0;
        $upd = $db->prepare('UPDATE MasterAlut SET kategori = :k WHERE id_alut = :id');
        foreach ($db->query("SELECT id_alut, nama_alut FROM MasterAlut WHERE kategori = 'PALSAR'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $t = self::BAKU[self::kunciNama((string)$r['nama_alut'])] ?? self::tebak((string)$r['nama_alut']);
            if ($t !== self::PALSAR) {
                $upd->execute([':k' => $t, ':id' => $r['id_alut']]);
                $n++;
            }
        }
        self::resetCache();
        return $n;
    }

    private static function kunciNama(string $nama): string {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $nama) ?? $nama));
    }

    /** @return array{id: array<int,string>, nama: array<string,string>} */
    private static function master(): array {
        if (self::$master !== null) {
            return self::$master;
        }
        $m = ['id' => [], 'nama' => []];
        try {
            foreach (getDB()->query('SELECT id_alut, nama_alut, kategori FROM MasterAlut')->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $k = self::normalize($r['kategori'] ?? null);
                if ($k !== null) {
                    $m['id'][(int)$r['id_alut']] = $k;
                    $m['nama'][self::kunciNama((string)$r['nama_alut'])] = $k;
                }
            }
        } catch (PDOException $e) {
            // Kolom kategori belum dimigrasi: resolver jatuh ke tebakan kata kunci.
            if ($e->getCode() !== '42S22') {
                throw $e;
            }
        }
        return self::$master = $m;
    }
}
