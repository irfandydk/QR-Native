# Two-Way KML/KMZ Export & Import (Google Earth ↔ QR-SAR) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement reliable, lightweight, lossless two-way KML/KMZ spatial data exchange between Google Earth and QR-SAR (supporting Points, Polygons/Circles/Rectangles, Search Patterns including E-SAR, and Routes) with explicit Active Operation (`OPEN`) vs Base Map scope selection.

**Architecture:** Client-side KML/KMZ export (`assets/js/gis/export.js`) and XML/KMZ parsing (`assets/js/gis/kml_importer.js` with `JSZip`, `DOMParser` in browser, and pure-JS XML fallback for Node.js CLI tests) feed an interactive 3-step import preview/curation modal in `views/gis.php` + `assets/js/gis_master.js`, persisting validated selections in a single atomic DB transaction via `POST /api/gis/import` (`GisApiController::handleImportRoute` → `GisModel::importBatch`). Export adds a scope-selection modal (`Current View`, `Active Operation + optional Base Map`, `Base Map Only`) with `hidden*Ids` visibility toggle and unified delegation through `window.QrSarGis.Export`.

**Tech Stack:** Vanilla JS (IIFE browser modules dual-compatible with Node v24 `node --test`), `JSZip` (`assets/vendor/jszip/jszip.min.js`), PHP 8.5 (`PDO` MySQL transactions), TailwindCSS cockpit-glass modals, Leaflet 1.x.

