<?php
/**
 * QR-SAR - Detail Operasi Hub View
 * KPP Kendari - Basarnas
 */

if (empty($operasi)) {
    echo "<div class='card'>Operasi tidak ditemukan.</div>";
    return;
}

$id = (int)$operasi['id_operasi'];
$isOpen = $operasi['status'] === 'OPEN';
$waktuMulaiOps = !empty($operasi['waktu_berangkat']) ? $operasi['waktu_berangkat'] : (!empty($operasi['waktu_lapor']) ? $operasi['waktu_lapor'] : (!empty($operasi['waktu_kejadian']) ? $operasi['waktu_kejadian'] : date('Y-m-d H:i:s')));
$tglMulaiOps = date('Y-m-d', strtotime($waktuMulaiOps));
$hariKe = TemplateHelper::hitungHariKe($waktuMulaiOps);
$statsKorban = $operasi['stats_korban'];
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

// Operational Hierarchy & State Inheritance
$timeline = HierarchyHelper::getOperationalTimeline($operasi);
$nextRenops = HierarchyHelper::getNextRenopsDefaults($operasi);
$nextLapbang = HierarchyHelper::getNextLapbangDefaults($operasi);
$masterAlut = MasterModel::getAlut();
$masterTim = MasterModel::getTim();

// Determine immediate next step in reporting lifecycle
$maxRenopsDay = !empty($operasi['renops']) ? max(array_map(fn($r) => HierarchyHelper::extractDayNumber($r['hari_ke'] ?? '1'), $operasi['renops'])) : 0;
$maxLapbangDay = !empty($operasi['laporan_harian']) ? max(array_map(fn($l) => HierarchyHelper::extractDayNumber($l['hari_ke'] ?? '1'), $operasi['laporan_harian'])) : 0;

$nextActionType = 'LAPBANG';
$nextActionLabel = "Buat Lapbang H.1";
$nextActionDesc = "Mewarisi potensi SAR & alut dari Laporan Awal";
if ($maxLapbangDay >= 1 && $maxLapbangDay >= $maxRenopsDay) {
    $nextTarget = min(7, $maxLapbangDay + 1);
    $nextActionType = 'RENOPS';
    $nextActionLabel = "Buat Renops H.{$nextTarget}";
    $nextActionDesc = "Mewarisi potensi SAR & alut dari Lapbang H.{$maxLapbangDay}";
} elseif ($maxRenopsDay > $maxLapbangDay) {
    $nextActionType = 'LAPBANG';
    $nextActionLabel = "Buat Lapbang H.{$maxRenopsDay}";
    $nextActionDesc = "Mewarisi potensi SAR & alut dari Renops H.{$maxRenopsDay}";
}

$lkpCoords = GeoHelper::parseCoordinates($operasi['koordinat'] ?? '');
$hasLkpCoords = !empty($lkpCoords) && isset($lkpCoords['lat']) && isset($lkpCoords['lng']);
$lkpLat = $hasLkpCoords ? (float)$lkpCoords['lat'] : -4.037694;
$lkpLng = $hasLkpCoords ? (float)$lkpCoords['lng'] : 122.486694;
?>

<!-- ======================================================== -->
<!-- BREADCRUMB NAVIGATION -->
<!-- ======================================================== -->
<div class="flex items-center justify-between gap-3 mb-4">
    <div class="flex items-center gap-2">
        <a href="<?= BASE_URL ?>/operasi.php" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold text-xs shadow-xs transition">
            <svg class="w-3.5 h-3.5 text-orange-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 12H5m7 7l-7-7 7-7"/></svg>
            <span>Daftar Operasi</span>
        </a>
        <span class="text-xs text-slate-400 font-medium hidden sm:inline">/</span>
        <span class="text-xs font-bold text-slate-500 dark:text-slate-400 font-mono hidden sm:inline">
            OPS-#<?= $id ?>
        </span>
    </div>

    <?php if (AuthHelper::isAdmin()): ?>
    <a href="<?= BASE_URL ?>/operasi.php?action=delete&id=<?= $id ?>" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-red-500/10 hover:bg-red-500 text-red-600 hover:text-white border border-red-500/20 font-bold text-xs transition" data-confirm="Apakah Anda yakin ingin menghapus Operasi SAR ini secara permanen beserta seluruh Renops, Sitrep, dan data terkait? Tindakan ini tidak dapat dibatalkan." data-confirm-title="Hapus Operasi SAR Permanen" data-confirm-btn="Ya, Hapus Permanen" data-confirm-danger="true" title="Hapus Operasi">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        <span>Hapus Operasi</span>
    </a>
    <?php endif; ?>
</div>

<!-- ======================================================== -->
<!-- COMPACT SUMMARY CARD (LAPORAN AWAL) -->
<!-- ======================================================== -->
<div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white shadow-xs p-4 sm:p-5 mb-5 transition-colors">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1.5 min-w-0">
            <!-- Badges Bar -->
            <div class="flex flex-wrap items-center gap-2">
                <?php if ($isOpen): ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold font-mono tracking-wider bg-emerald-500/15 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    OPERASI BERJALAN
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-orange-500/15 dark:bg-orange-500/20 text-orange-700 dark:text-orange-300 border border-orange-500/30">
                    SIKLUS: <?= e($hariKe) ?>
                </span>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold font-mono tracking-wider bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700">
                    🔒 OPERASI DITUTUP
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-white/10 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-white/10">
                    SELESAI (<?= e($hariKe) ?>)
                </span>
                <?php endif; ?>

                <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                    ID: #<?= $id ?>
                </span>

                <?php if (!empty($operasi['jenis_kejadian'])): ?>
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                    🏷️ <?= e($operasi['jenis_kejadian']) ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Title -->
            <h1 class="text-base sm:text-lg lg:text-xl font-black text-slate-900 dark:text-white tracking-tight leading-snug">
                <?= e($operasi['nama_operasi']) ?>
            </h1>

            <!-- Meta Details (Waktu Mulai & Lokasi Singkat) -->
            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                <span class="flex items-center gap-1">
                    <span>🚀</span>
                    <span>Mulai Ops: <strong class="text-orange-600 dark:text-orange-400 font-mono"><?= TemplateHelper::formatTanggalIndo($waktuMulaiOps) ?> (<?= TemplateHelper::formatJamWITA($waktuMulaiOps) ?> WITA)</strong></span>
                </span>
                <?php if (!empty($operasi['lokasi_kejadian'])): ?>
                <span class="hidden sm:inline text-slate-300 dark:text-slate-700">•</span>
                <span class="flex items-center gap-1 truncate max-w-md" title="<?= e($operasi['lokasi_kejadian']) ?>">
                    <span>📍</span>
                    <span><?= e($operasi['lokasi_kejadian']) ?></span>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Action Toolbar for Laporan Awal -->
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs shadow-xs transition cursor-pointer" onclick="openModal('modal-view-wa-awal')" title="Lihat naskah laporan awal format resmi WhatsApp">
                <span>📱</span>
                <span>Naskah WA Awal</span>
            </button>

            <?php if ($isOpen): ?>
            <a href="<?= BASE_URL ?>/operasi_form.php?id=<?= $id ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs shadow-xs transition" title="Koreksi data inisiasi laporan awal">
                <span>✏️</span>
                <span>Edit Lap Awal</span>
            </a>

            <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-xs shadow-sm transition cursor-pointer" onclick="openModal('modal-close-ops-detail')" title="Tutup Operasi SAR secara resmi">
                <span>🔒</span>
                <span>Tutup Operasi</span>
            </button>
            <?php else: ?>
            <div class="flex items-center gap-2">
                <span class="text-xs text-amber-600 dark:text-amber-300 font-medium">
                    Ditutup <?= $tglSelesai ? "({$tglSelesai})" : '' ?>
                </span>
                <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow-sm transition cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                    <span>Buka Kembali</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<!-- HUB TAB NAVIGATION (matching DetailOperasiHub) -->
