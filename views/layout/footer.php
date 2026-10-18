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
<script src="<?= BASE_URL ?>/assets/vendor/sweetalert2/sweetalert2.all.min.js?v=<?= APP_VERSION ?>"></script>

<!-- Conditional Vendor Scripts -->
<?php if ($requiresGis): ?>
<script src="<?= BASE_URL ?>/assets/vendor/leaflet/leaflet.min.js?v=<?= APP_VERSION ?>"></script>
<?php endif; ?>

<?php if ($requiresQr): ?>
<script src="<?= BASE_URL ?>/assets/vendor/qrcode/qrcode.min.js?v=<?= APP_VERSION ?>"></script>
<?php endif; ?>

<!-- Core App Scripts -->
<script src="<?= BASE_URL ?>/assets/js/utils/common.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>

<!-- Conditional Feature Scripts -->
<?php if ($requiresBra): ?>
<script src="<?= BASE_URL ?>/assets/js/bra.js?v=<?= APP_VERSION ?>"></script>
<?php endif; ?>

<?php if ($requiresGis): ?>
<script src="<?= BASE_URL ?>/assets/js/gis/geodesy.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/markers.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/patterns.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis/export.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis.js?v=<?= APP_VERSION ?>"></script>
<?php endif; ?>

<!-- Dedicated Page-Specific Scripts -->
<?php if (!empty($pageScript)): ?>
<script src="<?= BASE_URL ?>/assets/js/<?= ltrim($pageScript, '/') ?>?v=<?= APP_VERSION ?>"></script>
<?php endif; ?>
<?php if (!empty($pageScripts) && is_array($pageScripts)): ?>
    <?php foreach ($pageScripts as $script): ?>
<script src="<?= BASE_URL ?>/assets/js/<?= ltrim($script, '/') ?>?v=<?= APP_VERSION ?>"></script>
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

