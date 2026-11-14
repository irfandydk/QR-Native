'use strict';

const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function loadGisModules(options = {}) {
  const windowObj = {};
  const sandbox = {
    window: windowObj,
    self: windowObj,
    get JSZip() { return global.JSZip || windowObj.JSZip; },
    set JSZip(val) { windowObj.JSZip = val; },
    global,
    globalThis: windowObj,
    setTimeout,
    clearTimeout,
    setImmediate,
    clearImmediate,
    console,
    Math,
    JSON,
    parseFloat,
    parseInt,
    Number,
    String,
    Boolean,
    Array,
    Date,
    Buffer,
    Uint8Array,
    ArrayBuffer,
    TextDecoder,
    TextEncoder,
    Promise,
    RegExp,
    Error,
    Map,
    Set
  };
  vm.createContext(sandbox);

  if (options.includeJSZip) {
    const jszipPath = path.resolve(__dirname, '../../assets/vendor/jszip/jszip.min.js');
    if (fs.existsSync(jszipPath)) {
      vm.runInContext(fs.readFileSync(jszipPath, 'utf8'), sandbox);
    }
  }

  const baseDir = path.resolve(__dirname, '../../assets/js/gis');
  if (options.includeGeodesy !== false) {
    vm.runInContext(fs.readFileSync(path.join(baseDir, 'geodesy.js'), 'utf8'), sandbox);
  }
  if (options.includeMarkers !== false) {
    vm.runInContext(fs.readFileSync(path.join(baseDir, 'markers.js'), 'utf8'), sandbox);
  }
  if (options.includePatterns !== false) {
    vm.runInContext(fs.readFileSync(path.join(baseDir, 'patterns.js'), 'utf8'), sandbox);
  }
  if (options.includeExport !== false) {
    vm.runInContext(fs.readFileSync(path.join(baseDir, 'export.js'), 'utf8'), sandbox);
  }
  if (options.includeImporter !== false) {
    vm.runInContext(fs.readFileSync(path.join(baseDir, 'kml_importer.js'), 'utf8'), sandbox);
  }

  if (options.returnSandbox) {
    return sandbox;
  }
  return sandbox.window.QrSarGis;
}

test('cssColorToKmlAbgr converts #rrggbb, #rgb, and opacity to aabbggrr', () => {
  const QrSarGis = loadGisModules();
  const { cssColorToKmlAbgr } = QrSarGis.Export;

  assert.equal(typeof cssColorToKmlAbgr, 'function');
  assert.equal(cssColorToKmlAbgr('#dc2626', 1), 'ff2626dc');
  assert.equal(cssColorToKmlAbgr('#0ea5e9', 0.25), '40e9a50e');
  assert.equal(cssColorToKmlAbgr('#f00', 0.5), '800000ff');
  assert.equal(cssColorToKmlAbgr('invalid'), 'ffea580c');
  assert.equal(cssColorToKmlAbgr(null), 'ffea580c');
  assert.equal(cssColorToKmlAbgr(''), 'ffea580c');
});

test('extractSafeLocalIconPath accepts only local assets/img and uploads/icons paths', () => {
  const QrSarGis = loadGisModules();
  const { extractSafeLocalIconPath } = QrSarGis.Export;

  assert.equal(typeof extractSafeLocalIconPath, 'function');
  assert.equal(extractSafeLocalIconPath('assets/img/basarnas-logo.png'), 'assets/img/basarnas-logo.png');
  assert.equal(extractSafeLocalIconPath('https://qrsar.local/QR-SAR/uploads/icons/icon_123.png'), 'uploads/icons/icon_123.png');
  assert.equal(extractSafeLocalIconPath('data:image/png;base64,AAA'), null);
  assert.equal(extractSafeLocalIconPath('https://evil.com/uploads/icons/../secret.png'), null);
});

