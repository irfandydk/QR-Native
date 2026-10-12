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
            measurements: L.layerGroup()
        };

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

        this.map = L.map(this.containerId, {
            center: this.options.center,
            zoom: this.options.zoom,
            layers: [satellite] // Default satellite for tactical SAR plotting
        });

        // Layer controls
        const baseMaps = {
            "Satelit (Esri World Imagery)": satellite,
            "Peta Jalan (OpenStreetMap)": osm,
            "Topografi (OpenTopoMap)": topo
        };

        const overlayMaps = {
            "Satuan SAR (Kansar/Pos SAR)": this.layers.sarUnits,
            "Operasi SAR Aktif": this.layers.operations,
            "Pola Pencarian SAR": this.layers.searchPatterns,
            "Area Pencarian": this.layers.polygons,
            "Alat Ukur": this.layers.measurements
        };

        L.control.layers(baseMaps, overlayMaps, { position: 'topright' }).addTo(this.map);
        L.control.scale({ imperial: true, metric: true, position: 'bottomleft' }).addTo(this.map);

        // Add layers to map
        Object.values(this.layers).forEach(layer => layer.addTo(this.map));

        // Click handler for coordinates
        this.map.on('click', (e) => this.onMapClick(e));
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
}
