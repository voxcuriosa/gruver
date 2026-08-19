import os
import re

path = r"c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\viewer.js"

try:
    with open(path, "r", encoding="utf-8") as f:
        content = f.read()
except:
    with open(path, "r", encoding="cp1252") as f:
        content = f.read()

# 1. Update toggleMapLock to provide visual feedback and update button text
new_toggle_map_lock = """
/**
 * Låser kartet så det ikke kan panoreres
 */
function toggleMapLock(locked) {
    isAdminMapLocked = locked;
    const btn = document.getElementById('admin-move-toggle');
    if (isAdminMapLocked) {
        document.body.classList.add('map-locked');
        if (map.dragging) map.dragging.disable();
        if (map.touchZoom) map.touchZoom.disable();
        if (map.doubleClickZoom) map.doubleClickZoom.disable();
        if (map.scrollWheelZoom) map.scrollWheelZoom.disable();
        if (btn) btn.innerText = "🛑 Flytt (LÅST)";
        console.log("Kart låst");
    } else {
        document.body.classList.remove('map-locked');
        if (map.dragging) map.dragging.enable();
        if (map.touchZoom) map.touchZoom.enable();
        if (map.doubleClickZoom) map.doubleClickZoom.enable();
        if (map.scrollWheelZoom) map.scrollWheelZoom.enable();
        if (btn && isAdminMoveEnabled) btn.innerText = "🛑 Stopp flytt";
        console.log("Kart låst opp");
    }
}"""

content = re.sub(r"function toggleMapLock\(locked\) \{[\s\S]*?\}", new_toggle_map_lock, content)

# 2. Fix toggleAdminMoveMode to show/hide lock container correctly
content = content.replace("btn.innerText = \"🛑 Stopp flytt\";", "btn.innerText = \"🛑 Stopp flytt\";\n        document.getElementById('admin-map-lock-container').style.display = 'flex';")

# 3. Enhance applyDraggingFixes with more aggressive event handling
new_apply_fixes = """function applyDraggingFixes(marker, site) {
    const el = marker._icon;
    if (!el) return;

    L.DomEvent.disableClickPropagation(el);
    L.DomEvent.disableScrollPropagation(el);

    marker.off('dragstart');
    marker.off('dragend');

    L.DomEvent.on(el, 'mousedown touchstart', (e) => {
        if (isAdminMoveEnabled) {
            L.DomEvent.stopPropagation(e);
            if (map.dragging) map.dragging.disable();
        }
    }, this);

    marker.on('dragstart', (e) => {
        if (isAdminMoveEnabled) {
            marker.closePopup();
            document.body.classList.add('dragging-active');
            if (map.dragging) map.dragging.disable();
        }
    });

    marker.on('dragend', (e) => {
        if (!isAdminMapLocked) {
            if (map.dragging) map.dragging.enable();
        }
        document.body.classList.remove('dragging-active');
        if (isAdminMoveEnabled) {
            handleMarkerDragEnd(e, site);
        }
    });
}"""

content = re.sub(r"function applyDraggingFixes\(marker, site\) \{[\s\S]*?\}\n\n// Dummy-funksjoner", 
                 new_apply_fixes + "\n\n// Dummy-funksjoner", content)

with open(path, "w", encoding="utf-8") as f:
    f.write(content)
print("Successfully patched viewer.js with enhanced Map Lock and fixes")
