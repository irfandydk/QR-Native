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
            sarUnits: L.layerGroup(),
            operations: L.layerGroup(),
            searchPatterns: L.layerGroup(),
            polygons: L.layerGroup(),
            routes: L.layerGroup(),
            measurements: L.layerGroup()
        };
        this.baseLayers = {};
        this.currentBaseLayerKey = 'satellite';
        this.componentLayers = {};

        this.measureMode = false;
        this.measurePoints = [];
        this.measureLine = null;

        this.init();
    }

    init() {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        // Base Layers
        const osm = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        });

        const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: '© Esri, Maxar, Earthstar Geographics',
            maxZoom: 19
        });

        const topo = L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenTopoMap contributors',
            maxZoom: 17
        });

        const dark = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            attribution: '© CartoDB, OpenStreetMap contributors',
            maxZoom: 19
        });

        this.baseLayers = {
            satellite: satellite,
            osm: osm,
            topo: topo,
            dark: dark
        };

        this.map = L.map(this.containerId, {
            center: this.options.center,
            zoom: this.options.zoom,
            layers: [satellite] // Default satellite for tactical SAR plotting
        });

        // Layer controls
        const baseMaps = {
            "Satelit (Esri World Imagery)": satellite,
            "Peta Jalan (OpenStreetMap)": osm,
            "Topografi (OpenTopoMap)": topo,
            "Dark Taktis (CartoDB Dark)": dark
        };

        const overlayMaps = {
            "Satuan SAR (Kansar/Pos SAR)": this.layers.sarUnits,
            "Operasi SAR Aktif": this.layers.operations,
            "Area Pencarian (Search Area)": this.layers.polygons,
            "Pola Pencarian SAR": this.layers.searchPatterns,
            "Jalur & Rute": this.layers.routes,
            "Alat Ukur": this.layers.measurements
        };

        L.control.layers(baseMaps, overlayMaps, { position: 'topright' }).addTo(this.map);
        L.control.scale({ imperial: true, metric: true, position: 'bottomleft' }).addTo(this.map);

        // Add layers to map
        Object.values(this.layers).forEach(layer => layer.addTo(this.map));

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

    // Add Pulsing LKP Marker
    addLkpMarker(lat, lng, title = 'Lokasi Kejadian (LKP)') {
        this.layers.operations.clearLayers();

        // Pulsing outer radar circle
        const pulseCircle = L.circleMarker([lat, lng], {
            radius: 22,
            color: '#EF4444',
            weight: 1.5,
            fillColor: '#EF4444',
            fillOpacity: 0.15
        }).addTo(this.layers.operations);

        const centerMarker = L.circleMarker([lat, lng], {
            radius: 8,
            color: '#FFFFFF',
            weight: 2.5,
            fillColor: '#EF4444',
            fillOpacity: 1
        }).addTo(this.layers.operations);

        centerMarker.bindPopup(`
            <div style="font-size:12px;">
                <strong style="color:#EF4444;">📍 ${title}</strong><br>
                Lat: ${lat.toFixed(6)} | Lng: ${lng.toFixed(6)}
            </div>
        `).openPopup();

        this.map.setView([lat, lng], 11);
    }

    // Load and Display All SAR Units
    loadSarUnits(units) {
        this.layers.sarUnits.clearLayers();
        units.forEach(u => {
            const isKansar = u.jenis_unit === 'KANTOR_SAR';
            const color = isKansar ? '#F97316' : '#0284C7';

            const marker = L.circleMarker([u.latitude, u.longitude], {
                radius: isKansar ? 10 : 7,
                color: '#FFFFFF',
                weight: 2,
                fillColor: color,
                fillOpacity: 1
            }).addTo(this.layers.sarUnits);

            marker.bindPopup(`
                <div style="font-size:12px;">
                    <strong style="color:${color};">${u.nama_unit}</strong><br>
                    <span style="font-size:11px;color:#64748B;">${u.wilayah}</span><br>
                    DMS: <code>${u.koordinat_dms}</code><br>
                    Telp: ${u.no_telp || '-'}
                </div>
            `);
        });
    }

    // Render Search Pattern
    drawSearchPattern(waypoints, color = '#0284C7') {
        this.layers.searchPatterns.clearLayers();
        if (!waypoints || waypoints.length === 0) return;

        const latlngs = waypoints.map(w => [w.lat, w.lng]);
        const polyline = L.polyline(latlngs, {
            color: color,
            weight: 2.5,
            dashArray: '4, 4'
        }).addTo(this.layers.searchPatterns);

        waypoints.forEach((w, idx) => {
            const isStart = idx === 0;
            const isEnd = idx === waypoints.length - 1;

            L.circleMarker([w.lat, w.lng], {
                radius: isStart || isEnd ? 6 : 4,
                color: '#FFFFFF',
                weight: 1.5,
                fillColor: isStart ? '#10B981' : (isEnd ? '#EF4444' : color),
                fillOpacity: 1
            }).bindTooltip(w.label || `WP-${idx + 1}`, { permanent: false })
            .addTo(this.layers.searchPatterns);
        });

        this.map.fitBounds(polyline.getBounds(), { padding: [40, 40] });
    }

    // Math Helpers
    getDistanceKm(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    getBearing(lat1, lon1, lat2, lon2) {
        const y = Math.sin((lon2 - lon1) * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180);
        const x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
                  Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos((lon2 - lon1) * Math.PI / 180);
        const brng = (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
        const cardinals = ['Utara', 'Timur Laut', 'Timur', 'Tenggara', 'Selatan', 'Barat Daya', 'Barat', 'Barat Laut', 'Utara'];
        const card = cardinals[Math.round(brng / 45)];
        return { deg: Math.round(brng), cardinal: card };
    }

    loadOperationBundle(bundle) {
        if (!bundle || !this.map) return;
        this.componentLayers = {};

        // 1. Polygons (Search Area)
        if (Array.isArray(bundle.polygons)) {
            bundle.polygons.forEach(p => {
                const pts = p.points || [];
                if (pts.length < 3) return;
                const latlngs = pts.map(pt => [pt.lat, pt.lng]);
                const strokeColor = p.strokeColor || '#2563eb';
                const fillColor = p.fillColor || strokeColor;
                const area = p.areaNm2 ? Number(p.areaNm2).toFixed(2) : '-';
                const isBaseMap = !p.id_operasi;

                const polygonLayer = L.polygon(latlngs, {
                    color: strokeColor,
                    weight: Number(p.strokeWeight) || 3,
                    opacity: Number(p.strokeOpacity) || 0.9,
                    fillColor: fillColor,
                    fillOpacity: Number(p.fillOpacity) || 0.25
                }).addTo(this.layers.polygons);

                const popupContent = `
                    <div style="font-size:12px;min-width:180px;line-height:1.4;">
                        <div style="font-weight:bold;color:${strokeColor};margin-bottom:3px;font-size:13px;">⬡ ${p.name || 'Sektor Pencarian'}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                        </div>
                        <div>Luas: <strong>${area} Nm²</strong></div>
                        <div>Sudut Batas: <strong>${pts.length} titik koordinat</strong></div>
                        ${p.keterangan ? `<div style="margin-top:4px;color:#64748b;font-size:11px;">${p.keterangan}</div>` : ''}
                    </div>
                `;
                polygonLayer.bindPopup(popupContent);
                this.componentLayers['polygon_' + p.id] = polygonLayer;
                this.componentLayers[p.id] = polygonLayer;
            });
        }

        // 2. Search Patterns
        if (Array.isArray(bundle.searchPatterns)) {
            bundle.searchPatterns.forEach(p => {
                const wps = p.waypoints || [];
                if (wps.length < 2) return;
                const latlngs = wps.map(w => [w.lat, w.lng]);
                const color = p.color || '#ea580c';
                const isBaseMap = !p.id_operasi;

                const patternLayer = L.polyline(latlngs, {
                    color: color,
                    weight: Number(p.strokeWeight) || 3,
                    opacity: Number(p.opacity) || 0.9,
                    dashArray: '5, 5'
                }).addTo(this.layers.searchPatterns);

                // Add start & end markers
                const startWp = wps[0];
                const endWp = wps[wps.length - 1];
                L.circleMarker([startWp.lat, startWp.lng], {
                    radius: 5,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: '#10b981',
                    fillOpacity: 1
                }).bindTooltip('Entry (Start)', { permanent: false }).addTo(this.layers.searchPatterns);

                L.circleMarker([endWp.lat, endWp.lng], {
                    radius: 5,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: '#ef4444',
                    fillOpacity: 1
                }).bindTooltip('Exit (End)', { permanent: false }).addTo(this.layers.searchPatterns);

                const typeLabels = {
                    'creeping_line': 'Creeping Line Search',
                    'parallel_track': 'Parallel Track Search',
                    'expanding_square': 'Expanding Square Search',
                    'e_sar': 'Pola Pencarian E-SAR',
                    'sector_search': 'Pola Pencarian E-SAR',
                    'track_line': 'Track Line Search'
                };
                const typeLabel = typeLabels[p.patternType] || p.patternType || 'Pola SAR';

                const popupContent = `
                    <div style="font-size:12px;min-width:180px;line-height:1.4;">
                        <div style="font-weight:bold;color:${color};margin-bottom:3px;font-size:13px;">⚡ ${p.name || 'Pola Pencarian SAR'}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                        </div>
                        <div>Tipe: <strong>${typeLabel}</strong></div>
                        <div>Track Spacing: <strong>${p.trackSpacingNm || '-'} Nm</strong></div>
                        <div>Total Jarak: <strong>${p.totalDistanceNm ? Number(p.totalDistanceNm).toFixed(1) : '-'} Nm</strong></div>
                        <div>Jumlah Sapu: <strong>${p.legsCount || wps.length}</strong></div>
                    </div>
                `;
                patternLayer.bindPopup(popupContent);
                this.componentLayers['pattern_' + p.id] = patternLayer;
                this.componentLayers[p.id] = patternLayer;
            });
        }

        // 3. Routes
        if (Array.isArray(bundle.routes)) {
            bundle.routes.forEach(r => {
                const pts = r.points || [];
                if (pts.length < 2) return;
                const latlngs = pts.map(pt => [pt.lat, pt.lng]);
                const strokeColor = r.strokeColor || '#059669';
                const isBaseMap = !r.id_operasi;

                const routeLayer = L.polyline(latlngs, {
                    color: strokeColor,
                    weight: Number(r.strokeWeight) || 3.5,
                    opacity: Number(r.strokeOpacity) || 0.9
                }).addTo(this.layers.routes);

                pts.forEach((pt, i) => {
                    L.circleMarker([pt.lat, pt.lng], {
                        radius: 4,
                        color: '#ffffff',
                        weight: 1.5,
                        fillColor: strokeColor,
                        fillOpacity: 1
                    }).bindTooltip(pt.label || `WP-${i + 1}`).addTo(this.layers.routes);
                });

                const popupContent = `
                    <div style="font-size:12px;min-width:180px;line-height:1.4;">
                        <div style="font-weight:bold;color:${strokeColor};margin-bottom:3px;font-size:13px;">↗️ ${r.name || 'Jalur Operasi'}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'}
                        </div>
                        <div>Panjang: <strong>${r.totalDistanceNm ? Number(r.totalDistanceNm).toFixed(1) : '-'} Nm</strong></div>
                        <div>Kecepatan Alut: <strong>${r.vesselSpeedKnots || 25} Knots</strong></div>
                        <div>Titik Waypoint: <strong>${pts.length} titik</strong></div>
                    </div>
                `;
                routeLayer.bindPopup(popupContent);
                this.componentLayers['route_' + r.id] = routeLayer;
                this.componentLayers[r.id] = routeLayer;
            });
        }

        // 4. Points (Titik Taktis)
        if (Array.isArray(bundle.points)) {
            bundle.points.forEach(pt => {
                const isBaseMap = !pt.id_operasi;
                const cat = (pt.category || 'Taktis').toUpperCase();
                let markerColor = '#8b5cf6';
                if (cat.includes('POS') || cat.includes('KANSAR')) markerColor = '#f97316';
                else if (cat.includes('LKP')) markerColor = '#ef4444';
                else if (cat.includes('DERMAGA') || cat.includes('PELABUHAN')) markerColor = '#0284c7';
                else if (cat.includes('HELIPAD') || cat.includes('BANDARA')) markerColor = '#10b981';

                const marker = L.circleMarker([pt.lat, pt.lng], {
                    radius: 7,
                    color: '#ffffff',
                    weight: 2,
                    fillColor: markerColor,
                    fillOpacity: 0.95
                }).addTo(this.layers.operations);

                const popupContent = `
                    <div style="font-size:12px;min-width:180px;line-height:1.4;">
                        <div style="font-weight:bold;color:${markerColor};margin-bottom:3px;font-size:13px;">📍 ${pt.label || 'Titik Taktis'}</div>
                        <div style="display:inline-block;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:bold;margin-bottom:6px;background:${isBaseMap ? '#eff6ff;color:#2563eb;' : '#fff7ed;color:#ea580c;'}">
                            ${isBaseMap ? 'BASE MAP' : 'OPERASI SAR'} &bull; ${cat}
                        </div>
                        <div style="font-family:monospace;font-size:11px;">Lat: ${Number(pt.lat).toFixed(6)} | Lng: ${Number(pt.lng).toFixed(6)}</div>
                        ${pt.description ? `<div style="margin-top:4px;color:#64748b;font-size:11px;">${pt.description}</div>` : ''}
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
        const uniqueLayers = Array.from(new Set(Object.values(this.componentLayers)));
        if (!uniqueLayers.length) return;
        const group = new L.featureGroup(uniqueLayers);
        const bounds = group.getBounds();
        if (bounds.isValid()) {
            this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
        }
    }
}
