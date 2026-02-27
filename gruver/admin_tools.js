// Admin Tools - Standalone Module
// Loaded after viewer.js

let adminSelectedMarker = null;
let adminMoveLine = null;
// We'll track undo stack size locally to enable/disable button
let undoCount = 0;

// Initialize Admin Tools
function initAdminTools() {
    console.log("Admin Tools Initialized");
    if (typeof map === 'undefined') {
        console.error("Map not found! Admin tools cannot start.");
        return;
    }

    // Inject Admin Toolbar
    injectAdminToolbar();

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            deselectMarker();
        }
        if ((e.ctrlKey || e.metaKey) && e.key === 'z') {
            e.preventDefault();
            undoLastMove();
        }
    });

    // Iterate all sites and attach click listeners
    if (typeof allSites !== 'undefined' && Array.isArray(allSites)) {
        allSites.forEach(site => {
            if (site.marker) {
                // Attach data to marker for easy access in handler
                site.marker.siteData = site;

                // Remove old admin handlers (MUST be exact reference)
                site.marker.off('click', handleAdminMarkerClick);
                // Add new handler (direct reference)
                site.marker.on('click', handleAdminMarkerClick);
            }
        });

        // Initial feedback
        showAdminStatus("Admin-modus: KLAR. Klikk et punkt for å flytte.", "ok");
    } else {
        console.error("DEBUG: allSites er ikke funnet eller er tom.", typeof allSites);
        showAdminStatus("Feil: Kunne ikke hente datagrunnlaget (allSites mangler).", "error");
    }

    // Add map click listener for moving
    map.off('click', handleAdminMapClick);
    map.on('click', handleAdminMapClick);
}

function injectAdminToolbar() {
    const existing = document.getElementById('admin-toolbar');
    if (existing) existing.remove();

    const toolbar = document.createElement('div');
    toolbar.id = 'admin-toolbar';
    toolbar.style.cssText = `
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: #111827; color: white; padding: 10px 20px; border-radius: 50px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1500;
        display: flex; gap: 15px; align-items: center; border: 1px solid #374151;
        font-family: 'Outfit', sans-serif; font-size: 0.9rem;
    `;

    toolbar.innerHTML = `
        <div id="admin-status" style="font-weight: 600; color: #10b981;">Klar</div>
        <div style="width: 1px; height: 20px; background: #374151;"></div>
        <button onclick="undoLastMove()" id="btn-undo" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">
            <span style="font-size:1.2em;">↩</span> Angre
        </button>
        <button onclick="showChangelog()" style="background:none; border:none; color:#38bdf8; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">
            <span style="font-size:1.2em;">📜</span> Logg
        </button>
        <button onclick="exitAdminMode()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-weight:bold;">
            Avslutt
        </button>
    `;
    document.body.appendChild(toolbar);
}

// ... existing code ...

