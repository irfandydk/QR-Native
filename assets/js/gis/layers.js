/**
 * QR-SAR - Tactical GIS: Layers & Basemap Controller
 * KPP Kendari - Basarnas
 * Part of QRSAR.GIS Architecture Decomposition
 */

(function () {
  'use strict';

  window.QRSAR = window.QRSAR || {};
  window.QRSAR.GIS = window.QRSAR.GIS || {};
  window.QrSarGis = window.QrSarGis || {};

  const Layers = {
    /**
     * Create standard zero-API-key tactical tile layers
     */
    createTileLayers: function () {
      return {
        hybrid: L.tileLayer('https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
          maxNativeZoom: 20,
          maxZoom: 22,
          subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
          attribution: 'Google Hybrid Satelit'
        }),
        osm: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '© OpenStreetMap contributors'
        }),
        roadmap: L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
          maxNativeZoom: 20,
          maxZoom: 22,
          subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
          attribution: 'Google Maps Roadmap'
        }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
          maxNativeZoom: 18,
          maxZoom: 22,
          attribution: 'Esri World Imagery'
        }),
        terrain: L.tileLayer('https://mt1.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', {
          maxNativeZoom: 15,
          maxZoom: 20,
          subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
          attribution: 'Google Terrain'
        })
      };
    },

    /**
     * Switch active basemap on Leaflet map instance
     */
    switchBasemap: function (map, basemapLayers, targetType) {
      if (!map || !basemapLayers) return null;
      const type = basemapLayers[targetType] ? targetType : 'hybrid';
      Object.keys(basemapLayers).forEach(function (k) {
        if (map.hasLayer(basemapLayers[k])) {
          map.removeLayer(basemapLayers[k]);
        }
      });
      basemapLayers[type].addTo(map);
      return type;
    },

    /**
     * Filter items by operation context and basemap visibility
     */
    filterEntities: function (items, filterMode, currentOperasiId, includeBaseInActive) {
      if (!Array.isArray(items)) return [];
      const curId = currentOperasiId ? parseInt(currentOperasiId, 10) : null;

      return items.filter(function (item) {
        const itemOpsId = item.id_operasi ? parseInt(item.id_operasi, 10) : 0;
        const isBase = itemOpsId === 0 || !item.id_operasi || (item.category && item.category.toLowerCase().includes('base'));

        if (filterMode === 'all') {
          return true;
        }
        if (filterMode === 'basemap') {
          return isBase;
        }
        if (filterMode === 'operasi' && curId) {
          if (itemOpsId === curId) return true;
          if (includeBaseInActive && isBase) return true;
          return false;
        }
        return true;
      });
    }
  };

  window.QRSAR.GIS.Layers = Layers;
  window.QrSarGis.Layers = Layers;
})();
