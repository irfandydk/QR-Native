/**
 * QR-SAR - Studio Dokumentasi Kolase Operasi SAR & Umum (Foto & Video)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Fitur:
 * - 2 Gaya Desain: Klasik Resmi KPP & Editorial Modern
 * - Kontrol slot kontekstual anti-overflow (mobile & desktop): pan langsung, zoom, tap-to-swap, mute, Trim maks 3 menit
 * - Auto-draft IndexedDB tahan refresh halaman (memulihkan seluruh berkas File/Blob, serverPath, trim, & job render aktif)
 * - Unggah bertahap (chunked 4 MB) tanpa batas ukuran per sumber, batas hasil akhir 150 MB
 * - Render video server-side FFmpeg (MP4 H.264/AAC) dengan fallback client teroptimasi & 1 loading bar terpadu (0% -> 100%)
 */
(function () {
  'use strict';

  const cfg = window.__DOK_STUDIO_CONFIG__ || null;
  const Layouts = window.QrSarCollageLayouts || null;
  const DraftStore = window.QrSarDokDraftStore || null;
  const ChunkUploader = window.QrSarDokChunkUploader || null;
  if (!cfg || !Layouts) return;

  const canvas = document.getElementById('collagePreviewCanvas');
  const overlayEl = document.getElementById('collageInteractiveOverlay');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');

  const CAPS = cfg.capabilities || {};
  const MAX_FINAL_BYTES = Number(CAPS.max_final_bytes) || (150 * 1024 * 1024); // 150 MB
  const MAX_VIDEO_SECONDS = Number(CAPS.max_video_duration_seconds) || 180;    // 3 menit
  const CHUNK_BYTES = Number(CAPS.chunk_size_bytes) || (4 * 1024 * 1024);      // 4 MB

  const DRAFT_SCOPE_KEY = (cfg.editDoc && cfg.editDoc.id)
    ? `edit:${cfg.editDoc.id}`
    : `create:ops:${Number(cfg.idOperasi) || 0}`;

  // --- Application State ---
  const state = {
    idOperasi: Number(cfg.idOperasi) > 0 ? Number(cfg.idOperasi) : 0,
    namaOperasi: String(cfg.namaOperasi || ''),
    styleId: 'klasik_kpp',
    orientation: 'vertical',
    canvasPresetId: 'portrait_2_3',
    qualityId: 'qhd',
    userPickedQuality: false,
    slotCount: 6,
    layoutId: 'v_6_2x3',
    selectedSlotIndex: 0,
    swapSourceIndex: null,
    bulkMode: false,
    bulkSelected: new Set(),
    slots: [], // { type, el, url, isObjectUrl, file, serverPath, name, zoom, offsetX, offsetY, muted, videoTime, duration, trimStart, trimEnd }
    isPlaying: false,
    isExporting: false,
    rafId: null,
    audioCtx: null,
    logoImages: { logo1: null, logo2: null, logo3: null },
    slotRectsCache: [],
    activeAbortController: null,
    activeServerJobId: null,
    progressHighWater: 0,
    draftHydrating: false,
    draftSaveTimer: null,
    trimSlotIndex: null,
    trimPreviewLoopId: null,
  };

  // --- DOM References ---
  const els = {
    tabBtns: Array.from(document.querySelectorAll('[data-studio-tab]')),
    tabPanels: Array.from(document.querySelectorAll('[data-studio-panel]')),
    selectDokOperasi: document.getElementById('selectDokOperasi'),
    labelInputDokHari: document.getElementById('labelInputDokHari'),
    labelInputDokJudul: document.getElementById('labelInputDokJudul'),
    studioHeaderScopeLabel: document.getElementById('studioHeaderScopeLabel'),
    studioHeaderHariLabel: document.getElementById('studioHeaderHariLabel'),
    studioHeaderTitle: document.getElementById('studioHeaderTitle'),
    draftRestoredBadge: document.getElementById('draftRestoredBadge'),
    styleBtns: Array.from(document.querySelectorAll('[data-style-id]')),
    orientTabs: Array.from(document.querySelectorAll('.dok-orient-tab')),
    canvasPresetGrid: document.getElementById('canvasPresetGrid'),
    activeCanvasDimBadge: document.getElementById('activeCanvasDimBadge'),
    slotCountBtns: Array.from(document.querySelectorAll('[data-slot-count]')),
    layoutVariantGrid: document.getElementById('layoutVariantGrid'),
    filledSlotsBadge: document.getElementById('filledSlotsBadge'),
    filledSlotsSummaryText: document.getElementById('filledSlotsSummaryText'),
    btnClearAllSlots: document.getElementById('btnClearAllSlots'),
    btnQuickAddMedia: document.getElementById('btnQuickAddMedia'),
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
    videoEffectiveDurPill: document.getElementById('videoEffectiveDurPill'),
    exportProgressWrap: document.getElementById('exportProgressWrap'),
    exportProgressLabel: document.getElementById('exportProgressLabel'),
    exportProgressPct: document.getElementById('exportProgressPct'),
    exportProgressBar: document.getElementById('exportProgressBar'),
    btnCancelExport: document.getElementById('btnCancelExport'),
    selectExportQuality: document.getElementById('selectExportQuality'),
    selectImageFormat: document.getElementById('selectImageFormat'),
    wrapSelectImageFormat: document.getElementById('wrapSelectImageFormat'),
    btnTopDownloadImg: document.getElementById('btnTopDownloadImg'),
    btnTopDownloadVid: document.getElementById('btnTopDownloadVid'),
    btnSaveArchive: document.getElementById('btnSaveArchive'),
    // Contextual Selected Slot Bar
    slotActionBar: document.getElementById('slotActionBar'),
    slotBarBadge: document.getElementById('slotBarBadge'),
    slotBarName: document.getElementById('slotBarName'),
    slotBarTrimPill: document.getElementById('slotBarTrimPill'),
    btnSlotReplace: document.getElementById('btnSlotReplace'),
    btnSlotZoomOut: document.getElementById('btnSlotZoomOut'),
    btnSlotZoomFit: document.getElementById('btnSlotZoomFit'),
    btnSlotZoomIn: document.getElementById('btnSlotZoomIn'),
    btnSlotSwap: document.getElementById('btnSlotSwap'),
    btnSlotMute: document.getElementById('btnSlotMute'),
    btnSlotTrim: document.getElementById('btnSlotTrim'),
    btnSlotDelete: document.getElementById('btnSlotDelete'),
    swapModeBar: document.getElementById('swapModeBar'),
    swapModeLabel: document.getElementById('swapModeLabel'),
    btnCancelSwap: document.getElementById('btnCancelSwap'),
    // Video Trim Modal
    videoTrimModal: document.getElementById('videoTrimModal'),
    trimModalTitle: document.getElementById('trimModalTitle'),
    btnCloseTrimModal: document.getElementById('btnCloseTrimModal'),
    trimPreviewVideo: document.getElementById('trimPreviewVideo'),
    trimSelectionTrack: document.getElementById('trimSelectionTrack'),
    trimStartLabel: document.getElementById('trimStartLabel'),
    trimEndLabel: document.getElementById('trimEndLabel'),
    trimStartRange: document.getElementById('trimStartRange'),
    trimEndRange: document.getElementById('trimEndRange'),
    btnTrimSetStartHere: document.getElementById('btnTrimSetStartHere'),
    btnTrimSetEndHere: document.getElementById('btnTrimSetEndHere'),
    btnTrimPlaySelection: document.getElementById('btnTrimPlaySelection'),
    btnTrimReset: document.getElementById('btnTrimReset'),
    trimDurationBadge: document.getElementById('trimDurationBadge'),
    btnCancelTrim: document.getElementById('btnCancelTrim'),
    btnApplyTrim: document.getElementById('btnApplyTrim'),
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

  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return (meta ? meta.getAttribute('content') : '') || window.CSRF_TOKEN || '';
  }

  function formatTimeMMSS(sec, withDec) {
    const v = Math.max(0, Number(sec) || 0);
    const mins = Math.floor(v / 60);
    const rem = v - mins * 60;
    const whole = Math.floor(rem);
    const base = String(mins).padStart(2, '0') + ':' + String(whole).padStart(2, '0');
    if (withDec) {
      const dec = Math.floor((rem - whole) * 10);
      return `${base}.${dec}`;
    }
    return base;
  }

  function getEffectiveClipRange(slot) {
    if (!slot || slot.type !== 'video') {
      return { start: 0, end: 0, dur: 0, sourceDur: 0 };
    }
    const srcDur = Number.isFinite(slot.duration) && slot.duration > 0 ? slot.duration : 10;
    const start = Math.max(0, Math.min(Math.max(0, srcDur - 0.1), Number(slot.trimStart) || 0));
    let end = (slot.trimEnd !== null && slot.trimEnd !== undefined && Number.isFinite(Number(slot.trimEnd)))
      ? Number(slot.trimEnd)
      : Math.min(srcDur, start + MAX_VIDEO_SECONDS);
    if (end <= start + 0.1) {
      end = Math.min(srcDur, start + MAX_VIDEO_SECONDS);
    }
    if ((end - start) > MAX_VIDEO_SECONDS) {
      end = Math.min(srcDur, start + MAX_VIDEO_SECONDS);
    }
    end = Math.min(srcDur, end);
    return {
      start: Math.round(start * 100) / 100,
      end: Math.round(end * 100) / 100,
      dur: Math.max(0.2, Math.round((end - start) * 100) / 100),
      sourceDur: srcDur,
    };
  }

  function setActiveStudioTab(tabName) {
    els.tabBtns.forEach((b) => {
      const active = b.dataset.studioTab === tabName;
      b.dataset.active = active ? 'true' : 'false';
      b.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    els.tabPanels.forEach((p) => {
      p.classList.toggle('hidden', p.dataset.studioPanel !== tabName);
    });
  }

  function ensureSlotsLength(count) {
    while (state.slots.length < count) {
      state.slots.push(null);
    }
    if (state.selectedSlotIndex >= count) {
      state.selectedSlotIndex = count - 1;
    }
    if (state.swapSourceIndex !== null && state.swapSourceIndex >= count) {
      state.swapSourceIndex = null;
    }
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
      els.labelInputDokHari.textContent = general ? 'Kategori' : 'Hari';
    }
    if (els.inputDokHari) {
      els.inputDokHari.placeholder = general ? 'KEGIATAN' : 'H1';
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
        ? 'Studio Dokumentasi KPP Kendari'
        : (state.namaOperasi || (opt ? opt.nama : `Operasi SAR #${state.idOperasi}`));
    }
  }

  // --- IndexedDB Auto-Draft Persistence (Tahan Refresh Halaman) ---
  function buildDraftPayload(activeJobOverride) {
    const slotsSnap = activeSlotsSlice().map((slot) => {
      if (!slot) return null;
      return {
        type: slot.type === 'video' ? 'video' : 'image',
        name: slot.name || '',
        file: slot.file || null,
        serverPath: slot.serverPath || null,
        remoteUrl: (!slot.isObjectUrl && slot.url) ? slot.url : null,
        zoom: Number(slot.zoom) || 1,
        offsetX: Number(slot.offsetX) || 0,
        offsetY: Number(slot.offsetY) || 0,
        muted: slot.muted !== false,
        videoTime: Number(slot.videoTime) || 0,
        duration: Number(slot.duration) || 0,
        trimStart: Number(slot.trimStart) || 0,
        trimEnd: (slot.trimEnd !== null && slot.trimEnd !== undefined) ? Number(slot.trimEnd) : null,
      };
    });

    return {
      idOperasi: state.idOperasi,
      namaOperasi: state.namaOperasi,
      styleId: state.styleId,
      orientation: state.orientation,
      canvasPresetId: state.canvasPresetId,
      qualityId: state.qualityId,
      userPickedQuality: state.userPickedQuality,
      slotCount: state.slotCount,
      layoutId: state.layoutId,
      imageFormat: (els.selectImageFormat && els.selectImageFormat.value) || 'image/jpeg',
      videoDuration: (els.selectVideoDuration && els.selectVideoDuration.value) || 'auto',
      form: {
        hari: els.inputDokHari ? els.inputDokHari.value : '',
        tanggal: els.inputDokTanggal ? els.inputDokTanggal.value : '',
        judul: els.inputDokJudul ? els.inputDokJudul.value : '',
        logo1: !(els.chkShowLogo1 && !els.chkShowLogo1.checked),
        logo2: !(els.chkShowLogo2 && !els.chkShowLogo2.checked),
        logo3: !(els.chkShowLogo3 && !els.chkShowLogo3.checked),
        headerNama: els.inputHeaderNama ? els.inputHeaderNama.value : '',
        headerAlamat: els.inputHeaderAlamat ? els.inputHeaderAlamat.value : '',
        headerTelp: els.inputHeaderTelp ? els.inputHeaderTelp.value : '',
        headerWa: els.inputHeaderWa ? els.inputHeaderWa.value : '',
        headerEmail: els.inputHeaderEmail ? els.inputHeaderEmail.value : '',
        footerWeb: els.inputFooterWeb ? els.inputFooterWeb.value : '',
        footerIg: els.inputFooterIg ? els.inputFooterIg.value : '',
        footerFb: els.inputFooterFb ? els.inputFooterFb.value : '',
        footerX: els.inputFooterX ? els.inputFooterX.value : '',
      },
      slots: slotsSnap,
      activeJob: activeJobOverride !== undefined
        ? activeJobOverride
        : (state.activeServerJobId ? { jobId: state.activeServerJobId, mode: 'archive' } : null),
    };
  }

  function scheduleDraftSave(immediate) {
    if (!DraftStore || state.draftHydrating) return;
    if (state.draftSaveTimer) {
      clearTimeout(state.draftSaveTimer);
      state.draftSaveTimer = null;
    }
    const persist = () => {
      // Hanya simpan bila ada media terisi atau teks sudah diubah dari kosong
      const hasMedia = filledSlotCount() > 0;
      const hasCustomTitle = els.inputDokJudul && els.inputDokJudul.value.trim() !== '' && els.inputDokJudul.value.trim() !== cfg.judulDefault;
      if (!hasMedia && !hasCustomTitle && !state.activeServerJobId) {
        return;
      }
      DraftStore.save(DRAFT_SCOPE_KEY, buildDraftPayload()).catch(() => {});
    };
    if (immediate) {
      persist();
    } else {
      state.draftSaveTimer = setTimeout(persist, 450);
    }
  }

  async function clearDraftStorage() {
    if (els.draftRestoredBadge) els.draftRestoredBadge.classList.add('hidden');
    if (DraftStore) {
      await DraftStore.clear(DRAFT_SCOPE_KEY).catch(() => {});
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
        drawCollage(false, true);
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
      btn.className = 'dok-chip px-2.5 py-1.5 rounded-lg text-left cursor-pointer';
      btn.dataset.active = p.id === state.canvasPresetId ? 'true' : 'false';
      btn.innerHTML =
        '<span class="block text-[11px] font-bold leading-tight">' + escapeHtml(p.ratio) + '</span>' +
        '<span class="block text-[10px] opacity-80 truncate">' + escapeHtml(p.label.replace(p.ratio + ' ', '')) + '</span>';
      btn.addEventListener('click', () => {
        state.canvasPresetId = p.id;
        syncCanvasDimensions();
        renderCanvasPresetButtons();
        drawCollage(false);
        scheduleDraftSave();
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

      const svgW = state.orientation === 'horizontal' ? 38 : 26;
      const svgH = state.orientation === 'horizontal' ? 24 : 34;
      const rects = Layouts.computeSlotRects(layout, { x: 2, y: 2, width: svgW - 4, height: svgH - 4 }, 2);
      const rectsSvg = rects
        .map((r) => `<rect x="${r.x.toFixed(1)}" y="${r.y.toFixed(1)}" width="${r.w.toFixed(1)}" height="${r.h.toFixed(1)}" rx="1.6" fill="currentColor" opacity="0.78" />`)
        .join('');

      btn.innerHTML =
        `<svg width="${svgW}" height="${svgH}" viewBox="0 0 ${svgW} ${svgH}" class="shrink-0 text-orange-600 dark:text-orange-400" aria-hidden="true">${rectsSvg}</svg>` +
        `<span class="text-[11px] font-semibold text-slate-700 dark:text-slate-200 leading-tight">${escapeHtml(layout.label)}</span>`;

      btn.addEventListener('click', () => {
        state.layoutId = layout.id;
        renderLayoutVariants();
        drawCollage(false);
        scheduleDraftSave();
      });
      els.layoutVariantGrid.appendChild(btn);
    });
  }

  function resolveVideoExportSeconds() {
    const sel = els.selectVideoDuration ? els.selectVideoDuration.value : 'auto';
    if (sel !== 'auto') {
      return Math.max(1, Math.min(MAX_VIDEO_SECONDS, Number(sel) || 30));
    }
    let maxDur = 0;
    activeSlotsSlice().forEach((s) => {
      if (s && s.type === 'video') {
        const rng = getEffectiveClipRange(s);
        maxDur = Math.max(maxDur, rng.dur);
      }
    });
    return Math.max(3, Math.min(MAX_VIDEO_SECONDS, Math.round((maxDur || 10) * 10) / 10));
  }

  function syncSelectedSlotActionBar() {
    if (!els.slotActionBar) return;
    const idx = state.selectedSlotIndex;
    const slot = (idx >= 0 && idx < state.slotCount) ? state.slots[idx] : null;
    if (!slot || state.bulkMode) {
      els.slotActionBar.classList.add('hidden');
    } else {
      els.slotActionBar.classList.remove('hidden');
      if (els.slotBarBadge) els.slotBarBadge.textContent = `#${idx + 1}`;
      if (els.slotBarName) els.slotBarName.textContent = slot.name || `Slot ${idx + 1}`;

      const isVid = slot.type === 'video';
      if (els.btnSlotMute) {
        els.btnSlotMute.classList.toggle('hidden', !isVid);
        els.btnSlotMute.textContent = slot.muted ? 'Bisu' : 'Suara';
      }
      if (els.btnSlotTrim) {
        els.btnSlotTrim.classList.toggle('hidden', !isVid);
      }
      if (els.slotBarTrimPill) {
        if (isVid) {
          const rng = getEffectiveClipRange(slot);
          els.slotBarTrimPill.textContent = `${formatTimeMMSS(rng.start)}-${formatTimeMMSS(rng.end)}`;
          els.slotBarTrimPill.classList.remove('hidden');
        } else {
          els.slotBarTrimPill.classList.add('hidden');
        }
      }
      if (els.btnSlotSwap) {
        const isSwapSrc = state.swapSourceIndex === idx;
        els.btnSlotSwap.className = isSwapSrc
          ? 'inline-flex items-center gap-1 h-7 px-2 rounded-lg bg-sky-600 text-white text-[11px] font-bold cursor-pointer'
          : 'inline-flex items-center gap-1 h-7 px-2 rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-sky-50 dark:hover:bg-sky-950/50 text-slate-700 dark:text-slate-200 text-[11px] font-semibold cursor-pointer';
      }
    }

    if (els.swapModeBar) {
      const swapping = state.swapSourceIndex !== null;
      els.swapModeBar.classList.toggle('hidden', !swapping);
      if (swapping && els.swapModeLabel) {
        els.swapModeLabel.textContent = `Ketuk slot tujuan untuk ditukar dengan Slot #${state.swapSourceIndex + 1}`;
      }
    }
  }

  function syncModeAndCounters() {
    const filled = filledSlotCount();
    const hasVid = hasVideoSlot();

    // Default kualitas cerdas: Full HD (1080p) otomatis saat video pertama ditambahkan (jika belum dipilih manual)
    if (hasVid && !state.userPickedQuality && state.qualityId !== 'fhd') {
      state.qualityId = 'fhd';
      if (els.selectExportQuality) els.selectExportQuality.value = 'fhd';
    } else if (!hasVid && !state.userPickedQuality && state.qualityId !== 'qhd') {
      state.qualityId = 'qhd';
      if (els.selectExportQuality) els.selectExportQuality.value = 'qhd';
    }

    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const expDim = Layouts.resolveExportDimensions(preset, state.qualityId);
    const qShort = state.qualityId === 'uhd_4k' ? '4K' : (state.qualityId === 'qhd' ? '2K' : 'FHD');

    if (els.filledSlotsBadge) {
      els.filledSlotsBadge.textContent = `${filled}/${state.slotCount}`;
    }
    if (els.filledSlotsSummaryText) {
      els.filledSlotsSummaryText.textContent = `${filled} / ${state.slotCount} slot terisi`;
    }
    if (els.previewMetaPill) {
      els.previewMetaPill.textContent = `${preset.ratio} \u2022 ${state.slotCount} Slot \u2022 ${qShort}`;
    }
    if (els.activeCanvasDimBadge) {
      els.activeCanvasDimBadge.textContent = `${expDim.width} \u00D7 ${expDim.height} px`;
    }
    if (els.previewModeBadge) {
      els.previewModeBadge.textContent = hasVid ? 'VIDEO' : 'FOTO';
      els.previewModeBadge.className = hasVid
        ? 'px-2 py-0.5 rounded-md bg-orange-500/15 text-orange-700 dark:text-orange-400 font-bold text-[10px] uppercase tracking-wider'
        : 'px-2 py-0.5 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold text-[10px] uppercase tracking-wider';
    }
    if (els.wrapSelectImageFormat) {
      els.wrapSelectImageFormat.classList.toggle('opacity-50', hasVid);
    }
    if (els.btnToggleBulkMode) {
      els.btnToggleBulkMode.classList.toggle('hidden', filled === 0);
      els.btnToggleBulkMode.textContent = state.bulkMode ? 'Selesai' : 'Pilih Banyak';
    }
    if (els.previewBulkBar) {
      const showBulk = filled > 0 && (state.bulkMode || state.bulkSelected.size > 0);
      els.previewBulkBar.classList.toggle('hidden', !showBulk);
      if (els.bulkSelectedCountLabel) {
        els.bulkSelectedCountLabel.textContent = `${state.bulkSelected.size} / ${filled} dipilih`;
      }
      if (els.btnBulkDeleteSelected) {
        els.btnBulkDeleteSelected.disabled = state.bulkSelected.size === 0;
        els.btnBulkDeleteSelected.classList.toggle('opacity-50', state.bulkSelected.size === 0);
      }
    }
    [els.videoPlaybackBar, els.btnTopDownloadVid].forEach((el) => {
      if (!el) return;
      el.classList.toggle('hidden', !hasVid);
    });
    if (hasVid && els.videoEffectiveDurPill) {
      const effSec = resolveVideoExportSeconds();
      els.videoEffectiveDurPill.textContent = `${formatTimeMMSS(effSec)} / ${formatTimeMMSS(MAX_VIDEO_SECONDS)}`;
    }
    if (!hasVid && state.isPlaying) {
      stopPlayback();
    }
    syncSelectedSlotActionBar();
  }

  function syncCanvasDimensions() {
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    canvas.width = preset.width;
    canvas.height = preset.height;
    syncModeAndCounters();
  }

  // --- Minimal Anti-Overflow Interactive Overlay on Live Preview ---
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
      const isSwapSource = state.swapSourceIndex === idx;
      const isSwapReady = state.swapSourceIndex !== null && state.swapSourceIndex !== idx;

      const box = document.createElement('div');
      box.className = 'dok-ov-slot';
      box.dataset.slotIndex = String(idx);
      box.dataset.filled = isFilled ? 'true' : 'false';
      box.dataset.selected = isSelected ? 'true' : 'false';
      box.dataset.bulkMode = state.bulkMode ? 'true' : 'false';
      box.dataset.bulkChecked = isChecked ? 'true' : 'false';
      box.dataset.swapSource = isSwapSource ? 'true' : 'false';
      box.dataset.swapReady = isSwapReady ? 'true' : 'false';
      box.style.left = ((rect.x / W) * 100).toFixed(4) + '%';
      box.style.top = ((rect.y / H) * 100).toFixed(4) + '%';
      box.style.width = ((rect.w / W) * 100).toFixed(4) + '%';
      box.style.height = ((rect.h / H) * 100).toFixed(4) + '%';

      // Drag-and-drop file or reorder (desktop)
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
          return;
        }
        dragSourceIndex = null;
        const droppedFiles = e.dataTransfer && e.dataTransfer.files;
        if (droppedFiles && droppedFiles.length === 1) {
          await loadFileIntoSlot(droppedFiles[0], idx);
          state.selectedSlotIndex = idx;
          refreshAllUI();
          scheduleDraftSave();
        } else if (droppedFiles && droppedFiles.length > 1) {
          handleBulkFiles(droppedFiles);
        }
      });

      if (!isFilled) {
        box.title = `Slot #${idx + 1}`;
        box.addEventListener('click', () => {
          if (state.swapSourceIndex !== null) {
            const fromIdx = state.swapSourceIndex;
            state.swapSourceIndex = null;
            swapSlots(fromIdx, idx);
            return;
          }
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

      // Filled Slot: Clean Pan-by-Drag + Tap to Select/Swap (ZERO overflowing button bars inside slot!)
      let panActive = false;
      let startX = 0;
      let startY = 0;
      let origOffX = 0;
      let origOffY = 0;
      let moved = false;

      box.addEventListener('pointerdown', (e) => {
        if (e.target.closest('input, label, [data-reorder-handle]')) return;
        if (e.button !== 0) return;
        if (state.swapSourceIndex !== null) return;
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
        if (Math.abs(dx) > 3 || Math.abs(dy) > 3) moved = true;
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
        if (moved) {
          scheduleDraftSave();
        }
        syncSelectedSlotActionBar();
        syncInteractiveOverlay();
      };
      box.addEventListener('pointerup', endPan);
      box.addEventListener('pointercancel', endPan);

      box.addEventListener('click', (e) => {
        if (e.target.closest('input, label')) return;
        if (state.swapSourceIndex !== null) {
          const fromIdx = state.swapSourceIndex;
          state.swapSourceIndex = null;
          if (fromIdx !== idx) {
            swapSlots(fromIdx, idx);
          } else {
            syncSelectedSlotActionBar();
            syncInteractiveOverlay();
          }
        }
      });

      // Minimal corner indicator inside slot (strictly small & clipped inside overflow-hidden slot)
      const topBadgeRow = document.createElement('div');
      topBadgeRow.className = 'absolute top-1 left-1 right-1 flex items-center justify-between gap-1 pointer-events-none';

      if (state.bulkMode) {
        const chkLabel = document.createElement('label');
        chkLabel.className = 'dok-ov-badge inline-flex items-center justify-center w-5 h-5 rounded cursor-pointer pointer-events-auto';
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
        topBadgeRow.appendChild(chkLabel);
      } else {
        const grip = document.createElement('span');
        grip.setAttribute('data-reorder-handle', 'true');
        grip.draggable = true;
        grip.className = 'dok-ov-badge inline-flex items-center px-1.5 py-0.5 rounded text-[9.5px] font-mono font-bold cursor-move pointer-events-auto select-none';
        grip.textContent = '#' + (idx + 1);
        grip.addEventListener('dragstart', (ev) => {
          dragSourceIndex = idx;
          ev.dataTransfer.effectAllowed = 'move';
          try { ev.dataTransfer.setData('text/plain', String(idx)); } catch (err) {}
        });
        grip.addEventListener('dragend', () => {
          dragSourceIndex = null;
          overlayEl.querySelectorAll('.is-drop-target').forEach((n) => n.classList.remove('is-drop-target'));
        });
        topBadgeRow.appendChild(grip);
      }

      if (item.type === 'video') {
        const vTag = document.createElement('span');
        vTag.className = 'dok-ov-badge inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-mono font-bold text-orange-300';
        vTag.textContent = 'VID';
        topBadgeRow.appendChild(vTag);
      }

      box.appendChild(topBadgeRow);
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

  // --- Media Loading (Images & Videos: Local File, Draft Blob, & Saved Server Asset) ---
  function loadFileIntoSlot(file, index, presetMeta) {
    if (!file) return Promise.resolve();
    const fname = (presetMeta && presetMeta.name) || file.name || `media_${index + 1}`;
    const ftype = file.type || '';
    const isVideo = (presetMeta && presetMeta.type === 'video') || ftype.startsWith('video/') || /\.(mp4|webm|mov|m4v|3gp|mkv)$/i.test(fname);
    const isImage = (presetMeta && presetMeta.type === 'image') || ftype.startsWith('image/') || /\.(jpg|jpeg|png|webp|gif)$/i.test(fname);
    if (!isVideo && !isImage) {
      notify(`Berkas "${fname}" bukan format foto atau video yang didukung.`, 'error');
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
            serverPath: (presetMeta && presetMeta.serverPath) || null,
            name: fname,
            zoom: presetMeta ? Math.max(1, Math.min(3, Number(presetMeta.zoom) || 1)) : 1,
            offsetX: presetMeta ? Math.max(-100, Math.min(100, Number(presetMeta.offsetX) || 0)) : 0,
            offsetY: presetMeta ? Math.max(-100, Math.min(100, Number(presetMeta.offsetY) || 0)) : 0,
          };
          resolve();
        };
        img.onerror = () => {
          URL.revokeObjectURL(objUrl);
          notify(`Gagal membaca gambar "${fname}".`, 'error');
          resolve();
        };
        img.src = objUrl;
      });
    }

    return new Promise((resolve) => {
      const video = document.createElement('video');
      video.crossOrigin = 'anonymous';
      video.preload = 'metadata';
      video.loop = true;
      video.playsInline = true;
      video.src = objUrl;

      const existingVideos = activeSlotsSlice().filter((s, i) => i !== index && s && s.type === 'video');
      const hasAudible = existingVideos.some((s) => !s.muted);
      const initialMuted = (presetMeta && typeof presetMeta.muted === 'boolean') ? presetMeta.muted : hasAudible;
      video.muted = initialMuted;

      let settled = false;
      const finish = () => {
        if (settled) return;
        settled = true;
        const dur = Number.isFinite(video.duration) && video.duration > 0
          ? video.duration
          : ((presetMeta && Number(presetMeta.duration) > 0) ? Number(presetMeta.duration) : 10);
        const initTrimStart = presetMeta ? Math.max(0, Math.min(dur - 0.1, Number(presetMeta.trimStart) || 0)) : 0;
        let initTrimEnd = (presetMeta && presetMeta.trimEnd !== null && presetMeta.trimEnd !== undefined && Number(presetMeta.trimEnd) > initTrimStart)
          ? Math.min(dur, Number(presetMeta.trimEnd))
          : Math.min(dur, initTrimStart + MAX_VIDEO_SECONDS);
        if ((initTrimEnd - initTrimStart) > MAX_VIDEO_SECONDS) {
          initTrimEnd = Math.min(dur, initTrimStart + MAX_VIDEO_SECONDS);
        }
        const startT = Math.min(initTrimEnd, Math.max(initTrimStart, presetMeta && Number(presetMeta.videoTime) ? Number(presetMeta.videoTime) : (initTrimStart + (dur > 1 ? 0.2 : 0))));
        state.slots[index] = {
          type: 'video',
          el: video,
          url: objUrl,
          isObjectUrl: true,
          file: file,
          serverPath: (presetMeta && presetMeta.serverPath) || null,
          name: fname,
          zoom: presetMeta ? Math.max(1, Math.min(3, Number(presetMeta.zoom) || 1)) : 1,
          offsetX: presetMeta ? Math.max(-100, Math.min(100, Number(presetMeta.offsetX) || 0)) : 0,
          offsetY: presetMeta ? Math.max(-100, Math.min(100, Number(presetMeta.offsetY) || 0)) : 0,
          muted: initialMuted,
          videoTime: startT,
          duration: dur,
          trimStart: Math.round(initTrimStart * 100) / 100,
          trimEnd: Math.round(initTrimEnd * 100) / 100,
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
        notify(`Format video "${fname}" tidak dapat diputar di browser ini.`, 'error');
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
            serverPath: slotMeta.path || slotMeta.serverPath || null,
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
      video.preload = 'metadata';
      video.loop = true;
      video.playsInline = true;
      video.src = remoteUrl;
      const isMuted = slotMeta.muted !== false;
      video.muted = isMuted;

      let settled = false;
      const finish = () => {
        if (settled) return;
        settled = true;
        const dur = Number.isFinite(video.duration) && video.duration > 0
          ? video.duration
          : (Number(slotMeta.duration) > 0 ? Number(slotMeta.duration) : 10);
        const tStart = Math.max(0, Math.min(dur - 0.1, Number(slotMeta.trimStart) || 0));
        let tEnd = (slotMeta.trimEnd !== null && slotMeta.trimEnd !== undefined && Number(slotMeta.trimEnd) > tStart)
          ? Math.min(dur, Number(slotMeta.trimEnd))
          : Math.min(dur, tStart + MAX_VIDEO_SECONDS);
        if ((tEnd - tStart) > MAX_VIDEO_SECONDS) {
          tEnd = Math.min(dur, tStart + MAX_VIDEO_SECONDS);
        }
        const startT = Math.min(tEnd, Math.max(tStart, Number(slotMeta.videoTime) || (tStart + (dur > 1 ? 0.2 : 0))));
        state.slots[index] = {
          type: 'video',
          el: video,
          url: remoteUrl,
          isObjectUrl: false,
          file: null,
          serverPath: slotMeta.path || slotMeta.serverPath || null,
          name: slotMeta.name || `Video ${index + 1}`,
          zoom: Math.max(1, Math.min(3, Number(slotMeta.zoom) || 1)),
          offsetX: Math.max(-100, Math.min(100, Number(slotMeta.offsetX) || 0)),
          offsetY: Math.max(-100, Math.min(100, Number(slotMeta.offsetY) || 0)),
          muted: isMuted,
          videoTime: startT,
          duration: dur,
          trimStart: Math.round(tStart * 100) / 100,
          trimEnd: Math.round(tEnd * 100) / 100,
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
      f.type.startsWith('image/') || f.type.startsWith('video/') || /\.(jpg|jpeg|png|webp|gif|mp4|webm|mov|m4v|3gp|mkv)$/i.test(f.name)
    );
    if (!files.length) return;

    const currentFilled = filledSlotCount();
    if (currentFilled === 0 && files.length >= Layouts.MIN_SLOTS && files.length <= Layouts.MAX_SLOTS && files.length !== state.slotCount) {
      setSlotCount(files.length);
    }

    let cursor = 0;
    let firstLoadedIdx = null;
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
      if (firstLoadedIdx === null) firstLoadedIdx = targetIdx;
      cursor++;
      tasks.push(loadFileIntoSlot(file, targetIdx));
    }

    await Promise.all(tasks);
    if (firstLoadedIdx !== null) {
      state.selectedSlotIndex = firstLoadedIdx;
    }
    refreshAllUI();
    scheduleDraftSave(true);

    // Peringatkan halus jika ada video berdurasi > 3 menit bahwa jendela otomatis dipotong 00:00-03:00
    const longVid = activeSlotsSlice().find((s) => s && s.type === 'video' && s.duration > MAX_VIDEO_SECONDS + 0.5);
    if (longVid) {
      notify(`${tasks.length} media dimuat. Video > 3 menit disetel otomatis 00:00–03:00 (gunakan tombol Trim untuk mengubah).`, 'info');
    } else {
      notify(`${tasks.length} media berhasil dimuat.`, 'success');
    }
  }

  function clearSlot(idx) {
    releaseSlotResources(state.slots[idx]);
    state.slots[idx] = null;
    state.bulkSelected.delete(idx);
    if (state.swapSourceIndex === idx) state.swapSourceIndex = null;
    refreshAllUI();
    if (filledSlotCount() === 0) {
      clearDraftStorage();
    } else {
      scheduleDraftSave(true);
    }
  }

  function clearSelectedBulkSlots() {
    if (state.bulkSelected.size === 0) return;
    const count = state.bulkSelected.size;
    Array.from(state.bulkSelected).forEach((idx) => {
      releaseSlotResources(state.slots[idx]);
      state.slots[idx] = null;
    });
    state.bulkSelected.clear();
    if (filledSlotCount() === 0) {
      state.bulkMode = false;
      clearDraftStorage();
    } else {
      scheduleDraftSave(true);
    }
    refreshAllUI();
    notify(`${count} slot media dihapus.`, 'success');
  }

  function clearAllSlots() {
    stopPlayback();
    for (let i = 0; i < state.slots.length; i++) {
      releaseSlotResources(state.slots[i]);
      state.slots[i] = null;
    }
    state.bulkSelected.clear();
    state.bulkMode = false;
    state.swapSourceIndex = null;
    clearDraftStorage();
    refreshAllUI();
  }

  function swapSlots(idxA, idxB) {
    if (idxA < 0 || idxB < 0 || idxA >= state.slotCount || idxB >= state.slotCount || idxA === idxB) return;
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
    scheduleDraftSave(true);
    notify(`Slot #${idxA + 1} dan #${idxB + 1} ditukar.`, 'success');
  }

  function setStyle(styleId) {
    state.styleId = styleId === 'editorial_modern' ? 'editorial_modern' : 'klasik_kpp';
    els.styleBtns.forEach((b) => {
      b.dataset.active = b.dataset.styleId === state.styleId ? 'true' : 'false';
    });
    drawCollage(false);
    scheduleDraftSave();
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
    scheduleDraftSave();
  }

  function setOrientation(orient) {
    state.orientation = orient;
    els.orientTabs.forEach((t) => {
      const active = t.dataset.orient === orient;
      t.dataset.active = active ? 'true' : 'false';
      t.className = active
        ? 'dok-orient-tab h-7 rounded-lg text-[11px] font-bold bg-white dark:bg-slate-900 text-orange-700 dark:text-orange-400 shadow-xs transition cursor-pointer'
        : 'dok-orient-tab h-7 rounded-lg text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer';
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
    scheduleDraftSave();
  }

  // --- Video Trim Modal Logic (Maksimal 3 Menit / 180 Detik) ---
  function updateTrimModalVisuals() {
    if (!els.trimStartRange || !els.trimEndRange) return;
    const maxDur = Math.max(0.5, Number(els.trimStartRange.max) || 10);
    const s = Math.max(0, Number(els.trimStartRange.value) || 0);
    const e = Math.max(s + 0.5, Math.min(maxDur, Number(els.trimEndRange.value) || maxDur));
    const selDur = Math.max(0.5, e - s);

    if (els.trimStartLabel) els.trimStartLabel.textContent = formatTimeMMSS(s, true);
    if (els.trimEndLabel) els.trimEndLabel.textContent = formatTimeMMSS(e, true);
    if (els.trimDurationBadge) {
      els.trimDurationBadge.textContent = `${formatTimeMMSS(selDur)} / ${formatTimeMMSS(MAX_VIDEO_SECONDS)}`;
    }
    if (els.trimSelectionTrack) {
      const leftPct = (s / maxDur) * 100;
      const widthPct = Math.max(1, ((e - s) / maxDur) * 100);
      els.trimSelectionTrack.style.left = `${leftPct.toFixed(2)}%`;
      els.trimSelectionTrack.style.width = `${widthPct.toFixed(2)}%`;
    }
  }

  function openVideoTrimModal(slotIdx) {
    const slot = state.slots[slotIdx];
    if (!slot || slot.type !== 'video' || !els.videoTrimModal || !els.trimPreviewVideo) return;
    stopPlayback();

    state.trimSlotIndex = slotIdx;
    const rng = getEffectiveClipRange(slot);
    const srcDur = Math.max(1, rng.sourceDur);

    if (els.trimModalTitle) {
      els.trimModalTitle.textContent = `Potong Video — Slot #${slotIdx + 1}`;
    }

    els.trimStartRange.min = '0';
    els.trimStartRange.max = String(Math.round(srcDur * 10) / 10);
    els.trimStartRange.value = String(Math.round(rng.start * 10) / 10);

    els.trimEndRange.min = '0.5';
    els.trimEndRange.max = String(Math.round(srcDur * 10) / 10);
    els.trimEndRange.value = String(Math.round(rng.end * 10) / 10);

    els.trimPreviewVideo.src = slot.url;
    els.trimPreviewVideo.muted = false;
    els.trimPreviewVideo.onloadedmetadata = () => {
      try { els.trimPreviewVideo.currentTime = rng.start; } catch (e) {}
    };

    updateTrimModalVisuals();
    els.videoTrimModal.classList.remove('hidden');
    els.videoTrimModal.classList.add('flex');
  }

  function closeVideoTrimModal() {
    if (state.trimPreviewLoopId) {
      clearInterval(state.trimPreviewLoopId);
      state.trimPreviewLoopId = null;
    }
    if (els.trimPreviewVideo) {
      try { els.trimPreviewVideo.pause(); } catch (e) {}
      els.trimPreviewVideo.removeAttribute('src');
      els.trimPreviewVideo.load();
    }
    state.trimSlotIndex = null;
    if (els.videoTrimModal) {
      els.videoTrimModal.classList.add('hidden');
      els.videoTrimModal.classList.remove('flex');
    }
  }

  function applyVideoTrimFromModal() {
    const idx = state.trimSlotIndex;
    const slot = idx !== null ? state.slots[idx] : null;
    if (!slot || slot.type !== 'video') {
      closeVideoTrimModal();
      return;
    }
    const maxDur = Math.max(0.5, slot.duration || Number(els.trimEndRange.max) || 10);
    let s = Math.max(0, Number(els.trimStartRange.value) || 0);
    let e = Math.max(s + 0.5, Math.min(maxDur, Number(els.trimEndRange.value) || maxDur));
    if ((e - s) > MAX_VIDEO_SECONDS) {
      e = Math.min(maxDur, s + MAX_VIDEO_SECONDS);
    }
    slot.trimStart = Math.round(s * 100) / 100;
    slot.trimEnd = Math.round(e * 100) / 100;
    slot.videoTime = slot.trimStart;
    if (slot.el) {
      try { slot.el.currentTime = slot.trimStart; } catch (err) {}
    }
    closeVideoTrimModal();
    refreshAllUI();
    scheduleDraftSave(true);
    notify(`Potongan video Slot #${idx + 1} diterapkan (${formatTimeMMSS(slot.trimStart)}–${formatTimeMMSS(slot.trimEnd)}).`, 'success');
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
      c.arc(cx + size * 0.28, cy - size * 0.28, size * 0.06, 0, Math.PI * 2);
      c.fill();
    } else if (kind === 'fb') {
      c.beginPath();
      c.arc(cx, cy, r, 0, Math.PI * 2);
      c.stroke();
      c.font = `700 ${Math.round(size * 0.72)}px Arial, sans-serif`;
      c.textAlign = 'center';
      c.textBaseline = 'middle';
      c.fillText('f', cx, cy + size * 0.05);
    } else {
      c.lineCap = 'round';
      c.beginPath();
      c.moveTo(cx - r * 0.72, cy - r * 0.72);
      c.lineTo(cx + r * 0.72, cy + r * 0.72);
      c.moveTo(cx + r * 0.72, cy - r * 0.72);
      c.lineTo(cx - r * 0.72, cy + r * 0.72);
      c.stroke();
    }
    c.restore();
  }

  function collectFormTexts() {
    const telpVal = (els.inputHeaderTelp && els.inputHeaderTelp.value.trim()) || '0401 3196 557';
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

  function drawMediaSlots(c, rects, W, H, scaleBase, forExport, frameBorderColor, cutoutVideoSlots) {
    rects.forEach((rect, idx) => {
      const slot = state.slots[idx];
      const isVideoCutout = Boolean(cutoutVideoSlots && slot && slot.type === 'video');

      // Soft elevation shadow (tetap tergambar di luar sudut membulat untuk foto maupun lubang video)
      c.save();
      c.shadowColor = 'rgba(15, 23, 42, 0.16)';
      c.shadowBlur = Math.round(14 * scaleBase);
      c.shadowOffsetX = 0;
      c.shadowOffsetY = Math.round(5 * scaleBase);
      c.fillStyle = '#E2E8F0';
      roundRectPath(c, rect.x, rect.y, rect.w, rect.h, rect.radius);
      c.fill();
      c.restore();

      if (isVideoCutout) {
        // Lubangi presisi berbentuk rounded-rectangle transparan (alpha=0) agar video FFmpeg tampil di bawahnya
        c.save();
        c.globalCompositeOperation = 'destination-out';
        c.fillStyle = '#000000';
        roundRectPath(c, rect.x, rect.y, rect.w, rect.h, rect.radius);
        c.fill();
        c.restore();
      } else {
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
            c.fillText(`Slot ${idx + 1}`, rect.x + rect.w / 2, rect.y + rect.h / 2 - Math.round(10 * scaleBase));
            c.font = `500 ${Math.round(Math.max(12 * scaleBase, Math.min(rect.w, rect.h) * 0.065))}px "Plus Jakarta Sans", Arial, sans-serif`;
            c.fillText('+ Media', rect.x + rect.w / 2, rect.y + rect.h / 2 + Math.round(14 * scaleBase));
          }
        }
        c.restore();
      }

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
  function renderStyleKlasikKpp(c, W, H, forExport, cutoutVideoSlots) {
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
    drawMediaSlots(c, rects, W, H, scaleBase, forExport, 'rgba(15, 23, 42, 0.08)', cutoutVideoSlots);
    c.restore();
    return rects;
  }

  // --- STYLE 2: Editorial Modern ---
  function renderStyleEditorialModern(c, W, H, forExport, cutoutVideoSlots) {
    const isLandscape = W > H;
    const isSquare = W === H;
    const scaleBase = Math.min(W, H) / 1080;
    const txt = collectFormTexts();
    const activeLogos = getActiveLogos();
    const padX = Math.round(W * 0.042);

    c.save();
    c.imageSmoothingEnabled = true;
    c.imageSmoothingQuality = 'high';

    c.fillStyle = '#F8FAFC';
    c.fillRect(0, 0, W, H);

    const topRibbonH = Math.max(10, Math.round(12 * scaleBase));
    const ribbonGrad = c.createLinearGradient(0, 0, W, 0);
    ribbonGrad.addColorStop(0, '#EA580C');
    ribbonGrad.addColorStop(1, '#F97316');
    c.fillStyle = ribbonGrad;
    c.fillRect(0, 0, W, topRibbonH);

    const mastH = Math.round(isLandscape ? H * 0.132 : (isSquare ? H * 0.138 : H * 0.118));
    const mastY = topRibbonH;
    c.fillStyle = '#FFFFFF';
    c.fillRect(0, mastY, W, mastH);

    c.fillStyle = '#E2E8F0';
    c.fillRect(0, mastY + mastH, W, Math.max(1.5, Math.round(2 * scaleBase)));

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
        if (txt.emergencyLine) kopRows.push({ text: txt.emergencyLine, font: `800 ${kopEmSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#C2410C' });
        if (emailLabel) kopRows.push({ text: emailLabel, font: `600 ${kopSubSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#475569' });
      }
    } else {
      if (txt.emergencyLine) kopRows.push({ text: txt.emergencyLine, font: `800 ${kopEmSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#C2410C' });
      if (emailLabel) kopRows.push({ text: emailLabel, font: `600 ${kopSubSize}px "Plus Jakarta Sans", Arial, sans-serif`, color: '#475569' });
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

    const footerH = Math.round(isLandscape ? H * 0.064 : (isSquare ? H * 0.088 : H * 0.072));
    const footerY = H - footerH;
    c.fillStyle = '#0F172A';
    c.fillRect(0, footerY, W, footerH);
    c.fillStyle = '#F97316';
    c.fillRect(0, footerY, W, Math.max(3, Math.round(4 * scaleBase)));

    const usableFooterW = W - padX * 2;
    if (isLandscape) {
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
    drawMediaSlots(c, rects, W, H, scaleBase, forExport, 'rgba(15, 23, 42, 0.12)', cutoutVideoSlots);
    c.restore();
    return rects;
  }

  function renderCollageToContext(targetCtx, targetW, targetH, forExport, cutoutVideoSlots) {
    if (state.styleId === 'editorial_modern') {
      return renderStyleEditorialModern(targetCtx, targetW, targetH, forExport, cutoutVideoSlots);
    }
    return renderStyleKlasikKpp(targetCtx, targetW, targetH, forExport, cutoutVideoSlots);
  }

  function drawCollage(forExport, skipOverlayRebuild) {
    const rects = renderCollageToContext(ctx, canvas.width, canvas.height, !!forExport, false);
    state.slotRectsCache = rects;
    if (!forExport && !skipOverlayRebuild) {
      syncInteractiveOverlay();
    }
  }

  // --- Audio & Trim-Aware Video Preview Loop ---
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
    const actx = ensureAudioContext();
    if (!actx) {
      s.el.muted = !!s.muted;
      return;
    }
    try {
      if (!s._sourceNode) {
        s._sourceNode = actx.createMediaElementSource(s.el);
        s._previewGain = actx.createGain();
        s._sourceNode.connect(s._previewGain);
        s._previewGain.connect(actx.destination);
      }
      s.el.muted = false;
      if (s._previewGain) {
        s._previewGain.gain.setValueAtTime(s.muted ? 0 : 1, actx.currentTime);
      }
    } catch (e) {
      s.el.muted = !!s.muted;
    }
  }

  function startPlayback() {
    if (!hasVideoSlot()) return;
    state.isPlaying = true;
    if (els.labelToggleVideoPlay) els.labelToggleVideoPlay.textContent = 'Jeda';

    ensureAudioContext();

    activeSlotsSlice().forEach((s) => {
      if (s && s.type === 'video' && s.el) {
        const rng = getEffectiveClipRange(s);
        if (s.el.currentTime < rng.start || s.el.currentTime >= rng.end - 0.05) {
          try { s.el.currentTime = rng.start; } catch (e) {}
        }
        attachSlotAudio(s);
        s.el.play().catch(() => {});
      }
    });

    const tick = () => {
      if (!state.isPlaying && !state.isExporting) return;
      activeSlotsSlice().forEach((s) => {
        if (s && s.type === 'video' && s.el) {
          const rng = getEffectiveClipRange(s);
          if (s.el.currentTime >= rng.end) {
            try { s.el.currentTime = rng.start; } catch (e) {}
          }
        }
      });
      drawCollage(false, true);
      state.rafId = window.requestAnimationFrame(tick);
    };
    if (state.rafId) window.cancelAnimationFrame(state.rafId);
    state.rafId = window.requestAnimationFrame(tick);
  }

  function stopPlayback() {
    state.isPlaying = false;
    if (els.labelToggleVideoPlay) els.labelToggleVideoPlay.textContent = 'Putar';
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

  // --- Single Unified Monotonic Progress Bar (0% -> 100%, NEVER resets!) ---
  function setUnifiedProgress(visible, label, pct, resetHighWater) {
    if (!els.exportProgressWrap) return;
    if (resetHighWater) {
      state.progressHighWater = 0;
    }
    els.exportProgressWrap.classList.toggle('hidden', !visible);
    if (!visible) {
      state.progressHighWater = 0;
      if (els.exportProgressBar) els.exportProgressBar.style.width = '0%';
      if (els.exportProgressPct) els.exportProgressPct.textContent = '0%';
      return;
    }
    if (els.exportProgressLabel && label) {
      els.exportProgressLabel.textContent = label;
    }
    const clamped = Math.max(state.progressHighWater, Math.min(100, Math.round(pct || 0)));
    state.progressHighWater = clamped;
    if (els.exportProgressPct) els.exportProgressPct.textContent = clamped + '%';
    if (els.exportProgressBar) els.exportProgressBar.style.width = clamped + '%';
  }

  function setActionButtonsDisabled(disabled) {
    [els.btnSaveArchive, els.btnTopDownloadImg, els.btnTopDownloadVid].forEach((b) => {
      if (b) b.disabled = !!disabled;
    });
  }

  // --- High-Resolution Offscreen Image & Overlay PNG Exporters ---
  function exportImageBlob(mimeType, qualityOverride) {
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const expDim = Layouts.resolveExportDimensions(preset, qualityOverride || state.qualityId);
    const offCanvas = document.createElement('canvas');
    offCanvas.width = expDim.width;
    offCanvas.height = expDim.height;
    const offCtx = offCanvas.getContext('2d');
    const rects = renderCollageToContext(offCtx, expDim.width, expDim.height, true, false);

    return new Promise((resolve, reject) => {
      offCanvas.toBlob((blob) => {
        if (blob) {
          resolve({ blob, width: expDim.width, height: expDim.height, rects });
        } else {
          reject(new Error('Gagal merender gambar kolase.'));
        }
      }, mimeType || 'image/jpeg', expDim.jpegQuality);
    });
  }

  function exportVideoOverlayPng(qualityOverride) {
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const expDim = Layouts.resolveExportDimensions(preset, qualityOverride || state.qualityId);
    // Pastikan dimensi genap untuk kompatibilitas libx264 yuv420p
    const outW = expDim.width & ~1;
    const outH = expDim.height & ~1;
    const offCanvas = document.createElement('canvas');
    offCanvas.width = outW;
    offCanvas.height = outH;
    const offCtx = offCanvas.getContext('2d');
    const rects = renderCollageToContext(offCtx, outW, outH, true, true);

    return new Promise((resolve, reject) => {
      offCanvas.toBlob((blob) => {
        if (blob) {
          resolve({ blob, width: outW, height: outH, rects });
        } else {
          reject(new Error('Gagal membuat frame overlay kolase.'));
        }
      }, 'image/png');
    });
  }

  // --- Prepare Image Source Blob (Optimal tanpa menurunkan ketajaman) ---
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

  // --- Upload All Pending Slot Sources via 4 MB Chunked Upload (Unlimited Source Size!) ---
  async function uploadPendingSourcesChunked(csrfToken, signal, progressStart, progressEnd, loadingLabel) {
    const active = activeSlotsSlice();
    const pending = [];
    active.forEach((slot, idx) => {
      if (slot && !slot.serverPath && slot.file) {
        pending.push({ slot, idx });
      }
    });
    if (!pending.length) {
      setUnifiedProgress(true, loadingLabel, progressEnd);
      return;
    }

    const prepared = [];
    let totalBytes = 0;
    for (let i = 0; i < pending.length; i++) {
      const { slot } = pending[i];
      const payload = slot.type === 'image' ? await prepareImageSourceBlob(slot) : slot.file;
      if (!payload) continue;
      prepared.push({ slot, payload, size: payload.size || 1 });
      totalBytes += (payload.size || 1);
    }

    let uploadedSoFar = 0;
    const span = Math.max(1, progressEnd - progressStart);

    for (let i = 0; i < prepared.length; i++) {
      const item = prepared[i];
      const fallbackName = item.slot.type === 'video' ? 'video_slot.mp4' : 'foto_slot.jpg';
      const res = await ChunkUploader.uploadBlobChunked({
        blob: item.payload,
        fileName: item.slot.name || fallbackName,
        purpose: 'source',
        idOperasi: state.idOperasi,
        chunkSize: CHUNK_BYTES,
        uploadApiBase: cfg.uploadApiBase,
        csrfToken,
        signal,
        onProgress: (loaded) => {
          const frac = (uploadedSoFar + loaded) / Math.max(1, totalBytes);
          setUnifiedProgress(true, loadingLabel, progressStart + frac * span);
        },
      });
      if (!res || !res.path) {
        throw new Error(`Gagal menyimpan sumber media "${item.slot.name || fallbackName}".`);
      }
      item.slot.serverPath = res.path;
      uploadedSoFar += item.size;
      setUnifiedProgress(true, loadingLabel, progressStart + (uploadedSoFar / Math.max(1, totalBytes)) * span);
      // Simpan langsung ke IndexedDB draft sehingga bila halaman di-refresh, slot ini tidak perlu diunggah ulang!
      scheduleDraftSave(true);
    }
  }

  function buildProjectJson() {
    const slotsMeta = activeSlotsSlice().map((slot) => {
      if (!slot || !slot.serverPath) return null;
      const entry = {
        type: slot.type === 'video' ? 'video' : 'image',
        path: slot.serverPath,
        name: slot.name || '',
        zoom: Math.round((Number(slot.zoom) || 1) * 1000) / 1000,
        offsetX: Math.round(Number(slot.offsetX) || 0),
        offsetY: Math.round(Number(slot.offsetY) || 0),
        muted: slot.muted !== false,
        videoTime: Math.round((Number(slot.videoTime) || 0) * 100) / 100,
      };
      if (slot.type === 'video') {
        const rng = getEffectiveClipRange(slot);
        entry.trimStart = rng.start;
        entry.trimEnd = rng.end;
        entry.duration = Math.round(rng.sourceDur * 100) / 100;
      }
      return entry;
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

  // --- Poll Background Server-Side FFmpeg Job (Anti-Cloudflare 524 Timeout) ---
  async function pollServerRenderJob(jobId, signal, progressStart, progressEnd, loadingLabel) {
    const jobsBase = (cfg.jobsApiBase || ((window.BASE_URL || '') + '/api/index.php?path=/dokumentasi/jobs')).replace(/\/+$/, '');
    const span = Math.max(1, progressEnd - progressStart);

    while (true) {
      if (signal && signal.aborted) {
        const err = new Error('Proses dibatalkan oleh pengguna.');
        err.name = 'AbortError';
        throw err;
      }

      const resp = await fetch(`${jobsBase}/${jobId}`, {
        method: 'GET',
        credentials: 'same-origin',
        signal,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await resp.json().catch(() => null);
      if (!resp.ok || !data || !data.success) {
        throw new Error((data && (data.message || data.error)) || 'Gagal memantau status pemrosesan video.');
      }

      const srvPct = Math.max(0, Math.min(100, Number(data.progress) || 0));
      setUnifiedProgress(true, loadingLabel, progressStart + (srvPct / 100) * span);

      if (data.status === 'completed') {
        return data;
      }
      if (data.status === 'failed') {
        throw new Error(data.error || data.message || 'Pemrosesan video kolase gagal di server.');
      }
      if (data.status === 'cancelled') {
        const err = new Error('Proses dibatalkan oleh pengguna.');
        err.name = 'AbortError';
        throw err;
      }

      await new Promise((resolve, reject) => {
        const t = setTimeout(() => {
          if (signal) signal.removeEventListener('abort', onAb);
          resolve();
        }, 1000);
        const onAb = () => {
          clearTimeout(t);
          const err = new Error('Proses dibatalkan oleh pengguna.');
          err.name = 'AbortError';
          reject(err);
        };
        if (signal) signal.addEventListener('abort', onAb, { once: true });
      });
    }
  }

  // --- Server-Side FFmpeg Video Pipeline (Archive or Download) ---
  async function runServerFfmpegPipeline(mode, csrfToken, signal, loadingLabel) {
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const general = isGeneralMode();
    const isEdit = !!(cfg.editDoc && cfg.editDoc.id);
    const hari = (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : cfg.hariLabel);
    const judul = (els.inputDokJudul && els.inputDokJudul.value.trim()) || (general ? 'Dokumentasi Kegiatan KPP Kelas A Kendari' : cfg.judulDefault);
    const tanggal = (els.inputDokTanggal && els.inputDokTanggal.value.trim()) || cfg.tanggalDefault;
    const durationSec = resolveVideoExportSeconds();

    // 1. Upload sumber media yang belum tersimpan di server (4% -> 46%)
    setUnifiedProgress(true, loadingLabel, 3);
    await uploadPendingSourcesChunked(csrfToken, signal, 4, 46, loadingLabel);

    // 2. Render frame PNG berlubang transparan & thumbnail poster (46% -> 50%)
    const overlayRes = await exportVideoOverlayPng(state.qualityId);
    let thumbUploadId = '';
    if (mode === 'archive') {
      const thumbRes = await exportImageBlob('image/jpeg', 'fhd');
      const thumbUp = await ChunkUploader.uploadBlobChunked({
        blob: thumbRes.blob,
        fileName: 'poster.jpg',
        purpose: 'final_thumb',
        idOperasi: state.idOperasi,
        chunkSize: CHUNK_BYTES,
        uploadApiBase: cfg.uploadApiBase,
        csrfToken,
        signal,
      });
      thumbUploadId = thumbUp.upload_id || '';
    }
    setUnifiedProgress(true, loadingLabel, 50);

    // 3. Upload frame overlay PNG (50% -> 54%)
    const ovUp = await ChunkUploader.uploadBlobChunked({
      blob: overlayRes.blob,
      fileName: 'overlay.png',
      purpose: 'overlay_png',
      idOperasi: state.idOperasi,
      chunkSize: CHUNK_BYTES,
      uploadApiBase: cfg.uploadApiBase,
      csrfToken,
      signal,
      onProgress: (loaded, total) => {
        const frac = total > 0 ? loaded / total : 0;
        setUnifiedProgress(true, loadingLabel, 50 + frac * 4);
      },
    });
    setUnifiedProgress(true, loadingLabel, 55);

    // 4. Kumpulkan spesifikasi slot video + trim + koordinat piksel eksak
    const videoSlotsPayload = [];
    activeSlotsSlice().forEach((slot, idx) => {
      if (!slot || slot.type !== 'video' || !slot.serverPath) return;
      const rect = overlayRes.rects[idx];
      if (!rect) return;
      const rng = getEffectiveClipRange(slot);
      videoSlotsPayload.push({
        path: slot.serverPath,
        rect: {
          x: Math.round(rect.x),
          y: Math.round(rect.y),
          w: Math.round(rect.w),
          h: Math.round(rect.h),
        },
        zoom: Number(slot.zoom) || 1,
        offsetX: Number(slot.offsetX) || 0,
        offsetY: Number(slot.offsetY) || 0,
        muted: slot.muted !== false,
        trimStart: rng.start,
        trimEnd: rng.end,
      });
    });

    if (!videoSlotsPayload.length) {
      throw new Error('Sumber video belum siap di server. Silakan coba lagi.');
    }

    const downloadFilename = Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, 'mp4', general);

    // 5. Mulai job FFmpeg background di server
    const renderEndpoint = cfg.renderApiEndpoint || ((window.BASE_URL || '') + '/api/index.php?path=/dokumentasi/render');
    const startResp = await fetch(renderEndpoint, {
      method: 'POST',
      credentials: 'same-origin',
      signal,
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({
        mode,
        edit_doc_id: isEdit ? Number(cfg.editDoc.id) : 0,
        id_operasi: state.idOperasi,
        expected_operasi_id: (!cfg.allowOperasiSelect && state.idOperasi > 0) ? state.idOperasi : 0,
        judul,
        hari_ke: hari,
        tanggal_label: tanggal,
        aspect_ratio: preset.ratio,
        orientation: preset.orientation,
        slot_count: state.slotCount,
        layout_preset: state.styleId + ':' + state.layoutId,
        width_px: overlayRes.width,
        height_px: overlayRes.height,
        duration: durationSec,
        project_json: buildProjectJson(),
        download_filename: downloadFilename,
        overlay_upload_id: ovUp.upload_id,
        thumb_upload_id: thumbUploadId,
        video_slots: videoSlotsPayload,
        csrf_token: csrfToken,
      }),
    });
    const startData = await startResp.json().catch(() => null);
    if (!startResp.ok || !startData || !startData.success || !startData.job_id) {
      throw new Error((startData && (startData.message || startData.error)) || 'Gagal memulai pemrosesan video di server.');
    }

    state.activeServerJobId = startData.job_id;
    if (mode === 'archive' && DraftStore) {
      DraftStore.save(DRAFT_SCOPE_KEY, buildDraftPayload({ jobId: startData.job_id, mode: 'archive' })).catch(() => {});
    }

    // 6. Pantau progres FFmpeg real-time (55% -> 99%)
    const jobResult = await pollServerRenderJob(startData.job_id, signal, 55, 99, loadingLabel);
    state.activeServerJobId = null;
    setUnifiedProgress(true, loadingLabel, 100);
    return {
      jobResult,
      width: overlayRes.width,
      height: overlayRes.height,
      downloadFilename,
    };
  }

  // --- Optimized Client Fallback Video Recorder (ONLY used if server FFmpeg is unavailable) ---
  function pickSupportedVideoMime(hasAudio) {
    if (typeof MediaRecorder === 'undefined') return '';
    const candidates = hasAudio
      ? ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm', 'video/mp4']
      : ['video/mp4;codecs=avc1.42E01E,mp4a.40.2', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm'];
    for (const mime of candidates) {
      if (typeof MediaRecorder.isTypeSupported === 'function' && MediaRecorder.isTypeSupported(mime)) {
        return mime;
      }
    }
    return '';
  }

  async function exportVideoBlobClientFallback(signal, progressStart, progressEnd, loadingLabel) {
    const videoSlots = activeSlotsSlice().filter((s) => s && s.type === 'video' && s.el);
    const hasAudioSlot = videoSlots.some((s) => !s.muted);
    const mime = pickSupportedVideoMime(hasAudioSlot);
    if (!mime || typeof canvas.captureStream !== 'function') {
      throw new Error('Browser tidak mendukung perekaman video fallback dan FFmpeg server tidak aktif.');
    }

    stopPlayback();
    const durationSec = resolveVideoExportSeconds();
    const durationMs = durationSec * 1000;
    const preset = Layouts.getCanvasPreset(state.canvasPresetId);
    const expDim = Layouts.resolveExportDimensions(preset, state.qualityId);
    const outW = expDim.width & ~1;
    const outH = expDim.height & ~1;

    // Pre-render static layer SEKALI saja (tidak menggambar ulang kop/logo/foto statis di setiap frame!)
    const staticCanvas = document.createElement('canvas');
    staticCanvas.width = outW;
    staticCanvas.height = outH;
    const staticCtx = staticCanvas.getContext('2d');
    const rects = renderCollageToContext(staticCtx, outW, outH, true, true);

    const recCanvas = document.createElement('canvas');
    recCanvas.width = outW;
    recCanvas.height = outH;
    const recCtx = recCanvas.getContext('2d');

    await Promise.all(videoSlots.map((s) => new Promise((res) => {
      const rng = getEffectiveClipRange(s);
      const v = s.el;
      const done = () => res();
      v.addEventListener('seeked', done, { once: true });
      try { v.currentTime = rng.start; } catch (e) { res(); }
      setTimeout(res, 350);
    })));

    const videoStream = recCanvas.captureStream(30);
    let audioDest = null;
    const connectedAudioNodes = [];
    let combinedStream = videoStream;

    if (hasAudioSlot && (window.AudioContext || window.webkitAudioContext)) {
      try {
        const actx = ensureAudioContext();
        if (actx) {
          audioDest = actx.createMediaStreamDestination();
          videoSlots.forEach((s) => {
            if (s.muted || !s.el) return;
            attachSlotAudio(s);
            if (s._sourceNode) {
              if (!s._recordGain) {
                s._recordGain = actx.createGain();
                s._sourceNode.connect(s._recordGain);
              }
              s._recordGain.gain.setValueAtTime(1, actx.currentTime);
              s._recordGain.connect(audioDest);
              connectedAudioNodes.push(s._recordGain);
            }
            if (s._previewGain) s._previewGain.gain.setValueAtTime(0, actx.currentTime);
          });
          if (connectedAudioNodes.length > 0 && audioDest.stream.getAudioTracks().length > 0) {
            combinedStream = new MediaStream([...videoStream.getVideoTracks(), ...audioDest.stream.getAudioTracks()]);
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

    const maxArchiveBps = Math.floor((140 * 1024 * 1024 * 8) / Math.max(4, durationSec));
    const targetBps = Math.max(600000, Math.min(expDim.videoBitsPerSecond, maxArchiveBps));

    const chunks = [];
    const recorder = new MediaRecorder(combinedStream, { mimeType: mime, videoBitsPerSecond: targetBps });
    const span = Math.max(1, progressEnd - progressStart);

    return new Promise((resolve, reject) => {
      let tickTimer = null;
      let aborted = false;
      const cleanup = () => {
        if (tickTimer) clearInterval(tickTimer);
        tickTimer = null;
        if (signal) signal.removeEventListener('abort', onAbort);
        videoSlots.forEach((s) => { try { s.el.pause(); } catch (e) {} });
        disconnectAudio();
      };
      const onAbort = () => {
        aborted = true;
        cleanup();
        try { if (recorder.state === 'recording') recorder.stop(); } catch (e) {}
        const err = new Error('Proses dibatalkan oleh pengguna.');
        err.name = 'AbortError';
        reject(err);
      };
      if (signal) signal.addEventListener('abort', onAbort, { once: true });

      recorder.ondataavailable = (ev) => {
        if (ev.data && ev.data.size > 0) chunks.push(ev.data);
      };
      recorder.onerror = (ev) => {
        cleanup();
        reject(new Error(ev.error ? ev.error.message : 'Gagal merekam video kolase.'));
      };
      recorder.onstop = () => {
        cleanup();
        if (aborted) return;
        drawCollage(false, true);
        const cleanMime = mime.split(';')[0];
        const blob = new Blob(chunks, { type: cleanMime });
        const ext = cleanMime.includes('mp4') ? 'mp4' : 'webm';
        resolve({ blob, mime: cleanMime, ext, width: outW, height: outH });
      };

      videoSlots.forEach((s) => s.el.play().catch(() => {}));
      const startedAt = performance.now();

      const drawFrame = () => {
        recCtx.fillStyle = '#0F172A';
        recCtx.fillRect(0, 0, outW, outH);
        activeSlotsSlice().forEach((slot, idx) => {
          if (!slot || slot.type !== 'video' || !slot.el) return;
          const rect = rects[idx];
          if (!rect) return;
          const rng = getEffectiveClipRange(slot);
          if (slot.el.currentTime >= rng.end) {
            try { slot.el.currentTime = rng.start; } catch (e) {}
          }
          const mw = slot.el.videoWidth;
          const mh = slot.el.videoHeight;
          if (mw > 0 && mh > 0) {
            const crop = Layouts.computeCoverCrop(mw, mh, rect.w, rect.h, slot.zoom, slot.offsetX, slot.offsetY);
            recCtx.drawImage(slot.el, crop.sx, crop.sy, crop.sw, crop.sh, rect.x, rect.y, rect.w, rect.h);
          }
        });
        recCtx.drawImage(staticCanvas, 0, 0);

        const elapsed = performance.now() - startedAt;
        const frac = Math.min(1, elapsed / durationMs);
        setUnifiedProgress(true, loadingLabel, progressStart + frac * span);
        if (elapsed >= durationMs) {
          if (recorder.state === 'recording') recorder.stop();
        }
      };

      recorder.start(250);
      drawFrame();
      tickTimer = setInterval(drawFrame, 33);
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
    if (state.isExporting) return;
    if (filledSlotCount() === 0) {
      notify('Tambahkan foto/video terlebih dahulu.', 'warning');
      return;
    }
    const fmt = (els.selectImageFormat && els.selectImageFormat.value) || 'image/jpeg';
    const ext = fmt === 'image/png' ? 'png' : 'jpg';
    const general = isGeneralMode();
    const hari = (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : cfg.hariLabel);
    const judul = (els.inputDokJudul && els.inputDokJudul.value.trim()) || cfg.judulDefault;
    try {
      setUnifiedProgress(true, 'Menyiapkan gambar kolase...', 55, true);
      const res = await exportImageBlob(fmt);
      setUnifiedProgress(false, '', 0, true);
      triggerBlobDownload(res.blob, Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, ext, general));
      notify(`Gambar kolase (${res.width}x${res.height} px) diunduh.`, 'success');
    } catch (err) {
      setUnifiedProgress(false, '', 0, true);
      notify(err.message || 'Gagal mengekspor gambar kolase.', 'error');
    }
  }

  async function handleDownloadVideo() {
    if (!hasVideoSlot() || state.isExporting) return;
    state.isExporting = true;
    state.activeAbortController = new AbortController();
    setActionButtonsDisabled(true);
    const loadingLabel = 'Menyiapkan video kolase...';
    setUnifiedProgress(true, loadingLabel, 2, true);

    try {
      const csrfToken = getCsrfToken();
      if (CAPS.server_render && ChunkUploader) {
        const res = await runServerFfmpegPipeline('download', csrfToken, state.activeAbortController.signal, loadingLabel);
        const dlUrl = res.jobResult && res.jobResult.download_url;
        setUnifiedProgress(false, '', 0, true);
        if (dlUrl) {
          const a = document.createElement('a');
          a.href = dlUrl;
          a.download = res.downloadFilename;
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          notify(`Video kolase MP4 (${res.width}x${res.height} px) siap diunduh.`, 'success');
        }
      } else {
        const general = isGeneralMode();
        const hari = (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : cfg.hariLabel);
        const judul = (els.inputDokJudul && els.inputDokJudul.value.trim()) || cfg.judulDefault;
        const vidRes = await exportVideoBlobClientFallback(state.activeAbortController.signal, 5, 98, loadingLabel);
        setUnifiedProgress(false, '', 0, true);
        triggerBlobDownload(vidRes.blob, Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, vidRes.ext, general));
        notify(`Video kolase (${vidRes.width}x${vidRes.height} px) diunduh.`, 'success');
      }
    } catch (err) {
      setUnifiedProgress(false, '', 0, true);
      if (!err || err.name !== 'AbortError') {
        notify((err && err.message) || 'Gagal mengekspor video kolase.', 'error');
      } else {
        notify('Proses dibatalkan.', 'info');
      }
    } finally {
      state.isExporting = false;
      state.activeAbortController = null;
      state.activeServerJobId = null;
      setActionButtonsDisabled(false);
    }
  }

  async function finalizeSavedSuccess(outW, outH, isEdit) {
    const general = isGeneralMode();
    await clearDraftStorage();
    setUnifiedProgress(false, '', 0, true);

    const successMsg = isEdit
      ? 'Perubahan dokumentasi kolase berhasil disimpan!'
      : (general ? 'Dokumentasi umum berhasil disimpan ke arsip!' : 'Dokumentasi kolase berhasil disimpan ke arsip operasi!');
    notify(successMsg, 'success');

    if (typeof Swal !== 'undefined') {
      const go = await Swal.fire({
        title: isEdit ? 'Perubahan Tersimpan' : 'Dokumentasi Tersimpan',
        text: isEdit
          ? `Arsip dokumentasi #${cfg.editDoc.id} (${outW}x${outH} px) berhasil diperbarui.`
          : `Kolase resolusi ${outW}x${outH} px telah tersimpan rapi di arsip.`,
        icon: 'success',
        showCancelButton: true,
        confirmButtonText: cfg.allowOperasiSelect ? 'Lihat Pusat Dokumentasi' : 'Lihat Arsip Operasi',
        cancelButtonText: isEdit ? 'Lanjut Edit' : 'Buat Kolase Lagi',
      });
      if (go.isConfirmed && cfg.backDetailUrl) {
        window.location.href = cfg.backDetailUrl;
      }
    }
  }

  // --- Unified 1-Step Save Archive Pipeline (0% -> 100%, <= 150 MB Final, Unlimited Sources) ---
  async function handleSaveToArchive() {
    if (state.isExporting) return;
    const filled = filledSlotCount();
    if (filled < Layouts.MIN_SLOTS) {
      notify(`Isi minimal ${Layouts.MIN_SLOTS} slot foto/video sebelum menyimpan (saat ini ${filled} terisi).`, 'warning');
      return;
    }

    stopPlayback();
    state.isExporting = true;
    state.activeAbortController = new AbortController();
    setActionButtonsDisabled(true);

    const isEdit = !!(cfg.editDoc && cfg.editDoc.id);
    const loadingLabel = isEdit ? 'Menyimpan perubahan dokumentasi...' : 'Menyimpan dokumentasi...';
    setUnifiedProgress(true, loadingLabel, 2, true);

    // Pastikan draf tersimpan ke IndexedDB sebelum mulai mengunggah
    scheduleDraftSave(true);

    try {
      const csrfToken = getCsrfToken();
      const signal = state.activeAbortController.signal;
      const hasVid = hasVideoSlot();

      // JALUR 1 (Utama): Kolase Video via Server-Side FFmpeg + Chunked Upload
      if (hasVid && CAPS.server_render && ChunkUploader) {
        const res = await runServerFfmpegPipeline('archive', csrfToken, signal, loadingLabel);
        await finalizeSavedSuccess(res.width, res.height, isEdit);
        return;
      }

      // JALUR 2: Kolase Foto (atau Fallback Video Client bila FFmpeg tidak ada) via Chunked Upload
      const preset = Layouts.getCanvasPreset(state.canvasPresetId);
      const general = isGeneralMode();
      const hari = (els.inputDokHari && els.inputDokHari.value.trim()) || (general ? 'KEGIATAN' : cfg.hariLabel);
      const judul = (els.inputDokJudul && els.inputDokJudul.value.trim()) || (general ? 'Dokumentasi Kegiatan KPP Kelas A Kendari' : cfg.judulDefault);
      const tanggal = (els.inputDokTanggal && els.inputDokTanggal.value.trim()) || cfg.tanggalDefault;

      let mediaBlob;
      let mediaFilename;
      let outW = preset.width;
      let outH = preset.height;
      let thumbBlob = null;

      if (hasVid) {
        await uploadPendingSourcesChunked(csrfToken, signal, 4, 28, loadingLabel);
        const thumbRes = await exportImageBlob('image/jpeg', 'fhd');
        thumbBlob = thumbRes.blob;
        const vidRes = await exportVideoBlobClientFallback(signal, 28, 74, loadingLabel);
        if (vidRes.blob.size > MAX_FINAL_BYTES) {
          throw new Error('Ukuran hasil video melebihi batas maksimal 150 MB. Coba turunkan kualitas atau potong durasi.');
        }
        mediaBlob = vidRes.blob;
        mediaFilename = Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, vidRes.ext, general);
        outW = vidRes.width;
        outH = vidRes.height;
      } else {
        const fmt = (els.selectImageFormat && els.selectImageFormat.value) || 'image/jpeg';
        const ext = fmt === 'image/png' ? 'png' : 'jpg';
        setUnifiedProgress(true, loadingLabel, 8);
        const imgRes = await exportImageBlob(fmt);
        if (imgRes.blob.size > MAX_FINAL_BYTES) {
          throw new Error('Ukuran hasil gambar melebihi batas maksimal 150 MB.');
        }
        mediaBlob = imgRes.blob;
        mediaFilename = Layouts.formatCollageFilename(hari, general ? judul : state.namaOperasi, ext, general);
        outW = imgRes.width;
        outH = imgRes.height;

        await uploadPendingSourcesChunked(csrfToken, signal, 12, 62, loadingLabel);
      }

      // Unggah hasil akhir via chunked upload (lolos batas Cloudflare/PHP hingga 150 MB)
      const upStart = hasVid ? 74 : 62;
      const mediaUp = await ChunkUploader.uploadBlobChunked({
        blob: mediaBlob,
        fileName: mediaFilename,
        purpose: 'final_media',
        idOperasi: state.idOperasi,
        chunkSize: CHUNK_BYTES,
        uploadApiBase: cfg.uploadApiBase,
        csrfToken,
        signal,
        onProgress: (loaded, total) => {
          const frac = total > 0 ? loaded / total : 0;
          setUnifiedProgress(true, loadingLabel, upStart + frac * (94 - upStart));
        },
      });

      let thumbUploadId = '';
      if (thumbBlob) {
        const tUp = await ChunkUploader.uploadBlobChunked({
          blob: thumbBlob,
          fileName: 'poster.jpg',
          purpose: 'final_thumb',
          idOperasi: state.idOperasi,
          chunkSize: CHUNK_BYTES,
          uploadApiBase: cfg.uploadApiBase,
          csrfToken,
          signal,
        });
        thumbUploadId = tUp.upload_id || '';
      }

      setUnifiedProgress(true, loadingLabel, 96);

      const endpoint = isEdit ? `${cfg.apiEndpoint}/${cfg.editDoc.id}` : cfg.apiEndpoint;
      const resp = await fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        signal,
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          media_upload_id: mediaUp.upload_id,
          thumb_upload_id: thumbUploadId,
          id_operasi: state.idOperasi,
          judul,
          hari_ke: hari,
          tanggal_label: tanggal,
          aspect_ratio: preset.ratio,
          orientation: preset.orientation,
          slot_count: state.slotCount,
          layout_preset: state.styleId + ':' + state.layoutId,
          width_px: outW,
          height_px: outH,
          project_json: buildProjectJson(),
          csrf_token: csrfToken,
        }),
      });
      const data = await resp.json().catch(() => null);
      if (!resp.ok || !data || !data.success) {
        throw new Error((data && (data.message || data.error)) || 'Gagal menyimpan dokumentasi ke arsip.');
      }

      setUnifiedProgress(true, loadingLabel, 100);
      await finalizeSavedSuccess(outW, outH, isEdit);
    } catch (err) {
      setUnifiedProgress(false, '', 0, true);
      if (err && err.name === 'AbortError') {
        notify('Penyimpanan dibatalkan. Media Anda tetap aman tersimpan di draf.', 'info');
      } else {
        notify((err && err.message) || 'Gagal menyimpan arsip dokumentasi.', 'error');
      }
    } finally {
      state.isExporting = false;
      state.activeAbortController = null;
      state.activeServerJobId = null;
      setActionButtonsDisabled(false);
    }
  }

  async function handleCancelExport() {
    if (state.activeServerJobId) {
      const jobId = state.activeServerJobId;
      const jobsBase = (cfg.jobsApiBase || ((window.BASE_URL || '') + '/api/index.php?path=/dokumentasi/jobs')).replace(/\/+$/, '');
      fetch(`${jobsBase}/${jobId}`, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: { 'X-CSRF-Token': getCsrfToken(), 'X-Requested-With': 'XMLHttpRequest' },
      }).catch(() => {});
      if (DraftStore) DraftStore.patchActiveJob(DRAFT_SCOPE_KEY, null).catch(() => {});
    }
    if (state.activeAbortController) {
      state.activeAbortController.abort();
    }
  }

  // --- Wire Events ---
  els.tabBtns.forEach((btn) => {
    btn.addEventListener('click', () => setActiveStudioTab(btn.dataset.studioTab));
  });

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
      state.userPickedQuality = true;
      state.qualityId = els.selectExportQuality.value || 'qhd';
      syncModeAndCounters();
      scheduleDraftSave();
    });
  }

  if (els.selectVideoDuration) {
    els.selectVideoDuration.addEventListener('change', () => {
      syncModeAndCounters();
      scheduleDraftSave();
    });
  }

  if (els.selectImageFormat) {
    els.selectImageFormat.addEventListener('change', () => scheduleDraftSave());
  }

  if (els.btnQuickAddMedia && els.bulkMediaInput) {
    els.btnQuickAddMedia.addEventListener('click', () => {
      els.bulkMediaInput.value = '';
      els.bulkMediaInput.click();
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
      scheduleDraftSave(true);
      e.target.value = '';
    });
  }

  if (els.btnClearAllSlots) {
    els.btnClearAllSlots.addEventListener('click', clearAllSlots);
  }

  // Contextual Selected Slot Action Bar Wiring
  if (els.btnSlotReplace) {
    els.btnSlotReplace.addEventListener('click', () => {
      replaceTargetIndex = state.selectedSlotIndex;
      if (els.singleSlotInput) {
        els.singleSlotInput.value = '';
        els.singleSlotInput.click();
      }
    });
  }
  if (els.btnSlotZoomOut) {
    els.btnSlotZoomOut.addEventListener('click', () => {
      const slot = state.slots[state.selectedSlotIndex];
      if (!slot) return;
      slot.zoom = Math.max(1, Math.round((slot.zoom - 0.15) * 100) / 100);
      drawCollage(false, true);
      scheduleDraftSave();
    });
  }
  if (els.btnSlotZoomFit) {
    els.btnSlotZoomFit.addEventListener('click', () => {
      const slot = state.slots[state.selectedSlotIndex];
      if (!slot) return;
      slot.zoom = 1;
      slot.offsetX = 0;
      slot.offsetY = 0;
      drawCollage(false, true);
      scheduleDraftSave();
    });
  }
  if (els.btnSlotZoomIn) {
    els.btnSlotZoomIn.addEventListener('click', () => {
      const slot = state.slots[state.selectedSlotIndex];
      if (!slot) return;
      slot.zoom = Math.min(2.5, Math.round((slot.zoom + 0.15) * 100) / 100);
      drawCollage(false, true);
      scheduleDraftSave();
    });
  }
  if (els.btnSlotSwap) {
    els.btnSlotSwap.addEventListener('click', () => {
      state.swapSourceIndex = (state.swapSourceIndex === state.selectedSlotIndex) ? null : state.selectedSlotIndex;
      syncSelectedSlotActionBar();
      syncInteractiveOverlay();
    });
  }
  if (els.btnCancelSwap) {
    els.btnCancelSwap.addEventListener('click', () => {
      state.swapSourceIndex = null;
      syncSelectedSlotActionBar();
      syncInteractiveOverlay();
    });
  }
  if (els.btnSlotMute) {
    els.btnSlotMute.addEventListener('click', () => {
      const slot = state.slots[state.selectedSlotIndex];
      if (!slot || slot.type !== 'video') return;
      slot.muted = !slot.muted;
      if (slot._previewGain && state.audioCtx) {
        slot._previewGain.gain.setValueAtTime(slot.muted ? 0 : 1, state.audioCtx.currentTime);
      }
      if (slot.el && !slot._previewGain) {
        slot.el.muted = slot.muted;
      }
      syncSelectedSlotActionBar();
      scheduleDraftSave();
    });
  }
  if (els.btnSlotTrim) {
    els.btnSlotTrim.addEventListener('click', () => {
      openVideoTrimModal(state.selectedSlotIndex);
    });
  }
  if (els.btnSlotDelete) {
    els.btnSlotDelete.addEventListener('click', () => {
      clearSlot(state.selectedSlotIndex);
    });
  }

  // Video Trim Modal Wiring
  if (els.trimStartRange) {
    els.trimStartRange.addEventListener('input', () => {
      const maxDur = Number(els.trimStartRange.max) || 10;
      let s = Number(els.trimStartRange.value) || 0;
      let e = Number(els.trimEndRange.value) || maxDur;
      if (s > e - 0.5) {
        e = Math.min(maxDur, s + 0.5);
        if (s > e - 0.5) s = Math.max(0, e - 0.5);
        els.trimStartRange.value = String(Math.round(s * 10) / 10);
        els.trimEndRange.value = String(Math.round(e * 10) / 10);
      }
      if ((e - s) > MAX_VIDEO_SECONDS) {
        e = Math.min(maxDur, s + MAX_VIDEO_SECONDS);
        els.trimEndRange.value = String(Math.round(e * 10) / 10);
      }
      if (els.trimPreviewVideo) {
        try { els.trimPreviewVideo.currentTime = s; } catch (err) {}
      }
      updateTrimModalVisuals();
    });
  }
  if (els.trimEndRange) {
    els.trimEndRange.addEventListener('input', () => {
      const maxDur = Number(els.trimEndRange.max) || 10;
      let s = Number(els.trimStartRange.value) || 0;
      let e = Number(els.trimEndRange.value) || maxDur;
      if (e < s + 0.5) {
        s = Math.max(0, e - 0.5);
        if (e < s + 0.5) e = Math.min(maxDur, s + 0.5);
        els.trimStartRange.value = String(Math.round(s * 10) / 10);
        els.trimEndRange.value = String(Math.round(e * 10) / 10);
      }
      if ((e - s) > MAX_VIDEO_SECONDS) {
        s = Math.max(0, e - MAX_VIDEO_SECONDS);
        els.trimStartRange.value = String(Math.round(s * 10) / 10);
      }
      if (els.trimPreviewVideo) {
        try { els.trimPreviewVideo.currentTime = e; } catch (err) {}
      }
      updateTrimModalVisuals();
    });
  }
  if (els.btnTrimSetStartHere) {
    els.btnTrimSetStartHere.addEventListener('click', () => {
      if (!els.trimPreviewVideo) return;
      els.trimStartRange.value = String(Math.round((els.trimPreviewVideo.currentTime || 0) * 10) / 10);
      els.trimStartRange.dispatchEvent(new Event('input'));
    });
  }
  if (els.btnTrimSetEndHere) {
    els.btnTrimSetEndHere.addEventListener('click', () => {
      if (!els.trimPreviewVideo) return;
      els.trimEndRange.value = String(Math.round((els.trimPreviewVideo.currentTime || 0) * 10) / 10);
      els.trimEndRange.dispatchEvent(new Event('input'));
    });
  }
  if (els.btnTrimPlaySelection) {
    els.btnTrimPlaySelection.addEventListener('click', () => {
      if (!els.trimPreviewVideo) return;
      const s = Number(els.trimStartRange.value) || 0;
      const e = Number(els.trimEndRange.value) || 10;
      if (state.trimPreviewLoopId) clearInterval(state.trimPreviewLoopId);
      try { els.trimPreviewVideo.currentTime = s; } catch (err) {}
      els.trimPreviewVideo.play().catch(() => {});
      state.trimPreviewLoopId = setInterval(() => {
        if (!els.trimPreviewVideo || els.trimPreviewVideo.paused) {
          clearInterval(state.trimPreviewLoopId);
          state.trimPreviewLoopId = null;
          return;
        }
        if (els.trimPreviewVideo.currentTime >= e) {
          els.trimPreviewVideo.pause();
          try { els.trimPreviewVideo.currentTime = s; } catch (err) {}
          clearInterval(state.trimPreviewLoopId);
          state.trimPreviewLoopId = null;
        }
      }, 100);
    });
  }
  if (els.btnTrimReset) {
    els.btnTrimReset.addEventListener('click', () => {
      const maxDur = Number(els.trimEndRange.max) || 10;
      els.trimStartRange.value = '0';
      els.trimEndRange.value = String(Math.round(Math.min(maxDur, MAX_VIDEO_SECONDS) * 10) / 10);
      updateTrimModalVisuals();
      if (els.trimPreviewVideo) {
        try { els.trimPreviewVideo.currentTime = 0; } catch (err) {}
      }
    });
  }
  if (els.btnCloseTrimModal) els.btnCloseTrimModal.addEventListener('click', closeVideoTrimModal);
  if (els.btnCancelTrim) els.btnCancelTrim.addEventListener('click', closeVideoTrimModal);
  if (els.btnApplyTrim) els.btnApplyTrim.addEventListener('click', applyVideoTrimFromModal);
  if (els.videoTrimModal) {
    els.videoTrimModal.addEventListener('click', (ev) => {
      if (ev.target === els.videoTrimModal) closeVideoTrimModal();
    });
  }

  // Bulk Mode Wiring
  if (els.btnToggleBulkMode) {
    els.btnToggleBulkMode.addEventListener('click', () => {
      state.bulkMode = !state.bulkMode;
      state.swapSourceIndex = null;
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

  if (els.selectDokOperasi) {
    els.selectDokOperasi.addEventListener('change', () => {
      const nextId = Number(els.selectDokOperasi.value) || 0;
      state.idOperasi = nextId > 0 ? nextId : 0;
      const opt = state.idOperasi > 0 ? findOperasiOption(state.idOperasi) : null;
      state.namaOperasi = opt ? String(opt.nama || '') : '';
      syncOperasiSelectionUI(true);
      drawCollage(false, true);
      scheduleDraftSave();
    });
  }

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

  [
    els.inputDokHari, els.inputDokTanggal, els.inputDokJudul,
    els.inputHeaderNama, els.inputHeaderAlamat, els.inputHeaderTelp, els.inputHeaderWa, els.inputHeaderEmail,
    els.inputFooterWeb, els.inputFooterIg, els.inputFooterFb, els.inputFooterX,
  ].forEach((inp) => {
    if (!inp) return;
    inp.addEventListener('input', () => {
      drawCollage(false, true);
      scheduleDraftSave();
    });
  });

  [els.chkShowLogo1, els.chkShowLogo2, els.chkShowLogo3].forEach((chk) => {
    if (!chk) return;
    chk.addEventListener('change', () => {
      drawCollage(false, true);
      scheduleDraftSave();
    });
  });

  if (els.btnToggleVideoPlay) {
    els.btnToggleVideoPlay.addEventListener('click', () => {
      if (state.isPlaying) stopPlayback();
      else startPlayback();
    });
  }

  if (els.btnTopDownloadImg) els.btnTopDownloadImg.addEventListener('click', handleDownloadImage);
  if (els.btnTopDownloadVid) els.btnTopDownloadVid.addEventListener('click', handleDownloadVideo);
  if (els.btnSaveArchive) els.btnSaveArchive.addEventListener('click', handleSaveToArchive);
  if (els.btnCancelExport) els.btnCancelExport.addEventListener('click', handleCancelExport);

  // --- Hydrate Saved Archive (Edit Mode) or Auto-Saved IndexedDB Draft ---
  async function hydrateFromDraft(draft) {
    if (!draft) return false;
    state.draftHydrating = true;
    try {
      if (cfg.allowOperasiSelect && els.selectDokOperasi && typeof draft.idOperasi === 'number') {
        state.idOperasi = draft.idOperasi;
        state.namaOperasi = draft.namaOperasi || '';
        els.selectDokOperasi.value = String(draft.idOperasi);
        syncOperasiSelectionUI(false);
      }
      if (draft.form) {
        const f = draft.form;
        if (els.inputDokHari && typeof f.hari === 'string') els.inputDokHari.value = f.hari;
        if (els.inputDokTanggal && typeof f.tanggal === 'string') els.inputDokTanggal.value = f.tanggal;
        if (els.inputDokJudul && typeof f.judul === 'string') els.inputDokJudul.value = f.judul;
        if (els.chkShowLogo1 && typeof f.logo1 === 'boolean') els.chkShowLogo1.checked = f.logo1;
        if (els.chkShowLogo2 && typeof f.logo2 === 'boolean') els.chkShowLogo2.checked = f.logo2;
        if (els.chkShowLogo3 && typeof f.logo3 === 'boolean') els.chkShowLogo3.checked = f.logo3;
        if (els.inputHeaderNama && typeof f.headerNama === 'string') els.inputHeaderNama.value = f.headerNama;
        if (els.inputHeaderAlamat && typeof f.headerAlamat === 'string') els.inputHeaderAlamat.value = f.headerAlamat;
        if (els.inputHeaderTelp && typeof f.headerTelp === 'string') els.inputHeaderTelp.value = f.headerTelp;
        if (els.inputHeaderWa && typeof f.headerWa === 'string') els.inputHeaderWa.value = f.headerWa;
        if (els.inputHeaderEmail && typeof f.headerEmail === 'string') els.inputHeaderEmail.value = f.headerEmail;
        if (els.inputFooterWeb && typeof f.footerWeb === 'string') els.inputFooterWeb.value = f.footerWeb;
        if (els.inputFooterIg && typeof f.footerIg === 'string') els.inputFooterIg.value = f.footerIg;
        if (els.inputFooterFb && typeof f.footerFb === 'string') els.inputFooterFb.value = f.footerFb;
        if (els.inputFooterX && typeof f.footerX === 'string') els.inputFooterX.value = f.footerX;
      }

      state.styleId = draft.styleId === 'editorial_modern' ? 'editorial_modern' : 'klasik_kpp';
      els.styleBtns.forEach((b) => { b.dataset.active = b.dataset.styleId === state.styleId ? 'true' : 'false'; });

      state.orientation = ['vertical', 'horizontal', 'square'].includes(draft.orientation) ? draft.orientation : 'vertical';
      els.orientTabs.forEach((t) => {
        const active = t.dataset.orient === state.orientation;
        t.dataset.active = active ? 'true' : 'false';
        t.className = active
          ? 'dok-orient-tab h-7 rounded-lg text-[11px] font-bold bg-white dark:bg-slate-900 text-orange-700 dark:text-orange-400 shadow-xs transition cursor-pointer'
          : 'dok-orient-tab h-7 rounded-lg text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer';
      });

      const matchedCanvas = Layouts.CANVAS_PRESETS.find((p) => p.id === draft.canvasPresetId)
        || Layouts.CANVAS_PRESETS.find((p) => p.orientation === state.orientation)
        || Layouts.CANVAS_PRESETS[0];
      state.canvasPresetId = matchedCanvas.id;

      state.slotCount = Layouts.clampSlotCount(draft.slotCount || 6);
      ensureSlotsLength(state.slotCount);
      els.slotCountBtns.forEach((b) => { b.dataset.active = Number(b.dataset.slotCount) === state.slotCount ? 'true' : 'false'; });

      const validLayouts = Layouts.getLayoutsForCount(state.slotCount);
      state.layoutId = validLayouts.some((l) => l.id === draft.layoutId)
        ? draft.layoutId
        : Layouts.getDefaultLayoutId(state.slotCount, state.orientation);

      if (draft.qualityId && els.selectExportQuality) {
        state.qualityId = draft.qualityId;
        state.userPickedQuality = !!draft.userPickedQuality;
        els.selectExportQuality.value = draft.qualityId;
      }
      if (draft.imageFormat && els.selectImageFormat) {
        els.selectImageFormat.value = draft.imageFormat;
      }
      if (draft.videoDuration && els.selectVideoDuration) {
        els.selectVideoDuration.value = draft.videoDuration;
      }

      syncCanvasDimensions();
      renderCanvasPresetButtons();
      renderLayoutVariants();

      let loadedCount = 0;
      if (Array.isArray(draft.slots) && draft.slots.length > 0) {
        const tasks = [];
        draft.slots.slice(0, state.slotCount).forEach((sMeta, idx) => {
          if (!sMeta) return;
          if (sMeta.file && typeof sMeta.file === 'object') {
            tasks.push(loadFileIntoSlot(sMeta.file, idx, sMeta).then(() => { loadedCount++; }));
          } else if (sMeta.remoteUrl || sMeta.serverPath) {
            const url = sMeta.remoteUrl || ((window.BASE_URL || '') + '/' + String(sMeta.serverPath).replace(/^\/+/, ''));
            tasks.push(loadRemoteSlotIntoSlot(Object.assign({}, sMeta, { url, path: sMeta.serverPath }), idx).then(() => { loadedCount++; }));
          }
        });
        if (tasks.length > 0) {
          await Promise.all(tasks);
        }
      }

      const firstFilled = activeSlotsSlice().findIndex(Boolean);
      if (firstFilled >= 0) state.selectedSlotIndex = firstFilled;
      refreshAllUI();

      if (loadedCount > 0) {
        if (els.draftRestoredBadge) els.draftRestoredBadge.classList.remove('hidden');
        notify(`Draf otomatis dipulihkan (${loadedCount} media siap disimpan).`, 'info');
      }

      // Sambung kembali job server FFmpeg jika masih aktif saat refresh!
      if (draft.activeJob && draft.activeJob.jobId && CAPS.server_render) {
        resumeServerJobAfterRefresh(draft.activeJob.jobId);
      }
      return true;
    } finally {
      state.draftHydrating = false;
    }
  }

  async function resumeServerJobAfterRefresh(jobId) {
    const jobsBase = (cfg.jobsApiBase || ((window.BASE_URL || '') + '/api/index.php?path=/dokumentasi/jobs')).replace(/\/+$/, '');
    try {
      const resp = await fetch(`${jobsBase}/${jobId}`, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      const data = await resp.json().catch(() => null);
      if (!resp.ok || !data || !data.success) {
        if (DraftStore) DraftStore.patchActiveJob(DRAFT_SCOPE_KEY, null).catch(() => {});
        return;
      }
      if (data.status === 'completed' && data.item) {
        await finalizeSavedSuccess(data.item.width_px || 1080, data.item.height_px || 1620, !!(cfg.editDoc && cfg.editDoc.id));
        return;
      }
      if (data.status === 'queued' || data.status === 'rendering') {
        state.isExporting = true;
        state.activeServerJobId = jobId;
        state.activeAbortController = new AbortController();
        setActionButtonsDisabled(true);
        const label = 'Melanjutkan penyimpanan dokumentasi...';
        setUnifiedProgress(true, label, Math.max(55, Number(data.progress) || 55), true);
        const done = await pollServerRenderJob(jobId, state.activeAbortController.signal, 55, 99, label);
        const item = done && done.item;
        await finalizeSavedSuccess((item && item.width_px) || 1080, (item && item.height_px) || 1620, !!(cfg.editDoc && cfg.editDoc.id));
      } else {
        if (DraftStore) DraftStore.patchActiveJob(DRAFT_SCOPE_KEY, null).catch(() => {});
      }
    } catch (err) {
      setUnifiedProgress(false, '', 0, true);
      if (DraftStore) DraftStore.patchActiveJob(DRAFT_SCOPE_KEY, null).catch(() => {});
    } finally {
      state.isExporting = false;
      state.activeServerJobId = null;
      state.activeAbortController = null;
      setActionButtonsDisabled(false);
    }
  }

  async function hydrateEditDoc(editDoc) {
    state.draftHydrating = true;
    try {
      const proj = (editDoc && editDoc.project && typeof editDoc.project === 'object') ? editDoc.project : null;
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

      const targetOrient = (proj && proj.orientation) || editDoc.orientation || 'vertical';
      const targetCount = Layouts.clampSlotCount((proj && proj.slotCount) || editDoc.slot_count || 6);

      state.styleId = targetStyle === 'editorial_modern' ? 'editorial_modern' : 'klasik_kpp';
      els.styleBtns.forEach((b) => { b.dataset.active = b.dataset.styleId === state.styleId ? 'true' : 'false'; });

      state.orientation = ['vertical', 'horizontal', 'square'].includes(targetOrient) ? targetOrient : 'vertical';
      els.orientTabs.forEach((t) => {
        const active = t.dataset.orient === state.orientation;
        t.dataset.active = active ? 'true' : 'false';
        t.className = active
          ? 'dok-orient-tab h-7 rounded-lg text-[11px] font-bold bg-white dark:bg-slate-900 text-orange-700 dark:text-orange-400 shadow-xs transition cursor-pointer'
          : 'dok-orient-tab h-7 rounded-lg text-[11px] font-semibold text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer';
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
      els.slotCountBtns.forEach((b) => { b.dataset.active = Number(b.dataset.slotCount) === targetCount ? 'true' : 'false'; });

      const validLayouts = Layouts.getLayoutsForCount(targetCount);
      state.layoutId = validLayouts.some((l) => l.id === targetLayout)
        ? targetLayout
        : Layouts.getDefaultLayoutId(targetCount, state.orientation);

      if (proj && proj.qualityId && els.selectExportQuality) {
        state.qualityId = proj.qualityId;
        state.userPickedQuality = true;
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

      if (proj && Array.isArray(proj.slots) && proj.slots.length > 0) {
        const tasks = [];
        proj.slots.slice(0, state.slotCount).forEach((slotMeta, idx) => {
          if (slotMeta && slotMeta.url) {
            tasks.push(loadRemoteSlotIntoSlot(slotMeta, idx));
          }
        });
        if (tasks.length > 0) {
          await Promise.all(tasks);
          const firstFilled = activeSlotsSlice().findIndex(Boolean);
          if (firstFilled >= 0) state.selectedSlotIndex = firstFilled;
          refreshAllUI();
        }
      }
    } finally {
      state.draftHydrating = false;
    }
  }

  // Expose studio helper for automated verification
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
      capabilities: CAPS,
    }),
    setStyle,
    setActiveStudioTab,
    loadFileIntoSlot,
    openVideoTrimModal,
    refreshAllUI,
    drawCollage,
  };

  // --- Initial Boot ---
  async function bootStudio() {
    ensureSlotsLength(state.slotCount);
    syncOperasiSelectionUI(false);
    preloadLogos();

    // Cek draf otomatis IndexedDB terlebih dahulu (pulihkan jika sebelumnya refresh saat loading/editing)
    const savedDraft = DraftStore ? await DraftStore.load(DRAFT_SCOPE_KEY).catch(() => null) : null;
    if (savedDraft && ((Array.isArray(savedDraft.slots) && savedDraft.slots.some(Boolean)) || savedDraft.activeJob)) {
      await hydrateFromDraft(savedDraft);
      return;
    }

    if (cfg.editDoc && cfg.editDoc.id) {
      await hydrateEditDoc(cfg.editDoc);
    } else {
      setOrientation('vertical');
      refreshAllUI();
    }
  }

  bootStudio();
})();
