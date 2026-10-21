<?php
/**
 * QR-SAR - Master Data View (Orchestrator)
 * KPP Kendari - Basarnas
 * 100% Faithful to Reference MasterDataPage.tsx with Integrated Edit Modals & Global Theme Adaptations
 * Sub-menus: 1. Alut & Palsar, 2. Potensi (Tim), 3. SMC (Pejabat)
 * Decomposed into modular partial views under views/master/partials/
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$activeTab = $tab ?? ($activeTab ?? ($_GET['tab'] ?? 'alut'));
if (!in_array($activeTab, ['alut', 'tim', 'pejabat'], true)) {
    $activeTab = 'alut';
}
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
      <a href="<?= BASE_URL ?>/index.php" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-xl bg-white/20 hover:bg-white/30 backdrop-blur-sm border border-white/30 text-white transition shadow-sm" title="Kembali ke Dashboard">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
        <span>Dashboard</span>
      </a>
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

  <!-- Active Tab Partial View -->
  <?php
    $masterTabMap = [
        'alut'    => 'master/partials/tab_alut',
        'tim'     => 'master/partials/tab_potensi',
        'pejabat' => 'master/partials/tab_pejabat',
    ];
    $activePartial = $masterTabMap[$activeTab] ?? 'master/partials/tab_alut';
    renderPartial($activePartial, get_defined_vars());
  ?>
</div>

<!-- Edit Modals Partial View -->
<?php renderPartial('master/partials/modals', get_defined_vars()); ?>
