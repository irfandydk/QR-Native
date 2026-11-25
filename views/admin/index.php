<?php
/**
 * QR-SAR - Admin Settings View (Orchestrator)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 * Decomposed into modular partial views under views/admin/partials/
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$tab = $tab ?? 'admin';
$settings = $settings ?? [];
$aiConfig = $aiConfig ?? ['keys' => [], 'providers' => [], 'auto_fallback' => true, 'default_key_id' => '', 'active_default' => null, 'env_fallbacks' => []];
$dbStats = $dbStats ?? [];
$usersList = $usersList ?? [];
$auditLogs = $auditLogs ?? [];
$totalLogs = $totalLogs ?? 0;
$logPage = $logPage ?? 1;
$totalPages = $totalPages ?? 1;
$logActionFilter = $logActionFilter ?? null;
$logSearch = $logSearch ?? null;
$logoUrl = getAppLogoUrl();
?>

<div class="max-w-7xl mx-auto space-y-5">
  <h1 class="sr-only">Pengaturan Administrator dan Satker</h1>

  <!-- Flush Underline Tab Rail (No Duplicate Header Bar) -->
  <nav aria-label="Menu pengaturan admin" class="adm-tabs flex items-center overflow-x-auto no-print">
    <a href="<?= BASE_URL ?>/admin.php?tab=admin" class="adm-tab-link <?= ($tab === 'admin' || $tab === 'user') ? 'active' : '' ?>">
      <span>Akun &amp; Pengguna</span>
      <?php if (!empty($usersList)): ?>
        <span class="adm-tab-count"><?= count($usersList) ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=satker" class="adm-tab-link <?= $tab === 'satker' ? 'active' : '' ?>">
      <span>Satker &amp; Tampilan</span>
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=ai" class="adm-tab-link <?= $tab === 'ai' ? 'active' : '' ?>">
      <span>Asisten AI</span>
      <?php if (!empty($aiConfig['keys'])): ?>
        <span class="adm-tab-count"><?= count($aiConfig['keys']) ?></span>
      <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=backup" class="adm-tab-link <?= $tab === 'backup' ? 'active' : '' ?>">
      <span>Backup &amp; Restore</span>
    </a>
    <a href="<?= BASE_URL ?>/admin.php?tab=logs" class="adm-tab-link <?= $tab === 'logs' ? 'active' : '' ?>">
      <span>Log Audit</span>
      <?php if ($totalLogs > 0): ?>
        <span class="adm-tab-count"><?= number_format((int)$totalLogs) ?></span>
      <?php endif; ?>
    </a>
  </nav>

  <!-- Main Content Area (Rendered via Partial Views) -->
  <?php
    $tabViewMap = [
        'admin'  => 'admin/partials/tab_admin',
        'user'   => 'admin/partials/tab_user',
        'satker' => 'admin/partials/tab_satker',
        'ai'     => 'admin/partials/tab_ai',
        'backup' => 'admin/partials/tab_backup',
        'logs'   => 'admin/partials/tab_logs',
    ];
    $activePartial = $tabViewMap[$tab] ?? 'admin/partials/tab_admin';
    renderPartial($activePartial, get_defined_vars());
  ?>
</div>
