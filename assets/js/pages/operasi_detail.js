// Page Configuration from window.__OPS_DETAIL_CONFIG__
const opsDetailCfg = window.__OPS_DETAIL_CONFIG__ || {};
const OPS_DATA = opsDetailCfg.opsData || {};

let detailOpsMap = null;
let detailOpsGisBundle = opsDetailCfg.gisBundle || {};
let activeHistoryFilter = 'all';

function formatDetailDms(lat, lng) {
    return window.QrSarUtils.formatDmsPair(lat, lng);
}

function switchDetailMapLayer(layerKey) {
    if (!detailOpsMap) return;
    detailOpsMap.switchBaseLayer(layerKey);

    const layerBtns = {
        'hybrid': document.getElementById('btn-layer-hybrid'),
        'satellite': document.getElementById('btn-layer-satellite'),
        'roadmap': document.getElementById('btn-layer-roadmap'),
        'terrain': document.getElementById('btn-layer-terrain')
    };

    Object.entries(layerBtns).forEach(([key, btn]) => {
        if (!btn) return;
        if (key === layerKey) {
            btn.className = 'px-2.5 py-1 rounded-lg transition cursor-pointer bg-orange-600 text-white shadow-xs font-bold text-[11px] flex items-center gap-1';
        } else {
            btn.className = 'px-2.5 py-1 rounded-lg transition cursor-pointer bg-transparent text-slate-700 dark:text-slate-300 hover:text-orange-500 font-bold text-[11px] flex items-center gap-1';
        }
    });
}

function filterDetailGisHistory(cat) {
    activeHistoryFilter = cat;
    const filterButtons = {
        'all': document.getElementById('btn-hist-all'),
        'polygons': document.getElementById('btn-hist-polygons'),
        'patterns': document.getElementById('btn-hist-patterns'),
        'routes': document.getElementById('btn-hist-routes'),
        'points': document.getElementById('btn-hist-points'),
        'basemap': document.getElementById('btn-hist-basemap')
    };

    Object.entries(filterButtons).forEach(([key, btn]) => {
        if (!btn) return;
        if (key === cat) {
            btn.className = 'px-2.5 py-1 rounded-lg text-xs font-bold bg-orange-600 text-white transition cursor-pointer';
        } else {
            btn.className = 'px-2.5 py-1 rounded-lg text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white transition cursor-pointer';
        }
    });

    renderDetailGisHistoryList();
}

function updateDetailGisStats() {
    const polys = (detailOpsGisBundle.polygons || []).length;
    const pats = (detailOpsGisBundle.searchPatterns || []).length;
    const rts = (detailOpsGisBundle.routes || []).length;
    const pts = (detailOpsGisBundle.points || []).length;
    const total = polys + pats + rts + pts;

    const elPolys = document.getElementById('stat-cnt-polygons');
    const elPats = document.getElementById('stat-cnt-patterns');
    const elRts = document.getElementById('stat-cnt-routes');
    const elPts = document.getElementById('stat-cnt-points');
    const elTotal = document.getElementById('detail-total-badge');
    const elTabBadge = document.getElementById('gis-tab-count');

    if (elPolys) elPolys.textContent = polys;
    if (elPats) elPats.textContent = pats;
    if (elRts) elRts.textContent = rts;
    if (elPts) elPts.textContent = pts;
    if (elTotal) elTotal.textContent = `Total ${total}`;
    if (elTabBadge) {
        elTabBadge.textContent = total;
        if (total > 0) {
            elTabBadge.classList.remove('hidden');
        }
    }
}

