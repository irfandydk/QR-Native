import React, { useState, useEffect, useRef, useMemo } from 'react';
import {
  Map as MapIcon,
  Compass,
  Ruler,
  Maximize2,
  Trash2,
  Copy,
  Download,
  MapPin,
  Clock,
  Printer,
  Anchor,
  Wind,
  Sparkles,
  RefreshCw,
  Sliders,
  Move,
  Check,
  Edit3,
  Crosshair,
  Building2,
  Radio,
  Ship,
  Plus,
  X,
  Layers,
  Search,
  Palette,
  Save,
  FileText,
  Bookmark,
  Lock,
  Unlock,
  Eye,
  EyeOff,
} from 'lucide-react';
import { useTheme } from '../context/ThemeContext';
import {
  decimalToDms,
  getAlphabetLabel,
  calculatePolylineDistance,
  calculatePolygonArea,
  generateSearchPatternWaypoints,
  LatLngPoint,
  PointIconType,
  SearchPatternType,
  DEFAULT_SAR_UNITS,
  formatDurationIndonesian,
  SavedDistanceRoute,
} from '../utils/geoUtils';
import { loadGoogleMaps } from '../utils/googleMapsLoader';
import {
  createPointSvgHtml,
  createPointMarkerHtml,
  createPolygonVertexSvgHtml,
  createPatternCornerSvgHtml,
  createAdvancedMapMarker,
  clearAdvancedMarkers,
  removeAdvancedMarker,
} from '../utils/advancedMarkerUtils';
import { DmsNumericEditor } from './gis/DmsNumericEditor';
import { PointCreatorCard, PRESET_POINT_ICONS } from './gis/PointCreatorCard';
import { PolygonManager, SavedPolygon } from './gis/PolygonManager';
import { showSuccessAlert, showErrorAlert, showToast, showConfirmDialog } from '../utils/alerts';

type ActiveTab = 'saved-points' | 'polygon-area' | 'search-pattern' | 'measure-distance';

