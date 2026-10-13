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

    public static function getNextNomorBra(string $kategori = 'MAPEL', string $satkerKode = 'SAR-304'): array {
        $db = getDB();
        $stmt = $db->query("SELECT MAX(nomor_urut) as max_urut FROM BraSar");
        $row = $stmt->fetch();
        $nextUrut = ($row && $row['max_urut']) ? (int)$row['max_urut'] + 1 : 1;

        $suffix = self::getBulanTahunSuffix();
        $cleanSatker = $satkerKode ?: 'SAR-304';
        $noBra = "{$nextUrut}/{$cleanSatker}/{$suffix}";

        return [
            'nomor_urut' => $nextUrut,
            'satker_kode' => $cleanSatker,
            'bulan_tahun' => $suffix,
            'no_bra' => $noBra
        ];
    }

    public static function getDefaultTemplate(string $kategori = 'MAPEL', string $noBra = '', int $nomorUrut = 1, string $satker = 'SAR-304', string $bulanTahun = ''): array {
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

    public static function countBraWords(array $sections, string $additional = ''): int {
        $combined = '';
        foreach ($sections as $s) {
            $combined .= ' ' . ($s['kode'] ?? '') . ' ' . ($s['isi'] ?? '');
        }
        $combined .= ' ' . $additional;
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
