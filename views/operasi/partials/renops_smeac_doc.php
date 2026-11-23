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
$noRenops = trim((string)($meta['nomor'] ?? $meta['no_renops'] ?? ''));
$kopBaris3 = trim((string)($S['judul_operasi'] ?? $namaOps));
if ($kopBaris3 === '') {
    $kopBaris3 = $namaOps;
}
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
    return implode('', array_map(function ($p) {
        $trimmed = trim($p);
        if (preg_match('/^Hari\s+(Pertama|Kedua|Ketiga|Keempat|Kelima|Keenam|Ketujuh|ke-[\d\s\+]+)\s*(\([^\)]+\))?$/ui', $trimmed)) {
            return '<p class="hari">' . e($trimmed) . '</p>';
        }
        return '<p>' . nl2br(e($trimmed)) . '</p>';
    }, $parts));
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
// Sertakan from_renops sebelum capture_doc sebagai konteks navigasi kembali ke draf RENOPS asal.
$fromRenopsQ = (int)($meta['id_renops'] ?? 0) > 0 ? '&from_renops=' . (int)$meta['id_renops'] : '';
$capturePetaUrl = BASE_URL . '/gis.php?id_operasi=' . (int)($meta['id_operasi'] ?? 0) . '&include_base=1' . $fromRenopsQ . '&capture_doc=RENOPS';

