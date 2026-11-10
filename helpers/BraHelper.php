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
        $yearSuffix = substr($suffix, 2);
        $cleanSatker = $satkerKode ?: 'SAR-304';

        if ($forUpdate) {
            // Penguncian baris eksklusif pada nomor_urut tertinggi tahun berjalan
            $stmt = $db->prepare("SELECT nomor_urut FROM BraSar WHERE bulan_tahun LIKE :yr ORDER BY nomor_urut DESC LIMIT 1 FOR UPDATE");
            $stmt->execute([':yr' => '%' . $yearSuffix]);
            $row = $stmt ? $stmt->fetch() : null;
            $maxUrut = ($row && isset($row['nomor_urut'])) ? (int)$row['nomor_urut'] : 0;
        } else {
            $stmt = $db->prepare("SELECT COALESCE(MAX(nomor_urut), 0) as max_urut FROM BraSar WHERE bulan_tahun LIKE :yr");
            $stmt->execute([':yr' => '%' . $yearSuffix]);
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
            : (!empty($ops['jumlah_pob']) ? (int)$ops['jumlah_pob'] : (!empty($ops['jumlah_korban']) ? (int)$ops['jumlah_korban'] : (!empty($ops['korban']) && is_array($ops['korban']) ? count($ops['korban']) : 1)));
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
}
