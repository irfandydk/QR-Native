<?php
/**
 * QR-SAR - Master Data View (Orchestrator)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 * Sub-menus: 1. Alut & Palsar, 2. Potensi (Tim), 3. SMC (Pejabat)
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

<style>
  .mst-accent-btn { background-color: var(--primary); color: #fff !important; border: 1px solid transparent; }
  .mst-accent-btn:hover { background-color: var(--primary-hover); color: #fff !important; }
  .mst-accent-text { color: var(--m-accent-text, var(--primary)); }
  .mst-ghost-btn { background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
  .mst-ghost-btn:hover { background-color: var(--bg-card-subtle); }
  .mst-tabs { border-bottom: 1px solid var(--border-color); gap: 0.2rem; }
  .mst-tab-link { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.55rem 0.8rem; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); border-bottom: 2px solid transparent; border-radius: 0.5rem 0.5rem 0 0; white-space: nowrap; text-decoration: none; transition: color 160ms ease, background-color 160ms ease; }
  .mst-tab-link:hover { color: var(--text-main); background: color-mix(in srgb, var(--bg-card-subtle) 65%, transparent); }
  .mst-tab-link.active { color: var(--m-accent-text, var(--primary)); border-bottom-color: var(--primary); font-weight: 700; }
  .mst-tab-count { font-family: 'JetBrains Mono', monospace; font-size: 10px; padding: 0.08rem 0.4rem; border-radius: 9999px; background: var(--bg-card-subtle); color: var(--text-muted); }
  .mst-tab-link.active .mst-tab-count { background: color-mix(in srgb, var(--primary) 14%, transparent); color: var(--m-accent-text, var(--primary)); }
  .mst-accent-btn:focus-visible, .mst-ghost-btn:focus-visible, .mst-tab-link:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
  @media (prefers-reduced-motion: reduce) { .mst-accent-btn, .mst-ghost-btn, .mst-tab-link { transition: none !important; } }
</style>

<div class="max-w-7xl mx-auto space-y-5">
  <h1 class="sr-only">Master Data Operasional SAR</h1>

  <!-- Flush Underline Tab Rail -->
  <nav aria-label="Kategori master data" class="mst-tabs flex items-center overflow-x-auto no-print">
    <a href="<?= BASE_URL ?>/master.php?tab=alut" class="mst-tab-link <?= $activeTab === 'alut' ? 'active' : '' ?>">
      <span>Alut &amp; Palsar</span>
      <span class="mst-tab-count"><?= count($masterAlut) ?></span>
    </a>
    <a href="<?= BASE_URL ?>/master.php?tab=tim" class="mst-tab-link <?= $activeTab === 'tim' ? 'active' : '' ?>">
      <span>Potensi SAR / Tim</span>
      <span class="mst-tab-count"><?= count($masterTim) ?></span>
    </a>
    <a href="<?= BASE_URL ?>/master.php?tab=pejabat" class="mst-tab-link <?= $activeTab === 'pejabat' ? 'active' : '' ?>">
      <span>Pejabat &amp; SMC</span>
      <span class="mst-tab-count"><?= count($pejabatList) ?></span>
    </a>
  </nav>

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
