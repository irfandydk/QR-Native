<?php
/**
 * QR-SAR - Halaman Verifikasi Dokumen BRA Tidak Sah / Tidak Ditemukan
 * Basarnas KPP Kendari
 * Tampilan Khusus Akses Publik / Scanner QR yang Tidak Lolos Audit Integritas
 */

declare(strict_types=1);

$refId = $invalidRef ?? htmlspecialchars((string)($_GET['view'] ?? '-'), ENT_QUOTES, 'UTF-8');
$tokenParam = $invalidToken ?? (isset($_GET['token']) ? htmlspecialchars((string)$_GET['token'], ENT_QUOTES, 'UTF-8') : null);
$reason = $verificationMessage ?? 'Parameter dokumen tidak valid atau naskah tidak terdaftar dalam pangkalan data resmi Basarnas.';
$timestamp = date('d F Y, H:i:s') . ' WITA';
?>

<div class="max-w-3xl mx-auto py-4 sm:py-8 space-y-6">
    <!-- Main Warning Card -->
    <div class="rounded-3xl border-2 border-rose-500/40 bg-white dark:bg-slate-900 shadow-2xl p-6 sm:p-10 text-center relative overflow-hidden">
        <!-- Background Ambient Glow -->
        <div class="absolute -top-24 -left-24 w-60 h-60 bg-rose-500/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-24 -right-24 w-60 h-60 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Shield Warning Icon -->
        <div class="mx-auto w-20 h-20 rounded-2xl bg-rose-500/15 text-rose-600 dark:text-rose-400 flex items-center justify-center mb-6 shadow-inner border border-rose-500/30">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
        </div>

        <!-- Badge Status -->
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-bold tracking-wider uppercase mb-3">
            <span class="w-2 h-2 rounded-full bg-rose-600 dark:bg-rose-400"></span>
            <span>VERIFIKASI GAGAL &bull; DOKUMEN TIDAK SAH</span>
        </div>

        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
            <?= htmlspecialchars($invalidTitle ?? 'Naskah Berita Radio SAR Tidak Terverifikasi', ENT_QUOTES, 'UTF-8') ?>
        </h1>
        
        <p class="text-sm text-slate-600 dark:text-slate-300 mt-2 max-w-xl mx-auto leading-relaxed">
            <?= htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <!-- Audit Metadata Details Grid -->
        <div class="mt-8 grid grid-cols-1 sm:grid-cols-3 gap-3 text-left">
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">ID Pelacakan</span>
                <span class="font-mono font-bold text-xs sm:text-sm text-slate-800 dark:text-slate-200 mt-0.5 block truncate">
                    <?= !empty($refId) ? $refId : '-' ?>
                </span>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Status Kriptografis</span>
                <span class="font-mono font-bold text-xs text-rose-600 dark:text-rose-400 mt-0.5 block truncate">
                    <?= !empty($tokenParam) ? 'TOKEN_MISMATCH' : 'RECORD_NOT_FOUND' ?>
                </span>
            </div>

            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/80">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 block">Waktu Pemeriksaan</span>
                <span class="font-mono text-xs text-slate-700 dark:text-slate-300 mt-0.5 block">
                    <?= $timestamp ?>
                </span>
            </div>
        </div>

        <!-- Security Education Notice -->
        <div class="mt-6 p-4 rounded-2xl bg-amber-500/10 border border-amber-500/20 text-left text-xs space-y-2">
            <div class="flex items-center gap-2 font-bold text-amber-700 dark:text-amber-400">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Panduan Keamanan Dokumen Resmi Basarnas:</span>
            </div>
            <ul class="list-disc list-inside text-slate-600 dark:text-slate-300 space-y-1 pl-1 text-[11px] leading-relaxed">
                <li>Dokumen resmi Berita Radio SAR (BRA) selalu memiliki Nomor Registrasi resmi dan Tanda Tangan Barcode QR yang terdaftar di server Basarnas KPP Kendari.</li>
                <li>Jika Anda menerima lembaran cetak dengan QR Code ini, waspadai adanya upaya pemalsuan dokumen atau penyuntingan nomor secara sepihak.</li>
                <li>Untuk verifikasi keabsahan operasi pencarian dan pertolongan, silakan hubungi langsung Sentra Komunikasi (SENKOM) KPP Kendari.</li>
            </ul>
        </div>

        <!-- Action Buttons (Safe Public Only, Zero Internal Links) -->
        <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center justify-center gap-3">
            <a href="tel:115" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-bold text-xs text-white bg-orange-600 hover:bg-orange-700 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <span>Hubungi Call Center 115 (Darurat Basarnas)</span>
            </a>

            <button type="button" onclick="copyAuditReport()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-medium text-xs border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg>
                <span id="btnCopyReportText">Salin Laporan Audit</span>
            </button>
        </div>
    </div>
</div>

<script>
function copyAuditReport() {
    const reportText = "LAPORAN AUDIT VERIFIKASI QR BRA SAR BASARNAS\n" +
        "Status: GAGAL TERVERIFIKASI / TIDAK SAH\n" +
        "ID Dokumen: <?= addslashes((string)$refId) ?>\n" +
        "Token: <?= addslashes((string)($tokenParam ?? '-')) ?>\n" +
        "Waktu: <?= addslashes($timestamp) ?>\n" +
        "Keterangan: <?= addslashes($reason) ?>\n" +
        "Layanan: Kantor Pencarian dan Pertolongan Kelas A Kendari (KPP Kendari)";

    navigator.clipboard.writeText(reportText).then(() => {
        const btn = document.getElementById('btnCopyReportText');
        if (btn) btn.textContent = 'Laporan Berhasil Disalin!';
        if (typeof showToast === 'function') {
            showToast('Laporan audit berhasil disalin ke clipboard!', 'success');
        }
        setTimeout(() => {
            if (btn) btn.textContent = 'Salin Laporan Audit';
        }, 3000);
    });
}
</script>
