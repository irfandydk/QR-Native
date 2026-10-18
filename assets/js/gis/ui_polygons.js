/**
 * QR-SAR - Tactical GIS: Polygons & Search Area Controller
 * KPP Kendari - Basarnas
 * Part of QRSAR.GIS Architecture Decomposition
 */

(function () {
  'use strict';

  window.QRSAR = window.QRSAR || {};
  window.QRSAR.GIS = window.QRSAR.GIS || {};
  window.QrSarGis = window.QrSarGis || {};

  const Polygons = {
    /**
     * Generate circle vertices given center and nautical miles radius
     */
    generateCircleVertices: function (centerLat, centerLng, radiusNm, steps = 64) {
      const radiusKm = radiusNm * 1.852;
      const R = 6371; // Earth radius in km
      const d = radiusKm / R; // Angular distance in radians
      const latRad = (centerLat * Math.PI) / 180;
      const lngRad = (centerLng * Math.PI) / 180;
      const points = [];

      for (let i = 0; i < steps; i++) {
        const bearing = (i * 2 * Math.PI) / steps;
        const pLat = Math.asin(
          Math.sin(latRad) * Math.cos(d) +
          Math.cos(latRad) * Math.sin(d) * Math.cos(bearing)
        );
        const pLng =
          lngRad +
          Math.atan2(
            Math.sin(bearing) * Math.sin(d) * Math.cos(latRad),
            Math.cos(d) - Math.sin(latRad) * Math.sin(pLat)
          );

        points.push({
          lat: (pLat * 180) / Math.PI,
          lng: (pLng * 180) / Math.PI
        });
      }
      return points;
    },

    /**
     * Generate rectangle/box vertices given center, width (NM) and height (NM)
     */
    generateBoxVertices: function (centerLat, centerLng, widthNm, heightNm) {
      const halfWidthKm = (widthNm * 1.852) / 2;
      const halfHeightKm = (heightNm * 1.852) / 2;
      const R = 6371;

      const dLat = (halfHeightKm / R) * (180 / Math.PI);
      const dLng = (halfWidthKm / (R * Math.cos((centerLat * Math.PI) / 180))) * (180 / Math.PI);

      return [
        { lat: centerLat + dLat, lng: centerLng - dLng }, // Northwest
        { lat: centerLat + dLat, lng: centerLng + dLng }, // Northeast
        { lat: centerLat - dLat, lng: centerLng + dLng }, // Southeast
        { lat: centerLat - dLat, lng: centerLng - dLng }  // Southwest
      ];
    },

    /**
     * Calculate spherical polygon area in Nautical Miles squared and KM squared
     */
    calculateArea: function (vertices) {
      if (!Array.isArray(vertices) || vertices.length < 3) {
        return { km2: 0, nm2: 0, hectares: 0 };
      }

      const R = 6371; // km
      let totalArea = 0;

      for (let i = 0; i < vertices.length; i++) {
        const p1 = vertices[i];
        const p2 = vertices[(i + 1) % vertices.length];

        const lat1 = (p1.lat * Math.PI) / 180;
        const lat2 = (p2.lat * Math.PI) / 180;
        const dLng = ((p2.lng - p1.lng) * Math.PI) / 180;

        totalArea += dLng * (2 + Math.sin(lat1) + Math.sin(lat2));
      }

      totalArea = Math.abs((totalArea * R * R) / 2); // km²
      const nm2 = totalArea / (1.852 * 1.852);
      const hectares = totalArea * 100;

      return {
        km2: Math.round(totalArea * 100) / 100,
        nm2: Math.round(nm2 * 100) / 100,
        hectares: Math.round(hectares * 10) / 10
      };
    }
  };

  window.QRSAR.GIS.Polygons = Polygons;
  window.QrSarGis.Polygons = Polygons;
})();
