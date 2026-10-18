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
   * Generate valid KML 2.2 XML Document String
   */
  function generateKmlDocument(data) {
    const d = data || {};
    const opsName = d.operasiName || 'Plotting Operasi SAR Basarnas Kendari';
    const points = Array.isArray(d.points) ? d.points : [];
    const polygons = Array.isArray(d.polygons) ? d.polygons : [];
    const patterns = Array.isArray(d.patterns) ? d.patterns : [];
    const routes = Array.isArray(d.routes) ? d.routes : [];

    let kml = `<?xml version="1.0" encoding="UTF-8"?>\n<kml xmlns="http://www.opengis.net/kml/2.2">\n  <Document>\n    <name>${escapeXml(opsName)}</name>\n`;

    // Folder 1: Points
    kml += `    <Folder>\n      <name>Titik Operasi SAR</name>\n`;
    points.forEach((p) => {
      kml += `      <Placemark>\n        <name>${escapeXml(p.label || 'Titik')}</name>\n        <description>${escapeXml(p.description || '')} [${escapeXml(p.category || 'SAR')}]</description>\n        <Point><coordinates>${p.lng},${p.lat},0</coordinates></Point>\n      </Placemark>\n`;
    });
    kml += `    </Folder>\n`;

    // Folder 2: Polygons
    kml += `    <Folder>\n      <name>Area Sektor Poligon</name>\n`;
    polygons.forEach((poly) => {
      if (poly.points && poly.points.length >= 3) {
        const coordsStr = poly.points.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
        kml += `      <Placemark>\n        <name>${escapeXml(poly.name || 'Sektor')}</name>\n        <Polygon><outerBoundaryIs><LinearRing><coordinates>${coordsStr} ${poly.points[0].lng},${poly.points[0].lat},0</coordinates></LinearRing></outerBoundaryIs></Polygon>\n      </Placemark>\n`;
      }
    });
    kml += `    </Folder>\n`;

    // Folder 3: Patterns & Routes
    kml += `    <Folder>\n      <name>Rute dan Lintasan SAR</name>\n`;
    patterns.forEach((pat) => {
      if (pat.waypoints && pat.waypoints.length >= 2) {
        const coordsStr = pat.waypoints.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
        kml += `      <Placemark>\n        <name>${escapeXml(pat.name || 'Pola Lintasan')}</name>\n        <LineString><coordinates>${coordsStr}</coordinates></LineString>\n      </Placemark>\n`;
      }
    });
    routes.forEach((rt) => {
      if (rt.points && rt.points.length >= 2) {
        const coordsStr = rt.points.map((pt) => `${pt.lng},${pt.lat},0`).join(' ');
        kml += `      <Placemark>\n        <name>${escapeXml(rt.name || 'Rute Jarak')}</name>\n        <LineString><coordinates>${coordsStr}</coordinates></LineString>\n      </Placemark>\n`;
      }
    });
    kml += `    </Folder>\n  </Document>\n</kml>`;

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
      features.push({
        type: 'Feature',
        properties: {
          name: p.label || '',
          category: p.category || 'Tanpa Kategori',
          type: p.pointType || 'custom',
          id_operasi: p.id_operasi || null
        },
        geometry: {
          type: 'Point',
          coordinates: [parseFloat(p.lng), parseFloat(p.lat)]
        }
      });
    });

    polygons.forEach((poly) => {
      if (poly.points && poly.points.length >= 3) {
        const ring = poly.points.map((pt) => [parseFloat(pt.lng), parseFloat(pt.lat)]);
        ring.push([parseFloat(poly.points[0].lng), parseFloat(poly.points[0].lat)]);
        features.push({
          type: 'Feature',
          properties: {
            name: poly.name || 'Sektor',
            id_operasi: poly.id_operasi || null,
            areaNm2: poly.areaNm2 || null
          },
          geometry: {
            type: 'Polygon',
            coordinates: [ring]
          }
        });
      }
    });

    patterns.forEach((pat) => {
      if (pat.waypoints && pat.waypoints.length >= 2) {
        features.push({
          type: 'Feature',
          properties: {
            name: pat.name || 'Pola SAR',
            type: pat.patternType,
            trackSpacingNm: pat.trackSpacingNm,
            totalDistanceNm: pat.totalDistanceNm,
            legsCount: pat.legsCount,
            id_operasi: pat.id_operasi || null
          },
          geometry: {
            type: 'LineString',
            coordinates: pat.waypoints.map((wp) => [parseFloat(wp.lng), parseFloat(wp.lat)])
          }
        });
      }
    });

    routes.forEach((rt) => {
      if (rt.points && rt.points.length >= 2) {
        features.push({
          type: 'Feature',
          properties: {
            name: rt.name || 'Rute Jarak & Navigasi',
            vesselSpeedKnots: rt.vesselSpeedKnots,
            distanceNm: rt.distanceNm,
            etaText: rt.etaText,
            id_operasi: rt.id_operasi || null
          },
          geometry: {
            type: 'LineString',
            coordinates: rt.points.map((pt) => [parseFloat(pt.lng), parseFloat(pt.lat)])
          }
        });
      }
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
    URL.revokeObjectURL(url);
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
    const content = await zip.generateAsync({ type: 'blob' });
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
    generateKmlDocument,
    generateGeoJson,
    downloadKml,
    downloadKmz,
    downloadGeoJson
  };

})(typeof window !== 'undefined' ? window : this);