<div class="tabs-header">
    <button class="tab-btn <?= $activeTab === 'hierarchy' ? 'active' : '' ?>" onclick="switchTab('hierarchy')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span>Hirarki Dokumen SAR (<?= count($timeline) ?>)</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'renops' ? 'active' : '' ?>" onclick="switchTab('renops')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polygon points="16.24 7.76 14.12 14.12 7.76 16.24 9.88 9.88 16.24 7.76"/></svg>
        <span>Rencana Operasi (<?= count($operasi['renops']) ?>)</span>
    </button>
    <button class="tab-btn <?= ($activeTab === 'sitrep' || $activeTab === 'laporan') ? 'active' : '' ?>" onclick="switchTab('sitrep')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        <span>Laporan Perkembangan & Penutupan (<?= count($operasi['laporan_harian']) ?>)</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'korban' ? 'active' : '' ?>" onclick="switchTab('korban')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Data Korban & Kronologi</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'kekuatan' ? 'active' : '' ?>" onclick="switchTab('kekuatan')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 17h20M2 12h20M2 7h20"/><circle cx="12" cy="12" r="10"/></svg>
        <span>Kekuatan Unsur & Alut Terkini</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'gis' ? 'active' : '' ?>" onclick="switchTab('gis')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
        <span>Peta Taktis Operasi</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'cetak' ? 'active' : '' ?>" onclick="switchTab('cetak')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
        <span>Cetak & Berkas Resmi</span>
    </button>
    <button class="tab-btn <?= $activeTab === 'bra' ? 'active' : '' ?>" onclick="switchTab('bra')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="2"/><path d="M16.24 7.76a6 6 0 0 1 0 8.49m-8.48-.01a6 6 0 0 1 0-8.49m11.31-2.82a10 10 0 0 1 0 14.14m-14.14 0a10 10 0 0 1 0-14.14"/></svg>
        <span>BRA SAR (<?= count($operasi['bra_sar'] ?? []) ?>)</span>
    </button>
</div>

