<?php
/**
 * Test Suite: Roll-Out Daftar RENOPS & LAPBANG + Daftar Ringkas Tab BRA Detail Operasi SAR
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../config/app.php';

$passed = 0;
$total = 0;

function assertCheck(string $desc, bool $cond): void {
    global $passed, $total;
    $total++;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$desc}\n";
    } else {
        echo "  [FAIL] {$desc}\n";
    }
}

$rootDir = dirname(__DIR__);
$renopsSrc = (string)file_get_contents($rootDir . '/views/operasi/partials/tab_renops.php');
$sitrepSrc = (string)file_get_contents($rootDir . '/views/operasi/partials/tab_sitrep.php');
$braSrc    = (string)file_get_contents($rootDir . '/views/operasi/partials/tab_bra.php');
$jsSrc     = (string)file_get_contents($rootDir . '/assets/js/pages/operasi_detail.js');

echo "=== 1. TAB RENOPS ROLL-OUT ===\n";
assertCheck(
    'tab_renops.php memiliki tombol roll-out dengan aria-expanded="false" dan toggleDetailRollout',
    str_contains($renopsSrc, 'aria-expanded="false"')
        && str_contains($renopsSrc, 'aria-controls="renops-detail-')
        && str_contains($renopsSrc, "toggleDetailRollout('renops-detail-")
);
assertCheck(
    'tab_renops.php menyembunyikan panel detail secara default (class="hidden ...")',
    str_contains($renopsSrc, 'id="renops-detail-<?= $renId ?>" class="hidden')
);
assertCheck(
    'tab_renops.php mempertahankan tombol aksi Naskah WA, Edit, BRA Renops, dan label Satuan',
    str_contains($renopsSrc, '<span>Naskah WA</span>')
        && str_contains($renopsSrc, '<span>Edit</span>')
        && str_contains($renopsSrc, '<span>BRA Renops</span>')
        && str_contains($renopsSrc, "Satuan: <?= e(\$ren['satuan_jarak']) ?>")
);

echo "\n=== 2. TAB LAPBANG ROLL-OUT ===\n";
assertCheck(
    'tab_sitrep.php memiliki tombol roll-out dengan aria-expanded="false" dan toggleDetailRollout',
    str_contains($sitrepSrc, 'aria-expanded="false"')
        && str_contains($sitrepSrc, 'aria-controls="sitrep-detail-')
        && str_contains($sitrepSrc, "toggleDetailRollout('sitrep-detail-")
);
assertCheck(
    'tab_sitrep.php menyembunyikan panel detail secara default (class="hidden ...")',
    str_contains($sitrepSrc, 'id="sitrep-detail-<?= $lapId ?>" class="hidden')
);
assertCheck(
    'tab_sitrep.php mempertahankan akses edit Penutupan saat CLOSED dan aksi standar',
    str_contains($sitrepSrc, '$isOpen || $isPenutupan')
        && str_contains($sitrepSrc, '<span>Naskah WA</span>')
        && str_contains($sitrepSrc, '<span>Edit</span>')
        && str_contains($sitrepSrc, '<span>BRA LAPBANG</span>')
);

echo "\n=== 3. JS ROLL-OUT & AUTO-EXPAND DARI HIRARKI ===\n";
assertCheck(
    'operasi_detail.js mendefinisikan toggleDetailRollout() dan auto-expand di navigateToDoc()',
    str_contains($jsSrc, 'function toggleDetailRollout(')
        && str_contains($jsSrc, 'toggleDetailRollout(panelId, null, true)')
);

echo "\n=== 4. TAB BRA SAR RINGKAS (DAFTAR SAJA TANPA DETAIL ISI) ===\n";
assertCheck(
    'tab_bra.php tidak lagi menampilkan pratinjau isi sections maupun detail TWU/TWB/Jumlah Kata',
    !str_contains($braSrc, "\$bra['sections']")
        && !str_contains($braSrc, "\$bra['twu']")
        && !str_contains($braSrc, "\$bra['twb']")
        && !str_contains($braSrc, "\$bra['jumlah_kata']")
);
assertCheck(
    'tab_bra.php menampilkan daftar tabel ringkas (No BRA SAR, Kategori, Perihal, Aksi)',
    str_contains($braSrc, 'No BRA SAR')
        && str_contains($braSrc, 'Kategori')
        && str_contains($braSrc, 'Perihal')
        && str_contains($braSrc, 'Lihat Dokumen')
);
assertCheck(
    'tab_bra.php mempertahankan navigasi kembali return_to=operasi_bra&return_op= (>= 4 tautan)',
    substr_count($braSrc, 'return_to=operasi_bra&return_op=') >= 4
);

echo "\n=== RINGKASAN: {$passed}/{$total} PASS ===\n";
exit($passed === $total ? 0 : 1);
