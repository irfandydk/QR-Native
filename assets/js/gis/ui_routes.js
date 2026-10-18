/**
 * QR-SAR - Tactical GIS: Route Leg & Vessel ETA Controller
 * KPP Kendari - Basarnas
 * Part of QRSAR.GIS Architecture Decomposition
 */

(function () {
  'use strict';

  window.QRSAR = window.QRSAR || {};
  window.QRSAR.GIS = window.QRSAR.GIS || {};
  window.QrSarGis = window.QrSarGis || {};

  const Routes = {
    /**
     * Standard SAR speed presets
     */
    speedPresets: [
      { name: 'Kapal SAR Utama (KN SAR Pacitan)', speed: 25.0, unit: 'knots' },
      { name: 'Rigid Inflatable Boat (RIB 12 M)', speed: 30.0, unit: 'knots' },
      { name: 'Rigid Inflatable Boat (RIB 09 M)', speed: 25.0, unit: 'knots' },
      { name: 'Rubber Boat / Perahu Karet 40 PK', speed: 12.0, unit: 'knots' },
      { name: 'Rescue Car / Truk Personel', speed: 60.0, unit: 'kmh' },
      { name: 'Helikopter SAR (Dauphin AS365)', speed: 130.0, unit: 'knots' }
    ],

    /**
     * Calculate route total distance and leg bearings
     */
    computeRouteMetrics: function (waypoints, vesselSpeedKnots = 25.0) {
      if (!Array.isArray(waypoints) || waypoints.length < 2) {
        return {
          totalNm: 0,
          totalKm: 0,
          initialBearing: 0,
          etaMinutes: 0,
          legs: []
        };
      }

      let totalKm = 0;
      const legs = [];

      for (let i = 0; i < waypoints.length - 1; i++) {
        const p1 = waypoints[i];
        const p2 = waypoints[i + 1];

        // Haversine
        const R = 6371;
        const dLat = ((p2.lat - p1.lat) * Math.PI) / 180;
        const dLon = ((p2.lng - p1.lng) * Math.PI) / 180;
        const a =
          Math.sin(dLat / 2) * Math.sin(dLat / 2) +
          Math.cos((p1.lat * Math.PI) / 180) *
          Math.cos((p2.lat * Math.PI) / 180) *
          Math.sin(dLon / 2) *
          Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const legKm = R * c;
        const legNm = legKm * 0.539957;

        // Bearing
        const y = Math.sin(dLon) * Math.cos((p2.lat * Math.PI) / 180);
        const x =
          Math.cos((p1.lat * Math.PI) / 180) * Math.sin((p2.lat * Math.PI) / 180) -
          Math.sin((p1.lat * Math.PI) / 180) * Math.cos((p2.lat * Math.PI) / 180) * Math.cos(dLon);
        let brng = (Math.atan2(y, x) * 180) / Math.PI;
        brng = (brng + 360) % 360;

        legs.push({
          fromIndex: i,
          toIndex: i + 1,
          distanceNm: Math.round(legNm * 100) / 100,
          distanceKm: Math.round(legKm * 100) / 100,
          bearing: Math.round(brng)
        });

        totalKm += legKm;
      }

      const totalNm = totalKm * 0.539957;
      const initialBearing = legs.length > 0 ? legs[0].bearing : 0;
      const speed = Math.max(0.1, parseFloat(vesselSpeedKnots) || 25.0);
      const etaMinutes = Math.round((totalNm / speed) * 60);

      return {
        totalNm: Math.round(totalNm * 100) / 100,
        totalKm: Math.round(totalKm * 100) / 100,
        initialBearing: initialBearing,
        etaMinutes: etaMinutes,
        legs: legs
      };
    },

    /**
     * Format duration into readable Indonesian text
     */
    formatDuration: function (minutes) {
      if (!minutes || minutes <= 0) return '0 Menit';
      const m = Math.round(minutes);
      const hours = Math.floor(m / 60);
      const remainingMin = m % 60;
      if (hours === 0) return `${remainingMin} Menit`;
      if (remainingMin === 0) return `${hours} Jam`;
      return `${hours} Jam ${remainingMin} Menit`;
    }
  };

  window.QRSAR.GIS.Routes = Routes;
  window.QrSarGis.Routes = Routes;
})();