function showChangelog() {
    // Create Modal if not exists
    let modal = document.getElementById('changelog-modal');
    if (modal) modal.remove();

    modal = document.createElement('div');
    modal.id = 'changelog-modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8); z-index: 10001; display: flex;
        align-items: center; justify-content: center; backdrop-filter: blur(5px);
    `;

    modal.innerHTML = `
        <div style="background: #111827; width: 90%; max-width: 600px; max-height: 80vh; border-radius: 12px; border: 1px solid #374151; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            <div style="padding: 15px 20px; border-bottom: 1px solid #374151; display: flex; justify-content: space-between; align-items: center; background: #1f2937;">
                <h3 style="margin: 0; color: white;">Endringslogg</h3>
                <button onclick="document.getElementById('changelog-modal').remove()" style="background:none; border:none; color: #94a3b8; font-size: 1.5em; cursor: pointer;">&times;</button>
            </div>
            <div id="changelog-list" style="padding: 0; overflow-y: auto; flex-grow: 1;">
                <div style="padding: 20px; text-align: center; color: #94a3b8;">Laster historikk...</div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    fetch('get_changelog.php')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('changelog-list');
            list.innerHTML = '';

            if (!data.log || data.log.length === 0) {
                list.innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;">Ingen endringer funnet i loggen.</div>';
                return;
            }

            // Entries are already sorted by timestamp (newest first) from PHP
            const items = data.log;

            items.forEach(entry => {
                const date = new Date(entry.timestamp * 1000).toLocaleString('no-NO');
                const row = document.createElement('div');
                row.style.cssText = `
                    padding: 15px 20px; border-bottom: 1px solid #374151; display: flex; 
                    justify-content: space-between; align-items: center; gap: 10px;
                `;

                let content = '';
                let undoAction = '';

                if (entry.type === 'override') {
                    let actionText = '';
                    let actionColor = '#94a3b8';
                    if (entry.action === 'hide') {
                        actionText = 'SKJULT';
                        actionColor = '#f59e0b'; // Amber
                    } else if (entry.action === 'delete') {
                        actionText = 'SLETTET';
                        actionColor = '#ef4444'; // Red
                    } else if (entry.action === 'add_image') {
                        actionText = 'BILDE';
                        actionColor = '#10b981'; // Emerald
                    } else if (entry.action === 'rename') {
                        actionText = 'NAVN';
                        actionColor = '#38bdf8'; // Blue
                    }

                    content = `
                        <div style="font-size: 0.9em;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <span style="background: ${actionColor}; color: white; font-size: 0.7em; padding: 2px 6px; border-radius: 4px; font-weight: bold;">${actionText}</span>
                                <span style="font-weight: bold; color: #f9fafb;">${entry.name}</span>
                            </div>
                            <div style="color: #94a3b8; font-size: 0.85em;">${date}</div>
                        </div>
                    `;
                    undoAction = `undoOverride('${entry.name.replace(/'/g, "\\'")}', ${entry.timestamp})`;
                } else {
                    content = `
                        <div style="font-size: 0.9em;">
                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                <span style="background: #38bdf8; color: white; font-size: 0.7em; padding: 2px 6px; border-radius: 4px; font-weight: bold;">FLYTTET</span>
                                <span style="font-weight: bold; color: #f9fafb;">${entry.name}</span>
                            </div>
                            <div style="color: #94a3b8; font-size: 0.85em;">${date}</div>
                            <div style="color: #64748b; font-size: 0.8em; margin-top: 2px;">Ny posisjon: ${entry.newLat.toFixed(5)}, ${entry.newLng.toFixed(5)}</div>
                        </div>
                    `;
                    // Coordinate undos are tricky because they rely on array index in changelog.json
                    // But our get_changelog now merges and sorts.
                    // We need a way to undo coords by timestamp/name too if we want to be safe.
                    // For now, I'll stick to coordinate undo as it was if possible, but it's risky.
                    // UPDATE: I'll simplify and only allow undoing the VERY LATEST coord move globally,
                    // OR I'll update undo_coord.php to match by timestamp/name too.
                    undoAction = `undoCoordinate('${entry.name.replace(/'/g, "\\'")}', ${entry.timestamp})`;
                }

                row.innerHTML = `
                    ${content}
                    <button onclick="${undoAction}" style="
                        background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid #ef4444; 
                        padding: 6px 12px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.85em;
                        white-space: nowrap;
                    ">
                        ↩ Angre
                    </button>
                `;
                list.appendChild(row);
            });
        })
        .catch(e => {
            document.getElementById('changelog-list').innerHTML = '<div style="padding: 20px; text-align: center; color: #ef4444;">Feil ved lasting av logg.</div>';
        });
}

function undoOverride(name, timestamp) {
    if (!confirm(`Vil du fjerne overstyringen for "${name}"?\n(Dette vil f.eks. gjøre et skjult punkt synlig igjen)`)) return;

    fetch('undo_override.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, timestamp })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload(); // Reload to apply sync changes
            } else {
                alert("Feil: " + data.message);
            }
        })
        .catch(e => alert("Nettverksfeil."));
}

function undoCoordinate(name, timestamp) {
    // We need to update undo_coord.php to support name/timestamp matching
    // For now, let's just warn or implement the backend too.
    if (!confirm(`Vil du flytte "${name}" tilbake?`)) return;

    fetch('undo_coord.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, timestamp })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message || "Suksess!");
                location.reload();
            } else {
                alert("Feil: " + data.message);
            }
        })
        .catch(e => alert("Nettverksfeil."));
}

function undoSpecific(index) {
    if (!confirm('Er du sikker på at du vil omgjøre denne endringen?')) return;

    // Close modal optionally, or keep it open to show update? 
    // Let's keep it open but show loading.

    fetch('undo_coord.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ index: index })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(`Suksess! "${data.name}" er flyttet tilbake.`);

                // Refresh Log
                showChangelog();

                // Update Map
                const targetSite = allSites.find(s => s.name === data.name);
                if (targetSite && targetSite.marker) {
                    const newLatLng = new L.LatLng(data.lat, data.lng);
                    targetSite.marker.setLatLng(newLatLng);
                    targetSite.lat = data.lat;
                    targetSite.lng = data.lng;

                    // Highlight it briefly
                    map.panTo(newLatLng);
                    const el = targetSite.marker.getElement();
                    if (el) {
                        el.style.border = "3px solid #10b981";
                        setTimeout(() => el.style.border = "", 2000);
                    }
                }
            } else {
                alert("Feil: " + data.message);
            }
        })
        .catch(e => {
            alert("Nettverksfeil.");
        });
}
function showAdminStatus(msg, type = "normal") {
    const el = document.getElementById('admin-status');
    if (!el) return;
    el.innerText = msg;
    if (type === "error") el.style.color = "#ef4444";
    else if (type === "ok") el.style.color = "#10b981";
    else if (type === "warn") el.style.color = "#f59e0b";
    else el.style.color = "white";
}

