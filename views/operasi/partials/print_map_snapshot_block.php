<?php
/**
 * QR-SAR - Partial Blok Visual Peta Taktis 16:9 & Tabel Keterangan Komponen untuk Dokumen Cetak Resmi
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Variabel yang diharapkan:
 * - $mapSnapshot (array|null): Hasil dari DokumenPetaSnapshotModel::getSnapshot(...)
 * - $mapBlockTitle (string, opsional): Sub-judul blok peta pada BAB relevan
 */

if (empty($mapSnapshot) || (empty($mapSnapshot['image_data_uri']) && empty($mapSnapshot['image_url']))) {
    return;
}

if (!function_exists('e')) {
    function e($val): string {
        return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
    }
}

$imgSrc = !empty($mapSnapshot['image_data_uri']) ? $mapSnapshot['image_data_uri'] : $mapSnapshot['image_url'];
$blockTitle = !empty($mapBlockTitle)
    ? $mapBlockTitle
    : (!empty($mapSnapshotSectionTitle) ? $mapSnapshotSectionTitle : 'VISUALISASI PETA TAKTIS OPERASI SAR (LKP, SEARCH AREA & PENEMUAN KORBAN)');
$ketTambahan = trim((string)($mapSnapshot['keterangan_tambahan'] ?? ''));
$bounds = is_array($mapSnapshot['bounds'] ?? null) ? $mapSnapshot['bounds'] : [];
$komponen = is_array($mapSnapshot['komponen'] ?? null) ? $mapSnapshot['komponen'] : [];

$pointsList = is_array($komponen['points'] ?? null) ? $komponen['points'] : [];
$polygonsList = is_array($komponen['polygons'] ?? null) ? $komponen['polygons'] : [];
$patternsList = is_array($komponen['patterns'] ?? null) ? $komponen['patterns'] : [];
$routesList = is_array($komponen['routes'] ?? null) ? $komponen['routes'] : [];
$unitLabel = !empty($komponen['unit']) ? strtoupper((string)$komponen['unit']) : 'NM';

// Gabungkan seluruh komponen ke dalam baris tabel legenda yang informatif dan terstruktur
$tableRows = [];

// 1. Titik Taktis (Kantor SAR, Dermaga SAR, LKP, Penemuan Korban, Pos SAR, dll)
foreach ($pointsList as $pt) {
    $cat = trim((string)($pt['category'] ?? 'Titik Taktis'));
    $lbl = trim((string)($pt['label'] ?? $cat));
    $dms = trim((string)($pt['dms'] ?? ''));
    if ($dms === '' && isset($pt['lat'], $pt['lng'])) {
        $dms = $pt['lat'] . ', ' . $pt['lng'];
    }
    $desc = trim((string)($pt['description'] ?? ''));

    // Tentukan logo/simbol visual berdasarkan kategori
    $catLower = strtolower($cat);
    $catIcon = '📍';
    if (str_contains($catLower, 'kantor sar')) {
        $catIcon = '🏢';
    } elseif (str_contains($catLower, 'dermaga')) {
        $catIcon = '⚓';
    } elseif (str_contains($catLower, 'lkp') || str_contains($catLower, 'last known')) {
        $catIcon = '🚨';
    } elseif (str_contains($catLower, 'korban') || str_contains($catLower, 'penemuan')) {
        $catIcon = '⚠️';
    } elseif (str_contains($catLower, 'pos sar')) {
        $catIcon = '🏠';
    }

    $customIcon = !empty($pt['customIconUrl']) ? trim((string)$pt['customIconUrl']) : '';

    $tableRows[] = [
        'tipe' => 'POINT',
        'kategori_icon' => $catIcon,
        'custom_icon' => $customIcon,
        'kategori' => $cat,
        'nama' => $lbl,
        'dms' => $dms,
        'deskripsi' => $desc,
        'data' => $pt
    ];
}

