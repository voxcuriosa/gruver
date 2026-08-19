<?php
// Lite vekt autentisering for GeoRef - kun for brukere med PIN
session_start();
if (!isset($_SESSION['georef_auth'])) {
    if (isset($_POST['pin']) && $_POST['pin'] === '5877') {
        $_SESSION['georef_auth'] = true;
    } else {
        ?>
        <!DOCTYPE html>
        <html lang="no">

        <head>
            <meta charset="UTF-8">
            <title>GeoRef Login</title>
            <style>
                body {
                    background: #0b0f19;
                    color: white;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    height: 100vh;
                    font-family: sans-serif;
                }

                form {
                    background: #111827;
                    padding: 2rem;
                    border-radius: 12px;
                    border: 1px solid #374151;
                }

                input {
                    padding: 8px;
                    border-radius: 4px;
                    border: 1px solid #374151;
                    background: #1f2937;
                    color: white;
                    width: 100%;
                    box-sizing: border-box;
                }

                button {
                    margin-top: 10px;
                    width: 100%;
                    padding: 10px;
                    background: #38bdf8;
                    color: #0b0f19;
                    border: none;
                    border-radius: 4px;
                    font-weight: bold;
                    cursor: pointer;
                }
            </style>
        </head>

        <body>
            <form method="POST">
                <h3>Skriv inn PIN</h3>
                <input type="password" name="pin" placeholder="PIN-kode" autofocus>
                <button type="submit">Logg inn</button>
            </form>
        </body>

        </html>
        <?php
        exit;
    }
}

