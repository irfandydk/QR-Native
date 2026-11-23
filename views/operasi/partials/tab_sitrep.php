<?php
/**
 * QR-SAR - Tab Laporan Perkembangan (LAPBANG)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */
?>
<div id="tab-content-sitrep" class="tab-pane space-y-3.5" style="display: <?= ($activeTab === 'sitrep' || $activeTab === 'laporan') ? 'block' : 'none' ?>;">

  <?php if (!$isOpen): ?>
    <div class="px-4 py-3 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
      <div class="text-xs">
        <strong class="font-bold">Operasi Ditutup (CLOSED).</strong>
        <span class="text-slate-600 dark:text-slate-300 ml-1">Penambahan LAPBANG dinonaktifkan. Laporan Penutupan tetap dapat dikoreksi langsung tanpa membuka operasi.</span>
      </div>
      <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs transition whitespace-nowrap cursor-pointer shrink-0" title="Khusus perpanjangan hari operasi SAR lanjutan (misal H7+1)">
        Perpanjang Operasi SAR
      </button>
    </div>
  <?php endif; ?>

  <?php if (empty($operasi['laporan_harian'])): ?>
    <div class="opsd-panel p-10 text-center rounded-2xl space-y-3">
      <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto border border-slate-200 dark:border-slate-700">
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      </div>
      <div class="max-w-sm mx-auto space-y-1">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Laporan Perkembangan (LAPBANG)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          Memuat evaluasi hasil pencarian harian tim gabungan dan debriefing lapangan.
        </p>
      </div>
      <div class="flex flex-wrap items-center justify-center gap-2 pt-1">
        <?php if ($isOpen): ?>
          <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-9 px-4 rounded-xl font-semibold text-xs transition no-underline">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            <span>Terbitkan LAPBANG <?= e($nextLapbang['hari_ke'] ?? $hariKe) ?></span>
          </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&kategori=OPERASI_SAR" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl font-semibold text-xs text-slate-700 dark:text-white transition no-underline" title="Buat Berita Radio dari Laporan Perkembangan">
          <span>BRA Operasi SAR</span>
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="flex flex-wrap items-center justify-between gap-2.5 no-print">
      <p class="text-xs font-medium text-slate-500 dark:text-slate-400">
        <span class="opsd-num font-mono font-semibold text-slate-800 dark:text-slate-200"><?= count($operasi['laporan_harian']) ?></span> laporan perkembangan tercatat
      </p>
      <div class="flex flex-wrap items-center gap-2">
        <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&kategori=OPERASI_SAR" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-3 rounded-lg font-semibold text-xs text-slate-700 dark:text-white transition no-underline" title="Buat Berita Radio dari Laporan Perkembangan">
          <span>BRA Operasi SAR</span>
        </a>
        <?php if ($isOpen): ?>
          <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg font-semibold text-xs transition no-underline">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
            <span>Terbitkan LAPBANG <?= e($nextLapbang['hari_ke'] ?? $hariKe) ?></span>
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Compact Hierarchy-Style Collapsible List -->
    <div class="relative pl-7 space-y-3 before:absolute before:left-[11px] before:top-4 before:bottom-4 before:w-px before:bg-slate-200 dark:before:bg-slate-800">
      <?php foreach ($operasi['laporan_harian'] as $idx => $lap):
        $isPenutupan = ($lap['jenis_laporan'] ?? '') === 'PENUTUPAN';
        $snapUnsur = !empty($lap['detail_unsur']) && is_array($lap['detail_unsur']) ? $lap['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
        $snapAlut = !empty($lap['detail_alut']) && is_array($lap['detail_alut']) ? $lap['detail_alut'] : ($operasi['detail_alut'] ?? []);
        $totalSnapPers = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $snapUnsur));
        $totalSnapAlut = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $snapAlut));

        $lapTgl = !empty($lap['waktu_laporan']) ? date('Y-m-d', strtotime($lap['waktu_laporan'])) : (!empty($lap['tanggal_laporan']) ? $lap['tanggal_laporan'] : date('Y-m-d'));
        $dynamicHariKeLap = TemplateHelper::hitungHariKe($operasi, $lapTgl, $lap['hari_ke'] ?? null);
        $lapForWa = $lap;
        $lapForWa['hari_ke'] = $dynamicHariKeLap;

        $lapWa = $lap['full_text_wa'] ?? '';
        if (empty($lapWa) || strpos($lapWa, 'FILE AKTIF') === false || (!$isPenutupan && strpos($lapWa, 'Data Korban:') !== false)) {
            $lapWa = TemplateHelper::generateLapbangWA($operasi, $lapForWa, $operasi['korban'] ?? [], $snapUnsur, $snapAlut, $isPenutupan);
        } elseif (!empty($lap['hari_ke']) && $lap['hari_ke'] !== $dynamicHariKeLap) {
            $lapWa = preg_replace('/\b(?:LAPORAN PERKEMBANGAN|LAPBANG OPS SAR)(?:\s+DAN\s+PENUTUPAN)?\s+H(?:\.?\d+(?:\s*\+\s*\d+)?)/i', ($isPenutupan ? "LAPBANG OPS SAR {$dynamicHariKeLap} DAN PENUTUPAN" : "LAPBANG OPS SAR {$dynamicHariKeLap}"), $lapWa, 1) ?? $lapWa;
        }
        $lapId = (int)($lap['id_laporan'] ?? ($lap['id_lapbang'] ?? 0));

        $isiRaw = trim(str_replace(["\r\n", "\r"], "\n", (string)($lap['isi_laporan'] ?? '')));
        $isiLines = $isiRaw !== '' ? array_values(array_filter(array_map('trim', explode("\n", $isiRaw)), static fn($l) => $l !== '')) : [];
        $summaryExcerpt = !empty($isiLines) ? preg_replace('/^([-*•]|\d+[\.\)])\s+/u', '', $isiLines[0]) : '';
        $nodeClass = $isPenutupan ? 'bg-rose-600 text-white' : 'bg-emerald-600 text-white';
      ?>
      <div class="relative">
        <div class="opsd-num absolute -left-7 top-4 w-6 h-6 rounded-full <?= $nodeClass ?> flex items-center justify-center font-mono font-bold text-[11px] ring-4 ring-[var(--bg-body)] z-10">
          <?= $idx + 1 ?>
        </div>

        <article id="card-sitrep-<?= $lapId ?>" class="opsd-panel opsd-row-card rounded-xl overflow-hidden <?= $isPenutupan ? 'border-rose-500/35 dark:border-rose-900/60' : '' ?>">
          <!-- Compact List Row Header -->
          <div class="px-4 py-3.5 flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
            <div class="space-y-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="px-2 py-0.5 rounded-md font-mono font-bold text-[11px] <?= $isPenutupan ? 'bg-rose-600 text-white' : 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' ?>">
                  <?= $isPenutupan ? 'PENUTUPAN' : 'LAPBANG' ?>
                </span>
                <span class="opsd-num font-mono text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                  <?= e($dynamicHariKeLap) ?>
                </span>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                  <?= TemplateHelper::formatTanggalIndo($lap['waktu_laporan']) ?>
                </h3>
                <span class="opsd-num font-mono text-[11px] text-slate-500 dark:text-slate-400">
                  <?= TemplateHelper::formatJamWITA($lap['waktu_laporan']) ?> WITA
                </span>
              </div>

              <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-0.5 min-w-0">
                <?php if ($summaryExcerpt !== ''): ?>
                  <span class="truncate max-w-xl"><?= e(mb_strimwidth($summaryExcerpt, 0, 100, '...')) ?></span>
                <?php endif; ?>
                <span class="opsd-num font-mono text-[11px] shrink-0"><?= $totalSnapPers ?> Personel, <?= $totalSnapAlut ?> Alut</span>
              </div>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap shrink-0 no-print">
              <button type="button" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-sitrep-<?= $lapId ?>')" title="Lihat pratinjau format WhatsApp LAPBANG">
                <span>Naskah WA</span>
              </button>

              <?php if ($isOpen || $isPenutupan): ?>
                <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>&id_laporan=<?= $lap['id_laporan'] ?>" class="opsd-ghost-btn inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline" title="<?= $isPenutupan && !$isOpen ? 'Koreksi data Laporan Penutupan Operasi SAR' : 'Edit Laporan Perkembangan' ?>">
                  <span>Edit</span>
                </a>
              <?php endif; ?>

              <a href="<?= BASE_URL ?>/bra.php?action=create&id_operasi=<?= $id ?>&from=sitrep&id_laporan=<?= $lap['id_laporan'] ?>" class="opsd-accent-soft inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline" title="Buat Berita Radio dari laporan ini">
                <span>BRA LAPBANG</span>
              </a>

              <?php if ($isOpen && AuthHelper::canDelete()): ?>
                <button type="button" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/40 transition cursor-pointer" onclick="deleteLaporan(<?= (int)$lap['id_laporan'] ?>)" aria-label="Hapus laporan perkembangan" title="Hapus Laporan Perkembangan">
                  <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                </button>
              <?php endif; ?>

              <button
                type="button"
                aria-expanded="false"
                aria-controls="sitrep-detail-<?= $lapId ?>"
                onclick="toggleDetailRollout('sitrep-detail-<?= $lapId ?>', this)"
                class="opsd-ghost-btn inline-flex items-center gap-1 h-8 px-2.5 rounded-lg font-semibold text-xs transition cursor-pointer"
                title="Tampilkan atau sembunyikan detail LAPBANG"
              >
                <span class="rollout-label">Detail</span>
                <svg class="rollout-chevron w-3.5 h-3.5 text-slate-400 transition-transform duration-200" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
              </button>
            </div>
          </div>

          <!-- Collapsible Roll-Out Detail Body -->
          <div id="sitrep-detail-<?= $lapId ?>" class="hidden border-t border-slate-200/80 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-950/25 p-4 sm:p-5 space-y-4">
            <div class="space-y-2">
              <div class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">Hasil Pencarian &amp; Tindakan Lapangan</div>
              <?php if (empty($isiLines)): ?>
                <p class="text-xs text-slate-400">Belum ada catatan hasil pencarian.</p>
              <?php else: ?>
                <div class="space-y-2 text-xs sm:text-[13px] leading-[1.68] text-slate-800 dark:text-slate-200 break-words">
                  <?php foreach ($isiLines as $line): ?>
                    <?php if (preg_match('/^[-*•]\s+(.+)$/u', $line, $m)): ?>
                      <div class="flex items-start gap-2.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400 dark:bg-slate-500 mt-[9px] shrink-0" aria-hidden="true"></span>
                        <p class="flex-1 min-w-0"><?= e($m[1]) ?></p>
                      </div>
                    <?php elseif (preg_match('/^(\d+[\.\)])\s+(.+)$/u', $line, $m)): ?>
                      <div class="flex items-start gap-2">
                        <span class="opsd-num font-mono text-[11px] font-semibold text-slate-400 dark:text-slate-500 min-w-[1.25rem] pt-0.5 shrink-0"><?= e($m[1]) ?></span>
                        <p class="flex-1 min-w-0"><?= e($m[2]) ?></p>
                      </div>
                    <?php else: ?>
                      <p><?= e($line) ?></p>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <?php if (!empty($lap['waktu_debrief'])): ?>
                <?php if ($isPenutupan): ?>
                  <div class="mt-2.5 pl-3 py-1.5 border-l-2 border-rose-500 text-rose-700 dark:text-rose-400 font-semibold text-xs">
                    Debriefing Resmi Penutupan: <?= TemplateHelper::formatTanggalIndo($lap['waktu_debrief']) ?>, <?= TemplateHelper::formatJamWITA($lap['waktu_debrief']) ?> WITA
                  </div>
                <?php else: ?>
                  <div class="mt-2.5 pl-3 py-1.5 border-l-2 border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-medium text-xs">
                    Penutupan Sementara: <?= TemplateHelper::formatTanggalIndo($lap['waktu_debrief']) ?>, <?= TemplateHelper::formatJamWITA($lap['waktu_debrief']) ?> WITA
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <div class="pt-3.5 border-t border-slate-200/75 dark:border-slate-800 space-y-2.5">
              <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <span class="font-bold text-slate-700 dark:text-slate-300">Snapshot Potensi &amp; Armada (<?= e($dynamicHariKeLap) ?>)</span>
                <span class="opsd-num font-mono text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                  <?= $totalSnapPers ?> Personel, <?= $totalSnapAlut ?> Unit Alut
                </span>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                  <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400 mb-1">Potensi SAR Gabungan</div>
                  <?php if (empty($snapUnsur)): ?>
                    <span class="text-slate-400 text-xs">Tidak ada catatan unsur.</span>
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
                    <span class="text-slate-400 text-xs">Tidak ada catatan alut.</span>
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

      <!-- MODAL: VIEW WA LAPBANG -->
      <div id="modal-view-wa-sitrep-<?= $lapId ?>" class="modal-overlay">
        <div class="modal-container" style="max-width: 760px; width: 95%;">
          <div class="modal-header flex items-center justify-between border-b border-slate-100 dark:border-slate-800 p-4">
            <div class="min-w-0 pr-3">
              <h3 class="font-bold text-sm text-slate-900 dark:text-white">
                Naskah <?= $isPenutupan ? 'Penutupan Operasi' : 'LAPBANG (' . e($dynamicHariKeLap) . ')' ?>
              </h3>
              <p class="text-[11px] opsd-accent-text font-medium mt-0.5 truncate" title="<?= e($operasi['nama_operasi']) ?>"><?= e($operasi['nama_operasi']) ?></p>
            </div>
            <button type="button" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer shrink-0" onclick="closeModal('modal-view-wa-sitrep-<?= $lapId ?>')" data-close-modal aria-label="Tutup modal" title="Tutup">
              <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
          <div class="modal-body p-4 overflow-y-auto flex-1">
            <pre id="wa-sitrep-text-<?= $lapId ?>" class="font-mono p-4 rounded-xl text-xs bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-800 leading-relaxed select-all whitespace-pre-wrap m-0"><?= e($lapWa) ?></pre>
          </div>
          <div class="modal-footer flex items-center justify-between p-4 border-t border-slate-100 dark:border-slate-800">
            <span class="text-[11px] text-slate-400 font-mono">Format Baku FILE AKTIF</span>
            <div class="flex items-center gap-2">
              <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-view-wa-sitrep-<?= $lapId ?>')" data-close-modal>Tutup</button>
              <button type="button" class="btn btn-primary btn-sm inline-flex items-center gap-1.5" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($lapWa), ENT_QUOTES) ?>, "Naskah LAPBANG WhatsApp Berhasil Disalin!")'>
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
