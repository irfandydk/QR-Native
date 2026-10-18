/**
 * QR-SAR - Tactical LKP & Nautical Route Planner Engine (Modular Map Controller)
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 * Part of QRSAR.OperasiFormMap Architecture Decomposition
 */

(function () {
  'use strict';

  window.QRSAR = window.QRSAR || {};

  const opsFormCfg = window.__OPS_FORM_CONFIG__ || {};

  const escapeHtml = (window.QrSarUtils && window.QrSarUtils.escapeHtml) || function (str) {
    if (!str) return '';
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  };

  function padZero(num, size = 2) {
    let s = String(num);
    while (s.length < size) s = '0' + s;
    return s;
  }

  // Centralized Tactical Map State
  const tacticalState = {
    inlineMap: null,
    modalMap: null,
    routingMode: 'direct', // 'direct' | 'manual'
    originLat: -4.037857,
    originLng: 122.486653,
    originName: 'Kantor SAR Kendari',
    originIconUrl: '',
    originPointType: 'custom_upload',
    originCategory: 'Kantor SAR',
    lkpLat: -3.976387,
    lkpLng: 122.588475,
    manualWaypoints: [], // Array of { lat, lng, label }
    vesselSpeedKnots: 25.0,
    speedUnit: 'knots', // 'knots' | 'kmh'
    tacticalDistanceNm: 0.0,
    tacticalDistanceKm: 0.0,
    tacticalRadial: 0,
    tacticalEtaMinutes: 0,
    etaStr: '',
    isUpdatingFromMap: false,
    isUpdatingFromInput: false,
    inlineLayers: {
      originMarker: null,
      lkpMarker: null,
      routeLine: null,
      waypointMarkers: []
    },
    modalLayers: {
      originMarker: null,
      lkpMarker: null,
      routeLine: null,
      waypointMarkers: []
    }
  };

  function getLkpDecimalCoordinates() {
    const latDeg = parseInt(document.getElementById('dms_lat_deg')?.value || '0', 10);
    const latMin = parseInt(document.getElementById('dms_lat_min')?.value || '0', 10);
    const latSec = parseFloat(document.getElementById('dms_lat_sec')?.value || '0');
    const latDir = document.getElementById('dms_lat_dir')?.value || 'S';

    const lngDeg = parseInt(document.getElementById('dms_lng_deg')?.value || '0', 10);
    const lngMin = parseInt(document.getElementById('dms_lng_min')?.value || '0', 10);
    const lngSec = parseFloat(document.getElementById('dms_lng_sec')?.value || '0');
    const lngDir = document.getElementById('dms_lng_dir')?.value || 'E';

    let lat = latDeg + (latMin / 60) + ((isNaN(latSec) ? 0 : latSec) / 3600);
    if (latDir === 'S') lat = -lat;

    let lng = lngDeg + (lngMin / 60) + ((isNaN(lngSec) ? 0 : lngSec) / 3600);
    if (lngDir === 'W') lng = -lng;

    return { lat, lng };
  }

  function decimalToDmsParts(lat, lng) {
    const isLatS = lat < 0;
    const absLat = Math.abs(lat);
    let latDeg = Math.floor(absLat);
    let latMinFloat = (absLat - latDeg) * 60;
    let latMin = Math.floor(latMinFloat);
    let latSec = Math.round((latMinFloat - latMin) * 60 * 10) / 10;
    if (latSec >= 60) {
      latSec = 0;
      latMin += 1;
      if (latMin >= 60) {
        latMin = 0;
        latDeg += 1;
      }
    }

    const isLngW = lng < 0;
    const absLng = Math.abs(lng);
    let lngDeg = Math.floor(absLng);
    let lngMinFloat = (absLng - lngDeg) * 60;
    let lngMin = Math.floor(lngMinFloat);
    let lngSec = Math.round((lngMinFloat - lngMin) * 60 * 10) / 10;
    if (lngSec >= 60) {
      lngSec = 0;
      lngMin += 1;
      if (lngMin >= 60) {
        lngMin = 0;
        lngDeg += 1;
      }
    }

    return {
      latDeg,
      latMin,
      latSec,
      latDir: isLatS ? 'S' : 'N',
      lngDeg,
      lngMin,
      lngSec,
      lngDir: isLngW ? 'W' : 'E'
    };
  }

  function calculateDistanceAndBearing(lat1, lon1, lat2, lon2) {
    const R = 6371; // Earth radius in km
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLon = ((lon2 - lon1) * Math.PI) / 180;
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) * Math.sin(dLon / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    const km = R * c;
    const nm = km * 0.539957;

    const y = Math.sin(dLon) * Math.cos((lat2 * Math.PI) / 180);
    const x =
      Math.cos((lat1 * Math.PI) / 180) * Math.sin((lat2 * Math.PI) / 180) -
      Math.sin((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.cos(dLon);
    let brng = (Math.atan2(y, x) * 180) / Math.PI;
    brng = (brng + 360) % 360;

    return { km, nm, bearing: brng };
  }

  function createLkpDivIcon() {
    return L.divIcon({
      className: 'sar-marker-lkp-container',
      html: `
        <div style="position: relative; width: 44px; height: 52px; cursor: grab;">
          <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 3px; background: rgba(220, 38, 38, 0.95); color: #fff; font-size: 10px; font-weight: 800; padding: 1.5px 7px; border-radius: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.5); border: 1px solid rgba(255,255,255,0.8);">🚨 LKP</div>
          <svg width="44" height="52" viewBox="0 0 44 52">
            <circle cx="22" cy="18" r="17" fill="none" stroke="#dc2626" stroke-width="2" opacity="0.8">
              <animate attributeName="r" values="13;22;13" dur="2s" repeatCount="indefinite" />
              <animate attributeName="opacity" values="0.9;0.1;0.9" dur="2s" repeatCount="indefinite" />
            </circle>
            <path d="M22 48 C20 41, 7 27, 7 18 A15 15 0 1 1 37 18 C37 27, 24 41, 22 48 Z" fill="#dc2626" stroke="#ffffff" stroke-width="2.2" />
            <circle cx="22" cy="18" r="10" fill="#ffffff" />
            <circle cx="22" cy="18" r="7.5" fill="#dc2626" />
            <circle cx="22" cy="18" r="3" fill="#ffffff" />
            <line x1="12" y1="18" x2="32" y2="18" stroke="#ffffff" stroke-width="1.8" />
            <line x1="22" y1="8" x2="22" y2="28" stroke="#ffffff" stroke-width="1.8" />
          </svg>
        </div>
      `,
      iconSize: [44, 52],
      iconAnchor: [22, 50]
    });
  }

  function normalizeOriginIconUrl(url) {
    if (!url) return '';
    const u = String(url).trim();
    if (!u) return '';
    const idx = u.indexOf('/uploads/icons/');
    if (idx !== -1) {
      const origin = window.location.origin || '';
      return origin + u.substring(idx);
    }
    if (u.startsWith('/uploads/')) {
      const origin = window.location.origin || '';
      return origin + u;
    }
    return u;
  }

  function createOriginDivIcon(labelName, customUrl, pointType, category) {
    const shortName = labelName ? (labelName.length > 25 ? labelName.substring(0, 23) + '...' : labelName) : 'PANGKALAN';
    const normUrl = normalizeOriginIconUrl(customUrl || tacticalState.originIconUrl);

    let pinColor = '#ea580c';
    let badgeBg = 'rgba(234, 88, 12, 0.95)';
    const cat = (category || tacticalState.originCategory || '').toLowerCase();
    if (cat.includes('kantor')) {
      pinColor = '#c2410c';
      badgeBg = 'rgba(194, 65, 12, 0.95)';
    } else if (cat.includes('dermaga')) {
      pinColor = '#0284c7';
      badgeBg = 'rgba(2, 132, 199, 0.95)';
    } else if (cat.includes('siaga')) {
      pinColor = '#059669';
      badgeBg = 'rgba(5, 150, 105, 0.95)';
    }

    let innerSvgContent = '';
    if (normUrl) {
      const safeUrl = escapeHtml(normUrl);
      innerSvgContent = `
        <circle cx="19" cy="16" r="11" fill="#ffffff" />
        <image href="${safeUrl}" xlink:href="${safeUrl}" x="9" y="6" width="20" height="20" preserveAspectRatio="xMidYMid slice" />
      `;
    } else {
      innerSvgContent = `
        <circle cx="19" cy="16" r="10" fill="#ffffff" />
        <text x="19" y="16.5" font-family="'Segoe UI Emoji', Arial" font-size="11" text-anchor="middle" dominant-baseline="central">🏢</text>
      `;
    }

    return L.divIcon({
      className: 'sar-marker-origin-container',
      html: `
        <div style="position: relative; width: 38px; height: 46px; user-select: none;">
          <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 3px; background: ${badgeBg}; color: #fff; font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 6px; white-space: nowrap; box-shadow: 0 2px 6px rgba(0,0,0,0.6); border: 1px solid rgba(255,255,255,0.7); pointer-events: none; max-width: 200px; overflow: hidden; text-overflow: ellipsis; z-index: 10;">
            ${escapeHtml(shortName)}
          </div>
          <svg xmlns="http://www.w3.org/2000/svg" width="38" height="46" viewBox="0 0 38 46" style="display:block;">
            <g>
              <path d="M19 42 C17 36, 5 24, 5 16 A14 14 0 1 1 33 16 C33 24, 21 36, 19 42 Z" fill="${pinColor}" stroke="#ffffff" stroke-width="2" />
              ${innerSvgContent}
            </g>
          </svg>
        </div>
      `,
      iconSize: [38, 46],
      iconAnchor: [19, 44]
    });
  }

  function createWaypointDivIcon(idx) {
    return L.divIcon({
      className: 'sar-marker-wp-container',
      html: `
        <div style="position: relative; width: 32px; height: 38px; cursor: grab;">
          <div style="position: absolute; bottom: 100%; left: 50%; transform: translateX(-50%); margin-bottom: 2px; background: rgba(217, 119, 6, 0.95); color: #fff; font-size: 9px; font-weight: 800; padding: 1px 5px; border-radius: 4px; white-space: nowrap; box-shadow: 0 2px 4px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.8);">WP ${idx}</div>
          <svg width="32" height="38" viewBox="0 0 32 38">
            <path d="M16 35 C14 29, 5 20, 5 13 A11 11 0 1 1 27 13 C27 20, 18 29, 16 35 Z" fill="#d97706" stroke="#ffffff" stroke-width="2" />
            <circle cx="16" cy="13" r="8" fill="#ffffff" />
            <text x="16" y="16.5" font-family="Arial, sans-serif" font-size="10" font-weight="900" fill="#92400e" text-anchor="middle">${idx}</text>
          </svg>
        </div>
      `,
      iconSize: [32, 38],
      iconAnchor: [16, 36]
    });
  }

  function addStandardTileLayers(map) {
    const googleHybrid = L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
      maxNativeZoom: 20,
      maxZoom: 22,
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: 'Google Hybrid'
    });

    const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '© OpenStreetMap contributors'
    });

    const googleRoadmap = L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
      maxNativeZoom: 20,
      maxZoom: 22,
      subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
      attribution: 'Google Maps'
    });

    const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      maxNativeZoom: 18,
      maxZoom: 22,
      attribution: 'Esri World Imagery'
    });

    googleHybrid.addTo(map);

    L.control.layers({
      '🛰️ Google Satelit Hybrid': googleHybrid,
      '🌍 OpenStreetMap': osm,
      '🗺️ Google Roadmap': googleRoadmap,
      '🛰️ Esri Satelit': esriSatellite
    }, null, { position: 'topright' }).addTo(map);
  }

  function toggleTacticalMapMinimize(forceState) {
    const bodyEl = document.getElementById('tactical-map-body');
    const stripEl = document.getElementById('tactical-map-minimized-strip');
    const btnIcon = document.getElementById('btn-minimize-icon');
    const btnText = document.getElementById('btn-minimize-text');
    const btnToggle = document.getElementById('btn-toggle-map-minimize');

    if (!bodyEl || !stripEl) return;

    const isCurrentlyHidden = bodyEl.classList.contains('hidden');
    const willMinimize = (typeof forceState === 'boolean') ? forceState : !isCurrentlyHidden;

    if (willMinimize) {
      bodyEl.classList.add('hidden');
      stripEl.classList.remove('hidden');
      if (btnIcon) btnIcon.textContent = '🔼';
      if (btnText) btnText.textContent = 'Buka Peta';
      if (btnToggle) btnToggle.title = 'Buka / Tampilkan kembali peta taktis LKP';
      try {
        localStorage.setItem('qr_sar_map_minimized', 'true');
      } catch (e) {}
    } else {
      bodyEl.classList.remove('hidden');
      stripEl.classList.add('hidden');
      if (btnIcon) btnIcon.textContent = '🔽';
      if (btnText) btnText.textContent = 'Minimize';
      if (btnToggle) btnToggle.title = 'Sembunyikan / Perkecil tampilan peta untuk menghemat ruang';
      try {
        localStorage.setItem('qr_sar_map_minimized', 'false');
      } catch (e) {}

      if (tacticalState.inlineMap) {
        setTimeout(function () {
          tacticalState.inlineMap.invalidateSize();
          fitMapToBounds(tacticalState.inlineMap);
        }, 60);
      }
    }
  }

  function readOriginBaseFromSelect() {
    const originSelect = document.getElementById('origin_base_select');
    if (!originSelect) return;
    const opt = originSelect.options[originSelect.selectedIndex];
    if (!opt) return;

    const lat = parseFloat(opt.getAttribute('data-lat'));
    const lng = parseFloat(opt.getAttribute('data-lng'));
    const icon = opt.getAttribute('data-icon') || '';
    const pointType = opt.getAttribute('data-point-type') || 'custom_upload';
    const category = opt.getAttribute('data-category') || '';

    let name = opt.textContent.trim();
    if (name.includes(']')) {
      const endBracket = name.indexOf(']');
      if (endBracket !== -1) name = name.substring(endBracket + 1).trim();
    }

    if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
      tacticalState.originLat = lat;
      tacticalState.originLng = lng;
      tacticalState.originName = name;
      tacticalState.originIconUrl = icon;
      tacticalState.originPointType = pointType;
      tacticalState.originCategory = category;

      const latIn = document.getElementById('origin_lat');
      const lngIn = document.getElementById('origin_lng');
      const nameIn = document.getElementById('origin_name');
      const iconIn = document.getElementById('origin_icon');
      const typeIn = document.getElementById('origin_point_type');

      if (latIn) latIn.value = lat;
      if (lngIn) lngIn.value = lng;
      if (nameIn) nameIn.value = name;
      if (iconIn) iconIn.value = icon;
      if (typeIn) typeIn.value = pointType;
    }
  }

  function fitMapToBounds(mapInstance) {
    if (!mapInstance) return;
    const pts = [
      [tacticalState.originLat, tacticalState.originLng],
      [tacticalState.lkpLat, tacticalState.lkpLng],
      ...tacticalState.manualWaypoints.map(wp => [wp.lat, wp.lng])
    ];
    try {
      const bounds = L.latLngBounds(pts);
      mapInstance.fitBounds(bounds, { padding: [45, 45], maxZoom: 14 });
    } catch (e) {}
  }

  function onLkpMarkerDragged(lat, lng, isFinal = false) {
    tacticalState.isUpdatingFromMap = true;
    tacticalState.lkpLat = lat;
    tacticalState.lkpLng = lng;

    const dms = decimalToDmsParts(lat, lng);
    const latDegEl = document.getElementById('dms_lat_deg');
    const latMinEl = document.getElementById('dms_lat_min');
    const latSecEl = document.getElementById('dms_lat_sec');
    const latDirEl = document.getElementById('dms_lat_dir');

    const lngDegEl = document.getElementById('dms_lng_deg');
    const lngMinEl = document.getElementById('dms_lng_min');
    const lngSecEl = document.getElementById('dms_lng_sec');
    const lngDirEl = document.getElementById('dms_lng_dir');

    if (latDegEl) latDegEl.value = dms.latDeg;
    if (latMinEl) latMinEl.value = dms.latMin;
    if (latSecEl) latSecEl.value = dms.latSec;
    if (latDirEl) latDirEl.value = dms.latDir;

    if (lngDegEl) lngDegEl.value = dms.lngDeg;
    if (lngMinEl) lngMinEl.value = dms.lngMin;
    if (lngSecEl) lngSecEl.value = dms.lngSec;
    if (lngDirEl) lngDirEl.value = dms.lngDir;

    const latSecStr = dms.latSec % 1 === 0 ? padZero(Math.floor(dms.latSec), 2) : dms.latSec.toFixed(1);
    const lngSecStr = dms.lngSec % 1 === 0 ? padZero(Math.floor(dms.lngSec), 2) : dms.lngSec.toFixed(1);

    const formatted = `${padZero(dms.latDeg, 2)}° ${padZero(dms.latMin, 2)}' ${latSecStr}" ${dms.latDir} - ${padZero(dms.lngDeg, 3)}° ${padZero(dms.lngMin, 2)}' ${lngSecStr}" ${dms.lngDir}`;

    const coordInput = document.getElementById('koordinat');
    if (coordInput) coordInput.value = formatted;

    const previewEl = document.getElementById('dms_preview_text');
    if (previewEl) previewEl.textContent = formatted;

    const lkpLatIn = document.getElementById('lkp_lat');
    const lkpLngIn = document.getElementById('lkp_lng');
    if (lkpLatIn) lkpLatIn.value = lat;
    if (lkpLngIn) lkpLngIn.value = lng;

    const stripLkpEl = document.getElementById('strip-summary-lkp');
    if (stripLkpEl) stripLkpEl.textContent = formatted;

    redrawTacticalRoute();
    recalculateTacticalMetrics(true);

    if (isFinal) {
      setTimeout(function () {
        tacticalState.isUpdatingFromMap = false;
      }, 50);
    }
  }

  function syncDmsToTacticalMap() {
    if (tacticalState.isUpdatingFromMap) return;
    tacticalState.isUpdatingFromInput = true;

    const coord = getLkpDecimalCoordinates();
    if (!isNaN(coord.lat) && !isNaN(coord.lng) && (coord.lat !== 0 || coord.lng !== 0)) {
      tacticalState.lkpLat = coord.lat;
      tacticalState.lkpLng = coord.lng;

      const lkpLatIn = document.getElementById('lkp_lat');
      const lkpLngIn = document.getElementById('lkp_lng');
      if (lkpLatIn) lkpLatIn.value = coord.lat;
      if (lkpLngIn) lkpLngIn.value = coord.lng;

      if (tacticalState.inlineLayers.lkpMarker) {
        tacticalState.inlineLayers.lkpMarker.setLatLng([coord.lat, coord.lng]);
      }
      if (tacticalState.modalLayers.lkpMarker) {
        tacticalState.modalLayers.lkpMarker.setLatLng([coord.lat, coord.lng]);
      }

      redrawTacticalRoute();
      recalculateTacticalMetrics(true);
    }
    tacticalState.isUpdatingFromInput = false;
  }

  function setRoutingMode(mode, updateState = true) {
    if (updateState) {
      tacticalState.routingMode = mode;
      const hiddenInput = document.getElementById('routing_mode');
      if (hiddenInput) hiddenInput.value = mode;
    }

    const btnDirect = document.getElementById('btn-mode-direct');
    const btnManual = document.getElementById('btn-mode-manual');
    const manualActions = document.getElementById('manual-route-actions');
    const modeBadge = document.getElementById('route-mode-badge');
    const instructionText = document.getElementById('map-instruction-text');

    const modalBtnDirect = document.getElementById('map-modal-btn-mode-direct') || document.getElementById('modal-btn-mode-direct');
    const modalBtnManual = document.getElementById('map-modal-btn-mode-manual') || document.getElementById('modal-btn-mode-manual');
    const modalManualActions = document.getElementById('map-modal-manual-actions') || document.getElementById('modal-manual-actions');
    const modalModeBadge = document.getElementById('map-modal-route-mode-badge') || document.getElementById('modal-route-mode-badge');

    if (mode === 'direct') {
      if (btnDirect) {
        btnDirect.className = 'px-3 py-1.5 rounded-xl font-bold text-xs bg-orange-600 text-white shadow-xs transition-all cursor-pointer';
      }
      if (btnManual) {
        btnManual.className = 'px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition-all cursor-pointer';
      }
      if (modalBtnDirect) {
        modalBtnDirect.className = 'px-3.5 py-1.5 rounded-xl font-bold text-xs bg-orange-600 text-white shadow-xs transition-all cursor-pointer';
      }
      if (modalBtnManual) {
        modalBtnManual.className = 'px-3.5 py-1.5 rounded-xl font-bold text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition-all cursor-pointer';
      }

      if (manualActions) manualActions.classList.add('hidden');
      if (modalManualActions) modalManualActions.classList.add('hidden');

      if (modeBadge) {
        modeBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30';
        modeBadge.textContent = 'RUTE LANGSUNG (DIRECT)';
      }
      if (modalModeBadge) {
        modalModeBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-black bg-blue-500/15 text-blue-600 dark:text-blue-400 border border-blue-500/30';
        modalModeBadge.textContent = 'RUTE LANGSUNG (DIRECT)';
      }

      if (instructionText) {
        instructionText.textContent = '💡 Mode Langsung: Garis lurus dihitung dari Pangkalan ke Titik LKP. Geser marker LKP untuk memperbarui koordinat secara otomatis.';
      }
    } else {
      if (btnDirect) {
        btnDirect.className = 'px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition-all cursor-pointer';
      }
      if (btnManual) {
        btnManual.className = 'px-3 py-1.5 rounded-xl font-bold text-xs bg-amber-600 text-white shadow-xs transition-all cursor-pointer';
      }
      if (modalBtnDirect) {
        modalBtnDirect.className = 'px-3.5 py-1.5 rounded-xl font-bold text-xs bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200 transition-all cursor-pointer';
      }
      if (modalBtnManual) {
        modalBtnManual.className = 'px-3.5 py-1.5 rounded-xl font-bold text-xs bg-amber-600 text-white shadow-xs transition-all cursor-pointer';
      }

      if (manualActions) manualActions.classList.remove('hidden');
      if (modalManualActions) modalManualActions.classList.remove('hidden');

      if (modeBadge) {
        modeBadge.className = 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30';
        modeBadge.textContent = 'RUTE MULTI-LEG (MANUAL)';
      }
      if (modalModeBadge) {
        modalModeBadge.className = 'inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-black bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30';
        modalModeBadge.textContent = 'RUTE MULTI-LEG (MANUAL)';
      }

      if (instructionText) {
        instructionText.textContent = '💡 Mode Manual: Klik pada peta laut di antara pangkalan dan LKP untuk menambahkan waypoint menghindari pulau/daratan.';
      }
    }

    redrawTacticalRoute();
    recalculateTacticalMetrics(true);
  }

  function addManualWaypoint(latlng) {
    const wpIndex = tacticalState.manualWaypoints.length + 1;
    tacticalState.manualWaypoints.push({
      lat: latlng.lat,
      lng: latlng.lng,
      label: `WP ${wpIndex}`
    });
    redrawTacticalRoute();
    recalculateTacticalMetrics(true);
  }

  function undoLastWaypoint() {
    if (tacticalState.manualWaypoints.length > 0) {
      tacticalState.manualWaypoints.pop();
      redrawTacticalRoute();
      recalculateTacticalMetrics(true);
    }
  }

  function clearManualRouteWaypoints() {
    tacticalState.manualWaypoints = [];
    redrawTacticalRoute();
    recalculateTacticalMetrics(true);
  }

  function redrawTacticalRoute() {
    const wpCountPill = document.getElementById('waypoint-count-pill');
    const modalWpCountPill = document.getElementById('map-modal-waypoint-count-pill') || document.getElementById('modal-waypoint-count-pill');
    const countText = `${tacticalState.manualWaypoints.length} Waypoint`;

    if (wpCountPill) wpCountPill.textContent = countText;
    if (modalWpCountPill) modalWpCountPill.textContent = countText;

    let latlngs = [];
    if (tacticalState.routingMode === 'direct' || tacticalState.manualWaypoints.length === 0) {
      latlngs = [
        [tacticalState.originLat, tacticalState.originLng],
        [tacticalState.lkpLat, tacticalState.lkpLng]
      ];
    } else {
      latlngs = [
        [tacticalState.originLat, tacticalState.originLng],
        ...tacticalState.manualWaypoints.map(wp => [wp.lat, wp.lng]),
        [tacticalState.lkpLat, tacticalState.lkpLng]
      ];
    }

    if (tacticalState.inlineLayers.routeLine) {
      tacticalState.inlineLayers.routeLine.setLatLngs(latlngs);
    }
    if (tacticalState.modalLayers.routeLine) {
      tacticalState.modalLayers.routeLine.setLatLngs(latlngs);
    }

    // Waypoint markers management
    [tacticalState.inlineLayers, tacticalState.modalLayers].forEach(layersObj => {
      layersObj.waypointMarkers.forEach(m => m.remove());
      layersObj.waypointMarkers = [];
    });

    if (tacticalState.routingMode === 'manual') {
      tacticalState.manualWaypoints.forEach((wp, idx) => {
        const icon = createWaypointDivIcon(idx + 1);

        if (tacticalState.inlineMap) {
          const marker = L.marker([wp.lat, wp.lng], {
            icon: icon,
            draggable: true
          }).addTo(tacticalState.inlineMap);

          marker.on('drag', function (e) {
            const pos = e.target.getLatLng();
            wp.lat = pos.lat;
            wp.lng = pos.lng;
            redrawTacticalRoute();
            recalculateTacticalMetrics(false);
          });
          marker.on('dragend', function () {
            recalculateTacticalMetrics(true);
          });
          tacticalState.inlineLayers.waypointMarkers.push(marker);
        }

        if (tacticalState.modalMap) {
          const marker = L.marker([wp.lat, wp.lng], {
            icon: icon,
            draggable: true
          }).addTo(tacticalState.modalMap);

          marker.on('drag', function (e) {
            const pos = e.target.getLatLng();
            wp.lat = pos.lat;
            wp.lng = pos.lng;
            redrawTacticalRoute();
            recalculateTacticalMetrics(false);
          });
          marker.on('dragend', function () {
            recalculateTacticalMetrics(true);
          });
          tacticalState.modalLayers.waypointMarkers.push(marker);
        }
      });
    }
  }

  function handleSpeedPresetChange(sel) {
    if (!sel) return;
    const speedNumInput = document.getElementById('input_vessel_speed_num');
    const unitSelect = document.getElementById('select_speed_unit');
    const modalSpeedPreset = document.getElementById('modal_select_speed_preset');
    const modalSpeedNum = document.getElementById('modal_input_vessel_speed');

    if (modalSpeedPreset && modalSpeedPreset !== sel) {
      modalSpeedPreset.value = sel.value;
    }

    const opt = sel.options[sel.selectedIndex];
    const unit = opt ? (opt.getAttribute('data-unit') || 'knots') : 'knots';
    const speedVal = parseFloat(sel.value);

    if (!isNaN(speedVal) && speedVal > 0) {
      tacticalState.speedUnit = unit;
      if (unitSelect) unitSelect.value = unit;
      if (speedNumInput) speedNumInput.value = speedVal;
      if (modalSpeedNum) modalSpeedNum.value = speedVal;

      tacticalState.vesselSpeedKnots = unit === 'kmh' ? (speedVal / 1.852) : speedVal;

      const speedKnotsHidden = document.getElementById('vessel_speed_knots');
      if (speedKnotsHidden) speedKnotsHidden.value = Math.round(tacticalState.vesselSpeedKnots * 10) / 10;

      recalculateTacticalMetrics(true);
    }
  }

  function handleManualSpeedInput(val) {
    const num = parseFloat(val);
    if (!isNaN(num) && num > 0) {
      const unit = tacticalState.speedUnit || 'knots';
      tacticalState.vesselSpeedKnots = unit === 'kmh' ? (num / 1.852) : num;

      const speedKnotsHidden = document.getElementById('vessel_speed_knots');
      if (speedKnotsHidden) speedKnotsHidden.value = Math.round(tacticalState.vesselSpeedKnots * 10) / 10;

      const speedPresetSel = document.getElementById('select_speed_preset');
      if (speedPresetSel) {
        const matched = Array.from(speedPresetSel.options).find(o => {
          const oUnit = o.getAttribute('data-unit') || 'knots';
          return Math.abs(parseFloat(o.value) - num) < 0.1 && oUnit === unit;
        });
        speedPresetSel.value = matched ? matched.value : '';
      }

      recalculateTacticalMetrics(true);
    }
  }

  function handleSpeedUnitChange(unit) {
    const prevUnit = tacticalState.speedUnit;
    tacticalState.speedUnit = unit;
    const speedNumInput = document.getElementById('input_vessel_speed_num');

    if (speedNumInput && speedNumInput.value) {
      if (unit === 'kmh' && prevUnit === 'knots') {
        const kmh = tacticalState.vesselSpeedKnots * 1.852;
        speedNumInput.value = Math.round(kmh * 10) / 10;
      } else if (unit === 'knots' && prevUnit === 'kmh') {
        speedNumInput.value = Math.round(tacticalState.vesselSpeedKnots * 10) / 10;
      }
    }

    const speedPresetSel = document.getElementById('select_speed_preset');
    if (speedPresetSel && speedNumInput) {
      const curVal = parseFloat(speedNumInput.value);
      const matched = Array.from(speedPresetSel.options).find(o => {
        const oUnit = o.getAttribute('data-unit') || 'knots';
        return Math.abs(parseFloat(o.value) - curVal) < 0.1 && oUnit === unit;
      });
      speedPresetSel.value = matched ? matched.value : '';
    }

    recalculateTacticalMetrics(true);
  }

  function recalculateTacticalMetrics(autoApply = false) {
    const fullPoints = [
      { lat: tacticalState.originLat, lng: tacticalState.originLng, label: tacticalState.originName },
      ...tacticalState.manualWaypoints,
      { lat: tacticalState.lkpLat, lng: tacticalState.lkpLng, label: 'LKP' }
    ];

    let totalKm = 0;
    for (let i = 0; i < fullPoints.length - 1; i++) {
      const p1 = fullPoints[i];
      const p2 = fullPoints[i + 1];
      const leg = calculateDistanceAndBearing(p1.lat, p1.lng, p2.lat, p2.lng);
      totalKm += leg.km;
    }

    const totalNm = totalKm * 0.539957;

    const directLkp = calculateDistanceAndBearing(tacticalState.originLat, tacticalState.originLng, tacticalState.lkpLat, tacticalState.lkpLng);
    const departureRadial = Math.round(directLkp.bearing);

    tacticalState.tacticalDistanceNm = Math.round(totalNm * 10) / 10;
    tacticalState.tacticalDistanceKm = Math.round(totalKm * 10) / 10;
    tacticalState.tacticalRadial = departureRadial;

    const speed = Math.max(0.1, tacticalState.vesselSpeedKnots || 25.0);
    const etaMinutes = Math.round((totalNm / speed) * 60);
    tacticalState.tacticalEtaMinutes = etaMinutes;

    let etaText = '';
    const hours = Math.floor(etaMinutes / 60);
    const mins = etaMinutes % 60;
    if (hours > 0) {
      etaText = `${hours} Jam ${mins} Menit`;
    } else {
      etaText = `${mins} Menit`;
    }
    tacticalState.etaStr = etaText;

    const hudDistance = document.getElementById('hud_distance');
    const hudRadial = document.getElementById('hud_radial');
    const hudSpeed = document.getElementById('hud_speed');
    const hudEta = document.getElementById('hud_eta');

    const modalHudDistance = document.getElementById('modal_hud_distance');
    const modalHudRadial = document.getElementById('modal_hud_radial');
    const modalHudSpeed = document.getElementById('modal_hud_speed');
    const modalHudEta = document.getElementById('modal_hud_eta');

    const distDisplay = `${tacticalState.tacticalDistanceNm} NM (${tacticalState.tacticalDistanceKm} KM)`;
    const radDisplay = `${departureRadial}° (True)`;
    const speedDisplay = `${Math.round(tacticalState.vesselSpeedKnots * 10) / 10} Knots`;

    if (hudDistance) hudDistance.textContent = distDisplay;
    if (hudRadial) hudRadial.textContent = radDisplay;
    if (hudSpeed) hudSpeed.textContent = speedDisplay;
    if (hudEta) hudEta.textContent = etaText;

    if (modalHudDistance) modalHudDistance.textContent = distDisplay;
    if (modalHudRadial) modalHudRadial.textContent = radDisplay;
    if (modalHudSpeed) modalHudSpeed.textContent = speedDisplay;
    if (modalHudEta) modalHudEta.textContent = etaText;

    const stripDist = document.getElementById('strip-summary-distance');
    const stripRadial = document.getElementById('strip-summary-radial');
    const stripEta = document.getElementById('strip-summary-eta');

    if (stripDist) stripDist.textContent = `${tacticalState.tacticalDistanceNm} NM`;
    if (stripRadial) stripRadial.textContent = `${departureRadial}°`;
    if (stripEta) stripEta.textContent = etaText;

    const routeDataHidden = document.getElementById('tactical_route_data');
    if (routeDataHidden) {
      routeDataHidden.value = JSON.stringify({
        routingMode: tacticalState.routingMode,
        origin: { lat: tacticalState.originLat, lng: tacticalState.originLng, name: tacticalState.originName },
        lkp: { lat: tacticalState.lkpLat, lng: tacticalState.lkpLng },
        waypoints: tacticalState.manualWaypoints,
        totalNm: tacticalState.tacticalDistanceNm,
        totalKm: tacticalState.tacticalDistanceKm,
        radial: tacticalState.tacticalRadial,
        speedKnots: tacticalState.vesselSpeedKnots,
        etaMinutes: tacticalState.tacticalEtaMinutes,
        etaStr: tacticalState.etaStr,
        points: fullPoints
      });
    }

    if (autoApply) {
      applyTacticalRouteToForm();
    }
  }

  function applyTacticalRouteToForm() {
    const jarakAngkaIn = document.getElementById('jarak_angka');
    const jarakUnitIn = document.getElementById('jarak_unit');
    const radialAngkaIn = document.getElementById('radial_angka');
    const etaLokasiIn = document.getElementById('eta_lokasi');

    const curUnit = (jarakUnitIn?.value || 'NM').toUpperCase();
    if (jarakAngkaIn) {
      jarakAngkaIn.value = curUnit === 'KM' ? tacticalState.tacticalDistanceKm : tacticalState.tacticalDistanceNm;
    }
    if (radialAngkaIn) {
      radialAngkaIn.value = tacticalState.tacticalRadial;
    }
    if (etaLokasiIn) {
      etaLokasiIn.value = tacticalState.etaStr;
    }

    syncJarakDanRadial();

    if (typeof window.updateLiveWa === 'function') {
      window.updateLiveWa();
    }
  }

  function handleJarakUnitChange(newUnit) {
    const inputEl = document.getElementById('jarak_angka');
    const unitNormalized = (newUnit || 'NM').toUpperCase();
    let currentUnit = (document.getElementById('jarak_unit')?.value || 'NM').toUpperCase();

    if (inputEl && inputEl.value !== '') {
      const val = parseFloat(inputEl.value);
      if (!isNaN(val) && val > 0) {
        if (unitNormalized === 'KM' && currentUnit === 'NM') {
          const converted = Math.round(val * 1.852 * 10) / 10;
          inputEl.value = converted.toString();
        } else if (unitNormalized === 'NM' && currentUnit === 'KM') {
          const converted = Math.round((val / 1.852) * 10) / 10;
          inputEl.value = converted.toString();
        }
      }
    }
    syncJarakDanRadial();
  }

  function syncJarakDanRadial() {
    const numJarak = document.getElementById('jarak_angka')?.value?.trim();
    const unitJarak = document.getElementById('jarak_unit')?.value || 'NM';
    const hiddenJarak = document.getElementById('jarak');
    if (hiddenJarak) {
      hiddenJarak.value = numJarak ? `${numJarak} ${unitJarak}` : '';
    }

    const numRadial = document.getElementById('radial_angka')?.value?.trim();
    const hiddenRadial = document.getElementById('radial');
    if (hiddenRadial) {
      hiddenRadial.value = numRadial ? `${numRadial}°` : '';
    }

    if (typeof window.updateLiveWa === 'function') {
      window.updateLiveWa();
    }
  }

  // Export to namespace
  window.QRSAR.OperasiFormMap = {
    state: tacticalState,
    init: function () {
      readOriginBaseFromSelect();
      const initialCoord = getLkpDecimalCoordinates();
      if (initialCoord && !isNaN(initialCoord.lat) && !isNaN(initialCoord.lng) && (initialCoord.lat !== 0 || initialCoord.lng !== 0)) {
        tacticalState.lkpLat = initialCoord.lat;
        tacticalState.lkpLng = initialCoord.lng;
      }
    },
    getLkpDecimalCoordinates,
    decimalToDmsParts,
    calculateDistanceAndBearing,
    createLkpDivIcon,
    createOriginDivIcon,
    createWaypointDivIcon,
    addStandardTileLayers,
    toggleTacticalMapMinimize,
    readOriginBaseFromSelect,
    fitMapToBounds,
    onLkpMarkerDragged,
    syncDmsToTacticalMap,
    setRoutingMode,
    addManualWaypoint,
    undoLastWaypoint,
    clearManualRouteWaypoints,
    redrawTacticalRoute,
    handleSpeedPresetChange,
    handleManualSpeedInput,
    handleSpeedUnitChange,
    recalculateTacticalMetrics,
    applyTacticalRouteToForm,
    handleJarakUnitChange,
    syncJarakDanRadial
  };
})();
