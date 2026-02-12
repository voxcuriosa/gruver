import json
import os

def audit_images():
    json_path = 'full_data.json'
    bilder_dir = 'bilder'
    
    if not os.path.exists(json_path):
        print(f"Error: {json_path} not found.")
        return
        
    # 1. Get list of files in bilder/
    local_files = set()
    for f in os.listdir(bilder_dir):
        if f.lower().endswith(('.jpg', '.jpeg', '.png', '.gif', '.webp')):
            local_files.add(f)
            
    # 2. Get list of references in JSON
    referenced_files = set()
    try:
        with open(json_path, 'r', encoding='utf-8') as f:
            data = json.load(f)
            
        for feature in data.get('features', []):
            props = feature.get('properties', {})
            
            # Check localImages array
            for img_path in props.get('localImages', []):
                # localImages paths are usually like "bilder/filename.jpg"
                filename = os.path.basename(img_path)
                referenced_files.add(filename)
                
            # Check imageUrl field
            img_url = props.get('imageUrl')
            if img_url and 'bilder/' in img_url:
                filename = os.path.basename(img_url)
                referenced_files.add(filename)
                
    except Exception as e:
        print(f"Error parsing JSON: {e}")
        return
        
    # 3. Compare sets
    orphans = local_files - referenced_files
    missing = referenced_files - local_files
    
    print(f"--- Image Audit Report ---")
    print(f"Total files in bilder/: {len(local_files)}")
    print(f"Total files referenced in JSON: {len(referenced_files)}")
    
    if orphans:
        print(f"\nFound {len(orphans)} orphan files (files in bilder/ NOT used in map):")
        # Print first 20 orphans
        for op in sorted(list(orphans))[:20]:
            print(f"  - {op}")
        if len(orphans) > 20:
            print(f"  ... and {len(orphans)-20} more.")
    else:
        print("\nNo orphan files found. All images in bilder/ are used.")
        
    if missing:
        print(f"\nFound {len(missing)} broken references (referenced in map but MISSING in bilder/):")
        for ms in sorted(list(missing))[:20]:
            print(f"  - {ms}")
        if len(missing) > 20:
            print(f"  ... and {len(missing)-20} more.")
    else:
        print("\nNo broken references found. All referenced images exist.")

if __name__ == "__main__":
    audit_images()
