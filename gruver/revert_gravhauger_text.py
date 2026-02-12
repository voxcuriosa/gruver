import json
import os

# Configuration
JSON_FILE = "full_data.json"
TARGET_CATKEY = "GRAVHAUG"
REMOVE_TEXT = "<br><br><strong>Tips:</strong> Slå på kartlaget 'Kulturminner' for mer detaljert informasjon."

def revert_descriptions():
    if not os.path.exists(JSON_FILE):
        print(f"Error: {JSON_FILE} not found.")
        return

    with open(JSON_FILE, "r", encoding="utf-8") as f:
        data = json.load(f)

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
        
        # We can check catKey or just checking if the text exists and remove it globally if safer, 
        # but let's stick to the ones we likely modified or just check all.
        # Since the text is specific, removing it from ALL entries is safe and ensures cleanup.
        
        modified = False
        for field in ['cleanDesc', 'description']:
            if field in props and REMOVE_TEXT in props[field]:
                props[field] = props[field].replace(REMOVE_TEXT, "")
                modified = True
        
        if modified:
            updated_count += 1

    if updated_count > 0:
        with open(JSON_FILE, "w", encoding="utf-8") as f:
            json.dump(data, f, ensure_ascii=False, indent=4)
        print(f"Successfully reverted text in {updated_count} entries.")
    else:
        print("No entries needed reverting.")

if __name__ == "__main__":
    revert_descriptions()
