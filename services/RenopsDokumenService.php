<?php
/**
 * QR-SAR - Dokumen Rencana Operasi SAR (SMEAC)
 * resolve() menghasilkan satu array "Kontrak Data Dokumen": nilai default dari data operasi
 * (hierarki per hari) ditimpa suntingan draf. Dipakai renderer, editor draf, penerbitan, dan halaman publik.
 * Teks default mengikuti lampiran "Rencana Operasi SAR Hari ke-1".
 */

declare(strict_types=1);

class RenopsDokumenService {
    public const KOTA = 'Kendari';
    public const TUGAS_DEFAULT = 'Pencarian, pertolongan, dan evakuasi';
    public const AREA_DEFAULT = 'Melaksanakan koordinasi bersama potensi SAR dan melaksanakan pencarian di sekitar LKP duga.';
    private const SATKER_DEFAULT = 'Kantor Pencarian dan Pertolongan Kelas A Kendari';
    // Grup tabel aset per matra kategori alut (urutan baris mengikuti AlutKategori::sort).
    private const GRUP_ASET = [AlutKategori::DARAT => 'Darat', AlutKategori::LAUT => 'Laut', AlutKategori::UDARA => 'Udara'];
    /** SAR Coordinator bawaan (Kepala Basarnas) bila belum ada dokumen sebelumnya yang mengisinya; tetap bisa disunting. */
    public const SC_DEFAULT = 'MARSEKAL MADYA TNI Dr.Mohammad Syafii, S.I.P., M.M., CHRMP.';
    private const STRUKTUR_JABATAN = ['sc', 'smc', 'osc', 'staf_operasi', 'staf_admin', 'staf_logistik', 'staf_intelijen', 'staf_humas', 'staf_komunikasi'];
    private const LIST_KEYS = ['koordinasi', 'koordinasi_instansi', 'persiapan_personel', 'sarana', 'peralatan', 'administrasi', 'logistik'];
    private const ROW_KEYS = [
        'data_kejadian' => ['label', 'value'],
        'aset' => ['grup', 'aset', 'jumlah'],
        'sru' => ['unsur', 'tugas', 'area', 'pola', 'track_spacing'],
    ];

