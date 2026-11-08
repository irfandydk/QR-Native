<?php
/**
 * QR-SAR - Renderer tunggal Dokumen Rencana Operasi SAR (format SMEAC): A4 portrait, kecuali lembar terakhir
 * (struktur organisasi + pengesahan) A4 landscape.
 * Dipakai editor draf ($mode 'draft'), cetak/unduh draf sebelum terbit ($mode 'pratinjau': watermark DRAF, tanpa QR
 * & tanpa kontrol sunting) serta halaman cetak internal & publik ($mode 'final').
 *
 * Variabel: $doc (Kontrak Data Dokumen), $mode ('draft'|'final'), $qrUrl (?string, wajib untuk 'final'),
 * $versiLabel (string, mis. "v2").
 * Semua teks dari data/suntingan lewat e() (halaman publik tanpa login): tidak pernah HTML mentah.
 *
 * Kontrak DOM mode draft (dipakai editor): pembungkus bagian [data-sec][data-kind=text|list|table|struktur]
 * + span.sec-badge; teks di .sec-body; butir di li; baris tabel tr[data-row] dengan sel [data-col];
 * kolom kontrak yang tidak tampil (grup aset) berupa sel [data-col][hidden]; struktur memakai [data-col] per jabatan.
 */

declare(strict_types=1);

