/**
 * QR-SAR - Tactical GIS: Location Search & Maritime Geocoding Controller
 * KPP Kendari - Basarnas
 * Part of QRSAR.GIS Architecture Decomposition
 */

(function () {
  'use strict';

  window.QRSAR = window.QRSAR || {};
  window.QRSAR.GIS = window.QRSAR.GIS || {};
  window.QrSarGis = window.QrSarGis || {};

  const Search = {
    /**
     * Verified local maritime hotspots & strategic SAR base stations in Southeast Sulawesi
     */
    localGazetteer: [
      { name: 'Kantor Pencarian dan Pertolongan (KPP) Kendari', lat: -4.037664, lng: 122.486662, category: 'Base Kansar', desc: 'Pusat Komando Operasi SAR Sultra' },
      { name: 'Dermaga Utama Basarnas Kendari', lat: -3.976387, lng: 122.588475, category: 'Dermaga SAR', desc: 'Pangkalan Kapal KN SAR Pacitan & RIB' },
      { name: 'Pos SAR Baubau', lat: -5.460773, lng: 122.597656, category: 'Pos SAR', desc: 'Wilayah Perairan Buton & Selat Tiworo' },
      { name: 'Pos SAR Wakatobi (Tomia / Wangi-Wangi)', lat: -5.318889, lng: 123.593611, category: 'Pos SAR', desc: 'Wilayah Kepulauan Wakatobi & Laut Banda' },
      { name: 'Pos SAR Kolaka', lat: -4.055833, lng: 121.602778, category: 'Pos SAR', desc: 'Teluk Bone & Perairan Kolaka' },
      { name: 'Unit Siaga SAR Konawe Utara', lat: -3.516667, lng: 122.183333, category: 'Unit Siaga', desc: 'Perairan Labengki & Teluk Lasolo' },
      { name: 'Unit Siaga SAR Muna (Raha)', lat: -4.845000, lng: 122.730000, category: 'Unit Siaga', desc: 'Selat Muna & Selat Buton' },
      { name: 'Unit Siaga SAR Luwu Timur', lat: -2.570000, lng: 121.170000, category: 'Unit Siaga', desc: 'Danau Towuti & Matano' },
      { name: 'Pelabuhan Bungkutoko Kendari', lat: -3.985000, lng: 122.610000, category: 'Pelabuhan', desc: 'Pelabuhan Samudera Kendari' },
      { name: 'Pelabuhan Nusantara Kendari', lat: -3.965000, lng: 122.585000, category: 'Pelabuhan', desc: 'Terminal Penumpang Kapal Cepat' },
      { name: 'Pelabuhan Feri Torobulu (Konawe Selatan)', lat: -4.483333, lng: 122.566667, category: 'Pelabuhan Feri', desc: 'Penyeberangan Lintas Torobulu - Tampo' },
      { name: 'Pelabuhan Feri Tampo (Muna)', lat: -4.566667, lng: 122.633333, category: 'Pelabuhan Feri', desc: 'Penyeberangan Selat Tiworo' },
      { name: 'Perairan Teluk Kendari', lat: -3.970000, lng: 122.560000, category: 'Perairan Maritim', desc: 'Teluk Kendari & Muara Sungai Wanggu' },
      { name: 'Perairan Selat Buton', lat: -5.150000, lng: 122.750000, category: 'Perairan Maritim', desc: 'Alur Pelayaran Selat Buton' },
      { name: 'Perairan Selat Tiworo', lat: -4.750000, lng: 122.350000, category: 'Perairan Maritim', desc: 'Gugusan Pulau Tiworo & Selat Muna' },
      { name: 'Perairan Laut Banda (Sultra)', lat: -4.500000, lng: 123.500000, category: 'Zona Maritim Lepas', desc: 'Area Samudera Laut Banda Timur Sultra' }
    ],

    /**
     * Search coordinates or location query locally & remotely
     */
    searchLocation: async function (query) {
      if (!query || typeof query !== 'string') return [];
      const q = query.trim();
      if (!q) return [];

      const results = [];

      // 1. Try Geodesy coordinate parsing first (DMS / DD)
      if (window.QrSarGis && window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.parseCoordinatesString === 'function') {
        const parsed = window.QrSarGis.Geodesy.parseCoordinatesString(q);
        if (parsed && typeof parsed.lat === 'number' && typeof parsed.lng === 'number' && !isNaN(parsed.lat) && !isNaN(parsed.lng)) {
          results.push({
            name: `📍 Koordinat: ${q}`,
            lat: parsed.lat,
            lng: parsed.lng,
            category: 'Input Koordinat',
            desc: 'Posisi Geografis Tepat',
            isExactCoordinate: true
          });
        }
      }

      // 2. Filter local gazetteer
      const qLower = q.toLowerCase();
      const localMatches = Search.localGazetteer.filter(function (item) {
        return item.name.toLowerCase().includes(qLower) ||
               item.desc.toLowerCase().includes(qLower) ||
               item.category.toLowerCase().includes(qLower);
      });

      localMatches.forEach(function (m) {
        results.push(m);
      });

      // 3. Fallback online query if local results < 3 and query >= 3 chars
      if (results.length < 5 && q.length >= 3) {
        try {
          const fetchUrl = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q + ' Sulawesi Tenggara')}&limit=4&addressdetails=1`;
          const resp = await fetch(fetchUrl, {
            headers: { 'Accept-Language': 'id-ID,id;q=0.9,en;q=0.8' }
          });
          if (resp.ok) {
            const data = await resp.json();
            if (Array.isArray(data)) {
              data.forEach(function (item) {
                const lat = parseFloat(item.lat);
                const lon = parseFloat(item.lon);
                if (!isNaN(lat) && !isNaN(lon)) {
                  results.push({
                    name: item.display_name.split(',')[0],
                    lat: lat,
                    lng: lon,
                    category: item.type ? item.type.toUpperCase() : 'Online',
                    desc: item.display_name
                  });
                }
              });
            }
          }
        } catch (e) {
          // Gracefully fallback to local matches on network error / offline mode
        }
      }

      return results;
    }
  };

  window.QRSAR.GIS.Search = Search;
  window.QrSarGis.Search = Search;
})();