    public static function resolve(int $idRenops): ?array {
        $renops = RenopsModel::getById($idRenops);
        $ops = $renops ? OperasiModel::getById((int)$renops['id_operasi']) : null;
        if (!$ops) {
            return null;
        }
        $idOps = (int)$ops['id_operasi'];
        $sumber = HierarchyHelper::resolveRenopsSources($ops, $renops);
        $settings = SettingsModel::get();
        $smc = PejabatModel::getDefaultSmc() ?? [];

        $sections = self::defaultSections($ops, $renops, $sumber);
        $over = RenopsDokumenModel::getDraft($idRenops)['konten']['sections'] ?? [];
        $over = is_array($over) ? $over : [];
        $edited = [];
        foreach ($sections as $key => $val) {
            $ov = array_key_exists($key, $over) ? self::sesuaiBentuk($key, $over[$key]) : null;
            if ($ov === null) {
                continue;
            }
            // Struktur yang disunting sebagian tetap berbentuk lengkap.
            $sections[$key] = $key === 'struktur' ? array_replace($val, $ov) : $ov;
            $edited[] = $key;
        }
        // R9: kotak SRU di struktur selalu mengikuti tabel SRU final; keterangan suntingan dibawa per nama.
        $ket = array_column($sections['struktur']['sru'], 'keterangan', 'nama');
        $sections['struktur']['sru'] = array_map(fn($r) => ['nama' => $r['unsur'], 'keterangan' => $ket[$r['unsur']] ?? ''], $sections['sru']);

        // Peta Lokasi (LKP): 1x per operasi, sama untuk semua hari RENOPS.
        $snap = DokumenPetaSnapshotModel::getSnapshot($idOps, 'RENOPS');
        $peta = ['path' => !empty($snap['image_exists']) ? ltrim((string)$snap['image_path'], '/') : null];
        // Peta Rencana Operasi: capture khusus hari ini, tanpa cadangan dari dokumen lain.
        $snapRencana = DokumenPetaSnapshotModel::getSnapshot($idOps, 'RENOPS_RENCANA', $idRenops);
        $petaRencana = ['path' => !empty($snapRencana['image_exists']) ? ltrim((string)$snapRencana['image_path'], '/') : null];

        $foto = array_map(fn($f) => [
            'id_foto' => (int)$f['id_foto'],
            'path' => (string)$f['path'],
            'keterangan' => (string)$f['keterangan'],
        ], RenopsFotoModel::listByRenops($idRenops));

        $tanggal = substr((string)$renops['tanggal_laporan'], 0, 10);
        $doc = [
            'meta' => [
                'id_renops' => $idRenops,
                'id_operasi' => $idOps,
                'hari_num' => (int)$sumber['hari_num'],
                'hari_label' => (string)$sumber['hari_label'],
                'tanggal' => $tanggal,
                'nomor' => TemplateHelper::generateNomorRenops($ops, $renops, $settings['satker_kode'] ?? 'SAR-304'),
                'satker_nama' => self::satker($settings),
                'kota' => self::KOTA,
                'jenis_kejadian' => trim((string)($ops['jenis_kejadian'] ?? '')),
                'nama_operasi' => trim((string)($ops['nama_operasi'] ?? '')),
                'closed' => ($ops['status'] ?? '') === 'CLOSED',
                'source_hash' => '', // diisi sesudah dokumen lengkap
            ],
            'sections' => $sections,
            'edited' => $edited,
            'peta' => $peta,
            'peta_rencana' => $petaRencana,
            'foto' => $foto,
            'kontak' => [
                'telp' => (string)($settings['no_telp'] ?? ''),
                'email' => (string)($settings['email'] ?? ''),
                'alamat' => (string)($settings['alamat'] ?? ''),
            ],
            'pengesahan' => [
                'kota' => self::KOTA,
                'tanggal' => $tanggal,
                'smc_nama' => (string)($smc['nama'] ?? ''),
                'smc_pangkat' => (string)($smc['pangkat'] ?? ''),
                'smc_nip' => (string)($smc['nip'] ?? ''),
            ],
        ];
        // R14: seluruh isi tercetak; 'closed' dikecualikan (menutup operasi bukan perubahan isi).
        // Foto lengkap (bukan hanya id) agar perubahan keterangan/urutan ikut terdeteksi.
        $doc['meta']['source_hash'] = hash('sha256', (string)json_encode([
            array_diff_key($doc['meta'], ['source_hash' => 0, 'closed' => 0]),
            $sections, $peta['path'], $foto, $doc['kontak'], $doc['pengesahan'],
            // Hanya bila ada: hash dokumen lama tanpa peta rencana tetap sama (tidak memunculkan "belum terbit").
            ...($petaRencana['path'] !== null ? [$petaRencana['path']] : []),
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        return $doc;
    }

    /** Nilai default seluruh bagian (urutan kunci = urutan kontrak). */
    public static function defaultSections(array $ops, array $renops, array $sumber): array {
        $v = fn(string $k) => trim((string)($ops[$k] ?? ''));
        $satker = self::satker(SettingsModel::get());
        $jenis = $v('jenis_kejadian');
        $nama = $v('nama_operasi');
        $n = (int)$sumber['hari_num'];
        $label = (string)$sumber['hari_label'];
        $unsur = is_array($sumber['unsur'] ?? null) ? $sumber['unsur'] : [];
        $unsurNama = array_values(array_unique(array_filter(array_map(fn($u) => trim((string)($u['nama_unsur'] ?? '')), $unsur), 'strlen')));
        $personel = array_sum(array_map(fn($u) => max(0, (int)($u['jumlah_personil'] ?? 0)), $unsur));
        $korban = array_values(array_filter($ops['korban'] ?? [], 'is_array'));
        $stats = TemplateHelper::hitungStatistikKorban($korban, (int)($ops['jumlah_pob'] ?? 0));
        $isiLapbang = trim(str_replace(["\r\n", "\r"], "\n", (string)($sumber['lapbang']['isi_laporan'] ?? '')));
        $hn = $n >= 2 && $isiLapbang !== '';

        // 1.a Awareness
        $ketPelapor = array_filter([$v('hubungan_pelapor'), $v('no_telp_pelapor') !== '' ? 'telepon ' . $v('no_telp_pelapor') : '']);
        $dari = $v('nama_pelapor') !== '' ? ' dari ' . $v('nama_pelapor') . ($ketPelapor ? ' (' . implode(', ', $ketPelapor) . ')' : '') : '';
        $wLapor = self::waktu($ops['waktu_lapor'] ?? null);
        $wKejadian = self::waktu($ops['waktu_kejadian'] ?? null);
        $unit = self::unitDefault();
        $lokasi = array_filter([
            $v('lokasi_kejadian') !== '' ? 'berada di ' . $v('lokasi_kejadian') : '',
            $v('koordinat') !== '' ? 'diduga pada koordinat ' . $v('koordinat') : '',
            $v('jarak') !== '' ? 'berjarak sekitar ' . $v('jarak') . ($unit !== '' ? " dari {$unit}" : '') : '',
        ]);
        $awareness = ($wLapor !== '' ? "Pada tanggal {$wLapor}, " : '') . "{$satker} menerima informasi{$dari} bahwa"
            . ($wKejadian !== '' ? " pada tanggal {$wKejadian}" : '') . ' telah terjadi ' . ($jenis ?: 'musibah')
            . ($nama !== '' ? ", yaitu {$nama}" : '') . '.'
            . ($lokasi ? ' Lokasi kejadian ' . implode(', ', $lokasi) . '.' : '');

        // Tabel 1: identitas korban
        $identitas = [];
        foreach ($korban as $i => $k) {
            $umur = trim((string)($k['umur'] ?? ''));
            $identitas[] = (count($korban) > 1 ? ($i + 1) . '. ' : '') . 'Nama: ' . (trim((string)($k['nama_korban'] ?? '')) ?: '-');
            $identitas[] = 'Jenis Kelamin: ' . self::jenisKelamin((string)($k['jk'] ?? ''));
            $identitas[] = 'Umur: ' . ($umur === '' ? '-' : (ctype_digit($umur) ? "{$umur} tahun" : $umur));
            $identitas[] = 'Alamat: ' . (trim((string)($k['alamat'] ?? '')) ?: '-');
        }

        $upaya = $hn ? $isiLapbang : "{$satker} telah berkoordinasi dengan " . self::daftar(['BCC', ...$unsurNama, 'keluarga korban']) . '.';
        $dataKejadian = [
            ['label' => 'Jenis Musibah', 'value' => $jenis ?: '-'],
            ['label' => 'Lokasi Duga Kejadian', 'value' => $v('koordinat') ?: ($v('lokasi_kejadian') ?: '-')],
            ['label' => 'Waktu Kejadian', 'value' => self::waktu($ops['waktu_kejadian'] ?? null, ', ') ?: '-'],
            ['label' => 'Penyebab', 'value' => $stats['dp'] + $stats['h'] > 0 ? 'Hilang' : '-'],
            ['label' => 'Objek yang Dicari', 'value' => $stats['total'] > 0 ? self::orang($stats['total']) : '-'],
            ['label' => 'Upaya yang Telah Dilakukan', 'value' => 'Berkoordinasi dengan ' . self::daftar(['Basarnas Command Center (BCC)', ...$unsurNama])],
            ['label' => 'Jumlah Korban dalam Pencarian', 'value' => $stats['dp'] > 0 ? self::orang($stats['dp']) : 'Nihil'],
            ['label' => 'Catatan', 'value' => $identitas ? "Identitas Korban:\n" . implode("\n", $identitas) : '-'],
        ];

        // Sarana/peralatan & tabel aset (hanya Sarana)
        $alut = is_array($sumber['alut'] ?? null) ? $sumber['alut'] : [];
        $split = self::splitAlut($alut);
        $aset = [];
        foreach (AlutKategori::sort($alut) as $a) {
            $namaA = trim((string)($a['nama_alut'] ?? ''));
            $grup = self::GRUP_ASET[AlutKategori::resolve($a)] ?? null;
            if ($namaA === '' || $grup === null) {
                continue;
            }
            $jml = (int)($a['jumlah_alut'] ?? 1);
            $satuan = mb_strtolower(trim((string)($a['satuan'] ?? '')) ?: 'unit');
            $aset[] = ['grup' => $grup, 'aset' => $namaA, 'jumlah' => $jml > 0 ? self::angka($jml) . " {$satuan}" : '-'];
        }

        // SRU hari N: tabel penugasan, kalimat area, kotak struktur
        $sru = $area = $sruStruktur = [];
        foreach ($renops['srus'] ?? [] as $s) {
            $namaS = trim((string)($s['nama_sru'] ?? ''));
            if ($namaS === '') {
                continue;
            }
            $pola = trim((string)($s['pola_pencarian'] ?? ''));
            $luas = trim((string)($s['luas_area'] ?? ''));
            $koor = trim((string)($s['koordinat_area'] ?? ''));
            $sru[] = [
                'unsur' => $namaS,
                'tugas' => self::TUGAS_DEFAULT,
                'area' => implode(', ', array_filter([$luas, $koor], 'strlen')) ?: '-',
                'pola' => $pola ?: '-',
                'track_spacing' => trim((string)($s['track_spacing'] ?? '')) ?: '-',
            ];
            $area[] = "{$namaS} melaksanakan pencarian" . ($pola !== '' ? " dengan pola {$pola}" : '')
                . ($luas !== '' ? " pada area seluas {$luas}" : '') . ($koor !== '' ? " di koordinat {$koor}" : '') . '.';
            $sruStruktur[] = ['nama' => $namaS, 'keterangan' => ''];
        }

        // 4. Operation stage
        $tim = $v('tim_berangkat') ?: implode(', ', array_filter(array_map(fn($t) => trim((string)($t['nama_tim'] ?? '')), $ops['detail_tim_berangkat'] ?? []), 'strlen'));
        if ($n <= 1) {
            $wBerangkat = self::waktu($ops['waktu_berangkat'] ?? null);
            $stage = ($wBerangkat !== '' ? "Pada tanggal {$wBerangkat}, diberangkatkan " : 'Diberangkatkan ')
                . ($personel > 0 ? self::orang($personel) . ' ' : '') . 'personel' . ($tim !== '' ? " {$tim}" : '') . '.';
        } else {
            $wMulai = self::waktu($renops['waktu_mulai_ops'] ?? null);
            $stage = ($isiLapbang !== '' ? "{$isiLapbang}\n\n" : '') . ($wMulai !== '' ? "Pada tanggal {$wMulai}, " : '')
                . "Operasi SAR Hari ke-{$label} dilanjutkan" . ($personel > 0 ? ' dengan ' . self::orang($personel) . ' personel' : '') . '.';
        }

        $wMulaiOps = self::waktu($renops['waktu_mulai_ops'] ?? null, ', ');
        return [
            'awareness' => $awareness,
            'kronologi' => trim(str_replace(["\r\n", "\r"], "\n", $v('kronologi'))) ?: '-',
            'jenis_operasi' => $jenis ?: '-',
            'data_kejadian' => $dataKejadian,
            'upaya_sebelumnya' => $upaya,
            'koordinasi' => ['Menyampaikan laporan musibah kepada BCC.', 'Berkoordinasi dengan:'],
            'koordinasi_instansi' => array_values(array_unique(['BCC', ...$unsurNama])),
            'persiapan_personel' => ['Menyiapkan rescuer yang sedang siaga;', 'Menghubungi rescuer yang berstatus on call; dan', 'Menyiapkan peralatan.'],
            'sarana' => $split['sarana'],
            'peralatan' => $split['peralatan'],
            'mission' => 'Melaksanakan Operasi SAR' . ($jenis !== '' ? " pada {$jenis}" : '') . ($nama !== '' ? ", yaitu {$nama}" : '') . '.',
            'waktu' => "Operasi SAR Hari ke-{$label} dilaksanakan" . ($wMulaiOps !== '' ? " pada tanggal {$wMulaiOps}" : '') . ' sampai dengan selesai.',
            'area_pencarian' => $area ? implode("\n", $area) : self::AREA_DEFAULT,
            'aset' => $aset,
            'sru' => $sru,
            'rescue_plan' => 'Korban yang ditemukan dievakuasi ke puskesmas terdekat dan diserahterimakan kepada keluarga korban.',
            'briefing' => 'Briefing dan debriefing disampaikan secara langsung serta melalui radio VHF dan telepon.',
            'operation_stage' => $stage,
            'administrasi' => ['Buku jurnal catatan pergerakan unsur;', 'Catatan data unsur yang terlibat;', 'Data personel yang terlibat;', 'Surat Perintah Tugas; dan', 'Dokumen pendukung lainnya.'],
            'logistik' => ['Makanan darurat;', 'Bahan bakar;', 'Air mineral untuk menjaga daya tahan tubuh; dan', 'Kebutuhan logistik lainnya.'],
            'struktur' => self::defaultStruktur($ops, $renops, $n, $sruStruktur),
        ];
    }

    /** Sarana = kategori ALUT_* (Darat/Laut/Udara), Peralatan = PALSAR. Urutan masukan dipertahankan. @return array{sarana: list<string>, peralatan: list<string>} */
    public static function splitAlut(array $alut): array {
        $out = ['sarana' => [], 'peralatan' => []];
        foreach ($alut as $a) {
            $nama = trim((string)($a['nama_alut'] ?? ''));
            if ($nama !== '') {
                $out[AlutKategori::isAlut(AlutKategori::resolve($a)) ? 'sarana' : 'peralatan'][] = $nama;
            }
        }
        return $out;
    }

    /**
     * Suntingan draf dipakai hanya bila bentuknya sama dengan default (teks / daftar teks / baris tabel / struktur).
     * Baris tabel hanya membawa kolom kontrak. null = abaikan suntingan.
     */
    private static function sesuaiBentuk(string $key, mixed $val): array|string|null {
        if ($key === 'struktur') {
            if (!is_array($val) || ($val !== [] && array_is_list($val))) {
                return null;
            }
            $out = array_map(fn($x) => is_scalar($x) ? (string)$x : '', array_intersect_key($val, array_flip(self::STRUKTUR_JABATAN)));
            if (array_key_exists('sru', $val)) {
                $sru = is_array($val['sru']) && array_is_list($val['sru']) ? self::baris($val['sru'], ['nama', 'keterangan']) : null;
                if ($sru === null) {
                    return null;
                }
                $out['sru'] = $sru;
            }
            return $out;
        }
        if (isset(self::ROW_KEYS[$key])) {
            return is_array($val) && array_is_list($val) ? self::baris($val, self::ROW_KEYS[$key]) : null;
        }
        if (in_array($key, self::LIST_KEYS, true)) {
            return is_array($val) && array_is_list($val) && !array_filter($val, fn($x) => !is_scalar($x)) ? array_map('strval', $val) : null;
        }
        return is_string($val) ? $val : null;
    }

    /** Baris tabel -> hanya kolom $cols (string); null bila ada baris yang bukan objek. */
    private static function baris(array $rows, array $cols): ?array {
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) {
                return null;
            }
            $out[] = array_map(fn($c) => is_scalar($r[$c] ?? null) ? (string)$r[$c] : '', array_combine($cols, $cols));
        }
        return $out;
    }

    /**
     * Jabatan disalin dari dokumen RENOPS hari terdekat sebelumnya (TERBIT lalu DRAF), lalu versi terbit
     * terbaru di sistem sebelum RENOPS ini dibuat (R11). SMC = pejabat SMC default bila ada. SRU selalu dari SRU hari N.
     */
    private static function defaultStruktur(array $ops, array $renops, int $n, array $sruStruktur): array {
        $idRenops = (int)$renops['id_renops'];
        $calon = [];
        foreach ($ops['renops'] ?? [] as $r) {
            $d = HierarchyHelper::absoluteDayNumber(TemplateHelper::hitungHariKe($ops, $r['tanggal_laporan'] ?? null, $r['hari_ke'] ?? null));
            if ($d < $n && (int)$r['id_renops'] !== $idRenops) {
                $calon[(int)$r['id_renops']] = $d;
            }
        }
        arsort($calon);
        $src = null;
        foreach (array_keys($calon) as $id) {
            $src = RenopsDokumenModel::getLatestTerbit($id)['konten']['sections']['struktur']
                ?? RenopsDokumenModel::getDraft($id)['konten']['sections']['struktur'] ?? null;
            if (is_array($src)) {
                break;
            }
        }
        if (!is_array($src)) {
            $src = RenopsDokumenModel::latestStruktur($idRenops, (string)($renops['created_at'] ?? '')) ?? [];
        }

        $out = [];
        foreach (self::STRUKTUR_JABATAN as $k) {
            $out[$k] = is_scalar($src[$k] ?? null) ? (string)$src[$k] : '';
        }
        $smc = trim((string)(PejabatModel::getDefaultSmc()['nama'] ?? ''));
        if ($smc !== '') {
            $out['smc'] = $smc;
        }
        if (trim($out['sc']) === '') {
            $out['sc'] = self::SC_DEFAULT;
        }
        $out['sru'] = $sruStruktur;
        return $out;
    }

    /** "21 Oktober 2026{sep}pukul 06.30 WITA" (format lampiran), '' bila waktu tidak valid. */
    private static function waktu(?string $dt, string $sep = ' '): string {
        if (TemplateHelper::parseToTimestamp($dt) === null) {
            return '';
        }
        return TemplateHelper::formatTanggalSimple($dt) . "{$sep}pukul " . str_replace(':', '.', TemplateHelper::formatJamWITA($dt)) . ' WITA';
    }

    private static function angka(int $n): string {
        return "{$n} (" . TemplateHelper::terbilang($n) . ')';
    }

    private static function orang(int $n): string {
        return self::angka($n) . ' orang';
    }

    /** Rincian gaya Indonesia: "A", "A dan B", "A, B, dan C". */
    private static function daftar(array $items): string {
        $items = array_values($items);
        $last = (string)array_pop($items);
        return $items ? implode(', ', $items) . (count($items) > 1 ? ',' : '') . " dan {$last}" : $last;
    }

    private static function jenisKelamin(string $jk): string {
        return match (strtoupper(trim($jk))) {
            'L' => 'Laki-laki',
            'P' => 'Perempuan',
            '' => '-',
            default => trim($jk),
        };
    }

    private static function satker(array $settings): string {
        return trim((string)($settings['satker_nama'] ?? '')) ?: self::SATKER_DEFAULT;
    }

    private static function unitDefault(): string {
        $units = SarUnitModel::getAll();
        foreach ($units as $u) {
            if (!empty($u['is_default'])) {
                return trim((string)($u['nama_unit'] ?? ''));
            }
        }
        return trim((string)($units[0]['nama_unit'] ?? ''));
    }
}