$jenisOps = trim((string)($S['jenis_operasi'] ?? ''));
$petaJudul = 'Peta Lokasi ' . ($jenisOps !== '' && $jenisOps !== '-' ? $jenisOps : 'Kejadian');
$petaPath = (string)($doc['peta']['path'] ?? '');
// Peta Rencana Operasi (capture per hari, jenis RENOPS_RENCANA) tepat di bawah Peta Lokasi.
$petaRencanaPath = (string)($doc['peta_rencana']['path'] ?? '');
$capturePetaRencanaUrl = BASE_URL . '/gis.php?id_operasi=' . (int)($meta['id_operasi'] ?? 0)
    . '&include_base=1' . $fromRenopsQ . '&capture_doc=RENOPS_RENCANA&capture_ref=' . (int)($meta['id_renops'] ?? 0);
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
.renops-smeac .ind2 { padding-left: 16mm; }
.renops-smeac .ind3 { padding-left: 24mm; }
.renops-smeac .sheet-hdr { position: absolute; top: 8mm; left: 20mm; right: 20mm; font-family: "Times New Roman", Times, serif; font-size: 8pt; line-height: 1.2; color: #000; text-align: right; border-bottom: 0.5pt solid #000; padding-bottom: 1.5mm; pointer-events: none; user-select: none; }
.renops-smeac .sheet-ftr { position: absolute; bottom: 8mm; left: 20mm; right: 20mm; font-family: "Times New Roman", Times, serif; font-size: 8pt; line-height: 1.2; color: #000; text-align: center; pointer-events: none; user-select: none; }
@media print { .renops-smeac .sheet-hdr, .renops-smeac .sheet-ftr { display: none !important; } }
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
    .renops-smeac .dash-paras .sec-body > p.hari {
        position: static;
        padding-left: 0;
        font-weight: bold;
        margin-top: 8pt;
        margin-bottom: 2pt;
    }
    .renops-smeac .dash-paras .sec-body > p.hari:first-child {
        margin-top: 0;
    }
    .renops-smeac .dash-paras .sec-body > p.hari::before {
        content: none !important;
    }
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
    .page-sheet.renops-smeac.renops-cont { break-before: page; page-break-before: always; }
.page-sheet.renops-smeac.hal-jaring { break-before: page; page-break-before: always; }
    .renops-smeac figure.jaring .jaring-svg { width: auto !important; height: 205mm !important; max-width: 100% !important; }
    /* Halaman lampiran foto dokumentasi (portrait) setelah tanda tangan SMC */
    .hal-lampiran-foto {
        break-before: page !important;
        page-break-before: always !important;
    }
    .page-sheet.renops-smeac.hal-lampiran-foto {
        break-before: page !important;
        page-break-before: always !important;
    }
    .page-sheet.renops-smeac.hal-lampiran-foto .doc-header {
        border-bottom: 0.75pt solid #000000;
        padding-bottom: 3pt;
        margin-bottom: 4pt;
    }
    .page-sheet.renops-smeac.hal-lampiran-foto .doc-header .h1 {
        border-bottom: none !important;
        padding: 0 !important;
        margin-bottom: 2pt !important;
        text-align: center !important;
    }
    .page-sheet.renops-smeac.hal-lampiran-foto .doc-header p {
        text-align: center !important;
        margin: 0;
    }
    .renops-smeac .foto-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 3.5mm 5mm; margin-top: 3pt; }
    .renops-smeac .foto-grid img { width: 100%; height: 48mm; object-fit: contain; display: block; }
    .page-sheet.renops-smeac.hal-lampiran-foto .cap-gambar { margin: 2pt 0 0 !important; font-size: 9.5pt !important; line-height: 1.25 !important; }
    .renops-smeac figure.foto {
        position: relative;
        break-inside: avoid !important;
        page-break-inside: avoid !important;
        border-radius: 6px;
        transition: box-shadow 0.15s ease, outline 0.15s ease;
    }
    .page-sheet.renops-smeac.is-draft figure.foto { cursor: grab; }
    .page-sheet.renops-smeac.is-draft figure.foto:active { cursor: grabbing; }
    .page-sheet.renops-smeac.is-draft figure.foto.is-selected { outline: 2px solid #ea580c; outline-offset: 2px; background: rgba(254, 215, 170, 0.18); }
    .renops-smeac .foto-sortable-ghost { opacity: 0.4; outline: 2px dashed #ea580c !important; }
    .renops-smeac .foto-ket[contenteditable] { outline: 1px dashed #94a3b8; outline-offset: 1px; cursor: text; }
    .renops-smeac .foto-select-wrap { position: absolute; top: 2mm; left: 2mm; z-index: 3; display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 7px; border-radius: 6px; border: 1px solid #cbd5e1; background: rgba(255,255,255,0.94); color: #334155; font-size: 8pt !important; font-weight: bold; cursor: pointer; user-select: none; }
    .renops-smeac .foto-select-wrap input { width: 13px; height: 13px; accent-color: #ea580c; cursor: pointer; margin: 0; }
    .renops-smeac .foto-ctl { position: absolute; top: 2mm; right: 2mm; z-index: 3; display: flex; gap: 3px; align-items: center; }
    .renops-smeac .foto-drag-handle { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid #cbd5e1; background: rgba(255,255,255,0.92); color: #475569; font-size: 12px; font-weight: bold; line-height: 1; cursor: grab; user-select: none; }
    .renops-smeac .foto-ctl button { width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center;
        border-radius: 6px; border: 1px solid #cbd5e1; background: rgba(255,255,255,0.92); color: #334155; font-size: 11px; line-height: 1; cursor: pointer; }
    .renops-smeac .foto-ctl button:disabled { opacity: 0.35; cursor: default; }
    .renops-smeac .foto-ctl button[data-foto-hapus] { color: #dc2626; border-color: #fecaca; }
    .renops-smeac .foto-bulk-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 10px; margin-bottom: 8px; padding: 6px 12px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .renops-smeac .ttd-box { break-inside: avoid; page-break-inside: avoid; }
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
        .page-sheet.renops-smeac.hal-lampiran-foto { width: auto; min-height: 0; }
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
                <p class="k3 sec"<?= $sec('judul_operasi', 'text') ?>><span class="sec-body"><?= e($kopBaris3) ?></span></p>
            </td>
            <td class="kop-logo"><img src="<?= e(getAppLogo2DataUri()) ?>" alt="Logo SAR Nasional"></td>
        </tr>
    </table></div>
    <div class="kop-garis"></div>

    <h2 class="h1"><span class="no">A.</span>SITUATION (SITUASI)</h2>
    <div class="ind">
        <h3 class="h2"><span class="no">1.</span>Awareness Stage (Tahap Menyadari)</h3>
    </div>
    <div class="ind2">
        <?= $text('awareness') ?>
    </div>
    <div class="ind2">
        <?= $text('kronologi', 'sec-kronologi') ?>
    </div>
    <div class="ind2">
        <p class="sec"<?= $sec('jenis_operasi', 'text') ?>><?= $badge('jenis_operasi') ?><span class="lbl">Jenis Operasi: </span><span class="sec-body"><?= e($jenisOps) ?></span></p>
    </div>
    <div class="ind2">
        <div class="sec"<?= $sec('data_kejadian', 'table') ?>>
                <?= $badge('data_kejadian') ?>
                <?= $tabel('Data Kejadian') ?>
                <div class="tw t-kejadian"><table class="table-dinas">
                    <colgroup><col style="width:30.6%"><col></colgroup>
                    <tbody><?= $rows($S['data_kejadian'] ?? [], ['label' => 'lb', 'value' => '']) ?></tbody>
                </table></div>
            </div>
    </div>
    <?php if ($petaPath !== ''): ?>
        <div class="ind2">
            <h4 class="h3"><span class="no">a.</span><?= e($petaJudul) ?></h4>
            <figure class="peta">
                <img src="<?= $img($petaPath) ?>" alt="<?= e($petaJudul) ?>">
                <?= $gambar($petaJudul) ?>
            </figure>
            <?php if ($draft): ?>
                <div class="doc-aksi no-print"><a class="aksi-peta" id="btnCapturePeta" href="<?= e($capturePetaUrl) ?>">Perbarui Capture Peta LKP (1x per operasi)</a></div>
            <?php endif; ?>
        </div>
    <?php elseif ($draft): ?>
        <div class="ind2">
            <div class="peta-kosong no-print">
                Peta Lokasi (LKP) operasi ini belum di-capture (cukup 1x, dipakai semua hari RENOPS).
                <div class="doc-aksi"><a class="aksi-peta" id="btnCapturePeta" href="<?= e($capturePetaUrl) ?>">Capture Peta LKP 16:9</a></div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ($petaRencanaPath !== ''): ?>
        <div class="ind2">
            <h4 class="h3"><span class="no"><?= $hurufPetaRencana ?>.</span>Peta Rencana Operasi</h4>
            <figure class="peta">
                <img src="<?= $img($petaRencanaPath) ?>" alt="Peta Rencana Operasi">
                <?= $gambar('Peta Rencana Operasi SAR Hari ke-' . $hariLabel) ?>
            </figure>
            <?php if ($draft): ?>
                <div class="doc-aksi no-print"><a class="aksi-peta" id="btnCapturePetaRencana" href="<?= e($capturePetaRencanaUrl) ?>">Perbarui Capture Peta Rencana Operasi</a></div>
            <?php endif; ?>
        </div>
    <?php elseif ($draft): ?>
        <div class="ind2">
            <div class="peta-kosong no-print">
                Peta Rencana Operasi (area pencarian, SRU &amp; pola) belum di-capture.
                <div class="doc-aksi"><a class="aksi-peta" id="btnCapturePetaRencana" href="<?= e($capturePetaRencanaUrl) ?>">Capture Peta Rencana Operasi 16:9</a></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="ind">
        <h3 class="h2"><span class="no">2.</span>Initial Action Stage (Tahap Tindakan Awal)</h3>
    </div>
    <div class="ind2">
        <h4 class="h3"><span class="no">a.</span>Upaya Sebelumnya</h4>
        <div class="ind"><?= $text('upaya_sebelumnya') ?></div>
    </div>
    <div class="ind2">
        <h4 class="h3"><span class="no">b.</span>Koordinasi</h4>
        <div class="ind">
            <?= $list('koordinasi', 'lst-num') ?>
            <div class="sub"><?= $list('koordinasi_instansi', 'lst-dash') ?></div>
        </div>
    </div>
    <div class="ind2">
        <h4 class="h3"><span class="no">c.</span>Persiapan</h4>
        <div class="ind">
            <ol class="lst lst-num">
                <li><span class="lbl">Personel Rescuer</span><?= $list('persiapan_personel', 'lst-dash') ?></li>
                <li><span class="lbl">Sarana</span><?= $list('sarana', 'lst-dash') ?></li>
                <li><span class="lbl">Peralatan</span><?= $list('peralatan', 'lst-dash') ?></li>
            </ol>
        </div>
    </div>

    <h2 class="h1"><span class="no">B.</span>MISSION (TUGAS)</h2>
    <div class="ind"><?= $text('mission') ?></div>

    <h2 class="h1"><span class="no">C.</span>IMPLEMENTATION (PELAKSANAAN)</h2>
    <div class="ind">
        <h3 class="h2"><span class="no">1.</span>Planning Stage (Tahap Perencanaan)</h3>
    </div>
    <div class="ind2">
        <h4 class="h3"><span class="no">a.</span>Waktu</h4>
        <div class="ind"><?= $text('waktu') ?></div>
    </div>
    <div class="ind2">
        <h4 class="h3"><span class="no">b.</span>Area Pencarian</h4>
        <div class="ind"><?= $text('area_pencarian') ?></div>
    </div>
    <div class="ind2">
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
    </div>
    <div class="ind2">
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
    </div>
    <div class="ind2">
        <h4 class="h3"><span class="no">e.</span>Rencana Penyelamatan (Rescue Plan)</h4>
        <div class="ind"><?= $text('rescue_plan') ?></div>
    </div>
    <div class="ind2">
        <h4 class="h3"><span class="no">f.</span>Briefing dan Debriefing</h4>
        <div class="ind"><?= $text('briefing') ?></div>
    </div>

    <h2 class="h1"><span class="no">D.</span>OPERATION STAGE (TAHAP OPERASI)</h2>
    <div class="ind">
        <?php if (!preg_match('/^Hari\s+(Pertama|Kedua|Ketiga|Keempat|Kelima|Keenam|Ketujuh|ke-[\d\s\+]+)\b/ui', trim((string)($S['operation_stage'] ?? '')))): ?>
            <p class="hari">Hari <?= e($hariOrdinal) ?> (<?= e($tglHari) ?>)</p>
        <?php endif; ?>
        <?= $text('operation_stage', 'dash-paras') ?>
    </div>

    <h2 class="h1"><span class="no">E.</span>ADMINISTRATION AND LOGISTICS (ADMINISTRASI DAN LOGISTIK)</h2>
    <div class="ind">
        <h3 class="h2"><span class="no">1.</span>Administrasi</h3>
        <div class="ind"><?= $list('administrasi', 'lst-alpha') ?></div>
    </div>
    <div class="ind">
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

    <section class="ttd-box">
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
    </section>
</article>
<?php if ($draft || !empty($fotoList)): ?>
<!-- HALAMAN LAMPIRAN DOKUMENTASI FOTO (SETELAH TTD SMC, MAKS 6 FOTO PER HALAMAN) -->
<?php
$fotoValues = array_values($fotoList);
$totalFotos = count($fotoValues);
$fotoPages = $totalFotos > 0 ? array_chunk($fotoValues, 6, true) : [[]];
$totalFotoPages = count($fotoPages);
foreach ($fotoPages as $pageIdx => $fotoChunk):
    $isLastFotoPage = ($pageIdx === $totalFotoPages - 1);
?>
<article class="page-sheet renops-smeac hal-lampiran-foto<?= $draft ? ' is-draft' : '' ?>" data-pdf-orientation="portrait" data-pdf-header="<?= e($headerBerjalan) ?>">
    <div class="sheet-inner">
        <header class="doc-header border-b pb-2 mb-3">
            <h2 class="h1 text-center font-bold">LAMPIRAN: DOKUMENTASI OPERASI SAR</h2>
            <p class="text-xs text-center text-slate-500">Rencana Operasi SAR — <?= htmlspecialchars($namaOps !== '' ? $namaOps : 'Operasi SAR') ?></p>
        </header>
        <div class="foto-grid" data-base-gambar="<?= (int)$nGambar ?>">
            <?php foreach ($fotoChunk as $iFoto => $f): $ket = trim((string)($f['keterangan'] ?? '')) ?: 'Dokumentasi Operasi SAR'; ?>
                <figure class="foto"<?= $draft ? ' data-id-foto="' . (int)($f['id_foto'] ?? 0) . '"' : '' ?>>
                    <img src="<?= $img((string)($f['path'] ?? '')) ?>" alt="<?= e($ket) ?>">
                    <?php if ($draft): ?>
                        <label class="foto-select-wrap no-print" title="Pilih foto untuk hapus massal">
                            <input type="checkbox" class="foto-select-cb" data-foto-pilih value="<?= (int)($f['id_foto'] ?? 0) ?>" aria-label="Pilih foto">
                            <span>Pilih</span>
                        </label>
                        <div class="foto-ctl no-print">
                            <span class="foto-drag-handle" data-foto-drag title="Tarik untuk mengubah urutan foto" aria-label="Tarik untuk mengubah urutan foto">&#8942;&#8942;</span>
                            <button type="button" data-foto-hapus title="Hapus foto" aria-label="Hapus foto"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg></button>
                        </div>
                        <figcaption class="cap-gambar"><span class="foto-num">Gambar <?= ++$nGambar ?>. </span><span class="foto-ket" data-foto-ket="<?= e(trim((string)($f['keterangan'] ?? ''))) ?>"><?= e($ket) ?></span></figcaption>
                    <?php else: ?>
                        <?= $gambar($ket) ?>
                    <?php endif; ?>
                </figure>
            <?php endforeach; ?>
        </div>
        <?php if ($draft && $isLastFotoPage): ?>
            <div class="mt-3 text-center no-print">
                <div class="foto-bulk-bar" id="fotoBulkBar"<?= $totalFotos === 0 ? ' hidden' : '' ?>>
                    <label class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-700 cursor-pointer select-none">
                        <input type="checkbox" id="cbPilihSemuaFoto" class="w-3.5 h-3.5 accent-orange-600 cursor-pointer">
                        <span>Pilih Semua Foto (<span id="countFotoTerpilih">0</span> terpilih)</span>
                    </label>
                    <button type="button" id="btnHapusFotoTerpilih" disabled class="px-3 py-1.5 bg-rose-600 hover:bg-rose-500 disabled:opacity-40 text-white rounded-lg font-bold text-xs cursor-pointer transition">
                        Hapus Foto Terpilih
                    </button>
                </div>
                <button type="button" id="btnUploadFoto" class="px-4 py-2 bg-orange-600 hover:bg-orange-500 text-white rounded-lg font-bold text-xs cursor-pointer">
                    + Upload Foto Dokumentasi
                </button>
                <small class="block text-slate-500 text-xs mt-1">JPG/PNG/WebP, maksimal 5 MB per foto, maksimal 12 foto. Tarik foto untuk mengatur urutan; keterangan diedit langsung di bawah foto.</small>
            </div>
        <?php endif; ?>
    </div>
</article>
<?php endforeach; ?>
<?php endif; ?>
<script>
(function () {
    function isHeadingBlock(el) {
        if (!el || !el.tagName) return false;
        if (el.tagName === 'H2' || el.tagName === 'H3' || el.tagName === 'H4') return true;
        if (el.children && el.children.length === 1) {
            var ch = el.children[0].tagName;
            if (ch === 'H2' || ch === 'H3' || ch === 'H4') return true;
        }
        return false;
    }

    function stampHeadersAndFooters(parent, defaultHeader) {
        var allSheets = parent.querySelectorAll('.page-sheet.renops-smeac');
        var total = allSheets.length;
        for (var i = 0; i < total; i++) {
            var sh = allSheets[i];
            var hdrText = sh.getAttribute('data-pdf-header') || defaultHeader || '';
            var hdr = sh.querySelector(':scope > .sheet-hdr');
            if (!hdr) {
                hdr = document.createElement('div');
                hdr.className = 'sheet-hdr no-print';
                sh.insertBefore(hdr, sh.firstChild);
            }
            hdr.textContent = hdrText;

            var ftr = sh.querySelector(':scope > .sheet-ftr');
            if (!ftr) {
                ftr = document.createElement('div');
                ftr.className = 'sheet-ftr no-print';
                sh.appendChild(ftr);
            }
            ftr.textContent = 'Halaman ' + (i + 1) + ' dari ' + total;
        }
    }

    window.paginateRenopsSheets = function () {
        var doc = document.getElementById('renopsSmeacDoc');
        if (!doc || !doc.parentNode) return;
        var parent = doc.parentNode;

        var ae = document.activeElement;
        if (ae && ae.isContentEditable && (doc.contains(ae) || (ae.closest && ae.closest('.renops-cont')))) {
            stampHeadersAndFooters(parent, doc.getAttribute('data-pdf-header'));
            return;
        }

        var oldConts = parent.querySelectorAll('.page-sheet.renops-smeac.renops-cont');
        for (var c = 0; c < oldConts.length; c++) {
            var cont = oldConts[c];
            while (cont.firstChild) {
                var node = cont.firstChild;
                if (node.nodeType === 1 && (node.classList.contains('sheet-hdr') || node.classList.contains('sheet-ftr'))) {
                    cont.removeChild(node);
                } else {
                    doc.appendChild(node);
                }
            }
            if (cont.parentNode) cont.parentNode.removeChild(cont);
        }

        var prevZoom = doc.style.zoom;
        doc.style.zoom = '1';

        var mmPx = (doc.offsetWidth || 793.7) / 210;
        var maxContentH = Math.floor(259 * mmPx);

        var items = [];
        for (var i = 0; i < doc.children.length; i++) {
            var el = doc.children[i];
            if (el.classList.contains('renops-watermark') || el.classList.contains('sheet-hdr') || el.classList.contains('sheet-ftr')) {
                continue;
            }
            items.push({
                el: el,
                top: el.offsetTop,
                bottom: el.offsetTop + el.offsetHeight,
                isHead: isHeadingBlock(el)
            });
        }

        if (items.length > 0) {
            var groups = [];
            var currentGroup = [];
            var sheetStartTop = items[0].top;

            for (var j = 0; j < items.length; j++) {
                var it = items[j];
                var checkBottom = it.bottom;
                if (it.isHead && j + 1 < items.length) {
                    var nextBottom = items[j + 1].bottom;
                    if (nextBottom - it.top <= maxContentH) {
                        checkBottom = nextBottom;
                    }
                }
                if (currentGroup.length > 0 && (checkBottom - sheetStartTop > maxContentH)) {
                    groups.push(currentGroup);
                    currentGroup = [it.el];
                    sheetStartTop = it.top;
                } else {
                    currentGroup.push(it.el);
                }
            }
            if (currentGroup.length > 0) {
                groups.push(currentGroup);
            }

            var refSheet = doc;
            var isDraft = doc.classList.contains('is-draft');
            var pdfHeader = doc.getAttribute('data-pdf-header') || '';

            for (var g = 1; g < groups.length; g++) {
                var newSheet = document.createElement('article');
                newSheet.className = 'page-sheet renops-smeac renops-cont' + (isDraft ? ' is-draft' : '');
                newSheet.setAttribute('data-pdf-orientation', 'portrait');
                newSheet.setAttribute('data-pdf-header', pdfHeader);
                for (var k = 0; k < groups[g].length; k++) {
                    newSheet.appendChild(groups[g][k]);
                }
                if (refSheet.nextSibling) {
                    parent.insertBefore(newSheet, refSheet.nextSibling);
                } else {
                    parent.appendChild(newSheet);
                }
                refSheet = newSheet;
            }
        }

        doc.style.zoom = prevZoom;
        var contSheets = parent.querySelectorAll('.page-sheet.renops-smeac.renops-cont');
        for (var m = 0; m < contSheets.length; m++) {
            contSheets[m].style.zoom = prevZoom;
        }

        stampHeadersAndFooters(parent, doc.getAttribute('data-pdf-header'));
    };

    function initPagination() {
        window.paginateRenopsSheets();
        var parent = document.getElementById('renopsSmeacDoc');
        parent = parent ? parent.parentNode : document;
        var imgs = parent.querySelectorAll('.page-sheet.renops-smeac img');
        for (var i = 0; i < imgs.length; i++) {
            if (!imgs[i].complete) {
                imgs[i].addEventListener('load', function () {
                    window.paginateRenopsSheets();
                });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPagination);
    } else {
        initPagination();
    }
})();
</script>
<?php
})($doc, $mode ?? 'final', $qrUrl ?? null, $versiLabel ?? '');
