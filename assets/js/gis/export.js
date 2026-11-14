/**
 * QR-SAR Tactical GIS - Spatial Data Exporter (KML, KMZ, GeoJSON)
 * File: assets/js/gis/export.js
 * 
 * Modular exporter for points, sector polygons, search patterns, and navigation routes.
 * Compatible with Google Earth, QGIS, ArcGIS, and GPS handheld receivers.
 */

(function (window) {
  'use strict';

  window.QrSarGis = window.QrSarGis || {};

  /**
   * Safe distance resolver (Haversine in Nautical Miles)
   */
  function haversineNm(lat1, lng1, lat2, lng2) {
    if (window.QrSarGis && window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.calculateDistanceHaversine === 'function') {
      const res = window.QrSarGis.Geodesy.calculateDistanceHaversine(lat1, lng1, lat2, lng2);
      if (res && typeof res.nm === 'number') return res.nm;
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
   * Escape XML/KML special characters
   */
  function escapeXml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&apos;');
  }

  /**
   * Convert CSS hex color and opacity into KML 8-char aabbggrr hex format
   * @param {string} color CSS hex color (#rgb or #rrggbb)
   * @param {number} [opacity=1] Opacity between 0 and 1
   * @returns {string} 8-character lowercase aabbggrr hex string
   */
  function cssColorToKmlAbgr(color, opacity) {
    const FALLBACK_ABGR = 'ffea580c';
    if (typeof color !== 'string') {
      return FALLBACK_ABGR;
    }
    let c = color.trim().toLowerCase();
    if (c.startsWith('#')) {
      c = c.slice(1);
    }
    if (/^[0-9a-f]{3}$/.test(c)) {
      c = c[0] + c[0] + c[1] + c[1] + c[2] + c[2];
    }
    if (!/^[0-9a-f]{6}$/.test(c)) {
      return FALLBACK_ABGR;
    }
    const rr = c.slice(0, 2);
    const gg = c.slice(2, 4);
    const bb = c.slice(4, 6);

    let op = (typeof opacity === 'number' && Number.isFinite(opacity)) ? opacity : 1;
    if (op < 0) op = 0;
    if (op > 1) op = 1;
    const aa = Math.round(op * 255).toString(16).padStart(2, '0').toLowerCase();

    return `${aa}${bb}${gg}${rr}`;
  }

  /**
   * Validate geographical coordinate bounds
   */
  function isValidCoord(lat, lng) {
    return Number.isFinite(lat) && lat >= -90 && lat <= 90 &&
           Number.isFinite(lng) && lng >= -180 && lng <= 180;
  }

  /**
   * Extract safe local icon path from URL or relative path
   * Accepts only paths matching ^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$
   * Rejects .., query/hash, whitespace, and data: URIs
   * @param {unknown} url
   * @returns {string|null}
   */
  function extractSafeLocalIconPath(url) {
    if (typeof url !== 'string' || !url.trim()) return null;
    const trimmed = url.trim();
    if (trimmed.includes('..') || trimmed.startsWith('data:') || /[?#\s]/.test(trimmed)) {
      return null;
    }
    const match = trimmed.match(/(?:^|\/)((?:assets\/img|uploads\/icons)\/[A-Za-z0-9_.\-/]+)$/);
    if (!match) return null;
    const safePath = match[1];
    if (!/^(assets\/img|uploads\/icons)\/[A-Za-z0-9_.\-/]+$/.test(safePath) || safePath.includes('..')) {
      return null;
    }
    return safePath;
  }

  /**
   * Generate coordinates ring for a circle polygon
   * @param {number} centerLat Latitude of circle center
   * @param {number} centerLng Longitude of circle center
   * @param {number} radiusNm Radius in Nautical Miles (> 0)
   * @param {number} [numPoints=64] Number of vertices in circle ring
   * @returns {Array<{lat: number, lng: number}>} Array of points (unclosed)
   */
  function generateCircleRing(centerLat, centerLng, radiusNm, numPoints) {
    const cLat = parseFloat(centerLat);
    const cLng = parseFloat(centerLng);
    const rNm = parseFloat(radiusNm);
    if (!isValidCoord(cLat, cLng) || !Number.isFinite(rNm) || rNm <= 0) {
      return [];
    }
    const n = (typeof numPoints === 'number' && Number.isFinite(numPoints) && numPoints >= 3)
      ? Math.floor(numPoints)
      : 64;

    const radiusKm = rNm * 1.852;
    const deltaLatDeg = radiusKm / 110.574;
    const cosLat = Math.cos((cLat * Math.PI) / 180);
    const cosFactor = Math.abs(cosLat) < 1e-6 ? 1e-6 : Math.abs(cosLat);
    const deltaLngDeg = radiusKm / (111.32 * cosFactor);

    const ring = [];
    for (let i = 0; i < n; i++) {
      const angle = (2 * Math.PI * i) / n;
      const lat = cLat + deltaLatDeg * Math.sin(angle);
      const lng = cLng + deltaLngDeg * Math.cos(angle);
      ring.push({ lat, lng });
    }
    return ring;
  }

  /**
   * Render KML ExtendedData block from key-value pairs
   */
  function renderExtendedData(entries) {
    const validRows = [];
    for (const [name, val] of entries) {
      if (val !== undefined && val !== null && String(val).trim() !== '') {
        validRows.push(`        <Data name="${escapeXml(name)}"><value>${escapeXml(val)}</value></Data>`);
      }
    }
    if (validRows.length === 0) return '';
    return `      <ExtendedData>\n${validRows.join('\n')}\n      </ExtendedData>\n`;
  }

  /**
   * Generate valid KML 2.2 XML Document String
   */
  function generateKmlDocument(data) {
    const d = data || {};
    const opsName = d.operasiName || 'Plotting Operasi SAR Basarnas Kendari';
    const points = Array.isArray(d.points) ? d.points : [];
    const polygons = Array.isArray(d.polygons) ? d.polygons : [];
    const patterns = Array.isArray(d.patterns) ? d.patterns : [];
    const routes = Array.isArray(d.routes) ? d.routes : [];

    const Markers = (window.QrSarGis && window.QrSarGis.Markers) ? window.QrSarGis.Markers : null;
    const Geodesy = (window.QrSarGis && window.QrSarGis.Geodesy) ? window.QrSarGis.Geodesy : null;
    const Patterns = (window.QrSarGis && window.QrSarGis.Patterns) ? window.QrSarGis.Patterns : null;

    const stylesMap = new Map();

    // Folder 1: Points
    let pointsPlacemarks = '';
    points.forEach((p) => {
      const lat = parseFloat(p.lat);
      const lng = parseFloat(p.lng);
      if (!isValidCoord(lat, lng)) return;

      const pointColor = p.color || (Markers && Array.isArray(Markers.PRESET_ICONS)
        ? (Markers.PRESET_ICONS.find(i => i.type === p.pointType) || {}).color
        : null) || '#ea580c';
      const abgr = cssColorToKmlAbgr(pointColor, 1);
      const styleKey = `pt:${abgr}`;
      const styleId = `style-pt-${abgr}`;
      if (!stylesMap.has(styleKey)) {
        stylesMap.set(styleKey, `    <Style id="${styleId}">\n      <IconStyle>\n        <color>${abgr}</color>\n        <scale>1.1</scale>\n        <Icon>\n          <href>https://maps.google.com/mapfiles/kml/paddle/wht-blank.png</href>\n        </Icon>\n      </IconStyle>\n    </Style>\n`);
      }

      const safeIcon = (p.pointType === 'custom_upload')
        ? extractSafeLocalIconPath(p.customIconUrl || p.iconUrl || p.customIconPath)
        : null;

      const extEntries = [
        ['qrsar:sourceId', p.id || p.sourceId],
        ['qrsar:sarType', 'point'],
        ['qrsar:category', p.category],
        ['qrsar:pointType', p.pointType],
        ['qrsar:alphabetChar', p.alphabetChar],
        ['qrsar:customIconPath', safeIcon]
      ];
      const extXml = renderExtendedData(extEntries);

      pointsPlacemarks += `      <Placemark>\n        <name>${escapeXml(p.label || 'Titik')}</name>\n        <description>${escapeXml(p.description || '')} [${escapeXml(p.category || 'SAR')}]</description>\n        <styleUrl>#${styleId}</styleUrl>\n${extXml}        <Point>\n          <coordinates>${lng},${lat},0</coordinates>\n        </Point>\n      </Placemark>\n`;
    });

    // Folder 2: Polygons
    let polygonsPlacemarks = '';
    polygons.forEach((poly) => {
      let ringPoints = [];
      if (poly.shapeType === 'circle') {
        if (!poly.points || poly.points.length < 3) {
          if (Number.isFinite(poly.centerLat) && Number.isFinite(poly.centerLng) && Number.isFinite(poly.radiusNm) && poly.radiusNm > 0) {
            ringPoints = generateCircleRing(poly.centerLat, poly.centerLng, poly.radiusNm, 64);
          }
        } else {
          ringPoints = poly.points.filter(pt => isValidCoord(parseFloat(pt.lat), parseFloat(pt.lng)));
        }
      } else {
        ringPoints = (poly.points || []).filter(pt => isValidCoord(parseFloat(pt.lat), parseFloat(pt.lng)));
      }

      if (ringPoints.length < 3) return;

      const ring = ringPoints.map(pt => ({ lat: parseFloat(pt.lat), lng: parseFloat(pt.lng) }));
      const first = ring[0];
      const last = ring[ring.length - 1];
      if (first.lat !== last.lat || first.lng !== last.lng) {
        ring.push({ lat: first.lat, lng: first.lng });
      }

      const strokeColor = poly.strokeColor || '#0284c7';
      const strokeWeight = (typeof poly.strokeWeight === 'number' && Number.isFinite(poly.strokeWeight)) ? poly.strokeWeight : 2;
      const strokeOpacity = (typeof poly.strokeOpacity === 'number' && Number.isFinite(poly.strokeOpacity)) ? poly.strokeOpacity : 1;
      const fillColor = poly.fillColor || poly.strokeColor || '#0284c7';
      const fillOpacity = (typeof poly.fillOpacity === 'number' && Number.isFinite(poly.fillOpacity)) ? poly.fillOpacity : 0.25;
      const hasFill = (poly.hasFill !== undefined) ? (poly.hasFill ? 1 : 0) : (fillOpacity > 0 ? 1 : 0);

      const strokeAbgr = cssColorToKmlAbgr(strokeColor, strokeOpacity);
      const fillAbgr = cssColorToKmlAbgr(fillColor, fillOpacity);
      const styleKey = `poly:${strokeAbgr}:${strokeWeight}:${fillAbgr}:${hasFill}`;
      const styleId = `style-poly-${strokeAbgr}-${strokeWeight}-${fillAbgr}-${hasFill}`;

      if (!stylesMap.has(styleKey)) {
        stylesMap.set(styleKey, `    <Style id="${styleId}">\n      <LineStyle>\n        <color>${strokeAbgr}</color>\n        <width>${strokeWeight}</width>\n      </LineStyle>\n      <PolyStyle>\n        <color>${fillAbgr}</color>\n        <fill>${hasFill}</fill>\n        <outline>1</outline>\n      </PolyStyle>\n    </Style>\n`);
      }

      const extEntries = [
        ['qrsar:sourceId', poly.id || poly.sourceId],
        ['qrsar:sarType', 'polygon'],
        ['qrsar:shapeType', poly.shapeType || 'polygon'],
        ['qrsar:centerLat', poly.centerLat],
        ['qrsar:centerLng', poly.centerLng],
        ['qrsar:radiusNm', poly.radiusNm],
        ['qrsar:boxWidthNm', poly.boxWidthNm],
        ['qrsar:boxHeightNm', poly.boxHeightNm],
        ['qrsar:areaNm2', poly.areaNm2],
        ['qrsar:strokeColor', strokeColor],
        ['qrsar:strokeWeight', strokeWeight],
        ['qrsar:strokeOpacity', strokeOpacity],
        ['qrsar:fillColor', fillColor],
        ['qrsar:fillOpacity', fillOpacity]
      ];
      const extXml = renderExtendedData(extEntries);
      const coordsStr = ring.map(pt => `${pt.lng},${pt.lat},0`).join(' ');

      polygonsPlacemarks += `      <Placemark>\n        <name>${escapeXml(poly.name || 'Sektor')}</name>\n        <styleUrl>#${styleId}</styleUrl>\n${extXml}        <Polygon>\n          <tessellate>1</tessellate>\n          <altitudeMode>clampToGround</altitudeMode>\n          <outerBoundaryIs>\n            <LinearRing>\n              <coordinates>${coordsStr}</coordinates>\n            </LinearRing>\n          </outerBoundaryIs>\n        </Polygon>\n      </Placemark>\n`;
    });

    // Folder 3: Patterns
    let patternsPlacemarks = '';
    patterns.forEach((pat) => {
      const waypoints = (pat.waypoints || []).filter(w => isValidCoord(parseFloat(w.lat), parseFloat(w.lng)));
      if (waypoints.length < 2) return;

      const strokeColor = pat.color || '#ea580c';
      const strokeWeight = (typeof pat.strokeWeight === 'number' && Number.isFinite(pat.strokeWeight)) ? pat.strokeWeight : 2;
      const strokeOpacity = (typeof pat.opacity === 'number' && Number.isFinite(pat.opacity))
        ? pat.opacity
        : ((typeof pat.strokeOpacity === 'number' && Number.isFinite(pat.strokeOpacity)) ? pat.strokeOpacity : 1);
      const strokeAbgr = cssColorToKmlAbgr(strokeColor, strokeOpacity);
      const styleKey = `line:${strokeAbgr}:${strokeWeight}`;
      const styleId = `style-line-${strokeAbgr}-${strokeWeight}`;

      if (!stylesMap.has(styleKey)) {
        stylesMap.set(styleKey, `    <Style id="${styleId}">\n      <LineStyle>\n        <color>${strokeAbgr}</color>\n        <width>${strokeWeight}</width>\n      </LineStyle>\n    </Style>\n`);
      }

      const isEsarOrSector = (pat.patternType === 'sector_search' || pat.patternType === 'e_sar');

      let waypointsJsonStr = null;
      if (isEsarOrSector) {
        waypointsJsonStr = JSON.stringify(waypoints.map(w => ({
          lat: parseFloat(w.lat),
          lng: parseFloat(w.lng),
          label: w.label || ''
        })));
      }

      const extEntries = [
        ['qrsar:sourceId', pat.id || pat.sourceId],
        ['qrsar:sarType', 'pattern'],
        ['qrsar:patternType', pat.patternType],
        ['qrsar:trackSpacingNm', pat.trackSpacingNm],
        ['qrsar:legsCount', pat.legsCount],
        ['qrsar:totalDistanceNm', pat.totalDistanceNm],
        ['qrsar:flipH', pat.flipH !== undefined ? String(Boolean(pat.flipH)) : undefined],
        ['qrsar:flipV', pat.flipV !== undefined ? String(Boolean(pat.flipV)) : undefined],
        ['qrsar:polygonRef', pat.polygonRef || pat.polygonId],
        ['qrsar:polygonName', pat.polygonName],
        ['qrsar:poskoId', pat.poskoId],
        ['qrsar:poskoName', pat.poskoName],
        ['qrsar:color', strokeColor],
        ['qrsar:opacity', strokeOpacity],
        ['qrsar:strokeWeight', strokeWeight],
        ['qrsar:waypointsJson', waypointsJsonStr]
      ];
      const extXml = renderExtendedData(extEntries);

      let geomXml = '';
      if (isEsarOrSector) {
        let segments = null;
        if (Array.isArray(pat.segments) && pat.segments.length > 0) {
          segments = pat.segments;
        } else if (Patterns && typeof Patterns.getPatternPolylineCoords === 'function') {
          segments = Patterns.getPatternPolylineCoords(pat.patternType, waypoints);
        }
        if (!Array.isArray(segments) || segments.length === 0) {
          const segs = [];
          const base = [];
          for (let i = 0; i < waypoints.length - 1; i += 2) {
            const p1 = waypoints[i];
            const p2 = waypoints[i + 1];
            if (p1 && p2) {
              segs.push([[parseFloat(p1.lat), parseFloat(p1.lng)], [parseFloat(p2.lat), parseFloat(p2.lng)]]);
              base.push([parseFloat(p1.lat), parseFloat(p1.lng)]);
            }
          }
          if (base.length >= 2) {
            segs.push(base);
          }
          segments = segs;
        }

        let multiInner = '';
        for (const seg of segments) {
          if (!Array.isArray(seg) || seg.length < 2) continue;
          const segCoordsStr = seg.map(pt => {
            const lat = Array.isArray(pt) ? pt[0] : pt.lat;
            const lng = Array.isArray(pt) ? pt[1] : pt.lng;
            return `${lng},${lat},0`;
          }).join(' ');
          multiInner += `          <LineString>\n            <tessellate>1</tessellate>\n            <altitudeMode>clampToGround</altitudeMode>\n            <coordinates>${segCoordsStr}</coordinates>\n          </LineString>\n`;
        }
        geomXml = `        <MultiGeometry>\n${multiInner}        </MultiGeometry>\n`;
      } else {
        const coordsStr = waypoints.map(wp => `${wp.lng},${wp.lat},0`).join(' ');
        geomXml = `        <LineString>\n          <tessellate>1</tessellate>\n          <altitudeMode>clampToGround</altitudeMode>\n          <coordinates>${coordsStr}</coordinates>\n        </LineString>\n`;
      }

      patternsPlacemarks += `      <Placemark>\n        <name>${escapeXml(pat.name || 'Pola Lintasan')}</name>\n        <styleUrl>#${styleId}</styleUrl>\n${extXml}${geomXml}      </Placemark>\n`;
    });

    // Folder 4: Routes
    let routesPlacemarks = '';
    routes.forEach((rt) => {
      const points = (rt.points || []).filter(p => isValidCoord(parseFloat(p.lat), parseFloat(p.lng)));
      if (points.length < 2) return;

      const strokeColor = rt.strokeColor || rt.color || '#0ea5e9';
      const strokeWeight = (typeof rt.strokeWeight === 'number' && Number.isFinite(rt.strokeWeight)) ? rt.strokeWeight : 3;
      const strokeOpacity = (typeof rt.strokeOpacity === 'number' && Number.isFinite(rt.strokeOpacity))
        ? rt.strokeOpacity
        : ((typeof rt.opacity === 'number' && Number.isFinite(rt.opacity)) ? rt.opacity : 1);
      const strokeAbgr = cssColorToKmlAbgr(strokeColor, strokeOpacity);
      const styleKey = `line:${strokeAbgr}:${strokeWeight}`;
      const styleId = `style-line-${strokeAbgr}-${strokeWeight}`;

      if (!stylesMap.has(styleKey)) {
        stylesMap.set(styleKey, `    <Style id="${styleId}">\n      <LineStyle>\n        <color>${strokeAbgr}</color>\n        <width>${strokeWeight}</width>\n      </LineStyle>\n    </Style>\n`);
      }

      let totalNm = (typeof rt.distanceNm === 'number' && Number.isFinite(rt.distanceNm)) ? rt.distanceNm : 0;
      if (!totalNm && points.length >= 2) {
        const calcHaversine = Geodesy?.calculateDistanceHaversine;
        if (typeof calcHaversine === 'function') {
          for (let i = 0; i < points.length - 1; i++) {
            const d = calcHaversine(points[i].lat, points[i].lng, points[i + 1].lat, points[i + 1].lng);
            if (d && typeof d.nm === 'number') totalNm += d.nm;
          }
        } else {
          for (let i = 0; i < points.length - 1; i++) {
            totalNm += haversineNm(points[i].lat, points[i].lng, points[i + 1].lat, points[i + 1].lng);
          }
        }
      }
      const totalKm = totalNm * 1.852;

      let etaText = rt.etaText || '';
      if (!etaText && rt.vesselSpeedKnots && rt.vesselSpeedKnots > 0 && totalNm > 0) {
        const hours = totalNm / rt.vesselSpeedKnots;
        const h = Math.floor(hours);
        const m = Math.round((hours - h) * 60);
        etaText = h > 0 ? `${h} jam ${m} menit` : `${m} menit`;
      }

      let desc = rt.description ? `${rt.description} | ` : '';
      desc += `Jarak: ${totalNm.toFixed(2)} NM (${totalKm.toFixed(2)} km)`;
      if (etaText) {
        desc += ` | ETA: ${etaText}`;
      }

      const extEntries = [
        ['qrsar:sourceId', rt.id || rt.sourceId],
        ['qrsar:sarType', 'route'],
        ['qrsar:vesselSpeedKnots', rt.vesselSpeedKnots],
        ['qrsar:polygonRef', rt.polygonRef || rt.polygonId],
        ['qrsar:polygonName', rt.polygonName],
        ['qrsar:strokeColor', strokeColor],
        ['qrsar:strokeOpacity', strokeOpacity],
        ['qrsar:strokeWeight', strokeWeight]
      ];
      const extXml = renderExtendedData(extEntries);
      const coordsStr = points.map(pt => `${pt.lng},${pt.lat},0`).join(' ');

      routesPlacemarks += `      <Placemark>\n        <name>${escapeXml(rt.name || 'Rute Jarak')}</name>\n        <description>${escapeXml(desc)}</description>\n        <styleUrl>#${styleId}</styleUrl>\n${extXml}        <LineString>\n          <tessellate>1</tessellate>\n          <altitudeMode>clampToGround</altitudeMode>\n          <coordinates>${coordsStr}</coordinates>\n        </LineString>\n      </Placemark>\n`;
    });

    let stylesXml = '';
    stylesMap.forEach((styleDef) => {
      stylesXml += styleDef;
    });

    let kml = `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n  <Document>\n    <name>${escapeXml(opsName)}</name>\n`;
    if (stylesXml) {
      kml += stylesXml;
    }
    kml += `    <Folder>\n      <name>Titik Operasi SAR</name>\n${pointsPlacemarks}    </Folder>\n`;
    kml += `    <Folder>\n      <name>Area Sektor Poligon</name>\n${polygonsPlacemarks}    </Folder>\n`;
    kml += `    <Folder>\n      <name>Pola Pencarian SAR</name>\n${patternsPlacemarks}    </Folder>\n`;
    kml += `    <Folder>\n      <name>Rute dan Jalur Navigasi</name>\n${routesPlacemarks}    </Folder>\n`;
    kml += `  </Document>\n</kml>`;

    return kml;
  }

  /**
   * Generate RFC 7946 Standard GeoJSON FeatureCollection
   */
  function generateGeoJson(data) {
    const d = data || {};
    const points = Array.isArray(d.points) ? d.points : [];
    const polygons = Array.isArray(d.polygons) ? d.polygons : [];
    const patterns = Array.isArray(d.patterns) ? d.patterns : [];
    const routes = Array.isArray(d.routes) ? d.routes : [];

    const features = [];

    points.forEach((p) => {
      const lat = parseFloat(p.lat);
      const lng = parseFloat(p.lng);
      if (!isValidCoord(lat, lng)) return;

      const safeIcon = (p.pointType === 'custom_upload')
        ? extractSafeLocalIconPath(p.customIconUrl || p.iconUrl || p.customIconPath)
        : null;

      features.push({
        type: 'Feature',
        properties: {
          sourceId: p.sourceId || p.id || null,
          name: p.label || '',
          category: p.category || 'Tanpa Kategori',
          type: p.pointType || 'custom',
          alphabetChar: p.alphabetChar || null,
          customIconPath: safeIcon,
          id_operasi: p.id_operasi || null
        },
        geometry: {
          type: 'Point',
          coordinates: [lng, lat]
        }
      });
    });

    polygons.forEach((poly) => {
      let ringPoints = [];
      if (poly.shapeType === 'circle') {
        if (!poly.points || poly.points.length < 3) {
          if (Number.isFinite(poly.centerLat) && Number.isFinite(poly.centerLng) && Number.isFinite(poly.radiusNm) && poly.radiusNm > 0) {
            ringPoints = generateCircleRing(poly.centerLat, poly.centerLng, poly.radiusNm, 64);
          }
        } else {
          ringPoints = poly.points.filter(pt => isValidCoord(parseFloat(pt.lat), parseFloat(pt.lng)));
        }
      } else {
        ringPoints = (poly.points || []).filter(pt => isValidCoord(parseFloat(pt.lat), parseFloat(pt.lng)));
      }

      if (ringPoints.length < 3) return;

      const ring = ringPoints.map(pt => [parseFloat(pt.lng), parseFloat(pt.lat)]);
      const first = ring[0];
      const last = ring[ring.length - 1];
      if (first[0] !== last[0] || first[1] !== last[1]) {
        ring.push([first[0], first[1]]);
      }

      features.push({
        type: 'Feature',
        properties: {
          sourceId: poly.sourceId || poly.id || null,
          name: poly.name || 'Sektor',
          shapeType: poly.shapeType || 'polygon',
          centerLat: poly.centerLat !== undefined ? poly.centerLat : null,
          centerLng: poly.centerLng !== undefined ? poly.centerLng : null,
          radiusNm: poly.radiusNm !== undefined ? poly.radiusNm : null,
          areaNm2: poly.areaNm2 || null,
          id_operasi: poly.id_operasi || null
        },
        geometry: {
          type: 'Polygon',
          coordinates: [ring]
        }
      });
    });

    patterns.forEach((pat) => {
      const validWps = (pat.waypoints || []).filter(w => isValidCoord(parseFloat(w.lat), parseFloat(w.lng)));
      if (validWps.length < 2) return;

      features.push({
        type: 'Feature',
        properties: {
          sourceId: pat.sourceId || pat.id || null,
          name: pat.name || 'Pola SAR',
          type: pat.patternType,
          patternType: pat.patternType,
          trackSpacingNm: pat.trackSpacingNm,
          totalDistanceNm: pat.totalDistanceNm,
          legsCount: pat.legsCount,
          polygonRef: pat.polygonRef || pat.polygonId || null,
          id_operasi: pat.id_operasi || null
        },
        geometry: {
          type: 'LineString',
          coordinates: validWps.map(wp => [parseFloat(wp.lng), parseFloat(wp.lat)])
        }
      });
    });

    routes.forEach((rt) => {
      const validPts = (rt.points || []).filter(p => isValidCoord(parseFloat(p.lat), parseFloat(p.lng)));
      if (validPts.length < 2) return;

      features.push({
        type: 'Feature',
        properties: {
          sourceId: rt.sourceId || rt.id || null,
          name: rt.name || 'Rute Jarak & Navigasi',
          vesselSpeedKnots: rt.vesselSpeedKnots,
          distanceNm: rt.distanceNm,
          etaText: rt.etaText,
          polygonRef: rt.polygonRef || rt.polygonId || null,
          id_operasi: rt.id_operasi || null
        },
        geometry: {
          type: 'LineString',
          coordinates: validPts.map(pt => [parseFloat(pt.lng), parseFloat(pt.lat)])
        }
      });
    });

    return { type: 'FeatureCollection', features };
  }

  /**
   * Trigger browser file download via Blob anchor click
   */
  function triggerDownload(blob, filename) {
    if (typeof window === 'undefined' || !window.document) return;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => {
      try {
        URL.revokeObjectURL(url);
      } catch (e) {
        // ignore
      }
    }, 1500);
  }

  /**
   * Export and download KML file
   */
  function downloadKml(data, filename) {
    const kml = generateKmlDocument(data);
    const fname = filename || `SAR_KENDARI_PLOTTING_${new Date().toISOString().slice(0, 10)}.kml`;
    const blob = new Blob([kml], { type: 'application/vnd.google-earth.kml+xml' });
    triggerDownload(blob, fname);
  }

  /**
   * Export and download KMZ (Zipped KML) file
   */
  async function downloadKmz(data, filename) {
    const fname = filename || `SAR_KENDARI_PLOTTING_${new Date().toISOString().slice(0, 10)}.kmz`;
    if (typeof JSZip === 'undefined') {
      downloadKml(data, fname.replace(/\.kmz$/i, '.kml'));
      return;
    }
    const zip = new JSZip();
    zip.file('doc.kml', generateKmlDocument(data));
    const content = await zip.generateAsync({
      type: 'blob',
      compression: 'DEFLATE',
      mimeType: 'application/vnd.google-earth.kmz'
    });
    triggerDownload(content, fname);
  }

  /**
   * Export and download GeoJSON file
   */
  function downloadGeoJson(data, filename) {
    const geoJson = generateGeoJson(data);
    const fname = filename || `SAR_KENDARI_GEOJSON_${new Date().toISOString().slice(0, 10)}.geojson`;
    const blob = new Blob([JSON.stringify(geoJson, null, 2)], { type: 'application/json' });
    triggerDownload(blob, fname);
  }

  // Export to namespace
  window.QrSarGis.Export = {
    escapeXml,
    cssColorToKmlAbgr,
    generateCircleRing,
    extractSafeLocalIconPath,
    generateKmlDocument,
    generateGeoJson,
    downloadKml,
    downloadKmz,
    downloadGeoJson
  };

})(typeof window !== 'undefined' ? window : this);
