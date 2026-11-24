/**
 * QR-SAR Tactical GIS - Search Pattern Engine (IAMSAR & E-SAR)
 * File: assets/js/gis/patterns.js
 * 
 * Standalone pure-logic search pattern generation conforming to IAMSAR Manual Vol II:
 * - Parallel Track Search (PS)
 * - Creeping Line Search (CS)
 * - Expanding Square Search (SS)
 * - Explorer SAR (E-SAR) Sweep Line (Darat / Hutan)
 * 
 * Strict boundary collision detection, line clipping, and waypoint distance metrics.
 */

(function (window) {
  'use strict';

  window.QrSarGis = window.QrSarGis || {};

  /**
   * Safe distance resolver (Haversine via Geodesy module with internal fallback)
   */
  function distNm(lat1, lng1, lat2, lng2) {
    if (window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.calculateDistanceHaversine === 'function') {
      return window.QrSarGis.Geodesy.calculateDistanceHaversine(lat1, lng1, lat2, lng2).nm;
    }
    const R = 6371; // km
    const dLat = ((lat2 - lat1) * Math.PI) / 180;
    const dLng = ((lng2 - lng1) * Math.PI) / 180;
    const a =
      Math.sin(dLat / 2) * Math.sin(dLat / 2) +
      Math.cos((lat1 * Math.PI) / 180) *
        Math.cos((lat2 * Math.PI) / 180) *
        Math.sin(dLng / 2) *
        Math.sin(dLng / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    return (R * c) / 1.852;
  }

  /**
   * Point-in-polygon ray-casting algorithm
   */
  function isPointInPolygon(lat, lng, poly) {
    if (!poly || !Array.isArray(poly) || poly.length < 3) return false;
    let inside = false;
    const n = poly.length;
    for (let i = 0, j = n - 1; i < n; j = i++) {
      const xi = parseFloat(poly[i].lng), yi = parseFloat(poly[i].lat);
      const xj = parseFloat(poly[j].lng), yj = parseFloat(poly[j].lat);
      const intersect = ((yi > lat) !== (yj > lat)) &&
        (lng < ((xj - xi) * (lat - yi)) / (yj - yi) + xi);
      if (intersect) inside = !inside;
    }
    return inside;
  }

  /**
   * Calculate polygon geometric centroid with fallback to bounding average
   */
  function getPolygonCentroid(poly) {
    if (!poly || !Array.isArray(poly) || poly.length === 0) {
      return { lat: -4.037694, lng: 122.486694 };
    }
    const n = poly.length;
    if (n === 1) return { lat: parseFloat(poly[0].lat), lng: parseFloat(poly[0].lng) };
    if (n === 2) {
      return {
        lat: (parseFloat(poly[0].lat) + parseFloat(poly[1].lat)) / 2,
        lng: (parseFloat(poly[0].lng) + parseFloat(poly[1].lng)) / 2
      };
    }

    let area = 0;
    let cx = 0;
    let cy = 0;

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const xi = parseFloat(poly[i].lng);
      const yi = parseFloat(poly[i].lat);
      const xj = parseFloat(poly[j].lng);
      const yj = parseFloat(poly[j].lat);
      const factor = (xi * yj - xj * yi);
      area += factor;
      cx += (xi + xj) * factor;
      cy += (yi + yj) * factor;
    }
    area = area / 2;

    if (Math.abs(area) > 1e-9) {
      cx = cx / (6 * area);
      cy = cy / (6 * area);
      if (isPointInPolygon(cy, cx, poly)) {
        return { lat: cy, lng: cx };
      }
    }

    let sumLat = 0, sumLng = 0;
    poly.forEach(p => { sumLat += parseFloat(p.lat); sumLng += parseFloat(p.lng); });
    const avgPt = { lat: sumLat / n, lng: sumLng / n };
    if (isPointInPolygon(avgPt.lat, avgPt.lng, poly)) {
      return avgPt;
    }
    return { lat: parseFloat(poly[0].lat), lng: parseFloat(poly[0].lng) };
  }

  /**
   * Horizontal ray-polygon intersection points
   */
  function getHorizontalIntersections(lat, poly) {
    const n = poly.length;
    const intersections = [];

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const p1 = { lat: parseFloat(poly[i].lat), lng: parseFloat(poly[i].lng) };
      const p2 = { lat: parseFloat(poly[j].lat), lng: parseFloat(poly[j].lng) };

      const minY = Math.min(p1.lat, p2.lat);
      const maxY = Math.max(p1.lat, p2.lat);

      if (lat >= minY && lat <= maxY && maxY !== minY) {
        const t = (lat - p1.lat) / (p2.lat - p1.lat);
        if (t >= 0 && t <= 1) {
          const lngInter = p1.lng + t * (p2.lng - p1.lng);
          intersections.push(lngInter);
        }
      }
    }

    intersections.sort((a, b) => a - b);
    const unique = [];
    for (let i = 0; i < intersections.length; i++) {
      if (i === 0 || Math.abs(intersections[i] - intersections[i - 1]) > 1e-6) {
        unique.push(intersections[i]);
      }
    }
    return unique;
  }

  /**
   * Vertical ray-polygon intersection points
   */
  function getVerticalIntersections(lng, poly) {
    const n = poly.length;
    const intersections = [];

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const p1 = { lat: parseFloat(poly[i].lat), lng: parseFloat(poly[i].lng) };
      const p2 = { lat: parseFloat(poly[j].lat), lng: parseFloat(poly[j].lng) };

      const minX = Math.min(p1.lng, p2.lng);
      const maxX = Math.max(p1.lng, p2.lng);

      if (lng >= minX && lng <= maxX && maxX !== minX) {
        const t = (lng - p1.lng) / (p2.lng - p1.lng);
        if (t >= 0 && t <= 1) {
          const latInter = p1.lat + t * (p2.lat - p1.lat);
          intersections.push(latInter);
        }
      }
    }

    intersections.sort((a, b) => a - b);
    const unique = [];
    for (let i = 0; i < intersections.length; i++) {
      if (i === 0 || Math.abs(intersections[i] - intersections[i - 1]) > 1e-6) {
        unique.push(intersections[i]);
      }
    }
    return unique;
  }

  /**
   * 2D Line segment intersection
   */
  function lineSegmentIntersection(p1, p2, p3, p4) {
    const dX1 = p2.lng - p1.lng;
    const dY1 = p2.lat - p1.lat;
    const dX2 = p4.lng - p3.lng;
    const dY2 = p4.lat - p3.lat;

    const denom = dX1 * dY2 - dY1 * dX2;
    if (Math.abs(denom) < 1e-12) return null;

    const t = ((p3.lng - p1.lng) * dY2 - (p3.lat - p1.lat) * dX2) / denom;
    const u = ((p3.lng - p1.lng) * dY1 - (p3.lat - p1.lat) * dX1) / denom;

    if (t >= 0 && t <= 1 && u >= 0 && u <= 1) {
      return {
        lat: p1.lat + t * dY1,
        lng: p1.lng + t * dX1,
        t: t
      };
    }
    return null;
  }

  /**
   * Rotate polygon/waypoint array around centerPt in local conformal metric space (isometric NM).
   * Positive angleDeg rotates clockwise (compass bearing); negative angleDeg rotates counter-clockwise.
   */
  function rotatePointsConformal(pts, angleDeg, centerPt) {
    const normAngle = ((Number(angleDeg) || 0) % 360 + 360) % 360;
    if (normAngle === 0 || !Array.isArray(pts) || pts.length === 0) {
      return (pts || []).map((p) => ({ ...p }));
    }
    let cLat = centerPt && !isNaN(parseFloat(centerPt.lat)) ? parseFloat(centerPt.lat) : null;
    let cLng = centerPt && !isNaN(parseFloat(centerPt.lng)) ? parseFloat(centerPt.lng) : null;
    if (cLat === null || cLng === null) {
      const lats = pts.map((p) => parseFloat(p.lat)).filter((v) => !isNaN(v));
      const lngs = pts.map((p) => parseFloat(p.lng)).filter((v) => !isNaN(v));
      cLat = lats.length ? (Math.min(...lats) + Math.max(...lats)) / 2 : -4.037694;
      cLng = lngs.length ? (Math.min(...lngs) + Math.max(...lngs)) / 2 : 122.486694;
    }
    const rad = (normAngle * Math.PI) / 180;
    const cosA = Math.cos(rad);
    const sinA = Math.sin(rad);
    const cosLat = Math.max(0.1, Math.cos((cLat * Math.PI) / 180));

    return pts.map((p) => {
      const dx = (parseFloat(p.lng) - cLng) * cosLat;
      const dy = parseFloat(p.lat) - cLat;
      // Clockwise rotation in (East=dx, North=dy):
      const rx = dx * cosA + dy * sinA;
      const ry = -dx * sinA + dy * cosA;
      const out = {
        ...p,
        lat: cLat + ry,
        lng: cLng + rx / cosLat
      };
      if (typeof p.bearing === 'number' && !isNaN(p.bearing)) {
        out.bearing = Math.round(((p.bearing + normAngle) % 360 + 360) % 360);
      }
      return out;
    });
  }

  /**
   * Analyze polygon geometry (Minimum-Area Oriented Bounding Box) to determine:
   * - majorAxisBearingDeg (0..179): compass bearing of the longest dimension
   * - minorAxisBearingDeg (0..179): compass bearing of the shortest dimension
   * - majorSpanNm & minorSpanNm: dimensions in Nautical Miles
   */
  function analyzePolygonOrientation(polygonPoints) {
    const pts = (polygonPoints || [])
      .map((p) => ({ lat: parseFloat(p.lat), lng: parseFloat(p.lng) }))
      .filter((p) => !isNaN(p.lat) && !isNaN(p.lng));

    if (pts.length < 3) {
      return {
        centroid: { lat: -4.037694, lng: 122.486694 },
        majorAxisBearingDeg: 90,
        minorAxisBearingDeg: 0,
        majorSpanNm: 2.0,
        minorSpanNm: 2.0,
        isElongated: false
      };
    }

    const lats = pts.map((p) => p.lat);
    const lngs = pts.map((p) => p.lng);
    const cLat = (Math.min(...lats) + Math.max(...lats)) / 2;
    const cLng = (Math.min(...lngs) + Math.max(...lngs)) / 2;
    const cosLat = Math.max(0.1, Math.cos((cLat * Math.PI) / 180));

    // Convert to NM relative to bbox center (East = x, North = y)
    const metricPts = pts.map((p) => ({
      x: (p.lng - cLng) * cosLat * 60,
      y: (p.lat - cLat) * 60
    }));

    const candidateAngles = new Set([0, 45, 90, 135]);
    for (let i = 0; i < metricPts.length; i++) {
      const a = metricPts[i];
      const b = metricPts[(i + 1) % metricPts.length];
      const dx = b.x - a.x;
      const dy = b.y - a.y;
      if (Math.hypot(dx, dy) > 1e-4) {
        let brg = (Math.atan2(dx, dy) * 180) / Math.PI;
        brg = ((brg % 180) + 180) % 180;
        candidateAngles.add(Math.round(brg));
      }
    }

    let bestArea = Infinity;
    let bestMajorBearing = 90;
    let bestMajorSpan = 1.0;
    let bestMinorSpan = 1.0;

    candidateAngles.forEach((angleDeg) => {
      const rad = (angleDeg * Math.PI) / 180;
      const ux = Math.sin(rad);
      const uy = Math.cos(rad);
      const vx = Math.cos(rad);
      const vy = -Math.sin(rad);

      let minU = Infinity, maxU = -Infinity;
      let minV = Infinity, maxV = -Infinity;
      metricPts.forEach((p) => {
        const u = p.x * ux + p.y * uy;
        const v = p.x * vx + p.y * vy;
        if (u < minU) minU = u;
        if (u > maxU) maxU = u;
        if (v < minV) minV = v;
        if (v > maxV) maxV = v;
      });

      const spanU = Math.max(0.01, maxU - minU);
      const spanV = Math.max(0.01, maxV - minV);
      const area = spanU * spanV;

      if (area < bestArea - 1e-5) {
        bestArea = area;
        if (spanU >= spanV) {
          bestMajorBearing = ((angleDeg % 180) + 180) % 180;
          bestMajorSpan = spanU;
          bestMinorSpan = spanV;
        } else {
          bestMajorBearing = (((angleDeg + 90) % 180) + 180) % 180;
          bestMajorSpan = spanV;
          bestMinorSpan = spanU;
        }
      }
    });

    const minorBearing = (bestMajorBearing + 90) % 180;
    const aspectRatio = bestMajorSpan / Math.max(0.01, bestMinorSpan);
    return {
      centroid: { lat: cLat, lng: cLng },
      majorAxisBearingDeg: Math.round(bestMajorBearing) % 180,
      minorAxisBearingDeg: Math.round(minorBearing) % 180,
      majorSpanNm: bestMajorSpan,
      minorSpanNm: bestMinorSpan,
      aspectRatio: aspectRatio,
      isElongated: bestMajorSpan > bestMinorSpan * 1.08
    };
  }

  /**
   * Compute optimal pattern orientation (rotationDeg, flipH, flipV, direction) based on:
   * 1. Polygon shape (Major Axis for Parallel Track, Minor Axis for Creeping Line per IAMSAR Vol II)
   * 2. Reference point (e.g. LKP / Posko / Route arrival point) so Entry (S) starts at the nearest corner.
   */
  function computeOptimalPatternConfig(type, polygonPoints, referencePoint = null, spacingNm = 1.0, legsCount = 6) {
    const info = analyzePolygonOrientation(polygonPoints);
    const baseRot = info.minorAxisBearingDeg; // Aligns x' with Major Axis and y' with Minor Axis

    const hasRef = referencePoint && !isNaN(parseFloat(referencePoint.lat)) && !isNaN(parseFloat(referencePoint.lng));
    const refLat = hasRef ? parseFloat(referencePoint.lat) : null;
    const refLng = hasRef ? parseFloat(referencePoint.lng) : null;

    if (type === 'e_sar' || type === 'sector_search') {
      const candidates = [
        { rotationDeg: baseRot, orientation: 'horizontal', direction: 'west_to_east' },
        { rotationDeg: baseRot, orientation: 'horizontal', direction: 'east_to_west' },
        { rotationDeg: baseRot, orientation: 'vertical', direction: 'north_to_south' },
        { rotationDeg: baseRot, orientation: 'vertical', direction: 'south_to_north' }
      ];
      if (!hasRef) {
        return {
          rotationDeg: baseRot,
          flipH: false,
          flipV: false,
          orientation: 'horizontal',
          direction: 'west_to_east',
          majorAxisBearingDeg: info.majorAxisBearingDeg,
          minorAxisBearingDeg: info.minorAxisBearingDeg,
          majorSpanNm: info.majorSpanNm,
          minorSpanNm: info.minorSpanNm,
          aspectRatio: info.aspectRatio
        };
      }
      let best = candidates[0];
      let bestDist = Infinity;
      candidates.forEach((cand) => {
        const wps = generateFittedPatternInsidePolygon('e_sar', polygonPoints, spacingNm, legsCount, cand);
        if (wps && wps.length >= 2) {
          const d = distNm(refLat, refLng, wps[0].lat, wps[0].lng);
          if (d < bestDist) {
            bestDist = d;
            best = cand;
          }
        }
      });
      return {
        ...best,
        flipH: false,
        flipV: false,
        majorAxisBearingDeg: info.majorAxisBearingDeg,
        minorAxisBearingDeg: info.minorAxisBearingDeg,
        majorSpanNm: info.majorSpanNm,
        minorSpanNm: info.minorSpanNm,
        aspectRatio: info.aspectRatio
      };
    }

    // Parallel Track & Creeping Line: test 4 entry corners along aligned axes
    const candidates = [
      { rotationDeg: baseRot, flipH: false, flipV: false },
      { rotationDeg: baseRot, flipH: true, flipV: false },
      { rotationDeg: (baseRot + 180) % 360, flipH: false, flipV: false },
      { rotationDeg: (baseRot + 180) % 360, flipH: true, flipV: false }
    ];

    if (type === 'creeping_line') {
      candidates.push(
        { rotationDeg: baseRot, flipH: false, flipV: true },
        { rotationDeg: baseRot, flipH: true, flipV: true }
      );
    }

    if (!hasRef) {
      return {
        rotationDeg: baseRot,
        flipH: false,
        flipV: false,
        majorAxisBearingDeg: info.majorAxisBearingDeg,
        minorAxisBearingDeg: info.minorAxisBearingDeg,
        majorSpanNm: info.majorSpanNm,
        minorSpanNm: info.minorSpanNm,
        aspectRatio: info.aspectRatio
      };
    }

    let bestCand = candidates[0];
    let minEntryDist = Infinity;
    candidates.forEach((cand) => {
      const wps = generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount, cand);
      if (wps && wps.length >= 2) {
        const d = distNm(refLat, refLng, wps[0].lat, wps[0].lng);
        if (d < minEntryDist - 1e-4) {
          minEntryDist = d;
          bestCand = cand;
        }
      }
    });

    return {
      ...bestCand,
      majorAxisBearingDeg: info.majorAxisBearingDeg,
      minorAxisBearingDeg: info.minorAxisBearingDeg,
      majorSpanNm: info.majorSpanNm,
      minorSpanNm: info.minorSpanNm,
      aspectRatio: info.aspectRatio
    };
  }

  /**
   * Map 8-compass direction preset code to pattern orientation config.
   * Preset codes define Start Side -> Advance Direction:
   * - N_S: Utara -> Selatan (Start North 0°)
   * - S_N: Selatan -> Utara (Start South 180°)
   * - W_E: Barat -> Timur (Start West 270°)
   * - E_W: Timur -> Barat (Start East 90°)
   * - NW_SE: Barat Laut -> Tenggara (Start NW 315°)
   * - NE_SW: Timur Laut -> Barat Daya (Start NE 45°)
   * - SW_NE: Barat Daya -> Timur Laut (Start SW 225°)
   * - SE_NW: Tenggara -> Barat Laut (Start SE 135°)
   */
  function directionPresetToConfig(type, presetCode) {
    const startBearingMap = {
      N_S: 0,
      NE_SW: 45,
      E_W: 90,
      SE_NW: 135,
      S_N: 180,
      SW_NE: 225,
      W_E: 270,
      NW_SE: 315
    };
    const code = String(presetCode || 'N_S').toUpperCase();
    const startBearing = startBearingMap[code] !== undefined ? startBearingMap[code] : 0;

    if (type === 'creeping_line') {
      // At rotationDeg = 0, creeping_line starts at West (270°) and advances East (90°).
      // Adding 90° aligns creeping_line's start side with startBearing.
      return {
        rotationDeg: (startBearing + 90) % 360,
        flipH: false,
        flipV: false,
        presetCode: code
      };
    }

    if (type === 'e_sar' || type === 'sector_search') {
      if (code === 'N_S') return { rotationDeg: 0, orientation: 'vertical', direction: 'north_to_south', flipH: false, flipV: false, presetCode: code };
      if (code === 'S_N') return { rotationDeg: 0, orientation: 'vertical', direction: 'south_to_north', flipH: false, flipV: false, presetCode: code };
      if (code === 'W_E') return { rotationDeg: 0, orientation: 'horizontal', direction: 'west_to_east', flipH: false, flipV: false, presetCode: code };
      if (code === 'E_W') return { rotationDeg: 0, orientation: 'horizontal', direction: 'east_to_west', flipH: false, flipV: false, presetCode: code };
      return {
        rotationDeg: startBearing,
        orientation: 'vertical',
        direction: 'north_to_south',
        flipH: false,
        flipV: false,
        presetCode: code
      };
    }

    // parallel_track: at rotationDeg = 0, starts at North (0°) and advances South (180°)
    return {
      rotationDeg: startBearing,
      flipH: false,
      flipV: false,
      presetCode: code
    };
  }

  /**
   * Generate search pattern fitted strictly inside polygon boundary.
   * Supports:
   * - options.rotationDeg (0..359): arbitrary orientation angle while clipping strictly inside polygon
   * - options.autoAlign (boolean): automatically orient pattern along polygon axis & optional options.referencePoint
   * - options.startPreset ('N_S'|'S_N'|'W_E'|'E_W'|'NW_SE'|'NE_SW'|'SW_NE'|'SE_NW')
   * - options.flipH (cermin timur-barat) / options.flipV (cermin utara-selatan)
   */
  function generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount, options = {}) {
    const pts = (polygonPoints || []).map((p) => ({ ...p, lat: parseFloat(p.lat), lng: parseFloat(p.lng) }))
      .filter((p) => !isNaN(p.lat) && !isNaN(p.lng));
    if (pts.length < 3) return [];

    let effOptions = { ...(options || {}) };

    if (effOptions.autoAlign) {
      const autoCfg = computeOptimalPatternConfig(type, pts, effOptions.referencePoint || null, spacingNm, legsCount);
      effOptions = { ...effOptions, ...autoCfg, autoAlign: false };
    } else if (effOptions.startPreset) {
      const presetCfg = directionPresetToConfig(type, effOptions.startPreset);
      effOptions = {
        ...effOptions,
        rotationDeg: presetCfg.rotationDeg,
        orientation: presetCfg.orientation || effOptions.orientation,
        direction: presetCfg.direction || effOptions.direction,
        startPreset: null
      };
    }

    const rotDeg = ((Number(effOptions.rotationDeg) || 0) % 360 + 360) % 360;
    const lats = pts.map((p) => p.lat);
    const lngs = pts.map((p) => p.lng);
    const centerPt = {
      lat: (Math.min(...lats) + Math.max(...lats)) / 2,
      lng: (Math.min(...lngs) + Math.max(...lngs)) / 2
    };

    // If rotated (rotDeg != 0), transform polygon by -rotDeg into pattern local frame, generate, then rotate back by +rotDeg
    const workingPts = rotDeg !== 0 ? rotatePointsConformal(pts, -rotDeg, centerPt) : pts;

    const flipH = !!effOptions.flipH;
    const flipV = !!effOptions.flipV;
    let rawWaypoints = [];

    if ((type === 'parallel_track' || type === 'creeping_line') && (flipH || flipV)) {
      const wLats = workingPts.map((p) => p.lat);
      const wLngs = workingPts.map((p) => p.lng);
      const cLat = (Math.min(...wLats) + Math.max(...wLats)) / 2;
      const cLng = (Math.min(...wLngs) + Math.max(...wLngs)) / 2;
      const mirror = (p) => ({ ...p, lat: flipV ? 2 * cLat - p.lat : p.lat, lng: flipH ? 2 * cLng - p.lng : p.lng });
      rawWaypoints = generateFittedPatternCore(type, workingPts.map(mirror), spacingNm, legsCount, effOptions).map(mirror);
    } else {
      rawWaypoints = generateFittedPatternCore(type, workingPts, spacingNm, legsCount, effOptions);
    }

    const finalWaypoints = rotDeg !== 0 ? rotatePointsConformal(rawWaypoints, rotDeg, centerPt) : rawWaypoints;
    if (rotDeg === 0) return finalWaypoints;

    const origCentroid = getPolygonCentroid(pts);
    return finalWaypoints.map((w) => {
      if (isPointInPolygon(w.lat, w.lng, pts)) return w;
      for (const eps of [-1e-4, 1e-4, -5e-4, 5e-4]) {
        if (isPointInPolygon(w.lat + eps, w.lng, pts)) return { ...w, lat: w.lat + eps };
        if (isPointInPolygon(w.lat, w.lng + eps, pts)) return { ...w, lng: w.lng + eps };
      }
      for (const alpha of [0.05, 0.12, 0.25, 0.4, 0.6, 0.8, 1.0]) {
        const cLat = w.lat * (1 - alpha) + origCentroid.lat * alpha;
        const cLng = w.lng * (1 - alpha) + origCentroid.lng * alpha;
        if (isPointInPolygon(cLat, cLng, pts)) return { ...w, lat: cLat, lng: cLng };
      }
      return w;
    });
  }

  function generateFittedPatternCore(type, polygonPoints, spacingNm, legsCount, options = {}) {
    const cleanPoints = (polygonPoints || []).map((p) => ({
      lat: parseFloat(p.lat),
      lng: parseFloat(p.lng),
      label: p.label || ''
    })).filter((p) => !isNaN(p.lat) && !isNaN(p.lng));

    if (cleanPoints.length < 3) return [];

    let minLat = Infinity, maxLat = -Infinity, minLng = Infinity, maxLng = -Infinity;
    cleanPoints.forEach((p) => {
      if (p.lat < minLat) minLat = p.lat;
      if (p.lat > maxLat) maxLat = p.lat;
      if (p.lng < minLng) minLng = p.lng;
      if (p.lng > maxLng) maxLng = p.lng;
    });

    const centroid = getPolygonCentroid(cleanPoints);
    const midLat = centroid.lat;
    const lngKmPerDeg = Math.max(0.1, 111.32 * Math.cos((midLat * Math.PI) / 180));
    const spacingKm = Math.max(0.1, spacingNm * 1.852);

    const marginLat = (spacingKm / 2) / 110.574;
    const marginLng = (spacingKm / 2) / lngKmPerDeg;

    const spanLat = Math.max(0.001, maxLat - minLat);
    const spanLng = Math.max(0.001, maxLng - minLng);
    const safeMarginLat = Math.min(marginLat, spanLat * 0.12);
    const safeMarginLng = Math.min(marginLng, spanLng * 0.12);

    const points = [];
    const effectiveLegs = Math.max(2, legsCount || 2);

    // Helper: pick longest interior interval when scanline intersects a concave polygon >2 times
    function pickInteriorInterval(inters, fixedVal, isHorizontal) {
      if (inters.length === 2) return [inters[0], inters[1]];
      let bestPair = [inters[0], inters[inters.length - 1]];
      let bestLen = -1;
      for (let a = 0; a < inters.length - 1; a++) {
        for (let b = a + 1; b < inters.length; b++) {
          const mid = (inters[a] + inters[b]) / 2;
          const q1 = inters[a] * 0.75 + inters[b] * 0.25;
          const q3 = inters[a] * 0.25 + inters[b] * 0.75;
          const insideMid = isHorizontal
            ? (isPointInPolygon(fixedVal, mid, cleanPoints) && isPointInPolygon(fixedVal, q1, cleanPoints) && isPointInPolygon(fixedVal, q3, cleanPoints))
            : (isPointInPolygon(mid, fixedVal, cleanPoints) && isPointInPolygon(q1, fixedVal, cleanPoints) && isPointInPolygon(q3, fixedVal, cleanPoints));
          if (insideMid && (inters[b] - inters[a]) > bestLen) {
            bestLen = inters[b] - inters[a];
            bestPair = [inters[a], inters[b]];
          }
        }
      }
      return bestPair;
    }

    if (type === 'parallel_track') {
      const effMinLat = minLat + safeMarginLat;
      const effMaxLat = maxLat - safeMarginLat;
      const stepY = (effMaxLat - effMinLat) / Math.max(1, effectiveLegs - 1);

      for (let i = 0; i < effectiveLegs; i++) {
        const curLat = effMaxLat - i * stepY;
        const inters = getHorizontalIntersections(curLat, cleanPoints);
        if (inters.length >= 2) {
          const [left, right] = pickInteriorInterval(inters, curLat, true);
          const legSpan = right - left;
          const legMargin = Math.min(safeMarginLng, legSpan * 0.08);
          const west = left + legMargin;
          const east = right - legMargin;
          const sweepEast = i % 2 === 0;

          points.push({
            lat: curLat,
            lng: sweepEast ? west : east,
            label: i === 0 ? 'Entry (S)' : `Lintasan ${i + 1}A`
          });
          points.push({
            lat: curLat,
            lng: sweepEast ? east : west,
            label: i === effectiveLegs - 1 ? 'Exit (E)' : `Lintasan ${i + 1}B`
          });
        }
      }

      if (points.length < 2) {
        const stepY = spanLat / Math.max(1, effectiveLegs - 1);
        for (let i = 0; i < effectiveLegs; i++) {
          const curLat = effMaxLat - i * stepY;
          const sweepEast = i % 2 === 0;
          points.push({ lat: curLat, lng: sweepEast ? minLng + safeMarginLng : maxLng - safeMarginLng, label: i === 0 ? 'Entry (S)' : `Lintasan ${i + 1}A` });
          points.push({ lat: curLat, lng: sweepEast ? maxLng - safeMarginLng : minLng + safeMarginLng, label: i === effectiveLegs - 1 ? 'Exit (E)' : `Lintasan ${i + 1}B` });
        }
      }
    } else if (type === 'creeping_line') {
      const effMinLng = minLng + safeMarginLng;
      const effMaxLng = maxLng - safeMarginLng;
      const stepX = (effMaxLng - effMinLng) / Math.max(1, effectiveLegs - 1);

      for (let i = 0; i < effectiveLegs; i++) {
        const curLng = effMinLng + i * stepX;
        const inters = getVerticalIntersections(curLng, cleanPoints);
        if (inters.length >= 2) {
          const [bottom, top] = pickInteriorInterval(inters, curLng, false);
          const legSpan = top - bottom;
          const legMargin = Math.min(safeMarginLat, legSpan * 0.08);
          const south = bottom + legMargin;
          const north = top - legMargin;
          const sweepSouth = i % 2 === 0;

          points.push({
            lat: sweepSouth ? north : south,
            lng: curLng,
            label: i === 0 ? 'Entry (S)' : `Sapu ${i + 1}A`
          });
          points.push({
            lat: sweepSouth ? south : north,
            lng: curLng,
            label: i === effectiveLegs - 1 ? 'Exit (E)' : `Sapu ${i + 1}B`
          });
        }
      }

      if (points.length < 2) {
        const stepX = spanLng / Math.max(1, effectiveLegs - 1);
        for (let i = 0; i < effectiveLegs; i++) {
          const curLng = effMinLng + i * stepX;
          const sweepSouth = i % 2 === 0;
          points.push({ lat: sweepSouth ? maxLat - safeMarginLat : minLat + safeMarginLat, lng: curLng, label: i === 0 ? 'Entry (S)' : `Sapu ${i + 1}A` });
          points.push({ lat: sweepSouth ? minLat + safeMarginLat : maxLat - safeMarginLat, lng: curLng, label: i === effectiveLegs - 1 ? 'Exit (E)' : `Sapu ${i + 1}B` });
        }
      }
    } else {
      // 3. Pola Pencarian E-SAR (Explorer SAR - Darat / Gunung Hutan)
      const personnelCount = Math.max(2, effectiveLegs || 5);
      const spanLatKm = (maxLat - minLat) * 110.574;
      const spanLngKm = (maxLng - minLng) * lngKmPerDeg;
      const orient = (options && options.orientation) ? options.orientation : (spanLatKm >= spanLngKm ? 'vertical' : 'horizontal');
      const dir = (options && options.direction) ? options.direction : (orient === 'vertical' ? 'north_to_south' : 'west_to_east');

      if (orient === 'vertical') {
        const effMinLng = minLng + safeMarginLng;
        const effMaxLng = maxLng - safeMarginLng;
        const stepX = (effMaxLng - effMinLng) / Math.max(1, personnelCount - 1);

        for (let i = 0; i < personnelCount; i++) {
          const curLng = effMinLng + i * stepX;
          const inters = getVerticalIntersections(curLng, cleanPoints);
          if (inters.length >= 2) {
            const [rawB, rawT] = pickInteriorInterval(inters, curLng, false);
            const legMargin = Math.min(safeMarginLat, (rawT - rawB) * 0.08);
            const bottom = rawB + legMargin;
            const top = rawT - legMargin;
            const pNum = i + 1;

            if (dir === 'south_to_north') {
              // Maju dari Selatan ke Utara
              points.push({ lat: bottom, lng: curLng, label: `P${pNum} Start (Selatan)`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'north', bearing: 0 });
              points.push({ lat: top, lng: curLng, label: `P${pNum} Batas Maju (Utara)`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'north', bearing: 0 });
            } else {
              // Maju dari Utara ke Selatan (default)
              points.push({ lat: top, lng: curLng, label: `P${pNum} Start (Utara)`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'south', bearing: 180 });
              points.push({ lat: bottom, lng: curLng, label: `P${pNum} Batas Maju (Selatan)`, sweepDir: 'vertical', pNum: pNum, advanceDir: 'south', bearing: 180 });
            }
          }
        }
      } else {
        const effMinLat = minLat + safeMarginLat;
        const effMaxLat = maxLat - safeMarginLat;
        const stepY = (effMaxLat - effMinLat) / Math.max(1, personnelCount - 1);

        for (let i = 0; i < personnelCount; i++) {
          const curLat = effMaxLat - i * stepY;
          const inters = getHorizontalIntersections(curLat, cleanPoints);
          if (inters.length >= 2) {
            const [rawW, rawE] = pickInteriorInterval(inters, curLat, true);
            const legMargin = Math.min(safeMarginLng, (rawE - rawW) * 0.08);
            const west = rawW + legMargin;
            const east = rawE - legMargin;
            const pNum = i + 1;

            if (dir === 'east_to_west') {
              // Maju dari Timur ke Barat
              points.push({ lat: curLat, lng: east, label: `P${pNum} Start (Timur)`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'west', bearing: 270 });
              points.push({ lat: curLat, lng: west, label: `P${pNum} Batas Maju (Barat)`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'west', bearing: 270 });
            } else {
              // Maju dari Barat ke Timur (default)
              points.push({ lat: curLat, lng: west, label: `P${pNum} Start (Barat)`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'east', bearing: 90 });
              points.push({ lat: curLat, lng: east, label: `P${pNum} Batas Maju (Timur)`, sweepDir: 'horizontal', pNum: pNum, advanceDir: 'east', bearing: 90 });
            }
          }
        }
      }
    }

    // Boundary containment verification: pertahankan properti sweepDir, pNum, bearing
    return points.map((p) => {
      if (!isPointInPolygon(p.lat, p.lng, cleanPoints)) {
        for (const alpha of [0.12, 0.25, 0.4, 0.55, 0.7, 0.85, 1.0]) {
          const candLat = p.lat * (1 - alpha) + centroid.lat * alpha;
          const candLng = p.lng * (1 - alpha) + centroid.lng * alpha;
          if (isPointInPolygon(candLat, candLng, cleanPoints)) {
            return { ...p, lat: candLat, lng: candLng };
          }
        }
      }
      return p;
    });
  }

  /**
   * Generate fallback datum pattern around center coordinate
   */
  function generateDatumPattern(type, center, spacingNm, legsCount, options = {}) {
    let c = center;
    if (!c || typeof c.lat !== 'number' || isNaN(c.lat)) {
      c = { lat: -4.037694, lng: 122.486694 };
    } else {
      c = { lat: parseFloat(c.lat), lng: parseFloat(c.lng) };
    }
    const spanDeg = Math.max(0.08, (spacingNm * Math.max(3, legsCount || 4) * 1.852) / 110.574);
    const fakePolygon = [
      { lat: c.lat + spanDeg / 2, lng: c.lng - spanDeg / 2 },
      { lat: c.lat + spanDeg / 2, lng: c.lng + spanDeg / 2 },
      { lat: c.lat - spanDeg / 2, lng: c.lng + spanDeg / 2 },
      { lat: c.lat - spanDeg / 2, lng: c.lng - spanDeg / 2 }
    ];
    return generateFittedPatternInsidePolygon(type, fakePolygon, spacingNm, legsCount, options);
  }

  /**
   * Get polyline coordinates for Leaflet rendering
   */
  function getPatternPolylineCoords(type, waypoints) {
    if (!waypoints || waypoints.length < 2) return [];
    if (type === 'sector_search' || type === 'e_sar') {
      const segments = [];
      const baseline = [];
      for (let i = 0; i < waypoints.length - 1; i += 2) {
        const pStart = waypoints[i];
        const pFin = waypoints[i + 1];
        if (pStart && pFin) {
          segments.push([
            [parseFloat(pStart.lat), parseFloat(pStart.lng)],
            [parseFloat(pFin.lat), parseFloat(pFin.lng)]
          ]);
          baseline.push([parseFloat(pStart.lat), parseFloat(pStart.lng)]);
        }
      }
      if (baseline.length >= 2) {
        segments.push(baseline);
      }
      return segments;
    }
    return waypoints.map((p) => [parseFloat(p.lat), parseFloat(p.lng)]);
  }

  /**
   * Friendly display name for search patterns
   */
  function getPatternDisplayName(type) {
    if (type === 'parallel_track') return 'Parallel Track (PS)';
    if (type === 'creeping_line') return 'Creeping Line (CS)';
    if (type === 'sector_search' || type === 'e_sar') return 'Pola Pencarian E-SAR';
    return 'Lintasan IAMSAR';
  }

  // Export to namespace
  window.QrSarGis.Patterns = {
    isPointInPolygon,
    getPolygonCentroid,
    getHorizontalIntersections,
    getVerticalIntersections,
    lineSegmentIntersection,
    rotatePointsConformal,
    analyzePolygonOrientation,
    computeOptimalPatternConfig,
    directionPresetToConfig,
    generateFittedPatternInsidePolygon,
    generateDatumPattern,
    getPatternPolylineCoords,
    getPatternDisplayName
  };

})(typeof window !== 'undefined' ? window : this);
