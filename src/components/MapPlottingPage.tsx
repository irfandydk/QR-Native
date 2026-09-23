import React, { useState, useEffect, useRef, useMemo } from 'react';
import {
  Map as MapIcon,
  Compass,
  Ruler,
  Maximize2,
  Minimize2,
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
  Navigation,
  Tent,
  CheckCircle2,
  PanelLeftClose,
  PanelLeftOpen,
  FileDown,
  ChevronDown,
} from 'lucide-react';
import { useTheme } from '../context/ThemeContext';
import {
  decimalToDms,
  getAlphabetLabel,
  calculatePolylineDistance,
  calculatePolygonArea,
  generateSearchPatternWaypoints,
  calculateAzimuthHeading,
  findClosestPoskoToPolygon,
  LatLngPoint,
  PointIconType,
  SearchPatternType,
  DEFAULT_SAR_UNITS,
  formatDurationIndonesian,
  SavedDistanceRoute,
  SavedSearchPattern,
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
import { LocationSearchBar, LocationSearchResult } from './gis/LocationSearchBar';
import { PrintMapModal } from './gis/PrintMapModal';
import { downloadKml, downloadKmz, downloadGeoJson } from '../utils/kmlExportUtils';
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
  const savedPatternsPolylinesRef = useRef<google.maps.Polyline[]>([]);
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
            if (Array.isArray(json.data.search_patterns) && json.data.search_patterns.length > 0) {
              setSavedSearchPatterns(json.data.search_patterns);
              try {
                localStorage.setItem('basarnas_saved_search_patterns', JSON.stringify(json.data.search_patterns));
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
        const storedPatterns = localStorage.getItem('basarnas_saved_search_patterns');
        if (storedPatterns) setSavedSearchPatterns(JSON.parse(storedPatterns));
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

  // 3. SEARCH PATTERNS STATE (Requirement 2 & Persistence)
  const [savedSearchPatterns, setSavedSearchPatterns] = useState<SavedSearchPattern[]>(() => {
    try {
      const stored = localStorage.getItem('basarnas_saved_search_patterns');
      if (stored) {
        const parsed = JSON.parse(stored);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      }
    } catch {}
    return [];
  });

  const [activePatternId, setActivePatternId] = useState<string | null>(null);
  const [editingPatternId, setEditingPatternId] = useState<string | null>(null);
  const [patternName, setPatternName] = useState<string>('Pola Lintasan SAR Sektor A');
  const [patternType, setPatternType] = useState<SearchPatternType>('parallel_track');
  const [patternTrackSpacingNm, setPatternTrackSpacingNm] = useState<number | string>(2.0);
  const [patternLegsCount, setPatternLegsCount] = useState<number | string>(5);
  const [selectedPoskoForPattern, setSelectedPoskoForPattern] = useState<string>('auto');
  const [patternDatumLat, setPatternDatumLat] = useState(-3.95);
  const [patternDatumLng, setPatternDatumLng] = useState(122.75);
  const [selectedPolygonForPattern, setSelectedPolygonForPattern] = useState<string>('poly-default-alpha');
  const [patternWaypoints, setPatternWaypoints] = useState<LatLngPoint[]>([]);
  const [patternColor, setPatternColor] = useState('#0284c7');

  // Save search patterns to localStorage whenever changed
  useEffect(() => {
    try {
      localStorage.setItem('basarnas_saved_search_patterns', JSON.stringify(savedSearchPatterns));
    } catch {}
  }, [savedSearchPatterns]);

  // 4. MEASURE DISTANCE & ETA STATE (Requirement 3: Enable/Disable Form, Mode Jarak Lurus vs Jalur, Heading/Azimuth)
  const [isRouteFormEnabled, setIsRouteFormEnabled] = useState<boolean>(false);
  const [routeMeasurementMode, setRouteMeasurementMode] = useState<'straight' | 'path'>('straight');

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

  // TASK 7 & 8: Lock/Unlock Point Draggable (Edit Posisi Titik)
  const [isPointsDraggableUnlocked, setIsPointsDraggableUnlocked] = useState<boolean>(false);

  // NEW FEATURE 1: Location Search State & Temporary Pin
  const [searchPin, setSearchPin] = useState<LocationSearchResult | null>(null);
  const searchMarkerRef = useRef<google.maps.marker.AdvancedMarkerElement | null>(null);

  // NEW FEATURE 2: Fullscreen State & Ref
  const mapWrapperRef = useRef<HTMLDivElement>(null);
  const [isFullscreen, setIsFullscreen] = useState<boolean>(false);

  // NEW FEATURE 3: Sidebar Visibility Toggle (Tools Plotting)
  const [isSidebarVisible, setIsSidebarVisible] = useState<boolean>(true);

  // NEW FEATURE 4: Print Map Modal & Export Menu States
  const [isPrintModalOpen, setIsPrintModalOpen] = useState<boolean>(false);
  const [isExportMenuOpen, setIsExportMenuOpen] = useState<boolean>(false);

  // Sync fullscreen change with document API
  useEffect(() => {
    const handleFullscreenChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
      setTimeout(() => {
        if (mapInstanceRef.current) {
          google.maps.event.trigger(mapInstanceRef.current, 'resize');
        }
      }, 150);
    };

    document.addEventListener('fullscreenchange', handleFullscreenChange);
    return () => document.removeEventListener('fullscreenchange', handleFullscreenChange);
  }, []);

  const toggleFullscreen = () => {
    const container = mapWrapperRef.current || document.documentElement;
    if (!document.fullscreenElement) {
      if (container.requestFullscreen) {
        container.requestFullscreen();
      }
    } else {
      if (document.exitFullscreen) {
        document.exitFullscreen();
      }
    }
  };

  const toggleSidebar = () => {
    setIsSidebarVisible((prev) => {
      const next = !prev;
      setTimeout(() => {
        if (mapInstanceRef.current) {
          google.maps.event.trigger(mapInstanceRef.current, 'resize');
        }
      }, 150);
      return next;
    });
  };

  // Location search selection handler
  const handleSelectLocation = (result: LocationSearchResult) => {
    setSearchPin(result);
    if (mapInstanceRef.current) {
      mapInstanceRef.current.panTo({ lat: result.lat, lng: result.lng });
      mapInstanceRef.current.setZoom(13);
    }
    showToast(`Peta beralih ke "${result.name.split(',')[0]}"`, 'info');
  };

  const handleConvertSearchPinToPoint = () => {
    if (!searchPin) return;
    setDraftCoords({ lat: searchPin.lat, lng: searchPin.lng });
    setPointName(searchPin.name.split(',')[0]);
    setPointIconType('waypoint');
    setIsCreatingPoint(true);
    setEditingPointId(null);
    setActiveTab('saved-points');
    if (!isSidebarVisible) {
      setIsSidebarVisible(true);
    }
    showToast('Koordinat lokasi diterapkan ke form pembuatan titik SAR baru.', 'success');
  };

  // Spatial Export Handlers
  const handleExportKml = () => {
    downloadKml(savedPoints, savedPolygons, savedSearchPatterns, savedDistanceRoutes);
    setIsExportMenuOpen(false);
    showToast('File KML berhasil diunduh untuk Google Earth / GIS!', 'success');
  };

  const handleExportKmz = async () => {
    try {
      await downloadKmz(savedPoints, savedPolygons, savedSearchPatterns, savedDistanceRoutes);
      setIsExportMenuOpen(false);
      showToast('File KMZ terkompresi berhasil diunduh!', 'success');
    } catch {
      showErrorAlert('Gagal Ekspor KMZ', 'Terjadi kesalahan saat memproses arsip KMZ.');
    }
  };

  const handleExportGeoJson = () => {
    downloadGeoJson(savedPoints, savedPolygons, savedSearchPatterns, savedDistanceRoutes);
    setIsExportMenuOpen(false);
    showToast('File GeoJSON berhasil diunduh!', 'success');
  };

  // CARD-LEVEL INDIVIDUAL VISIBILITY TOGGLES (100% independent per-card toggle)
  const [hiddenPointIds, setHiddenPointIds] = useState<string[]>(() => {
    try {
      const stored = localStorage.getItem('gis_hidden_points');
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  });

  const [hiddenPolygonIds, setHiddenPolygonIds] = useState<string[]>(() => {
    try {
      const stored = localStorage.getItem('gis_hidden_polygons');
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  });

  const [hiddenRouteIds, setHiddenRouteIds] = useState<string[]>(() => {
    try {
      const stored = localStorage.getItem('gis_hidden_routes');
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  });

  const [hiddenPatternIds, setHiddenPatternIds] = useState<string[]>(() => {
    try {
      const stored = localStorage.getItem('gis_hidden_patterns');
      return stored ? JSON.parse(stored) : [];
    } catch {
      return [];
    }
  });

  const togglePointVisibility = (id: string) => {
    setHiddenPointIds((prev) => {
      const next = prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id];
      try {
        localStorage.setItem('gis_hidden_points', JSON.stringify(next));
      } catch {}
      return next;
    });
  };

  const togglePolygonVisibility = (id: string) => {
    setHiddenPolygonIds((prev) => {
      const next = prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id];
      try {
        localStorage.setItem('gis_hidden_polygons', JSON.stringify(next));
      } catch {}
      return next;
    });
  };

  const toggleRouteVisibility = (id: string) => {
    setHiddenRouteIds((prev) => {
      const next = prev.includes(id) ? prev.filter((r) => r !== id) : [...prev, id];
      try {
        localStorage.setItem('gis_hidden_routes', JSON.stringify(next));
      } catch {}
      return next;
    });
  };

  const togglePatternVisibility = (id: string) => {
    setHiddenPatternIds((prev) => {
      const next = prev.includes(id) ? prev.filter((p) => p !== id) : [...prev, id];
      try {
        localStorage.setItem('gis_hidden_patterns', JSON.stringify(next));
      } catch {}
      return next;
    });
  };

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

  const isRouteFormEnabledRef = useRef(isRouteFormEnabled);
  isRouteFormEnabledRef.current = isRouteFormEnabled;

  const routeMeasurementModeRef = useRef(routeMeasurementMode);
  routeMeasurementModeRef.current = routeMeasurementMode;

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
      if (!isRouteFormEnabledRef.current) {
        showToast('Klik tombol "+ Tambah / Mulai Ukur Jarak Baru" untuk mengaktifkan pengukuran di peta.');
        return;
      }
      const mode = routeMeasurementModeRef.current;
      const pts = measurePointsRef.current;

      if (mode === 'straight') {
        // Mode Jarak Lurus: Point A and Point B
        if (pts.length === 0) {
          const ptA = { lat: clickedLat, lng: clickedLng, label: 'Titik Asal (A)' };
          setMeasurePoints([ptA]);
          showToast('Titik Asal (A) ditentukan. Klik peta untuk menentukan Titik Tujuan (B).');
        } else {
          const ptA = pts[0];
          const ptB = { lat: clickedLat, lng: clickedLng, label: 'Titik Tujuan (B)' };
          setMeasurePoints([ptA, ptB]);
          const az = calculateAzimuthHeading(ptA.lat, ptA.lng, clickedLat, clickedLng);
          showToast(`Titik B ditentukan: Haluan ${az.azimuthFormatted}, Jarak ${az.distanceNm} NM`);
        }
      } else {
        // Mode Jalur Multi-Point
        const newPt: LatLngPoint = {
          lat: clickedLat,
          lng: clickedLng,
          label: `WP-${pts.length + 1}`,
        };
        setMeasurePoints([...pts, newPt]);
      }
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

    // Render each saved point with point name label on map
    savedPoints.forEach((point) => {
      if (point.id && hiddenPointIds.includes(point.id)) return;
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
  }, [savedPoints, hiddenPointIds, draggedPointId, isMapReady, isPointsDraggableUnlocked, editingPointId]);

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

  // 1C. RENDER TEMPORARY SEARCH RESULT PIN (Feature 1: Pulsing search marker on map)
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMapReady) return;

    if (searchMarkerRef.current) {
      removeAdvancedMarker(searchMarkerRef.current);
      searchMarkerRef.current = null;
    }

    if (searchPin) {
      const pinShortName = searchPin.name.split(',')[0];
      const contentHtml = `
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; pointer-events: auto; cursor: pointer;">
          <div style="position: absolute; width: 42px; height: 42px; border-radius: 50%; background: rgba(234, 88, 12, 0.25); border: 2px solid #ea580c; animation: ping 1.8s cubic-bezier(0, 0, 0.2, 1) infinite; pointer-events: none; top: -5px;"></div>
          <div style="width: 32px; height: 32px; border-radius: 50%; background: #ea580c; border: 2.5px solid #ffffff; box-shadow: 0 4px 14px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; color: #ffffff; z-index: 2;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
              <circle cx="12" cy="10" r="3"/>
            </svg>
          </div>
          <div style="margin-top: 4px; padding: 2px 8px; border-radius: 8px; background: rgba(15, 23, 42, 0.92); backdrop-filter: blur(4px); border: 1px solid rgba(234, 88, 12, 0.8); color: #ffffff; font-size: 11px; font-weight: 800; white-space: nowrap; box-shadow: 0 4px 12px rgba(0,0,0,0.35);">
            🔍 ${pinShortName}
          </div>
        </div>
      `;

      const marker = createAdvancedMapMarker(map, {
        position: { lat: searchPin.lat, lng: searchPin.lng },
        title: `Hasil Pencarian: ${searchPin.name} (${decimalToDms(searchPin.lat, searchPin.lng)})`,
        draggable: false,
        contentHtml,
        zIndex: 1200,
        onClick: () => {
          showToast(`Lokasi: ${searchPin.name}`, 'info');
        },
      });

      searchMarkerRef.current = marker;
    }
  }, [searchPin, isMapReady]);

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
      if (poly.id && hiddenPolygonIds.includes(poly.id)) return;

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
    hiddenPolygonIds,
    editingPolygonId,
    activeTab,
    isMapReady,
  ]);

  // 3. RENDER SEARCH PATTERNS (Draft & Saved patterns with draggable vertices and visibility toggle)
  useEffect(() => {
    const map = mapInstanceRef.current;
    if (!map || !isMapReady) return;

    if (searchPatternPolylineRef.current) {
      searchPatternPolylineRef.current.setMap(null);
      searchPatternPolylineRef.current = null;
    }
    savedPatternsPolylinesRef.current.forEach((p) => p.setMap(null));
    savedPatternsPolylinesRef.current = [];

    clearAdvancedMarkers(searchPatternMarkersRef.current);
    searchPatternMarkersRef.current = [];

    // 3A. Render all saved search patterns (inactive)
    savedSearchPatterns.forEach((pattern) => {
      if (pattern.id === editingPatternId) return; // Active edited pattern is handled below
      if (pattern.id && hiddenPatternIds.includes(pattern.id)) return;
      if (pattern.waypoints.length < 2) return;

      const poly = new google.maps.Polyline({
        path: pattern.waypoints.map((p) => ({ lat: p.lat, lng: p.lng })),
        strokeColor: pattern.strokeColor || '#0284c7',
        strokeOpacity: 0.85,
        strokeWeight: 3.5,
        geodesic: true,
        map,
      });

      savedPatternsPolylinesRef.current.push(poly);
    });

    // 3B. Render active draft/editing search pattern with draggable corner handles
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
            ? `Titik Awal Entry (S) - ${decimalToDms(wp.lat, wp.lng)} - Geser untuk menyesuaikan`
            : isEnd
            ? `Titik Akhir Exit (E) - ${decimalToDms(wp.lat, wp.lng)} - Geser untuk menyesuaikan`
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
  }, [
    patternWaypoints,
    savedSearchPatterns,
    editingPatternId,
    hiddenPatternIds,
    patternColor,
    isMapReady,
  ]);

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

    // 4A. Render all saved routes (inactive routes rendered with their individual saved styling)
    savedDistanceRoutes.forEach((route) => {
      if (route.id === activeRouteId) return; // Active route is rendered below with interactive handles
      if (route.id && hiddenRouteIds.includes(route.id)) return;
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
        const isPointA = index === 0;
        const isPointB = index === 1 && routeMeasurementMode === 'straight';
        const labelText =
          routeMeasurementMode === 'straight'
            ? isPointA
              ? 'A'
              : isPointB
              ? 'B'
              : String(index + 1)
            : String(index + 1);

        const contentHtml = createPolygonVertexSvgHtml(labelText, measureStrokeColor, false);

        const m = createAdvancedMapMarker(map, {
          position: { lat: pt.lat, lng: pt.lng },
          title: `${pt.label || `WP-${labelText}`} (${decimalToDms(pt.lat, pt.lng)}) - Geser untuk memindahkan`,
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
    hiddenRouteIds,
    routeMeasurementMode,
    measureStrokeColor,
    measureStrokeWeight,
    measureStrokeOpacity,
    activeTab,
    isMapReady,
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

  // Posko SAR & Closest Entry Point Analysis (Requirement 3: Custom Icon Posko & Entry/Exit calculation)
  const closestPoskoAnalysis = useMemo(() => {
    if (!selectedPolygonObj || selectedPolygonObj.points.length < 3) return null;
    return findClosestPoskoToPolygon(savedPoints, selectedPolygonObj.points);
  }, [savedPoints, selectedPolygonObj]);

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

  // SEARCH PATTERN GENERATION (Flow: User sets parameters first -> Validates -> Generates on Map -> Reviews -> Saves)
  const handleGenerateSearchPattern = (boundPolygon?: SavedPolygon) => {
    const targetPoly =
      boundPolygon || (selectedPolygonForPattern ? savedPolygons.find((p) => p.id === selectedPolygonForPattern) : undefined);

    if (selectedPolygonForPattern && !targetPoly) {
      showErrorAlert('Area Polygon Tidak Ditemukan', 'Polygon yang dipilih tidak valid. Silakan pilih polygon dari daftar atau gunakan mode datum bebas.');
      return;
    }

    if (targetPoly && targetPoly.points.length < 3) {
      showErrorAlert('Polygon Tidak Lengkap', `Polygon "${targetPoly.name}" memiliki kurang dari 3 titik. Minimal 3 titik diperlukan.`);
      return;
    }

    const spacingVal =
      typeof patternTrackSpacingNm === 'number'
        ? patternTrackSpacingNm
        : parseFloat(patternTrackSpacingNm as string);

    if (isNaN(spacingVal) || spacingVal <= 0) {
      showErrorAlert('Track Spacing Tidak Valid', 'Jarak lintasan (Track Spacing S) harus berupa angka positif lebih dari 0 NM (contoh: 0.5, 1.0, 2.0 NM).');
      return;
    }

    const legsVal =
      typeof patternLegsCount === 'number'
        ? patternLegsCount
        : parseInt(patternLegsCount as string, 10);

    if (isNaN(legsVal) || legsVal < 1) {
      showErrorAlert('Jumlah Lintasan Tidak Valid', 'Jumlah lintasan (legs) harus berupa angka bulat minimal 1.');
      return;
    }

    // Identify candidate Posko / Bases to determine closest Entry Point
    const poskoCandidates = savedPoints.filter(
      (p) =>
        p.pointType === 'posko_sar' ||
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

    const waypoints = generateSearchPatternWaypoints(
      patternType,
      { lat: patternDatumLat, lng: patternDatumLng },
      spacingVal,
      spacingVal * (legsVal / 2),
      targetPoly ? targetPoly.points : undefined,
      referencePosko ? { lat: referencePosko.lat, lng: referencePosko.lng } : undefined,
      legsVal
    );

    if (!waypoints || waypoints.length < 2) {
      showErrorAlert('Gagal Membuat Pola', 'Titik koordinat pola tidak mencukupi untuk dirender. Periksa kembali ukuran area dan parameter lintasan.');
      return;
    }

    setPatternWaypoints(waypoints);
    if (!editingPatternId && (!patternName || patternName.trim() === '')) {
      setPatternName(
        targetPoly
          ? `Pola ${patternType.toUpperCase()} - ${targetPoly.name}`
          : `Pola ${patternType.toUpperCase()} Sektor ${savedSearchPatterns.length + 1}`
      );
    }
    setActiveTab('search-pattern');

    if (mapInstanceRef.current && waypoints.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      waypoints.forEach((w) => bounds.extend(w));
      mapInstanceRef.current.fitBounds(bounds);
    }

    showSuccessAlert(
      'Pola Pencarian Berhasil Dibuat',
      `Lintasan ${patternType.toUpperCase()} (${legsVal} lintasan, Track Spacing S=${spacingVal} NM) telah digambar di peta. Anda dapat meninjau dan menggeser titik sudut sebelum mengklik tombol "Simpan ke Database".`
    );
  };

  const handleApplySearchPatternToPolygon = (poly: SavedPolygon) => {
    setSelectedPolygonForPattern(poly.id);
    setPatternWaypoints([]); // Clear previous pattern to enforce parameter setting flow
    setEditingPatternId(null);
    setPatternName(`Pola Lintasan SAR - ${poly.name}`);
    setActiveTab('search-pattern');
    showToast(
      `Area "${poly.name}" dipilih sebagai batas pola. Silakan tentukan parameter lalu klik "Generate Pola di Peta".`,
      'info'
    );
  };

  // SEARCH PATTERN CRUD (Requirement 2: Simpan, Tambah, Edit ke Database)
  const handleSaveSearchPattern = async () => {
    if (patternWaypoints.length < 2) {
      showErrorAlert('Pola Kosong', 'Silakan buat pola pencarian terlebih dahulu.');
      return;
    }

    const patternId = editingPatternId || `pattern_${Date.now()}`;
    const name = patternName.trim() || `Pola Lintasan SAR ${savedSearchPatterns.length + 1}`;
    const targetPoly = savedPolygons.find((p) => p.id === selectedPolygonForPattern);
    const targetPosko = savedPoints.find((p) => p.id === selectedPoskoForPattern);

    const patternMetrics = calculatePolylineDistance(patternWaypoints);

    const payload: SavedSearchPattern = {
      id: patternId,
      name,
      patternType,
      polygonId: selectedPolygonForPattern || undefined,
      polygonName: targetPoly ? targetPoly.name : undefined,
      poskoId: selectedPoskoForPattern !== 'auto' ? selectedPoskoForPattern : undefined,
      poskoName: targetPosko ? targetPosko.label : closestPoskoAnalysis?.closestPosko ? closestPoskoAnalysis.closestPosko.label : undefined,
      trackSpacingNm: Number(patternTrackSpacingNm) || 2.0,
      legsCount: Number(patternLegsCount) || 5,
      waypoints: patternWaypoints,
      strokeColor: patternColor,
      totalDistanceNm: patternMetrics.totalNm,
      totalDistanceKm: patternMetrics.totalKm,
      createdAt: new Date().toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
      }),
    };

    try {
      if (editingPatternId) {
        // Update existing pattern
        await fetch(`/api/gis/search-patterns/${editingPatternId}`, {
          method: 'PUT',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        setSavedSearchPatterns((prev) =>
          prev.map((p) => (p.id === editingPatternId ? payload : p))
        );
        showSuccessAlert('Pola Diperbarui', `Pola "${name}" berhasil disimpan kembali ke database.`);
      } else {
        // Create new pattern
        await fetch('/api/gis/search-patterns', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
        });
        setSavedSearchPatterns((prev) => [payload, ...prev]);
        showSuccessAlert('Pola Disimpan', `Pola "${name}" berhasil disimpan ke database.`);
      }
      setEditingPatternId(null);
    } catch (err: any) {
      console.error('Failed to save search pattern:', err);
      showErrorAlert('Gagal Menyimpan', err.message || 'Terjadi kesalahan saat menyimpan ke database.');
    }
  };

  const handleEditSavedSearchPattern = (pattern: SavedSearchPattern) => {
    setEditingPatternId(pattern.id);
    setPatternName(pattern.name);
    setPatternType(pattern.patternType);
    setPatternTrackSpacingNm(pattern.trackSpacingNm || 2.0);
    setPatternLegsCount(pattern.legsCount || 5);
    setPatternColor(pattern.strokeColor || '#0284c7');
    if (pattern.polygonId) setSelectedPolygonForPattern(pattern.polygonId);
    if (pattern.poskoId) setSelectedPoskoForPattern(pattern.poskoId);
    setPatternWaypoints(pattern.waypoints);
    setActiveTab('search-pattern');

    if (mapInstanceRef.current && pattern.waypoints.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      pattern.waypoints.forEach((w) => bounds.extend(w));
      mapInstanceRef.current.fitBounds(bounds);
    }
    showToast(`Mode Edit Pola: "${pattern.name}". Geser titik sudut di peta lalu klik Simpan Perubahan.`);
  };

  const handleDeleteSavedSearchPattern = async (id: string, name: string) => {
    const confirmed = await showConfirmDialog(
      'Hapus Pola Pencarian?',
      `Apakah Anda yakin ingin menghapus pola "${name}"?`
    );
    if (confirmed) {
      setSavedSearchPatterns((prev) => prev.filter((p) => p.id !== id));
      try {
        await fetch(`/api/gis/search-patterns/${id}`, { method: 'DELETE' });
      } catch (err) {
        console.error('Failed to delete search pattern:', err);
      }
      if (editingPatternId === id) {
        setEditingPatternId(null);
        setPatternWaypoints([]);
      }
      showToast(`Pola "${name}" telah dihapus.`);
    }
  };

  const handleFocusSearchPattern = (waypoints: LatLngPoint[]) => {
    if (!mapInstanceRef.current || waypoints.length === 0) return;
    const bounds = new google.maps.LatLngBounds();
    waypoints.forEach((w) => bounds.extend(w));
    mapInstanceRef.current.fitBounds(bounds);
  };

  // DISTANCE & AZIMUTH (HEADING) METRICS (Requirement 4: UI Pengukuran Jarak & Heading Azimuth)
  const distanceMetrics = useMemo(() => {
    return calculatePolylineDistance(measurePoints);
  }, [measurePoints]);

  const straightAzimuthMetrics = useMemo(() => {
    if (measurePoints.length >= 2) {
      const ptA = measurePoints[0];
      const ptB = measurePoints[1];
      return calculateAzimuthHeading(ptA.lat, ptA.lng, ptB.lat, ptB.lng);
    }
    return null;
  }, [measurePoints]);

  const estimatedMinutes = useMemo(() => {
    return distanceMetrics.totalNm > 0 && vesselSpeedKnots > 0
      ? (distanceMetrics.totalNm / vesselSpeedKnots) * 60
      : 0;
  }, [distanceMetrics, vesselSpeedKnots]);

  const handleStartNewRoute = () => {
    setIsRouteFormEnabled(true);
    setActiveRouteId(null);
    setActiveRouteName(`Rute Pengukuran ${savedDistanceRoutes.length + 1}`);
    setMeasurePoints([]);
    setMeasureStrokeColor('#059669');
    setMeasureStrokeWeight(3.5);
    setMeasureStrokeOpacity(0.9);
    showToast(
      routeMeasurementMode === 'straight'
        ? 'Mode Jarak Lurus: Klik Titik Asal (A) dan Titik Tujuan (B) pada peta.'
        : 'Mode Jalur: Klik pada peta untuk membuat titik-titik rute pengukuran.',
      'info'
    );
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
      mode: routeMeasurementMode,
      headingDeg: straightAzimuthMetrics ? straightAzimuthMetrics.azimuthDeg : undefined,
      headingFormatted: straightAzimuthMetrics ? straightAzimuthMetrics.azimuthFormatted : undefined,
      headingName: straightAzimuthMetrics ? straightAzimuthMetrics.compassDir : undefined,
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
    setIsRouteFormEnabled(true);
    setActiveRouteId(route.id);
    setActiveRouteName(route.name);
    setRouteMeasurementMode(route.mode || 'path');
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
    <div ref={mapWrapperRef} className="flex flex-col h-[calc(100vh-68px)] overflow-hidden font-sans bg-white dark:bg-slate-950">
      {/* TOP COMPACT BAR: Status, Search, Basemap & Action Controls */}
      <div
        className={`px-3 py-2 border-b flex items-center justify-between gap-2.5 text-xs shrink-0 ${
          isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
        }`}
      >
        {/* Left Section: Sidebar Toggle & App Title */}
        <div className="flex items-center gap-2 shrink-0">
          <button
            type="button"
            onClick={toggleSidebar}
            className={`p-1.5 rounded-xl border font-bold flex items-center gap-1.5 cursor-pointer transition-all ${
              isSidebarVisible
                ? 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border-inherit'
                : 'bg-orange-600 hover:bg-orange-500 text-white border-orange-500 shadow-md shadow-orange-600/20'
            }`}
            title={isSidebarVisible ? 'Sembunyikan Panel Tools Plotting' : 'Tampilkan Panel Tools Plotting'}
          >
            {isSidebarVisible ? <PanelLeftClose className="w-4 h-4" /> : <PanelLeftOpen className="w-4 h-4" />}
            <span className="hidden sm:inline text-[11px]">
              {isSidebarVisible ? 'Sembunyikan Tools' : 'Buka Tools Plotting'}
            </span>
          </button>

          <div className="hidden xl:flex items-center gap-2">
            <div className="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse" />
            <span className="font-extrabold tracking-wide text-slate-800 dark:text-slate-200">
              GIS RADAR OPERASI SAR KENDARI
            </span>
          </div>
        </div>

        {/* Center Section: Location Search Bar */}
        <div className="flex-1 max-w-sm sm:max-w-md mx-1">
          <LocationSearchBar onSelectLocation={handleSelectLocation} isDarkMode={isDarkMode} />
        </div>

        {/* Right Section: Basemap Switcher & Operational Actions */}
        <div className="flex items-center gap-1.5 sm:gap-2 shrink-0">
          {/* Basemap Switcher */}
          <div className="hidden sm:flex items-center gap-0.5 bg-slate-100 dark:bg-slate-950 p-0.5 rounded-xl border border-inherit">
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

          {/* Cetak Peta Button */}
          <button
            type="button"
            onClick={() => setIsPrintModalOpen(true)}
            className="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-950 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold flex items-center gap-1.5 border border-inherit transition-colors cursor-pointer"
            title="Cetak Lembar Peta Operasi SAR Resmi"
          >
            <Printer className="w-3.5 h-3.5 text-orange-500" />
            <span className="hidden md:inline text-[11px]">Cetak Peta</span>
          </button>

          {/* Ekspor KMZ/KML Dropdown Menu */}
          <div className="relative">
            <button
              type="button"
              onClick={() => setIsExportMenuOpen((prev) => !prev)}
              className="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-950 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 font-bold flex items-center gap-1 border border-inherit transition-colors cursor-pointer"
              title="Ekspor Layer GIS ke KML / KMZ / GeoJSON"
            >
              <FileDown className="w-3.5 h-3.5 text-emerald-500" />
              <span className="hidden md:inline text-[11px]">Ekspor KMZ/KML</span>
              <ChevronDown className="w-3 h-3 text-slate-400" />
            </button>

            {isExportMenuOpen && (
              <div className="absolute right-0 top-full mt-1.5 w-60 rounded-2xl border shadow-2xl bg-white dark:bg-slate-900 border-slate-200 dark:border-slate-800 p-1.5 z-50 flex flex-col gap-1 text-xs">
                <div className="px-2.5 py-1 text-[10px] font-bold text-slate-400 uppercase border-b border-inherit">
                  Pilih Format Ekspor Spasial:
                </div>
                <button
                  type="button"
                  onClick={handleExportKml}
                  className="w-full text-left px-2.5 py-2 rounded-xl hover:bg-orange-50 dark:hover:bg-slate-800 flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer"
                >
                  <Download className="w-4 h-4 text-orange-500 shrink-0" />
                  <div>
                    <span className="block text-xs">Google Earth KML (.kml)</span>
                    <span className="text-[10px] text-slate-400 font-normal">Format standar OGC KML 2.2</span>
                  </div>
                </button>
                <button
                  type="button"
                  onClick={handleExportKmz}
                  className="w-full text-left px-2.5 py-2 rounded-xl hover:bg-emerald-50 dark:hover:bg-slate-800 flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer"
                >
                  <Download className="w-4 h-4 text-emerald-500 shrink-0" />
                  <div>
                    <span className="block text-xs">Google Earth KMZ (.kmz)</span>
                    <span className="text-[10px] text-slate-400 font-normal">Arsip KML terkompresi ZIP</span>
                  </div>
                </button>
                <button
                  type="button"
                  onClick={handleExportGeoJson}
                  className="w-full text-left px-2.5 py-2 rounded-xl hover:bg-sky-50 dark:hover:bg-slate-800 flex items-center gap-2 font-bold text-slate-700 dark:text-slate-200 cursor-pointer"
                >
                  <Download className="w-4 h-4 text-sky-500 shrink-0" />
                  <div>
                    <span className="block text-xs">Universal GeoJSON (.geojson)</span>
                    <span className="text-[10px] text-slate-400 font-normal">Kompatibel QGIS, ArcGIS, ATAK</span>
                  </div>
                </button>
              </div>
            )}
          </div>

          {/* Fullscreen Toggle */}
          <button
            type="button"
            onClick={toggleFullscreen}
            className="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-950 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200 border border-inherit transition-colors cursor-pointer"
            title={isFullscreen ? 'Keluar dari Fullscreen (ESC)' : 'Layar Penuh / Maximize Peta'}
          >
            {isFullscreen ? <Minimize2 className="w-4 h-4 text-orange-500" /> : <Maximize2 className="w-4 h-4 text-orange-500" />}
          </button>
        </div>
      </div>

      {/* MAIN GIS SPLIT VIEW */}
      <div className="flex-1 flex overflow-hidden relative">
        {/* LEFT PANEL: Controls, Points, Polygon, Patterns */}
        <div
          className={`${
            isSidebarVisible ? 'w-96 md:w-[420px]' : 'w-0 hidden'
          } border-r flex flex-col shrink-0 shadow-lg z-10 transition-all duration-200 ${
            isDarkMode ? 'bg-slate-950 border-slate-800' : 'bg-slate-50 border-slate-200'
          }`}
        >
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
                        <div className="flex items-center gap-1.5">
                          <span>DAFTAR TITIK TERSIMPAN</span>
                          <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300">
                            {savedPoints.length}
                          </span>
                        </div>

                        {/* Lock / Unlock Draggable Points for precise safety */}
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
                          className={`px-2 py-1 rounded-lg text-[10px] font-bold flex items-center gap-1 border transition-all cursor-pointer ${
                            isPointsDraggableUnlocked
                              ? 'bg-amber-600 hover:bg-amber-500 text-white border-amber-400 animate-pulse shadow-xs'
                              : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700'
                          }`}
                          title={
                            isPointsDraggableUnlocked
                              ? 'Klik untuk mengunci posisi marker'
                              : 'Klik untuk mengizinkan geser marker (Edit Posisi Titik)'
                          }
                        >
                          {isPointsDraggableUnlocked ? (
                            <>
                              <Unlock className="w-3 h-3 text-white" />
                              <span>Geser: AKTIF</span>
                            </>
                          ) : (
                            <>
                              <Lock className="w-3 h-3 text-slate-400" />
                              <span>Posisi: TERKUNCI</span>
                            </>
                          )}
                        </button>
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
                                  className="p-1.5 rounded-lg hover:bg-slate-500/10 text-slate-400 hover:text-orange-500 cursor-pointer transition-colors"
                                  title="Fokus pada Peta"
                                >
                                  <Crosshair className="w-3.5 h-3.5" />
                                </button>
                                <button
                                  type="button"
                                  onClick={() => pt.id && togglePointVisibility(pt.id)}
                                  className={`p-1.5 rounded-lg hover:bg-slate-500/10 cursor-pointer transition-colors ${
                                    pt.id && hiddenPointIds.includes(pt.id)
                                      ? 'text-slate-400 dark:text-slate-600 hover:text-orange-500'
                                      : 'text-orange-500 hover:text-orange-600'
                                  }`}
                                  title={
                                    pt.id && hiddenPointIds.includes(pt.id)
                                      ? 'Tampilkan titik di peta'
                                      : 'Sembunyikan titik dari peta'
                                  }
                                >
                                  {pt.id && hiddenPointIds.includes(pt.id) ? (
                                    <EyeOff className="w-3.5 h-3.5" />
                                  ) : (
                                    <Eye className="w-3.5 h-3.5" />
                                  )}
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
                hiddenPolygonIds={hiddenPolygonIds}
                onToggleVisibility={togglePolygonVisibility}
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
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-black uppercase tracking-wider text-sky-500 flex items-center gap-1.5">
                      <Sparkles className="w-4 h-4" />
                      Parameter Pola Pencarian SAR (IAMSAR)
                    </span>
                    {selectedPolygonForPattern && (
                      <span className="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                        Area Polygon Aktif
                      </span>
                    )}
                  </div>

                  {/* LANGKAH 1: PILIH JENIS POLA */}
                  <div className="space-y-1.5">
                    <label className="text-[10px] text-slate-400 font-bold flex items-center gap-1">
                      <span className="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] flex items-center justify-center font-bold">1</span>
                      <span>PILIH TIPE POLA SAR:</span>
                    </label>
                    <div className="grid grid-cols-2 gap-2 text-xs">
                      {[
                        {
                          type: 'parallel_track' as const,
                          label: 'Parallel Track (PS)',
                          desc: 'Sapuan sejajar sumbu utama',
                        },
                        {
                          type: 'creeping_line' as const,
                          label: 'Creeping Line (CS)',
                          desc: 'Tegak lurus koridor sempit',
                        },
                        {
                          type: 'expanding_square' as const,
                          label: 'Expanding Square (SS)',
                          desc: 'Spiral keluar dari titik LKP',
                        },
                        {
                          type: 'sector_search' as const,
                          label: 'Sector Search (VS)',
                          desc: 'Pola 3 sektor segitiga radial',
                        },
                        {
                          type: 'e_sar' as const,
                          label: 'E-SAR Hutan & Rimba',
                          desc: 'Sapu darat interval rapat',
                        },
                      ].map((item) => (
                        <button
                          key={item.type}
                          type="button"
                          onClick={() => setPatternType(item.type)}
                          className={`p-2 rounded-xl border text-left transition-all cursor-pointer ${
                            patternType === item.type
                              ? 'border-sky-500 bg-sky-500/10 text-sky-600 dark:text-sky-400 font-bold ring-1 ring-sky-500'
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
                  </div>

                  {/* LANGKAH 2: BATAS AREA (POLYGON / DATUM BEBAS) */}
                  <div className="space-y-1.5">
                    <label className="text-[10px] text-slate-400 font-bold flex items-center gap-1">
                      <span className="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] flex items-center justify-center font-bold">2</span>
                      <span>BATAS AREA PENCARIAN:</span>
                    </label>
                    <select
                      value={selectedPolygonForPattern}
                      onChange={(e) => setSelectedPolygonForPattern(e.target.value)}
                      className={`w-full px-2.5 py-2 rounded-xl border text-xs font-semibold cursor-pointer ${
                        isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    >
                      <option value="">Gunakan Datum Center (Area Bebas)</option>
                      {savedPolygons.map((p) => (
                        <option key={p.id} value={p.id}>
                          Polygon: {p.name} ({p.points.length} Titik Sudut)
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Automatic Calculation Readout (Requirement 3: Automatic legs based on area dimensions & track spacing) */}
                  <div className="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-800 dark:text-amber-300 space-y-1">
                    <div className="font-bold flex items-center justify-between">
                      <span>Dimensi Area Sapuan:</span>
                      <span className="font-mono">{areaSweepDimensionNm.toFixed(1)} NM</span>
                    </div>
                    <div className="flex items-center justify-between text-[10px] text-slate-500 dark:text-slate-400">
                      <span>Kalkulasi Otomatis:</span>
                      <span className="font-mono">
                        {areaSweepDimensionNm.toFixed(1)} NM ÷ {patternTrackSpacingNm || 1} NM ={' '}
                        <strong className="text-amber-600 dark:text-amber-400">
                          {patternLegsCount} Lintasan
                        </strong>
                      </span>
                    </div>
                  </div>

                  {/* LANGKAH 3: POSKO SAR ACUAN (ENTRY POINT PRIORITY) */}
                  <div className="space-y-1.5">
                    <label className="text-[10px] text-slate-400 font-bold flex items-center justify-between">
                      <span className="flex items-center gap-1">
                        <span className="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] flex items-center justify-center font-bold">3</span>
                        <span>POSKO SAR / PANGKALAN ACUAN:</span>
                      </span>
                      <span className="text-[9px] text-sky-500 font-bold">Titik Awal (Entry Point)</span>
                    </label>
                    <select
                      value={selectedPoskoForPattern}
                      onChange={(e) => setSelectedPoskoForPattern(e.target.value)}
                      className={`w-full px-2.5 py-2 rounded-xl border text-xs font-semibold cursor-pointer ${
                        isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    >
                      <option value="auto">⚡ Otomatis (Cari Posko / Kansar Terdekat)</option>
                      {savedPoints
                        .filter(
                          (p) =>
                            p.pointType === 'posko_sar' ||
                            p.pointType === 'kantor' ||
                            p.pointType === 'pos_sar' ||
                            (p.label && p.label.toLowerCase().includes('pos')) ||
                            (p.label && p.label.toLowerCase().includes('kansar'))
                        )
                        .map((pos) => (
                          <option key={pos.id} value={pos.id}>
                            {pos.label || 'Posko SAR'} ({(pos.pointType || 'posko_sar').toUpperCase()})
                          </option>
                        ))}
                    </select>
                  </div>

                  {/* Nearest Posko & Entry/Exit Point Analysis Card (Requirement 3) */}
                  {closestPoskoAnalysis && closestPoskoAnalysis.closestPosko && (
                    <div className="p-2.5 rounded-xl bg-sky-500/10 border border-sky-500/20 text-[11px] space-y-1.5 text-sky-900 dark:text-sky-200">
                      <div className="flex items-center justify-between font-bold text-sky-600 dark:text-sky-400">
                        <span className="flex items-center gap-1">
                          <Compass className="w-3.5 h-3.5" />
                          Analisis Posko SAR & Entry Point:
                        </span>
                        <span className="font-mono text-[10px]">
                          {closestPoskoAnalysis.distanceNm} NM ({closestPoskoAnalysis.distanceKm} km)
                        </span>
                      </div>
                      <div className="grid grid-cols-2 gap-1 text-[10px]">
                        <div>
                          <span className="text-slate-400 block">Posko Terdekat:</span>
                          <span className="font-bold truncate block">{closestPoskoAnalysis.closestPosko.label}</span>
                        </div>
                        <div>
                          <span className="text-slate-400 block">Azimuth ke Entry:</span>
                          <span className="font-bold font-mono">
                            {closestPoskoAnalysis.bearing}° ({closestPoskoAnalysis.headingName})
                          </span>
                        </div>
                      </div>
                      {closestPoskoAnalysis.closestVertex && (
                        <div className="pt-1 border-t border-sky-500/20 flex items-center justify-between text-[10px]">
                          <span>Entry Corner: <strong>{decimalToDms(closestPoskoAnalysis.closestVertex.lat, closestPoskoAnalysis.closestVertex.lng)}</strong></span>
                        </div>
                      )}
                    </div>
                  )}

                  {/* LANGKAH 4: PARAMETER LINTASAN (TRACK SPACING & JUMLAH LEGS) */}
                  <div className="space-y-1.5">
                    <label className="text-[10px] text-slate-400 font-bold flex items-center gap-1">
                      <span className="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] flex items-center justify-center font-bold">4</span>
                      <span>JARAK LINTASAN & JUMLAH SAPUAN:</span>
                    </label>
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
                          Jumlah Lintasan (Legs):
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
                  </div>

                  {/* LANGKAH 5: IDENTITAS POLA (NAMA & WARNA) */}
                  <div className="space-y-2">
                    <label className="text-[10px] text-slate-400 font-bold flex items-center gap-1">
                      <span className="w-4 h-4 rounded-full bg-sky-500 text-white text-[9px] flex items-center justify-center font-bold">5</span>
                      <span>NAMA POLA & WARNA GARIS:</span>
                    </label>
                    <input
                      type="text"
                      value={patternName}
                      onChange={(e) => setPatternName(e.target.value)}
                      placeholder="Contoh: Pola Parallel Track Sektor A"
                      className={`w-full px-2.5 py-2 rounded-xl border text-xs font-semibold ${
                        isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                      }`}
                    />

                    <div className="flex items-center justify-between pt-1">
                      <span className="text-[10px] text-slate-400 font-semibold">Pilihan Warna Lintasan:</span>
                      <div className="flex items-center gap-1.5">
                        {['#0284c7', '#ea580c', '#059669', '#dc2626', '#9333ea'].map((c) => (
                          <button
                            key={c}
                            type="button"
                            onClick={() => setPatternColor(c)}
                            className={`w-5 h-5 rounded-full border-2 cursor-pointer ${
                              patternColor === c ? 'scale-120 border-white ring-2 ring-sky-500' : 'border-transparent'
                            }`}
                            style={{ backgroundColor: c }}
                          />
                        ))}
                      </div>
                    </div>
                  </div>

                  {/* ACTION BUTTON: GENERATE POLA DI PETA */}
                  <div className="pt-2">
                    <button
                      type="button"
                      onClick={() => handleGenerateSearchPattern()}
                      className="w-full py-2.5 px-4 rounded-xl bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-bold text-xs shadow-md shadow-sky-600/20 flex items-center justify-center gap-2 cursor-pointer transition-all"
                    >
                      <Sparkles className="w-4 h-4" />
                      <span>{patternWaypoints.length > 0 ? '✨ Generate Ulang Pola di Peta' : '✨ Generate Pola di Peta'}</span>
                    </button>
                  </div>
                </div>

                {/* Generated Pattern Readout (Requirement 2 & 4: corners manual adjustment without numbers) */}
                {patternWaypoints.length > 0 && (
                  <div
                    className={`p-3.5 rounded-2xl border space-y-2.5 ${
                      isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div className="flex items-center justify-between text-xs">
                      <span className="font-bold text-sky-500 flex items-center gap-1.5">
                        <Move className="w-3.5 h-3.5 animate-pulse" />
                        {editingPatternId ? 'Mode Edit Sudut' : 'Sudut Lintasan'} ({patternWaypoints.length} Titik)
                      </span>
                      <button
                        type="button"
                        onClick={() => {
                          setPatternWaypoints([]);
                          setEditingPatternId(null);
                        }}
                        className="text-red-500 hover:text-red-400 text-[11px] font-bold cursor-pointer"
                      >
                        Tutup Pola Aktif
                      </button>
                    </div>

                    <div className="p-2 rounded-xl bg-sky-500/10 text-sky-700 dark:text-sky-300 text-[10px] leading-tight">
                      💡 <strong>Penyesuaian Manual:</strong> Titik sudut pada peta tidak diberi nomor dan dapat langsung digeser di peta untuk menyesuaikan sudut dan bentuk lintasan secara presisi.
                    </div>

                    <div className="max-h-40 overflow-y-auto space-y-1.5 font-mono text-[11px] scrollbar-thin">
                      {patternWaypoints.map((wp, idx) => {
                        const isStart = idx === 0;
                        const isEnd = idx === patternWaypoints.length - 1;
                        const labelTitle = isStart
                          ? 'Titik Awal Entry (S)'
                          : isEnd
                          ? 'Titik Akhir Exit (E)'
                          : `Sudut Lintasan #${idx}`;

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

                    {/* Total Distance Metrics & Save Action */}
                    <div className="pt-2 border-t border-inherit space-y-2">
                      <div className="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-200">
                        <span>Total Panjang Lintasan:</span>
                        <span className="font-mono text-emerald-600 dark:text-emerald-400">
                          {calculatePolylineDistance(patternWaypoints).totalNm.toFixed(2)} NM ({calculatePolylineDistance(patternWaypoints).totalKm.toFixed(2)} km)
                        </span>
                      </div>

                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={handleSaveSearchPattern}
                          className="flex-1 py-2.5 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 flex items-center justify-center gap-1.5 cursor-pointer transition-all"
                        >
                          <Save className="w-3.5 h-3.5" />
                          <span>{editingPatternId ? 'Simpan Perubahan Pola' : 'Simpan Pola ke Database'}</span>
                        </button>
                        <button
                          type="button"
                          onClick={() => {
                            setPatternWaypoints([]);
                            setEditingPatternId(null);
                          }}
                          className="py-2.5 px-3 rounded-xl border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold text-xs cursor-pointer transition-all"
                        >
                          Reset
                        </button>
                      </div>
                    </div>
                  </div>
                )}

                {/* SAVED SEARCH PATTERNS LIST (Requirement 2: Simpan, Tambah, Edit CRUD) */}
                {savedSearchPatterns.length > 0 && (
                  <div
                    className={`p-4 rounded-2xl border space-y-2.5 ${
                      isDarkMode ? 'bg-slate-900 border-slate-800' : 'bg-white border-slate-200'
                    }`}
                  >
                    <div className="flex items-center justify-between text-xs font-bold text-slate-400">
                      <span>POLA PENCARIAN TERSIMPAN</span>
                      <span>{savedSearchPatterns.length} Pola</span>
                    </div>

                    <div className="space-y-2 max-h-56 overflow-y-auto scrollbar-thin">
                      {savedSearchPatterns.map((pattern) => {
                        const isCurrentlyEditing = editingPatternId === pattern.id;
                        const isHidden = hiddenPatternIds.includes(pattern.id);

                        return (
                          <div
                            key={pattern.id}
                            className={`p-3 rounded-xl border space-y-2 transition-all ${
                              isCurrentlyEditing
                                ? 'border-sky-500 bg-sky-500/5 ring-1 ring-sky-500'
                                : isDarkMode
                                ? 'bg-slate-950 border-slate-800'
                                : 'bg-slate-50 border-slate-200'
                            }`}
                          >
                            <div className="flex items-center justify-between gap-2">
                              <div className="flex items-center gap-2 min-w-0">
                                <span
                                  className="w-3 h-3 rounded-full shrink-0"
                                  style={{ backgroundColor: pattern.strokeColor || '#0284c7' }}
                                />
                                <div className="truncate">
                                  <h4 className="font-bold text-xs text-slate-800 dark:text-slate-200 truncate">
                                    {pattern.name}
                                  </h4>
                                  <span className="text-[10px] text-slate-400 block">
                                    {pattern.polygonName ? `Polygon: ${pattern.polygonName} • ` : ''}
                                    {pattern.waypoints.length} Titik • {pattern.totalDistanceNm?.toFixed(1) || 0} NM
                                  </span>
                                </div>
                              </div>

                              <div className="flex items-center gap-1 shrink-0">
                                {/* Focus */}
                                <button
                                  type="button"
                                  onClick={() => handleFocusSearchPattern(pattern.waypoints)}
                                  className="p-1.5 rounded-lg hover:bg-slate-500/10 text-slate-400 hover:text-sky-500 cursor-pointer"
                                  title="Fokus pada Peta"
                                >
                                  <Crosshair className="w-3.5 h-3.5" />
                                </button>
                                {/* Visibility Toggle */}
                                <button
                                  type="button"
                                  onClick={() => togglePatternVisibility(pattern.id)}
                                  className={`p-1.5 rounded-lg hover:bg-slate-500/10 cursor-pointer ${
                                    isHidden
                                      ? 'text-slate-400 dark:text-slate-600 hover:text-sky-500'
                                      : 'text-sky-500 hover:text-sky-600'
                                  }`}
                                  title={isHidden ? 'Tampilkan pola di peta' : 'Sembunyikan pola dari peta'}
                                >
                                  {isHidden ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                                </button>
                                {/* Edit */}
                                <button
                                  type="button"
                                  onClick={() => handleEditSavedSearchPattern(pattern)}
                                  className="p-1.5 rounded-lg hover:bg-slate-500/10 text-slate-400 hover:text-sky-500 cursor-pointer"
                                  title="Edit pola (bisa ditarik sudutnya)"
                                >
                                  <Edit3 className="w-3.5 h-3.5" />
                                </button>
                                {/* Delete */}
                                <button
                                  type="button"
                                  onClick={() => handleDeleteSavedSearchPattern(pattern.id, pattern.name)}
                                  className="p-1.5 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-500/10 cursor-pointer"
                                  title="Hapus pola"
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

            {/* 4. TAB: MEASURE DISTANCE & ETA (Requirement 4: UI Pengukuran Jarak & Heading/Azimuth) */}
            {activeTab === 'measure-distance' && (
              <div className="space-y-3.5">
                <div
                  className={`p-4 rounded-2xl border space-y-3.5 ${
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
                      className="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs flex items-center gap-1.5 shadow-md shadow-emerald-600/20 cursor-pointer transition-all"
                    >
                      <Plus className="w-3.5 h-3.5" />
                      <span>+ Tambah / Mulai Ukur Jarak Baru</span>
                    </button>
                  </div>

                  {!isRouteFormEnabled ? (
                    <div className="p-3.5 rounded-xl bg-slate-100 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 text-center space-y-2">
                      <p className="text-xs font-semibold text-slate-600 dark:text-slate-300">
                        Form input pengukuran dinonaktifkan.
                      </p>
                      <p className="text-[11px] text-slate-400">
                        Klik tombol <strong>"+ Tambah / Mulai Ukur Jarak Baru"</strong> di atas untuk mengaktifkan input form dan mulai memetakan titik di peta.
                      </p>
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {/* Mode Selector: Jarak Lurus vs Jalur (Requirement 4) */}
                      <div>
                        <label className="text-[10px] text-slate-400 font-semibold block mb-1">
                          Mode Pengukuran:
                        </label>
                        <div className="grid grid-cols-2 gap-2">
                          <button
                            type="button"
                            onClick={() => {
                              setRouteMeasurementMode('straight');
                              if (measurePoints.length > 2) {
                                setMeasurePoints(measurePoints.slice(0, 2));
                              }
                            }}
                            className={`p-2 rounded-xl border text-left cursor-pointer transition-all ${
                              routeMeasurementMode === 'straight'
                                ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold ring-1 ring-emerald-500'
                                : isDarkMode
                                ? 'border-slate-800 bg-slate-950 text-slate-300'
                                : 'border-slate-200 bg-slate-50 text-slate-700'
                            }`}
                          >
                            <div className="flex items-center gap-1.5">
                              <Compass className="w-3.5 h-3.5" />
                              <span className="text-xs font-bold">Jarak Lurus (P-to-P)</span>
                            </div>
                            <span className="text-[10px] text-slate-400 block mt-0.5">
                              Kalkulasi Azimuth & Heading
                            </span>
                          </button>

                          <button
                            type="button"
                            onClick={() => setRouteMeasurementMode('path')}
                            className={`p-2 rounded-xl border text-left cursor-pointer transition-all ${
                              routeMeasurementMode === 'path'
                                ? 'border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 font-bold ring-1 ring-emerald-500'
                                : isDarkMode
                                ? 'border-slate-800 bg-slate-950 text-slate-300'
                                : 'border-slate-200 bg-slate-50 text-slate-700'
                            }`}
                          >
                            <div className="flex items-center gap-1.5">
                              <Navigation className="w-3.5 h-3.5" />
                              <span className="text-xs font-bold">Jalur (Multi-point)</span>
                            </div>
                            <span className="text-[10px] text-slate-400 block mt-0.5">
                              Rute berliku banyak waypoint
                            </span>
                          </button>
                        </div>
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
                          placeholder="Contoh: Jarak Kantor SAR ke Titik LKP"
                          className={`w-full px-2.5 py-1.5 rounded-xl border text-xs font-semibold ${
                            isDarkMode ? 'bg-slate-950 border-slate-700' : 'bg-slate-50 border-slate-300'
                          }`}
                        />
                      </div>

                      {/* HEADING / AZIMUTH METRICS PANEL FOR STRAIGHT DISTANCE (Requirement 4) */}
                      {routeMeasurementMode === 'straight' && straightAzimuthMetrics && (
                        <div className="p-3 rounded-xl bg-gradient-to-br from-emerald-500/10 to-sky-500/10 border border-emerald-500/30 space-y-2 text-emerald-950 dark:text-emerald-100">
                          <div className="flex items-center justify-between border-b border-emerald-500/20 pb-1.5">
                            <span className="font-bold text-xs flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                              <Compass className="w-4 h-4 animate-spin-slow" />
                              Hasil Kalkulasi Heading / Azimuth:
                            </span>
                            <span className="text-[10px] font-mono font-bold px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-700 dark:text-emerald-300">
                              A → B
                            </span>
                          </div>

                          <div className="grid grid-cols-2 gap-2 text-xs">
                            <div className="p-2 rounded-lg bg-white/70 dark:bg-slate-900/70 border border-emerald-500/20">
                              <span className="text-[10px] text-slate-400 block font-semibold">Heading / Azimuth (Haluan)</span>
                              <span className="text-sm font-black text-emerald-600 dark:text-emerald-400 font-mono">
                                {straightAzimuthMetrics.azimuthFormatted}
                              </span>
                              <span className="text-[10px] text-slate-500 block">
                                {straightAzimuthMetrics.compassDir} ({straightAzimuthMetrics.compassAbbr})
                              </span>
                            </div>

                            <div className="p-2 rounded-lg bg-white/70 dark:bg-slate-900/70 border border-emerald-500/20">
                              <span className="text-[10px] text-slate-400 block font-semibold">Reciprocal (Haluan Balik)</span>
                              <span className="text-sm font-black text-sky-600 dark:text-sky-400 font-mono">
                                {straightAzimuthMetrics.reciprocalFormatted}
                              </span>
                              <span className="text-[10px] text-slate-500 block font-mono">
                                B → A
                              </span>
                            </div>
                          </div>

                          <div className="grid grid-cols-3 gap-1.5 text-center text-xs pt-1">
                            <div className="p-1.5 rounded-lg bg-white/50 dark:bg-slate-900/50">
                              <span className="text-[9px] text-slate-400 block">Nautical Miles</span>
                              <span className="font-bold font-mono text-xs">{straightAzimuthMetrics.distanceNm} NM</span>
                            </div>
                            <div className="p-1.5 rounded-lg bg-white/50 dark:bg-slate-900/50">
                              <span className="text-[9px] text-slate-400 block">Kilometer</span>
                              <span className="font-bold font-mono text-xs">{straightAzimuthMetrics.distanceKm} km</span>
                            </div>
                            <div className="p-1.5 rounded-lg bg-white/50 dark:bg-slate-900/50">
                              <span className="text-[9px] text-slate-400 block">Meter</span>
                              <span className="font-bold font-mono text-xs">{straightAzimuthMetrics.distanceMeters.toLocaleString('id-ID')} m</span>
                            </div>
                          </div>
                        </div>
                      )}

                      {/* Instruction helper */}
                      <div className="p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-[11px] text-emerald-700 dark:text-emerald-300">
                        {routeMeasurementMode === 'straight'
                          ? '👉 Klik 2 titik di atas peta (Titik A dan Titik B). Azimuth/Heading dan jarak akurat akan otomatis dihitung.'
                          : '👉 Klik pada peta untuk menambah titik-titik rute pengukuran (bisa diklik di dalam area polygon). Titik rute dapat langsung digeser di peta!'}
                      </div>

                      {/* STYLING CUSTOMIZATION: Color, Stroke Weight, Opacity */}
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

                      {/* Metrics Readout */}
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
                          {measurePoints.map((pt, idx) => {
                            const isPointA = idx === 0;
                            const isPointB = idx === 1 && routeMeasurementMode === 'straight';
                            const label =
                              routeMeasurementMode === 'straight'
                                ? isPointA
                                  ? 'Titik Awal (A)'
                                  : isPointB
                                  ? 'Titik Tujuan (B)'
                                  : `WP-${idx + 1}`
                                : `WP-${idx + 1}`;

                            return (
                              <div
                                key={idx}
                                className="p-1.5 rounded-lg border flex items-center justify-between bg-white dark:bg-slate-950 border-inherit"
                              >
                                <span className="font-bold text-slate-500">{label}</span>
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
                            );
                          })}
                        </div>
                      </div>
                    </div>
                  )}
                </div>

                {/* SAVED ROUTES LIBRARY (Requirement 1 & 4: Manage & switch saved routes with Eye Visibility) */}
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
                        const isHidden = hiddenRouteIds.includes(route.id);
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
                                    {route.mode === 'straight' && route.headingFormatted
                                      ? `Haluan: ${route.headingFormatted} • `
                                      : ''}
                                    {route.points.length} Titik • {routeMetrics.totalNm} NM • ETA:{' '}
                                    {formatDurationIndonesian(routeEtaMinutes)}
                                  </span>
                                </div>
                              </div>

                              <div className="flex items-center gap-1 shrink-0">
                                {/* Visibility Toggle */}
                                <button
                                  type="button"
                                  onClick={() => toggleRouteVisibility(route.id)}
                                  className={`p-1.5 rounded-lg hover:bg-slate-500/10 cursor-pointer ${
                                    isHidden
                                      ? 'text-slate-400 dark:text-slate-600 hover:text-emerald-500'
                                      : 'text-emerald-500 hover:text-emerald-600'
                                  }`}
                                  title={isHidden ? 'Tampilkan rute di peta' : 'Sembunyikan rute dari peta'}
                                >
                                  {isHidden ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                                </button>
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
        <div className="flex-1 relative bg-slate-900 overflow-hidden">
          <div ref={mapElementRef} className="w-full h-full" />

          {/* Floating Sidebar Toggle Button on Map Canvas (Shown when sidebar is closed) */}
          {!isSidebarVisible && (
            <div className="absolute top-4 left-4 z-10">
              <button
                type="button"
                onClick={toggleSidebar}
                className="px-3.5 py-2 rounded-2xl bg-orange-600 hover:bg-orange-500 text-white font-bold text-xs shadow-xl shadow-orange-600/30 flex items-center gap-2 cursor-pointer transition-all hover:scale-105 border border-orange-400"
              >
                <PanelLeftOpen className="w-4 h-4" />
                <span>Buka Tools Plotting</span>
              </button>
            </div>
          )}

          {/* Temporary Search Result Pin Action Card */}
          {searchPin && (
            <div className="absolute top-4 left-4 right-4 sm:left-auto sm:right-6 sm:max-w-md bg-white/95 dark:bg-slate-900/95 backdrop-blur-md rounded-2xl p-3.5 shadow-2xl border border-orange-500/50 z-20 flex flex-col gap-2.5 animate-in fade-in slide-in-from-top-4 duration-200">
              <div className="flex items-start justify-between gap-2">
                <div className="flex items-center gap-2.5 min-w-0">
                  <div className="w-8 h-8 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400 flex items-center justify-center font-bold shrink-0">
                    <MapPin className="w-4 h-4" />
                  </div>
                  <div className="min-w-0">
                    <h4 className="font-extrabold text-xs text-slate-800 dark:text-slate-100 truncate">
                      {searchPin.name}
                    </h4>
                    <span className="text-[10px] text-orange-600 dark:text-orange-400 font-bold uppercase">
                      {searchPin.type}
                    </span>
                  </div>
                </div>
                <button
                  type="button"
                  onClick={() => setSearchPin(null)}
                  className="p-1 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                  title="Tutup Pin"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>

              <div className="flex items-center justify-between text-[11px] font-mono bg-slate-100 dark:bg-slate-950 px-2.5 py-1.5 rounded-xl border border-inherit">
                <span className="text-slate-500">Koordinat:</span>
                <span className="font-bold text-slate-800 dark:text-slate-200">
                  {decimalToDms(searchPin.lat, searchPin.lng)}
                </span>
              </div>

              <div className="flex items-center gap-2 pt-0.5">
                <button
                  type="button"
                  onClick={handleConvertSearchPinToPoint}
                  className="flex-1 py-1.5 px-3 rounded-xl bg-gradient-to-r from-orange-600 to-amber-600 hover:from-orange-500 hover:to-amber-500 text-white font-bold text-xs flex items-center justify-center gap-1.5 shadow-md shadow-orange-600/20 cursor-pointer transition-all"
                >
                  <Plus className="w-3.5 h-3.5" />
                  <span>Jadikan Titik SAR</span>
                </button>
                <button
                  type="button"
                  onClick={() => setSearchPin(null)}
                  className="py-1.5 px-3 rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-300 dark:hover:bg-slate-700 font-bold text-xs cursor-pointer"
                >
                  Hapus Pin
                </button>
              </div>
            </div>
          )}

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

          {/* Quick Map Floating Controls (Bottom-Right) */}
          <div className="absolute bottom-6 right-6 flex flex-col gap-2 z-10">
            {/* Fullscreen Toggle */}
            <button
              type="button"
              onClick={toggleFullscreen}
              className="p-2.5 rounded-xl bg-white/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-200 shadow-md hover:bg-white dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition-all cursor-pointer"
              title={isFullscreen ? 'Keluar dari Fullscreen (ESC)' : 'Layar Penuh / Maximize Peta'}
            >
              {isFullscreen ? <Minimize2 className="w-5 h-5 text-orange-500" /> : <Maximize2 className="w-5 h-5 text-orange-500" />}
            </button>

            {/* Quick Print Button */}
            <button
              type="button"
              onClick={() => setIsPrintModalOpen(true)}
              className="p-2.5 rounded-xl bg-white/90 dark:bg-slate-900/90 text-slate-700 dark:text-slate-200 shadow-md hover:bg-white dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800 transition-all cursor-pointer"
              title="Cetak Lembar Peta Operasi"
            >
              <Printer className="w-5 h-5 text-orange-500" />
            </button>

            {/* Reset to Kansar Center */}
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

      {/* PRINT MAP MODAL */}
      <PrintMapModal
        isOpen={isPrintModalOpen}
        onClose={() => setIsPrintModalOpen(false)}
        savedPoints={savedPoints}
        savedPolygons={savedPolygons}
        savedSearchPatterns={savedSearchPatterns}
        savedDistanceRoutes={savedDistanceRoutes}
        centerCoords={centerCoords}
        basemapType={basemapType}
      />
    </div>
  );
};