export const MapPlottingPage: React.FC = () => {
  const { isDarkMode } = useTheme();

  // Active view tab
  const [activeTab, setActiveTab] = useState<ActiveTab>('saved-points');

  // Map Instance references
  const mapElementRef = useRef<HTMLDivElement>(null);
  const mapInstanceRef = useRef<google.maps.Map | null>(null);
  const [isMapReady, setIsMapReady] = useState(false);
  const [mapError, setMapError] = useState<string | null>(null);

  // Overlays references - Typed strictly to AdvancedMarkerElement
  const pointMarkersRef = useRef<google.maps.marker.AdvancedMarkerElement[]>([]);
  const draftPointMarkerRef = useRef<google.maps.marker.AdvancedMarkerElement | null>(null);
  const polygonVertexMarkersRef = useRef<google.maps.marker.AdvancedMarkerElement[]>([]);
  const polygonOverlayRef = useRef<google.maps.Polygon | null>(null);
  const savedPolygonOverlaysRef = useRef<google.maps.Polygon[]>([]);
  const searchPatternPolylineRef = useRef<google.maps.Polyline | null>(null);
  const searchPatternMarkersRef = useRef<google.maps.marker.AdvancedMarkerElement[]>([]);
  const measurePolylineRef = useRef<google.maps.Polyline | null>(null);
  const measureMarkersRef = useRef<google.maps.marker.AdvancedMarkerElement[]>([]);
  const savedRoutesPolylinesRef = useRef<google.maps.Polyline[]>([]);

  // 1. SAVED POINTS STATE (Requirement 1 & Left panel)
  const [savedPoints, setSavedPoints] = useState<LatLngPoint[]>(() => {
    try {
      const stored = localStorage.getItem('basarnas_saved_map_points');
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch {}
    return [
      {
        id: 'pt-kansar',
        lat: -4.015,
        lng: 122.585,
        label: 'Kantor SAR Kendari',
        pointType: 'kantor',
        description: 'Pusat Komando KPP Kendari, Jl. Bandara Haluoleo',
      },
      {
        id: 'pt-baubau',
        lat: -5.467,
        lng: 122.607,
        label: 'Pos SAR Baubau',
        pointType: 'pos_sar',
        description: 'Pangkalan Siaga Perairan Buton & Kepulauan',
      },
      {
        id: 'pt-lkp-1',
        lat: -3.85,
        lng: 122.75,
        label: 'LKP KM Sumber Bahagia',
        pointType: 'lkp',
        description: 'Titik duga kejadian kapal mati mesin di Teluk Kendari',
      },
    ];
  });

  // Save points to localStorage whenever changed
  useEffect(() => {
    try {
      localStorage.setItem('basarnas_saved_map_points', JSON.stringify(savedPoints));
    } catch {}
  }, [savedPoints]);

  // Point Creator / Editor State (Requirement 1 & 2)
  const [isCreatingPoint, setIsCreatingPoint] = useState(false);
  const [editingPointId, setEditingPointId] = useState<string | null>(null);
  const [pointName, setPointName] = useState('');
  const [pointIconType, setPointIconType] = useState<PointIconType>('lkp');
  const [pointAlphabetChar, setPointAlphabetChar] = useState<string>('A');
  const [pointCustomIconUrl, setPointCustomIconUrl] = useState<string | undefined>(undefined);
  const [draftCoords, setDraftCoords] = useState<{ lat: number; lng: number }>({
    lat: -4.015,
    lng: 122.585,
  });

  // Direct In-Map Drag-to-Edit for existing saved points
  const [draggedPointId, setDraggedPointId] = useState<string | null>(null);

  // 2. POLYGON STATE (Requirement 2 & 7)
  const [savedPolygons, setSavedPolygons] = useState<SavedPolygon[]>(() => {
    try {
      const stored = localStorage.getItem('basarnas_saved_polygons');
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch {}
    return [
      {
        id: 'poly-default-alpha',
        name: 'Search Area Sektor Kendari - Saponda',
        points: [
          { lat: -3.85, lng: 122.65, label: 'Titik A' },
          { lat: -3.85, lng: 122.88, label: 'Titik B' },
          { lat: -4.05, lng: 122.88, label: 'Titik C' },
          { lat: -4.05, lng: 122.65, label: 'Titik D' },
        ],
        strokeColor: '#ea580c',
        strokeWeight: 2.5,
        fillColor: '#ea580c',
        fillOpacity: 0.18,
      },
    ];
  });

  // Save polygons to localStorage whenever changed
  useEffect(() => {
    try {
      localStorage.setItem('basarnas_saved_polygons', JSON.stringify(savedPolygons));
    } catch {}
  }, [savedPolygons]);

  // Load persistent GIS data from server database on mount (cross-device sync)
  useEffect(() => {
    let isMounted = true;
    const fetchServerGisData = async () => {
      try {
        const res = await fetch('/api/gis/data');
        if (res.ok) {
          const json = await res.json();
          if (json.success && json.data && isMounted) {
            if (Array.isArray(json.data.points) && json.data.points.length > 0) {
              setSavedPoints(json.data.points);
              try {
                localStorage.setItem('basarnas_saved_map_points', JSON.stringify(json.data.points));
              } catch {}
            }
            if (Array.isArray(json.data.polygons) && json.data.polygons.length > 0) {
              setSavedPolygons(json.data.polygons);
              try {
                localStorage.setItem('basarnas_saved_polygons', JSON.stringify(json.data.polygons));
              } catch {}
            }
            if (Array.isArray(json.data.routes) && json.data.routes.length > 0) {
              setSavedDistanceRoutes(json.data.routes);
              try {
                localStorage.setItem('basarnas_saved_distance_routes', JSON.stringify(json.data.routes));
              } catch {}
            }
          }
        }
      } catch (err) {
        console.warn('Could not load GIS data from server, using local fallback:', err);
      }
    };

    fetchServerGisData();

    return () => {
      isMounted = false;
    };
  }, []);

  // Listen for GIS data restore events from Admin Backup/Restore
  useEffect(() => {
    const handleGisRestored = () => {
      try {
        const storedPts = localStorage.getItem('basarnas_saved_map_points');
        if (storedPts) setSavedPoints(JSON.parse(storedPts));
        const storedPolys = localStorage.getItem('basarnas_saved_polygons');
        if (storedPolys) setSavedPolygons(JSON.parse(storedPolys));
        const storedRoutes = localStorage.getItem('basarnas_saved_distance_routes');
        if (storedRoutes) setSavedDistanceRoutes(JSON.parse(storedRoutes));
      } catch (e) {
        console.warn('Failed to parse restored GIS data in MapPlottingPage:', e);
      }
    };

    window.addEventListener('basarnas_gis_data_restored', handleGisRestored);
    return () => window.removeEventListener('basarnas_gis_data_restored', handleGisRestored);
  }, []);

  const [isDrawingPolygon, setIsDrawingPolygon] = useState(false);
  const [activePolygonName, setActivePolygonName] = useState('');
  const [activePolygonPoints, setActivePolygonPoints] = useState<LatLngPoint[]>([]);
  const [editingPolygonId, setEditingPolygonId] = useState<string | null>(null);
  const [strokeColor, setStrokeColor] = useState<string>('#ea580c');
  const [strokeWeight, setStrokeWeight] = useState<number>(2.5);
  const [fillColor, setFillColor] = useState<string>('#ea580c');
  const [fillOpacity, setFillOpacity] = useState<number>(0.2);

  // 3. SEARCH PATTERNS STATE (Requirement 3 & 4)
  const [patternType, setPatternType] = useState<SearchPatternType>('parallel_track');
  const [patternTrackSpacingNm, setPatternTrackSpacingNm] = useState<number | string>(2.0);
  const [patternLegsCount, setPatternLegsCount] = useState<number | string>(5);
  const [selectedPoskoForPattern, setSelectedPoskoForPattern] = useState<string>('auto');
  const [patternDatumLat, setPatternDatumLat] = useState(-3.95);
  const [patternDatumLng, setPatternDatumLng] = useState(122.75);
  const [selectedPolygonForPattern, setSelectedPolygonForPattern] = useState<string>('poly-default-alpha');
  const [patternWaypoints, setPatternWaypoints] = useState<LatLngPoint[]>([]);
  const [patternColor, setPatternColor] = useState('#0284c7');

  // 4. MEASURE DISTANCE & ETA STATE (Requirement 5)
  const [savedDistanceRoutes, setSavedDistanceRoutes] = useState<SavedDistanceRoute[]>(() => {
    try {
      const stored = localStorage.getItem('basarnas_saved_distance_routes');
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch {}
    return [
      {
        id: 'route_sample_1',
        name: 'Rute Kansar ke LKP Sumber Bahagia',
        points: [
          { lat: -4.015, lng: 122.585, label: 'WP-1 (Kansar)' },
          { lat: -3.85, lng: 122.75, label: 'WP-2 (LKP)' },
        ],
        vesselSpeedKnots: 25,
        strokeColor: '#059669',
        strokeWeight: 3.5,
        strokeOpacity: 0.9,
        createdAt: 'Default Rute Siaga',
      },
    ];
  });

  const [activeRouteId, setActiveRouteId] = useState<string | null>('route_sample_1');
  const [activeRouteName, setActiveRouteName] = useState<string>('Rute Kansar ke LKP Sumber Bahagia');
  const [measurePoints, setMeasurePoints] = useState<LatLngPoint[]>([
    { lat: -4.015, lng: 122.585, label: 'WP-1 (Kansar)' },
    { lat: -3.85, lng: 122.75, label: 'WP-2 (LKP)' },
  ]);
  const [vesselSpeedKnots, setVesselSpeedKnots] = useState(25);
  const [measureStrokeColor, setMeasureStrokeColor] = useState<string>('#059669');
  const [measureStrokeWeight, setMeasureStrokeWeight] = useState<number>(3.5);
  const [measureStrokeOpacity, setMeasureStrokeOpacity] = useState<number>(0.9);

  // Basemap style & Map center readout
  const [basemapType, setBasemapType] = useState<'hybrid' | 'satellite' | 'roadmap' | 'terrain'>(
    'hybrid'
  );
  const [centerCoords, setCenterCoords] = useState<{ lat: number; lng: number }>({
    lat: -4.015,
    lng: 122.585,
  });

  // TASK 7 & 8: Lock/Unlock Point Draggable & Layer Visibility Toggles
  const [isPointsDraggableUnlocked, setIsPointsDraggableUnlocked] = useState<boolean>(false);
  const [layerVisibility, setLayerVisibility] = useState<{
    points: boolean;
    polygons: boolean;
    patterns: boolean;
    routes: boolean;
  }>({
    points: true,
    polygons: true,
    patterns: true,
    routes: true,
  });

  // REFS FOR AVOIDING STALE CLOSURES IN MAP CLICK EVENTS
  const activeTabRef = useRef(activeTab);
  activeTabRef.current = activeTab;

  const isCreatingPointRef = useRef(isCreatingPoint);
  isCreatingPointRef.current = isCreatingPoint;

  const isDrawingPolygonRef = useRef(isDrawingPolygon);
  isDrawingPolygonRef.current = isDrawingPolygon;

  const activePolygonPointsRef = useRef(activePolygonPoints);
  activePolygonPointsRef.current = activePolygonPoints;

  const measurePointsRef = useRef(measurePoints);
  measurePointsRef.current = measurePoints;

  // CENTRAL MAP & POLYGON CLICK DISPATCHER
  // Ensures clicks on any area (including directly on polygons) register for measurement, polygon drawing, or point placement
  const handleMapClick = (e: google.maps.MapMouseEvent) => {
    if (!e.latLng) return;
    const clickedLat = e.latLng.lat();
    const clickedLng = e.latLng.lng();

    const currentTab = activeTabRef.current;

    if (currentTab === 'saved-points') {
      if (isCreatingPointRef.current) {
        // Move draft point to clicked location
        setDraftCoords({ lat: clickedLat, lng: clickedLng });
      }
    } else if (currentTab === 'polygon-area') {
      // Add point in polygon tab
      const pts = activePolygonPointsRef.current;
      const letter = getAlphabetLabel(pts.length);
      const newPt: LatLngPoint = {
        lat: clickedLat,
        lng: clickedLng,
        label: `Titik ${letter}`,
      };
      setActivePolygonPoints([...pts, newPt]);
      setIsDrawingPolygon(true);
    } else if (currentTab === 'measure-distance') {
      // Add distance measurement waypoint
      const pts = measurePointsRef.current;
      const newPt: LatLngPoint = {
        lat: clickedLat,
        lng: clickedLng,
        label: `WP-${pts.length + 1}`,
      };
      setMeasurePoints([...pts, newPt]);
    }
  };

  const handleMapClickRef = useRef(handleMapClick);
  handleMapClickRef.current = handleMapClick;

  // INITIALIZE GOOGLE MAPS
  useEffect(() => {
    let isMounted = true;

    async function initMap() {
      try {
        const google = await loadGoogleMaps();
        if (!isMounted || !mapElementRef.current) return;

        // Initialize Map using modern mapId for AdvancedMarkerElement support
        const map = new google.maps.Map(mapElementRef.current, {
          center: { lat: -4.015, lng: 122.585 },
          zoom: 10,
          mapTypeId: basemapType,
          mapId: 'SAR_KENDARI_MAP', // Enables google.maps.marker.AdvancedMarkerElement!
          mapTypeControl: false,
          streetViewControl: false,
          fullscreenControl: false,
          gestureHandling: 'greedy',
        });

        mapInstanceRef.current = map;
        setIsMapReady(true);

        // Center changed listener
        map.addListener('center_changed', () => {
          const c = map.getCenter();
          if (c) {
            setCenterCoords({ lat: c.lat(), lng: c.lng() });
          }
        });

        // MAP CLICK HANDLER
        map.addListener('click', (e: google.maps.MapMouseEvent) => {
          handleMapClickRef.current(e);
        });
      } catch (err: any) {
        console.error('Failed to load Google Maps:', err);
        setMapError(err?.message || 'Gagal memuat Google Maps.');
      }
    }

    initMap();

    return () => {
      isMounted = false;
      clearAdvancedMarkers(pointMarkersRef.current);
      removeAdvancedMarker(draftPointMarkerRef.current);
      clearAdvancedMarkers(polygonVertexMarkersRef.current);
      clearAdvancedMarkers(searchPatternMarkersRef.current);
      clearAdvancedMarkers(measureMarkersRef.current);
    };
  }, []);

  // Update Basemap type when changed
  useEffect(() => {
    if (mapInstanceRef.current) {
      mapInstanceRef.current.setMapTypeId(basemapType);
    }
  }, [basemapType]);

  // 1. RENDER SAVED POINTS MARKERS (Requirement 1: Display Point Name above pin & custom icons)
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMapReady) return;

    // Clear old point markers cleanly via AdvancedMarkerElement API
    clearAdvancedMarkers(pointMarkersRef.current);
    pointMarkersRef.current = [];

    // If points layer is disabled, do not render
    if (!layerVisibility.points) return;

    // Render each saved point with point name label on map
    savedPoints.forEach((point) => {
      const isBeingDragged = draggedPointId === point.id;
      const canDragThisPoint = isPointsDraggableUnlocked || editingPointId === point.id;
      const contentHtml = createPointMarkerHtml(point, isBeingDragged);

      const marker = createAdvancedMapMarker(map, {
        position: { lat: point.lat, lng: point.lng },
        title: `${point.label || 'Titik'} (${decimalToDms(point.lat, point.lng)})${canDragThisPoint ? ' - Mode Edit: Geser untuk memindahkan' : ' (Terkunci)'}`,
        draggable: canDragThisPoint,
        contentHtml,
        zIndex: isBeingDragged ? 999 : 50,
        onDrag: (lat, lng) => {
          setSavedPoints((prev) =>
            prev.map((p) => (p.id === point.id ? { ...p, lat, lng } : p))
          );
        },
        onDragEnd: async (lat, lng) => {
          const updatedPoint = { ...point, lat, lng };
          setSavedPoints((prev) =>
            prev.map((p) => (p.id === point.id ? updatedPoint : p))
          );
          // Persist coordinates directly to database via AJAX
          try {
            await fetch(`/api/gis/points/${point.id}`, {
              method: 'PUT',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ lat, lng }),
            });
          } catch (err) {
            console.error('AJAX sync failed on point dragend:', err);
          }
          showToast(`Posisi "${point.label || 'Titik'}" dipindahkan ke ${decimalToDms(lat, lng)}`);
        },
      });

      if (marker) pointMarkersRef.current.push(marker);
    });
  }, [savedPoints, draggedPointId, isMapReady, isPointsDraggableUnlocked, editingPointId, layerVisibility.points]);

  // 1B. RENDER DRAFT POINT MARKER (Requirement 1: Active draggable point with visible name label)
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMapReady) return;

    if (draftPointMarkerRef.current) {
      removeAdvancedMarker(draftPointMarkerRef.current);
      draftPointMarkerRef.current = null;
    }

    if (isCreatingPoint) {
      const draftPt: LatLngPoint = {
        lat: draftCoords.lat,
        lng: draftCoords.lng,
        label: pointName || 'Titik Baru',
        pointType: pointIconType,
        customIconUrl: pointCustomIconUrl,
        alphabetChar: pointAlphabetChar,
      };

      const contentHtml = createPointMarkerHtml(draftPt, true);

      const draftMarker = createAdvancedMapMarker(map, {
        position: draftCoords,
        title: `${pointName || 'Titik Baru'} (Geser di Peta)`,
        draggable: true,
        contentHtml,
        zIndex: 1000,
        onDrag: (lat, lng) => {
          setDraftCoords({ lat, lng });
        },
        onDragEnd: (lat, lng) => {
          setDraftCoords({ lat, lng });
        },
      });

      draftPointMarkerRef.current = draftMarker;
    }
  }, [
    isCreatingPoint,
    draftCoords,
    pointIconType,
    pointAlphabetChar,
    pointCustomIconUrl,
    pointName,
    isMapReady,
  ]);

  // 2. RENDER POLYGONS & VERTICES (Requirement 2: Ensure polygon allows click-through for distance measurement)
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMapReady) return;

    // Clear active polygon
    if (polygonOverlayRef.current) {
      polygonOverlayRef.current.setMap(null);
      polygonOverlayRef.current = null;
    }

    // Clear active polygon vertex markers
    clearAdvancedMarkers(polygonVertexMarkersRef.current);
    polygonVertexMarkersRef.current = [];

    // Clear saved polygon overlays
    savedPolygonOverlaysRef.current.forEach((p) => p.setMap(null));
    savedPolygonOverlaysRef.current = [];

    // Check layer visibility
    if (!layerVisibility.polygons) return;

    // Render active drawing polygon
    if (activePolygonPoints.length >= 2) {
      const activePoly = new google.maps.Polygon({
        paths: activePolygonPoints.map((p) => ({ lat: p.lat, lng: p.lng })),
        strokeColor,
        strokeOpacity: 0.95,
        strokeWeight,
        fillColor,
        fillOpacity: activePolygonPoints.length >= 3 ? fillOpacity : 0.05,
        clickable: activeTab !== 'measure-distance',
        map,
      });

      activePoly.addListener('click', (e: google.maps.MapMouseEvent) => {
        handleMapClickRef.current(e);
      });
      polygonOverlayRef.current = activePoly;
    }

    // Render active polygon vertex markers (Titik A, B, C...)
    activePolygonPoints.forEach((pt, index) => {
      const letter = getAlphabetLabel(index);
      const contentHtml = createPolygonVertexSvgHtml(letter, strokeColor, false);

      const vertexMarker = createAdvancedMapMarker(map, {
        position: { lat: pt.lat, lng: pt.lng },
        title: `${pt.label || `Titik ${letter}`} - Geser untuk mengubah sudut`,
        draggable: true,
        contentHtml,
        zIndex: 200,
        onDrag: (lat, lng) => {
          setActivePolygonPoints((prev) =>
            prev.map((p, i) => (i === index ? { ...p, lat, lng } : p))
          );
        },
        onDragEnd: (lat, lng) => {
          setActivePolygonPoints((prev) => {
            const nextPoints = prev.map((p, i) => (i === index ? { ...p, lat, lng } : p));
            if (editingPolygonId) {
              setSavedPolygons((allPolys) =>
                allPolys.map((poly) =>
                  poly.id === editingPolygonId ? { ...poly, points: nextPoints } : poly
                )
              );
              fetch(`/api/gis/polygons/${editingPolygonId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ points: nextPoints }),
              }).catch((err) => console.error('AJAX sync failed on polygon vertex dragend:', err));
            }
            return nextPoints;
          });
        },
      });

      if (vertexMarker) polygonVertexMarkersRef.current.push(vertexMarker);
    });

    // Render saved polygons
    // When in measure-distance tab, setting clickable: false ensures clicks pass directly through to Google Map
    savedPolygonOverlaysRef.current.forEach((p) => p.setMap(null));
    savedPolygonOverlaysRef.current = [];

    savedPolygons.forEach((poly) => {
      if (isDrawingPolygon && editingPolygonId === poly.id) return;

      const overlay = new google.maps.Polygon({
        paths: poly.points.map((p) => ({ lat: p.lat, lng: p.lng })),
        strokeColor: poly.strokeColor,
        strokeOpacity: 0.9,
        strokeWeight: poly.strokeWeight,
        fillColor: poly.fillColor,
        fillOpacity: poly.fillOpacity,
        clickable: activeTab !== 'measure-distance',
        map,
      });

      overlay.addListener('click', (e: google.maps.MapMouseEvent) => {
        handleMapClickRef.current(e);
      });

      savedPolygonOverlaysRef.current.push(overlay);
    });
  }, [
    activePolygonPoints,
    isDrawingPolygon,
    strokeColor,
    strokeWeight,
    fillColor,
    fillOpacity,
    savedPolygons,
    editingPolygonId,
    activeTab,
    isMapReady,
    layerVisibility.polygons,
  ]);

  // 3. RENDER SEARCH PATTERNS (Requirement 4: corners manual adjustment without numbers)
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMapReady) return;

    if (searchPatternPolylineRef.current) {
      searchPatternPolylineRef.current.setMap(null);
      searchPatternPolylineRef.current = null;
    }
    clearAdvancedMarkers(searchPatternMarkersRef.current);
    searchPatternMarkersRef.current = [];

    if (!layerVisibility.patterns) return;

    if (patternWaypoints.length >= 2) {
      searchPatternPolylineRef.current = new google.maps.Polyline({
        path: patternWaypoints.map((p) => ({ lat: p.lat, lng: p.lng })),
        strokeColor: patternColor,
        strokeOpacity: 0.95,
        strokeWeight: 3.5,
        geodesic: true,
        map,
      });

      // Render draggable unnumbered corner grab handles
      patternWaypoints.forEach((wp, index) => {
        const isStart = index === 0;
        const isEnd = index === patternWaypoints.length - 1;
        const type: 'start' | 'end' | 'corner' = isStart ? 'start' : isEnd ? 'end' : 'corner';
        const contentHtml = createPatternCornerSvgHtml(type, patternColor, false);

        const m = createAdvancedMapMarker(map, {
          position: { lat: wp.lat, lng: wp.lng },
          title: isStart
            ? `Titik Awal (S) - ${decimalToDms(wp.lat, wp.lng)} - Geser untuk menyesuaikan`
            : isEnd
            ? `Titik Akhir (E) - ${decimalToDms(wp.lat, wp.lng)} - Geser untuk menyesuaikan`
            : `Sudut Lintasan - ${decimalToDms(wp.lat, wp.lng)} - Geser untuk menyesuaikan sudut`,
          draggable: true,
          contentHtml,
          zIndex: isStart || isEnd ? 260 : 250,
          onDrag: (lat, lng) => {
            // Live update polyline while dragging
            if (searchPatternPolylineRef.current) {
              const currentPath = searchPatternPolylineRef.current.getPath();
              if (currentPath && currentPath.getLength() > index) {
                currentPath.setAt(index, new google.maps.LatLng(lat, lng));
              }
            }
          },
          onDragEnd: (lat, lng) => {
            setPatternWaypoints((prev) =>
              prev.map((p, i) => (i === index ? { ...p, lat, lng } : p))
            );
          },
        });

        if (m) searchPatternMarkersRef.current.push(m);
      });
    }
  }, [patternWaypoints, patternColor, isMapReady, layerVisibility.patterns]);

  // 4. RENDER DISTANCE MEASUREMENT & SAVED ROUTES (Requirement 5: custom color, weight, opacity & multiple routes)
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMapReady) return;

    // Clear previous polylines & markers
    if (measurePolylineRef.current) {
      measurePolylineRef.current.setMap(null);
      measurePolylineRef.current = null;
    }
    savedRoutesPolylinesRef.current.forEach((poly) => poly.setMap(null));
    savedRoutesPolylinesRef.current = [];

    clearAdvancedMarkers(measureMarkersRef.current);
    measureMarkersRef.current = [];

    if (!layerVisibility.routes) return;

    // 4A. Render all saved routes (inactive routes rendered with their individual saved styling)
    savedDistanceRoutes.forEach((route) => {
      if (route.id === activeRouteId) return; // Active route is rendered below with interactive handles
      if (route.points.length < 2) return;

      const poly = new google.maps.Polyline({
        path: route.points.map((p) => ({ lat: p.lat, lng: p.lng })),
        strokeColor: route.strokeColor || '#059669',
        strokeOpacity: route.strokeOpacity ?? 0.85,
        strokeWeight: route.strokeWeight || 3.5,
        geodesic: true,
        map,
      });

      savedRoutesPolylinesRef.current.push(poly);
    });

    // 4B. Render active distance measurement route with custom thickness, color, transparency & draggable vertices
    if (measurePoints.length >= 2) {
      measurePolylineRef.current = new google.maps.Polyline({
        path: measurePoints.map((p) => ({ lat: p.lat, lng: p.lng })),
        strokeColor: measureStrokeColor,
        strokeOpacity: measureStrokeOpacity,
        strokeWeight: measureStrokeWeight,
        geodesic: true,
        map,
      });
    }

    if (activeTab === 'measure-distance') {
      measurePoints.forEach((pt, index) => {
        const contentHtml = createPolygonVertexSvgHtml(String(index + 1), measureStrokeColor, false);

        const m = createAdvancedMapMarker(map, {
          position: { lat: pt.lat, lng: pt.lng },
          title: `${pt.label || `WP-${index + 1}`} (${decimalToDms(pt.lat, pt.lng)}) - Geser untuk memindahkan`,
          draggable: true,
          contentHtml,
          zIndex: 180,
          onDrag: (lat, lng) => {
            if (measurePolylineRef.current) {
              const currentPath = measurePolylineRef.current.getPath();
              if (currentPath && currentPath.getLength() > index) {
                currentPath.setAt(index, new google.maps.LatLng(lat, lng));
              }
            }
          },
          onDragEnd: (lat, lng) => {
            setMeasurePoints((prev) =>
              prev.map((p, i) => (i === index ? { ...p, lat, lng } : p))
            );
          },
        });

        if (m) measureMarkersRef.current.push(m);
      });
    }
  }, [
    measurePoints,
    savedDistanceRoutes,
    activeRouteId,
    measureStrokeColor,
    measureStrokeWeight,
    measureStrokeOpacity,
    activeTab,
    isMapReady,
    layerVisibility.routes,
  ]);

  // START POINT CREATION (Requirement 1 & 2)
  const handleStartCreatePoint = () => {
    const map = mapInstanceRef.current;
    const center = map?.getCenter() || { lat: () => -4.015, lng: () => 122.585 };
    const lat = typeof center.lat === 'function' ? center.lat() : center.lat;
    const lng = typeof center.lng === 'function' ? center.lng() : center.lng;

    setPointName('');
    setPointIconType('lkp');
    setPointAlphabetChar('A');
    setPointCustomIconUrl(undefined);
    setDraftCoords({ lat, lng });
    setEditingPointId(null);
    setIsCreatingPoint(true);
    setActiveTab('saved-points');

    if (map) {
      map.panTo({ lat, lng });
    }
    showToast('Marker aktif dimunculkan di peta. Silakan geser atau ubah koordinat.');
  };

  const handleEditPoint = (pt: LatLngPoint) => {
    setPointName(pt.label || '');
    setPointIconType(pt.pointType || pt.iconType || 'waypoint');
    setPointAlphabetChar(pt.alphabetChar || 'A');
    setPointCustomIconUrl(pt.customIconUrl);
    setDraftCoords({ lat: pt.lat, lng: pt.lng });
    setEditingPointId(pt.id || null);
    setIsCreatingPoint(true);
    setActiveTab('saved-points');

    if (mapInstanceRef.current) {
      mapInstanceRef.current.panTo({ lat: pt.lat, lng: pt.lng });
    }
  };

  const handleSavePoint = () => {
    if (!pointName.trim()) {
      showErrorAlert('Nama Titik Wajib Diisi', 'Silakan masukkan nama atau identitas titik.');
      return;
    }

    if (editingPointId) {
      const updatedPoint: LatLngPoint = {
        id: editingPointId,
        label: pointName,
        pointType: pointIconType,
        alphabetChar: pointIconType === 'alphabet' ? (pointAlphabetChar || 'A') : undefined,
        customIconUrl: pointCustomIconUrl,
        lat: draftCoords.lat,
        lng: draftCoords.lng,
      };
      setSavedPoints((prev) =>
        prev.map((p) => (p.id === editingPointId ? updatedPoint : p))
      );
      try {
        fetch(`/api/gis/points/${editingPointId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(updatedPoint),
        });
      } catch (e) {
        console.error('AJAX sync failed on point edit:', e);
      }
      showSuccessAlert('Titik Diperbarui', `Titik "${pointName}" berhasil diperbarui.`);
    } else {
      const newPoint: LatLngPoint = {
        id: `pt-${Date.now()}`,
        label: pointName,
        pointType: pointIconType,
        alphabetChar: pointIconType === 'alphabet' ? (pointAlphabetChar || 'A') : undefined,
        customIconUrl: pointCustomIconUrl,
        lat: draftCoords.lat,
        lng: draftCoords.lng,
      };
      setSavedPoints((prev) => [...prev, newPoint]);
      try {
        fetch('/api/gis/points', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(newPoint),
        });
      } catch (e) {
        console.error('AJAX sync failed on point create:', e);
      }
      showSuccessAlert('Titik Disimpan', `Titik "${pointName}" berhasil ditambahkan ke peta.`);
    }

    setIsCreatingPoint(false);
    setEditingPointId(null);
  };

  const handleDeletePoint = async (id: string, label: string) => {
    const confirmed = await showConfirmDialog(
      'Hapus Titik Plotting?',
      `Apakah Anda yakin ingin menghapus titik "${label}"?`
    );
    if (confirmed) {
      setSavedPoints((prev) => prev.filter((p) => p.id !== id));
      if (draggedPointId === id) setDraggedPointId(null);
      try {
        fetch(`/api/gis/points/${id}`, { method: 'DELETE' });
      } catch (e) {
        console.error('AJAX sync failed on point delete:', e);
      }
      showToast(`Titik "${label}" telah dihapus.`);
    }
  };

  // POLYGON HANDLERS (Requirement 2)
  const handleStartDrawingPolygon = () => {
    setIsDrawingPolygon(true);
    setEditingPolygonId(null);
    setActivePolygonName(`Sektor SAR ${String.fromCharCode(65 + savedPolygons.length)}`);
    setActivePolygonPoints([]);
    setActiveTab('polygon-area');
    showToast('Mode Gambar Polygon Aktif. Klik peta untuk membuat Titik A, B, C, dst.');
  };

  const handleSavePolygon = () => {
    if (activePolygonPoints.length < 3) {
      showErrorAlert('Minimal 3 Titik', 'Polygon area pencarian membutuhkan minimal 3 titik sudut.');
      return;
    }

    const name = activePolygonName.trim() || `Sektor SAR ${savedPolygons.length + 1}`;

    if (editingPolygonId) {
      const updatedPoly: SavedPolygon = {
        id: editingPolygonId,
        name,
        points: activePolygonPoints,
        strokeColor,
        strokeWeight,
        fillColor,
        fillOpacity,
      };
      setSavedPolygons((prev) =>
        prev.map((poly) => (poly.id === editingPolygonId ? updatedPoly : poly))
      );
      try {
        fetch(`/api/gis/polygons/${editingPolygonId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(updatedPoly),
        });
      } catch (e) {
        console.error('AJAX sync failed on polygon update:', e);
      }
      showSuccessAlert('Polygon Diperbarui', `Polygon "${name}" berhasil diperbarui.`);
    } else {
      const newPoly: SavedPolygon = {
        id: `poly-${Date.now()}`,
        name,
        points: activePolygonPoints,
        strokeColor,
        strokeWeight,
        fillColor,
        fillOpacity,
      };
      setSavedPolygons((prev) => [...prev, newPoly]);
      try {
        fetch('/api/gis/polygons', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(newPoly),
        });
      } catch (e) {
        console.error('AJAX sync failed on polygon create:', e);
      }
      showSuccessAlert('Polygon Disimpan', `Polygon "${name}" berhasil disimpan.`);
    }

    setIsDrawingPolygon(false);
    setEditingPolygonId(null);
    setActivePolygonPoints([]);
  };

  const handleEditSavedPolygon = (poly: SavedPolygon) => {
    setEditingPolygonId(poly.id);
    setActivePolygonName(poly.name);
    setActivePolygonPoints(poly.points);
    setStrokeColor(poly.strokeColor);
    setStrokeWeight(poly.strokeWeight);
    setFillColor(poly.fillColor);
    setFillOpacity(poly.fillOpacity);
    setIsDrawingPolygon(true);
    setActiveTab('polygon-area');

    if (mapInstanceRef.current && poly.points.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      poly.points.forEach((p) => bounds.extend(p));
      mapInstanceRef.current.fitBounds(bounds);
    }
  };

  const handleDeleteSavedPolygon = async (id: string, name: string) => {
    const confirmed = await showConfirmDialog(
      'Hapus Polygon?',
      `Apakah Anda yakin ingin menghapus polygon "${name}"?`
    );
    if (confirmed) {
      setSavedPolygons((prev) => prev.filter((p) => p.id !== id));
      try {
        fetch(`/api/gis/polygons/${id}`, { method: 'DELETE' });
      } catch (e) {
        console.error('AJAX sync failed on polygon delete:', e);
      }
      if (editingPolygonId === id) {
        setIsDrawingPolygon(false);
        setEditingPolygonId(null);
        setActivePolygonPoints([]);
      }
      showToast(`Polygon "${name}" telah dihapus.`);
    }
  };

  const handleFocusPolygon = (points: LatLngPoint[]) => {
    if (!mapInstanceRef.current || points.length === 0) return;
    const bounds = new google.maps.LatLngBounds();
    points.forEach((p) => bounds.extend(p));
    mapInstanceRef.current.fitBounds(bounds);
  };

  // SEARCH PATTERN AREA DIMENSION & DYNAMIC LEGS CALCULATION (Requirement 3)
  const selectedPolygonObj = useMemo(() => {
    return savedPolygons.find((p) => p.id === selectedPolygonForPattern);
  }, [savedPolygons, selectedPolygonForPattern]);

  const areaSweepDimensionNm = useMemo(() => {
    if (selectedPolygonObj && selectedPolygonObj.points.length >= 3) {
      const lats = selectedPolygonObj.points.map((p) => p.lat);
      const lngs = selectedPolygonObj.points.map((p) => p.lng);
      const minLat = Math.min(...lats);
      const maxLat = Math.max(...lats);
      const minLng = Math.min(...lngs);
      const maxLng = Math.max(...lngs);
      const midLat = (minLat + maxLat) / 2;

      if (patternType === 'creeping_line') {
        // Horizontal sweep dimension
        const kmPerDegLng = 111.32 * Math.cos((midLat * Math.PI) / 180);
        return Math.max(0.1, ((maxLng - minLng) * kmPerDegLng) / 1.852);
      } else {
        // Vertical sweep dimension
        const kmPerDegLat = 110.574;
        return Math.max(0.1, ((maxLat - minLat) * kmPerDegLat) / 1.852);
      }
    }
    // Default 10.0 NM standard area dimension when no polygon selected
    return 10.0;
  }, [selectedPolygonObj, patternType]);

  // Automatically calculate number of legs: Dimension (NM) / Track Spacing (NM)
  // e.g. 10 NM / 2 NM = 5 lintasan automatically!
  useEffect(() => {
    const spacingNum =
      typeof patternTrackSpacingNm === 'number'
        ? patternTrackSpacingNm
        : parseFloat(patternTrackSpacingNm as string);
    if (spacingNum && spacingNum > 0 && areaSweepDimensionNm > 0) {
      const calculatedLegs = Math.max(1, Math.round(areaSweepDimensionNm / spacingNum));
      setPatternLegsCount(calculatedLegs);
    }
  }, [areaSweepDimensionNm, patternTrackSpacingNm]);

  // SEARCH PATTERN GENERATION (Requirements 3 & 4)
  const handleGenerateSearchPattern = (boundPolygon?: SavedPolygon) => {
    const targetPoly =
      boundPolygon || savedPolygons.find((p) => p.id === selectedPolygonForPattern);

    // Identify candidate Posko / Bases to determine closest Entry Point
    const poskoCandidates = savedPoints.filter(
      (p) =>
        p.pointType === 'kantor' ||
        p.pointType === 'pos_sar' ||
        (p.label && p.label.toLowerCase().includes('pos')) ||
        (p.label && p.label.toLowerCase().includes('kansar')) ||
        (p.label && p.label.toLowerCase().includes('base'))
    );

    let referencePosko: { lat: number; lng: number; label: string } | null = null;
    if (selectedPoskoForPattern !== 'auto') {
      const found = savedPoints.find((p) => p.id === selectedPoskoForPattern);
      if (found) referencePosko = { lat: found.lat, lng: found.lng, label: found.label || 'Posko Acuan' };
    }

    if (!referencePosko) {
      if (poskoCandidates.length > 0) {
        // If polygon is present, find which posko is closest to polygon centroid
        const targetLat = targetPoly
          ? targetPoly.points.reduce((acc, p) => acc + p.lat, 0) / targetPoly.points.length
          : patternDatumLat;
        const targetLng = targetPoly
          ? targetPoly.points.reduce((acc, p) => acc + p.lng, 0) / targetPoly.points.length
          : patternDatumLng;

        poskoCandidates.sort((a, b) => {
          const dA = Math.hypot(a.lat - targetLat, a.lng - targetLng);
          const dB = Math.hypot(b.lat - targetLat, b.lng - targetLng);
          return dA - dB;
        });
        referencePosko = { lat: poskoCandidates[0].lat, lng: poskoCandidates[0].lng, label: poskoCandidates[0].label || 'Posko SAR' };
      } else if (savedPoints.length > 0) {
        referencePosko = { lat: savedPoints[0].lat, lng: savedPoints[0].lng, label: savedPoints[0].label || 'Titik Acuan' };
      } else {
        referencePosko = { lat: -4.015, lng: 122.585, label: 'Kansar Kendari (Default Base)' };
      }
    }

    const spacingVal =
      typeof patternTrackSpacingNm === 'number'
        ? patternTrackSpacingNm
        : parseFloat(patternTrackSpacingNm as string) || 2.0;
    const legsVal =
      typeof patternLegsCount === 'number'
        ? patternLegsCount
        : parseInt(patternLegsCount as string, 10) || 5;

    const waypoints = generateSearchPatternWaypoints(
      patternType,
      { lat: patternDatumLat, lng: patternDatumLng },
      spacingVal,
      spacingVal * (legsVal / 2),
      targetPoly ? targetPoly.points : undefined,
      referencePosko ? { lat: referencePosko.lat, lng: referencePosko.lng } : undefined,
      legsVal
    );

    setPatternWaypoints(waypoints);
    setActiveTab('search-pattern');

    if (mapInstanceRef.current && waypoints.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      waypoints.forEach((w) => bounds.extend(w));
      mapInstanceRef.current.fitBounds(bounds);
    }

    showSuccessAlert(
      'Pola Pencarian Dihasilkan',
      `Berhasil membuat lintasan ${patternType.toUpperCase()} (${legsVal} lintasan, S=${spacingVal} NM). Entry point disesuaikan dari ${referencePosko ? referencePosko.label : 'posko terdekat'}. Sudut lintasan dapat disesuaikan manual di peta.`
    );
  };

  const handleApplySearchPatternToPolygon = (poly: SavedPolygon) => {
    setSelectedPolygonForPattern(poly.id);
    handleGenerateSearchPattern(poly);
  };

  // DISTANCE & ETA METRICS (Requirement 5)
  const distanceMetrics = useMemo(() => {
    return calculatePolylineDistance(measurePoints);
  }, [measurePoints]);

  const estimatedMinutes = useMemo(() => {
    return distanceMetrics.totalNm > 0 && vesselSpeedKnots > 0
      ? (distanceMetrics.totalNm / vesselSpeedKnots) * 60
      : 0;
  }, [distanceMetrics, vesselSpeedKnots]);

  const handleStartNewRoute = () => {
    setActiveRouteId(null);
    setActiveRouteName(`Rute Pengukuran ${savedDistanceRoutes.length + 1}`);
    setMeasurePoints([]);
    setMeasureStrokeColor('#059669');
    setMeasureStrokeWeight(3.5);
    setMeasureStrokeOpacity(0.9);
    showToast('Mode Rute Baru: Klik di peta untuk membuat titik rute pengukuran.');
  };

  const handleSaveCurrentRoute = () => {
    if (measurePoints.length < 2) {
      showErrorAlert('Rute Belum Lengkap', 'Rute pengukuran membutuhkan minimal 2 titik sudut.');
      return;
    }

    const routeId = activeRouteId || `route_${Date.now()}`;
    const name = activeRouteName.trim() || `Rute ${savedDistanceRoutes.length + 1}`;

    const newRoute: SavedDistanceRoute = {
      id: routeId,
      name,
      points: measurePoints,
      vesselSpeedKnots,
      strokeColor: measureStrokeColor,
      strokeWeight: measureStrokeWeight,
      strokeOpacity: measureStrokeOpacity,
      createdAt: new Date().toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      }),
    };

    const updatedRoutes = activeRouteId
      ? savedDistanceRoutes.map((r) => (r.id === activeRouteId ? newRoute : r))
      : [newRoute, ...savedDistanceRoutes];

    setSavedDistanceRoutes(updatedRoutes);
    setActiveRouteId(routeId);

    try {
      localStorage.setItem('basarnas_saved_distance_routes', JSON.stringify(updatedRoutes));
      fetch('/api/gis/routes', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(newRoute),
      });
    } catch {}

    showSuccessAlert(
      'Rute Pengukuran Tersimpan',
      `Rute "${name}" (${distanceMetrics.totalNm.toFixed(1)} NM) berhasil disimpan dengan gaya garis kustom.`
    );
  };

  const handleSelectSavedRoute = (route: SavedDistanceRoute) => {
    setActiveRouteId(route.id);
    setActiveRouteName(route.name);
    setMeasurePoints(route.points);
    setVesselSpeedKnots(route.vesselSpeedKnots || 25);
    setMeasureStrokeColor(route.strokeColor || '#059669');
    setMeasureStrokeWeight(route.strokeWeight || 3.5);
    setMeasureStrokeOpacity(route.strokeOpacity ?? 0.9);

    if (mapInstanceRef.current && route.points.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      route.points.forEach((p) => bounds.extend(p));
      mapInstanceRef.current.fitBounds(bounds);
    }
    showToast(`Rute "${route.name}" aktif.`);
  };

  const handleDeleteSavedRoute = async (routeId: string, name: string) => {
    const confirmed = await showConfirmDialog(
      'Hapus Rute Tersimpan?',
      `Apakah Anda yakin ingin menghapus rute "${name}"?`
    );
    if (confirmed) {
      const updated = savedDistanceRoutes.filter((r) => r.id !== routeId);
      setSavedDistanceRoutes(updated);
      try {
        localStorage.setItem('basarnas_saved_distance_routes', JSON.stringify(updated));
        fetch(`/api/gis/routes/${routeId}`, { method: 'DELETE' });
      } catch {}

      if (activeRouteId === routeId) {
        setActiveRouteId(null);
        setMeasurePoints([]);
      }
      showToast(`Rute "${name}" telah dihapus.`);
    }
  };

  return (
    <div className="flex flex-col h-[calc(100vh-68px)] overflow-hidden font-sans">
      {/* TOP COMPACT BAR: Status & Basemap Switcher */}
      <div
        className={`px-4 py-2 border-b flex items-center justify-between gap-3 text-xs shrink-0 ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}
      >
        <div className="flex items-center gap-2">
          <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" />
          <span className="font-extrabold tracking-wide text-slate-800 dark:text-slate-200">
            GIS PLOTTING & RADAR OPERASI SAR KENDARI
          </span>
          <span className="hidden md:inline px-2 py-0.5 rounded-full bg-orange-500/10 text-orange-600 dark:text-orange-400 font-mono text-[10px] font-bold">
            Google Maps v3 + Advanced Markers
          </span>
        </div>

        {/* Center coordinates & Basemap */}
        <div className="flex items-center gap-3">
          <div className="hidden sm:flex items-center gap-1.5 font-mono text-[11px] text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-950 px-2.5 py-1 rounded-lg border border-inherit">
            <Compass className="w-3.5 h-3.5 text-orange-500" />
            <span>Pusat: {decimalToDms(centerCoords.lat, centerCoords.lng)}</span>
          </div>

          <div className="flex items-center gap-1 bg-slate-100 dark:bg-slate-950 p-1 rounded-xl border border-inherit">
            {(['hybrid', 'satellite', 'roadmap', 'terrain'] as const).map((type) => (
              <button
                key={type}
                type="button"
                onClick={() => setBasemapType(type)}
                className={`px-2 py-0.5 rounded-lg text-[10px] font-bold capitalize transition-colors cursor-pointer ${
                  basemapType === type
                    ? 'bg-orange-600 text-white shadow-xs'
                    : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                }`}
              >
                {type}
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* MAIN GIS SPLIT VIEW */}
      <div className="flex-1 flex overflow-hidden">
        {/* LEFT PANEL: Controls, Points, Polygon, Patterns */}
        <div
          className={`w-96 md:w-[420px] border-r flex flex-col shrink-0 shadow-lg z-10 ${
            isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-50 border-slate-200'
          }`}
        >
          {/* EXTERNAL CARD: LAYER VISIBILITY & MODE GESER TITIK (Requirement 3: Pindahkan kontrol keluar dari kanvas peta) */}
          <div className="p-3 bg-white dark:bg-slate-900 border-b border-inherit space-y-2.5">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-1.5 font-bold text-xs text-slate-800 dark:text-slate-100">
                <Layers className="w-3.5 h-3.5 text-orange-500" />
                <span>Layer & Visibilitas Peta</span>
              </div>

              {/* Mode Geser Titik Marker Button */}
              <button
                type="button"
                onClick={() => {
                  const next = !isPointsDraggableUnlocked;
                  setIsPointsDraggableUnlocked(next);
                  showToast(
                    next
                      ? 'Mode Edit Posisi Aktif: Titik marker kini dapat digeser langsung di peta.'
                      : 'Posisi Titik Dikunci: Marker diamankan dari pergeseran tidak sengaja.',
                    next ? 'warning' : 'info'
                  );
                }}
                className={`px-2.5 py-1 rounded-lg text-[10px] font-bold flex items-center gap-1 border transition-all cursor-pointer ${
                  isPointsDraggableUnlocked
                    ? 'bg-amber-600 hover:bg-amber-500 text-white border-amber-400 animate-pulse shadow-xs'
                    : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700'
                }`}
                title={isPointsDraggableUnlocked ? 'Klik untuk mengunci posisi marker' : 'Klik untuk mengizinkan geser marker (Edit Posisi)'}
              >
                {isPointsDraggableUnlocked ? (
                  <>
                    <Unlock className="w-3 h-3 text-white" />
                    <span>Mode Geser: AKTIF</span>
                  </>
                ) : (
                  <>
                    <Lock className="w-3 h-3 text-slate-400" />
                    <span>Posisi: TERKUNCI</span>
                  </>
                )}
              </button>
            </div>

            {/* Layer Checkboxes in 2x2 Clean Grid */}
            <div className="grid grid-cols-2 gap-1.5 p-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs">
              {/* Toggle Points */}
              <label className="flex items-center justify-between gap-1.5 px-1.5 py-1 rounded-md cursor-pointer hover:bg-slate-200/50 dark:hover:bg-slate-800/50 transition-colors">
                <span className="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700 dark:text-slate-300 truncate">
                  <span className="w-2 h-2 rounded-full bg-orange-500 shrink-0" />
                  Titik SAR ({savedPoints.length})
                </span>
                <input
                  type="checkbox"
                  checked={layerVisibility.points}
                  onChange={(e) => setLayerVisibility((prev) => ({ ...prev, points: e.target.checked }))}
                  className="rounded text-orange-600 focus:ring-orange-500 cursor-pointer h-3.5 w-3.5"
                />
              </label>

              {/* Toggle Polygons */}
              <label className="flex items-center justify-between gap-1.5 px-1.5 py-1 rounded-md cursor-pointer hover:bg-slate-200/50 dark:hover:bg-slate-800/50 transition-colors">
                <span className="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700 dark:text-slate-300 truncate">
                  <span className="w-2 h-2 rounded-full bg-amber-500 shrink-0" />
                  Area Poly ({savedPolygons.length})
                </span>
                <input
                  type="checkbox"
                  checked={layerVisibility.polygons}
                  onChange={(e) => setLayerVisibility((prev) => ({ ...prev, polygons: e.target.checked }))}
                  className="rounded text-orange-600 focus:ring-orange-500 cursor-pointer h-3.5 w-3.5"
                />
              </label>

              {/* Toggle Search Patterns */}
              <label className="flex items-center justify-between gap-1.5 px-1.5 py-1 rounded-md cursor-pointer hover:bg-slate-200/50 dark:hover:bg-slate-800/50 transition-colors">
                <span className="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700 dark:text-slate-300 truncate">
                  <span className="w-2 h-2 rounded-full bg-sky-500 shrink-0" />
                  Pola SAR
                </span>
                <input
                  type="checkbox"
                  checked={layerVisibility.patterns}
                  onChange={(e) => setLayerVisibility((prev) => ({ ...prev, patterns: e.target.checked }))}
                  className="rounded text-orange-600 focus:ring-orange-500 cursor-pointer h-3.5 w-3.5"
                />
              </label>

              {/* Toggle Measure Routes */}
              <label className="flex items-center justify-between gap-1.5 px-1.5 py-1 rounded-md cursor-pointer hover:bg-slate-200/50 dark:hover:bg-slate-800/50 transition-colors">
                <span className="flex items-center gap-1.5 text-[11px] font-semibold text-slate-700 dark:text-slate-300 truncate">
                  <span className="w-2 h-2 rounded-full bg-emerald-500 shrink-0" />
                  Rute ({savedDistanceRoutes.length})
                </span>
                <input
                  type="checkbox"
                  checked={layerVisibility.routes}
                  onChange={(e) => setLayerVisibility((prev) => ({ ...prev, routes: e.target.checked }))}
                  className="rounded text-orange-600 focus:ring-orange-500 cursor-pointer h-3.5 w-3.5"
                />
              </label>
            </div>
          </div>

          {/* TAB HEADERS */}
          <div className="grid grid-cols-4 border-b border-inherit bg-white dark:bg-slate-900 text-xs font-bold">
            <button
              type="button"
              onClick={() => setActiveTab('saved-points')}
              className={`py-2.5 px-1 border-b-2 flex flex-col items-center gap-1 cursor-pointer transition-colors ${
                activeTab === 'saved-points'
                  ? 'border-orange-500 text-orange-600 dark:text-orange-400 bg-orange-500/5'
                  : 'border-transparent text-slate-400 hover:text-slate-600'
              }`}
            >
              <MapPin className="w-4 h-4" />
              <span className="text-[10px]">Titik ({savedPoints.length})</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('polygon-area')}
              className={`py-2.5 px-1 border-b-2 flex flex-col items-center gap-1 cursor-pointer transition-colors ${
                activeTab === 'polygon-area'
                  ? 'border-orange-500 text-orange-600 dark:text-orange-400 bg-orange-500/5'
                  : 'border-transparent text-slate-400 hover:text-slate-600'
              }`}
            >
              <Maximize2 className="w-4 h-4" />
              <span className="text-[10px]">Polygon</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('search-pattern')}
              className={`py-2.5 px-1 border-b-2 flex flex-col items-center gap-1 cursor-pointer transition-colors ${
                activeTab === 'search-pattern'
                  ? 'border-orange-500 text-orange-600 dark:text-orange-400 bg-orange-500/5'
                  : 'border-transparent text-slate-400 hover:text-slate-600'
              }`}
            >
              <Sparkles className="w-4 h-4" />
              <span className="text-[10px]">Pola SAR</span>
            </button>

            <button
              type="button"
              onClick={() => setActiveTab('measure-distance')}
              className={`py-2.5 px-1 border-b-2 flex flex-col items-center gap-1 cursor-pointer transition-colors ${
                activeTab === 'measure-distance'
                  ? 'border-orange-500 text-orange-600 dark:text-orange-400 bg-orange-500/5'
                  : 'border-transparent text-slate-400 hover:text-slate-600'
              }`}
            >
              <Ruler className="w-4 h-4" />
              <span className="text-[10px]">Jarak & ETA</span>
            </button>
          </div>

          {/* TAB CONTENT (SCROLLABLE) */}
          <div className="flex-1 overflow-y-auto p-4 space-y-4 scrollbar-thin">
            {/* 1. TAB: SAVED POINTS */}
            {activeTab === 'saved-points' && (
              <div className="space-y-3">
                {isCreatingPoint ? (
                  <PointCreatorCard
                    name={pointName}
                    setName={setPointName}
                    selectedIconType={pointIconType}
                    setSelectedIconType={setPointIconType}
                    alphabetChar={pointAlphabetChar}
                    setAlphabetChar={setPointAlphabetChar}
                    customIconUrl={pointCustomIconUrl}
                    setCustomIconUrl={setPointCustomIconUrl}
                    lat={draftCoords.lat}
                    lng={draftCoords.lng}
                    onCoordsChange={setDraftCoords}
                    onSave={handleSavePoint}
                    onCancel={() => {
                      setIsCreatingPoint(false);
                      setEditingPointId(null);
                    }}
                    isEditMode={!!editingPointId}
                  />
                ) : (
                  <>
                    <button
                      type="button"
                      onClick={handleStartCreatePoint}
                      className="w-full py-2.5 px-4 rounded-2xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white font-bold text-xs shadow-md shadow-orange-600/20 flex items-center justify-center gap-2 cursor-pointer transition-all"
                    >
                      <Plus className="w-4 h-4" />
                      <span>Tambah Titik Baru di Peta (LKP / Pos / Alut)</span>
                    </button>

                    {draggedPointId && (
                      <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs flex items-center justify-between">
                        <span className="flex items-center gap-1.5 font-bold">
                          <Move className="w-3.5 h-3.5 animate-pulse" />
                          Mode Geser Peta Aktif
                        </span>
                        <button
                          type="button"
                          onClick={() => setDraggedPointId(null)}
                          className="px-2 py-0.5 rounded-md bg-amber-500 text-white font-bold text-[10px] cursor-pointer"
                        >
                          Selesai Geser
                        </button>
                      </div>
                    )}

                    <div className="space-y-2">
                      <div className="flex items-center justify-between text-xs text-slate-400 font-bold px-1">
                        <span>DAFTAR TITIK TERSIMPAN</span>
                        <span>{savedPoints.length} Titik</span>
                      </div>

                      {savedPoints.map((pt, idx) => {
                        const preset = PRESET_POINT_ICONS.find((p) => p.type === pt.pointType);
                        const isBeingDragged = draggedPointId === pt.id;

                        return (
                          <div
                            key={pt.id}
                            className={`p-3 rounded-2xl border space-y-2 transition-all ${
                              isBeingDragged
                                ? 'border-amber-500 ring-2 ring-amber-500/20 bg-amber-500/5'
                                : isDarkMode
                                ? 'bg-slate-900 border-slate-800'
                                : 'bg-white border-slate-200'
                            }`}
                          >
                            <div className="flex items-start justify-between gap-2">
                              <div className="flex items-center gap-2.5 min-w-0">
                                <span className="w-5 h-5 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono text-[10px] font-bold flex items-center justify-center shrink-0">
                                  {idx + 1}
                                </span>
                                <div className="min-w-0">
                                  <p className="font-bold text-xs truncate text-slate-900 dark:text-white">
                                    {pt.label}
                                  </p>
                                  <span className="text-[10px] text-orange-500 font-semibold block">
                                    {preset?.label || 'Waypoint'}
                                  </span>
                                </div>
                              </div>

                              <div className="flex items-center gap-1 shrink-0">
                                <button
                                  type="button"
                                  onClick={() => {
                                    if (mapInstanceRef.current) {
                                      mapInstanceRef.current.panTo({ lat: pt.lat, lng: pt.lng });
                                      mapInstanceRef.current.setZoom(13);
                                    }
                                  }}
                                  className="p-1.5 rounded-lg hover:bg-slate-500/10 text-slate-400 hover:text-orange-500 cursor-pointer"
                                  title="Fokus di peta"
                                >
                                  <Crosshair className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleEditPoint(pt)}
                                  className="p-1.5 rounded-lg hover:bg-slate-500/10 text-slate-400 hover:text-orange-500 cursor-pointer"
                                  title="Edit data titik"
                                >
                                  <Edit3 className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() =>
                                    setDraggedPointId(draggedPointId === pt.id ? null : pt.id!)
                                  }
                                  className={`p-1.5 rounded-lg cursor-pointer ${
                                    isBeingDragged
                                      ? 'bg-amber-500 text-white'
                                      : 'hover:bg-slate-500/10 text-slate-400 hover:text-amber-500'
                                  }`}
                                  title="Geser titik langsung di peta"
                                >
                                  <Move className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeletePoint(pt.id!, pt.label || 'Titik')}
                                  className="p-1.5 rounded-lg hover:bg-red-500/10 text-slate-400 hover:text-red-500 cursor-pointer"
                                  title="Hapus titik"
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            </div>

                            {/* DMS Format Display */}
                            <div className="p-2 rounded-xl bg-slate-50 dark:bg-slate-950 font-mono text-[11px] text-slate-600 dark:text-slate-400">
                              {decimalToDms(pt.lat, pt.lng)}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </>
                )}
              </div>
            )}

            {/* 2. TAB: POLYGON AREA */}
            {activeTab === 'polygon-area' && (
              <PolygonManager
                isDrawing={isDrawingPolygon}
                onStartDrawing={handleStartDrawingPolygon}
                onCancelDrawing={() => {
                  setIsDrawingPolygon(false);
                  setEditingPolygonId(null);
                  setActivePolygonPoints([]);
                }}
                onSaveDrawing={handleSavePolygon}
                activePolygonName={activePolygonName}
                setActivePolygonName={setActivePolygonName}
                activePoints={activePolygonPoints}
                onRemovePoint={(idx) => {
                  setActivePolygonPoints((prev) => prev.filter((_, i) => i !== idx));
                }}
                strokeColor={strokeColor}
                setStrokeColor={setStrokeColor}
                strokeWeight={strokeWeight}
                setStrokeWeight={setStrokeWeight}
                fillColor={fillColor}
                setFillColor={setFillColor}
                fillOpacity={fillOpacity}
                setFillOpacity={setFillOpacity}
                savedPolygons={savedPolygons}
                onEditSavedPolygon={handleEditSavedPolygon}
                onDeleteSavedPolygon={handleDeleteSavedPolygon}
                onFocusPolygon={handleFocusPolygon}
                onApplySearchPatternToPolygon={handleApplySearchPatternToPolygon}
              />
            )}

            {/* 3. TAB: SEARCH PATTERNS */}
            {activeTab === 'search-pattern' && (
              <div className="space-y-3.5">
                <div
                  className={`p-4 rounded-2xl border space-y-3 ${
                    isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                  }`}
                >
                  <span className="text-xs font-black uppercase tracking-wider text-orange-500 flex items-center gap-1.5">
                    <Sparkles className="w-4 h-4" />
                    Pola Pencarian SAR Standar IAMSAR / E-SAR
                  </span>

                  {/* Pattern Selection */}
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    {[
                      {
                        type: 'parallel_track' as const,
                        label: 'Parallel Track (PS)',
                        desc: 'Sapuan sejajar sumbu laut',
                      },
                      {
                        type: 'creeping_line' as const,
                        label: 'Creeping Line (CS)',
                        desc: 'Tegak lurus koridor',
                      },
                      {
                        type: 'expanding_square' as const,
                        label: 'Expanding Square (SS)',
                        desc: 'Spiral melingkar dari LKP',
                      },
                      {
                        type: 'e_sar' as const,
                        label: 'E-SAR Hutan (Darat)',
                        desc: 'Rapat jelajah rimba',
                      },
                    ].map((item) => (
                      <button
                        key={item.type}
                        type="button"
                        onClick={() => setPatternType(item.type)}
                        className={`p-2.5 rounded-xl border text-left transition-all cursor-pointer ${
                          patternType === item.type
                            ? 'border-orange-500 bg-orange-500/10 text-orange-600 dark:text-orange-400 font-bold ring-1 ring-orange-500'
                            : isDarkMode
                            ? 'border-slate-800 bg-slate-950 text-slate-300'
                            : 'border-slate-200 bg-slate-50 text-slate-700'
                        }`}
                      >
                        <p className="text-xs font-bold">{item.label}</p>
                        <p className="text-[10px] text-slate-400 leading-tight mt-0.5">{item.desc}</p>
                      </button>
                    ))}
                  </div>

                  {/* Automatic Calculation Readout (Requirement 3: Automatic legs based on area dimensions & track spacing) */}
                  <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-800 dark:text-amber-300 space-y-1">
                    <div className="font-bold flex items-center justify-between">
                      <span>Dimensi Area Sapuan:</span>
                      <span className="font-mono">{areaSweepDimensionNm.toFixed(1)} NM</span>
                    </div>
                    <div className="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400">
                      <span>Formula Otomatis:</span>
                      <span className="font-mono">
                        {areaSweepDimensionNm.toFixed(1)} NM ÷ {patternTrackSpacingNm || 1} NM ={' '}
                        <strong className="text-amber-600 dark:text-amber-400">
                          {patternLegsCount} Lintasan
                        </strong>
                      </span>
                    </div>
                  </div>

                  {/* Pattern Configuration */}
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <div>
                      <label className="text-[10px] text-slate-400 font-semibold block mb-1">
                        Track Spacing (S):
                      </label>
                      <div className="relative">
                        <input
                          type="number"
                          step="0.1"
                          min="0.1"
                          max="20"
                          value={patternTrackSpacingNm}
                          onChange={(e) => {
                            setPatternTrackSpacingNm(e.target.value);
                          }}
                          onBlur={() => {
                            if (patternTrackSpacingNm === '' || Number(patternTrackSpacingNm) <= 0) {
                              setPatternTrackSpacingNm(2.0);
                            }
                          }}
                          className={`w-full px-2.5 py-1.5 rounded-xl border text-xs font-mono font-bold ${
                            isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                          }`}
                          placeholder="2.0"
                        />
                        <span className="absolute right-2 top-1.5 text-[10px] text-slate-400 font-bold">
                          NM
                        </span>
                      </div>
                    </div>

                    <div>
                      <label className="text-[10px] text-slate-400 font-semibold block mb-1">
                        Jumlah Lintasan (Otomatis):
                      </label>
                      <input
                        type="number"
                        min="1"
                        max="50"
                        value={patternLegsCount}
                        onChange={(e) => {
                          setPatternLegsCount(e.target.value);
                        }}
                        onBlur={() => {
                          if (patternLegsCount === '' || Number(patternLegsCount) < 1) {
                            setPatternLegsCount(5);
                          }
                        }}
                        className={`w-full px-2.5 py-1.5 rounded-xl border text-xs font-mono font-bold ${
                          isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                        }`}
                        placeholder="5"
                      />
                    </div>
                  </div>

                  {/* Posko Selection for Entry Point Priority (Requirement 4) */}
                  <div>
                    <label className="text-[10px] text-slate-400 font-semibold mb-1 flex items-center justify-between">
                      <span>Posko / Pangkalan Acuan:</span>
                      <span className="text-[9px] text-sky-500 font-bold">Entry Point Terdekat</span>
                    </label>
                    <select
                      value={selectedPoskoForPattern}
                      onChange={(e) => setSelectedPoskoForPattern(e.target.value)}
                      className={`w-full px-2.5 py-1.5 rounded-xl border text-xs font-semibold cursor-pointer ${
                        isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    >
                      <option value="auto">⚡ Otomatis (Posko / Kansar Terdekat)</option>
                      {savedPoints
                        .filter(
                          (p) =>
                            p.pointType === 'kantor' ||
                            p.pointType === 'pos_sar' ||
                            (p.label && p.label.toLowerCase().includes('pos')) ||
                            (p.label && p.label.toLowerCase().includes('kansar'))
                        )
                        .map((pos) => (
                          <option key={pos.id} value={pos.id}>
                            {pos.label || 'Posko'} ({(pos.pointType || 'pos_sar').toUpperCase()})
                          </option>
                        ))}
                    </select>
                    <p className="text-[10px] text-sky-500/90 leading-tight mt-1">
                      ℹ️ Entry Point otomatis dipilih dari sudut polygon terdekat dengan Posko ini, dan margin track spacing (S/2) diterapkan pada seluruh batas area.
                    </p>
                  </div>

                  {/* Bound to Polygon Area Selection (Requirement 6) */}
                  {savedPolygons.length > 0 && (
                    <div>
                      <label className="text-[10px] text-slate-400 font-semibold block mb-1">
                        Batas Area (Di Dalam Polygon):
                      </label>
                      <select
                        value={selectedPolygonForPattern}
                        onChange={(e) => setSelectedPolygonForPattern(e.target.value)}
                        className={`w-full px-2.5 py-1.5 rounded-xl border text-xs font-semibold cursor-pointer ${
                          isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                        }`}
                      >
                        <option value="">Gunakan Datum Center (Bebas)</option>
                        {savedPolygons.map((p) => (
                          <option key={p.id} value={p.id}>
                            {p.name} ({p.points.length} Titik)
                          </option>
                        ))}
                      </select>
                    </div>
                  )}

                  {/* Generate Button */}
                  <button
                    type="button"
                    onClick={() => handleGenerateSearchPattern()}
                    className="w-full py-2.5 px-4 rounded-xl bg-sky-600 hover:bg-sky-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center justify-center gap-2 cursor-pointer transition-all"
                  >
                    <Sparkles className="w-4 h-4" />
                    <span>Generate Pola Pencarian di Peta</span>
                  </button>
                </div>

                {/* Generated Pattern Readout (Requirement 4: corners manual adjustment without numbers) */}
                {patternWaypoints.length > 0 && (
                  <div
                    className={`p-3.5 rounded-2xl border space-y-2.5 ${
                      isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-bold text-sky-500 flex items-center gap-1.5">
                        <Move className="w-3.5 h-3.5 animate-pulse" />
                        Sudut Lintasan ({patternWaypoints.length} Titik)
                      </span>
                      <button
                        type="button"
                        onClick={() => setPatternWaypoints([])}
                        className="text-red-500 hover:text-red-400 text-[11px] font-bold cursor-pointer"
                      >
                        Hapus Pola
                      </button>
                    </div>

                    <div className="p-2 rounded-xl bg-sky-500/10 text-sky-700 dark:text-sky-300 text-[10px] leading-tight">
                      💡 <strong>Penyesuaian Manual:</strong> Titik sudut pada peta tidak diberi nomor dan dapat langsung digeser di peta untuk menyesuaikan sudut dan bentuk lintasan secara presisi.
                    </div>

                    <div className="max-h-48 overflow-y-auto space-y-1.5 font-mono text-[11px] scrollbar-thin">
                      {patternWaypoints.map((wp, idx) => {
                        const isStart = idx === 0;
                        const isEnd = idx === patternWaypoints.length - 1;
                        const labelTitle = isStart
                          ? 'Titik Awal (S)'
                          : isEnd
                          ? 'Titik Akhir (E)'
                          : 'Sudut Lintasan';

                        return (
                          <div
                            key={idx}
                            className="p-1.5 rounded-lg bg-slate-50 dark:bg-slate-950 flex items-center justify-between border border-inherit"
                          >
                            <div className="flex items-center gap-1.5">
                              <span
                                className={`w-2 h-2 rounded-full ${
                                  isStart
                                    ? 'bg-emerald-500'
                                    : isEnd
                                    ? 'bg-red-500'
                                    : 'bg-orange-500'
                                }`}
                              />
                              <span className="font-sans font-bold text-[11px] text-slate-700 dark:text-slate-300">
                                {labelTitle}
                              </span>
                            </div>
                            <span className="text-slate-400 text-[10px]">
                              {decimalToDms(wp.lat, wp.lng)}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* 4. TAB: MEASURE DISTANCE & ETA (Requirement 5: Save & add multiple routes, custom stroke style) */}
            {activeTab === 'measure-distance' && (
              <div className="space-y-3.5">
                <div
                  className={`p-4 rounded-2xl border space-y-3 ${
                    isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-black uppercase tracking-wider text-emerald-500 flex items-center gap-1.5">
                      <Ruler className="w-4 h-4" />
                      Jarak, Haluan & ETA SAR
                    </span>
                    <button
                      type="button"
                      onClick={handleStartNewRoute}
                      className="px-2.5 py-1 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[10px] flex items-center gap-1 shadow-xs cursor-pointer transition-colors"
                    >
                      <Plus className="w-3.5 h-3.5" />
                      <span>Rute Baru</span>
                    </button>
                  </div>

                  {/* Route Name Input */}
                  <div>
                    <label className="text-[10px] text-slate-400 font-semibold block mb-1">
                      Nama Rute Pengukuran:
                    </label>
                    <input
                      type="text"
                      value={activeRouteName}
                      onChange={(e) => setActiveRouteName(e.target.value)}
                      placeholder="Contoh: Rute Kansar ke LKP"
                      className={`w-full px-2.5 py-1.5 rounded-xl border text-xs font-semibold ${
                        isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    />
                  </div>

                  <div className="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-[11px] text-emerald-700 dark:text-emerald-300">
                    👉 <strong>Klik di atas peta</strong> untuk menambah titik rute pengukuran (bisa diklik di dalam area polygon). Titik rute dapat langsung digeser di peta!
                  </div>

                  {/* STYLING CUSTOMIZATION: Color, Stroke Weight, Opacity (Requirement 5) */}
                  <div className="p-3 rounded-xl border border-inherit space-y-2.5 bg-slate-50 dark:bg-slate-950/70">
                    <span className="text-[10px] font-bold text-slate-400 uppercase tracking-wide block">
                      Kustomisasi Garis Rute (Warna, Tebal, Opasitas)
                    </span>

                    {/* Color Presets */}
                    <div className="space-y-1">
                      <div className="flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                        <span>Warna Garis:</span>
                        <span className="font-mono text-xs font-bold" style={{ color: measureStrokeColor }}>
                          {measureStrokeColor}
                        </span>
                      </div>
                      <div className="flex items-center gap-1.5">
                        {[
                          '#059669', // Emerald
                          '#0284c7', // Sky
                          '#dc2626', // Red
                          '#ea580c', // Orange
                          '#9333ea', // Purple
                          '#eab308', // Yellow
                          '#06b6d4', // Cyan
                        ].map((c) => (
                          <button
                            key={c}
                            type="button"
                            onClick={() => setMeasureStrokeColor(c)}
                            className={`w-6 h-6 rounded-full border-2 cursor-pointer transition-transform ${
                              measureStrokeColor.toLowerCase() === c.toLowerCase()
                                ? 'scale-115 border-white ring-2 ring-emerald-500'
                                : 'border-transparent hover:scale-105'
                            }`}
                            style={{ backgroundColor: c }}
                          />
                        ))}
                        <input
                          type="color"
                          value={measureStrokeColor}
                          onChange={(e) => setMeasureStrokeColor(e.target.value)}
                          className="w-7 h-7 rounded-lg cursor-pointer bg-transparent border-0"
                          title="Pilih warna kustom"
                        />
                      </div>
                    </div>

                    {/* Stroke Weight */}
                    <div className="space-y-1">
                      <div className="flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                        <span>Ketebalan Garis:</span>
                        <span className="font-mono font-bold text-slate-700 dark:text-slate-300">
                          {measureStrokeWeight} px
                        </span>
                      </div>
                      <input
                        type="range"
                        min="1"
                        max="10"
                        step="0.5"
                        value={measureStrokeWeight}
                        onChange={(e) => setMeasureStrokeWeight(parseFloat(e.target.value))}
                        className="w-full accent-emerald-500 cursor-pointer h-1.5 bg-slate-200 dark:bg-slate-800 rounded-lg"
                      />
                    </div>

                    {/* Opacity */}
                    <div className="space-y-1">
                      <div className="flex items-center justify-between text-[10px] text-slate-400 font-semibold">
                        <span>Transparansi / Opasitas:</span>
                        <span className="font-mono font-bold text-slate-700 dark:text-slate-300">
                          {Math.round(measureStrokeOpacity * 100)}%
                        </span>
                      </div>
                      <input
                        type="range"
                        min="0.2"
                        max="1"
                        step="0.05"
                        value={measureStrokeOpacity}
                        onChange={(e) => setMeasureStrokeOpacity(parseFloat(e.target.value))}
                        className="w-full accent-emerald-500 cursor-pointer h-1.5 bg-slate-200 dark:bg-slate-800 rounded-lg"
                      />
                    </div>
                  </div>

                  {/* Speed Setting */}
                  <div>
                    <label className="text-[10px] text-slate-400 font-semibold block mb-1">
                      Kecepatan Kapal / Alut SAR:
                    </label>
                    <div className="flex items-center gap-2">
                      <input
                        type="number"
                        min="5"
                        max="60"
                        value={vesselSpeedKnots}
                        onChange={(e) => setVesselSpeedKnots(parseFloat(e.target.value) || 20)}
                        className={`w-28 px-3 py-1.5 rounded-xl border text-xs font-mono font-bold ${
                          isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                        }`}
                      />
                      <span className="text-xs font-bold text-slate-400">Knots (Kts)</span>
                    </div>
                  </div>

                  {/* Metrics */}
                  <div className="grid grid-cols-2 gap-2 text-xs">
                    <div className="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-inherit">
                      <span className="text-[10px] text-slate-400 block font-semibold">Total Jarak</span>
                      <span className="text-base font-black text-emerald-600 dark:text-emerald-400 font-mono">
                        {distanceMetrics.totalNm} <span className="text-xs font-sans">NM</span>
                      </span>
                      <span className="text-[10px] text-slate-500 block font-mono">
                        {distanceMetrics.totalKm} km
                      </span>
                    </div>

                    <div className="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-inherit">
                      <span className="text-[10px] text-slate-400 block font-semibold">Estimasi Tiba (ETA)</span>
                      <span className="text-base font-black text-orange-600 dark:text-orange-400 font-mono">
                        {formatDurationIndonesian(estimatedMinutes)}
                      </span>
                      <span className="text-[10px] text-slate-500 block font-mono">
                        Pada speed {vesselSpeedKnots} kts
                      </span>
                    </div>
                  </div>

                  {/* Action Buttons: Save Route */}
                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={handleSaveCurrentRoute}
                      disabled={measurePoints.length < 2}
                      className="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20 cursor-pointer transition-all"
                    >
                      <Save className="w-3.5 h-3.5" />
                      <span>Simpan Rute Ini</span>
                    </button>
                    {measurePoints.length > 0 && (
                      <button
                        type="button"
                        onClick={() => setMeasurePoints([])}
                        className="py-2 px-3 rounded-xl bg-slate-200 dark:bg-slate-800 hover:bg-red-500/10 hover:text-red-500 text-slate-600 dark:text-slate-400 font-bold text-xs cursor-pointer transition-colors"
                      >
                        Reset Titik
                      </button>
                    )}
                  </div>

                  {/* Active Waypoints List */}
                  <div className="space-y-1">
                    <span className="text-[10px] font-bold text-slate-400 uppercase block">
                      Titik Sudut Rute Aktif ({measurePoints.length}):
                    </span>
                    <div className="max-h-32 overflow-y-auto space-y-1 font-mono text-[11px] scrollbar-thin">
                      {measurePoints.map((pt, idx) => (
                        <div
                          key={idx}
                          className="p-1.5 rounded-lg border flex items-center justify-between bg-white dark:bg-slate-950 border-inherit"
                        >
                          <span className="font-bold text-slate-500">WP-{idx + 1}</span>
                          <span className="text-slate-400">{decimalToDms(pt.lat, pt.lng)}</span>
                          <button
                            type="button"
                            onClick={() =>
                              setMeasurePoints((prev) => prev.filter((_, i) => i !== idx))
                            }
                            className="p-0.5 text-red-500 hover:bg-red-500/10 rounded cursor-pointer"
                          >
                            <Trash2 className="w-3 h-3" />
                          </button>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>

                {/* SAVED ROUTES LIBRARY (Requirement 5: Manage & switch saved routes) */}
                {savedDistanceRoutes.length > 0 && (
                  <div
                    className={`p-4 rounded-2xl border space-y-2.5 ${
                      isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div className="flex items-center justify-between text-xs font-bold text-slate-400">
                      <span>RUTE PENGUKURAN TERSIMPAN</span>
                      <span>{savedDistanceRoutes.length} Rute</span>
                    </div>

                    <div className="space-y-2 max-h-56 overflow-y-auto scrollbar-thin">
                      {savedDistanceRoutes.map((route) => {
                        const isCurrentActive = route.id === activeRouteId;
                        const routeMetrics = calculatePolylineDistance(route.points);
                        const routeEtaMinutes =
                          routeMetrics.totalNm > 0 && (route.vesselSpeedKnots || 25) > 0
                            ? (routeMetrics.totalNm / (route.vesselSpeedKnots || 25)) * 60
                            : 0;

                        return (
                          <div
                            key={route.id}
                            className={`p-3 rounded-xl border space-y-2 transition-all ${
                              isCurrentActive
                                ? 'border-emerald-500 bg-emerald-500/5 ring-1 ring-emerald-500'
                                : isDarkMode
                                ? 'bg-slate-950 border-slate-800'
                                : 'bg-slate-50 border-slate-200'
                            }`}
                          >
                            <div className="flex items-center justify-between gap-2">
                              <div className="flex items-center gap-2 min-w-0">
                                <span
                                  className="w-3 h-3 rounded-full shrink-0"
                                  style={{ backgroundColor: route.strokeColor || '#059669' }}
                                />
                                <div className="truncate">
                                  <h4 className="font-bold text-xs text-slate-800 dark:text-slate-200 truncate">
                                    {route.name}
                                  </h4>
                                  <span className="text-[10px] text-slate-400">
                                    {route.points.length} Titik • {routeMetrics.totalNm} NM • ETA:{' '}
                                    {formatDurationIndonesian(routeEtaMinutes)}
                                  </span>
                                </div>
                              </div>

                              <div className="flex items-center gap-1 shrink-0">
                                <button
                                  type="button"
                                  onClick={() => handleSelectSavedRoute(route)}
                                  className={`px-2 py-1 rounded-lg text-[10px] font-bold cursor-pointer transition-colors ${
                                    isCurrentActive
                                      ? 'bg-emerald-600 text-white'
                                      : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-600 hover:text-white'
                                  }`}
                                >
                                  {isCurrentActive ? 'Aktif' : 'Pilih'}
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleDeleteSavedRoute(route.id, route.name)}
                                  className="p-1 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 cursor-pointer"
                                  title="Hapus rute"
                                >
                                  <Trash2 className="w-3.5 h-3.5" />
                                </button>
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>

        {/* RIGHT PANEL: GOOGLE MAPS CANVAS */}
        <div className="flex-1 relative bg-slate-900">
          <div ref={mapElementRef} className="w-full h-full" />

          {/* Map Error Banner */}
          {mapError && (
            <div className="absolute inset-0 flex items-center justify-center p-6 bg-slate-900/90 backdrop-blur-xs text-white z-20">
              <div className="max-w-md p-6 rounded-2xl bg-slate-800 border border-red-500/30 text-center space-y-3">
                <div className="w-12 h-12 rounded-full bg-red-500/20 text-red-400 flex items-center justify-center mx-auto">
                  <MapIcon className="w-6 h-6" />
                </div>
                <h3 className="font-bold text-base text-red-400">Peta Tidak Dapat Dimuat</h3>
                <p className="text-xs text-slate-300">{mapError}</p>
              </div>
            </div>
          )}

          {/* Quick Map Floating Controls */}
          <div className="absolute bottom-6 right-6 flex flex-col gap-2 z-10">
            <button
              type="button"
              onClick={() => {
                if (mapInstanceRef.current) {
                  mapInstanceRef.current.panTo({ lat: -4.015, lng: 122.585 });
                  mapInstanceRef.current.setZoom(10);
                }
              }}
              className="p-2.5 rounded-xl bg-white/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-200 shadow-md hover:bg-white dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition-all cursor-pointer"
              title="Reset ke Kantor SAR Kendari"
            >
              <Crosshair className="w-5 h-5 text-orange-500" />
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
