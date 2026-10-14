<?php
/**
 * QR-SAR - Operational Report Hierarchy & Inheritance Helper
 * Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas
 *
 * Implements the operational reporting hierarchy:
 * Laporan Awal (H.1) -> Lapbang H.1 -> Renops H.2 -> Lapbang H.2 -> ... -> H.7
 */

declare(strict_types=1);

class HierarchyHelper {
    /**
     * Extracts integer day number from strings like "H.1", "H.2", "H.7"
     */
    public static function extractDayNumber(?string $hariKe): int {
        if (empty($hariKe)) return 1;
        if (preg_match('/\d+/', $hariKe, $m)) {
            return (int)$m[0];
        }
        return 1;
    }

    /**
     * Returns a sorted chronological timeline of all documents in the operation
     */
    public static function getOperationalTimeline(array $ops): array {
        $docs = [];

        // 1. Base: Laporan Awal (H.1)
        $docs[] = [
            'id' => 'awal-' . ($ops['id_operasi'] ?? 0),
            'type' => 'LAPORAN_AWAL',
            'label' => 'Laporan Awal (File Aktif)',
            'hari_ke' => 'H.1',
            'day_number' => 1,
            'waktu' => $ops['waktu_berangkat'] ?: ($ops['waktu_lapor'] ?: ($ops['created_at'] ?? '')),
            'unsur' => $ops['detail_unsur'] ?? [],
            'alut' => $ops['detail_alut'] ?? [],
            'cuaca' => $ops['cuaca'] ?? '',
            'korban' => $ops['korban'] ?? [],
            'is_closed' => false
        ];

        $renopsList = $ops['renops'] ?? [];
        $lapbangList = $ops['laporan_harian'] ?? [];

        // Sort Renops by day
        usort($renopsList, function($a, $b) {
            $dayA = self::extractDayNumber($a['hari_ke'] ?? '1');
            $dayB = self::extractDayNumber($b['hari_ke'] ?? '1');
            if ($dayA !== $dayB) return $dayA <=> $dayB;
            return strcmp($a['created_at'] ?? ($a['waktu_briefing'] ?? ''), $b['created_at'] ?? ($b['waktu_briefing'] ?? ''));
        });

        // Sort Lapbang by day
        usort($lapbangList, function($a, $b) {
            $dayA = self::extractDayNumber($a['hari_ke'] ?? '1');
            $dayB = self::extractDayNumber($b['hari_ke'] ?? '1');
            if ($dayA !== $dayB) return $dayA <=> $dayB;
            return strcmp($a['waktu_laporan'] ?? '', $b['waktu_laporan'] ?? '');
        });

        $maxDay = 1;
        foreach ($renopsList as $r) {
            $maxDay = max($maxDay, self::extractDayNumber($r['hari_ke'] ?? '1'));
        }
        foreach ($lapbangList as $l) {
            $maxDay = max($maxDay, self::extractDayNumber($l['hari_ke'] ?? '1'));
        }

        for ($d = 1; $d <= $maxDay; $d++) {
            // Renops for day $d
            foreach ($renopsList as $r) {
                if (self::extractDayNumber($r['hari_ke'] ?? '1') === $d) {
                    $unsur = !empty($r['detail_unsur']) && is_array($r['detail_unsur']) ? $r['detail_unsur'] : ($ops['detail_unsur'] ?? []);
                    $alut = !empty($r['detail_alut']) && is_array($r['detail_alut']) ? $r['detail_alut'] : ($ops['detail_alut'] ?? []);
                    $docs[] = [
                        'id' => 'renops-' . $r['id_renops'],
                        'type' => 'RENOPS',
                        'label' => 'Renops ' . ($r['hari_ke'] ?: "H.{$d}"),
                        'hari_ke' => $r['hari_ke'] ?: "H.{$d}",
                        'day_number' => $d,
                        'waktu' => $r['waktu_briefing'] ?: ($r['created_at'] ?? ''),
                        'unsur' => $unsur,
                        'alut' => $alut,
                        'cuaca' => $r['cuaca'] ?? '',
                        'raw' => $r,
                        'is_closed' => false
                    ];
                }
            }

            // Lapbang for day $d
            foreach ($lapbangList as $l) {
                if (self::extractDayNumber($l['hari_ke'] ?? '1') === $d) {
                    $isPenutupan = strtoupper($l['jenis_laporan'] ?? '') === 'PENUTUPAN';
                    $unsur = !empty($l['detail_unsur']) && is_array($l['detail_unsur']) ? $l['detail_unsur'] : ($ops['detail_unsur'] ?? []);
                    $alut = !empty($l['detail_alut']) && is_array($l['detail_alut']) ? $l['detail_alut'] : ($ops['detail_alut'] ?? []);
                    $docs[] = [
                        'id' => 'lapbang-' . $l['id_laporan'],
                        'type' => $isPenutupan ? 'PENUTUPAN' : 'LAPBANG',
                        'label' => $isPenutupan ? "Lapbang Penutupan ({$l['hari_ke']})" : "Lapbang " . ($l['hari_ke'] ?: "H.{$d}"),
                        'hari_ke' => $l['hari_ke'] ?: "H.{$d}",
                        'day_number' => $d,
                        'waktu' => $l['waktu_laporan'] ?? '',
                        'unsur' => $unsur,
                        'alut' => $alut,
                        'cuaca' => $l['cuaca'] ?? ($ops['cuaca'] ?? ''),
                        'raw' => $l,
                        'is_closed' => $isPenutupan
                    ];
                }
            }
        }

        return $docs;
    }