// Lingkup sendiri: helper & variabel lokal tidak bocor ke halaman pemanggil (editor/cetak).
// Sub-partial yang di-include di dalamnya tetap mewarisi lingkup closure ini.
(static function (array $doc, string $mode, ?string $qrUrl, string $versiLabel): void {
$draft = $mode === 'draft';
$pratinjau = $mode === 'pratinjau';
$meta = $doc['meta'] ?? [];
$S = $doc['sections'] ?? [];
$edited = array_flip($doc['edited'] ?? []);
$hariLabel = (string)($meta['hari_label'] ?? '');
$hariNum = (int)($meta['hari_num'] ?? 0);
$satker = (string)($meta['satker_nama'] ?? '');
$jenisKejadian = trim((string)($meta['jenis_kejadian'] ?? ''));
$namaOps = trim((string)($meta['nama_operasi'] ?? ''));
// R7: jenis_kejadian sering kosong; tanpa spasi/titik dua menggantung.
$kopBaris3 = 'Operasi SAR' . ($jenisKejadian !== '' ? " {$jenisKejadian}" : '') . ($namaOps !== '' ? ": {$namaOps}" : '');
$headerBerjalan = "Rencana Operasi SAR Hari ke-{$hariLabel} | {$satker}";
// String CSS di dalam <style>: escape \ " dan < (cegah "</style>" menutup blok).
$cssStr = fn(string $s): string => '"' . strtr($s, ['\\' => '\\\\', '"' => '\\"', '<' => '\\3C ', "\n" => ' ', "\r" => ' ', "\f" => ' ']) . '"';

$nTabel = 0;
$nGambar = 0;
$tabel = function (string $judul) use (&$nTabel): string {
    return '<p class="cap-tabel">Tabel ' . (++$nTabel) . '. ' . e($judul) . '</p>';
};
$gambar = function (string $judul) use (&$nGambar): string {
    return '<figcaption class="cap-gambar">Gambar ' . (++$nGambar) . '. ' . e($judul) . '</figcaption>';
};

// Atribut & lencana sunting: hanya ada di mode draft.
$sec = fn(string $key, string $kind): string => $draft ? ' data-sec="' . e($key) . '" data-kind="' . $kind . '"' : '';
$badge = function (string $key) use ($draft, $edited): string {
    if (!$draft) {
        return '';
    }
    return isset($edited[$key]) ? '<span class="sec-badge is-edited">Disunting</span>' : '<span class="sec-badge">Otomatis</span>';
};
$col = fn(string $c): string => $draft ? ' data-col="' . $c . '"' : '';
$row = $draft ? ' data-row' : '';

/** Teks multi-paragraf: paragraf dipisah baris kosong, baris tunggal jadi <br>. */
$paras = function (string $t): string {
    $parts = preg_split('/\n\s*\n/', trim(str_replace(["\r\n", "\r"], "\n", $t))) ?: [''];
    return implode('', array_map(fn($p) => '<p>' . nl2br(e(trim($p))) . '</p>', $parts));
};
$text = function (string $key, string $class = '') use ($S, $sec, $badge, $paras): string {
    return '<div class="sec ' . $class . '"' . $sec($key, 'text') . '>' . $badge($key)
        . '<div class="sec-body">' . $paras((string)($S[$key] ?? '')) . '</div></div>';
};
$list = function (string $key, string $style) use ($S, $sec, $badge): string {
    $items = $S[$key] ?? [];
    $li = implode('', array_map(fn($x) => '<li>' . e((string)$x) . '</li>', $items));
    return '<div class="sec"' . $sec($key, 'list') . '>' . $badge($key) . '<ol class="lst ' . $style . '">' . $li . '</ol>'
        . ($items ? '' : '<p class="sec-empty">-</p>') . '</div>';
};
/** Baris tabel: $cols = [kolom => kelas sel]; kolom berkelas 'hidden' hanya ada di draft (kolom kontrak tak tampil). */
$rows = function (array $data, array $cols, bool $nomor = false) use ($col, $row, $draft): string {
    $out = '';
    foreach ($data as $i => $r) {
        $out .= '<tr' . $row . '>' . ($nomor ? '<td class="c">' . ($i + 1) . '</td>' : '');
        foreach ($cols as $c => $cls) {
            if ($cls === 'hidden') {
                $out .= $draft ? '<td' . $col($c) . ' hidden>' . e((string)($r[$c] ?? '')) . '</td>' : '';
                continue;
            }
            $out .= '<td class="' . $cls . '"' . $col($c) . '>' . nl2br(e((string)($r[$c] ?? ''))) . '</td>';
        }
        $out .= '</tr>';
    }
    $span = count(array_filter($cols, fn($c) => $c !== 'hidden')) + ($nomor ? 1 : 0);
    return $out !== '' ? $out : '<tr class="row-empty"><td colspan="' . $span . '" class="c">-</td></tr>';
};
$img = fn(string $path): string => e(BASE_URL . '/' . ltrim($path, '/'));
// Tombol aksi di tempat isinya (draft): capture peta & unggah foto, tanpa gulir ke toolbar atas.
// Peta Lokasi (LKP) 1x per operasi: tanpa capture_ref, dipakai semua hari RENOPS.
$capturePetaUrl = BASE_URL . '/gis.php?id_operasi=' . (int)($meta['id_operasi'] ?? 0) . '&include_base=1&capture_doc=RENOPS';

$jenisOps = trim((string)($S['jenis_operasi'] ?? ''));
$petaJudul = 'Peta Lokasi ' . ($jenisOps !== '' && $jenisOps !== '-' ? $jenisOps : 'Kejadian');
$petaPath = (string)($doc['peta']['path'] ?? '');
// Peta Rencana Operasi (capture per hari, jenis RENOPS_RENCANA) tepat di bawah Peta Lokasi.
$petaRencanaPath = (string)($doc['peta_rencana']['path'] ?? '');
$capturePetaRencanaUrl = BASE_URL . '/gis.php?id_operasi=' . (int)($meta['id_operasi'] ?? 0)
    . '&include_base=1&capture_doc=RENOPS_RENCANA&capture_ref=' . (int)($meta['id_renops'] ?? 0);
$hurufPetaRencana = $petaPath !== '' ? 'b' : 'a';
$fotoList = $doc['foto'] ?? [];
$hariOrdinal = ($hariNum > 0 && $hariLabel === (string)$hariNum)
    ? ($hariNum === 1 ? 'Pertama' : ucwords('ke' . TemplateHelper::terbilang($hariNum)))
    : 'ke-' . $hariLabel;
$tglHari = TemplateHelper::formatTanggalSimple((string)($meta['tanggal'] ?? ''));
$asetGrup = [];
foreach ($S['aset'] ?? [] as $a) {
    $asetGrup[(string)($a['grup'] ?? '')][] = $a;
}
$asetGrup = $asetGrup ?: ['' => []];
$peng = $doc['pengesahan'] ?? [];
?>
<style>
<?php foreach (['', ' lanskap'] as $namaHal): ?>
    @page<?= $namaHal ?> {
        size: A4 <?= $namaHal === '' ? 'portrait' : 'landscape' ?>;
        margin: 20mm 20mm 18mm 20mm;
        @top-right {
            content: <?= $cssStr($headerBerjalan) ?>;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000000;
            vertical-align: bottom;
            border-bottom: 0.5pt solid #000000;
        }
        @bottom-center {
            content: "Halaman " counter(page) " dari " counter(pages);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 8pt;
            color: #000000;
        }
    }
<?php endforeach; ?>
    /* !important + selektor .page-sheet.renops-smeac: menimpa gaya Times yang disuntik exporter PDF. */
    .page-sheet.renops-smeac {
        position: relative;
        background: #ffffff;
        color: #000000;
        width: 210mm;
        min-height: 297mm;
        margin: 20px auto;
        padding: 20mm 20mm 18mm 20mm;
        box-sizing: border-box;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.3);
        font-family: Arial, Helvetica, sans-serif !important;
        font-size: 10.5pt !important;
        line-height: 1.15 !important;
        text-align: left !important;
    }
    /* Lembar terakhir (struktur organisasi + pengesahan): landscape, halaman cetak sendiri. */
    .page-sheet.renops-smeac.lanskap { width: 297mm; min-height: 210mm; page: lanskap; break-before: page; page-break-before: always; }
    .page-sheet.renops-smeac *, .page-sheet.renops-smeac *::before, .page-sheet.renops-smeac *::after {
        font-family: Arial, Helvetica, sans-serif !important;
        box-sizing: border-box;
    }
    .page-sheet.renops-smeac [hidden] { display: none !important; }
    .page-sheet.renops-smeac p, .page-sheet.renops-smeac li {
        font-size: 10.5pt !important;
        line-height: 1.15 !important;
        text-align: justify !important;
        margin: 0 0 5pt 0;
    }
    /* Lebar tabel di pembungkus: exporter PDF memaksa table { width: 100% !important } dengan selektor sangat spesifik. */
    .renops-smeac .kop-wrap { width: 170mm; max-width: 100%; margin: 0 auto; }
    .renops-smeac .kop { width: 100%; border-collapse: collapse; table-layout: fixed !important; }
    .renops-smeac .kop td { border: none !important; vertical-align: middle; padding: 0; }
    .renops-smeac .kop .kop-logo { width: 17.6mm; text-align: center; }
    .renops-smeac .kop .kop-logo img { width: 14mm; height: auto; display: inline-block; }
    .page-sheet.renops-smeac .kop .kop-judul p { text-align: center !important; font-weight: bold; color: #000000; margin: 0; }
    .page-sheet.renops-smeac .kop .k1 { font-size: 15pt !important; margin-bottom: 2pt; }
    .page-sheet.renops-smeac .kop .k2 { font-size: 11.5pt !important; margin-bottom: 4pt; }
    .page-sheet.renops-smeac .kop .k3 { font-size: 9.5pt !important; line-height: 1.3 !important; }
    .renops-smeac .kop-garis { border-top: 2.25pt solid #000000; border-bottom: 0.75pt solid #000000; height: 2.5pt; margin: 4pt 0 6pt; }
    .page-sheet.renops-smeac .h1, .page-sheet.renops-smeac .h2, .page-sheet.renops-smeac .h3 {
        font-weight: bold; margin: 0; break-after: avoid; page-break-after: avoid;
    }
    .page-sheet.renops-smeac .h1 { font-size: 11.5pt !important; color: #000000; border-bottom: 0.75pt solid #000000; padding: 15pt 0 2pt 8mm; margin-bottom: 6pt; }
    .page-sheet.renops-smeac .h2 { font-size: 11pt !important; color: #000000; padding: 10pt 0 0 8mm; margin-bottom: 4pt; }
    .page-sheet.renops-smeac .h3 { font-size: 10.5pt !important; color: #000000; padding: 7pt 0 0 8mm; margin-bottom: 3pt; }
    .renops-smeac .no { display: inline-block; width: 8mm; margin-left: -8mm; }
    .renops-smeac .ind { padding-left: 8mm; }
    .renops-smeac .sec { position: relative; }
    .renops-smeac .sec-kronologi .sec-body > p:first-child::before { content: "Kronologi kejadian: "; font-weight: bold; }
    .renops-smeac .lbl { font-weight: bold; }
    .renops-smeac .hari { font-weight: bold; }
    .renops-smeac .lst { list-style: none; margin: 0 0 5pt; padding: 0; counter-reset: butir; }
    .renops-smeac .lst > li { position: relative; padding-left: 8mm; counter-increment: butir; }
    .renops-smeac .lst > li::before { position: absolute; left: 0; }
    .renops-smeac .lst-num > li::before { content: counter(butir) ")"; }
    .renops-smeac .lst-alpha > li::before { content: counter(butir, lower-alpha) "."; }
    .renops-smeac .lst-dash > li { padding-left: 5.3mm; }
    .renops-smeac .lst-dash > li::before { content: "\2013"; }
    .renops-smeac .sub { padding-left: 8mm; }
    .renops-smeac .dash-paras .sec-body > p { position: relative; padding-left: 5.3mm; }
    .renops-smeac .dash-paras .sec-body > p::before { content: "\2013"; position: absolute; left: 0; }
    .page-sheet.renops-smeac .cap-tabel { font-size: 9.5pt !important; font-weight: bold; color: #000000; margin: 6pt 0 4pt; text-align: left !important; break-after: avoid; page-break-after: avoid; }
    .page-sheet.renops-smeac .cap-gambar { font-size: 9pt; font-style: italic; color: #000000; text-align: center; margin: 3pt 0 10pt; }
    .page-sheet.renops-smeac table.table-dinas { border-collapse: collapse !important; table-layout: fixed !important; margin: 0 0 8pt; font-size: 9.5pt !important; }
    .page-sheet.renops-smeac .table-dinas thead { display: table-header-group; }
    .page-sheet.renops-smeac .table-dinas tr { break-inside: avoid; page-break-inside: avoid; }
    .page-sheet.renops-smeac .table-dinas th, .page-sheet.renops-smeac .table-dinas td {
        border: 0.75pt solid #000000 !important; color: #000000 !important; padding: 3pt 5pt !important; font-size: 9.5pt !important;
        line-height: 1.25 !important; vertical-align: top !important; text-align: left; text-transform: none !important;
        word-wrap: break-word; overflow-wrap: break-word;
    }
    .page-sheet.renops-smeac .table-dinas th { background: #ffffff !important; color: #000000 !important; font-weight: bold !important; text-align: center !important; vertical-align: middle !important; }
    .page-sheet.renops-smeac .table-dinas td.c { text-align: center; }
    .page-sheet.renops-smeac .table-dinas td.lb { background: #ffffff !important; font-weight: bold; }
    .renops-smeac .tw { max-width: 100%; }
    .renops-smeac .tw > table { width: 100%; }
    .renops-smeac .t-kejadian { width: 144mm; }
    .renops-smeac .t-sru { width: 146mm; }
    .renops-smeac .t-aset { width: 136mm; }
    .renops-smeac figure { margin: 4pt 0 0; text-align: center; break-inside: avoid; page-break-inside: avoid; }
    .renops-smeac .aset-grup { break-inside: avoid; page-break-inside: avoid; }
    .renops-smeac figure img { display: block; margin: 0 auto; }
    .renops-smeac .peta img { width: 138mm; max-width: 100%; height: auto; }
    .renops-smeac .peta-kosong { border: 1px dashed #94a3b8; color: #64748b; padding: 10mm; text-align: center; font-size: 9pt; margin: 4pt 0 8pt; }
    .renops-smeac .doc-aksi { text-align: center; margin: 3pt 0 8pt; }
    .renops-smeac .doc-aksi a, .renops-smeac .doc-aksi button { display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px; border-radius: 8px;
        font-size: 9pt !important; font-weight: bold; color: #ffffff !important; text-decoration: none; cursor: pointer; border: none; }
    .renops-smeac .doc-aksi .aksi-peta { background: #0284c7; }
    .renops-smeac .doc-aksi .aksi-foto { background: #059669; }
    .renops-smeac .doc-aksi .aksi-foto:disabled { opacity: 0.6; cursor: default; }
    .renops-smeac .doc-aksi small { display: block; font-size: 7.5pt !important; color: #64748b; margin-top: 2pt; }
    /* Jaring komunikasi: halaman sendiri; diagram dibatasi tinggi agar utuh dalam satu halaman A4 portrait. */
    .page-sheet.renops-smeac.hal-jaring { break-before: page; page-break-before: always; }
    .renops-smeac figure.jaring .jaring-svg { width: auto !important; height: 205mm !important; max-width: 100% !important; }
    .renops-smeac .foto-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 4mm 6mm; margin-top: 4pt; }
    .renops-smeac .foto-grid img { width: 100%; height: 56mm; object-fit: contain; }
    .renops-smeac figure.foto { position: relative; }
    .renops-smeac .foto-ket[contenteditable] { outline: 1px dashed #94a3b8; outline-offset: 1px; cursor: text; }
    .renops-smeac .foto-ctl { position: absolute; top: 2mm; right: 2mm; display: flex; gap: 3px; }
    .renops-smeac .foto-ctl button { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid #cbd5e1; background: rgba(255,255,255,0.92); color: #334155; font-size: 11px; line-height: 1; cursor: pointer; }
    .renops-smeac .foto-ctl button:disabled { opacity: 0.35; cursor: default; }
    .renops-smeac .foto-ctl button[data-foto-hapus] { color: #dc2626; border-color: #fecaca; }
    .renops-smeac .pengesahan { display: flex; justify-content: flex-end; margin-top: 2mm; break-inside: avoid; page-break-inside: avoid; }
    .renops-smeac .ttd { width: 92mm; text-align: center; }
    .page-sheet.renops-smeac .ttd p { text-align: center !important; font-size: 10pt !important; margin: 0; }
    .page-sheet.renops-smeac .ttd .ttd-nama { font-weight: bold; text-decoration: underline; }
    /* QR bermodel BRA SAR: bingkai putih bergaris hitam membulat, keterangan monospace "SCAN UNTUK VERIFIKASI". */
    .renops-smeac .qr-frame { width: 26mm; height: 26mm; margin: 2pt auto 0; padding: 0.6mm; background: #ffffff; border: 1px solid #000000;
        border-radius: 1.2mm; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05); display: flex; align-items: center; justify-content: center; }
    .renops-smeac .qr-frame canvas, .renops-smeac .qr-frame img { width: 24mm !important; height: 24mm !important; display: block; object-fit: contain; }
    /* Kelas (bukan atribut hidden): exporter menyalin className saat mengganti canvas dengan <img>. */
    .page-sheet.renops-smeac .qr-frame .qr-off { display: none !important; }
    .renops-smeac .qr-kosong { border: 1.5px dashed #94a3b8; color: #64748b; font-size: 7.5pt; text-align: center; }
    .page-sheet.renops-smeac .qr-ket { font-family: 'Courier New', Courier, monospace !important; font-size: 7pt !important; color: #374151; margin: 1pt 0 3pt; }
    .renops-smeac .sec-badge {
        position: absolute; top: 0; right: -3mm; transform: translateX(100%); z-index: 2;
        font-size: 7pt !important; font-weight: bold; line-height: 1 !important; padding: 2px 5px; border-radius: 9999px;
        background: #e2e8f0; color: #475569; white-space: nowrap;
    }
    .renops-smeac .sec-badge.is-edited { background: #fef3c7; color: #b45309; }
    .renops-smeac .renops-watermark {
        position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); z-index: 5;
        font-size: 150pt; font-weight: bold; letter-spacing: 12mm; color: rgba(220, 38, 38, 0.08); pointer-events: none; user-select: none;
    }
    @media print {
        .page-sheet.renops-smeac { zoom: 1 !important; width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        .renops-smeac .sec-badge, .renops-smeac .foto-ctl { display: none; }
        .page-sheet.renops-smeac.lanskap { width: auto; min-height: 0; }
    }
</style>
<article id="renopsSmeacDoc" class="page-sheet renops-smeac<?= $draft ? ' is-draft' : '' ?>" data-pdf-orientation="portrait" data-pdf-header="<?= e($headerBerjalan) ?>">
    <?php if ($draft || $pratinjau): ?><div class="renops-watermark" aria-hidden="true">DRAF</div><?php endif; ?>

    <div class="kop-wrap"><table class="kop">
        <tr>
            <td class="kop-logo"><img src="<?= e(getAppLogoDataUri()) ?>" alt="Logo Basarnas"></td>
            <td class="kop-judul">
                <p class="k1">RENCANA OPERASI SAR HARI KE-<?= e(mb_strtoupper($hariLabel)) ?></p>
                <p class="k2"><?= e(mb_strtoupper($satker)) ?></p>
                <p class="k3"><?= e($kopBaris3) ?></p>
            </td>
            <td class="kop-logo"><img src="<?= e(getAppLogo2DataUri()) ?>" alt="Logo SAR Nasional"></td>
        </tr>
    </table></div>
    <div class="kop-garis"></div>

    <h2 class="h1"><span class="no">A.</span>SITUATION (SITUASI)</h2>
    <div class="ind">
        <h3 class="h2"><span class="no">1.</span>Awareness Stage (Tahap Menyadari)</h3>
        <div class="ind">
            <?= $text('awareness') ?>
            <?= $text('kronologi', 'sec-kronologi') ?>
            <p class="sec"<?= $sec('jenis_operasi', 'text') ?>><?= $badge('jenis_operasi') ?><span class="lbl">Jenis Operasi: </span><span class="sec-body"><?= e($jenisOps) ?></span></p>
            <div class="sec"<?= $sec('data_kejadian', 'table') ?>>
                <?= $badge('data_kejadian') ?>
                <?= $tabel('Data Kejadian') ?>
                <div class="tw t-kejadian"><table class="table-dinas">
                    <colgroup><col style="width:30.6%"><col></colgroup>
                    <tbody><?= $rows($S['data_kejadian'] ?? [], ['label' => 'lb', 'value' => '']) ?></tbody>
                </table></div>
            </div>
            <?php if ($petaPath !== ''): ?>
                <h4 class="h3"><span class="no">a.</span><?= e($petaJudul) ?></h4>
                <figure class="peta">
                    <img src="<?= $img($petaPath) ?>" alt="<?= e($petaJudul) ?>">
                    <?= $gambar($petaJudul) ?>
                </figure>
                <?php if ($draft): ?>
                    <div class="doc-aksi no-print"><a class="aksi-peta" id="btnCapturePeta" href="<?= e($capturePetaUrl) ?>">Perbarui Capture Peta LKP (1x per operasi)</a></div>
                <?php endif; ?>
            <?php elseif ($draft): ?>
                <div class="peta-kosong no-print">
                    Peta Lokasi (LKP) operasi ini belum di-capture (cukup 1x, dipakai semua hari RENOPS).
                    <div class="doc-aksi"><a class="aksi-peta" id="btnCapturePeta" href="<?= e($capturePetaUrl) ?>">Capture Peta LKP 16:9</a></div>
                </div>
            <?php endif; ?>
            <?php if ($petaRencanaPath !== ''): ?>
                <h4 class="h3"><span class="no"><?= $hurufPetaRencana ?>.</span>Peta Rencana Operasi</h4>
                <figure class="peta">
                    <img src="<?= $img($petaRencanaPath) ?>" alt="Peta Rencana Operasi">
                    <?= $gambar('Peta Rencana Operasi SAR Hari ke-' . $hariLabel) ?>
                </figure>
                <?php if ($draft): ?>
                    <div class="doc-aksi no-print"><a class="aksi-peta" id="btnCapturePetaRencana" href="<?= e($capturePetaRencanaUrl) ?>">Perbarui Capture Peta Rencana Operasi</a></div>
                <?php endif; ?>
            <?php elseif ($draft): ?>
                <div class="peta-kosong no-print">
                    Peta Rencana Operasi (area pencarian, SRU &amp; pola) belum di-capture.
                    <div class="doc-aksi"><a class="aksi-peta" id="btnCapturePetaRencana" href="<?= e($capturePetaRencanaUrl) ?>">Capture Peta Rencana Operasi 16:9</a></div>
                </div>
            <?php endif; ?>
        </div>

        <h3 class="h2"><span class="no">2.</span>Initial Action Stage (Tahap Tindakan Awal)</h3>
        <div class="ind">
            <h4 class="h3"><span class="no">a.</span>Upaya Sebelumnya</h4>
            <div class="ind"><?= $text('upaya_sebelumnya') ?></div>
            <h4 class="h3"><span class="no">b.</span>Koordinasi</h4>
            <div class="ind">
                <?= $list('koordinasi', 'lst-num') ?>
                <div class="sub"><?= $list('koordinasi_instansi', 'lst-dash') ?></div>
            </div>
            <h4 class="h3"><span class="no">c.</span>Persiapan</h4>
            <div class="ind">
                <ol class="lst lst-num">
                    <li><span class="lbl">Personel Rescuer</span><?= $list('persiapan_personel', 'lst-dash') ?></li>
                    <li><span class="lbl">Sarana</span><?= $list('sarana', 'lst-dash') ?></li>
                    <li><span class="lbl">Peralatan</span><?= $list('peralatan', 'lst-dash') ?></li>
                </ol>
            </div>
        </div>
    </div>

    <h2 class="h1"><span class="no">B.</span>MISSION (TUGAS)</h2>
    <div class="ind"><?= $text('mission') ?></div>

    <h2 class="h1"><span class="no">C.</span>IMPLEMENTATION (PELAKSANAAN)</h2>
    <div class="ind">
        <h3 class="h2"><span class="no">1.</span>Planning Stage (Tahap Perencanaan)</h3>
        <div class="ind">
            <h4 class="h3"><span class="no">a.</span>Waktu</h4>
            <div class="ind"><?= $text('waktu') ?></div>
            <h4 class="h3"><span class="no">b.</span>Area Pencarian</h4>
            <div class="ind"><?= $text('area_pencarian') ?></div>
            <h4 class="h3"><span class="no">c.</span>Alokasi Unsur Pencarian dan Pertolongan</h4>
            <div class="ind">
                <div class="sec"<?= $sec('aset', 'table') ?>>
                    <?= $badge('aset') ?>
                    <ol class="lst lst-num">
                        <?php foreach ($asetGrup as $grup => $asetRows): $label = trim('Aset ' . $grup); ?>
                            <li class="aset-grup">
                                <span class="lbl"><?= e($label) ?></span>
                                <?= $tabel('Alokasi ' . $label) ?>
                                <div class="tw t-aset"><table class="table-dinas">
                                    <colgroup><col style="width:57%"><col></colgroup>
                                    <thead><tr><th>Aset</th><th>Jumlah</th></tr></thead>
                                    <tbody><?= $rows($asetRows, ['grup' => 'hidden', 'aset' => '', 'jumlah' => 'c']) ?></tbody>
                                </table></div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            </div>
            <h4 class="h3"><span class="no">d.</span>Penugasan SRU</h4>
            <div class="ind">
                <div class="sec"<?= $sec('sru', 'table') ?>>
                    <?= $badge('sru') ?>
                    <?= $tabel('Penugasan Search and Rescue Unit (SRU)') ?>
                    <div class="tw t-sru"><table class="table-dinas">
                        <colgroup><col style="width:6.9%"><col style="width:18.5%"><col style="width:18%"><col style="width:29.7%"><col style="width:14.5%"><col></colgroup>
                        <thead><tr><th>No</th><th>Unsur</th><th>Tugas</th><th>Area</th><th>Pola Pencarian</th><th>Track Spacing</th></tr></thead>
                        <tbody><?= $rows($S['sru'] ?? [], ['unsur' => '', 'tugas' => '', 'area' => '', 'pola' => 'c', 'track_spacing' => 'c'], true) ?></tbody>
                    </table></div>
                </div>
            </div>
            <h4 class="h3"><span class="no">e.</span>Rencana Penyelamatan (Rescue Plan)</h4>
            <div class="ind"><?= $text('rescue_plan') ?></div>
            <h4 class="h3"><span class="no">f.</span>Briefing dan Debriefing</h4>
            <div class="ind"><?= $text('briefing') ?></div>
        </div>
    </div>

    <h2 class="h1"><span class="no">D.</span>OPERATION STAGE (TAHAP OPERASI)</h2>
    <div class="ind">
        <p class="hari">Hari <?= e($hariOrdinal) ?> (<?= e($tglHari) ?>)</p>
        <?= $text('operation_stage', 'dash-paras') ?>
        <?php if ($fotoList): ?>
            <div class="foto-grid">
                <?php foreach (array_values($fotoList) as $iFoto => $f): $ket = trim((string)($f['keterangan'] ?? '')) ?: 'Dokumentasi Operasi SAR'; ?>
                    <figure class="foto"<?= $draft ? ' data-id-foto="' . (int)($f['id_foto'] ?? 0) . '"' : '' ?>>
                        <img src="<?= $img((string)($f['path'] ?? '')) ?>" alt="<?= e($ket) ?>">
                        <?php if ($draft): ?>
                            <div class="foto-ctl no-print">
                                <button type="button" data-foto-geser="-1" title="Naikkan urutan" aria-label="Naikkan urutan foto"<?= $iFoto === 0 ? ' disabled' : '' ?>>&#9650;</button>
                                <button type="button" data-foto-geser="1" title="Turunkan urutan" aria-label="Turunkan urutan foto"<?= $iFoto === count($fotoList) - 1 ? ' disabled' : '' ?>>&#9660;</button>
                                <button type="button" data-foto-hapus title="Hapus foto" aria-label="Hapus foto"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
                            </div>
                            <figcaption class="cap-gambar">Gambar <?= ++$nGambar ?>. <span class="foto-ket" data-foto-ket="<?= e(trim((string)($f['keterangan'] ?? ''))) ?>"><?= e($ket) ?></span></figcaption>
                        <?php else: ?>
                            <?= $gambar($ket) ?>
                        <?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($draft): ?>
            <div class="doc-aksi no-print">
                <button type="button" class="aksi-foto" id="btnUploadFoto">+ Upload Foto Dokumentasi</button>
                <small>JPG/PNG/WebP, maksimal 5 MB per foto, maksimal 12 foto. Keterangan diedit langsung di bawah foto.</small>
            </div>
        <?php endif; ?>
    </div>

    <h2 class="h1"><span class="no">E.</span>ADMINISTRATION AND LOGISTICS (ADMINISTRASI DAN LOGISTIK)</h2>
    <div class="ind">
        <h3 class="h2"><span class="no">1.</span>Administrasi</h3>
        <div class="ind"><?= $list('administrasi', 'lst-alpha') ?></div>
        <h3 class="h2"><span class="no">2.</span>Logistik</h3>
        <div class="ind"><?= $list('logistik', 'lst-alpha') ?></div>
    </div>
</article>
<article class="page-sheet renops-smeac hal-jaring<?= $draft ? ' is-draft' : '' ?>" data-pdf-orientation="portrait">
    <h2 class="h1"><span class="no">F.</span>KOORDINASI, PENGENDALIAN, DAN KOMUNIKASI</h2>
    <div class="ind">
        <h3 class="h2"><span class="no">1.</span>Jaring Komunikasi</h3>
        <div class="ind">
            <p>Jaring komunikasi dan koordinasi pelaksanaan Operasi SAR ditunjukkan pada Gambar <?= $nGambar + 1 ?>. Garis tegas menggambarkan jaring pengendalian, sedangkan garis putus-putus menggambarkan jaring koordinasi.</p>
            <figure class="jaring">
                <?php include __DIR__ . '/renops_jaring_komunikasi.php'; ?>
                <?= $gambar('Jaring Komunikasi dan Koordinasi Operasi SAR') ?>
            </figure>
        </div>
    </div>
</article>
<article class="page-sheet renops-smeac lanskap<?= $draft ? ' is-draft' : '' ?>" data-pdf-orientation="landscape">
    <div class="ind">
        <h3 class="h2"><span class="no">2.</span>Struktur Organisasi Operasi SAR</h3>
        <div class="ind">
            <figure class="struktur">
                <?php include __DIR__ . '/renops_struktur_org.php'; ?>
                <?= $gambar('Struktur Organisasi Operasi SAR') ?>
            </figure>
        </div>
    </div>

    <div class="pengesahan signature-block">
        <div class="ttd">
            <p><?= e((string)($peng['kota'] ?? '')) ?>, <?= e(TemplateHelper::formatTanggalSimple((string)($peng['tanggal'] ?? ''))) ?></p>
            <p>Koordinator Misi Pencarian dan Pertolongan (SMC)</p>
            <?php if ($draft || $pratinjau): ?>
                <div class="qr-frame qr-kosong">DRAF &bull; QR terbit setelah diterbitkan</div>
                <p class="qr-ket">BELUM TERVERIFIKASI</p>
            <?php else: ?>
                <div class="qr-frame">
                    <canvas id="qrCanvas" data-qr-url="<?= e($qrUrl ?? '') ?>"></canvas>
                    <img id="qrImg" class="qr-off" alt="QR verifikasi dokumen">
                </div>
                <p class="qr-ket">SCAN UNTUK VERIFIKASI &bull; <?= e($versiLabel) ?></p>
            <?php endif; ?>
            <p class="ttd-nama"><?= e((string)($peng['smc_nama'] ?? '')) ?></p>
            <p><?= e((string)($peng['smc_pangkat'] ?? '')) ?></p>
        </div>
    </div>
</article>
<?php
})($doc, $mode ?? 'final', $qrUrl ?? null, $versiLabel ?? '');
