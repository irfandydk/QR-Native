<?php
/**
 * QR-SAR - Editor draf Dokumen RENOPS (Opsi A: edit langsung di kertas).
 *
 * @var int $idRenops
 * @var int $idOperasi
 * @var array $doc  Kontrak Data Dokumen (resolve())
 * @var int $rev
 * @var ?array $terbit
 * @var bool $belumTerbit
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$meta = $doc['meta'] ?? [];
$toc = [
    'awareness' => 'A. Situation', 'upaya_sebelumnya' => '   Initial Action', 'mission' => 'B. Mission',
    'waktu' => 'C. Implementation', 'aset' => '   Alokasi Unsur', 'sru' => '   Penugasan SRU',
    'operation_stage' => 'D. Operation Stage', 'administrasi' => 'E. Adm & Logistik', 'struktur' => 'F. Koordinasi & Struktur',
];
$badge = $terbit ? 'TERBIT v' . (int)$terbit['versi'] : 'DRAF';
?>
<div class="max-w-full mx-auto space-y-3 pb-20" id="renopsDokumenApp"
     data-id-renops="<?= (int)$idRenops ?>" data-rev="<?= (int)$rev ?>"
     data-versi="<?= (int)($terbit['versi'] ?? 0) ?>">

  <div class="no-print flex flex-wrap items-center gap-2 bg-slate-900 text-slate-100 rounded-xl px-4 py-3 text-xs font-semibold">
    <a href="<?= BASE_URL ?>/operasi_detail.php?id=<?= (int)$idOperasi ?>&tab=renops" class="hover:text-orange-400">&larr; Detail Operasi</a>
    <span>RENOPS H.<?= e((string)($meta['hari_label'] ?? '')) ?> &bull; <?= e(TemplateHelper::formatTanggalSimple((string)($meta['tanggal'] ?? ''))) ?></span>
    <span class="px-2 py-0.5 rounded-full bg-amber-200 text-amber-900" id="badgeStatus"><?= e($badge) ?></span>
    <?php if ($terbit && $belumTerbit): ?>
      <span class="px-2 py-0.5 rounded-full bg-rose-200 text-rose-900" id="badgeBelumTerbit">Ada perubahan belum diterbitkan</span>
    <?php endif; ?>
    <span class="flex-1"></span>
    <button type="button" id="btnSimpanDraf" class="px-3 py-1.5 rounded-lg bg-slate-600 hover:bg-slate-500 text-white font-bold">Simpan Draf</button>
    <input type="file" id="inputFoto" class="hidden" multiple accept="image/jpeg,image/png,image/webp">
    <button type="button" id="btnTerbitkan" class="px-3 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-500 text-white font-bold disabled:opacity-60">Terbitkan QR</button>
    <a href="<?= BASE_URL ?>/operasi.php?action=print_renops&amp;draf=1&amp;id_renops=<?= (int)$idRenops ?>" target="_blank" rel="noopener" id="btnCetakDraf" title="Cetak / unduh PDF draf (watermark DRAF, tanpa QR)" class="px-3 py-1.5 rounded-lg bg-slate-200 hover:bg-white text-slate-900 font-bold no-underline">Cetak Draf / PDF</a>
    <?php if ($terbit): ?>
      <a href="<?= BASE_URL ?>/operasi.php?action=print_renops&amp;id_renops=<?= (int)$idRenops ?>" target="_blank" rel="noopener" id="btnCetak" title="Cetak / unduh PDF versi terbit ber-QR" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold no-underline">Cetak Terbit / PDF</a>
    <?php endif; ?>
  </div>

  <?php if (!empty($meta['closed'])): ?>
    <div data-closed-warning class="no-print rounded-xl border border-amber-400 bg-amber-50 text-amber-900 px-4 py-3 text-xs font-semibold">
      Operasi ini sudah berstatus CLOSED. Tabel 1 (Data Kejadian) menampilkan status korban TERAKHIR. Periksa dan koreksi dulu sebelum menerbitkan dokumen.
    </div>
  <?php endif; ?>

  <div id="renopsMsg" class="no-print hidden rounded-xl border px-4 py-2 text-xs font-semibold" role="status"></div>

  <div class="flex gap-3 items-start">
    <aside class="no-print w-48 shrink-0 sticky top-2 space-y-3">
      <nav id="renopsToc" class="bg-white dark:bg-slate-800 rounded-xl p-3 text-xs space-y-1 shadow-sm">
        <p class="font-bold text-slate-500 mb-1">Daftar Isi</p>
        <?php foreach ($toc as $key => $label): ?>
          <a href="#" data-goto="<?= e($key) ?>" class="block text-slate-700 dark:text-slate-200 hover:text-orange-500 whitespace-pre"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </aside>
    <div class="flex-1 min-w-0 overflow-x-auto pr-16" id="renopsPaper">
      <?php $mode = 'draft'; $qrUrl = null; $versiLabel = ''; include __DIR__ . '/partials/renops_smeac_doc.php'; ?>
    </div>
  </div>
</div>
<script src="<?= assetUrl('assets/js/pages/renops_dokumen.js') ?>"></script>
