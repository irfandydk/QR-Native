<?php
/**
 * QR-SAR - Arsip Berita Radio SAR (BRA SAR)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Tata letak tunggal tanpa sub-header duplikat, selaras dengan Operasi SAR
 * dan Detail Operasi SAR, serta adaptif penuh terhadap seluruh tema admin.
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$kategoriFilter = strtoupper($_GET['kategori'] ?? 'SEMUA');
if ($kategoriFilter !== 'SEMUA') {
    $kategoriFilter = BraHelper::normalizeCategory($kategoriFilter);
}
$searchQuery = trim($_GET['q'] ?? '');

// Ringkasan jumlah per kategori dalam 1 kueri agregasi
$braStats = BraModel::getStats();
$countAll = (int)($braStats['total'] ?? 0);
$countMapel = (int)($braStats['mapel'] ?? 0);
$countOperasiSar = (int)($braStats['operasi_sar'] ?? $braStats['sar_umum'] ?? 0);
$countKapal = (int)($braStats['kapal'] ?? 0);

// Daftar BRA tersaring tanpa N+1 section query
$katParam = ($kategoriFilter !== 'SEMUA') ? $kategoriFilter : null;
$braList = BraModel::getAll($katParam, $searchQuery !== '' ? $searchQuery : null, false);
$totalFiltered = count($braList);
?>

<style>
  .bra-accent-btn { background-color: var(--primary); color: #fff !important; border: 1px solid transparent; }
  .bra-accent-btn:hover { background-color: var(--primary-hover); color: #fff !important; }
  .bra-accent-soft { background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--m-accent-text, var(--primary)) !important; border: 1px solid color-mix(in srgb, var(--primary) 26%, transparent); }
  .bra-accent-soft:hover { background-color: var(--primary); color: #fff !important; border-color: transparent; }
  .bra-accent-text { color: var(--m-accent-text, var(--primary)); }
  .bra-ghost-btn { background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
  .bra-ghost-btn:hover { background-color: var(--bg-card-subtle); }
  .bra-metric-item { position: relative; display: block; border-radius: 0.5rem; transition: color 160ms ease; }
  .bra-metric-item[data-active="true"] .bra-metric-label,
  .bra-metric-item[data-active="true"] .bra-metric-val { color: var(--m-accent-text, var(--primary)); }
  .bra-metric-item[data-active="true"]::after { content: ""; position: absolute; left: 0; right: 0; bottom: -14px; height: 2px; background-color: var(--primary); border-radius: 2px; }
  .bra-accent-btn:focus-visible, .bra-accent-soft:focus-visible, .bra-ghost-btn:focus-visible, .bra-metric-item:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
  @media (prefers-reduced-motion: reduce) { .bra-accent-btn, .bra-accent-soft, .bra-ghost-btn, .bra-metric-item { transition: none !important; } }
</style>

<div class="max-w-7xl mx-auto space-y-4">
  <h1 class="sr-only">Arsip Berita Radio SAR (BRA SAR)</h1>

  <!-- ======================================================== -->
  <!-- 1. COMPACT 4-METRIC INTERACTIVE CATEGORY RAIL            -->
  <!-- ======================================================== -->
  <section aria-label="Ringkasan kategori dokumen BRA SAR" class="pb-3.5 border-b border-slate-200 dark:border-slate-800">
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-y-3 gap-x-5 lg:gap-x-0 lg:divide-x lg:divide-slate-200 dark:lg:divide-slate-800">

      <div class="lg:pr-5">
        <a href="<?= BASE_URL ?>/bra.php?kategori=SEMUA<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
           data-active="<?= $kategoriFilter === 'SEMUA' ? 'true' : 'false' ?>"
           class="bra-metric-item no-underline">
          <div class="bra-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">Semua Telegram</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span class="bra-metric-val text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countAll ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">dokumen</span>
          </div>
        </a>
      </div>

      <div class="lg:px-5">
        <a href="<?= BASE_URL ?>/bra.php?kategori=MAPEL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
           data-active="<?= $kategoriFilter === 'MAPEL' ? 'true' : 'false' ?>"
           class="bra-metric-item no-underline">
          <div class="bra-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">BRA Mapel</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span class="bra-metric-val text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countMapel ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">dokumen</span>
          </div>
        </a>
      </div>

      <div class="lg:px-5">
        <a href="<?= BASE_URL ?>/bra.php?kategori=OPERASI_SAR<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
           data-active="<?= $kategoriFilter === 'OPERASI_SAR' ? 'true' : 'false' ?>"
           class="bra-metric-item no-underline">
          <div class="bra-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">BRA Ops SAR</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span class="bra-metric-val text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countOperasiSar ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">dokumen</span>
          </div>
        </a>
      </div>

      <div class="lg:pl-5">
        <a href="<?= BASE_URL ?>/bra.php?kategori=PERGERAKAN_KAPAL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
           data-active="<?= $kategoriFilter === 'PERGERAKAN_KAPAL' ? 'true' : 'false' ?>"
           class="bra-metric-item no-underline">
          <div class="bra-metric-label text-[11px] font-medium text-slate-500 dark:text-slate-400">BRA Kapal</div>
          <div class="mt-0.5 flex items-baseline gap-2">
            <span class="bra-metric-val text-xl sm:text-2xl font-bold font-mono tracking-tight text-slate-900 dark:text-white leading-none"><?= $countKapal ?></span>
            <span class="text-[11px] text-slate-500 dark:text-slate-400">dokumen</span>
          </div>
        </a>
      </div>

    </div>
  </section>

  <!-- ======================================================== -->
  <!-- 2. UNIFIED COMMAND & SEARCH TOOLBAR                      -->
  <!-- ======================================================== -->
  <section aria-label="Pencarian dan aksi pembuatan BRA" class="space-y-2">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
      <!-- Left: Search Input -->
      <div class="flex items-center gap-2.5 flex-1 min-w-0">
        <div class="relative flex-1 min-w-0 lg:max-w-sm">
          <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
          <input type="text"
                 id="bra-search-input"
                 name="q"
                 value="<?= htmlspecialchars($searchQuery) ?>"
                 placeholder="Cari nomor BRA, perihal, SMC..."
                 aria-label="Cari nomor BRA, perihal, atau SMC"
                 class="control control-sm w-full placeholder:text-slate-400 dark:placeholder:text-slate-500 pl-9 pr-8 text-xs" />
          <button type="button"
                  id="btn-clear-bra-search"
                  onclick="clearBraSearch()"
                  aria-label="Hapus pencarian"
                  class="<?= empty($searchQuery) ? 'hidden' : '' ?> absolute right-2 top-1/2 -translate-y-1/2 p-1 rounded-md text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 cursor-pointer"
                  title="Hapus Pencarian">
            <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <span class="hidden xl:inline text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
          <strong class="font-mono text-slate-800 dark:text-slate-200"><?= $totalFiltered ?></strong> dari <?= $countAll ?> arsip
        </span>
      </div>

      <!-- Right: Create Actions -->
      <div class="flex flex-wrap items-center gap-2 shrink-0">
        <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=OPERASI_SAR"
           class="bra-accent-btn inline-flex items-center gap-1.5 h-9 px-3.5 rounded-xl text-xs font-bold transition whitespace-nowrap no-underline">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          <span>BRA Ops SAR</span>
        </a>

        <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=MAPEL"
           class="bra-ghost-btn inline-flex items-center gap-1.5 h-9 px-3 rounded-xl text-xs font-semibold transition whitespace-nowrap no-underline">
          <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          <span>BRA Mapel</span>
        </a>

        <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=PERGERAKAN_KAPAL"
           class="bra-ghost-btn inline-flex items-center gap-1.5 h-9 px-3 rounded-xl text-xs font-semibold transition whitespace-nowrap no-underline">
          <svg class="w-3.5 h-3.5 text-slate-400" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          <span>BRA Kapal</span>
        </a>
      </div>
    </div>

    <!-- Subtle Context / Filter Status Row -->
    <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-500 dark:text-slate-400">
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
        <span>Menampilkan <strong class="font-mono text-slate-800 dark:text-slate-200"><?= $totalFiltered ?></strong> dari <?= $countAll ?> dokumen BRA SAR</span>
        <?php if ($kategoriFilter !== 'SEMUA'): ?>
          <span>Kategori: <strong class="bra-accent-text"><?= e($kategoriFilter) ?></strong></span>
          <a href="<?= BASE_URL ?>/bra.php" class="bra-accent-text font-semibold hover:underline">Reset</a>
        <?php endif; ?>
        <?php if ($searchQuery): ?>
          <span>Kata kunci: "<strong class="text-slate-800 dark:text-slate-200"><?= e($searchQuery) ?></strong>"</span>
        <?php endif; ?>
      </div>
      <a href="<?= BASE_URL ?>/index.php" class="text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white font-medium transition-colors">
        &larr; Dashboard
      </a>
    </div>
  </section>

  <!-- ======================================================== -->
  <!-- 3. ARCHIVE TABLE OR EMPTY STATE                          -->
  <!-- ======================================================== -->
  <?php if (empty($braList)): ?>
    <div class="bento-card-v1 p-10 sm:p-12 text-center rounded-2xl space-y-3">
      <div class="w-11 h-11 mx-auto rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center border border-slate-200 dark:border-slate-700">
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
      </div>
      <div class="max-w-sm mx-auto space-y-1">
        <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Tidak Ada Naskah BRA Ditemukan</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          <?= $searchQuery ? 'Tidak ada hasil untuk pencarian "' . htmlspecialchars($searchQuery) . '".' : 'Belum ada dokumen Berita Radio SAR yang tersimpan pada kategori ini.' ?>
        </p>
      </div>
      <div class="pt-1">
        <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=<?= $kategoriFilter === 'SEMUA' ? 'OPERASI_SAR' : $kategoriFilter ?>" class="bra-accent-btn inline-flex items-center gap-1.5 h-9 px-4 text-xs font-bold rounded-xl transition no-underline">
          <span>Buat Naskah BRA Baru</span>
        </a>
      </div>
    </div>
  <?php else: ?>
    <div id="bra-container-list" class="bento-card-v1 rounded-2xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-[11px] font-semibold text-slate-500 dark:text-slate-400">
              <th class="px-4 py-3" style="width: 26%;">No BRA SAR</th>
              <th class="px-4 py-3" style="width: 16%;">Kategori</th>
              <th class="px-4 py-3">Perihal</th>
              <th class="px-4 py-3 text-right" style="width: 16%;">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-xs">
            <?php foreach ($braList as $bra): ?>
              <?php
                $kategori = strtoupper($bra['kategori'] ?? 'MAPEL');
                $badgeClass = match($kategori) {
                    'MAPEL' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300',
                    'PERGERAKAN_KAPAL' => 'bg-sky-500/10 text-sky-700 dark:text-sky-300',
                    default => 'bra-accent-text bg-slate-100 dark:bg-slate-800',
                };
                $kategoriLabel = match($kategori) {
                    'MAPEL' => 'BRA MAPEL',
                    'PERGERAKAN_KAPAL' => 'BRA KAPAL',
                    default => 'BRA OPS SAR',
                };
                $searchDataStr = strtolower(($bra['no_bra'] ?? '') . ' ' . ($bra['perihal'] ?? '') . ' ' . ($bra['nama_smc'] ?? '') . ' ' . ($bra['pengirim'] ?? '') . ' ' . ($bra['nama_operasi'] ?? ''));
              ?>
              <tr class="bra-item-row hover:bg-slate-50/70 dark:hover:bg-slate-800/30 transition-colors" data-search="<?= htmlspecialchars($searchDataStr) ?>">
                <!-- 1. No BRA SAR -->
                <td class="px-4 py-3 align-middle whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5 font-mono font-bold bra-accent-text">
                    <span><?= htmlspecialchars($bra['no_bra'] ?? '-') ?></span>
                    <button type="button"
                            data-no-bra="<?= e($bra['no_bra'] ?? '') ?>"
                            onclick="copyBraNo(this.dataset.noBra, this)"
                            aria-label="Salin nomor dokumen"
                            title="Salin Nomor Dokumen"
                            class="p-1 rounded text-slate-400 hover:text-slate-700 dark:hover:text-slate-200 transition cursor-pointer shrink-0">
                      <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="13" height="13" x="9" y="9" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </button>
                  </div>
                </td>

                <!-- 2. Kategori -->
                <td class="px-4 py-3 align-middle whitespace-nowrap">
                  <span class="inline-flex items-center px-2 py-0.5 text-[11px] font-bold rounded-md font-mono <?= $badgeClass ?>">
                    <?= $kategoriLabel ?>
                  </span>
                </td>

                <!-- 3. Perihal -->
                <td class="px-4 py-3 align-middle">
                  <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>" class="font-semibold text-slate-900 dark:text-white hover:underline block leading-snug">
                    <?= htmlspecialchars($bra['perihal'] ?? 'Naskah Berita Radio SAR') ?>
                  </a>
                </td>

                <!-- 4. Aksi Cepat -->
                <td class="px-4 py-3 align-middle text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5">
                    <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>"
                       class="bra-accent-soft inline-flex items-center h-8 px-2.5 rounded-lg font-semibold text-xs transition no-underline"
                       title="Lihat Dokumen">
                      Lihat
                    </a>

                    <a href="<?= BASE_URL ?>/bra.php?action=edit&id=<?= $bra['id_bra'] ?>"
                       class="bra-ghost-btn inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition"
                       aria-label="Edit naskah BRA"
                       title="Koreksi / Edit Naskah">
                      <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                    </a>

                    <?php if (AuthHelper::canDelete()): ?>
                      <form method="POST" action="<?= BASE_URL ?>/bra.php?action=delete" class="inline-flex" data-confirm="Apakah Anda yakin ingin menghapus naskah Berita Radio SAR <?= htmlspecialchars($bra['no_bra']) ?>? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus Dokumen BRA SAR" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$bra['id_bra'] ?>">
                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/40 hover:border-rose-200 dark:hover:border-rose-900 transition cursor-pointer" aria-label="Hapus dokumen BRA" title="Hapus Dokumen">
                          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
function copyBraNo(noBra, btnEl) {
  if (!navigator.clipboard) {
    const tempInput = document.createElement('input');
    tempInput.value = noBra;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    if (typeof showToast === 'function') {
      showToast(`Nomor dokumen ${noBra} disalin!`, 'success');
    }
    return;
  }
  navigator.clipboard.writeText(noBra).then(() => {
    if (typeof showToast === 'function') {
      showToast(`Nomor dokumen ${noBra} disalin!`, 'success');
    }
  });
}

function filterBraLive(query) {
  const q = (query || '').toLowerCase().trim();
  const rows = document.querySelectorAll('.bra-item-row');
  const clearBtn = document.getElementById('btn-clear-bra-search');

  if (clearBtn) {
    if (q.length > 0) {
      clearBtn.classList.remove('hidden');
    } else {
      clearBtn.classList.add('hidden');
    }
  }

  rows.forEach(row => {
    const text = row.getAttribute('data-search') || row.textContent.toLowerCase();
    row.style.display = (!q || text.includes(q)) ? '' : 'none';
  });
}

function clearBraSearch() {
  const input = document.getElementById('bra-search-input');
  if (input) {
    input.value = '';
    filterBraLive('');
    input.focus();
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('bra-search-input');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      filterBraLive(e.target.value);
    });
    if (searchInput.value.trim().length > 0) {
      filterBraLive(searchInput.value.trim());
    }
  }
});
</script>
