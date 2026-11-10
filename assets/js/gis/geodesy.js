/**
 * QR-SAR - Tactical Maritime GIS Geodetic & Spatial Math Module
 * Kantor Pencarian dan Pertolongan (KPP) Kelas A Kendari - Basarnas
 *
 * Provides spherical trigonometry, Haversine nautical distance, bearing azimuth,
 * destination point calculation, and geodesic polygon area according to IAMSAR & Basarnas standards.
 */

(function (global) {
  'use strict';

  global.QrSarGis = global.QrSarGis || {};

  const Geodesy = {
    /**
     * Convert decimal latitude and longitude to Basarnas DMS string.
     * @param {number} lat
     * @param {number} lng
     * @returns {string}
     */
    decimalToDms(lat, lng) {
      if (global.QrSarUtils && typeof global.QrSarUtils.formatDmsPair === 'function') {
        return global.QrSarUtils.formatDmsPair(lat, lng);
      }
      // Fallback bila common.js belum dimuat (gis.php memuat geodesy.js lebih dulu): format sama dengan QrSarUtils & GeoHelper PHP
      function formatDmsPart(val, isLat) {
        const dir = isLat ? (val < 0 ? 'S' : 'N') : (val < 0 ? 'W' : 'E');
        const absVal = Math.abs(val);
        let d = Math.floor(absVal);
        const mFloat = (absVal - d) * 60;
        let m = Math.floor(mFloat);
        let s = Math.round((mFloat - m) * 60 * 10) / 10;
        if (s >= 60) {
          s = 0;
          m += 1;
          if (m >= 60) {
            m = 0;
            d += 1;
          }
        }
        const padD = String(d).padStart(isLat ? 2 : 3, '0');
        return `${padD}° ${String(m).padStart(2, '0')}' ${s.toFixed(1).padStart(4, '0')}" ${dir}`;
      }
      return `${formatDmsPart(lat, true)} - ${formatDmsPart(lng, false)}`;
    },

    /**
     * Robust coordinate parser supporting standard DMS, DDM, Signed Decimal, and Indonesian Cardinal (U/S/T/B).
     * @param {string} text
     * @returns {{ lat: number, lng: number }|null}
     */
    parseCoordinatesString(text) {
      if (!text || typeof text !== 'string') return null;
      if (global.QrSarUtils && typeof global.QrSarUtils.parseCoordinatesString === 'function') {
        const res = global.QrSarUtils.parseCoordinatesString(text);
        if (res) return res;
      }

      const clean = text.trim();
      if (!clean) return null;

      // Normalise Indonesian cardinal letters (LS -> S, LU -> N, BT -> E, BB -> W)
      const norm = clean
        .replace(/\bLS\b/gi, 'S')
        .replace(/\bLU\b/gi, 'N')
        .replace(/\bBT\b/gi, 'E')
        .replace(/\bBB\b/gi, 'W');

      // 1. Full DMS Regex: e.g. 04° 02' 16" S, 122° 29' 12" E
      const dmsRegex = /(\d{1,2})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([NSns])\s*[-–,\s/]+\s*(\d{1,3})[°\s]+(\d{1,2})['\s]+(\d{1,2}(?:\.\d+)?)["]?\s*([EWew])/i;
      const mDms = norm.match(dmsRegex);
      if (mDms) {
        let lat = parseFloat(mDms[1]) + parseFloat(mDms[2]) / 60 + parseFloat(mDms[3]) / 3600;
        if (mDms[4].toUpperCase() === 'S') lat = -lat;
        let lng = parseFloat(mDms[5]) + parseFloat(mDms[6]) / 60 + parseFloat(mDms[7]) / 3600;
        if (mDms[8].toUpperCase() === 'W') lng = -lng;
        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
          return { lat, lng };
        }
      }

      // 2. DDM (Degrees Decimal Minutes) Regex: e.g. 04° 02.266' S, 122° 29.200' E
      const ddmRegex = /(\d{1,2})[°\s]+(\d{1,2}(?:\.\d+)?)[']?\s*([NSns])\s*[-–,\s/]+\s*(\d{1,3})[°\s]+(\d{1,2}(?:\.\d+)?)[']?\s*([EWew])/i;
      const mDdm = norm.match(ddmRegex);
      if (mDdm) {
        let lat = parseFloat(mDdm[1]) + parseFloat(mDdm[2]) / 60;
        if (mDdm[3].toUpperCase() === 'S') lat = -lat;
        let lng = parseFloat(mDdm[4]) + parseFloat(mDdm[5]) / 60;
        if (mDdm[6].toUpperCase() === 'W') lng = -lng;
        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
          return { lat, lng };
        }
      }

      // 3. Decimal with Cardinal direction: e.g. 4.0376 S, 122.4866 E
      const decCardRegex = /(\d{1,2}(?:\.\d+)?)\s*([NSns])\s*[-–,\s/]+\s*(\d{1,3}(?:\.\d+)?)\s*([EWew])/i;
      const mDecCard = norm.match(decCardRegex);
      if (mDecCard) {
        let lat = parseFloat(mDecCard[1]);
        if (mDecCard[2].toUpperCase() === 'S') lat = -lat;
        let lng = parseFloat(mDecCard[3]);
        if (mDecCard[4].toUpperCase() === 'W') lng = -lng;
        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
          return { lat, lng };
        }
      }

      // 4. Standard Signed Decimal: e.g. -4.037664, 122.486662
      const decRegex = /([-+]?\d{1,2}(?:\.\d+)?)\s*[,;\s]+\s*([-+]?\d{1,3}(?:\.\d+)?)/;
      const mDec = clean.match(decRegex);
      if (mDec) {
        const lat = parseFloat(mDec[1]);
        const lng = parseFloat(mDec[2]);
        if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
          return { lat, lng };
        }
      }

      return null;
    },

    /**
     * Compute Great-Circle Haversine distance and initial bearing azimuth between two points.
     * @param {number} lat1
     * @param {number} lon1
     * @param {number} lat2
     * @param {number} lon2
     * @returns {{ km: number, nm: number, bearing: number, headingName: string }}
     */
    calculateDistanceHaversine(lat1, lon1, lat2, lon2) {
      const R = 6371; // Earth radius in km
      const dLat = ((lat2 - lat1) * Math.PI) / 180;
      const dLon = ((lon2 - lon1) * Math.PI) / 180;
      const a =
        Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos((lat1 * Math.PI) / 180) *
        Math.cos((lat2 * Math.PI) / 180) *
        Math.sin(dLon / 2) *
        Math.sin(dLon / 2);
      const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
      const km = R * c;
      const nm = km * 0.539957;

      const y = Math.sin(dLon) * Math.cos((lat2 * Math.PI) / 180);
      const x =
        Math.cos((lat1 * Math.PI) / 180) * Math.sin((lat2 * Math.PI) / 180) -
        Math.sin((lat1 * Math.PI) / 180) * Math.cos((lat2 * Math.PI) / 180) * Math.cos(dLon);
      let brng = (Math.atan2(y, x) * 180) / Math.PI;
      brng = (brng + 360) % 360;

      const headings = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut'];
      const headingIdx = Math.round(brng / 45) % 8;

      return {
        km: Math.round(km * 100) / 100,
        nm: Math.round(nm * 100) / 100,
        bearing: Math.round(brng),
        headingName: headings[headingIdx]
      };
    },

    /**
     * Calculate spherical nautical polygon area in NM² and perimeter.
     * @param {Array|Object} coordsOrPoly
     * @param {string} [shapeType]
     * @param {number} [radiusNm]
     * @param {number} [boxWidthNm]
     * @param {number} [boxHeightNm]
     * @returns {{ sqKm: number, sqNm: number, hectares: number, perimeterKm: number, perimeterNm: number }}
     */
    calculatePolygonSphericalArea(coordsOrPoly, shapeType, radiusNm, boxWidthNm, boxHeightNm) {
      let coords = coordsOrPoly;
      if (coordsOrPoly && typeof coordsOrPoly === 'object' && !Array.isArray(coordsOrPoly)) {
        if (coordsOrPoly.points) {
          coords = coordsOrPoly.points;
        }
        shapeType = shapeType || coordsOrPoly.shapeType;
        radiusNm = radiusNm || coordsOrPoly.radiusNm;
        boxWidthNm = boxWidthNm || coordsOrPoly.boxWidthNm;
        boxHeightNm = boxHeightNm || coordsOrPoly.boxHeightNm;
      }

      // 1. Circle Area: A = pi * r^2
      if (shapeType === 'circle' && radiusNm && Number(radiusNm) > 0) {
        const r = Number(radiusNm);
        const sqNm = Math.PI * Math.pow(r, 2);
        const sqKm = sqNm * 3.429904;
        const perimeterNm = 2 * Math.PI * r;
        const perimeterKm = perimeterNm * 1.852;
        return {
          sqKm: Math.round(sqKm * 100) / 100,
          sqNm: Math.round(sqNm * 100) / 100,
          hectares: Math.round(sqKm * 1000) / 10,
          perimeterKm: Math.round(perimeterKm * 100) / 100,
          perimeterNm: Math.round(perimeterNm * 100) / 100
        };
      }

      // 2. Box / Rectangle Area: A = width * height
      if (shapeType === 'box' && boxWidthNm && boxHeightNm && Number(boxWidthNm) > 0 && Number(boxHeightNm) > 0) {
        const w = Number(boxWidthNm);
        const h = Number(boxHeightNm);
        const sqNm = w * h;
        const sqKm = sqNm * 3.429904;
        const perimeterNm = 2 * (w + h);
        const perimeterKm = perimeterNm * 1.852;
        return {
          sqKm: Math.round(sqKm * 100) / 100,
          sqNm: Math.round(sqNm * 100) / 100,
          hectares: Math.round(sqKm * 1000) / 10,
          perimeterKm: Math.round(perimeterKm * 100) / 100,
          perimeterNm: Math.round(perimeterNm * 100) / 100
        };
      }

      if (!coords || coords.length < 3) {
        return { sqKm: 0, sqNm: 0, hectares: 0, perimeterKm: 0, perimeterNm: 0 };
      }

      let sumLat = 0;
      coords.forEach(p => {
        const lat = p.lat !== undefined ? p.lat : (Array.isArray(p) ? p[0] : 0);
        sumLat += Number(lat || 0);
      });
      const meanLatRad = (sumLat / coords.length) * (Math.PI / 180);
      const cosMeanLat = Math.cos(meanLatRad);

      let areaSum = 0;
      const n = coords.length;
      for (let i = 0; i < n; i++) {
        const j = (i + 1) % n;
        const pt1 = coords[i];
        const pt2 = coords[j];
        const lng1 = pt1.lng !== undefined ? pt1.lng : (Array.isArray(pt1) ? pt1[1] : 0);
        const lat1 = pt1.lat !== undefined ? pt1.lat : (Array.isArray(pt1) ? pt1[0] : 0);
        const lng2 = pt2.lng !== undefined ? pt2.lng : (Array.isArray(pt2) ? pt2[1] : 0);
        const lat2 = pt2.lat !== undefined ? pt2.lat : (Array.isArray(pt2) ? pt2[0] : 0);

        const x1 = Number(lng1 || 0) * 60.0 * cosMeanLat;
        const y1 = Number(lat1 || 0) * 60.0;
        const x2 = Number(lng2 || 0) * 60.0 * cosMeanLat;
        const y2 = Number(lat2 || 0) * 60.0;
        areaSum += (x1 * y2) - (x2 * y1);
      }
      const sqNm = Math.abs(areaSum) / 2.0;
      const sqKm = sqNm * 3.429904;
      const hectares = sqKm * 100;

      let perimeterKm = 0;
      for (let i = 0; i < coords.length; i++) {
        const p1 = coords[i];
        const p2 = coords[(i + 1) % coords.length];
        const lat1 = p1.lat !== undefined ? p1.lat : (Array.isArray(p1) ? p1[0] : 0);
        const lng1 = p1.lng !== undefined ? p1.lng : (Array.isArray(p1) ? p1[1] : 0);
        const lat2 = p2.lat !== undefined ? p2.lat : (Array.isArray(p2) ? p2[0] : 0);
        const lng2 = p2.lng !== undefined ? p2.lng : (Array.isArray(p2) ? p2[1] : 0);
        perimeterKm += this.calculateDistanceHaversine(lat1, lng1, lat2, lng2).km;
      }
      const perimeterNm = perimeterKm * 0.539957;

      return {
        sqKm: Math.round(sqKm * 100) / 100,
        sqNm: Math.round(sqNm * 100) / 100,
        hectares: Math.round(hectares * 10) / 10,
        perimeterKm: Math.round(perimeterKm * 100) / 100,
        perimeterNm: Math.round(perimeterNm * 100) / 100
      };
    },

    /**
     * Human-readable duration in Indonesian.
     * @param {number} minutes
     * @returns {string}
     */
    formatDurationIndonesian(minutes) {
      const rounded = Math.max(1, Math.round(minutes));
      const hrs = Math.floor(rounded / 60);
      const mins = rounded % 60;
      if (hrs > 0 && mins > 0) return `${hrs} Jam ${mins} Menit`;
      if (hrs > 0) return `${hrs} Jam`;
      return `${mins} Menit`;
    }
  };

  global.QrSarGis.Geodesy = Geodesy;

})(typeof window !== 'undefined' ? window : this);
