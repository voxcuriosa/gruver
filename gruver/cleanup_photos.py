import json
import os

json_file = 'full_data.json'
links_to_remove = [
    'https://photos.app.goo.gl/SkPQzkgqQ3E6zR7n6',
    'https://photos.app.goo.gl/ZKktL6ESvvG6rdcN9',
    'https://photos.google.com/share/AF1QipPtACB3BbwGKRDDumcBHLXQygkEVLcwqaPUhcr4JVkTKo_wZQy5zP9jpCYv0LzJfA?key=QmZWNWNYY2FTTmRFZmQ5R2kxc182QnlsV2lPazdR'
]

if not os.path.exists(json_file):
    print(f"Error: {json_file} not found.")
    exit(1)

with open(json_file, 'r', encoding='utf-8') as f:
    data = json.load(f)

count_deleted = 0
for feature in data['features']:
    props = feature['properties']
    
    # 1. Clean Desc
    if 'cleanDesc' in props:
        desc = props['cleanDesc']
        for url in links_to_remove:
            if url in desc:
                # Remove URL and common prefixes/suffixes like "og", "Nettside:", etc.
                # Pattern: "Nettside: [URL]" or "... og [URL]"
                desc = desc.replace(f" og {url}", "")
                desc = desc.replace(f"{url} og ", "")
                desc = desc.replace(url, "")
                
        # Cleanup "Nettside:" labels that have no links left
        desc = desc.replace("Nettside: ]]>", "]]>")
        desc = desc.replace("Nettside: \n", "\n")
        desc = desc.replace("Nettside:\n", "\n")
        
        # Final trim for trailing "og" or whitespace
        desc = desc.strip()
        if desc.endswith(" og]]>"): desc = desc.replace(" og]]>", "]]>")
        if desc.endswith(" og ]]>"): desc = desc.replace(" og ]]>", "]]>")
        
        props['cleanDesc'] = desc

    # 2. Links Array
    if 'links' in props:
        new_links = []
        for l in props['links']:
            # Strip trailing KML artifacts if present (sometimes ]]> is attached)
            clean_l = l.split(']')[0].strip() 
            if clean_l not in links_to_remove and l not in links_to_remove:
                new_links.append(l)
            else:
                count_deleted += 1
        props['links'] = new_links

with open(json_file, 'w', encoding='utf-8') as f:
    json.dump(data, f, ensure_ascii=False, indent=4)

print(f"Done. Removed {count_deleted} link instances.")