test('generateKmlDocument outputs Styles, tessellate, ExtendedData, 64-pt circle fallback, E-SAR MultiGeometry, and escaped XML', () => {
  const QrSarGis = loadGisModules();
  const { generateKmlDocument } = QrSarGis.Export;

  const sampleData = {
    operasiName: 'Ops SAR Kendari 2026',
    points: [
      {
        id: 'pt-1',
        sourceId: 'pt-1',
        label: 'LKP <Test> & "Rescue"',
        description: "Desc with 'quotes' & <tags>",
        pointType: 'lkp',
        category: 'kejadian',
        lat: -4.0376,
        lng: 122.4866
      },
      {
        id: 'pt-2',
        label: 'Tim SAR Posko',
        pointType: 'custom_upload',
        customIconUrl: 'http://host/uploads/icons/team.png',
        lat: -4.0500,
        lng: 122.5000
      },
      {
        id: 'pt-invalid',
        label: 'Invalid NaN Point',
        lat: NaN,
        lng: 122.0
      }
    ],
    polygons: [
      {
        id: 'poly-1',
        sourceId: 'poly-1',
        name: 'Area Sektor Alpha',
        shapeType: 'polygon',
        strokeColor: '#0284c7',
        fillColor: '#0ea5e9',
        fillOpacity: 0.3,
        points: [
          { lat: -4.0, lng: 122.0 },
          { lat: -4.0, lng: 122.2 },
          { lat: -4.2, lng: 122.2 },
          { lat: -4.2, lng: 122.0 }
        ]
      },
      {
        id: 'poly-circle',
        sourceId: 'poly-circle',
        name: 'Area Sektor Circle',
        shapeType: 'circle',
        points: [],
        centerLat: -4.0,
        centerLng: 122.5,
        radiusNm: 2.0
      }
    ],
    patterns: [
      {
        id: 'pat-1',
        sourceId: 'pat-1',
        name: 'PS Lintasan',
        patternType: 'parallel_track',
        polygonId: 'poly-1',
        trackSpacingNm: 1.0,
        legsCount: 4,
        waypoints: [
          { lat: -4.01, lng: 122.01 },
          { lat: -4.01, lng: 122.19 },
          { lat: -4.05, lng: 122.19 },
          { lat: -4.05, lng: 122.01 }
        ]
      },
      {
        id: 'pat-2',
        sourceId: 'pat-2',
        name: 'Pola Pencarian E-SAR',
        patternType: 'sector_search',
        polygonId: 'poly-1',
        waypoints: [
          { lat: -4.10, lng: 122.10, label: 'W1' },
          { lat: -4.12, lng: 122.10, label: 'W2' },
          { lat: -4.10, lng: 122.12, label: 'W3' },
          { lat: -4.12, lng: 122.12, label: 'W4' },
          { lat: -4.10, lng: 122.14, label: 'W5' },
          { lat: -4.12, lng: 122.14, label: 'W6' }
        ]
      }
    ],
    routes: [
      {
        id: 'rt-1',
        sourceId: 'rt-1',
        name: 'Rute Alpha',
        polygonId: 'poly-1',
        vesselSpeedKnots: 20,
        points: [
          { lat: -4.0, lng: 122.0 },
          { lat: -4.1, lng: 122.1 }
        ]
      }
    ]
  };

  const kml = generateKmlDocument(sampleData);

  assert.ok(kml.includes('xmlns="http://www.opengis.net/kml/2.2"'), 'Must have KML 2.2 namespace');
  assert.ok(kml.includes('<Style id="style-'), 'Must declare reusable Style elements');
  assert.ok(kml.includes('ff2626dc'), 'Must contain aabbggrr color for lkp red point');
  assert.ok(kml.includes('<tessellate>1</tessellate>'), 'Must include tessellate for terrain clamping');
  assert.ok(kml.includes('<Folder><name>Titik Operasi SAR</name>') || kml.includes('<name>Titik Operasi SAR</name>'), 'Must contain Points folder');
  assert.ok(kml.includes('Area Sektor Poligon'), 'Must contain Polygon folder');
  assert.ok(kml.includes('Pola Pencarian SAR'), 'Must contain Patterns folder');
  assert.ok(kml.includes('Rute dan Jalur Navigasi'), 'Must contain Routes folder');

  // XML escaping
  assert.ok(kml.includes('&amp;'), 'Must escape ampersand');
  assert.ok(kml.includes('&lt;'), 'Must escape less-than');
  assert.ok(kml.includes('&gt;'), 'Must escape greater-than');
  assert.ok(kml.includes('&quot;'), 'Must escape double quote');
  assert.ok(kml.includes('&apos;'), 'Must escape single quote');

  // Skips NaN point
  assert.ok(!kml.includes('Invalid NaN Point'), 'Must skip point with NaN coordinates');
  assert.ok(!kml.includes('NaN'), 'KML must not contain NaN');

  // Circle polygon has 65 coordinate tuples (64 points + 1 closing point)
  const circleMatch = kml.match(/<Placemark>[\s\S]*?<name>Area Sektor Circle<\/name>[\s\S]*?<coordinates>([\s\S]*?)<\/coordinates>[\s\S]*?<\/Placemark>/);
  assert.ok(circleMatch, 'Must find Area Sektor Circle placemark');
  const circleCoords = circleMatch[1].trim().split(/\s+/);
  assert.equal(circleCoords.length, 65, 'Circle Placemark must have 65 coordinate tuples (64 ring points + closing point)');
  assert.equal(circleCoords[0], circleCoords[64], 'First coordinate must equal 65th coordinate in closed circle ring');

  // E-SAR Placemark contains <MultiGeometry> and qrsar:waypointsJson
  const esarMatch = kml.match(/<Placemark>[\s\S]*?<name>Pola Pencarian E-SAR<\/name>[\s\S]*?<\/Placemark>/);
  assert.ok(esarMatch, 'Must find E-SAR Placemark');
  assert.ok(esarMatch[0].includes('<MultiGeometry>'), 'E-SAR Placemark must contain <MultiGeometry>');
  assert.ok(esarMatch[0].includes('name="qrsar:waypointsJson"'), 'E-SAR Placemark ExtendedData must contain qrsar:waypointsJson');

  // Route and pattern contain qrsar:polygonRef
  assert.ok(kml.includes('name="qrsar:polygonRef"'), 'KML must contain qrsar:polygonRef ExtendedData');
  assert.ok(kml.includes('<value>poly-1</value>'), 'qrsar:polygonRef value must be poly-1');

  // Local icon emits qrsar:customIconPath with uploads/icons/team.png
  assert.ok(kml.includes('name="qrsar:customIconPath"'), 'Must contain qrsar:customIconPath ExtendedData');
  assert.ok(kml.includes('uploads/icons/team.png'), 'Must extract uploads/icons/team.png');
});

