/**
 * QR-SAR - Studio Dokumentasi Kolase Operasi SAR (Foto & Video)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Fitur:
 * - 2 Gaya Desain: Klasik Resmi KPP & Editorial Modern (Taktis Bersih)
 * - Kontrol langsung di atas pratinjau kanvas: klik unggah, seret atur posisi (pan),
 *   zoom +/-, seret handle untuk reorder/tukar urutan, hapus 1 per 1, dan bulk delete
 * - Rendering kualitas tinggi Full HD (1x), 2K QHD (1.5x), hingga 4K Ultra HD (2x)
 *   langsung dari berkas sumber asli dengan imageSmoothingQuality = 'high'
 */
(function () {
  'use strict';

  const cfg = window.__DOK_STUDIO_CONFIG__ || null;
  const Layouts = window.QrSarCollageLayouts || null;
  if (!cfg || !Layouts) return;

  const canvas = document.getElementById('collagePreviewCanvas');
  const overlayEl = document.getElementById('collageInteractiveOverlay');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  // --- Application State ---
  const state = {
    idOperasi: Number(cfg.idOperasi) > 0 ? Number(cfg.idOperasi) : 0,
    namaOperasi: String(cfg.namaOperasi || ''),
    styleId: 'klasik_kpp',
    orientation: 'vertical',
    canvasPresetId: 'portrait_2_3',
    qualityId: 'qhd',
    slotCount: 6,
    layoutId: 'v_6_2x3',
    selectedSlotIndex: 0,
    bulkMode: false,
    bulkSelected: new Set(),
    slots: [], // { type: 'image'|'video', el, url, name, zoom, offsetX, offsetY, muted, videoTime, duration } or null
    isPlaying: false,
    isExporting: false,
    rafId: null,
    audioCtx: null,
    logoImages: { logo1: null, logo2: null, logo3: null },
    slotRectsCache: [],
  };

  // --- DOM References ---
  const els = {
    selectDokOperasi: document.getElementById('selectDokOperasi'),
    labelInputDokHari: document.getElementById('labelInputDokHari'),
    labelInputDokJudul: document.getElementById('labelInputDokJudul'),
    studioHeaderScopeLabel: document.getElementById('studioHeaderScopeLabel'),
    studioHeaderHariLabel: document.getElementById('studioHeaderHariLabel'),
    studioHeaderTitle: document.getElementById('studioHeaderTitle'),
    styleBtns: Array.from(document.querySelectorAll('[data-style-id]')),
    orientTabs: Array.from(document.querySelectorAll('.dok-orient-tab')),
    canvasPresetGrid: document.getElementById('canvasPresetGrid'),
    activeCanvasDimBadge: document.getElementById('activeCanvasDimBadge'),
    slotCountBtns: Array.from(document.querySelectorAll('[data-slot-count]')),
    layoutVariantGrid: document.getElementById('layoutVariantGrid'),
    filledSlotsBadge: document.getElementById('filledSlotsBadge'),
    btnClearAllSlots: document.getElementById('btnClearAllSlots'),
    bulkMediaDropzone: document.getElementById('bulkMediaDropzone'),
    bulkMediaInput: document.getElementById('bulkMediaInput'),
    singleSlotInput: document.getElementById('singleSlotInput'),
    inputDokHari: document.getElementById('inputDokHari'),
    inputDokTanggal: document.getElementById('inputDokTanggal'),
    inputDokJudul: document.getElementById('inputDokJudul'),
    chkShowLogo1: document.getElementById('chkShowLogo1'),
    chkShowLogo2: document.getElementById('chkShowLogo2'),
    chkShowLogo3: document.getElementById('chkShowLogo3'),
    inputHeaderNama: document.getElementById('inputHeaderNama'),
    inputHeaderAlamat: document.getElementById('inputHeaderAlamat'),
    inputHeaderTelp: document.getElementById('inputHeaderTelp'),
    inputHeaderWa: document.getElementById('inputHeaderWa'),
    inputHeaderEmail: document.getElementById('inputHeaderEmail'),
    inputFooterWeb: document.getElementById('inputFooterWeb'),
    inputFooterIg: document.getElementById('inputFooterIg'),
    inputFooterFb: document.getElementById('inputFooterFb'),
    inputFooterX: document.getElementById('inputFooterX'),
    previewMetaPill: document.getElementById('previewMetaPill'),
    previewModeBadge: document.getElementById('previewModeBadge'),
    btnToggleBulkMode: document.getElementById('btnToggleBulkMode'),
    previewBulkBar: document.getElementById('previewBulkBar'),
    bulkSelectedCountLabel: document.getElementById('bulkSelectedCountLabel'),
    btnBulkSelectAll: document.getElementById('btnBulkSelectAll'),
    btnBulkDeleteSelected: document.getElementById('btnBulkDeleteSelected'),
    btnBulkCancel: document.getElementById('btnBulkCancel'),
    videoPlaybackBar: document.getElementById('videoPlaybackBar'),
    btnToggleVideoPlay: document.getElementById('btnToggleVideoPlay'),
    labelToggleVideoPlay: document.getElementById('labelToggleVideoPlay'),
    selectVideoDuration: document.getElementById('selectVideoDuration'),
    exportProgressWrap: document.getElementById('exportProgressWrap'),
    exportProgressLabel: document.getElementById('exportProgressLabel'),
    exportProgressPct: document.getElementById('exportProgressPct'),
    exportProgressBar: document.getElementById('exportProgressBar'),
    selectExportQuality: document.getElementById('selectExportQuality'),
    selectImageFormat: document.getElementById('selectImageFormat'),
    btnTopDownloadImg: document.getElementById('btnTopDownloadImg'),
    btnBottomDownloadImg: document.getElementById('btnBottomDownloadImg'),
    btnTopDownloadVid: document.getElementById('btnTopDownloadVid'),
    btnBottomDownloadVid: document.getElementById('btnBottomDownloadVid'),
    btnSaveArchive: document.getElementById('btnSaveArchive'),
    btnBottomSaveArchive: document.getElementById('btnBottomSaveArchive'),
  };

  let replaceTargetIndex = 0;
  let dragSourceIndex = null;

  function notify(msg, type) {
    if (typeof window.showToast === 'function') {
      window.showToast(msg, type || 'info');
    } else if (type === 'error') {
      window.alert(msg);
    }
  }

  const escapeHtml = (window.QrSarUtils && window.QrSarUtils.escapeHtml) || function (str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  };

  function ensureSlotsLength(count) {
    while (state.slots.length < count) {
      state.slots.push(null);
    }
    if (state.selectedSlotIndex >= count) {
      state.selectedSlotIndex = count - 1;
    }
    // Prune bulk selections outside active slotCount or empty
    Array.from(state.bulkSelected).forEach((idx) => {
      if (idx >= count || !state.slots[idx]) {
        state.bulkSelected.delete(idx);
      }
    });
  }

  function activeSlotsSlice() {
    ensureSlotsLength(state.slotCount);
    return state.slots.slice(0, state.slotCount);
  }

  function hasVideoSlot() {
    return activeSlotsSlice().some((s) => s && s.type === 'video');
  }

  function filledSlotCount() {
    return activeSlotsSlice().filter(Boolean).length;
  }

  function isGeneralMode() {
    return !(state.idOperasi > 0);
  }

  function findOperasiOption(opId) {
    const list = Array.isArray(cfg.activeOperasiOptions) ? cfg.activeOperasiOptions : [];
    return list.find((o) => Number(o.id) === Number(opId)) || null;
  }

  function isAutoGeneratedTitle(val) {
    const clean = String(val || '').trim();
    if (!clean) return true;
    const genDef = (cfg.generalDefaults && cfg.generalDefaults.judulDefault) || 'Dokumentasi Kegiatan KPP Kelas A Kendari';
    if (clean === genDef || clean === cfg.judulDefault) return true;
    if (/^Dok Operasi SAR\s+H\.?\d+\b/i.test(clean)) return true;
    const list = Array.isArray(cfg.activeOperasiOptions) ? cfg.activeOperasiOptions : [];
    return list.some((o) => o.judulDefault === clean);
  }

  function syncOperasiSelectionUI(applyDefaultTexts) {
    const general = isGeneralMode();
    const genHari = (cfg.generalDefaults && cfg.generalDefaults.hariLabel) || 'KEGIATAN';
    const genJudul = (cfg.generalDefaults && cfg.generalDefaults.judulDefault) || 'Dokumentasi Kegiatan KPP Kelas A Kendari';
    const opt = general ? null : findOperasiOption(state.idOperasi);

    if (els.labelInputDokHari) {
      els.labelInputDokHari.textContent = general ? 'Label Kategori' : 'Hari Operasi';
    }
    if (els.inputDokHari) {
      els.inputDokHari.placeholder = general ? 'KEGIATAN' : 'H1';
    }
    if (els.labelInputDokJudul) {
      els.labelInputDokJudul.textContent = general ? 'Judul Dokumentasi Kolase' : 'Judul Dokumentasi Operasi SAR';
    }
    if (els.inputDokJudul) {
      els.inputDokJudul.placeholder = general
        ? 'Dokumentasi Kegiatan KPP Kelas A Kendari ...'
        : 'Dok Operasi SAR H1 ...';
    }

    if (applyDefaultTexts) {
      if (general) {
        if (els.inputDokHari) els.inputDokHari.value = genHari;
        if (els.inputDokJudul && isAutoGeneratedTitle(els.inputDokJudul.value)) {
          els.inputDokJudul.value = genJudul;
        }
      } else if (opt) {
        if (els.inputDokHari) els.inputDokHari.value = opt.hariLabel || 'H1';
        if (els.inputDokJudul && isAutoGeneratedTitle(els.inputDokJudul.value)) {
          els.inputDokJudul.value = opt.judulDefault || ('Dok Operasi SAR ' + (opt.hariLabel || 'H1') + ' ' + opt.nama);
        }
      }
    }

    if (els.studioHeaderScopeLabel) {
      els.studioHeaderScopeLabel.textContent = general ? 'Dokumentasi Umum' : `Operasi #${state.idOperasi}`;
    }
    if (els.studioHeaderHariLabel && els.inputDokHari) {
      els.studioHeaderHariLabel.textContent = els.inputDokHari.value.trim() || (general ? genHari : 'H1');
    }
    if (els.studioHeaderTitle) {
      els.studioHeaderTitle.textContent = general
        ? 'Dokumentasi Kegiatan & Umum KPP Kelas A Kendari'
        : (state.namaOperasi || (opt ? opt.nama : `Operasi SAR #${state.idOperasi}`));
    }
  }

  // --- Preload 3 Official Basarnas Logos ---
  function preloadLogos() {
    ['logo1', 'logo2', 'logo3'].forEach((key) => {
      const uri = cfg.logos && cfg.logos[key];
      if (!uri) return;
      const img = new Image();
      img.onload = function () {
        state.logoImages[key] = img;
        drawCollage(false);
      };
      img.src = uri;
    });
  }

  // --- UI Renderers ---
  function renderCanvasPresetButtons() {
    if (!els.canvasPresetGrid) return;
    const filtered = Layouts.CANVAS_PRESETS.filter((p) => p.orientation === state.orientation);
    els.canvasPresetGrid.innerHTML = '';
    filtered.forEach((p) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dok-chip px-3 py-2 rounded-xl text-left cursor-pointer';
      btn.dataset.active = p.id === state.canvasPresetId ? 'true' : 'false';
      btn.innerHTML =
        '<span class="block text-xs font-bold leading-tight">' + escapeHtml(p.ratio) + '</span>' +
        '<span class="block text-[10px] opacity-80 mt-0.5">' + escapeHtml(p.label.replace(p.ratio + ' ', '')) + '</span>';
      btn.addEventListener('click', () => {
        state.canvasPresetId = p.id;
        syncCanvasDimensions();
        renderCanvasPresetButtons();
        drawCollage(false);
      });
      els.canvasPresetGrid.appendChild(btn);
    });
  }

  function renderLayoutVariants() {
    if (!els.layoutVariantGrid) return;
    const list = Layouts.getLayoutsForCount(state.slotCount);
    els.layoutVariantGrid.innerHTML = '';

    list.forEach((layout) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dok-layout-btn p-2 rounded-xl flex items-center gap-2.5 text-left cursor-pointer';
      btn.dataset.active = layout.id === state.layoutId ? 'true' : 'false';

      const svgW = state.orientation === 'horizontal' ? 42 : 30;
      const svgH = state.orientation === 'horizontal' ? 28 : 40;
      const rects = Layouts.computeSlotRects(layout, { x: 2, y: 2, width: svgW - 4, height: svgH - 4 }, 2);
      const rectsSvg = rects
        .map((r) => `<rect x="${r.x.toFixed(1)}" y="${r.y.toFixed(1)}" width="${r.w.toFixed(1)}" height="${r.h.toFixed(1)}" rx="1.8" fill="currentColor" opacity="0.72" />`)
        .join('');

      btn.innerHTML =
        `<svg width="${svgW}" height="${svgH}" viewBox="0 0 ${svgW} ${svgH}" class="shrink-0 text-orange-600 dark:text-orange-400" aria-hidden="true">${rectsSvg}</svg>` +
        `<span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 leading-tight">${escapeHtml(layout.label)}</span>`;

      btn.addEventListener('click', () => {
        state.layoutId = layout.id;
        renderLayoutVariants();
        drawCollage(false);
      });
      els.layoutVariantGrid.appendChild(btn);
    });
  }

  function syncModeAndCounters() {
    const filled = filledSlotCount();
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const expDim = Layouts.resolveExportDimensions(preset, state.qualityId);
    const hasVid = hasVideoSlot();
    const qShort = state.qualityId === 'uhd_4k' ? '4K' : (state.qualityId === 'qhd' ? '2K' : 'FHD');

    if (els.filledSlotsBadge) {
      els.filledSlotsBadge.textContent = `${filled} / ${state.slotCount} terisi`;
    }
    if (els.previewMetaPill) {
      els.previewMetaPill.textContent = `${preset.ratio} \u2022 ${state.slotCount} Slot \u2022 ${qShort}`;
    }
    if (els.activeCanvasDimBadge) {
      els.activeCanvasDimBadge.textContent = `${expDim.width} \u00D7 ${expDim.height} px (${qShort})`;
    }
    if (els.previewModeBadge) {
      els.previewModeBadge.textContent = hasVid ? 'MODE VIDEO + FOTO' : 'MODE FOTO';
      els.previewModeBadge.className = hasVid
        ? 'px-2 py-0.5 rounded-md bg-orange-500/15 text-orange-700 dark:text-orange-400 font-bold text-[10px] uppercase tracking-wider'
        : 'px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider';
    }
    if (els.btnToggleBulkMode) {
      els.btnToggleBulkMode.classList.toggle('hidden', filled === 0);
      els.btnToggleBulkMode.textContent = state.bulkMode ? 'Selesai Pilih' : 'Pilih Banyak';
    }
    if (els.previewBulkBar) {
      const showBulk = filled > 0 && (state.bulkMode || state.bulkSelected.size > 0);
      els.previewBulkBar.classList.toggle('hidden', !showBulk);
      if (els.bulkSelectedCountLabel) {
        els.bulkSelectedCountLabel.textContent = `${state.bulkSelected.size} dari ${filled} media dipilih`;
      }
      if (els.btnBulkDeleteSelected) {
        els.btnBulkDeleteSelected.disabled = state.bulkSelected.size === 0;
        els.btnBulkDeleteSelected.classList.toggle('opacity-50', state.bulkSelected.size === 0);
      }
    }
    [els.videoPlaybackBar, els.btnTopDownloadVid, els.btnBottomDownloadVid].forEach((el) => {
      if (!el) return;
      el.classList.toggle('hidden', !hasVid);
    });
    if (!hasVid && state.isPlaying) {
      stopPlayback();
    }
  }

  function syncCanvasDimensions() {
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    canvas.width = preset.width;
    canvas.height = preset.height;
    syncModeAndCounters();
  }

  // --- Interactive Pixel-Aligned Overlay directly on Live Preview ---
  function syncInteractiveOverlay() {
    if (!overlayEl) return;
    const W = canvas.width || 1080;
    const H = canvas.height || 1620;
    const rects = state.slotRectsCache || [];
    overlayEl.innerHTML = '';

    rects.forEach((rect, idx) => {
      const item = state.slots[idx];
      const isFilled = !!item;
      const isSelected = idx === state.selectedSlotIndex;
      const isChecked = state.bulkSelected.has(idx);

      const box = document.createElement('div');
      box.className = 'dok-ov-slot';
      box.dataset.slotIndex = String(idx);
      box.dataset.filled = isFilled ? 'true' : 'false';
      box.dataset.selected = isSelected ? 'true' : 'false';
      box.dataset.bulkMode = state.bulkMode ? 'true' : 'false';
      box.dataset.bulkChecked = isChecked ? 'true' : 'false';
      box.style.left = ((rect.x / W) * 100).toFixed(4) + '%';
      box.style.top = ((rect.y / H) * 100).toFixed(4) + '%';
      box.style.width = ((rect.w / W) * 100).toFixed(4) + '%';
      box.style.height = ((rect.h / H) * 100).toFixed(4) + '%';

      // Drag-and-drop target for BOTH slot reorder AND dropping files directly onto a slot
      box.addEventListener('dragover', (e) => {
        e.preventDefault();
        box.classList.add('is-drop-target');
      });
      box.addEventListener('dragleave', () => {
        box.classList.remove('is-drop-target');
      });
      box.addEventListener('drop', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        box.classList.remove('is-drop-target');
        if (dragSourceIndex !== null && dragSourceIndex !== idx) {
          const fromIdx = dragSourceIndex;
          dragSourceIndex = null;
          swapSlots(fromIdx, idx);
          notify(`Slot #${fromIdx + 1} dan #${idx + 1} berhasil ditukar.`, 'success');
          return;
        }
        dragSourceIndex = null;
        const droppedFiles = e.dataTransfer && e.dataTransfer.files;
        if (droppedFiles && droppedFiles.length === 1) {
          await loadFileIntoSlot(droppedFiles[0], idx);
          state.selectedSlotIndex = idx;
          refreshAllUI();
        } else if (droppedFiles && droppedFiles.length > 1) {
          handleBulkFiles(droppedFiles);
        }
      });

      if (!isFilled) {
        box.title = `Klik atau seret foto/video ke Slot #${idx + 1}`;
        box.addEventListener('click', () => {
          state.selectedSlotIndex = idx;
          replaceTargetIndex = idx;
          if (els.singleSlotInput) {
            els.singleSlotInput.value = '';
            els.singleSlotInput.click();
          }
          refreshAllUI();
        });
        overlayEl.appendChild(box);
        return;
      }

      // Filled Slot: Direct Pan-by-Drag inside preview
      let panActive = false;
      let startX = 0;
      let startY = 0;
      let origOffX = 0;
      let origOffY = 0;
      let moved = false;

      box.addEventListener('pointerdown', (e) => {
        if (e.target.closest('button, input, label, [data-reorder-handle]')) return;
        if (e.button !== 0) return;
        state.selectedSlotIndex = idx;
        panActive = true;
        moved = false;
        startX = e.clientX;
        startY = e.clientY;
        origOffX = item.offsetX || 0;
        origOffY = item.offsetY || 0;
        box.classList.add('is-panning');
        try { box.setPointerCapture(e.pointerId); } catch (err) {}
      });

      box.addEventListener('pointermove', (e) => {
        if (!panActive) return;
        const dx = e.clientX - startX;
        const dy = e.clientY - startY;
        if (Math.abs(dx) > 2 || Math.abs(dy) > 2) moved = true;
        if (!moved) return;
        const rectPx = box.getBoundingClientRect();
        const sensX = (dx / Math.max(40, rectPx.width)) * 180;
        const sensY = (dy / Math.max(40, rectPx.height)) * 180;
        item.offsetX = Math.max(-100, Math.min(100, Math.round(origOffX - sensX)));
        item.offsetY = Math.max(-100, Math.min(100, Math.round(origOffY - sensY)));
        drawCollage(false, true);
      });

      const endPan = (e) => {
        if (!panActive) return;
        panActive = false;
        box.classList.remove('is-panning');
        try { box.releasePointerCapture(e.pointerId); } catch (err) {}
        if (!moved && state.bulkMode) {
          toggleBulkIndex(idx);
          return;
        }
        syncInteractiveOverlay();
      };
      box.addEventListener('pointerup', endPan);
      box.addEventListener('pointercancel', endPan);

      // Top Control Row (Left: Bulk Checkbox + Drag Reorder Handle; Right: Replace + Delete 1-by-1)
      const topRow = document.createElement('div');
      topRow.className = 'dok-ov-controls absolute top-1.5 inset-x-1.5 flex items-center justify-between gap-1 pointer-events-none';

      const leftGroup = document.createElement('div');
      leftGroup.className = 'flex items-center gap-1 pointer-events-auto';

      // Bulk Select Checkbox
      const chkLabel = document.createElement('label');
      chkLabel.className = 'dok-ov-pill inline-flex items-center justify-center w-6 h-6 rounded-md cursor-pointer';
      chkLabel.title = 'Pilih untuk hapus massal';
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.checked = isChecked;
      chk.className = 'w-3.5 h-3.5 accent-rose-600 cursor-pointer';
      chk.setAttribute('aria-label', `Pilih slot ${idx + 1}`);
      chk.addEventListener('change', (ev) => {
        ev.stopPropagation();
        toggleBulkIndex(idx, chk.checked);
      });
      chkLabel.appendChild(chk);
      leftGroup.appendChild(chkLabel);

      // Drag Reorder Handle
      const grip = document.createElement('span');
      grip.setAttribute('data-reorder-handle', 'true');
      grip.draggable = true;
      grip.title = 'Seret ke slot lain untuk tukar posisi';
      grip.className = 'dok-ov-pill inline-flex items-center gap-0.5 h-6 px-1.5 rounded-md text-[10px] font-mono font-bold cursor-move select-none';
      grip.innerHTML =
        '<svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="6" r="1.6"/><circle cx="15" cy="6" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="18" r="1.6"/><circle cx="15" cy="18" r="1.6"/></svg>' +
        '<span>#' + (idx + 1) + '</span>';
      grip.addEventListener('dragstart', (ev) => {
        dragSourceIndex = idx;
        ev.dataTransfer.effectAllowed = 'move';
        try { ev.dataTransfer.setData('text/plain', String(idx)); } catch (err) {}
      });
      grip.addEventListener('dragend', () => {
        dragSourceIndex = null;
        overlayEl.querySelectorAll('.is-drop-target').forEach((n) => n.classList.remove('is-drop-target'));
      });
      leftGroup.appendChild(grip);
      topRow.appendChild(leftGroup);

      // Right Group: Replace + Delete 1-by-1
      const rightGroup = document.createElement('div');
      rightGroup.className = 'flex items-center gap-1 pointer-events-auto';

      const btnReplace = document.createElement('button');
      btnReplace.type = 'button';
      btnReplace.className = 'dok-ov-pill inline-flex items-center justify-center w-6 h-6 rounded-md hover:bg-orange-600 transition cursor-pointer';
      btnReplace.title = 'Ganti foto/video slot ini';
      btnReplace.setAttribute('aria-label', `Ganti media slot ${idx + 1}`);
      btnReplace.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>';
      btnReplace.addEventListener('click', (ev) => {
        ev.stopPropagation();
        replaceTargetIndex = idx;
        state.selectedSlotIndex = idx;
        if (els.singleSlotInput) {
          els.singleSlotInput.value = '';
          els.singleSlotInput.click();
        }
      });
      rightGroup.appendChild(btnReplace);

      const btnRemove = document.createElement('button');
      btnRemove.type = 'button';
      btnRemove.className = 'dok-ov-pill inline-flex items-center justify-center w-6 h-6 rounded-md hover:bg-rose-600 transition cursor-pointer';
      btnRemove.title = 'Hapus slot ini';
      btnRemove.setAttribute('aria-label', `Hapus media slot ${idx + 1}`);
      btnRemove.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';
      btnRemove.addEventListener('click', (ev) => {
        ev.stopPropagation();
        clearSlot(idx);
      });
      rightGroup.appendChild(btnRemove);
      topRow.appendChild(rightGroup);
      box.appendChild(topRow);

      // Bottom Compact Zoom & Fit Bar right inside the preview slot
      const botRow = document.createElement('div');
      botRow.className = 'dok-ov-controls absolute bottom-1.5 left-1/2 -translate-x-1/2 flex items-center gap-0.5 dok-ov-pill px-1.5 py-0.5 rounded-lg pointer-events-auto shadow-md';

      const makeMiniBtn = (label, title, onClick) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'px-1.5 h-5 rounded text-[10px] font-mono font-bold hover:bg-white/20 transition cursor-pointer';
        b.title = title;
        b.textContent = label;
        b.addEventListener('click', (ev) => {
          ev.stopPropagation();
          state.selectedSlotIndex = idx;
          onClick();
        });
        return b;
      };

      botRow.appendChild(makeMiniBtn('-', 'Perkecil (Zoom Out)', () => {
        item.zoom = Math.max(1, Math.round((item.zoom - 0.15) * 100) / 100);
        drawCollage(false, true);
      }));
      botRow.appendChild(makeMiniBtn('Fit', 'Reset Fit ke Kolom', () => {
        item.zoom = 1;
        item.offsetX = 0;
        item.offsetY = 0;
        drawCollage(false, true);
      }));
      botRow.appendChild(makeMiniBtn('+', 'Perbesar (Zoom In)', () => {
        item.zoom = Math.min(2.5, Math.round((item.zoom + 0.15) * 100) / 100);
        drawCollage(false, true);
      }));

      if (item.type === 'video') {
        const muteLabel = item.muted ? 'Bisu' : 'Suara';
        const muteTitle = item.muted ? 'Aktifkan audio klip video ini' : 'Bisukan audio klip video ini';
        botRow.appendChild(makeMiniBtn(muteLabel, muteTitle, () => {
          item.muted = !item.muted;
          if (item._previewGain && state.audioCtx) {
            item._previewGain.gain.setValueAtTime(item.muted ? 0 : 1, state.audioCtx.currentTime);
          }
          if (item.el && !item._previewGain) {
            item.el.muted = item.muted;
          }
          syncInteractiveOverlay();
        }));
      }

      box.appendChild(botRow);
      overlayEl.appendChild(box);
    });
  }

  function toggleBulkIndex(idx, forcedState) {
    if (!state.slots[idx]) return;
    const shouldCheck = typeof forcedState === 'boolean' ? forcedState : !state.bulkSelected.has(idx);
    if (shouldCheck) {
      state.bulkSelected.add(idx);
    } else {
      state.bulkSelected.delete(idx);
    }
    syncModeAndCounters();
    syncInteractiveOverlay();
  }

  function refreshAllUI() {
    syncModeAndCounters();
    drawCollage(false);
  }

  function releaseSlotResources(slot) {
    if (!slot) return;
    if (slot.type === 'video' && slot.el) {
      try { slot.el.pause(); } catch (e) {}
    }
    if (slot._previewGain) {
      try { slot._previewGain.disconnect(); } catch (e) {}
      slot._previewGain = null;
    }
    if (slot._recordGain) {
      try { slot._recordGain.disconnect(); } catch (e) {}
      slot._recordGain = null;
    }
    if (slot.isObjectUrl && slot.url) {
      try { URL.revokeObjectURL(slot.url); } catch (e) {}
    }
  }

  // --- Media Loading (Images & Videos: Local File & Saved Server Asset) ---
  function loadFileIntoSlot(file, index) {
    if (!file) return Promise.resolve();
    const isVideo = file.type.startsWith('video/') || /\.(mp4|webm|mov|m4v)$/i.test(file.name);
    const isImage = file.type.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif)$/i.test(file.name);
    if (!isVideo && !isImage) {
      notify(`Berkas "${file.name}" bukan format foto atau video yang didukung.`, 'error');
      return Promise.resolve();
    }

    releaseSlotResources(state.slots[index]);
    const objUrl = URL.createObjectURL(file);

    if (isImage) {
      return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => {
          state.slots[index] = {
            type: 'image',
            el: img,
            url: objUrl,
            isObjectUrl: true,
            file: file,
            serverPath: null,
            name: file.name,
            zoom: 1,
            offsetX: 0,
            offsetY: 0,
          };
          resolve();
        };
        img.onerror = () => {
          URL.revokeObjectURL(objUrl);
          notify(`Gagal membaca gambar "${file.name}".`, 'error');
          resolve();
        };
        img.src = objUrl;
      });
    }

    return new Promise((resolve) => {
      const video = document.createElement('video');
      video.crossOrigin = 'anonymous';
      video.preload = 'auto';
      video.loop = true;
      video.playsInline = true;
      video.src = objUrl;

      // Slot video pertama default bersuara (unmuted) agar langsung terdengar alami;
      // slot video berikutnya default bisu (muted) untuk mencegah tabrakan audio ganda.
      const existingVideos = activeSlotsSlice().filter((s) => s && s.type === 'video');
      const hasAudible = existingVideos.some((s) => !s.muted);
      const initialMuted = hasAudible;
      video.muted = initialMuted;

      let settled = false;
      const finish = () => {
        if (settled) return;
        settled = true;
        const dur = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 10;
        const startT = Math.min(dur, Math.max(0, dur > 1 ? 0.2 : 0));
        state.slots[index] = {
          type: 'video',
          el: video,
          url: objUrl,
          isObjectUrl: true,
          file: file,
          serverPath: null,
          name: file.name,
          zoom: 1,
          offsetX: 0,
          offsetY: 0,
          muted: initialMuted,
          videoTime: startT,
          duration: dur,
        };
        try { video.currentTime = startT; } catch (e) {}
        resolve();
      };

      video.addEventListener('loadeddata', finish, { once: true });
      video.addEventListener('canplay', finish, { once: true });
      video.addEventListener('loadedmetadata', finish, { once: true });
      video.addEventListener('seeked', () => {
        if (!state.isPlaying) drawCollage(false, true);
      });
      video.addEventListener('error', () => {
        if (settled) return;
        settled = true;
        URL.revokeObjectURL(objUrl);
        notify(`Format video "${file.name}" tidak dapat diputar di browser ini.`, 'error');
        resolve();
      }, { once: true });
      setTimeout(finish, 8000);
      video.load();
    });
  }

  function loadRemoteSlotIntoSlot(slotMeta, index) {
    if (!slotMeta || !slotMeta.url) return Promise.resolve();
    releaseSlotResources(state.slots[index]);
    const isVideo = slotMeta.type === 'video';
    const remoteUrl = slotMeta.url;

    if (!isVideo) {
      return new Promise((resolve) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => {
          state.slots[index] = {
            type: 'image',
            el: img,
            url: remoteUrl,
            isObjectUrl: false,
            file: null,
            serverPath: slotMeta.path || null,
            name: slotMeta.name || `Slot ${index + 1}`,
            zoom: Math.max(1, Math.min(3, Number(slotMeta.zoom) || 1)),
            offsetX: Math.max(-100, Math.min(100, Number(slotMeta.offsetX) || 0)),
            offsetY: Math.max(-100, Math.min(100, Number(slotMeta.offsetY) || 0)),
          };
          resolve();
        };
        img.onerror = () => resolve();
        img.src = remoteUrl;
      });
    }

    return new Promise((resolve) => {
      const video = document.createElement('video');
      video.crossOrigin = 'anonymous';
      video.preload = 'auto';
      video.loop = true;
      video.playsInline = true;
      video.src = remoteUrl;
      const isMuted = slotMeta.muted !== false;
      video.muted = isMuted;

      let settled = false;
      const finish = () => {
        if (settled) return;
        settled = true;
        const dur = Number.isFinite(video.duration) && video.duration > 0 ? video.duration : 10;
        const startT = Math.min(dur, Math.max(0, Number(slotMeta.videoTime) || (dur > 1 ? 0.2 : 0)));
        state.slots[index] = {
          type: 'video',
          el: video,
          url: remoteUrl,
          isObjectUrl: false,
          file: null,
          serverPath: slotMeta.path || null,
          name: slotMeta.name || `Video ${index + 1}`,
          zoom: Math.max(1, Math.min(3, Number(slotMeta.zoom) || 1)),
          offsetX: Math.max(-100, Math.min(100, Number(slotMeta.offsetX) || 0)),
          offsetY: Math.max(-100, Math.min(100, Number(slotMeta.offsetY) || 0)),
          muted: isMuted,
          videoTime: startT,
          duration: dur,
        };
        try { video.currentTime = startT; } catch (e) {}
        resolve();
      };

      video.addEventListener('loadeddata', finish, { once: true });
      video.addEventListener('canplay', finish, { once: true });
      video.addEventListener('loadedmetadata', finish, { once: true });
      video.addEventListener('seeked', () => {
        if (!state.isPlaying) drawCollage(false, true);
      });
      video.addEventListener('error', () => {
        if (settled) return;
        settled = true;
        resolve();
      }, { once: true });
      setTimeout(finish, 10000);
      video.load();
    });
  }

  async function handleBulkFiles(fileList) {
    const files = Array.from(fileList || []).filter((f) =>
      f.type.startsWith('image/') || f.type.startsWith('video/') || /\.(jpg|jpeg|png|webp|gif|mp4|webm|mov|m4v)$/i.test(f.name)
    );
    if (!files.length) return;

    const currentFilled = filledSlotCount();
    if (currentFilled === 0 && files.length >= Layouts.MIN_SLOTS && files.length <= Layouts.MAX_SLOTS && files.length !== state.slotCount) {
      setSlotCount(files.length);
    }

    let cursor = 0;
    const tasks = [];
    for (const file of files) {
      while (cursor < state.slotCount && state.slots[cursor]) {
        cursor++;
      }
      if (cursor >= state.slotCount) {
        if (state.slotCount < Layouts.MAX_SLOTS) {
          setSlotCount(Math.min(Layouts.MAX_SLOTS, state.slotCount + 1));
        } else {
          break;
        }
      }
      const targetIdx = cursor;
      cursor++;
      tasks.push(loadFileIntoSlot(file, targetIdx));
    }

    await Promise.all(tasks);
    refreshAllUI();
    notify(`${tasks.length} media berhasil dimuat (fit-to-slot).`, 'success');
  }

  function clearSlot(idx) {
    releaseSlotResources(state.slots[idx]);
    state.slots[idx] = null;
    state.bulkSelected.delete(idx);
    refreshAllUI();
  }

  function clearSelectedBulkSlots() {
    if (state.bulkSelected.size === 0) return;
    const count = state.bulkSelected.size;
    Array.from(state.bulkSelected).forEach((idx) => {
      releaseSlotResources(state.slots[idx]);
      state.slots[idx] = null;
    });
    state.bulkSelected.clear();
    if (filledSlotCount() === 0) state.bulkMode = false;
    refreshAllUI();
    notify(`${count} slot media berhasil dihapus.`, 'success');
  }

  function clearAllSlots() {
    stopPlayback();
    for (let i = 0; i < state.slots.length; i++) {
      releaseSlotResources(state.slots[i]);
      state.slots[i] = null;
    }
    state.bulkSelected.clear();
    state.bulkMode = false;
    refreshAllUI();
  }

  function swapSlots(idxA, idxB) {
    if (idxA < 0 || idxB < 0 || idxA >= state.slotCount || idxB >= state.slotCount) return;
    const tmp = state.slots[idxA];
    state.slots[idxA] = state.slots[idxB];
    state.slots[idxB] = tmp;
    const hadA = state.bulkSelected.has(idxA);
    const hadB = state.bulkSelected.has(idxB);
    state.bulkSelected.delete(idxA);
    state.bulkSelected.delete(idxB);
    if (hadA) state.bulkSelected.add(idxB);
    if (hadB) state.bulkSelected.add(idxA);
    state.selectedSlotIndex = idxB;
    refreshAllUI();
  }

  function setStyle(styleId) {
    state.styleId = styleId === 'editorial_modern' ? 'editorial_modern' : 'klasik_kpp';
    els.styleBtns.forEach((b) => {
      b.dataset.active = b.dataset.styleId === state.styleId ? 'true' : 'false';
    });
    drawCollage(false);
  }

  function setSlotCount(newCount) {
    const clamped = Layouts.clampSlotCount(newCount);
    state.slotCount = clamped;
    ensureSlotsLength(clamped);
    state.layoutId = Layouts.getDefaultLayoutId(clamped, state.orientation);
    els.slotCountBtns.forEach((b) => {
      b.dataset.active = Number(b.dataset.slotCount) === clamped ? 'true' : 'false';
    });
    renderLayoutVariants();
    refreshAllUI();
  }

  function setOrientation(orient) {
    state.orientation = orient;
    els.orientTabs.forEach((t) => {
      const active = t.dataset.orient === orient;
      t.dataset.active = active ? 'true' : 'false';
      t.className = active
        ? 'dok-orient-tab h-8 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 text-orange-700 dark:text-orange-400 shadow-xs transition cursor-pointer'
        : 'dok-orient-tab h-8 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer';
    });
    const firstPreset = Layouts.CANVAS_PRESETS.find((p) => p.orientation === orient);
    if (firstPreset) {
      state.canvasPresetId = firstPreset.id;
    }
    state.layoutId = Layouts.getDefaultLayoutId(state.slotCount, orient);
    syncCanvasDimensions();
    renderCanvasPresetButtons();
    renderLayoutVariants();
    drawCollage(false);
  }

  // --- Shared Vector Drawing Helpers ---
  function roundRectPath(c, x, y, w, h, r) {
    const rad = Math.min(r, w / 2, h / 2);
    c.beginPath();
    c.moveTo(x + rad, y);
    c.arcTo(x + w, y, x + w, y + h, rad);
    c.arcTo(x + w, y + h, x, y + h, rad);
    c.arcTo(x, y + h, x, y, rad);
    c.arcTo(x, y, x + w, y, rad);
    c.closePath();
  }

  function drawSocialIcon(c, kind, cx, cy, size, color) {
    const r = size / 2;
    c.save();
    c.strokeStyle = color || '#FFFFFF';
    c.fillStyle = color || '#FFFFFF';
    c.lineWidth = Math.max(1.8, size * 0.09);

    if (kind === 'ig') {
      roundRectPath(c, cx - r, cy - r, size, size, size * 0.28);
      c.stroke();
      c.beginPath();
      c.arc(cx, cy, size * 0.22, 0, Math.PI * 2);
      c.stroke();
      c.beginPath();
      c.arc(cx + size * 0.26, cy - size * 0.26, size * 0.06, 0, Math.PI * 2);
      c.fill();
    } else if (kind === 'fb') {
      c.beginPath();
      c.arc(cx, cy, r, 0, Math.PI * 2);
      c.stroke();
      c.font = `700 ${Math.round(size * 0.72)}px "Plus Jakarta Sans", Arial, sans-serif`;
      c.textAlign = 'center';
      c.textBaseline = 'middle';
      c.fillText('f', cx + size * 0.03, cy + size * 0.06);
    } else if (kind === 'x') {
      c.beginPath();
      c.arc(cx, cy, r, 0, Math.PI * 2);
      c.stroke();
      c.font = `700 ${Math.round(size * 0.56)}px "Plus Jakarta Sans", Arial, sans-serif`;
      c.textAlign = 'center';
      c.textBaseline = 'middle';
      c.fillText('X', cx, cy + size * 0.04);
    }
    c.restore();
  }

  function collectFormTexts() {
    const telpVal = (els.inputHeaderTelp && els.inputHeaderTelp.value.trim()) || '';
    const waVal = (els.inputHeaderWa && els.inputHeaderWa.value.trim()) || '';
    const general = isGeneralMode();
    return {
      hari: (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : (cfg.hariLabel || 'H1')),
      tanggal: (els.inputDokTanggal && els.inputDokTanggal.value.trim()) || '',
      judul: (els.inputDokJudul && els.inputDokJudul.value.trim()) || (general ? 'Dokumentasi Kegiatan KPP Kelas A Kendari' : (cfg.judulDefault || 'Dokumentasi Operasi SAR')),
      namaSatker: (els.inputHeaderNama && els.inputHeaderNama.value.trim()) || 'KANTOR PENCARIAN DAN PERTOLONGAN KELAS A KENDARI',
      alamat: (els.inputHeaderAlamat && els.inputHeaderAlamat.value.trim()) || '',
      emergencyLine: Layouts.formatEmergencyLine(telpVal, waVal),
      email: (els.inputHeaderEmail && els.inputHeaderEmail.value.trim()) || '',
      web: (els.inputFooterWeb && els.inputFooterWeb.value.trim()) || '',
      socials: [
        { kind: 'ig', label: (els.inputFooterIg && els.inputFooterIg.value.trim()) || '' },
        { kind: 'fb', label: (els.inputFooterFb && els.inputFooterFb.value.trim()) || '' },
        { kind: 'x', label: (els.inputFooterX && els.inputFooterX.value.trim()) || '' },
      ].filter((s) => s.label !== ''),
    };
  }

  function getActiveLogos() {
    const list = [];
    if (els.chkShowLogo1 && els.chkShowLogo1.checked && state.logoImages.logo1) list.push(state.logoImages.logo1);
    if (els.chkShowLogo2 && els.chkShowLogo2.checked && state.logoImages.logo2) list.push(state.logoImages.logo2);
    if (els.chkShowLogo3 && els.chkShowLogo3.checked && state.logoImages.logo3) list.push(state.logoImages.logo3);
    return list;
  }

  function drawMediaSlots(c, rects, W, H, scaleBase, forExport, frameBorderColor) {
    rects.forEach((rect, idx) => {
      const slot = state.slots[idx];

      // Soft elevation shadow
      c.save();
      c.shadowColor = 'rgba(15, 23, 42, 0.16)';
      c.shadowBlur = Math.round(14 * scaleBase);
      c.shadowOffsetX = 0;
      c.shadowOffsetY = Math.round(5 * scaleBase);
      c.fillStyle = '#E2E8F0';
      roundRectPath(c, rect.x, rect.y, rect.w, rect.h, rect.radius);
      c.fill();
      c.restore();

      // Clipped photo/video fill (default fit-to-slot cover, high-quality resampling)
      c.save();
      c.imageSmoothingEnabled = true;
      c.imageSmoothingQuality = 'high';
      roundRectPath(c, rect.x, rect.y, rect.w, rect.h, rect.radius);
      c.clip();

      if (slot && slot.el) {
        const mw = slot.type === 'video' ? slot.el.videoWidth : slot.el.naturalWidth;
        const mh = slot.type === 'video' ? slot.el.videoHeight : slot.el.naturalHeight;
        if (mw > 0 && mh > 0) {
          const crop = Layouts.computeCoverCrop(mw, mh, rect.w, rect.h, slot.zoom, slot.offsetX, slot.offsetY);
          c.drawImage(slot.el, crop.sx, crop.sy, crop.sw, crop.sh, rect.x, rect.y, rect.w, rect.h);
        } else {
          c.fillStyle = '#0F172A';
          c.fillRect(rect.x, rect.y, rect.w, rect.h);
        }
      } else {
        c.fillStyle = '#F1F5F9';
        c.fillRect(rect.x, rect.y, rect.w, rect.h);
        if (!forExport) {
          c.strokeStyle = '#CBD5E1';
          c.lineWidth = Math.max(2, Math.round(3 * scaleBase));
          c.setLineDash([Math.round(10 * scaleBase), Math.round(8 * scaleBase)]);
          roundRectPath(c, rect.x + 6 * scaleBase, rect.y + 6 * scaleBase, rect.w - 12 * scaleBase, rect.h - 12 * scaleBase, Math.max(6, rect.radius - 4));
          c.stroke();
          c.setLineDash([]);

          c.fillStyle = '#64748B';
          c.font = `700 ${Math.round(Math.max(16 * scaleBase, Math.min(rect.w, rect.h) * 0.105))}px "Plus Jakarta Sans", Arial, sans-serif`;
          c.textAlign = 'center';
          c.textBaseline = 'middle';
          c.fillText(`Slot ${idx + 1}`, rect.x + rect.w / 2, rect.y + rect.h / 2 - Math.round(12 * scaleBase));
          c.font = `500 ${Math.round(Math.max(12 * scaleBase, Math.min(rect.w, rect.h) * 0.065))}px "Plus Jakarta Sans", Arial, sans-serif`;
          c.fillText('Klik untuk Foto / Video', rect.x + rect.w / 2, rect.y + rect.h / 2 + Math.round(15 * scaleBase));
        }
      }
      c.restore();

      // Subtle frame hairline
      c.save();
      roundRectPath(c, rect.x, rect.y, rect.w, rect.h, rect.radius);
      c.strokeStyle = frameBorderColor || 'rgba(15, 23, 42, 0.08)';
      c.lineWidth = Math.max(1, Math.round(1.5 * scaleBase));
      c.stroke();
      c.restore();
    });
  }

  // --- STYLE 1: Klasik Resmi KPP Kendari ---
  function renderStyleKlasikKpp(c, W, H, forExport) {
    const isLandscape = W > H;
    const isSquare = W === H;
    const scaleBase = Math.min(W, H) / 1080;
    const txt = collectFormTexts();
    const activeLogos = getActiveLogos();

    c.save();
    c.imageSmoothingEnabled = true;
    c.imageSmoothingQuality = 'high';
    c.fillStyle = '#FFFFFF';
    c.fillRect(0, 0, W, H);

    // Top Orange Banner
    const headerH = Math.round(isLandscape ? H * 0.145 : (isSquare ? H * 0.14 : H * 0.118));
    const orangeGrad = c.createLinearGradient(0, 0, W, headerH);
    orangeGrad.addColorStop(0, '#FF6D00');
    orangeGrad.addColorStop(1, '#F25C00');
    c.fillStyle = orangeGrad;
    c.fillRect(0, 0, W, headerH);

    const ruleGap = Math.max(4, Math.round(6 * scaleBase));
    const ruleH = Math.max(3, Math.round(5 * scaleBase));
    c.fillStyle = '#F25C00';
    c.fillRect(0, headerH + ruleGap, W, ruleH);

    // 3 Logos Left
    const padX = Math.round(W * 0.028);
    const logoBoxH = Math.round(headerH * 0.62);
    const logoGap = Math.round(padX * 0.42);
    let logoRightEdge = padX;
    const logoCenterY = Math.round(headerH * 0.47);

    activeLogos.forEach((img) => {
      const aspect = (img.naturalWidth || 1) / Math.max(1, img.naturalHeight || 1);
      const drawH = logoBoxH;
      const drawW = Math.min(Math.round(drawH * aspect), Math.round(logoBoxH * 1.35));
      c.drawImage(img, logoRightEdge, logoCenterY - Math.round(drawH / 2), drawW, drawH);
      logoRightEdge += drawW + logoGap;
    });

    // Right-aligned Satker Text (115 / telp / WA)
    const textMaxW = Math.max(220, W - padX - logoRightEdge - Math.round(16 * scaleBase));
    const rightX = W - padX;
    c.textAlign = 'right';
    c.textBaseline = 'middle';
    c.fillStyle = '#0F172A';

    const nameSize = Math.round(Math.min(headerH * 0.185, textMaxW / Math.max(24, txt.namaSatker.length * 0.56)));
    const subSize = Math.round(nameSize * 0.74);
    const linesInfo = [
      { text: txt.namaSatker, font: `800 ${nameSize}px "Plus Jakarta Sans", Arial, sans-serif` },
      txt.alamat ? { text: txt.alamat, font: `600 ${subSize}px "Plus Jakarta Sans", Arial, sans-serif` } : null,
      txt.emergencyLine ? { text: txt.emergencyLine, font: `800 ${Math.round(subSize * 1.04)}px "Plus Jakarta Sans", Arial, sans-serif` } : null,
      txt.email ? { text: txt.email.toLowerCase().startsWith('e-mail') ? txt.email : `e-Mail: ${txt.email}`, font: `600 ${subSize}px "Plus Jakarta Sans", Arial, sans-serif` } : null,
    ].filter(Boolean);

    const lineStep = Math.round(headerH * 0.21);
    const blockStartY = Math.round((headerH - (linesInfo.length - 1) * lineStep) / 2);
    linesInfo.forEach((ln, idx) => {
      c.font = ln.font;
      c.fillText(ln.text, rightX, blockStartY + idx * lineStep, textMaxW);
    });

    // Overlapping Date Pill Left
    const pillH = Math.round(Math.max(36 * scaleBase, headerH * 0.30));
    const pillY = headerH + ruleGap + Math.round(ruleH / 2) - Math.round(pillH / 2);
    if (txt.tanggal) {
      const dateFontSize = Math.round(pillH * 0.46);
      c.font = `700 ${dateFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;
      const textW = c.measureText(txt.tanggal).width;
      const pillW = Math.min(Math.round(W * 0.52), Math.round(textW + padX * 2.1));

      c.save();
      c.fillStyle = '#B84B08';
      roundRectPath(c, -Math.round(pillH / 2), pillY, pillW + Math.round(pillH / 2), pillH, Math.round(pillH / 2));
      c.fill();
      c.fillStyle = '#FFFFFF';
      c.textAlign = 'left';
      c.textBaseline = 'middle';
      c.fillText(txt.tanggal, Math.round(padX * 0.85), pillY + Math.round(pillH / 2) + 1, pillW - padX);
      c.restore();
    }

    // Centered Bold Title
    const titleTopY = pillY + pillH + Math.round(H * (isLandscape ? 0.016 : 0.014));
    const titleMaxW = Math.round(W * 0.90);
    let titleFontSize = Math.round(Math.min(W, H) * (isLandscape ? 0.031 : 0.034));
    c.font = `800 ${titleFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;
    let titleLines = Layouts.wrapCanvasLines((t) => c.measureText(t).width, txt.judul, titleMaxW, isLandscape ? 2 : 3);
    if (titleLines.length >= 3 && titleFontSize > 24 * scaleBase) {
      titleFontSize = Math.round(titleFontSize * 0.9);
      c.font = `800 ${titleFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;
      titleLines = Layouts.wrapCanvasLines((t) => c.measureText(t).width, txt.judul, titleMaxW, isLandscape ? 2 : 3);
    }

    const titleLineH = Math.round(titleFontSize * 1.28);
    c.fillStyle = '#0B1120';
    c.textAlign = 'center';
    c.textBaseline = 'top';
    titleLines.forEach((line, idx) => {
      c.fillText(line, Math.round(W / 2), titleTopY + idx * titleLineH, titleMaxW);
    });
    const titleBottomY = titleTopY + titleLines.length * titleLineH;

    // Bottom Orange Footer + Right Website Pill + Social Icons
    const footerH = Math.round(isLandscape ? H * 0.088 : (isSquare ? H * 0.085 : H * 0.072));
    const footerY = H - footerH;
    const footerGrad = c.createLinearGradient(0, footerY, W, H);
    footerGrad.addColorStop(0, '#FF6D00');
    footerGrad.addColorStop(1, '#F25C00');
    c.fillStyle = footerGrad;
    c.fillRect(0, footerY, W, footerH);

    const webPillH = Math.round(Math.max(30 * scaleBase, footerH * 0.42));
    const webPillY = footerY - Math.round(webPillH * 0.48);
    if (txt.web) {
      const webFontSize = Math.round(webPillH * 0.46);
      c.font = `700 ${webFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;
      const webW = c.measureText(txt.web).width;
      const webPillW = Math.min(Math.round(W * 0.46), Math.round(webW + padX * 2.1));

      c.save();
      c.fillStyle = '#B84B08';
      roundRectPath(c, W - webPillW, webPillY, webPillW + Math.round(webPillH / 2), webPillH, Math.round(webPillH / 2));
      c.fill();
      c.fillStyle = '#FFFFFF';
      c.textAlign = 'right';
      c.textBaseline = 'middle';
      c.fillText(txt.web, W - Math.round(padX * 0.8), webPillY + Math.round(webPillH / 2) + 1, webPillW - padX);
      c.restore();
    }

    if (txt.socials.length) {
      const socFontSize = Math.round(Math.max(18 * scaleBase, footerH * 0.25));
      const iconSize = Math.round(socFontSize * 1.18);
      const itemGap = Math.round(W * 0.036);
      const iconTextGap = Math.round(iconSize * 0.42);
      c.font = `600 ${socFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;

      const measured = txt.socials.map((s) => ({ ...s, w: iconSize + iconTextGap + c.measureText(s.label).width }));
      const totalSocW = measured.reduce((acc, m) => acc + m.w, 0) + itemGap * (measured.length - 1);
      let sx = Math.round((W - totalSocW) / 2);
      const sy = footerY + Math.round(footerH * 0.62);

      c.fillStyle = '#FFFFFF';
      c.textAlign = 'left';
      c.textBaseline = 'middle';
      measured.forEach((m) => {
        drawSocialIcon(c, m.kind, sx + Math.round(iconSize / 2), sy, iconSize, '#FFFFFF');
        c.fillText(m.label, sx + iconSize + iconTextGap, sy + 1);
        sx += m.w + itemGap;
      });
    }

    // Media Grid
    const gridMarginX = Math.round(W * 0.042);
    const gridTopY = titleBottomY + Math.round(H * 0.018);
    const gridBottomY = Math.min(footerY, webPillY) - Math.round(H * 0.022);
    const gridBox = {
      x: gridMarginX,
      y: gridTopY,
      width: Math.max(100, W - gridMarginX * 2),
      height: Math.max(120, gridBottomY - gridTopY),
    };

    const layoutObj = Layouts.getLayoutById(state.slotCount, state.layoutId);
    const gapPx = Math.round(Math.max(12 * scaleBase, Math.min(26 * scaleBase, Math.min(W, H) * 0.018)));
    const rects = Layouts.computeSlotRects(layoutObj, gridBox, gapPx);
    drawMediaSlots(c, rects, W, H, scaleBase, forExport, 'rgba(15, 23, 42, 0.08)');
    c.restore();
    return rects;
  }

  // --- STYLE 2: Editorial Modern (Taktis Bersih & Berkarakter) ---
  function renderStyleEditorialModern(c, W, H, forExport) {
    const isLandscape = W > H;
    const isSquare = W === H;
    const scaleBase = Math.min(W, H) / 1080;
    const txt = collectFormTexts();
    const activeLogos = getActiveLogos();
    const padX = Math.round(W * 0.042);

    c.save();
    c.imageSmoothingEnabled = true;
    c.imageSmoothingQuality = 'high';

    // 1. Clean Warm-Porcelain Base Canvas
    c.fillStyle = '#F8FAFC';
    c.fillRect(0, 0, W, H);

    // 2. Slim Solid Basarnas Orange Top Accent Ribbon (consistent across vertical & horizontal)
    const topRibbonH = Math.max(10, Math.round(12 * scaleBase));
    const ribbonGrad = c.createLinearGradient(0, 0, W, 0);
    ribbonGrad.addColorStop(0, '#EA580C');
    ribbonGrad.addColorStop(1, '#F97316');
    c.fillStyle = ribbonGrad;
    c.fillRect(0, 0, W, topRibbonH);

    // 3. Crisp White Institutional Kop Grid (3 Logos + Nama, Alamat, Emergency Call/WA, e-Mail — TANPA Tanggal)
    const mastH = Math.round(isLandscape ? H * 0.132 : (isSquare ? H * 0.138 : H * 0.118));
    const mastY = topRibbonH;
    c.fillStyle = '#FFFFFF';
    c.fillRect(0, mastY, W, mastH);

    // Subtle Hairline Border below Kop
    c.fillStyle = '#E2E8F0';
    c.fillRect(0, mastY + mastH, W, Math.max(1.5, Math.round(2 * scaleBase)));

    // Left: 3 Official Logos + Vertical Hairline
    const logoH = Math.round(mastH * 0.56);
    const logoGap = Math.round(14 * scaleBase);
    let cursorX = padX;
    const mastMidY = mastY + Math.round(mastH / 2);

    activeLogos.forEach((img) => {
      const aspect = (img.naturalWidth || 1) / Math.max(1, img.naturalHeight || 1);
      const drawW = Math.min(Math.round(logoH * aspect), Math.round(logoH * 1.35));
      c.drawImage(img, cursorX, mastMidY - Math.round(logoH / 2), drawW, logoH);
      cursorX += drawW + logoGap;
    });

    if (activeLogos.length > 0) {
      c.fillStyle = '#CBD5E1';
      c.fillRect(cursorX + Math.round(2 * scaleBase), mastMidY - Math.round(logoH * 0.44), Math.max(2, Math.round(2.5 * scaleBase)), Math.round(logoH * 0.88));
      cursorX += Math.round(18 * scaleBase);
    }

    // Kop Identity & Contact Stack (full breathable width right of logos)
    const satkerMaxW = Math.max(220, W - padX - cursorX);
    const emailLabel = txt.email ? (txt.email.toLowerCase().startsWith('e-mail') ? txt.email : `e-Mail: ${txt.email}`) : '';
    const kopNameSize = Math.round(Math.min(mastH * (isLandscape ? 0.21 : 0.165), satkerMaxW / Math.max(22, txt.namaSatker.length * 0.54)));
    const kopSubSize = Math.round(kopNameSize * 0.74);
    const kopEmSize = Math.round(kopNameSize * 0.76);

    const kopRows = [
      { text: txt.namaSatker, font: `800 ${kopNameSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#0F172A' },
    ];
    if (txt.alamat) {
      kopRows.push({ text: txt.alamat, font: `600 ${kopSubSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#475569' });
    }
    if (isLandscape) {
      // Landscape: gabungkan Emergency Call + WA dan e-Mail dalam satu baris rapi (jika muat) agar Kop 3 baris lega
      const combined = emailLabel ? `${txt.emergencyLine}   \u2022   ${emailLabel}` : txt.emergencyLine;
      c.font = `700 ${kopEmSize}px "Plus Jakarta Sans", Arial, sans-serif`;
      if (emailLabel && c.measureText(combined).width <= satkerMaxW) {
        kopRows.push({
          split: true,
          leftText: txt.emergencyLine,
          leftFont: `800 ${kopEmSize}px "Plus Jakarta Sans", Arial, sans-serif`,
          leftColor: '#C2410C',
          rightText: `   \u2022   ${emailLabel}`,
          rightFont: `600 ${kopSubSize}px "Plus Jakarta Sans", Arial, sans-serif`,
          rightColor: '#475569',
        });
      } else {
        if (txt.emergencyLine) {
          kopRows.push({ text: txt.emergencyLine, font: `800 ${kopEmSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#C2410C' });
        }
        if (emailLabel) {
          kopRows.push({ text: emailLabel, font: `600 ${kopSubSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#475569' });
        }
      }
    } else {
      if (txt.emergencyLine) {
        kopRows.push({ text: txt.emergencyLine, font: `800 ${kopEmSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#C2410C' });
      }
      if (emailLabel) {
        kopRows.push({ text: emailLabel, font: `600 ${kopSubSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#475569' });
      }
    }

    const rowStep = Math.round(mastH * (kopRows.length >= 4 ? 0.215 : 0.275));
    const kopStartY = Math.round(mastMidY - ((kopRows.length - 1) * rowStep) / 2);
    c.textAlign = 'left';
    c.textBaseline = 'middle';
    kopRows.forEach((row, i) => {
      const ry = kopStartY + i * rowStep;
      if (row.split) {
        c.font = row.leftFont;
        c.fillStyle = row.leftColor;
        c.fillText(row.leftText, cursorX, ry, satkerMaxW);
        const lw = c.measureText(row.leftText).width;
        c.font = row.rightFont;
        c.fillStyle = row.rightColor;
        c.fillText(row.rightText, cursorX + lw, ry, Math.max(40, satkerMaxW - lw));
      } else {
        c.font = row.font;
        c.fillStyle = row.color;
        c.fillText(row.text, cursorX, ry, satkerMaxW);
      }
    });

    // 4. Editorial Kicker + Tanggal Row (Selalu di bawah Kop pada SEMUA orientasi) & Bold Headline
    const headlineTopY = mastY + mastH + Math.round(H * (isLandscape ? 0.022 : 0.017));
    const hariUpper = String(txt.hari || '').trim().toUpperCase();
    const kickerText = isGeneralMode()
      ? (!hariUpper || /^DOKUMENTASI\b/.test(hariUpper) ? (hariUpper || 'DOKUMENTASI KEGIATAN') : `DOKUMENTASI \u2022 ${hariUpper}`)
      : `DOKUMENTASI OPERASI SAR \u2022 ${txt.hari}`;
    const kickerH = Math.round(Math.max(28 * scaleBase, Math.min(W, H) * 0.030));
    const kickerFont = Math.round(kickerH * 0.48);
    c.font = `800 ${kickerFont}px "Plus Jakarta Sans", Arial, sans-serif`;
    const kickerW = Math.round(c.measureText(kickerText).width + 26 * scaleBase);

    c.save();
    c.fillStyle = '#EA580C';
    roundRectPath(c, padX, headlineTopY, kickerW, kickerH, Math.round(7 * scaleBase));
    c.fill();
    c.fillStyle = '#FFFFFF';
    c.textAlign = 'left';
    c.textBaseline = 'middle';
    c.fillText(kickerText, padX + Math.round(13 * scaleBase), headlineTopY + Math.round(kickerH / 2) + 1);
    c.restore();

    if (txt.tanggal) {
      const dateFont = Math.round(kickerH * 0.48);
      c.font = `700 ${dateFont}px "Plus Jakarta Sans", Arial, sans-serif`;
      const tw = c.measureText(txt.tanggal).width;
      const maxDateW = Math.max(120, W - padX * 2 - kickerW - Math.round(18 * scaleBase));
      const datePillW = Math.min(maxDateW, Math.round(tw + 30 * scaleBase));
      const datePillX = W - padX - datePillW;
      c.save();
      c.fillStyle = '#0F172A';
      roundRectPath(c, datePillX, headlineTopY, datePillW, kickerH, Math.round(kickerH / 2));
      c.fill();
      c.fillStyle = '#FFFFFF';
      c.textAlign = 'center';
      c.textBaseline = 'middle';
      c.fillText(txt.tanggal, datePillX + Math.round(datePillW / 2), headlineTopY + Math.round(kickerH / 2) + 1, datePillW - Math.round(18 * scaleBase));
      c.restore();
    }

    const titleY = headlineTopY + kickerH + Math.round(12 * scaleBase);
    const titleMaxW = W - padX * 2;
    let titleFontSize = Math.round(Math.min(W, H) * (isLandscape ? 0.032 : 0.035));
    c.font = `800 ${titleFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;
    let titleLines = Layouts.wrapCanvasLines((t) => c.measureText(t).width, txt.judul, titleMaxW, isLandscape ? 2 : 3);
    if (titleLines.length >= 3 && titleFontSize > 24 * scaleBase) {
      titleFontSize = Math.round(titleFontSize * 0.88);
      c.font = `800 ${titleFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;
      titleLines = Layouts.wrapCanvasLines((t) => c.measureText(t).width, txt.judul, titleMaxW, isLandscape ? 2 : 3);
    }
    const titleLineH = Math.round(titleFontSize * 1.24);
    c.fillStyle = '#0F172A';
    c.textAlign = 'left';
    c.textBaseline = 'top';
    titleLines.forEach((ln, idx) => {
      c.fillText(ln, padX, titleY + idx * titleLineH, titleMaxW);
    });
    const titleBottomY = titleY + titleLines.length * titleLineH;

    // 5. Minimal & Clean Deep Navy Footer (#0F172A) — Hanya Website & Media Sosial (tidak ramai)
    const footerH = Math.round(isLandscape ? H * 0.064 : (isSquare ? H * 0.088 : H * 0.072));
    const footerY = H - footerH;
    c.fillStyle = '#0F172A';
    c.fillRect(0, footerY, W, footerH);
    c.fillStyle = '#F97316';
    c.fillRect(0, footerY, W, Math.max(3, Math.round(4 * scaleBase)));

    const usableFooterW = W - padX * 2;
    if (isLandscape) {
      // Landscape: Satu baris bersih — Website di kiri, Media Sosial di kanan
      const fMidY = footerY + Math.round(footerH * 0.54);
      const fFontSize = Math.round(Math.max(16 * scaleBase, footerH * 0.30));

      if (txt.web) {
        c.textAlign = 'left';
        c.textBaseline = 'middle';
        c.fillStyle = '#FFFFFF';
        c.font = `700 ${fFontSize}px "Plus Jakarta Sans", Arial, sans-serif`;
        c.fillText(txt.web, padX, fMidY, Math.round(usableFooterW * 0.42));
      }

      if (txt.socials.length) {
        const socSize = Math.round(fFontSize * 0.92);
        const iconSz = Math.round(socSize * 1.15);
        const itemGap = Math.round(24 * scaleBase);
        const iconGap = Math.round(8 * scaleBase);
        c.font = `600 ${socSize}px "Plus Jakarta Sans", Arial, sans-serif`;
        const items = txt.socials.map((s) => ({ ...s, w: iconSz + iconGap + c.measureText(s.label).width }));
        const totalW = items.reduce((acc, m) => acc + m.w, 0) + itemGap * (items.length - 1);
        let sx = W - padX - totalW;

        c.textAlign = 'left';
        c.textBaseline = 'middle';
        c.fillStyle = '#E2E8F0';
        items.forEach((m) => {
          drawSocialIcon(c, m.kind, sx + Math.round(iconSz / 2), fMidY, iconSz, '#F97316');
          c.fillText(m.label, sx + iconSz + iconGap, fMidY + 1);
          sx += m.w + itemGap;
        });
      }
    } else {
      // Portrait / Square: 2 baris terpusat yang lega (Website di atas, Media Sosial di bawah)
      const hasBoth = Boolean(txt.web && txt.socials.length);
      const webY = hasBoth ? footerY + Math.round(footerH * 0.34) : footerY + Math.round(footerH * 0.54);
      const socY = hasBoth ? footerY + Math.round(footerH * 0.72) : footerY + Math.round(footerH * 0.54);
      const webSize = Math.round(Math.max(18 * scaleBase, footerH * 0.22));
      const socSize = Math.round(webSize * 0.88);

      if (txt.web) {
        c.textAlign = 'center';
        c.textBaseline = 'middle';
        c.fillStyle = '#FFFFFF';
        c.font = `700 ${webSize}px "Plus Jakarta Sans", Arial, sans-serif`;
        c.fillText(txt.web, Math.round(W / 2), webY, usableFooterW);
      }

      if (txt.socials.length) {
        const iconSz = Math.round(socSize * 1.15);
        const itemGap = Math.round(28 * scaleBase);
        const iconGap = Math.round(8 * scaleBase);
        c.font = `600 ${socSize}px "Plus Jakarta Sans", Arial, sans-serif`;
        const items = txt.socials.map((s) => ({ ...s, w: iconSz + iconGap + c.measureText(s.label).width }));
        const totalW = items.reduce((acc, m) => acc + m.w, 0) + itemGap * (items.length - 1);
        let sx = Math.round((W - totalW) / 2);

        c.textAlign = 'left';
        c.textBaseline = 'middle';
        c.fillStyle = '#CBD5E1';
        items.forEach((m) => {
          drawSocialIcon(c, m.kind, sx + Math.round(iconSz / 2), socY, iconSz, '#F97316');
          c.fillText(m.label, sx + iconSz + iconGap, socY + 1);
          sx += m.w + itemGap;
        });
      }
    }

    // 6. Media Grid on Crisp Light Stage
    const gridTopY = titleBottomY + Math.round(H * 0.020);
    const gridBottomY = footerY - Math.round(H * 0.024);
    const gridBox = {
      x: padX,
      y: gridTopY,
      width: Math.max(100, W - padX * 2),
      height: Math.max(120, gridBottomY - gridTopY),
    };

    const layoutObj = Layouts.getLayoutById(state.slotCount, state.layoutId);
    const gapPx = Math.round(Math.max(14 * scaleBase, Math.min(28 * scaleBase, Math.min(W, H) * 0.019)));
    const rects = Layouts.computeSlotRects(layoutObj, gridBox, gapPx);
    drawMediaSlots(c, rects, W, H, scaleBase, forExport, 'rgba(15, 23, 42, 0.12)');
    c.restore();
    return rects;
  }

  function renderCollageToContext(targetCtx, targetW, targetH, forExport) {
    if (state.styleId === 'editorial_modern') {
      return renderStyleEditorialModern(targetCtx, targetW, targetH, forExport);
    }
    return renderStyleKlasikKpp(targetCtx, targetW, targetH, forExport);
  }

  function drawCollage(forExport, skipOverlayRebuild) {
    const rects = renderCollageToContext(ctx, canvas.width, canvas.height, !!forExport);
    state.slotRectsCache = rects;
    if (!forExport && !skipOverlayRebuild) {
      syncInteractiveOverlay();
    }
  }

  // --- Audio Graph Helpers (Web Audio Live Preview & Stream Mixing) ---
  function ensureAudioContext() {
    const AC = window.AudioContext || window.webkitAudioContext;
    if (!AC) return null;
    if (!state.audioCtx || state.audioCtx.state === 'closed') {
      state.audioCtx = new AC();
    }
    if (state.audioCtx.state === 'suspended') {
      state.audioCtx.resume().catch(() => {});
    }
    return state.audioCtx;
  }

  function attachSlotAudio(s) {
    if (!s || s.type !== 'video' || !s.el) return;
    const ctx = ensureAudioContext();
    if (!ctx) {
      if (s.el) s.el.muted = !!s.muted;
      return;
    }
    try {
      if (!s._sourceNode) {
        s._sourceNode = ctx.createMediaElementSource(s.el);
        s._previewGain = ctx.createGain();
        s._sourceNode.connect(s._previewGain);
        s._previewGain.connect(ctx.destination);
      }
      s.el.muted = false;
      if (s._previewGain) {
        s._previewGain.gain.setValueAtTime(s.muted ? 0 : 1, ctx.currentTime);
      }
    } catch (e) {
      if (s.el) s.el.muted = !!s.muted;
    }
  }

  // --- Video Live Preview Loop ---
  function startPlayback() {
    if (!hasVideoSlot()) return;
    state.isPlaying = true;
    if (els.labelToggleVideoPlay) els.labelToggleVideoPlay.textContent = 'Jeda Video';

    ensureAudioContext();

    activeSlotsSlice().forEach((s) => {
      if (s && s.type === 'video' && s.el) {
        attachSlotAudio(s);
        s.el.play().catch(() => {});
      }
    });

    const tick = () => {
      if (!state.isPlaying && !state.isExporting) return;
      drawCollage(false, true);
      state.rafId = window.requestAnimationFrame(tick);
    };
    if (state.rafId) window.cancelAnimationFrame(state.rafId);
    state.rafId = window.requestAnimationFrame(tick);
  }

  function stopPlayback() {
    state.isPlaying = false;
    if (els.labelToggleVideoPlay) els.labelToggleVideoPlay.textContent = 'Putar Video';
    if (state.rafId) {
      window.cancelAnimationFrame(state.rafId);
      state.rafId = null;
    }
    activeSlotsSlice().forEach((s) => {
      if (s && s.type === 'video' && s.el) {
        try { s.el.pause(); } catch (e) {}
      }
    });
    drawCollage(false, true);
  }

  // --- High-Resolution Offscreen Image & Video Exporters (FHD / 2K / 4K) ---
  function exportImageBlob(mimeType, qualityOverride) {
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const expDim = Layouts.resolveExportDimensions(preset, qualityOverride || state.qualityId);
    const offCanvas = document.createElement('canvas');
    offCanvas.width = expDim.width;
    offCanvas.height = expDim.height;
    const offCtx = offCanvas.getContext('2d');
    renderCollageToContext(offCtx, expDim.width, expDim.height, true);

    return new Promise((resolve, reject) => {
      offCanvas.toBlob((blob) => {
        if (blob) {
          resolve({ blob, width: expDim.width, height: expDim.height });
        } else {
          reject(new Error('Gagal merender gambar kolase resolusi tinggi.'));
        }
      }, mimeType || 'image/jpeg', expDim.jpegQuality);
    });
  }

  function pickSupportedVideoMime(hasAudio = false) {
    if (typeof MediaRecorder === 'undefined') return '';
    const candidatesWithAudio = [
      'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
      'video/mp4;codecs=avc1,mp4a.40.2',
      'video/webm;codecs=vp9,opus',
      'video/webm;codecs=vp8,opus',
      'video/webm;codecs=h264,opus',
      'video/webm',
      'video/mp4',
    ];
    const candidatesWithoutAudio = [
      'video/mp4;codecs=avc1.42E01E,mp4a.40.2',
      'video/mp4',
      'video/webm;codecs=vp9,opus',
      'video/webm;codecs=vp8,opus',
      'video/webm',
    ];
    const candidates = hasAudio ? candidatesWithAudio : candidatesWithoutAudio;
    for (const mime of candidates) {
      if (typeof MediaRecorder.isTypeSupported === 'function' && MediaRecorder.isTypeSupported(mime)) {
        return mime;
      }
    }
    return '';
  }

  function resolveVideoExportSeconds() {
    const sel = els.selectVideoDuration ? els.selectVideoDuration.value : 'auto';
    if (sel !== 'auto') {
      return Math.max(3, Math.min(20, Number(sel) || 8));
    }
    let maxDur = 0;
    activeSlotsSlice().forEach((s) => {
      if (s && s.type === 'video') maxDur = Math.max(maxDur, Number(s.duration) || 6);
    });
    return Math.max(4, Math.min(12, Math.round(maxDur || 8)));
  }

  function setProgressUI(visible, label, pct) {
    if (!els.exportProgressWrap) return;
    els.exportProgressWrap.classList.toggle('hidden', !visible);
    if (els.exportProgressLabel && label) els.exportProgressLabel.textContent = label;
    const clamped = Math.max(0, Math.min(100, Math.round(pct || 0)));
    if (els.exportProgressPct) els.exportProgressPct.textContent = clamped + '%';
    if (els.exportProgressBar) els.exportProgressBar.style.width = clamped + '%';
  }

  async function exportVideoBlob(forArchive) {
    const videoSlots = activeSlotsSlice().filter((s) => s && s.type === 'video' && s.el);
    const hasAudioSlot = videoSlots.some((s) => !s.muted);
    const mime = pickSupportedVideoMime(hasAudioSlot);
    if (!mime || typeof canvas.captureStream !== 'function') {
      throw new Error('Browser ini tidak mendukung perekaman video kolase (MediaRecorder). Gunakan Chrome/Edge terbaru atau ekspor sebagai gambar.');
    }

    stopPlayback();
    state.isExporting = true;
    const durationSec = resolveVideoExportSeconds();
    const durationMs = durationSec * 1000;
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const expDim = Layouts.resolveExportDimensions(preset, state.qualityId);

    const recCanvas = document.createElement('canvas');
    recCanvas.width = expDim.width;
    recCanvas.height = expDim.height;
    const recCtx = recCanvas.getContext('2d');

    await Promise.all(videoSlots.map((s) => new Promise((res) => {
      const v = s.el;
      const done = () => res();
      v.addEventListener('seeked', done, { once: true });
      try { v.currentTime = 0; } catch (e) { res(); }
      setTimeout(res, 350);
    })));

    const videoStream = recCanvas.captureStream(30);
    let audioDest = null;
    const connectedAudioNodes = [];
    let combinedStream = videoStream;
    if (hasAudioSlot && (window.AudioContext || window.webkitAudioContext)) {
      try {
        const ctx = ensureAudioContext();
        if (ctx) {
          audioDest = ctx.createMediaStreamDestination();
          videoSlots.forEach((s) => {
            if (s.muted || !s.el) return;
            attachSlotAudio(s);
            if (s._sourceNode) {
              if (!s._recordGain) {
                s._recordGain = ctx.createGain();
                s._sourceNode.connect(s._recordGain);
              }
              s._recordGain.gain.setValueAtTime(1, ctx.currentTime);
              s._recordGain.connect(audioDest);
              connectedAudioNodes.push(s._recordGain);
            }
            if (s._previewGain) {
              s._previewGain.gain.setValueAtTime(0, ctx.currentTime);
            }
          });
          if (connectedAudioNodes.length > 0 && audioDest.stream.getAudioTracks().length > 0) {
            combinedStream = new MediaStream([
              ...videoStream.getVideoTracks(),
              ...audioDest.stream.getAudioTracks(),
            ]);
          }
        }
      } catch (e) {
        combinedStream = videoStream;
      }
    }

    const disconnectAudio = () => {
      connectedAudioNodes.forEach((node) => {
        try { if (audioDest) node.disconnect(audioDest); } catch (e) {}
      });
      videoSlots.forEach((s) => {
        if (s._previewGain && state.audioCtx) {
          s._previewGain.gain.setValueAtTime(s.muted ? 0 : 1, state.audioCtx.currentTime);
        }
      });
    };

    // Untuk simpan arsip (server mendukung hingga 100 MB), jaga bitrate visual tajam
    let targetBps = expDim.videoBitsPerSecond;
    if (forArchive) {
      const maxArchiveBps = Math.floor((85 * 1024 * 1024 * 8) / Math.max(4, durationSec));
      targetBps = Math.min(targetBps, maxArchiveBps);
    }

    const chunks = [];
    const recorder = new MediaRecorder(combinedStream, {
      mimeType: mime,
      videoBitsPerSecond: targetBps,
    });

    return new Promise((resolve, reject) => {
      recorder.ondataavailable = (ev) => {
        if (ev.data && ev.data.size > 0) chunks.push(ev.data);
      };
      recorder.onerror = (ev) => {
        state.isExporting = false;
        disconnectAudio();
        setProgressUI(false, '', 0);
        reject(new Error(ev.error ? ev.error.message : 'Gagal merekam video kolase.'));
      };
      recorder.onstop = () => {
        state.isExporting = false;
        if (state.rafId) {
          window.cancelAnimationFrame(state.rafId);
          state.rafId = null;
        }
        videoSlots.forEach((s) => s.el.pause());
        disconnectAudio();
        setProgressUI(false, '', 0);
        drawCollage(false, true);
        const cleanMime = mime.split(';')[0];
        const blob = new Blob(chunks, { type: cleanMime });
        const ext = cleanMime.includes('mp4') ? 'mp4' : 'webm';
        resolve({ blob, mime: cleanMime, ext, width: expDim.width, height: expDim.height });
      };

      const qLabel = state.qualityId === 'uhd_4k' ? '4K' : (state.qualityId === 'qhd' ? '2K' : 'FHD');
      setProgressUI(true, `Merekam video kolase ${qLabel} (${durationSec} detik)...`, 2);
      videoSlots.forEach((s) => s.el.play().catch(() => {}));

      const startedAt = performance.now();
      const renderLoop = () => {
        if (!state.isExporting) return;
        renderCollageToContext(recCtx, expDim.width, expDim.height, true);
        const elapsed = performance.now() - startedAt;
        const pct = Math.min(99, (elapsed / durationMs) * 100);
        setProgressUI(true, `Merekam video kolase ${qLabel} (${durationSec} detik)...`, pct);
        if (elapsed >= durationMs) {
          if (recorder.state === 'recording') recorder.stop();
          return;
        }
        state.rafId = window.requestAnimationFrame(renderLoop);
      };

      recorder.start(250);
      state.rafId = window.requestAnimationFrame(renderLoop);
    });
  }

  function triggerBlobDownload(blob, filename) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => URL.revokeObjectURL(url), 1500);
  }

  async function handleDownloadImage() {
    if (filledSlotCount() === 0) {
      notify('Unggah minimal 4 foto/video terlebih dahulu sebelum mengunduh.', 'warning');
      return;
    }
    const fmt = (els.selectImageFormat && els.selectImageFormat.value) || 'image/jpeg';
    const ext = fmt === 'image/png' ? 'png' : 'jpg';
    const general = isGeneralMode();
    const hari = (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : cfg.hariLabel);
    const judul = (els.inputDokJudul && els.inputDokJudul.value.trim()) || cfg.judulDefault;
    try {
      setProgressUI(true, 'Merender gambar kolase kualitas tinggi...', 70);
      const res = await exportImageBlob(fmt);
      setProgressUI(false, '', 0);
      triggerBlobDownload(res.blob, Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, ext, general));
      notify(`Gambar kolase (${res.width}x${res.height} px) berhasil diunduh.`, 'success');
    } catch (err) {
      setProgressUI(false, '', 0);
      notify(err.message || 'Gagal mengekspor gambar kolase.', 'error');
    }
  }

  async function handleDownloadVideo() {
    if (!hasVideoSlot()) {
      notify('Belum ada slot video pada kolase ini.', 'warning');
      return;
    }
    if (state.isExporting) return;
    const general = isGeneralMode();
    const hari = (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : cfg.hariLabel);
    const judul = (els.inputDokJudul && els.inputDokJudul.value.trim()) || cfg.judulDefault;
    try {
      const res = await exportVideoBlob(false);
      triggerBlobDownload(res.blob, Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, res.ext, general));
      notify(`Video kolase (${res.width}x${res.height} px) berhasil diekspor.`, 'success');
    } catch (err) {
      notify(err.message || 'Gagal mengekspor video kolase.', 'error');
    }
  }

  function prepareImageSourceBlob(slot) {
    return new Promise((resolve) => {
      if (!slot || !slot.el || !slot.file) {
        resolve(slot ? slot.file : null);
        return;
      }
      const nw = slot.el.naturalWidth || 0;
      const nh = slot.el.naturalHeight || 0;
      const maxDim = 2560;
      if (nw <= maxDim && nh <= maxDim && slot.file.size <= 4 * 1024 * 1024) {
        resolve(slot.file);
        return;
      }
      const ratio = Math.min(1, maxDim / Math.max(1, nw, nh));
      const tw = Math.max(1, Math.round(nw * ratio));
      const th = Math.max(1, Math.round(nh * ratio));
      const tmp = document.createElement('canvas');
      tmp.width = tw;
      tmp.height = th;
      const tctx = tmp.getContext('2d');
      tctx.imageSmoothingEnabled = true;
      tctx.imageSmoothingQuality = 'high';
      tctx.drawImage(slot.el, 0, 0, tw, th);
      tmp.toBlob((blob) => resolve(blob || slot.file), 'image/jpeg', 0.92);
    });
  }

  async function persistNewSlotSources(csrfToken) {
    if (!cfg.sourceApiEndpoint) return;
    const active = activeSlotsSlice();
    const pending = [];
    active.forEach((slot, idx) => {
      if (slot && !slot.serverPath && slot.file) {
        pending.push({ slot, idx });
      }
    });
    if (!pending.length) return;

    const maxVideoBytes = 100 * 1024 * 1024; // 100 MB
    const maxImageBytes = 20 * 1024 * 1024;  // 20 MB

    for (let i = 0; i < pending.length; i++) {
      const { slot } = pending[i];
      const pct = Math.round(((i + 1) / pending.length) * 35);
      setProgressUI(true, `Menyimpan sumber media slot ${i + 1}/${pending.length} agar dapat diedit ulang...`, pct);
      try {
        const payload = slot.type === 'image' ? await prepareImageSourceBlob(slot) : slot.file;
        if (!payload) continue;
        const limit = slot.type === 'video' ? maxVideoBytes : maxImageBytes;
        if (payload.size > limit) {
          notify(`Ukuran berkas "${slot.name}" (${Math.round(payload.size / (1024 * 1024))} MB) melebihi batas simpan server (${Math.round(limit / (1024 * 1024))} MB).`, 'warning');
          continue;
        }
        const sfd = new FormData();
        const fallbackName = slot.type === 'video' ? 'slot.mp4' : 'slot.jpg';
        const fname = slot.name || fallbackName;
        sfd.append('source_file', payload, fname);
        sfd.append('id_operasi', String(state.idOperasi || 0));
        if (csrfToken) sfd.append('csrf_token', csrfToken);
        const sResp = await fetch(cfg.sourceApiEndpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
          body: sfd,
        });
        const sData = await sResp.json();
        if (sResp.ok && sData.success && sData.path) {
          slot.serverPath = sData.path;
        } else {
          console.warn('[DokumentasiStudio] Gagal menyimpan sumber slot:', sData.message || sResp.statusText);
        }
      } catch (e) {
        console.warn('[DokumentasiStudio] Gagal menyimpan sumber slot:', e);
      }
    }
  }

  function buildProjectJson() {
    const slotsMeta = activeSlotsSlice().map((slot) => {
      if (!slot || !slot.serverPath) return null;
      return {
        type: slot.type === 'video' ? 'video' : 'image',
        path: slot.serverPath,
        name: slot.name || '',
        zoom: Math.round((Number(slot.zoom) || 1) * 1000) / 1000,
        offsetX: Math.round(Number(slot.offsetX) || 0),
        offsetY: Math.round(Number(slot.offsetY) || 0),
        muted: slot.muted !== false,
        videoTime: Math.round((Number(slot.videoTime) || 0) * 100) / 100,
      };
    });

    return JSON.stringify({
      version: 1,
      styleId: state.styleId,
      orientation: state.orientation,
      canvasPresetId: state.canvasPresetId,
      qualityId: state.qualityId,
      imageFormat: (els.selectImageFormat && els.selectImageFormat.value) || 'image/jpeg',
      slotCount: state.slotCount,
      layoutId: state.layoutId,
      videoDuration: (els.selectVideoDuration && els.selectVideoDuration.value) || 'auto',
      logos: {
        logo1: !(els.chkShowLogo1 && !els.chkShowLogo1.checked),
        logo2: !(els.chkShowLogo2 && !els.chkShowLogo2.checked),
        logo3: !(els.chkShowLogo3 && !els.chkShowLogo3.checked),
      },
      header: {
        nama: (els.inputHeaderNama && els.inputHeaderNama.value.trim()) || '',
        alamat: (els.inputHeaderAlamat && els.inputHeaderAlamat.value.trim()) || '',
        telp: (els.inputHeaderTelp && els.inputHeaderTelp.value.trim()) || '',
        wa: (els.inputHeaderWa && els.inputHeaderWa.value.trim()) || '',
        email: (els.inputHeaderEmail && els.inputHeaderEmail.value.trim()) || '',
      },
      footer: {
        web: (els.inputFooterWeb && els.inputFooterWeb.value.trim()) || '',
        ig: (els.inputFooterIg && els.inputFooterIg.value.trim()) || '',
        fb: (els.inputFooterFb && els.inputFooterFb.value.trim()) || '',
        x: (els.inputFooterX && els.inputFooterX.value.trim()) || '',
      },
      slots: slotsMeta,
    });
  }

  async function handleSaveToArchive() {
    if (state.isExporting) return;
    const filled = filledSlotCount();
    if (filled < Layouts.MIN_SLOTS) {
      notify(`Isi minimal ${Layouts.MIN_SLOTS} slot foto/video sebelum menyimpan ke arsip operasi (saat ini ${filled} terisi).`, 'warning');
      return;
    }

    const saveButtons = [els.btnSaveArchive, els.btnBottomSaveArchive].filter(Boolean);
    saveButtons.forEach((b) => { b.disabled = true; });
    const isEdit = !!(cfg.editDoc && cfg.editDoc.id);

    try {
      const csrfMeta = document.querySelector('meta[name="csrf-token"]');
      const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : (window.CSRF_TOKEN || '');

      // 1. Simpan aset sumber per-slot yang baru agar arsip dapat diedit penuh kapan saja
      await persistNewSlotSources(csrfToken);

      const preset = Layouts.getCanvasPreset(state.canvasPresetId);
      const general = isGeneralMode();
      const hari = (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : cfg.hariLabel);
      const judul = (els.inputDokJudul && els.inputDokJudul.value.trim()) || (general ? 'Dokumentasi Kegiatan KPP Kelas A Kendari' : cfg.judulDefault);
      const tanggal = (els.inputDokTanggal && els.inputDokTanggal.value.trim()) || cfg.tanggalDefault;
      const hasVid = hasVideoSlot();

      let mediaBlob;
      let mediaFilename;
      let outW = preset.width;
      let outH = preset.height;
      let thumbBlob = null;

      if (hasVid) {
        const thumbRes = await exportImageBlob('image/jpeg', 'fhd');
        thumbBlob = thumbRes.blob;
        const vidRes = await exportVideoBlob(true);
        if (vidRes.blob.size > 20 * 1024 * 1024) {
          throw new Error('Ukuran video kolase melebihi batas server 20 MB. Coba kurangi durasi atau turunkan kualitas ke Full HD / 2K.');
        }
        mediaBlob = vidRes.blob;
        mediaFilename = Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, vidRes.ext, general);
        outW = vidRes.width;
        outH = vidRes.height;
      } else {
        const fmt = (els.selectImageFormat && els.selectImageFormat.value) || 'image/jpeg';
        const ext = fmt === 'image/png' ? 'png' : 'jpg';
        setProgressUI(true, 'Merender gambar kolase resolusi tinggi...', 70);
        const imgRes = await exportImageBlob(fmt);
        if (imgRes.blob.size > 20 * 1024 * 1024) {
          throw new Error('Ukuran gambar melebihi batas server 20 MB. Gunakan format JPG (Tajam) untuk ukuran berkas lebih ringkas.');
        }
        mediaBlob = imgRes.blob;
        mediaFilename = Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, ext, general);
        outW = imgRes.width;
        outH = imgRes.height;
      }

      setProgressUI(true, isEdit ? 'Menyimpan perubahan ke arsip dokumentasi...' : 'Mengunggah hasil kolase ke arsip dokumentasi...', 95);

      const fd = new FormData();
      fd.append('media_file', mediaBlob, mediaFilename);
      if (thumbBlob) fd.append('thumb_file', thumbBlob, 'poster.jpg');
      fd.append('id_operasi', String(state.idOperasi || 0));
      fd.append('judul', judul);
      fd.append('hari_ke', hari);
      fd.append('tanggal_label', tanggal);
      fd.append('aspect_ratio', preset.ratio);
      fd.append('orientation', preset.orientation);
      fd.append('slot_count', String(state.slotCount));
      fd.append('layout_preset', state.styleId + ':' + state.layoutId);
      fd.append('width_px', String(outW));
      fd.append('height_px', String(outH));
      fd.append('project_json', buildProjectJson());
      if (csrfToken) fd.append('csrf_token', csrfToken);

      const endpoint = isEdit ? `${cfg.apiEndpoint}/${cfg.editDoc.id}` : cfg.apiEndpoint;
      const resp = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
        body: fd,
      });
      const data = await resp.json();
      setProgressUI(false, '', 0);

      if (!resp.ok || !data.success) {
        throw new Error(data.error || data.message || 'Gagal menyimpan dokumentasi ke arsip.');
      }

      const successMsg = isEdit
        ? 'Perubahan dokumentasi kolase berhasil disimpan!'
        : (general ? 'Dokumentasi umum berhasil disimpan ke arsip!' : 'Dokumentasi kolase berhasil disimpan ke arsip operasi!');
      notify(successMsg, 'success');
      if (typeof Swal !== 'undefined') {
        const go = await Swal.fire({
          title: isEdit ? 'Perubahan Tersimpan' : 'Dokumentasi Tersimpan',
          text: isEdit
            ? `Arsip dokumentasi #${cfg.editDoc.id} (${outW}x${outH} px) berhasil diperbarui.`
            : (general
                ? `Kolase resolusi ${outW}x${outH} px telah tersimpan rapi di Pusat Dokumentasi.`
                : `Kolase resolusi ${outW}x${outH} px telah tersimpan di arsip Operasi SAR #${state.idOperasi} dan Pusat Dokumentasi.`),
          icon: 'success',
          showCancelButton: true,
          confirmButtonText: cfg.allowOperasiSelect ? 'Lihat Pusat Dokumentasi' : 'Lihat Arsip Operasi',
          cancelButtonText: isEdit ? 'Lanjut Edit' : 'Buat Kolase Lagi',
        });
        if (go.isConfirmed && cfg.backDetailUrl) {
          window.location.href = cfg.backDetailUrl;
        }
      }
    } catch (err) {
      setProgressUI(false, '', 0);
      notify(err.message || 'Gagal menyimpan arsip dokumentasi.', 'error');
    } finally {
      saveButtons.forEach((b) => { b.disabled = false; });
    }
  }

  // --- Wire Events ---
  els.styleBtns.forEach((btn) => {
    btn.addEventListener('click', () => setStyle(btn.dataset.styleId));
  });

  els.orientTabs.forEach((tab) => {
    tab.addEventListener('click', () => setOrientation(tab.dataset.orient || 'vertical'));
  });

  els.slotCountBtns.forEach((btn) => {
    btn.addEventListener('click', () => setSlotCount(Number(btn.dataset.slotCount)));
  });

  if (els.selectExportQuality) {
    els.selectExportQuality.addEventListener('change', () => {
      state.qualityId = els.selectExportQuality.value || 'qhd';
      syncModeAndCounters();
    });
  }

  if (els.bulkMediaInput) {
    els.bulkMediaInput.addEventListener('change', (e) => {
      handleBulkFiles(e.target.files);
      e.target.value = '';
    });
  }

  if (els.bulkMediaDropzone) {
    ['dragenter', 'dragover'].forEach((evt) => {
      els.bulkMediaDropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        els.bulkMediaDropzone.classList.add('is-dragover');
      });
    });
    ['dragleave', 'drop'].forEach((evt) => {
      els.bulkMediaDropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        els.bulkMediaDropzone.classList.remove('is-dragover');
      });
    });
    els.bulkMediaDropzone.addEventListener('drop', (e) => {
      if (e.dataTransfer && e.dataTransfer.files) handleBulkFiles(e.dataTransfer.files);
    });
  }

  if (els.singleSlotInput) {
    els.singleSlotInput.addEventListener('change', async (e) => {
      const f = e.target.files && e.target.files[0];
      if (!f) return;
      await loadFileIntoSlot(f, replaceTargetIndex);
      state.selectedSlotIndex = replaceTargetIndex;
      refreshAllUI();
      e.target.value = '';
    });
  }

  if (els.btnClearAllSlots) {
    els.btnClearAllSlots.addEventListener('click', clearAllSlots);
  }

  // Bulk mode controls on Preview
  if (els.btnToggleBulkMode) {
    els.btnToggleBulkMode.addEventListener('click', () => {
      state.bulkMode = !state.bulkMode;
      if (!state.bulkMode) state.bulkSelected.clear();
      syncModeAndCounters();
      syncInteractiveOverlay();
    });
  }
  if (els.btnBulkSelectAll) {
    els.btnBulkSelectAll.addEventListener('click', () => {
      for (let i = 0; i < state.slotCount; i++) {
        if (state.slots[i]) state.bulkSelected.add(i);
      }
      state.bulkMode = true;
      syncModeAndCounters();
      syncInteractiveOverlay();
    });
  }
  if (els.btnBulkDeleteSelected) {
    els.btnBulkDeleteSelected.addEventListener('click', clearSelectedBulkSlots);
  }
  if (els.btnBulkCancel) {
    els.btnBulkCancel.addEventListener('click', () => {
      state.bulkMode = false;
      state.bulkSelected.clear();
      syncModeAndCounters();
      syncInteractiveOverlay();
    });
  }

  // Pilihan Operasi SAR Aktif vs Dokumentasi Umum (saat Studio dibuka dari Pusat Dokumentasi)
  if (els.selectDokOperasi) {
    els.selectDokOperasi.addEventListener('change', () => {
      const nextId = Number(els.selectDokOperasi.value) || 0;
      state.idOperasi = nextId > 0 ? nextId : 0;
      const opt = state.idOperasi > 0 ? findOperasiOption(state.idOperasi) : null;
      state.namaOperasi = opt ? String(opt.nama || '') : '';
      syncOperasiSelectionUI(true);
      drawCollage(false, true);
    });
  }

  // Sync Hari label into default title prefix & header badge
  if (els.inputDokHari && els.inputDokJudul) {
    els.inputDokHari.addEventListener('input', () => {
      const hariVal = els.inputDokHari.value.trim().toUpperCase();
      if (els.studioHeaderHariLabel) {
        els.studioHeaderHariLabel.textContent = hariVal || (isGeneralMode() ? 'KEGIATAN' : 'H1');
      }
      if (!isGeneralMode() && hariVal && /^Dok Operasi SAR\s+H\.?\d+/i.test(els.inputDokJudul.value)) {
        els.inputDokJudul.value = els.inputDokJudul.value.replace(/^Dok Operasi SAR\s+H\.?\d+/i, 'Dok Operasi SAR ' + hariVal);
      }
    });
  }

  // Live text & logo inputs redraw canvas immediately
  [
    els.inputDokHari, els.inputDokTanggal, els.inputDokJudul,
    els.inputHeaderNama, els.inputHeaderAlamat, els.inputHeaderTelp, els.inputHeaderWa, els.inputHeaderEmail,
    els.inputFooterWeb, els.inputFooterIg, els.inputFooterFb, els.inputFooterX,
  ].forEach((inp) => {
    if (!inp) return;
    inp.addEventListener('input', () => drawCollage(false, true));
  });
  [els.chkShowLogo1, els.chkShowLogo2, els.chkShowLogo3].forEach((chk) => {
    if (!chk) return;
    chk.addEventListener('change', () => drawCollage(false, true));
  });

  if (els.btnToggleVideoPlay) {
    els.btnToggleVideoPlay.addEventListener('click', () => {
      if (state.isPlaying) stopPlayback();
      else startPlayback();
    });
  }

  [els.btnTopDownloadImg, els.btnBottomDownloadImg].forEach((b) => {
    if (b) b.addEventListener('click', handleDownloadImage);
  });
  [els.btnTopDownloadVid, els.btnBottomDownloadVid].forEach((b) => {
    if (b) b.addEventListener('click', handleDownloadVideo);
  });
  [els.btnSaveArchive, els.btnBottomSaveArchive].forEach((b) => {
    if (b) b.addEventListener('click', handleSaveToArchive);
  });

  // Expose minimal studio helper for verification
  window.__QR_SAR_DOK_STUDIO__ = {
    getState: () => ({
      editDocId: cfg.editDoc ? cfg.editDoc.id : null,
      idOperasi: state.idOperasi,
      isGeneral: isGeneralMode(),
      styleId: state.styleId,
      orientation: state.orientation,
      canvasPresetId: state.canvasPresetId,
      qualityId: state.qualityId,
      slotCount: state.slotCount,
      layoutId: state.layoutId,
      filled: filledSlotCount(),
      hasVideo: hasVideoSlot(),
    }),
    setStyle,
    loadFileIntoSlot,
    refreshAllUI,
    drawCollage,
  };

  async function hydrateEditDoc(editDoc) {
    const proj = (editDoc && editDoc.project && typeof editDoc.project === 'object') ? editDoc.project : null;

    // 1. Style & Layout ID
    let targetStyle = proj && proj.styleId ? proj.styleId : 'klasik_kpp';
    let targetLayout = proj && proj.layoutId ? proj.layoutId : '';
    const rawPreset = String(editDoc.layout_preset || '');
    if (rawPreset.includes(':')) {
      const parts = rawPreset.split(':');
      if (!proj) targetStyle = parts[0] || targetStyle;
      if (!targetLayout) targetLayout = parts[1] || '';
    } else if (!targetLayout && rawPreset) {
      targetLayout = rawPreset;
    }

    // 2. Orientation & Canvas Preset
    const targetOrient = (proj && proj.orientation) || editDoc.orientation || 'vertical';
    const targetCount = Layouts.clampSlotCount((proj && proj.slotCount) || editDoc.slot_count || 6);

    state.styleId = targetStyle === 'editorial_modern' ? 'editorial_modern' : 'klasik_kpp';
    els.styleBtns.forEach((b) => {
      b.dataset.active = b.dataset.styleId === state.styleId ? 'true' : 'false';
    });

    state.orientation = ['vertical', 'horizontal', 'square'].includes(targetOrient) ? targetOrient : 'vertical';
    els.orientTabs.forEach((t) => {
      const active = t.dataset.orient === state.orientation;
      t.dataset.active = active ? 'true' : 'false';
      t.className = active
        ? 'dok-orient-tab h-8 rounded-lg text-xs font-bold bg-white dark:bg-slate-900 text-orange-700 dark:text-orange-400 shadow-xs transition cursor-pointer'
        : 'dok-orient-tab h-8 rounded-lg text-xs font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer';
    });

    let matchedCanvas = null;
    if (proj && proj.canvasPresetId) {
      matchedCanvas = Layouts.CANVAS_PRESETS.find((p) => p.id === proj.canvasPresetId) || null;
    }
    if (!matchedCanvas && editDoc.aspect_ratio) {
      matchedCanvas = Layouts.CANVAS_PRESETS.find((p) => p.ratio === editDoc.aspect_ratio && p.orientation === state.orientation) || null;
    }
    if (!matchedCanvas) {
      matchedCanvas = Layouts.CANVAS_PRESETS.find((p) => p.orientation === state.orientation) || Layouts.CANVAS_PRESETS[0];
    }
    state.canvasPresetId = matchedCanvas.id;

    state.slotCount = targetCount;
    ensureSlotsLength(targetCount);
    els.slotCountBtns.forEach((b) => {
      b.dataset.active = Number(b.dataset.slotCount) === targetCount ? 'true' : 'false';
    });

    const validLayouts = Layouts.getLayoutsForCount(targetCount);
    state.layoutId = validLayouts.some((l) => l.id === targetLayout)
      ? targetLayout
      : Layouts.getDefaultLayoutId(targetCount, state.orientation);

    if (proj && proj.qualityId && els.selectExportQuality) {
      state.qualityId = proj.qualityId;
      els.selectExportQuality.value = proj.qualityId;
    }
    if (proj && proj.imageFormat && els.selectImageFormat) {
      els.selectImageFormat.value = proj.imageFormat;
    }
    if (proj && proj.videoDuration && els.selectVideoDuration) {
      els.selectVideoDuration.value = proj.videoDuration;
    }

    syncCanvasDimensions();
    renderCanvasPresetButtons();
    renderLayoutVariants();
    refreshAllUI();

    // 3. Muat kembali seluruh media per-slot beserta zoom/pan yang tersimpan
    if (proj && Array.isArray(proj.slots) && proj.slots.length > 0) {
      const tasks = [];
      proj.slots.slice(0, state.slotCount).forEach((slotMeta, idx) => {
        if (slotMeta && slotMeta.url) {
          tasks.push(loadRemoteSlotIntoSlot(slotMeta, idx));
        }
      });
      if (tasks.length > 0) {
        await Promise.all(tasks);
        refreshAllUI();
        notify(`Arsip #${editDoc.id} (${tasks.length} media) siap diedit.`, 'info');
      }
    }
  }

  // --- Initial Boot ---
  ensureSlotsLength(state.slotCount);
  syncOperasiSelectionUI(false);
  preloadLogos();
  if (cfg.editDoc && cfg.editDoc.id) {
    hydrateEditDoc(cfg.editDoc);
  } else {
    setOrientation('vertical');
    refreshAllUI();
  }
})();
