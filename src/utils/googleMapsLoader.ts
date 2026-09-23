import { setOptions, importLibrary } from '@googlemaps/js-api-loader';

// Helper to reliably load Google Maps JavaScript API SDK in browser using @googlemaps/js-api-loader
let loaderPromise: Promise<typeof google> | null = null;
let isConfigured = false;

export async function loadGoogleMaps(apiKey?: string): Promise<typeof google> {
  if (typeof window === 'undefined') {
    throw new Error('Google Maps can only be loaded in browser environment');
  }

  // If already loaded and marker library with AdvancedMarkerElement is ready
  if ((window as any).google?.maps?.marker?.AdvancedMarkerElement && (window as any).google?.maps?.Map) {
    return (window as any).google;
  }

  if (loaderPromise) {
    return loaderPromise;
  }

  loaderPromise = (async () => {
    try {
      let key =
        apiKey ||
        (typeof window !== 'undefined' ? localStorage.getItem('sar_custom_gmaps_api_key') : null) ||
        'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc' ||
        (import.meta as any).env?.VITE_GOOGLE_MAPS_API_KEY;

      // If needed, check server configuration
      if (!key || key.startsWith('AIzaSyDemo')) {
        try {
          const res = await fetch('/api/config/maps', { cache: 'no-store' });
          const data = await res.json();
          if (data.success && data.apiKey) {
            key = data.apiKey;
          }
        } catch {
          // fallback
        }
      }

      if (!isConfigured) {
        setOptions({
          key: key || 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc',
          v: 'weekly',
          libraries: ['maps', 'marker', 'places', 'geometry'],
          solutionChannel: 'gmp_mcp_codeassist_v1_aistudio',
        });
        isConfigured = true;
      }

      // Explicitly import maps and marker libraries
      await Promise.all([
        importLibrary('maps'),
        importLibrary('marker'),
        importLibrary('geometry'),
      ]);

      return (window as any).google;
    } catch (err) {
      loaderPromise = null;
      console.error('Failed to initialize Google Maps via @googlemaps/js-api-loader:', err);
      throw err;
    }
  })();

  return loaderPromise;
}

export { importLibrary, setOptions };

