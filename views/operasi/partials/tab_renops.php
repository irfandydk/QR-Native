<?php
/**
 * QR-SAR - Tab Rencana Operasi SAR (RENOPS)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */
$renopsDocStatus = RenopsDokumenModel::statusByOperasi((int)$id);
?>
<div id="tab-content-renops" class="tab-pane space-y-3.5" style="display: <?= $activeTab === 'renops' ? 'block' : 'none' ?>;">

  <?php if (!$isOpen): ?>
    <div class="px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="text-xs">
        <strong class="font-bold">Operasi Ditutup (CLOSED).</strong>
        <span class="text-slate-600 dark:text-slate-300 ml-1">Penambahan RENOPS dinonaktifkan kecuali operasi diperpanjang.</span>
      </div>
      <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition whitespace-nowrap cursor-pointer shrink-0" title="Khusus perpanjangan hari operasi SAR lanjutan (misal H7+1)">
        Perpanjang Operasi SAR
      </button>
    </div>
  <?php endif; ?>

  <?php if (empty($operasi['renops'])): ?>
    <div class="opsd-panel p-10 text-center rounded-2xl space-y-3">
      <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto border border-slate-200 dark:border-slate-700">
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
      </div>
      <div class="max-w-sm mx-auto space-y-1">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Rencana Operasi (RENOPS)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          Memuat pembagian SRU, sektor koordinat, dan pola pencarian per hari operasi.
        </p>
      </div>
      <?php if ($isOpen): ?>
        <div class="pt-1">
          <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-9 px-4 rounded-xl font-semibold text-xs transition no-underline">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            <span>Buat RENOPS <?= e($nextRenops['hari_ke'] ?? $hariKe) ?></span>
          </a>
        </div>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="flex flex-wrap items-center justify-between gap-2.5 no-print">
      <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
        <span class="opsd-num font-mono font-semibold text-slate-800 dark:text-slate-200"><?= count($operasi['renops']) ?></span> rencana operasi tercatat
      </p>
      <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg font-semibold text-xs transition no-underline">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
          <span>Buat RENOPS <?= e($nextRenops['hari_ke'] ?? $hariKe) ?></span>
        </a>
      <?php endif; ?>
    </div>

    <!-- Compact Hierarchy-Style Collapsible List -->
    <div class="relative pl-7 space-y-3 before:absolute before:left-[11px] before:top-4 before:bottom-4 before:w-px before:bg-slate-200 dark:before:bg-slate-800">
      <?php foreach ($operasi['renops'] as $renIdx => $ren):
        $renId = (int)$ren['id_renops'];
        $snapUnsur = !empty($ren['detail_unsur']) && is_array($ren['detail_unsur']) ? $ren['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
        $snapAlut = !empty($ren['detail_alut']) && is_array($ren['detail_alut']) ? $ren['detail_alut'] : ($operasi['detail_alut'] ?? []);
        $totalSnapPers = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $snapUnsur));
        $totalSnapAlut = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $snapAlut));
        $sruCount = count($ren['srus'] ?? []);

        $renTgl = !empty($ren['tanggal_laporan']) ? $ren['tanggal_laporan'] : (!empty($ren['waktu_briefing']) ? date('Y-m-d', strtotime($ren['waktu_briefing'])) : date('Y-m-d'));
        $dynamicHariKe = TemplateHelper::hitungHariKe($operasi, $renTgl, $ren['hari_ke'] ?? null);
        $renForWa = $ren;
        $renForWa['hari_ke'] = $dynamicHariKe;

        $renWa = $ren['full_text_wa'] ?? '';
        if (empty($renWa) || strpos($renWa, 'FILE AKTIF') === false || strpos($renWa, 'Data Korban:') !== false || strpos($renWa, '• Gelombang :') === false) {
            $renWa = TemplateHelper::generateRenopsWA($operasi, $renForWa, $ren['srus'] ?? [], $operasi['korban'] ?? [], $snapUnsur, $snapAlut);
        } elseif (!empty($ren['hari_ke']) && $ren['hari_ke'] !== $dynamicHariKe) {
            $renWa = preg_replace('/\bRENCANA (?:OPERASI|OPS) SAR\s+H(?:\.?\d+(?:\s*\+\s*\d+)?)/i', 'RENCANA OPS SAR ' . $dynamicHariKe, $renWa, 1) ?? $renWa;
        }

        $renGel = trim((string)(($ren['gelombang'] ?? '') !== '' ? $ren['gelombang'] : ($operasi['gelombang'] ?? '')));
        $renAng = trim((string)(($ren['angin'] ?? '') !== '' ? $ren['angin'] : ($operasi['angin'] ?? '')));
      ?>
      <div class="relative">
        <div class="opsd-num absolute -left-7 top-4 w-6 h-6 rounded-full opsd-accent-badge flex items-center justify-center font-mono font-bold text-[11px] ring-4 ring-[var(--bg-body)] z-10">
          <?= $renIdx + 1 ?>
        </div>

        <article id="card-renops-<?= $renId ?>" class="opsd-panel opsd-row-card rounded-xl overflow-hidden">
          <!-- Compact List Row Header -->
          <div class="px-4 py-3.5 flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
            <div class="space-y-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="opsd-accent-badge px-2 py-0.5 rounded-md font-mono font-bold text-[11px]">
                  RENOPS
                </span>
                <span class="opsd-num font-mono text-[11px] font-bold opsd-accent-text">
                  <?= e($dynamicHariKe) ?>
                </span>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                  <?= TemplateHelper::formatTanggalIndo($ren['tanggal_laporan']) ?>
                </h3>
                <?php if (!empty($renopsDocStatus[$renId]['terbit_versi'])): ?>
                  <span class="opsd-num px-2 py-0.5 rounded-md text-[11px] font-mono font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400">TERBIT v<?= (int)$renopsDocStatus[$renId]['terbit_versi'] ?></span>
                <?php else: ?>
                  <span class="px-2 py-0.5 rounded-md text-[11px] font-medium bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">Draf</span>
                <?php endif; ?>
                <?php if (!empty($ren['satuan_jarak'])): ?>
                  <span class="px-2 py-0.5 rounded-md text-[11px] font-mono font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                    Satuan: <?= e($ren['satuan_jarak']) ?>
                  </span>
                <?php endif; ?>
              </div>

              <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-0.5">
                <span class="opsd-num font-mono text-[11px]">Mulai <?= TemplateHelper::formatTW($ren['waktu_mulai_ops']) ?></span>
                <span class="opsd-num"><?= $sruCount ?> SRU</span>
                <span class="opsd-num"><?= $totalSnapPers ?> Personel, <?= $totalSnapAlut ?> Alut</span>
              </div>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap shrink-0 no-print">
              <button type="button" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-renops-<?= $renId ?>')" title="Lihat pratinjau format WhatsApp RENOPS">
                <span>Naskah WA</span>
              </button>

              <?php if ($isOpen): ?>
                <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>&id_renops=<?= $renId ?>" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline">
                  <span>Edit</span>
                </a>
              <?php endif; ?>

              <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=renops&id_renops=<?= $renId ?>" class="opsd-accent-soft inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline" title="Buat Berita Radio dari RENOPS ini">
                <span>BRA Renops</span>
              </a>

              <?php if ($isOpen && AuthHelper::canDelete()): ?>
                <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer" onclick="deleteRenops(<?= $renId ?>)" aria-label="Hapus lembar renops" title="Hapus Lembar Renops">
                  <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </button>
              <?php endif; ?>

              <button
                type="button"
                aria-expanded="false"
                aria-controls="renops-detail-<?= $renId ?>"
                onclick="toggleDetailRollout('renops-detail-<?= $renId ?>', this)"
                class="opsd-ghost-btn inline-flex items-center gap-1 h-8 px-2.5 rounded-lg font-semibold text-xs transition cursor-pointer"
                title="Tampilkan atau sembunyikan detail RENOPS"
              >
                <span class="rollout-label">Detail</span>
                <svg class="rollout-chevron w-3.5 h-3.5 text-slate-400 transition-transform duration-200" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
              </button>
            </div>
          </div>

          <!-- Collapsible Roll-Out Detail Body -->
          <div id="renops-detail-<?= $renId ?>" class="hidden border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-950/25 p-4 sm:p-5 space-y-4">
            <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 pb-3.5 border-b border-slate-200/75 dark:border-slate-800 text-xs">
              <div>
                <dt class="text-[11px] text-slate-500 dark:text-slate-400">Waktu Briefing</dt>
                <dd class="opsd-num mt-0.5 font-mono font-semibold text-slate-900 dark:text-white"><?= TemplateHelper::formatTW($ren['waktu_briefing']) ?></dd>
              </div>
              <div>
                <dt class="text-[11px] text-slate-500 dark:text-slate-400">Mulai Operasi</dt>
                <dd class="opsd-num mt-0.5 font-mono font-semibold opsd-accent-text"><?= TemplateHelper::formatTW($ren['waktu_mulai_ops']) ?></dd>
              </div>
              <div>
                <dt class="text-[11px] text-slate-500 dark:text-slate-400">Prakiraan Cuaca BMKG</dt>
                <dd class="mt-0.5 font-semibold text-slate-900 dark:text-white truncate"><?= e(($ren['cuaca'] ?? '') !== '' ? $ren['cuaca'] : ($operasi['cuaca'] ?: '-')) ?></dd>
                <?php if ($renGel !== '' || $renAng !== ''): ?>
                  <dd class="text-[11px] text-slate-500 dark:text-slate-400 truncate mt-0.5">
                    Gelombang: <?= e($renGel !== '' ? $renGel : '-') ?>, Angin: <?= e($renAng !== '' ? $renAng : '-') ?>
                  </dd>
                <?php endif; ?>
              </div>
            </dl>

            <div class="space-y-2">
              <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300">Pembagian Sektor &amp; SRU</h4>
              <div class="table-responsive rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-x-auto">
                <table class="table text-xs min-w-[540px]">
                  <thead>
                    <tr>
                      <th style="width: 24%;">Nama SRU</th>
                      <th style="width: 46%;">Koordinat Sektor &amp; Tugas</th>
                      <th style="width: 14%;">Luas Area</th>
                      <th style="width: 16%;">Pola &amp; Sapuan</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($ren['srus'])): ?>
                      <tr><td colspan="4" class="text-slate-400 text-center py-3">Tidak ada pembagian SRU tersimpan.</td></tr>
                    <?php else: foreach ($ren['srus'] as $s): ?>
                      <tr>
                        <td class="font-bold opsd-accent-text"><?= e($s['nama_sru']) ?></td>
                        <td class="font-mono text-xs whitespace-pre-wrap"><?= e($s['koordinat_area']) ?></td>
                        <td class="opsd-num font-semibold text-xs"><?= e($s['luas_area']) ?></td>
                        <td>
                          <span class="px-2 py-0.5 rounded-md text-[11px] font-semibold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 block text-center"><?= e($s['pola_pencarian']) ?></span>
                          <?php if (!empty($s['total_sapuan'])): ?>
                            <div class="opsd-num text-[11px] font-mono font-semibold text-emerald-700 dark:text-emerald-400 mt-1 text-center" title="Total Sapuan / Lintasan"><?= e($s['total_sapuan']) ?></div>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <div class="pt-3.5 border-t border-slate-200/75 dark:border-slate-800 space-y-2.5">
              <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <span class="font-bold text-slate-700 dark:text-slate-300">Snapshot Potensi &amp; Armada (<?= e($dynamicHariKe) ?>)</span>
                <span class="opsd-num font-mono text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                  <?= $totalSnapPers ?> Personel, <?= $totalSnapAlut ?> Unit Alut
                </span>
              </div>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                  <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mb-1">Unsur SAR Terlibat</div>
                  <?php if (empty($snapUnsur)): ?>
                    <span class="text-slate-400">-</span>
                  <?php else: ?>
                    <ul class="divide-y divide-slate-200/70 dark:divide-slate-800/80 list-none p-0 m-0">
                      <?php foreach ($snapUnsur as $u): ?>
                        <li class="py-1.5 flex items-center justify-between gap-2">
                          <span class="text-slate-700 dark:text-slate-300"><?= e($u['nama_unsur']) ?></span>
                          <strong class="opsd-num font-mono text-slate-800 dark:text-slate-200 shrink-0"><?= (int)($u['jumlah_personil'] ?? 1) ?> Org</strong>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
                <div>
                  <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mb-1">Armada Alut &amp; Palsar</div>
                  <?php if (empty($snapAlut)): ?>
                    <span class="text-slate-400">-</span>
                  <?php else: ?>
                    <ul class="divide-y divide-slate-200/70 dark:divide-slate-800/80 list-none p-0 m-0">
                      <?php foreach ($snapAlut as $a): ?>
                        <li class="py-1.5 flex items-center justify-between gap-2">
                          <span class="text-slate-700 dark:text-slate-300"><?= e($a['nama_alut']) ?></span>
                          <strong class="opsd-num font-mono text-slate-800 dark:text-slate-200 shrink-0"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></strong>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- MODAL: VIEW WA RENOPS -->
      <div id="modal-view-wa-renops-<?= $renId ?>" class="modal-overlay">
        <div class="modal-container" style="max-width: 760px; width: 95%;">
          <div class="modal-header flex items-center justify-between border-b border-slate-100 dark:border-slate-800 p-4">
            <div class="min-w-0 pr-3">
              <h3 class="font-bold text-sm text-slate-900 dark:text-white">
                Naskah Rencana Operasi SAR (<?= e($dynamicHariKe) ?>)
              </h3>
              <p class="text-[11px] opsd-accent-text font-medium mt-0.5 truncate" title="<?= e($operasi['nama_operasi']) ?>"><?= e($operasi['nama_operasi']) ?></p>
            </div>
            <button type="button" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer shrink-0" onclick="closeModal('modal-view-wa-renops-<?= $renId ?>')" data-close-modal aria-label="Tutup modal" title="Tutup">
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="modal-body p-4 overflow-y-auto flex-1">
            <pre id="wa-renops-text-<?= $renId ?>" class="font-mono p-4 rounded-xl text-xs bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-800 leading-relaxed select-all whitespace-pre-wrap m-0"><?= e($renWa) ?></pre>
          </div>
          <div class="modal-footer flex items-center justify-between p-4 border-t border-slate-100 dark:border-slate-800">
            <span class="text-[11px] text-slate-400 font-mono">Format Baku FILE AKTIF</span>
            <div class="flex items-center gap-2">
              <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-view-wa-renops-<?= $renId ?>')" data-close-modal>Tutup</button>
              <button type="button" class="btn btn-primary btn-sm inline-flex items-center gap-1.5" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($renWa), ENT_QUOTES) ?>, "Naskah RENOPS WhatsApp Berhasil Disalin!")'>
                <span>Salin Naskah</span>
              </button>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
