import json
import os

# Configuration
JSON_FILE = "full_data.json"
TARGET_CATKEY = "GRAVHAUG"
APPEND_TEXT = "<br><br><strong>Tips:</strong> Slå på kartlaget 'Kulturminner' for mer detaljert informasjon."

def update_descriptions():
    if not os.path.exists(JSON_FILE):
        print(f"Error: {JSON_FILE} not found.")
        return

    with open(JSON_FILE, "r", encoding="utf-8") as f:
        data = json.load(f)

    # Handle GeoJSON structure
    if isinstance(data, dict) and 'features' in data:
        sites = data['features']
    elif isinstance(data, list):
        sites = data
    else:
        print("Unknown JSON structure")
        return

    count = 0
    updated_count = 0

    for site in sites:
        props = site.get('properties', {})
        
        # Check catKey
        if props.get('catKey') == TARGET_CATKEY:
            count += 1
            
            # Check if text is already present
            current_desc = props.get('cleanDesc', '') # Use cleanDesc if it exists as source, but maybe modify description?
            # Actually description is usually constructed or stored. Let's list keys.
            # In previous steps we saw 'cleanDesc' and description might be generated?
            # Wait, the popup uses 'description' (which might be generated from cleanDesc+images etc?)
            # Or is 'description' a property?
            # Looking at previous view_file of full_data.json (step 2716), I see 'cleanDesc' but NO 'description' property in the JSON!
            # The 'viewer.js' likely uses 'cleanDesc' to build the popup description OR it expects 'description'.
            
            # Let's check viewer.js to be sure which field is used.
            # Step 2521 viewer.js snippet: let desc = site.description.replace...
            # But in full_data.json I only see 'cleanDesc'.
            # Ah, maybe convert_geojson.php or something transforms it?
            # Or maybe I missed 'description' in the JSON because I only saw a snippet.
            
            # Use 'cleanDesc' for now as that's what I see in the file.
            target_field = 'cleanDesc'
            if target_field not in props:
                # Fallback
                if 'description' in props:
                    target_field = 'description'
                else:
                    # Create it?
                    target_field = 'cleanDesc'
                    props[target_field] = ""
            
            if APPEND_TEXT not in props[target_field]:
                props[target_field] += APPEND_TEXT
                updated_count += 1
                
                # Also update 'description' if it exists independently
                if 'description' in props and target_field != 'description':
                     if APPEND_TEXT not in props['description']:
                        props['description'] += APPEND_TEXT


    if updated_count > 0:
        with open(JSON_FILE, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=4)
        print(f"Successfully updated {updated_count} Gravhauger entries.")
    else:
        print(f"No new updates needed (found {count} Gravhauger, text might already be present).")

if __name__ == "__main__":
    update_descriptions()
