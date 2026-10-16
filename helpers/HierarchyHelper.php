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
        $baseDateOps = TemplateHelper::getWaktuMulaiAktual($ops);

        // Sort Renops dynamically by actual day / date
        usort($renopsList, function($a, $b) use ($baseDateOps) {
            $tglA = !empty($a['tanggal_laporan']) ? $a['tanggal_laporan'] : date('Y-m-d', strtotime($a['waktu_briefing'] ?? ($a['created_at'] ?? 'now')));
            $tglB = !empty($b['tanggal_laporan']) ? $b['tanggal_laporan'] : date('Y-m-d', strtotime($b['waktu_briefing'] ?? ($b['created_at'] ?? 'now')));
            $dayA = HierarchyHelper::extractDayNumber(TemplateHelper::hitungHariKe($baseDateOps, $tglA));
            $dayB = HierarchyHelper::extractDayNumber(TemplateHelper::hitungHariKe($baseDateOps, $tglB));
            if ($dayA !== $dayB) return $dayA <=> $dayB;
            return strcmp($a['created_at'] ?? ($a['waktu_briefing'] ?? ''), $b['created_at'] ?? ($b['waktu_briefing'] ?? ''));
        });

        // Sort Lapbang dynamically by actual day / date
        usort($lapbangList, function($a, $b) use ($baseDateOps) {
            $tglA = !empty($a['tanggal_laporan']) ? $a['tanggal_laporan'] : (!empty($a['waktu_laporan']) ? date('Y-m-d', strtotime($a['waktu_laporan'])) : date('Y-m-d'));
            $tglB = !empty($b['tanggal_laporan']) ? $b['tanggal_laporan'] : (!empty($b['waktu_laporan']) ? date('Y-m-d', strtotime($b['waktu_laporan'])) : date('Y-m-d'));
            $dayA = HierarchyHelper::extractDayNumber(TemplateHelper::hitungHariKe($baseDateOps, $tglA));
            $dayB = HierarchyHelper::extractDayNumber(TemplateHelper::hitungHariKe($baseDateOps, $tglB));
            if ($dayA !== $dayB) return $dayA <=> $dayB;
            return strcmp($a['waktu_laporan'] ?? '', $b['waktu_laporan'] ?? '');
        });

        $maxDay = 1;
        foreach ($renopsList as $r) {
            $rDate = !empty($r['tanggal_laporan']) ? $r['tanggal_laporan'] : date('Y-m-d', strtotime($r['waktu_briefing'] ?? ($r['created_at'] ?? 'now')));
            $maxDay = max($maxDay, self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateOps, $rDate)));
        }
        foreach ($lapbangList as $l) {
            $lDate = !empty($l['tanggal_laporan']) ? $l['tanggal_laporan'] : (!empty($l['waktu_laporan']) ? date('Y-m-d', strtotime($l['waktu_laporan'])) : date('Y-m-d'));
            $maxDay = max($maxDay, self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateOps, $lDate)));
        }

        for ($d = 1; $d <= $maxDay; $d++) {
            // Renops for day $d
            foreach ($renopsList as $r) {
                $rDate = !empty($r['tanggal_laporan']) ? $r['tanggal_laporan'] : date('Y-m-d', strtotime($r['waktu_briefing'] ?? ($r['created_at'] ?? 'now')));
                $dynHariKe = TemplateHelper::hitungHariKe($baseDateOps, $rDate);
                $dayNum = self::extractDayNumber($dynHariKe);

                if ($dayNum === $d) {
                    $unsur = !empty($r['detail_unsur']) && is_array($r['detail_unsur']) ? $r['detail_unsur'] : ($ops['detail_unsur'] ?? []);
                    $alut = !empty($r['detail_alut']) && is_array($r['detail_alut']) ? $r['detail_alut'] : ($ops['detail_alut'] ?? []);
                    $docs[] = [
                        'id' => 'renops-' . $r['id_renops'],
                        'type' => 'RENOPS',
                        'label' => 'Renops ' . $dynHariKe,
                        'hari_ke' => $dynHariKe,
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
                $lDate = !empty($l['tanggal_laporan']) ? $l['tanggal_laporan'] : (!empty($l['waktu_laporan']) ? date('Y-m-d', strtotime($l['waktu_laporan'])) : date('Y-m-d'));
                $dynHariKe = TemplateHelper::hitungHariKe($baseDateOps, $lDate);
                $dayNum = self::extractDayNumber($dynHariKe);

                if ($dayNum === $d) {
                    $isPenutupan = strtoupper($l['jenis_laporan'] ?? '') === 'PENUTUPAN';
                    $unsur = !empty($l['detail_unsur']) && is_array($l['detail_unsur']) ? $l['detail_unsur'] : ($ops['detail_unsur'] ?? []);
                    $alut = !empty($l['detail_alut']) && is_array($l['detail_alut']) ? $l['detail_alut'] : ($ops['detail_alut'] ?? []);
                    $docs[] = [
                        'id' => 'lapbang-' . $l['id_laporan'],
                        'type' => $isPenutupan ? 'PENUTUPAN' : 'LAPBANG',
                        'label' => $isPenutupan ? "Lapbang Penutupan ({$dynHariKe})" : "Lapbang {$dynHariKe}",
                        'hari_ke' => $dynHariKe,
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
        if (empty($renopsList) && !empty($ops['id_operasi']) && class_exists('RenopsModel')) {
            $renopsList = RenopsModel::getByOperasiId((int)$ops['id_operasi']);
        }
        $lapbangList = $ops['laporan_harian'] ?? [];
        if (empty($lapbangList) && !empty($ops['id_operasi']) && class_exists('LaporanModel')) {
            $lapbangList = LaporanModel::getByOperasiId((int)$ops['id_operasi']);
        }

        $baseDateStr = TemplateHelper::getWaktuMulaiAktual($ops);
        $baseYmd = date('Y-m-d', strtotime($baseDateStr));
        $todayYmd = date('Y-m-d');

        // Dynamically compute day numbers from actual report dates relative to operation start date
        $maxRenopsDay = 0;
        $latestRenops = null;
        foreach ($renopsList as $r) {
            $rDate = !empty($r['tanggal_laporan']) ? $r['tanggal_laporan'] : date('Y-m-d', strtotime($r['waktu_briefing'] ?? ($r['created_at'] ?? 'now')));
            $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $rDate));
            if ($day >= $maxRenopsDay) {
                $maxRenopsDay = $day;
                $latestRenops = $r;
            }
        }

        $maxLapbangDay = 0;
        $latestLapbang = null;
        foreach ($lapbangList as $l) {
            $lDate = !empty($l['tanggal_laporan']) ? $l['tanggal_laporan'] : (!empty($l['waktu_laporan']) ? date('Y-m-d', strtotime($l['waktu_laporan'])) : date('Y-m-d'));
            $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $lDate));
            if ($day >= $maxLapbangDay) {
                $maxLapbangDay = $day;
                $latestLapbang = $l;
            }
        }

        $inheritedUnsur = $ops['detail_unsur'] ?? [];
        $inheritedAlut = $ops['detail_alut'] ?? [];
        $inheritedCuaca = !empty($ops['cuaca']) ? $ops['cuaca'] : 'Cerah Berawan, Angin Barat 5-15 Kts, Gelombang 0.5 - 1.25 M';
        $predecessorLabel = 'Laporan Awal (File Aktif)';

        if ($maxLapbangDay >= 1 && $latestLapbang) {
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
        } elseif ($maxRenopsDay >= 1 && $latestRenops) {
            $predecessorLabel = "Renops H.{$maxRenopsDay}";
            if (!empty($latestRenops['detail_unsur']) && is_array($latestRenops['detail_unsur'])) {
                $inheritedUnsur = $latestRenops['detail_unsur'];
            }
            if (!empty($latestRenops['detail_alut']) && is_array($latestRenops['detail_alut'])) {
                $inheritedAlut = $latestRenops['detail_alut'];
            }
            if (!empty($latestRenops['cuaca'])) {
                $inheritedCuaca = $latestRenops['cuaca'];
            }
        }

        // Hitung hari operasional kalender aktif hari ini (relatif terhadap waktu mulai aktual)
        $todayDayNumber = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $todayYmd));

        $maxExistingDay = max($maxRenopsDay, $maxLapbangDay);
        if ($maxExistingDay > 0) {
            // Jika dokumen sebelumnya sudah ada, target berikutnya minimal hari ini atau hari esok berikutnya
            $targetDayNumber = max($todayDayNumber, $maxExistingDay + 1);
        } else {
            // Jika belum ada Renops/Lapbang, sesuaikan dengan hari kalender aktif saat ini
            $targetDayNumber = $todayDayNumber;
        }

        $targetDayNumber = max(1, $targetDayNumber);
        $t1 = strtotime($baseYmd);
        if ($t1 !== false) {
            $targetDateStr = date('Y-m-d', strtotime("+ " . ($targetDayNumber - 1) . " days", $t1));
        } else {
            $targetDateStr = $todayYmd;
        }

        $isMaxDay = ($targetDayNumber >= 7);
        $hariKe = "H.{$targetDayNumber}";

        $lkp = !empty($ops['koordinat']) ? $ops['koordinat'] : '03° 58\' 30" S - 122° 35\' 12" E';
        $jarakStr = !empty($ops['jarak']) ? $ops['jarak'] : '10 Nm';
        $lokasiStr = !empty($ops['lokasi_kejadian']) ? $ops['lokasi_kejadian'] : '';
        $defaultSrus = [
            [
                'nama_sru' => 'SRU 1 (Tim Rescue Kansar Kendari)',
                'koordinat_area' => "Sektor A: {$lkp}\nPenyisiran sekitar LKP radius {$jarakStr}",
                'luas_area' => ($targetDayNumber * 8) . ' Nm²',
                'pola_pencarian' => 'Parallel Track Search'
            ],
            [
                'nama_sru' => 'SRU 2 (Unsur Gabungan & Nelayan)',
                'koordinat_area' => 'Penyisiran visual di sepanjang pesisir pantai dan pulau terdekat ' . $lokasiStr,
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
        if (empty($renopsList) && !empty($ops['id_operasi']) && class_exists('RenopsModel')) {
            $renopsList = RenopsModel::getByOperasiId((int)$ops['id_operasi']);
        }
        $lapbangList = $ops['laporan_harian'] ?? [];
        if (empty($lapbangList) && !empty($ops['id_operasi']) && class_exists('LaporanModel')) {
            $lapbangList = LaporanModel::getByOperasiId((int)$ops['id_operasi']);
        }

        $baseDateStr = TemplateHelper::getWaktuMulaiAktual($ops);
        $baseYmd = date('Y-m-d', strtotime($baseDateStr));
        $todayYmd = date('Y-m-d');
        $t1 = strtotime($baseYmd);
        $t2 = strtotime($todayYmd);
        $diffDaysToday = ($t1 !== false && $t2 !== false) ? (int)floor(($t2 - $t1) / 86400) : 0;
        $dayByDate = max(1, $diffDaysToday + 1);

        $maxRenopsDay = 0;
        $latestRenops = null;
        foreach ($renopsList as $r) {
            $rDate = !empty($r['tanggal_laporan']) ? $r['tanggal_laporan'] : date('Y-m-d', strtotime($r['waktu_briefing'] ?? ($r['created_at'] ?? 'now')));
            $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $rDate));
            if ($day >= $maxRenopsDay) {
                $maxRenopsDay = $day;
                $latestRenops = $r;
            }
        }

        $maxLapbangDay = 0;
        $latestLapbang = null;
        foreach ($lapbangList as $l) {
            $lDate = !empty($l['tanggal_laporan']) ? $l['tanggal_laporan'] : (!empty($l['waktu_laporan']) ? date('Y-m-d', strtotime($l['waktu_laporan'])) : date('Y-m-d'));
            $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $lDate));
            if ($day >= $maxLapbangDay) {
                $maxLapbangDay = $day;
                $latestLapbang = $l;
            }
        }

        $targetDayNumber = $dayByDate;
        $predecessorLabel = 'Laporan Awal';
        $inheritedUnsur = $ops['detail_unsur'] ?? [];
        $inheritedAlut = $ops['detail_alut'] ?? [];
        $inheritedCuaca = !empty($ops['cuaca']) ? $ops['cuaca'] : 'Cerah Berawan, Angin Barat 5-15 Kts, Gelombang 0.5 - 1.25 M';

        $matchingRenops = null;
        foreach ($renopsList as $r) {
            $rDate = !empty($r['tanggal_laporan']) ? $r['tanggal_laporan'] : date('Y-m-d', strtotime($r['waktu_briefing'] ?? ($r['created_at'] ?? 'now')));
            $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $rDate));
            if ($day === $targetDayNumber) {
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
