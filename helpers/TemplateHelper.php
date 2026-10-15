<?php
/**
 * QR-SAR - Template Helper (Format Resmi WhatsApp FILE AKTIF Basarnas)
 * KPP Kendari - Basarnas
 */

declare(strict_types=1);

class TemplateHelper {
    public const HARI_INDO = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    public const BULAN_INDO = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    public static function parseToTimestamp(?string $dateInput): ?int {
        if (!$dateInput) return null;
        $clean = trim((string)$dateInput);
        if ($clean === '' || $clean === '-') return null;
        if (is_numeric($clean)) return (int)$clean;

        // Support d/m/Y or d-m-Y format: e.g. 12/10/2026 or 12-10-2026
        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})(?:\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?$/', $clean, $m)) {
            $day = (int)$m[1];
            $month = (int)$m[2];
            $year = (int)$m[3];
            $hour = isset($m[4]) ? (int)$m[4] : 0;
            $min = isset($m[5]) ? (int)$m[5] : 0;
            $sec = isset($m[6]) ? (int)$m[6] : 0;
            return mktime($hour, $min, $sec, $month, $day, $year);
        }

        $ts = strtotime($clean);
        return $ts !== false ? $ts : null;
    }

    public static function formatTanggalIndo(?string $dateInput): string {
        $timestamp = self::parseToTimestamp($dateInput);
        if ($timestamp === null) return '-';

        $hari = self::HARI_INDO[(int)date('w', $timestamp)];
        $tgl = (int)date('j', $timestamp);
        $bln = self::BULAN_INDO[(int)date('n', $timestamp)];
        $thn = date('Y', $timestamp);

        return "{$hari}, {$tgl} {$bln} {$thn}";
    }

    public static function formatTanggalSimple(?string $dateInput): string {
        $timestamp = self::parseToTimestamp($dateInput);
        if ($timestamp === null) return '-';

        $tgl = (int)date('j', $timestamp);
        $bln = self::BULAN_INDO[(int)date('n', $timestamp)];
        $thn = date('Y', $timestamp);

        return "{$tgl} {$bln} {$thn}";
    }

    public static function formatJamWITA(?string $dateInput): string {
        $timestamp = self::parseToTimestamp($dateInput);
        if ($timestamp === null) return '00:00';
        return date('H:i', $timestamp);
    }

    public static function formatTW(?string $dateInput): string {
        if (!$dateInput) return 'TW 0000 0000 H';
        $timestamp = strtotime($dateInput);
        if ($timestamp === false) return 'TW 0000 0000 H';

        $mm = date('m', $timestamp);
        $dd = date('d', $timestamp);
        $hh = date('H', $timestamp);
        $min = date('i', $timestamp);

        return "TW {$mm}{$dd} {$hh}{$min} H";
    }

    public static function formatDMS(float|string|null $val, bool $isLat, bool $useIndoDir = false): string {
        if ($val === null || $val === '') return '-';
        $v = (float)$val;
        $dir = $isLat ? ($v < 0 ? 'S' : ($useIndoDir ? 'U' : 'N')) : ($v < 0 ? ($useIndoDir ? 'B' : 'W') : ($useIndoDir ? 'T' : 'E'));
        $abs = abs($v);
        $d = (int)floor($abs);
        $mFloat = ($abs - $d) * 60;
        $m = (int)floor($mFloat);
        $sFloat = ($mFloat - $m) * 60;
        $s = round($sFloat, 1);
        if ($s >= 60.0) {
            $s = 0.0;
            $m++;
            if ($m >= 60) {
                $m = 0;
                $d++;
            }
        }
        $dPad = $isLat ? str_pad((string)$d, 2, '0', STR_PAD_LEFT) : str_pad((string)$d, 3, '0', STR_PAD_LEFT);
        $mPad = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        $sPad = str_pad(number_format($s, 1, '.', ''), 4, '0', STR_PAD_LEFT);
        return "{$dPad}° {$mPad}' {$sPad}\" {$dir}";
    }

    public static function formatDmsPair(float|string|null $lat, float|string|null $lng, bool $useIndoDir = false): string {
        return self::formatDMS($lat, true, $useIndoDir) . " - " . self::formatDMS($lng, false, $useIndoDir);
    }

    public static function getWaktuMulaiAktual(?array $operasi): string {
        if (!$operasi) return date('Y-m-d H:i:s');
        if (!empty($operasi['waktu_reopen'])) {
            return $operasi['waktu_reopen'];
        }
        if (!empty($operasi['waktu_berangkat'])) {
            return $operasi['waktu_berangkat'];
        }
        if (!empty($operasi['waktu_lapor'])) {
            return $operasi['waktu_lapor'];
        }
        if (!empty($operasi['waktu_kejadian'])) {
            return $operasi['waktu_kejadian'];
        }
        return date('Y-m-d H:i:s');
    }

    public static function hitungHariKe(string|array|null $waktuOrOperasi, ?string $targetDate = null): string {
        if (is_array($waktuOrOperasi)) {
            $waktu = self::getWaktuMulaiAktual($waktuOrOperasi);
        } else {
            $waktu = $waktuOrOperasi;
        }

        if (!$waktu) return 'H.1';
        $t1 = strtotime(date('Y-m-d', strtotime($waktu)));
        $t2 = strtotime(date('Y-m-d', $targetDate ? strtotime($targetDate) : time()));

        if ($t1 === false || $t2 === false) return 'H.1';
        $diffDays = (int)floor(($t2 - $t1) / 86400);
        $dayNumber = max(1, $diffDays + 1);

        return "H.{$dayNumber}";
    }

    /**
     * Hitung Response Time SAR (dari informasi diterima/lapor hingga tim berangkat)
     */
    public static function hitungResponseTime(?string $waktuLapor, ?string $waktuBerangkat): array {
        if (empty($waktuLapor) || empty($waktuBerangkat)) {
            return [
                'minutes' => null,
                'formatted' => 'Belum Lengkap',
                'is_quick' => false,
                'badge_color' => 'slate',
                'badge_text' => '⏱️ Respons Time',
                'label' => 'Waktu lapor/berangkat belum tercatat'
            ];
        }

        $t1 = strtotime($waktuLapor);
        $t2 = strtotime($waktuBerangkat);
        if ($t1 === false || $t2 === false) {
            return [
                'minutes' => null,
                'formatted' => 'Format Salah',
                'is_quick' => false,
                'badge_color' => 'slate',
                'badge_text' => '⏱️ Respons Time',
                'label' => 'Format waktu tidak valid'
            ];
        }

        $diffSeconds = $t2 - $t1;
        if ($diffSeconds < 0) {
            $diffSeconds = abs($diffSeconds);
        }

        $diffMinutes = (int)round($diffSeconds / 60);

        if ($diffMinutes < 60) {
            $formatted = "{$diffMinutes} Menit";
        } else {
            $hours = (int)floor($diffMinutes / 60);
            $remMin = $diffMinutes % 60;
            $formatted = $remMin > 0 ? "{$hours} Jam {$remMin} Menit" : "{$hours} Jam";
        }

        $badgeColor = $diffMinutes <= 15 ? 'emerald' : ($diffMinutes <= 30 ? 'teal' : 'amber');
        $badgeText = $diffMinutes <= 15 ? '⚡ Quick Response' : ($diffMinutes <= 30 ? '⚡ Sesuai Standar' : '⏱️ Respons Time');

        return [
            'minutes' => $diffMinutes,
            'formatted' => $formatted,
            'is_quick' => $diffMinutes <= 30,
            'badge_color' => $badgeColor,
            'badge_text' => $badgeText,
            'label' => $formatted
        ];
    }

    /**
     * Hitung Hari Pelaksanaan Operasi SAR
     * - Jika sudah ditutup: "1 s/d 7 Hari" atau "1 s/d 7 Hari + X Hari Tambahan (Total Y Hari)" jika diperpanjang
     * - Jika masih open: "Hari ke-X (Sedang Berjalan)" atau "7 Hari + X Hari Tambahan (Sedang Berjalan)"
     */
    public static function hitungPelaksanaanOperasi(array $op, ?string $nowDate = null): array {
        $status = strtoupper((string)($op['status'] ?? 'OPEN'));
        $isOpen = ($status === 'OPEN');
        $isReopened = !empty($op['waktu_reopen']);
        
        $waktuBerangkat = !empty($op['waktu_berangkat']) 
            ? $op['waktu_berangkat'] 
            : (!empty($op['waktu_kejadian']) ? $op['waktu_kejadian'] : (!empty($op['waktu_lapor']) ? $op['waktu_lapor'] : ($op['created_at'] ?? 'now')));
        
        $tMulai = strtotime(date('Y-m-d', strtotime($waktuBerangkat)));
        if ($tMulai === false) $tMulai = time();
        $tNow = $nowDate ? strtotime($nowDate) : time();

        if ($isOpen) {
            if ($isReopened) {
                $tReopen = strtotime(date('Y-m-d', strtotime((string)$op['waktu_reopen'])));
                $xHari = max(1, (int)floor(($tNow - $tReopen) / 86400) + 1);
                $totalHari = 7 + $xHari;
                return [
                    'status' => 'OPEN_REOPEN',
                    'teks' => "7 Hari + {$xHari} Hari Tambahan",
                    'subteks' => "Perpanjangan H.{$xHari} (Total {$totalHari} Hari)",
                    'badge' => "7 + {$xHari} Hari",
                    'h_display' => "H-{$xHari}",
                    'h_full' => "H-{$totalHari} (Ext)",
                    'is_extended' => true,
                    'total_hari' => $totalHari,
                    'x_hari' => $xHari
                ];
            } else {
                $hariKeNum = max(1, (int)floor(($tNow - $tMulai) / 86400) + 1);
                return [
                    'status' => 'OPEN',
                    'teks' => "1 s/d Hari ke-{$hariKeNum}",
                    'subteks' => "Sedang Berjalan (Hari ke-{$hariKeNum})",
                    'badge' => "H-{$hariKeNum}",
                    'h_display' => "H-{$hariKeNum}",
                    'h_full' => "H-{$hariKeNum}",
                    'is_extended' => false,
                    'total_hari' => $hariKeNum,
                    'x_hari' => 0
                ];
            }
        } else {
            // Status CLOSED
            $waktuSelesai = !empty($op['waktu_selesai']) ? $op['waktu_selesai'] : ($op['updated_at'] ?? date('Y-m-d H:i:s'));
            $tSelesai = strtotime(date('Y-m-d', strtotime($waktuSelesai)));
            if ($tSelesai === false) $tSelesai = $tMulai;

            if ($isReopened) {
                $tReopen = strtotime(date('Y-m-d', strtotime((string)$op['waktu_reopen'])));
                $xHari = max(1, (int)floor(($tSelesai - $tReopen) / 86400) + 1);
                $totalHari = 7 + $xHari;
                return [
                    'status' => 'CLOSED_REOPEN',
                    'teks' => "1 s/d 7 Hari + {$xHari} Hari Tambahan",
                    'subteks' => "Perpanjangan Selesai (Total {$totalHari} Hari)",
                    'badge' => "7 + {$xHari} Hari Selesai",
                    'h_display' => "H-{$totalHari}",
                    'h_full' => "H-{$totalHari} (Ext Selesai)",
                    'is_extended' => true,
                    'total_hari' => $totalHari,
                    'x_hari' => $xHari
                ];
            } else {
                $durasiHari = max(1, (int)floor(($tSelesai - $tMulai) / 86400) + 1);
                return [
                    'status' => 'CLOSED',
                    'teks' => "1 s/d {$durasiHari} Hari",
                    'subteks' => "Operasi Selesai ({$durasiHari} Hari)",
                    'badge' => "1 s/d {$durasiHari} Hari",
                    'h_display' => "H-{$durasiHari}",
                    'h_full' => "H-{$durasiHari} (Selesai)",
                    'is_extended' => false,
                    'total_hari' => $durasiHari,
                    'x_hari' => 0
                ];
            }
        }
    }


    public static function hitungStatistikKorban(array $korbanList): array {
        $stats = ['total' => count($korbanList), 's' => 0, 'md' => 0, 'dp' => 0, 'h' => 0];
        foreach ($korbanList as $k) {
            $status = strtoupper((string)($k['status_korban'] ?? 'DP'));
            if ($status === 'S' || $status === 'SELAMAT') $stats['s']++;
            elseif ($status === 'MD' || $status === 'MENINGGAL DUNIA') $stats['md']++;
            elseif ($status === 'DP' || $status === 'DALAM PENCARIAN') $stats['dp']++;
            elseif ($status === 'H' || $status === 'HILANG / NIHIL') $stats['h']++;
            else $stats['dp']++;
        }
        return $stats;
    }

    public static function getLabelStatusKorban(string $status): string {
        $st = strtoupper(trim($status));
        return match ($st) {
            'S', 'SELAMAT' => 'Selamat (S)',
            'MD', 'MENINGGAL DUNIA' => 'Meninggal Dunia (MD)',
            'DP', 'DALAM PENCARIAN' => 'Dalam Pencarian (DP)',
            'H', 'HILANG / NIHIL' => 'Hilang / Nihil (H)',
            default => $status
        };
    }

    public static function getHeaderWA(string $judulLaporan, string $namaOperasi, ?string $waktu = null): string {
        $tglFormatted = self::formatTanggalIndo($waktu ?: date('Y-m-d H:i:s'));
        $judulUpper = strtoupper($judulLaporan);
        return <<<HEADER
FILE AKTIF
____

SEMANGAT QUICK ACTION SEARCH AND RESCUE
SATU JIWA SATU RASA
WE ARE FAMILY
PROFESIONAL MODERN TERUJI
{$judulUpper}

KANTOR PENCARIAN DAN PERTOLONGAN KENDARI
{$tglFormatted}

YTH : KABASARNAS
CC : PIMTI MADYA & PRATAMA

Dengan hormat, mohon ijin melaporkan {$namaOperasi}.
__________________________________________________
HEADER;
    }

    public static function getFooterWA(): string {
        return <<<FOOTER
Demikian kami laporkan, mohon petunjuk dan arahan

Ttd

KKS Kendari
===========================
🏢 Kantor Pencarian Dan Pertolongan Kendari
📍 Alamat Kantor : Jl. Kapt. Piere Tendean No. 91 Baruga, Kota Kendari, Sultra 93116
☎️ Telp. : (0401) 3196557
📠 Fax. : (0401) 3196558
📱 WhatsApp : +62811-4034-115
🚨 Emergency Call : 115
🌐 Website : Www.Kendari.Basarnas.go.id
📧 E-Mail : Sar304kendari@gmail.com / Pusdatinsarkendari@Yahoo.co.id
📲 Medsos : @Basarnaskendari
FOOTER;
    }

    public static function generateLaporanAwalWA(array $ops, array $korban = [], array $unsur = [], array $alut = []): string {
        $header = self::getHeaderWA(
            'LAPORAN AWAL OPS SAR',
            $ops['nama_operasi'] ?? 'Operasi SAR',
            $ops['waktu_lapor'] ?? date('Y-m-d H:i:s')
        );

        $tglLapor = self::formatTanggalSimple($ops['waktu_lapor'] ?? null);
        $jamLapor = self::formatJamWITA($ops['waktu_lapor'] ?? null);

        $tglBerangkat = self::formatTanggalSimple($ops['waktu_berangkat'] ?? null);
        $jamBerangkat = self::formatJamWITA($ops['waktu_berangkat'] ?? null);

        $waktuEta = $ops['eta_tiba'] ?? ($ops['waktu_berangkat'] ?? null);
        $tglEta = self::formatTanggalSimple($waktuEta);
        $jamEta = self::formatJamWITA($waktuEta);

        $stats = self::hitungStatistikKorban($korban);

        // Identitas korban text
        if (empty($korban)) {
            $identitasText = "1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -";
        } else {
            $kList = [];
            foreach ($korban as $idx => $k) {
                $num = $idx + 1;
                $statusK = $k['status_korban'] ?? $k['status'] ?? 'DP';
                $label = $statusK === 'DP' ? '' : ' (' . self::getLabelStatusKorban($statusK) . ')';
                $namaKorban = $k['nama_korban'] ?? $k['nama'] ?? '-';
                $jkKorban = $k['jk'] ?? $k['jenis_kelamin'] ?? '-';
                $umurKorban = !empty($k['umur']) ? $k['umur'] : '-';
                $alamatKorban = !empty($k['alamat']) ? $k['alamat'] : '-';
                $kList[] = "{$num}. Nama : {$namaKorban}{$label}\n   Umur : {$umurKorban}\n   Jenis Kelamin : {$jkKorban}\n   Alamat : {$alamatKorban}";
            }
            $identitasText = implode("\n\n", $kList);
        }

        // Unsur
        if (empty($unsur)) {
            $unsurText = "• Tim Rescue KPP Kendari (7 Org)";
        } else {
            $uList = [];
            foreach ($unsur as $u) {
                $uList[] = "• {$u['nama_unsur']} ({$u['jumlah_personil']} Org)";
            }
            $unsurText = implode("\n", $uList);
        }

        // Alut
        if (empty($alut)) {
            $alutText = "• 1 Unit Rescue Car\n• 1 Unit Rubber Boat";
        } else {
            $aList = [];
            foreach ($alut as $a) {
                $sat = $a['satuan'] ?: 'Unit';
                $aList[] = "• {$a['jumlah_alut']} {$sat} {$a['nama_alut']}";
            }
            $alutText = implode("\n", $aList);
        }

        $pelapor = $ops['nama_pelapor'] ?: 'Bpk. Pelapor';
        $hub = $ops['hubungan_pelapor'] ?: 'Masyarakat';
        $hp = $ops['no_telp_pelapor'] ?: '-';
        $tim = $ops['tim_berangkat'] ?: 'Tim Rescue Kansar Kendari';

        $jarakStr = $ops['jarak'] ?: '-';
        if (!empty($ops['radial'])) {
            $jarakStr .= " (Radial {$ops['radial']})";
        }

        $body = <<<BODY
1. Sumber Info kejadian :
{$pelapor} ({$hub} / No. HP {$hp})
Pada Tanggal {$tglLapor} pukul {$jamLapor} Wita

2. Lokasi Kejadian :
Lokasi : {$ops['lokasi_kejadian']}
Koordinat : {$ops['koordinat']}
jarak tempuh : {$jarakStr}
Estimasi waktu tempuh : {$ops['eta_lokasi']}

3. Pelaksanaan SAR
Pada Tanggal {$tglBerangkat} pukul {$jamBerangkat} Wita, {$tim} berangkat ke LKP untuk melaksanakan Operasi SAR, dengan estimasi waktu tempuh ETA {$tglEta} pukul {$jamEta} WITA.

4. Kronologi Kejadian
{$ops['kronologi']}

5. Data Korban: {$stats['total']} orang
• S : {$stats['s']} orang
• MD : {$stats['md']} orang
• DP : {$stats['dp']} orang
• H : {$stats['h']} orang

Identitas Korban :
{$identitasText}

6. Unsur SAR Terlibat
{$unsurText}

7. Alut dan Palsar
{$alutText}

8. Faktor Penghambat
{$ops['faktor_penghambat']}

9. Kondisi Cuaca :
• cuaca : {$ops['cuaca']}
• Gelombang : {$ops['gelombang']}
• Angin: {$ops['angin']}
BODY;

        return $header . "\n\n" . $body . "\n\n" . self::getFooterWA();
    }

    public static function cleanSruCoordinates(?string $koor): string {
        if (!$koor) return '';
        $lines = explode("\n", str_replace("\r", "", $koor));
        $cleanLines = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if (preg_match('/^(pola\s*pencarian\s*:|total\s*sapuan\s*:|\[pola\s*sar|tipe\s*:)/i', $trim)) {
                continue;
            }
            $cleanLines[] = $line;
        }
        return trim(implode("\n", $cleanLines));
    }

    public static function generateRenopsWA(array $ops, array $renops, array $srus = [], array $korban = [], array $unsur = [], array $alut = []): string {
        $hariKe = strtoupper($renops['hari_ke'] ?? 'H.1');
        $namaOps = $ops['nama_operasi'] ?? 'Operasi SAR';
        $waktuLap = $renops['tanggal_laporan'] ?? date('Y-m-d');

        $header = self::getHeaderWA("RENCANA OPS SAR {$hariKe}", "Rencana Ops {$namaOps}", $waktuLap);

        $twBriefing = self::formatTW($renops['waktu_briefing'] ?? null);
        $twMulai = self::formatTW($renops['waktu_mulai_ops'] ?? null);

        // SRUs
        if (empty($srus)) {
            $koorOps = $ops['koordinat'] ?: '03° 58\' 30" S - 122° 35\' 12" E';
            $sruText = "*SRU 1 Tim Rescue Kansar Kendari* melakukan penyisiran dgn koordinat :\nA: {$koorOps}\nLuas area pencarian : 10 Nm²\nPola pencarian : Parallel Track Search";
        } else {
            $sList = [];
            foreach ($srus as $s) {
                $rawName = trim($s['nama_sru'] ?? 'SRU');
                $cleanName = trim($rawName, "* \t\n\r\0\x0B") ?: 'SRU';
                $nameFormatted = "*{$cleanName}* melakukan penyisiran dgn koordinat :";

                $koor = self::cleanSruCoordinates($s['koordinat_area'] ?? '') ?: ($ops['koordinat'] ?: '-');
                $pola = !empty($s['pola_pencarian']) ? $s['pola_pencarian'] : 'Parallel Track Search';
                
                $lines = [
                    $nameFormatted,
                    $koor
                ];

                if (!empty($s['luas_area'])) {
                    $luasVal = trim($s['luas_area']);
                    if (!preg_match('/^luas\s*area/i', $luasVal)) {
                        $lines[] = "Luas area pencarian : {$luasVal}";
                    } else {
                        $lines[] = $luasVal;
                    }
                }

                $lines[] = "Pola pencarian : {$pola}";

                if (!empty($s['total_sapuan'])) {
                    $sapuanVal = trim($s['total_sapuan']);
                    if (!preg_match('/^total\s*sapuan\s*:/i', $sapuanVal)) {
                        $lines[] = "Total sapuan : {$sapuanVal}";
                    } else {
                        $lines[] = $sapuanVal;
                    }
                }

                $sList[] = implode("\n", $lines);
            }
            $sruText = implode("\n\n", $sList);
        }

        if (empty($unsur)) {
            $unsurText = "• Tim Rescue Kansar Kendari : 7 Orang";
        } else {
            $uList = [];
            foreach ($unsur as $u) {
                $uList[] = "• {$u['nama_unsur']} : {$u['jumlah_personil']} Orang";
            }
            $unsurText = implode("\n", $uList);
        }

        if (empty($alut)) {
            $alutText = "• 1 Unit Rescue Car\n• 1 Unit Rubber Boat";
        } else {
            $aList = [];
            foreach ($alut as $a) {
                $sat = $a['satuan'] ?: 'Unit';
                $aList[] = "• {$a['jumlah_alut']} {$sat} {$a['nama_alut']}";
            }
            $alutText = implode("\n", $aList);
        }

        $cuaca = !empty($renops['cuaca']) ? $renops['cuaca'] : (!empty($ops['cuaca']) ? $ops['cuaca'] : 'Cerah Berawan');
        $sruCount = count($srus) ?: 1;

        $body = <<<BODY
Rencana Ops {$hariKe} :

• PD {$twBriefing} Tim SAR Gabungan Melaksanakan Brifing Operasi.
• Pada {$twMulai} Tim SAR Gabungan Melanjutkan Operasi Pencarian {$hariKe} Sesuai Rencana Operasi {$hariKe} dengan Tim SAR gabungan menuju ke entri point masing-masing.
Dengan Tim dibagi {$sruCount} SRU dengan area pencarian sbb :

{$sruText}

1. Unsur SAR Terlibat :
{$unsurText}

2. Alut dan Palsar :
{$alutText}

3. Kondisi Cuaca :
• Cuaca: {$cuaca}
(Sumber BMKG)
BODY;

        return $header . "\n\n" . $body . "\n\n" . self::getFooterWA();
    }

    public static function generateLapbangWA(array $ops, array $laporan, array $korban = [], array $unsur = [], array $alut = [], bool $isPenutupan = false): string {
        $hariKe = strtoupper($laporan['hari_ke'] ?? 'H.1');
        $judul = $isPenutupan ? "LAPBANG OPS SAR {$hariKe} DAN PENUTUPAN" : "LAPBANG OPS SAR {$hariKe}";
        $namaOps = $ops['nama_operasi'] ?? 'Operasi SAR';
        $waktuLap = $laporan['waktu_laporan'] ?? date('Y-m-d H:i:s');

        $header = self::getHeaderWA($judul, "Perkembangan Ops {$namaOps}", $waktuLap);

        $klausulPenutupan = '';
        if ($isPenutupan) {
            $waktuDebrief = $laporan['waktu_debrief'] ?: ($laporan['waktu_laporan'] ?: date('Y-m-d H:i:s'));
            $tglDebrief = self::formatTanggalSimple($waktuDebrief);
            $jamDebrief = self::formatJamWITA($waktuDebrief);
            $klausulPenutupan = "\n\n• Pada tanggal {$tglDebrief} Pukul {$jamDebrief} WITA, Tim SAR Gabungan melakukan debriefing penutupan Ops SAR terhadap {$namaOps}, selanjutnya operasi SAR diusulkan ditutup dan unsur SAR yang terlibat kembali ke kesatuannya masing-masing dengan ucapan terima kasih.";
        }

        $stats = self::hitungStatistikKorban($korban);

        if (empty($korban)) {
            $identitasText = "1. Nama : -\n   Umur : -\n   Jenis Kelamin : -\n   Alamat : -";
        } else {
            $kList = [];
            foreach ($korban as $idx => $k) {
                $num = $idx + 1;
                $label = self::getLabelStatusKorban($k['status_korban']);
                $kList[] = "{$num}. Nama : {$k['nama_korban']} [Status: {$label}]\n   Umur : " . ($k['umur'] ?: '-') . "\n   Jenis Kelamin : " . ($k['jk'] ?: '-') . "\n   Alamat : " . ($k['alamat'] ?: '-');
            }
            $identitasText = implode("\n\n", $kList);
        }

        if (empty($unsur)) {
            $unsurText = "• Tim Rescue Kansar Kendari : 7 Orang";
        } else {
            $uList = [];
            foreach ($unsur as $u) {
                $uList[] = "• {$u['nama_unsur']} : {$u['jumlah_personil']} Orang";
            }
            $unsurText = implode("\n", $uList);
        }

        if (empty($alut)) {
            $alutText = "• 1 Unit Rescue Car\n• 1 Unit Rubber Boat";
        } else {
            $aList = [];
            foreach ($alut as $a) {
                $sat = $a['satuan'] ?: 'Unit';
                $aList[] = "• {$a['jumlah_alut']} {$sat} {$a['nama_alut']}";
            }
            $alutText = implode("\n", $aList);
        }

        $isi = !empty($laporan['isi_laporan']) ? $laporan['isi_laporan'] : 'Pencarian telah dilaksanakan oleh Tim SAR Gabungan sesuai Renops.';

        $body = <<<BODY
Hasil Ops SAR {$hariKe}:

{$isi}{$klausulPenutupan}

1. Data Korban: {$stats['total']} Orang
• S : {$stats['s']} ORANG
• MD : {$stats['md']} ORANG
• DP : {$stats['dp']} ORANG
• H : {$stats['h']} ORANG

2. Identitas Korban
{$identitasText}

3. Unsur SAR Terlibat
{$unsurText}

4. Alut dan Palsar
{$alutText}
BODY;

        return $header . "\n\n" . $body . "\n\n" . self::getFooterWA();
    }
}
