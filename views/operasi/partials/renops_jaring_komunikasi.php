<?php
/**
 * QR-SAR - Diagram Jaring Komunikasi & Koordinasi Operasi SAR (SVG inline, meniru lampiran RENOPS).
 * Kotak Kantor memakai satker_nama + kontak dari Pengaturan; kotak lain teks statis lampiran (K4).
 * Gaya ditulis sebagai atribut SVG (bukan CSS halaman) agar ikut tergambar saat diekspor html2canvas.
 * Variabel: $doc (Kontrak Data Dokumen).
 */

declare(strict_types=1);

$jkKontak = $doc['kontak'] ?? [];
$jkRadio = [
    'Radio Komunikasi : HF Radio, VHF Radio (FM, AM, Amatir,', 'Marine), VOIP, ROIP',
    'Sistem Interkoneksi, Pemetaan Digital, Pengendali Suara,', 'Komunikasi Terpadu',
    'Video Conference', 'Jaringan Protokol Internet', 'Satelit (Telepon dan/atau Modem)',
];
$jkKantor = array_merge(
    trim((string)($jkKontak['telp'] ?? '')) !== '' ? ['Telepon : ' . trim((string)$jkKontak['telp'])] : [],
    ['Com Center : +62811-4034-115', 'Faksimile : (0401) 3196558'], // statis seperti lampiran (R16)
    $jkRadio,
    trim((string)($jkKontak['email'] ?? '')) !== '' ? ['Email : ' . trim((string)$jkKontak['email'])] : []
);
/** Teks rata tengah per baris mulai dari y, jarak antarbaris $dy. */
$jkTeks = function (array $baris, float $x, float $y, float $dy, string $attr = '') {
    $out = '';
    foreach ($baris as $i => $b) {
        $out .= '<text x="' . $x . '" y="' . ($y + $i * $dy) . '"' . $attr . '>' . e($b) . '</text>';
    }
    return $out;
};
$jkJudul = ' font-size="27" font-weight="bold" fill="#000000"';
?>
<svg class="jaring-svg" viewBox="0 0 1663 1900" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Jaring Komunikasi dan Koordinasi Operasi SAR" style="display:block;width:136mm;max-width:100%;height:auto;margin:0 auto;">
    <defs>
        <marker id="jkPanah" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse">
            <path d="M0,0 L10,5 L0,10 z" fill="#000000"/>
        </marker>
    </defs>
    <g fill="#ffffff" stroke="#000000" stroke-width="4">
        <rect x="417" y="15" width="830" height="455"/>
        <rect x="1292" y="15" width="355" height="182" fill="#ffffff"/>
        <rect x="417" y="560" width="830" height="505" fill="#ffffff" stroke="#000000" stroke-width="5"/>
        <rect x="15" y="625" width="312" height="375"/>
        <rect x="1337" y="625" width="310" height="375"/>
        <rect x="417" y="1260" width="830" height="480"/>
        <rect x="1337" y="1363" width="310" height="233"/>
    </g>
    <!-- Garis tegas = pengendalian -->
    <g fill="none" stroke="#000000" stroke-width="4" marker-end="url(#jkPanah)">
        <path d="M637 470 V556"/>
        <path d="M637 1065 V1256"/>
        <path d="M1156 1065 V1156 H1428 V1359"/>
        <path d="M1545 113 H1620"/>
    </g>
    <!-- Garis putus-putus = koordinasi -->
    <g fill="none" stroke="#000000" stroke-width="4" stroke-dasharray="14 10" marker-end="url(#jkPanah)">
        <path d="M922 560 V474"/>
        <path d="M922 1260 V1069"/>
        <path d="M1363 1363 V1220 H1039 V1069"/>
        <path d="M1545 166 H1620"/>
        <path d="M413 288 H171 V621" marker-start="url(#jkPanah)"/>
        <path d="M1251 288 H1492 V621" marker-start="url(#jkPanah)"/>
        <path d="M331 812 H413" marker-start="url(#jkPanah)"/>
        <path d="M1251 812 H1333" marker-start="url(#jkPanah)"/>
        <path d="M1530 1004 V1359" marker-start="url(#jkPanah)"/>
        <path d="M197 1004 V1557 H413" marker-start="url(#jkPanah)"/>
        <path d="M93 1004 V1867 H1466 V1600" marker-start="url(#jkPanah)"/>
        <path d="M1251 1478 H1333" marker-start="url(#jkPanah)"/>
    </g>
    <g font-family="Arial, Helvetica, sans-serif" font-size="22" fill="#000000" text-anchor="middle">
        <text x="832" y="66"<?= $jkJudul ?>>Badan SAR Nasional (Kantor Pusat)</text>
        <?= $jkTeks(['Telepon : 021-65867510 / 65867511', 'Faksimile : 021-65867512', 'Emergency Call : 115', ...$jkRadio], 832, 154, 27) ?>

        <text x="1310" y="60" text-anchor="start" font-weight="bold" font-size="23">Keterangan</text>
        <text x="1310" y="120" text-anchor="start">Jaring Pengendalian</text>
        <text x="1310" y="173" text-anchor="start">Jaring Koordinasi</text>

        <text x="832" y="612"<?= $jkJudul ?>><?= e((string)($doc['meta']['satker_nama'] ?? '')) ?></text>
        <?= $jkTeks($jkKantor, 832, 722, 27) ?>

        <?= $jkTeks(['Instansi Pemerintah', 'Berpotensi', 'Pencarian dan', 'Pertolongan'], 171, 770, 32, $jkJudul) ?>
        <?= $jkTeks(['Potensi Pencarian', 'dan Pertolongan'], 1492, 802, 32, $jkJudul) ?>

        <text x="832" y="1310"<?= $jkJudul ?>>Pos dan Unit Pencarian dan Pertolongan</text>
        <?= $jkTeks(['Telepon Pos Wakatobi, Bau-Bau, Kolaka : (Korpos)', 'Telepon Unit Raha/Muna, Luwu Timur, Konawe Utara : (Korpos)', ...$jkRadio], 832, 1422, 27) ?>

        <?= $jkTeks(['Pos dan Unit', 'Pencarian dan', 'Pertolongan'], 1492, 1454, 32, $jkJudul) ?>
    </g>
</svg>
