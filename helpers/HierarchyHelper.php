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
     * Extracts integer day number from strings like "H.1", "H.2", "H.7", or "H7 + 1"
     */
    public static function extractDayNumber(?string $hariKe): int {
        if (empty($hariKe)) return 1;
        if (preg_match('/^H\.?(\d+)\s*\+\s*(\d+)$/i', trim($hariKe), $mExt)) {
            return (int)$mExt[2];
        }
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
            'waktu' => ($ops['waktu_berangkat'] ?? '') ?: (($ops['waktu_lapor'] ?? '') ?: ($ops['created_at'] ?? '')),
            'unsur' => $ops['detail_unsur'] ?? [],
            'alut' => $ops['detail_alut'] ?? [],
            'cuaca' => $ops['cuaca'] ?? '',
            'korban' => $ops['korban'] ?? [],
            'is_closed' => false
        ];

        $renopsList = $ops['renops'] ?? [];
        $lapbangList = $ops['laporan_harian'] ?? ($ops['lapbang'] ?? []);

        // Group documents by calendar date (Y-m-d) so pre-reopen and post-reopen phases stay strictly chronological (PHP-03)
        $byDate = [];

        foreach ($renopsList as $r) {
            $rDateRaw = $r['tanggal_laporan'] ?? ($r['tanggal'] ?? ($r['waktu_briefing'] ?? ($r['created_at'] ?? '')));
            $rTs = !empty($rDateRaw) ? TemplateHelper::parseToTimestamp((string)$rDateRaw) : null;
            $rDate = date('Y-m-d', $rTs ?: time());
            $dynHariKe = TemplateHelper::hitungHariKe($ops, $rDate, $r['hari_ke'] ?? null);
            $dayNum = self::extractDayNumber($dynHariKe);

            $unsur = !empty($r['detail_unsur']) && is_array($r['detail_unsur']) ? $r['detail_unsur'] : ($ops['detail_unsur'] ?? []);
            $alut = !empty($r['detail_alut']) && is_array($r['detail_alut']) ? $r['detail_alut'] : ($ops['detail_alut'] ?? []);

            $byDate[$rDate]['renops'][] = [
                'id' => 'renops-' . ($r['id_renops'] ?? 0),
                'doc_id' => (int)($r['id_renops'] ?? 0),
                'type' => 'RENOPS',
                'label' => 'Renops ' . $dynHariKe,
                'hari_ke' => $dynHariKe,
                'day_number' => $dayNum,
                'waktu' => ($r['waktu_briefing'] ?? '') ?: (($r['tanggal_laporan'] ?? ($r['tanggal'] ?? '')) ?: ($r['created_at'] ?? '')),
                'sort_key' => (string)($r['waktu_briefing'] ?? ($r['tanggal_laporan'] ?? ($r['tanggal'] ?? ($r['created_at'] ?? '')))),
                'unsur' => $unsur,
                'alut' => $alut,
                'cuaca' => $r['cuaca'] ?? '',
                'raw' => $r,
                'is_closed' => false
            ];
        }

        foreach ($lapbangList as $l) {
            $lDateRaw = $l['waktu_laporan'] ?? ($l['tanggal_laporan'] ?? ($l['created_at'] ?? ''));
            $lTs = !empty($lDateRaw) ? TemplateHelper::parseToTimestamp((string)$lDateRaw) : null;
            $lDate = date('Y-m-d', $lTs ?: time());
            $dynHariKe = TemplateHelper::hitungHariKe($ops, $lDate, $l['hari_ke'] ?? null);
            $dayNum = self::extractDayNumber($dynHariKe);

            $isPenutupan = strtoupper($l['jenis_laporan'] ?? '') === 'PENUTUPAN';
            $unsur = !empty($l['detail_unsur']) && is_array($l['detail_unsur']) ? $l['detail_unsur'] : ($ops['detail_unsur'] ?? []);
            $alut = !empty($l['detail_alut']) && is_array($l['detail_alut']) ? $l['detail_alut'] : ($ops['detail_alut'] ?? []);
            $docId = (int)($l['id_laporan'] ?? ($l['id_lapbang'] ?? 0));

            $byDate[$lDate]['lapbang'][] = [
                'id' => 'lapbang-' . $docId,
                'doc_id' => $docId,
                'type' => $isPenutupan ? 'PENUTUPAN' : 'LAPBANG',
                'label' => $isPenutupan ? "Lapbang Penutupan ({$dynHariKe})" : "Lapbang {$dynHariKe}",
                'hari_ke' => $dynHariKe,
                'day_number' => $dayNum,
                'waktu' => ($l['waktu_laporan'] ?? '') ?: ($l['tanggal_laporan'] ?? ''),
                'sort_key' => (string)($l['waktu_laporan'] ?? ($l['tanggal_laporan'] ?? ($l['created_at'] ?? ''))),
                'unsur' => $unsur,
                'alut' => $alut,
                'cuaca' => $l['cuaca'] ?? ($ops['cuaca'] ?? ''),
                'raw' => $l,
                'is_closed' => $isPenutupan
            ];
        }

        ksort($byDate);

        foreach ($byDate as $ymd => $group) {
            if (!empty($group['renops'])) {
                usort($group['renops'], fn($a, $b) => strcmp($a['sort_key'], $b['sort_key']));
                foreach ($group['renops'] as $rDoc) {
                    unset($rDoc['sort_key']);
                    $docs[] = $rDoc;
                }
            }
            if (!empty($group['lapbang'])) {
                usort($group['lapbang'], fn($a, $b) => strcmp($a['sort_key'], $b['sort_key']));
                foreach ($group['lapbang'] as $lDoc) {
                    unset($lDoc['sort_key']);
                    $docs[] = $lDoc;
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
        $baseTs = TemplateHelper::parseToTimestamp($baseDateStr) ?? time();
        $baseYmd = date('Y-m-d', $baseTs);
        $todayYmd = date('Y-m-d');
        $t1 = strtotime($baseYmd);

        // Dynamically compute day numbers from actual report dates in the active cycle (on/after baseYmd)
        $maxRenopsDay = 0;
        $latestRenops = null;
        $latestAnyRenops = null;
        $latestAnyRenopsYmd = '';

        foreach ($renopsList as $r) {
            $rTs = !empty($r['tanggal_laporan'])
                ? TemplateHelper::parseToTimestamp((string)$r['tanggal_laporan'])
                : TemplateHelper::parseToTimestamp((string)($r['waktu_briefing'] ?? ($r['created_at'] ?? '')));
            $rDate = date('Y-m-d', $rTs ?: time());
            if ($rDate >= $latestAnyRenopsYmd) {
                $latestAnyRenopsYmd = $rDate;
                $latestAnyRenops = $r;
            }
            if ($rDate >= $baseYmd) {
                $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $rDate));
                if ($day >= $maxRenopsDay) {
                    $maxRenopsDay = $day;
                    $latestRenops = $r;
                }
            }
        }

        $maxLapbangDay = 0;
        $latestLapbang = null;
        $latestAnyLapbang = null;
        $latestAnyLapbangYmd = '';

        foreach ($lapbangList as $l) {
            $lTs = !empty($l['waktu_laporan'])
                ? TemplateHelper::parseToTimestamp((string)$l['waktu_laporan'])
                : (!empty($l['tanggal_laporan']) ? TemplateHelper::parseToTimestamp((string)$l['tanggal_laporan']) : null);
            $lDate = date('Y-m-d', $lTs ?: time());
            if ($lDate >= $latestAnyLapbangYmd) {
                $latestAnyLapbangYmd = $lDate;
                $latestAnyLapbang = $l;
            }
            if ($lDate >= $baseYmd) {
                $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $lDate));
                if ($day >= $maxLapbangDay) {
                    $maxLapbangDay = $day;
                    $latestLapbang = $l;
                }
            }
        }

        $inheritedUnsur = $ops['detail_unsur'] ?? [];
        $inheritedAlut = $ops['detail_alut'] ?? [];
        $inheritedCuaca = !empty($ops['cuaca']) ? $ops['cuaca'] : 'Cerah Berawan, Angin Barat 5-15 Kts, Gelombang 0.5 - 1.25 M';
        $predecessorLabel = 'Laporan Awal (File Aktif)';

        $refLapbang = $latestLapbang ?? $latestAnyLapbang;
        $refRenops = $latestRenops ?? $latestAnyRenops;

        if ($maxLapbangDay >= 1 && $latestLapbang) {
            $lapDate = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($maxLapbangDay - 1) . " days", $t1)) : $todayYmd;
            $predecessorLabel = "Lapbang " . TemplateHelper::hitungHariKe($ops, $lapDate);
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
            $renDate = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($maxRenopsDay - 1) . " days", $t1)) : $todayYmd;
            $predecessorLabel = "Renops " . TemplateHelper::hitungHariKe($ops, $renDate);
            if (!empty($latestRenops['detail_unsur']) && is_array($latestRenops['detail_unsur'])) {
                $inheritedUnsur = $latestRenops['detail_unsur'];
            }
            if (!empty($latestRenops['detail_alut']) && is_array($latestRenops['detail_alut'])) {
                $inheritedAlut = $latestRenops['detail_alut'];
            }
            if (!empty($latestRenops['cuaca'])) {
                $inheritedCuaca = $latestRenops['cuaca'];
            }
        } elseif ($refLapbang) {
            $refHari = TemplateHelper::hitungHariKe($ops, $latestAnyLapbangYmd, $refLapbang['hari_ke'] ?? null);
            $predecessorLabel = "Lapbang {$refHari}";
            if (!empty($refLapbang['detail_unsur']) && is_array($refLapbang['detail_unsur'])) {
                $inheritedUnsur = $refLapbang['detail_unsur'];
            }
            if (!empty($refLapbang['detail_alut']) && is_array($refLapbang['detail_alut'])) {
                $inheritedAlut = $refLapbang['detail_alut'];
            }
            if (!empty($refLapbang['cuaca'])) {
                $inheritedCuaca = $refLapbang['cuaca'];
            }
        } elseif ($refRenops) {
            $refHari = TemplateHelper::hitungHariKe($ops, $latestAnyRenopsYmd, $refRenops['hari_ke'] ?? null);
            $predecessorLabel = "Renops {$refHari}";
            if (!empty($refRenops['detail_unsur']) && is_array($refRenops['detail_unsur'])) {
                $inheritedUnsur = $refRenops['detail_unsur'];
            }
            if (!empty($refRenops['detail_alut']) && is_array($refRenops['detail_alut'])) {
                $inheritedAlut = $refRenops['detail_alut'];
            }
            if (!empty($refRenops['cuaca'])) {
                $inheritedCuaca = $refRenops['cuaca'];
            }
        }

        // Hitung hari operasional kalender aktif hari ini (relatif terhadap waktu mulai aktual)
        $todayDayNumber = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $todayYmd));

        $maxExistingDay = max($maxRenopsDay, $maxLapbangDay);
        if ($maxExistingDay > 0) {
            // Jika dokumen pada siklus ini sudah ada, target berikutnya minimal hari ini atau hari esok berikutnya
            $targetDayNumber = max($todayDayNumber, $maxExistingDay + 1);
        } else {
            // Jika belum ada Renops/Lapbang pada siklus ini, sesuaikan dengan hari kalender aktif saat ini
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
        $hariKe = TemplateHelper::hitungHariKe($ops, $targetDateStr);

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
            'target_day' => $hariKe,
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
        $baseTs = TemplateHelper::parseToTimestamp($baseDateStr) ?? time();
        $baseYmd = date('Y-m-d', $baseTs);
        $todayYmd = date('Y-m-d');
        $t1 = strtotime($baseYmd);
        $t2 = strtotime($todayYmd);
        $diffDaysToday = ($t1 !== false && $t2 !== false) ? (int)floor(($t2 - $t1) / 86400) : 0;
        $dayByDate = max(1, $diffDaysToday + 1);

        $maxRenopsDay = 0;
        $latestRenops = null;
        foreach ($renopsList as $r) {
            $rTs = !empty($r['tanggal_laporan'])
                ? TemplateHelper::parseToTimestamp((string)$r['tanggal_laporan'])
                : TemplateHelper::parseToTimestamp((string)($r['waktu_briefing'] ?? ($r['created_at'] ?? '')));
            $rDate = date('Y-m-d', $rTs ?: time());
            if ($rDate >= $baseYmd) {
                $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $rDate));
                if ($day >= $maxRenopsDay) {
                    $maxRenopsDay = $day;
                    $latestRenops = $r;
                }
            }
        }

        $maxLapbangDay = 0;
        $latestLapbang = null;
        foreach ($lapbangList as $l) {
            $lTs = !empty($l['waktu_laporan'])
                ? TemplateHelper::parseToTimestamp((string)$l['waktu_laporan'])
                : (!empty($l['tanggal_laporan']) ? TemplateHelper::parseToTimestamp((string)$l['tanggal_laporan']) : null);
            $lDate = date('Y-m-d', $lTs ?: time());
            if ($lDate >= $baseYmd) {
                $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $lDate));
                if ($day >= $maxLapbangDay) {
                    $maxLapbangDay = $day;
                    $latestLapbang = $l;
                }
            }
        }

        // Jika Lapbang hari ini sudah terbit, targetkan hari berikutnya atau hari Renops terbaru
        if ($maxLapbangDay >= $dayByDate) {
            $targetDayNumber = max($dayByDate, $maxRenopsDay, $maxLapbangDay + 1);
        } else {
            $targetDayNumber = max($dayByDate, $maxRenopsDay);
        }
        $targetDayNumber = max(1, $targetDayNumber);

        $targetDateStr = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($targetDayNumber - 1) . " days", $t1)) : $todayYmd;
        $hariKe = TemplateHelper::hitungHariKe($ops, $targetDateStr);

        $predecessorLabel = 'Laporan Awal';
        $inheritedUnsur = $ops['detail_unsur'] ?? [];
        $inheritedAlut = $ops['detail_alut'] ?? [];
        $inheritedCuaca = !empty($ops['cuaca']) ? $ops['cuaca'] : 'Cerah Berawan, Angin Barat 5-15 Kts, Gelombang 0.5 - 1.25 M';

        $matchingRenops = null;
        foreach ($renopsList as $r) {
            $rTs = !empty($r['tanggal_laporan'])
                ? TemplateHelper::parseToTimestamp((string)$r['tanggal_laporan'])
                : TemplateHelper::parseToTimestamp((string)($r['waktu_briefing'] ?? ($r['created_at'] ?? '')));
            $rDate = date('Y-m-d', $rTs ?: time());
            if ($rDate >= $baseYmd) {
                $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $rDate));
                if ($day === $targetDayNumber) {
                    $matchingRenops = $r;
                    break;
                }
            }
        }

        if ($matchingRenops) {
            // Inherits directly from Renops of today / same target day
            $predecessorLabel = "Renops {$hariKe}";
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
            $lapDate = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($maxLapbangDay - 1) . " days", $t1)) : $todayYmd;
            $predecessorLabel = "Lapbang " . TemplateHelper::hitungHariKe($ops, $lapDate);
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
        $nextDateStr = $t1 !== false ? date('Y-m-d', strtotime("+ " . $targetDayNumber . " days", $t1)) : $todayYmd;
        $nextDayLabel = (empty($ops['waktu_reopen']) && $targetDayNumber >= 7)
            ? 'evaluasi penutupan ops'
            : TemplateHelper::hitungHariKe($ops, $nextDateStr);
        $suggestedIsiLaporan = "Tim SAR Gabungan telah melaksanakan pencarian Operasi SAR ({$hariKe}) sesuai Rencana Operasi. Hingga sore hari pencarian dihentikan sementara dengan hasil NIHIL dan akan dilanjutkan esok hari ({$nextDayLabel}) sesuai Renops Harian.";

        return [
            'hari_ke' => $hariKe,
            'target_day' => $hariKe,
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