function focusDetailMapComponent(layerKey) {
    if (!detailOpsMap) return;
    const ok = detailOpsMap.focusComponent(layerKey);
    const mapEl = document.getElementById('map-detail-ops');
    if (mapEl) {
        mapEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    if (!ok) {
        showToast('Komponen belum terpetakan pada viewport saat ini', 'warning');
    }
}

function copyDetailPolygonCoords(id) {
    const p = (detailOpsGisBundle.polygons || []).find(item => item.id == id);
    if (!p) {
        showToast('Search Area tidak ditemukan', 'error');
        return;
    }
    if (p.shapeType === 'circle') {
        const cLat = (p.centerLat !== null && p.centerLat !== undefined && p.centerLat !== '') ? Number(p.centerLat) : (p.points && p.points[0] ? Number(p.points[0].lat) : 0);
        const cLng = (p.centerLng !== null && p.centerLng !== undefined && p.centerLng !== '') ? Number(p.centerLng) : (p.points && p.points[0] ? Number(p.points[0].lng) : 0);
        const rNm = Number(p.radiusNm || 1);
        const rKm = (rNm * 1.852).toFixed(2);
        const areaNm = p.areaNm2 ? Number(p.areaNm2).toFixed(2) : (Math.PI * rNm * rNm).toFixed(2);
        const text = `[Search Area: ${p.name || 'Sektor Radius'}]\nBentuk: Lingkaran (Circle)\nPusat Datum (LKP): ${formatDetailDms(cLat, cLng)}\nRadius Sektor: ${rNm} NM (${rKm} km)\nLuas Area: ${areaNm} NM²`;
        copyToClipboard(text, 'Parameter Radius Search Area Disalin!');
        return;
    }
    if (!p.points || !p.points.length) {
        showToast('Koordinat tidak ditemukan', 'error');
        return;
    }
    const lines = p.points.map((pt, idx) => {
        const letter = String.fromCharCode(65 + (idx % 26));
        return `Titik ${letter}: ${formatDetailDms(pt.lat, pt.lng)}`;
    });
    copyToClipboard(`[Search Area: ${p.name || 'Sektor'}]\n` + lines.join('\n'), 'Koordinat Sudut Search Area Disalin!');
}

function copyDetailPatternCoords(id) {
    const p = (detailOpsGisBundle.searchPatterns || []).find(item => item.id == id);
    if (!p || !p.waypoints || !p.waypoints.length) {
        showToast('Koordinat tidak ditemukan', 'error');
        return;
    }
    if (p.patternType === 'e_sar' || p.patternType === 'sector_search') {
        const lines = [];
        for (let i = 0; i < p.waypoints.length; i += 2) {
            const pIndex = Math.floor(i / 2) + 1;
            const wpStart = p.waypoints[i];
            const wpEnd = p.waypoints[i + 1];
            if (wpStart) {
                lines.push(`Personel ${pIndex} (Start Baseline): ${formatDetailDms(wpStart.lat, wpStart.lng)}`);
            }
            if (wpEnd) {
                lines.push(`Personel ${pIndex} (Batas Maju): ${formatDetailDms(wpEnd.lat, wpEnd.lng)}`);
            }
        }
        copyToClipboard(`[Pola SAR E-SAR: ${p.name || 'Pola'}]\n` + lines.join('\n'), 'Koordinat Pola E-SAR Disalin!');
        return;
    }
    const lines = p.waypoints.map((pt, idx) => {
        const lbl = idx === 0 ? 'Start (Entry)' : (idx === p.waypoints.length - 1 ? 'End (Exit)' : `WP-${idx + 1}`);
        return `${lbl}: ${formatDetailDms(pt.lat, pt.lng)}`;
    });
    copyToClipboard(`[Pola SAR: ${p.name || 'Pola'}]\n` + lines.join('\n'), 'Koordinat Pola SAR Disalin!');
}

function copyDetailRouteCoords(id) {
    const r = (detailOpsGisBundle.routes || []).find(item => item.id == id);
    if (!r || !r.points || !r.points.length) {
        showToast('Koordinat tidak ditemukan', 'error');
        return;
    }
    const lines = r.points.map((pt, idx) => {
        return `WP-${idx + 1} (${pt.label || 'Titik'}): ${formatDetailDms(pt.lat, pt.lng)}`;
    });
    copyToClipboard(`[Jalur: ${r.name || 'Track'}]\n` + lines.join('\n'), 'Koordinat Jalur Disalin!');
}

function copyDetailPointCoords(id) {
    const pt = (detailOpsGisBundle.points || []).find(item => item.id == id);
    if (!pt) {
        showToast('Koordinat tidak ditemukan', 'error');
        return;
    }
    const dms = formatDetailDms(pt.lat, pt.lng);
    copyToClipboard(`${pt.label || 'Titik Taktis'}: ${dms} (${Number(pt.lat).toFixed(6)}, ${Number(pt.lng).toFixed(6)})`, 'Koordinat Titik Disalin!');
}

function sanitizeDetailGisColor(color, fallback = '#2563eb') {
    if (!color || typeof color !== 'string') return fallback;
    const trimmed = color.trim();
    return /^#[0-9a-fA-F]{3,8}$/.test(trimmed) ? trimmed : fallback;
}

function triggerDetailGisCopy(type, id) {
    if (type === 'polygon') return copyDetailPolygonCoords(id);
    if (type === 'pattern') return copyDetailPatternCoords(id);
    if (type === 'route') return copyDetailRouteCoords(id);
    if (type === 'point') return copyDetailPointCoords(id);
}

function renderDetailGisHistoryList() {
    const container = document.getElementById('detail-gis-history-container');
    if (!container) return;

    let items = [];

    // 1. Polygons
    if (Array.isArray(detailOpsGisBundle.polygons)) {
        detailOpsGisBundle.polygons.forEach(p => {
            const isBaseMap = !p.id_operasi;
            if (activeHistoryFilter === 'polygons' || activeHistoryFilter === 'all' || (activeHistoryFilter === 'basemap' && isBaseMap)) {
                let metaStr = '';
                if (p.shapeType === 'circle') {
                    const rNm = Number(p.radiusNm || 1).toFixed(2);
                    metaStr = `⭕ Radius: ${rNm} NM &bull; Luas: ${p.areaNm2 ? Number(p.areaNm2).toFixed(2) + ' Nm²' : '- Nm²'}`;
                } else if (p.shapeType === 'rectangle') {
                    const wNm = Number(p.boxWidthNm || 2).toFixed(2);
                    const hNm = Number(p.boxHeightNm || 2).toFixed(2);
                    metaStr = `⏹️ Persegi: ${wNm} x ${hNm} NM &bull; Luas: ${p.areaNm2 ? Number(p.areaNm2).toFixed(2) + ' Nm²' : '- Nm²'}`;
                } else {
                    metaStr = `${p.areaNm2 ? Number(p.areaNm2).toFixed(2) + ' Nm²' : '- Nm²'} &bull; ${(p.points || []).length} Sudut Batas`;
                }

                items.push({
                    type: 'polygon',
                    layerKey: 'polygon_' + p.id,
                    id: p.id,
                    isBaseMap: isBaseMap,
                    name: p.name || 'Sektor Pencarian',
                    tag: 'Search Area',
                    tagColor: 'blue',
                    color: sanitizeDetailGisColor(p.strokeColor, '#2563eb'),
                    meta: metaStr,
                    desc: p.keterangan || ''
                });
            }
        });
    }

    // 2. Search Patterns
    if (Array.isArray(detailOpsGisBundle.searchPatterns)) {
        const typeLabels = {
            'creeping_line': 'Creeping Line Search',
            'parallel_track': 'Parallel Track Search',
            'e_sar': 'Pola Pencarian E-SAR',
            'sector_search': 'Pola Pencarian E-SAR',
            'track_line': 'Track Line Search'
        };
        detailOpsGisBundle.searchPatterns.forEach(p => {
            const isBaseMap = !p.id_operasi;
            if (activeHistoryFilter === 'patterns' || activeHistoryFilter === 'all' || (activeHistoryFilter === 'basemap' && isBaseMap)) {
                const isEsar = (p.patternType === 'e_sar' || p.patternType === 'sector_search');
                const typeStr = escapeHtml(typeLabels[p.patternType] || p.patternType || 'Pola SAR');
                const safeSpacing = escapeHtml(p.trackSpacingNm || '-');
                const safeDist = p.totalDistanceNm ? Number(p.totalDistanceNm).toFixed(1) : '-';
                const safeLegs = Number(p.legsCount) || (isEsar ? Math.floor((p.waypoints || []).length / 2) : (p.waypoints || []).length);
                const legsLabel = isEsar ? 'Personil' : 'Leg';
                items.push({
                    type: 'pattern',
                    layerKey: 'pattern_' + p.id,
                    id: p.id,
                    isBaseMap: isBaseMap,
                    name: p.name || 'Pola Pencarian SAR',
                    tag: 'Pola SAR',
                    tagColor: 'orange',
                    color: sanitizeDetailGisColor(p.color, '#ea580c'),
                    meta: `${typeStr} &bull; S: ${safeSpacing} Nm &bull; Jarak: ${safeDist} Nm &bull; ${safeLegs} ${legsLabel}`,
                    desc: p.notes || ''
                });
            }
        });
    }

    // 3. Routes
    if (Array.isArray(detailOpsGisBundle.routes)) {
        detailOpsGisBundle.routes.forEach(r => {
            const isBaseMap = !r.id_operasi;
            if (activeHistoryFilter === 'routes' || activeHistoryFilter === 'all' || (activeHistoryFilter === 'basemap' && isBaseMap)) {
                const safeDist = r.totalDistanceNm ? Number(r.totalDistanceNm).toFixed(1) : '-';
                const safeSpeed = escapeHtml(r.vesselSpeedKnots || 25);
                items.push({
                    type: 'route',
                    layerKey: 'route_' + r.id,
                    id: r.id,
                    isBaseMap: isBaseMap,
                    name: r.name || 'Jalur Operasi',
                    tag: 'Jalur',
                    tagColor: 'emerald',
                    color: sanitizeDetailGisColor(r.strokeColor, '#059669'),
                    meta: `${safeDist} Nm &bull; ${safeSpeed} Knots &bull; ${(r.points || []).length} Waypoint`,
                    desc: r.notes || ''
                });
            }
        });
    }

    // 4. Points
    if (Array.isArray(detailOpsGisBundle.points)) {
        detailOpsGisBundle.points.forEach(pt => {
            const isBaseMap = !pt.id_operasi;
            if (activeHistoryFilter === 'points' || activeHistoryFilter === 'all' || (activeHistoryFilter === 'basemap' && isBaseMap)) {
                items.push({
                    type: 'point',
                    layerKey: 'point_' + pt.id,
                    id: pt.id,
                    isBaseMap: isBaseMap,
                    name: pt.label || 'Titik Taktis',
                    tag: pt.category || 'Titik Taktis',
                    tagColor: 'purple',
                    color: '#8b5cf6',
                    meta: `${escapeHtml(formatDetailDms(Number(pt.lat) || 0, Number(pt.lng) || 0))}`,
                    desc: pt.description || ''
                });
            }
        });
    }

    if (items.length === 0) {
        container.innerHTML = `
            <div class="p-8 text-center bg-slate-50 dark:bg-slate-950/40 rounded-xl border border-dashed border-slate-200 dark:border-slate-800">
                <div class="text-3xl mb-2">🗺️</div>
                <div class="text-xs font-bold text-slate-700 dark:text-slate-300">Belum ada komponen pada kategori ini</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                    Buka halaman Plotting GIS untuk menggambar Search Area, Pola Pencarian SAR, atau membuat Titik Taktis baru.
                </div>
            </div>
        `;
        return;
    }

    let html = '';
    items.forEach(item => {
        const badgeBg = item.isBaseMap 
            ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20' 
            : 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border border-orange-500/20';
        const badgeText = item.isBaseMap ? 'BASE MAP' : 'OPERASI SAR';

        let icon = '⬡';
        if (item.type === 'pattern') icon = '⚡';
        else if (item.type === 'route') icon = '↗️';
        else if (item.type === 'point') icon = '📍';

        const safeColor = sanitizeDetailGisColor(item.color, '#2563eb');
        const safeIdAttr = escapeHtml(item.id);
        const safeTypeAttr = escapeHtml(item.type);
        const safeLayerKeyAttr = escapeHtml(item.layerKey);

        html += `
            <div class="p-3.5 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950/60 hover:border-orange-500/40 transition flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 text-base" style="background-color: ${safeColor}15; color: ${safeColor};">
                        ${icon}
                    </div>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5 mb-0.5">
                            <span class="text-xs font-bold text-slate-900 dark:text-white truncate max-w-[240px] sm:max-w-xs">
                                ${escapeHtml(item.name)}
                            </span>
                            <span class="text-[11px] font-bold px-1.5 py-0.2 rounded ${badgeBg} font-mono">
                                ${badgeText}
                            </span>
                            <span class="text-[11px] font-medium text-slate-400">
                                &bull; ${escapeHtml(item.tag)}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                            ${item.meta}
                        </div>
                        ${item.desc ? `<div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 line-clamp-1 italic">${escapeHtml(item.desc)}</div>` : ''}
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    <button type="button" data-copy-type="${safeTypeAttr}" data-copy-id="${safeIdAttr}" onclick="triggerDetailGisCopy(this.dataset.copyType, this.dataset.copyId)" class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 text-xs font-semibold transition cursor-pointer flex items-center gap-1" title="Salin Data Koordinat">
                        <span>📋</span>
                        <span class="text-[11px]">Salin</span>
                    </button>
                    <button type="button" data-layer-key="${safeLayerKeyAttr}" onclick="focusDetailMapComponent(this.dataset.layerKey)" class="px-2.5 py-1.5 rounded-lg bg-orange-600 hover:bg-orange-700 text-white text-xs font-bold transition cursor-pointer flex items-center gap-1 shadow-xs" title="Fokuskan peta ke komponen ini">
                        <span>🎯</span>
                        <span class="text-[11px]">Fokus</span>
                    </button>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function switchTab(tabName) {
    let resolvedTab = tabName;
    if (resolvedTab === 'ringkasan') resolvedTab = 'hierarchy';
    if (resolvedTab === 'laporan') resolvedTab = 'sitrep';

    document.querySelectorAll('.tab-pane').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    const targetContent = document.getElementById(`tab-content-${resolvedTab}`) || document.getElementById(`tab-content-${tabName}`);
    if (targetContent) targetContent.style.display = 'block';

    const btn = Array.from(document.querySelectorAll('.tab-btn')).find(b => 
        b.getAttribute('onclick')?.includes(`'${resolvedTab}'`) || b.getAttribute('onclick')?.includes(`'${tabName}'`)
    );
    if (btn) btn.classList.add('active');

    const url = new URL(window.location);
    url.searchParams.set('tab', resolvedTab);
    window.history.replaceState({}, '', url);

    if (resolvedTab === 'gis') {
        setTimeout(initDetailMap, 100);
    }
}

function navigateToDoc(tab, docId) {
    switchTab(tab);
    if (!docId) return;
    setTimeout(() => {
        const cardId = tab === 'renops' ? `card-renops-${docId}` : `card-sitrep-${docId}`;
        const target = document.getElementById(cardId);
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const ringColor = tab === 'renops' ? 'ring-orange-500' : 'ring-blue-500';
            target.classList.add('ring-4', ringColor, 'ring-offset-2', 'dark:ring-offset-slate-900', 'transition-all', 'duration-500');
            setTimeout(() => {
                target.classList.remove('ring-4', ringColor, 'ring-offset-2', 'dark:ring-offset-slate-900');
            }, 2500);
        }
    }, 150);
}

function initDetailMap() {
    // 1. Dependency Guard: Tunggu sampai Leaflet (L) dan TacticalGisMap siap
    if (typeof TacticalGisMap === 'undefined' || typeof L === 'undefined') {
        setTimeout(initDetailMap, 100);
        return;
    }

    const container = document.getElementById('map-detail-ops');
    if (!container) return;

    if (detailOpsMap && detailOpsMap.map) {
        detailOpsMap.map.invalidateSize();
        setTimeout(() => {
            if (detailOpsMap && detailOpsMap.map) {
                detailOpsMap.map.invalidateSize();
                detailOpsMap.fitAllComponents();
            }
        }, 150);
        return;
    }

    const lat = Number(opsDetailCfg.lkpLat) || -4.037694;
    const lng = Number(opsDetailCfg.lkpLng) || 122.486694;
    const hasLkp = Boolean(opsDetailCfg.hasLkpCoords);
    const lkpTitle = opsDetailCfg.namaOperasi || 'Lokasi Kejadian (LKP)';

    detailOpsMap = new TacticalGisMap('map-detail-ops', {
        center: [lat, lng],
        zoom: hasLkp ? 11 : 9,
        defaultLayer: 'hybrid'
    });

    if (hasLkp) {
        detailOpsMap.addLkpMarker(lat, lng, lkpTitle);
    }

    // Load initial bundled components (Search Areas, Pola SAR, Jalur, Titik Taktis)
    detailOpsMap.loadOperationBundle(detailOpsGisBundle);
    renderDetailGisHistoryList();
    updateDetailGisStats();

    // Invalidate size dan fit components setelah browser selesai reflow layout
    setTimeout(() => {
        if (detailOpsMap && detailOpsMap.map) {
            detailOpsMap.map.invalidateSize();
            detailOpsMap.fitAllComponents();
        }
    }, 200);
}

function refreshDetailGis(showFeedback = false) {
    const btn = document.getElementById('btn-refresh-gis');
    const icon = document.getElementById('icon-refresh-gis');
    if (btn) btn.disabled = true;
    if (icon) icon.classList.add('animate-spin');

    return fetch((window.BASE_URL || '') + '/api/gis/bundle?id_operasi=' + encodeURIComponent(opsDetailCfg.idOperasi) + '&include_base_map=1')
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                detailOpsGisBundle = res.data;
                if (detailOpsMap) {
                    detailOpsMap.loadOperationBundle(detailOpsGisBundle);
                    detailOpsMap.fitAllComponents();
                }
                renderDetailGisHistoryList();
                updateDetailGisStats();
                if (showFeedback && typeof showToast === 'function') {
                    showToast('Data plotting GIS berhasil diperbarui', 'success');
                }
            } else if (showFeedback && typeof showToast === 'function') {
                showToast(res.message || 'Gagal memuat pembaruan GIS', 'error');
            }
        })
        .catch(err => {
            console.error('GIS Bundle sync error:', err);
            if (showFeedback && typeof showToast === 'function') {
                showToast('Gagal terhubung ke server GIS', 'error');
            }
        })
        .finally(() => {
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('animate-spin');
        });
}

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || opsDetailCfg.activeTab;
    if (activeTab === 'gis') {
        setTimeout(initDetailMap, 200);
    }
});

