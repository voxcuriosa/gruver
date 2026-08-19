<?php
/**
 * lidarfunn.php - LiDAR-basert objektgjenkjenning (Tegn-og-Lær)
 */
?>
<!DOCTYPE html>
<html lang="no">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>LiDARfunn - AI Objektgjenkjenning</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-color: #0b0f19;
            --panel-bg: rgba(17, 24, 39, 0.9);
            --accent-color: #38bdf8;
            --text-main: #e2e8f0;
            --glass-border: rgba(255, 255, 255, 0.1);
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            width: 100%;
            font-family: 'Outfit', sans-serif;
            background: var(--bg-color);
            color: var(--text-main);
            overflow: hidden;
        }

        #map {
            height: 100vh;
            width: 100vw;
            background: #0b0f19;
        }

        .ui-panel {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--panel-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 20px;
            width: 280px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
        }

        h1 {
            margin: 0 0 10px 0;
            font-size: 1.4rem;
            color: var(--accent-color);
        }

        p {
            font-size: 0.85rem;
            margin-bottom: 20px;
            opacity: 0.8;
            line-height: 1.4;
        }

        .btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: var(--accent-color);
            color: #0b0f19;
            border: none;
            border-radius: 8px;
            font-weight: 700;
            cursor: pointer;
            margin-bottom: 10px;
            transition: transform 0.2s, background 0.2s;
            text-align: center;
            font-size: 0.9rem;
        }

        .btn:hover {
            transform: scale(1.02);
            background: #7dd3fc;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .cat-popup {
            background: var(--panel-bg);
            color: white;
            padding: 15px;
            border-radius: 12px;
            border: 1px solid var(--accent-color);
            min-width: 200px;
        }

        .cat-popup h3 {
            margin-top: 0;
            font-size: 1rem;
            color: var(--accent-color);
        }

        .cat-option {
            padding: 10px;
            margin: 8px 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.9rem;
            border: 1px solid transparent;
        }

        .cat-option:hover {
            background: rgba(56, 189, 248, 0.2);
            border-color: var(--accent-color);
        }

        #loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(11, 15, 25, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            backdrop-filter: blur(5px);
        }

        .found-needle {
            background: transparent !important;
            border: none !important;
        }

        .marker-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body>

    <div id="loading-overlay">
        <div style="text-align: center;">
            <div style="font-size: 2rem; color: var(--accent-color); font-weight: 800; margin-bottom: 10px;">Analyserer
                LiDAR...</div>
            <p>Vennligst vent mens AI-motoren jobber med dataene.</p>
            <div class="spinner"
                style="margin: 20px auto; border: 4px solid rgba(56, 189, 248, 0.1); border-left-color: var(--accent-color); border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite;">
            </div>
        </div>
    </div>

    <style>
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <div id="map"></div>

    <div class="ui-panel">
        <h1>LiDARfunn AI</h1>
        <p>Tegn rundt objekter i kartet for å lære opp AI-motoren, eller lag en boks for å søke etter nye funn.</p>

        <button id="search-btn" class="btn" disabled>Søk i tegnet boks/område</button>
        <div style="font-size: 0.75rem; opacity: 0.7; margin-top: -5px; margin-bottom: 20px;">
            AI-søket skjer kun innenfor den boksen du har tegnet i kartet.
        </div>
        <button id="clear-btn" class="btn"
            style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; margin-top: 5px;">Slett
            alle AI-funn</button>

        <div style="margin-top: 20px; font-size: 0.8rem; border-top: 1px solid var(--glass-border); padding-top: 10px;">
            <div id="status-text" style="color: var(--accent-color); font-weight: 600;">Status: Klar for bruk</div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.11.0/proj4.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4leaflet/1.0.2/proj4leaflet.min.js"></script>

    <script>
        let map;
        let editableLayers = new L.FeatureGroup();
        let referenceLayer = L.layerGroup();
        let foundLayer = L.layerGroup();
        let currentSearchArea = null;

        function initMap() {
            console.log("Initializing map...");
            map = L.map('map', {
                center: [59.2245, 9.5255], // Skien sentrum/marka
                zoom: 15,
                zoomControl: false
            });

            L.control.zoom({ position: 'bottomright' }).addTo(map);

            // Kartverket LiDAR Lag (Direct)
            const lidarEnkel = L.tileLayer.wms('https://wms.geonorge.no/skwms1/wms.hoyde-dtm', {
                layers: 'DTM:skyggerelieff',
                format: 'image/png',
                transparent: true,
                version: '1.1.1',
                attribution: '&copy; Kartverket',
                maxZoom: 20
            }).addTo(map);

            const hillshade = L.tileLayer.wms('https://wms.geonorge.no/skwms1/wms.hoyde-hillshade', {
                layers: 'hillshade',
                format: 'image/png',
                transparent: true,
                version: '1.1.1',
                attribution: '&copy; Kartverket',
                opacity: 0.5
            });

            map.addLayer(editableLayers);
            map.addLayer(referenceLayer);
            map.addLayer(foundLayer);

            // Draw tools
            const drawControl = new L.Control.Draw({
                draw: {
                    polygon: true,
                    polyline: false,
                    circle: false,
                    marker: false,
                    circlemarker: false,
                    rectangle: true
                },
                edit: {
                    featureGroup: editableLayers,
                    remove: true
                }
            });
            map.addControl(drawControl);

            // Event listeners moved inside initMap
            map.on(L.Draw.Event.CREATED, function (e) {
                const type = e.layerType;
                const layer = e.layer;

                if (type === 'rectangle' || type === 'polygon') {
                    if (currentSearchArea) {
                        // Option: Clear older search areas to keep it simple
                        // editableLayers.removeLayer(currentSearchArea);
                    }
                    currentSearchArea = layer;
                    document.getElementById('search-btn').disabled = false;
                    document.getElementById('status-text').textContent = "Status: Område markert";
                }

                editableLayers.addLayer(layer);

                if (type === 'polygon' || type === 'rectangle') {
                    const geojson = layer.toGeoJSON();
                    const popupContent = document.createElement('div');
                    popupContent.className = 'cat-popup';
                    popupContent.innerHTML = `
                    <h3>Velg kategori</h3>
                    <div class="cat-option" id="cat-kullmile">Charcoal Kiln (Kullmile)</div>
                    <div class="cat-option" id="cat-gruve">Mine Pit (Dagstrosse)</div>
                `;

                    layer.bindPopup(popupContent).openPopup();

                    // Add listeners directly to elements to avoid JSON stringify issues in HTML strings
                    popupContent.querySelector('#cat-kullmile').onclick = () => saveTrainingData('kullmiler', geojson, layer);
                    popupContent.querySelector('#cat-gruve').onclick = () => saveTrainingData('gruver', geojson, layer);
                }
            });

            loadReferenceData();
            loadExistingFunn();
            console.log("Map initialized.");
        }

        const categoryMap = {
            'GRUVE': { name: 'Gruver & Skjerp', icon: '⚒️', color: '#ef4444' },
            'BYGDEBORG': { name: 'Bygdeborger', icon: '🏰', color: '#3b82f6' },
            'HUSTUFT': { name: 'Hustufter & Ruiner', icon: '🧱', color: '#f59e0b' },
            'UTSIKT': { name: 'Utsiktspunkter', icon: '🔭', color: '#10b981' },
            'VANN': { name: 'Vannsystemer', icon: '💧', color: '#0ea5e9' },
            'GRENSESTEIN': { name: 'Grensesteiner', icon: '🗿', color: '#8b5cf6' },
            'GRAVHAUG': { name: 'Gravhauger', icon: '🪨', color: '#4b5563' },
            'GAPAHUK': { name: 'Gapahuker', icon: '⛺', color: '#fb923c' },
            'HULE': { name: 'Huler / Grotter', icon: '⛰️', color: '#d946ef' },
            'VEI': { name: 'Veier', icon: '🛣️', color: '#84cc16' },
            'DIVERSE': { name: 'Diverse / Kultur', icon: '📦', color: '#6366f1' },
            'DEFAULT': { name: 'Interessepunkter', icon: '📍', color: '#14b8a6' }
        };

        async function loadReferenceData() {
            try {
                const res = await fetch('full_data.json');
                const data = await res.json();
                console.log(`Loaded ${data.features.length} features for reference.`);
                data.features.forEach(f => {
                    if (f.geometry.type !== 'Point') return; // Skip lines/polygons in reference layer for now

                    const cat = f.properties.catKey || 'DEFAULT';
                    const config = categoryMap[cat] || categoryMap['DEFAULT'];

                    const lng = f.geometry.coordinates[0];
                    const lat = f.geometry.coordinates[1];

                    L.marker([lat, lng], {
                        icon: L.divIcon({
                            className: 'reference-needle',
                            html: `<div class="marker-dot" style="background: ${config.color};"></div>`,
                            iconSize: [10, 10]
                        })
                    }).bindPopup(`
                    <div style="font-family: 'Outfit', sans-serif;">
                        <b style="color: ${config.color};">${config.name}</b><br>
                        <strong>${f.properties.name}</strong><br>
                        <hr style="opacity: 0.1; margin: 5px 0;">
                        <span style="font-size: 0.75rem; opacity: 0.7;">
                            Lat: ${lat.toFixed(6)}<br>
                            Lng: ${lng.toFixed(6)}
                        </span>
                    </div>
                `).addTo(referenceLayer);
                });
            } catch (e) { console.error("Could not load reference data", e); }
        }

        async function loadExistingFunn() {
            try {
                const res = await fetch('data/funn/ai_funn.json?t=' + Date.now());
                if (res.ok) {
                    const data = await res.json();
                    data.forEach(f => {
                        L.marker([f.lat, f.lng], {
                            icon: L.divIcon({
                                className: 'found-needle',
                                html: '<div class="marker-dot" style="background: #38bdf8; width: 12px; height: 12px; box-shadow: 0 0 10px #38bdf8;"></div>',
                                iconSize: [12, 12]
                            })
                        }).bindPopup(`
                        <div style="font-family: 'Outfit', sans-serif;">
                            <b style="color: #38bdf8;">AI POTENSIELT FUNN</b><br>
                            Kategori: ${f.cat === 'kullmiler' ? 'Kullmile' : 'Gruve/Dagstrosse'}<br>
                            Sannsynlighet: ${Math.round(f.prob * 100)}%<br>
                            <hr style="opacity: 0.1; margin: 5px 0;">
                            <span style="font-size: 0.75rem; opacity: 0.8;">
                                Lat: ${f.lat.toFixed(6)}<br>
                                Lng: ${f.lng.toFixed(6)}
                            </span>
                        </div>
                    `).addTo(foundLayer);
                    });
                }
            } catch (e) { }
        }

        async function saveTrainingData(category, geojson, layer) {
            geojson.properties = geojson.properties || {};
            geojson.properties.category = category;
            geojson.properties.timestamp = new Date().toISOString();

            try {
                const res = await fetch('save_training.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ category, geojson })
                });
                const result = await res.json();
                if (result.success) {
                    document.getElementById('status-text').textContent = `Status: Lagret ${category}`;
                    layer.closePopup();
                    // Change color of the drawn layer to indicate it's saved
                    layer.setStyle({ color: '#10b981', fillColor: '#10b981' });
                }
            } catch (e) { alert("Feil ved lagring av treningsdata"); }
        }

        document.getElementById('search-btn').onclick = async () => {
            if (!currentSearchArea) return;

            const bounds = currentSearchArea.getBounds();
            const bbox = [
                bounds.getSouth(),
                bounds.getWest(),
                bounds.getNorth(),
                bounds.getEast()
            ];

            document.getElementById('loading-overlay').style.display = 'flex';
            document.getElementById('status-text').textContent = "Status: Analyserer...";

            try {
                const res = await fetch('analyze_area.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ bbox })
                });
                const data = await res.json();
                if (data.success) {
                    foundLayer.clearLayers();
                    await loadExistingFunn();
                    let msg = `Status: Fant ${data.count} nye objekter`;
                    if (data.debug) {
                        msg += ` (Raw: ${data.debug.raw_hits}, Clustered: ${data.debug.clustered}, Lys: ${data.debug.brightness}, Område: ${data.debug.intensity_range})`;
                        console.log("AI Debug:", data.debug);
                    }
                    document.getElementById('status-text').textContent = msg;
                } else {
                    let msg = data.error;
                    if (data.debug) {
                        msg += `\nHost: ${data.debug.host}\nCode: ${data.debug.http_code}\nSize: ${data.debug.data_size} bytes`;
                        if (data.debug.curl_error) msg += `\nCURL: ${data.debug.curl_error}`;
                        console.error("AI Error Debug:", data.debug);
                    }
                    alert(`Error: ${msg}`);
                    document.getElementById('status-text').textContent = "Status: Analyse feilet (sjekk feilmelding)";
                }
            } catch (e) {
                console.error(e);
                document.getElementById('status-text').textContent = "Status: Kritisk feil i AI-motoren";
                alert(`Kritisk feil ved AI-analyse.\n\nDetaljer:\n${e.message}`);
            } finally {
                document.getElementById('loading-overlay').style.display = 'none';
            }
        };

        document.getElementById('clear-btn').onclick = async () => {
            if (!confirm("Er du sikker på at du vil slette alle AI-funn fra kartet?")) return;
            try {
                const res = await fetch('clear_funn.php');
                const data = await res.json();
                if (data.success) {
                    foundLayer.clearLayers();
                    document.getElementById('status-text').textContent = "Status: AI-funn slettet";
                }
            } catch (e) { alert("Kunne ikke slette funn"); }
        };

        // Initialize when DOM is ready
        window.onload = initMap;
    </script>

</body>

</html>