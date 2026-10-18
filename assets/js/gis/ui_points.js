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
     * Standard category badge HTML
     */
    getCategoryBadgeHtml: function (cat) {
      if (!cat) return '';
      const c = String(cat).toLowerCase();
      let colorClass = 'bg-slate-500/15 text-slate-700 dark:text-slate-300 border-slate-500/30';

      if (c.includes('lkp') || c.includes('darurat')) {
        colorClass = 'bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30';
      } else if (c.includes('pos') || c.includes('base') || c.includes('kantor')) {
        colorClass = 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30';
      } else if (c.includes('dermaga') || c.includes('kapal') || c.includes('pelabuhan')) {
        colorClass = 'bg-blue-500/15 text-blue-600 dark:text-blue-400 border-blue-500/30';
      } else if (c.includes('medis') || c.includes('rumah sakit')) {
        colorClass = 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30';
      }

      return `<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold border ${colorClass}">${cat}</span>`;
    },

    /**
     * Normalizes custom icon URL for local and remote assets
     */
    normalizeIconUrl: function (url) {
      if (!url) return '';
      const u = String(url).trim();
      if (!u) return '';
      const idx = u.indexOf('/uploads/icons/');
      if (idx !== -1) {
        return (window.location.origin || '') + u.substring(idx);
      }
      if (u.startsWith('/uploads/')) {
        return (window.location.origin || '') + u;
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
