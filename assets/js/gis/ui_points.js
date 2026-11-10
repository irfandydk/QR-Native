/**
 * QR-SAR - Tactical GIS: Points & Custom Icons Controller
 * KPP Kendari - Basarnas
 * Part of QRSAR.GIS Architecture Decomposition
 */

(function () {
  'use strict';

  window.QRSAR = window.QRSAR || {};
  window.QRSAR.GIS = window.QRSAR.GIS || {};
  window.QrSarGis = window.QrSarGis || {};

  const Points = {
    /**
     * Normalizes custom icon URL for local and remote assets
     */
    normalizeIconUrl: function (url) {
      if (!url) return '';
      const u = String(url).trim();
      if (!u) return '';
      const base = (window.BASE_URL || window.location.origin || '').replace(/\/+$/, '');
      const idx = u.indexOf('/uploads/icons/');
      if (idx !== -1) {
        return base + u.substring(idx);
      }
      if (u.startsWith('/uploads/')) {
        return base + u;
      }
      return u;
    },

    /**
     * Verified Basarnas Maritime Preset Icons
     */
    getVerifiedPresets: function () {
      return [
        { code: 'lkp', name: 'LKP (Last Known Position)', color: '#dc2626', icon: '🚨', category: 'Insiden Maritim' },
        { code: 'kansar', name: 'Kantor SAR (KPP Kendari)', color: '#ea580c', icon: '🏢', category: 'Pangkalan / Base' },
        { code: 'pos_sar', name: 'Pos / Unit Siaga SAR', color: '#f59e0b', icon: '⛺', category: 'Pos Lapangan' },
        { code: 'dermaga', name: 'Dermaga Kapal SAR', color: '#0284c7', icon: '⚓', category: 'Dermaga / Laut' },
        { code: 'kapal_sar', name: 'Kapal SAR (KN Pacitan)', color: '#0369a1', icon: '🚢', category: 'Armada Laut' },
        { code: 'rib', name: 'Rigid Inflatable Boat (RIB)', color: '#0891b2', icon: '🚤', category: 'Armada Laut' },
        { code: 'helipad', name: 'Helipad / Evakuasi Udara', color: '#7c3aed', icon: '🚁', category: 'Armada Udara' },
        { code: 'medis', name: 'Rumah Sakit / Medis', color: '#10b981', icon: '🏥', category: 'Fasilitas Medis' },
        { code: 'korban_selamat', name: 'Temuan Korban Selamat', color: '#059669', icon: '🟢', category: 'Hasil Pencarian' },
        { code: 'korban_meninggal', name: 'Temuan Korban MD', color: '#475569', icon: '⚫', category: 'Hasil Pencarian' }
      ];
    }
  };

  window.QRSAR.GIS.Points = Points;
  window.QrSarGis.Points = Points;
})();