test('generateGeoJson synthesizes 64-point ring when circle polygon has empty points', () => {
  const QrSarGis = loadGisModules();
  const { generateGeoJson } = QrSarGis.Export;

  const geoJson = generateGeoJson({
    polygons: [
      {
        id: 'poly-circle-1',
        name: 'Lingkaran SAR',
        shapeType: 'circle',
        points: [],
        centerLat: -4.0,
        centerLng: 122.5,
        radiusNm: 2.0
      }
    ]
  });

  assert.equal(geoJson.type, 'FeatureCollection');
  assert.equal(geoJson.features.length, 1);
  const polyFeature = geoJson.features[0];
  assert.equal(polyFeature.geometry.type, 'Polygon');
  const ring = polyFeature.geometry.coordinates[0];
  assert.equal(ring.length, 65);
  assert.deepEqual(ring[0], ring[64]);
});

test('export.js loads cleanly and functions when siblings are absent', () => {
  const QrSarGis = loadGisModules({
    includeGeodesy: false,
    includeMarkers: false,
    includePatterns: false
  });
  const { generateKmlDocument, generateGeoJson } = QrSarGis.Export;

  const kml = generateKmlDocument({
    operasiName: 'Standalone Test',
    points: [{ id: 'p1', label: 'Point 1', lat: -4.0, lng: 122.0 }],
    polygons: [{ id: 'poly1', points: [{ lat: -4.0, lng: 122.0 }, { lat: -4.0, lng: 122.1 }, { lat: -4.1, lng: 122.1 }] }],
    patterns: [{ id: 'pat1', patternType: 'sector_search', waypoints: [{ lat: -4.0, lng: 122.0 }, { lat: -4.1, lng: 122.0 }] }],
    routes: [{ id: 'rt1', points: [{ lat: -4.0, lng: 122.0 }, { lat: -4.1, lng: 122.1 }] }]
  });
  assert.ok(kml.includes('Standalone Test'));

  const geoJson = generateGeoJson({
    points: [{ id: 'p1', label: 'Point 1', lat: -4.0, lng: 122.0 }]
  });
  assert.equal(geoJson.type, 'FeatureCollection');
  assert.equal(geoJson.features.length, 1);
});

test('circle polygon with stringified centerLat, centerLng, radiusNm and empty points synthesizes ring in KML and GeoJSON', () => {
  const QrSarGis = loadGisModules();
  const { generateKmlDocument, generateGeoJson } = QrSarGis.Export;

  const stringifiedCircle = {
    id: 'poly-circle-str',
    name: 'Lingkaran Stringified',
    shapeType: 'circle',
    points: [],
    centerLat: '-4.0376',
    centerLng: '122.4866',
    radiusNm: '2.5'
  };

  // 1. KML generation
  const kml = generateKmlDocument({
    polygons: [stringifiedCircle]
  });
  const circleMatch = kml.match(/<Placemark>[\s\S]*?<name>Lingkaran Stringified<\/name>[\s\S]*?<coordinates>([\s\S]*?)<\/coordinates>[\s\S]*?<\/Placemark>/);
  assert.ok(circleMatch, 'KML must contain Placemark for stringified circle');
  const kmlCoords = circleMatch[1].trim().split(/\s+/);
  assert.equal(kmlCoords.length, 65, 'KML coordinates must have 65 tuples');
  assert.equal(kmlCoords[0], kmlCoords[64], 'KML first and last coordinates must match');

  // 2. GeoJSON generation
  const geoJson = generateGeoJson({
    polygons: [stringifiedCircle]
  });
  assert.equal(geoJson.type, 'FeatureCollection');
  assert.equal(geoJson.features.length, 1, 'GeoJSON must contain 1 feature for stringified circle');
  const ring = geoJson.features[0].geometry.coordinates[0];
  assert.equal(ring.length, 65, 'GeoJSON ring must have 65 coordinates');
  assert.deepEqual(ring[0], ring[64], 'GeoJSON first and last coordinates must match');
});

