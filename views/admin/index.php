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

<style>
  .adm-accent-btn { background-color: var(--primary); color: #fff !important; border: 1px solid transparent; }
  .adm-accent-btn:hover { background-color: var(--primary-hover); color: #fff !important; }
  .adm-accent-soft { background-color: color-mix(in srgb, var(--primary) 10%, transparent); color: var(--m-accent-text, var(--primary)) !important; border: 1px solid color-mix(in srgb, var(--primary) 26%, transparent); }
  .adm-accent-soft:hover { background-color: var(--primary); color: #fff !important; border-color: transparent; }
  .adm-accent-text { color: var(--m-accent-text, var(--primary)); }
  .adm-ghost-btn { background-color: var(--bg-card); color: var(--text-main); border: 1px solid var(--border-color); }
  .adm-ghost-btn:hover { background-color: var(--bg-card-subtle); }
  .adm-radio-card { border: 1px solid var(--border-color); background-color: var(--bg-card); transition: border-color 160ms ease, background-color 160ms ease; }
  .adm-radio-card input[type="radio"] { accent-color: var(--primary); }
  .adm-radio-card:has(input:checked) { border-color: var(--primary); background-color: color-mix(in srgb, var(--primary) 8%, var(--bg-card)); }
  .adm-tabs { border-bottom: 1px solid var(--border-color); gap: 0.2rem; }
  .adm-tab-link { display: inline-flex; align-items: center; gap: 0.45rem; padding: 0.55rem 0.8rem; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); border-bottom: 2px solid transparent; border-radius: 0.5rem 0.5rem 0 0; white-space: nowrap; text-decoration: none; transition: color 160ms ease, background-color 160ms ease; }
  .adm-tab-link:hover { color: var(--text-main); background: color-mix(in srgb, var(--bg-card-subtle) 65%, transparent); }
  .adm-tab-link.active { color: var(--m-accent-text, var(--primary)); border-bottom-color: var(--primary); font-weight: 700; }
  .adm-tab-count { font-family: 'JetBrains Mono', monospace; font-size: 10px; padding: 0.08rem 0.4rem; border-radius: 9999px; background: var(--bg-card-subtle); color: var(--text-muted); }
  .adm-tab-link.active .adm-tab-count { background: color-mix(in srgb, var(--primary) 14%, transparent); color: var(--m-accent-text, var(--primary)); }
  .adm-accent-btn:focus-visible, .adm-accent-soft:focus-visible, .adm-ghost-btn:focus-visible, .adm-tab-link:focus-visible { outline: 2px solid var(--primary); outline-offset: 2px; }
  @media (prefers-reduced-motion: reduce) { .adm-accent-btn, .adm-accent-soft, .adm-ghost-btn, .adm-tab-link, .adm-radio-card { transition: none !important; } }
</style>

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
