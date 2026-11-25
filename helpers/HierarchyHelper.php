<?php
/**
 * QR-SAR - Operational Report Hierarchy & Inheritance Helper
 * Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas
 *
 * Implements the operational reporting hierarchy:
 * Laporan Awal -> Renops H.1 -> Lapbang H.1 -> Renops H.2 -> Lapbang H.2 -> ... -> H.7
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
            $rDateRaw = $r['tanggal_laporan'] ?? ($r['tanggal'] ?? ($r['waktu_briefing'] ?? ($r['waktu_mulai_ops'] ?? ($r['created_at'] ?? ''))));
            $rTs = !empty($rDateRaw) ? TemplateHelper::parseToTimestamp((string)$rDateRaw) : null;
            $rDate = date('Y-m-d', $rTs ?: time());
            $dynHariKe = TemplateHelper::hitungHariKe($ops, $rDate, $r['hari_ke'] ?? null);
            $dayNum = self::extractDayNumber($dynHariKe);

            $unsur = !empty($r['detail_unsur']) && is_array($r['detail_unsur']) ? $r['detail_unsur'] : ($ops['detail_unsur'] ?? []);
            $alut = !empty($r['detail_alut']) && is_array($r['detail_alut']) ? $r['detail_alut'] : ($ops['detail_alut'] ?? []);
            $rWaktu = ($r['waktu_briefing'] ?? '') ?: (($r['waktu_mulai_ops'] ?? '') ?: (($r['tanggal_laporan'] ?? ($r['tanggal'] ?? '')) ?: ($r['created_at'] ?? '')));

            $byDate[$rDate]['renops'][] = [
                'id' => 'renops-' . ($r['id_renops'] ?? 0),
                'doc_id' => (int)($r['id_renops'] ?? 0),
                'type' => 'RENOPS',
                'label' => 'Renops ' . $dynHariKe,
                'hari_ke' => $dynHariKe,
                'day_number' => $dayNum,
                'waktu' => $rWaktu,
                'sort_key' => (string)$rWaktu,
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
     * Computes default form parameters for a NEW Renops based on latest document in hierarchy.
     * Hierarchy: Laporan Awal -> Renops H.1 -> Lapbang H.1 -> Renops H.2 -> Lapbang H.2 -> Renops H.3 ...
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
                : TemplateHelper::parseToTimestamp((string)($r['waktu_briefing'] ?? ($r['waktu_mulai_ops'] ?? ($r['created_at'] ?? ''))));
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
        $inheritedCuaca = !empty($ops['cuaca']) ? (string)$ops['cuaca'] : 'Cerah Berawan';
        $inheritedGelombang = (string)($ops['gelombang'] ?? '');
        $inheritedAngin = (string)($ops['angin'] ?? '');
        $predecessorLabel = 'Laporan Awal (File Aktif)';

        $applySource = static function (array $src) use (&$inheritedUnsur, &$inheritedAlut, &$inheritedCuaca, &$inheritedGelombang, &$inheritedAngin): void {
            if (!empty($src['detail_unsur']) && is_array($src['detail_unsur'])) {
                $inheritedUnsur = $src['detail_unsur'];
            }
            if (!empty($src['detail_alut']) && is_array($src['detail_alut'])) {
                $inheritedAlut = $src['detail_alut'];
            }
            foreach (['cuaca' => &$inheritedCuaca, 'gelombang' => &$inheritedGelombang, 'angin' => &$inheritedAngin] as $k => &$target) {
                if (!empty($src[$k])) {
                    $target = (string)$src[$k];
                }
            }
        };

        $refLapbang = $latestLapbang ?? $latestAnyLapbang;
        $refRenops = $latestRenops ?? $latestAnyRenops;

        // Kronologis ketat: pada hari sama LAPBANG selalu sesudah RENOPS; hari yang lebih baru selalu menang.
        if ($maxLapbangDay >= 1 || $maxRenopsDay >= 1) {
            if ($maxLapbangDay >= $maxRenopsDay && $latestLapbang) {
                $lapDate = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($maxLapbangDay - 1) . " days", $t1)) : $todayYmd;
                $predecessorLabel = "Lapbang " . TemplateHelper::hitungHariKe($ops, $lapDate);
                $applySource($latestLapbang);
            } elseif ($latestRenops) {
                $renDate = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($maxRenopsDay - 1) . " days", $t1)) : $todayYmd;
                $predecessorLabel = "Renops " . TemplateHelper::hitungHariKe($ops, $renDate);
                $applySource($latestRenops);
            }
        } elseif ($refLapbang || $refRenops) {
            if ($refLapbang && (!$refRenops || $latestAnyLapbangYmd >= $latestAnyRenopsYmd)) {
                $refHari = TemplateHelper::hitungHariKe($ops, $latestAnyLapbangYmd, $refLapbang['hari_ke'] ?? null);
                $predecessorLabel = "Lapbang {$refHari}";
                $applySource($refLapbang);
            } elseif ($refRenops) {
                $refHari = TemplateHelper::hitungHariKe($ops, $latestAnyRenopsYmd, $refRenops['hari_ke'] ?? null);
                $predecessorLabel = "Renops {$refHari}";
                $applySource($refRenops);
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
        $isHariPertama = class_exists('RenopsModel')
            ? RenopsModel::isHariPertama($hariKe)
            : (bool)preg_match('/^(?:H[\.\-\s]*)?1$/i', $hariKe);
        $defaultWaktuBriefing = $isHariPertama ? null : ($targetDateStr . 'T07:00');
        $defaultWaktuMulaiOps = ($isHariPertama && !empty($ops['waktu_berangkat']))
            ? date('Y-m-d\TH:i', strtotime((string)$ops['waktu_berangkat']))
            : ($targetDateStr . 'T07:30');

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
            'is_hari_pertama' => $isHariPertama,
            'default_waktu_briefing' => $defaultWaktuBriefing,
            'default_waktu_mulai_ops' => $defaultWaktuMulaiOps,
            'inherited_unsur' => $inheritedUnsur,
            'inherited_alut' => $inheritedAlut,
            'inherited_cuaca' => $inheritedCuaca,
            'inherited_gelombang' => $inheritedGelombang,
            'inherited_angin' => $inheritedAngin,
            'default_srus' => $defaultSrus,
            'predecessor_label' => $predecessorLabel,
            'is_max_day' => $isMaxDay
        ];
    }

    /**
     * Computes default form parameters for a NEW Lapbang based on latest document in hierarchy.
     * Hierarchy: Laporan Awal -> Renops H.1 -> Lapbang H.1 -> Renops H.2 -> Lapbang H.2 -> Renops H.3 ...
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
        $latestAnyRenops = null;
        $latestAnyRenopsYmd = '';
        foreach ($renopsList as $r) {
            $rTs = !empty($r['tanggal_laporan'])
                ? TemplateHelper::parseToTimestamp((string)$r['tanggal_laporan'])
                : TemplateHelper::parseToTimestamp((string)($r['waktu_briefing'] ?? ($r['waktu_mulai_ops'] ?? ($r['created_at'] ?? ''))));
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

        // Jika Lapbang hari ini sudah terbit, targetkan hari berikutnya atau hari Renops terbaru
        if ($maxLapbangDay >= $dayByDate) {
            $targetDayNumber = max($dayByDate, $maxRenopsDay, $maxLapbangDay + 1);
        } else {
            $targetDayNumber = max($dayByDate, $maxRenopsDay);
        }
        $targetDayNumber = max(1, $targetDayNumber);

        $targetDateStr = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($targetDayNumber - 1) . " days", $t1)) : $todayYmd;
        $hariKe = TemplateHelper::hitungHariKe($ops, $targetDateStr);

        $predecessorLabel = 'Laporan Awal (File Aktif)';
        $inheritedUnsur = $ops['detail_unsur'] ?? [];
        $inheritedAlut = $ops['detail_alut'] ?? [];
        $inheritedCuaca = !empty($ops['cuaca']) ? (string)$ops['cuaca'] : 'Cerah Berawan';
        $inheritedGelombang = (string)($ops['gelombang'] ?? '');
        $inheritedAngin = (string)($ops['angin'] ?? '');

        $applySource = static function (array $src) use (&$inheritedUnsur, &$inheritedAlut, &$inheritedCuaca, &$inheritedGelombang, &$inheritedAngin): void {
            if (!empty($src['detail_unsur']) && is_array($src['detail_unsur'])) {
                $inheritedUnsur = $src['detail_unsur'];
            }
            if (!empty($src['detail_alut']) && is_array($src['detail_alut'])) {
                $inheritedAlut = $src['detail_alut'];
            }
            foreach (['cuaca' => &$inheritedCuaca, 'gelombang' => &$inheritedGelombang, 'angin' => &$inheritedAngin] as $k => &$target) {
                if (!empty($src[$k])) {
                    $target = (string)$src[$k];
                }
            }
        };

        // Cari RENOPS paling baru pada hari target (tanpa break dini agar revisi/RENOPS terakhir menang)
        $matchingRenops = null;
        foreach ($renopsList as $r) {
            $rTs = !empty($r['tanggal_laporan'])
                ? TemplateHelper::parseToTimestamp((string)$r['tanggal_laporan'])
                : TemplateHelper::parseToTimestamp((string)($r['waktu_briefing'] ?? ($r['waktu_mulai_ops'] ?? ($r['created_at'] ?? ''))));
            $rDate = date('Y-m-d', $rTs ?: time());
            if ($rDate >= $baseYmd) {
                $day = self::extractDayNumber(TemplateHelper::hitungHariKe($baseDateStr, $rDate));
                if ($day === $targetDayNumber) {
                    $matchingRenops = $r;
                }
            }
        }

        // Urutan pewaris ketat: RENOPS hari sama -> dokumen terbaru pada siklus aktif (RENOPS lebih baru vs LAPBANG) -> siklus sebelumnya (reopen) -> Laporan Awal
        if ($matchingRenops) {
            $predecessorLabel = "Renops {$hariKe}";
            $applySource($matchingRenops);
        } elseif ($maxRenopsDay > $maxLapbangDay && $latestRenops) {
            $renDate = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($maxRenopsDay - 1) . " days", $t1)) : $todayYmd;
            $predecessorLabel = "Renops " . TemplateHelper::hitungHariKe($ops, $renDate);
            $applySource($latestRenops);
        } elseif ($latestLapbang) {
            $lapDate = $t1 !== false ? date('Y-m-d', strtotime("+ " . ($maxLapbangDay - 1) . " days", $t1)) : $todayYmd;
            $predecessorLabel = "Lapbang " . TemplateHelper::hitungHariKe($ops, $lapDate);
            $applySource($latestLapbang);
        } elseif ($latestAnyLapbang || $latestAnyRenops) {
            if ($latestAnyLapbang && (!$latestAnyRenops || $latestAnyLapbangYmd >= $latestAnyRenopsYmd)) {
                $refHari = TemplateHelper::hitungHariKe($ops, $latestAnyLapbangYmd, $latestAnyLapbang['hari_ke'] ?? null);
                $predecessorLabel = "Lapbang {$refHari}";
                $applySource($latestAnyLapbang);
            } elseif ($latestAnyRenops) {
                $refHari = TemplateHelper::hitungHariKe($ops, $latestAnyRenopsYmd, $latestAnyRenops['hari_ke'] ?? null);
                $predecessorLabel = "Renops {$refHari}";
                $applySource($latestAnyRenops);
            }
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
            'target_date_str' => $targetDateStr,
            'inherited_unsur' => $inheritedUnsur,
            'inherited_alut' => $inheritedAlut,
            'inherited_cuaca' => $inheritedCuaca,
            'inherited_gelombang' => $inheritedGelombang,
            'inherited_angin' => $inheritedAngin,
            'inherited_korban' => $ops['korban'] ?? [],
            'suggested_isi_laporan' => $suggestedIsiLaporan,
            'predecessor_label' => $predecessorLabel,
            'is_max_day' => $isMaxDay
        ];
    }

    /** Nomor hari absolut lintas reopen: "H.3" -> 3, "H7 + 1" -> 8. */
    public static function absoluteDayNumber(string $hariKe): int {
        if (preg_match('/^H\.?\s*(\d+)\s*\+\s*(\d+)$/i', trim($hariKe), $m)) {
            return (int)$m[1] + (int)$m[2];
        }
        return self::extractDayNumber($hariKe);
    }

    /**
     * Sumber data dasar RENOPS hari N: Laporan Awal (H1) atau pendahulu kronologis terdekat < N
     * (pada hari sama LAPBANG mengalahkan RENOPS; hari lebih besar selalu menang; fallback Laporan Awal).
     * Nilai non-kosong milik RENOPS itu sendiri menimpa pendahulu.
     * @return array{hari_num:int,hari_label:string,sumber:string,lapbang:?array,unsur:array,alut:array,cuaca:string,gelombang:string,angin:string}
     */
    public static function resolveRenopsSources(array $ops, array $renops): array {
        $hari = TemplateHelper::hitungHariKe($ops, $renops['tanggal_laporan'] ?? null, $renops['hari_ke'] ?? null);
        $n = self::absoluteDayNumber($hari);
        $arr = fn($v) => is_array($v) ? $v : (is_string($v) && $v !== '' ? (json_decode($v, true) ?: []) : []);

        $lapbang = null;
        $lapDay = 0;
        foreach ($ops['laporan_harian'] ?? [] as $l) {
            $raw = $l['waktu_laporan'] ?? ($l['tanggal_laporan'] ?? null);
            $d = $raw ? self::absoluteDayNumber(TemplateHelper::hitungHariKe($ops, (string)$raw, $l['hari_ke'] ?? null)) : 0;
            // Seri hari sama: laporan paling akhir menang (>=).
            if ($d < $n && $d >= 1 && $d >= $lapDay && ($lapbang === null || $d > $lapDay || strcmp((string)($l['waktu_laporan'] ?? ''), (string)($lapbang['waktu_laporan'] ?? '')) >= 0)) {
                $lapDay = $d;
                $lapbang = $l;
            }
        }

        $prevRenops = null;
        $renDay = 0;
        if ($n >= 2) {
            foreach ($ops['renops'] ?? [] as $r) {
                if ((int)($r['id_renops'] ?? 0) === (int)($renops['id_renops'] ?? -1)) continue;
                $d = self::absoluteDayNumber(TemplateHelper::hitungHariKe($ops, $r['tanggal_laporan'] ?? null, $r['hari_ke'] ?? null));
                if ($d < $n && $d >= 1 && $d >= $renDay) {
                    $renDay = $d;
                    $prevRenops = $r;
                }
            }
        }

        $sumber = 'LAPORAN_AWAL';
        $pred = [
            'unsur' => $arr($ops['detail_unsur'] ?? []),
            'alut' => $arr($ops['detail_alut'] ?? []),
            'cuaca' => (string)($ops['cuaca'] ?? ''),
            'gelombang' => (string)($ops['gelombang'] ?? ''),
            'angin' => (string)($ops['angin'] ?? ''),
        ];
        $src = null;
        if ($n >= 2) {
            if ($lapbang && (!$prevRenops || $lapDay >= $renDay)) {
                $sumber = 'LAPBANG';
                $src = $lapbang;
            } elseif ($prevRenops) {
                $sumber = 'RENOPS';
                $src = $prevRenops;
            }
        }
        if ($src) {
            foreach (['unsur' => 'detail_unsur', 'alut' => 'detail_alut'] as $k => $col) {
                $v = $arr($src[$col] ?? []);
                if ($v) $pred[$k] = $v;
            }
            foreach (['cuaca', 'gelombang', 'angin'] as $wk) {
                if (!empty($src[$wk])) $pred[$wk] = (string)$src[$wk];
            }
        }
        foreach (['unsur' => 'detail_unsur', 'alut' => 'detail_alut'] as $k => $col) {
            $own = $arr($renops[$col] ?? []);
            if ($own) $pred[$k] = $own;
        }
        foreach (['cuaca', 'gelombang', 'angin'] as $wk) {
            if (!empty($renops[$wk])) $pred[$wk] = (string)$renops[$wk];
        }

        return [
            'hari_num' => $n,
            'hari_label' => preg_replace('/^H\.?\s*/i', '', trim($hari)),
            'sumber' => $sumber,
            'lapbang' => $sumber === 'LAPBANG' ? $lapbang : null,
            'unsur' => $pred['unsur'],
            'alut' => $pred['alut'],
            'cuaca' => $pred['cuaca'],
            'gelombang' => $pred['gelombang'],
            'angin' => $pred['angin'],
        ];
    }
}
