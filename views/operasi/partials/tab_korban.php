<?php
/**
 * QR-SAR - Tab Data Korban & Kronologi Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */
?>
<div id="tab-content-korban" class="tab-pane space-y-4" style="display: <?= $activeTab === 'korban' ? 'block' : 'none' ?>;">

  <!-- Unified 5-Column POB Hairline Metric Bar -->
  <div class="opsd-hairline-grid rounded-2xl overflow-hidden grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-px">
    <div class="opsd-hairline-cell px-4 py-3.5">
      <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Total POB</div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="opsd-num text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-white leading-none"><?= (int)($statsKorban['total'] ?? $countKorban) ?></span>
        <span class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><?= (int)($statsKorban['identified'] ?? $countKorban) ?> terdata<?= !empty($statsKorban['unrecorded_dp']) ? ", {$statsKorban['unrecorded_dp']} belum" : '' ?></span>
      </div>
    </div>

    <div class="opsd-hairline-cell px-4 py-3.5">
      <div class="text-[11px] font-medium text-emerald-700 dark:text-emerald-400">Selamat (S)</div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="opsd-num text-xl sm:text-2xl font-bold font-mono text-emerald-600 dark:text-emerald-400 leading-none"><?= (int)($statsKorban['s'] ?? 0) ?></span>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">dievakuasi</span>
      </div>
    </div>

    <div class="opsd-hairline-cell px-4 py-3.5">
      <div class="text-[11px] font-medium text-rose-700 dark:text-rose-400">Meninggal (MD)</div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="opsd-num text-xl sm:text-2xl font-bold font-mono text-rose-600 dark:text-rose-400 leading-none"><?= (int)($statsKorban['md'] ?? 0) ?></span>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">ditemukan</span>
      </div>
    </div>

    <div class="opsd-hairline-cell px-4 py-3.5">
      <div class="text-[11px] font-medium text-amber-700 dark:text-amber-400">Pencarian (DP)</div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="opsd-num text-xl sm:text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 leading-none"><?= (int)($statsKorban['dp'] ?? 0) ?></span>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">target aktif</span>
      </div>
    </div>

    <div class="opsd-hairline-cell px-4 py-3.5 col-span-2 sm:col-span-1">
      <div class="text-[11px] font-medium text-slate-500 dark:text-slate-400">Hilang / Nihil (H)</div>
      <div class="mt-1 flex items-baseline gap-2">
        <span class="opsd-num text-xl sm:text-2xl font-bold font-mono text-slate-700 dark:text-slate-300 leading-none"><?= (int)($statsKorban['h'] ?? 0) ?></span>
        <span class="text-[11px] text-slate-500 dark:text-slate-400">operasi tutup</span>
      </div>
    </div>
  </div>

  <!-- Main Two-Column Content (Gapless 7/5 Split) -->
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
    <!-- Left: Victims Table (7 cols) -->
    <section class="lg:col-span-7 opsd-panel rounded-2xl overflow-hidden flex flex-col">
      <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex items-center justify-between gap-2">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Identitas Korban</h3>
        <span class="opsd-num font-mono text-xs font-semibold text-slate-500 dark:text-slate-400">
          <?= (int)($statsKorban['total'] ?? $countKorban) ?> Jiwa
        </span>
      </div>

      <?php if (empty($operasi['korban']) && empty($statsKorban['unrecorded_dp'])): ?>
        <div class="p-8 text-center text-slate-400 text-xs">
          Tidak ada data korban tercatat.
        </div>
      <?php else: ?>
        <div class="overflow-x-auto flex-1">
          <table class="w-full text-left border-collapse text-xs">
            <thead>
              <tr class="border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/40 text-slate-500 dark:text-slate-400 font-semibold text-[11px]">
                <th class="py-2.5 px-4 w-10 text-center">No</th>
                <th class="py-2.5 px-3.5">Nama Korban</th>
                <th class="py-2.5 px-3.5">Umur / JK</th>
                <th class="py-2.5 px-3.5">Domisili</th>
                <th class="py-2.5 px-4 text-center">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-slate-700 dark:text-slate-300">
              <?php foreach ($operasi['korban'] as $idx => $k):
                $st = $k['status_korban'];
                $stUpper = strtoupper((string)$st);
                if (in_array($stUpper, ['S', 'SELAMAT'], true)) {
                    $stClass = 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400';
                } elseif (in_array($stUpper, ['MD', 'MENINGGAL', 'MENINGGAL DUNIA'], true)) {
                    $stClass = 'bg-rose-500/10 text-rose-700 dark:text-rose-400';
                } elseif (in_array($stUpper, ['H', 'HILANG', 'NIHIL'], true)) {
                    $stClass = 'bg-slate-200/80 dark:bg-slate-800 text-slate-700 dark:text-slate-300';
                } else {
                    $stClass = 'bg-amber-500/10 text-amber-700 dark:text-amber-400';
                }
              ?>
              <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors">
                <td class="opsd-num py-2.5 px-4 text-center font-mono text-slate-400"><?= $idx + 1 ?></td>
                <td class="py-2.5 px-3.5 font-semibold text-slate-900 dark:text-white"><?= e($k['nama_korban']) ?></td>
                <td class="py-2.5 px-3.5 whitespace-nowrap"><?= e($k['umur'] ?: '-') ?> (<?= e($k['jk'] ?: '-') ?>)</td>
                <td class="py-2.5 px-3.5 text-slate-500 dark:text-slate-400"><?= e($k['alamat'] ?: '-') ?></td>
                <td class="py-2.5 px-4 text-center whitespace-nowrap">
                  <span class="inline-block px-2 py-0.5 rounded-md font-semibold font-mono text-[11px] whitespace-nowrap <?= $stClass ?>">
                    <?= e(TemplateHelper::getLabelStatusKorban($st)) ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>

              <?php if (!empty($statsKorban['unrecorded_dp'])): ?>
              <tr class="bg-amber-500/5 dark:bg-amber-500/10">
                <td class="opsd-num py-2.5 px-4 text-center font-mono text-amber-600 dark:text-amber-400">
                  <?= count($operasi['korban']) > 0 ? (count($operasi['korban']) + 1) : 1 ?>
                </td>
                <td class="py-2.5 px-3.5 font-semibold text-amber-800 dark:text-amber-300">
                  <?= (int)$statsKorban['unrecorded_dp'] ?> Orang Korban Belum Terdata (POB)
                </td>
                <td class="py-2.5 px-3.5 text-amber-700 dark:text-amber-400">-</td>
                <td class="py-2.5 px-3.5 text-amber-700 dark:text-amber-400">Identitas belum terverifikasi</td>
                <td class="py-2.5 px-4 text-center whitespace-nowrap">
                  <span class="inline-block px-2 py-0.5 rounded-md font-semibold font-mono text-[11px] whitespace-nowrap bg-amber-500/10 text-amber-700 dark:text-amber-400">
                    Dalam Pencarian (DP)
                  </span>
                </td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <!-- Right: Kronologi (Justify) & Pelapor (5 cols) -->
    <div class="lg:col-span-5 space-y-4">
      <section class="opsd-panel rounded-2xl overflow-hidden">
        <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex items-center justify-between gap-2">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Kronologi Kejadian</h3>
          <span class="opsd-num text-[11px] text-slate-500 dark:text-slate-400 font-mono"><?= $tglKejadian ?></span>
        </div>
        <div class="p-4 sm:p-5">
          <?php
            $kronRaw = trim(str_replace(["\r\n", "\r"], "\n", (string)($operasi['kronologi'] ?? '')));
            $kronLines = $kronRaw !== '' ? array_values(array_filter(array_map('trim', explode("\n", $kronRaw)), static fn($l) => $l !== '')) : [];
          ?>
          <?php if (empty($kronLines)): ?>
            <p class="text-xs text-slate-400">Kronologi belum diinput.</p>
          <?php else: ?>
            <div class="space-y-2.5 text-xs sm:text-[13px] leading-[1.7] text-slate-700 dark:text-slate-300 break-words">
              <?php foreach ($kronLines as $line): ?>
                <?php if (preg_match('/^[-*•]\s+(.+)$/u', $line, $m)): ?>
                  <div class="flex items-start gap-2.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400 dark:bg-slate-500 mt-[9px] shrink-0" aria-hidden="true"></span>
                    <p class="opsd-justify flex-1 min-w-0"><?= e($m[1]) ?></p>
                  </div>
                <?php elseif (preg_match('/^(\d+[\.\)])\s+(.+)$/u', $line, $m)): ?>
                  <div class="flex items-start gap-2">
                    <span class="opsd-num font-mono text-[11px] font-semibold text-slate-400 dark:text-slate-500 min-w-[1.25rem] pt-0.5 shrink-0"><?= e($m[1]) ?></span>
                    <p class="opsd-justify flex-1 min-w-0"><?= e($m[2]) ?></p>
                  </div>
                <?php else: ?>
                  <p class="opsd-justify"><?= e($line) ?></p>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="opsd-panel rounded-2xl overflow-hidden">
        <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Pelapor &amp; Kondisi Lapangan</h3>
        </div>

        <dl class="grid grid-cols-2 gap-px bg-slate-200/80 dark:bg-slate-800 text-xs">
          <div class="opsd-hairline-cell px-4 sm:px-5 py-3">
            <dt class="text-[11px] text-slate-500 dark:text-slate-400">Pelapor</dt>
            <dd class="font-semibold text-slate-900 dark:text-white mt-0.5 truncate"><?= e(($operasi['nama_pelapor'] ?? '') ?: '-') ?></dd>
            <dd class="text-[11px] text-slate-500 dark:text-slate-400 truncate"><?= e(($operasi['hubungan_pelapor'] ?? '') ?: '-') ?></dd>
          </div>
          <div class="opsd-hairline-cell px-4 sm:px-5 py-3">
            <dt class="text-[11px] text-slate-500 dark:text-slate-400">Kontak Pelapor</dt>
            <dd class="opsd-num font-semibold font-mono text-slate-900 dark:text-white mt-0.5 truncate"><?= e(($operasi['no_telp_pelapor'] ?? '') ?: '-') ?></dd>
          </div>
          <div class="opsd-hairline-cell px-4 sm:px-5 py-3">
            <dt class="text-[11px] text-slate-500 dark:text-slate-400">Cuaca</dt>
            <dd class="font-semibold text-slate-900 dark:text-white mt-0.5 truncate"><?= e(($operasi['cuaca'] ?? '') ?: '-') ?></dd>
          </div>
          <div class="opsd-hairline-cell px-4 sm:px-5 py-3">
            <dt class="text-[11px] text-slate-500 dark:text-slate-400">Gelombang</dt>
            <dd class="font-semibold text-slate-900 dark:text-white mt-0.5 truncate"><?= e(($operasi['gelombang'] ?? '') ?: '-') ?></dd>
          </div>
          <div class="opsd-hairline-cell px-4 sm:px-5 py-3">
            <dt class="text-[11px] text-slate-500 dark:text-slate-400">Angin</dt>
            <dd class="font-semibold text-slate-900 dark:text-white mt-0.5 truncate"><?= e(($operasi['angin'] ?? '') ?: '-') ?></dd>
          </div>
          <div class="opsd-hairline-cell px-4 sm:px-5 py-3">
            <dt class="text-[11px] text-slate-500 dark:text-slate-400">Faktor Penghambat</dt>
            <dd class="font-semibold text-slate-900 dark:text-white mt-0.5 truncate"><?= e(($operasi['faktor_penghambat'] ?? '') ?: 'Nihil') ?></dd>
          </div>
        </dl>
      </section>
    </div>
  </div>
</div>
