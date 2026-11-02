<?php
/**
 * QR-SAR - Modals & Fallback Forms Detail Operasi SAR
 * Kantor Pencarian dan Pertolongan (Basarnas) Kendari
 */
?>
<!-- ======================================================== -->
<!-- MODAL: VIEW WA LAPORAN AWAL -->
<!-- ======================================================== -->
<div id="modal-view-wa-awal" class="modal-overlay">
    <div class="modal-container" style="max-width: 760px; width: 95%;">
        <div class="modal-header flex items-center justify-between border-b border-slate-100 dark:border-slate-800 p-4">
            <div class="min-w-0 pr-3">
                <h3 class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                    <svg class="w-4 h-4 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a.75.75 0 01-.774-.954l.583-2.183A7.95 7.95 0 013 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
                    <span>Naskah Laporan Awal WhatsApp Resmi</span>
                </h3>
                <p class="text-[11px] text-slate-400 mt-0.5 truncate" title="<?= e($operasi['nama_operasi']) ?>"><?= e($operasi['nama_operasi']) ?></p>
            </div>
            <button type="button" class="btn-tactile p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer shrink-0" onclick="closeModal('modal-view-wa-awal')" title="Tutup">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <?php
            $awalWa = trim((string)($operasi['full_text_wa'] ?? ''));
            if (empty($awalWa) || strpos($awalWa, 'FILE AKTIF') === false) {
                $awalWa = TemplateHelper::generateLaporanAwalWA(
                    $operasi, 
                    $operasi['korban'] ?? [], 
                    $operasi['detail_unsur'] ?? [], 
                    $operasi['detail_alut'] ?? []
                );
            }
        ?>
        <div class="modal-body p-4 overflow-y-auto flex-1">
            <pre id="wa-awal-modal-text" class="font-mono p-4 rounded-xl text-xs bg-slate-50 dark:bg-slate-950 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-800 leading-relaxed select-all whitespace-pre-wrap m-0"><?= e($awalWa) ?></pre>
        </div>
        <div class="modal-footer flex items-center justify-between p-4 border-t border-slate-100 dark:border-slate-800">
            <span class="text-[11px] text-slate-400 font-mono">Format Baku FILE AKTIF</span>
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
        <div class="modal-header flex items-center justify-between border-b border-slate-100 dark:border-slate-800 p-4">
            <h3 class="font-bold text-sm text-red-600 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                <span>Tutup Operasi SAR Resmi</span>
            </h3>
            <button type="button" class="btn-tactile p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition cursor-pointer shrink-0" data-close-modal title="Tutup">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/operasi.php?action=close">
            <?= csrfInput() ?>
            <input type="hidden" name="id_operasi" value="<?= $id ?>">
            <div class="modal-body space-y-3">
                <p class="font-bold text-xs text-slate-800 dark:text-slate-200">
                    <?= e($operasi['nama_operasi']) ?>
                </p>
                <div class="form-group">
                    <div class="field">
                      <label class="field-label">Waktu Debriefing Selesai (WITA):</label>
                      <input placeholder=" " type="datetime-local" name="waktu_selesai" class="field-input form-control" required value="<?= date('Y-m-d\TH:i') ?>">
                    </div>
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

<!-- Fallback Hidden Form for Rollback -->
<form id="form-fallback-rollback" method="POST" action="<?= BASE_URL ?>/operasi.php?action=rollback" style="display: none;">
    <?= csrfInput() ?>
    <input type="hidden" name="id_operasi" value="<?= $id ?>">
    <input type="hidden" name="waktu_reopen" id="fallback_waktu_reopen" value="">
</form>