function handleAdminMarkerClick(e) {
    if (!isAdminMode) return;

    // Retrieve site data from marker
    const site = e.target.siteData;
    if (!site) return;

    // If clicking the ALREADY selected marker, deselect it
    if (adminSelectedMarker && adminSelectedMarker === site.marker) {
        deselectMarker();
        return;
    }

    // Deselect previous if any
    if (adminSelectedMarker) {
        deselectMarker(); // Use the function to clean up line etc
    }

    adminSelectedMarker = site.marker;
    adminSelectedMarker.siteData = site; // Ensure link

    // Visual feedback
    const el = adminSelectedMarker.getElement();
    if (el) {
        L.DomUtil.addClass(el, 'admin-selected-marker');
        el.style.border = "3px solid #f59e0b";
        el.style.borderRadius = "50%";
        el.style.boxShadow = "0 0 15px #f59e0b";
        el.style.zIndex = "10000";
    }

    // adminSelectedMarker.closePopup(); // REMOVED: User wants to see info before moving

    // START VISUAL GUIDE
    map.on('mousemove', updateAdminLine);
    document.getElementById('map').style.cursor = 'crosshair';

    showAdminStatus(`Valgt: ${site.name}. Klikk på kartet for å flytte.`, "warn");

    L.DomEvent.stopPropagation(e);
}

function deselectMarker() {
    if (adminSelectedMarker) {
        const el = adminSelectedMarker.getElement();
        if (el) {
            el.style.border = "";
            el.style.boxShadow = "";
            el.style.zIndex = "";
        }
        adminSelectedMarker = null;

        // Remove line
        if (adminMoveLine) {
            adminMoveLine.remove();
            adminMoveLine = null;
        }
        // Remove map listener
        map.off('mousemove', updateAdminLine);

        // Reset cursor
        document.getElementById('map').style.cursor = '';

        showAdminStatus("Valg opphevet.", "normal");
    }
}

function updateAdminLine(e) {
    if (!adminSelectedMarker) return;

    const startLatLng = adminSelectedMarker.getLatLng();
    const endLatLng = e.latlng;

    if (!adminMoveLine) {
        adminMoveLine = L.polyline([startLatLng, endLatLng], {
            color: '#f59e0b',
            weight: 3,
            dashArray: '10, 10',
            opacity: 0.8
        }).addTo(map);
    } else {
        adminMoveLine.setLatLngs([startLatLng, endLatLng]);
    }
}

function handleAdminMapClick(e) {
    if (!isAdminMode || !adminSelectedMarker) return;

    const newLatLng = e.latlng;
    const site = adminSelectedMarker.siteData;

    // Confirm dialog
    const confirmMove = confirm(`Vil du flytte "${site.name}" til denne posisjonen?\n\nLat: ${newLatLng.lat.toFixed(5)}\nLng: ${newLatLng.lng.toFixed(5)}`);

    if (confirmMove) {
        // Update Frontend
        adminSelectedMarker.setLatLng(newLatLng);

        // Remove visual selection
        const el = adminSelectedMarker.getElement();
        if (el) {
            el.style.border = "";
            el.style.boxShadow = "";
            el.style.zIndex = ""; // Reset z-index
        }

        // Capture Original Coordinates (for backend identification)
        const originalLat = site.lat;
        const originalLng = site.lng;

        // Update Data Object
        site.lat = newLatLng.lat;
        site.lng = newLatLng.lng;

        // Save to Backend
        saveCoordinate(site.name, newLatLng.lat, newLatLng.lng, originalLat, originalLng);

        // Reset selection
        adminSelectedMarker = null;
    }
}

function saveCoordinate(name, lat, lng, originalLat, originalLng) {
    showAdminStatus("Lagrer...", "warn");
    fetch('update_coord.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            name: name,
            lat: lat,
            lng: lng,
            originalLat: originalLat,
            originalLng: originalLng
        })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAdminStatus("Lagret OK!", "ok");
                setTimeout(() => showAdminStatus("Klar"), 2000);
                undoCount++;
            } else {
                showAdminStatus("Feil: " + data.message, "error");
                alert("Feil ved lagring: " + data.message);
            }
        })
        .catch(err => {
            console.error("Save error", err);
            showAdminStatus("Nettverksfeil", "error");
        });
}

function undoLastMove() {
    showAdminStatus("Angrer siste endring...", "warn");
    fetch('undo_coord.php')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAdminStatus("Angret: " + data.name, "ok");

                // Update map marker
                // We have to search allSites to find the correct marker object
                const targetSite = allSites.find(s => s.name === data.name);
                if (targetSite && targetSite.marker) {
                    const newLatLng = new L.LatLng(data.lat, data.lng);
                    targetSite.marker.setLatLng(newLatLng);
                    // Update local data
                    targetSite.lat = data.lat;
                    targetSite.lng = data.lng;

                    // Pan to it to show user what happened
                    map.panTo(newLatLng);
                }
            } else {
                showAdminStatus(data.message || "Ingen endringer å angre", "normal");
            }
        })
        .catch(err => {
            console.error("Undo error", err);
            showAdminStatus("Feil ved angring", "error");
        });
}

function exitAdminMode() {
    isAdminMode = false;
    deselectMarker();
    const toolbar = document.getElementById('admin-toolbar');
    if (toolbar) toolbar.remove();
    alert("Admin-modus avsluttet.");
}

// --- ADMIN SECRET HANDLERS ---

