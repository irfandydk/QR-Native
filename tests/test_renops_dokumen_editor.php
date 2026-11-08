<?php
/** Test statis: view & JS editor draf RENOPS (Task 8). */
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$ok = 0; $total = 0;
function assertCondition(bool $c, string $m): void {
    global $ok, $total;
    $total++; $ok += $c ? 1 : 0;
    echo ($c ? ' [PASS] ' : ' [FAIL] ') . $m . "\n";
}

$view = (string)@file_get_contents(ROOT_DIR . '/views/operasi/renops_dokumen.php');
$js = (string)@file_get_contents(ROOT_DIR . '/assets/js/pages/renops_dokumen.js');
$ctl = (string)file_get_contents(ROOT_DIR . '/controllers/OperasiController.php');

echo "=== TEST RENOPS DOKUMEN EDITOR ===\n\n";
assertCondition($view !== '' && $js !== '', 'view & JS ada');
assertCondition(str_contains($view, 'partials/renops_smeac_doc.php') && str_contains($view, "'draft'"), 'view meng-include partial mode draft');
assertCondition(str_contains($view, "assetUrl('assets/js/pages/renops_dokumen.js')"), 'JS dimuat via assetUrl');
foreach (['btnSimpanDraf', 'btnTerbitkan', 'btnCetakDraf', 'btnCetak', 'renopsToc', 'data-closed-warning', 'id="inputFoto"'] as $id) {
    assertCondition(str_contains($view, $id), "view memuat {$id}");
}
assertCondition(str_contains($view, 'print_renops&amp;draf=1') && str_contains($view, 'print_renops'), 'cetak draf (tanpa QR) & cetak terbit');
// Tombol aksi berada di tempat isinya (bukan toolbar atas): capture peta LKP di bagian peta, upload foto di bagian dokumentasi.
$docPartial = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php');
assertCondition(!str_contains($view, 'id="btnUploadFoto"') && !str_contains($view, 'id="btnCapturePeta"'), 'Toolbar atas tanpa tombol upload foto/capture peta');
assertCondition(substr_count($docPartial, 'id="btnCapturePeta"') === 2 && str_contains($docPartial, "capture_doc=RENOPS';")
    && strpos($docPartial, 'id="btnCapturePeta"') > strpos($docPartial, 'class="peta"') - 400, 'Capture Peta LKP di bagian peta (kosong & perbarui)');
assertCondition(str_contains($docPartial, 'id="btnUploadFoto"') && strpos($docPartial, 'id="btnUploadFoto"') > strpos($docPartial, 'class="foto-grid"')
    && strpos($docPartial, 'id="btnUploadFoto"') < strpos($docPartial, 'ADMINISTRATION AND LOGISTICS'), 'Upload foto tepat di bawah tempat foto dokumentasi');
assertCondition(str_contains($js, 'f.size > 5 * 1024 * 1024') && str_contains($js, 'Ukuran foto maksimal 5 MB'), 'JS: foto > 5 MB ditolak sebelum diunggah');
assertCondition(str_contains($view, 'belumTerbit') && str_contains($view, 'Ada perubahan belum diterbitkan'), 'lencana perubahan belum terbit');
assertCondition(str_contains($view, 'image/jpeg,image/png,image/webp'), 'input file accept');
assertCondition(str_contains($js, 'plaintext-only') && str_contains($js, 'innerText'), 'JS: plaintext-only + innerText');
assertCondition(!str_contains($js, 'innerHTML') && !str_contains($js, 'insertAdjacentHTML'), 'JS: tanpa innerHTML');
assertCondition(preg_match('/rev\b/', $js) === 1 && str_contains($js, '/dokumen/terbit'), 'JS mengirim rev & terbit');
assertCondition(str_contains($js, 'baru === false') && str_contains($js, 'Tidak ada perubahan sejak versi terakhir'), 'JS: baru=false');
assertCondition(str_contains($js, 'Terbitkan versi baru? QR versi sebelumnya akan berstatus'), 'JS: konfirmasi terbit');
assertCondition((bool)preg_match('/if \(!empty\(\$meta\[\'closed\'\]\)\): \?>\s*<div data-closed-warning[^>]*>.*?<\?php endif;/s', $view), 'peringatan CLOSED hanya bila meta.closed');
assertCondition(str_contains($ctl, "'renops_dokumen'") && !str_contains((string)substr($ctl, (int)strpos($ctl, 'function renopsDokumen()'), 600), 'http_response_code(404)'), 'controller: aksi renops_dokumen tanpa 404+redirect');
assertCondition(str_contains($js, 'Number.isInteger(res.rev)'), 'JS: guard rev respons');
assertCondition((bool)preg_match("/renops_dokumen'[^}]*requireLogin|function renopsDokumen\(\)[^}]*requireLogin/s", $ctl), 'controller: login');

