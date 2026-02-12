
import os

file_path = r"c:\Users\chr1203\OneDrive - Telemark fylkeskommune\Jobb\Diverse\Antigravity\vox_portal\gruver\viewer.js"

with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

new_lines = []
skip = False
desktop_logic_removed = False

desktop_logic_code = """
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
                if (container.classList.contains('leaflet-control-layers-expanded')) {
                    layerControl.collapse();
                    desktopMapBtn.classList.remove('active');
                } else {
                    layerControl.expand();
                    desktopMapBtn.classList.add('active');
                    if (flyfotoMenu) flyfotoMenu.style.display = 'none';
                    if (desktopFlyfotoBtn) desktopFlyfotoBtn.classList.remove('active');
                }
            }
        });
    }

    if (flyfotoList) {
        Object.keys(flyfotoLayers).forEach(name => {
            const item = document.createElement('div');
            item.className = 'flyfoto-item';
            item.innerHTML = `<span>${name}</span>`;
            item.onclick = () => {
                map.eachLayer(layer => {
                    if (layer instanceof L.TileLayer && layer !== topoLayer && layer !== darkLabels) { 
                        Object.values(baseMaps).forEach(bl => { if (map.hasLayer(bl)) map.removeLayer(bl); });
                        Object.values(flyfotoLayers).forEach(fl => { if (map.hasLayer(fl)) map.removeLayer(fl); });
                    }
                });
                flyfotoLayers[name].addTo(map);
                document.querySelectorAll('.flyfoto-item').forEach(el => el.classList.remove('active'));
                item.classList.add('active');
                desktopFlyfotoBtn.classList.add('active');
            };
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
                if (layerControl) {
                    layerControl.collapse();
                    if (desktopMapBtn) desktopMapBtn.classList.remove('active');
                }
            }
        });
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#desktop-controls') && !e.target.closest('#flyfoto-menu') && !e.target.closest('.leaflet-control-layers')) {
            if (flyfotoMenu) flyfotoMenu.style.display = 'none';
            if (desktopFlyfotoBtn) desktopFlyfotoBtn.classList.remove('active');
            if (layerControl) {
                layerControl.collapse();
                if (desktopMapBtn) desktopMapBtn.classList.remove('active');
            }
        }
    });
"""

inserted_desktop_logic = False

for line in lines:
    if "// --- DESKTOP BUTTON LOGIC ---" in line:
        skip = True
        desktop_logic_removed = True
        continue
    
    if skip:
        if "} // End of initMap" in line:
            skip = False
            # Restore Opacity Logic variables
            new_lines.append("                    const slider = document.getElementById('opacity-slider');\n")
            new_lines.append("                    const valueDisplay = document.getElementById('opacity-value');\n")
        continue

    # Append the line
    new_lines.append(line)

    # Insert Desktop Logic after overlayremove block
    if "map.on('overlayremove'" in line:
         # We need to find the closing bracket for this block
         pass 

# To correctly insert Desktop Logic after 'overlayremove' block:
# searching for the closing }); of overlayremove is hard line-by-line without context.
# But we know markerLayer.addTo(map); follows it.
# So insert BEFORE markerLayer.addTo(map);

final_lines = []
for line in new_lines:
    if "markerLayer.addTo(map);" in line and not inserted_desktop_logic:
        final_lines.append(desktop_logic_code)
        inserted_desktop_logic = True
    final_lines.append(line)

with open(file_path, 'w', encoding='utf-8') as f:
    f.writelines(final_lines)

print("Repair complete.")