function handleAdminSecret() {
    const modal = document.getElementById('admin-pin-modal');
    if (modal) {
        modal.style.display = 'flex';
        const input = document.getElementById('admin-pin-input-field');
        if (input) {
            input.value = '';
            setTimeout(() => input.focus(), 100);
            input.onkeydown = function (e) { if (e.key === 'Enter') submitAdminPin(); };
        }
    } else {
        const pin = prompt("Skriv inn administrasjons-PIN:");
        if (!pin) return;
        verifyAdminPin(pin);
    }
}

function closeAdminPinModal() {
    const modal = document.getElementById('admin-pin-modal');
    if (modal) modal.style.display = 'none';
}

function submitAdminPin() {
    const input = document.getElementById('admin-pin-input-field');
    const pin = input ? input.value : '';
    verifyAdminPin(pin);
}

function verifyAdminPin(pin) {
    if (!pin) return;

    fetch('admin_check.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ pin: pin })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.isAdminMode = true; // VIKTIG: Oppdater klient-state
                closeAdminPinModal();
                initAdminTools();
            } else {
                alert(data.message || "Feil kode.");
                const input = document.getElementById('admin-pin-input-field');
                if (input) {
                    input.value = '';
                    input.focus();
                }
            }
        })
        .catch(e => {
            console.error(e);
            alert("Feil ved tilkobling.");
        });
}

function handleSecretGeoref() {
    window.location.href = "georef.php";
}

// --- NEW OVERRIDE FUNCTIONS ---

function hideSite(name, lat, lng) {
    if (!confirm(`Vil du skjule "${name}" fra det offentlige kartet?\n(Den forblir i databasen, men vises ikke for brukere)`)) return;
    saveOverride(name, lat, lng, 'hide');
}

function deleteSite(name, lat, lng) {
    if (!confirm(`ADVARSEL: Vil du slette "${name}" permanent?\n\n(Dette vil fjerne den helt fra kartet og databasen, selv etter synkronisering med Google My Maps.)`)) return;
    saveOverride(name, lat, lng, 'delete');
}

// ID-based wrappers (safe for names with special characters)
function hideSiteById(id) {
    const site = allSites.find(s => s.id === id);
    if (!site) { alert('Punkt ikke funnet'); return; }
    hideSite(site.name, site.lat, site.lng);
}

function deleteSiteById(id) {
    const site = allSites.find(s => s.id === id);
    if (!site) { alert('Punkt ikke funnet'); return; }
    deleteSite(site.name, site.lat, site.lng);
}

function saveOverride(name, lat, lng, action, value = null) {
    showAdminStatus("Lagrer override...", "warn");
    fetch('save_override.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, lat, lng, action, value })
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                showAdminStatus("Lagret!", "ok");
                setTimeout(() => {
                    const confirmReload = confirm(`Override lagret for "${name}". Vil du laste inn kartet på nytt nå for å se endringene?`);
                    if (confirmReload) location.reload();
                }, 500);
            } else {
                showAdminStatus("Feil: " + data.message, "error");
                alert("Feil ved lagring: " + data.message);
            }
        })
        .catch(err => {
            console.error("Override error", err);
            showAdminStatus("Nettverksfeil", "error");
        });
}

// --- REDIGER PUNKT NAVN/BESKRIVELSE ---
function editSiteLabel(siteId) {
    const site = allSites.find(s => s.id === siteId);
    if (!site) return;

    // Remove any existing edit modal
    const existing = document.getElementById('edit-label-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'edit-label-modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.85); z-index: 20000; display: flex;
        align-items: center; justify-content: center; backdrop-filter: blur(6px);
    `;

    const currentName = site.displayName || site.name;
    const currentDesc = site.displayDesc || site.description || '';
    const safeDesc = currentDesc.replace(/<br>/g, '\n').replace(/<[^>]+>/g, '');

    modal.innerHTML = `
        <div style="background: #111827; width: 90%; max-width: 520px; border-radius: 14px;
            border: 1px solid #38bdf8; padding: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.6);
            font-family: 'Outfit', sans-serif;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h3 style="margin: 0; color: #38bdf8; font-size: 1.1rem;">✏️ Rediger visningsnavn/beskrivelse</h3>
                <button onclick="document.getElementById('edit-label-modal').remove()"
                    style="background: none; border: none; color: #94a3b8; font-size: 1.4em; cursor: pointer;">×</button>
            </div>
            <div style="color: #64748b; font-size: 0.8rem; margin-bottom: 16px;">
                Originalt Google-navn: <strong style="color: #94a3b8;">${site.name}</strong><br>
                Endringer her påvirker kun visningen — synkronisering bruker fortsatt originalnavnet.
            </div>
            <label style="color: #e2e8f0; font-size: 0.85rem; font-weight: 600;">Visningsnavn</label>
            <input id="edit-label-name" type="text" value="${currentName.replace(/"/g, '&quot;')}"
                style="width: 100%; margin: 6px 0 14px; padding: 10px 12px; background: #1e293b;
                border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.95rem;
                box-sizing: border-box;">
            <label style="color: #e2e8f0; font-size: 0.85rem; font-weight: 600;">Beskrivelse (valgfritt)</label>
            <textarea id="edit-label-desc" rows="5"
                style="width: 100%; margin: 6px 0 18px; padding: 10px 12px; background: #1e293b;
                border: 1px solid #334155; color: white; border-radius: 8px; font-size: 0.9rem;
                box-sizing: border-box; resize: vertical; line-height: 1.5;">${safeDesc}</textarea>
            <div style="display: flex; gap: 10px;">
                <button onclick="saveSiteLabel(${siteId})"
                    style="flex: 1; padding: 12px; background: linear-gradient(135deg, #0ea5e9, #0284c7);
                    border: none; color: white; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 0.95rem;">
                    💾 Lagre
                </button>
                <button onclick="clearSiteLabel(${siteId})"
                    style="padding: 12px 16px; background: rgba(239,68,68,0.1); border: 1px solid #ef4444;
                    color: #ef4444; border-radius: 8px; cursor: pointer; font-size: 0.85rem;">
                    Tilbakestill
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    // Focus the name field
    setTimeout(() => document.getElementById('edit-label-name').focus(), 100);
}

