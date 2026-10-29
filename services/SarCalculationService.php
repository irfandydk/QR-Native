<?php
/**
 * QR-SAR - SAR Calculation & Operational Metrics Service
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Dedicated domain service for operational time calculations, victim statistics,
 * SAR response times, reopen duration logic, and date formatting.
 */

declare(strict_types=1);

class SarCalculationService {
    public const HARI_INDO = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    public const BULAN_INDO = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
    ];

    /**
     * Parse date string or timestamp into integer Unix timestamp.
     */
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

    /**
     * Format date to Indonesian full date string: e.g. "Senin, 12 Oktober 2026".
     */
    public static function formatTanggalIndo(?string $dateInput): string {
        $timestamp = self::parseToTimestamp($dateInput);
        if ($timestamp === null) return '-';

        $hari = self::HARI_INDO[(int)date('w', $timestamp)];
        $tgl = (int)date('j', $timestamp);
        $bln = self::BULAN_INDO[(int)date('n', $timestamp)];
        $thn = date('Y', $timestamp);

        return "{$hari}, {$tgl} {$bln} {$thn}";
    }

    /**
     * Format date to simple Indonesian date string: e.g. "12 Oktober 2026".
     */
    public static function formatTanggalSimple(?string $dateInput): string {
        $timestamp = self::parseToTimestamp($dateInput);
        if ($timestamp === null) return '-';

        $tgl = (int)date('j', $timestamp);
        $bln = self::BULAN_INDO[(int)date('n', $timestamp)];
        $thn = date('Y', $timestamp);

        return "{$tgl} {$bln} {$thn}";
    }

    /**
     * Resolve Indonesian day name from date string or numeric day of week.
     */
    public static function getNamaHariIndo(string|int|null $input): string {
        if ($input === null || $input === '') return 'Minggu';
        if (is_numeric($input)) {
            $w = (int)$input % 7;
            return self::HARI_INDO[$w] ?? 'Minggu';
        }
        $str = trim((string)$input);
        $map = [
            'sunday' => 'Minggu', 'sun' => 'Minggu',
            'monday' => 'Senin', 'mon' => 'Senin',
            'tuesday' => 'Selasa', 'tue' => 'Selasa',
            'wednesday' => 'Rabu', 'wed' => 'Rabu',
            'thursday' => 'Kamis', 'thu' => 'Kamis',
            'friday' => 'Jumat', 'fri' => 'Jumat',
            'saturday' => 'Sabtu', 'sat' => 'Sabtu'
        ];
        $lower = strtolower($str);
        if (isset($map[$lower])) {
            return $map[$lower];
        }
        $ts = self::parseToTimestamp($str);
        if ($ts !== null) {
            return self::HARI_INDO[(int)date('w', $ts)];
        }
        return in_array($str, self::HARI_INDO, true) ? $str : 'Minggu';
    }

    /**
     * Format time string to 24-hour WITA format: e.g. "08:30".
     */
    public static function formatJamWITA(?string $dateInput): string {
        $timestamp = self::parseToTimestamp($dateInput);
        if ($timestamp === null) return '-';
        return date('H:i', $timestamp);
    }

    /**
     * Format date to Military Time Word (TW): e.g. "TW 1012 0830 H".
     */
    public static function formatTW(?string $dateInput): string {
        if (!$dateInput) return '-';
        $timestamp = strtotime($dateInput);
        if ($timestamp === false) return '-';

        $mm = date('m', $timestamp);
        $dd = date('d', $timestamp);
        $hh = date('H', $timestamp);
        $min = date('i', $timestamp);

        return "TW {$mm}{$dd} {$hh}{$min} H";
    }

    /**
     * Resolve earliest operational start time from operation record.
     */
    public static function getWaktuMulaiAwal(?array $operasi): string {
        if (!$operasi) return date('Y-m-d H:i:s');

        $candidates = [];
        if (!empty($operasi['waktu_berangkat'])) {
            $candidates[] = $operasi['waktu_berangkat'];
        }
        if (!empty($operasi['waktu_lapor'])) {
            $candidates[] = $operasi['waktu_lapor'];
        }

        if (!empty($candidates)) {
            usort($candidates, function($a, $b) {
                return (self::parseToTimestamp((string)$a) ?? 0) <=> (self::parseToTimestamp((string)$b) ?? 0);
            });
            return (string)$candidates[0];
        }

        if (!empty($operasi['waktu_kejadian'])) {
            return (string)$operasi['waktu_kejadian'];
        }
        if (!empty($operasi['created_at'])) {
            return (string)$operasi['created_at'];
        }
        return date('Y-m-d H:i:s');
    }

    /**
     * Resolve actual start time respecting reopen status.
     */
    public static function getWaktuMulaiAktual(?array $operasi, bool $ignoreReopen = false): string {
        if (!$operasi) return date('Y-m-d H:i:s');
        if (!$ignoreReopen && !empty($operasi['waktu_reopen'])) {
            return (string)$operasi['waktu_reopen'];
        }
        return self::getWaktuMulaiAwal($operasi);
    }

    /**
     * Determine closing day number (N) prior to reopening (e.g. H.7).
     */
    public static function getBaseHariSebelumReopen(?array $operasi): int {
        if (!$operasi) return 7;

        if (!empty($operasi['hari_sebelum_reopen']) && (int)$operasi['hari_sebelum_reopen'] > 0) {
            return (int)$operasi['hari_sebelum_reopen'];
        }

        $initialStart = self::getWaktuMulaiAwal($operasi);
        $startTs = self::parseToTimestamp($initialStart);
        $t1 = $startTs !== null ? strtotime(date('Y-m-d', $startTs)) : false;

        $reopenStart = !empty($operasi['waktu_reopen']) ? (string)$operasi['waktu_reopen'] : null;
        $reopenTs = $reopenStart !== null ? self::parseToTimestamp($reopenStart) : null;
        $tReopen = $reopenTs !== null ? strtotime(date('Y-m-d', $reopenTs)) : false;

        if ($tReopen === false && !empty($operasi['waktu_selesai']) && $t1 !== false) {
            $selesaiTs = self::parseToTimestamp((string)$operasi['waktu_selesai']);
            if ($selesaiTs !== null) {
                $tSelesai = strtotime(date('Y-m-d', $selesaiTs));
                if ($tSelesai !== false && $tSelesai >= $t1) {
                    return max(1, (int)floor(($tSelesai - $t1) / 86400) + 1);
                }
            }
        }

        $lapList = $operasi['laporan_harian'] ?? ($operasi['lapbang'] ?? []);
        $renList = $operasi['renops'] ?? [];
        $maxPreReopenDay = 0;
        $penutupanDay = 0;

        foreach ($lapList as $l) {
            $lRaw = $l['waktu_debrief'] ?? ($l['waktu_laporan'] ?? ($l['tanggal_laporan'] ?? ''));
            $lTs = !empty($lRaw) ? self::parseToTimestamp((string)$lRaw) : null;
            if ($lTs === null) continue;
            $tLap = strtotime(date('Y-m-d', $lTs));
            if ($tLap === false) continue;

            if ($tReopen === false || $tLap < $tReopen) {
                $day = ($t1 !== false && $tLap >= $t1)
                    ? max(1, (int)floor(($tLap - $t1) / 86400) + 1)
                    : 0;
                if ($day === 0 && !empty($l['hari_ke']) && preg_match('/^H\.?(\d+)$/i', trim((string)$l['hari_ke']), $mH)) {
                    $day = (int)$mH[1];
                }
                if ($day > $maxPreReopenDay) {
                    $maxPreReopenDay = $day;
                }
                if (strtoupper((string)($l['jenis_laporan'] ?? '')) === 'PENUTUPAN' && $day > 0) {
                    $penutupanDay = $day;
                }
            }
        }

        if ($penutupanDay > 0) {
            return $penutupanDay;
        }

        foreach ($renList as $r) {
            $rRaw = $r['tanggal_laporan'] ?? ($r['tanggal'] ?? ($r['waktu_briefing'] ?? ''));
            $rTs = !empty($rRaw) ? self::parseToTimestamp((string)$rRaw) : null;
            if ($rTs === null) continue;
            $tRen = strtotime(date('Y-m-d', $rTs));
            if ($tRen === false) continue;

            if ($tReopen === false || $tRen < $tReopen) {
                $day = ($t1 !== false && $tRen >= $t1)
                    ? max(1, (int)floor(($tRen - $t1) / 86400) + 1)
                    : 0;
                if ($day === 0 && !empty($r['hari_ke']) && preg_match('/^H\.?(\d+)$/i', trim((string)$r['hari_ke']), $mH)) {
                    $day = (int)$mH[1];
                }
                if ($day > $maxPreReopenDay) {
                    $maxPreReopenDay = $day;
                }
            }
        }

        if ($maxPreReopenDay > 0) {
            return $maxPreReopenDay;
        }

        return 7;
    }

    /**
     * Hitung Hari Ke (H.1, H.2... atau H7 + 1, H7 + 2 jika perpanjangan).
     */
    public static function hitungHariKe(string|array|null $waktuOrOperasi, ?string $targetDate = null, ?string $fallbackHariKe = null): string {
        $targetTs = null;
        if ($targetDate !== null && trim($targetDate) !== '') {
            $targetTs = self::parseToTimestamp($targetDate);
        }

        if (is_array($waktuOrOperasi)) {
            $statusOp = strtoupper((string)($waktuOrOperasi['status'] ?? $waktuOrOperasi['status_operasi'] ?? ''));
            if ($targetTs === null && $statusOp === 'CLOSED' && !empty($waktuOrOperasi['waktu_selesai'])) {
                $targetTs = self::parseToTimestamp((string)$waktuOrOperasi['waktu_selesai']);
            }
            if ($targetTs === null) {
                $targetTs = time();
            }

            $t2 = strtotime(date('Y-m-d', $targetTs));
            $initialStart = self::getWaktuMulaiAwal($waktuOrOperasi);
            $reopenStart = !empty($waktuOrOperasi['waktu_reopen']) ? (string)$waktuOrOperasi['waktu_reopen'] : null;

            if ($reopenStart !== null) {
                $reopenTs = self::parseToTimestamp($reopenStart);
                $tReopen = $reopenTs !== null ? strtotime(date('Y-m-d', $reopenTs)) : false;
                if ($tReopen !== false && $t2 !== false && $t2 >= $tReopen) {
                    $baseHari = self::getBaseHariSebelumReopen($waktuOrOperasi);
                    $extDay = max(1, (int)floor(($t2 - $tReopen) / 86400) + 1);
                    return "H{$baseHari} + {$extDay}";
                }
            }
            $waktu = $initialStart;
        } else {
            $waktu = $waktuOrOperasi;
            if ($targetTs === null) {
                $targetTs = time();
            }
            $t2 = strtotime(date('Y-m-d', $targetTs));
        }

        if (!$waktu) return $fallbackHariKe ?: 'H.1';
        $startTs = self::parseToTimestamp($waktu);
        if ($startTs === null) return $fallbackHariKe ?: 'H.1';

        $t1 = strtotime(date('Y-m-d', $startTs));
        if ($t1 === false || $t2 === false) return $fallbackHariKe ?: 'H.1';

        $diffDays = (int)floor(($t2 - $t1) / 86400);
        if ($diffDays < 0 && !empty($fallbackHariKe) && preg_match('/^H(?:\.?\d+(?:\s*\+\s*\d+)?)$/i', trim($fallbackHariKe))) {
            return strtoupper(trim($fallbackHariKe));
        }
        $dayNumber = max(1, $diffDays + 1);

        return "H.{$dayNumber}";
    }

    /**
     * Hitung Response Time SAR (dari informasi diterima hingga tim berangkat).
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
     * Hitung Hari Pelaksanaan Operasi SAR (Open, Extended, Closed).
     */
    public static function hitungPelaksanaanOperasi(array $op, ?string $nowDate = null): array {
        $status = strtoupper((string)($op['status'] ?? 'OPEN'));
        $isOpen = ($status === 'OPEN');
        $isReopened = !empty($op['waktu_reopen']);
        
        $waktuBerangkat = self::getWaktuMulaiAwal($op);
        $tMulai = strtotime(date('Y-m-d', strtotime($waktuBerangkat)));
        if ($tMulai === false) $tMulai = time();
        $tNow = $nowDate ? strtotime($nowDate) : time();

        if ($isOpen) {
            if ($isReopened) {
                $tReopen = strtotime(date('Y-m-d', strtotime((string)$op['waktu_reopen'])));
                $xHari = max(1, (int)floor(($tNow - $tReopen) / 86400) + 1);
                $baseHari = self::getBaseHariSebelumReopen($op);
                $totalHari = $baseHari + $xHari;
                $hLabel = "H{$baseHari} + {$xHari}";
                return [
                    'status' => 'OPEN_REOPEN',
                    'teks' => "{$baseHari} Hari + {$xHari} Hari Tambahan",
                    'subteks' => "Perpanjangan {$hLabel} (Total {$totalHari} Hari)",
                    'badge' => $hLabel,
                    'h_display' => $hLabel,
                    'h_full' => "{$hLabel} (Ext)",
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
                    'badge' => "H.{$hariKeNum}",
                    'h_display' => "H.{$hariKeNum}",
                    'h_full' => "H.{$hariKeNum}",
                    'is_extended' => false,
                    'total_hari' => $hariKeNum,
                    'x_hari' => 0
                ];
            }
        } else {
            $waktuSelesai = !empty($op['waktu_selesai']) ? $op['waktu_selesai'] : ($op['updated_at'] ?? date('Y-m-d H:i:s'));
            $tSelesai = strtotime(date('Y-m-d', strtotime($waktuSelesai)));
            if ($tSelesai === false) $tSelesai = $tMulai;

            if ($isReopened) {
                $tReopen = strtotime(date('Y-m-d', strtotime((string)$op['waktu_reopen'])));
                $xHari = max(1, (int)floor(($tSelesai - $tReopen) / 86400) + 1);
                $baseHari = self::getBaseHariSebelumReopen($op);
                $totalHari = $baseHari + $xHari;
                $hLabel = "H{$baseHari} + {$xHari}";
                return [
                    'status' => 'CLOSED_REOPEN',
                    'teks' => "1 s/d {$baseHari} Hari + {$xHari} Hari Tambahan",
                    'subteks' => "Perpanjangan Selesai {$hLabel} (Total {$totalHari} Hari)",
                    'badge' => "{$hLabel} Selesai",
                    'h_display' => $hLabel,
                    'h_full' => "{$hLabel} (Selesai)",
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
                    'h_display' => "H.{$durasiHari}",
                    'h_full' => "H.{$durasiHari} (Selesai)",
                    'is_extended' => false,
                    'total_hari' => $durasiHari,
                    'x_hari' => 0
                ];
            }
        }
    }

    /**
     * Hitung Statistik Korban SAR (Total, Selamat, Meninggal, DP, Hilang).
     */
    public static function hitungStatistikKorban(array $korbanList, ?int $totalPob = null): array {
        $countIdentified = count($korbanList);
        $pob = ($totalPob !== null && $totalPob > 0) ? max($totalPob, $countIdentified) : $countIdentified;
        $unrecorded = max(0, $pob - $countIdentified);

        $stats = [
            'total' => $pob,
            'identified' => $countIdentified,
            'unrecorded_dp' => $unrecorded,
            'pob' => $pob,
            's' => 0,
            'md' => 0,
            'dp' => $unrecorded,
            'h' => 0
        ];

        foreach ($korbanList as $k) {
            $status = strtoupper((string)($k['status_korban'] ?? 'DP'));
            if ($status === 'S' || $status === 'SELAMAT') $stats['s']++;
            elseif ($status === 'MD' || $status === 'MENINGGAL DUNIA') $stats['md']++;
            elseif ($status === 'DP' || $status === 'DALAM PENCARIAN') $stats['dp']++;
            elseif ($status === 'H' || $status === 'HILANG / NIHIL') $stats['h']++;
            else $stats['dp']++;
        }

        // Alias penamaan ramah template untuk mencegah undefined index notice
        $stats['total_pob'] = $pob;
        $stats['selamat'] = $stats['s'];
        $stats['meninggal'] = $stats['md'];
        $stats['dalam_pencarian'] = $stats['dp'];
        $stats['hilang'] = $stats['h'];

        return $stats;
    }

    /**
     * Dapatkan label ramah tampilan status korban.
     */
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
}
