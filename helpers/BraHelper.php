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

    public static function normalizeMilitaryTW(?string $input, string $defaultZone = 'H'): string {
        if (empty($input)) {
            return self::formatMilitaryTW(null, $defaultZone);
        }
        $clean = trim((string)$input);
        // Hapus prefix TWU atau TWB jika ada
        $clean = preg_replace('/^(?:TW[UB]\s*)+/i', '', $clean);
        $clean = trim($clean);

        // Jika sudah cocok format MMDD HHmm atau MMDD HHmm Z (termasuk 8 digit bersambung atau zona tanpa spasi)
        if (preg_match('/^(\d{4})\s*(\d{4})\s*([A-Z])?$/i', $clean, $m)) {
            $z = !empty($m[3]) ? strtoupper($m[3]) : $defaultZone;
            return "{$m[1]} {$m[2]} {$z}";
        }

        // Coba parse timestamp dari string tanggal biasa
        $ts = strtotime($clean);
        if ($ts !== false) {
            return self::formatMilitaryTW(date('Y-m-d H:i:s', $ts), $defaultZone);
        }

        return strtoupper($clean);
    }

    public static function normalizeCategory(?string $kategori): string {
        $cat = strtoupper(trim((string)$kategori));
        if ($cat === 'SAR_UMUM' || $cat === 'OPERASI_SAR') {
            return 'OPERASI_SAR';
        }
        if ($cat === 'PERGERAKAN_KAPAL' || $cat === 'KAPAL') {
            return 'PERGERAKAN_KAPAL';
        }
        return 'MAPEL';
    }

    public static function ensureEndsWithTtk(string $perihal): string {
        $clean = trim($perihal);
        if ($clean === '') return 'BERITA RADIO SAR TTK';
        $clean = rtrim($clean, " .\t\n\r\0\x0B");
        if (!preg_match('/\bTTK$/i', $clean)) {
            $clean .= ' TTK';
        }
        return strtoupper($clean);
    }

    /**
     * Sandi telegram per baris: baris daftar ("1. ", "A. ") dan judul daftar ("... TTK DUA") cukup kapital,
     * baris naratif disandikan (KMA/TTK/TTK DUA). Baris baru dipertahankan (sejalan BraTemplates.formatSectionText).
     */
    public static function sanitizeTelegramPunctuation(string $text): string {
        if ($text === '') return '';
        $lines = preg_split('/\r\n|\r|\n/', $text);
        $out = array_map(static function (string $line): string {
            if (trim($line) === '') return '';
            $upper = trim(mb_strtoupper($line, 'UTF-8'));
            if (preg_match('/^(\d+|[A-Z])\.\s/u', $upper) || preg_match('/TTK DUA\s*$/u', $upper)) return $upper;
            return self::sanitizeTelegramLine($line);
        }, $lines);
        return trim(preg_replace('/\n{3,}/', "\n\n", implode("\n", $out)));
    }

    private static function sanitizeTelegramLine(string $text): string {
        // 1. Ubah ke Uppercase
        $formatted = mb_strtoupper($text, 'UTF-8');

        // Keterangan dalam kurung (mis. pelapor/HP) dipertahankan apa adanya
        $kurung = [];
        $formatted = preg_replace_callback('/\([^)]*\)/u', function ($m) use (&$kurung) {
            $kurung[] = $m[0];
            return '__KURUNG_' . (count($kurung) - 1) . '__';
        }, $formatted);

        // 1.5. Konversi kebocoran format tanggal ISO YYYY-MM-DD HH:ii:ss atau YYYY-MM-DD ke sandi militer MMDD HHMM H
        $formatted = preg_replace_callback(
            '/(?:TW\s+)?(\d{4})-(\d{2})-(\d{2})[T\s](\d{2}):(\d{2})(?::(\d{2}))?/i',
            function($m) {
                return "TW {$m[2]}{$m[3]} {$m[4]}{$m[5]} H";
            },
            $formatted
        );
        $formatted = preg_replace_callback(
            '/(?:TW\s+)?(\d{4})-(\d{2})-(\d{2})\b/i',
            function($m) {
                return "TW {$m[2]}{$m[3]} 0000 H";
            },
            $formatted
        );
        $formatted = preg_replace('/\bTW\s+TW\b/i', 'TW', $formatted);

        // 2. Proteksi desimal angka koordinat (misal 03° 58' 30.0" S - 122° 35' 12.5" T)
        $formatted = preg_replace_callback('/(\d+)\.(\d+)/', function($m) {
            return $m[1] . '__DECIMAL_DOT__' . $m[2];
        }, $formatted);

        // Proteksi singkatan gelar seperti A.S., S.SOS., S.T., S.E., S.PD.
        $formatted = preg_replace_callback('/\b([A-Z])\.([A-Z])\./i', function($m) {
            return $m[1] . '__GELAR_DOT__' . $m[2] . '__GELAR_DOT__';
        }, $formatted);

        // 3. Konversi titik dua (:) menjadi TTK DUA
        $formatted = preg_replace('/\s*:\s*/', ' TTK DUA ', $formatted);

        // 4. Konversi koma (,) menjadi KMA
        $formatted = preg_replace('/\s*,\s*/', ' KMA ', $formatted);

        // 5. Konversi titik (.) menjadi TTK
        $formatted = preg_replace('/\s*\.+\s*/', ' TTK ', $formatted);

        // Kembalikan tanda titik desimal & gelar
        $formatted = str_replace('__DECIMAL_DOT__', '.', $formatted);
        $formatted = str_replace('__GELAR_DOT__', '.', $formatted);

        // 6. Reduplikasi kata ulang (CIRI-CIRI -> CIRIS, KAPAL-KAPAL -> KAPALS)
        $formatted = self::formatTelegramReduplication($formatted);

        // 7. Bersihkan duplikasi TTK atau KMA yang menumpuk
        $formatted = preg_replace('/\bTTK(?:\s+TTK)+\b/', 'TTK', $formatted);
        $formatted = preg_replace('/\bKMA(?:\s+KMA)+\b/', 'KMA', $formatted);
        $formatted = preg_replace('/\s+/', ' ', $formatted);
        $formatted = preg_replace_callback('/__KURUNG_(\d+)__/', fn($m) => $kurung[(int)$m[1]], $formatted);

        return trim($formatted);
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

    /**
     * Memformat kata pengulangan (reduplikasi) sesuai kaidah sandi baku telegram dinas:
     * Dilarang menggunakan tanda hubung (-) atau angka 2, melainkan diubah menjadi sufiks 'S'
     * Contoh: CIRI-CIRI -> CIRIS, KAPAL-KAPAL -> KAPALS, TANDA-TANDA -> TANDAS
     */
    public static function formatTelegramReduplication(string $text): string {
        if ($text === '') {
            return '';
        }
        // Ganti kata-kata pengulangan persis dengan sufiks 'S' (e.g. CIRI-CIRI -> CIRIS)
        $formatted = preg_replace_callback('/\b([A-Za-z]+)-\1\b/ui', function($m) {
            return strtoupper($m[1]) . 'S';
        }, $text);

        // Kamus kata ulang berimbuhan atau istilah khusus telegram
        $specialMap = [
            'TERAPUNG-APUNG' => 'TERAPUNG',
            'BERPINTAL-PINTAL' => 'BERPINTAL',
            'TEROMBANG-AMBING' => 'TEROMBANG AMBING',
            'SAMBUNG-MENYAMBUNG' => 'SAMBUNG MENYAMBUNG',
            'BOLAK-BALIK' => 'BOLAK BALIK',
            'HILANG-KONTAK' => 'HILANG KONTAK'
        ];
        foreach ($specialMap as $from => $to) {
            $formatted = preg_replace('/\b' . preg_quote($from, '/') . '\b/i', $to, $formatted);
        }

        return $formatted;
    }

    public static function buildSectionsFromMapel(?array $ops = null, array $customParams = []): array {
        $twu = self::formatMilitaryTW();
        $twLapor = !empty($ops['waktu_lapor']) 
            ? self::formatMilitaryTW($ops['waktu_lapor']) 
            : (!empty($customParams['tw_lapor']) ? $customParams['tw_lapor'] : self::formatMilitaryTW(date('Y-m-d H:i:s', strtotime('-15 minutes'))));

        $pelapor = !empty($customParams['pelapor']) 
            ? strtoupper($customParams['pelapor']) 
            : (!empty($ops['nama_pelapor']) ? strtoupper($ops['nama_pelapor']) : 'BPK SAHARUDDIN');
        
        $hub = !empty($customParams['hubungan_pelapor']) 
            ? strtoupper($customParams['hubungan_pelapor']) 
            : (!empty($ops['hubungan_pelapor']) ? strtoupper($ops['hubungan_pelapor']) : 'PEMILIK KAPAL / KELUARGA');
        
        $noHp = !empty($customParams['no_telp_pelapor']) 
            ? $customParams['no_telp_pelapor'] 
            : (!empty($ops['no_telp_pelapor']) ? $ops['no_telp_pelapor'] : '081327820103');
        $infoPelapor = "{$pelapor} ({$hub}/HP {$noHp})";

        $namaMusibah = !empty($customParams['nama_musibah']) 
            ? strtoupper($customParams['nama_musibah']) 
            : (!empty($ops['nama_operasi']) ? strtoupper($ops['nama_operasi']) : 'LONGBOAT MATI MESIN DAN HILANG KONTAK');

        $pob = !empty($customParams['pob']) 
            ? (int)$customParams['pob'] 
            : (!empty($ops['jumlah_korban']) ? (int)$ops['jumlah_korban'] : (!empty($ops['korban']) && is_array($ops['korban']) ? count($ops['korban']) : 1));
        $pobText = $pob > 0 ? " DENGAN POB {$pob} ORANG" : "";

        $ciriKapal = !empty($customParams['ciri_kapal']) 
            ? strtoupper($customParams['ciri_kapal']) 
            : (!empty($ops['alut_palsar']) ? strtoupper(str_replace("\n", ' KMA ', $ops['alut_palsar'])) : 'LONGBOAT WARNA BIRU PUTIH MESIN TEMPEL 15 PK');
        $ciriKapal = self::formatTelegramReduplication($ciriKapal);

        $lokasi = !empty($customParams['lokasi']) 
            ? strtoupper($customParams['lokasi']) 
            : (!empty($ops['lokasi_kejadian']) ? strtoupper($ops['lokasi_kejadian']) : 'PERAIRAN SULAWESI TENGGARA');

        $koordinat = !empty($customParams['koordinat']) 
            ? strtoupper($customParams['koordinat']) 
            : (!empty($ops['koordinat']) ? strtoupper($ops['koordinat']) : '03° 58\' 30" S - 122° 35\' 12" E');

        // Hitung Hari Ke Operasi SAR jika merujuk pada Operasi SAR
        $hariKe = 1;
        if (!empty($ops)) {
            // 1. Cek dari renops jika ada
            if (!empty($ops['renops']) && is_array($ops['renops'])) {
                foreach ($ops['renops'] as $rn) {
                    if (!empty($rn['hari_ke'])) {
                        if (preg_match('/(?:H\s*[\.\-]?\s*)?(\d+)/i', (string)$rn['hari_ke'], $m)) {
                            $hariKe = max($hariKe, (int)$m[1]);
                        }
                    }
                }
            }
            // 2. Cek dari laporan_harian (sitrep) jika ada
            if (!empty($ops['laporan_harian']) && is_array($ops['laporan_harian'])) {
                foreach ($ops['laporan_harian'] as $lp) {
                    if (!empty($lp['hari_ke'])) {
                        if (preg_match('/(?:H\s*[\.\-]?\s*)?(\d+)/i', (string)$lp['hari_ke'], $m)) {
                            $hariKe = max($hariKe, (int)$m[1]);
                        }
                    }
                }
            }
            // 3. Cek selisih hari dari tanggal_mulai atau waktu_lapor terhadap hari ini
            if ($hariKe === 1) {
                $startDateStr = !empty($ops['tanggal_mulai']) ? $ops['tanggal_mulai'] : (!empty($ops['waktu_lapor']) ? $ops['waktu_lapor'] : null);
                if ($startDateStr) {
                    $startTime = strtotime(substr($startDateStr, 0, 10));
                    $nowTime = strtotime(date('Y-m-d'));
                    if ($startTime && $nowTime >= $startTime) {
                        $calcDay = (int)floor(($nowTime - $startTime) / 86400) + 1;
                        $hariKe = max($hariKe, $calcDay);
                    }
                }
            }
        }
        if (!empty($customParams['hari_ke'])) {
            $hariKe = (int)$customParams['hari_ke'];
        }

        $kalimatHariKe = "";
        if ($hariKe >= 2) {
            $kalimatHariKe = " SAAT INI OPERASI SAR SEDANG BERJALAN MEMASUKI HARI KE-{$hariKe} (H.{$hariKe}) DENGAN HASIL KORBAN MASIH DALAM PENCARIAN TTK";
        }

        $kondisiTerakhir = !empty($customParams['kondisi_terakhir']) 
            ? strtoupper($customParams['kondisi_terakhir']) 
            : 'INFO TERAKHIR DARI KORBAN KAPAL TERAPUNG DAN MEMBUTUHKAN BANTUAN SAR';

        // Seksi AAA baku telegram pemapelan (sandi resmi CIRIS tanpa tanda hubung, koordinat bersambung, klausul H.X bila >= 2)
        $isiAaa = "PD TW {$twLapor} TERIMA INFO DARI {$infoPelapor} BAHWA TELAH TERJADI {$namaMusibah}{$pobText} POSISI SAAT INI DI SEKITAR {$lokasi} PADA KOORDINAT {$koordinat} TTK CIRIS KAPAL TTK DUA {$ciriKapal} TTK{$kalimatHariKe} {$kondisiTerakhir} TTK";
        $isiAaa = self::formatTelegramReduplication(trim($isiAaa));

        // Seksi BBB baku permintaan pemapelan maritim kepada kapal melintas (KAPALS)
        $isiBbb = "TERKAIT INFO MUSIBAH TSB MHN BANTUAN PEMANTAUAN DAN PEMAPELAN KEPADA KAPALS YANG MELINTASI DI AREA TERSEBUT KMA APABILA MENEMUKAN TANDAS KEBERADAAN KAPAL TERSEBUT AGAR DAPAT MEMBERIKAN PERTOLONGAN PERTAMA SERTA MELAPORKAN KPD BASARNAS KENDARI TTK";

        // Seksi CCC baku ucapan terima kasih telegram resmi
        $isiCcc = "DEMIKIAN SEBAGAI LAPORAN ATAS KERJASAMANYA DIUCAPKAN TERIMAKASIH TTK KMA KAKANSAR KENDARI KRM TTK HBS";

        $perihal = "PERMINTAAN PEMAPELAN DAN PEMANTAUAN TERHADAP " . self::formatTelegramReduplication($namaMusibah) . " TTK";

        return [
            'kategori' => 'MAPEL',
            'perihal' => $perihal,
            'derajat' => 'SGR',
            'sifat' => 'SEGERA',
            'hari_ke' => $hariKe,
            'aksi' => $customParams['aksi'] ?? 'KA KSOP KENDARI KMA KA SROP KENDARI',
            'info' => $customParams['info'] ?? 'KEPALA BADAN NASIONAL PENCARIAN DAN PERTOLONGAN',
            'dari' => 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
            'jabatan_smc' => 'KEPALA KANTOR PENCARIAN DAN PERTOLONGAN KENDARI',
            'sections' => [
                ['kode' => 'AAA', 'isi' => $isiAaa],
                ['kode' => 'BBB', 'isi' => $isiBbb],
                ['kode' => 'CCC', 'isi' => $isiCcc],
            ]
        ];
    }

    public static function getDefaultTemplate(string $kategori = 'MAPEL', string $noBra = '', int $nomorUrut = 1, string $satker = 'SAR-304', string $bulanTahun = ''): array {
        if ($kategori === 'PERGERAKAN_KAPAL') {
            return self::buildSectionsFromVesselMovement('KN_SAR_PACITAN', 'TOLAK');
        }

        if ($kategori === 'MAPEL') {
            return self::buildSectionsFromMapel();
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
