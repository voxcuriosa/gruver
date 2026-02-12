import json
import os
import re

def get_safe_name(name):
    if not name: return "Ukjent"
    # Replicate JS logic:
    # 1. Remove CDATA
    clean = re.sub(r'<!\[CDATA\[(.*?)\]\]>', r'\1', name)
    # 2. Normalize whitespace
    clean = re.sub(r'\r?\n|\r', ' ', clean).strip()
    # 3. Replace non-alphanumeric (allowing Norwegian) with _
    # Note: re.sub with [^...] works but we need to ensure unicode
    clean = re.sub(r'[^a-zA-Z0-9æøåÆØÅ]', '_', clean)
    # 4. Collapse underscores and strip
    clean = re.sub(r'_+', '_', clean).strip('_')
    return clean

def audit_orphans():
    json_path = 'full_data.json'
    bilder_dir = 'bilder'
    
    # 1. Get referenced files
    referenced_files = set()
    with open(json_path, 'r', encoding='utf-8') as f:
        data = json.load(f)
        for feature in data.get('features', []):
            for img_path in feature.get('properties', {}).get('localImages', []):
                referenced_files.add(os.path.basename(img_path))
    
    # 2. Get local files and orphans
    local_files = [f for f in os.listdir(bilder_dir) if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
    orphans = [f for f in local_files if f not in referenced_files]
    
    print(f"Total orphans: {len(orphans)}")
    
    unaccounted_for = []
    
    for orphan in orphans:
        # Try to "clean" the orphan name itself to see if it matches a reference
        # Orphans like "Bruberg_(vest)_1.jpg" -> "Bruberg_vest_1.jpg"
        base, ext = os.path.splitext(orphan)
        safe_base = get_safe_name(base)
        safe_name = safe_base + ext # Note: .jpg vs .JPG might matter but typically lower in our script
        
        # Check if the "safe" version of this filename is in the references
        if safe_name in referenced_files:
            # print(f"MATCH: {orphan} -> {safe_name}")
            pass
        else:
            unaccounted_for.append(orphan)
            
    print(f"\n--- Orphans WITHOUT replacements: {len(unaccounted_for)} ---")
    if unaccounted_for:
        for u in unaccounted_for[:50]:
            print(f"  - {u}")
        if len(unaccounted_for) > 50:
            print(f"  ... and {len(unaccounted_for)-50} more.")
    else:
        print("ALL orphans have a corresponding 'safe' version currently linked in the map!")

if __name__ == "__main__":
    audit_orphans()
