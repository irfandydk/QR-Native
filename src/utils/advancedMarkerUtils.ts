import { PointIconType, LatLngPoint } from './geoUtils';

export interface MarkerOptions {
  position: { lat: number; lng: number } | google.maps.LatLngLiteral;
  title?: string;
  draggable?: boolean;
  contentHtml?: string;
  contentElement?: HTMLElement;
  zIndex?: number;
  onClick?: () => void;
  onDrag?: (lat: number, lng: number) => void;
  onDragEnd?: (lat: number, lng: number) => void;
}

/**
 * Generate SVG HTML string for Point Icons with first-class custom icon support
 */
export function createPointSvgHtml(
  type: PointIconType = 'waypoint',
  labelNumber: string = '1',
  color: string = '#ea580c',
  isEditing: boolean = false,
  customIconUrl?: string,
  uniqueMarkerId?: string,
  alphabetChar?: string
): string {
  const width = isEditing ? 46 : 38;
  const height = isEditing ? 54 : 46;
  const cx = width / 2;
  const clipId = `customClip_${uniqueMarkerId || Math.random().toString(36).substring(2, 9)}`;

  let innerContent = '';

  if (customIconUrl) {
    // Custom icon (uploaded PNG/SVG/JPG/server URL) with robust background fallback
    innerContent = `
      <defs>
        <clipPath id="${clipId}">
          <circle cx="${cx}" cy="16" r="11" />
        </clipPath>
      </defs>
      <circle cx="${cx}" cy="16" r="12" fill="#ffffff" stroke="${color}" stroke-width="1.5" />
      <!-- Robust fallback SAR glyph in case custom image asset fails or is offline -->
      <circle cx="${cx}" cy="16" r="6" fill="${color}" opacity="0.85" />
      <circle cx="${cx}" cy="16" r="2.5" fill="#ffffff" />
      <!-- Custom Asset Image from Server -->
      <image href="${customIconUrl}" x="${cx - 11}" y="5" width="22" height="22" preserveAspectRatio="xMidYMid slice" clip-path="url(#${clipId})" />
    `;
  } else {
    switch (type) {
      case 'alphabet':
        const char = (alphabetChar || labelNumber || 'A').toUpperCase();
        innerContent = `
          <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
          <text x="${cx}" y="20.5" font-family="Arial, system-ui, sans-serif" font-size="12" font-weight="900" fill="${color}" text-anchor="middle">
            ${char}
          </text>
        `;
        break;
      case 'lkp':
        // LKP (Last Known Position) with emergency bullseye & pulsing ring
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <circle cx="${cx}" cy="16" r="9" fill="${color}" />
          <circle cx="${cx}" cy="16" r="4" fill="#ffffff" />
          <line x1="${cx - 10}" y1="16" x2="${cx + 10}" y2="16" stroke="#ffffff" stroke-width="1.8" />
          <line x1="${cx}" y1="6" x2="${cx}" y2="26" stroke="#ffffff" stroke-width="1.8" />
        `;
        break;
      case 'kantor':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 6} 22 V14 L${cx} 9 L${cx + 6} 14 V22 H${cx - 6} Z" fill="${color}" />
          <rect x="${cx - 2}" y="17" width="4" height="5" fill="#ffffff" />
        `;
        break;
      case 'pos_sar':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 4} 22 L${cx} 11 L${cx + 4} 22 Z" fill="${color}" />
          <circle cx="${cx}" cy="11" r="2" fill="${color}" />
          <path d="M${cx - 6} 11 A6 6 0 0 1 ${cx + 6} 11" fill="none" stroke="${color}" stroke-width="1.5" stroke-linecap="round" />
        `;
        break;
      case 'posko_sar':
        // Tactical Command Post (Pos Komando / Tenda Operasi SAR Taktis)
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 7} 22 L${cx} 12 L${cx + 7} 22 Z" fill="${color}" />
          <line x1="${cx}" y1="7" x2="${cx}" y2="12" stroke="${color}" stroke-width="1.5" stroke-linecap="round" />
          <polygon points="${cx},7 ${cx + 4.5},8.5 ${cx},10" fill="#dc2626" />
          <polygon points="${cx - 2.5},22 ${cx},17.5 ${cx + 2.5},22" fill="#ffffff" />
          <circle cx="${cx}" cy="16" r="1" fill="${color}" />
        `;
        break;
      case 'rumah':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 6} 16 L${cx} 10 L${cx + 6} 16 V22 H${cx - 6} Z" fill="${color}" />
          <rect x="${cx - 2}" y="18" width="4" height="4" fill="#ffffff" />
        `;
        break;
      case 'kapal':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 8} 17 L${cx - 5} 21 H${cx + 5} L${cx + 8} 17 Z" fill="${color}" />
          <rect x="${cx - 4}" y="12" width="8" height="5" fill="${color}" />
          <line x1="${cx}" y1="8" x2="${cx}" y2="12" stroke="${color}" stroke-width="1.5" />
        `;
        break;
      case 'perahu':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 7} 17 Q${cx} 22 ${cx + 7} 17 L${cx + 5} 20 H${cx - 5} Z" fill="${color}" />
          <line x1="${cx - 1}" y1="10" x2="${cx - 1}" y2="17" stroke="${color}" stroke-width="1.5" />
          <polygon points="${cx - 1},10 ${cx + 4},13 ${cx - 1},16" fill="${color}" />
        `;
        break;
      case 'rib':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 6} 15 C${cx - 6} 13 ${cx + 6} 13 ${cx + 6} 15 L${cx + 5} 20 C${cx + 5} 22 ${cx - 5} 22 ${cx - 5} 20 Z" fill="none" stroke="${color}" stroke-width="2.5" />
          <rect x="${cx - 2}" y="15" width="4" height="4" fill="${color}" />
        `;
        break;
      case 'orang_hilang':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <circle cx="${cx}" cy="12" r="3" fill="${color}" />
          <path d="M${cx - 5} 22 C${cx - 5} 17, ${cx + 5} 17, ${cx + 5} 22 Z" fill="${color}" />
          <circle cx="${cx}" cy="17" r="4.5" fill="none" stroke="#ef4444" stroke-width="1.5" />
        `;
        break;
      case 'kecelakaan_kapal':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 7} 14 L${cx + 2} 21 H${cx + 7} L${cx + 1} 13 Z" fill="${color}" />
          <path d="M${cx - 8} 21 Q${cx - 4} 19 ${cx} 21 T${cx + 8} 21" stroke="#38bdf8" stroke-width="1.5" fill="none" />
        `;
        break;
      case 'kecelakaan_pesawat':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <path d="M${cx - 6} 13 L${cx + 6} 19 M${cx - 2} 11 L${cx + 2} 21 M${cx - 5} 17 L${cx - 1} 19" stroke="${color}" stroke-width="2" stroke-linecap="round" />
          <circle cx="${cx + 4}" cy="18" r="2" fill="#ef4444" />
        `;
        break;
      case 'bencana':
        innerContent = `
          <circle cx="${cx}" cy="16" r="11" fill="#ffffff" />
          <polygon points="${cx},9 ${cx + 7},22 ${cx - 7},22" fill="${color}" />
          <line x1="${cx}" y1="13" x2="${cx}" y2="17" stroke="#ffffff" stroke-width="1.5" />
          <circle cx="${cx}" cy="19.5" r="0.8" fill="#ffffff" />
        `;
        break;
      case 'waypoint':
      default:
        innerContent = `
          <circle cx="${cx}" cy="16" r="10" fill="#ffffff" />
          <text x="${cx}" y="20" font-family="Arial, sans-serif" font-size="11" font-weight="900" fill="${color}" text-anchor="middle">
            ${labelNumber}
          </text>
        `;
        break;
    }
  }

  const haloRing = isEditing
    ? `<circle cx="${cx}" cy="16" r="17" fill="none" stroke="#facc15" stroke-width="3" stroke-dasharray="3,3" />`
    : type === 'lkp'
    ? `<circle cx="${cx}" cy="16" r="18" fill="none" stroke="${color}" stroke-width="1.5" stroke-opacity="0.6">
        <animate attributeName="r" values="14;22;14" dur="2.5s" repeatCount="indefinite" />
        <animate attributeName="stroke-opacity" values="0.8;0.1;0.8" dur="2.5s" repeatCount="indefinite" />
       </circle>`
    : '';

  return `
    <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" style="display:block;cursor:${isEditing ? 'grab' : 'pointer'};">
      <defs>
        <filter id="shadow_${clipId}" x="-20%" y="-20%" width="140%" height="140%">
          <feDropShadow dx="0" dy="2" stdDeviation="2" flood-color="#000000" flood-opacity="0.4" />
        </filter>
      </defs>
      <g filter="url(#shadow_${clipId})">
        ${haloRing}
        <path d="M${cx} ${height - 4} C${cx - 2} ${height - 10}, ${cx - 14} 24, ${cx - 14} 16 A14 14 0 1 1 ${cx + 14} 16 C${cx + 14} 24, ${cx + 2} ${height - 10}, ${cx} ${height - 4} Z" fill="${color}" stroke="#ffffff" stroke-width="2" />
        ${innerContent}
      </g>
    </svg>
  `;
}

const POINT_TYPE_DEFAULT_COLORS: Record<string, string> = {
  lkp: '#dc2626',
  kantor: '#1e3a8a',
  pos_sar: '#ea580c',
  rumah: '#16a34a',
  kapal: '#0284c7',
  perahu: '#06b6d4',
  rib: '#f97316',
  orang_hilang: '#ef4444',
  kecelakaan_kapal: '#b91c1c',
  kecelakaan_pesawat: '#7c3aed',
  bencana: '#d97706',
  alphabet: '#0284c7',
  waypoint: '#ea580c',
};

/**
 * Generate full marker HTML with point name badge positioned visibly above the pin tip
 */
export function createPointMarkerHtml(
  point: LatLngPoint,
  isEditing: boolean = false,
  colorOverride?: string
): string {
  const defaultColor = POINT_TYPE_DEFAULT_COLORS[point.pointType || ''] || '#ea580c';
  const color = colorOverride || point.customColor || defaultColor;
  const svg = createPointSvgHtml(
    point.pointType || 'waypoint',
    point.alphabetChar || '1',
    color,
    isEditing,
    point.customIconUrl,
    point.id,
    point.alphabetChar
  );

  const labelText = (point.label || '').trim();

  return `
    <div style="display:flex; flex-direction:column; align-items:center; user-select:none; pointer-events:auto; transform: translateZ(0);">
      ${
        labelText
          ? `<div style="
              background: rgba(15, 23, 42, 0.94);
              color: #f8fafc;
              font-size: 11px;
              font-weight: 800;
              font-family: system-ui, -apple-system, sans-serif;
              padding: 2.5px 8px;
              border-radius: 6px;
              border: 1px solid rgba(255, 255, 255, 0.4);
              box-shadow: 0 2px 6px rgba(0,0,0,0.55);
              white-space: nowrap;
              margin-bottom: 2px;
              pointer-events: none;
              max-width: 170px;
              overflow: hidden;
              text-overflow: ellipsis;
              letter-spacing: 0.25px;
            ">${labelText}</div>`
          : ''
      }
      ${svg}
    </div>
  `;
}

/**
 * Generate SVG HTML for Search Pattern handles.
 * Corners are unnumbered control grab points.
 * Start (Entry) is marked with 'S', End (Exit) with 'E'.
 */
export function createPatternCornerSvgHtml(
  type: 'start' | 'end' | 'corner',
  color: string = '#0284c7',
  isDragging: boolean = false
): string {
  const size = isDragging ? 26 : 22;
  const radius = size / 2;
  const clipId = `pat_${Math.random().toString(36).substring(2, 7)}`;
  const halo = isDragging
    ? `<circle cx="${radius}" cy="${radius}" r="${radius - 1}" fill="none" stroke="#facc15" stroke-width="3" stroke-dasharray="3,3" />`
    : '';

  if (type === 'start') {
    return `
      <div style="transform: translate(-50%, -50%); display: inline-block;">
        <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="display:block;cursor:grab;">
          ${halo}
          <circle cx="${radius}" cy="${radius}" r="${radius - 2.5}" fill="#16a34a" stroke="#ffffff" stroke-width="2" />
          <text x="${radius}" y="${radius + 4}" font-family="Arial, sans-serif" font-size="11" font-weight="900" fill="#ffffff" text-anchor="middle">S</text>
        </svg>
      </div>
    `;
  }

  if (type === 'end') {
    return `
      <div style="transform: translate(-50%, -50%); display: inline-block;">
        <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="display:block;cursor:grab;">
          ${halo}
          <circle cx="${radius}" cy="${radius}" r="${radius - 2.5}" fill="#dc2626" stroke="#ffffff" stroke-width="2" />
          <text x="${radius}" y="${radius + 4}" font-family="Arial, sans-serif" font-size="11" font-weight="900" fill="#ffffff" text-anchor="middle">E</text>
        </svg>
      </div>
    `;
  }

  // Intermediate corner: clean handle dot without numbers
  return `
    <div style="transform: translate(-50%, -50%); display: inline-block;">
      <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="display:block;cursor:grab;">
        <defs>
          <filter id="${clipId}" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="1" stdDeviation="1" flood-color="#000000" flood-opacity="0.4" />
          </filter>
        </defs>
        <g filter="url(#${clipId})">
          ${halo}
          <circle cx="${radius}" cy="${radius}" r="${radius - 2.5}" fill="${color}" stroke="#ffffff" stroke-width="2" />
          <circle cx="${radius}" cy="${radius}" r="${radius - 5}" fill="#ffffff" opacity="0.85" />
        </g>
      </svg>
    </div>
  `;
}

/**
 * Generate SVG HTML string for Polygon Vertex (Titik A, B, C...) & Waypoints
 */
export function createPolygonVertexSvgHtml(
  letter: string,
  color: string = '#ea580c',
  isDragging: boolean = false
): string {
  const size = isDragging ? 38 : 32;
  const radius = size / 2;
  const clipId = `vtx_${letter}_${Math.random().toString(36).substring(2, 7)}`;
  const halo = isDragging
    ? `<circle cx="${radius}" cy="${radius}" r="${radius - 2}" fill="none" stroke="#facc15" stroke-width="3" stroke-dasharray="3,3" />`
    : '';

  return `
    <div style="transform: translateY(50%); display: inline-block;">
      <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" style="display:block;cursor:grab;">
        <defs>
          <filter id="${clipId}" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="1.5" stdDeviation="1.5" flood-color="#000000" flood-opacity="0.45" />
          </filter>
        </defs>
        <g filter="url(#${clipId})">
          ${halo}
          <circle cx="${radius}" cy="${radius}" r="${radius - 4}" fill="${color}" stroke="#ffffff" stroke-width="2.5" />
          <text x="${radius}" y="${radius + 4}" font-family="Arial, sans-serif" font-size="12" font-weight="900" fill="#ffffff" text-anchor="middle">
            ${letter}
          </text>
        </g>
      </svg>
    </div>
  `;
}

/**
 * Create modern Google Maps marker using google.maps.marker.AdvancedMarkerElement
 * strictly utilizing the latest @googlemaps/js-api-loader implementation.
 * Includes bulletproof dual-layer drag handling (GMP events + Pointer fallback)
 * to ensure markers can always be dragged smoothly on the map.
 */
export function createAdvancedMapMarker(
  map: google.maps.Map,
  options: MarkerOptions
): google.maps.marker.AdvancedMarkerElement | null {
  const googleMaps = (window as any).google?.maps;
  if (!googleMaps) return null;

  const AdvancedMarker = googleMaps.marker?.AdvancedMarkerElement;
  if (!AdvancedMarker) {
    console.error('google.maps.marker.AdvancedMarkerElement is not available.');
    return null;
  }

  // Create content DOM container with hardware acceleration styling
  const contentContainer = document.createElement('div');
  contentContainer.className = 'advanced-marker-wrapper';
  contentContainer.style.cursor = options.draggable ? 'grab' : 'pointer';
  contentContainer.style.userSelect = 'none';
  contentContainer.style.touchAction = 'none';
  contentContainer.style.willChange = 'transform';
  contentContainer.style.transition = 'transform 0.1s ease-out';
  contentContainer.setAttribute('draggable', 'false');

  // Prevent browser native drag ghost on SVG and image tags
  contentContainer.addEventListener('dragstart', (e) => e.preventDefault());

  if (options.contentHtml) {
    contentContainer.innerHTML = options.contentHtml;
  } else if (options.contentElement) {
    contentContainer.appendChild(options.contentElement);
  }

  const marker = new AdvancedMarker({
    map,
    position: options.position,
    title: options.title || '',
    gmpDraggable: !!options.draggable,
    content: contentContainer,
    zIndex: options.zIndex ?? (options.draggable ? 999 : undefined),
  });

  // Click listeners (standard DOM and GMP events)
  if (options.onClick) {
    const handleClick = (e?: any) => {
      e?.stopPropagation?.();
      options.onClick!();
    };
    marker.addListener('click', handleClick);
    marker.addEventListener('gmp-click', handleClick);
    contentContainer.addEventListener('click', handleClick);
  }

  // Drag listeners
  if (options.draggable && (options.onDrag || options.onDragEnd)) {
    let isDragging = false;
    let startX = 0;
    let startY = 0;

    const extractCoordinates = (e?: any): { lat: number; lng: number } | null => {
      if (e?.latLng) {
        const lat = typeof e.latLng.lat === 'function' ? e.latLng.lat() : e.latLng.lat;
        const lng = typeof e.latLng.lng === 'function' ? e.latLng.lng() : e.latLng.lng;
        if (typeof lat === 'number' && typeof lng === 'number') return { lat, lng };
      }
      const pos = marker.position;
      if (pos) {
        const lat = typeof (pos as any).lat === 'function' ? (pos as any).lat() : (pos as any).lat;
        const lng = typeof (pos as any).lng === 'function' ? (pos as any).lng() : (pos as any).lng;
        if (typeof lat === 'number' && typeof lng === 'number') return { lat, lng };
      }
      return null;
    };

    // 1. Google Maps AdvancedMarkerElement native drag events
    const handleNativeDrag = (e: any) => {
      contentContainer.style.cursor = 'grabbing';
      contentContainer.style.transform = 'scale(1.15)';
      const coords = extractCoordinates(e);
      if (coords) {
        options.onDrag?.(coords.lat, coords.lng);
      }
    };

    const handleNativeDragEnd = (e: any) => {
      contentContainer.style.cursor = 'grab';
      contentContainer.style.transform = 'none';
      const coords = extractCoordinates(e);
      if (coords) {
        options.onDragEnd?.(coords.lat, coords.lng);
      }
    };

    marker.addEventListener('gmp-drag', handleNativeDrag);
    marker.addEventListener('drag', handleNativeDrag);
    marker.addListener('drag', handleNativeDrag);

    marker.addEventListener('gmp-dragend', handleNativeDragEnd);
    marker.addEventListener('dragend', handleNativeDragEnd);
    marker.addListener('dragend', handleNativeDragEnd);

    // 2. Direct Pointer / Mouse Drag Fallback
    // Guarantees dragging works smoothly in all browsers and iFrames
    let dragThresholdPassed = false;

    const cleanupDrag = () => {
      if (isDragging) {
        isDragging = false;
        contentContainer.style.cursor = 'grab';
        contentContainer.style.transform = 'none';
        map.setOptions({ gestureHandling: 'greedy' });
      }
      window.removeEventListener('pointermove', onPointerMove, true);
      window.removeEventListener('pointerup', onPointerUp, true);
      window.removeEventListener('pointercancel', cleanupDrag, true);
      window.removeEventListener('blur', cleanupDrag, true);
    };

    const onPointerMove = (moveEvent: PointerEvent) => {
      const dist = Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY);
      if (!dragThresholdPassed && dist > 4) {
        dragThresholdPassed = true;
        isDragging = true;
        contentContainer.style.cursor = 'grabbing';
        contentContainer.style.transform = 'scale(1.15)';
        // Only disable map gesture while actively dragging marker
        map.setOptions({ gestureHandling: 'none' });
      }

      if (!isDragging) return;
      moveEvent.preventDefault();
      moveEvent.stopPropagation();

      const mapDiv = map.getDiv();
      const rect = mapDiv.getBoundingClientRect();
      const x = moveEvent.clientX - rect.left;
      const y = moveEvent.clientY - rect.top;

      // Convert container pixel to LatLng
      const bounds = map.getBounds();
      const projection = map.getProjection();

      if (bounds && projection) {
        const ne = bounds.getNorthEast();
        const sw = bounds.getSouthWest();
        const topPoint = projection.fromLatLngToPoint(new googleMaps.LatLng(ne.lat(), sw.lng()));
        const bottomPoint = projection.fromLatLngToPoint(new googleMaps.LatLng(sw.lat(), ne.lng()));

        if (topPoint && bottomPoint) {
          const worldX = topPoint.x + (x / rect.width) * (bottomPoint.x - topPoint.x);
          const worldY = topPoint.y + (y / rect.height) * (bottomPoint.y - topPoint.y);
          const latLng = projection.fromPointToLatLng(new googleMaps.Point(worldX, worldY));

          if (latLng) {
            lastLat = latLng.lat();
            lastLng = latLng.lng();
            marker.position = { lat: lastLat, lng: lastLng };
            options.onDrag?.(lastLat, lastLng);
          }
        }
      }
    };

    const onPointerUp = (upEvent: PointerEvent) => {
      const hadMoved = dragThresholdPassed;
      cleanupDrag();

      if (hadMoved && lastLat !== 0 && lastLng !== 0) {
        options.onDragEnd?.(lastLat, lastLng);
      }
    };

    let lastLat = 0;
    let lastLng = 0;

    const onPointerDown = (downEvent: PointerEvent) => {
      if (downEvent.button !== 0 && downEvent.pointerType === 'mouse') return;
      downEvent.stopPropagation();

      dragThresholdPassed = false;
      isDragging = false;
      startX = downEvent.clientX;
      startY = downEvent.clientY;
      lastLat = 0;
      lastLng = 0;

      window.addEventListener('pointermove', onPointerMove, { capture: true, passive: false });
      window.addEventListener('pointerup', onPointerUp, { capture: true });
      window.addEventListener('pointercancel', cleanupDrag, { capture: true });
      window.addEventListener('blur', cleanupDrag, { capture: true });
    };

    contentContainer.addEventListener('pointerdown', onPointerDown);
  }

  return marker;
}

/**
 * Remove AdvancedMarkerElement safely by nullifying .map reference
 */
export function removeAdvancedMarker(marker: google.maps.marker.AdvancedMarkerElement | null | undefined): void {
  if (marker) {
    marker.map = null;
  }
}

/**
 * Clear a list of AdvancedMarkerElements cleanly
 */
export function clearAdvancedMarkers(markers: (google.maps.marker.AdvancedMarkerElement | null | undefined)[]): void {
  markers.forEach((m) => {
    if (m) m.map = null;
  });
}

