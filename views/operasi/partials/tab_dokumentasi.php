<?php
/**
 * QR-SAR - Tab Dokumentasi Kolase Operasi SAR (Foto & Video)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 */

declare(strict_types=1);

$dokItems = $operasi['dokumentasi'] ?? [];
$studioUrl = BASE_URL . '/operasi.php?action=dokumentasi&id=' . (int)$id;
$formatBytes = static function (int $bytes): string {
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 0) . ' KB';
    }
    return $bytes . ' B';
};
?>
<div id="tab-content-dokumentasi" class="tab-pane space-y-3.5" style="display: <?= $activeTab === 'dokumentasi' ? 'block' : 'none' ?>;">
  <?php if (empty($dokItems)): ?>
    <div class="opsd-panel p-10 text-center rounded-2xl space-y-3">
      <div class="w-11 h-11 rounded-2xl bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 flex items-center justify-center mx-auto border border-slate-200 dark:border-slate-700">
        <svg class="w-5 h-5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg>
      </div>
      <div class="max-w-md mx-auto space-y-1">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Belum Ada Dokumentasi Kolase Operasi SAR</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
          Buat kolase foto atau video dokumentasi operasi berformat resmi KPP Kelas A Kendari (4 hingga 12 slot, vertikal maupun horizontal).
        </p>
      </div>
      <div class="pt-1">
        <a href="<?= e($studioUrl) ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-9 px-4 rounded-xl font-semibold text-xs transition no-underline">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
          <span>Buat Dokumentasi Kolase</span>
        </a>
      </div>
    </div>
  <?php else: ?>
    <div class="opsd-panel rounded-2xl overflow-hidden">
      <div class="px-4 sm:px-5 py-3 border-b border-slate-200/80 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/35 flex flex-wrap items-center justify-between gap-2.5">
        <div class="flex items-center gap-2">
          <h3 class="text-sm font-bold text-slate-900 dark:text-white">Arsip Dokumentasi Kolase Operasi SAR</h3>
          <span id="dok-archive-count-pill" class="opsd-num px-2 py-0.5 rounded-md font-mono text-[11px] font-semibold bg-slate-200/70 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
            <?= count($dokItems) ?> berkas
          </span>
        </div>
        <a href="<?= e($studioUrl) ?>" class="opsd-accent-btn inline-flex items-center gap-1.5 h-8 px-3.5 rounded-lg font-semibold text-xs transition shrink-0 no-underline no-print">
          <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12 5v14"/><path d="M5 12h14"/></svg>
          <span>Buat Dokumentasi Baru</span>
        </a>
      </div>

      <div id="dok-archive-grid" class="p-4 sm:p-5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($dokItems as $dok):
          $dokId = (int)$dok['id_dokumentasi'];
          $isVideo = ($dok['media_type'] ?? 'image') === 'video';
          $fileUrl = BASE_URL . '/' . ltrim((string)$dok['file_path'], '/');
          $thumbUrl = !empty($dok['thumb_path'])
              ? BASE_URL . '/' . ltrim((string)$dok['thumb_path'], '/')
              : (!$isVideo ? $fileUrl : '');
          $ext = pathinfo((string)$dok['file_path'], PATHINFO_EXTENSION) ?: ($isVideo ? 'webm' : 'jpg');
          $downloadName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', (string)($dok['judul'] ?: 'DOK_SAR')) . '.' . $ext;
        ?>
        <article data-dok-card="<?= $dokId ?>" class="opsd-row-card rounded-xl border border-slate-200/90 dark:border-slate-800 bg-white dark:bg-slate-900/70 overflow-hidden flex flex-col">
          <button type="button"
                  onclick="openDokPreviewModal(this.dataset.url, this.dataset.type, this.dataset.title)"
                  data-url="<?= e($fileUrl) ?>"
                  data-type="<?= $isVideo ? 'video' : 'image' ?>"
                  data-title="<?= e((string)$dok['judul']) ?>"
                  class="relative aspect-[4/3] w-full bg-slate-100 dark:bg-slate-950 overflow-hidden flex items-center justify-center group cursor-pointer border-b border-slate-100 dark:border-slate-800">
            <?php if ($thumbUrl !== ''): ?>
              <img src="<?= e($thumbUrl) ?>" alt="<?= e((string)$dok['judul']) ?>" loading="lazy" class="w-full h-full object-contain group-hover:scale-[1.02] transition-transform duration-300" />
            <?php else: ?>
              <video src="<?= e($fileUrl) ?>" muted playsinline preload="metadata" class="w-full h-full object-contain"></video>
            <?php endif; ?>

            <span class="absolute top-2.5 left-2.5 inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider <?= $isVideo ? 'bg-slate-900/90 text-orange-300' : 'bg-slate-900/85 text-white' ?>">
              <?= $isVideo ? 'VIDEO' : 'FOTO' ?>
            </span>

            <span class="absolute top-2.5 right-2.5 px-2 py-0.5 rounded-md bg-white/90 dark:bg-slate-900/90 text-slate-800 dark:text-slate-200 font-mono text-[10px] font-bold border border-slate-200/80 dark:border-slate-700/80">
              <?= e((string)($dok['aspect_ratio'] ?? '2:3')) ?> &bull; <?= (int)($dok['slot_count'] ?? 6) ?> Slot
            </span>

            <?php if ($isVideo): ?>
              <span class="absolute inset-0 flex items-center justify-center bg-slate-950/20 group-hover:bg-slate-950/35 transition-colors">
                <span class="w-11 h-11 rounded-full bg-orange-600/95 text-white flex items-center justify-center shadow-lg">
                  <svg class="w-5 h-5 ml-0.5" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </span>
              </span>
            <?php endif; ?>
          </button>

          <div class="p-3.5 flex-1 flex flex-col justify-between gap-3">
            <div class="space-y-1">
              <div class="flex flex-wrap items-center gap-1.5 text-[11px] font-mono text-slate-500 dark:text-slate-400">
                <span class="font-bold opsd-accent-text"><?= e((string)($dok['hari_ke'] ?? 'H1')) ?></span>
                <?php if (!empty($dok['tanggal_label'])): ?>
                  <span>&bull;</span>
                  <span><?= e((string)$dok['tanggal_label']) ?></span>
                <?php endif; ?>
                <span>&bull;</span>
                <span><?= $formatBytes((int)($dok['file_size'] ?? 0)) ?></span>
              </div>
              <h4 class="text-xs font-bold text-slate-900 dark:text-white leading-snug line-clamp-2">
                <?= e((string)$dok['judul']) ?>
              </h4>
            </div>

            <div class="flex items-center justify-between gap-1.5 pt-2 border-t border-slate-100 dark:border-slate-800/80 no-print">
              <div class="flex flex-wrap items-center gap-1.5">
                <button type="button"
                        onclick="openDokPreviewModal(this.dataset.url, this.dataset.type, this.dataset.title)"
                        data-url="<?= e($fileUrl) ?>"
                        data-type="<?= $isVideo ? 'video' : 'image' ?>"
                        data-title="<?= e((string)$dok['judul']) ?>"
                        class="opsd-accent-soft inline-flex items-center h-7 px-2.5 rounded-lg text-[11px] font-semibold transition cursor-pointer">
                  <span>Lihat</span>
                </button>
                <a href="<?= e($studioUrl . '&doc_id=' . $dokId) ?>" class="opsd-ghost-btn inline-flex items-center gap-1 h-7 px-2.5 rounded-lg text-[11px] font-semibold transition no-underline" title="Edit Kolase Dokumentasi ini di Studio">
                  <svg class="w-3 h-3" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  <span>Edit</span>
                </a>
                <a href="<?= e($fileUrl) ?>" download="<?= e($downloadName) ?>" class="opsd-ghost-btn inline-flex items-center h-7 px-2.5 rounded-lg text-[11px] font-semibold transition no-underline">
                  <span>Unduh</span>
                </a>
              </div>
              <?php if (AuthHelper::canDelete('dokumentasi')): ?>
                <button type="button"
                        onclick="deleteOperasiDokumentasi(<?= (int)$id ?>, <?= $dokId ?>, this)"
                        class="inline-flex items-center justify-center w-7 h-7 rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-rose-50 dark:hover:bg-rose-950/40 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:border-rose-200 dark:hover:border-rose-900 transition cursor-pointer"
                        title="Hapus Arsip Dokumentasi"
                        aria-label="Hapus Arsip Dokumentasi">
                  <svg class="w-3.5 h-3.5" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
                </button>
              <?php endif; ?>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- Lightbox Preview Modal for Saved Documentation Collages -->