    /**
     * Computes default form parameters for a NEW Renops based on latest document in hierarchy
     * Hierarchy: Laporan Awal -> Lapbang H.1 -> Renops H.2 -> Lapbang H.2 -> Renops H.3 ...
     */
    public static function getNextRenopsDefaults(array $ops): array {
        $renopsList = $ops['renops'] ?? [];
        $lapbangList = $ops['laporan_harian'] ?? [];

        $maxRenopsDay = 0;
        foreach ($renopsList as $r) {
            $maxRenopsDay = max($maxRenopsDay, self::extractDayNumber($r['hari_ke'] ?? '1'));
        }

        $maxLapbangDay = 0;
        $latestLapbang = null;
        foreach ($lapbangList as $l) {
            $day = self::extractDayNumber($l['hari_ke'] ?? '1');
            if ($day >= $maxLapbangDay) {
                $maxLapbangDay = $day;
                $latestLapbang = $l;
            }
        }

        $targetDayNumber = 1;
        $predecessorLabel = 'Laporan Awal';
        $inheritedUnsur = $ops['detail_unsur'] ?? [];
        $inheritedAlut = $ops['detail_alut'] ?? [];
        $inheritedCuaca = $ops['cuaca'] ?: 'Cerah Berawan, Angin Barat 5-15 Kts, Gelombang 0.5 - 1.25 M';

        if ($maxLapbangDay >= 1 && $latestLapbang) {
            // Lapbang H.1 exists -> Next Renops is H.2
            // Inherits directly from the latest Lapbang snapshot
            $targetDayNumber = $maxLapbangDay + 1;
            $predecessorLabel = "Lapbang H.{$maxLapbangDay}";
            if (!empty($latestLapbang['detail_unsur']) && is_array($latestLapbang['detail_unsur'])) {
                $inheritedUnsur = $latestLapbang['detail_unsur'];
            }
            if (!empty($latestLapbang['detail_alut']) && is_array($latestLapbang['detail_alut'])) {
                $inheritedAlut = $latestLapbang['detail_alut'];
            }
            if (!empty($latestLapbang['cuaca'])) {
                $inheritedCuaca = $latestLapbang['cuaca'];
            }
        } elseif ($maxRenopsDay >= 1) {
            // Renops H.1 exists without Lapbang -> Next Renops is H.2
            $targetDayNumber = $maxRenopsDay + 1;
            $predecessorLabel = "Renops H.{$maxRenopsDay}";
            foreach ($renopsList as $r) {
                if (self::extractDayNumber($r['hari_ke'] ?? '1') === $maxRenopsDay) {
                    if (!empty($r['detail_unsur']) && is_array($r['detail_unsur'])) {
                        $inheritedUnsur = $r['detail_unsur'];
                    }
                    if (!empty($r['detail_alut']) && is_array($r['detail_alut'])) {
                        $inheritedAlut = $r['detail_alut'];
                    }
                    if (!empty($r['cuaca'])) {
                        $inheritedCuaca = $r['cuaca'];
                    }
                    break;
                }
            }
        } else {
            // Day 1 initial Renops
            $targetDayNumber = 1;
            $predecessorLabel = 'Laporan Awal (File Aktif)';
            $inheritedUnsur = $ops['detail_unsur'] ?? [];
            $inheritedAlut = $ops['detail_alut'] ?? [];
        }

        // Cap target day number at 7 (Standard Basarnas SAR limit)
        $baseDateStr = TemplateHelper::getWaktuMulaiAktual($ops);
        $baseYmd = date('Y-m-d', strtotime($baseDateStr));
        $todayYmd = date('Y-m-d');

        // Hitung hari kalender dari waktu mulai operasi ke hari ini
        $t1 = strtotime($baseYmd);
        $t2 = strtotime($todayYmd);
        $diffDaysToday = ($t1 !== false && $t2 !== false) ? (int)floor(($t2 - $t1) / 86400) : 0;
        $dayByDate = max(1, $diffDaysToday + 1);

        // Penghitungan hari aktual berdasarkan tanggal kalender (bukan hitung jumlah renops)
        $targetDayNumber = $dayByDate;
        $targetDateStr = $todayYmd;

        $isMaxDay = ($targetDayNumber >= 7);
        $hariKe = "H.{$targetDayNumber}";

        $lkp = $ops['koordinat'] ?: '03° 58\' 30" S - 122° 35\' 12" E';
        $defaultSrus = [
            [
                'nama_sru' => 'SRU 1 (Tim Rescue Kansar Kendari)',
                'koordinat_area' => "Sektor A: {$lkp}\nPenyisiran sekitar LKP radius " . ($ops['jarak'] ?: '10 Nm'),
                'luas_area' => ($targetDayNumber * 8) . ' Nm²',
                'pola_pencarian' => 'Parallel Track Search'
            ],
            [
                'nama_sru' => 'SRU 2 (Unsur Gabungan & Nelayan)',
                'koordinat_area' => 'Penyisiran visual di sepanjang pesisir pantai dan pulau terdekat ' . ($ops['lokasi_kejadian'] ?: ''),
                'luas_area' => max(4, $targetDayNumber * 4) . ' Nm²',
                'pola_pencarian' => 'Creeping Line Search'
            ]
        ];

        return [
            'hari_ke' => $hariKe,
            'target_day_number' => $targetDayNumber,
            'target_date_str' => $targetDateStr,
            'inherited_unsur' => $inheritedUnsur,
            'inherited_alut' => $inheritedAlut,
            'inherited_cuaca' => $inheritedCuaca,
            'default_srus' => $defaultSrus,
            'predecessor_label' => $predecessorLabel,
            'is_max_day' => $isMaxDay
        ];
    }

