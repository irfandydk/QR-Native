import React, { useState, useEffect, useRef } from 'react';
import { Search, MapPin, X, Loader2, Navigation, Compass, Plus, CornerDownLeft } from 'lucide-react';
import { decimalToDms, dmsToDecimal } from '../../utils/geoUtils';

export interface LocationSearchResult {
  name: string;
  lat: number;
  lng: number;
  type: string;
  source: 'coordinate' | 'preset' | 'geocoder';
}

interface LocationSearchBarProps {
  onSelectLocation: (result: LocationSearchResult) => void;
  isDarkMode: boolean;
}

// Preset locations for quick SAR access in Sultra & surrounding maritime areas
const SULTRA_PRESETS: LocationSearchResult[] = [
  { name: 'Kantor Pencarian & Pertolongan Kendari (Kansar)', lat: -4.015, lng: 122.585, type: 'Kansar Pusat', source: 'preset' },
  { name: 'Pelabuhan Nusantara Kendari', lat: -3.9782, lng: 122.5934, type: 'Pelabuhan', source: 'preset' },
  { name: 'Teluk Kendari & Jembatan Bahteramas', lat: -3.985, lng: 122.571, type: 'Perairan Teluk', source: 'preset' },
  { name: 'Pos SAR Wakatobi (Wangi-Wangi)', lat: -5.321, lng: 123.593, type: 'Pos SAR', source: 'preset' },
  { name: 'Pos SAR Baubau (Buton)', lat: -5.465, lng: 122.612, type: 'Pos SAR', source: 'preset' },
  { name: 'Pos SAR Kolaka', lat: -4.053, lng: 121.597, type: 'Pos SAR', source: 'preset' },
  { name: 'Unit Siaga SAR Konawe Utara', lat: -3.456, lng: 122.182, type: 'Unit Siaga', source: 'preset' },
  { name: 'Unit Siaga SAR Muna (Raha)', lat: -4.845, lng: 122.721, type: 'Unit Siaga', source: 'preset' },
  { name: 'Unit Siaga SAR Luwu Timur / Sorowako', lat: -2.551, lng: 121.352, type: 'Unit Siaga', source: 'preset' },
  { name: 'Pulau Labengki (Sombori)', lat: -3.441, lng: 122.482, type: 'Kepulauan / Wisata', source: 'preset' },
  { name: 'Selat Tiworo (Muna Barat)', lat: -4.752, lng: 122.381, type: 'Selat / Maritim', source: 'preset' },
  { name: 'Pelabuhan Penyeberangan Torobulu', lat: -4.431, lng: 122.613, type: 'Pelabuhan Ferry', source: 'preset' },
  { name: 'Pelabuhan Penyeberangan Tampo (Muna)', lat: -4.542, lng: 122.631, type: 'Pelabuhan Ferry', source: 'preset' },
  { name: 'Perairan Menui (Morowali / Sultra)', lat: -3.382, lng: 123.212, type: 'Perairan Lepas', source: 'preset' },
];

