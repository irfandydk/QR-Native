<?php
/**
 * QR-SAR - BRA SAR Helper (Berita Radio SAR & Telegram Formatter)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class BraHelper {
    public static function formatMilitaryTW(?string $dateInput = null, string $zone = 'H'): string {
        $timestamp = $dateInput ? strtotime($dateInput) : time();
        if ($timestamp === false) $timestamp = time();

        $mm = date('m', $timestamp);
        $dd = date('d', $timestamp);
        $hh = date('H', $timestamp);
        $min = date('i', $timestamp);
        $zoneSuffix = $zone !== '' ? ' ' . trim($zone) : '';

        return "{$mm}{$dd} {$hh}{$min}{$zoneSuffix}";
    }

    public static function getBulanTahunSuffix(?string $dateInput = null): string {
        $timestamp = $dateInput ? strtotime($dateInput) : time();
        if ($timestamp === false) $timestamp = time();
        return date('my', $timestamp); // e.g. "0926"
    }

    public static function parseNoBra(string $noBra, string $defaultSatker = 'SAR-304'): array {
        $clean = trim($noBra);
        $nomorUrut = 1;
        $satker = $defaultSatker ?: 'SAR-304';
        $bulanTahun = self::getBulanTahunSuffix();

        if (preg_match('#^(\d+)(?:/([^/]+)/(\d+))?#', $clean, $m)) {
            $nomorUrut = (int)$m[1];
            if (!empty($m[2])) $satker = trim($m[2]);
            if (!empty($m[3])) $bulanTahun = trim($m[3]);
        }

        return [
            'nomor_urut' => $nomorUrut,
            'satker_kode' => $satker,
            'bulan_tahun' => $bulanTahun,
            'no_bra' => $clean
        ];
    }

    public static function getNextNomorBra(string $kategori = 'MAPEL', string $satkerKode = 'SAR-304', ?PDO $db = null, bool $forUpdate = false): array {
        $db = $db ?: getDB();
        $suffix = self::getBulanTahunSuffix();
        $cleanSatker = $satkerKode ?: 'SAR-304';

        if ($forUpdate) {
            // Penguncian baris eksklusif (exclusive index row lock) pada nomor_urut tertinggi
            $stmt = $db->query("SELECT nomor_urut FROM BraSar ORDER BY nomor_urut DESC LIMIT 1 FOR UPDATE");
            $row = $stmt ? $stmt->fetch() : null;
            $maxUrut = ($row && isset($row['nomor_urut'])) ? (int)$row['nomor_urut'] : 0;
        } else {
            $stmt = $db->query("SELECT COALESCE(MAX(nomor_urut), 0) as max_urut FROM BraSar");
            $row = $stmt ? $stmt->fetch() : null;
            $maxUrut = ($row && isset($row['max_urut'])) ? (int)$row['max_urut'] : 0;
        }

        $nextUrut = $maxUrut + 1;

        // Loop proteksi tabrakan nomor naskah (menghindari duplikasi)
        do {
            $noBra = "{$nextUrut}/{$cleanSatker}/{$suffix}";
            $stmtChk = $db->prepare("SELECT id_bra FROM BraSar WHERE no_bra = :no LIMIT 1" . ($forUpdate ? " FOR UPDATE" : ""));
            $stmtChk->execute([':no' => $noBra]);
            if (!$stmtChk->fetch()) {
                break;
            }
            $nextUrut++;
        } while (true);

        return [
            'nomor_urut' => $nextUrut,
            'satker_kode' => $cleanSatker,
            'bulan_tahun' => $suffix,
            'no_bra' => $noBra
        ];
    }

    /**
     * Daftar 3 Kapal Resmi Basarnas Kendari untuk BRA Pergerakan Kapal (ISSUE-10)
     */
    public static function getAvailableVessels(): array {
        return [
            'KN_SAR_PACITAN' => [
                'id_alut' => 18,
                'key' => 'KN_SAR_PACITAN',
                'nama' => 'KN SAR PACITAN',
                'nama_resmi' => 'KN SAR PACITAN 102',
                'panggilan' => 'YCSB',
                'tipe_alut' => 'KAPAL SAR KELAS I (ALUMINIUM ALLOY)',
                'loa' => '59,80 METER',
                'speed_max' => '20 KNOT',
                'speed_eco' => '15 KNOT',
                'endurance' => '48 JAM / 70%',
                'pangkalan' => 'DERMAGA UTAMA BASARNAS BUNGKUTOKO KENDARI',
                'nakhoda' => 'RUSLAN, S.T.',
                'pob_default' => 40,
                'crew_default' => 20,
                'rescuer_default' => 20,
                'palsar_alkom' => 'SEA RIDER 1 UNIT (40 PK) READY KMA SEKOCI 2 UNIT READY KMA ALAT SELAM 4 SET READY KMA RADAR AIS ECDIS READY TTK'
            ],
            'RB_210' => [
                'id_alut' => 3,
                'key' => 'RB_210',
                'nama' => 'RESCUE BOAT 210',
                'nama_resmi' => 'RESCUE BOAT 210 KENDARI',
                'panggilan' => 'YCSB-210',
                'tipe_alut' => 'KAPAL SAR KELAS II (MONOHULL)',
                'loa' => '36 METER',
                'speed_max' => '26 KNOT',
                'speed_eco' => '18 KNOT',
                'endurance' => '14.500 LITER BBM / 6.000 LITER AIR TAWAR',
                'pangkalan' => 'DERMAGA BASARNAS KAWASAN KENDARI',
                'nakhoda' => 'JUMARDIN, S.E.',
                'pob_default' => 16,
                'crew_default' => 12,
                'rescuer_default' => 4,
                'palsar_alkom' => 'RUBBER BOAT 1 UNIT (25 PK) KMA LIFE JACKET 20 BUAH KMA ALAT SELAM 2 SET KMA VHF MARINE RADAR GPS AIS READY TTK'
            ],
            'RB_307' => [
                'id_alut' => 4,
                'key' => 'RB_307',
                'nama' => 'RESCUE BOAT 307',
                'nama_resmi' => 'RESCUE BOAT 307 KENDARI',
                'panggilan' => 'YCSB-307',
                'tipe_alut' => 'KAPAL SAR KELAS II/III (MONOHULL)',
                'loa' => '28 METER',
                'speed_max' => '25 KNOT',
                'speed_eco' => '16 KNOT',
                'endurance' => '12.000 LITER BBM / 4.000 LITER AIR TAWAR',
                'pangkalan' => 'DERMAGA BASARNAS KENDARI / POS SAR BAUBAU',
                'nakhoda' => 'ERIK PRATAMA, S.Sos.',
                'pob_default' => 14,
                'crew_default' => 10,
                'rescuer_default' => 4,
                'palsar_alkom' => 'RUBBER BOAT 1 UNIT (25 PK) KMA LIFE JACKET 15 BUAH KMA ALAT SELAM 2 SET KMA VHF MARINE RADAR GPS AIS READY TTK'
            ]
        ];
    }

    /**
     * Membangun naskah telegram resmi militer untuk pergerakan kapal (ISSUE-10)
     */
    public static function buildSectionsFromVesselMovement(string $vesselKey, string $movementType = 'TOLAK', array $customParams = []): array {
        $vessels = self::getAvailableVessels();
        $normalizedKey = strtoupper(trim(str_replace([' ', '-'], '_', $vesselKey)));

        if (str_contains($normalizedKey, 'PACITAN')) {
            $key = 'KN_SAR_PACITAN';
        } elseif (str_contains($normalizedKey, '307')) {
            $key = 'RB_307';
        } elseif (str_contains($normalizedKey, '210')) {
            $key = 'RB_210';
        } else {
            $key = 'KN_SAR_PACITAN';
        }

        $v = $vessels[$key];
        $tw = !empty($customParams['tw']) ? $customParams['tw'] : self::formatMilitaryTW();
        $movement = strtoupper(trim($movementType));

        $namaKapal = $v['nama_resmi'];
        $callSign = $v['panggilan'];
        $pangkalan = $v['pangkalan'];

        if ($movement === 'SANDAR') {
            $perihal = "PERGERAKAN {$namaKapal} TIBA DAN SANDAR DI {$pangkalan} TTK";
            $derajat = 'RUTIN';
            $sections = [
                [
                    'kode' => 'AAA',
                    'isi' => "PADA TW {$tw} KMA {$namaKapal} TELAH TIBA DAN SANDAR AMAN DI {$pangkalan} SETELAH MELAKSANAKAN KEGIATAN OPERASI SAR / PATROLI DI PERAIRAN SULAWESI TENGGARA TTK"
                ],
                [
                    'kode' => 'BBB',
                    'isi' => "STATUS ALUT PASCA PELAYARAN TTK DUA\n• KONDISI MESIN / LAMBUNG : BAIK DAN NORMAL\n• KESIAPSIAGAAN : SIAP DITUGASKAN KEMBALI SEWAKTU-WAKTU (STANDBY PANGKALAN)\n• SISA BBM & AIR TAWAR : AMAN / CUKUP UNTUK SIAGA KEDARURATAN TTK"
                ],
                [
                    'kode' => 'CCC',
                    'isi' => "SELURUH CREW DAN RESCUER LENGKAP DALAM KEADAAN SEHAT KMA DMKN SBG LAPORAN KMA NAKHODA {$namaKapal} KRM TTK HBS"
                ]
            ];
        } elseif ($movement === 'PANTAUAN') {
            $perihal = "LAPORAN PERKEMBANGAN POSISI {$namaKapal} DI PERAIRAN SULAWESI TENGGARA TTK";
            $derajat = 'SGR';
            $sections = [
                [
                    'kode' => 'AAA',
                    'isi' => "PADA TW {$tw} KMA POSISI {$namaKapal} BERADA PADA KOORDINAT 03° 58' 30\" S - 122° 38' 15\" E KMA HALUAN 135° KMA KECEPATAN {$v['speed_eco']} MENUJU SEKTOR PENCARIAN / PATROLI TTK"
                ],
                [
                    'kode' => 'BBB',
                    'isi' => "KONDISI METEOROLOGI DAN LAUT TTK DUA\n• CUACA : CERAH BERAWAN\n• ANGIN : TIMUR LAUT - TENGGARA, KECEPATAN 8 - 15 KNOTS\n• TINGGI GELOMBANG : 0.5 - 1.25 METER\n• JARAK PANDANG : BAIK (VISIBILITY 8 - 10 NM)\n\nKONDISI MATERIAL DAN PERSONIL TTK DUA\nSELURUH CREW DAN RESCUER LENGKAP {$v['pob_default']} ORANG DALAM KEADAAN SEHAT KMA ALUT DAN PERALATAN KAPAL BERFUNGSI NORMAL TTK"
                ],
                [
                    'kode' => 'CCC',
                    'isi' => "PELAYARAN DILANJUTKAN SESUAI RENCANA OPERASI KMA NAKHODA {$namaKapal} KRM TTK HBS"
                ]
            ];
        } else {
            // Default: TOLAK (Keberangkatan / Berlayar)
            $perihal = "PELAYARAN {$namaKapal} DALAM RANGKA BKO / OPERASI SAR DI SEKITAR PERAIRAN SULAWESI TENGGARA TTK";
            $derajat = 'SGR';
            $sections = [
                [
                    'kode' => 'AAA',
                    'isi' => "PADA TW {$tw} KMA PERINTAH/RENCANA PELAYARAN {$namaKapal} DARI {$pangkalan} MENUJU LKP/AREA OPERASI DI SEKITAR PERAIRAN SULTRA DALAM RANGKA PELAKSANAAN OPERASI SAR TTK"
                ],
                [
                    'kode' => 'BBB',
                    'isi' => "DATA KAPAL TTK DUA\nNAMA : {$namaKapal}\nCALL SIGN : {$callSign}\nNAKHODA : {$v['nakhoda']}\nLOA : {$v['loa']}\nTYPE : {$v['tipe_alut']}\nSPEED MAX : {$v['speed_max']}\nSPEED ECO : {$v['speed_eco']}\nENDURANCE : {$v['endurance']}\nKONDISI MESIN : BAIK / SIAP OPERASI\n\nREN LAYAR TTK DUA\nRUTE : {$pangkalan} MENUJU PERAIRAN SULAWESI TENGGARA\nESTIMASI : 4 JAM PERJALANAN\n\nMUATAN POB TTK DUA\nTOTAL POB : {$v['pob_default']} ORANG (CREW {$v['crew_default']} ORANG + RESCUER {$v['rescuer_default']} ORANG)\n\nDATA PALSAR ALKOM ALNAV TTK DUA\n{$v['palsar_alkom']}"
                ],
                [
                    'kode' => 'CCC',
                    'isi' => "LAPORAN PERKEMBANGAN MENYUSUL KMA NAKHODA {$namaKapal} KRM TTK HBS"
                ]
            ];
        }

        return [
            'vessel_key' => $key,
            'kategori' => 'PERGERAKAN_KAPAL',
            'perihal' => $perihal,
            'derajat' => $derajat,
            'panggilan' => $callSign,
            'aksi' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
            'info' => 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN KMA DIREKTUR SARPRAS KMA DIREKTUR KESIAPSIAGAAN',
            'dari' => 'KANSAR KENDARI',
            'sections' => $sections
        ];
    }

    public static function getDefaultTemplate(string $kategori = 'MAPEL', string $noBra = '', int $nomorUrut = 1, string $satker = 'SAR-304', string $bulanTahun = ''): array {
        if ($kategori === 'PERGERAKAN_KAPAL') {
            return self::buildSectionsFromVesselMovement('KN_SAR_PACITAN', 'TOLAK');
        }

        return [
            'aksi' => '',
            'info' => '',
            'perihal' => '',
            'sections' => [
                ['kode' => 'AAA', 'isi' => ''],
                ['kode' => 'BBB', 'isi' => ''],
                ['kode' => 'CCC', 'isi' => ''],
            ]
        ];
    }

    public static function countBraWords(array $sections, string $perihal = ''): int {
        $combined = trim($perihal);
        if ($combined !== '') {
            $combined .= ' ';
        }
        foreach ($sections as $s) {
            $kode = trim((string)($s['kode_seksi'] ?? $s['kode'] ?? ''));
            $isi = trim((string)($s['isi_seksi'] ?? $s['isi'] ?? ''));
            if ($isi !== '') {
                if ($kode !== '') {
                    $combined .= $kode . ' ';
                }
                $combined .= $isi . ' ';
            }
        }
        $words = preg_split('/\s+/', trim($combined), -1, PREG_SPLIT_NO_EMPTY);
        return is_array($words) ? count($words) : 0;
    }

    public static function getSectionCode(int $index): string {
        $char = chr(65 + ($index % 26));
        return str_repeat($char, 3);
    }

    public static function buildSectionsFromLaporanAwal(array $ops): array {
        $twLapor = !empty($ops['waktu_lapor']) ? self::formatMilitaryTW($ops['waktu_lapor']) : self::formatMilitaryTW();
        $twBerangkat = !empty($ops['waktu_berangkat']) ? self::formatMilitaryTW($ops['waktu_berangkat']) : self::formatMilitaryTW();

        $pelapor = strtoupper($ops['nama_pelapor'] ?: 'BAPAK POLSEK');
        $hub = strtoupper($ops['hubungan_pelapor'] ?: 'INSTANSI TERKAIT');
        $hp = !empty($ops['no_telp_pelapor']) ? "HP {$ops['no_telp_pelapor']}" : "HP. -";
        $infoPelapor = "{$pelapor} ({$hub} / {$hp})";

        $namaOps = strtoupper($ops['nama_operasi'] ?: 'KECELAKAAN KAPAL / KONDISI MEMBAHAYAKAN MANUSIA');
        $lokasi = strtoupper($ops['lokasi_kejadian'] ?: 'PERAIRAN SULAWESI TENGGARA');
        $koordinat = strtoupper($ops['koordinat'] ?: '03° 58\' 30" S - 122° 35\' 12" E');
        $timBerangkat = strtoupper($ops['tim_berangkat'] ?: 'TIM RESCUE KANSAR KENDARI');

        $alutRingkas = !empty($ops['alut_palsar'])
            ? strtoupper(str_replace("\n", ' KMA ', $ops['alut_palsar']))
            : '1 UNIT RESCUE CAR DAN 1 UNIT RUBBER BOAT';

        $eta = 'PADA KESEMPATAN PERTAMA';
        if (!empty($ops['eta_tiba'])) {
            $eta = 'TW ' . self::formatMilitaryTW($ops['eta_tiba']);
        } elseif (!empty($ops['eta_lokasi']) || !empty($ops['jarak'])) {
            $eta = trim("ESTIMASI " . ($ops['eta_lokasi'] ?: 'PERJALANAN') . " (JARAK ± " . ($ops['jarak'] ?: '') . ")");
        }

        $unsurText = !empty($ops['unsur_terlibat'])
            ? strtoupper($ops['unsur_terlibat'])
            : "* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD POLDA SULTRA : 3 ORANG\n* MASYARAKAT DAN KELUARGA : 5 ORANG";

        $cuaca = strtoupper($ops['cuaca'] ?: 'CERAH BERAWAN');
        $gelombang = strtoupper($ops['gelombang'] ?: '0.5 - 1.25 METER');
        $angin = strtoupper($ops['angin'] ?: 'TIMUR LAUT - TENGGARA, 5-15 KNOTS');

        $sections = [
            [
                'kode' => 'AAA',
                'isi' => "PADA TW {$twLapor} TERIMA INFO DARI {$infoPelapor} BAHWA TELAH TERJADI {$namaOps} DI {$lokasi} PADA KOORDINAT {$koordinat} TTK"
            ],
            [
                'kode' => 'BBB',
                'isi' => "TERSEBUT AAA DIATAS MAKA PADA TW {$twBerangkat} {$timBerangkat} DIBERANGKATKAN MENUJU LKP DENGAN MENGGUNAKAN {$alutRingkas} DENGAN ETA {$eta} TTK"
            ],
            [
                'kode' => 'CCC',
                'isi' => "UNSUR SAR TERLIBAT TTK DUA\n{$unsurText}\n\nALUT DAN PALSAR TTK DUA\n{$alutRingkas}\n\nKONDISI CUACA TTK DUA\n• CUACA : {$cuaca}\n• ANGIN : {$angin}\n• GELOMBANG : {$gelombang}"
            ],
            [
                'kode' => 'DDD',
                'isi' => "LAPORAN PERKEMBANGAN MENYUSUL KMA KA KPP KENDARI KRM TTK HBS"
            ]
        ];

        return [
            'sections' => $sections,
            'perihal' => "LAPORAN AWAL {$namaOps} TTK",
            'aksi' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
            'info' => 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
            'dari' => 'KANSAR KENDARI'
        ];
    }

    public static function buildSectionsFromRenops(array $ops, array $renops, array $srus = []): array {
        $hariKe = strtoupper($renops['hari_ke'] ?: 'H.1');
        $namaOps = strtoupper($ops['nama_operasi'] ?: 'OPERASI SAR');
        $perihal = "RENCANA OPERASI SAR {$hariKe} {$namaOps} TTK";

        $sruTextList = [];
        if (!empty($srus)) {
            foreach ($srus as $s) {
                $parts = [];
                if (!empty($s['nama_sru'])) $parts[] = "* " . strtoupper($s['nama_sru']);
                if (!empty($s['pola_pencarian'])) $parts[] = "POLA: " . strtoupper($s['pola_pencarian']);
                if (!empty($s['luas_area'])) $parts[] = "LUAS: " . strtoupper($s['luas_area']);
                if (!empty($s['koordinat_area'])) $parts[] = "AREA: " . strtoupper(str_replace("\n", ' / ', $s['koordinat_area']));
                $sruTextList[] = implode(' KMA ', $parts);
            }
        }
        $sruText = !empty($sruTextList)
            ? implode("\n", $sruTextList)
            : "* SRU 1: POLA PARALLEL TRACK SEARCH LUAS 10 NM² DISEPUTARAN LKP\n* SRU 2: PENYISIRAN PESISIR PANTAI";

        $unsurText = strtoupper($renops['unsur_text'] ?: ($ops['unsur_terlibat'] ?: "* RESCUER KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG"));
        $alutText = strtoupper($renops['alut_text'] ?: ($ops['alut_palsar'] ?: "• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT"));

        $twBriefing = self::formatMilitaryTW($renops['waktu_briefing'] ?? null);
        $twMulai = self::formatMilitaryTW($renops['waktu_mulai_ops'] ?? null);

        $cuaca = strtoupper($renops['cuaca'] ?: ($ops['cuaca'] ?: 'CERAH BERAWAN'));

        $sections = [
            [
                'kode' => 'AAA',
                'isi' => "PADA TW {$twBriefing} TIM SAR GABUNGAN MELAKSANAKAN BRIEFING PEMBAGIAN SEKTOR PENCARIAN RENCANA OPERASI SAR {$hariKe} TERHADAP {$namaOps} DENGAN WAKTU MULAI PERGERAKAN TW {$twMulai} TTK"
            ],
            [
                'kode' => 'BBB',
                'isi' => "RENCANA PEMBAGIAN SRU TTK DUA\n{$sruText}\n\nKONDISI METEOROLOGI / CUACA TTK DUA\n• CUACA : {$cuaca}"
            ],
            [
                'kode' => 'CCC',
                'isi' => "UNSUR SAR TERLIBAT TTK DUA\n{$unsurText}\n\nALUT DAN PALSAR TTK DUA\n{$alutText}"
            ],
            [
                'kode' => 'DDD',
                'isi' => "DMKN SBG RENCANA OPERASI {$hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS"
            ]
        ];

        return [
            'sections' => $sections,
            'perihal' => $perihal,
            'aksi' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
            'info' => 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
            'dari' => 'KANSAR KENDARI'
        ];
    }

    public static function buildSectionsFromSitrep(array $ops, array $sitrep, array $korban = []): array {
        $hariKe = strtoupper($sitrep['hari_ke'] ?: 'H.1');
        $namaOps = strtoupper($ops['nama_operasi'] ?: 'OPERASI SAR');
        $isPenutupan = ($sitrep['jenis_laporan'] ?? '') === 'PENUTUPAN';

        if ($isPenutupan) {
            return self::buildSectionsFromPenutupan($ops, $sitrep, $korban);
        }

        $perihal = "LAPORAN PERKEMBANGAN {$hariKe} {$namaOps} TTK";
        $stats = TemplateHelper::hitungStatistikKorban($korban);
        $isiLaporan = strtoupper($sitrep['isi_laporan'] ?: "PENCARIAN {$hariKe} TELAH DILAKSANAKAN SESUAI RENCANA OPERASI DENGAN HASIL KORBAN BELUM DITEMUKAN (NIHIL)");
        $laporTW = self::formatMilitaryTW($sitrep['waktu_laporan'] ?? null);
        $twu = self::formatMilitaryTW();

        $alutText = strtoupper($sitrep['alut_text'] ?: ($ops['alut_palsar'] ?: "• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT"));
        $unsurText = strtoupper($sitrep['unsur_text'] ?: ($ops['unsur_terlibat'] ?: "* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG"));

        $cuaca = strtoupper($sitrep['cuaca'] ?: ($ops['cuaca'] ?: 'CERAH BERAWAN'));
        $gelombang = strtoupper($ops['gelombang'] ?: '0.5 - 1.25 METER');

        // Dynamic basarnas military telegram structure: AAA Info Awal, BBB Alut & Palsar, CCC Unsur, DDD Rekap Korban, EEE Cuaca & Lanjutan, FFF Penutup
        $sections = [
            [
                'kode' => 'AAA',
                'isi' => "PADA TW {$laporTW} TERIMA INFO DARI OSC TIM SAR GABUNGAN BAHWA {$isiLaporan} TTK"
            ],
            [
                'kode' => 'BBB',
                'isi' => "ALUT DAN PALSAR DIKERAHKAN TTK DUA\n{$alutText}"
            ],
            [
                'kode' => 'CCC',
                'isi' => "UNSUR SAR TERLIBAT TTK DUA\n{$unsurText}"
            ],
            [
                'kode' => 'DDD',
                'isi' => "DATA REKAPITULASI KORBAN TTK DUA\n* KORBAN SELAMAT : {$stats['s']} ORANG\n* KORBAN MENINGGAL DUNIA : {$stats['md']} ORANG\n* KORBAN DALAM PENCARIAN (DP) : {$stats['dp']} ORANG\n* TOTAL POB : {$stats['total']} ORANG"
            ],
            [
                'kode' => 'EEE',
                'isi' => "KONDISI CUACA TTK DUA\n• CUACA : {$cuaca}\n• GELOMBANG : {$gelombang}\n\nPADA TW {$twu} PENCARIAN DIHENTIKAN SEMENTARA DENGAN HASIL NIHIL KMA OPERASI AKAN DILANJUTKAN KEMBALI BESOK PAGI PADA TW 0700 H TTK"
            ],
            [
                'kode' => 'FFF',
                'isi' => "DMKN SBG LAPORAN PERKEMBANGAN {$hariKe} KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS"
            ]
        ];

        return [
            'sections' => $sections,
            'perihal' => $perihal,
            'aksi' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
            'info' => 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
            'dari' => 'KANSAR KENDARI'
        ];
    }

    public static function buildSectionsFromPenutupan(array $ops, array $sitrep = [], array $korban = []): array {
        $namaOps = strtoupper($ops['nama_operasi'] ?: 'OPERASI SAR');
        $perihal = "LAPORAN PERKEMBANGAN DAN PENUTUPAN OPERASI SAR {$namaOps} TTK";

        $stats = TemplateHelper::hitungStatistikKorban($korban);
        $isiLaporan = strtoupper($sitrep['isi_laporan'] ?: "SELURUH KORBAN TELAH BERHASIL DIEVAKUASI DAN OPERASI SAR DINYATAKAN SELESAI");
        $twDebrief = !empty($sitrep['waktu_debrief']) ? self::formatMilitaryTW($sitrep['waktu_debrief']) : self::formatMilitaryTW();

        $alutText = strtoupper($sitrep['alut_text'] ?: ($ops['alut_palsar'] ?: "• 1 UNIT RESCUE CAR\n• 1 UNIT RUBBER BOAT"));
        $unsurText = strtoupper($sitrep['unsur_text'] ?: ($ops['unsur_terlibat'] ?: "* TIM RESCUE KANSAR KENDARI : 6 ORANG\n* POLAIRUD : 3 ORANG"));

        $sections = [
            [
                'kode' => 'AAA',
                'isi' => "PADA TW {$twDebrief} TIM SAR GABUNGAN MELAKSANAKAN DEBRIEFING DAN EVALUASI PENUTUPAN OPERASI SAR TERHADAP {$namaOps} KMA HASIL AKHIR {$isiLaporan} TTK"
            ],
            [
                'kode' => 'BBB',
                'isi' => "ALUT DAN PALSAR DIKEMBALIKAN KE PANGKALAN TTK DUA\n{$alutText}"
            ],
            [
                'kode' => 'CCC',
                'isi' => "UNSUR SAR TERLIBAT DIKEMBALIKAN KE KESATUAN MASING-MASING TTK DUA\n{$unsurText}"
            ],
            [
                'kode' => 'DDD',
                'isi' => "DATA REKAPITULASI KORBAN AKHIR TTK DUA\n* KORBAN SELAMAT : {$stats['s']} ORANG\n* KORBAN MENINGGAL DUNIA : {$stats['md']} ORANG\n* KORBAN DALAM PENCARIAN (DP) : {$stats['dp']} ORANG\n* TOTAL POB : {$stats['total']} ORANG"
            ],
            [
                'kode' => 'EEE',
                'isi' => "DENGAN DITEMUKANNYA SELURUH KORBAN MAKA OPERASI SAR DINYATAKAN DITUTUP KMA UCAPAN TERIMA KASIH ATAS KERJASAMANYA KEPADA SELURUH UNSUR SAR TERLIBAT TTK"
            ],
            [
                'kode' => 'FFF',
                'isi' => "DMKN SBG LAPORAN PENUTUPAN OPERASI SAR KMA KA KPP KENDARI SELAKU SMC KRM TTK HBS"
            ]
        ];

        return [
            'sections' => $sections,
            'perihal' => $perihal,
            'aksi' => 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
            'info' => 'DEPUTI BIDANG OPERASI PENCARIAN DAN PERTOLONGAN DAN KESIAPSIAGAAN',
            'dari' => 'KANSAR KENDARI'
        ];
    }
}
