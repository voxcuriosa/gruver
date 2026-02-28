let map;
let allSites = [];
let imageRegistry = [];
let currentAlbumImages = []; // Global for navigation
let layerControl; // Global reference for layer control
let overlayMaps = {}; // Global overlay maps container
let flyfotoLayers = {}; // Global flyfoto layers container

// Helper to sanitize filenames (matches export_images.py logic)
function getSafeName(name) {
    if (!name) return "Ukjent";
    // Remove CDATA markers if present
    let clean = name.replace(/<!\[CDATA\[(.*?)\]\]>/g, '$1');
    // Normalize newlines and whitespace to single space
    clean = clean.replace(/\r?\n|\r/g, ' ').trim();
    // Replace all non-alphanumeric (allowing Norwegian) with underscore
    clean = clean.replace(/[^a-zA-Z0-9æøåÆØÅ]/g, '_');
    // Collapse multiple underscores and strip from ends
    clean = clean.replace(/_+/g, '_').replace(/^_+|_+$/g, '');
    return clean;
}

// Mobile Map Menu Close Helper
function handleGlobalClick(e) {
    const isMobile = window.innerWidth < 1025;
    if (!isMobile) return;

    // --- LEAFLET LAYER CONTROL ---
    const layerContainer = layerControl ? layerControl.getContainer() : null;
    const isLayerExpanded = layerContainer && layerContainer.classList.contains('leaflet-control-layers-expanded');
    const isMapBtn = e.target.closest('#desktop-map-btn');
    const insideLayerMenu = e.target.closest('.leaflet-control-layers');

    if (isLayerExpanded && !isMapBtn && !insideLayerMenu) {
        layerContainer.classList.remove('leaflet-control-layers-expanded');
        const desktopMapBtn = document.getElementById('desktop-map-btn');
        if (desktopMapBtn) desktopMapBtn.classList.remove('active');
        updateOpacitySliderVisibility();
    }

    // --- SIDEBAR ---
    const sidebar = document.getElementById('sidebar');
    const isSidebarOpen = sidebar && !sidebar.classList.contains('collapsed');
    const isToggleBtn = e.target.closest('#mobile-toggle');
    const insideSidebar = e.target.closest('#sidebar');

    if (isSidebarOpen && !isToggleBtn && !insideSidebar) {
        toggleSidebar();
    }

    // --- FLYFOTO MENU ---
    const flyfotoMenu = document.getElementById('flyfoto-menu');
    const isFlyfotoOpen = flyfotoMenu && flyfotoMenu.style.display === 'block';
    const isFlyfotoBtn = e.target.closest('#desktop-flyfoto-btn');
    const insideFlyfoto = e.target.closest('#flyfoto-menu');

    if (isFlyfotoOpen && !isFlyfotoBtn && !insideFlyfoto) {
        flyfotoMenu.style.display = 'none';
        const desktopFlyfotoBtn = document.getElementById('desktop-flyfoto-btn');
        if (desktopFlyfotoBtn) desktopFlyfotoBtn.classList.remove('active');
    }
}

document.addEventListener('click', handleGlobalClick, true);
document.addEventListener('touchstart', handleGlobalClick, { passive: true, capture: true });

// --- ATTRIBUTION MODAL HELPERS ---
window.openAttributionModal = function (url) {
    console.log("openAttributionModal forespurt:", url);
    if (!url) return;
    const modal = document.getElementById('attribution-modal');
    const iframe = document.getElementById('attribution-iframe');
    const titleSpan = document.getElementById('attribution-modal-title');

    if (modal && iframe) {
        console.log("Modal og iframe funnet, åpner...");
        iframe.src = url;
        if (titleSpan) {
            titleSpan.innerHTML = `Kildedokumentasjon — <a href="${url}" target="_blank" style="color:#38bdf8 !important; text-decoration:underline !important; font-size:0.9rem;">Åpne i ny fane</a>`;
        }
        // Force visibility
        modal.style.setProperty('display', 'flex', 'important');
        modal.style.setProperty('z-index', '999999', 'important');
        modal.style.setProperty('opacity', '1', 'important');
        modal.style.setProperty('pointer-events', 'auto', 'important');
    } else {
        console.error("Mangler #attribution-modal eller #attribution-iframe i DOM.");
        // Fallback
        window.open(url, '_blank');
    }
};

window.closeAttributionModal = function () {
    console.log("Stenger attribution modal.");
    const modal = document.getElementById('attribution-modal');
    const iframe = document.getElementById('attribution-iframe');
    if (modal) {
        modal.style.setProperty('display', 'none', 'important');
    }
    if (iframe) iframe.src = 'about:blank';
};

function updateToolButton() {
    const btn = document.getElementById('finish-tool-btn');
    if (!btn) return;
    const pointsCount = isMeasuring ? measurePoints.length : (isElevationMode ? elevationPoints.length : 0);

    if ((isMeasuring || isElevationMode) && pointsCount >= 2) {
        btn.style.display = 'flex';
    } else {
        btn.style.display = 'none';
    }
}

// Debounce helper to limit function execution frequency
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
const markerLayer = L.layerGroup();
let currentSearchMarker = null;
let isTracking = false;
let userMarker = null;
let userCircle = null;
let lastLatLng = null;
let isMeasuring = false;
let measurePoints = [];
let measureLine = null;
let measureMarkers = [];
let tempLine = null;
let measureArea = null;
let isElevationMode = false;
let elevationPoints = [];
let elevationLine = null;
let elevationMarkers = [];
let elevationTempLine = null;
const dynamiskeGatenavn = L.layerGroup();
dynamiskeGatenavn.options = { minZoom: 16 };
// layerControl is declared globally at the top

const categoryMap = {
    'GRUVE': { name: 'Gruver & Skjerp', icon: '⚒️', color: '#ef4444' }, // Red
    'BYGDEBORG': { name: 'Bygdeborger', icon: '🏰', color: '#3b82f6' }, // Blue
    'HUSTUFT': { name: 'Hustufter & Ruiner', icon: '🧱', color: '#f59e0b' }, // Amber
    'UTSIKT': { name: 'Utsiktspunkter', icon: '🔭', color: '#10b981' }, // Emerald
    'VANN': { name: 'Vannsystemer', icon: '💧', color: '#0ea5e9' }, // Sky
    'GRENSESTEIN': { name: 'Grensesteiner', icon: '🗿', color: '#8b5cf6' }, // Violet
    'GRAVHAUG': { name: 'Gravhauger', icon: '🪨', color: '#4b5563' }, // Gray
    'GAPAHUK': { name: 'Gapahuker', icon: '⛺', color: '#fb923c' }, // Orange
    'HULE': { name: 'Huler / Grotter', icon: '⛰️', color: '#d946ef' }, // Fuchsia
    'VEI': { name: 'Veier', icon: '🛣️', color: '#84cc16' }, // Lime
    'DIVERSE': { name: 'Diverse / Kultur', icon: '📦', color: '#6366f1' }, // Indigo
    'DEFAULT': { name: 'Interessepunkter', icon: '📍', color: '#14b8a6' }  // Teal
};

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const btn = document.getElementById('mobile-toggle');
    sidebar.classList.toggle('collapsed');

    if (sidebar.classList.contains('collapsed')) {
        if (btn) {
            btn.style.display = 'flex';
            btn.innerHTML = '☰';
        }
    } else {
        if (btn) {
            btn.style.display = 'none';
        }
    }

    // Fix grey area: Force Leaflet to recalculate container size after transition
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 350); // Matches transition duration
}


// --- Definitions for UTM32 (Standard for Norway mapping) ---
if (typeof L.Proj !== 'undefined') {
    L.CRS.EPSG32632 = new L.Proj.CRS('EPSG:32632',
        '+proj=utm +zone=32 +datum=WGS84 +units=m +no_defs',
        {
            resolutions: [
                8192, 4096, 2048, 1024, 512, 256, 128, 64, 32, 16, 8, 4, 2, 1, 0.5
            ],
            origin: [-2500000.0, 9045984.0]
        }
    );
}

// --- PROTOTYPE PATCHES FOR LEAFLET GROUPED LAYERS ---
if (L.Control.GroupedLayers) {
    // 1. Accessibility: swap <label> for group titles to <span> to avoid console errors
    const originalAddItem = L.Control.GroupedLayers.prototype._addItem;
    L.Control.GroupedLayers.prototype._addItem = function (obj) {
        const result = originalAddItem.call(this, obj);
        if (obj.group) {
            const labels = this._form.querySelectorAll('.leaflet-control-layers-group-label');
            labels.forEach(lbl => {
                if (lbl.tagName.toLowerCase() === 'label') {
                    const span = document.createElement('span');
                    span.className = lbl.className;
                    span.innerHTML = lbl.innerHTML;
                    lbl.parentNode.replaceChild(span, lbl);
                }
            });
        }
        return result;
    };

    // 2. v117: Override _expand on the PROTOTYPE (must be done before control creation,
    //    because mouseover is bound to this._expand at construction time).
    //    The original calculates a small height based on map size which causes a jump.
    //    We enforce a stable height based on screen size instead.
    L.Control.GroupedLayers.prototype._expand = function () {
        L.DomUtil.addClass(this._container, 'leaflet-control-layers-expanded');
        const targetH = Math.round(window.innerHeight * 0.72);
        L.DomUtil.addClass(this._form, 'leaflet-control-layers-scrollbar');
        this._form.style.height = targetH + 'px';
        this._form.style.maxHeight = targetH + 'px';
        this._form.style.overflowY = 'auto';
    };
}


function getOrCreateOpacitySlider() {
    if (!layerControl) return null;
    const isMobile = window.innerWidth < 1025;
    const mobileWrapper = document.getElementById('mobile-slider-wrapper');
    const menuContainer = layerControl.getContainer();

    // Bestem hvor slideren skal bo
    const currentFlyfotoMenu = document.getElementById('flyfoto-menu');
    const isFlyfotoMenuExpanded = currentFlyfotoMenu && currentFlyfotoMenu.style.display === 'block';

    let targetContainer = (isMobile && mobileWrapper) ? mobileWrapper : menuContainer;

    if (!isMobile && isFlyfotoMenuExpanded) {
        let hasActiveFlyfotoLayer = false;
        Object.values(flyfotoLayers).forEach(layer => {
            if (map.hasLayer(layer)) hasActiveFlyfotoLayer = true;
        });
        if (hasActiveFlyfotoLayer && currentFlyfotoMenu) {
            targetContainer = currentFlyfotoMenu;
        }
    }

    if (!targetContainer) return null;

    let sliderContainer = document.querySelector('.integrated-opacity-control');

    if (sliderContainer && sliderContainer.parentElement !== targetContainer) {
        sliderContainer.remove();
        sliderContainer = null;
    }

    if (!sliderContainer) {
        sliderContainer = L.DomUtil.create('div', 'integrated-opacity-control', targetContainer);

        if (isMobile) {
            sliderContainer.style.width = '100%';
            sliderContainer.style.height = '100%';
            sliderContainer.style.padding = '0';
            sliderContainer.style.borderTop = 'none';
            sliderContainer.style.marginTop = '0';
            sliderContainer.style.display = 'flex';
            sliderContainer.style.flexDirection = 'column';
            sliderContainer.style.alignItems = 'center';
            sliderContainer.style.gap = '5px';
        } else {
            sliderContainer.style.padding = '10px 12px';
            sliderContainer.style.borderTop = '1px solid var(--glass-border)';
            sliderContainer.style.background = 'rgba(0,0,0,0.1)';
            sliderContainer.style.marginTop = '5px';
        }

        sliderContainer.style.display = 'none';

        let sliderHtml = `
            <style>
                .integrated-opacity-control-flex {
                    display: flex;
                    flex-direction: column;
                    width: 100%;
                    justify-content: center;
                    align-items: center;
                }
                .integrated-opacity-control-flex.mobile-layout {
                    flex-direction: row;
                    gap: 8px;
                    padding: 0 5px;
                }
                .integrated-opacity-control label {
                    display: block;
                    margin-bottom: 2px;
                    font-size: 10px;
                    font-weight: 700;
                    color: #38bdf8;
                    text-transform: uppercase;
                    letter-spacing: 0.1em;
                }
                .integrated-opacity-control-flex.mobile-layout label {
                    display: none;
                }
                .integrated-opacity-control input[type="range"] {
                    -webkit-appearance: none;
                    width: 100%;
                    height: 4px;
                    border-radius: 3px;
                    background: linear-gradient(to right, rgba(56, 189, 248, 0.1) 0%, rgba(56, 189, 248, 1) 100%);
                    outline: none;
                    cursor: pointer;
                    margin: 8px 0;
                }
                .integrated-opacity-control input[type="range"]::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                    width: 14px;
                    height: 14px;
                    border-radius: 50%;
                    background: #38bdf8;
                    cursor: pointer;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
                }
                .integrated-opacity-value-display {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #fff;
                    font-weight: 600;
                    white-space: nowrap;
                    min-width: 28px;
                    padding: 0 5px;
                }
                .integrated-opacity-value-display #opacity-value {
                    font-size: 16px;
                    color: #38bdf8;
                }
                .mobile-attribution-line {
                    font-size: 10px;
                    color: #94a3b8;
                    margin-top: 4px;
                    padding: 0 5px;
                    white-space: normal;
                    line-height: 1.3;
                    pointer-events: auto;
                    display: none;
                    text-align: center;
                    width: 100%;
                }
                .percent-symbol {
                    color: #38bdf8;
                    font-size: 11px;
                    margin-left: 1px;
                    font-weight: 600;
                }
            </style>
            <div class="integrated-opacity-control-flex ${isMobile ? 'mobile-layout' : ''}">
                <label>Gjennomsiktighet</label>
                <input type="range" id="opacity-slider" min="0" max="100" value="0">
                <div class="integrated-opacity-value-display">
                    <span id="opacity-value">0</span><span class="percent-symbol">%</span>
                </div>
            </div>
            <div id="mobile-attribution-line" class="mobile-attribution-line"></div>
            <div id="integrated-attribution-box" style="margin-top:10px; padding-top:8px; border-top:1px solid rgba(255,255,255,0.08); display:none; width:100%; pointer-events:auto;">
                <div id="integrated-attribution-text" style="font-size:10px; color:#94a3b8; margin-bottom:5px; line-height:1.4;"></div>
                <button id="integrated-attribution-btn" style="background:rgba(56, 189, 248, 0.1); color:#38bdf8; border:1px solid rgba(56, 189, 248, 0.3); padding:4px 8px; border-radius:4px; font-size:9px; font-weight:700; cursor:pointer; text-transform:uppercase; display:none; align-items:center; gap:5px;">
                    Info <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:0.5rem;"></i>
                </button>
            </div>
        `;

        sliderContainer.innerHTML = sliderHtml;

        L.DomEvent.disableClickPropagation(sliderContainer);
        L.DomEvent.disableScrollPropagation(sliderContainer);

        const slider = sliderContainer.querySelector('#opacity-slider');
        const valueDisplay = sliderContainer.querySelector('#opacity-value');

        if (slider) {
            slider.addEventListener('input', function () {
                const opacity = 1 - (this.value / 100);
                valueDisplay.innerText = this.value;

                map.eachLayer(layer => {
                    if (layer instanceof L.DistortableImageOverlay) {
                        layer.setOpacity(opacity);
                    }
                });

                Object.values(flyfotoLayers).forEach(layer => {
                    if (map.hasLayer(layer)) {
                        if (layer.setOpacity) {
                            layer.setOpacity(opacity);
                        } else if (layer.options) {
                            layer.options.opacity = opacity;
                            if (layer.redraw) layer.redraw();
                        }
                    }
                });
            });
        }
    }
    return sliderContainer;
}

function updateOpacitySliderVisibility() {
    const sliderContainer = getOrCreateOpacitySlider();
    const mobileWrapper = document.getElementById('mobile-slider-wrapper');
    const mobileAttr = document.getElementById('mobile-attribution-line');
    const pcAttrBox = document.getElementById('integrated-attribution-box');
    const pcAttrText = document.getElementById('integrated-attribution-text');
    const pcAttrBtn = document.getElementById('integrated-attribution-btn');

    if (!sliderContainer) return;

    if (layerControl) {
        const isMobile = window.innerWidth < 1025;
        const layersContainer = layerControl.getContainer();
        const isMenuExpanded = layersContainer && layersContainer.classList.contains('leaflet-control-layers-expanded');

        let hasActiveHistoricalLayer = false;
        map.eachLayer(layer => {
            if (layer instanceof L.DistortableImageOverlay) {
                hasActiveHistoricalLayer = true;
            }
        });

        let hasActiveFlyfotoLayer = false;
        if (typeof flyfotoLayers !== 'undefined') {
            Object.values(flyfotoLayers).forEach(layer => {
                if (map.hasLayer(layer)) hasActiveFlyfotoLayer = true;
            });
        }

        const currentFlyfotoMenu = document.getElementById('flyfoto-menu');
        const isFlyfotoMenuExpanded = currentFlyfotoMenu && currentFlyfotoMenu.style.display === 'block';

        const hasAnyTransparentLayer = hasActiveHistoricalLayer || hasActiveFlyfotoLayer;

        if (hasAnyTransparentLayer && (isMenuExpanded || isFlyfotoMenuExpanded || isMobile)) {
            sliderContainer.style.display = isMobile ? 'flex' : 'block';
            if (isMobile && mobileWrapper) mobileWrapper.style.display = 'flex';

            // Universal attribution aggregator (v107)
            let collectedData = [];
            const noise = ['OpenStreetMap', 'CARTO', 'OpenMapTiles', 'Mapbox'];

            map.eachLayer(layer => {
                if (map.hasLayer(layer)) {
                    // v108: Only aggregate attribution for historical maps and flyfoto
                    const isHistorical = layer instanceof L.DistortableImageOverlay;
                    const isFlyfoto = typeof flyfotoLayers !== 'undefined' && Object.values(flyfotoLayers).includes(layer);

                    if (!isHistorical && !isFlyfoto) return;

                    let rawAttr = layer.options.attribution || "";
                    if (layer.getAttribution) {
                        const fall = layer.getAttribution();
                        if (fall && !rawAttr) rawAttr = fall;
                    }

                    let plain = rawAttr.replace(/<[^>]*>/g, '').trim();
                    if (!plain || noise.some(n => plain.includes(n))) return;
                    if (plain.toLowerCase().includes('bora.uib.no')) return;

                    // v107: Scrub branding symbols ONLY from the slider display
                    plain = plain.replace(/[©&]|copy(right)?|\u00a9/gi, '').trim();

                    let name = layer.options.attributionName || plain || "";
                    let link = layer.options.attributionLink || null;

                    if (link && link.toLowerCase().includes('bora.uib.no')) link = null;

                    // v107: Deduplicate by name and link
                    if (name) {
                        const isDuplicate = collectedData.some(item => item.name === name && item.link === link);
                        if (!isDuplicate) {
                            collectedData.push({ name, link });
                        }
                    }
                }
            });

            if (collectedData.length > 0) {
                // Build HTML links for all active sources (v106)
                const htmlArray = collectedData.map(item => {
                    if (item.link) {
                        return `<a href="${item.link}" target="_blank" style="color:#38bdf8; text-decoration:underline; font-weight:600;">${item.name}</a>`;
                    }
                    return `<span style="color:#94a3b8;">${item.name}</span>`;
                });
                const combinedHtml = htmlArray.join(" | ");

                if (isMobile) {
                    if (mobileAttr) {
                        mobileAttr.innerHTML = `<span style="text-transform:uppercase; font-size:9px; font-weight:700; color:#64748b; margin-right:5px; display:inline-block;">Kreditering:</span>` + combinedHtml;
                        mobileAttr.style.display = 'block';
                    }
                    if (pcAttrBox) pcAttrBox.style.display = 'none';
                } else {
                    if (pcAttrBox) {
                        pcAttrBox.style.display = 'block';
                        if (pcAttrText) {
                            pcAttrText.innerHTML = `<span style="text-transform:uppercase; font-size:9px; font-weight:700; color:#64748b; display:block; margin-bottom:5px;">Kreditering:</span>` + combinedHtml;
                        }
                        if (pcAttrBtn) pcAttrBtn.style.display = 'none';
                    }
                    if (mobileAttr) mobileAttr.style.display = 'none';
                }
            } else {
                if (mobileAttr) mobileAttr.style.display = 'none';
                if (pcAttrBox) pcAttrBox.style.display = 'none';
            }
        } else {
            sliderContainer.style.display = 'none';
            if (mobileWrapper) mobileWrapper.style.display = 'none';
            if (mobileAttr) mobileAttr.style.display = 'none';
            if (pcAttrBox) pcAttrBox.style.display = 'none';
        }
    } else {
        sliderContainer.style.display = 'none';
        if (mobileWrapper) mobileWrapper.style.display = 'none';
    }
}

