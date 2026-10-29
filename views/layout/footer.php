<?php
/**
 * QR-SAR - Layout Footer
 * KPP Kendari - Basarnas
 */

$flash = getFlash();
$requiresGis = !empty($useGis) || in_array($currentRoute ?? '', ['gis', 'operasi', 'operasi_detail', 'operasi_form'], true);
$requiresQr  = !empty($useQr) || in_array($currentRoute ?? '', ['bra', 'bra_view', 'bra_list', 'bra_form'], true);
$requiresBra = !empty($useBra) || in_array($currentRoute ?? '', ['bra', 'bra_view', 'bra_list', 'bra_form'], true);
?>
</main> <!-- end page-body -->

<footer class="py-4 text-center text-xs text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition-colors">
    <div><strong style="color: #F97316;">QR SAR System</strong> — Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari • BASARNAS</div>
</footer>

</div> <!-- end main-content -->
</div> <!-- end app-container -->

<!-- Core Vendor Scripts (Global) -->
<script src="<?= assetUrl('assets/vendor/sweetalert2/sweetalert2.all.min.js') ?>"></script>

<!-- Conditional Vendor Scripts -->
<?php if ($requiresGis): ?>
<script src="<?= assetUrl('assets/vendor/leaflet/leaflet.min.js') ?>"></script>
<?php endif; ?>

<?php if ($requiresQr): ?>
<script src="<?= assetUrl('assets/vendor/qrcode/qrcode.min.js') ?>"></script>
<?php endif; ?>

<!-- Core App Scripts -->
<script src="<?= assetUrl('assets/js/utils/common.js') ?>"></script>
<script src="<?= assetUrl('assets/js/app.js') ?>"></script>
<?php if (($uiTheme ?? ((SettingsModel::getSettings()['ui_theme'] ?? 'klasik'))) === 'minimal'): ?>
<script src="<?= assetUrl('assets/js/theme-minimal.js') ?>"></script>
<?php endif; ?>

<!-- Conditional Feature Scripts -->
<?php if ($requiresBra): ?>
<script src="<?= assetUrl('assets/js/bra.js') ?>"></script>
<?php endif; ?>

<?php if ($requiresGis): ?>
<script src="<?= assetUrl('assets/js/gis/geodesy.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/markers.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/patterns.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis/export.js') ?>"></script>
<script src="<?= assetUrl('assets/js/gis.js') ?>"></script>
<?php endif; ?>

<!-- Dedicated Page-Specific Scripts -->
<?php if (!empty($pageScript)): ?>
<script src="<?= assetUrl('assets/js/' . ltrim($pageScript, '/')) ?>"></script>
<?php endif; ?>
<?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
<script src="<?= assetUrl('assets/js/' . ltrim($script, '/')) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<?php if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    showToast(<?= jsonSafe($flash['message']) ?>, <?= jsonSafe($flash['type']) ?>);
});
</script>
<?php endif; ?>

</body>
</html>

