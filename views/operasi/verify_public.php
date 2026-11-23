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

<div class="w-full max-w-2xl mx-auto">
    <!-- Minimal Institutional Verification Credential Card -->
    <section class="rounded-2xl border <?= $renopsDiubah ? 'border-amber-500/40' : 'border-slate-200 dark:border-slate-800' ?> bg-white dark:bg-slate-900 shadow-lg overflow-hidden">
        <!-- Top Status Accent Strip -->
        <div class="h-1.5 w-full <?= $renopsDiubah ? 'bg-amber-500' : 'bg-emerald-600' ?>"></div>

        <div class="p-6 sm:p-8 space-y-6">
            <!-- Header: Seal + Status + Operation Name + Renops Day -->
            <div class="flex items-start gap-4">
                <?php if ($renopsDiubah): ?>
                <div data-verify-icon="diubah" class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/35 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <?php else: ?>
                <div data-verify-icon="sah" class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <?php endif; ?>

                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <?php if ($renopsDiubah): ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-amber-500/10 border border-amber-500/35 text-amber-800 dark:text-amber-300 text-[11px] font-bold tracking-wider uppercase">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600 dark:bg-amber-400 shrink-0"></span>
                            <span>DOKUMEN TELAH DIUBAH</span>
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-emerald-700 text-white text-[11px] font-bold tracking-wider uppercase">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-200 shrink-0"></span>
                            <span>SAH SECARA ELEKTRONIK</span>
                        </span>
                        <?php endif; ?>

                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-[11px] font-semibold uppercase tracking-wide <?= ($operasi['status'] ?? 'OPEN') === 'CLOSED' ? 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/25' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700' ?>">
                            <?= ($operasi['status'] ?? 'OPEN') === 'CLOSED' ? 'DITUTUP (CLOSED)' : 'AKTIF (OPEN)' ?>
                        </span>
                    </div>

                    <h1 class="verify-heading text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug">
                        <?= htmlspecialchars($operasi['nama_operasi'], ENT_QUOTES, 'UTF-8') ?>
                    </h1>

                    <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 flex flex-wrap items-center gap-2">
                        <span><?= htmlspecialchars($docTitle, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (($renopsVerify['versi'] ?? 0) > 0): ?>
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 tabular-nums">
                            v<?= (int)$renopsVerify['versi'] ?>
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if ($renopsDiubah): ?>
            <!-- Version Replacement Banner -->
            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/35 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <p class="verify-prose text-xs sm:text-sm font-semibold text-amber-950 dark:text-amber-200">
                    Versi <?= $renopsVerify['versi'] > 0 ? 'v' . (int)$renopsVerify['versi'] : 'dokumen lama' ?> telah digantikan oleh v<?= (int)$renopsVerify['latest_versi'] ?>.
                </p>
                <a href="<?= e($renopsVerify['open_url']) ?>" target="_blank" rel="noopener" class="btn-tactile inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl font-semibold text-xs text-slate-950 bg-amber-400 hover:bg-amber-300 transition-colors no-underline shrink-0">
                    <span>Klik di sini untuk membuka dokumen</span>
                </a>
            </div>
            <?php endif; ?>

            <!-- Gapless Credential Ledger: Token/Hash + Tanggal Terbit + Structured SMC -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-slate-50/80 dark:bg-slate-950/50 divide-y divide-slate-200 dark:divide-slate-800">
                <!-- Row 1: Token / Hash & Tanggal Terbit (No Time) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 grid-flow-dense gap-px bg-slate-200 dark:bg-slate-800">
                    <div class="p-4 bg-slate-50/95 dark:bg-slate-900/95">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Token / Hash</span>
                        <span class="font-mono font-bold text-sm text-slate-900 dark:text-slate-100 tabular-nums mt-1 block break-all">
                            <?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="p-4 bg-slate-50/95 dark:bg-slate-900/95">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Tanggal Terbit</span>
                        <span class="font-bold text-sm text-slate-900 dark:text-slate-100 mt-1 block">
                            <?= $tglResmi ?>
                        </span>
                    </div>
                </div>

                <!-- Row 2: SAR Mission Coordinator (Each Field on Its Own Clean Row) -->
                <div class="p-4 sm:p-5 bg-white dark:bg-slate-900/70">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 block mb-3">
                        SAR Mission Coordinator (SMC)
                    </span>
                    <dl class="divide-y divide-slate-100 dark:divide-slate-800/80 text-xs sm:text-sm">
                        <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                            <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">Nama</dt>
                            <dd class="sm:col-span-2 font-bold text-slate-900 dark:text-white">
                                <?= htmlspecialchars($smcName, ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                        <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                            <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">Pangkat / Golongan</dt>
                            <dd class="sm:col-span-2 font-semibold text-slate-800 dark:text-slate-200">
                                <?= htmlspecialchars($smcPangkat, ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                        <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                            <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">NIP</dt>
                            <dd class="sm:col-span-2 font-mono font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                                <?= htmlspecialchars($smcNip, ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Concise Status Line & Primary Document Action -->
        <div class="px-6 sm:px-8 py-4 bg-slate-50/90 dark:bg-slate-950/60 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <?php if ($renopsDiubah): ?>
            <p class="verify-prose text-xs font-semibold text-amber-800 dark:text-amber-300">
                Versi ini telah digantikan; gunakan versi terbaru.
            </p>
            <?php else: ?>
            <p class="verify-prose text-xs text-slate-600 dark:text-slate-400">
                Dokumen ini tercatat sah dalam sistem QR-SAR KPP Kelas A Kendari.
            </p>
            <?php endif; ?>

            <?php if ($renopsVerify && !$renopsDiubah): ?>
            <a href="<?= e($renopsVerify['open_url']) ?>" target="_blank" rel="noopener" class="btn-tactile inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-xs text-white bg-orange-700 hover:bg-orange-800 shadow-xs transition-colors shrink-0">
                <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                <span>Buka dokumen</span>
            </a>
            <?php elseif (!$renopsVerify && AuthHelper::isLoggedIn()): ?>
            <a href="<?= e($printUrl) ?>" target="_blank" class="btn-tactile inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-xs text-white bg-orange-700 hover:bg-orange-800 shadow-xs transition-colors shrink-0">
                <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                <span>Buka Dokumen (Cetak / PDF)</span>
            </a>
            <?php endif; ?>
        </div>
    </section>
</div>