// Fix wave akhir: urutan foto, muat pas lebar (layar saja), filter kolom grup, unggah parsial
$partial = (string)file_get_contents(ROOT_DIR . '/views/operasi/partials/renops_smeac_doc.php');
$print = (string)file_get_contents(ROOT_DIR . '/views/operasi/renops_smeac_print.php');
$exp = (string)file_get_contents(ROOT_DIR . '/assets/js/pdf_document_exporter.js');
assertCondition(str_contains($partial, 'data-foto-geser="-1"') && str_contains($partial, 'aria-label="Naikkan urutan foto"')
    && str_contains($partial, 'aria-label="Turunkan urutan foto"') && str_contains($partial, 'aria-label="Hapus foto"')
    && !str_contains($view, 'renopsFotoPanel'), 'Foto: tombol ▲/▼ & ikon hapus di foto (kertas), panel kiri dihapus');
assertCondition(str_contains($partial, 'class="foto-ket" data-foto-ket=') && str_contains($js, "$('.foto-ket', fig)")
    && str_contains($js, 'keterangan: v })'), 'Foto: keterangan disunting langsung di bawah gambar');
assertCondition(str_contains($js, 'if (s && ev.target.isContentEditable) lastFocus[s.dataset.sec] = ev.target;'), 'Hapus baris/butir terpilih: fokus tombol tidak menimpa pilihan');
assertCondition(str_contains($js, 'data-foto-geser') && str_contains($js, "api('GET', '/dokumen')") && str_contains($js, '{ urutan: '), 'JS: geser foto via PUT urutan');
assertCondition(str_contains($js, "sh.style.zoom = String(Math.min(1, (paper.clientWidth - 72) / widths[i]))") && str_contains($js, "addEventListener('resize', fit)"), 'JS: zoom muat pas lebar tiap lembar + resize');
assertCondition(str_contains($print, "sh.style.zoom = String(Math.min(1, (box.clientWidth - 16) / widths[i]))") && str_contains($print, "addEventListener('resize', fit)"), 'halaman cetak/publik: zoom muat pas lebar tiap lembar');
assertCondition((bool)preg_match('/@media print \{\s*\.page-sheet\.renops-smeac \{[^}]*zoom: 1 !important;/', $partial), 'CSS cetak: zoom direset ke 1');
assertCondition(str_contains($exp, "zoom: '1'") && str_contains($exp, "STYLE_KEYS.forEach(function (k) { el.style[k] = prev[k]; })")
    && str_contains($exp, "'lineHeight', 'zoom', 'breakBefore', 'pageBreakBefore']"), 'exporter PDF: zoom 1 saat html2pdf, lalu dipulihkan (semua lembar)');
assertCondition(str_contains($exp, "breakBefore: 'auto',") && str_contains($exp, "pageBreakBefore: 'auto'"), 'exporter PDF: break-before lembar landscape tidak jadi halaman kosong');
assertCondition(str_contains($js, "k !== 'grup' && v !== ''"), 'JS: baris kosong abaikan kolom grup tersembunyi');
assertCondition(str_contains($js, "fileInput.value = ''") && str_contains($js, 'e.data && e.data.foto'), 'JS: unggah parsial (422 + foto) muat ulang, input file direset');

echo "\nHasil: {$ok}/{$total} lulus.\n";
exit($ok === $total ? 0 : 1);
