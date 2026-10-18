/**
 * QR-SAR - Common Frontend Utilities
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Centralized, shared functions for HTML sanitization, coordinate DMS parsing/formatting,
 * and UI clipboard interaction.
 */

(function (global) {
  'use strict';

  const QrSarUtils = {
    /**
     * Escape special HTML characters to prevent XSS.
     * @param {*} str
     * @returns {string}
     */
    escapeHtml(str) {
      if (str === null || str === undefined) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    },

    /**
     * Pad a number with leading zeroes.
     * @param {number|string} num
     * @param {number} size
     * @returns {string}
     */
    padZero(num, size = 2) {
      return String(num).padStart(size, '0');
    },

    /**
     * Convert decimal coordinate to DMS parts object.
     * @param {number} val Decimal coordinate
     * @param {boolean} isLat True if latitude, false if longitude
     * @returns {{ deg: number, min: number, sec: number, dir: string, degPad: string, minPad: string, secStr: string }}
     */
    decimalToDms(val, isLat) {
      const numVal = Number(val) || 0;
      const abs = Math.abs(numVal);
      let deg = Math.floor(abs);
      const minFloat = (abs - deg) * 60;
      let min = Math.floor(minFloat);
      let sec = Math.round((minFloat - min) * 60 * 10) / 10;
      if (sec >= 60.0) {
        sec = 0.0;
        min += 1;
        if (min >= 60) {
          min = 0;
          deg += 1;
        }
      }

      // Direction: standard Indonesian Basarnas (U/S for Lat, T/B for Lng)
      const dir = isLat ? (numVal >= 0 ? 'U' : 'S') : (numVal >= 0 ? 'T' : 'B');
      const degPad = String(deg).padStart(isLat ? 2 : 3, '0');
      const minPad = String(min).padStart(2, '0');
      const secStr = (sec % 1 === 0 ? Math.floor(sec).toString().padStart(2, '0') : sec.toFixed(1)).padStart(2, '0');

      return { deg, min, sec, dir, degPad, minPad, secStr };
    },

    /**
     * Format a single coordinate part into standard Basarnas DMS format:
     * e.g. 04° 00' 52.0" S or 122° 33' 09.0" T
     * @param {number} val
     * @param {boolean} isLat
     * @returns {string}
     */
    formatDmsPart(val, isLat) {
      const p = this.decimalToDms(val, isLat);
      return `${p.degPad}° ${p.minPad}' ${p.secStr}" ${p.dir}`;
    },

    /**
     * Format a pair of coordinates (lat, lng) into standard Basarnas DMS pair:
     * e.g. 04° 00' 52.0" S - 122° 33' 09.0" T
     * @param {number} lat
     * @param {number} lng
     * @returns {string}
     */
    formatDmsPair(lat, lng) {
      return `${this.formatDmsPart(lat, true)} - ${this.formatDmsPart(lng, false)}`;
    },

    /**
     * Convert DMS parts to signed decimal degrees.
     * @param {number} deg
     * @param {number} min
     * @param {number} sec
     * @param {string} dir Direction letter ('U'/'N', 'S', 'T'/'E', 'B'/'W')
     * @returns {number}
     */
    dmsToDecimal(deg, min, sec, dir) {
      const d = Math.abs(Number(deg) || 0);
      const m = Math.abs(Number(min) || 0);
      const s = Math.abs(Number(sec) || 0);
      let decimal = d + (m / 60) + (s / 3600);
      const dirUpper = String(dir || '').trim().toUpperCase();
      if (dirUpper === 'S' || dirUpper === 'B' || dirUpper === 'W') {
        decimal = -decimal;
      }
      return decimal;
    },

    /**
     * Parse arbitrary coordinate string (DMS or DD) into { lat, lng } numbers.
     * Supports formats like:
     * - "04° 00' 52\" S - 122° 33' 09\" E"
     * - "-4.014444, 122.552500"
     * @param {string} str
     * @returns {{ lat: number, lng: number }|null}
     */
    parseCoordinates(str) {
      if (!str || typeof str !== 'string') return null;
      const clean = str.trim();

      // Check decimal comma or space separated: "-4.084194, 122.428389"
      const ddRegex = /^(-?\d+(?:\.\d+)?)[,\s]+(-?\d+(?:\.\d+)?)$/;
      const ddMatch = clean.match(ddRegex);
      if (ddMatch) {
        return { lat: parseFloat(ddMatch[1]), lng: parseFloat(ddMatch[2]) };
      }

      // Check DMS pair
      const dmsRegex = /(\d+)\s*°\s*(\d+)\s*['’]\s*([\d.]+)\s*["”]?\s*([UNSBTEW])\s*[-–,\s]+\s*(\d+)\s*°\s*(\d+)\s*['’]\s*([\d.]+)\s*["”]?\s*([UNSBTEW])/i;
      const m = clean.match(dmsRegex);
      if (m) {
        const lat = this.dmsToDecimal(m[1], m[2], m[3], m[4]);
        const lng = this.dmsToDecimal(m[5], m[6], m[7], m[8]);
        return { lat, lng };
      }

      return null;
    },

    /**
     * Safe clipboard copy helper with notification feedback.
     * @param {string} text
     * @param {string} [msg]
     * @returns {Promise<boolean>}
     */
    async copyToClipboard(text, msg = 'Teks Berhasil Disalin ke Clipboard!') {
      try {
        if (navigator.clipboard && window.isSecureContext) {
          await navigator.clipboard.writeText(text);
        } else {
          const ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.focus();
          ta.select();
          document.execCommand('copy');
          document.body.removeChild(ta);
        }
        if (typeof showToast === 'function') {
          showToast(msg, 'success');
        }
        return true;
      } catch (err) {
        console.error('Clipboard copy failed:', err);
        return false;
      }
    },

    /**
     * Berita Radio SAR (BRA) Utilities.
     */
    Bra: {
      /**
       * Generate repeating 3-letter code (0 -> AAA, 1 -> BBB, ... 25 -> ZZZ, 26 -> AAA).
       * @param {number} idx
       * @returns {string}
       */
      getSectionLetter(idx) {
        const char = String.fromCharCode(65 + (Math.abs(Number(idx) || 0) % 26));
        return char.repeat(3);
      },

      /**
       * Format Date to Military Time Word (TW) in WITA (UTC+8): e.g. "1018 1055 H".
       * @param {Date|string|null} [dateObj]
       * @returns {string}
       */
      getMilitaryTW(dateObj) {
        const d = dateObj instanceof Date ? dateObj : (dateObj ? new Date(dateObj) : new Date());
        const utc = d.getTime() + d.getTimezoneOffset() * 60000;
        const wita = new Date(utc + 8 * 3600000);
        const mm = String(wita.getMonth() + 1).padStart(2, '0');
        const dd = String(wita.getDate()).padStart(2, '0');
        const hh = String(wita.getHours()).padStart(2, '0');
        const min = String(wita.getMinutes()).padStart(2, '0');
        return `${mm}${dd} ${hh}${min} H`;
      },

      /**
       * Count words in telegram string (Senkom Basarnas standard).
       * @param {string} text
       * @returns {number}
       */
      countWords(text) {
        if (!text || typeof text !== 'string') return 0;
        const trimmed = text.trim();
        if (!trimmed) return 0;
        const words = trimmed.split(/\s+/).filter(Boolean);
        return words.length;
      }
    }
  };

  // Export to global window namespace
  global.QrSarUtils = QrSarUtils;

  // Polyfill / Export global aliases for backwards compatibility with inline onclick and existing scripts
  if (typeof global.escapeHtml !== 'function') {
    global.escapeHtml = QrSarUtils.escapeHtml.bind(QrSarUtils);
  }
  if (typeof global.formatDmsPair !== 'function') {
    global.formatDmsPair = QrSarUtils.formatDmsPair.bind(QrSarUtils);
  }
  if (typeof global.copyToClipboard !== 'function') {
    global.copyToClipboard = QrSarUtils.copyToClipboard.bind(QrSarUtils);
  }

})(typeof window !== 'undefined' ? window : this);
