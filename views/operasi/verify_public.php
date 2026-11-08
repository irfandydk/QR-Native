<?php
/**
 * QR-SAR - Portal Verifikasi Keabsahan Dokumen Operasi SAR
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Tampilan publik hasil pemindaian QR Dokumen Rencana Operasi SAR (RENOPS).
 * Variabel disiapkan OperasiController::verifyDocument().
 */

declare(strict_types=1);

// Dokumen RENOPS format baru: SAH (Buka dokumen) atau DIUBAH (digantikan versi terbaru). versi 0 = QR portrait lama.
$renopsVerify = $renopsVerify ?? null;
$renopsDiubah = ($renopsVerify['status'] ?? '') === 'DIUBAH';
$smcNip = !empty($defaultSmc['nip']) ? $defaultSmc['nip'] : '-';
$smcPangkat = !empty($defaultSmc['pangkat']) ? $defaultSmc['pangkat'] : 'PEMBINA (IV/a)';
// RENOPS format baru: penandatangan beku dari versi yang dipindai, bukan SMC default saat ini.
$pengesahan = $renopsVerify['pengesahan'] ?? null;
if ($pengesahan) {
    $smcNip = $pengesahan['smc_nip'] !== '' ? $pengesahan['smc_nip'] : '-';
    $smcPangkat = $pengesahan['smc_pangkat'];
}
$smcJabatan = !empty($defaultSmc['jabatan']) ? $defaultSmc['jabatan'] : 'KEPALA SEKSI OPERASI DAN SIAGA';

$tglResmi = TemplateHelper::formatTanggalIndo($docTimestamp);
$jamResmi = TemplateHelper::formatJamWITA($docTimestamp);

$logoUrl = getAppLogoUrl();
?>

<div class="max-w-4xl mx-auto py-6 sm:py-10 px-4 space-y-6">
    <!-- Main Verification Success Banner -->
    <div class="rounded-3xl border-2 <?= $renopsDiubah ? 'border-amber-500/60' : 'border-emerald-500/40' ?> bg-white dark:bg-slate-900 shadow-2xl p-6 sm:p-10 relative overflow-hidden">
        <!-- Ambient Glow -->
        <div class="absolute -top-24 -right-24 w-72 h-72 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -left-24 w-72 h-72 bg-orange-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 text-center sm:text-left relative z-10">
            <!-- Official Shield Icon -->
            <?php if ($renopsDiubah): ?>
            <div data-verify-icon="diubah" class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-amber-500/15 border-2 border-amber-500/40 text-amber-600 dark:text-amber-400 flex items-center justify-center shrink-0 shadow-lg">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                </svg>
            </div>
            <?php else: ?>
            <div data-verify-icon="sah" class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl bg-emerald-500/15 border-2 border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 shadow-lg">
                <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <?php endif; ?>

            <!-- Header Titles -->
            <div class="flex-1 space-y-2">
                <?php if ($renopsDiubah): ?>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/40 text-amber-700 dark:text-amber-300 text-xs font-black tracking-wider uppercase">
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                    <span>DOKUMEN TELAH DIUBAH</span>
                </div>
                <?php else: ?>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-black tracking-wider uppercase">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>DOKUMEN RESMI TERVERIFIKASI &bull; SAH SECARA ELEKTRONIK</span>
                </div>
                <?php endif; ?>

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
                    <?php if (($renopsVerify['versi'] ?? 0) > 0): ?>
                    <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 font-bold">
                        Versi: v<?= (int)$renopsVerify['versi'] ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($renopsDiubah): ?>
        <!-- Dokumen RENOPS telah digantikan versi terbaru -->
        <div class="mt-6 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/40 text-sm text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="font-semibold">Versi <?= $renopsVerify['versi'] > 0 ? 'v' . (int)$renopsVerify['versi'] : 'dokumen lama' ?> telah digantikan oleh v<?= (int)$renopsVerify['latest_versi'] ?>.</p>
            <a href="<?= e($renopsVerify['open_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl font-bold text-xs text-slate-950 bg-amber-500 hover:bg-amber-400 shadow-md transition no-underline shrink-0">
                <span>Klik di sini untuk membuka dokumen</span>
            </a>
        </div>
        <?php endif; ?>

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
                <?php if ($pengesahan && $pengesahan['tanggal'] !== ''): ?>
                <span class="text-[11px] text-slate-500 block">Disahkan <?= e(trim($pengesahan['kota'] . ', ' . TemplateHelper::formatTanggalSimple($pengesahan['tanggal']), ', ')) ?></span>
                <?php endif; ?>
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
        <div class="mt-6 p-4 rounded-2xl <?= $renopsDiubah ? 'bg-amber-500/10 border border-amber-500/30' : 'bg-emerald-500/10 border border-emerald-500/20' ?> text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
            <div class="flex items-center gap-2 font-bold <?= $renopsDiubah ? 'text-amber-800 dark:text-amber-300' : 'text-emerald-800 dark:text-emerald-300' ?> mb-1">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Pernyataan Keabsahan Elektronik:</span>
            </div>
            <?php if ($renopsDiubah): ?>
            <p class="font-semibold text-amber-900 dark:text-amber-200">Versi ini telah digantikan; gunakan versi terbaru.</p>
            <?php else: ?>
            <p>
                Dokumen ini merupakan warkat resmi operasional yang diterbitkan melalui Sistem Komando & Informasi Operasi SAR (QR-SAR) Kantor Pencarian dan Pertolongan Kelas A Kendari. Tanda tangan barcode digital yang tertera telah divalidasi keasliannya berdasarkan kunci kriptografis instansi dan tercatat sah dalam pangkalan data resmi Basarnas Republik Indonesia.
            </p>
            <?php endif; ?>
        </div>

        <!-- Action Buttons -->
        <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3">
                <?php if ($renopsVerify && !$renopsDiubah): ?>
                <a href="<?= e($renopsVerify['open_url']) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-orange-600 hover:bg-orange-700 shadow-md transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    <span>Buka dokumen</span>
                </a>
                <?php elseif (!$renopsVerify && AuthHelper::isLoggedIn()): ?>
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
