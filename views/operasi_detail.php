<?php
/**
 * QR-SAR - Detail Operasi Hub View (Redesigned Modern UI/UX)
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
$waktuMulaiOps = !empty($operasi['waktu_berangkat']) ? $operasi['waktu_berangkat'] : (!empty($operasi['waktu_lapor']) ? $operasi['waktu_lapor'] : (!empty($operasi['waktu_kejadian']) ? $operasi['waktu_kejadian'] : date('Y-m-d H:i:s')));
$tglMulaiOps = date('Y-m-d', strtotime($waktuMulaiOps));
$hariKe = TemplateHelper::hitungHariKe($waktuMulaiOps);
$statsKorban = $operasi['stats_korban'] ?? ['total' => 0, 's' => 0, 'md' => 0, 'dp' => 0];
$tglKejadian = TemplateHelper::formatTanggalIndo($operasi['waktu_kejadian']);
$jamKejadian = TemplateHelper::formatJamWITA($operasi['waktu_kejadian']);
$tglBerangkat = TemplateHelper::formatTanggalIndo($operasi['waktu_berangkat'] ?: $waktuMulaiOps);
$jamBerangkat = TemplateHelper::formatJamWITA($operasi['waktu_berangkat'] ?: $waktuMulaiOps);
$tglSelesai = !empty($operasi['waktu_selesai']) ? TemplateHelper::formatTanggalIndo($operasi['waktu_selesai']) : null;
$jamSelesai = !empty($operasi['waktu_selesai']) ? TemplateHelper::formatJamWITA($operasi['waktu_selesai']) : null;

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
$masterAlut = MasterModel::getAlut();
$masterTim = MasterModel::getTim();

// Coordinates
$lkpCoords = GeoHelper::parseCoordinates($operasi['koordinat'] ?? '');
$hasLkpCoords = !empty($lkpCoords) && isset($lkpCoords['lat']) && isset($lkpCoords['lng']);
$lkpLat = $hasLkpCoords ? (float)$lkpCoords['lat'] : -4.037694;
$lkpLng = $hasLkpCoords ? (float)$lkpCoords['lng'] : 122.486694;

$countRenops = count($operasi['renops'] ?? []);
$countSitrep = count($operasi['laporan_harian'] ?? []);
$countKorban = count($operasi['korban'] ?? []);
$countBra = count($operasi['bra_sar'] ?? []);
?>

<!-- ======================================================== -->
<!-- TOP BREADCRUMB & CONTEXT HEADER -->
<!-- ======================================================== -->
<div class="flex items-center justify-between gap-3 mb-4">
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

    <?php if (AuthHelper::isAdmin()): ?>
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
                    OPERASI BERJALAN
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold font-mono tracking-wider bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    OPERASI SELESAI
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
        <div class="flex flex-wrap items-center gap-2 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100 dark:border-slate-800">
            <a href="<?= BASE_URL ?>/gis.php?id=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-semibold text-xs shadow-xs transition" title="Buka Peta Taktis GIS">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                <span>Peta GIS</span>
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
<div class="tabs-header border-b border-slate-200 dark:border-slate-800 mb-6 flex overflow-x-auto">
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
<!-- SUB-HALAMAN 1: HIRARKI DOKUMEN OPERASI SAR -->
<!-- ======================================================== -->
<div id="tab-content-hierarchy" class="tab-pane space-y-4" style="display: <?= $activeTab === 'hierarchy' ? 'block' : 'none' ?>;">
    <div class="flex items-center justify-between gap-3 mb-2">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z"/></svg>
            <span>Alur Kronologis Dokumen Operasi SAR &bull; <?= count($timeline) ?> Dokumen Terbit</span>
        </h3>
    </div>

    <div class="relative pl-6 sm:pl-8 space-y-3.5 before:absolute before:left-3 sm:before:left-4 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200 dark:before:bg-slate-800">
        <?php foreach ($timeline as $idx => $doc): 
            $docType = $doc['type'];
            $isAwal = $docType === 'LAPORAN_AWAL';
            $isRenops = $docType === 'RENOPS';
            $isPenutupan = $docType === 'PENUTUPAN';
            $isLapbang = $docType === 'LAPBANG';

            $nodeColor = $isRenops ? 'bg-orange-500 text-white' : ($isPenutupan ? 'bg-red-500 text-white' : ($isLapbang ? 'bg-emerald-600 text-white' : 'bg-blue-600 text-white'));
            $badgeClass = $isRenops ? 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20' : ($isPenutupan ? 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20' : ($isLapbang ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20' : 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20'));
        ?>
        <div class="relative">
            <!-- Node Pin -->
            <div class="absolute -left-6 sm:-left-8 top-1/2 -translate-y-1/2 w-6 h-6 sm:w-7 sm:h-7 rounded-full <?= $nodeColor ?> flex items-center justify-center font-mono font-black text-[11px] shadow-sm ring-4 ring-white dark:ring-slate-900 z-10">
                <?= $idx + 1 ?>
            </div>

            <!-- Item Card -->
            <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-slate-300 dark:hover:border-slate-700 transition">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-lg font-mono text-[11px] font-bold border <?= $badgeClass ?>">
                            <?= e($docType) ?> • <?= e($doc['hari_ke']) ?>
                        </span>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">
                            <?= e($doc['label']) ?>
                        </h4>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-1">
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                            <span><?= TemplateHelper::formatTanggalIndo($doc['waktu']) ?> (<?= TemplateHelper::formatJamWITA($doc['waktu']) ?> WITA)</span>
                        </span>
                        <?php if (!empty($doc['cuaca'])): ?>
                        <span class="text-slate-300 dark:text-slate-700 hidden sm:inline">&bull;</span>
                        <span class="inline-flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z"/></svg>
                            <span><?= e($doc['cuaca']) ?></span>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="shrink-0 flex items-center gap-2">
                    <?php if ($isAwal): ?>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-awal')">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <span>Naskah WA</span>
                    </button>
                    <?php elseif ($isRenops): ?>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/25 font-semibold text-xs transition cursor-pointer" onclick="switchTab('renops')">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                        <span>Buka RENOPS</span>
                    </button>
                    <?php elseif ($isLapbang || $isPenutupan): ?>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-white border border-emerald-500/25 font-semibold text-xs transition cursor-pointer" onclick="switchTab('sitrep')">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <span>Buka SITREP</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ======================================================== -->
<!-- SUB-HALAMAN 2: RENCANA OPERASI (RENOPS) -->
<!-- ======================================================== -->
<div id="tab-content-renops" class="tab-pane space-y-4" style="display: <?= $activeTab === 'renops' ? 'block' : 'none' ?>;">

    <div class="flex items-center justify-between gap-3 mb-2">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
            <span>Daftar Rencana Operasi SAR (RENOPS) &bull; <?= $countRenops ?> Dokumen</span>
        </h3>
        <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition cursor-pointer">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat RENOPS H.<?= $nextRenops['target_day_number'] ?? substr($hariKe, 2) ?></span>
        </a>
        <?php endif; ?>
    </div>

    <?php if (!$isOpen): ?>
    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            <div>
                <h4 class="font-bold text-xs text-amber-900 dark:text-amber-100">Operasi Telah Ditutup (CLOSED)</h4>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">Penambahan RENOPS dinonaktifkan. Buka kembali operasi jika ada pembaharuan.</p>
            </div>
        </div>
        <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-xs transition whitespace-nowrap cursor-pointer">
            Buka Kembali Operasi
        </button>
    </div>
    <?php endif; ?>

    <?php if (empty($operasi['renops'])): ?>
    <div class="p-8 sm:p-12 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs text-center">
        <div class="w-12 h-12 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Belum Ada Rencana Operasi (RENOPS)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Lembar RENOPS memuat pembagian Search and Rescue Unit (SRU), sektor koordinat, dan pola pencarian per hari operasi.
        </p>
        <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat RENOPS H.<?= $nextRenops['target_day_number'] ?? substr($hariKe, 2) ?></span>
        </a>
        <?php endif; ?>
    </div>
    <?php else: foreach ($operasi['renops'] as $ren): 
        $snapUnsur = !empty($ren['detail_unsur']) && is_array($ren['detail_unsur']) ? $ren['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
        $snapAlut = !empty($ren['detail_alut']) && is_array($ren['detail_alut']) ? $ren['detail_alut'] : ($operasi['detail_alut'] ?? []);
        $totalSnapPers = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 0), $snapUnsur));
        $totalSnapAlut = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 0), $snapAlut));
        $renWa = $ren['full_text_wa'] ?? '';
        if (empty($renWa) || strpos($renWa, 'FILE AKTIF') === false) {
            $renWa = TemplateHelper::generateRenopsWA($operasi, $ren, $ren['srus'] ?? [], $operasi['korban'] ?? [], $snapUnsur, $snapAlut);
        }
    ?>
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden transition-all">
        <!-- Renops Header -->
        <div class="p-3.5 sm:p-4 border-b bg-slate-50/70 dark:bg-slate-800/40 border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-lg font-mono font-black text-xs bg-orange-600 text-white shadow-xs">
                    <?= e($ren['hari_ke']) ?>
                </span>
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200 inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                    <span>Rencana: <?= TemplateHelper::formatTanggalIndo($ren['tanggal_laporan']) ?></span>
                </span>
            </div>

            <!-- Action Toolbar for Renops -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($renWa), ENT_QUOTES) ?>, "Naskah RENOPS WhatsApp Berhasil Disalin!")'>
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    <span>Salin WA</span>
                </button>

                <?php if ($isOpen): ?>
                <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>&id_renops=<?= $ren['id_renops'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                    <span>Edit</span>
                </a>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&from=renops&id_renops=<?= $ren['id_renops'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/20 font-semibold text-xs transition cursor-pointer" title="Buat Berita Radio dari RENOPS ini">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                    <span>BRA Renops</span>
                </a>

                <?php if ($isOpen): ?>
                <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer" onclick="deleteRenops(<?= $ren['id_renops'] ?>)" title="Hapus Lembar Renops">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Renops Body -->
        <div class="p-4 sm:p-5 space-y-4">
            <!-- Timetable & Cuaca Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Waktu Briefing</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 font-mono"><?= TemplateHelper::formatTW($ren['waktu_briefing']) ?></span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Mulai Operasi</span>
                    <span class="font-bold text-orange-600 dark:text-orange-400 font-mono"><?= TemplateHelper::formatTW($ren['waktu_mulai_ops']) ?></span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Prakiraan Cuaca BMKG</span>
                    <span class="font-bold text-slate-800 dark:text-slate-200 truncate block"><?= e($ren['cuaca']) ?></span>
                </div>
            </div>

            <!-- SRU Table -->
            <div>
                <h4 class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                    <span>Pembagian Sektor & Search and Rescue Unit (SRU):</span>
                </h4>
                <div class="table-responsive rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <table class="table text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-800/50">
                            <tr>
                                <th style="width: 25%;">Nama SRU</th>
                                <th style="width: 45%;">Koordinat Sektor & Tugas</th>
                                <th style="width: 15%;">Luas Area</th>
                                <th style="width: 15%;">Pola Pencarian</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($ren['srus'])): ?>
                            <tr><td colspan="4" class="text-slate-400 text-center py-3">Tidak ada pembagian SRU tersimpan</td></tr>
                            <?php else: foreach ($ren['srus'] as $s): ?>
                            <tr>
                                <td class="font-bold text-orange-600 dark:text-orange-400"><?= e($s['nama_sru']) ?></td>
                                <td class="font-mono text-xs whitespace-pre-wrap"><?= e($s['koordinat_area']) ?></td>
                                <td class="font-semibold text-xs"><?= e($s['luas_area']) ?></td>
                                <td><span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20"><?= e($s['pola_pencarian']) ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Snapshot Potensi & Alut Renops -->
            <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                        <span>Potensi SAR & Armada Terlibat (Snapshot <?= e($ren['hari_ke']) ?>):</span>
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-mono font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                        <?= $totalSnapPers ?> Personel &bull; <?= $totalSnapAlut ?> Unit Alut
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Unsur SAR Terlibat:</span>
                        <div class="space-y-1">
                            <?php if (empty($snapUnsur)): ?>
                            <span class="text-slate-400">-</span>
                            <?php else: foreach ($snapUnsur as $u): ?>
                            <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                <span>&bull; <?= e($u['nama_unsur']) ?></span>
                                <strong class="text-purple-600 dark:text-purple-400 font-mono"><?= (int)($u['jumlah_personil'] ?? 1) ?> Org</strong>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Armada Alut & Palsar:</span>
                        <div class="space-y-1">
                            <?php if (empty($snapAlut)): ?>
                            <span class="text-slate-400">-</span>
                            <?php else: foreach ($snapAlut as $a): ?>
                            <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                <span>&bull; <?= e($a['nama_alut']) ?></span>
                                <strong class="text-emerald-600 dark:text-emerald-400 font-mono"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></strong>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Text Format Accordion -->
            <details class="group rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 p-3 transition">
                <summary class="flex items-center justify-between cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300 select-none">
                    <span class="flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                        <span>Format Naskah WhatsApp Siap Kirim (<?= e($ren['hari_ke']) ?>)</span>
                    </span>
                    <span class="text-slate-400 group-open:rotate-180 transition-transform text-[11px] font-mono">&dtrif;</span>
                </summary>
                <div class="mt-2.5 pt-2.5 border-t border-slate-200 dark:border-slate-800">
                    <pre class="font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed select-all max-h-72 overflow-y-auto"><?= e($renWa) ?></pre>
                </div>
            </details>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- ======================================================== -->
<!-- SUB-HALAMAN 3: LAPORAN PERKEMBANGAN & PENUTUPAN (SITREP) -->
<!-- ======================================================== -->
<div id="tab-content-sitrep" class="tab-pane space-y-4" style="display: <?= ($activeTab === 'sitrep' || $activeTab === 'laporan') ? 'block' : 'none' ?>;">

    <div class="flex items-center justify-between gap-3 mb-2">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            <span>Daftar Laporan Perkembangan (LAPBANG / SITREP) &bull; <?= $countSitrep ?> Dokumen</span>
        </h3>
        <div class="flex items-center gap-2">
            <?php if ($isOpen): ?>
            <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-xs transition cursor-pointer">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span>+ Terbitkan SITREP <?= e($hariKe) ?></span>
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&kategori=SAR_UMUM" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-white border border-slate-200 dark:border-slate-700 font-semibold text-xs transition" title="Buat Berita Radio dari Laporan Perkembangan">
                <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                <span>BRA Operasi SAR</span>
            </a>
        </div>
    </div>

    <?php if (!$isOpen): ?>
    <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2.5">
            <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
            <div>
                <h4 class="font-bold text-xs text-amber-900 dark:text-amber-100">Operasi Telah Ditutup (CLOSED)</h4>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">Penambahan SITREP dinonaktifkan demi integritas arsip.</p>
            </div>
        </div>
        <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-xs transition whitespace-nowrap cursor-pointer">
            Buka Kembali Operasi
        </button>
    </div>
    <?php endif; ?>

    <!-- LIST FEED SITREP (BERSIH TANPA KARTU KPI REDUNDAN) -->
    <?php if (empty($operasi['laporan_harian'])): ?>
    <div class="p-8 sm:p-12 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs text-center">
        <div class="w-12 h-12 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Belum Ada Laporan Perkembangan (SITREP)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Laporan perkembangan memuat evaluasi hasil pencarian harian tim gabungan dan debriefing lapangan.
        </p>
        <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-xs transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Terbitkan SITREP <?= e($hariKe) ?></span>
        </a>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($operasi['laporan_harian'] as $idx => $lap): 
            $isPenutupan = ($lap['jenis_laporan'] ?? '') === 'PENUTUPAN';
            $snapUnsur = !empty($lap['detail_unsur']) && is_array($lap['detail_unsur']) ? $lap['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
            $snapAlut = !empty($lap['detail_alut']) && is_array($lap['detail_alut']) ? $lap['detail_alut'] : ($operasi['detail_alut'] ?? []);
            $totalSnapPers = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 0), $snapUnsur));
            $totalSnapAlut = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 0), $snapAlut));
            $lapWa = $lap['full_text_wa'] ?? '';
            if (empty($lapWa) || strpos($lapWa, 'FILE AKTIF') === false) {
                $lapWa = TemplateHelper::generateLapbangWA($operasi, $lap, $operasi['korban'] ?? [], $snapUnsur, $snapAlut, $isPenutupan);
            }
        ?>
        <div class="rounded-2xl bg-white dark:bg-slate-900 border <?= $isPenutupan ? 'border-red-500/30 dark:border-red-900/50' : 'border-slate-200 dark:border-slate-800' ?> shadow-xs overflow-hidden transition-all">
            
            <!-- Sitrep Header -->
            <div class="p-3.5 sm:p-4 border-b <?= $isPenutupan ? 'bg-red-50/40 dark:bg-red-950/20 border-red-200/50 dark:border-red-900/40' : 'bg-slate-50/70 dark:bg-slate-800/40 border-slate-100 dark:border-slate-800' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-lg font-mono font-black text-xs <?= $isPenutupan ? 'bg-red-500 text-white shadow-xs' : 'bg-blue-600 text-white shadow-xs' ?>">
                        <?= $isPenutupan ? 'PENUTUPAN OPERASI' : "LAPBANG " . e($lap['hari_ke']) ?>
                    </span>
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 inline-flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                        <span><?= TemplateHelper::formatTanggalIndo($lap['waktu_laporan']) ?></span>
                        <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                        <span class="font-mono text-blue-600 dark:text-blue-400"><?= TemplateHelper::formatJamWITA($lap['waktu_laporan']) ?> WITA</span>
                    </span>
                </div>

                <!-- Action Toolbar -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button type="button" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($lapWa), ENT_QUOTES) ?>, "Naskah SITREP WhatsApp Berhasil Disalin!")'>
                        <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                        <span>Salin WA</span>
                    </button>

                    <?php if ($isOpen): ?>
                    <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>&id_laporan=<?= $lap['id_laporan'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer">
                        <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                        <span>Edit</span>
                    </a>
                    <?php endif; ?>

                    <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&from=sitrep&id_laporan=<?= $lap['id_laporan'] ?>" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/20 font-semibold text-xs transition cursor-pointer" title="Buat Berita Radio dari laporan ini">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                        <span>BRA Sitrep</span>
                    </a>

                    <?php if ($isOpen): ?>
                    <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer" onclick="deleteLaporan(<?= $lap['id_laporan'] ?>)" title="Hapus Laporan Perkembangan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sitrep Body -->
            <div class="p-4 sm:p-5 space-y-4">
                <!-- Hasil Pencarian -->
                <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200/70 dark:border-slate-800 text-xs sm:text-sm leading-relaxed text-slate-800 dark:text-slate-200">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                        <span class="font-bold text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">Hasil Pencarian & Tindakan Lapangan:</span>
                    </div>
                    <div class="whitespace-pre-wrap font-sans pl-1">
                        <?= nl2br(e($lap['isi_laporan'])) ?>
                    </div>
                    
                    <?php if (!empty($lap['waktu_debrief'])): ?>
                    <div class="mt-3 p-3 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/60 flex items-center gap-2 text-red-600 dark:text-red-400 font-bold text-xs">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                        <span>Debriefing Resmi Penutupan: <?= TemplateHelper::formatTanggalIndo($lap['waktu_debrief']) ?> Pukul <?= TemplateHelper::formatJamWITA($lap['waktu_debrief']) ?> WITA</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Snapshot Unsur & Alut -->
                <div class="p-3.5 sm:p-4 rounded-xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-200/70 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-2.5">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                            <span>Potensi & Alut Terlibat (Snapshot <?= e($lap['hari_ke']) ?>):</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full font-mono text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                            <?= $totalSnapPers ?> Personel &bull; <?= $totalSnapAlut ?> Unit Alut
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Potensi SAR Gabungan:</span>
                            <div class="space-y-1">
                                <?php if (empty($snapUnsur)): ?>
                                <span class="text-slate-400 text-xs italic">Tidak ada catatan unsur</span>
                                <?php else: foreach ($snapUnsur as $u): ?>
                                <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">&bull; <?= e($u['nama_unsur']) ?></span>
                                    <span class="font-mono font-bold text-purple-600 dark:text-purple-400"><?= (int)($u['jumlah_personil'] ?? 1) ?> Org</span>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Armada Alut & Palsar:</span>
                            <div class="space-y-1">
                                <?php if (empty($snapAlut)): ?>
                                <span class="text-slate-400 text-xs italic">Tidak ada catatan alut</span>
                                <?php else: foreach ($snapAlut as $a): ?>
                                <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/70 dark:border-slate-800">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">&bull; <?= e($a['nama_alut']) ?></span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></span>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collapsible WhatsApp Output -->
                <details class="group rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                    <summary class="px-3.5 py-2.5 text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white cursor-pointer flex items-center justify-between bg-slate-50/70 dark:bg-slate-800/30 transition">
                        <span class="flex items-center gap-2">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                            <span>Format Naskah WhatsApp Resmi</span>
                        </span>
                        <span class="text-[11px] font-mono text-slate-400 group-open:rotate-180 transition-transform">&dtrif;</span>
                    </summary>
                    <div class="p-3.5 border-t border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950">
                        <pre class="font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed select-all max-h-72 overflow-y-auto"><?= e($lapWa) ?></pre>
                    </div>
                </details>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- SUB-HALAMAN 4: DATA KORBAN & KRONOLOGI -->
<!-- ======================================================== -->
<div id="tab-content-korban" class="tab-pane space-y-5" style="display: <?= $activeTab === 'korban' ? 'block' : 'none' ?>;">
    
    <!-- STATS SUMMARY 4 MINI CARDS -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Total Korban</span>
                <span class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white font-mono"><?= $countKorban ?></span>
                <span class="text-[11px] text-slate-400 block">Orang</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
            </div>
        </div>

        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Selamat (S)</span>
                <span class="text-xl sm:text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono"><?= (int)($statsKorban['s'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400 block">Dievakuasi</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-red-600 dark:text-red-400 uppercase tracking-wider block">Meninggal (MD)</span>
                <span class="text-xl sm:text-2xl font-black text-red-600 dark:text-red-400 font-mono"><?= (int)($statsKorban['md'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400 block">Ditemukan</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-red-500/10 text-red-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
            </div>
        </div>

        <div class="p-3.5 sm:p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex items-center justify-between">
            <div>
                <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider block">Pencarian (DP)</span>
                <span class="text-xl sm:text-2xl font-black text-amber-600 dark:text-amber-400 font-mono"><?= (int)($statsKorban['dp'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400 block">Target Aktif</span>
            </div>
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN CONTENT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Left: Victims Table (7 cols) -->
        <div class="lg:col-span-7 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden flex flex-col">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Identitas Korban</h3>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                    <?= $countKorban ?> Orang
                </span>
            </div>

            <?php if (empty($operasi['korban'])): ?>
            <div class="p-8 text-center text-slate-400 text-xs">
                <p>Tidak ada data korban tercatat.</p>
            </div>
            <?php else: ?>
            <div class="overflow-x-auto flex-1">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-800/40 text-slate-500 uppercase font-bold text-[10px] tracking-wider">
                            <th class="py-2.5 px-3.5 w-10 text-center">No</th>
                            <th class="py-2.5 px-3.5">Nama Korban</th>
                            <th class="py-2.5 px-3.5">Umur / JK</th>
                            <th class="py-2.5 px-3.5">Domisili</th>
                            <th class="py-2.5 px-3.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                        <?php foreach ($operasi['korban'] as $idx => $k): 
                            $st = $k['status_korban'];
                            $stClass = $st === 'S' 
                                ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20' 
                                : ($st === 'MD' 
                                    ? 'bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20' 
                                    : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20');
                        ?>
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-2.5 px-3.5 text-center font-bold font-mono text-slate-400"><?= $idx + 1 ?></td>
                            <td class="py-2.5 px-3.5 font-bold text-slate-900 dark:text-white">
                                <?= e($k['nama_korban']) ?>
                            </td>
                            <td class="py-2.5 px-3.5 font-medium"><?= e($k['umur'] ?: '-') ?> (<?= e($k['jk'] ?: '-') ?>)</td>
                            <td class="py-2.5 px-3.5 text-slate-500 text-[11px]"><?= e($k['alamat'] ?: '-') ?></td>
                            <td class="py-2.5 px-3.5 text-center">
                                <span class="px-2 py-0.5 rounded-lg font-bold font-mono text-[10px] <?= $stClass ?>">
                                    <?= TemplateHelper::getLabelStatusKorban($st) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Kronologi & Pelapor (5 cols) -->
        <div class="lg:col-span-5 space-y-5">
            <!-- Kronologi Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-2.5">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Kronologi Kejadian</h3>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono"><?= $tglKejadian ?></span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/40 border border-slate-100 dark:border-slate-800 text-xs leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-wrap font-sans">
                    <?= e($operasi['kronologi'] ?: 'Kronologi belum diinput.') ?>
                </div>
            </div>

            <!-- Pelapor & Kondisi Lapangan Card -->
            <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3">
                <div class="flex items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-2.5">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <h3 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">Informasi Pelapor & Cuaca</h3>
                </div>

                <div class="grid grid-cols-2 gap-2.5 text-xs">
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Pelapor</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e($operasi['nama_pelapor'] ?: '-') ?></strong>
                        <span class="text-[10px] text-slate-500">(<?= e($operasi['hubungan_pelapor'] ?: '-') ?>)</span>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Kontak Pelapor</span>
                        <strong class="text-slate-900 dark:text-white font-mono block truncate"><?= e($operasi['no_telp_pelapor'] ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Kondisi Cuaca</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e($operasi['cuaca'] ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Tinggi Gelombang</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e($operasi['gelombang'] ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Angin</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e($operasi['angin'] ?: '-') ?></strong>
                    </div>
                    <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-0.5">Faktor Penghambat</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e($operasi['faktor_penghambat'] ?: 'Nihil') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- SUB-HALAMAN 5: KEKUATAN UNSUR & ALUT TERKINI -->
<!-- ======================================================== -->
<div id="tab-content-kekuatan" class="tab-pane space-y-5" style="display: <?= $activeTab === 'kekuatan' ? 'block' : 'none' ?>;">
    
    <?php
        $totalPersKekuatan = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $operasi['detail_unsur'] ?? []));
        $totalAlutKekuatan = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $operasi['detail_alut'] ?? []));
    ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <!-- Left: Unsur SAR Terlibat -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Unsur SAR Terlibat (Potensi Gabungan)</h3>
                        <p class="text-[11px] text-slate-400">Instansi dan potensi yang dikerahkan</p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                    Total: <?= $totalPersKekuatan ?> Personel
                </span>
            </div>

            <?php if (!empty($operasi['detail_unsur'])): ?>
            <div class="space-y-1.5">
                <?php foreach ($operasi['detail_unsur'] as $u): ?>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs">
                    <span class="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                        <span><?= e($u['nama_unsur']) ?></span>
                    </span>
                    <span class="px-2 py-0.5 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 font-mono font-bold text-xs">
                        <?= (int)($u['jumlah_personil'] ?? 1) ?> Org
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                <?= e($operasi['unsur_terlibat'] ?: '• Tim Rescue KPP Kendari') ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Alut & Peralatan SAR (Palsar) -->
        <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3.5">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"/></svg>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Alut & Peralatan SAR</h3>
                        <p class="text-[11px] text-slate-400">Armada dan sarana operasi</p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-orange-500/10 text-orange-600 dark:text-orange-400">
                    Total: <?= $totalAlutKekuatan ?> Unit
                </span>
            </div>

            <?php if (!empty($operasi['detail_alut'])): ?>
            <div class="space-y-1.5">
                <?php foreach ($operasi['detail_alut'] as $a): ?>
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/80 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs">
                    <span class="font-medium text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>
                        <span><?= e($a['nama_alut']) ?></span>
                    </span>
                    <span class="px-2 py-0.5 rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-400 font-mono font-bold text-xs">
                        <?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                <?= e($operasi['alut_palsar'] ?: '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat + Mopel') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- SUB-HALAMAN 6: BERITA RADIO SAR (BRA) -->
<!-- ======================================================== -->
<div id="tab-content-bra" class="tab-pane space-y-4" style="display: <?= $activeTab === 'bra' ? 'block' : 'none' ?>;">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                <span>Berita Radio SAR (BRA) &bull; <?= $countBra ?> Dokumen</span>
            </h3>
        </div>
        <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&from=laporan_awal" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition cursor-pointer shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat BRA SAR Baru</span>
        </a>
    </div>

    <?php if (empty($operasi['bra_sar'])): ?>
    <div class="p-8 sm:p-12 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs text-center">
        <div class="w-12 h-12 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center mx-auto mb-3">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
        </div>
        <h3 class="text-sm font-bold text-slate-900 dark:text-white mb-1">Belum Ada Berita Radio SAR (BRA)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Terbitkan naskah telegram resmi Basarnas untuk dikirimkan ke Kabasarnas dan jajaran pimpinan Senkom.
        </p>
        <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&from=laporan_awal" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xs transition">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
            <span>+ Buat BRA SAR Baru</span>
        </a>
    </div>
    <?php else: foreach ($operasi['bra_sar'] as $bra): ?>
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-3.5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-lg bg-orange-500 text-white font-mono font-bold text-xs">
                    <?= in_array(strtoupper($bra['kategori']), ['SAR_UMUM', 'OPERASI_SAR']) ? 'BRA SAR OPERASI SAR' : ($bra['kategori'] === 'PERGERAKAN_KAPAL' ? 'BRA KAPAL' : 'BRA MAPEL') ?>
                </span>
                <span class="font-mono font-black text-sm text-slate-900 dark:text-white">
                    NO: <?= e($bra['no_bra']) ?>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Lihat Dokumen</span>
                </a>
                <a href="<?= BASE_URL ?>/bra_form.php?id=<?= $bra['id_bra'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487zm0 0L19.5 7.125"/></svg>
                    <span>Edit</span>
                </a>
            </div>
        </div>

        <div class="text-xs">
            <strong class="text-slate-900 dark:text-white">PERIHAL:</strong> <span class="text-slate-600 dark:text-slate-400"><?= e($bra['perihal'] ?: '-') ?></span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] p-2.5 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono">
            <div>TWU: <strong class="text-slate-900 dark:text-white"><?= e($bra['twu']) ?></strong></div>
            <div>TWB: <strong class="text-slate-900 dark:text-white"><?= e($bra['twb']) ?></strong></div>
            <div>Jml Kata: <strong class="text-slate-900 dark:text-white"><?= e($bra['jumlah_kata']) ?></strong></div>
            <div>SMC: <strong class="text-slate-900 dark:text-white"><?= e($bra['nama_smc']) ?></strong></div>
        </div>

        <?php if (!empty($bra['sections'])): ?>
        <div class="p-3 rounded-xl bg-slate-50/70 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800 font-mono text-[11px] space-y-1">
            <div class="font-bold text-orange-600 dark:text-orange-400 uppercase text-[10px] mb-1">Naskah Telegram (Preview):</div>
            <?php foreach (array_slice($bra['sections'], 0, 4) as $sec): ?>
                <div class="text-slate-700 dark:text-slate-300">
                    <span class="font-bold"><?= e($sec['kode']) ?> :</span>
                    <span class="text-slate-500"><?= e(mb_strimwidth($sec['isi'], 0, 140, '...')) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- ======================================================== -->
<!-- SUB-HALAMAN 7: PETA TAKTIS OPERASI (GIS) -->
<!-- ======================================================== -->
<div id="tab-content-gis" class="tab-pane space-y-4" style="display: <?= $activeTab === 'gis' ? 'block' : 'none' ?>;">
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
                    <span>Peta Taktis Operasi SAR (Tactical GIS)</span>
                </h3>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                    LKP: <span class="font-bold text-slate-700 dark:text-slate-300"><?= e($operasi['koordinat']) ?></span> &bull; Jarak Kansar: <strong class="text-orange-600"><?= e($operasi['jarak']) ?></strong>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/gis.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition">
                <span>Buka GIS Penuh</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
            </a>
        </div>
        <div id="map-detail-ops" class="w-full" style="height: 500px;"></div>
    </div>
</div>

<!-- ======================================================== -->
<!-- SUB-HALAMAN 8: CETAK & BERKAS RESMI -->
<!-- ======================================================== -->
<div id="tab-content-cetak" class="tab-pane space-y-4" style="display: <?= $activeTab === 'cetak' ? 'block' : 'none' ?>;">
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs space-y-4">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                <span>Cetak Dokumen Resmi Operasi SAR</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pilih berkas resmi yang ingin dicetak atau diekspor ke PDF:</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <!-- Card 1: Laporan Awal -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-lg bg-orange-500/10 text-orange-600 flex items-center justify-center font-bold text-xs mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">1. Berkas Laporan Awal</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Format standar informasi kejadian dan pemberangkatan tim SAR.</p>
                </div>
                <button type="button" class="w-full py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5" onclick="printBerkas('laporan_awal')">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak Dokumen</span>
                </button>
            </div>

            <!-- Card 2: RENOPS -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-xs mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">2. Rencana Operasi SAR</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Lembar RENOPS harian lengkap dengan sektor & SRU.</p>
                </div>
                <button type="button" class="w-full py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5" onclick="printBerkas('renops')">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak Lembar RENOPS</span>
                </button>
            </div>

            <!-- Card 3: BRA SAR -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-lg bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-xs mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">3. Berita Radio SAR</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Naskah telegram resmi Basarnas dengan verifikasi QR Digital.</p>
                </div>
                <?php if (!empty($operasi['bra_sar'])): ?>
                <a href="<?= BASE_URL ?>/bra.php?view=<?= $operasi['bra_sar'][0]['id_bra'] ?>" class="w-full py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs text-center transition shadow-xs flex items-center justify-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak Dokumen BRA</span>
                </a>
                <?php else: ?>
                <span class="text-[11px] text-slate-400 text-center py-2">Belum ada BRA terbit</span>
                <?php endif; ?>
            </div>

            <!-- Card 4: Penutupan -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-lg bg-red-500/10 text-red-600 flex items-center justify-center font-bold text-xs mb-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    </div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">4. Berita Acara Penutupan</h4>
                    <p class="text-[11px] text-slate-500 mt-0.5 leading-relaxed">Debriefing usul penutupan operasi dan pelepasan unsur SAR.</p>
                </div>
                <button type="button" class="w-full py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-xs transition shadow-xs cursor-pointer flex items-center justify-center gap-1.5" onclick="printBerkas('penutupan')">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
                    <span>Cetak Berita Acara</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: VIEW WA LAPORAN AWAL -->
<!-- ======================================================== -->
<div id="modal-view-wa-awal" class="modal-overlay">
    <div class="modal-container" style="max-width: 760px; width: 95%;">
        <div class="modal-header flex items-center justify-between border-b border-slate-100 dark:border-slate-800 p-4">
            <div>
                <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                    <span>Naskah Laporan Awal WhatsApp Resmi</span>
                </h3>
                <p class="text-[11px] text-slate-400 mt-0.5"><?= e($operasi['nama_operasi']) ?></p>
            </div>
            <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer" onclick="closeModal('modal-view-wa-awal')">✕</button>
        </div>
        <?php
            $awalWa = $operasi['full_text_wa'] ?? '';
            if (empty($awalWa) || strpos($awalWa, 'FILE AKTIF') === false) {
                $awalWa = TemplateHelper::generateLaporanAwalWA($operasi, $operasi['korban'] ?? [], $operasi['detail_unsur'] ?? [], $operasi['detail_alut'] ?? []);
            }
        ?>
        <div class="modal-body p-4">
            <pre id="modal-wa-awal-text" class="font-mono p-4 rounded-xl text-xs bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-800 leading-relaxed max-h-[55vh] overflow-y-auto select-all whitespace-pre-wrap"><?= e($awalWa) ?></pre>
        </div>
        <div class="modal-footer flex items-center justify-between p-4 border-t border-slate-100 dark:border-slate-800">
            <span class="text-[11px] text-slate-400">Format Resmi Basarnas FILE AKTIF</span>
            <div class="flex items-center gap-2">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-view-wa-awal')">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm inline-flex items-center gap-1.5" onclick="copyDetailWA()">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184"/></svg>
                    <span>Salin Naskah</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ======================================================== -->
<!-- MODAL: CLOSE OPERATION DETAIL -->
<!-- ======================================================== -->
<div id="modal-close-ops-detail" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 class="font-bold text-sm text-red-600 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <span>Tutup Operasi SAR Resmi</span>
            </h3>
            <button type="button" class="btn btn-secondary btn-sm" data-close-modal>✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/operasi.php?action=close">
            <?= csrfInput() ?>
            <input type="hidden" name="id_operasi" value="<?= $id ?>">
            <div class="modal-body space-y-3">
                <p class="font-bold text-xs text-slate-800 dark:text-slate-200">
                    <?= e($operasi['nama_operasi']) ?>
                </p>
                <div class="form-group">
                    <label class="form-label text-xs">Waktu Debriefing Selesai (WITA):</label>
                    <input type="datetime-local" name="waktu_selesai" class="form-control text-xs" required value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 text-xs text-slate-500">
                    Seluruh unsur SAR yang terlibat diusulkan kembali ke kesatuannya masing-masing dengan ucapan terima kasih. Status operasi akan menjadi <strong>CLOSED</strong>.
                </div>
            </div>
            <div class="modal-footer flex items-center justify-end gap-2">
                <button type="button" class="btn btn-secondary text-xs" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-danger text-xs">Tutup Operasi Sekarang</button>
            </div>
        </form>
    </div>
</div>

<script>
// Server Operations Master State
const OPS_DATA = <?= json_encode([
    'id_operasi' => (int)$operasi['id_operasi'],
    'nama_operasi' => $operasi['nama_operasi'],
    'status' => $operasi['status'],
    'is_open' => $operasi['status'] === 'OPEN',
    'waktu_berangkat' => $operasi['waktu_berangkat'],
    'waktu_lapor' => $operasi['waktu_lapor'],
    'waktu_kejadian' => $operasi['waktu_kejadian'],
    'waktu_mulai_operasi' => $waktuMulaiOps,
    'tgl_mulai_operasi' => $tglMulaiOps,
    'lokasi_kejadian' => $operasi['lokasi_kejadian'],
    'koordinat' => $operasi['koordinat'],
    'jarak' => $operasi['jarak'],
    'cuaca' => $operasi['cuaca'],
    'gelombang' => $operasi['gelombang'],
    'angin' => $operasi['angin'],
    'korban' => $operasi['korban'] ?? [],
    'detail_unsur' => $operasi['detail_unsur'] ?? [],
    'detail_alut' => $operasi['detail_alut'] ?? []
]) ?>;

let detailOpsMap = null;

function switchTab(tabName) {
    let resolvedTab = tabName;
    if (resolvedTab === 'ringkasan') resolvedTab = 'hierarchy';
    if (resolvedTab === 'laporan') resolvedTab = 'sitrep';

    document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    const targetContent = document.getElementById(`tab-content-${resolvedTab}`) || document.getElementById(`tab-content-${tabName}`);
    if (targetContent) targetContent.style.display = 'block';

    const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => 
        b.getAttribute('onclick')?.includes(`'${resolvedTab}'`) || b.getAttribute('onclick')?.includes(`'${tabName}'`)
    );
    if (btn) btn.classList.add('active');

    const url = new URL(window.location);
    url.searchParams.set('tab', resolvedTab);
    window.history.replaceState({}, '', url);

    if (resolvedTab === 'gis') {
        setTimeout(initDetailMap, 200);
    }
}

function initDetailMap() {
    if (detailOpsMap) {
        detailOpsMap.map.invalidateSize();
        return;
    }

    const lat = <?= json_encode($lkpLat) ?>;
    const lng = <?= json_encode($lkpLng) ?>;
    const hasLkp = <?= json_encode($hasLkpCoords) ?>;
    const lkpTitle = <?= json_encode($operasi['nama_operasi']) ?>;

    detailOpsMap = new TacticalGisMap('map-detail-ops', {
        center: [lat, lng],
        zoom: hasLkp ? 11 : 9
    });

    if (hasLkp) {
        detailOpsMap.addLkpMarker(lat, lng, lkpTitle);
        const kansarCoords = [-4.037694, 122.486694];
        L.polyline([kansarCoords, [lat, lng]], {
            color: '#F97316',
            weight: 3,
            dashArray: '6, 6'
        }).addTo(detailOpsMap.layers.operations);
    }

    fetch('<?= BASE_URL ?>/api/sar-units')
        .then(r => r.json())
        .then(d => {
            if (d.success && d.data) detailOpsMap.loadSarUnits(d.data);
        })
        .catch(err => console.log('SAR Units error:', err));

    setTimeout(() => {
        if (detailOpsMap && detailOpsMap.map) {
            detailOpsMap.map.invalidateSize();
        }
    }, 250);
}

document.addEventListener('DOMContentLoaded', () => {
    if ('<?= $activeTab ?>' === 'gis') {
        setTimeout(initDetailMap, 250);
    }
});

function copyDetailWA() {
    const el = document.getElementById('modal-wa-awal-text') || document.getElementById('detail-wa-text');
    const text = el ? (el.value || el.textContent || '').trim() : '';
    copyToClipboard(text, 'Naskah Laporan Awal WhatsApp Berhasil Disalin!');
}

function copyTextToClipboard(text, msg) {
    copyToClipboard(text, msg);
}

function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

async function deleteRenops(id) {
    if (!OPS_DATA.is_open) {
        showToast('Operasi SAR ini telah DITUTUP. Data tidak dapat dihapus.', 'error');
        return;
    }
    if (await confirmAction('Hapus RENOPS?', 'Apakah Anda yakin ingin menghapus lembar Rencana Operasi ini?')) {
        const res = await fetch(`<?= BASE_URL ?>/api/renops/${id}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            showToast('RENOPS berhasil dihapus', 'success');
            setTimeout(() => location.reload(), 600);
        }
    }
}

async function deleteLaporan(id) {
    if (!OPS_DATA.is_open) {
        showToast('Operasi SAR ini telah DITUTUP. Data tidak dapat dihapus.', 'error');
        return;
    }
    if (await confirmAction('Hapus SITREP?', 'Apakah Anda yakin ingin menghapus laporan perkembangan ini?')) {
        const res = await fetch(`<?= BASE_URL ?>/api/laporan/${id}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            showToast('Laporan berhasil dihapus', 'success');
            setTimeout(() => location.reload(), 600);
        }
    }
}

async function confirmAndRollbackOperasi(idOperasi) {
    if (!idOperasi) return;

    if (typeof Swal !== 'undefined') {
        const res = await Swal.fire({
            title: 'Buka Kembali Operasi SAR?',
            html: `<div class="text-left text-xs text-slate-600 dark:text-slate-300 space-y-2">
                <p>Status operasi SAR akan dikembalikan dari <strong>CLOSED</strong> menjadi <strong>OPEN</strong>.</p>
                <p class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300">
                    Penguncian akan dibuka: Penambahan dan penyuntingan RENOPS, SITREP, serta naskah telegram resmi dapat dilakukan kembali.
                </p>
            </div>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Buka Kembali Operasi',
            cancelButtonText: 'Batal',
            reverseButtons: true
        });

        if (!res.isConfirmed) return;

        Swal.fire({
            title: 'Membuka Kembali Operasi...',
            text: 'Sedang memperbarui status operasional ke server...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const resp = await fetch(`<?= BASE_URL ?>/api/operasi/${idOperasi}/rollback-penutupan`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            });
            const data = await resp.json();
            if (data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Operasi SAR Berhasil Dibuka!',
                    text: data.message || 'Status operasi kini kembali OPEN. Seluruh fitur penambahan dan penyuntingan telah aktif kembali.',
                    confirmButtonColor: '#f97316',
                    confirmButtonText: 'Lanjutkan'
                });
                window.location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Membuka Kembali Operasi',
                    text: data.message || 'Terjadi kesalahan sistem saat memperbarui status.',
                    confirmButtonColor: '#ef4444'
                });
            }
        } catch (err) {
            console.error('Rollback fetch error, submitting form fallback:', err);
            const form = document.getElementById('form-fallback-rollback');
            if (form) {
                form.submit();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Gagal',
                    text: 'Gagal menghubungi server: ' + err.message,
                    confirmButtonColor: '#ef4444'
                });
            }
        }
    } else {
        if (confirm('Buka kembali operasi SAR ini menjadi status OPEN? Seluruh dokumen akan dapat ditambahkan kembali.')) {
            const form = document.getElementById('form-fallback-rollback');
            if (form) form.submit();
        }
    }
}

function printBerkas(type) {
    window.print();
}
</script>

<!-- Fallback Hidden Form for Rollback -->
<form id="form-fallback-rollback" method="POST" action="<?= BASE_URL ?>/operasi.php?action=rollback" style="display: none;">
    <?= csrfInput() ?>
    <input type="hidden" name="id_operasi" value="<?= $id ?>">
</form>