test('lossless round-trip QR-SAR -> Export.generateKmlDocument -> KmlImporter.parseKmlString', () => {
  const QrSarGis = loadGisModules();
  const { generateKmlDocument } = QrSarGis.Export;
  const { parseKmlString } = QrSarGis.KmlImporter;

  const sampleData = {
    operasiName: 'Ops SAR Kendari Roundtrip',
    points: [
      {
        id: 'pt-lkp',
        sourceId: 'pt-lkp',
        label: 'LKP Titik Duga Kapal',
        description: 'Terlihat suar darurat di perairan',
        pointType: 'lkp',
        category: 'kejadian',
        alphabetChar: 'A',
        lat: -4.0376,
        lng: 122.4866
      },
      {
        id: 'pt-custom',
        sourceId: 'pt-custom',
        label: 'Posko Utama Basarnas',
        description: 'Pusat Komando Kendari',
        pointType: 'custom_upload',
        customIconUrl: 'assets/img/basarnas-logo.png',
        category: 'tempat',
        lat: -4.0500,
        lng: 122.5000
      }
    ],
    polygons: [
      {
        id: 'poly-src-1',
        sourceId: 'poly-src-1',
        name: 'Area Sektor Alpha',
        shapeType: 'polygon',
        strokeColor: '#0284c7',
        fillColor: '#0ea5e9',
        fillOpacity: 0.3,
        strokeWeight: 2,
        points: [
          { lat: -4.0, lng: 122.0 },
          { lat: -4.0, lng: 122.2 },
          { lat: -4.2, lng: 122.2 },
          { lat: -4.2, lng: 122.0 }
        ]
      },
      {
        id: 'poly-circle-1',
        sourceId: 'poly-circle-1',
        name: 'Area Sektor Lingkaran',
        shapeType: 'circle',
        centerLat: -4.02,
        centerLng: 122.52,
        radiusNm: 2.5,
        points: []
      },
      {
        id: 'poly-rect-1',
        sourceId: 'poly-rect-1',
        name: 'Area Sektor Kotak',
        shapeType: 'rectangle',
        centerLat: -4.1,
        centerLng: 122.3,
        boxWidthNm: 3,
        boxHeightNm: 2,
        points: [
          { lat: -4.08, lng: 122.28 },
          { lat: -4.08, lng: 122.32 },
          { lat: -4.12, lng: 122.32 },
          { lat: -4.12, lng: 122.28 }
        ]
      }
    ],
    patterns: [
      {
        id: 'pat-esar',
        sourceId: 'pat-esar',
        name: 'Pola Pencarian E-SAR Sektor',
        patternType: 'sector_search',
        polygonId: 'poly-src-1',
        polygonRef: 'poly-src-1',
        flipH: true,
        waypoints: [
          { lat: -4.01, lng: 122.01, label: 'W1' },
          { lat: -4.02, lng: 122.02, label: 'W2' },
          { lat: -4.03, lng: 122.03, label: 'W3' },
          { lat: -4.04, lng: 122.04, label: 'W4' },
          { lat: -4.05, lng: 122.05, label: 'W5' },
          { lat: -4.06, lng: 122.06, label: 'W6' }
        ]
      },
      {
        id: 'pat-parallel',
        sourceId: 'pat-parallel',
        name: 'Pola Parallel Lintasan',
        patternType: 'parallel_track',
        polygonId: 'datum_kansar',
        polygonRef: 'datum_kansar',
        waypoints: [
          { lat: -4.10, lng: 122.10 },
          { lat: -4.10, lng: 122.20 },
          { lat: -4.15, lng: 122.20 },
          { lat: -4.15, lng: 122.10 }
        ]
      }
    ],
    routes: [
      {
        id: 'rt-nav-1',
        sourceId: 'rt-nav-1',
        name: 'Rute Navigasi KN SAR',
        polygonId: 'poly-src-1',
        polygonRef: 'poly-src-1',
        vesselSpeedKnots: 18,
        points: [
          { lat: -4.00, lng: 122.00 },
          { lat: -4.05, lng: 122.05 },
          { lat: -4.10, lng: 122.10 }
        ]
      }
    ]
  };

  const kmlString = generateKmlDocument(sampleData);
  const result = parseKmlString(kmlString);

  // 1. Points
  assert.equal(result.points.length, 2, 'Must parse 2 points');
  const ptLkp = result.points.find(p => p.sourceId === 'pt-lkp' || p.label.includes('LKP'));
  assert.ok(ptLkp, 'Must find LKP point');
  assert.equal(ptLkp.label, 'LKP Titik Duga Kapal');
  assert.equal(ptLkp.pointType, 'lkp');
  assert.equal(ptLkp.alphabetChar, 'A');
  assert.equal(ptLkp.lat, -4.0376);
  assert.equal(ptLkp.lng, 122.4866);

  const ptCustom = result.points.find(p => p.sourceId === 'pt-custom' || p.label.includes('Posko'));
  assert.ok(ptCustom, 'Must find Custom point');
  assert.equal(ptCustom.label, 'Posko Utama Basarnas');
  assert.equal(ptCustom.pointType, 'custom_upload');
  assert.equal(ptCustom.customIconUrl, 'assets/img/basarnas-logo.png');

  // 2. Polygons
  assert.equal(result.polygons.length, 3, 'Must parse 3 polygons');
  const polyAlpha = result.polygons.find(p => p.sourceId === 'poly-src-1' || p.name.includes('Alpha'));
  assert.ok(polyAlpha, 'Must find Polygon Alpha');
  assert.equal(polyAlpha.strokeColor.toLowerCase(), '#0284c7');
  assert.equal(polyAlpha.fillColor.toLowerCase(), '#0ea5e9');
  assert.equal(Math.round(polyAlpha.fillOpacity * 100) / 100, 0.3);
  assert.equal(polyAlpha.points.length, 4, 'Standard polygon must have 4 unclosed vertices');

  const polyCircle = result.polygons.find(p => p.shapeType === 'circle');
  assert.ok(polyCircle, 'Must find Circle polygon');
  assert.equal(polyCircle.shapeType, 'circle');
  assert.equal(polyCircle.radiusNm, 2.5);
  assert.ok(polyCircle.points.length >= 3, 'Parsed circle must have synthesized points.length >= 3');

  const polyRect = result.polygons.find(p => p.shapeType === 'rectangle');
  assert.ok(polyRect, 'Must find Rectangle polygon');
  assert.equal(polyRect.shapeType, 'rectangle');
  assert.equal(polyRect.boxWidthNm, 3);
  assert.equal(polyRect.boxHeightNm, 2);

  // 3. Patterns
  assert.equal(result.patterns.length, 2, 'Must parse 2 patterns');
  const patEsar = result.patterns.find(p => p.patternType === 'sector_search');
  assert.ok(patEsar, 'Must find E-SAR pattern');
  assert.equal(patEsar.waypoints.length, 6, 'E-SAR waypoints must preserve 6 waypoints');
  assert.equal(patEsar.flipH, true, 'flipH must be preserved as boolean true');
  assert.equal(patEsar.polygonRef, 'poly-src-1');

  const patParallel = result.patterns.find(p => p.patternType === 'parallel_track');
  assert.ok(patParallel, 'Must find Parallel Track pattern');
  assert.equal(patParallel.polygonRef, 'datum_kansar');

  // 4. Routes
  assert.equal(result.routes.length, 1, 'Must parse 1 route');
  const rtNav = result.routes[0];
  assert.equal(rtNav.vesselSpeedKnots, 18);
  assert.equal(rtNav.polygonRef, 'poly-src-1');
});

