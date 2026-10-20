/**
 * QR-SAR - Tactical GIS: Search Pattern UI & Boundary Fitting Controller
 * KPP Kendari - Basarnas
 * Part of QRSAR.GIS Architecture Decomposition
 */

(function () {
  'use strict';

  window.QRSAR = window.QRSAR || {};
  window.QRSAR.GIS = window.QRSAR.GIS || {};
  window.QrSarGis = window.QrSarGis || {};

  const PatternsUI = {
    /**
     * Map pattern type code to Indonesian operational title
     */
    getPatternDisplayName: function (type) {
      switch (String(type).toLowerCase()) {
        case 'parallel_track':
        case 'parallel':
          return 'Pola Pencarian Paralel (Parallel Track Search - PS)';
        case 'creeping_line':
        case 'creeping':
          return 'Pola Jalur Rapat (Creeping Line Search - CS)';
        case 'e_sar':
        case 'sector_search':
        case 'sector':
          return 'Pola Pencarian E-SAR (Explorer Search and Rescue)';
        default:
          return 'Pola Pencarian Standar IAMSAR';
      }
    },

    /**
     * Test whether point is inside polygon using ray casting algorithm
     */
    isPointInPolygon: function (lat, lng, poly) {
      if (!Array.isArray(poly) || poly.length < 3) return false;
      let inside = false;
      for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
        const xi = poly[i].lat, yi = poly[i].lng;
        const xj = poly[j].lat, yj = poly[j].lng;
        const intersect = ((yi > lng) !== (yj > lng)) &&
          (lat < ((xj - xi) * (lng - yi)) / (yj - yi) + xi);
        if (intersect) inside = !inside;
      }
      return inside;
    },

    /**
     * Calculate centroid center point of polygon
     */
    getPolygonCentroid: function (poly) {
      if (!Array.isArray(poly) || poly.length === 0) return { lat: 0, lng: 0 };
      let sumLat = 0, sumLng = 0;
      poly.forEach(function (pt) {
        sumLat += pt.lat;
        sumLng += pt.lng;
      });
      return {
        lat: sumLat / poly.length,
        lng: sumLng / poly.length
      };
    }
  };

  window.QRSAR.GIS.PatternsUI = PatternsUI;
  window.QrSarGis.PatternsUI = PatternsUI;
})();