<div id="dok-preview-lightbox" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/85 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-label="Pratinjau Dokumentasi Operasi SAR" onclick="if(event.target===this) closeDokPreviewModal()">
  <div class="w-full max-w-4xl bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl overflow-hidden flex flex-col max-h-[92vh]">
    <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
      <h3 id="dok-lightbox-title" class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate">Pratinjau Dokumentasi</h3>
      <button type="button" onclick="closeDokPreviewModal()" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" aria-label="Tutup pratinjau">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
    <div id="dok-lightbox-stage" class="p-4 bg-slate-950 flex-1 overflow-auto flex items-center justify-center min-h-[280px]"></div>
  </div>
</div>

<script>
window.openDokPreviewModal = function(url, type, title) {
  const modal = document.getElementById('dok-preview-lightbox');
  const stage = document.getElementById('dok-lightbox-stage');
  const titleEl = document.getElementById('dok-lightbox-title');
  if (!modal || !stage) return;
  if (titleEl) titleEl.textContent = title || 'Pratinjau Dokumentasi Operasi SAR';
  stage.innerHTML = '';
  if (type === 'video') {
    const vid = document.createElement('video');
    vid.src = url;
    vid.controls = true;
    vid.autoplay = true;
    vid.playsInline = true;
    vid.className = 'max-h-[75vh] max-w-full rounded-xl shadow-lg bg-black';
    stage.appendChild(vid);
  } else {
    const img = document.createElement('img');
    img.src = url;
    img.alt = title || 'Dokumentasi Operasi SAR';
    img.className = 'max-h-[75vh] max-w-full object-contain rounded-xl shadow-lg';
    stage.appendChild(img);
  }
  modal.classList.remove('hidden');
  modal.classList.add('flex');
};

