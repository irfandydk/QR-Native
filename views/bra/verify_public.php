<?php
/**
 * QR-SAR - Portal Verifikasi Keabsahan Berita Radio SAR (BRA)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Halaman khusus verifikasi hasil pemindaian QR BRA SAR (terpisah dari lembar naskah).
 * Variabel disiapkan BraController::verifyDocument().
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$braKategoriLabel = ($bra['kategori'] ?? '') === 'MAPEL'
    ? 'BRA MAPEL'
    : (($bra['kategori'] ?? '') === 'PERGERAKAN_KAPAL' ? 'BRA KAPAL' : 'BRA OPERASI SAR');

$braTitleDisplay = trim((string)($bra['nama_operasi'] ?? '')) !== ''
    ? (string)$bra['nama_operasi']
    : (trim((string)($bra['perihal'] ?? '')) !== '' ? (string)$bra['perihal'] : 'Berita Radio SAR (BRA)');

$braTanggalTerbit = !empty($bra['created_at'])
    ? TemplateHelper::formatTanggalIndo((string)$bra['created_at'])
    : (!empty($bra['twu']) ? (string)$bra['twu'] : '-');

$docHashDisplay = $docHash ?? BraModel::generateVerifyToken($bra);
$openDocUrl = $publicDocUrl ?? BraModel::generatePublicDocUrl($bra);
?>

<div class="w-full max-w-2xl mx-auto">
    <!-- Minimal Institutional Verification Credential Card (Identical System to RENOPS Verify) -->
    <section class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 shadow-lg overflow-hidden">
        <!-- Top Status Accent Strip -->
        <div class="h-1.5 w-full bg-emerald-600"></div>

        <div class="p-6 sm:p-8 space-y-6">
            <!-- Header: Seal + Status + Operation/Subject Title + BRA Subtitle -->
            <div class="flex items-start gap-4">
                <div data-verify-icon="sah" class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>

                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-emerald-700 text-white text-[11px] font-bold tracking-wider uppercase">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-200 shrink-0"></span>
                            <span>SAH SECARA ELEKTRONIK</span>
                        </span>

                        <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-lg text-[11px] font-semibold uppercase tracking-wide bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                            <?= htmlspecialchars($braKategoriLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <h1 class="verify-heading text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug">
                        <?= htmlspecialchars($braTitleDisplay, ENT_QUOTES, 'UTF-8') ?>
                    </h1>

                    <p class="text-sm font-semibold text-slate-600 dark:text-slate-300 flex flex-wrap items-center gap-2">
                        <span>Berita Radio SAR (BRA)</span>
                        <?php if (!empty($bra['no_bra'])): ?>
                        <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-[11px] font-mono font-bold text-slate-700 dark:text-slate-200 tabular-nums">
                            <?= htmlspecialchars((string)$bra['no_bra'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- Gapless Credential Ledger: Token/Hash + Tanggal Terbit + Structured SMC -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-slate-50/80 dark:bg-slate-950/50 divide-y divide-slate-200 dark:divide-slate-800">
                <!-- Row 1: Token / Hash & Tanggal Terbit (No Time) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 grid-flow-dense gap-px bg-slate-200 dark:bg-slate-800">
                    <div class="p-4 bg-slate-50/95 dark:bg-slate-900/95">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Token / Hash</span>
                        <span class="font-mono font-bold text-sm text-slate-900 dark:text-slate-100 tabular-nums mt-1 block break-all">
                            <?= htmlspecialchars($docHashDisplay, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div class="p-4 bg-slate-50/95 dark:bg-slate-900/95">
                        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400 block">Tanggal Terbit</span>
                        <span class="font-bold text-sm text-slate-900 dark:text-slate-100 mt-1 block">
                            <?= htmlspecialchars($braTanggalTerbit, ENT_QUOTES, 'UTF-8') ?>
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
                                <?= htmlspecialchars((string)(!empty($bra['nama_smc']) ? $bra['nama_smc'] : 'KEPALA KANSAR KENDARI'), ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                        <?php if (!empty($bra['pangkat_smc'])): ?>
                        <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                            <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">Pangkat / Golongan</dt>
                            <dd class="sm:col-span-2 font-semibold text-slate-800 dark:text-slate-200">
                                <?= htmlspecialchars((string)$bra['pangkat_smc'], ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($bra['jabatan_smc'])): ?>
                        <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                            <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">Jabatan</dt>
                            <dd class="sm:col-span-2 font-semibold text-slate-800 dark:text-slate-200">
                                <?= htmlspecialchars((string)$bra['jabatan_smc'], ENT_QUOTES, 'UTF-8') ?>
                            </dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Concise Status Line & Primary Document Action -->
        <div class="px-6 sm:px-8 py-4 bg-slate-50/90 dark:bg-slate-950/60 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <p class="verify-prose text-xs text-slate-600 dark:text-slate-400">
                Dokumen ini tercatat sah dalam sistem QR-SAR KPP Kelas A Kendari.
            </p>

            <a href="<?= e($openDocUrl) ?>" target="_blank" rel="noopener" class="btn-tactile inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-xs text-white bg-orange-700 hover:bg-orange-800 shadow-xs transition-colors shrink-0">
                <svg class="w-4 h-4 shrink-0" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                <span>Buka dokumen</span>
            </a>
        </div>
    </section>
</div>