function initMap() {
    // Initial center on Skien
    const isMobileDevice = window.innerWidth < 1025;

    map = L.map('map', {
        maxZoom: 22,
        zoomControl: false,
        fadeAnimation: !isMobileDevice // Deaktiver fade på mobil for "brutal" (raskere) innlasting
    }).setView([59.23, 9.53], 13);

    L.control.zoom({ position: 'bottomright' }).addTo(map);

    // Auto-zoom og persistence-fix ved aktivering av lag
    map.on('overlayadd', function (e) {
        // Eksisterende sjekk for minZoom
        const minZoom = e.layer.options.minZoom;
        if (minZoom && map.getZoom() < minZoom) {
            map.flyTo(map.getCenter(), minZoom, { animate: true });
        }

        // Spesialhåndtering for georefererte historiske kart (DistortableImageOverlay)
        if (e.layer instanceof L.DistortableImageOverlay) {
            const levelLayer = e.layer;

            // Definer oppfrisknings-funksjon
            const refreshLayer = () => {
                console.log("Refreshing layer:", levelLayer);

                // Fix for persistence: Force a reset and corner refresh
                if (typeof levelLayer._reset === 'function') levelLayer._reset();
                if (typeof levelLayer._update === 'function') levelLayer._update();

                // FORCE RE-INJECTION: Known bug in DistortableImage where it loses its DOM element
                if (levelLayer._image && levelLayer._map) {
                    const pane = levelLayer.options.pane || 'overlayPane';
                    const container = levelLayer._map.getPanes()[pane];
                    if (container && !container.contains(levelLayer._image)) {
                        console.log("Re-appending image to pane:", pane);
                        container.appendChild(levelLayer._image);
                    }
                }

                // Force recalculation of corners (CRITICAL for reappappearing)
                if (typeof levelLayer.setCorners === 'function') {
                    const currentCorners = levelLayer.getCorners();
                    if (currentCorners && currentCorners.length) {
                        levelLayer.setCorners(currentCorners);
                    }
                }

                // Sikre at bildet er synlig
                if (levelLayer._image) {
                    levelLayer._image.style.visibility = 'visible';
                    levelLayer._image.style.display = 'block';
                    levelLayer._image.style.zIndex = '1000';
                }

                // Sikre at gjennomsiktighet fra slideren blir påført (hvis slider finnes)
                let slider = document.getElementById('opacity-slider');
                if (slider) {
                    // Hvis det ikke var noen aktive lag før dette, sett slideren til 0% gjennomsiktighet (full synlighet)
                    const otherLayers = Object.values(map._layers);
                    const otherActive = otherLayers.some(l => l !== levelLayer && l instanceof L.DistortableImageOverlay);

                    if (!otherActive) {
                        slider.value = 0;
                        const valDisplay = document.getElementById('opacity-value');
                        if (valDisplay) valDisplay.textContent = '0';
                    }

                    const transparency = slider.value / 100;
                    levelLayer.setOpacity(1 - transparency);
                } else {
                    levelLayer.setOpacity(1.0);
                }

                // Zoom til lagets utstrekning (BARE hvis vi ikke allerede ser på området)
                const bounds = levelLayer.getBounds();
                if (bounds && bounds.isValid()) {
                    const mapCenter = map.getCenter();
                    if (!bounds.contains(mapCenter)) {
                        console.log("Zooming to bounds:", bounds);
                        map.fitBounds(bounds, { padding: [50, 50], animate: true });
                    } else {
                        console.log("Staying put, map center is already within bounds of historical map");
                    }
                }
            };

            // Hvis bildet ikke er ferdig lastet, vent til det er det
            if (levelLayer._image && levelLayer._image.complete) {
                // Litt delay for å sikre at Leaflet har lagt det til i DOM
                setTimeout(refreshLayer, 50);
            } else {
                levelLayer.once('load', () => {
                    setTimeout(refreshLayer, 100);
                });
            }
        }
    });

    // Topographical maps from Kartverket
    const topoLayer = L.tileLayer('https://cache.kartverket.no/v1/wmts/1.0.0/topo/default/webmercator/{z}/{y}/{x}.png', {
        attribution: '© Kartverket',
        maxZoom: 22,
        maxNativeZoom: 18,
        updateWhenIdle: false, // Last inn fliser umiddelbart ved panorering/zoom
        keepBuffer: 4,         // Behold flere fliser i minnet
        updateInterval: 150    // Oppdateringsfrekvens
    });

    // Satellite imagery from Esri
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EBP, and the GIS User Community',
        maxZoom: 22,
        maxNativeZoom: 18,
        updateWhenIdle: false,
        keepBuffer: 3
    });

    // LiDAR layers from Kartverket
    const lidarEnkel = L.tileLayer.wms('https://wms.geonorge.no/skwms1/wms.hoyde-dtm', {
        layers: 'DTM:skyggerelieff',
        format: 'image/png',
        transparent: true,
        attribution: '&copy; Kartverket',
        version: '1.3.0',
        maxZoom: 20,
        tileSize: 256,
        updateWhenIdle: true,
        detectRetina: false
    });

    const lidarTerreng = L.tileLayer.wms('https://wms.geonorge.no/skwms1/wms.hoyde-dtm', {
        layers: 'DTM:multiskyggerelieff',
        format: 'image/png',
        transparent: true,
        attribution: '&copy; Kartverket',
        version: '1.3.0',
        maxZoom: 20,
        tileSize: 256,
        updateWhenIdle: true,
        detectRetina: false
    });

    const isMobile = window.innerWidth < 768;
    const nibUrl = 'nib_proxy.php?svc=nib';
    const nibProjUrl = 'nib_proxy.php?svc=nib-prosjekter';

    const nibOptions = (layer) => ({
        layers: layer,
        format: 'image/png',
        transparent: true,
        attribution: '© Norge i bilder',
        version: '1.1.1',
        maxZoom: 22,
        maxNativeZoom: 18,
        tileSize: 256,
        updateWhenIdle: false,
        keepBuffer: 2,
        detectRetina: false
    });

    const darkLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    });

    const darkLabels = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_only_labels/{z}/{x}/{y}{r}.png', {
        subdomains: 'abcd',
        maxZoom: 20
    });

    const lightLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    });

    const baseMaps = {
        "Topografisk": topoLayer,
        "LiDAR Terreng": lidarTerreng,
        "LiDAR Enkel": lidarEnkel,
        "Satellitt": satelliteLayer,
        "Mørkt Kart": darkLayer,
        "Lyst Kart": lightLayer
    };

    // Flyfoto - Midlertidig deaktivert (venter på API-tilgang fra Geonorge)
    // baseMaps["——— Historiske Flyfoto ———"] = L.layerGroup();
    // baseMaps["ℹ️ Kommer snart"] = L.layerGroup(); // Placeholder

    // Flyfoto-lag - Aktivert via GeoID (Proxy håndterer autentisering)
    flyfotoLayers = {
        "Flyfoto (Ortofoto)": L.tileLayer('nib_proxy.php?svc=nib&service=WMTS&request=GetTile&version=1.0.0&layer=Nibcache_web_mercator_v2&style=default&tilematrixset=GoogleMapsCompatible&tilematrix={z}&tilerow={y}&tilecol={x}&format=image/jpeg', {
            attribution: '© Norge i bilder',
            maxZoom: 22,
            maxNativeZoom: 18,
            tileSize: 256,
            updateWhenIdle: false,
            keepBuffer: 3
        }),

        "S. Grenland 2024": L.tileLayer.wms(nibProjUrl, nibOptions('Skråfoto Grenland 2024')),
        "V/V Sør 2022": L.tileLayer.wms(nibProjUrl, nibOptions('Vestfold og Viken Sør 2022')),
        "V/V Sør CIR 2022": L.tileLayer.wms(nibProjUrl, nibOptions('Vestfold og Viken Sør CIR 2022')),
        "Grenland Vestfold 2022": L.tileLayer.wms(nibProjUrl, nibOptions('Grenland Vestfold 2022')),
        "Grenland skråfoto 2018": L.tileLayer.wms(nibProjUrl, nibOptions('Grenland skråfoto 2018')),
        "Skien Lardal 2018": L.tileLayer.wms(nibProjUrl, nibOptions('Skien Lardal 2018')),
        "Flom Skien 2015": L.tileLayer.wms(nibProjUrl, nibOptions('Flom Skiensvassdraget 2015')),
        "Telemark 2015": L.tileLayer.wms(nibProjUrl, nibOptions('Telemark 2015')),
        "Skien 2014": L.tileLayer.wms(nibProjUrl, nibOptions('Skien 2014')),
        "Porsgrunn/Skien 2013": L.tileLayer.wms(nibProjUrl, nibOptions('Skien sentrum-Porsgrunn 2013')),
        "Skien-Siljan 2010": L.tileLayer.wms(nibProjUrl, nibOptions('Skien-Siljan 2010')),
        "Sørlandet 2009": L.tileLayer.wms(nibProjUrl, nibOptions('Sørlandet 2009')),
        "Porsgrunn Skien 2007": L.tileLayer.wms(nibProjUrl, nibOptions('Porsgrunn Skien 2007')),
        "Skien 2005": L.tileLayer.wms(nibProjUrl, nibOptions('Skien 2005')),
        "Telemark 2004": L.tileLayer.wms(nibProjUrl, nibOptions('Telemark 2004')),
        "Skien sentrum 2004": L.tileLayer.wms(nibProjUrl, nibOptions('Skien sentrum 2004')),
        "Skien 1991": L.tileLayer.wms(nibProjUrl, nibOptions('Skien 1991')),
        "Skien 1966": L.tileLayer.wms(nibProjUrl, nibOptions('Skien 1966')),
        "Ski./Por./Bre. 1965": L.tileLayer.wms(nibProjUrl, nibOptions('Skien Porsgrunn Brevik 1965')),
        "Skien 1947": L.tileLayer.wms(nibProjUrl, nibOptions('Skien 1947')),
        "Skien/Porsgrunn 1937": L.tileLayer.wms(nibProjUrl, nibOptions('Skien Porsgrunn 1937'))
    };
    // Eiendomskart
    const eiendomLayer = L.tileLayer.wms('https://wms.geonorge.no/skwms1/wms.matrikkelkart', {
        layers: 'teig,bygning_symbol,eiendomsgrense,adresse',
        format: 'image/png8', // Optimalisert for ytelse
        transparent: true,
        attribution: '\u00a9 Kartverket',
        version: '1.3.0',
        tileSize: 256,
        maxZoom: 20,
        minZoom: 15,
        detectRetina: false,
        updateWhenIdle: true,
        className: 'inverted-layer'
    });

    eiendomLayer.on('loading', () => {
        const loader = document.getElementById('map-loader');
        if (loader) loader.style.display = 'flex';
    });
    eiendomLayer.on('load', () => {
        const loader = document.getElementById('map-loader');
        if (loader) loader.style.display = 'none';
    });
    eiendomLayer.on('tileerror', () => {
        const loader = document.getElementById('map-loader');
        if (loader) loader.style.display = 'none';
    });

    const groupedOverlays = {
        "Referanse": {
            "Stedsnavn": darkLabels
        },
        "Geologi & Grunn": {
            "Løsmasser (NGU)": L.tileLayer.wms('https://geo.ngu.no/mapserver/LosmasserWMS2', {
                layers: 'Losmasse_flate',
                format: 'image/png',
                transparent: true,
                version: '1.3.0',
                opacity: 0.6, // Satt til 0.6 for å se berggrunn/ortofoto igjennom
                attribution: '© NGU'
            }),
            "Berggrunn (NGU Overlegg)": L.tileLayer.wms('https://geo.ngu.no/mapserver/BerggrunnWMS3', {
                layers: 'Berggrunn_regional_flater',
                format: 'image/png',
                transparent: true,
                opacity: 0.6,
                attribution: '© NGU',
                version: '1.3.0',
                maxZoom: 20,
                tileSize: 256,
                updateWhenIdle: true,
                detectRetina: false
            }),
            "Mineralressurser (NGU)": L.tileLayer.wms('https://geo.ngu.no/mapserver/MetalsWMS2', {
                layers: 'Point_Metals,Area_Metals',
                format: 'image/png',
                transparent: true,
                attribution: '© NGU',
                version: '1.3.0',
                maxZoom: 20,
                tileSize: 256,
                updateWhenIdle: true,
                detectRetina: false
            }),
            "Radon (NGU)": L.tileLayer.wms('https://geo.ngu.no/mapserver/RadonWMS2', {
                layers: 'Radon_aktsomhet',
                format: 'image/png',
                transparent: true,
                opacity: 0.7,
                attribution: '© NGU',
                version: '1.3.0',
                maxZoom: 20,
                tileSize: 256,
                updateWhenIdle: true,
                detectRetina: false
            }),
            "Kvikkleire (NVE)": L.tileLayer.wms('https://kart.nve.no/enterprise/services/SkredKvikkleire2/MapServer/WMSServer', {
                layers: 'KvikkleireKartlagtOmrade,KvikkleireRisiko,KvikkleireFaregrad',
                format: 'image/png',
                transparent: true,
                opacity: 0.8,
                attribution: '© NVE',
                version: '1.3.0',
                maxZoom: 20,
                tileSize: 256,
                updateWhenIdle: true,
                detectRetina: false
            })
        },
        "Kultur & Eiendom": {
            "Eiendomskart": eiendomLayer,
            "Kulturminner": L.tileLayer.wms('kultur_proxy.php', {
                layers: 'Kulturminner',
                format: 'image/png',
                transparent: true,
                attribution: '© Riksantikvaren',
                version: '1.3.0',
                maxZoom: 20,
                tileSize: 256,
                updateWhenIdle: true,
                detectRetina: false,
                minZoom: 15
            }),
            "Reguleringsplan": L.tileLayer.wms('https://cache1.nois.no/geowebcache/service/wms', {
                layers: 'Grenland Reguleringsplan',
                format: 'image/png',
                transparent: true,
                version: '1.1.1',
                crs: L.CRS.EPSG32632,
                uppercase: true,
                attribution: '© Grenland'
            }),
            "Gatenavn (Historikk)": dynamiskeGatenavn
        },
        "Historiske Kart (Georef)": {
            // Dette fylles ut dynamisk fra published_maps.json
        }
    };

    // MERGE FLYFOTO LAYERS INTO OVERLAYS (GROUPED)
    // groupedOverlays["Historiske Flyfoto"] = flyfotoLayers; // This line was removed

    topoLayer.addTo(map);
    darkLabels.addTo(map);

    // LOAD BOUNDS FOR AUTO-ZOOM
    let nibBounds = {};
    fetch('nib_bounds.json')
        .then(r => r.json())
        .then(data => {
            console.log("Loaded NIB bounds:", Object.keys(data).length);
            nibBounds = data;
        })
        .catch(e => console.error("Could not load bounds:", e));

    // AUTO-ZOOM HANDLER (Generic 'layeradd' works for all methods)
    map.on('layeradd', function (e) {
        // Check if the added layer is a WMS layer and has bounds
        if (e.layer && e.layer.wmsParams && e.layer.wmsParams.layers) {
            const wmsName = e.layer.wmsParams.layers;

            if (nibBounds[wmsName]) {
                const b = nibBounds[wmsName];
                // Bounds format: [[south, west], [north, east]]
                if (b && b.length === 2) {
                    const layerBounds = L.latLngBounds(b);
                    const mapCenter = map.getCenter();

                    // Bare zoom hvis vi IKKE er innafor ramma til det nye laget
                    if (!layerBounds.contains(mapCenter)) {
                        console.log("Auto-zooming to:", wmsName, b);
                        map.fitBounds(b);
                    } else {
                        console.log("Staying put, map center is already within bounds of:", wmsName);
                    }
                }
            }
        }
    });

    if (L.control.groupedLayers) {
        // Use grouped layers if available (ONLY FOR OVERLAYS)
        layerControl = L.control.groupedLayers(baseMaps, groupedOverlays, { collapsed: true }).addTo(map);
    } else {
        const overlayMaps = { "Stedsnavn (Check)": darkLabels };
        layerControl = L.control.layers(baseMaps, overlayMaps, { collapsed: true }).addTo(map);
    }

    // Last inn publiserte kart fra GeoRef-verktøyet
    fetch('published_maps.json?v=' + Date.now())
        .then(response => response.json())
        .then(data => {
            if (Array.isArray(data)) {
                data.forEach(m => {
                    // Filter ut admin-only kart hvis man ikke er i admin-modus
                    if (m.isAdminOnly && !window.isAdminMode) {
                        return;
                    }

                    const corners = m.corners.map(c => L.latLng(c.lat, c.lng));
                    const isMobile = window.innerWidth < 1025;
                    let imagePath = m.imagePath;

                    // Bruk bilde-proxy på mobil for å unngå GPU-crash ved gigantiske bilder
                    if (isMobile) {
                        imagePath = `image_proxy.php?img=${encodeURIComponent(m.imagePath)}`;
                        console.log("Using mobile image proxy for:", m.name);
                    }

                    const overlay = L.distortableImageOverlay(imagePath, {
                        corners: corners,
                        opacity: 1.0,
                        editable: false,
                        mode: 'lock',
                        attribution: m.attributionName || '',
                        attributionName: m.attributionName || '',
                        attributionLink: m.attributionLink || '',
                        updateWhenIdle: false
                    });

                    if (layerControl) {
                        layerControl.addOverlay(overlay, m.name, "Historiske Kart (Georef)");
                    }
                });
            }
        }).catch(err => console.log("Ingen publiserte kart eller feil ved lasting:", err));

    map.on('overlayadd overlayremove', updateOpacitySliderVisibility);

    // Hindre at menyen lukker seg når musen forlater den
    if (layerControl) {
        const layerContainer = layerControl.getContainer();
        if (layerContainer) {
            L.DomEvent.off(layerContainer, 'mouseout', layerControl._collapse, layerControl);
        }

        // Note: _expand is now patched on the prototype above (v117)
    }

    // Prevents touch propagation to map behind the layer control on mobile
    if (layerControl && layerControl.getContainer()) {
        const container = layerControl.getContainer();
        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.disableScrollPropagation(container);
    }

    // --- INTEGRATED OPACITY SLIDER FOR AMTSKART ---
    let activeHistoricalLayer = null;


    // NOTE: getOrCreateOpacitySlider and updateOpacitySliderVisibility are now global


    map.on('overlayadd overlayremove', function (e) {
        setTimeout(() => {
            updateOpacitySliderVisibility();
        }, 100);
    });

    window.addEventListener('resize', debounce(() => {
        updateOpacitySliderVisibility();
    }, 200));


    // --- DESKTOP BUTTON LOGIC ---
    const desktopMapBtn = document.getElementById('desktop-map-btn');
    const desktopFlyfotoBtn = document.getElementById('desktop-flyfoto-btn');
    const flyfotoMenu = document.getElementById('flyfoto-menu');
    const flyfotoList = document.getElementById('flyfoto-list');
    const desktopMenuToggle = document.getElementById('mobile-toggle');

    if (desktopMapBtn) {
        desktopMapBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (layerControl) {
                const container = layerControl.getContainer();
                if (container) {
                    const isExpanded = container.classList.contains('leaflet-control-layers-expanded');
                    if (isExpanded) {
                        container.classList.remove('leaflet-control-layers-expanded');
                        desktopMapBtn.classList.remove('active');
                    } else {
                        layerControl._expand(); // FIX: use _expand() so height is set same as on mouseover (avoids jump on first hover)
                        desktopMapBtn.classList.add('active');
                        // Hide flyfoto menu if open
                        if (flyfotoMenu) flyfotoMenu.style.display = 'none';
                        if (desktopFlyfotoBtn) desktopFlyfotoBtn.classList.remove('active');
                    }
                    updateOpacitySliderVisibility();
                }
            }
        });
    }

    if (flyfotoList) {
        Object.keys(flyfotoLayers).forEach((name, index) => {
            const item = document.createElement('div');
            item.className = 'flyfoto-item';

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'flyfoto_layer';
            radio.id = 'fly_' + index;
            radio.value = name;
            radio.className = 'flyfoto-radio';

            // Handle click/change
            item.onclick = () => { radio.checked = true; radio.dispatchEvent(new Event('change')); };

            radio.addEventListener('change', () => {
                if (radio.checked) {

                    // GA4 Sporing
                    if (typeof gtag === 'function') {
                        gtag('event', 'velg_flyfoto', {
                            'flyfoto_aar': name
                        });
                    }

                    // Fjern alle andre flyfoto-lag og baselag (unntatt topo og labels)
                    Object.values(baseMaps).forEach(bl => {
                        if (map.hasLayer(bl) && bl !== topoLayer && bl !== darkLabels) {
                            map.removeLayer(bl);
                        }
                    });

                    Object.values(flyfotoLayers).forEach(fl => {
                        if (map.hasLayer(fl)) map.removeLayer(fl);
                    });

                    // VIKTIG: Fjern også alle georefererte historiske kartlag
                    map.eachLayer(layer => {
                        if (layer instanceof L.DistortableImageOverlay) {
                            map.removeLayer(layer);
                        }
                    });

                    // Legg til valgt flyfoto
                    flyfotoLayers[name].addTo(map);

                    // Visuell oppdatering
                    document.querySelectorAll('.flyfoto-item').forEach(el => el.classList.remove('active'));
                    item.classList.add('active');
                    if (desktopFlyfotoBtn) desktopFlyfotoBtn.classList.add('active');

                    // Trigger slider-synlighet
                    updateOpacitySliderVisibility();
                }
            });

            const label = document.createElement('label');
            label.setAttribute('for', 'fly_' + index);
            label.textContent = name;
            label.style.cursor = 'pointer';
            label.style.flexGrow = '1';

            item.appendChild(radio);
            item.appendChild(label);
            flyfotoList.appendChild(item);
        });
    }

    if (desktopFlyfotoBtn) {
        desktopFlyfotoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (flyfotoMenu.style.display === 'block') {
                flyfotoMenu.style.display = 'none';
                desktopFlyfotoBtn.classList.remove('active');
            } else {
                flyfotoMenu.style.display = 'block';
                desktopFlyfotoBtn.classList.add('active');

                // Shift Kart menu if it's open to avoid overlap, OR close it
                if (layerControl) {
                    const container = layerControl.getContainer();
                    if (container) {
                        container.classList.remove('leaflet-control-layers-expanded');
                        if (desktopMapBtn) desktopMapBtn.classList.remove('active');
                    }
                    updateOpacitySliderVisibility();
                }
            }
        });
    }

    // Unified global click handler already defined at top of file

    markerLayer.addTo(map);

    // --- MÅLESTOKK ---
    L.control.scale({
        imperial: false,
        metric: true,
        maxWidth: 150,
        position: 'bottomleft'
    }).addTo(map);

    // --- STABIL TRACKING ---
    map.on('locationfound', (e) => {
        if (isTracking) {
            const dist = lastLatLng ? e.latlng.distanceTo(lastLatLng) : 999;
            if (!userMarker) {
                // Første gang: Zoom inn til nivå 18
                map.setView(e.latlng, 18);
                userMarker = L.marker(e.latlng).addTo(map);
                userCircle = L.circle(e.latlng, { radius: e.accuracy, color: '#38bdf8', opacity: 0.2 }).addTo(map);
                lastLatLng = e.latlng;
            } else if (dist > 10) {
                map.panTo(e.latlng);
                userMarker.setLatLng(e.latlng);
                userCircle.setLatLng(e.latlng).setRadius(e.accuracy);
                lastLatLng = e.latlng;
            }
        }
    });

    map.on('locationerror', function (e) {
        if (isTracking) {
            alert("Kunne ikke finne posisjonen din: " + e.message);
            isTracking = false;
        }
    });

    map.on('dragstart', function () {
        if (isTracking) {
            isTracking = false;
            const btn = document.getElementById('track-btn');
            if (btn) { btn.innerHTML = '📍'; btn.style.background = '#fff'; }
            map.stopLocate();
        }
    });

    map.on('click', function (e) {
        console.log("Map clicked at", e.latlng);

        const layers = [
            { layer: eiendomLayer, name: 'Eiendom' },
            { layer: groupedOverlays["Kultur & Eiendom"]["Kulturminner"], name: 'Kulturminner' },
            { layer: groupedOverlays["Kultur & Eiendom"]["Reguleringsplan"], name: 'Reguleringsplan' },
            { layer: groupedOverlays["Geologi & Grunn"]["Løsmasser (NGU)"], name: 'Løsmasser (NGU)' },
            { layer: groupedOverlays["Geologi & Grunn"]["Mineralressurser (NGU)"], name: 'Mineralressurser' },
            { layer: groupedOverlays["Geologi & Grunn"]["Berggrunn (NGU Overlegg)"], name: 'Berggrunn (NGU Overlegg)' },
            { layer: groupedOverlays["Geologi & Grunn"]["Radon (NGU)"], name: 'Radon (NGU)' },
            { layer: groupedOverlays["Geologi & Grunn"]["Kvikkleire (NVE)"], name: 'Kvikkleire (NVE)' }
        ];

        // Finn det øverste aktive WMS-laget
        const activeLayerObj = layers.find(l => {
            if (!l.layer || !map.hasLayer(l.layer)) return false;
            const minZ = l.layer.options.minZoom || 0;
            const maxZ = l.layer.options.maxZoom || 99;
            const currentZoom = map.getZoom();
            if (currentZoom < minZ || currentZoom > maxZ) return false;
            return true;
        });

        if (!activeLayerObj) {
            console.log("No visible active WMS layer found for popup");
            return;
        }

        // 1. Definer info-format (Eiendom er plain text, Reguleringsplan krever XML for teknisk data, resten er HTML)
        let infoFormat = activeLayerObj.name === 'Eiendom' ? 'text/plain' : 'text/html';
        if (activeLayerObj.name.includes('Reguleringsplan')) {
            infoFormat = 'application/vnd.ogc.wms_xml';
        }

        let url = getFeatureInfoUrl(map, activeLayerObj.layer, e.latlng, { info_format: infoFormat });

        // Reguleringsplan: Bruk plandialog KommuneInfoWrapper API (WMS GetFeatureInfo er avviklet)
        if (activeLayerObj.name.includes('Reguleringsplan')) {
            const latlng = e.latlng;

            // Konverter WGS84 (lat/lng) til UTM32 (EPSG:25832)
            // Korrekt UTM-konvertering for zone 32N
            const lat = latlng.lat;
            const lng = latlng.lng;

            // WGS84 ellipsoid parameters
            const a = 6378137.0; // semi-major axis
            const ecc = 0.081819190842622; // eccentricity
            const e2 = ecc * ecc;
            const k0 = 0.9996; // scale factor
            const lon0 = 9; // central meridian for zone 32

            const latRad = lat * Math.PI / 180;
            const lonRad = lng * Math.PI / 180;
            const lon0Rad = lon0 * Math.PI / 180;

            // Calculate N (radius of curvature in prime vertical)
            const N = a / Math.sqrt(1 - e2 * Math.sin(latRad) * Math.sin(latRad));

            // Calculate T, C, A
            const T = Math.tan(latRad) * Math.tan(latRad);
            const C = (e2 / (1 - e2)) * Math.cos(latRad) * Math.cos(latRad);
            const A = (lonRad - lon0Rad) * Math.cos(latRad);

            // Calculate M (meridian arc length) - corrected formula
            const e4 = e2 * e2;
            const e6 = e4 * e2;
            const M = a * ((1 - e2 / 4 - 3 * e4 / 64 - 5 * e6 / 256) * latRad
                - (3 * e2 / 8 + 3 * e4 / 32 + 45 * e6 / 1024) * Math.sin(2 * latRad)
                + (15 * e4 / 256 + 45 * e6 / 1024) * Math.sin(4 * latRad)
                - (35 * e6 / 3072) * Math.sin(6 * latRad));

            const utmX = Math.round(500000 + k0 * N * (A + (1 - T + C) * A * A * A / 6 + (5 - 18 * T + T * T + 72 * C - 58 * (e2 / (1 - e2))) * A * A * A * A * A / 120));
            const utmY = Math.round(k0 * (M + N * Math.tan(latRad) * (A * A / 2 + (5 - T + 9 * C + 4 * C * C) * A * A * A * A / 24 + (61 - 58 * T + T * T + 600 * C - 330 * (e2 / (1 - e2))) * A * A * A * A * A * A / 720)));

            // Bygg API-URL med UTM32-koordinater
            // Bruk noisfinnplanerforomraade som returnerer planer for et punkt
            const apiUrl = `https://plandialog.isy.no/PlanregisterGiWs/noisfinnplanerforomraade/EPSG:25832/${utmX}/${utmY}`;

            // Bruk proxy for å unngå CORS-problemer
            const proxyUrl = `/gruver/proxy_xml.php?url=${encodeURIComponent(apiUrl)}`;

            console.log("Henter reguleringsplan fra plandialog API:", apiUrl);
            console.log(`Koordinater: lat=${lat.toFixed(5)}, lng=${lng.toFixed(5)} → UTM32: ${utmX}, ${utmY}`);

            fetch(proxyUrl)
                .then(response => {
                    if (!response.ok) throw new Error(`API feilet: ${response.status}`);
                    return response.json();
                })
                .then(data => {
                    console.log("Plandialog API respons:", data);

                    // API returnerer en array med planer
                    const plans = data?.$values || data;

                    if (!Array.isArray(plans) || plans.length === 0) {
                        throw new Error('Ingen reguleringsplan funnet på dette punktet');
                    }

                    // Finn første reguleringsplan (ikke kommuneplan)
                    const reguleringsplan = plans.find(p => {
                        const planType = p.planType?.kodeverdi || p.plantype?.kodeverdi;
                        return planType && planType !== '20'; // 20 = Kommuneplanens arealdel
                    }) || plans[0]; // Fallback til første plan hvis ingen reguleringsplan

                    // Ekstraher plan-ID
                    const planId = reguleringsplan.arealplanId?.planidentifikasjon ||
                        reguleringsplan.planidentifikasjon ||
                        reguleringsplan.Id;

                    if (planId) {
                        // Bruk plandialog web interface for å vise planen
                        const planUrl = `https://plandialog.isy.no/4003/?funksjon=visplan&kommunenummer=4003&planidentifikasjon=${planId}`;
                        const planNavn = reguleringsplan.plannavn || 'Reguleringsplan';
                        console.log(`Åpner reguleringsplan: ${planNavn} (ID: ${planId})`);

                        if (typeof openModal === 'function') {
                            // Vis modal med forklaring og link
                            const modalContent = `
                                <div style="font-family:'Outfit',sans-serif; padding:20px; text-align:center;">
                                    <h3 style="color:#38bdf8; margin:0 0 15px 0; font-size:1.4rem;">${planNavn}</h3>
                                    <p style="color:#cbd5e1; line-height:1.6; margin-bottom:20px;">
                                        Reguleringsplaner vises i Plandialog, som ikke kan innebygges direkte i kartet. 
                                        Klikk på knappen under for å åpne planen i et nytt vindu.
                                    </p>
                                    <a href="${planUrl}" target="_blank" 
                                       style="display:inline-block; background:#38bdf8; color:#0b0f19; padding:12px 30px; 
                                              border-radius:8px; font-weight:700; text-decoration:none; font-size:1.1rem; 
                                              box-shadow: 0 4px 12px rgba(56, 189, 248, 0.3); transition: all 0.2s;"
                                       onmouseover="this.style.background='#0ea5e9'; this.style.transform='translateY(-2px)'"
                                       onmouseout="this.style.background='#38bdf8'; this.style.transform='translateY(0)'">
                                        ↗️ Åpne reguleringsplan
                                    </a>
                                </div>
                            `;
                            openModal(null, planNavn, null, 0, modalContent);
                        } else {
                            const iframeHtml = `<iframe src="${planUrl}" style="width:100%; height:500px; border:none; border-radius:10px; background:white;"></iframe>`;
                            L.popup().setLatLng(e.latlng).setContent(iframeHtml).openOn(map);
                        }
                    } else {
                        throw new Error('Ingen plan-ID funnet i API-respons');
                    }
                })
                .catch(error => {
                    console.error("Feil ved henting av reguleringsplan:", error);
                    // Vis feilmelding i popup
                    L.popup()
                        .setLatLng(e.latlng)
                        .setContent('<div style="padding:15px; max-width:300px;"><strong>Ingen reguleringsplan funnet</strong><br><br>Det er ingen reguleringsplan registrert på dette punktet.</div>')
                        .openOn(map);
                });

            return;
        }

        if (!url) return;

        console.log("Henter info for:", activeLayerObj.name, "via:", url);

        const isExternal = url.includes('://') || url.startsWith('//');
        const fetchUrl = isExternal ? '/gruver/proxy_xml.php?url=' + encodeURIComponent(url) : url;

        fetch(fetchUrl)
            .then(response => response.text())
            .then(text => {

                // Sjekk om dette er et geofag-lag (NGU/NVE) som skal ha iframe i popup
                const iframeLayers = ['Kvikkleire', 'Løsmasser', 'Berggrunn', 'Radon'];
                const isComplex = iframeLayers.some(l => activeLayerObj.name.includes(l));

                if (isComplex) {
                    console.log("Viser iframe i popup for complex layer:", activeLayerObj.name);

                    let finalSrc;

                    // FORBEDRING: Bruk teksten vi allerede har hentet i stedet for ny proxy-request
                    // Dette gjør at vi kan fikse linker (som Radon-feilen) før visning
                    if (text) {
                        let content = text;

                        // FIX: Radon link (NGU returnerer feil/relativ link)
                        if (activeLayerObj.name.includes('Radon')) {
                            // Erstatt feil link med korrekt link til NGU
                            content = content.replace(
                                /href="[^"]*RadonAktsomhet\.html?[^"]*"/gi,
                                'href="https://geo.ngu.no/service/RadonWMS2/RadonAktsomhet.htm" target="_blank"'
                            );

                            // Sikre at base-URL er korrekt for eventuelle bilder/css
                            if (!content.includes('<base')) {
                                content = content.replace('<head>', '<head><base href="https://geo.ngu.no/">');
                            }
                        }

                        const blob = new Blob([content], { type: 'text/html;charset=UTF-8' });
                        finalSrc = URL.createObjectURL(blob);

                    } else {
                        // Fallback hvis text mangler (skal ikke skje her)
                        finalSrc = 'proxy.php?url=' + encodeURIComponent(url);
                    }

                    const iframeHtml = `
                        <div style="font-family:'Outfit',sans-serif; width:100%; min-width:300px;">
                            <h4 style="margin:0 0 10px 0; color:#38bdf8; font-size:1.3rem; border-bottom:1.2px solid rgba(56,189,248,0.3); padding-bottom:8px;">${activeLayerObj.name}</h4>
                            <div style="margin-bottom: 12px;">
                                <a href="${url}" target="_blank" style="display:block; text-align:center; background:#38bdf8; color:#0b0f19; padding:10px; border-radius:8px; font-weight:700; text-decoration:none; font-size:1rem; box-shadow: 0 4px 12px rgba(56, 189, 248, 0.2);">↗️ Åpne i nytt vindu (dersom den ikke vises)</a>
                            </div>
                            <iframe src="${finalSrc}" style="width:100%; height:450px; border:none; border-radius:10px; background:white;"></iframe>
                        </div>`;

                    const isMobile = window.innerWidth < 1025;
                    L.popup({
                        maxWidth: isMobile ? window.innerWidth - 40 : 700,
                        minWidth: isMobile ? 280 : 500,
                        className: 'property-popup'
                    })
                        .setLatLng(e.latlng)
                        .setContent(iframeHtml)
                        .openOn(map);
                    return;
                }

                const formattedContent = formatFeatureInfo(text, activeLayerObj.name);
                if (formattedContent) {
                    const isMobile = window.innerWidth < 1025;
                    const popupWidth = isMobile ? window.innerWidth - 40 : 850;
                    L.popup({
                        maxWidth: popupWidth,
                        minWidth: isMobile ? 280 : 600,
                        className: 'property-popup'
                    })
                        .setLatLng(e.latlng)
                        .setContent(formattedContent)
                        .openOn(map);
                }
            })
            .catch(err => console.error("Error fetching WMS info:", err));
    });

    map.on('moveend', debouncedUpdateGatenavn);

    // --- AUTOMATIC LAYER TOGGLE LOGIC ---
    function checkLayerToggle() {
        const isTopo = map.hasLayer(topoLayer);
        const isGatenavn = map.hasLayer(dynamiskeGatenavn);
        const hasStedsnavn = map.hasLayer(darkLabels);

        // If Topo + Gatenavn are both active, hide Stedsnavn
        if (isTopo && isGatenavn) {
            if (hasStedsnavn) {
                map.removeLayer(darkLabels);
                map._autoRemovedStedsnavn = true;
            }
        } else {
            // Otherwise, if it was auto-removed, bring it back
            if (map._autoRemovedStedsnavn && !hasStedsnavn) {
                map.addLayer(darkLabels);
                map._autoRemovedStedsnavn = false;
            }
        }
    }

    map.on('baselayerchange overlayadd overlayremove', checkLayerToggle);

    // loadImageRegistry(); // Moved to lazy load when album is opened
    loadData();

    // --- LAZY LOADING AV MARKØRER ---
    map.on('moveend', () => debounce(updateVisibleMarkers, 200)());
    map.on('zoomend', () => debounce(updateVisibleMarkers, 200)());
    map.on('zoomend', updateMarkerSizes); // Zoom-dependent marker sizing
    map.on('moveend', updateMarkerSizes); // Also after lazy-load

    // --- FIRST TIME WELCOME MODAL ---
    // Sjekk om dette er første gang brukeren besøker kartet på denne enheten
    try {
        if (!localStorage.getItem('vox_portal_visited')) {
            // Åpne "Om kartet" automatisk etter en kort forsinkelse (1.2 sek)
            // Dette sikrer at kartet er lastet og gir en penere overgang
            setTimeout(() => {
                if (typeof openAboutModal === 'function') {
                    openAboutModal();
                    // Sett flagg så den ikke åpnes igjen neste gang
                    localStorage.setItem('vox_portal_visited', 'true');
                }
            }, 1200);
        }
    } catch (e) {
        console.warn("Kunne ikke lese fra localStorage:", e);
    }

}