function saveSiteLabel(siteId) {
    const site = allSites.find(s => s.id === siteId);
    if (!site) return;

    const newName = document.getElementById('edit-label-name').value.trim();
    const newDesc = document.getElementById('edit-label-desc').value.trim();

    if (!newName) { alert('Visningsnavn kan ikke være tomt.'); return; }

    saveOverride(site.name, site.lat, site.lng, 'rename', { displayName: newName, displayDesc: newDesc });
    document.getElementById('edit-label-modal').remove();
}

function clearSiteLabel(siteId) {
    const site = allSites.find(s => s.id === siteId);
    if (!site) return;
    if (!confirm(`Vil du fjerne navneoverride for "${site.name}" og gå tilbake til Google-navn?`)) return;
    saveOverride(site.name, site.lat, site.lng, 'rename', { displayName: null, displayDesc: null });
    document.getElementById('edit-label-modal').remove();
}

// ============================================================
// FASE 2: Legg til nytt punkt + Gjeste-modus + Godkjenning
// ============================================================

let isGuestMode = false;
let addPointMode = false;
let tempPlacementMarker = null;

// --- Guest mode ---

function showGuestInfoModal() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && !sidebar.classList.contains('collapsed')) toggleSidebar();
    document.getElementById('guest-info-modal').style.display = 'flex';
}

function startGuestMode() {
    document.getElementById('guest-info-modal').style.display = 'none';
    isGuestMode = true;
    window.isAdminMode = false; // Gjest er ikke admin

    // Close sidebar
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.remove('open');

    // Inject minimal toolbar
    injectGuestToolbar();
    showAdminStatus("Gjeste-modus: Klikk 'Nytt punkt' for å starte.", "ok");
}

function injectGuestToolbar() {
    const existing = document.getElementById('admin-toolbar');
    if (existing) existing.remove();

    const toolbar = document.createElement('div');
    toolbar.id = 'admin-toolbar';
    toolbar.style.cssText = `
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: #111827; color: white; padding: 10px 20px; border-radius: 50px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1500;
        display: flex; gap: 15px; align-items: center; border: 1px solid #0ea5e9;
        font-family: 'Outfit', sans-serif; font-size: 0.9rem;
    `;
    toolbar.innerHTML = `
        <div id="admin-status" style="font-weight: 600; color: #10b981;">Gjest</div>
        <div style="width: 1px; height: 20px; background: #374151;"></div>
        <button onclick="startAddPointMode()" style="background:none; border:none; color:#0ea5e9; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">
            <span style="font-size:1.2em;">➕</span> Nytt punkt
        </button>
        <button onclick="exitGuestMode()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-weight:bold;">
            Avslutt
        </button>
    `;
    document.body.appendChild(toolbar);
}

function exitGuestMode() {
    isGuestMode = false;
    addPointMode = false;
    if (tempPlacementMarker) { tempPlacementMarker.remove(); tempPlacementMarker = null; }
    map.off('click', handleAddPointMapClick);
    document.getElementById('map').style.cursor = '';
    const toolbar = document.getElementById('admin-toolbar');
    if (toolbar) toolbar.remove();
}

// --- Add point mode (shared by admin and guest) ---

function startAddPointMode() {
    addPointMode = true;
    showAdminStatus("Klikk på kartet for å plassere nytt punkt", "warn");
    document.getElementById('map').style.cursor = 'crosshair';
    map.once('click', handleAddPointMapClick);
}

function handleAddPointMapClick(e) {
    if (!addPointMode) return;
    addPointMode = false;
    document.getElementById('map').style.cursor = '';

    const lat = e.latlng.lat;
    const lng = e.latlng.lng;

    // Place temp yellow marker
    if (tempPlacementMarker) tempPlacementMarker.remove();
    tempPlacementMarker = L.circleMarker([lat, lng], {
        radius: 12, color: '#f59e0b', fillColor: '#f59e0b', fillOpacity: 0.6, weight: 3
    }).addTo(map);

    showAddPointModal(lat, lng);
}

