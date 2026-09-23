/**
 * Utility Ekspor Spasial KML, KMZ, dan GeoJSON untuk Basarnas GIS
 * Menghasilkan file standar OGC KML 2.2 yang 100% kompatibel dengan Google Earth, Garmin GPS, ArcGIS, QGIS, dan ATAK.
 */

import JSZip from 'jszip';
import {
  LatLngPoint,
  SavedPoint,
  SavedPolygon,
  SavedSearchPattern,
  SavedDistanceRoute,
  decimalToDms,
  calculatePolylineDistance,
} from './geoUtils';

/**
 * Konversi warna Hex (#RRGGBB) ke format KML Color (AABBGGRR)
 */
export function hexToKmlColor(hex: string = '#ea580c', alpha: number = 1.0): string {
  let cleanHex = hex.replace('#', '');
  if (cleanHex.length === 3) {
    cleanHex = cleanHex.split('').map((c) => c + c).join('');
  }
  if (cleanHex.length !== 6) {
    cleanHex = 'ff7800'; // Default Orange
  }

  const r = cleanHex.substring(0, 2);
  const g = cleanHex.substring(2, 4);
  const b = cleanHex.substring(4, 6);
  const a = Math.round(Math.min(Math.max(alpha, 0), 1) * 255)
    .toString(16)
    .padStart(2, '0');

  // KML uses Alpha-Blue-Green-Red order
  return `${a}${b}${g}${r}`;
}

/**
 * Generate string XML KML 2.2 dari data layer GIS aktif
 */
