<?php
/**
 * QR-SAR - Arsip Berita Radio SAR (BRA SAR)
 * Kantor Pencarian dan Pertolongan (KPP) Kendari - Basarnas
 * 
 * Desain Taktis Sederhana (List View Baku Basarnas):
 * - 4 Kartu Statistik Ringkasan Interaktif (Semua, MAPEL, SAR UMUM, KAPAL)
 * - Pencarian Real-Time di Samping Tombol Tambah BRA SAR
 * - Tabel Data Sederhana & Ringkas: 1. No BRA SAR, 2. Kategori, 3. Perihal (+ Aksi)
 * - Full Dark/Light Mode & Palette Compatibility
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

// Counts for Summary Cards via single aggregated query
$braStats = BraModel::getStats();
$countAll = $braStats['total'];
$countMapel = $braStats['mapel'];
$countOperasiSar = $braStats['operasi_sar'] ?? $braStats['sar_umum'];
$countKapal = $braStats['kapal'];

// Fetch filtered list directly from database without N+1 section queries
$katParam = ($kategoriFilter !== 'SEMUA') ? $kategoriFilter : null;
$braList = BraModel::getAll($katParam, $searchQuery !== '' ? $searchQuery : null, false);
$totalFiltered = count($braList);
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-4">

  <!-- ======================================================== -->
  <!-- 1. TOP HEADER & COMMAND ACTIONS WITH SEARCH -->
  <!-- ======================================================== -->
  <div class="bento-card-v1 p-4 sm:p-5 rounded-2xl shadow-sm transition-colors bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3.5">
      <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/index.php"
           class="btn-tactile p-2 rounded-xl border transition-colors border-slate-200 dark:border-slate-700 bg-slate-100 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 shrink-0"
           title="Kembali ke Dashboard">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        </a>
        <div>
          <div class="flex flex-wrap items-center gap-2">
            <div class="w-7 h-7 rounded-lg border flex items-center justify-center bg-orange-500/10 dark:bg-orange-500/20 text-orange-600 dark:text-orange-400 border-orange-500/30">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
            </div>
            <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 dark:text-white">
              Arsip Berita Radio SAR (BRA SAR)
            </h1>
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
              SENKOM 304
            </span>
          </div>
          <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 leading-normal">
            Pusat data arsip telegram radio resmi Kantor Pencarian dan Pertolongan (KPP) Kendari
          </p>
        </div>
      </div>

      <!-- Search Box & Quick Action Buttons -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full lg:w-auto">
        <!-- Live Search Input -->
        <div class="relative w-full sm:w-60 md:w-64">
          <svg class="w-3.5 h-3.5 absolute left-3 top-2.5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text"
                 id="bra-search-input"
                 name="q"
                 value="<?= htmlspecialchars($searchQuery) ?>"
                 placeholder="Cari No BRA, Perihal..."
                 class="control control-sm w-full placeholder:text-slate-400 dark:placeholder:text-slate-500 pl-9 pr-8 text-xs" />
          <button type="button"
                  id="btn-clear-bra-search"
                  onclick="clearBraSearch()"
                  class="<?= empty($searchQuery) ? 'hidden' : '' ?> absolute right-2.5 top-2.5 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                  title="Hapus Pencarian">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>

        <!-- Quick Action Buttons -->
        <div class="flex items-center gap-1.5 shrink-0 flex-wrap">
          <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=MAPEL"
             class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-600 hover:bg-orange-500 text-white rounded-xl text-xs font-bold shadow-xs transition-all">
            <span>+ BRA Mapel</span>
          </a>

          <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=OPERASI_SAR"
             class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-600 hover:bg-amber-500 text-white rounded-xl text-xs font-bold shadow-xs transition-all">
            <span>+ BRA SAR</span>
          </a>

          <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=PERGERAKAN_KAPAL"
             class="btn-tactile inline-flex items-center gap-1.5 px-3 py-1.5 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold shadow-xs transition-all">
            <span>+ BRA Kapal</span>
          </a>
        </div>
      </div>
    </div>

    <!-- 4 Stat Summary Cards (Interactive Filters) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 mt-3.5 pt-3.5 border-t border-slate-200/70 dark:border-slate-800/70">
      <!-- Card 1: Semua -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=SEMUA<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-2.5 sm:p-3 rounded-xl border cursor-pointer transition-all btn-tactile <?= $kategoriFilter === 'SEMUA'
           ? 'bg-orange-500/10 dark:bg-slate-800 border-orange-400 dark:border-orange-500 ring-2 ring-orange-400/30 dark:ring-orange-500/40 shadow-xs'
           : 'bg-slate-50/70 dark:bg-slate-950/60 border-slate-200/80 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-[11px] font-bold uppercase tracking-wider font-mono">
          <span>Semua Telegram</span>
        </div>
        <div class="mt-1 flex items-baseline gap-1.5">
          <span class="text-xl sm:text-2xl font-bold font-mono text-slate-900 dark:text-white leading-none">
            <?= $countAll ?>
          </span>
          <span class="text-[11px] text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>

      <!-- Card 2: MAPEL -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=MAPEL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-2.5 sm:p-3 rounded-xl border cursor-pointer transition-all btn-tactile <?= $kategoriFilter === 'MAPEL'
           ? 'bg-orange-500/10 dark:bg-slate-800 border-orange-400 dark:border-orange-500 ring-2 ring-orange-400/30 dark:ring-orange-500/40 shadow-xs'
           : 'bg-slate-50/70 dark:bg-slate-950/60 border-slate-200/80 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-[11px] font-bold uppercase tracking-wider font-mono">
          <span>BRA Mapel</span>
        </div>
        <div class="mt-1 flex items-baseline gap-1.5">
          <span class="text-xl sm:text-2xl font-bold font-mono text-orange-600 dark:text-orange-400 leading-none">
            <?= $countMapel ?>
          </span>
          <span class="text-[11px] text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>

      <!-- Card 3: OPERASI SAR -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=OPERASI_SAR<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-2.5 sm:p-3 rounded-xl border cursor-pointer transition-all btn-tactile <?= $kategoriFilter === 'OPERASI_SAR'
           ? 'bg-amber-500/10 dark:bg-slate-800 border-amber-400 dark:border-amber-500 ring-2 ring-amber-400/30 dark:ring-amber-500/40 shadow-xs'
           : 'bg-slate-50/70 dark:bg-slate-950/60 border-slate-200/80 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-[11px] font-bold uppercase tracking-wider font-mono">
          <span>BRA Ops SAR</span>
        </div>
        <div class="mt-1 flex items-baseline gap-1.5">
          <span class="text-xl sm:text-2xl font-bold font-mono text-amber-600 dark:text-amber-400 leading-none">
            <?= $countOperasiSar ?>
          </span>
          <span class="text-[11px] text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>

      <!-- Card 4: KAPAL -->
      <a href="<?= BASE_URL ?>/bra.php?kategori=PERGERAKAN_KAPAL<?= $searchQuery ? '&q=' . urlencode($searchQuery) : '' ?>"
         class="p-2.5 sm:p-3 rounded-xl border cursor-pointer transition-all btn-tactile <?= $kategoriFilter === 'PERGERAKAN_KAPAL'
           ? 'bg-cyan-500/10 dark:bg-slate-800 border-cyan-400 dark:border-cyan-500 ring-2 ring-cyan-400/30 dark:ring-cyan-500/40 shadow-xs'
           : 'bg-slate-50/70 dark:bg-slate-950/60 border-slate-200/80 dark:border-slate-800 hover:bg-slate-100 dark:hover:bg-slate-800/80' ?>">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 text-[11px] font-bold uppercase tracking-wider font-mono">
          <span>BRA Kapal</span>
        </div>
        <div class="mt-1 flex items-baseline gap-1.5">
          <span class="text-xl sm:text-2xl font-bold font-mono text-cyan-600 dark:text-cyan-400 leading-none">
            <?= $countKapal ?>
          </span>
          <span class="text-[11px] text-slate-400 font-medium">Dokumen</span>
        </div>
      </a>
    </div>
  </div>

  <!-- Filter Active Info Row -->
  <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 px-1">
    <div>
      Menampilkan <strong class="text-slate-800 dark:text-slate-200 font-mono"><?= $totalFiltered ?></strong> dari <?= $countAll ?> arsip naskah BRA SAR
      <?php if ($kategoriFilter !== 'SEMUA'): ?>
        • Kategori: <span class="font-bold text-orange-600 dark:text-orange-400"><?= e($kategoriFilter) ?></span>
      <?php endif; ?>
      <?php if ($searchQuery): ?>
        • Kata Kunci: "<span class="font-bold text-slate-800 dark:text-slate-200"><?= e($searchQuery) ?></span>"
      <?php endif; ?>
    </div>
  </div>

  <!-- ======================================================== -->
  <!-- 2. MAIN DOCUMENTS CONTAINER (LIST / TABLE VIEW) -->
  <!-- ======================================================== -->
  <?php if (empty($braList)): ?>
    <div class="bento-card-v1 p-10 text-center rounded-2xl border border-dashed border-slate-300 dark:border-slate-800 bg-white dark:bg-slate-900">
      <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-orange-500/10 flex items-center justify-center text-orange-500 text-2xl">
        📡
      </div>
      <h3 class="text-sm font-bold text-slate-900 dark:text-slate-100 mb-1">Tidak Ada Naskah BRA Ditemukan</h3>
      <p class="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto mb-4 leading-relaxed">
        <?= $searchQuery ? 'Tidak ada hasil untuk pencarian "' . htmlspecialchars($searchQuery) . '".' : 'Belum ada dokumen Berita Radio SAR yang tersimpan pada kategori ini.' ?>
      </p>
      <div class="flex items-center justify-center gap-2">
        <a href="<?= BASE_URL ?>/bra.php?action=create&kategori=<?= $kategoriFilter === 'SEMUA' ? 'MAPEL' : $kategoriFilter ?>" class="btn-tactile inline-flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-orange-600 hover:bg-orange-500 rounded-xl shadow-xs transition">
          + Buat Naskah BRA Baru
        </a>
      </div>
    </div>
  <?php else: ?>

    <!-- STRUCTURED TACTICAL LIST / TABLE -->
    <div id="bra-container-list" class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/80 dark:bg-slate-800/40 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              <th class="px-5 py-3.5" style="width: 28%;">No BRA SAR</th>
              <th class="px-4 py-3.5" style="width: 20%;">Kategori</th>
              <th class="px-4 py-3.5">Perihal</th>
              <th class="px-5 py-3.5 text-right" style="width: 14%;">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80 text-xs">
            <?php foreach ($braList as $bra): ?>
              <?php
                $kategori = strtoupper($bra['kategori'] ?? 'MAPEL');
                $badgeClass = match($kategori) {
                    'MAPEL' => 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30',
                    'PERGERAKAN_KAPAL' => 'bg-cyan-500/15 text-cyan-600 dark:text-cyan-300 border-cyan-500/30',
                    default => 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
                };
                $kategoriIcon = match($kategori) {
                    'MAPEL' => '📡',
                    'PERGERAKAN_KAPAL' => '🚢',
                    default => '📋',
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
                <td class="px-5 py-3.5 align-middle">
                  <div class="flex items-center gap-1.5 font-mono font-bold text-orange-600 dark:text-orange-400">
                    <span class="truncate"><?= htmlspecialchars($bra['no_bra'] ?? '-') ?></span>
                    <button type="button"
                            onclick="copyBraNo('<?= htmlspecialchars($bra['no_bra'] ?? '') ?>', this)"
                            title="Salin Nomor Dokumen"
                            class="p-1 rounded text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer shrink-0">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    </button>
                  </div>
                </td>

                <!-- 2. Kategori -->
                <td class="px-4 py-3.5 align-middle">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-bold rounded-lg border font-mono <?= $badgeClass ?>">
                    <span><?= $kategoriIcon ?></span>
                    <span><?= $kategoriLabel ?></span>
                  </span>
                </td>

                <!-- 3. Perihal -->
                <td class="px-4 py-3.5 align-middle">
                  <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>" class="font-bold text-slate-900 dark:text-white hover:text-orange-600 dark:hover:text-orange-400 transition block leading-snug">
                    <?= htmlspecialchars($bra['perihal'] ?? 'Naskah Berita Radio SAR') ?>
                  </a>
                </td>

                <!-- Aksi Cepat -->
                <td class="px-5 py-3.5 align-middle text-right whitespace-nowrap">
                  <div class="inline-flex items-center gap-1.5">
                    <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>"
                       class="px-2.5 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs transition"
                       title="Lihat Dokumen">
                      Lihat
                    </a>

                    <a href="<?= BASE_URL ?>/bra.php?action=edit&id=<?= $bra['id_bra'] ?>"
                       class="p-1.5 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl transition"
                       title="Koreksi / Edit Naskah">
                      <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </a>

                    <?php if (AuthHelper::canDelete()): ?>
                      <form method="POST" action="<?= BASE_URL ?>/bra.php?action=delete" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus naskah Berita Radio SAR <?= htmlspecialchars($bra['no_bra']) ?>? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus Dokumen BRA SAR" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$bra['id_bra'] ?>">
                        <button type="submit" class="p-1.5 text-red-500 hover:text-red-700 bg-red-50 dark:bg-red-500/10 hover:bg-red-100 rounded-xl transition cursor-pointer" title="Hapus Dokumen">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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

<!-- ======================================================== -->
<!-- 3. SEARCH & UTILITY SCRIPTS -->
<!-- ======================================================== -->
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
    if (!q || text.includes(q)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
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