test('Google Earth KML compatibility: StyleMap, nested Folder, gx:Track, MultiGeometry split, duplicate ring vertex strip, HTML description strip, line SAR keyword auto-classification', () => {
  const QrSarGis = loadGisModules();
  const { parseKmlString } = QrSarGis.KmlImporter;

  const googleEarthKml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2" xmlns:gx="http://www.google.com/kml/ext/2.2">
  <Document>
    <name>GE Export Sample</name>
    <Style id="s-red">
      <LineStyle>
        <color>ff00ff00</color>
        <width>3</width>
      </LineStyle>
      <PolyStyle>
        <color>7f0000ff</color>
        <fill>1</fill>
      </PolyStyle>
    </Style>
    <StyleMap id="sm-red">
      <Pair>
        <key>normal</key>
        <styleUrl>#s-red</styleUrl>
      </Pair>
    </StyleMap>
    <Style id="s-nofill">
      <PolyStyle>
        <fill>0</fill>
      </PolyStyle>
    </Style>
    <NetworkLink>
      <name>Live SAR Feed</name>
      <Link>
        <href>https://evil.example/a.kml</href>
      </Link>
    </NetworkLink>
    <Folder>
      <name>Armada SAR</name>
      <Placemark>
        <name>Kapal Patroli 01</name>
        <description><![CDATA[<b>Kapal</b> &amp; Posko<script>alert(1)</script>]]></description>
        <Point>
          <coordinates>122.5,-4.0,0</coordinates>
        </Point>
      </Placemark>
    </Folder>
    <Placemark>
      <name>Sektor Red StyleMap</name>
      <styleUrl>#sm-red</styleUrl>
      <Polygon>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>
              122.0,-4.0,0 122.1,-4.0,0 122.1,-4.1,0 122.0,-4.1,0 122.0,-4.0,0
            </coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>
    <Placemark>
      <name>Sektor NoFill</name>
      <styleUrl>#s-nofill</styleUrl>
      <Polygon>
        <outerBoundaryIs>
          <LinearRing>
            <coordinates>
              122.2,-4.0,0 122.3,-4.0,0 122.3,-4.1,0 122.2,-4.0,0
            </coordinates>
          </LinearRing>
        </outerBoundaryIs>
      </Polygon>
    </Placemark>
    <Folder>
      <name>Pola Pencarian</name>
      <Placemark>
        <name>Lintasan Tim Alfa</name>
        <gx:Track>
          <gx:coord>122.5 -4.0 0</gx:coord>
          <gx:coord>122.6 -4.1 0</gx:coord>
          <gx:coord>122.7 -4.2 0</gx:coord>
        </gx:Track>
      </Placemark>
    </Folder>
    <Placemark>
      <name>Jalur Biasa Kapal</name>
      <LineString>
        <coordinates>122.0,-4.5,0 122.2,-4.6,0</coordinates>
      </LineString>
    </Placemark>
    <Placemark>
      <name>Jalur Multi</name>
      <MultiGeometry>
        <LineString>
          <coordinates>122.1,-4.1,0 122.2,-4.2,0</coordinates>
        </LineString>
        <LineString>
          <coordinates>122.3,-4.3,0 122.4,-4.4,0</coordinates>
        </LineString>
      </MultiGeometry>
    </Placemark>
  </Document>
</kml>`;

  const result = parseKmlString(googleEarthKml);

  // 1. Point HTML stripping & Folder category
  assert.equal(result.points.length, 1);
  const pt = result.points[0];
  assert.equal(pt.label, 'Kapal Patroli 01');
  assert.equal(pt.description, 'Kapal & Posko', 'Script tags removed, HTML stripped, entities decoded');
  assert.equal(pt.category, 'Armada SAR', 'Point in nested folder inherits folder name as category');

  // 2. StyleMap & PolyStyle fill=0 & duplicate ring vertex stripped
  assert.equal(result.polygons.length, 2);
  const polyRed = result.polygons.find(p => p.name === 'Sektor Red StyleMap');
  assert.ok(polyRed, 'Must find Sektor Red');
  assert.equal(polyRed.points.length, 4, 'Duplicate closing ring coordinate must be stripped from 5 to 4 points');
  assert.equal(polyRed.strokeColor.toLowerCase(), '#00ff00');
  assert.equal(polyRed.strokeWeight, 3);
  assert.equal(polyRed.fillColor.toLowerCase(), '#ff0000');
  assert.equal(Math.round(polyRed.fillOpacity * 100) / 100, 0.5);

  const polyNoFill = result.polygons.find(p => p.name === 'Sektor NoFill');
  assert.ok(polyNoFill, 'Must find Sektor NoFill');
  assert.equal(polyNoFill.fillOpacity, 0, 'fill=0 must produce fillOpacity 0');

  // 3. gx:Track auto-classified as pattern due to "Pola Pencarian" folder
  const trackPattern = result.patterns.find(p => p.name === 'Lintasan Tim Alfa');
  assert.ok(trackPattern, 'gx:Track under Pola Pencarian folder must be classified as pattern');
  assert.equal(trackPattern.waypoints.length, 3);

  // 4. LineString without SAR keywords auto-classified as route
  const lineRoute = result.routes.find(r => r.name === 'Jalur Biasa Kapal');
  assert.ok(lineRoute, 'LineString without SAR keywords must be classified as route');
  assert.equal(lineRoute.points.length, 2);

  // 5. MultiGeometry without qrsar:waypointsJson split into separate features
  const multi1 = result.routes.find(r => r.name === 'Jalur Multi (1)');
  const multi2 = result.routes.find(r => r.name === 'Jalur Multi (2)');
  assert.ok(multi1, 'MultiGeometry must split into Nama (1)');
  assert.ok(multi2, 'MultiGeometry must split into Nama (2)');

  // 6. NetworkLink warning recorded and external link skipped
  assert.ok(result.warnings.some(w => w.toLowerCase().includes('networklink')), 'NetworkLink must add security warning');
});

test('Google Earth geometry edit on exported circle/rectangle overrides stale ExtendedData and downgrades shapeType to polygon', () => {
  const QrSarGis = loadGisModules();
  const { generateKmlDocument } = QrSarGis.Export;
  const { parseKmlString } = QrSarGis.KmlImporter;

  const circleData = {
    polygons: [
      {
        id: 'poly-circle-edit',
        sourceId: 'poly-circle-edit',
        name: 'Lingkaran Diedit GE',
        shapeType: 'circle',
        centerLat: -4.0,
        centerLng: 122.5,
        radiusNm: 2.0,
        areaNm2: 12.57,
        points: []
      }
    ]
  };

  const originalKml = generateKmlDocument(circleData);

  // Shift coordinates by +0.5 degrees
  const coordsMatch = originalKml.match(/<coordinates>([\s\S]*?)<\/coordinates>/);
  assert.ok(coordsMatch, 'Must find coordinates block in circle KML');

  const shiftedCoords = coordsMatch[1].trim().split(/\s+/).map(tuple => {
    const [lng, lat, alt] = tuple.split(',');
    return `${(parseFloat(lng) + 0.5).toFixed(6)},${(parseFloat(lat) + 0.5).toFixed(6)},${alt || 0}`;
  }).join(' ');

  const modifiedKml = originalKml.replace(coordsMatch[0], `<coordinates>${shiftedCoords}</coordinates>`);

  const res = parseKmlString(modifiedKml);
  assert.equal(res.polygons.length, 1);
  const poly = res.polygons[0];

  assert.equal(poly.shapeType, 'polygon', 'Externally edited circle must downgrade shapeType to polygon');
  assert.ok(Math.abs(poly.centerLat - (-3.5)) < 0.05, 'centerLat must update to shifted centroid (~ -3.5)');
  assert.ok(Math.abs(poly.centerLng - 123.0) < 0.05, 'centerLng must update to shifted centroid (~ 123.0)');
  assert.ok(typeof poly.areaNm2 === 'number' && poly.areaNm2 > 0, 'areaNm2 must be recomputed');
  assert.ok(res.warnings.some(w => w.toLowerCase().includes('downgrade') || w.toLowerCase().includes('modified')), 'Warning must indicate shapeType downgrade');
});

test('Guardrails: invalid coords skipped with warnings, malicious customIconPath stripped, > maxFeatures truncated, malformed XML throws', () => {
  const QrSarGis = loadGisModules();
  const { parseKmlString } = QrSarGis.KmlImporter;

  // 1. Malicious customIconPath stripped
  const maliciousKml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Evil Icon Point 1</name>
      <ExtendedData>
        <Data name="qrsar:customIconPath"><value>http://evil.example/malware.png</value></Data>
        <Data name="qrsar:pointType"><value>custom_upload</value></Data>
      </ExtendedData>
      <Point><coordinates>122.5,-4.0,0</coordinates></Point>
    </Placemark>
    <Placemark>
      <name>Evil Icon Point 2</name>
      <ExtendedData>
        <Data name="qrsar:customIconPath"><value>../../etc/passwd</value></Data>
        <Data name="qrsar:pointType"><value>custom_upload</value></Data>
      </ExtendedData>
      <Point><coordinates>122.6,-4.1,0</coordinates></Point>
    </Placemark>
    <Placemark>
      <name>Invalid Coords Point</name>
      <Point><coordinates>999.9,999.9,0</coordinates></Point>
    </Placemark>
  </Document>
</kml>`;

  const evilRes = parseKmlString(maliciousKml);
  assert.equal(evilRes.points.length, 2, 'Must parse the 2 valid coord points and skip invalid coord point');
  assert.equal(evilRes.points[0].customIconUrl, null, 'External URL must be stripped');
  assert.notEqual(evilRes.points[0].pointType, 'custom_upload', 'Point with stripped icon falls back to preset or pin_umum');
  assert.equal(evilRes.points[1].customIconUrl, null, 'Relative directory traversal path must be stripped');
  assert.ok(evilRes.warnings.some(w => w.toLowerCase().includes('coordinate') || w.toLowerCase().includes('invalid')), 'Warning recorded for invalid coordinates');

  // 2. Truncation at maxFeatures
  const fivePlacemarksKml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark><name>P1</name><Point><coordinates>122.1,-4.1,0</coordinates></Point></Placemark>
    <Placemark><name>P2</name><Point><coordinates>122.2,-4.2,0</coordinates></Point></Placemark>
    <Placemark><name>P3</name><Point><coordinates>122.3,-4.3,0</coordinates></Point></Placemark>
    <Placemark><name>P4</name><Point><coordinates>122.4,-4.4,0</coordinates></Point></Placemark>
    <Placemark><name>P5</name><Point><coordinates>122.5,-4.5,0</coordinates></Point></Placemark>
  </Document>
</kml>`;

  const truncRes = parseKmlString(fivePlacemarksKml, { maxFeatures: 3 });
  assert.equal(truncRes.points.length, 3, 'Must truncate at 3 features');
  assert.equal(truncRes.stats.totalPlacemarks, 5);
  assert.equal(truncRes.stats.parsedCount, 3);
  assert.equal(truncRes.stats.truncated, true);
  assert.ok(truncRes.warnings.some(w => w.toLowerCase().includes('limit') || w.toLowerCase().includes('truncat')), 'Warning recorded for truncation');

  // 3. Malformed XML throws
  assert.throws(() => {
    parseKmlString('This is clearly not XML <unclosed>');
  }, /xml/i);
});

test('parseFile extracts doc.kml from compressed KMZ buffer using mock/real JSZip shim and rejects > 10MB', async () => {
  const QrSarGis = loadGisModules();
  const { parseFile } = QrSarGis.KmlImporter;

  const validKml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Point In File</name>
      <Point><coordinates>122.5,-4.0,0</coordinates></Point>
    </Placemark>
  </Document>
</kml>`;

  // 1. KML file object
  const kmlFileMock = {
    name: 'operasi.kml',
    size: 200,
    text: async () => validKml
  };
  const kmlRes = await parseFile(kmlFileMock);
  assert.equal(kmlRes.points.length, 1);
  assert.equal(kmlRes.points[0].label, 'Point In File');

  // 2. KMZ file object with JSZip mock
  const kmzFileMock = {
    name: 'operasi.kmz',
    size: 500,
    arrayBuffer: async () => new ArrayBuffer(500)
  };

  const origZip = global.JSZip;
  global.JSZip = {
    async loadAsync() {
      return {
        file(name) {
          if (name === 'doc.kml') {
            return { async: async () => validKml };
          }
          return null;
        },
        files: {
          'doc.kml': {
            name: 'doc.kml',
            dir: false,
            async: async () => validKml
          }
        }
      };
    }
  };

  try {
    const kmzRes = await parseFile(kmzFileMock);
    assert.equal(kmzRes.points.length, 1);
    assert.equal(kmzRes.points[0].label, 'Point In File');
  } finally {
    global.JSZip = origZip;
  }

  // 3. Reject file > 10MB
  const oversizedFile = {
    name: 'huge.kml',
    size: 11 * 1024 * 1024,
    text: async () => validKml
  };

  await assert.rejects(async () => {
    await parseFile(oversizedFile);
  }, /10MB/i);
});

