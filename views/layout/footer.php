<?php
/**
 * QR-SAR - Layout Footer
 * KPP Kendari - Basarnas
 */

$flash = getFlash();
?>
</main> <!-- end page-body -->

<footer class="py-4 text-center text-xs text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 transition-colors">
    <div><strong style="color: #F97316;">QR SAR System</strong> — Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari • BASARNAS</div>
</footer>

</div> <!-- end main-content -->
</div> <!-- end app-container -->

<!-- Leaflet JS (Local Offline Bundle) -->
<script src="<?= BASE_URL ?>/assets/js/leaflet.min.js?v=<?= APP_VERSION ?>"></script>

<!-- SweetAlert2 JS (Local Offline Bundle) -->
<script src="<?= BASE_URL ?>/assets/js/sweetalert2.all.min.js?v=<?= APP_VERSION ?>"></script>

<!-- QRCode.js (Local Offline Engine) -->
<script src="<?= BASE_URL ?>/assets/js/qrcode.min.js?v=<?= APP_VERSION ?>"></script>

<!-- Core App Scripts -->
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/bra.js?v=<?= APP_VERSION ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/gis.js?v=<?= APP_VERSION ?>"></script>

<?php if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    showToast(<?= json_encode($flash['message']) ?>, <?= json_encode($flash['type']) ?>);
});
</script>
<?php endif; ?>

</body>
</html>
