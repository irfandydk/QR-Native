/**
 * QR-SAR Tactical GIS - Modular Browser & Node.js KML/KMZ Parser
 * File: assets/js/gis/kml_importer.js
 * 
 * Supports standard KML 2.2, Google Earth Pro exports, gx:Track, StyleMap,
 * MultiGeometry splitting, external edit detection, HTML sanitization, and KMZ extraction.
 */

(function (global) {
  'use strict';

  global.QrSarGis = global.QrSarGis || {};

  // Maximum size limits & guardrails
  const MAX_FILE_BYTES = 10485760; // 10 MB
  const MAX_KML_BYTES = 15728640;  // 15 MB
  const MAX_FEATURES = 500;

  // Fallback preset marker icon types when markers.js is not loaded
  const FALLBACK_PRESET_TYPES = new Set([
    'lkp', 'orang_hilang', 'tenggelam', 'kecelakaan_kapal', 'perahu', 'kecelakaan_pesawat',
    'laka_darat', 'kmm', 'banjir', 'longsor', 'gempa', 'kebakaran', 'gunung_api', 'badai',
    'hewan_buas', 'medis_gawat', 'bencana', 'kantor', 'pos_sar', 'posko_sar', 'tenda',
    'rumah_sakit', 'pelabuhan', 'bandara', 'helipad', 'mercusuar', 'spbu_bbm', 'rumah',
    'pantai', 'pulau', 'hutan', 'jembatan', 'kapal', 'rib', 'perahu_karet', 'speedboat',
    'helikopter', 'pesawat', 'rescue_car', 'rescue_truck', 'ambulans', 'motor_trail',
    'penyelam', 'drone', 'tim_rescue', 'k9', 'korban_selamat', 'korban_md', 'korban_hilang',
    'korban_luka', 'alphabet', 'pin_umum', 'waypoint', 'target', 'debris', 'radio'
  ]);

  const SAR_KEYWORD_REGEX = /\b(pola|pattern|parallel|creeping|expanding|square|sector|e-sar|lintasan)\b/i;

  /**
   * Validate geographical coordinate bounds
   */
  function isValidCoord(lat, lng) {
    return Number.isFinite(lat) && lat >= -90 && lat <= 90 &&
           Number.isFinite(lng) && lng >= -180 && lng <= 180;
  }

  /**
   * Convert KML aabbggrr hex string to CSS #rrggbb and opacity
   * @param {string} abgr 8-char hex (e.g. ff2626dc, 7f0000ff)
   * @returns {{ hex: string, opacity: number }|null}
   */
  function kmlAbgrToCss(abgr) {
    if (typeof abgr !== 'string') return null;
    let str = abgr.trim().replace(/^#/, '');
    if (/^[0-9a-fA-F]{6}$/.test(str)) {
      str = 'ff' + str;
    }
    if (!/^[0-9a-fA-F]{8}$/.test(str)) {
      return null;
    }
    const aa = parseInt(str.slice(0, 2), 16);
    const bb = str.slice(2, 4).toLowerCase();
    const gg = str.slice(4, 6).toLowerCase();
    const rr = str.slice(6, 8).toLowerCase();
    const opacity = Math.round((aa / 255) * 100) / 100;
    return {
      hex: `#${rr}${gg}${bb}`,
      opacity
    };
  }

  /**
   * Strip HTML tags and <script>/<style> elements from descriptions, decoding entities
   * @param {unknown} raw Raw description text or CDATA
   * @returns {string} Plain sanitized text
   */
  function stripHtmlDescription(raw) {
    if (raw === null || raw === undefined) return '';
    let str = String(raw);
    // Remove script and style tags and all inner content
    str = str.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
    str = str.replace(/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/gi, '');
    // Remove all remaining HTML tags
    str = str.replace(/<[^>]+>/g, '');
    // Decode HTML entities
    str = str
      .replace(/&quot;/g, '"')
      .replace(/&apos;/g, "'")
      .replace(/&#39;/g, "'")
      .replace(/&lt;/g, '<')
      .replace(/&gt;/g, '>')
      .replace(/&nbsp;/g, ' ')
      .replace(/&#(\d+);/g, (match, dec) => String.fromCharCode(parseInt(dec, 10)))
      .replace(/&#x([0-9a-fA-F]+);/g, (match, hex) => String.fromCharCode(parseInt(hex, 16)))
      .replace(/&amp;/g, '&');
    return str.trim();
  }

  /**
   * Validate safe local icon path
   * Accepts only ^(assets/img|uploads/icons)/[A-Za-z0-9_.\-/]+$
   * Rejects .., external URLs, query/hash, whitespace, and data: URIs
   * @param {unknown} path
   * @returns {string|null}
   */
  function isSafeLocalIconPath(path) {
    if (typeof path !== 'string' || !path.trim()) return null;
    const trimmed = path.trim();
    if (
      trimmed.includes('..') ||
      trimmed.startsWith('data:') ||
      trimmed.startsWith('http://') ||
      trimmed.startsWith('https://') ||
      /[?#\s]/.test(trimmed)
    ) {
      return null;
    }
    if (!/^(assets\/img|uploads\/icons)\/[A-Za-z0-9_.\-/]+$/.test(trimmed)) {
      return null;
    }
    return trimmed;
  }

  /**
   * Universal XML Node Adapter
   * Provides identical querying interface across browser DOMParser and Node pure-JS parser
   */
  class XmlNodeAdapter {
    constructor(tagName, localName, attributes, children, textContent) {
      this.tagName = tagName || '';
      this.localName = localName || tagName || '';
      this.attributes = attributes || {};
      this.children = children || [];
      this.textContent = textContent || '';
    }

    getAttribute(name) {
      if (!name) return null;
      if (this.attributes[name] !== undefined) return this.attributes[name];
      const lower = name.toLowerCase();
      for (const k of Object.keys(this.attributes)) {
        if (k.toLowerCase() === lower) return this.attributes[k];
      }
      return null;
    }

    childrenNamed(name) {
      if (!name) return this.children.slice();
      const target = name.toLowerCase();
      return this.children.filter(c =>
        c.localName.toLowerCase() === target || c.tagName.toLowerCase() === target
      );
    }

    firstChildNamed(name) {
      const list = this.childrenNamed(name);
      return list.length > 0 ? list[0] : null;
    }

    textOf(name) {
      if (!name) return (this.textContent || '').trim();
      const child = this.firstChildNamed(name);
      return child ? (child.textContent || '').trim() : '';
    }

    getElementsByTagName(name) {
      if (!name) return [];
      const target = name.toLowerCase();
      const result = [];
      function search(node) {
        for (let i = 0; i < node.children.length; i++) {
          const child = node.children[i];
          if (child.localName.toLowerCase() === target || child.tagName.toLowerCase() === target) {
            result.push(child);
          }
          search(child);
        }
      }
      search(this);
      return result;
    }
  }

  /**
   * Convert native browser DOM element tree to XmlNodeAdapter
   */
  function domToAdapter(domNode) {
    const tagName = domNode.tagName || domNode.nodeName || '';
    const localName = domNode.localName || (tagName.includes(':') ? tagName.split(':')[1] : tagName);
    const attributes = {};
    if (domNode.attributes) {
      for (let i = 0; i < domNode.attributes.length; i++) {
        const a = domNode.attributes[i];
        attributes[a.name] = a.value;
      }
    }
    const children = [];
    let text = '';
    if (domNode.childNodes) {
      for (let i = 0; i < domNode.childNodes.length; i++) {
        const child = domNode.childNodes[i];
        if (child.nodeType === 1) { // ELEMENT_NODE
          children.push(domToAdapter(child));
        } else if (child.nodeType === 3 || child.nodeType === 4) { // TEXT_NODE or CDATA_SECTION_NODE
          text += child.nodeValue || '';
        }
      }
    }
    return new XmlNodeAdapter(tagName, localName, attributes, children, text);
  }

  /**
   * Pure-JS XML tokenizer and tree builder
   * Used in Node.js and environments without DOMParser
   */
  function parseXmlPureJs(xml) {
    if (typeof xml !== 'string' || !xml.trim()) {
      throw new Error('Malformed XML: empty content');
    }

    let i = 0;
    const len = xml.length;
    const stack = [];
    let root = null;

    function decodeXmlEntities(str) {
      return str
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'")
        .replace(/&#39;/g, "'")
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&#(\d+);/g, (m, d) => String.fromCharCode(parseInt(d, 10)))
        .replace(/&#x([0-9a-fA-F]+);/g, (m, h) => String.fromCharCode(parseInt(h, 16)))
        .replace(/&amp;/g, '&');
    }

    function parseAttributes(attrStr) {
      const attrs = {};
      const re = /([A-Za-z0-9_.:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+))/g;
      let match;
      while ((match = re.exec(attrStr)) !== null) {
        const name = match[1];
        const val = match[2] !== undefined ? match[2] : (match[3] !== undefined ? match[3] : match[4]);
        attrs[name] = decodeXmlEntities(val);
      }
      return attrs;
    }

    while (i < len) {
      const nextTag = xml.indexOf('<', i);
      if (nextTag === -1) {
        const text = xml.slice(i);
        if (stack.length > 0) {
          stack[stack.length - 1].textContent += decodeXmlEntities(text);
        }
        break;
      }

      if (nextTag > i) {
        const text = xml.slice(i, nextTag);
        if (stack.length > 0) {
          stack[stack.length - 1].textContent += decodeXmlEntities(text);
        }
      }

      i = nextTag;

      // 1. Comments: <!-- ... -->
      if (xml.startsWith('<!--', i)) {
        const endComment = xml.indexOf('-->', i + 4);
        if (endComment === -1) {
          throw new Error('Malformed XML: unclosed comment');
        }
        i = endComment + 3;
        continue;
      }

      // 2. CDATA: <![CDATA[ ... ]]>
      if (xml.startsWith('<![CDATA[', i)) {
        const endCdata = xml.indexOf(']]>', i + 9);
        if (endCdata === -1) {
          throw new Error('Malformed XML: unclosed CDATA');
        }
        const cdataText = xml.slice(i + 9, endCdata);
        if (stack.length > 0) {
          stack[stack.length - 1].textContent += cdataText;
        }
        i = endCdata + 3;
        continue;
      }

      // 3. Processing instructions or DOCTYPE
      if (xml.startsWith('<?', i)) {
        const endPi = xml.indexOf('?>', i + 2);
        if (endPi === -1) {
          throw new Error('Malformed XML: unclosed processing instruction');
        }
        i = endPi + 2;
        continue;
      }
      if (xml.startsWith('<!DOCTYPE', i) || xml.startsWith('<!doctype', i)) {
        const endDt = xml.indexOf('>', i + 9);
        if (endDt === -1) {
          throw new Error('Malformed XML: unclosed DOCTYPE');
        }
        i = endDt + 1;
        continue;
      }

      // 4. Closing tag: </tag>
      if (xml.startsWith('</', i)) {
        const endClose = xml.indexOf('>', i + 2);
        if (endClose === -1) {
          throw new Error('Malformed XML: unclosed closing tag');
        }
        const closeTagName = xml.slice(i + 2, endClose).trim();
        i = endClose + 1;

        if (stack.length === 0) {
          throw new Error(`Malformed XML: unexpected closing tag </${closeTagName}>`);
        }
        const top = stack.pop();
        if (top.tagName !== closeTagName && top.localName !== closeTagName) {
          throw new Error(`Malformed XML: mismatched closing tag </${closeTagName}>, expected </${top.tagName}>`);
        }
        continue;
      }

      // 5. Opening or self-closing tag: <tag ...> or <tag .../>
      const endTag = xml.indexOf('>', i + 1);
      if (endTag === -1) {
        throw new Error('Malformed XML: unclosed tag');
      }

      const tagContent = xml.slice(i + 1, endTag).trim();
      i = endTag + 1;

      const isSelfClosing = tagContent.endsWith('/');
      const cleanContent = isSelfClosing ? tagContent.slice(0, -1).trim() : tagContent;

      const spaceIdx = cleanContent.search(/\s/);
      const tagName = spaceIdx === -1 ? cleanContent : cleanContent.slice(0, spaceIdx);
      const attrStr = spaceIdx === -1 ? '' : cleanContent.slice(spaceIdx);

      if (!tagName || !/^[A-Za-z_:][A-Za-z0-9_.:-]*$/.test(tagName)) {
        throw new Error(`Malformed XML: invalid tag name "${tagName}"`);
      }

      const localName = tagName.includes(':') ? tagName.split(':')[1] : tagName;
      const attrs = parseAttributes(attrStr);
      const node = new XmlNodeAdapter(tagName, localName, attrs, [], '');

      if (stack.length > 0) {
        stack[stack.length - 1].children.push(node);
      } else {
        if (root !== null) {
          throw new Error('Malformed XML: multiple root elements');
        }
        root = node;
      }

      if (!isSelfClosing) {
        stack.push(node);
      }
    }

    if (stack.length > 0) {
      throw new Error(`Malformed XML: unclosed tag <${stack[stack.length - 1].tagName}>`);
    }
    if (!root) {
      throw new Error('Malformed XML: no root element found');
    }

    return root;
  }

  /**
   * Parse XML string into an XmlNodeAdapter
   * Uses browser DOMParser when available, falling back to pure-JS parser
   */
  function parseXml(xmlString) {
    if (typeof xmlString !== 'string' || !xmlString.trim()) {
      throw new Error('Malformed XML: empty or non-string input');
    }

    if (typeof DOMParser !== 'undefined') {
      try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(xmlString, 'text/xml');
        const parserErrors = doc.getElementsByTagName('parsererror');
        if (parserErrors && parserErrors.length > 0) {
          throw new Error('Malformed XML: ' + (parserErrors[0].textContent || 'DOMParser error'));
        }
        if (!doc.documentElement) {
          throw new Error('Malformed XML: no document element');
        }
        return domToAdapter(doc.documentElement);
      } catch (e) {
        if (e.message && e.message.startsWith('Malformed XML:')) {
          throw e;
        }
        throw new Error('Malformed XML: ' + e.message);
      }
    }

    return parseXmlPureJs(xmlString);
  }

  /**
   * Calculate polygon area in NM² (spherical geodesy or planar Gauss fallback)
   */
  function calculatePolygonAreaNm2(points) {
    if (!Array.isArray(points) || points.length < 3) return 0;

    if (global.QrSarGis && global.QrSarGis.Geodesy) {
      if (typeof global.QrSarGis.Geodesy.calculatePolygonAreaNm2 === 'function') {
        return global.QrSarGis.Geodesy.calculatePolygonAreaNm2(points);
      }
      if (typeof global.QrSarGis.Geodesy.calculatePolygonSphericalArea === 'function') {
        const res = global.QrSarGis.Geodesy.calculatePolygonSphericalArea(points);
        if (res && typeof res.sqNm === 'number') return res.sqNm;
      }
    }

    // Planar Gauss formula in Nautical Miles
    let sumLat = 0;
    for (let i = 0; i < points.length; i++) {
      sumLat += points[i].lat;
    }
    const meanLatRad = (sumLat / points.length) * (Math.PI / 180);
    const cosMeanLat = Math.cos(meanLatRad);

    let areaSum = 0;
    const n = points.length;
    for (let i = 0; i < n; i++) {
      const j = (i + 1) % n;
      const pt1 = points[i];
      const pt2 = points[j];
      const x1 = pt1.lng * 60.0 * cosMeanLat;
      const y1 = pt1.lat * 60.0;
      const x2 = pt2.lng * 60.0 * cosMeanLat;
      const y2 = pt2.lat * 60.0;
      areaSum += (x1 * y2) - (x2 * y1);
    }
    const sqNm = Math.abs(areaSum) / 2.0;
    return Math.round(sqNm * 100) / 100;
  }

  /**
   * Calculate geometric centroid of points
   */
  function calculateCentroid(points) {
    if (!Array.isArray(points) || points.length === 0) {
      return { lat: 0, lng: 0 };
    }
    let sumLat = 0;
    let sumLng = 0;
    for (let i = 0; i < points.length; i++) {
      sumLat += points[i].lat;
      sumLng += points[i].lng;
    }
    return {
      lat: sumLat / points.length,
      lng: sumLng / points.length
    };
  }

  /**
   * Parse coordinate tuples from whitespace-separated text (lng,lat[,alt])
   */
  function parseCoordinateTuples(coordsText) {
    if (typeof coordsText !== 'string' || !coordsText.trim()) return [];
    const tokens = coordsText.trim().split(/\s+/);
    const points = [];
    for (let i = 0; i < tokens.length; i++) {
      const parts = tokens[i].split(',');
      if (parts.length >= 2) {
        const lng = parseFloat(parts[0]);
        const lat = parseFloat(parts[1]);
        if (isValidCoord(lat, lng)) {
          points.push({ lat, lng });
        }
      }
    }
    return points;
  }

  /**
   * Strip duplicate closing vertex from polygon ring
   */
  function stripClosingVertex(points) {
    if (!Array.isArray(points) || points.length < 2) return points;
    const first = points[0];
    const last = points[points.length - 1];
    if (Math.abs(first.lat - last.lat) < 1e-9 && Math.abs(first.lng - last.lng) < 1e-9) {
      return points.slice(0, -1);
    }
    return points;
  }

  /**
   * Index all <Style> and <StyleMap> elements in document
   */
  function indexStyles(root) {
    const styles = new Map();
    const styleMaps = new Map();

    const styleNodes = root.getElementsByTagName('Style');
    for (let i = 0; i < styleNodes.length; i++) {
      const s = styleNodes[i];
      const id = s.getAttribute('id');
      if (id) {
        styles.set(id, s);
      }
    }

    const styleMapNodes = root.getElementsByTagName('StyleMap');
    for (let i = 0; i < styleMapNodes.length; i++) {
      const sm = styleMapNodes[i];
      const id = sm.getAttribute('id');
      if (id) {
        const pairs = sm.childrenNamed('Pair');
        let normalTarget = null;
        let firstTarget = null;
        for (let j = 0; j < pairs.length; j++) {
          const pair = pairs[j];
          const key = pair.textOf('key');
          const styleUrl = pair.textOf('styleUrl');
          const inlineStyle = pair.firstChildNamed('Style');
          const target = styleUrl ? styleUrl.replace(/^#/, '') : inlineStyle;
          if (key === 'normal') {
            normalTarget = target;
          }
          if (!firstTarget) {
            firstTarget = target;
          }
        }
        styleMaps.set(id, normalTarget || firstTarget);
      }
    }

    return { styles, styleMaps };
  }

  /**
   * Resolve styling properties for a placemark
   */
  function resolvePlacemarkStyle(placemarkNode, styleIndex) {
    let styleNode = placemarkNode.firstChildNamed('Style');
    if (!styleNode) {
      const styleUrl = placemarkNode.textOf('styleUrl');
      if (styleUrl) {
        let targetId = styleUrl.replace(/^#/, '');
        if (styleIndex.styleMaps.has(targetId)) {
          const mapped = styleIndex.styleMaps.get(targetId);
          if (typeof mapped === 'string') {
            targetId = mapped.replace(/^#/, '');
          } else if (mapped instanceof XmlNodeAdapter) {
            styleNode = mapped;
          }
        }
        if (!styleNode && styleIndex.styles.has(targetId)) {
          styleNode = styleIndex.styles.get(targetId);
        }
      }
    }

    const resolved = {
      iconColor: null,
      iconHref: null,
      strokeColor: null,
      strokeWeight: null,
      strokeOpacity: null,
      fillColor: null,
      fillOpacity: null
    };

    if (!styleNode) return resolved;

    // IconStyle
    const iconStyle = styleNode.firstChildNamed('IconStyle');
    if (iconStyle) {
      const colText = iconStyle.textOf('color');
      if (colText) {
        const c = kmlAbgrToCss(colText);
        if (c) resolved.iconColor = c.hex;
      }
      const icon = iconStyle.firstChildNamed('Icon');
      if (icon) {
        resolved.iconHref = icon.textOf('href');
      }
    }

    // LineStyle
    const lineStyle = styleNode.firstChildNamed('LineStyle');
    if (lineStyle) {
      const colText = lineStyle.textOf('color');
      if (colText) {
        const c = kmlAbgrToCss(colText);
        if (c) {
          resolved.strokeColor = c.hex;
          resolved.strokeOpacity = c.opacity;
        }
      }
      const widthText = lineStyle.textOf('width');
      if (widthText) {
        const w = parseFloat(widthText);
        if (Number.isFinite(w) && w >= 0) resolved.strokeWeight = w;
      }
    }

    // PolyStyle
    const polyStyle = styleNode.firstChildNamed('PolyStyle');
    if (polyStyle) {
      const colText = polyStyle.textOf('color');
      if (colText) {
        const c = kmlAbgrToCss(colText);
        if (c) {
          resolved.fillColor = c.hex;
          resolved.fillOpacity = c.opacity;
        }
      }
      const fillText = polyStyle.textOf('fill');
      if (fillText === '0' || fillText === 'false') {
        resolved.fillOpacity = 0;
      }
      const outlineText = polyStyle.textOf('outline');
      if (outlineText === '0' || outlineText === 'false') {
        resolved.strokeWeight = 0;
      }
    }

    return resolved;
  }

  /**
   * Extract ExtendedData entries (Data & SimpleData)
   */
  function extractExtendedData(placemarkNode) {
    const ext = {};
    const extDataNode = placemarkNode.firstChildNamed('ExtendedData');
    if (!extDataNode) return ext;

    const dataNodes = extDataNode.getElementsByTagName('Data');
    for (let i = 0; i < dataNodes.length; i++) {
      const d = dataNodes[i];
      const name = d.getAttribute('name');
      if (name) {
        ext[name] = d.textOf('value');
      }
    }

    const simpleDataNodes = extDataNode.getElementsByTagName('SimpleData');
    for (let i = 0; i < simpleDataNodes.length; i++) {
      const sd = simpleDataNodes[i];
      const name = sd.getAttribute('name');
      if (name) {
        ext[name] = sd.textContent.trim();
      }
    }

    return ext;
  }

  /**
   * Recursively collect placemarks preserving enclosing folder name
   */
  function collectPlacemarksWithContext(node, currentFolder, results) {
    for (let i = 0; i < node.children.length; i++) {
      const child = node.children[i];
      const lname = child.localName.toLowerCase();
      if (lname === 'folder') {
        const folderName = child.textOf('name') || currentFolder;
        collectPlacemarksWithContext(child, folderName, results);
      } else if (lname === 'placemark') {
        results.push({ placemark: child, folderName: currentFolder });
      } else {
        collectPlacemarksWithContext(child, currentFolder, results);
      }
    }
    return results;
  }

  /**
   * Parse coordinates from <gx:Track> or <Track> element (<gx:coord>lng lat alt</gx:coord>)
   */
  function parseTrackCoords(trackNode) {
    const coordNodes = trackNode.children.filter(c =>
      c.localName.toLowerCase() === 'coord'
    );
    const points = [];
    for (let i = 0; i < coordNodes.length; i++) {
      const parts = coordNodes[i].textContent.trim().split(/\s+/);
      if (parts.length >= 2) {
        const lng = parseFloat(parts[0]);
        const lat = parseFloat(parts[1]);
        if (isValidCoord(lat, lng)) {
          points.push({ lat, lng });
        }
      }
    }
    return points;
  }

  /**
   * Parse a single KML XML string
   * @param {string} xmlString
   * @param {{ maxFeatures?: number }} [options]
   * @returns {{ points: Array, polygons: Array, patterns: Array, routes: Array, warnings: Array<string>, stats: Object }}
   */
  function parseKmlString(xmlString, options) {
    const root = parseXml(xmlString);
    const maxFeatures = (options && typeof options.maxFeatures === 'number' && options.maxFeatures > 0)
      ? options.maxFeatures
      : MAX_FEATURES;

    const points = [];
    const polygons = [];
    const patterns = [];
    const routes = [];
    const warnings = [];

    const stats = {
      totalPlacemarks: 0,
      parsedCount: 0,
      skippedCount: 0,
      truncated: false
    };

    // 1. Guardrail: Detect external NetworkLink elements
    const networkLinks = root.getElementsByTagName('NetworkLink');
    if (networkLinks.length > 0) {
      for (let i = 0; i < networkLinks.length; i++) {
        const nl = networkLinks[i];
        const nlName = nl.textOf('name') || 'External Link';
        warnings.push(`Skipped external NetworkLink "${nlName}": remote network links are not fetched for security.`);
      }
    }

    // 2. Index Styles & StyleMaps
    const styleIndex = indexStyles(root);

    // 3. Collect Placemarks with folder hierarchy
    const placemarkEntries = collectPlacemarksWithContext(root, '', []);
    stats.totalPlacemarks = placemarkEntries.length;

    let idSeq = 1;
    let limitReached = false;

    for (let pIdx = 0; pIdx < placemarkEntries.length; pIdx++) {
      if (stats.parsedCount >= maxFeatures) {
        if (!limitReached) {
          warnings.push(`Maximum feature limit of ${maxFeatures} reached. Remaining features were truncated.`);
          stats.truncated = true;
          limitReached = true;
        }
        stats.skippedCount += (placemarkEntries.length - pIdx);
        break;
      }

      const { placemark, folderName } = placemarkEntries[pIdx];
      const name = placemark.textOf('name') || '';
      const rawDesc = placemark.textOf('description');
      const description = stripHtmlDescription(rawDesc);
      const ext = extractExtendedData(placemark);
      const style = resolvePlacemarkStyle(placemark, styleIndex);

      // Check geometries in placemark
      const pointNode = placemark.firstChildNamed('Point');
      const polygonNode = placemark.firstChildNamed('Polygon');
      const lineStringNode = placemark.firstChildNamed('LineString');
      const trackNode = placemark.firstChildNamed('Track') || placemark.firstChildNamed('gx:Track');
      const multiGeomNode = placemark.firstChildNamed('MultiGeometry') || placemark.firstChildNamed('gx:MultiTrack');

      // Helper to classify line (pattern vs route)
      function classifyLineType(customName) {
        if (ext['qrsar:sarType'] === 'pattern') return 'pattern';
        if (ext['qrsar:sarType'] === 'route') return 'route';
        const checkStr = `${customName} ${folderName} ${description}`;
        return SAR_KEYWORD_REGEX.test(checkStr) ? 'pattern' : 'route';
      }

      // 1. Point Geometry
      if (pointNode) {
        const coordsText = pointNode.textOf('coordinates');
        const pts = parseCoordinateTuples(coordsText);
        if (pts.length === 0) {
          warnings.push(`Placemark "${name || 'Unnamed'}" Point has invalid coordinates; skipped.`);
          stats.skippedCount++;
          continue;
        }

        const ptCoord = pts[0];
        const safeCustomIcon = isSafeLocalIconPath(ext['qrsar:customIconPath']);

        let pointType = ext['qrsar:pointType'] || 'pin_umum';
        if (safeCustomIcon) {
          pointType = 'custom_upload';
        } else {
          // Validate against known preset icons
          const Markers = (global.QrSarGis && global.QrSarGis.Markers) ? global.QrSarGis.Markers : null;
          const isKnownPreset = (Markers && Array.isArray(Markers.PRESET_ICONS))
            ? Markers.PRESET_ICONS.some(i => i.type === pointType)
            : FALLBACK_PRESET_TYPES.has(pointType);
          if (!isKnownPreset) {
            pointType = 'pin_umum';
          }
        }

        points.push({
          id: ext['qrsar:sourceId'] || `pt-${idSeq++}`,
          sourceId: ext['qrsar:sourceId'] || null,
          label: name || 'Titik SAR',
          description,
          category: ext['qrsar:category'] || folderName || 'Tanpa Kategori',
          pointType,
          customIconUrl: safeCustomIcon || null,
          alphabetChar: ext['qrsar:alphabetChar'] || null,
          lat: ptCoord.lat,
          lng: ptCoord.lng,
          color: style.iconColor || null
        });

        stats.parsedCount++;
        continue;
      }

      // 2. Polygon Geometry
      if (polygonNode) {
        const ringNode = polygonNode.getElementsByTagName('LinearRing')[0];
        const coordsText = ringNode ? ringNode.textOf('coordinates') : polygonNode.textOf('coordinates');
        const rawPoints = parseCoordinateTuples(coordsText);
        const unclosedPoints = stripClosingVertex(rawPoints);

        if (unclosedPoints.length < 3) {
          warnings.push(`Placemark "${name || 'Unnamed'}" Polygon has fewer than 3 valid coordinates; skipped.`);
          stats.skippedCount++;
          continue;
        }

        const centroid = calculateCentroid(unclosedPoints);
        const areaNm2 = calculatePolygonAreaNm2(unclosedPoints);

        let shapeType = ext['qrsar:shapeType'] || 'polygon';
        let centerLat = ext['qrsar:centerLat'] !== undefined ? parseFloat(ext['qrsar:centerLat']) : centroid.lat;
        let centerLng = ext['qrsar:centerLng'] !== undefined ? parseFloat(ext['qrsar:centerLng']) : centroid.lng;
        let radiusNm = ext['qrsar:radiusNm'] !== undefined ? parseFloat(ext['qrsar:radiusNm']) : undefined;
        let boxWidthNm = ext['qrsar:boxWidthNm'] !== undefined ? parseFloat(ext['qrsar:boxWidthNm']) : undefined;
        let boxHeightNm = ext['qrsar:boxHeightNm'] !== undefined ? parseFloat(ext['qrsar:boxHeightNm']) : undefined;
        let finalAreaNm2 = ext['qrsar:areaNm2'] !== undefined ? parseFloat(ext['qrsar:areaNm2']) : areaNm2;

        // Google Earth edit detection on exported circle/rectangle
        if (shapeType === 'circle' || shapeType === 'rectangle' || shapeType === 'box') {
          const latDiff = Math.abs(centroid.lat - centerLat);
          const lngDiff = Math.abs(centroid.lng - centerLng);
          let areaEdited = false;
          if (ext['qrsar:areaNm2'] !== undefined && parseFloat(ext['qrsar:areaNm2']) > 0) {
            const areaDiff = Math.abs(areaNm2 - parseFloat(ext['qrsar:areaNm2']));
            if (areaDiff / parseFloat(ext['qrsar:areaNm2']) > 0.05) {
              areaEdited = true;
            }
          }

          if (latDiff > 0.0005 || lngDiff > 0.0005 || areaEdited) {
            warnings.push(`Polygon "${name || 'Unnamed'}" geometry was modified externally; shapeType downgraded from ${shapeType} to polygon.`);
            shapeType = 'polygon';
            centerLat = centroid.lat;
            centerLng = centroid.lng;
            finalAreaNm2 = areaNm2;
          }
        }

        const strokeColor = ext['qrsar:strokeColor'] || style.strokeColor || '#0284c7';
        const strokeWeight = ext['qrsar:strokeWeight'] !== undefined
          ? parseFloat(ext['qrsar:strokeWeight'])
          : (style.strokeWeight !== null ? style.strokeWeight : 2);
        const strokeOpacity = ext['qrsar:strokeOpacity'] !== undefined
          ? parseFloat(ext['qrsar:strokeOpacity'])
          : (style.strokeOpacity !== null ? style.strokeOpacity : 1);
        const fillColor = ext['qrsar:fillColor'] || style.fillColor || strokeColor;
        const fillOpacity = ext['qrsar:fillOpacity'] !== undefined
          ? parseFloat(ext['qrsar:fillOpacity'])
          : (style.fillOpacity !== null ? style.fillOpacity : 0.25);

        polygons.push({
          id: ext['qrsar:sourceId'] || `poly-${idSeq++}`,
          sourceId: ext['qrsar:sourceId'] || null,
          name: name || 'Sektor Poligon',
          shapeType,
          centerLat,
          centerLng,
          radiusNm,
          boxWidthNm,
          boxHeightNm,
          areaNm2: finalAreaNm2,
          strokeColor,
          strokeWeight,
          strokeOpacity,
          fillColor,
          fillOpacity,
          points: unclosedPoints
        });

        stats.parsedCount++;
        continue;
      }

      // 3. MultiGeometry (E-SAR recovery or child geometry split)
      if (multiGeomNode) {
        // Check if QR-SAR E-SAR Pattern with serialized waypointsJson
        if (ext['qrsar:sarType'] === 'pattern' && ext['qrsar:waypointsJson']) {
          try {
            const parsedWps = JSON.parse(ext['qrsar:waypointsJson']);
            if (Array.isArray(parsedWps) && parsedWps.length >= 2) {
              const validWps = parsedWps.filter(w => isValidCoord(parseFloat(w.lat), parseFloat(w.lng)));
              if (validWps.length >= 2) {
                const strokeColor = ext['qrsar:color'] || style.strokeColor || '#ea580c';
                const strokeWeight = ext['qrsar:strokeWeight'] !== undefined
                  ? parseFloat(ext['qrsar:strokeWeight'])
                  : (style.strokeWeight !== null ? style.strokeWeight : 2);
                const strokeOpacity = ext['qrsar:opacity'] !== undefined
                  ? parseFloat(ext['qrsar:opacity'])
                  : (style.strokeOpacity !== null ? style.strokeOpacity : 1);

                patterns.push({
                  id: ext['qrsar:sourceId'] || `pat-${idSeq++}`,
                  sourceId: ext['qrsar:sourceId'] || null,
                  name: name || 'Pola Pencarian SAR',
                  patternType: ext['qrsar:patternType'] || 'sector_search',
                  polygonRef: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
                  polygonId: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
                  trackSpacingNm: ext['qrsar:trackSpacingNm'] !== undefined ? parseFloat(ext['qrsar:trackSpacingNm']) : undefined,
                  legsCount: ext['qrsar:legsCount'] !== undefined ? parseInt(ext['qrsar:legsCount'], 10) : undefined,
                  totalDistanceNm: ext['qrsar:totalDistanceNm'] !== undefined ? parseFloat(ext['qrsar:totalDistanceNm']) : undefined,
                  flipH: ext['qrsar:flipH'] === 'true' || ext['qrsar:flipH'] === true,
                  flipV: ext['qrsar:flipV'] === 'true' || ext['qrsar:flipV'] === true,
                  color: strokeColor,
                  strokeWeight,
                  opacity: strokeOpacity,
                  waypoints: validWps
                });

                stats.parsedCount++;
                continue;
              }
            }
          } catch (e) {
            // fallback to splitting child geometries
          }
        }

        // Split child geometries into individual items
        const childGeoms = multiGeomNode.children.filter(c => {
          const ln = c.localName.toLowerCase();
          return ln === 'point' || ln === 'polygon' || ln === 'linestring' || ln === 'track';
        });

        if (childGeoms.length === 0) {
          warnings.push(`Placemark "${name || 'Unnamed'}" MultiGeometry has no supported child elements; skipped.`);
          stats.skippedCount++;
          continue;
        }

        const multiCount = childGeoms.length;
        for (let gIdx = 0; gIdx < childGeoms.length; gIdx++) {
          if (stats.parsedCount >= maxFeatures) {
            if (!limitReached) {
              warnings.push(`Maximum feature limit of ${maxFeatures} reached. Remaining features were truncated.`);
              stats.truncated = true;
              limitReached = true;
            }
            stats.skippedCount += (childGeoms.length - gIdx);
            break;
          }

          const geom = childGeoms[gIdx];
          const gName = multiCount > 1 ? `${name || 'Jalur'} (${gIdx + 1})` : (name || 'Jalur');
          const gLocal = geom.localName.toLowerCase();

          if (gLocal === 'linestring' || gLocal === 'track') {
            const pts = (gLocal === 'track')
              ? parseTrackCoords(geom)
              : parseCoordinateTuples(geom.textOf('coordinates'));

            if (pts.length < 2) {
              warnings.push(`MultiGeometry child in "${name}" has fewer than 2 valid coordinates; skipped.`);
              stats.skippedCount++;
              continue;
            }

            const lineType = classifyLineType(gName);
            if (lineType === 'pattern') {
              patterns.push({
                id: `pat-${idSeq++}`,
                name: gName,
                patternType: ext['qrsar:patternType'] || 'custom',
                polygonRef: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
                polygonId: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
                color: ext['qrsar:color'] || style.strokeColor || '#ea580c',
                strokeWeight: style.strokeWeight || 2,
                opacity: style.strokeOpacity || 1,
                waypoints: pts
              });
            } else {
              routes.push({
                id: `rt-${idSeq++}`,
                name: gName,
                description,
                polygonRef: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
                polygonId: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
                vesselSpeedKnots: ext['qrsar:vesselSpeedKnots'] !== undefined ? parseFloat(ext['qrsar:vesselSpeedKnots']) : undefined,
                strokeColor: style.strokeColor || '#0ea5e9',
                strokeWeight: style.strokeWeight || 3,
                strokeOpacity: style.strokeOpacity || 1,
                points: pts
              });
            }
            stats.parsedCount++;
          } else if (gLocal === 'point') {
            const pts = parseCoordinateTuples(geom.textOf('coordinates'));
            if (pts.length > 0) {
              points.push({
                id: `pt-${idSeq++}`,
                label: gName,
                description,
                category: folderName || 'Tanpa Kategori',
                pointType: 'pin_umum',
                customIconUrl: null,
                lat: pts[0].lat,
                lng: pts[0].lng,
                color: style.iconColor || null
              });
              stats.parsedCount++;
            }
          } else if (gLocal === 'polygon') {
            const ringNode = geom.getElementsByTagName('LinearRing')[0];
            const pts = stripClosingVertex(parseCoordinateTuples(ringNode ? ringNode.textOf('coordinates') : geom.textOf('coordinates')));
            if (pts.length >= 3) {
              const centroid = calculateCentroid(pts);
              polygons.push({
                id: `poly-${idSeq++}`,
                name: gName,
                shapeType: 'polygon',
                centerLat: centroid.lat,
                centerLng: centroid.lng,
                areaNm2: calculatePolygonAreaNm2(pts),
                strokeColor: style.strokeColor || '#0284c7',
                strokeWeight: style.strokeWeight || 2,
                strokeOpacity: style.strokeOpacity || 1,
                fillColor: style.fillColor || '#0ea5e9',
                fillOpacity: style.fillOpacity !== null ? style.fillOpacity : 0.25,
                points: pts
              });
              stats.parsedCount++;
            }
          }
        }
        continue;
      }

      // 4. LineString Geometry
      if (lineStringNode) {
        const coordsText = lineStringNode.textOf('coordinates');
        const pts = parseCoordinateTuples(coordsText);
        if (pts.length < 2) {
          warnings.push(`Placemark "${name || 'Unnamed'}" LineString has fewer than 2 valid coordinates; skipped.`);
          stats.skippedCount++;
          continue;
        }

        const lineType = classifyLineType(name);
        if (lineType === 'pattern') {
          const strokeColor = ext['qrsar:color'] || style.strokeColor || '#ea580c';
          const strokeWeight = ext['qrsar:strokeWeight'] !== undefined
            ? parseFloat(ext['qrsar:strokeWeight'])
            : (style.strokeWeight !== null ? style.strokeWeight : 2);
          const strokeOpacity = ext['qrsar:opacity'] !== undefined
            ? parseFloat(ext['qrsar:opacity'])
            : (style.strokeOpacity !== null ? style.strokeOpacity : 1);

          patterns.push({
            id: ext['qrsar:sourceId'] || `pat-${idSeq++}`,
            sourceId: ext['qrsar:sourceId'] || null,
            name: name || 'Pola Lintasan SAR',
            patternType: ext['qrsar:patternType'] || 'custom',
            polygonRef: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            polygonId: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            trackSpacingNm: ext['qrsar:trackSpacingNm'] !== undefined ? parseFloat(ext['qrsar:trackSpacingNm']) : undefined,
            legsCount: ext['qrsar:legsCount'] !== undefined ? parseInt(ext['qrsar:legsCount'], 10) : undefined,
            totalDistanceNm: ext['qrsar:totalDistanceNm'] !== undefined ? parseFloat(ext['qrsar:totalDistanceNm']) : undefined,
            flipH: ext['qrsar:flipH'] === 'true' || ext['qrsar:flipH'] === true,
            flipV: ext['qrsar:flipV'] === 'true' || ext['qrsar:flipV'] === true,
            color: strokeColor,
            strokeWeight,
            opacity: strokeOpacity,
            waypoints: pts
          });
        } else {
          const strokeColor = ext['qrsar:strokeColor'] || style.strokeColor || '#0ea5e9';
          const strokeWeight = ext['qrsar:strokeWeight'] !== undefined
            ? parseFloat(ext['qrsar:strokeWeight'])
            : (style.strokeWeight !== null ? style.strokeWeight : 3);
          const strokeOpacity = ext['qrsar:strokeOpacity'] !== undefined
            ? parseFloat(ext['qrsar:strokeOpacity'])
            : (style.strokeOpacity !== null ? style.strokeOpacity : 1);

          routes.push({
            id: ext['qrsar:sourceId'] || `rt-${idSeq++}`,
            sourceId: ext['qrsar:sourceId'] || null,
            name: name || 'Rute Navigasi',
            description,
            polygonRef: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            polygonId: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            vesselSpeedKnots: ext['qrsar:vesselSpeedKnots'] !== undefined ? parseFloat(ext['qrsar:vesselSpeedKnots']) : undefined,
            strokeColor,
            strokeWeight,
            strokeOpacity,
            points: pts
          });
        }

        stats.parsedCount++;
        continue;
      }

      // 5. Track Geometry (<gx:Track>)
      if (trackNode) {
        const pts = parseTrackCoords(trackNode);
        if (pts.length < 2) {
          warnings.push(`Placemark "${name || 'Unnamed'}" Track has fewer than 2 valid coordinates; skipped.`);
          stats.skippedCount++;
          continue;
        }

        const lineType = classifyLineType(name);
        if (lineType === 'pattern') {
          patterns.push({
            id: ext['qrsar:sourceId'] || `pat-${idSeq++}`,
            sourceId: ext['qrsar:sourceId'] || null,
            name: name || 'Track Lintasan SAR',
            patternType: ext['qrsar:patternType'] || 'custom',
            polygonRef: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            polygonId: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            color: ext['qrsar:color'] || style.strokeColor || '#ea580c',
            strokeWeight: style.strokeWeight || 2,
            opacity: style.strokeOpacity || 1,
            waypoints: pts
          });
        } else {
          routes.push({
            id: ext['qrsar:sourceId'] || `rt-${idSeq++}`,
            sourceId: ext['qrsar:sourceId'] || null,
            name: name || 'Track Navigasi',
            description,
            polygonRef: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            polygonId: ext['qrsar:polygonRef'] || ext['qrsar:polygonName'] || null,
            vesselSpeedKnots: ext['qrsar:vesselSpeedKnots'] !== undefined ? parseFloat(ext['qrsar:vesselSpeedKnots']) : undefined,
            strokeColor: style.strokeColor || '#0ea5e9',
            strokeWeight: style.strokeWeight || 3,
            strokeOpacity: style.strokeOpacity || 1,
            points: pts
          });
        }

        stats.parsedCount++;
        continue;
      }

      // Unrecognized geometry
      warnings.push(`Placemark "${name || 'Unnamed'}" has no recognized geometry; skipped.`);
      stats.skippedCount++;
    }

    return {
      points,
      polygons,
      patterns,
      routes,
      warnings,
      stats
    };
  }

  /**
   * Safe byte length calculation in browser and Node environments
   */
  function getStringByteLength(str) {
    if (typeof Buffer !== 'undefined') {
      return Buffer.byteLength(str, 'utf8');
    }
    if (typeof TextEncoder !== 'undefined') {
      return new TextEncoder().encode(str).length;
    }
    return str.length;
  }

  /**
   * Parse File, Blob, or mock file object (.kml or .kmz)
   * @param {File|Blob|{ name: string, size?: number, text?: Function, arrayBuffer?: Function }} file
   * @param {Object} [options]
   * @returns {Promise<Object>}
   */
  async function parseFile(file, options) {
    if (!file) {
      throw new Error('No file provided to parseFile');
    }

    // 1. File size check <= MAX_FILE_BYTES
    if (file.size !== undefined && typeof file.size === 'number') {
      if (file.size > MAX_FILE_BYTES) {
        throw new Error(`File size (${file.size} bytes) exceeds maximum limit of 10MB (${MAX_FILE_BYTES} bytes).`);
      }
    }

    const fileName = file.name || '';
    const isKmz = /\.kmz$/i.test(fileName);

    if (isKmz) {
      let jszipInst = null;
      try {
        if (typeof JSZip !== 'undefined' && JSZip) {
          jszipInst = JSZip;
        }
      } catch (e) {
        // ignore
      }
      if (!jszipInst && typeof window !== 'undefined' && window.JSZip) {
        jszipInst = window.JSZip;
      }
      if (!jszipInst && typeof globalThis !== 'undefined' && globalThis.JSZip) {
        jszipInst = globalThis.JSZip;
      }
      if (!jszipInst && typeof global !== 'undefined' && global.JSZip) {
        jszipInst = global.JSZip;
      }

      if (!jszipInst || typeof jszipInst.loadAsync !== 'function') {
        throw new Error('JSZip library is required to parse .kmz files.');
      }

      let arrayBuffer = null;
      if (typeof file.arrayBuffer === 'function') {
        arrayBuffer = await file.arrayBuffer();
      } else if (typeof file.text === 'function') {
        const text = await file.text();
        arrayBuffer = (typeof Buffer !== 'undefined') ? Buffer.from(text) : new TextEncoder().encode(text).buffer;
      } else {
        throw new Error('Unsupported file object: unable to read arrayBuffer');
      }

      const zip = await jszipInst.loadAsync(arrayBuffer);
      let kmlEntry = zip.file('doc.kml') || zip.file('Doc.kml') || zip.file('DOC.KML');
      if (!kmlEntry) {
        const files = zip.files || {};
        for (const k of Object.keys(files)) {
          if (!files[k].dir && /\.kml$/i.test(files[k].name)) {
            kmlEntry = files[k];
            break;
          }
        }
      }

      if (!kmlEntry) {
        throw new Error('No .kml entry found inside KMZ archive.');
      }

      const kmlString = await kmlEntry.async('text');
      const decompressedBytes = getStringByteLength(kmlString);

      if (decompressedBytes > MAX_KML_BYTES) {
        throw new Error(`Decompressed KML size (${decompressedBytes} bytes) exceeds maximum limit of 15MB (${MAX_KML_BYTES} bytes).`);
      }

      return parseKmlString(kmlString, options);
    }

    // Normal .kml file
    let kmlString = '';
    if (typeof file.text === 'function') {
      kmlString = await file.text();
    } else if (typeof file.arrayBuffer === 'function') {
      const buf = await file.arrayBuffer();
      kmlString = new TextDecoder('utf-8').decode(buf);
    } else {
      throw new Error('Unsupported file object: unable to read text');
    }

    const kmlBytes = getStringByteLength(kmlString);
    if (kmlBytes > MAX_KML_BYTES) {
      throw new Error(`KML file size (${kmlBytes} bytes) exceeds maximum limit of 15MB (${MAX_KML_BYTES} bytes).`);
    }

    return parseKmlString(kmlString, options);
  }

  // Export to namespace
  global.QrSarGis.KmlImporter = {
    MAX_FILE_BYTES,
    MAX_KML_BYTES,
    MAX_FEATURES,
    kmlAbgrToCss,
    stripHtmlDescription,
    isSafeLocalIconPath,
    parseXml,
    parseKmlString,
    parseFile
  };

})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this));