test('kml_importer.js loads cleanly and functions when siblings are absent', () => {
  const QrSarGis = loadGisModules({
    includeGeodesy: false,
    includeMarkers: false,
    includePatterns: false,
    includeExport: false
  });
  const { kmlAbgrToCss, stripHtmlDescription, isSafeLocalIconPath, parseKmlString } = QrSarGis.KmlImporter;

  assert.equal(typeof kmlAbgrToCss, 'function');
  assert.equal(typeof stripHtmlDescription, 'function');
  assert.equal(typeof isSafeLocalIconPath, 'function');
  assert.equal(typeof parseKmlString, 'function');

  const css = kmlAbgrToCss('ff0000ff');
  assert.equal(css.hex, '#ff0000');
  assert.equal(css.opacity, 1);

  const clean = stripHtmlDescription('<b>Test</b> <script>bad()</script>');
  assert.equal(clean, 'Test');

  assert.equal(isSafeLocalIconPath('assets/img/basarnas-logo.png'), 'assets/img/basarnas-logo.png');
  assert.equal(isSafeLocalIconPath('https://evil.com/icon.png'), null);

  const kml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Standalone Point</name>
      <Point><coordinates>122.5,-4.0,0</coordinates></Point>
    </Placemark>
  </Document>
</kml>`;
  const res = parseKmlString(kml);
  assert.equal(res.points.length, 1);
  assert.equal(res.points[0].label, 'Standalone Point');
});

test('generateKmlDocument normalizes ETA minute overflow (e.g. 11.99 NM at 12 knots -> 1 jam 0 menit, not 60 menit)', () => {
  const QrSarGis = loadGisModules();
  const { generateKmlDocument } = QrSarGis.Export;

  const kml = generateKmlDocument({
    operasiName: 'ETA Overflow Test',
    routes: [
      {
        id: 'rt-overflow',
        name: 'Rute Overflow ETA',
        distanceNm: 11.99,
        vesselSpeedKnots: 12,
        points: [
          { lat: -4.0, lng: 122.0 },
          { lat: -4.1, lng: 122.1 }
        ]
      }
    ]
  });

  assert.ok(kml.includes('ETA: 1 jam 0 menit'), 'Must normalize 60 menit overflow to 1 jam 0 menit');
  assert.ok(!kml.includes('60 menit'), 'Must not emit 60 menit');
});

test('MultiGeometry skips invalid child Point and Polygon with warnings and increments skippedCount', () => {
  const QrSarGis = loadGisModules();
  const { parseKmlString } = QrSarGis.KmlImporter;

  const kml = `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
  <Document>
    <Placemark>
      <name>Mixed MultiGeom</name>
      <MultiGeometry>
        <Point><coordinates></coordinates></Point>
        <Polygon>
          <outerBoundaryIs>
            <LinearRing><coordinates>122.0,-4.0 122.1,-4.1</coordinates></LinearRing>
          </outerBoundaryIs>
        </Polygon>
        <Point><coordinates>122.5,-4.0,0</coordinates></Point>
      </MultiGeometry>
    </Placemark>
  </Document>
</kml>`;

  const res = parseKmlString(kml);
  assert.equal(res.points.length, 1);
  assert.equal(res.points[0].label, 'Mixed MultiGeom (3)');
  assert.equal(res.stats.skippedCount, 2);
  assert.ok(res.warnings.some(w => w.includes('MultiGeometry Point child')), 'Records warning for invalid Point child');
  assert.ok(res.warnings.some(w => w.includes('MultiGeometry Polygon child')), 'Records warning for invalid Polygon child');
});

test('real JSZip compression and parseFile KMZ round-trip', async () => {
  const sandbox = loadGisModules({ includeJSZip: true, returnSandbox: true });
  const QrSarGis = sandbox.window.QrSarGis;
  const JSZip = sandbox.window.JSZip;

  assert.equal(typeof JSZip, 'function', 'Real JSZip constructor must load cleanly');

  const exportData = {
    operasiName: 'Ops SAR Kendari KMZ Real Test',
    points: [
      { id: 'pt-kmz-1', label: 'Posko KMZ Kendari', lat: -4.0123, lng: 122.5678, pointType: 'posko_sar', category: 'posko' }
    ],
    polygons: [
      {
        id: 'poly-kmz-1',
        name: 'Sektor KMZ Utama',
        points: [{ lat: -4.0, lng: 122.5 }, { lat: -4.0, lng: 122.6 }, { lat: -4.1, lng: 122.6 }, { lat: -4.1, lng: 122.5 }],
        shapeType: 'polygon',
        strokeColor: '#0284c7'
      }
    ],
    routes: [
      {
        id: 'rt-kmz-1',
        name: 'Rute KMZ KN SAR',
        points: [{ lat: -4.0, lng: 122.5 }, { lat: -4.05, lng: 122.55 }],
        vesselSpeedKnots: 15
      }
    ]
  };

  const kml = QrSarGis.Export.generateKmlDocument(exportData);
  assert.ok(kml.includes('Posko KMZ Kendari'));

  const zip = new JSZip();
  zip.file('doc.kml', kml);
  const kmzBuffer = await zip.generateAsync({ type: 'arraybuffer' });
  assert.ok(kmzBuffer.byteLength > 100, 'KMZ ArrayBuffer must be generated');

  const kmzFileMock = {
    name: 'test_ops.kmz',
    size: kmzBuffer.byteLength,
    arrayBuffer: async () => kmzBuffer
  };

  const parsed = await QrSarGis.KmlImporter.parseFile(kmzFileMock);
  assert.equal(parsed.points.length, 1);
  assert.equal(parsed.points[0].label, 'Posko KMZ Kendari');
  assert.equal(parsed.polygons.length, 1);
  assert.equal(parsed.polygons[0].name, 'Sektor KMZ Utama');
  assert.equal(parsed.routes.length, 1);
  assert.equal(parsed.routes[0].name, 'Rute KMZ KN SAR');
  assert.equal(parsed.stats.skippedCount, 0);
  assert.equal(parsed.warnings.length, 0);
});


