import json
import os

# Paths
V42_DATA = r'backup\v42\full_data.json'
OVERRIDES_FILE = 'overrides.json'

def recover():
    if not os.path.exists(V42_DATA):
        print(f"Error: {V42_DATA} not found.")
        return

    with open(V42_DATA, 'r', encoding='utf-8') as f:
        v42_data = json.load(f)

    if os.path.exists(OVERRIDES_FILE):
        with open(OVERRIDES_FILE, 'r', encoding='utf-8') as f:
            overrides = json.load(f)
    else:
        overrides = []

    # Map existing overrides to avoid duplicates
    # We'll use a string key of name + rounded coords
    existing_keys = set()
    for o in overrides:
        if o['action'] == 'add_image':
            key = f"{o['name']}_{o['lat']:.5f}_{o['lng']:.5f}_{o['value']}"
            existing_keys.add(key)

    added_count = 0
    import time
    timestamp = int(time.time())

    for f in v42_data['features']:
        props = f['properties']
        name = props.get('name')
        lat = props.get('lat')
        lng = props.get('lng')
        img_list = props.get('images', [])
        
        # We only care about manual assets
        manual_assets = [img for img in img_list if 'assets/' in img or 'mining_map' in img]
        
        for asset in manual_assets:
            # Check if already in overrides
            key = f"{name}_{lat:.5f}_{lng:.5f}_{asset}"
            if key not in existing_keys:
                overrides.append({
                    "timestamp": timestamp,
                    "name": name,
                    "lat": lat,
                    "lng": lng,
                    "action": "add_image",
                    "value": asset
                })
                existing_keys.add(key)
                added_count += 1

    if added_count > 0:
        with open(OVERRIDES_FILE, 'w', encoding='utf-8') as f:
            json.dump(overrides, f, ensure_ascii=False, indent=4)
        print(f"Successfully recovered {added_count} manual image entries to {OVERRIDES_FILE}.")
    else:
        print("No new manual images found to recover.")

if __name__ == "__main__":
    recover()
