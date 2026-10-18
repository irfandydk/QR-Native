/**
 * QR-SAR - Tactical Maritime GIS SVG Markers Module
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Provides standardized Basarnas tactical SVG icons, pulses, and DOM marker badges.
 */

(function (global) {
  'use strict';

  global.QrSarGis = global.QrSarGis || {};

  const PRESET_ICONS = [
    // --- KEJADIAN & BENCANA ---
    { type: 'lkp', label: 'LKP / Titik Kejadian', color: '#dc2626', emoji: '🚨', group: 'kejadian' },
    { type: 'orang_hilang', label: 'Orang Hilang / Survivor', color: '#ef4444', emoji: '👤', group: 'kejadian' },
    { type: 'tenggelam', label: 'Korban Tenggelam / Laka Air', color: '#0284c7', emoji: '🏊', group: 'kejadian' },
    { type: 'kecelakaan_kapal', label: 'Laka Kapal / Karam', color: '#b91c1c', emoji: '🚢', group: 'kejadian' },
    { type: 'perahu', label: 'Perahu Terbalik / Nelayan', color: '#06b6d4', emoji: '⛵', group: 'kejadian' },
    { type: 'kecelakaan_pesawat', label: 'Laka Pesawat / Jatuh', color: '#7c3aed', emoji: '✈️', group: 'kejadian' },
    { type: 'laka_darat', label: 'Laka Transportasi Darat', color: '#d97706', emoji: '🚗', group: 'kejadian' },
    { type: 'kmm', label: 'KMM / Bahaya Jiwa', color: '#ea580c', emoji: '🧗', group: 'kejadian' },
    { type: 'banjir', label: 'Banjir / Luapan Air', color: '#2563eb', emoji: '🌊', group: 'kejadian' },
    { type: 'longsor', label: 'Tanah Longsor', color: '#78350f', emoji: '⛰️', group: 'kejadian' },
    { type: 'gempa', label: 'Gempa / Runtuhan', color: '#854d0e', emoji: '🏚️', group: 'kejadian' },
    { type: 'kebakaran', label: 'Kebakaran / Karhutla', color: '#ea580c', emoji: '🔥', group: 'kejadian' },
    { type: 'gunung_api', label: 'Erupsi Gunung Berapi', color: '#991b1b', emoji: '🌋', group: 'kejadian' },
    { type: 'badai', label: 'Cuaca Buruk / Badai', color: '#475569', emoji: '🌪️', group: 'kejadian' },
    { type: 'hewan_buas', label: 'Serangan Satwa / Buaya', color: '#15803d', emoji: '🐊', group: 'kejadian' },
    { type: 'medis_gawat', label: 'Korban Medis Kritis', color: '#be123c', emoji: '🩸', group: 'kejadian' },
    { type: 'bencana', label: 'Bencana Alam / Hazard', color: '#d97706', emoji: '⚠️', group: 'kejadian' },

    // --- TEMPAT & FASILITAS ---
    { type: 'kantor', label: 'Kantor / Pangkalan SAR', color: '#1e3a8a', emoji: '🏢', group: 'tempat' },
    { type: 'pos_sar', label: 'Pos / Stasiun SAR', color: '#ea580c', emoji: '📻', group: 'tempat' },
    { type: 'posko_sar', label: 'Posko Taktis Lapangan', color: '#059669', emoji: '🏕️', group: 'tempat' },
    { type: 'tenda', label: 'Tenda Siaga / Pengungsi', color: '#0d9488', emoji: '⛺', group: 'tempat' },
    { type: 'rumah_sakit', label: 'Rumah Sakit / Faskes', color: '#e11d48', emoji: '🏥', group: 'tempat' },
    { type: 'pelabuhan', label: 'Pelabuhan / Dermaga', color: '#0284c7', emoji: '⚓', group: 'tempat' },
    { type: 'bandara', label: 'Bandara / Airstrip', color: '#4338ca', emoji: '🛫', group: 'tempat' },
    { type: 'helipad', label: 'Helipad / LZ Evakuasi', color: '#ca8a04', emoji: '🚁', group: 'tempat' },
    { type: 'mercusuar', label: 'Mercusuar / Tower Pantau', color: '#334155', emoji: '🗼', group: 'tempat' },
    { type: 'spbu_bbm', label: 'Depot BBM / Pengisian', color: '#d97706', emoji: '⛽', group: 'tempat' },
    { type: 'rumah', label: 'Rumah / Titik Hunian', color: '#16a34a', emoji: '🏠', group: 'tempat' },
    { type: 'pantai', label: 'Pantai / Area Pesisir', color: '#0891b2', emoji: '🏖️', group: 'tempat' },
    { type: 'pulau', label: 'Pulau / Terumbu Karang', color: '#059669', emoji: '🏝️', group: 'tempat' },
    { type: 'hutan', label: 'Hutan / Pegunungan', color: '#166534', emoji: '🌲', group: 'tempat' },
    { type: 'jembatan', label: 'Jembatan / Akses Utama', color: '#64748b', emoji: '🌉', group: 'tempat' },

    // --- ARMADA, ALUT & TIM SAR ---
    { type: 'kapal', label: 'Kapal SAR KN / KRI', color: '#0284c7', emoji: '🚢', group: 'alut' },
    { type: 'rib', label: 'RIB Basarnas', color: '#f97316', emoji: '🚤', group: 'alut' },
    { type: 'perahu_karet', label: 'Perahu Karet / Raft', color: '#dc2626', emoji: '🛶', group: 'alut' },
    { type: 'speedboat', label: 'Speedboat / Patroli', color: '#0ea5e9', emoji: '🛥️', group: 'alut' },
    { type: 'helikopter', label: 'Helikopter SAR', color: '#ea580c', emoji: '🚁', group: 'alut' },
    { type: 'pesawat', label: 'Pesawat Patroli Udara', color: '#7c3aed', emoji: '🛩️', group: 'alut' },
    { type: 'rescue_car', label: 'Rescue Car / D-Max', color: '#ea580c', emoji: '🚙', group: 'alut' },
    { type: 'rescue_truck', label: 'Truk Angkut / Truk SAR', color: '#475569', emoji: '🚛', group: 'alut' },
    { type: 'ambulans', label: 'Ambulans Evakuasi Medis', color: '#dc2626', emoji: '🚑', group: 'alut' },
    { type: 'motor_trail', label: 'Motor Trail Rescuer', color: '#c2410c', emoji: '🏍️', group: 'alut' },
    { type: 'penyelam', label: 'Tim Selam / Under-Water', color: '#0369a1', emoji: '🤿', group: 'alut' },
    { type: 'drone', label: 'Drone Pemantau Udara', color: '#059669', emoji: '🛸', group: 'alut' },
    { type: 'tim_rescue', label: 'Tim Rescue Darat', color: '#d97706', emoji: '👷', group: 'alut' },
    { type: 'k9', label: 'Unit Anjing Pelacak K-9', color: '#78350f', emoji: '🐕', group: 'alut' },

    // --- KORBAN & SURVIVOR ---
    { type: 'korban_selamat', label: 'Korban Selamat (S)', color: '#16a34a', emoji: '🟢', group: 'korban' },
    { type: 'korban_md', label: 'Korban Meninggal (MD)', color: '#0f172a', emoji: '⚫', group: 'korban' },
    { type: 'korban_hilang', label: 'Korban Hilang / DP', color: '#dc2626', emoji: '🔴', group: 'korban' },
    { type: 'korban_luka', label: 'Korban Luka Ringan/Berat', color: '#ca8a04', emoji: '🟡', group: 'korban' },

    // --- PETUNJUK & TAKTIS UMUM ---
    { type: 'alphabet', label: 'Abjad (A-Z Otomatis)', color: '#ea580c', emoji: '🔤', group: 'taktis' },
    { type: 'pin_umum', label: 'Pin Lokasi Standar', color: '#ea580c', emoji: '📍', group: 'taktis' },
    { type: 'waypoint', label: 'Titik Waypoint Rute', color: '#0284c7', emoji: '💠', group: 'taktis' },
    { type: 'target', label: 'Target / Objek Temuan', color: '#e11d48', emoji: '🎯', group: 'taktis' },
    { type: 'debris', label: 'Puing / Serpihan / Debris', color: '#64748b', emoji: '📦', group: 'taktis' },
    { type: 'radio', label: 'Stasiun Radio Senkom', color: '#7c3aed', emoji: '📡', group: 'taktis' }
  ];

  function safeEscape(str) {
    if (global.QrSarUtils && typeof global.QrSarUtils.escapeHtml === 'function') {
      return global.QrSarUtils.escapeHtml(str);
    }
    return String(str || '').replace(/[&<>"']/g, '');
  }

  const Markers = {
    PRESET_ICONS,

    createPointSvgHtml(type, color, isEditing, customUrl, labelNumber) {
      const width = isEditing ? 46 : 38;
      const height = isEditing ? 54 : 46;
      const cx = width / 2;

      let innerContent = '';
      const normalizedUrl = customUrl ? customUrl.trim() : null;

      if (normalizedUrl) {
        const safeUrl = safeEscape(normalizedUrl);
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <image href="${safeUrl}" xlink:href="${safeUrl}" x="${cx - 10}" y="6" width="20" height="20" preserveAspectRatio="xMidYMid slice" />
        `;
      } else if (type === 'alphabet') {
        const char = (labelNumber || 'A').toUpperCase();
        innerContent = `
          <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
          <text x="${cx}" y="20.5" font-family="Arial, system-ui, sans-serif" font-size="12" font-weight="900" fill="${color}" text-anchor="middle">${safeEscape(char)}</text>
        `;
      } else if (type === 'lkp' || type === 'target') {
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <circle cx="${cx}" cy="16" r="9" fill="${color}" />
          <circle cx="${cx}" cy="16" r="4" fill="#ffffff" />
          <line x1="${cx - 10}" y1="16" x2="${cx + 10}" y2="16" stroke="#ffffff" stroke-width="1.8" />
          <line x1="${cx}" y1="6" x2="${cx}" y2="26" stroke="#ffffff" stroke-width="1.8" />
        `;
      } else {
        const preset = PRESET_ICONS.find((p) => p.type === type);
        const emoji = preset ? preset.emoji : '📍';
        innerContent = `
          <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
          <text x="${cx}" y="16.5" font-family="'Segoe UI Emoji', 'Apple Color Emoji', 'Noto Color Emoji', Arial, sans-serif" font-size="12" text-anchor="middle" dominant-baseline="central">${emoji}</text>
        `;
      }

      const haloRing = isEditing
        ? `<circle cx="${cx}" cy="16" r="17" fill="none" stroke="#facc15" stroke-width="3" stroke-dasharray="3,3" />`
        : (type === 'lkp' || type === 'target' || type === 'medis_gawat' || type === 'bencana')
          ? `<circle cx="${cx}" cy="16" r="18" fill="none" stroke="${color}" stroke-width="1.5" stroke-opacity="0.6">
            <animate attributeName="r" values="14;22;14" dur="2s" repeatCount="indefinite" />
            <animate attributeName="stroke-opacity" values="0.8;0.1;0.8" dur="2s" repeatCount="indefinite" />
           </circle>`
          : '';

      return `
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="display:block;">
          <g>
            ${haloRing}
            <path d="M${cx} ${height - 4} C${cx - 2} ${height - 10}, ${cx - 14} 24, ${cx - 14} 16 A14 14 0 1 1 ${cx + 14} 16 C${cx + 14} 24, ${cx + 2} ${height - 10}, ${cx} ${height - 4} Z" fill="${color}" stroke="#ffffff" stroke-width="2" />
            ${innerContent}
          </g>
        </svg>
      `;
    },

    createMarkerHtml(point, isEditing = false) {
      const pType = point.pointType || point.type || 'pin_umum';
      const preset = PRESET_ICONS.find((p) => p.type === pType);
      const color = point.customColor || (preset ? preset.color : '#ea580c');
      const label = point.label !== undefined && point.label !== null ? String(point.label).trim() : '';
      const width = isEditing ? 46 : 38;
      const height = isEditing ? 54 : 46;
      const svg = this.createPointSvgHtml(pType, color, isEditing, point.customIconUrl, point.alphabetChar);

      const labelBadge = label
        ? `
          <div style="
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 3px;
            background: rgba(15, 23, 42, 0.95);
            color: #f8fafc;
            font-size: 11px;
            font-weight: 800;
            padding: 2px 7px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
            white-space: nowrap;
            pointer-events: none;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
            z-index: 10;
          ">${safeEscape(label)}</div>
        `
        : '';

      return `
        <div class="sar-marker-root" style="position: relative; width: ${width}px; height: ${height}px; user-select: none; pointer-events: auto;">
          ${labelBadge}
          ${svg}
        </div>
      `;
    },

    createVertexBadgeHtml(letter, color = '#ea580c', isDragging = false) {
      const size = isDragging ? 36 : 30;
      return `
        <div style="display: block; width: ${size}px; height: ${size}px; user-select: none;">
          <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
            <circle cx="${size / 2}" cy="${size / 2}" r="${size / 2 - 2}" fill="${color}" stroke="#ffffff" stroke-width="2.5" />
            <text x="${size / 2}" y="${size / 2 + 4.5}" font-family="Arial, sans-serif" font-size="12" font-weight="900" fill="#ffffff" text-anchor="middle">${letter}</text>
          </svg>
        </div>
      `;
    },

    createPatternCornerHtml(type, color = '#0284c7', customLetter = '') {
      const isStart = type === 'start';
      const isEnd = type === 'end';
      const fillColor = isStart ? '#16a34a' : isEnd ? '#dc2626' : color;
      const letter = customLetter ? customLetter : (isStart ? 'S' : isEnd ? 'E' : '');
      const isLong = letter.length >= 2;
      const size = isLong ? 26 : 24;

      return `
        <div style="display: block; width: ${size}px; height: ${size}px; user-select: none;">
          <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
            <circle cx="${size / 2}" cy="${size / 2}" r="${size / 2 - 2}" fill="${fillColor}" stroke="#ffffff" stroke-width="2" />
            <text x="${size / 2}" y="${size / 2 + 3.5}" font-family="Arial, sans-serif" font-size="${isLong ? '9' : '11'}" font-weight="900" fill="#ffffff" text-anchor="middle">${letter}</text>
          </svg>
        </div>
      `;
    }
  };

  global.QrSarGis.Markers = Markers;

})(typeof window !== 'undefined' ? window : this);
