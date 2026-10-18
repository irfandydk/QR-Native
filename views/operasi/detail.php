<?php
/**
 * QR-SAR - Detail Operasi Hub View (Modular Architecture)
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

if (empty($operasi)) {
    echo "<div class='p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-300'>Operasi SAR tidak ditemukan.</div>";
    return;
}

$id = (int)$operasi['id_operasi'];
$isOpen = ($operasi['status'] ?? '') === 'OPEN';
$waktuMulaiOps = TemplateHelper::getWaktuMulaiAktual($operasi);
$tglMulaiOps = date('Y-m-d', strtotime($waktuMulaiOps));
$hariKe = TemplateHelper::hitungHariKe($operasi);
$statsKorban = $operasi['stats_korban'] ?? ['total' => 0, 's' => 0, 'md' => 0, 'dp' => 0, 'h' => 0];
$tglKejadian = TemplateHelper::formatTanggalIndo($operasi['waktu_kejadian']);
$jamKejadian = TemplateHelper::formatJamWITA($operasi['waktu_kejadian']);
$tglBerangkat = TemplateHelper::formatTanggalIndo($operasi['waktu_berangkat'] ?: $waktuMulaiOps);
$jamBerangkat = TemplateHelper::formatJamWITA($operasi['waktu_berangkat'] ?: $waktuMulaiOps);
$tglSelesai = !empty($operasi['waktu_selesai']) ? TemplateHelper::formatTanggalIndo($operasi['waktu_selesai']) : null;
$jamSelesai = !empty($operasi['waktu_selesai']) ? TemplateHelper::formatJamWITA($operasi['waktu_selesai']) : null;
$isReopened = !empty($operasi['waktu_reopen']);
$tglReopen = $isReopened ? TemplateHelper::formatTanggalIndo($operasi['waktu_reopen']) : null;
$jamReopen = $isReopened ? TemplateHelper::formatJamWITA($operasi['waktu_reopen']) : null;

$activeTab = $_GET['tab'] ?? 'hierarchy';
if ($activeTab === 'ringkasan') {
    $activeTab = 'hierarchy';
}
if ($activeTab === 'laporan') {
    $activeTab = 'sitrep';
}

// Operational Hierarchy & Defaults
$timeline = HierarchyHelper::getOperationalTimeline($operasi);
$nextRenops = HierarchyHelper::getNextRenopsDefaults($operasi);
$nextLapbang = HierarchyHelper::getNextLapbangDefaults($operasi);

// Coordinates
$lkpCoords = GeoHelper::parseCoordinates($operasi['koordinat'] ?? '');
$hasLkpCoords = !empty($lkpCoords) && isset($lkpCoords['lat']) && isset($lkpCoords['lng']);
$lkpLat = $hasLkpCoords ? (float)$lkpCoords['lat'] : -4.037694;
$lkpLng = $hasLkpCoords ? (float)$lkpCoords['lng'] : 122.486694;

$countRenops = count($operasi['renops'] ?? []);
$countSitrep = count($operasi['laporan_harian'] ?? []);
$countKorban = count($operasi['korban'] ?? []);
$countBra = count($operasi['bra_sar'] ?? []);

$gisBundle = $gisBundle ?? GisModel::getOperationBundle($id, true);
$cntPolys = count($gisBundle['polygons'] ?? []);
$cntPatterns = count($gisBundle['searchPatterns'] ?? []);
$cntRoutes = count($gisBundle['routes'] ?? []);
$cntPoints = count($gisBundle['points'] ?? []);
$totalComponents = $cntPolys + $cntPatterns + $cntRoutes + $cntPoints;
?>

<!-- ======================================================== -->
<!-- TOP BREADCRUMB & CONTEXT HEADER -->
<!-- ======================================================== -->
<div class="flex items-center justify-between gap-3 mb-4 no-print">
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/operasi.php" class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-semibold text-xs shadow-xs transition">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
            <span>Daftar Operasi</span>
        </a>
        <span class="text-xs text-slate-300 dark:text-slate-700 font-semibold hidden sm:inline">/</span>
        <span class="inline-flex items-center px-2 py-0.5 rounded-md font-mono text-[11px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
            OPS-#<?= $id ?>
        </span>
    </div>

    <?php if (AuthHelper::canDelete()): ?>
    <form method="POST" action="<?= BASE_URL ?>/operasi.php?action=delete" class="inline-block" data-confirm="Apakah Anda yakin ingin menghapus Operasi SAR ini secara permanen beserta seluruh Renops, Sitrep, dan data terkait? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus Operasi SAR Permanen" data-confirm-btn="Ya, Hapus Permanen" data-confirm-danger="true">
        <?= csrfInput() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500 text-red-600 hover:text-white border border-red-500/20 font-semibold text-xs transition cursor-pointer" title="Hapus Operasi">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            <span>Hapus Operasi</span>
        </button>
    </form>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- EXECUTIVE SUMMARY CARD (SIMPEL, BERSIH, MODERN) -->
<!-- ======================================================== -->
<div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 shadow-sm p-4 sm:p-5 mb-5 transition-colors">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <div class="space-y-2 min-w-0">
            <!-- Badges Row -->
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($isOpen): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold font-mono tracking-wider bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-500/25">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    OPERASI BERJALAN (<?= $hariKe ?>)
                </span>
                <?php if ($isReopened): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold font-mono tracking-wider bg-amber-500/10 dark:bg-amber-500/20 text-amber-700 dark:text-amber-400 border border-amber-500/25" title="Operasi SAR dibuka kembali / diperpanjang pada <?= $tglReopen ?> <?= $jamReopen ?> WITA. Perhitungan hari dimulai dari tanggal pembukaan.">
                    <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    DIPERPANJANG
                </span>
                <?php endif; ?>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold font-mono tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    OPERASI SELESAI (<?= e($hariKe) ?>)
                </span>
                <?php endif; ?>

                <?php if (!empty($operasi['jenis_kejadian'])): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-slate-100 dark:bg-slate-800/80 text-slate-700 dark:text-slate-300 border border-slate-200/70 dark:border-slate-700">
                    <svg class="w-3 h-3 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.386l5.228-3.076c.827-.486 1.139-1.528.698-2.316L11.16 3.659A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                    <span><?= e($operasi['jenis_kejadian']) ?></span>
                </span>
                <?php endif; ?>
            </div>

            <!-- Title -->
            <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 dark:text-white tracking-tight leading-snug">
                <?= e($operasi['nama_operasi']) ?>
            </h1>

            <!-- Meta Information -->
            <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-slate-500 dark:text-slate-400">
                <span class="inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-orange-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Mulai Ops: <strong class="text-slate-800 dark:text-slate-200 font-mono"><?= TemplateHelper::formatTanggalIndo($waktuMulaiOps) ?> (<?= TemplateHelper::formatJamWITA($waktuMulaiOps) ?> WITA)</strong></span>
                </span>

                <?php if (!empty($operasi['lokasi_kejadian'])): ?>
                <span class="inline-flex items-center gap-1.5 truncate max-w-md" title="<?= e($operasi['lokasi_kejadian']) ?>">
                    <svg class="w-3.5 h-3.5 text-rose-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                    <span><?= e($operasi['lokasi_kejadian']) ?></span>
                </span>
                <?php endif; ?>

                <?php if (!empty($operasi['koordinat'])): ?>
                <span class="inline-flex items-center gap-1.5 font-mono text-[11px] text-slate-600 dark:text-slate-300">
                    <svg class="w-3.5 h-3.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v3m0 12v3M3 12h3m12 0h3"/></svg>
                    <span>LKP: <?= e($operasi['koordinat']) ?></span>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Toolbar -->
        <div class="flex flex-wrap items-center gap-2 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100 dark:border-slate-800 no-print">
            <a href="<?= BASE_URL ?>/gis.php?id_operasi=<?= $id ?>&include_base=1" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs shadow-xs transition" title="Buka Peta Taktis GIS Operasi Ini">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                <span>Peta Plotting GIS</span>
            </a>

            <button type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs shadow-xs transition cursor-pointer" onclick="openModal('modal-view-wa-awal')" title="Lihat naskah laporan awal format WhatsApp">
                <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                <span>Naskah WA</span>
            </button>

            <?php if ($isOpen): ?>
            <a href="<?= BASE_URL ?>/operasi_form.php?id=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs shadow-xs transition" title="Koreksi data inisiasi laporan awal">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                <span>Edit Lap Awal</span>
            </a>

            <button type="button" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-red-500/10 hover:bg-red-600 text-red-600 hover:text-white border border-red-500/25 font-bold text-xs shadow-xs transition cursor-pointer" onclick="openModal('modal-close-ops-detail')" title="Tutup Operasi SAR secara resmi">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <span>Tutup Operasi</span>
            </button>
            <?php else: ?>
            <div class="flex items-center gap-2">
                <span class="text-xs text-amber-600 dark:text-amber-400 font-medium hidden sm:inline">
                    Ditutup <?= $tglSelesai ? "({$tglSelesai})" : '' ?>
                </span>
                <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-xs transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    <span>Buka Kembali</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- TAB NAVIGATION (CLEAN & MODERN) -->
<!-- ======================================================== -->
<div class="tabs-header border-b border-slate-200 dark:border-slate-800 mb-6 flex overflow-x-auto no-print">
    <button class="tab-btn <?= $activeTab === 'hierarchy' ? 'active' : '' ?>" onclick="switchTab('hierarchy')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Hirarki Dokumen (<?= count($timeline) ?>)</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'renops' ? 'active' : '' ?>" onclick="switchTab('renops')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
        <span>Rencana Operasi (<?= $countRenops ?>)</span>
    </button>
    <button class="tab-btn <?= ($activeTab === 'sitrep' || $activeTab === 'laporan') ? 'active' : '' ?>" onclick="switchTab('sitrep')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        <span>Laporan Perkembangan (<?= $countSitrep ?>)</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'korban' ? 'active' : '' ?>" onclick="switchTab('korban')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
        <span>Data Korban (<?= $countKorban ?>)</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'kekuatan' ? 'active' : '' ?>" onclick="switchTab('kekuatan')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
        <span>Kekuatan Unsur & Alut</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'gis' ? 'active' : '' ?>" onclick="switchTab('gis')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
        <span>Peta Taktis (GIS)</span>
        <span id="gis-tab-count" class="<?= $totalComponents > 0 ? '' : 'hidden' ?> ml-1 px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-orange-500/15 text-orange-600 dark:text-orange-400 border border-orange-500/30"><?= $totalComponents ?></span>
    </button>
    <button class="tab-btn <?= $activeTab === 'cetak' ? 'active' : '' ?>" onclick="switchTab('cetak')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
        <span>Cetak Berkas</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'bra' ? 'active' : '' ?>" onclick="switchTab('bra')">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
        <span>BRA SAR (<?= $countBra ?>)</span>
    </button>
</div>

<!-- ======================================================== -->
<!-- TAB CONTENT PANES (MODULAR PARTIALS) -->
<!-- ======================================================== -->
<?php
$contextData = get_defined_vars();
renderPartial('operasi/partials/tab_hierarchy', $contextData);
renderPartial('operasi/partials/tab_renops', $contextData);
renderPartial('operasi/partials/tab_sitrep', $contextData);
renderPartial('operasi/partials/tab_korban', $contextData);
renderPartial('operasi/partials/tab_kekuatan', $contextData);
renderPartial('operasi/partials/tab_bra', $contextData);
renderPartial('operasi/partials/tab_gis', $contextData);
renderPartial('operasi/partials/tab_cetak', $contextData);
renderPartial('operasi/partials/modals', $contextData);
?>

<script>
window.__OPS_DETAIL_CONFIG__ = <?= jsonSafe([
    'idOperasi' => (int)$operasi['id_operasi'],
    'namaOperasi' => $operasi['nama_operasi'] ?? '',
    'activeTab' => $activeTab ?? 'overview',
    'lkpLat' => $lkpLat ?? null,
    'lkpLng' => $lkpLng ?? null,
    'hasLkpCoords' => $hasLkpCoords ?? false,
    'gisBundle' => $gisBundle ?? [],
    'opsData' => [
        'id_operasi' => (int)$operasi['id_operasi'],
        'nama_operasi' => $operasi['nama_operasi'],
        'status' => $operasi['status'],
        'is_open' => $operasi['status'] === 'OPEN',
        'can_delete' => AuthHelper::canDelete(),
        'waktu_berangkat' => $operasi['waktu_berangkat'],
        'waktu_lapor' => $operasi['waktu_lapor'],
        'waktu_kejadian' => $operasi['waktu_kejadian'],
        'waktu_mulai_operasi' => $waktuMulaiOps,
        'tgl_mulai_operasi' => $tglMulaiOps,
        'waktu_reopen' => $operasi['waktu_reopen'] ?? null,
        'hari_sebelum_reopen' => $operasi['hari_sebelum_reopen'] ?? null,
        'detail_korban' => $operasi['korban'] ?? [],
        'detail_unsur' => $operasi['detail_unsur'] ?? [],
        'detail_alut' => $operasi['detail_alut'] ?? [],
        'renops' => $operasi['renops'] ?? [],
        'laporan' => $operasi['laporan_harian'] ?? []
    ]
]) ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/pages/operasi_detail.js?v=<?= APP_VERSION ?>"></script>