    /**
     * Computes default form parameters for a NEW Lapbang based on latest document in hierarchy
     * Hierarchy: Laporan Awal -> Lapbang H.1 -> Renops H.2 -> Lapbang H.2 -> Renops H.3 ...
     */
    public static function getNextLapbangDefaults(array $ops): array {
        $renopsList = $ops['renops'] ?? [];
        $lapbangList = $ops['laporan_harian'] ?? [];

        $maxRenopsDay = 0;
        $latestRenops = null;
        foreach ($renopsList as $r) {
            $day = self::extractDayNumber($r['hari_ke'] ?? '1');
            if ($day >= $maxRenopsDay) {
                $maxRenopsDay = $day;
                $latestRenops = $r;
            }
        }

        $maxLapbangDay = 0;
        $latestLapbang = null;
        foreach ($lapbangList as $l) {
            $day = self::extractDayNumber($l['hari_ke'] ?? '1');
            if ($day >= $maxLapbangDay) {
                $maxLapbangDay = $day;
                $latestLapbang = $l;
            }
        }

        $baseDateStr = TemplateHelper::getWaktuMulaiAktual($ops);
        $baseYmd = date('Y-m-d', strtotime($baseDateStr));
        $todayYmd = date('Y-m-d');
        $t1 = strtotime($baseYmd);
        $t2 = strtotime($todayYmd);
        $diffDaysToday = ($t1 !== false && $t2 !== false) ? (int)floor(($t2 - $t1) / 86400) : 0;
        $dayByDate = max(1, $diffDaysToday + 1);

        $targetDayNumber = $dayByDate;
        $predecessorLabel = 'Laporan Awal';
        $inheritedUnsur = $ops['detail_unsur'] ?? [];
        $inheritedAlut = $ops['detail_alut'] ?? [];
        $inheritedCuaca = $ops['cuaca'] ?: 'Cerah Berawan, Angin Barat 5-15 Kts, Gelombang 0.5 - 1.25 M';

        $matchingRenops = null;
        foreach ($renopsList as $r) {
            if (self::extractDayNumber($r['hari_ke'] ?? '1') === $targetDayNumber) {
                $matchingRenops = $r;
                break;
            }
        }

        if ($matchingRenops) {
            // Inherits directly from Renops of today / same target day
            $predecessorLabel = "Renops H.{$targetDayNumber}";
            if (!empty($matchingRenops['detail_unsur']) && is_array($matchingRenops['detail_unsur'])) {
                $inheritedUnsur = $matchingRenops['detail_unsur'];
            }
            if (!empty($matchingRenops['detail_alut']) && is_array($matchingRenops['detail_alut'])) {
                $inheritedAlut = $matchingRenops['detail_alut'];
            }
            if (!empty($matchingRenops['cuaca'])) {
                $inheritedCuaca = $matchingRenops['cuaca'];
            }
        } elseif ($latestLapbang) {
            $predecessorLabel = "Lapbang H." . self::extractDayNumber($latestLapbang['hari_ke'] ?? '1');
            if (!empty($latestLapbang['detail_unsur']) && is_array($latestLapbang['detail_unsur'])) {
                $inheritedUnsur = $latestLapbang['detail_unsur'];
            }
            if (!empty($latestLapbang['detail_alut']) && is_array($latestLapbang['detail_alut'])) {
                $inheritedAlut = $latestLapbang['detail_alut'];
            }
            if (!empty($latestLapbang['cuaca'])) {
                $inheritedCuaca = $latestLapbang['cuaca'];
            }
        } else {
            // First report on Day 1: inherits directly from Laporan Awal
            $predecessorLabel = 'Laporan Awal (File Aktif)';
            $inheritedUnsur = $ops['detail_unsur'] ?? [];
            $inheritedAlut = $ops['detail_alut'] ?? [];
        }

        $isMaxDay = ($targetDayNumber >= 7);
        $hariKe = "H.{$targetDayNumber}";

        $nextDayLabel = ($targetDayNumber >= 7) ? 'evaluasi penutupan ops' : "H." . ($targetDayNumber + 1);
        $suggestedIsiLaporan = "Tim SAR Gabungan telah melaksanakan pencarian Operasi SAR Hari Ke-{$targetDayNumber} ({$hariKe}) sesuai Rencana Operasi. Hingga sore hari pencarian dihentikan sementara dengan hasil NIHIL dan akan dilanjutkan esok hari ({$nextDayLabel}) sesuai Renops Harian.";

        return [
            'hari_ke' => $hariKe,
            'target_day_number' => $targetDayNumber,
            'inherited_unsur' => $inheritedUnsur,
            'inherited_alut' => $inheritedAlut,
            'inherited_cuaca' => $inheritedCuaca,
            'inherited_korban' => $ops['korban'] ?? [],
            'suggested_isi_laporan' => $suggestedIsiLaporan,
            'predecessor_label' => $predecessorLabel,
            'is_max_day' => $isMaxDay
        ];
    }
}
