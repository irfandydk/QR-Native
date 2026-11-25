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
