import json
from collections import defaultdict

def check_duplicates():
    with open('full_data.json', 'r', encoding='utf-8') as f:
        data = json.load(f)

    # Map filename -> list of (id, name)
    usage = defaultdict(list)

    for feature in data['features']:
        props = feature['properties']
        fid = props.get('id')
        fname = props.get('name')
        
        if 'localImages' in props:
            for img in props['localImages']:
                usage[img].append((fid, fname))

    # Filter for duplicates
    duplicates = {k: v for k, v in usage.items() if len(v) > 1}

    print(f"Found {len(duplicates)} images used by multiple points.")
    
    print("\nTop 10 duplicates:")
    for i, (img, points) in enumerate(sorted(duplicates.items(), key=lambda x: len(x[1]), reverse=True)[:10]):
        print(f"\n{i+1}. {img} is used by {len(points)} points:")
        for pid, pname in points[:5]:
             print(f"   - ID {pid}: {pname}")
        if len(points) > 5:
            print(f"   ... and {len(points)-5} more")

if __name__ == "__main__":
    check_duplicates()