<!-- ========================================== -->
<!-- TAB 1: ALUR HIRARKI LAPORAN OPERASI SAR -->
<!-- ========================================== -->
<div id="tab-content-hierarchy" class="tab-pane space-y-4" style="display: <?= $activeTab === 'hierarchy' ? 'block' : 'none' ?>;">
    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-slate-50 via-white to-slate-50 dark:from-slate-900 dark:via-slate-800/40 dark:to-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20">
                    Silsilah Terstruktur
                </span>
                <span class="text-xs text-slate-400 font-medium">
                    Total: <?= count($timeline) ?> Dokumen Terbit
                </span>
            </div>
            <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                <span>⏱️</span>
                <span>Alur Kronologis Dokumen Operasi SAR</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 leading-relaxed">
                Urutan rantai pelaporan terpadu: Laporan Awal ➔ Renops H.1 ➔ Lapbang H.1 ➔ Renops H.2 ➔ Lapbang H.2 ➔ Seterusnya hingga Penutupan.
            </p>
        </div>
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
            <div class="p-4 sm:p-4.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 hover:border-slate-300 dark:hover:border-slate-700 transition">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-0.5 rounded-lg font-mono text-[11px] font-bold border <?= $badgeClass ?>">
                            <?= e($docType) ?> • <?= e($doc['hari_ke']) ?>
                        </span>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white">
                            <?= e($doc['label']) ?>
                        </h4>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-2">
                        <span>📅 <?= TemplateHelper::formatTanggalIndo($doc['waktu']) ?> (<?= TemplateHelper::formatJamWITA($doc['waktu']) ?> WITA)</span>
                        <?php if (!empty($doc['cuaca'])): ?>
                        <span>•</span>
                        <span>🌤️ <?= e($doc['cuaca']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="shrink-0">
                    <?php if ($isAwal): ?>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs transition cursor-pointer" onclick="openModal('modal-view-wa-awal')">
                        <span>📱</span>
                        <span>Naskah WA</span>
                    </button>
                    <?php elseif ($isRenops): ?>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/30 font-bold text-xs transition cursor-pointer" onclick="switchTab('renops')">
                        <span>🧭</span>
                        <span>Buka Tab RENOPS</span>
                    </button>
                    <?php elseif ($isLapbang || $isPenutupan): ?>
                    <button type="button" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-600 dark:text-emerald-400 hover:text-white border border-emerald-500/30 font-bold text-xs transition cursor-pointer" onclick="switchTab('sitrep')">
                        <span>📊</span>
                        <span>Buka Tab Lapbang</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>


<!-- ========================================== -->
<!-- TAB 2: RENCANA OPERASI (RENOPS) -->
<!-- ========================================== -->
<div id="tab-content-renops" class="tab-pane space-y-5" style="display: <?= $activeTab === 'renops' ? 'block' : 'none' ?>;">

    <!-- COMMAND HEADER BANNER FOR RENOPS -->
    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-orange-50 via-white to-orange-50/50 dark:from-slate-900 dark:via-orange-950/60 dark:to-slate-900 border border-orange-200 dark:border-orange-500/20 text-slate-900 dark:text-white shadow-sm dark:shadow-md relative overflow-hidden transition-colors">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-orange-500/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold font-mono tracking-wider <?= $isOpen ? 'bg-orange-500/15 dark:bg-orange-500/20 text-orange-700 dark:text-orange-300 border border-orange-500/30' : 'bg-red-500/15 dark:bg-red-500/20 text-red-700 dark:text-red-300 border border-red-500/30' ?>">
                        <span class="w-2 h-2 rounded-full <?= $isOpen ? 'bg-orange-400 animate-pulse' : 'bg-red-400' ?>"></span>
                        <?= $isOpen ? 'SIKLUS RENCANA AKTIF' : 'OPERASI DITUTUP' ?>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-orange-100 dark:bg-white/10 text-orange-700 dark:text-orange-200 border border-orange-200 dark:border-white/15">
                        Rencana Terbit: <?= count($operasi['renops']) ?> Dokumen
                    </span>
                </div>
                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🧭</span>
                    <span>Rencana Operasi SAR Harian (RENOPS)</span>
                </h2>
                <p class="text-xs text-slate-600 dark:text-slate-300 max-w-2xl mt-0.5 leading-relaxed">
                    Siklus pembagian Search and Rescue Unit (SRU), sektor koordinat pencarian, pola penyisiran lapangan, serta alokasi potensi & armada per hari operasi.
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <?php if ($isOpen): ?>
                <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-md shadow-orange-600/20 hover:scale-[1.02] active:scale-[0.98] transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    <span>+ Buat RENOPS H.<?= $nextRenops['target_day_number'] ?? substr($hariKe, 2) ?></span>
                </a>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-400 border border-slate-300 dark:border-slate-700 font-bold text-xs">
                    🔒 RENOPS Terkunci
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CALLOUT IF OPERATION IS CLOSED -->
    <?php if (!$isOpen): ?>
    <div class="p-3.5 sm:p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-start gap-2.5">
            <span class="text-xl">🔒</span>
            <div>
                <h4 class="font-bold text-xs text-amber-900 dark:text-amber-100">Operasi SAR Telah Berstatus DITUTUP (CLOSED)</h4>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">Penambahan dan pengubahan Rencana Operasi (RENOPS) dinonaktifkan. Silakan buka kembali operasi (Rollback) jika ada perpanjangan atau pembukaan operasi kembali.</p>
            </div>
        </div>
        <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow transition whitespace-nowrap cursor-pointer">
            🔄 Buka Kembali Operasi
        </button>
    </div>
    <?php endif; ?>

    <!-- RENOPS FEED LIST -->
    <?php if (empty($operasi['renops'])): ?>
    <div class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs text-center">
        <div class="w-14 h-14 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center text-3xl mx-auto mb-3.5">
            🧭
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1.5">Belum Ada Rencana Operasi (RENOPS)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Lembar RENOPS memuat pembagian Search and Rescue Unit (SRU), sektor koordinat pencarian, serta pola penyisiran armada tim gabungan per hari operasi.
        </p>
        <?php if ($isOpen): ?>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-orange-50 dark:bg-orange-950/40 border border-orange-200 dark:border-orange-900/50 text-orange-700 dark:text-orange-300 text-xs font-semibold">
            <span>💡</span>
            <span>Gunakan tombol <strong>+ Buat RENOPS H.<?= $nextRenops['target_day_number'] ?? substr($hariKe, 2) ?></strong> di sudut kanan atas untuk menyusun rencana operasi.</span>
        </div>
        <?php else: ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-semibold">
            🔒 Operasi ditutup, penyusunan RENOPS dinonaktifkan.
        </span>
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
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs overflow-hidden transition-all hover:shadow-md">
        
        <!-- Renops Card Header -->
        <div class="p-3.5 sm:p-4 border-b bg-slate-50/50 dark:bg-slate-800/30 border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
            <div class="flex flex-wrap items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-lg font-mono font-black text-xs bg-orange-600 text-white shadow-xs">
                    <?= e($ren['hari_ke']) ?>
                </span>
                <div class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                    <span>📅 Rencana Pelaksanaan: <?= TemplateHelper::formatTanggalIndo($ren['tanggal_laporan']) ?></span>
                </div>
            </div>

            <!-- Action Toolbar for Renops -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($renWa), ENT_QUOTES) ?>, "Naskah RENOPS WhatsApp Berhasil Disalin!")'>
                    📋 <span>Salin WA</span>
                </button>
                <?php if ($isOpen): ?>
                <a href="<?= BASE_URL ?>/renops_form.php?id_operasi=<?= $id ?>&id_renops=<?= $ren['id_renops'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer">
                    ✏️ <span>Edit</span>
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&from=renops&id_renops=<?= $ren['id_renops'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/20 font-bold text-xs transition cursor-pointer" title="Buat Berita Radio dari RENOPS ini">
                    📡 <span>BRA Renops</span>
                </a>
                <?php if ($isOpen): ?>
                <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer" onclick="deleteRenops(<?= $ren['id_renops'] ?>)" title="Hapus Lembar Renops">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Renops Card Body -->
        <div class="p-4 sm:p-5 space-y-3.5">
            <!-- Timetable & Cuaca Row -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Waktu Briefing</span>
                    <span class="font-bold text-slate-900 dark:text-white"><?= TemplateHelper::formatTW($ren['waktu_briefing']) ?></span>
                </div>
                <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Mulai Pergerakan Ops</span>
                    <span class="font-bold text-orange-600 dark:text-orange-400"><?= TemplateHelper::formatTW($ren['waktu_mulai_ops']) ?></span>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-0.5">Prakiraan Cuaca BMKG</span>
                    <span class="font-bold text-slate-900 dark:text-white truncate block"><?= e($ren['cuaca']) ?></span>
                </div>
            </div>

            <!-- SRU Table -->
            <div>
                <h4 class="text-xs font-bold text-orange-600 dark:text-orange-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                    <span>🚤</span>
                    <span>Pembagian Sektor & Search and Rescue Unit (SRU):</span>
                </h4>
                <div class="table-responsive rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <table class="table">
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
                                <td class="font-bold text-xs"><?= e($s['luas_area']) ?></td>
                                <td><span class="badge badge-info text-[10px]"><?= e($s['pola_pencarian']) ?></span></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Snapshot Potensi & Alut Renops -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                        <span>👥</span>
                        <span>Potensi SAR & Armada Terlibat (Snapshot <?= e($ren['hari_ke']) ?>):</span>
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-mono font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                        Total: <?= $totalSnapPers ?> Personel & <?= $totalSnapAlut ?> Unit Alut
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Unsur SAR Terlibat:</span>
                        <div class="space-y-1">
                            <?php if (empty($snapUnsur)): ?>
                            <span class="text-slate-400">-</span>
                            <?php else: foreach ($snapUnsur as $u): ?>
                            <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <span>• <?= e($u['nama_unsur']) ?></span>
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
                            <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                <span>• <?= e($a['nama_alut']) ?></span>
                                <strong class="text-emerald-600 dark:text-emerald-400 font-mono"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></strong>
                            </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Text Format Accordion -->
            <details class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950/40 p-3 sm:p-4 transition">
                <summary class="flex items-center justify-between cursor-pointer text-xs font-bold text-slate-700 dark:text-slate-300 select-none">
                    <span class="flex items-center gap-1.5">
                        <span>💬</span>
                        <span>Format Teks WhatsApp Siap Kirim (<?= e($ren['hari_ke']) ?>)</span>
                    </span>
                    <span class="text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                </summary>
                <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <pre class="font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed select-all max-h-80 overflow-y-auto"><?= e($renWa) ?></pre>
                </div>
            </details>
        </div>
    </div>
    <?php endforeach; endif; ?>
</div>

<!-- ========================================== -->
<!-- TAB 3: PERKEMBANGAN & SITREP -->
<!-- ========================================== -->
<div id="tab-content-sitrep" class="tab-pane space-y-5" style="display: <?= ($activeTab === 'sitrep' || $activeTab === 'laporan') ? 'block' : 'none' ?>;">

    <!-- COMMAND HEADER BANNER -->
    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-blue-50 via-white to-blue-50/50 dark:from-slate-900 dark:via-blue-950/60 dark:to-slate-900 border border-blue-200 dark:border-blue-500/20 text-slate-900 dark:text-white shadow-sm dark:shadow-md relative overflow-hidden transition-colors">
        <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-blue-500/10 rounded-full blur-2xl pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold font-mono tracking-wider <?= $isOpen ? 'bg-emerald-500/15 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30' : 'bg-red-500/15 dark:bg-red-500/20 text-red-700 dark:text-red-300 border border-red-500/30' ?>">
                        <span class="w-2 h-2 rounded-full <?= $isOpen ? 'bg-emerald-400 animate-pulse' : 'bg-red-400' ?>"></span>
                        <?= $isOpen ? 'OPERASI BERJALAN' : 'OPERASI DITUTUP' ?>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">
                        Siklus: <?= e($hariKe) ?>
                    </span>
                    <span class="text-xs text-blue-700/70 dark:text-blue-200/70 font-medium hidden sm:inline">
                        • Standar SOP Maks. 7 Hari Basarnas
                    </span>
                </div>
                <h2 class="text-base sm:text-lg font-black tracking-tight text-slate-900 dark:text-white flex items-center gap-2">
                    <span>📊</span>
                    <span>Laporan Perkembangan Harian (SITREP / LAPBANG)</span>
                </h2>
                <p class="text-xs text-slate-600 dark:text-slate-300 max-w-2xl mt-0.5 leading-relaxed">
                    Pusat pemantauan berkala hasil pencarian lapangan, mutasi korban, kekuatan armada & potensi gabungan, serta laporan penutupan operasi SAR.
                </p>
            </div>
            
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <?php if ($isOpen): ?>
                <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs shadow-md shadow-blue-600/20 hover:scale-[1.02] active:scale-[0.98] transition cursor-pointer">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                    <span>+ Terbitkan SITREP <?= e($hariKe) ?></span>
                </a>
                <?php else: ?>
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 font-bold text-xs">
                    <span>🔒 Terkunci (Operasi Ditutup)</span>
                </span>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&kategori=SAR%20UMUM" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-white/10 dark:hover:bg-white/20 text-slate-700 dark:text-white border border-slate-200 dark:border-white/15 font-semibold text-xs transition cursor-pointer backdrop-blur" title="Buat Berita Radio Senkom dari Laporan Perkembangan">
                    <span>📡</span>
                    <span>BRA Sitrep</span>
                </a>
            </div>
        </div>
    </div>

    <?php if (!$isOpen): ?>
    <!-- CLOSED STATUS WARNING BANNER -->
    <div class="p-3.5 sm:p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center font-bold text-base shrink-0">
                🔒
            </div>
            <div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
                    <span>Operasi SAR Telah Ditutup (CLOSED)</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-mono bg-red-500 text-white font-bold">LOCKED</span>
                </h4>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">Penambahan dan pengubahan Laporan Perkembangan (SITREP/Lapbang) dinonaktifkan demi integritas data arsip. Buka kembali status operasi (Rollback) jika ada pembaharuan pencarian.</p>
            </div>
        </div>
        <button type="button" onclick="confirmAndRollbackOperasi(<?= $id ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold text-xs shadow transition cursor-pointer shrink-0">
            <span>🔓 Buka Kembali Operasi</span>
        </button>
    </div>
    <?php endif; ?>

    <!-- TACTICAL KPI & METRICS HUD -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3">
        <!-- KPI 1: Hari Operasi -->
        <div class="p-3 sm:p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider">Hari Berjalan</span>
                <span class="text-sm">⏱️</span>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-xl font-black text-slate-900 dark:text-white font-mono"><?= e($hariKe) ?></span>
                <span class="text-[11px] text-slate-500">/ H.7 SOP</span>
            </div>
            <?php 
                $dayNum = (int)filter_var($hariKe, FILTER_SANITIZE_NUMBER_INT) ?: 1;
                $pct = min(100, round(($dayNum / 7) * 100));
            ?>
            <div class="w-full bg-slate-100 dark:bg-slate-800 h-1.5 rounded-full mt-2.5 overflow-hidden">
                <div class="bg-blue-600 h-full rounded-full transition-all" style="width: <?= $pct ?>%;"></div>
            </div>
        </div>

        <!-- KPI 2: Total SITREP Rilis -->
        <div class="p-3 sm:p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider">Total SITREP</span>
                <span class="text-sm">📑</span>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-xl font-black text-blue-600 dark:text-blue-400 font-mono"><?= count($operasi['laporan_harian']) ?></span>
                <span class="text-[11px] text-slate-500">Dokumen</span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-2 truncate">
                <?= count($operasi['laporan_harian']) > 0 ? 'Tersinkronisasi' : 'Menunggu rilis H.1' ?>
            </p>
        </div>

        <!-- KPI 3: Status Korban Terkini -->
        <div class="p-3 sm:p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider">Status Korban</span>
                <span class="text-sm">👥</span>
            </div>
            <div class="flex items-center gap-1 font-mono text-xs">
                <span class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold" title="Selamat">S: <?= (int)($statsKorban['s'] ?? 0) ?></span>
                <span class="px-1.5 py-0.5 rounded bg-red-500/10 text-red-600 dark:text-red-400 font-bold" title="Meninggal Dunia">MD: <?= (int)($statsKorban['md'] ?? 0) ?></span>
                <span class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 font-bold" title="Dalam Pencarian">DP: <?= (int)($statsKorban['dp'] ?? 0) ?></span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-2">
                Total: <strong class="text-slate-700 dark:text-slate-300"><?= (int)($statsKorban['total'] ?? 0) ?> Korban</strong>
            </p>
        </div>

        <!-- KPI 4: Kekuatan Operasi Terkini -->
        <?php 
            $latestLap = !empty($operasi['laporan_harian']) ? end($operasi['laporan_harian']) : null;
            $kpiUnsur = !empty($latestLap['detail_unsur']) ? $latestLap['detail_unsur'] : ($operasi['detail_unsur'] ?? []);
            $kpiAlut = !empty($latestLap['detail_alut']) ? $latestLap['detail_alut'] : ($operasi['detail_alut'] ?? []);
            $kpiTotalPers = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 0), $kpiUnsur));
            $kpiTotalAlut = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 0), $kpiAlut));
        ?>
        <div class="p-3 sm:p-3.5 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs flex flex-col justify-between">
            <div class="flex items-center justify-between text-slate-500 dark:text-slate-400 mb-1.5">
                <span class="text-[10px] font-bold uppercase tracking-wider">Kekuatan Lapangan</span>
                <span class="text-sm">🚢</span>
            </div>
            <div class="flex items-baseline gap-1.5">
                <span class="text-xl font-black text-purple-600 dark:text-purple-400 font-mono"><?= $kpiTotalPers ?></span>
                <span class="text-[11px] text-slate-500">Personel & <?= $kpiTotalAlut ?> Alut</span>
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-2 truncate">
                Snapshot <?= $latestLap ? e($latestLap['hari_ke']) : 'Operasi Awal' ?>
            </p>
        </div>
    </div>

    <!-- LIST FEED SITREP -->
    <?php if (empty($operasi['laporan_harian'])): ?>
    <div class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs text-center">
        <div class="w-14 h-14 rounded-2xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-3xl mx-auto mb-3.5">
            📊
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1.5">Belum Ada Laporan Perkembangan (SITREP)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Laporan perkembangan memuat evaluasi hasil pencarian harian tim gabungan, mutasi korban, serta debriefing penutupan operasi SAR.
        </p>
        <?php if ($isOpen): ?>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900/50 text-blue-700 dark:text-blue-300 text-xs font-semibold">
            <span>💡</span>
            <span>Gunakan tombol <strong>+ Terbitkan SITREP <?= e($hariKe) ?></strong> di sudut kanan atas untuk merilis laporan perkembangan lapangan.</span>
        </div>
        <?php else: ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-semibold">
            🔒 Operasi telah ditutup, penerbitan SITREP dinonaktifkan.
        </span>
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
        <div class="rounded-2xl bg-white dark:bg-slate-900 border <?= $isPenutupan ? 'border-red-500/40 dark:border-red-900/60' : 'border-slate-200 dark:border-slate-800' ?> shadow-xs overflow-hidden transition-all hover:shadow-md">
            
            <!-- Card Header -->
            <div class="p-3.5 sm:p-4 border-b <?= $isPenutupan ? 'bg-red-50/40 dark:bg-red-950/20 border-red-200/60 dark:border-red-900/40' : 'bg-slate-50/50 dark:bg-slate-800/30 border-slate-100 dark:border-slate-800' ?> flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded-lg font-mono font-black text-xs <?= $isPenutupan ? 'bg-red-500 text-white shadow-xs' : 'bg-blue-600 text-white shadow-xs' ?>">
                        <?= $isPenutupan ? '🔒 PENUTUPAN OPERASI' : "LAPBANG " . e($lap['hari_ke']) ?>
                    </span>
                    <div class="flex items-center gap-2 text-xs font-bold text-slate-800 dark:text-slate-200">
                        <span>📅 <?= TemplateHelper::formatTanggalIndo($lap['waktu_laporan']) ?></span>
                        <span class="text-slate-400">•</span>
                        <span class="font-mono text-blue-600 dark:text-blue-400">⏱️ <?= TemplateHelper::formatJamWITA($lap['waktu_laporan']) ?> WITA</span>
                    </div>
                </div>

                <!-- Action Toolbar -->
                <div class="flex items-center gap-1.5 flex-wrap">
                    <button type="button" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer" onclick='copyTextToClipboard(<?= htmlspecialchars(json_encode($lapWa), ENT_QUOTES) ?>, "Naskah SITREP WhatsApp Berhasil Disalin!")'>
                        📋 <span>Salin WA</span>
                    </button>
                    <?php if ($isOpen): ?>
                    <a href="<?= BASE_URL ?>/sitrep_form.php?id_operasi=<?= $id ?>&id_laporan=<?= $lap['id_laporan'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-semibold text-xs transition cursor-pointer">
                        ✏️ <span>Edit</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&from=sitrep&id_laporan=<?= $lap['id_laporan'] ?>" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-orange-500/10 hover:bg-orange-500 text-orange-600 dark:text-orange-400 hover:text-white border border-orange-500/20 font-bold text-xs transition cursor-pointer" title="Buat Berita Radio dari laporan ini">
                        📡 <span>BRA Sitrep</span>
                    </a>
                    <?php if ($isOpen): ?>
                    <button type="button" class="p-1 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-950/40 transition cursor-pointer" onclick="deleteLaporan(<?= $lap['id_laporan'] ?>)" title="Hapus Laporan Perkembangan">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Card Body -->
            <div class="p-4 sm:p-5 space-y-3.5">
                
                <!-- Hasil Pencarian Box -->
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800 text-xs sm:text-sm leading-relaxed text-slate-800 dark:text-slate-200">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="text-blue-500">📝</span>
                        <span class="font-bold text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400">Hasil Pencarian & Tindakan Lapangan:</span>
                    </div>
                    <div class="whitespace-pre-wrap font-sans pl-1">
                        <?= nl2br(e($lap['isi_laporan'])) ?>
                    </div>
                    
                    <?php if (!empty($lap['waktu_debrief'])): ?>
                    <div class="mt-3 p-3 rounded-xl bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900/60 flex items-center gap-2 text-red-600 dark:text-red-400 font-bold text-xs">
                        <span>🔒</span>
                        <span>Debriefing Resmi Penutupan: <?= TemplateHelper::formatTanggalIndo($lap['waktu_debrief']) ?> Pukul <?= TemplateHelper::formatJamWITA($lap['waktu_debrief']) ?> WITA</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Snapshot Unsur & Alut Grid -->
                <div class="p-4 rounded-2xl bg-slate-50/60 dark:bg-slate-950/40 border border-slate-200/80 dark:border-slate-800/80">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
                            <span>👥</span>
                            <span>Riwayat Potensi & Alut Terlibat (Snapshot <?= e($lap['hari_ke']) ?>):</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full font-mono text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                            Total: <?= $totalSnapPers ?> Personel • <?= $totalSnapAlut ?> Alut
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <!-- Unsur List -->
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 block mb-1.5">Potensi SAR Gabungan:</span>
                            <div class="space-y-1">
                                <?php if (empty($snapUnsur)): ?>
                                <span class="text-slate-400 text-[11px] italic">Tidak ada catatan unsur</span>
                                <?php else: foreach ($snapUnsur as $u): ?>
                                <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">• <?= e($u['nama_unsur']) ?></span>
                                    <span class="font-mono font-bold text-purple-600 dark:text-purple-400"><?= (int)($u['jumlah_personil'] ?? 1) ?> Org</span>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                        <!-- Alut List -->
                        <div>
                            <span class="text-[11px] font-semibold text-slate-400 block mb-1.5">Armada Alut & Palsar:</span>
                            <div class="space-y-1">
                                <?php if (empty($snapAlut)): ?>
                                <span class="text-slate-400 text-[11px] italic">Tidak ada catatan alut</span>
                                <?php else: foreach ($snapAlut as $a): ?>
                                <div class="flex items-center justify-between px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                                    <span class="font-medium text-slate-700 dark:text-slate-300">• <?= e($a['nama_alut']) ?></span>
                                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400"><?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?></span>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Collapsible WhatsApp Output -->
                <details class="group rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                    <summary class="px-4 py-3 text-xs font-bold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white cursor-pointer flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30 transition">
                        <span class="flex items-center gap-2">
                            <span>📱</span>
                            <span>Lihat Format Naskah WhatsApp Resmi (FILE AKTIF)</span>
                        </span>
                        <span class="text-[11px] font-mono font-normal text-slate-400 group-open:rotate-180 transition-transform">▼</span>
                    </summary>
                    <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                        <pre class="font-mono text-xs text-slate-800 dark:text-slate-200 whitespace-pre-wrap leading-relaxed select-all max-h-80 overflow-y-auto"><?= e($lapWa) ?></pre>
                    </div>
                </details>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ========================================== -->
