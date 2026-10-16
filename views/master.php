<?php
/**
 * QR-SAR - Master Data View
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference MasterDataPage.tsx with Integrated Edit Modals & Global Theme Adaptations
 * Sub-menus: 1. Alut & Palsar, 2. Potensi (Tim), 3. SMC (Pejabat)
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$activeTab = $tab ?? 'alut';
$searchQ = trim($_GET['q'] ?? '');

$masterAlut = $masterAlut ?? (class_exists('MasterModel') ? MasterModel::getAlut() : []);
$masterTim = $masterTim ?? (class_exists('MasterModel') ? MasterModel::getTim() : []);
$pejabatList = $pejabatList ?? (class_exists('MasterModel') ? MasterModel::getPejabat() : []);
?>

<div class="space-y-6">
  <!-- Top Header Banner -->
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 p-5 rounded-2xl bg-gradient-to-r from-orange-600 via-amber-600 to-orange-500 shadow-lg text-white" style="background: linear-gradient(135deg, var(--primary) 0%, #ea580c 50%, #f59e0b 100%);">
    <div class="flex items-center gap-3.5">
      <div class="w-12 h-12 rounded-xl bg-white/20 backdrop-blur-sm border border-white/30 flex items-center justify-center text-white shrink-0 shadow-inner">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5V19A9 3 0 0 0 21 19V5"/><path d="M3 12A9 3 0 0 0 21 12"/></svg>
      </div>
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white uppercase">
            Master Data Operasional SAR
          </h1>
          <span class="text-[10px] font-mono uppercase px-2 py-0.5 rounded-full bg-white/25 text-white font-bold tracking-wider">
            DATABASE UTAMA
          </span>
        </div>
        <p class="text-xs text-orange-100 mt-0.5">
          Kelola master armada Alut & Palsar, unsur potensi SAR, serta data pejabat penandatangan resmi & SMC.
        </p>
      </div>
    </div>

    <div class="flex items-center gap-2 self-end sm:self-auto">
      <a href="<?= BASE_URL ?>/master.php?tab=<?= urlencode($activeTab) ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-white/20 hover:bg-white/30 backdrop-blur-sm border border-white/30 text-white transition shadow-sm" title="Segarkan Data">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        <span>Segarkan</span>
      </a>
    </div>
  </div>

  <!-- Master Tabs Switcher (Strictly 3 Sub-menus with Dynamic Theme Accent) -->
  <div class="p-1.5 rounded-2xl flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs">
    <a href="<?= BASE_URL ?>/master.php?tab=alut" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer <?= $activeTab === 'alut' ? 'text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>" style="<?= $activeTab === 'alut' ? 'background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);' : '' ?>">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"/><path d="M12 10v4"/></svg>
      <span>Master Alut & Palsar (<?= count($masterAlut) ?>)</span>
    </a>

    <a href="<?= BASE_URL ?>/master.php?tab=tim" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer <?= $activeTab === 'tim' ? 'text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>" style="<?= $activeTab === 'tim' ? 'background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);' : '' ?>">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Master Potensi SAR / Tim (<?= count($masterTim) ?>)</span>
    </a>

    <a href="<?= BASE_URL ?>/master.php?tab=pejabat" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer <?= $activeTab === 'pejabat' ? 'text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-800' ?>" style="<?= $activeTab === 'pejabat' ? 'background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);' : '' ?>">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="7" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
      <span>Pejabat & SMC (<?= count($pejabatList) ?>)</span>
    </a>
  </div>

  <!-- ======================================================== -->
  <!-- TAB 1: MASTER ALUT & PALSAR                              -->
  <!-- ======================================================== -->
  <?php if ($activeTab === 'alut'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column: Add New Alut Form -->
      <div class="card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
          <span class="p-1.5 rounded-lg text-orange-600 dark:text-orange-400" style="background-color: var(--primary-light); color: var(--primary);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          </span>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase">Tambah Alut / Palsar Baru</h2>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/master.php?action=add_alut" class="space-y-4">
          <?= csrfInput() ?>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nama Alut / Peralatan SAR <span class="text-red-500">*</span></label>
            <input type="text" name="nama_alut" required placeholder="Contoh: KN SAR Pacitan / Rigid Inflatable Boat (RIB)" class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Satuan Ukuran</label>
            <input type="text" name="satuan" value="Unit" placeholder="Unit, Set, Buah, dsb." class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
          </div>

          <button type="submit" class="w-full py-2.5 text-xs font-bold text-white rounded-xl transition shadow-md flex items-center justify-center gap-2 cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Simpan ke Master Alut</span>
          </button>
        </form>
      </div>

      <!-- Right Column: Alut List Table -->
      <div class="lg:col-span-2 card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase">Daftar Master Alut & Palsar</h2>
            <p class="text-[11px] text-slate-400">Total terdata: <?= count($masterAlut) ?> armada dan peralatan operasi SAR</p>
          </div>
          <div>
            <input type="text" id="filterMasterAlut" placeholder="Cari nama alut..." onkeyup="filterTable('filterMasterAlut', 'tableMasterAlut')" class="px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-orange-500 transition w-48">
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs" id="tableMasterAlut">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold text-[11px] uppercase tracking-wider">
                <th class="py-2.5 px-3">#</th>
                <th class="py-2.5 px-3">Nama Alut / Palsar</th>
                <th class="py-2.5 px-3">Satuan</th>
                <th class="py-2.5 px-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <?php foreach ($masterAlut as $idx => $a): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                  <td class="py-2.5 px-3 text-slate-400 font-mono"><?= $idx + 1 ?></td>
                  <td class="py-2.5 px-3 font-bold text-slate-800 dark:text-slate-100">
                    <?= e($a['nama_alut']) ?>
                  </td>
                  <td class="py-2.5 px-3 text-slate-500 dark:text-slate-400">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-[10px] font-mono font-bold text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                      <?= e($a['satuan'] ?? 'Unit') ?>
                    </span>
                  </td>
                  <td class="py-2.5 px-3 text-right">
                    <div class="inline-flex items-center gap-1 justify-end">
                      <button type="button" onclick="openEditAlutModal(<?= (int)$a['id_alut'] ?>, '<?= e(addslashes($a['nama_alut'])) ?>', '<?= e(addslashes($a['satuan'] ?? 'Unit')) ?>')" class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Edit Alut">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      </button>
                      <form method="POST" action="<?= BASE_URL ?>/master.php?action=delete_alut" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus alut &quot;<?= e($a['nama_alut']) ?>&quot; dari master data?" data-confirm-title="Hapus Master Alut" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$a['id_alut'] ?>">
                        <button type="submit" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Hapus Alut">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($masterAlut)): ?>
                <tr>
                  <td colspan="4" class="py-8 text-center text-slate-400 italic">Belum ada data Alut SAR.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ======================================================== -->
  <!-- TAB 2: MASTER TIM RESCUE & POTENSI SAR                  -->
  <!-- ======================================================== -->
  <?php if ($activeTab === 'tim'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column: Add New Tim Form -->
      <div class="card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
          <span class="p-1.5 rounded-lg text-orange-600 dark:text-orange-400" style="background-color: var(--primary-light); color: var(--primary);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          </span>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase">Tambah Tim / Unsur Potensi Baru</h2>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/master.php?action=add_tim" class="space-y-4">
          <?= csrfInput() ?>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nama Tim Penyelamat / Instansi Potensi <span class="text-red-500">*</span></label>
            <input type="text" name="nama_tim" required placeholder="Contoh: Tim Rescue Kansar Kendari / Pos SAR Kolaka / Polairud" class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
            <p class="text-[10px] text-slate-400 mt-1 italic">* Digunakan untuk pilihan Tim yang Diberangkatkan pada Laporan Awal maupun Unsur SAR Terlibat.</p>
          </div>

          <button type="submit" class="w-full py-2.5 text-xs font-bold text-white rounded-xl transition shadow-md flex items-center justify-center gap-2 cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Simpan ke Master Tim</span>
          </button>
        </form>
      </div>

      <!-- Right Column: Tim List Table -->
      <div class="lg:col-span-2 card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase">Daftar Tim Penyelamat & Unsur Potensi SAR</h2>
            <p class="text-[11px] text-slate-400">Total terdata: <?= count($masterTim) ?> tim siaga Basarnas dan unsur potensi SAR gabungan</p>
          </div>
          <div>
            <input type="text" id="filterMasterTim" placeholder="Cari nama tim/potensi..." onkeyup="filterTable('filterMasterTim', 'tableMasterTim')" class="px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-orange-500 transition w-48">
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs" id="tableMasterTim">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold text-[11px] uppercase tracking-wider">
                <th class="py-2.5 px-3">#</th>
                <th class="py-2.5 px-3">Nama Tim / Unsur Potensi SAR</th>
                <th class="py-2.5 px-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <?php foreach ($masterTim as $idx => $t): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                  <td class="py-2.5 px-3 text-slate-400 font-mono"><?= $idx + 1 ?></td>
                  <td class="py-2.5 px-3 font-bold text-slate-800 dark:text-slate-100">
                    <?= e($t['nama_tim']) ?>
                  </td>
                  <td class="py-2.5 px-3 text-right">
                    <div class="inline-flex items-center gap-1 justify-end">
                      <button type="button" onclick="openEditTimModal(<?= (int)$t['id_tim'] ?>, '<?= e(addslashes($t['nama_tim'])) ?>')" class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Edit Tim / Potensi SAR">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      </button>
                      <form method="POST" action="<?= BASE_URL ?>/master.php?action=delete_tim" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus &quot;<?= e($t['nama_tim']) ?>&quot; dari master data?" data-confirm-title="Hapus Master Tim" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= (int)$t['id_tim'] ?>">
                        <button type="submit" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Hapus Tim / Potensi">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($masterTim)): ?>
                <tr>
                  <td colspan="3" class="py-8 text-center text-slate-400 italic">Belum ada data Potensi SAR.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <!-- ======================================================== -->
  <!-- TAB 3: PEJABAT BERWENANG & SMC                           -->
  <!-- ======================================================== -->
  <?php if ($activeTab === 'pejabat'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Left Column: Add New Pejabat Form -->
      <div class="card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex items-center gap-2 pb-3 border-b border-slate-100 dark:border-slate-800">
          <span class="p-1.5 rounded-lg text-orange-600 dark:text-orange-400" style="background-color: var(--primary-light); color: var(--primary);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          </span>
          <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase">Tambah Pejabat Baru</h2>
        </div>

        <form method="POST" action="<?= BASE_URL ?>/master.php?action=add_pejabat" class="space-y-3.5">
          <?= csrfInput() ?>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nama Lengkap Pejabat <span class="text-red-500">*</span></label>
            <input type="text" name="nama" required placeholder="Contoh: AMIRUDDIN AS, S.Sos." class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Jabatan Resmi <span class="text-red-500">*</span></label>
            <input type="text" name="jabatan" required placeholder="Contoh: KEPALA KANTOR / SMC KPP KENDARI" class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
          </div>

          <div class="grid grid-cols-2 gap-2.5">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Pangkat / Golongan</label>
              <input type="text" name="pangkat" placeholder="PEMBINA (IV/a)" class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">NIP</label>
              <input type="text" name="nip" placeholder="197405101998031002" class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
            </div>
          </div>

          <div class="pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" name="is_default_smc" value="1" class="rounded text-orange-600 focus:ring-orange-500">
              <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Tetapkan sebagai Default SMC pada dokumen BRA</span>
            </label>
          </div>

          <button type="submit" class="w-full py-2.5 text-xs font-bold text-white rounded-xl transition shadow-md flex items-center justify-center gap-2 cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
            <span>Simpan Data Pejabat</span>
          </button>
        </form>
      </div>

      <!-- Right Column: Pejabat List Table -->
      <div class="lg:col-span-2 card p-5 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-100 dark:border-slate-800">
          <div>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase">Daftar Pejabat Berwenang & Penandatangan SMC</h2>
            <p class="text-[11px] text-slate-400">Total terdata: <?= count($pejabatList) ?> pejabat berwenang penandatangan BRA dan Renops</p>
          </div>
          <div>
            <input type="text" id="filterMasterPejabat" placeholder="Cari nama pejabat..." onkeyup="filterTable('filterMasterPejabat', 'tableMasterPejabat')" class="px-3 py-1.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 placeholder-slate-400 focus:outline-none focus:border-orange-500 transition w-48">
          </div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs" id="tableMasterPejabat">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold text-[11px] uppercase tracking-wider">
                <th class="py-2.5 px-3">Pejabat & Jabatan</th>
                <th class="py-2.5 px-3">NIP / Pangkat</th>
                <th class="py-2.5 px-3">Status SMC</th>
                <th class="py-2.5 px-3 text-right">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-medium">
              <?php foreach ($pejabatList as $p): ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                  <td class="py-2.5 px-3">
                    <div class="font-bold text-slate-800 dark:text-slate-100"><?= e($p['nama']) ?></div>
                    <div class="text-[10px] text-slate-400 font-semibold"><?= e($p['jabatan']) ?></div>
                  </td>
                  <td class="py-2.5 px-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                    <div><?= e($p['pangkat'] ?: '-') ?></div>
                    <div class="text-[10px] text-slate-400">NIP: <?= e($p['nip'] ?: '-') ?></div>
                  </td>
                  <td class="py-2.5 px-3">
                    <?php if (!empty($p['is_default_smc'])): ?>
                      <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                        ⭐ DEFAULT SMC
                      </span>
                    <?php else: ?>
                      <form method="POST" action="<?= BASE_URL ?>/master.php?action=toggle_smc" class="inline-block">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= e((string)$p['id']) ?>">
                        <button type="submit" class="inline-flex items-center text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-500 hover:text-orange-500 border border-slate-200 dark:border-slate-700 transition cursor-pointer" title="Jadikan default SMC">
                          Set Default
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                  <td class="py-2.5 px-3 text-right">
                    <div class="inline-flex items-center gap-1 justify-end">
                      <button type="button" onclick="openEditPejabatModal('<?= e(addslashes((string)$p['id'])) ?>', '<?= e(addslashes($p['nama'])) ?>', '<?= e(addslashes($p['jabatan'])) ?>', '<?= e(addslashes($p['pangkat'] ?? '')) ?>', '<?= e(addslashes($p['nip'] ?? '')) ?>', <?= !empty($p['is_default_smc']) ? 'true' : 'false' ?>)" class="p-1.5 text-blue-500 hover:text-blue-700 hover:bg-blue-50 dark:hover:bg-blue-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Edit Pejabat">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                      </button>
                      <form method="POST" action="<?= BASE_URL ?>/master.php?action=delete_pejabat" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus pejabat &quot;<?= e($p['nama']) ?>&quot;?" data-confirm-title="Hapus Pejabat" data-confirm-btn="Ya, Hapus" data-confirm-danger="true">
                        <?= csrfInput() ?>
                        <input type="hidden" name="id" value="<?= e((string)$p['id']) ?>">
                        <button type="submit" class="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition inline-flex items-center cursor-pointer" title="Hapus Pejabat">
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                      </form>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($pejabatList)): ?>
                <tr>
                  <td colspan="4" class="py-8 text-center text-slate-400 italic">Belum ada data Pejabat.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT ALUT / PALSAR                                -->
<!-- Dialog hanya dapat ditutup via tombol close              -->
<!-- ======================================================== -->
<div id="modal-edit-alut" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Edit Master Alut & Palsar</h3>
          <p class="text-[11px] text-slate-400">Perbarui spesifikasi nama armada atau satuan perlengkapan</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-edit-alut')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        ✕
      </button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/master.php?action=edit_alut" class="flex flex-col flex-1 overflow-hidden">
      <?= csrfInput() ?>
      <input type="hidden" name="id_alut" id="edit_id_alut">

      <div class="p-6 space-y-4 overflow-y-auto">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nama Alut / Peralatan SAR <span class="text-red-500">*</span></label>
          <input type="text" name="nama_alut" id="edit_nama_alut" required class="w-full px-3.5 py-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Satuan Ukuran</label>
          <input type="text" name="satuan" id="edit_satuan_alut" placeholder="Unit, Set, Buah, dsb." class="w-full px-3.5 py-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
        <button type="button" onclick="closeModal('modal-edit-alut')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-white font-bold text-xs shadow-md transition cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT POTENSI SAR (TIM)                            -->
<!-- Dialog hanya dapat ditutup via tombol close              -->
<!-- ======================================================== -->
<div id="modal-edit-tim" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Edit Tim / Potensi SAR</h3>
          <p class="text-[11px] text-slate-400">Perbarui nama tim penyelamat Basarnas atau instansi potensi SAR gabungan</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-edit-tim')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        ✕
      </button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/master.php?action=edit_tim" class="flex flex-col flex-1 overflow-hidden">
      <?= csrfInput() ?>
      <input type="hidden" name="id_tim" id="edit_id_tim">

      <div class="p-6 space-y-4 overflow-y-auto">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nama Tim Penyelamat / Potensi SAR <span class="text-red-500">*</span></label>
          <input type="text" name="nama_tim" id="edit_nama_tim" required class="w-full px-3.5 py-2.5 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
        <button type="button" onclick="closeModal('modal-edit-tim')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-white font-bold text-xs shadow-md transition cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: EDIT PEJABAT BERWENANG & SMC                      -->
<!-- Dialog hanya dapat ditutup via tombol close              -->
<!-- ======================================================== -->
<div id="modal-edit-pejabat" class="modal-overlay fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm hidden" style="display: none;">
  <div class="w-full max-w-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
      <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-xl bg-blue-500/15 text-blue-500 flex items-center justify-center">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </div>
        <div>
          <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">Edit Data Pejabat Penandatangan / SMC</h3>
          <p class="text-[11px] text-slate-400">Perbarui identitas pejabat resmi penandatangan berkas operasional SAR</p>
        </div>
      </div>
      <button type="button" onclick="closeModal('modal-edit-pejabat')" class="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer">
        ✕
      </button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>/master.php?action=edit_pejabat" class="flex flex-col flex-1 overflow-hidden">
      <?= csrfInput() ?>
      <input type="hidden" name="id" id="edit_id_pejabat">

      <div class="p-6 space-y-3.5 overflow-y-auto">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Nama Lengkap Pejabat <span class="text-red-500">*</span></label>
          <input type="text" name="nama" id="edit_nama_pejabat" required class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Jabatan Resmi <span class="text-red-500">*</span></label>
          <input type="text" name="jabatan" id="edit_jabatan_pejabat" required class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
        </div>

        <div class="grid grid-cols-2 gap-2.5">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">Pangkat / Golongan</label>
            <input type="text" name="pangkat" id="edit_pangkat_pejabat" placeholder="PEMBINA (IV/a)" class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">NIP</label>
            <input type="text" name="nip" id="edit_nip_pejabat" placeholder="197405101998031002" class="w-full px-3.5 py-2 text-xs rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-100 focus:outline-none focus:border-orange-500 dark:focus:border-orange-400 transition font-medium">
          </div>
        </div>

        <div class="pt-1">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="checkbox" name="is_default_smc" id="edit_is_default_smc" value="1" class="rounded text-orange-600 focus:ring-orange-500">
            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Tetapkan sebagai Default SMC pada dokumen BRA</span>
          </label>
        </div>
      </div>

      <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-950/50">
        <button type="button" onclick="closeModal('modal-edit-pejabat')" class="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer">
          Batal
        </button>
        <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2 rounded-xl text-white font-bold text-xs shadow-md transition cursor-pointer hover:opacity-95" style="background-color: var(--primary); box-shadow: 0 4px 12px var(--primary-glow);">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
          <span>Simpan Perubahan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function filterTable(inputId, tableId) {
  const input = document.getElementById(inputId);
  if (!input) return;
  const filter = input.value.toLowerCase();
  const table = document.getElementById(tableId);
  if (!table) return;
  const rows = table.getElementsByTagName('tr');

  for (let i = 1; i < rows.length; i++) {
    const text = rows[i].textContent.toLowerCase();
    rows[i].style.display = text.includes(filter) ? '' : 'none';
  }
}

// Open Edit Alut Modal
function openEditAlutModal(id, nama, satuan) {
  document.getElementById('edit_id_alut').value = id;
  document.getElementById('edit_nama_alut').value = nama;
  document.getElementById('edit_satuan_alut').value = satuan || 'Unit';
  openModal('modal-edit-alut');
}

// Open Edit Tim Modal
function openEditTimModal(id, nama) {
  document.getElementById('edit_id_tim').value = id;
  document.getElementById('edit_nama_tim').value = nama;
  openModal('modal-edit-tim');
}

// Open Edit Pejabat Modal
function openEditPejabatModal(id, nama, jabatan, pangkat, nip, isDefaultSmc) {
  document.getElementById('edit_id_pejabat').value = id;
  document.getElementById('edit_nama_pejabat').value = nama;
  document.getElementById('edit_jabatan_pejabat').value = jabatan;
  document.getElementById('edit_pangkat_pejabat').value = pangkat || '';
  document.getElementById('edit_nip_pejabat').value = nip || '';
  document.getElementById('edit_is_default_smc').checked = !!isDefaultSmc;
  openModal('modal-edit-pejabat');
}
</script>