function copyDetailWA() {
    const el = document.getElementById('wa-awal-modal-text') || document.getElementById('modal-wa-awal-text') || document.getElementById('detail-wa-text');
    const text = el ? (el.value || el.textContent || '').trim() : '';
    copyToClipboard(text, 'Naskah Laporan Awal WhatsApp Berhasil Disalin!');
}

function copyTextToClipboard(text, msg) {
    copyToClipboard(text, msg);
}

const escapeHtml = (window.QrSarUtils && window.QrSarUtils.escapeHtml) || function(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

async function deleteRenops(id) {
    if (!OPS_DATA.can_delete) {
        showToast('Akses ditolak: Hanya Administrator yang berwenang menghapus dokumen RENOPS.', 'error');
        return;
    }
    if (!OPS_DATA.is_open) {
        showToast('Operasi SAR ini telah DITUTUP. Data tidak dapat dihapus.', 'error');
        return;
    }
    if (await confirmAction('Hapus RENOPS?', 'Apakah Anda yakin ingin menghapus lembar Rencana Operasi ini?')) {
        try {
            const res = await fetch((window.BASE_URL || '') + '/api/renops/' + encodeURIComponent(id), { 
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            let json = {};
            try { json = await res.json(); } catch (_) { json = {}; }
            if (res.ok && json.success) {
                showToast('RENOPS berhasil dihapus', 'success');
                setTimeout(() => location.reload(), 600);
            } else {
                showToast(json.error || json.message || ('Gagal menghapus RENOPS (' + res.status + ')'), 'error');
            }
        } catch (e) {
            showToast('Terjadi kesalahan jaringan: ' + e.message, 'error');
        }
    }
}

async function deleteLaporan(id) {
    if (!OPS_DATA.can_delete) {
        showToast('Akses ditolak: Hanya Administrator yang berwenang menghapus dokumen SITREP.', 'error');
        return;
    }
    if (!OPS_DATA.is_open) {
        showToast('Operasi SAR ini telah DITUTUP. Data tidak dapat dihapus.', 'error');
        return;
    }
    if (await confirmAction('Hapus SITREP?', 'Apakah Anda yakin ingin menghapus laporan perkembangan ini?')) {
        try {
            const res = await fetch((window.BASE_URL || '') + '/api/laporan/' + encodeURIComponent(id), { 
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            });
            let json = {};
            try { json = await res.json(); } catch (_) { json = {}; }
            if (res.ok && json.success) {
                showToast('Laporan berhasil dihapus', 'success');
                setTimeout(() => location.reload(), 600);
            } else {
                showToast(json.error || json.message || ('Gagal menghapus laporan (' + res.status + ')'), 'error');
            }
        } catch (e) {
            showToast('Terjadi kesalahan jaringan: ' + e.message, 'error');
        }
    }
}

async function confirmAndRollbackOperasi(idOperasi) {
    if (!idOperasi) return;

    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const nowDefault = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

    if (typeof Swal !== 'undefined') {
        const res = await Swal.fire({
            title: 'Buka Kembali / Perpanjang Operasi SAR?',
            html: `<div class="text-left text-xs text-slate-600 dark:text-slate-300 space-y-3">
                <p>Status operasi SAR akan dikembalikan dari <strong>CLOSED</strong> menjadi <strong>OPEN</strong> (Aktif kembali).</p>
                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/25 text-amber-800 dark:text-amber-300 space-y-1">
                    <p class="font-bold flex items-center gap-1.5">
                        <span>Perhitungan Hari Operasi Dimulai Lagi:</span>
                    </p>
                    <p class="text-[11px] leading-relaxed">
                        Sesuai ketentuan operasional SAR, perhitungan hari lanjutan (misal: <strong>H7 + 1, H7 + 2, dst.</strong>) untuk RENOPS dan SITREP akan dihitung mulai dari tanggal operasi dibuka kembali.
                    </p>
                </div>
                <div class="space-y-1.5 pt-1">
                    <label class="block font-bold text-slate-700 dark:text-slate-300 text-[11px]">Tanggal & Waktu Dibuka Kembali (WITA):</label>
                    <input type="datetime-local" id="swal_waktu_reopen" value="${nowDefault}" class="control control-sm w-full font-semibold">
                </div>
            </div>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#f97316',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Ya, Buka & Mulai Hari Baru',
            cancelButtonText: 'Batal',
            reverseButtons: true,
            preConfirm: () => {
                const val = document.getElementById('swal_waktu_reopen')?.value;
                return val || nowDefault;
            }
        });

        if (!res.isConfirmed) return;

        const waktuReopenInput = res.value;

        Swal.fire({
            title: 'Membuka Kembali Operasi...',
            text: 'Sedang memperbarui status operasional ke server...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const resp = await fetch((window.BASE_URL || '') + '/api/operasi/' + encodeURIComponent(idOperasi) + '/rollback-penutupan', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ 
                    waktu_reopen: waktuReopenInput,
                    csrf_token: window.CSRF_TOKEN || document.querySelector('meta[name="csrf-token"]')?.content || ''
                })
            });
            let data = {};
            try { data = await resp.json(); } catch (_) { data = {}; }
            if (resp.ok && data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Operasi SAR Berhasil Dibuka!',
                    text: data.message || 'Status operasi kini kembali OPEN. Perhitungan hari operasional dimulai lagi sejak tanggal pembukaan.',
                    confirmButtonColor: '#f97316',
                    confirmButtonText: 'Lanjutkan'
                });
                window.location.reload();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Membuka Kembali Operasi',
                    text: data.message || ('Terjadi kesalahan sistem saat memperbarui status (' + resp.status + ').'),
                    confirmButtonColor: '#ef4444'
                });
            }
        } catch (err) {
            console.error('Rollback fetch error, submitting form fallback:', err);
            const form = document.getElementById('form-fallback-rollback');
            const hiddenWaktu = document.getElementById('fallback_waktu_reopen');
            if (hiddenWaktu) hiddenWaktu.value = waktuReopenInput;
            if (form) {
                form.submit();
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Koneksi Gagal',
                    text: 'Gagal menghubungi server: ' + err.message,
                    confirmButtonColor: '#ef4444'
                });
            }
        }
    } else {
        const confirmed = typeof confirmAction === 'function'
            ? await confirmAction('Buka Kembali Operasi SAR?', 'Buka kembali operasi SAR ini menjadi status OPEN? Perhitungan hari operasional akan dimulai lagi.', 'Ya, Buka Kembali', false)
            : true;
        if (confirmed) {
            const form = document.getElementById('form-fallback-rollback');
            if (form) form.submit();
        }
    }
}
