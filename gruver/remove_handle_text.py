
import json

file_path = 'c:/Users/chr1203/OneDrive - Telemark fylkeskommune/Jobb/Diverse/Antigravity/vox_portal/gruver/full_data.json'
backup_path = 'c:/Users/chr1203/OneDrive - Telemark fylkeskommune/Jobb/Diverse/Antigravity/vox_portal/gruver/full_data_backup_v2.json'

text_to_remove = "og /handle/1956/3409"
alternate_text = "/handle/1956/3409"

with open(file_path, 'r', encoding='utf-8') as f:
    data = json.load(f)

# Create backup first (simple write of current data)
with open(backup_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=4)

count = 0
for feature in data['features']:
    props = feature['properties']
    if 'cleanDesc' in props and props['cleanDesc']:
        desc = props['cleanDesc']
        if alternate_text in desc:
            # Replace the specific 'og /handle...' first
            new_desc = desc.replace(text_to_remove, "")
            # Then clean up just the handle if it exists alone or with different spacing
            new_desc = new_desc.replace(alternate_text, "")
            # Clean up double spaces or trailing "og "
            new_desc = new_desc.replace("  ", " ").strip()
            if new_desc.endswith(" og"):
                new_desc = new_desc[:-3]
            
            if desc != new_desc:
                props['cleanDesc'] = new_desc
                count += 1
                print(f"Cleaned {props.get('name', 'Unknown')}")

with open(file_path, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=4)

print(f"Processed {count} entries.")