window.closeDokPreviewModal = function() {
  const modal = document.getElementById('dok-preview-lightbox');
  const stage = document.getElementById('dok-lightbox-stage');
  if (!modal) return;
  if (stage) {
    const vid = stage.querySelector('video');
    if (vid) vid.pause();
    stage.innerHTML = '';
  }
  modal.classList.add('hidden');
  modal.classList.remove('flex');
};

window.deleteOperasiDokumentasi = async function(idOperasi, docId, btnEl) {
  let confirmed = false;
  if (typeof Swal !== 'undefined') {
    const res = await Swal.fire({
      title: 'Hapus Arsip Dokumentasi?',
      text: 'Berkas kolase foto/video ini akan dihapus permanen dari operasi.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#e11d48',
      confirmButtonText: 'Ya, Hapus',
      cancelButtonText: 'Batal'
    });
    confirmed = !!res.isConfirmed;
  } else {
    confirmed = window.confirm('Hapus arsip dokumentasi ini secara permanen?');
  }
  if (!confirmed) return;

  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : (window.CSRF_TOKEN || '');
  const baseUrl = window.BASE_URL || '';
  if (btnEl) btnEl.disabled = true;

  try {
    const resp = await fetch(`${baseUrl}/api/index.php?path=/operasi/${idOperasi}/dokumentasi/${docId}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    const data = await resp.json();
    if (!resp.ok || !data.success) {
      throw new Error(data.error || data.message || 'Gagal menghapus dokumentasi.');
    }
    const card = document.querySelector(`[data-dok-card="${docId}"]`);
    if (card) card.remove();
    const remaining = document.querySelectorAll('#dok-archive-grid [data-dok-card]').length;
    const tabCount = document.getElementById('dok-tab-count');
    if (tabCount) tabCount.textContent = String(remaining);
    const pill = document.getElementById('dok-archive-count-pill');
    if (pill) pill.textContent = remaining + ' berkas';
    if (typeof showToast === 'function') showToast('Arsip dokumentasi berhasil dihapus.', 'success');
    if (remaining === 0) window.location.reload();
  } catch (err) {
    if (btnEl) btnEl.disabled = false;
    if (typeof showToast === 'function') showToast(err.message || 'Gagal menghapus arsip.', 'error');
  }
};
</script>