// 2. Search Area (Persegi, Poligon, Lingkaran)
foreach ($polygonsList as $poly) {
    $rawShape = strtolower(trim((string)($poly['shapeType'] ?? 'polygon')));
    $shapeBadge = 'Poligon';
    $shapeIcon = '⬡';
    if ($rawShape === 'rectangle' || $rawShape === 'box' || $rawShape === 'persegi') {
        $shapeBadge = 'Persegi';
        $shapeIcon = '🔲';
    } elseif ($rawShape === 'circle' || $rawShape === 'lingkaran') {
        $shapeBadge = 'Lingkaran';
        $shapeIcon = '⭕';
    }

    $nama = trim((string)($poly['name'] ?? 'Search Area'));
    $areaText = trim((string)($poly['area_text'] ?? ''));
    $coordsSum = trim((string)($poly['coords_summary'] ?? ''));
    $pointsArr = is_array($poly['points'] ?? null) ? $poly['points'] : [];
    $centerDms = trim((string)($poly['center_dms'] ?? ''));
    $radiusText = trim((string)($poly['radius_text'] ?? ''));

    // Ekstraksi fallback untuk snapshot circle lama yang belum memiliki field center_dms terpisah
    if ($centerDms === '' && ($rawShape === 'circle' || $rawShape === 'lingkaran')) {
        if ($coordsSum !== '' && preg_match('/Pusat:\s*([^\(]+)(?:\(Radius:\s*([^\)]+)\))?/i', $coordsSum, $m)) {
            $centerDms = trim($m[1]);
            if (empty($radiusText) && !empty($m[2])) {
                $radiusText = trim($m[2]);
            }
        }
    }

    $tableRows[] = [
        'tipe' => 'SEARCH_AREA',
        'kategori_icon' => $shapeIcon,
        'kategori' => "Search Area ({$shapeBadge})",
        'nama' => $nama,
        'area_text' => $areaText,
        'coords_summary' => $coordsSum,
        'center_dms' => $centerDms,
        'radius_text' => $radiusText,
        'points' => $pointsArr,
        'shape_type' => $rawShape,
        'data' => $poly
    ];
}

// 3. Pola Pencarian SAR (Creeping Line, Parallel Track, dll)
foreach ($patternsList as $pat) {
    $typeLbl = trim((string)($pat['type_label'] ?? ($pat['type'] ?? 'Pola SAR')));
    $nama = trim((string)($pat['name'] ?? $typeLbl));
    $spacing = trim((string)($pat['spacing_text'] ?? ''));
    $length = trim((string)($pat['length_text'] ?? ''));
    $swept = trim((string)($pat['swept_area_text'] ?? ''));
    $csp = trim((string)($pat['csp_dms'] ?? ''));

    $tableRows[] = [
        'tipe' => 'PATTERN',
        'kategori_icon' => '🧭',
        'kategori' => "Pola SAR ({$typeLbl})",
        'nama' => $nama,
        'spacing' => $spacing,
        'length' => $length,
        'swept' => $swept,
        'csp' => $csp,
        'data' => $pat
    ];
}

// 4. Jalur / Rute Navigasi SAR (Garis Jarak & Arah)
foreach ($routesList as $rt) {
    $nama = trim((string)($rt['name'] ?? 'Rute Navigasi'));
    $dist = trim((string)($rt['distance_text'] ?? ''));
    $brg = trim((string)($rt['bearing_text'] ?? ''));
    $spd = trim((string)($rt['speed_text'] ?? ''));
    $wpSum = trim((string)($rt['waypoints_summary'] ?? ''));
    $startPt = is_array($rt['start_point'] ?? null) ? $rt['start_point'] : null;
    $endPt = is_array($rt['end_point'] ?? null) ? $rt['end_point'] : null;

    $tableRows[] = [
        'tipe' => 'ROUTE',
        'kategori_icon' => '📏',
        'kategori' => 'Jalur / Rute Navigasi',
        'nama' => $nama,
        'distance' => $dist,
        'bearing' => $brg,
        'speed' => $spd,
        'waypoints_summary' => $wpSum,
        'start_point' => $startPt,
        'end_point' => $endPt,
        'data' => $rt
    ];
}

$activeRenderMode = strtoupper(trim((string)($mapRenderMode ?? 'FULL')));
if (!in_array($activeRenderMode, ['FULL', 'IMAGE_ONLY', 'LEGEND_ONLY'], true)) {
    $activeRenderMode = 'FULL';
}
$legendHeaderTitle = !empty($mapLegendTitle)
    ? $mapLegendTitle
    : 'LEGENDA & KETERANGAN KOMPONEN PETA TAKTIS OPERASI SAR';
