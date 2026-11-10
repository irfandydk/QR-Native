/**
 * QR-SAR - Tactical GIS & Map Plotting Engine (Leaflet)
 * KPP Kendari - Basarnas
 */

class TacticalGisMap {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.options = Object.assign({
            center: [-4.037694, 122.486694], // Kansar Kendari
            zoom: 9
        }, options);

        this.map = null;
        this.layers = {
            operations: L.layerGroup(),     // LKP & Marker Utama
            points: L.layerGroup(),         // Titik Taktis & Icon
            polygons: L.layerGroup(),       // Area Pencarian (Polygon)
            searchPatterns: L.layerGroup(), // Pola SAR
            routes: L.layerGroup(),         // Jalur & Rute (Garis)
            measurements: L.layerGroup()    // Penggaris & Sudut
        };
        this.baseLayers = {};
        this.currentBaseLayerKey = this.options.defaultLayer || 'hybrid';
        this.componentLayers = {};

        this.measureMode = false;
        this.measurePoints = [];
        this.measureLine = null;

        this.init();
    }

    init() {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        // 1. Google Hybrid (Satelit Resolusi Tinggi + Label Jalan & Wilayah) - 100% Bebas API Key
        const hybrid = L.tileLayer('https://mt{s}.google.com/vt/lyrs=y&x={x}&y={y}&z={z}', {
            attribution: '© Google Maps',
            subdomains: ['0', '1', '2', '3'],
            maxZoom: 22,
            maxNativeZoom: 20
        });

        // 2. Google Satellite (Citra Satelit Murni Resolusi Tinggi)
        const satellite = L.tileLayer('https://mt{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', {
            attribution: '© Google Maps',
            subdomains: ['0', '1', '2', '3'],
            maxZoom: 22,
            maxNativeZoom: 20
        });

        // 3. ESRI World Imagery (High-Res Global Satellite)
        const esriSatellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Esri World Imagery',
            maxZoom: 22,
            maxNativeZoom: 18
        });

        // 4. Google Roadmap (Peta Jalan Vektor Bersih)
        const roadmap = L.tileLayer('https://mt{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', {
            attribution: '© Google Maps',
            subdomains: ['0', '1', '2', '3'],
            maxZoom: 22,
            maxNativeZoom: 20
        });

        // 5. Google Terrain (Peta Topografi, Kontur Relief & Elevasi)
        const terrain = L.tileLayer('https://mt{s}.google.com/vt/lyrs=p&x={x}&y={y}&z={z}', {
            attribution: '© Google Maps',
            subdomains: ['0', '1', '2', '3'],
            maxZoom: 22,
            maxNativeZoom: 20
        });

        // 6. OpenStreetMap Standard
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            subdomains: ['a', 'b', 'c'],
            maxZoom: 22,
            maxNativeZoom: 19
        });

        // Optional Dark Taktis (Operasi Malam)
        const dark = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '© CartoDB, OpenStreetMap contributors',
            subdomains: 'abcd',
            maxZoom: 22,
            maxNativeZoom: 19
        });

        this.baseLayers = {
            hybrid: hybrid,
            satellite: satellite,
            esri: esriSatellite,
            roadmap: roadmap,
            terrain: terrain,
            osm: osm,
            topo: terrain,
            dark: dark
        };

        // Silently tolerate single tile errors
        Object.values(this.baseLayers).forEach(layer => {
            if (layer && typeof layer.on === 'function') {
                layer.on('tileerror', () => { /* Silent fallback without uncaught exceptions */ });
            }
        });

        const activeBaseLayer = this.baseLayers[this.currentBaseLayerKey] || hybrid;

        this.map = L.map(this.containerId, {
            center: this.options.center,
            zoom: this.options.zoom,
            minZoom: 4,
            maxZoom: 22,
            zoomSnap: 0.5,
            zoomDelta: 0.5,
            layers: [activeBaseLayer]
        });

        // Layer controls (Pilihan Peta Dasar & Overlay Komponen SAR)
        const baseMaps = {
            "🛰️ Hybrid (Satelit + Jalan)": hybrid,
            "🌍 Satelit Google": satellite,
            "🛰️ Satelit Esri": esriSatellite,
            "🗺️ Roadmap (Peta Jalan)": roadmap,
            "🏔️ Terrain (Topografi & Kontur)": terrain,
            "🌐 OpenStreetMap": osm,
            "🌙 Dark Taktis": dark
        };

        const overlayMaps = {
            "🚨 Titik Kejadian (LKP)": this.layers.operations,
            "📍 Titik Taktis & Icon": this.layers.points,
            "⬡ Area Pencarian (Polygon)": this.layers.polygons,
            "⚡ Pola Pencarian SAR": this.layers.searchPatterns,
            "↗️ Jalur & Rute (Garis)": this.layers.routes,
            "📐 Alat Ukur Jarak": this.layers.measurements
        };

        L.control.layers(baseMaps, overlayMaps, { position: 'topright' }).addTo(this.map);
        L.control.scale({ imperial: true, metric: true, position: 'bottomleft' }).addTo(this.map);

        // Add overlay layers to map
        Object.values(this.layers).forEach(layer => layer.addTo(this.map));

        // Invalidate size on resize to avoid distorted canvas
        window.addEventListener('resize', () => {
            if (this.map) {
                setTimeout(() => this.map.invalidateSize(), 150);
            }
        });

        // Click handler for coordinates
        this.map.on('click', (e) => this.onMapClick(e));
    }

    switchBaseLayer(layerKey) {
        if (!this.baseLayers[layerKey] || !this.map) return;
        Object.values(this.baseLayers).forEach(layer => {
            if (this.map.hasLayer(layer)) {
                this.map.removeLayer(layer);
            }
        });
        this.baseLayers[layerKey].addTo(this.map);
        this.currentBaseLayerKey = layerKey;
    }

    onMapClick(e) {
        if (this.measureMode) {
            this.handleMeasureClick(e.latlng);
            return;
        }

        const lat = e.latlng.lat;
        const lng = e.latlng.lng;

        // Dispatch custom event for picker
        const event = new CustomEvent('map-coordinate-picked', {
            detail: { lat, lng }
        });
        window.dispatchEvent(event);
    }

    // Toggle Distance & Bearing Measurement Mode
    toggleMeasureMode() {
        this.measureMode = !this.measureMode;
        if (!this.measureMode) {
            this.clearMeasurements();
            showToast('Mode Pengukuran Jarak Dinonaktifkan', 'info');
        } else {
            this.clearMeasurements();
            showToast('Klik titik awal di peta, lalu klik titik tujuan untuk mengukur jarak & arah kompas.', 'info');
        }
    }

    handleMeasureClick(latlng) {
        this.measurePoints.push(latlng);

        const marker = L.circleMarker(latlng, {
            radius: 5,
            fillColor: '#F97316',
            color: '#FFFFFF',
            weight: 2,
            fillOpacity: 1
        }).addTo(this.layers.measurements);

        if (this.measurePoints.length === 2) {
            const pt1 = this.measurePoints[0];
            const pt2 = this.measurePoints[1];

            this.measureLine = L.polyline([pt1, pt2], {
                color: '#F97316',
                weight: 3.5,
                dashArray: '6, 6'
            }).addTo(this.layers.measurements);

            // Compute distance and bearing
            const distKm = this.getDistanceKm(pt1.lat, pt1.lng, pt2.lat, pt2.lng);
            const distNm = (distKm * 0.539957).toFixed(1);
            const bearing = this.getBearing(pt1.lat, pt1.lng, pt2.lat, pt2.lng);

            const midLat = (pt1.lat + pt2.lat) / 2;
            const midLng = (pt1.lng + pt2.lng) / 2;

            L.popup()
                .setLatLng([midLat, midLng])
                .setContent(`
                    <div style="font-size:12px;font-weight:bold;color:#0F172A;">
                        <div style="color:#EA580C;margin-bottom:4px;">📐 HASIL PENGUKURAN TAKTIS</div>
                        <div>Jarak: <span style="color:#0284C7;">${distNm} Nm</span> (${distKm.toFixed(1)} Km)</div>
                        <div>Arah/Sudut: <span style="color:#059669;">${bearing.deg}° ${bearing.cardinal}</span></div>
                    </div>
                `)
                .openOn(this.map);

            this.measurePoints = [];
        }
    }

    clearMeasurements() {
        this.layers.measurements.clearLayers();
        this.measurePoints = [];
    }

    // Security & Sanitization Helpers (SEC-01)
    escapeHtml(val) {
        if (val === null || val === undefined) return '';
        return String(val)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    sanitizeColor(color, fallback = '#2563eb') {
        if (!color || typeof color !== 'string') return fallback;
        const trimmed = color.trim();
        if (/^#[0-9a-fA-F]{3,8}$/.test(trimmed)) return trimmed;
        if (/^(rgb|rgba|hsl|hsla)\(\s*[\d.,%\s]+\)$/i.test(trimmed)) return trimmed;
        if (/^[a-zA-Z]{3,20}$/.test(trimmed)) return trimmed;
        return fallback;
    }

    sanitizeIconUrl(url) {
        if (!url || typeof url !== 'string') return '';
        const trimmed = url.trim();
        if (/^(https?:\/\/|\/|uploads\/|assets\/|data:image\/(png|jpeg|jpg|gif|webp|svg\+xml);base64,[a-zA-Z0-9+/=]+$)/i.test(trimmed)) {
            return this.escapeHtml(trimmed);
        }
        return '';
    }

    // Add Pulsing LKP Marker
    addLkpMarker(lat, lng, title = 'Lokasi Kejadian (LKP)') {
        this.layers.operations.clearLayers();
        const numLat = Number(lat) || 0;
        const numLng = Number(lng) || 0;
        const safeTitle = this.escapeHtml(title || 'Lokasi Kejadian (LKP)');

        // Pulsing outer radar circle
        const pulseCircle = L.circleMarker([numLat, numLng], {
            radius: 22,
            color: '#EF4444',
            weight: 1.5,
            fillColor: '#EF4444',
            fillOpacity: 0.15
        }).addTo(this.layers.operations);

        const centerMarker = L.circleMarker([numLat, numLng], {
            radius: 8,
            color: '#FFFFFF',
            weight: 2.5,
            fillColor: '#EF4444',
            fillOpacity: 1
        }).addTo(this.layers.operations);

        centerMarker.bindPopup(`
            <div style="font-size:12px;">
                <strong style="color:#EF4444;">📍 ${safeTitle}</strong><br>
                Lat: ${numLat.toFixed(6)} | Lng: ${numLng.toFixed(6)}
            </div>
        `).openPopup();

        this.map.setView([numLat, numLng], 11);
    }

    // Math Helpers (Unified with QrSarGis.Geodesy SSOT)
    getDistanceKm(lat1, lon1, lat2, lon2) {
        if (window.QrSarGis && window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.calculateDistanceHaversine === 'function') {
            return window.QrSarGis.Geodesy.calculateDistanceHaversine(lat1, lon1, lat2, lon2).km;
        }
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    getBearing(lat1, lon1, lat2, lon2) {
        if (window.QrSarGis && window.QrSarGis.Geodesy && typeof window.QrSarGis.Geodesy.calculateDistanceHaversine === 'function') {
            const res = window.QrSarGis.Geodesy.calculateDistanceHaversine(lat1, lon1, lat2, lon2);
            return { deg: Math.round(res.bearing), cardinal: res.headingName };
        }
        const y = Math.sin((lon2 - lon1) * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180);
        const x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
                  Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos((lon2 - lon1) * Math.PI / 180);
        const brng = (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
        const cardinals = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut', 'Utara'];
        const card = cardinals[Math.round(brng / 45)];
        return { deg: Math.round(brng), cardinal: card };
    }

    // DMS Formatter Helper
    formatDms(lat, lng) {
        return window.QrSarGis.Geodesy.decimalToDms(lat, lng);
    }

    loadOperationBundle(bundle) {
        if (!bundle || !this.map) return;
        
        // Bersihkan layer sebelumnya agar tidak terjadi duplikasi saat re-render
        if (this.layers.points) this.layers.points.clearLayers();
        if (this.layers.polygons) this.layers.polygons.clearLayers();
        if (this.layers.searchPatterns) this.layers.searchPatterns.clearLayers();
        if (this.layers.routes) this.layers.routes.clearLayers();
        this.componentLayers = {};

        // 1. POLYGON (AREA PENCARIAN / SEARCH AREA)
        if (Array.isArray(bundle.polygons)) {
            bundle.polygons.forEach(p => {
                const pts = p.points || [];
                const strokeColor = this.sanitizeColor(p.strokeColor, '#2563eb');
                const fillColor = this.sanitizeColor(p.fillColor, strokeColor);
                const area = p.areaNm2 ? Number(p.areaNm2).toFixed(2) : '-';
                const isBaseMap = !p.id_operasi;
                const safeKet = p.keterangan ? this.escapeHtml(p.keterangan) : '';

                // Handle Lingkaran (Circle Search Area)
                const isCircle = p.shapeType === 'circle' && Number(p.radiusNm) > 0;
                if (isCircle) {
                    const cLat = (p.centerLat !== null && p.centerLat !== undefined && p.centerLat !== '')
                        ? Number(p.centerLat)
                        : (pts.length > 0 ? Number(pts[0].lat) : null);
                    const cLng = (p.centerLng !== null && p.centerLng !== undefined && p.centerLng !== '')
                        ? Number(p.centerLng)
                        : (pts.length > 0 ? Number(pts[0].lng) : null);

                    if (cLat !== null && cLng !== null && !isNaN(cLat) && !isNaN(cLng)) {
                        const center = [cLat, cLng];
                        const radiusNmVal = Number(p.radiusNm) || 0;
                        const radiusMeters = radiusNmVal * 1852;
                        const safeCircleName = this.escapeHtml(p.name || 'Search Area Radius');
                        const circleLayer = L.circle(center, {
                            radius: radiusMeters,
                            color: strokeColor,
                            weight: Number(p.strokeWeight) || 3,
                            opacity: Number(p.strokeOpacity) || 0.9,
                            fillColor: fillColor,
                            fillOpacity: Number(p.fillOpacity) || 0.25
                        }).addTo(this.layers.polygons);

                        // Center Datum Marker
                        const centerHtml = `
                            <div style="background:#ea580c;color:#ffffff;width:24px;height:24px;border-radius:50%;border:2px solid #ffffff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;box-shadow:0 2px 6px rgba(0,0,0,0.5);user-select:none;">
                                ⊙
                            </div>
                        `;
                        const centerIcon = L.divIcon({
                            className: 'sar-circle-center-pin',
                            html: centerHtml,
                            iconSize: [24, 24],
                            iconAnchor: [12, 12]
                        });
                        L.marker(center, { icon: centerIcon })
                            .bindTooltip(`Pusat Datum (LKP): ${this.formatDms(cLat, cLng)}<br>Radius: ${radiusNmVal} NM`, { permanent: false })
                            .addTo(this.layers.polygons);

                        const popupContent = `
                            <div style="font-size:12px;min-width:200px;line-height:1.4;">
                                <div style="font-weight:bold;color:${strokeColor};margin-bottom:3px;font-size:13px;">⭕ ${safeCircleName}</div>
                                <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                                    ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                                </div>
                                <div>Radius: <strong>${radiusNmVal} Nm</strong> (${(radiusNmVal * 1.852).toFixed(1)} Km)</div>
                                <div>Luas Area: <strong>${area} Nm²</strong></div>
                                <div>Pusat Datum: <strong>${this.formatDms(cLat, cLng)}</strong></div>
                                ${safeKet ? `<div style="margin-top:4px;color:#64748b;font-size:11px;">${safeKet}</div>` : ''}
                            </div>
                        `;
                        circleLayer.bindPopup(popupContent);
                        this.componentLayers['polygon_' + p.id] = circleLayer;
                        this.componentLayers[p.id] = circleLayer;
                        return;
                    }
                }

                // Handle Rectangle corner generation if only dimensions/center exist
                let effectivePts = pts;
                if ((p.shapeType === 'rectangle' || p.shapeType === 'box') && (!effectivePts || effectivePts.length < 3)) {
                    const cLat = Number(p.centerLat);
                    const cLng = Number(p.centerLng);
                    const wNm = Number(p.boxWidthNm);
                    const hNm = Number(p.boxHeightNm);
                    if (!isNaN(cLat) && !isNaN(cLng) && wNm > 0 && hNm > 0) {
                        const halfWidthKm = (wNm * 1.852) / 2;
                        const halfHeightKm = (hNm * 1.852) / 2;
                        const latKmPerDeg = 110.574;
                        const lngKmPerDeg = 111.32 * Math.cos((cLat * Math.PI) / 180);
                        const dLat = halfHeightKm / latKmPerDeg;
                        const dLng = halfWidthKm / lngKmPerDeg;
                        effectivePts = [
                            { lat: cLat + dLat, lng: cLng - dLng, label: 'Titik A (NW)' },
                            { lat: cLat + dLat, lng: cLng + dLng, label: 'Titik B (NE)' },
                            { lat: cLat - dLat, lng: cLng + dLng, label: 'Titik C (SE)' },
                            { lat: cLat - dLat, lng: cLng - dLng, label: 'Titik D (SW)' }
                        ];
                    }
                }

                // Handle Standard Polygon (Minimal 3 titik koordinat sudut)
                if (!effectivePts || effectivePts.length < 3) return;
                const latlngs = effectivePts.map(pt => [Number(pt.lat) || 0, Number(pt.lng) || 0]);
                const safePolyName = this.escapeHtml(p.name || 'Sektor Pencarian');

                const polygonLayer = L.polygon(latlngs, {
                    color: strokeColor,
                    weight: Number(p.strokeWeight) || 3,
                    opacity: Number(p.strokeOpacity) || 0.9,
                    fillColor: fillColor,
                    fillOpacity: Number(p.fillOpacity) || 0.25
                }).addTo(this.layers.polygons);

                // Tambahkan Badge Sudut Koordinat (A, B, C, D...)
                effectivePts.forEach((pt, idx) => {
                    const letter = String.fromCharCode(65 + (idx % 26));
                    const cornerHtml = `
                        <div style="background:${strokeColor};color:#ffffff;width:22px;height:22px;border-radius:50%;border:2px solid #ffffff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:900;box-shadow:0 2px 5px rgba(0,0,0,0.45);user-select:none;">
                            ${letter}
                        </div>
                    `;
                    const cornerIcon = L.divIcon({
                        className: 'sar-corner-pin',
                        html: cornerHtml,
                        iconSize: [22, 22],
                        iconAnchor: [11, 11]
                    });

                    L.marker([Number(pt.lat) || 0, Number(pt.lng) || 0], { icon: cornerIcon })
                        .bindTooltip(`Titik ${letter}: ${this.formatDms(Number(pt.lat) || 0, Number(pt.lng) || 0)}`, { permanent: false })
                        .addTo(this.layers.polygons);
                });

                const popupContent = `
                    <div style="font-size:12px;min-width:200px;line-height:1.4;">
                        <div style="font-weight:bold;color:${strokeColor};margin-bottom:3px;font-size:13px;">⬡ ${safePolyName}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                        </div>
                        <div>Luas Sektor: <strong>${area} Nm²</strong></div>
                        <div>Sudut Batas: <strong>${effectivePts.length} titik koordinat (A-${String.fromCharCode(64 + Math.min(effectivePts.length, 26))})</strong></div>
                        ${safeKet ? `<div style="margin-top:4px;color:#64748b;font-size:11px;">${safeKet}</div>` : ''}
                    </div>
                `;
                polygonLayer.bindPopup(popupContent);
                this.componentLayers['polygon_' + p.id] = polygonLayer;
                this.componentLayers[p.id] = polygonLayer;
            });
        }

        // 2. POLA SAR (SEARCH PATTERNS)
        if (Array.isArray(bundle.searchPatterns)) {
            bundle.searchPatterns.forEach(p => {
                const wps = p.waypoints || [];
                if (wps.length < 2) return;
                const color = this.sanitizeColor(p.color, '#ea580c');
                const isBaseMap = !p.id_operasi;
                const isEsar = (p.patternType === 'e_sar' || p.patternType === 'sector_search');

                let patternPolylineCoords = [];
                if (isEsar) {
                    const segments = [];
                    const baseline = [];
                    for (let i = 0; i < wps.length - 1; i += 2) {
                        const pStart = wps[i];
                        const pFin = wps[i + 1];
                        if (pStart && pFin) {
                            const lat1 = Number(pStart.lat) || 0;
                            const lng1 = Number(pStart.lng) || 0;
                            const lat2 = Number(pFin.lat) || 0;
                            const lng2 = Number(pFin.lng) || 0;
                            segments.push([[lat1, lng1], [lat2, lng2]]);
                            baseline.push([lat1, lng1]);

                            // Add SVG directional arrow for each sweep line
                            const arrowLat = lat1 + (lat2 - lat1) * 0.65;
                            const arrowLng = lng1 + (lng2 - lng1) * 0.65;
                            const dLng = (lng2 - lng1) * Math.PI / 180;
                            const φ1 = lat1 * Math.PI / 180;
                            const φ2 = lat2 * Math.PI / 180;
                            const y = Math.sin(dLng) * Math.cos(φ2);
                            const x = Math.cos(φ1) * Math.sin(φ2) - Math.sin(φ1) * Math.cos(φ2) * Math.cos(dLng);
                            const brng = (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;

                            const arrowSvg = `
                                <div style="transform: rotate(${Math.round(brng)}deg); display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; pointer-events: none;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="${color}" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 2px 4px rgba(0,0,0,0.45));">
                                        <polygon points="12,2 22,22 12,17 2,22 12,2" />
                                    </svg>
                                </div>
                            `;
                            L.marker([arrowLat, arrowLng], {
                                icon: L.divIcon({
                                    className: 'sar-esar-sweep-arrow',
                                    html: arrowSvg,
                                    iconSize: [28, 28],
                                    iconAnchor: [14, 14]
                                }),
                                interactive: false,
                                zIndexOffset: 1450
                            }).addTo(this.layers.searchPatterns);
                        }
                    }
                    if (baseline.length >= 2) {
                        segments.push(baseline);
                    }
                    patternPolylineCoords = segments;

                    // Add Personnel Start Badges (P1, P2, P3...)
                    for (let i = 0; i < wps.length - 1; i += 2) {
                        const pNum = Math.floor(i / 2) + 1;
                        const pStart = wps[i];
                        const lat = Number(pStart.lat) || 0;
                        const lng = Number(pStart.lng) || 0;
                        const pHtml = `
                            <div style="background:#0284c7;color:#ffffff;width:22px;height:22px;border-radius:50%;border:2px solid #ffffff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:900;box-shadow:0 2px 4px rgba(0,0,0,0.45);">
                                P${pNum}
                            </div>
                        `;
                        L.marker([lat, lng], {
                            icon: L.divIcon({ className: 'sar-pat-esar-p', html: pHtml, iconSize: [22, 22], iconAnchor: [11, 11] })
                        }).bindTooltip(`Personel ${pNum} (Garis Awal)`, { permanent: false }).addTo(this.layers.searchPatterns);
                    }
                } else {
                    patternPolylineCoords = wps.map(w => [Number(w.lat) || 0, Number(w.lng) || 0]);

                    // Entry / Start Marker (S)
                    const startWp = wps[0];
                    const startHtml = `
                        <div style="background:#16a34a;color:#ffffff;width:24px;height:24px;border-radius:50%;border:2px solid #ffffff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;box-shadow:0 2px 5px rgba(0,0,0,0.45);">
                            S
                        </div>
                    `;
                    L.marker([Number(startWp.lat) || 0, Number(startWp.lng) || 0], {
                        icon: L.divIcon({ className: 'sar-pat-start', html: startHtml, iconSize: [24, 24], iconAnchor: [12, 12] })
                    }).bindTooltip('Titik Awal Sapuan (Entry / Start)', { permanent: false }).addTo(this.layers.searchPatterns);

                    // Exit / End Marker (E)
                    const endWp = wps[wps.length - 1];
                    const endHtml = `
                        <div style="background:#dc2626;color:#ffffff;width:24px;height:24px;border-radius:50%;border:2px solid #ffffff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:900;box-shadow:0 2px 5px rgba(0,0,0,0.45);">
                            E
                        </div>
                    `;
                    L.marker([Number(endWp.lat) || 0, Number(endWp.lng) || 0], {
                        icon: L.divIcon({ className: 'sar-pat-end', html: endHtml, iconSize: [24, 24], iconAnchor: [12, 12] })
                    }).bindTooltip('Titik Akhir Sapuan (Exit / End)', { permanent: false }).addTo(this.layers.searchPatterns);
                }

                const patternLayer = L.polyline(patternPolylineCoords, {
                    color: color,
                    weight: Number(p.strokeWeight) || 3,
                    opacity: Number(p.opacity) || 0.9,
                    dashArray: '6, 6'
                }).addTo(this.layers.searchPatterns);

                const typeLabels = {
                    'creeping_line': 'Creeping Line Search (Lintasan Merayap)',
                    'parallel_track': 'Parallel Track Search (Lintasan Paralel)',
                    'e_sar': 'Pola E-SAR (Hutan/Darat)',
                    'sector_search': 'Pola E-SAR (Hutan/Darat)',
                    'track_line': 'Track Line Search (Jalur Lurus)'
                };
                const safePatName = this.escapeHtml(p.name || 'Pola Pencarian SAR');
                const safeTypeLabel = this.escapeHtml(typeLabels[p.patternType] || p.patternType || 'Pola SAR');
                const safeSpacing = p.trackSpacingNm !== undefined && p.trackSpacingNm !== null && p.trackSpacingNm !== ''
                    ? this.escapeHtml(p.trackSpacingNm)
                    : '-';
                const safeLegsCount = Number(p.legsCount) || (isEsar ? Math.floor(wps.length / 2) : wps.length);

                const popupContent = isEsar ? `
                    <div style="font-size:12px;min-width:200px;line-height:1.4;">
                        <div style="font-weight:bold;color:${color};margin-bottom:3px;font-size:13px;">⚡ ${safePatName}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                        </div>
                        <div>Tipe: <strong>${safeTypeLabel}</strong></div>
                        <div>Jumlah Personil: <strong>${safeLegsCount} Personil</strong></div>
                        <div>Spasi Antar Personil (S): <strong>${safeSpacing} Nm</strong></div>
                        <div>Total Panjang Sapuan: <strong>${p.totalDistanceNm ? Number(p.totalDistanceNm).toFixed(1) : '-'} Nm</strong></div>
                    </div>
                ` : `
                    <div style="font-size:12px;min-width:200px;line-height:1.4;">
                        <div style="font-weight:bold;color:${color};margin-bottom:3px;font-size:13px;">⚡ ${safePatName}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                        </div>
                        <div>Tipe: <strong>${safeTypeLabel}</strong></div>
                        <div>Spasi Sapuan (S): <strong>${safeSpacing} Nm</strong></div>
                        <div>Total Panjang Sapuan: <strong>${p.totalDistanceNm ? Number(p.totalDistanceNm).toFixed(1) : '-'} Nm</strong></div>
                        <div>Jumlah Lintasan: <strong>${safeLegsCount} Leg</strong></div>
                    </div>
                `;
                patternLayer.bindPopup(popupContent);
                this.componentLayers['pattern_' + p.id] = patternLayer;
                this.componentLayers[p.id] = patternLayer;
            });
        }

        // 3. GARIS / JALUR RUTE (ROUTES)
        if (Array.isArray(bundle.routes)) {
            bundle.routes.forEach(r => {
                const pts = r.points || [];
                if (pts.length < 2) return;
                const latlngs = pts.map(pt => [Number(pt.lat) || 0, Number(pt.lng) || 0]);
                const strokeColor = this.sanitizeColor(r.strokeColor, '#059669');
                const isBaseMap = !r.id_operasi;
                const safeRouteName = this.escapeHtml(r.name || 'Jalur Operasi');
                const safeSpeed = this.escapeHtml(r.vesselSpeedKnots || 25);

                const routeLayer = L.polyline(latlngs, {
                    color: strokeColor,
                    weight: Number(r.strokeWeight) || 3.5,
                    opacity: Number(r.strokeOpacity) || 0.9
                }).addTo(this.layers.routes);

                pts.forEach((pt, i) => {
                    const wpLabel = this.escapeHtml(pt.label || `WP-${i + 1}`);
                    const wpHtml = `
                        <div style="background:${strokeColor};color:#ffffff;width:20px;height:20px;border-radius:50%;border:1.5px solid #ffffff;display:flex;align-items:center;justify-content:center;font-size:9px;font-weight:bold;box-shadow:0 2px 4px rgba(0,0,0,0.4);">
                            ${i + 1}
                        </div>
                    `;
                    L.marker([Number(pt.lat) || 0, Number(pt.lng) || 0], {
                        icon: L.divIcon({ className: 'sar-route-wp', html: wpHtml, iconSize: [20, 20], iconAnchor: [10, 10] })
                    }).bindTooltip(`${wpLabel}: ${this.formatDms(Number(pt.lat) || 0, Number(pt.lng) || 0)}`).addTo(this.layers.routes);
                });

                const popupContent = `
                    <div style="font-size:12px;min-width:200px;line-height:1.4;">
                        <div style="font-weight:bold;color:${strokeColor};margin-bottom:3px;font-size:13px;">↗️ ${safeRouteName}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                        </div>
                        <div>Panjang Rute: <strong>${r.totalDistanceNm ? Number(r.totalDistanceNm).toFixed(1) : '-'} Nm</strong></div>
                        <div>Kecepatan Alut: <strong>${safeSpeed} Knots</strong></div>
                        <div>Jumlah Waypoint: <strong>${pts.length} titik</strong></div>
                    </div>
                `;
                routeLayer.bindPopup(popupContent);
                this.componentLayers['route_' + r.id] = routeLayer;
                this.componentLayers[r.id] = routeLayer;
            });
        }

        // 4. ICON & TITIK TAKTIS (POINTS)
        if (Array.isArray(bundle.points)) {
            bundle.points.forEach(pt => {
                const isBaseMap = !pt.id_operasi;
                const pType = (pt.pointType || pt.type || '').toLowerCase();
                const cat = (pt.category || 'Taktis').toUpperCase();
                const safeCat = this.escapeHtml(cat);
                const rawLabel = pt.label ? String(pt.label).trim() : '';
                const safeLabel = this.escapeHtml(rawLabel);
                const safeDesc = pt.description ? this.escapeHtml(pt.description) : '';
                const safeAlpha = this.escapeHtml(String(pt.alphabetChar || 'A').slice(0, 3));
                const safeIconUrl = this.sanitizeIconUrl(pt.customIconUrl);

                // Warna pin taktis
                let markerColor = '#ea580c';
                if (pType === 'lkp' || cat.includes('LKP')) markerColor = '#dc2626';
                else if (pType.includes('kapal') || pType === 'pelabuhan' || cat.includes('DERMAGA') || cat.includes('PELABUHAN')) markerColor = '#0284c7';
                else if (pType.includes('medis') || pType === 'rumah_sakit' || cat.includes('MEDIS')) markerColor = '#e11d48';
                else if (pType.includes('helipad') || pType === 'bandara' || cat.includes('BANDARA')) markerColor = '#ca8a04';
                else if (pType === 'posko_sar' || cat.includes('POSKO')) markerColor = '#059669';
                else if (cat.includes('POS SAR') || cat.includes('KANSAR')) markerColor = '#f97316';
                else if (pType === 'orang_hilang' || pType === 'tenggelam') markerColor = '#ef4444';

                // Emoji / visual icon
                let innerVisual = '📍';
                if (safeIconUrl) {
                    innerVisual = `<img src="${safeIconUrl}" style="width:20px;height:20px;object-fit:contain;border-radius:4px;" alt="icon">`;
                } else if (pType === 'alphabet') {
                    innerVisual = `<span style="font-weight:900;font-size:12px;color:${markerColor};">${safeAlpha}</span>`;
                } else if (pType === 'lkp' || cat.includes('LKP')) {
                    innerVisual = '🚨';
                } else if (pType.includes('kapal')) {
                    innerVisual = '🚢';
                } else if (pType.includes('perahu') || pType === 'rib') {
                    innerVisual = '🚤';
                } else if (pType === 'pelabuhan') {
                    innerVisual = '⚓';
                } else if (pType === 'posko_sar') {
                    innerVisual = '🏕️';
                } else if (pType === 'pos_sar' || cat.includes('POS')) {
                    innerVisual = '📻';
                } else if (pType === 'rumah_sakit' || cat.includes('MEDIS')) {
                    innerVisual = '🏥';
                } else if (pType === 'helipad' || pType === 'bandara') {
                    innerVisual = '🚁';
                } else if (pType === 'orang_hilang') {
                    innerVisual = '👤';
                } else if (pType === 'tenggelam') {
                    innerVisual = '🏊';
                }

                const labelBadge = safeLabel ? `
                    <div style="position:absolute;bottom:100%;left:50%;transform:translateX(-50%);margin-bottom:3px;background:rgba(15,23,42,0.92);color:#ffffff;font-size:10px;font-weight:bold;padding:1px 6px;border-radius:4px;border:1px solid rgba(255,255,255,0.4);white-space:nowrap;box-shadow:0 2px 4px rgba(0,0,0,0.5);pointer-events:none;">
                        ${safeLabel}
                    </div>
                ` : '';

                let markerHtml = '';
                if (window.QrSarGis && window.QrSarGis.Markers && typeof window.QrSarGis.Markers.createMarkerHtml === 'function') {
                    markerHtml = window.QrSarGis.Markers.createMarkerHtml({
                        pointType: pType,
                        customColor: markerColor,
                        label: safeLabel,
                        customIconUrl: safeIconUrl,
                        alphabetChar: safeAlpha
                    });
                } else {
                    markerHtml = `
                        <div class="sar-point-marker" style="position:relative;width:34px;height:42px;user-select:none;cursor:pointer;">
                            ${labelBadge}
                            <svg xmlns="http://www.w3.org/2000/svg" width="34" height="42" viewBox="0 0 34 42" style="display:block;filter:drop-shadow(0 2px 4px rgba(0,0,0,0.45));">
                                <path d="M17 41 C15 35, 3 22, 3 15 A14 14 0 1 1 31 15 C31 22, 19 35, 17 41 Z" fill="${markerColor}" stroke="#ffffff" stroke-width="2"/>
                                <circle cx="17" cy="15" r="10" fill="#ffffff"/>
                            </svg>
                            <div style="position:absolute;top:5px;left:7px;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:12px;line-height:1;">
                                ${innerVisual}
                            </div>
                        </div>
                    `;
                }

                const ptLat = Number(pt.lat) || 0;
                const ptLng = Number(pt.lng) || 0;

                const marker = L.marker([ptLat, ptLng], {
                    icon: L.divIcon({
                        className: 'sar-tactical-icon',
                        html: markerHtml,
                        iconSize: [34, 42],
                        iconAnchor: [17, 41],
                        popupAnchor: [0, -38]
                    })
                }).addTo(this.layers.points);

                const popupContent = `
                    <div style="font-size:12px;min-width:200px;line-height:1.4;">
                        <div style="font-weight:bold;color:${markerColor};margin-bottom:3px;font-size:13px;">📍 ${safeLabel || 'Titik Taktis'}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'} &bull; ${safeCat}
                        </div>
                        <div style="font-family:monospace;font-size:11px;color:#334155;background:#f8fafc;padding:4px 6px;border-radius:4px;border:1px solid #e2e8f0;margin-bottom:4px;">
                            ${this.formatDms(ptLat, ptLng)}
                        </div>
                        <div style="font-size:10px;color:#64748b;font-family:monospace;">DD: ${ptLat.toFixed(6)}, ${ptLng.toFixed(6)}</div>
                        ${safeDesc ? `<div style="margin-top:6px;color:#475569;font-size:11px;border-top:1px dashed #cbd5e1;padding-top:4px;">${safeDesc}</div>` : ''}
                    </div>
                `;
                marker.bindPopup(popupContent);
                this.componentLayers['point_' + pt.id] = marker;
                this.componentLayers[pt.id] = marker;
            });
        }
    }

    focusComponent(id) {
        const layer = this.componentLayers[id];
        if (!layer || !this.map) return false;
        if (typeof layer.getBounds === 'function') {
            this.map.fitBounds(layer.getBounds(), { padding: [50, 50], maxZoom: 14 });
            setTimeout(() => { if (layer.openPopup) layer.openPopup(); }, 300);
        } else if (typeof layer.getLatLng === 'function') {
            this.map.setView(layer.getLatLng(), 13);
            setTimeout(() => { if (layer.openPopup) layer.openPopup(); }, 300);
        }
        return true;
    }

    fitAllComponents() {
        if (!this.map) return;
        const allLayers = [];
        
        // Kumpulkan seluruh layer komponen aktif
        ['operations', 'points', 'polygons', 'searchPatterns', 'routes'].forEach(key => {
            if (this.layers[key]) {
                this.layers[key].eachLayer(layer => {
                    allLayers.push(layer);
                });
            }
        });

        if (!allLayers.length) return;
        const group = new L.featureGroup(allLayers);
        const bounds = group.getBounds();
        if (bounds.isValid()) {
            this.map.fitBounds(bounds, { padding: [45, 45], maxZoom: 14 });
        }
    }
}