<!-- TAB 4: DATA KORBAN & KRONOLOGI -->
<!-- ========================================== -->
<div id="tab-content-korban" class="tab-pane space-y-6" style="display: <?= $activeTab === 'korban' ? 'block' : 'none' ?>;">
    
    <!-- STATS SUMMARY HUD -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider block">Total Korban</span>
                <span class="text-2xl font-black text-slate-900 dark:text-white font-mono"><?= count($operasi['korban']) ?></span>
                <span class="text-[11px] text-slate-400">Orang Tercatat</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-lg">
                👥
            </div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider block">Selamat (S)</span>
                <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono"><?= (int)($statsKorban['s'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400">Dievakuasi</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold text-lg">
                ✅
            </div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-red-600 dark:text-red-400 uppercase tracking-wider block">Meninggal (MD)</span>
                <span class="text-2xl font-black text-red-600 dark:text-red-400 font-mono"><?= (int)($statsKorban['md'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400">Ditemukan</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-red-500/10 text-red-600 flex items-center justify-center font-bold text-lg">
                ⚠️
            </div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider block">Pencarian (DP)</span>
                <span class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono"><?= (int)($statsKorban['dp'] ?? 0) ?></span>
                <span class="text-[11px] text-slate-400">Target Aktif</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-lg">
                ⏳
            </div>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN CONTENT -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left: Victims Table (7 cols) -->
        <div class="lg:col-span-7 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
            <div class="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center font-bold text-sm">
                        👥
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Daftar Identitas Korban</h3>
                        <p class="text-[11px] text-slate-400">Data manifest korban yang dilaporkan dalam operasi</p>
                    </div>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                    <?= count($operasi['korban']) ?> Orang
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
                        <tr class="border-b border-slate-100 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 text-slate-500 uppercase font-bold text-[10px] tracking-wider">
                            <th class="py-3 px-4 w-12 text-center">No</th>
                            <th class="py-3 px-4">Nama Korban</th>
                            <th class="py-3 px-4">Umur / JK</th>
                            <th class="py-3 px-4">Alamat Domisili</th>
                            <th class="py-3 px-4 text-center">Status</th>
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
                            <td class="py-3 px-4 text-center font-bold font-mono text-slate-400"><?= $idx + 1 ?></td>
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                <span class="w-6 h-6 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-[10px] text-slate-500">👤</span>
                                <span><?= e($k['nama_korban']) ?></span>
                            </td>
                            <td class="py-3 px-4 font-medium"><?= e($k['umur'] ?: '-') ?> (<?= e($k['jk'] ?: '-') ?>)</td>
                            <td class="py-3 px-4 text-slate-500 text-[11px]"><?= e($k['alamat'] ?: '-') ?></td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2.5 py-1 rounded-xl font-bold font-mono text-[10px] <?= $stClass ?>">
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
        <div class="lg:col-span-5 space-y-6">
            <!-- Kronologi Card -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-600 flex items-center justify-center font-bold text-sm">
                            📜
                        </div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Kronologi Kejadian</h3>
                    </div>
                    <span class="text-[11px] text-slate-400 font-mono"><?= $tglKejadian ?></span>
                </div>
                <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 text-xs leading-relaxed text-slate-700 dark:text-slate-300 whitespace-pre-wrap font-sans">
                    <?= e($operasi['kronologi'] ?: 'Kronologi belum diinput.') ?>
                </div>
            </div>

            <!-- Pelapor & Kondisi Lapangan Card -->
            <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-center gap-2.5 border-b border-slate-100 dark:border-slate-800 pb-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-sm">
                        ℹ️
                    </div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Informasi Pelapor & Lingkungan</h3>
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Pelapor</span>
                        <strong class="text-slate-900 dark:text-white block truncate"><?= e($operasi['nama_pelapor'] ?: '-') ?></strong>
                        <span class="text-[11px] text-slate-500">(<?= e($operasi['hubungan_pelapor'] ?: '-') ?>)</span>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">No. Kontak Pelapor</span>
                        <strong class="text-slate-900 dark:text-white font-mono block truncate"><?= e($operasi['no_telp_pelapor'] ?: '-') ?></strong>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Kondisi Cuaca</span>
                        <strong class="text-slate-900 dark:text-white block"><?= e($operasi['cuaca'] ?: '-') ?></strong>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Tinggi Gelombang</span>
                        <strong class="text-slate-900 dark:text-white block"><?= e($operasi['gelombang'] ?: '-') ?></strong>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Arah & Kec. Angin</span>
                        <strong class="text-slate-900 dark:text-white block"><?= e($operasi['angin'] ?: '-') ?></strong>
                    </div>
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800">
                        <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Faktor Penghambat</span>
                        <strong class="text-slate-900 dark:text-white block"><?= e($operasi['faktor_penghambat'] ?: 'Nihil') ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 5: KEKUATAN UNSUR & ALUT TERKINI -->
<!-- ========================================== -->
<div id="tab-content-kekuatan" class="tab-pane space-y-6" style="display: <?= $activeTab === 'kekuatan' ? 'block' : 'none' ?>;">
    
    <?php
        $totalPersKekuatan = array_sum(array_map(fn($u) => (int)($u['jumlah_personil'] ?? 1), $operasi['detail_unsur'] ?? []));
        $totalAlutKekuatan = array_sum(array_map(fn($a) => (int)($a['jumlah_alut'] ?? 1), $operasi['detail_alut'] ?? []));
    ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Left: Unsur SAR Terlibat -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-sm">
                        👥
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Unsur SAR Terlibat (Potensi Gabungan)</h3>
                        <p class="text-[11px] text-slate-400">Instansi dan potensi yang dikerahkan dalam operasi</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400">
                    Total: <?= $totalPersKekuatan ?> Personel
                </span>
            </div>

            <?php if (!empty($operasi['detail_unsur'])): ?>
            <div class="space-y-2">
                <?php foreach ($operasi['detail_unsur'] as $u): ?>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs">
                    <span class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                        <span><?= e($u['nama_unsur']) ?></span>
                    </span>
                    <span class="px-2.5 py-0.5 rounded-lg bg-purple-500/10 text-purple-600 dark:text-purple-400 font-mono font-bold text-xs">
                        <?= (int)($u['jumlah_personil'] ?? 1) ?> Org
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                <?= e($operasi['unsur_terlibat'] ?: '• Tim Rescue KPP Kendari') ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right: Alut & Peralatan SAR (Palsar) -->
        <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-600 flex items-center justify-center font-bold text-sm">
                        🚤
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Alut & Peralatan Khusus SAR</h3>
                        <p class="text-[11px] text-slate-400">Armada dan sarana pertolongan di lapangan</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-orange-500/10 text-orange-600 dark:text-orange-400">
                    Total: <?= $totalAlutKekuatan ?> Unit
                </span>
            </div>

            <?php if (!empty($operasi['detail_alut'])): ?>
            <div class="space-y-2">
                <?php foreach ($operasi['detail_alut'] as $a): ?>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs">
                    <span class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-orange-500"></span>
                        <span><?= e($a['nama_alut']) ?></span>
                    </span>
                    <span class="px-2.5 py-0.5 rounded-lg bg-orange-500/10 text-orange-600 dark:text-orange-400 font-mono font-bold text-xs">
                        <?= (int)($a['jumlah_alut'] ?? 1) ?> <?= e($a['satuan'] ?? 'Unit') ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400 whitespace-pre-line leading-relaxed">
                <?= e($operasi['alut_palsar'] ?: '• 1 Unit Rescue Car\n• 1 Unit Rubber Boat + Mopel') ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 6: BERITA RADIO SAR (BRA) -->
<!-- ========================================== -->
<div id="tab-content-bra" class="tab-pane space-y-6" style="display: <?= $activeTab === 'bra' ? 'block' : 'none' ?>;">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>📡</span>
                <span>Berita Radio SAR (BRA) Terkait Operasi Ini</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Dokumen resmi naskah telegram Basarnas dengan nomor urut satker SAR-304</p>
        </div>
        <a href="<?= BASE_URL ?>/bra_form.php?id_operasi=<?= $id ?>&from=laporan_awal" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-md shadow-orange-600/20 transition cursor-pointer shrink-0">
            <span>➕ Buat BRA SAR Baru</span>
        </a>
    </div>

    <?php if (empty($operasi['bra_sar'])): ?>
    <div class="p-8 sm:p-12 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm text-center">
        <div class="w-14 h-14 rounded-2xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center text-3xl mx-auto mb-3.5">
            📡
        </div>
        <h3 class="text-base font-bold text-slate-900 dark:text-white mb-1.5">Belum Ada Berita Radio SAR (BRA)</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto mb-4 leading-relaxed">
            Terbitkan naskah telegram resmi Basarnas untuk dikirimkan ke Kabasarnas dan jajaran pimpinan Senkom.
        </p>
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-orange-50 dark:bg-orange-950/40 border border-orange-200 dark:border-orange-900/50 text-orange-700 dark:text-orange-300 text-xs font-semibold">
            <span>💡</span>
            <span>Gunakan tombol <strong>➕ Buat BRA SAR Baru</strong> di sudut kanan atas untuk menerbitkan telegram.</span>
        </div>
    </div>
    <?php else: foreach ($operasi['bra_sar'] as $bra): ?>
    <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="flex items-center gap-2.5">
                <span class="px-2.5 py-1 rounded-xl bg-orange-500 text-white font-mono font-bold text-xs">
                    <?= e($bra['kategori']) ?>
                </span>
                <span class="font-mono font-black text-sm text-slate-900 dark:text-white">
                    NO: <?= e($bra['no_bra']) ?>
                </span>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/bra.php?view=<?= $bra['id_bra'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition">
                    👁️ <span>Lihat Dokumen</span>
                </a>
                <a href="<?= BASE_URL ?>/bra_form.php?id=<?= $bra['id_bra'] ?>" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-semibold text-xs hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                    ✏️ <span>Edit</span>
                </a>
            </div>
        </div>

        <div class="text-xs">
            <strong class="text-slate-900 dark:text-white">PERIHAL:</strong> <span class="text-slate-600 dark:text-slate-400"><?= e($bra['perihal'] ?: '-') ?></span>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-mono">
            <div>TWU: <strong class="text-slate-900 dark:text-white"><?= e($bra['twu']) ?></strong></div>
            <div>TWB: <strong class="text-slate-900 dark:text-white"><?= e($bra['twb']) ?></strong></div>
            <div>Jml Kata: <strong class="text-slate-900 dark:text-white"><?= e($bra['jumlah_kata']) ?></strong></div>
            <div>SMC: <strong class="text-slate-900 dark:text-white"><?= e($bra['nama_smc']) ?></strong></div>
        </div>

        <?php if (!empty($bra['sections'])): ?>
        <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-950/60 border border-slate-100 dark:border-slate-800/80 font-mono text-[11px] space-y-1">
            <div class="font-bold text-orange-600 dark:text-orange-400 uppercase text-[10px] mb-1">Naskah Telegram Radio (Preview):</div>
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

<!-- ========================================== -->
<!-- TAB 7: PETA TAKTIS OPERASI (GIS) -->
<!-- ========================================== -->
<div id="tab-content-gis" class="tab-pane space-y-4" style="display: <?= $activeTab === 'gis' ? 'block' : 'none' ?>;">
    <div class="rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span>🗺️</span>
                    <span>Peta Taktis Operasi SAR (Tactical GIS)</span>
                </h3>
                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">
                    LKP: <span class="font-bold text-slate-700 dark:text-slate-300"><?= e($operasi['koordinat']) ?></span> | Jarak dari Kansar: <strong class="text-orange-600"><?= e($operasi['jarak']) ?></strong>
                </div>
            </div>
            <a href="<?= BASE_URL ?>/gis.php" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold text-xs transition">
                Buka GIS Penuh ↗
            </a>
        </div>
        <div id="map-detail-ops" class="w-full" style="height: 520px;"></div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 8: CETAK & BERKAS RESMI -->
<!-- ========================================== -->
<div id="tab-content-cetak" class="tab-pane space-y-6" style="display: <?= $activeTab === 'cetak' ? 'block' : 'none' ?>;">
    <div class="p-5 sm:p-6 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div>
            <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white flex items-center gap-2">
                <span>🖨️</span>
                <span>Cetak Dokumen Resmi Operasi SAR</span>
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Pilih format berkas resmi yang ingin dicetak atau diekspor ke PDF:</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-600 flex items-center justify-center font-bold text-sm mb-2">📄</div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">1. Berkas Laporan Awal</h4>
                    <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">Format standar FILE AKTIF informasi kejadian dan pemberangkatan tim SAR.</p>
                </div>
                <button type="button" class="w-full py-2 rounded-xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs transition shadow-sm" onclick="printBerkas('laporan_awal')">🖨️ Cetak Dokumen</button>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold text-sm mb-2">🧭</div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">2. Rencana Operasi SAR</h4>
                    <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">Lembar RENOPS harian lengkap dengan pembagian sektor dan SRU.</p>
                </div>
                <button type="button" class="w-full py-2 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs transition shadow-sm" onclick="printBerkas('renops')">🖨️ Cetak Lembar RENOPS</button>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold text-sm mb-2">📡</div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">3. Berita Radio SAR</h4>
                    <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">Naskah telegram resmi Basarnas dengan verifikasi Barcode QR Digital.</p>
                </div>
                <?php if (!empty($operasi['bra_sar'])): ?>
                <a href="<?= BASE_URL ?>/bra.php?view=<?= $operasi['bra_sar'][0]['id_bra'] ?>" class="w-full py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs text-center transition shadow-sm">🖨️ Cetak Dokumen BRA</a>
                <?php else: ?>
                <span class="text-[11px] text-slate-400 text-center py-2">Belum ada BRA terbit</span>
                <?php endif; ?>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-100 dark:border-slate-800 flex flex-col justify-between space-y-3">
                <div>
                    <div class="w-8 h-8 rounded-xl bg-red-500/10 text-red-600 flex items-center justify-center font-bold text-sm mb-2">🔒</div>
                    <h4 class="font-bold text-xs text-slate-900 dark:text-white">4. Berita Acara Penutupan</h4>
                    <p class="text-[11px] text-slate-500 mt-1 leading-relaxed">Debriefing resmi usul penutupan operasi dan pelepasan unsur SAR.</p>
                </div>
                <button type="button" class="w-full py-2 rounded-xl bg-red-600 hover:bg-red-500 text-white font-bold text-xs transition shadow-sm" onclick="printBerkas('penutupan')">🖨️ Cetak Berita Acara</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: VIEW WA LAPORAN AWAL -->
<!-- ========================================== -->
<div id="modal-view-wa-awal" class="modal-overlay">
    <div class="modal-container" style="max-width: 760px; width: 95%;">
        <div class="modal-header">
            <div>
                <h3 style="font-weight: 800; font-size: 1.1rem; color: var(--text-main); margin: 0;">📱 Naskah Laporan Awal WhatsApp Resmi</h3>
                <p style="font-size: 0.775rem; color: var(--text-muted); margin: 0.25rem 0 0 0;"><?= e($operasi['nama_operasi']) ?></p>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-view-wa-awal')">✕</button>
        </div>
        <?php
            $awalWa = $operasi['full_text_wa'] ?? '';
            if (empty($awalWa) || strpos($awalWa, 'FILE AKTIF') === false) {
                $awalWa = TemplateHelper::generateLaporanAwalWA($operasi, $operasi['korban'] ?? [], $operasi['detail_unsur'] ?? [], $operasi['detail_alut'] ?? []);
            }
        ?>
        <div class="modal-body" style="padding: 1.25rem;">
            <pre id="modal-wa-awal-text" class="font-mono" style="background-color: var(--bg-card-subtle); padding: 1.25rem; border-radius: 0.75rem; font-size: 0.8rem; white-space: pre-wrap; line-height: 1.6; max-height: 55vh; overflow-y: auto; border: 1px solid var(--border-color); color: var(--text-main);"><?= e($awalWa) ?></pre>
        </div>
        <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.75rem; color: var(--text-muted);">Format Resmi Basarnas Siap Kirim</span>
            <div style="display: flex; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="closeModal('modal-view-wa-awal')">Tutup</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="copyDetailWA()">📋 Salin Teks</button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: CLOSE OPERATION DETAIL -->
<!-- ========================================== -->
<div id="modal-close-ops-detail" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3 style="font-weight: 800; font-size: 1.15rem; color: var(--danger);">🔒 Tutup Operasi SAR Resmi</h3>
            <button type="button" class="btn btn-secondary btn-sm" data-close-modal>✕</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/operasi.php?action=close">
            <input type="hidden" name="id_operasi" value="<?= $id ?>">
            <div class="modal-body">
                <p style="font-weight: 700; color: var(--text-main); margin-bottom: 1rem;">
                    <?= e($operasi['nama_operasi']) ?>
                </p>
                <div class="form-group">
                    <label class="form-label">Waktu Debriefing Selesai (WITA):</label>
                    <input type="datetime-local" name="waktu_selesai" class="form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                </div>
                <div style="background-color: var(--bg-card-subtle); padding: 0.85rem; border-radius: 0.5rem; font-size: 0.8rem; color: var(--text-muted);">
                    ⚠️ Seluruh unsur SAR yang terlibat diusulkan kembali ke kesatuannya masing-masing dengan ucapan terima kasih. Status operasi akan menjadi <strong>CLOSED</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
                <button type="submit" class="btn btn-danger">Tutup Operasi Sekarang</button>
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
                    ⚠️ Penguncian akan dibuka: Penambahan dan penyuntingan RENOPS, SITREP, serta naskah telegram resmi dapat dilakukan kembali.
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
    <input type="hidden" name="id_operasi" value="<?= $id ?>">
</form>
