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
  <!-- Master Tabs Switcher (High-End Floating Island Segmented Control) -->
  <div class="cockpit-glass p-1.5 rounded-2xl flex flex-wrap items-center gap-1.5 border border-slate-200/80 dark:border-slate-800 shadow-xs">
    <a href="<?= BASE_URL ?>/master.php?tab=alut" class="btn-tactile flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer <?= $activeTab === 'alut' ? 'text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>" style="<?= $activeTab === 'alut' ? 'background-color: var(--primary); box-shadow: 0 4px 14px -2px var(--primary-glow);' : '' ?>">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/><path d="M19.38 20A11.6 11.6 0 0 0 21 14l-9-4-9 4c0 2.9.94 5.34 2.81 7.76"/><path d="M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6"/><path d="M12 10v4"/></svg>
      <span>Master Alut & Palsar (<?= count($masterAlut) ?>)</span>
    </a>

    <a href="<?= BASE_URL ?>/master.php?tab=tim" class="btn-tactile flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer <?= $activeTab === 'tim' ? 'text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>" style="<?= $activeTab === 'tim' ? 'background-color: var(--primary); box-shadow: 0 4px 14px -2px var(--primary-glow);' : '' ?>">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      <span>Master Potensi SAR / Tim (<?= count($masterTim) ?>)</span>
    </a>

    <a href="<?= BASE_URL ?>/master.php?tab=pejabat" class="btn-tactile flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer <?= $activeTab === 'pejabat' ? 'text-white shadow-md' : 'text-slate-600 dark:text-slate-300 hover:bg-white/80 dark:hover:bg-slate-800/80' ?>" style="<?= $activeTab === 'pejabat' ? 'background-color: var(--primary); box-shadow: 0 4px 14px -2px var(--primary-glow);' : '' ?>">
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

<!-- Edit Modals Partial View (Hanya dirender untuk Administrator) -->
<?php if (AuthHelper::isAdmin()): ?>
  <?php renderPartial('master/partials/modals', get_defined_vars()); ?>
<?php endif; ?>