function showAddPointModal(lat, lng) {
    const existing = document.getElementById('add-point-modal');
    if (existing) existing.remove();

    const role = isGuestMode ? 'guest' : 'admin';
    const submitLabel = isGuestMode ? '📩 Send til godkjenning' : '💾 Lagre punkt';

    const categories = [
        { key: 'GRUVE', label: 'Gruve / Skjerp' },
        { key: 'HULE', label: 'Hule' },
        { key: 'BYGDEBORG', label: 'Bygdeborg' },
        { key: 'GAPAHUK', label: 'Gapahuk' },
        { key: 'VANN', label: 'Vann / Dam' },
        { key: 'DIVERSE', label: 'Diverse' },
        { key: 'UTSIKT', label: 'Utsikt' },
        { key: 'HUSTUFT', label: 'Hustuft' },
        { key: 'GRENSESTEIN', label: 'Grensestein' },
        { key: 'GRAVHAUG', label: 'Gravhaug' },
    ];
    const catOptions = categories.map(c => `<option value="${c.key}">${c.label}</option>`).join('');

    const modal = document.createElement('div');
    modal.id = 'add-point-modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.85); z-index: 20000; display: flex;
        align-items: flex-start; justify-content: center; backdrop-filter: blur(6px);
        overflow-y: auto; padding: 30px 0;
    `;

    const fieldStyle = `width:100%; margin:5px 0 12px; padding:10px 12px; background:#1e293b; border:1px solid #334155; color:white; border-radius:8px; font-size:0.9rem; box-sizing:border-box; font-family:'Outfit',sans-serif;`;
    const labelStyle = `color:#e2e8f0; font-size:0.8rem; font-weight:600; display:block;`;

    modal.innerHTML = `
        <div style="background:#111827; width:90%; max-width:560px; border-radius:14px; border:1px solid #0ea5e9; padding:24px; box-shadow:0 20px 50px rgba(0,0,0,0.6); font-family:'Outfit',sans-serif; max-height:90vh; overflow-y:auto;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                <h3 style="margin:0; color:#0ea5e9; font-size:1.1rem;">📍 Nytt punkt</h3>
                <button onclick="cancelAddPoint()" style="background:none; border:none; color:#94a3b8; font-size:1.4em; cursor:pointer;">×</button>
            </div>

            <label style="${labelStyle}">Ditt navn *</label>
            <input id="ap-guest-name" type="text" placeholder="F.eks. Ola Nordmann" style="${fieldStyle}">

            <label style="${labelStyle}">Punktnavn *</label>
            <input id="ap-name" type="text" placeholder="F.eks. Gammel gruve ved Ulvsvann" style="${fieldStyle}">

            <label style="${labelStyle}">Kategori *</label>
            <select id="ap-catkey" style="${fieldStyle}">${catOptions}</select>

            <label style="${labelStyle}">Beskrivelse</label>
            <textarea id="ap-desc" rows="3" placeholder="Beskriv funnstedet..." style="${fieldStyle} resize:vertical;"></textarea>

            <div style="display:flex; gap:10px;">
                <div style="flex:1;">
                    <label style="${labelStyle}">Breddegrad (lat)</label>
                    <input id="ap-lat" type="number" step="0.00001" value="${lat.toFixed(7)}" style="${fieldStyle}">
                </div>
                <div style="flex:1;">
                    <label style="${labelStyle}">Lengdegrad (lng)</label>
                    <input id="ap-lng" type="number" step="0.00001" value="${lng.toFixed(7)}" style="${fieldStyle}">
                </div>
            </div>

            <label style="${labelStyle}">Last opp bilder</label>
            <input id="ap-files" type="file" accept="image/*" multiple style="${fieldStyle} padding:8px;">

            <label style="${labelStyle}">Eller lim inn bilde-URL(er) (én per linje)</label>
            <textarea id="ap-image-urls" rows="2" placeholder="https://..." style="${fieldStyle} resize:vertical;"></textarea>

            <label style="${labelStyle}">Nettside-lenker (én per linje)</label>
            <textarea id="ap-links" rows="2" placeholder="https://..." style="${fieldStyle} resize:vertical;"></textarea>

            <label style="${labelStyle}">Kreditering (hvem er kilden?)</label>
            <input id="ap-credit" type="text" placeholder="F.eks. Telemark Museum" style="${fieldStyle}">

            <label style="${labelStyle}">Kreditering-URL</label>
            <input id="ap-credit-url" type="text" placeholder="https://..." style="${fieldStyle}">

            <div style="display:flex; gap:10px; margin-top:8px;">
                <button onclick="submitNewPoint('${role}')" style="flex:1; padding:13px; background:linear-gradient(135deg,#0ea5e9,#0284c7); border:none; color:white; border-radius:8px; cursor:pointer; font-weight:700; font-size:0.95rem;">
                    ${submitLabel}
                </button>
                <button onclick="cancelAddPoint()" style="padding:13px 18px; background:rgba(239,68,68,0.1); border:1px solid #ef4444; color:#ef4444; border-radius:8px; cursor:pointer; font-size:0.9rem;">
                    Avbryt
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(modal);
    setTimeout(() => document.getElementById('ap-guest-name').focus(), 100);
}

function cancelAddPoint() {
    const modal = document.getElementById('add-point-modal');
    if (modal) modal.remove();
    if (tempPlacementMarker) { tempPlacementMarker.remove(); tempPlacementMarker = null; }
    if (isGuestMode) showAdminStatus("Gjeste-modus: Klikk 'Nytt punkt' for å starte.", "ok");
    else showAdminStatus("Klar.", "ok");
}

async function submitNewPoint(role) {
    const name = document.getElementById('ap-name').value.trim();
    const guestName = document.getElementById('ap-guest-name').value.trim();
    const catKey = document.getElementById('ap-catkey').value;
    const desc = document.getElementById('ap-desc').value.trim();
    const lat = document.getElementById('ap-lat').value;
    const lng = document.getElementById('ap-lng').value;
    const imageUrls = document.getElementById('ap-image-urls').value.trim();
    const links = document.getElementById('ap-links').value.trim();
    const credit = document.getElementById('ap-credit').value.trim();
    const creditUrl = document.getElementById('ap-credit-url').value.trim();
    const filesInput = document.getElementById('ap-files');

    if (!name) { alert('Punktnavn er påkrevd'); return; }
    if (!guestName) { alert('Ditt navn er påkrevd'); return; }
    if (!lat || !lng) { alert('Koordinater er påkrevd'); return; }

    const formData = new FormData();
    formData.append('role', role);
    formData.append('name', name);
    formData.append('guestName', guestName);
    formData.append('catKey', catKey);
    formData.append('description', desc);
    formData.append('lat', lat);
    formData.append('lng', lng);
    formData.append('imageUrls', imageUrls);
    formData.append('links', links);
    formData.append('credit', credit);
    formData.append('creditUrl', creditUrl);

    // Append files
    if (filesInput.files.length > 0) {
        for (let i = 0; i < filesInput.files.length; i++) {
            formData.append('images[]', filesInput.files[i]);
        }
    }

    showAdminStatus("Lagrer...", "warn");

    try {
        const resp = await fetch('add_point.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.success) {
            // Remove modal and temp marker
            cancelAddPoint();

            if (role === 'guest') {
                alert('Takk! Punktet er sendt til godkjenning. Du vil se det på kartet når administrator har verifisert det.');
                showAdminStatus("Punkt sendt til godkjenning!", "ok");
            } else {
                // Admin: add marker to map immediately without reload
                showAdminStatus("Punkt opprettet! (ID: " + data.id + ")", "ok");
                // Reload to show new point
                setTimeout(() => location.reload(), 1000);
            }
        } else {
            alert('Feil: ' + data.message);
            showAdminStatus("Feil ved lagring", "error");
        }
    } catch (e) {
        console.error(e);
        alert('Nettverksfeil ved lagring');
        showAdminStatus("Nettverksfeil", "error");
    }
}

// --- Admin: Approval panel ---

async function showApprovalPanel() {
    const existing = document.getElementById('approval-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'approval-modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.8); z-index: 10001; display: flex;
        align-items: center; justify-content: center; backdrop-filter: blur(5px);
    `;
    modal.innerHTML = `
        <div style="background: #111827; width: 90%; max-width: 650px; max-height: 80vh; border-radius: 12px; border: 1px solid #f59e0b; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
            <div style="padding: 15px 20px; border-bottom: 1px solid #374151; display: flex; justify-content: space-between; align-items: center; background: #1f2937;">
                <h3 style="margin: 0; color: #f59e0b;">👥 Gjestepunkter til godkjenning</h3>
                <button onclick="document.getElementById('approval-modal').remove()" style="background:none; border:none; color: #94a3b8; font-size: 1.5em; cursor: pointer;">&times;</button>
            </div>
            <div id="approval-list" style="padding: 0; overflow-y: auto; flex-grow: 1;">
                <div style="padding: 20px; text-align: center; color: #94a3b8;">Laster...</div>
            </div>
        </div>
    `;
    document.body.appendChild(modal);

    try {
        const resp = await fetch('pending_points.json?v=' + Date.now());
        const list = document.getElementById('approval-list');

        if (!resp.ok) {
            list.innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;">Ingen gjestepunkter venter.</div>';
            return;
        }

        const pending = await resp.json();
        if (!Array.isArray(pending) || pending.length === 0) {
            list.innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;">Ingen gjestepunkter venter.</div>';
            return;
        }

        list.innerHTML = '';
        pending.forEach(p => {
            const props = p.properties;
            const guest = props.pendingGuest || {};
            const date = guest.submitted ? new Date(guest.submitted * 1000).toLocaleString('no-NO') : '?';
            const row = document.createElement('div');
            row.style.cssText = 'padding: 15px 20px; border-bottom: 1px solid #374151;';
            row.innerHTML = `
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                    <div style="flex:1;">
                        <div style="font-weight:700; color:#f9fafb; font-size:1rem; margin-bottom:4px;">${props.name}</div>
                        <div style="color:#94a3b8; font-size:0.85em;">Innsendt av <strong style="color:#0ea5e9;">${guest.name || '?'}</strong> — ${date}</div>
                        <div style="color:#64748b; font-size:0.8em; margin-top:3px;">Kategori: ${props.catKey} | Posisjon: ${props.lat?.toFixed(5)}, ${props.lng?.toFixed(5)}</div>
                        ${props.cleanDesc ? `<div style="color:#94a3b8; font-size:0.85em; margin-top:6px;">${props.cleanDesc}</div>` : ''}
                        ${props.localImages?.length ? `<div style="margin-top:6px;"><img src="${props.localImages[0]}" style="max-height:80px; border-radius:6px; border:1px solid #374151;"></div>` : ''}
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px; min-width:100px;">
                        <button onclick="approvePoint(${props.id})" style="padding:8px 12px; background:#10b981; border:none; color:white; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.85em;">✅ Godkjenn</button>
                        <button onclick="rejectPoint(${props.id})" style="padding:8px 12px; background:rgba(239,68,68,0.1); border:1px solid #ef4444; color:#ef4444; border-radius:6px; cursor:pointer; font-weight:600; font-size:0.85em;">❌ Avvis</button>
                    </div>
                </div>
            `;
            list.appendChild(row);
        });
    } catch (e) {
        document.getElementById('approval-list').innerHTML = '<div style="padding: 20px; text-align: center; color: #94a3b8;">Ingen gjestepunkter venter.</div>';
    }
}

