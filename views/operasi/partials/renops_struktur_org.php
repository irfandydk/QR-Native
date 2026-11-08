<?php
/**
 * QR-SAR - Bagan Struktur Organisasi Operasi SAR (HTML/CSS, meniru lampiran RENOPS):
 * SC -> SMC -> OSC -> SRU di kolom kiri, enam staf di kanan pada satu garis dari SC.
 * Dipanggil dari renops_smeac_doc.php (memakai $S, $sec, $badge, $col, $row).
 * Nilai [data-col] tidak diberi text-transform: editor membaca innerText apa adanya.
 */

declare(strict_types=1);

$orgData = $S['struktur'] ?? [];
$orgStaf = [
    'staf_operasi' => 'STAF OPERASI', 'staf_admin' => 'STAF ADMIN', 'staf_logistik' => 'STAF LOGISTIK',
    'staf_intelijen' => 'STAF INTELIJEN', 'staf_humas' => 'STAF HUMAS', 'staf_komunikasi' => 'STAF KOMUNIKASI',
];
$orgBox = fn(string $judul, string $key): string => '<div class="org-box"><div class="oh">' . e($judul) . '</div><div class="ob"'
    . $col($key) . '>' . nl2br(e((string)($orgData[$key] ?? ''))) . '</div></div>';
$orgSru = $orgData['sru'] ?? [];
?>
<style>
    .renops-smeac .org { text-align: left; }
    .renops-smeac .org-grid { display: grid; grid-template-columns: 26% repeat(6, 1fr); }
    .renops-smeac .org-box { border: 1.5pt solid #000000; background: #ffffff; text-align: center; }
    .renops-smeac .org-box .oh { background: #ffffff; color: #000000; border-bottom: 1pt solid #000000; font-weight: bold; font-size: 8pt; line-height: 1.2; padding: 4pt; }
    .renops-smeac .org-box .ob { font-size: 8pt; line-height: 1.25; padding: 4pt; min-height: 8mm; color: #000000; }
    .renops-smeac .org-sc, .renops-smeac .org-staf, .renops-smeac .org-bus, .renops-smeac .org-sru { position: relative; }
    .renops-smeac .org-sc { padding-bottom: 3mm; }
    .renops-smeac .org-sc::after, .renops-smeac .org-bus::after, .renops-smeac .org-staf::before, .renops-smeac .org-sru::after {
        content: ""; position: absolute; left: 50%; border-left: 1.5pt solid #000000;
    }
    .renops-smeac .org-sc::after { bottom: 0; height: 3mm; }
    .renops-smeac .org-bus { height: 4mm; }
    .renops-smeac .org-bus::before, .renops-smeac .org-sru::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; border-top: 1.5pt solid #000000;
    }
    .renops-smeac .org-bus.first::before, .renops-smeac .org-sru:first-child::before { left: 50%; }
    .renops-smeac .org-bus.last::before, .renops-smeac .org-sru:last-child::before { right: 50%; }
    .renops-smeac .org-sru:only-child::before { display: none; }
    .renops-smeac .org-bus::after { top: 0; bottom: 0; }
    .renops-smeac .org-staf { padding: 5mm 1.2mm 0; }
    .renops-smeac .org-staf::before { top: 0; height: 5mm; }
    .renops-smeac .org-kiri .org-box { width: 86%; margin: 0 auto; }
    .renops-smeac .org-stem { width: 0; height: 3mm; margin: 0 auto; border-left: 1.5pt solid #000000; }
    .renops-smeac .org-sru-row { display: flex; }
    .renops-smeac .org-sru { flex: 0 1 40mm; min-width: 0; padding: 4mm 1.2mm 0; }
    .renops-smeac .org-sru::after { top: 0; height: 4mm; }
    /* SRU tunggal: tepat di bawah OSC (tengah kolom kiri 26%). */
    .renops-smeac .org-sru:only-child { margin-left: calc(13% - 20mm); }
</style>
<div class="sec org"<?= $sec('struktur', 'struktur') ?>>
    <?= $badge('struktur') ?>
    <div class="org-grid">
        <div class="org-sc"><?= $orgBox('KOORDINATOR PENCARIAN DAN PERTOLONGAN (SC)', 'sc') ?></div>
        <?php for ($orgI = 1; $orgI < 7; $orgI++): ?><div></div><?php endfor; ?>
        <?php for ($orgI = 0; $orgI < 7; $orgI++): ?><div class="org-bus<?= $orgI === 0 ? ' first' : ($orgI === 6 ? ' last' : '') ?>"></div><?php endfor; ?>
        <div class="org-kiri">
            <?= $orgBox('KOORDINATOR MISI PENCARIAN DAN PERTOLONGAN (SMC)', 'smc') ?>
            <div class="org-stem"></div>
            <?= $orgBox('KOORDINATOR LAPANGAN (OSC)', 'osc') ?>
            <?php if ($orgSru): ?><div class="org-stem"></div><?php endif; ?>
        </div>
        <?php foreach ($orgStaf as $orgKey => $orgJudul): ?>
            <div class="org-staf"><?= $orgBox($orgJudul, $orgKey) ?></div>
        <?php endforeach; ?>
    </div>
    <?php if ($orgSru): ?>
        <div class="org-sru-row">
            <?php foreach ($orgSru as $orgS): ?>
                <div class="org-sru"<?= $row ?>>
                    <div class="org-box">
                        <div class="oh"<?= $col('nama') ?>><?= e((string)($orgS['nama'] ?? '')) ?></div>
                        <div class="ob"<?= $col('keterangan') ?>><?= nl2br(e((string)($orgS['keterangan'] ?? ''))) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
