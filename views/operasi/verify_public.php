<?php
/**
 * QR-SAR - Portal Verifikasi Keabsahan Dokumen Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Tampilan Publik Resmi untuk Pemindaian Barcode / QR Code Dokumen:
 * - Laporan Awal Operasi SAR
 * - Rencana Operasi SAR (RENOPS)
 * - Laporan Perkembangan SAR (SITREP)
 * - Berita Acara Pengusulan Penutupan Operasi SAR
 */

declare(strict_types=1);

$docTitle = $docTitle ?? DocVerifyHelper::getDocTitle($type, $operasi, $docData);
$docNumber = $docNumber ?? DocVerifyHelper::getDocNumber($type, $operasi, $docData, $satkerKode ?? 'SAR-304');
$docTimestamp = $docTimestamp ?? DocVerifyHelper::getDocTimestamp($type, $operasi, $docData);
$smcName = $smcName ?? DocVerifyHelper::getSmcName($defaultSmc, $docData, $operasi);
$smcNip = !empty($defaultSmc['nip']) ? $defaultSmc['nip'] : '-';
$smcPangkat = !empty($defaultSmc['pangkat']) ? $defaultSmc['pangkat'] : 'PEMBINA (IV/a)';
$smcJabatan = !empty($defaultSmc['jabatan']) ? $defaultSmc['jabatan'] : 'KEPALA SEKSI OPERASI DAN SIAGA';

$tglResmi = TemplateHelper::formatTanggalIndo($docTimestamp);
$jamResmi = TemplateHelper::formatJamWITA($docTimestamp);

$printUrl = $printUrl ?? DocVerifyHelper::getPrintUrl($type, (int)$operasi['id_operasi'], (int)($docData['id_renops'] ?? ($docData['id_laporan'] ?? 0)));
$logoUrl = getAppLogoUrl();
?>

<div class="max-w-4xl mx-auto py-6 sm:py-10 px-4 space-y-6">
    <!-- Main Verification Success Banner -->
    <div class="rounded-3xl border-2 border-emerald-500/40 bg-white dark:bg-slate-900 shadow-2xl p-6 sm:p-10 relative overflow-hidden">
        <!-- Ambient Glow -->
        <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 text-center sm:text-left relative z-10">
            <!-- Official Shield Icon -->
            <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-emerald-500/15 border-2 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 shadow-lg">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>

            <!-- Header Titles -->
            <div class="flex-1 space-y-2">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-black tracking-wider uppercase">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>DOKUMEN RESMI TERVERIFIKASI &bull; SAH SECARA ELEKTRONIK</span>
                </div>

                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
                    <?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?>
                </h1>

                <p class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                    Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari &bull; Badan Nasional Pencarian dan Pertolongan (Basarnas)
                </p>

                <div class="pt-1 flex flex-wrap items-center justify-center sm:justify-start gap-2 text-xs font-mono">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 font-bold">
                        No. Registrasi: <?= htmlspecialchars($docNumber, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 font-bold">
                        Token: <?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Metadata Grid -->
        <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <!-- Nama Operasi -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Nama Operasi SAR</span>
                <span class="font-bold text-sm text-slate-900 dark:text-white mt-1 block">
                    <?= htmlspecialchars($operasi['nama_operasi'], ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>

            <!-- Waktu Pengesahan -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Tanggal & Jam Terbit</span>
                <span class="font-bold text-sm text-slate-900 dark:text-white mt-1 block">
                    <?= $tglResmi ?>
                </span>
                <span class="text-xs text-slate-500 font-mono"><?= $jamResmi ?> WITA</span>
            </div>

            <!-- Pejabat SMC -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">SAR Mission Coordinator</span>
                <span class="font-bold text-sm text-slate-900 dark:text-white mt-1 block">
                    <?= htmlspecialchars($smcName, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="text-[11px] text-slate-500 block truncate">
                    <?= htmlspecialchars($smcPangkat, ENT_QUOTES, 'UTF-8') ?><?= !empty($smcNip) && $smcNip !== '-' ? ' &bull; NIP. ' . htmlspecialchars($smcNip, ENT_QUOTES, 'UTF-8') : '' ?>
                </span>
            </div>

            <!-- Status Operasi -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Status Operasi SAR</span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-black uppercase mt-1 <?= ($operasi['status'] ?? 'OPEN') === 'CLOSED' ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' ?>">
                    <span class="w-1.5 h-1.5 rounded-full <?= ($operasi['status'] ?? 'OPEN') === 'CLOSED' ? 'bg-rose-500' : 'bg-emerald-500' ?>"></span>
                    <?= ($operasi['status'] ?? 'OPEN') === 'CLOSED' ? 'DITUTUP (CLOSED)' : 'AKTIF (OPEN)' ?>
                </span>
            </div>
        </div>

        <!-- Legal Statement Card -->
        <div class="mt-6 p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
            <div class="flex items-center gap-2 font-bold text-emerald-800 dark:text-emerald-300 mb-1">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Pernyataan Keabsahan Elektronik:</span>
            </div>
            <p>
                Dokumen ini merupakan warkat resmi operasional yang diterbitkan melalui Sistem Komando & Informasi Operasi SAR (QR-SAR) Kantor Pencarian dan Pertolongan Kelas A Kendari. Tanda tangan barcode digital yang tertera telah divalidasi keasliannya berdasarkan kunci kriptografis instansi dan tercatat sah dalam pangkalan data resmi Basarnas Republik Indonesia.
            </p>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <?php if (AuthHelper::isLoggedIn()): ?>
                <a href="<?= e($printUrl) ?>" target="_blank" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-orange-600 hover:bg-orange-700 shadow-md transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    <span>Buka Lembar Naskah Dinas Resmi (Cetak / PDF)</span>
                </a>
                <?php endif; ?>

                <a href="tel:115" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-xs border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                    <svg class="w-4 h-4 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>Call Center 115 Basarnas</span>
                </a>
            </div>

            <span class="text-[11px] text-slate-600 dark:text-slate-300 font-mono">
                KPP KENDARI &bull; QR-SAR SECURE VERIFIED
            </span>
        </div>
    </div>
</div>
