import React, { useEffect, useRef, useState, useCallback } from 'react';
import {
  MapPin,
  Navigation,
  Search,
  Compass,
  Building2,
  RefreshCw,
  CheckCircle2,
  AlertCircle,
  Car,
  Anchor,
  Clock,
  Send,
  Sliders,
} from 'lucide-react';
import { SarUnit } from '../types';
import { useTheme } from '../context/ThemeContext';
import {
  decimalToDms,
  parseDmsToDecimal,
  calculateStraightDistance,
  formatDurationIndonesian,
  DEFAULT_SAR_UNITS,
} from '../utils/geoUtils';
import { loadGoogleMaps } from '../utils/googleMapsLoader';

export interface GoogleMapsLkpPickerProps {
  currentCoordinates?: string;
  currentLocationName?: string;
  selectedBaseId?: number;
  onSelectLkp: (data: {
    koordinatDms: string;
    latitude: number;
    longitude: number;
    locationName?: string;
    distanceKm: number;
    distanceNm: number;
    durationText: string;
    selectedBase: SarUnit;
    routeSource: 'google_route' | 'direct_calculation';
  }) => void;
  onBaseChange?: (baseId: number) => void;
  sarUnits?: SarUnit[];
}

export const GoogleMapsLkpPicker: React.FC<GoogleMapsLkpPickerProps> = ({
  currentCoordinates = '',
  currentLocationName = '',
  selectedBaseId: propSelectedBaseId,
  onSelectLkp,
  onBaseChange,
  sarUnits: propSarUnits,
}) => {
  const { isDarkMode, accentColors } = useTheme();

  const mapRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

  const [sarUnits, setSarUnits] = useState<SarUnit[]>(propSarUnits || DEFAULT_SAR_UNITS);
  const [selectedBaseId, setSelectedBaseId] = useState<number>(propSelectedBaseId || 1);
  const [selectedBase, setSelectedBase] = useState<SarUnit>(DEFAULT_SAR_UNITS[0]);

  // Current LKP position state (Official KPP Kendari initial default coordinates: -4.084194, 122.428389)
  const [lkpLat, setLkpLat] = useState<number>(-4.084194);
  const [lkpLng, setLkpLng] = useState<number>(122.428389);
  const [lkpDms, setLkpDms] = useState<string>(currentCoordinates || '04° 05\' 03" S - 122° 25\' 42" E');
  const [dmsInputValue, setDmsInputValue] = useState<string>(currentCoordinates || '04° 05\' 03" S - 122° 25\' 42" E');
  const [resolvedAddress, setResolvedAddress] = useState<string>(currentLocationName);

  // Map instances
  const googleMapInstance = useRef<google.maps.Map | null>(null);
  const lkpMarkerInstance = useRef<google.maps.Marker | null>(null);
  const baseMarkersRef = useRef<google.maps.Marker[]>([]);
  const routePolylineRef = useRef<google.maps.Polyline | null>(null);

  // Status & calculation states
  const [mapLoaded, setMapLoaded] = useState(false);
  const [mapError, setMapError] = useState<string | null>(null);
  const [isCalculatingRoute, setIsCalculatingRoute] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [tagSuccessToast, setTagSuccessToast] = useState<string | null>(null);
  const [routeInfo, setRouteInfo] = useState<{
    distanceKm: number;
    distanceNm: number;
    durationText: string;
    source: 'google_route' | 'direct_calculation';
  } | null>(null);

  // All bases comparison table with distances
  const [basesDistances, setBasesDistances] = useState<
    Array<{
      unit: SarUnit;
      distanceKm: number;
      distanceNm: number;
      durationText: string;
      isClosest: boolean;
    }>
  >([]);

  // Sync SAR units list
  useEffect(() => {
    if (propSarUnits && propSarUnits.length > 0) {
      setSarUnits(propSarUnits);
      const cur = propSarUnits.find((u) => u.id_unit === selectedBaseId) || propSarUnits[0];
      setSelectedBase(cur);
    } else {
      fetch('/api/sar-units')
        .then((res) => res.json())
        .then((res) => {
          if (res.success && Array.isArray(res.data) && res.data.length > 0) {
            setSarUnits(res.data);
            const cur = res.data.find((u: SarUnit) => u.id_unit === selectedBaseId) || res.data[0];
            setSelectedBase(cur);
          }
        })
        .catch(() => {
          setSarUnits(DEFAULT_SAR_UNITS);
        });
    }
  }, [propSarUnits]);

  // Sync external selectedBaseId from parent
  useEffect(() => {
    if (propSelectedBaseId !== undefined && propSelectedBaseId !== selectedBaseId) {
      setSelectedBaseId(propSelectedBaseId);
      const found = sarUnits.find((u) => u.id_unit === propSelectedBaseId);
      if (found) {
        setSelectedBase(found);
        if (mapLoaded) {
          calculateRoute(lkpLat, lkpLng, found);
        }
      }
    }
  }, [propSelectedBaseId, sarUnits, mapLoaded, lkpLat, lkpLng]);

  // Sync external currentCoordinates
  useEffect(() => {
    if (currentCoordinates && currentCoordinates !== lkpDms) {
      setDmsInputValue(currentCoordinates);
      const parsed = parseDmsToDecimal(currentCoordinates);
      if (parsed) {
        setLkpLat(parsed.lat);
        setLkpLng(parsed.lng);
        setLkpDms(currentCoordinates);
        if (googleMapInstance.current) {
          const latLng = new google.maps.LatLng(parsed.lat, parsed.lng);
          googleMapInstance.current.panTo(latLng);
          if (lkpMarkerInstance.current) {
            lkpMarkerInstance.current.setPosition(latLng);
          }
        }
      }
    }
  }, [currentCoordinates]);

  // Calculate distances for all bases to LKP
  const updateBasesDistances = useCallback(
    (targetLat: number, targetLng: number) => {
      const computed = sarUnits.map((unit) => {
        const straight = calculateStraightDistance(unit.latitude, unit.longitude, targetLat, targetLng);
        // Realistic road network distance calculation (approx 1.32x straight line for Sulawesi terrain)
        const roadKm = Math.round(straight.km * 1.32 * 10) / 10;
        const roadNm = Math.round(roadKm * 0.539957 * 10) / 10;
        const durationMins = Math.round((roadKm / 45) * 60);
        return {
          unit,
          distanceKm: roadKm,
          distanceNm: roadNm,
          durationText: formatDurationIndonesian(durationMins),
          isClosest: false,
        };
      });

      computed.sort((a, b) => a.distanceKm - b.distanceKm);
      if (computed.length > 0) {
        computed[0].isClosest = true;
      }

      setBasesDistances(computed);
    },
    [sarUnits]
  );

  // Compute Driving Route via server API & render polyline vector on map
  const calculateRoute = useCallback(
    async (targetLat: number, targetLng: number, base: SarUnit) => {
      setIsCalculatingRoute(true);

      // Update polyline on map immediately
      if (routePolylineRef.current) {
        routePolylineRef.current.setPath([
          { lat: base.latitude, lng: base.longitude },
          { lat: targetLat, lng: targetLng },
        ]);
      }

      try {
        const res = await fetch('/api/maps/calculate-route', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            originLat: base.latitude,
            originLng: base.longitude,
            destLat: targetLat,
            destLng: targetLng,
          }),
        });
        const data = await res.json();
        if (data.success) {
          const info = {
            distanceKm: data.distanceKm,
            distanceNm: data.distanceNm,
            durationText: data.durationText,
            source: data.source === 'google_directions' ? ('google_route' as const) : ('direct_calculation' as const),
          };
          setRouteInfo(info);
          onSelectLkp({
            koordinatDms: decimalToDms(targetLat, targetLng),
            latitude: targetLat,
            longitude: targetLng,
            locationName: resolvedAddress,
            distanceKm: data.distanceKm,
            distanceNm: data.distanceNm,
            durationText: data.durationText,
            selectedBase: base,
            routeSource: info.source,
          });
        } else {
          throw new Error('Fallback needed');
        }
      } catch {
        const straight = calculateStraightDistance(base.latitude, base.longitude, targetLat, targetLng);
        const roadKm = Math.round(straight.km * 1.32 * 10) / 10;
        const roadNm = Math.round(roadKm * 0.539957 * 10) / 10;
        const durationMins = Math.round((roadKm / 45) * 60);
        const durText = formatDurationIndonesian(durationMins);
        const fallbackInfo = {
          distanceKm: roadKm,
          distanceNm: roadNm,
          durationText: durText,
          source: 'direct_calculation' as const,
        };
        setRouteInfo(fallbackInfo);
        onSelectLkp({
          koordinatDms: decimalToDms(targetLat, targetLng),
          latitude: targetLat,
          longitude: targetLng,
          locationName: resolvedAddress,
          distanceKm: roadKm,
          distanceNm: roadNm,
          durationText: durText,
          selectedBase: base,
          routeSource: 'direct_calculation',
        });
      } finally {
        setIsCalculatingRoute(false);
      }
    },
    [onSelectLkp, resolvedAddress]
  );

  // Reverse Geocoding
  const reverseGeocode = useCallback(async (lat: number, lng: number) => {
    try {
      const res = await fetch('/api/maps/reverse-geocode', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng }),
      });
      const data = await res.json();
      if (data.success && data.address) {
        setResolvedAddress(data.address);
      }
    } catch {
      // ignore
    }
  }, []);

  // Handle position change from click or drag
  const handlePositionChanged = useCallback(
    (lat: number, lng: number, base: SarUnit) => {
      setLkpLat(lat);
      setLkpLng(lng);
      const dms = decimalToDms(lat, lng);
      setLkpDms(dms);
      setDmsInputValue(dms);
      updateBasesDistances(lat, lng);
      calculateRoute(lat, lng, base);
      reverseGeocode(lat, lng);
    },
    [calculateRoute, updateBasesDistances, reverseGeocode]
  );

  // Execute "Tag Map" button action
  const handleTagMap = useCallback(
    (customDms?: string) => {
      const targetStr = customDms || dmsInputValue || lkpDms;
      const parsed = parseDmsToDecimal(targetStr);
      if (!parsed) {
        setTagSuccessToast('Format koordinat DMS tidak valid. Contoh: 04° 05\' 03" S - 122° 25\' 42" E');
        setTimeout(() => setTagSuccessToast(null), 4000);
        return;
      }

      setLkpLat(parsed.lat);
      setLkpLng(parsed.lng);
      setLkpDms(targetStr);
      setDmsInputValue(targetStr);

      if (googleMapInstance.current) {
        const latLng = new google.maps.LatLng(parsed.lat, parsed.lng);
        googleMapInstance.current.setCenter(latLng);
        googleMapInstance.current.setZoom(12);
        if (lkpMarkerInstance.current) {
          lkpMarkerInstance.current.setPosition(latLng);
        }
      }

      handlePositionChanged(parsed.lat, parsed.lng, selectedBase);

      setTagSuccessToast(`✅ Titik LKP berhasil di-tag ke Google Maps: ${targetStr}`);
      setTimeout(() => setTagSuccessToast(null), 3500);
    },
    [dmsInputValue, lkpDms, selectedBase, handlePositionChanged]
  );

  // Initialize Google Maps
  useEffect(() => {
    let isMounted = true;

    async function initMap() {
      try {
        let apiKey = 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc';
        try {
          const cfgRes = await fetch('/api/config/maps');
          const cfgData = await cfgRes.json();
          if (cfgData.success && cfgData.apiKey) {
            apiKey = cfgData.apiKey;
          }
        } catch {
          // default key fallback
        }

        await loadGoogleMaps(apiKey);

        if (!isMounted || !mapRef.current) return;

        const initialCenter = { lat: lkpLat, lng: lkpLng };

        const map = new google.maps.Map(mapRef.current, {
          center: initialCenter,
          zoom: 11,
          mapTypeId: google.maps.MapTypeId.ROADMAP,
          mapTypeControl: true,
          mapTypeControlOptions: {
            style: google.maps.MapTypeControlStyle.DROPDOWN_MENU,
            position: google.maps.ControlPosition.TOP_RIGHT,
          },
          zoomControl: true,
          streetViewControl: false,
          fullscreenControl: true,
          gestureHandling: 'greedy',
        });

        googleMapInstance.current = map;

        // Polyline for Route visualization
        routePolylineRef.current = new google.maps.Polyline({
          map,
          path: [
            { lat: selectedBase.latitude, lng: selectedBase.longitude },
            { lat: initialCenter.lat, lng: initialCenter.lng },
          ],
          strokeColor: '#f97316', // Basarnas Orange
          strokeWeight: 4,
          strokeOpacity: 0.9,
        });

        // 1. Add LKP Target Marker (Red Animated Pin)
        const lkpMarker = new google.maps.Marker({
          position: initialCenter,
          map,
          draggable: true,
          title: 'Titik Duga LKP (Last Known Position)',
          icon: {
            path: google.maps.SymbolPath.BACKWARD_CLOSED_ARROW,
            scale: 6,
            fillColor: '#ef4444',
            fillOpacity: 1,
            strokeColor: '#ffffff',
            strokeWeight: 2,
          },
          animation: google.maps.Animation.DROP,
        });

        lkpMarkerInstance.current = lkpMarker;

        // Map Click Listener -> Move LKP Pin
        map.addListener('click', (e: google.maps.MapMouseEvent) => {
          if (e.latLng) {
            const lat = e.latLng.lat();
            const lng = e.latLng.lng();
            lkpMarker.setPosition(e.latLng);
            handlePositionChanged(lat, lng, selectedBase);
          }
        });

        // Marker Drag End Listener
        lkpMarker.addListener('dragend', (e: google.maps.MapMouseEvent) => {
          if (e.latLng) {
            const lat = e.latLng.lat();
            const lng = e.latLng.lng();
            handlePositionChanged(lat, lng, selectedBase);
          }
        });

        // 2. Render Markers for All SAR Bases
        sarUnits.forEach((unit) => {
          const isMainKantor = unit.jenis_unit === 'KANTOR_SAR';
          const isPos = unit.jenis_unit === 'POS_SAR';

          const marker = new google.maps.Marker({
            position: { lat: unit.latitude, lng: unit.longitude },
            map,
            title: `${unit.nama_unit} (${unit.wilayah})`,
            icon: {
              path: google.maps.SymbolPath.CIRCLE,
              scale: isMainKantor ? 9 : 7,
              fillColor: isMainKantor ? '#2563eb' : isPos ? '#059669' : '#d97706',
              fillOpacity: 0.95,
              strokeColor: '#ffffff',
              strokeWeight: 2,
            },
          });

          const infoWindow = new google.maps.InfoWindow({
            content: `
              <div style="font-family: sans-serif; padding: 6px; font-size: 12px; color: #0f172a; line-height: 1.4;">
                <b style="color: #ea580c; font-size: 13px;">${unit.nama_unit}</b><br/>
                <span style="font-weight: 600;">Wilayah: ${unit.wilayah}</span><br/>
                <span style="color: #475569; font-size: 11px;">Alamat: ${unit.alamat}</span><br/>
                <small style="color: #64748b; font-family: monospace;">DMS: ${unit.koordinat_dms}</small>
              </div>
            `,
          });

          marker.addListener('click', () => {
            infoWindow.open(map, marker);
            setSelectedBaseId(unit.id_unit);
            setSelectedBase(unit);
            if (onBaseChange) onBaseChange(unit.id_unit);
            calculateRoute(lkpLat, lkpLng, unit);
          });

          baseMarkersRef.current.push(marker);
        });

        // 3. Search Autocomplete for Places
        if (searchInputRef.current) {
          const autocomplete = new google.maps.places.Autocomplete(searchInputRef.current, {
            componentRestrictions: { country: 'id' },
            fields: ['geometry', 'name', 'formatted_address'],
          });

          autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();
            if (place.geometry && place.geometry.location) {
              const lat = place.geometry.location.lat();
              const lng = place.geometry.location.lng();
              map.setCenter(place.geometry.location);
              map.setZoom(13);
              lkpMarker.setPosition(place.geometry.location);
              setResolvedAddress(place.formatted_address || place.name || '');
              handlePositionChanged(lat, lng, selectedBase);
            }
          });
        }

        setMapLoaded(true);
        // Initial calculation
        handlePositionChanged(lkpLat, lkpLng, selectedBase);
      } catch (err: any) {
        console.error('Failed to load Google Maps:', err);
        setMapError(err.message || 'Gagal memuat Google Maps.');
      }
    }

    initMap();

    return () => {
      isMounted = false;
      baseMarkersRef.current.forEach((m) => m.setMap(null));
      baseMarkersRef.current = [];
    };
  }, []);

  // When selected base changes, recalculate route
  const handleSelectBase = (base: SarUnit) => {
    setSelectedBaseId(base.id_unit);
    setSelectedBase(base);
    if (onBaseChange) onBaseChange(base.id_unit);
    calculateRoute(lkpLat, lkpLng, base);
  };

  return (
    <div
      className={`border rounded-2xl p-4 sm:p-5 space-y-4 shadow-xl transition-colors ${
        isDarkMode ? 'bg-slate-900 border-slate-800 text-slate-100' : 'bg-white border-slate-200 text-slate-900'
      }`}
    >
      {/* Header / Title */}
      <div className="flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-slate-200 dark:border-slate-800">
        <div className="flex items-center gap-2.5">
          <div className={`p-2 rounded-xl text-white shadow-md bg-gradient-to-r ${accentColors.gradient}`}>
            <Compass className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h3 className={`text-sm sm:text-base font-bold uppercase tracking-wider ${isDarkMode ? 'text-white' : 'text-slate-900'}`}>
                Peta Google Maps & Penentuan Jarak Rute Jalan Raya
              </h3>
              <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-500 border border-emerald-500/30">
                Akurat & Terintegrasi
              </span>
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Ketik koordinat DMS lalu klik <b>Tag Map</b>, atau pilih tim keberangkatan untuk hitung rute jalan raya otomatis.
            </p>
          </div>
        </div>

        {/* Quick coordinates badge */}
        <div
          className={`px-3 py-1.5 rounded-xl border font-mono text-xs font-bold ${
            isDarkMode
              ? 'bg-slate-950 border-orange-500/30 text-orange-400'
              : 'bg-orange-50 border-orange-200 text-orange-600'
          }`}
        >
          📍 {lkpDms}
        </div>
      </div>

      {/* WORKFLOW STEP 1: DMS INPUT WITH TAG MAP BUTTON */}
      <div
        className={`p-3.5 rounded-xl border space-y-2 ${
          isDarkMode ? 'bg-slate-950/90 border-orange-500/30' : 'bg-orange-50/70 border-orange-200'
        }`}
      >
        <div className="flex items-center justify-between">
          <label className="text-xs font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
            <span className="flex items-center justify-center w-5 h-5 rounded-full bg-orange-500 text-white text-[11px] font-bold">
              1
            </span>
            <span>Masukkan Koordinat DMS & Tag ke Peta Maps:</span>
          </label>
          <span className="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
            Format: DD° MM' SS" S/N - DDD° MM' SS" E/W
          </span>
        </div>

        <div className="flex flex-col sm:flex-row gap-2">
          <div className="relative flex-1">
            <input
              type="text"
              value={dmsInputValue}
              onChange={(e) => setDmsInputValue(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault();
                  handleTagMap();
                }
              }}
              placeholder={'Contoh: 04° 05\' 03" S - 122° 25\' 42" E'}
              className={`w-full px-3.5 py-2.5 text-xs sm:text-sm font-mono font-bold tracking-wider rounded-xl border outline-none transition-all focus:ring-2 ${accentColors.ring} ${
                isDarkMode
                  ? 'bg-slate-900 border-slate-700 text-white focus:border-orange-500 placeholder-slate-600'
                  : 'bg-white border-slate-300 text-slate-900 focus:border-orange-500 shadow-xs placeholder-slate-400'
              }`}
            />
          </div>

          <button
            type="button"
            onClick={() => handleTagMap()}
            className={`inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gradient-to-r ${accentColors.gradient} hover:opacity-95 text-white rounded-xl text-xs sm:text-sm font-bold shadow-md shadow-orange-500/20 transition-all hover:scale-[1.02] active:scale-[0.98] shrink-0 cursor-pointer`}
          >
            <MapPin className="w-4 h-4" />
            <span>Tag Map</span>
          </button>
        </div>

        {tagSuccessToast && (
          <div className="text-xs font-semibold px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 flex items-center gap-2 animate-fadeIn">
            <CheckCircle2 className="w-4 h-4 shrink-0" />
            <span>{tagSuccessToast}</span>
          </div>
        )}
      </div>

      {/* Google Search Bar for Places */}
      <div className="relative">
        <input
          ref={searchInputRef}
          type="text"
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          placeholder="Cari lokasi kejadian, desa, pantai, pelabuhan, pulau di Sulawesi Tenggara..."
          className={`w-full pl-10 pr-4 py-2.5 text-xs sm:text-sm rounded-xl border outline-none transition-all focus:ring-2 ${accentColors.ring} ${
            isDarkMode
              ? 'bg-slate-950 border-slate-700 text-white placeholder-slate-500'
              : 'bg-slate-50 border-slate-300 text-slate-900 placeholder-slate-400'
          }`}
        />
        <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5 pointer-events-none" />
      </div>

      {/* Map Container */}
      <div className="relative rounded-2xl overflow-hidden border border-slate-200 dark:border-slate-800 shadow-inner">
        <div ref={mapRef} className="w-full h-80 sm:h-96 bg-slate-950" />

        {/* Overlay helper badges on map */}
        <div className="absolute top-3 left-3 z-10 flex flex-wrap gap-2 pointer-events-none">
          <div className="px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-sm text-white text-[11px] font-semibold border border-slate-700 shadow-md flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-red-500 animate-ping" />
            <span>Pin Merah: LKP Target</span>
          </div>
          <div className="px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-sm text-white text-[11px] font-semibold border border-slate-700 shadow-md flex items-center gap-1.5">
            <span className="w-2.5 h-2.5 rounded-full bg-blue-500" />
            <span>Pin Biru/Hijau: Pangkalan SAR</span>
          </div>
        </div>

        {isCalculatingRoute && (
          <div className="absolute bottom-3 left-3 z-10 px-3 py-1.5 rounded-xl bg-slate-900/90 backdrop-blur-sm text-orange-400 text-xs font-bold border border-orange-500/30 flex items-center gap-2 shadow-lg">
            <RefreshCw className="w-3.5 h-3.5 animate-spin text-orange-500" />
            <span>Mengkalkulasi rute jalan raya efektif...</span>
          </div>
        )}
      </div>

      {/* WORKFLOW STEP 2: SELECTED BASE & ROUTE CALCULATION DISPLAY */}
      <div
        className={`p-4 rounded-xl border space-y-3 ${
          isDarkMode ? 'bg-slate-950/70 border-slate-800' : 'bg-slate-50 border-slate-200'
        }`}
      >
        <div className="flex flex-wrap items-center justify-between gap-2">
          <div className="flex items-center gap-2">
            <span className="flex items-center justify-center w-5 h-5 rounded-full bg-orange-500 text-white text-[11px] font-bold">
              2
            </span>
            <Building2 className="w-4 h-4 text-orange-500" />
            <span className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
              Pangkalan Asal Pergerakan Tim:
            </span>
          </div>

          <div className="flex items-center gap-2">
            <select
              value={selectedBaseId}
              onChange={(e) => {
                const id = parseInt(e.target.value, 10);
                const found = sarUnits.find((u) => u.id_unit === id);
                if (found) handleSelectBase(found);
              }}
              className={`px-3 py-1.5 text-xs font-bold rounded-xl border outline-none cursor-pointer ${
                isDarkMode
                  ? 'bg-slate-900 border-slate-700 text-white'
                  : 'bg-white border-slate-300 text-slate-900'
              }`}
            >
              {sarUnits.map((u) => (
                <option key={u.id_unit} value={u.id_unit}>
                  {u.jenis_unit === 'KANTOR_SAR' ? '🏢 ' : u.jenis_unit === 'POS_SAR' ? '🏠 ' : '⚓ '}
                  {u.nama_unit} ({u.wilayah})
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Live Distance & ETA Stat Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
          {/* Jarak Rute Jalan Raya (Km) */}
          <div
            className={`p-3 rounded-xl border flex items-center justify-between ${
              isDarkMode
                ? 'bg-slate-900/80 border-orange-500/20 text-slate-100'
                : 'bg-white border-orange-200 text-slate-900 shadow-sm'
            }`}
          >
            <div>
              <div className="text-[11px] text-slate-400 font-semibold flex items-center gap-1">
                <Car className="w-3.5 h-3.5 text-orange-500" />
                <span>Jarak Rute Jalan Raya</span>
              </div>
              <div className="text-lg font-extrabold text-orange-500 mt-0.5">
                {routeInfo?.distanceKm || 0} <span className="text-xs font-bold text-slate-400">Km</span>
              </div>
            </div>
            <span className="px-2 py-1 rounded bg-orange-500/10 text-orange-500 text-[10px] font-bold">
              Rute Efektif
            </span>
          </div>

          {/* Jarak Mil Laut (Nm) */}
          <div
            className={`p-3 rounded-xl border flex items-center justify-between ${
              isDarkMode
                ? 'bg-slate-900/80 border-cyan-500/20 text-slate-100'
                : 'bg-white border-cyan-200 text-slate-900 shadow-sm'
            }`}
          >
            <div>
              <div className="text-[11px] text-slate-400 font-semibold flex items-center gap-1">
                <Anchor className="w-3.5 h-3.5 text-cyan-500" />
                <span>Jarak Konversi Laut</span>
              </div>
              <div className="text-lg font-extrabold text-cyan-500 mt-0.5">
                {routeInfo?.distanceNm || 0} <span className="text-xs font-bold text-slate-400">Nm</span>
              </div>
            </div>
            <span className="px-2 py-1 rounded bg-cyan-500/10 text-cyan-500 text-[10px] font-bold">
              Nautical Miles
            </span>
          </div>

          {/* Estimasi Waktu Tempuh */}
          <div
            className={`p-3 rounded-xl border flex items-center justify-between ${
              isDarkMode
                ? 'bg-slate-900/80 border-emerald-500/20 text-slate-100'
                : 'bg-white border-emerald-200 text-slate-900 shadow-sm'
            }`}
          >
            <div>
              <div className="text-[11px] text-slate-400 font-semibold flex items-center gap-1">
                <Clock className="w-3.5 h-3.5 text-emerald-500" />
                <span>Estimasi Waktu Tempuh</span>
              </div>
              <div className="text-sm font-extrabold text-emerald-500 mt-1">
                {routeInfo?.durationText || '1 Jam'}
              </div>
            </div>
            <span className="px-2 py-1 rounded bg-emerald-500/10 text-emerald-500 text-[10px] font-bold">
              ETA Tim
            </span>
          </div>
        </div>

        {/* All Bases Comparison List (Kantor, POS SAR, Unit Siaga) */}
        <div className="pt-2">
          <label className="block text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1.5 uppercase tracking-wider">
            Perbandingan Jarak Rute dari Semua Pangkalan SAR ke LKP:
          </label>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            {basesDistances.map((item) => {
              const isCurrent = item.unit.id_unit === selectedBaseId;
              return (
                <button
                  type="button"
                  key={item.unit.id_unit}
                  onClick={() => handleSelectBase(item.unit)}
                  className={`p-2.5 rounded-xl border text-left transition-all ${
                    isCurrent
                      ? 'bg-orange-500/15 border-orange-500 text-orange-500 shadow-sm ring-1 ring-orange-500'
                      : isDarkMode
                      ? 'bg-slate-900 border-slate-800 text-slate-300 hover:border-slate-700'
                      : 'bg-white border-slate-200 text-slate-700 hover:border-slate-300 shadow-xs'
                  }`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold truncate">{item.unit.nama_unit}</span>
                    {item.isClosest && (
                      <span className="text-[9px] px-1.5 py-0.2 rounded-full bg-emerald-500 text-white font-bold">
                        Terdekat
                      </span>
                    )}
                  </div>
                  <div className="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
                    <span>{item.distanceKm} Km / {item.distanceNm} Nm</span>
                    <span>⏱ {item.durationText}</span>
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
};
