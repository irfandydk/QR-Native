// Helper to reliably load Google Maps JavaScript API SDK in browser
let googleMapsPromise: Promise<typeof google> | null = null;

export async function loadGoogleMaps(apiKey?: string): Promise<typeof google> {
  if (typeof window === 'undefined') {
    throw new Error('Google Maps can only be loaded in browser environment');
  }

  // If already loaded
  if ((window as any).google && (window as any).google.maps) {
    return (window as any).google;
  }

  if (googleMapsPromise) {
    return googleMapsPromise;
  }

  googleMapsPromise = new Promise(async (resolve, reject) => {
    try {
      let key = apiKey || 'AIzaSyDoOh6pAKgDBgmtXsW7QBS2uW0UOBCAYHc';
      
      // Try to fetch custom key from config if not provided
      if (!apiKey) {
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

      // Check if script already on page
      const existingScript = document.querySelector('script[src*="maps.googleapis.com/maps/api/js"]');
      if (existingScript) {
        const interval = setInterval(() => {
          if ((window as any).google && (window as any).google.maps) {
            clearInterval(interval);
            resolve((window as any).google);
          }
        }, 100);
        return;
      }

      // Append Google Maps script tag
      const script = document.createElement('script');
      script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(
        key
      )}&libraries=places,geometry&v=weekly`;
      script.async = true;
      script.defer = true;

      script.onload = () => {
        if ((window as any).google && (window as any).google.maps) {
          resolve((window as any).google);
        } else {
          reject(new Error('Google Maps script loaded but google.maps is not available'));
        }
      };

      script.onerror = (e) => {
        reject(new Error('Failed to load Google Maps script from Google CDN'));
      };

      document.head.appendChild(script);
    } catch (err) {
      reject(err);
    }
  });

  return googleMapsPromise;
}