async function approvePoint(id) {
    if (!confirm('Godkjenne dette punktet?')) return;
    try {
        const resp = await fetch('approve_point.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await resp.json();
        if (data.success) {
            alert(data.message);
            showApprovalPanel(); // Refresh
        } else {
            alert('Feil: ' + data.message);
        }
    } catch (e) { alert('Nettverksfeil'); }
}

async function rejectPoint(id) {
    if (!confirm('Avvise og slette dette punktet?')) return;
    try {
        const resp = await fetch('reject_point.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const data = await resp.json();
        if (data.success) {
            alert(data.message);
            showApprovalPanel(); // Refresh
        } else {
            alert('Feil: ' + data.message);
        }
    } catch (e) { alert('Nettverksfeil'); }
}

// --- Check pending count for admin badge ---

async function checkPendingCount() {
    try {
        const resp = await fetch('pending_points.json?v=' + Date.now());
        if (!resp.ok) return 0;
        const pending = await resp.json();
        return Array.isArray(pending) ? pending.length : 0;
    } catch (e) { return 0; }
}

// --- Override admin toolbar to include new buttons ---

const _originalInjectAdminToolbar = injectAdminToolbar;
injectAdminToolbar = function () {
    const existing = document.getElementById('admin-toolbar');
    if (existing) existing.remove();

    const toolbar = document.createElement('div');
    toolbar.id = 'admin-toolbar';
    toolbar.style.cssText = `
        position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%);
        background: #111827; color: white; padding: 10px 20px; border-radius: 50px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.5); z-index: 1500;
        display: flex; gap: 15px; align-items: center; border: 1px solid #374151;
        font-family: 'Outfit', sans-serif; font-size: 0.9rem;
    `;

    toolbar.innerHTML = `
        <div id="admin-status" style="font-weight: 600; color: #10b981;">Klar</div>
        <div style="width: 1px; height: 20px; background: #374151;"></div>
        <button onclick="undoLastMove()" id="btn-undo" style="background:none; border:none; color:#94a3b8; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">
            <span style="font-size:1.2em;">↩</span> Angre
        </button>
        <button onclick="showChangelog()" style="background:none; border:none; color:#38bdf8; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">
            <span style="font-size:1.2em;">📜</span> Logg
        </button>
        <button onclick="startAddPointMode()" style="background:none; border:none; color:#0ea5e9; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">
            <span style="font-size:1.2em;">➕</span> Nytt punkt
        </button>
        <button id="btn-approve" onclick="showApprovalPanel()" style="background:none; border:none; color:#f59e0b; cursor:pointer; font-weight:bold; display:flex; align-items:center; gap:5px;">
            <span style="font-size:1.2em;">👥</span> Godkjenn
        </button>
        <button onclick="exitAdminMode()" style="background:none; border:none; color:#ef4444; cursor:pointer; font-weight:bold;">
            Avslutt
        </button>
    `;
    document.body.appendChild(toolbar);

    // Check pending count in background (non-blocking)
    checkPendingCount().then(count => {
        if (count > 0) {
            const btn = document.getElementById('btn-approve');
            if (btn) btn.innerHTML = `<span style="font-size:1.2em;">👥</span> Godkjenn<span style="background:#ef4444; color:white; font-size:0.7em; padding:1px 6px; border-radius:50%; margin-left:4px;">${count}</span>`;
            showAdminStatus(`${count} gjestepunkt${count > 1 ? 'er' : ''} venter på godkjenning`, "warn");
            setTimeout(() => showAdminStatus("Admin-modus: KLAR. Klikk et punkt for å flytte.", "ok"), 4000);
        }
    });
};

