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
    console,
    Math,
    JSON,
    parseFloat,
    parseInt,
    Number,
    String,
    Boolean,
    Array,
    Date
  };
  vm.createContext(sandbox);

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
  vm.runInContext(fs.readFileSync(path.join(baseDir, 'export.js'), 'utf8'), sandbox);

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

