import { setOptions, importLibrary } from '@googlemaps/js-api-loader';

// Helper to reliably load Google Maps JavaScript API SDK in browser using @googlemaps/js-api-loader
let loaderPromise: Promise<typeof google> | null = null;
let isConfigured = false;

export async function loadGoogleMaps(apiKey?: string): Promise<typeof google> {
  if (typeof window === 'undefined') {
    throw new Error('Google Maps can only be loaded in browser environment');
  }

  // If already loaded and marker library with AdvancedMarkerElement is ready
  if ((window as any).google?.maps?.marker?.AdvancedMarkerElement) {
    return (window as any).google;
  }

  if (loaderPromise) {
    return loaderPromise;
  }

  loaderPromise = (async () => {
    try {
      let key =
        apiKey ||
        (import.meta as any).env?.VITE_GOOGLE_MAPS_API_KEY ||
        'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc';

      // Try to fetch custom key from config if not provided
      if (!apiKey && !(import.meta as any).env?.VITE_GOOGLE_MAPS_API_KEY) {
        try {
          const res = await fetch('/api/config/maps');
          const data = await res.json();
          if (data.success && data.apiKey) {
            key = data.apiKey;
          }
        } catch {
          // use fallback key
        }
      }

      if (!isConfigured) {
        setOptions({
          key,
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