**Spec:** [docs/superpowers/specs/2026-11-14-gis-kml-kmz-import-export-design.md](file:///Users/irfandydk/antigravity/QR-SAR/docs/superpowers/specs/2026-11-14-gis-kml-kmz-import-export-design.md)

## Global Constraints

- Coordinate bounds: `Number.isFinite(lat)` & `lat ∈ [-90, 90]`, `Number.isFinite(lng)` & `lng ∈ [-180, 180]`; polygons require `>= 3` vertices, routes and patterns require `>= 2` vertices.
- Upload & batch limits: compressed/raw file max `10 MB` (`10485760` bytes), decompressed `doc.kml` max `15 MB` (`15728640` bytes), max `500` features per import batch (`points + polygons + patterns + routes`).
- Security & anti-bloat: external `<NetworkLink>` fetches are never performed (recorded as warning); `<description>` HTML/CDATA is stripped to safe plain text; NO external URL or KMZ binary `DataURL` is saved into `GisPoint.customIconUrl`; NO new DB column added to `GisPoint`.
- Local custom icon allowlist: `qrsar:customIconPath` is emitted and accepted only for normalized relative paths matching `^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$` without `..` segments, and the PHP backend only keeps `'custom_upload'` when `is_file(ROOT_DIR . '/' . $relPath)` is true (otherwise falling back to `'kantor'`, `'pos_sar'`, or `'pin_umum'`).
- RBAC & lock rules: `id_operasi === null` (Base Map) requires `AuthHelper::isAdmin()` (HTTP 403 otherwise); `id_operasi > 0` requires existing operation not in `'CLOSED'` status (HTTP 404/403).
- Backward compatibility: `window.QrSarGis.Export.generateKmlDocument` and `generateGeoJson` must run cleanly in `eval()` Node.js sandbox inside [tests/test_phase5_gis_modular.php](file:///Users/irfandydk/antigravity/QR-SAR/tests/test_phase5_gis_modular.php), and [assets/js/gis_master.js](file:///Users/irfandydk/antigravity/QR-SAR/assets/js/gis_master.js) must preserve its `window.QrSarGis.Export.generateKmlDocument` call and `window.SAR_GIS = {` facade; `export.js` must load cleanly in [views/layout/footer.php](file:///Users/irfandydk/antigravity/QR-SAR/views/layout/footer.php) even without optional sibling modules.
- CLI DB tests require `DB_DATABASE=qrsar_test` per [tests/bootstrap.php](file:///Users/irfandydk/antigravity/QR-SAR/tests/bootstrap.php) and must clean up inserted records in `try/finally`.

## Review Focus

- **XML special chars, malformed coordinates & external `<NetworkLink>` / oversized batches:** Exporting names/descriptions containing `& < > " '`, NaN coordinates, or importing `<NetworkLink>` / HTML `<script>` in descriptions / `> 500` Placemarks must sanitize, warn, skip bad geometry, and enforce hard limits without crashing. *(Covered in Tasks 1 & 2 tests)*
- **Circle empty-points export, Google Earth shape edits overriding `<ExtendedData>`, and disjoint `E-SAR` segments:** Exporting a `circle` without `points` must generate 64 geodesic ring vertices; importing a `circle` must persist `>= 3` vertices to `GisPolygonPoint`; reshaping an exported `circle`/`rectangle` in Google Earth must drop stale dimensions and downgrade `shapeType` to `'polygon'`; `E-SAR` (`sector_search`/`e_sar`) must export as `<MultiGeometry>` and round-trip without false leg jumps. *(Covered in Tasks 1, 2 & 3 tests)*
- **3-stage `polygonRef` resolution & `custom_upload` path allowlisting:** Importing Operation A's KML into Operation B without its polygon must clear `polygonId` (never link cross-op), whereas unchecking an already-existing polygon in the same scope preserves the scoped DB link and `'datum_kansar'` stays intact; forged `customIconPath` (`../`, `http://`, `data:`, missing file) must strip cleanly to a preset icon. *(Covered in Tasks 1, 2 & 3 tests)*
- **RBAC bypass & partial DB writes on mid-batch failure:** Non-admin targeting Base Map (`403`), `CLOSED` op (`403`), missing op (`404`), empty/oversized payload (`400`), and mid-batch transaction failure (valid Point + valid Polygon + invalid Route in one `importBatch` call) must roll back 100% atomically without premature commits in `savePolygon`/`saveRoute`/`saveSearchPattern`. *(Covered in Tasks 3 & 4 tests)*
- **Dual-runtime JS parity (`DOMParser` vs Node pure-JS parser) & UI target-scope synchronization:** Executing `export.js` and `kml_importer.js` under Node.js without `DOMParser` (`tests/js/kml_roundtrip.test.js`) AND keeping `test_phase5_gis_modular.php` passing while wiring Scope Export + Preview Import modals cleanly in `views/gis.php` and `assets/js/gis_master.js`. *(Covered in Tasks 1, 2 & 5 tests)*

---

### Task 1: Enhanced KML / KMZ / GeoJSON Exporter (`assets/js/gis/export.js`)

**Files:**
- Modify: `assets/js/gis/export.js:1-225`
- Test: `tests/js/kml_roundtrip.test.js` (Create)
- Verify compatibility: `tests/test_phase5_gis_modular.php`

**Interfaces:**
- Consumes (optional globals, safe fallback when absent):
  - `window.QrSarGis.Markers.PRESET_ICONS` (for point color lookup by `pointType`)
  - `window.QrSarGis.Geodesy.calculateDistanceHaversine(lat1, lng1, lat2, lng2)` (for route distance fallback)
  - `window.QrSarGis.Patterns.getPatternPolylineCoords(type, waypoints)` (or per-pattern `segments` property passed on pattern object)
  - `JSZip` global (for `downloadKmz`)
- Produces on `window.QrSarGis.Export`:
  - `escapeXml(str: unknown): string`
  - `cssColorToKmlAbgr(color: string, opacity?: number): string` (returns 8-char lowercase `aabbggrr` hex, defaults opacity `1`)
  - `generateCircleRing(centerLat: number, centerLng: number, radiusNm: number, numPoints?: number): Array<{lat: number, lng: number}>` (default `64` points)
  - `extractSafeLocalIconPath(url: unknown): string|null` (normalizes to `assets/img/...` or `uploads/icons/...`, rejects `..`, query/hash, `data:`, external origins)
  - `generateKmlDocument(data: { operasiName?: string, points?: Array, polygons?: Array, patterns?: Array, routes?: Array }): string`
  - `generateGeoJson(data: { points?: Array, polygons?: Array, patterns?: Array, routes?: Array }): { type: 'FeatureCollection', features: Array }`
  - `downloadKml(data, filename?: string): void`
  - `downloadKmz(data, filename?: string): Promise<void>` (uses `compression: 'DEFLATE'`, MIME `'application/vnd.google-earth.kmz'`)
  - `downloadGeoJson(data, filename?: string): void`

- [ ] **Step 1: Write the failing exporter test suite in `tests/js/kml_roundtrip.test.js`**

Write tests using Node built-in `node:test` and `node:assert/strict` that load `assets/js/gis/geodesy.js`, `assets/js/gis/markers.js`, `assets/js/gis/patterns.js`, and `assets/js/gis/export.js` via `vm.runInNewContext` with a clean `window` shim (no `DOMParser`), covering:
1. `test('cssColorToKmlAbgr converts #rrggbb, #rgb, and opacity to aabbggrr')`:
   - `cssColorToKmlAbgr('#dc2626', 1)` === `'ff2626dc'`
   - `cssColorToKmlAbgr('#0ea5e9', 0.25)` === `'40e9a50e'`
   - `cssColorToKmlAbgr('#f00', 0.5)` === `'800000ff'`
   - Invalid color falls back to `'ffea580c'`
2. `test('extractSafeLocalIconPath accepts only local assets/img and uploads/icons paths')`:
   - `'assets/img/basarnas-logo.png'` → `'assets/img/basarnas-logo.png'`
   - `'https://qrsar.local/QR-SAR/uploads/icons/icon_123.png'` → `'uploads/icons/icon_123.png'`
   - `'data:image/png;base64,AAA'` → `null`
   - `'https://evil.com/uploads/icons/../secret.png'` → `null`
3. `test('generateKmlDocument outputs Styles, tessellate, ExtendedData, 64-pt circle fallback, E-SAR MultiGeometry, and escaped XML')`:
   - Pass points (preset `lkp` with `<>&"'` in label/description, `custom_upload` with `http://host/uploads/icons/team.png`, and invalid `{ lat: NaN, lng: 122 }` skipped), polygons (standard closed/unclosed polygon + `shapeType: 'circle'` with `points: []` + `centerLat: -4.0, centerLng: 122.5, radiusNm: 2.0`), patterns (standard `parallel_track` + `sector_search` E-SAR with 6 waypoints), and routes (`polygonId: 'poly-1'`, `vesselSpeedKnots: 20`).
   - Assert KML contains `xmlns="http://www.opengis.net/kml/2.2"`, `<Style id="style-`, `aabbggrr` colors (`ff2626dc` for `#dc2626` point icon style), `<tessellate>1</tessellate>`, `<Folder><name>Titik Operasi SAR</name>`, `Area Sektor Poligon`, `Pola Pencarian SAR`, `Rute dan Jalur Navigasi`, escaped `&amp; &lt; &gt; &quot; &apos;`, skips `NaN` point, circle Placemark has 65 coordinate tuples (64 ring points + closing point), E-SAR Placemark contains `<MultiGeometry>` and `qrsar:waypointsJson`, route/pattern contain `qrsar:polygonRef`, local icon emits `qrsar:customIconPath` with `'uploads/icons/team.png'`.
4. `test('generateGeoJson synthesizes 64-point ring when circle polygon has empty points')`:
   - Circle with `points: []` still produces 1 Polygon Feature whose outer ring has 65 coordinates (`ring[0] === ring[64]`).

- [ ] **Step 2: Run Node test to verify it fails**

Run: `node --test tests/js/kml_roundtrip.test.js`
Expected: FAIL (`cssColorToKmlAbgr` / `extractSafeLocalIconPath` undefined, `<Style>`/`MultiGeometry`/`ExtendedData`/64-pt circle missing in KML output).

- [ ] **Step 3: Implement enhanced `assets/js/gis/export.js`**

Update `assets/js/gis/export.js`:
- Keep pure-JS safe global lookup (`window.QrSarGis?.Markers?.PRESET_ICONS`, `window.QrSarGis?.Geodesy`, `window.QrSarGis?.Patterns`) so loading without siblings (e.g. `footer.php` or minimal Node test) never throws.
- Implement `cssColorToKmlAbgr(color, opacity)`: support `#rgb`, `#rrggbb`, clamped opacity `[0..1]`, default `#ea580c`.
- Implement `generateCircleRing(centerLat, centerLng, radiusNm, numPoints = 64)`: validate finite inputs (`radiusNm > 0`), project using `110.574` km/deg lat and `111.32 * Math.cos(centerLat * Math.PI / 180)` km/deg lng (guarded against polar cos=0).
- Implement `extractSafeLocalIconPath(url)`: extract `(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+` while rejecting `..`, `?`, `#`, whitespace, and `data:`.
- Deduplicate `<Style id="style-...">` definitions in `<Document>`:
  - Point style key: `pt:<colorAbgr>`: `<IconStyle><color>${abgr}</color><scale>1.1</scale><Icon><href>https://maps.google.com/mapfiles/kml/paddle/wht-blank.png</href></Icon></IconStyle>`
  - Polygon style key: `poly:<strokeAbgr>:<weight>:<fillAbgr>:<hasFill>`: `<LineStyle><color>...</color><width>...</width></LineStyle><PolyStyle><color>...</color><fill>1|0</fill><outline>1</outline></PolyStyle>`
  - Line style key: `line:<strokeAbgr>:<weight>`: `<LineStyle><color>...</color><width>...</width></LineStyle>`
- Build `<ExtendedData>` helper rendering `<Data name="qrsar:..."><value>${escapeXml(...)}</value></Data>` cleanly for all core attributes:
  - Point: `sourceId`, `sarType="point"`, `category`, `pointType`, `alphabetChar`, `customIconPath` (only if `pointType === 'custom_upload'` and `extractSafeLocalIconPath(p.customIconUrl)` is non-null).
  - Polygon: `sourceId`, `sarType="polygon"`, `shapeType`, `centerLat`, `centerLng`, `radiusNm`, `boxWidthNm`, `boxHeightNm`, `areaNm2`, `strokeColor`, `strokeWeight`, `strokeOpacity`, `fillColor`, `fillOpacity`. Ensure `<LinearRing>` closes (`first !== last`) and `<tessellate>1</tessellate><altitudeMode>clampToGround</altitudeMode>`. If `shapeType === 'circle'` and `< 3` points, call `generateCircleRing(...)`.
  - Pattern: `sourceId`, `sarType="pattern"`, `patternType`, `trackSpacingNm`, `legsCount`, `totalDistanceNm`, `flipH`, `flipV`, `polygonRef` (`polygonId`), `polygonName`, `poskoId`, `poskoName`, `color`, `opacity`, `strokeWeight`, and for `'sector_search'`/`'e_sar'` also compact `waypointsJson` (`JSON.stringify` `[lat,lng,label]` or `{lat,lng,label}`). Emit `<MultiGeometry>` of per-leg `<LineString>`s using `pat.segments` or `window.QrSarGis.Patterns.getPatternPolylineCoords` (fallback pairwise legs + baseline if `Patterns` absent) for E-SAR/sector search; single `<LineString>` otherwise.
  - Route: `sourceId`, `sarType="route"`, `vesselSpeedKnots`, `polygonRef` (`polygonId`), `polygonName`, `strokeColor`, `strokeOpacity`, `strokeWeight`; `<description>` formatted with computed distance NM/KM and ETA.
- Update `generateGeoJson(data)` so `shapeType === 'circle'` with `< 3` points uses `generateCircleRing(...)`.
- Update `downloadKmz` to pass `{ type: 'blob', compression: 'DEFLATE', mimeType: 'application/vnd.google-earth.kmz' }` and update `triggerDownload` to defer `URL.revokeObjectURL(url)` with `setTimeout(() => URL.revokeObjectURL(url), 1500)`.

- [ ] **Step 4: Run tests to verify pass & backward compatibility**

Run: `node --test tests/js/kml_roundtrip.test.js && DB_DATABASE=qrsar_test php tests/test_phase5_gis_modular.php && node -c assets/js/gis/export.js`
Expected: All `kml_roundtrip.test.js` assertions PASS, `test_phase5_gis_modular.php` 100% PASS, `node -c` exit code 0.

- [ ] **Step 5: Commit**

```bash
git add assets/js/gis/export.js tests/js/kml_roundtrip.test.js
git commit -m "feat(gis): enhance KML/KMZ/GeoJSON exporter with styles, circle ring fallback, E-SAR MultiGeometry, and QR-SAR ExtendedData"
```

---

### Task 2: Modular Browser & Node.js KML/KMZ Parser (`assets/js/gis/kml_importer.js`)

**Files:**
- Create: `assets/js/gis/kml_importer.js`
- Modify: `tests/js/kml_roundtrip.test.js` (Add round-trip & Google Earth Pro compatibility tests)

**Interfaces:**
- Consumes:
  - `window.QrSarGis.Export` (only in tests for round-trip checks; optional in runtime)
  - `window.QrSarGis.Geodesy` (area / haversine helpers; falls back cleanly to spherical planar approximation if absent)
  - `window.QrSarGis.Markers.PRESET_ICONS` (validates known preset `pointType` values; keeps hardcoded fallback set of valid preset IDs)
  - `JSZip` global (when `parseFile` receives a `.kmz` File/`ArrayBuffer`)
- Produces on `window.QrSarGis.KmlImporter`:
  - `MAX_FILE_BYTES` (`10485760`), `MAX_KML_BYTES` (`15728640`), `MAX_FEATURES` (`500`)
  - `kmlAbgrToCss(abgr: string): { hex: string, opacity: number }|null`
  - `stripHtmlDescription(raw: unknown): string`
  - `isSafeLocalIconPath(path: unknown): string|null`
  - `parseXml(xmlString: string): XmlNodeAdapter` (browser uses `DOMParser` & checks `<parsererror>`; Node/no-DOM uses internal pure-JS XML tokenizer/tree builder supporting attributes, self-closing tags, comments `<!-- -->`, processing instructions, `xmlns`/prefixed tags like `gx:Track`, and `<![CDATA[...]]>`)
  - `parseKmlString(xmlString: string, options?: { maxFeatures?: number }): { points: Array, polygons: Array, patterns: Array, routes: Array, warnings: Array<string>, stats: { totalPlacemarks: number, parsedCount: number, skippedCount: number, truncated: boolean } }`
  - `parseFile(file: File | Blob | { name: string, arrayBuffer?: Function, text?: Function }, options?: Object): Promise<ParseResult>` (checks file size <= `MAX_FILE_BYTES`, detects `.kmz` vs `.kml`, extracts `doc.kml` or first `.kml` entry from ZIP via `JSZip`, checks decompressed size <= `MAX_KML_BYTES`, runs `parseKmlString`)

- [ ] **Step 1: Add failing parser & round-trip tests to `tests/js/kml_roundtrip.test.js`**

Add tests in `tests/js/kml_roundtrip.test.js` loading `assets/js/gis/kml_importer.js` after `export.js`:
1. `test('lossless round-trip QR-SAR -> Export.generateKmlDocument -> KmlImporter.parseKmlString')`:
   - Export and re-import:
     - Preset `lkp` Point + `custom_upload` local Basarnas Point (`assets/img/basarnas-logo.png`),
     - Standard 4-vertex Polygon (`sourceId: 'poly-src-1'`),
     - Empty-points `circle` (`centerLat: -4.02, centerLng: 122.52, radiusNm: 2.5`),
     - `rectangle` (`boxWidthNm: 3, boxHeightNm: 2`),
     - `sector_search` E-SAR Pattern (6 waypoints, `polygonId: 'poly-src-1'`, `flipH: true`),
     - `parallel_track` Pattern (`polygonId: 'datum_kansar'`),
     - Route (`vesselSpeedKnots: 18`, `polygonId: 'poly-src-1'`).
   - Assert exact preservation of `label`, `pointType`, `customIconUrl === 'assets/img/basarnas-logo.png'`, `alphabetChar`, `strokeColor`/`fillColor`/`fillOpacity`, `circle` `shapeType === 'circle'` with `radiusNm === 2.5` AND `points.length >= 3`, E-SAR `waypoints.length === 6` & `flipH === true`, `polygonRef === 'poly-src-1'` / `'datum_kansar'`, `vesselSpeedKnots === 18`.
2. `test('Google Earth KML compatibility: StyleMap, nested Folder, gx:Track, MultiGeometry split, duplicate ring vertex strip, HTML description strip, line SAR keyword auto-classification')`:
   - Parse hand-crafted Google Earth KML containing:
     - `<StyleMap id="sm-red">` resolving `normal` pair to `<Style id="s-red">` (`<PolyStyle><color>7f0000ff</color><fill>1</fill></PolyStyle>`, `<LineStyle><color>ff00ff00</color><width>3</width></LineStyle>`), plus another `<Style>` with `<PolyStyle><fill>0</fill></PolyStyle>` (`fillOpacity === 0`),
     - Nested `<Folder><name>Armada SAR</name>` with a `<Point>` having `<![CDATA[<b>Kapal</b> &amp; Posko<script>alert(1)</script>]]>` → plain safe description `'Kapal & Posko'`, `category: 'Armada SAR'`,
     - Polygon with duplicate closing coordinate `[lng0, lat0]` → duplicate final vertex stripped (`points.length === 4`, not 5),
     - `<gx:Track>` with `<gx:coord>122.5 -4.0 0</gx:coord>` tags in folder `"Pola Pencarian"` → classified as `pattern`,
     - `<LineString>` without SAR keywords → classified as `route`,
     - Placemark containing `<MultiGeometry>` with 2 separate `<LineString>`s → split into `'Nama (1)'` and `'Nama (2)'`,
     - `<NetworkLink><name>Live Feed</name><Link><href>https://evil.example/a.kml</href></Link></NetworkLink>` → non-empty `warnings` explaining NetworkLink is skipped.
3. `test('Google Earth geometry edit on exported circle/rectangle overrides stale ExtendedData and downgrades shapeType to polygon')`:
   - Take an exported `circle` KML (`radiusNm: 2`), shift every coordinate in `<LinearRing>` by `+0.5` degrees (outside tolerance), parse it, assert parsed polygon keeps shifted coordinates (centroid near shifted lat/lng), updates `areaNm2`, downgrades `shapeType` to `'polygon'`, and adds an informational warning.
4. `test('Guardrails: invalid coords skipped with warnings, malicious customIconPath stripped, > maxFeatures truncated, malformed XML throws')`:
   - Forged `qrsar:customIconPath` (`http://evil/a.png`, `../../etc/passwd`) stripped cleanly to preset fallback without `customIconUrl`.
   - `parseKmlString(kml, { maxFeatures: 3 })` with 5 placemarks returns max 3 items, `stats.truncated === true`, and warning.
   - Non-XML / malformed XML throws Error.
5. `test('parseFile extracts doc.kml from compressed KMZ buffer using mock/real JSZip shim and rejects > 10MB')`:
   - Verify `.kml` Blob/object and mock KMZ object extraction + size guard `> 10MB` rejection.

- [ ] **Step 2: Run Node test to verify it fails**

Run: `node --test tests/js/kml_roundtrip.test.js`
Expected: FAIL (`ENOENT` / module `assets/js/gis/kml_importer.js` does not exist).

- [ ] **Step 3: Implement `assets/js/gis/kml_importer.js`**

Implement UMD/IIFE attaching `window.QrSarGis.KmlImporter`:
- Dual XML parser `parseXml`: use native `DOMParser` when `typeof DOMParser !== 'undefined'` (checking `getElementsByTagName('parsererror')`); otherwise run compact stack-based pure-JS XML parser supporting CDATA, XML entity decode (`&amp; &lt; &gt; &quot; &apos;` and numeric char refs), tag local-name helper (stripping namespace prefix cleanly so `gx:Track` matches `'Track'`), child element query helpers (`childrenNamed`, `firstChildNamed`, `textOf`).
- Style indexer: index all `<Style id="...">` and resolve `<StyleMap id="...">` (`Pair/key === 'normal'`). Support inline `<Style>` on `<Placemark>` overriding referenced `styleUrl`. Convert `aabbggrr` → `{ hex: '#rrggbb', opacity }`. Respect `<PolyStyle><fill>0</fill>` (`fillOpacity = 0`) and `<PolyStyle><outline>0</outline>`.
- Coordinate parser: split whitespace, extract `lng,lat[,alt]`, reject NaN or outside `[-90, 90]` / `[-180, 180]`.
- Polygon extractor + GE edit detector: strip duplicate closing vertex (`|a-b| < 1e-9`), calculate centroid & `areaNm2` (using `QrSarGis.Geodesy.calculatePolygonAreaNm2` or Gauss/planar NM² fallback). When `qrsar:shapeType` is `'circle'` or `'rectangle'`, check if parsed centroid differs from `qrsar:centerLat`/`centerLng` by `> 0.0005` deg OR parsed `areaNm2` differs from `qrsar:areaNm2` by `> 5%`: if edited, keep parsed coordinates & recomputed `centerLat`/`centerLng`/`areaNm2` and downgrade `shapeType` to `'polygon'` + warning; otherwise preserve lossless `circle`/`rectangle` metadata and ensure `points.length >= 3`.
- Track & MultiGeometry extractor: parse `<LineString>`, `<gx:Track>` (`gx:coord` triples separated by spaces), `<gx:MultiTrack>`. On `<MultiGeometry>`, if `qrsar:sarType === 'pattern'` and valid `qrsar:waypointsJson` parses cleanly into >= 2 valid coords, restore full E-SAR `waypoints`; otherwise split child `<Point>`, `<Polygon>`, `<LineString>`, `<Track>` geometries into individual items suffixed `(1), (2)` when > 1.
- ExtendedData & keyword classifier: strip HTML (`<script|style>` content removed, tags stripped, entities decoded, whitespace normalized). Validate `isSafeLocalIconPath`. Auto-classify non-QR-SAR lines using regex `/\b(pola|pattern|parallel|creeping|expanding|square|sector|e-sar|lintasan)\b/i` across name + folder + description.
- Guardrails: count `<NetworkLink>` elements to add warning; enforce `maxFeatures` (default `500`); enforce `MAX_FILE_BYTES` (`10MB`) & `MAX_KML_BYTES` (`15MB`) in `parseFile`.

- [ ] **Step 4: Run tests to verify pass**

Run: `node --test tests/js/kml_roundtrip.test.js && node -c assets/js/gis/kml_importer.js`
Expected: 100% PASS, 0 failures, 0 syntax errors.

- [ ] **Step 5: Commit**

```bash
git add assets/js/gis/kml_importer.js tests/js/kml_roundtrip.test.js
git commit -m "feat(gis): add modular dual-runtime KML/KMZ parser with Google Earth compatibility and round-trip tests"
```

---

### Task 3: Transactional Backend Batch Model & GeoJSON Circle Parity (`models/GisModel.php`)

**Files:**
- Modify: `models/GisModel.php:234-270` (`savePoint`), `350-434` (`savePolygon`), `505-590` (`saveRoute`), `650-748` (`saveSearchPattern`), `953-982` (`exportGeoJson`), plus new `resolveSafeLocalIconPath` and `importBatch`
- Create: `tests/test_gis_kml_import_export.php` (initial model & JS-via-PHP test runner assertions; Task 4 will append API controller assertions)

**Interfaces:**
- Consumes: `getDB()`, `GeoHelper::calculatePolygonAreaNm2()`, `GisModel::validId()`, `GisModel::refId()`, `GisModel::safeColor()`, existing CRUD queries.
- Produces on `GisModel`:
  - Transaction-safe `savePolygon(array $data): string`, `saveRoute(array $data): string`, `saveSearchPattern(array $data): string` (`$ownTx = !$db->inTransaction()`, wrapping `beginTransaction`, `commit`, and `rollBack` with `if ($ownTx)` so single CRUD calls preserve identical behavior while outer batch transactions remain atomic).
  - `public static function resolveSafeLocalIconPath(mixed $path): ?string` (returns relative path `assets/img/...` or `uploads/icons/...` ONLY if regex `^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$` without `..` matches AND `is_file(ROOT_DIR . '/' . $relPath)` is true; else `null`).
  - `public static function importBatch(?int $idOperasi, array $payload): array`:
    - Throws `InvalidArgumentException` if total count is `0` or `> 500`, or if any entity fails coordinate/vertex count validation (`lat ∈ [-90, 90]`, `lng ∈ [-180, 180]`, polygon `>= 3` pts, route/pattern `>= 2` pts) so transaction rolls back completely.
    - Returns:
      ```php
      [
          'counts' => ['points' => int, 'polygons' => int, 'patterns' => int, 'routes' => int],
          'points' => [...],   // hydrated DB shape matching getPoints()
          'polygons' => [...], // hydrated DB shape matching getPolygons()
          'patterns' => [...], // hydrated DB shape matching getSearchPatterns()
          'routes' => [...],   // hydrated DB shape matching getRoutes()
      ]
      ```

- [ ] **Step 1: Write failing PHP model tests in `tests/test_gis_kml_import_export.php`**

Create `tests/test_gis_kml_import_export.php` requiring `tests/bootstrap.php`, `config/database.php`, `models/OperasiModel.php`, `models/GisModel.php`, creating temporary test operations `$opA`, `$opB`, cleaning everything in `try/finally`:
1. Assert `method_exists('GisModel', 'importBatch')` and `method_exists('GisModel', 'resolveSafeLocalIconPath')`.
2. Test `resolveSafeLocalIconPath`:
   - Accepts `'assets/img/basarnas-logo.png'` (exists on disk) → `'assets/img/basarnas-logo.png'`.
   - Rejects `'https://evil.com/uploads/icons/x.png'`, `'assets/img/../config/app.php'`, `'uploads/icons/nonexistent_xyz.png'`, `'data:image/png;base64,abc'` → all return `null`.
3. Test `GisModel::importBatch($opA, ...)`:
   - Imports:
     - Preset point (`pointType: 'lkp'`) + valid `custom_upload` point (`assets/img/basarnas-logo.png`) + unsafe `custom_upload` point (`http://evil/icon.png`, label `'Pos SAR Uji'`) → verify safe kept `'custom_upload'`, unsafe downgraded to `'pos_sar'` with `customIconUrl === null`.
     - Circle polygon (`clientRefId: 'ref-poly-1'`, `shapeType: 'circle'`, `centerLat: -4.0`, `centerLng: 122.5`, `radiusNm: 2.0`, empty `points: []` OR >=3 `points`) → verify persisted in `GisPolygon` with recalculated `areaNm2 > 0` AND persisted `>= 3` vertices in `GisPolygonPoint`, plus verify `GisModel::exportGeoJson($opA, false)` outputs a `Polygon` feature for it even if we manually test an empty-points DB circle row!
     - SearchPattern referencing `polygonRef: 'ref-poly-1'` → verify `polygonId` equals the NEW `poly-imp-...` ID created in this batch!
     - SearchPattern referencing `polygonRef: 'datum_kansar'` → verify `polygonId === 'datum_kansar'`.
     - Existing DB polygon `$existingPolyOpA` saved earlier in `$opA`, plus existing `$foreignPolyOpB` saved in `$opB`:
       - Import Route in `$opA` with `polygonRef: $existingPolyOpA` (not in batch) → `polygonId === $existingPolyOpA` preserved!
       - Import Route in `$opA` with `polygonRef: $foreignPolyOpB` (cross-op) → `polygonId === null` cleared!
   - Also test importing into Base Map (`null`): verify all saved items have `id_operasi === null`, clean up afterward.
4. Test **atomic rollback**:
   - Count total `GisPoint`, `GisPolygon`, `GisSearchPattern` rows for `$opB`.
   - Call `GisModel::importBatch($opB, ['points' => [validPoint], 'polygons' => [validPolygon], 'routes' => [invalidRouteWithOnly1Point]])`.
   - Expect `InvalidArgumentException` AND row counts for `$opB` remain `0` (validPoint & validPolygon were rolled back!).

- [ ] **Step 2: Run test to verify failure**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_kml_import_export.php`
Expected: FAIL (`Call to undefined method GisModel::importBatch()`).

- [ ] **Step 3: Implement transaction guard refactor, `resolveSafeLocalIconPath`, `importBatch`, and GeoJSON circle fallback in `models/GisModel.php`**

- Wrap `beginTransaction()` / `commit()` / `rollBack()` in `savePolygon()`, `saveRoute()`, and `saveSearchPattern()` using `$ownTx = !$db->inTransaction();`:
  ```php
  $ownTx = !$db->inTransaction();
  if ($ownTx) {
      $db->beginTransaction();
  }
  ```
  and only call `$db->commit()` or `$db->rollBack()` when `$ownTx && $db->inTransaction()`!
- Add `private static function validateCoordPair(mixed $lat, mixed $lng, string $context): array` validating finite numeric floats within `[-90, 90]` and `[-180, 180]`, throwing `InvalidArgumentException` on failure.
- Add `public static function resolveSafeLocalIconPath(mixed $path): ?string`:
  Normalize whitespace & strip optional root prefix (`BASE_URL`/origin) ONLY up to `/assets/img/` or `/uploads/icons/`, verify `preg_match('#^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$#', $rel)`, reject `str_contains($rel, '..')`, and check `is_file(ROOT_DIR . '/' . $rel)`.
- Implement `public static function importBatch(?int $idOperasi, array $payload): array`:
  - Normalize `$idOperasi` (`$idOperasi !== null && $idOperasi <= 0 ? null : $idOperasi`).
  - Extract arrays `points`, `polygons`, `patterns`, `routes`. Throw `InvalidArgumentException` if total count === 0 or > 500.
  - SearchPatterns & Routes accept either `waypoints` or `points` on input (normalized cleanly when an operator reclassifies a line between `Jalur` and `Pola SAR`), returning `waypoints` on hydrated patterns and `points` on hydrated routes.
  - Validate every coordinate before DB writes, and wrap all writes in `$ownTx = !$db->inTransaction(); if ($ownTx) $db->beginTransaction();` with rollback on `\Throwable`.
  - Generate unique server IDs via `"{$prefix}-imp-" . bin2hex(random_bytes(5)) . '-' . (++$seq)`.
  - For each point: validate coords; check `pointType === 'custom_upload'` against `resolveSafeLocalIconPath`; fallback to preset `'kantor'` / `'pos_sar'` / `'pin_umum'` (`preset` validation against safe `/^[a-z0-9_]{1,40}$/`). Call `self::savePoint(...)` and collect hydrated point matching `getPoints()` shape (`lat`, `lng` float, `id_operasi`, etc.).
  - For each polygon: if `shapeType === 'circle'` with `< 3` points but valid `centerLat`/`centerLng`/`radiusNm > 0`, generate 36 geodesic vertices in PHP so `GisPolygonPoint` always has `>= 3` rows. Call `self::savePolygon(...)` and map `$clientRefIdMap[$clientRefId] = ['id' => $newId, 'name' => $polyName]`. Store hydrated polygon matching `getPolygons()`.
  - Implement 3-stage `$resolvePolyRef`: `'datum_kansar'` → `$clientRefIdMap[$ref]` → scoped SQL query (`SELECT id, name FROM GisPolygon WHERE id = :ref AND (` . ($idOperasi !== null ? '(id_operasi = :id_ops OR id_operasi IS NULL)' : 'id_operasi IS NULL') . `) LIMIT 1`) → else `['id' => '', 'name' => '']`.
  - Save each pattern and route inside the same transaction via `self::saveSearchPattern(...)` and `self::saveRoute(...)`.
  - Commit `$ownTx` cleanly and return `counts` + hydrated `points`, `polygons`, `patterns`, `routes`.
- In `GisModel::exportGeoJson()` (`models/GisModel.php:954-981`), if `empty($rawPts)` and `($pl['shapeType'] ?? '') === 'circle'` with `radiusNm > 0`, synthesize 64 geodesic ring points instead of `continue`.

- [ ] **Step 4: Run test to verify pass**

Run: `php -l models/GisModel.php && DB_DATABASE=qrsar_test php tests/test_gis_kml_import_export.php`
Expected: Lint OK and all model assertions PASS!

- [ ] **Step 5: Commit**

```bash
git add models/GisModel.php tests/test_gis_kml_import_export.php
git commit -m "feat(gis): add atomic GisModel::importBatch transaction, icon path allowlist, and circle GeoJSON fallback"
```

---

### Task 4: Backend API Endpoint `POST /api/gis/import` (`api/controllers/GisApiController.php`)

**Files:**
- Modify: `api/controllers/GisApiController.php:10-58` (`handle`), add `handleImportRoute` & payload validation helper
- Modify: `tests/test_gis_kml_import_export.php` (append HTTP/RBAC controller subprocess tests using the same `php -r` isolation pattern as `tests/test_tahap1_backend_security.php`)

**Interfaces:**
- Consumes: `BaseApiController::{requireAuth, error, json}`, `AuthHelper::isAdmin()`, `GisApiController::assertCanMutateGis()`, `GisModel::importBatch()`
- Produces endpoint:
  - `POST /api/gis/import` (via `/api/gis/import` in `api/index.php`, protected by auth & CSRF middleware)
  - Request JSON: `{ id_operasi: int|null, points: [...], polygons: [...], patterns: [...], routes: [...] }`
  - Success JSON (`200`): `{ success: true, id_operasi: int|null, counts: { points, polygons, patterns, routes }, imported: { points, polygons, patterns, routes } }`
  - Error responses: `401` unauthenticated, `403` non-Admin on Base Map or `CLOSED` op, `404` op not found, `400` invalid payload / empty / > 500 items / invalid geometry.

- [ ] **Step 1: Add failing API controller RBAC & validation tests to `tests/test_gis_kml_import_export.php`**

Append isolated `php -r` subprocess invocations of `GisApiController::handle('/gis/import', 'POST', ...)` testing:
1. Unauthenticated / non-admin `USER` targeting `id_operasi => null` (Base Map) → rejected (`success === false`, mentions Administrator / 403).
2. `ADMIN` targeting `id_operasi => null` (Base Map) with 1 Point + 1 Polygon → succeeds (`success === true`, `id_operasi === null`, `counts.points === 1`), rows exist in DB with `id_operasi IS NULL`, and are deleted in `finally`.
3. Authenticated `USER` targeting `CLOSED` operation → rejected (`403` message).
4. Authenticated `USER` targeting nonexistent `id_operasi => 9999999` → rejected (`404` message).
5. Authenticated `USER` sending empty batch (`points: []`) OR `501` items OR out-of-range coordinate (`lat: 95`) → rejected (`400` / `success === false`).
6. Authenticated `USER` importing 1 Point + 1 Polygon + 1 E-SAR Pattern + 1 Route into valid `OPEN` operation → succeeds (`success === true`, returned `imported` entities have non-empty IDs matching DB, `finally` cleans all rows).

- [ ] **Step 2: Run test to verify failure**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_kml_import_export.php`
Expected: FAIL because `/gis/import` route returns unhandled/`false`.

- [ ] **Step 3: Implement `handleImportRoute` in `api/controllers/GisApiController.php`**

- Register `if (self::handleImportRoute($path, $method, $input)) return true;` in `GisApiController::handle()`.
- In `handleImportRoute(string $path, string $method, array $input): bool`:
  - Match `$path === '/gis/import' && $method === 'POST'`.
  - Call `self::requireAuth()`.
  - Parse target `id_operasi`: `null` if not set, empty string, `'basemap'`, or `0`; else validate numeric positive integer (`filter_var`, reject invalid non-numeric with `400`).
  - Enforce RBAC + operation lock via `self::assertCanMutateGis('GisPoint', null, ['id_operasi' => $idOperasi])`.
  - Validate entity arrays exist as arrays (`points`, `polygons`, `patterns`, `routes`), total items between `1` and `500`, each item is an array, calling `GisModel::importBatch($idOperasi, $input)`.
  - Catch `InvalidArgumentException` → `self::error($e->getMessage(), 400)`. Catch `Throwable` → `error_log(...)` + `self::error('Gagal mengimpor data KML/KMZ secara atomik.', 500)`.
  - On success return `self::json(['success' => true, 'id_operasi' => $idOperasi, 'counts' => $result['counts'], 'imported' => ['points' => $result['points'], 'polygons' => $result['polygons'], 'patterns' => $result['patterns'], 'routes' => $result['routes']]])`.

- [ ] **Step 4: Run test to verify pass**

Run: `php -l api/controllers/GisApiController.php && DB_DATABASE=qrsar_test php tests/test_gis_kml_import_export.php && DB_DATABASE=qrsar_test php tests/test_phase8_relations_logger.php`
Expected: Lint OK, `test_gis_kml_import_export.php` 100% PASS, `test_phase8_relations_logger.php` 100% PASS!

- [ ] **Step 5: Commit**

```bash
git add api/controllers/GisApiController.php tests/test_gis_kml_import_export.php
git commit -m "feat(api): add POST /api/gis/import endpoint with RBAC, coordinate validation, and atomic batch persistence"
```

---

### Task 5: Scope-Aware Export Modal + Interactive Import/Preview Modal (`views/gis.php` & `assets/js/gis_master.js`)

**Files:**
- Modify: `views/gis.php:362-395` (header Ekspor + new `Impor KML/KMZ` button), `1570-1627` (modals markup, `isAdmin` in `SAR_INITIAL_GIS_DATA`, `<script src="kml_importer.js">`)
- Modify: `assets/js/gis_master.js:6396-6571` (remove duplicate inline fallback KML/GeoJSON builders while preserving `window.QrSarGis.Export.generateKmlDocument` delegator), `6682-6714` (`initExportDropdown` → Export Scope Modal + new `initImportKmlUI()`), `8366` (call `initImportKmlUI()` inside `bootstrapApp`)
- Modify: `tests/test_gis_kml_import_export.php` (add view integration assertions & run `node --test tests/js/kml_roundtrip.test.js` inside PHP suite or verify CLI + `run_all.php`)

**Interfaces:**
- Consumes:
  - `window.SAR_INITIAL_GIS_DATA.isAdmin` (`boolean`), `window.SAR_INITIAL_GIS_DATA.operasiList`, `rawAllPoints`, `rawAllPolygons`, `rawAllPatterns`, `rawAllRoutes`, `hiddenPointIds`, `hiddenPolygonIds`, `hiddenPatternIds`, `hiddenRouteIds`, `gisFetch`, `BASE_URL`, `applyActiveFilter()`, `reRenderAllLayers()`, `notifyGisStorageUpdate()`, `showToast()`, `window.QrSarGis.Export`, `window.QrSarGis.KmlImporter`, `window.QrSarGis.Markers.PRESET_ICONS`, `map.fitBounds()`
- Produces:
  - Interactive Export Spatial Modal (`#exportSpatialModal`) allowing source choice (`current`, `operasi` + `#exportOperasiSelect` + `#exportIncludeBaseCheck`, `basemap`), `#exportIncludeHiddenCheck`, format radio/buttons (`kmz`, `kml`, `geojson`), and scope-based download filenames (`SAR_KENDARI_OPS_<id>_YYYY-MM-DD`, `SAR_KENDARI_BASEMAP_YYYY-MM-DD`, `SAR_KENDARI_PLOTTING_YYYY-MM-DD`).
  - Interactive Import KML/KMZ Modal (`#importKmlModal`) with Step 1 file dropzone/input (`#importKmlFileInput`), Step 2 target selector (`#importTargetTypeOperasi`, `#importTargetOperasiSelect`, `#importTargetTypeBasemap` disabled with note when `!isAdmin`), Step 3 summary cards + curated checklist (`#importPreviewList` with name input, line `Jalur`/`Pola SAR` selector + bulk toggle `#importSetAllLinesRoute` / `#importSetAllLinesPattern`, point `pointType` preset selector, warnings panel `#importWarningsBox`, `#importAutoZoomCheck`, `#btnConfirmImportKml`).

- [ ] **Step 1: Add failing UI integration assertions to `tests/test_gis_kml_import_export.php`**

Append assertions in `tests/test_gis_kml_import_export.php` inspecting `views/gis.php`, `assets/js/gis_master.js`, and executing `node --test tests/js/kml_roundtrip.test.js` via `shell_exec`/`exec`:
1. `views/gis.php` exposes `isAdmin:` inside `window.SAR_INITIAL_GIS_DATA`.
2. `views/gis.php` loads `assets/js/gis/kml_importer.js`.
3. `views/gis.php` contains `#btnOpenImportKmlModal`, `#importKmlModal`, `#exportSpatialModal`, `#btnConfirmImportKml`.
4. `assets/js/gis_master.js` calls `/api/gis/import`, delegates to `window.QrSarGis.KmlImporter.parseFile`, preserves `window.QrSarGis.Export.generateKmlDocument`, and no longer embeds legacy duplicate inline `<kml xmlns=` fallback XML string.
5. Sub-executes `node --test tests/js/kml_roundtrip.test.js` and asserts exit code `0` so running `php tests/run_all.php` automatically covers the JS suite too!

- [ ] **Step 2: Run test to verify UI assertions fail**

Run: `DB_DATABASE=qrsar_test php tests/test_gis_kml_import_export.php`
Expected: FAIL on missing `isAdmin`, `kml_importer.js`, `#importKmlModal`, `#exportSpatialModal`.

- [ ] **Step 3: Add `isAdmin`, `kml_importer.js`, Export Scope Modal, and Import KML/KMZ Modal in `views/gis.php`**

- In `views/gis.php` header right section (beside Capture Peta button):
  - Replace the legacy dropdown inside `#exportMenuWrapper` with `#btnToggleExportMenu` clicking directly to open `#exportSpatialModal` so users always choose export scope, hidden-layer inclusion, and format (`.kmz`/`.kml`/`.geojson`) in one dialog.
  - Add `#btnOpenImportKmlModal` (**Impor KML/KMZ**) right beside `#btnToggleExportMenu` styled consistently in `cockpit-glass` / slate button hierarchy.
- Right before `<!-- Bottom Crosshair Coordinate HUD Bar -->` in `views/gis.php`:
  - Add `#exportSpatialModal` (cockpit-glass overlay modal, dark/light aware, max-h with scroll, close button, Source radio cards: *Tampilan Peta Saat Ini*, *Operasi SAR Aktif* + `<select id="exportOperasiSelect">` + `<input type="checkbox" id="exportIncludeBaseCheck">`, *Base Map Global*; `<input type="checkbox" id="exportIncludeHiddenCheck">`; Format selector `kmz` / `kml` / `geojson`; `#btnExecuteSpatialExport`).
  - Add `#importKmlModal` (cockpit-glass overlay modal, max-h with scroll):
    - Step 1: drag-drop zone + `<input type="file" id="importKmlFileInput" accept=".kml,.kmz">` + selected file info badge.
    - Step 2: Target Scope radios (`#importTargetTypeOperasi` with `<select id="importTargetOperasiSelect">` populated from `activeOperasiList`, and `#importTargetTypeBasemap` disabled + badge *"Khusus Administrator"* if `!AuthHelper::isAdmin()`, clear message if no active ops & non-admin).
    - Step 3: Counters (`Titik`, `Poligon`, `Pola SAR`, `Jalur`, `Peringatan`), warnings alert box `#importWarningsBox`, bulk toolbar (`Pilih Semua`, `Kosongkan`, `Semua Garis → Jalur`, `Semua Garis → Pola SAR`), scrollable `#importPreviewList`, `#importAutoZoomCheck`, footer Cancel + `#btnConfirmImportKml`.
- In `window.SAR_INITIAL_GIS_DATA` (`views/gis.php`): add `isAdmin: <?= jsonSafe(AuthHelper::isAdmin()) ?>,`.
- In `<script>` list (`views/gis.php`): add `<script src="<?= assetUrl('assets/js/gis/kml_importer.js') ?>"></script>` right after `assets/js/gis/export.js`.

- [ ] **Step 4: Wire Export Scope Modal, clean delegation, and Import KML/KMZ workflow in `assets/js/gis_master.js`**

- In `assets/js/gis_master.js` (`~6396-6571`):
  - Keep thin `generateKmlDocument(customData)` calling `window.QrSarGis.Export.generateKmlDocument(customData || buildExportPayload(...))` (preserving `test_phase5_gis_modular.php` contract), and remove duplicated legacy XML/GeoJSON string builder fallbacks!
  - Implement `buildExportPayload(scopeMode, options)` reading from `rawAll*` (filtered by `'current'`, `'operasi'` + `includeBase`, or `'basemap'`) and filtering `hidden*Ids` unless `includeHidden === true`, enriching `patterns` with `segments: getPatternPolylineCoords(pat.patternType, pat.waypoints)` for E-SAR `<MultiGeometry>`, and computing filename (`SAR_KENDARI_OPS_<id>_YYYY-MM-DD`, `SAR_KENDARI_BASEMAP_YYYY-MM-DD`, `SAR_KENDARI_PLOTTING_YYYY-MM-DD`).
- Replace dropdown toggle in `initExportDropdown()` with modal open/close & export execution wiring for `#btnToggleExportMenu` / `#exportSpatialModal`. Auto-toggle `#exportIncludeHiddenCheck` to `true` when user selects explicit `'operasi'` or `'basemap'`, and `false` when selecting `'current'`.
- Implement `initImportKmlUI()` (called in `bootstrapApp()` with `try/catch`):
  - Handle file drop/change → call `window.QrSarGis.KmlImporter.parseFile(file)` → populate working preview items array (`checked: true`, editable `label`/`name`, line `sarType` toggle `'route'|'pattern'`, point `pointType` select populated from `PRESET_ICONS`) + show warnings in `#importWarningsBox`.
  - Handle Target Scope initialization (`isAdmin = !!initialData.isAdmin`; disable `#importTargetTypeBasemap` when `!isAdmin`; preselect current open `currentOperasiId` if in `operasi` mode). Disable `#btnConfirmImportKml` when target invalid or 0 checked items.
  - Handle `#btnConfirmImportKml`: split checked items into `{ id_operasi, points, polygons, patterns, routes }` (normalizing line vertices to `waypoints` when classified as `pattern` and `points` when classified as `route`), send `POST ${BASE_URL}/api/gis/import` via `gisFetch` (auto-injecting `X-CSRF-Token`), append returned `imported.points/polygons/patterns/routes` into `rawAll*`, call `applyActiveFilter()`, `reRenderAllLayers()`, `notifyGisStorageUpdate()`, fit bounds (`L.latLngBounds`) across newly visible layers when `#importAutoZoomCheck.checked`, and if imported into an active operation currently not selected in `filterMode`, show a toast informing count + offer/button to switch view or refresh dropdown!

- [ ] **Step 5: Run full verification suite**

Run:
1. `node -c assets/js/gis/export.js && node -c assets/js/gis/kml_importer.js && node -c assets/js/gis_master.js`
2. `php -l views/gis.php && php -l models/GisModel.php && php -l api/controllers/GisApiController.php`
3. `node --test tests/js/kml_roundtrip.test.js`
4. `DB_DATABASE=qrsar_test php tests/test_gis_kml_import_export.php`
5. `DB_DATABASE=qrsar_test php tests/test_phase5_gis_modular.php`
6. `DB_DATABASE=qrsar_test php tests/run_all.php`
Expected: ALL syntax checks exit 0, `kml_roundtrip.test.js` 100% PASS, `test_gis_kml_import_export.php` 100% PASS, and **entire** `php tests/run_all.php` suite 100% PASS (0 FAIL).

- [ ] **Step 6: Commit**

```bash
git add views/gis.php assets/js/gis_master.js tests/test_gis_kml_import_export.php
git commit -m "feat(gis): add KML/KMZ scope export modal and interactive import preview UI"
```
