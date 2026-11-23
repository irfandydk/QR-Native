<?php
/**
 * QR-SAR - Halaman Verifikasi Dokumen BRA Tidak Sah / Tidak Ditemukan
 * Basarnas KPP Kendari
 * Tampilan Khusus Akses Publik / Scanner QR yang Tidak Lolos Audit Integritas
 */

declare(strict_types=1);

if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Akses langsung ditolak.');
}

$refId = $invalidRef ?? htmlspecialchars((string)($_GET['view'] ?? '-'), ENT_QUOTES, 'UTF-8');
$tokenParam = $invalidToken ?? (isset($_GET['token']) ? htmlspecialchars((string)$_GET['token'], ENT_QUOTES, 'UTF-8') : null);
$reason = $verificationMessage ?? 'Parameter dokumen tidak valid atau naskah tidak terdaftar dalam pangkalan data resmi Basarnas.';
$timestamp = date('d F Y, H:i:s') . ' WITA';
?>

<div class="w-full max-w-2xl mx-auto">
    <!-- Minimal Invalid Verification Card -->
    <section class="rounded-2xl border border-rose-500/35 bg-white dark:bg-slate-900 shadow-lg overflow-hidden">
        <!-- Top Alert Accent Strip -->
        <div class="h-1.5 w-full bg-rose-600"></div>

        <div class="p-6 sm:p-8 space-y-6">
            <!-- Header: Warning Seal + Status + Title + Reason -->
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 rounded-xl bg-rose-500/10 text-rose-700 dark:text-rose-400 flex items-center justify-center shrink-0 border border-rose-500/30 mt-0.5">
                    <svg class="w-6 h-6" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>

                <div class="min-w-0 flex-1 space-y-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-lg bg-rose-600 text-white text-[11px] font-bold tracking-wider uppercase">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-200 shrink-0"></span>
                        <span>VERIFIKASI GAGAL &bull; DOKUMEN TIDAK SAH</span>
                    </span>

                    <h1 class="verify-heading text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight leading-snug">
                        <?= htmlspecialchars($invalidTitle ?? 'Naskah Berita Radio SAR Tidak Terverifikasi', ENT_QUOTES, 'UTF-8') ?>
                    </h1>

                    <p class="verify-prose text-xs sm:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                        <?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                </div>
            </div>

            <!-- Clean Key-Value Cryptographic Audit Ledger (Zero Truncation) -->
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/95 dark:bg-slate-900/70 p-4 sm:p-5 text-left">
                <dl class="divide-y divide-slate-200/80 dark:divide-slate-800 text-xs sm:text-sm">
                    <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                        <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">ID Pelacakan</dt>
                        <dd class="sm:col-span-2 font-mono font-bold text-slate-900 dark:text-slate-100 break-words">
                            <?= !empty($refId) ? $refId : '-' ?>
                        </dd>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                        <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">Status Kriptografis</dt>
                        <dd class="sm:col-span-2 font-mono font-bold text-rose-700 dark:text-rose-400 break-words">
                            <?= !empty($tokenParam) ? 'TOKEN_MISMATCH' : 'RECORD_NOT_FOUND' ?>
                        </dd>
                    </div>
                    <div class="py-2 first:pt-0 last:pb-0 grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-4 items-baseline">
                        <dt class="text-xs font-medium text-slate-600 dark:text-slate-400">Waktu Pemeriksaan</dt>
                        <dd class="sm:col-span-2 font-mono font-semibold text-slate-800 dark:text-slate-200 tabular-nums">
                            <?= $timestamp ?>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Minimal Footer Note -->
        <div class="px-6 sm:px-8 py-3.5 bg-slate-50/90 dark:bg-slate-950/60 border-t border-slate-200 dark:border-slate-800">
            <p class="text-xs text-slate-600 dark:text-slate-400">
                Pindai ulang kode QR pada lembar resmi jika terjadi kesalahan tautan.
            </p>
        </div>
    </section>
</div>