function formatFeatureInfo(text, layerType = 'Eiendom') {
    if (!text || text.includes('404 Not Found')) return null;

    // 1. SPESIALHÅNDTERING FOR REGULERINGSPLAN (Planregister)
    if (layerType.includes('Reguleringsplan')) {
        // Vi pleide å bygge popup-HTML her, men nå håndteres dette direkte i fetch-løkka
        // for å åpne den store modalen i steden. Se fetch(fetchUrl) i map.on('click').
        return null;
    }

    const lowerText = text.toLowerCase();
    if (lowerText.includes('no features found') || lowerText.includes('no features were found')) return null;

    // 2. SJEKK ETTER EKSTERNE LENKER (F.eks. Kulturminnesøk)
    let externalUrl = null;
    const kulturRegex = /(https?:\/\/(?:www\.)?kulturminnesok\.no[^\s"<>]+)/i;
    const kulturMatch = text.match(kulturRegex);
    if (kulturMatch) externalUrl = kulturMatch[1];

    const nguRegex = /oMR\.getrec\(\['([0-9]+)'/i;
    const nguMatch = text.match(nguRegex);
    if (nguMatch && layerType === 'Mineralressurser') {
        externalUrl = `https://geo.ngu.no/api/faktaark/mineralressurser/visImiNasOreOmr.php?objid=${nguMatch[1]}&lang=nor`;
    }

    if (externalUrl) {
        return `<div class="popup-content" style="padding-right: 5px;">
            <div style="font-family:'Outfit',sans-serif; width:100%; display: flex; flex-direction: column;">
                <h4 style="margin:0 0 10px 0; color:var(--accent-color); font-size:1.3rem; border-bottom:1.2px solid var(--accent-glow); padding-bottom:8px; line-height:1.2;">${layerType}</h4>
                <div style="margin-bottom: 15px;">
                    <a href="${externalUrl}" target="_blank" style="display:block; text-align:center; background:#38bdf8; color:#0b0f19; padding:12px; border-radius:10px; font-weight:700; text-decoration:none; width:100%; box-sizing:border-box; box-shadow: 0 4px 12px rgba(56, 189, 248, 0.2); font-size:1.1rem;">📖 Åpne lokalitet i ny fane ↗</a>
                </div>
                <iframe src="${externalUrl}" style="width:100%; height:450px; border:none; border-radius:10px; background:white;"></iframe>
            </div></div>`;
    }

    // 3. PARSING AV DATA (Eiendom og Kulturminner)
    const whitelist = [
        'navn', 'art', 'kategori', 'datering', 'funn', 'materiale', 'matrikkelnummertekst',
        'kommunenavn', 'areal', 'status', 'forekomst', 'mineral',
        'faregrad', 'risiko', 'konsekvens', 'sonestatus', 'områdenavn', 'aktsomhetsgrad',
        'kartlegging', 'rapport', 'url', 'faktaark', 'beskrivelse', 'kommune', 'areal_km2', 'skredtype', 'kommunenummer'
    ];
    const blacklist = ['objectid', 'statusomr', 'faregradsc', 'globalid', 'shape', 'starea', 'stlength', 'objektstatus', 'navnerom'];

    let knr = '', gnr = '', bnr = '';
    let rowsHtml = '';
    let title = layerType === 'Eiendom' ? 'Eiendomskart (Zoom inn)' : layerType;
    const seenData = new Set();

    const addRow = (key, val) => {
        if (!val || val === 'Null' || val === 'null' || val === '0' || val.toLowerCase() === 'false') return;
        let cleanKey = key.toLowerCase().trim();

        // Bruk vasking/whitelist
        if (blacklist.some(b => cleanKey.includes(b))) return;
        if (!whitelist.some(w => cleanKey.includes(w))) return;

        const dataHash = `${cleanKey}:${val.toLowerCase().trim()}`;
        if (seenData.has(dataHash)) return;
        seenData.add(dataHash);

        // Matrikkel-fangst (for SeEiendom-link)
        if (cleanKey.includes('matrikkelnummertekst')) {
            const m = val.match(/(?:(\d+)-)?(\d+)\/(\d+)/);
            if (m) { if (m[1]) knr = m[1]; gnr = m[2]; bnr = m[3]; }
        }
        if (cleanKey === 'kommunenummer' && !knr) knr = val;
        if (cleanKey === 'navn' || cleanKey === 'forekomstnavn' || cleanKey === 'lokalitet_navn') title = val.toUpperCase();

        let displayKey = cleanKey;
        let displayVal = val;
        if (cleanKey === 'arealmerknadtekst') displayKey = 'Arealmerknad';
        else if (cleanKey === 'matrikkelnummertekst') displayKey = 'Matrikkelnummer (Gnr/Bnr)';
        else if (cleanKey === 'lagretberegnetareal') displayKey = 'Areal';
        else if (cleanKey === 'avklarteiere') { displayKey = 'Avklart eiere'; displayVal = `${val} (Eierforhold er ikke verifisert i matrikkelen)`; }
        else {
            displayKey = displayKey.charAt(0).toUpperCase() + displayKey.slice(1).replace(/_/g, ' ');
        }

        const isUrl = displayVal.toLowerCase().startsWith('http') || displayVal.toLowerCase().startsWith('www.');
        if (isUrl) {
            const finalUrl = displayVal.toLowerCase().startsWith('www.') ? 'http://' + displayVal : displayVal;
            rowsHtml += `<tr style="border-bottom: 1px solid rgba(255,255,255,0.08);"><td style="padding:12px 4px; color:#94a3b8; font-weight:600; width:43%;">${displayKey}</td><td style="padding:12px 4px;"><a href="${finalUrl}" target="_blank" style="display:inline-block; background:rgba(56, 189, 248, 0.1); color:#38bdf8; padding:6px 12px; border-radius:6px; text-decoration:none; font-size:0.9rem; border:1px solid rgba(56, 189, 248, 0.3);">Åpne link 🔗</a></td></tr>`;
        } else {
            rowsHtml += `<tr style="border-bottom: 1px solid rgba(255,255,255,0.08);"><td style="padding:12px 4px; color:#94a3b8; font-weight:600; width:43%;">${displayKey}</td><td style="padding:12px 4px; color:#fff;">${displayVal}</td></tr>`;
        }
    };

    if (text.includes('<table') && text.includes('<th')) {
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, 'text/html');
        const tables = doc.querySelectorAll('table');
        tables.forEach(table => {
            const headers = Array.from(table.querySelectorAll('th')).map(th => th.textContent.trim());
            const cells = Array.from(table.querySelectorAll('td')).map(td => td.textContent.trim());
            headers.forEach((key, i) => addRow(key, cells[i]));
        });
    } else {
        const lines = text.split(/\r?\n/).map(l => l.trim()).filter(l => l.length > 0);
        lines.forEach(line => {
            let parts = line.split(/[=:]/);
            if (parts.length < 2 && line.includes('|')) parts = line.split('|');
            if (parts.length >= 2) addRow(parts[0].trim(), parts.slice(1).join(':').trim().replace(/['"]/g, ''));
        });
    }

    if (!rowsHtml) return null;

    let htmlBody = `<div style="font-family:'Outfit',sans-serif;"><h4 style="margin:0 0 15px 0; color:var(--accent-color); font-size:1.3rem; font-weight:800; border-bottom:1.2px solid var(--accent-glow); padding-bottom:10px; line-height:1.2;">${title}</h4><table style="width:100%; border-collapse:collapse; font-size:1rem;">${rowsHtml}</table>`;

    if (gnr && bnr) {
        const finalKnr = knr || '4003';
        htmlBody += `<div style="margin-top:25px; border-top:1.2px solid rgba(255,255,255,0.1); padding-top:15px;">
            <p style="font-size:0.9rem; color:#94a3b8; margin-bottom:12px; line-height:1.4;"><strong>SeEiendom:</strong> Offisiell kilde for eierforhold og tinglyste heftelser.</p>
            <a href="https://seeiendom.kartverket.no/eiendom/${finalKnr}/${gnr}/${bnr}/0/0" target="_blank" style="display:block; text-align:center; background:#38bdf8; color:#0b0f19; padding:14px; border-radius:10px; font-weight:700; text-decoration:none; font-size:1.1rem;">Åpne i SeEiendom ↗</a>
        </div>`;
    }
    htmlBody += `</div>`;
    return `<div class="popup-content" style="max-width:100%; overflow-x:hidden;">${htmlBody}</div>`;
}

function getFeatureInfoUrl(map, layer, latlng, params) {
    const point = map.latLngToContainerPoint(latlng, map.getZoom());
    const size = map.getSize();

    // VIKTIG: Bruk lagets egen CRS hvis den finnes (f.eks. EPSG32632 for Reguleringsplan), ellers kartets CRS
    const crs = layer.options.crs || map.options.crs;
    const bounds = map.getBounds();
    const sw = crs.project(bounds.getSouthWest());
    const ne = crs.project(bounds.getNorthEast());
    const bbox = sw.x + "," + sw.y + "," + ne.x + "," + ne.y;

    let outputParams = {
        request: 'GetFeatureInfo', service: 'WMS',
        version: layer.wmsParams.version, format: layer.wmsParams.format,
        bbox: bbox, height: size.y, width: size.x,
        layers: layer.wmsParams.layers, query_layers: layer.wmsParams.layers,
        info_format: params.info_format || 'text/plain', buffer: 30
    };

    const srsCode = crs.code || (crs.options && crs.options.code);

    if (layer.wmsParams.version === '1.3.0') {
        outputParams.crs = srsCode; outputParams.I = Math.round(point.x); outputParams.J = Math.round(point.y);
    } else {
        outputParams.srs = srsCode; outputParams.X = Math.round(point.x); outputParams.Y = Math.round(point.y);
    }
    outputParams.styles = layer.wmsParams.styles || '';
    outputParams = { ...outputParams, ...params };

    const url = layer._url + L.Util.getParamString(outputParams, layer._url, true);
    // VIKTIG: Noen WMS-servere (som nois.no / Grenlandskart) tåler ikke at komma i BBOX er kodet som %2C.
    return url.replace(/%2C/g, ',');
}

// Funksjon for å bygge album-galleriet i modalløsningen
async function renderDynamicAlbumGrid() {
    const frame = document.getElementById('modal-iframe');
    const label = document.getElementById('modal-title');

    // Lazy load: Last inn bilde-registeret første gang album åpnes
    if (imageRegistry.length === 0) {
        await loadImageRegistry();
    }

    // Oppdater tittel med antall bildeelementer
    const count = Array.isArray(imageRegistry) ? imageRegistry.length : 0;
    label.style.display = 'block';
    label.innerHTML = `Fotoalbum <span style="font-size: 0.9rem; font-weight: 400; color: #94a3b8; margin-left: 10px;">(${count} bildeelementer)</span>`;

    // Bygg HTML-innholdet for galleriet
    let imagesHtml = `
        <div style="padding: 20px 25px; background: rgba(56, 189, 248, 0.05); border-bottom: 1px solid rgba(56, 189, 248, 0.1); margin-bottom: 20px; font-size: 1rem; color: #cbd5e1; line-height: 1.6;">
            <p style="margin: 0;">✨ Dette er et arkiv med utvalgte bilder og videoer. Det finnes <strong>mange flere bilder</strong> på de ulike punktene i kartet – klikk på ikonene i kartet for å se detaljerte bilder fra hver enkelt lokalitet.</p>
        </div>
    `;

    if (count === 0) {
        imagesHtml += `<div style="text-align:center; padding:50px; color:#94a3b8;">Laster inn bilder og videoer... Prøv igjen om et lite øyeblikk.</div>`;
    } else {
        // Vi sorterer bildene slik at de nyeste (høyest timestamp i filnavn) kommer først
        currentAlbumImages = [...imageRegistry].sort((a, b) => b.filename.localeCompare(a.filename));

        imagesHtml += `<div class="album-grid">
            <a href="https://www.youtube.com/playlist?list=PLOZwXap5aJpsdZGJQWJzDNRmHauFFPSpT" target="_blank" class="album-item youtube-card">
                <svg viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0-3.897.266-4.356 2.62-4.385 8.816.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0 3.897-.266 4.356-2.62 4.385-8.816-.029-6.185-.484-8.549-4.385-8.816zm-10.615 12.816v-8l8 3.993-8 4.007z"/></svg>
                <span>Se videoer på YouTube</span>
            </a>`;
        currentAlbumImages.forEach((img, idx) => {
            const localPath = `albumgooglephotos/${img.filename}`;
            const thumbPath = img.thumbnail ? `albumgooglephotos/${img.thumbnail}` : localPath;
            const isVideo = img.type === 'video' || img.filename.endsWith('.mp4');

            imagesHtml += `
                <div class="album-item" data-type="${isVideo ? 'video' : 'image'}" onclick="parent.openModal('${localPath}', 'Album: ${img.type === 'video' ? 'Video' : 'Bilde'}', 'album', ${idx})">
                    ${isVideo ? `
                        <video src="${localPath}" muted playsinline style="width:100%; height:100%; object-fit:cover;"></video>
                        <div style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:50px; height:50px; background:rgba(0,0,0,0.5); border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid white;">
                            <div style="width:0; height:0; border-top:10px solid transparent; border-bottom:10px solid transparent; border-left:15px solid white; margin-left:5px;"></div>
                        </div>
                    ` : `
                        <img src="${thumbPath}" loading="lazy" alt="Album bilde">
                    `}
                    <div class="album-overlay">Klikk for fullskjerm</div>
                </div>`;
        });
        imagesHtml += `</div>`;
    }

    const styledHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
            <style>
                body { margin:0; padding:0; background: #0b0f19; font-family: 'Outfit', sans-serif; color: white; overflow-x: hidden; }
                .youtube-card {
                    background: linear-gradient(135deg, #cc0000 0%, #ff0000 100%);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-decoration: none;
                    color: white;
                    font-weight: 600;
                    text-align: center;
                    padding: 15px;
                    position: relative;
                    z-index: 1;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                }
                .youtube-card:hover { transform: scale(1.05); z-index: 10; box-shadow: 0 10px 30px rgba(255,0,0,0.4); }
                .youtube-card svg { width: 48px; height: 48px; margin-bottom: 10px; fill: white; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
                .youtube-card {
                    background: linear-gradient(135deg, #cc0000 0%, #ff0000 100%);
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                    text-decoration: none;
                    color: white;
                    font-weight: 600;
                    text-align: center;
                    padding: 15px;
                    position: relative;
                    z-index: 1;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.3);
                }
                .youtube-card:hover { transform: scale(1.05); z-index: 10; box-shadow: 0 10px 30px rgba(255,0,0,0.4); }
                .youtube-card svg { width: 48px; height: 48px; margin-bottom: 10px; fill: white; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3)); }
                
                .album-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
                    gap: 16px;
                    padding: 20px;
                }
                .album-item {
                    aspect-ratio: 1;
                    border-radius: 12px;
                    overflow: hidden;
                    cursor: pointer;
                    background: #1a202c;
                    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), border-color 0.3s;
                    border: 1px solid rgba(255,255,255,0.05);
                    position: relative;
                }
                .album-item:hover { transform: scale(1.05); border-color: #38bdf8; z-index: 10; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
                .album-item img, .album-item video { width: 100%; height: 100%; object-fit: cover; }
                .enlarged-overlay {
                    position: fixed; top: 0; left: 0; width: 100%; height: 100%;
                    background: rgba(0,0,0,0.98); z-index: 1000;
                    display: none; align-items: center; justify-content: center;
                    cursor: zoom-out;
                    backdrop-filter: blur(5px);
                }
                .enlarged-overlay img, .enlarged-overlay video { max-width: 95%; max-height: 95%; border-radius: 8px; box-shadow: 0 0 50px rgba(0,0,0,0.8); border: 2px solid rgba(255,255,255,0.1); }
                @media (max-width: 600px) { 
                    .album-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; padding: 15px; }
                    .filter-btn { padding: 8px 14px; font-size: 0.8rem; }
                }
            </style>
            <script>
                let currentItems = [];
                let currentIndex = 0;

                function getVisibleItems() {
                    // Assuming there's a filter button with 'active' class, or default to 'all'
                    const activeBtn = document.querySelector('.filter-btn.active');
                    const activeType = activeBtn ? activeBtn.getAttribute('onclick').match(/'([^']+)'/)[1] : 'all';
                    const allItems = Array.from(document.querySelectorAll('.album-item'));
                    
                    if (activeType === 'all') {
                        return allItems;
                    }
                    return allItems.filter(item => item.getAttribute('data-type') === activeType);
                }

                function filterMedia(type, btn) {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    
                    document.querySelectorAll('.album-item').forEach(item => {
                        if (type === 'all' || item.getAttribute('data-type') === type) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                }

                function showMedia(index) {
                    currentItems = getVisibleItems();
                    
                    if (index < 0) index = currentItems.length - 1;
                    if (index >= currentItems.length) index = 0;
                    currentIndex = index;

                    const item = currentItems[currentIndex];
                    const media = item.querySelector('img, video');
                    const src = media.src.replace('_thumb.jpg', '.jpg'); // Use full res
                    const type = item.getAttribute('data-type');
                    
                    const overlay = document.getElementById('overlay');
                    const oContent = document.getElementById('overlay-content');
                    
                    const videoHtml = \`<video src="\${src}" controls autoplay loop style="max-width:90vw; max-height:90vh; width:auto; height:auto; border-radius:4px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);"></video>\`;
                    const imgHtml = \`<img src="\${src}" style="max-width:90vw; max-height:90vh; width:auto; height:auto; object-fit:contain; border-radius:4px; box-shadow: 0 10px 25px rgba(0,0,0,0.5);">\`;
                    
                    oContent.innerHTML = type === 'video' ? videoHtml : imgHtml;
                    overlay.style.display = 'flex';
                    
                    // Update navigation buttons visibility
                    document.getElementById('prevPic').style.display = currentItems.length > 1 ? 'flex' : 'none';
                    document.getElementById('nextPic').style.display = currentItems.length > 1 ? 'flex' : 'none';
                }

                function hideMedia(e) { 
                    if (e && e.target.id !== 'overlay' && e.target.id !== 'close-btn') return;
                    
                    const overlay = document.getElementById('overlay');
                    const oContent = document.getElementById('overlay-content');
                    oContent.innerHTML = ''; // Stop video
                    overlay.style.display = 'none'; 
                }

                function nextMedia(e) {
                    e.stopPropagation();
                    showMedia(currentIndex + 1);
                }

                function prevMedia(e) {
                    e.stopPropagation();
                    showMedia(currentIndex - 1);
                }

                document.addEventListener('keydown', function(e) {
                    if (document.getElementById('overlay').style.display === 'flex') {
                        if (e.key === 'ArrowLeft') prevMedia(e);
                        if (e.key === 'ArrowRight') nextMedia(e);
                        if (e.key === 'Escape') hideMedia({target: {id: 'overlay'}});
                    }
                });
            </script>
        </head>
        <body>
            <div id="overlay" class="enlarged-overlay" onclick="hideMedia(event)">
                <div id="close-btn" onclick="hideMedia(event)" style="position:absolute; top:20px; right:30px; font-size:40px; color:white; cursor:pointer; z-index:1002; filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5));">&times;</div>
                
                <div id="prevPic" onclick="prevMedia(event)" style="position:absolute; left:20px; top:50%; transform:translateY(-50%); font-size:60px; color:white; cursor:pointer; z-index:1002; padding:20px; user-select:none; display:none; filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5));">&#10094;</div>
                <div id="nextPic" onclick="nextMedia(event)" style="position:absolute; right:20px; top:50%; transform:translateY(-50%); font-size:60px; color:white; cursor:pointer; z-index:1002; padding:20px; user-select:none; display:none; filter:drop-shadow(0 2px 4px rgba(0,0,0,0.5));">&#10095;</div>

                <div id="overlay-content" style="display:flex; align-items:center; justify-content:center; width:100%; height:100%;"></div>
            </div>
            
            ${imagesHtml}
            <script>
                // Initialize click listeners with index tracking
                const items = document.querySelectorAll('.album-item');
                items.forEach((item, index) => {
                    // Start relative index search when clicked
                    item.onclick = function() {
                        // Recalculate Visible Items to find correct index in filtered list
                        const visible = getVisibleItems();
                        const myIndex = visible.indexOf(this);
                        if (myIndex !== -1) {
                            showMedia(myIndex);
                        }
                    }
                });
            </script>
        </body>
        </html>
    `;

    frame.style.display = 'block';
    frame.srcdoc = styledHtml;
}

// Last inn bilde-registeret ved oppstart
async function loadImageRegistry() {
    try {
        const res = await fetch('sync_registry.json?v=' + Date.now());
        if (res.ok) {
            const data = await res.json();
            // sync_registry.json er et objekt, konverter til array
            imageRegistry = Object.values(data);
            console.log("Lastet bilde-register:", imageRegistry.length, "bildeelementer.");
        }
    } catch (e) {
        console.error("Kunne ikke laste bilde-register:", e);
    }
}

let modalInitialUrl = null;

function modalBack() {
    const frame = document.getElementById('modal-iframe');
    if (frame && frame.contentWindow) {
        try {
            const currentUrl = frame.contentWindow.location.href;
            if (modalInitialUrl && (currentUrl === modalInitialUrl || currentUrl.split('?')[0] === modalInitialUrl.split('?')[0])) {
                console.log("Allerede på startsiden, går ikke lenger tilbake.");
                return;
            }
        } catch (e) {
            console.warn("Kunne ikke sjekke historikk-posisjon (cross-origin?):", e);
        }
        frame.contentWindow.history.back();
    }
}

let currentModalSite = null;
let currentModalImageIndex = 0;

function openModal(url, title, siteId = null, imgIndex = 0, htmlContent = null) {
    const modal = document.getElementById('content-modal');
    // Hide search bar to prevent overlap issues on all devices
    const searchContainer = document.getElementById('coord-search-container');
    if (searchContainer) searchContainer.style.display = 'none';
    const frame = document.getElementById('modal-iframe');
    const img = document.getElementById('modal-image');
    const htmlDiv = document.getElementById('modal-html-content'); // New container
    const wrapper = document.getElementById('modal-content-wrapper'); // Wrapper
    const label = document.getElementById('modal-title');
    const extLink = document.getElementById('modal-external-link');
    const errorLink = document.getElementById('modal-error-link');
    const prevBtn = document.getElementById('modal-prev');
    const nextBtn = document.getElementById('modal-next');
    const loader = document.getElementById('modal-loader');
    const backBtn = document.getElementById('modal-back-btn');

    // Show back button always for iframe content (if it's an iframe-based modal)
    // For images/videos shown directly in top-level elements, it's not needed
    if (backBtn) backBtn.style.display = 'none';
    modalInitialUrl = null; // Will be set in onload or after calculating finalUrl

    label.textContent = title || "Innhold";
    if (siteId === 'album') {
        currentModalSite = { id: 'album', images: currentAlbumImages.map(img => `albumgooglephotos/${img.filename}`), name: "Album" };
    } else {
        currentModalSite = siteId !== null ? allSites.find(s => s.id === siteId) : null;
    }
    currentModalImageIndex = imgIndex;

    if (loader) loader.style.display = 'flex';
    if (img) img.style.display = 'none';
    if (frame) {
        frame.style.display = 'none';
        frame.srcdoc = "";
    }
    if (htmlDiv) htmlDiv.style.display = 'none'; // Hide by default

    // Reset wrapper styles to default (image/iframe mode)
    if (wrapper) {
        wrapper.style.display = 'flex';
        wrapper.style.alignItems = 'center';
        wrapper.style.justifyContent = 'center';
        wrapper.style.overflow = 'hidden';
        wrapper.style.background = '#000';
    }

    if (htmlContent) {
        // Compact modal for HTML content (like reguleringsplan)
        modal.classList.add('compact');

        // Adjust wrapper for text content
        if (wrapper) {
            wrapper.style.display = 'block';
            wrapper.style.overflowY = 'auto'; // Enable scrolling
            wrapper.style.background = 'transparent';
        }

        if (htmlDiv) {
            htmlDiv.style.display = 'block';
            htmlDiv.style.height = 'auto'; // Let content drive height
            htmlDiv.innerHTML = htmlContent;
        }
        if (loader) loader.style.display = 'none';
        console.log("Modal opened with htmlContent (div)");
    } else if (url) {
        // Remove compact class for regular content
        modal.classList.remove('compact');
        // Robust bildesjekk for gruvebilder og eksterne kilder
        const isPdf = url && (url.toLowerCase().endsWith('.pdf') || url.toLowerCase().includes('.pdf?'));
        const isImage = url && (
            (url.match(/\.(jpg|jpeg|png|gif|webp)(?:\?|#|$)/i) && !url.includes('GetFeatureInfo')) ||
            url.includes('image_proxy.php') ||
            (url.includes('assets/') && !isPdf) ||
            url.includes('googleusercontent.com')
        );
        const isLocalVideo = url && url.match(/\.(mp4|webm|ogg)(?:\?|#|$)/i);
        const isYouTube = url && url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?|shorts|watch)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/i);

        if (isImage) {
            if (img) {
                img.onload = () => { if (loader) loader.style.display = 'none'; };
                img.onerror = () => {
                    if (loader) loader.style.display = 'none';
                    const errorMsg = document.getElementById('modal-error-msg');
                    if (errorMsg) errorMsg.style.display = 'block';
                    if (img) {
                        img.style.display = 'none';
                        img.className = 'modal-error-img'; // Use class for consistency if needed
                    }
                };
                img.src = url;
                img.style.display = 'block';
                img.style.cursor = 'zoom-in';
                img.title = 'Klikk for å se bildet i full størrelse';
                img.onclick = () => window.open(url, '_blank');
            }
            if (currentModalSite && currentModalSite.images.length > 1) {
                if (prevBtn) prevBtn.style.display = 'flex';
                if (nextBtn) nextBtn.style.display = 'flex';
            } else {
                if (prevBtn) prevBtn.style.display = 'none';
                if (nextBtn) nextBtn.style.display = 'none';
            }
        } else if (isPdf) {
            if (img) img.style.display = 'none';
            // Force white background for PDFs
            if (wrapper) wrapper.style.background = 'white';
            if (frame) {
                frame.style.display = 'block';
                frame.removeAttribute('srcdoc');
                // Force white background for PDFs to prevent black artifacts
                frame.style.background = 'white';
                frame.src = url;
                frame.onload = () => { if (loader) loader.style.display = 'none'; };
                // Fallback hide loader after 2s if onload doesn't fire (common for PDFs)
                setTimeout(() => { if (loader) loader.style.display = 'none'; }, 2000);
            }
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
        } else if (isLocalVideo) {
            if (img) img.style.display = 'none';
            if (frame) {
                frame.style.display = 'block';
                frame.srcdoc = `
                    <body style="margin:0; background:#0b0f19; display:flex; align-items:center; justify-content:center; height:100vh;">
                        <video src="${url}" controls autoplay style="max-width:100%; max-height:100%;"></video>
                    </body>
                `;
            }
            if (loader) loader.style.display = 'none';
            if (currentModalSite && currentModalSite.images.length > 1) {
                if (prevBtn) prevBtn.style.display = 'flex';
                if (nextBtn) nextBtn.style.display = 'flex';
            }
        } else if (isYouTube) {
            const videoId = isYouTube[1];
            const embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&rel=0`;
            if (img) img.style.display = 'none';
            if (frame) {
                frame.style.display = 'block';
                frame.removeAttribute('srcdoc');
                frame.src = embedUrl;
                frame.onload = () => { if (loader) loader.style.display = 'none'; };
                // Fallback hide loader
                setTimeout(() => { if (loader) loader.style.display = 'none'; }, 2000);
            }
            if (prevBtn) prevBtn.style.display = 'none';
            if (nextBtn) nextBtn.style.display = 'none';
        } else {
            let finalUrl = url;
            const htmlContent = document.getElementById('modal-html-content');

            // Spesialhåndtering for NGU som ikke tillater iframe
            if (url.includes('aps.ngu.no')) {
                if (loader) loader.style.display = 'none';
                if (img) img.style.display = 'none';
                if (frame) frame.style.display = 'none';

                // Add compact class for transparent background
                modal.classList.add('compact');

                // Override background for weak dimming
                modal.style.background = 'rgba(0, 0, 0, 0.3)';
                modal.style.backdropFilter = 'blur(2px)';

                // Override container style for auto-sizing
                const container = modal.querySelector('.modal-container');
                if (container) {
                    container.style.width = 'auto';
                    container.style.height = 'auto';
                    container.style.maxWidth = '90%';
                    container.style.minWidth = '300px';
                }

                if (htmlContent) {
                    htmlContent.style.display = 'flex';
                    htmlContent.style.flexDirection = 'column';
                    htmlContent.style.alignItems = 'center';
                    htmlContent.style.justifyContent = 'center';
                    htmlContent.style.textAlign = 'center';
                    htmlContent.style.padding = '30px';

                    htmlContent.innerHTML = `
                       <h3 style="color: var(--accent-color); margin-bottom: 15px; font-size: 1.4rem;">Faktaark fra NGU</h3>
                       <p style="color: var(--text-main); margin-bottom: 25px; max-width: 350px; line-height: 1.6;">
                           Denne informasjonen kan ikke vises direkte i kartet.
                       </p>
                       <a href="${url}" target="_blank" style="
                           display: inline-flex;
                           align-items: center; 
                           gap: 8px;
                           background-color: #38bdf8; 
                           color: #0f172a; 
                           padding: 10px 20px; 
                           border-radius: 8px; 
                           text-decoration: none; 
                           font-weight: 700; 
                           font-size: 0.95rem;
                           box-shadow: 0 4px 12px rgba(56, 189, 248, 0.4);
                           transition: transform 0.2s;
                       " onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                           <span style="font-size: 1.2em;">↗</span> Åpne faktaark
                       </a>
                   `;
                }
                modal.style.display = 'flex';
                return;
            }

            // Reset styles for normal use
            modal.classList.remove('compact');
            modal.style.background = ''; // Reset to CSS default
            modal.style.backdropFilter = '';

            const container = modal.querySelector('.modal-container');
            if (container) {
                container.style.width = ''; // Reset to CSS default
                container.style.height = '';
                container.style.maxWidth = '';
                container.style.minWidth = '';
            }

            // Reset htmlContent display for other modifications
            if (htmlContent) htmlContent.style.display = 'none';


            // Reset htmlContent display for other modifications
            if (htmlContent) htmlContent.style.display = 'none';

            if (url.includes('photos.app.goo.gl') || url.includes('photos.google.com/share/')) {
                // Bygg dynamisk lokalt galleri istedenfor ekstern lenke
                finalUrl = 'about:blank';
                if (loader) loader.style.display = 'none';
                if (extLink) extLink.style.display = 'none';
                modal.style.display = 'flex';
                renderDynamicAlbumGrid();
                return;
            }
            else {
                const overlay = document.getElementById('album-click-overlay');
                if (overlay) overlay.style.display = 'none';

                if (url && url.startsWith('http')) {
                    // Unntak: Kulturminnesøk skal ikke gå via proxy (samme som i WMS-popup)
                    if (url.includes('kulturminnesok.no')) {
                        finalUrl = url;
                    }
                    // Hvis URL allerede er wrappet i proxy, ikke wrap igjen
                    else if (url.includes('proxy.php') || url.includes('proxy_xml.php')) {
                        finalUrl = url + (url.includes('?') ? '&' : '?') + 'cachebust=' + Date.now();
                    } else {
                        finalUrl = 'proxy.php?url=' + encodeURIComponent(url);
                    }
                }
            }

            console.log("Modal: Setting iframe src to:", finalUrl);
            if (frame) {
                frame.onload = () => {
                    // Litt ekstra forsinkelse før vi fjerner loader, så innholdet rekker å tegnes
                    setTimeout(() => { if (loader) loader.style.display = 'none'; }, 1000);

                    if (!modalInitialUrl) {
                        modalInitialUrl = frame.contentWindow.location.href;
                    }

                    if (backBtn) {
                        backBtn.style.display = 'block';
                    }

                    // Style standalone images
                    const currentUrl = frame.contentWindow.location.pathname;
                    if (currentUrl.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                        try {
                            const doc = frame.contentDocument || frame.contentWindow.document;
                            doc.body.style.backgroundColor = 'black';
                            doc.body.style.margin = '0';
                            doc.body.style.display = 'flex';
                            doc.body.style.alignItems = 'center';
                            doc.body.style.justifyContent = 'center';
                            doc.body.style.height = '100vh';
                            doc.body.style.overflow = 'hidden';
                            const docImg = doc.querySelector('img');
                            if (docImg) {
                                docImg.style.maxWidth = '100%';
                                docImg.style.maxHeight = '100%';
                                docImg.style.objectFit = 'contain';
                            }
                        } catch (e) {
                            console.warn("Could not style iframe content (cross-origin?):", e);
                        }
                    }
                };
                frame.style.display = 'block';

                // Fix for Kulturminnesøk black screen: Force white background and clear srcdoc
                if (finalUrl.includes('kulturminnesok.no') || finalUrl.endsWith('.html') || !finalUrl.startsWith('http')) {
                    frame.style.background = 'white';
                    frame.removeAttribute('srcdoc');
                } else {
                    frame.style.background = 'transparent'; // Ensure iframe background is transparent for others
                }

                frame.src = finalUrl;
            }
        }
    }

    if (extLink) {
        // For reguleringsplan, link til plandialog search page
        if (url && url.includes('plandialog.isy.no')) {
            extLink.href = 'https://plandialog.isy.no/findplan/planstatuskodeverdi/3,6,7';
        } else {
            extLink.href = url || "#";
        }
        // Vis knappen for alle eksterne lenker (ikke lokale assets/bilder), men skjul for albuminnhold der vi har stor blå knapp
        const isAlbum = url && url.includes('photos.app.goo.gl');
        const isImage = url && (
            (url.match(/\.(jpg|jpeg|png|gif|webp)(?:\?|#|$)/i) && !url.includes('GetFeatureInfo')) ||
            url.includes('image_proxy.php') ||
            url.includes('assets/') ||
            url.includes('googleusercontent.com')
        );
        extLink.style.display = (url && !url.includes('assets/') && !isAlbum && !isImage) ? 'block' : 'none';
    }
    if (errorLink) errorLink.href = url || "";
    modal.style.display = 'flex';
}

function changeModalImage(delta) {
    if (!currentModalSite || currentModalSite.images.length <= 1) return;
    currentModalImageIndex = (currentModalImageIndex + delta + currentModalSite.images.length) % currentModalSite.images.length;

    const newUrl = currentModalSite.images[currentModalImageIndex];
    const title = currentModalSite.id === 'album' ? `Album: Bilde ${currentModalImageIndex + 1} / ${currentModalSite.images.length}` : currentModalSite.name;

    // Kall openModal på nytt for å tegne korrekt (håndterer bilde vs video vs iframe)
    openModal(newUrl, title, currentModalSite.id, currentModalImageIndex);
}

function closeModal() {
    const modal = document.getElementById('content-modal');
    modal.style.display = 'none';

    // Restore search bar
    const searchContainer = document.getElementById('coord-search-container');
    if (searchContainer) searchContainer.style.display = ''; // Reset to CSS default (flex)

    const frame = document.getElementById('modal-iframe');
    const img = document.getElementById('modal-image');
    const prevBtn = document.getElementById('modal-prev');
    const nextBtn = document.getElementById('modal-next');
    if (frame) frame.src = '';
    if (img) img.src = '';
    if (prevBtn) prevBtn.style.display = 'none';
    if (nextBtn) nextBtn.style.display = 'none';
    currentModalSite = null;
}

function openAboutModal() { document.getElementById('about-modal').style.display = 'flex'; }
function closeAboutModal() { document.getElementById('about-modal').style.display = 'none'; }

async function loadData() {
    try {
        const response = await fetch('full_data.json?v=' + Date.now());
        const data = await response.json();

        // Load local points (admin-created, separate from Google My Maps sync)
        let localFeatures = [];
        try {
            const localResp = await fetch('local_points.json?v=' + Date.now());
            if (localResp.ok) {
                const localData = await localResp.json();
                if (Array.isArray(localData)) localFeatures = localData;
            }
        } catch (e) { /* local_points.json may not exist yet */ }

        // Merge: Google points + local points
        const allFeatures = [...data.features, ...localFeatures];

        // Tøm eksisterende data
        allSites = [];
        markerLayer.clearLayers();

        allFeatures.forEach((feature, i) => {
            const props = feature.properties;
            const geom = feature.geometry;

            // --- Server-Side Parsed Data ---
            // Nå henter vi ferdigtygget data rett fra JSON!
            const catKey = props.catKey || 'DEFAULT';
            const category = categoryMap[catKey];

            // Koordinater (ferdig beregnet i PHP, eller hentet fra geometri)
            const isLine = props.isLine;
            let coordsArray = [];

            if (isLine) {
                // GeoJSON er [lng, lat], Leaflet vil ha [lat, lng]
                coordsArray = geom.coordinates.map(c => [c[1], c[0]]);
            } else {
                coordsArray = [[geom.coordinates[1], geom.coordinates[0]]];
            }

            const site = {
                id: props.id || i, // Bruk ID fra PHP eller index
                name: props.name,                          // Original Google-navn (brukes internt)
                displayName: props.displayName || null,    // Lokalt overstyrt visningsnavn
                description: props.cleanDesc, // Ferdig vasket beskrivelse
                displayDesc: props.displayDesc || null,    // Lokalt overstyrt beskrivelse
                lat: props.lat,
                lng: props.lng,
                coordsArray: coordsArray,
                isLine: isLine,
                category: category,
                catKey: catKey,
                images: props.images || [],
                localImages: props.localImages || [],
                remoteImageUrl: props.remoteImageUrl,
                imageUrl: props.imageUrl,
                currentImageIndex: 0,
                links: props.links || [],
                styleUrl: props.styleUrl,
                hidden: props.hidden || false,
                isLocalPoint: props.isLocalPoint || false,
                credit: props.credit || null,
                creditUrl: props.creditUrl || null,
                isVisibleByFilter: true // Default synlig
            };

            // Ingen hardkoding her lenger! Alt er flyttet til convert_geojson_v2.php

            allSites.push(site);
            addMarker(site);
        });

        // Start lazy loading initielt
        updateVisibleMarkers();
        updateMarkerSizes(); // Set initial marker sizes

        summarizeStats();
        displayDailyHighlight();
        renderRecentChanges(5);

        setTimeout(() => renderSidebarList(allSites), 500);
        document.getElementById('recent-count').addEventListener('change', (e) => renderRecentChanges(parseInt(e.target.value)));

        // START ADMIN TOOLS IF LOGGED IN
        if (typeof initAdminTools === 'function' && window.isAdminMode) {
            initAdminTools();
        }

    } catch (err) {
        console.error("Error loading JSON data (V2):", err);
    } finally {
        const loadingOverlay = document.getElementById('loading-overlay');
        if (loadingOverlay) {
            loadingOverlay.style.opacity = '0';
            setTimeout(() => { loadingOverlay.style.display = 'none'; }, 500);
        }
    }
}

function updateVisibleMarkers() {
    if (!map) return;
    const bounds = map.getBounds();
    // Legg til en buffer rundt skjermen for å unngå "popping"
    const paddedBounds = bounds.pad(0.2);

    // Samle alle synlige markører
    const keysToKeep = new Set();

    allSites.forEach(site => {
        if (!site.marker) return;

        if (!site.isVisibleByFilter || site.hidden) {
            if (markerLayer.hasLayer(site.marker)) {
                markerLayer.removeLayer(site.marker);
            }
            if (site.lineHandle && markerLayer.hasLayer(site.lineHandle)) {
                markerLayer.removeLayer(site.lineHandle);
            }
            return;
        }

        // Sjekk om site er i bounds eller om den har en åpen popup
        const isVisible = paddedBounds.contains([site.lat, site.lng]);
        const isOpen = site.marker.isPopupOpen && site.marker.isPopupOpen();

        if (isVisible || isOpen) {
            keysToKeep.add(site.id); // Bruk ID for unikhet
            if (!site.isLine && !markerLayer.hasLayer(site.marker)) {
                markerLayer.addLayer(site.marker);
            }
            // Skjul handle for tracéer også (bruker ønsker dem skjult fra kartvisningen)
            // Men vis dem hvis de er åpne (f.eks. ved søk)
            if (site.lineHandle) {
                if (isOpen) {
                    if (!markerLayer.hasLayer(site.lineHandle)) markerLayer.addLayer(site.lineHandle);
                } else {
                    if (markerLayer.hasLayer(site.lineHandle)) markerLayer.removeLayer(site.lineHandle);
                }
            }
        } else {
            if (markerLayer.hasLayer(site.marker)) {
                markerLayer.removeLayer(site.marker);
            }
            if (site.lineHandle && markerLayer.hasLayer(site.lineHandle)) {
                markerLayer.removeLayer(site.lineHandle);
            }
        }
    });
}

function summarizeStats() {
    const stats = {};
    allSites.filter(s => !s.hidden && !s.isLine && s.isVisibleByFilter).forEach(s => {
        stats[s.catKey] = (stats[s.catKey] || 0) + 1;
    });
    const container = document.getElementById('stats-container'); container.innerHTML = '';
    Object.entries(stats).sort((a, b) => b[1] - a[1]).forEach(([key, count]) => {
        const name = categoryMap[key].name; const item = document.createElement('div');
        item.className = 'stat-item'; item.style.cursor = 'pointer';
        item.innerHTML = `<span class="stat-value">${count}</span><span class="stat-label">${name}</span>`;
        item.onclick = () => {
            selectAllCats(false); // Uncheck all
            const cb = document.getElementById('cat-' + key);
            if (cb) { cb.checked = true; handleCatChange(cb); }
            document.getElementById('search').scrollIntoView({ behavior: 'smooth' });
        };
        container.appendChild(item);
    });
}

function toggleMarkers(checkbox) {
    if (checkbox.checked) { if (!map.hasLayer(markerLayer)) map.addLayer(markerLayer); }
    else { if (map.hasLayer(markerLayer)) map.removeLayer(markerLayer); }
}

function renderRecentChanges(count) {
    const container = document.getElementById('recent-changes-container'); container.innerHTML = '';
    const recent = [...allSites].reverse().slice(0, count);
    recent.forEach(site => {
        const item = document.createElement('div'); item.className = 'recent-item';
        item.innerHTML = `<span class="recent-icon">${site.category.icon}</span><span>${site.name}</span>`;
        item.style.cursor = 'pointer'; item.onclick = () => focusSite(site.id);
        container.appendChild(item);
    });
}

function displayDailyHighlight() {
    // Finn alle steder som har bilder (prioriter de med lokale bilder)
    let sitesWithImages = allSites.filter(s => s.localImages && s.localImages.length > 0);
    if (sitesWithImages.length === 0) {
        sitesWithImages = allSites.filter(s => s.images && s.images.length > 0);
    }

    if (sitesWithImages.length === 0) return;

    const section = document.getElementById('daily-photo-section');
    const container = document.getElementById('daily-photo-container');
    const site = sitesWithImages[Math.floor(Math.random() * sitesWithImages.length)];

    // Hent bilde-URL (lokal hvis mulig, ellers original via proxy)
    let displayUrl = (site.localImages && site.localImages[0]) ? site.localImages[0] : null;
    let fallbackUrl = (site.images && site.images[0]) ? `image_proxy.php?url=${encodeURIComponent(site.images[0])}` : null;

    if (!displayUrl && !fallbackUrl) return;

    const initialSrc = displayUrl || fallbackUrl;
    // Hvis lokal fil feiler, prøv proxy som backup
    const onerrorAttr = displayUrl && fallbackUrl ? `onerror="this.onerror=null; this.src='${fallbackUrl}';"` : '';

    container.innerHTML = `
        <img src="${initialSrc}" ${onerrorAttr} alt="${site.name}" style="width:100%; border-radius:12px; aspect-ratio:16/9; object-fit:cover; box-shadow:0 4px 12px rgba(0,0,0,0.3);">
        <div style="margin-top:10px;">
            <strong>${site.category.icon} ${site.category.name}</strong><br>
            <span style="font-size:0.9em; opacity:0.8; color:#cbd5e1;">${site.name}</span>
        </div>`;

    container.style.cursor = 'pointer';
    container.onclick = () => focusSite(site.id);
    section.style.display = 'block';
}

function focusSite(id) {
    const site = allSites.find(s => s.id === id);
    if (site) {
        // Fly to site first, this triggers moveend -> updateVisibleMarkers -> marker added
        map.flyTo([site.lat, site.lng], 16, { animate: true, duration: 1.5 });

        // Wait for flyTo to complete (or at least start moving so bounds update)
        // Since updateVisibleMarkers is debounced, we might need to force add it if we want to open popup immediately
        if (!markerLayer.hasLayer(site.marker)) {
            markerLayer.addLayer(site.marker);
        }

        setTimeout(() => {
            site.marker.openPopup();
        }, 1600);
    }
}


// --- ZOOM-DEPENDENT MARKER SIZING (v2) ---
// To disable entirely: set MARKER_ZOOM_SCALING_ENABLED = false
var MARKER_ZOOM_SCALING_ENABLED = true;

function getMarkerSize(zoom) {
    if (!MARKER_ZOOM_SCALING_ENABLED) return { size: 28, fontSize: 18 };
    var minZoom = 10, maxZoom = 16;
    var minSize = 12, maxSize = 28;
    var minFont = 6, maxFont = 18;
    if (zoom <= minZoom) return { size: minSize, fontSize: minFont };
    if (zoom >= maxZoom) return { size: maxSize, fontSize: maxFont };
    var t = (zoom - minZoom) / (maxZoom - minZoom);
    return {
        size: Math.round(minSize + (maxSize - minSize) * t),
        fontSize: Math.round(minFont + (maxFont - minFont) * t)
    };
}

function updateMarkerSizes() {
    if (!MARKER_ZOOM_SCALING_ENABLED) return;
    var ms = getMarkerSize(map.getZoom());
    document.querySelectorAll('.emoji-marker').forEach(function(el) {
        el.style.width = ms.size + 'px';
        el.style.height = ms.size + 'px';
        el.style.marginLeft = (-ms.size / 2) + 'px';
        el.style.marginTop = (-ms.size / 2) + 'px';
        var inner = el.querySelector('div');
        if (inner) inner.style.fontSize = ms.fontSize + 'px';
    });
}

function addMarker(site) {
    let marker;
    if (site.isLine) {
        marker = L.polyline(site.coordsArray, { color: site.category.color, weight: 5, opacity: 0.8, lineJoin: 'round' });
        const handle = L.circleMarker(site.coordsArray[0], { radius: 8, fillColor: site.category.color, color: '#fff', weight: 2, fillOpacity: 1 });
        handle.bindTooltip(`${site.name} (Tras\u00e9)`); handle.on('click', () => marker.openPopup());
        site.lineHandle = handle;
    } else {
        const emojiIcon = L.divIcon({ className: 'emoji-marker', html: `<div style="color: ${site.category.color}; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">${site.category.icon}</div>`, iconSize: [28, 28], iconAnchor: [14, 14] });
        marker = L.marker([site.lat, site.lng], { icon: emojiIcon, interactive: true, riseOnHover: true });
    }
    marker.bindTooltip(`${site.displayName || site.name}<br><span style="font-size: 0.8em; color: #aaa;">${site.lat.toFixed(5)}, ${site.lng.toFixed(5)}</span>`, { direction: 'top', offset: [0, -20], opacity: 0.9, className: 'custom-tooltip' });
    site.marker = marker;
    // markerLayer.addLayer(marker); // REMOVED: Managed by lazy load (updateVisibleMarkers)
    marker.bindPopup(() => getPopupHTML(site), {
        maxWidth: window.innerWidth > 1024 ? 300 : 350,
        autoPanPaddingTopLeft: L.point(20, 20), // Reduced to allow overlap with search bar
        autoPanPaddingBottomRight: L.point(20, 20),
        closeButton: true,
        className: 'point-popup'
    });
    marker.on('popupopen', () => {
        // GA4 Sporing
        if (typeof gtag === 'function') {
            gtag('event', 'vis_gruve', {
                'gruve_navn': site.name,
                'kategori': site.category.name
            });
        }

        if (window.isAdminMode) return; // Unngå disorientering ved flytting i admin-modus
        const px = map.project([site.lat, site.lng], map.getZoom());
        // More aggressive offset to push marker to bottom on desktop
        px.y -= (window.innerWidth > 1024 ? 350 : 250);
        map.setView(map.unproject(px, map.getZoom()), map.getZoom(), { animate: true });
    });
}

function getPopupHTML(site) {
    const lat = site.lat.toFixed(5); const lng = site.lng.toFixed(5);
    let html = `<div class="popup-container">
        <div class="popup-header-row">
            <div class="popup-category" style="color: ${site.category.color};">
                <span class="popup-category-icon">${site.category.icon}</span> ${site.category.name}
            </div>
        </div>
        <h3 class="popup-title">${site.displayName || site.name}</h3>
        <div class="popup-coords">📍 ${lat}, ${lng}</div>`;

    if (window.isAdminMode) {
        html += `<div style="margin-top: 10px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 5px; background: rgba(245, 158, 11, 0.1); border-radius: 8px; border: 1px dashed #f59e0b;">
            <button onclick="map.closePopup()" style="padding: 8px; background: #f59e0b; border: 1px solid #f59e0b; color: #111827; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 4px;">🎯 Flytt</button>
            <button onclick="editSiteLabel(${site.id})" style="padding: 8px; background: #111827; border: 1px solid #38bdf8; color: #38bdf8; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">✏️ Rediger</button>
            <button onclick="hideSite('${site.name.replace(/'/g, "\\'")}', ${site.lat}, ${site.lng})" style="padding: 8px; background: #111827; border: 1px solid #f59e0b; color: #f59e0b; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">🙈 Skjul</button>
            <button onclick="deleteSite('${site.name.replace(/'/g, "\\'")}', ${site.lat}, ${site.lng})" style="padding: 8px; background: #111827; border: 1px solid #ef4444; color: #ef4444; border-radius: 6px; cursor: pointer; font-size: 0.8rem; font-weight: 600;">🗑️ Slett</button>
        </div>`;
    }

    html += `<div style="margin-top: 10px;">`;
    const curIdx = site.currentImageIndex;
    const currentExt = site.images && site.images.length > 0 ? site.images[curIdx] : null;
    const currentLocal = site.localImages && site.localImages.length > 0 ? site.localImages[curIdx] : null;

    // ALDRI proxy lokale assets/ filer
    let displayUrl = currentLocal;
    if (!displayUrl && currentExt) {
        const isLocal = currentExt.toLowerCase().startsWith('assets/') ||
            currentExt.toLowerCase().startsWith('albumgooglephotos/') ||
            currentExt.startsWith('assets/') ||
            currentExt.startsWith('albumgooglephotos/');
        displayUrl = isLocal ? currentExt : `image_proxy.php?url=${encodeURIComponent(currentExt)}`;
    }

    let fallbackCode = (currentLocal && currentExt) ? `onerror="this.onerror=null; this.src='image_proxy.php?url=${encodeURIComponent(currentExt)}';"` : '';

    let imageSection = displayUrl ? `
        <div class="image-carousel" style="cursor: pointer; position: relative; margin-bottom: 5px;" title="Klikk for å se bildet stort" onclick="openModal('${displayUrl}', '${site.name}', ${site.id}, ${curIdx})">
            <img src="${displayUrl}" ${fallbackCode} alt="${site.name}" style="transition: transform 0.3s ease; border-radius: 12px; width: 100%;">
            ${site.images.length > 1 ? `<button type="button" class="carousel-btn prev" onclick="event.stopPropagation(); event.preventDefault(); updatePopupImage(event, ${site.id}, -1)">❮</button><button type="button" class="carousel-btn next" onclick="event.stopPropagation(); event.preventDefault(); updatePopupImage(event, ${site.id}, 1)">❯</button><div class="image-counter">${curIdx + 1} / ${site.images.length}</div>` : ''}
        </div>` : '';

    const shortenLink = (url) => {
        try {
            let clean = url.replace(/^https?:\/\//, '').replace(/^www\./, '');
            if (clean.length > 30) return clean.substring(0, 27) + '...';
            return clean;
        } catch (e) { return url; }
    };

    // --- DESCRIPTION & BUTTONS LOGIC ---
    let desc = (site.displayDesc || site.description || '').replace(/\n/g, '<br>');
    const sName = (site.name || '').toLowerCase();
    const sId = Number(site.id);

    // 1. Initial Cleanup
    desc = desc.replace(/Nettside:\s*https?:\/\/[^\s<"']+(\s*og\s*)?/gi, '');
    desc = desc.replace(/\(Kilde Stein Finmarks masteroppgave\. Se link for mer info\)\.?/gi, '');
    desc = desc.replace(/\]\]\>/g, '').replace(/<!\[CDATA\[/gi, '');

    // 2. Identify and Generate Buttons
    let finalButtons = [];
    let kulturUrl = null;
    let videoCount = 0;

    const norm = (u) => {
        try {
            if (!u || typeof u !== 'string') return '';
            // Aggressive cleaning of trailing junk/conjunctions
            let s = u.trim().replace(/[\u00A0\s\t\n\r]+(og|and)[\u00A0\s\t\n\r]*$/gi, '');
            s = s.replace(/[.,;!?\)\(\s\u00A0\t]+$/g, '');
            return s.toLowerCase().replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/$/, '').split('?')[0];
        } catch (e) { return u; }
    };

    let sourceLinks = Array.isArray(site.links) ? [...site.links] : (site.links ? [site.links] : []);
    const textLinksFound = (site.displayDesc || site.description || '').match(/https?:\/\/[^\s<"'\(\)\[\]]+/g) || [];

    let allLinks = [...sourceLinks, ...textLinksFound];

    // FORCE CLEAN KJEMPA (Special case for the corrupt link)
    allLinks = allLinks.map(l => (typeof l === 'string') ? l.replace(/kjempeaasen[\u00A0\s]+og$/i, 'kjempeaasen') : l);

    // MEGA-OVERRIDE (Absolute fallback)
    if (sId === 39 || sName.includes('kjempa')) {
        if (!allLinks.some(l => norm(l).includes('skiensatlas'))) {
            allLinks.push('http://www.skiensatlas.org/soner/vestmarka/kjempeaasen');
        }
    }
    if (sId === 49 || sName.includes('bjordamskollen')) {
        if (!allLinks.some(l => norm(l).includes('vindmul'))) allLinks.push('https://vindmul.wordpress.com/2016/07/13/bjordamskollen/');
        if (!allLinks.some(l => norm(l).includes('kulturminne') || norm(l).includes('askeladden'))) {
            allLinks.push('https://www.kulturminnesok.no/kart/?id=22507');
        }
    }

    let seenNorm = new Set();
    let uniqueLinks = [];
    for (let i = 0; i < allLinks.length; i++) {
        const l = allLinks[i];
        if (!l || typeof l !== 'string') continue;
        const n = norm(l);
        if (!n || seenNorm.has(n)) continue;
        seenNorm.add(n);
        uniqueLinks.push(l);
    }

    for (let i = 0; i < uniqueLinks.length; i++) {
        const link = uniqueLinks[i];
        const lower = link.toLowerCase();

        if (lower.includes('bora.uib.no')) continue;
        if (lower.includes('skpqzkgqq3e6zr7n6') || lower.includes('zkktl6esvvg6rdcn9')) continue;

        // Kulturminnesøk / Askeladden
        if (lower.includes('kulturminnesok.no') || lower.includes('askeladden') || lower.includes('kulturminne.no')) {
            if (kulturUrl) continue;
            let decL = link;
            try { decL = decodeURIComponent(link); } catch (e) { }
            const idM = decL.match(/\/lokalitet\/(\d+)/) || decL.match(/[?&]id=(\d+)/) || decL.match(/lokalitet\/(\d+)/) || decL.match(/[?&]q=(\d+)/);
            kulturUrl = (idM && idM[1]) ? `https://www.kulturminnesok.no/kart/?id=${idM[1]}` : link;
            continue;
        }

        const isVideo = lower.includes('youtube.com') || lower.includes('youtu.be') || lower.includes('rz7on7pvrtrc');
        const isAlbum = lower.includes('photos.app.goo.gl') || lower.includes('photos.google.com/share/');
        const isImg = lower.match(/\.(jpg|jpeg|png|gif|webp|bmp|svg)(\?|#|$)/i) ||
            lower.includes('googleusercontent.com') ||
            lower.includes('hostedimage') ||
            lower.includes('mymaps.usercontent');

        if (isImg && !isVideo && !isAlbum) {
            if (lower.includes('hostedimage') || lower.includes('mymaps.usercontent') || lower.includes('googleusercontent.com')) continue;
            if (site.images && site.images.some(img => norm(img) === norm(link))) continue;
        }

        if (isVideo) videoCount++;
        let label = '📄 Les mer';
        if (isAlbum) label = '🖼️ Se Album';
        else if (isVideo) label = (videoCount > 1) ? `📽️ Se video ${videoCount}` : '📽️ Se video';
        else {
            try {
                const domain = link.split('/')[2].replace('www.', '');
                label = `📄 Les mer (${domain})`;
            } catch (e) { }
        }
        finalButtons.push({ label, action: `openModal('${link}', '${site.name.replace(/'/g, "\\'")}')` });
    }

    // --- ABSOLUTE BUTTON FORCING (v100) ---
    // If we're on Bjordamskollen (49) or Kjempa (39), we override to ensure EXACTLY the right buttons appear.
    if (sId === 49 || sName.includes('bjordamskollen')) {
        kulturUrl = 'https://www.kulturminnesok.no/kart/?id=22507';
        finalButtons = [
            {
                label: '📄 Les mer (wordpress.com)',
                action: `openModal('https://vindmul.wordpress.com/2016/07/13/bjordamskollen/', '${site.name.replace(/'/g, "\\'")}')`
            }
        ];
    } else if (sId === 39 || sName.includes('kjempa')) {
        kulturUrl = 'https://www.kulturminnesok.no/kart/?id=52633';
        finalButtons = [
            {
                label: '📄 Les mer (skiensatlas.org)',
                action: `openModal('http://www.skiensatlas.org/soner/vestmarka/kjempeaasen', '${site.name.replace(/'/g, "\\'")}')`
            }
        ];
    }

    // 3. Assemble Buttons HTML
    let buttonsHtml = '';
    if (kulturUrl) {
        buttonsHtml += `<br><button onclick="openModal('${kulturUrl}', '${site.name.replace(/'/g, "\\'")}')" style="margin-top: 15px; width: 100%; padding: 14px; background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(34, 197, 94, 0.1)); border: 1px solid rgba(34, 197, 94, 0.4); color: white; border-radius: 12px; cursor: pointer; font-weight: 700; font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s;">🔗 Kulturminnesøk (Kart)</button>`;
    }
    const isBygdeborgFound = site.catKey === 'BYGDEBORG' || (site.name && site.name.toLowerCase().includes('bygdeborg'));
    if (isBygdeborgFound) {
        buttonsHtml += `<br><button onclick="openModal('bygdeborger.pdf', 'Bygdeborger i Telemark (PDF)')" style="margin-top: 15px; width: 100%; padding: 14px; background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(239, 68, 68, 0.1)); border: 1px solid rgba(239, 68, 68, 0.4); color: white; border-radius: 12px; cursor: pointer; font-weight: 700; font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s;">📄 Bygdeborger (PDF)</button>`;
    }
    for (let i = 0; i < finalButtons.length; i++) {
        const b = finalButtons[i];
        buttonsHtml += `<br><button onclick="${b.action}" style="margin-top: 15px; width: 100%; padding: 14px; background: linear-gradient(135deg, rgba(56, 189, 248, 0.15), rgba(56, 189, 248, 0.1)); border: 1px solid rgba(56, 189, 248, 0.4); color: white; border-radius: 12px; cursor: pointer; font-weight: 700; font-family: 'Outfit', sans-serif; display: flex; align-items: center; justify-content: center; gap: 10px; transition: all 0.2s;">🔗 ${b.label}</button>`;
    }

    // 4. Description Cleanup
    desc = desc.replace(/(?<!href="|">)https?:\/\/[^\s<"']+/g, (m) => {
        if (m.toLowerCase().includes('bora.uib.no') || m.toLowerCase().includes('kulturminne') || m.toLowerCase().includes('askeladden')) return '';
        return `<span class="inline-link" onclick="openModal('${m}', '${site.name.replace(/'/g, "\\'")}')" style="color: var(--accent-color); text-decoration: none; border-bottom: 2px solid var(--accent-glow); cursor: pointer; font-weight: 600; padding: 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: inline-block; max-width: 100%; vertical-align: bottom;">${shortenLink(m)}</span>`;
    });
    desc = desc.replace(/og\s*$/gi, '').replace(/<br>\s*og\s*$/gi, '').replace(/\s* og\s*$/gi, '').replace(/<br>\s*$/gi, '').trim();

    desc = desc + buttonsHtml;

    return `<div class="popup-content" style="word-break: break-word;"><style>.inline-link:hover { filter: brightness(1.2); } .image-carousel { overflow: hidden; border-radius: 12px; } .image-carousel img { width: 100%; max-height: 350px; object-fit: contain; border-radius: 12px; display: block; background: #000; transition: filter 0.3s; } .image-carousel:hover img { filter: brightness(1.1); }</style>${html}${imageSection}<div style="margin-top: 18px; font-size: 1.1rem; line-height: 1.6; color: #f1f5f9;">${desc}</div></div>`;
}

function updatePopupImage(event, id, d) { if (event) L.DomEvent.stopPropagation(event); const s = allSites.find(x => x.id === id); if (!s || s.images.length <= 1) return; s.currentImageIndex = (s.currentImageIndex + d + s.images.length) % s.images.length; s.marker.setPopupContent(getPopupHTML(s)); }

const debouncedUpdateGatenavn = debounce(updateGatenavn, 800);

async function updateGatenavn() {
    if (!map.hasLayer(dynamiskeGatenavn) || map.getZoom() < 16) {
        dynamiskeGatenavn.clearLayers();
        return;
    }

    const bounds = map.getBounds();
    const bbox = `${bounds.getWest()},${bounds.getSouth()},${bounds.getEast()},${bounds.getNorth()}`;

    const url = 'https://map.isy.no/services/isy.gis.isyproxy/?instance=grenland&project=gatenavn&url=' +
        encodeURIComponent(`https://grenlandskart.nois.no/webinnsyn/api/wfs?theme1=GatenavnBetydning&service=WFS&request=GetFeature&version=1.0.0&typename=gmgml:GatenavnSkien&srsname=EPSG:4326&bbox=${bbox}`);

    try {
        const response = await fetch(url);
        const text = await response.text();

        const parser = new DOMParser();
        const xmlDoc = parser.parseFromString(text, "text/xml");
        const features = xmlDoc.getElementsByTagName("gmgml:GatenavnSkien");

        dynamiskeGatenavn.clearLayers();
        Array.from(features).forEach(feature => {
            const navn = feature.getElementsByTagName("gmgml:GATENAVN")[0]?.textContent;
            const info = feature.getElementsByTagName("gmgml:BAKGRUNN")[0]?.textContent;
            const coordsNode = feature.getElementsByTagName("gml:coordinates")[0];

            if (navn && coordsNode) {
                const coordsText = coordsNode.textContent.trim();
                const points = coordsText.split(/\s+/).map(p => {
                    const c = p.split(",");
                    return [parseFloat(c[1]), parseFloat(c[0])];
                });
                const polyline = L.polyline(points, { color: 'blue', weight: 4, opacity: 0.2, interactive: false });

                // Find the actual middle point of the coordinates for the label
                const middleIndex = Math.floor(points.length / 2);
                const midpoint = points[middleIndex];

                // Use a marker with a divIcon as the label to ensure 100% click reliability
                const labelMarker = L.marker(midpoint, {
                    icon: L.divIcon({
                        className: 'dynamic-street-label-marker',
                        html: `<div class="street-label-content">${navn}</div>`,
                        iconSize: [200, 30],
                        iconAnchor: [100, 15]
                    }),
                    interactive: true
                });

                const handleCityClick = () => {
                    const modalContent = info ? info : "Ingen historikk tilgjengelig for dette veinavnet.";
                    openModal(null, navn, null, 0, `<div class='popup-content'><h3>${navn}</h3><p>${modalContent}</p></div>`);
                };

                labelMarker.on('click', handleCityClick);

                polyline.addTo(dynamiskeGatenavn);
                labelMarker.addTo(dynamiskeGatenavn);
            }
        });

    } catch (err) {
        console.error("Kunne ikke hente gatenavn for dette utsnittet", err);
    }
}

function renderSidebarList(s) { }
function renderSiteList(sites) {
    const list = document.getElementById('modal-site-list-content'); if (!list) return; list.innerHTML = '';
    const groups = {}; sites.forEach(s => { const n = s.category.name; if (!groups[n]) groups[n] = []; groups[n].push(s); });
    ['GRUVE', 'BYGDEBORG', 'HUSTUFT', 'UTSIKT', 'VANN', 'GRENSESTEIN', 'GRAVHAUG', 'GAPAHUK', 'HULE', 'VEI', 'DIVERSE', 'DEFAULT'].forEach(id => {
        const cat = categoryMap[id]; const sitesInCat = groups[cat.name]; if (!sitesInCat) return;
        const h = document.createElement('div'); h.className = 'section-title'; h.style.marginTop = '24px'; h.style.padding = '10px 15px'; h.style.color = cat.color; h.style.borderLeft = `4px solid ${cat.color}`; h.style.background = 'rgba(0,0,0,0.2)'; h.style.borderRadius = '4px'; h.innerText = cat.name; list.appendChild(h);
        sitesInCat.forEach(s => {
            const card = document.createElement('div'); card.className = 'site-card'; card.style.margin = '8px 0';
            const curIdx = s.currentImageIndex;
            const currentExt = s.images && s.images.length > 0 ? s.images[curIdx] : null;
            const currentLocal = s.localImages && s.localImages.length > 0 ? s.localImages[curIdx] : null;

            let imgUrl = ''; let fallback = '';
            if (currentExt && (currentExt.includes('assets/') || !currentExt.startsWith('http'))) { imgUrl = currentExt; }
            else if (currentLocal) { imgUrl = currentLocal; if (currentExt) fallback = `onerror="this.onerror=null; this.src='image_proxy.php?url=${encodeURIComponent(currentExt)}';"`; }
            else if (currentExt) { imgUrl = `image_proxy.php?url=${encodeURIComponent(currentExt)}`; }

            const imgHtml = imgUrl ? `<img src="${imgUrl}" ${fallback} style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;" loading="lazy">` : `<div style="width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">${s.category.icon}</div>`;
            card.innerHTML = `<div style="display: flex; gap: 12px; align-items: center;">${imgHtml}<div><div class="category" style="color: ${s.category.color}">${s.category.icon} ${s.category.name}</div><h3 style="margin: 0; font-size: 1rem;">${s.name}</h3></div></div>`;
            card.onclick = () => { closeSiteListModal(); focusSite(s.id); }; list.appendChild(card);
        });
    });
}

function openSiteListModal() { renderSiteList(allSites); document.getElementById('site-list-modal').style.display = 'flex'; document.getElementById('modal-search').focus(); }
function closeSiteListModal() { document.getElementById('site-list-modal').style.display = 'none'; }
function filterModalList() { const q = document.getElementById('modal-search').value.toLowerCase(); renderSiteList(allSites.filter(s => s.name.toLowerCase().includes(q) || s.description.toLowerCase().includes(q))); }

// --- Multiselect Helpers ---
window.toggleCatDropdown = function () {
    document.getElementById('cat-multiselect').classList.toggle('open');
};

window.selectAllCats = function (bool) {
    const checkboxes = document.querySelectorAll('#category-checkbox-list input[type="checkbox"]');
    checkboxes.forEach(cb => cb.checked = bool);
    updateMultiselectLabel();
    applyFilters();
};

window.handleCatChange = function (el) {
    const allCb = document.getElementById('cat-ALL');
    const checkboxes = document.querySelectorAll('#category-checkbox-list input[type="checkbox"]:not(#cat-ALL)');

    if (el.id === 'cat-ALL') {
        checkboxes.forEach(cb => cb.checked = el.checked);
    } else {
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        allCb.checked = allChecked;
    }

    updateMultiselectLabel();
    applyFilters();
};

function updateMultiselectLabel() {
    const checkboxes = document.querySelectorAll('#category-checkbox-list input[type="checkbox"]:not(#cat-ALL)');
    const checked = Array.from(checkboxes).filter(cb => cb.checked);
    const label = document.getElementById('multiselect-label');

    if (checked.length === checkboxes.length) {
        label.innerText = "Alle Kategorier";
    } else if (checked.length === 0) {
        label.innerText = "Ingen valgt";
    } else if (checked.length === 1) {
        const catId = checked[0].value;
        label.innerText = categoryMap[catId].name;
    } else {
        label.innerText = `${checked.length} kategorier valgt`;
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    const ms = document.getElementById('cat-multiselect');
    if (ms && !ms.contains(e.target)) {
        ms.classList.remove('open');
    }
});

function applyFilters() {
    const q = document.getElementById('search').value.toLowerCase();
    const checkedBoxes = document.querySelectorAll('#category-checkbox-list input[type="checkbox"]:not(#cat-ALL):checked');
    const selectedCats = Array.from(checkedBoxes).map(cb => cb.value);

    // Oppdater synlighets-flagg på alle sites
    allSites.forEach(s => {
        const matchesSearch = s.name.toLowerCase().includes(q) || s.description.toLowerCase().includes(q);
        const matchesCat = selectedCats.includes(s.catKey);
        s.isVisibleByFilter = matchesSearch && matchesCat;
    });

    // Trigge oppdatering av visning og statistikk
    updateVisibleMarkers();
    summarizeStats();
}

// BINDING FOR applyFilters - Erstattet original funksjon med denne:
/*
function applyFilters() {
    // ... (original kode ble erstattet av logikken over som setter flags og kaller lazy loader)
*/
function applyFiltersOld() { // Beholder navnet for referanse om nødvendig, men koden over overskriver
    const q = document.getElementById('search').value.toLowerCase();
    const checkedBoxes = document.querySelectorAll('#category-checkbox-list input[type="checkbox"]:not(#cat-ALL):checked');
    const selectedCats = Array.from(checkedBoxes).map(cb => cb.value);

    /* 
    Original logikk fjernet til fordel for lazy loading integrasjon.
    Nå setter vi bare s.isVisibleByFilter = true/false (i koden over), 
    og kaller updateVisibleMarkers() som gjør jobben med bounds sjekk.
    */

    // Hvis vi har et aktivt tekstsøk, zoom til resultatene (hvis få)
    const isFiltered = q !== '' || selectedCats.length < Object.keys(categoryMap).length - 1;

    if (isFiltered) {
        // Finn visible markers basert på FILTER (ikke bounds) for å gjøre fitBounds
        const filteredMarkers = allSites.filter(s => s.isVisibleByFilter).map(s => s.marker);
        if (filteredMarkers.length > 0 && filteredMarkers.length < 50) {
            const g = L.featureGroup(filteredMarkers);
            map.fitBounds(g.getBounds(), { padding: [50, 50], maxZoom: 16, animate: true });
        }
    }
}

function parseCoordinates(input) {
    // 1. Rens input: Fjern spesialtegn og bokstaver, men behold tall og desimalpunkter
    let clean = input.toLowerCase()
        .replace(/[°'"]/g, ' ')
        .replace(/nord|øst|east|north|west|south|v|e|n|w|s/g, ' ')
        .replace(/,/g, '.') // Endre komma til punktum for desimalstøtte
        .trim();

    // 2. Splitt opp og filtrer ut alt som ikke inneholder et siffer 
    // (Dette fjerner enslige prikker som oppstår fra ', ')
    let parts = clean.split(/\s+/).filter(p => p.length > 0 && /[0-9]/.test(p));

    if (parts.length < 2) return null;

    // Hent de to siste numeriske verdiene for UTM/Desimal-sjekk
    let pLast1 = parseFloat(parts[parts.length - 2]);
    let pLast2 = parseFloat(parts[parts.length - 1]);

    // PRIORITET 1: UTM-deteksjon (Sjekker om siste verdi er en Northing > 1 000 000)
    if (pLast2 > 1000000) {
        let easting = pLast1;
        let northing = pLast2;
        let zone = 32;
        if (parts.length >= 3) {
            let pZ = parseInt(parts[parts.length - 3]);
            if (pZ >= 31 && pZ <= 37) zone = pZ;
        }

        const a = 6378137.0;
        const f = 1 / 298.257223563;
        const k0 = 0.9996;
        const n = f / (2 - f);
        const A = (a / (1 + n)) * (1 + Math.pow(n, 2) / 4 + Math.pow(n, 4) / 64);
        const xi = northing / (k0 * A);
        const eta = (easting - 500000) / (k0 * A);
        const beta = [n / 2 - (2 / 3) * n ** 2 + (5 / 16) * n ** 3, (13 / 48) * n ** 2 - (3 / 5) * n ** 3, (61 / 240) * n ** 3];
        let xi_p = xi, eta_p = eta;
        for (let j = 0; j < 3; j++) {
            xi_p -= beta[j] * Math.sin(2 * (j + 1) * xi) * Math.cosh(2 * (j + 1) * eta);
            eta_p -= beta[j] * Math.cos(2 * (j + 1) * xi) * Math.sinh(2 * (j + 1) * eta);
        }
        const chi = Math.asin(Math.sin(xi_p) / Math.cosh(eta_p));
        const delta = [2 * n - (2 / 3) * n ** 2, (7 / 3) * n ** 2];
        let lat = chi;
        for (let j = 0; j < 2; j++) lat += delta[j] * Math.sin(2 * (j + 1) * chi);
        let lon = Math.atan(Math.sinh(eta_p) / Math.cos(xi_p));

        return {
            lat: lat * 180 / Math.PI,
            lng: (lon * 180 / Math.PI) + (zone * 6 - 183)
        };
    }

    // PRIORITET 2: GPS-format (4 deler: Grader og desimalminutter)
    if (parts.length >= 4) {
        return {
            lat: parseFloat(parts[0]) + parseFloat(parts[1]) / 60,
            lng: parseFloat(parts[2]) + parseFloat(parts[3]) / 60
        };
    }

    // PRIORITET 3: Desimale grader (2 deler)
    if (parts.length === 2) {
        return { lat: pLast1, lng: pLast2 };
    }

    return null;
}

window.toggleSearchHelp = function () {
    const helpText = `
        <div style="color:white; font-family:'Outfit', sans-serif; font-size:13px; line-height:1.5; padding:15px; max-width:280px; box-sizing:border-box;">
            <b style="color:#38bdf8; font-size:15px; display:block; margin-bottom:8px;">Kartverktøy & Søk</b>
            <div style="margin-bottom:10px; border-bottom:1px solid rgba(255,255,255,0.1); padding-bottom:8px;">
                <b style="color:#38bdf8;">📍 Hent koordinater:</b> Aktiver og klikk i kartet for å få nøyaktig posisjon i søkefeltet.<br>
                <b style="color:#38bdf8;">📏 Måleverktøy:</b> Klikk flere punkter. Viser avstand og areal. Avslutt med dobbeltklikk eller Grønn knapp.<br>
                <b style="color:#38bdf8;">⛰️ Høydeprofil:</b> Tegn rute og dobbeltklikk (eller Grønn knapp) for å se profil (DTM1) og stigning.
            </div>
            <b style="color:#38bdf8;">Søke-eksempler:</b><br>
            • <b>Adresse:</b> "Gulsetvegen 20"<br>
            • <b>Grader:</b> 59.22, 9.53<br>
            • <b>GPS:</b> 59 12.183 9 36.650<br>
            • <b>UTM:</b> 32 540300 6565200<br>
            <div style="margin-top:8px; font-size:11px; opacity:0.8; font-style:italic;">Trykk <b>ESC</b> for å avbryte eller fjerne målinger.</div>
        </div>`;
    L.popup({
        maxWidth: 280,
        className: 'compact-popup',
        autoPan: true
    }).setLatLng(map.getCenter()).setContent(helpText).openOn(map);
};

function formatAsUTM(lat, lng) {
    const a = 6378137.0;
    const f = 1 / 298.257223563;
    const k0 = 0.9996;
    const zone = Math.floor((lng + 180) / 6) + 1;
    const phi = lat * Math.PI / 180;
    const lambda = (lng - (zone * 6 - 183)) * Math.PI / 180;
    const n = f / (2 - f);
    const A = (a / (1 + n)) * (1 + n ** 2 / 4 + n ** 4 / 64);
    const t = Math.sinh(Math.atanh(Math.sin(phi)) - (2 * Math.sqrt(n) / (1 + n)) * Math.atanh((2 * Math.sqrt(n) / (1 + n)) * Math.sin(phi)));
    const xi = Math.atan2(t, Math.cos(lambda));
    const eta = Math.atanh(Math.sin(lambda) / Math.sqrt(1 + t ** 2));
    const alpha = [n / 2 - (2 / 3) * n ** 2 + (5 / 16) * n ** 3, (13 / 48) * n ** 2 - (3 / 5) * n ** 3, (61 / 240) * n ** 3];
    let x = xi, y = eta;
    for (let j = 0; j < 3; j++) {
        x += alpha[j] * Math.sin(2 * (j + 1) * xi) * Math.cosh(2 * (j + 1) * eta);
        y += alpha[j] * Math.cos(2 * (j + 1) * xi) * Math.sinh(2 * (j + 1) * eta);
    }
    return `${zone}V E ${Math.round(k0 * A * y + 500000)} N ${Math.round(k0 * A * x)}`;
}

function formatAsGPS(lat, lng) {
    const format = (val, pos, neg) => {
        const d = Math.floor(Math.abs(val));
        const m = ((Math.abs(val) - d) * 60).toFixed(3);
        return `${d}° ${m}' ${val >= 0 ? pos : neg}`;
    };
    return `${format(lat, 'N', 'S')}, ${format(lng, 'E', 'W')}`;
}

function executeFlyTo(lat, lng, label) {
    if (map) map.flyTo([lat, lng], 18, { duration: 1.5 });
    if (currentSearchMarker) map.removeLayer(currentSearchMarker);

    const popupContent = `
        <div style="font-family:'Outfit',sans-serif; min-width:200px;">
            <b style="color:#38bdf8; font-size:1.1rem;">${label}</b>
            <div style="margin-top:10px; border-top:1px solid rgba(255,255,255,0.1); padding-top:8px; font-size:0.85rem; color:#eee;">
                <div style="margin-bottom:5px;"><b>WGS84:</b> ${lat.toFixed(6)}, ${lng.toFixed(6)}</div>
                <div style="margin-bottom:5px;"><b>UTM:</b> ${formatAsUTM(lat, lng)}</div>
                <div><b>GPS:</b> ${formatAsGPS(lat, lng)}</div>
            </div>
        </div>
    `;

    currentSearchMarker = L.marker([lat, lng]).addTo(map);
    currentSearchMarker.bindPopup(popupContent).openPopup();
}

function calculateArea(latlngs) {
    if (latlngs.length < 3) return 0;
    const radius = 6378137; // Jordens radius i meter
    let area = 0;
    for (let i = 0; i < latlngs.length; i++) {
        const p1 = latlngs[i];
        const p2 = latlngs[(i + 1) % latlngs.length];
        area += (p2.lng - p1.lng) * Math.PI / 180 * (2 + Math.sin(p1.lat * Math.PI / 180) + Math.sin(p2.lat * Math.PI / 180));
    }
    return Math.abs(area * radius * radius / 2.0);
}

function toggleMeasureMode() {
    // Hvis vi ikke måler aktivt, men har rester fra forrige måling (f.eks. etter dblclick)
    if (!isMeasuring && measurePoints.length > 0) {
        clearMeasurement();
        return;
    }

    isMeasuring = !isMeasuring;

    // GA4 Sporing
    if (isMeasuring && typeof gtag === 'function') {
        gtag('event', 'bruk_verktøy', { 'verktøy_navn': 'målebånd' });
    }

    const btn = document.getElementById('measure-btn');
    const mapContainer = document.getElementById('map');

    if (btn) btn.classList.toggle('active', isMeasuring);
    if (mapContainer) mapContainer.classList.toggle('measuring-mode', isMeasuring);

    if (!isMeasuring) {
        clearMeasurement();
        map.off('click', handleMeasureClick);
        map.off('mousemove', handleMeasureMove);
        map.off('dblclick', handleMeasureDblClick);
    } else {
        // Deaktiver picker-mode hvis den er aktiv
        const pickerBtn = document.getElementById('coord-picker-btn');
        if (pickerBtn && pickerBtn.classList.contains('active')) {
            pickerBtn.click();
        }
        map.on('click', handleMeasureClick);
        map.on('mousemove', handleMeasureMove);
        map.on('dblclick', handleMeasureDblClick);
    }
}

function handleMeasureMove(e) {
    if (!isMeasuring || measurePoints.length === 0) return;
    const latlng = e.latlng;

    if (tempLine) {
        tempLine.setLatLngs([measurePoints[measurePoints.length - 1], latlng]);
    } else {
        tempLine = L.polyline([measurePoints[measurePoints.length - 1], latlng], {
            color: '#fbbf24', // Brighter amber color
            weight: 3,
            opacity: 0.8,
            interactive: false
        }).addTo(map);
    }
}

function handleMeasureDblClick(e) {
    if (!isMeasuring) return;
    isMeasuring = false;

    // Fjern lyttere
    map.off('click', handleMeasureClick);
    map.off('mousemove', handleMeasureMove);
    map.off('dblclick', handleMeasureDblClick);

    // Oppdater UI
    const btn = document.getElementById('measure-btn');
    const mapContainer = document.getElementById('map');
    if (btn) btn.classList.remove('active');
    if (mapContainer) mapContainer.classList.remove('measuring-mode');

    // Fjern midlertidig linje
    if (tempLine) {
        map.removeLayer(tempLine);
        tempLine = null;
    }
    updateToolButton();
}

function handleMeasureClick(e) {
    const latlng = e.latlng;
    measurePoints.push(latlng);

    const marker = L.circleMarker(latlng, {
        radius: 5,
        color: '#38bdf8',
        fillColor: '#0b0f19',
        fillOpacity: 1,
        weight: 2,
        interactive: false
    }).addTo(map);
    measureMarkers.push(marker);

    // Fjern areal-info fra forrige markør hvis den eksisterer
    if (measureMarkers.length > 1) {
        const prevMarker = measureMarkers[measureMarkers.length - 2];

        let totalDistToPrev = 0;
        for (let i = 0; i < measurePoints.length - 2; i++) {
            totalDistToPrev += measurePoints[i].distanceTo(measurePoints[i + 1]);
        }

        const formatDist = (d) => d > 1000 ? (d / 1000).toFixed(2) + ' km' : Math.round(d) + ' m';
        const prevSegDist = measurePoints.length > 2 ? measurePoints[measurePoints.length - 3].distanceTo(measurePoints[measurePoints.length - 2]) : 0;

        const prevDistText = `
            <div style="line-height:1.2;">
                ${measurePoints.length > 2 ? `<div style="font-size:10px; color:var(--accent-color); opacity:0.8; margin-bottom:2px;">+ ${formatDist(prevSegDist)}</div>` : ''}
                <div style="font-weight:700;">Totalt: ${formatDist(totalDistToPrev)}</div>
            </div>
        `;
        prevMarker.setTooltipContent(prevDistText);
    }

    if (measurePoints.length > 1) {
        if (measureLine) {
            measureLine.addLatLng(latlng);
        } else {
            measureLine = L.polyline(measurePoints, {
                color: '#38bdf8',
                weight: 3,
                dashArray: '5, 10',
                opacity: 0.8,
                interactive: false
            }).addTo(map);
        }

        if (measurePoints.length >= 3) {
            if (measureArea) {
                measureArea.setLatLngs(measurePoints);
            } else {
                measureArea = L.polygon(measurePoints, {
                    color: '#38bdf8',
                    weight: 0,
                    fillColor: '#38bdf8',
                    fillOpacity: 0.2,
                    interactive: false
                }).addTo(map);
            }
        }

        let totalDist = 0;
        for (let i = 0; i < measurePoints.length - 1; i++) {
            totalDist += measurePoints[i].distanceTo(measurePoints[i + 1]);
        }

        const segDist = measurePoints[measurePoints.length - 2].distanceTo(latlng);
        const area = calculateArea(measurePoints);

        const formatDist = (d) => d > 1000 ? (d / 1000).toFixed(2) + ' km' : Math.round(d) + ' m';
        const formatArea = (a) => a > 10000 ? (a / 1000000).toFixed(3) + ' km²' : Math.round(a) + ' m²';

        const distText = `
            <div style="line-height:1.2;">
                <div style="font-size:10px; color:var(--accent-color); opacity:0.8; margin-bottom:2px;">+ ${formatDist(segDist)}</div>
                <div style="font-weight:700;">Totalt: ${formatDist(totalDist)}</div>
                ${(area > 0 && measurePoints.length >= 3) ? `<div style="font-size:11px; margin-top:3px; color:#fbbf24; font-weight:700;">Areal: ${formatArea(area)}</div>` : ''}
            </div>
        `;

        marker.bindTooltip(distText, {
            permanent: true,
            direction: 'right',
            className: 'measure-tooltip',
            offset: [10, 0]
        }).openTooltip();
    }
    updateToolButton();
}

function clearMeasurement() {
    if (measureLine) map.removeLayer(measureLine);
    if (tempLine) map.removeLayer(tempLine);
    if (measureArea) map.removeLayer(measureArea);
    measureMarkers.forEach(m => map.removeLayer(m));
    measurePoints = [];
    measureMarkers = [];
    measureLine = null;
    tempLine = null;
    measureArea = null;
    updateToolButton();
}

function toggleElevationMode() {
    // Hvis vi allerede har rester fra en måling, rens opp først
    if (!isElevationMode && elevationPoints.length > 0) {
        clearElevation();
        return;
    }

    isElevationMode = !isElevationMode;

    // GA4 Sporing
    if (isElevationMode && typeof gtag === 'function') {
        gtag('event', 'bruk_verktøy', { 'verktøy_navn': 'høydeprofil' });
    }

    const btn = document.getElementById('elevation-btn');
    const mapContainer = document.getElementById('map');

    if (btn) btn.classList.toggle('active', isElevationMode);
    if (mapContainer) mapContainer.classList.toggle('elevation-mode', isElevationMode);

    if (!isElevationMode) {
        clearElevation();
        map.off('click', handleElevationClick);
        map.off('mousemove', handleElevationMove);
        map.off('dblclick', handleElevationDblClick);
    } else {
        // Deaktiver andre moduser
        if (isMeasuring) toggleMeasureMode();
        const pickerBtn = document.getElementById('coord-picker-btn');
        if (pickerBtn && pickerBtn.classList.contains('active')) {
            pickerBtn.click();
        }

        map.on('click', handleElevationClick);
        map.on('mousemove', handleElevationMove);
        map.on('dblclick', handleElevationDblClick);
    }
}

function handleElevationMove(e) {
    if (!isElevationMode || elevationPoints.length === 0) return;
    const latlng = e.latlng;

    if (elevationTempLine) {
        elevationTempLine.setLatLngs([elevationPoints[elevationPoints.length - 1], latlng]);
    } else {
        elevationTempLine = L.polyline([elevationPoints[elevationPoints.length - 1], latlng], {
            color: '#ef4444',
            weight: 3,
            opacity: 0.8,
            dashArray: '5, 10',
            interactive: false
        }).addTo(map);
    }
}

function handleElevationClick(e) {
    const latlng = e.latlng;
    elevationPoints.push(latlng);

    const marker = L.circleMarker(latlng, {
        radius: 4,
        color: '#ef4444',
        fillColor: '#0b0f19',
        fillOpacity: 1,
        weight: 2,
        interactive: false
    }).addTo(map);
    elevationMarkers.push(marker);

    if (elevationPoints.length > 1) {
        if (elevationLine) {
            elevationLine.addLatLng(latlng);
        } else {
            elevationLine = L.polyline(elevationPoints, {
                color: '#ef4444',
                weight: 3,
                opacity: 0.8,
                interactive: false
            }).addTo(map);
        }
    }
    updateToolButton();
}

async function handleElevationDblClick(e) {
    if (!isElevationMode || elevationPoints.length < 2) return;

    // Stop drawing
    isElevationMode = false;
    map.off('click', handleElevationClick);
    map.off('mousemove', handleElevationMove);
    map.off('dblclick', handleElevationDblClick);

    const btn = document.getElementById('elevation-btn');
    const mapContainer = document.getElementById('map');
    if (btn) btn.classList.remove('active');
    if (mapContainer) mapContainer.classList.remove('elevation-mode');

    if (elevationTempLine) {
        map.removeLayer(elevationTempLine);
        elevationTempLine = null;
    }
    updateToolButton();

    // Filter out consecutive duplicate points and limit decimals to 6
    const filteredPoints = [];
    for (let i = 0; i < elevationPoints.length; i++) {
        const current = elevationPoints[i];
        if (i === 0 || current.lat !== elevationPoints[i - 1].lat || current.lng !== elevationPoints[i - 1].lng) {
            filteredPoints.push(current);
        }
    }

    if (filteredPoints.length < 2) {
        alert("Du må velge minst to ulike punkter.");
        clearElevation();
        return;
    }

    // Beregn total distanse for å velge et lurt intervall
    let totalDist = 0;
    for (let i = 0; i < filteredPoints.length - 1; i++) {
        totalDist += map.distance(filteredPoints[i], filteredPoints[i + 1]);
    }

    // Velg intervall basert på distanse (hindrer for mange punkter på lange strekk)
    let interval = 10;
    if (totalDist > 5000) interval = 50;
    else if (totalDist > 1500) interval = 25;

    // Interpoler punkter for å få en EKTE profil
    const interpolated = getInterpolatedPoints(filteredPoints, interval);

    // Begrens antall punkter for å være trygg mot URL-lengde og API-begrensninger (STRENG GRENSE: 50)
    let finalPoints = interpolated;
    const maxPoints = 50;
    if (interpolated.length > maxPoints) {
        const step = (interpolated.length - 1) / (maxPoints - 1);
        finalPoints = [];
        for (let i = 0; i < maxPoints - 1; i++) {
            finalPoints.push(interpolated[Math.floor(i * step)]);
        }
        finalPoints.push(interpolated[interpolated.length - 1]);
    }
    // Dobbeltsjekk for å være 100% sikker
    if (finalPoints.length > 50) finalPoints = finalPoints.slice(0, 50);

    const pointsStr = finalPoints.map(p => `${p.lng.toFixed(5)},${p.lat.toFixed(5)}`).join(';');
    const jsonUrl = `elevation_proxy.php?punkter=${pointsStr}&format=json`;

    try {
        const response = await fetch(jsonUrl);
        const data = await response.json();

        const points = data.punkter || [];
        const heights = points.map(p => p.z).filter(z => z !== null && z !== undefined);

        let totalAscent = 0;
        let totalDescent = 0;
        if (heights.length > 1) {
            for (let i = 0; i < heights.length - 1; i++) {
                const diff = heights[i + 1] - heights[i];
                if (diff > 0) totalAscent += diff;
                else totalDescent += Math.abs(diff);
            }
        }

        // Formater distanse
        const distText = totalDist > 1000 ? (totalDist / 1000).toFixed(2) + " km" : Math.round(totalDist) + " m";

        const chartSvg = generateElevationChartSVG(heights);

        const popupContent = `
            <div class="elevation-popup" style="min-width: 320px; font-family: 'Outfit', sans-serif;">
                <h3 style="margin: 0 0 10px 0; color: #ef4444; font-size: 16px; display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 20px;">⛰️</span> Høydeprofil (DTM1)
                </h3>
                <div style="background: rgba(255,255,255,0.05); padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.1); display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                    <div>
                        <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase;">Stigning</div>
                        <div style="font-size: 15px; font-weight: 700; color: #10b981;">+${Math.round(totalAscent)} m</div>
                    </div>
                    <div>
                        <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase;">Fall</div>
                        <div style="font-size: 15px; font-weight: 700; color: #ef4444;">-${Math.round(totalDescent)} m</div>
                    </div>
                    <div>
                        <div style="font-size: 10px; color: #94a3b8; text-transform: uppercase;">Distanse</div>
                        <div style="font-size: 15px; font-weight: 700; color: #3b82f6;">${distText}</div>
                    </div>
                </div>
                <div id="elevation-chart-container" style="width: 100%; border-radius: 8px; background: white; padding: 10px; box-sizing: border-box; box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);">
                    ${chartSvg}
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button onclick="downloadElevationSVG()" class="elevation-dl-btn" style="flex-grow: 1; text-align: center; border: none; padding: 10px; font-weight: 600;">💾 Last ned bilde</button>
                    <button onclick="clearElevation(); map.closePopup();" style="flex-grow: 1; padding: 10px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: white; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; transition: all 0.2s;">Lukk</button>
                </div>
            </div>
        `;

        L.popup({ maxWidth: 850, className: 'custom-elevation-popup' })
            .setLatLng(elevationPoints[elevationPoints.length - 1])
            .setContent(popupContent)
            .openOn(map);

    } catch (err) {
        console.error("Elevation API failed:", err);
        alert("Kunne ikke hente høydeprofil. Prøv igjen.");
        clearElevation();
    }
}

// Hjelper for å generere SVG graf lokalt
function generateElevationChartSVG(heights) {
    if (!heights || heights.length < 2) return '<div style="height:150px; display:flex; align-items:center; justify-content:center; color:#666;">Ingen data</div>';

    const w = 400;
    const h = 150;
    const padding = 30;

    const minH = Math.min(...heights);
    const maxH = Math.max(...heights);
    const range = (maxH - minH) || 10;

    // Legg til litt buffer topp/bunn
    const drawMin = Math.max(0, minH - (range * 0.1));
    const drawMax = maxH + (range * 0.1);
    const drawRange = drawMax - drawMin;

    let points = "";
    heights.forEach((val, i) => {
        const x = padding + (i * (w - 2 * padding) / (heights.length - 1));
        const y = h - padding - ((val - drawMin) * (h - 2 * padding) / drawRange);
        points += `${x},${y} `;
    });

    // Lag fill-område
    const firstX = padding;
    const lastX = w - padding;
    const baseline = h - padding;
    const fillPoints = `${firstX},${baseline} ${points} ${lastX},${baseline}`;

    return `
        <svg viewBox="0 0 ${w} ${h}" xmlns="http://www.w3.org/2000/svg" id="elevation-svg" style="display:block; width: 100%; height: auto;">
            <defs>
                <linearGradient id="grad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#ef4444;stop-opacity:0.3" />
                    <stop offset="100%" style="stop-color:#ef4444;stop-opacity:0.0" />
                </linearGradient>
            </defs>
            <!-- Grid lines -->
            <line x1="${padding}" y1="${padding}" x2="${padding}" y2="${h - padding}" stroke="#e2e8f0" stroke-width="1" />
            <line x1="${padding}" y1="${h - padding}" x2="${w - padding}" y2="${h - padding}" stroke="#e2e8f0" stroke-width="1" />
            
            <!-- Axis labels -->
            <text x="${padding - 5}" y="${padding}" text-anchor="end" font-size="10" fill="#64748b" alignment-baseline="middle">${Math.round(maxH)}m</text>
            <text x="${padding - 5}" y="${h - padding}" text-anchor="end" font-size="10" fill="#64748b" alignment-baseline="middle">${Math.round(minH)}m</text>
            
            <polyline points="${fillPoints}" fill="url(#grad)" />
            <polyline points="${points}" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linejoin="round" />
        </svg>
    `;
}

// Funksjon for å laste ned SVG som bilde (bruker canvas-triks)
function downloadElevationSVG() {
    const svg = document.getElementById('elevation-svg');
    if (!svg) return;

    const svgData = new XMLSerializer().serializeToString(svg);
    const canvas = document.createElement("canvas");
    const svgSize = svg.getBoundingClientRect();
    canvas.width = 800; // Høyere oppløsning for nedlasting
    canvas.height = 300;

    const ctx = canvas.getContext("2d");
    const img = new Image();
    const svgBlob = new Blob([svgData], { type: "image/svg+xml;charset=utf-8" });
    const url = URL.createObjectURL(svgBlob);

    img.onload = function () {
        ctx.fillStyle = "white";
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);

        const pngUrl = canvas.toDataURL("image/png");
        const downloadLink = document.createElement("a");
        downloadLink.href = pngUrl;
        downloadLink.download = "hoydeprofil.png";
        document.body.appendChild(downloadLink);
        downloadLink.click();
        document.body.removeChild(downloadLink);
    };
    img.src = url;
}

// Hjelper for å interpolere punkter langs en polyline for å få en ekte profil
function getInterpolatedPoints(points, intervalMeters = 20) {
    const interpolated = [];
    if (points.length < 2) return points;

    for (let i = 0; i < points.length - 1; i++) {
        const p1 = points[i];
        const p2 = points[i + 1];
        const dist = map.distance(p1, p2);

        // Legg til startpunktet for segmentet
        interpolated.push(p1);

        if (dist > intervalMeters) {
            const numSegments = Math.floor(dist / intervalMeters);
            for (let j = 1; j <= numSegments; j++) {
                const fraction = j / (numSegments + 1);
                const interpolatedLat = p1.lat + (p2.lat - p1.lat) * fraction;
                const interpolatedLng = p1.lng + (p2.lng - p1.lng) * fraction;
                interpolated.push(L.latLng(interpolatedLat, interpolatedLng));
            }
        }
    }
    // Legg til det aller siste punktet
    interpolated.push(points[points.length - 1]);

    // Fjern duplikater som kan oppstå i skjøtene
    return interpolated.filter((p, i, self) =>
        i === 0 || p.lat !== self[i - 1].lat || p.lng !== self[i - 1].lng
    );
}

function clearElevation() {
    if (elevationLine) map.removeLayer(elevationLine);
    if (elevationTempLine) map.removeLayer(elevationTempLine);
    elevationMarkers.forEach(m => map.removeLayer(m));
    elevationPoints = [];
    elevationMarkers = [];
    elevationLine = null;
    elevationTempLine = null;
    isElevationMode = false;
    const btn = document.getElementById('elevation-btn');
    if (btn) btn.classList.remove('active');
    document.getElementById('map').classList.remove('elevation-mode');
    updateToolButton();
}

document.addEventListener('DOMContentLoaded', () => {
    initMap();

    // Ensure sidebar state and button visibility
    const sidebar = document.getElementById('sidebar');
    const btn = document.getElementById('mobile-toggle');
    const isMobile = window.innerWidth < 1024;

    if (isMobile && sidebar) {
        sidebar.classList.add('collapsed');
        if (btn) {
            btn.style.display = 'flex';
            btn.innerHTML = '☰';
        }
    } else if (sidebar) {
        sidebar.classList.remove('collapsed');
        if (btn) btn.style.display = 'none';
    }

    const search = document.getElementById('search'); if (search) search.addEventListener('input', debounce(applyFilters, 250));

    // Koble knappen til eksisterende logikk
    const finishBtn = document.getElementById('finish-tool-btn');
    if (finishBtn) {
        finishBtn.addEventListener('click', () => {
            if (isMeasuring) handleMeasureDblClick();
            if (isElevationMode) handleElevationDblClick();
        });
    }
    const filter = document.getElementById('category-filter'); if (filter) filter.addEventListener('change', applyFilters);
    // Debounce search to prevent lag
    const searchInput = document.getElementById('search');
    if (searchInput) {
        const debouncedFilter = debounce(applyFilters, 300);
        searchInput.addEventListener('input', debouncedFilter);
    }

    const coordSearchContainer = document.getElementById('coord-search-container');
    const coordInput = document.getElementById('coord-search-input');
    const coordBtn = document.getElementById('coord-search-btn');
    const pickerBtn = document.getElementById('coord-picker-btn');

    if (coordInput && coordBtn) {
        let isPickingMode = false;
        L.DomEvent.disableClickPropagation(coordSearchContainer); L.DomEvent.disableScrollPropagation(coordSearchContainer);

        // --- SMART SØKEFUNKSJON UTEN GEOGRAFISK BEGRENSNING ---
        async function handleSearch() {
            const input = document.getElementById('coord-search-input');
            const val = input.value.trim();
            if (!val) return;

            // Fjern gamle resultater
            const oldList = document.getElementById('search-results-list');
            if (oldList) oldList.remove();
            if (currentSearchMarker) map.removeLayer(currentSearchMarker);

            // PRIORITET 1: Sjekk om input er koordinater
            const coords = parseCoordinates(val);
            if (coords) {
                executeFlyTo(coords.lat, coords.lng, "Valgte koordinater");
                return;
            }

            // PRIORITET 2: Hvis ikke koordinater, gj\u00f8r vanlig adresses\u00f8k
            try {
                const response = await fetch(`https://ws.geonorge.no/adresser/v1/sok?sok=${encodeURIComponent(val)}&fuzzy=true&treffPerSide=15`);
                const data = await response.json();

                if (data.adresser && data.adresser.length > 0) {
                    // Prioriter Skien og Porsgrunn \u00f8verst
                    data.adresser.sort((a, b) => {
                        const aLocal = (a.kommunenavn === 'SKIEN' || a.kommunenavn === 'PORSGRUNN') ? 1 : 0;
                        const bLocal = (b.kommunenavn === 'SKIEN' || b.kommunenavn === 'PORSGRUNN') ? 1 : 0;
                        return bLocal - aLocal;
                    });

                    if (data.adresser.length > 1) {
                        const list = document.createElement('div');
                        list.id = 'search-results-list';
                        data.adresser.forEach(adr => {
                            const item = document.createElement('div');
                            item.className = 'search-result-item';
                            item.innerHTML = `<div style="color:white; font-weight:600;">${adr.adressetekst}</div><div style="font-size:0.75rem; color:#94a3b8;">${adr.kommunenavn}</div>`;
                            item.onclick = () => {
                                executeFlyTo(adr.representasjonspunkt.lat, adr.representasjonspunkt.lon, adr.adressetekst);
                                list.remove();
                            };
                            list.appendChild(item);
                        });
                        document.getElementById('coord-search-container').appendChild(list);
                    } else {
                        const best = data.adresser[0];
                        executeFlyTo(best.representasjonspunkt.lat, best.representasjonspunkt.lon, best.adressetekst);
                    }
                } else {
                    alert("Ingen treff på adresse eller koordinater.");
                }
            } catch (e) {
                console.error("Søk feilet:", e);
            }
        }


        coordBtn.addEventListener('click', handleSearch);
        coordInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleSearch(); });

        const trackBtnControl = L.control({ position: 'bottomright' });
        trackBtnControl.onAdd = function () {
            const div = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-locate');
            div.innerHTML = '<button id="track-btn" style="background:#fff; border:none; width:30px; height:30px; cursor:pointer; font-size:18px;">📍</button>';
            div.onclick = function (e) {
                L.DomEvent.stopPropagation(e); isTracking = !isTracking;
                const btn = document.getElementById('track-btn');
                if (isTracking) {
                    btn.innerHTML = '🛰️';
                    btn.style.background = '#38bdf8';
                    // setView: false hindrer at kartet zoomer ut
                    map.locate({ watch: true, setView: false, enableHighAccuracy: true });
                } else {
                    btn.innerHTML = '📍';
                    btn.style.background = '#fff';
                    map.stopLocate();
                    if (userMarker) map.removeLayer(userMarker);
                    if (userCircle) map.removeLayer(userCircle);
                    userMarker = userCircle = null;
                }
            };
            return div;
        };
        trackBtnControl.addTo(map);

        if (pickerBtn) {
            pickerBtn.addEventListener('click', () => {
                isPickingMode = !isPickingMode;
                pickerBtn.classList.toggle('active', isPickingMode); document.getElementById('map').classList.toggle('picking-mode', isPickingMode);
                if (isPickingMode) {
                    // Deaktiver måling hvis aktiv
                    if (isMeasuring) toggleMeasureMode();

                    map.once('click', (e) => { const { lat, lng } = e.latlng; coordInput.value = `${lat.toFixed(6)}, ${lng.toFixed(6)}`; executeFlyTo(lat, lng, "Valgt posisjon"); isPickingMode = false; pickerBtn.classList.remove('active'); document.getElementById('map').classList.remove('picking-mode'); });
                }
                else { /* No-op, map.once handles itself */ }
            });
        }

        const measureBtn = document.getElementById('measure-btn');
        if (measureBtn) {
            measureBtn.addEventListener('click', toggleMeasureMode);
        }

        const elevationBtn = document.getElementById('elevation-btn');
        if (elevationBtn) {
            elevationBtn.addEventListener('click', toggleElevationMode);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (isMeasuring || measurePoints.length > 0 || isElevationMode || elevationPoints.length > 0) {
                    if (isMeasuring) toggleMeasureMode();
                    else if (isElevationMode) toggleElevationMode();
                    else {
                        clearMeasurement();
                        clearElevation();
                    }
                }
            }
        });
    }

    // initMap() moved to start of DOMContentLoaded

    // Lukk resultatlisten hvis man klikker utenfor
    document.addEventListener('click', (e) => {
        const list = document.getElementById('search-results-list');
        if (list && !document.getElementById('coord-search-container').contains(e.target)) {
            list.remove();
        }
    });

    // Sidebar initialization handled at top of file
    // loadImageRegistry(); // Moved to lazy load when album is opened
});

if (typeof L !== 'undefined' && L.Layer) {
    L.Layer.prototype.fadeOut = function (duration) {
        const self = this; let opacity = 1; const interval = 50; const step = interval / duration;
        const timer = setInterval(() => { opacity -= step; if (opacity <= 0) { self.remove(); clearInterval(timer); } else { if (self.setStyle) self.setStyle({ opacity: opacity, fillOpacity: opacity * 0.5 }); else if (self.setOpacity) self.setOpacity(opacity); } }, interval);
    };
}

// --- PWA INSTALLATION LOGIC ---
let deferredPrompt;
const installBtn = document.getElementById('install-app-btn');

// Platform Detection
const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) && !window.MSStream;
const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
const isSafari = /^((?!chrome|android).)*safari/i.test(navigator.userAgent);
const isStandalone = window.navigator.standalone || (window.matchMedia('(display-mode: standalone)').matches);

// Smart Install Prompt Logic
function shouldShowInstallPrompt() {
    const now = new Date().getTime();
    const firstSeen = localStorage.getItem('pwa_first_seen');
    const cooldownStart = localStorage.getItem('pwa_cooldown_start');

    // 1. First Visit ever
    if (!firstSeen) {
        localStorage.setItem('pwa_first_seen', now);
        return true;
    }

    // 2. In Cooldown Mode (after 1 week of showing)
    if (cooldownStart) {
        const oneMonth = 30 * 24 * 60 * 60 * 1000;
        if (now - parseInt(cooldownStart) > oneMonth) {
            // Cooldown over! Reset everything to start a new "week"
            localStorage.removeItem('pwa_cooldown_start');
            localStorage.setItem('pwa_first_seen', now);
            return true;
        }
        return false; // Still in cooldown
    }

    // 3. In Active Week Mode
    const oneWeek = 7 * 24 * 60 * 60 * 1000;
    if (now - parseInt(firstSeen) > oneWeek) {
        // Week is over, start 1 month cooldown
        localStorage.setItem('pwa_cooldown_start', now);
        return false;
    }

    return true; // Still in active week
}

// Show button if:
// 1. Android/PC Chrome (captured via beforeinstallprompt)
// 2. iOS Safari (Manual)
// 3. Mac Safari (Manual)
// AND: Not standalone AND Smart Logic says OK
if (!isStandalone && shouldShowInstallPrompt()) {
    if (isIos || (isMac && isSafari)) {
        if (installBtn) {
            installBtn.style.display = 'flex';
            const txt = installBtn.querySelector('#install-btn-text');
            if (txt) {
                if (isIos) txt.innerHTML = '📲 Installer på iPhone';
                if (isMac) txt.innerHTML = '📲 Installer på Mac';
            }
        }
    }
}

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    if (installBtn && shouldShowInstallPrompt()) {
        installBtn.style.display = 'flex';
        const txt = installBtn.querySelector('#install-btn-text');
        if (txt) txt.innerHTML = '📲 Installer App';
    }
});

if (installBtn) {
    installBtn.addEventListener('click', async () => {
        // SCENARIO 1: Android / PC Chrome (Native Trigger)
        if (deferredPrompt) {
            deferredPrompt.prompt();
            const { outcome } = await deferredPrompt.userChoice;
            console.log(`User response to the install prompt: ${outcome}`);
            deferredPrompt = null;
            installBtn.style.display = 'none';
        }
        // SCENARIO 2: iOS / Mac Safari (Manual Instructions)
        else {
            const modal = document.getElementById('install-instructions-modal');
            const actionText = document.getElementById('install-action-text');

            if (modal) {
                // Customize text for Mac
                if (isMac && !isIos) {
                    if (actionText) actionText.innerText = '"Legg til i Dock"';
                }
                modal.style.display = 'flex';
            }
        }
    });
}

window.addEventListener('appinstalled', () => {
    if (installBtn) installBtn.style.display = 'none';
    deferredPrompt = null;
    console.log('PWA was installed');
});

/**
 * Håndterer det skjulte georef-verktøyet via koden 5877
 */
function handleSecretGeoref() {
    // Sjekk om vi har en gyldig kode lagret (7 dager)
    const savedPin = localStorage.getItem('vox_georef_pin');
    const expiry = localStorage.getItem('vox_georef_expiry');

    if (savedPin === "5877" && expiry && Date.now() < parseInt(expiry)) {
        window.location.href = "georef.php?v=" + Date.now();
        return;
    }

    const code = prompt("Tast inn tilgangskode for Georef-verktøyet:");
    if (code === "5877") {
        // Lagre tilgang i 7 dager
        localStorage.setItem('vox_georef_pin', '5877');
        localStorage.setItem('vox_georef_expiry', Date.now() + 7 * 24 * 60 * 60 * 1000);
        window.location.href = "georef.php";
    } else if (code !== null) {
        alert("Feil kode. Tilgang nektet.");
    }
}