?>
<div class="print-map-snapshot-section" style="margin-top: 8px; margin-bottom: 8px; page-break-inside: avoid; break-inside: avoid;">
    <?php if ($activeRenderMode === 'FULL' || $activeRenderMode === 'IMAGE_ONLY'): ?>
        <div style="font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 4px; color: #000; display: flex; justify-content: space-between; align-items: baseline;">
            <span><?= e($blockTitle) ?><?= $ketTambahan !== '' ? ' — ' . e($ketTambahan) : '' ?></span>
            <span style="font-size: 8pt; font-weight: normal; color: #334155;">Rasio Peta 16:9 &bull; Satuan Acuan: <?= e($unitLabel) ?></span>
        </div>

        <!-- Bingkai Gambar Peta Rasio 16:9 pada Dokumen Portrait A4 -->
        <div style="width: 100%; aspect-ratio: 16 / 9; max-height: 280px; border: 1.5px solid #000; overflow: hidden; background: #0b1329; position: relative; margin-bottom: 5px;">
            <img src="<?= $imgSrc ?>" alt="Peta Taktis Operasi SAR 16:9" style="width: 100%; height: 100%; object-fit: cover; display: block;">
        </div>
    <?php endif; ?>

    <?php if ($activeRenderMode === 'FULL' || $activeRenderMode === 'LEGEND_ONLY'): ?>
        <?php if ($activeRenderMode === 'LEGEND_ONLY'): ?>
            <div style="font-weight: bold; font-size: 9pt; text-transform: uppercase; margin-bottom: 4px; color: #000; display: flex; justify-content: space-between; align-items: baseline;">
                <span><?= e($legendHeaderTitle) ?></span>
                <span style="font-size: 8pt; font-weight: normal; color: #334155;">Satuan Acuan: <?= e($unitLabel) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tabel Legenda Informatif Seluruh Komponen Peta yang Termuat di Area Capture -->
        <table style="width: 100%; max-width: 100%; border-collapse: collapse; font-size: 8.5pt; border: 1px solid #000; table-layout: fixed;" class="table-dinas">
            <thead>
                <tr style="background-color: #f1f5f9; text-align: left;">
                    <th style="border: 1px solid #000; padding: 4px 5px; width: 5%; text-align: center; font-weight: bold;">NO</th>
                    <th style="border: 1px solid #000; padding: 4px 6px; width: 23%; font-weight: bold;">KATEGORI &amp; SIMBOL</th>
                    <th style="border: 1px solid #000; padding: 4px 6px; width: 22%; font-weight: bold;">NAMA / IDENTITAS</th>
                    <th style="border: 1px solid #000; padding: 4px 6px; width: 50%; font-weight: bold;">KOORDINAT &amp; SPESIFIKASI AREA</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tableRows)): ?>
                    <tr>
                        <td colspan="4" style="border: 1px solid #000; padding: 6px; text-align: center; font-style: italic; color: #475569;">
                            Cuplikan visual area peta operasi SAR (Tidak ada komponen vektor terpisah di dalam batas koordinat capture).
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tableRows as $idx => $r): ?>
                        <tr>
                            <!-- 1. Nomor Urut -->
                            <td style="border: 1px solid #000; padding: 4px 5px; text-align: center; vertical-align: top; font-weight: bold;">
                                <?= $idx + 1 ?>
                            </td>

                            <!-- 2. Kategori dengan Logo / Simbol Visual -->
                            <td style="border: 1px solid #000; padding: 4px 6px; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word;">
                                <div style="display: flex; align-items: center; gap: 4px;">
                                    <?php if (!empty($r['custom_icon'])): ?>
                                        <img src="<?= e($r['custom_icon']) ?>" alt="Icon" style="width: 16px; height: 16px; object-fit: contain; shrink-0;" onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <span style="font-size: 11pt; line-height: 1;"><?= $r['kategori_icon'] ?></span>
                                    <?php endif; ?>
                                    <span style="font-weight: bold; color: #0f172a;"><?= e($r['kategori']) ?></span>
                                </div>
                            </td>

                            <!-- 3. Nama / Identitas Komponen -->
                            <td style="border: 1px solid #000; padding: 4px 6px; vertical-align: top; font-weight: 600; color: #1e293b; word-wrap: break-word; overflow-wrap: break-word;">
                                <?= e($r['nama']) ?>
                            </td>

                            <!-- 4. Koordinat & Spesifikasi Rinci Area Capture -->
                            <td style="border: 1px solid #000; padding: 4px 6px; vertical-align: top; font-size: 8pt; word-wrap: break-word; overflow-wrap: break-word;">
                                <?php if ($r['tipe'] === 'POINT'): ?>
                                    <div style="font-family: 'Courier New', Courier, monospace; font-weight: bold; color: #000000;">
                                        <?= e($r['dms']) ?>
                                    </div>
                                    <?php if (!empty($r['deskripsi'])): ?>
                                        <div style="font-size: 7.5pt; color: #334155; margin-top: 2px; line-height: 1.25;">
                                            <b>Ket:</b> <?= nl2br(e($r['deskripsi'])) ?>
                                        </div>
                                    <?php endif; ?>

                                <?php elseif ($r['tipe'] === 'SEARCH_AREA'): ?>
                                    <?php 
                                    $isCircle = ($r['shape_type'] === 'circle' || $r['shape_type'] === 'lingkaran' || count($r['points'] ?? []) > 12);
                                    ?>
                                    <?php if ($isCircle): ?>
                                        <!-- Khusus Search Area Lingkaran (Circle): Cukup Titik Tengah (Pusat), Radius, dan Luas Area! -->
                                        <div style="line-height: 1.35;">
                                            <?php if (!empty($r['area_text'])): ?>
                                                <div style="font-weight: bold; color: #0f172a; margin-bottom: 2px;">
                                                    &bull; <b>Luas Area:</b> <?= e($r['area_text']) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div style="font-family: 'Courier New', Courier, monospace; font-size: 7.5pt; color: #000000;">
                                                &bull; <b>Titik Pusat:</b> <?= e(!empty($r['center_dms']) ? $r['center_dms'] : (!empty($r['coords_summary']) ? $r['coords_summary'] : '-')) ?>
                                            </div>
                                            <?php if (!empty($r['radius_text'])): ?>
                                                <div style="font-size: 7.5pt; color: #334155; margin-top: 1px;">
                                                    &bull; <b>Radius Pencarian:</b> <?= e($r['radius_text']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <!-- Untuk Poligon & Persegi: Tampilkan Luas dan Titik Sudut A, B, C, D dst -->
                                        <?php if (!empty($r['area_text'])): ?>
                                            <div style="font-weight: bold; margin-bottom: 2px; color: #0f172a;">
                                                &bull; <b>Luas Area:</b> <?= e($r['area_text']) ?>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($r['points']) && is_array($r['points'])): ?>
                                            <div style="font-family: 'Courier New', Courier, monospace; font-size: 7.5pt; margin-top: 2px; line-height: 1.3;">
                                                <?php foreach ($r['points'] as $pIdx => $p): 
                                                    $pLbl = !empty($p['label']) ? $p['label'] : chr(65 + $pIdx);
                                                    $pDms = !empty($p['dms']) ? $p['dms'] : (($p['lat'] ?? '') . ', ' . ($p['lng'] ?? ''));
                                                ?>
                                                    <div>&bull; <b>Titik <?= e($pLbl) ?>:</b> <?= e($pDms) ?></div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif (!empty($r['coords_summary'])): ?>
                                            <div style="font-family: 'Courier New', Courier, monospace; font-size: 7.5pt;">
                                                <?= e($r['coords_summary']) ?>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: #64748b;">-</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                <?php elseif ($r['tipe'] === 'PATTERN'): ?>
                                    <div style="font-size: 7.5pt; margin-bottom: 2px; line-height: 1.3;">
                                        <?php if (!empty($r['spacing'])): ?>
                                            <span>&bull; <b>Track Spacing (S):</b> <?= e($r['spacing']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['length'])): ?>
                                            <span style="margin-left: 4px;">&bull; <b>Panjang Lintasan:</b> <?= e($r['length']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['swept'])): ?>
                                            <span style="margin-left: 4px;">&bull; <b>Sapuan:</b> <?= e($r['swept']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($r['csp'])): ?>
                                        <div style="font-family: 'Courier New', Courier, monospace; font-size: 7.5pt;">
                                            &bull; <b><?= e($r['csp']) ?></b>
                                        </div>
                                    <?php endif; ?>

                                <?php elseif ($r['tipe'] === 'ROUTE'): ?>
                                    <div style="font-size: 7.5pt; margin-bottom: 2px; line-height: 1.3;">
                                        <?php if (!empty($r['distance'])): ?>
                                            <span>&bull; <b>Jarak:</b> <?= e($r['distance']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['bearing'])): ?>
                                            <span style="margin-left: 4px;">&bull; <b>Haluan/Radial:</b> <?= e($r['bearing']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($r['speed'])): ?>
                                            <span style="margin-left: 4px;">&bull; <b>Kecepatan:</b> <?= e($r['speed']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($r['start_point']) && !empty($r['end_point'])): ?>
                                        <div style="font-family: 'Courier New', Courier, monospace; font-size: 7.5pt;">
                                            &bull; <b>Titik A (Awal):</b> <?= e($r['start_point']['dms'] ?? '') ?><br>
                                            &bull; <b>Titik B (Akhir):</b> <?= e($r['end_point']['dms'] ?? '') ?>
                                        </div>
                                    <?php elseif (!empty($r['waypoints_summary'])): ?>
                                        <div style="font-family: 'Courier New', Courier, monospace; font-size: 7.5pt;">
                                            &bull; <?= e($r['waypoints_summary']) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
<?php
// Reset mode render setelah partial dipanggil agar pemanggilan berikutnya kembali ke default FULL
$mapRenderMode = 'FULL';
$mapLegendTitle = null;
$mapBlockTitle = null;
?>