export function generateKmlDocument(
  points: SavedPoint[],
  polygons: SavedPolygon[],
  patterns: SavedSearchPattern[],
  routes: SavedDistanceRoute[],
  documentTitle: string = 'Plotting Operasi SAR Basarnas'
): string {
  const timestamp = new Date().toISOString();

  // Helper escape XML
  const escapeXml = (unsafe: string = '') =>
    unsafe
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&apos;');

  let kml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2">
  <Document>
    <name>${escapeXml(documentTitle)}</name>
    <description>Hasil Plotting Peta &amp; Rencana Operasi SAR Basarnas (Diekspor: ${timestamp})</description>

    <!-- STYLE DEFINITIONS -->
    <Style id="style-point-lkp">
      <IconStyle>
        <color>ff0000ff</color>
        <scale>1.2</scale>
        <Icon><href>http://maps.google.com/mapfiles/kml/paddle/red-circle.png</href></Icon>
      </IconStyle>
      <LabelStyle><scale>0.9</scale></LabelStyle>
    </Style>
    <Style id="style-point-posko">
      <IconStyle>
        <color>ffffaa00</color>
        <scale>1.2</scale>
        <Icon><href>http://maps.google.com/mapfiles/kml/paddle/blu-circle.png</href></Icon>
      </IconStyle>
      <LabelStyle><scale>0.9</scale></LabelStyle>
    </Style>
    <Style id="style-point-default">
      <IconStyle>
        <color>ff00aaff</color>
        <scale>1.1</scale>
        <Icon><href>http://maps.google.com/mapfiles/kml/paddle/orange-circle.png</href></Icon>
      </IconStyle>
      <LabelStyle><scale>0.9</scale></LabelStyle>
    </Style>
`;

  // 1. FOLDER: TITIK-TITIK OPERASI SAR
  kml += `    <Folder>
      <name>1. Titik Operasi SAR (${points.length} Titik)</name>
      <open>1</open>
`;

  points.forEach((pt, idx) => {
    const dms = decimalToDms(pt.lat, pt.lng);
    const styleId =
      pt.pointType === 'lkp'
        ? 'style-point-lkp'
        : pt.pointType === 'posko_sar' || pt.pointType === 'kantor'
        ? 'style-point-posko'
        : 'style-point-default';

    kml += `      <Placemark id="point-${pt.id || idx}">
        <name>${escapeXml(pt.label || `Titik SAR #${idx + 1}`)}</name>
        <description><![CDATA[
          <b>Tipe:</b> ${escapeXml(pt.pointType || 'Titik Spasial')}<br/>
          <b>Koordinat Desimal:</b> ${pt.lat.toFixed(6)}, ${pt.lng.toFixed(6)}<br/>
          <b>Koordinat DMS:</b> ${dms}<br/>
          ${pt.notes ? `<b>Catatan:</b> ${escapeXml(pt.notes)}<br/>` : ''}
          ${pt.createdAt ? `<b>Waktu Input:</b> ${pt.createdAt}<br/>` : ''}
        ]]></description>
        <styleUrl>#${styleId}</styleUrl>
        <Point>
          <coordinates>${pt.lng},${pt.lat},0</coordinates>
        </Point>
      </Placemark>
`;
  });

  kml += `    </Folder>
`;

  // 2. FOLDER: AREA SEKTOR PENCARIAN (POLYGONS)
  kml += `    <Folder>
      <name>2. Area Pencarian / Poligon Sektor (${polygons.length} Area)</name>
      <open>1</open>
`;

  polygons.forEach((poly, idx) => {
    const strokeKmlColor = hexToKmlColor(poly.strokeColor || '#ea580c', 1.0);
    const fillKmlColor = hexToKmlColor(poly.fillColor || '#ea580c', poly.fillOpacity ?? 0.25);
    const customStyleId = `style-poly-${poly.id || idx}`;

    kml += `      <Style id="${customStyleId}">
        <LineStyle>
          <color>${strokeKmlColor}</color>
          <width>${poly.strokeWeight || 2.5}</width>
        </LineStyle>
        <PolyStyle>
          <color>${fillKmlColor}</color>
          <fill>1</fill>
          <outline>1</outline>
        </PolyStyle>
      </Style>
      <Placemark id="polygon-${poly.id || idx}">
        <name>${escapeXml(poly.name || `Sektor Area #${idx + 1}`)}</name>
        <description><![CDATA[
          <b>Nama Area:</b> ${escapeXml(poly.name)}<br/>
          <b>Jumlah Sudut:</b> ${poly.points.length} Titik<br/>
          <b>Koordinat Sudut:</b><br/>
          ${poly.points
            .map(
              (p: LatLngPoint, pIdx: number) =>
                `• Titik ${p.label || `#${pIdx + 1}`}: ${decimalToDms(p.lat, p.lng)} (${p.lat.toFixed(5)}, ${p.lng.toFixed(5)})`
            )
            .join('<br/>')}
        ]]></description>
        <styleUrl>#${customStyleId}</styleUrl>
        <Polygon>
          <extrude>1</extrude>
          <altitudeMode>clampToGround</altitudeMode>
          <outerBoundaryIs>
            <LinearRing>
              <coordinates>
                ${poly.points.map((p: LatLngPoint) => `${p.lng},${p.lat},0`).join(' ')}
                ${poly.points.length > 0 ? ` ${poly.points[0].lng},${poly.points[0].lat},0` : ''}
              </coordinates>
            </LinearRing>
          </outerBoundaryIs>
        </Polygon>
      </Placemark>
`;
  });

  kml += `    </Folder>
`;

  // 3. FOLDER: POLA PENCARIAN SAR (IAMSAR SEARCH PATTERNS)
  kml += `    <Folder>
      <name>3. Pola Pencarian SAR IAMSAR (${patterns.length} Pola)</name>
      <open>1</open>
`;

  patterns.forEach((pat, idx) => {
    const patColor = hexToKmlColor(pat.strokeColor || pat.color || '#0284c7', 1.0);
    const patStyleId = `style-pattern-${pat.id || idx}`;
    const patTypeName = pat.patternType || pat.type || 'Pola SAR';

    kml += `      <Style id="${patStyleId}">
        <LineStyle>
          <color>${patColor}</color>
          <width>3.0</width>
        </LineStyle>
      </Style>
      <Placemark id="pattern-${pat.id || idx}">
        <name>${escapeXml(pat.name || `Pola Pencarian #${idx + 1}`)}</name>
        <description><![CDATA[
          <b>Tipe Pola:</b> ${escapeXml(patTypeName)}<br/>
          <b>Track Spacing (S):</b> ${pat.trackSpacingNm || 2.0} NM<br/>
          <b>Jumlah Lintasan (Legs):</b> ${pat.legsCount || pat.waypoints.length}<br/>
          ${pat.polygonName ? `<b>Batas Polygon:</b> ${escapeXml(pat.polygonName)}<br/>` : ''}
          ${pat.poskoName ? `<b>Posko Acuan:</b> ${escapeXml(pat.poskoName)}<br/>` : ''}
          <b>Jumlah Waypoint Lintasan:</b> ${pat.waypoints.length} Titik Sudut<br/>
        ]]></description>
        <styleUrl>#${patStyleId}</styleUrl>
        <LineString>
          <tessellate>1</tessellate>
          <altitudeMode>clampToGround</altitudeMode>
          <coordinates>
            ${pat.waypoints.map((p: LatLngPoint) => `${p.lng},${p.lat},0`).join(' ')}
          </coordinates>
        </LineString>
      </Placemark>
`;
  });

  kml += `    </Folder>
`;

  // 4. FOLDER: RUTE PENGUKURAN & AZIMUTH
  kml += `    <Folder>
      <name>4. Rute Jarak &amp; Azimuth Navigasi (${routes.length} Rute)</name>
      <open>1</open>
`;

  routes.forEach((rt, idx) => {
    const rtColor = hexToKmlColor(rt.strokeColor || '#059669', rt.strokeOpacity ?? 0.9);
    const rtStyleId = `style-route-${rt.id || idx}`;
    const dist = calculatePolylineDistance(rt.points);
    const totalNm = rt.totalDistanceNm ?? dist.totalNm;
    const totalKm = rt.totalDistanceKm ?? dist.totalKm;

    kml += `      <Style id="${rtStyleId}">
        <LineStyle>
          <color>${rtColor}</color>
          <width>${rt.strokeWeight || 3.0}</width>
        </LineStyle>
      </Style>
      <Placemark id="route-${rt.id || idx}">
        <name>${escapeXml(rt.name || `Rute Navigasi #${idx + 1}`)}</name>
        <description><![CDATA[
          <b>Mode:</b> ${rt.mode === 'straight' ? 'Jarak Lurus (Azimuth)' : 'Jalur Multi-Point'}<br/>
          <b>Total Jarak:</b> ${totalNm.toFixed(2)} NM (${totalKm.toFixed(2)} km)<br/>
          ${rt.headingFormatted ? `<b>Haluan / Azimuth:</b> ${escapeXml(rt.headingFormatted)}<br/>` : ''}
          ${rt.vesselSpeedKnots ? `<b>Kecepatan Alut:</b> ${rt.vesselSpeedKnots} Knots<br/>` : ''}
          <b>Jumlah Titik:</b> ${rt.points.length} Titik<br/>
        ]]></description>
        <styleUrl>#${rtStyleId}</styleUrl>
        <LineString>
          <tessellate>1</tessellate>
          <altitudeMode>clampToGround</altitudeMode>
          <coordinates>
            ${rt.points.map((p: LatLngPoint) => `${p.lng},${p.lat},0`).join(' ')}
          </coordinates>
        </LineString>
      </Placemark>
`;
  });

  kml += `    </Folder>
  </Document>
</kml>`;

  return kml;
}

/**
 * Generate standard GeoJSON FeatureCollection dari seluruh layer peta
 */
export function generateGeoJson(
  points: SavedPoint[],
  polygons: SavedPolygon[],
  patterns: SavedSearchPattern[],
  routes: SavedDistanceRoute[]
) {
  const features: any[] = [];

  // Points
  points.forEach((pt) => {
    features.push({
      type: 'Feature',
      id: pt.id,
      properties: {
        layerType: 'point',
        label: pt.label,
        pointType: pt.pointType,
        dms: decimalToDms(pt.lat, pt.lng),
        notes: pt.notes,
        createdAt: pt.createdAt,
      },
      geometry: {
        type: 'Point',
        coordinates: [pt.lng, pt.lat],
      },
    });
  });

  // Polygons
  polygons.forEach((poly) => {
    const coords = poly.points.map((p: LatLngPoint) => [p.lng, p.lat]);
    if (coords.length > 0) coords.push([poly.points[0].lng, poly.points[0].lat]); // Close ring

    features.push({
      type: 'Feature',
      id: poly.id,
      properties: {
        layerType: 'polygon',
        name: poly.name,
        strokeColor: poly.strokeColor,
        strokeWeight: poly.strokeWeight,
        fillColor: poly.fillColor,
        fillOpacity: poly.fillOpacity,
        pointsCount: poly.points.length,
      },
      geometry: {
        type: 'Polygon',
        coordinates: [coords],
      },
    });
  });

  // Patterns
  patterns.forEach((pat) => {
    features.push({
      type: 'Feature',
      id: pat.id,
      properties: {
        layerType: 'search_pattern',
        name: pat.name,
        patternType: pat.patternType || pat.type,
        trackSpacingNm: pat.trackSpacingNm,
        legsCount: pat.legsCount,
        color: pat.strokeColor || pat.color,
      },
      geometry: {
        type: 'LineString',
        coordinates: pat.waypoints.map((w: LatLngPoint) => [w.lng, w.lat]),
      },
    });
  });

  // Routes
  routes.forEach((rt) => {
    const dist = calculatePolylineDistance(rt.points);
    const totalNm = rt.totalDistanceNm ?? dist.totalNm;
    const totalKm = rt.totalDistanceKm ?? dist.totalKm;

    features.push({
      type: 'Feature',
      id: rt.id,
      properties: {
        layerType: 'distance_route',
        name: rt.name,
        mode: rt.mode,
        totalDistanceNm: totalNm,
        totalDistanceKm: totalKm,
        headingFormatted: rt.headingFormatted,
        strokeColor: rt.strokeColor,
      },
      geometry: {
        type: 'LineString',
        coordinates: rt.points.map((p: LatLngPoint) => [p.lng, p.lat]),
      },
    });
  });

  return {
    type: 'FeatureCollection',
    name: 'Basarnas_SAR_GIS_Data',
    crs: {
      type: 'name',
      properties: { name: 'urn:ogc:def:crs:OGC:1.3:CRS84' },
    },
    features,
  };
}

/**
 * Download KML file to browser
 */
export function downloadKml(
  points: SavedPoint[],
  polygons: SavedPolygon[],
  patterns: SavedSearchPattern[],
  routes: SavedDistanceRoute[],
  baseFileName: string = 'Plotting_Operasi_SAR_Basarnas'
) {
  const kmlContent = generateKmlDocument(points, polygons, patterns, routes, baseFileName);
  const blob = new Blob([kmlContent], { type: 'application/vnd.google-earth.kml+xml;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${baseFileName}_${new Date().toISOString().slice(0, 10)}.kml`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * Download compressed KMZ (zip of doc.kml) to browser
 */
export async function downloadKmz(
  points: SavedPoint[],
  polygons: SavedPolygon[],
  patterns: SavedSearchPattern[],
  routes: SavedDistanceRoute[],
  baseFileName: string = 'Plotting_Operasi_SAR_Basarnas'
) {
  const kmlContent = generateKmlDocument(points, polygons, patterns, routes, baseFileName);
  const zip = new JSZip();
  zip.file('doc.kml', kmlContent);

  const kmzBlob = await zip.generateAsync({
    type: 'blob',
    compression: 'DEFLATE',
    compressionOptions: { level: 9 },
  });

  const url = URL.createObjectURL(kmzBlob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${baseFileName}_${new Date().toISOString().slice(0, 10)}.kmz`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}

/**
 * Download GeoJSON file to browser
 */
export function downloadGeoJson(
  points: SavedPoint[],
  polygons: SavedPolygon[],
  patterns: SavedSearchPattern[],
  routes: SavedDistanceRoute[],
  baseFileName: string = 'Plotting_Operasi_SAR_Basarnas'
) {
  const geojson = generateGeoJson(points, polygons, patterns, routes);
  const blob = new Blob([JSON.stringify(geojson, null, 2)], { type: 'application/geo+json;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${baseFileName}_${new Date().toISOString().slice(0, 10)}.geojson`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(url);
}