// SEO Protection: Prevent search engine indexing
header('X-Robots-Tag: noindex, nofollow');
?>
<!DOCTYPE html>
<html lang="no">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Georeferering Verktøy - Bratsbergs Amt</title>
    <meta name="robots" content="noindex, nofollow">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <link rel="stylesheet" href="https://unpkg.com/leaflet-toolbar@0.4.0-alpha.2/dist/leaflet.toolbar.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet-distortableimage@0.21.7/dist/leaflet.distortableimage.css">

    <style>
        :root {
            --bg-color: #0b0f19;
            --accent-color: #38bdf8;
            --panel-bg: rgba(17, 24, 39, 0.95);
        }

        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Outfit', sans-serif;
            background: var(--bg-color);
            color: white;
            overflow: hidden;
        }

        #map {
            height: 100vh;
            width: 100vw;
        }

        #control-panel {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 9999;
            background: var(--panel-bg);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 340px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
            backdrop-filter: blur(10px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
        }

        h2 {
            margin: 0 0 10px 0;
            color: var(--accent-color);
            font-size: 1.4rem;
            font-weight: 800;
        }

        .coord-box {
            background: rgba(0, 0, 0, 0.4);
            padding: 12px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            margin-bottom: 15px;
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.2);
            white-space: pre-wrap;
        }

        button {
            width: 100%;
            padding: 14px;
            background: var(--accent-color);
            border: none;
            border-radius: 8px;
            color: #0b0f19;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        button:hover {
            background: white;
            transform: translateY(-2px);
        }

        .instructions {
            font-size: 0.85rem;
            color: #94a3b8;
            line-height: 1.5;
            margin-bottom: 15px;
        }

        .hint {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 10px;
            font-style: italic;
        }

        .slider-container {
            margin-bottom: 15px;
            background: rgba(0, 0, 0, 0.4);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .slider-container label {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            margin-bottom: 8px;
            color: #94a3b8;
        }

        input[type=range] {
            width: 100%;
            height: 4px;
            background: #1e293b;
            border-radius: 5px;
            outline: none;
            -webkit-appearance: none;
        }

        input[type=range]::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 14px;
            height: 14px;
            background: var(--accent-color);
            cursor: pointer;
            border-radius: 50%;
        }

        /* Sikre at verktøylinje-ikonene er synlige */
        .leaflet-toolbar-icon {
            color: #333 !important;
            display: flex !important;
            align-items: center;
            justify-content: center;
            background-color: white !important;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .status-loading {
            background: #f59e0b;
            color: #000;
        }

        .status-ok {
            background: #10b981;
            color: #fff;
        }

        .status-error {
            background: #ef4444;
            color: #fff;
        }

        .mode-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px;
            margin-bottom: 12px;
        }

        .btn-mode {
            padding: 8px 4px;
            font-size: 0.65rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            width: 100%;
        }

        .btn-mode.active {
            background: var(--accent-color);
            color: #0b0f19;
            border-color: var(--accent-color);
        }

        .lock-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.05);
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
            cursor: pointer;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .lock-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .lock-toggle i {
            margin-right: 8px;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 15px;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Sikre at bilderaden er på riktig nivå */
        .leaflet-image-pane {
            z-index: 450;
        }

        /* Ny pane for nåler så de ALLTID er øverst */
        .leaflet-pin-pane {
            z-index: 700 !important;
        }

        .pin-mode-toggle {
            display: flex;
            background: rgba(0, 0, 0, 0.4);
            padding: 5px;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .pin-mode-btn {
            flex: 1;
            padding: 8px;
            font-size: 0.7rem;
            background: none;
            border: none;
            color: #94a3b8;
            cursor: pointer;
            border-radius: 6px;
        }

        .pin-mode-btn.active {
            background: var(--accent-color);
            color: #0b0f19;
            font-weight: bold;
        }

        #pin-setup-controls {
            background: rgba(56, 189, 248, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px solid var(--accent-color);
        }

        .margin-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 5px;
            margin-top: 5px;
        }

        .margin-item {
            font-size: 0.65rem;
            color: #94a3b8;
        }

        .upload-section {
            background: rgba(56, 189, 248, 0.1);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 15px;
            border: 1px dashed var(--accent-color);
            text-align: center;
        }

        .upload-section input {
            display: none;
        }

        .upload-label {
            cursor: pointer;
            font-size: 0.8rem;
            color: var(--accent-color);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .btn-danger {
            background: #ef4444 !important;
            color: white !important;
            margin-top: 10px;
        }

        .btn-success {
            background: #10b981 !important;
            color: white !important;
            margin-bottom: 5px;
        }

        .custom-pin {
            background: none !important;
            border: none !important;
        }
    </style>
</head>

<body oncontextmenu="return false;">

    <div id="control-panel">
        <h2>Georeferering</h2>
        <div class="instructions" id="main-instructions">
            <b>Slik gjør du det:</b>
            <ol style="padding-left: 18px; margin: 10px 0;">
                <li>Last opp et bilde (skanning av kart).</li>
                <li>Dra bildet omtrent dit det skal ligge. Bruk <b>skalering</b> og <b>opacity</b> for å finjustere.
                </li>
                <li>Trykk på <b>📌 Nåler</b>. Plasser nålene på kjente landemerker (f.eks. kirker, elvebuer) på
                    <i>bildet</i> først.
                </li>
                <li>Bytt til <b>"2. Dra til kart"</b> og dra de samme nålene til der de ligger i virkeligheten.</li>
                <li>Bruk <b>Beskjæring</b> hvis bildet har hvite kanter eller tekst du vil fjerne.</li>
                <li>Trykk <b>Publiser til Kart</b> når du er fornøyd.</li>
            </ol>
        </div>

        <div id="image-status" class="status-badge status-loading">Laster bilde...</div>

        <div class="upload-section" id="upload-box">
            <label class="upload-label" for="file-upload">
                <i class="fa-solid fa-cloud-arrow-up" style="font-size: 1.5rem;"></i>
                <span>Trykk for å laste opp bilde</span>
            </label>
            <input type="file" id="file-upload" accept="image/*" onchange="handleUpload(this)">
        </div>

        <div id="pin-setup-controls" style="display:none">
            <div
                style="font-size: 0.8rem; margin-bottom: 8px; font-weight: bold; display: flex; align-items: center; justify-content: space-between;">
                <span>Avansert Nål-modus</span>
                <span id="pin-number"
                    style="background: var(--accent-color); color: #0b0f19; padding: 2px 6px; border-radius: 10px; font-size: 0.6rem;">4
                    nåler</span>
            </div>
            <div class="pin-mode-toggle">
                <button class="pin-mode-btn active" id="btn-pin-setup" onclick="setPinPhase('setup')">1. Sett på
                    bilde</button>
                <button class="pin-mode-btn" id="btn-pin-align" onclick="setPinPhase('align')">2. Dra til kart</button>
                <button class="pin-mode-btn" style="background: #a855f7;" onclick="addExtraPin()" id="btn-add-pin">
                    <i class="fa-solid fa-plus"></i> Nål</button>
            </div>
            <div class="instructions" style="font-size: 0.7rem; margin-bottom: 0;" id="pin-instr">
                Plasser de 4 nålene på kjente steder <b>inne i selve bildet</b> (f.eks. kirketårn, veikryss).
            </div>
        </div>

        <div id="clip-controls"
            style="display:none; background: rgba(16, 185, 129, 0.1); padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #10b981;">
            <div style="font-size: 0.8rem; margin-bottom: 8px; font-weight: bold;">Beskjæring (Rektangel)</div>
            <div class="instructions" style="font-size: 0.7rem;">
                Dra i den mørke firkanten over bildet for å velge området du vil beholde. Du kan også flytte hele
                firkanten.
            </div>
            <button onclick="executeVirtualCrop()"
                style="background:#10b981; color:white; font-size:0.75rem; padding:8px; width:100%; margin-bottom:5px;">
                <i class="fa-solid fa-check"></i> Utfør beskjæring
            </button>
            <button id="btn-download-crop"
                style="display:none; background:#3b82f6; color:white; font-size:0.75rem; padding:8px; width:100%;">
                <i class="fa-solid fa-download"></i> Last ned beskjært bilde
            </button>
            <div class="hint" style="color:#10b981; margin-top:5px;">Dette skjer kun i nettleseren din. Ingenting endres
                på serveren!</div>
        </div>

        <div class="mode-buttons">
            <button class="btn-mode" id="mode-drag" onclick="setMode('drag')">Flytt</button>
            <button class="btn-mode" id="mode-distort" onclick="setMode('distort')">Hjørner</button>
            <button class="btn-mode" id="mode-pins" onclick="togglePins()">📌 Nåler</button>
            <button class="btn-mode" id="mode-clip" onclick="toggleClip()">📐 Beskjær</button>
            <button class="btn-mode" id="mode-rotate" onclick="setMode('rotate')">Roter</button>
            <button class="btn-mode" id="mode-scale" onclick="setMode('scale')">Skaler</button>
            <button class="btn-mode" id="mode-lock" onclick="setMode('lock')">Lås bilde</button>
        </div>

        <div class="lock-toggle" id="map-lock-btn" onclick="toggleMapLock()">
            <span><i class="fa-solid fa-unlock" id="lock-icon"></i> Lås kart-panorering</span>
            <small id="lock-status">AV</small>
        </div>


        <div class="slider-container" style="padding: 8px;">
            <label style="margin-bottom: 4px;">Gjennomsiktighet: <span id="opacity-val">50%</span></label>
            <input type="range" min="0" max="1" step="0.05" value="0.5" oninput="updateOpacity(this.value)">
        </div>

        <div class="slider-container" style="padding: 8px;">
            <label style="margin-bottom: 4px;">Bredde (Skalering): <span id="width-val">100%</span></label>
            <input type="range" min="0.1" max="3" step="0.01" value="1" id="width-slider" oninput="rescaleImage()">
        </div>

        <div class="slider-container" style="padding: 8px;">
            <label style="margin-bottom: 4px;">Høyde (Skalering): <span id="height-val">100%</span></label>
            <input type="range" min="0.1" max="3" step="0.01" value="1" id="height-slider" oninput="rescaleImage()">
        </div>

        <div class="coord-box" id="coord-output">Venter på bilde...</div>

        <button class="btn-success" onclick="saveMetadata('save')">
            <i class="fa-solid fa-floppy-disk"></i> Lagre Georef
        </button>
        <button class="btn-mode" style="background: #a855f7; color: white;" onclick="saveMetadata('publish')">
            <i class="fa-solid fa-globe"></i> Publiser til Kart
        </button>

        <button class="btn-danger" id="btn-clear" onclick="clearMap()">
            <i class="fa-solid fa-trash-can"></i> Tøm kart (Start på nytt)
        </button>
        <div class="hint">Referanse: Bruk Skien Kirke og Skienselva for best presisjon.</div>
    </div>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-toolbar@0.4.0-alpha.2/dist/leaflet.toolbar.js"></script>
    <script>
        // Alias L.Toolbar2 til L.Toolbar for kompatibilitet med distortableimage
        if (typeof L !== 'undefined' && typeof L.Toolbar2 !== 'undefined') {
            L.Toolbar = L.Toolbar2;
            console.log("Aliased L.Toolbar2 to L.Toolbar");
        }
    </script>
    <script src="https://unpkg.com/leaflet-distortableimage@0.21.7/dist/vendor.js"></script>
    <script src="https://unpkg.com/leaflet-distortableimage@0.21.7/dist/leaflet.distortableimage.js"></script>

    <script>
        const map = L.map('map', {
            zoomControl: false
        }).setView([59.20, 9.60], 13); // Grenland fokus

        L.control.zoom({ position: 'bottomright' }).addTo(map);

        // Topografisk kart
        const topoLayer = L.tileLayer('https://cache.kartverket.no/v1/wmts/1.0.0/topo/default/webmercator/{z}/{y}/{x}.png', {
            attribution: '&copy; Kartverket',
            maxZoom: 22,
            maxNativeZoom: 18,
            updateWhenIdle: true
        });

        // Satellittkart
        const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            attribution: 'Tiles &copy; Esri',
            maxZoom: 22,
            maxNativeZoom: 18,
            updateWhenIdle: true
        });

        topoLayer.addTo(map);

        const baseMaps = {
            "Topografisk": topoLayer,
            "Satellitt": satelliteLayer
        };

        L.control.layers(baseMaps, {}, { position: 'bottomright' }).addTo(map);

        // Initialiserer variabler globalt
        let img;
        let imgPath = null;
        let baseCorners = null;
        let pins = [];
        let pinUVs = [
            { u: 0.2, v: 0.2 },
            { u: 0.8, v: 0.2 },
            { u: 0.2, v: 0.8 },
            { u: 0.8, v: 0.8 }
        ];
        let isDraggingPin = false;
        let centerGrip = null;
        let cropSelector = null;
        let cropHandles = [];
        let currentAbsUV = { u1: 0, v1: 0, u2: 1, v2: 1 };
        let currentCroppedBlob = null;
        let mapLocked = false;
        let pinPhase = 'setup';

        // Oppretter en egen pane for å sikre at bildet ligger øverst
        map.createPane('imagePane');
        map.getPane('imagePane').style.zIndex = 650;
        map.getPane('imagePane').style.pointerEvents = 'auto';

        const status = document.getElementById('image-status');
        console.log("Sjekker biblioteker...");
        const hasLeaflet = typeof L !== 'undefined';
        const hasToolbar = hasLeaflet && typeof L.Toolbar2 !== 'undefined';
        const hasDistort = hasLeaflet && typeof L.distortableImageOverlay === 'function';

        console.log("Leaflet:", hasLeaflet);
        console.log("Leaflet Toolbar2:", hasToolbar);
        console.log("DistortableImageOverlay:", hasDistort);

        // Fiks for å forhindre av-markering når man trykker på kartet
        if (hasDistort) {
            const oldDeselect = L.DistortableImageOverlay.prototype.deselect;
            L.DistortableImageOverlay.prototype.deselect = function () {
                // Vi tillater bare deselect hvis vi eksplisitt ønsker det (f.eks. ved bytte av bilde)
                if (window.allowDeselect === true) {
                    return oldDeselect.apply(this, arguments);
                }
                console.log("Deselect blokkert for stabilitet");
                return this;
            };

            // Tving "editable" status
            L.DistortableImageOverlay.prototype.isEditable = function () { return true; };
        }
        window.allowDeselect = false;

        if (hasDistort) {
            // Laster ikke bilde før opplasting

            // Lag en egen pane for nålene
            map.createPane('pinPane');
            map.getPane('pinPane').style.zIndex = 700;
            map.getPane('pinPane').style.pointerEvents = 'none'; // La klikk gå igjennom selve panelet, men ikke markørene

            status.innerText = "Biblioteker OK. Laster bilde...";
            status.className = "status-badge status-loading";
        } else {
            let errorMsg = "Feil: ";
            if (!hasLeaflet) errorMsg += "Leaflet mangler. ";
            if (!hasToolbar) errorMsg += "Toolbar mangler. ";
            if (!hasDistort) errorMsg += "Distort-plugin mangler. ";

            console.error(errorMsg);
            status.innerText = errorMsg;
            status.className = "status-badge status-error";
        }

        async function handleUpload(input) {
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            const MAX_SIZE = 10 * 1024 * 1024; // Økt til 10MB
            if (file.size > 10 * 1024 * 1024) {
                alert('Bildet er for stort. Maksgrense er 10MB.');
                return;
            }

            status.innerText = "Laster opp...";
            status.className = "status-badge status-loading";

            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    imgPath = result.filePath;
                    loadImageOnMap(imgPath);
                    document.getElementById('upload-box').style.display = 'none';
                } else {
                    alert("Opplasting feilet: " + result.error);
                }
            } catch (e) {
                console.error(e);
                alert("Teknisk feil ved opplasting.");
            }
        }

        function loadImageOnMap(url) {
            if (img) map.removeLayer(img);

            // Last inn bilde først for å finne aspektrate (forhindre sammentrykket bilde)
            const tempImg = new Image();
            tempImg.crossOrigin = "anonymous";
            tempImg.onload = () => {
                const w = tempImg.naturalWidth;
                const h = tempImg.naturalHeight;
                const ratio = w / h;

                const center = map.getCenter();
                // Korriger for breddegrad (Mercator-projeksjon)
                // Ved 59 grader nord er 1 grad lunde ca 0.515 ganger så lang som 1 grad breddegrad.
                const cosLat = Math.cos(center.lat * Math.PI / 180);

                // Base-størrelse (0.01 grader bredde)
                let dLat, dLng;
                if (ratio >= 1) {
                    // Landskap eller kvadratisk
                    dLat = 0.01;
                    dLng = (0.01 * ratio) / cosLat;
                } else {
                    // Portrett
                    dLat = (0.01 / ratio);
                    dLng = 0.01 / cosLat;
                }

                img = L.distortableImageOverlay(url, {
                    corners: [
                        L.latLng(center.lat + dLat, center.lng - dLng),
                        L.latLng(center.lat + dLat, center.lng + dLng),
                        L.latLng(center.lat - dLat, center.lng - dLng),
                        L.latLng(center.lat - dLat, center.lng + dLng)
                    ],
                    opacity: 0.5,
                    editable: true,
                    selected: true,
                    pane: 'imagePane'
                }).addTo(map);

                img.on('load', () => {
                    status.innerText = "Bilde OK";
                    status.className = "status-badge status-ok";
                    enableEditing();
                    updateOutput();
                });

                img.on('drag rotate scale', () => {
                    updatePinsFromImage();
                    updateOutput();
                });


                img.on('click', (e) => {
                    L.DomEvent.stopPropagation(e);
                    enableEditing();
                });


                // Lagre baseCorners for skalering med en gang
                const corners = img.getCorners();
                baseCorners = corners.map(c => L.latLng(c.lat, c.lng));

                img.on('edit', () => {
                    const c = img.getCorners();
                    if (c && c.length >= 4) {
                        baseCorners = c.map(cl => L.latLng(cl.lat, cl.lng));
                        document.getElementById('width-slider').value = 1;
                        document.getElementById('height-slider').value = 1;
                        document.getElementById('width-val').innerText = '100%';
                        document.getElementById('height-val').innerText = '100%';
                    }
                });

                // Nullstill slidere
                document.getElementById('width-slider').value = 1;
                document.getElementById('height-slider').value = 1;
                document.getElementById('width-val').innerText = '100%';
                document.getElementById('height-val').innerText = '100%';

                // Oppdater koordinat-output
                updateOutput();
            };
            tempImg.onerror = () => {
                status.innerText = "Kunne ikke laste bilde-info";
                status.className = "status-badge status-error";
            };
            tempImg.src = url;
        }

        function clearMap() {
            if (!confirm("Vil du tømme kartet og starte på nytt?")) return;
            if (img) map.removeLayer(img);
            pins.forEach(p => map.removeLayer(p));
            pins = [];
            if (centerGrip) map.removeLayer(centerGrip);
            centerGrip = null;
            if (cropSelector) map.removeLayer(cropSelector);
            cropSelector = null;
            cropHandles.forEach(h => map.removeLayer(h));
            cropHandles = [];

            imgPath = null;
            baseCorners = null; // Nullstill base-hjørner for skalering
            currentAbsUV = { u1: 0, v1: 0, u2: 1, v2: 1 };

            // Nullstill slidere
            document.getElementById('width-slider').value = 1;
            document.getElementById('height-slider').value = 1;
            document.getElementById('width-val').innerText = '100%';
            document.getElementById('height-val').innerText = '100%';

            document.getElementById('upload-box').style.display = 'block';
            document.getElementById('pin-setup-controls').style.display = 'none';
            document.getElementById('clip-controls').style.display = 'none';
            document.getElementById('coord-output').innerText = "Venter på bilde...";
            status.innerText = "Klar til opplasting";
            status.className = "status-badge status-loading";
        }

        async function saveMetadata(action) {
            if (!img) return;

            let mapName = "";
            let attributionName = "";
            let attributionLink = "";

            if (action === 'publish') {
                mapName = prompt("Hva skal kartet hete i menyen?");
                if (!mapName) return;

                attributionName = prompt("Kreditering (navn på kilde/fotograf):", "");
                attributionLink = prompt("Link til kilde (valgfritt):", "");

                if (!confirm("Er du sikker på at du vil publisere '" + mapName + "' til hovedkartet?")) return;
            }

            let finalPath = imgPath;

            try {
                // Hvis bildet er beskjært, last opp beskjæringen først
                if (currentCroppedBlob) {
                    const uploadResult = await uploadBlob(currentCroppedBlob);
                    if (uploadResult.success) {
                        finalPath = uploadResult.filePath;
                    } else {
                        throw new Error("Kunne ikke laste opp beskjært bilde: " + uploadResult.error);
                    }
                }

                const payload = {
                    action: action,
                    name: mapName,
                    attributionName: attributionName,
                    attributionLink: attributionLink,
                    imagePath: finalPath,
                    corners: img.getCorners()
                };

                const response = await fetch('save_georef.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const result = await response.json();
                if (result.success) {
                    alert(action === 'publish' ? "Kartet er nå publisert!" : "Georeferering er lagret.");
                } else {
                    alert("Feil: " + result.error);
                }
            } catch (e) {
                console.error(e);
                alert("Teknisk feil ved lagring: " + e.message);
            }
        }

        async function uploadBlob(blob) {
            const formData = new FormData();
            formData.append('image', blob, 'beskjaert_kart.jpg');
            const res = await fetch('upload.php', {
                method: 'POST',
                body: formData
            });
            return await res.json();
        }

        let currentMode = 'distort';

        function enableEditing() {
            if (img) {
                if (img.select) img.select();
                if (img.editing) {
                    img.editing.enable();
                    console.log("Redigering tvunget aktivert");
                    setMode(currentMode);
                }
            } else {
                console.warn("img ikke tilgjengelig");
            }
        }

        function setMode(mode) {
            currentMode = mode;
            if (img && img.editing) {
                try {
                    img.editing.setMode(mode);
                    console.log("Modus satt til:", mode);

                    // Oppdater knapper
                    document.querySelectorAll('.btn-mode').forEach(btn => btn.classList.remove('active'));
                    const activeBtn = document.getElementById('mode-' + mode);
                    if (activeBtn) activeBtn.classList.add('active');

                    // Automatisk oppdater nåler hvis de er på
                    if (pins.length > 0) {
                        updatePinsFromImage();
                    }
                } catch (e) {
                    console.error("Kunne ikke sette modus:", e);
                }
            }
        }

        pinPhase = 'setup';

        function setPinPhase(phase) {
            pinPhase = phase;
            document.querySelectorAll('.pin-mode-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('btn-pin-' + phase).classList.add('active');

            const instr = document.getElementById('pin-instr');
            const setupBox = document.getElementById('pin-setup-controls');

            if (phase === 'setup') {
                instr.innerHTML = "Dra nålene til landemerker <b>på bildet</b>. Da 'låses' de fast til det punktet.";
                setupBox.style.background = "rgba(56, 189, 248, 0.1)";
            } else {
                instr.innerHTML = "Dra nålene til der landemerkene er <b>i virkeligheten</b> (på kartet).";
                setupBox.style.background = "rgba(16, 185, 129, 0.1)";
            }
        }

        function togglePins() {
            const btn = document.getElementById('mode-pins');
            const setupBox = document.getElementById('pin-setup-controls');

            if (pins.length > 0) {
                pins.forEach(p => map.removeLayer(p));
                pins = [];
                if (centerGrip) map.removeLayer(centerGrip);
                centerGrip = null;
                btn.classList.remove('active');
                setupBox.style.display = 'none';
            } else {
                setupBox.style.display = 'block';
                const corners = img.getCorners();

                // Start med 4 pins som standard
                pinUVs = [
                    { u: 0.2, v: 0.2 }, { u: 0.8, v: 0.2 }, { u: 0.2, v: 0.8 }, { u: 0.8, v: 0.8 }
                ];
                
                pinUVs.forEach((uv, i) => {
                    createPin(i);
                });

                // Legg til senter-grep
                const center = getAverageLatLng(corners);
                centerGrip = L.marker(center, {
                    draggable: true,
                    pane: 'pinPane',
                    icon: L.divIcon({
                        className: 'custom-pin',
                        html: `<div style="background:#fff; width:40px; height:40px; border-radius:50%; border:4px solid var(--accent-color); display:flex; align-items:center; justify-content:center; color:var(--accent-color); font-size:22px; box-shadow:0 0 20px rgba(0,0,0,0.7)"><i class="fa-solid fa-arrows-up-down-left-right"></i></div>`,
                        iconAnchor: [20, 20]
                    })
                }).addTo(map);

                centerGrip.getElement().style.pointerEvents = 'auto';

                centerGrip.on('dragstart', function () {
                    this._startCorners = img.getCorners().map(c => L.latLng(c.lat, c.lng));
                    this._startPos = this.getLatLng();
                });

                centerGrip.on('drag', function () {
                    const deltaLat = this.getLatLng().lat - this._startPos.lat;
                    const deltaLng = this.getLatLng().lng - this._startPos.lng;
                    const newCorners = this._startCorners.map(c => L.latLng(c.lat + deltaLat, c.lng + deltaLng));
                    img.setCorners(newCorners);
                    updateOutput();
                    updatePinsFromImage();
                });

                btn.classList.add('active');
                setPinPhase('setup');
            }
        }

        function createPin(i) {
            const corners = img.getCorners();
            const labels = ["1", "2", "3", "4", "5", "6", "7", "8"];
            const uv = pinUVs[i];
            const pos = interpolateUV(corners, uv.u, uv.v);
            
            const pin = L.marker(pos, {
                draggable: true,
                pane: 'pinPane',
                icon: L.divIcon({
                    className: 'custom-pin',
                    html: `<div style="background:var(--accent-color); width:32px; height:32px; border-radius:50%; border:3px solid white; display:flex; align-items:center; justify-content:center; color:#0b0f19; font-weight:bold; font-size:14px; box-shadow:0 0 15px rgba(0,0,0,0.6)">${labels[i]}</div>`,
                    iconAnchor: [16, 16]
                })
            }).addTo(map);

            pin.getElement().style.pointerEvents = 'auto';

            pin.on('dragstart', () => { isDraggingPin = true; });
            pin.on('dragend', () => {
                isDraggingPin = false;
                updatePinsFromImage(); 
            });

            pin.on('drag', function () {
                if (pinPhase === 'setup') {
                    pinUVs[i] = calculateUVFromPos(img.getCorners(), this.getLatLng());
                } else {
                    updateCornersFromPins();
                }
                updateOutput();
                updateCenterGrip();
            });

            pins.push(pin);
        }

        function addExtraPin() {
            if (!img) return;
            if (pins.length >= 8) {
                alert("Maksimum 8 nåler er tillatt.");
                return;
            }
            
            // Finn et ledig UV-punkt i midten av bildet
            const nextIdx = pins.length;
            const extraUVs = [
                { u: 0.5, v: 0.2 }, { u: 0.5, v: 0.8 }, { u: 0.2, v: 0.5 }, { u: 0.8, v: 0.5 }
            ];
            
            // Bruk en av de ekstra UV-ene basert på hvor mange vi har
            const uv = extraUVs[nextIdx - 4] || { u: 0.5, v: 0.5 };
            pinUVs.push(uv);
            createPin(nextIdx);
            
            // Hvis vi er i align phase, må vi kanskje oppdatere h-matrisen
            if (pinPhase === 'align') {
                updateOutput();
            }
        }

            function interpolateUV(corners, u, v) {
                // Vi bruker Homografi for å være konsistent med bildevisningen
                const src = [[0, 0], [1, 0], [0, 1], [1, 1]];
                const dst = corners.map(c => [c.lng, c.lat]);
                const h = solveHomography(src, dst);
                if (!h) return corners[0]; // Fallback

                const res = applyHomography(h, [u, v]);
                return L.latLng(res[1], res[0]);
            }

            function calculateUVFromPos(corners, pos) {
                // Finn UV ved å reversere homografien
                const src = [[0, 0], [1, 0], [0, 1], [1, 1]];
                const dst = corners.map(c => [c.lng, c.lat]);
                const h = solveHomography(dst, src); // Reversert: Kart -> UV

                if (!h) return { u: 0.5, v: 0.5 };
                const res = applyHomography(h, [pos.lng, pos.lat]);
                return { u: Math.max(0, Math.min(1, res[0])), v: Math.max(0, Math.min(1, res[1])) };
            }

            function updateCornersFromPins() {
                if (pins.length < 4) return;

                const src = pinUVs.map(uv => [uv.u, uv.v]);
                const dst = pins.map(p => [p.getLatLng().lng, p.getLatLng().lat]);

                const h = solveHomography(src, dst);
                if (!h) return;

                const cornersUV = [[0, 0], [1, 0], [0, 1], [1, 1]];
                const newCorners = cornersUV.map(uv => {
                    const res = applyHomography(h, uv);
                    return L.latLng(res[1], res[0]);
                });

                img.setCorners(newCorners);
            }

            function solveHomography(src, dst) {
                const n = src.length;
                if (n < 4) return null;

                // Build ATA (8x8) and ATb (8x1) for Least Squares solution
                const ata = Array.from({ length: 8 }, () => new Array(8).fill(0));
                const atb = new Array(8).fill(0);

                for (let i = 0; i < n; i++) {
                    const [x, y] = src[i];
                    const [u, v] = dst[i];

                    // Equations:
                    // h0*x + h1*y + h2 - h6*u*x - h7*u*y = u
                    // h3*x + h4*y + h5 - h6*v*x - h7*v*y = v
                    const r1 = [x, y, 1, 0, 0, 0, -u * x, -u * y];
                    const r2 = [0, 0, 0, x, y, 1, -v * x, -v * y];
                    const b = [u, v];

                    const rows = [r1, r2];

                    for (let r = 0; r < 2; r++) {
                        for (let j = 0; j < 8; j++) {
                            for (let k = 0; k < 8; k++) {
                                ata[j][k] += rows[r][j] * rows[r][k];
                            }
                            atb[j] += rows[r][j] * b[r];
                        }
                    }
                }

                // Prepare augmented matrix [ata | atb] for Gauss-Jordan
                const matrix = ata.map((row, i) => [...row, atb[i]]);
                const res = gaussJordan(matrix);
                return res ? [...res, 1] : null;
            }

            function gaussJordan(m) {
                const h = m.length;
                const w = m[0].length;
                for (let i = 0; i < h; i++) {
                    let max = i;
                    for (let j = i + 1; j < h; j++) if (Math.abs(m[j][i]) > Math.abs(m[max][i])) max = j;
                    [m[i], m[max]] = [m[max], m[i]];
                    const pivot = m[i][i];
                    if (Math.abs(pivot) < 1e-10) return null;
                    for (let j = i; j < w; j++) m[i][j] /= pivot;
                    for (let j = 0; j < h; j++) {
                        if (i === j) continue;
                        const factor = m[j][i];
                        for (let k = i; k < w; k++) m[j][k] -= factor * m[i][k];
                    }
                }
                return m.map(row => row[w - 1]);
            }

            function applyHomography(h, uv) {
                const [x, y] = uv;
                const w = h[6] * x + h[7] * y + h[8];
                return [
                    (h[0] * x + h[1] * y + h[2]) / w,
                    (h[3] * x + h[4] * y + h[5]) / w
                ];
            }

            function getAverageLatLng(latlngs) {
                const lats = latlngs.map(c => c.lat);
                const lngs = latlngs.map(c => c.lng);
                return L.latLng(
                    (Math.max(...lats) + Math.min(...lats)) / 2,
                    (Math.max(...lngs) + Math.min(...lngs)) / 2
                );
            }

            function updateCenterGrip() {
                if (centerGrip && img) {
                    centerGrip.setLatLng(getAverageLatLng(img.getCorners()));
                }
            }

            function updatePinsFromImage() {
                if (img && !isDraggingPin) {
                    const corners = img.getCorners();
                    pins.forEach((p, i) => {
                        const uv = pinUVs[i];
                        if (uv) {
                            p.setLatLng(interpolateUV(corners, uv.u, uv.v));
                        }
                    });
                    updateCenterGrip();
                }
            }



            function toggleClip() {
                const btn = document.getElementById('mode-clip');
                const clipBox = document.getElementById('clip-controls');
                if (cropSelector) {
                    map.removeLayer(cropSelector);
                    cropSelector = null;
                    cropHandles.forEach(h => map.removeLayer(h));
                    cropHandles = [];
                    btn.classList.remove('active');
                    clipBox.style.display = 'none';
                } else {
                    clipBox.style.display = 'block';
                    const corners = img.getCorners();

                    // Initial rektangel: Litt mindre enn hele bildet
                    const nw = interpolateUV(corners, 0.1, 0.1);
                    const se = interpolateUV(corners, 0.9, 0.9);

                    cropSelector = L.rectangle([nw, se], {
                        color: "#10b981",
                        weight: 3,
                        fillColor: "#10b981",
                        fillOpacity: 0.4,
                        pane: 'pinPane',
                        interactive: false // Håndtakene tar seg av flytting/endring
                    }).addTo(map);

                    // Opprett 4 håndtak (hjørner)
                    const bounds = cropSelector.getBounds();
                    const points = [
                        bounds.getNorthWest(),
                        bounds.getNorthEast(),
                        bounds.getSouthWest(),
                        bounds.getSouthEast()
                    ];

                    points.forEach((pos, i) => {
                        const handle = L.marker(pos, {
                            draggable: true,
                            pane: 'pinPane',
                            icon: L.divIcon({
                                className: 'custom-pin',
                                html: `<div style="background:#10b981; width:14px; height:14px; border:2px solid white; box-shadow:0 0 5px rgba(0,0,0,0.5)"></div>`,
                                iconAnchor: [7, 7]
                            })
                        }).addTo(map);

                        handle.getElement().style.pointerEvents = 'auto';

                        handle.on('drag', () => {
                            updateCropFromHandles(i);
                        });

                        cropHandles.push(handle);
                    });

                    btn.classList.add('active');
                }
            }

            function updateCropFromHandles(draggedIdx) {
                const nw = cropHandles[0].getLatLng();
                const ne = cropHandles[1].getLatLng();
                const sw = cropHandles[2].getLatLng();
                const se = cropHandles[3].getLatLng();

                // Synkroniser andre håndtak for å opprettholde et rektangel
                if (draggedIdx === 0) { // NW
                    cropHandles[1].setLatLng([nw.lat, ne.lng]);
                    cropHandles[2].setLatLng([sw.lat, nw.lng]);
                } else if (draggedIdx === 1) { // NE
                    cropHandles[0].setLatLng([ne.lat, nw.lng]);
                    cropHandles[3].setLatLng([se.lat, ne.lng]);
                } else if (draggedIdx === 2) { // SW
                    cropHandles[0].setLatLng([nw.lat, sw.lng]);
                    cropHandles[3].setLatLng([sw.lat, se.lng]);
                } else if (draggedIdx === 3) { // SE
                    cropHandles[1].setLatLng([ne.lat, se.lng]);
                    cropHandles[2].setLatLng([se.lat, sw.lng]);
                }

                const newBounds = L.latLngBounds(cropHandles[0].getLatLng(), cropHandles[3].getLatLng());
                cropSelector.setBounds(newBounds);
            }

            function updateCropSelector() {
                if (!img || !cropSelector) return;
                // Hvis bildet flyttes, oppdaterer vi rektangelet slik at det fortsatt dekker det aktive utsnittet
                // Dette er viktig hvis man flytter bildet mens beskjøringsverktøyet er åpent.
                const corners = img.getCorners();
                const nw = interpolateUV(corners, currentAbsUV.u1, currentAbsUV.v1);
                const se = interpolateUV(corners, currentAbsUV.u2, currentAbsUV.v2);

                cropSelector.setBounds(L.latLngBounds(nw, se));

                // Oppdater håndtakene også
                if (cropHandles.length === 4) {
                    cropHandles[0].setLatLng(nw);
                    cropHandles[1].setLatLng(interpolateUV(corners, currentAbsUV.u2, currentAbsUV.v1));
                    cropHandles[2].setLatLng(interpolateUV(corners, currentAbsUV.u1, currentAbsUV.v2));
                    cropHandles[3].setLatLng(se);
                }
            }

            async function executeVirtualCrop() {
                if (!cropSelector) return;

                const selBounds = cropSelector.getBounds();
                const cornersOld = img.getCorners();

                // Finn UV-koordinater for rektangelet RELATIVT til nåværende visning
                const uvNW = calculateUVFromPos(cornersOld, selBounds.getNorthWest());
                const uvSE = calculateUVFromPos(cornersOld, selBounds.getSouthEast());

                // Beregn nye ABSOLUTTE UV-koordinater (mot originalfila)
                const rangeU = currentAbsUV.u2 - currentAbsUV.u1;
                const rangeV = currentAbsUV.v2 - currentAbsUV.v1;

                const nextAbsUV = {
                    u1: currentAbsUV.u1 + uvNW.u * rangeU,
                    v1: currentAbsUV.v1 + uvNW.v * rangeV,
                    u2: currentAbsUV.u1 + uvSE.u * rangeU,
                    v2: currentAbsUV.v1 + uvSE.v * rangeV
                };

                // Nye LatLng-hjørner for bildet (bevarer posisjon i kartet)
                const newNW = interpolateUV(cornersOld, uvNW.u, uvNW.v);
                const newNE = interpolateUV(cornersOld, uvSE.u, uvNW.v);
                const newSW = interpolateUV(cornersOld, uvNW.u, uvSE.v);
                const newSE = interpolateUV(cornersOld, uvSE.u, uvSE.v);

                // Lag canvas og klipp fra ORIGINAL-BILDE (imgPath)
                const sourceImg = new Image();
                sourceImg.crossOrigin = "anonymous";
                sourceImg.src = imgPath;

                sourceImg.onload = function () {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');

                    // Bruk de ABSOLUTTE UV-verdiene mot original-piksler
                    const x = nextAbsUV.u1 * sourceImg.width;
                    const y = nextAbsUV.v1 * sourceImg.height;
                    const w = (nextAbsUV.u2 - nextAbsUV.u1) * sourceImg.width;
                    const h = (nextAbsUV.v2 - nextAbsUV.v1) * sourceImg.height;

                    canvas.width = w;
                    canvas.height = h;

                    ctx.drawImage(sourceImg, x, y, w, h, 0, 0, w, h);

                    canvas.toBlob(function (blob) {
                        currentCroppedBlob = blob;
                        const blobUrl = URL.createObjectURL(blob);

                        // Oppdater bildet i Leaflet med perfekte rektangulære hjørner fra utsnittet
                        img.setCorners([
                            selBounds.getNorthWest(),
                            selBounds.getNorthEast(),
                            selBounds.getSouthWest(),
                            selBounds.getSouthEast()
                        ]);

                        // Oppdater kilden
                        const el = img.getElement();
                        if (el) { el.src = blobUrl; }

                        // Oppdater nåværende sporing
                        currentAbsUV = nextAbsUV;

                        // Vis nedlastingsknapp
                        const dlBtn = document.getElementById('btn-download-crop');
                        dlBtn.style.display = 'block';
                        dlBtn.onclick = function () {
                            const a = document.createElement('a');
                            a.href = blobUrl;
                            a.download = "georef_crop.jpg";
                            a.click();
                        };

                        alert("Bildet er klippet! Du kan nå fortsette georefereringen med det nye utsnittet.");

                        toggleClip(); // Lukk verktøyet
                        updateOutput();
                    }, 'image/jpeg', 0.9);
                };
            }

            function toggleMapLock() {
                mapLocked = !mapLocked;
                const icon = document.getElementById('lock-icon');
                const statusText = document.getElementById('lock-status');
                const btn = document.getElementById('map-lock-btn');

                if (mapLocked) {
                    map.dragging.disable();
                    map.touchZoom.disable();
                    map.doubleClickZoom.disable();
                    map.scrollWheelZoom.disable();
                    icon.className = "fa-solid fa-lock";
                    statusText.innerText = "PÅ";
                    btn.style.borderColor = "var(--accent-color)";
                    btn.style.color = "var(--accent-color)";
                } else {
                    map.dragging.enable();
                    map.touchZoom.enable();
                    map.doubleClickZoom.enable();
                    map.scrollWheelZoom.enable();
                    icon.className = "fa-solid fa-unlock";
                    statusText.innerText = "AV";
                    btn.style.borderColor = "rgba(255, 255, 255, 0.1)";
                    btn.style.color = "#94a3b8";
                }
            }

            function updateOpacity(val) {
                // Inverter logikken: 100% gjennomsiktighet = 0 opacity
                if (img) img.setOpacity(1 - val);
                document.getElementById('opacity-val').innerText = Math.round(val * 100) + '%';
            }


            function rescaleImage() {
                if (!img) return;

                // Hvis vi ikke har lagret base-hjørner ennå, gjør det nå
                if (!baseCorners) {
                    const corners = img.getCorners();
                    if (!corners || corners.length < 4) return;
                    baseCorners = corners.map(c => L.latLng(c.lat, c.lng));
                }

                const scaleW = parseFloat(document.getElementById('width-slider').value);
                const scaleH = parseFloat(document.getElementById('height-slider').value);

                if (isNaN(scaleW) || isNaN(scaleH)) return;

                document.getElementById('width-val').innerText = Math.round(scaleW * 100) + '%';
                document.getElementById('height-val').innerText = Math.round(scaleH * 100) + '%';

                // Finn sentrum av bildet (som referanse for skalering)
                const lats = baseCorners.map(c => c.lat);
                const lngs = baseCorners.map(c => c.lng);
                const centerLat = (Math.max(...lats) + Math.min(...lats)) / 2;
                const centerLng = (Math.max(...lngs) + Math.min(...lngs)) / 2;

                try {
                    // Skaler bildet først
                    let scaledCorners = baseCorners.map(c => {
                        const newLat = centerLat + (c.lat - centerLat) * scaleH;
                        const newLng = centerLng + (c.lng - centerLng) * scaleW;
                        return L.latLng(newLat, newLng);
                    });

                    img.setCorners(scaledCorners);
                    updateOutput();
                    // Oppdater nåler og senter-grep
                    if (pins.length > 0) updatePinsFromImage();
                } catch (e) {
                    console.error("Skaleringsfeil:", e);
                    alert("Feil ved skalering: " + e.message);
                }
            }

            function updateOutput() {
                if (!img) return;
                const corners = img.getCorners();
                const output = `Hjørner registrert:\n` +
                    `NW: ${corners[0].lat.toFixed(5)}, ${corners[0].lng.toFixed(5)}\n` +
                    `NE: ${corners[1].lat.toFixed(5)}, ${corners[1].lng.toFixed(5)}\n` +
                    `SW: ${corners[2].lat.toFixed(5)}, ${corners[2].lng.toFixed(5)}\n` +
                    `SE: ${corners[3].lat.toFixed(5)}, ${corners[3].lng.toFixed(5)}`;
                document.getElementById('coord-output').innerText = output;
            }

            function copyToClipboard() {
                const corners = img.getCorners();
                const cStr = corners.map(c => `    L.latLng(${c.lat.toFixed(6)}, ${c.lng.toFixed(6)})`).join(',\n');
                const isCropped = !!document.getElementById('btn-download-crop').style.display && document.getElementById('btn-download-crop').style.display !== 'none';
                const fileName = isCropped ? "beskjaert_kart.jpg" : imgPath;

                const code = `// 1. Lim dette inn i viewer.js under 'Historiske Kart' (ca linje 432)
const mapExp = L.distortableImageOverlay('${fileName}', {
    corners: [
${cStr}
    ],
    opacity: 0.7,
    editable: false,
    mode: 'lock'
});

// 2. Legg til i menyen (groupedOverlays):
// "Historisk Kart": mapExp`;

                navigator.clipboard.writeText(code).then(() => {
                    let msg = "Kode kopiert! ";
                    if (isCropped) {
                        msg += "\n\nMERK: Siden du har beskjært bildet, er disse koordinatene beregnet for den BESKJÆRTE fila. Husk å laste ned fila og legge den i 'assets/' mappen med navnet 'beskjaert_kart.jpg' (eller endre navnet i koden).";
                    } else {
                        msg += "\n\nKoordinatene er beregnet for originalfilen.";
                    }
                    alert(msg);
                });
            }

            setTimeout(updateOutput, 1000);
    </script>
</body>

</html>