export const LocationSearchBar: React.FC<LocationSearchBarProps> = ({
  onSelectLocation,
  isDarkMode,
}) => {
  const [query, setQuery] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [results, setResults] = useState<LocationSearchResult[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  // Close dropdown on click outside
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setIsOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Try parsing coordinates from query (Decimal or DMS)
  const tryParseCoordinates = (text: string): LocationSearchResult | null => {
    const trimmed = text.trim();
    if (!trimmed) return null;

    // Pattern 1: Decimal (e.g., "-3.985, 122.571" or "-3.985 122.571")
    const decRegex = /^([+-]?\d+(?:\.\d+)?)[,\s]+([+-]?\d+(?:\.\d+)?)$/;
    const decMatch = trimmed.match(decRegex);
    if (decMatch) {
      const lat = parseFloat(decMatch[1]);
      const lng = parseFloat(decMatch[2]);
      if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
        return {
          name: `Koordinat: ${decimalToDms(lat, lng)}`,
          lat,
          lng,
          type: 'Koordinat Desimal',
          source: 'coordinate',
        };
      }
    }

    // Pattern 2: DMS (e.g. 03° 59' 24" S, 122° 30' 36" E)
    if (trimmed.includes('°') || trimmed.includes("'") || /[NSEW]/i.test(trimmed)) {
      try {
        const parts = trimmed.split(/[,;\/]+/);
        if (parts.length === 2) {
          const lat = dmsToDecimal(parts[0].trim());
          const lng = dmsToDecimal(parts[1].trim());
          if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
            return {
              name: `Koordinat DMS: ${decimalToDms(lat, lng)}`,
              lat,
              lng,
              type: 'Koordinat DMS',
              source: 'coordinate',
            };
          }
        }
      } catch {}
    }

    return null;
  };

  // Search logic (debounced)
  useEffect(() => {
    if (!query.trim()) {
      setResults(SULTRA_PRESETS.slice(0, 6));
      return;
    }

    // Check if input is a direct coordinate
    const parsedCoord = tryParseCoordinates(query);
    if (parsedCoord) {
      setResults([parsedCoord]);
      setIsOpen(true);
      return;
    }

    // Filter local presets
    const qLower = query.toLowerCase();
    const matchedPresets = SULTRA_PRESETS.filter(
      (p) => p.name.toLowerCase().includes(qLower) || p.type.toLowerCase().includes(qLower)
    );

    const timer = setTimeout(async () => {
      setIsLoading(true);
      try {
        // Fetch from OpenStreetMap Nominatim with viewbox prioritized to Indonesia / Sulawesi
        const res = await fetch(
          `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(
            query
          )}&countrycodes=id&limit=6&addressdetails=1`
        );
        if (res.ok) {
          const data = await res.json();
          const geoResults: LocationSearchResult[] = data.map((item: any) => ({
            name: item.display_name,
            lat: parseFloat(item.lat),
            lng: parseFloat(item.lon),
            type: item.type || item.class || 'Lokasi',
            source: 'geocoder' as const,
          }));

          const combined = [...matchedPresets, ...geoResults];
          // Remove duplicates
          const unique = combined.filter(
            (val, idx, self) =>
              idx === self.findIndex((t) => Math.abs(t.lat - val.lat) < 0.001 && Math.abs(t.lng - val.lng) < 0.001)
          );
          setResults(unique);
        } else {
          setResults(matchedPresets);
        }
      } catch {
        setResults(matchedPresets);
      } finally {
        setIsLoading(false);
      }
    }, 350);

    return () => clearTimeout(timer);
  }, [query]);

  const handleSelect = (item: LocationSearchResult) => {
    setQuery(item.name.split(',')[0]);
    setIsOpen(false);
    onSelectLocation(item);
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && results.length > 0) {
      handleSelect(results[0]);
    }
  };

  return (
    <div ref={containerRef} className="relative w-72 sm:w-84 md:w-96 z-30">
      <div
        className={`flex items-center gap-2 px-3 py-1.5 rounded-2xl border shadow-lg backdrop-blur-md transition-all ${
          isDarkMode
            ? 'bg-slate-900/90 border-slate-700/80 text-white focus-within:border-orange-500 focus-within:ring-1 focus-within:ring-orange-500'
            : 'bg-white/95 border-slate-300/80 text-slate-800 focus-within:border-orange-500 focus-within:ring-1 focus-within:ring-orange-500'
        }`}
      >
        <Search className="w-4 h-4 text-orange-500 shrink-0" />
        <input
          type="text"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          onKeyDown={handleKeyDown}
          placeholder="Cari lokasi, pulau, atau koordinat..."
          className="flex-1 bg-transparent text-xs outline-hidden placeholder:text-slate-400 font-medium"
        />
        {isLoading && <Loader2 className="w-3.5 h-3.5 text-orange-500 animate-spin shrink-0" />}
        {query && !isLoading && (
          <button
            type="button"
            onClick={() => {
              setQuery('');
              setResults(SULTRA_PRESETS.slice(0, 6));
            }}
            className="p-1 rounded-full hover:bg-slate-200 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
          >
            <X className="w-3 h-3" />
          </button>
        )}
      </div>

      {/* DROPDOWN RESULTS */}
      {isOpen && (
        <div
          className={`absolute top-full left-0 right-0 mt-1.5 rounded-2xl border shadow-xl overflow-hidden backdrop-blur-md max-h-80 overflow-y-auto ${
            isDarkMode ? 'bg-slate-900/95 border-slate-700 divide-slate-800' : 'bg-white/98 border-slate-200 divide-slate-100'
          }`}
        >
          <div className="px-3 py-1.5 bg-slate-100 dark:bg-slate-950/80 border-b border-inherit flex items-center justify-between text-[10px] font-bold text-slate-400 uppercase">
            <span>{query ? 'Hasil Pencarian' : 'Pangkalan & Sektor SAR Cepat'}</span>
            <span>{results.length} Lokasi</span>
          </div>

          {results.length === 0 ? (
            <div className="p-4 text-center text-xs text-slate-400">
              {isLoading ? 'Sedang mencari data spasial...' : 'Tidak ada lokasi atau koordinat yang cocok.'}
            </div>
          ) : (
            <div className="divide-y divide-inherit">
              {results.map((item, idx) => (
                <button
                  key={idx}
                  type="button"
                  onClick={() => handleSelect(item)}
                  className={`w-full text-left px-3 py-2.5 flex items-start gap-2.5 transition-colors cursor-pointer ${
                    isDarkMode ? 'hover:bg-slate-800/80' : 'hover:bg-orange-50/60'
                  }`}
                >
                  <div className="p-1.5 rounded-xl bg-orange-500/10 text-orange-600 dark:text-orange-400 shrink-0 mt-0.5">
                    {item.source === 'coordinate' ? (
                      <Compass className="w-3.5 h-3.5" />
                    ) : (
                      <MapPin className="w-3.5 h-3.5" />
                    )}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">
                      {item.name}
                    </p>
                    <div className="flex items-center gap-2 mt-0.5 text-[10px] text-slate-500 dark:text-slate-400 font-mono">
                      <span className="font-semibold text-orange-600 dark:text-orange-400">
                        {item.type}
                      </span>
                      <span>•</span>
                      <span>{decimalToDms(item.lat, item.lng)}</span>
                    </div>
                  </div>
                  <CornerDownLeft className="w-3 h-3 text-slate-400 shrink-0 mt-1 opacity-60" />
                </button>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};
