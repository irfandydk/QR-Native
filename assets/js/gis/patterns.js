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
   * Clip ray segment within polygon boundary with safety buffer
   */
  function clipSegmentToPolygon(fromPt, toPt, poly, safeMarginDeg = 0.0005) {
    const n = poly.length;
    let closestInter = null;
    let minT = 1.0;

    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const inter = lineSegmentIntersection(fromPt, toPt, poly[i], poly[j]);
      if (inter && inter.t < minT && inter.t > 1e-5) {
        minT = inter.t;
        closestInter = inter;
      }
    }

    if (closestInter) {
      const dist = Math.hypot(toPt.lat - fromPt.lat, toPt.lng - fromPt.lng) || 1e-5;
      const pullT = Math.max(0.01, minT - (safeMarginDeg / dist));
      const safeT = Math.max(0.05, Math.min(minT * 0.96, pullT));
      return {
        lat: fromPt.lat + safeT * (toPt.lat - fromPt.lat),
        lng: fromPt.lng + safeT * (toPt.lng - fromPt.lng),
        clipped: true
      };
    }

    if (isPointInPolygon(toPt.lat, toPt.lng, poly)) {
      return { lat: toPt.lat, lng: toPt.lng, clipped: false };
    }

    return {
      lat: fromPt.lat + 0.9 * (toPt.lat - fromPt.lat),
      lng: fromPt.lng + 0.9 * (toPt.lng - fromPt.lng),
      clipped: true
    };
  }

  /**
   * Generate search pattern fitted strictly inside polygon boundary
   */
  function generateFittedPatternInsidePolygon(type, polygonPoints, spacingNm, legsCount, options = {}) {
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

    if (type === 'parallel_track') {
      const effMinLat = minLat + safeMarginLat;
      const effMaxLat = maxLat - safeMarginLat;
      const stepY = (effMaxLat - effMinLat) / Math.max(1, effectiveLegs - 1);

      for (let i = 0; i < effectiveLegs; i++) {
        const curLat = effMaxLat - i * stepY;
        const inters = getHorizontalIntersections(curLat, cleanPoints);
        if (inters.length >= 2) {
          const left = inters[0];
          const right = inters[inters.length - 1];
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
          const bottom = inters[0];
          const top = inters[inters.length - 1];
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
            const bottom = inters[0] + safeMarginLat;
            const top = inters[inters.length - 1] - safeMarginLat;
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
            const west = inters[0] + safeMarginLng;
            const east = inters[inters.length - 1] - safeMarginLng;
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
        return {
          ...p,
          lat: p.lat * 0.88 + centroid.lat * 0.12,
          lng: p.lng * 0.88 + centroid.lng * 0.12
        };
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
   * Calculate total distance in Nautical Miles along waypoints
   */
  function calculatePatternDistance(type, waypoints) {
    if (!waypoints || !Array.isArray(waypoints) || waypoints.length < 2) return 0;
    let totalNm = 0;
    if (type === 'sector_search' || type === 'e_sar') {
      for (let i = 0; i < waypoints.length - 1; i += 2) {
        const p1 = waypoints[i];
        const p2 = waypoints[i + 1];
        if (p1 && p2) {
          totalNm += distNm(parseFloat(p1.lat), parseFloat(p1.lng), parseFloat(p2.lat), parseFloat(p2.lng));
        }
      }
    } else {
      for (let i = 0; i < waypoints.length - 1; i++) {
        const p1 = waypoints[i];
        const p2 = waypoints[i + 1];
        totalNm += distNm(parseFloat(p1.lat), parseFloat(p1.lng), parseFloat(p2.lat), parseFloat(p2.lng));
      }
    }
    return totalNm;
  }

  /**
   * Calculate swept area (IAMSAR: A = S * L)
   */
  function calculateAreaSwept(totalNm, spacingNm) {
    const areaSweptNm2 = Math.max(0, (totalNm || 0) * (spacingNm || 0));
    const areaSweptKm2 = areaSweptNm2 * 3.429904;
    return { nm2: areaSweptNm2, km2: areaSweptKm2 };
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
    clipSegmentToPolygon,
    generateFittedPatternInsidePolygon,
    generateDatumPattern,
    getPatternPolylineCoords,
    calculatePatternDistance,
    calculateAreaSwept,
    getPatternDisplayName
  };

})(typeof window !== 'undefined' ? window : this);
