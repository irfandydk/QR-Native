import html2canvas from 'html2canvas';
import { LatLngPoint, SavedPolygon, SavedDistanceRoute, SavedSearchPattern } from './geoUtils';

/**
 * Generate a pristine, high-resolution Google Static Map URL with all SAR plotting overlays
 */
export function buildGoogleStaticMapUrl(params: {
  center: { lat: number; lng: number };
  zoom: number;
  mapType?: string;
  width?: number;
  height?: number;
  scale?: number;
  apiKey?: string;
  points?: LatLngPoint[];
  polygons?: SavedPolygon[];
  routes?: SavedDistanceRoute[];
  patterns?: SavedSearchPattern[];
}): string {
  const {
    center,
    zoom = 12,
    mapType = 'hybrid',
    width = 800,
    height = 480,
    scale = 2,
    apiKey = 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc',
    points = [],
    polygons = [],
    routes = [],
    patterns = [],
  } = params;

  let url = `https://maps.googleapis.com/maps/api/staticmap?center=${center.lat},${center.lng}&zoom=${zoom}&size=${width}x${height}&scale=${scale}&maptype=${mapType}&key=${apiKey}`;

  // 1. Add Polygon Paths (encodes polygon boundary & fill)
  polygons.forEach((poly) => {
    if (poly.points.length >= 3) {
      const colorHex = (poly.strokeColor || '#ea580c').replace('#', '0x');
      const fillHex = (poly.fillColor || '#ea580c').replace('#', '0x');
      const fillAlpha = Math.round((poly.fillOpacity ?? 0.2) * 255).toString(16).padStart(2, '0');
      const strokeWeight = Math.round(poly.strokeWeight || 2);

      const pathCoords = [...poly.points, poly.points[0]]
        .map((pt) => `${pt.lat},${pt.lng}`)
        .join('|');

      url += `&path=color:${colorHex}|weight:${strokeWeight}|fillcolor:${fillHex}${fillAlpha}|${pathCoords}`;
    }
  });

  // 2. Add Distance Routes (encodes lines)
  routes.forEach((rt) => {
    if (rt.points.length >= 2) {
      const colorHex = (rt.strokeColor || '#059669').replace('#', '0x');
      const strokeWeight = Math.round(rt.strokeWeight || 3);
      const pathCoords = rt.points.map((pt) => `${pt.lat},${pt.lng}`).join('|');
      url += `&path=color:${colorHex}|weight:${strokeWeight}|${pathCoords}`;
    }
  });

  // 3. Add Search Patterns
  patterns.forEach((pat) => {
    if (pat.waypoints.length >= 2) {
      const colorHex = (pat.strokeColor || '#0284c7').replace('#', '0x');
      const pathCoords = pat.waypoints.map((pt) => `${pt.lat},${pt.lng}`).join('|');
      url += `&path=color:${colorHex}|weight:2|${pathCoords}`;
    }
  });

  // 4. Add Point Markers
  points.slice(0, 15).forEach((pt, idx) => {
    let color = 'orange';
    let label = (pt.alphabetChar || String(idx + 1)).charAt(0).toUpperCase();

    if (pt.category === 'Kantor SAR' || pt.pointType === 'kantor') {
      color = 'blue';
      label = 'K';
    } else if (pt.category === 'LKP' || pt.pointType === 'lkp') {
      color = 'red';
      label = 'L';
    } else if (pt.category === 'POS SAR' || pt.pointType === 'pos_sar') {
      color = 'orange';
      label = 'P';
    } else if (pt.category === 'Posko' || pt.pointType === 'posko_sar') {
      color = 'green';
      label = 'T';
    }

    url += `&markers=color:${color}|label:${label}|${pt.lat},${pt.lng}`;
  });

  return url;
}

/**
 * Capture map with guaranteed non-blank image result
 */
export async function captureMapSnapshot(params: {
  mapElement: HTMLElement | null;
  mapInstance: google.maps.Map | null;
  points?: LatLngPoint[];
  polygons?: SavedPolygon[];
  routes?: SavedDistanceRoute[];
  patterns?: SavedSearchPattern[];
  basemapType?: string;
  apiKey?: string;
}): Promise<string> {
  const {
    mapElement,
    mapInstance,
    points = [],
    polygons = [],
    routes = [],
    patterns = [],
    basemapType = 'hybrid',
    apiKey = 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc',
  } = params;

  const center = mapInstance?.getCenter()
    ? { lat: mapInstance.getCenter()!.lat(), lng: mapInstance.getCenter()!.lng() }
    : { lat: -4.037694, lng: 122.486694 };
  const zoom = mapInstance?.getZoom() ?? 11;

  const staticFallbackUrl = buildGoogleStaticMapUrl({
    center,
    zoom,
    mapType: basemapType,
    points,
    polygons,
    routes,
    patterns,
    apiKey,
  });

  if (!mapElement) {
    return staticFallbackUrl;
  }

  try {
    // Wait for markers and vector layers to settle
    await new Promise((resolve) => setTimeout(resolve, 200));

    const canvas = await html2canvas(mapElement, {
      useCORS: true,
      allowTaint: true,
      scale: 2,
      logging: false,
      backgroundColor: '#0f172a',
      foreignObjectRendering: false,
      imageTimeout: 10000,
    });

    // Check if the canvas returned solid black / blank
    const ctx = canvas.getContext('2d');
    if (ctx) {
      const w = canvas.width;
      const h = canvas.height;
      const sample = ctx.getImageData(Math.floor(w / 2), Math.floor(h / 2), 1, 1).data;
      
      // If pure black without opacity or empty
      if (sample[0] <= 15 && sample[1] <= 15 && sample[2] <= 15 && sample[3] >= 250) {
        // Double check a secondary point
        const sample2 = ctx.getImageData(Math.floor(w / 4), Math.floor(h / 4), 1, 1).data;
        if (sample2[0] <= 15 && sample2[1] <= 15 && sample2[2] <= 15) {
          // WebGL tile reading was blocked by browser security, use Google Static Map
          return staticFallbackUrl;
        }
      }
    }

    const dataUrl = canvas.toDataURL('image/png');
    if (dataUrl && dataUrl.length > 200) {
      return dataUrl;
    }
  } catch (err) {
    console.warn('html2canvas capture failed, using Static Map fallback:', err);
  }

  return staticFallbackUrl;
}
